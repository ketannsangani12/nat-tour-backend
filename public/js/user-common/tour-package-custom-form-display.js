class CustomFormDisplay {

    /**
     * This is called from the template blade booking info view
     * @param {*} props 
     * @returns 
     */
    // static initCustomFormDisplay(props={}){
    //     return new CustomFormDisplay(props);
    // }

    constructor(props) {

        if (document.getElementById("booking-addons") === null) {
            console.log("add-ons target dom element not found, enable CUSTOM_FIELDS in settings ")
            return false;
        }

        this.props = props;
        this.fields = [];
        this.formId = null;
        this.formData = [];
        this.bookingId = props.bookingId;
        this.packageId = props.packageId;

        if (props.admin === 'Admin') {
            this.admin = "/admin/tour-operator";
        } else if (props.admin === 'Tour Operator') {
            this.admin = "/tour-operator";
        } else if (props.admin === 'Affiliate') {
            this.admin = "/affiliate";
        } else {
            this.admin = "";
        }

        this.fetchUrl = `${this.admin}/package/get-package-form-submission`;
        this.updateUrl = `${this.admin}/package/put-package-form-submission`;
        this.fetchFormUrl = `${this.admin}/package/get-package-form/${this.packageId}`;
        this.upsertAddonsUrl = `${this.admin}/booking/upsert-addons`;

        this.refundType = null;
        this.partialRefundValue = 0;
        this.paymentAmount = 0;
        this.feesAmount = 0;
        this.refundAmount = 0;
        this.paymentItems = [];
        this.refundItems = [];
        this.messages = [];
        this.changeAmount = 0;

        this.addonFees = 0;
        this.targetDiv = "booking-addons";
        this.addEvents();
        this.fetchAddons();
    }

    /**
     * Add Event Listeners
     */
    addEvents() {
        // Don't add edit event for old add-ons (read-only mode)
        if (typeof hasOldAddons !== 'undefined' && hasOldAddons) {
            return; // Skip adding edit events for old add-ons
        }
        $(document).off('click', '.edit-custom-addons-form').on('click', '.edit-custom-addons-form', this.onEditForm.bind(this));
    }

    /**
     * Display a list of addons 
     */
    fetchAddons() {
        // show loading....
        $(`#${this.targetDiv}`).html(this.renderLoading());
        $.get(`${this.fetchUrl}/${this.bookingId}`).done(this.fetchAddonsOk.bind(this)).fail(this.onError.bind(this));
    }
    /**
     * 
     * @param {*} data 
     */
    fetchAddonsOk(data) {

        if (data.fields === null) {
            this.renderFormEditAction();
            return false;
        }

        // Add "Read Only" indicator for old add-ons
        const titleText = (typeof hasOldAddons !== 'undefined' && hasOldAddons) 
            ? "Booking Add-Ons (Legacy - Read Only):" 
            : "Booking Add-Ons:";
        $("#booking-addons-title").html(titleText);

        let addons = [];
        this.formId = data.form_id;
        this.fields = data.fields;
        //sort the fields 
        data.fields.sort((a, b) => { return (parseInt(a.order) < parseInt(b.order)) ? -1 : 1 });

        if (data.fields) {
            for (let key in data.fields) {
                let f = data.fields[key];
                this.addonFees += parseFloat(f.priceInfo.price);
                addons.push(this.renderField(f));
            }
        }

        this.renderGrandTotal();

        if (addons.length > 0) {
            const html = this.renderTable('custom-field-addons-display',
                addons.join("\n"),
                this.renderHeader(),
                this.renderFooter()
            );
            $("#booking-addons-wrap").removeClass('d-none');
            $("#cc_section").addClass('mt-8');
            $(`#${this.targetDiv}`).html(html);
        } else {
            $("#booking-addons-wrap").addClass('d-none');
            $("#cc_section").removeClass('mt-8');
            $(`#${this.targetDiv}`).html("");
        }
    }


    /**
     * let the user edit the addons in a html form 
     * @returns void
     */
    onEditForm() {
        try {
            let form = null;
            if (this.fields.length > 0) {
                form = {
                    id: this.formId,
                    form_name: `Booking Addons for ${this.bookingId}`,
                    status: "active",
                    form_package_id: this.packageId,
                    form_fields: JSON.stringify(this.fields)
                }
            }

            $("#booking-addons").hide();
            this.renderMessages([], true);
            new CustomFormReader({
                admin: this.props.admin,
                dom: "booking-addons-form-view",
                path: this.fetchFormUrl,
                form: form,
                onSubmit: this.onFormSubmit.bind(this),
                submitAction: "Update Addons",
                onCancel: (_evt) => {
                    $("#booking-addons").show();
                    $("#booking-addons-form-view").html("");
                }
            });
        } catch (err) {
            console.log(err);
        }
        return true;
    }

    /** 
     * on form submit 
     */
    onFormSubmit(_evt, formData) {
        this.formData = formData;
        this.paymentMethod = paymentMethod;
        this.refundType = null;
        this.partialRefundValue = 0;
        this.paymentAmount = 0;
        this.feesAmount = 0;
        this.refundAmount = 0;
        this.paymentItems = [];
        this.refundItems = [];
        this.messages = [];

        this.parseFormSubmission();

        // console.log(this.fields);
        // console.log(_evt);
    }

    updateBookingForm() {
        if (this.messages?.length > 0) {
            this.renderMessages(this.messages);
        }

        const data = {
            tour_package_form_id: this.formData.form_id,
            tour_booking_id: this.bookingId,
            form_submission: this.formData.submission
        }

        const csrf = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            type: "POST",
            url: `${this.updateUrl}`,
            data: data,
            headers: { 'X-CSRF-TOKEN': csrf },
            success: this.onFormSubmitOk.bind(this),
            dataType: 'json'
        }).done((response) => {
            console.log(response);
            location.reload();
        }).fail((err) => {
            swal(err.responseJSON.message, '', 'error');
        }).always(this.hideLoading);
    }

    onFormSubmitOk(data) {
        $("#booking-addons").show();
        $("#booking-addons-form-view").html("");

        this.fetchAddonsOk(data);
    }

    parseFormSubmission() {
        this.renderMessages(this.messages, true);

        /** 
         * check if the user is removing an option that has a fee.
         * this is important if you want to notifiy them of refunds
         */
        for (let key in this.fields) {
            let f = this.fields[key];
            // if a field that contains a price and is not in data set we have to raise a state change
            if (this.toBool(f.priceInfo.enabled) === true) {
                // if a field that contains a price is already in the data set
                if (this.formData.submission.filter((s) => s.id === f.id).length === 0) {
                    console.log(" remove fee ");
                    console.log(f);
                    this.refundItems.push({ id: f.id, bookingAddonId: f.booking_addon_id, fieldId: f.id, priceInfo: f.priceInfo })
                    this.refundAmount += this.toBool(f.priceInfo.enabled) === true ? (Number(parseFloat(f.priceInfo.price)) + Number(parseFloat(f.priceInfo.fee))) : 0;
                    this.messages.push({ id: f.id, priceInfo: f.priceInfo, msg: `You removed ${f.name} which has a amount of: ${this.renderPrice(f.priceInfo)}`, type: 'refund' });
                }
            }
        }

        /**
         * check if a new fee item was added 
         * this is imporant becuase we need to prompt the user to request payment for the fee. 
         */
        this.formData.submission.forEach((f) => {
            if (this.toBool(f.priceInfo.enabled) == true) {
                if (this.fields.filter((s) => s.id === f.id).length === 0) {
                    console.log(" add fee ");
                    console.log(f);
                    f.priceInfo.fee = (this.toBool(f.priceInfo.enabled) === true && (this.paymentMethod == 'Card' || this.paymentMethod == 'Pay Later' || (this.paymentMethod == 'Cash' && isCashFees == 1))) ? this.roundout(Number(parseFloat(f.priceInfo.price)) * natServiceCommission / 100) : 0;
                    this.paymentItems.push({ id: f.id, name: f.name, description: f.description, priceInfo: f.priceInfo })
                    this.paymentAmount += this.toBool(f.priceInfo.enabled) === true ? (Number(parseFloat(f.priceInfo.price)) + Number(f.priceInfo.fee)) : 0;
                    this.feesAmount += this.toBool(f.priceInfo.enabled) === true ? Number(f.priceInfo.fee) : 0;
                    this.messages.push({ id: f.id, priceInfo: f.priceInfo, msg: `You added ${f.name} which has a amount of: ${this.renderPrice(f.priceInfo)}`, type: 'charge' });
                }
            }
        });

        this.editBookingAddons();
    }

    editBookingAddons() {
        if (this.paymentMethod == 'Pay Later') {
            this.updateBookingForm();
        } else {
            if (this.paymentAmount > 0 && this.refundAmount > 0) {
                // if (this.paymentAmount > this.refundAmount) {
                //     this.openAddonPaymentPopup();
                // } else {
                //     let msg = "This action adjusts for new addons and refunds the remaining amount."
                //     this.openAddonRefundPopup(msg);
                // }
                swal({
                    icon: "info",
                    title: "Addon Adjustment Notice",
                    text: "Please note: You can either add or remove an addon at a time."
                });
            } else if (this.paymentAmount > 0 && this.refundAmount == 0) {
                this.openAddonPaymentPopup();
                backendCardReaderInfo.getTotal(this.paymentAmount);
                backendCardReaderInfo.getFees(this.feesAmount);
            } else if (this.paymentAmount == 0 && this.refundAmount > 0) {
                let msg = "This action eliminates the add-ons and issues a refund for paid reservations."
                this.openAddonRefundPopup(msg);
            } else {
                this.updateBookingForm();
            }
        }
    }

    openAddonRefundPopup(msg) {
        if ((this.refundItems.length || this.paymentItems.length) && this.bookingId && this.packageId) {
            swal({
                title: 'Addon Adjustment Notice',
                text: msg,
                icon: "info",
                dangerMode: true,
                buttons: true,
                buttons: {
                    no: {
                        text: 'Cancel',
                        value: false,
                    },
                    yes: {
                        text: 'Remove',
                        value: true,
                        className: 'btn-danger',
                    }
                }
            }).then((result) => {
                if (result == true) {
                    $("#addons-partial-refund-div").attr("hidden", true);
                    $(".refund_type").prop('checked', false);
                    $(".clearable").val('');

                    $("#modal_addon_refund").modal('show');
                }
            });
        }
    }

    makeAddonsRefund() {

        let description = $('#addons-refund-notes').val();

        let bothItems = {
            bookingId: this.bookingId,
            packageId: this.packageId,
            paymentMethod: this.paymentMethod,
            refundType: this.refundType,
            refundItems: this.refundItems,
            paymentItems: this.paymentItems,
            description: description
        };

        if (bothItems.refundType == "partial_refund") {
            let partialRefundValue = parseFloat($('#addons-partial-refund-value').val());
            bothItems.partialRefundValue = partialRefundValue;
        }
        var field_type = 'Refund Addons_' + bothItems.refundType + bothItems.refundItems[0].bookingAddonId + '_' + bothItems.refundItems[0].fieldId + '_' + bothItems.refundItems[0].id;
        var inArray = $.inArray(field_type, fieldRequestPermissions);
        if (inArray !== -1) {
            refundAddonsPermission = "Yes";
        }
        if (refundAddonsPermission == 'No') {
            bothItems = {
                bookingId: this.bookingId,
                packageId: this.packageId,
                paymentMethod: this.paymentMethod,
                refundType: this.refundType,
                refundItems: this.refundItems,
                paymentItems: this.paymentItems,
                description: description,
                field_type: field_type,
                request_type: 'Refund Addons'
            };
            requestType(bothItems, '#modal_addon_refund');
            return false;
        }
        if (refundAddonsPermission == 'Yes') {
            const csrf = $('meta[name="csrf-token"]').attr('content');
            this.showLoading();
            $.ajax({
                type: "POST",
                url: this.upsertAddonsUrl,
                data: bothItems,
                headers: { 'X-CSRF-TOKEN': csrf },
                dataType: 'json'
            }).done((response) => {
                console.log(response);

                $("#modal_addon_refund").modal('hide');

                swal({
                    icon: 'success',
                    title: 'Success',
                    text: 'Add-ons removed!'
                });

                this.updateBookingForm(this.formData);
            }).fail((err) => {
                // err.responseJSON.message
                this.messages = [];
                swal(err.responseJSON.errors.common[0], '', 'error');
            }).always(this.hideLoading);

        }
    }

    getRefundType(refundType) {
        this.refundType = refundType
        if (this.refundType == 'partial_refund') {
            $("#addons-partial-refund-div").attr("hidden", false);
            $("#addons-partial-refund-value").attr("required", true);
        } else {
            $("#addons-partial-refund-div").attr("hidden", true);
            $("#addons-partial-refund-value").attr("required", false);
        }
    }

    openAddonPaymentPopup() {
        let payableAmount = `${currency(this.paymentAmount).format()}`;
        $(".addons-payment-form .amount-text, .addons-payment-form .total-price").text(payableAmount);
        $("#modal_addon_payment").modal("show");
        if (this.paymentMethod == 'Card') {
            $(".requirable").attr("required", true);
        } else {
            $(".requirable").attr("required", false);
        }
    }

    makeAddonsPayment(paymentIntentId = "") {
        // let changeAmount = 0;
        let cashPaidAmount = $("#modal_addon_payment #cash-paid-amount").val();

        if (this.paymentMethod == 'Cash') {
            if (cashPaidAmount?.length > 0) {
                if (parseFloat(cashPaidAmount) < this.paymentAmount) {
                    swal('Sorry!', 'Please add a paid amount greater than the final addons amount.', 'warning');
                    return false;
                }

                if (cashPaidAmount > this.paymentAmount) {
                    this.changeAmount = cashPaidAmount - this.paymentAmount;
                }
            }
        }

        this.showLoading();
        let bothItems = {
            bookingId: this.bookingId,
            packageId: this.packageId,
            formId: this.formData.form_id,
            paymentMethod: this.paymentMethod,
            refundItems: this.refundItems,
            paymentItems: this.paymentItems,
            paymentIntentId: paymentIntentId
        };

        var form = (document.getElementById("modal-addons-payment-form"));
        var feature = 'add_ons';
        $(form).append(`<input type="hidden" name="feature" value="add_ons">`);
        $(form).append(`<input type="hidden" name="bookingId" value='${bookingId}'>`);
        var paymentItemsJson = JSON.stringify(bothItems.paymentItems);
        $(form).append(`<input type="hidden" name="paymentItems" value='${paymentItemsJson}'>`);
        $(form).append(`<input type="hidden" name="packageId" value='${bothItems.packageId}'>`);
        $(form).append(`<input type="hidden" name="formId" value='${bothItems.formId}'>`);
        $(form).append(`<input type="hidden" name="paymentMethod" value='${bothItems.paymentMethod}'>`);
        $(form).append(`<input type="hidden" name="refundItems" value='${bothItems.refundItems}'>`);
        $(form).append(`<input type="hidden" name="paymentIntentId" value='${bothItems.paymentIntentId}'>`);

        if (this.paymentMethod == 'Card') {
            if (!paymentIntentId) {
                initTourBookingStripePayment.initializeStripe(form, feature);
                return false;
            }
        }
        this.submitAddOns(form);

    }

    submitAddOns(form = '') {
        const csrf = $('meta[name="csrf-token"]').attr('content');
        var data = $(form).serializeArray();
        $.ajax({
            type: "POST",
            url: this.upsertAddonsUrl,
            data: data,
            headers: { 'X-CSRF-TOKEN': csrf },
            dataType: 'json'
        }).done((response) => {

            $("#modal_addon_payment").modal('hide');
            let message = '';
            if (this.paymentMethod == 'Cash') {
                if (this.changeAmount > 0) {
                    message = "Add-ons added! Please give change of " + currency(this.changeAmount).format() + "";
                } else {
                    message = 'Add-ons added!';
                }
            } else {
                message = 'Add-ons added!';
            }
            swal({
                icon: 'success',
                title: 'Success',
                text: message
            });

            this.updateBookingForm(this.formData);
        }).fail((err) => {
            // err.responseJSON.message
            this.messages = [];
            swal(err.responseJSON.errors.common[0], '', 'error');
        }).always(hideLoading);
    }

    renderFormEditAction() {
        $("#booking-addons-wrap").addClass('d-none');
        $("#cc_section").removeClass('mt-8');
        $(`#${this.targetDiv}`).html("");
        $.get(this.fetchFormUrl).done((resp) => {
            if (resp.form !== null && resp.form.status === 'active') {
                // Add "Read Only" indicator for old add-ons
                const titleText = (typeof hasOldAddons !== 'undefined' && hasOldAddons) 
                    ? "Booking Add-Ons (Legacy - Read Only):" 
                    : "Booking Add-Ons:";
                $("#booking-addons-title").html(titleText);
                this.formId = resp.form.id;
                const html = this.renderTable('custom-field-addons-display', 
                    "<tr><td class='td-custom-css border-0' colspan='100%'>No Addons</td></tr>", 
                    "", 
                    this.renderFooter()
                );

                $("#booking-addons-wrap").removeClass('d-none');
                $("#cc_section").addClass('mt-8');
                $(`#${this.targetDiv}`).html(html);
            }
        });


    }

    /**
     * display messages above add-on options
     */
    renderMessages(messages, reset = false) {
        if (reset) { $("#booking-addons-msg").html(" "); return; }
        let msg = `<div class="alert alert-info"> 
                ${messages.map((m) => { return `<div id="${m.id}">${m.msg}</div>` }).join("\n")}
            </div>`;
        $("#booking-addons-msg").html(msg);
    }
    /**
     * display the add on total in the grand total table on the info page. 
     */
    renderGrandTotal() {
        if (this.addonFees > 0) {
            pricedAddonAdded = 1;
            $("#addonGrandTotalDisplay").html(`${currency(this.addonFees).format()}`)
            $("#addonGrandTotalWrap").show();
        }
    }

    /**
     * display an html table
     * @param {*} id 
     * @param {*} body 
     * @param {*} header 
     * @param {*} footer 
     * @returns 
     */
    renderTable(id, body="", header="", footer=""){
        return `<table id="${id}" class='table table-sm table-stripe table-hover text-left w-full'>
                    <thead>${header}</thead>
                    <tbody>${body}</tbody>
                    <tfoot>${footer}</tfoot>
                </table>`;
    }

    renderHeader(){
        return `<tr>
                    <th class='text-xs font-medium text-[#374151] px-4 py-3'>Name</th>
                    <th class='text-xs font-medium text-[#374151] px-4 py-3'>Value</th>
                    <th class='text-xs font-medium text-[#374151] px-4 py-3'>Fee</th>
                </tr>`;
    }

    renderFooter() {
        // Don't show edit button for old add-ons (read-only mode)
        // Old add-ons are identified by the presence of hasOldAddons flag
        if (typeof hasOldAddons !== 'undefined' && hasOldAddons) {
            return ''; // No footer for read-only old add-ons
        }
        
        if (managerRequestEnableRefundAddons == "Yes") {
            return `
                <tr class="border-t border-[#E2E8F0]"> 
                    <td class="px-4 py-3 text-right" colspan="100%">
                        <button class="edit-custom-addons-form btn btn-sm btn-primary ml-auto btn-secondary-small">
                            Edit
                        </button>
                    </td>
                </tr>`;
        }
    }

    renderField(field) {
        return `
            <tr class="border-t border-[#E2E8F0] hover:bg-[#F0FDF4]">
                <td class="text-sm text-[#4B5563] px-4 py-3">${this.renderTitle(field.name)}</td>
                <td class="text-sm text-[#4B5563] px-4 py-3">${this.renderValue(field)}</td>
                <td class="text-sm text-[#4B5563] px-4 py-3">${this.renderPrice(field.priceInfo)}</td>
            </tr>
        `;
    }

    renderTitle(s) {
        return s.toString().replace("_", " ").replace("-", " ").replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
    }

    renderValue(field) {
        let val = field.value;
        if (typeof val == "boolean") {
            return (val) ? "Yes" : "No";
        }
        if (val === 'true' || val === 'false') {
            return (val === 'true') ? "Yes" : "No";
        }
        if (val === true || val === false) {
            return (val) ? "Yes" : "No";
        }
        return val;
    }

    renderPrice(priceInfo) {
        if (this.toBool(priceInfo.enabled) === true) {
            let price = 0
            if (priceInfo?.price) {
                price = priceInfo?.price;
            }

            let fee = 0;
            if (priceInfo?.fee && (paymentMethod == 'Card' || (isCashFees == 1 && paymentMethod == 'Cash'))) {
                fee = priceInfo?.fee;
            }

            return `${currency(parseFloat(price) + parseFloat(fee)).format()}`;
        }

        return "-";
    }

    renderLoading() {
        return `<div class="spinner-border" role="status">  <span class="visually-hidden">Loading...</span> </div>`;
    }

    onError(data) {
        //remove loading...
        $("#booking-addons-wrap").addClass('d-none');
        $("#cc_section").removeClass('mt-8');
        $(`#${this.targetDiv}`).html(" ");
        console.log(data);
    }

    //string val to boolean 
    toBool(val) {
        console.log(`VALUE: ${val}`);
        return (val === true || val === "true" || val === 1 || val.toString().toLowerCase() === 'y') ? true : false
    }

    roundout(amount, places = 2) {
        if (places < 0) places = 0;
        let factor = Math.pow(10, places);
        return Math.round(amount * factor) / factor;
    }

    showLoading() {
        $('#loading-container').css('display', 'block');
    }

    hideLoading() {
        $('#loading-container').css('display', 'none');
    }
}

var pricedAddonAdded = 0;

const initCustomFormDisplay = new CustomFormDisplay({ admin: userType, packageId: packageId, bookingId: bookingId });