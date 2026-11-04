// backend-card-reader.js – FINAL VERSION WITH YOUR DESIGN
class BackendCardReader {
    constructor() {
        this.paymentInProgress = false;
        this.cardReader = null;
        // Bind tab switching first so a Stripe Terminal init failure below
        // can never block the user from opening the Card Reader tab.
        this.setupTabs();

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

        this.bindReaderButtons();
        this.bindPaymentActions();
        this.bindModalSafety();
    }

    setupTabs() {
        const self = this;
        $(document).on('click', '.tab-btn', function () {
            const tab = $(this).data('tab');

            // Update active tab
            $('.tab-btn').removeClass('text-blue-600 border-blue-600').addClass('text-gray-500 border-transparent');
            $(this).addClass('text-blue-600 border-blue-600').removeClass('text-gray-500 border-transparent');

            // Show content
            $('.tab-pane').addClass('hidden');
            $('#' + tab).removeClass('hidden');

            if (tab === 'card-reader') {
                self.reconnectIfReaderChanged();
            }
        });

        // The workstation's reader assignment lives in localStorage
        // (StripeReaderIdentity), so the browser's own 'storage' event is
        // the right tool for "another tab changed it" — it fires instantly
        // in every other tab of this origin the moment that happens,
        // regardless of whether this modal is open, which of its tabs is
        // active, or whether this page currently has focus. Covers the
        // case that mattered: switching readers on the Card Reader
        // Settings page in another tab, then coming back here.
        window.addEventListener('storage', (evt) => {
            if (evt.key === 'stripeCardReaderInfo' || evt.key === 'stripeCardReader') {
                self.reconnectIfReaderChanged();
            }
        });
    }

    /**
     * If the workstation's assigned reader was switched elsewhere (e.g. on
     * the Card Reader Settings page, possibly in another tab or a while
     * ago) since this instance last connected, reconnect to the current one
     * instead of silently keeping the stale reader showing "Connected".
     */
    reconnectIfReaderChanged() {
        // Guards against the tab-click trigger and the storage-event
        // trigger firing close together (e.g. a reader gets reassigned
        // right as this tab is clicked into) and each scheduling their own
        // overlapping reconnect attempt.
        if (this._reconnectCheckScheduled) return;
        const notYetConnected = $('.connect-to-card-reader:visible').length > 0;
        const savedId = this.cardReader?.getSavedReader?.()?.id;
        const connectedId = this.cardReader?.reader?.id;
        const staleReader = savedId && connectedId && connectedId !== savedId;
        if (notYetConnected || staleReader) {
            this._reconnectCheckScheduled = true;
            setTimeout(() => {
                this._reconnectCheckScheduled = false;
                $('.connect-to-card-reader').trigger('click');
            }, 500);
        }
    }

    bindReaderButtons() {
        const self = this;
        $(document).off('click', '.connect-to-card-reader').on('click', '.connect-to-card-reader', async () => {
            if (!self.cardReader) {
                $('.card-reader-status-output').html('<div class="alert alert-danger w-100" role="alert">Card reader is unavailable. Please refresh the page.</div>');
                return;
            }
            $('.connect-to-card-reader').hide();
            $('.dis-connect-to-card-reader').show();
            const amount = self.calculateAmount();
            await this.cardReader.connectToReader("make-payment-with-card-reader", false, null, amount);
        });

        $(document).off('click', '.dis-connect-to-card-reader').on('click', '.dis-connect-to-card-reader', async () => {
            $('.dis-connect-to-card-reader').hide();
            $('.connect-to-card-reader').show();
            await this.cardReader.disconnectReader();
        });
    }

    bindPaymentActions() {
        $(document).off('click', '.make-payment-with-card-reader').on('click', '.make-payment-with-card-reader', () => {
            if (this.paymentInProgress) return;
            if (this.validateForm()) {
                this.startPayment();
            }
        });

        $(document).off('click', '.cancel-card-reader-payment').on('click', '.cancel-card-reader-payment', () => {
            this.cancelPayment();
        });
    }

    bindModalSafety() {
        $('#make-a-payment').on('hidden.bs.modal', () => {
            if (this.paymentInProgress) {
                this.cardReader.cancelPayment();
                this.paymentInProgress = false;
                swal("Payment Cancelled", "The payment was safely cancelled.", "warning");
            }
        });
    }

    /**
     * Validate the form before capture the payment from card reader
     * @returns
     */
    validateForm() {
        if (!validateEmailAddressFormat({ inputId: 'customer_email' }))
            return false;

        if (!validateCharacters('#customer_name, #customer_phone_number'))
            return false;

        if ($('#affiliatecust').length > 0) {
            var affiliate_id = $('#affiliatecust option:selected').val();
            if (affiliate_id > 0 && $('#is_reserved_for_affiliate').length > 0 && $('#is_reserved_for_affiliate option:selected').val() == '1') {
                if ($('#pay-with-card')[0].checked || ($('#pay-with-cash').length && $('#pay-with-cash')[0].checked)) {
                    swal('You do not allow to reserve seats using any payment method.', '', 'warning');
                    return false;
                }
            }
        }

        if (cartView == 0 && bookingHasContacts == 1) {
            if (rateGroupWiseContactsCount > 0) {
                var contactsValidation = tourBookingContacts.newBookingValidation();

                if (!contactsValidation) {
                    return false;
                }
            }
        }

        if (cartView == 1 && isComboPackage == 1 && Object.keys(cartPackages).length == 0) {
            swal('Your cart is empty.Please add packages.', '', 'warning');
            return false;
        }

        if (cartView == 1 && Object.keys(cartPackages).length > 15) {
            swal('You can add up to 15 packages in the cart.', '', 'warning');
            return false;
        }

        if ($('#pay-with-card')[0].checked == false) {
            swal('You only allow to reserve seats using card payment method.', '', 'warning');
            return false;
        }
        return true;
    }

    startPayment() {
        if (!this.cardReader) {
            $('#reader-error').text('Card reader is unavailable. Please refresh the page.').removeClass('hidden');
            return;
        }
        this.paymentInProgress = true;
        // Not showing #reader-processing here anymore — the card reader's
        // own progression (Charge button goes busy, then "Waiting for
        // card…" with a working Cancel button) already covers this exact
        // window. Showing both stacked two near-identical spinners on top
        // of each other with no way to tell them apart.
        this.capturePaymenWithStripeCardReader();
    }

    cancelPayment() {
        if (this.cardReader) {
            this.cardReader.cancelPayment();
        }
        this.paymentInProgress = false;
        $('#reader-processing').addClass('hidden');
    }

    capturePaymenWithStripeCardReader() {
        // Normalize the customer fields in the DOM so the value the user sees
        // matches the value we validate and send — pasted emails often carry a
        // trailing space or invisible whitespace that fails the email regex.
        const trimmedName = ($('#customer_name').val() || '').trim();
        const trimmedEmail = ($('#customer_email').val() || '').trim();
        const trimmedPhone = ($('#customer_phone_number').val() || '').trim();
        $('#customer_name').val(trimmedName);
        $('#customer_email').val(trimmedEmail);
        $('#customer_phone_number').val(trimmedPhone);

        const meta = {
            customer_name: trimmedName,
            customer_email: trimmedEmail,
            customer_phone_number: trimmedPhone
        };

        if (meta.customer_name === "" || meta.customer_email === "") {
            swal('Error occurred', 'Customer name and email are required.', 'error');
            return;
        }

        // Email format validation
        var emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        if (!emailPattern.test(meta.customer_email)) {
            swal('Invalid Email', 'Please enter a valid email address.', 'error');
            return;
        }

        var amount = 0;
        var fees = 0;
        // Settlement-mode resolution (see CardReaderRepository::resolveModeFromInput()) reads
        // these tour dates server-side — no booking row exists yet for a brand-new booking, so
        // the dates have to come from what's already selected client-side, same as the cart
        // totals just below.
        if (cartView == 1 && Object.keys(cartPackages).length > 0) {
            fees = Number(totalCartServiceCommission + totalCustomChargeFees + totalCartAddonsFees).toFixed(2);
            amount = Number(cartTotal + totalCustomChargeAmount + totalCustomChargeFees + totalCartAddonsTotal).toFixed(2);
            // Drop any cart item with no real date rather than let moment() produce its own literal
            // "Invalid date" string for it (a plain package with no slot ever selected has no `date`
            // at all). Send only the LATEST date across the cart, not every item's — equivalent to
            // PaymentModeResolver::resolve()'s own "any date >= 2027-01-01" rule (the max only
            // crosses the boundary when at least one item does), so this changes nothing about
            // which mode gets picked, just how many values get sent. An empty result (no cart item
            // had a real date) degrades to the same safe DESTINATION fallback as before.
            const cartDates = Object.values(cartPackages)
                .map((p) => p.date)
                .filter((d) => !!d)
                .map((d) => moment(d).format('YYYY-MM-DD'));
            meta.dates = cartDates.length ? [cartDates.reduce((latest, d) => (d > latest ? d : latest))] : [];
        } else {
            const addOnFee = initTourBookingAddCheckoutAddons.getFees();
            serviceCommission = roundout(serviceCommission + addOnFee + customChargeFeesTotal, 2);
            fees = Number(serviceCommission).toFixed(2);
            amount = Number(totalAfterDiscountAndServiceCommission).toFixed(2);
            // Read the datepicker's own internal selection rather than the #hid-tour_slot_date
            // alt-field it's paired with: colorDate() (tour-booking-add-edit.js) destroys and
            // reinitializes this widget on every package/date interaction, which clears that alt
            // field and never gets a chance to re-sync it (jQuery UI only updates altField from a
            // real day click or an explicit setDate call, not from a plain reinit) — even though
            // the widget's own selected date, and the visible #tour_slot_date text, stay correct
            // throughout. getDate() reads that same live state directly, so it can't drift.
            const selectedSlotDate = $('#tour_slot_date').datepicker('getDate');
            meta.dates = selectedSlotDate ? [moment(selectedSlotDate).format('YYYY-MM-DD')] : [];
        }

        const isSplitPaymentChecked = $('#split-payment').prop('checked');
        if (isSplitPaymentChecked) {
            amount = $('#split_value [name="split_value"]').val();
            fees = roundout(amount * stripeCommissionPercentage / 100);
        }

        if (amount <= 0) {
            swal('Error occurred', 'Please enter a valid amount.', 'error');
            return;
        }

        this.cardReader.makePayment(
            amount, "", fees, meta,
            (data) => this.onSuccess(data),
            () => this.onFail()
        );
    }

    /**
     * Calculate the amount for display
     */
    calculateAmount() {
        try {
            var amount = 0;
            if (typeof cartView !== 'undefined' && cartView == 1 && typeof cartPackages !== 'undefined' && Object.keys(cartPackages).length > 0) {
                amount = Number(cartTotal + totalCustomChargeAmount + totalCustomChargeFees + totalCartAddonsTotal).toFixed(2);
            } else if (typeof totalAfterDiscountAndServiceCommission !== 'undefined') {
                amount = Number(totalAfterDiscountAndServiceCommission).toFixed(2);
            }

            const isSplitPaymentChecked = $('#split-payment').prop('checked');
            if (isSplitPaymentChecked && $('#split_value [name="split_value"]').val()) {
                amount = $('#split_value [name="split_value"]').val();
            }

            return amount;
        } catch (err) {
            console.log('Error calculating amount:', err);
            return 0;
        }
    }

    onSuccess(data) {
        this.paymentInProgress = false;
        $('#reader-processing').addClass('hidden');
        $('#reader-success').removeClass('hidden');

        const form = document.getElementById('payment-form');
        let input = form.querySelector('input[name="payment_intent_id"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'payment_intent_id';
            form.appendChild(input);
        }
        input.value = data.id;

        setTimeout(() => postTourBooking(form), 1500);
    }

    onFail() {
        // No longer takes/shows err here — the reader's own error display
        // (.card-reader-status-output, now guaranteed to be populated for
        // every failure path — see makePayment()) already shows it.
        // Showing both stacked a boxed "Error processing payment: ..."
        // alert directly above a second, plain-text repeat of the same
        // message.
        this.paymentInProgress = false;
        $('#reader-processing').addClass('hidden');
    }
}

// Initialize
$(document).ready(function () {
    if ($('#make-a-payment').length) {
        window.cardReader = new BackendCardReader();
    }
});