/** 
 * Gratuity class for tour booking. 
 * this is to display the gratuity on the tour-booking-add.blade.php
 **/
class TourBookingGratuity {

    constructor(props) {

        this.paymentMethod = $(".book-pay-method").filter(":checked").val();
        this.bookingServiceCommission = 0;
        this.gratuitySetting = props.gratuitySetting || {};
        this.gratuityType = '';
        this.gratuityMandatory = false;
        this.tipPercentage = 0;
        this.tipAmount = 0;
        this.customTipAmount = 0;
        this.tipFees = 0;
        this.tipTotal = 0;
        this.tipAmountTotal = 0;
        this.tipFeeTotal = 0;
        this.message = '';
        this.bindEvents();
        this.setupEventListners();
    }
    bindEvents() {
        $(document).off('click', '#addGratuityBtn').on('click', '#addGratuityBtn', (evt) => {
            this.renderGratuityModal();
        });
        $(document).off('click', '#sendTipBtn').on('click', '#sendTipBtn', (evt) => {
            this.handleSendTip();
        });
        $(document).on('input change', '.custom-amount', (e) => this.renderCustomTipAmount($(e.target)));
    }
    setupEventListners() {
        $(document).off('click', '.amount-btn').on('click', '.amount-btn', (evt) => {
            this.selectAmount(evt.target);
        });

        const slider = $("#tip-percentage-slider");

        slider.slider({
            range: false,
            max: 50,
            step: 1,
            create: function () {
                $("#percentage-value").text(slider.slider("value") + "%");
                $('#tipPercentage').val(slider.slider("value"));
            },
            slide: function (event, ui) {
                $("#percentage-value").text(ui.value + "%");
                $('#tipPercentage').val(ui.value);
                document.querySelectorAll('.amount-btn').forEach(btn => {
                    if (ui.value == btn.dataset.amount) {
                        btn.classList.add('active');
                    } else {
                        btn.classList.remove('active');
                    }
                });
                var tipPercentage = parseInt(ui.value);
                initTourBookingGratuity.tipPercentage = tipPercentage;
                var tipAmount = initTourBookingGratuity.roundout((totalBeforeDiscountAndServiceCommissionPackage * tipPercentage) / 100, 2);
                initTourBookingGratuity.updateSendButton(tipAmount);
            }
        });
    }
    selectAmount(selectedButton) {
        // Remove active class from all buttons
        document.querySelectorAll('.amount-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Add active class to selected button
        selectedButton.classList.add('active');

        // Set selected amount
        if (this.gratuityType === 'fixed_percentage') {
            var tipPercentage = parseInt(selectedButton.dataset.amount);
            $("#tip-percentage-slider").slider("value", tipPercentage);
            $("#percentage-value").text(tipPercentage + "%");
            $('#tipPercentage').val(tipPercentage);
            this.tipPercentage = tipPercentage;
            tipAmount = this.roundout((totalBeforeDiscountAndServiceCommissionPackage * tipPercentage) / 100, 2);
        } else {
            var tipAmount = parseInt(selectedButton.dataset.amount);
        }
        // Clear custom amount input
        document.querySelector('.custom-amount').value = '';

        // Update send button
        this.updateSendButton(tipAmount);
    }
    calculateTipFeesAmount(serviceCommissionPercentage) {
        var tipFees = 0;
        tipFees = (this.tipAmount * serviceCommissionPercentage / 100);
        this.tipFees = tipFees = this.roundout(tipFees, 2);
        return tipFees;
    }
    setServiceCommissionPercentage(val) {
        this.bookingServiceCommission = val;
    }
    resetTipData() {
        this.tipAmount = 0;
        this.tipPercentage = 0;
        this.customTipAmount = 0;
        this.tipFees = 0;
        this.message = '';
        this.gratuitySetting = {};
        this.gratuityType = '';
        this.tipFeeTotal = 0;
        this.tipAmountTotal = 0;
        $('#tip_data').val('');
    }
    getTipAmount() {
        return this.tipAmount;
    }
    setTipAmount(tipAmount) {
        this.tipAmount = tipAmount;
    }
    getTipPercentage() {
        return this.tipPercentage;
    }
    setTipPercentage(tipPercentage) {
        this.tipPercentage = tipPercentage;
    }
    setTipAmount(tipPercentage) {
        this.tipPercentage = tipPercentage;
    }
    getTipFeesAmount() {
        return this.tipFees;
    }
    setTipFeesAmount(tipFees) {
        this.tipFees = tipFees;
    }
    getTotalTipAmount() {
        return this.tipAmount; //;
    }
    setCustomTipAmount(customTipAmount) {
        this.customTipAmount = customTipAmount;
    }
    getTipMessage() {
        return this.message || '';
    }
    getTipFees() {
        return this.tipFeeTotal;
    }
    getTipAmountTotal() {
        return this.tipAmountTotal;
    }
    getTipAmountCart() {
        var tipAmount = 0;
        if (cartPackages.length > 0) {
            for (var a = 0; a < cartPackages.length; a++) {
                tipAmount += cartPackages[a].tip_amount || 0;
            }
        }
        return tipAmount;
    }
    getTipAmountCartPackage() {
        var tourPackageId = $('#tour_package_id').val();
        var cartPackageDetails = getCartPackageDetails(tourPackageId);
        if (cartPackages.length > 0 && cartPackageDetails != "") {
            return cartPackageDetails.tip_amount;
        }
    }
    updateTipAmounts() {
        if (this.gratuityType === 'fixed_percentage' && ($('#customAmount').val() == '' || $('#customAmount').val() == 0)) {
            this.tipAmount = this.roundout(((totalBeforeDiscountAndServiceCommissionPackage * this.tipPercentage) / 100), 2);
        } else if ($('#customAmount').val() && $('#customAmount').val() > 0) {
            this.tipAmount = parseFloat($('#customAmount').val());
            this.customTipAmount = this.tipAmount;
            this.tipPercentage = 0; // Reset percentage if custom amount is used
        }
    }
    setTipFormData() {
        let tipSetting = this.gratuitySetting;
        let tipAmount = this.tipAmount;
        let tipPercentage = this.tipPercentage;
        let customTipAmount = this.customTipAmount || 0;
        let tipMessage = this.message || '';

        if (tipAmount > 0) {
            const tipData = {
                tip_setting_id: tipSetting.gratuity_setting_id,
                tip_amount: tipAmount,
                tip_type: 'pre',
                custom_tip_amount: customTipAmount,
                tip_percentage: tipPercentage,
                tip_fees: this.tipFees,
                tip_message: tipMessage
            };
            $("#tip_data").val(JSON.stringify(tipData));
        } else {
            $("#tip_data").val('');
        }
    }
    checkTipIsMandatory() {
        var gratuitySetting = this.gratuitySetting;
        if (gratuitySetting.is_mandatory && gratuitySetting.pre_gratuity_enabled) {
            return true;
        }
        return false;
    }
    updateSendButton(gratuityAmount) {
        const sendTipBtn = document.querySelector('.send-tip-btn');
        const btnText = sendTipBtn.querySelector('.btn-text');

        btnText.textContent = `Add Tip - ${currencyFormation.symbol}${gratuityAmount.toFixed(2)}`;

        // Enable/disable button based on amount
        if (gratuityAmount > 0) {
            sendTipBtn.classList.remove('disabled');
            sendTipBtn.style.opacity = '1';
        } else {
            sendTipBtn.style.opacity = '0.7';
        }
    }
    renderCustomTipAmount(inputElement) {
        const customAmount = parseFloat(inputElement.val());
        if (customAmount > 0) {
            // Remove active class from all buttons
            document.querySelectorAll('.amount-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            $("#tip-percentage-slider").slider("value", 0);
            $("#percentage-value").text("0%");
            $('#tipPercentage').val(0);
        }
        const sendTipBtn = document.querySelector('.send-tip-btn');
        const btnText = sendTipBtn.querySelector('.btn-text');

        if (customAmount > 0) {
            btnText.textContent = `Add Tip - ${currencyFormation.symbol}${customAmount.toFixed(2)}`;
        }
    }
    setGratuitySetting(setting) {
        this.gratuitySetting = setting;
    }
    getGratuitySetting() {
        return this.gratuitySetting;
    }
    handleSendTip() {
        var gratuitySetting = this.gratuitySetting;
        var isTipMandatory = gratuitySetting.is_mandatory;
        var preTipEnabled = gratuitySetting.pre_gratuity_enabled;
        var gratuityType = this.gratuityType;
        if (gratuityType === 'fixed_percentage') {
            this.tipAmount = this.roundout((totalBeforeDiscountAndServiceCommissionPackage * this.tipPercentage / 100), 2);
        } else {
            this.tipAmount = $('.amount-btn.active').data('amount');
        }

        if ($('#customAmount').val() && $('#customAmount').val() > 0) {
            this.tipAmount = parseFloat($('#customAmount').val());
            this.customTipAmount = this.tipAmount;
            this.tipPercentage = 0; // Reset percentage if custom amount is used
        } else {
            this.customTipAmount = 0;
        }

        if (isTipMandatory && preTipEnabled && this.tipAmount <= 0) {
            swal('Error!', 'Please select a tip amount.', 'error');
            return;
        }
        this.tipFees = this.calculateTipFeesAmount(this.bookingServiceCommission);
        this.message = $('#message').val() || '';
        this.setTipFormData();
        this.calculateUpdatedTipAmounts();
        if (isGroupRateEnabled == 1) {
            updateGroupRatesTable();
        } else {
            updateRatesTable();
        }
        this.hideShowSplitInputUI();

        $('#tipModal').modal('hide');
    }
    calculateUpdatedTipAmounts() {
        var tourPackageId = $('#tour_package').val();
        this.tipAmountTotal = 0;
        this.tipFeeTotal = 0;
        if (cartView == 1 && cartPackages.length > 0) {
            var cartPackageDetails = getCartPackageDetails(tourPackageId);
            if (cartPackageDetails && Object.keys(cartPackageDetails).length > 0) {
                if (this.tipAmount > 0) {
                    if (cartPackageDetails.tip_fees != this.tipFees) {
                        this.tipFeeTotal = this.tipFeeTotal - cartPackageDetails.tip_fees + this.tipFees;
                    }
                    if (cartPackageDetails.tip_amount != this.tipAmount) {
                        this.tipAmountTotal = this.tipAmountTotal - cartPackageDetails.tip_amount + this.tipAmount;
                    }
                }
            } else {
                this.tipFeeTotal = this.tipFees;
                this.tipAmountTotal = this.tipAmount;
            }
            this.tipAmountTotal = parseFloat(this.tipAmountTotal) + parseFloat(totalCartTipAmount);
            this.tipFeeTotal = parseFloat(this.tipFeeTotal) + parseFloat(totalCartTipFees);
        } else {
            this.tipFeeTotal = this.tipFees;
            this.tipAmountTotal = this.tipAmount;
        }
    }
    renderGratuityModal() {
        var gratuitySetting = this.gratuitySetting;
        this.gratuityType = gratuitySetting.gratuity_type;
        var gratuityRules = JSON.parse(gratuitySetting.gratuity_rules);
        var tipData = $('#tip_data').val();
        if (tipData) {
            var tipDataObj = JSON.parse(tipData);

            this.tipAmount = tipDataObj.tip_amount || 0;
            this.tipPercentage = tipDataObj.tip_percentage || 0;
            this.customTipAmount = tipDataObj.custom_tip_amount || 0;
            this.tipFees = tipDataObj.tip_fees || 0;
            this.message = tipDataObj.tip_message || '';
            if (this.tipPercentage > 0) {
                $("#tip-percentage-slider").slider("value", this.tipPercentage);
                $("#percentage-value").text(this.tipPercentage + "%");
                $('#tipPercentage').val(this.tipPercentage);
            }
            $('#customAmount').val(this.customTipAmount);
            $('#message').val(this.message);
        }

        if (!gratuityRules || gratuityRules.length === 0) {
            swal('Sorry!', 'No tip rules found.', 'warning');
            return;
        }

        var htmlTipAmounts = '';
        if (this.gratuityType == 'fixed_amount') {
            for (var i = 0; i < gratuityRules.length; i++) {
                htmlTipAmounts += `<div class="col-6 col-md-3"><button type="button" class="btn amount-btn w-100 ${(this.tipAmount == gratuityRules[i].amount) ? 'active' : ''}" data-amount="${gratuityRules[i].amount}">${currencyFormation.symbol}${gratuityRules[i].amount}</button></div>`;
            }
            $('#tipModal #tipAmountSection').show();
            $('#tipModal #tipAmounts').html(htmlTipAmounts);
            $('#tipModal #tipCustomPercentageSection').hide();
        } else if (this.gratuityType == 'fixed_percentage') {
            for (var i = 0; i < gratuityRules.length; i++) {
                htmlTipAmounts += `<div class="col-6 col-md-3"><button type="button" class="btn amount-btn w-100 ${(this.tipPercentage == gratuityRules[i].percentage) ? 'active' : ''}" data-amount="${gratuityRules[i].percentage}">${gratuityRules[i].percentage}%</button></div>`;
            }
            $('#tipModal #tipAmountSection').show();
            $('#tipModal #tipAmounts').html(htmlTipAmounts);
        }
        if (this.gratuitySetting.allow_pre_custom_gratuity == 1) {
            $('#tipModal #tipCustomAmountSection').show();
            if (this.gratuityType == 'fixed_percentage') {
                $('#tipModal #tipCustomPercentageSection').show();
            }
            $('#tipModal #customAmount').val(this.customTipAmount);
        } else {
            $('#tipModal #tipCustomAmountSection').hide();
        }
        $('#tipModal #message').val(this.message);
        $('#tipModal .send-tip-btn .btn-text').text(`Add Tip - ${currencyFormation.symbol + this.tipAmount.toFixed(2)}`);
        $('#tipModal').modal('show');
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
        $("#booking-addons").modal("hide");
    }

    hideShowSplitInputUI() {
        if (this.tipAmount > 0) {
            $("#dividedBy").prop("selectedIndex", 0);
            $("#split-payment").prop('checked', false);
            $(".split-check").text("$0.00");
            $("#split_value_field").val("").prop('required', false).hide();
            $("#split-payment-div").hide();

            // Split payment was showing the "Total payment" row (#psplit) with the now-stale
            // per-split amount — swap back to the full-total row for the active payment method,
            // same as PaymentHandler.resetSplitValues() does when split is reset elsewhere.
            var paymentMethod = $(".book-pay-method").filter(":checked").val();
            $(".split-set").text("$0.00");
            $("#psplit").hide();
            if (paymentMethod === 'Card' || (paymentMethod === 'Cash' && cashFees == 1)) {
                $("#pcard").show();
            } else if (paymentMethod === 'Cash' || paymentMethod === 'Pay Later') {
                $("#pcash").show();
            }
        } else {
            $("#split-payment-div").show();
            $("#split_value_field").show();
        }
    }

    roundout(amount, places = 2) {
        if (places < 0) {
            places = 0;
        }

        let x = Math.pow(10, places);
        let formul = (amount * x).toFixed(10);
        return (amount >= 0 ? Math.ceil(formul) : Math.floor(formul)) / x;
    }
}

const initTourBookingGratuity = new TourBookingGratuity({ admin: userType });