<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\Booking;
use Modules\UserManagement\Entities\User;
class OperationalAnalytics
{
    public function __construct(private readonly OperationalAccess $access, private readonly GeoService $geo)
    {
    }
    public function bookings(User $actor, array $filters)
    {
        return $this->access->scope(Booking::withoutGlobalScopes(), $actor, 'analytics.read')->when($filters['branch_id'] ?? null, fn($q, $v) => $q->where('provider_id', $v));
    }
    private function subjects(User $actor, array $filters)
    {
        return app(BranchReportService::class)->subjects($actor, $filters);
    }
    private function monetarySummary(): string
    {
        return "COUNT(*) AS orders, SUM(total_booking_amount) AS booking_value, SUM(CASE WHEN booking_status = 'completed' AND is_paid = 1 AND is_warranty = 0 AND payment_method <> 'prepaid_visit' THEN total_booking_amount ELSE 0 END) AS revenue";
    }
    public function report(User $actor, array $filters): array
    {
        $base = $this->subjects($actor, $filters);
        $summary = (array) (clone $base)->selectRaw($this->monetarySummary() . ", SUM(CASE WHEN booking_status = 'completed' THEN 1 ELSE 0 END) AS completed, SUM(CASE WHEN booking_status = 'canceled' THEN 1 ELSE 0 END) AS canceled, AVG(CASE WHEN booking_status = 'completed' THEN actual_duration_seconds ELSE NULL END) AS average_actual_duration_seconds, SUM(CASE WHEN booking_repeat_id IS NOT NULL AND booking_status = 'completed' AND is_paid = 1 AND is_warranty = 0 AND payment_method <> 'prepaid_visit' THEN total_booking_amount ELSE 0 END) AS repeat_visit_revenue")->first();
        $summary['completion_rate'] = $summary['orders'] ? round((float) $summary['completed'] / (float) $summary['orders'], 4) : 0;
        $summary['cancellation_rate'] = $summary['orders'] ? round((float) $summary['canceled'] / (float) $summary['orders'], 4) : 0;
        $branches = (clone $base)->select('provider_id')->selectRaw($this->monetarySummary())->groupBy('provider_id')->orderBy('provider_id')->paginate(25, ['*'], 'branch_page', $filters['branch_page'] ?? null);
        $areas = (clone $base)->select('zone_id')->selectRaw($this->monetarySummary())->groupBy('zone_id')->orderByDesc('orders')->limit(100)->get();
        $payments = (clone $base)->select('payment_method')->selectRaw('COUNT(*) AS orders, SUM(total_booking_amount) AS value')->groupBy('payment_method')->get();
        $details = DB::table('booking_details')->select(['service_id', 'quantity'])->selectRaw('booking_id AS subject_id')->unionAll(DB::table('booking_repeat_details')->select(['service_id', 'quantity'])->selectRaw('booking_repeat_id AS subject_id'));
        $lines = DB::query()->fromSub(clone $base, 'jobs')->joinSub($details, 'lines', 'lines.subject_id', '=', 'jobs.subject_id');
        $services = $lines->select('lines.service_id')->selectRaw('COUNT(DISTINCT jobs.subject_id) AS orders, SUM(lines.quantity) AS units')->groupBy('lines.service_id')->orderByDesc('orders')->limit(50)->get();
        $neighborhoods = (clone $base)->join('user_addresses', 'user_addresses.id', '=', 'jobs.service_address_id')->select('user_addresses.city', 'user_addresses.zip_code')->selectRaw('COUNT(*) AS orders, SUM(jobs.total_booking_amount) AS value')->groupBy('user_addresses.city', 'user_addresses.zip_code')->orderByDesc('orders')->limit(100)->get();
        $collections = DB::query()->fromSub(clone $base, 'jobs')->join('payment_collections AS payments', function ($join): void {
            $join->on('payments.booking_id', '=', 'jobs.booking_id')->where(function ($q): void {
                $q->whereColumn('payments.booking_repeat_id', 'jobs.booking_repeat_id')->orWhere(fn($q) => $q->whereNull('payments.booking_repeat_id')->whereNull('jobs.booking_repeat_id'));
            });
        })->select('payments.status')->selectRaw('COUNT(*) AS count, SUM(payments.amount) AS amount')->groupBy('payments.status')->get();
        $custody = $this->access->scope(\Modules\BookingModule\Entities\TechnicianCustodyEntry::query(), $actor, 'analytics.read')->when($filters['branch_id'] ?? null, fn($q, $v) => $q->where('provider_id', $v))->sum('amount');
        $assignment = DB::table('booking_assignment_histories')->select('booking_id')->selectRaw('MIN(created_at) AS first_assigned_at')->where('event', 'offered')->groupBy('booking_id');
        $travel = DB::table('booking_status_histories')->selectRaw('COALESCE(booking_repeat_id, booking_id) AS subject_id, MIN(created_at) AS departed_at')->where('booking_status', 'on_the_way')->groupByRaw('COALESCE(booking_repeat_id, booking_id)');
        $timing = (clone $base)->leftJoinSub($assignment, 'first_assignment', 'first_assignment.booking_id', '=', 'jobs.booking_id')->leftJoinSub($travel, 'travel', 'travel.subject_id', '=', 'jobs.subject_id')->selectRaw('AVG(' . $this->geo->secondsBetween('jobs.created_at', 'first_assignment.first_assigned_at') . ') AS average_assignment_seconds, AVG(' . $this->geo->secondsBetween('travel.departed_at', 'jobs.arrived_at') . ') AS average_arrival_seconds, AVG(' . $this->geo->secondsBetween('jobs.service_schedule', 'jobs.arrived_at') . ') AS average_arrival_schedule_difference_seconds')->first();
        $ratings = DB::table('reviews')->where('target_type', 'technician')->where('is_active', 1)->select('serviceman_id')->selectRaw('AVG(review_rating) AS rating')->groupBy('serviceman_id');
        $technicians = (clone $base)->join('booking_assignments AS assignments', 'assignments.booking_id', '=', 'jobs.booking_id')->where('assignments.status', 'accepted')->leftJoinSub($ratings, 'ratings', 'ratings.serviceman_id', '=', 'assignments.serviceman_id')->select('assignments.serviceman_id', 'assignments.team_id')->selectRaw("COUNT(DISTINCT jobs.subject_id) AS jobs, SUM(CASE WHEN jobs.booking_status = 'completed' THEN 1 ELSE 0 END) AS completed, AVG(jobs.actual_duration_seconds) AS average_service_seconds, MAX(ratings.rating) AS rating")->groupBy('assignments.serviceman_id', 'assignments.team_id')->orderBy('assignments.serviceman_id')->paginate(25, ['*'], 'technician_page', $filters['technician_page'] ?? null);
        $quotes = $this->access->scope(\Modules\BidModule\Entities\PostBid::query(), $actor, 'analytics.read')->whereBetween('created_at', [$filters['from'], $filters['to']])->when($filters['branch_id'] ?? null, fn($q, $v) => $q->where('provider_id', $v))->selectRaw("COUNT(*) AS total, SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS converted")->toBase()->first();
        $summary['repeat_customers'] = DB::query()->fromSub((clone $base)->where('booking_status', 'completed')->whereNotNull('customer_id')->select('customer_id')->groupBy('customer_id')->havingRaw('COUNT(*) > 1'), 'repeat_customers')->count();
        $summary['cash_custody_exposure'] = (float) $custody;
        $summary['quote_conversion'] = $quotes->total ? round((float) $quotes->converted / (float) $quotes->total, 4) : 0;
        $summary = array_merge($summary, (array) $timing);
        $prepaid = (clone $base)->join('customer_visit_usage AS usage', 'usage.booking_id', '=', 'jobs.booking_id')->join('customer_visit_packages AS packages', 'packages.id', '=', 'usage.package_id')->whereNull('jobs.booking_repeat_id')->where('usage.status', 'consumed')->select('jobs.provider_id')->selectRaw('SUM(packages.price / packages.total_visits) AS prepaid_visit_value')->groupBy('jobs.provider_id')->pluck('prepaid_visit_value', 'jobs.provider_id');
        foreach ($branches as $row) {
            $row->prepaid_visit_value = (float) ($prepaid[$row->provider_id] ?? 0);
        }
        $branchNames = DB::table('providers')->whereIn('id', $branches->pluck('provider_id')->filter())->pluck('company_name', 'id');
        $serviceNames = DB::table('services')->whereIn('id', $services->pluck('service_id')->filter())->pluck('name', 'id');
        $areaNames = DB::table('zones')->whereIn('id', $areas->pluck('zone_id')->filter())->pluck('name', 'id');
        $staff = DB::table('servicemen')->join('users', 'users.id', '=', 'servicemen.user_id')->whereIn('servicemen.id', $technicians->pluck('serviceman_id'))->get(['servicemen.id', 'users.first_name', 'users.last_name'])->keyBy('id');
        $teams = DB::table('teams')->whereIn('id', $technicians->pluck('team_id')->filter())->pluck('name', 'id');
        foreach ($branches as $row) {
            $row->branch_name = $branchNames[$row->provider_id] ?? $row->provider_id;
        }
        foreach ($services as $row) {
            $row->service_name = $serviceNames[$row->service_id] ?? $row->service_id;
        }
        foreach ($areas as $row) {
            $row->zone_name = $areaNames[$row->zone_id] ?? $row->zone_id;
        }
        foreach ($technicians as $row) {
            $person = $staff[$row->serviceman_id] ?? null;
            $row->technician_name = $person ? trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? '')) : $row->serviceman_id;
            $row->team_name = $teams[$row->team_id] ?? $row->team_id;
        }
        $summary['prepaid_visit_value'] = (float) $prepaid->sum();
        $hq = $actor->user_type === 'super-admin' || $actor->user_type === 'admin-employee' && \App\Models\OperationalScope::where('user_id', $actor->id)->where('all_branches', true)->exists();
        if ($hq && empty($filters['branch_id'])) {
            $sales = DB::table('customer_visit_packages')->whereBetween('created_at', [$filters['from'], $filters['to']])->selectRaw('COUNT(*) AS package_sales, SUM(price) AS package_revenue')->first();
            $summary = array_merge($summary, (array) $sales);
        }
        return compact('summary', 'branches', 'services', 'areas', 'neighborhoods', 'payments', 'collections', 'technicians');
    }
    public function heatmap(User $actor, array $filters): array
    {
        $cell = max(0.005, min(1.0, 180 / 2 ** ($filters['zoom'] ?? 10)));
        $latitude = 'FLOOR(CAST(user_addresses.lat AS DECIMAL(10,6)) / ?)';
        $longitude = 'FLOOR(CAST(user_addresses.lon AS DECIMAL(10,6)) / ?)';
        $query = $this->subjects($actor, $filters)
            ->join('user_addresses', 'user_addresses.id', '=', 'jobs.service_address_id')
            ->whereNotNull('user_addresses.lat')->whereNotNull('user_addresses.lon')
            ->where('user_addresses.lat', '!=', '')->where('user_addresses.lon', '!=', '')
            ->whereBetween(DB::raw('CAST(user_addresses.lat AS DECIMAL(10,6))'), [$filters['south'] ?? -90, $filters['north'] ?? 90])
            ->whereBetween(DB::raw('CAST(user_addresses.lon AS DECIMAL(10,6))'), [$filters['west'] ?? -180, $filters['east'] ?? 180])
            ->selectRaw($latitude . ' AS lat_bucket, ' . $longitude . ' AS lng_bucket, COUNT(*) AS count', [$cell, $cell])
            // Reuse the selected expressions; separate bound parameters in GROUP BY fail MySQL's strict grouping check.
            ->groupBy('lat_bucket', 'lng_bucket')
            ->orderByDesc('count')->limit(501)->get();
        return ['cell_degrees' => $cell, 'buckets' => $query->take(500)->map(fn($row) => ['center_latitude' => round(($row->lat_bucket + 0.5) * $cell, 5), 'center_longitude' => round(($row->lng_bucket + 0.5) * $cell, 5), 'count' => (int) $row->count])->values()->all(), 'truncated' => $query->count() > 500, 'max_buckets' => 500];
    }
}
