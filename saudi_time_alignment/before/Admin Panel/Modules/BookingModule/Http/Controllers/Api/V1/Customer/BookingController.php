<?php

namespace Modules\BookingModule\Http\Controllers\Api\V1\Customer;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\BidModule\Entities\PostBid;
use Illuminate\Support\Facades\Validator;
use Modules\BookingModule\Entities\BookingOfflinePayment;
use Modules\BookingModule\Entities\BookingPartialPayment;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\PaymentModule\Entities\PaymentRequest;
use Modules\UserManagement\Entities\User;
use Modules\CartModule\Entities\Cart;
use Modules\PromotionManagement\Entities\Coupon;
use Modules\BookingModule\Entities\Booking;
use Modules\PaymentModule\Entities\OfflinePayment;
use Modules\BookingModule\Http\Traits\BookingTrait;
use Modules\BookingModule\Http\Traits\RebookingTrait;
use Modules\CustomerModule\Traits\CustomerAddressTrait;
use Modules\BookingModule\Entities\BookingStatusHistory;
use Modules\BidModule\Http\Controllers\APi\V1\Customer\PostBidController;
use Modules\BookingModule\Services\TrackingService;
use Modules\BidModule\Http\Controllers\APi\V1\Customer\QuotationController;

class BookingController extends Controller
{
    use BookingTrait, CustomerAddressTrait, RebookingTrait;

    private Booking $booking;
    private BookingStatusHistory $bookingStatusHistory;

    protected OfflinePayment $offlinePayment;
    private BookingRepeat $bookingRepeat;
    private bool $isCustomerLoggedIn;
    private mixed $customerUserId;

    public function __construct(Booking $booking, BookingStatusHistory $bookingStatusHistory, Request $request, OfflinePayment $offlinePayment, BookingRepeat $bookingRepeat)
    {
        $this->booking = $booking;
        $this->bookingStatusHistory = $bookingStatusHistory;
        $this->offlinePayment = $offlinePayment;
        $this->bookingRepeat = $bookingRepeat;

        $this->isCustomerLoggedIn = (bool)auth('api')->user();
        $this->customerUserId = $this->isCustomerLoggedIn ? auth('api')->user()->id : $request['guest_id'];
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return JsonResponse
     */
    public function checkoutPrice(Request $request): JsonResponse
    {
        abort_unless(nirab_branch_mode(), 404);
        $request->validate(['guest_id' => $this->isCustomerLoggedIn ? 'nullable' : 'required|uuid']);
        $this->assertGuestBookingPrincipal($request);
        abort_if(!$this->isCustomerLoggedIn && User::whereKey($this->customerUserId)->exists(), 403);
        $snapshot = app(\Modules\BookingModule\Services\BranchCheckoutService::class)->preview((string) $this->customerUserId, $request->all());
        $payload = $snapshot->payload;
        return response()->json(response_formatter(DEFAULT_200, [
            'checkout_snapshot_id' => $snapshot->id, 'price_hash' => $snapshot->price_hash,
            'total' => $payload['total'], 'wallet_amount' => $payload['wallet_amount'], 'currency' => $payload['currency'], 'timezone' => config('app.timezone'),
            'expires_at' => $snapshot->expires_at->toIso8601String(), 'groups' => $payload['groups'],
        ]));
    }

    public function placeRequest(Request $request): JsonResponse
    {
        $serviceAtProviderPlace = (int)((business_config('service_at_provider_place', 'provider_config'))->live_values ?? 0);

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|in:' . implode(',', array_column(PAYMENT_METHODS, 'key')),
            'zone_id' => 'required|uuid',
            'service_schedule' => 'required_if:service_type,regular|nullable|date',
            'service_address_id' => is_null($request['service_address']) ? 'required' : 'nullable',

            'post_id' => 'nullable|uuid',
            'quotation_id' => 'nullable|uuid',
            'provider_id' => 'nullable|uuid',
            'branch_selection' => 'nullable|in:manual,auto',

            'guest_id' => $this->isCustomerLoggedIn ? 'nullable' : 'required|uuid',
            'service_address' => is_null($request['service_address_id']) && $request['service_location'] == 'customer' ? [
                'required',
                'json',
                function ($attribute, $value, $fail) {
                    $decoded = json_decode($value, true);

                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded) || array_is_list($decoded)) {
                        $fail($attribute . ' must be a JSON address object.');
                        return;
                    }

                    foreach (['lat', 'lon', 'address', 'contact_person_name', 'contact_person_number', 'address_label'] as $field) {
                        if (!isset($decoded[$field]) || $decoded[$field] === '') {
                            $fail($attribute . ' must contain "' . $field . '" properties.');
                        }
                    }
                },
            ] : '',

            'is_partial' => 'nullable|in:0,1',
            'service_location' => ['required', 'in:customer,provider',
                function ($attribute, $value, $fail) use ($serviceAtProviderPlace) {
                    if ($value == 'provider' && $serviceAtProviderPlace != 1) {
                        $fail('The selected service location cannot be "provider" because the service is not available at the provider’s place.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }
        $this->assertGuestBookingPrincipal($request);

        $newUserInfo = null;
        if ($request->has('new_user_info') && !empty($request->get('new_user_info')) && !$this->isCustomerLoggedIn) {
            $newUserInfo = json_decode($request['new_user_info'], true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($newUserInfo)) {
                return response()->json(response_formatter(DEFAULT_400, null, 'Invalid new_user_info format'), 400);
            }

            $newUserValidator = Validator::make($newUserInfo, [
                'first_name' => 'required',
                'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10',
                'password' => 'required|min:8',
            ]);

            if ($newUserValidator->fails()) {
                return response()->json(response_formatter(DEFAULT_400, null, error_processor($newUserValidator)), 400);
            }
        }

        $customerUserId = $this->customerUserId;

        abort_if(!$this->isCustomerLoggedIn && User::whereKey($customerUserId)->exists(), 403);

        $proMemberCouponBlock = $this->blockIneligibleProMemberCoupon($customerUserId);
        if ($proMemberCouponBlock) {
            return $proMemberCouponBlock;
        }

        if (nirab_branch_mode() && !$request->filled('post_id')) {
            $response = app(\Modules\BookingModule\Services\BranchCheckoutService::class)->confirm((string) $customerUserId, $request->all(), null, $newUserInfo);
            return response()->json(response_formatter(BOOKING_PLACE_SUCCESS_200, $response));
        }

        if (is_null($request['service_address_id'])) {
            $request['service_address_id'] = $this->add_address(json_decode($request['service_address']), $customerUserId, !$this->isCustomerLoggedIn, $request->service_location);
        }

        if (nirab_branch_mode() && $request->filled('post_id')) {
            $quote = PostBid::where('post_id', $request->post_id)
                ->where('provider_id', $request->provider_id)
                ->whereIn('status', ['ready', 'accepted'])
                ->when($request->quotation_id, fn ($query) => $query->whereKey($request->quotation_id))->latest('revision')->first();
            if (!$quote) return response()->json(response_formatter(DEFAULT_404), 404);
            return app(QuotationController::class)->accept($request, $quote->id);
        }

        $minimumBookingAmount = (float)(business_config('min_booking_amount', 'booking_setup'))?->live_values;
        $totalBookingAmount = cart_total($customerUserId) + getServiceFee();

        if (!isset($request['post_id']) && $minimumBookingAmount > 0 && $totalBookingAmount < $minimumBookingAmount) {
            return response()->json(response_formatter(MINIMUM_BOOKING_AMOUNT_200), 200);
        }

        if ($request['payment_method'] == 'wallet_payment') {
            if (!isset($request['post_id'])) {
                $response = $this->placeBookingRequest(userId: $customerUserId, request: $request, transactionId: 'wallet_payment', newUserInfo: $newUserInfo);
            } else {
                $postBid = PostBid::with(['post'])
                    ->where('post_id', $request['post_id'])
                    ->where('provider_id', $request['provider_id'])
                    ->first();

                $data = [
                    'payment_method' => $request['payment_method'],
                    'zone_id' => $request['zone_id'],
                    'service_tax' => $postBid?->post?->service?->tax,
                    'provider_id' => $postBid->provider_id,
                    'price' => $postBid->offered_price,
                    'service_schedule' => !is_null($request['booking_schedule']) ? $request['booking_schedule'] : $postBid->post->booking_schedule,
                    'service_id' => $postBid->post->service_id,
                    'service_address_id' => !is_null($request['service_address_id']) ? $request['service_address_id'] : $postBid->post->service_address_id,
                    'is_partial' => $request['is_partial']
                ];

                $user = User::find($customerUserId);
                $tax = !is_null($data['service_tax']) ? round((($data['price'] * $data['service_tax']) / 100) * 1, 2) : 0;
                if (isset($user) && $user->wallet_balance < ($postBid->offered_price + $tax)) {
                    return response()->json(response_formatter(INSUFFICIENT_WALLET_BALANCE_400), 400);
                }

                $response = $this->placeBookingRequestForBidding($customerUserId, $request, 'wallet_payment', $data);

                if ($response['flag'] == 'success') {
                    PostBidController::acceptPostBidOffer($postBid->id, $response['booking_id']);
                }
            }

        } elseif ($request['payment_method'] == 'offline_payment') {
            if (!isset($request['post_id'])) {
                $response = $this->placeBookingRequest($customerUserId, $request, 'offline-payment', newUserInfo: $newUserInfo, isGuest: !$this->isCustomerLoggedIn);
            } else {
                $postBid = PostBid::with(['post'])
                    ->where('post_id', $request['post_id'])
                    ->where('provider_id', $request['provider_id'])
                    ->first();

                $data = [
                    'payment_method' => $request['payment_method'],
                    'zone_id' => $request['zone_id'],
                    'service_tax' => $postBid?->post?->service?->tax,
                    'provider_id' => $postBid->provider_id,
                    'price' => $postBid->offered_price,
                    'service_schedule' => !is_null($request['booking_schedule']) ? $request['booking_schedule'] : $postBid->post->booking_schedule,
                    'service_id' => $postBid->post->service_id,
                    'service_address_id' => !is_null($request['service_address_id']) ? $request['service_address_id'] : $postBid->post->service_address_id,
                    'is_partial' => $request['is_partial']
                ];

                $response = $this->placeBookingRequestForBidding($customerUserId, $request, 'offline_payment', $data);

                if ($response['flag'] == 'success') {
                    PostBidController::acceptPostBidOffer($postBid->id, $response['booking_id']);
                }
            }
        } else {
            if ($request['service_type'] == 'repeat'){
                $response = $this->placeRepeatBookingRequest($customerUserId, $request, 'cash-payment', newUserInfo: $newUserInfo, isGuest: !$this->isCustomerLoggedIn);
            }else{
                $response = $this->placeBookingRequest($customerUserId, $request, 'cash-payment', newUserInfo: $newUserInfo, isGuest: !$this->isCustomerLoggedIn);
            }
        }

        if ($response['flag'] == 'success') {
            return response()->json(response_formatter(BOOKING_PLACE_SUCCESS_200, $response), 200);
        } else {
            return response()->json(response_formatter(BOOKING_PLACE_FAIL_200), 200);
        }
    }

    private function appendOfflineTransferDetails(Booking $booking): void
    {
        $booking->offline_transfer = app(\Modules\BookingModule\Services\OfflineTransferService::class)->summary($booking);
        $offline = $booking->booking_offline_payments()->first();
        if ($offline) {
            $booking->booking_offline_payment_method = $offline->method_name;
            $booking->booking_offline_payment = collect($offline->customer_information)->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all();
            $booking->offline_payment_id = $offline->offline_payment_id;
            $booking->offline_payment_status = $offline->payment_status;
            $booking->offline_payment_denied_note = $offline->denied_note;
        }
        unset($booking->booking_offline_payments);
    }

    private function assertGuestBookingPrincipal(Request $request): void
    {
        if ($this->isCustomerLoggedIn) return;
        $principal = app(\Modules\PaymentModule\Services\PaymentPrincipal::class)->resolve($request);
        abort_unless(($principal['is_guest'] ?? false)
            && (string) ($principal['id'] ?? '') === (string) $this->customerUserId,
            401, 'guest_session_required');
    }

    private function blockIneligibleProMemberCoupon($customerUserId): ?JsonResponse
    {
        $couponCode = Cart::where('customer_id', $customerUserId)->whereNotNull('coupon_code')->value('coupon_code');
        if (!$couponCode) {
            return null;
        }

        $coupon = Coupon::where('coupon_code', $couponCode)->first();
        if (!$coupon || $coupon->coupon_type !== 'pro_member') {
            return null;
        }

        $isProMember = $this->isCustomerLoggedIn && (bool)User::find($customerUserId)?->pro_member_status;
        $proCouponActive = \Modules\CustomerSubscriptionModule\Lib\ProMemberBenefit::activeBenefitType($customerUserId) === 'coupon';
        if (!$proCouponActive) {
            return response()->json(response_formatter($isProMember ? COUPON_NOT_IN_CURRENT_PLAN : COUPON_IS_VALID_FOR_PRO_MEMBER), 200);
        }

        return null;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric|min:1|max:200',
            'offset' => 'required|numeric|min:1|max:100000',
            'booking_status' => 'required|in:all,' . implode(',', array_column(BOOKING_STATUSES, 'key')),
            'service_type' => 'required|in:all,regular,repeat',
            'string' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $bookings = $this->booking
            ->with(['customer', 'repeat', 'customizeBooking'])
            ->where(['customer_id' => $request->user()->id])
            ->search(base64_decode($request['string']), ['readable_id'])
            ->when($request['booking_status'] != 'all', function ($query) use ($request) {
                return $query->ofBookingStatus($request['booking_status']);
            })
            ->when($request['service_type'] != 'all', function ($query) use ($request) {
                return $query->ofRepeatBookingStatus($request['service_type'] === 'repeat' ? 1 : ($request['service_type'] === 'regular' ? 0 : null));
            })
            ->latest()
            ->paginate($request['limit'], ['*'], 'offset', $request['offset'])
            ->withPath('');

        foreach ($bookings as $booking) {
            if ($booking->repeat->isNotEmpty()) {
                $sortedRepeats = $booking->repeat->sortBy(function ($repeat) {
                    $parts = explode('-', $repeat->readable_id);
                    $suffix = end($parts);
                    return $this->readableIdToNumber($suffix);
                });
                $booking->repeats = $sortedRepeats->values()->toArray();
            }
            $booking->is_customize_booking = $booking->customizeBooking ? 1 : 0;

            unset($booking->repeat);
            unset($booking->customizeBooking);
        }

        return response()->json(response_formatter(DEFAULT_200, $bookings), 200);
    }

    /**
     * Customer's recent (completed) bookings that can be rebooked.
     * @param Request $request
     * @return JsonResponse
     */
    public function rebookingList(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric|min:1|max:200',
            'offset' => 'required|numeric|min:1|max:100000',
            'string' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        if (!(int)business_config('rebooking_status', 'booking_setup')?->live_values) {
            return response()->json(response_formatter(REBOOKING_DISABLED_403), 403);
        }

        return $this->buildRebookingList($request);
    }

    /**
     * Customer's recent (completed) bookings with a specific provider that can
     * be rebooked. Surfaced on the provider-details screen.
     * @param Request $request
     * @return JsonResponse
     */
    public function rebookingListByProvider(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|numeric|min:1|max:200',
            'offset' => 'required|numeric|min:1|max:100000',
            'provider_id' => 'required|uuid',
            'string' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        if (!(int)business_config('rebooking_status', 'booking_setup')?->live_values) {
            return response()->json(response_formatter(REBOOKING_DISABLED_403), 403);
        }

        return $this->buildRebookingList($request, $request['provider_id']);
    }

    /**
     * Shared builder for both rebooking lists: completed bookings of the logged-in
     * customer, each tagged with the same eligibility the order-details rebooking
     * flow uses (resolveRebookingEligibility), so all three surfaces agree.
     * @param Request $request
     * @param string|null $providerId optional provider filter (provider-details list)
     * @return JsonResponse
     */
    private function buildRebookingList(Request $request, ?string $providerId = null): JsonResponse
    {
        $bookings = $this->booking
            ->with([
                'detail',
                'provider:id,company_name,company_phone,logo,is_verified',
            ])
            ->where('customer_id', $request->user()->id)
            ->ofBookingStatus('completed')
            ->when($providerId, function ($query) use ($providerId) {
                $query->where('provider_id', $providerId);
            })
            ->search(base64_decode($request['string'] ?? ''), ['readable_id'])
            ->latest()
            ->paginate($request['limit'], ['*'], 'offset', $request['offset'])
            ->withPath('');

        $zoneId = $request->header('zoneid');

        foreach ($bookings as $booking) {
            $eligibility = $this->resolveRebookingEligibility($booking, $zoneId);

            $booking->is_provider_available = $eligibility['is_provider_available'];
            $booking->is_service_info_unchanged = $eligibility['is_service_info_unchanged'];
            $booking->is_rebooking_available = $eligibility['is_rebooking_available'];
            $booking->services = $eligibility['services'];

            $booking->provider?->makeHidden(['is_eligible_for_badge', 'cover_image_full_path']);

            unset($booking->detail);
        }

        return response()->json(response_formatter(DEFAULT_200, $bookings), 200);
    }

    /**
     * Show the specified resource.
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $this->booking
            ->where(['customer_id' => $request->user()->id])
            ->with([
                'detail.service',
                'schedule_histories.user',
                'status_histories.user',
                'customer',
                'provider',
                'serviceman.user',
                'booking_partial_payments',
                'repeat.scheduleHistories',
                'repeat.repeatHistories'
            ])
            ->where(['id' => $id])
            ->first();

        if (isset($booking)) {
            $booking->workflow = app(\Modules\BookingModule\Services\BookingWorkflowService::class)->details($booking);
            $booking->dispatch_tracking = rescue(fn () => app(TrackingService::class)->customerTracking($booking), null, report: true);
            $this->appendOfflineTransferDetails($booking);

            $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

            unset($booking->booking_offline_payments, $booking->service_address_location);

            if (isset($booking->provider)){
                $booking->provider->chatEligibility = chatEligibility($booking->provider_id);
            }

            if ($booking->repeat->isNotEmpty()) {
                $repeatHistoryCollection = $booking->repeat->flatMap(function ($repeat) {
                    return $repeat->repeatHistories->map(function ($history) {
                        $history->log_details = json_decode($history->log_details);
                        return $history;
                    });
                });

                $booking['repeatHistory'] = $repeatHistoryCollection->toArray();
                $sortedRepeats = $booking->repeat->sortBy(function ($repeat) {
                    $parts = explode('-', $repeat->readable_id);
                    $suffix = end($parts);
                    $repeat['service_address'] = json_decode($repeat->service_address_location);
                    unset($repeat->service_address_location);
                    return $this->readableIdToNumber($suffix);
                });
                $booking['repeats'] = $sortedRepeats->values()->toArray();

                $nextService = collect($booking['repeats'])->firstWhere('booking_status', 'accepted');
                if (!$nextService) {
                    $nextService = collect($booking['repeats'])->firstWhere('booking_status', 'pending');
                }

                $booking['nextService'] = $nextService;
                $booking['time'] = max(collect($booking['repeats'])->pluck('service_schedule')->flatten()->toArray());
                $booking['startDate'] = min(collect($booking['repeats'])->pluck('service_schedule')->flatten()->toArray());
                $booking['endDate'] = max(collect($booking['repeats'])->pluck('service_schedule')->flatten()->toArray());
                $booking['totalCount'] = count($booking['repeats']);
                $booking['bookingType'] = $booking['repeats'][0]['booking_type'];

                if ($booking['bookingType'] == 'weekly') {
                    $booking['weekNames'] = collect($booking['repeats'])
                        ->pluck('service_schedule')
                        ->map(function ($schedule) {
                            return \Carbon\Carbon::parse($schedule)->format('l');
                        })
                        ->unique()
                        ->sort()
                        ->values()
                        ->toArray();
                }

                $booking['completedCount'] = collect($booking['repeats'])->where('booking_status', 'completed')->count();
                $booking['canceledCount'] = collect($booking['repeats'])->where('booking_status', 'canceled')->count();

                unset($booking->repeat);
                $booking['repeats'] = array_map(function($repeat) {
                    if (isset($repeat['repeat_histories'])) {
                        unset($repeat['repeat_histories']);
                    }
                    return $repeat;
                }, $booking['repeats']);
            }

            $booking->is_customize_booking = $booking->customizeBooking ? 1 : 0;
            unset($booking->customizeBooking);

            $this->resolveProBenefit($booking);

            $execution = app(\Modules\BookingModule\Services\BookingExecutionService::class);
            if ($execution->enabled()) {
                $booking->execution_evidence = [
                    'before' => $execution->evidence($booking)->where('type', 'before')->get(),
                    'after' => $execution->evidence($booking)->where('type', 'after')->get(),
                ];
            }

            return response()->json(response_formatter(DEFAULT_200, $booking), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 204);
    }

    /**
     * Show the specified resource.
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function singleDetails(Request $request, string $id): JsonResponse
    {
        $booking = $this->bookingRepeat->with([
            'detail.service', 'scheduleHistories.user', 'statusHistories.user', 'booking.customer', 'provider', 'serviceman.user'
        ])->whereHas('booking', fn ($query) => $query->where('customer_id', $request->user()->id))->where(['id' => $id])->first();

        if (isset($booking)) {
            $booking->workflow = app(\Modules\BookingModule\Services\BookingWorkflowService::class)->details($booking);
            $booking->dispatch_tracking = rescue(fn () => app(TrackingService::class)->customerTracking($booking->booking, $booking), null, report: true);
            $booking->booking->service_address = $booking->booking->service_address_location != null ? json_decode($booking->booking->service_address_location) : $booking->booking->service_address;
            $execution = app(\Modules\BookingModule\Services\BookingExecutionService::class);
            if ($execution->enabled()) {
                $booking->execution_evidence = [
                    'before' => $execution->evidence($booking)->where('type', 'before')->get(),
                    'after' => $execution->evidence($booking)->where('type', 'after')->get(),
                ];
            }

            if (isset($booking->provider)){
                $booking->provider->chatEligibility = chatEligibility($booking->provider_id);
            }
            return response()->json(response_formatter(DEFAULT_200, $booking), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 204);
    }
    /**
     * Show the specified resource.
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function track(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $booking = $this->booking
            ->with(['detail.service', 'schedule_histories.user', 'status_histories.user', 'customer', 'provider', 'zone', 'serviceman.user'])
            ->where(['readable_id' => $id])
            ->whereHas('service_address', fn($query) => $query->where('contact_person_number', $request['phone']))
            ->first();

        if (isset($booking)) {
            if (auth('api')->user() || $request->header('X-Guest-Payment-Token')) {
                $principal = app(\Modules\PaymentModule\Services\PaymentPrincipal::class)->resolve($request);
                if ((string) $booking->customer_id === (string) $principal['id'] && (bool) $booking->is_guest === (bool) $principal['is_guest']) {
                    $this->appendOfflineTransferDetails($booking);
                }
            }

            $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;
            unset($booking->service_address_location);
            return response()->json(response_formatter(DEFAULT_200, $booking), 200);
        }

        return response()->json(response_formatter(DEFAULT_404, $booking), 404);
    }

    /**
     * Show the specified resource.
     * @param Request $request
     * @param string $booking_id
     * @return JsonResponse
     */
    public function statusUpdate(Request $request, string $booking_id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_status' => 'required|in:canceled',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $booking = $this->booking->where('id', $booking_id)->where('customer_id', $request->user()->id)->first();

        if (isset($booking)) {

            if($booking->booking_status == 'accepted' && $request['booking_status'] == 'canceled'){
                return response()->json(response_formatter(BOOKING_ALREADY_ACCEPTED), 200);
            }

            if($booking->booking_status == 'ongoing' && $request['booking_status'] == 'canceled'){
                return response()->json(response_formatter(BOOKING_ALREADY_ONGOING), 200);
            }

            if($booking->booking_status == 'completed' && $request['booking_status'] == 'canceled'){
                return response()->json(response_formatter(BOOKING_ALREADY_COMPLETED), 200);
            }

            $booking->booking_status = $request['booking_status'];

            $bookingStatusHistory = $this->bookingStatusHistory;
            $bookingStatusHistory->booking_id = $booking_id;
            $bookingStatusHistory->changed_by = $request->user()->id;
            $bookingStatusHistory->booking_status = $request['booking_status'];

            DB::transaction(function () use ($bookingStatusHistory, $booking, $request) {
                $booking->save();
                $bookingStatusHistory->save();

                if ($request['booking_status'] == 'canceled' && $booking->repeat->isNotEmpty()){
                    foreach ($booking->repeat as $repeat) {
                        $repeat->booking_status = 'canceled';
                        $repeat->setAttribute('skipNotification', false);
                        unset($repeat->skipNotification);
                        $repeat->save();

                        $repeatBookingStatusHistory = new $this->bookingStatusHistory;
                        $repeatBookingStatusHistory->booking_id = 0;
                        $repeatBookingStatusHistory->booking_repeat_id = $repeat->id;
                        $repeatBookingStatusHistory->changed_by = $request->user()->id;
                        $repeatBookingStatusHistory->booking_status = 'canceled';
                        $repeatBookingStatusHistory->save();
                    }
                }
            });

            return response()->json(response_formatter(BOOKING_STATUS_UPDATE_SUCCESS_200, $booking), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 204);
    }

    /**
     * @param Request $request
     * @param string $repeatId
     * @return JsonResponse
     */
    public function singleBookingCancel(Request $request, string $repeatId): JsonResponse
    {
        $customerId = $request->user()->id;
        $repeat = $this->bookingRepeat->where('id', $repeatId)->first();
        $bookingId = $repeat->booking_id;
        $booking = $this->booking->where('id', $bookingId)->where('customer_id', $customerId)->first();

        if ($booking && $repeat)
        {
            $statusCheck = $repeat->booking_status == 'canceled';
            if ($statusCheck){
                return response()->json(response_formatter(BOOKING_ALREADY_CANCELED_200), 200);
            }

            DB::transaction(function () use ($repeat) {
                $repeat->booking_status = 'canceled';
                $repeat->save();
            });

            return response()->json(response_formatter(DEFAULT_200), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 204);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function storeOfflinePaymentData(Request $request): JsonResponse
    {
        try {
            $content = app(\Modules\BookingModule\Services\OfflineTransferService::class)->submit($request);
            return response()->json(response_formatter(OFFLINE_PAYMENT_SUCCESS_200, $content), 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            // Keep the established text-client validation envelope for multipart too.
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($error->validator)), 400);
        }
    }

    public function offlineReceiptAccess(Request $request): JsonResponse
    {
        $url = app(\Modules\BookingModule\Services\OfflineTransferService::class)->customerAccess($request);
        return response()->json(response_formatter(DEFAULT_200, ['url' => $url]));
    }

    public function offlineReceiptDownload(Request $request)
    {
        abort_unless($request->hasValidSignature(), 403);
        $service = app(\Modules\BookingModule\Services\OfflineTransferService::class);
        $booking = Booking::findOrFail($request->query('booking_id'));
        return $service->download($service->receiptForBooking($booking, $request->query('receipt_id')));
    }

    public function switchPaymentMethod(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required',
            'payment_method' => 'required|in:cash_after_service,wallet_payment',
            'is_partial' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $principal = app(\Modules\PaymentModule\Services\PaymentPrincipal::class)->resolve($request);
        $booking = $this->booking->where('customer_id', $principal['id'])->find($request->booking_id);
        if (!$booking) {
            return response()->json(response_formatter(DEFAULT_204), 204);
        }

        if ($request->is_partial && $request->payment_method == 'wallet_payment') {
            return response()->json(response_formatter(DEFAULT_400, null, 'Partial payment is not allowed with full wallet payment.'), 400);
        }

        if ($request->is_partial) {
            abort_if($principal['is_guest'], 403);
            $walletBalance = auth('api')->user()->wallet_balance;
            if ($walletBalance <= 0 || $walletBalance >= $booking->total_booking_amount) {
                return response()->json(response_formatter(DEFAULT_400, null, 'Invalid partial payment data.'), 400);
            }
        }

        DB::transaction(function () use ($request, $booking, $principal) {
            $booking = $this->booking->where('customer_id', $principal['id'])->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_if($booking->is_paid || in_array($booking->booking_status, ['canceled', 'cancelled', 'refunded'], true), 409);
            $reservedWallet = $booking->payment_method === 'offline_payment'
                && app(\Modules\BookingModule\Services\OfflineTransferService::class)->recordedWalletAmount($booking) > 0
                && $booking->booking_partial_payments()->where('paid_with', 'wallet')->exists();
            abort_if($reservedWallet && $request->payment_method === 'wallet_payment', 409, 'checkout_wallet_already_reserved');
            if ($reservedWallet) {
                $booking->booking_partial_payments()->where('paid_with', '!=', 'wallet')->delete();
            } else {
                $booking->booking_partial_payments()->delete();
            }

            if ($request->payment_method == 'cash_after_service') {
                $booking->update(['payment_method' => 'cash_after_service', 'transaction_id' => 'cash-payment', 'is_verified' => 1]);

                if ($request->is_partial && !$reservedWallet) {
                    $walletBalance = auth('api')->user()->wallet_balance;
                    BookingPartialPayment::create([
                        'booking_id' => $booking->id,
                        'paid_with' => 'wallet',
                        'paid_amount' => $walletBalance,
                        'due_amount' => $booking->total_booking_amount - $walletBalance,
                    ]);
                    placeBookingTransactionForPartialCas($booking);
                }
            } else {
                $booking->update(['payment_method' => 'wallet_payment', 'transaction_id' => 'wallet-payment']);
                placeBookingTransactionForWalletPayment($booking);
            }
        });

        return response()->json(response_formatter(PAYMENT_METHOD_UPDATE_200), 200);
    }

    public function digitalPaymentBookingResponse(Request $request): JsonResponse|array
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $payment_info = PaymentRequest::where('transaction_id', $request->transaction_id)->first();

        if (!$payment_info) {
            return response()->json(response_formatter(DEFAULT_204), 204);
        }

        app(\Modules\PaymentModule\Services\PaymentPrincipal::class)->authorizePayment($request, $payment_info);
        if (in_array($payment_info->payment_method, ['tap', 'tabby', 'tamara'], true)) {
            if (!$payment_info->verified_at || !$payment_info->finalized_at) {
                return response()->json(response_formatter(DEFAULT_204), 204);
            }
            $result = $payment_info->finalization_result ?? [];
            if (!empty($result['newUserPhone'])) $request->attributes->set('nirab_verified_payment_phone', $result['newUserPhone']);
            return response()->json(response_formatter(DEFAULT_200, [
                'booking_id' => $payment_info->finance_booking_id,
                'booking_ids' => (array) ($result['booking_id'] ?? []),
                'bookings' => $result['bookings'] ?? [],
                'readable_id' => $result['readable_id'] ?? null,
                'booking_repeat_id' => $payment_info->finance_booking_repeat_id,
                'new_user_phone' => $result['newUserPhone'] ?? null,
                'login_token' => $result['loginToken'] ?? null,
                'payment_state' => $payment_info->gateway_state,
            ]));
        }

        $additional_data = json_decode($payment_info->additional_data, true);

        $booking_repeat_id = $additional_data['booking_repeat_id'] ?? null;
        $register_new_customer = $additional_data['register_new_customer'] ?? 0;
        $new_user_phone = $register_new_customer == 1 ? $additional_data['phone'] : null;

        $booking = null;
        $booking_id = null;
        if (isset($payment_info) && $payment_info->attribute_id != null) {
            $booking = Booking::where('readable_id', $payment_info->attribute_id)->first();
            $booking_id = $booking ? $booking->id : null;
        }

        $loginToken = null;
        if ($register_new_customer == 1 && $new_user_phone != null){
            $user = new User();
            $user->first_name = $additional_data['first_name'];
            $user->last_name = '';
            $user->phone = $additional_data['phone'];
            $user->password = bcrypt($additional_data['password']);
            $user->user_type = 'customer';
            $user->is_active = 1;
            $user->save();

            if ($user && $booking) {
                $booking->customer_id = $user->id;
                $booking->is_guest = 0;
                $booking->save();
            }

            $loginToken = $user->createToken('CUSTOMER_PANEL_ACCESS')->accessToken;
        }

        $response =  [
            'booking_id' => $booking_id,
            'booking_repeat_id' => $booking_repeat_id,
            'new_user_phone' => $new_user_phone,
            'login_token' => $loginToken,
        ];

        return response()->json(response_formatter(DEFAULT_200, $response), 200);

    }

}
