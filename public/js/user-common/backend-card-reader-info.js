/**
 * Setup the backend card reader for payments in Booking Info section
 * Matches behavior of backend-card-reader.js — clean, minimal, auto-saves card silently
 */
class BackendCardReaderInfo {

    constructor() {
        this.cardReader = null;
        try {
            this.cardReader = new StripeCardReader({
                messageDiv: ".card-reader-status-output",
                onStateChange: (state, meta) => {
                    if (typeof StripeReaderIdentity !== 'undefined') {
                        StripeReaderIdentity.applyState(state, meta);
                    }
                },
            });
            // Paint the last-known reader's name/model immediately — don't
            // wait for a discovery round-trip before staff can see which
            // unit this workstation is set up to use.
            if (typeof StripeReaderIdentity !== 'undefined') {
                StripeReaderIdentity.applyState('idle', {});
            }
        } catch (err) {
            console.error('Failed to initialize Stripe Terminal:', err);
            $('.card-reader-status-output').html(
                `<div class="alert alert-danger w-100" role="alert">Card reader is unavailable (${err.message}). Please refresh the page or contact support.</div>`
            );
        }
        this.paymentIntentId = null;
        this.bookingId = bookingId;
        this.total = 0;
        this.fees = 0;
        this.customerName = ($('#customerName').text() || '').trim();
        this.customerEmail = ($('#customerEmail').text() || '').trim();
        this.customerPhoneNumber = ($('#customerNum').text() || '').trim();

        // Connect to card reader
        $(document).off('click', '.connect-to-card-reader').on('click', '.connect-to-card-reader', async (evt) => {
            if (!this.cardReader) {
                $('.card-reader-status-output').html('<div class="alert alert-danger w-100" role="alert">Card reader is unavailable. Please refresh the page.</div>');
                return;
            }
            $(evt.target).hide();
            setTimeout(() => $(".dis-connect-to-card-reader").show(), 1000);
            setTimeout(() => $(".card-reader-status-output").show(), 1000);
            await this.cardReader.connectToReader("make-payment-with-card-reader", false, null, this.total);
        });

        // Disconnect from reader
        $(document).off('click', '.dis-connect-to-card-reader').on('click', '.dis-connect-to-card-reader', async (evt) => {
            if (!this.cardReader) return;
            $(evt.target).hide();
            $(".connect-to-card-reader").show();
            $(".card-reader-status-output").hide();
            await this.cardReader.disconnectReader();
        });

        // Trigger payment
        $(document).off('click', '.make-payment-with-card-reader').on('click', '.make-payment-with-card-reader', () => {
            if (!this.cardReader) {
                $('.card-reader-status-output').html('<div class="alert alert-danger w-100" role="alert">Card reader is unavailable. Please refresh the page.</div>');
                return;
            }
            this.capturePaymenWithStripeCardReader();
        });

        $(document).off('click', '.cancel-card-reader-payment').on('click', '.cancel-card-reader-payment', () => {
            if (this.cardReader) {
                this.cardReader.cancelPayment();
            }
        });
    }

    /**
     * Main payment capture — identical logic to new booking flow
     */
    capturePaymenWithStripeCardReader() {
        // Re-trim here as a guard — the constructor trims once at load, but the
        // displayed fields can be refreshed (booking edits), and pasted values
        // often carry trailing whitespace that fails the email regex.
        const tourPackage = typeof packageName !== 'undefined' ? packageName : '';
        const customerMeta = {
            customer_name: (this.customerName || '').trim(),
            customer_email: (this.customerEmail || '').trim(),
            customer_phone_number: (this.customerPhoneNumber || '').trim()
        };

        if (!customerMeta.customer_name || !customerMeta.customer_email) {
            swal('Error occurred', 'Customer name and email are required.', 'error');
            return;
        }

        const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailPattern.test(customerMeta.customer_email)) {
            swal('Invalid Email', 'Please enter a valid email address.', 'error');
            return;
        }

        let amount = this.total;
        let fees = this.fees;

        if (amount <= 0) {
            swal('Error', 'Payment amount is invalid. Please try again.', 'error');
            return;
        }

        // Mirror StripeUtils::createStripePaymentIntentCommon()'s metadata shape (used by
        // Card Online for this same booking) so a Card Reader charge is equally identifiable
        // in the Stripe Dashboard, instead of showing only the raw PaymentIntent ID. amount/
        // platform_fee reuse the exact values already being charged (this.total/this.fees) —
        // no separate recomputation, so there's no risk of metadata disagreeing with what's
        // actually captured. partner_fee/payout_amount are hardcoded 0.00 to match that same
        // shared builder's own convention (real values are only ever set for is_partner_booking
        // edit-discount charges, which don't apply here). tour_date/tour_time are intentionally
        // omitted — no reliably-scoped, already-timezone-converted global for them exists on
        // this page yet (unlike bookingId/packageName/bookingTourists).
        const meta = {
            ...customerMeta,
            booking_id: this.bookingId,
            tour_package: tourPackage,
            tourists: typeof bookingTourists !== 'undefined' ? bookingTourists : '',
            amount: amount,
            platform_fee: fees,
            partner_fee: 0.00,
            payout_amount: 0.00
        };

        const description = `Booking Id: ${this.bookingId}\nTour Package: ${tourPackage}\nTourists: ${meta.tourists}\nPayment Method: Card Reader`;

        this.cardReader.makePayment(
            amount,
            description,
            fees,
            meta,
            (data) => this.onCardReaderSuccess(data),
            () => this.onCardReaderFail()
        );
    }

    /**
     * Success handler — 100% your original logic
     */
    onCardReaderSuccess = (data) => {
        this.paymentIntentId = data.id;

        // Passenger add-on edit charge (single or bulk) doesn't submit via a DOM form like
        // the flows below — it POSTs a JSON payload (see tour-booking-addons.js). Route it
        // there directly and stop, otherwise this would fall through to the default branch
        // and call postTourBooking() on the wrong form: the card would be charged
        // successfully but the add-ons/payment would never be saved against the booking.
        if ($('#make-a-payment').data('call-by-addon-edit-charge-payment')) {
            if (typeof tourBookingAddons !== 'undefined') {
                if (tourBookingAddons._pendingCardReaderFeature === 'bulk_pax_addon_charge') {
                    tourBookingAddons._submitBulkPaxAddonsUpdate(tourBookingAddons._pendingBulkPayload, 'Card', this.paymentIntentId);
                } else {
                    tourBookingAddons._submitAddonEditCharge(tourBookingAddons._pendingAddonPayload, 'Card', this.paymentIntentId);
                }
            }
            return;
        }

        // Find the form (adjust the selector if needed)
        let infoForm = document.querySelector("#modal-affilate1-form");

        // Check if this is edit discount payment (either modal visible or data attribute set)
        if ($('#modal_edit_discount_card_payment').is(':visible') || $('#make-a-payment').data('call-by-edit-discount-payment')) {
            infoForm = document.querySelector("#modal-edit-discount-card-payment");
        }
        // Check if this is custom charge payment
        if ($('#bookingAddCustomCharge').is(':visible') || $('#make-a-payment').data('call-by-custom-charge-payment')) {
            infoForm = document.querySelector("#modal-custom-charge-form");
        }
        if ($('#modal_addon_payment').is(':visible')) {
            infoForm = document.querySelector("#modal-addons-payment-form");
        }
        if ($('#modal_affilate').is(':visible')) {
            infoForm = document.querySelector("#makePaymentForm");
        }
        if ($('#add_tips').is(':visible')) {
            infoForm = document.querySelector("#tips-payment-form");
        }
        if ($('#modal_cart_payment').is(':visible') || $('#make-a-payment').data('call-by-make-combo-payment')) {
            infoForm = document.querySelector("#modal-cart-payment-form");
        }
        if ($('#modal_change_payment_type').is(':visible') || $('#make-a-payment').data('call-by-switch-payment-type-payment')) {
            infoForm = document.querySelector("#modal-change-payment-type-form");
        }

        if (infoForm) {
            // Check if the hidden input already exists
            let inputInfo = infoForm.querySelector("input[name='payment_intent_id']");
            if (!inputInfo) {
                inputInfo = document.createElement("input");
                inputInfo.type = "hidden";
                inputInfo.name = "payment_intent_id";
                infoForm.appendChild(inputInfo);
            }

            // Set paymentIntentId
            inputInfo.value = this.paymentIntentId;

            const paymentIntentId = inputInfo.value;

            if (typeof isTourBookingInfo !== "undefined" && isTourBookingInfo === true &&
                ($('#modal_affilate').data('call-by-edit-booking-change-people') || $('#modal_admin').data('call-by-edit-booking-change-people'))) {
                const form = $('#edit_booking_change_people').find('form');
                form.data('validation_check', false);
                validateAndSubmitFormChangePeople(form[0], infoForm, paymentIntentId);
            } else if (typeof isTourBookingInfo !== "undefined" && isTourBookingInfo === true &&
                ($('#modal_affilate').data('call-by-switch-package') || $('#modal_admin').data('call-by-switch-package'))) {
                const form = $('#modal-switch-package').find('form');
                form.data('validation_check', false);
                validateAndSubmitFormSwitchPackage(form[0], infoForm, true, paymentIntentId);
            } else if ($('#modal_edit_discount_card_payment').is(':visible') || $('#make-a-payment').data('call-by-edit-discount-payment')) {
                // edit discount - check both the edit discount modal and if make-a-payment was opened for edit discount
                updateCardPayment(paymentIntentId);
            } else if ($('#modal_addon_payment').is(':visible')) {
                // addon
                initCustomFormDisplay.makeAddonsPayment(paymentIntentId);
            } else if ($('#bookingAddCustomCharge').is(':visible') || $('#make-a-payment').data('call-by-custom-charge-payment')) {
                // custom charge - check both the custom charge modal and if make-a-payment was opened for custom charge
                showMakePaymentForCustomCharge(infoForm, paymentIntentId);
            } else if ($('#modal_affilate').is(':visible')) {
                // custom charge
                validateAndSubmitForm1(infoForm, paymentIntentId);
            } else if ($('#add_tips').is(':visible')) {
                // tip payment
                validateAndSubmitForm3(infoForm, paymentIntentId);
            } else if ($('#modal_cart_payment').is(':visible') || $('#make-a-payment').data('call-by-make-combo-payment')) {
                // cart payment
                validateAndSubmitForm5(infoForm, paymentIntentId);
            } else if ($('#modal_change_payment_type').is(':visible') || $('#make-a-payment').data('call-by-switch-payment-type-payment')) {
                // change payment type
                changeHistoryPaymentType(infoForm);
            } else {
                // Call the existing function to validate and submit
                postTourBooking(infoForm);
            }
        } else {
            console.error("Form not found!");
        }
    }

    /**
     * on Card Reader Payment fail
     */
    onCardReaderFail() {
        // No swal here anymore — the reader's own error display
        // (.card-reader-status-output, plus the identity chip/state area)
        // already shows this in context, with friendlier wording. A
        // blocking "Error" popup on top of that just repeated the same
        // (often cryptic, raw SDK) message a third time.
        this.paymentIntentId = null;
    }

    /**
     * To get the total amount
      @param {} total 
     */
    getTotal(total = 0) {
        this.total = parseFloat(total) || 0;
    }

    /**
     * To get the fees amount
      @param {} fees 
     */
    getFees(fees = 0) {
        this.fees = parseFloat(fees) || 0;
    }

    /**
     * To round the fees amount
      @param {} amount 
      @param {} places 
     * @returns 
     */
    roundout(amount, places = 2) {
        if (places < 0) places = 0;
        const factor = Math.pow(10, places);
        return Math.round(amount * factor) / factor;
    }
}

// This modal can stay open a long time across several bookings. If the
// workstation's assigned reader was switched elsewhere in the meantime
// (e.g. on the Card Reader Settings page, possibly in another tab), this
// page has no way to know on its own — it just keeps trusting whatever it
// connected to earlier in its own lifetime. Reconnect to the current one
// instead of silently keeping the stale reader showing "Connected".
let reconnectCheckScheduled = false;
function reconnectCardReaderIfChanged() {
    // Guards against the tab-click trigger and the storage-event trigger
    // firing close together (e.g. a reader gets reassigned right as this
    // tab is clicked into) and each scheduling their own overlapping
    // reconnect attempt.
    if (reconnectCheckScheduled) return;
    const savedId = backendCardReaderInfo?.cardReader?.getSavedReader?.()?.id;
    const connectedId = backendCardReaderInfo?.cardReader?.reader?.id;
    const staleReader = savedId && connectedId && connectedId !== savedId;
    if ($('.connect-to-card-reader:visible').length || staleReader) {
        reconnectCheckScheduled = true;
        setTimeout(() => {
            reconnectCheckScheduled = false;
            $('.connect-to-card-reader').trigger('click');
        }, 300);
    }
}

// Quick fix: Enable tabs in Booking Info modal
$(document).on('click', '.tab-btn', function () {
    const tab = $(this).data('tab');
    $('.tab-btn').removeClass('text-blue-600 border-blue-600').addClass('text-gray-500 border-transparent');
    $(this).addClass('text-blue-600 border-blue-600').removeClass('text-gray-500 border-transparent');
    $('.tab-pane').addClass('hidden');
    $('#' + tab).removeClass('hidden');

    if (tab === 'card-reader') {
        reconnectCardReaderIfChanged();
    }
});

// The workstation's reader assignment lives in localStorage
// (StripeReaderIdentity), so the browser's own 'storage' event is the right
// tool for "another tab changed it" — it fires instantly in every other tab
// of this origin the moment that happens, regardless of whether this modal
// is open, which of its tabs is active, or whether this page currently has
// focus. Covers the case that mattered: switching readers on the Card
// Reader Settings page in another tab, then coming back here.
window.addEventListener('storage', (evt) => {
    if (evt.key === 'stripeCardReaderInfo' || evt.key === 'stripeCardReader') {
        reconnectCardReaderIfChanged();
    }
});

// Set total and fees when #make-a-payment modal opens
$(document).on('show.bs.modal', '#make-a-payment', function () {
    if (!backendCardReaderInfo) return;

    // Check if this is being opened for edit discount, custom charge, change people, change payment type, or cart payment (values already set)
    const isEditDiscount = $('#modal_edit_discount_card_payment').is(':visible') ||
        $(this).data('call-by-edit-discount-payment');
    const isCustomCharge = $('#bookingAddCustomCharge').is(':visible') ||
        $(this).data('call-by-custom-charge-payment');
    const isChangePeople = $('#edit_booking_change_people').is(':visible') ||
        $(this).data('call-by-change-people-payment');
    const isChangePaymentType = $('#modal_change_payment_type').is(':visible') ||
        $(this).data('call-by-switch-payment-type-payment');
    const isCartPayment = $('#modal_cart_payment').is(':visible') ||
        $(this).data('call-by-make-combo-payment');
    const isSwitchPackage = ($('#modal_admin').data('call-by-switch-package') ||
        $('#modal_affilate').data('call-by-switch-package'));
    // Passenger add-on edit charge (single or bulk) — see tour-booking-addons.js
    // _initAddonEditCardPayment()/_initBulkPaxCardPayment(), which set the correct
    // incremental total/fees via getTotal()/getFees() before this modal is shown.
    const isAddonEditCharge = $(this).data('call-by-addon-edit-charge-payment');

    if ((isEditDiscount || isCustomCharge || isChangePeople || isChangePaymentType || isCartPayment || isSwitchPackage || isAddonEditCharge) && backendCardReaderInfo.total > 0) {
        // Values already set, don't override them
        return;
    }

    let amount = 0;
    let fees = 0;

    // Helper function to get split amount from fields
    const getSplitAmount = (splitField, splitValueField, dividedBy, isSplitChecked) => {
        if (splitField && splitField !== "" && splitField !== "0") {
            return parseFloat(splitField);
        }
        if (splitValueField && splitValueField !== "" && splitValueField !== "0" &&
            dividedBy && dividedBy !== "Please select option" && isSplitChecked) {
            return parseFloat(splitValueField);
        }
        return null;
    };

    // Check URL for split payment parameter
    const urlParams = new URLSearchParams(window.location.search);
    const isSplitPaymentFromUrl = urlParams.get('split') && parseInt(urlParams.get('split')) > 0;

    // Get split amount from modal_admin
    const adminSplitAmount = getSplitAmount(
        $('#split_value_field1').val(),
        $('#modal_admin [name="split_value"]').val(),
        $('#modal_admin #dividedBy').val(),
        $('#modal_admin #split-payment').prop('checked')
    );

    // Get split amount from modal_affilate
    const affiliateSplitAmount = getSplitAmount(
        $('#split_value_field').val(),
        $('#modal_affilate [name="split_value"]').val(),
        $('#modal_affilate #dividedBy').val(),
        $('#modal_affilate #split-payment').prop('checked')
    );
    // Determine amount based on priority
    if (adminSplitAmount) {
        amount = adminSplitAmount;
    } else if (affiliateSplitAmount) {
        amount = affiliateSplitAmount;
    } else if (isSplitPaymentFromUrl && typeof splitPendingAmount !== 'undefined' && splitPendingAmount !== "" && splitPendingAmount !== "0") {
        amount = parseFloat(splitPendingAmount);
    } else if (isChangePaymentType && typeof totalForCashChangePaymentType !== 'undefined') {
        // For change payment type, use the totalForCashChangePaymentType variable
        amount = parseFloat(totalForCashChangePaymentType) || 0;
    } else if (isCartPayment && typeof cartPaymentTotalForCard !== 'undefined') {
        // For cart payment, use the cartPaymentTotalForCard variable
        amount = parseFloat(cartPaymentTotalForCard) || 0;
        fees = parseFloat(cartServiceCommissionTotal) || 0;
    } else if (isAffiliate == 'No' && $('#hidden_total_for_admin').length && $('#hidden_total_for_admin').val()) {
        // For regular booking payment (admin)
        amount = parseFloat($('#hidden_total_for_admin').val()) || 0;
        fees = parseFloat($('#hidden_fees_for_admin_make_payment').val()) || 0;
    } else if (isAffiliate == 'Yes' && $('#hidden_total_for_affiliate').length && $('#hidden_total_for_affiliate').val()) {
        // For regular booking payment (affiliate)
        amount = parseFloat($('#hidden_total_for_affiliate').val()) || 0;
        fees = parseFloat($('#hidden_fees_for_affiliate').val()) || 0;
    }

    // Calculate fees if not already set
    if (amount > 0 && fees === 0) {
        fees = backendCardReaderInfo.roundout(amount * stripeServiceCommission / 100);
    }

    backendCardReaderInfo.getTotal(amount);
    backendCardReaderInfo.getFees(fees);
});

// GLOBAL SAFETY: Cancel any in-progress card reader payment when modal is closed
$(document).on('hidden.bs.modal', '#make-a-payment', function () {
    // If a card reader payment is in progress → cancel it
    if (window.cardReader && typeof window.cardReader.cancelPayment === 'function') {
        window.cardReader.cancelPayment();
    }
    if (backendCardReaderInfo && typeof backendCardReaderInfo.cardReader?.cancelPayment === 'function') {
        backendCardReaderInfo.cardReader.cancelPayment();
    }

    // Optional: Show user it was safely cancelled
    if (window.cardReader?.paymentInProgress ||
        (backendCardReaderInfo && backendCardReaderInfo.cardReader?.paymentInProgress)) {
        swal("Payment Cancelled", "The card reader payment was safely cancelled.", "warning");
    }

    // Reset states (optional but clean)
    if (window.cardReader) window.cardReader.paymentInProgress = false;
    if (backendCardReaderInfo && backendCardReaderInfo.cardReader) {
        backendCardReaderInfo.cardReader.paymentInProgress = false;
    }

    // Clear the addon-edit-charge flag so a later, unrelated payment on this same
    // modal instance (e.g. a plain "Make Payment") doesn't inherit this charge's
    // total/fees via the isAddonEditCharge guard in the show.bs.modal handler above.
    $('#make-a-payment').removeData('call-by-addon-edit-charge-payment');
});