/** 
 * Custom Field Add-ons by package id
 * this is to display the custom add on form on the tour-booking-add.blade.php
 **/
class TourBookingAddCheckoutAddons {

    constructor(props) {

        this.selectedCount = "";
        this.formData = null;
        this.paymentMethod = $(".book-pay-method").filter(":checked").val();
        this.bookingServiceCommission = 0;
        this.customFields = "";
        this.mountedFormData = "";
        this.currentAddonsPrice = 0;
        this.currentAddonsFees = 0;
        this.currentAddonsTotal = 0;

        // if the user submits the custom addons form 
        const onCustomFormSubmit = (_evt, formData) => {
            this.formData = formData;

            if (this.formData) {
                /**
                 * check if a new fee item was added 
                 * this is imporant becuase we need to prompt the user to request payment for the fee. 
                 */
                this.updateFormData();
            }
        };

        //if we have custom add ons for this pkg 
        const onCustomFormMount = (data) => {
            this.mountedFormData = data;
            if (isAddonsEnabled == true && data.form !== null && data.form.status === 'active') {
                let count = `<span id='booking-addons-count'>${this.selectedCount}</span>`
                let addonValidationText = `
                    <i class="fa fa-exclamation-circle addon-required" style="color: red; font-size: 18px; position: absolute; transform: translate(25%, 56%); display: none;" title="Addons Required"></i>
                    <i class="fa fa-check-circle addon-required-fulfilled" style="color: #0ded0d; font-size: 18px; position: absolute; transform: translate(25%, 56%); display: none;"></i>
                `;

                let btn = `${addonValidationText}<button type='button' id='btn-booking-addons' class='addons-view-form btn-add-package btn-primary-regular'><i class="fa fa-cart-plus mr-2"></i> ${count} Add-ons</button>`;
                $('#booking-addons-form-action-btn').html(btn);

                this.checkRequiredAddons();
            }
        }

        //init the custom form for the pkg 
        const initCustomForm = (evt) => {
            let packageId = evt.target.value;
            if (packageId === "") {
                this.resetBookingForm();
                return false;
            }

            let admin = "";
            if (props.admin === 'Admin') {
                admin = "/admin/tour-operator";
            } else if (props.admin === 'Tour Operator') {
                admin = "/tour-operator";
            } else if (props.admin === 'Affiliate') {
                admin = "/affiliate";
            } else {
                admin = "";
            }

            this.setFromData(packageId);
            new CustomFormReader({
                admin: props.admin,
                dom: "booking-addons-modal-body",
                path: `${admin}/package/get-package-form/${packageId}`,
                form: this.customFields,
                onMount: onCustomFormMount,
                onSubmit: onCustomFormSubmit,
                submitAction: "Save Addons",
                onCancel: (_evt) => {
                    this.restoreAddonForm();
                    $("#booking-addons").modal("hide");
                }
            });
        }

        if ($('#tour_package').val() !== '') {
            const customEvent = $.Event('change', {
                target: $('#tour_package')[0]
            });

            initCustomForm(customEvent);
        }

        // add event listiner to tour pkg drop down
        $(document).off('change', '#tour_package').on('change', '#tour_package', (evt) => {
            let packageId = evt.target.value
            if (packageId === "") {
                this.resetBookingForm();
                return false;
            }

            let admin = "";
            if (props.admin === 'Admin') {
                admin = "/admin/tour-operator";
            } else if (props.admin === 'Tour Operator') {
                admin = "/tour-operator";
            } else if (props.admin === 'Affiliate') {
                admin = "/affiliate";
            } else {
                admin = "";
            }

            this.setFromData(packageId);
            new CustomFormReader({
                admin: props.admin,
                dom: "booking-addons-modal-body",
                path: `${admin}/package/get-package-form/${packageId}`,
                form: this.customFields,
                onMount: onCustomFormMount,
                onSubmit: onCustomFormSubmit,
                submitAction: "Save Addons",
                onCancel: (_evt) => {
                    this.restoreAddonForm();
                    $("#booking-addons").modal("hide");
                }
            });
        });

        //add event listiner to display booking add on forms
        $(document).off('click', '.addons-view-form').on('click', '.addons-view-form', (_evt) => {
            // $("#booking-addons").modal("show");
            const modalElement = document.getElementById('booking-addons');
            const existingInstance = bootstrap.Modal.getInstance(modalElement);
            if (existingInstance) {
                existingInstance.dispose();
            }

            const modalInstance = new bootstrap.Modal(modalElement, {
                backdrop: 'static',
                keyboard: false
            });

            modalInstance.show();
        });

        $(document).on('change', '.book-pay-method', (evt) => {
            this.paymentMethod = evt.target.value;
            onCustomFormSubmit(evt, this.formData);
        });

        $(document).on('click', '#booking-addons .btn-close', (e) => {
            e.preventDefault();
            this.restoreAddonForm();
            const modalInstance = bootstrap.Modal.getInstance(document.getElementById('booking-addons'));
            if (modalInstance) modalInstance.hide();
        });
    }

    setFromData(packageId) {
        // To reset the previous form
        this.formData = null;
        this.currentAddonsPrice = 0;
        this.currentAddonsFees = 0;
        this.currentAddonsTotal = 0;

        $('#booking-addons-form-view').html(bootstrapModalHtml({ id: 'booking-addons', size: "xl", title: 'Booking Add-ons', content: "Loading", close: "  " }));
        $('#booking-addons-form-action-btn').html("");

        if (packageId === "") {
            $("#custom_fields").val("");
            return false;
        }

        this.customFields = "";
        this.selectedCount = "";

        try {
            const pkgCustomAddData = $("#custom_fields").val().trim();
            if (pkgCustomAddData != "") {
                this.customFields = JSON.parse(document.getElementById('custom_fields').value);
                this.selectedCount = `${this.customFields.fields.length}`;
            }
        } catch (err) {
            console.log(err);
        }
    }

    setServiceCommissionPercentage(val) {
        this.bookingServiceCommission = val;
        if (this.formData) {
            this.updateFormData();
        }
    }

    updateFormData() {
        this.processSubmission();

        if (this.formData.submission.length > 0) {
            const data = { form_id: this.formData.form_id, fields: this.formData.submission, package_id: this.formData.package_id };
            $("#custom_fields").val(JSON.stringify(data));
        }

        this.checkRequiredAddons();

        // call the update rates in the main table.
        if (isGroupRateEnabled == 1) {
            updateGroupRatesTable();
        } else {
            updateRatesTable();
        }

        this.hideShowSplitInputUI();

        $("#booking-addons").modal("hide");
    }

    hideShowSplitInputUI() {

        if (this.currentAddonsTotal > 0) {
            $("#dividedBy").prop("selectedIndex", 0);
            $("#split-payment").prop('checked', false);
            $(".split-check").text("$0.00");
            $("#split_value_field").val("").prop('required', false).hide();
            $("#split-payment-div").hide();

            // Split payment was showing the "Total payment" row (#psplit) with the now-stale
            // per-split amount — swap back to the full-total row for the active payment method,
            // same as PaymentHandler.resetSplitValues() does when split is reset elsewhere.
            $(".split-set").text("$0.00");
            $("#psplit").hide();
            if (this.paymentMethod === 'Card' || (this.paymentMethod === 'Cash' && cashFees == 1)) {
                $("#pcard").show();
            } else if (this.paymentMethod === 'Cash' || this.paymentMethod === 'Pay Later') {
                $("#pcash").show();
            }
        } else {
            $("#split-payment-div").show();
            $("#split_value_field").show();
        }
    }

    processSubmission() {
        this.currentAddonsPrice = 0;
        this.currentAddonsFees = 0;
        this.currentAddonsTotal = 0;

        let packageFormFields = [];

        // Get add-on fields from cart package
        const cartPackageDetails = getCartPackageDetails(this.formData.package_id);
        if (cartPackageDetails?.custom_addons_field) {
            try {
                const addonForm = typeof cartPackageDetails.custom_addons_field === 'string'
                    ? JSON.parse(cartPackageDetails.custom_addons_field)
                    : cartPackageDetails.custom_addons_field;

                packageFormFields = Array.isArray(addonForm?.fields) ? addonForm.fields : [];
            } catch (e) {
                console.error("Invalid custom_addons_field JSON:", e);
            }
        }

        const submissionArray = Array.isArray(this.formData.submission) ? this.formData.submission : [];
        const submissionIds = new Set(submissionArray.map(sub => sub.id));

        const arraysAreEqual = JSON.stringify(packageFormFields) === JSON.stringify(submissionArray);

        if (!arraysAreEqual) {
            // Remove unsubmitted addons
            for (const field of packageFormFields) {
                const isEnabled = this.toBool(field.priceInfo?.enabled);
                const isSubmitted = submissionIds.has(field.id);

                if (!isEnabled || isSubmitted) continue;

                const price = Number(field.priceInfo?.price) || 0;
                const fee = this.roundout(price * this.bookingServiceCommission / 100);

                totalCartAddonsPrice = Math.max(0, totalCartAddonsPrice - price);
                totalCartAddonsFees = Math.max(0, totalCartAddonsFees - fee);
            }

            // Add submitted addons
            for (const sub of submissionArray) {
                const isEnabled = this.toBool(sub.priceInfo?.enabled);
                if (!isEnabled) continue;

                const price = Number(sub.priceInfo?.price) || 0;
                const fee = this.roundout(price * this.bookingServiceCommission / 100);

                sub.priceInfo.price = price;
                sub.priceInfo.fee = fee;

                this.currentAddonsPrice += price;
                this.currentAddonsFees += fee;
                this.currentAddonsTotal += price + fee;
            }
        }

        this.currentAddonsFees = this.roundout(this.currentAddonsFees, 2);

        // Reset UI values and update
        $("#custom_fields").val("");
        this.displayBookingsCount();
    }


    //string val to boolean 
    toBool(val) {
        return (val === true || val === "true" || val === 1 || val.toString().toLowerCase() === 'y') ? true : false
    }

    roundout(amount, places = 2) {
        if (places < 0) places = 0;
        let factor = Math.pow(10, places);
        return Math.round(amount * factor) / factor;
    }

    getPrice() {
        return this.currentAddonsPrice;
    }

    getFees() {
        return this.currentAddonsFees
    }

    displayBookingsCount() {
        try {
            this.selectedCount = "";
            if (this.formData) {
                if (this.formData.hasOwnProperty("submission")) {
                    if (this.formData.submission) {
                        this.selectedCount = this.formData.submission.length;
                    }
                }
            }

            if (this.selectedCount === 0) {
                this.selectedCount = "";
            }

            $("#booking-addons-count").html(this.selectedCount);
        } catch (err) {
            $("#booking-addons-count").html("");
        }

    }

    resetBookingForm() {
        $("#booking-addons-count").html("");
        $("#btn-booking-addons").remove();
        $("#custom_fields").val("");
    }

    requiredAddonsValidation() {
        let packageForm = [];
        let isValid = true;
        let errorMessages = [];
        const $required = $(".addon-required");
        const $fulfilled = $(".addon-required-fulfilled");

        // Check if custom_fields input has value
        const pkgCustomAddData = $("#custom_fields").val()?.trim();
        if (pkgCustomAddData) {
            try {
                const form = JSON.parse(pkgCustomAddData);
                packageForm = form.fields || [];
            } catch (e) {
                console.error("Invalid JSON in custom_fields input", e);
                return true; // or false depending on whether you want to halt here
            }
        }
        // If no value in custom_fields input, use mountedFormData
        else if (this.mountedFormData.form && this.mountedFormData.form.status === 'active') {
            if (this.mountedFormData.form.hasOwnProperty('fields')) {
                packageForm = this.mountedFormData.form.fields || [];
            } else if (this.mountedFormData.form.hasOwnProperty('form_fields')) {
                try {
                    let parsedFormFields = this.mountedFormData.form.form_fields;
                    if (typeof parsedFormFields === "string") {
                        parsedFormFields = JSON.parse(parsedFormFields);
                    }
                    packageForm = parsedFormFields || [];
                } catch (e) {
                    console.error("Invalid JSON in mountedFormData.form.form_fields", e);
                    return true;
                }
            }
        }

        // Defensive check: if packageForm is not an array, skip validation
        if (!Array.isArray(packageForm)) {
            console.warn("packageForm is not an array. Skipping validation.");
            return true;
        }

        // Validate required fields
        packageForm.forEach(field => {
            if (field.required !== "true") return; // skip if not required

            const id = field.id;
            const name = field.name;
            const type = field.type;

            switch (type) {
                case 'checkbox':
                    if (!$(`#${id}`).is(':checked')) {
                        isValid = false;
                        errorMessages.push(`${name} is required.`);
                    }
                    break;

                case 'text':
                case 'textarea':
                case 'number':
                case 'select':
                    if (!$(`#${id}`).val()) {
                        isValid = false;
                        errorMessages.push(`${name} is required.`);
                    }
                    break;

                case 'radio':
                    const selectedRadio = $(`input[name="${name}"]:checked`);
                    if (selectedRadio.length === 0) {
                        isValid = false;
                        errorMessages.push(`${name} is required.`);
                    }
                    break;

                default:
                    console.warn(`Unsupported field type: ${type}`);
            }
        });

        // If any validation errors, show alert and return false
        if (!isValid) {
            swal({
                title: 'Required Addons Missing',
                text: errorMessages.join("\n"),
                icon: 'warning'
            });
            $required.show();
            $fulfilled.hide();
            return false;
        }

        return true;
    }

    checkRequiredAddons() {
        const $required = $(".addon-required");
        const $fulfilled = $(".addon-required-fulfilled");
        const $form = $(".addons-view-form");

        const applyPadding = () => {
            $form.css({ padding: "8px 15px 8px 25px" }); // no !important
        };

        const showRequired = () => {
            $required.show();
            $fulfilled.hide();
            applyPadding();
        };

        const showFulfilled = () => {
            $required.hide();
            $fulfilled.show();
            applyPadding();
        };

        const hideBoth = () => {
            $required.hide();
            $fulfilled.hide();
        };

        const raw = $("#custom_fields").val()?.trim();

        if (raw) {
            const fields = JSON.parse(raw).fields;
            for (const field of fields) {
                if (field.required === "true") {
                    return field.value === "false" ? showRequired() : showFulfilled();
                }
            }
            hideBoth(); // in case no required field
        } else if (this.mountedFormData?.form) {
            const form = this.mountedFormData.form;

            if (form?.form_fields) {
                let fields = form.form_fields;

                if (typeof fields === 'string') {
                    try {
                        fields = JSON.parse(fields);
                    } catch (e) {
                        console.error('Invalid JSON in form_fields', e);
                        return;
                    }
                }

                for (const field of fields) {
                    if (field.required === "true") return showRequired();
                }
            }
            hideBoth(); // in case no required field
        }
    }

    restoreAddonForm() {
        const rawData = $("#custom_fields").val()?.trim();
        if (!rawData) return;

        let packageForm = [];
        const addonIds = [];

        // Extract package form fields from mountedFormData
        const mountedForm = this.mountedFormData?.form;
        if (mountedForm) {
            try {
                if (Array.isArray(mountedForm.fields)) {
                    packageForm = mountedForm.fields;
                } else if (mountedForm.form_fields) {
                    const parsed = typeof mountedForm.form_fields === "string"
                        ? JSON.parse(mountedForm.form_fields)
                        : mountedForm.form_fields;
                    if (Array.isArray(parsed)) {
                        packageForm = parsed;
                    }
                }
            } catch (e) {
                console.error("Invalid JSON in mountedFormData.form.form_fields", e);
                return;
            }
        }

        // Ensure packageForm is a valid array
        if (!Array.isArray(packageForm)) {
            console.warn("packageForm is not an array. Skipping restoration.");
            return;
        }

        // Build set of addon IDs for fast lookup
        packageForm.forEach(field => addonIds.push(field.id));
        const addonIdSet = new Set(addonIds);

        try {
            const parsed = JSON.parse(rawData);
            const rawFields = Array.isArray(parsed.fields) ? parsed.fields : [];

            // Filter fields that are part of addon IDs
            const matchingFields = rawFields.filter(field => addonIdSet.has(field.id));
            if (matchingFields.length === 0) {
                console.warn("No matching addon IDs found in rawData.");
                return;
            }

            // Restore form field values
            for (const field of matchingFields) {
                const { id, type, value = '', required } = field;
                if (!id) continue;

                const $input = $("#" + id);
                if ($input.length === 0) continue;

                switch (type) {
                    case 'checkbox':
                    case 'radio':
                        $input.prop('checked', true);
                        break;

                    case 'select':
                        $input.val(value);
                        break;

                    default: // text, textarea, number, etc.
                        $input.val(value);
                        break;
                }
            }

        } catch (e) {
            console.error("Invalid JSON in custom_fields input", e);
            return;
        }
    }

}

const initTourBookingAddCheckoutAddons = new TourBookingAddCheckoutAddons({ admin: userType });