<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\{AuditService, BranchPeriod, BranchReportService, NirabFinanceAccess, OperationalAccess};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\BookingModule\Entities\{Booking, BookingRepeat, CashHandover, PaymentCollection, TechnicianCustodyEntry};
use Modules\OdooIntegration\Models\{Invoice, Outbox};
use Modules\PaymentModule\Entities\PaymentRefund;
use Modules\ProviderManagement\Entities\Provider;
use Modules\UserManagement\Entities\Serviceman;

class BranchWorkspaceController extends Controller
{
    public function __construct(private readonly OperationalAccess $access, private readonly NirabFinanceAccess $finance, private readonly BranchReportService $reports) {}

    public function context(Request $request): array
    {
        abort_unless(nirab_branch_mode(), 404);
        $actor = $request->user();
        $branchId = str_starts_with($actor->user_type, 'provider-') ? $this->access->branch($actor) : $request->input('branch_id');
        abort_unless($branchId, 422, __('branch.select_branch'));
        if (str_starts_with($actor->user_type, 'provider-')) abort_if($request->filled('branch_id') && $request->branch_id !== $branchId, 403);
        $request->merge(['branch_id' => $branchId, 'provider_id' => $branchId]);
        $request->attributes->set('branch_workspace', true);
        $prefix = str_starts_with($actor->user_type, 'provider-') ? 'provider' : 'admin';
        $tabs = [];
        foreach (['overview' => 'analytics.read', 'bookings' => 'analytics.read', 'operations' => 'dispatch.manage', 'quality' => 'complaints.read', 'quotes' => 'quotations.manage', 'reports' => 'analytics.read'] as $tab => $permission) {
            if ($this->access->allows($actor, $permission, $branchId)) $tabs[] = $tab;
        }
        if (collect(OperationalAccess::MANAGEMENT_PERMISSIONS)->contains(fn ($p) => $this->access->allows($actor, $p, $branchId))) $tabs[] = 'operations';
        if ($this->access->allows($actor, 'analytics.read', $branchId)) $tabs[] = 'quality';
        if ($this->finance->allows($actor, 'view_cash', $branchId)) array_push($tabs, 'finance', 'invoices', 'audit');
        abort_unless($tabs, 403);
        $tabs[] = 'settings';
        $branch = Provider::withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($branchId);
        $base = url($prefix . '/branch-workspace');
        $layout = $prefix === 'admin' ? 'adminmodule::layouts.new-master' : 'providermanagement::layouts.new-master';
        return compact('branch', 'branchId', 'prefix', 'base', 'layout') + ['tabs' => array_values(array_unique($tabs))];
    }

    public function index(Request $request)
    {
        $context = $this->context($request);
        $tab = $request->input('tab', $context['tabs'][0]);
        abort_unless(in_array($tab, $context['tabs'], true), 403);
        $request->merge(['tab' => $tab]);
        $filters = BranchPeriod::filters($request);
        $data = $context + compact('tab', 'filters');
        if ($tab === 'operations') {
            return app(\Modules\ServiceManagement\Http\Controllers\Web\Admin\OperationsController::class)->index($request)->with($data);
        }
        if ($tab === 'quality') return app(QualityAdministrationController::class)->index($request)->with($data);
        if (in_array($tab, ['overview', 'bookings', 'reports'], true)) {
            $this->access->authorizeBranch($request->user(), 'analytics.read', $context['branchId']);
            $data['summary'] = $this->reports->summary($request->user(), $filters);
            $data['rows'] = $this->reports->subjects($request->user(), $filters)->orderBy('service_schedule')->orderBy('subject_id')->paginate(30)->withQueryString();
            $data['technicians'] = Serviceman::withoutGlobalScopes()->with('user')->whereNull('deleted_at')->where('provider_id', $context['branchId'])->get();
            $data['invoicesBySubject'] = Invoice::where('branch_id', $context['branchId'])->whereIn(DB::raw('COALESCE(booking_repeat_id, booking_id)'), $data['rows']->pluck('subject_id'))->orderBy('created_at')->get()->keyBy(fn ($i) => $i->booking_repeat_id ?: $i->booking_id);
        }
        if ($tab === 'finance') $data += $this->financialData($request, $filters);
        if ($tab === 'invoices') {
            $request->validate(['invoice_status' => 'nullable|in:draft,posted,canceled,cancel', 'sync_status' => 'nullable|in:pending,processing,synced,failed,requires_attention']);
            $data['invoices'] = $this->invoiceQuery($request, $filters)->latest('issued_at')->paginate(25)->withQueryString();
            $data['outbox'] = BranchPeriod::apply(Outbox::where('branch_id', $context['branchId']), 'created_at', $filters)
                ->when($request->sync_status, fn ($q, $v) => $q->where('status', $v))->latest()->paginate(20, ['*'], 'outbox_page')->withQueryString();
        }
        if ($tab === 'audit') {
            $request->validate(['event' => 'nullable|string|max:100', 'entity_id' => 'nullable|uuid']);
            $data['audit'] = BranchPeriod::apply(AuditLog::with('actor')->where('branch_id', $context['branchId']), 'created_at', $filters)
                ->when($request->event, fn ($q, $v) => $q->where('event', $v))->when($request->entity_id, fn ($q, $v) => $q->where('entity_id', $v))
                ->latest()->paginate(25)->withQueryString();
            $data['auditReferences'] = app(\App\Services\AuditPresentation::class)->references($data['audit'], [$context['branchId']]);
        }
        return view('branch.workspace', $data);
    }

    private function financialData(Request $request, array $filters): array
    {
        $this->finance->authorize($request->user(), 'view_cash', $request->branch_id);
        // Finance visibility is separate from analytics visibility.
        $balanceRows = $this->reports->balances($request->user(), $filters, 'view_cash');
        $totals = DB::query()->fromSub(clone $balanceRows, 'b')->selectRaw('SUM(total_booking_amount) AS booking_value, SUM(allocated) AS allocated, SUM(outstanding) AS outstanding')->first();
        $collections = $this->financialRows($request, $filters, 'collections')->with('technician.user');
        $refunds = $this->financialRows($request, $filters, 'refunds');
        $entries = TechnicianCustodyEntry::where('provider_id', $request->branch_id);
        $opening = (float) (clone $entries)->where('occurred_at', '<', $filters['start'])->sum('amount');
        $movements = BranchPeriod::apply(clone $entries, 'occurred_at', $filters);
        $movementTotal = (float) (clone $movements)->sum('amount');
        return ['balances' => $balanceRows->orderBy('service_schedule')->orderBy('subject_id')->paginate(25)->withQueryString(), 'totals' => $totals,
            'payments' => $this->reports->paymentLedger($request->user(), $filters)->orderByDesc('payment.created_at')->paginate(25, ['*'], 'payment_page')->withQueryString(),
            'collections' => $collections->latest()->paginate(20, ['*'], 'collection_page')->withQueryString(),
            'refunds' => (clone $refunds)->latest()->paginate(20, ['*'], 'refund_page')->withQueryString(),
            'refundTotals' => (clone $refunds)->reorder()->select('status')->selectRaw('SUM(amount) AS amount')->groupBy('status')->pluck('amount', 'status'),
            'custody' => compact('opening', 'movementTotal') + ['closing' => $opening + $movementTotal, 'current' => (float) (clone $entries)->sum('amount')],
            'entries' => $movements->with(['technician.user', 'actor'])->latest('occurred_at')->paginate(25, ['*'], 'entry_page')->withQueryString()];
    }

    public function invoiceQuery(Request $request, array $filters)
    {
        $this->finance->authorize($request->user(), 'view_cash', $request->branch_id);
        return Invoice::where('branch_id', $request->branch_id)
            ->whereRaw('COALESCE(issued_at, created_at) >= ?', [$filters['start']])->whereRaw('COALESCE(issued_at, created_at) < ?', [$filters['end']])
            ->when($request->invoice_status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(fn ($q) => $q->where('number', 'like', '%' . $v . '%')->orWhere('booking_id', $v)));
    }

    public function export(Request $request)
    {
        $context = $this->context($request);
        $filters = BranchPeriod::filters($request);
        $tab = $request->input('tab', 'bookings');
        abort_unless(in_array($tab, $context['tabs'], true), 403);
        $query = match ($tab) {
            'bookings', 'overview', 'reports' => $this->reports->subjects($request->user(), $filters)->orderBy('subject_id'),
            'finance' => $this->financeExport($request, $filters),
            'invoices' => $this->invoiceQuery($request, $filters)->orderBy('id'),
            default => abort(422),
        };
        $columns = match ($tab) {
            'finance' => match ($request->input('dataset', 'balances')) {
                'collections' => ['booking_id','booking_repeat_id','technician_id','amount','method','status','collected_at'],
                'refunds' => ['booking_id','booking_repeat_id','amount','destination','status','reason','created_at','completed_at'],
                'custody' => ['technician_id','type','amount','reason','source_id','occurred_at'],
                'handovers' => ['technician_id','amount','received_amount','status','created_at','confirmed_at','accountant_verified_at','note'],
                'payments' => ['id','readable_id','subject_id','payment_method','credit','debit','created_at'],
                default => ['readable_id', 'subject_id', 'booking_status', 'service_schedule', 'total_booking_amount', 'allocated', 'outstanding'],
            },
            'invoices' => ['number', 'booking_id', 'booking_repeat_id', 'status', 'payment_status', 'amount', 'currency', 'issued_at'],
            default => ['readable_id', 'subject_id', 'booking_repeat_id', 'booking_status', 'is_paid', 'service_schedule', 'serviceman_id', 'total_booking_amount'],
        };
        return $this->csv($query, $columns, 'branch-' . $tab);
    }

    private function financeExport(Request $request, array $filters)
    {
        $this->finance->authorize($request->user(), 'view_cash', $request->branch_id);
        $request->validate(['dataset' => 'nullable|in:balances,collections,refunds,custody,handovers,payments']);
        if ($request->filled('dataset') && $request->dataset !== 'balances') return $this->financialRows($request, $filters, $request->dataset)->orderBy('id');
        return $this->reports->balances($request->user(), $filters, 'view_cash')->orderBy('subject_id');
    }

    private function financialRows(Request $request, array $filters, string $dataset)
    {
        $this->finance->authorize($request->user(), 'view_cash', $request->branch_id);
        if ($dataset === 'payments') return $this->reports->paymentLedger($request->user(), $filters);
        [$query, $date] = match ($dataset) {
            'collections' => [PaymentCollection::where('provider_id', $request->branch_id), 'collected_at'],
            'refunds' => [PaymentRefund::where('provider_id', $request->branch_id), 'created_at'],
            'custody' => [TechnicianCustodyEntry::where('provider_id', $request->branch_id), 'occurred_at'],
            'handovers' => [CashHandover::where('provider_id', $request->branch_id), 'created_at'],
        };
        return BranchPeriod::apply($query, $date, $filters);
    }

    public function csv($query, array $columns, string $name)
    {
        return response()->streamDownload(function () use ($query, $columns): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $columns, ',', '"', '');
            foreach ($query->cursor() as $row) fputcsv($out, array_map(fn ($key) => self::csvCell($row->$key ?? ''), $columns), ',', '"', '');
            fclose($out);
        }, $name . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public static function csvCell($value): string
    {
        $text = (string) $value;
        return preg_match('/^[\s]*[=+@-]/u', $text) ? "'" . $text : $text;
    }

    public function branchReport(Request $request, bool $download = false)
    {
        $filters = BranchPeriod::filters($request);
        $rows = $this->reports->subjects($request->user(), $filters)->leftJoin('providers', 'providers.id', '=', 'jobs.provider_id')
            ->select('jobs.provider_id', 'providers.company_name')->selectRaw("COUNT(*) AS orders, SUM(booking_status = 'completed') AS completed, SUM(booking_status = 'canceled') AS canceled, ROUND(100 * SUM(booking_status = 'completed') / COUNT(*), 2) AS completion_rate, ROUND(100 * SUM(booking_status = 'canceled') / COUNT(*), 2) AS cancellation_rate, SUM(total_booking_amount) AS booking_value")
            ->groupBy('jobs.provider_id', 'providers.company_name')->orderBy('providers.company_name');
        if ($download) return $this->csv($rows, ['provider_id', 'company_name', 'orders', 'completed', 'canceled', 'completion_rate', 'cancellation_rate', 'booking_value'], 'branches');
        return view('branch.report', ['rows' => $rows->paginate(30)->withQueryString(), 'filters' => $filters]);
    }

    public function bookingAction(Request $request, string $kind, string $id, string $action)
    {
        abort_unless(in_array($kind, ['booking', 'booking_repeat'], true), 404);
        $this->access->authorizeBranch($request->user(), 'dispatch.manage', $request->branch_id);
        $subject = ($kind === 'booking' ? Booking::withoutGlobalScopes() : BookingRepeat::withoutGlobalScopes())->where('provider_id', $request->branch_id)->findOrFail($id);
        $controller = str_starts_with($request->user()->user_type, 'provider-')
            ? \Modules\BookingModule\Http\Controllers\Web\Provider\BookingController::class : \Modules\BookingModule\Http\Controllers\Web\Admin\BookingController::class;
        abort_unless(in_array($action, ['status', 'schedule'], true), 404);
        $data = $request->validate(['reason' => 'required|string|max:2000', 'service_schedule' => 'required_if:action,schedule|nullable|date', 'booking_status' => 'nullable|in:' . implode(',', BranchReportService::STATUSES)]);
        if ($action === 'schedule') {
            $request->validate(['service_schedule' => 'required|date|after:now']);
            $request->merge(['service_schedule' => \Carbon\Carbon::parse($request->service_schedule, config('nirab_execution.settlement_timezone'))->utc()->toDateTimeString()]);
            $request->validate(['service_schedule' => 'required|date|after:now']);
        }
        return DB::transaction(function () use ($subject, $request, $controller, $action, $id, $data) {
            $subject = $subject->newQueryWithoutScopes()->whereKey($id)->where('provider_id', $request->branch_id)->lockForUpdate()->firstOrFail();
            $old = $subject->only(['booking_status', 'service_schedule']);
            $response = app($controller)->{$action === 'status' ? 'statusUpdate' : 'scheduleUpdate'}($id, $request);
            $fresh = $subject->fresh();
            $applied = $action === 'status' ? $fresh->booking_status === $request->booking_status
                : \Carbon\Carbon::parse($fresh->service_schedule)->equalTo(\Carbon\Carbon::parse($request->service_schedule));
            abort_unless($applied, 422, $response->getData(true)['message'] ?? __('branch.failed'));
            app(AuditService::class)->record('branch.booking.' . $action, $subject, $old, $subject->fresh()->only(['booking_status', 'service_schedule']) + ['reason' => $data['reason']], $request->user());
            return $response;
        });
    }
}
