/**
 * this is used to invoke a new instance of the PromotionReport class in the report.blade template
 * @param {*} props 
 */
const promotionReportInit = (props) => {
    $('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForTransaction').css('display', 'none');
    $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForTransactionSummary').css('display', 'none');
    $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForaffiliateSeatsReport').css('display', 'none');
    $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForRefundDetails').css('display', 'none');
    $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForAddonsPackageReport').css('display', 'none');
    $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForAddonsPackageDetailsReport').css('display', 'none');
    $('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false });
    $('#datatableForActivityLogsReport').css('display', 'none');
    new PromotionReport(props);
}

/**
 * @class 
 * @description PromotionReport 
 * Revenue Report Load agg. function data
 * Load json data from /app/Repositories/Report/ReportRepository @promotionReport to populate a data table.
 * @param string endpont the endpoint to the promotion report.
 * @requires js: jquery, moment, showLoading, hideLoading, updateReportInfo, swal
 * @returns void
 */
class PromotionReport {
    constructor(props) {
        this.endpoint = props.endpoint;
        this.admin = props.admin;
        this.adminpath = (this.admin) ? "/admin" : "";
        this.tdCount = '4';
        this.tdWidth = '12.5%';
        this.operatorId = (!this.admin) ? props.operatorId : $("#tour_operator").val();
        this.promotionId = $("#promotion-data").val();
        this.packageId = $("#tour_package").val();
        this.dates = this.getDateRange();
        this.fromDate = this.dates.start_date;
        this.toDate = this.dates.end_date;
        this.url = this.endpoint.replace('_FROM_DATE_', this.fromDate).replace('_TO_DATE_', this.toDate);
        this.url += `/${this.operatorId}/${this.packageId}/${this.promotionId}`;
        this.title = 'Promotion Report';
        this.collection = [];
        if (PromotionReport.validateSelectedFields()) {
            this.init();
        }
    }

    /**
     * @function getDateRange
     * @description return the start and end date from the datepicker 
     * @returns {*} start and end date yyyy-mm-dd
     */
    getDateRange() {
        let d = $('#reportrange').data('daterangepicker');
        return { start_date: d.startDate.format('YYYY-MM-DD'), end_date: d.endDate.format('YYYY-MM-DD') }
    }

    /**
     * @function validateSelectedFields
     * @description make sure all the correct selection vals are set
     * @returns bool
     */
    static validateSelectedFields() {
        if (this.admin) {
            if ($('#tour_package').val() == '' || $('#tour_operator').val() == '') {
                swal('Please select a tour operator and a tour package', '', 'warning');
                return false;
            }
        } else {
            if ($('#tour_package').val() == '') {
                swal('Please select a tour package', '', 'warning');
                return false;
            }
        }

        return true;
    }

    /**
     * @function init
     * @description fetch data and display view. 
     * @returns void
     */
    init() {
        // display a loading spinner
        showLoading();

        // update the view ad a title to the report heading.
        updateReportInfo(`${this.title}`);
        updateActionBtn(downloadReport);

        // add ui elements 
        let mainView = $('#mainTableDiv');
        mainView.append("<div id='promotion-report-view'></div>");

        // fetch the data and render the promotion table
        $.get(this.url).done(this.renderTable.bind(this)).fail(this.onError).always(hideLoading);
    }

    /**
     * @function renderTable
     * @description do data calc and then display the data table.  
     * @param {*} data 
     * @returns void
     */
    renderTable(data) {
        this.collection = data;
        this.displayTable();
    }

    /**
     * @function displayTable
     * @description  display a data table of booking data.  
     * @returns void
     */
    displayTable() {
        let data = this.collection;

        var dateRange = reportDateRange();
        var totalTicketCostAmount = (data.total_ticket_cost_amount > 0) ? parseFloat(data.total_ticket_cost_amount).toFixed(2) : 0.00;
        var totalDiscountAmount = (data.total_discount_amount > 0) ? parseFloat(data.total_discount_amount).toFixed(2) : 0.00;
        var totalFinalAmount = (data.total_discount_amount > 0 && data.total_ticket_cost_amount > 0) ? parseFloat(data.total_ticket_cost_amount - data.total_discount_amount).toFixed(2) : parseFloat(data.total_ticket_cost_amount).toFixed(2);
        var html = "";

        html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader' style='margin-bottom: 10px;'>";

        html += "<thead><tr><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Date Range</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Created</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Total Tourists</th>";

        html += "<th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Total Infants</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Total Amount</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Total Amount Discounted</th><th class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>Total Final Amount</th></tr></thead><tbody>";

        html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MMM DD, YYYY') + " - " + moment(dateRange.toDate).format('MMM DD, YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MMM DD, YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + data.total_tourists + "</td>";

        html += "<td class='td-custom-css info-text-color fw-normal'>" + data.total_infants + "</td><td class='td-custom-css info-text-color fw-normal'>" + currency(totalTicketCostAmount).format() + "</td><td class='td-custom-css info-text-color fw-normal'>" + currency(totalDiscountAmount).format() + "</td> <td class='td-custom-css info-text-color fw-normal'>" + currency(totalFinalAmount).format() + "</td> </tr>";

        html += "</tbody></table></div>";

        $('#mainTableDiv').append(html);
        $('#affiliateReportHeader').DataTable({
            searching: false,
            paging:    false,
            info:      false,
            ordering:  false,
        });

        // Rate Categories Table
        var categories = data.rateCategories;
        if (categories && Object.keys(categories).length > 0) {
            var overallPax = 0, overallAmount = 0;
            var catHtml = "<div class='w-full overflow-y-auto mt-4 mb-2'><table class='w-full border-collapse text-sm'>";
            catHtml += "<thead><tr style='background:#1D4ED8;color:#fff;'>";
            catHtml += "<th class='px-4 py-2.5 font-medium text-left'>Rate Group</th>";
            catHtml += "<th class='px-4 py-2.5 font-medium text-left'>Tourist</th>";
            catHtml += "<th class='px-4 py-2.5 font-medium text-left'>Rate</th>";
            catHtml += "<th class='px-4 py-2.5 font-medium text-left'>Tax</th>";
            catHtml += "<th class='px-4 py-2.5 font-medium text-left'>Permit</th>";
            catHtml += "<th class='px-4 py-2.5 font-medium text-right'>Total</th>";
            catHtml += "</tr></thead><tbody>";

            Object.keys(categories).forEach(function(catKey) {
                var cat        = categories[catKey];
                var safeKey    = String(catKey).replace(/[^a-zA-Z0-9_]/g, '_');
                var catRowsId  = 'catRows-' + safeKey;
                overallPax    += (cat.totalPax    || 0);
                overallAmount += (cat.totalAmount || 0);

                catHtml += `<tr onclick="toggleRateCategory('${catRowsId}')" style='cursor:pointer;background:#EFF6FF;'>
                    <td class='px-4 py-2.5 font-semibold text-[#1F2937]' colspan='5'>
                        <span class='inline-flex items-center gap-2'>
                            <svg id='catChevron-${catRowsId}' xmlns='http://www.w3.org/2000/svg' class='w-4 h-4 text-[#6B7280] transition-transform duration-200' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/></svg>
                            ${cat.categoryName}
                        </span>
                    </td>
                    <td class='px-4 py-2.5 text-right'>
                        <span class='text-xs font-bold text-[#6B7280] mr-2'>${cat.totalPax} tourists</span>
                        <span class='font-bold text-[#1F2937]'>${currency(parseFloat(cat.totalAmount || 0).toFixed(2)).format()}</span>
                    </td>
                </tr>`;

                Object.keys(cat.rateGroups).forEach(function(grpKey) {
                    var grp = cat.rateGroups[grpKey];
                    catHtml += `<tr class='${catRowsId} border-t border-[#E5E7EB]'>
                        <td class='px-4 py-2.5 text-[#374151] pl-8'>${grp.rateFor}</td>
                        <td class='px-4 py-2.5 font-bold text-[#1F2937]'>${grp.pax}</td>
                        <td class='px-4 py-2.5 text-[#374151]'>${currency(parseFloat(grp.perPersonRate || 0).toFixed(2)).format()}</td>
                        <td class='px-4 py-2.5 text-[#374151]'>${currency(parseFloat(grp.perPersonTax || 0).toFixed(2)).format()}</td>
                        <td class='px-4 py-2.5 text-[#374151]'>${currency(parseFloat(grp.perPersonPermitFee || 0).toFixed(2)).format()}</td>
                        <td class='px-4 py-2.5 font-bold text-[#1F2937] text-right'>${currency(parseFloat(grp.totalAmount || 0).toFixed(2)).format()}</td>
                    </tr>`;
                });
            });

            catHtml += `<tr style='background:#DBEAFE;border-top:2px solid #93C5FD;'>
                <td class='px-4 py-2.5 font-bold text-[#1D4ED8] uppercase tracking-wide'>TOTAL</td>
                <td class='px-4 py-2.5 font-bold text-[#1F2937]'>${overallPax}</td>
                <td class='px-4 py-2.5 text-[#6B7280]'>-</td>
                <td class='px-4 py-2.5 text-[#6B7280]'>-</td>
                <td class='px-4 py-2.5 text-[#6B7280]'>-</td>
                <td class='px-4 py-2.5 font-bold text-[#1D4ED8] text-right'>${currency(overallAmount.toFixed(2)).format()}</td>
            </tr>`;
            catHtml += "</tbody></table></div>";
            $('#mainTableDiv').append(catHtml);
        }

        // append the table to the main view.
        var arraydata = [];
        var arrayKeysInternal = [];

        if (data.length != 0) {
            for (var main in data) {
                arraydata = data[main].bookings;
                arrayKeysInternal = data[main].promo_code;

                var tableId          = 'promoTable-' + main;
                var breakdownChildMap = {};
                var html1            = "<div class='w-full overflow-y-auto mt-2'>";

                if (arrayKeysInternal != null) {
                    var totalFinalAmountOfCode = (data[main].total_discount > 0 && data[main].ticket_cost > 0) ? parseFloat(data[main].ticket_cost - data[main].total_discount).toFixed(2) : parseFloat(data[main].ticket_cost).toFixed(2);
                    var usageUsed = arraydata.length;
                    var usageMax = parseInt(data[main].max_allowed_usages) || 0;
                    var usagePct = usageMax > 0 ? Math.min(Math.round((usageUsed / usageMax) * 100), 100) : 0;
                    var usageBarColor = usagePct >= 90 ? '#DC2626' : usagePct >= 60 ? '#F59E0B' : '#16A34A';
                    var expiryDays = null, expiryLabel = '', expiryBg = '', expiryText = '', expiryDate = '';
                    if (data[main].end_date) {
                        var endDate = moment(data[main].end_date);
                        expiryDays = endDate.diff(moment().startOf('day'), 'days');
                        expiryDate = endDate.format('MMM DD, YYYY');
                        if (expiryDays < 0) {
                            expiryLabel = 'Expired'; expiryBg = '#FEE2E2'; expiryText = '#DC2626';
                        } else if (expiryDays === 0) {
                            expiryLabel = 'Expires today'; expiryBg = '#FEF3C7'; expiryText = '#D97706';
                        } else if (expiryDays <= 7) {
                            expiryLabel = expiryDays + 'd left'; expiryBg = '#FEF3C7'; expiryText = '#D97706';
                        } else {
                            expiryLabel = expiryDays + 'd left'; expiryBg = '#DCFCE7'; expiryText = '#16A34A';
                        }
                    }

                    // Promo card is outside the <table> to keep valid HTML
                    html1 += `
                    <div class='flex items-center justify-between flex-wrap gap-3 bg-white border border-[#E5E7EB] rounded-xl px-5 py-4 mb-3 shadow-sm'>
                        <div class='flex items-center gap-3'>
                            <div class='bg-[#FFF3ED] rounded-lg px-4 py-2'>
                                <span class='text-xs text-[#9CA3AF] uppercase tracking-wide block'>Promo Code</span>
                                <span class='text-base font-bold text-[#E4622A] tracking-widest'>${arrayKeysInternal}</span>
                            </div>
                            <div class='flex flex-col gap-1 min-w-[140px] bg-[#F8FAFC] border border-[#E5E7EB] rounded-lg px-3 py-2'>
                                <span class='text-xs font-semibold text-[#6B7280] uppercase tracking-wide'>Usage</span>
                                ${usageMax > 0 ? `<span class='text-base font-bold' style='color:${usageBarColor}'>${usageUsed} / ${usageMax}</span>
                                <div class='w-full bg-[#E5E7EB] rounded-full h-2'>
                                    <div style='width:${usagePct}%;background-color:${usageBarColor}' class='h-2 rounded-full transition-all duration-300'></div>
                                </div>` : `<span class='text-base font-bold text-[#374151]'>${usageUsed} used</span>`}
                            </div>
                            ${expiryDays !== null ? `<div class='flex flex-col gap-1 bg-[#F8FAFC] border border-[#E5E7EB] rounded-lg px-3 py-2'>
                                <span class='text-xs font-semibold text-[#6B7280] uppercase tracking-wide'>Expires</span>
                                <span class='text-sm font-bold text-[#374151]'>${expiryDate}</span>
                                <span class='inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold' style='background-color:${expiryBg};color:${expiryText}'>${expiryLabel}</span>
                            </div>` : ''}
                        </div>
                        <div class='flex flex-wrap gap-4'>
                            <div class='text-center'>
                                <span class='text-xs text-[#9CA3AF] block'>Total Bookings</span>
                                <span class='text-lg font-semibold text-[#374151]'>${arraydata.length}</span>
                            </div>
                            <div class='w-px bg-[#E5E7EB]'></div>
                            <div class='text-center'>
                                <span class='text-xs text-[#9CA3AF] block'>Total Amount</span>
                                <span class='text-lg font-semibold text-[#16A34A]'>${currency(parseFloat(data[main].ticket_cost || 0).toFixed(2)).format()}</span>
                            </div>
                            <div class='w-px bg-[#E5E7EB]'></div>
                            <div class='text-center'>
                                <span class='text-xs text-[#9CA3AF] block'>Total Discounted Amount</span>
                                <span class='text-lg font-semibold text-[#DC2626]'>${currency(parseFloat(data[main].total_discount || 0).toFixed(2)).format()}</span>
                            </div>
                            <div class='w-px bg-[#E5E7EB]'></div>
                            <div class='text-center'>
                                <span class='text-xs text-[#9CA3AF] block'>Total Final Amount</span>
                                <span class='text-lg font-semibold text-[#16A34A]'>${currency(parseFloat(totalFinalAmountOfCode).toFixed(2)).format()}</span>
                            </div>
                        </div>
                    </div>
                    `;

                    html1 += "<table class='table table-bordered table-striped table-hover w-full border-collapse text-left' id='" + tableId + "' style='margin-bottom:10px;'>";
                    html1 += "<thead><tr><th class='th-custom-css'>Booking</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css'>Customer Name</th><th class='th-custom-css'>Status</th><th class='th-custom-css'>Tourists</th>";
                    html1 += "<th class='th-custom-css'>Infants</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Discount</th><th class='th-custom-css'>Final Amount</th></tr></thead><tbody>";
                }

                for (var children in arraydata) {
                    var booking     = arraydata[children];
                    var totalAmount = (booking.ticket_cost > 0) ? parseFloat(booking.ticket_cost).toFixed(2) : 0.00;
                    var discount    = (booking.discount > 0) ? parseFloat(booking.discount).toFixed(2) : 0.00;
                    var finalAmount = (booking.discount > 0 && booking.ticket_cost > 0) ? parseFloat(booking.ticket_cost - booking.discount).toFixed(2) : parseFloat(booking.ticket_cost).toFixed(2);
                    var hasBreakdown = Array.isArray(booking.rate_breakdown) && booking.rate_breakdown.length > 0;
                    var bdId = 'bd-' + main + '-' + children;

                    var rowAttrs = hasBreakdown
                        ? "data-bd-id='" + bdId + "' onclick=\"toggleRateGroup('" + bdId + "')\" style='cursor:pointer;'"
                        : "";
                    html1 += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out' " + rowAttrs + ">";
                    html1 += "<td class='td-custom-css'><a target=_blank href=" + bookingInfoUrl + booking.tour_booking_id + " onclick='event.stopPropagation()'>" + booking.tour_booking_id + "</a></td>";
                    html1 += "<td class='td-custom-css'>" + moment(booking.date, 'YYYY-MM-DD').format('MMM DD, YYYY') + " @ " + booking.time_from + "</td>";
                    html1 += "<td class='td-custom-css'>" + moment(booking.created_at, 'YYYY-MM-DD').format('MMM DD, YYYY') + "</td>";
                    html1 += "<td class='td-custom-css'>" + booking.customer + "</td>";
                    html1 += "<td class='td-custom-css success-text-color'>" + booking.status + "</td>";
                    html1 += "<td class='td-custom-css'>" + booking.tourists + "</td>";
                    html1 += "<td class='td-custom-css'>" + booking.infants + "</td>";
                    html1 += "<td class='td-custom-css'>" + currency(totalAmount).format() + "</td>";
                    html1 += "<td class='td-custom-css'>" + currency(discount).format() + "</td>";
                    html1 += "<td class='td-custom-css'>";
                    if (hasBreakdown) {
                        html1 += "<span class='flex items-center justify-between gap-2'>" + currency(finalAmount).format();
                        html1 += "<svg id='chevron-" + bdId + "' xmlns='http://www.w3.org/2000/svg' class='w-4 h-4 text-[#6B7280] transition-transform duration-200 flex-shrink-0' fill='none' viewBox='0 0 24 24' stroke='currentColor'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/></svg></span>";
                    } else {
                        html1 += currency(finalAmount).format();
                    }
                    html1 += "</td></tr>";

                    // Breakdown stored separately for DataTables child rows (not inline <tr>)
                    if (hasBreakdown) {
                        var rateRows = '';
                        booking.rate_breakdown.forEach(function(rb) {
                            var subtotal = ((parseFloat(rb.rate) || 0) + (parseFloat(rb.tax) || 0) + (parseFloat(rb.permit_fee) || 0)) * (parseInt(rb.pax) || 0);
                            rateRows += `<tr class='border-t border-[#E5E7EB]'>
                                <td class='px-4 py-2.5 text-sm font-semibold text-[#1F2937]'>${rb.rate_for || 'Unknown'}</td>
                                <td class='px-4 py-2.5 text-sm text-[#374151] font-bold'>${rb.pax}</td>
                                <td class='px-4 py-2.5 text-sm text-[#374151]'>${currency(parseFloat(rb.rate || 0).toFixed(2)).format()}</td>
                                <td class='px-4 py-2.5 text-sm text-[#374151]'>${currency(parseFloat(rb.tax || 0).toFixed(2)).format()}</td>
                                <td class='px-4 py-2.5 text-sm text-[#374151]'>${currency(parseFloat(rb.permit_fee || 0).toFixed(2)).format()}</td>
                                <td class='px-4 py-2.5 text-sm font-bold text-[#1F2937]' colspan='4'>${currency(subtotal.toFixed(2)).format()}</td>
                            </tr>`;
                        });
                        breakdownChildMap[bdId] = `<table class='w-full'>
                            <thead>
                                <tr class='bg-[#F1F5F9]'>
                                    <th class='px-4 py-2 text-xs font-medium text-[#6B7280] text-left'>Rate Group</th>
                                    <th class='px-4 py-2 text-xs font-medium text-[#6B7280] text-left'>Pax</th>
                                    <th class='px-4 py-2 text-xs font-medium text-[#6B7280] text-left'>Rate / Person</th>
                                    <th class='px-4 py-2 text-xs font-medium text-[#6B7280] text-left'>Tax / Person</th>
                                    <th class='px-4 py-2 text-xs font-medium text-[#6B7280] text-left'>Permit Fee / Person</th>
                                    <th class='px-4 py-2 text-xs font-medium text-[#6B7280] text-left' colspan='4'>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>${rateRows}</tbody>
                        </table>`;
                    }
                }
                html1 += "</tbody></table></div>";
                $('#mainTableDiv').append(html1);

                var dt = $('#' + tableId).DataTable({
                    searching:  true,
                    ordering:   true,
                    paging:     true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
                });

                Object.keys(breakdownChildMap).forEach(function(bdId) {
                    var $row = $('[data-bd-id="' + bdId + '"]');
                    if ($row.length) {
                        dt.row($row).child(breakdownChildMap[bdId]);
                    }
                });
            }
        }
    }

}

const toggleRateCategory = (rowClass) => {
    var $rows    = $('tr.' + rowClass);
    var $chevron = $('#catChevron-' + rowClass);
    var isOpen   = $rows.first().is(':visible');
    $rows.toggle(!isOpen);
    if ($chevron.length) {
        $chevron[0].style.transform = isOpen ? 'rotate(-90deg)' : 'rotate(0deg)';
    }
}

const toggleRateGroup = (id) => {
    const $tr     = $('[data-bd-id="' + id + '"]');
    const chevron = document.getElementById('chevron-' + id);
    if (!$tr.length) return;
    const dt  = $tr.closest('table').DataTable();
    const row = dt.row($tr);
    if (row.child.isShown()) {
        row.child.hide();
        if (chevron) chevron.style.transform = 'rotate(0deg)';
    } else {
        row.child.show();
        if (chevron) chevron.style.transform = 'rotate(180deg)';
    }
}

/**
 * @description Download the Promotion Report from /app/Repositories/Report/ReportRepository @promotionReport to populate a data table.
 * @requires string url rotue "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'promotion_report_export')}}"
 * @returns void
 */
const promotionReportDownload = (url) => {
    const dpiker = $('#reportrange').data('daterangepicker');
    const promotionId = $("#promotion-data").val();
    const from = dpiker.startDate.format('YYYY-MM-DD');
    const to = dpiker.endDate.format('YYYY-MM-DD');
    if (PromotionReport.validateSelectedFields()) {
        const params = `tourOperatorId=${$('#tour_operator').val()}&tourPackageId=${$("#tour_package").val()}&fromDate=${from}&toDate=${to}&promotionId=${promotionId}&csv=1`;
        console.log(url);
        window.location = `${url}?${params}`;
    }
}
