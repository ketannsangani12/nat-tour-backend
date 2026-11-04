function restoreBooking(bookingId) {
    restoreBookingRoute = restoreBookingRoute.replace(':id', bookingId);
    swal({
        title: "Are you sure?",
        text: "You will be able to restore your cancelled booking by taking this action",
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
                type: "post",
                url: restoreBookingRoute,
                success: function(data) {
                    hideLoading();
                    swal("Booking Restored", 'Page will reload', 'success').then(function() {
                        window.location.reload();
                    });
                },
                error: function(data) {
                    hideLoading();
                }
            });
        }
    });
}

function changePaymentType(historyId) {
    changePaymentTypeRoute = changePaymentTypeRoute.replace(':id', historyId);
    swal({
        title: "Are you sure?",
        text: "You will be able to change the payment type of booking by taking this action",
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
        	if(paymentMethod=='Invoiced' || paymentMethod=='Cash') {
					$('#modal_change_payment_type').modal('show');
					$('#modal-change-payment-type-form').attr('action',changePaymentTypeRoute);
			} else {
				showLoading();

				$.ajax({
				    type: "post",
				    url: changePaymentTypeRoute,
				    success: function(data) {
				        hideLoading();
				        swal("History payment type changed", 'Page will reload', 'success').then(function() {
				            window.location.reload();
				        });
				    },
				    error: function(jqXHR) {
						showSubmissionError(jqXHR);
				        hideLoading();
				    }
				});
			}
        }
    });
}
