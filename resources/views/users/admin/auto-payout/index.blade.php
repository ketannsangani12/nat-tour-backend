<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NAT - Auto Payout Management System</title>
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link href="{{ asset('images/favicon.png') }}" rel="icon">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="{{ asset('css/admin/auto-payout/style.css') }}" rel="stylesheet">
    <style>
        /* Approval table styling */
        .table th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 1rem 0.75rem;
            vertical-align: middle;
        }

        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #e9ecef;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }

        .form-check-input {
            margin-top: 0;
        }

        /* Status badge colors */
        .bg-warning {
            background-color: #ffc107 !important;
            color: #000;
        }

        .bg-success {
            background-color: #198754 !important;
        }

        .bg-danger {
            background-color: #dc3545 !important;
        }

        /* Responsive table improvements */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.875rem;
            }

            .btn-group-sm .btn {
                padding: 0.2rem 0.4rem;
            }
        }

        /* Dashboard two-column layout */
        .dashboard-content-grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 20px;
        }
        @media (max-width: 992px) {
            .dashboard-content-grid {
                grid-template-columns: 1fr;
            }
        }

        .dash-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .dash-card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .dash-card-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .dash-card-subtitle {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .dash-card-body {
            padding: 20px;
        }

        /* Chart */
        .dash-chart-wrapper {
            padding: 16px 20px 8px;
            position: relative;
        }

        .dash-chart-total {
            position: absolute;
            top: 16px;
            right: 20px;
            font-size: 12.5px;
            color: #64748b;
        }

        .dash-chart-total strong {
            color: #1e293b;
            font-weight: 700;
        }

        .dash-chart-svg-container {
            width: 100%;
            position: relative;
            overflow: visible;
        }

        .dash-chart-tooltip {
            position: absolute;
            background: #1e293b;
            color: #fff;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 500;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.15s;
            white-space: nowrap;
            z-index: 200;
            transform: translate(-50%, -100%);
            margin-top: -6px;
        }

        .dash-chart-tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: #1e293b;
        }

        /* Tabs */
        .dash-tabs {
            display: flex;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 20px;
            background: #fff;
            gap: 0;
        }

        /* Tab strip + column toggle share a row so the toggle sits above the table */
        .dash-tabs-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }

        .dash-tabs-row .dash-tabs {
            border-bottom: none;
            flex: 1 1 auto;
            min-width: 0;
            overflow-x: auto;
        }

        .dash-tabs-row-end {
            justify-content: flex-end;
            padding: 8px 20px;
        }

        .dash-breakdown-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 0 20px;
            margin: 0;
            font-size: 12px;
            color: #64748b;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }

        .dash-tabs-row-end .dash-breakdown-toggle {
            padding: 0;
        }

        .dash-breakdown-toggle input {
            cursor: pointer;
            margin: 0;
        }

        /* Gross/fee/tax columns are opt-in -- see payout-breakdown-toggle in app.js */
        .payout-breakdown-hidden .col-gross,
        .payout-breakdown-hidden .col-fee,
        .payout-breakdown-hidden .col-tax {
            display: none;
        }

        /* Payout detail header.
           The fields used to be label/value table rows, one per line. On a laptop the
           three cards are ~330px wide, so every label wrapped onto two lines and the
           header ate the whole viewport -- the booking items table was never on screen.
           Laying the fields out as auto-fitting columns keeps the same information in
           roughly a third of the height. */
        /* The booking items list is the only thing that should scroll. Sizing its
           container off the viewport (calc(100vh - Npx)) needs the header and footer
           heights guessed correctly and was 48px out, which produced a second scrollbar
           on the modal body and pushed the pager out of sight. Chain flex heights from
           the modal body instead so the list gets exactly the space that is left. */
        #approvalPayoutDetailsModal .modal-body {
            padding: 0.75rem 1rem;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        #approvalPayoutDetailsModal #approvalPayoutDetailsContent {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
        }

        .payout-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.5rem 1rem;
        }

        .payout-meta-heading {
            grid-column: 1 / -1;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 2px;
        }

        .payout-meta-heading:not(:first-child) {
            margin-top: 0.35rem;
        }

        .payout-meta-label {
            display: block;
            font-size: 11px;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            line-height: 1.3;
        }

        .payout-meta-value {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #1e293b;
            line-height: 1.35;
            word-break: break-word;
        }

        /* Long values (reference, account id, the booking window) need two slots or
           they wrap into a tall block and undo the saving. */
        @media (min-width: 576px) {
            .payout-meta-wide {
                grid-column: span 2;
            }
        }

        .payout-activity-log {
            max-height: 160px;
            overflow-y: auto;
        }

        .dash-tab-btn {
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.15s;
            white-space: nowrap;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
            font-family: inherit;
        }

        .dash-tab-btn:hover {
            color: #1e293b;
        }

        .dash-tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            font-weight: 600;
        }

        /* Table */
        .dash-table-wrapper {
            overflow-x: auto;
        }

        .dash-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .dash-table thead th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .dash-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: background 0.1s;
        }

        .dash-table tbody tr:hover {
            background: #f8fafc;
        }

        .dash-table tbody tr:last-child {
            border-bottom: none;
        }

        .dash-table td {
            padding: 11px 14px;
            color: #334155;
            vertical-align: middle;
        }

        .dash-td-id {
            color: #2563eb;
            font-weight: 600;
            cursor: pointer;
            font-size: 12.5px;
            font-family: 'SF Mono', 'Fira Mono', monospace;
        }

        .dash-td-id:hover {
            text-decoration: underline;
        }

        .dash-td-amount {
            font-weight: 600;
            color: #1e293b;
            font-variant-numeric: tabular-nums;
        }

        .dash-td-fee {
            color: #dc2626;
            font-variant-numeric: tabular-nums;
        }

        .dash-td-net {
            font-weight: 700;
            color: #0f172a;
            font-variant-numeric: tabular-nums;
        }

        .dash-td-date {
            color: #64748b;
            font-size: 12.5px;
        }

        /* Dashboard badges */
        .dash-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }

        .dash-badge::before {
            content: '';
            width: 5px;
            height: 5px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.6;
        }

        .dash-badge-succeeded {
            background: #dcfce7;
            color: #15803d;
        }

        .dash-badge-completed {
            background: #dcfce7;
            color: #15803d;
        }

        .dash-badge-initiated {
            background: #1e293b;
            color: #fff;
        }

        .dash-badge-initiated::before {
            background: #fff;
        }

        .dash-badge-processing {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .dash-badge-pending {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .dash-badge-failed {
            background: #fee2e2;
            color: #b91c1c;
        }

        .dash-badge-scheduled {
            background: #1e293b;
            color: #fff;
        }

        .dash-badge-scheduled::before {
            background: #94a3b8;
        }

        .dash-table-footer {
            padding: 12px 14px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .dash-table-footer strong {
            color: #334155;
        }

        .dash-empty-state {
            padding: 40px 20px;
            text-align: center;
            color: #94a3b8;
            font-size: 13.5px;
        }

        /* Summary card */
        .dash-summary-rows {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .dash-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .dash-summary-row:last-child {
            border-bottom: none;
        }

        .dash-summary-row-label {
            font-size: 13px;
            color: #64748b;
        }

        .dash-summary-row-value {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
        }

        /* Next payout box */
        .dash-next-payout-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 14px 16px;
            margin-top: 16px;
        }

        .dash-next-payout-box-title {
            font-size: 11.5px;
            font-weight: 600;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }

        .dash-next-payout-date {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .dash-next-payout-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
        }

        .dash-next-payout-amount {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
        }

        /* This Period grid */
        .dash-period-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }

        .dash-period-grid-single {
            grid-template-columns: 1fr;
        }

        .dash-period-grid-single .dash-period-value {
            font-size: 22px;
        }

        .dash-period-item {
            text-align: center;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
        }

        .dash-period-item-highlight {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .dash-period-label {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }

        .dash-period-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
    </style>
</head>

@php
    $currencySettings = [
        'code' => currencyFormation(true, false),
        'symbol' => currencyFormation(false, true),
        'separator' => ',',
        'decimal' => '.',
        'precision' => 2,
    ];
@endphp
<input type="hidden" id="currency" value='@json($currencySettings)'>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar bg-white shadow-sm" id="sidebar">
            <div class="sidebar-header p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <div class="rounded d-flex align-items-center justify-content-center me-3">
                        <a href="{{ route(prefixedRouteName('dashboard')) }}"><img id="mainLogo"
                                src="{{ asset('images/logo.png') }}"><img src="" style="display:none"
                                id="mainLogoPrint"></a>
                    </div>
                    <span class="h4 mb-0 fw-bold text-dark">Auto Payouts</span>
                </div>
            </div>

            <div class="sidebar-nav p-3">
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="#" data-view="dashboard">
                            <i class="bi bi-house me-3"></i>
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" data-view="scheduler">
                            <i class="bi bi-calendar me-3"></i>
                            Payout Scheduler
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" data-view="reports">
                            <i class="bi bi-bar-chart me-3"></i>
                            Reports
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" data-view="approval">
                            <i class="bi bi-shield-check me-3"></i>
                            Approve Payouts
                            <span class="badge bg-warning ms-2" id="pending-approvals-badge" style="display: none;">0</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" data-view="adjustments">
                            <i class="bi bi-arrow-left-right me-3"></i>
                            Adjustments
                            <span class="badge bg-info ms-2" id="pending-adjustments-badge" style="display: none;">0</span>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" data-view="activity-log">
                            <i class="bi bi-journal-text me-3"></i>
                            Activity Log
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="#" data-view="about">
                            <i class="bi bi-info-circle me-3"></i>
                            About
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content flex-grow-1">
            <!-- Mobile Header -->
            <div class="mobile-header d-lg-none bg-white border-bottom p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <button class="btn btn-link p-0" id="sidebarToggle">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <div class="d-flex align-items-center">
                        <div class="rounded d-flex align-items-center justify-content-center me-2">
                            <a href="{{ route(prefixedRouteName('dashboard')) }}"><img id="mainLogo"
                                    src="{{ asset('images/logo.png') }}"><img src="" style="display:none"
                                    id="mainLogoPrint"></a>
                        </div>
                        <span class="h5 mb-0 fw-bold">Auto Payouts</span>
                    </div>
                    <div style="width: 24px;"></div>
                </div>
            </div>

            {{-- AUTO_PAYOUT global setting banner. Hidden by default;
                 JS toggles the alert-danger / alert-success variant based on
                 data.auto_payout_enabled returned from the dashboard endpoint.
                 Lives outside the view-content blocks so it is visible on
                 every tab while the service is disabled. --}}
            <div id="autoPayoutServiceStatus" class="px-4 pt-3 d-none">
                <div id="autoPayoutServiceBanner" class="alert d-flex align-items-center justify-content-between mb-0" role="alert">
                    <div class="d-flex align-items-center">
                        <i id="autoPayoutServiceIcon" class="me-2 fs-5"></i>
                        <div>
                            <strong id="autoPayoutServiceTitle"></strong>
                            <span id="autoPayoutServiceMessage" class="ms-1"></span>
                        </div>
                    </div>
                    <a id="autoPayoutServiceLink" href="#" class="btn btn-sm btn-outline-light d-none">
                        <i class="bi bi-gear me-1"></i>Go to Global Settings
                    </a>
                </div>
            </div>

            {{-- Latest scheduler-tick error banner. Populated by JS from
                 dashboardData.latest_run_error when the most recent run
                 outcome was 'error'. Clears automatically as soon as the
                 next tick succeeds. --}}
            <div id="latestRunErrorStatus" class="px-4 pt-3 d-none">
                <div class="alert alert-warning d-flex align-items-center justify-content-between mb-0" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div>
                            <strong>Last scheduler tick failed</strong>
                            <span id="latestRunErrorTimestamp" class="ms-1 text-muted small"></span>
                            <div id="latestRunErrorMessage" class="small mt-1 font-monospace"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-warning"
                            onclick="window.payoutSystem.switchView('activity-log'); return false;">
                        <i class="bi bi-list-ul me-1"></i>View Activity
                    </button>
                </div>
            </div>

            <!-- Dashboard View -->
            <div class="view-content" id="dashboard-view">
                <div class="container-fluid p-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between aligin-items-center">
                                <div>
                                    <h1 class="h2 fw-bold text-dark mb-1">Payout Dashboard</h1>
                                    <p class="text-muted mb-0">Manage automated payouts.</p>
                                </div>
                                <div class="d-flex gap-2" >
                                    <button class="btn btn-primary btn-sm d-none" style="min-width: 150px;" id="force-run-btn">
                                        <i class="bi bi-lightning-fill me-1"></i>Run Payout Now
                                    </button>
                                    <select class="form-select" id="periodSelect">
                                        <option value="7d">Last 7 days</option>
                                        <option value="30d" selected>Last 30 days</option>
                                        <option value="90d">Last 90 days</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Adjustments Alert (populated by JS) -->
                    <div id="pending-adjustments-alert-container"></div>

                    <!-- Failed Payouts Alert -->
                    <div class="row mb-3" id="failed-payouts-alert" style="display: none;">
                        <div class="col-12">
                            <div class="alert alert-danger d-flex align-items-center justify-content-between mb-0" role="alert">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                    <div>
                                        <strong id="failed-payouts-count">0</strong> payout(s) failed and require attention.
                                        <span class="text-muted small" id="failed-payouts-total"></span>
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-danger" onclick="payoutSystem.switchView('approval'); payoutSystem.filterFailedPayouts();">
                                    <i class="bi bi-arrow-right me-1"></i>View Failed Payouts
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stripe Schedule Warning -->
                    <div class="row mb-3" id="stripe-schedule-alert" style="display: none;">
                        <div class="col-12">
                            <div class="alert alert-warning d-flex align-items-center justify-content-between mb-0" role="alert">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                                    <div>
                                        Stripe payout schedule is set to '<strong id="stripe-schedule-interval"></strong>' instead of manual.
                                        Automatic Stripe payouts may conflict with this system.
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-warning" id="fix-stripe-schedule-btn">
                                    <i class="bi bi-wrench me-1"></i>Fix Now
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Pending Amount</p>
                                            <h3 class="fw-bold mb-1" id="pending-amount">
                                                {{ currencyFormation(false, true) }}0.00
                                            </h3>
                                            <small class="text-warning">Awaiting next payout cycle</small>
                                        </div>
                                        <div class="stat-icon bg-opacity-10 rounded">
                                            <i class="bi bi-clock text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Total Paid Out</p>
                                            <h3 class="fw-bold mb-1" id="total-paid-out">
                                                {{ currencyFormation(false, true) }}0.00
                                            </h3>
                                            <small class="text-success">
                                                <span id="paid-out-change">0%</span> vs last period
                                            </small>
                                        </div>
                                        <div class="stat-icon bg-opacity-10 rounded">
                                            <i class="bi bi-currency-dollar text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Active Packages</p>
                                            <h3 class="fw-bold mb-1" id="active-packages">0</h3>
                                            <small class="text-primary">Receiving payouts</small>
                                        </div>
                                        <div class="stat-icon bg-primary bg-opacity-10 rounded">
                                            <i class="bi bi-people text-primary"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Next Payout</p>
                                            <h3 class="fw-bold mb-1" id="next-payout">-</h3>
                                            <small class="text-info">Automated schedule</small>
                                        </div>
                                        <div class="stat-icon bg-info bg-opacity-10 rounded">
                                            <i class="bi bi-calendar text-info"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Two-column layout: Main + Sidebar -->
                    <div class="dashboard-content-grid">

                        <!-- LEFT: Recent Payouts card -->
                        <div class="dash-card">
                            <div class="dash-card-header">
                                <div>
                                    <div class="dash-card-title">Recent Payouts</div>
                                    <div class="dash-card-subtitle">Payout history and transactions</div>
                                </div>
                                <div style="display:flex;gap:8px;">
                                    <button class="btn btn-sm btn-outline-primary" id="viewAllBtnForReport">
                                        <i class="bi bi-eye me-1"></i>View All
                                    </button>
                                </div>
                            </div>

                            <!-- SVG Chart -->
                            <div class="dash-chart-wrapper" id="dashChartWrapper">
                                <div class="dash-chart-total">Total <strong id="dash-chart-total-value">{{ currencyFormation(false, true) }}0.00</strong></div>
                                <div class="dash-chart-svg-container" id="dashChartContainer">
                                    <div class="dash-chart-tooltip" id="dashChartTooltip"></div>
                                </div>
                            </div>

                            <!-- Tabs -->
                            <div class="dash-tabs-row">
                                <div class="dash-tabs" id="dashTabs">
                                    <button class="dash-tab-btn active" onclick="payoutSystem.switchDashTab(this, 'payouts')">Payouts</button>
                                    <button class="dash-tab-btn" onclick="payoutSystem.switchDashTab(this, 'pending')">Pending Payouts</button>
                                    <button class="dash-tab-btn" onclick="payoutSystem.switchDashTab(this, 'adjustments')">Pending Adjustments</button>
                                    <button class="dash-tab-btn" onclick="payoutSystem.switchDashTab(this, 'upcoming')">Upcoming Bookings</button>
                                </div>
                                <label class="dash-breakdown-toggle" title="Show the gross amount and platform fees behind each net payout">
                                    <input type="checkbox" class="payout-breakdown-toggle">
                                    <span>Show fee breakdown</span>
                                </label>
                            </div>

                            <!-- Table -->
                            <div class="dash-table-wrapper" id="dashTableContainer">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>Payout ID</th>
                                            <th class="col-gross">Gross</th>
                                            <th class="col-fee">Fees</th>
                                            <th>Net Total</th>
                                            <th class="col-tax">Tax</th>
                                            <th>Paid On</th>
                                            <th>Activity Before</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashTableBody">
                                    </tbody>
                                </table>
                                <div class="dash-table-footer" id="dashTableFooter">
                                    <strong>0 rows</strong>&nbsp;&middot;&nbsp;<strong>{{ currencyFormation(false, true) }}0.00 net</strong>
                                </div>
                            </div>

                            <!-- Pending Payouts Table -->
                            <div class="dash-table-wrapper" id="dashPendingTableContainer" style="display:none;">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>Payout ID</th>
                                            <th class="col-gross">Amount</th>
                                            <th class="col-fee">Fees</th>
                                            <th>Net Total</th>
                                            <th>Bookings</th>
                                            <th>Created</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashPendingTableBody"></tbody>
                                </table>
                                <div class="dash-table-footer" id="dashPendingTableFooter"></div>
                            </div>

                            <!-- Pending Adjustments Table -->
                            <div class="dash-table-wrapper" id="dashAdjustmentsTableContainer" style="display:none;">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>Booking</th>
                                            <th>Type</th>
                                            <th>Net Amount</th>
                                            <th>Original Payout</th>
                                            <th>Reason</th>
                                            <th>Detected</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashAdjustmentsTableBody"></tbody>
                                </table>
                                <div class="dash-table-footer" id="dashAdjustmentsTableFooter"></div>
                            </div>

                            <!-- Upcoming Bookings Table -->
                            <div class="dash-table-wrapper" id="dashUpcomingTableContainer" style="display:none;">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Tour Date</th>
                                            <th>Tourists</th>
                                            <th>Amount Paid</th>
                                            <th class="col-fee">Fees</th>
                                            <th>Eligible In</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dashUpcomingTableBody"></tbody>
                                </table>
                                <div class="dash-table-footer" id="dashUpcomingTableFooter"></div>
                            </div>

                            <div id="dashTabEmpty" style="display:none;" class="dash-empty-state">
                                <i class="bi bi-file-earmark" style="font-size:40px;opacity:0.3;display:block;margin:0 auto 12px;"></i>
                                No data for this period.
                            </div>
                        </div>

                        <!-- RIGHT: Sidebar -->
                        <div>
                            <!-- Payout Summary card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">Payout Summary</div>
                                </div>
                                <div class="dash-card-body">
                                    <div class="dash-summary-rows">
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Average Payout</span>
                                            <span class="dash-summary-row-value" id="average-payout">{{ currencyFormation(false, true) }}0.00</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Total Payouts</span>
                                            <span class="dash-summary-row-value" id="total-payouts">0</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Success Rate</span>
                                            <span class="dash-summary-row-value" style="color:#16a34a;" id="success-rate">0%</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Processing Time</span>
                                            <span class="dash-summary-row-value" id="processing-time">2-3 days</span>
                                        </div>
                                    </div>

                                    <div class="dash-next-payout-box">
                                        <div class="dash-next-payout-box-title">Next Payout</div>
                                        <div class="dash-next-payout-date" id="dash-next-payout-date">Not scheduled</div>
                                        <div class="dash-next-payout-meta">
                                            <div class="dash-next-payout-amount" id="dash-next-payout-amount">{{ currencyFormation(false, true) }}0.00</div>
                                            <span class="badge bg-dark text-white">Scheduled</span>
                                        </div>
                                        <div style="font-size:11.5px;color:#94a3b8;margin-top:6px;">Net amount after fees</div>
                                    </div>
                                </div>
                            </div>

                            <!-- This Period card -->
                            <div class="dash-card" style="margin-top:16px;">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">This Period</div>
                                </div>
                                <div class="dash-card-body" style="padding:16px 20px;">
                                    {{-- Net only. The gross/fees/tax breakdown reads as extra charges at a
                                         glance -- it lives in the table behind "Show fee breakdown" instead. --}}
                                    <div class="dash-period-grid dash-period-grid-single">
                                        <div class="dash-period-item dash-period-item-highlight">
                                            <div class="dash-period-label" style="color:#2563eb;">Net Total</div>
                                            <div class="dash-period-value" style="color:#1d4ed8;" id="dash-period-net">{{ currencyFormation(false, true) }}0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Scheduler View -->
            <div class="view-content d-none" id="scheduler-view">
                <div class="container-fluid p-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h2 fw-bold text-dark mb-1">Payout Scheduler</h1>
                                    <p class="text-muted mb-0">Configure automated payout rules for your packages</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Banner -->
                    <div class="alert alert-info d-flex align-items-start mb-4">
                        <i class="bi bi-info-circle me-3 mt-1"></i>
                        <div>
                            <h6 class="alert-heading">Auto Payout Information</h6>
                            <p class="mb-0 small">Payouts will only be processed for bookings that have been completed
                                and passed their service date. The system automatically filters out future or ongoing
                                bookings to ensure accurate payout calculations.</p>
                        </div>
                    </div>

                    <!-- Payout Rules -->
                    <div id="payoutRulesList">
                        <!-- Rules will be populated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Reports View -->
            <div class="view-content d-none" id="reports-view">
                <div class="container-fluid p-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h2 fw-bold text-dark mb-1">Reports</h1>
                                    <p class="text-muted mb-0">Detailed payout analytics and history</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-secondary" onclick="payoutSystem.loadReports()">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Range + Filters Bar -->
                    <div class="dash-card mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2" style="padding: 12px 20px;">
                            <div class="d-flex align-items-center gap-3">
                                <span class="fw-semibold" id="rp-date-range-label">--</span>
                                <div class="input-group input-group-sm" style="width: 220px;">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" placeholder="Search by payout ID..." id="searchInput">
                                </div>
                                <div class="input-group input-group-sm" style="width: 180px;">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" placeholder="Booking ID..." id="bookingIdSearch">
                                </div>
                            </div>
                            <div class="d-flex gap-2 align-items-center">
                                <select class="form-select form-select-sm" id="periodFilter" style="width: auto;">
                                    <option value="7d">Last 7 days</option>
                                    <option value="30d" selected>Last 30 days</option>
                                    <option value="90d">Last 90 days</option>
                                    <option value="1y">Last year</option>
                                    <option value="custom">Custom range</option>
                                </select>
                                <div class="d-flex gap-1 align-items-center d-none" id="customDateRange">
                                    <input type="date" class="form-control form-control-sm" id="reportDateFrom" style="width: 140px;">
                                    <span class="text-muted small">to</span>
                                    <input type="date" class="form-control form-control-sm" id="reportDateTo" style="width: 140px;">
                                    <button class="btn btn-sm btn-primary" id="applyCustomDateRange">Go</button>
                                </div>
                                <select class="form-select form-select-sm" id="statusFilter" style="width: auto;">
                                    <option value="all">All Statuses</option>
                                    <option value="completed" selected='selected'>Completed</option>
                                    <option value="processing">Processing</option>
                                    <option value="pending_approval">Pending Approval</option>
                                    <option value="approved">Approved</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Two-column layout: Main + Sidebar -->
                    <div class="dashboard-content-grid">

                        <!-- LEFT: Chart + Table -->
                        <div class="dash-card">
                            <div class="dash-card-header">
                                <div>
                                    <div class="dash-card-title">Payouts</div>
                                    <div class="dash-card-subtitle">Transaction history for this period</div>
                                </div>
                                <div class="dash-chart-total">Total <strong id="rp-chart-total-value">{{ currencyFormation(false, true) }}0.00</strong></div>
                            </div>

                            <!-- SVG Chart -->
                            <div class="dash-chart-wrapper" id="rpChartWrapper">
                                <div class="dash-chart-svg-container" id="rpChartContainer">
                                    <div class="dash-chart-tooltip" id="rpChartTooltip"></div>
                                </div>
                            </div>

                            <!-- Table -->
                            <div class="dash-tabs-row dash-tabs-row-end">
                                <label class="dash-breakdown-toggle" title="Show the gross amount and platform fees behind each net payout">
                                    <input type="checkbox" class="payout-breakdown-toggle">
                                    <span>Show fee breakdown</span>
                                </label>
                            </div>
                            <div class="dash-table-wrapper" id="rpTableContainer">
                                <table class="dash-table">
                                    <thead>
                                        <tr>
                                            <th>Payout ID</th>
                                            <th class="col-gross">Gross</th>
                                            <th class="col-fee">Fees</th>
                                            <th>Net Total</th>
                                            <th>Bookings</th>
                                            <th>Paid On</th>
                                            <th>Status</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="transactionsTableBody">
                                    </tbody>
                                </table>
                                <div class="dash-table-footer" id="rpTableFooter">
                                    <strong>0 rows</strong>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT: Sidebar -->
                        <div>
                            <!-- Payout Summary card -->
                            <div class="dash-card">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">Summary</div>
                                </div>
                                <div class="dash-card-body">
                                    <div class="dash-summary-rows">
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Total Paid Out</span>
                                            <span class="dash-summary-row-value" id="rp-total-payout-amount">{{ currencyFormation(false, true) }}0.00</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">vs Last Period</span>
                                            <span class="dash-summary-row-value" id="rp-paid-out-change" style="color:#16a34a;">0%</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Total Payouts</span>
                                            <span class="dash-summary-row-value" id="rp-successful-payouts">0</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Success Rate</span>
                                            <span class="dash-summary-row-value" style="color:#16a34a;" id="rp-success-rate">0%</span>
                                        </div>
                                        <div class="dash-summary-row">
                                            <span class="dash-summary-row-label">Average Payout</span>
                                            <span class="dash-summary-row-value" id="rp-average-payout">{{ currencyFormation(false, true) }}0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- This Period card -->
                            <div class="dash-card" style="margin-top:16px;">
                                <div class="dash-card-header">
                                    <div class="dash-card-title">This Period</div>
                                </div>
                                <div class="dash-card-body" style="padding:16px 20px;">
                                    {{-- Net only -- see the dashboard "This Period" card above. --}}
                                    <div class="dash-period-grid dash-period-grid-single">
                                        <div class="dash-period-item dash-period-item-highlight">
                                            <div class="dash-period-label" style="color:#2563eb;">Net Total</div>
                                            <div class="dash-period-value" style="color:#1d4ed8;" id="rp-period-net">{{ currencyFormation(false, true) }}0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Approval View -->
            <div class="view-content d-none" id="approval-view">
                <div class="container-fluid p-4">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h2 fw-bold text-dark mb-1">Payout Approval Queue</h1>
                                    <p class="text-muted mb-0">Review and approve pending payouts</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-secondary" id="refreshApprovalQueue">
                                        <i class="bi bi-arrow-clockwise me-2"></i>
                                        Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Pending Approvals</p>
                                            <h3 class="fw-bold mb-1" id="approval-pending-count">0</h3>
                                            <small class="text-warning">Awaiting review</small>
                                        </div>
                                        <div class="stat-icon bg-opacity-10 rounded">
                                            <i class="bi bi-clock text-warning"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Approved Today</p>
                                            <h3 class="fw-bold mb-1" id="approval-approved-today">0</h3>
                                            <small class="text-success">Processed</small>
                                        </div>
                                        <div class="stat-icon bg-opacity-10 rounded">
                                            <i class="bi bi-check-circle text-success"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Rejected Today</p>
                                            <h3 class="fw-bold mb-1" id="approval-rejected-today">0</h3>
                                            <small class="text-danger">Declined</small>
                                        </div>
                                        <div class="stat-icon bg-opacity-10 rounded">
                                            <i class="bi bi-x-circle text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card stat-card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <p class="text-muted small mb-1">Failed Payouts</p>
                                            <h3 class="fw-bold mb-1" id="approval-failed-count">0</h3>
                                            <small class="text-danger" id="approval-failed-amount">{{ currencyFormation(false, true) }}0.00</small>
                                        </div>
                                        <div class="stat-icon bg-opacity-10 rounded">
                                            <i class="bi bi-exclamation-triangle text-danger"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters and Search -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-search"></i>
                                        </span>
                                        <input type="text" class="form-control" placeholder="Search by reference..." id="approvalSearchInput">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <select class="form-select" id="approvalStatusFilter">
                                        <option value="pending_approval">Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="failed">Failed</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="all">All Status</option>
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <input type="number" class="form-control" placeholder="Min Amount" id="approvalMinAmount">
                                </div>
                                <div class="col-lg-2">
                                    <input type="number" class="form-control" placeholder="Max Amount" id="approvalMaxAmount">
                                </div>
                                <div class="col-lg-2">
                                    <button class="btn btn-primary w-100" id="applyApprovalFilters">
                                        <i class="bi bi-funnel me-1"></i>
                                        Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="card mb-4" id="approvalBulkActions" style="display: none;">
                        <div class="card-body bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-semibold">
                                        <span id="approvalSelectedCount">0</span> payout(s) selected
                                    </span>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm" id="bulkApproveBtn">
                                        <i class="bi bi-check-circle me-1"></i>
                                        Approve Selected
                                    </button>
                                    <button class="btn btn-danger btn-sm" id="bulkRejectBtn">
                                        <i class="bi bi-x-circle me-1"></i>
                                        Reject Selected
                                    </button>
                                    <button class="btn btn-outline-secondary btn-sm" id="clearSelectionBtn">
                                        Clear Selection
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payouts List -->
                    <div class="card">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-semibold">Pending Payouts</h5>
                                <span class="text-muted small" id="approvalPayoutCount">0 payouts</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="selectAllPayouts">
                                                </div>
                                            </th>
                                            <th style="width: 200px;">Payout Reference</th>
                                            <th style="width: 150px;">Amount</th>
                                            <th style="width: 100px;">Status</th>
                                            <th style="width: 120px;">Created</th>
                                            <th style="width: 150px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="approvalPayoutsList">
                                        <!-- Payouts will be loaded here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination" id="approvalPagination">
                                <!-- Pagination will be generated here -->
                            </ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Activity Log View -->
            <div class="view-content d-none" id="activity-log-view">
                <div class="container-fluid p-4">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h2 fw-bold text-dark mb-1">Activity Log</h1>
                                    <p class="text-muted mb-0">Track all payout events and system actions</p>
                                </div>
                                <button class="btn btn-outline-secondary" id="refreshActivityLog">
                                    <i class="bi bi-arrow-clockwise me-2"></i>
                                    Refresh
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Scheduler Runs: backed by auto_payout_run_logs. One row per
                         minute (or per tick) from ProcessAutoPayouts. This is the
                         diagnostic surface for "why aren't payouts running" --
                         the most recent outcome explains exactly which guard fired. --}}
                    <div class="card mb-4">
                        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0 fw-semibold">Scheduler Runs</h5>
                                <small class="text-muted">Latest payout scheduler ticks (one row per run)</small>
                            </div>
                            <button class="btn btn-sm btn-outline-secondary" id="refreshSchedulerRuns">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 180px;">Timestamp</th>
                                            <th style="width: 200px;">Outcome</th>
                                            <th style="width: 90px;">Created</th>
                                            <th style="width: 90px;">Executed</th>
                                            <th style="width: 90px;">Duration</th>
                                            <th>Message</th>
                                        </tr>
                                    </thead>
                                    <tbody id="schedulerRunsBody">
                                        <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text" class="form-control" placeholder="Search by payout reference..." id="activityLogSearch">
                                    </div>
                                </div>
                                <div class="col-lg-2">
                                    <select class="form-select" id="activityLogActionFilter">
                                        <option value="all">All Actions</option>
                                        <option value="created">Created</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                        <option value="executed">Executed</option>
                                        <option value="failed">Failed</option>
                                        <option value="cancelled">Cancelled</option>
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <select class="form-select" id="activityLogPeriodFilter">
                                        <option value="7d">Last 7 days</option>
                                        <option value="30d" selected>Last 30 days</option>
                                        <option value="90d">Last 90 days</option>
                                        <option value="1y">Last year</option>
                                        <option value="custom">Custom range</option>
                                    </select>
                                </div>
                                <div class="col-lg-3 d-none" id="activityLogCustomDates">
                                    <div class="d-flex gap-1 align-items-center">
                                        <input type="date" class="form-control form-control-sm" id="activityLogDateFrom">
                                        <span class="text-muted small">to</span>
                                        <input type="date" class="form-control form-control-sm" id="activityLogDateTo">
                                    </div>
                                </div>
                                <div class="col-lg-1">
                                    <button class="btn btn-primary w-100" id="applyActivityLogFilters">
                                        <i class="bi bi-funnel me-1"></i>
                                        Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-semibold">Event History</h5>
                                <span class="text-muted small" id="activityLogCount">0 events</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 180px;">Timestamp</th>
                                            <th style="width: 100px;">Action</th>
                                            <th style="width: 200px;">Payout Reference</th>
                                            <th style="width: 120px;">User</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody id="activityLogBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination" id="activityLogPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>

            <!-- Adjustments View -->
            <div class="view-content d-none" id="adjustments-view">
                <div class="container-fluid p-4">
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h1 class="h2 fw-bold text-dark mb-1">Payout Adjustments</h1>
                                    <p class="text-muted mb-0">Post-payout amendments, refunds, and corrections</p>
                                </div>
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-primary" id="detectAdjustmentsBtn">
                                        <i class="bi bi-search me-2"></i>
                                        Detect Adjustments
                                    </button>
                                    <button class="btn btn-outline-secondary" id="refreshAdjustmentsBtn">
                                        <i class="bi bi-arrow-clockwise me-2"></i>
                                        Refresh
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mb-4" id="adjustmentSummaryCards">
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-muted small mb-1">Pending Count</div>
                                    <div class="h4 fw-bold mb-0" id="adj-pending-count">0</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-muted small mb-1">Pending Net</div>
                                    <div class="h4 fw-bold mb-0" id="adj-pending-net">$0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-muted small mb-1">Credits (owed)</div>
                                    <div class="h4 fw-bold text-success mb-0" id="adj-credit-total">$0.00</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body text-center">
                                    <div class="text-muted small mb-1">Debits (clawback)</div>
                                    <div class="h4 fw-bold text-danger mb-0" id="adj-debit-total">$0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="row g-3 align-items-center">
                                <div class="col-lg-3">
                                    <select class="form-select" id="adjustmentStatusFilter">
                                        <option value="">All Statuses</option>
                                        <option value="pending">Pending</option>
                                        <option value="applied">Applied</option>
                                        <option value="voided">Voided</option>
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <select class="form-select" id="adjustmentTypeFilter">
                                        <option value="">All Types</option>
                                        <option value="credit">Credits</option>
                                        <option value="debit">Debits</option>
                                    </select>
                                </div>
                                <div class="col-lg-2">
                                    <input type="text" class="form-control" placeholder="Booking ID" id="adjustmentBookingFilter">
                                </div>
                                <div class="col-lg-2">
                                    <input type="text" class="form-control" placeholder="Payout Ref" id="adjustmentPayoutRefFilter">
                                </div>
                                <div class="col-lg-2">
                                    <button class="btn btn-primary w-100" id="applyAdjustmentFilters">
                                        <i class="bi bi-funnel me-1"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Adjustments Table -->
                    <div class="card">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 fw-semibold">Adjustment Ledger</h5>
                                <span class="text-muted small" id="adjustmentCount">0 adjustments</span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 80px;">ID</th>
                                            <th style="width: 100px;">Booking</th>
                                            <th style="width: 80px;">Type</th>
                                            <th style="width: 100px;">Amount</th>
                                            <th style="width: 80px;">Fee</th>
                                            <th style="width: 100px;">Net</th>
                                            <th style="width: 160px;">Original Payout</th>
                                            <th>Reason</th>
                                            <th style="width: 90px;">Status</th>
                                            <th style="width: 100px;">Detected</th>
                                            <th style="width: 80px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="adjustmentTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center mt-4">
                        <nav>
                            <ul class="pagination" id="adjustmentPagination"></ul>
                        </nav>
                    </div>
                </div>
            </div>
            <!-- About View -->
            <div class="view-content d-none" id="about-view">
                <div class="container-fluid p-4" style="max-width: 900px;">
                    <h1 class="h2 fw-bold text-dark mb-4">How Auto Payouts Work</h1>

                    <div class="card mb-4 border-primary">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Enabling Auto Payouts</h5>
                            <p>Auto Payouts must be enabled before it will process any payouts:</p>
                            <ol>
                                <li>Go to <strong>Global Settings</strong> and set <strong>AUTO_PAYOUT</strong> to <strong>true</strong>.</li>
                                <li>Return to this page and configure a payout rule in the <strong>Scheduler</strong> tab (frequency, time, minimum amount, etc.).</li>
                                <li>Toggle the rule to <strong>Enabled</strong> and save.</li>
                            </ol>
                            <p class="mb-0">If AUTO_PAYOUT is not enabled in Global Settings, the scheduled payout command will skip processing entirely, even if a rule is configured and enabled here.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Overview</h5>
                            <p>Auto Payouts automatically calculates what is owed to the tour operator based on completed card transactions, subtracts platform fees and any refunds, and sends the net amount to the operator's connected bank account via Stripe.</p>
                            <p class="mb-0">Only <strong>card payments</strong> with a status of <strong>Completed Bookings</strong> or <strong>Refunded Bookings <small>(partial)</small></strong>  with a positive balance are included. Cash, wire transfers, and manually inserted transactions are excluded.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Frequency and Scheduling</h5>
                            <p>You choose how often payouts are calculated:</p>
                            <ul>
                                <li><strong>Daily</strong> -- Runs once every day at your chosen time.</li>
                                <li><strong>Weekly</strong> -- Runs once per week on the day you select (e.g., every Monday at 9:00 AM).</li>
                                <li><strong>Monthly</strong> -- Runs once per month on the date you select (e.g., the 1st of each month).</li>
                            </ul>
                            <p>Each run looks at bookings with tour dates that fall within the window since the last run. If a payout was last processed on March 1st and runs again on March 8th, it picks up all qualifying bookings with tour dates between those two dates.</p>
                            <p>A new payout is created <strong>every cycle</strong>, regardless of whether previous payouts are still pending approval. Each payout only includes bookings that have not yet been paid out. If you have multiple pending payouts queued up, the system ensures no booking appears in more than one executed payout.</p>
                            <p class="mb-0"><strong>Minimum Amount</strong> -- If the calculated payout is below this threshold, it is skipped and carried forward to the next cycle.</p>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Buffer Days</h5>
                            <p>Buffer days add a waiting period before a booking becomes eligible for payout. This gives time for refunds to be processed before money is sent out.</p>
                            <p><strong>Example:</strong> If buffer days is set to <strong>3</strong> and the payout runs on March 10th, only bookings with tour dates on or before <strong>March 7th</strong> are included. Bookings from March 8th, 9th, and 10th are held back until the next cycle.</p>
                            <p>The system also extends the <em>start</em> of the calculation window back by the same number of buffer days. That way bookings that were buffered out of the previous cycle are picked up in the next one. The <code>payout_made</code> flag on each booking prevents anything from being paid twice.</p>
                            <p class="mb-0">This reduces the chance of paying out a booking that gets refunded shortly after the tour. A booking is never paid out twice -- once included in a payout, it is permanently marked as paid.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Maximum Payout Ceiling</h5>
                            <p class="mb-0">An optional safety limit. If the calculated payout exceeds this amount, the system will block the payout and flag it for review. This protects against unexpected calculation errors resulting in an oversized transfer. Leave it empty if no limit is needed.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Approval Workflow</h5>
                            <p>When <strong>Require Approval</strong> is turned on, the system does not send money automatically. Instead, it creates a <strong>pending payout</strong> that an admin must review and approve before it is executed.</p>
                            <ul>
                                <li><strong>Pending Approval</strong> -- Payout is calculated and waiting for an admin to review.</li>
                                <li><strong>Approved</strong> -- Admin has approved; the system will execute it on the next processing cycle.</li>
                                <li><strong>Rejected</strong> -- Admin rejected it with a reason. No money is sent.</li>
                                <li><strong>Executed</strong> -- Money has been sent to the operator's bank account via Stripe.</li>
                            </ul>
                            <p>Before execution, the system automatically <strong>recalculates</strong> the payout against current transaction data:</p>
                            <ul>
                                <li>If a booking was <strong>refunded</strong> between approval and execution, the refund is deducted and the payout amount is updated.</li>
                                <li>Fully refunded bookings are <strong>removed entirely</strong> from the payout.</li>
                                <li>Bookings that were already <strong>paid out by another payout</strong> are automatically removed to prevent double-payment.</li>
                            </ul>
                            <p class="mb-0">You can also manually click <strong>Recalculate</strong> in the payout detail view at any time to refresh the amounts.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Notification Emails</h5>
                            <p>Email addresses added to the payout rule will receive notifications when:</p>
                            <ul>
                                <li><strong>Pending Approval</strong> -- A new payout has been created and is waiting for review.</li>
                                <li><strong>Executed</strong> -- A payout has been approved and the money has been sent to the bank account.</li>
                                <li><strong>Failed</strong> -- A payout failed to process after 3 retry attempts and needs attention.</li>
                            </ul>
                            <p class="mb-0">If no notification emails are configured on the rule, all admin users with an email address on file will be notified instead.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Payout Detail View</h5>
                            <p>Click on any payout reference to open the detail view. Each booking in the payout shows:</p>
                            <ul>
                                <li><strong>Booking Total</strong> -- The original amount the customer was charged.</li>
                                <li><strong>Collected</strong> -- The actual amount collected via card payments.</li>
                                <li><strong>Refunded</strong> -- Total refund amount and number of refund transactions (shown in red if any).</li>
                                <li><strong>Fee</strong> -- Platform processing fees deducted.</li>
                                <li><strong>Net</strong> -- The amount being paid out for this booking.</li>
                                <li><strong>Booking Status</strong> -- Whether the booking is Booked, Completed, or Cancelled.</li>
                                <li><strong>Payout Status</strong> -- Whether the booking has post-payout amendments (Amended) or is unchanged (OK).</li>
                            </ul>
                            <p>Booking IDs are clickable links that open the full booking detail page in a new tab for further investigation.</p>
                            <p>The dashboard and report tables show the <strong>net</strong> paid out. Tick <strong>Show fee breakdown</strong> above either table to reveal the gross amount and the platform fees behind that net. The choice is remembered on this browser.</p>
                            <p class="mb-0">Use <strong>Export CSV</strong> to download all booking items for a payout, including adjustment flags, for offline accounting review. The export always includes the fee columns regardless of the toggle.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Adjustments</h5>
                            <p>Sometimes a booking is amended <strong>after</strong> its payout has already been sent -- for example, a partial refund or an additional payment. The adjustment system handles this:</p>
                            <ul>
                                <li><strong>Automatic detection</strong> -- When the system runs, it checks for any new payments or refunds on bookings that were already paid out. If found, it creates an adjustment record.</li>
                                <li><strong>Credit</strong> -- An additional payment was received after payout. The operator is owed more. This amount is <strong>added</strong> to the next payout.</li>
                                <li><strong>Debit</strong> -- A refund was issued after payout. The operator was overpaid. This amount is <strong>deducted</strong> from the next payout.</li>
                                <li><strong>Manual adjustments</strong> -- Admins can also create adjustments manually from the payout detail view (e.g., for corrections or negotiated discounts).</li>
                                <li><strong>Void</strong> -- If an adjustment was created in error, it can be voided. Voided adjustments are permanently excluded from future payouts.</li>
                            </ul>
                            <p class="mb-0">All pending adjustments are applied when the next payout executes. Each adjustment records which payout it originated from and which payout it was settled in, providing a complete audit trail.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">How the Payout Amount is Calculated</h5>
                            <p>For each qualifying booking:</p>
                            <ol>
                                <li>Sum all <strong>Completed</strong> card transaction amounts</li>
                                <li>Subtract all <strong>Refund</strong> transaction amounts (minus the refund fees returned to the platform)</li>
                                <li>Subtract <strong>platform fees</strong></li>
                                <li>The result is the <strong>net amount</strong> owed to the operator for that booking</li>
                            </ol>
                            <p>If <strong>Include Partial Payments</strong> is off, only bookings where the total payments equal or exceed the booking total are included. This prevents paying out bookings that are only partially paid.</p>
                            <p class="mb-0">The final payout total is: <strong>sum of all booking net amounts + pending credit adjustments - pending debit adjustments</strong>.</p>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">Important Notes</h5>
                            <ul class="mb-0">
                                <li>The Stripe connected account ID is read from the <strong>Tour Operator</strong> settings. The system verifies the account is set to <strong>manual payout mode</strong> before every execution. If it has been changed externally, the system attempts to auto-correct it. Use the <strong>Fix Stripe Schedule</strong> button on the dashboard to manually reset it to manual.</li>
                                <li>Each booking can only be paid out <strong>once</strong>. While a booking may appear in multiple pending payouts (created by daily runs before approval), the system automatically removes already-paid bookings during recalculation before execution. Money is never sent twice for the same booking.</li>
                                <li>Bookings with comments starting with <strong>#</strong> are excluded from payouts (used for internal/test bookings).</li>
                                <li>Manually inserted transactions (<strong>request_from = Insert</strong>) are excluded from payout calculations.</li>
                                <li>All actions (approvals, rejections, executions, recalculations, failures, cancellations) are logged in the <strong>Activity Log</strong> with timestamps, user details, and IP addresses.</li>
                                <li>If a payout fails (e.g., insufficient Stripe balance), it will retry up to <strong>3 times</strong> before being marked as permanently failed.</li>
                                <li>Failed payouts can be <strong>re-approved</strong> for another round of retries, or <strong>cancelled</strong> permanently.</li>
                                <li>If the payout rule is set to <strong>disabled</strong>, or the <strong>AUTO_PAYOUT</strong> global setting is off, no payouts will be created or processed.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Void Adjustment Modal -->
    <div class="modal fade" id="voidAdjustmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Void Adjustment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">This will remove the adjustment from the next payout calculation. This cannot be undone.</p>
                    <input type="hidden" id="voidAdjustmentId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="voidAdjustmentReason" rows="3" placeholder="Explain why this adjustment is being voided..." minlength="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmVoidAdjustment">
                        <i class="bi bi-x-circle me-1"></i> Void Adjustment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Payout Details Modal -->
    <div class="modal fade" id="approvalPayoutDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payout Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="approvalPayoutDetailsContent">
                        <!-- Details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-outline-secondary" id="modalExportCsvBtn">
                        <i class="bi bi-download me-1"></i>
                        Export CSV
                    </button>
                    <button type="button" class="btn btn-warning" id="modalRecalculateBtn" style="display: none;">
                        <i class="bi bi-arrow-repeat me-1"></i>
                        Recalculate
                    </button>
                    <button type="button" class="btn btn-success" id="modalApproveBtn" style="display: none;">
                        <i class="bi bi-check-circle me-1"></i>
                        Approve
                    </button>
                    <button type="button" class="btn btn-danger" id="modalRejectBtn" style="display: none;">
                        <i class="bi bi-x-circle me-1"></i>
                        Reject
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="approvalRejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Payout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Please provide a reason for rejecting this payout:</p>
                    <div class="mb-3">
                        <label for="approvalRejectionReason" class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="approvalRejectionReason" rows="3" placeholder="Explain why this payout is being rejected..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmApprovalRejectionBtn">
                        <i class="bi bi-x-circle me-1"></i>
                        Reject Payout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Recipients Modal -->
    <div class="modal fade" id="notificationEmailsModal" tabindex="-1" aria-labelledby="notificationEmailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="notificationEmailsModalLabel">Notification Recipients</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Add email addresses that should receive payout notifications. If no recipients are added, notifications will be sent to all admins.</p>
                    <div class="input-group mb-3">
                        <input type="email" class="form-control" id="notificationEmailInput" placeholder="Enter email address">
                        <button type="button" class="btn btn-primary" id="addNotificationEmail">
                            <i class="bi bi-plus-lg"></i> Add
                        </button>
                    </div>
                    <div id="notificationEmailList">
                        <!-- Email list rendered by JS -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script type="text/javascript">
        const fetchRuleUrl = "{{ route('admin.auto-payout.fetch-rule') }}";
        const storeRuleUrl = "{{ route('admin.auto-payout.store-rule') }}";
        const fetchDashboardUrl = "{{ route('admin.auto-payout.dashboard') }}";
        const fetchPayoutReportUrl = "{{ route('admin.auto-payout.report') }}";
        const fetchAutoPayoutDetailsUrl = "{{ route('admin.auto-payout.details', ':id') }}";
        const exportAutoPayoutUrl = "{{ route('admin.auto-payout.export', ':id') }}";
        const payoutSummaryUrl = "{{ route('admin.auto-payout.summary', ':id') }}";
        const fixStripeScheduleUrl = "{{ route('admin.auto-payout.fix-stripe-schedule') }}";
        const forceRunPayoutUrl = "{{ route('admin.auto-payout.force-run') }}";
        const fetchRunLogUrl = "{{ route('admin.auto-payout.run-log') }}";
        const isAdminUser = "{{ session('userType') == 'Admin' ? true : false}} ";

        // Booking detail URL (opens in new tab)
        const bookingDetailUrl = "{{ route('admin.tour-operator.booking.show', ':id') }}";

        // Approval API URLs
        const approvalApiUrls = {
            dashboardData: "{{ route('admin.auto-payout.approval.dashboard-data') }}",
            pending: "{{ route('admin.auto-payout.approval.pending') }}",
            details: "{{ route('admin.auto-payout.approval.details', ':id') }}",
            export: "{{ route('admin.auto-payout.approval.export', ':id') }}",
            summary: "{{ route('admin.auto-payout.approval.summary', ':id') }}",
            approve: "{{ route('admin.auto-payout.approval.approve', ':id') }}",
            reject: "{{ route('admin.auto-payout.approval.reject', ':id') }}",
            bulkApprove: "{{ route('admin.auto-payout.approval.bulk-approve') }}",
            bulkReject: "{{ route('admin.auto-payout.approval.bulk-reject') }}",
            activityLog: "{{ route('admin.auto-payout.approval.activity-log') }}",
            reapprove: "{{ route('admin.auto-payout.approval.reapprove', ':id') }}",
            cancel: "{{ route('admin.auto-payout.approval.cancel', ':id') }}",
            recalculate: "{{ route('admin.auto-payout.approval.recalculate', ':id') }}"
        };

        // Adjustment API URLs
        const adjustmentApiUrls = {
            index: "{{ route('admin.auto-payout.adjustments.index') }}",
            summary: "{{ route('admin.auto-payout.adjustments.summary') }}",
            detect: "{{ route('admin.auto-payout.adjustments.detect') }}",
            create: "{{ route('admin.auto-payout.adjustments.store') }}",
            show: "{{ route('admin.auto-payout.adjustments.show', ':id') }}",
            void: "{{ route('admin.auto-payout.adjustments.void', ':id') }}"
        };
    </script>
    <script src="{{ asset('js/admin/auto-payout/app.js') }}"></script>
</body>

</html>
