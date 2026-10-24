<?php

namespace Modules\BidModule\Http\Controllers\APi\V1\Provider;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BidModule\Entities\Post;
use Modules\BidModule\Entities\PostBid;
use Modules\BidModule\Entities\QuoteInspection;

class QuotationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->branchId($request);
        $this->expireBranchQuotations($branchId);
        $posts = Post::with(['customer', 'service', 'service_address', 'addition_instructions', 'activeQuotation', 'inspections', 'fieldValues', 'attachments'])
            ->where('branch_id', $branchId)
            ->when($request->status, function ($query, $status) {
                $statuses = match ($status) {
                    'new_request' => ['submitted', 'under_review', 'inspection_required', 'inspection_scheduled'],
                    'placed_offer' => ['quote_ready', 'rejected', 'expired'],
                    'booking_placed' => ['accepted'],
                    default => [$status],
                };
                $query->whereIn('quote_status', $statuses);
            })
            ->latest()->paginate(max(1, min((int) $request->input('limit', 20), 100)));

        return response()->json(response_formatter(DEFAULT_200, $posts));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $branchId = $this->branchId($request);
        $this->expireBranchQuotations($branchId);
        $post = Post::with(['customer', 'service.activeFields', 'service_address', 'attachments', 'fieldValues.field', 'quotations', 'inspections'])
            ->where('branch_id', $branchId)->findOrFail($id);
        if ($post->quote_status === 'submitted') {
            Post::whereKey($post->id)->where('quote_status', 'submitted')->update(['quote_status' => 'under_review']);
            $post->refresh();
        }
        return response()->json(response_formatter(DEFAULT_200, $post));
    }

    public function issue(Request $request, string $postId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'offered_price' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:5000', 'terms' => 'nullable|string|max:10000',
            'estimated_duration_minutes' => 'required|integer|min:15|max:43200',
            'execution_type' => 'required|in:single,team',
            'required_technicians' => 'required|integer|min:1|max:100',
            'expires_in_hours' => 'sometimes|integer|min:1|max:720',
        ]);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 422);

        $quotation = DB::transaction(function () use ($request, $postId) {
            $post = Post::where('branch_id', $this->branchId($request))->whereKey($postId)->lockForUpdate()->firstOrFail();
            if ($post->is_booked || $post->quote_status === 'accepted') abort(409, translate('This quote request has already been accepted.'));

            $post->quotations()->where('status', 'ready')->update(['status' => 'revised', 'expired_at' => now()]);
            $revision = ((int) $post->quotations()->max('revision')) + 1;
            $inspection = $post->inspections()->where('fee_type', 'paid')->whereNotNull('paid_at')->latest()->first();
            $deduction = $inspection && !$inspection->deducted_at ? min((float) $inspection->amount, (float) $request->offered_price) : 0;

            $quote = new PostBid();
            $quote->quote_number = sprintf('Q-%s-%s', now()->format('Ymd'), strtoupper(substr(str_replace('-', '', $post->id), 0, 8)));
            if ($revision > 1) $quote->quote_number .= '-R' . $revision;
            $quote->post_id = $post->id;
            $quote->revision = $revision;
            $quote->provider_id = $post->branch_id;
            $quote->offered_price = $request->offered_price;
            $quote->provider_note = $request->note;
            $quote->terms = $request->terms;
            $quote->estimated_duration_minutes = $request->estimated_duration_minutes;
            $quote->execution_type = $request->execution_type;
            $quote->required_technicians = $request->required_technicians;
            $quote->inspection_deduction = $deduction;
            $quote->issued_at = now();
            $quote->expires_at = now()->addHours((int) $request->input('expires_in_hours', 48));
            $quote->status = 'ready';
            $quote->price_snapshot = [
                'offered_price' => (float) $request->offered_price,
                'inspection_deduction_if_accepted' => $deduction,
                'payable_if_accepted' => max(0, (float) $request->offered_price - $deduction),
                'currency' => business_config('currency_code', 'business_information')?->live_values ?? 'SAR',
            ];
            $quote->service_snapshot = $post->service?->only([
                'id', 'name', 'pricing_type', 'tax',
                'estimated_duration_minutes', 'execution_type', 'default_technicians',
            ]);
            $quote->save();
            if ($inspection) $inspection->update(['post_bid_id' => $quote->id]);
            $post->update(['quote_status' => 'quote_ready']);
            return $quote;
        });

        return response()->json(response_formatter(DEFAULT_STORE_200, $quotation), 201);
    }

    public function inspection(Request $request, string $postId): JsonResponse
    {
        if ($request->attributes->get('branch_workspace') && $request->filled('scheduled_at')) {
            $request->validate(['scheduled_at' => 'date']);
            $request->merge(['scheduled_at' => \Carbon\Carbon::parse($request->scheduled_at, config('nirab_execution.settlement_timezone'))->utc()->toDateTimeString()]);
        }
        $validator = Validator::make($request->all(), [
            'fee_type' => 'required|in:free,paid',
            'amount' => 'required_if:fee_type,paid|numeric|min:0',
            'scheduled_at' => 'nullable|date|after:now',
            'note' => 'nullable|string|max:5000',
        ]);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 422);

        $inspection = DB::transaction(function () use ($request, $postId) {
            $post = Post::where('branch_id', $this->branchId($request))->whereKey($postId)->lockForUpdate()->firstOrFail();
            abort_if($post->is_booked || $post->quote_status === 'accepted', 409);
            $inspection = QuoteInspection::create([
                'post_id' => $post->id, 'fee_type' => $request->fee_type,
                'amount' => $request->fee_type === 'paid' ? $request->amount : 0,
                'scheduled_at' => $request->scheduled_at,
                'status' => $request->scheduled_at ? 'scheduled' : 'required', 'note' => $request->note,
            ]);
            $post->update(['quote_status' => $request->scheduled_at ? 'inspection_scheduled' : 'inspection_required']);
            app(\App\Services\AuditService::class)->record('inspection.created', $inspection, [], $inspection->only(['scheduled_at','fee_type','amount','status','note']), $request->user(), $post->branch_id);
            return $inspection;
        }, 3);
        return response()->json(response_formatter(DEFAULT_STORE_200, $inspection), 201);
    }

    public function updateInspection(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), ['status' => 'required|in:scheduled,paid,completed,canceled']);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 422);

        $inspection = DB::transaction(function () use ($request, $id) {
            $inspection = QuoteInspection::whereHas('post', fn ($q) => $q->where('branch_id', $this->branchId($request)))->findOrFail($id);
            // Match quotation issue/acceptance lock order: parent first, then inspection.
            $post = Post::whereKey($inspection->post_id)->lockForUpdate()->firstOrFail();
            $inspection = QuoteInspection::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($post->is_booked || $post->quote_status === 'accepted', 409);
            if ($request->status === 'paid') app(\App\Services\NirabFinanceAccess::class)->authorize($request->user(), 'branch_verify', $post->branch_id);
            abort_if(in_array($inspection->status, ['completed','canceled'], true) && $request->status !== $inspection->status, 409);
            $old = $inspection->only(['status','paid_at','completed_at']);
            $data = ['status' => $request->status];
            if ($request->status === 'paid') $data['paid_at'] = $inspection->paid_at ?? now();
            if ($request->status === 'completed') $data['completed_at'] = $inspection->completed_at ?? now();
            $inspection->update($data);
            app(\App\Services\AuditService::class)->record('inspection.updated', $inspection, $old, $data, $request->user(), $post->branch_id);
            return $inspection;
        }, 3);
        return response()->json(response_formatter(DEFAULT_UPDATE_200, $inspection));
    }

    private function branchId(Request $request): string
    {
        $actor = $request->user();
        $access = app(\App\Services\OperationalAccess::class);
        $branch = str_starts_with($actor->user_type, 'provider-') ? $access->branch($actor) : $request->input('branch_id', $request->input('provider_id'));
        abort_unless(is_string($branch) && $branch !== '', 403);
        $access->authorizeBranch($actor, 'quotations.manage', $branch);
        return $branch;
    }

    private function expireBranchQuotations(string $branchId): void
    {
        $postIds = PostBid::query()
            ->where('status', 'ready')
            ->where('expires_at', '<=', now())
            ->whereHas('post', fn ($query) => $query->where('branch_id', $branchId))
            ->pluck('post_id');

        if ($postIds->isEmpty()) return;

        PostBid::whereIn('post_id', $postIds)
            ->where('status', 'ready')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'expired_at' => now()]);
        Post::whereIn('id', $postIds)
            ->where('quote_status', 'quote_ready')
            ->whereDoesntHave('quotations', fn ($query) => $query->where('status', 'ready'))
            ->update(['quote_status' => 'expired']);
    }
}
