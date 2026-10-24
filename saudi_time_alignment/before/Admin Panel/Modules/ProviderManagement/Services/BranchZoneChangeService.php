<?php

namespace Modules\ProviderManagement\Services;

use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\Booking;
use Modules\ProviderManagement\Entities\{BranchServiceArea, Provider};
use Modules\ServiceManagement\Entities\{BranchServiceAvailability, Variation};
use Modules\ServiceManagement\Services\ServicePriceResolver;
use Modules\ZoneManagement\Entities\Zone;

final class BranchZoneChangeService
{
    public function preview(Provider $branch, string $zone): array
    {
        Zone::whereKey($zone)->ofStatus(1)->firstOrFail();
        $areas = BranchServiceArea::where('provider_id', $branch->id)->orderBy('id')->get()->map(function ($area) use ($zone) {
            $inside = DB::table('zones')->where('id', $zone)->whereRaw('ST_Contains(coordinates, ST_GeomFromText(?, 0))', [$area->coordinates->toWkt()])->exists();
            return ['id' => $area->id, 'name' => $area->name, 'fits_new_zone' => $inside, 'is_active' => (bool) $area->is_active];
        })->all();
        $permissions = BranchServiceAvailability::where('provider_id', $branch->id)->orderBy('service_id')->get(['service_id', 'admin_enabled', 'is_active'])->toArray();
        $missing = [];
        foreach ($permissions as $permission) {
            foreach (Variation::withoutGlobalScopes()->where('service_id', $permission['service_id'])->where('is_active', true)->pluck('variant_key')->unique() as $key) {
                try { app(ServicePriceResolver::class)->resolve($permission['service_id'], $key, $zone); }
                catch (\Illuminate\Validation\ValidationException $error) { $missing[] = ['service_id' => $permission['service_id'], 'variant_key' => $key]; }
            }
        }
        $coordinates = $branch->coordinates ?: [];
        $lat = $coordinates['latitude'] ?? $coordinates['lat'] ?? null; $lon = $coordinates['longitude'] ?? $coordinates['lon'] ?? null;
        $fits = $lat !== null && $lon !== null && app(\App\Services\GeoService::class)->containing(Zone::query(), 'coordinates', $lat, $lon)->whereKey($zone)->exists();
        $data = ['branch_id' => $branch->id, 'old_zone_id' => $branch->zone_id, 'new_zone_id' => $zone, 'areas' => $areas,
            'services_for_review' => $permissions, 'prices_missing' => $missing, 'branch_location_fits' => $fits,
            'future_bookings' => Booking::withoutGlobalScopes()->where('provider_id', $branch->id)->whereNotIn('booking_status', ['completed', 'canceled'])
                ->where(fn ($q) => $q->where('service_schedule', '>', now())->orWhereHas('repeat', fn ($r) => $r->where('service_schedule', '>', now())->whereNotIn('booking_status', ['completed', 'canceled'])))
                ->orderBy('id')->get(['id', 'readable_id', 'zone_id'])->toArray()];
        $data['confirmation_hash'] = hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key'));
        return $data;
    }

    public function confirm(Provider $branch, string $zone, string $hash, ?string $reason): void
    {
        DB::transaction(function () use ($branch, $zone, $hash, $reason): void {
            $branch = Provider::withoutGlobalScopes()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $impact = $this->preview($branch, $zone);
            abort_unless(hash_equals($impact['confirmation_hash'], $hash), 409, 'zone_change_impact_changed');
            abort_if($branch->zone_id === $zone, 422, 'zone_unchanged');
            $old = $branch->only(['zone_id', 'intake_enabled']);
            $branch->zoneChangeConfirmed = true;
            $branch->zone_id = $zone; $branch->intake_enabled = false; $branch->zone_review_required = true; $branch->setup_step = 'coverage-services';
            $branch->save();
            $branch->owner?->zones()->sync([$zone]);
            // Retain old geometry and authorization history for inspection; never relabel polygons.
            BranchServiceArea::where('provider_id', $branch->id)->update(['is_active' => false]);
            BranchServiceAvailability::where('provider_id', $branch->id)->update(['is_active' => false]);
            app(AuditService::class)->record('branch.zone.changed', $branch, $old, $impact + ['reason' => $reason], request()->user(), $branch->id);
        });
    }
}
