@extends('adminmodule::layouts.master')

@section('title',translate('Offline payment list'))

@push('css_or_js')
    <link rel="stylesheet" href="{{asset('public/assets/admin-module/css/daterangepicker.css')}}"/>
@endpush

@section('content')

    <div class="main-content nirab-operational-page nirab-booking-list">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="page-title-wrap mb-3">
                        <h2 class="page-title">{{translate('Offline payment booking list')}}</h2>
                    </div>
                    <div class="border border-danger-light bg-soft-danger rounded py-3 px-3 text-dark mb-3">
                        <span class="text-danger"># {{translate('Note:')}} </span>
                            <span>
                            {{translate('For offline payments please verify, If the payments are safely received to your account. Customer is not liable, If you confirm the bookings without checking payment transactions')}}
                        </span>
                    </div>

                    <form method="GET" action="{{ route('admin.booking.offline.payment') }}" class="mb-3">
                        <label for="review_status">{{ __('offline_transfer.review_status') }}</label>
                        <select id="review_status" name="review_status" class="form-select" onchange="this.form.submit()">
                            <option value="">{{ __('offline_transfer.all') }}</option>
                            @foreach(['pending', 'approved', 'denied'] as $reviewStatus)
                                <option value="{{ $reviewStatus }}" @selected(request('review_status') === $reviewStatus)>{{ __('offline_transfer.'.$reviewStatus) }}</option>
                            @endforeach
                        </select>
                    </form>
                    <div class="card">
                        <div class="card-body">
                            <div class="data-table-top nirab-booking-toolbar d-flex flex-wrap gap-10 justify-content-between">
                                <form action="{{ route('admin.booking.offline.payment') }}"
                                      class="search-form search-form_style-two"
                                      method="GET">
                                    @foreach(['service_ids', 'zone_ids', 'start_date', 'end_date', 'review_status'] as $field)
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
                                                   href="{{route('admin.booking.offline.payment.download', $queryParams)}}">{{translate('excel')}}</a>
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
                                <table id="example" class="table align-middle">
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
                                        <tr>
                                            <td>{{$key+$bookings?->firstItem()}}</td>
                                            <td>
                                                <a href="{{route('admin.booking.details', [$booking->id,'web_page'=>'details'])}}">
                                                    {{$booking->readable_id}}</a>
                                                <small class="d-block">{{ $booking->provider?->company_name }}</small>
                                                @php($offline = $booking->booking_offline_payments->first())
                                                <small class="d-block">{{ $offline?->method_name }} · {{ $offline?->activeAttempt?->created_at ?? $offline?->created_at }}</small>
                                                @foreach($offline?->customer_information ?? [] as $field => $value)
                                                    <small class="d-block">{{ str_replace('_', ' ', $field) }}: {{ $value }}</small>
                                                @endforeach
                                                @if($offline?->activeAttempt?->receipt_id)
                                                    <a target="_blank" rel="noopener noreferrer" href="{{ route('admin.booking.offline-payment.receipt', ['booking_id' => $booking->id, 'receipt_id' => $offline->activeAttempt->receipt_id]) }}">{{ __('offline_transfer.view_receipt') }}</a>
                                                @endif
                                                <small class="d-block">{{ $offline?->denied_note }}</small>
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
                                                <div>
                                                    @if($booking->customer)
                                                        <a href="{{route('admin.customer.detail',[$booking?->customer?->id, 'web_page'=>'overview'])}}">
                                                            {{ Str::limit($customer_name, 30) }}
                                                        </a>
                                                    @else
                                                        <span>
                                                            {{Str::limit($customer_name, 30)}}
                                                        </span>
                                                    @endif
                                                </div>
                                                {{$customer_phone}}
                                            </td>
                                            <td>{{with_currency_symbol($booking->total_booking_amount)}}</td>
                                            <td>
                                                <span
                                                    class="badge badge badge-{{$booking->is_paid?'success':'danger'}} radius-50">
                                                    <span class="dot"></span>
                                                    {{$booking->is_paid?translate('paid'):translate('unpaid')}}
                                                </span>
                                            </td>
                                            <td>{{date('d-M-Y h:ia',strtotime($booking->service_schedule))}}</td>
                                            <td>{{date('d-M-Y h:ia',strtotime($booking->created_at))}}</td>
                                            <td class="text-center">
                                                <label class="badge badge-info">{{__('offline_transfer.'.($offline?->payment_status ?? 'incomplete'))}}</label>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex gap-2">
                                                    <a href="{{route('admin.booking.details', [$booking->id,'web_page'=>'details'])}}"
                                                        type="button"
                                                        class="action-btn btn--light-primary fw-medium text-capitalize fz-14" style="--size: 30px">
                                                        <span class="material-icons m-0">visibility</span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        @include('adminmodule::layouts.partials.components._empty-state', [
                                            'variant' => (
                                                filled($queryParams['search'] ?? null)
                                                || (($filterCounter ?? 0) > 0)
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
            <div class="filter-aside nirab-booking-filters">
                <div class="filter-aside__header d-flex justify-content-between align-items-center">
                    <h3 class="filter-aside__title">{{translate('Filter_your_Booking')}}</h3>
                    <button type="button" class="btn-close p-2 btn-close-white"></button>
                </div>
                <form action="{{ route('admin.booking.offline.payment') }}" method="GET"
                    enctype="multipart/form-data" id="filter-form">
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
