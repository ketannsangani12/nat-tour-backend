const historyAsyncView = (data, id) => {
      return  $(displayHistoryTableView(data.history, id));
  }


   	/**
     * displayHistoryTableView
     * @param {*} data 
     * @param {*} id 
     * @returns string html
     */
  const displayHistoryTableView = (data, id) => {
        let historyView = (row)=>{
            let details = "";
            let html = "";
            const tag = (title, detail) => `<li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-[#E5E7EB] text-black text-sm"> <small class="font-medium">${titleize(title)}:</small> <span class="float-end"><small>${detail}</small></span> </li>`;

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
                        <td class="td-custom-css">${row.operation}</td> 
                        <td class="td-custom-css">${displayNullAsStr(row.payment_method, '--')}</td> 
                        <td class="td-custom-css">${displayNullAsStr(row.payment_type, '--')}</td> 
                        <td class="td-custom-css"> 
                            <ul class="list-group">
                                ${html}
                            </ul>
                        </td>
                    </tr>`;

        }
    
        let header = `<tr> <th class="th-custom-css">Operation</th> <th class="th-custom-css">Payment Method</th> <th class="th-custom-css">Payment Type</th> <th class="th-custom-css" with="30%"> Details </th> </tr>`;
        let rows = data.map((d)=>historyView(d)).join("");
        return transDataTableTemplate(`booking-history-deatils-${id}`, header, rows );   
    }


   const transDataTableTemplate = (id, thead="", tbody="", tfoot="", cls="") => {
    return `<table width="100%" class="table table-sm table-striped table-hover thead-sm" id='${id}'>
                <thead id='${id}-head'>${thead}</thead>
                <tbody class="${cls}" id='${id}-body'>${tbody}</tbody>
                <tfoot id='${id}-foot'>${tfoot}</tfoot>
            </table>`;
  }


const transAsyncViews = (data, booking, id, refund, paymentTotal, bookingTotal) => {
      return  $(displayTransView(data, booking, id, refund, paymentTotal, bookingTotal));
  }
 

     /**
     * displayTransView
     * @param {*} data 
     * @param {*} booking 
     * @param {*} id 
     * @param {*} refund 
     * @param {*} paymentTotal
     * @param {*} bookingTotal 
     * @returns string html
     */

 const displayTransView = (data, booking, id, refund, paymentTotal, bookingTotal) =>{

        let BookingTransView = (row)=>{
            let tourists = (row.infants > 0) ? parseFloat(row.infants) : parseFloat(row.tourists);
            let rate = parseFloat(row.rate) * parseFloat(tourists);
            let pfee = parseFloat(row.permit_fee) * parseFloat(tourists);
            let tax = parseFloat(row.additional_charge) * parseFloat(tourists);
            return `<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out"> 
                        <td class="td-custom-css">${tourists}</td> 
                        <td class="td-custom-css">${row.rate} x ${tourists} <small class="text-muted">(${dollarNumFmt(rate)})</small></td> 
                        <td class="td-custom-css">${row.permit_fee} x ${tourists} <small class="text-muted">(${dollarNumFmt(pfee)})</small> </td> 
                        <td class="td-custom-css">${row.additional_charge} x ${tourists} <small class="text-muted">(${dollarNumFmt(tax)})</small></td>
                        <td class="td-custom-css"><small class="text-muted">=</small> ${dollarNumFmt(rate+pfee+tax)} </td>
                    </tr>`;
        }

        let header = `<tr> <th class="th-custom-css">Tourists</th> <th class="th-custom-css">Rate</th> <th class="th-custom-css">Permit Fee</th> <th class="th-custom-css">Tax</th> <th class="th-custom-css">Total</th>  </tr>`;
        
        let rows = data.booking_tansactions.map((d)=>BookingTransView(d)).join("");
        let amt = data.booking_tansactions.map((d)=>parseFloat(d.total));
        let total = getArraySum(amt);
        let footer = `<tr> <td class="td-custom-css" colspan="4"> </td> <td class="td-custom-css">${dollarNumFmt(total)}</td> </tr>`
        return `${dataTableTemplate(`booking-transact-deatils-trans-${id}`, header, rows, footer )}
                ${displayCostBreakDownTrans(data, total, booking[0], id, refund, paymentTotal, bookingTotal)}
        `;  
    }


     /**
     * Display the cost sums for a report 
     * note this is called as a render function from a datatable
     * @param {*} data 
     * @param {*} ticket_cost 
     * @param {*} booking 
     * @param {*} id 
     * @param {*} refund 
     * @param {*} paymentTotal 
     * @param {*} bookingTotal 
     * @returns string html
     */
    const displayCostBreakDownTrans = (data, ticket_cost, booking, id, refund, paymentTotal, bookingTotal) => {

        return `
        <ul class="list-group">
            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Ticket Cost:</small>
                <span class="float-end"><small>${dollarNumFmt(ticket_cost)}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Discount:</small>
                <span class="float-end"><small>- ${dollarNumFmt(booking.discount2_value)}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Subtotal:</small>
                <span class="float-end"><small>${ dollarNumFmt( parseFloat(ticket_cost) - parseFloat(booking.discount2_value) )}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Booking Fees:</small>
                <span class="float-end"><small>${dollarNumFmt(booking.service_commission)}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Booking Total: </small>
                <span class="float-end"><small>${dollarNumFmt(bookingTotal)}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Refund Amount:</small> 
                <span class="float-end"><small>${dollarNumFmt(refund)}</small></span> 
            </li>

            <li class="list-group-item flex items-center justify-between px-4 py-2.5 border border-t-0 border-[#E5E7EB] text-black text-sm"> 
                <small>Payment Total:</small>
                <span class="float-end"><small>${dollarNumFmt(paymentTotal)}</small></span> 
            </li>
        </ul>`;
    }