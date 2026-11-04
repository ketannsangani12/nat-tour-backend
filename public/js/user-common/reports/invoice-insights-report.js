/**
 * @description 
 * InvoiceInsights Report Load agg. function data
 * Load json data from /app/Repositories/Report/ReportRepository@InvoiceInsightsReport to populate a data table.
 * @param string endpont the endpoint to the InvoiceInsights report. 
 * @requires js: jquery, moment, showLoading, hideLoading, swal 
 * @returns void
 * Booked - Billable - Invoicing Overview - Invoice List - Revenue Chart
 */
const InvoiceInsightsReportInit = (props) => {
  // make sure tour operator and tour package is selected
  $('#mainTableDiv').empty();
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
  if ($('#tour_operator').val() == '') {
    swal('Please select a tour operator', '', 'warning');
    return false;
  }
  //create the invoice report class instance
  new InvoiceInsightsReport(props);
}

// How often to poll for the finished PDF, and a generous backstop so the
// dialog cannot spin forever if the job silently dies (the PDF would never
// appear on S3, so existence-polling alone can't detect that).
const INVOICE_PDF_POLL_MS = 3000;
const INVOICE_PDF_MAX_ATTEMPTS = 100; // ~5 min of polling

// Inject the spinner styles once (kept here so this file is self-contained).
function injectInvoicePdfSpinnerStyle() {
  if (document.getElementById('invoice-pdf-spinner-style')) {
    return;
  }
  const style = document.createElement('style');
  style.id = 'invoice-pdf-spinner-style';
  style.textContent =
    '.invoice-pdf-wait{display:flex;flex-direction:column;align-items:center;gap:14px;padding:6px 0;}' +
    '.invoice-pdf-spinner{width:44px;height:44px;border-radius:50%;border:4px solid #e2e8f0;' +
    'border-top-color:#60BEBD;animation:invoicePdfSpin 0.9s linear infinite;}' +
    '.invoice-pdf-status{margin:0;color:#475569;font-size:14px;}' +
    '@keyframes invoicePdfSpin{to{transform:rotate(360deg);}}';
  document.head.appendChild(style);
}

// Generate the invoice PDF and watch it to completion. This is the copy used
// on the report page, where the invoice is shown in a modal via
// getInvoiceDetails(). It mirrors the standalone implementation in
// public/js/admin/invoice.js - keep the two behaviourally in sync.
const generatePDFAsync = (event) => {
  event.preventDefault();

  // event.target may be the icon inside the anchor; walk up to the anchor.
  const ele = event.target.closest('a');
  if (!ele) {
    return false;
  }

  const tag = $(ele);
  // Remember the original markup (icon + label) so we can restore it verbatim.
  if (tag.data('originalHtml') === undefined) {
    tag.data('originalHtml', tag.html());
  }

  const setBusy = (text) => {
    tag.text(text);
    tag.css('pointer-events', 'none').addClass('opacity-60');
  };
  const reset = () => {
    tag.html(tag.data('originalHtml'));
    tag.css('pointer-events', '').removeClass('opacity-60');
    $("#pdf-not-generated").remove();
  };

  setBusy('Starting...');

  $.ajax({ url: ele.href, dataType: 'json' })
    .done((data) => {
      if (!data || !data.success) {
        reset();
        swal('PDF creation', (data && data.message) || 'Could not start PDF generation.', 'error');
        return;
      }
      pollInvoicePdf(data.status_url, data.previous_last_modified, setBusy, reset);
    })
    .fail((xhr) => {
      reset();
      const message = (xhr.responseJSON && xhr.responseJSON.message)
        || 'Could not start PDF generation. Please try again.';
      swal('PDF creation', message, 'error');
    });

  return false;
};

// Show a waiting dialog with a spinner that stays open and polls the status
// endpoint until a PDF newer than `baseline` appears, then swaps itself for a
// download prompt. The dialog is not dismissible by click/escape, but a
// "Run in background" button lets the user stop watching (generation keeps
// going server-side) so they are never trapped if something stalls.
function pollInvoicePdf(statusUrl, baseline, setBusy, reset) {
  if (!statusUrl) {
    reset();
    swal('PDF creation', 'Generation started, but the status could not be tracked. Please check back shortly.', 'info');
    return;
  }

  injectInvoicePdfSpinnerStyle();

  // finished: we resolved successfully / with a terminal message and are
  //           swapping the dialog ourselves - not a user cancel.
  // cancelled: the user clicked "Run in background" - stop polling.
  let finished = false;
  let cancelled = false;
  let attempt = 0;
  const startedAt = Date.now();

  const content = document.createElement('div');
  content.className = 'invoice-pdf-wait';
  content.innerHTML =
    '<div class="invoice-pdf-spinner"></div>' +
    '<p class="invoice-pdf-status">Starting the PDF generation...</p>';
  const statusText = content.querySelector('.invoice-pdf-status');

  swal({
    title: 'Generating invoice PDF',
    content: content,
    closeOnClickOutside: false,
    closeOnEsc: false,
    buttons: {
      background: { text: 'Run in background', value: null },
    },
  }).then(() => {
    // Fires when the dialog closes. If we did not close it ourselves, the
    // user chose to stop watching.
    if (!finished) {
      cancelled = true;
      reset();
    }
  });

  const showReady = (status) => {
    finished = true;
    reset();
    swal({
      title: 'Invoice PDF ready',
      text: 'Your PDF has finished generating.',
      icon: 'success',
      buttons: {
        download: { text: 'Download PDF', value: 'download' },
        close: { text: 'Close', value: null },
      },
    }).then((choice) => {
      if (choice === 'download' && status.url) {
        window.open(status.url, '_blank');
      }
    });
  };

  const showStalled = () => {
    finished = true;
    reset();
    swal('PDF creation', 'This is taking longer than expected. The PDF is still being generated in the background and will be ready shortly - please try again in a moment.', 'info');
  };

  const tick = () => {
    if (cancelled) {
      return;
    }

    attempt += 1;
    const seconds = Math.round((Date.now() - startedAt) / 1000);
    if (statusText) {
      statusText.textContent = `Working on your PDF... ${seconds}s elapsed. Please keep this window open.`;
    }

    $.ajax({ url: statusUrl, dataType: 'json' })
      .done((status) => {
        if (cancelled) {
          return;
        }

        // A newly generated PDF has a last_modified different from the
        // one that existed before we kicked off generation.
        const isFresh = status && status.ready
          && (baseline === null || baseline === undefined || status.last_modified !== baseline);

        if (isFresh) {
          showReady(status);
          return;
        }

        if (attempt >= INVOICE_PDF_MAX_ATTEMPTS) {
          showStalled();
          return;
        }

        setTimeout(tick, INVOICE_PDF_POLL_MS);
      })
      .fail(() => {
        if (cancelled) {
          return;
        }

        if (attempt >= INVOICE_PDF_MAX_ATTEMPTS) {
          finished = true;
          reset();
          swal('PDF creation', 'Could not confirm the PDF status. Please refresh the page and try again.', 'error');
          return;
        }

        setTimeout(tick, INVOICE_PDF_POLL_MS);
      });
  };

  setTimeout(tick, INVOICE_PDF_POLL_MS);
}




/**
 * Invoice Insights Report Class
 * @description display invoice data and update forms in a single view. 
 * This is scripted is use on the tour-backend/resources/views/users/common/report.blade.php view.
 * @param {*} props { endpoint, admin }
 * @returns void
 */
class InvoiceInsightsReport {
  // init class vars. 
  constructor(props) {
    this.endpoint = props.endpoint;
    this.admin = (props.admin === 'Admin') ? "/admin" : "";
    this.invoiceUrl = props.invoiceUrl;
    this.tourOptId = $("#tour_operator").val();
    this.tourPkgId = $("#tour_package").val() || 0;
    this.daterange = this.getDateRange();
    this.fromDate = this.daterange.start_date;
    this.toDate = this.daterange.end_date;
    this.status = "overdue+draft+viewed+sent+unpaid+partial+cancelled";
    this.customerID = parseInt($('#customer-data').val());
    this.customerTitle = $("#customer-data").find(":selected").text();
    this.compTable = false; this.unpaidTable = false; this.chartIsRendered = false; this.billableTable = false; this.invoiceChart = false; this.invoiceTable = false; this.bookedTable = false;
    this.unpaidInvoices = [];
    this.affiliateGroupFormData = [];
    this.url = this.endpoint.replace('_FROM_DATE_', this.fromDate).replace('_TO_DATE_', this.toDate);
    this.url += `/${this.customerID}/${this.tourOptId}/${this.tourPkgId}/_STATUS_`;
    this.colors = InvoiceInsightsReport.colors();
    this.icons = InvoiceInsightsReport.icons();
    this.selectColNum = 0;
    this.displayVoided = 0;
    this.compURL = props.compDownLoadURL;
    this.bookURL = props.bookedDownLoadURL;
   
    this.init(); //invoke the report load / render methods
    updateActionBtn(downloadReport);
  }

    static colors(){
      return { "billable": "#ffc107", "cash": "green", "invoiced": "#125D8A", "draft": "#125D8A", "sent": "#0dcaf0", "viewed": "#0d6efd", "partial": "#20c997", "paid": "#393", "card": "#393", "approved": "#60D2BC", "overdue": "#8B1C00", "cancelled": "grey", "unpaid": "#ED3F3F"};
    }

    static icons(){
      return { "billable": "fa-info-circle", "invoiced": "fa-receipt", "draft": "fa-receipt", "sent": "fa-file-export", "viewed": "fa-file", "partial": "fa-file-invoice", "paid": "fa-file-invoice-dollar", "approved":"fa-check", "overdue": "fa-money", "cash": "fa-money-bill", "cancelled": "fa-ban", "unpaid": "fa-money" };
    }
    /*
       * @function getDateRange
       * @description return the start and end date from the datepicker 
       * @returns {*} start and end date yyyy-mm-dd
   */
  getDateRange(){
    let d = $('#reportrange').data('daterangepicker');
    return {start_date: d.startDate.format('YYYY-MM-DD'), end_date:  d.endDate.format('YYYY-MM-DD')};
  }

  /**
   * @function init
   * @description
   * Add common elements to the view attach events. 
   * Note sendInvoiceEmailAsyncForm and refNumberInvoiceAsyncForm elements are loaded from 
   * tour-backend/resources/views/users/admin/invoice/show.blade.php
   * #mainTableDiv element is loaded on the 
   * tour-backend/resources/views/users/common/report.blade.php
   * @returns void
   */
  init() {

    this.eventListeners();
    // update the view ad a title to the report heading. 
    let dateDisplay = {from: this.fromDate, to: this.toDate};
    updateReportInfo(`Invoice: ( ${this.customerTitle} )`, dateDisplay);

    //reset the main view
    let mainDiv = $("#mainTableDiv");
        mainDiv.empty();
        mainDiv.append(this.expandViewTmpl())
        mainDiv.append(this.invoiceTotalsTmpl());
        mainDiv.append(this.invoiceTabsTemplate());
        mainDiv.append(this.invoiceDetailsModalTemplate());
        mainDiv.append(this.filtersModalTemplate());
        mainDiv.append(this.currentInvoiceFilters());
        mainDiv.append(this.viewInvoicedBookingModalTemplate());

    //show view loading
    showLoading();
    //display data in the 1st tab [billable]
    this.renderBillableView();
    this.renderInvoiceDetail();

    // multiple select drop-down
    $('select[multiple]').multiselect({
      columns  : 2,
      search   : false,
      selectAll: true,
      texts    : {
          placeholder: 'Select Status',
          search     : ''
      }
    });
   
  }

  /**
   * @functionn eventListeners
   * @description attach all the events to the given dom component see templates for markup
   * @returns void
   */
  eventListeners(){

        //reload page if session exp. 
        $(document).off('ajaxError').on('ajaxError', (_event, xhr) => {
          if (xhr.status === 401 || xhr.status === 403) {
            window.location.reload();
          }
        });


        $(document).off('click', '#insight-report-expand-view').on('click', '#insight-report-expand-view', () => {
          const ele = $("#printAreaReport");
          if  (ele.hasClass("container-fluid")) { 
            ele.removeClass("container-fluid").addClass("container");
             $("#insight-report-expand-view").text("<-- Expand View -->");
          } else{
            ele.removeClass("container").addClass("container-fluid");
           
            $("#insight-report-expand-view").text("--> Collapse View <--");
          }
        
        });


        // on the invoice details page we can submit the send invoice form with this event listner. 
        $(document).off('submit', '#sendInvoiceEmailAsyncForm').on('submit', '#sendInvoiceEmailAsyncForm', this.sendInvoiceAsync.bind(this));
        // update ref number on invoice.
        $(document).off('submit', '#refNumberInvoiceAsyncForm').on('submit', '#refNumberInvoiceAsyncForm', this.referenceInvoiceAsync.bind(this));
        // update invoice form listner
        $(document).off('submit', '#form-invoice-status').on('submit', '#form-invoice-status', this.updateInvoiceStatusSubmit.bind(this));
        // catch the submit from the create invoice form 
        $(document).off('submit', "#invoice-create-form-async").on("submit", "#invoice-create-form-async", this.createInvoiceAsync.bind(this));
        //update invoice btn
        $(document).off('click', '.update-invoice-data').on('click', '.update-invoice-data', this.updateInvoiceStatus.bind(this));
        // delete invoice. 
        $(document).off('click', ".invoice-delete").on("click", ".invoice-delete", this.deleteInvoice.bind(this));
        //checkbox when selected a booking to add to invoice. 
        $(document).off('click', ".invoice-save-billable").on("click", ".invoice-save-billable", this.renderCreateInvoiceForm.bind(this));
        $(document).off('click', "#invoice-insights-filter-status").on("click", "#invoice-insights-filter-status", this.triggerUpdateFilter.bind(this));
        $(document).off('click', "#updateBookingTable").on("click", "#updateBookingTable", this.renderBookedTable.bind(this));
        $(document).off('click', ".invoice-insight-close-bookig-edit").on('click', '.invoice-insight-close-bookig-edit', this.checkBookingForUpdate.bind(this));
        $(document).off('click', ".view-booking-in-modal").on('click', '.view-booking-in-modal', this.viewUnpaidBooking.bind(this));
        $(document).off('click', ".refresh-current-invoice").on('click', '.refresh-current-invoice', this.renderCurrentInvoices.bind(this));
        $(document).off('change', ".enable-stripe-for-affiliate").on('change', '.enable-stripe-for-affiliate', this.enableStripeInvoice.bind(this));
        $(document).off('click', ".toggle-void-invoice-view-btn").on('click', '.toggle-void-invoice-view-btn', this.filterCurrentInvoices.bind(this));
        //$(document).off('click', ".display-stripe-detail").on('click', ".display-stripe-detail", this.getStripeInvoiceDetails.bind(this));
        
        //checkbox update all due dates if checked
        $(document).off('change', "#async_invoice_due_date").on("change", "#async_invoice_due_date", (evt) => {
          //update-all-due-dates
          $(".invoice-create-grp-date").val($("#async_invoice_due_date").val());
        });

        // fetch booked
        $(document).off('click', "#invoiceBookedTab-tab").on("click", "#invoiceBookedTab-tab", () => {
          this.billableTable = false;
          this.invoiceChart = false;
          if (this.bookedTable) { return true; }
          setTimeout(() => this.renderBookedTable(), 300);
        }); 
        // fetch billable data table
        $(document).off('click', "#invoiceBillableTab-tab").on("click", "#invoiceBillableTab-tab", () => {
          if (this.billableTable) { return true; }
          setTimeout(() => this.renderBillableView(), 300);
        });
        // fetch a list of invoices
        $(document).off('click', "#invoiceTable-tab").on("click", "#invoiceTable-tab", () => {
          if (this.invoiceTable) { return true; }
          setTimeout(() => this.renderCurrentInvoices(), 300);
        });

        // fetch bookings that are nested in an invoiced 
        $(document).off('click', "#invoiceNestedTab-tab").on("click", "#invoiceNestedTab-tab", () => {
          if (this.unpaidTable) { return true; }
          setTimeout(() => this.renderNestedTable(), 300);
        });

        // fetch piechart data
        $(document).off('click', "#invoiceChart-tab").on("click", "#invoiceChart-tab", () => {
          if (this.invoiceChart) { return true; }
          setTimeout(() => this.renderChart(), 300);
        });

        $(document).off('click', "#comprehensiveTab-tab").on("click", "#comprehensiveTab-tab", () => {
          if (this.compTable) { return true; }
          setTimeout(() => this.renderCompTable(), 300);
        });

  }

  /**
   * @function dataRefreshAction
   * @description force refesh a view. 
   * @param string classname class to assign event to
   * @param funciton callback to call on click event
   * @returns void 
   */
  dataRefreshAction(classname, callback, options={   }) {
    $(document).off('click', `.${classname}`).on('click', `.${classname}`, (_evt) => { callback() });

    let cls = 'float-start';
    if ( _.has(options, 'cls') ) { cls = options.cls; }
    let html = `<div class="btn-toolbar ${cls}" style="z-index: 1; position: relative;" role="toolbar" aria-label="Invoice filters">
      <div class="btn-group flex items-center justify-end mb-2" role="group" aria-label="Basic example">
        <button class="btn btn-sm inv-display-filters btn-info-small mr-2" title="Refesh Data" ><i class="fas fa-sync ${classname}"></i></button>`;

      if(_.has(options, 'invoice')){
          $(document).off('click', `.${classname}-invoice`).on('click', `.${classname}-invoice`, (_evt) => { options.invoice.callback() });
          html += `<button class="btn btn-sm btn-info-small mr-2 inv-display-totals ${classname}-invoice" title="View Invoice List" ><i class="fas fa-list"></i></button>`;
        }


      if (_.has(options, 'create_invoice')){
        html += '<button class="btn btn-success btn-sm invoice-save-billable btn-success-small mr-2">Create Invoice</button>';
      }

      if(_.has(options, 'view_all')){
        $(document).off('click', `.${classname}-view_all`).on('click', `.${classname}-view_all`, (_evt) => { options.view_all.callback() });
        html += `<button class="btn btn-sm btn-info-small mr-2 inv-display-totals ${classname}-view_all" title="View All Bookings" ><i class="fas fa-table"></i></button>`;
      }

     

      if ( _.has(options, 'filters') ) {
        $(document).off('click', `.${classname}-filter`).on('click', `.${classname}-filter`, (_evt) => { options.filters.callback() });
        html += `<button class="btn btn-sm btn-info-small mr-2 inv-display-totals ${classname}-filter" title="Filters" ><i class="fas fa-filter"></i></button>`;
      }

 

    html += '</div> </div>';
    return html;
  }

  /**
   * @function resetTabState
   * @description reset the tabs so on click the data is refreshed
   * @returns void 
   */
  resetTabState() {
    this.unpaidTable = this.billableTable = this.invoiceChart = this.invoiceTable = this.bookedTable = false;
  }

  /**
   * @function renderInvoiceDetail
   * @description Fetch the invoice data from the existing affilate all report. 
   * @returns void ajax request 
   */
  renderInvoiceDetail(){
     ///admin/tour-operator/report/affiliate-all-report/2023-05-01/2023-05-31/0/1/1
    this.invoiceDetail = true;
    let url = `${this.admin}/tour-operator/report/affiliate-all-report`;
    let uri = `/${this.fromDate}/${this.toDate}/${this.customerID}/${this.tourOptId}/${this.tourPkgId}`;
    $.get(`${url}${uri}`).done(this.renderInvoiceDetailOk.bind(this)).fail(this.renderViewError).always(hideLoading);
  }

  /** 
   * @function renderInvoiceDetailOk
   * @description Load the invoice totals at the top of the page
   * @returns void render html data table. 
  */
  renderInvoiceDetailOk(data){
    let thead = dataTableHeaderTemplate(['Date Range','Created','Booking Amount','Tourists Total','Infants Total','Paid Amount','Refund Amount','Unpaid Amount'])
    $("#invoice-insights-totals-table-head").html(thead);
    let tbody = ` 
              <tr>
                <td class="td-custom-css"><small>${this.fromDate} - ${this.toDate}</small></td>
                <td class="td-custom-css">${moment().format('YYYY-MM-DD')}</td>
                <td class="td-custom-css">${dollarNumFmt(data.booking_amount)}</td>
                <td class="td-custom-css">${toFmtStr(data.tourists_total)}</td>
                <td class="td-custom-css">${toFmtStr(data.infants_total)}</td>
                <td class="td-custom-css text-[#2FA136]">${dollarNumFmt(data.paid_amount)}</td>
                <td class="td-custom-css">${dollarNumFmt(data.refund_amount)}</td>
                <td class="td-custom-css text-[#A32624]">${dollarNumFmt(data.unpaid_amount)}</td>
              </tr>`;

    
    $("#invoice-insights-totals-table-body").html(tbody);
    this.renderAffDueTable(data.due_amount, {total: data.booking_amount, refund: data.refund_amount, unpaid: data.unpaid_amount});
  }

  renderAffBarChart(data){
    let cols = {};
    data.map((d)=>{
      let key = d.name;
      if (! _.has(cols,  key ) ) { cols[key] = 0;}
      cols[key] += parseFloat(d.booking_details.payment_cost);
    });

    this.renderBarChart("AffBookingAmtChart", "Affliate Booking Data", cols );

  }

  /**
   * @function renderAffDueTable
   * @description Render a simple table of amount due. 
   * @param {*} data json data from affliate all report due_amount prop. 
   */
  renderAffDueTable(data, totals){

    const {total, refund, unpaid} = totals;
    let thead = dataTableHeaderTemplate(['Affiliate', 'Tourist', 'Gross Amount']);
     let tbody = data.map((d)=>{
       return `
        <tr> 
          <td class="td-custom-css" data-id="${d.id}">${d.name}</td>
          <td class="td-custom-css">${toFmtStr(d.tourists)}</td>
          <td class="td-custom-css">${dollarNumFmt(d.amount)}</td>
        </tr>
       `;  
     });
     
    let total_tourists = getArraySum(data.map(d=>parseInt(d.tourists)));

     let tfoot = "";
     let table = dataTableTemplate('affilate-invoice-due-amount', thead, tbody.join(""), tfoot, "tbody-sm");
      $("#invoiceinsights-invoice-graph-aff-sum").html( table );
  }

  /**
   * @function renderBillableView
   * @description fetch the billable data
   * @returns void
   */
  renderBillableView() {
    this.billableTable = true;
    // fetch the data and render the revenue table
    $.get(this.url.replace("_STATUS_", "billable")+'?tourDate=1').done(this.renderBillableViewOk.bind(this)).fail(this.renderViewError).always(hideLoading);
  }

  /**
   * @function renderBillableViewOk
   * @description success method for billable table view
   * @param {*} data response from invoice report controller.  
   * @returns void
   */
  renderBillableViewOk(data) {
    this.billableTable = true;
    // add table to the main view.
    let refreshbtn = this.dataRefreshAction('refresh-billable-invoice-table', this.renderBillableView.bind(this), { create_invoice: true }  );
    $("#invoiceinsights-billing-table-container").html(refreshbtn);
    $('#invoiceinsights-billing-table-container').append(this.invoiceTableTemplate('invoiceBilliable-table'));
    // display a data billable table of invoice data. 
    this.renderBillableTable(data);
  }

  /**
   * @function renderTable
   * @description render a data table of invoice table. 
   * @param {*} data invoice report data
   * @returns void 
   */
  renderBillableTable(data) {
    this.billableData = data;    
    // data table columns 
    let cols = [
      { data: 'booking_id', title: 'Booking ID', render: this.renderBookingLink, className: 'th-custom-css !whitespace-normal' },
      { data: 'affiliate_name', title: 'Name', render: this.renderTruncatedString, className: 'th-custom-css !whitespace-normal' },
      { data: 'tour_name', title: 'Tour Pkg', className: 'th-custom-css !whitespace-normal' },
      { data: 'tour_date', title: 'Tour Date', className: 'th-custom-css' },
      { data: 'tour_time_from', title: 'Tour Time', className: 'th-custom-css !whitespace-normal' },
      { data: 'tourists', title: 'Tourist', className: 'th-custom-css !whitespace-normal' },
      { data: 'infants', title: 'Infants', className: 'th-custom-css !whitespace-normal' },
      { data: 'invoice_status', title: 'Status', render: this.renderStatus, className: 'th-custom-css !whitespace-normal' },
      { data: 'booking_total', title: 'Booking Total', render: this.renderBookingTotal, className: 'th-custom-css !whitespace-normal' },
      { data: 'refund_total', title: 'Refund', render: this.renderCurrency, className: 'th-custom-css !whitespace-normal' },
      { data: 'payment_total', title: 'UnPaid', render: this.renderAmountUnPaid, className: 'th-custom-css !whitespace-normal' },
      { data: 'payment_total', title: 'Paid', render: this.renderAmountPaid, className: 'th-custom-css !whitespace-normal' },]
    // render the data table
    let exportTitle = `Billable-Bookings-${this.fromDate}-to-${this.toDate}`;
    let btns =  [
      { extend: 'excelHtml5', className: 'btn btn-sm', title: exportTitle, filename: exportTitle},
      { extend: 'csvHtml5', className: 'btn btn-sm', title: exportTitle, filename: exportTitle },
    ];

    $('#invoiceBilliable-table').DataTable({ data: data, dom: 'Bfrtip', buttons: btns, pageLength: 100, columns: cols, info: false });

  }

  /**
   * @function dataTableCols  
   * @description Standard data table columns
   * @returns {*} object for datatable col. def. 
   */
  dataTableCols() {
    return [
      { data: 'booking_id', title: 'Booking', render: this.renderBookingLink, className: 'td-custom-css' },
      { data: 'invoice_id', title: 'ID', render: this.renderInvoiceIDLink, className: 'td-custom-css' },
      { data: 'customer', title: 'Name', className: 'td-custom-css' },
      { data: 'tour_name', title: 'Tour Pkg', render: this.renderTruncatedString, className: 'td-custom-css' },
      { data: 'tour_date', title: 'Tour Date', className: 'td-custom-css' },
      { data: 'tour_time_from', title: 'Tour Time', className: 'td-custom-css' },
      { data: 'tourists', title: 'Tourist', className: 'td-custom-css' },
      { data: 'invoice_status', title: 'Status', render: this.renderStatus, className: 'td-custom-css' },
      { data: 'booking_total', title: 'Amount', render: this.renderCurrency, className: 'td-custom-css' },
      { data: 'refund_total', title: 'Refund', render: this.renderCurrency, className: 'td-custom-css' },
      { data: 'payment_total', title: 'UnPaid', render: this.renderAmountUnPaid, className: 'td-custom-css' },
      { data: 'payment_total', title: 'Paid', render: this.renderAmountPaid, className: 'td-custom-css' },
    ]
  }

  /**
   * @function renderNestedTable
   * @description render a data table of invoice table. 
   * @param {*} data invoice report data
   * @returns void 
   */
    renderNestedTable() {
      this.unpaidTable = true;
      $("#invoiceinsights-nested-table-container").css('display', 'block');
      $("#invoiceinsights-invoice-table-container").css('display', 'none');
      $("#dropdownMenuButton1").css('display', 'none');
      $.get(this.url.replace("_STATUS_", this.status)).done(this.renderNestedTableOk.bind(this)).fail(this.renderViewError);
    }


    /**
     * @function renderNestedTableOk
     * @description handle the response from the the renderUnpaidtable request above 
     * @returns void renders a data table 
     */
    renderNestedTableOk(data) {
      this.unpaidInvoices = data;
      let cols = this.dataTableCols();
      let refreshbtn = this.dataRefreshAction('refresh-unpaid-invoice-table', this.renderNestedTable.bind(this), 
      {filters: { callback: this.displayFilters.bind(this)  }, 
       invoice: { callback: this.renderCurrentInvoices.bind(this)}
      } );
      $("#invoiceinsights-nested-table-container").html(refreshbtn);
      $('#invoiceinsights-nested-table-container').append(this.invoiceTableTemplate('invoiceNestedTable'));

      let exportTitle = `Expanded-Invoice-List-${this.fromDate}-to-${this.toDate}`;
      let btns =  [
        { extend: 'excelHtml5', className: 'btn btn-sm', title: exportTitle, filename: exportTitle},
        { extend: 'csvHtml5', className: 'btn btn-sm', title: exportTitle, filename: exportTitle },
      ];

      $("#invoiceNestedTable").DataTable({ data: data,  dom: 'Bfrtip', buttons: btns, pageLength: 100, columns: cols, autoWidth: false, info: false });
      $("#invoiceNestedTable").css('table-layout', 'fixed');
      
    }

    /**
     * @function viewUnpaidBooking
     * @description display the booking detail page
     * @param {*} evt 
     */
    viewUnpaidBooking(evt){
      evt.preventDefault();
      let id = $(evt.target).data('id');
      $("#invoice-insights-bookings-modal").modal('show');
      let html = `<iframe style="border: 0 none; width: 100vw; height: 98vh;max-width:100%;" src="${this.admin}/tour-operator/booking/${id}?modal=1"></iframe>`;
      console.log(id);  
      $(".invoice-insight-close-bookig-edit").data('id', id);
      $("#viewUnpiadBookingTable").html( html );
    }


    /**
     * @function triggerUpdateFilter
     * @description update the unpaid table by the seleted status tags. 
     * @param {*} _evt 
     * @returns 
     */
    triggerUpdateFilter(_evt){
      let values = $("#invoice-insights-filter-status-select").val();      
      if(values.length <= 0){
        return false;
      }
      this.status = values.join("+");
      this.renderNestedTable();
    }


    static updateDays(){
      let start_date = $("#booked_start_date").val();
      let end_date = $("#booked_end_date").val();
      let num_days = moment(end_date).diff(moment(start_date), 'days');
      $("#booked_number_days").html(num_days + 1);
    }

    /**
     * @function renderBookedTable
     * @description hit the aff. booked report and display result
     * @returns void init. datatable. 
     */
    renderBookedTable(evt=false){


      $("#invoiceinsights-booked-table-toolbar").css('display', 'none');
      this.bookedTable = true;
      let update = (evt) ? $(evt.target).data('update') : false;
      let start_date = moment().format('YYYY-MM-DD');
      let end_date = moment().add(7, 'd').format('YYYY-MM-DD');

      if(update){
        start_date = $("#booked_start_date").val();
        end_date = $("#booked_end_date").val();
      }else{
        $("#booked_start_date").val(this.fromDate);
        $("#booked_end_date").val(this.toDate);
        start_date = $("#booked_start_date").val();
        end_date = $("#booked_end_date").val();
      }

      $(document).off('click', ".download-booked-data").on("click", ".download-booked-data",  (evt)=>{
        evt.preventDefault();
        let bdlurl = this.bookURL;
        window.location = `${bdlurl}?fromDate=${start_date}&toDate=${end_date}&customerId=${this.customerID}&tourPackageId=${this.tourPkgId}&tourOperatorId=${this.tourOptId}&csv=1`;
      });

    
      InvoiceInsightsReport.updateDays();
      let url = `${this.admin}/tour-operator/report/affiliate-report`;
      let uri = `/${start_date}/${end_date}/${this.customerID}/${this.tourOptId}/${this.tourPkgId}`;
      $("#invoiceinsights-booked-table-container").html(loadingSpinner());
      $.get(`${url}${uri}`).done(this.renderBookedTableOk.bind(this)).fail(this.renderViewError);
    }

    /**
     * @function renderBookedTableOk
     * @description render a list of upcoming aff. bookings 
     * @param {*} response json aff. booking report 
     */
    renderBookedTableOk(response){
      this.bookingData = response;

      $("#invoiceinsights-booked-table-toolbar").css('display', 'block');

      $(document).off('change', "#bookedViewPaidOnly").on("change", "#bookedViewPaidOnly",  ()=>{
        $("#invoiceinsights-booked-table-container").html(loadingSpinner());
        setTimeout(()=>this.renderBookedTableOk(this.bookingData), 300);
      });


      const {booking_amount, paid_amount, refund_amount, total_tourists, unpaid_amount} = response;
      $('#invoiceinsights-booked-table-container').html(accordionTemplate("invoiceinsights-booked-accordion"));
      // $('#insights-booked-report-total').html(`
      //       <hr />
      //       <div class="row mt-1 nat-rounded">
      //         <div class="col nat-grid">
      //         <span class="text-muted">Tourists:</span> ${toFmtStr(total_tourists)}
      //         </div>
      //         <div class="col nat-grid">
      //           <span class="text-muted">Total:</span> ${dollarNumFmt(booking_amount)}
      //         </div>
      //         <div class="col nat-grid">
      //         <span class="text-muted">Paid:</span> ${dollarNumFmt(paid_amount)}
      //         </div>
      //         <div class="col nat-grid">
      //         <span class="text-muted">Refund:</span> ${dollarNumFmt(refund_amount)}
      //         </div>
      //         <div class="col nat-grid">
      //         <span class="text-muted">Unpaid:</span> ${dollarNumFmt(unpaid_amount)}
      //         </div>
      //     </div>`);
      
    
      this.bookedTable = true;
      let idx = 0;
      let skip = ['booking_amount','paid_amount','refund_amount','total_tourists','unpaid_amount'];
      for( let key in response ){
          if(skip.includes(key)){
            continue;
          }
          let data = response[key];
          this.bookedTableLoop(data, key, idx);
          idx++;  
      }
    }

   /**
    * @function bookedTableLoop
    * @description loop over the booked json result nested by date. 
    * @param {*} data booking details
    * @param {*} date date for bookings 
    * @param {*} idx index of the loop 
    * @returns void init datatable. 
    */
   bookedTableLoop(collection, date, idx){
      let data = collection.filter((d)=>{ 
       return ( d.billable_invoiced_status === null && d.payment_amount === 0 && d.booking_details.payment_status != "Completed");
      });

      let table = this.invoiceTableTemplate(`invoiceBookedTable${idx}`);
      // bookingamount already accounts for custom charges AND add-ons, paid or still-owed (see
      // renderBookingTotalWithCustomCharge above) — sum that directly instead of re-deriving from
      // raw booking_total/custom_charge/unpaid_custom_charges, which silently dropped add-ons.
      let total = getArraySum(data.map((d)=>parseFloat(d.bookingamount)));
      let grand_total = getArraySum(collection.map((d)=>parseFloat(d.bookingamount)));
      let title = `<span>${date} | <span class="text-muted text-[#7C706A]"> ${collection.length} total bookings ${dollarNumFmt(grand_total)}  | </span> <span class=" ${ data.length === 0 ? 'text-success text-[#15803D]' : 'text-danger text-[#B91C1C]' }"> ${data.length} unpaid bookings ${dollarNumFmt(total)} </span> </span>`;
      let exit = false;
      let showOnlyPaid = $("#bookedViewPaidOnly").prop('checked');
      
      if (collection.length === 0 || data.length === 0){

        if (showOnlyPaid){
          exit = true;
          table =  "<span class='text-success text-[#2FA136]'>No Bookings</span>";
        }

      }

      $('#invoiceinsights-booked-accordion').append(accordionItemTemplate(idx, title, table, false));
      if(exit===true){ return; } 

      // data table columns 
      let url = `${this.admin}/tour-operator/booking`;
      let colors = InvoiceInsightsReport.colors();
      let icons  = InvoiceInsightsReport.icons();
      let cols = [
        { data: 'bookingid', title: 'Booking ID', className: 'td-custom-css', render: (data, _type, _row, _meta)=>{
          return `<a href="${url}/${data}?modal=1" data-id="${data}" title="display booking detail" class="view-booking-in-modal">${data}</a>`;
        } },
        { data: 'name', title: 'Name', className: 'td-custom-css', render: this.renderTruncatedString },
        { data: 'rate', title: 'Rates', className: 'td-custom-css', render: this.renderRates },
        { data: 'package', title: "Tour Pkg", className: 'td-custom-css', render: this.renderTruncatedString},
        { data: 'bookingid', title: 'Tour Date', className: 'td-custom-css', render: this.renderTourDate },
        { data: 'bookingid', title: 'Tour Time', className: 'td-custom-css', render: this.renderTourTime },
        { data: 'TouristTotal', title: 'Tourist', className: 'td-custom-css', render: (data)=>toFmtStr(data) },
        { data: 'payment_type', title: 'Status', className: 'td-custom-css text-center', render: ( data, _type, row, meta )=> {
            let term = (data) ? data.toLowerCase() : 'unpaid';
            let badge  = `<span id="booked-table-row-${row.bookingid}" data-idx="${idx}" class="badge text-white flex items-center justify-center mt-1 bage-sm py-1 px-3 rounded-full ${term} invoice-row-${row.bookingid}" data-colnum="${meta.col}" data-rownum="${meta.row}" style="background-color:${_.has(colors, term) ? colors[term] : "#ccc"}"> ${term}</span>`;
            return `${row.booking_details.status}<br /> 
                    ${badge}`;
        } },
        { data: 'booking_details.booking_total', title: 'Booking Total', className: 'td-custom-css', render: this.renderBookingTotalWithCustomCharge },
        { data: 'payment_amount', title: 'Paid', className: 'td-custom-css', render: this.renderAmountPaid },
        { data: 'refundamount', title: 'Refund', className: 'td-custom-css', render: this.renderCurrency },
        { data: 'unpaid_amount', title: 'UnPaid', className: 'td-custom-css', render: (data)=>dollarNumFmt(data) },
      ]
      
      // render the data table

      if(!$("#bookedViewPaidOnly").prop('checked')){
        data = collection;
      }

      $(`#invoiceBookedTable${idx}`).DataTable({ data: data, pageLength: 100, columns: cols, info: false });
   }



   checkBookingForUpdate(evt){
    let id = $(evt.target).data('id');
    console.log(id);
    let el = $(`#booked-table-row-${id}`);
    let row_num = el.data('rownum');
    let idx = el.data('idx');
    let temp = $(`#invoiceBookedTable${idx}`).DataTable().row(parseInt(row_num)).data();

    let url = `${this.admin}/tour-operator/booking/history-and-transactions/${id}`;
    $.get(url).done((data)=>{
      if(data.transactions.length > 0){
          try{
            let tr = data.transactions[0];

            if (_.has(temp, 'booking_details')){
              temp.booking_details.payment_status = tr.status;
              temp.billable_invoiced_status = tr.via
              $(`#invoiceBookedTable${idx}`).dataTable().fnUpdate(temp, row_num, undefined, false);
              $(`#booked-table-row-${data.id}`).html(  (tr.via === 'Invoiced') ? 'Billable' : tr.via);
              $(`#booked-table-row-${data.id}`).css('background-color', 'green');
            }
       
          }catch(e){
            console.log(e);
          }

      }

    }).fail(this.renderViewError);
  }


  /**
   * @function displayFilters
   * @description display a modal with filters  
   * @returns void 
   */
    displayFilters(_evt=null){
      $("#invoice-insights-filter-modal").modal("show");
    }


  /**
   * @function renderCreateInvoiceForm
   * @description display the create invoice form 
   * @returns void
   */
  renderCreateInvoiceForm(_evt) {

    let bookings = this.billableData.map((d) => d.booking_id);
    if (bookings.length === 0) {
      swal('No Bookings Selected', '', 'warning');
      return;
    }

    let stripeEnabled = this.billableData[0].stripe_enabled;
    

    let grp = _.groupBy(this.billableData, d => d.affiliate_id);
    this.affiliateGroupFormData = [];

    for (let key in grp) {
      let aff = grp[key];
      this.affiliateGroupFormData.push({
        meta: {
          id: aff[0].affiliate_id,
          name: aff[0].affiliate_name,
          booking_ids: aff.map((a) => a.booking_id),
          status: "pending",
        },
        tour_operator_id: this.tourOptId,
        tour_package: this.tourPkgId,
        customer_id: key,
        stripe_invoice_enabled: aff[0].stripe_invoice_enabled,
        from_date: this.fromDate,
        to_date: this.toDate,
        due_date: new Date().toLocaleDateString(),
        amount: aff.reduce((partialSum, a) => partialSum + ( parseFloat(a.payment_total) - parseFloat(a.refund_total) ) , 0),
        refund: getArraySum( aff.map((a) => ( parseFloat(a.refund_total)  )   ) ),
        _token: $('meta[name="csrf-token"]').attr('content'),
      })
    }

    $('#invoiceinsights-billing-form-container').html(this.createInvoiceTemplate({
      tour_operator_id: this.tourOptId,
      tour_pkg_id: this.tourPkgId,
      cus_id: this.customerID,
      from_date: this.fromDate,
      to_date: this.toDate,
      admin: this.admin
    }));
    $("#createInvoiceMessageDiv").html(`Generate Invoices: ${this.fromDate} - ${this.toDate}`);
    $("#createInvoiceList").empty().html(this.createInvoiceAffiliateList(this.affiliateGroupFormData, stripeEnabled));
    $("#modal-invoice-create-from-seleted-bookings").modal("toggle");
    $('[data-toggle="tooltip"]').tooltip();

  }

  /**
   * @function createInvoiceAsync store/with/stripe
   * @description handle create invoice form submit calls submitInvoiceData 
   * @param {*} evt 
   */
  createInvoiceAsync(evt) {
    evt.preventDefault();
    $("#generate-invoice-btn").prop('disabled', true);
    this.createInvoiceAsyncSubmit(0, this.affiliateGroupFormData.length - 1);
  }
  /**
   * @function createInvoiceAsyncSubmit
   * @description handle create invoice form submit
   * @param {*} evt 
   */
  createInvoiceAsyncSubmit(idx, len) {

    if (idx <= len) {
      const dat = this.affiliateGroupFormData[idx];      
      const is_enabled = $(`#enable-invoice-for-${dat.meta.id}`).prop('checked');
      let nxt = idx += 1;

      if( !is_enabled ){
        $("#invoice-form-header").html( `<span class='text-info'> ${dat.meta.name} skipped </span>` );
        $(`#invoice-status-${dat.meta.id}`).html("skipped").addClass('text-info');

        this.updateInvoiceFormProgressBar(idx, len);
        setTimeout(()=>this.createInvoiceAsyncSubmit(nxt, len), 1000);
        return false;
      }

      $(`#invoice-status-${dat.meta.id}`).html("sending...");
      $("#invoice-form-header").html( `<span class='text-success text-[#2FA136]'> ${dat.meta.name} creating invoice... </span>` );

      let data = {
        _token: $('meta[name="csrf-token"]').attr('content'),
        due_date: $(`#due_date-${dat.meta.id}`).val(),
        tour_operator_id: this.tourOptId,
        tour_package: this.tourPkgId,
        customer_id: dat.customer_id,
        from_date: this.fromDate,
        to_date: this.toDate,
        amount: dat.amount,
        refund: dat.refund,
      };
      $.ajax({
        type: 'POST',
        url: `${this.admin}/tour-operator/invoice/store-with/stripe`,
        data: data,
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        success: (res) => {
          this.updateInvoiceFormProgressBar(idx, len);
          if (res.success === null || res.success === "null") {
            $(`#invoice-status-${dat.meta.id}`).html("error").addClass('text-[#A32624]').attr("title", res.error);
            $(`#invoice-msg-display-${dat.meta.id}`).html(`<span class="text-danger text-[#A32624]">${dat.meta.name}: ${res.error}</span>`);
          } else {
            $(`#invoice-status-${dat.meta.id}`).html("success").addClass('text-[#2FA136]');
            $(`#invoice-msg-display-${dat.meta.id}`).html(`<span class="text-success text-[#2FA136]">${dat.meta.name}: ${res.success}</span>`);
          }
           setTimeout(()=>this.createInvoiceAsyncSubmit(nxt, len), 1000);
        },
        error: (err) => console.log(err)
      });
    } else {
      const msg = 'All Invoices Created, You May Close This Form.';
      this.resetTabState();
      this.renderBillableView();
      this.renderInvoiceDetail();
      $("#generate-invoice-btn").prop('disabled', false);
      $("#invoice-form-header").html( `<span class='text-success text-[#2FA136]'> ${msg} </span>` );
      swal(`Invoice Created:`, msg, 'success')
    }
  }

  /**
   * Display a progress bar on the invoice form
   * @param {*} idx 
   * @param {*} length 
   */
  updateInvoiceFormProgressBar(idx, length){
    let len = (length+1);
    const progress = (idx <= 0) ? `0` : Math.ceil(( idx /  len  ) * 100);
    $("#invoice-create-progress-bar").css('width', `${progress}%`);
  }


  /**
   * @function renderRateForGraph
   * @description fetch data to display a rate for bar chart. 
  */
  renderRateForGraph(){

    this.rateforDisplay = 'total_amount';
    $("#RateForChart").html("<select class='float-end updateRateforGraph nat-select-sm form-custom-input !w-auto'> <option value='total_amount'>Total Reveune</option> <option value='tourists'>Tourists</option> <option value='total_permit_fee'>Permit Fees</option>  </select>")
    let url = `${this.admin}/tour-operator/invoice/invoice-sum-by-status`;
    let uri = `ratefor=1&aggregate=1&from_date=${this.fromDate}&to_date=${this.toDate}&customerId=${this.customerID}&tourOpId=${this.tourOptId}`
    $.get(`${url}?${uri}`).done(this.renderRateForGraphOk.bind(this)).fail(this.renderViewError);
  }

  /**
   * 
   * @param {*} data { "Adults (Ages 13 & Up)": {
        "total_amount": 224164.2,
        "tourists": 3707,
        "total_rate": 33105.31000000034,
        "total_permit_fee": 5360,
        "rate": "49.06" } .. }    
   */
  renderRateForGraphOk(data){
    if(data.length === 0){
      $("#RateForChart").html("No Invoice Data.");
      return false;
    }


    this.ratefordata = data;
    $(document).off('change', ".updateRateforGraph").on('change', '.updateRateforGraph', this.renderRateForGraphUpdate.bind(this) );
    $("#RateForChartDisplay").html('<canvas id="RateForBarChart"></canvas>');
    let cols = {};
    for(let idx in data){
        cols[idx] = data[idx][this.rateforDisplay];
    }

    let title = titleize(this.rateforDisplay);
    let fmrt  = (this.rateforDisplay === 'tourists') ? false : true;
    this.renderBarChart("RateForBarChart", `Affliate ${title} by rate type`, cols, fmrt );
    
  }

  renderRateForGraphUpdate(evt){
    this.rateforDisplay = $(evt.target).val();
    this.renderRateForGraphOk(this.ratefordata);
  }



  /**
   * @function renderChart
   * @description fetch the data for the pie chart.
   * @param {*} response invoice reponse data
   */
   renderChart(){
      let url = `${this.admin}/tour-operator/invoice/invoice-sum-by-status`;
      let uri = `from_date=${this.fromDate}&to_date=${this.toDate}&customerId=${this.customerID}&tourOpId=${this.tourOptId}`
      $.get(`${url}?${uri}`).done(this.renderChartOk.bind(this)).fail(this.renderViewError);
      //Render the rate for graph
      this.renderRateForGraph();
   }

   /**
    * @function renderChartOk
    * @description add the cost pie chart to the screen
    * @param {*} response 
    * @returns void 
    */
  renderChartOk(response) {
    //let the class know the chart is loaded. 
    this.invoiceChart = true;
    let refreshbtn = this.dataRefreshAction('refresh-invoice-graph', this.renderChart.bind(this), { cls: 'float-end' });
    $("#invoiceinsights-invoice-graph-container").html(refreshbtn);
    let dataset = {};
    for(let key in response){
      let d = response[key]
      if(d.external_status!== ""){
        if ( d.external_status !== "open"){
          d.status = d.external_status;
        }
      }

      let idx = capitalized(d.status);
      if(!_.has(dataset, idx)){
        dataset[idx] = 0;
      }
      dataset[idx] += parseFloat(d.amount);
    }
    //let dataset = this.formatChartData(data);
     $("#pie-chart-loader").css("display", "none");
      const invoiceColors = (labels) => {
      let schema = [];
      let colors = {
        "Draft": "#85929e",
        "Sent": "#85c1e9",
        "Viewed": "#bb8fce",
        "Approved": "#f6f6f6",
        "Partial": "#76d7c4",
        "Paid": "#7dcea0",
        "Overdue": "#f1948a",
        "Unpaid": "#DF9A8C",
        "Cancelled": "#B7201A",
        "Billable": "#ffc107",
      };
      labels.forEach((l) => {
        if (colors.hasOwnProperty(l)) {
          schema.push(colors[l]);
        }
      });
      return schema;
    }

    if(this.chartIsRendered){
      this.chartIsRendered.destroy();
    }

    this.chartIsRendered =  new Chart(document.getElementById("invoicePieChart").getContext('2d'), {
      type: 'pie',
      data: {
        labels: Object.keys(dataset),
        datasets: [{
          backgroundColor: invoiceColors(Object.keys(dataset)),
          data: Object.values(dataset)
        }]
      },
      options: {
        tooltips: {
          callbacks: {
            label: function (tooltipItem, data) {
              let label = data.labels[tooltipItem.index];
              let value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
              return ' ' + label + ' ' + dollarNumFmt(value, currencyFormation.symbol) + '';
            }
          }
        },
        responsive: true,
        plugins: { legend: { position: 'top', }, title: { display: true, text: 'Invoice Status'}
        }
      },

    });
  }


  renderCompTable(){
    //get comp affliate report
    this.compTable = true;
    let url = `${this.admin}/tour-operator/report/affiliate-comprehensive-report`;
    let uri = `/${this.fromDate}/${this.toDate}/${this.customerID}/${this.tourOptId}/${this.tourPkgId}`;
    $.get(`${url}${uri}`).done(this.renderCompTableOk.bind(this)).fail(this.renderViewError);
  }

  renderCompTableOk(data){

    //console.log(data);
    
    let cols = {
      "Booking Amount": data.booking_amount,
      "Refund": data.refund_amount,
      "Cancelled": data.cancellation_amount,
      "Unpaid": data.unpaid_amount,
      "Net": data.total_net_amount,
    }


    $("#comprehensiveChart").html('<canvas id="CompBarChart"></canvas>');
    this.renderBarChart("CompBarChart", "Affliate Comprehensive Data", cols );

    cols = {
      "Card": data.card_amount,
      "Cash": data.cash_amount,
      "Invoiced": data.invoiced_amount
    }
    $("#comprehensivePaymentDistroChart").html('<canvas id="compPayDistroChart"></canvas>');
    this.renderBarChart("compPayDistroChart", "Affliate Income By Payment Type", cols );
    this.renderCompDataTable(data);
    //this.renderCompDensityChart(data);

  }


  renderCompDensityChart(data){
    let collection = {};
    for(let key in data.comprehensive_data){
      let d = data.comprehensive_data[key];
      if(!_.has(collection, d.name)){
          collection[d.name] = { tourist: 0, amount: 0  };
      }
      
      collection[d.name].tourist += parseInt(d.TouristTotal);
      collection[d.name].amount += parseFloat(d.bookingamount);

    }
    $("#comprehensiveDensityChart").html('<canvas id="compDensityChart"></canvas>');
  
    
    let touristData = [];
    for(let key in collection){
      touristData.push(collection[key].tourist);
    }

    const touristDensity = {
      label: 'Tourist Total',
      data: touristData,
      backgroundColor: 'rgba(0, 99, 132, 0.6)',
      borderColor: 'rgba(0, 99, 132, 1)',
      yAxisID: "y-axis-tourist"
    };

    

    let bookingData = [];
    for(let key in collection){
      bookingData.push(collection[key].amount);
    }
    const bookingAmount = {
      label: 'Booking Total',
      data: bookingData,
      backgroundColor: 'rgba(99, 132, 0, 0.6)',
      borderColor: 'rgba(99, 132, 0, 1)',
      yAxisID: "y-axis-amount"
    };
     
    const chartData = { labels: Object.keys(collection), datasets: [touristDensity, bookingAmount] };
     
    const chartOptions = {
      tooltips: {
        callbacks: chartJSLableNumberFmt()
      },
      scales: {
        xAxes: [{
          barPercentage: 1,
          categoryPercentage: 0.6
        }],
        yAxes: [{
          ticks: chartJSYAxisNumberFmt(),
          id: "y-axis-tourist"
        }, {
          ticks: chartJSYAxisNumberFmt(),
          id: "y-axis-amount"
        }]
      }
    };
     
    const barChart = new Chart( document.getElementById("compDensityChart"), 
    { type: 'bar', data: chartData, options: chartOptions});

  }


  renderCompDataTable(data){
    // aff. comp report download 
    let durl =`${this.compURL}?fromDate=${this.fromDate}&toDate=${this.toDate}&customerId=${this.customerID}&tourPackageId=${this.tourPkgId}&tourOperatorId=${this.tourOptId}&csv=1`;
    let download = `<div class="my-2 text-right"><a href="${durl}" target="_blank" class="btn btn-sm btn-secondary btn-info-small" style="position: relative; z-index: 1;"> <i class="fa fa-download"></i> </a></div>`;
    let div = $("#comprehensiveTable");
    div.html(this.comprehensiveTotalsTempl(data));
    div.append(download);
    div.append(dataTableTemplate('comprehensiveDataTable', '', '', '', 'tbody-sm') );
    let url = `${this.admin}/tour-operator/booking`;

    /**
     *  ID	Name	Tourists	Package	Booking Date	Created Date	Total Tourist	Booking Status	Booking Amount	Paid Amount	Refund Amount	Unpaid Amount
     */
    // let colors = InvoiceInsightsReport.colors();
    // let icons  = InvoiceInsightsReport.icons();
    let cols = [
      { data: 'booking_details.bookingid', title: 'Booking ID', className: 'th-custom-css whitespace-nowrap', render: (data, _type, _row, _meta)=>{
        return `<a href="${url}/${data}?modal=1" data-id="${data}" title="display booking detail" class="view-booking-in-modal">${data}</a>`;
      } },
      { data: 'name', title: 'Name', className: 'th-custom-css whitespace-nowrap', render: this.renderTruncatedString },
      { data: 'rate', title: 'Rates', className: 'th-custom-css whitespace-nowrap', render: this.renderRates },
      { data: 'package', title: "Tour Pkg", className: 'th-custom-css whitespace-nowrap', render: this.renderTruncatedString},
      
      
      { data: 'booking_details.bookingdate', title: 'Booked Date', className: 'th-custom-css whitespace-nowrap', render: (data, _type, row, _meta)=> {
          return `${data} - ${row.booking_details.time_from}`;
      }},
      { data: 'booking_details.created_at', title: 'Created Date', className: 'th-custom-css whitespace-nowrap',},
      { data: 'TouristTotal', title: 'Tourist', className: 'th-custom-css whitespace-nowrap', render: (data)=>toFmtStr(data) },
      // { data: 'bookingid', title: 'Tour Date', render: this.renderTourDate },
      // { data: 'bookingid', title: 'Tour Time', render: this.renderTourTime },
      
      { data: 'booking_details.status', title: 'Booking Status', className: 'th-custom-css whitespace-nowrap', render: ( data, _type, row, _meta )=> {

          return `${InvoiceInsightsReport.renderBookingStatus(data, row.booking_details, row.payment_type)}`;
      } },
      { data: 'bookingamount', title: 'Booking Total', className: 'th-custom-css whitespace-nowrap', render: this.renderCurrency },
      { data: 'payment_amount', title: 'Paid', className: 'th-custom-css whitespace-nowrap', render: this.renderAmountPaid },
      { data: 'refundamount', title: 'Refund', className: 'th-custom-css whitespace-nowrap', render: this.renderCurrency },
      { data: 'unpaid_amount', title: 'UnPaid', className: 'th-custom-css whitespace-nowrap', render: (data)=>dollarNumFmt(data) },
    ]
    // render the data table
    $(`#comprehensiveDataTable`).DataTable({ data: data.comprehensive_data, pageLength: 10, columns: cols, info: false });

  }


 static renderBookingStatus(status, d, payment_type){

  let class_name = "text-warning text-[#ed9c45]";
  if(d.billable_invoiced_status == "Billable"){
      class_name = "text-warning text-[#ed9c45]";
  }else if(d.billable_invoiced_status == "Invoiced"){
      class_name = "text-danger text-[#A32624]";
  }else if(d.billable_invoiced_status == "Paid"){
      d.billable_invoiced_status = "Invoiced";
      class_name = "text-success text-[#2FA136]";
  }

  if(d.billable_invoiced_status == null){
      if((payment_type)){
          d.billable_invoiced_status = payment_type;
          class_name = "text-success text-[#2FA136]";
      }else{
          d.billable_invoiced_status = '';
      }
    }
   status = (status === 'Cancelled') ? `<span class="text-danger text-[#A32624]">${status}</span>` : `<span class="text-success text-[#2FA136]">${status}</span>`;
   return `${status} <br /> <span class="${class_name}"> ${d.billable_invoiced_status}</span>`;

 }

  

  renderBarChart(id, title, cols, currency=true){


    const formater = (currency) ? dollarNumFmt : toFmtStr;

    const ctx = document.getElementById(id);
    new Chart(ctx, {
      type: 'bar',
      data: {
          labels: Object.keys(cols),
          datasets: [{
              label: title,
              data: Object.values(cols),
              borderColor: '#125D8A',
              backgroundColor: '#85929e',
              borderWidth: 0
          }]
      },
      options: {
          tooltips: {
              callbacks: chartJSLableNumberFmt(formater) 
          },
          animations: {
              tension: {
                  duration: 1500,
                  easing: 'linear',
                  from: 1,
                  to: 0,
                  loop: true
              }
          },
          responsive: true,
          scales: {
              xAxes: [{
                  gridLines: {
                      display: false
                  }
              }],
              yAxes: [{
                ticks: chartJSYAxisNumberFmt(),
                  gridLines: {
                      display: false
                  }
              }]
          }
      }
  });

  }



  /**
   * @function getInvoiceDetails
   * @description Display the invoice show view in a modal. 
   * Note: passing the blank param as the layout to imform the view to not render headers.
   * @param string uuid invoice UUID 
   * @returns void
   */
  static getInvoiceDetails(uuid) {
    let admin = (adminStatus) ? "/admin" : "";
    $("#invoice-insights-modal-view").empty().html("Loading...");
    $('#modal-invoice-detail').modal('show');
    $.get(`${admin}/tour-operator/invoice/show/${uuid}/blank`, (html) => {
      $("#invoice-insights-modal-view").empty().html(html);
    });
    $('#modal-invoice-detail').off('hidden.bs.modal').on('hidden.bs.modal', function () {
      $('#invoice-insights-modal-view').empty();
    });
  }

  getStripeInvoiceDetails(evt){
    let ele = $(evt.target);
    let uuid = ele.data('uuid');
    let stripeInvId = ele.data('stripeid'); 
    //uuid, stripeInvId
    ///invoice/stripe-invoice-read?uuid=3c6e42db-6214-438f-859c-8ecc0ad6e51a&stripe_id=in_1NR1BBFSeo8zJHh98XqirOAN
    console.log(uuid);
    console.log(stripeInvId);
    let admin = (adminStatus) ? "/admin" : "";
    let url = `${admin}/tour-operator/invoice/stripe-invoice-read?uuid=${uuid}&stripe_id=${stripeInvId}`;
    $.get(url).done((data)=>console.log(data)).fail((err)=>console.log(err.responseJSON.status));

  }

  /**
   * @function sendAsyncForm
   * @description helper method to submit forms async 
   * @param {*} props  { ele: event target, data: form data, msgDiv: id of the msg div , 
   *                     msgTxt: form label, msgOk: success msg, msgErr: error msg. }
   * @returns void
   */
  sendAsyncForm(props) {

    let onSuccess = (_data, _textStatus, xhr) => {
      if (xhr.status === 200) {
        $(props.msgDiv).html(props.msgOk);
      } else {
        swal(props.msgErr, '', 'warning');
      }
    }

    if (props.hasOwnProperty('callback')) {
      onSuccess = (_data, _textStatus, _xhr) => { props.callback(_data, this); };
    }

    $(props.msgDiv).html(props.msgSend);
    $.ajax({
      type: 'POST',
      url: $(props.ele).attr("action"),
      data: props.data,
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
      success: onSuccess,
      error: (err) => {
        swal(`${props.msgErr} ${err.message}`, '', 'warning');
      }
    });
    setTimeout(() => { $(props.msgDiv).html(props.msgTxt) }, 1000);
  }

  /** 
   * @function referenceInvoiceAsync
   * @description submit handler for send invoice ref number on invoice show view. 
   * Form on view: tour-backend/resources/views/users/admin/invoice/show.blade.php
   * @param {form} event from onsubmit
   * @returns void
   * */
  referenceInvoiceAsync(evt) {
    evt.preventDefault();
    let props = {
      ele: evt.target,
      data: { reference_invoice: $("#reference_invoice-async").val() },
      msgDiv: "#refNumberInvoiceAsyncFormMessage",
      msgTxt: "<strong>Reference Invoice</strong>",
      msgSend: "<strong>Reference Update...</strong>",
      msgOk: "<strong style='color:#198754;'>Reference Invoice Updated Successfully</strong>",
      msgErr: "Unable to submit form Please Try Again"
    }
    this.sendAsyncForm(props);
  }

  /**
   * @function sendInvoiceAsync
   * @description submit handler for send invoice email on invoice show view. 
   * form on view: tour-backend/resources/views/users/admin/invoice/show.blade.php
   * @param {form} evt 
   * @returns void
   */
  sendInvoiceAsync(evt) {
    evt.preventDefault();
    let props = {
      ele: evt.target,
      data: { email: $("#email-async").val() },
      msgDiv: "#sendInvoiceEmailAsyncFormMessage",
      msgTxt: "<strong>Send Invoice</strong>",
      msgSend: "<strong>Sending Invoice...</strong>",
      msgOk: "<strong style='color:#198754;'>Email Sent Successfully</strong>",
      msgErr: "Unable to send Email Please Try Again"
    }
    this.sendAsyncForm(props);
  }

  /**
   * @description Download the Revenue Report from /app/Repositories/Report/ReportRepository@InvoiceInsights to populate a data table.
   * @requires string url rotue "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'invoice_nsights_report_export')}}"
   * @returns void
   */
  static invoiceInsightsDownload(url) {
    const from = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
    const to = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
    const params = `tourOperatorId=${$('#tour_operator').val()}&tourPackageId=${$("#tour_package").val()}&fromDate=${from}&toDate=${to}&groupBy=${$("#groupby-select").val()}&paymentType=Card&csv=1`;
    window.location = `${url}?${params}`;
  }

  /**
   * @function renderCurrentInvoices
   * @description Get list of invoices for selected aff. customer
   * @returns void
   */
  renderCurrentInvoices() {
    this.invoiceTable = true;
    $("#invoiceinsights-nested-table-container").css('display', 'none');
    $("#invoiceinsights-invoice-table-container").css('display', 'block');
    $("#dropdownMenuButton1").css('display', 'block');
    let url = `${this.admin}/tour-operator/invoice/list/${this.customerID}/${this.tourOptId}/`;
    let params = `to_date=${this.toDate}&from_date=${this.fromDate}&package_id=${this.tourPkgId}&displayVoided=${this.displayVoided}`; 
    $.get(`${url}?${params}`).done(this.renderCurrentInvoicesOk.bind(this)).fail((err) => console.log(err));
  }

  /**
   * 
   */
  filterCurrentInvoices(evt) {
    evt.preventDefault();
    
    let ck = $("#toggle-void-invoice-view").prop('checked');
    console.log(ck);
    this.displayVoided = (ck) ? 1 : 0;
    this.renderCurrentInvoices();
  }


  viewAllInvoice(evt){
    let ele = $(evt.target);
    let range = ele.data('range');
    let year = moment().year();
    if (range === 'last-year'){
      year = year - 1;
    }

    let fr_date = `${year}-01-01`;
    let to_date = `${year}-12-31`;
    if(  range === 'selected-date-range'  ){
      fr_date = this.fromDate;
      to_date = this.toDate;
    }
    
    let url = `${this.admin}/tour-operator/invoice/list/${this.customerID}/${this.tourOptId}/`;
    let params = `to_date=${to_date}&from_date=${fr_date}&package_id=${this.tourPkgId}`; 
    $.get(`${url}?${params}`).done(this.renderCurrentInvoicesOk.bind(this)).fail((err) => console.log(err));

  }

  /**
   * @function renderCurrentInvoicesOk
   * @description success method for renderCurrentInvoices display datatable of invoices
   * @param {*} response 
   * @returns void
   */
  renderCurrentInvoicesOk(response) {

    let refreshbtn = this.dataRefreshAction('refresh-current-invoice-table', this.renderCurrentInvoices.bind(this), 
          { _filters: { callback: this.displayCurrentInvoiceFilters.bind(this)  },  
            view_all:  { callback: this.renderNestedTable.bind(this)}});
    $("#invoiceinsights-invoice-table-container").html(refreshbtn);
    $("#invoiceinsights-invoice-table-container").append(this.invoiceUpdateAllStatusTemplate());
    $("#invoiceinsights-invoice-table-container").append(this.invoiceTableTemplate("currentInvoice-table"));
    
    $(document).off('click', '#delete-all-invoices-in-data-table').on('click', '#delete-all-invoices-in-data-table', this.deleteAllInvoice.bind(this));
    $(document).off('click', '#update-all-invoices-in-data-table').on('click', '#update-all-invoices-in-data-table', this.updateAllInvoice.bind(this));
    $(document).off('click', '.view-all-invoices').on('click', '.view-all-invoices', this.viewAllInvoice.bind(this));

    

    const renderStatus = (data, _type, row, _meta) => 
    { 

      if(row.external_status === 'draft'){
        row.external_status = 'editable';
      }
      
      return `<span class="invoice-row-${row.id}">${data}</span> ${((row.external_status ?? '').toString().trim() !== ''  ? ` | ${row.external_status}` : '')}` 
    
    }
    const renderAction = (_data, _type, row, _meta) => {
      return ` <div style="min-width:50px text-center">
                <i class="text-info fa fa-eye smtxt mb-1 btn-info-small" onclick="InvoiceInsightsReport.getInvoiceDetails('${row.uuid}');" title="view" ></i> 
                ${ (row.status === 'draft') ? `<i class="text-info fas fa-pencil-alt smtxt btn-primary-small !font-bold update-invoice-data" data-invoice="${row.id}" data-status="${row.status}" title="update" ></i>` : ""  }
                <!-- i class="text-danger text-[#A32624] fas fa-trash smtxt invoice-delete" data-uuid="${row.uuid}" title="delete"></i -->
                </div>
              `;
    }
    // data table columns 
    let cols = [
      { data: 'id', title: 'ID', className: 'td-custom-css', render: (data, _type, row, _meta)=>(`<a href='#' onclick="InvoiceInsightsReport.getInvoiceDetails('${row.uuid}');" >${data}</a>`) },
      // { data: 'external_invoice_id', title: 'Stripe Info', className: 'td-custom-css', render: (data, _type, row, _meta)=>{
      //   if(data === ""){ return "n/a";}
      //   return `<i class="fab fa-stripe-s display-stripe-detail" style="cursor:pointer;" data-uuid="${row.uuid}" data-stripeid="${data}"></i>`}},
      { data: 'customer_name', title: 'Affiliate', className: 'td-custom-css', render: this.renderTruncatedString },
      { data: 'invoice_number', title: 'Inv#', className: 'td-custom-css', render: (d)=>(`<small>${d}</small>`) },
      { data: 'tour_package_name', title: 'Tour Pkg', className: 'td-custom-css', render: this.renderTruncatedString },
      { data: 'from_date', title: 'From Date', className: 'td-custom-css' },
      { data: 'to_date', title: 'To Date', className: 'td-custom-css' },
      { data: 'amount', title: 'Amount', className: 'td-custom-css', render: this.renderCurrency },
      { data: 'due_date', title: "Due Date", className: 'td-custom-css' },
      { data: 'reference_invoice', title: 'Ref#', className: 'td-custom-css', render: (data, _type, row, _meta)=>{  
        return `<a id="ref-${row.uuid}" class='refedit' style="cursor:pointer;" data-type="text" data-pk="${row.uuid}" data-val="${data}" 
        data-url="${this.admin}/tour-operator/invoice/reference-invoice/${row.uuid}" data-title="Enter Reference Number">${(data) ? data : ''}</a>`;
      } },
      { data: 'external_payment_link', title: "Link", className: 'td-custom-css', render: (data, _type, _row, _meta)=>{
        if(!data){return ""}
        return `<a href="${data}" target="_blank"><i class="fa fa-link"></i></a>`;
      }},
      { data: 'email_sent', title: 'Email Sent', className: 'td-custom-css' },
      { data: 'status', title: 'Status', className: 'td-custom-css', render: renderStatus },
      { data: 'id', title: 'Action', className: 'td-custom-css', render: renderAction },
      { data: 'uuid', title: 'Select', className: 'td-custom-css' }];
      this.currentInvoiceTableData = response.results;
      let exportTitle = `Invoice-List-${this.fromDate}-to-${this.toDate}`;
      let btns =  [
        { extend: 'excelHtml5', className: 'btn btn-sm', title: exportTitle, filename: exportTitle},
        { extend: 'csvHtml5', className: 'btn btn-sm', title: exportTitle, filename: exportTitle },
      ];

      this.selectColNum = (cols.length - 1);
      this.currentInvoiceTable = $('#currentInvoice-table').DataTable({
      data: this.currentInvoiceTableData, dom: 'Bfrtip', buttons: btns, pageLength: 100, columns: cols,
      'columnDefs': [{ 'targets': this.selectColNum, 'checkboxes': { 'selectRow': false } }],
      select: { style: 'multi', selector: 'none', info: false },

      'fnCreatedRow': (row, data, _idx) => {
          $(row).attr('id', data.uuid);
      },

      'drawCallback':  (_d) => {

        $(".refedit").each((_idx, ele)=>{
          let val = $(ele).data('val');
          new DarkEditable(ele, {value: val, name:"reference_invoice", emptytext: "+"});
        });
      

      }

    });


    


    

  }

  displayCurrentInvoiceFilters(_evt=null){
    $("#invoice-insights-current-filter-modal").modal("show");
  }

  /**
   * @function updateInvoiceStatus
   * @description update invoice status async
   * @param {*} evt 
   */
  updateInvoiceStatus(evt) {
    let ele = $(evt.target);
    let id = ele.data('invoice');
    let status = ele.data('status');
    let url = this.invoiceUrl.replace(':id', id);
    $('#form-invoice-status').attr('action', url);
    $("#invoice-status").val(status);
    $("#invoice-async-form-id").val(id);
    $('#modal-invoice-status').modal('toggle');
  }

  /**
   * @function updateInvoiceStatusSubmit
   * @description handel update status form submit
   * @param {*} evt 
   */
  updateInvoiceStatusSubmit(evt) {
    evt.preventDefault()
    let id = $("#invoice-async-form-id").val();
    let status = $("#invoice-status").val();
    let props = {
      ele: evt.target,
      data: { _method: "patch", status: status },
      msgDiv: "#invoice-status-update-msg",
      msgTxt: "Update Invoice",
      msgSend: "<strong>Updating...</strong>",
      msgOk: "<strong style='color:#198754;'>Invoice Updated Successfully</strong>",
      msgErr: "Unable to submit form Please Try Again"
    }

    this.sendAsyncForm(props);
    $(".invoice-row-" + id).html(status);
    this.resetTabState();
  }

 /**
   * @function updateAllInvoiceStatus
   * @description update selected invoices status async
   */
 updateAllInvoice() {
  let uuids = this.currentInvoiceTable.column(this.selectColNum).checkboxes.selected();
  if( uuids.length === 0 ){
    return false;
  }
  $(document).off('submit', '#form-invoice-all-status').on('submit', '#form-invoice-all-status', this.updateAllInvoiceSubmit.bind(this));
  $("#modal-invoice-all-status").modal('toggle');
}

updateAllInvoiceSubmit(evt){
  evt.preventDefault();
  let i = 0;
  let grps = _.groupBy(this.currentInvoiceTableData, 'uuid');
  let uuids = this.currentInvoiceTable.column(this.selectColNum).checkboxes.selected();
  let status = $("#invoice-all-status").val();
  let url = $(evt.target).attr("action");
  let data = { _method: "patch", status: status }
  let ids = []
  let token = $('meta[name="csrf-token"]').attr('content');
  $("#invoice-all-status-sub-btn").prop("disabled",true).html("saving...");

  $.each(uuids, (_idx, uuid)=>{ 
    let invoice = grps[uuid];
    ids.push({id: invoice[0].id, uuid: uuid});
   });
  
  let saveLoop = () => {       
    setTimeout(()=>{ 
      let inv = ids[i];
      $.ajax({
        type: 'POST',
        url: url.replace('0', inv.id ),
        data: data,
        headers: { 'X-CSRF-TOKEN': token },
        success: (_data)=>{ 
          $(`.invoice-row-${inv.id}`).html(status);
        },
        error: (err) => console.log(err)
      });
      i++;                 
      if (i < ids.length) { saveLoop(); }else{ 
        $("#modal-invoice-all-status").modal('toggle');  
        $("#invoice-all-status-sub-btn").prop("disabled",false).html("Update All");
        this.resetTabState();
      }          
    }, 300)
  }
  
  saveLoop();
  
}

  /**
   * @function deleteInvoice
   * @description delete a single invoice by uuid 
   * @param {*} evt 
   */
  deleteInvoice(evt) {
    let ele = $(evt.target);
    let id = ele.data('uuid');
    let url = this.invoiceUrl.replace(':id', id);
    let msgOk = "The record status is now void.";
    let par = ele.parents('tr');
    swal({
      title: "Void?", text: "Are you sure you want to void this invoice? You will not be able to change the status after this record is voided.", icon: "warning",
      buttons: { no: { text: 'No', value: false, }, yes: { text: 'Yes', className: 'btn-danger', } }
    }).then((result) => {
      if (!result) { return; }
      $.ajax({
        type: "post",
        url: url,
        data: { _method: 'DELETE' },
        success: (_data) => {
          this.resetTabState();
          swal(msgOk, '', 'success'); par.remove();
        },
        error: (err) => console.log(err)
      });
    });
  }

  /**
   * @function deleteAllInvoice
   * @description delete a selected invoices by uuid 
   * @param {*} evt 
   */
  deleteAllInvoice() {
    let ids= [];
    let uuids = this.currentInvoiceTable.column(this.selectColNum).checkboxes.selected();
    $.each(uuids, (_idx, uuid) => { ids.push(uuid);});
    if (ids.length <= 0) { return;}
    swal({
      title: "Void?", text: `Are you sure to void ${ids.length} invoices? You will not be able to change the status after this records are voided.`, icon: "warning",
      buttons: { no: { text: 'No', value: false, }, yes: { text: 'Yes', className: 'btn-danger', } }
    }).then((result) => {
      if (!result) { return; }
      this.deleteAllInvoiceSubmit(ids, this.invoiceUrl);
    });
  }

  /**
   * @function deleteAllInvoiceSubmit
   * @description handle on delete submit 
   * @param {*} ids 
   * @param {*} url 
   */
  deleteAllInvoiceSubmit(ids, url){
    let i = 0;
    let token = $('meta[name="csrf-token"]').attr('content');
    let deleteLoop = () => {       
      setTimeout(()=>{ 
        let id = ids[i];
        $.ajax({
          type: 'POST',
          url: url.replace(':id', id),
          data: { _method: 'DELETE' },
          headers: { 'X-CSRF-TOKEN': token },
          success: (_data)=>$(`#${id}`).remove(),
          error: (err)=>console.log(err)
        });
        i++;                 
        if (i < ids.length) { deleteLoop(); }else{ this.resetTabState(); }          
      }, 300);
    }
    deleteLoop();
  }

    /** ---------- DataTable render helpers ----------- */

    /**
     * @function renderCurrency
     * @description display USD for float 
     * @param {any} data value in the the datatable row.    
     * @returns {string} formated currency string. 
     */
    renderCurrency(data, _type, _row, _meta) {
      return dollarNumFmt(data, currencyFormation.symbol)
    }

    renderBookingTotal(data, _type, row, _meta) {
      let customChargeTotal = (row.custom_charge_total > 0) ? parseFloat(row.custom_charge_total) : 0;
      let amount = (parseFloat(data) + parseFloat(customChargeTotal));
      
      return dollarNumFmt(amount, currencyFormation.symbol)
    }
    renderAmountUnPaid(data, _type, row, _meta) {
      if (row.invoice_status === 'paid') {
        return currencyFormation.symbol + "0.00";
      }
      let amount = (parseFloat(data) - parseFloat(row.refund_total));
      return dollarNumFmt(amount, currencyFormation.symbol);
    }

    renderAmountPaid(data, _type, row, _meta) {
      if (row.invoice_status !== 'paid') {
        return currencyFormation.symbol + "0.00";
      } else {
        let amount = (parseFloat(data) - parseFloat(row.refund_total));
        return dollarNumFmt(amount, currencyFormation.symbol);
      }
    }
    renderBookingTotalWithCustomCharge(data, _type, row, _meta) {
      // row.bookingamount is computed server-side (ReportRepository::affiliateReportsCalculation,
      // fed by the same /affiliate-report endpoint this table pulls from) and already accounts for
      // custom charges AND add-ons, paid or still-owed — recomputing it here from raw
      // booking_details.unpaid_custom_charges / row.custom_charge duplicated that logic and
      // silently dropped add-on amounts from this "Booking Total" column.
      let amount = parseFloat(row.bookingamount) || 0;
      return dollarNumFmt(amount, currencyFormation.symbol)
    }
    renderRates(data, _type, _row, _meta){
      if(data && typeof(data) === 'object'){
        return data.map((d)=>`<small>${d}</small>`).join("<br />") 
      }
      return "";
    }

    renderTourDate(_data, _type, row, _meta){
      return row.booking_details.bookingdate;
    }

    renderTourTime(_data, _type, row, _meta){
        return `<small>
                ${row.booking_details.time_from} - ${row.booking_details.time_to} 
                </small> `;
    }
  /**
     * @function renderViewError
     * @description return default error, display an error dialog. 
     * @param {err} xhr error object
     * @returns void
     */
  renderViewError(err) {
    swal(`Error occurred:`, `Unable to get data from server. ${err.responseJSON.message}`, 'error');
  }

  /**
   * @function renderBookingLink
   * @description return a link to the booking view. 
   * @param {any} data value in the the datatable row.    
   * @returns {string} html link 
   */
  renderBookingLink(data, _type, _row, _meta) {
    return `<a href='/admin/tour-operator/booking/${data}' target='_blank'>${data}</a>`;
  }
  /**
   * @function rednerInvoiceCheckBox
   * @description return a checkbox to add items to the invoice list 
   * @param {any} data value in the the datatable row.    
   * @returns {string} html link 
   */
  rednerInvoiceCheckBox(_data, _type, row, _meta) {
    return `<input type="checkbox"  value="${row.booking_id}" class="invoice-add-to-list"   /> `;
  }

  /**
   * @function renderInvoiceIDLink
   * @description return a link view the invoice data in a modal window.  
   * @param {any} data value in the the datatable row.
   * @param {*} row map of the the row data for an invoice.     
   * @returns {string} html link 
   */
  renderInvoiceIDLink(data, _type, row, _meta) {
    if (data === "0") {
      return "n/a";
    }
    return `<a href='#' title=" Ref # ${(row.reference_invoice) ? row.reference_invoice : ' N/A '}" onclick="InvoiceInsightsReport.getInvoiceDetails('${row.invoice_uuid}');" >${data}</a>`;
  }

  /**
   * @function renderStatus
   * @description display a badge in based on the status of the invoice row. 
   * @param {any} data value in the the datatable row.    
   * @returns {string} html link 
   */
  renderStatus(data, _type, row, _meta) {
    let term = data.trim().toLowerCase();
    let name = capitalized(term);
    let colors = InvoiceInsightsReport.colors();
    let icons = InvoiceInsightsReport.icons();
    return `<span class="badge bage-sm whitespace-nowrap py-1 px-3 rounded-full text-white ${term} invoice-row-${row.invoice_id}" style="background-color:${_.has(colors, term) ? colors[term] : "#fff"}"><i class="fa ${_.has(icons, term) ? icons[term] : "fa-file"}"></i> ${name}</span>`;

  }
  /**
   * @function renderTruncatedString
   * @description trancate a string in the datatable 
   * @requires string
   */
  renderTruncatedString(data, _type, _row, _meta) {
    return (data.length > 20) ? `${data.slice(0, 20)}...` : data;
  }


  /** ---------- Html Templates ----------- */
  /**
   * @function invoiceTableTemplate
   * @description return the place holder for a data table. 
   * @param {int} id html table element id 
   * @returns {string} html table 
   */
  invoiceTableTemplate(id) {
    return `<div class="overflow-x-auto mt-10px">
            <table width="100%" class="table table-sm table-striped table-hover thead-sm" id='${id}'>
            <thead></thead>
            <tbody class='tbody-sm'></tbody>
            <tfoot id='${id}-foot'></tfoot>
        </table></div>`;
  }

  /**
   * @function invoiceDetailsModalTemplate
   * @description modal used to display the invoice details 
   * @returns string html modal 
   */
  invoiceDetailsModalTemplate() {

    return `<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-invoice-detail" tabindex="-1" aria-labelledby="modal-invoice-detail-label" aria-hidden="true"
    data-mdb-backdrop="true"
    data-focus-on="input:first"
    data-mdb-keyboard="true">
    <div class="modal-dialog modal-fullscreen max-w-[90vw] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
      <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
        <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
          <h5 class="modal-title text-lg font-semibold text-[#111827]" id="modal-invoice-detail-label">Invoice Details</h5>
          <button type="button" class="btn-close refresh-current-invoice" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
        </div>
        <div class="modal-body p-4" id="invoice-insights-modal-view">
        Loading...
        </div>
      </div>
    </div>
    </div>
    </div>`;
  }

  /**
   * @function invoiceTabsTemplate
   * @description return the main tabs that make up the invoice view. 
   * @returns string bootstrap tabs. 
   */
  invoiceTabsTemplate() {
    return `

    <ul class="nav nav-tabs tab-list p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-x-auto" id="invoiceTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link tabs-custom-link !px-4" id="invoiceBookedTab-tab" data-bs-toggle="tab" data-bs-target="#invoiceBookedTab" type="button" role="tab" aria-controls="invoiceBookedTab" aria-selected="false"> Unpaid Bookings </button>
      </li>  

      <li class="nav-item" role="presentation">
        <button class="nav-link tabs-custom-link !px-4 active" id="invoiceBillableTab-tab" data-bs-toggle="tab" data-bs-target="#invoiceBillableTab" type="button" role="tab" aria-controls="home" aria-selected="true"> Billable Bookings </button>
      </li>

      <li class="nav-item" role="presentation">
        <button class="nav-link tabs-custom-link !px-4" id="invoiceTable-tab" data-bs-toggle="tab" data-bs-target="#invoiceTable" type="button" role="tab" aria-controls="invoiceTable" aria-selected="false">Invoice List</button>
      </li>

      <li class="nav-item" role="presentation">
        <button class="nav-link tabs-custom-link !px-4" id="invoiceChart-tab" data-bs-toggle="tab" data-bs-target="#invoiceChart" type="button" role="tab" aria-controls="invoiceChart" aria-selected="false"> Invoice Status </button>
      </li>

      <li class="nav-item" role="presentation">
        <button class="nav-link tabs-custom-link !px-4" id="comprehensiveTab-tab" data-bs-toggle="tab" data-bs-target="#comprehensiveTab" type="button" role="tab" aria-controls="comprehensiveTab" aria-selected="false"> Comprehensive Report </button>
      </li>
  </ul>


  <div class="tab-content" id="invoiceTabContent">
    <div class="tab-pane fade" id="invoiceBookedTab" role="tabpanel" aria-labelledby="invoiceBookedTab-tab">
      <div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
        <div class="m-2 flex flex-wrap -mx-3">
          <div class="w-full md:w-5/12 max-w-full px-3">
            <div id="invoiceinsights-booked-table-toolbar" style="display:none;">
              <div class="flex items-center">
                <input type="checkbox" class="nat-input-sm w-4 h-4 mr-2" id="bookedViewPaidOnly" name="bookedViewPaidOnly" value="1" checked />
                <label class="text-sm font-normal text-[#020817]" for="bookedViewPaidOnly">View Unpaid Only</label>
              </div>
            </div>
          </div>
          <div class="w-full md:w-7/12 max-w-full px-3">
            <div class="md:flex justify-end">
              <div class="flex justify-end">
                <input type="date" class="form-custom-input mr-2 nat-input" onchange="InvoiceInsightsReport.updateDays();" title="Start Date" id="booked_start_date" /> 
                <input type="date" class="form-custom-input mr-2 nat-input" onchange="InvoiceInsightsReport.updateDays();" title="End Date" id="booked_end_date" /> 
              </div>
              <div class="flex justify-end">
                <button id="updateBookingTable" data-update="yes" class="btn btn-sm btn-info-small whitespace-nowrap mr-2">Update <span id="booked_number_days"></span> Days </button>
                <a href="#" title="Download Booked Invoice Report" class="btn btn-sm btn-secondary download-booked-data btn-info-small"><i class="fa fa-download"></i></a>
              </div>
            </div>
          </div>
          <div class="w-full max-w-full px-3" id="insights-booked-report-total"> 
          </div>
        </div>

        <div class='tab-table' id="invoiceinsights-booked-table-container">
          <div class="nat-spinner"></div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade show active" id="invoiceBillableTab" role="tabpanel" aria-labelledby="invoiceBillableTab-tab">
      <div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
        <div style="min-width:300px; min-height:500px">
            <div id="invoiceinsights-billing-table-container">   </div>
            <div class='tab-table' id="invoiceinsights-billing-form-container">   </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="invoiceTable" role="tabpanel" aria-labelledby="invoiceTable-tab">
      <div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
        <div style="display:nonel" id="invoiceinsights-nested-table-container">
          <div class="nat-spinner"></div>
        </div>

        <div class=""> ${this.invoiceActionsTemplate()} </div>
        <div class='tab-table' id="invoiceinsights-invoice-table-container">Loading...</div>
      </div>
    </div>

    <div class="tab-pane fade" id="invoiceChart" role="tabpanel" aria-labelledby="invoiceChart-tab">
      <div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
        <div class="container-fluid">
          <div class="flex flex-wrap -mx-3">
            <div class="w-full md:w-1/2 max-w-full px-3">
              <h5 class="mt-2 nat-border-bottom text-lg font-semibold text-[#111827]">Affiliate Amounts</h5>
              <div id="invoiceinsights-invoice-graph-aff-sum" style="height:425px; overflow:hidden; overflow-y:auto;">
                <div class="nat-spinner"></div>
              </div>

              <h5 class="ms-2 nat-border-bottom text-lg font-semibold text-[#111827]">Affiliate Amounts By Rate Type </h5>
                <div id="RateForChart"> Loading... </div>
                <div id="RateForChartDisplay"></div>
            </div>

            <div class="w-full md:w-1/2 max-w-full px-3" style="position:relative;" >
              <div class="nat-left-divider"></div>
              <h5 class="ms-2 mt-2 nat-border-bottom text-lg font-semibold text-[#111827]">Affiliate Invoice Distribution</h5>
              <div id="invoiceinsights-invoice-graph-container"></div> 
              <div class="nat-spinner" id="pie-chart-loader"></div>
              <div style="height:425px;">
                <canvas id="invoicePieChart"></canvas>
              </div>
            </div> 
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="comprehensiveTab" role="tabpanel" aria-labelledby="comprehensiveTab-tab">
      <div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
        <h5 class="ms-2 mt-2 p-2 nat-border-bottom text-lg font-semibold text-[#111827]">Affiliate Comprehensive Totals</h5>
        <div class="flex flex-wrap -mx-3">
        <div class="w-full md:w-1/2 max-w-full px-3">
        <div id="comprehensivePaymentDistroChart" style="min-height:300px;">
                Loading...
              </div>
          </div>

        <div class="w-full md:w-1/2 max-w-full px-3">
            <div id="comprehensiveChart"></div>
        </div>

        <h5 class="ms-2 mt-3 p-2 nat-border-bottom text-lg font-semibold text-[#111827]">Affiliate Comprehensive Table</h5>
        
        <div id="comprehensiveTable" class="w-full max-w-full px-3 overflow-x-auto">
            <div class="nat-spinner"></div>
        </div>

        </div>
      </div>
    </div>
  </div>`;
  }


 /**
  * @function expandViewTmpl
  * @description template for the expanded view of the invoice details. 
  * @returns string html template for the expanded view of the invoice details.
  */
  expandViewTmpl(){
    return `<div class="flex items-center justify-center"><a href="javascript:void(0)" style="font-size:11px;" class="float-center" id="insight-report-expand-view"><-- Expand View --></a></div>`;
  }
  /**
   * @function invoiceTotalsTmpl
   * @description 
   * @returns string html table of totals at the top of the page
   */
  invoiceTotalsTmpl(){
    return `
      <div class="mb-2" id="invoice-insights-top-totals-view" style="min-height:100px">
      ${dataTableTemplate('invoice-insights-totals-table', '', '<tr><td colspan="100%">Loading totals... </td></tr>' )}
      </div>
    `;
  }

  /**
   * enableStripeInvoice
   * enable / disable stripe invoice for selected affiliate. 
   * bound to: .enable-stripe-for-affiliate checkbox elements
   * @param evt html checkbox element
   * @returns void
   */
  enableStripeInvoice(evt){
    let id = $(evt.target).data('id');
    let ck = $(evt.target).prop('checked');
    let url = `${this.admin}/tour-operator/invoice/stripe-invoice-enable`;
    $("#generate-invoice-btn").prop('disabled', true);

    $.ajax({
      type: "POST",
      url: url,
      data: {  id: id, enabled: ck  },
      success: this.enableStripeInvoiceOk.bind(this),
      headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
    }).fail(
      (err)=>{ 
        swal({
          title: 'Error',
          text : `error setting invoice type ${err.responseJSON.message}` ,
          type : 'error'});
       $("#generate-invoice-btn").prop('disabled', false) }
    );
  }

  enableStripeInvoiceOk(res){
    let id = parseInt(res.id);
    let st = (res.state === "true") ? 1 : 0;
    let data = this.billableData;
    $("#generate-invoice-btn").prop('disabled', false);
    for (let key in data){
      if( data[key].affiliate_id === id){
        this.billableData[key].stripe_invoice_enabled = st;
      }
    }
  }

  /**
   * @function createInvoiceAffiliateList Display a list of affilates 
   * @param {*} props 
   * @returns string html view
   */
  createInvoiceAffiliateList(affilates, stripeEnabled, tableContents = "") {
    //console.log(stripeEnabled)
    
    const enableHeader = () => {
      return `<span class="relative inline-flex items-center group">
          <button type="button" class="inline-flex items-center text-sm focus:outline-none" aria-describedby="tooltip-stripe" tabindex="0">Create <i class="fa fa-question-circle ml-2" aria-hidden="true"></i></button>

          <div id="tooltip-stripe" role="tooltip" class="pointer-events-none w-64 absolute left-0 bottom-full mb-2 opacity-0 scale-95 group-hover:opacity-100 group-hover:scale-100 group-focus:opacity-100 group-focus:scale-100 transition-all duration-150 bg-gray-900 text-white text-xs rounded px-2 py-1 !whitespace-normal z-50">
            If checked an invoice will be created for selected affiliate, if left un-checked this affiliate invoice will be skipped.
            <div class="absolute left-2 -bottom-1 w-2 h-2 bg-gray-900 transform rotate-45"></div>
          </div>
        </span>
      `;
    }

    const stripeHeader = () => {
      if( !stripeEnabled ){ return ''; }
      return `<th class="th-custom-css"> 
              <span class="relative inline-flex items-center group">
                <button type="button" class="inline-flex items-center text-sm focus:outline-none" aria-describedby="tooltip-stripe" tabindex="0">Stripe Invoice <i class="fa fa-question-circle ml-2" aria-hidden="true"></i></button>

                <div id="tooltip-stripe" role="tooltip" class="pointer-events-none w-64 absolute left-0 bottom-full mb-2 opacity-0 scale-95 group-hover:opacity-100 group-hover:scale-100 group-focus:opacity-100 group-focus:scale-100 transition-all duration-150 bg-gray-900 text-white text-xs rounded px-2 py-1 !whitespace-normal z-50">
                  If enabled a stripe invoice will be generated, a link will be sent to the customer to collect payment.
                  <div class="absolute left-2 -bottom-1 w-2 h-2 bg-gray-900 transform rotate-45"></div>
                </div>
              </span>
              </th>`;
    }

    let stripeToggle = (props) => {

      if (!stripeEnabled){ return ''; }
      return `
      <td class="td-custom-css"> <input type="checkbox" class="enable-stripe-for-affiliate"  ${(props.stripe_enabled === 1) ? "checked" : ""} value="1" data-id="${props.customer_id}" id="stripe_invoice-${props.id}" name="stripe_invoice" /> 
          <label style="font-size:13px;" for="stripe_invoice-${props.id}">Stripe Invoice</lable>  </td>
      `;
    }
    let tableRow = (props) => `
      <tr>
          <td class="td-custom-css" colspan="${stripeEnabled ? '5' : '4'}" id="invoice-msg-display-${props.id}"></td>
          <td class="td-custom-css"></td>
      </tr>
        <tr>
          <td class="td-custom-css" width="7%"> 
            <input class="form-check-input" value="${props.id}" type="checkbox" id="enable-invoice-for-${props.id}" aria-label="Create Invoice" checked />
          </td>
          <td class="td-custom-css"> ${this.renderTruncatedString(props.name)} </td>
          ${stripeToggle(props)}
          <td class="td-custom-css"> ${this.renderCurrency(props.amount)}</td>
          <td class="td-custom-css"> <input id="due_date-${props.id}" type="date" class="invoice-create-grp-date form-custom-input" value="${defaultDateFormat()}" /> </td>
          <td class="td-custom-css" style="width:130px; overflow:hidden;"> <span id="invoice-status-${props.id}">pending</div> </td>
        </tr>`;

    for (let key in affilates) {
      let props = {customer_id: affilates[key].customer_id,
                  stripe_enabled: affilates[key].stripe_invoice_enabled,
                  id: affilates[key].meta.id, 
                  name: affilates[key].meta.name, 
                  amount: affilates[key].amount }
      tableContents += tableRow(props);
    }

    return `
      <div class="card border border-[#E5E7EB] rounded-lg overflow-hidden">
      <h5 class="card-header block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full" id="invoice-form-header">
        Create ${(affilates.length > 1) ? "Invoices" : " A New Invoice" } For: ${(affilates.length > 1) ? `${affilates.length} Affiliates` : this.customerTitle}
      </h5>
      <div class="card-body p-4">
        ${(affilates.length > 1) ? '<label class="" for="async_invoice_due_date">Update all due dates</label>' : ""}
        ${(affilates.length > 1) ? `<input type="date" name="due_date" value="${defaultDateFormat()}" id="async_invoice_due_date" class="form-control hasDatepicker"  required />` : ""}
          <table class="table table-borderless table-hover thead-sm table-sm w-100">
              <thead>
                <tr>
                <th class="th-custom-css"> ${enableHeader()}</th>
                <th class="th-custom-css">Name</th>
                ${stripeHeader()}
                <th class="th-custom-css">Amount</th>
                <th class="th-custom-css">Due Date</th>
                <th class="th-custom-css">Status</th>
                </tr>
              </thead>
              ${tableContents}
          </table>
      </div>
      <div class="card-footer px-4 pb-4">
      <div class="progress">
      <div class="progress-bar" 
      id="invoice-create-progress-bar"
      role="progressbar" style="width: 0%" aria-valuenow="10" aria-valuemin="0" aria-valuemax="100"></div>
      </div>
      </div>
    </div>`;
    }

  /**
   * @function createInvoiceTemplate Display a form interface to create invoices
   * @param {*} props 
   * @returns string html view
   */
  createInvoiceTemplate(props) {
    return `
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-invoice-create-from-seleted-bookings" tabindex="-1" role="dialog" aria-hidden="true">
      <div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">

            <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
              <h5 class="modal-title text-lg font-semibold text-[#111827]" id="createInvoiceMessageDiv" style="font-weight:400;">Generate Invoices</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
            </div>

            <div class="modal-body p-4">
              <div id="createInvoiceList"> </div>
            </div>

            <div class="modal-footer px-4 pb-4">
                <form method="post" action="${props.admin}/tour-operator/invoice/store-with/stripe" id="invoice-create-form-async">
                  <button type="submit" id="generate-invoice-btn" class="btn btn-primary btn-sm btn-primary-small">Generate</button>
                  <button type="button" class="btn btn-sm btn-secondary btn-light-small" data-bs-dismiss="modal"> Close </button>
              </form>
            </div>
              
        </div>
      </div>
    </div>`;
  }

  /**
   * @function invoiceActionsTemplate
   * @description Select Actions above the current invoice table. 
   * @returns string html drop down. 
   */
  invoiceActionsTemplate(){
    return `<div class="dropdown flex items-center overflow-x-auto">
            <button class="btn btn-sm btn-secondary dropdown-toggle form-custom-input !w-auto mr-2" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
              Action
              <svg class="inline-block ms-1" width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg"><path class="group-hover:!stroke-[#F97316]" d="M1.4001 4.70019L7.0001 10.3002L12.6001 4.7002" stroke="#9CA3AF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
            </button>
            <ul class="dropdown-menu hidden absolute left-1/2 -translate-x-1/2 mt-2 w-72 bg-white shadow-lg z-50 rounded border text-sm text-left" aria-labelledby="dropdownMenuButton1">
              <li><a class="smtxt dropdown-item view-all-invoices block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="javascript:void(0);" id="view-all-invoices-current-year" data-range="current-year"><i class="fa fa-list mr-1"></i> View Invoices - Current Year</a> </li>
              <li><a class="smtxt dropdown-item view-all-invoices block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="javascript:void(0);" id="view-all-invoices-last-year" data-range="last-year"><i class="fa fa-list mr-1"></i> View  Invoices - Last Year</a> </li>
              <li><a class="smtxt dropdown-item view-all-invoices block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="javascript:void(0);" id="view-all-invoices-last-year" data-range="selected-date-range"><i class="fa fa-list mr-1"></i> View  Invoices - Selected Date Range</a> </li>
              <li><a class="smtxt dropdown-item block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="javascript:void(0);" id="delete-all-invoices-in-data-table"><i class="fa fa-trash mr-1"></i> Void Selected</a> </li>
              <li><a class="smtxt dropdown-item block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="javascript:void(0);" id="update-all-invoices-in-data-table"><i class="fa fa-edit mr-1"></i> Update Selected</a> </li>
            </ul>
          </div>`;
  }

  /**
   * @function filterByInvoiceTypeTemplate
   * @description Select filters for paid + unpaid + partial etc..
   * @returns string html tag select. 
   */
   filterByInvoiceTypeTemplate(id="invoice-insights-filter-status-select", attrs='multiple="multiple"'){
    return `
      <div class="row">
        <div class="col-12">
            <select id="${id}" ${attrs}>
              <option value="draft" selected>Draft</option>
              <option value="sent" selected>Sent</option>
              <option value="viewed" selected>Viewed</option>
              <option value="partial" selected>Partial</option>
              <option value="paid">Paid</option>
              <option value="overdue" selected>Overdue</option>
              <option value="unpaid" selected>Unpaid</option>
              <option value="cancelled" selected>Cancelled</option>
            </select>
        </div>

       
      </div>
    `;
   }

  /**
   * @function
   * @description
   * @parm
   * @returns string html multi status update from
   */
   invoiceUpdateAllStatusTemplate(){
    let form = `<form method="post" action="${this.admin}/tour-operator/invoice/0" id="form-invoice-all-status">          
                          <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                              <h5 class="text-lg font-semibold text-[#111827]" id="invoice-status-all-update-msg">Update Selected Invoices</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                          </div>
                          <div class="modal-body p-4">
                              <div class="mb-3">
                                  <label for="invoice-all-status" class="form-label text-sm font-semibold text-[#111827] mr-2 whitespace-nowrap min-w-32">Status</label>
                                  <select name="status" id="invoice-all-status" class="form-control form-custom-input bg-[#FAFAFA]" required="true">
                                    <!-- option value="draft">Draft</option -->
                                    <option value="sent">Sent</option>
                                    <option value="viewed">Viewed</option>
                                    <!-- option value="approved">View All BookingsApproved</option -->
                                    <option value="partial">Partial</option>
                                    <option value="paid">Paid</option>
                                    <option value="overdue">Overdue</option>
                                    <option value="unpaid">Unpaid</option>
                                    <option value="cancelled">Cancelled</option>
                                    </select>
                              </div>
                              <button type="submit" id="invoice-all-status-sub-btn btn-primary-small w-full" class="btn btn-main btn-main-sm">Update All</button>
                          </div>
                      </form>`;

        return ` <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-invoice-all-status" tabindex="-1"  aria-modal="true" role="dialog">
                  <div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                      <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                          ${form}
                      </div>
                  </div>
              </div>`;
   }


   currentInvoiceFilters(){

      let form = `
        <form class="p-3">

          <input type="checkbox" value="1" id="toggle-void-invoice-view" id="toggle-void-invoice-view" > 
          <label for="toggle-void-invoice-view">View Voided Invoices</label>
        </form>
      `;
      return bootstrapModalHtml({ id:"invoice-insights-current-filter-modal", 
                                  size: "lg",
                                  title: 'Filter Invoices',
                                  content: form,
                                  footer: "",
                                  action: '<button class="toggle-void-invoice-view-btn float-end btn btn-sm btn-primary">Update</button>'});
   }

   /**
    * Filters Modal
    * this is shown when the user clicks on the filter under the current invoice display all list. 
    * @returns string html 
    */
   filtersModalTemplate() {
      return bootstrapModalHtml({ id:"invoice-insights-filter-modal", 
                                  size: "lg",
                                  title: 'Filter Invoice Bookings',
                                  content:  this.filterByInvoiceTypeTemplate(),
                                  action: '<button class="btn btn-sm btn-primary btn-primary-small mt-3" id="invoice-insights-filter-status">Filter</button>',
                                  footer: '<div style="min-height:150px;"> </div>',
                                });
    }
   /**
    * Booking Detail Modal
    * this is shown when a user clickes on a booking id in the the data table
    * @returns string html 
    */
    viewInvoicedBookingModalTemplate() {
      return bootstrapModalHtml({ id:"invoice-insights-bookings-modal", 
                                  size: "fullscreen",
                                  title: 'Booking Info',
                                  content:  "<div id='viewUnpiadBookingTable'> </div>",
                                  action: '',
                                  //close: '<button type="button" class="btn btn-sm btn-secondary invoice-insight-close-bookig-edit" data-id="0" data-bs-dismiss="modal">Close</button>',
                                  topclose: '<button type="button" class="btn btn-sm btn-secondary btn-close invoice-insight-close-bookig-edit" data-id="0" data-bs-dismiss="modal" aria-label="Close">x</button>',
                                  footer: '',
                                });



            // return `<div class="modal top fade" id="invoice-insights-bookings-modal" tabindex="-1" aria-labelledby="invoice-insights-bookings-modal-label" aria-hidden="true"
            //         data-mdb-backdrop="true"
            //         data-focus-on="input:first"
            //         data-mdb-keyboard="true">
            //         <div class="modal-dialog modal-fullscreen">
            //         <div class="modal-content">
            //             <div class="modal-header">
            //             <h5 class="modal-title" id="invoice-insights-filter-moda-label">Booking Info</h5>
            //               <button type="button" class="btn-close invoice-insight-close-bookig-edit" data-id="0" data-bs-dismiss="modal" aria-label="Close"></button>
            //             </div>
            //             <div class="modal-body" id="invoice-insights-bookings-modal-body">
            //                     <div id='viewUnpiadBookingTable'> </div>
            //             </div>
            //             <div class="modal-footer">
            //               <button type="button" class="btn btn-sm btn-secondary invoice-insight-close-bookig-edit" data-id="0" data-bs-dismiss="modal">Close</button>
            //             </div>
            //         </div>
            //         </div>
            //         </div>
            //         </div>`;
            }


    comprehensiveTotalsTempl(data){
      //<th> Billable Invoices</th> <td class='success-text-color fw-normal'>"${currency(data.billable_invoiced_total.toFixed(2)).format()}</td>
      return`<table class='table table-sm table-striped table-hover thead-sm'>
                  <thead>
                    <tr>
                      <th class="th-custom-css whitespace-nowrap">Date Range</th>
                      <th class="th-custom-css whitespace-nowrap">Created</th>
                      <th class="th-custom-css whitespace-nowrap">Booking Amount</th>
                      <th class="th-custom-css whitespace-nowrap">Cancellation Amount</th>
                      <th class="th-custom-css whitespace-nowrap">Net Booked</th>
                      <th class="th-custom-css whitespace-nowrap">Refund Amount</th>
                      <th class="th-custom-css whitespace-nowrap">Net Card</th>
                      <th class="th-custom-css whitespace-nowrap">Net Cash</th>
                      <th class="th-custom-css whitespace-nowrap">Net Invoiced</th>
                      <th class="th-custom-css whitespace-nowrap">Paid Invoices</th>
                      <th class="th-custom-css whitespace-nowrap">Unpaid Invoices</th>
                      <th class="th-custom-css whitespace-nowrap">Total Net Collected</th>
                      <th class="th-custom-css whitespace-nowrap">Unpaid Amount</th>
                    </tr>
                  </thead>
                  <tbody class='tbody-sm'>
                    <tr>
                      <td class="td-custom-css">${moment(this.fromDate).format('MM/DD/YYYY')} - ${moment(this.toDate).format('MM/DD/YYYY')}</td>
                      <td class="td-custom-css">${moment(new Date()).format('MM-DD-YYYY')}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.booking_amount)}</td>
                      <td class='alert-text-color fw-normal td-custom-css'>${dollarNumFmt(data.cancellation_amount)}</td> 
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.paid_amount)}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.refund_amount)}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.card_amount)}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.cash_amount)}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.invoiced_amount)}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.paid_invoiced_amount)}</td>
                      <td class='alert-text-color fw-normal td-custom-css'>${dollarNumFmt(data.unpaid_invoiced_amount)}</td>
                      <td class='success-text-color fw-normal td-custom-css'>${dollarNumFmt(data.total_net_amount)}</td>
                      <td class='alert-text-color fw-normal td-custom-css'>${dollarNumFmt(data.unpaid_amount)}</td>
                  </tr>
                </tbody>
                </table>`;
    }


    } // end class


  /**
   *  Download the Aff. all report from the main download btn. 
   * @param {*} _admin 
   * @param {*} url 
   */
const invoiceInsightsReportDownload = (_admin, url)=> {

      let opt = $('#tour_operator').val();
			let pkg = $("#tour_package").val();
			let cid = $("#customer-data").val();
      let d = $('#reportrange').data('daterangepicker');
      let frm = d.startDate.format('YYYY-MM-DD');
      let to = d.endDate.format('YYYY-MM-DD');
			var csv = 1;
      
			window.location = url+'?fromDate='+frm+'&toDate='+to+'&customerId='+cid+'&tourPackageId='+pkg+'&tourOperatorId='+opt+'&csv='+csv;


}



