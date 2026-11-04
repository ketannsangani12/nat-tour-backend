@extends('layouts.default-6')

@section('main-content')
@php
$routePrefix = routePrefix();
$bookingPrefix = bookingPrefixSetting();
@endphp
<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="tab-content" id="mainTabContent">

        <!-- Guide Management -->
        <div class="tab-pane fade show active" id="guides" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">
                            <i class="fas fa-calendar-check text-primary me-2"></i> Bookings
                        </h5>
                        <p class="text-muted mb-0 small">Manage bookings their assigned guides</p>
                    </div>

                </div>
                <div class="card-body">

                    <!-- Filter Row -->
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="filter-label">Date</label>
                            <div class="position-relative">
                                <input type="text" class="form-control" id="date-range-filter" placeholder="Select date">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="filter-label">Time</label>
                            <select class="form-select custom-select" id="slot-filter">
                                <option value="0">Show All Time</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="filter-label">Tour Package</label>
                            <select class="form-select custom-select" id="tour-package-filter">
                                <option value="0">Show All Package</option>
                                @foreach($tourPackages as $tp)
                                <option value="{{ $tp->id }}">{{ $tp->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @if(session('userType') == 'Admin' || $userRole == 'staff')
                        <div class="col-md-3">
                            <label class="filter-label">Guide</label>
                            <select class="form-select custom-select" id="guide-filter">
                                <option value="0">Show All Guide</option>
                                @foreach($guides as $guide)
                                <option value="{{ $guide->id }}">{{ $guide->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                    </div>

                    <!-- Search and Sort Row -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="position-relative">
                                <i class="bi bi-search search-icon"></i>
                                <input
                                    type="text"
                                    class="form-control search-input"
                                    placeholder="Search Booking Id..."
                                    id="search-input">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-secondary w-100" id="clear-filters-btn">
                                <i class="bi bi-funnel"></i> Clear Filters
                            </button>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-outline-secondary" id="expand-all-btn">
                                    Collapse All
                                </button>

                            </div>
                        </div>
                    </div>

                    <!-- Active Filters -->
                    <div id="active-filters" class="mt-3 pt-3 border-top" style="display: none;">
                        <span class="text-muted small">Active filters:</span>
                        <div id="filter-badges" class="mt-2"></div>
                    </div>

                </div>
            </div>
            <div class="container-fluid py-4">
                @if(session('userType') == 'Admin')
                <div class="filter-card p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex gap-3">
                            <div class="stat-item">
                                <div class="stat-value text-dark" id="totalBookingsCount">1</div>
                                <div class="stat-label">Bookings</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value text-warning" id="totalUnAssignedBookingsCount">1,045</div>
                                <div class="stat-label">Unassigned Bookings</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value text-success" id="totalAssignedBookingsCount">4</div>
                                <div class="stat-label">Assigned Bookings</div>
                            </div>
                        </div>

                    </div>
                </div>
                @endif
                <!-- Loading State -->
                <div id="loading" class="loading">
                    <div>
                        <div class="spinner mx-auto mb-3"></div>
                        <p class="text-muted">Loading bookings...</p>
                    </div>
                </div>
                <div id="bookings-container" style="display: block;">

                </div>
            </div>
        </div>

    </div>
</div>
@include('users.common.partials.booking._assign-guides-popup',['tourGuides' => $guides])

@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
@endif
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="{{ asset('plugins/daterange/daterangepicker.2.1.25.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
<script src="{{ asset('plugins/daterange/daterangepicker.2.1.25.js')}}"></script>
@else
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="{{ asset('plugins/daterange/daterangepicker.2.1.25.js')}}"></script>
@endif
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('js-custom')
<script>
    var userType = "{{ session('userType')  }}";
    var userRole = "{{ $userRole }}";
    var routePrefixUrl = "{{ routePrefix() }}";
    var todayDate = moment('{{ $date }}').format('YYYY-MM-DD');
    var getAssignedGuidesURL = "{{ (session('userType') == 'Admin' || $userRole == 'staff') ? route(prefixedRouteName($routePrefix . '.booking.get-assigned-guides'), ['bookingId' => '_BOOKING_ID_']) : '' }}";
    var assignTourGuideUrl = "{{ (session('userType') == 'Admin' || $userRole == 'staff') ? route(prefixedRouteName($routePrefix . '.booking.save-tour-guide'), ['bookingId' => '_BOOKING_ID_']) : '' }}";
    var tourGuides = @json($guides);
    var guideId = "{{ (session('userType') == 'Admin' || $userRole == 'staff') ? '0' : auth()->user()->id }}";
</script>
<script src="{{ asset('js/user-common/tip-center/guide-bookings.js') }}"></script>
<script src="{{ asset('js/user-common/tour-booking-assign-guides.js') }}"></script>
<script>
    $(document).ready(() => {
        const bookingPrefix = "{{ $bookingPrefix }}";
        const fetchGuideBookingsUrl = "{{ route(routePrefix().'.tip-center.guide-bookings') }}";
        const bookingInfoUrl = "{{ route(prefixedRouteName(routePrefix().'.booking.show'), [ 'booking' => ':id' ]) }}";
        const defaultDate = moment('{{ $date }}');
        const guideBookings = new GuideBookings({
            userType,
            userRole,
            fetchGuideBookingsUrl,
            bookingInfoUrl,
            todayDate,
            defaultDate,
            bookingPrefix,
            guideId
        });


    });
</script>
@endsection