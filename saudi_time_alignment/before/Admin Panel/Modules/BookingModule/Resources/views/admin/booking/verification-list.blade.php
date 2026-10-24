@extends('adminmodule::layouts.master')

@section('title',translate('Booking_List'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin-module/css/daterangepicker.css')}}"/>
@endpush

@section('content')
    <div class="filter-aside nirab-booking-filters">
        <div class="filter-aside__header d-flex justify-content-between align-items-center">
            <h3 class="filter-aside__title">{{translate('Filter_your_Booking')}}</h3>
            <button type="button" class="btn-close p-2 btn-close-white"></button>
        </div>
        <form action="{{ route('admin.booking.list.verification') }}" method="GET"
              enctype="multipart/form-data" id="filter-form">
            <input type="hidden" name="booking_status" value="{{ $queryParams['booking_status'] ?? 'pending' }}">
            <input type="hidden" name="type" value="{{ $queryParams['type'] ?? 'pending' }}">
            <input type="hidden" name="search" value="{{ $queryParams['search']??'' }}" id="filter-search">
            <div class="filter-aside__body d-flex flex-column">
                <p class="nirab-field-hint">{{ __('admin_ui.booking_filters_help') }}</p>
                <div class="filter-aside__date_range">
                    <h4 class="fw-normal mb-4">{{translate('Select_Date_Range')}}</h4>
                    <div class="mb-30">
                        <div class="form-floating">
                            <input type="text" class="form-control" placeholder="{{translate('start_date')}}"
                                   id="start_date" name="start_date"
                                   value="{{$queryParams['start_date']}}"
                                   data-ff-datepicker readonly autocomplete="off" inputmode="none">
                            <label for="start_date">{{translate('Start_Date')}}</label>
                        </div>
                    </div>
                    <div class="fw-normal mb-30">
                        <div class="form-floating">
                            <input type="text" class="form-control" placeholder="{{translate('end_date')}}"
                                   id="end_date" name="end_date"
                                   value="{{$queryParams['end_date']}}"
                                   data-ff-datepicker readonly autocomplete="off" inputmode="none">
                            <label for="end_date">{{translate('End_Date')}}</label>
                        </div>
                    </div>
                </div>

                <div class="filter-aside__category_select">
                    <h4 class="fw-normal mb-2">{{translate('Select Services')}}</h4>
                    <div class="mb-30">
                        <select class="service-select theme-input-style w-100" name="service_ids[]"
                                multiple="multiple">
                            @foreach($serviceCatalog as $serviceOption)
                                <option
                                    value="{{$serviceOption->id}}" {{in_array($serviceOption->id,$queryParams['service_ids']??[])?'selected':''}}>
                                    {{$serviceOption->name}}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="filter-aside__zone_select">
                    <h4 class="mb-2 fw-normal">{{translate('Select_Zones')}}</h4>
                    <div class="mb-30">
                        <select class="zone-select theme-input-style w-100" name="zone_ids[]" multiple="multiple">
                            @foreach($zones as $zone)
                                <option
                                    value="{{$zone->id}}" {{in_array($zone->id,$queryParams['zone_ids']??[])?'selected':''}}>
                                    {{$zone->name}}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="filter-aside__bottom_btns p-20">
                <div class="d-flex justify-content-center gap-20">
                    <button class="btn btn--secondary text-capitalize" id="reset-btn"
                            type="button">{{translate('Clear_all_Filter')}}</button>
                    <button class="btn btn--primary text-capitalize" type="submit">{{translate('Filter')}}</button>
                </div>
            </div>
        </form>
    </div>

    <div class="main-content nirab-operational-page nirab-booking-list">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-wrap d-flex align-items-center justify-content-between gap-2 mb-3">
                        <h2 class="page-title">{{translate('Verify_Request')}}</h2>
                        <div class="ripple-animation" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{translate('This booking verification list is for verifying bookings whose total amount exceeds the maximum booking amount for cash on delivery')}}" type="button">
                            <img src="{{asset('/public/assets/admin-module/img/info.svg')}}" class="svg" alt="">
                        </div>
                    </div>

                    <div
                        class="d-flex flex-wrap justify-content-between align-items-center border-bottom mx-lg-4 mb-10 gap-3">
                        <ul class="nav nav--tabs">
                            <li class="nav-item">
                                <a class="nav-link {{$type=='pending'?'active':''}}"
                                   href="{{ request()->fullUrlWithQuery(['type' => 'pending', 'page' => null]) }}">
                                    {{translate('pending')}}
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link {{$type=='denied'?'active':''}}"
                                   href="{{ request()->fullUrlWithQuery(['type' => 'denied', 'page' => null]) }}">
                                    {{translate('denied')}}
                                </a>
                            </li>
                        </ul>

                        <div class="d-flex gap-2 fw-medium">
                            <span class="opacity-75">{{translate('Total_Request')}}:</span>
                            <span class="title-color">{{$bookings->total()}}</span>
                        </div>
                    </div>


                    <div class="card">
                        <div class="card-body">
                            <div class="data-table-top nirab-booking-toolbar d-flex flex-wrap gap-10 justify-content-between">
                                <form action="{{ route('admin.booking.list.verification') }}"
                                      class="search-form search-form_style-two"
                                      method="GET">
                                    <input type="hidden" name="booking_status" value="{{$queryParams['booking_status'] ??'pending'}}">
                                    <input type="hidden" name="type" value="{{$queryParams['type'] ??'pending'}}">
                                    @foreach(['service_ids', 'zone_ids', 'start_date', 'end_date'] as $field)
                                        @if(isset($queryParams[$field]))
                                            @if(is_array($queryParams[$field]))
                                                @foreach($queryParams[$field] as $value)
                                                    <input type="hidden" name="{{ $field }}[]" value="{{ $value }}">
                                                @endforeach
                                            @else
                                                <input type="hidden" name="{{ $field }}" value="{{ $queryParams[$field] }}">
                                            @endif
                                        @endif
                                    @endforeach
                                    <div class="input-group search-form__input_group">
                                            <span class="search-form__icon">
                                                <span class="material-icons">search</span>
                                            </span>
                                        <input type="search" class="theme-input-style search-form__input"
                                               value="{{$queryParams['search']??''}}" name="search"
                                               placeholder="{{translate('search by booking id or customer name')}}">
                                    </div>
                                    <button type="submit"
                                            class="btn btn--primary">{{translate('search')}}</button>
                                </form>
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    @can('booking_export')
                                    <div class="dropdown">
                                        <button type="button"
                                                class="btn btn--secondary text-capitalize dropdown-toggle"
                                                data-bs-toggle="dropdown">
                                            <span class="material-icons">file_download</span> {{translate('download')}}
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                                            <li><a class="dropdown-item"
                                                   href="{{ route('admin.booking.list.verification.download',$queryParams) }}">{{translate('excel')}}</a>
                                            </li>
                                        </ul>
                                    </div>
                                    @endcan

                                    <button type="button" class="btn text-capitalize filter-btn border px-3">
                                        <span class="material-icons">filter_list</span> {{translate('Filter')}}
                                        <span class="count">{{$filterCounter??0}}</span>
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive nirab-data-table">
                                <table id="example" class="table align-middle tr-hover">
                                    <thead class="text-nowrap">
                                    <tr>
                                        <th>{{translate('SL')}}</th>
                                        <th>{{translate('Booking_ID')}}</th>
                                        <th>{{ translate('Where_Service_will_be_Provided') }}</th>
                                        <th>{{translate('Customer_Info')}}</th>
                                        <th>{{translate('Total_Amount')}}</th>
                                        <th>{{translate('Payment_Status')}}</th>
                                        <th>{{translate('Schedule_Date')}}</th>
                                        <th>{{translate('Booking_Date')}}</th>
                                        <th>{{translate('Status')}}</th>
                                        <th>{{translate('Action')}}</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($bookings as $key=>$booking)
                                        <tr
                                            @if($booking->is_repeated)
                                                data-bs-custom-class="review-tooltip custom"
                                            data-bs-toggle="tooltip"
                                            data-bs-html="true"
                                            data-bs-placement="bottom"
                                            data-bs-title="{{ translate('This is a repeat booking.') }} <br> {{ translate('Customer has requested total :count bookings under this booking.', ['count' => count($booking->repeat)]) }} <br> {{ translate('Check the details') }}"
                                            @endif
                                        >
                                            <td>{{$key+$bookings?->firstItem()}}</td>
                                            <td>
                                                @if($booking->is_repeated)
                                                    <a href="{{ route('admin.booking.repeat_details', [$booking->id, 'web_page' => 'details']) }}">
                                                        {{ $booking->readable_id }}
                                                    </a>
                                                    <img width="34" height="34"
                                                         src="{{ asset('public/assets/admin-module/img/icons/repeat.svg') }}"
                                                         class="rounded-circle repeat-icon"
                                                         alt="{{ translate('repeat') }}">
                                                @else
                                                    <a href="{{ route('admin.booking.details', [$booking->id, 'web_page' => 'details']) }}">
                                                        {{ $booking->readable_id }}</a>
                                                @endif
                                            </td>
                                            <td>
                                                @if($booking->service_location == 'provider')
                                                    {{ translate('Provider Location') }}
                                                @else
                                                    {{ translate('Customer Location') }}
                                                @endif
                                            </td>
                                            <td>
                                                @php($customer_name = $booking?->service_address?->contact_person_name ?? $booking?->customer?->first_name . ' ' . $booking?->customer?->last_name)
                                                @php($customer_phone = $booking?->service_address?->contact_person_number ?? $booking?->customer?->phone)
                                                <div class="d-flex flex-column gap-1">
                                                    @if($booking->customer)
                                                        <a href="{{route('admin.customer.detail',[$booking?->customer?->id, 'web_page'=>'overview'])}}">
                                                            {{ Str::limit($customer_name, 30) }}
                                                        </a>
                                                    @else
                                                        <span>
                                                            {{Str::limit($customer_name, 30)}}
                                                        </span>
                                                    @endif
                                                    @if($customer_phone)
                                                        <a href="tel:{{$customer_phone}}">{{$customer_phone}}</a>
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{with_currency_symbol($booking->total_booking_amount)}}</td>
                                            <td>
                                                <span
                                                    class="badge badge badge-{{$booking->is_paid?'success':'danger'}} radius-50">
                                                    <span class="dot"></span>
                                                    {{$booking->is_paid?translate('paid'):translate('unpaid')}}
                                                </span>
                                            </td>
                                            <td>
                                                @if($booking->is_repeated)
                                                    <span>{{translate('Next upcoming')}}</span>
                                                    <div>{{ date('d-M-Y', strtotime($booking?->nextService?->service_schedule)) }}</div>
                                                    <div>{{ format_time_by_business_settings($booking?->nextService?->service_schedule) }}</div>
                                                @else
                                                    <div>{{ date('d-M-Y', strtotime($booking->service_schedule)) }}</div>
                                                    <div>{{ format_time_by_business_settings($booking->service_schedule) }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column gap-1">
                                                    <div>{{ \Carbon\Carbon::parse($booking->created_at)->format('d-M-Y') }}</div>
                                                    <div>{{ format_time_by_business_settings($booking->created_at) }}</div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($booking->is_verified == '0')
                                                    <label class="badge badge-info bg-transparent p-0">
                                                        <span class="dot"></span>
                                                        {{translate('pending')}}
                                                    </label>
                                                @elseif($booking->is_verified == '1')
                                                    <label class="badge badge-success bg-transparent p-0">
                                                        <span class="dot"></span>
                                                        {{translate('verified')}}
                                                    </label>
                                                @else
                                                    <label class="badge badge-danger bg-transparent p-0">
                                                        <span class="dot"></span>
                                                        {{translate('denied')}}
                                                    </label>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-2">
                                                    @if($booking->is_repeated)
                                                        <div class="dropdown">
                                                            <button type="button"
                                                                    class="action-btn btn--light-primary fw-medium text-capitalize fz-14"
                                                                    style="--size: 30px" data-bs-toggle="dropdown">
                                                                <span class="material-icons">visibility</span>
                                                            </button>
                                                            <ul
                                                                class="dropdown-menu border-none dropdown-menu-lg dropdown-menu-right">
                                                                <li class="mx-2"><a
                                                                        class="dropdown-item d-flex align-items-center gap-1"
                                                                        href="{{ route('admin.booking.repeat_details', [$booking->id, 'web_page' => 'details']) }}">
                                                                                <span
                                                                                    class="material-icons">visibility</span>
                                                                        {{ translate('Full_Booking_Details') }}
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    @else
                                                        <a href="{{ route('admin.booking.details', [$booking->id, 'web_page' => 'details']) }}"
                                                           type="button"
                                                           class="action-btn btn--light-primary fw-medium text-capitalize fz-14"
                                                           style="--size: 30px">
                                                            <span class="material-icons">visibility</span>
                                                        </a>
                                                    @endif
                                                    @if($booking->is_verified == '0')
                                                        @can('booking_can_approve_or_deny')
                                                        <button type="button" data-verify="{{$booking->id}}" class="action-btn btn--success booking-verify" style="--size: 30px">
                                                            <span class="material-icons m-0">done_outline</span>
                                                        </button>

                                                        <button type="button" data-deny="{{$booking->id}}" style="--size: 30px"
                                                                class="action-btn btn--danger booking-deny"
                                                                data-bs-toggle="modal" data-bs-target="#exampleModal--{{$booking->id}}">
                                                            <span class="material-icons m-0">close</span>
                                                        </button>

                                                        @endcan

                                                        <div class="modal fade" id="exampleModal--{{$booking->id}}" tabindex="-1"
                                                             aria-labelledby="exampleModalLabel--{{$booking->id}}" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-body pt-5 p-md-5">
                                                                        <button type="button" class="btn-close"
                                                                                data-bs-dismiss="modal"
                                                                                aria-label="Close"></button>

                                                                        <div class="d-flex justify-content-center mb-4">
                                                                            <img width="75" height="75" src="{{asset('public/assets/admin-module/img/delete2.png')}}" class="rounded-circle" alt="">
                                                                        </div>

                                                                        <h3 class="text-start mb-1 fw-medium text-center">{{translate('Are you sure you want to cancel the request?')}}</h3>
                                                                        <p class="text-start fs-12 fw-medium text-muted text-center">{{translate('You will lost the custom booking request?')}}</p>
                                                                        <form method="post"
                                                                              action="{{route('admin.booking.verification-status',[$booking->id])}}">
                                                                            @csrf
                                                                            <div class="form-floating">
                                                                                <textarea class="form-control h-69px"
                                                                                          placeholder="{{translate('Cancellation Note')}}"
                                                                                          name="booking_deny_note"
                                                                                          id="add-your-note" required></textarea>
                                                                                <label for="add-your-note"
                                                                                       class="d-flex align-items-center gap-1">
                                                                                    {{translate('Cancellation Note')}}
                                                                                </label>
                                                                                <input type="hidden" value="deny" name="status">
                                                                                <div class="d-flex justify-content-center mt-3 gap-3">
                                                                                    <button type="button" data-bs-dismiss="modal"
                                                                                            aria-label="Close" class="btn btn--secondary min-w-92px px-2">{{translate('Not Now')}}</button>
                                                                                    <button type="submit" class="btn btn--danger min-w-92px">{{translate('Yes')}}</button>
                                                                                </div>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        @include('adminmodule::layouts.partials.components._empty-state', [
                                            'variant' => (
                                                filled($queryParams['search'] ?? null)
                                                || (($filterCounter ?? 0) > 0)
                                                || (($type ?? ($queryParams['type'] ?? 'pending')) !== 'pending')
                                            ) ? 'search' : 'list',
                                        ])
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-end">
                                {!! $bookings->links() !!}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('script')
    <script src="{{asset('public/assets/admin-module/js/moment.min.js')}}"></script>
    <script src="{{asset('public/assets/admin-module/js/daterangepicker.min.js')}}"></script>
    <script>
        function initSingleDatePicker(selector) {
            const $input = $(selector);

            $input.daterangepicker({
                singleDatePicker: true,
                showDropdowns: true,
                autoApply: true,
                autoUpdateInput: false,
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });

            if ($input.val()) {
                const selectedDate = moment($input.val(), 'YYYY-MM-DD', true);

                if (selectedDate.isValid()) {
                    $input.data('daterangepicker').setStartDate(selectedDate);
                    $input.data('daterangepicker').setEndDate(selectedDate);
                    $input.val(selectedDate.format('YYYY-MM-DD'));
                }
            }

            $input.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD'));
            });
        }

        $(document).ready(function () {
            initSingleDatePicker('#start_date');
            initSingleDatePicker('#end_date');

            $('#filter-form').on('submit', function (e) {
                let from = $('#start_date').val();
                let to = $('#end_date').val();
                let hasError = false;

                if (from === '' && to === '') {
                    return;
                }

                if (to === '') {
                    toastr.error('{{ translate('please select to date') }}');
                    hasError = true;
                }

                if (from === '') {
                    toastr.error('{{ translate('please select from date') }}');
                    hasError = true;
                }

                if (!hasError) {
                    let fromDate = moment(from, 'YYYY-MM-DD', true);
                    let toDate = moment(to, 'YYYY-MM-DD', true);

                    if (!fromDate.isValid() || !toDate.isValid() || fromDate.isAfter(toDate)) {
                        toastr.error('{{ translate('From date must be earlier than To date') }}');
                        hasError = true;
                    }
                }

                if (hasError) {
                    e.preventDefault();
                }
            });
        });
    </script>
    <script>
        (function ($) {
            "use strict";

            $('.service-select').select2({
                placeholder: "{{translate('Select Category')}}"
            });
            $('.subservice-select').select2({
                placeholder: "{{translate('Select Subcategory')}}"
            });
            $('.zone-select').select2({
                placeholder: "{{translate('Select Zone')}}"
            })

            $('.booking-verify').on('click', function () {
                let itemId = $(this).data('verify');
                let route = '{{ route('admin.booking.verification_status_update', [':itemId']) }}';
                route = route.replace(':itemId', itemId);
                route_alert_reload(route, '{{ translate('want_to_verified_this_booking') }}', true);
            })

        })(jQuery);
    </script>

    <script>
        $(document).ready(function() {
            $('#reset-btn').on('click', function(e) {
                e.preventDefault();
                window.location.href = '{{ url()->current() }}';
            });
        });
    </script>
@endpush
