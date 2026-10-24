<?php

namespace Modules\BookingModule\Http\Controllers\Web\Provider;

use Box\Spout\Common\Exception\InvalidArgumentException;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery\Exception;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingDetail;
use Modules\BookingModule\Entities\BookingIgnore;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\BookingModule\Entities\BookingRepeatDetails;
use Modules\BookingModule\Entities\BookingRepeatHistory;
use Modules\BookingModule\Entities\BookingScheduleHistory;
use Illuminate\Http\RedirectResponse;
use Modules\BookingModule\Entities\BookingStatusHistory;
use Modules\BookingModule\Http\Traits\BookingTrait;
use Modules\BusinessSettingsModule\Entities\PackageSubscriber;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Entities\Variation;
use Modules\UserManagement\Entities\Serviceman;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Entities\UserAddress;
use Modules\ZoneManagement\Entities\Zone;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{

    private Booking $booking;
    private BookingDetail $bookingDetail;
    private BookingStatusHistory $bookingStatusHistory;
    private BookingScheduleHistory $bookingScheduleHistory;
    private Zone $zone;
    private Serviceman $serviceman;
    private Provider $provider;
    private User $user;
    private UserAddress $userAddress;

    private BookingIgnore $bookingIgnore;
    private BookingRepeat $bookingRepeat;
    private BookingRepeatDetails $bookingRepeatDetail;
    private BookingRepeatHistory $bookingRepeatHistory;

    use BookingTrait;

    public function __construct(Booking $booking, BookingRepeat $bookingRepeat, BookingRepeatDetails $bookingRepeatDetail, BookingRepeatHistory $bookingRepeatHistory, BookingIgnore $bookingIgnore, BookingStatusHistory $bookingStatusHistory, BookingScheduleHistory $bookingScheduleHistory, Zone $zone, Serviceman $serviceman, Provider $provider, User $user, UserAddress $userAddress, BookingDetail $bookingDetail)
    {
        $this->booking = $booking;
        $this->bookingStatusHistory = $bookingStatusHistory;
        $this->bookingScheduleHistory = $bookingScheduleHistory;
        $this->zone = $zone;
        $this->serviceman = $serviceman;
        $this->provider = $provider;
        $this->user = $user;
        $this->userAddress = $userAddress;
        $this->bookingDetail = $bookingDetail;

        $this->bookingIgnore = $bookingIgnore;
        $this->bookingRepeat = $bookingRepeat;
        $this->bookingRepeatDetail = $bookingRepeatDetail;
        $this->bookingRepeatHistory = $bookingRepeatHistory;


    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function index(Request $request): Renderable
    {
        $request->validate([
            'booking_status' => 'in:' . implode(',', array_column(BOOKING_STATUSES, 'key')) . ',all',
        ]);

        $queryParams = $request->only(['service_ids', 'start_date', 'end_date', 'search']);
        $filterCounter = collect($queryParams)->filter()->count();
        $bookingStatus = $queryParams['booking_status'] = $request->input('booking_status', 'pending');
        $queryParams['booking_type'] = $request->input('booking_type', '');
        $queryParams['service_type'] = $request->input('service_type', '');
        if (empty($queryParams['start_date'])) {
            $queryParams['start_date'] = null;
        }
        if (empty($queryParams['end_date'])) {
            $queryParams['end_date'] = null;
        }

        $maxBookingAmount = business_config('max_booking_amount', 'booking_setup')->live_values;
        $providerId = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;
        $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->first();
        $endDate = optional($packageSubscriber)->package_end_date;
        $canceled = optional($packageSubscriber)->is_canceled;
        $packageEndDate = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        $currentDate = Carbon::now()->subDay();
        $isPackageEnded = $packageEndDate ? $currentDate->diffInDays($packageEndDate, false) : null;
        $scheduleBookingEligibility = nextBookingEligibility($providerId);

        $serviceAtProviderPlace = (int)((business_config('service_at_provider_place', 'provider_config'))->live_values ?? 0);
        $serviceLocations = getProviderSettings(providerId: $providerId, key: 'service_location', type: 'provider_config') ?? ['customer'];

        $bookings = $this->booking->with(['customer'])
            ->search($request['search'], ['readable_id'])
            ->whereDoesntHave('ignores', function ($query) use ($providerId) {
                $query->where('provider_id', $providerId);
            })
            ->when(!in_array($request['booking_status'], ['pending', 'all']), function ($query) use ($providerId, $request, $maxBookingAmount) {
                $query->ofBookingStatus($request['booking_status'])
                    ->where(function ($query) use ($providerId) {
                        $query->where('provider_id', $providerId)
                            ->orWhereHas('repeat', function ($subQuery) use ($providerId) {
                                $subQuery->where('provider_id', $providerId);
                            });
                    })
                    ->when($request['booking_status'] == 'accepted', function ($query) use ($request, $maxBookingAmount) {
                        $query->providerAcceptedBookings(app(\App\Services\OperationalAccess::class)->provider($request->user())->id, $maxBookingAmount);
                    });
            })
            ->when($request['booking_status'] == 'pending', function ($query) use ($packageSubscriber, $canceled, $scheduleBookingEligibility, $isPackageEnded, $request, $maxBookingAmount, $serviceAtProviderPlace, $serviceLocations) {
                if (nirab_branch_mode()) {
                    $query->providerPendingBookings(app(\App\Services\OperationalAccess::class)->provider($request->user()), $maxBookingAmount)
                        ->when($serviceAtProviderPlace == 1, function ($query) use ($serviceLocations) {
                            $query->whereIn('service_location', $serviceLocations);
                        });

                    return;
                }

                if ($packageSubscriber) {
                    if ($isPackageEnded > 0 && $scheduleBookingEligibility && !$canceled) {
                        $query->providerPendingBookings(app(\App\Services\OperationalAccess::class)->provider($request->user()), $maxBookingAmount)
                            ->when($serviceAtProviderPlace == 1, function ($query) use ($serviceLocations) {
                                $query->whereIn('service_location', $serviceLocations);
                            });
                    }else{
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    $query->providerPendingBookings(app(\App\Services\OperationalAccess::class)->provider($request->user()), $maxBookingAmount)
                        ->when($serviceAtProviderPlace == 1, function ($query) use ($serviceLocations) {
                            $query->whereIn('service_location', $serviceLocations);
                        });
                }
            })
            ->when($request['service_type'] != 'all', function ($query) use ($request) {
                return $query->ofRepeatBookingStatus($request['service_type'] === 'repeat' ? 1 : ($request['service_type'] === 'regular' ? 0 : null));
            })
            ->filterByDateRange($request['start_date'], $request['end_date'])

            ->filterByServiceIds($request['service_ids'])
            ->latest()->paginate(pagination_limit())->appends($queryParams);

            foreach ($bookings as $booking) {
                if ($booking->repeat->isNotEmpty()) {
                    $sortedRepeats = $booking->repeat->sortBy(function ($repeat) {
                        $parts = explode('-', $repeat->readable_id);
                        $suffix = end($parts);
                        return $this->readableIdToNumber($suffix);
                    });
                    $booking->repeats = $sortedRepeats->values();

                    $nextService = $booking->repeats->firstWhere('booking_status', 'ongoing')
                        ?? $booking->repeats->firstWhere('booking_status', 'accepted')
                        ?? $booking->repeats->firstWhere('booking_status', 'pending');

                    $lastRepeat = $booking->repeats->last();
                    $booking['nextServiceId'] = $nextService ? $nextService->id : null;
                    $booking['nextService'] = $nextService;
                    $booking['lastRepeat'] = $lastRepeat;
                }
            }

        if ($bookingStatus == 'pending') {
            $this->booking
                ->where('provider_id', app(\App\Services\OperationalAccess::class)->provider(auth()->user())->id)
                ->where('zone_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->zone_id)
                ->where('is_checked', 0)
                ->update(['is_checked' => 1]);
        }


        $serviceCatalog = Service::withoutGlobalScopes()->select('id', 'name')->orderBy('name')->get();


        return view('bookingmodule::provider.booking.list', compact('bookings', 'serviceCatalog', 'queryParams', 'filterCounter'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @return void
     */
    public function checkBooking($id): void
    {
        $this->booking->where('id', $id)->where('provider_id', app(\App\Services\OperationalAccess::class)->provider(auth()->user())->id)
            ->where('is_checked', 0)->update(['is_checked' => 1]);
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Application|Factory|View|RedirectResponse
     */
    public function details($id, Request $request)
    {
        $webPage = \App\Services\BranchWorkspaceNavigation::detailPage($request);
        $booking = $this->booking->with(['detail.service' => fn($query) => $query->withTrashed(), 'detail.variation', 'customer', 'provider', 'serviceman', 'status_histories.user'])->findOrFail($id);
        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        if ($booking['provider_id'] != app(\App\Services\OperationalAccess::class)->provider($request->user())->id) {
            Toastr::error(translate(ACCESS_DENIED['key']));
            return redirect(route('provider.booking.list', ['booking_status' => 'accepted']));
        }

        if ($webPage == 'details') {
            $servicemen = $this->serviceman->with(['user'])
                ->withCount('bookings')
                ->whereHas('user', function ($q) {
                    $q->ofStatus(1);
                })
                ->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)
                ->latest()
                ->get();

            $customerAddresses = $this->userAddress->where(['user_id' => $booking?->customer?->id])->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

            $customerAddress = $this->userAddress->find($booking['service_address_id']);
            $zones = Zone::ofStatus(1)->get();

            return view('bookingmodule::provider.booking.details', compact('booking', 'servicemen', 'webPage', 'customerAddresses', 'services', 'customerAddress', 'zones'));

        } elseif ($webPage == 'status') {
            $servicemen = $this->serviceman->with(['user'])
                ->withCount('bookings')
                ->whereHas('user', function ($q) {
                    $q->ofStatus(1);
                })
                ->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)
                ->latest()
                ->get();
            $customerAddresses = $this->userAddress->where(['user_id' => $booking?->customer?->id])->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

            $customerAddress = $this->userAddress->find($booking['service_address_id']);
            $zones = Zone::ofStatus(1)->get();
            return view('bookingmodule::provider.booking.status', compact('booking', 'servicemen','webPage', 'customerAddress', 'zones', 'services'));
        }
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable|RedirectResponse
     * @throws AuthorizationException
     */
    public function repeatDetails($id, Request $request): Renderable|RedirectResponse
    {
        Validator::make($request->all(), [
            'web_page' => 'required|in:details,service_log',
        ]);

        $webPage = $request->has('web_page') ? $request['web_page'] : 'details';

        $booking = $this->booking->with(['repeat.detail.service','repeat.scheduleHistories','repeat.repeatHistories', 'detail.service' => function ($query) {
            $query->withTrashed();
        }, 'detail.variation', 'customer', 'provider',
             'serviceman', 'status_histories.user'])->findOrFail($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        $servicemen = $this->serviceman->with(['user'])
            ->withCount('bookings')
            ->where('provider_id', $booking?->provider_id)
            ->whereHas('user', function ($query) {
                $query->ofStatus(1);
            })
            ->latest()
            ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

        $customerAddress = $this->userAddress->find($booking['service_address_id']);
        $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

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
                return $this->readableIdToNumber($suffix);
            });
            $booking['repeats'] = $sortedRepeats->values()->toArray();

            $nextService = collect($booking['repeats'])->firstWhere('booking_status', 'ongoing');
            if (!$nextService) {
                $nextService = collect($booking['repeats'])->firstWhere('booking_status', 'accepted');
            }
            if (!$nextService) {
                $nextService = collect($booking['repeats'])->firstWhere('booking_status', 'pending');
            }

            $serviceSchedules = collect($booking['repeats'])->pluck('service_schedule')->flatten()->map(function ($schedule) {
                return Carbon::parse($schedule);
            });

            $booking['completeCancel'] = collect($booking['repeats'])->filter(function ($repeat) {
                return in_array($repeat['booking_status'], ['completed', 'canceled']);
            })->values()->toArray();

            $booking['upComing'] = collect($booking['repeats'])->filter(function ($repeat) use ($nextService) {

                if ($repeat['booking_status'] === 'pending') {
                    return in_array($repeat['booking_status'], ['accepted', 'pending']);
                }

                return in_array($repeat['booking_status'], ['accepted', 'pending']) && $repeat['readable_id'] !== $nextService['readable_id'];
            })->values()->toArray();

            $booking['nextService'] = $nextService;
            $booking['time'] = $serviceSchedules->max()->format('g:ia');
            $booking['startDate'] = $serviceSchedules->min()->format('d M, Y');
            $booking['endDate'] = $serviceSchedules->max()->format('d M, Y');
            $booking['totalCount'] = count($booking['repeats']);
            $booking['bookingType'] = $booking['repeats'][0]['booking_type'];

            if ($booking['bookingType'] == 'weekly') {
                $dayOrder = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

                $booking['weekNames'] = collect($booking['repeats'])
                    ->pluck('service_schedule')
                    ->map(function ($schedule) {
                        return \Carbon\Carbon::parse($schedule)->format('l');
                    })
                    ->unique()
                    ->sort(function ($a, $b) use ($dayOrder) {
                        return array_search($a, $dayOrder) - array_search($b, $dayOrder);
                    })
                    ->values()
                    ->toArray();
            }

            $booking['completedCount'] = collect($booking['repeats'])->where('booking_status', 'completed')->count();
            $booking['canceledCount'] = collect($booking['repeats'])->where('booking_status', 'canceled')->count();

            $booking['repeats'] = array_map(function ($repeat) {
                if (isset($repeat['repeat_histories'])) {
                    unset($repeat['repeat_histories']);
                }
                return $repeat;
            }, $booking['repeats']);
        }

        if ($webPage == 'details') {
            return view('bookingmodule::provider.booking.repeat-booking-details', compact('booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones'));

        }elseif ($webPage == 'service_log'){
            return view('bookingmodule::provider.booking.service-log', compact('booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones'));

        }

        Toastr::success(translate(ACCESS_DENIED['key']));
        return back();
    }


    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable|RedirectResponse
     * @throws AuthorizationException
     */
    public function repeatSingleDetails($id, Request $request): Renderable|RedirectResponse
    {
        $webPage = \App\Services\BranchWorkspaceNavigation::detailPage($request);


        $booking = $this->bookingRepeat->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'detail.service', 'scheduleHistories.user', 'statusHistories.user', 'booking.service_address', 'booking.customer', 'booking.provider', 'serviceman.user'])->findOrFail($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking?->booking?->service_address;
        unset($booking->service_address_location);

        $servicemen = $this->serviceman->with(['user'])
            ->withCount('bookings')
            ->where('provider_id', $booking?->provider_id)
            ->whereHas('user', function ($query) {
                $query->ofStatus(1);
            })
            ->latest()
            ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

        $customerAddress = $this->userAddress->find($booking['service_address_id']);
        $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

        if ($webPage == 'details') {
            return view('bookingmodule::provider.booking.rebooking-ongoing', compact('booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones'));

        }elseif ($webPage == 'status') {
            return view('bookingmodule::provider.booking.repeat-status', compact('booking', 'webPage', 'servicemen', 'customerAddress', 'services', 'zones'));
        }
    }

    /**
     * @param Request $request
     * @param string $bookingId
     * @return RedirectResponse
     */
    public function requestAccept(Request $request, string $bookingId): RedirectResponse
    {
        $booking = $this->booking->where('id', $bookingId)->first();

        if (isset($booking)) {

            $provider = app(\App\Services\OperationalAccess::class)->provider($request->user());

            if (!nirab_branch_mode() && $provider?->is_suspended == 1 && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values) {
                Toastr::error(translate(DEFAULT_SUSPEND_200['key']));
                return back();
            }

            if ($booking->booking_status == 'canceled') {
                Toastr::error(translate(BOOKING_ALREADY_CANCELED_200['key']));
                return back();
            }

            $nextBookingEligibility = nextBookingEligibility($provider->id);
            if (!$nextBookingEligibility){
                Toastr::error(translate(BOOKING_LIMIT_END['key']));
                return back();
            }

            $booking->provider_id = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;
            $booking->booking_status = 'accepted';

            $bookingStatusHistory = $this->bookingStatusHistory;
            $bookingStatusHistory->booking_id = $bookingId;
            $bookingStatusHistory->changed_by = $request->user()->id;
            $bookingStatusHistory->booking_status = 'accepted';

            DB::transaction(function () use ($bookingStatusHistory, $booking, $request) {
                $booking->save();
                $bookingStatusHistory->save();

                if ($booking->repeat->isNotEmpty()){
                    foreach ($booking->repeat as $repeat) {
                        $repeat->provider_id = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;
                        $repeat->booking_status = 'accepted';
                        $repeat->save();

                        $repeatBookingStatusHistory = new $this->bookingStatusHistory;
                        $repeatBookingStatusHistory->booking_id = 0;
                        $repeatBookingStatusHistory->booking_repeat_id = $repeat->id;
                        $repeatBookingStatusHistory->changed_by = $request->user()->id;
                        $repeatBookingStatusHistory->booking_status = 'accepted';
                        $repeatBookingStatusHistory->save();
                    }
                }
            });

            Toastr::success(translate(BOOKING_STATUS_UPDATE_SUCCESS_200['key']));
            return back();
        }

        Toastr::error(translate(DEFAULT_204['key']));
        return back();
    }

    /**
     * @param Request $request
     * @param string $bookingId
     * @return RedirectResponse
     */
    public function requestIgnore(Request $request, string $bookingId): RedirectResponse
    {
        $providerId = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;
        $booking = $this->booking->where('id', $bookingId)->first();
        $repeatBookings = $this->bookingRepeat->where('booking_id', $bookingId)->get();

        if (isset($booking)) {

            $ignoreList = $this->bookingIgnore->where('booking_id', $bookingId)->where('provider_id', $providerId)->first();
            if ($ignoreList){
                Toastr::error(translate(BOOKING_ALREADY_IGNORED_200['key']));
                return redirect(route('provider.booking.list', ['booking_status'=>'pending','service_type'=>'all']));
            }

            $bookingIgnore = $this->bookingIgnore;
            $bookingIgnore->booking_id = $bookingId;
            $bookingIgnore->provider_id = $providerId;

            if (!empty($booking->provider_id)){
                $booking->provider_id = null;

                $fcmToken = $booking?->customer?->fcm_token ?? null;
                $repeatOrRegular = $booking?->is_repeated ? 'repeat' : 'regular';
                $languageKey = $booking?->customer?->current_language_key;
                if (!is_null($fcmToken)) {
                    $notification = isNotificationActive(null, 'booking', 'notification', 'user');
                    if ($notification) {
                        device_notification($fcmToken, "Booking ignore by provider", null, null, $booking->id, 'booking_ignored', null, null, null, null, $repeatOrRegular);
                    }
                }
            }


            DB::transaction(function () use ($bookingIgnore, $booking, $repeatBookings) {
                $bookingIgnore->save();
                $booking->save();

                foreach ($repeatBookings as $repeatBooking) {
                    $repeatBooking->provider_id = null;
                    $repeatBooking->save();
                }
            });

            Toastr::success(translate(BOOKING_IGNORE_SUCCESS_200['key']));
            return redirect(route('provider.booking.list', ['booking_status'=>'pending','service_type'=>'all']));
        }

        Toastr::error(translate(DEFAULT_204['key']));
        return back();
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     */
    public function statusUpdate($bookingId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_status' => 'required|in:' . implode(',', array_column(BOOKING_STATUSES, 'key')),
            'otp_field' => ((business_config('booking_otp', 'booking_setup'))->live_values == 1 && $request->booking_status == 'completed') ? 'required' : 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $booking = $this->booking->where('id', $bookingId)->where(function ($query) use ($request) {
            return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->orWhereNull('provider_id');
        })->first();

        $repeatBooking = $this->bookingRepeat->where('id', $bookingId)->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->first();

        $provider = app(\App\Services\OperationalAccess::class)->provider($request->user());

        if (isset($booking)) {

            if ($booking->payment_method == 'offline_payment' && $booking->is_paid == 0 && in_array($request->booking_status, ['ongoing', 'completed'])) {
                if ($booking->booking_offline_payments->isEmpty()) {
                    return response()->json(response_formatter(UPDATE_FAILED_FOR_OFFLINE_PAYMENT_VERIFICATION_200), 200);
                }
                if ($booking->booking_offline_payments->isNotEmpty() && $booking->booking_offline_payments?->first()?->payment_status != 'approved'){
                    return response()->json(response_formatter(UPDATE_FAILED_FOR_OFFLINE_PAYMENT_VERIFICATION_200), 200);
                }
            }
            if ($request->booking_status == 'completed' && (business_config('booking_otp', 'booking_setup'))?->live_values == 1) {

                $otp_number = implode('', $request->otp_field);
                if ($booking->booking_otp != $otp_number) {
                    return response()->json(response_formatter(OTP_VERIFICATION_FAIL_403), 200);
                }
            }

            if ($request->booking_status == 'accepted') {
                if (!nirab_branch_mode() && $provider?->is_suspended == 1 && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values) {
                    return response()->json(DEFAULT_SUSPEND_200, 200);
                }

                $nextBookingEligibility = nextBookingEligibility(app(\App\Services\OperationalAccess::class)->provider($request->user())->id);
                if (!$nextBookingEligibility){
                    return response()->json(BOOKING_LIMIT_END, 200);
                }
            }

            if ($booking->booking_status == 'canceled') {
                return response()->json(response_formatter(BOOKING_ALREADY_CANCELED_200, errors: [['error_code' => 422, 'message' => BOOKING_ALREADY_CANCELED_200['message']]]), 422);
            }

            if ($booking->booking_status == 'ongoing' && $request['booking_status'] == 'canceled') {
                return response()->json(response_formatter(BOOKING_ALREADY_ONGOING, errors: [['error_code' => 422, 'message' => BOOKING_ALREADY_ONGOING['message']]]), 422);
            }

            if ($booking->booking_status == 'completed' && $request['booking_status'] == 'canceled') {
                return response()->json(response_formatter(BOOKING_ALREADY_COMPLETED, errors: [['error_code' => 422, 'message' => BOOKING_ALREADY_COMPLETED['message']]]), 422);
            }

            if ($booking->payment_method != 'cash_after_service' && $request['booking_status'] == 'canceled' && $booking->additional_charge > 0) {
                return response()->json(response_formatter(BOOKING_ALREADY_EDITED, errors: [['error_code' => 422, 'message' => BOOKING_ALREADY_EDITED['message']]]), 422);
            }

            $booking->booking_status = $request['booking_status'];
            $booking->provider_id = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;

            $bookingStatusHistory = $this->bookingStatusHistory;
            $bookingStatusHistory->booking_id = $bookingId;
            $bookingStatusHistory->changed_by = $request->user()->id;
            $bookingStatusHistory->booking_status = $request['booking_status'];
            if ($booking->isDirty('booking_status')) {
                if (!is_null($booking->repeat)) {
                    foreach ($booking->repeat->whereIn('booking_status', ['pending', 'accepted', 'ongoing']) as $bookingRepeat) {
                        $bookingRepeat->provider_id = $request->provider_id;
                        $bookingRepeat->booking_status = $request['booking_status'];
                        $bookingRepeat->serviceman_id = null;
                        $bookingRepeat->save();

                        $bookingRepeatStatusHistory = new $this->bookingStatusHistory;
                        $bookingRepeatStatusHistory->booking_id = $bookingId;
                        $bookingRepeatStatusHistory->changed_by = $request->user()->id;
                        $bookingRepeatStatusHistory->booking_status = $request['booking_status'];
                        $bookingRepeatStatusHistory->booking_repeat_id = $bookingRepeat->id;
                        $bookingRepeatStatusHistory->save();
                    }

                    if ($request['booking_status'] == 'canceled' && $booking->repeat->contains('booking_status', 'completed')) {
                        $booking->booking_status = 'completed';
                    }
                }

                DB::transaction(function () use ($bookingStatusHistory, $booking) {
                    $booking->save();
                    $bookingStatusHistory->save();
                });

                self::checkBooking($booking->id);

                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        if (isset($repeatBooking)){
            $repeatBooking->booking_status = $request['booking_status'];

            $bookingStatusHistory = $this->bookingStatusHistory;
            $bookingStatusHistory->booking_id = $bookingId;
            $bookingStatusHistory->changed_by = $request->user()->id;
            $bookingStatusHistory->booking_status = $request['booking_status'];
            $bookingStatusHistory->booking_repeat_id = $repeatBooking->id;

            if ($request['booking_status'] == 'canceled' && $repeatBooking->extra_fee > 0){

                $repeats = $this->booking->where('id', $repeatBooking->booking_id)->first();
                $sortedRepeats = $repeats->repeat->sortBy(function ($repeat) {
                    $parts = explode('-', $repeat->readable_id);
                    $suffix = end($parts);
                    return $this->readableIdToNumber($suffix);
                });

                $repeats['repeats'] = $sortedRepeats->values()->toArray();

                $nextService = collect($repeats['repeats'])
                    ->where('booking_status', 'ongoing')
                    ->skip(1)
                    ->first();

                if (!$nextService) {
                    $nextService = collect($repeats['repeats'])
                        ->where('booking_status', 'accepted')
                        ->skip(1)
                        ->first();
                }

                if (!$nextService) {
                    $nextService = collect($repeats['repeats'])
                        ->where('booking_status', 'pending')
                        ->skip(1)
                        ->first();
                }

                if (isset($nextService)){
                    $nextServiceId = $nextService['id'];
                    $nextServiceFee = $this->bookingRepeat->where('id', $nextServiceId)->first();
                    $nextServiceFee->extra_fee = $repeatBooking->extra_fee;
                    $nextServiceFee->total_booking_amount += $repeatBooking->extra_fee;
                    $nextServiceFee->save();

                    $repeatBooking->total_booking_amount -= $repeatBooking->extra_fee;
                    $repeatBooking->extra_fee = 0;
                }
            }

            if ($repeatBooking->isDirty('booking_status')) {
                DB::transaction(function () use ($bookingStatusHistory, $repeatBooking,) {
                    $repeatBooking->save();
                    $bookingStatusHistory->save();

                    $fullBooking = $this->bookingRepeat->where('booking_id', $repeatBooking->booking_id)->get();
                    $allInactive = $fullBooking->every(function ($repeat) {
                        return in_array($repeat->booking_status, ['completed', 'canceled'], true);
                    });

                    if ($allInactive) {
                        $repeatBooking->booking->booking_status = 'completed';
                        $repeatBooking->booking->save();
                    }

                    if (in_array($repeatBooking->booking_status, ['ongoing', 'completed', 'canceled'])) {
                        if ($repeatBooking->booking->booking_status != 'ongoing' && $repeatBooking->booking->booking_status != 'completed' && $repeatBooking->booking->booking_status != 'canceled') {
                            $repeatBooking->booking->booking_status = 'ongoing';
                            $repeatBooking->booking->save();
                        }
                    }
                });

                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     */
    public function evidencePhotosUpload($bookingId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'evidence_photos' => 'nullable|array',
            'evidence_photos.*' => 'image|max:'. uploadMaxFileSizeInKB('image') .'|mimes:' . implode(',', array_column(IMAGEEXTENSION, 'key')),
        ]);

        $booking = $this->booking->where('id', $bookingId)->where(function ($query) use ($request) {
            return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->orWhereNull('provider_id');
        })->first();

        $repeat = $this->bookingRepeat->where('id', $bookingId)->where(function ($query) use ($request) {
            return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->orWhereNull('provider_id');
        })->first();
        if (isset($booking)) {

            $evidence_photos = [];

            if ($booking->evidence_photos != 'null'){
                foreach ($booking->evidence_photos ?? [] as $image) {
                    $img = is_array($image) ? $image['image'] : $image;
                    file_remover('booking/evidence/', $img);
                }

                $booking->evidence_photos = [];
                $booking->save();
            }

            $booking->evidence_photos = [];

            if ($request->has('evidence_photos')) {
                foreach ($request->evidence_photos as $image) {
                    $imageName = file_uploader('booking/evidence/', APPLICATION_IMAGE_FORMAT, $image);
                    $evidence_photos[] = ['image'=>$imageName, 'storage'=> getDisk()];
                }
            }

            $booking->evidence_photos = $evidence_photos;
            $booking->save();

            return response()->json(response_formatter(DEFAULT_UPDATE_200), 200);
        }
        if (isset($repeat)) {

            $evidence_photos = [];

            if ($repeat->evidence_photos != 'null'){
                foreach ($repeat->evidence_photos ?? [] as $image) {
                    $img = is_array($image) ? $image['image'] : $image;
                    file_remover('booking/evidence/', $img);
                }

                $repeat->evidence_photos = [];
                $repeat->save();
            }

            $repeat->evidence_photos = [];

            if ($request->has('evidence_photos')) {
                foreach ($request->evidence_photos as $image) {
                    $imageName = file_uploader('booking/evidence/', APPLICATION_IMAGE_FORMAT, $image);
                    $evidence_photos[] = ['image'=>$imageName, 'storage'=> getDisk()];
                }
            }

            $repeat->evidence_photos = $evidence_photos;
            $repeat->save();

            return response()->json(response_formatter(DEFAULT_UPDATE_200), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
    }

    /**
     * Show the specified resource.
     * @param Request $request
     * @return JsonResponse
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 400);
        }

        $booking = $this->booking
            ->with(['customer'])
            ->where('id', $request['booking_id'])
            ->where(function ($query) use ($request) {
                return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id);
            })
            ->first();

        if (!isset($booking)) {

            $repeat = $this->bookingRepeat->where('id', $request['booking_id'])->where(function ($query) use ($request) {
                return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->orWhereNull('provider_id');
            })->first();

            if ($repeat){
                $fcmToken = $repeat?->booking?->customer?->fcm_token;
                $title = translate('Your booking verification OTP is') . ' ' . $repeat->booking_otp;

                if ($fcmToken) {
                    device_notification($fcmToken, $title, null, null, $repeat->id, 'booking', null, $repeat?->booking?->customer?->id);
                    return response()->json(response_formatter(NOTIFICATION_SEND_SUCCESSFULLY_200), 200);

                } else {
                    return response()->json(response_formatter(NOTIFICATION_SEND_FAILED_200), 200);
                }
            }

            return response()->json(response_formatter(DEFAULT_404), 404);
        }

        $fcmToken = $booking?->customer?->fcm_token;
        $title = translate('Your booking verification OTP is') . ' ' . $booking->booking_otp;

        if ($fcmToken) {
            device_notification($fcmToken, $title, null, null, $booking->id, 'booking', null, $booking?->customer?->id);
            return response()->json(response_formatter(NOTIFICATION_SEND_SUCCESSFULLY_200), 200);

        } else {
            return response()->json(response_formatter(NOTIFICATION_SEND_FAILED_200), 200);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     */
    public function paymentUpdate($bookingId, Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'paymentStatus' => 'required|in:1,0',
        ]);

        $booking = $this->booking->where('id', $bookingId)->where(function ($query) use ($request) {
            return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->orWhereNull('provider_id');
        })->first();

        $repeatBooking = $this->bookingRepeat->where('id', $bookingId)->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->first();

        if (isset($booking)) {
            $booking->is_paid = $request->paymentStatus == '1' ? 1 : 0;

            if ($booking->isDirty('is_paid')) {
                $booking->save();
                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }

        if (isset($repeatBooking)) {
            $repeatBooking->is_paid = $request->paymentStatus == '1' ? 1 : 0;

            if ($repeatBooking->isDirty('is_paid')) {
                $repeatBooking->save();
                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     */
    public function scheduleUpdate($bookingId, Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'service_schedule' => 'required',
        ]);

        $booking = $this->booking->where('id', $bookingId)->where(function ($query) use ($request) {
            return $query->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->orWhereNull('provider_id');
        })->first();

        $bookingRepeat = $this->bookingRepeat->where('id', $bookingId)->where('provider_id', app(\App\Services\OperationalAccess::class)->provider($request->user())->id)->first();

        if (isset($booking)) {
            $booking->service_schedule = Carbon::parse($request->service_schedule)->toDateTimeString();

            $bookingScheduleHistory = $this->bookingScheduleHistory;
            $bookingScheduleHistory->booking_id = $bookingId;
            $bookingScheduleHistory->changed_by = $request->user()->id;
            $bookingScheduleHistory->schedule = $request['service_schedule'];

            if ($booking->isDirty('service_schedule')) {
                $booking->save();
                $bookingScheduleHistory->save();
                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        if (isset($bookingRepeat)) {
            $bookingRepeat->service_schedule = Carbon::parse($request->service_schedule)->toDateTimeString();

            $bookingRepeatScheduleHistory = $this->bookingScheduleHistory;
            $bookingRepeatScheduleHistory->booking_id = $bookingRepeat->booking_id;
            $bookingRepeatScheduleHistory->changed_by = $request->user()->id;
            $bookingRepeatScheduleHistory->schedule = $request['service_schedule'];
            $bookingRepeatScheduleHistory->booking_repeat_id = $bookingId;

            if ($bookingRepeat->isDirty('service_schedule')) {
                $bookingRepeat->save();
                $bookingRepeatScheduleHistory->save();
                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     */
    public function servicemanUpdate($bookingId, Request $request): JsonResponse
    {
        if (nirab_branch_mode()) {
            $request->validate(['booking_id' => 'required|uuid', 'serviceman_id' => 'nullable|uuid|required_without:team_id', 'team_id' => 'nullable|uuid|required_without:serviceman_id']);
            $access = app(\App\Services\OperationalAccess::class);
            $branch = $access->branch($request->user());
            $access->authorizeBranch($request->user(), 'dispatch.manage', $branch);
            $subject = $this->booking->where('provider_id', $branch)->find($request->booking_id)
                ?? $this->bookingRepeat->where('provider_id', $branch)->with('booking')->findOrFail($request->booking_id);
            $parent = $subject instanceof \Modules\BookingModule\Entities\BookingRepeat ? $subject->booking : $subject;
            $booking = app(\Modules\BookingModule\Services\BookingAssignmentService::class)->assign($parent->id, $request->serviceman_id, $request->team_id, 'branch_manager', $request->user()->id);
            $servicemen = $this->serviceman->with('user')->withCount('bookings')->where('provider_id', $branch)->get();
            $search = $request->search;
            return response()->json(['view' => view('bookingmodule::provider.booking.partials.details.serviceman-info-modal-data', compact('servicemen', 'booking', 'search'))->render()]);
        }
        $booking = $this->booking->where('id', $request->booking_id)->first();
        $bookingRepeat = $this->bookingRepeat->where('id', $request->booking_id)->with('booking')->first();

        if (isset($booking)) {
            $booking->serviceman_id = $request->serviceman_id;
            $booking->save();

            if (!is_null($booking->repeat)) {
                foreach ($booking->repeat->whereIn('booking_status', ['pending', 'accepted', 'ongoing']) as $bookingRepeat) {
                    $bookingRepeat->serviceman_id = $request->serviceman_id;
                    $bookingRepeat->save();
                }
            }

            $search = $request->search;
            $servicemen = $this->serviceman
                ->withCount('bookings')
                ->where('provider_id', $bookingRepeat?->provider_id)
                ->when($request->has('search'), function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    return $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhereHas('user', function ($query) use ($key) {
                                $query->where('first_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('last_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('phone', 'LIKE', '%' . $key . '%')
                                    ->orWhere('email', 'LIKE', '%' . $key . '%');
                            });
                        }
                    });
                })
                ->whereHas('user', function ($query) {
                    $query->ofStatus(1);
                })->get();

            return response()->json([
                'view' => view('bookingmodule::provider.booking.partials.details.serviceman-info-modal-data', compact('servicemen', 'booking', 'search'))->render()
            ]);
        }
        if (isset($bookingRepeat)) {

            $bookingRepeat->serviceman_id = $request->serviceman_id;
            $bookingRepeat->save();

            if ($bookingRepeat->booking) {
                $bookingRepeat->booking->serviceman_id = $request->serviceman_id;
                $bookingRepeat->booking->save();
            }

//            $bookingRepeatAll = $this->bookingRepeat->where('booking_id', $bookingRepeat->booking_id)->get();
//
//            foreach ($bookingRepeatAll->whereIn('booking_status', ['pending', 'accepted', 'ongoing']) as $repeat) {
//                $repeat->serviceman_id = $request->serviceman_id;
//                $repeat->save();
//            }

            $search = $request->search;
            $servicemen = $this->serviceman
                ->withCount('bookings')
                ->where('provider_id', $bookingRepeat?->provider_id)
                ->when($request->has('search'), function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    return $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhereHas('user', function ($query) use ($key) {
                                $query->where('first_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('last_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('phone', 'LIKE', '%' . $key . '%')
                                    ->orWhere('email', 'LIKE', '%' . $key . '%');
                            });
                        }
                    });
                })
                ->whereHas('user', function ($query) {
                    $query->ofStatus(1);
                })->get();

            $booking = $bookingRepeat;

            return response()->json([
                'view' => view('bookingmodule::provider.booking.partials.details.serviceman-info-modal-data', compact('servicemen', 'booking', 'search'))->render()
            ]);
        }
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function upComingBookingCancel($bookingId, Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'booking_status' => 'required|in:' . implode(',', array_column(BOOKING_STATUSES, 'key')),
        ]);

        $repeatBooking = $this->bookingRepeat->where('id', $bookingId)->first();
        if (isset($repeatBooking)){
            $repeatBooking->booking_status = $request['booking_status'];

            $bookingStatusHistory = $this->bookingStatusHistory;
            $bookingStatusHistory->booking_id = $bookingId;
            $bookingStatusHistory->changed_by = $request->user()->id;
            $bookingStatusHistory->booking_status = $request['booking_status'];
            $bookingStatusHistory->booking_repeat_id = $repeatBooking->id;

            if ($repeatBooking->isDirty('booking_status')) {
                DB::transaction(function () use ($bookingStatusHistory, $repeatBooking,) {
                    $repeatBooking->save();
                    $bookingStatusHistory->save();
                });

                Toastr::success(translate(DEFAULT_STATUS_UPDATE_200['key']));
                return back();
            }
            Toastr::success(translate(NO_CHANGES_FOUND['key']));
            return back();
        }
        Toastr::success(translate(DEFAULT_204['key']));
        return back();
    }


    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function upComingBookingScheduleUpdate($bookingId, Request $request): RedirectResponse
    {
        $request->validate([
            'service_schedule' => 'required',
        ]);

        $bookingRepeat = $this->bookingRepeat->where('id', $bookingId)->first();

        if (isset($bookingRepeat)) {
            $bookingRepeat->service_schedule = Carbon::parse($request->service_schedule)->toDateTimeString();

            $bookingRepeatScheduleHistory = $this->bookingScheduleHistory;
            $bookingRepeatScheduleHistory->booking_id = $bookingRepeat->booking_id;
            $bookingRepeatScheduleHistory->changed_by = $request->user()->id;
            $bookingRepeatScheduleHistory->schedule = $request['service_schedule'];
            $bookingRepeatScheduleHistory->booking_repeat_id = $bookingId;

            if ($bookingRepeat->isDirty('service_schedule')) {
                $bookingRepeat->save();
                $bookingRepeatScheduleHistory->save();

                Toastr::success(translate(DEFAULT_UPDATE_200['key']));
                return back();
            }
            Toastr::success(translate(NO_CHANGES_FOUND['key']));
            return back();
        }
        Toastr::success(translate(DEFAULT_204['key']));
        return back();
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return RedirectResponse
     */
    public function serviceAddressUpdate($bookingId, Request $request): RedirectResponse
    {
        Validator::make($request->all(), [
            'service_address_id' => 'required',
        ]);

        $booking = $this->booking->where('id', $bookingId)->first();

        if (isset($booking)) {
            $booking->service_address_id = $request->service_address_id;

            if ($booking->isDirty('service_address_id')) {
                $booking->save();

                Toastr::success(translate(DEFAULT_STATUS_UPDATE_200['key']));
                return back();
            }
            Toastr::info(translate(NO_CHANGES_FOUND['key']));
            return back();
        }
        Toastr::success(translate(DEFAULT_204['key']));
        return back();
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return string|StreamedResponse
     * @throws IOException
     * @throws InvalidArgumentException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     */
    public function download(Request $request): string|StreamedResponse
    {
        $request->validate([
            'booking_status' => 'in:' . implode(',', array_column(BOOKING_STATUSES, 'key')) . ',all',
        ]);

        $queryParams = $request->only(['service_ids', 'start_date', 'end_date', 'search']);
        $filterCounter = collect($queryParams)->filter()->count();
        $bookingStatus = $queryParams['booking_status'] = $request->input('booking_status', 'pending');
        $queryParams['booking_type'] = $request->input('booking_type', '');
        $queryParams['service_type'] = $request->input('service_type', '');
        if (empty($queryParams['start_date'])) {
            $queryParams['start_date'] = null;
        }
        if (empty($queryParams['end_date'])) {
            $queryParams['end_date'] = null;
        }

        $maxBookingAmount = business_config('max_booking_amount', 'booking_setup')->live_values;
        $providerId = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;
        $packageSubscriber = PackageSubscriber::where('provider_id', $providerId)->first();
        $endDate = optional($packageSubscriber)->package_end_date;
        $canceled = optional($packageSubscriber)->is_canceled;
        $packageEndDate = $endDate ? Carbon::parse($endDate)->endOfDay() : null;
        $currentDate = Carbon::now()->subDay();
        $isPackageEnded = $packageEndDate ? $currentDate->diffInDays($packageEndDate, false) : null;
        $scheduleBookingEligibility = nextBookingEligibility($providerId);

        $serviceAtProviderPlace = (int)((business_config('service_at_provider_place', 'provider_config'))->live_values ?? 0);
        $serviceLocations = getProviderSettings(providerId: $providerId, key: 'service_location', type: 'provider_config') ?? ['customer'];

        $items = $this->booking->with(['customer', 'repeat'])
            ->search($request['search'], ['readable_id'])
            ->whereDoesntHave('ignores', function ($query) use ($providerId) {
                $query->where('provider_id', $providerId);
            })
            ->when(!in_array($request['booking_status'], ['pending', 'all']), function ($query) use ($providerId, $request, $maxBookingAmount) {
                $query->ofBookingStatus($request['booking_status'])
                    ->where(function ($query) use ($providerId) {
                        $query->where('provider_id', $providerId)
                            ->orWhereHas('repeat', function ($subQuery) use ($providerId) {
                                $subQuery->where('provider_id', $providerId);
                            });
                    })
                    ->when($request['booking_status'] == 'accepted', function ($query) use ($request, $maxBookingAmount) {
                        $query->providerAcceptedBookings(app(\App\Services\OperationalAccess::class)->provider($request->user())->id, $maxBookingAmount);
                    });
            })
            ->when($request['booking_status'] == 'pending', function ($query) use ($packageSubscriber, $canceled, $scheduleBookingEligibility, $isPackageEnded, $request, $maxBookingAmount, $serviceAtProviderPlace, $serviceLocations) {
                if (nirab_branch_mode()) {
                    $query->providerPendingBookings(app(\App\Services\OperationalAccess::class)->provider($request->user()), $maxBookingAmount)
                        ->when($serviceAtProviderPlace == 1, function ($query) use ($serviceLocations) {
                            $query->whereIn('service_location', $serviceLocations);
                        });

                    return;
                }

                if ($packageSubscriber) {
                    if ($isPackageEnded > 0 && $scheduleBookingEligibility && !$canceled) {
                        $query->providerPendingBookings(app(\App\Services\OperationalAccess::class)->provider($request->user()), $maxBookingAmount)
                            ->when($serviceAtProviderPlace == 1, function ($query) use ($serviceLocations) {
                                $query->whereIn('service_location', $serviceLocations);
                            });
                    }else{
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    $query->providerPendingBookings(app(\App\Services\OperationalAccess::class)->provider($request->user()), $maxBookingAmount)
                        ->when($serviceAtProviderPlace == 1, function ($query) use ($serviceLocations) {
                            $query->whereIn('service_location', $serviceLocations);
                        });
                }
            })
            ->when($request['service_type'] != 'all', function ($query) use ($request) {
                return $query->ofRepeatBookingStatus($request['service_type'] === 'repeat' ? 1 : ($request['service_type'] === 'regular' ? 0 : null));
            })
            ->filterByDateRange($request['start_date'], $request['end_date'])

            ->filterByServiceIds($request['service_ids'])
            ->latest()->get();

        $formatted = $items->map(function ($item, $key) {
            $serviceAddress = is_string($item->service_address) ? json_decode($item->service_address) : $item->service_address;
            if ($item->is_repeated) {
                $sortedRepeats = $item->repeat->sortBy(function ($repeat) {
                    $parts = explode('-', $repeat->readable_id);
                    return $this->readableIdToNumber(end($parts));
                })->values();

                $nextService = $sortedRepeats->firstWhere('booking_status', 'ongoing')
                    ?? $sortedRepeats->firstWhere('booking_status', 'accepted')
                    ?? $sortedRepeats->firstWhere('booking_status', 'pending');
                $lastRepeat = $sortedRepeats->last();

                $scheduleSource = $nextService?->service_schedule ?? $lastRepeat?->service_schedule;
            } else {
                $scheduleSource = $item->service_schedule;
            }

            return [
                translate('SL') => $key + 1,
                translate('Booking_ID') => $item->readable_id,
                translate('Where_Service_will_be_Provided') => $item->service_location == 'provider' ? translate('Provider Location') : translate('Customer Location'),
                translate('Customer_Name') => $item->customer ? trim(($item->customer->first_name ?? '') . ' ' . ($item->customer->last_name ?? '')) : ($serviceAddress?->contact_person_name ?? ''),
                translate('Customer_Phone') => $item->customer ? $item->customer->phone : ($serviceAddress?->contact_person_number ?? ''),
                translate('Total_Amount') => with_currency_symbol($item->total_booking_amount),
                translate('Payment_Status') => $item->is_paid ? translate('paid') : translate('unpaid'),
                translate('Schedule_Date') => $scheduleSource ? \Carbon\Carbon::parse($scheduleSource)->format('d-M-Y h:i A') : '',
                translate('Booking_Date') => $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d-M-Y h:i A') : '',
                translate('Status') => translate($item->booking_status),
            ];
        });

        $fileName = 'provider_bookings_' . $bookingStatus . '_list_' . date('Y_m_d') . '.xlsx';
        return (new FastExcel($formatted))->download($fileName);
    }


    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable
     */
    public function invoice($id, Request $request): Renderable
    {
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::provider.booking.invoice', compact('booking'));
    }


    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function fullBookingInvoice($id): Renderable
    {
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user','repeat'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::provider.booking.fullbooking-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function fullBookingSingleInvoice($id): Renderable
    {
        $booking = $this->bookingRepeat->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'booking', 'provider', 'serviceman'])->find($id);

        $booking->booking->service_address = $booking->booking->service_address_location != null ? json_decode($booking->booking->service_address_location) : $booking->booking->service_address;

        return view('bookingmodule::provider.booking.fullbooking-single-invoice', compact('booking'));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function ajaxGetServiceInfo(Request $request): JsonResponse
    {
        $request->validate(['booking_id'=>'required|uuid', 'booking_repeat_id'=>'nullable|uuid',
            'service_id'=>'required|uuid', 'variant_key'=>'required|string', 'quantity'=>'required|integer|min:1|max:1000',
            'field_answers'=>'nullable|array|max:100']);
        $parent = \Modules\BookingModule\Entities\Booking::findOrFail($request->booking_id);
        $booking = $request->booking_repeat_id ? $parent->repeat()->findOrFail($request->booking_repeat_id) : $parent;
        app(\Modules\BookingModule\Services\BookingAmendmentService::class)->authorize($booking);
        $catalog = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class);
        $context = $catalog->context($booking);
        $service = $catalog->query($booking)->findOrFail($request->service_id);
        $variation = $service->variations->firstWhere('variant_key',$request->variant_key);
        abort_unless($variation,422,'invalid_variant');
        $engine = app(\Modules\ServiceManagement\Services\PricingEngine::class);
        $price = $engine->calculate($service,$variation,(int)$request->quantity,$context['zone_id'],$context['provider_id'],$context['area_id'],Carbon::parse($booking->service_schedule),fieldAnswers:$request->field_answers ?? []);
        $discount = max(basic_discount_calculation($service,$price->adjustedPrice),campaign_discount_calculation($service,$price->adjustedPrice));
        $price = $engine->calculate($service,$variation,(int)$request->quantity,$context['zone_id'],$context['provider_id'],$context['area_id'],Carbon::parse($booking->service_schedule),$discount,$request->field_answers ?? []);
        return response()->json(response_formatter(DEFAULT_200, ['service_id'=>$service->id,'service_name'=>$service->name,
            'variant_key'=>$variation->variant_key,'quantity'=>(int)$request->quantity,'field_answers'=>$request->field_answers ?? [],
            'service_cost'=>round($price->adjustedPrice/$request->quantity,3),'total_cost'=>$price->total,'tax_amount'=>$price->tax]));
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function ajaxGetVariant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'zone_id' => 'required|uuid',
            'service_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json(response_formatter(DEFAULT_400, null, error_processor($validator)), 200);
        }

        $variations = Variation::withoutGlobalScopes()->where('service_id', $request['service_id'])
            ->forZone($request['zone_id'])
            ->where('price', '>', 0)
            ->get();
        return response()->json(response_formatter(DEFAULT_200, $variations, null), 200);
    }

    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function updateBookingService(Request $request): RedirectResponse
    {

        Validator::make($request->all(), ['qty'=>'required|array','service_ids'=>'required|array',
            'variant_keys'=>'required|array','booking_id'=>'required|uuid'])->validate();
        $items = [];
        foreach ($request['service_ids'] as $i => $serviceId) {
            $item = ['service_id'=>$serviceId, 'variant_key'=>$request['variant_keys'][$i] ?? '', 'quantity'=>$request['qty'][$i] ?? 0];
            if (!empty($request['detail_ids'][$i])) $item['detail_id'] = $request['detail_ids'][$i];
            if (isset($request['field_answers'][$i])) $item['field_answers'] = is_string($request['field_answers'][$i]) ? json_decode($request['field_answers'][$i], true, 512, JSON_THROW_ON_ERROR) : $request['field_answers'][$i];
            $items[] = $item;
        }
        $parent = \Modules\BookingModule\Entities\Booking::findOrFail($request['booking_id']);
        $booking = $parent;
        app(\Modules\BookingModule\Services\BookingAmendmentService::class)->replaceAll($booking,$items, false);
        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }


    /**
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException|AuthorizationException
     */
    public function updateRepeatBookingService(Request $request): RedirectResponse
    {

        Validator::make($request->all(), ['qty'=>'required|array','service_ids'=>'required|array',
            'variant_keys'=>'required|array','booking_id'=>'required|uuid'])->validate();
        $items = [];
        foreach ($request['service_ids'] as $i => $serviceId) {
            $item = ['service_id'=>$serviceId, 'variant_key'=>$request['variant_keys'][$i] ?? '', 'quantity'=>$request['qty'][$i] ?? 0];
            if (!empty($request['detail_ids'][$i])) $item['detail_id'] = $request['detail_ids'][$i];
            if (isset($request['field_answers'][$i])) $item['field_answers'] = is_string($request['field_answers'][$i]) ? json_decode($request['field_answers'][$i], true, 512, JSON_THROW_ON_ERROR) : $request['field_answers'][$i];
            $items[] = $item;
        }
        $parent = \Modules\BookingModule\Entities\Booking::findOrFail($request['booking_id']);
        $booking = \Modules\BookingModule\Entities\BookingRepeat::where('booking_id',$parent->id)->findOrFail($request['booking_repeat_id']);
        app(\Modules\BookingModule\Services\BookingAmendmentService::class)->replaceAll($booking,$items, $request->boolean('next_all_booking_change'));
        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }

    public function changeServiceLocation($bookingId, Request $request)
    {
        $booking = $this->booking->find($bookingId);

        if (!$booking) {
            Toastr::error(translate('Booking not found'));
            return back();
        }

        $serviceAtProviderPlace = (int)((business_config('service_at_provider_place', 'provider_config'))->live_values ?? 0);

        if ($serviceAtProviderPlace == 0 && $request->service_location == 'provider') {
            Toastr::error(translate('Cannot switch to provider when provider service location is off'));
            return back();
        }

        if ($request->service_location == 'customer') {
            $existingAddress = json_decode($booking->service_address_location, true) ?? [];

            // Update only the changed values, keeping others untouched
            $updatedAddress = array_merge($existingAddress, [
                "lat" => $request->latitude ?? $existingAddress['lat'] ?? null,
                "lon" => $request->longitude ?? $existingAddress['lon'] ?? null,
                "city" => $request->city ?? $existingAddress['city'] ?? null,
                "street" => $request->street ?? $existingAddress['street'] ?? "",
                "zip_code" => $request->zip_code ?? $existingAddress['zip_code'] ?? "",
                "country" => $request->country ?? $existingAddress['country'] ?? null,
                "address" => $request->address ?? $existingAddress['address'] ?? null,
                "updated_at" => now()->toISOString(),
                "address_type" => $request->address_type ?? $existingAddress['address_type'] ?? "others",
                "contact_person_name" => $request->contact_person_name ?? $existingAddress['contact_person_name'] ?? null,
                "contact_person_number" => $request->contact_person_number ?? $existingAddress['contact_person_number'] ?? null,
                "address_label" => $request->address_label ?? $existingAddress['address_label'] ?? null,
                "zone_id" => $request->zone_id ?? $existingAddress['zone_id'] ?? null,
                "is_guest" => $request->is_guest ?? $existingAddress['is_guest'] ?? 0,
                "house" => $request->house ?? $existingAddress['house'] ?? "",
                "floor" => $request->floor ?? $existingAddress['floor'] ?? null,
            ]);

            $updateData = [
                'service_location' => 'customer',
                'service_address_location' => json_encode($updatedAddress), // Store updated JSON
            ];

        } else {
            $updateData = [
                'service_location' => 'provider',
            ];
        }

        $booking->update($updateData);

        if ($request->has('next_all_booking_change')){
            $this->bookingRepeat
                ->where('booking_id', $booking->id)
                ->whereIn('booking_status', ['accepted', 'ongoing'])
                ->update($updateData);
        }else{
            if ($booking->repeat->isNotEmpty()) {
                $sortedRepeats = $booking->repeat->sortBy(function ($repeat) {
                    $parts = explode('-', $repeat->readable_id);
                    $suffix = end($parts);
                    return $this->readableIdToNumber($suffix);
                });

                // Keep original collection for update
                $booking['repeats'] = $sortedRepeats->values()->toArray();

                // Work with the original model collection
                $sortedModelRepeats = $sortedRepeats->values();

                $nextService = $sortedModelRepeats->firstWhere('booking_status', 'ongoing')
                    ?? $sortedModelRepeats->firstWhere('booking_status', 'accepted')
                    ?? $sortedModelRepeats->firstWhere('booking_status', 'pending');

                if ($nextService) {
                    $nextService->update($updateData);
                }
            }
        }

        $user = $booking?->customer;
        $repeatOrRegular = $booking?->is_repeated ? 'repeat' : 'regular';
        if (isset($user) && $user?->fcm_token && $user?->is_active) {
            try {
                device_notification($user?->fcm_token, translate('service location updated'), null, null, $booking->id, 'booking', null, null, null, null, $repeatOrRegular);
            }catch (\Exception $exception) {
                //
            }
        }

        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }

    public function repeatChangeServiceLocation($bookingId, Request $request)
    {
        $booking = $this->bookingRepeat->find($bookingId);

        if (!$booking) {
            Toastr::error(translate('Booking not found'));
            return back();
        }

        $serviceAtProviderPlace = (int)((business_config('service_at_provider_place', 'provider_config'))->live_values ?? 0);

        if ($serviceAtProviderPlace == 0 && $request->service_location == 'provider') {
            Toastr::error(translate('Cannot switch to provider when provider service location is off'));
            return back();
        }

        if ($request->service_location == 'customer') {
            $existingAddress = json_decode($booking->service_address_location, true) ?? [];

            // Update only the changed values, keeping others untouched
            $updatedAddress = array_merge($existingAddress, [
                "lat" => $request->latitude ?? $existingAddress['lat'] ?? null,
                "lon" => $request->longitude ?? $existingAddress['lon'] ?? null,
                "city" => $request->city ?? $existingAddress['city'] ?? null,
                "street" => $request->street ?? $existingAddress['street'] ?? "",
                "zip_code" => $request->zip_code ?? $existingAddress['zip_code'] ?? "",
                "country" => $request->country ?? $existingAddress['country'] ?? null,
                "address" => $request->address ?? $existingAddress['address'] ?? null,
                "updated_at" => now()->toISOString(),
                "address_type" => $request->address_type ?? $existingAddress['address_type'] ?? "others",
                "contact_person_name" => $request->contact_person_name ?? $existingAddress['contact_person_name'] ?? null,
                "contact_person_number" => $request->contact_person_number ?? $existingAddress['contact_person_number'] ?? null,
                "address_label" => $request->address_label ?? $existingAddress['address_label'] ?? null,
                "zone_id" => $request->zone_id ?? $existingAddress['zone_id'] ?? null,
                "is_guest" => $request->is_guest ?? $existingAddress['is_guest'] ?? 0,
                "house" => $request->house ?? $existingAddress['house'] ?? "",
                "floor" => $request->floor ?? $existingAddress['floor'] ?? null,
            ]);

            $updateData = [
                'service_location' => 'customer',
                'service_address_location' => json_encode($updatedAddress), // Store updated JSON
            ];

        } else {
            $updateData = [
                'service_location' => 'provider',
            ];
        }

        $booking->update($updateData);

        $mainBooking = $this->booking->find($booking->booking_id);
        if ($mainBooking) {
            $mainBooking->update($updateData);
        }

        $user = $booking?->customer;
        $repeatOrRegular = $booking?->is_repeated ? 'repeat' : 'regular';
        if (isset($user) && $user?->fcm_token && $user?->is_active) {
            try {
                device_notification($user?->fcm_token, translate('service location updated'), null, null, $booking->id, 'booking', null, null, null, null, $repeatOrRegular);
            }catch (Exception $exception){
                //
            }
        }

        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }

    public function calendarView(Request $request): Renderable
    {
        return view('bookingmodule::provider.booking.calendar-view');
    }

    public function calendarEvents(Request $request)
    {
        $providerId = app(\App\Services\OperationalAccess::class)->provider($request->user())->id;
        if (!$providerId) {
            return response()->json([]);
        }

        $mode = $request->mode ?? 'dayGridMonth';

        /*
        |--------------------------------------------------------------------------
        | Resolve calendar date range
        |--------------------------------------------------------------------------
        */
        if ($request->filled('filter_start_date') && $request->filled('filter_end_date')) {

            $sDate = Carbon::parse($request->filter_start_date)->startOfDay();
            $eDate = Carbon::parse($request->filter_end_date)->endOfDay();

        } else {

            if ($mode === 'dayGridMonth') {
                $sDate = Carbon::create($request->year, $request->month, 1)->startOfDay();
                $eDate = Carbon::create($request->year, $request->month, 1)->endOfMonth()->endOfDay();

            } elseif ($mode === 'timeGridWeek') {
                $sDate = Carbon::parse($request->start_date)->startOfDay();
                $eDate = Carbon::parse($request->end_date)->endOfDay();

            } else { // timeGridDay
                $sDate = Carbon::parse($request->date)->startOfDay();
                $eDate = Carbon::parse($request->date)->endOfDay();
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Load bookings (regular + repeat masters)
        |--------------------------------------------------------------------------
        */
        $bookings = Booking::where('provider_id', $providerId)
            ->where(function ($q) use ($sDate, $eDate) {

                // Regular bookings
                $q->where(function ($q1) use ($sDate, $eDate) {
                    $q1->where('is_repeated', 0)
                        ->whereBetween('service_schedule', [$sDate, $eDate]);
                })

                    // Repeat bookings (date comes from repeat table)
                    ->orWhere(function ($q2) use ($sDate, $eDate) {
                        $q2->where('is_repeated', 1)
                            ->whereHas('repeat', function ($qr) use ($sDate, $eDate) {
                                $qr->whereBetween('service_schedule', [$sDate, $eDate]);
                            });
                    });
            })
            ->when($request->filled('booking_status'), function ($q) use ($request) {
                $statuses = explode(',', $request->booking_status);
                $q->whereIn('booking_status', $statuses);
            })
            ->when(
                $request->filled('booking_type') && $request->booking_type !== 'all',
                fn ($q) => $q->where('is_repeated', $request->booking_type === 'repeat' ? 1 : 0)
            )
            ->with('repeat')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | EXPAND bookings into calendar items
        */

        $calendarItems = [];

        foreach ($bookings as $booking) {

            // REGULAR BOOKING â†’ single calendar entry
            if (!$booking->is_repeated) {

                if (!$booking->service_schedule) {
                    continue;
                }

                $calendarItems[] = [
                    'booking_id' => $booking->id,
                    'schedule'   => Carbon::parse($booking->service_schedule),
                ];

                continue;
            }

            // REPEAT BOOKING â†’ multiple calendar entries
            foreach ($booking->repeat as $repeat) {

                // must fall inside calendar range
                if (
                    $repeat->service_schedule < $sDate ||
                    $repeat->service_schedule > $eDate
                ) {
                    continue;
                }

                // optional status filter
                if ($request->filled('booking_status')) {
                    $statuses = explode(',', $request->booking_status);
                    if (!in_array($repeat->booking_status, $statuses)) {
                        continue;
                    }
                }

                $calendarItems[] = [
                    'booking_id' => $booking->id,
                    'schedule'   => Carbon::parse($repeat->service_schedule),
                ];
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Group expanded items by date/hour (FullCalendar format)
        |--------------------------------------------------------------------------
        */
        $groups = [];

        foreach ($calendarItems as $item) {

            $dt = $item['schedule'];

            if ($mode === 'dayGridMonth') {

                $key = $dt->format('Y-m-d');
                $start = $dt->format('Y-m-d') . 'T00:00:00';
                $end   = $dt->format('Y-m-d') . 'T23:59:59';

            } else {

                $key = $dt->format('Y-m-d H:00');
                $start = $dt->format('Y-m-d\TH:00:00');
                $end   = $dt->copy()->addHour()->format('Y-m-d\TH:00:00');
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'count'      => 0,
                    'start'      => $start,
                    'end'        => $end,
                    'bookingIds' => [],
                ];
            }

            $groups[$key]['count']++;
            $groups[$key]['bookingIds'][] = $item['booking_id'];
        }

        /*
        |--------------------------------------------------------------------------
        | Build FullCalendar events
        |--------------------------------------------------------------------------
        */
        $events = [];

        foreach ($groups as $group) {
            $events[] = [
                'title'      => $group['count'] > 1
                    ? sprintf('%02d Bookings', $group['count'])
                    : sprintf('%02d Booking', $group['count']),
                'start'      => $group['start'],
                'end'        => $group['end'],
                'allDay'     => false,
                'bookingIds' => $group['bookingIds'],
                'date' => $group['start']
            ];
        }

        return response()->json($events);
    }


    public function getCalendarBookingList(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        $rawDate = $request->input('date');

        $calendarDate = $request->date
            ? Carbon::createFromFormat('Y-m-d', $rawDate)
            : null;

        $bookings = Booking::with('repeat')
            ->whereIn('id', $ids)
            ->get();

        return response()->json(
            $bookings->map(function ($booking) use ($calendarDate) {

                $serviceSchedule = null;

                /*
                 |--------------------------------------------
                 | REPEAT BOOKING â†’ match clicked date
                 |--------------------------------------------
                 */
                if ($booking->is_repeated && $booking->repeat->isNotEmpty() && $calendarDate) {

                    $matchedRepeat = $booking->repeat
                        ->first(function ($repeat) use ($calendarDate) {
                            return Carbon::parse($repeat->service_schedule)
                                ->isSameDay($calendarDate);
                        });

                    // If matched by date â†’ use it
                    if ($matchedRepeat) {
                        $serviceSchedule = $matchedRepeat->service_schedule;
                    }
                }

                // fallback logic
                if (!$serviceSchedule) {
                    if ($booking->is_repeated && $booking->repeat->isNotEmpty()) {
                        $serviceSchedule = $booking->repeat
                            ->sortBy('service_schedule')
                            ->first()
                            ?->service_schedule;
                    } else {
                        $serviceSchedule = $booking->service_schedule;
                    }
                }

                if (!$serviceSchedule) {
                    return null;
                }

                return [
                    'id'              => $booking->id,
                    'readable_id'     => $booking->readable_id,
                    'time'            => Carbon::parse($serviceSchedule)->format('h:i A'),
                    'service_date'    => Carbon::parse($serviceSchedule)->format('d M, Y, h:i a'),
                    'service_location'=> $booking->service_location ?? 'At your location',
                    'status'          => ucfirst($booking->booking_status),
                    'statusClass'     => match ($booking->booking_status) {
                        'pending'   => 'info',
                        'accepted'  => 'primary',
                        'ongoing'   => 'warning',
                        'completed' => 'success',
                        'canceled'  => 'danger',
                        default     => 'info'
                    },
                    'amount'          => with_currency_symbol($booking->total_booking_amount),
                    'is_repeated'     => $booking->is_repeated
                ];
            })->filter()->values()
        );
    }

}
