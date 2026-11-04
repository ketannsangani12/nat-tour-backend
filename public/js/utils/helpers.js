/**
 * @function capitalized
 * @description  capitalized string
 * @param {string} str to capitalized
 * @returns {string} capitalized string
 */
const capitalized = str => str.charAt(0).toUpperCase() + str.slice(1);

/**
 * @function defaultDateFormat
 * @description  date format
 * @param {string} str_date
 * @returns {string} yyyy-mm-dd date string
 */
const defaultDateFormat = (str_date=null) => {
    const date = (str_date) ? new Date(str_date) : new Date();
    let year = date.toLocaleString("default", { year: "numeric" });
    let month = date.toLocaleString("default", { month: "2-digit" });
    let day = date.toLocaleString("default", { day: "2-digit" });
    return year + "-" + month + "-" + day;
};

/**
 * @function getArraySum
 * @description Get a sum of all values in array
 * @param {*} arry
 * @returns
 */
const getArraySum = (arry) => {
    let sum = arry.reduce((accumulator, currentValue) => { return accumulator + currentValue },0);
    return sum;
}

/**
 * @function getArraySum
 * @description Get a sum of all values in array
 * @param {*} arry
 * @returns
 */
const sortArrayByKey = (arry, key) => {
    return arry.sort(function(a, b) {
        var x = a[key]; var y = b[key];
        return ((x < y) ? -1 : ((x > y) ? 1 : 0));
    });
}


/**
 * @function decodeCheckInStatus
 * @description return the check in status based on the enum.
 * @param {*} status check in status ( N:none, A:Absent, 1:1st checkin, 2:2nd checkin )
 * @returns string checkin named status
 */
const decodeCheckInStatus = (status) =>{
    let state = "";
    switch(status){
        case "N":
        case "n":
            state = "Not Checked In";
            break;
        case "A":
        case "a":
            state = "Absent";
            break;
        case "1":
        case 1:
            state = "1st Check In";
            break;
        case "2":
        case "2":
            state = "2nd Check In";
            break;
        default:
            state = "Unknown Status";
    }
    return state;

}

/**
 * @function dollarNumFmt
 * @description return money formate
 * @param {float} num currency amount
 * @param {string} pre prefix
 * @returns {string} currency format
 */
const dollarNumFmt = (num, _pre='') => {
    return  new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currencyFormation.code,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format( parseFloat(num) );
}

/**
 * stripeDollarFmt
 * @param {*} num
 * @returns string dollar format $1.90
 */
const stripeDollarFmt = (num) => {
    let val = parseFloat(num);
    if ( val <= 0 ){
        return 0;
    }
    return dollarNumFmt( num / 100 );
}

/**
 * toFmtStr
 * @param {*} num
 * @returns string format number 1,100.00
 */
const toFmtStr = (num) => {
    return parseFloat(num).toLocaleString("en-US");
}
/**
 * input 1000 -> output -> 1,000
 * @param {*} num
 * @returns string
 */
const FormatNumber = (num) => {
    return toFmtStr(num);
}

/**
 * Display a spinner
 * @param {*} classname
 * @returns string html
 */
const loadingSpinner = (classname="")=>{
    return `<div class="nat-spinner ${classname} "></div>`;
}


/**
 * Take a string_like_this and output String Like This
 * @param {*} str
 * @returns
 */
const titleize = (str) => {
    return str.replace( /\w\S*/g, (txt) => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()).replace(/\_/gmi, " ");
  }

/**
 * @function getMonthByInt
 * @description return month name by int 0-12
 * @param {int} id month number
 * @returns {string} month name
 */
const getMonthByInt = (num, abv=false) => {
    let idx = parseInt(num.toString().replace(/^0+/, ''));
    let months = ["January","January","February","March","April","May","June","July","August","September","October","November","December"];
    if (abv === true){
        months = ["Jan", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
    }
    return months[idx];
}

/**
 * @function dateTableTemplate
 * @description return the place holder for a data table.
 * @param {int} id html table element id
 * @returns {string} html table
 */
 const dataTableTemplate = (id, thead="", tbody="", tfoot="", cls="") => {
    return `<div class="my-2 overflow-y-auto">
            <table width="100%" class="table table-sm table-striped table-hover thead-sm" id='${id}'>
                <thead id='${id}-head'>${thead}</thead>
                <tbody class="${cls}" id='${id}-body'>${tbody}</tbody>
                <tfoot id='${id}-foot'>${tfoot}</tfoot>
            </table>
        </div>`;
  }

  /**
   * @function dataTableHeaderTemplate
   * @param Array cols list of header column names
   * @returns string html thead contents
   */
  const dataTableHeaderTemplate = (cols) =>{
    return `<tr>
                ${cols.map((c)=>`<th class="th-custom-css">${c}</th>`).join("\n")}
            </tr>`;
  }

    /**
     *  return a placeholder for null/false/blank values
     * @param {*} val the value to check if null or empty
     * @param {*} plhold value to default to if val is null or empty
     * @returns mixed
     */
    const displayNullAsStr = (val, plhold="") => {
        return (val && val !== null) ? val : plhold;
    }


    /**
     * return a history view
     * @param data booking history data
     * @returns html/jsx table.
     */

    const displayHistoryView = (data, id) => {

        let historyView = (row)=>{
            let details = "";
            let html = "";
            const tag = (title, detail) => `<li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-[#E5E7EB] text-black text-sm flex items-center justify-between px-4 py-2.5 border border-[#E5E7EB] text-black text-sm"> <small class="font-medium">${titleize(title)}:</small> <span class="float-end"><small>${detail}</small></span> </li>`;

            try{
                if(  row.details !== "" && row.details.includes("{") ){
                    details = JSON.parse(row.details);
                    for(let k in details){
                        if (k==='card_number'|| k==='created_by_with_name'|| k==='card_type'){continue;}
                        html += tag(k, details[k]);
                    }
                }else{
                    html += tag("Detail", row.details );
                }

                html += tag("Date", row.operation_at );


            }catch(e){
                html = row.operation_at;
            }


            return `<tr>
                        <td class='td-custom-css'>${row.operation}</td>
                        <td class='td-custom-css'>${displayNullAsStr(row.payment_method, '--')}</td>
                        <td class='td-custom-css'>${displayNullAsStr(row.payment_type, '--')}</td>
                        <td class='td-custom-css'>
                            <ul class="list-group">
                                ${html}
                            </ul>
                        </td>
                    </tr>`;

        }

        let header = `<tr> <th class='th-custom-css'>Operation</th> <th class='th-custom-css'>Payment Method</th> <th class='th-custom-css'>Payment Type</th> <th class='th-custom-css' with="30%"> Details </th> </tr>`;
        let rows = data.map((d)=>historyView(d)).join("");
        return dataTableTemplate(`booking-history-deatils-${id}`, header, rows );
    }

    /**
     * displayBookingTransactionDetail
     * @param {*} data
     * @param {*} booking
     * @param {*} id
     * @returns string html
     */
    const displayBookingTransactionDetail = (data, booking, id) =>{

        let BookingTransView = (row)=>{
			if(row.is_group_rate == '1') {
				let rate = parseFloat(row.rate);
				let pfee = parseFloat(row.permit_fee);
				let tax = parseFloat(row.additional_charge);
				return `<tr>
                        <td class='td-custom-css'>${row.tourists}</td>
                        <td class='td-custom-css'>${row.rate} <small class="text-muted">(${dollarNumFmt(rate)})</small></td>
                        <td class='td-custom-css'>${row.permit_fee}<small class="text-muted">(${dollarNumFmt(pfee)})</small> </td>
                        <td class='td-custom-css'>${row.additional_charge}<small class="text-muted">(${dollarNumFmt(tax)})</small></td>
                        <td class='td-custom-css'><small class="text-muted">=</small> ${dollarNumFmt(rate+pfee+tax)} </td>
                    </tr>`;
			} else {
                let tourists = (row.infants > 0) ? parseFloat(row.infants) : parseFloat(row.tourists);
				let rate = parseFloat(row.rate) * parseFloat(tourists);
				let pfee = parseFloat(row.permit_fee) * parseFloat(tourists);
				let tax = parseFloat(row.additional_charge) * parseFloat(tourists);
				return `<tr>
                        <td class='td-custom-css'>${tourists}</td>
                        <td class='td-custom-css'>${row.rate} x ${tourists} <small class="text-muted">(${dollarNumFmt(rate)})</small></td>
                        <td class='td-custom-css'>${row.permit_fee} x ${tourists} <small class="text-muted">(${dollarNumFmt(pfee)})</small> </td>
                        <td class='td-custom-css'>${row.additional_charge} x ${tourists} <small class="text-muted">(${dollarNumFmt(tax)})</small></td>
                        <td class='td-custom-css'><small class="text-muted">=</small> ${dollarNumFmt(rate+pfee+tax)} </td>
                    </tr>`;
			}

        }

        let header = `<tr> <th class='th-custom-css'>Tourists</th> <th class='th-custom-css'>Rate</th> <th class='th-custom-css'>Permit Fee</th> <th class='th-custom-css'>Tax</th> <th class='th-custom-css'>Total</th>  </tr>`;

        let rows = data.booking_tansactions.map((d)=>BookingTransView(d)).join("");
        //let tourists = data.map((d)=>parseInt(d.tourists)); //<td>${getArraySum(tourists)}</td>
        let amt = data.booking_tansactions.map((d)=>parseFloat(d.total));
        let total = getArraySum(amt);
        let footer = `<tr> <td class='td-custom-css' colspan="4"> </td> <td class='td-custom-css'>${dollarNumFmt(total)}</td> </tr>`
        return `${dataTableTemplate(`booking-transact-deatils-${id}`, header, rows, footer )}
                ${displayCostBreakDown(data, total, booking[0], id)}
        `;
    }


    /**
     * Display the cost sums for a report
     * note this is called as a render function from a datatable
     * @param {*} data
     * @param {*} ticket_cost
     * @param {*} booking
     * @param {*} id
     * @returns string html
     */
    const displayCostBreakDown = (data, ticket_cost, booking, id) => {

        // console.log(data);
        // console.log(ticket_cost);
        // console.log(booking);
        return `
        <ul class="list-group">
            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Ticket Cost:</small>
                <span class="float-end"><small>${dollarNumFmt(ticket_cost)}</small></span>
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Discount:</small>
                <span class="float-end"><small>- ${dollarNumFmt(data.booking[0].discount2_value)}</small></span> 

            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Subtotal:</small>
                <span class="float-end"><small>${ dollarNumFmt( parseFloat(ticket_cost) - parseFloat(data.booking[0].discount2_value) )}</small></span> 

            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Booking Fees:</small>
                <span class="float-end"><small>${dollarNumFmt(data.booking[0].service_commission)}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Booking Total: </small>
                <span class="float-end"><small>${dollarNumFmt(booking.gross_revenue)}</small></span>
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Refund Amount:</small>
                <span class="float-end"><small>${dollarNumFmt(booking.refund_total)}</small></span>
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm">
                <small>Payment Total:</small>
                <span class="float-end"><small>${dollarNumFmt(booking.gross_revenue - booking.refund_total)}</small></span>
            </li>
        </ul>`;
    }

    /**
     *
     * Display booking history and transaction detail
     * @param {*} data
     * @param {*} booking
     * @param {*} id
     * @returns html/jsx view
     */
    const bookingDetailsAsyncView = (data, booking, id) => {
        return `
        <div class="text-base font-semibold text-[#111827] mb-1">Transaction Detail</div>
        ${displayBookingTransactionDetail(data, booking, id )}
        <div class="text-base font-semibold text-[#111827] mt-4 mb-1"> History Detail </div>
            ${displayHistoryView(data.history, id)}
        `;
  }

  /**
   * @function accordionTemplate
   * @description return a bootstrap 5 accordion wrapper
   * @returns string html
   */
  const accordionTemplate = (id="") =>{
    return `<div class="accordion" id="${id}"></div>`;
  }


  const accordionItemTemplate = (idx=0, title="", content="", show=false) => {
    let btn = (show) ? "" : "collapsed";
    let cls = (show) ? "collapse show" : "collapse";
    let exp = (show) ? "true" : "false";

    return `
    <div class="accordion-item mb-2">
        <h2 class="accordion-header" id="panel-heading-${idx}">
            <button class="accordion-button accordion-button-wrap ${btn}" type="button" data-bs-toggle="collapse" data-bs-target="#panel-collapse-${idx}" aria-expanded="${exp}" aria-controls="panel-collapse-${idx}">
            ${title}
            <svg class="ml-3 align-text-top" width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M0.590332 0.590332L4.13331 4.13331L7.67628 0.590332" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </h2>
        <div id="panel-collapse-${idx}" class="accordion-collapse border border-[#e5e7eb] rounded-lg p-4 ${cls}" aria-labelledby="panel-heading-${idx}">
            <div class="accordion-body p-0">${content}</div>
        </div>
    </div>`;
  }

  /**
   * chartJSYAxisNumberFmt
   * Y axis label format
   * @returns string|number for the bottom lable of a chart
   *
  */
const chartJSYAxisNumberFmt = () => {
    return {
        beginAtZero:true,
        userCallback: function(value, _index, _values) {
          value = value.toString();
          value = value.split(/(?=(?:...)*$)/);
          value = value.join(',');
          return value;
        }
      };
  }

  /**
   * chartJSLableNumberFmt
   * return the formated lable for chart
   * @param wrapper function to format the label
   * @returns object label for chart js line/pie/ chart
   *
  */
  const chartJSLableNumberFmt = (wrapper=null) => {
    if(!wrapper){ wrapper = dollarNumFmt; }
    return {
        label: function (tooltipItem, data) {
            let label = data.labels[tooltipItem.index];
            let value = data.datasets[tooltipItem.datasetIndex].data[tooltipItem.index];
            return ' ' + label + ' ' + wrapper(value) + '';
        }
    };
  }

  /**
   * Return a modal html for bootstrap 5
   * @param {*} props id,size,title,content,topclose,close,footer
   * @returns
   */
  const bootstrapModalHtml = (props) => {
    const dynamicClass = props.id === "booking-addons" ? "max-w-[440px]" : "";
    return `<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="${props.id}" tabindex="-1" aria-labelledby="${props.id}-modal-label" aria-hidden="true"
    data-mdb-backdrop="true"
    data-focus-on="input:first"
    data-mdb-keyboard="true">
    <div class="modal-dialog modal-${props.size || "md"} ${dynamicClass} max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
        <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
        ${ (props.title) ? `<h5 class="modal-title text-lg font-semibold text-[#111827]" id="${props.id}-modal-label">${props.title}</h5>` : ""  }
        ${ (props.topclose) ? props.topclose : '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' }
        </div>
        <div class="modal-body p-5 h-[calc(100%-60px)] overflow-x-auto" id="${props.id}-modal-body">
            ${props.content}
            ${props.action || ""}
            ${ (props.close) ? props.close :  ''}
            ${props.footer || ""}
        </div>
    </div>
    </div>
    </div>
    </div>`

  }

/**
   * @function truncatedString
   * @description trancate a string in the datatable
   * @requires string
*/
const truncatedString = (str, len=20)  => {
    return (str.length > len) ? `${str.slice(0, len)}...` : str;
}


/**
 * @function localstorageUtils
 * methods for working with local storage
 */
const localstorageUtils = {
    //set local storage item
    set:(key, value="")=>{
       return localStorage.setItem(key, value);
    },

    //get local storage item
    get:(key, default_value=null, as_bool=false)=>{
        let res = localStorage.getItem(key);
        if(!res){ res = default_value;}
        if(as_bool){ return locals.bool(res);}
        return res;
    },

    //remove a local storage item by key
    del:(key)=>{
        return localStorage.removeItem(key);
    },

    //delete all items in local storage
    clr:()=>{
        return localStorage.clear();
    },

    //check if value in local storage is false|true
    bool:(v)=>{
        if(v === undefined || v === null || v === 'false' || v == '0' || v ==''){
            return false;
        }else{
            return true;
        }
    }
};


const timeSlotToStandardTime = ( t ) => {
   return  moment(t, "hm").format('LT');
}


const helperReportFilter = () => {

        let paymentDisplay = (pt) => {
            return `<div class="flex items-center mb-1"><input id="rev-filter-${pt}" class="rev-filter w-4 h-4 mr-2" data-type="payment_type"  type='checkbox' checked value="${pt}" /> <label for="rev-filter-${pt}">${pt}</label></div>`;
        }

        return `<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="rev-report-filter-modal" tabindex="-1" aria-labelledby="rev-report-filter-modal-label" aria-hidden="true"
                data-mdb-backdrop="true"
                data-focus-on="input:first"
                data-mdb-keyboard="true">
                <div class="modal-dialog modal-md my-[20px] max-w-[400px] mx-auto min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="rev-report-filter-modal-label">Filter List</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
                    </div>
                    <div class="modal-body p-4" id="revenue-modal-view">
                    <form class="form-control">
                    <div class="form-group mb-4 inline-block text-sm font-normal text-[#020817]">
                        ${["Cash", "Card", "Wire Transfer", "Check/Wire", "Invoiced"].map((pt) => paymentDisplay(pt)).join("\n")}
                    </div>

                    <div class="form-group mb-4">

                        <div class="nat-accordion collapsed form-custom-input flex items-center" data-bs-target="#collapseExternalBookingsArea" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="collapseExternalBookingsArea">
                            Bookings Imported From External System
                            <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#9CA3AF" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </div>

                        <div id="collapseExternalBookingsArea" class="collapse hide w-full border border-solid border-[#E2E8F0] p-4 rounded-lg">
                            <div class="flex items-center mb-1">
                                <input id="rev-filter-bookingsystemnone" name="bookingsys" class="rev-filter w-4 h-4 mr-2" data-type="bookingsystemnone" type='radio' value="0" />
                                <label class="inline-block text-sm font-normal text-[#020817]" for="rev-filter-bookingsystemnone" >Exclude External Bookings</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="rev-filter-bookingsystem" checked name="bookingsys" class="rev-filter w-4 h-4 mr-2" data-type="bookingsystem"  type='radio' value="1" />
                                <label class="inline-block text-sm font-normal text-[#020817]" for="rev-filter-bookingsystem">Include External Bookings</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="rev-filter-bookingsystemWithFee" name="bookingsys" class="rev-filter w-4 h-4 mr-2" data-type="bookingsystem"  type='radio' value="2" />
                                <label class="inline-block text-sm font-normal text-[#020817]" for="rev-filter-bookingsystemWithFee">Include External Bookings With Fees</label>
                            </div>
                            <div class="flex items-center mb-1">
                                <input id="rev-filter-bookingsystemonly" name="bookingsys" class="rev-filter w-4 h-4 mr-2" data-type="bookingsystemonly" type='radio' value="1" />
                                <label class="inline-block text-sm font-normal text-[#020817]" for="rev-filter-bookingsystemonly">Only Show External Bookings</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-4 flex items-center">
                        <input id="rev-filter-cancel" class="rev-filter w-4 h-4 mr-2" data-type="cancelled"  type='checkbox' value="1" />
                        <label class="inline-block text-sm font-normal text-[#020817]" for="rev-filter-cancel">Remove Cancelled Bookings</label>
                    </div>

                    <div class="form-group mb-4" style="display: none">
                        <div class="text-sm font-semibold text-[#111827] me-2"> Sort Date: </div>
                        <div class="form-check form-check-inline flex items-center mb-1">
                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="revReportSortOption" id="revReportASC" value="ASC" />
                            <label class="inline-block text-sm font-normal text-[#020817] form-check-label" for="revReportASC">Ascending</label>
                        </div>

                        <div class="form-check form-check-inline flex items-center mb-1">
                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="revReportSortOption" id="revReportDESC" value="DESC" checked />
                            <label class="inline-block text-sm font-normal text-[#020817] form-check-label" for="revReportDESC">Descending</label>
                        </div>

                    </div>
                    </form>
                    </div>
                    <div class="modal-footer px-4 pb-4">
                        <div class="btn-group btn-sm flex flex-wrap -mx-3">
                            <div class="w-1/2 px-3">
                                <button type="button" class="btn btn-primary rev-report-reload bg-[#264F73] border border-[#264F73] text-white text-[12px] font-semibold p-2 w-full rounded-[6px]">Filter Results</button>
                            </div>
                            <div class="w-1/2 px-3">
                                <button type="button" class="btn btn-secondary bg-[#E5E7EB] border border-[#E5E7EB] text-[#4B5563] text-[12px] font-semibold p-2 w-full rounded-[6px]" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
                </div>
                </div>`;

}


function helperExportToCsv(filename, rows) {
    var processRow = function (row) {
        var finalVal = '';
        for (var j = 0; j < row.length; j++) {
            var innerValue = row[j] === null ? '' : row[j].toString();
            if (row[j] instanceof Date) {
                innerValue = row[j].toLocaleString();
            };
            var result = innerValue.replace(/"/g, '""');
            if (result.search(/("|,|\n)/g) >= 0)
                result = '"' + result + '"';
            if (j > 0)
                finalVal += ',';
            finalVal += result;
        }
        return finalVal + '\n';
    };

    var csvFile = '';
    for (var i = 0; i < rows.length; i++) {
        csvFile += processRow(rows[i]);
    }

    var blob = new Blob([csvFile], { type: 'text/csv;charset=utf-8;' });
    if (navigator.msSaveBlob) { // IE 10+
        navigator.msSaveBlob(blob, filename);
    } else {
        var link = document.createElement("a");
        if (link.download !== undefined) { // feature detection
            // Browsers that support HTML5 download attribute
            var url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    }
}



const printHtmlContent = (html, title, style="") => {
    let win = window.open('', 'PRINT', 'height=650,width=900,top=100,left=150');
        win.document.write(`<html><head><title>${title}</title>`);
        win.document.write(`<style>${style}</style>`);
        win.document.write('</head><body >');
        win.document.write(html);
        win.document.write('</body></html>');
        win.document.close(); // necessary for IE >= 10
        win.focus(); // necessary for IE >= 10*/
        win.print();
        win.close();

    return true;
 }
