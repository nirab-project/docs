<?php

namespace Modules\BookingModule\Services;

use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingAssignment;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\ServicemanModule\Entities\ServicemanLastLocation;

class TrackingService
{
    public function __construct(private readonly EtaProvider $eta)
    {
    }

    public function updateLocation(string $servicemanId, string $bookingId, float $latitude, float $longitude, ?float $accuracy, Carbon $capturedAt): ServicemanLastLocation
    {
        return DB::transaction(function () use ($servicemanId, $bookingId, $latitude, $longitude, $accuracy, $capturedAt): ServicemanLastLocation {
            $booking = Booking::withoutGlobalScopes()->with('service_address')->whereKey($bookingId)->lockForUpdate()->firstOrFail();
            $assigned = BookingAssignment::query()->where('booking_id', $bookingId)->where('serviceman_id', $servicemanId)->where('status', 'accepted')->exists();
            $active = (int) $booking->is_repeated === 1
                ? $booking->repeat()->whereIn('booking_status', ['on_the_way', 'arrived', 'ongoing'])->exists()
                : in_array($booking->booking_status, ['on_the_way', 'arrived', 'ongoing'], true);
            if (!$assigned || !$active || $capturedAt->lt(now()->subMinutes(config('nirab_dispatch.location_max_age_minutes'))) || $capturedAt->isFuture()) {
                throw ValidationException::withMessages(['booking_id' => [translate('location_update_requires_active_assignment')]]);
            }

            $previous = ServicemanLastLocation::query()->where('serviceman_id', $servicemanId)->lockForUpdate()->first();
            if ($previous?->captured_at && $capturedAt->lte($previous->captured_at)) return $previous;

            $location = ServicemanLastLocation::query()->updateOrCreate(
                ['serviceman_id' => $servicemanId],
                ['booking_id' => $bookingId, 'latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => $accuracy, 'captured_at' => $capturedAt],
            );

            if ($booking->service_address?->lat !== null && $booking->service_address?->lon !== null) {
                $minutes = $this->eta->estimateMinutes($latitude, $longitude, (float) $booking->service_address->lat, (float) $booking->service_address->lon);
                $booking->forceFill(['estimated_arrival_at' => now()->addMinutes($minutes)])->save();
            }

            return $location;
        }, 3);
    }

    public function customerTracking(Booking $booking, ?BookingRepeat $repeat = null): array
    {
        $status = $repeat?->booking_status ?? $booking->booking_status;
        $active = $status === 'on_the_way';
        $primary = $active ? $booking->assignments()->whereIn('role', ['leader', 'primary'])->where('status', 'accepted')->latest()->first() : null;
        $location = $primary ? ServicemanLastLocation::query()->where('serviceman_id', $primary->serviceman_id)
            ->where('booking_id', $booking->id)
            ->first() : null;
        $stale = $location && (!$location->captured_at || $location->captured_at->lt(now()->subMinutes(config('nirab_dispatch.location_max_age_minutes'))));
        $address = $booking->service_address_location ? json_decode($booking->service_address_location, true) : $booking->service_address?->toArray();
        $latitude = $address['lat'] ?? $address['latitude'] ?? null;
        $longitude = $address['lon'] ?? $address['longitude'] ?? null;
        $etaMinutes = $location && !$stale && is_numeric($latitude) && is_numeric($longitude)
            ? $this->eta->estimateMinutes($location->latitude, $location->longitude, (float) $latitude, (float) $longitude) : null;

        return [
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'booking_status' => $status,
            'tracking_active' => $active,
            'stale' => (bool) $stale,
            'max_age_seconds' => max(1, (int) config('nirab_dispatch.location_max_age_minutes')) * 60,
            'customer_location' => $active && is_numeric($latitude) && is_numeric($longitude) ? ['latitude' => (float) $latitude, 'longitude' => (float) $longitude] : null,
            'execution_type' => $booking->execution_type,
            'location' => $location ? [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'accuracy' => $location->accuracy,
                'captured_at' => $location->captured_at?->toIso8601String(),
            ] : null,
            'eta_minutes' => $etaMinutes,
            'estimated_arrival_at' => $etaMinutes !== null ? now()->addMinutes($etaMinutes)->toIso8601String() : null,
        ];
    }
}
