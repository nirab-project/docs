<?php

namespace Modules\BookingModule\Services;

use Carbon\CarbonInterface;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingAssignment;
use Modules\ServicemanModule\Entities\ServicemanLeave;
use Modules\ServicemanModule\Entities\ServicemanShift;
use Modules\ServicemanModule\Entities\ServicemanSkill;
use Modules\UserManagement\Entities\Serviceman;
use Modules\ServicemanModule\Entities\Team;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public function canFulfill(string $providerId, array $serviceIds, CarbonInterface $startsAt, int $durationMinutes, int $requiredTechnicians, string $executionType = 'single', ?string $ignoreBookingId = null): bool
    {
        if ($startsAt->isPast() || $durationMinutes < 1 || $requiredTechnicians < 1 || empty($serviceIds)) {
            return false;
        }
        $eligible = Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->with('user')->where('provider_id', $providerId)->get()
            ->filter(fn (Serviceman $serviceman) => $this->explain($serviceman, $serviceIds, $startsAt, $durationMinutes, $ignoreBookingId)['available']);
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes + max(0, (int) config('nirab_dispatch.travel_buffer_minutes')));
        $reservationStart = $startsAt->copy()->subMinutes(max(0, (int) config('nirab_dispatch.travel_buffer_minutes')));
        $reservedTechnicians = (int) Booking::withoutGlobalScopes()
            ->where('provider_id', $providerId)
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->where('assignment_status', 'pending')
            ->where(fn ($query) => $query->whereNull('is_repeated')->orWhere('is_repeated', 0))
            ->whereNotIn('booking_status', ['canceled', 'completed'])
            ->where('service_schedule', '<', $endsAt)
            ->whereRaw('DATE_ADD(service_schedule, INTERVAL estimated_duration_minutes MINUTE) > ?', [$reservationStart])
            ->sum('required_technicians');
        $reservedTechnicians += (int) DB::table('booking_repeats')
            ->join('bookings', 'bookings.id', '=', 'booking_repeats.booking_id')
            ->where('bookings.provider_id', $providerId)->when($ignoreBookingId, fn ($q) => $q->where('bookings.id', '!=', $ignoreBookingId))
            ->where('bookings.assignment_status', 'pending')
            ->whereNotIn('booking_repeats.booking_status', ['canceled', 'completed'])
            ->where('booking_repeats.service_schedule', '<', $endsAt)
            ->whereRaw('DATE_ADD(booking_repeats.service_schedule, INTERVAL COALESCE(booking_repeats.estimated_duration_minutes, bookings.estimated_duration_minutes) MINUTE) > ?', [$reservationStart])
            ->sum(DB::raw('COALESCE(booking_repeats.required_technicians, bookings.required_technicians)'));

        if (($eligible->count() - $reservedTechnicians) < $requiredTechnicians) {
            return false;
        }

        if ($executionType === 'team') {
            $reservedTeams = Booking::withoutGlobalScopes()->where('provider_id', $providerId)->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))->where('assignment_status', 'pending')->where('execution_type', 'team')
                ->where(fn ($query) => $query->whereNull('is_repeated')->orWhere('is_repeated', 0))->whereNotIn('booking_status', ['canceled', 'completed'])
                ->where('service_schedule', '<', $endsAt)->whereRaw('DATE_ADD(service_schedule, INTERVAL estimated_duration_minutes MINUTE) > ?', [$reservationStart])->count();
            $reservedTeams += DB::table('booking_repeats')->join('bookings', 'bookings.id', '=', 'booking_repeats.booking_id')
                ->where('bookings.provider_id', $providerId)->when($ignoreBookingId, fn ($q) => $q->where('bookings.id', '!=', $ignoreBookingId))->where('bookings.assignment_status', 'pending')->whereRaw("COALESCE(booking_repeats.execution_type, bookings.execution_type) = 'team'")
                ->whereNotIn('booking_repeats.booking_status', ['canceled', 'completed'])->where('booking_repeats.service_schedule', '<', $endsAt)
                ->whereRaw('DATE_ADD(booking_repeats.service_schedule, INTERVAL COALESCE(booking_repeats.estimated_duration_minutes, bookings.estimated_duration_minutes) MINUTE) > ?', [$reservationStart])->count();
            return Team::query()->with(['members.user'])->where('provider_id', $providerId)->where('is_active', true)->get()
                ->filter(function (Team $team) use ($requiredTechnicians, $eligible, $reservedTechnicians): bool {
                    return $team->leader_id && $team->members->contains('id', $team->leader_id) && $team->members->count() >= $requiredTechnicians && $team->members->count() <= $eligible->count() - $reservedTechnicians
                        && $team->members->every(fn (Serviceman $member) => $eligible->contains('id', $member->id));
                })->count() > $reservedTeams;
        }

        return true;
    }

    public function explain(Serviceman $serviceman, array $serviceIds, CarbonInterface $startsAt, int $durationMinutes, ?string $ignoreBookingId = null): array
    {
        $reasons = [];
        if (!$serviceman->user?->is_active) {
            $reasons[] = 'inactive';
        }

        $skillCount = ServicemanSkill::query()
            ->where('serviceman_id', $serviceman->id)
            ->whereIn('service_id', $serviceIds)
            ->distinct('service_id')
            ->count('service_id');
        if ($skillCount < count(array_unique($serviceIds))) {
            $reasons[] = 'missing_skill';
        }

        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $shift = ServicemanShift::query()
            ->where('serviceman_id', $serviceman->id)
            ->where('day_of_week', $startsAt->dayOfWeek)
            ->where('is_active', true)
            ->whereTime('starts_at', '<=', $startsAt->format('H:i:s'))
            ->whereTime('ends_at', '>=', $endsAt->format('H:i:s'))
            ->exists();
        if (!$shift || !$startsAt->isSameDay($endsAt)) {
            $started = ServicemanShift::where('serviceman_id', $serviceman->id)->where('day_of_week', $startsAt->dayOfWeek)
                ->where('is_active', true)->whereTime('starts_at', '<=', $startsAt->format('H:i:s'))->whereTime('ends_at', '>', $startsAt->format('H:i:s'))->exists();
            $reasons[] = $started ? 'duration_outside_shift' : 'off_shift';
        }

        $onLeave = ServicemanLeave::query()
            ->where('serviceman_id', $serviceman->id)
            ->where('status', 'approved')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
        if ($onLeave) {
            $reasons[] = 'on_leave';
        }

        if ($this->hasConflict($serviceman->id, $startsAt, $endsAt, $ignoreBookingId)) {
            $reasons[] = 'schedule_conflict';
        }

        return ['available' => $reasons === [], 'reasons' => $reasons];
    }

    public function hasConflict(string $servicemanId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?string $ignoreBookingId = null): bool
    {
        $buffer = config('nirab_dispatch.travel_buffer_minutes');
        $windowStart = $startsAt->copy()->subMinutes($buffer);
        $windowEnd = $endsAt->copy()->addMinutes($buffer);

        $regularConflict = BookingAssignment::query()
            ->where('serviceman_id', $servicemanId)
            ->whereIn('status', ['offered', 'accepted'])
            ->when($ignoreBookingId, fn ($query) => $query->where('booking_id', '!=', $ignoreBookingId))
            ->whereHas('booking', function ($query) use ($windowStart, $windowEnd): void {
                $query->whereNotIn('booking_status', ['canceled', 'completed'])
                    ->where('service_schedule', '<', $windowEnd)
                    ->whereRaw('DATE_ADD(service_schedule, INTERVAL estimated_duration_minutes MINUTE) > ?', [$windowStart]);
            })
            ->exists();

        if ($regularConflict) return true;

        return DB::table('booking_assignments')
            ->join('booking_repeats', 'booking_repeats.booking_id', '=', 'booking_assignments.booking_id')
            ->join('bookings', 'bookings.id', '=', 'booking_assignments.booking_id')
            ->where('booking_assignments.serviceman_id', $servicemanId)
            ->whereIn('booking_assignments.status', ['offered', 'accepted'])
            ->when($ignoreBookingId, fn ($query) => $query->where('bookings.id', '!=', $ignoreBookingId))
            ->whereNotIn('booking_repeats.booking_status', ['canceled', 'completed'])
            ->where('booking_repeats.service_schedule', '<', $windowEnd)
            ->whereRaw('DATE_ADD(booking_repeats.service_schedule, INTERVAL COALESCE(booking_repeats.estimated_duration_minutes, bookings.estimated_duration_minutes) MINUTE) > ?', [$windowStart])
            ->exists();
    }

    public function slots(string $providerId, array $serviceIds, CarbonInterface $day, int $durationMinutes, int $requiredTechnicians, string $executionType = 'single'): array
    {
        $servicemen = Serviceman::withoutGlobalScopes()->with('user')->where('provider_id', $providerId)->get();
        $slots = [];
        $cursor = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        while ($cursor->copy()->addMinutes($durationMinutes)->lte($end)) {
            $available = $servicemen->filter(fn (Serviceman $serviceman) =>
                $this->explain($serviceman, $serviceIds, $cursor, $durationMinutes)['available']
            );
            if ($this->canFulfill($providerId, $serviceIds, $cursor, $durationMinutes, $requiredTechnicians, $executionType)) {
                $slots[] = ['starts_at' => $cursor->toIso8601String(), 'available_technicians' => $available->count()];
            }
            $cursor->addMinutes(30);
        }

        return $slots;
    }

    /** Administrative explanation uses the same explain/canFulfill decisions as checkout. */
    public function diagnose(string $providerId, array $ids, CarbonInterface $start, int $duration, int $crew, string $type): array
    {
        if ($start->isPast()) return ['selected_time_past'];
        $staff = Serviceman::withoutGlobalScopes()->whereNull('deleted_at')->with('user')->where('provider_id', $providerId)->orderBy('id')->limit(201)->get();
        abort_if($staff->count() > 200, 422, 'diagnostic_candidate_limit');
        if (!$staff->contains(fn ($member) => $member->user?->is_active)) return ['no_active_technician'];
        $reasons = $staff->flatMap(fn ($member) => $this->explain($member, $ids, $start, $duration)['reasons'])->unique()->values()->all();
        if ($type === 'team' && !Team::where('provider_id', $providerId)->where('is_active', true)->exists()) return array_values(array_unique(array_merge($reasons, ['team_missing'])));
        if ($this->canFulfill($providerId, $ids, $start, $duration, $crew, $type)) return [];
        return array_values(array_unique(array_merge($reasons, [$type === 'team' ? 'team_capacity_insufficient' : 'schedule_conflict'])));
    }

    /** Bounded readiness probe: shift/opening boundaries during the next week, no reservations. */
    public function configuredWindow(\Modules\ProviderManagement\Entities\Provider $branch, string $service, int $duration, int $crew, string $type): array
    {
        $schedule = app(\Modules\ProviderManagement\Services\BranchScheduleService::class);
        $settings = $schedule->read($branch->id)['time_schedule'];
        $reasons = [];
        $shifts = ServicemanShift::where('is_active', true)->whereHas('serviceman', fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at')->where('provider_id', $branch->id))
            ->orderBy('starts_at')->get();
        for ($offset = 1; $offset <= 7; $offset++) {
            $day = now()->setTimezone(config('app.timezone'))->startOfDay()->addDays($offset);
            $hours = $settings['weekly'][$day->dayOfWeek] ?? $settings;
            $times = $shifts->where('day_of_week', $day->dayOfWeek)->pluck('starts_at')->map(fn ($time) => max(substr($time, 0, 5), $hours['start_time'] ?? '00:00'))
                ->merge(array_column($hours['breaks'] ?? [], 'end_time'))->unique()->sort()->take(12);
            foreach ($times as $time) {
                $start = $day->copy()->setTimeFromTimeString($time);
                if (!$schedule->contains($branch, $start, $duration)) { $reasons[] = 'branch_closed'; continue; }
                if ($this->canFulfill($branch->id, [$service], $start, $duration, $crew, $type)) return ['available' => true, 'reasons' => []];
                $reasons = array_merge($reasons, $this->diagnose($branch->id, [$service], $start, $duration, $crew, $type));
            }
        }
        return ['available' => false, 'reasons' => array_values(array_unique($reasons ?: ['off_shift']))];
    }
}
