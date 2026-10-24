<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/** Local calendar dates, half-open UTC boundaries for timestamp columns. */
class BranchPeriod
{
    public static function dates(string $period, ?string $from = null, ?string $to = null): array
    {
        $zone = config('nirab_execution.settlement_timezone', 'Asia/Riyadh');
        $today = CarbonImmutable::now($zone);
        [$start, $end] = match ($period) {
            'today' => [$today, $today],
            'last_month' => [$today->startOfMonth()->subMonth(), $today->startOfMonth()->subDay()],
            'custom' => [CarbonImmutable::parse($from, $zone), CarbonImmutable::parse($to, $zone)],
            default => [$today->startOfMonth(), $today->endOfMonth()],
        };
        return ['from' => $start->toDateString(), 'to' => $end->toDateString(),
            'start' => $start->startOfDay()->utc()->toDateTimeString(),
            'end' => $end->startOfDay()->addDay()->utc()->toDateTimeString(), 'timezone' => $zone];
    }

    public static function filters(Request $request): array
    {
        $data = $request->validate([
            'branch_id' => 'nullable|uuid',
            'period' => 'nullable|in:today,this_month,last_month,custom',
            'from' => 'nullable|required_with:to|date_format:Y-m-d',
            'to' => 'nullable|required_with:from|date_format:Y-m-d|after_or_equal:from',
            'status' => 'nullable|in:' . implode(',', BranchReportService::STATUSES),
            'queue' => 'nullable|in:pending,unassigned,in_progress,overdue,completed_unpaid',
            'payment' => 'nullable|in:paid,unpaid', 'technician_id' => 'nullable|uuid',
            'search' => 'nullable|string|max:100', 'service_id' => 'nullable|uuid',
        ]);
        $period = ($data['from'] ?? null) ? 'custom' : ($data['period'] ?? 'this_month');
        abort_if($period === 'custom' && empty($data['from']), 422);
        $dates = self::dates($period, $data['from'] ?? null, $data['to'] ?? null);
        abort_if(CarbonImmutable::parse($dates['start'])->diffInDays($dates['end']) > 367, 422);
        return array_merge($data, $dates, ['period' => $period, 'branch_id' => $request->input('branch_id')]);
    }

    public static function apply($query, string $column, array $filters)
    {
        return $query->where($column, '>=', $filters['start'])->where($column, '<', $filters['end']);
    }
}
