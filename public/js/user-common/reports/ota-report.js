/**
 * this is used to invoke a new instance of the otaReport class in the report.blade template
 * @param {*} props 
 */
const otaReportInit = (props) => {
    $('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    $('#datatableForTransaction').css('display','none');
    $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    $('#datatableForTransactionSummary').css('display','none');
    $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    $('#datatableForaffiliateSeatsReport').css('display','none');
    $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    $('#datatableForRefundDetails').css('display','none');
    $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    $('#datatableForAddonsPackageReport').css('display','none');
    $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    $('#datatableForAddonsPackageDetailsReport').css('display','none');
    $('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
	$('#datatableForActivityLogsReport').css('display','none');
    new OTAsReport(props);
}

/**
 * @class 
 * @description OTAsReport 
 * OTAs Report Load agg. function data
 * Load json data from /app/Repositories/Report/ReportRepository@otaBookingReport to populate a data table.
 * @param string endpont the endpoint to the OTAs report.
 * @requires js: jquery, moment, showLoading, hideLoading, updateReportInfo, swal
 * @returns void
 */
class OTAsReport {
    constructor(props) {
        this.endpoint = props.endpoint;
        this.admin = props.admin;
        this.adminpath = (this.admin) ? "/admin" : "";
        this.tdCount = '10';
        this.tdWidth = '10%';
        this.operatorId = (!this.admin) ? props.operatorId : $("#tour_operator").val();
        this.packageId = $("#tour_package").val();
        this.dates = this.getDateRange();
        this.fromDate = this.dates.start_date;
        this.toDate = this.dates.end_date;
        this.otaPlatform = $("#otaPlatform").val();
        this.otaPlatforms = otaPlatforms;
        this.viewBy = $("#viewby-select").val(); //services rendered || pending bookings
        this.dateType = (this.viewBy === 'purchasedate') ? 'purchase_date' : 'tour_date';
        this.url = this.endpoint.replace('_FROM_DATE_', this.fromDate).replace('_TO_DATE_', this.toDate);
        this.url += `/${this.operatorId}/${this.packageId}`;
        if(this.otaPlatform) {
            this.url += `/${this.otaPlatform}`;
        }
        this.title = ((!this.otaPlatform) ? 'OTAs ' : `${this.otaPlatform}`) + ' List By';
        this.collection = [];
        this.state = { currentView: 'total', reload: true, totalBy: 'month', totals_toggle: 'show' }
        if (OTAsReport.validateSelectedFields( this.fromDate, this.toDate )) {
            this.init();
        }
    }

    /**
     * @function getDateRange
     * @description return the start and end date from the datepicker 
     * @returns {*} start and end date yyyy-mm-dd
     */
    getDateRange(){
        let d = $('#reportrange').data('daterangepicker');
        return {start_date: d.startDate.format('YYYY-MM-DD'), end_date:  d.endDate.format('YYYY-MM-DD')}
      }

    /**
     * @function validateSelectedFields
     * @description make sure all the correct selection vals are set
     * @returns bool
     */
   static validateSelectedFields(fromDate, toDate) {

        const startDate = moment(fromDate);
        const endDate = moment(toDate);
        const days = endDate.diff(startDate , 'days');

        if ( days > 92){
            swal('Please Select A Date Range Less Then Or Equal To 3 Months', '', 'warning');
            return false;
        }
    
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

        // assign event listners 
        this.eventListeners();

        // display a loading spinner
        showLoading();

        // update the view ad a title to the report heading.
        let dateDisplay = {from: this.fromDate, to: this.toDate};
        updateReportInfo(`${this.title} ${capitalized(this.state.totalBy)}`, dateDisplay);
        updateActionBtn(downloadReport);

        // add ui elements 
        let mainView = $('#mainTableDiv');
        mainView.html("<div id='ota-report-summary-view'></div>");
        mainView.append(this.collapseExapandAllBtnTemplate());
        mainView.append(this.filtersModalTemplate());
        mainView.append(this.detailsModalTemplate());
        mainView.append("<div class='my-2' id='ota-report-view'></div>");

        // fetch the data and render the ota table
        $.get(this.url + `?dateType=${this.dateType}`).done(this.renderTotals.bind(this)).fail(this.onError).always(hideLoading);
     
    }

    /**
     * @function events listeners for ui 
     * @description
     * assign click events
     * @returns void
     */
    eventListeners() {
        $(document).off("keyup", "#nat-collapse-search").on("keyup", "#nat-collapse-search", this.searchTable.bind(this));
        $(document).off('click', '.ota-report-reload').on('click', '.ota-report-reload', this.reloadView.bind(this));
        $(document).off('click', '.ota-display-totals').on('click', '.ota-display-totals', this.displayTotals.bind(this));
        $(document).off('click', '.ota-display-table').on('click', '.ota-display-table', this.displayTable.bind(this));
        $(document).off('change', '#ota-total-by-select').on('change', '#ota-total-by-select', this.updateTotalBy.bind(this));
        $(document).off('click', '.ota-report-row-detail').on('click', '.ota-report-row-detail', this.displayDetails.bind(this));
    }

    /**
     * This function is triggered when a user clicks on the refresh icon btns. 
     * @returns void
     */
    reloadView() {
        let filters = {};
        $(".ota-filter:checkbox:checked").each((_idx, ele) => {
            let el = $(ele);
            let tp = el.data('type');
            if (!_.has(filters, tp)) { filters[tp] = []; }
            filters[tp].push(encodeURIComponent(el.val()));
        });

        filters['sort'] = [$('input[name="otaReportSortOption"]:checked').val()];
        let query = `?filters=1&dateType=${this.dateType}`
        for (let key in filters) {
            if (filters[key].length > 0) {
                query += `&${key}=${filters[key].join("|")}`
            }
        }

        $("#ota-report-filter-modal").modal("hide");
        showLoading();
        let cb = this.renderTotals.bind(this);
        if (this.state.currentView == 'table') {
            cb = this.renderTable.bind(this);
        }else if(this.state.currentView == 'total'){
            cb = this.renderTotals.bind(this);
        }
        $.get(this.url + query).done(cb).fail(this.onError).always(hideLoading);
    }

    /**
     * Helper function to make sure you are dealing with a float value. 
     * @param {*} num the value from the database 
     * @param {*} alt a default value if the num var is a NAN
     * @returns float
     */
    parseAmt(num, alt=0) {
        let amt = 0;
        try {
            num = parseFloat(num.toString().replace(/[^0-9\.]/gi,''));
            amt = num;
        } catch (e) {
            amt = 0;
        }
        if(amt <= 0 ||  isNaN(amt)){
            amt = parseFloat(alt);
        }
        return amt;
    }

    /**
     * @function updateTotalBy
     * @description This will show the data by: week|month|day
     * it sets a class var that will tell the views below how to 
     * aggragate the data when loaded.
     * @returns void 
     */
    updateTotalBy() {
        this.state.totalBy = $('#ota-total-by-select').val();
        if (this.state.currentView === 'total') {
            this.displayTotals();
        }else if( this.state.currentView === 'table' ){
            this.displayTable();
        }
    }

    /**
     * @function renderTotals
     * @description Display the rev table. 
     * @param {*} data 
     */
    renderTotals(data) {
        this.state.currentView = 'total';
        this.collection = data;
        this.renderRevenueSumTemplate(this.collection.sums);
        this.displayTotals();
    }

    /**
       * @function displayTotals
       * @description Display the table view if the data is already loaded 
       * @param {*} data 
    */
    displayTotals() {
        this.state.currentView = 'total';
        this.toggleControls();
        this.renderyTotalsView(this.collection.data);
    }

    /**
     * @description Display a drop down accordion with nested tables
     * @param {*} data 
     */
    renderyTotalsView(data) {
        $("#ota-report-view").html("loading...");
        let collection = {};
        let grp = _.groupBy(data, this.getGroupByField());
        let view = [];
        let total = 0;
        let count = 0;
        let amt = 0;
        showLoading()
        for (let key in grp) {

            let data_by_date = grp[key];
            
            if (!_.has(collection, key)) {
                collection[key] = { 
                     rows: [], summary: { 
                                'row_count': data_by_date.length, 
                                'total': 0, 
                                'date':  data_by_date[0].date, 
                                'week':  data_by_date[0].week, 
                                'month': data_by_date[0].month, 
                                'year':  data_by_date[0].year 
                } };
            }

            data_by_date.forEach(ele => {
                collection[key].rows.push(this.collapseDataRowTemplate(key, ele));
                amt = parseFloat(ele.net_revenue);
                collection[key].summary.total += amt;
                total += amt;
                count += 1;
            });

        }

        for (let k in collection) {
            let title = this.getDisplayName(collection[k].summary);
            view.push(this.collapseTemplate({
                title: title, id: `revdom-${k}`,
                count: collection[k].rows.length,
                total: dollarNumFmt( collection[k].summary.total ),
                html: this.tableTemplate(k, collection[k].rows.join("\n"),
                    this.collapseTFootTemplate(title,
                        dollarNumFmt(collection[k].summary.total)))
            }));
        }
        $("#ota-report-view").html(view.join("\n"));
        $("#ota-report-view").append(`<div class="flex items-center justify-between"><small class="font-medium text-black text-sm text-muted">Total Bookings:(${count})</small> <small class="font-medium text-black text-sm text-success"><strong>${dollarNumFmt(total)}</strong></small></div>`);
        hideLoading();
        view = [];
        collection = {};
    }

    /**
     * @function renderTable
     * @description do data calc and then display the data table.  
     * @param {*} data 
     * @returns void
     */
    renderTable(data){
        this.collection = data;
        this.renderRevenueSumTemplate(this.collection.sums);
        this.displayTable();
    }

    /**
     * @function displayTable
     * @description  display a data table of booking data.  
     * @returns void
     */
    displayTable() {
        this.toggleControls('hide');
        this.state.currentView = 'table'
        let data = this.collection.data;
        // append the table to the main view.
        let dom_id = 'ota-report-data-table';
        $("#ota-report-view").html(dataTableTemplate(dom_id));
        const bookingLink = (data, _type, _row, _meta)=>{ return `<a href='${this.adminpath}/tour-operator/booking/${data}' target='_blank'>${data}</a>`; };
        // declare the columns to add to the data table.
        let cols = [
        { data: 'booking_id', title: 'Booking ID', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm', render: bookingLink},
        { data: 'reference_id', title: 'Reference ID', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'},
        { data: 'booking_date', title: 'Purchase Date', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' },
        { data: 'tour_date', title: 'Tour Date', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' },
        { data: 'tour_time', title: 'Tour Time', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' },
        { data: 'tourists', title: 'Tourists', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' },
        { data: 'trans_type', title: 'Via', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm', render: (_data, _type, row, _meta )=>this.renderTransactionType(row) },
        { data: 'status', title: 'Status', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'},
        { data: 'gross_revenue', render: (gross_revenue) => { return currency(gross_revenue).format();}, title: 'Gross Total', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'},
        { data: 'refund_total', render:  (refund_total) => {return currency(refund_total).format();},title: 'Refund', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'},
        { data: 'net_revenue', render: (net_revenue) => { return currency(net_revenue).format();}, title: 'Net Total', className: 'px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'}]        
        let btns = ['excelHtml5','csvHtml5','pdfHtml5']
        // render the data table
        $(`#${dom_id}`).DataTable({ data: data, dom: 'Bfrtip', buttons: btns, pageLength: 100, columns: cols, fixedHeader: true, info: false, order: [[0, 'desc']] });
    }

    /**
     * @function searchTable
     * @description search the dom in the collection
     * @param evt search input 
     * @returns void
     */
    searchTable(evt) {
        let term = evt.target.value.toString().toLowerCase().trim();
        if(this.searchtimeout){
            clearTimeout(this.searchtimeout);
        }
        this.searchtimeout = setTimeout(()=>this.searchInit(term), 800);
    }

    /**
     * @function searchInit
     * @description Search the totals display if the setTimeout is up
     * @param string term the keyword to search the dom by
     * @returns void
     */
    searchInit(term){
        let rows = $("tr.search-row");
        let found = 0;
        let tfoot = {};
        if (term == "") {
            $.each(rows, (_idx, tr) => {
                let id = $(tr).data('id');
                $("#total-" + id).show();
                $(tr).show();
            });
            return;
        }

        $.each(rows, (_idx, tr) => {
            let id = $(tr).data('id');
            found = 0;
            $(tr).find('td').each((_index, element) => {
                let val = $(element).data('val').toString().toLowerCase().trim();
                let exp = val.includes(term);
                found += exp ? 1 : 0;
                if (found) { return true; }
            });
            (!_.has(tfoot, id)) ? tfoot[id] = found : tfoot[id] += found;
            (found > 0) ? $(tr).show() : $(tr).hide();
        });
        for (let key in tfoot) {
            (tfoot[key] > 0) ? ($("#total-" + key).hide()) : $("#total-" + key).show();
            //(tfoot[key] > 0) ? ($("#nat-revdom-" + key).show()) : $("#nat-revdom-" + key).hide();
        }

    }

    /**
     * @function getGroupByField
     * @description set the group by agg. to update the view
     * @returns string group by string week|day|year
     */
    getGroupByField() {
        let group_by = 'mn_yr';
        if (this.state.totalBy === 'week') {
            group_by = 'wk_mn_yr';
        }
        if (this.state.totalBy === 'day') {
            group_by = 'date';
        }
        $("#reportHeadingPrintDiv").html( `${this.title} ${capitalized( this.state.totalBy )}` )
        return group_by;
    }

    /**
     * @function getDisplayName
     * @description update the report title based on the group by title
     * @returns void
     */
    getDisplayName(row) {
        if (this.state.totalBy === 'month') {
            return `${getMonthByInt(row.month)} ${row.year}`;
        }
        if (this.state.totalBy === 'week') {
            return `Week(${row.week}) ${getMonthByInt(row.month, true)} ${row.year}`;
        }
        if (this.state.totalBy === 'day') {
            return `${row.date}`;
        }
    };

    /**
     * @function displayDetails
     * @description view the history details if clicked on for the totals view
     * @returns void
     */
    displayDetails(evt){
        let booking_id = $(evt.target).data('id');
        let url = `${this.adminpath}/tour-operator/booking/history-and-transactions/${booking_id}`;
        showLoading();
        $.get(url).done(this.viewBookingHistory.bind(this)).fail(this.onError);
    }

    /**
     * @function viewBooking
     * @description render the standard booking view
     * @returns void
     */
    viewBookingHistory(data){
        hideLoading();
        let id = data.id;
        let row = this.collection.data.filter((d)=>d.booking_id === id);
        let html = bookingDetailsAsyncView( data, row, id );
        $("#ota-history-modal-body").html(html);
        $("#ota-report-booking-history-modal").modal("toggle");
        html = "";
    }

    /**
     * @function onError
     * @description display error messages 
     * @returns void
     */
    onError(err) {
        swal({
			title: "Error Retrieving Data",
			text: "Max Response Size Exceeded While Retrieving Data! Please narrow the Date Rage and search again. ",
			icon: "warning",
			buttons: {
				yes: {
					text: 'Dismiss',
					className: 'btn-success btn-sm',
				}
			}
		});
        hideLoading();
    }

    /**
     * ---------------  HTML VIEW TEMPLATES BELOW ---------------
     */
    tableTemplate(id, data, total) {
        return `<div class="w-full overflow-y-auto mt-2">
        <table class="table table-sm table-hover w-full border-collapse text-left">
            <thead class="nat-header-grp w-full border-collapse text-left">
                <tr style="background-color:#F5F5F5;">
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Booking ID</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Reference ID</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Purchase Date</th>
                    <th width="12%" class="nat-header nat-no-border th-custom-css">Tour Date</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Tour Time</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Tourists</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Via</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Status</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Amount</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Refund</th>
                    <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Net</th>
                </tr>
            </thead>
            <tbody class="table-body">
                ${data}
            </tbody>
            <tfoot id="total-${id}">
                ${total}
            </tfoot>
        </table>
        </div>`;
    }

    collapseTemplate(props) {
        let viewstate = (this.state.totals_toggle === 'show') ? ['collapsed', ''] : ['', 'show']; 
        return `<div class="nat-collapse mb-2">
                <div class="nat-accordion form-custom-input flex items-center justify-between ${viewstate[0]}" data-bs-target="#nat-${props.id}" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="nat-${props.id}">
                    <div class="flex items-center"><div class="icon flex mr-2"><svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#9CA3AF" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg></div><span> <span>${props.title}</span> <span class="text-muted"> (bookings: ${props.count})</span></span></div>
                    <div class="float-end"> <span class="me-2">  ${props.total} </span> </div>
                </div>
                <div class="collapse ${viewstate[1]}" id="nat-${props.id}">
                    ${props.html} 
                </div>
            </div>`;
    }

    collapseTFootTemplate(name, total) {
        return `<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'>
                    <td class="td-custom-css" colspan="${this.tdCount}">
                        Total for ${name}
                    </td> 
                    <td class="td-custom-css text-success">
                        <strong>${total}</strong>
                    </td>
                </tr>`;
    }

    tableHeaderTemplate() {
        return `
        <div class="vis-toggle w-full overflow-y-auto mt-2" id="rev-report-table-header">
        <table class="table table-borderless w-full border-collapse text-left">
            <thead class="nat-header-grp w-full border-collapse text-left" >
                    <tr style="background-color:#F5F5F5;">
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Booking ID</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Reference ID</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Purchase Date</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Tour Date</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Tour Time</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Tourists</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Via</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Status</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Amount</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Refund</th>
                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css" style="border-right:none; border-left:none;" >Net</th>
                    </tr>
            </thead>
        </table>
    </div>`;
    }

    /**
     * @function renderTransactionType
     * @param {*} ele 
     * @returns String
     */
    renderTransactionType(ele){
        let _type = ele.trans_type;
        if (_type === "Invoiced"){
            if (ele.billable){
                _type = ` ${_type} <span class="text-warning">(billable)</span>`;
            }
        }
        return _type;
    }

    collapseDataRowTemplate(id, ele) {
        return `<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out search-row" data-id="${id}" > 

                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.booking_id}">
                    <i class="fa fa-plus-circle ota-report-row-detail" data-id="${ele.booking_id}" size="xs"></i> |  <a href='${this.adminpath}/tour-operator/booking/${ele.booking_id}' target='_blank'>${ele.booking_id}</a>
                    </td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.reference_id}">${ele.reference_id}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.booking_date}">${ele.booking_date}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.tour_date}">${ele.tour_date}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.tour_time}">${ele.tour_time}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.tourists}">${ele.tourists}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.trans_type}">${this.renderTransactionType(ele)}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.status}">${ele.status}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.gross_revenue}">${dollarNumFmt(ele.gross_revenue)}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.refund_total}">${dollarNumFmt(ele.refund_total)}</td>
                    <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.net_revenue}">${dollarNumFmt( (ele.state === "Cancelled") ? 0.00 : ele.net_revenue)}</td> 
                </tr>`
    }

    collapseExapandAllBtnTemplate() {

        $(document).off('click', ".ota-report-expand-all").on("click", ".ota-report-expand-all", (evt) => {
            let ele = $(evt.target);
            let act = (ele.data('state') === 'hide') ? 'show' : 'hide'
            $('#ota-report-view .collapse').collapse(ele.data('state'));
            ele.html(act === 'hide' ? 'Hide All' : 'Show All');
            ele.data('state', act);
            this.state.totals_toggle = act;
        });

        $(document).off('click', ".ota-display-filters").on("click", ".ota-display-filters", (evt) => {
            $("#ota-report-filter-modal").modal("toggle");
        });


        return `
        <div class="mb-3 h-20 flex items-center justify-between">
            <div class="flex items-center">
                <input class="disable-toggle nat-sm-input form-custom-input mr-2" type="text" placeholder="Search" id="nat-collapse-search" value="" />
                <select id="ota-total-by-select" class="nat-sm-input form-custom-input bg-[#FAFAFA]">
                    <option value="day">Day</option>
                    <option value="week">Week</option>
                    <option value="month" selected="">Month</option>
                </select>
            </div>
            <div class="flex items-center">
                <div class="btn-group ota-report flex items-center mr-2" role="group" aria-label="Basic example">
                    <button class="btn btn-sm ota-display-filters btn-info-small mr-2" title="Filter Data" onclick="this.style.color='blue';"><i class="fas fa-funnel-dollar"></i></button>
                    <button class="btn btn-sm ota-display-totals btn-info-small mr-2" title="Display Totals" ><i class="fas fa-bars"></i></button>
                    <button class="btn btn-sm ota-display-table btn-info-small" title="Display Table" ><i class="fas fa-table"></i></button>
                </div>
                <button class="btn btn-sm btn-default disable-toggle float-end ota-report-expand-all btn-primary-small" data-state="show">Show All</button>
            </div>
        </div>`;
    }

    filtersModalTemplate() {
        let platforms = [];

        let paymentDisplay = (pt) => {
            return `<input id="ota-filter-${pt}" class="ota-filter" data-type="payment_type"  type='checkbox' checked value="${pt}" /> <label for="ota-filter-${pt}">${pt}</label> <br />`;
        }

        if (!this.otaPlatform || this.otaPlatform.length === 0) {
            platforms = this.otaPlatforms || [];
        } else {
            platforms = [this.otaPlatform];
        }

        return `<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="ota-report-filter-modal" tabindex="-1" aria-labelledby="ota-report-filter-modal-label" aria-hidden="true"
                data-mdb-backdrop="true"
                data-focus-on="input:first"
                data-mdb-keyboard="true">
                <div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="ota-report-filter-modal-label">Filter List</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                    </div>
                    <div class="modal-body p-4" id="ota-modal-view">
                        <form class="form-control">
                            <div class="form-group">
                                ${platforms.map((pt) => paymentDisplay(pt)).join("\n")}
                            </div>

                            <div class="form-group" style="display: none">
                            <span class="me-2"> Sort Date: </span>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="otaReportSortOption" id="otaReportASC" value="ASC" />
                                    <label class="form-check-label" for="otaReportASC">Ascending</label>
                                </div>

                                <div class="form-check form-check-inline" >
                                    <input class="form-check-input" type="radio" name="otaReportSortOption" id="otaReportDESC" value="DESC" checked />
                                    <label class="form-check-label" for="otaReportDESC">Descending</label> 
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer flex items-center px-4 pb-4">
                    <button type="button" class="btn btn-primary ota-report-reload btn-primary-small w-full">Filter Results</button>
                    <button type="button" class="btn btn-secondary btn-light-small ml-2 w-full" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
                </div>
                </div>
                </div>`;
        }

    detailsModalTemplate() {

        return `<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="ota-report-booking-history-modal" tabindex="-1" aria-labelledby="ota-report-booking-history-modal-label" aria-hidden="true"
                data-mdb-backdrop="true"
                data-focus-on="input:first"
                data-mdb-keyboard="true">
                <div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="ota-report-booking-history-modal-label">Booking Detail</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                    </div>
                    <div class="modal-body p-4" id="ota-history-modal-body">

                    </div>
                </div>
                </div>
                </div>
                </div>`;
    }

    renderRevenueSumTemplate(data) {
        $('#ota-report-summary-view').html(`
        <div w-full overflow-y-auto mt-2">
            <table class="table table-sm table-hover w-full border-collapse text-left">
                <thead>
                    <tr>
                        <th class="th-custom-css whitespace-nowrap">Date Range</th>
                        <th class="th-custom-css whitespace-nowrap">Created</th>
                        <th class="th-custom-css whitespace-nowrap">Tourists</th>
                        <th class="th-custom-css whitespace-nowrap">Amount Paid</th>
                        <th class="th-custom-css whitespace-nowrap">Total Refund</th>
                        <th class="th-custom-css whitespace-nowrap">Total Net</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="td-custom-css">${this.fromDate + " - " + this.toDate}</td>
                        <td class="td-custom-css">${moment(new Date()).format('YYYY-MM-DD')}</td>
                        <td class="td-custom-css">${data.total_tourists}</td>
                        <td class="td-custom-css">${dollarNumFmt(data.total_gross)}</td>
                        <td class="td-custom-css">${dollarNumFmt(data.total_refund  - data.total_refund_fees)}</td>
                        <td class='td-custom-css success-text-color'>${dollarNumFmt(data.total_gross - data.total_refund)} </td>
                    <tr/>
                </tbody>
            </table>
        </div>`);
    }

    toggleControls(state = 'show') {
        $("#nat-collapse-search").val("");
        if (state === 'hide') {
            $(".disable-toggle").prop("disabled", true);
            $(".hide-toggle").hide();
            $(".vis-toggle").css('visibility', 'hidden');
        } else {
            $(".disable-toggle").prop("disabled", false);
            $(".hide-toggle").show();
            $(".vis-toggle").css('visibility', 'visible');
        }

    }

}

/**
 * @description Download the OTAs Report from /app/Repositories/Report/ReportRepository@otaBookingReport to populate a data table.
 * @requires string url rotue "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'ota_report_export')}}"
 * @returns void
 */
const otaReportDownload = (url) => {
    const dpiker = $('#reportrange').data('daterangepicker');
    const from = dpiker.startDate.format('YYYY-MM-DD');
    const to = dpiker.endDate.format('YYYY-MM-DD');
    if (OTAsReport.validateSelectedFields(from, to)) {
        const viewBy = $("#viewby-select").val(); //services rendered || pending bookings
        const dateType = (viewBy === 'purchasedate') ? 'purchase_date' : 'tour_date';
        const params = `tourOperatorId=${$('#tour_operator').val()}&tourPackageId=${$("#tour_package").val()}&otaPlatform=${$("#otaPlatform").val()}&fromDate=${from}&toDate=${to}&viewBy=${viewBy}&dateType=${dateType}&csv=1`;
        window.location = `${url}?${params}`;
    }
}