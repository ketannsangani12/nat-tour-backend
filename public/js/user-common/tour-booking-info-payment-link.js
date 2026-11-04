class TourBookingInfoPaymentLink {
    /**
     * This is called from the template blade booking info view for bundle bookings
     * @param {*} props 
     * @returns 
     */

    constructor(props) {
        this.props = props;
        this.admin = (props.admin === 'Admin') ? '/admin' : '';
        this.bookingId = bookingId;
        this.customerEmail = custEmail;
        this.customerPhoneNo = custPhoneNo;
        this.tourSlotId = tourSlotId;
        this.isPaymentLinkButtonVisible = isPaymentLinkButtonVisible;
        this.getPaymentLinkReasonUrl = getPaymentLinkReasonUrl;
        this.expiryDateOverride = expiryDateOverride;
        this.checkStatus = checkStatus;

        this.bindEvents();
    }

    /**
     * Bind event listeners to DOM
     */
    bindEvents() {
        $(document)
            .off("click", "#initPaymentLinkModal")
            .on("click", "#initPaymentLinkModal", this.initPaymentLinkModal.bind(this));

        // $(document)
        //     .off("click", ".create-payment-link")
        //     .on("click", ".create-payment-link", this.validateAndSavePaymentLink.bind(this));

        $(document)
            .off("click", ".cancel-payment-link")
            .on("click", ".cancel-payment-link", this.cancelPaymentLink.bind(this));

        // $(document)
        //     .off("shown.bs.modal", "#createPaymentLink")
        //     .on("shown.bs.modal", "#createPaymentLink", this.paymentLinkModal.bind(this));

        $(document)
            .off("shown.bs.modal", "#paymentLinkDetails")
            .on("shown.bs.modal", "#paymentLinkDetails", this.paymentLinkDetails.bind(this)); 

        $(document)
            .off('click', ".link-action")
            .on("click", ".link-action", this.paymentLinkAction.bind(this));       
    }

    /**
     * Initialize and validate modal input before showing payment link modal
     */
    initPaymentLinkModal() {
        const customerPhone = this.customerPhoneNo;
        const customerEmail = this.customerEmail;
        const bookingPrice = $("#span_card").text().replace(/[^0-9.]/g, '');
        let modalId = $("#initPaymentLinkModal").closest('.modal').attr('id');

        showLoading();

        $(".pl-amount").val(bookingPrice);
        $(".pl-customer-mobile-no").val(customerPhone);
        $(".pl-customer-email").val(customerEmail);

        $('#' + modalId).data('prevent-hide-callback', true).modal('hide');
        // $('#createPaymentLink').data('call-by-payment-link', true).modal('show');
        $('#paymentLinkDetails').data('call-by-payment-link', true).modal('show');

        hideLoading();
    }

    /**
     * Runs when payment detaisl model is fully opened
     */

    paymentLinkDetails() {
        var table = $('#detailsPaymentLink').DataTable();
        table.clear().draw();
        const params = {
            order_id: orderId,
        };
         var table = $('#detailsPaymentLink')
           .DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                bDestroy: true,
                searching: false,
                ordering: false,
                paging: false,
                info: false,
                bLengthChange: false,
                pageLength: 200,
                ajax:  {
                    method: 'GET',
                    url: getActivePaymentLinkUrl,
                    data: params,
                },
                columns: [
                    {
                        data: 'order_id',
                        name: 'order_id',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'amount',
                        name: 'amount',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'expiry_date',
                        name: 'expiry_date',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'override_status',
                        name: 'override_status',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'status',
                        name: 'status',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'updated_at',
                        name: 'updated_at',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'created_by',
                        name: 'created_by',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'action',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs text-center',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

        var table = $('#InActivePaymentLink').DataTable();
        table.clear().draw();

         var table = $('#InActivePaymentLink')
           .DataTable({
                processing: true,
                serverSide: true,
                responsive: true,
                bDestroy: true,
                searching: false,
                ordering: false,
                paging: false,
                info: false,
                bLengthChange: false,
                pageLength: 200,
                ajax:  {
                    method: 'GET',
                    url: getInActivePaymentLinkUrl,
                    data: params,
                },
                columns: [
                    {
                        data: 'order_id',
                        name: 'order_id',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'amount',
                        name: 'amount',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'expiry_date',
                        name: 'expiry_date',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'override_status',
                        name: 'override_status',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'status',
                        name: 'status',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'updated_at',
                        name: 'updated_at',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                    {
                        data: 'created_by',
                        name: 'created_by',
                        className: 'px-4 py-2.5 border border-[#E5E7EB] border-t-0 !shadow-none font-normal text-black text-xs',
                    },
                ]
            });
    }

    /**
     * Runs when payment action is performed
     */
    paymentLinkAction(event) {
        var type = $(event.target).data('type');
        var id = $(event.target).data('id');
        var eventResponce = event;
        const params = {
            order_id: orderId,
            type: type,
            id:id
        };
        if (type == "cancel") {
             swal({
                title: "Are you sure?",
                text: "Cancel the existing payment link? Any add-ons still waiting for payment on this link will also be removed from the booking.",
                icon: "warning",
                buttons: {
                    no: {
                        text: 'No',
                        value: false,
                    },
                    yes: {
                        text: 'Yes',
                        className: 'btn-danger',
                    }
                }
            }).then(function(result) {
                if (result) {
                    showLoading();
                    $.ajax({
                        headers: { Accept: 'application/json' },
                        dataType: 'json',
                        type: 'POST',
                        url: paymentLinkStatusUpdateUrl,
                        data: params,
                    })
                    .done(function(data) {
                        swal("Cancelled the payment link", 'Page will reload', 'success').then(function() {
                            // Reload so the add-ons / payment sections reflect any pending add-ons removed with the link.
                            location.reload();
                        });
                    })
                    .fail(showSubmissionError)
                    .always(hideLoading);
                }
            });

        } else {
            showLoading();
            $('#paymentLinkDetails').data('call-by-payment-link', true).modal('hide');
            $.post(this.checkStatus, params)
            .done(function (response) {
                if (response == 1) {
                     swal({
                        title: 'An active payment link',
                        text: 'Are you still want to proceed? Existing payment link will be cancelled',
                        icon: "warning",
                        dangerMode: true,
                        buttons: true,
                        buttons: {
                            no: {
                                text: 'No',
                                value: false,
                            },
                            yes: {
                                text: 'Yes',
                                value: true,
                                className: 'btn-danger',
                            }
                        }
                    }).then(function (result) {
                        if (result) {
                            tourBookingInfoPaymentLink.overrideExpiryDate(eventResponce);
                        } else {
                            return false;
                        }
                    });
                } else {
                    tourBookingInfoPaymentLink.overrideExpiryDate(eventResponce);
                }
            })
            .fail(showSubmissionError)
            .always(hideLoading);   

        }
       
    }

     /**
     * Runs when payment modal is fully opened
     */
    paymentLinkModal() {
        const bookingPrice = $('.pl-amount').val();
        // Construct pax details from selected tourists
        let paxDetails = '';

        $(bookingT).each(function (key, val) {
            const paxCount = val.tourists ;
            const rateName = val.rate_for;
    
            if (paxCount > 0) {
                paxDetails += `${paxCount} ${rateName} `;
            }
        });
    
        // Integrate reason of payment along with all added booking details
        const postData = {
            amount: bookingPrice,
            package_name: packageName,
            slot_time: tourSlotId,
            pax_details: paxDetails.trim(),
            page: 'create_booking',
            order_id: orderId
        };
    
        $.post(this.getPaymentLinkReasonUrl, postData)
            .done(function (response) {
                $(".pl-reason").val(response.finalMessage);
                $(".pl-amount").val(response.totalAmount);
            })
            .fail(showSubmissionError)
            .always(hideLoading);
    }

    /**
     * Validate modal inputs and save payment link as JSON
     */
    validateAndSavePaymentLink() {
        showLoading();
        let amount = 0;
        let comboPrice   = $('.card-amount3').text();      // e.g. "$456.71"
        let bookingPrice = parseFloat(document.getElementById('hideencard').value); // 295.56

        // remove $ and convert to float
        comboPrice = parseFloat(comboPrice.replace('$', '').trim());
    
        const btn = document.getElementById('combo-payment-button');
        
        if (btn) {
            // Button exists (visible in DOM)
            amount = comboPrice;
        } else {
            // Button does not exist (hidden by Blade condition)
            amount = bookingPrice;
        }

        const reason = $(".pl-reason").val();
        const customerPhone = $(".pl-customer-mobile-no").val();
        const customerEmail = $(".pl-customer-email").val();

        const paymentLinkData = {
            amount: amount,
            reason_of_payment: reason,
            customer_email: customerEmail,
            customer_phone_no: customerPhone,
            booking_id: this.bookingId,
            tour_slot_id: this.tourSlotId
        };
        
            const form = this;

            $('.create-payment-link')[0].disabled = true;

            $.post(createPaymentLinkUrl, paymentLinkData)
                .done(function (response) {
                    console.log(response)
                    if(response) {
                        $("#createPaymentLink").modal("hide");
                        swal('Payment link created and sent successfully.', 'Page will reload.', 'success')
							/*.then(function () {
								location = location;
							});*/
                    }
                })
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                    $('.create-payment-link')[0].disabled = false;
                })
                .always(hideLoading)
    }
    
    /**
     * Reset modal and form
     */
    cancelPaymentLink() {
        $("#createPaymentLink").modal("hide");
    }

    /**
     * Override expiry date 
     */
    overrideExpiryDate(event) {
        var type = $(event.target).data('type');
        var id = $(event.target).data('id');
        const params = {
            order_id: orderId,
            type: type,
            id:id
        };
        showLoading(); 
        $.post(this.expiryDateOverride, params)
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
                            text: 'No',
                            value: false,
                        },
                        yes: {
                            text: 'Yes',
                            value: true,
                            className: 'btn-danger',
                        }
                    }
                }).then(function (result) {
                    if (result) {
                        tourBookingInfoPaymentLink.validateAndSavePaymentLink();
                    } else {
                        return false;
                    }
                });

            } else {

                // $('#createPaymentLink').data('call-by-payment-link', true).modal('show');
                tourBookingInfoPaymentLink.validateAndSavePaymentLink();
            }
        })
        .fail(showSubmissionError)
        .always(hideLoading);
    }
}

const tourBookingInfoPaymentLink = new TourBookingInfoPaymentLink({ admin: userType });