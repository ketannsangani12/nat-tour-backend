/**
 * @class WebQRCodeCheckIn
 * this is a sample test flow for customer qr code checkings. 
 * The web version is ment to mimic the ipad app api functions 
 * Flow
 * ------------------------------
 * pull up booking by qr code data
 * search for the booking return data or error message
 * ensure we are talking to the correct booking
 * next btn ->
 * check tour time and current time, make sure tour is at least within the next 10 mins
 * check if they signed the waver
 * display check in button for each user. 
 * next btn ->
 * display custom message about tour. 
 */
class WebQRCodeCheckIn {

    constructor(props) {
        this.onComponentLoad(props);
        this.eventListeners();
    }

    /**
     * @function onComponentLoad
     * init all class vars. 
     * @param {*} props 
     * @returns void
     */
    onComponentLoad(props) {
        if (props.admin && props.admin === 'Admin') {
            this.admin = "/admin/tour-operator";
        } else if (props.admin && props.admin === 'Tour Operator') {
            this.admin = "/tour-operator";
        } else if (props.admin && props.admin === 'Affiliate') {
            this.admin = "/affiliate";
        } else {
            this.admin = "";
        }

        this.media = props.media;
        this.booking_id = 0;
        this.checkinData = 0;
        this.setPresentToggle = 0;
        this.waiverFormData = {};
        this.countDown = 60;
        this.interval = null;
        this.tenant = props.tenant;
        this.searchBookingByGroupId = `${this.admin}/checkin/search-booking`;
    }

    /**
     * @function eventListeners
     * inti all class envent listeners
     * @returns void
     */
    eventListeners() {
        $(document).off('click', "#init-qr-code-checkin").on("click", "#init-qr-code-checkin", this.redirectToBookingInfo.bind(this));
        // $(document).off('click', "#init-qr-code-checkin").on("click", "#init-qr-code-checkin", this.getBooking.bind(this));
        $(document).off('click', ".reset-view").on("click", ".reset-view", this.resetViewWrongUser.bind(this));
        $(document).off('click', ".begin-check-in").on("click", ".begin-check-in", this.beginCheckin.bind(this));
        $(document).off('blur', ".check-status-on-blur").on("blur", ".check-status-on-blur", this.waiverFormValidate.bind(this));
        $(document).off('change', ".check-in-status").on("change", ".check-in-status", this.waiverFormValidate.bind(this));
        $(document).off('click', ".check-in-status-set-all-present").on("click", ".check-in-status-set-all-present", this.waiverSetAllPresent.bind(this));
        $(document).off('click', ".complete-checkin").on("click", ".complete-checkin", this.completeCheckin.bind(this));
        $(document).off('click', ".go-to-restricted-items-screen").on("click", ".go-to-restricted-items-screen", this.restrictedItemsScreen.bind(this));
        $(document).off('click', ".go-to-success-screen").on("click", ".go-to-success-screen", this.successScreen.bind(this));
        $(document).off('click', ".complete-waiver-checkin").on("click", ".complete-waiver-checkin", this.waiverCompleteCheckin.bind(this));
        $(document).off('click', ".print-receipt ").on("click", ".print-receipt ", this.printScreen.bind(this));
    }

    /**
     * @function getError
     * Display a default dialog with error message from json response. 
     * @param string msg 
     * @returns void
     */
    getError(msg = "Unknow Error Occured") {
        swal({ title: 'Error', text: msg, icon: 'error' });
    }

    /**
     * @function getHeaders
     * return default header object with csrf token
     * @param {*} headers 
     * @returns {Object}  
     */
    getHeaders(headers = {}) {
        headers['X-Csrf-Token'] = $("#csrf_token").val();
        // headers['Content-Type'] = 'application/json';
        return headers
    }

    /**
     * @function setMainView
     * Update or Append data to the main checkin flow
     * @param {*} html 
     * @param {*} action replace | append
     * @returns void
     */
    setMainView(html = "", action = "replace") {
        if (action === 'append') {
            $("#check-in-flow-display").append(html);
        } else {
            $("#check-in-flow-display").html(html);
        }
    }


    /**
     * @function resetViewWrongUser
     * if the user clicks reset-view because the booking is the wronge user
     * @returns void 
     */
    resetViewWrongUser(evt) {
        this.booking_id = 0;
        this.checkinData = null;
        let status = (evt) ? $(evt.target).data('display') : "";
        $("#booking-id").val("");
        $("#check-in-dev-tools").hide();
        this.resetCountDownInterval();
        if (status === 'wronguser') {
            this.setMainView(this.resetViewWrongUserTmpl());
        } else {
            this.setMainView("");
        }
    }

    /**
     * @function resetCountDownInterval
     * resest countdown interval
     * @returns void 
     */
    resetCountDownInterval() {
        try {
            clearInterval(this.interval);
        } catch (err) {
            console.log(err);
        }
        this.countDown = 60;
        this.interval = null;
    }

    /**
     * @function generateSampleQRCode
     * Generate A sample QR code for testing.
     * @returns void
     */
    generateSampleQRCode() {
        $("#qrcode").empty();
        let qrcode = new QRCode("qrcode");
        qrcode.makeCode(this.booking_id);
        $("#check-in-dev-tools").show();
    }

    redirectToBookingInfo() {
        this.booking_id = $("#booking-id").val();
        this.booking_id = this.booking_id.replace(/\D/g, '');
        let url = `${this.admin}/booking/${this.booking_id}`;
        window.location = url;
    }

    checkAndredirectToBookingInfo(package_id) {
        let url = this.searchBookingByGroupId;
        showLoading();

        $.ajax({
            type: "POST",
            url: url,
            data: {
                booking_group_id: $("#booking-group-id").val(),
                package_id: package_id
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            dataType: 'json'
        }).done((response) => {
            if (response.code == "bookingfound") {
                let url = `${this.admin}/booking/${response.bookingId}`;
                window.location = url;
            } else {
                hideLoading();
                swal({
                    title: 'Scan QR Code',
                    text: response.message,
                    icon: "warning"
                });
            }
        }).fail((error) => {
            swal(error.responseJSON.error, '', 'error');
        }).always(hideLoading);
    }

    /**
     * @function getBooking
     * Fetch the booking, note returns valiations about the booking: 
     * ie( if booking is expired or is about to start. ) 
     * @retuns void
     */
    getBooking() {
        this.resetCountDownInterval();
        this.booking_id = $("#booking-id").val();
        this.checkinData = null;
        if (this.booking_id === "") {
            return this.getError("Booking ID Can Not Be Blank.");
        }
        this.generateSampleQRCode();
        let url = `${this.admin}/checkin/validate/${this.booking_id}`;
        this.setMainView('<div class="nat-spinner"></div>');
        $.ajax({
            type: "POST",
            url: url,
            data: { _token: $("#csrf_token").val() },
            success: this.getBookingOk.bind(this),
            headers: this.getHeaders(),
        }).fail((err) => this.getError(err.responseJSON.message));
    }

    /**
     * @function getBookingOk
     * Display json response as html template from getBooking method
     * @param {*} response 
     * @returns void 
     */
    getBookingOk(response) {
        if (response.errorcode) {
            return this.setMainView(this.displayBookingErrorTmpl(response.error, response.booking));
        }
        this.checkinData = response;

        //this.setMainView( this.displayImageGridTmpl() );

        this.setMainView(this.displayBookingDetailsTmpl(response));
    }


    /**
     * @function getStartTime
     * parse the start time of the booking
     * @param {
     *     "seconds": int,
     *     "minutes": int,
     *     "hours": int,
     *     "days": int } dt 
     * @return string html markup
     */
    getStartTime(dt = {}) {

        let isPlural = (val, num) => { return (num > 1) ? val : val.substring(0, val.length - 1); }
        let alreadyStarted = 'Your tour has started. Please see the front desk for help';
        let msg = 'Your tour starts in ';
        let inc = null;
        if (dt.days != 0) { inc = 'days'; }
        if (!inc && dt.hours != 0) { inc = 'hours'; }
        if (!inc && dt.minutes != 0) { inc = 'minutes'; }
        if (!inc && dt.seconds != 0) { return alreadyStarted; }

        if (inc) {
            return `${msg} ${dt[inc]} ${isPlural(inc, dt[inc])}`;
        } else {
            return alreadyStarted;
        }
    }

    /**
     * @function beginCheckin
     * first step of check in process. 
     * @param {*} evt 
     */
    beginCheckin(_evt) {
        if (this.checkinData.waiver.signed === false) {
            this.getWaiver({ id: this.checkinData.booking_id, tourist: this.checkinData.pax_count });
        } else {
            this.getCheckInForm(this.checkinData);
        }
    }

    /**
     * @function getCheckInForm
     * @description if the waiver has been signed display a simple check in form 
     * @param {*} data 
     */
    getCheckInForm(data) {
        this.setMainView(this.displayCheckInFormTmpl(data));
    }

    /**
     * @function waiverSetAllPresent
     * toggle all chk in fields to 1st check - in
     * @return void
     */
    waiverSetAllPresent() {

        let val = $("#checkin0").val();

        if (this.setPresentToggle !== 0) {
            if (this.setPresentToggle === '1') {
                val = "A";
            }

            if (this.setPresentToggle === 'A') {
                val = "1";
            }
        }

        $(".checkin-select").each((i, e) => { $(e).val(val); });

        this.setPresentToggle = val;
    }


    /**
     * @function getWaiver 
     * @description async fetch waiver content
     * @param {*} props number of tourist | booking id
     * @returns void
     */
    getWaiver(props) {
        let url = `${this.admin}/checkin/waiver/${props.id}/${props.tourist}/1`;
        $.ajax({
            type: "POST",
            url: url,
            data: {},
            success: this.getWaiverOk.bind(this),
            headers: this.getHeaders(),
        }).fail((err) => this.getError(err.responseJSON.message));
    }
    /**
     * @function getWaiverOk
     * @description success for async fetch waiver content
     * @param {*} response
     * @returns void
     */
    getWaiverOk(response) {
        this.setMainView(this.displayWaiverContentTmpl(response));
        this.populateSignatures(this.checkinData.waiver.signatures);
    }

    /**
     * @function waiverFormValidate
     * data object props: waiverForm waiverName waiverAge checkin waiverStatus
     * @return void
     */
    waiverFormValidate(evt) {

        let idx = $(evt.target).data('idx');
        let status = '<i class="fas fa-check-circle text-success"></i> complete ';
        let valid = true;

        //package the form data group
        let data = {
            booking_id: this.checkinData.booking_id,
            tourists: this.checkinData.pax_count,
            index: idx,
            name: $(`#waiverName${idx}`).val(),
            age: $(`#waiverAge${idx}`).val(),
            chk: $(`#checkin${idx}`).val(),
            valid: valid
        };

        //validate 
        if (data.name.length <= 0 || data.age === "" || isNaN(data.age)) {
            status = ' <i class="fas fa-exclamation-circle text-danger"></i> incomplete ';
            data.valid = false;
        }

        //add the data to a shared collection
        this.waiverFormData[idx] = data;

        //update the signing row 
        $(`#waiverStatus${idx}`).html(status);

        //enable form submit btn. 
        let subBtnEnabled = false;
        for (let key in this.waiverFormData) {
            let d = this.waiverFormData[key];
            if (!d.valid) { subBtnEnabled = true; break; }
        }
        //Enable/Disable submit btn. console.log(this.waiverFormData);
        // console.log(` subBtnEnabled ${subBtnEnabled}`);
        $("#submitWaiverFormBtn").prop("disabled", subBtnEnabled);

    }


    waiverCompleteCheckin() {

        let msg = $("#agree-to-waiver-message");

        if ($("#agree-to-waiver").is(':checked')) {
            msg.removeClass('text-danger');
            let bulkCheckins = this.getWaiverSignedValues();
            let waiverSignatures = this.getWaiverSignatures();
            let url = `${this.admin}/checkin/sign/waiver/${this.booking_id}`;
            $.ajax({
                type: "POST",
                url: url,
                data: { checkins: bulkCheckins, signatures: waiverSignatures },
                success: this.waiverCompleteCheckinOk.bind(this),
                headers: this.getHeaders(),
            }).fail((err) => this.getError(err.responseJSON));

        } else {
            msg.html("Please agree to the conditions as mentioned in this liability waiver.");
            msg.addClass('text-danger');
        }

    }


    waiverCompleteCheckinOk(response) {
        this.completeCheckinOk(response);
    }

    /** 
    * @function getWaiverSignatures
    * @description return a list of signatures from the booking data
    * @returns array
    */
    getWaiverSignatures() {
        let data = { name: [], age: [], agree: 'on' };
        for (let key in this.waiverFormData) {
            let val = this.waiverFormData[key];
            data.name.push(val.name);
            data.age.push(parseInt(val.age));
        };
        return data;
    }
    /** 
    * @function getWaiverSignedValues
    * @description collect a list of signatures from the checkin form 
    * @returns array
    */
    getWaiverSignedValues() {
        let data = [];
        $(".check-in-status-signed").each((idx, ele) => {
            let d = $(ele);
            data.push({
                booking_id: this.checkinData.booking_id,
                tpr_id: d.data('tpr'),
                index: d.data('rateindex'),
                check_in_status: d.val()
            });
        });

        return data;
    }

    /**
     * booking_id: 117193
     * tpr_id: 164
     * index: 0
     * check_in_status: 1
     */
    completeCheckin() {
        //console.log( " this.getWaiverSignedValues()" );
        //console.log( this.getWaiverSignedValues());
        $.ajax({
            type: "POST",
            url: `${this.admin}/checkin/bulk/checkin`,
            data: { 'checkins': this.getWaiverSignedValues() },
            success: this.completeCheckinOk.bind(this),
            headers: this.getHeaders(),
        }).fail((err) => this.getError(err.responseJSON));
    }

    completeCheckinOk(response) {

        // console.log(response.error);
        if (response.error) {
            this.getError(response.error);
            return false;
        }
        let title = 'These items are <span class="text-success">allowed</span> on the tour.';

        if (this.checkinData.allowed_items.length > 0) {
            let nextScreenAction = (this.checkinData.restricted_items.length > 0) ? 'go-to-restricted-items-screen' : 'go-to-success-screen';
            this.setMainView(this.displayImageGridTmpl(this.checkinData.allowed_items, title, nextScreenAction, 'allowed_items'));

        } else if (this.checkinData.restricted_items.length > 0) {
            this.restrictedItemsScreen();
        } else {
            this.successScreen();
        }

    }

    restrictedItemsScreen() {
        let title = 'These items are <span class="text-danger">NOT allowed</span> on the tour. No exceptions.'
        this.setMainView(this.displayImageGridTmpl(this.checkinData.restricted_items, title, 'go-to-success-screen', "restricted_items"));
    }

    successScreen() {
        this.setMainView(this.displaySuccessTmpl());
        this.countDownScreen();
    }

    /**
     * reset view after 60 seconds
     */
    countDownScreen() {

        this.interval = setInterval(() => {

            if (this.countDown > 0) {
                this.countDown--;
                $("#count-down-time").html(this.countDown);
            } else {
                clearInterval(this.interval);
                this.resetViewWrongUser(null);
                this.countDown = 60;
            }

        }, 1000);
    }


    printScreen() {
        let url = `${this.admin}/booking/print/${this.checkinData.booking_id}?modal=1`;

        let html = `
            <iframe src="${url}" 
            style="border:0px #ffffff none;" 
            name="printiFrame" scrolling="yes" 
            frameborder="0" marginheight="0px" marginwidth="0px" height="90%" width="100%%" allowfullscreen></iframe>
        `;
        $("#print-view-display").html(this.printModalTmpl(html));
        $("#print-modal").modal("show");

    }



    /* Templates */

    /**
     * @function displayBookingDetailsTmpl
     * display a welcome message with booking details to our checked in guess
     * @param {*} data 
     * @return string html markup
     */
    displayBookingDetailsTmpl(data) {

        return `<div class="row">

                    <div class="col-6">

                            <div class="nat-center mb-4"> 
                                <i class="fas fa-check-circle text-success" style="font-size: 61px;"></i>
                            </div>

                            <div class="mt-4 mb-4"> 
                                <h2 style="text-align: center;">Welcome ${data.booking.name}! </h2>
                                <p style="text-align: center;">Lets get you checked in for ${data.booking.tour_package_name} </p>
                                <p style="text-align: center;"> <small><i>Not ${data.booking.name} <a href="javascript:void(0)" data-display="wronguser" class="reset-view" >click here</a></i></small> </p>
                            </div>

                            <div class="nat-center mt-3"> 
                                <button data-step="1" class="begin-check-in btn btn-primary"> Start Check-In </button> 
                            </div>
                            <div class="nat-center mt-3"> 
                                <p><i class="fas fa-check-circle text-info" style="font-size: 12px;"></i>  ${this.getStartTime(data.time_delta)}</p>
                            </div>
                    </div>

                    <div class="col-6">
                        <img src="${this.media}/${data.booking.tour_logo}" 
                            style="max-width:350px;"
                            class="nat-border-img" alt="${data.booking.tour_package_name} Logo" 
                            onerror="noPhoto(this)" />
                    </div>

                </div>`;

    }

    /**
     * @function displayErrorMsgTmpl
     * @param string error 
     * @returns 
     */
    displayErrorMsgTmpl(data) {
        return `
            <div class="alert alert-danger" role="alert">
                ${data.error}
                <p>${data.message}</p>
            </div>`;
    }


    /**
     * @function resetViewWrongUserTmpl
     * @returns stirng html markup
     */
    resetViewWrongUserTmpl() {
        return `
            <div class="alert alert-info" role="alert">
                Please carefully re-enter your booking id.
               <p> If the problem persists please see a representative at the front desk </p>
            </div>`;
    }

    /**
     * @function displayBookingErrorTmpl
     * @param {*} data error message from xhr request 
     * @returns string html markup
     */
    displayBookingErrorTmpl(data, booking = {}) {
        return `<div class=" p-4">
                    <div class="nat-center"> 
                        <i class="fas fa-times-circle text-danger" style="font-size: 61px;"></i>
                    </div>
                    
                    <div class="nat-center mt-3"> 
                        <h3>${data.error}</h3>
                    </div>
                    
                    <div class="mt-3"> 
                        <p style=" text-align: center;">${data.message}</p>
                    </div>

                    ${this.bookingErrorDetail(booking)}
                    
                    <div class="nat-center mt-3"> 
                        <button data-display="resetall" class="reset-view btn btn-primary">Start Over</button> 
                    </div>
             </div>`;
    }

    bookingErrorDetail(booking) {

        if (!booking) {
            return `<p style="text-align: center;"> No Booking Info </p>`;
        }
        return `
            <div class="mt-1">
            <p style="text-align: center;"> 
                Tour Name: ${booking.tour_package_name} <br /> Tour Date: ${booking.tour_d_t}
            </p>
        </div>`
    }

    checkinSelectTmpl(idx, rateindex, trp) {

        return `<select 
                data-idx="${idx}"
                data-tpr="${trp}"
                data-rateindex="${rateindex}"
                id='checkin${idx}' 
                style="max-width:200px;"
                class="form-select self-check-in-signed check-in-status-signed checkin-select" >
                    <option value="1" selected>Present</option>
                    <option value="A">Absent</option>
                </select>`;
    }


    displayCheckInFormTmpl(data) {

        //let signatures = data.waiver.signatures;
        let rates = data.rates;
        return `
            <div>
                <table class="table table-sm">
                <thead>
                    <tr> 
                        <th> Name  </th>
                        <th> Check In ( <a class="check-in-status-set-all-present" href="javascript:void(0)"> Toggle All </a> ) </th>
                    </tr>
                </thead>
                <tbody>
                ${rates.map((rate) => {
            return rate.check_in_status.map((r, idx) => {
                return `
                            <tr> 
                                <td><small>#${r.ageGroupIndex + 1}</small> ${rate.rate_for}</td> 
                                <td>${this.checkinSelectTmpl(idx, r.ageGroupIndex, rate.tour_package_rate_id)}</td>
                            </tr>`;
            }).join("");
        }).join("")}

                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="100%" class="mt-3">
                            <div class="nat-center mt-3 mb-5"> 
                                <button data-display="complete" class="complete-checkin btn btn-primary">NEXT</button> 
                            </div>
                        </td>
                    </tr>
                </tfoot>
                </table>
            </div>`;
    }


    displayWaiverContentTmpl(data) {

        return `
            <div class="row">
            <div class="col-12">
                <h3>${this.checkinData.booking.tour_package_name} • Liability Waiver</h3>
                <p>Please Read and Sign the Liability Waiver</p>
            </div>
            <div class="col-12">
                <div style="border-radius: 5px;max-height:400px; padding:10px; overflow-y: scroll; background-color:#f6f6f6">
                    ${data.waiver.bookingInfo.waiver_content}
                </div>
            </div>
                <div class="col-12 mt-3 mb-1">
                    <p><strong>Details of the people for whom this liability waiver is being signed.</strong></p>
                    ${this.waiverFormTmpl(data.waiver)}
                </div>
            </div>`;
    }


    /**
     * @function getRateGroups
     * Join the Weiver data with the rate grp so we can sign and checkin each pax
     * @param {*} data 
     * //data.rateGroups, data.touristNum, data.signingInfo
     * @returns 
     */
    getRateGroups(data) {

        let grps = [];
        let rates = data.rateGroups;

        for (let key in rates) {

            let rate = rates[key];
            let count = parseInt(rates[key].tourists);

            for (let i = 0; i < count; i++) {
                grps.push({ 'sign': ['', -1, ''], 'rate': rate, 'rateindex': i });
            }
        }


        return grps;
    }

    /**
     * @function populateSignatures
     * @description add any signatures to the waiver form.
     * @param {*} liabWaivSign list of signatures [ ["Fname Lname", 29, "yyyy-mm-dd H:M:S"] ... ]
     * @returns void 
     */
    populateSignatures(liabWaivSign) {

        if (liabWaivSign === null) {
            return false;
        }

        const txtNames = $('.txt-name');
        const txtAges = $('.txt-age');

        for (var a = 0; a < liabWaivSign.length; a++) {
            for (var b = 0; b < txtNames.length; b++) {
                if (txtNames[b].value === '') {
                    txtNames[b].value = liabWaivSign[a][0];
                }
                if (liabWaivSign[a][1] !== '') {
                    txtAges[b].value = liabWaivSign[a][1];
                    break;
                }
            }
        }
        for (var c = 0; c < txtAges.length; c++) {
            if (txtAges[c].value == -1) {
                txtAges[c].value = '';
            }
        }
    }


    waiverFormTmpl(data) {

        let getRateGroups = this.getRateGroups(data);

        let inputs = (val, idx) => {

            return `
                    <tr class="check-status-on-blur" id="waiverForm${idx}" data-idx="${idx}">
                        <td>${val.rate.rate_for}</td>
                        <td>
                            <input class="txt-name" placeholder="Enter Name" type="text" id='waiverName${idx}' name="name[]" value="" required data-idx="${idx}" /> 
                        </td>
                        <td>
                            <input style="width:80px;"  maxlength="3" class="txt-age" placeholder="Age" type="number" id='waiverAge${idx}' name="age[]" min="${val.rate.age_from}" max="${val.rate.age_to === 0 ? 150 : val.rate.age_to}"  value="${val.rate.age_from}" required data-idx="${idx}" />
                        </td>
                        <td> 

                            <select data-idx="${idx}"
                                    id='checkin${idx}' 
                                    style="max-width:200px;"
                                    data-rateindex="${val.rateindex}"
                                    class="form-select self-check-in check-in-status check-in-status-signed checkin-select" 
                                    data-tpr="${val.rate.tour_package_rate_id}" >
                                    <option class="check-in-status-1" value="1" selected="">Present</option>
                                    <option class="check-in-status-A" value="A">Absent</option>
                            </select>
                        </td>
                        <td>
                                <div id="waiverStatus${idx}"> incomplete </div>
                        </td>

                    </tr>`;
        }

        return `
            <table class="table table-sm">
            <thead>
                <tr>
                    <th width="20%">Group</th>
                    <th width="15%">Name</th>
                    <th width="5%">Age</th>
                    <th width="20%">Check in ( <a class="check-in-status-set-all-present" href="javascript:void(0)"> Toggle All </a> )</th>
                    <th width="40%"> Status </th>
                </tr>
            </thead>
            <tbody>
                ${getRateGroups.map((s, i) => inputs(s, i)).join("")}
            </tbody>

            <tfoot>
                <tr>
                <td colspan="100%">
                <label>
				<input type="checkbox" name="agree" id="agree-to-waiver" required />
				    <span id="agree-to-waiver-message" class="">I/we agree to the conditions as mentioned in this liability waiver.</span>
			    </label>

                <div class="mt-2 text-center">
                    <button class="btn btn-primary btn-lg complete-waiver-checkin" id="submitWaiverFormBtn" disabled > NEXT </button>
                </div>

                <div class="mb-5">   </div>
                </td> 
                </tr>
            </tfoot>
            </table>
        `;

    }


    /**
     * @function displayImageGridTmpl
     * display a list of images and next with captions if images are set as Allowed or NotAllowed tour_package_images
     * @returns string html markup
     */
    displayImageGridTmpl(items, title = "", next = "go-to-success-screen", classname = "", style = "") {

        let row = 0;
        return `<div class="${classname}">
            
                <h1 class="text-center mb-4">${title}</h1>
                <div class="row" >
                    ${items.map((item, index) => {

            return `
                                <div class="col-lrg-2 col-md-4 col-sm-6 col-xsm-12 mt-2">
                                <div class="card">
                                    <img src="${item.url}" id="not-allowed-img-${index}"  style="${style}" class="card-img-top" alt="${item.caption}" />
                                    <div class="card-body" style="min-height:82px;">
                                        <i class="fas fa-check-circle text-success" ></i> ${item.caption} 
                                    </div>
                                </div>
                                </div>`;
        }).join("")}

                </div>
                
                <div class="nat-center mt-4"> 
                    <button class="btn btn-primary btn-lg ${next}">I Understand</button>
                </div> 
            
            </div>`;
    }


    /**
     * @function displaySuccessTmpl
     * final screen displayed after checkin success
     * @returns string html markup
     */
    displaySuccessTmpl() {

        return `
            <!--container --> 
            <div class="container" style="width:80%;">

                <!-- countdown -->
                <div class="row">
                    <div class="col-12 text-center">
                        <strong>Check in complete!</strong> 
                        <i class="far fa-clock"></i> This view will reset in <span class="text-success" id="count-down-time">-</span> seconds.
                    </div>
                </div>
                <!-- countdown -->

                <!-- header --> 
                <div class="row"> 
                    <div class="col-12">
                        <div class="nat-center mb-4"> 
                            <div style="text-align:center; font-size:35px;">Great! Get ready to go on an adventure of a lifetime!</div>
                        </div>
                    </div>
                </div>
                <!-- header --> 

                <!-- info --> 
                <div class="row" style="border-radius:5px; background-color:#f2fbff; border:1px dashed #93c1e2; padding:25px;"> 
                    
                    <!-- detail -->
                    <div class="col-6"> 
                        
                        <div class="text-center">
                        
                            <div class="mt-4"> </div>
                            <strong> <i class="fas fa-clock"></i> Your Tour Time: </strong> <br /> 
                            ${this.checkinData.booking.tour_d_t}<br /> 
                            
                            <div class="mt-4"> </div>
                            <strong> <i class="fas fa-users"></i> Number in Party: ${this.checkinData.pax_count} </strong> <br /> 
                            ${this.displayPaxCountTmpl()}
                            <br /> 

                            <div class="mt-4"> </div>
                                <button class="btn btn-primary print-receipt" style="margin-top:4px;" > Print Receipt  </button>  
                                <button class="btn btn-primary reset-view" style="margin-top:4px;" > New Check-In  </button>
                            <div class="mt-4"> </div>

                        </div>
                        
                    </div>
                    <!-- detail --> 

                    <!-- img --> 

                    <div class="col-6">
                        ${this.displayTourCoverImgTmpl()}
                    </div> 
                    <!-- img --> 

                    <!-- Directions -->
                        ${this.displayDirectionsTmpl()}
                    <!-- Directions -->

                </div> 
                <!-- info --> 

            
                <!-- bottom info --> 
                <div class="row">
                    <div class="col-12">
                        <div class="nat-center mt-4">
                            <i class="fas fa-check-circle text-info" style="margin-top:4px; margin-right:5px"></i> All tour group must show their receipt to the coordinator
                            15 minutes before their tour time
                        </div>
                    </div>

                    
                    <div class="col-12">
                        
                        <hr />
                        <div class="nat-center mt-3 mb-3">
                            <button class="reset-view btn btn-success btn-lg">Done</div>
                        </div>
                    </div>



                </div> <!-- bottom info --> 
                
                </div>
                <!-- container --> `;
    }

    /**
     * @function displayTourCoverImgTmpl
     * if a cover images is set in tour_package_images with tour pkg id it will be displayed 
     * on the success screen if not it will display a generic success message. 
     * @returns string html
     */
    displayTourCoverImgTmpl() {
        if (this.checkinData.booking.tour_image != "") {
            return `<img class="nat-border-img"  src="${this.checkinData.booking.tour_image}" alt="${this.checkinData.tour_package_name}" />`;
        } else {
            return `<div class="nat-border-img" style="min-width:430px; min-height:300px"> 
                        <h3>Check-In Successful! </h3>
                        <p> If you have any questions plesae seek help at the front desk. </p>
                    </div>`;
        }
    }

    /**
     * @function displayDirectionsTmpl
     * if direction images are set in tour_package_images with tour pkg id it will be displayed 
     * on the success screen else nothing will show
     * @returns string html
     */
    displayDirectionsTmpl() {

        let directions = this.checkinData.directions;
        if (directions.length > 0) {

            return `
                <div class="col-12 mt-4">
                    <hr />
                    ${directions.map((d, idx) => `<div> <img style="margin:auto; text-center" class="nat-center nat-border-img" src="${d.url}" alt="${d.caption}" /> <p class="text-center mt-3">${d.caption}</p> </div>`)}
                </div>`;
        }

        return ``;
    }


    /**
     * @function displayPaxCountTmpl
     * @returns string html markup
     */
    displayPaxCountTmpl() {

        let rates = this.checkinData.rates;
        return `
    
            <div class="well"> 
                ${rates.map((r) => {
            return `<p>${r.tourists} :  ${r.rate_for} </p>`;
        }).join("")}
            
            </div> 
        `;
    }


    /**
    * Print Modal
    * this is shown when the user clicks print  
    * @returns string html 
    */
    printModalTmpl(booking) {
        return bootstrapModalHtml({
            id: "print-modal",
            size: "fullscreen",
            title: 'Print Bookings',
            content: booking,
            action: '',
            footer: '',
        });
    }

    // For bundle/non-bundle package
    redirectToSearchBookingWithPackage(package_id) {
        // this.package_id = $("#package_id").val();
        // this.is_bundle_package = $("#is_bundle_package").val();
        this.package_id = package_id;
        // this.is_bundle_package = is_bundle_package;
        // this.package_id = this.package_id.replace(/\D/g,'');
        let url = `${this.admin}/checkin/index/${this.package_id}`;
        window.location = url;
    }
}