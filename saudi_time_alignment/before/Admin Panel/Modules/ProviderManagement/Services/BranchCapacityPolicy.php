<?php

namespace Modules\ProviderManagement\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\Booking;
use Modules\ProviderManagement\Entities\Provider;

final class BranchCapacityPolicy
{
    public function limit(Provider $branch): int
    {
        $value = (int) $branch->service_capacity_per_day;
        return $value > 0 ? $value : (app(BranchIntakePolicy::class)->legacy($branch) ? 1 : 0);
    }

    /** Active visits per operational calendar day; a repeat parent is never counted. */
    public function dailyLoad(string $branchId, CarbonInterface $at, ?string $ignoreBookingId = null, ?string $ignoreRepeatId = null): int
    {
        $day = $at->copy()->setTimezone(config('app.timezone'))->toDateString();
        $regular = Booking::withoutGlobalScopes()->where('provider_id', $branchId)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where(fn ($q) => $q->whereNull('is_repeated')->orWhere('is_repeated', 0))
            ->whereDate('service_schedule', $day)->whereNotIn('booking_status', ['canceled', 'completed'])->count();
        $repeats = DB::table('booking_repeats')->join('bookings', 'bookings.id', '=', 'booking_repeats.booking_id')
            ->where('bookings.provider_id', $branchId)
            ->when($ignoreRepeatId, fn ($q) => $q->where('booking_repeats.id', '!=', $ignoreRepeatId))
            ->whereDate('booking_repeats.service_schedule', $day)
            ->whereNotIn('booking_repeats.booking_status', ['canceled', 'completed'])->count();
        return $regular + $repeats;
    }

    public function available(Provider $branch, CarbonInterface $at, int $additional = 1, ?string $ignoreBookingId = null, ?string $ignoreRepeatId = null): bool
    {
        return $this->dailyLoad($branch->id, $at, $ignoreBookingId, $ignoreRepeatId) + $additional <= $this->limit($branch);
    }

    /** Call inside the booking transaction, before checking any visit. */
    public function lock(array $ids): void
    {
        Provider::withoutGlobalScopes()->whereIn('id', array_unique($ids))->orderBy('id')->lockForUpdate()->get();
    }
}
