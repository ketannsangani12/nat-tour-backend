
/**
 * 
 * @param {*} props 
 * {  url: string, admin: string,   }
 */
const invokeDailyBreakdownReport = ( props ) => {
    const _dailyBreakdownRepost = new DailyBreakdownReport(props);
}

class DailyBreakdownReport {


    constructor(props){

        // base vars
        this.title = "Daily Tour Summary";

        // get base url 
        this.baseUrl = props.url;

        // get dates
        const { start_date, end_date } = this.getDateRange();
        this.fromDate = start_date;
        this.toDate = end_date;

        // form prefix name
        this.prefix = "daily-dreakdown-report";

        // init the report view 
        this.initView();

    }


    initView(){

        // display loader
        showLoading();

        // update the view ad a title to the report heading.
        updateReportInfo(`${this.title}`, {from: this.fromDate, to: this.toDate});
        //updateActionBtn(downloadReport);

        // add ui elements
        let mainView = $('#mainTableDiv');
        mainView.html(`<div id="${this.prefix}-tour-sales"> </div>`);

        // hide loader
        hideLoading();

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
     * @function featchTourSales
     * @description build the 1st table in the report set
     * @returns {*} start and end date yyyy-mm-dd
     */
    featchTourSales(){
        const uri = "";
        $.get(`${url}${uri}`).done(this.renderInvoiceDetailOk.bind(this)).fail(this.renderTourSales)
    }
    /**
     * @function renderTourSales
     * @description render the tour sales table
     * @returns {*} start and end date yyyy-mm-dd
     */
    renderTourSales(reponse){

        const {results} = reponse;
        const thead = dataTableHeaderTemplate(['Affiliate', 'Tourist', 'Gross Amount']);
        const tfoot = "";
        const table = dataTableTemplate('', thead, tbody.join(""), tfoot, "tbody-sm");
        $(`#${this.prefix}-tour-sales`).html( table );

    }

}