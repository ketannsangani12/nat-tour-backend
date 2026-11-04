var cardElement = null;
var cardInputValid = false;
var $radioButtons = $('.payment-condition-item input[type="radio"]');
var selectedCheckInStatus = null;
var isCheckInStatusChanged = false;

function validateAndSubmitForm2(form) {
	//This is for add people feature
	if (typeof isTourBookingInfo !== "undefined"
		&& isTourBookingInfo === true
		&& ($('#modal_affilate').data('call-by-edit-booking-add-people') || $('#modal_admin').data('call-by-edit-booking-add-people'))) {
		var editBookingAddPeopleForm = $('#edit_booking_add_people').find('form');
		editBookingAddPeopleForm.data('validation_check', false);
		validateAndSubmitFormAddPeople(editBookingAddPeopleForm[0], form);
		return false;
	}

	//This is for change people feature
	if (typeof isTourBookingInfo !== "undefined"
		&& isTourBookingInfo === true
		&& ($('#modal_affilate').data('call-by-edit-booking-change-people') || $('#modal_admin').data('call-by-edit-booking-change-people'))) {
		var editBookingChangePeopleForm = $('#edit_booking_change_people').find('form');
		editBookingChangePeopleForm.data('validation_check', false);
		// Use the actually-selected radio value, not the global (original booking) paymentMethod
		var selectedChangePeopleMethod = $('#modal_admin input[name="payment_method"]:checked').val()
			|| $('#modal_affilate input[name="payment_method"]:checked').val()
			|| paymentMethod;
		if (selectedChangePeopleMethod == 'Card') {
			$(editBookingChangePeopleForm).append(`<input type="hidden" name="feature" value="change_people">`);
			if (typeof changePeopleV2 !== 'undefined' && changePeopleV2.prepareCardPaymentForm) {
				changePeopleV2.prepareCardPaymentForm(editBookingChangePeopleForm);
			}
			var feature = 'change_people';
			initTourBookingStripePayment.initializeStripe(editBookingChangePeopleForm[0], feature);
			return false;
		} else {
			validateAndSubmitFormChangePeople(editBookingChangePeopleForm[0], form);
			return false;
		}
	}

	//This is for swipe package feature
	if (typeof isTourBookingInfo !== "undefined"
		&& isTourBookingInfo === true
		&& ($('#modal_affilate').data('call-by-switch-package') || $('#modal_admin').data('call-by-switch-package'))) {
		var switchPackageForm = $('#modal-switch-package').find('form');
		switchPackageForm.data('validation_check', false);
		// Read the admin's current selection from the modal, not the booking's original via
		var selectedSwitchMethod = $('#modal_admin input[name="payment_method"]:checked').val()
			|| $('#modal_affilate input[name="payment_method"]:checked').val()
			|| paymentMethod;
		if (selectedSwitchMethod == 'Card') {
			$(switchPackageForm).append(`<input type="hidden" name="feature" value="switch_package">`);
			var feature = 'switch_package';
			initTourBookingStripePayment.initializeStripe(switchPackageForm[0], feature);
			return false;
		} else {
			validateAndSubmitFormSwitchPackage(switchPackageForm[0], form, true);
			return false;
		}

	}
	if (typeof isTourBookingInfo !== "undefined"
		&& isTourBookingInfo === true
		&& ($('#modal_affilate').data('call-by-check-in-people') || $('#modal_admin').data('call-by-check-in-people'))) {
		isCheckInStatusChanged = true;
		selectedCheckInStatus = $('#modal_admin').data('pendingCheckin');
	}
	
	// Before processing any payment on a pending remaining_payment link, verify
	// the link is still Unpaid — guards against double-payment if it was already
	// paid in another tab or by another admin.
	var $rlInput = $(form).find('input[name="remaining_link_id"]');
	if ($rlInput.length && $rlInput.val() !== '') {
		var rlBreakdownUrl = $('#modal_admin').data('breakdownUrl') || $('#modal_affilate').data('breakdownUrl');
		if (rlBreakdownUrl) {
			validateRemainingLinkAndPay(form, rlBreakdownUrl);
			return false;
		}
	}

	if (paymentLinkActive == 'Yes') {
		activePaymentlinkValidation(form);
		return false;
	}

	if ($('#pay-with-cash1').length && $('#pay-with-cash1')[0].checked) {
		if (($('#dividedBy_2').find('option:selected').val() != 'Please select option' && $('#dividedBy').find('option:selected').val() != undefined) || $('#split-payment1').is(":checked") == true) {
			cashAmount = $('#split_value_field1').val();
		} else {
			cashAmount = totalForCash;
		}

		var paidCashAmount = $('#cash-paid-amount').val();

		if (customChargeTotal > 0 || pricedAddonAdded > 0) {
			if (parseFloat(paidCashAmount) < cashAmount) {
				swal('Please add a paid amount greater than the final booking amount.', '', 'warning');
				return false;
			}
		}
	}
	$('#btn-make-payment1')[0].disabled = false;
	if ($('#pay-with-card1').length && $('#pay-with-card1')[0].checked) {
		$(form).append(`<input type="hidden" name="feature" value="make_payment">`);
		var feature = 'make_payment';
		initTourBookingStripePayment.initializeStripe(form, feature);
		return false;
	}

	submitMakePayment(form);
	return false;
}

function activePaymentlinkValidation(form) {

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
			if ($('#pay-with-cash1').length && $('#pay-with-cash1')[0].checked) {
				if (($('#dividedBy_2').find('option:selected').val() != 'Please select option' && $('#dividedBy').find('option:selected').val() != undefined) || $('#split-payment1').is(":checked") == true) {
					cashAmount = $('#split_value_field1').val();
				} else {
					cashAmount = totalForCash;
				}

				var paidCashAmount = $('#cash-paid-amount').val();

				if (customChargeTotal > 0 || pricedAddonAdded > 0) {
					if (parseFloat(paidCashAmount) < cashAmount) {
						swal('Please add a paid amount greater than the final booking amount.', '', 'warning');
						return false;
					}
				}
			}
			$('#btn-make-payment1')[0].disabled = false;
			if ($('#pay-with-card1').length && $('#pay-with-card1')[0].checked) {
				$(form).append(`<input type="hidden" name="feature" value="make_payment">`);
				var feature = 'make_payment';
				initTourBookingStripePayment.initializeStripe(form, feature);
				return false;
			}

			submitMakePayment(form);
			return false;
		} else {
			return false;
		}
	});
}
function validateRemainingLinkAndPay(form, breakdownUrl) {
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'GET',
		url: breakdownUrl,
	}).done(function (response) {
		hideLoading();
		if (!response.items || response.items.length === 0) {
			swal('Payment Already Completed', 'This pending payment has already been processed. Please refresh the page to see the updated booking status.', 'error');
			return;
		}
		$('#btn-make-payment1').prop('disabled', false);
		if ($('#pay-with-card1').length && $('#pay-with-card1')[0].checked) {
			if (!$(form).find('input[name="feature"]').length) {
				$(form).append('<input type="hidden" name="feature" value="make_payment">');
			}
			initTourBookingStripePayment.initializeStripe(form, 'make_payment');
		} else {
			submitMakePayment(form);
		}
	}).fail(function () {
		hideLoading();
		swal('Error', 'Could not verify payment status. Please try again.', 'error');
	});
}

function submitMakePayment(form) {
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: $(form).serialize(),
	})

		.done(function (response) {

			if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {

				parent.location.href = response.nextAction;

				window.location.href = response.nextAction;

			} else {
				if (typeof (response.paid) != "undefined" && response.paid == '1') {
					hideLoading();
					swal('Error in processing payment', 'Tour booking already paid.', 'error');
					return false;
				} else if (typeof (response.errors) != "undefined") {
					hideLoading();
					swal('Error(s) Occurred', response.errors, 'error');
					showErrorsInHistory(response)
					$('#btn-make-payment')[0].disabled = false;
				} else {
					if(isCheckInStatusChanged && selectedCheckInStatus != null) {
						performCheckInAfterPayment(selectedCheckInStatus);
					}
					if ($('#pay-with-link-cp').is(':checked')) {
						hideLoading();
						swal('Payment link sent successfully.', 'Page will reload', 'success')
							.then(function () { location = location; });
					} else {
						tourBooked(form, response);
					}
				}
			}
		})
		.fail(function (response) {
			hideLoading();
			var message = "Something went wrong with entered card details.";
			if (response.responseJSON?.message) {
				message = response.responseJSON?.message;
			}
			if (response.responseJSON?.errors?.common[0]) {
				message = response.responseJSON?.errors?.common[0];
			}
			swal('Error(s) Occurred', message, 'error');
			$('#btn-make-payment')[0].disabled = false;
		})
		.always(hideLoading);
}

function validateAndSubmitForm5(form, paymentIntentId = "") {
	if (!paymentIntentId) {
		if ($('#pay-with-card2')[0].checked) {
			$(form).append(`<input type="hidden" name="feature" value="make_combo_payment">`);
			var feature = 'make_combo_payment';
			initTourBookingStripePayment.initializeStripe(form, feature);
			return false;
		}
	}
	$('#btn-make-cart-payment')[0].disabled = false;
	makeCartPayment(form);
	return false;
}
function makeCartPayment(form) {
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: $(form).serialize(),
	})
		.done(function (response) {
			if (typeof (response.paid) != "undefined" && response.paid == '1') {
				hideLoading();
				swal('Error in processing payment', 'Tour booking already paid.', 'error');
				return false;
			} else if (typeof (response.errors) != "undefined") {
				hideLoading();
				swal('Error(s) Occurred', response.errors, 'error');
				showErrorsInHistory(response)
				$('#btn-make-cart-payment')[0].disabled = false;
			} else {
				swal('Booking Payment Done', 'Page will reload', 'success')
					.then(function () {
						location = location; // location.reload() keeps data in form which isn't desirable
					});
			}

		})
		.fail(function (response) {
			hideLoading();
			var message = "Something went wrong with entered card details.";
			if (response.responseJSON?.message) {
				message = response.responseJSON?.message;
			}
			if (response.responseJSON?.errors?.common[0]) {
				message = response.responseJSON?.errors?.common[0];
			}
			swal('Error(s) Occurred', message, 'error');
			$('#btn-make-payment')[0].disabled = false;
		})
		.always(hideLoading);

}

$(function () {
	$('[data-toggle="tooltip"]').tooltip();
	$('.select-tour, .select-tour-time, .select-agent-name, #country').select2();

	if ($('#tour_slot_date').val() == '')
		$('#tour_slot_date').val(moment().format('dddd, MMMM Do, Y'));

	$('#tour_slot_date').datepicker({
		dateFormat: 'DD, MM d yy',
		altField: "#hid-tour_slot_date",
		altFormat: "yy-mm-dd",
		minDate: 0,
		onSelect: function () {
			getSlotTimes(1);
		}
	});
	$("#discount_percentage, #discount_fixed_money").prop('disabled', true);

	var $radioButtons = $('.payment-condition-item input[type="radio"]');

	$radioButtons.on('change', function () {
		$radioButtons.each(function () {
			$(this).parent().toggleClass('checked-item', this.checked);
		});

		if ($('#pay-with-card1')[0].checked)
			$('#btn-make-payment1')[0].disabled = false;
		else
			$('#btn-make-payment1')[0].disabled = false;

		if (this.id == 'pay-with-cash')
			$('#card-element').addClass('d-none');
		else if (this.id == 'pay-with-card') // there are other radio buttons so only 'else' can not be specified
			$('#card-element').removeClass('d-none');
	});

	var elements = stripe.elements({
		fonts: [{
			cssSrc: 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'
		}]
	});

	cardElement = elements.create("card", {
		style: {
			base: {
				color: "#464646",
				fontFamily: 'Inter, sans-serif',
				fontSmoothing: "antialiased",
				fontSize: "14px",
				"::placeholder": {
					color: "#32325d"
				},
				backgroundColor: 'transparent',
				lineHeight: '26px'
			},
			invalid: {
				fontFamily: 'Inter, sans-serif',
				color: "#fa755a",
				iconColor: "#fa755a"
			}
		}
	});


	cardElement.on("change", function (event) {
		// Disable the Pay button if there are no card details in the Element
		cardInputValid = event.complete;
		$('#btn-make-payment1')[0].disabled = ($('#pay-with-card1')[0].checked && !cardInputValid);

		if (event.error) {
			$("#card-error")
				.text(event.error.message)
				.removeClass('hidden');
		} else
			$("#card-error").addClass('hidden');
	});
});


function addTaxCols() {
	$('.custom-tax').remove();
	$hdRateGrp = $('#headings-rate-group').children();
	$tblRateGrps = $('#table-rate-groups').children();
	var initRateGrpCols = $hdRateGrp.length;
	var taxCellStart = '<td class="normal custom-tax">';
	var taxCellEnd = '</td>';
	var taxDisplayValue = '';

	for (var a = 0; a < taxes.length; a++) {
		$('<th class="custom-tax">' + taxes[a].name + '</th>')
			.insertBefore($hdRateGrp[$hdRateGrp.length - 4]);

		for (var b = 0; b < $tblRateGrps.length; b++) {
			// if rate is zero for age/rate group then display 0 tax value
			if ($tblRateGrps[b].children[2].innerText == '$0.00')
				taxDisplayValue = (taxes[a].value_type == 'Percent' ? '0.00%' : '$0.00');
			else
				taxDisplayValue = (taxes[a].value_type == 'Percent' ? taxes[a].value + '%' : currencyFormation.symbol + taxes[a].value);

			$(taxCellStart + taxDisplayValue + taxCellEnd)
				.insertBefore($tblRateGrps[b].children[$tblRateGrps[b].children.length - 4]);
		}
	}

	$('.subtotal-price, .total').attr('colspan', initRateGrpCols + taxes.length);
}

function tourBooked(form, response) {
	var isPayWithCard = $('#pay-with-card1')[0].checked;
	// disabling form controls
	$(form)
		.data('booking-id', response.bookingId)


	if (response.error && response.errors.length) {
		$('#btn-make-payment1')[0].disabled = false;

		if (isPayWithCard && response.errors.indexOf('payment') != -1)
			swal('Payment initialization error', 'Tour has been booked but an error occurred when initializing payment on the server. You may try again to make a payment.', 'error');

	} else {
		swal('Booking Payment Done', 'Page will reload', 'success')
			.then(function () {
				location = location; // location.reload() keeps data in form which isn't desirable
			});
	}
}

function validateAndSubmitForm4(form) {
	if ($('#pay-with-card3')[0].checked) {
		var formAction = form.action;
		var parts = formAction.split('/');
		var lastSegment = parts.pop() || parts.pop();
		$(form).append(`<input type="hidden" name="feature" value="change_payment_type">`);
		$(form).append(`<input type="hidden" name="historyId" value="${lastSegment}">`);
		var feature = 'change_payment_type';
		initTourBookingStripePayment.initializeStripe(form, feature);
		return false;
	}
	if ($('#pay-with-cash2')[0].checked) {

		cashAmount = totalForCashChangePaymentType;

		var paidCashAmount = $('#cash-paid-amount2').val();

	}
	var isPayWithCard2 = $('#pay-with-card1')[0].checked;
	$('#btn-make-payment2')[0].disabled = true;
	changeHistoryPaymentType(form);
	return false;
}
function changeHistoryPaymentType(form) {
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: $(form).serialize(),
	}).done(function (response) {
		if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {

			parent.location.href = response.nextAction;

			window.location.href = response.nextAction;

		} else {
			if (typeof (response.paid) != "undefined" && response.paid == '1') {
				hideLoading();
				swal('Error in processing payment', 'Tour booking already paid.', 'error');
				return false;
			} else if (typeof (response.errors) != "undefined") {
				hideLoading();
				swal('Error(s) Occurred', response.errors, 'error');
				showErrorsInHistory(response)
				$('#btn-make-payment2')[0].disabled = false;
			} else {
				if (response.error && response.errors.length) {
					$('#btn-make-payment2')[0].disabled = false;

					if (isPayWithCard2 && response.errors.indexOf('payment') != -1)
						swal('Payment initialization error', 'You may try again to make a payment.', 'error');

				} else {
					if ($('#pay-with-cash2')[0].checked) {
						cashAmount = totalForCashChangePaymentType;
						var paidCashAmount = $('#cash-paid-amount2').val();

						if (Number(cashAmount) > 0 && paidCashAmount > 0 && Number(paidCashAmount) >= Number(cashAmount)) {
							var changeAmount = paidCashAmount - cashAmount;
							if (changeAmount > 0) {
								elemdiv.classList.add("swal-title");
								elemdiv.classList.add("swal-title");
								let message = `Change<br>${currency(changeAmount).format()}`;
								let amountColor = '#15803D';
								elemdiv.innerHTML = `
									<div style="font-size: 26px;
										font-weight: 600;
										color: ${amountColor};
												">
													${message}
												</div>
												<div class="swal-title">
													Booking Payment Done.
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
								swal('History payment type changed', 'Page will reload', 'success')
									.then(function () {
										location = location; // location.reload() keeps data in form which isn't desirable
									});
							}
						} else {
							swal('History payment type changed', 'Page will reload', 'success')
								.then(function () {
									location = location; // location.reload() keeps data in form which isn't desirable
								});
						}
					} else {
						swal('History payment type changed', 'Page will reload', 'success')
							.then(function () {
								location = location; // location.reload() keeps data in form which isn't desirable
							});
					}
				}
			}
		}
	})
		.fail(function (response) {
			hideLoading();
			var message = "Something went wrong.";
			if (response.responseJSON?.message) {
				message = response.responseJSON?.message;
			}
			if (response.responseJSON?.errors?.common[0]) {
				message = response.responseJSON?.errors?.common[0];
			}
			swal('Error(s) Occurred', message, 'error');
			$('#btn-make-payment2')[0].disabled = false;
		})
		.always(hideLoading);
}
function splitPeople(form) {
	var formData = $(form).serialize();
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: splitBookingUrl,
		data: formData,
	})
		.done(function (response) {
			if (typeof (response.errors) != "undefined") {
				hideLoading();
				swal('Error(s) Occurred', response.errors, 'error');
			} else {
				hideLoading();
				var isCalculateAmount = $('#is_calculate_amount').val();
				if (isCalculateAmount == '1' && isMultipleCardPayments == 1) {
					$('#splitBookingUpdate #calculated_amount').html('Total Amount: $' + response.total);
					$('#splitBookingUpdate').modal('toggle');
					$('#edit_booking').modal('hide');
					$('#is_calculate_amount').val('0');
				} else if (response.splitAmount > 0) {
					NewExtendedbookingUrl = NewExtendedbookingUrl.replace(':id', response.bookingId);
					if (window.location != window.parent.location) {
						window.location.replace(NewExtendedbookingUrl + '?modal=1');
					} else {
						window.location.replace(NewExtendedbookingUrl);
					}
				} else {
					swal('Split Booking Done', 'Page will reload', 'success')
						.then(function () {
							NewExtendedbookingUrl = NewExtendedbookingUrl.replace(':id', response.bookingId);
							if (window.location != window.parent.location) {
								window.location.replace(NewExtendedbookingUrl + '?modal=1');
							} else {
								window.location.replace(NewExtendedbookingUrl);
							}
							// location.reload() keeps data in form which isn't desirable
						});
				}
			}
		}).fail(showSubmissionError)
		.always(hideLoading);
}
