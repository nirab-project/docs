<?php

namespace Modules\BidModule\Http\Controllers\APi\V1\Customer;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Modules\BidModule\Entities\Post;
use Modules\BidModule\Entities\PostBid;
use Modules\BidModule\Entities\QuoteRequestAttachment;
use Modules\BidModule\Entities\ServiceFieldValue;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingDetail;
use Modules\BookingModule\Entities\BookingDetailsAmount;
use Modules\BookingModule\Entities\BookingScheduleHistory;
use Modules\BookingModule\Entities\BookingStatusHistory;
use Modules\ProviderManagement\Services\BranchAssignmentService;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Entities\ServiceField;
use Modules\ServiceManagement\Services\BranchServiceAvailabilityService;
use Modules\UserManagement\Entities\UserAddress;
use Modules\UserManagement\Entities\User;
use Modules\CustomerModule\Traits\CustomerAddressTrait;

class QuotationController extends Controller
{
    use CustomerAddressTrait;

    public function index(Request $request): JsonResponse
    {
        $this->expireCustomerQuotations($request->user()->id);
        $quotes = Post::query()
            ->with(['service' => fn ($query) => $query->withoutGlobalScopes()->whereNull('deleted_at')->with('translations'), 'branch', 'activeQuotation', 'inspections', 'booking'])
            ->withCount(['bids' => fn ($query) => $query->whereIn('status', ['ready', 'accepted'])])
            ->where('customer_user_id', $request->user()->id)
            ->when($request->status, fn ($q, $status) => $q->where('quote_status', $status))
            ->latest()->paginate(min((int) $request->input('limit', 20), 100));

        return response()->json(response_formatter(DEFAULT_200, $quotes));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $post = Post::with(['service' => fn ($query) => $query->withoutGlobalScopes()->whereNull('deleted_at')->with(['activeFields.options', 'translations']), 'branch', 'quotations.inspection', 'attachments', 'fieldValues.field', 'inspections', 'booking'])
            ->where('customer_user_id', $request->user()->id)->findOrFail($id);
        $this->expireDueQuotations($post);

        $post = $post->fresh(['service' => fn ($query) => $query->withoutGlobalScopes()->whereNull('deleted_at')->with(['activeFields.options', 'translations']), 'branch', 'quotations.inspection', 'attachments', 'fieldValues.field', 'inspections', 'booking']);
        foreach ($post->quotations as $quote) {
            $ready = !$post->booking_id && !$quote->booking_id && $quote->status === 'ready' && !$quote->isExpired();
            $quote->setAttribute('customer_actions', [
                'can_accept' => $ready && $post->service?->is_active && $post->service?->pricing_type === 'quote' && $quote->provider_id === $post->branch_id,
                'can_reject' => $ready,
            ]);
            $quote->setAttribute('payable_amount', $quote->status === 'accepted'
                ? max(0, round((float) $quote->offered_price - (float) $quote->inspection_deduction, 2))
                : $this->payableAmount($quote));
        }
        $post->setAttribute('currency', currency_code());
        return response()->json(response_formatter(DEFAULT_200, $post));
    }

    public function store(Request $request, BranchAssignmentService $branches, BranchServiceAvailabilityService $serviceAvailability): JsonResponse
    {
        $request->merge(['field_values' => $this->decodeArray($request->input('field_values', [])), 'additional_instructions' => $this->decodeArray($request->input('additional_instructions', []))]);
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|uuid|exists:services,id',
            'description' => 'required|string|max:10000',
            'service_address_id' => 'nullable|exists:user_addresses,id|required_without:service_address',
            'service_address' => 'nullable|json|required_without:service_address_id',
            'requested_window_start' => 'nullable|date',
            'requested_window_end' => 'nullable|date|after:requested_window_start',
            'branch_id' => 'nullable|uuid|exists:providers,id',
            'field_values' => 'nullable|array',
            'additional_instructions' => 'nullable|array|max:50',
            'additional_instructions.*' => 'string|max:2000',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,doc,docx|max:' . (int) uploadMaxFileSizeInKB('file'),
        ]);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 422);

        $service = Service::withoutGlobalScopes()->whereNull('deleted_at')->with('activeFields.options')->findOrFail($request->service_id);
        if (!$service->is_active || $service->pricing_type !== 'quote') {
            return response()->json(response_formatter(DEFAULT_400, null, translate('This service has a fixed price.')), 422);
        }

        if (!$request->filled('service_address_id')) {
            $request->merge(['service_address_id' => $this->add_address(json_decode($request->service_address), $request->user()->id)]);
        }
        $address = UserAddress::where('user_id', $request->user()->id)->findOrFail($request->service_address_id);
        $schedule = Carbon::parse($request->input('requested_window_start', now()->addHours(2)));
        Validator::make($address->getAttributes(), [
            'lat' => 'required|numeric|between:-90,90', 'lon' => 'required|numeric|between:-180,180',
            'address' => 'required|string', 'contact_person_name' => 'required|string', 'contact_person_number' => 'required|string',
        ])->validate();
        $zoneId = app(\App\Services\GeoService::class)->containing(\Modules\ZoneManagement\Entities\Zone::query(), 'coordinates', $address->lat, $address->lon)
            ->ofStatus(1)->orderBy('id')->value('id');
        abort_unless($zoneId && $zoneId === $address->zone_id, 422, 'service_address_zone_mismatch');
        $branch = $branches->resolve($zoneId, [$service->id], $schedule,
            $address->lat !== null ? (float) $address->lat : null,
            $address->lon !== null ? (float) $address->lon : null,
            $request->branch_id, fn ($candidate) => $serviceAvailability->isAvailable($candidate->id, $service->id, app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($candidate->id, $zoneId, $address->lat, $address->lon)), intakeOnly: true);
        if (!$branch) return response()->json(response_formatter(DEFAULT_400, null, translate('No branch is available for this request.')), 422);
        if (!$serviceAvailability->isAvailable($branch->id, $service->id, app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($branch->id, $zoneId, $address->lat, $address->lon))) {
            return response()->json(response_formatter(DEFAULT_400, null, translate('Service is unavailable in the selected area.')), 422);
        }

        $storedFiles = [];
        try {
            $post = DB::transaction(function () use ($request, $service, $address, $branch, $zoneId, &$storedFiles) {
                $answers = app(\Modules\ServiceManagement\Services\ServiceFieldAnswers::class)->quote(
                    $service, $request->field_values ?? [], $request->file('attachments', []), (int) uploadMaxFileSizeInKB('file'));

                $post = new Post();
                $post->service_description = $request->description;
                $post->booking_schedule = $request->requested_window_start;
                $post->requested_window_start = $request->requested_window_start;
                $post->requested_window_end = $request->requested_window_end;
                $post->customer_user_id = $request->user()->id;
                $post->branch_id = $branch->id;
                $post->service_id = $service->id;
                $post->service_address_id = $address->id;
                $post->zone_id = $zoneId;
                $post->quote_status = 'submitted';
                $post->location_snapshot = $address->toArray();
                $post->save();
                foreach ($request->additional_instructions ?? [] as $instruction) {
                    $post->addition_instructions()->create(['details' => $instruction]);
                }

                $stored = [];
                foreach ($request->file('attachments', []) as $index => $file) {
                    $disk = getDisk();
                    $path = $file->store('quote-requests', $disk);
                    if (!$path) throw new \RuntimeException('Quote attachment storage failed.');
                    $storedFiles[] = [$disk, $path];
                    $stored[$index] = QuoteRequestAttachment::create([
                        'post_id' => $post->id, 'file_name' => basename(str_replace('\\', '/', $file->getClientOriginalName())),
                        'file_path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $file->getSize(), 'storage_disk' => $disk,
                    ]);
                }
                $answers = \Modules\BidModule\Services\QuoteFieldAttachments::resolve($answers, $stored, $post->id);
                foreach ($answers as $answer) {
                    ServiceFieldValue::create([
                        'service_field_id' => $answer['field_id'], 'post_id' => $post->id,
                        'value' => ['value' => $answer['value']],
                        'field_snapshot' => array_diff_key($answer, array_flip(['value'])),
                    ]);
                }
                return $post;
            });

        } catch (\Throwable $exception) {
            foreach ($storedFiles as [$disk, $path]) {
                try { Storage::disk($disk)->delete($path); }
                catch (\Throwable $cleanupError) { report($cleanupError); }
            }
            throw $exception;
        }

        return response()->json(response_formatter(DEFAULT_STORE_200, $post->load(['branch', 'fieldValues', 'attachments'])), 201);
    }

    public function payableAmount(PostBid $quote): float
    {
        $inspection = $quote->post->inspections()->where('fee_type', 'paid')->whereNotNull('paid_at')->latest()->first();
        $deduction = $inspection && !$inspection->deducted_at ? min((float) $inspection->amount, (float) $quote->offered_price) : 0;
        return max(0, round((float) $quote->offered_price - $deduction, 2));
    }

    public function acceptVerifiedPayment(\Modules\PaymentModule\Entities\PaymentRequest $payment, PostBid $quote, ?string $schedule): Booking
    {
        abort_unless($payment->verified_at && $payment->gateway_state === 'paid' && (int) $payment->is_paid === 1, 422);
        $request = new Request(['payment_method' => $payment->payment_method, 'transaction_id' => $payment->transaction_id, 'service_schedule' => $schedule]);
        $request->setUserResolver(fn () => User::findOrFail($payment->payer_id));
        $request->attributes->set('nirab_verified_quote_payment', $payment);
        $response = $this->accept($request, $quote->id)->getData(true);
        return Booking::withoutGlobalScopes()->findOrFail($response['content']['id']);
    }

    public function paymentSnapshot(PostBid $quote, ?string $schedule): array
    {
        $post = $quote->post;
        abort_unless(Service::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($post->service_id)->where('is_active', 1)->where('pricing_type', 'quote')->exists(), 409, 'service_unavailable');
        abort_if($quote->status !== 'ready' || $quote->isExpired() || $post->booking_id || $quote->provider_id !== $post->branch_id, 409);
        abort_unless($schedule, 422, 'execution_schedule_required');
        $start = Carbon::parse($schedule);
        $address = $post->location_snapshot ?: [];
        $lat = $address['lat'] ?? $address['latitude'] ?? null;
        $lon = $address['lon'] ?? $address['longitude'] ?? null;
        $branch = \Modules\ProviderManagement\Entities\Provider::withoutGlobalScopes()->findOrFail($quote->provider_id);
        $area = app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($branch->id, $post->zone_id, $lat, $lon);
        $branches = app(BranchAssignmentService::class);
        abort_unless(!$start->isPast() && app(BranchServiceAvailabilityService::class)->isAvailable($branch->id, $post->service_id, $area)
            && app(\Modules\ProviderManagement\Services\BranchScheduleService::class)->contains($branch, $start, (int) $quote->estimated_duration_minutes)
            && $branches->isEligible($branch, $post->zone_id, [$post->service_id], $start, $lat, $lon)
            && $branches->isEligible($branch, $post->zone_id, [$post->service_id], $start->copy()->addMinutes($quote->estimated_duration_minutes), $lat, $lon)
            && app(\Modules\BookingModule\Services\AvailabilityService::class)->canFulfill($branch->id, [$post->service_id], $start, $quote->estimated_duration_minutes, $quote->required_technicians, $quote->execution_type), 409);
        return ['quotation_id' => $quote->id, 'revision' => (int) $quote->revision, 'provider_id' => $branch->id,
            'area_id' => $area, 'address_hash' => hash('sha256', json_encode($address)),
            'schedule' => $start->format('Y-m-d H:i:s'), 'offered_price' => (float) $quote->offered_price,
            'payable' => $this->payableAmount($quote), 'currency' => currency_code()];
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method' => $request->attributes->get('nirab_verified_quote_payment') instanceof \Modules\PaymentModule\Entities\PaymentRequest ? 'required|string|max:100' : 'sometimes|in:cash_after_service,wallet_payment,offline_payment',
            'service_schedule' => 'required|date',
        ]);
        if ($validator->fails()) return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 422);

        $booking = DB::transaction(function () use ($request, $id) {
            $quote = PostBid::whereKey($id)->lockForUpdate()->firstOrFail();
            $post = Post::whereKey($quote->post_id)->lockForUpdate()->firstOrFail();
            if ($post->customer_user_id !== $request->user()->id) abort(403);
            if ($quote->booking_id || $post->booking_id) {
                $existing = Booking::findOrFail($quote->booking_id ?: $post->booking_id);
                $payment = $request->attributes->get('nirab_verified_quote_payment');
                abort_if($payment && $existing->transaction_id !== $payment->transaction_id, 409);
                return $existing;
            }
            if ($quote->status !== 'ready' || $quote->isExpired()) {
                if ($quote->isExpired() && $quote->status === 'ready') {
                    $quote->update(['status' => 'expired', 'expired_at' => now()]);
                    $post->update(['quote_status' => 'expired']);
                }
                throw ValidationException::withMessages(['quotation' => translate('This quotation is not active or has expired.')]);
            }

            abort_unless(Service::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($post->service_id)->where('is_active', 1)->where('pricing_type', 'quote')->exists(), 409, 'service_unavailable');
            $inspection = $post->inspections()->where('fee_type', 'paid')->whereNotNull('paid_at')->lockForUpdate()->latest()->first();
            $deduction = $inspection && !$inspection->deducted_at ? min((float) $inspection->amount, (float) $quote->offered_price) : 0;
            $payable = max(0, round((float) $quote->offered_price - $deduction, 2));
            $verifiedPayment = $request->attributes->get('nirab_verified_quote_payment');
            $walletPortion = 0;
            if (!$verifiedPayment) {
                abort_unless($request->filled('quotation_payable_amount') && abs((float) $request->quotation_payable_amount - $payable) < 0.005, 409, 'quotation_price_changed');
                if ($request->boolean('is_partial') && $request->input('payment_method') !== 'wallet_payment') {
                    $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                    $walletPortion = floor((float) $user->wallet_balance * 100) / 100;
                    abort_unless($walletPortion > 0 && $walletPortion < $payable, 422);
                }
            }
            if ($verifiedPayment) {
                $additional = json_decode($verifiedPayment->additional_data ?? '{}', true) ?: [];
                $walletPortion = max(0, (float) ($additional['wallet_paid_amount'] ?? 0));
                abort_unless($verifiedPayment->payer_id === $post->customer_user_id && abs((float) $verifiedPayment->payment_amount + $walletPortion - $payable) < 0.005, 422);
                if ($walletPortion > 0) {
                    $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                    abort_unless((float) $user->wallet_balance >= $walletPortion, 422);
                }
            }
            if ($request->input('payment_method') === 'wallet_payment') {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if ((float) $user->wallet_balance < $payable) {
                    throw ValidationException::withMessages(['payment_method' => translate('Insufficient wallet balance.')]);
                }
            }
            $schedule = Carbon::parse($request->input('service_schedule'));
            if ($verifiedPayment) {
                $additional = json_decode($verifiedPayment->additional_data ?? '{}', true) ?: [];
                abort_unless(json_encode($additional['quotation_snapshot'] ?? null) === json_encode($this->paymentSnapshot($quote->setRelation('post', $post), $schedule->toIso8601String())), 409, 'quotation_context_changed');
            }

            $branch = \Modules\ProviderManagement\Entities\Provider::withoutGlobalScopes()->whereKey($post->branch_id)->lockForUpdate()->firstOrFail();
            $address = $post->location_snapshot ?: [];
            $latitude = $address['lat'] ?? $address['latitude'] ?? null;
            $longitude = $address['lon'] ?? $address['longitude'] ?? null;
            $branches = app(BranchAssignmentService::class);
            $areaId = app(\Modules\ProviderManagement\Services\BranchServiceAreaRepository::class)->resolveAreaId($branch->id, $post->zone_id, $latitude, $longitude);
            $available = !$schedule->isPast() && $branch->id === $quote->provider_id && app(BranchServiceAvailabilityService::class)->isAvailable($branch->id, $post->service_id, $areaId)
                && app(\Modules\ProviderManagement\Services\BranchScheduleService::class)->contains($branch, $schedule, (int) $quote->estimated_duration_minutes)
                && $branches->isEligible($branch, $post->zone_id, [$post->service_id], $schedule, $latitude, $longitude, false)
                && $branches->isEligible($branch, $post->zone_id, [$post->service_id], $schedule->copy()->addMinutes($quote->estimated_duration_minutes), $latitude, $longitude, false)
                && app(\Modules\BookingModule\Services\AvailabilityService::class)->canFulfill($branch->id, [$post->service_id], $schedule, $quote->estimated_duration_minutes, $quote->required_technicians, $quote->execution_type);
            if (!$available) throw ValidationException::withMessages(['service_schedule' => translate('selected_slot_is_no_longer_available')]);

            $booking = new Booking();
            $booking->customer_id = $post->customer_user_id;
            $booking->provider_id = $post->branch_id;
            $booking->quotation_id = $quote->id;
            $booking->zone_id = $post->zone_id;
            $booking->booking_status = 'pending';
            $paymentMethod = $request->input('payment_method', 'cash_after_service');
            $booking->is_paid = $paymentMethod === 'wallet_payment' || $verifiedPayment ? 1 : 0;
            $booking->payment_method = $paymentMethod;
            $booking->transaction_id = $paymentMethod === 'wallet_payment' ? 'wallet-payment' : $request->input('transaction_id');
            $booking->total_booking_amount = $payable;
            $booking->total_tax_amount = 0;
            $booking->total_discount_amount = 0;
            $booking->total_campaign_discount_amount = 0;
            $booking->total_coupon_discount_amount = 0;
            $booking->service_schedule = $schedule;
            $booking->service_address_id = $post->service_address_id;
            $booking->service_address_location = json_encode($post->location_snapshot);
            $booking->booking_otp = random_int(100000, 999999);
            $booking->is_guest = 0;
            $booking->execution_type = $quote->execution_type;
            $booking->required_technicians = $quote->required_technicians;
            $booking->estimated_duration_minutes = $quote->estimated_duration_minutes;
            $booking->inspection_deduction_amount = $deduction;
            $booking->pricing_snapshot = [
                'provider_id' => $branch->id, 'branch_service_area_id' => $areaId, 'service_schedule' => $schedule->toIso8601String(),
                'source' => 'quotation', 'quote_number' => $quote->quote_number, 'revision' => $quote->revision,
                'offered_price' => (float) $quote->offered_price, 'inspection_deduction' => $deduction,
                'total' => $payable, 'issued_at' => $quote->issued_at?->toISOString(),
            ];
            $booking->assignment_status = 'pending';
            $booking->assignment_due_at = now()->addMinutes(config('nirab_dispatch.assignment_delay_minutes'));
            $booking->save();

            $detail = new BookingDetail();
            $detail->booking_id = $booking->id;
            $detail->service_id = $post->service_id;
            $detail->service_name = $quote->service_snapshot['name'] ?? $post->service?->name ?? 'service';
            $detail->quantity = 1;
            $detail->service_cost = $payable;
            $detail->discount_amount = $deduction;
            $detail->tax_amount = 0;
            $detail->total_cost = $payable;
            $detail->save();

            $amounts = new BookingDetailsAmount();
            $amounts->forceFill([
                'booking_details_id' => $detail->id, 'booking_id' => $booking->id,
                'service_unit_cost' => $payable, 'service_quantity' => 1, 'service_tax' => 0,
                'discount_by_admin' => $deduction, 'discount_by_provider' => 0,
                'campaign_discount_by_admin' => 0, 'campaign_discount_by_provider' => 0,
                'coupon_discount_by_admin' => 0, 'coupon_discount_by_provider' => 0, 'admin_commission' => 0,
            ])->save();
            $scheduleHistory = new BookingScheduleHistory();
            $scheduleHistory->booking_id = $booking->id;
            $scheduleHistory->changed_by = $request->user()->id;
            $scheduleHistory->schedule = $schedule;
            $scheduleHistory->save();
            BookingStatusHistory::create(['booking_id' => $booking->id, 'changed_by' => $request->user()->id, 'booking_status' => 'pending']);

            foreach ($post->fieldValues as $value) {
                ServiceFieldValue::create([
                    'service_field_id' => $value->service_field_id, 'booking_id' => $booking->id,
                    'value' => $value->value, 'field_snapshot' => $value->field_snapshot,
                ]);
            }
            if ($deduction > 0) $inspection->update(['deducted_at' => now(), 'post_bid_id' => $quote->id]);
            $quote->update(['status' => 'accepted', 'accepted_at' => now(), 'booking_id' => $booking->id, 'inspection_deduction' => $deduction]);
            $post->update(['quote_status' => 'accepted', 'is_booked' => true, 'booking_id' => $booking->id]);

            if ($booking->payment_method === 'wallet_payment') placeBookingTransactionForWalletPayment($booking);
            elseif ($verifiedPayment && $walletPortion > 0) {
                \Modules\BookingModule\Entities\BookingPartialPayment::create(['booking_id' => $booking->id, 'paid_with' => 'wallet', 'paid_amount' => $walletPortion, 'due_amount' => $verifiedPayment->payment_amount]);
                \Modules\BookingModule\Entities\BookingPartialPayment::create(['booking_id' => $booking->id, 'paid_with' => 'digital', 'paid_amount' => $verifiedPayment->payment_amount, 'due_amount' => 0]);
                placeBookingTransactionForPartialDigital($booking, $walletPortion);
            } elseif ($verifiedPayment) placeBookingTransactionForDigitalPayment($booking);
            elseif ($walletPortion > 0) {
                \Modules\BookingModule\Entities\BookingPartialPayment::create(['booking_id' => $booking->id, 'paid_with' => 'wallet', 'paid_amount' => $walletPortion, 'due_amount' => $payable - $walletPortion]);
                placeBookingTransactionForPartialCas($booking, $walletPortion);
            }
            return $booking;
        });

        return response()->json(response_formatter(DEFAULT_UPDATE_200, $booking));
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        DB::transaction(function () use ($request, $id): void {
            $quote = PostBid::whereKey($id)->lockForUpdate()->firstOrFail();
            $post = Post::whereKey($quote->post_id)->lockForUpdate()->firstOrFail();
            abort_unless($post->customer_user_id === $request->user()->id, 403);
            if ($quote->status === 'rejected') return;
            abort_unless(!$post->booking_id && !$quote->booking_id && $quote->status === 'ready' && !$quote->isExpired(), 409);
            $quote->update(['status' => 'rejected', 'rejected_at' => now()]);
            $post->update(['quote_status' => 'rejected']);
        });
        return response()->json(response_formatter(DEFAULT_UPDATE_200));
    }

    private function expireDueQuotations(Post $post): void
    {
        $expired = $post->quotations()->where('status', 'ready')->where('expires_at', '<=', now())->update(['status' => 'expired', 'expired_at' => now()]);
        if ($expired && !$post->quotations()->where('status', 'ready')->exists()) $post->update(['quote_status' => 'expired']);
    }

    private function expireCustomerQuotations(string $customerId): void
    {
        $postIds = PostBid::query()
            ->where('status', 'ready')
            ->where('expires_at', '<=', now())
            ->whereHas('post', fn ($query) => $query->where('customer_user_id', $customerId))
            ->pluck('post_id');

        if ($postIds->isEmpty()) return;

        PostBid::whereIn('post_id', $postIds)
            ->where('status', 'ready')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'expired_at' => now()]);
        Post::whereIn('id', $postIds)
            ->whereDoesntHave('quotations', fn ($query) => $query->where('status', 'ready'))
            ->update(['quote_status' => 'expired']);
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
