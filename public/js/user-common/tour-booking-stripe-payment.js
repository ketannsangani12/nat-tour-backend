class TourBookingStripePayment {
    constructor(props) {
        this.isAffiliate = props.isAffiliate;
        this.form = [];
        this.feature = '';
        this.elements = '';
        this.additionalForm = [];
        this.clientSecret = null; // Store clientSecret
        $(document).off('click', '#makePaymentBtn').on('click', '#makePaymentBtn', (evt) => {
            this.handleMakePayment();
        });
    }

    async initializeStripe(form, feature, additionalForm = []) {
        showLoading();

        try {
            $('#payment-element').empty();
            // $('#link-authentication-element').empty();
            $('#saved-cards-container').empty().hide();

            this.form = form;
            this.feature = feature;
            this.additionalForm = additionalForm;

            const totalText = $('.total-after-discount-and-service-commission.set-total').text().trim();
            const totalAmount = parseFloat(
                totalText.replace(/[^0-9.-]+/g, '')
            ) || 0;

            if (totalAmount === 0) { 
                swal({
                    icon: 'warning',
                    title: 'Action Required',
                    text: 'This booking has no payable amount. Please use Cash to proceed.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const formData = new FormData(form);

            // Force the authoritative feature onto the request instead of trusting a hidden
            // <input name="feature"> baked into the form's DOM by the caller. That field can be
            // missing (the append guarded on an object/method existing) or stale (a form reused
            // across features without clearing an earlier value) — either way the backend's
            // switch (stripeCreatePaymentIntentForMakePayment) silently falls through to its
            // `default` case, which creates a COMBO payment intent instead. `feature` here is
            // the same value handleMakePayment() later uses to route the post-payment settle
            // call, so forcing it here keeps the create-intent call and the settle call in sync.
            // FormData.set() replaces every existing entry for the key, so stale/duplicate
            // hidden inputs are neutralized too.
            formData.set('feature', feature);
            formData.append('save_card', $('#save-card-checkbox').is(':checked') ? '1' : '0');

            const intentResponse = await fetch(createPaymentIntentUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData,
            });

            const responseData = await intentResponse.json();

            if (!intentResponse.ok) {
                throw new Error(responseData.message || 'Unknown backend error');
            } else if (responseData.error) {
                throw new Error(responseData.error);
            }

            // Store clientSecret globally
            this.clientSecret = responseData.clientSecret;

            // Store savedCards from backend
            const savedCards = responseData.savedCards || [];

            const appearance = {
                theme: 'stripe',
            };

            const loader = 'auto';

            const options = {
                layout: {
                    type: 'auto',
                },
                paymentMethodOrder: ['card', 'apple_pay', 'google_pay'],
                wallets: {
                    applePay: 'auto',
                    googlePay: 'auto'
                },
                defaultValues: {
                    billingDetails: {
                        email: custEmail,
                        address: {
                            country: countryCode,
                        },
                    },
                },
            };

            this.elements = stripe.elements({ clientSecret: this.clientSecret, appearance, loader });

            const paymentElement = this.elements.create('payment', options);
            // const linkAuthenticationElement = this.elements.create('linkAuthentication');

            await new Promise((resolve, reject) => {
                paymentElement.mount('#payment-element');
                paymentElement.on('ready', resolve);

                // linkAuthenticationElement.mount('#link-authentication-element');
                // linkAuthenticationElement.on('ready', resolve);

                setTimeout(() => reject(new Error('Element mounting timed out')), 30000);
            });

            // Populate saved cards
            const savedCardsContainer = $('#saved-cards-container');
            savedCardsContainer.empty();
            if (savedCards && savedCards.length > 0 && !disableAffiliateCardSave && !disableCardSave) {
                var savedLastCardChecked = false;
                if(savedCards.length >= 1){
                    savedLastCardChecked = true;
                }   
                savedCardsContainer.append('<h5 class="text-base font-semibold text-[#111827] mb-3">Saved Cards:</h5>');
                savedCards.forEach(card => {
                    if (!card.id || !card.id.startsWith('pm_')) {
                        console.error('Invalid PaymentMethod ID in savedCards:', card);
                        return;
                    }
                    savedCardsContainer.append(`
                        <div class="form-check flex items-center mb-2 save-card-list">
                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="saved_payment_method" id="card-${card.id}" value="${card.id}" ${savedLastCardChecked ? 'checked' : ''}>
                            <label class="form-check-label mr-2 form-custom-label !mb-0" for="card-${card.id}">
                                ${card.brand} ending in ${card.last4} (Expires: ${card.exp_month}/${card.exp_year})
                                <span class="checkmark"></span>
                            </label>
                            <i class="fas fa-trash text-danger delete-card text-[#A32624] text-xs" data-payment-method-id="${card.id}" style="cursor: pointer;" title="Delete this card" aria-label="Delete saved card"></i>
                        </div>
                    `);
                });
                savedCardsContainer.append(`
                    <div class="form-check flex items-center mb-2 save-card-list">
                        <input class="form-check-input w-4 h-4 mr-2" type="radio" name="saved_payment_method" id="new-card" value="new" ${savedLastCardChecked ? '' : 'checked'}>
                        <label class="form-check-label form-custom-label !mb-0" for="new-card">
                            Use a new card
                            <span class="checkmark"></span>
                        </label>
                    </div>
                `);
                savedCardsContainer.show();

                // Toggle payment element and save card checkbox
                const toggleSaveCardCheckbox = () => {
                    const selectedValue = $('input[name="saved_payment_method"]:checked').val();
                    if (selectedValue === 'new') {
                        $('#save-card-checkbox').prop('checked', false).closest('.form-check').show();
                        $('#payment-element').show();
                    } else {
                        $('#save-card-checkbox').prop('checked', false).closest('.form-check').hide();
                        $('#payment-element').hide();
                    }
                };

                toggleSaveCardCheckbox();
                if(savedLastCardChecked){
				    $('#payment-element').hide();
			    }
                $('input[name="saved_payment_method"]').on('change', toggleSaveCardCheckbox);

                // Handle delete card
                $(document).off('click', '.delete-card').on('click', '.delete-card', async (evt) => {
                    const paymentMethodId = $(evt.currentTarget).data('payment-method-id');

                    const confirmDelete = await swal({
                        title: 'Are you sure?',
                        text: 'This will permanently delete the saved card.',
                        icon: 'warning',
                        buttons: ['Cancel', 'Delete'],
                        dangerMode: true,
                    });

                    if (!confirmDelete) {
                        return;
                    }

                    if (!deletePaymentMethodUrl) {
                        throw new Error('deletePaymentMethodUrl is not defined');
                    }

                    $(evt.currentTarget).prop('disabled', true);
                    showLoading();
                    try {
                        const response = await fetch(deletePaymentMethodUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ payment_method_id: paymentMethodId }),
                        });

                        const result = await response.json();

                        if (!response.ok) {
                            throw new Error(result.message || 'Unknown backend error');
                        }

                        swal('Success', 'Payment method deleted successfully', 'success');

                        // Refresh saved cards
                        await this.initializeStripe(this.form, this.feature, this.additionalForm);
                    } catch (error) {
                        console.error('Error deleting PaymentMethod:', error);
                        hideLoading();
                        swal('Error', error.message || 'Failed to delete payment method', 'error');
                    } finally {
                        $(evt.currentTarget).prop('disabled', false);
                        hideLoading();
                    }
                });
            } else {
                // No saved cards, ensure checkbox is visible and unchecked
                $('#save-card-checkbox').prop('checked', false).closest('.form-check').show();
                $('#payment-element').show();
            }

            // Show the modal only on success
            if (['make_payment', 'change_people', 'switch_package'].includes(this.feature)) {
                if (this.isAffiliate === 'Yes') {
                    $('#modal_affilate').modal('hide');
                } else {
                    $('#modal_admin').modal('hide');
                }
            }

            const modalData = {
                'make_payment': 'call-by-make-payment',
                'change_people': 'call-by-change-people-payment',
                'edit_discount': 'call-by-edit-discount-payment',
                'switch_package': 'call-by-switch-package-payment',
                'change_payment_type': 'call-by-switch-payment-type-payment',
                'custom_charge': 'call-by-custom-charge-payment',
                'tip': 'call-by-tip-payment',
                'add_ons': 'call-by-add-ons-payment',
                'make_combo_payment': 'call-by-make-combo-payment',
                'addon_edit_charge': 'call-by-addon-edit-charge-payment',
                'bulk_pax_addon_charge': 'call-by-addon-edit-charge-payment'
            };

            if (modalData[this.feature]) {
                $('#make-a-payment').data(modalData[this.feature], true).modal('show');

                // Global setting to hide the card save option for affiliates
                if (disableAffiliateCardSave || disableCardSave) {
                    $('#save-card-checkbox').prop('checked', false).closest('.form-check').hide();
                }
            }
            if (this.feature === 'change_people') {
                $('#edit_booking_change_people').modal('hide');
            } else if (this.feature === 'edit_discount') {
                $('#modal_edit_discount_card_payment').modal('hide');
            } else if (this.feature === 'switch_package') {
                $('#modal-switch-package').modal('hide');
            } else if (this.feature === 'change_payment_type') {
                $('#modal_change_payment_type').modal('hide');
            } else if (this.feature === 'custom_charge') {
                $('#bookingAddCustomCharge').modal('hide');
            } else if (this.feature === 'make_combo_payment') {
                $('#modal_cart_payment').modal('hide');
            }
        } catch (error) {
            console.error('Error initializing Stripe elements:', error);
            hideLoading();

            // Someone else (e.g. another staff member sharing this login) already completed this
            // exact payment. The backend reconciles the booking's records before reporting this, so
            // by now it's genuinely fixed on the server - reload so this screen catches up to it,
            // instead of leaving a dismissable alert that still shows the booking as unpaid.
            if (error?.message === 'This booking has already been fully paid.') {
                swal({
                    icon: 'success',
                    title: 'Already Paid',
                    text: 'This booking has already been paid. The page will reload.',
                }).then(() => {
                    location.reload();
                });
            } else {
                swal('Error occurred', error.message || 'Failed to initialize payment elements', 'error');
            }
        } finally {
            hideLoading();
        }
    }

    async handleMakePayment() {
        showLoading();
        try {
            const feature = this.feature;
            const form = this.form;
            const additionalForm = this.additionalForm;
            $('#makePaymentBtn').prop('disabled', true);

            const selectedPaymentMethod = $('input[name="saved_payment_method"]:checked').val();

            let paymentConfirmation;

            const confirmParams = {
                return_url: 'https://google.com',
                save_payment_method: $('#save-card-checkbox').is(':checked')
            };

            if (selectedPaymentMethod && selectedPaymentMethod !== 'new') {
                if (!selectedPaymentMethod.startsWith('pm_')) {
                    throw new Error(`Invalid PaymentMethod ID: ${selectedPaymentMethod}. Expected a valid Stripe PaymentMethod ID (e.g., pm_xxx).`);
                }
                if (!this.clientSecret) {
                    throw new Error('Client secret is missing');
                }
                paymentConfirmation = await stripe.confirmPayment({
                    clientSecret: this.clientSecret,
                    confirmParams: {
                        ...confirmParams,
                        payment_method: selectedPaymentMethod
                    },
                    redirect: 'if_required'
                });
            } else {
                paymentConfirmation = await stripe.confirmPayment({
                    elements: this.elements,
                    confirmParams: {
                        ...confirmParams,
                        payment_method_data: {
                            billing_details: {
                                name: $('[name="customer_name"]').val(),
                                email: $('[name="customer_email"]').val(),
                                phone: $('[name="customer_phone_number"]').val(),
                            }
                        }
                    },
                    redirect: 'if_required'
                });
            }

            const { paymentIntent, error } = paymentConfirmation;

            if (error) {
                console.error('Payment Confirmation Error:', error);
                hideLoading();
                $('#makePaymentBtn').prop('disabled', false);

                // Someone else (e.g. another staff member sharing this login) already confirmed
                // this exact payment moments ago. Stripe rejects our confirm with its own raw
                // wording (varies by the exact state it caught - e.g. "...has already succeeded
                // after being previously confirmed", or a generic processing error) - the reliable
                // signal is the underlying PaymentIntent already being succeeded, not the specific
                // error code, so check that directly and show something clearer instead.
                if (error.payment_intent && error.payment_intent.status === 'succeeded') {
                    swal({
                        icon: 'success',
                        title: 'Already Paid',
                        text: 'This booking was just paid (possibly by someone else). The page will reload.',
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    swal('Error occurred', error.message, 'error');
                }
            } else {
                if (paymentIntent.id == '') {
                    throw new Error('Payment Intent ID is missing. Please try again.');
                }
                if ($(form).find('input[name="payment_intent_id"]').length === 0) {
                    $(form).append(`<input type="hidden" name="payment_intent_id" value="${paymentIntent.id}">`);
                } else {
                    $(form).find('input[name="payment_intent_id"]').val(paymentIntent.id);
                }

                if (feature === 'make_payment') {
                    this.isAffiliate === 'Yes' ? submitMakePaymentAffiliate(form) : submitMakePayment(form);
                } else if (feature === 'change_people') {
                    const additionalForm = this.isAffiliate === 'Yes' ? $('#modal_affilate').find('form') : $('#modal_admin').find('form');
                    validateAndSubmitFormChangePeople(form, additionalForm, paymentIntent.id);
                } else if (feature === 'switch_package') {
                    const additionalForm = this.isAffiliate === 'Yes' ? $('#modal_affilate').find('form') : $('#modal_admin').find('form');
                    validateAndSubmitFormSwitchPackage(form, additionalForm, paymentIntent.id);
                } else if (feature === 'edit_discount') {
                    submitEditDiscount(form, 'card payment');
                } else if (feature === 'custom_charge') {
                    submitCustomCharge(form, additionalForm);
                } else if (feature === 'tip') {
                    submitTip(form);
                } else if (feature === 'change_payment_type') {
                    changeHistoryPaymentType(form);
                } else if (feature === 'add_ons') {
                    initCustomFormDisplay.submitAddOns(form);
                } else if (feature === 'addon_edit_charge') {
                    if (typeof tourBookingAddons !== 'undefined') {
                        tourBookingAddons._submitAddonEditCharge(
                            tourBookingAddons._pendingAddonPayload,
                            'Card',
                            paymentIntent.id
                        );
                    }
                } else if (feature === 'bulk_pax_addon_charge') {
                    if (typeof tourBookingAddons !== 'undefined') {
                        tourBookingAddons._submitBulkPaxAddonsUpdate(
                            tourBookingAddons._pendingBulkPayload,
                            'Card',
                            paymentIntent.id
                        );
                    }
                } else if (feature === 'make_combo_payment') {
                    makeCartPayment(form);
                }
            }
        } catch (apiError) {
            console.error('API Error:', apiError);
            swal('Error occurred', apiError.message || apiError, 'error');
            hideLoading();
            $('#makePaymentBtn').prop('disabled', false);
        }
    }
}

const initTourBookingStripePayment = new TourBookingStripePayment({ isAffiliate: isAffiliate });