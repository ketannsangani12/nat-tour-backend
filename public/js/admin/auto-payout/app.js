/**
 * Tourism Platform Auto Payout Management System
 * Bootstrap + jQuery Implementation
 */
const UpdateIncludePartialPaymentsLabel = function(element) {
    const ele = $('#includePartialPaymentsLabel');
    const msg = element.checked ? "Include partial paid bookings" : "Exclude partial paid bookings";
    ele.text(msg);
    return true;
};

const UpdateRequireApprovalLabel = function(element) {
    const ele = $('#requireApprovalLabel');
    const msg = element.checked ? "Approval required" : "Execute immediately";
    ele.text(msg);
    return true;
};

class AutoPayoutManagementSystem {
    constructor() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        this.currentView = 'dashboard';
        this._escDiv = document.createElement('div');
        this.currencyFormation = JSON.parse(document.getElementById('currency').value);
        this.payoutRules = {
            id: '1',
            name: 'Payouts',
            frequency: 'weekly',
            minimum_amount: 100,
            max_payout_amount: null,
            buffer_days: 0,
            notification_emails: [],
            day_of_week: 1,
            execution_time: '09:00',
            enabled: false,
        };
        this.transactions = [];
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.setupBreakdownToggle();
        this.populateDayOfMonthOptions();
        this.loadDashboard();

        // Capture the interval id so we can clear it on unload. Without
        // clearInterval, the polling keeps firing after the user navigates
        // away from this page in a SPA-style host (memory leak + needless
        // requests).
        this._approvalBadgeIntervalId = setInterval(() => {
            this.updateApprovalBadge();
        }, 30000);

        if (typeof window !== 'undefined') {
            window.addEventListener('beforeunload', () => this.destroy());
        }

        // Check if we should show approval view (from redirect)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view') === 'approval') {
            this.switchView('approval');
        }
    }

    destroy() {
        if (this._approvalBadgeIntervalId) {
            clearInterval(this._approvalBadgeIntervalId);
            this._approvalBadgeIntervalId = null;
        }
    }

    /**
     * Gross and fee columns are hidden by default -- operators read them as extra
     * charges rather than as the breakdown of the net they were paid. The values stay
     * in the DOM so toggling is instant and needs no refetch; only visibility changes.
     *
     * One class on <body> drives every table (payout history, pending payouts,
     * upcoming bookings, report transactions) so the tables can never disagree.
     */
    static BREAKDOWN_STORAGE_KEY = 'autoPayout.showFeeBreakdown';

    setupBreakdownToggle() {
        this.applyBreakdownVisibility(this.isBreakdownVisible());

        $(document).on('change', '.payout-breakdown-toggle', (e) => {
            const show = $(e.currentTarget).is(':checked');
            try {
                window.localStorage.setItem(AutoPayoutManagementSystem.BREAKDOWN_STORAGE_KEY, show ? '1' : '0');
            } catch (err) {
                // Private browsing / storage disabled: the choice just won't persist.
                this.notifyError(err, 'Could not save column preference');
            }
            this.applyBreakdownVisibility(show);
        });
    }

    isBreakdownVisible() {
        try {
            return window.localStorage.getItem(AutoPayoutManagementSystem.BREAKDOWN_STORAGE_KEY) === '1';
        } catch (err) {
            return false;
        }
    }

    applyBreakdownVisibility(show) {
        $('body').toggleClass('payout-breakdown-hidden', !show);
        // Keep every toggle on the page in sync (dashboard tab and report tab each have one).
        $('.payout-breakdown-toggle').prop('checked', show);
    }

    /**
     * Centralized error reporter. Console-logs always; the optional toast is
     * suppressed by default so background polling errors don't spam the user.
     * Pass { toast: true } to surface a user-facing message.
     */
    notifyError(err, context, opts = {}) {
        const message = (err && err.message) || String(err || 'Unknown error');
        console.error(`[auto-payout] ${context}:`, err);
        if (opts.toast && typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: context, text: message, timer: 4000 });
        }
    }

    /**
     * Toggle the AUTO_PAYOUT global setting banner. Visible on every tab while
     * the service is off, so admins can't miss it after wondering why payouts
     * stopped.
     */
    renderServiceStatus(data) {
        const $wrap = $('#autoPayoutServiceStatus');
        const $banner = $('#autoPayoutServiceBanner');
        const $icon = $('#autoPayoutServiceIcon');
        const $title = $('#autoPayoutServiceTitle');
        const $msg = $('#autoPayoutServiceMessage');
        const $link = $('#autoPayoutServiceLink');

        $banner.removeClass('alert-danger alert-success');
        $icon.attr('class', 'me-2 fs-5');

        if (data && data.auto_payout_enabled === false) {
            $banner.addClass('alert-danger');
            $icon.addClass('bi bi-exclamation-octagon-fill');
            $title.text('Auto Payouts are disabled.');
            $msg.text('The AUTO_PAYOUT global setting is off, so the scheduler skips every tick. No payouts will be created or executed until you turn it on.');
            if (data.settings_url) {
                $link.attr('href', data.settings_url).removeClass('d-none');
            } else {
                $link.addClass('d-none');
            }
            $wrap.removeClass('d-none');
        } else if (data && data.auto_payout_enabled === true) {
            // Keep the wrap hidden when enabled -- the green confirmation
            // would just add noise to the dashboard. The presence of fresh
            // scheduler-run rows in the Activity Log is the positive signal.
            $wrap.addClass('d-none');
            $link.addClass('d-none');
        } else {
            $wrap.addClass('d-none');
        }
    }

    /**
     * Show / hide the "Last scheduler tick failed" banner. Server returns
     * a populated object only when the most recent tick was outcome=error;
     * any other state clears the banner.
     */
    renderLatestRunError(payload) {
        const $wrap = $('#latestRunErrorStatus');
        if (!payload || !payload.message) {
            $wrap.addClass('d-none');
            return;
        }

        const ts = payload.created_at ? new Date(payload.created_at).toLocaleString() : '';
        this._escDiv.textContent = ts ? `at ${ts}` : '';
        $('#latestRunErrorTimestamp').html(this._escDiv.innerHTML);

        this._escDiv.textContent = payload.message;
        $('#latestRunErrorMessage').html(this._escDiv.innerHTML);

        $wrap.removeClass('d-none');
    }

    /**
     * Fetch the latest scheduler tick rows and render the Scheduler Runs
     * table on the Activity Log view.
     */
    async loadSchedulerRuns() {
        try {
            const response = await $.ajax({
                url: fetchRunLogUrl,
                method: 'GET',
                data: { limit: 50 },
            });
            if (response && response.success) {
                this.renderSchedulerRuns(response.data || []);
            } else {
                this.renderSchedulerRuns([]);
            }
        } catch (err) {
            this.notifyError(err, 'Failed to load scheduler runs');
            $('#schedulerRunsBody').html(
                '<tr><td colspan="6" class="text-center text-danger py-3">Failed to load scheduler runs</td></tr>'
            );
        }
    }

    renderSchedulerRuns(rows) {
        const $body = $('#schedulerRunsBody');
        if (!rows || rows.length === 0) {
            $body.html('<tr><td colspan="6" class="text-center text-muted py-3">No scheduler runs recorded yet</td></tr>');
            return;
        }

        const badgeFor = (outcome) => {
            if (outcome === 'processed') return 'bg-success';
            if (outcome === 'error') return 'bg-danger';
            return 'bg-secondary';
        };

        const labelFor = (outcome) => {
            switch (outcome) {
                case 'skipped_disabled': return 'Skipped - service disabled';
                case 'skipped_no_rule': return 'Skipped - no rule';
                case 'skipped_no_enabled_rule': return 'Skipped - no enabled rule';
                case 'skipped_lock_held': return 'Skipped - lock held';
                case 'processed': return 'Processed';
                case 'error': return 'Error';
                default: return outcome;
            }
        };

        const escapeHtml = (s) => {
            this._escDiv.textContent = s == null ? '' : String(s);
            return this._escDiv.innerHTML;
        };

        const html = rows.map((row) => {
            const ts = row.created_at ? new Date(row.created_at).toLocaleString() : '';
            const duration = row.duration_ms != null ? `${row.duration_ms} ms` : '';
            return `
                <tr>
                    <td><small class="text-muted">${escapeHtml(ts)}</small></td>
                    <td><span class="badge ${badgeFor(row.outcome)}">${escapeHtml(labelFor(row.outcome))}</span></td>
                    <td>${escapeHtml(row.payouts_created ?? 0)}</td>
                    <td>${escapeHtml(row.payouts_executed ?? 0)}</td>
                    <td><small>${escapeHtml(duration)}</small></td>
                    <td><small>${escapeHtml(row.message || '')}</small></td>
                </tr>
            `;
        }).join('');

        $body.html(html);
    }

    setupEventListeners() {
        $('.sidebar .nav-link').on('click', (e) => {
            e.preventDefault();
            const view = $(e.currentTarget).data('view');
            if (view) {
                this.switchView(view);
            }
        });

        $('#sidebarToggle').on('click', () => {
            $('.sidebar').toggleClass('show');
        });

        $(document).on('click', (e) => {
            if ($(window).width() < 992) {
                if (!$(e.target).closest('.sidebar, #sidebarToggle').length) {
                    $('.sidebar').removeClass('show');
                }
            }
        });

        $(document).on('click', '#saveRuleBtn', () => {
            this.savePayoutRule();
        });

        $('select[name="frequency"]').on('change', (e) => {
            this.handleFrequencyChange($(e.target).val());
        });

        $('#periodSelect').on('change', () => {
            this.switchView('dashboard');
        });

        $('#searchInput').on('input', () => {
            this.switchView('reports');
        });

        $('#bookingIdSearch').on('keypress', (e) => {
            if (e.which === 13) {
                e.preventDefault();
                this.switchView('reports');
            }
        });

        $('#statusFilter').on('change', () => {
            this.switchView('reports');
        });

        $('#periodFilter').on('change', (e) => {
            if (e.target.value === 'custom') {
                $('#customDateRange').removeClass('d-none');
                // Default: last 30 days
                const today = new Date();
                const monthAgo = new Date(today.getTime() - 30 * 86400000);
                $('#reportDateTo').val(today.toISOString().slice(0, 10));
                $('#reportDateFrom').val(monthAgo.toISOString().slice(0, 10));
                // Set max to 1 year range
                const yearAgo = new Date(today.getTime() - 365 * 86400000);
                $('#reportDateFrom').attr('min', yearAgo.toISOString().slice(0, 10));
                $('#reportDateFrom').attr('max', today.toISOString().slice(0, 10));
                $('#reportDateTo').attr('min', yearAgo.toISOString().slice(0, 10));
                $('#reportDateTo').attr('max', today.toISOString().slice(0, 10));
            } else {
                $('#customDateRange').addClass('d-none');
                this.switchView('reports');
            }
        });

        $('#applyCustomDateRange').on('click', () => {
            const from = $('#reportDateFrom').val();
            const to = $('#reportDateTo').val();
            if (from && to) {
                if (new Date(to) < new Date(from)) {
                    this.showToast('End date must be after start date', 'error');
                    return;
                }
                const diffDays = (new Date(to) - new Date(from)) / 86400000;
                if (diffDays > 365) {
                    this.showToast('Date range cannot exceed 1 year', 'error');
                    return;
                }
                this.switchView('reports');
            }
        });

        $('#viewAllBtnForReport').on('click', () => {
            this.switchView('reports');
        });

        $(document).on('click', '#addNotificationEmail', () => {
            this.addNotificationEmail();
        });

        $(document).on('keypress', '#notificationEmailInput', (e) => {
            if (e.which === 13) {
                e.preventDefault();
                this.addNotificationEmail();
            }
        });

        $(document).on('show.bs.modal', '#notificationEmailsModal', () => {
            this.renderNotificationEmailList();
        });

        // Fix Stripe schedule
        $(document).on('click', '#fix-stripe-schedule-btn', () => {
            this.fixStripeSchedule();
        });

        // Force-run payout
        $(document).on('click', '#force-run-btn', () => {
            this.forceRunPayout();
        });

        // Re-approve failed payouts
        $(document).on('click', '.reapprove-payout-btn', (e) => {
            const payoutId = $(e.currentTarget).data('payout-id');
            this.reapprovePayout(payoutId);
        });

        // Cancel failed payouts
        $(document).on('click', '.cancel-payout-btn', (e) => {
            const payoutId = $(e.currentTarget).data('payout-id');
            this.cancelPayout(payoutId);
        });

        // Activity log
        $('#refreshActivityLog, #applyActivityLogFilters').on('click', () => {
            this.fetchActivityLog();
        });

        $('#activityLogPeriodFilter').on('change', (e) => {
            if (e.target.value === 'custom') {
                $('#activityLogCustomDates').removeClass('d-none');
                const today = new Date();
                const monthAgo = new Date(today.getTime() - 30 * 86400000);
                $('#activityLogDateTo').val(today.toISOString().slice(0, 10));
                $('#activityLogDateFrom').val(monthAgo.toISOString().slice(0, 10));
            } else {
                $('#activityLogCustomDates').addClass('d-none');
                this.fetchActivityLog();
            }
        });

        // Report view: payout details
        $(document).on('click', '.view-report-payout-details', (e) => {
            const btn = $(e.currentTarget);
            const payoutId = btn.data('payout-id');
            const source = btn.data('source');
            this._detailBookingSearch = '';
            this.showReportPayoutDetails(payoutId, source);
        });
    }

    switchView(view) {
        $('.sidebar .nav-link').removeClass('active');
        $(`.sidebar .nav-link[data-view="${view}"]`).addClass('active');

        $('.view-content').addClass('d-none');

        $(`#${view}-view`).removeClass('d-none').addClass('fade-in');

        this.currentView = view;

        switch (view) {
            case 'dashboard':
                this.loadDashboard();
                break;
            case 'scheduler':
                this.loadScheduler();
                break;
            case 'reports':
                this.loadReports();
                break;
            case 'approval':
                this.loadApproval();
                break;
            case 'adjustments':
                this.loadAdjustments();
                break;
            case 'activity-log':
                this.loadActivityLog();
                break;
            case 'about':
                // Static content, no data to load
                break;
        }

        $('.sidebar').removeClass('show');
    }

    loadDashboard() {
        this.showLoader('#dashboard-view');
        this._adjustmentsTabLoaded = false;
        this._upcomingTabLoaded = false;
        this.fetchPayoutDashboard();
    }

    loadScheduler() {
        this.showLoader('#scheduler-view');
        this.fetchPayoutRules();
    }

    loadReports() {
        this.showLoader('#reports-view');
        this.fetchPayoutReport();
    }

    async fetchPayoutDashboard() {
        await $.ajax({
            type: "POST",
            data: {
                period: $('#periodSelect').val()
            },
            url: fetchDashboardUrl,
            success: (response) => {
                if (response?.success && response?.data) {
                    this.renderServiceStatus(response.data);
                    this.renderLatestRunError(response.data.latest_run_error);
                    this.taxRate = parseFloat(response.data.tax_rate) || 0.09;
                    $('#pending-amount').text(`${this.currencyFormation.symbol}${response.data.pending_amount.toLocaleString()}`);
                    $('#total-paid-out').text(`${this.currencyFormation.symbol}${response.data.total_paid_out.toLocaleString()}`);
                    $('#paid-out-change').text(`${response.data.paid_out_change}%`);
                    $('#active-packages').text(response.data.active_packages);
                    $('#next-payout').text(response.data.next_payout);
                    $('#average-payout').text(`${this.currencyFormation.symbol}${response.data.average_payout.toLocaleString()}`);
                    $('#total-payouts').text(response.data.total_payouts);
                    $('#success-rate').text(`${response.data.success_rate}%`);
                    $('#processing-time').text(response.data.processing_time);
                    this.transactions = response.data.recent_payouts;
                    this.dashboardData = response.data;
                    this.renderRecentPayouts();
                    this.renderPayoutSummary(response.data);
                    this.buildDashboardChart();
                    this.renderPendingPayoutsTab(response.data.pending_payouts_list || []);

                    // Show failed payouts alert
                    if (response.data.failed_payouts && response.data.failed_payouts.length > 0) {
                        const failedTotal = response.data.failed_payouts.reduce((sum, p) => sum + parseFloat(p.net_amount), 0);
                        $('#failed-payouts-count').text(response.data.failed_payouts.length);
                        $('#failed-payouts-total').text(`Total: ${this.formatCurrency(failedTotal.toFixed(2))}`);
                        $('#failed-payouts-alert').show();
                    } else {
                        $('#failed-payouts-alert').hide();
                    }

                    // Show Stripe schedule warning
                    const scheduleStatus = response.data.stripe_schedule_status;
                    if (scheduleStatus && !scheduleStatus.is_manual) {
                        $('#stripe-schedule-interval').text(scheduleStatus.current_interval);
                        $('#stripe-schedule-alert').show();
                    } else {
                        $('#stripe-schedule-alert').hide();
                    }
                }
                this.hideLoader('#dashboard-view');

                // Also update approval and adjustment badges
                this.updateApprovalBadge();
                this.updateAdjustmentBadge();

                // Show pending adjustments alert on dashboard
                const adjData = response.data.pending_adjustments;
                if (adjData && adjData.count > 0) {
                    const adjNet = adjData.net_adjustment;
                    const adjLabel = adjNet >= 0 ? 'credit' : 'debit';
                    const adjHtml = `<div class="alert alert-info d-flex align-items-center" role="alert" id="pending-adjustments-alert">
                        <i class="bi bi-arrow-left-right me-3 fs-5"></i>
                        <div>
                            <strong>${adjData.count} pending adjustment(s)</strong> will be applied to the next payout.
                            Net ${adjLabel}: ${this.formatCurrency(Math.abs(adjNet).toFixed(2))}
                            <a href="#" class="alert-link ms-2" onclick="window.payoutSystem.switchView('adjustments'); return false;">View adjustments</a>
                        </div>
                    </div>`;
                    $('#pending-adjustments-alert-container').html(adjHtml);
                } else {
                    $('#pending-adjustments-alert-container').empty();
                }
            },
            error: () => {
                this.showToast('Unable to fetch payout dashboard. Please try again.', 'error');
                this.hideLoader('#dashboard-view');
            }
        });
    }

    async updateApprovalBadge() {
        try {
            const response = await $.ajax({
                url: approvalApiUrls.dashboardData,
                method: 'GET'
            });

            if (response.success && response.data.pending_count > 0) {
                $('#pending-approvals-badge').text(response.data.pending_count).show();
            } else {
                $('#pending-approvals-badge').hide();
            }
        } catch (error) {
            // Silently fail for badge update
            console.log('Could not update approval badge');
        }
    }

    async fixStripeSchedule() {
        const btn = $('#fix-stripe-schedule-btn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Fixing...');

        try {
            const response = await $.ajax({
                url: fixStripeScheduleUrl,
                method: 'POST'
            });

            if (response.success) {
                $('#stripe-schedule-alert').hide();
                this.showToast('Stripe payout schedule set to manual successfully.', 'success');
                this.loadDashboard();
            } else {
                this.showToast(response.message || 'Failed to fix Stripe schedule.', 'error');
            }
        } catch (error) {
            const msg = error.responseJSON?.message || 'Failed to fix Stripe payout schedule.';
            this.showToast(msg, 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-wrench me-1"></i>Fix Now');
        }
    }

    async forceRunPayout() {
        const result = await Swal.fire({
            title: 'Run Payout Now?',
            text: 'This will immediately process any pending payouts and initiate real Stripe transfers to connected accounts. This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, run payout',
            cancelButtonText: 'Cancel'
        });

        if (!result.isConfirmed) return;

        const btn = $('#force-run-btn');
        const originalHtml = btn.html();
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Running...');

        try {
            const response = await $.ajax({
                url: forceRunPayoutUrl,
                method: 'POST'
            });

            if (response.success) {
                this.showToast('Payout process executed successfully.', 'success');
                this.loadDashboard();
            } else {
                this.showToast(response.message || 'Failed to run payout.', 'error');
            }
        } catch (error) {
            const msg = error.responseJSON?.message || 'Failed to run payout.';
            this.showToast(msg, 'error');
        } finally {
            btn.prop('disabled', false).html(originalHtml);
        }
    }

    async fetchPayoutRules() {
        await $.ajax({
            type: "GET",
            url: fetchRuleUrl,
            success: (response) => {
                if (response?.success && response?.data) {
                    this.payoutRules = response.data;
                }
                this.renderPayoutRules();
                this.hideLoader('#scheduler-view');
            },
            error: () => {
                this.showToast('Unable to fetch payout rules. Please try again.', 'error');
                this.hideLoader('#scheduler-view');
            }
        });
    }

    async fetchPayoutReport() {
        const period = $('#periodFilter').val();
        const isCustom = period === 'custom';
        const dateFrom = isCustom ? $('#reportDateFrom').val() : null;
        const dateTo = isCustom ? $('#reportDateTo').val() : null;

        const requestData = {
            search: $('#searchInput').val(),
            status: $('#statusFilter').val(),
            period: period,
            booking_id: $('#bookingIdSearch').val()
        };
        if (isCustom && dateFrom) requestData.dateFrom = dateFrom;
        if (isCustom && dateTo) requestData.dateTo = dateTo;

        await $.ajax({
            type: "POST",
            data: requestData,
            url: fetchPayoutReportUrl,
            success: (response) => {
                if (response?.success && response?.data) {
                    const sym = this.currencyFormation.symbol;
                    $('#rp-total-payout-amount').text(`${sym}${response.data.total_paid_out.toLocaleString()}`);
                    $('#rp-paid-out-change').text(`${response.data.paid_out_change}%`);
                    $('#rp-successful-payouts').text(response.data.total_payouts);
                    $('#rp-success-rate').text(`${response.data.success_rate}%`);
                    $('#rp-average-payout').text(`${sym}${response.data.average_payout.toLocaleString()}`);
                    this.transactions = response.data.payouts;
                    this.renderTransactionsTable();

                    // Date range label
                    const fmt = (d) => d.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                    let start, end;
                    if (isCustom && dateFrom && dateTo) {
                        start = new Date(dateFrom + 'T00:00:00');
                        end = new Date(dateTo + 'T00:00:00');
                    } else {
                        const daysMap = { '7d': 7, '30d': 30, '90d': 90, '1y': 365 };
                        const days = daysMap[period] || 30;
                        end = new Date();
                        start = new Date(end.getTime() - days * 86400000);
                    }
                    $('#rp-date-range-label').text(`${fmt(start)} - ${fmt(end)}`);
                }
                this.hideLoader('#reports-view');
            },
            error: () => {
                this.showToast('Unable to fetch payout report. Please try again.', 'error');
                this.hideLoader('#reports-view');
            }
        });
    }

    renderRecentPayouts() {
        const payouts = this.transactions;
        const sym = this.currencyFormation.symbol;

        const fmtNum = (n) => {
            const num = parseFloat(n) || 0;
            return sym + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        };

        const statusBadge = (status) => {
            const s = (status || '').toLowerCase();
            const labelMap = {
                completed: 'Succeeded',
                succeeded: 'Succeeded',
                processing: 'Processing',
                pending: 'Pending',
                pending_approval: 'Pending',
                failed: 'Failed',
                initiated: 'Initiated',
                scheduled: 'Scheduled',
            };
            const label = labelMap[s] || (s.charAt(0).toUpperCase() + s.slice(1));
            const classMap = {
                completed: 'dash-badge-succeeded',
                succeeded: 'dash-badge-succeeded',
                processing: 'dash-badge-processing',
                pending: 'dash-badge-pending',
                pending_approval: 'dash-badge-pending',
                failed: 'dash-badge-failed',
                initiated: 'dash-badge-initiated',
                scheduled: 'dash-badge-scheduled',
            };
            const cls = classMap[s] || 'dash-badge-succeeded';
            return `<span class="dash-badge ${cls}">${label}</span>`;
        };

        const formatShortDate = (dateStr) => {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return `${d.getMonth()+1}/${d.getDate()}/${String(d.getFullYear()).slice(-2)}`;
        };

        let totalNet = 0;

        const html = payouts.map(payout => {
            const gross = parseFloat(payout.total_amount) || 0;
            const fees = parseFloat(payout.platform_fee) || 0;
            const net = parseFloat(payout.net_amount) || 0;
            // Not a figure from the payout: derived in the browser from gross. Kept in the
            // opt-in breakdown group rather than shown by default.
            const tax = Math.round(gross * (this.taxRate || 0.09) * 100) / 100;
            const paidOn = formatShortDate(payout.processed_date);
            // The collection cutoff, not the payout date. This cell used to repeat
            // paidOn, so the column claimed a figure it never had. Payouts created
            // before the period columns existed have no window to show.
            const activityBefore = payout.period_end ? formatShortDate(payout.period_end) : 'Not recorded';

            totalNet += net;

            return `
                <tr>
                    <td class="dash-td-id view-report-payout-details"
                        data-payout-id="${payout.id}"
                        data-source="auto_payout"
                        style="cursor: pointer; color: #0d6efd; text-decoration: underline;"
                        >${payout.name || '-'}${payout.has_adjustments ? ` <span class="badge bg-warning text-dark ms-1" title="${payout.adjustment_count} adjustment(s)">${payout.adjustment_count} adj</span>` : ''}</td>
                    <td class="dash-td-amount col-gross">${fmtNum(gross)}</td>
                    <td class="dash-td-fee col-fee">-${fmtNum(fees)}</td>
                    <td class="dash-td-net">${fmtNum(net)}</td>
                    <td class="col-tax">${fmtNum(tax)}</td>
                    <td class="dash-td-date">${paidOn}</td>
                    <td class="dash-td-date">${activityBefore}</td>
                    <td>${statusBadge(payout.status)}</td>
                </tr>
            `;
        }).join('');

        $('#dashTableBody').html(html);

        // Update table footer
        $('#dashTableFooter').html(
            `<strong>${payouts.length} rows</strong>&nbsp;&middot;&nbsp;<strong>${fmtNum(totalNet)} net</strong>`
        );

        // Update chart total
        $('#dash-chart-total-value').text(fmtNum(totalNet));
    }

    renderPayoutSummary(data) {
        const sym = this.currencyFormation.symbol;
        const fmtNum = (n) => {
            const num = parseFloat(n) || 0;
            return sym + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        };

        // Sidebar summary values are already set via element IDs in fetchPayoutDashboard

        // Next payout box
        $('#dash-next-payout-date').text(data.next_payout || 'Not scheduled');
        $('#dash-next-payout-amount').text(fmtNum(data.pending_amount));

        // This Period card - net only. The gross/fee/tax breakdown moved into the table.
        let totalNet = 0;

        if (this.transactions && this.transactions.length > 0) {
            this.transactions.forEach(p => {
                totalNet += parseFloat(p.net_amount) || 0;
            });
        }

        const fmtShort = (n) => {
            const num = Math.round(n);
            return sym + num.toLocaleString();
        };

        $('#dash-period-net').text(fmtShort(totalNet));
    }

    buildDashboardChart() {
        const container = document.getElementById('dashChartContainer');
        if (!container) return;

        const payouts = this.transactions || [];
        if (payouts.length === 0) return;

        // Build chart points from payout data, sorted by date
        const sorted = [...payouts].sort((a, b) => {
            const da = new Date(a.processed_date || 0);
            const db = new Date(b.processed_date || 0);
            return da - db;
        });

        const chartPoints = sorted.map(p => {
            const d = new Date(p.processed_date);
            const label = isNaN(d.getTime()) ? '-' : `${d.getMonth()+1}/${d.getDate()}`;
            return { label: label, value: parseFloat(p.net_amount) || 0, status: (p.status || '').toLowerCase() };
        });

        if (chartPoints.length === 0) return;

        const W = container.offsetWidth || 500;
        const H = 160;
        const pad = { top: 20, right: 20, bottom: 36, left: 52 };
        const chartW = W - pad.left - pad.right;
        const chartH = H - pad.top - pad.bottom;

        const values = chartPoints.map(p => p.value);
        const maxV = Math.max(...values) * 1.2 || 100;
        const minV = 0;
        const n = chartPoints.length;
        const xStep = n > 1 ? chartW / (n - 1) : chartW;

        const xOf = (i) => pad.left + i * xStep;
        const yOf = (v) => pad.top + chartH - ((v - minV) / (maxV - minV)) * chartH;

        const pts = chartPoints.map((p, i) => ({ x: xOf(i), y: yOf(p.value), ...p }));

        const polyPoints = pts.map(p => `${p.x},${p.y}`).join(' ');
        const areaPoints = `${pts[0].x},${pad.top + chartH} ` + polyPoints + ` ${pts[pts.length-1].x},${pad.top + chartH}`;

        // Y axis labels
        const ySteps = 5;
        const yLabelsSvg = Array.from({length: ySteps + 1}, (_, i) => {
            const v = minV + (maxV - minV) * i / ySteps;
            const y = yOf(v);
            const sym = this.currencyFormation.symbol;
            let label;
            if (v === 0) label = sym + '0';
            else if (v >= 1000) label = sym + (v/1000).toFixed(1) + 'k';
            else label = sym + Math.round(v);
            return `<text x="${pad.left - 8}" y="${y + 4}" fill="#94a3b8" font-size="10" text-anchor="end">${label}</text>
                    <line x1="${pad.left}" y1="${y}" x2="${pad.left + chartW}" y2="${y}" stroke="#f1f5f9" stroke-width="1"/>`;
        }).join('');

        // X axis labels (deduplicated)
        const seenLabels = new Set();
        const xLabelsSvg = pts.map((p) => {
            if (seenLabels.has(p.label)) return '';
            seenLabels.add(p.label);
            return `<text x="${p.x}" y="${pad.top + chartH + 18}" fill="#94a3b8" font-size="10" text-anchor="middle">${p.label}</text>`;
        }).join('');

        // Data point circles - red for failed payouts
        const circlesSvg = pts.map((p, i) => {
            const isFailed = p.status === 'failed';
            const strokeColor = isFailed ? '#dc2626' : '#2563eb';
            const fillColor = isFailed ? '#dc2626' : 'white';
            return `<circle cx="${p.x}" cy="${p.y}" r="5" fill="${fillColor}" stroke="${strokeColor}" stroke-width="2" class="dash-chart-dot" data-idx="${i}" data-label="${p.label}" data-value="${p.value}" style="cursor:pointer;"/>`;
        }).join('');

        const svgHtml = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" style="display:block;width:100%;height:${H}px;overflow:visible;">
            <defs>
                <linearGradient id="dashChartGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgba(37,99,235,0.18)"/>
                    <stop offset="100%" stop-color="rgba(37,99,235,0.01)"/>
                </linearGradient>
            </defs>
            ${yLabelsSvg}
            ${xLabelsSvg}
            <polygon points="${areaPoints}" fill="url(#dashChartGrad)"/>
            <polyline points="${polyPoints}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            ${circlesSvg}
        </svg>`;

        // Remove old SVG
        const oldSvg = container.querySelector('svg');
        if (oldSvg) oldSvg.remove();
        container.insertAdjacentHTML('afterbegin', svgHtml);

        // Tooltip
        const tooltip = document.getElementById('dashChartTooltip');
        const self = this;
        container.querySelectorAll('.dash-chart-dot').forEach(dot => {
            dot.addEventListener('mouseenter', function() {
                const rect = container.getBoundingClientRect();
                const dotRect = dot.getBoundingClientRect();
                const val = self.formatCurrency(dot.dataset.value);
                tooltip.textContent = `${dot.dataset.label}: ${val}`;
                tooltip.style.left = (dotRect.left - rect.left + dotRect.width / 2) + 'px';
                tooltip.style.top = (dotRect.top - rect.top) + 'px';
                tooltip.style.opacity = '1';
            });
            dot.addEventListener('mouseleave', function() {
                tooltip.style.opacity = '0';
            });
        });
    }

    switchDashTab(btn, tab) {
        $('#dashTabs .dash-tab-btn').removeClass('active');
        $(btn).addClass('active');
        $('#dashTableContainer, #dashPendingTableContainer, #dashAdjustmentsTableContainer, #dashUpcomingTableContainer, #dashTabEmpty').hide();
        switch (tab) {
            case 'payouts':
                $('#dashTableContainer').show();
                break;
            case 'pending':
                $('#dashPendingTableContainer').show();
                break;
            case 'adjustments':
                $('#dashAdjustmentsTableContainer').show();
                if (!this._adjustmentsTabLoaded) {
                    this.renderDashAdjustmentsTab();
                }
                break;
            case 'upcoming':
                $('#dashUpcomingTableContainer').show();
                if (!this._upcomingTabLoaded) {
                    this.fetchUpcomingBookings();
                }
                break;
            default:
                $('#dashTabEmpty').show();
        }
    }

    renderPendingPayoutsTab(pendingPayouts) {
        const tbody = $('#dashPendingTableBody');
        const footer = $('#dashPendingTableFooter');

        if (!pendingPayouts || pendingPayouts.length === 0) {
            tbody.html(`<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">No pending payouts</td></tr>`);
            footer.html('');
            return;
        }

        const rows = pendingPayouts.map(p => {
            const statusLabel = p.status === 'approved' ? 'Approved' : 'Pending Approval';
            const created = new Date(p.created_at).toLocaleDateString();
            return `<tr>
                <td class="dash-td-id">${p.payout_reference}</td>
                <td class="dash-td-amount col-gross">${this.formatCurrency(p.total_amount)}</td>
                <td class="dash-td-fee col-fee">-${this.formatCurrency(p.platform_fee)}</td>
                <td class="dash-td-net">${this.formatCurrency(p.net_amount)}</td>
                <td>${p.booking_count} bookings</td>
                <td class="dash-td-date">${created}</td>
                <td><span class="badge bg-${p.status === 'approved' ? 'success' : 'warning'}">${statusLabel}</span></td>
            </tr>`;
        }).join('');

        tbody.html(rows);

        const totalNet = pendingPayouts.reduce((sum, p) => sum + parseFloat(p.net_amount), 0);
        footer.html(`<strong>${pendingPayouts.length} pending</strong>&nbsp;&middot;&nbsp;<strong>${this.formatCurrency(totalNet.toFixed(2))} net</strong>`);
    }

    async renderDashAdjustmentsTab() {
        const tbody = $('#dashAdjustmentsTableBody');
        const footer = $('#dashAdjustmentsTableFooter');

        tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Loading...</td></tr>`);

        try {
            const response = await $.ajax({
                url: adjustmentApiUrls.index,
                method: 'GET',
                data: { status: 'pending', per_page: 50 }
            });

            if (response.success) {
                const adjustments = response.data.data || [];
                this._adjustmentsTabLoaded = true;

                if (adjustments.length === 0) {
                    tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">No pending adjustments</td></tr>`);
                    footer.html('');
                    return;
                }

                const rows = adjustments.map(adj => {
                    const typeBadge = adj.type === 'credit'
                        ? '<span class="badge bg-success">Credit</span>'
                        : '<span class="badge bg-danger">Debit</span>';
                    const detected = adj.detected_at ? new Date(adj.detected_at).toLocaleDateString() : '-';
                    return `<tr>
                        <td class="dash-td-id">#${adj.booking_id}</td>
                        <td>${typeBadge}</td>
                        <td class="dash-td-net">${this.formatCurrency(adj.net_amount)}</td>
                        <td class="dash-td-id">${adj.payout_reference ? this.escapeHtml(adj.payout_reference) : '<span class="badge bg-secondary" title="Booking was never included in a payout">Orphaned</span>'}</td>
                        <td><small>${this.escapeHtml(adj.reason) || '-'}</small></td>
                        <td class="dash-td-date">${detected}</td>
                    </tr>`;
                }).join('');

                tbody.html(rows);

                const creditTotal = adjustments.filter(a => a.type === 'credit').reduce((sum, a) => sum + parseFloat(a.net_amount), 0);
                const debitTotal = adjustments.filter(a => a.type === 'debit').reduce((sum, a) => sum + parseFloat(a.net_amount), 0);
                footer.html(`<strong>${adjustments.length} pending</strong>&nbsp;&middot;&nbsp;` +
                    `<span class="text-success">${this.formatCurrency(creditTotal.toFixed(2))} credits</span>&nbsp;&middot;&nbsp;` +
                    `<span class="text-danger">${this.formatCurrency(debitTotal.toFixed(2))} debits</span>&nbsp;&middot;&nbsp;` +
                    `<strong>Net: ${this.formatCurrency((creditTotal - debitTotal).toFixed(2))}</strong>`);
            }
        } catch (error) {
            tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Failed to load adjustments</td></tr>`);
        }
    }

    async fetchUpcomingBookings() {
        const tbody = $('#dashUpcomingTableBody');
        const footer = $('#dashUpcomingTableFooter');

        tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Loading...</td></tr>`);

        try {
            const response = await $.ajax({
                url: fetchDashboardUrl,
                method: 'POST',
                data: { period: 'upcoming' }
            });

            if (response.success && response.data.upcoming_bookings) {
                const bookings = response.data.upcoming_bookings || [];
                this._upcomingTabLoaded = true;

                if (bookings.length === 0) {
                    tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">No upcoming bookings in the buffer window</td></tr>`);
                    footer.html('');
                    return;
                }

                const rows = bookings.map(b => {
                    return `<tr>
                        <td class="dash-td-id">#${b.booking_id}</td>
                        <td class="dash-td-date">${b.tour_date}</td>
                        <td>${b.tourists}</td>
                        <td class="dash-td-amount">${this.formatCurrency(b.amount_paid)}</td>
                        <td class="dash-td-fee col-fee">-${this.formatCurrency(b.fees)}</td>
                        <td><span class="badge bg-light text-dark">${b.eligible_in}</span></td>
                    </tr>`;
                }).join('');

                tbody.html(rows);

                const totalAmount = bookings.reduce((sum, b) => sum + parseFloat(b.amount_paid), 0);
                footer.html(`<strong>${bookings.length} bookings</strong>&nbsp;&middot;&nbsp;<strong>${this.formatCurrency(totalAmount.toFixed(2))} total</strong>`);
            } else {
                tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">No upcoming bookings in the buffer window</td></tr>`);
                footer.html('');
                this._upcomingTabLoaded = true;
            }
        } catch (error) {
            tbody.html(`<tr><td colspan="6" style="text-align:center;padding:30px;color:#94a3b8;">Failed to load upcoming bookings</td></tr>`);
        }
    }

    /**
     * Period roll-up for the payout open in the detail modal: bookings, tourists and
     * amount per category, what is still unpaid, and the Stripe balance the deposit
     * lands on. This is the table that goes out with the weekly payout email.
     */
    async loadPeriodSummary() {
        if (!this._currentDetailsPayoutId) return;

        const btn = $('#loadPeriodSummaryBtn');
        const body = $('#payoutPeriodSummaryBody');
        btn.prop('disabled', true);
        body.html('<div class="text-muted small">Loading summary...</div>');

        try {
            const urlTemplate = this._currentDetailsSource === 'pending_payout'
                ? approvalApiUrls.summary
                : payoutSummaryUrl;
            const response = await $.ajax({
                url: urlTemplate.replace(':id', this._currentDetailsPayoutId),
                method: 'GET'
            });

            if (!response?.success || !response?.data) {
                body.html('<div class="text-danger small">Failed to load the summary.</div>');
                return;
            }

            this._currentSummaryData = response.data;
            body.html(this.buildPeriodSummaryHtml(response.data));
            $('#copyPeriodSummaryBtn').show();
            btn.hide();
        } catch (error) {
            this.notifyError(error, 'Failed to load period summary');
            body.html('<div class="text-danger small">Failed to load the summary.</div>');
        } finally {
            btn.prop('disabled', false);
        }
    }

    buildPeriodSummaryHtml(summary) {
        const rows = summary.rows || [];
        const total = summary.total || { bookings: 0, tourists: 0, net_amount: 0 };
        const unpaid = summary.unpaid || {};
        const tourDates = summary.tour_dates || {};
        const num = (n) => (parseInt(n, 10) || 0).toLocaleString();

        const rowHtml = rows.map(r => `
            <tr>
                <td>${this.escapeHtml(r.category)}</td>
                <td class="text-end">${num(r.bookings)}</td>
                <td class="text-end">${num(r.tourists)}</td>
                <td class="text-end">${this.formatCurrency(r.net_amount)}</td>
            </tr>
        `).join('');

        // The unpaid figure is what was collected, not a net: the platform fee is not
        // known for a booking until a payout is calculated for it. Labelled inline so it
        // is never read as another net in the same column.
        const unpaidHtml = unpaid.recorded
            ? `<tr>
                   <td>Bookings not paid yet <span class="text-muted">(gross)</span></td>
                   <td class="text-end">${num(unpaid.bookings)}</td>
                   <td class="text-end">${num(unpaid.tourists)}</td>
                   <td class="text-end">${this.formatCurrency(unpaid.amount)}</td>
               </tr>`
            : `<tr>
                   <td colspan="4" class="text-muted small">
                       Bookings not paid yet: unavailable -- this payout predates the recorded collection window.
                   </td>
               </tr>`;

        const balanceHtml = summary.balance_before_deposit === null || summary.balance_before_deposit === undefined
            ? '<td class="text-end text-muted">Not recorded</td>'
            : `<td class="text-end">${this.formatCurrency(summary.balance_before_deposit)}</td>`;

        // Payouts created before the window columns existed have nothing to show, and a
        // bare "Not recorded" reads as a fault. Deliberately not backfilled: the window
        // came from the rule's last_executed_at at the time, which has since moved on,
        // so any reconstruction would be a guess presented as a fact.
        const windowRecorded = summary.period_display && summary.period_display !== 'Not recorded';
        const windowHtml = windowRecorded
            ? this.escapeHtml(summary.period_display)
            : 'not tracked for payouts created before this release';

        return `
            <div class="small text-muted mb-2">
                <div>Tour dates: ${this.escapeHtml(tourDates.display || 'No tour dates recorded')}</div>
                <div>Collection window: ${windowHtml}</div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Bookings</th>
                            <th class="text-end">Tourists</th>
                            <th class="text-end">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowHtml || '<tr><td colspan="4" class="text-muted small">No bookings in this payout.</td></tr>'}
                        <tr class="fw-bold table-light">
                            <td>Total</td>
                            <td class="text-end">${num(total.bookings)}</td>
                            <td class="text-end">${num(total.tourists)}</td>
                            <td class="text-end">${this.formatCurrency(total.net_amount)}</td>
                        </tr>
                        ${unpaidHtml}
                        <tr>
                            <td colspan="3">Stripe balance before deposit</td>
                            ${balanceHtml}
                        </tr>
                    </tbody>
                </table>
            </div>
            ${this.periodSummaryNetWarning(summary, total)}
            <div class="small text-muted mt-2">
                Amount is the net paid out -- the figure that lands in the bank from Stripe.
                Gross collected for this payout was ${this.formatCurrency(total.amount)}.
                Affiliate bookings are counted only in the Affiliates row, never in a
                package row.
            </div>
        `;
    }

    /**
     * The Total is summed from the payout's item rows, but the payout also stores its
     * own net. They should be the same number. If an adjustment lands after the items
     * were written they can diverge, and this table is the one people read as "what
     * Stripe will transfer" -- so say so rather than quietly showing the wrong figure.
     */
    periodSummaryNetWarning(summary, total) {
        const stored = parseFloat(summary.net_amount);
        const summed = parseFloat(total.net_amount) || 0;

        if (!isFinite(stored) || Math.abs(stored - summed) < 0.01) return '';

        return `<div class="alert alert-warning small py-2 px-3 mt-2 mb-0">
                    Total above (${this.formatCurrency(summed)}) does not match the payout's
                    recorded net of ${this.formatCurrency(stored)}. Check for adjustments
                    made after this payout was calculated before sending these figures out.
                </div>`;
    }

    /**
     * Tab-separated so it pastes into Gmail or Sheets as a real table.
     */
    buildPeriodSummaryText(summary) {
        const rows = summary.rows || [];
        const total = summary.total || {};
        const unpaid = summary.unpaid || {};
        const tourDates = summary.tour_dates || {};
        const money = (n) => this.formatCurrency(n);
        const lines = [
            `Tour dates: ${tourDates.display || 'No tour dates recorded'}`,
            `Collection window: ${summary.period_display || 'Not recorded'}`,
            '',
            ['', 'Bookings', 'Tourists', 'Amount'].join('\t')
        ];

        rows.forEach(r => lines.push([r.category, r.bookings, r.tourists, money(r.net_amount)].join('\t')));
        lines.push(['Total', total.bookings, total.tourists, money(total.net_amount)].join('\t'));
        lines.push('');
        lines.push(unpaid.recorded
            ? ['Bookings not paid yet (gross)', unpaid.bookings, unpaid.tourists, money(unpaid.amount)].join('\t')
            : 'Bookings not paid yet\tnot recorded');
        lines.push(['Stripe balance before deposit', '', '',
            summary.balance_before_deposit === null || summary.balance_before_deposit === undefined
                ? 'Not recorded'
                : money(summary.balance_before_deposit)].join('\t'));

        return lines.join('\n');
    }

    async copyPeriodSummary() {
        if (!this._currentSummaryData) return;

        const text = this.buildPeriodSummaryText(this._currentSummaryData);

        try {
            await navigator.clipboard.writeText(text);
            this.showToast('Summary copied to clipboard.', 'success');
        } catch (error) {
            // Clipboard API needs a secure context; show the text so it can still be copied.
            this.notifyError(error, 'Clipboard unavailable');
            $('#payoutPeriodSummaryBody').append(
                `<textarea class="form-control mt-2" rows="8" onclick="this.select()">${this.escapeHtml(text)}</textarea>`
            );
        }
    }

    async exportPayoutDetailsCsv() {
        if (!this._currentDetailsPayoutId) {
            this.showToast('No payout data to export.', 'error');
            return;
        }

        try {
            this.showToast('Preparing export...', 'info');
            // Pending payouts live in a different table with its own id sequence, so the
            // export has to follow the source the modal was opened from. Sending a pending
            // id to the auto-payout endpoint used to 500, or silently return another payout.
            const urlTemplate = this._currentDetailsSource === 'pending_payout'
                ? approvalApiUrls.export
                : exportAutoPayoutUrl;
            const url = urlTemplate.replace(':id', this._currentDetailsPayoutId);
            const response = await $.ajax({ url: url, method: 'GET' });

            if (!response?.success || !response?.data) {
                this.showToast('Failed to fetch export data.', 'error');
                return;
            }

            const data = response.data;
            const payout = data.payout;
            const items = data.items || [];
            const adjustments = data.applied_adjustments || [];

            let csv = 'Payout Export\r\n';
            csv += `Reference,${payout.payout_reference}\r\n`;
            csv += `Status,${payout.status}\r\n`;
            csv += `Total Amount,${payout.total_amount}\r\n`;
            csv += `Platform Fee,${payout.platform_fee}\r\n`;
            csv += `Net Amount,${payout.net_amount}\r\n`;
            csv += `Bookings,${payout.booking_count}\r\n`;
            csv += `Booking Window,"${payout.period_display || 'Not recorded'}"\r\n`;
            csv += `Created,${payout.created_at || ''}\r\n`;
            // A pending payout has not executed, so it has no processed date to report.
            csv += `Executed,${payout.processed_date || 'Not executed'}\r\n`;
            csv += '\r\n';

            csv += 'Booking Items\r\n';
            csv += 'Booking ID,Customer,Tour,Tour Date,Pax,Amount,Platform Fee,Net Amount,Amended\r\n';
            items.forEach(item => {
                const customerName = (item.customer_name || '').replace(/"/g, '""');
                const tourName = (item.tour_package_name || '').replace(/"/g, '""');
                csv += `${item.booking_id},"${customerName}","${tourName}",${item.tour_date || ''},${item.tourist_count || ''},${item.total_amount},${item.platform_fee},${item.net_amount},${item.has_adjustment ? 'Yes' : 'No'}\r\n`;
            });

            if (adjustments.length > 0) {
                csv += '\r\n';
                csv += 'Applied Adjustments\r\n';
                csv += 'Booking ID,Type,Gross Amount,Platform Fee,Net Amount,Original Payout,Reason\r\n';
                adjustments.forEach(adj => {
                    const reason = (adj.reason || '').replace(/"/g, '""');
                    csv += `${adj.booking_id},${adj.type},${adj.gross_amount},${adj.platform_fee},${adj.net_amount},${adj.payout_reference || ''},"${reason}"\r\n`;
                });
            }

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const blobUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = blobUrl;
            link.download = `payout-${payout.payout_reference || 'export'}.csv`;
            link.click();
            URL.revokeObjectURL(blobUrl);
        } catch (error) {
            console.error('Export failed:', error);
            this.showToast('Failed to export payout data.', 'error');
        }
    }

    renderPayoutRules() {
        const html = `
        <form id="saveRuleForm">
            <div class="card rule-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <h5 class="mb-0 fw-semibold me-4">${this.payoutRules.name}</h5>
                            <div class="d-flex align-items-center">
                                <label class="rule-toggle">
                                    <input type="checkbox" name="enabled" ${this.payoutRules.enabled ? 'checked' : ''} 
                                           onchange="payoutSystem.toggleRule(this.checked)">
                                    <span class="rule-slider"></span>
                                </label>
                                <span class="ms-2 small text-muted">
                                    ${this.payoutRules.enabled ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Rule Name</label>
                            <input type="text" class="form-control form-control-sm" name="name" value="${this.payoutRules.name}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Frequency</label>
                            <select class="form-select form-select-sm" name="frequency" onchange="payoutSystem.updateRule('frequency', this.value)">
                                <option value="daily" ${this.payoutRules.frequency === 'daily' ? 'selected' : ''}>Daily</option>
                                <option value="weekly" ${this.payoutRules.frequency === 'weekly' ? 'selected' : ''}>Weekly</option>
                                <option value="monthly" ${this.payoutRules.frequency === 'monthly' ? 'selected' : ''}>Monthly</option>
                            </select>
                        </div>
                        ${this.payoutRules.frequency === 'weekly' ? `
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Day of Week</label>
                                <select class="form-select form-select-sm" name="day_of_week">
                                    ${this.getWeekDayOptions(this.payoutRules.day_of_week)}
                                </select>
                            </div>
                        ` : ''}
                        ${this.payoutRules.frequency === 'monthly' ? `
                            <div class="col-md-3">
                                <label class="form-label small text-muted">Day of Month</label>
                                <select class="form-select form-select-sm" name="day_of_month">
                                    ${this.getDayOfMonthOptions(this.payoutRules.day_of_month)}
                                </select>
                            </div>
                        ` : ''}
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Time</label>
                            <input type="time" class="form-control form-control-sm" name="execution_time" value="${this.payoutRules.execution_time}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Minimum Amount</label>
                            <input type="number" class="form-control form-control-sm" name="minimum_amount" value="${this.payoutRules.minimum_amount}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Max Payout Ceiling</label>
                            <input type="number" class="form-control form-control-sm" name="max_payout_amount" step="0.01" min="0" value="${this.payoutRules.max_payout_amount || ''}" placeholder="No limit">
                            <div class="form-text" style="font-size: 0.7rem;">Leave empty for no limit</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">
                                Buffer Days
                                <i class="bi bi-info-circle text-primary ms-1" role="button" data-bs-toggle="modal" data-bs-target="#bufferDaysInfoModal"></i>
                            </label>
                            <input type="number" class="form-control form-control-sm" name="buffer_days" min="0" max="30" value="${this.payoutRules.buffer_days || 0}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">
                                Start Date
                                <i class="bi bi-info-circle text-primary ms-1" role="button" data-bs-toggle="modal" data-bs-target="#startDateInfoModal"></i>
                            </label>
                            <input type="date" class="form-control form-control-sm" name="start_date" value="${this.payoutRules.start_date || ''}">
                            <div class="form-text" style="font-size: 0.7rem;">First payout reaches back to this date</div>
                        </div>

                        <!-- Start Date Info Modal -->
                        <div class="modal fade" id="startDateInfoModal" tabindex="-1" aria-labelledby="startDateInfoModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" id="startDateInfoModalLabel">Start Date</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>The start date sets how far back the very first payout reaches. Only bookings whose tour date is on or after this date (minus the buffer) are included in the first run.</p>
                                        <p>It applies only until the first payout runs. After that, each cycle picks up where the previous one left off, so the start date no longer has any effect.</p>
                                        <p class="mb-0">Leave it empty to start fresh from today and never sweep historical bookings.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Buffer Days Info Modal -->
                        <div class="modal fade" id="bufferDaysInfoModal" tabindex="-1" aria-labelledby="bufferDaysInfoModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" id="bufferDaysInfoModalLabel">Buffer Days</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Buffer days add a grace period between when a tour is completed and when it becomes eligible for payout.</p>
                                        <p>For example, with a 2-day buffer, a tour completed on March 8 will not be included in a payout until March 10. This gives customers time to request refunds before funds are committed.</p>
                                        <p class="mb-0">Buffered tours are not lost -- they are automatically included in the next payout cycle once the buffer period has passed.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Require Approval</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" name="requires_approval"
                                       ${this.payoutRules.requires_approval ? 'checked' : ''} onchange="UpdateRequireApprovalLabel(this)">
                                <label class="form-check-label small" id="requireApprovalLabel">
                                    ${this.payoutRules.requires_approval ? 'Approval required' : 'Execute immediately'}
                                </label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Include Partial Payments
                                <i class="bi bi-info-circle text-primary ms-1" role="button" data-bs-toggle="modal" data-bs-target="#partialPaymentsInfoModal"></i>
                            </label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" id="include_partial_payments" name="include_partial_payments"
                                       ${this.payoutRules.include_partial_payments ? 'checked' : ''} onchange="UpdateIncludePartialPaymentsLabel(this)">
                                <label class="form-check-label small" id="includePartialPaymentsLabel" for="include_partial_payments">
                                    ${this.payoutRules.include_partial_payments ? 'Include partial paid bookings' : 'Exclude partial paid bookings (default)'}
                                </label>
                            </div>
                        </div>

                        <!-- Partial Payments Info Modal -->
                        <div class="modal fade" id="partialPaymentsInfoModal" tabindex="-1" aria-labelledby="partialPaymentsInfoModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h6 class="modal-title" id="partialPaymentsInfoModalLabel">Include Partial Payments</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>When disabled (default), only bookings where the total card payments equal or exceed the booking total are included in payouts. This ensures you only pay out for fully settled bookings.</p>
                                        <p class="mb-0">When enabled, bookings with any completed card transaction are included, even if the customer has not paid the full amount by card. This is useful when the remainder is collected in cash or other methods.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">
                                Notification Recipients
                            </label>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#notificationEmailsModal">
                                    <i class="bi bi-envelope me-1"></i>
                                    ${(this.payoutRules.notification_emails || []).length > 0
                                        ? this.payoutRules.notification_emails.length + ' recipient(s)'
                                        : 'All admins (default)'}
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 p-3 bg-light rounded">
                        <p class="small text-muted mb-0">
                            <strong>Summary:</strong> This rule will process payouts ${this.payoutRules.frequency}
                            ${this.payoutRules.frequency === 'weekly' ? ` on ${this.getWeekDayName(this.payoutRules.day_of_week)}` : ''}
                            ${this.payoutRules.frequency === 'monthly' ? ` on the ${this.payoutRules.day_of_month}${this.getOrdinalSuffix(this.payoutRules.day_of_month)} of each month` : ''}
                            at ${this.payoutRules.execution_time} for company with a minimum balance of ${this.currencyFormation.symbol}${this.payoutRules.minimum_amount}${this.payoutRules.max_payout_amount ? ` and a maximum ceiling of ${this.currencyFormation.symbol}${this.payoutRules.max_payout_amount}` : ''}${this.payoutRules.buffer_days > 0 ? ` with a ${this.payoutRules.buffer_days}-day buffer for refunds` : ''}.
                        </p>
                    </div>

                    <div class="d-flex gap-2 mt-3 justify-content-end">
                        <button type="button" class="btn btn-primary" id="saveRuleBtn">
                            <i class="bi bi-save me-2"></i>
                            Save Rule
                        </button>
                    </div>
                </div>
            </div>
        </form>`;

        $('#payoutRulesList').html(html);
    }

    renderTransactionsTable() {
        const sym = this.currencyFormation.symbol;
        const fmtNum = (n) => {
            const num = parseFloat(n) || 0;
            return sym + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        };

        const statusBadge = (status) => {
            const s = (status || '').toLowerCase();
            const labelMap = {
                completed: 'Succeeded', succeeded: 'Succeeded',
                processing: 'Processing', pending: 'Pending',
                pending_approval: 'Pending', failed: 'Failed',
                initiated: 'Initiated', scheduled: 'Scheduled',
                approved: 'Approved', rejected: 'Rejected',
            };
            const label = labelMap[s] || (s.charAt(0).toUpperCase() + s.slice(1));
            const classMap = {
                completed: 'dash-badge-succeeded', succeeded: 'dash-badge-succeeded',
                processing: 'dash-badge-processing', pending: 'dash-badge-pending',
                pending_approval: 'dash-badge-pending', failed: 'dash-badge-failed',
                initiated: 'dash-badge-initiated', scheduled: 'dash-badge-scheduled',
                approved: 'dash-badge-succeeded', rejected: 'dash-badge-failed',
            };
            const cls = classMap[s] || 'dash-badge-succeeded';
            return `<span class="dash-badge ${cls}">${label}</span>`;
        };

        const formatShortDate = (dateStr) => {
            if (!dateStr) return '-';
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return `${d.getMonth()+1}/${d.getDate()}/${String(d.getFullYear()).slice(-2)}`;
        };

        let totalNet = 0;

        const html = this.transactions.map(t => {
            const gross = parseFloat(t.total_amount) || 0;
            const fees = parseFloat(t.platform_fee) || 0;
            const net = parseFloat(t.net_amount) || 0;
            const paidOn = formatShortDate(t.sort_date);
            totalNet += net;

            return `
                <tr>
                    <td class="dash-td-id view-report-payout-details"
                    data-payout-id="${t.id}"
                    data-source="${t.source}"
                    >${t.name || '-'}${t.has_adjustments ? ` <span class="badge bg-warning text-dark ms-1" title="${t.adjustment_count} adjustment(s), net: ${fmtNum(t.adjustment_net)}">${t.adjustment_count} adj</span>` : ''}</td>
                    <td class="dash-td-amount col-gross">${fmtNum(gross)}</td>
                    <td class="dash-td-fee col-fee">-${fmtNum(fees)}</td>
                    <td class="dash-td-net">${fmtNum(net)}</td>
                    <td>${t.completed_bookings}</td>
                    <td class="dash-td-date">${paidOn}</td>
                    <td>${statusBadge(t.status)}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary view-report-payout-details"
                            data-payout-id="${t.id}"
                            data-source="${t.source}">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        $('#transactionsTableBody').empty().html(html);

        // Footer
        $('#rpTableFooter').html(
            `<strong>${this.transactions.length} rows</strong>&nbsp;&middot;&nbsp;<strong>${fmtNum(totalNet)} net</strong>`
        );

        // Period summary sidebar - net only
        const fmtShort = (n) => sym + Math.round(n).toLocaleString();
        $('#rp-period-net').text(fmtShort(totalNet));

        // Chart total
        $('#rp-chart-total-value').text(fmtNum(totalNet));

        // Build chart
        this.buildReportChart();
    }

    buildReportChart() {
        const container = document.getElementById('rpChartContainer');
        if (!container) return;

        const payouts = this.transactions || [];
        if (payouts.length === 0) {
            const oldSvg = container.querySelector('svg');
            if (oldSvg) oldSvg.remove();
            return;
        }

        const sorted = [...payouts].sort((a, b) => {
            const da = new Date(a.sort_date || 0);
            const db = new Date(b.sort_date || 0);
            return da - db;
        });

        const chartPoints = sorted.map(p => {
            const d = new Date(p.sort_date);
            const label = isNaN(d.getTime()) ? '-' : `${d.getMonth()+1}/${d.getDate()}`;
            return { label: label, value: parseFloat(p.net_amount) || 0, status: (p.status || '').toLowerCase() };
        });

        if (chartPoints.length === 0) return;

        const W = container.offsetWidth || 500;
        const H = 160;
        const pad = { top: 20, right: 20, bottom: 36, left: 52 };
        const chartW = W - pad.left - pad.right;
        const chartH = H - pad.top - pad.bottom;

        const values = chartPoints.map(p => p.value);
        const maxV = Math.max(...values) * 1.2 || 100;
        const minV = 0;
        const n = chartPoints.length;
        const xStep = n > 1 ? chartW / (n - 1) : chartW;

        const xOf = (i) => pad.left + i * xStep;
        const yOf = (v) => pad.top + chartH - ((v - minV) / (maxV - minV)) * chartH;

        const pts = chartPoints.map((p, i) => ({ x: xOf(i), y: yOf(p.value), ...p }));
        const polyPoints = pts.map(p => `${p.x},${p.y}`).join(' ');
        const areaPoints = `${pts[0].x},${pad.top + chartH} ` + polyPoints + ` ${pts[pts.length-1].x},${pad.top + chartH}`;

        const ySteps = 5;
        const yLabelsSvg = Array.from({length: ySteps + 1}, (_, i) => {
            const v = minV + (maxV - minV) * i / ySteps;
            const y = yOf(v);
            const sym = this.currencyFormation.symbol;
            let label;
            if (v === 0) label = sym + '0';
            else if (v >= 1000) label = sym + (v/1000).toFixed(1) + 'k';
            else label = sym + Math.round(v);
            return `<text x="${pad.left - 8}" y="${y + 4}" fill="#94a3b8" font-size="10" text-anchor="end">${label}</text>
                    <line x1="${pad.left}" y1="${y}" x2="${pad.left + chartW}" y2="${y}" stroke="#f1f5f9" stroke-width="1"/>`;
        }).join('');

        const seenLabels = new Set();
        const xLabelsSvg = pts.map((p) => {
            if (seenLabels.has(p.label)) return '';
            seenLabels.add(p.label);
            return `<text x="${p.x}" y="${pad.top + chartH + 18}" fill="#94a3b8" font-size="10" text-anchor="middle">${p.label}</text>`;
        }).join('');

        const circlesSvg = pts.map((p, i) => {
            const isFailed = p.status === 'failed';
            const strokeColor = isFailed ? '#dc2626' : '#2563eb';
            const fillColor = isFailed ? '#dc2626' : 'white';
            return `<circle cx="${p.x}" cy="${p.y}" r="5" fill="${fillColor}" stroke="${strokeColor}" stroke-width="2" class="rp-chart-dot" data-idx="${i}" data-label="${p.label}" data-value="${p.value}" style="cursor:pointer;"/>`;
        }).join('');

        const svgHtml = `<svg width="${W}" height="${H}" viewBox="0 0 ${W} ${H}" xmlns="http://www.w3.org/2000/svg" style="display:block;width:100%;height:${H}px;overflow:visible;">
            <defs>
                <linearGradient id="rpChartGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="rgba(37,99,235,0.18)"/>
                    <stop offset="100%" stop-color="rgba(37,99,235,0.01)"/>
                </linearGradient>
            </defs>
            ${yLabelsSvg}
            ${xLabelsSvg}
            <polygon points="${areaPoints}" fill="url(#rpChartGrad)"/>
            <polyline points="${polyPoints}" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>
            ${circlesSvg}
        </svg>`;

        const oldSvg = container.querySelector('svg');
        if (oldSvg) oldSvg.remove();
        container.insertAdjacentHTML('afterbegin', svgHtml);

        const tooltip = document.getElementById('rpChartTooltip');
        const self = this;
        container.querySelectorAll('.rp-chart-dot').forEach(dot => {
            dot.addEventListener('mouseenter', function() {
                const rect = container.getBoundingClientRect();
                const dotRect = dot.getBoundingClientRect();
                const val = self.formatCurrency(dot.dataset.value);
                tooltip.textContent = `${dot.dataset.label}: ${val}`;
                tooltip.style.left = (dotRect.left - rect.left + dotRect.width / 2) + 'px';
                tooltip.style.top = (dotRect.top - rect.top) + 'px';
                tooltip.style.opacity = '1';
            });
            dot.addEventListener('mouseleave', function() {
                tooltip.style.opacity = '0';
            });
        });
    }

    async savePayoutRule() {
        this.showLoader('body'); // Global loader for save action
        const form = $('#saveRuleForm')[0];
        const formData = new FormData(form);

        const newRule = {
            id: this.payoutRules?.id || Date.now().toString(),
            name: formData.get('name'),
            frequency: formData.get('frequency'),
            minimum_amount: parseFloat(formData.get('minimum_amount')),
            max_payout_amount: formData.get('max_payout_amount') ? parseFloat(formData.get('max_payout_amount')) : null,
            buffer_days: parseInt(formData.get('buffer_days')) || 0,
            start_date: formData.get('start_date') || null,
            execution_time: formData.get('execution_time'),
            enabled: formData.get('enabled') === 'on',
            requires_approval: formData.get('requires_approval') === 'on',
            include_partial_payments: formData.get('include_partial_payments') === 'on',
            notification_emails: (this.payoutRules.notification_emails && this.payoutRules.notification_emails.length > 0) ? this.payoutRules.notification_emails : null,
        };



        if (!newRule.name || !newRule.frequency || !newRule.execution_time || isNaN(newRule.minimum_amount)) {
            this.showToast('Please fill out all required fields.', 'error');
            this.hideLoader('body');
            return;
        }

        if (newRule.frequency === 'weekly') {
            newRule.day_of_week = parseInt(formData.get('day_of_week'));
            if (isNaN(newRule.day_of_week)) {
                this.showToast('Please select a day of the week.', 'error');
                this.hideLoader('body');
                return;
            }
        } else if (newRule.frequency === 'monthly') {
            newRule.day_of_month = parseInt(formData.get('day_of_month'));
            if (isNaN(newRule.day_of_month)) {
                this.showToast('Please select a day of the month.', 'error');
                this.hideLoader('body');
                return;
            }
        }

        try {
            await $.ajax({
                type: "POST",
                url: storeRuleUrl,
                data: JSON.stringify(newRule),
                contentType: "application/json",
            });

            this.showToast('Payout rules saved successfully!', 'success');
            this.payoutRules = { ...newRule };
            this.fetchPayoutRules();
            this.renderPayoutRules();
        } catch (error) {
            this.showToast('Unable to save payout rules. Please try again.', 'error');
        } finally {
            this.hideLoader('body');
        }
    }

    toggleRule(enabled) {
        this.showLoader('body');
        const rule = this.payoutRules;
        rule.enabled = enabled;

        $.ajax({
            type: "POST",
            url: storeRuleUrl,
            data: JSON.stringify(rule),
            contentType: "application/json",
            success: () => {
                this.fetchPayoutRules();
                this.showToast(`Rule ${enabled ? 'enabled' : 'disabled'} successfully!`, 'info');
            },
            error: () => {
                this.showToast('Unable to update payout rule. Please try again.', 'error');
            }
        }).always(() => {
            this.hideLoader('body');
        });
    }

    updateRule(field, value) {
        const rule = this.payoutRules;
        rule[field] = field === 'minimum_amount' ? parseFloat(value) : value;
        this.payoutRules = { ...rule };
        this.renderPayoutRules();
    }

    addNotificationEmail() {
        const input = $('#notificationEmailInput');
        const email = input.val().trim();

        if (!email) return;

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            this.showToast('Please enter a valid email address.', 'error');
            return;
        }

        if (!this.payoutRules.notification_emails) {
            this.payoutRules.notification_emails = [];
        }

        if (this.payoutRules.notification_emails.includes(email)) {
            this.showToast('This email has already been added.', 'error');
            return;
        }

        this.payoutRules.notification_emails.push(email);
        input.val('');
        this.renderNotificationEmailList();
        this.renderPayoutRules();
    }

    removeNotificationEmail(index) {
        if (!this.payoutRules.notification_emails) return;
        this.payoutRules.notification_emails.splice(index, 1);
        this.renderNotificationEmailList();
        this.renderPayoutRules();
    }

    renderNotificationEmailList() {
        const emails = this.payoutRules.notification_emails || [];
        if (emails.length === 0) {
            $('#notificationEmailList').html(
                '<p class="text-muted small text-center mb-0">No recipients added. All admins will be notified.</p>'
            );
            return;
        }
        const html = emails.map((email, index) => `
            <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2 mb-2">
                <div class="d-flex align-items-center">
                    <i class="bi bi-envelope text-primary me-2"></i>
                    <span class="small">${email}</span>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="payoutSystem.removeNotificationEmail(${index})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `).join('');
        $('#notificationEmailList').html(html);
    }

    handleFrequencyChange(frequency) {
        if (frequency === 'weekly') {
            $('#dayOfWeekGroup').removeClass('d-none');
            $('#dayOfMonthGroup').addClass('d-none');
        } else if (frequency === 'monthly') {
            $('#dayOfWeekGroup').addClass('d-none');
            $('#dayOfMonthGroup').removeClass('d-none');
        } else {
            $('#dayOfWeekGroup').addClass('d-none');
            $('#dayOfMonthGroup').addClass('d-none');
        }
    }

    populateDayOfMonthOptions() {
        const select = $('select[name="day_of_month"]');
        for (let i = 1; i <= 28; i++) {
            select.append(`<option value="${i}">${i}</option>`);
        }
    }

    getPackageInitials(name) {
        return name.split(' ').map(word => word[0]).join('').slice(0, 2).toUpperCase();
    }

    getStatusIcon(status) {
        const icons = {
            completed: '<i class="bi bi-check-circle"></i>',
            pending: '<i class="bi bi-clock"></i>',
            processing: '<i class="bi bi-arrow-clockwise"></i>',
            failed: '<i class="bi bi-x-circle"></i>',
            active: '<i class="bi bi-check-circle"></i>',
            inactive: '<i class="bi bi-pause-circle"></i>'
        };
        return icons[status] || '<i class="bi bi-clock"></i>';
    }

    getWeekDayOptions(selectedDay) {
        const days = [
            { value: 1, label: 'Monday' },
            { value: 2, label: 'Tuesday' },
            { value: 3, label: 'Wednesday' },
            { value: 4, label: 'Thursday' },
            { value: 5, label: 'Friday' },
            { value: 6, label: 'Saturday' },
            { value: 0, label: 'Sunday' }
        ];
        return days.map(day =>
            `<option value="${day.value}" ${day.value === selectedDay ? 'selected' : ''}>${day.label}</option>`
        ).join('');
    }

    getDayOfMonthOptions(selectedDay) {
        let options = '';
        for (let i = 1; i <= 28; i++) {
            options += `<option value="${i}" ${i === selectedDay ? 'selected' : ''}>${i}</option>`;
        }
        return options;
    }

    getWeekDayName(dayValue) {
        const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return days[dayValue] || 'Monday';
    }

    getOrdinalSuffix(day) {
        if (day >= 11 && day <= 13) return 'th';
        switch (day % 10) {
            case 1: return 'st';
            case 2: return 'nd';
            case 3: return 'rd';
            default: return 'th';
        }
    }

    showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);

        $('body').append(`<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>`);
        $('#toastContainer').append(toast);

        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();

        toast.on('hidden.bs.toast', function () {
            $(this).remove();
        });
    }

    showLoader(target) {
        let $target = $(target);
        if (!$target.find('.loader-overlay').length) {
            $target.append(`
                <div class="loader-overlay">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);
        }
        $target.find('.loader-overlay').show();
    }

    hideLoader(target) {
        $(target).find('.loader-overlay').hide();
    }

    // Approval System Methods
    loadApproval() {
        this.showLoader('#approval-view');
        this.fetchApprovalDashboard();
        this.fetchPendingPayouts();
        this.setupApprovalEventListeners();
        
        // Clear any existing selections
        $('#selectAllPayouts').prop('checked', false);
        $('.approval-payout-checkbox').prop('checked', false);
        this.updateBulkActions();
    }

    filterFailedPayouts() {
        $('#approvalStatusFilter').val('failed');
        this.fetchPendingPayouts();
    }

    setupApprovalEventListeners() {
        // Refresh button
        $('#refreshApprovalQueue').off('click').on('click', () => {
            this.loadApproval();
        });

        // Search and filters
        $('#applyApprovalFilters').off('click').on('click', () => {
            this.fetchPendingPayouts();
        });

        // Search on Enter key
        $('#approvalSearchInput').off('keypress').on('keypress', (e) => {
            if (e.which === 13) { // Enter key
                this.fetchPendingPayouts();
            }
        });

        // Auto-apply filters on change
        $('#approvalStatusFilter, #approvalMinAmount, #approvalMaxAmount').off('change').on('change', () => {
            this.fetchPendingPayouts();
        });

        // Select all checkbox
        $('#selectAllPayouts').off('change').on('change', (e) => {
            const isChecked = $(e.target).is(':checked');
            $('.approval-payout-checkbox').prop('checked', isChecked);
            this.updateBulkActions();
        });

        // Bulk actions
        $(document).off('change', '.approval-payout-checkbox').on('change', '.approval-payout-checkbox', () => {
            this.updateBulkActions();
            
            // Update select all checkbox state
            const totalCheckboxes = $('.approval-payout-checkbox').length;
            const checkedCheckboxes = $('.approval-payout-checkbox:checked').length;
            $('#selectAllPayouts').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
        });

        $('#bulkApproveBtn').off('click').on('click', () => {
            this.bulkApprovePayouts();
        });

        $('#bulkRejectBtn').off('click').on('click', () => {
            this.bulkRejectPayouts();
        });

        $('#clearSelectionBtn').off('click').on('click', () => {
            $('.approval-payout-checkbox').prop('checked', false);
            $('#selectAllPayouts').prop('checked', false);
            this.updateBulkActions();
        });

        // Individual actions
        $(document).off('click', '.approve-payout-btn').on('click', '.approve-payout-btn', (e) => {
            e.preventDefault();
            const button = $(e.currentTarget);
            const payoutId = button.data('payout-id') || button.closest('[data-payout-id]').data('payout-id');
            if (payoutId) {
                this.approvePayout(payoutId);
            }
        });

        $(document).off('click', '.reject-payout-btn').on('click', '.reject-payout-btn', (e) => {
            e.preventDefault();
            const button = $(e.currentTarget);
            const payoutId = button.data('payout-id') || button.closest('[data-payout-id]').data('payout-id');
            if (payoutId) {
                this.showRejectionModal(payoutId);
            }
        });

        $(document).off('click', '.view-payout-details').on('click', '.view-payout-details', (e) => {
            e.preventDefault();
            const button = $(e.currentTarget);
            const payoutId = button.data('payout-id') || button.closest('[data-payout-id]').data('payout-id');
            if (payoutId) {
                this._detailBookingSearch = '';
                this.showPayoutDetails(payoutId);
            }
        });

        // Modal actions
        $('#confirmApprovalRejectionBtn').off('click').on('click', () => {
            this.confirmRejection();
        });
    }

    async fetchApprovalDashboard() {
        try {
            const response = await $.ajax({
                url: approvalApiUrls.dashboardData,
                method: 'GET'
            });

            if (response.success) {
                $('#approval-pending-count').text(response.data.pending_count);
                $('#approval-approved-today').text(response.data.approved_today);
                $('#approval-rejected-today').text(response.data.rejected_today);
                $('#approval-failed-count').text(response.data.failed_count);
                $('#approval-failed-amount').text(`${this.currencyFormation.symbol}${response.data.failed_amount}`);
                $('#pending-approvals-badge').text(response.data.pending_count).toggle(response.data.pending_count > 0);
            }
        } catch (error) {
            console.error('Failed to fetch approval dashboard:', error);
            this.showToast('Failed to load approval dashboard', 'error');
        }
    }

    async fetchPendingPayouts(page = 1) {
        try {
            // Show loading state
            $('#approvalPayoutsList').html(`
                <tr>
                    <td colspan="6" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Loading payouts...</div>
                    </td>
                </tr>
            `);

            const filters = {
                search: $('#approvalSearchInput').val(),
                status: $('#approvalStatusFilter').val(),
                min_amount: $('#approvalMinAmount').val(),
                max_amount: $('#approvalMaxAmount').val(),
                page: page,
                per_page: 10
            };

            const response = await $.ajax({
                url: approvalApiUrls.pending,
                method: 'GET',
                data: filters
            });

            if (response.success) {
                this.renderApprovalPayouts(response.data);
                this.renderApprovalPagination(response.pagination);
                $('#approvalPayoutCount').text(`${response.pagination.total} payouts`);
            }
        } catch (error) {
            console.error('Failed to fetch pending payouts:', error);
            this.showToast('Failed to load pending payouts', 'error');
            
            // Show error state
            $('#approvalPayoutsList').html(`
                <tr>
                    <td colspan="6" class="text-center py-4 text-danger">
                        <i class="bi bi-exclamation-triangle display-4"></i>
                        <div class="mt-2">Failed to load payouts</div>
                        <button class="btn btn-outline-primary btn-sm mt-2" onclick="window.payoutSystem.fetchPendingPayouts()">
                            <i class="bi bi-arrow-clockwise me-1"></i>
                            Retry
                        </button>
                    </td>
                </tr>
            `);
        } finally {
            this.hideLoader('#approval-view');
        }
    }

    renderApprovalPayouts(payouts) {
        const container = $('#approvalPayoutsList');

        // Kept so the approve/reject confirmations can name the payout and its amount
        // without a second round trip.
        this._pendingPayoutsById = {};
        (payouts || []).forEach(p => { this._pendingPayoutsById[p.id] = p; });

        if (!payouts || payouts.length === 0) {
            container.html(`
                <tr>
                    <td colspan="6" class="text-center py-5">
                        <i class="bi bi-inbox display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No payouts found</h5>
                        <p class="text-muted">There are no payouts matching your criteria.</p>
                    </td>
                </tr>
            `);
            return;
        }

        const payoutRows = payouts.map(payout => `
            <tr>
                <td>
                    <div class="form-check">
                        <input class="form-check-input approval-payout-checkbox" type="checkbox" value="${payout.id}">
                    </div>
                </td>
                <td>
                    <div>
                        <a href="#" class="fw-bold text-primary view-payout-details" data-payout-id="${payout.id}" title="View payout details">${this.escapeHtml(payout.payout_reference)}</a>
                        <br>
                        <small class="text-muted">${this.escapeHtml(payout.stripe_account_id)}</small>
                    </div>
                </td>
                <td>
                    <div>
                        <div class="fw-bold ${payout.status === 'failed' || payout.status === 'rejected' ? 'text-danger' : 'text-success'}">${this.formatCurrency(payout.net_amount)}</div>
                        <small class="text-muted">${payout.booking_count} bookings</small>
                        ${payout.adjustment_count > 0 ? `<br><small class="text-warning"><i class="bi bi-exclamation-triangle-fill"></i> ${payout.adjustment_count} adj (${this.formatCurrency(payout.adjustment_net_impact)})</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="badge ${this.getStatusBadgeClass(payout.status || '')}">${this.formatStatus(payout.status || 'unknown')}</span>
                </td>
                <td>
                    <small class="text-muted">${this.formatDate(payout.created_at)}</small>
                </td>
                <td>
                    <!-- No view button here: the payout reference itself opens the details. -->
                    <div class="btn-group btn-group-sm">
                        ${payout.status === 'pending_approval' ? `
                            <button class="btn btn-success approve-payout-btn" data-payout-id="${payout.id}" title="Approve">
                                <i class="bi bi-check"></i>
                            </button>
                            <button class="btn btn-danger reject-payout-btn" data-payout-id="${payout.id}" title="Reject">
                                <i class="bi bi-x"></i>
                            </button>
                        ` : ''}
                        ${payout.status === 'failed' ? `
                            <button class="btn btn-warning reapprove-payout-btn" data-payout-id="${payout.id}" title="Re-approve for retry">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            <button class="btn btn-outline-danger cancel-payout-btn" data-payout-id="${payout.id}" title="Cancel payout">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        ` : ''}
                    </div>
                    ${payout.status !== 'pending_approval' && payout.status !== 'failed' ? '<span class="text-muted">-</span>' : ''}
                </td>
            </tr>
        `).join('');

        container.html(payoutRows);
    }

    renderApprovalPagination(pagination) {
        const paginationContainer = $('#approvalPagination');
        
        if (!pagination || pagination.last_page <= 1) {
            paginationContainer.empty();
            return;
        }

        let paginationHtml = '';
        
        // Previous button
        if (pagination.current_page > 1) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current_page - 1}">Previous</a></li>`;
        }

        // Page numbers
        for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
            paginationHtml += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        // Next button
        if (pagination.current_page < pagination.last_page) {
            paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${pagination.current_page + 1}">Next</a></li>`;
        }

        paginationContainer.html(paginationHtml);

        // Pagination click handlers
        paginationContainer.off('click', 'a').on('click', 'a', (e) => {
            e.preventDefault();
            const page = $(e.target).data('page');
            if (page) {
                this.fetchPendingPayouts(page);
            }
        });
    }

    updateBulkActions() {
        const selectedCount = $('.approval-payout-checkbox:checked').length;
        $('#approvalSelectedCount').text(selectedCount);
        $('#approvalBulkActions').toggle(selectedCount > 0);
    }

    /**
     * Confirm step shared by the payout actions that move money or destroy a payout.
     * Returns true only when the operator explicitly confirms; dismissing the dialog
     * any other way (Esc, backdrop, Cancel) is treated as "no".
     */
    async confirmPayoutAction({ title, html, confirmButtonText, confirmButtonColor = '#198754' }) {
        const result = await Swal.fire({
            title,
            html,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor,
            cancelButtonColor: '#6c757d',
            confirmButtonText,
            cancelButtonText: 'Cancel',
            reverseButtons: true
        });

        return result.isConfirmed === true;
    }

    /**
     * Reference and amount for the confirm dialog, so the operator can see which payout
     * they are about to release. Falls back to the open detail modal's payout when the
     * action was triggered from there rather than from the pending list.
     */
    payoutConfirmDetail(payoutId) {
        const cached = (this._pendingPayoutsById || {})[payoutId];
        const current = this._currentDetailsData?.payout;
        const payout = cached || (current && String(current.id) === String(payoutId) ? current : null);

        if (!payout) return '';

        return `<div class="mt-2"><strong>${this.escapeHtml(payout.payout_reference)}</strong><br>`
            + `${this.formatCurrency(payout.net_amount)} across ${payout.booking_count} booking(s)</div>`;
    }

    async approvePayout(payoutId, notes = '') {
        const confirmed = await this.confirmPayoutAction({
            title: 'Approve this payout?',
            html: 'Approving releases the funds to the connected account. This cannot be undone.'
                + this.payoutConfirmDetail(payoutId),
            confirmButtonText: 'Yes, approve'
        });

        if (!confirmed) return;

        const btn = $('#modalApproveBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Approving...');
        try {
            const response = await $.ajax({
                url: approvalApiUrls.approve.replace(':id', payoutId),
                method: 'POST',
                data: { notes }
            });

            if (response.success) {
                this.showToast('Payout approved successfully', 'success');
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
            } else {
                this.showToast(response.message || 'Failed to approve payout', 'error');
            }
        } catch (error) {
            console.error('Failed to approve payout:', error);
            this.showToast('Failed to approve payout', 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i>Approve');
        }
    }

    async recalculatePayout(payoutId) {
        const btn = $('#modalRecalculateBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Recalculating...');

        try {
            const response = await $.ajax({
                url: approvalApiUrls.recalculate.replace(':id', payoutId),
                method: 'POST'
            });

            if (response.success) {
                this.showToast(response.message, 'success');
                // Refresh the detail view to show updated amounts
                this.showPayoutDetails(payoutId);
                // Refresh the list behind the modal
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
            } else {
                this.showToast(response.message || 'Failed to recalculate payout.', 'error');
            }
        } catch (error) {
            const msg = error.responseJSON?.message || 'Failed to recalculate payout.';
            this.showToast(msg, 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-arrow-repeat me-1"></i>Recalculate');
        }
    }

    showRejectionModal(payoutId) {
        this.currentRejectionPayoutId = payoutId;
        $('#approvalRejectionReason').val('');
        $('#approvalRejectionModal').modal('show');
    }

    async confirmRejection() {
        const reason = $('#approvalRejectionReason').val().trim();

        if (!reason) {
            this.showToast('Please provide a rejection reason', 'error');
            return;
        }

        const btn = $('#approvalRejectionModal .btn-danger');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Rejecting...');
        try {
            const response = await $.ajax({
                url: approvalApiUrls.reject.replace(':id', this.currentRejectionPayoutId),
                method: 'POST',
                data: { reason }
            });

            if (response.success) {
                this.showToast('Payout rejected successfully', 'success');
                $('#approvalRejectionModal').modal('hide');
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
            } else {
                this.showToast(response.message || 'Failed to reject payout', 'error');
            }
        } catch (error) {
            console.error('Failed to reject payout:', error);
            this.showToast('Failed to reject payout', 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-x-circle me-1"></i>Reject');
        }
    }

    async bulkApprovePayouts() {
        const selectedIds = $('.approval-payout-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            this.showToast('Please select payouts to approve', 'error');
            return;
        }

        const confirmed = await this.confirmPayoutAction({
            title: `Approve ${selectedIds.length} payout(s)?`,
            html: 'Approving releases the funds to each connected account. This cannot be undone.',
            confirmButtonText: `Yes, approve ${selectedIds.length}`
        });

        if (!confirmed) return;

        try {
            const response = await $.ajax({
                url: approvalApiUrls.bulkApprove,
                method: 'POST',
                data: { 
                    payout_ids: selectedIds,
                    notes: 'Bulk approval'
                }
            });

            if (response.success) {
                this.showToast(`${selectedIds.length} payout(s) approved successfully`, 'success');
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
                this.updateBulkActions();
            } else {
                this.showToast(response.message || 'Failed to approve payouts', 'error');
            }
        } catch (error) {
            console.error('Failed to bulk approve payouts:', error);
            this.showToast('Failed to approve payouts', 'error');
        }
    }

    async bulkRejectPayouts() {
        const selectedIds = $('.approval-payout-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        if (selectedIds.length === 0) {
            this.showToast('Please select payouts to reject', 'error');
            return;
        }

        const result = await Swal.fire({
            title: `Reject ${selectedIds.length} payout(s)?`,
            html: 'The bookings return to the pool and can be picked up by a later payout cycle.',
            icon: 'warning',
            input: 'textarea',
            inputLabel: 'Reason for rejection',
            inputPlaceholder: 'Enter a reason...',
            inputValidator: (value) => {
                if (!value || !value.trim()) return 'A reason is required.';
            },
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Yes, reject ${selectedIds.length}`,
            cancelButtonText: 'Cancel',
            reverseButtons: true
        });

        if (!result.isConfirmed) return;

        const reason = result.value;

        try {
            const response = await $.ajax({
                url: approvalApiUrls.bulkReject,
                method: 'POST',
                data: { 
                    payout_ids: selectedIds,
                    reason: reason.trim()
                }
            });

            if (response.success) {
                this.showToast(`${selectedIds.length} payout(s) rejected successfully`, 'success');
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
                this.updateBulkActions();
            } else {
                this.showToast(response.message || 'Failed to reject payouts', 'error');
            }
        } catch (error) {
            console.error('Failed to bulk reject payouts:', error);
            this.showToast('Failed to reject payouts', 'error');
        }
    }

    async showPayoutDetails(payoutId, page = 1) {
        try {
            const params = { page: page, per_page: 25 };
            if (this._detailBookingSearch) params.booking_id = this._detailBookingSearch;
            const response = await $.ajax({
                url: approvalApiUrls.details.replace(':id', payoutId),
                method: 'GET',
                data: params
            });

            if (response.success) {
                this._currentDetailsPayoutId = payoutId;
                this._currentDetailsSource = 'pending_payout';
                response.data._bookingSearch = this._detailBookingSearch || '';
                this.renderPayoutDetails(response.data);
                $('#approvalPayoutDetailsModal').modal('show');
            } else {
                this.showToast('Failed to load payout details', 'error');
            }
        } catch (error) {
            console.error('Failed to fetch payout details:', error);
            this.showToast('Failed to load payout details', 'error');
        }
    }

    async showReportPayoutDetails(payoutId, source, page = 1) {
        if (source === 'pending_payout') {
            return this.showPayoutDetails(payoutId, page);
        }

        try {
            const url = fetchAutoPayoutDetailsUrl.replace(':id', payoutId);
            const params = { page: page, per_page: 25 };
            if (this._detailBookingSearch) params.booking_id = this._detailBookingSearch;
            const response = await $.ajax({
                url: url,
                method: 'GET',
                data: params
            });

            if (response.success) {
                this._currentDetailsPayoutId = payoutId;
                this._currentDetailsSource = source;
                response.data._bookingSearch = this._detailBookingSearch || '';
                this.renderPayoutDetails(response.data);
                $('#approvalPayoutDetailsModal').modal('show');
            } else {
                this.showToast('Failed to load payout details', 'error');
            }
        } catch (error) {
            console.error('Failed to fetch auto payout details:', error);
            this.showToast('Failed to load payout details', 'error');
        }
    }

    renderPayoutDetails(data) {
        this._currentDetailsData = data;
        // Drop any summary loaded for a previously opened payout so "Copy for email"
        // can never hand back another payout's figures.
        this._currentSummaryData = null;
        const { payout, items, items_total, items_page, items_last_page, logs, stripe_balance } = data;

        const hasStripeInfo = !!payout.stripe_account_id;
        const hasLogs = logs && logs.length > 0;
        const execLog = (logs || []).find(l => l.metadata && l.metadata.balance_available !== undefined);

        // One label/value cell of the header grid. `wide` gives the cell two column
        // slots, for values long enough to wrap otherwise.
        const meta = (label, value, wide = false) => `
            <div class="payout-meta${wide ? ' payout-meta-wide' : ''}">
                <span class="payout-meta-label">${label}</span>
                <span class="payout-meta-value">${value}</span>
            </div>`;

        const detailsHtml = `
            <div class="d-flex flex-column" style="flex: 1 1 auto; min-height: 0;">
                <div class="row g-2 mb-2">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-body p-2">
                                <div class="payout-meta-grid">
                                    <div class="payout-meta-heading">Payout Information</div>
                                    ${meta('Reference', this.escapeHtml(payout.payout_reference), true)}
                                    ${meta('Status', `<span class="badge ${this.getStatusBadgeClass(payout.status)}">${this.formatStatus(payout.status)}</span>`)}
                                    ${meta('Bookings', payout.booking_count)}
                                    ${meta('Total Amount', this.formatCurrency(payout.total_amount))}
                                    ${meta('Platform Fee', this.formatCurrency(payout.platform_fee))}
                                    ${meta('Net Amount', `<strong>${this.formatCurrency(payout.net_amount)}</strong>`)}
                                    ${meta('Created', this.formatDate(payout.created_at || payout.processed_date))}
                                    ${meta('Executed', payout.processed_date ? this.formatDate(payout.processed_date) : 'Not executed yet')}
                                    ${meta('Booking Window', this.formatPayoutWindow(payout), true)}
                                    <div class="payout-meta-heading">Stripe Information</div>
                                    ${hasStripeInfo ? meta('Account ID', this.escapeHtml(payout.stripe_account_id), true) : ''}
                                    ${meta('Rule Used', this.escapeHtml(payout.rule_snapshot?.name || payout.rule?.name || 'N/A'))}
                                    ${stripe_balance ? meta('Current Balance', this.formatCurrency(stripe_balance.available)) + meta('Pending Balance', this.formatCurrency(stripe_balance.pending)) : ''}
                                    ${execLog ? meta('Balance at Payout', this.formatCurrency(execLog.metadata.balance_available)) + meta('Balance After', this.formatCurrency(execLog.metadata.balance_after_payout)) : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-body p-2">
                                <div class="payout-meta-heading mb-2">Activity Log</div>
                                ${hasLogs ? `
                                    <div class="payout-activity-log">
                                        ${logs.map(log => `
                                            <div class="small mb-2">
                                                <strong>${this.formatStatus(log.action)}</strong> - ${this.formatDate(log.created_at)}
                                                ${log.notes ? `<br><em>${this.escapeHtml(log.notes)}</em>` : ''}
                                                ${log.metadata && log.metadata.removed && log.metadata.removed.length > 0 ? `<br><span class="text-muted">Removed: ${log.metadata.removed.map(r => '#' + r.booking_id + ' (' + this.escapeHtml(r.reason) + ')').join(', ')}</span>` : ''}
                                                ${log.metadata && log.metadata.updated && log.metadata.updated.length > 0 ? `<br><span class="text-muted">Updated: ${log.metadata.updated.map(u => '#' + u.booking_id + ' (net ' + this.formatCurrency(u.previous_net) + ' &rarr; ' + this.formatCurrency(u.new_net) + ')').join(', ')}</span>` : ''}
                                                ${log.metadata && log.metadata.balance_available !== undefined ? `<br><span class="text-muted">Balance: ${this.formatCurrency(log.metadata.balance_available)}</span>` : ''}
                                            </div>
                                        `).join('')}
                                    </div>
                                ` : '<p class="text-muted small mb-0">No activity log entries.</p>'}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Period roll-up. Loaded on demand: the unpaid-bookings figure scans the
                     whole collection window, which is too heavy to run on every modal open. -->
                <div class="card mb-2" id="payoutPeriodSummaryCard">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fs-6">Period Summary</h6>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" id="loadPeriodSummaryBtn">
                                    <i class="bi bi-table me-1"></i>Load summary
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" id="copyPeriodSummaryBtn" style="display:none;">
                                    <i class="bi bi-clipboard me-1"></i>Copy for email
                                </button>
                            </div>
                        </div>
                        <div id="payoutPeriodSummaryBody" class="mt-2"></div>
                    </div>
                </div>

                ${items && items.length > 0 || data._bookingSearch ? `
                    <div class="flex-grow-1 d-flex flex-column" style="min-height: 0;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-3">
                                <h6 class="mb-0">Booking Items</h6>
                                <div class="input-group input-group-sm" style="width: 200px;">
                                    <span class="input-group-text"><i class="bi bi-hash"></i></span>
                                    <input type="text" class="form-control" placeholder="Find booking..." id="detailBookingSearch" value="${this.escapeHtml(data._bookingSearch || '')}">
                                    ${data._bookingSearch ? '<button class="btn btn-outline-secondary btn-sm" id="clearDetailBookingSearch" type="button"><i class="bi bi-x"></i></button>' : ''}
                                </div>
                            </div>
                            <button class="btn btn-sm btn-outline-primary" id="toggleInlineAdjustmentForm">
                                <i class="bi bi-plus-circle me-1"></i> Add Adjustment
                            </button>
                        </div>

                        <!-- Inline adjustment form (hidden by default) -->
                        <div id="inlineAdjustmentForm" class="card border-primary mb-3" style="display: none;">
                            <div class="card-body py-2 px-3">
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Booking</label>
                                        <input type="text" class="form-control form-control-sm" id="inlineAdjBookingId" list="inlineAdjBookingList" placeholder="Type booking #..." autocomplete="off">
                                        <datalist id="inlineAdjBookingList">
                                            ${items.map(item => `<option value="${item.booking_id}">#${item.booking_id}</option>`).join('')}
                                        </datalist>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Type</label>
                                        <select class="form-select form-select-sm" id="inlineAdjType">
                                            <option value="credit">Credit</option>
                                            <option value="debit">Debit</option>
                                        </select>
                                        <div class="form-text lh-1 mt-1" id="inlineAdjTypeHint" style="font-size: 0.7rem;">Add to next payout</div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Net Amount</label>
                                        <input type="number" class="form-control form-control-sm" id="inlineAdjNetAmount" step="0.01" min="0.01" placeholder="0.00">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Reason</label>
                                        <input type="text" class="form-control form-control-sm" id="inlineAdjReason" placeholder="Reason for adjustment..." minlength="5">
                                    </div>
                                    <div class="col-md-3 d-flex gap-1">
                                        <button class="btn btn-sm btn-primary flex-grow-1" id="submitInlineAdjustment" data-payout-id="${payout.id}">
                                            <i class="bi bi-check-lg me-1"></i>Save
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" id="cancelInlineAdjustment">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive flex-grow-1" style="overflow-y: auto;">
                            ${items && items.length > 0 ? `
                            <table class="table table-sm mb-0">
                                <thead style="position: sticky; top: 0; background: #fff; z-index: 1;">
                                    <tr>
                                        <th>Booking</th>
                                        <th>Customer</th>
                                        <th>Tour</th>
                                        <th>Date</th>
                                        <th>Pax</th>
                                        <th>Booking Total</th>
                                        <th>Collected</th>
                                        <th>Refunded</th>
                                        <th>Fee</th>
                                        <th>Net</th>
                                        <th>Booking Status</th>
                                        <th>Payout Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${items.map(item => {
                                        const adjTooltip = item.has_adjustment && item.adjustments
                                            ? item.adjustments.map(a => (a.type || '') + ': ' + (a.reason || 'no reason')).join('; ')
                                            : '';
                                        const bookingUrl = (typeof bookingDetailUrl !== 'undefined') ? bookingDetailUrl.replace(':id', item.booking_id) : '#';
                                        const statusClass = item.booking_status === 'Cancelled' ? 'text-danger' : (item.booking_status === 'Completed' ? 'text-success' : 'text-muted');
                                        return `
                                        <tr${item.has_adjustment ? ' class="table-warning"' : ''}>
                                            <td><a href="${bookingUrl}" target="_blank" class="text-primary text-decoration-none" title="Open booking in new tab">${item.booking_id} <i class="bi bi-box-arrow-up-right" style="font-size: 0.65rem;"></i></a> ${item.has_adjustment ? '<i class="bi bi-exclamation-triangle-fill text-warning ms-1" title="Amended after payout"></i>' : ''}</td>
                                            <td class="small text-truncate" style="max-width:120px;" title="${this.escapeHtml(item.customer_name || '')}">${this.escapeHtml(item.customer_name || '-')}</td>
                                            <td class="small text-truncate" style="max-width:150px;" title="${this.escapeHtml(item.tour_package_name || '')}">${this.escapeHtml(item.tour_package_name || '-')}</td>
                                            <td class="small">${item.tour_date || '-'}</td>
                                            <td>${item.tourist_count || '-'}</td>
                                            <td>${item.booking_total ? this.formatCurrency(item.booking_total) : '-'}</td>
                                            <td>${this.formatCurrency(item.total_amount)}</td>
                                            <td>${item.refund_total > 0 ? '<span class="text-danger">' + this.formatCurrency(item.refund_total) + ' (' + item.refund_count + ')</span>' : '-'}</td>
                                            <td>${this.formatCurrency(item.platform_fee)}</td>
                                            <td>${this.formatCurrency(item.net_amount)}</td>
                                            <td><span class="small ${statusClass}">${item.booking_status || '-'}</span></td>
                                            <td>${item.has_adjustment ? `<span class="badge bg-warning text-dark" title="${this.escapeHtml(adjTooltip)}">Amended (${item.adjustments ? item.adjustments.length : ''})</span>` : '<span class="badge bg-light text-muted">OK</span>'}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                            ` : `<div class="text-center py-4 text-muted">
                                <i class="bi bi-search d-block mb-2" style="font-size: 1.5rem;"></i>
                                Booking #${this.escapeHtml(data._bookingSearch)} not found in this payout
                            </div>`}
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <p class="text-muted small mb-0">Showing ${items.length} of ${items_total} booking items (page ${items_page} of ${items_last_page})</p>
                            ${items_last_page > 1 ? `
                                <nav>
                                    <ul class="pagination pagination-sm mb-0">
                                        <li class="page-item ${items_page <= 1 ? 'disabled' : ''}">
                                            <a class="page-link details-prev-page" href="#">Previous</a>
                                        </li>
                                        ${this.getDetailPageNumbers(items_page, items_last_page).map(p => `
                                            <li class="page-item ${p === items_page ? 'active' : ''} ${p === '...' ? 'disabled' : ''}">
                                                <a class="page-link ${p !== '...' ? 'details-goto-page' : ''}" href="#" data-page="${p}">${p}</a>
                                            </li>
                                        `).join('')}
                                        <li class="page-item ${items_page >= items_last_page ? 'disabled' : ''}">
                                            <a class="page-link details-next-page" href="#">Next</a>
                                        </li>
                                    </ul>
                                </nav>
                            ` : ''}
                        </div>
                    </div>
                ` : ''}

                ${data.applied_adjustments && data.applied_adjustments.length > 0 ? `
                    <div class="mt-3">
                        <h6>Adjustments Applied in This Payout</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Booking</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Fee</th>
                                        <th>Net</th>
                                        <th>Original Payout</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${data.applied_adjustments.map(adj => {
                                        const adjBookingUrl = (typeof bookingDetailUrl !== 'undefined') ? bookingDetailUrl.replace(':id', adj.booking_id) : '#';
                                        return `
                                        <tr class="${adj.type === 'debit' ? 'table-danger' : 'table-success'}">
                                            <td><a href="${adjBookingUrl}" target="_blank" class="text-decoration-none">#${adj.booking_id} <i class="bi bi-box-arrow-up-right" style="font-size: 0.65rem;"></i></a></td>
                                            <td>${adj.type === 'credit' ? '<span class="badge bg-success">Credit</span>' : '<span class="badge bg-danger">Debit</span>'}</td>
                                            <td>${this.formatCurrency(adj.gross_amount)}</td>
                                            <td>${this.formatCurrency(adj.platform_fee)}</td>
                                            <td class="fw-semibold">${this.formatCurrency(adj.net_amount)}</td>
                                            <td class="small">${this.escapeHtml(adj.payout_reference) || '-'}</td>
                                            <td class="small">${this.escapeHtml(adj.reason) || '-'}</td>
                                        </tr>`;
                                    }).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : ''}
            </div>
        `;

        $('#approvalPayoutDetailsContent').html(detailsHtml);

        // Pagination handlers
        const navigatePage = (page) => {
            if (this._currentDetailsSource === 'auto_payout') {
                this.showReportPayoutDetails(this._currentDetailsPayoutId, 'auto_payout', page);
            } else {
                this.showPayoutDetails(this._currentDetailsPayoutId, page);
            }
        };
        $('.details-prev-page').off('click').on('click', (e) => {
            e.preventDefault();
            navigatePage(items_page - 1);
        });
        $('.details-next-page').off('click').on('click', (e) => {
            e.preventDefault();
            navigatePage(items_page + 1);
        });
        $('.details-goto-page').off('click').on('click', (e) => {
            e.preventDefault();
            const page = parseInt($(e.currentTarget).data('page'));
            if (page) navigatePage(page);
        });

        // Booking search in detail view
        $('#detailBookingSearch').off('keypress').on('keypress', (e) => {
            if (e.which === 13) {
                e.preventDefault();
                this._detailBookingSearch = e.target.value.trim();
                if (this._currentDetailsSource === 'auto_payout') {
                    this.showReportPayoutDetails(this._currentDetailsPayoutId, 'auto_payout', 1);
                } else {
                    this.showPayoutDetails(this._currentDetailsPayoutId, 1);
                }
            }
        });
        $('#clearDetailBookingSearch').off('click').on('click', () => {
            this._detailBookingSearch = '';
            if (this._currentDetailsSource === 'auto_payout') {
                this.showReportPayoutDetails(this._currentDetailsPayoutId, 'auto_payout', 1);
            } else {
                this.showPayoutDetails(this._currentDetailsPayoutId, 1);
            }
        });

        // Inline adjustment form handlers
        $('#toggleInlineAdjustmentForm').off('click').on('click', () => {
            $('#inlineAdjustmentForm').slideToggle(200);
        });
        $('#cancelInlineAdjustment').off('click').on('click', () => {
            $('#inlineAdjustmentForm').slideUp(200);
        });
        $('#submitInlineAdjustment').off('click').on('click', () => {
            this.submitInlineAdjustment(payout.id);
        });
        $('#inlineAdjType').off('change').on('change', (e) => {
            const hints = { credit: 'Add to next payout', debit: 'Deduct from next payout' };
            $('#inlineAdjTypeHint').text(hints[e.target.value]);
        });

        // Export CSV button -- always visible
        $('#modalExportCsvBtn').off('click').on('click', () => {
            this.exportPayoutDetailsCsv();
        });

        $('#loadPeriodSummaryBtn').off('click').on('click', () => {
            this.loadPeriodSummary();
        });

        $('#copyPeriodSummaryBtn').off('click').on('click', () => {
            this.copyPeriodSummary();
        });

        // Show/hide action buttons based on status
        if (payout.status === 'pending_approval' || payout.status === 'approved') {
            $('#modalRecalculateBtn').show().off('click').on('click', () => {
                this.recalculatePayout(payout.id);
            });
        } else {
            $('#modalRecalculateBtn').hide();
        }

        if (payout.status === 'pending_approval') {
            $('#modalApproveBtn').show().off('click').on('click', () => {
                // Close first: the confirm dialog would otherwise open on top of a modal
                // that traps focus.
                $('#approvalPayoutDetailsModal').modal('hide');
                this.approvePayout(payout.id);
            });
            $('#modalRejectBtn').show().off('click').on('click', () => {
                $('#approvalPayoutDetailsModal').modal('hide');
                this.showRejectionModal(payout.id);
            });
        } else {
            $('#modalApproveBtn, #modalRejectBtn').hide();
        }
    }

    getStatusBadgeClass(status) {
        switch (status) {
            case 'pending_approval': return 'bg-warning';
            case 'approved': return 'bg-success';
            case 'rejected': return 'bg-danger';
            case 'failed': return 'bg-danger';
            case 'cancelled': return 'bg-secondary';
            case 'executed': return 'bg-info';
            case 'created': return 'bg-secondary';
            default: return 'bg-secondary';
        }
    }

    formatStatus(status) {
        return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    }

    formatDate(dateString) {
        return new Date(dateString).toLocaleString();
    }

    /**
     * The window of tour dates a payout collected its bookings from.
     *
     * Rendered server-side (period_display) because the stored value is wall-clock time
     * in the operator's timezone -- reparsing it here would shift it into the viewer's
     * timezone and misreport the cutoff. Payouts made before the window was recorded
     * say so rather than showing a guess.
     */
    formatPayoutWindow(payout) {
        const display = this._currentDetailsData?.period_display;
        if (display) return this.escapeHtml(display);
        if (!payout?.period_start || !payout?.period_end) return '<span class="text-muted">Not recorded</span>';
        return this.escapeHtml(`${payout.period_start} - ${payout.period_end}`);
    }

    escapeHtml(str) {
        if (!str) return '';
        this._escDiv.textContent = str;
        return this._escDiv.innerHTML;
    }

    formatCurrency(value) {
        const num = parseFloat(value) || 0;
        return this.currencyFormation.symbol + num.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    async reapprovePayout(payoutId) {
        try {
            const result = await Swal.fire({
                title: 'Re-approve Payout?',
                text: 'This will reset the retry count and queue the payout for execution again.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f0ad4e',
                confirmButtonText: 'Yes, re-approve'
            });

            if (!result.isConfirmed) return;

            const response = await $.ajax({
                url: approvalApiUrls.reapprove.replace(':id', payoutId),
                method: 'POST'
            });

            if (response.success) {
                this.showToast('Payout re-approved for retry', 'success');
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
            } else {
                this.showToast(response.message || 'Failed to re-approve payout', 'error');
            }
        } catch (error) {
            console.error('Failed to re-approve payout:', error);
            this.showToast('Failed to re-approve payout', 'error');
        }
    }

    async cancelPayout(payoutId) {
        try {
            const result = await Swal.fire({
                title: 'Cancel Payout?',
                text: 'This will permanently cancel the failed payout. The bookings will be available for future payout cycles.',
                icon: 'warning',
                input: 'textarea',
                inputLabel: 'Reason for cancellation',
                inputPlaceholder: 'Enter a reason...',
                inputValidator: (value) => {
                    if (!value) return 'A reason is required.';
                },
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, cancel it'
            });

            if (!result.isConfirmed) return;

            const response = await $.ajax({
                url: approvalApiUrls.cancel.replace(':id', payoutId),
                method: 'POST',
                data: { reason: result.value }
            });

            if (response.success) {
                this.showToast('Payout cancelled successfully', 'success');
                this.fetchPendingPayouts();
                this.fetchApprovalDashboard();
            } else {
                this.showToast(response.message || 'Failed to cancel payout', 'error');
            }
        } catch (error) {
            console.error('Failed to cancel payout:', error);
            this.showToast('Failed to cancel payout', 'error');
        }
    }

    loadActivityLog() {
        this.showLoader('#activity-log-view');
        this.loadSchedulerRuns();
        this.fetchActivityLog();
        $('#refreshSchedulerRuns').off('click').on('click', () => this.loadSchedulerRuns());
    }

    async fetchActivityLog(page = 1) {
        try {
            const period = $('#activityLogPeriodFilter').val();
            let dateFrom, dateTo;

            if (period === 'custom') {
                dateFrom = $('#activityLogDateFrom').val();
                dateTo = $('#activityLogDateTo').val();
            } else {
                const daysMap = { '7d': 7, '30d': 30, '90d': 90, '1y': 365 };
                const days = daysMap[period] || 30;
                const now = new Date();
                dateFrom = new Date(now.getTime() - days * 86400000).toISOString().slice(0, 10);
                dateTo = now.toISOString().slice(0, 10);
            }

            const response = await $.ajax({
                url: approvalApiUrls.activityLog,
                method: 'GET',
                data: {
                    search: $('#activityLogSearch').val(),
                    action: $('#activityLogActionFilter').val(),
                    date_from: dateFrom,
                    date_to: dateTo,
                    page: page,
                    per_page: 25
                }
            });

            if (response.success) {
                this.renderActivityLog(response.data);
                this.renderActivityLogPagination(response.pagination);
                $('#activityLogCount').text(`${response.pagination.total} events`);
            }
        } catch (error) {
            console.error('Failed to fetch activity log:', error);
            $('#activityLogBody').html(`
                <tr><td colspan="5" class="text-center py-4 text-danger">Failed to load activity log</td></tr>
            `);
        } finally {
            this.hideLoader('#activity-log-view');
        }
    }

    renderActivityLog(logs) {
        const container = $('#activityLogBody');

        if (!logs || logs.length === 0) {
            container.html(`
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <i class="bi bi-journal display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No events found</h5>
                        <p class="text-muted">There are no activity log entries matching your criteria.</p>
                    </td>
                </tr>
            `);
            return;
        }

        const rows = logs.map(log => `
            <tr>
                <td><small class="text-muted">${log.created_at}</small></td>
                <td><span class="badge ${this.getStatusBadgeClass(log.action)}">${this.formatStatus(log.action)}</span></td>
                <td><strong class="text-primary">${log.payout_reference}</strong></td>
                <td><small>${log.user}</small></td>
                <td><small class="text-muted">${this.escapeHtml(log.notes) || '-'}</small></td>
            </tr>
        `).join('');

        container.html(rows);
    }

    renderActivityLogPagination(pagination) {
        const container = $('#activityLogPagination');

        if (!pagination || pagination.last_page <= 1) {
            container.empty();
            return;
        }

        let html = '';
        if (pagination.current_page > 1) {
            html += `<li class="page-item"><a class="page-link activity-log-page" href="#" data-page="${pagination.current_page - 1}">Previous</a></li>`;
        }
        for (let i = Math.max(1, pagination.current_page - 2); i <= Math.min(pagination.last_page, pagination.current_page + 2); i++) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                <a class="page-link activity-log-page" href="#" data-page="${i}">${i}</a>
            </li>`;
        }
        if (pagination.current_page < pagination.last_page) {
            html += `<li class="page-item"><a class="page-link activity-log-page" href="#" data-page="${pagination.current_page + 1}">Next</a></li>`;
        }

        container.html(html);
        container.off('click', '.activity-log-page').on('click', '.activity-log-page', (e) => {
            e.preventDefault();
            const page = $(e.target).data('page');
            if (page) this.fetchActivityLog(page);
        });
    }

    getDetailPageNumbers(current, last) {
        const pages = [];
        const delta = 2;
        const left = Math.max(2, current - delta);
        const right = Math.min(last - 1, current + delta);

        pages.push(1);
        if (left > 2) pages.push('...');
        for (let i = left; i <= right; i++) pages.push(i);
        if (right < last - 1) pages.push('...');
        if (last > 1) pages.push(last);

        return pages;
    }

    // ===== ADJUSTMENTS =====

    loadAdjustments() {
        this.showLoader('#adjustments-view');
        this.fetchAdjustmentSummary();
        this.fetchAdjustments();
        this.setupAdjustmentListeners();
    }
    setupAdjustmentListeners() {
        $('#detectAdjustmentsBtn').off('click').on('click', () => this.detectAdjustments());
        $('#refreshAdjustmentsBtn').off('click').on('click', () => this.loadAdjustments());
        $('#applyAdjustmentFilters').off('click').on('click', () => this.fetchAdjustments());
        $('#confirmVoidAdjustment').off('click').on('click', () => this.submitVoidAdjustment());
    }

    async fetchAdjustmentSummary() {
        try {
            const response = await $.ajax({
                url: adjustmentApiUrls.summary,
                method: 'GET'
            });

            if (response.success) {
                const d = response.data;
                $('#adj-pending-count').text(d.pending.count);
                $('#adj-pending-net').text(this.formatCurrency(d.pending.net.toFixed(2)));
                $('#adj-credit-total').text(this.formatCurrency(d.pending.credit_total.toFixed(2)));
                $('#adj-debit-total').text(this.formatCurrency(d.pending.debit_total.toFixed(2)));

                // Update badge
                if (d.pending.count > 0) {
                    $('#pending-adjustments-badge').text(d.pending.count).show();
                } else {
                    $('#pending-adjustments-badge').hide();
                }
            }
        } catch (error) {
            console.log('Could not fetch adjustment summary');
        }
    }

    async fetchAdjustments(page = 1) {
        try {
            const params = { page };
            const status = $('#adjustmentStatusFilter').val();
            const type = $('#adjustmentTypeFilter').val();
            const bookingId = $('#adjustmentBookingFilter').val();
            const payoutRef = $('#adjustmentPayoutRefFilter').val();

            if (status) params.status = status;
            if (type) params.type = type;
            if (bookingId) params.booking_id = bookingId;
            if (payoutRef) params.payout_reference = payoutRef;

            const response = await $.ajax({
                url: adjustmentApiUrls.index,
                method: 'GET',
                data: params
            });

            if (response.success) {
                this.renderAdjustmentTable(response.data);
            }
        } catch (error) {
            this.showToast('Failed to fetch adjustments.', 'error');
        } finally {
            this.hideLoader('#adjustments-view');
        }
    }

    renderAdjustmentTable(paginatedData) {
        const tbody = $('#adjustmentTableBody');
        const adjustments = paginatedData.data || [];
        const isAdmin = ( isAdminUser ) ? '/admin' : '';

        $('#adjustmentCount').text(`${paginatedData.total || 0} adjustments`);

        if (adjustments.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle d-block mb-2" style="font-size: 2rem;"></i>
                        No adjustments found
                    </td>
                </tr>
            `);
            $('#adjustmentPagination').html('');
            return;
        }

        let html = '';
        adjustments.forEach(adj => {
            const typeBadge = adj.type === 'credit'
                ? '<span class="badge bg-success">Credit</span>'
                : '<span class="badge bg-danger">Debit</span>';

            let statusBadge;
            switch (adj.status) {
                case 'pending':
                    statusBadge = '<span class="badge bg-warning text-dark">Pending</span>';
                    break;
                case 'applied':
                    statusBadge = '<span class="badge bg-primary">Applied</span>';
                    break;
                case 'voided':
                    statusBadge = '<span class="badge bg-secondary">Voided</span>';
                    break;
                default:
                    statusBadge = `<span class="badge bg-light text-dark">${adj.status}</span>`;
            }

            const detectedDate = adj.detected_at ? new Date(adj.detected_at).toLocaleDateString() : '-';
            const voidBtn = adj.status === 'pending'
                ? `<button class="btn btn-sm btn-outline-danger void-adj-btn" data-id="${adj.id}" title="Void"><i class="bi bi-x-circle"></i></button>`
                : '';

            html += `
                <tr>
                    <td class="fw-semibold">#${adj.id}</td>
                    <td>
                    <a href="${isAdmin}/tour-operator/booking/0${adj.booking_id.toString().trim()}?modal=0" target="_blank" class="text-primary">${adj.booking_id}</a>
                    </td>
                    <td>${typeBadge}</td>
                    <td>${this.formatCurrency(parseFloat(adj.gross_amount).toFixed(2))}</td>
                    <td>${this.formatCurrency(parseFloat(adj.platform_fee).toFixed(2))}</td>
                    <td class="fw-semibold">${this.formatCurrency(parseFloat(adj.net_amount).toFixed(2))}</td>
                    <td><span class="text-muted small">${this.escapeHtml(adj.payout_reference) || '-'}</span></td>
                    <td><span class="small">${this.escapeHtml(adj.reason) || '-'}</span></td>
                    <td>${statusBadge}</td>
                    <td class="small">${detectedDate}</td>
                    <td>${voidBtn}</td>
                </tr>
            `;
        });

        tbody.html(html);

        // Void button handlers
        tbody.find('.void-adj-btn').on('click', (e) => {
            const id = $(e.currentTarget).data('id');
            this.showVoidModal(id);
        });

        // Render pagination
        this.renderAdjustmentPagination(paginatedData);
    }

    renderAdjustmentPagination(paginatedData) {
        const pagination = $('#adjustmentPagination');
        if (paginatedData.last_page <= 1) {
            pagination.html('');
            return;
        }

        const pages = this.getDetailPageNumbers(paginatedData.current_page, paginatedData.last_page);
        let html = '';

        html += `<li class="page-item ${paginatedData.current_page === 1 ? 'disabled' : ''}">
            <a class="page-link adj-page" href="#" data-page="${paginatedData.current_page - 1}">Previous</a>
        </li>`;

        pages.forEach(page => {
            if (page === '...') {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            } else {
                html += `<li class="page-item ${page === paginatedData.current_page ? 'active' : ''}">
                    <a class="page-link adj-page" href="#" data-page="${page}">${page}</a>
                </li>`;
            }
        });

        html += `<li class="page-item ${paginatedData.current_page === paginatedData.last_page ? 'disabled' : ''}">
            <a class="page-link adj-page" href="#" data-page="${paginatedData.current_page + 1}">Next</a>
        </li>`;

        pagination.html(html);
        pagination.find('.adj-page').on('click', (e) => {
            e.preventDefault();
            const page = $(e.target).data('page');
            if (page) this.fetchAdjustments(page);
        });
    }

    async detectAdjustments() {
        const btn = $('#detectAdjustmentsBtn');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Scanning...');

        try {
            const response = await $.ajax({
                url: adjustmentApiUrls.detect,
                method: 'POST'
            });

            if (response.success) {
                this.showToast(response.message, 'success');
                this.loadAdjustments();
            } else {
                this.showToast(response.message || 'Detection failed.', 'error');
            }
        } catch (error) {
            const msg = error.responseJSON?.message || 'Failed to detect adjustments.';
            this.showToast(msg, 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-search me-2"></i>Detect Adjustments');
        }
    }

    showVoidModal(adjustmentId) {
        $('#voidAdjustmentId').val(adjustmentId);
        $('#voidAdjustmentReason').val('');
        const modal = new bootstrap.Modal(document.getElementById('voidAdjustmentModal'));
        modal.show();
    }

    async submitVoidAdjustment() {
        const id = $('#voidAdjustmentId').val();
        const reason = $('#voidAdjustmentReason').val();

        if (!reason || reason.length < 5) {
            this.showToast('Please provide a reason (at least 5 characters).', 'error');
            return;
        }

        const btn = $('#confirmVoidAdjustment');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Voiding...');

        try {
            const url = adjustmentApiUrls.void.replace(':id', id);
            const response = await $.ajax({
                url: url,
                method: 'POST',
                data: { reason }
            });

            if (response.success) {
                bootstrap.Modal.getInstance(document.getElementById('voidAdjustmentModal')).hide();
                this.showToast('Adjustment voided successfully.', 'success');
                this.loadAdjustments();
            } else {
                this.showToast(response.message || 'Failed to void adjustment.', 'error');
            }
        } catch (error) {
            const msg = error.responseJSON?.message || 'Failed to void adjustment.';
            this.showToast(msg, 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-x-circle me-1"></i> Void Adjustment');
        }
    }

    async submitInlineAdjustment(payoutId) {
        const bookingId = $('#inlineAdjBookingId').val();
        const type = $('#inlineAdjType').val();
        const netAmount = $('#inlineAdjNetAmount').val();
        const reason = $('#inlineAdjReason').val();

        if (!bookingId) {
            this.showToast('Please select a booking.', 'error');
            return;
        }
        if (!netAmount || parseFloat(netAmount) <= 0) {
            this.showToast('Please enter a valid net amount.', 'error');
            return;
        }
        if (!reason || reason.length < 5) {
            this.showToast('Please provide a reason (at least 5 characters).', 'error');
            return;
        }

        const btn = $('#submitInlineAdjustment');
        btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Saving...');

        try {
            const response = await $.ajax({
                url: adjustmentApiUrls.create,
                method: 'POST',
                data: {
                    payout_id: payoutId,
                    payout_source: this._currentDetailsSource === 'auto_payout' ? 'auto' : 'pending',
                    booking_id: bookingId,
                    type: type,
                    net_amount: netAmount,
                    reason: reason
                }
            });

            if (response.success) {
                this.showToast('Adjustment created successfully.', 'success');
                this.updateAdjustmentBadge();
                // Refresh the detail view to show the new adjustment
                if (this._currentDetailsSource === 'auto_payout') {
                    this.showReportPayoutDetails(this._currentDetailsPayoutId, 'auto_payout');
                } else {
                    this.showPayoutDetails(this._currentDetailsPayoutId);
                }
            } else {
                this.showToast(response.message || 'Failed to create adjustment.', 'error');
            }
        } catch (error) {
            const msg = error.responseJSON?.message || 'Failed to create adjustment.';
            this.showToast(msg, 'error');
        } finally {
            btn.prop('disabled', false).html('<i class="bi bi-check-lg me-1"></i>Save');
        }
    }

    async updateAdjustmentBadge() {
        try {
            const response = await $.ajax({
                url: adjustmentApiUrls.summary,
                method: 'GET'
            });

            if (response.success && response.data.pending.count > 0) {
                $('#pending-adjustments-badge').text(response.data.pending.count).show();
            } else {
                $('#pending-adjustments-badge').hide();
            }
        } catch (error) {
            // Silently fail
        }
    }
}

// Initialize the system when document is ready
$(document).ready(function () {
    window.payoutSystem = new AutoPayoutManagementSystem();
});