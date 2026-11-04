var totalCustomChargeAmount = 0;
var customChargeFormData = [];

$('#addCustomCharge').on('click', function (e) {
	$("#is_taxed").prop('checked', true);
})

$('#bookingAddCustomCharge').on('hide.bs.modal', function (e) {
	$("#customChargeBooking").modal('show');
}).on('show.bs.modal', function (e) {
	if ($('#custom_charge_value_booking').val() == 0) {
		swal('Sorry!', 'Please at least add a custom charge greater than zero.', 'warning');
		$('#submitCustomCharge')[0].disabled = false;
		return false;
	}
	$("#customChargeBooking").modal('hide');

	if (isOtaBookingCustomCharge) {
		// Always reopen clean: an earlier click on Cash/Card in a previous attempt (even one
		// abandoned by clicking outside the modal) must not stick — otherwise the branches below
		// (written for single-method bookings) would permanently hide the other option via .hide().
		paymentMethod = '';
		$('#bookingAddCustomCharge input[name="payment_method"]').prop('checked', false);
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-cash"]').removeClass('checked-item');
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-card"]').removeClass('checked-item');
		refreshOtaCustomChargeAmounts();
		// Modal #1's submit handler already wrote a fee-less "Total payment" (paymentMethod
		// wasn't known yet at that point) — clear it so nothing misleading shows before staff
		// actually pick Cash or Card; the change handler below fills in the correct total then.
		$('.custom-charge-total').text('');
		return;
	}

	if (paymentMethod == 'Cash') {
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-card"]').hide();
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-billable"]').hide();
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-cash"]').addClass('checked-item');
		$('#bookingAddCustomCharge .card-details').hide();
		$('#bookingAddCustomCharge #custom-charge-pay-with-cash').prop('checked', true);
	} else if (paymentMethod == 'Card') {
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-cash"]').hide();
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-billable"]').hide();
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-card"]').addClass('checked-item');
		$('#bookingAddCustomCharge #custom-charge-pay-with-card').prop('checked', true);
	} else if (paymentMethod == 'Invoiced') {
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-cash"]').hide();
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-card"]').hide();
		$('#bookingAddCustomCharge label[for="custom-charge-pay-with-billable"]').addClass('checked-item');
		$('#bookingAddCustomCharge .card-details').hide();
		$('#bookingAddCustomCharge #custom-charge-pay-with-billable').prop('checked', true);
	}
});

// OTA bookings show Cash and Card side by side, so — unlike a single-method booking,
// where only one `.custom-charge-amount` span ever exists — both spans share that same
// class here. Each needs its OWN preview shown at once (Cash adds the service-commission
// fee only when the Cash Fees setting is on; Card always adds it when taxable), not a
// single value that flips to whichever radio happens to be selected.
function refreshOtaCustomChargeAmounts() {
	var customCharge = parseFloat($('#custom_charge_value_booking').val()) || 0;
	var isTaxable = $('#is_taxed:checked').val() ? 1 : 0;
	var cashFee = (isTaxable == 1 && isCashFees == 1) ? roundout((customCharge * customChargeServiceCommission / 100), 2) : 0;
	var cardFee = (isTaxable == 1) ? roundout((customCharge * customChargeServiceCommission / 100), 2) : 0;
	var cashDisplay = customCharge > 0 ? currency(customCharge + parseFloat(cashFee)).format() : '';
	var cardDisplay = customCharge > 0 ? currency(customCharge + parseFloat(cardFee)).format() : '';

	$('label[for="custom-charge-pay-with-cash"] .custom-charge-amount').text(cashDisplay);
	$('label[for="custom-charge-pay-with-card"] .custom-charge-amount').text(cardDisplay);
}

// Keep `paymentMethod` in sync with whichever one staff actually pick, so the existing
// Cash/Card branches elsewhere (fee calc, Stripe-vs-plain-ajax submit routing) work.
$(document).on('change', '#bookingAddCustomCharge input[name="payment_method"]', function () {
	if (!isOtaBookingCustomCharge) {
		return;
	}
	paymentMethod = $(this).val();
	var customCharge = $('#custom_charge_value_booking').val();
	var isTaxable = $('#is_taxed:checked').val() ? 1 : 0;
	customChargeFinalAmount(customCharge, isTaxable);
	refreshOtaCustomChargeAmounts();

	// The total/fees stashed in the hidden fields modal #1 populated were computed before
	// staff had picked Cash/Card, so they never included a fee. Recompute above just
	// refreshed those hidden fields — now refresh the "Total payment" row (the amount
	// that will actually be charged for whichever method is now selected) from them.
	totalCustomChargeAmount = $('.custom-charge-total-amount').val();
	if (totalCustomChargeAmount > 0) {
		$('.custom-charge-total').text(`${currency(parseFloat(totalCustomChargeAmount)).format()}`);
	}
	var totalCustomChargeFees = $('.custom-charge-fees').val();
	backendCardReaderInfo.getTotal(totalCustomChargeAmount);
	backendCardReaderInfo.getFees(totalCustomChargeFees);
});

$('#submitCustomCharge').on('click', function () {
	if ($('#custom_charge_value_booking').val() == '') {
		swal('Sorry!', 'Please enter Amount.', 'warning');
		return false;
	}

	if ($('#custom_charge_comment').val() == '' || !$.trim($('#custom_charge_comment').val())) {
		swal('Sorry!', 'Please add a note.', 'warning');
		return false;
	}

	totalCustomChargeAmount = $(".custom-charge-total-amount").val();

	customChargeFormData = {
		custom_charge_amount: $("#custom_charge_value_booking").val(),
		is_taxed: $('#is_taxed:checked').val() ? 1 : 0,
		comment: $("textarea#custom_charge_comment").val(),
		paymentMethod: paymentMethod
	};

	if (paymentMethod != 'Pay Later') {
		var pendingAmount = parseFloat($('#customChargeBooking').data('pending-amount')) || 0;
		var newAmount = parseFloat(totalCustomChargeAmount) || 0;
		var combinedAmount = newAmount + pendingAmount;

		$('#bookingAddCustomCharge').modal('show');

		if (pendingAmount > 0) {
			// Show combined total across all payment options
			$(".custom-charge-amount").text(currency(combinedAmount).format());
			$(".custom-charge-total").text(currency(combinedAmount).format());
			// Show pending summary row and "i" button in header
			$('#pending-items-summary').removeClass('hidden');
			$('#pending-items-amount').text(currency(pendingAmount).format());
			$('#btn-custom-charge-payment-breakdown').removeClass('hidden');
			// Set combined_charge_total for backend PI creation
			$('#combined-charge-total').val(combinedAmount.toFixed(2));
		} else {
			$(".custom-charge-amount").text(currency(newAmount).format());
			$(".custom-charge-total").text(currency(newAmount).format());
			$('#pending-items-summary').addClass('hidden');
			$('#btn-custom-charge-payment-breakdown').addClass('hidden');
			$('#combined-charge-total').val('0');
		}

		var totalCustomChargeFees = $(".custom-charge-fees").val();
		backendCardReaderInfo.getTotal(pendingAmount > 0 ? combinedAmount : totalCustomChargeAmount);
		backendCardReaderInfo.getFees(totalCustomChargeFees);
	} else {
		showLoading();
		$("#customChargeForm").submit();
	}

	return false;
});

$('#is_taxed').change(function () {
	var isTaxable = 0;
	if (this.checked) {
		isTaxable = 1;
	}
	$(this).val(isTaxable);
	var customCharge = $("#custom_charge_value_booking").val();
	var customChargeFees = $(".custom-charge-fees").val();
	var field_type = isTaxable + '_' + customCharge;
	data = {
		isTaxable: isTaxable,
		customCharge: customCharge,
		customChargeFees: customChargeFees,
		field_type: field_type,
		request_type: 'Tax On Custom Charge',
	}
	var inArray = $.inArray(field_type, fieldRequestPermissions);
	if (inArray !== -1) {
		taxOnCustomChargePermission = "Yes";
	}
	if (taxOnCustomChargePermission == 'No' && userType != 'Affiliate') {
		requestType(data, '#customChargeBooking');
		return false;
	}
	customChargeFinalAmount(customCharge, isTaxable);
});

$('#custom_charge_value_booking').keyup(function () {
	var customCharge = $(this).val();
	var isTaxable = $("#is_taxed:checked").val() ? 1 : 0;
	customChargeFinalAmount(customCharge, isTaxable);
});

function customChargeFinalAmount(customCharge, isTaxable) {
	var finalAmount = 0;

	if (paymentMethod == "Cash") {
		if (isTaxable == 1 && isCashFees == 1) {
			finalAmount = roundout((customCharge * customChargeServiceCommission / 100), 2);
		}
	} else if (paymentMethod == "Card" && isTaxable == 1) {
		finalAmount = roundout((customCharge * customChargeServiceCommission / 100), 2);
	} else if (isOtaBookingCustomCharge && isTaxable == 1 && isCashFees == 1) {
		// OTA booking, Cash/Card not chosen yet (this runs on the first "Amount to be paid"
		// screen, before the next modal's radio pick). When Cash Fees is on, the fee is
		// certain to apply no matter which of Cash/Card gets picked next — both add it — so
		// it's already knowable now, not actually ambiguous. Only when Cash Fees is off does
		// the outcome genuinely depend on that choice (Cash = no fee, Card = fee), and this
		// branch is skipped, leaving finalAmount at 0 (base charge only) for that case.
		finalAmount = roundout((customCharge * customChargeServiceCommission / 100), 2);
	}

	if (customCharge == 0) {
		$(".final-amount").html(`${currency(0.00).format()}`);
		$(".custom-charge-fees").val('0.00');
		$(".custom-charge-total-amount").val('0.00');
	} else {
		var totalCustomChargeAmount = parseFloat(customCharge) + parseFloat(finalAmount);
		$(".custom-charge-fees").val(finalAmount);
		$(".final-amount").html('<b>' + currency(totalCustomChargeAmount).format() + '</b>');
		$(".custom-charge-total-amount").val(totalCustomChargeAmount);
	}
}

$('#bookingAddCustomCharge').on('change', 'input[name="payment_method"]', function () {
	var btnLabel = $(this).attr('id') === 'custom-charge-pay-with-link'
		? 'Send Payment Link'
		: 'Make a Payment';
	$('#btn-make-custom-charge').val(btnLabel);
});

function showMakePaymentForCustomCharge(form, paymentIntentId = "") {
	if ($('#custom-charge-pay-with-link').is(':checked')) {
		var customChargeFees = $(".custom-charge-fees").val();
		$('#btn-make-custom-charge')[0].disabled = true;
		showLoading();
		$.ajax({
			headers: { Accept: 'application/json' },
			dataType: 'json',
			type: 'POST',
			url: form.action,
			data: $("#modal-custom-charge-form").serialize()
				+ '&custom_charge_amount=' + customChargeFormData.custom_charge_amount
				+ '&taxable=' + customChargeFormData.is_taxed
				+ '&comment=' + customChargeFormData.comment
				+ '&custom_charge_fees=' + customChargeFees
				+ '&custom_charge_total=' + totalCustomChargeAmount
				+ '&is_payment_link=1',
		}).done(function (response) {
			hideLoading();
			$('#bookingAddCustomCharge').modal('hide');
			swal(response.message || 'Payment link sent successfully.', 'Page will reload', 'success')
				.then(function () { location = location; });
		}).fail(function (jqXHR) {
			hideLoading();
			showSubmissionError(jqXHR);
			$('#btn-make-custom-charge')[0].disabled = false;
		});
		return false;
	}

	if (isOtaBookingCustomCharge && paymentMethod != 'Cash' && paymentMethod != 'Card') {
		swal('Sorry!', 'Please select Cash or Card to collect this custom charge.', 'warning');
		$('#btn-make-custom-charge')[0].disabled = false;
		return false;
	}

	var changeAmount = 0;
	var formData = $("#modal-custom-charge-form").serialize();
	var customChargeFees = $(".custom-charge-fees").val();
	var customChargeData = '&custom_charge_amount=' + customChargeFormData.custom_charge_amount + '&taxable=' + customChargeFormData.is_taxed + '&comment=' + customChargeFormData.comment + '&custom_charge_fees=' + customChargeFees + '&custom_charge_total=' + totalCustomChargeAmount;
	var paidAmt = $("#bookingAddCustomCharge #cash-paid-amount").val();
	if (paymentMethod == 'Card' && totalCustomChargeAmount >= 0) {
		var form_data = (document.getElementById("modal-custom-charge-form"));
		var feature = 'custom_charge';
		// Remove existing hidden inputs to avoid duplicates
		$(form_data).find('input[name="custom_charge_fees"][type="hidden"]').remove();
		$(form_data).find('input[name="custom_charge_amount"][type="hidden"]').remove();
		$(form_data).find('input[name="custom_charge_total"][type="hidden"]').remove();
		$(form_data).find('input[name="taxable"][type="hidden"]').remove();
		$(form_data).find('input[name="feature"][type="hidden"]').remove();
		// Append hidden inputs
		$(form_data).append(`<input type="hidden" name="custom_charge_fees" value="${customChargeFees}">`);
		$(form_data).append(`<input type="hidden" name="custom_charge_amount" value="${customChargeFormData.custom_charge_amount}">`);
		$(form_data).append(`<input type="hidden" name="custom_charge_total" value="${totalCustomChargeAmount}">`);
		$(form_data).append(`<input type="hidden" name="taxable" value="${customChargeFormData.is_taxed}">`);
		$(form_data).append(`<input type="hidden" name="feature" value="custom_charge">`);
		if (!paymentIntentId) {
			initTourBookingStripePayment.initializeStripe(form_data, feature, customChargeData);
			return false;
		} else {
			$(form_data).append(`<input type="hidden" name="payment_intent_id" value="${paymentIntentId}">`);
			submitCustomCharge(form_data, customChargeData);
		}

	}

	if (paymentMethod == 'Cash') {
		if (paidAmt.length > 0) {
			if (parseFloat(paidAmt) < totalCustomChargeAmount) {
				swal('Sorry!', 'Please add a paid amount greater than the custom charge amount.', 'warning');
				return false;
			}
			if (paidAmt > totalCustomChargeAmount) {
				changeAmount = paidAmt - totalCustomChargeAmount;
			}
		}
	}

	$('#btn-make-custom-charge')[0].disabled = true;
	if (paymentMethod != 'Card') {
		showLoading();
		$.ajax({
			headers: { Accept: 'application/json' },
			dataType: 'json',
			type: 'POST',
			url: form.action,
			data: formData + customChargeData,
		}).done(function (response) {
			$('#customChargeBooking').modal('hide');
			if (paymentMethod == 'Pay Later' && totalCustomChargeAmount > 0) {
				swal(response.message, 'Page will reload', 'success')
					.then(function () {
						location = location;
					});
			} else {
				if (totalCustomChargeAmount >= 0) {
					var pathname = window.location.href;
					var spliturlCheck = pathname.slice(-7);
					var splitslice = spliturlCheck.slice(0, 5);

					if (paymentMethod == 'Card') {
						if (response.errors && response.errors.length) {
							$('#btn-make-custom-charge')[0].disabled = false;

							if (response.errors.indexOf('payment') != -1)
								swal('Payment initialization error', response.errors_message, 'error');

							if (typeof (response.errors) != "undefined") {
								hideLoading();
								if (typeof (response.errors_message) != "undefined") {
									response.errors = response.errors_message;
								}
								swal('Error(s) Occurred', response.errors, 'error');
								showErrorsInHistory(response);
								$('#btn-make-custom-charge')[0].disabled = false;
							}

						} else {
							if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
								window.location.href = response.nextAction.redirect_to_url.url;
							} else {
								$('#bookingAddCustomCharge').modal('hide');
								swal(response.message, 'Page will reload', 'success')
									.then(function () {
										if (splitslice == "split") {
											pathname = pathname.split('?')[0];
											location = pathname;
										} else {
											location = location;
										}

									});
							}
						}
					} else {
						if (paymentMethod == 'Cash') {
							if (totalCustomChargeAmount > 0) {
								if (changeAmount > 0) {
									var elemdiv = document.createElement("div");
									elemdiv.classList.add("swal-title");
									let message = `Change<br>${currency(changeAmount).format()}`;
									let amountColor = '#15803D';
									elemdiv.innerHTML = `
												<div style="
													font-size: 26px;
													font-weight: 600;
													color: ${amountColor};
												">
													${message}
												</div>
												<div class="swal-title">
													${response.message}
												</div>
											`;
									swal({
										icon: "success",
										content: elemdiv
									})
										.then(function () {
											location = location;
										});
								} else {
									var message = response.message;
									swal(message, 'Page will reload', 'success')
										.then(function () {
											location = location;
										});
								}

							}
						} else {
							swal('The custom charge added successfully to the booking.', 'Page will reload', 'success')
								.then(function () {
									location = location;
								});
						}
					}
				}
			}
		})
			.fail(function (jqXHR) {
				hideLoading();
				showSubmissionError(jqXHR);
				$('#btn-make-custom-charge')[0].disabled = false;
			})
			.always(hideLoading);
	}
	return false;
}

$("#cc_partial_refund").click(function () {
	var radioValue = $("input[name='refund_type']:checked").val();
	if (radioValue) {
		$("#partial_refund_cc").attr("hidden", false);
		$("#partial_refund_value_cc").attr("required", true);
	}
});

$("#cc1, #cc2, #cc3").click(function () {
	var radioValue = $("input[name='refund_type']:checked").val();
	if (radioValue) {
		$("#partial_refund_cc").attr("hidden", true);
		$("#partial_refund_value_cc").attr("required", false);
	}
});

/* Refund Custom Charge Start */
$(".refund-cc").click(function (e) {
	if ($('.custom-charge-refund:checked').length == 0) {
		swal('Sorry!', 'Please select at least one custom charge you want to refund.', 'warning');
		return false;
	}

	var hasPending = false;
	var hasPaid    = false;
	$('.custom-charge-refund:checked').each(function () {
		if ($(this).data('status') === 'Open') {
			hasPending = true;
		} else {
			hasPaid = true;
		}
	});
	var hasMixed = hasPending && hasPaid;

	// Reset all options first
	$('#cc1').closest('.form-check-wrap').show();
	$('#cc2').closest('.form-check-wrap').show();
	$('input[name="refund_type"]').prop('checked', false);
	$('#cc-pending-note').addClass('hidden');

	if (hasMixed) {
		// Mixed: refund options apply to paid charge(s); pending charge is auto-cancelled
		$('#cc-pending-note').removeClass('hidden');
	} else if (hasPending) {
		// All pending: only "Don't Refund" is valid — the payment link will be cancelled
		$('#cc1').closest('.form-check-wrap').hide();
		$('#cc2').closest('.form-check-wrap').hide();
		$('#cc3').prop('checked', true);
	}

	$('#refundCustomChargeBooking').modal('toggle');
});

$('#refund_custom_charge').on('click', function (e) {
	if ($('.cc-refund-radio:checked').length == 0) {
		swal('Sorry!', 'Please select at least one refund option to proceed further.', 'warning');
		return false;
	}
	// $(this).prop('disabled', true);
	// showLoading();

	if (typeof isTourBookingInfo !== "undefined" && isTourBookingInfo === true) {
		var form = $('#custom-form')[0];
		refundCustomChargeBooking(form);
		return false;
	}
	$('#custom-form').submit();
});

$("#cc_partial_refund").click(function () {
	var radioValue = $("input[name='refund_type']:checked").val();
	if (radioValue) {
		$("#partial_refund_cc").attr("hidden", false);
		$("#partial_refund_value_cc").attr("required", true);
	}
});

$("#cc1, #cc2, #cc3").click(function () {
	var radioValue = $("input[name='refund_type']:checked").val();
	if (radioValue) {
		$("#partial_refund_cc").attr("hidden", true);
		$("#partial_refund_value_cc").attr("required", false);
	}
});

function refundCustomChargeBooking(form) {
	var checkArray = [];
	var refundType = $('#refundCustomChargeBooking').find("input[name='refund_type']:checked").val();
	var checkboxesList = $('.custom-charge-refund');
	var partialRefundVal = $("#partial_refund_value_cc").val();
	formData = $(form).serialize();

	if (refundType == "partial_refund") {
		if (partialRefundVal.length == 0) {
			swal('Sorry!', 'Please enter an amount you want to partially refund.', 'warning');
			return false;
		}
	}

	checkboxesList.each(function (index, value) {
		if (checkboxesList[index].checked == true) {
			checkArray.push(value.value);
		}
	});

	checkArray = JSON.stringify(checkArray);
	var formData = $(form).serializeArray();
	dataObj = {};
	$(formData).each(function (i, field) {
		dataObj[field.name] = field.value;
	});
	var field_type = 'Refund Custom Charge_' + dataObj['custom_charge_ids[]'] + '_' + refundType;
	var inArray = $.inArray(field_type, fieldRequestPermissions);
	if (inArray !== -1) {
		refundCustomChargePermission = "Yes";
	}
	if (refundCustomChargePermission == 'No') {
		formData.push({ name: "field_type", value: field_type });
		formData.push({ name: "request_type", value: 'Refund Custom Charge' });
		formData.push({ name: "refund_type", value: refundType });
		requestType(formData, '#refundCustomChargeBooking');
		return false;
	}

	if (refundCustomChargePermission == "Yes") {
		showLoading();

		$.ajax({
			headers: { Accept: 'application/json' },
			dataType: 'json',
			type: 'POST',
			url: form.action,
			data: formData + '&custom_charge_ids=' + checkArray + '&payment_method=' + paymentMethod + '&refund_type=' + refundType + '&partial_refund_value=' + partialRefundVal
		})
			.done(function (response) {
				if (paymentMethod == 'Card') {
					var pathname = window.location.href;
					var spliturlCheck = pathname.slice(-7);
					var splitslice = spliturlCheck.slice(0, 5);

					if (response.errors && response.errors.length) {
						$('#btn-make-custom-charge')[0].disabled = false;

						if (response.errors.indexOf('payment') != -1)
							swal('Payment initialization error', response.errors_message, 'error');

						if (typeof (response.errors) != "undefined") {
							hideLoading();
							if (typeof (response.errors_message) != "undefined") {
								response.errors = response.errors_message;
							}
							swal('Error(s) Occurred', response.errors, 'error');
							showErrorsInHistory(response);
							$('#btn-make-custom-charge')[0].disabled = false;
						}

					} else {
						if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
							window.location.href = response.nextAction.redirect_to_url.url;
						} else {
							$('#bookingAddCustomCharge').modal('hide');
							// $('#customChargeBooking').modal('hide');
							swal('The custom charge is refunded successfully.', 'Page will reload', 'success')
								.then(function () {
									if (splitslice == "split") {
										pathname = pathname.split('?')[0];
										location = pathname;
									} else {
										location = location;
									}

								});
						}
					}
				} else {
					swal('Booking updated successfully', 'Page will reload', 'success')
						.then(function () {
							location = location;
						});
				}
			})
			.fail(function (jqXHR) {
				hideLoading();
				swal(JSON.parse(jqXHR.responseText), '', 'error');
				$("#refund_custom_charge").disabled = false;
			})
			.always(hideLoading);
	}

	return false;
}

function submitCustomCharge(form, customChargeData) {
	var formData = $(form).serialize();
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: formData + customChargeData,
	}).done(function (response) {
		$('#customChargeBooking').modal('hide');
		if (totalCustomChargeAmount >= 0) {
			var pathname = window.location.href;
			var spliturlCheck = pathname.slice(-7);
			var splitslice = spliturlCheck.slice(0, 5);

			if (paymentMethod == 'Card') {
				if (response.errors && response.errors.length) {
					$('#btn-make-custom-charge')[0].disabled = false;

					if (response.errors.indexOf('payment') != -1)
						swal('Payment initialization error', response.errors_message, 'error');

					if (typeof (response.errors) != "undefined") {
						hideLoading();
						if (typeof (response.errors_message) != "undefined") {
							response.errors = response.errors_message;
						}
						swal('Error(s) Occurred', response.errors, 'error');
						showErrorsInHistory(response);
						$('#btn-make-custom-charge')[0].disabled = false;
					}

				} else {
					if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
						window.location.href = response.nextAction.redirect_to_url.url;
					} else {
						$('#bookingAddCustomCharge').modal('hide');
						swal(response.message, 'Page will reload', 'success')
							.then(function () {
								if (splitslice == "split") {
									pathname = pathname.split('?')[0];
									location = pathname;
								} else {
									location = location;
								}

							});
					}
				}
			}
		}
	})
		.fail(function (jqXHR) {
			hideLoading();
			showSubmissionError(jqXHR);
			$('#btn-make-custom-charge')[0].disabled = false;
		})
		.always(hideLoading);
}

function numbersDecimalOnly(event) {
	const keyCode = (event != undefined) ? event.keyCode : null;
	const excludedKeys = [8, 37, 39, 46, 32, 190, 110, 16, 17];

	if ($('#custom_charge_value_booking').val() != '') {
		if (keyCode == undefined || keyCode == null) {
			if ($.isNumeric($('#custom_charge_value_booking').val()) == false) {
				swal('Only decimal and numbers are allowed.', '', 'warning');
				return false;
			}
		} else {
			if (!((keyCode >= 48 && keyCode <= 57) || (keyCode >= 96 && keyCode <= 105) || (excludedKeys.includes(keyCode)))) {
				swal('Only decimal and numbers are allowed.', '', 'warning');
				return false;
			}
		}
	}

	return true;
}

$(document).on('keyup change', '#custom_charge_value_booking', function (event) {
	numbersDecimalOnly(event);
})
/* Refund Custom Charge End */

/* ── Pending Payment Breakdown popup ─────────────────────────────────────────
   Created dynamically and appended to <body> so position:fixed always works
   correctly, regardless of any CSS-transform on parent containers in the page.
   ─────────────────────────────────────────────────────────────────────────── */
function buildBreakdownModal() {
	return $(
		'<div id="remainingItemsBreakdownModal"'
		+ ' style="position:fixed;top:0;left:0;z-index:1070;width:100%;height:100%;'
		+ 'overflow-x:hidden;overflow-y:auto;background:rgba(0,0,0,0.75);display:block">'
		+ '<div style="max-width:400px;width:calc(100% - 40px);margin:20px auto;'
		+ 'min-height:calc(100% - 40px);display:flex;align-items:center;">'
		+ '<div style="background:#fff;width:100%;border-radius:12px;overflow:hidden;'
		+ 'box-shadow:0 20px 60px rgba(0,0,0,0.3);">'
		+ '<div style="padding:16px 20px;background:#F9FAFB;display:flex;align-items:center;'
		+ 'justify-content:space-between;border-bottom:1px solid #E5E7EB;">'
		+ '<h6 style="font-size:1rem;font-weight:600;color:#111827;margin:0">Pending Payment Breakdown</h6>'
		+ '<button type="button" id="closeBreakdownModal" aria-label="Close"'
		+ ' style="background:none;border:none;cursor:pointer;padding:4px;opacity:.6;line-height:1">'
		+ '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">'
		+ '<path d="M12 4L4 12" stroke="#020817" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
		+ '<path d="M4 4L12 12" stroke="#020817" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>'
		+ '</svg>'
		+ '</button>'
		+ '</div>'
		+ '<div id="remaining-items-breakdown-body" style="padding:12px 16px"></div>'
		+ '</div></div></div>'
	);
}

function hideBreakdownModal() {
	$('#remainingItemsBreakdownModal').remove();
}

function loadBreakdownItems(url) {
	/* Remove any stale instance before creating a fresh one */
	hideBreakdownModal();

	var $modal = buildBreakdownModal();
	$('body').append($modal);

	var $body = $('#remaining-items-breakdown-body');
	$body.html('<div style="text-align:center;font-size:.875rem;color:#9CA3AF;padding:8px 0">Loading&hellip;</div>');

	$.ajax({
		headers: { Accept: 'application/json' },
		type: 'GET',
		url: url,
	}).done(function (response) {
		if (!response.items || response.items.length === 0) {
			$body.html('<p style="font-size:.875rem;color:#6B7280;margin:0;padding:8px 0">No pending items found.</p>');
			return;
		}

		var rows = response.items.map(function (item) {
			var byLine = item.created_by
				? '<span style="font-size:.75rem;color:#9CA3AF;display:block">By: ' + item.created_by + '</span>'
				: '';
			return '<li style="display:flex;justify-content:space-between;align-items:flex-start;'
				+ 'padding:8px 0;border-bottom:1px solid #F3F4F6;gap:16px">'
				+ '<div>'
				+ '<span style="font-size:.875rem;font-weight:500;color:#1F2937;display:block">' + item.type + '</span>'
				+ '<span style="font-size:.75rem;color:#9CA3AF">' + (item.created_at || '') + '</span>'
				+ byLine
				+ '</div>'
				+ '<span style="font-size:.875rem;font-weight:600;color:#1F2937;white-space:nowrap">' + item.amount + '</span>'
				+ '</li>';
		}).join('');

		$body.html(
			'<ul style="list-style:none;padding:0;margin:0">' + rows + '</ul>'
			+ '<div style="display:flex;justify-content:space-between;font-weight:700;font-size:1rem;'
			+ 'margin-top:12px;padding-top:12px;border-top:2px solid #D1D5DB">'
			+ '<span>Total</span><span>' + response.total + '</span>'
			+ '</div>'
		);
	}).fail(function () {
		$body.html('<p style="font-size:.875rem;color:#EF4444;margin:0">Could not load breakdown. Please try again.</p>');
	});
}

/* "i" button inside the Make Payment Admin modal (remaining_payment link breakdown) */
$(document).on('click', '#btn-cp-payment-breakdown, #btn-cp-payment-breakdown-inline', function (e) {
	e.preventDefault();
	e.stopPropagation();
	var url = $('#modal_admin').data('breakdown-url');
	if (!url) return;
	loadBreakdownItems(url);
});

/* "i" button inside the Make Payment modal */
$(document).on('click', '#btn-custom-charge-payment-breakdown, #btn-custom-charge-payment-breakdown-inline', function (e) {
	e.preventDefault();
	e.stopPropagation();
	var url = $('#bookingAddCustomCharge').data('breakdown-url');
	if (!url) return;
	loadBreakdownItems(url);
});

/* "i" button inside the Add-on charge modal */
$(document).on('click', '#btn-addon-charge-payment-breakdown', function (e) {
	e.preventDefault();
	e.stopPropagation();
	var url = $('#modal_addon_edit_charge').data('breakdown-url');
	if (!url) return;
	loadBreakdownItems(url);
});

/* "i" button on the booking page (Pending Payment Link row) */
$(document).on('click', '.btn-remaining-items-info', function (e) {
	e.preventDefault();
	e.stopPropagation();
	var url = $(this).data('url');
	if (!url) return;
	loadBreakdownItems(url);
});

/* Close — X button, backdrop click, or parent Make Payment modal closing */
$(document).on('click', '#closeBreakdownModal', function (e) {
	e.stopPropagation();
	hideBreakdownModal();
});
$(document).on('click', '#remainingItemsBreakdownModal', function (e) {
	if ($(e.target).is('#remainingItemsBreakdownModal')) {
		hideBreakdownModal();
	}
});
$(document).on('hide.bs.modal', '#bookingAddCustomCharge', function () {
	hideBreakdownModal();
});