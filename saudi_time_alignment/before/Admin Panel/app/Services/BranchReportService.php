<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\UserManagement\Entities\User;

class BranchReportService
{
    public const STATUSES = ['pending', 'accepted', 'assigned', 'on_the_way', 'arrived', 'ongoing', 'work_finished', 'completed', 'canceled'];

    public function __construct(private readonly OperationalAccess $access) {}

    /** One row per execution visit; a recurring parent is never also counted. */
    public function subjects(User $actor, array $filters, string $permission = 'analytics.read')
    {
        $scope = $permission === 'view_cash' ? app(NirabFinanceAccess::class) : $this->access;
        $columns = ['provider_id', 'booking_status', 'is_paid', 'total_booking_amount', 'actual_duration_seconds', 'arrived_at', 'service_schedule', 'payment_method', 'serviceman_id'];
        $single = $scope->scope(Booking::withoutGlobalScopes(), $actor, $permission)->where(fn ($q) => $q->where('is_repeated', 0)->orWhereNull('is_repeated'))
            ->select($columns)->selectRaw('id AS subject_id, id AS booking_id, NULL AS booking_repeat_id, readable_id, customer_id, zone_id, service_address_id, created_at, is_warranty');
        $repeat = $scope->scope(BookingRepeat::withoutGlobalScopes()->from('booking_repeats AS visits'), $actor, $permission, 'visits.provider_id')
            ->join('bookings AS parent', 'parent.id', '=', 'visits.booking_id')
            ->select(array_map(fn ($column) => 'visits.' . $column, $columns))
            ->selectRaw('visits.id AS subject_id, visits.booking_id, visits.id AS booking_repeat_id, parent.readable_id, parent.customer_id, parent.zone_id, parent.service_address_id, parent.created_at, parent.is_warranty');
        foreach ([[$single, 'bookings', 'booking_details', 'booking_id'], [$repeat, 'visits', 'booking_repeat_details', 'booking_repeat_id']] as [$query, $table, $details, $key]) {
            $query->when($filters['branch_id'] ?? null, fn ($q, $id) => $q->where($table . '.provider_id', $id));
            if (!empty($filters['start'])) {
                BranchPeriod::apply($query, $table . '.service_schedule', $filters);
            } elseif (!empty($filters['from'])) {
                $query->whereBetween($table . '.service_schedule', [$filters['from'], $filters['to']]);
            }
            if (!empty($filters['service_id'])) {
                $query->whereExists(fn ($q) => $q->selectRaw('1')->from($details)->whereColumn($key, $table . '.id')->where('service_id', $filters['service_id']));
            }
        }
        $query = DB::query()->fromSub($single->toBase()->unionAll($repeat->toBase()), 'jobs');
        $query->when($filters['status'] ?? null, fn ($q, $v) => $q->where('booking_status', $v))
            ->when($filters['technician_id'] ?? null, fn ($q, $v) => $q->where('serviceman_id', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q->where('readable_id', 'like', '%' . $v . '%')->orWhere('booking_id', $v)->orWhere('subject_id', $v)));
        if (!empty($filters['payment'])) $query->where('is_paid', $filters['payment'] === 'paid' ? 1 : 0);
        return $this->queue($query, $filters['queue'] ?? null);
    }

    public function queue($query, ?string $queue)
    {
        return match ($queue) {
            'pending' => $query->where('booking_status', 'pending'),
            'unassigned' => $query->where('booking_status', 'accepted')->whereNull('serviceman_id')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('booking_assignments')->whereColumn('booking_assignments.booking_id', 'jobs.booking_id')->whereIn('status', ['offered', 'accepted'])),
            'in_progress' => $query->whereIn('booking_status', ['on_the_way', 'arrived', 'ongoing', 'work_finished']),
            'overdue' => $query->whereIn('booking_status', ['pending', 'accepted', 'assigned', 'on_the_way'])->where('service_schedule', '<', now('UTC')->subMinutes(config('nirab_branch.late_minutes', 15))),
            'completed_unpaid' => $query->where('booking_status', 'completed')->where('is_paid', 0),
            default => $query,
        };
    }

    public function summary(User $actor, array $filters): array
    {
        $base = $this->subjects($actor, $filters);
        $states = $this->subjects($actor, array_diff_key($filters, array_flip(['status', 'queue'])))->select('booking_status')->selectRaw('COUNT(*) AS count')->groupBy('booking_status')->pluck('count', 'booking_status')->all();
        $selectedStates = (clone $base)->select('booking_status')->selectRaw('COUNT(*) AS count')->groupBy('booking_status')->pluck('count', 'booking_status')->all();
        $total = array_sum($selectedStates);
        $queues = [];
        foreach (['pending', 'unassigned', 'in_progress', 'overdue', 'completed_unpaid'] as $queue) $queues[$queue] = $this->subjects($actor, array_merge($filters, ['queue' => $queue]))->count();
        return ['states' => array_replace(array_fill_keys(self::STATUSES, 0), $states), 'queues' => $queues, 'orders' => $total,
            'completion_rate' => self::rate($selectedStates['completed'] ?? 0, $total), 'cancellation_rate' => self::rate($selectedStates['canceled'] ?? 0, $total),
            'booking_value' => (float) (clone $base)->sum('total_booking_amount')];
    }

    public static function rate(int $count, int $total): float { return $total > 0 ? round($count * 100 / $total, 2) : 0.0; }

    public function paymentLedger(User $actor, array $filters)
    {
        $subjects = $this->subjects($actor, ['branch_id' => $filters['branch_id'] ?? null], 'view_cash');
        $query = DB::query()->fromSub($subjects, 'j')->join('transactions AS payment', function ($join): void {
            $join->whereRaw('COALESCE(payment.booking_repeat_id, payment.booking_id) = j.subject_id');
        })->where('payment.trx_type', TRX_TYPE['booking_amount'])
            ->select(['payment.id', 'j.readable_id', 'j.subject_id', 'j.booking_id', 'j.booking_repeat_id', 'j.payment_method', 'payment.credit', 'payment.debit', 'payment.created_at']);
        return BranchPeriod::apply($query, 'payment.created_at', $filters);
    }

    /** Gross allocated customer payments. Refunds are shown separately and never reopen a canceled debt. */
    public function balances(User $actor, array $filters, string $permission = 'analytics.read')
    {
        $base = $this->subjects($actor, $filters, $permission);
        $ledger = DB::table('transactions')->where('trx_type', TRX_TYPE['booking_amount'])
            ->selectRaw('COALESCE(booking_repeat_id, booking_id) AS subject_id, SUM(credit) AS amount')->groupByRaw('COALESCE(booking_repeat_id, booking_id)');
        $partial = DB::table('booking_partial_payments')->whereNotIn('paid_with', ['cash', 'cash_after_service', 'bank_transfer', 'offline_payment'])
            ->selectRaw('booking_id, SUM(paid_amount) AS amount')->groupBy('booking_id');
        $cash = DB::table('payment_collections')->where('status', 'ready_for_accounting_sync')
            ->selectRaw('COALESCE(booking_repeat_id, booking_id) AS subject_id, SUM(amount) AS amount')->groupByRaw('COALESCE(booking_repeat_id, booking_id)');
        // MAX avoids adding the same electronic allocation from the partial table and ledger twice.
        $allocated = DB::query()->fromSub($base, 'j')->leftJoinSub($ledger, 'l', 'l.subject_id', '=', 'j.subject_id')
            ->leftJoinSub($partial, 'p', function ($join) { $join->on('p.booking_id', '=', 'j.booking_id')->whereNull('j.booking_repeat_id'); })
            ->leftJoinSub($cash, 'c', 'c.subject_id', '=', 'j.subject_id')->select('j.*')
            ->selectRaw("CASE WHEN payment_method IN ('cash','cash_after_service','bank_transfer','offline_payment') THEN CASE WHEN j.booking_repeat_id IS NOT NULL THEN COALESCE(l.amount,0) ELSE GREATEST(COALESCE(p.amount,0),COALESCE(l.amount,0)) END + COALESCE(c.amount,0) ELSE GREATEST(COALESCE(l.amount,0),COALESCE(p.amount,0)) END AS allocated");
        return DB::query()->fromSub($allocated, 'balances')->select('balances.*')
            ->selectRaw("CASE WHEN booking_status = 'canceled' OR is_warranty = 1 OR payment_method = 'prepaid_visit' THEN 0 ELSE GREATEST(0,total_booking_amount-allocated) END AS outstanding");
    }
}
