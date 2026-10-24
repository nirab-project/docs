<?php

namespace Modules\BookingModule\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Modules\BookingModule\Entities\{Booking, BookingRepeat};
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Services\{BookingServiceCatalog, DirectServicePolicy, ServiceItemRequirements};

/** Runs inside the existing AtomicAccountingSave transaction for every mutation path. */
class DirectBookingReservationGuard
{
    public function validateMutation(Model $booking): void
    {
        if (!$booking->isDirty(['provider_id', 'service_schedule', 'service_address_id', 'service_address_location', 'service_location', 'serviceman_id', 'estimated_duration_minutes', 'required_technicians', 'execution_type'])) return;
        if (in_array($booking->booking_status, ['completed', 'canceled'], true)) return;
        $parent = $booking instanceof BookingRepeat ? $booking->booking : $booking;
        if (!$booking->provider_id) return; // Unassigned requests are validated when assigned.
        Provider::withoutGlobalScopes()->whereKey($booking->provider_id)->lockForUpdate()->firstOrFail();
        if ($booking instanceof Booking && $booking->is_repeated && $booking->repeat()->exists()) {
            foreach ($booking->repeat()->whereNotIn('booking_status', ['completed','canceled'])->get() as $visit) {
                $visit->provider_id = $booking->provider_id;
                $this->validateVisit($visit, $booking);
            }
            return;
        }
        $this->validateVisit($booking, $parent);
    }

    private function validateVisit(Model $booking, Booking $parent): void
    {
        $lines = $booking->detail()->get();
        abort_if($lines->isEmpty(), 409, 'booking_requires_service');
        $context = app(BookingServiceCatalog::class)->context($booking);
        $serviceIds = $lines->pluck('service_id')->unique()->values()->all();
        abort_unless($context['area_id'] && app(DirectServicePolicy::class)->allAvailable($booking->provider_id, $serviceIds, $context['area_id'], $booking->isDirty('provider_id')), 409, 'service_not_available_for_branch');
        // Accepted quotation amounts and prepaid rights are immutable; retain their agreed requirements.
        if (!$parent->quotation_id && $parent->payment_method !== 'prepaid_visit') {
            $requirements = app(ServiceItemRequirements::class)->evaluate($lines->map(fn ($line) => [
                'service_id'=>$line->service_id, 'variant_key'=>$line->variant_key, 'quantity'=>$line->quantity,
                'field_answers'=>$line->field_answers ?? [],
            ])->all(), $parent->zone_id);
            $booking->estimated_duration_minutes = $requirements['duration_minutes'];
            $booking->required_technicians = $requirements['required_technicians'];
            $booking->execution_type = $requirements['execution_type'];
        }
        $duration = (int)($booking->estimated_duration_minutes ?? $parent->estimated_duration_minutes);
        $crew = (int)($booking->required_technicians ?? $parent->required_technicians);
        $start = Carbon::parse($booking->service_schedule);
        if ($booking->isDirty(['provider_id', 'service_schedule'])) {
            $branch = Provider::withoutGlobalScopes()->findOrFail($booking->provider_id);
            abort_unless(app(\Modules\ProviderManagement\Services\BranchCapacityPolicy::class)->available($branch, $start, 1,
                $booking instanceof Booking ? $booking->id : null, $booking instanceof BookingRepeat ? $booking->id : null), 409, 'capacity_exceeded');
        }
        if ($start->isPast()) {
            abort_if($booking->isDirty('service_schedule'), 422, 'schedule_must_be_future');
            $start = now()->addSeconds(5);
        }
        $availability = app(AvailabilityService::class);
        abort_unless($availability->canFulfill($booking->provider_id, $serviceIds, $start, $duration, $crew,
            $booking->execution_type ?? $parent->execution_type, $parent->id), 409, 'booking_capacity_conflict');
        $assignedIds = $parent->assignments()->whereIn('status', ['offered', 'accepted'])->pluck('serviceman_id');
        if ($booking->serviceman_id) $assignedIds->push($booking->serviceman_id);
        $assignedIds = $assignedIds->unique();
        abort_if($assignedIds->isNotEmpty() && $assignedIds->count() < $crew, 409, 'assigned_team_insufficient_members');
        foreach ($assignedIds as $technicianId) {
            $technician = \Modules\UserManagement\Entities\Serviceman::withoutGlobalScopes()->with('user')->find($technicianId);
            abort_unless($technician && $technician->provider_id === $booking->provider_id && $availability->explain($technician, $serviceIds, $start, $duration, $parent->id)['available'], 409, 'assigned_technician_no_longer_eligible');
        }
        if ($booking instanceof BookingRepeat) {
            $overlap = $parent->repeat()->whereKeyNot($booking->id)->whereNotIn('booking_status', ['completed','canceled'])
                ->where('service_schedule','<',$start->copy()->addMinutes($duration))
                ->whereRaw('DATE_ADD(service_schedule, INTERVAL COALESCE(estimated_duration_minutes, ?) MINUTE) > ?', [(int)$parent->estimated_duration_minutes,$start])->exists();
            abort_if($overlap, 409, 'repeat_visits_overlap');
        }
    }
}
