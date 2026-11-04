$(document).ready(function () {
    const currencySymbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';

    // Use the operator's timezone; fall back to browser guess if moment-timezone unavailable
    const dashboardTz = (typeof moment.tz === 'function' && typeof timezone !== 'undefined' && timezone)
        ? timezone
        : null;

    function nowTz() {
        return dashboardTz ? moment.tz(dashboardTz) : moment();
    }

    function parseDateTz(dateStr) {
        return dashboardTz ? moment.tz(dateStr, 'YYYY-MM-DD', dashboardTz) : moment(dateStr, 'YYYY-MM-DD');
    }

    let purchaseTypeChart = null;
    let netByDateChart = null;
    let netByPackageChart = null;
    let touristsBookingsChart = null;
    let stateTaxChart   = null;
    let avgTaxChart     = null;

    let dashboardState = {
        from: nowTz().format('YYYY-MM-DD'),
        to:   nowTz().format('YYYY-MM-DD'),
        tourPackageIds: [],
        paymentTypes: ['Cash', 'Card', 'Check/Wire', 'Invoiced'],
        thirdPartyPlatforms: ['TripAdvisor', 'Headout', 'FareHarbor'],
        requestFrom: ['BACKEND', 'FRONTEND', 'HEADOUT', 'TRIPADVISOR'],
        bookingSources: []
    };

    $('#dashboardRangeBtn').daterangepicker({
        startDate: nowTz(),
        endDate:   nowTz(),
        autoUpdateInput: false,
        opens: 'right',
        ranges: {
            'Today':      [nowTz(), nowTz()],
            'This Week':  [nowTz().startOf('isoweek'), nowTz().endOf('isoweek')],
            'This Month': [nowTz().startOf('month'),   nowTz().endOf('month')],
            'Last Month': [
                nowTz().subtract(1, 'month').startOf('month'),
                nowTz().subtract(1, 'month').endOf('month')
            ],
            'This Year':  [nowTz().startOf('year'),    nowTz().endOf('year')],
            'Last Year':  [
                nowTz().subtract(1, 'year').startOf('year'),
                nowTz().subtract(1, 'year').endOf('year')
            ]
        }
    }, function (start, end, label) {

        // LABEL (Today / This Month / Last Year)
        $('#dashboardRangeLabel').text(label);

        // STORE DATES
        dashboardState.from = start.format('YYYY-MM-DD');
        dashboardState.to   = end.format('YYYY-MM-DD');

        loadDashboardMetrics();
    });

    // ── Multi-select tour package dropdown ────────────────────────────
    $(document).on('click', '#tourPackageBtn', function (e) {
        e.stopPropagation();
        $('#tourPackageDropdown').toggleClass('hidden');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#tourPackageDropdownWrap').length) {
            $('#tourPackageDropdown').addClass('hidden');
        }
    });

    function updateTourPackageState() {
        const selected = [];
        const names = [];

        $('.tour-pkg-cb:checked').each(function () {
            selected.push($(this).val());
            names.push($(this).data('name'));
        });

        dashboardState.tourPackageIds = selected;

        if (selected.length === 0) {
            $('#tourPackageBtnLabel').text('All Packages');
        } else if (selected.length === 1) {
            $('#tourPackageBtnLabel').text(names[0]);
        } else {
            $('#tourPackageBtnLabel').text(selected.length + ' packages selected');
        }

        loadDashboardMetrics();
    }

    $(document).on('change', '.tour-pkg-cb', function () {
        updateTourPackageState();
    });

    $(document).on('click', '#tourPkgSelectAll', function () {
        $('.tour-pkg-cb').prop('checked', true);
        updateTourPackageState();
    });

    $(document).on('click', '#tourPkgClear', function () {
        $('.tour-pkg-cb').prop('checked', false);
        updateTourPackageState();
    });

    // ── Multi-select request from dropdown ───────────────────────────
    $(document).on('click', '#requestFromBtn', function (e) {
        e.stopPropagation();
        $('#requestFromDropdown').toggleClass('hidden');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#requestFromDropdownWrap').length) {
            $('#requestFromDropdown').addClass('hidden');
        }
    });

    function updateRequestFromState() {
        const selected = [];
        $('.request-from-cb:checked').each(function () {
            selected.push($(this).val());
        });

        const total = $('.request-from-cb').length;
        dashboardState.requestFrom = selected;

        if (selected.length === 0) {
            $('#requestFromBtnLabel').text('No Sources');
        } else if (selected.length === total) {
            $('#requestFromBtnLabel').text('All Backend Sources');
        } else {
            const labels = { BACKEND: 'Backend', FRONTEND: 'Frontend', HEADOUT: 'Headout', TRIPADVISOR: 'TripAdvisor' };
            $('#requestFromBtnLabel').text(selected.map(v => labels[v] || v).join(', '));
        }

        loadDashboardMetrics();
    }

    $(document).on('change', '.request-from-cb', function () {
        updateRequestFromState();
    });

    $(document).on('click', '#requestFromSelectAll', function () {
        $('.request-from-cb').prop('checked', true);
        updateRequestFromState();
    });

    $(document).on('click', '#requestFromClear', function () {
        $('.request-from-cb').prop('checked', false);
        updateRequestFromState();
    });

    // ── Multi-select booking source dropdown ─────────────────────────
    $(document).on('click', '#bookingSourceBtn', function (e) {
        e.stopPropagation();
        $('#bookingSourceDropdown').toggleClass('hidden');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#bookingSourceDropdownWrap').length) {
            $('#bookingSourceDropdown').addClass('hidden');
        }
    });

    function updateBookingSourceState() {
        const selected = [];
        $('.booking-source-cb:checked').each(function () {
            selected.push($(this).val());
        });

        const total = $('.booking-source-cb').length;
        dashboardState.bookingSources = selected;

        if (selected.length === 0 || selected.length === total) {
            $('#bookingSourceBtnLabel').text('All Booking Sources');
        } else {
            const labels = { phone_call: 'Phone Call', walk_in: 'Walk In', online: 'Online', email: 'Email', other: 'Other' };
            $('#bookingSourceBtnLabel').text(selected.map(v => labels[v] || v).join(', '));
        }

        loadDashboardMetrics();
    }

    $(document).on('change', '.booking-source-cb', updateBookingSourceState);

    $(document).on('click', '#bookingSourceSelectAll', function () {
        $('.booking-source-cb').prop('checked', true);
        updateBookingSourceState();
    });

    $(document).on('click', '#bookingSourceClear', function () {
        $('.booking-source-cb').prop('checked', false);
        updateBookingSourceState();
    });

    // ── Multi-select payment type dropdown ────────────────────────────
    $(document).on('click', '#paymentTypeBtn', function (e) {
        e.stopPropagation();
        $('#paymentTypeDropdown').toggleClass('hidden');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#paymentTypeDropdownWrap').length) {
            $('#paymentTypeDropdown').addClass('hidden');
        }
    });

    function updatePaymentTypeState() {
        const selected = [];
        $('.payment-type-cb:checked').each(function () {
            selected.push($(this).val());
        });

        dashboardState.paymentTypes = selected;

        const total = $('.payment-type-cb').length;
        if (selected.length === 0) {
            $('#paymentTypeBtnLabel').text('Payment Types');
        } else if (selected.length === total) {
            $('#paymentTypeBtnLabel').text('All Payment Types');
        } else {
            $('#paymentTypeBtnLabel').text(selected.join(', '));
        }

        loadDashboardMetrics();
    }

    $(document).on('change', '.payment-type-cb', function () {
        updatePaymentTypeState();
    });

    $(document).on('click', '#paymentTypeSelectAll', function () {
        $('.payment-type-cb').prop('checked', true);
        updatePaymentTypeState();
    });

    $(document).on('click', '#paymentTypeClear', function () {
        $('.payment-type-cb').prop('checked', false);
        updatePaymentTypeState();
    });

    // ── Multi-select third party platform dropdown ────────────────────
    $(document).on('click', '#thirdPartyBtn', function (e) {
        e.stopPropagation();
        $('#thirdPartyDropdown').toggleClass('hidden');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#thirdPartyDropdownWrap').length) {
            $('#thirdPartyDropdown').addClass('hidden');
        }
    });

    function updateThirdPartyState() {
        const selected = [];
        $('.third-party-cb:checked').each(function () {
            selected.push($(this).val());
        });

        dashboardState.thirdPartyPlatforms = selected;

        const total = $('.third-party-cb').length;
        if (selected.length === 0) {
            $('#thirdPartyBtnLabel').text('Platforms');
        } else if (selected.length === total) {
            $('#thirdPartyBtnLabel').text('All Platforms');
        } else {
            $('#thirdPartyBtnLabel').text(selected.join(', '));
        }

        loadDashboardMetrics();
    }

    $(document).on('change', '.third-party-cb', function () {
        updateThirdPartyState();
    });

    $(document).on('click', '#thirdPartySelectAll', function () {
        $('.third-party-cb').prop('checked', true);
        updateThirdPartyState();
    });

    $(document).on('click', '#thirdPartyClear', function () {
        $('.third-party-cb').prop('checked', false);
        updateThirdPartyState();
    });

    function renderComparison($el, value) {
        if (value === null || value === undefined) {
            $el.addClass('hidden');
            return;
        }

        const isPositive = value >= 0;

        $el
            .removeClass('hidden text-red-500 text-emerald-600')
            .addClass(isPositive ? 'text-green-600' : 'text-red-500')
            .html(`
                ${isPositive ? '↗' : '↘'} ${Math.abs(value)}%
                <span class="text-gray-400">vs last month</span>
            `);
    }

    function showLoader() { $('#loading-container').removeClass('hidden').css('display', ''); }
    function hideLoader() { $('#loading-container').addClass('hidden').css('display', ''); }

    function loadDashboardMetrics() {
        $.ajax({
            url: dashboardMetricsUrl,
            type: 'GET',
            data: $.param({ from: dashboardState.from, to: dashboardState.to, tourPackageIds: dashboardState.tourPackageIds, paymentTypes: dashboardState.paymentTypes, thirdPartyPlatforms: dashboardState.thirdPartyPlatforms, requestFrom: dashboardState.requestFrom, bookingSources: dashboardState.bookingSources }),
            traditional: false,
            beforeSend() {
                showLoader();
                $('#totalGross,#totalNet,#totalRefund,#totalDiscount,#totalTourists,#totalBookings,#totalTax,#totalPermitFee')
                    .addClass('opacity-50');
            },
            success(res) {
                if (!res.success) return;

                // KPI values
                $('#totalGross').text(currencySymbol + res.metrics.gross);
                $('#totalNet').text(currencySymbol + res.metrics.net);
                $('#totalRefund').text(currencySymbol + res.metrics.refund);
                $('#totalDiscount').text(currencySymbol + res.metrics.discount);
                $('#totalTourists').text(res.metrics.tourists);
                $('#totalBookings').text(res.metrics.bookings);
                $('#totalTax').text(currencySymbol + res.metrics.tax);
                $('#totalPermitFee').text(currencySymbol + res.metrics.permit_fee);
                const purchaseData = res.purchase_date;

                let bookedAmount = 0;
                let refundedAmount = 0;

                Object.values(purchaseData).forEach(item => {
                    bookedAmount += Number(item.net || 0);
                    refundedAmount += Number(item.refund || 0); // ✅ use correct field
                });

                renderPurchaseTypeChart(bookedAmount, refundedAmount);

                renderNetByDateChart(
                    res.net_by_date.labels,
                    res.net_by_date.booked,
                    res.net_by_date.refund
                );

                if (res.net_by_package) {
                    renderNetByPackageChart(
                        res.net_by_package.labels,
                        res.net_by_package.net,
                        res.net_by_package.bookings
                    );
                }

                if (res.tourists_and_bookings) {
                    renderTouristsAndBookingsChart(res.tourists_and_bookings);
                }

                renderPeakBookingChart(res.peak_times);
                renderMostProfitableDays(res.profitable_days);
                renderWeekendWeekday(res.weekend_vs_weekday);
                renderNetPaymentDate(res.payment_date);
               
                renderStateTaxByDate(res.state_tax_by_date);
                renderAvgTaxPerPerson(res.avg_tax_per_person);
                hasAffiliates = !!res.isAffiliates;
                if (hasAffiliates) {
                    $('#affiliateSummarySection').show();
                    $('#packagePerformanceSection').removeClass('lg:col-span-2');
                    if (res.affiliate_summary) {
                        renderAffiliateSummary(res.affiliate_summary, res.is_month);
                    }
                } else {
                    $('#affiliateSummarySection').hide();
                    $('#packagePerformanceSection').addClass('lg:col-span-2');
                }

                if (res.package_performance) {
                    renderPackagePerformance(res.package_performance, 'net');
                }         
                // Month comparison (ONLY when month selected)
                if (res.is_month && res.comparison) {
                    renderComparison($('#grossComparison'), res.comparison.gross);
                    renderComparison($('#netComparison'), res.comparison.net);
                    renderComparison($('#refundComparison'), res.comparison.refund);
                    renderComparison($('#discountComparison'), res.comparison.discount);
                    renderComparison($('#touristsComparison'), res.comparison.tourists);
                    renderComparison($('#bookingsComparison'), res.comparison.bookings);
                    renderComparison($('#taxComparison'), res.comparison.tax);
                    renderComparison($('#permitFeeComparison'), res.comparison.permit_fee);
                } else {
                    $('#grossComparison,#netComparison,#refundComparison,#discountComparison,#touristsComparison,#bookingsComparison,#taxComparison,#permitFeeComparison')
                        .addClass('hidden');
                }

                $('#totalGross,#totalNet,#totalRefund,#totalDiscount,#totalTourists,#totalBookings,#totalTax,#totalPermitFee')
                    .removeClass('opacity-50');
            },
            complete() { hideLoader(); }
        });
    }

    function renderNetByDateChart(labels, bookedData, refundData) {

        const ctx = document.getElementById('netByDateChart').getContext('2d');

        if (netByDateChart) {
            netByDateChart.destroy();
        }

        var netByDatePlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font = 'bold 11px sans-serif';
                c.textAlign = 'center';
                var colors = ['#0d6b68', '#991b1b'];
                var offsets = [-14, 14];
                chart.data.datasets.forEach(function (dataset, dsIndex) {
                    var meta = chart.getDatasetMeta(dsIndex);
                    c.fillStyle = colors[dsIndex];
                    meta.data.forEach(function (point, i) {
                        var val = dataset.data[i];
                        if (val == null || parseFloat(val) === 0) return;
                        var text = currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        c.fillText(text, point._model.x, point._model.y + offsets[dsIndex]);
                    });
                });
                c.restore();
            }
        }];

        netByDateChart = new Chart(ctx, {
            type: 'line',
            plugins: netByDatePlugin,
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Booked',
                        data: bookedData,
                        backgroundColor: 'rgba(95, 189, 184, 0.45)',
                        borderColor: '#5FBDB8',
                        pointBackgroundColor: '#5FBDB8',
                        pointBorderColor: '#ffffff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        fill: true,
                        lineTension: 0.35
                    },
                    {
                        label: 'Refund',
                        data: refundData,
                        backgroundColor: 'rgba(217, 56, 30, 0.35)',
                        borderColor: '#D9381E',
                        pointBackgroundColor: '#D9381E',
                        pointBorderColor: '#ffffff',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        borderWidth: 2,
                        fill: true,
                        lineTension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        fontSize: 13
                    }
                },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#ffffff',
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 10,
                    displayColors: true,
                    xPadding: 14,
                    yPadding: 10,
                    callbacks: {
                        title: function (tooltipItem, data) {
                            return tooltipItem[0].label;
                        },
                        label: function (tooltipItem, data) {
                            const label = data.datasets[tooltipItem.datasetIndex].label;
                            const value = tooltipItem.yLabel;

                            return `${label}: ${currencySymbol}${value.toLocaleString()}`;
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            color: '#E5E7EB',
                            borderDash: [4, 4],
                            drawBorder: false
                        },
                        ticks: {
                            fontColor: '#6B7280'
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            color: '#E5E7EB',
                            borderDash: [4, 4],
                            drawBorder: false
                        },
                        ticks: {
                            beginAtZero: true,
                            fontColor: '#6B7280',
                            callback: function (value) {
                                if (value >= 1000) return currencySymbol + (value / 1000) + 'k';
                                return currencySymbol + value;
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderPurchaseTypeChart(bookedAmount, refundedAmount) {
        const total = bookedAmount + refundedAmount;

        const bookedPercent = total > 0
            ? ((bookedAmount / total) * 100).toFixed(1)
            : 0;

        const ctx = document.getElementById('purchaseTypeChart').getContext('2d');

        if (purchaseTypeChart) {
            purchaseTypeChart.destroy();
        }

        var purchaseTypePlugin = [{
            afterDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c       = chart.ctx;
                var meta    = chart.getDatasetMeta(0);
                var dataset = chart.data.datasets[0];
                var tot     = (dataset.data[0] || 0) + (dataset.data[1] || 0);
                if (!meta.data.length) return;
                var cx = meta.data[0]._model.x;
                var cy = meta.data[0]._model.y;
                c.save();
                c.textAlign    = 'center';
                c.textBaseline = 'middle';
                meta.data.forEach(function (arc, i) {
                    var val      = dataset.data[i];
                    if (!val) return;
                    var pct      = ((val / tot) * 100).toFixed(1);
                    var label    = chart.data.labels[i];
                    var midAngle = (arc._model.startAngle + arc._model.endAngle) / 2;
                    var segSpan  = arc._model.endAngle - arc._model.startAngle;
                    var r        = (arc._model.outerRadius + arc._model.innerRadius) / 2;
                    var x        = cx + Math.cos(midAngle) * r;
                    var y        = cy + Math.sin(midAngle) * r;
                    // Only draw inside-arc label if the segment is wide enough
                    if (segSpan > 0.35) {
                        c.font      = 'bold 13px sans-serif';
                        c.fillStyle = '#ffffff';
                        c.fillText(label, x, y - 10);
                        c.font      = '12px sans-serif';
                        c.fillText(pct + '%  (' + currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')', x, y + 10);
                    }
                });
                // Always draw a summary in the doughnut hole
                c.font      = 'bold 15px sans-serif';
                c.fillStyle = '#111827';
                c.fillText('Total', cx, cy - 30);
                c.font      = '13px sans-serif';
                c.fillStyle = '#374151';
                c.fillText(currencySymbol + parseFloat(tot).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), cx, cy - 8);
                dataset.data.forEach(function (val, i) {
                    var segColors = ['#5FBDB8', '#D9381E'];
                    var pct = tot > 0 ? ((val / tot) * 100).toFixed(1) : '0.0';
                    c.font      = '12px sans-serif';
                    c.fillStyle = segColors[i];
                    c.fillText(chart.data.labels[i] + ': ' + pct + '%', cx, cy + 14 + i * 18);
                });
                c.restore();
            }
        }];

        purchaseTypeChart = new Chart(ctx, {
            type: 'doughnut',
            plugins: purchaseTypePlugin,
            data: {
                labels: ['Booked', 'Cancelled / Refund'],
                datasets: [{
                    data: [bookedAmount, refundedAmount],
                    backgroundColor: ['#5FBDB8', '#D9381E'],
                    borderWidth: 4,
                    borderColor: '#ffffff',
                    hoverOffset: 10
                }]
            },
            options: {
                cutoutPercentage: 65,
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    display: false
                },
                tooltips: {
                    enabled: true,
                    backgroundColor: '#ffffff',
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 10,
                    displayColors: false,
                    xPadding: 14,
                    yPadding: 10,
                    callbacks: {
                        title: function (tooltipItem, data) {
                            return data.labels[tooltipItem[0].index];
                        },
                        label: function (tooltipItem, data) {
                            const value = data.datasets[0].data[tooltipItem.index];
                            const percent = total > 0
                                ? ((value / total) * 100).toFixed(1)
                                : 0;

                            return `${percent}% (${currencySymbol}${value.toLocaleString()})`;
                        }
                    }
                }
            }
        });
    }

    function renderNetByPackageChart(labels, netData, bookingsData) {

        const ctx = document.getElementById('netByPackageChart').getContext('2d');

        if (netByPackageChart) {
            netByPackageChart.destroy();
        }

        var netByPackagePlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.textBaseline = 'middle';
                var area = chart.chartArea;
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var net    = netData[i];
                    var label  = 'Net: ' + currencySymbol + parseFloat(net).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ',  Bookings: ' + bookingsData[i];
                    var barEnd  = bar._model.x;
                    var barWidth = barEnd - bar._model.base;
                    var y       = bar._model.y;
                    var pad     = 6;
                    var tw      = c.measureText(label).width;
                    if (area.right - barEnd > tw + pad * 2) {
                        c.fillStyle = '#374151';
                        c.textAlign = 'left';
                        c.fillText(label, barEnd + pad, y);
                    } else if (barWidth > tw + pad * 2) {
                        c.fillStyle = '#ffffff';
                        c.textAlign = 'right';
                        c.fillText(label, barEnd - pad, y);
                    }
                });
                c.restore();
            }
        }];

        netByPackageChart = new Chart(ctx, {
            type: 'horizontalBar',
            plugins: netByPackagePlugin,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Net',
                    data: netData,
                    backgroundColor: '#5FBDB8',
                    barThickness: 22,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                tooltips: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#ffffff',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    cornerRadius: 10,
                    xPadding: 12,
                    yPadding: 8,
                    displayColors: false,
                    callbacks: {
                        title: function (items) {
                            return items[0].yLabel;
                        },
                        label: function (item) {
                            const idx = item.index;
                            return [
                                `Net: ${currencySymbol}${netData[idx].toLocaleString()}`,
                                `Bookings: ${bookingsData[idx].toLocaleString()}`
                            ];
                        }
                    }
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            borderDash: [4, 4],
                            color: '#E5E7EB'
                        },
                        ticks: {
                            beginAtZero: true,
                            callback: function (value) {
                                if (value >= 1000000) return value / 1000000 + 'M';
                                if (value >= 1000) return value / 1000 + 'K';
                                return value;
                            }
                        }
                    }],
                    yAxes: [{
                        gridLines: { display: false },
                        ticks: {
                            fontSize: 12
                        }
                    }]
                }
            }
        });
    }

    function renderPeakBookingChart(data) {

        let labels = [];
        let counts = [];
        let dates  = [];

        data.forEach(item => {
            labels.push(item.booking_time);
            counts.push(item.booking_count);
            dates.push(item.booking_date);
        });

        const ctx = document.getElementById("peakBookingChart").getContext("2d");

        if(window.peakChart) {
            window.peakChart.destroy();
        }

        var peakBookingPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font      = 'bold 11px sans-serif';
                c.fillStyle = '#0ea5a4';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                chart.getDatasetMeta(0).data.forEach(function (point, i) {
                    var val = counts[i];
                    if (val == null) return;
                    c.fillText(val, point._model.x, point._model.y - 6);
                });
                c.restore();
            }
        }];

        window.peakChart = new Chart(ctx, {
            type: 'line',
            plugins: peakBookingPlugin,
            data: {
                labels: labels,
                datasets: [{
                    label: 'Bookings',
                    data: counts,
                    fill: true,
                    borderColor: '#0ea5a4',
                    backgroundColor: 'rgba(14,165,164,0.4)',
                    lineTension: 0.4,
                    pointRadius: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                legend: {
                    display: false
                },
                tooltips: {
                    callbacks: {
                        title: function(tooltipItems) {
                            const idx = tooltipItems[0].index;
                            return dates[idx] + ' ' + labels[idx];
                        }
                    }
                },
                scales: {
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        });
    }

    function renderMostProfitableDays(data) {

        let labels = [];
        let values = [];

        data.forEach(item => {
            labels.push(item.day_name);
            values.push(parseFloat(item.net_total));
        });

        const ctx = document.getElementById('profitableDaysChart').getContext('2d');

        if (window.profitableChart) {
            window.profitableChart.destroy();
        }

        var profitableDaysPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.fillStyle    = '#92400e';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var val = values[i];
                    if (!val) return;
                    var text = currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    c.fillText(text, bar._model.x, bar._model.y - 4);
                });
                c.restore();
            }
        }];

        window.profitableChart = new Chart(ctx, {
            type: 'bar',
            plugins: profitableDaysPlugin,
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: '#E89A42'
                }]
            },
            options: {
                maintainAspectRatio: false,
                responsive: true,
                legend: { display: false },

                tooltips: {
                    callbacks: {

                        title: function(tooltipItems) {
                            return labels[tooltipItems[0].index];
                        },

                        // 🔹 Amount
                        label: function(tooltipItem, data) {

                            const value = data.datasets[0].data[tooltipItem.index];

                            return currencySymbol + parseFloat(value).toLocaleString();
                        }
                    }
                },

                scales:{
                    yAxes:[{
                        ticks:{
                            beginAtZero:true,
                            callback: function(value){
                                return currencySymbol + parseFloat(value).toLocaleString();
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderWeekendWeekday(data){

        let labels = [];
        let weekend = [];
        let weekday = [];
        let weekRanges = [];

        data.forEach(item => {

            labels.push('Week ' + item.week_number);

            weekend.push(parseFloat(item.weekend_total));
            weekday.push(parseFloat(item.weekday_total));

            weekRanges.push({
                start: item.week_start,
                end: item.week_end
            });
        });

        const ctx = document.getElementById('weekendWeekdayChart').getContext('2d');

        if(window.weekChart){
            window.weekChart.destroy();
        }

        var weekendWeekdayPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 10px sans-serif';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                var dsColors = ['#1e3a5f', '#0d6b68'];
                chart.data.datasets.forEach(function (dataset, dsIndex) {
                    c.fillStyle = dsColors[dsIndex];
                    chart.getDatasetMeta(dsIndex).data.forEach(function (bar, i) {
                        var val = dataset.data[i];
                        if (!val) return;
                        var text = currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        c.fillText(text, bar._model.x, bar._model.y - 4);
                    });
                });
                c.restore();
            }
        }];

        window.weekChart = new Chart(ctx,{
            type:'bar',
            plugins: weekendWeekdayPlugin,
            data:{
                labels: labels,
                datasets:[
                    {
                        label:'Weekend',
                        data: weekend,
                        backgroundColor:'#2E4F6E'
                    },
                    {
                        label:'Weekday',
                        data: weekday,
                        backgroundColor:'#5FBDB8'
                    }
                ]
            },
            options:{
                maintainAspectRatio:false,
                responsive:true,

                legend:{
                    display:true,
                    position:'bottom'
                },

                tooltips:{
                    mode:'index',
                    intersect:false,

                    callbacks:{

                        title:function(tooltipItems){

                            const index = tooltipItems[0].index;
                            const range = weekRanges[index];

                            const start = parseDateTz(range.start).format('MMM D');
                            const end   = parseDateTz(range.end).format('D');

                            return labels[index] + ' (' + start + '–' + end + ')';
                        },

                        label:function(tooltipItem, data){

                            const datasetLabel = data.datasets[tooltipItem.datasetIndex].label;
                            const value = tooltipItem.yLabel;

                            return datasetLabel + ': ' + currencySymbol + (value/1000).toFixed(2) + 'K';
                        }
                    }
                },

                scales:{
                    yAxes:[{
                        ticks:{
                            beginAtZero:true,
                            callback: function(value){
                                return currencySymbol + (value / 1000).toFixed(2) + 'K';
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderNetPaymentDate(data){

        const ctx = document.getElementById('netPaymentDateChart').getContext('2d');

        if(window.paymentChart){
            window.paymentChart.destroy();
        }

        var paymentValues = data.values;
        var netPaymentPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.fillStyle    = '#355C7D';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                chart.getDatasetMeta(0).data.forEach(function (point, i) {
                    var val = paymentValues[i];
                    if (val == null) return;
                    var text = currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    c.fillText(text, point._model.x, point._model.y - 8);
                });
                c.restore();
            }
        }];

        window.paymentChart = new Chart(ctx,{
            type:'line',
            plugins: netPaymentPlugin,
            data:{
                labels: data.labels,
                datasets:[{
                    label: 'Net Amount',
                    data: data.values,
                    fill:false,
                    borderColor:'#355C7D',
                    tension:0.4,
                    pointRadius:5
                }]
            },
            options:{
                maintainAspectRatio:false,
                legend: { display: false },

                // ✅ TOOLTIP (v2 syntax)
                tooltips: {
                    callbacks: {
                        label: function(tooltipItem) {
                            return currencySymbol + tooltipItem.yLabel;
                        }
                    }
                },

                scales:{
                    yAxes:[{
                        ticks:{
                            beginAtZero:true,
                            callback: function(value){
                                return currencySymbol + value;
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderTouristsAndBookingsChart(data) {

        const ctx = document.getElementById('touristsBookingsChart').getContext('2d');

        if (touristsBookingsChart) {
            touristsBookingsChart.destroy();
        }

        var touristsBookingsPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 10px sans-serif';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                var dsColors = ['#c0392b', '#0d6b68'];
                chart.data.datasets.forEach(function (dataset, dsIndex) {
                    c.fillStyle = dsColors[dsIndex];
                    chart.getDatasetMeta(dsIndex).data.forEach(function (bar, i) {
                        var val = dataset.data[i];
                        if (!val) return;
                        c.fillText(val.toLocaleString(), bar._model.x, bar._model.y - 4);
                    });
                });
                c.restore();
            }
        }];

        touristsBookingsChart = new Chart(ctx, {
            type: 'bar',
            plugins: touristsBookingsPlugin,
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Tourists',
                        data: data.tourists,
                        backgroundColor: '#E05C3A',
                        barThickness: 18,
                        order: 1
                    },
                    {
                        label: 'Bookings',
                        data: data.bookings,
                        backgroundColor: '#5FBDB8',
                        barThickness: 18,
                        order: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,

                legend: {
                    display: false  // using custom HTML legend in blade
                },

                tooltips: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: '#ffffff',
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 10,
                    displayColors: false,
                    xPadding: 14,
                    yPadding: 10,
                    callbacks: {
                        title: function (tooltipItems) {
                            return tooltipItems[0].label;
                        },
                        label: function (tooltipItem, chartData) {
                            const label = chartData.datasets[tooltipItem.datasetIndex].label;
                            const val   = tooltipItem.yLabel;
                            return label + ': ' + val.toLocaleString();
                        },
                        labelTextColor: function (tooltipItem) {
                            return tooltipItem.datasetIndex === 0 ? '#E05C3A' : '#5FBDB8';
                        }
                    }
                },

                scales: {
                    xAxes: [{
                        gridLines: {
                            color: '#E5E7EB',
                            borderDash: [4, 4],
                            drawBorder: false
                        },
                        ticks: {
                            fontColor: '#6B7280',
                            fontSize: 12,
                            // Avoid label clutter on large ranges
                            maxTicksLimit: 31,
                            autoSkip: true
                        }
                    }],
                    yAxes: [{
                        gridLines: {
                            color: '#E5E7EB',
                            borderDash: [4, 4],
                            drawBorder: false
                        },
                        ticks: {
                            beginAtZero: true,
                            fontColor: '#6B7280',
                            callback: function (value) {
                                if (value >= 1000) return (value / 1000).toFixed(1) + 'k';
                                return value;
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderStateTaxByDate(chartData) {
        const ctx = document.getElementById('stateTaxByDateChart').getContext('2d');

        if (stateTaxChart) {
            stateTaxChart.destroy();
        }

        var stateTaxValues = chartData.values;
        var stateTaxPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.fillStyle    = '#1e3a5f';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                chart.getDatasetMeta(0).data.forEach(function (point, i) {
                    var val = stateTaxValues[i];
                    if (val == null) return;
                    var text = currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    c.fillText(text, point._model.x, point._model.y - 8);
                });
                c.restore();
            }
        }];

        stateTaxChart = new Chart(ctx, {
            type: 'line',
            plugins: stateTaxPlugin,
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'State Tax',
                    data: chartData.values,
                    borderColor: '#1e3a5f',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointBackgroundColor: '#1e3a5f',
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    tension: 0.3,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },

                // ✅ v2 tooltip syntax
                tooltips: {
                    displayColors: false,
                    backgroundColor: '#fff',
                    titleFontColor: '#1a1a1a',
                    bodyFontColor: '#3b82f6',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    xPadding: 12,
                    yPadding: 12,
                    titleFontSize: 14,
                    bodyFontSize: 13,
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        title: function(tooltipItems) {
                            return tooltipItems[0].xLabel;
                        },
                        label: function(tooltipItem) {
                            return 'St.Tax: ' + currencySymbol + Number(tooltipItem.yLabel).toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                },

                // ✅ v2 scale syntax
                scales: {
                    xAxes: [{
                        gridLines: { display: false },
                        ticks: {
                            fontColor: '#6b7280',
                            fontSize: 11,
                        }
                    }],
                    yAxes: [{
                        gridLines: { color: '#f0f0f0', drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            fontColor: '#6b7280',
                            fontSize: 11,
                            callback: function(value) {
                                return currencySymbol + Number(value).toLocaleString('en-US', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderAvgTaxPerPerson(chartData) {
        const ctx = document.getElementById('avgTaxPerPersonChart').getContext('2d');

        if (avgTaxChart) {
            avgTaxChart.destroy();
        }

        var avgTaxValues = chartData.values;
        var avgTaxPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c = chart.ctx;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.fillStyle    = '#b45309';
                c.textAlign    = 'center';
                c.textBaseline = 'bottom';
                var topPad = 16; // minimum px from chart top before flipping inside
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var val = avgTaxValues[i];
                    if (!val) return;
                    var text = 'Avg. Tax: ' + currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    if (bar._model.y - topPad < chart.chartArea.top) {
                        // Bar reaches the top — draw inside the bar
                        c.fillStyle    = '#ffffff';
                        c.textBaseline = 'top';
                        c.fillText(text, bar._model.x, bar._model.y + 5);
                        c.fillStyle    = '#b45309';
                        c.textBaseline = 'bottom';
                    } else {
                        c.fillText(text, bar._model.x, bar._model.y - 4);
                    }
                });
                c.restore();
            }
        }];

        avgTaxChart = new Chart(ctx, {
            type: 'bar',
            plugins: avgTaxPlugin,
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Avg. Tax',
                    data: chartData.values,
                    backgroundColor: '#f59e0b',
                    borderRadius: 4,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },

                // ✅ v2 tooltip syntax
                tooltips: {
                    displayColors: false,
                    backgroundColor: '#fff',
                    titleFontColor: '#1a1a1a',
                    bodyFontColor: '#f59e0b',
                    borderColor: '#e5e7eb',
                    borderWidth: 1,
                    xPadding: 12,
                    yPadding: 12,
                    titleFontSize: 14,
                    bodyFontSize: 13,
                    callbacks: {
                        title: function(tooltipItems) {
                            return tooltipItems[0].xLabel;
                        },
                        label: function(tooltipItem) {
                            return 'Avg. Tax: ' + currencySymbol + Number(tooltipItem.yLabel).toFixed(2);
                        }
                    }
                },

                // ✅ v2 scale syntax
                scales: {
                    xAxes: [{
                        gridLines: { display: false },
                        ticks: {
                            fontColor: '#6b7280',
                            fontSize: 11,
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function(value) {
                                if (this.chart && this.chart.canvas.id === 'chartZoomCanvas') {
                                    return value;
                                }
                                return value.length > 14 ? value.substring(0, 14) + '…' : value;
                            }
                        }
                    }],
                    yAxes: [{
                        gridLines: { color: '#f0f0f0', drawBorder: false },
                        ticks: {
                            beginAtZero: true,
                            fontColor: '#6b7280',
                            fontSize: 11,
                            callback: function(value) {
                                return currencySymbol + value;
                            }
                        }
                    }]
                }
            }
        });
    }

    function renderAffiliateSummary(data, isMonth) {

        const $tbody      = $('#affiliateTableBody');
        const $changeCols = $('.change-col');

        // Show / hide Change % column
        if (isMonth) {
            $changeCols.removeClass('hidden');
        } else {
            $changeCols.addClass('hidden');
        }

        // Empty state
        if (!data || data.length === 0) {
            $tbody.html(`
                <tr>
                    <td colspan="6" class="py-8 text-center text-gray-300 text-sm">
                        No affiliate data found for this period.
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';

        data.forEach(function (row) {

            // Status badge — matches Image 2 teal outlined style
            const isActive    = row.status === 'active';
            const badgeStyle  = isActive
                ? 'border: 1px solid #00b1ff; color: #00b1ff; background: transparent;'
                : 'border: 1px solid #d1d5db; color: #9ca3af; background: transparent;';
            const badgeClass  = 'rounded-full px-3 py-0.5 text-xs';

            // Change % cell — only rendered when month is selected
            let changeHtml = '';
            if (isMonth) {
                if (row.change_percent === null || row.change_percent === undefined) {
                    changeHtml = `<td class="py-4 text-gray-300 change-col text-sm">—</td>`;
                } else {
                    const isPos  = row.change_percent >= 0;
                    const color  = isPos ? '#00b1ff' : '#ef4444';
                    const sign   = isPos ? '+' : '';
                    changeHtml   = `
                        <td class="py-4 font-medium change-col text-sm" style="color:${color}">
                            ${sign}${row.change_percent}%
                        </td>`;
                }
            } else {
                changeHtml = `<td class="py-4 change-col hidden"></td>`;
            }

            html += `
                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors affiliate-row"
                    data-name="${row.affiliate_name.toLowerCase()}">

                    {{-- col 0: Affiliate Name --}}
                    <td class="py-4 pr-3 font-semibold text-gray-800 text-sm whitespace-nowrap">
                        ${row.affiliate_name}
                    </td>

                    {{-- col 1: Bookings --}}
                    <td class="py-4 pr-3 text-gray-600 text-sm">
                        ${row.total_bookings}
                    </td>

                    {{-- col 2: Total Revenue --}}
                    <td class="py-4 pr-3 font-semibold text-gray-800 text-sm whitespace-nowrap">
                        ${currencySymbol}${Number(row.total_gross).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        })}
                    </td>

                    {{-- col 3: Top Tour --}}
                    <td class="py-4 pr-3 text-gray-500 text-sm">
                        ${row.top_tour}
                    </td>

                    {{-- col 4: Status --}}
                    <td class="py-4 pr-3">
                        <span class="${badgeClass}" style="${badgeStyle}">${row.status}</span>
                    </td>

                    {{-- col 5: Change % --}}
                    ${changeHtml}

                </tr>
            `;
        });

        $tbody.html(html);

        // Re-apply search if something was already typed
        const q = $('#affiliateSearch').val().toLowerCase().trim();
        if (q) filterAffiliateTable(q);
    }

    // ── Search ────────────────────────────────────────────────────────
    function filterAffiliateTable(query) {
        $('.affiliate-row').each(function () {
            $(this).toggle($(this).data('name').includes(query));
        });
    }

    $(document).on('input', '#affiliateSearch', function () {
        filterAffiliateTable($(this).val().toLowerCase().trim());
    });

    // ── Column sort ───────────────────────────────────────────────────
    let affiliateSortState = { col: null, dir: 'asc' };

    $(document).on('click', '#affiliateTable thead th[data-col]', function () {

        const col = parseInt($(this).data('col'));

        if (affiliateSortState.col === col) {
            affiliateSortState.dir = affiliateSortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            affiliateSortState.col = col;
            affiliateSortState.dir = 'asc';
        }

        const dir   = affiliateSortState.dir;
        const $rows = $('#affiliateTableBody .affiliate-row').toArray();

        $rows.sort(function (a, b) {
            const aRaw = $(a).find('td').eq(col).text().trim().replace(/[$,+%]/g, '');
            const bRaw = $(b).find('td').eq(col).text().trim().replace(/[$,+%]/g, '');
            const aNum = parseFloat(aRaw);
            const bNum = parseFloat(bRaw);

            if (!isNaN(aNum) && !isNaN(bNum)) {
                return dir === 'asc' ? aNum - bNum : bNum - aNum;
            }

            return dir === 'asc'
                ? aRaw.localeCompare(bRaw)
                : bRaw.localeCompare(aRaw);
        });

        $('#affiliateTableBody').append($rows);
    });

    let hasAffiliates           = false; // set from API response
    let packagePerformanceData  = [];   // full dataset from API
    let packageFilterSelected   = [];   // currently visible package names

    // ── Render package cards ──────────────────────────────────────────
    function renderPackagePerformance(data) {

        packagePerformanceData = data;

        // Always reset to ALL selected on every fresh data load
        packageFilterSelected = data.map(p => p.package);

        // Build filter checkbox list
        buildPackageFilterList(data);

        // Draw cards
        applyPackageFilter();
    }

    // ── Build checkbox list inside dropdown ───────────────────────────
    function buildPackageFilterList(data) {

        let html = '';

        data.forEach(function (p) {
            const checked  = packageFilterSelected.includes(p.package) ? 'checked' : '';
            const safeName = p.package.replace(/"/g, '&quot;');

            html += `
                <label class="flex items-center gap-2 py-1 cursor-pointer text-sm text-gray-700 hover:text-gray-900">
                    <input
                        type="checkbox"
                        class="pkg-filter-cb rounded accent-[#00b1ff]"
                        value="${safeName}"
                        ${checked}
                    />
                    <span class="truncate">${p.package}</span>
                </label>
            `;
        });

        $('#packageFilterList').html(html);
    }

    // ── Apply filter — show only selected packages ────────────────────
    function applyPackageFilter() {

        const filtered = packagePerformanceData.filter(p =>
            packageFilterSelected.includes(p.package)
        );

        const maxNet      = Math.max(...filtered.map(p => p.net), 1);
        const maxBookings = Math.max(...filtered.map(p => p.bookings), 1);

        // Active-dot visibility
        const allSelected = packageFilterSelected.length === packagePerformanceData.length;
        $('#packageFilterDot').toggleClass('hidden', allSelected);

        if (filtered.length === 0) {
            $('#packagePerformanceList').html(
                '<div class="text-center text-gray-300 text-sm py-8">No packages selected.</div>'
            );
            return;
        }

        let html = '';

        filtered.forEach(function (p) {

            const netPct = Math.max(8, Math.round((p.net / maxNet) * 100));
            const bkPct  = Math.max(8, Math.round((p.bookings / maxBookings) * 100));

            const netFmt  = currencySymbol + Number(p.net).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const avgFmt  = currencySymbol + Number(p.avg_per_person).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const taxFmt  = currencySymbol + Number(p.tax).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            // Badge
            const badgeMap = {
                excellent : { bg: '#0d9488', color: '#ffffff', label: 'excellent' },
                good      : { bg: '#1e3a5f', color: '#ffffff', label: 'good'      },
                average   : { bg: '#f97316', color: '#ffffff', label: 'average'   },
            };
            const badge = badgeMap[p.badge] || badgeMap.good;

            html += `
                <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">

                    <!-- Name + badge -->
                    <div class="flex items-center gap-2 mb-3">
                        <span class="font-semibold text-gray-800 text-sm">${p.package}</span>
                        <span class="text-xs font-medium px-2 py-0.5 rounded-full"
                            style="background:${badge.bg}; color:${badge.color}">
                            ${badge.label}
                        </span>
                    </div>

                    <!-- Stats row -->
                    <div class="grid grid-cols-3 gap-3 text-xs">

                        <!-- Net Revenue -->
                        <div>
                            <p class="text-gray-400 mb-1">Net Revenue</p>
                        <div class="w-full bg-gray-200 rounded-full h-3 mb-1 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                    style="min-width:6%; width:${netPct}%; background:#14b8a6">
                                </div>
                            </div>
                            <p class="font-semibold text-gray-700">${netFmt}</p>
                        </div>

                        <!-- Bookings -->
                        <div>
                            <p class="text-gray-400 mb-1">Bookings</p>
                            <div class="w-full bg-gray-200 rounded-full h-3 mb-1 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-500"
                                    style="width:${bkPct}%; background:#00b1ff"></div>
                            </div>
                            <p class="font-semibold text-gray-700">${Number(p.bookings).toLocaleString()}</p>
                        </div>

                        <!-- Avg per Person -->
                        <div>
                            <p class="text-gray-400 mb-1">Avg per Person</p>
                            <div class="w-full bg-gray-100 rounded-full h-1.5 mb-1">
                                <div class="h-1.5 rounded-full bg-gray-200" style="width:100%"></div>
                            </div>
                            <p class="font-semibold text-gray-700">${avgFmt}</p>
                            <p class="text-gray-400 mt-0.5">Tax: ${taxFmt}</p>
                        </div>

                    </div>
                </div>
            `;
        });

        $('#packagePerformanceList').html(html);
    }

    // ── Checkbox change ───────────────────────────────────────────────
    $(document).on('change', '.pkg-filter-cb', function () {

        const val     = $(this).val();
        const checked = $(this).is(':checked');

        if (checked) {
            if (!packageFilterSelected.includes(val)) {
                packageFilterSelected.push(val);
            }
        } else {
            packageFilterSelected = packageFilterSelected.filter(n => n !== val);
        }

        applyPackageFilter();
    });

    // ── "All" button — re-select everything ──────────────────────────
    $(document).on('click', '#packageFilterSelectAll', function () {
        packageFilterSelected = packagePerformanceData.map(p => p.package);
        buildPackageFilterList(packagePerformanceData);
        applyPackageFilter();
    });

    // ── Toggle dropdown ───────────────────────────────────────────────
    $(document).on('click', '#packageFilterBtn', function (e) {
        e.stopPropagation();
        $('#packageFilterDropdown').toggleClass('hidden');
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#packageFilterDropdown, #packageFilterBtn').length) {
            $('#packageFilterDropdown').addClass('hidden');
        }
    });

    // ── Reset filter state when new data loads ────────────────────────
    // Call this at the top of loadDashboardMetrics() before the AJAX call
    function resetPackageFilter() {
        packageFilterSelected  = [];
        packagePerformanceData = [];
    }

    // Initial load
    loadDashboardMetrics();

    // ── Dashboard Tabs ────────────────────────────────────────────────
    var pgBookingChart  = null;
    var pgRevenueChart  = null;
    var pgLoaded        = false;

    var custTopChart      = null;
    var custGroupAvgChart = null;
    var custLoaded        = false;

    var insLoaded = false;
    var geoLoaded = false;

    var geoBookingsChart  = null;
    var geoLeadDistChart  = null;
    var geoLeadDaysChart  = null;

    var pgColors = ['#6B93C4', '#5FBDB8', '#1e3a5f', '#E89A42', '#3DAA5E', '#8B1520'];

    $(document).on('click', '.dashboard-tab-btn', function () {
        var id = $(this).attr('id').replace('tab-btn-', '');

        // Update button styles
        $('.dashboard-tab-btn')
            .removeClass('text-[#00b1ff] border-[#00b1ff]')
            .addClass('text-gray-400 border-transparent');
        $(this)
            .addClass('text-[#00b1ff] border-[#00b1ff]')
            .removeClass('text-gray-400 border-transparent');

        // Show/hide panels
        $('#tab-overview, #tab-payment, #tab-customers, #tab-insights, #tab-geographic, #tab-affiliates').addClass('hidden');
        $('#tab-' + id).removeClass('hidden');

        if (id === 'payment' && !pgLoaded) {
            loadPaymentGroupsData();
        }
        if (id === 'customers' && !custLoaded) {
            loadCustomersData();
        }
        if (id === 'insights' && !insLoaded) {
            loadInsightsData();
        }
        if (id === 'geographic' && !geoLoaded) {
            loadGeographicData();
        }
        if (id === 'affiliates' && !affLoaded) {
            loadAffiliatesData();
        }
    });

    var $pgKpis = function () {
        return $('#pgCardRevenue,#pgCardRevenueLabel,#pgCardRevenueDesc,' +
                 '#pgMostCommon,#pgMostCommonDesc,' +
                 '#pgTopRevGroup,#pgTopRevDesc,' +
                 '#pgPremiumGroup,#pgPremiumDesc');
    };

    function loadPaymentGroupsData() {
        $.ajax({
            url: paymentGroupsMetricsUrl,
            type: 'GET',
            data: $.param({
                from: dashboardState.from,
                to:   dashboardState.to,
                tourPackageIds: dashboardState.tourPackageIds,
                paymentTypes: dashboardState.paymentTypes,
                thirdPartyPlatforms: dashboardState.thirdPartyPlatforms,
                requestFrom: dashboardState.requestFrom,
                bookingSources: dashboardState.bookingSources
            }),
            traditional: false,
            beforeSend: function () {
                showLoader();
                $pgKpis().addClass('opacity-50');
                $('#pgBreakdownBody').html(
                    '<tr><td colspan="8" class="py-6 text-center text-gray-300 text-sm opacity-50">Loading...</td></tr>'
                );
                $('#pgInsightsList').html('<li class="text-xs text-gray-300 opacity-50">Loading...</li>');
            },
            success: function (res) {
                if (!res.success) return;
                pgLoaded = true;
                renderPaymentGroupsKpis(res.data.kpis);
                renderPgBookingCountChart(res.data.chart_groups);
                renderPgRevenueChart(res.data.chart_groups);
                renderPgBreakdownTable(res.data.breakdown, res.data.breakdown_totals, res.data.insights);
            },
            complete: function () {
                hideLoader();
                $pgKpis().removeClass('opacity-50');
            }
        });
    }

    var affLoaded = false;

    // Re-load when date/package filter changes
    var _origLoadDashboardMetrics = loadDashboardMetrics;
    loadDashboardMetrics = function () {
        pgLoaded   = false;
        custLoaded = false;
        insLoaded  = false;
        geoLoaded  = false;
        affLoaded  = false;
        _origLoadDashboardMetrics();
        if (!$('#tab-payment').hasClass('hidden')) {
            loadPaymentGroupsData();
        }
        if (!$('#tab-customers').hasClass('hidden')) {
            loadCustomersData();
        }
        if (!$('#tab-insights').hasClass('hidden')) {
            loadInsightsData();
        }
        if (!$('#tab-geographic').hasClass('hidden')) {
            loadGeographicData();
        }
        if (!$('#tab-affiliates').hasClass('hidden')) {
            loadAffiliatesData();
        }
    };

    function renderPaymentGroupsKpis(kpis) {
        // Total Revenue (pre-formatted by backend)
        $('#pgCardRevenueLabel').text('REVENUE (' + kpis.revenue_pct + '%)');
        $('#pgCardRevenue').text(currencySymbol + kpis.total_revenue);
        $('#pgCardRevenueDesc').text(
            'Total across ' + Number(kpis.total_bookings).toLocaleString() + ' bookings'
        );

        // Most Common Group
        $('#pgMostCommon').text(kpis.most_common_group || '—');
        $('#pgMostCommonDesc').text(
            Number(kpis.most_common_bookings).toLocaleString() + ' bookings · ' + kpis.most_common_pct + '% of all transactions'
        );

        // Top Revenue (pre-formatted by backend)
        $('#pgTopRevGroup').text(kpis.top_revenue_group || '—');
        $('#pgTopRevDesc').text(currencySymbol + kpis.top_revenue_amount + ' · ' + kpis.top_revenue_pct + '% of total revenue');

        // Premium (pre-formatted by backend)
        $('#pgPremiumGroup').text(kpis.premium_group || '—');
        $('#pgPremiumDesc').text('Avg ' + currencySymbol + kpis.premium_avg + '/booking · only ' + Number(kpis.premium_bookings).toLocaleString() + ' bookings');
    }

    function buildPgDoughnutConfig(labels, data) {
        return {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: pgColors,
                    borderWidth: 3,
                    borderColor: '#ffffff',
                    hoverOffset: 8
                }]
            },
            options: {
                cutoutPercentage: 58,
                responsive: true,
                maintainAspectRatio: false,
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        fontSize: 12,
                        padding: 16
                    }
                },
                tooltips: {
                    backgroundColor: '#ffffff',
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 10,
                    displayColors: true,
                    xPadding: 14,
                    yPadding: 10,
                    callbacks: {
                        label: function (item, chartData) {
                            var val   = chartData.datasets[0].data[item.index];
                            var total = chartData.datasets[0].data.reduce(function (a, b) { return a + b; }, 0);
                            var pct   = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ' ' + chartData.labels[item.index] + ': ' + val.toLocaleString() + ' (' + pct + '%)';
                        }
                    }
                }
            }
        };
    }

    function renderPgBookingCountChart(chartGroups) {
        var canvas = document.getElementById('pgBookingCountChart');
        if (!canvas) return;
        if (pgBookingChart) { pgBookingChart.destroy(); pgBookingChart = null; }

        var pgBookingPlugin = [{
            afterDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                var ds   = chart.data.datasets[0];
                var tot  = ds.data.reduce(function (a, b) { return a + b; }, 0);
                if (!meta.data.length || !tot) return;
                var cx = meta.data[0]._model.x;
                var cy = meta.data[0]._model.y;
                c.save();
                c.textAlign    = 'center';
                c.textBaseline = 'middle';
                // Labels inside each segment
                meta.data.forEach(function (arc, i) {
                    var val     = ds.data[i];
                    if (!val) return;
                    var pct     = ((val / tot) * 100).toFixed(1);
                    var midAngle = (arc._model.startAngle + arc._model.endAngle) / 2;
                    var segSpan = arc._model.endAngle - arc._model.startAngle;
                    var r       = (arc._model.outerRadius + arc._model.innerRadius) / 2;
                    if (segSpan > 0.35) {
                        var x = cx + Math.cos(midAngle) * r;
                        var y = cy + Math.sin(midAngle) * r;
                        c.font      = 'bold 12px sans-serif';
                        c.fillStyle = '#ffffff';
                        c.fillText(chart.data.labels[i], x, y - 9);
                        c.font      = '11px sans-serif';
                        c.fillText(val.toLocaleString() + ' (' + pct + '%)', x, y + 9);
                    }
                });
                // Doughnut hole summary
                c.font      = 'bold 15px sans-serif';
                c.fillStyle = '#111827';
                c.fillText('Total', cx, cy - 12);
                c.font      = '13px sans-serif';
                c.fillStyle = '#374151';
                c.fillText(tot.toLocaleString() + ' bookings', cx, cy + 10);
                c.restore();
            }
        }];

        var cfg = buildPgDoughnutConfig(chartGroups.labels, chartGroups.bookings);
        cfg.plugins = pgBookingPlugin;
        pgBookingChart = new Chart(canvas.getContext('2d'), cfg);
    }

    function renderPgRevenueChart(chartGroups) {
        var canvas = document.getElementById('pgRevenueChart');
        if (!canvas) return;
        if (pgRevenueChart) { pgRevenueChart.destroy(); pgRevenueChart = null; }

        var pgRevenuePlugin = [{
            afterDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                var ds   = chart.data.datasets[0];
                var tot  = ds.data.reduce(function (a, b) { return a + b; }, 0);
                if (!meta.data.length || !tot) return;
                var cx = meta.data[0]._model.x;
                var cy = meta.data[0]._model.y;
                c.save();
                c.textAlign    = 'center';
                c.textBaseline = 'middle';
                // Labels inside each segment
                meta.data.forEach(function (arc, i) {
                    var val     = ds.data[i];
                    if (!val) return;
                    var pct      = ((val / tot) * 100).toFixed(1);
                    var midAngle = (arc._model.startAngle + arc._model.endAngle) / 2;
                    var segSpan  = arc._model.endAngle - arc._model.startAngle;
                    var r        = (arc._model.outerRadius + arc._model.innerRadius) / 2;
                    if (segSpan > 0.35) {
                        var x = cx + Math.cos(midAngle) * r;
                        var y = cy + Math.sin(midAngle) * r;
                        c.font      = 'bold 12px sans-serif';
                        c.fillStyle = '#ffffff';
                        c.fillText(chart.data.labels[i], x, y - 9);
                        c.font      = '11px sans-serif';
                        c.fillText(currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' (' + pct + '%)', x, y + 9);
                    }
                });
                // Doughnut hole summary
                c.font      = 'bold 15px sans-serif';
                c.fillStyle = '#111827';
                c.fillText('Total', cx, cy - 12);
                c.font      = '13px sans-serif';
                c.fillStyle = '#374151';
                c.fillText(currencySymbol + parseFloat(tot).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }), cx, cy + 10);
                c.restore();
            }
        }];

        var cfg = buildPgDoughnutConfig(chartGroups.labels, chartGroups.revenue);
        cfg.plugins = pgRevenuePlugin;
        pgRevenueChart = new Chart(canvas.getContext('2d'), cfg);
    }

    function renderPgBreakdownTable(breakdown, totals, insights) {
        var sym = currencySymbol;

        // Rows — monetary fields are pre-formatted by backend
        var rowHtml = '';
        breakdown.forEach(function (r) {
            rowHtml += '<tr class="border-b border-gray-100 hover:bg-gray-50">' +
                '<td class="px-5 py-3.5 text-gray-700">' + r.label + '</td>' +
                '<td class="px-4 py-3.5 text-right text-gray-600">' + Number(r.bookings).toLocaleString() + '</td>' +
                '<td class="px-4 py-3.5 text-right text-gray-500">' + r.pct_bookings + '%</td>' +
                '<td class="px-4 py-3.5 text-right text-gray-700 font-medium">' + sym + r.revenue + '</td>' +
                '<td class="px-4 py-3.5 text-right text-gray-500">' + r.pct_revenue + '%</td>' +
                '<td class="px-4 py-3.5 text-right text-gray-600">' + Number(r.tourists).toLocaleString() + '</td>' +
                '<td class="px-4 py-3.5 text-right text-gray-600">' + sym + r.avg_per_booking + '</td>' +
                '<td class="px-5 py-3.5 text-right text-gray-600">' + sym + r.rev_per_tourist + '</td>' +
                '</tr>';
        });
        $('#pgBreakdownBody').html(rowHtml);

        // Totals footer — monetary fields are pre-formatted by backend
        $('#pgBreakdownFoot').html(
            '<tr style="background:#f1f5f9;">' +
            '<td class="px-5 py-3.5 font-bold text-gray-800">Total</td>' +
            '<td class="px-4 py-3.5 text-right font-bold text-gray-800">' + Number(totals.bookings).toLocaleString() + '</td>' +
            '<td class="px-4 py-3.5 text-right font-bold text-gray-800">100%</td>' +
            '<td class="px-4 py-3.5 text-right font-bold text-gray-800">' + sym + totals.revenue + '</td>' +
            '<td class="px-4 py-3.5 text-right font-bold text-gray-800">100%</td>' +
            '<td class="px-4 py-3.5 text-right font-bold text-gray-800">' + Number(totals.tourists).toLocaleString() + '</td>' +
            '<td class="px-4 py-3.5 text-right font-bold text-gray-800">' + sym + totals.avg_per_booking + '</td>' +
            '<td class="px-5 py-3.5 text-right font-bold text-gray-800">' + sym + totals.rev_per_tourist + '</td>' +
            '</tr>'
        );

        // Insights
        if (insights && insights.length) {
            var insHtml = '';
            insights.forEach(function (line) {
                insHtml += '<li class="text-xs text-gray-600 flex gap-1.5"><span class="text-gray-400 flex-shrink-0">→</span>' + line + '</li>';
            });
            $('#pgInsightsList').html(insHtml);
        } else {
            $('#pgInsightsList').html('<li class="text-xs text-gray-400">No data available for this period.</li>');
        }
    }

    // ── Customers Tab ─────────────────────────────────────────────────

    function loadCustomersData() {
        $.ajax({
            url: customersMetricsUrl,
            type: 'GET',
            data: $.param({
                from: dashboardState.from,
                to:   dashboardState.to,
                tourPackageIds: dashboardState.tourPackageIds,
                paymentTypes: dashboardState.paymentTypes,
                thirdPartyPlatforms: dashboardState.thirdPartyPlatforms,
                requestFrom: dashboardState.requestFrom,
                bookingSources: dashboardState.bookingSources
            }),
            traditional: false,
            beforeSend: function () {
                showLoader();
                $('#custTopRevenue,#custTopDesc,#custTop3Revenue,#custTop3Names,#custTop3Badge,' +
                  '#custRepeat,#custAvgRevTourist,#custAvgRevTouristDesc').addClass('opacity-50');
                $('#custInsightsList').html('<li class="text-xs text-gray-300 opacity-50">Loading...</li>');
            },
            success: function (res) {
                if (!res.success) return;
                custLoaded = true;
                renderCustomersKpis(res.data.kpis);
                renderCustTopChart(res.data.chart_customers);
                renderCustGroupAvgChart(res.data.chart_group_avg);
                renderCustInsights(res.data.insights);
                renderCustDetailTable(res.data.table_customers);
            },
            complete: function () {
                hideLoader();
                $('#custTopRevenue,#custTopDesc,#custTop3Revenue,#custTop3Names,#custTop3Badge,' +
                  '#custRepeat,#custAvgRevTourist,#custAvgRevTouristDesc').removeClass('opacity-50');
            }
        });
    }

    function renderCustomersKpis(kpis) {
        var sym = currencySymbol;

        $('#custTopRevenue').text(sym + kpis.top_customer_revenue);
        $('#custTopDesc').text(
            kpis.top_customer_name + ' · ' +
            Number(kpis.top_customer_bookings).toLocaleString() + ' bookings · ' +
            Number(kpis.top_customer_tourists).toLocaleString() + ' tourists'
        );

        $('#custTop3Revenue').text(sym + kpis.top3_revenue);
        $('#custTop3Names').text(kpis.top3_names);
        $('#custTop3Badge').text(kpis.top3_pct + '% of rev');

        $('#custRepeat').text(Number(kpis.repeat_customers).toLocaleString());

        $('#custAvgRevTourist').text(sym + kpis.avg_rev_per_tourist);
        $('#custAvgRevTouristDesc').text(
            'Consistent across all group sizes (' + sym + kpis.rev_per_tourist_min + '–' + sym + kpis.rev_per_tourist_max + ')'
        );

        // Update chart subtitle with date range
        var from = dashboardState.from ? parseDateTz(dashboardState.from).format('MMM YYYY') : '';
        var to   = dashboardState.to   ? parseDateTz(dashboardState.to).format('MMM YYYY')   : '';
        var range = (from && to && from !== to) ? from + ' – ' + to : (from || to);
        $('#custChartDateRange').text(range + ' · all card payments');
    }

    function renderCustTopChart(chartData) {
        var canvas = document.getElementById('custTopChart');
        if (!canvas) return;
        if (custTopChart) { custTopChart.destroy(); custTopChart = null; }

        var colors = pgColors.concat(pgColors); // enough for 12 bars
        var custTopRevenue = chartData.revenue;

        var custTopPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var area = chart.chartArea;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var val = custTopRevenue[i];
                    if (!val) return;
                    var text     = currencySymbol + parseFloat(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    var barEnd   = bar._model.x;
                    var barWidth = barEnd - bar._model.base;
                    var y        = bar._model.y;
                    var tw       = c.measureText(text).width;
                    var pad      = 6;
                    if (area.right - barEnd > tw + pad * 2) {
                        c.fillStyle = '#374151';
                        c.textAlign = 'left';
                        c.fillText(text, barEnd + pad, y);
                    } else if (barWidth > tw + pad * 2) {
                        c.fillStyle = '#ffffff';
                        c.textAlign = 'right';
                        c.fillText(text, barEnd - pad, y);
                    }
                });
                c.restore();
            }
        }];

        custTopChart = new Chart(canvas.getContext('2d'), {
            type: 'horizontalBar',
            plugins: custTopPlugin,
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.revenue,
                    backgroundColor: colors.slice(0, chartData.labels.length),
                    borderWidth: 0,
                    barThickness: 22
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    xAxes: [{
                        ticks: {
                            fontSize: 11,
                            fontColor: '#9CA3AF',
                            callback: function (v) {
                                return currencySymbol + (v >= 1000 ? (v / 1000).toFixed(1) + 'K' : Number(v).toFixed(2));
                            }
                        },
                        gridLines: { color: '#F3F4F6' }
                    }],
                    yAxes: [{
                        ticks: { fontSize: 11, fontColor: '#374151' },
                        gridLines: { display: false }
                    }]
                },
                tooltips: {
                    backgroundColor: '#ffffff',
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 10,
                    xPadding: 14,
                    yPadding: 10,
                    callbacks: {
                        label: function (item) {
                            return ' ' + currencySymbol + Number(item.xLabel).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            }
        });
    }

    function renderCustGroupAvgChart(chartData) {
        var canvas = document.getElementById('custGroupAvgChart');
        if (!canvas) return;
        if (custGroupAvgChart) { custGroupAvgChart.destroy(); custGroupAvgChart = null; }

        var custGroupAvgValues = chartData.avg;

        var custGroupAvgPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var topPad = 16;
                c.save();
                c.font      = 'bold 11px sans-serif';
                c.textAlign = 'center';
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var val = custGroupAvgValues[i];
                    if (!val) return;
                    var text = currencySymbol + parseFloat(val).toLocaleString(undefined, { maximumFractionDigits: 0 }) + '/booking';
                    if (bar._model.y - topPad < chart.chartArea.top) {
                        c.fillStyle    = '#ffffff';
                        c.textBaseline = 'top';
                        c.fillText(text, bar._model.x, bar._model.y + 5);
                        c.textBaseline = 'bottom';
                        c.fillStyle    = '#374151';
                    } else {
                        c.fillStyle    = '#374151';
                        c.textBaseline = 'bottom';
                        c.fillText(text, bar._model.x, bar._model.y - 4);
                    }
                });
                c.restore();
            }
        }];

        custGroupAvgChart = new Chart(canvas.getContext('2d'), {
            type: 'bar',
            plugins: custGroupAvgPlugin,
            data: {
                labels: chartData.labels,
                datasets: [{
                    data: chartData.avg,
                    backgroundColor: pgColors,
                    borderWidth: 0,
                    barThickness: 36
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    yAxes: [{
                        ticks: {
                            fontSize: 11,
                            fontColor: '#9CA3AF',
                            callback: function (v) {
                                return currencySymbol + v.toLocaleString();
                            }
                        },
                        gridLines: { color: '#F3F4F6' }
                    }],
                    xAxes: [{
                        ticks: { fontSize: 11, fontColor: '#374151' },
                        gridLines: { display: false }
                    }]
                },
                tooltips: {
                    backgroundColor: '#ffffff',
                    titleFontColor: '#111827',
                    bodyFontColor: '#111827',
                    borderColor: '#E5E7EB',
                    borderWidth: 1,
                    cornerRadius: 10,
                    xPadding: 14,
                    yPadding: 10,
                    callbacks: {
                        label: function (item) {
                            return ' Avg ' + currencySymbol + Number(item.yLabel).toLocaleString(undefined, { maximumFractionDigits: 0 }) + '/booking';
                        }
                    }
                }
            }
        });
    }

    function renderCustInsights(insights) {
        if (insights && insights.length) {
            var html = '';
            insights.forEach(function (line) {
                html += '<li class="text-xs text-gray-600 flex gap-1.5"><span class="text-gray-400 flex-shrink-0">→</span>' + line + '</li>';
            });
            $('#custInsightsList').html(html);
        } else {
            $('#custInsightsList').html('<li class="text-xs text-gray-400">No data available for this period.</li>');
        }
    }

    function renderCustDetailTable(rows) {
        var tbody = $('#custDetailTableBody');
        if (!rows || !rows.length) {
            tbody.html('<tr><td colspan="7" class="px-4 py-4 text-xs text-gray-400 text-center">No data available.</td></tr>');
            return;
        }
        var sym = currencySymbol;
        var html = '';
        rows.forEach(function (row, i) {
            var rowStyle = i % 2 !== 0 ? ' style="background:#f9fafb;"' : '';
            var name = $('<div>').text(row.name).html();
            html += '<tr' + rowStyle + '>';
            html += '<td class="px-4 py-3 text-xs text-gray-500">' + (i + 1) + '</td>';
            html += '<td class="px-4 py-3 font-medium text-gray-800">' + name + '</td>';
            html += '<td class="px-4 py-3 font-semibold" style="color:#3DAA5E;">' + sym + Number(row.revenue).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
            html += '<td class="px-4 py-3 text-gray-700">' + Number(row.bookings).toLocaleString() + '</td>';
            html += '<td class="px-4 py-3 text-gray-700">' + Number(row.tourists).toLocaleString() + '</td>';
            html += '<td class="px-4 py-3 text-gray-700">' + row.avg_pax_booking + '</td>';
            html += '<td class="px-4 py-3 text-gray-700">' + sym + Number(row.rev_per_tourist).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</td>';
            html += '</tr>';
        });
        tbody.html(html);
    }

    // ── Insights Tab ──────────────────────────────────────────────────

    var insightsSectionsData    = [];   // raw sections from API, used by PDF export
    var insightsKeyPatternsData = [];   // raw key_patterns from API

    function loadInsightsData() {
        $.ajax({
            url: insightsMetricsUrl,
            type: 'GET',
            data: $.param({
                from: dashboardState.from,
                to:   dashboardState.to,
                tourPackageIds: dashboardState.tourPackageIds,
                paymentTypes: dashboardState.paymentTypes,
                thirdPartyPlatforms: dashboardState.thirdPartyPlatforms,
                requestFrom: dashboardState.requestFrom,
                bookingSources: dashboardState.bookingSources
            }),
            traditional: false,
            beforeSend: function () {
                showLoader();
                $('#insightsSections').html(
                    '<div class="flex items-center justify-center py-16 text-gray-300 text-sm">Loading insights\u2026</div>'
                );
            },
            success: function (res) {
                if (!res.success) return;
                insLoaded = true;
                insightsSectionsData    = res.data.sections    || [];
                insightsKeyPatternsData = res.data.key_patterns || [];
                renderInsights(insightsSectionsData);
                renderKeyPatternsTable(insightsKeyPatternsData);
            },
            complete: function () { hideLoader(); }
        });
    }

    function renderInsights(sections) {
        var $container = $('#insightsSections');
        if (!sections || !sections.length) {
            $container.html('<div class="flex items-center justify-center py-16 text-gray-400 text-sm">No data available for this period.</div>');
            return;
        }

        var accentColors = {
            green:  { border: '#3DAA5E', bg: '#f0fdf4', label: '#166534' },
            teal:   { border: '#5FBDB8', bg: '#f0fdfa', label: '#0f766e' },
            orange: { border: '#E89A42', bg: '#fff7ed', label: '#c2410c' }
        };

        var html = '';
        sections.forEach(function (section) {
            html += '<div class="mb-8">';
            html += '<p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3 px-1">' + $('<div>').text(section.label).html() + '</p>';
            html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

            section.cards.forEach(function (card) {
                var colors = accentColors[card.accent] || accentColors.green;
                var icon   = $('<div>').text(card.icon).html();
                // Match formatCurrencyShort output: 708.75 | 1.5k | 2.30M
                var currencyRe = /\d[\d,]*\.\d{2}M|\d[\d,]*\.\dk|\d[\d,]*\.\d{2}/g;
                var title  = $('<div>').text(card.title).html().replace(currencyRe, function(match) { return currencySymbol + match; });
                var body   = $('<div>').text(card.body).html().replace(currencyRe, function(match) { return currencySymbol + match; });
                html += '<div class="bg-white rounded-xl p-5 shadow-sm border-l-4" style="border-color:' + colors.border + ';">';
                html += '<div class="text-2xl mb-3">' + icon + '</div>';
                html += '<h3 class="text-sm font-bold text-gray-800 mb-2 leading-snug">' + title + '</h3>';
                html += '<p class="text-xs text-gray-500 leading-relaxed">' + body + '</p>';
                html += '</div>';
            });

            html += '</div>';
            html += '</div>';
        });

        $container.html(html);
    }

    function renderKeyPatternsTable(patterns) {
        var $wrap = $('#keyPatternsSummary');
        if (!patterns || !patterns.length) {
            $wrap.hide();
            return;
        }
        var rows = '';
        patterns.forEach(function (p, i) {
            rows += '<tr class="border-t border-gray-100 hover:bg-blue-50 transition-colors">'
                + '<td class="px-5 py-3.5 text-gray-500 font-medium">' + (i + 1) + '</td>'
                + '<td class="px-5 py-3.5 text-gray-800 font-semibold">' + $('<div>').text(p.pattern).html() + '</td>'
                + '<td class="px-5 py-3.5 text-gray-600">' + $('<div>').text(p.data_point).html() + '</td>'
                + '<td class="px-5 py-3.5 text-gray-600">' + $('<div>').text(p.scale).html() + '</td>'
                + '<td class="px-5 py-3.5 text-gray-500">' + $('<div>').text(p.period).html() + '</td>'
                + '</tr>';
        });
        $wrap.find('tbody').html(rows);
        $wrap.show();
    }

    // ── Chart Zoom (expand to full-page modal) ────────────────────────
    var zoomChart = null;

    function findChartByCanvasId(canvasId) {
        var canvas = document.getElementById(canvasId);
        var keys = Object.keys(Chart.instances);
        for (var i = 0; i < keys.length; i++) {
            if (Chart.instances[keys[i]].canvas === canvas) {
                return Chart.instances[keys[i]];
            }
        }
        return null;
    }

    function safeCloneData(srcChart) {
        var data = srcChart.data;
        return {
            labels: data.labels ? data.labels.slice() : [],
            datasets: data.datasets.map(function (ds) {
                return {
                    label: ds.label,
                    data: ds.data ? ds.data.slice() : [],
                    backgroundColor: ds.backgroundColor,
                    borderColor: ds.borderColor,
                    borderWidth: ds.borderWidth,
                    pointBackgroundColor: ds.pointBackgroundColor,
                    pointBorderColor: ds.pointBorderColor,
                    pointRadius: ds.pointRadius,
                    pointHoverRadius: ds.pointHoverRadius,
                    fill: ds.fill,
                    lineTension: ds.lineTension,
                    hoverOffset: ds.hoverOffset,
                    barThickness: ds.barThickness
                };
            })
        };
    }

    function openChartZoom(canvasId, titleText, legendType) {
        if (zoomChart) { zoomChart.destroy(); zoomChart = null; }

        var modal = document.getElementById('chartZoomModal');
        var title  = document.getElementById('chartZoomModalTitle');

        // Replace canvas to clear any stale Chart.js state
        var wrap = document.getElementById('chartZoomCanvas').parentNode;
        document.getElementById('chartZoomCanvas').remove();
        var newCanvas = document.createElement('canvas');
        newCanvas.id = 'chartZoomCanvas';
        wrap.appendChild(newCanvas);

        // Reset legends
        document.getElementById('chartZoomLegendPurchase').style.display = 'none';
        document.getElementById('chartZoomLegendTourists').style.display = 'none';
        if (legendType === 'purchase') {
            document.getElementById('chartZoomLegendPurchase').style.display = 'flex';
        } else if (legendType === 'tourists') {
            document.getElementById('chartZoomLegendTourists').style.display = 'flex';
        }

        title.textContent = titleText;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';

        var srcChart = findChartByCanvasId(canvasId);
        if (srcChart) {
            var cfg = srcChart.config;
            zoomChart = new Chart(newCanvas.getContext('2d'), {
                type: cfg.type,
                data: safeCloneData(srcChart),
                plugins: cfg.plugins || [],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    legend: cfg.options.legend,
                    tooltips: cfg.options.tooltips,
                    scales: cfg.options.scales,
                    cutoutPercentage: cfg.options.cutoutPercentage
                }
            });
        }
    }

    function closeChartZoom() {
        var modal = document.getElementById('chartZoomModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.style.overflow = '';
        if (zoomChart) { zoomChart.destroy(); zoomChart = null; }
    }

    $(document).on('click', '.chart-zoom-btn', function () {
        var canvasId   = $(this).data('chart');
        var titleText  = $(this).data('title') || '';
        var legendType = $(this).data('legend') || '';
        openChartZoom(canvasId, titleText, legendType);
    });

    $('#chartZoomClose').on('click', closeChartZoom);

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') closeChartZoom();
    });

    // ─── Export PDF ───────────────────────────────────────────────────────────

    function getActiveTab() {
        var tabs = ['overview', 'payment', 'customers', 'insights', 'geographic', 'affiliates'];
        for (var i = 0; i < tabs.length; i++) {
            if (!$('#tab-' + tabs[i]).hasClass('hidden')) return tabs[i];
        }
        return 'overview';
    }

    $('#dashboardExportPdf').on('click', function () {
        exportTabPDF(getActiveTab());
    });

    // ── PDF helpers ───────────────────────────────────────────────────────────

    var PDF_CSS =
        '*{box-sizing:border-box;margin:0;padding:0}' +
        'body{font-family:"Segoe UI",Arial,sans-serif;font-size:11px;color:#1f2937;background:#f1f5f9;padding:0}' +
        '.pdf-wrap{max-width:1100px;margin:0 auto;padding:20px}' +

        /* ── Header ── */
        '.pdf-header{background:#1e3a5f;color:#fff;padding:18px 24px;border-radius:8px;margin-bottom:16px}' +
        '.pdf-hdr-tbl{width:100%;border-collapse:collapse}' +
        '.pdf-hdr-tbl td{vertical-align:middle;border:0;padding:0;background:transparent}' +
        '.pdf-header h1{font-size:17px;font-weight:700;letter-spacing:.01em;color:#fff;margin:0}' +
        '.pdf-header .period{font-size:10px;color:#a3b8d4;margin-top:4px}' +
        '.pdf-header .badge{display:inline-block;background:#2d5a8e;font-size:9px;font-weight:700;padding:5px 12px;border-radius:20px;letter-spacing:.06em;border:1px solid #3d6fa0;margin-left:6px;color:#fff}' +

        /* ── KPI table (HTML table, no grid) ── */
        '.kpi-table{width:100%;border-collapse:separate;border-spacing:8px 0;margin-bottom:10px;table-layout:fixed}' +
        '.kpi-table td{vertical-align:top;padding:0;border:0;background:transparent}' +
        '.kpi-card{border-radius:12px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.08)}' +
        '.kpi-card .k-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;margin-bottom:8px;opacity:.8}' +
        '.kpi-card .k-value{font-size:22px;font-weight:800;line-height:1;letter-spacing:-.02em}' +
        '.kpi-card .k-badge{display:inline-block;font-size:8px;font-weight:700;padding:2px 8px;border-radius:10px;margin-top:8px}' +
        '.kpi-card.green{background:#d1fae5;color:#065f46} .kpi-card.green .k-badge{background:#6ee7b7;color:#065f46}' +
        '.kpi-card.blue{background:#e0f2fe;color:#0c4a6e} .kpi-card.blue .k-badge{background:#7dd3fc;color:#0c4a6e}' +
        '.kpi-card.red{background:#fee2e2;color:#7f1d1d} .kpi-card.red .k-badge{background:#fca5a5;color:#7f1d1d}' +
        '.kpi-card.orange{background:#ffedd5;color:#7c2d12} .kpi-card.orange .k-badge{background:#fdba74;color:#7c2d12}' +
        '.kpi-card.purple{background:#ede9fe;color:#4c1d95} .kpi-card.purple .k-badge{background:#c4b5fd;color:#4c1d95}' +
        '.kpi-card.cyan{background:#cffafe;color:#164e63} .kpi-card.cyan .k-badge{background:#67e8f9;color:#164e63}' +
        '.kpi-card.slate{background:#f1f5f9;color:#1e293b} .kpi-card.slate .k-badge{background:#cbd5e1;color:#1e293b}' +
        '.kpi-card.yellow{background:#fef9c3;color:#713f12} .kpi-card.yellow .k-badge{background:#fde047;color:#713f12}' +

        /* ── Chart table (HTML table, no grid) ── */
        '.chart-table{width:100%;border-collapse:separate;border-spacing:10px 0;margin-bottom:10px}' +
        '.chart-table td{vertical-align:top;padding:0;border:0;background:transparent}' +
        '.chart-card{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 1px 4px rgba(0,0,0,.07);page-break-inside:avoid}' +
        '.chart-card h3{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6b7280;margin-bottom:10px;padding-bottom:8px;border-bottom:1px solid #f3f4f6}' +
        '.chart-card img{width:100%;display:block;border-radius:6px}' +

        /* ── Package performance cards ── */
        '.pkg-card{background:#f8faff;border:1px solid #e0e7ff;border-radius:10px;padding:12px 14px}' +
        '.pkg-card .pkg-name{font-size:11px;font-weight:700;color:#1e3a5f;margin-bottom:2px}' +
        '.pkg-card .pkg-badge{display:inline-block;font-size:8px;font-weight:700;padding:1px 7px;border-radius:8px;margin-bottom:8px}' +
        '.pkg-card .pkg-badge.good{background:#d1fae5;color:#065f46}' +
        '.pkg-card .pkg-badge.average{background:#fef9c3;color:#92400e}' +
        '.pkg-card .pkg-badge.poor{background:#fee2e2;color:#7f1d1d}' +
        '.pkg-card .pkg-row{font-size:10px;margin-top:4px;color:#374151}' +
        '.pkg-card .pkg-row .pkg-lbl{color:#6b7280}' +
        '.pkg-bar-wrap{background:#e5e7eb;border-radius:4px;height:5px;margin-top:8px}' +
        '.pkg-bar{background:#0ea5e9;border-radius:4px;height:5px}' +

        /* ── Data tables (scoped to .tbl-card to avoid polluting layout tables) ── */
        '.tbl-card{background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.07);margin-bottom:10px;overflow:hidden;page-break-inside:avoid}' +
        '.tbl-card .tbl-head{padding:12px 16px 8px;border-bottom:1px solid #f3f4f6}' +
        '.tbl-card .tbl-head h3{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#374151}' +
        '.tbl-card table{width:100%;border-collapse:collapse;font-size:10px}' +
        '.tbl-card table th{background:#1e3a5f;color:#fff;font-weight:600;font-size:9px;text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;text-align:left}' +
        '.tbl-card table td{padding:6px 10px;border-bottom:1px solid #f3f4f6;color:#374151;vertical-align:middle}' +
        '.tbl-card table tr:last-child td{border-bottom:none}' +
        '.tbl-card table tr:nth-child(even) td{background:#f8faff}' +
        '.tbl-card table tfoot td{background:#f1f5f9!important;font-weight:700;color:#111827}' +
        '.badge-pill{display:inline-block;padding:2px 8px;border-radius:20px;font-size:8px;font-weight:700}' +
        '.pill-green{background:#d1fae5;color:#065f46} .pill-yellow{background:#fef9c3;color:#92400e} .pill-red{background:#fee2e2;color:#7f1d1d}' +

        /* ── Insights ── */
        '.insights-card{background:#f0f6ff;border:1px solid #bfdbfe;border-radius:12px;padding:14px 16px;margin-bottom:10px}' +
        '.insights-card h3{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#1e3a5f;margin-bottom:8px}' +
        '.insights-card ul{list-style:none;padding:0}' +
        '.insights-card li{font-size:10px;color:#374151;padding:3px 0 3px 14px;position:relative;border-bottom:1px solid rgba(191,219,254,.4)}' +
        '.insights-card li:last-child{border-bottom:none}' +
        '.insights-card li::before{content:"\u2192";position:absolute;left:0;color:#60a5fa;font-weight:700}' +

        /* ── Print ── */
        '@media print{' +
        'body{background:#fff}' +
        '@page{margin:.8cm;size:A4 landscape}' +
        '.pdf-wrap{padding:0}' +
        '.pdf-header{border-radius:0;-webkit-print-color-adjust:exact;print-color-adjust:exact;background:#1e3a5f!important}' +
        '.kpi-card,.chart-card,.tbl-card,.insights-card,.pkg-card{-webkit-print-color-adjust:exact;print-color-adjust:exact}' +
        '}';

    function canvasImg(id) {
        var c = document.getElementById(id);
        if (!c) return '';
        try { return c.toDataURL('image/png'); } catch(e) { return ''; }
    }

    function kpiCard(label, value, cls, badge) {
        return '<td style="vertical-align:top;padding:0 4px;border:0;background:transparent">' +
            '<div class="kpi-card ' + (cls||'') + '">' +
            '<div class="k-label">' + label + '</div>' +
            '<div class="k-value">' + value + '</div>' +
            (badge ? '<div class="k-badge">' + badge + '</div>' : '') +
            '</div>' +
            '</td>';
    }

    // tdWidth: integer percentage (e.g. 50, 67, 33) — wraps in <td>
    function chartCard(title, imgSrc, extra, tdWidth) {
        var w = tdWidth !== undefined ? 'width:' + tdWidth + '%;' : '';
        if (!imgSrc) return '<td style="' + w + 'vertical-align:top;border:0;background:transparent"></td>';
        return '<td style="' + w + 'vertical-align:top;padding:0;border:0;background:transparent">' +
            '<div class="chart-card"><h3>' + title + '</h3>' + (extra||'') + '<img src="' + imgSrc + '"></div>' +
            '</td>';
    }

    function openPrintWindow(html, filename) {
        filename = filename || 'dashboard.pdf';

        // Show a brief loading state on the button
        var $btn = $('#dashboardExportPdf');
        var origHtml = $btn.html();
        $btn.html('<svg class="w-4 h-4 animate-spin text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg><span>Generating…</span>').prop('disabled', true);

        $.ajax({
            url:  dashboardExportPdfUrl,
            type: 'POST',
            data: { html: html, filename: filename, _token: csrfToken },
            xhrFields: { responseType: 'blob' },
            success: function (blob) {
                var url = URL.createObjectURL(blob);
                var a   = document.createElement('a');
                a.href     = url;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            },
            error: function () {
                alert('PDF generation failed. Please try again.');
            },
            complete: function () {
                $btn.html(origHtml).prop('disabled', false);
            }
        });
    }

    function buildPdfHtml(title, dateRange, body) {
        var rangeLabel = $('#dashboardRangeLabel').text().trim() || '';
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
            '<title>Dashboard \u2014 ' + title + '</title>' +
            '<style>' + PDF_CSS + '</style></head><body>' +
            '<div class="pdf-wrap">' +
            '<div class="pdf-header">' +
            '<table class="pdf-hdr-tbl"><tr>' +
            '<td><h1>Dashboard \u2014 ' + title + '</h1><div class="period">Period: ' + dateRange + '</div></td>' +
            '<td style="text-align:right;white-space:nowrap">' +
            (rangeLabel ? '<span class="badge">' + rangeLabel + '</span>' : '') +
            '<span class="badge">EXPORTED REPORT</span>' +
            '</td>' +
            '</tr></table>' +
            '</div>' +
            body +
            '</div></body></html>';
    }

    function exportTabPDF(tab) {
        var dateRange = dashboardState.from + ' to ' + dashboardState.to;

        if (tab === 'overview') {
            // Comparison badges from DOM
            function cmpBadge(id) {
                var el = document.getElementById(id);
                if (!el || el.classList.contains('hidden')) return '';
                return el.innerText.trim();
            }

            // Package performance cards — built as table rows of 2
            var pkgHtml = '';
            if (packagePerformanceData && packagePerformanceData.length) {
                var maxBk = Math.max.apply(null, packagePerformanceData.map(function(p){ return p.bookings || 0; })) || 1;
                var pkgCells = [];
                packagePerformanceData.forEach(function(p) {
                    var pct = Math.round(((p.bookings || 0) / maxBk) * 100);
                    var cls = p.status === 'good' ? 'good' : (p.status === 'average' ? 'average' : 'poor');
                    pkgCells.push(
                        '<td style="width:50%;vertical-align:top;padding:4px;border:0">' +
                        '<div class="pkg-card">' +
                        '<div class="pkg-name">' + (p.package||'') + '</div>' +
                        '<span class="pkg-badge ' + cls + '">' + (p.status||'') + '</span>' +
                        '<div class="pkg-row"><span class="pkg-lbl">Net Revenue</span> <strong>' + currencySymbol + (p.net||0) + '</strong></div>' +
                        '<div class="pkg-row"><span class="pkg-lbl">Bookings</span> <strong>' + (p.bookings||0) + '</strong></div>' +
                        '<div class="pkg-row"><span class="pkg-lbl">Avg per Person</span> <strong>' + currencySymbol + (p.avg_per_person||0) + '</strong></div>' +
                        '<div class="pkg-row"><span class="pkg-lbl">Tax</span> <strong>' + currencySymbol + (p.tax||0) + '</strong></div>' +
                        '<div class="pkg-bar-wrap"><div class="pkg-bar" style="width:' + pct + '%"></div></div>' +
                        '</div></td>'
                    );
                });
                var pkgRows = '';
                for (var pi = 0; pi < pkgCells.length; pi += 2) {
                    pkgRows += '<tr>' + pkgCells[pi] + (pkgCells[pi + 1] || '<td style="width:50%;border:0"></td>') + '</tr>';
                }
                pkgHtml = '<table style="width:100%;border-collapse:separate;border-spacing:0 6px">' + pkgRows + '</table>';
            }

            // Affiliate table — strip interactive sort icons and hidden columns
            var affTableHtml = '';
            var affTableEl = document.getElementById('affiliateTable');
            if (affTableEl) {
                var affClone = affTableEl.cloneNode(true);
                affClone.querySelectorAll('.sort-icon').forEach(function(el){ el.remove(); });
                affClone.querySelectorAll('.change-col').forEach(function(el){ el.remove(); });
                affTableHtml = affClone.outerHTML;
            }

            var purchaseLegend =
                '<div style="margin-bottom:6px;font-size:9px">' +
                '<span style="display:inline-block;margin-right:10px"><i style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#5FBDB8;vertical-align:middle;margin-right:3px"></i>Booked</span>' +
                '<span style="display:inline-block"><i style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#D9381E;vertical-align:middle;margin-right:3px"></i>Cancelled/Refund</span>' +
                '</div>';

            var touristsLegend =
                '<div style="margin-bottom:6px;font-size:9px">' +
                '<span style="display:inline-block;margin-right:10px"><i style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#E05C3A;vertical-align:middle;margin-right:3px"></i>Tourists</span>' +
                '<span style="display:inline-block"><i style="display:inline-block;width:10px;height:10px;border-radius:2px;background:#5FBDB8;vertical-align:middle;margin-right:3px"></i>Bookings</span>' +
                '</div>';

            var body =
                // ── Row 1: KPI cards ────────────────────────────────────
                '<table class="kpi-table"><tr>' +
                    kpiCard('Total Gross',      $('#totalGross').text(),      'green',  cmpBadge('grossComparison'))    +
                    kpiCard('Total Net',        $('#totalNet').text(),        'blue',   cmpBadge('netComparison'))      +
                    kpiCard('Total Refund',     $('#totalRefund').text(),     'red',    cmpBadge('refundComparison'))   +
                    kpiCard('Total Discount',   $('#totalDiscount').text(),   'orange', cmpBadge('discountComparison')) +
                '</tr></table>' +
                '<table class="kpi-table"><tr>' +
                    kpiCard('Total Tourists',   $('#totalTourists').text(),   'purple', cmpBadge('touristsComparison'))  +
                    kpiCard('Total Bookings',   $('#totalBookings').text(),   'cyan',   cmpBadge('bookingsComparison'))  +
                    kpiCard('Total Tax',        $('#totalTax').text(),        'slate',  cmpBadge('taxComparison'))       +
                    kpiCard('Total Permit Fee', $('#totalPermitFee').text(),  'yellow', cmpBadge('permitFeeComparison')) +
                '</tr></table>' +

                // ── Row 2: Net by date (wide) + Purchase type (narrow) ──
                '<table class="chart-table"><tr>' +
                    chartCard('Total Net by Tour Date', canvasImg('netByDateChart'), null, 67) +
                    chartCard('Net by Purchase Type',   canvasImg('purchaseTypeChart'), purchaseLegend, 33) +
                '</tr></table>' +

                // ── Row 3: Net & Bookings by package + Peak times ───────
                '<table class="chart-table"><tr>' +
                    chartCard('Net &amp; Bookings by Tour Package', canvasImg('netByPackageChart'), null, 67) +
                    chartCard('Peak Booking Times',                 canvasImg('peakBookingChart'), null, 33) +
                '</tr></table>' +

                // ── Row 4: Most profitable + Weekends/Weekdays + Net by payment date ──
                '<table class="chart-table"><tr>' +
                    chartCard('Most Profitable Days',  canvasImg('profitableDaysChart'), null, 33) +
                    chartCard('Weekends vs Weekdays',  canvasImg('weekendWeekdayChart'), null, 33) +
                    chartCard('Net by Payment Date',   canvasImg('netPaymentDateChart'), null, 33) +
                '</tr></table>' +

                // ── Row 5: Tourists & Bookings (full width) ─────────────
                '<table class="chart-table"><tr>' +
                    chartCard('Tourists &amp; Bookings', canvasImg('touristsBookingsChart'), touristsLegend, 100) +
                '</tr></table>' +

                // ── Row 6: State tax (wide) + Avg tax (narrow) ──────────
                '<table class="chart-table"><tr>' +
                    chartCard('State Tax by Tour Date', canvasImg('stateTaxByDateChart'), null, 67) +
                    chartCard('Avg Tax per Person',     canvasImg('avgTaxPerPersonChart'), null, 33) +
                '</tr></table>' +

                // ── Row 7: Affiliate + Package Performance ───────────────
                '<table class="chart-table"><tr>' +
                    (hasAffiliates && affTableHtml
                        ? '<td style="width:50%;vertical-align:top;padding:0 4px;border:0"><div class="tbl-card"><div class="tbl-head"><h3>Affiliate Summary</h3></div>' + affTableHtml + '</div></td>'
                        : '') +
                    (pkgHtml
                        ? '<td style="width:' + (hasAffiliates ? '50' : '100') + '%;vertical-align:top;padding:0 4px;border:0"><div class="chart-card"><h3>Tour Package Performance</h3>' + pkgHtml + '</div></td>'
                        : '') +
                '</tr></table>';

            openPrintWindow(buildPdfHtml('Overview & Revenue Trend', dateRange, body), 'dashboard_overview_' + dateRange + '.pdf');
            return;
        }

        if (tab === 'payment') {
            var body =
                '<table class="kpi-table"><tr>' +
                    kpiCard($('#pgCardRevenueLabel').text() || 'Revenue', $('#pgCardRevenue').text(),  'cyan',   $('#pgCardRevenueDesc').text()) +
                    kpiCard('Most Common Group',   $('#pgMostCommon').text(),   'blue',   $('#pgMostCommonDesc').text())  +
                    kpiCard('Highest Rev Segment', $('#pgTopRevGroup').text(),  'green',  $('#pgTopRevDesc').text())      +
                    kpiCard('Highest Rev/Booking', $('#pgPremiumGroup').text(), 'orange', $('#pgPremiumDesc').text())     +
                '</tr></table>' +
                '<table class="chart-table"><tr>' +
                    chartCard('Booking Count by Group Size', canvasImg('pgBookingCountChart'), null, 50) +
                    chartCard('Revenue by Group Size',       canvasImg('pgRevenueChart'), null, 50)      +
                '</tr></table>';

            var pgTable = document.getElementById('pgBreakdownTable');
            if (pgTable) {
                body += '<div class="tbl-card"><div class="tbl-head"><h3>Group Size — Full Breakdown</h3></div>' + pgTable.outerHTML + '</div>';
            }

            var insItems = [];
            $('#pgInsightsList li').each(function () {
                var t = $(this).text().trim().replace(/^→\s*/, '');
                if (t) insItems.push('<li>' + t + '</li>');
            });
            if (insItems.length) {
                body += '<div class="insights-card"><h3>&#9432; Group Size Pattern</h3><ul>' + insItems.join('') + '</ul></div>';
            }

            openPrintWindow(buildPdfHtml('Payment & Groups', dateRange, body), 'dashboard_payment_groups_' + dateRange + '.pdf');
            return;
        }

        if (tab === 'customers') {
            var body =
                '<table class="kpi-table"><tr>' +
                    kpiCard('Top Customer',      $('#custTopRevenue').text(),      'cyan',   $('#custTopDesc').text())            +
                    kpiCard('Top 3 Combined',    $('#custTop3Revenue').text(),     'blue',   $('#custTop3Names').text())          +
                    kpiCard('Repeat Customers',  $('#custRepeat').text(),          'green',  'Customers with 2+ bookings')       +
                    kpiCard('Avg Rev / Tourist', $('#custAvgRevTourist').text(),   'orange', $('#custAvgRevTouristDesc').text())  +
                '</tr></table>' +
                '<table class="chart-table"><tr>' +
                    chartCard('Top 12 Customers by Revenue',       canvasImg('custTopChart'), null, 50)      +
                    chartCard('Revenue per Booking by Group Size', canvasImg('custGroupAvgChart'), null, 50) +
                '</tr></table>';

            var custTable = document.getElementById('custDetailTable');
            if (custTable) {
                body += '<div class="tbl-card"><div class="tbl-head"><h3>Top 12 Customer Detail</h3></div>' + custTable.outerHTML + '</div>';
            }

            var custInsItems = [];
            $('#custInsightsList li').each(function () {
                var t = $(this).text().trim().replace(/^→\s*/, '');
                if (t) custInsItems.push('<li>' + t + '</li>');
            });
            if (custInsItems.length) {
                body += '<div class="insights-card"><h3>&#9432; Customer Pattern</h3><ul>' + custInsItems.join('') + '</ul></div>';
            }

            openPrintWindow(buildPdfHtml('Customers', dateRange, body), 'dashboard_customers_' + dateRange + '.pdf');
            return;
        }

        if (tab === 'insights') {
            var accentBorders = { green: '#3DAA5E', teal: '#5FBDB8', orange: '#E89A42' };
            var accentBgs     = { green: '#f0fdf4', teal: '#f0fdfa', orange: '#fff7ed' };
            var body = '';

            // ── Sections (Revenue Performance, Pricing & Yield, etc.) ──
            if (insightsSectionsData && insightsSectionsData.length) {
                insightsSectionsData.forEach(function (section) {
                    body += '<p style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin:16px 0 8px">' +
                        section.label + '</p>';
                    var cardCells = '';
                    section.cards.forEach(function (card) {
                        var border = accentBorders[card.accent] || '#3DAA5E';
                        var bg     = accentBgs[card.accent]     || '#f0fdf4';
                        cardCells +=
                            '<td style="width:33%;vertical-align:top;padding:4px;border:0">' +
                            '<div style="background:' + bg + ';border-left:4px solid ' + border + ';border-radius:10px;padding:14px 14px 12px;box-shadow:0 1px 3px rgba(0,0,0,.06)">' +
                            '<div style="margin-bottom:8px;line-height:1">' + (card.pdf_icon || '') + '</div>' +
                            '<div style="font-size:11px;font-weight:700;color:#111827;margin-bottom:6px;line-height:1.4">' + card.title + '</div>' +
                            '<div style="font-size:10px;color:#6b7280;line-height:1.5">' + card.body + '</div>' +
                            '</div></td>';
                    });
                    body += '<table style="width:100%;border-collapse:separate;border-spacing:8px 0;margin-bottom:4px"><tr>' + cardCells + '</tr></table>';
                });
            }

            // ── Key Patterns summary table ─────────────────────────────
            if (insightsKeyPatternsData && insightsKeyPatternsData.length) {
                body += '<p style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#6b7280;margin:16px 0 8px">Key Patterns — Summary</p>';
                body += '<div class="tbl-card"><table><thead><tr>' +
                    '<th>#</th><th>Pattern</th><th>Data Point</th><th>Scale</th><th>Period</th>' +
                    '</tr></thead><tbody>';
                insightsKeyPatternsData.forEach(function (p, i) {
                    body += '<tr><td>' + (i + 1) + '</td><td><strong>' + p.pattern + '</strong></td>' +
                        '<td>' + p.data_point + '</td><td>' + p.scale + '</td><td>' + p.period + '</td></tr>';
                });
                body += '</tbody></table></div>';
            }

            if (!body) body = '<p style="color:#9ca3af;font-size:12px;padding:20px">No insights available for the selected period.</p>';

            openPrintWindow(buildPdfHtml('Insights', dateRange, body), 'dashboard_insights_' + dateRange + '.pdf');
            return;
        }

        if (tab === 'geographic') {
            var body =
                '<table class="kpi-table"><tr>' +
                    kpiCard('Top Bookings',   $('#geoTopBookings').text(),  'cyan',   $('#geoTopBookingsDesc').text() + ' · ' + $('#geoTopCountryBadge').text()) +
                    kpiCard('Other Bookings', $('#geoIntlPct').text(),      'blue',   $('#geoIntlDesc').text())       +
                    kpiCard('Earliest Booker',        $('#geoEarliestCountry').text(), 'green',  $('#geoEarliestDesc').text())    +
                    kpiCard('Latest Booker',          $('#geoLatestCountry').text(),   'orange', $('#geoLatestDesc').text())      +
                '</tr></table>' +
                '<table class="chart-table"><tr>' +
                    chartCard('Bookings by Country',          canvasImg('geoBookingsChart'),  null, 67) +
                    chartCard('Lead Days Distribution',       canvasImg('geoLeadDistChart'),  null, 33) +
                '</tr></table>' +
                '<table class="chart-table"><tr>' +
                    chartCard('Avg Booking Lead Days by Country', canvasImg('geoLeadDaysChart'), null, 100) +
                '</tr></table>';

            // Lead time insights
            var leadInsItems = [];
            $('#geoLeadInsightsList li').each(function () {
                var t = $(this).text().trim().replace(/^→\s*/, '');
                if (t) leadInsItems.push('<li>' + t + '</li>');
            });
            if (leadInsItems.length) {
                body += '<div class="insights-card"><h3>&#9432; Lead Time Pattern</h3><ul>' + leadInsItems.join('') + '</ul></div>';
            }

            // Top countries table
            var geoTable = document.getElementById('geoTable');
            if (geoTable) {
                body += '<div class="tbl-card"><div class="tbl-head"><h3>Top 15 Countries – Summary</h3></div>' + geoTable.outerHTML + '</div>';
            }

            // Geographic insights
            var geoInsItems = [];
            $('#geoInsightsList li').each(function () {
                var t = $(this).text().trim().replace(/^→\s*/, '');
                if (t) geoInsItems.push('<li>' + t + '</li>');
            });
            if (geoInsItems.length) {
                body += '<div class="insights-card"><h3>&#9432; Geographic Pattern</h3><ul>' + geoInsItems.join('') + '</ul></div>';
            }

            openPrintWindow(buildPdfHtml('Geographic', dateRange, body), 'dashboard_geographic_' + dateRange + '.pdf');
            return;
        }

        if (tab === 'affiliates') {
            var body =
                '<table class="kpi-table"><tr>' +
                    kpiCard('Total Gross',         $('#affBookingAmount').text(),      'green',  '') +
                    kpiCard('Refund Amount',        $('#affRefundAmount').text(),       'red',    '') +
                    kpiCard('Total Net Collected',  $('#affTotalNetCollected').text(),  'cyan',   '') +
                '</tr></table>' +
                '<table class="kpi-table"><tr>' +
                    kpiCard('Net Card',        $('#affNetCard').text(),        'blue',   '') +
                    kpiCard('Net Cash',        $('#affNetCash').text(),        'green',  '') +
                    kpiCard('Net Invoiced',    $('#affNetInvoiced').text(),    'purple', '') +
                    kpiCard('Net TripAdvisor', $('#affNetTripAdvisor').text(), 'yellow', '') +
                '</tr></table>' +
                '<table class="kpi-table"><tr>' +
                    kpiCard('Net Headout',      $('#affNetHeadout').text(),              'red',    '') +
                    kpiCard('Net FareHarbor',   $('#affNetFareHarbor').text(),           'orange', '') +
                    kpiCard('Total Affiliates', $('#affTotalAffiliates').text(),         'blue',   $('#affTotalBookings').text()) +
                    kpiCard('Cancel Booking',   $('#affCancelBookings').text(),          'orange', '') +
                '</tr></table>';

            var affTable = document.getElementById('affTable');
            if (affTable) {
                var affClone = affTable.cloneNode(true);
                affClone.querySelector('#affTableSearch') && affClone.querySelector('#affTableSearch').remove();
                body += '<div class="tbl-card"><div class="tbl-head"><h3>Affiliate Bookings</h3></div>' + affClone.outerHTML + '</div>';
            }

            openPrintWindow(buildPdfHtml('Affiliates', dateRange, body), 'dashboard_affiliates_' + dateRange + '.pdf');
            return;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // GEOGRAPHIC TAB
    // ═══════════════════════════════════════════════════════════════

    function loadGeographicData() {
        $.ajax({
            url: geographicMetricsUrl,
            type: 'GET',
            data: $.param({
                from: dashboardState.from,
                to:   dashboardState.to,
                tourPackageIds: dashboardState.tourPackageIds,
                paymentTypes: dashboardState.paymentTypes,
                thirdPartyPlatforms: dashboardState.thirdPartyPlatforms,
                requestFrom: dashboardState.requestFrom,
                bookingSources: dashboardState.bookingSources
            }),
            traditional: false,
            beforeSend: function () {
                showLoader();
                $('#geoTopBookings,#geoTopBookingsDesc,#geoTopCountryBadge,#geoIntlPct,#geoIntlDesc,' +
                  '#geoEarliestCountry,#geoEarliestDesc,#geoLatestCountry,#geoLatestDesc').addClass('opacity-50');
                $('#geoTableBody').html(
                    '<tr><td colspan="6" class="py-8 text-center text-gray-300 text-sm opacity-50">Loading...</td></tr>'
                );
            },
            success: function (res) {
                if (!res.success) return;
                geoLoaded = true;
                renderGeographicKpis(res.data.kpis);
                renderGeoBookingsChart(res.data.bookings_by_country);
                renderGeoLeadDistChart(res.data.lead_distribution);
                renderGeoLeadDaysChart(res.data.lead_days_by_country);
                renderGeoTable(res.data.table);
                renderGeoInsights(res.data.insights);
                renderGeoLeadInsights(res.data.lead_insights);
            },
            complete: function () {
                hideLoader();
                $('#geoTopBookings,#geoTopBookingsDesc,#geoTopCountryBadge,#geoIntlPct,#geoIntlDesc,' +
                  '#geoEarliestCountry,#geoEarliestDesc,#geoLatestCountry,#geoLatestDesc').removeClass('opacity-50');
            }
        });
    }

    function renderGeographicKpis(kpis) {
        $('#geoTopBookings').text(Number(kpis.top_bookings).toLocaleString());
        $('#geoTopBookingsDesc').text(
            kpis.top_pct + '% of all bookings · ' +
            Number(kpis.top_tourists).toLocaleString() + ' tourists'
        );
        $('#geoTopCountryBadge').text(kpis.top_country || '#1');

        $('#geoIntlPct').text(Number(kpis.other_bookings).toLocaleString());
        $('#geoIntlDesc').text(kpis.other_pct + '% from other countries');

        $('#geoEarliestCountry').text(kpis.earliest_country || '—');
        $('#geoEarliestDesc').text('Avg ' + Number(kpis.earliest_days).toLocaleString() + ' days in advance');

        $('#geoLatestCountry').text(kpis.latest_country || '—');
        $('#geoLatestDesc').text('Avg ' + Number(kpis.latest_days).toLocaleString() + ' days in advance');
    }

    function renderGeoBookingsChart(chartData) {
        var canvas = document.getElementById('geoBookingsChart');
        if (!canvas) return;
        if (geoBookingsChart) { geoBookingsChart.destroy(); geoBookingsChart = null; }

        var bookingsData = chartData.bookings;
        var touristsData = chartData.tourists;

        var geoBookingsPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var area = chart.chartArea;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var bk = bookingsData[i];
                    var tc = touristsData[i];
                    if (!bk) return;
                    var line1    = Number(bk).toLocaleString() + ' bookings';
                    var line2    = Number(tc).toLocaleString() + ' tourists';
                    var barEnd   = bar._model.x;
                    var barWidth = barEnd - bar._model.base;
                    var y        = bar._model.y;
                    var tw       = Math.max(c.measureText(line1).width, c.measureText(line2).width);
                    var pad      = 6;
                    var lineH    = 13;
                    if (area.right - barEnd > tw + pad * 2) {
                        c.fillStyle = '#374151';
                        c.textAlign = 'left';
                        c.fillText(line1, barEnd + pad, y - lineH / 2);
                        c.fillText(line2, barEnd + pad, y + lineH / 2);
                    } else if (barWidth > tw + pad * 2) {
                        c.fillStyle = '#ffffff';
                        c.textAlign = 'right';
                        c.fillText(line1, barEnd - pad, y - lineH / 2);
                        c.fillText(line2, barEnd - pad, y + lineH / 2);
                    }
                });
                c.restore();
            }
        }];

        geoBookingsChart = new Chart(canvas, {
            type: 'horizontalBar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Bookings',
                    data: chartData.bookings,
                    backgroundColor: pgColors.concat(pgColors).concat(pgColors),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.05)' } }],
                    yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
                },
                tooltips: {
                    mode: 'y',
                    intersect: false,
                    callbacks: {
                        title: function (items) {
                            return chartData.labels[items[0].index];
                        },
                        label: function (item) {
                            var i = item.index;
                            return ' Bookings: ' + Number(bookingsData[i]).toLocaleString() +
                                   '  ·  Tourists: ' + Number(touristsData[i]).toLocaleString();
                        }
                    }
                }
            },
            plugins: geoBookingsPlugin
        });
    }

    function renderGeoLeadDistChart(chartData) {
        var canvas = document.getElementById('geoLeadDistChart');
        if (!canvas) return;
        if (geoLeadDistChart) { geoLeadDistChart.destroy(); geoLeadDistChart = null; }

        var distColors = ['#1e3a5f', '#6B93C4', '#5FBDB8', '#3DAA5E', '#E89A42', '#8B1520'];
        var distValues = chartData.values;
        var distLabels = chartData.labels;
        var total      = distValues.reduce(function (s, v) { return s + v; }, 0);

        var geoLeadDistPlugin = [{
            afterDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var meta = chart.getDatasetMeta(0);
                c.save();
                meta.data.forEach(function (arc, i) {
                    var val = distValues[i];
                    if (!val || arc._model.endAngle - arc._model.startAngle < 0.2) return;
                    var mid   = (arc._model.startAngle + arc._model.endAngle) / 2;
                    var r     = (arc._model.outerRadius + arc._model.innerRadius) / 2;
                    var x     = arc._model.x + Math.cos(mid) * r;
                    var y     = arc._model.y + Math.sin(mid) * r;
                    c.font         = 'bold 11px sans-serif';
                    c.textAlign    = 'center';
                    c.textBaseline = 'middle';
                    c.fillStyle    = '#ffffff';
                    c.fillText(Number(val).toLocaleString(), x, y);
                });
                // hole summary
                if (meta.data.length > 0) {
                    var cx = meta.data[0]._model.x;
                    var cy = meta.data[0]._model.y;
                    c.font         = 'bold 14px sans-serif';
                    c.textAlign    = 'center';
                    c.textBaseline = 'middle';
                    c.fillStyle    = '#1e3a5f';
                    c.fillText(Number(total).toLocaleString(), cx, cy - 8);
                    c.font      = '11px sans-serif';
                    c.fillStyle = '#6b7280';
                    c.fillText('bookings', cx, cy + 10);
                }
                c.restore();
            }
        }];

        geoLeadDistChart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: distLabels,
                datasets: [{
                    data: distValues,
                    backgroundColor: distColors,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutoutPercentage: 55,
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: { fontSize: 10, boxWidth: 12, padding: 8 }
                },
                tooltips: {
                    callbacks: {
                        label: function (item, data) {
                            var val = data.datasets[0].data[item.index];
                            var pct = total > 0 ? (val / total * 100).toFixed(1) : 0;
                            return ' ' + data.labels[item.index] + ': ' + Number(val).toLocaleString() + ' (' + pct + '%)';
                        }
                    }
                }
            },
            plugins: geoLeadDistPlugin
        });
    }

    function renderGeoLeadDaysChart(chartData) {
        var canvas = document.getElementById('geoLeadDaysChart');
        if (!canvas) return;
        if (geoLeadDaysChart) { geoLeadDaysChart.destroy(); geoLeadDaysChart = null; }

        var leadData   = chartData.lead_days;
        var styles     = chartData.booking_styles || [];
        var styleColor = {
            'Same-day':     '#1e3a5f',
            'Last minute':  '#8B1520',
            'Late planner': '#E89A42',
            'Mid-range':    '#6B93C4',
            'Early planner':'#3DAA5E'
        };
        var barColors = leadData.map(function (_, i) {
            return styleColor[styles[i]] || '#5FBDB8';
        });

        var geoLeadDaysPlugin = [{
            afterDatasetsDraw: function (chart) {
                if (chart.canvas.id !== 'chartZoomCanvas') return;
                var c    = chart.ctx;
                var area = chart.chartArea;
                c.save();
                c.font         = 'bold 11px sans-serif';
                c.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach(function (bar, i) {
                    var val = leadData[i];
                    if (!val) return;
                    var text     = Number(val).toLocaleString() + ' days';
                    var barEnd   = bar._model.x;
                    var barWidth = barEnd - bar._model.base;
                    var y        = bar._model.y;
                    var tw       = c.measureText(text).width;
                    var pad      = 6;
                    if (area.right - barEnd > tw + pad * 2) {
                        c.fillStyle = '#374151';
                        c.textAlign = 'left';
                        c.fillText(text, barEnd + pad, y);
                    } else if (barWidth > tw + pad * 2) {
                        c.fillStyle = '#ffffff';
                        c.textAlign = 'right';
                        c.fillText(text, barEnd - pad, y);
                    }
                });
                c.restore();
            }
        }];

        geoLeadDaysChart = new Chart(canvas, {
            type: 'horizontalBar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Avg Lead Days',
                    data: leadData,
                    backgroundColor: barColors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { display: false },
                scales: {
                    xAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: 'rgba(0,0,0,0.05)' } }],
                    yAxes: [{ ticks: { fontSize: 11 }, gridLines: { display: false } }]
                },
                tooltips: {
                    callbacks: {
                        label: function (item) {
                            return ' ' + Number(item.xLabel).toLocaleString() + ' days avg lead';
                        }
                    }
                }
            },
            plugins: geoLeadDaysPlugin
        });
    }

    function renderGeoTable(rows) {
        var tbody = document.getElementById('geoTableBody');
        if (!tbody) return;
        if (!rows || !rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="py-8 text-center text-gray-300 text-sm">No data available.</td></tr>';
            return;
        }
        var styleMap = {
            'Same-day':     'background:#fef3c7;color:#92400e;',
            'Last minute':  'background:#fee2e2;color:#991b1b;',
            'Late planner': 'background:#fff3e0;color:#c2410c;',
            'Mid-range':    'background:#e0f2fe;color:#0369a1;',
            'Early planner':'background:#dcfce7;color:#15803d;'
        };
        var html = '';
        rows.forEach(function (row, i) {
            var style = styleMap[row.booking_style] || 'background:#f3f4f6;color:#374151;';
            var rowBg = i % 2 === 0 ? '' : 'style="background:#f9fafb;"';
            html += '<tr ' + rowBg + '>' +
                '<td class="px-4 py-2.5 text-xs text-gray-400">' + (i + 1) + '</td>' +
                '<td class="px-4 py-2.5 text-sm font-medium text-gray-800">' + (row.country || '—') + '</td>' +
                '<td class="px-4 py-2.5 text-sm text-right text-gray-700">' + Number(row.bookings).toLocaleString() + '</td>' +
                '<td class="px-4 py-2.5 text-sm text-right text-gray-700">' + Number(row.tourists).toLocaleString() + '</td>' +
                '<td class="px-4 py-2.5 text-sm text-right text-gray-700">' + Number(row.avg_lead_days).toLocaleString() + '</td>' +
                '<td class="px-4 py-2.5 text-right">' +
                    '<span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full" style="' + style + '">' +
                    row.booking_style + '</span>' +
                '</td>' +
                '</tr>';
        });
        tbody.innerHTML = html;
    }

    function renderGeoLeadInsights(insights) {
        var list = document.getElementById('geoLeadInsightsList');
        if (!list) return;
        if (!insights || !insights.length) {
            list.innerHTML = '<li class="text-xs text-gray-400">No pattern data available.</li>';
            return;
        }
        list.innerHTML = insights.map(function (text) {
            return '<li class="text-xs text-gray-700 flex items-start gap-1.5">' +
                '<span style="color:#5FBDB8;flex-shrink:0;">→</span>' +
                '<span>' + text + '</span>' +
                '</li>';
        }).join('');
    }

    function renderGeoInsights(insights) {
        var list = document.getElementById('geoInsightsList');
        if (!list) return;
        if (!insights || !insights.length) {
            list.innerHTML = '<li class="text-xs text-gray-400">No pattern data available.</li>';
            return;
        }
        list.innerHTML = insights.map(function (text) {
            return '<li class="text-xs text-gray-700 flex items-start gap-1.5">' +
                '<span style="color:#5FBDB8;flex-shrink:0;">→</span>' +
                '<span>' + text + '</span>' +
                '</li>';
        }).join('');
    }

    // ═══════════════════════════════════════════════════════════════
    // AFFILIATES TAB
    // ═══════════════════════════════════════════════════════════════

    var affAllRows      = [];
    var affFilteredRows = [];
    var affCurrentPage  = 1;
    var affPageSize     = 200;
    var affSortCol      = 'id';
    var affSortDir      = 'desc';

    function updateAffSortIcons() {
        $('#affTable thead th[data-sort-col]').each(function () {
            var icon = $(this).find('.aff-sort-icon');
            if ($(this).data('sort-col') === affSortCol) {
                icon.text(affSortDir === 'asc' ? '↑' : '↓').css('opacity', '1');
            } else {
                icon.text('↕').css('opacity', '0.4');
            }
        });
    }

    function getAffSortedRows() {
        if (!affSortCol) return affFilteredRows.slice();
        var numericCols = { id: true, tourists: true, booking_amount: true, refund_amount: true, paid_amount: true };
        return affFilteredRows.slice().sort(function (a, b) {
            var va = a[affSortCol], vb = b[affSortCol];
            if (numericCols[affSortCol]) {
                va = parseFloat(va) || 0;
                vb = parseFloat(vb) || 0;
            } else {
                va = (va || '').toLowerCase();
                vb = (vb || '').toLowerCase();
            }
            if (va < vb) return affSortDir === 'asc' ? -1 : 1;
            if (va > vb) return affSortDir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    $(document).on('click', '#affTable thead th[data-sort-col]', function () {
        var col = $(this).data('sort-col');
        if (affSortCol === col) {
            affSortDir = affSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            affSortCol = col;
            affSortDir = 'asc';
        }
        updateAffSortIcons();
        affCurrentPage = 1;
        renderAffTablePage();
    });

    $(document).on('input', '#affTableSearch', function () {
        var q = $(this).val().toLowerCase();
        affFilteredRows = q
            ? affAllRows.filter(function (r) {
                return (String(r.id) + (r.name||'') + (r.package||'') + (r.booking_status||'') + (r.via||'')).toLowerCase().indexOf(q) !== -1;
            })
            : affAllRows.slice();
        affCurrentPage = 1;
        renderAffTablePage();
    });

    function buildAffRow(r, i) {
        var status      = r.booking_status || '';
        var via         = r.via ? ' (' + r.via + ')' : '';
        var statusClass = status === 'Cancelled' ? 'text-orange-500 font-semibold' : 'text-green-600 font-semibold';
        var bg          = i % 2 === 0 ? '' : 'style="background:#f8faff;"';
        var bookingUrl  = bookingInfoUrl.replace(/\/0$/, '/' + r.id);
        return '<tr ' + bg + '>' +
            '<td class="px-5 py-3"><a href="' + bookingUrl + '" target="_blank" class="text-blue-600 font-medium hover:underline">' + r.id + '</a></td>' +
            '<td class="px-4 py-3 text-gray-700">' + (r.name || '—') + '</td>' +
            '<td class="px-4 py-3 text-gray-700">' + (r.package || '—') + '</td>' +
            '<td class="px-4 py-3 text-right text-gray-700">' + (r.tourists || 0) + '</td>' +
            '<td class="px-4 py-3 ' + statusClass + '">' + status + via + '</td>' +
            '<td class="px-4 py-3 text-right text-gray-800">' + affFmt(r.booking_amount) + '</td>' +
            '<td class="px-4 py-3 text-right text-gray-800">' + affFmt(r.refund_amount) + '</td>' +
            '<td class="px-5 py-3 text-right text-gray-800">' + affFmt(r.paid_amount) + '</td>' +
            '</tr>';
    }

    function renderAffTablePage() {
        var total  = affFilteredRows.length;
        var pages  = Math.ceil(total / affPageSize) || 1;
        affCurrentPage = Math.min(Math.max(affCurrentPage, 1), pages);
        var start  = (affCurrentPage - 1) * affPageSize;
        var end    = Math.min(start + affPageSize, total);
        var sorted = getAffSortedRows();
        var slice  = sorted.slice(start, end);

        if (total === 0) {
            $('#affTableBody').html('<tr><td colspan="8" class="py-8 text-center text-gray-300 text-sm">No data</td></tr>');
            $('#affPagination').addClass('hidden');
            return;
        }

        var html = '';
        $.each(slice, function (i, r) { html += buildAffRow(r, start + i); });
        $('#affTableBody').html(html);

        // Info text
        $('#affPagInfo').text('Showing ' + (start + 1) + '–' + end + ' of ' + total + ' entries');

        // Page buttons
        var btnHtml = '';
        var btnBase = 'px-3 py-1 rounded border text-xs font-medium ';
        var btnDefault = 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50';
        var styleActive = 'style="background:#3b6fb3;color:#fff;border-color:#1e3a5f;"';

        btnHtml += '<button class="' + btnBase + (affCurrentPage === 1 ? 'opacity-40 cursor-default ' + btnDefault : btnDefault) + '" data-affpage="prev">‹ Prev</button>';

        var pageWindow = 2;
        for (var p = 1; p <= pages; p++) {
            if (p === 1 || p === pages || (p >= affCurrentPage - pageWindow && p <= affCurrentPage + pageWindow)) {
                if (p === affCurrentPage) {
                    btnHtml += '<button class="' + btnBase + '" ' + styleActive + ' data-affpage="' + p + '">' + p + '</button>';
                } else {
                    btnHtml += '<button class="' + btnBase + btnDefault + '" data-affpage="' + p + '">' + p + '</button>';
                }
            } else if (p === affCurrentPage - pageWindow - 1 || p === affCurrentPage + pageWindow + 1) {
                btnHtml += '<span class="px-1 text-gray-400">…</span>';
            }
        }

        btnHtml += '<button class="' + btnBase + (affCurrentPage === pages ? 'opacity-40 cursor-default ' + btnDefault : btnDefault) + '" data-affpage="next">Next ›</button>';

        $('#affPagButtons').html(btnHtml);
        $('#affPagination').removeClass('hidden');
    }

    $(document).on('click', '[data-affpage]', function () {
        var val    = $(this).data('affpage');
        var pages  = Math.ceil(affFilteredRows.length / affPageSize) || 1;
        if (val === 'prev') { affCurrentPage = Math.max(1, affCurrentPage - 1); }
        else if (val === 'next') { affCurrentPage = Math.min(pages, affCurrentPage + 1); }
        else { affCurrentPage = parseInt(val, 10); }
        renderAffTablePage();
    });

    function affFmt(v) {
        if (typeof v === 'string') {
            return currencySymbol + v;
        }
        var n = parseFloat(v) || 0;
        return currencySymbol + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function loadAffiliatesData() {
        $.ajax({
            url: affiliatesMetricsUrl,
            type: 'GET',
            data: $.param({
                from: dashboardState.from,
                to:   dashboardState.to,
                tourPackageIds: dashboardState.tourPackageIds,
                paymentTypes: dashboardState.paymentTypes,
                thirdPartyPlatforms: dashboardState.thirdPartyPlatforms,
                requestFrom: dashboardState.requestFrom,
                bookingSources: dashboardState.bookingSources
            }),
            traditional: false,
            beforeSend: function () {
                showLoader();
                $('#affBookingAmount,#affRefundAmount,' +
                  '#affNetCard,#affNetCash,#affNetInvoiced,#affTotalNetCollected,' +
                  '#affNetTripAdvisor,#affNetHeadout,#affNetFareHarbor,#affTotalAffiliates,#affCancelBookings').addClass('opacity-50');
            },
            success: function (res) {
                if (!res.success) return;
                affLoaded = true;
                renderAffiliatesData(res.data);
            },
            complete: function () {
                hideLoader();
                $('#affBookingAmount,#affRefundAmount,' +
                  '#affNetCard,#affNetCash,#affNetInvoiced,#affTotalNetCollected,' +
                  '#affNetTripAdvisor,#affNetHeadout,#affNetFareHarbor,#affTotalAffiliates').removeClass('opacity-50');
            }
        });
    }

    function renderAffiliatesData(d) {
        $('#affBookingAmount').text(affFmt(d.booking_amount));
        $('#affRefundAmount').text(affFmt(d.refund_amount));
        $('#affNetCard').text(affFmt(d.net_card));
        $('#affNetCash').text(affFmt(d.net_cash));
        $('#affNetInvoiced').text(affFmt(d.net_invoiced));
        $('#affTotalNetCollected').text(affFmt(d.total_net_collected));
        $('#affNetTripAdvisor').text(affFmt(d.net_tripadvisor));
        $('#affNetHeadout').text(affFmt(d.net_headout));
        $('#affNetFareHarbor').text(affFmt(d.net_fareharbor));
        $('#affTotalAffiliates').text(Number(d.total_affiliates).toLocaleString());
        $('#affTotalBookings').text(Number(d.total_bookings).toLocaleString() + ' bookings');
        $('#affCancelBookings').text(Number(d.cancellation_bookings).toLocaleString());

        $('#affTableSearch').val('');
        affAllRows      = d.table_rows || [];
        affFilteredRows = affAllRows.slice();
        affCurrentPage  = 1;
        renderAffTablePage();
        updateAffSortIcons();
    }

});
