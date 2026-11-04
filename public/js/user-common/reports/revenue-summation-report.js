class revenueSummationReport {
    static initReport(props) {
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
        const _report = new revenueSummationReport(props);
    }


    /**
     * set the local vars and call the render methods 
     * @param {*} props 
     */
    constructor(props) {
        //base vars 
        this.title = "Revenue Summary";
        this.rateData = [];
        this.admin = (props.admin === "Admin") ? '/admin' : '';
        this.dates = this.getDateRange();
        this.operatorId = ($('#tour_operator').val() !== '') ? $('#tour_operator').val() : "1";
        this.packageId = ($("#tour_package").val() !== '') ? parseInt($("#tour_package").val()) : "0";
        this.viewBy = $("#viewby-select").val(); //services rendered || pending bookings

        //url to view bookings in new link
        this.bookingUrl = `${this.admin}/tour-operator/booking/`;

        //url used to get rate data
        this.baseUrl = `${this.admin}/tour-operator/report/revenue-summation-report`;
        this.rateUrl = `${this.baseUrl}/${this.dates.start_date}/${this.dates.end_date}`;
        this.rateUrl += `/${this.operatorId}/${this.packageId}/${this.viewBy}`;

        //url used to get revenue data 
        this.revenueBase = `${this.admin}/tour-operator/report/revenue-report`;
        this.revenueUrl = `${this.revenueBase}/${this.dates.start_date}/${this.dates.end_date}`;
        this.revenueUrl += `/${this.operatorId}/${this.packageId}/${this.viewBy}`;


        this.revenueData = [];
        this.revenueSums = [];

        //add modal and table container 
        this.addDomElements();

        //fetch the rate data 
        this.fetchRateData();
    }


    addDomElements() {
        // update the view ad a title to the report heading.
        updateReportInfo(`${this.title}`, { from: this.dates.start_date, to: this.dates.end_date });

        // dom ele ids 
        this.tableContanerId = 'revenue-report-view';
        this.dataTableId = 'revenue-report-data-table';
        this.historyModalId = 'rev-report-booking-history';
        this.totalContanerId = 'revenue-report-summary-view';
        this.visitorsContanerId = 'revenue-report-visitors-summary-view';

        //get the main report view div 
        const mainDiv = $('#mainTableDiv');

        //add totals container to tabs
        mainDiv.html(this.renderTabs(`<div id="${this.totalContanerId}"> </div>`, `<div id="${this.visitorsContanerId}"> </div>`));
        //example of downloading
        mainDiv.append(`<button class="rev-report-download d-none">Download</button>`);

        //create a container to place our table in
        mainDiv.append(`<div id='${this.tableContanerId}'></div>`);
        //add filter form 
        mainDiv.append(helperReportFilter());
        //add the history modal to the dom
        mainDiv.append(bootstrapModalHtml({ id: this.historyModalId, size: 'lg', title: 'Booking History', content: "" }));
        // event listeners
        this.eventListeners();
    }

    static downloadTable(id, dates) {

        let header = []; let data = [];

        $(`#${id}-head tr th`).each(function (i, _v) { header[i] = $(this).text(); })

        $(`#${id}-body tr`).each(function (i, _v) {
            data[i] = [];
            $(this).children('td').each(function (ii, _vv) { data[i][ii] = $(this).text(); });
        })

        let title = `Revenue_Summary_from_${dates.start_date}_to_${dates.end_date}`;
        helperExportToCsv(title, [header, ...data]);

    }

    /**
     * get the start and end data from the date range picker 
     * @returns {object}
     */
    getDateRange() {
        let d = $('#reportrange').data('daterangepicker');
        return { start_date: d.startDate.format('YYYY-MM-DD'), end_date: d.endDate.format('YYYY-MM-DD') }
    }
    /**
     * register dom event listener
     */
    eventListeners() {
        $(document).off('click', '.rev-report-row-detail').on('click', '.rev-report-row-detail', this.displayHistoryDetails.bind(this));
        $(document).off('click', '.rev-report-reload').on('click', '.rev-report-reload', this.reloadView.bind(this));
        $(document).off('click', '.rev-report-download').on('click', '.rev-report-download', () => {
            downloadTable(this.dataTableId, this.dates);
        });

        updateActionBtn(() => revenueSummationReport.downloadTable(this.dataTableId, this.dates));

        $(document).off('click', '.downlad-visitor-summary').on('click', '.downlad-visitor-summary', () => {
            let title = `Visitor_Summary_from_${this.dates.start_date}_to_${this.dates.end_date}`;
            this.downloadCSVReport(title, this.visitorsCsv);
        });

        $(document).off('click', '.print-visitor-summary').on('click', '.print-visitor-summary', () => {

            let html = $("#branchDropdownBtn").html();
            if (!html) {
                console.warn("branchDropdownBtn missing or empty");
                return;
            }
            html = html.replace(/40/gi, 100);
            html += "<hr />"
            html += $(`#${this.visitorsContanerId}`).html();
            let style = `
                table, th, td {
                border: 1px solid black;
                border-collapse: collapse;
              }
              .visitor-totals-label{
                    font-size: 20px;
                    font-weight: bold;
              }
              
              .branch-img{
                padding:10px;
              }
              
              `;
            let title = `Visitor_Summary_from_${this.dates.start_date}_to_${this.dates.end_date}`;
            printHtmlContent(html, title, style);
        });



    }

    /**
     * Display history modal 
     * @param {*} evt 
     */
    displayHistoryDetails(evt) {
        let booking_id = $(evt.target).data('id');
        let url = `${this.admin}/tour-operator/booking/history-and-transactions/${booking_id}`;
        $.get(url).done(this.viewBookingHistory.bind(this)).fail(this.onError);
    }

    /**
     * display history details 
     * @param {*} data 
     */
    viewBookingHistory(data) {
        const id = data.id;
        const row = this.revenueData.filter((d) => d.id === id).shift();
        let html = bookingDetailsAsyncView(data, [row.data], id);
        $("#rev-report-booking-history-modal-body").html(html);
        $("#rev-report-booking-history").modal("toggle");
        html = "";
    }

    /**
     * Fetch the Rate Data
     */
    fetchRateData() {
        $.get(this.rateUrl).done(this.fetchRateDataOk.bind(this)).fail(this.onError).always(hideLoading);
    }

    /**
     * Fetch the revenue data 
     * @param {*} res 
     */
    fetchRateDataOk(res) {
        this.rateData = res;
        $.get(this.revenueUrl).done(this.fetchRevenueDataOk.bind(this)).fail(this.onError).always(hideLoading);
    }

    /**
     * map the revenue data to the rate data and display the data table
     * @param {*} res 
     */
    fetchRevenueDataOk(res) {

        this.revenueSums = res.sums;
        let collection = [];

        for (let key in res.data) {
            let d = res.data[key];
            collection.push({ id: d.booking_id, rate: this.findRateData(d.booking_id), data: d });
        }

        this.revenueData = collection;

        this.renderTable();
        this.renderRevenueSumTemplate(this.revenueSums);
        this.visitorsSummaryTemplate(this.revenueSums, this.revenueData);
    }

    /**
     * Display totals table 
     * @param {*} data 
     */
    renderRevenueSumTemplate(data) {
        $(`#${this.totalContanerId}`).html(`
        <table class="table table-sm table-hover" style="width:100%; margin-bottom:20px;">
        <thead>
        <tr>
            <th class="th-custom-css whitespace-nowrap">Total Gross</th>
            <th class="th-custom-css whitespace-nowrap">Total Refund</th>
            <th class="th-custom-css whitespace-nowrap">Total Fees</th>
            <th class="th-custom-css whitespace-nowrap">Total Discount</th>
            <th class="th-custom-css whitespace-nowrap">Total Net</th>
        </tr>
        </thead>
        <tbody>
            <tr>
                <td class="td-custom-css">${dollarNumFmt(data.total_gross)}</td>
                <td class="td-custom-css">${dollarNumFmt(data.total_refund)}</td>
                <td class="td-custom-css">${dollarNumFmt(data.total_platform_fees)}</td>
                <td class="td-custom-css">${dollarNumFmt(data.total_discounts)}</td>
                <td class='success-text-color td-custom-css'> ${dollarNumFmt(data.total_net)} </td>
            <tr/>
        </tbody>
        </table>`);

    }


    countPax(rates, type) {
        let count = 0;
        for (let k in rates) {
            if (rates[k]) {
                if (rates[k].is_group_rate) {
                    if (type == 'adult') {
                        count += rates[k].pax;
                    }
                } else {
                    if (rates[k].pkg_rate_name == 'Guest(s)') {
                        if (type == 'adult') {
                            count += rates[k].pax;
                        }
                    } else {
                        if (rates[k].pkg_rate_name.toLowerCase().includes(type)) {
                            count += rates[k].pax;
                        }
                    }
                }

            }
        }
        return count;
    }

    /**
     * Display number of visitors 
     * @param {*} data 
     */
    visitorsSummaryTemplate(sums, rows) {

        const sumTax = (rates) => {
            let tax = 0;
            for (let k in rates) {
                tax += parseFloat(rates[k].tax);
            }
            return tax;
        };



        let count = 0; let adults = 0; let children = 0; let infants = 0; let taxes = 0;

        let trips = {};

        for (let key in rows) {
            let d = rows[key];
            adults += this.countPax(d.rate, 'adult');
            children += this.countPax(d.rate, 'child');
            infants += this.countPax(d.rate, 'infant');
            taxes += sumTax(d.rate);
            let tripname = `${d.data.tour_date}@${d.data.tour_time}`;
            trips[tripname] = 1;

        }

        // console.log(trips);
        // count = Object.keys(trips).length;
        count = sums.trips;
        // "tour_date": "2024-03-07",
        // "tour_time": "09:00:00", <--  date + time == trip trip 

        let net = parseFloat(sums.total_net);
        let total = (net > 0) ? (net - taxes) : 0;
        let start = moment(this.dates.start_date).format("DD MMM Y");
        let end = moment(this.dates.end_date).format("DD MMM Y");

        this.visitorsCsv = [
            ["Totals Between:", start, "and", end, "", "", ""],
            ["", "", "", "", "", "", ""],
            ["Passengers", "", "", "", "Revenue", "", ""],
            ["", "", "", "", "", "", ""],
            ["Adults", "Children", "Infants", "Base", "Taxes", "Total", "Trips"],
            [FormatNumber(adults), FormatNumber(children), FormatNumber(infants),
            dollarNumFmt(net), dollarNumFmt(taxes), dollarNumFmt(total), FormatNumber(count)]
        ];

        $(`#${this.visitorsContanerId}`).html(`
        <table class="table table-sm table-hover table-border-print" style="width:100%; margin-bottom:20px;">
        <thead>
        <tr>
            <th colspan="100%" class="visitor-totals-label th-custom-css">
                Totals Between: ${start} and ${end}
            </th>
        </tr>
        <tr> 
            <th class="th-custom-css whitespace-nowrap" colspan="3">Passengers</th>
            <th class="th-custom-css whitespace-nowrap" colspan="3">Revenue</th>
            <th class="th-custom-css whitespace-nowrap" colspan="1">
                <i class="fas fa-print print-visitor-summary" style="cursor:pointer; "></i> | 
                <i class="fas fa-download downlad-visitor-summary" style="cursor:pointer; "></i>
                
            </th>
            
        </tr>
        <tr> 
            <th class="th-custom-css whitespace-nowrap">Adults</th>
            <th class="th-custom-css whitespace-nowrap">Children</th>
            <th class="th-custom-css whitespace-nowrap">Infants</th>
            <th class="th-custom-css whitespace-nowrap">Base</th>
            <th class="th-custom-css whitespace-nowrap">Taxes</th>
            <th class="th-custom-css whitespace-nowrap">Total</th>
            <th class="th-custom-css whitespace-nowrap">Trips</th>
        </tr>
        </thead>
        <tbody>
            <tr>
                <td class="td-custom-css">${FormatNumber(adults)}</td>
                <td class="td-custom-css">${FormatNumber(children)}</td>
                <td class="td-custom-css">${FormatNumber(infants)}</td>
                <td class="td-custom-css">${dollarNumFmt(total)}</td>
                <td class="td-custom-css">${dollarNumFmt(taxes)}</td>
                <td class="td-custom-css">${dollarNumFmt(net)}</td>
                <td class="td-custom-css">${FormatNumber(count)}</td>
            
            <tr/>
        </tbody>
        </table>`);
    }


    /**
     * render the main table 
     */

    renderTable() {

        // append the table to the main view.
        $(`#${this.tableContanerId}`).html(dataTableTemplate(this.dataTableId));

        // render a link to the booking page
        const bookingLink = (data) => {
            let history_link = `<i class="fa fa-plus-circle rev-report-row-detail" style="cursor:pointer;" data-id="${data.booking_id}" size="xs"></i>`;
            let booking_link = `<a href='${this.bookingUrl}${data.booking_id}' target='_blank'><small>${data.booking_id}<small></a>`;
            return `${history_link} ${booking_link}`;
        };

        const contactName = (data, _type, _row, _meta) => {
            return data.toLowerCase().replace(/(^\w{1})|(\s+\w{1})/g, letter => letter.toUpperCase());
        };

        const renderTax = (rate) => {
            let total_tax = 0;

            for (let k in rate) {
                if (rate[k].is_group_rate) {
                    total_tax += parseFloat(rate[k].tax);
                } else {
                    total_tax += parseFloat(rate[k].tax) * parseFloat(rate[k].pax);
                }

            }
            return currency(total_tax).format();
        };

        // declare the columns to add to the data table.
        let cols = [
            { data: 'data.booking_date', title: 'Payment Date', className: 'th-custom-css' },
            { data: 'data.tour_date', title: 'Tour Date', className: 'th-custom-css' },
            { data: 'data.nm', title: 'Tour', className: 'th-custom-css', render: (_data, _type, row, _meta) => this.renderTourNameAbr(row) },
            { data: 'data', title: 'Time', className: 'th-custom-css', render: { _: (t) => timeSlotToStandardTime(t.tour_time), sort: 'tour_time' } },
            { data: 'data', title: 'Confirmation', className: 'th-custom-css', render: bookingLink },
            { data: 'data.cnm', title: 'Contact Name', className: 'th-custom-css', render: contactName },
            { data: 'rate', title: 'Adults', className: 'th-custom-css', render: (rates) => this.countPax(rates, 'adult') },
            { data: 'rate', title: 'Children', className: 'th-custom-css', render: (rates) => this.countPax(rates, 'child') },
            { data: 'rate', title: 'Infants', className: 'th-custom-css', render: (rates) => this.countPax(rates, 'infant') },
            { data: 'data.status', title: 'Status', className: 'th-custom-css' },
            { data: 'data.trans_type', title: 'Purchase Type', className: 'th-custom-css', render: (_data, _type, row, _meta) => this.renderTransactionType(row) },
            { data: 'data.gross_revenue', render: (gross_revenue) => { return currency(gross_revenue).format(); }, title: 'Gross', className: 'th-custom-css' },
            { data: 'data.discount', title: 'Discount', className: 'th-custom-css', render: (dis) => currency(dis).format() },
            { data: 'data.platform_fees', render: (platform_fees) => { return currency(platform_fees).format(); }, title: 'Fees', className: 'th-custom-css' },
            { data: 'rate', title: "Tax", className: 'th-custom-css', render: renderTax },
            { data: 'data.refund_total', render: (refund_total) => { return currency(refund_total).format(); }, title: 'Refund', className: 'th-custom-css' },
            { data: 'data.net_revenue', render: (net_revenue) => { return currency(net_revenue).format(); }, title: 'Net', className: 'th-custom-css' }];

        // add a filter button to datatable btn grp
        const renderFilterBtn = {
            text: '<i class="fas fa-funnel-dollar"></i>',
            action: function (e, dt, node, config) {
                $("#rev-report-filter-modal").modal("toggle");
            }
        };


        const pdfBtn = {
            extend: 'pdfHtml5',
            orientation: 'landscape',
            pageSize: 'LEGAL'
        };

        //display download btns
        const btns = ['excelHtml5', 'csvHtml5', pdfBtn, renderFilterBtn];

        // render the data table
        $(`#${this.dataTableId}`).DataTable({
            data: this.revenueData,
            dom: 'Bfrtip',
            buttons: btns,
            pageLength: 100,
            columns: cols,
            fixedHeader: true,
            "footerCallback": this.renderTableFooter,
            info: false
        });

    } // end datatable

    /**
     * render the total in the table footer 
     * @param {*} _row 
     * @param {*} data 
     */
    renderTableFooter(_row, data) {
        let total = 0;
        for (let key in data) {
            total += parseFloat(data[key].data.net_revenue);
        }
        $(`#${this.dataTableId}-foot`).html(`
                <tr>
                    <td colspan="100%">
                    <div class="float-end">Total: <strong>${currency(total).format()}</strong> </div>
                    </td>
                </tr>`);
    }

    /**
     * @description acronym of tour name 
     * @param {*} row 
     * @returns String
     */
    renderTourNameAbr(row) {
        let str = row.data.nm;
        return str.split(/\s/).reduce((response, word) => response += word.slice(0, 1), '').toUpperCase();
    }

    /**
     * @function renderTransactionType
     * @param {*} row
     * @returns String
     */
    renderTransactionType(row) {

        try {
            let _type = row.data.trans_type;
            if (_type === "Invoiced") {
                if (row.billable) {
                    _type = ` ${_type} <span class="text-warning">(billable)</span>`;
                }
            }
            return _type;
        } catch (err) {
            console.log(err);
        }

        return "";


    }

    /**
     * map the rate data to the rev data 
     * @param {*} id 
     * @returns 
     */
    findRateData(id) {
        if (this.rateData.hasOwnProperty(id)) {
            return this.rateData[id];
        } else {
            return [];
        }

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
     * This function is triggered when a user clicks on the refresh icon btns.
     * @returns void
     */
    reloadView() {
        let filters = {};
        $(".rev-filter:checkbox:checked").each((_idx, ele) => {
            let el = $(ele);
            let tp = el.data('type');
            if (!_.has(filters, tp)) { filters[tp] = []; }
            filters[tp].push(encodeURIComponent(el.val()));
        });

        $(".rev-filter:radio:checked").each((_idx, ele) => {
            let el = $(ele);
            let tp = el.data('type');
            if (!_.has(filters, tp)) { filters[tp] = []; }
            filters[tp].push(encodeURIComponent(el.val()));
        });


        filters['sort'] = [$('input[name="revReportSortOption"]:checked').val()];
        let query = '?filters=1'
        for (let key in filters) {
            if (filters[key].length > 0) {
                query += `&${key}=${filters[key].join("|")}`
            }
        }

        $("#rev-report-filter-modal").modal("hide");

        showLoading();
        this.rateUrl += query;
        this.revenueUrl += query;
        this.fetchRateData();
    }


    downloadCSVReport(filename, data) {
        helperExportToCsv(filename, data)
    }


    renderTabs(totals, visitors) {

        return `

            <ul class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-y-auto" id="revsumTotalsTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link tabs-custom-link active" id="totals-tab" data-bs-toggle="tab" data-bs-target="#revtotals" type="button" role="tab" aria-controls="home" aria-selected="true">Revenue Summary</button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link tabs-custom-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#revprofile" type="button" role="tab" aria-controls="profile" aria-selected="false">Visitor Summary</button>
            </li>
          </ul>
          <div class="tab-content" id="revsumTotalsTabContent">
            <div class="tab-pane fade show active" id="revtotals" role="tabpanel" aria-labelledby="totals-tab">
                <div class="page-content p-5 border border-[#E5E7EB] rounded-b-md">
                ${totals}
                </div>
            </div>
            <div class="tab-pane fade" id="revprofile" role="tabpanel" aria-labelledby="profile-tab">
                <div class="page-content p-5 border border-[#E5E7EB] rounded-b-md">
                ${visitors}
                </div>
            </div>
          </div>`;



    }



}