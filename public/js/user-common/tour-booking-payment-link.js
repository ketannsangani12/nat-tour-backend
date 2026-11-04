class TourBookingPaymentLink {
    constructor(props) {
        this.props = props;
        this.admin = (props.admin === 'Admin') ? '/admin' : '';
        this.getPaymentLinkReasonUrl = getPaymentLinkReasonUrl;
        this.expiryDateOverride = expiryDateOverride;
        this.page = "create_booking";
        this.bindEvents();
    }

    /**
     * Bind event listeners to DOM
     */
    bindEvents() {
        $(document)
            .off("click", "#initPaymentLinkModal")
            .on("click", "#initPaymentLinkModal", this.initPaymentLinkModal.bind(this));

        $(document)
            .off("click", ".create-payment-link")
            .on("click", ".create-payment-link", this.validateAndSavePaymentLink.bind(this));

        $(document)
            .off("shown.bs.modal", "#createPaymentLink")
            .on("shown.bs.modal", "#createPaymentLink", this.paymentLinkModal.bind(this));
    }

    /**
     * Initialize and validate modal input before showing payment link modal
     */
    initPaymentLinkModal() {
        let selectedPax = 0;
        if (cartView == 0 || Object.keys(cartPackages).length == 0) {
            var tourPackageId = $("#tour_package").val();
            var timeSlot = $("#slot_time").val();
            var customerPhone = $("#customer_phone_number").val().trim();
            var customerEmail = $("#customer_email").val().trim();
            var customerName = $("#customer_name").val().trim();
            var date = $('#tour_slot_date').val();

            if (!tourPackageId) return swal('Please select the tour package.', '', 'warning');
            if (!timeSlot) return swal('Please select a time slot.', '', 'warning');
            if (!customerName) return swal('Please enter customer name.', '', 'warning');
            if (!customerEmail) return swal('Please enter email.', '', 'warning');
        }

        if (cartView == 1 && isComboPackage == 1 && Object.keys(cartPackages).length == 0) {
            swal('Your cart is empty.Please add packages.', '', 'warning');
            return false;
        } else if (cartView == 0) {
             $("#table-rate-groups .tourists").each(function () {
                selectedPax += parseInt($(this).val()) || 0;
            });

            if (selectedPax === 0) {
                return swal('Please select at least one tourist to create payment link.', '', 'warning');
            }

            const bookingPrice = $('#K').text().replace(/[^0-9.]/g, '');
            $(".pl-amount").val(bookingPrice);
            $(".pl-customer-mobile-no").val(customerPhone);
            $(".pl-customer-email").val(customerEmail);
        }
        const postData = {
                cartView: cartView,
                tourPackageId: tourPackageId,
                slot_time: timeSlot,
                date: date
            };
        showLoading();    

        $.post(this.expiryDateOverride, postData)
            .done(function (response) {
                if (response == 1) {
                    swal({
                        title: 'An override event',
                        text: 'Expiry date is over, Still you want to proceed',
                        icon: "warning",
                        dangerMode: true,
                        buttons: true,
                        buttons: {
                            no: {
                                text: 'Cancel',
                                value: false,
                            },
                            yes: {
                                text: 'Book',
                                value: true,
                                className: 'btn-danger',
                            }
                        }
                    }).then(function (result) {
                        if (result) {
                            $("#createPaymentLink").modal('show');
                        } else {
                            return false;
                        }
                    });

                } else {

                    $("#createPaymentLink").modal('show');
                }
            })
            .fail(showSubmissionError)
            .always(hideLoading);
    }

    /**
     * Runs when payment modal is fully opened
     */
    paymentLinkModal() {
        const bookingPrice = ($('#K').length > 0) ? $('#K').text().replace(/[^0-9.]/g, '') : $('#payWithLinkAmount').text().replace(/[^0-9.]/g, '');
        const timeSlot = $("#slot_time").val();
        const customerPhone = $("#customer_phone_number").val().trim();
        const customerEmail = $("#customer_email").val().trim();
        const packageName = $('#tour_package').find('option:selected').text();
    
        // Fill customer info and amount
        $(".pl-customer-mobile-no").val(customerPhone);
        $(".pl-customer-email").val(customerEmail);
    
        if (parseFloat(bookingPrice) > 0) {
            $(".pl-amount").val(bookingPrice);
        }
    
        // Construct pax details from selected tourists
        let paxDetails = '';
    
        $("#table-rate-groups").children().each(function () {
            const $row = $(this);
            const paxCount = parseInt($row.find('.tourists').val()) || 0;
            const rateName = $row.find('.rate-for').text().trim();
    
            if (paxCount > 0) {
                paxDetails += `${paxCount} ${rateName} `;
            }
        });

        if (timeSlot != null) {
            // Integrate reason of payment along with all added booking details
            const postData = {
                amount: bookingPrice,
                package_name: packageName,
                slot_time: timeSlot,
                pax_details: paxDetails.trim(),
                page: this.page
            };
        
            $.post(this.getPaymentLinkReasonUrl, postData)
                .done(function (response) {
                    $(".pl-reason").val(response);
                })
                .fail(showSubmissionError)
                .always(hideLoading);

        }
        
    }
    

    /**
     * Validate modal inputs and save payment link as JSON
     */
    validateAndSavePaymentLink() {
        var formId = $("#createPaymentLink").find('form').attr('id');
        const form = document.getElementById(formId);

        // Prevent multiple event bindings on submit
        form.addEventListener('submit', (evt) => {
            evt.preventDefault();

            const bookingPrice = $(".pl-amount").val();
            const reason = $(".pl-reason").val();
            const customerPhone = $(".pl-customer-mobile-no").val();
            const customerEmail = $(".pl-customer-email").val();

            if (bookingPrice <= 0) {
                return swal('Unable to create payment link.', '', 'warning');
            }

            const paymentLinkData = {
                amount: bookingPrice,
                reason_of_payment: reason,
                customer_email: customerEmail,
                customer_phone_no: customerPhone
            };

            $("#payment_link_data").val(JSON.stringify(paymentLinkData));
            $("#is_payment_link").val("1");

            // if(cartView == 0) {
                $('.create-payment-link')[0].disabled = true;
                $('#payment-form').trigger('submit');
            // } else {
            //     $("#createPaymentLink").modal("hide");
            // }
        });
    }

    togglePaymentLinkSection(selectId, sectionClass) {
        if (isPaymentLink != 1) return;
    
        // var $select = $('#' + selectId);
        var date = $('#tour_slot_date').val();
        var dateCheck = 0;
        // console.log($('.tourist-package-date').text());
        
        var currentDate = new Date();
        var pastDate = new Date(date);

        var expiryData = pastDate.setDate(pastDate.getDate() - paymentLinkDaysNumber.value);
        // var $selectedOption = $select.find('option:selected');
        
        // if (expiryData >= currentDate) {
        //     dateCheck = 1;
        // }
    
        // var isPaymentLinkEnabled = $selectedOption.data('payment-link') || 0;
        var paymentMethod = $('input[name=payment_method]:checked').val();
        // console.log(paymentMethod);
    
        if (isPaymentLink == 1 && paymentMethod === 'Link' /*&& dateCheck == 1*/) {
            $('.' + sectionClass).removeClass('d-none');
            $('.' + 'make-payment').addClass('d-none');
        } else {
            $('.' + sectionClass).addClass('d-none');
            $('.' + 'make-payment').removeClass('d-none');

        }
    }
}

const tourBookingPaymentLink = new TourBookingPaymentLink({ admin: userType });