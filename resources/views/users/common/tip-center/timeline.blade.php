@extends('layouts.default-6')

@php
$routePrefix = routePrefix();
$bookingPrefix = bookingPrefixSetting();
@endphp
@section('main-content')
<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="tab-content" id="mainTabContent">
        <!-- Reports -->
        <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
            <!-- Report Controls -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-1">
                                <i class="fas fa-users text-primary me-2"></i>Gratuity Distributions
                            </h5>
                            <p class="text-muted mb-0 small">Track and manage gratuity payments to tour guides</p>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="card shadow-sm mb-4">
                <div class="row g-4 mb-4 text-center">
                    <!-- Tools centered -->
                    <div class="tools d-flex justify-content-center gap-2 mb-3 d-none">
                        <button class="btn btn-sm btn-outline-primary" id="previous">Previous</button>
                        <button class="btn btn-sm btn-outline-primary" id="today">Today</button>
                        <button class="btn btn-sm btn-outline-primary" id="next">Next</button>
                    </div>

                    <!-- Scheduler centered -->
                    <div class="d-flex justify-content-center mt-4">

                        <div id="timeline" class="mx-auto "></div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm d-none" id="distributionsListDiv">
                <div class="container-fluid py-3">
                    <div class="filter-card p-3 d-none">
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
                            <div class="col-md-3">
                                <label class="filter-label">Guide</label>
                                <select class="form-select custom-select" id="guide-filter">
                                    <option value="0">Show All Guide</option>
                                    @foreach($guides as $guide)
                                    <option value="{{ $guide->id }}">{{ $guide->name }}</option>
                                    @endforeach
                                </select>
                            </div>

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
                            <div class="col-md-1">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-outline-secondary w-100" id="expand-all-btn">
                                        Collapse All
                                    </button>

                                </div>
                            </div>
                            <div class="col-md-1 d-none">
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary w-100" id="select-all-btn">
                                        Select All
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
                    <div class="row g-4 mb-4">
                        <h4 id="dateRange"></h2>
                    </div>
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-primary">
                                            <i class="fas fa-dollar-sign"></i>
                                        </div>
                                        <div class="ms-3">
                                            <p class="text-muted small mb-0">Total Gratuity</p>
                                            <h4 class="mb-0" id="totalGratuityAmount">0</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-warning">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="ms-3">
                                            <p class="text-muted small mb-0">Pending Transfers</p>
                                            <h4 class="mb-0" id="totalPendingBookingsCount">0</h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div id="scheduleDiv" class="d-flex align-items-center">
                                        <!-- Total Ready Bookings -->
                                        <div class="me-3 text-center">
                                            <span id="totalReadyBookingsCount" class="fw-bold" style="font-size: 28px;">0</span>
                                            <div class="small text-muted">Ready Bookings</div>
                                        </div>

                                        <!-- Ready & Schedule Button + Datepicker -->
                                        <div class="position-relative">
                                            <button id="ready-schedule-btn" class="btn btn-success d-flex align-items-center px-3 py-2">
                                                <i class="fas fa-calendar me-2" style="font-size: 14px;"></i>
                                                Ready & Schedule
                                            </button>
                                            <input type="hidden" id="scheduleDate">

                                            <!-- Datepicker container -->
                                            <div id="datepicker-container"
                                                class="shadow p-3 bg-white rounded border mt-2"
                                                style="display:none; position:absolute; top:100%; left:0; z-index:1050; min-width: 250px;">
                                            </div>
                                        </div>
                                    </div>
                                    <div id="scheduleCompletedDiv" class="d-none d-flex align-items-center">
                                        <div class="stat-icon bg-success d-flex align-items-center justify-content-center">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="ms-3">
                                            <p class="text-muted small mb-0">Completed</p>
                                            <h4 class="mb-0" id="totalCompletedBookingsCount">0</h4>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-info">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div class="ms-3">
                                            <p class="text-muted small mb-0">Schedule Payroll</p>
                                            <h4 class="mb-0" id="payrollScheduleDate"></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary btn-sm mt-1 mb-2 me-2" id="btn-export">
                            <i class="fas fa-file-export me-1"></i>
                            Export
                        </button>
                        <button class="badge bg-success border border-success border-opacity-25 mt-1 ready-all-btn ms-2 mb-2">
                            <i class="fas fa-check me-1" style="font-size: 10px;"></i>
                            Ready All
                        </button>
                    </div>

                    <div class="filter-card p-3 d-none">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-3">
                                <div class="stat-item">
                                    <div class="stat-value text-dark">1</div>
                                    <div class="stat-label">Bookings</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value text-warning" id="totalPendingBookingsCount">1,045</div>
                                    <div class="stat-label">Pending Transfers</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value text-success" id="totalCompletedBookingsCount">4</div>
                                    <div class="stat-label">Completed</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value text-warning" id="totalPendingAmount">$4</div>
                                    <div class="stat-label">Pending Amount</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value text-success" id="totalTransferredAmount">4</div>
                                    <div class="stat-label">Transferred Amount</div>
                                </div>
                            </div>
                            <div class="d-flex gap-4">
                                <button class="btn btn-primary btn-sm transfer-all-btn d-none">
                                    <i class="fas fa-paper-plane me-1"></i>
                                    Process All Transfer
                                </button>
                                <button class="btn btn-success btn-sm" id="btn-export">
                                    <i class="fas fa-file-export me-1"></i>
                                    Export
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Loading State -->
                    <div id="loading" class="loading d-none">
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
</div>
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
@else
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
@endif
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="{{ asset('plugins/daypilot/daypilot-all.min.js') }}"></script>
@else
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
<script src="{{ asset('plugins/daypilot/daypilot-all.min.js') }}"></script>
@endif
@endsection
@section('js-custom')
<script>
    var userType = "{{ session('userType')  }}";
    var routePrefixUrl = "{{ $routePrefix }}";
    var todayDate = moment('{{ $date }}').format('YYYY-MM-DD');
    var distributionSchedules = @json($distributionSchedules);
</script>
<script src="{{ asset('js/user-common/tip-center/gratuity-distributions.js') }}"></script>
<script>
    $(document).ready(() => {
        const bookingPrefix = "{{ $bookingPrefix }}";
        const fetchDistributionsUrl = "{{ route(routePrefix().'.tip-center.distributions-list') }}";
        const fetchDistributionInfoUrl = "{{ route(routePrefix().'.tip-center.distribution-info',['id' => ':id']) }}";
        const saveDistributionAmountsUrl = "{{ route(routePrefix().'.tip-center.save-distribution-amounts') }}";
        const exportDistributionsUrl = "{{ route(routePrefix().'.tip-center.distributions-export',['fromDate' => ':fromDate','toDate' => ':toDate', 'payrollScheduleId'=>':payrollScheduleId']) }}";
        const processAcceptTipsUrl = "{{ route(routePrefix().'.tip-center.process-accept-tips') }}";
        const processBulkAcceptTipsUrl = "{{ route(routePrefix().'.tip-center.process-bulk-accept-tips') }}";
        const processBulkCompleteTipsUrl = "{{ route(routePrefix().'.tip-center.process-bulk-complete-tips') }}";
        const bookingInfoUrl = "{{ route(prefixedRouteName(routePrefix().'.booking.show'), [ 'booking' => ':id' ]) }}";
        const defaultDate = moment('{{ $date }}');
        const gratuityDistributions = new GratuityDistributions({
            fetchDistributionsUrl,
            fetchDistributionInfoUrl,
            processAcceptTipsUrl,
            processBulkAcceptTipsUrl,
            processBulkCompleteTipsUrl,
            saveDistributionAmountsUrl,
            exportDistributionsUrl,
            bookingInfoUrl,
            distributionSchedules,
            todayDate,
            defaultDate,
            bookingPrefix
        });
    });
</script>
@endsection