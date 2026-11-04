$(function () {
	var cancelBookingUrl = $("#cancel-booking-url").val();
	var cancelPreventNotification = $("#cancel-prevent-notification").val();
	var refundType = $("#refund-type").val();
	var refundPercent = $("#refund-percent").val();
	var partialRefundValue = $("#partial-refund-value").val();
	var isCancelled = $("#is-cancelled").val();

	if (cancelBookingUrl != undefined) {
		if (cancelPreventNotification != undefined && cancelPreventNotification != '') {
			if (cancelPreventNotification) {
				if (isCancelled !== 'Cancelled') {
					swal('Error occurred', cancelPreventNotification, 'error');
				}
			}
		} else {
			if (refundType === undefined && refundPercent === undefined && isCancelled != 'Cancelled') {
				swal('Warning !', 'Please contact tour operator to cancel the booking.', 'warning');
			} else {
				swal({
					title: "Booking Cancellation Confirmation",
					text: "Are you sure you want to cancel the booking?",
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
				}).then(function (result) {
					if (result) {
						showLoading();
	
						$.ajax({
							type: "POST",
							url: cancelBookingUrl,
							data: {
								api: 1,
								cancel_booking: 1,
								refund_type: refundType,
								refund_percent: refundPercent,
								partial_refund_value: partialRefundValue
							},
							success: function (data) {
								if (data.code !== 200) {
									swal("Unable to cancel booking", data.message, 'error');
									hideLoading();
								} else {
									swal({
										title: "Booking Cancelled",
										text: data.message,
										icon: "success",
									}).then(function () {
										location.reload();
									})
									hideLoading();
								}
							},
							error: function (data) {
								hideLoading();
								showSubmissionError(data);
							}
						});
					}
				});
			}
		}
	}
});
