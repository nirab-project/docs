<?php

namespace Modules\BookingModule\Entities;

use App\Models\Scopes\BranchManagerScope;
use App\Services\NirabBookingFinancialService;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Mail;
use Modules\BidModule\Entities\Post;
use Modules\BidModule\Entities\PostBid;
use Modules\BidModule\Entities\ServiceFieldValue;
use Modules\BookingModule\Http\Traits\BookingTrait;
use Modules\BookingModule\Http\Traits\BookingScopes;
use Modules\BusinessSettingsModule\Emails\CashInHandOverflowMail;
use Modules\BusinessSettingsModule\Emails\SubscriptionToCommissionMail;
use Modules\BusinessSettingsModule\Entities\PackageSubscriber;
use Modules\PromotionManagement\Entities\Coupon;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ReviewModule\Entities\Review;
use Modules\UserManagement\Entities\Serviceman;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAddress;
use Modules\ZoneManagement\Entities\Zone;

class Booking extends Model
{
    use \Modules\OdooIntegration\Traits\AtomicAccountingSave;
    public bool $nirabOfflineReviewAccepted = false;
    public bool $nirabCompletionOverride = false;
    public bool $nirabAccountingApproval = false;

    use HasFactory, HasUuid, BookingTrait, BookingScopes;

    protected $hidden = ['category_id', 'sub_category_id'];

    protected $casts = [
        'is_warranty' => 'boolean',
        'warranty_approved_at' => 'datetime',
        'is_warranty' => 'boolean',
        'warranty_approved_at' => 'datetime',
        'arrival_location' => 'array',
        'finish_location' => 'array',
        'arrived_at' => 'datetime',
        'work_started_at' => 'datetime',
        'work_finished_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'completion_override' => 'array',
        'accounting_ready_at' => 'datetime',
        'actual_duration_seconds' => 'integer',
        'readable_id' => 'integer',
        'is_paid' => 'integer',
        'is_verified' => 'integer',
        'total_booking_amount' => 'float',
        'total_tax_amount' => 'float',
        'total_discount_amount' => 'float',
        'total_campaign_discount_amount' => 'float',
        'total_coupon_discount_amount' => 'float',
        'is_checked' => 'integer',
        'additional_charge' => 'float',
        'additional_tax_amount' => 'float',
        'additional_discount_amount' => 'float',
        'additional_campaign_discount_amount' => 'float',
        'evidence_photos' => 'array',
        'extra_fee' => 'float',
        'total_referral_discount_amount' => 'float',
        'total_pro_discount_amount' => 'float',
        'total_pro_service_fee_discount_amount' => 'float',
        'required_technicians' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'assignment_attempts' => 'integer',
        'assignment_due_at' => 'datetime',
        'estimated_arrival_at' => 'datetime',
        'late_alerted_at' => 'datetime',
        'service_schedule' => 'datetime',
        'pricing_snapshot' => 'array',
        'inspection_deduction_amount' => 'float',
    ];

    protected $fillable = [
        'id',
        'readable_id',
        'customer_id',
        'provider_id',
        'zone_id',
        'booking_status',
        'is_paid',
        'payment_method',
        'transaction_id',
        'total_booking_amount',
        'total_tax_amount',
        'total_discount_amount',
        'service_schedule',
        'service_address_id',
        'created_at',
        'updated_at',
        'serviceman_id',
        'total_campaign_discount_amount',
        'total_coupon_discount_amount',
        'coupon_code',
        'is_checked',
        'additional_charge',
        'additional_tax_amount',
        'additional_discount_amount',
        'additional_campaign_discount_amount',
        'evidence_photos',
        'booking_otp',
        'is_verified',
        'service_address_location',
        'service_location',
        'execution_type',
        'required_technicians',
        'estimated_duration_minutes',
        'assignment_due_at',
        'assignment_status',
        'assignment_attempts',
        'estimated_arrival_at',
        'late_alerted_at',
        'quotation_id',
        'pricing_snapshot',
        'inspection_deduction_amount',
    ];

    protected $appends = ['evidence_photos_full_path', 'branch_id', 'branch', 'payment_status', 'execution_requires_otp'];


    public function service_address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'service_address_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }





    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_id');
    }

    public function branch(): BelongsTo
    {
        return $this->provider();
    }

    public function getBranchIdAttribute(): ?string
    {
        return $this->provider_id;
    }

    public function getBranchAttribute()
    {
        return $this->relationLoaded('provider') ? $this->getRelation('provider') : $this->provider()->first();
    }

    public function getExecutionRequiresOtpAttribute(): bool
    {
        return app(\Modules\BookingModule\Services\BookingExecutionService::class)->enabled() && (bool) config('nirab_execution.require_customer_otp');
    }

    public function getPaymentStatusAttribute(): string
    {
        return (int) $this->is_paid === 1 ? 'paid' : 'unpaid';
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function serviceman(): BelongsTo
    {
        return $this->belongsTo(Serviceman::class, 'serviceman_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(BookingAssignmentHistory::class);
    }

    public function detail(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }

    public function repeatDetail(): HasMany
    {
        return $this->hasMany(BookingDetail::class);
    }
    public function repeat(): HasMany
    {
        return $this->hasMany(BookingRepeat::class);
    }

    public function booking_partial_payments(): HasMany
    {
        return $this->hasMany(BookingPartialPayment::class)->latest();
    }

    public function getPaidWithMethodsAttribute(): array
    {
        $partials = $this->relationLoaded('booking_partial_payments')
            ? $this->booking_partial_payments
            : $this->booking_partial_payments()->get();

        if ($partials->isEmpty()) {
            return array_values(array_filter([$this->payment_method]));
        }

        $methods = $partials
            ->sortBy('created_at')
            ->map(fn ($partial) => $partial->paid_with === 'digital' ? $this->payment_method : $partial->paid_with)
            ->filter()
            ->unique()
            ->values();

        if ($this->payment_method && $this->payment_method !== 'wallet_payment' && !$methods->contains($this->payment_method)) {
            $methods->push($this->payment_method);
        }

        return $methods->values()->all();
    }

    public function booking_details_amounts(): hasOne
    {
        return $this->hasOne(BookingDetailsAmount::class);
    }

    public function bookingDeniedNote(): hasOne
    {
        return $this->hasOne(BookingAdditionalInformation::class, 'booking_id')->where('key', 'booking_deny_note');
    }

    public function details_amounts(): hasMany
    {
        return $this->hasMany(BookingDetailsAmount::class);
    }

    public function schedule_histories(): HasMany
    {
        return $this->hasMany(BookingScheduleHistory::class);
    }
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function status_histories(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class);
    }

    public function booking_offline_payments(): HasMany
    {
        return $this->hasMany(BookingOfflinePayment::class, 'booking_id')->orderByDesc('created_at')->orderByDesc('id');
    }

    public function ignores(): HasMany
    {
        return $this->hasMany(BookingIgnore::class, 'booking_id');
    }

    public function customizeBooking(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'id', 'booking_id');
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(PostBid::class, 'quotation_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ServiceFieldValue::class);
    }

    public function getEvidencePhotosFullPathAttribute()
    {
        $evidenceImages = $this->evidence_photos ?? [];
        $defaultImagePath = asset('public/assets/admin-module/img/media/user.png');
        if (empty($evidenceImages)) {
            if (request()->is('api/*')) {
                $defaultImagePath = null;
            }
            return $defaultImagePath ? [$defaultImagePath] : [];
        }

        $path = 'booking/evidence/';

        return getIdentityImageFullPath(identityImages: $evidenceImages, path: $path, defaultPath: $defaultImagePath);
    }

    public static function boot()
    {
        parent::boot();
        self::updating(function ($model) {
            if ($model->getRawOriginal('payment_method') !== 'offline_payment') return;
            if ($model->isDirty('is_paid') && !$model->nirabOfflineReviewAccepted) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment_status' => [__('offline_transfer.review_required')]]);
            }
            if ($model->isDirty('payment_method') && $model->booking_offline_payments()->whereIn('payment_status', ['pending', 'approved'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['payment_method' => [__('offline_transfer.review_required')]]);
            }
        });
        self::updating(fn ($model) => app(\Modules\BookingModule\Services\DirectBookingReservationGuard::class)->validateMutation($model));

        self::retrieved(function ($model) {
            if (request()->user()?->user_type !== 'customer') $model->makeHidden('booking_otp');
        });

        static::addGlobalScope(new BranchManagerScope());

        self::creating(function ($model) {
            $model->readable_id = app(\Modules\BookingModule\Services\BookingNumberSequence::class)->next();
        });

        self::created(function ($model) {
            $providerId = $model->provider_id;

            if ($providerId && !nirab_branch_mode()) {
                $provider = PackageSubscriber::where('provider_id', $providerId)->first();

                if ($provider) {
                    $firstLog = $provider->package_subscriber_log_id;

                    $bookingType = new SubscriptionBookingType();
                    $bookingType->booking_id = $model->id;
                    $bookingType->type = 'subscription';
                    $bookingType->save();

                    $subscriptionSubscriberBooking = new SubscriptionSubscriberBooking();
                    $subscriptionSubscriberBooking->provider_id = $providerId;
                    $subscriptionSubscriberBooking->booking_id = $model->id;

                    if ($firstLog) {
                        $subscriptionSubscriberBooking->package_subscriber_log_id = $firstLog;
                    }

                    $subscriptionSubscriberBooking->save();
                }
            }

        });


        self::updating(function ($model) {
            $booking_notification_status = business_config('booking', 'notification_settings')->live_values;
            $permission = isNotificationActive(null, 'booking', 'notification', 'user');
            $providerPermission = isNotificationActive(null, 'booking', 'notification', 'provider');
            $servicemanPermission = isNotificationActive(null, 'booking', 'notification', 'serviceman');

            if ($model->isDirty('booking_status')) {
                $key = null;
                if ($model->booking_status == 'pending') {
                    if ($permission) {
                        $notifications[] = [
                            'key' => 'booking_place',
                            'settings_type' => 'customer_notification'
                        ];
                    }
                } elseif ($model->booking_status == 'ongoing') {
                    if ($permission) {
                        $notifications[] = [
                            'key' => 'booking_ongoing',
                            'settings_type' => 'customer_notification'
                        ];
                    }
                    if ($providerPermission){
                            $notifications[] = [
                                'key' => 'ongoing_booking',
                                'settings_type' => 'provider_notification'
                            ];
                    }
                    if ($servicemanPermission) {
                        $notifications[] = [
                            'key' => 'ongoing_booking',
                            'settings_type' => 'serviceman_notification'
                        ];
                    }
            } elseif ($model->booking_status == 'accepted') {
                if ($permission) {
                    $notifications[] = [
                        'key' => 'booking_accepted',
                        'settings_type' => 'customer_notification'
                    ];
                }
                if ($providerPermission && $model->is_repeated == 0) {
                    $notifications[] = [
                        'key' => 'booking_accepted',
                        'settings_type' => 'provider_notification'
                    ];
                }
            } elseif ($model->booking_status == 'completed' && $model->is_repeated == 0) {
                if ($permission) {
                    $notifications[] = [
                        'key' => 'booking_complete',
                        'settings_type' => 'customer_notification'
                    ];
                }
                if ($providerPermission) {
                    $notifications[] = [
                        'key' => 'booking_complete',
                        'settings_type' => 'provider_notification'
                    ];
                }
                if ($servicemanPermission) {
                    $notifications[] = [
                        'key' => 'booking_complete',
                        'settings_type' => 'serviceman_notification'
                    ];
                }

                $provider = $model->provider;

                if ($provider && !nirab_branch_mode()) {
                    $model->update_admin_commission($model, $model->total_booking_amount, $model->provider_id);
                }


                if (!$model->is_guest && $model?->customer && !$model->is_warranty && $model->payment_method !== 'prepaid_visit') {
                    $model->referral_earning_calculation($model->customer_id, $model->zone_id);

                    $model->loyaltyPointCalculation($model->customer_id, $model->total_booking_amount);

                    if ($model->total_referral_discount_amount > 0){
                        referralEarningTransactionAfterBookingCompleteFirst($model->customer, $model->total_referral_discount_amount, $model->id);
                    }
                }

                //================ Transactions for Booking ================

                if ($model?->provider && !nirab_branch_mode()) {
                    if ($model->booking_partial_payments->isNotEmpty()) {
                        if ($model['payment_method'] == 'cash_after_service') {
                            $booking_partial_payment = new BookingPartialPayment;
                            $booking_partial_payment->booking_id = $model->id;
                            $booking_partial_payment->paid_with = 'cash_after_service';
                            $booking_partial_payment->paid_amount = $model->booking_partial_payments->first()?->due_amount;
                            $booking_partial_payment->due_amount = 0;
                            $booking_partial_payment->save();

                            completeBookingTransactionForPartialCas($model);
                        } elseif ($model['payment_method'] != 'wallet_payment') {
                            completeBookingTransactionForPartialDigital($model);
                        }

                    } elseif ($model->payment_method == 'cash_after_service') {
                        completeBookingTransactionForCashAfterService($model);
                    } else {
                        if ($model->additional_charge == 0) {
                            completeBookingTransactionForDigitalPayment($model);
                        }

                        if ($model->additional_charge > 0) {
                            completeBookingTransactionForDigitalPaymentAndExtraService($model);
                        }
                    }

                    $limit_status = provider_warning_amount_calculate($provider->owner->account->account_payable, $provider->owner->account->account_receivable);

                    if ($limit_status == '100_percent' && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values) {
                        $provider->is_suspended = 1;
                        $provider->save();

                        $notification = isNotificationActive($provider?->id, 'transaction', 'notification', 'provider');
                        $title = get_push_notification_message('provider_suspend', 'provider_notification', $provider?->owner?->current_language_key);
                        if ($provider?->owner?->fcm_token && $title && $notification) {
                            device_notification($provider?->owner?->fcm_token, $title, null, null, $model->id, 'suspend', null, $provider->id);
                        }

                        $emailStatus = business_config('email_config_status', 'email_config')->live_values;

                        if ($emailStatus){
                            try {
                                Mail::to($provider?->owner?->email)->send(new CashInHandOverflowMail($provider));
                            } catch (\Exception $exception) {
                                info('Operation failed', ['operation' => __METHOD__, 'error_type' => get_class($exception)]);
                            }
                        }

                    }
                }

            } elseif ($model->booking_status == 'canceled') {
                if ($permission) {
                    $notifications[] = [
                        'key' => 'booking_cancel',
                        'settings_type' => 'customer_notification'
                    ];
                }
                if ($providerPermission) {
                    $notifications[] = [
                        'key' => 'booking_cancel',
                        'settings_type' => 'provider_notification'
                    ];
                }
                if ($servicemanPermission) {
                    $notifications[] = [
                        'key' => 'booking_cancel',
                        'settings_type' => 'serviceman_notification'
                    ];
                }

                if ($model?->customer && !$model->is_warranty && $model->payment_method !== 'prepaid_visit') {
                    refundTransactionForCanceledBooking($model);
                }

            } elseif ($model->booking_status == 'refund_request') {
                if ($permission) {
                    $notifications[] = [
                        [
                            'key' => 'refund',
                            'settings_type' => 'customer_notification'
                        ]
                    ];
                }
            }


            if (isset($booking_notification_status) && $booking_notification_status['push_notification_booking']) {
                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $model?->customer;
                        $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        $permission = isNotificationActive(null, 'booking', 'notification', 'user');
                        if ($user?->fcm_token && $user?->is_active && $title && $permission) {
                            device_notification($user?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                        }
                    }

                    if ($settingsType == 'provider_notification') {

                        if ((nirab_branch_mode() || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values || $model?->provider?->is_suspended == 0) && $model->booking_status == 'pending') {
                            $provider = $model?->provider?->owner;
                            $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                            $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);

                            if ($provider?->fcm_token && $title && sendDeviceNotificationPermission($model?->provider_id)) {
                                device_notification($provider?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                            }
                        } else {
                            $provider = $model?->provider?->owner;
                            $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                            $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);

                            if ($provider?->fcm_token && $title  && sendDeviceNotificationPermission($model?->provider_id)) {
                                device_notification($provider?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                            }
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $model?->serviceman?->user;
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $model->id, 'booking');
                        }
                    }
                }
            }
        }
        });

        self::updated(function ($model) {
            if ($model->wasChanged('booking_status') && $model->booking_status === 'completed') {
                app(NirabBookingFinancialService::class)->complete($model);
            }

            if ($model->wasChanged('is_paid') && (int) $model->is_paid === 1) {
                app(NirabBookingFinancialService::class)->paymentStatusChanged($model);
            }

            $status = $model->booking_status;
            $bookingScheduleTimeChange = isNotificationActive(null, 'booking', 'notification', 'user');
            $bookingScheduleTimeChangeProvider = isNotificationActive(null, 'booking', 'notification', 'provider');
            $bookingScheduleTimeChangeServiceman = isNotificationActive(null, 'booking', 'notification', 'serviceman');

            $providerId = $model->provider_id;

            if (!nirab_branch_mode() && $status == 'accepted'){
                $providerId = $model->provider_id;

                $subscriptionSubscriberBooking = new SubscriptionSubscriberBooking();
                $subscriptionSubscriberBooking->provider_id = $providerId;
                $subscriptionSubscriberBooking->booking_id = $model->id;

                if ($providerId) {
                    $provider = PackageSubscriber::where('provider_id', $providerId)->first();

                    if ($provider && !$model->isDirty('provider_id')) {
                        $firstLog = $provider->package_subscriber_log_id;

                        $bookingType = new SubscriptionBookingType();
                        $bookingType->booking_id = $model->id;
                        $bookingType->type = 'subscription';
                        $bookingType->save();

                        $subscriptionSubscriberBooking = new SubscriptionSubscriberBooking();
                        $subscriptionSubscriberBooking->provider_id = $providerId;
                        $subscriptionSubscriberBooking->booking_id = $model->id;

                        if ($firstLog) {
                            $subscriptionSubscriberBooking->package_subscriber_log_id = $firstLog;
                        }

                        $subscriptionSubscriberBooking->save();
                    }
                }
            }

            if (!nirab_branch_mode() && $model->isDirty('provider_id')){
                $provider = PackageSubscriber::where('provider_id', $providerId)->first();
                $firstLog = $provider?->package_subscriber_log_id;
                if ($provider){
                    $firstLog = $provider->package_subscriber_log_id;

                    SubscriptionBookingType::updateOrCreate(
                        [
                            'booking_id' => $model->id,
                        ],
                        [
                            'booking_id' => $model->id,
                            'type' => 'subscription'
                        ]
                    );
                }
                SubscriptionSubscriberBooking::updateOrCreate(
                    [
                        'booking_id' => $model->id,
                    ],
                    [
                        'provider_id'   => $providerId,
                        'booking_id' => $model->id,
                        'package_subscriber_log_id' => $firstLog,
                    ]
                );
            }
            $notifications = [];
            $booking_notification_status = business_config('booking', 'notification_settings')->live_values;

            if ($model->isDirty('serviceman_id') && !$model->is_repeted) {
                if ($bookingScheduleTimeChange) {
                    $notifications[] = [
                        'key' => 'serviceman_assign',
                        'settings_type' => 'customer_notification'
                    ];
                }
                if ($bookingScheduleTimeChangeProvider && !$model->is_repeted) {
                    $notifications[] = [
                        'key' => 'serviceman_assign',
                        'settings_type' => 'provider_notification'
                    ];
                }
                if ($bookingScheduleTimeChangeServiceman && !$model->is_repeted) {
                    $notifications[] = [
                        'key' => 'serviceman_assign',
                        'settings_type' => 'serviceman_notification'
                    ];
                }
            }

            if ($model->isDirty('service_schedule')) {
                if ($bookingScheduleTimeChange) {
                    $notifications[] = [
                        'key' => 'booking_schedule_time_change',
                        'settings_type' => 'customer_notification'
                    ];
                }
                if ($bookingScheduleTimeChangeProvider) {
                    $notifications[] = [
                        'key' => 'booking_schedule_time_change',
                        'settings_type' => 'provider_notification'
                    ];
                }
                if ($bookingScheduleTimeChangeServiceman) {
                    $notifications[] = [
                        'key' => 'booking_schedule_time_change',
                        'settings_type' => 'serviceman_notification'
                    ];
                }
            }

            if (isset($booking_notification_status) && $booking_notification_status['push_notification_booking']) {
                foreach ($notifications ?? [] as $notification) {
                    $key = $notification['key'];
                    $settingsType = $notification['settings_type'];

                    if ($settingsType == 'customer_notification') {
                        $user = $model?->customer;
                        $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                        $title = get_push_notification_message($key, $settingsType, $user?->current_language_key);
                        if ($user?->fcm_token && $title) {
                            device_notification($user?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                        }
                    }

                    if ($settingsType == 'provider_notification') {
                        if ((nirab_branch_mode() || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values || $model?->provider?->is_suspended == 0) && $model->booking_status == 'pending') {
                            $provider = $model?->provider?->owner;
                            $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                            $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                            if ($provider?->fcm_token && $title && sendDeviceNotificationPermission($model?->provider_id)) {
                                device_notification($provider?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                            }
                        } else {
                            $provider = $model?->provider?->owner;
                            $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                            $title = get_push_notification_message($key, $settingsType, $provider?->current_language_key);
                            if ($provider?->fcm_token && $title && sendDeviceNotificationPermission($model?->provider_id)) {
                                device_notification($provider?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                            }
                        }
                    }

                    if ($settingsType == 'serviceman_notification') {
                        $serviceman = $model?->serviceman?->user;
                        $repeatOrRegular = $model?->is_repeated ? 'repeat' : 'regular';
                        $title = get_push_notification_message($key, $settingsType, $serviceman?->current_language_key);
                        if ($serviceman?->fcm_token && $title) {
                            device_notification($serviceman?->fcm_token, $title, null, null, $model->id, 'booking', null, null, null, null, $repeatOrRegular);
                        }
                    }
                }
            }
        });


        self::deleting(function ($model) {

        });

        self::deleted(function ($model) {

        });
    }

    public function coupons() {
        return $this->hasMany(Coupon::class, 'coupon_code', 'coupon_code');
    }

    public function getIsProCouponAttribute(): bool
    {
        return $this->coupon_code
            && Coupon::withoutGlobalScopes()->where('coupon_code', $this->coupon_code)->where('coupon_type', 'pro_member')->exists();
    }
}
