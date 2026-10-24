<?php

namespace Modules\BookingModule\Http\Controllers\Web\Admin;

use App\Services\NirabBookingFinancialService;
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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\BookingModule\Entities\Booking;
use Modules\BookingModule\Entities\BookingAdditionalInformation;
use Modules\BookingModule\Entities\BookingDetail;
use Modules\BookingModule\Entities\BookingRepeat;
use Modules\BookingModule\Entities\BookingRepeatDetails;
use Modules\BookingModule\Entities\BookingRepeatHistory;
use Modules\BookingModule\Entities\BookingScheduleHistory;
use Illuminate\Http\RedirectResponse;
use Modules\BookingModule\Entities\BookingStatusHistory;
use Modules\BookingModule\Http\Traits\BookingTrait;
use Modules\ProviderManagement\Entities\Provider;
use Modules\ServiceManagement\Entities\Service;
use Modules\ServiceManagement\Entities\Variation;
use Modules\UserManagement\Entities\Serviceman;
use Modules\UserManagement\Entities\UserAddress;
use Modules\ZoneManagement\Entities\Zone;
use Rap2hpoutre\FastExcel\FastExcel;
use Symfony\Component\HttpFoundation\StreamedResponse;
use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BookingController extends Controller
{

    private Booking $booking;
    private BookingRepeat $bookingRepeat;
    private BookingStatusHistory $bookingStatusHistory;
    private BookingRepeatHistory $bookingRepeatHistory;
    private BookingScheduleHistory $bookingScheduleHistory;
    private Zone $zone;
    private Serviceman $serviceman;
    private Provider $provider;
    private UserAddress $userAddress;
    private BookingDetail $bookingDetails;
    private BookingAdditionalInformation $bookingAdditionalInformation;
    private BookingRepeatDetails $bookingRepeatDetail;

    use BookingTrait;
    use AuthorizesRequests;

    public function __construct(Booking $booking, BookingRepeatDetails $bookingRepeatDetail, BookingRepeatHistory $bookingRepeatHistory, BookingRepeat $bookingRepeat, BookingDetail $bookingDetails, BookingStatusHistory $bookingStatusHistory, BookingScheduleHistory $bookingScheduleHistory, Zone $zone, Serviceman $serviceman, Provider $provider, UserAddress $userAddress, BookingAdditionalInformation $bookingAdditionalInformation)
    {
        $this->booking = $booking;
        $this->bookingRepeat = $bookingRepeat;
        $this->bookingRepeatDetail = $bookingRepeatDetail;
        $this->bookingRepeatHistory = $bookingRepeatHistory;
        $this->bookingDetails = $bookingDetails;
        $this->bookingStatusHistory = $bookingStatusHistory;
        $this->bookingScheduleHistory = $bookingScheduleHistory;
        $this->zone = $zone;
        $this->serviceman = $serviceman;
        $this->provider = $provider;
        $this->userAddress = $userAddress;
        $this->bookingAdditionalInformation = $bookingAdditionalInformation;

    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     * @throws AuthorizationException
     */
    public function index(Request $request): Renderable
    {
        if (nirab_branch_mode() && $request->filled('provider_id')) {
            $request->validate(['provider_id' => 'uuid']);
            app(\App\Services\OperationalAccess::class)->authorizeBranch($request->user(), 'analytics.read', $request->provider_id);
        }
        $this->authorize('booking_view');
        $request->validate([
            'booking_status' => 'in:' . implode(',', array_column(BOOKING_STATUSES, 'key')) . ',all',
        ]);

        $queryParams = $request->only(['zone_ids', 'service_ids', 'start_date', 'end_date', 'search', 'service_type', 'provider_id']);
        $filterCounter = collect($queryParams)->filter(function ($value, $key) use ($queryParams) {
            if ($key === 'service_type' || $key === 'search') {
                return false;
            }

            return !is_null($value) && $value !== '';
        })->count();
        $bookingStatus = $queryParams['booking_status'] = $request->input('booking_status', 'pending');
        $queryParams['booking_type'] = $request->input('booking_type', '');
        $queryParams['service_type'] = $request->input('service_type', '');
        $queryParams['provider_assigned'] = $request->input('provider_assigned', '');

        if (empty($queryParams['start_date'])) {
            $queryParams['start_date'] = null;
        }
        if (empty($queryParams['end_date'])) {
            $queryParams['end_date'] = null;
        }

        $maxBookingAmount = (business_config('max_booking_amount', 'booking_setup'))->live_values;
        $bookings = $this->booking
            ->when($request->provider_id, fn ($q, $id) => $q->where('provider_id', $id))
            ->with(['customer'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    foreach ($keys as $key) {
                        $query->orWhere('readable_id', 'LIKE', '%' . $key . '%')
                            ->orWhereHas('customer', function ($q) use ($key) {
                                $q->where('first_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('last_name', 'LIKE', '%' . $key . '%');
                            });
                    }
                });
            })
            ->when($bookingStatus != 'all', function ($query) use ($bookingStatus, $maxBookingAmount, $request) {
                $query->when($bookingStatus == 'pending', function ($query) use ($maxBookingAmount) {
                    $query->adminPendingBookings($maxBookingAmount);
                })->when($bookingStatus == 'accepted', function ($query) use ($maxBookingAmount) {
                    $query->adminAcceptedBookings($maxBookingAmount);
                })->ofBookingStatus($request['booking_status']);
            })
            ->when($request['service_type'] != 'all', function ($query) use ($request) {
                return $query->ofRepeatBookingStatus($request['service_type'] === 'repeat' ? 1 : ($request['service_type'] === 'regular' ? 0 : null));
            })
            ->when($request['provider_assigned'] == 'assigned', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('provider_id')
                        ->orWhereHas('repeat', function ($q) {
                            $q->whereNotNull('provider_id');
                        });
                });
            })
            ->when($request['provider_assigned'] == 'unassigned', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('provider_id');
                });
            })
            ->filterByZoneIds($request['zone_ids'])

            ->filterByServiceIds($request['service_ids'])
            ->filterByDateRange($request['start_date'], $request['end_date'])
            ->latest()
            ->paginate(pagination_limit())
            ->appends($queryParams);

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


        $zones = $this->zone->withoutGlobalScope('translate')->select('id', 'name')->get();
        $serviceCatalog = Service::withoutGlobalScopes()->select('id', 'name')->orderBy('name')->get();


        return view('bookingmodule::admin.booking.list', compact('bookings', 'zones', 'serviceCatalog', 'queryParams', 'filterCounter'));
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function checkBooking(): Renderable
    {
        $this->booking->where('is_checked', 0)->update(['is_checked' => 1]);
    }

    /**
     * @param Request $request
     * @return Factory|View|Application
     * @throws AuthorizationException
     */
    public function bookingVerificationList(Request $request): Factory|View|Application
    {
        $this->authorize('booking_view');
        $request->validate([
            'booking_status' => 'in:' . implode(',', array_column(BOOKING_STATUSES, 'key')) . ',all',
            'type' => 'in:pending,denied'
        ]);
        $request['booking_status'] = $request['booking_status'] ?? 'pending';

        $queryParams = [];
        $filterCounter = 0;
        $type = $request->type ?? 'pending';

        if ($request->has('zone_ids')) {
            $zoneIds = $request['zone_ids'];
            $queryParams['zone_ids'] = $zoneIds;
            $filterCounter += count($zoneIds);
        }

        if ($request->has('service_ids')) {
            $serviceIds = $request['service_ids'];
            $queryParams['service_ids'] = $serviceIds;
            $filterCounter += count($serviceIds);
        }

        if ($request->has('start_date')) {
            $startDate = $request['start_date'];
            $queryParams['start_date'] = $startDate;
            if (!is_null($request['start_date'])) $filterCounter++;
        } else {
            $queryParams['start_date'] = null;
        }

        if ($request->has('end_date')) {
            $endDate = $request['end_date'];
            $queryParams['end_date'] = $endDate;
            if (!is_null($request['end_date'])) $filterCounter++;
        } else {
            $queryParams['end_date'] = null;
        }

        if ($request->has('search')) {
            $search = $request['search'];
            $queryParams['search'] = $search;
        }

        $queryParams['type'] = $type;

        if ($request->has('booking_status')) {
            $bookingStatus = $request['booking_status'];
            $queryParams['booking_status'] = $bookingStatus;
        } else {
            $queryParams['booking_status'] = 'pending';
        }

        $maxBookingAmount = (business_config('max_booking_amount', 'booking_setup'))->live_values;

        $bookings = $this->booking->with(['customer'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    foreach ($keys as $key) {
                        $query->orWhere('readable_id', 'LIKE', '%' . $key . '%')
                              ->orWhereHas('customer', function ($q) use ($key) {
                                  $q->where('first_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('last_name', 'LIKE', '%' . $key . '%');
                              });
                    }
                });
            })
            ->when($bookingStatus == 'pending', function ($query) use ($maxBookingAmount, $type) {
                $query->when($type == 'pending', function ($query) {
                    $query->where('is_verified', '0');
                })->when($type == 'denied', function ($query) {
                    $query->where('is_verified', '2');
                })
                    ->where('payment_method', 'cash_after_service')
                    ->Where('total_booking_amount', '>', $maxBookingAmount)
                    ->whereIn('booking_status', ['pending', 'accepted']);
            })
            ->when($request->has('zone_ids'), function ($query) use ($request) {
                $query->whereIn('zone_id', $request['zone_ids']);
            })->when($queryParams['start_date'] != null && $queryParams['end_date'] != null, function ($query) use ($request) {
                if ($request['start_date'] == $request['end_date']) {
                    $query->whereDate('created_at', Carbon::parse($request['start_date'])->startOfDay());
                } else {
                    $query->whereBetween('created_at', [Carbon::parse($request['start_date'])->startOfDay(), Carbon::parse($request['end_date'])->endOfDay()]);
                }
            })->when($request->has('service_ids'), function ($query) use ($request) {
                $query->filterByServiceIds($request['service_ids']);
            })
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

                $booking['nextService'] = $nextService;
            }
        }

        $zones = $this->zone->select('id', 'name')->withoutGlobalScope('translate')->get();
        $serviceCatalog = Service::withoutGlobalScopes()->select('id', 'name')->orderBy('name')->get();


        return view('bookingmodule::admin.booking.verification-list', compact('bookings', 'zones', 'serviceCatalog', 'queryParams', 'filterCounter', 'type'));
    }

    /**
     * @param Request $request
     * @return Factory|View|Application
     * @throws AuthorizationException
     */
    public function bookingOfflinePaymentList(Request $request): Factory|View|Application
    {
        $this->authorize('booking_view');
        $request['booking_status'] = $request['booking_status'] ?? 'pending';

        $queryParams = ['review_status' => $request->query('review_status')];
        $filterCounter = 0;

        if ($request->has('zone_ids')) {
            $zoneIds = $request['zone_ids'];
            $queryParams['zone_ids'] = $zoneIds;
            $filterCounter += count($zoneIds);
        }

        if ($request->has('service_ids')) {
            $serviceIds = $request['service_ids'];
            $queryParams['service_ids'] = $serviceIds;
            $filterCounter += count($serviceIds);
        }

        if ($request->has('start_date')) {
            $startDate = $request['start_date'];
            $queryParams['start_date'] = $startDate;
            if (!is_null($request['start_date'])) $filterCounter++;
        } else {
            $queryParams['start_date'] = null;
        }

        if ($request->has('end_date')) {
            $endDate = $request['end_date'];
            $queryParams['end_date'] = $endDate;
            if (!is_null($request['end_date'])) $filterCounter++;
        } else {
            $queryParams['end_date'] = null;
        }

        if ($request->has('search')) {
            $search = $request['search'];
            $queryParams['search'] = $search;
        }

        if ($request->has('booking_status')) {
            $bookingStatus = $request['booking_status'];
            $queryParams['booking_status'] = $bookingStatus;
        } else {
            $queryParams['booking_status'] = 'pending';
        }

        $maxBookingAmount = (business_config('max_booking_amount', 'booking_setup'))->live_values;

        $bookings = $this->booking->with(['customer', 'provider', 'booking_offline_payments.activeAttempt.receipt'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    foreach ($keys as $key) {
                        $query->orWhere('readable_id', 'LIKE', '%' . $key . '%')
                            ->orWhereHas('customer', function ($q) use ($key) {
                                $q->where('first_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('last_name', 'LIKE', '%' . $key . '%');
                            });
                    }
                });
            })
            ->where('payment_method', 'offline_payment')
            ->when(in_array($request->query('review_status'), ['pending', 'approved', 'denied'], true), fn ($query) => $query->whereHas('booking_offline_payments', fn ($q) => $q->current()->where('payment_status', $request->query('review_status'))))
            ->when($request->has('zone_ids'), function ($query) use ($request) {
                $query->whereIn('zone_id', $request['zone_ids']);
            })->when($queryParams['start_date'] != null && $queryParams['end_date'] != null, function ($query) use ($request) {
                if ($request['start_date'] == $request['end_date']) {
                    $query->whereDate('created_at', Carbon::parse($request['start_date'])->startOfDay());
                } else {
                    $query->whereBetween('created_at', [Carbon::parse($request['start_date'])->startOfDay(), Carbon::parse($request['end_date'])->endOfDay()]);
                }
            })->when($request->has('service_ids'), function ($query) use ($request) {
                $query->filterByServiceIds($request['service_ids']);
            })
            ->latest()->paginate(pagination_limit())->appends($queryParams);

        $zones = $this->zone->select('id', 'name')->withoutGlobalScope('translate')->get();
        $serviceCatalog = Service::withoutGlobalScopes()->select('id', 'name')->orderBy('name')->get();


        return view('bookingmodule::admin.booking.offline-payment-list', compact('bookings', 'zones', 'serviceCatalog', 'queryParams', 'filterCounter'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable|RedirectResponse
     * @throws AuthorizationException
     */
    public function details($id, Request $request): Renderable|RedirectResponse
    {
        if (nirab_branch_mode()) {
            $branchSubject = \Modules\BookingModule\Entities\Booking::withoutGlobalScopes()->findOrFail($id);
            app(\App\Services\OperationalAccess::class)->authorizeBranch($request->user(), 'analytics.read', $branchSubject->provider_id);
        }

        $this->authorize('booking_view');
        Validator::make($request->all(), [
            'web_page' => 'required|in:details,status',
        ]);
        $webPage = $request->has('web_page') ? $request['web_page'] : 'business_setup';

        if ($request->web_page == 'details') {

            $booking = $this->booking->with(['zone' , 'detail.service' => function ($query) {
                $query->withTrashed();
            }, 'detail.variation', 'customer', 'provider', 'serviceman', 'status_histories.user', 'booking_details_amounts'])
                ->find($id);

            $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

            $servicemen = $this->serviceman->with(['user'])->withCount('bookings')
                ->where('provider_id', $booking?->provider_id)
                ->whereHas('user', function ($query) {
                    $query->ofStatus(1);
                })
                ->latest()
                ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

            $customerAddress = $this->userAddress->find($booking['service_address_id']);
            $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

            $allProviders = $this->provider
                ->when($request->has('search'), function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    return $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhere('company_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_email', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_email', 'LIKE', '%' . $key . '%');
                        }
                    });
                })
                ->tap(fn ($query) => app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->constrainBranches($query, $booking))
                ->where('zone_id', $booking->zone_id)
                ->withCount('bookings', 'reviews')
                ->when(!nirab_branch_mode() && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values, function ($query) {
                    $query->where('is_suspended', 0);
                })
                ->where('service_availability', 1)
                ->withCount('reviews')
                ->ofApproval(1)->ofStatus(1)
                ->whereNot('id', $booking->provider_id)
                ->get();

            $providers = [];

            foreach ($allProviders as $provider) {
                $serviceLocation = getProviderSettings(providerId: $provider->id, key: 'service_location', type: 'provider_config');

                if (in_array($booking->service_location, $serviceLocation)) {
                    $providers[] = $provider;
                }
            }

            $currentlyAssignProvider = $booking->provider_id
                ? $this->provider->withCount('bookings', 'reviews')->find($booking->provider_id)
                : null;

            $sort_by = 'default';
            $zoneCenter = Zone::selectRaw("*,ST_AsText(ST_Centroid(`coordinates`)) as center")->withoutGlobalScope('translate')->find($booking->zone_id);

            $currentZone = [];
            $centerLat = [];
            $centerLng = [];
            $area = [];

            if (isset($zoneCenter)) {
                $currentZone = format_coordinates(json_decode($zoneCenter->coordinates[0]->toJson(), true));
                $centerLat = trim(explode(' ', $zoneCenter->center)[1], 'POINT()');
                $centerLng = trim(explode(' ', $zoneCenter->center)[0], 'POINT()');

                $area = json_decode($zoneCenter->coordinates[0]->toJson(), true);
            }

            return view('bookingmodule::admin.booking.details', compact('zoneCenter', 'currentZone', 'centerLat', 'centerLng', 'area', 'booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones', 'providers', 'sort_by', 'currentlyAssignProvider'));
        } elseif ($request->web_page == 'status') {
            $booking = $this->booking->with(['detail.service', 'customer', 'provider', 'service_address', 'serviceman.user', 'service_address', 'status_histories.user'])->find($id);

            $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

            $servicemen = $this->serviceman->with(['user'])->withCount('bookings')
                ->where('provider_id', $booking?->provider_id)
                ->whereHas('user', function ($query) {
                    $query->ofStatus(1);
                })
                ->latest()
                ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();
            $customerAddress = $this->userAddress->find($booking['service_address_id']);
            $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

            $allProviders = $this->provider
                ->when($request->has('search'), function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    return $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhere('company_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_email', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_email', 'LIKE', '%' . $key . '%');
                        }
                    });
                })
                ->tap(fn ($query) => app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->constrainBranches($query, $booking))
                ->where('zone_id', $booking->zone_id)
                ->withCount('bookings', 'reviews')
                ->ofApproval(1)->ofStatus(1)
                ->whereNot('id', $booking->provider_id)
                ->get();

            $providers = [];

            foreach ($allProviders as $provider) {
                $serviceLocation = getProviderSettings(providerId: $provider->id, key: 'service_location', type: 'provider_config');

                if (in_array($booking->service_location, $serviceLocation)) {
                    $providers[] = $provider;
                }
            }

            $currentlyAssignProvider = $booking->provider_id
                ? $this->provider->withCount('bookings', 'reviews')->find($booking->provider_id)
                : null;

            $sort_by = 'default';
            return view('bookingmodule::admin.booking.status', compact('booking', 'webPage', 'servicemen', 'customerAddress', 'services', 'providers', 'zones', 'sort_by', 'currentlyAssignProvider'));
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
    public function repeatDetails($id, Request $request): Renderable|RedirectResponse
    {
        $this->authorize('booking_view');
        Validator::make($request->all(), [
            'web_page' => 'required|in:details,service_log',
        ]);
        $webPage = $request->has('web_page') ? $request['web_page'] : 'business_setup';

        $booking = $this->booking->with(['repeat.detail.service','repeat.scheduleHistories','repeat.repeatHistories', 'detail.service' => function ($query) {
            $query->withTrashed();
        }, 'detail.variation', 'customer', 'provider',
            'serviceman', 'status_histories.user'])
            ->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        $servicemen = $this->serviceman->with(['user'])->withCount('bookings')
            ->where('provider_id', $booking?->provider_id)
            ->whereHas('user', function ($query) {
                $query->ofStatus(1);
            })
            ->latest()
            ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

        $customerAddress = $this->userAddress->find($booking['service_address_id']);
        $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

        $providers = $this->provider
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search']);
                return $query->where(function ($query) use ($keys) {
                    foreach ($keys as $key) {
                        $query->orWhere('company_phone', 'LIKE', '%' . $key . '%')
                            ->orWhere('company_email', 'LIKE', '%' . $key . '%')
                            ->orWhere('company_name', 'LIKE', '%' . $key . '%')
                            ->orWhere('contact_person_name', 'LIKE', '%' . $key . '%')
                            ->orWhere('contact_person_phone', 'LIKE', '%' . $key . '%')
                            ->orWhere('contact_person_email', 'LIKE', '%' . $key . '%');
                    }
                });
            })
            ->tap(fn ($query) => app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->constrainBranches($query, $booking))
            ->where('zone_id', $booking->zone_id)
            ->withCount('bookings', 'reviews')
            ->when(!nirab_branch_mode() && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values, function ($query) {
                $query->where('is_suspended', 0);
            })
            ->where('service_availability', 1)
            ->withCount('reviews')
            ->ofApproval(1)->ofStatus(1)->get();

        $sort_by = 'default';
        $id = "325778a8-53bd-4de5-a6bb-826f62edf603";
        $zoneCenter = Zone::selectRaw("*,ST_AsText(ST_Centroid(`coordinates`)) as center")->withoutGlobalScope('translate')->find($id);

        $currentZone = [];
        $centerLat = [];
        $centerLng = [];
        $area = [];

        if (isset($zoneCenter)) {
            $currentZone = format_coordinates(json_decode($zoneCenter->coordinates[0]->toJson(), true));
            $centerLat = trim(explode(' ', $zoneCenter->center)[1], 'POINT()');
            $centerLng = trim(explode(' ', $zoneCenter->center)[0], 'POINT()');

            $area = json_decode($zoneCenter->coordinates[0]->toJson(), true);
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
            return view('bookingmodule::admin.booking.repeat-booking-details', compact('zoneCenter', 'currentZone', 'centerLat', 'centerLng', 'area', 'booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones', 'providers', 'sort_by'));

        }elseif ($webPage == 'service_log'){
            return view('bookingmodule::admin.booking.service-log', compact('zoneCenter', 'currentZone', 'centerLat', 'centerLng', 'area', 'booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones', 'providers', 'sort_by'));

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
        if (nirab_branch_mode()) {
            $branchSubject = \Modules\BookingModule\Entities\BookingRepeat::withoutGlobalScopes()->findOrFail($id);
            app(\App\Services\OperationalAccess::class)->authorizeBranch($request->user(), 'analytics.read', $branchSubject->provider_id);
        }

        $this->authorize('booking_view');
        $webPage = \App\Services\BranchWorkspaceNavigation::detailPage($request);

        $booking = $this->bookingRepeat->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'detail.service', 'scheduleHistories.user', 'statusHistories.user', 'booking.service_address', 'booking.customer', 'booking.provider', 'serviceman.user'])
            ->find($id);

        if (!$booking) {
            Toastr::error(translate('Booking not found'));
            return back();
        }

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking?->booking?->service_address;
        unset($booking->service_address_location);

        $servicemen = $this->serviceman->with(['user'])->withCount('bookings')
            ->where('provider_id', $booking?->provider_id)
            ->whereHas('user', function ($query) {
                $query->ofStatus(1);
            })
            ->latest()
            ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

        $customerAddress = $this->userAddress->find($booking['service_address_id']);
        $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

        $providers = $this->provider
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search']);
                return $query->where(function ($query) use ($keys) {
                    foreach ($keys as $key) {
                        $query->orWhere('company_phone', 'LIKE', '%' . $key . '%')
                            ->orWhere('company_email', 'LIKE', '%' . $key . '%')
                            ->orWhere('company_name', 'LIKE', '%' . $key . '%')
                            ->orWhere('contact_person_name', 'LIKE', '%' . $key . '%')
                            ->orWhere('contact_person_phone', 'LIKE', '%' . $key . '%')
                            ->orWhere('contact_person_email', 'LIKE', '%' . $key . '%');
                    }
                });
            })
            ->tap(fn ($query) => app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->constrainBranches($query, $booking))
            ->where('zone_id', $booking->booking->zone_id)
            ->withCount('bookings', 'reviews')
            ->when(!nirab_branch_mode() && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values, function ($query) {
                $query->where('is_suspended', 0);
            })
            ->where('service_availability', 1)
            ->withCount('reviews')
            ->ofApproval(1)->ofStatus(1)->get();

        $sort_by = 'default';
        $id = "325778a8-53bd-4de5-a6bb-826f62edf603";
        $zoneCenter = Zone::selectRaw("*,ST_AsText(ST_Centroid(`coordinates`)) as center")->withoutGlobalScope('translate')->find($id);

        $currentZone = [];
        $centerLat = [];
        $centerLng = [];
        $area = [];

        if (isset($zoneCenter)) {
            $currentZone = format_coordinates(json_decode($zoneCenter->coordinates[0]->toJson(), true));
            $centerLat = trim(explode(' ', $zoneCenter->center)[1], 'POINT()');
            $centerLng = trim(explode(' ', $zoneCenter->center)[0], 'POINT()');

            $area = json_decode($zoneCenter->coordinates[0]->toJson(), true);
        }
        if ($webPage == 'details') {
            return view('bookingmodule::admin.booking.rebooking-ongoing', compact('zoneCenter', 'currentZone', 'centerLat', 'centerLng', 'area', 'booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones', 'providers', 'sort_by'));

        }elseif ($webPage == 'status') {
            return view('bookingmodule::admin.booking.repeat-status', compact('booking', 'webPage', 'servicemen', 'customerAddress', 'services', 'providers', 'zones', 'sort_by'));
        }
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function statusUpdate($bookingId, Request $request): JsonResponse
    {
        $this->authorize('booking_can_manage_status');

        $validated = $request->validate([
            'booking_status' => 'required|in:' . implode(',', array_column(BOOKING_STATUSES, 'key')),
        ]);

        $booking = $this->booking->find($bookingId);
        $repeatBooking = $this->bookingRepeat->find($bookingId);

        if ($booking) {
            if($booking->booking_status == 'ongoing' && $request['booking_status'] == 'canceled'){
                return response()->json(response_formatter(BOOKING_ALREADY_ONGOING, errors: [['error_code' => 422, 'message' => BOOKING_ALREADY_ONGOING['message']]]), 422);
            }
            return $this->updateBookingStatus($booking, $validated['booking_status'], $request);
        }

        if ($repeatBooking) {
            return $this->updateRepeatBookingStatus($repeatBooking, $validated['booking_status'], $request);
        }

        return response()->json(response_formatter(DEFAULT_204), 204);
    }

    private function updateBookingStatus($booking, string $status, Request $request): JsonResponse
    {
        $booking->booking_status = $status;

        if ($booking->isDirty('booking_status')) {
            DB::transaction(function () use ($booking, $status, $request) {
                if ($booking->repeat) {
                    foreach ($booking->repeat->whereIn('booking_status', ['pending', 'accepted', 'ongoing']) as $repeat) {
                        $repeat->update([
                            'provider_id' => $request->provider_id,
                            'booking_status' => $status,
                            'serviceman_id' => null,
                        ]);

                        $this->logBookingStatusHistory($repeat->id, $status, $request->user()->id, $booking->id);
                    }

                    if ($status == 'canceled' && $booking->repeat->contains('booking_status', 'completed')) {
                        $booking->booking_status = 'completed';
                    }
                }

                $booking->save();
                $this->logBookingStatusHistory(null, $status, $request->user()->id, $booking->id);
            });

            return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
        }

        return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
    }

    private function updateRepeatBookingStatus($repeatBooking, string $status, Request $request): JsonResponse
    {
        $repeatBooking->booking_status = $status;

        if ($status == 'canceled' && $repeatBooking->extra_fee > 0){

            $booking = $this->booking->where('id', $repeatBooking->booking_id)->first();
            $sortedRepeats = $booking->repeat->sortBy(function ($repeat) {
                $parts = explode('-', $repeat->readable_id);
                $suffix = end($parts);
                return $this->readableIdToNumber($suffix);
            });

            $booking['repeats'] = $sortedRepeats->values()->toArray();

            $nextService = collect($booking['repeats'])
                ->where('booking_status', 'ongoing')
                ->skip(1)
                ->first();

            if (!$nextService) {
                $nextService = collect($booking['repeats'])
                    ->where('booking_status', 'accepted')
                    ->skip(1)
                    ->first();
            }

            if (!$nextService) {
                $nextService = collect($booking['repeats'])
                    ->where('booking_status', 'pending')
                    ->skip(1)
                    ->first();
            }

            if (isset($nextService)) {
                $nextServiceId = $nextService['id'];
                $nextServiceFee = $this->bookingRepeat->where('id', $nextServiceId)->first();
                $nextServiceFee->extra_fee = $repeatBooking->extra_fee;
                $nextServiceFee->total_booking_amount += $repeatBooking->extra_fee;
                $nextServiceFee->save();
            }

            $repeatBooking->total_booking_amount -= $repeatBooking->extra_fee;
            $repeatBooking->extra_fee = 0;
        }

        if ($repeatBooking->isDirty('booking_status')) {
            DB::transaction(function () use ($repeatBooking, $status, $request) {

                $repeatBooking->save();
                $this->logBookingStatusHistory($repeatBooking->id, $status, $request->user()->id, $repeatBooking->booking_id);

                $relatedRepeats = $this->bookingRepeat->where('booking_id', $repeatBooking->booking_id)->get();
                if ($relatedRepeats->every(fn($repeat) => !in_array($repeat->booking_status, ['pending', 'accepted', 'ongoing']))) {
                    $repeatBooking->booking->update(['booking_status' => 'completed']);
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

    private function logBookingStatusHistory(?string $repeatId, string $status, string $changedBy, string $bookingId): void
    {
        $this->bookingStatusHistory->create([
            'booking_id' => $bookingId,
            'booking_repeat_id' => $repeatId,
            'changed_by' => $changedBy,
            'booking_status' => $status,
        ]);
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
        $this->authorize('booking_can_manage_status');

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
                DB::transaction(function () use ($bookingStatusHistory, $repeatBooking) {
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

    public function verificationUpdate($bookingId, Request $request): JsonResponse
    {
        $this->authorize('booking_can_manage_status');

        $booking = $this->booking->where('id', $bookingId)->first();
        if (isset($booking)) {
            $booking->is_verified = 1;
            $booking->save();

            if (isset($booking->provider_id)) {
                $fcmToken = Provider::with('owner')->whereId($booking->provider_id)->first()->owner->fcm_token ?? null;
                $language_key = $this->provider->with('owner')->whereId($booking->provider_id)->first()->owner?->current_language_key;
                if (!is_null($fcmToken) && (nirab_branch_mode() || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values || $booking?->provider?->is_suspended == 0)) {
                    $title = get_push_notification_message('new_service_request_arrived', 'provider_notification', $language_key);
                    device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                }
            } else {
                $provider_ids = array_filter([$booking->provider_id]);
                $providers = Provider::with('owner')->whereIn('id', $provider_ids)->where('zone_id', $booking->zone_id)->get();
                foreach ($providers as $provider) {
                    $fcmToken = $provider->owner->fcm_token ?? null;
                    $title = get_push_notification_message('new_service_request_arrived', 'provider_notification', $provider?->owner?->current_language_key);
                    if (!is_null($fcmToken) && (nirab_branch_mode() || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values || $provider?->is_suspended == 0)) device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                }
            }
            return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
    }


    /**
     * @param $bookingId
     * @param Request $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function verificationStatus($bookingId, Request $request): RedirectResponse
    {

        $this->authorize('booking_can_manage_status');

        $request->validate([
            'status' => 'required|in:approve,deny,cancel',
            'booking_deny_note' => 'required_if:status,deny|string|nullable'
        ]);

        $booking = $this->booking->where('id', $bookingId)->first();
        if (isset($booking) && $request->status == 'deny') {
            $booking->is_verified = 2;
            $booking->save();

            $additionalInfo = new $this->bookingAdditionalInformation;
            $additionalInfo->booking_id = $booking->id;
            $additionalInfo->key = 'booking_deny_note';
            $additionalInfo->value = $request->booking_deny_note;
            $additionalInfo->save();

            Toastr::success(translate(DEFAULT_STORE_200['key']));
            return back();
        } elseif (isset($booking) && $request->status == 'approve') {
            $booking->is_verified = 1;
            $booking->save();

            if (isset($booking->provider_id)) {
                $fcmToken = Provider::with('owner')->whereId($booking->provider_id)->first()->owner->fcm_token ?? null;
                $language_key = $this->provider->with('owner')->whereId($booking->provider_id)->first()->owner?->current_language_key;
                if (!is_null($fcmToken) && (nirab_branch_mode() || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values || $booking?->provider?->is_suspended == 0)) {
                    $title = get_push_notification_message('new_service_request_arrived', 'provider_notification', $language_key);
                    device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                }
            } else {
                $provider_ids = array_filter([$booking->provider_id]);
                $providers = Provider::with('owner')->whereIn('id', $provider_ids)->where('zone_id', $booking->zone_id)->get();
                foreach ($providers as $provider) {
                    $fcmToken = $provider->owner->fcm_token ?? null;
                    $title = get_push_notification_message('booking_accepted', 'provider_notification', $provider?->owner?->current_language_key);
                    if (!is_null($fcmToken) && (nirab_branch_mode() || !business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values || $provider?->is_suspended == 0)) device_notification($fcmToken, $title, null, null, $booking->id, 'booking');
                }
            }

            Toastr::success(translate(DEFAULT_STATUS_UPDATE_200['key']));
            return back();
        } elseif (isset($booking) && $request->status == 'cancel') {
            $booking->booking_status = 'canceled';
            $booking->is_verified = 3;

            $bookingStatusHistory = $this->bookingStatusHistory;
            $bookingStatusHistory->booking_id = $bookingId;
            $bookingStatusHistory->changed_by = $request->user()->id;
            $bookingStatusHistory->booking_status = 'canceled';

            if ($booking->isDirty('booking_status')) {
                DB::transaction(function () use ($bookingStatusHistory, $booking) {
                    $booking->save();
                    $bookingStatusHistory->save();
                });

                Toastr::success(translate(DEFAULT_STATUS_UPDATE_200['key']));
                return back();
            }
        }

        Toastr::success(translate(DEFAULT_404['key']));
        return back();
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function paymentUpdate($bookingId, Request $request): JsonResponse
    {
        if (nirab_branch_mode()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['payment_status' => [__('bookingmodule::execution.two_stage_approval_required')]]);
        }
        $this->authorize('booking_can_manage_status');

        Validator::make($request->all(), [
            'payment_status' => 'required|in:1,0',
        ]);

        $booking = $this->booking->where('id', $bookingId)->first();

        $repeatBooking = $this->bookingRepeat->where('id', $bookingId)->first();
        if (isset($booking)) {
            $booking->is_paid = $request->payment_status == '1' ? 1 : 0;

            if ($booking->isDirty('is_paid')) {
                $booking->save();
                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }
            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        if (isset($repeatBooking)) {
            $repeatBooking->is_paid = $request->payment_status == '1' ? 1 : 0;

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
     * @throws AuthorizationException
     */
    public function scheduleUpdate($bookingId, Request $request): JsonResponse
    {
        $this->authorize('booking_can_manage_status');

        Validator::make($request->all(), [
            'service_schedule' => 'required',
        ]);

        $booking = $this->booking->where('id', $bookingId)->first();
        $bookingRepeat = $this->bookingRepeat->where('id', $bookingId)->first();

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
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function upComingBookingScheduleUpdate($bookingId, Request $request): RedirectResponse
    {
        $this->authorize('booking_can_manage_status');

        Validator::make($request->all(), [
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
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function providerUpdate($bookingId, Request $request): JsonResponse
    {
        $this->authorize('booking_can_manage_status');

        Validator::make($request->all(), [
            'provider_id' => 'required|uuid',
        ]);

        return DB::transaction(function () use ($bookingId, $request) {
        $booking = $this->booking->where('id', $bookingId)->lockForUpdate()->first();

        if (isset($booking)) {
            $booking->provider_id = $request->provider_id;

            if ($booking->isDirty('provider_id')) {
                $booking->booking_status = 'accepted';
                $booking->serviceman_id = null;
                $booking->assigned_by = 'admin';

                if (!is_null($booking->repeat)) {
                    foreach ($booking->repeat->whereIn('booking_status', ['pending', 'accepted', 'ongoing']) as $bookingRepeat) {
                        $bookingRepeat->provider_id = $request->provider_id;
                        $bookingRepeat->booking_status = 'accepted';
                        $bookingRepeat->serviceman_id = null;
                        $bookingRepeat->save();
                    }
                }

                $booking->save();
                return response()->json(response_formatter(DEFAULT_STATUS_UPDATE_200), 200);
            }

            return response()->json(response_formatter(NO_CHANGES_FOUND), 200);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
        }, 3);
    }

    /**
     * Display a listing of the resource.
     * @param $bookingId
     * @param Request $request
     * @return JsonResponse
     * @throws AuthorizationException
     */
    public function servicemanUpdate(Request $request): JsonResponse
    {
        $this->authorize('booking_can_manage_status');

        if (nirab_branch_mode()) {
            $request->validate(['booking_id' => 'required|uuid', 'serviceman_id' => 'nullable|uuid|required_without:team_id', 'team_id' => 'nullable|uuid|required_without:serviceman_id']);
            $subject = $this->booking->find($request->booking_id) ?? $this->bookingRepeat->with('booking')->findOrFail($request->booking_id);
            $parent = $subject instanceof BookingRepeat ? $subject->booking : $subject;
            app(\App\Services\OperationalAccess::class)->authorizeBranch($request->user(), 'dispatch.manage', $parent->provider_id);
            $booking = app(\Modules\BookingModule\Services\BookingAssignmentService::class)->assign($parent->id, $request->serviceman_id, $request->team_id, 'admin', $request->user()->id);
            $servicemen = $this->serviceman->with('user')->withCount('bookings')->where('provider_id', $booking->provider_id)->get();
            $search = $request->search;
            return response()->json(['view' => view('bookingmodule::admin.booking.partials.details.serviceman-info-modal-data', compact('servicemen', 'booking', 'search'))->render()]);
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
            $servicemen = $this->serviceman->withCount('bookings')
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
                'view' => view('bookingmodule::admin.booking.partials.details.serviceman-info-modal-data', compact('servicemen', 'booking', 'search'))->render()
            ]);
        }
        if (isset($bookingRepeat)) {

            $bookingRepeat->serviceman_id = $request->serviceman_id;
            $bookingRepeat->save();

            if ($bookingRepeat->booking) {
                $bookingRepeat->booking->serviceman_id = $request->serviceman_id;
                $bookingRepeat->booking->save();
            }

            $search = $request->search;
            $servicemen = $this->serviceman->withCount('bookings')
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
                'view' => view('bookingmodule::admin.booking.partials.details.serviceman-info-modal-data', compact('servicemen', 'booking', 'search'))->render()
            ]);
        }
        return response()->json(response_formatter(DEFAULT_204), 200);
    }

    /**
     * Display a listing of the resource.
     * @param $service_address_id
     * @param Request $request
     * @return RedirectResponse
     * @throws AuthorizationException
     */
    public function serviceAddressUpdate($service_address_id, Request $request): RedirectResponse
    {
        $this->authorize('booking_edit');

        Validator::make($request->all(), [
            'city' => 'required',
            'street' => 'required',
            'zip_code' => 'required',
            'country' => 'required',
            'address' => 'required',
            'contact_person_name' => 'required',
            'contact_person_number' => 'required',
            'address_label' => 'required',
            'zone_id' => 'required|uuid',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $point = new Point($request->latitude, $request->longitude);
        $zoneExists = $this->zone->where('id', $request->zone_id)->whereContains('coordinates', $point)->exists();
        if (!$zoneExists) {
            Toastr::error(translate('coordinates_must_be_within_the_selected_zone'));
            return back()->withInput();
        }

        $userAddress = $this->userAddress->find($service_address_id);
        $userAddress->city = $request['city'];
        $userAddress->street = $request['street'];
        $userAddress->zip_code = $request['zip_code'];
        $userAddress->country = $request['country'];
        $userAddress->address = $request['address'];
        $userAddress->contact_person_name = $request['contact_person_name'];
        $userAddress->contact_person_number = $request['contact_person_number'];
        $userAddress->address_label = $request['address_label'];
        $userAddress->zone_id = $request['zone_id'];
        $userAddress->lat = $request['latitude'];
        $userAddress->lon = $request['longitude'];
        $userAddress->save();

        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }

    /**
     * @param Request $request
     * @return string|StreamedResponse
     * @throws AuthorizationException
     * @throws \OpenSpout\Common\Exception\IOException
     * @throws \OpenSpout\Common\Exception\InvalidArgumentException
     * @throws \OpenSpout\Common\Exception\UnsupportedTypeException
     * @throws \OpenSpout\Writer\Exception\WriterNotOpenedException
     */
    public function download(Request $request): string|StreamedResponse
    {
        if (nirab_branch_mode() && $request->filled('provider_id')) {
            $request->validate(['provider_id' => 'uuid']);
            app(\App\Services\OperationalAccess::class)->authorizeBranch($request->user(), 'analytics.read', $request->provider_id);
        }
        $this->authorize('booking_view');
        $request->validate([
            'booking_status' => 'in:' . implode(',', array_column(BOOKING_STATUSES, 'key')) . ',all',
        ]);

        $bookingStatus = $request->input('booking_status', 'pending');

        $maxBookingAmount = (business_config('max_booking_amount', 'booking_setup'))->live_values;
        $items = $this->booking
            ->when($request->provider_id, fn ($q, $id) => $q->where('provider_id', $id))
            ->with(['customer', 'provider', 'repeat'])
            ->search($request['search'], ['readable_id'])
            ->when($bookingStatus != 'all', function ($query) use ($bookingStatus, $maxBookingAmount, $request) {
                $query->when($bookingStatus == 'pending', function ($query) use ($maxBookingAmount) {
                    $query->adminPendingBookings($maxBookingAmount);
                })->when($bookingStatus == 'accepted', function ($query) use ($maxBookingAmount) {
                    $query->adminAcceptedBookings($maxBookingAmount);
                })->ofBookingStatus($request['booking_status']);
            })
            ->when($request['service_type'] != 'all', function ($query) use ($request) {
                return $query->ofRepeatBookingStatus($request['service_type'] === 'repeat' ? 1 : ($request['service_type'] === 'regular' ? 0 : null));
            })
            ->when($request['provider_assigned'] == 'assigned', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNotNull('provider_id')
                        ->orWhereHas('repeat', function ($q) {
                            $q->whereNotNull('provider_id');
                        });
                });
            })
            ->when($request['provider_assigned'] == 'unassigned', function ($query) {
                $query->where(function ($subQuery) {
                    $subQuery->whereNull('provider_id')
                        ->orWhereDoesntHave('repeat', function ($q) {
                            $q->whereNotNull('provider_id');
                        });
                });
            })
            ->filterByZoneIds($request['zone_ids'])

            ->filterByServiceIds($request['service_ids'])
            ->filterByDateRange($request['start_date'], $request['end_date'])
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
                translate('Booking_Date') => $item->created_at ? \Carbon\Carbon::parse($item->created_at)->format('d-M-Y h:i A') : '',
                translate('Where_Service_will_be_Provided') => $item->service_location == 'provider' ? translate('Provider Location') : translate('Customer Location'),
                translate('Schedule_Date') => $scheduleSource ? \Carbon\Carbon::parse($scheduleSource)->format('d-M-Y h:i A') : '',
                translate('Customer_Name') => $item->customer ? trim(($item->customer->first_name ?? '') . ' ' . ($item->customer->last_name ?? '')) : ($serviceAddress?->contact_person_name ?? ''),
                translate('Customer_Phone') => $item->customer ? $item->customer->phone : ($serviceAddress?->contact_person_number ?? ''),
                translate('Provider_Name') => $item->provider?->company_name ?? translate('unassigned'),
                translate('Provider_Phone') => $item->provider?->company_phone ?? '',
                translate('Total_Amount') => with_currency_symbol($item->total_booking_amount),
                translate('Payment_Status') => $item->is_paid ? translate('paid') : translate('unpaid'),
            ];
        });

        $fileName = 'bookings_' . $bookingStatus . '_list_' . date('Y_m_d') . '.xlsx';
        return (new FastExcel($formatted))->download($fileName);
    }

    /**
     * @param Request $request
     * @return string|StreamedResponse
     * @throws AuthorizationException
     */
    public function downloadOfflinePayment(Request $request): string|StreamedResponse
    {
        $this->authorize('booking_view');

        $bookings = $this->booking->with(['customer', 'provider', 'booking_offline_payments'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    foreach ($keys as $key) {
                        $query->orWhere('readable_id', 'LIKE', '%' . $key . '%')
                            ->orWhereHas('customer', function ($q) use ($key) {
                                $q->where('first_name', 'LIKE', '%' . $key . '%')
                                    ->orWhere('last_name', 'LIKE', '%' . $key . '%');
                            });
                    }
                });
            })
            ->where('payment_method', 'offline_payment')
            ->when(in_array($request->query('review_status'), ['pending', 'approved', 'denied'], true), fn ($query) => $query->whereHas('booking_offline_payments', fn ($q) => $q->current()->where('payment_status', $request->query('review_status'))))
            ->when($request->has('zone_ids'), function ($query) use ($request) {
                $query->whereIn('zone_id', $request['zone_ids']);
            })
            ->when($request->filled('start_date') && $request->filled('end_date'), function ($query) use ($request) {
                if ($request['start_date'] == $request['end_date']) {
                    $query->whereDate('created_at', Carbon::parse($request['start_date'])->startOfDay());
                } else {
                    $query->whereBetween('created_at', [Carbon::parse($request['start_date'])->startOfDay(), Carbon::parse($request['end_date'])->endOfDay()]);
                }
            })

            ->when($request->has('service_ids'), function ($query) use ($request) {
                $query->filterByServiceIds($request['service_ids']);
            })
            ->latest()->get();

        $formatted = $bookings->map(function ($booking, $key) {
            $serviceAddress = is_string($booking->service_address) ? json_decode($booking->service_address) : $booking->service_address;

            return [
                translate('SL') => $key + 1,
                translate('Booking_ID') => $booking->readable_id,
                translate('Where_Service_will_be_Provided') => $booking->service_location == 'provider' ? translate('Provider Location') : translate('Customer Location'),
                translate('Customer_Name') => $booking->customer ? trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? '')) : ($serviceAddress?->contact_person_name ?? ''),
                translate('Customer_Phone') => $booking->customer ? $booking->customer->phone : ($serviceAddress?->contact_person_number ?? ''),
                translate('Total_Amount') => with_currency_symbol($booking->total_booking_amount),
                translate('Payment_Status') => $booking->is_paid ? translate('paid') : translate('unpaid'),
                translate('Schedule_Date') => $booking->service_schedule ? \Carbon\Carbon::parse($booking->service_schedule)->format('d-M-Y h:i A') : '',
                translate('Booking_Date') => $booking->created_at ? \Carbon\Carbon::parse($booking->created_at)->format('d-M-Y h:i A') : '',
                translate('Branch') => $booking->provider?->company_name ?? '',
                translate('Payment_Method') => $booking->booking_offline_payments->first()?->method_name ?? '',
                translate('Status') => __('offline_transfer.'.($booking->booking_offline_payments->first()?->payment_status ?? 'incomplete')),
            ];
        });

        $fileName = 'offline_payment_bookings_' . date('Y_m_d') . '.xlsx';
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

        return view('bookingmodule::admin.booking.invoice', compact('booking'));
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

        return view('bookingmodule::admin.booking.fullbooking-invoice', compact('booking'));
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

        return view('bookingmodule::admin.booking.fullbooking-single-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function customerFullBookingInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user','repeat'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::admin.booking.fullbooking-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function customerFullBookingSingleInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->bookingRepeat->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'booking', 'provider', 'serviceman'])->find($id);

        $booking->booking->service_address = $booking->booking->service_address_location != null ? json_decode($booking->booking->service_address_location) : $booking->booking->service_address;

        return view('bookingmodule::admin.booking.fullbooking-single-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function providerFullBookingInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user','repeat'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::admin.booking.fullbooking-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function providerFullBookingSingleInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->bookingRepeat->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'booking', 'provider', 'serviceman'])->find($id);

        $booking->booking->service_address = $booking->booking->service_address_location != null ? json_decode($booking->booking->service_address_location) : $booking->booking->service_address;

        return view('bookingmodule::admin.booking.fullbooking-single-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param $lang
     * @return Renderable
     */
    public function servicemanFullBookingSingleInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->bookingRepeat->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'booking', 'provider', 'serviceman'])->find($id);

        $booking->booking->service_address = $booking->booking->service_address_location != null ? json_decode($booking->booking->service_address_location) : $booking->booking->service_address;

        return view('bookingmodule::admin.booking.fullbooking-single-invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable
     */
    public function customerInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::admin.booking.invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable
     */
    public function providerInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::admin.booking.invoice', compact('booking'));
    }

    /**
     * Display a listing of the resource.
     * @param $id
     * @param Request $request
     * @return Renderable
     */
    public function servicemanInvoice($id, $lang): Renderable
    {
        App::setLocale($lang);
        $booking = $this->booking->with(['detail.service' => function ($query) {
            $query->withTrashed();
        }, 'customer', 'provider', 'serviceman', 'status_histories.user'])->find($id);

        $booking->service_address = $booking->service_address_location != null ? json_decode($booking->service_address_location) : $booking->service_address;

        return view('bookingmodule::admin.booking.invoice', compact('booking'));
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
     * @return RedirectResponse
     * @throws ValidationException|AuthorizationException
     */
    public function updateBookingService(Request $request): RedirectResponse
    {
        $this->authorize('booking_edit');
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
        $this->authorize('booking_edit');
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


    public function verifyOfflinePayment(Request $request)
    {
        $this->authorize('booking_can_manage_status');

        app(\Modules\BookingModule\Services\OfflineTransferService::class)->review($request);
        if ($request->expectsJson()) return response()->json(response_formatter(DEFAULT_UPDATE_200), 200);
        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }

    public function offlineReceipt(Request $request)
    {
        $this->authorize('booking_view');
        $request->validate(['booking_id' => 'required|uuid', 'receipt_id' => 'required|uuid']);
        $booking = Booking::findOrFail($request->query('booking_id'));
        $service = app(\Modules\BookingModule\Services\OfflineTransferService::class);
        return $service->download($service->receiptForBooking($booking, $request->query('receipt_id')));
    }

    public function reBookingDetails(Request $request, $id)
    {
        $this->authorize('booking_view');
        Validator::make($request->all(), [
            'web_page' => 'required|in:details,status',
        ]);
        $webPage = $request->has('web_page') ? $request['web_page'] : 'business_setup';

        if ($request->web_page == 'details') {

            $booking = $this->booking->with(['detail.service' => function ($query) {
                $query->withTrashed();
            }, 'detail.variation', 'customer', 'provider', 'service_address', 'serviceman', 'service_address', 'status_histories.user'])->find($id);

            $servicemen = $this->serviceman->with(['user'])->withCount('bookings')
                ->where('provider_id', $booking?->provider_id)
                ->whereHas('user', function ($query) {
                    $query->ofStatus(1);
                })
                ->latest()
                ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();

            $customerAddress = $this->userAddress->find($booking['service_address_id']);
            $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

            $providers = $this->provider
                ->when($request->has('search'), function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    return $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhere('company_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_email', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_email', 'LIKE', '%' . $key . '%');
                        }
                    });
                })
                ->tap(fn ($query) => app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->constrainBranches($query, $booking))
                ->where('zone_id', $booking->zone_id)
                ->withCount('bookings', 'reviews')
                ->when(!nirab_branch_mode() && business_config('suspend_on_exceed_cash_limit_provider', 'provider_config')->live_values, function ($query) {
                    $query->where('is_suspended', 0);
                })
                ->where('service_availability', 1)
                ->withCount('reviews')
                ->ofApproval(1)->ofStatus(1)->get();

            $sort_by = 'default';
            $id = "325778a8-53bd-4de5-a6bb-826f62edf603";
            $zoneCenter = Zone::selectRaw("*,ST_AsText(ST_Centroid(`coordinates`)) as center")->withoutGlobalScope('translate')->find($id);

            $currentZone = [];
            $centerLat = [];
            $centerLng = [];
            $area = [];

            if (isset($zoneCenter)) {
                $currentZone = format_coordinates(json_decode($zoneCenter->coordinates[0]->toJson(), true));
                $centerLat = trim(explode(' ', $zoneCenter->center)[1], 'POINT()');
                $centerLng = trim(explode(' ', $zoneCenter->center)[0], 'POINT()');

                $area = json_decode($zoneCenter->coordinates[0]->toJson(), true);
            }

            return view('bookingmodule::admin.booking.rebooking-details', compact('zoneCenter', 'currentZone', 'centerLat', 'centerLng', 'area', 'booking', 'servicemen', 'webPage', 'customerAddress', 'services', 'zones', 'providers', 'sort_by'));
        } elseif ($request->web_page == 'status') {
            $booking = $this->booking->with(['detail.service', 'customer', 'provider', 'service_address', 'serviceman.user', 'service_address', 'status_histories.user'])->find($id);
            $servicemen = $this->serviceman->with(['user'])->withCount('bookings')
                ->where('provider_id', $booking?->provider_id)
                ->whereHas('user', function ($query) {
                    $query->ofStatus(1);
                })
                ->latest()
                ->get();
            $services = app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->query($booking)->get();
            $customerAddress = $this->userAddress->find($booking['service_address_id']);
            $zones = Zone::ofStatus(1)->withoutGlobalScope('translate')->get();

            $providers = $this->provider
                ->when($request->has('search'), function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    return $query->where(function ($query) use ($keys) {
                        foreach ($keys as $key) {
                            $query->orWhere('company_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_email', 'LIKE', '%' . $key . '%')
                                ->orWhere('company_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_name', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_phone', 'LIKE', '%' . $key . '%')
                                ->orWhere('contact_person_email', 'LIKE', '%' . $key . '%');
                        }
                    });
                })
                ->tap(fn ($query) => app(\Modules\ServiceManagement\Services\BookingServiceCatalog::class)->constrainBranches($query, $booking))
                ->where('zone_id', $booking->zone_id)
                ->withCount('bookings', 'reviews')
                ->ofApproval(1)->ofStatus(1)->get();
            $sort_by = 'default';
            return view('bookingmodule::admin.booking.service-log', compact('booking', 'webPage', 'servicemen', 'customerAddress', 'services', 'providers', 'zones', 'sort_by'));
        }

        Toastr::success(translate(ACCESS_DENIED['key']));
        return back();
    }

    public function reBookingOngoing()
    {
        return view('bookingmodule::admin.booking.rebooking-ongoing');
    }

    public function switchPaymentMethod($bookingId, Request $request)
    {
        $this->authorize('booking_can_manage_status');

        $validated = $request->validate([
            'payment_method' => 'required'
        ]);

        $booking = $this->booking->find($bookingId);
        $booking->payment_method = $request->payment_method;
        $booking->is_verified = 1;
        $booking->save();

        return response()->json(response_formatter(PAYMENT_METHOD_UPDATE_200), 200);
    }

    public function changeServiceLocation($bookingId, Request $request)
    {
        $this->authorize('booking_can_manage_status');

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
        $this->authorize('booking_can_manage_status');

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

        Toastr::success(translate(DEFAULT_UPDATE_200['key']));
        return back();
    }

    public function downloadBookingVerificationList(Request $request)
    {
        $request->validate([
            'booking_status' => 'in:' . implode(',', array_column(BOOKING_STATUSES, 'key')) . ',all',
            'type' => 'in:pending,denied'
        ]);
        $request['booking_status'] = $request['booking_status'] ?? 'pending';

        $queryParams = [];
        $type = $request->type ?? 'pending';

        if ($request->has('zone_ids')) {
            $zoneIds = $request['zone_ids'];
            $queryParams['zone_ids'] = $zoneIds;
        }

        if ($request->has('service_ids')) {
            $serviceIds = $request['service_ids'];
            $queryParams['service_ids'] = $serviceIds;
        }

        if ($request->has('start_date')) {
            $startDate = $request['start_date'];
            $queryParams['start_date'] = $startDate;
        } else {
            $queryParams['start_date'] = null;
        }

        if ($request->has('end_date')) {
            $endDate = $request['end_date'];
            $queryParams['end_date'] = $endDate;
        } else {
            $queryParams['end_date'] = null;
        }

        if ($request->has('search')) {
            $search = $request['search'];
            $queryParams['search'] = $search;
        }

        $queryParams['type'] = $type;

        if ($request->has('booking_status')) {
            $bookingStatus = $request['booking_status'];
            $queryParams['booking_status'] = $bookingStatus;
        } else {
            $queryParams['booking_status'] = 'pending';
        }

        $maxBookingAmount = (business_config('max_booking_amount', 'booking_setup'))->live_values;

        $bookings = $this->booking->with(['customer', 'repeat'])
            ->when($request->has('search'), function ($query) use ($request) {
                $query->where(function ($query) use ($request) {
                    $keys = explode(' ', $request['search']);
                    foreach ($keys as $key) {
                        $query->orWhere('readable_id', 'LIKE', '%' . $key . '%');
                    }
                });
            })
            ->when($bookingStatus == 'pending', function ($query) use ($maxBookingAmount, $type) {
                $query->when($type == 'pending', function ($query) {
                    $query->where('is_verified', '0');
                })->when($type == 'denied', function ($query) {
                    $query->where('is_verified', '2');
                })
                    ->where('payment_method', 'cash_after_service')
                    ->Where('total_booking_amount', '>', $maxBookingAmount)
                    ->whereIn('booking_status', ['pending', 'accepted']);
            })
            ->when($request->has('zone_ids'), function ($query) use ($request) {
                $query->whereIn('zone_id', $request['zone_ids']);
            })->when($queryParams['start_date'] != null && $queryParams['end_date'] != null, function ($query) use ($request) {
                if ($request['start_date'] == $request['end_date']) {
                    $query->whereDate('created_at', Carbon::parse($request['start_date'])->startOfDay());
                } else {
                    $query->whereBetween('created_at', [Carbon::parse($request['start_date'])->startOfDay(), Carbon::parse($request['end_date'])->endOfDay()]);
                }
            })->when($request->has('service_ids'), function ($query) use ($request) {
                $query->filterByServiceIds($request['service_ids']);
            })
            ->latest()->get();

        foreach ($bookings as $booking) {
            if ($booking->repeat->isNotEmpty()) {
                $sortedRepeats = $booking->repeat->sortBy(function ($repeat) {
                    $parts = explode('-', $repeat->readable_id);
                    $suffix = end($parts);
                    return $this->readableIdToNumber($suffix);
                })->values();

                $booking['nextService'] = $sortedRepeats->firstWhere('booking_status', 'ongoing')
                    ?? $sortedRepeats->firstWhere('booking_status', 'accepted')
                    ?? $sortedRepeats->firstWhere('booking_status', 'pending');
            }
        }

        $formatted = $bookings->map(function ($booking, $key) {
            $serviceAddress = is_string($booking->service_address) ? json_decode($booking->service_address) : $booking->service_address;
            $scheduleSource = $booking->is_repeated ? $booking->nextService?->service_schedule : $booking->service_schedule;

            $verificationStatus = match ((string) $booking->is_verified) {
                '0' => translate('pending'),
                '1' => translate('verified'),
                '2' => translate('denied'),
                default => '',
            };

            return [
                translate('SL') => $key + 1,
                translate('Booking_ID') => $booking->readable_id,
                translate('Where_Service_will_be_Provided') => $booking->service_location == 'provider' ? translate('Provider Location') : translate('Customer Location'),
                translate('Customer_Name') => $booking->customer ? trim(($booking->customer->first_name ?? '') . ' ' . ($booking->customer->last_name ?? '')) : ($serviceAddress?->contact_person_name ?? ''),
                translate('Customer_Phone') => $booking->customer ? $booking->customer->phone : ($serviceAddress?->contact_person_number ?? ''),
                translate('Total_Amount') => with_currency_symbol($booking->total_booking_amount),
                translate('Payment_Status') => $booking->is_paid ? translate('paid') : translate('unpaid'),
                translate('Schedule_Date') => $scheduleSource ? \Carbon\Carbon::parse($scheduleSource)->format('d-M-Y h:i A') : '',
                translate('Booking_Date') => $booking->created_at ? \Carbon\Carbon::parse($booking->created_at)->format('d-M-Y h:i A') : '',
                translate('Status') => $verificationStatus,
            ];
        });

        $fileName = 'bookings_verification_list_' . date('Y_m_d') . '.xlsx';
        return (new FastExcel($formatted))->download($fileName);

    }

}
