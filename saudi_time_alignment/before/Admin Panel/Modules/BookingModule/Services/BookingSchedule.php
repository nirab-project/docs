<?php

namespace Modules\BookingModule\Services;

use Illuminate\Support\Carbon;

/** The slot API and existing database schedules use app.timezone wall time. */
final class BookingSchedule
{
    public static function parse(string $value): Carbon
    {
        $timezone = config('app.timezone');
        // Explicit offsets identify an instant. Legacy offset-free inputs keep
        // their existing operational wall time, independent of PHP's timezone.
        return Carbon::parse($value, $timezone)->setTimezone($timezone);
    }

    public static function forStorage(string $value): string
    {
        return self::parse($value)->format('Y-m-d H:i:s');
    }
}
