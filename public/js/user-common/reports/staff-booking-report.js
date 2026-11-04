/**
 * this is used to invoke a new instance of the StaffBookingReport class in the report.blade template
 * @param {*} props
 */
const staffBookingReportInit = (props) => {
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
    new StaffBookingReport(props);
}

let collection_data = [];

/**
 * @class
 * @description StaffBookingReport
 * Staff Booking Report Load agg. function data
 * Load json data from /app/Repositories/Report/ReportRepository@staffBookingReport to populate a data table.
 * @param string endpont the endpoint to the staff booking report.
 * @requires js: jquery, moment, showLoading, hideLoading, updateReportInfo, swal
 * @returns void
 */
class StaffBookingReport {
    constructor(props) {
        this.endpoint = props.endpoint;
        this.admin = props.admin;
        this.adminpath = (this.admin=='Admin') ? "/admin" : "";
        this.tdCount = '7';
        this.tdWidth = '12.5%';
        this.operatorId = (!this.admin) ? props.operatorId : $("#tour_operator").val();
        this.packageId = $("#tour_package").val();
        this.dates = this.getDateRange();
        this.fromDate = this.dates.start_date;
        this.toDate = this.dates.end_date;
        this.url = this.endpoint.replace('_FROM_DATE_', this.fromDate).replace('_TO_DATE_', this.toDate);
        this.url += `/${this.operatorId}/${this.packageId}`;
        this.title = 'Staff Booking Report';
        this.collection = [];
        this.state = { currentView: 'total', reload: true, totals_toggle: 'show' }
        if (StaffBookingReport.validateSelectedFields( this.fromDate, this.toDate )) {
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
        updateReportInfo(`${this.title}`, dateDisplay);
        updateActionBtn(downloadReport);


        // // fetch the data and render the staff booking table
        $.get(this.url).done(this.renderTotals.bind(this)).fail(this.onError).always(hideLoading);
    }

    /**
     * @function events listeners for ui
     * @description
     * assign click events
     * @returns void
     */
    eventListeners() {
        $(document).off("keyup", "#nat-collapse-search").on("keyup", "#nat-collapse-search", this.searchTable.bind(this));
        $(document).off('click', '.rev-display-totals').on('click', '.rev-display-totals', this.displayTotals.bind(this));
        $(document).off('change', '#rev-total-by-select').on('change', '#rev-total-by-select', this.updateTotalBy.bind(this));
    }

    /**
     * @function renderTotals
     * @description Display the rev table.
     * @param {*} data
     */
    renderTotals(data) {
        this.collection = data;
        collection_data = this.collection;

        // add ui elements
        let mainView = $('#mainTableDiv');
        mainView.html("<div id='staff-booking-report-summary-view'></div>");
        mainView.append(this.collapseExapandAllBtnTemplate(collection_data));
        mainView.append(this.tableHeaderTemplate());
        mainView.append("<div id='staff-booking-report-view'></div>");
        this.displayTotals();
    }

    /**
       * @function displayTotals
       * @description Display the table view if the data is already loaded
       * @param {*} data
    */
    displayTotals() {
        this.toggleControls();
        this.renderyTotalsView(this.collection.data);
    }

    collapseExapandAllBtnTemplate(collection) {

        $(document).off('click', ".rev-report-expand-all").on("click", ".rev-report-expand-all", (evt) => {
            let ele = $(evt.target);
            let act = (ele.data('state') === 'hide') ? 'show' : 'hide'
            $('#staff-booking-report-view .collapse').collapse(ele.data('state'));
            ele.html(act === 'hide' ? 'Hide All' : 'Show All');
            ele.data('state', act);
            this.state.totals_toggle = act;
        });

        $(document).off('click', ".rev-display-filters").on("click", ".rev-display-filters", (evt) => {
            $("#rev-report-filter-modal").modal("toggle");
        });

        var html = '';
        if(!jQuery.isEmptyObject(collection)) {
            html += '<select id="rev-total-by-select" class="nat-sm-input form-custom-input mr-2 bg-[#FAFAFA]" style="max-width: 220px;">';
            html += '<option value="all">All</option>';
            $.each(collection.data, function(key, value) {
            	html += '<option value="' + value.name + '">' + value.name + '</option>';
            });
            html += '</select>';
        }

        return `
        <div class="mb-3 text-right flex items-center justify-end">
        <input class="disable-toggle nat-sm-input form-custom-input mr-2 max-w-80" style="max-width: 320px;" type="text" placeholder="Search" id="nat-collapse-search" value="" />

        ` + html + `

        <button class="btn btn-sm btn-default disable-toggle rev-report-expand-all btn-primary-small whitespace-nowrap" data-state="show">Show All</button>
        </div>`;
    }

    tableHeaderTemplate() {
        return `
	        <div class="col-12 vis-toggle" id="rev-report-table-header">
	        <table class="w-full border-collapse text-left table table-borderless">
	            <thead class="nat-header-grp" >
	                    <tr style="background-color:#F5F5F5;">
                            <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Sr No</th>
	                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Booking ID</th>
	                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Booking Date</th>
	                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Created Date</th>
                            <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Tour Time</th>
	                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Booking Amount</th>
	                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Customer Name</th>
	                        <th width="${this.tdWidth}" class="nat-header nat-no-border th-custom-css">Status</th>
	                    </tr>
	            </thead>
	        </table>
	    </div>`;
    }

    /**
     * @function getGroupByField
     * @description set the group by agg. to update the view
     * @returns string group by string week|day|year
     */
    getGroupByField() {
        return collection_data;
    }

    /**
     * @description Display a drop down accordion with nested tables
     * @param {*} data
     */
    renderyTotalsView(data) {
        $("#staff-booking-report-view").html("loading...");
        let collection = {};
        let grp = this.getGroupByField();
        let view = [];
        let total = 0;
        let count = 0;

        if(this.state.totalBy != undefined) {
            if(this.state.totalBy != "all") {
                grp = this.search(this.state.totalBy, grp);
            }
        }
        showLoading()

        if(jQuery.isEmptyObject(grp) != null) {
            for(let key in grp.data) {
                let data_by_staff = grp.data[key];

                collection[key] = {
                    rows: [], summary: {
                        'name': data_by_staff.name,
                        'total': 0
                    } 
                };

                let counter = 1;
                data_by_staff.data.forEach(ele => {
                    collection[key].rows.push(this.collapseDataRowTemplate(key, ele, counter));
                    count += 1;
                    total += 1;
                    counter++;
                });
            }
            
            for (let k in collection) {
                let title = collection[k].summary.name;
                view.push(this.collapseTemplate({
                    title: title, id: `revdom-${k}`,
                    count: collection[k].rows.length,
                    total: collection[k].rows.length,
                    html: this.tableTemplate(k, collection[k].rows.join("\n"),
                        this.collapseTFootTemplate(title))
                }));
            }
        }

        $("#staff-booking-report-view").html(view.join("\n"));
        $("#staff-booking-report-view").append(`<div class="flex items-center justify-between"><small class="font-medium text-black text-sm text-muted">Total Bookings:(${count})</small> <small class="font-medium text-black text-sm text-success"><strong>Total: ${total}</strong></small></div>`);
        hideLoading();
        view = [];
        collection = {};
    }

    collapseDataRowTemplate(id, ele, counter) {
        return `<tr class="search-row" data-id="${id}" >
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${counter}"><i class="fa fa-plus-circle rev-report-row-detail" data-id="${ele.tour_booking_id}" size="xs"></i> |  ${counter}</td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.tour_booking_id}">
                <a href='${this.adminpath}/tour-operator/booking/${ele.tour_booking_id}' target='_blank'>${ele.tour_booking_id}</a>
            </td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.date}">${ele.date}</td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.created_at}">${ele.created_at}</td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.time_from}">${ele.time_from}</td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.booking_amount}">${dollarNumFmt(ele.booking_amount)}</td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.customer}">${ele.customer}</td>
            <td class="td-custom-css" width="${this.tdWidth}" data-val="${ele.status}">${ele.status}</td>
        </tr>`;
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

    /**
     * ---------------  HTML VIEW TEMPLATES BELOW ---------------
     */
    tableTemplate(id, data, total) {
        return `<div style="background-color:#fff; border-radius: 10px; padding:1px;" >
        <table class="table table-sm table-hover w-full">
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
                <div class="float-end"> <span class="me-2">(${props.total}) </span> </div>
            </div>
            <div class="collapse ${viewstate[1]}" id="nat-${props.id}">
                ${props.html}
            </div>
        </div>`;
    }

    collapseTFootTemplate(name, total) {
        return `<tr>
            <td class='td-custom-css' colspan="${this.tdCount}">
                Total for ${name}
            </td>
            <td class='td-custom-css text-success'>
            </td>
        </tr>`;
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
        }
    }

    /**
     * @function updateTotalBy
     * @description This will show the data by: week|month|day
     * it sets a class var that will tell the views below how to
     * aggragate the data when loaded.
     * @returns void
     */
    updateTotalBy() {
        this.state.totalBy = $('#rev-total-by-select').val();
        this.displayTotals();
    }

    search(nameKey, myArray){
        for (let i = 0; i < myArray.data.length; i++) {
            if (myArray.data[i].name === nameKey) {
                return { data: { 0: myArray.data[i] } };
            }
        }
    }
}

/**
 * @description Download the Staff Booking Report from /app/Repositories/Report/ReportRepository@staffBookingReport to populate a data table.
 * @requires string url rotue "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'staff_booking_report_export')}}"
 * @returns void
 */
const staffBookingReportDownload = (url) => {
    const dpiker = $('#reportrange').data('daterangepicker');
    const from = dpiker.startDate.format('YYYY-MM-DD');
    const to = dpiker.endDate.format('YYYY-MM-DD');
    if (StaffBookingReport.validateSelectedFields(from,to)) {
        const params = `tourOperatorId=${$('#tour_operator').val()}&tourPackageId=${$("#tour_package").val()}&fromDate=${from}&toDate=${to}&csv=1`;
        window.location = `${url}?${params}`;
    }
}