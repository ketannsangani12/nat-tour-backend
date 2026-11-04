
/**
 * @description 
 * Pulls data from stripe to verify a refund. example refund booking 21190
 * Load json data from /app/Repositories/Report/ReportRepository@StripeRefundReport to populate a data table.
 * @param props endpont the endpoint to the StripeRefundReport report | admin . 
 * @requires js: jquery, moment, showLoading, hideLoading, swal 
 * @returns void
 */
const stripeRefundReportInit = (props) => {
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
    // make sure tour operator and tour package is selected 
    if ($('#tour_operator').val() == '') {
      swal('Please select a tour operator', '', 'warning');
      return false;
    }

    //create the report class instance
    new StripeRefundReport(props);
  }



class StripeRefundReport {

    constructor(props){

        console.log(props);
        
        this.title = "Stripe Refund Lookup";
        this.admin = (props.admin === 'Admin') ? '/admin' : '';
        this.fromDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
        this.toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
        this.optId = $("#tour_operator").val();
        this.bookingId = $("#bookingId").val(); 
        this.endpoint = `${this.admin}/tour-operator/report/stripe-refund-report`; //{bookingId?}
        this.init();
    }

    init(){

      updateReportInfo(`${this.title} (Loading...)`, {from: "", to: ""});

      this.mainView = $('#mainTableDiv');
      this.mainView.empty("");

      this.eventListeners();
      this.getRefunds();
    }


    eventListeners(){

    }


    getRefunds(){

      this.bookingId = $("#bookingId").val(); 
      const url = `${this.endpoint}/${this.bookingId}`;
      $.get(url).done(this.getRefundsOk.bind(this)).fail(this.onError).always(hideLoading);

    }

    getRefundsOk(data){


      if( !data.booking ){
        this.mainView.html( "<h1> No Booking Found </h1>" );
        return false;
      }

      updateReportInfo(`${this.title} (${data.booking.id})`, {from: "", to: ""});

      this.bookingTemplate(data.booking);
      this.stripeInfoTemplate(data);
      this.accordionTemplate( this.transactionsTemplate(data.transactions) , this.historyTemplate(data.history));
    }


    bookingTemplate(data){

      let html = `<h3 style="font-size:20px; color: gray; margin:0px; border-bottom: 1px solid #ccc;"> Booking Info </h3>
      <div style="padding:10px;">
                    Booking: <a href="${this.admin}/tour-operator/booking/${data.id}" target="_blank">${data.id}</a> <br />
                    Email: ${data.email} <br />
                    Phone: ${data.phone_number} <br />
                    Status: ${data.status}

      </div>`;

      this.mainView.html(html);

      return html;

      // //      "name": "Jamaal Ullrich",
      // "email": "glamorous-lightgrey-pademelon-s2s1t44po@e.rainfore",
      // "phone_number": "662-919-9379",
      // "tourists": 7,
    }


    //stripe info
    stripeInfoTemplate(data){
      
      let html = '<h3 style="font-size:20px; color: gray; margin:0px; border-bottom: 1px solid #ccc;"> Stripe Info </h3>';
      let datafound = false;

      if( data.payment_intents && data.payment_intents.length > 0){
          const payment_intents = data.payment_intents;
          for( let i=0; i<payment_intents.length; i++ ){
              datafound = true;
              html += this.renderRefundInfo(payment_intents[i].charges.data);
          }
      }

      //if the payment intent is empty try and get the data from the search 
      if (!datafound){
        const result = data.search_result;
        if( _.has(result, 'data') && result.data && result.data.length > 0 ){
            for( let i=0; i<result.data.length; i++ ){
                datafound = true;
               html += this.renderRefundInfo(result.data[i].charges.data);
            }
        }
      }

      // if no data found at all dispay no data found msg
      if(!datafound){
        html += "<h1>No Data Found</h2>"
      }

      this.mainView.append(html);
    }
    

    renderRefundInfo(items){

      let html = `<div style="padding:10px; background-color: #f6f6f6;">`;

    
      items.map( (i) => {


        html += `
        
        <strong> Charge: </strong>  ${i.payment_method_details.type}  <br /> 
        <p>Link: <a href="${i.receipt_url}" target="_blank"> receipt </a>  </p>
        <p>Payment: ${stripeDollarFmt(i.amount_captured)} </p>
        <p>Refund: ${stripeDollarFmt(i.amount_refunded)} </p>
        <p>Disputed: ${i.disputed} </p>
        <p>Info: ${i.description} </p>
        <hr /> `; 

        if( i.refunds && _.has(i.refunds, 'data') && i.refunds.data.length > 0 ) {
            i.refunds.data.map( (r) => {
                html += `<strong>Refund Issued</strong> 
                          <p>Date: ${  new Date(r.created * 1000).toLocaleString() } </p>
                          <p>Amount: ${stripeDollarFmt(r.amount)}</p>
                          <p>Status: ${r.status}</p>
                        <hr />`;
                console.log(r);

            } );

        }

       });

       html += "</div>"
       return html;


    }

    //history
    historyTemplate(data){

        const renderUrl = (url, id)=>{
          return  `<a href="${url}/${id}" target="_blank" title="booking detail" >${id}</a>`
        }

        const renderDetials = (data) => {
          let html = '';
          try{ 
            let items = JSON.parse(data);
            for(let key in items){
              html +=  `${key}: ${items[key]} <br />`;
            }
          } catch (err){
            html = data;
          }
          return html;
        }

        const renderItems = (items) => {
          const url = `${this.admin}/tour-operator/booking`;
            return items.map((i)=>{
                return `<tr> 
                    <td>${renderUrl( url, i.tour_booking_id)}</td>
                    <td>${i.operation}</td>
                    <td>${toFmtStr(i.tourists)}</td>
                    <td>${renderDetials(i.details)}</td>
                  </tr>`;
            }).join("\n");

        }

        const html = `<table class="table table-borderless table-sm" style="border: 1px solid #ccc;">
            <thead>
              <tr>
                <th>Booking ID</th>
                <th>Operation</th>
                <th>Tourists</th>
                <th>Details</th>
              </tr>
            </thead>
            <tbody>
              ${renderItems(data)}
            </tbody>
        </table>`;
        return html;
    }

    //transactions
    transactionsTemplate(data){

        const renderItems = (items)=> {
          return items.map((i)=>{
            return `<tr> <td>${i.status}</td><td>${dollarNumFmt(i.amount)}</td><td>${i.via}</td></tr>`;
          }).join("\n");
        }

        const html = `<table class="table table-borderless table-sm" style="border: 1px solid #ccc;">
            <thead>
              <tr>
                <th>Status</th>
                <th>Amount</th>
                <th>Via</th>
              </tr>
            </thead>
            <tbody>
              ${renderItems(data)}
            </tbody>
        </table>`;


        return html;
        
    }


    accordionTemplate( content1, content2 ){

      let html = `<div class="accordion" id="stripeRefundAccordion">
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="stripeAccordion-headingOne">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#stripeAccordion-collapseOne" aria-expanded="false" aria-controls="stripeAccordion-collapseOne">
                        Transactions
                      </button>
                    </h2>
                    <div id="stripeAccordion-collapseOne" class="accordion-collapse collapse" aria-labelledby="stripeAccordion-headingOne">
                      <div class="accordion-body">
                        ${content1}
                      </div>
                    </div>
                  </div>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="stripeAccordion-headingTwo">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#stripeAccordion-collapseTwo" aria-expanded="false" aria-controls="stripeAccordion-collapseTwo">
                        History
                      </button>
                    </h2>
                    <div id="stripeAccordion-collapseTwo" class="accordion-collapse collapse" aria-labelledby="stripeAccordion-headingTwo">
                      <div class="accordion-body">
                        ${content2}
                      </div>
                    </div>
                  </div>
    
                </div>`;


        this.mainView.append(html);
    }

}
