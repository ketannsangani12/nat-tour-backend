@extends('layouts.default-6')

@section('main-content')
@php
$currencyCode = currencyFormation(false, true);
@endphp
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
                                <i class="fas fa-chart-bar text-primary me-2"></i>Dashboard
                            </h5>
                            <p class="text-muted mb-0 small">Analyze gratuity performance and trends</p>
                        </div>
                        <div class="d-flex gap-2 d-none">
                            <select class="form-select">
                                <option>Last 30 days</option>
                                <option>Last 7 days</option>
                                <option>Last 90 days</option>
                                <option>Last year</option>
                            </select>
                            <button class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Export
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="row g-4 mb-4">
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-info">
                                    <i class="fa-solid fa-money-bill"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Paid Amount</p>
                                    <h4 class="mb-0">{{$currencyCode}}{{$totalGratuity - $totalUnpaidGratuity}}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-danger">
                                    <i class="fa-solid fa-money-bill-1"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Unpaid Amount</p>
                                    <h4 class="mb-0 text-danger">{{$currencyCode}}{{ $totalUnpaidGratuity }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-success">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Total Amount</p>
                                    <h4 class="mb-0">{{$currencyCode}}{{ $totalGratuity }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="col-md-2">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-primary">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Total Bookings</p>
                                    <h4 class="mb-0">{{$totalBookings}}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @if(userType() == 'Admin')
                <div class="col-md-4">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon bg-warning">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="ms-3">
                                    <p class="text-muted small mb-0">Top Guide</p>
                                    <h5 class="mb-0">-</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            <div class="row g-4">
                <!-- Monthly Trend -->
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Tip Amounts Today</h6>
                            <canvas id="barChartToday" style="min-height: 300px; height: 350px; max-height: 450px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Tip Amounts Week</h6>
                            <canvas id="barChartWeek" style="min-height: 300px; height: 350px; max-height: 450px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Tip Amounts Month</h6>
                            <canvas id="barChartMonth" style="min-height: 300px; height: 350px; max-height: 450px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Tip Amounts Year</h6>
                            <canvas id="barChartYear" style="min-height: 300px; height: 350px; max-height: 450px; max-width: 100%;"></canvas>
                        </div>
                    </div>
                </div>
                <!-- Gratuity Type Distribution -->
                <div class="col-lg-6 d-none">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h6 class="card-title">Gratuity Type Distribution</h6>
                            <div class="distribution-chart">
                                <div class="distribution-item mb-3">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Fixed Percentage </span>
                                        <span class="fw-semibold">${{$fixedPercentageGratuityTotal}}</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: 60%"></div>
                                    </div>
                                </div>
                                <div class="distribution-item mb-3 d-none">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Flexible ($5-$50)</span>
                                        <span class="fw-semibold">$3,735 (30%)</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: 30%"></div>
                                    </div>
                                </div>
                                <div class="distribution-item">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Fixed Amount</span>
                                        <span class="fw-semibold">${{$fixedAmountGrauityTotal}}</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" style="width: 10%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if(userType() == 'Admin')
            <!-- Top Performing Guides -->
            <div class="card shadow-sm mt-4 d-none">
                <div class="card-header bg-white border-bottom">
                    <h6 class="card-title mb-1">Top Performing Guides</h6>
                    <p class="text-muted mb-0 small">Guides with highest gratuity earnings this period</p>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Rank</th>
                                    <th>Guide</th>
                                    <th>Total Earnings</th>
                                    <th>Tours Completed</th>

                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="rank-badge rank-1">1</div>
                                    </td>
                                    <td class="fw-semibold">Sarah Johnson</td>
                                    <td class="fw-bold text-success">$2,890</td>
                                    <td>34</td>

                                </tr>
                                <tr>
                                    <td>
                                        <div class="rank-badge rank-2">2</div>
                                    </td>
                                    <td class="fw-semibold">Mike Rodriguez</td>
                                    <td class="fw-bold text-success">$2,340</td>
                                    <td>28</td>

                                </tr>
                                <tr>
                                    <td>
                                        <div class="rank-badge rank-3">3</div>
                                    </td>
                                    <td class="fw-semibold">Emily Chen</td>
                                    <td class="fw-bold text-success">$2,120</td>
                                    <td>25</td>

                                </tr>
                                <tr>
                                    <td>
                                        <div class="rank-badge rank-other">4</div>
                                    </td>
                                    <td class="fw-semibold">David Park</td>
                                    <td class="fw-bold text-success">$1,980</td>
                                    <td>23</td>

                                </tr>
                                <tr>
                                    <td>
                                        <div class="rank-badge rank-other">5</div>
                                    </td>
                                    <td class="fw-semibold">Lisa Wong</td>
                                    <td class="fw-bold text-success">$1,750</td>
                                    <td>21</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('js-libraries')
<script src="{{ asset('plugins/chart.js-2.9.4/Chart.min.js') }}"></script>
@endsection
@section('js-custom')

<script type="text/javascript">
    var tipAmountsOfToday = @json($tipAmountsOfToday);
    var tipAmountsOfWeek = @json($tipAmountsOfWeek);
    var tipAmountsOfMonth = @json($tipAmountsOfMonth);
    var tipAmountsOfYear = @json($tipAmountsOfYear);
</script>
<script src="{{ asset('js/user-common/tip-center/tip-center.js') }}"></script>
<script type="text/javascript">
    $(document).ready(() => {
        const tipCenter = new TipCenter({
            tipAmountsOfToday,
            tipAmountsOfWeek,
            tipAmountsOfMonth,
            tipAmountsOfYear
        });
    });
</script>
@endsection