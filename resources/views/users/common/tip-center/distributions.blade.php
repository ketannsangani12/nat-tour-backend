@extends('layouts.default-6')

@section('main-content')
@php
$routePrefix = routePrefix();
$bookingPrefix = bookingPrefixSetting();
@endphp
<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="tab-content" id="mainTabContent">

        <!-- Distribution Management -->
        <div class="tab-pane fade show active" id="distribution" role="tabpanel">
            <!-- Stats Overview -->
            <div class="row g-4 mb-4 d-none">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Total Gratuities</p>
                                    <h4 class="mb-0">$2,450</h4>
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
                                    <h4 class="mb-0">5</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Completed</p>
                                    <h4 class="mb-0">23</h4>
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
                                    <p class="text-muted small mb-0">Average Gratuity</p>
                                    <h4 class="mb-0">$87</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Distribution List -->
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-1">
                        <i class="fas fa-users text-primary me-2"></i>Gratuity Distributions
                    </h5>
                    <p class="text-muted mb-0 small">Track and manage gratuity payments to tour guides</p>
                </div>
                <div class="container-fluid py-3">
                    <div class="filter-card p-3">
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
                    <div class="filter-card p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex gap-3">
                                <div class="stat-item">
                                    <div class="stat-value text-dark" id="totalBookingsCount">1</div>
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
</div>

<div class="modal fade " id="processTransferModal" tabindex="-1" role="dialog" aria-modal="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Process Transfer</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div id="process-transfer-body">
                    <form id="processTransferForm">
                        <div class="row">
                            <div class="col-12 mb-3">
                                <div class="booking-info p-3 bg-light rounded">
                                    <h6 class="mb-2">Booking Details</h6>
                                    <div class="row" id="booking-info-div">

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="guideOptsDiv">

                        </div>


                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-process-transfer-btn">Submit</button>
            </div>
        </div>
    </div>
</div>

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
    var routePrefixUrl = "{{ $routePrefix }}";
    var todayDate = moment('{{ $date }}').format('YYYY-MM-DD');
</script>
<script src="{{ asset('js/user-common/tip-center/distribution_bkup.js') }}"></script>
<script>
    $(document).ready(() => {
        const bookingPrefix = "{{ $bookingPrefix }}";
        const fetchDistributionsUrl = "{{ route(routePrefix().'.tip-center.distributions-list') }}";
        const fetchDistributionInfoUrl = "{{ route(routePrefix().'.tip-center.distribution-info',['id' => ':id']) }}";
        const saveDistributionAmountsUrl = "{{ route(routePrefix().'.tip-center.save-distribution-amounts') }}";
        const exportDistributionsUrl = "{{ route(routePrefix().'.tip-center.distributions-export',['fromDate' => ':fromDate','toDate' => ':toDate', 'payrollScheduleId'=>':payrollScheduleId']) }}";
        const processTransferUrl = "{{ route(routePrefix().'.tip-center.process-transfer') }}";
        const processBulkTransferTipsUrl = "{{ route(routePrefix().'.tip-center.process-bulk-transfer-tips') }}";
        const bookingInfoUrl = "{{ route(prefixedRouteName(routePrefix().'.booking.show'), [ 'booking' => ':id' ]) }}";
        const defaultDate = moment('{{ $date }}');
        const gratuityDistributions = new GratuityDistributions({
            fetchDistributionsUrl,
            fetchDistributionInfoUrl,
            processTransferUrl,
            processBulkTransferTipsUrl,
            saveDistributionAmountsUrl,
            exportDistributionsUrl,
            bookingInfoUrl,
            todayDate,
            defaultDate,
            bookingPrefix
        });
    });
</script>
@endsection