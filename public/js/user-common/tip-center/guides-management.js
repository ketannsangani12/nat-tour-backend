class GuidesManagement {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.userType = props.admin;
        this.saveGeneralInfoUrl = props.saveGeneralInfoUrl;
        this.saveStripeAccountInfoUrl = props.saveStripeAccountInfoUrl;
        this.saveBankAccountInfoUrl = props.saveBankAccountInfoUrl;
        this.bindEvents();
        this.addGlobalListeners();
    }
    bindEvents() {
        $(document).off('click', '#update-general').on('click', '#update-general', (evt) => {
            this.handleUpdateGeneralInfo();
        });
        $(document).off('click', '#update-stripe-details').on('click', '#update-stripe-details', (evt) => {
            this.handleUpdateStripeInfo();
        });
        $(document).off('click', '#update-bank-details').on('click', '#update-bank-details', (evt) => {
            this.handleUpdateBankAccountInfo();
        });
        
        $(document).on('input change', '.capacity-input, .bank-account-inputs', (e) => this.validateInput($(e.target)));
    }
   
    validateInput($element) {
        if ($element.hasClass('capacity-input')) {
            const value = parseFloat($element.val());
            const isValid = !isNaN(value) && value > 0;
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('stripe-account-input')) {
            const value = $element.val();
            const isValid = value && value.trim() != '';
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.closest('.col-md-4').find('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.closest('.col-md-4').find('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('bank-account-inputs')) {
            const value = $element.val();
            const isValid = value && value.trim() != '';
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        } 
        return false;
    }
    validateDropdown($element) {
        if ($element.is('select')) {
            const isValid = $element.val() && $element.val().length > 0;
            const $select2 = $element.next('.select2-container').find('.select2-selection--multiple');

            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $select2.removeClass('is-invalid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $select2.addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        }
        return false;
    }

    validateAndCollectGeneralInfo() {
        const $form = $('#updateGeneralDetailsForm');
        let isValid = true;

        if (!this.validateInput($(`#capacity`))) {
            isValid = false;
        }

        if (!isValid) {
            return { isValid: false, data: null };
        }

        const data = {
            capacity: $('#capacity').val(),
            tourGuideId : $('#tour_guide_id').val(),
        };

        return { isValid: true, data };

    }

    async handleUpdateGeneralInfo() {
        const { isValid, data } = this.validateAndCollectGeneralInfo();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        showLoading();
        try {
            $.ajax({
                url: this.saveGeneralInfoUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    ...data
                })

            })
                .done(function (response) {
                    swal('Success',
                        'General Details Updated successfully!',
                        'success')
                        .then(function () {
                            location = location;
                        });
                })
                .always(hideLoading)
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                });

        } catch (error) {
            console.error('Error updating General Details:', error);
            swal('Error', 'Failed to update General Details. Please try again.', 'error');
        }
    }
    validateAndCollectStripeInfo() {
        const $form = $('#updateStripeDetailsForm');
        let isValid = true;

        if (!this.validateInput($(`#stripe-account-id`))) {
            isValid = false;
        }

        if (!isValid) {
            return { isValid: false, data: null };
        }

        const data = {
            stripeAccountId: $('#stripe-account-id').val(),
            tourGuideId : $('#tour_guide_id').val(),
            primaryMethod: $('#stripe-account-primary-method').is(':checked')
        };

        return { isValid: true, data };

    }
    async handleUpdateStripeInfo() {
        const { isValid, data } = this.validateAndCollectStripeInfo();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        showLoading();
        try {
            $.ajax({
                url: this.saveStripeAccountInfoUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    ...data
                })

            })
                .done(function (response) {
                    swal('Success',
                        'Stripe Account information Updated successfully!',
                        'success')
                        .then(function () {
                            location = location;
                        });
                })
                .always(hideLoading)
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                });

        } catch (error) {
            console.error('Error updating Stripe Account information:', error);
            swal('Error', 'Failed to update Stripe Account information. Please try again.', 'error');
        }
    }
    validateAndCollectBankAccountInfo() {
        const $form = $('#updateBankAccountDetailsForm');
        // $form.addClass('was-validated');

        let isValid = true;

        $('#bank-account-number, #bank-routing-number, #account-holder-name').each((_, el) => {
            if (!this.validateInput($(el))) {
                isValid = false;
            }
        });
    
        if (!isValid) {
            return { isValid: false, data: null };
        }

        const data = {
            bankAccountNumber: $('#bank-account-number').val(),
            bankRoutingNumber: $('#bank-routing-number').val(),
            accountHolderName: $('#account-holder-name').val(),
            bankAccountType: $('#account-type').val(),
            tourGuideId : $('#tour_guide_id').val(),
            primaryMethod: $('#bank-account-primary-method').is(':checked')
        };

        return { isValid: true, data };

    }
    async handleUpdateBankAccountInfo() {
        const { isValid, data } = this.validateAndCollectBankAccountInfo();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        showLoading();
        try {
            $.ajax({
                url: this.saveBankAccountInfoUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    ...data
                })

            })
                .done(function (response) {
                    swal('Success',
                        'Bank Account information Updated successfully!',
                        'success')
                        .then(function () {
                            location = location;
                        });
                })
                .always(hideLoading)
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                });

        } catch (error) {
            console.error('Error updating Stripe Account information:', error);
            swal('Error', 'Failed to update Stripe Account information. Please try again.', 'error');
        }
    }
}


