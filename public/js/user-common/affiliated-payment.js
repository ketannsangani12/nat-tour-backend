var cardElement = null;
var cardInputValid = false;
var $radioButtons = $('.payment-condition-item input[type="radio"]');
var cashAmount = 0;
var selectedCheckInStatus = null;
var isCheckInStatusChanged = false;

function validateAndSubmitForm1(form, paymentIntentId = "") {
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
		var selectedChangePeopleMethod = $('#modal_affilate input[name="payment_method"]:checked').val()
			|| $('#modal_admin input[name="payment_method"]:checked').val()
			|| paymentMethod;
		if (selectedChangePeopleMethod == 'Card') {
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
		// Read the affiliate's current selection from the modal, not the booking's original via
		var selectedSwitchMethod = $('#modal_affilate input[name="payment_method"]:checked').val()
			|| $('#modal_admin input[name="payment_method"]:checked').val()
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
		selectedCheckInStatus = $('#modal_affilate').data('pendingCheckin');
	}
	if ($('#pay-with-cash').length && $('#pay-with-cash')[0].checked) {

		if (($('#dividedBy').find('option:selected').val() != 'Please select option' && $('#dividedBy').find('option:selected').val() != undefined) || $('#split-payment').is(":checked") == true) {
			cashAmount = $('#split_value_field').val();
		} else if (cashAmountWithDiscount > 0) {
			cashAmount = cashAmountWithDiscount;
		} else {
			if (splitPayment == '1') {
				cashAmount = totalForCashSplit;
			} else {
				cashAmount = totalForCash;
			}
		}
		var paidCashAmount = $('#cash-paid-amount1').val();

		if (customChargeTotal > 0 || pricedAddonAdded > 0) {
			if (parseFloat(paidCashAmount) < cashAmount) {
				swal('Please add a paid amount greater than the final booking amount.', '', 'warning');
				return false;
			}
		}
	}
	$('#btn-make-payment')[0].disabled = false;
	if ($('#pay-with-card').length && ($('#pay-with-card')[0].checked && !paymentIntentId)) {
		$(form).append(`<input type="hidden" name="feature" value="make_payment">`);
		var feature = 'make_payment';
		initTourBookingStripePayment.initializeStripe(form, feature);
		return false;

	} else {
		$('#btn-make-payment')[0].disabled = true;
		submitMakePaymentAffiliate(form);
	}
	return false;
}
function submitMakePaymentAffiliate(form) {
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
					if (isCheckInStatusChanged && selectedCheckInStatus != null) {
						performCheckInAfterPayment(selectedCheckInStatus);
					}
					tourBooked(form, response);
				}
			}
		})
		.fail(function (response) {
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

		if ($('#pay-with-card')[0].checked)
			$('#btn-make-payment')[0].disabled = false;
		else
			$('#btn-make-payment')[0].disabled = false;

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

	// cardElement.mount("#card-element");

	cardElement.on("change", function (event) {
		// Disable the Pay button if there are no card details in the Element
		cardInputValid = event.complete;
		$('#btn-make-payment')[0].disabled = ($('#pay-with-card')[0].checked && !cardInputValid);

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
				taxDisplayValue = (taxes[a].value_type == 'Percent' ? taxes[a].value + '%' : currency(taxes[a].value).format());

			$(taxCellStart + taxDisplayValue + taxCellEnd)
				.insertBefore($tblRateGrps[b].children[$tblRateGrps[b].children.length - 4]);
		}
	}

	$('.subtotal-price, .total').attr('colspan', initRateGrpCols + taxes.length);
}

function tourBooked(form, response) {
	var isPayWithCard = ($('#pay-with-card').length && $('#pay-with-card')[0].checked);
	// disabling form controls
	$(form)
		.data('booking-id', response.bookingId)



	if (response.error && response.errors.length) {
		$('#btn-make-payment')[0].disabled = false;

		if (isPayWithCard && response.errors.indexOf('payment') != -1)
			swal('Payment initialization error', 'Tour has been booked but an error occurred when initializing payment on the server. You may try again to make a payment.', 'error');

	} else if (isPayWithCard && typeof (response.errors) != "undefined" && response.errors.length) {
		hideLoading();
		swal('Error(s) Occurred', response.errors, 'error');
		showErrorsInHistory(response);
		$('#btn-make-payment')[0].disabled = false;
	} else {
		if (isAffiliate == 'No' && ($('#pay-with-cash1').length && $('#pay-with-cash1')[0].checked)) {
			if (($('#dividedBy_2').find('option:selected').val() != 'Please select option' && $('#dividedBy').find('option:selected').val() != undefined) || $('#split-payment').is(":checked") == true) {
				cashAmount = $('#split_value_field1').val();
			} else {
				if (isCashFees == '1') {
					if (splitPayment == '1') {
						cashAmount = totalForCashSplit;
					} else {
						cashAmount = (cashPriceEnabled == 1) ? cashPriceTotalWithFees : totalForCard;
					}
				} else {
					if (splitPayment == '1') {
						cashAmount = totalForCashSplit;
					} else {
						cashAmount = totalForCash;
					}
				}

			}


			var paidCashAmount = $('#cash-paid-amount').val();

			if (Number(cashAmount) > 0 && paidCashAmount > 0) { //Number(paidCashAmount) >= Number(cashAmount)
				var changeAmount = paidCashAmount - cashAmount;
				payWithCashChange(changeAmount, response.splitCount);
			} else {
				swal('Booking Payment Done.', 'Page will reload', 'success')
					.then(function () {
						var urlw = window.location.href
						var spliturlCheck = urlw.slice(-7);
						var splitslice = spliturlCheck.slice(0, 5);
						if (response.splitCount != "Please select option") {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -1);
								setUrl = setUrl + response.splitCount;
								window.location.replace(setUrl);
							} else {
								urlw = urlw + "?split=" + response.splitCount;
								window.location.replace(urlw);
							}

						} else {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -8);
								window.location.replace(setUrl);
							} else {
								location = location; // location.reload() keeps data in form which isn't desirable
							}

						}
					});
			}
		} else if (isAffiliate == 'Yes' && ($('#pay-with-cash').length && $('#pay-with-cash')[0].checked)) {
			if (($('#dividedBy').find('option:selected').val() != 'Please select option' && $('#dividedBy').find('option:selected').val() != undefined) || $('#split-payment1').is(":checked") == true) {
				cashAmount = $('#split_value_field').val();
			} else if (cashAmountWithDiscount > 0) {
				cashAmount = cashAmountWithDiscount;
			} else {
				if (isCashFees == '1') {
					if (splitPayment == '1') {
						cashAmount = totalForCashSplit;
					} else {
						cashAmount = (cashPriceEnabled == 1) ? cashPriceTotalWithFees : totalForCard;
					}

				} else {
					if (splitPayment == '1') {
						cashAmount = totalForCashSplit;
					} else {
						cashAmount = totalForCash;
					}
				}
			}
			var paidCashAmount = $('#cash-paid-amount1').val();

			if (Number(cashAmount) > 0 && paidCashAmount > 0) {// Number(paidCashAmount) >= Number(cashAmount)
				var changeAmount = paidCashAmount - cashAmount;

				payWithCashChange(changeAmount, response.splitCount);
			} else {
				swal('Booking Payment Done', 'Page will reload', 'success')
					.then(function () {
						var urlw = window.location.href
						var spliturlCheck = urlw.slice(-7);
						var splitslice = spliturlCheck.slice(0, 5);
						if (response.splitCount != "Please select option") {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -1);
								setUrl = setUrl + response.splitCount;
								window.location.replace(setUrl);
							} else {
								urlw = urlw + "?split=" + response.splitCount;
								window.location.replace(urlw);
							}

						} else {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -8);
								window.location.replace(setUrl);
							} else {
								location = location; // location.reload() keeps data in form which isn't desirable
							}

						}
					});
			}
		} else {
			if (isPayWithCard)
				swal('Booking Payment Done', 'Page will reload', 'success')
					.then(function () {
						var urlw = window.location.href
						var spliturlCheck = urlw.slice(-7);
						var splitslice = spliturlCheck.slice(0, 5);
						if (response.splitCount != "Please select option") {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -1);
								setUrl = setUrl + response.splitCount;
								window.location.replace(setUrl);
							} else {
								urlw = urlw + "?split=" + response.splitCount;
								window.location.replace(urlw);
							}

						} else {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -8);
								window.location.replace(setUrl);
							} else {
								location = location; // location.reload() keeps data in form which isn't desirable
							}

						}

					});
			else {
				swal('Booking Payment Done', 'Page will reload', 'success')
					.then(function () {
						var urlw = window.location.href
						var spliturlCheck = urlw.slice(-7);
						var splitslice = spliturlCheck.slice(0, 5);
						if (response.splitCount != "Please select option") {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -1);
								setUrl = setUrl + response.splitCount;
								window.location.replace(setUrl);
							} else {
								urlw = urlw + "?split=" + response.splitCount;
								window.location.replace(urlw);
							}

						} else {
							if (splitslice == "split") {
								var setUrl = urlw.slice(0, -8);
								window.location.replace(setUrl);
							} else {
								location = location; // location.reload() keeps data in form which isn't desirable
							}

						}
					});
			}
		}
	}
}

function payWithCashChange(changeAmount, splitCount) {

	if (changeAmount > 0) {

		var elemdiv = document.createElement("div");
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
				var urlw = window.location.href
				var spliturlCheck = urlw.slice(-7);
				var splitslice = spliturlCheck.slice(0, 5);
				if (splitCount != "Please select option") {
					if (splitslice == "split") {
						var setUrl = urlw.slice(0, -1);
						setUrl = setUrl + splitCount;
						window.location.replace(setUrl);
					} else {
						urlw = urlw + "?split=" + splitCount;
						window.location.replace(urlw);
					}

				} else {
					if (splitslice == "split") {
						var setUrl = urlw.slice(0, -8);
						window.location.replace(setUrl);
					} else {
						location = location; // location.reload() keeps data in form which isn't desirable
					}

				}
			});
	} else {
		var addText = '';
		const el = document.createElement('div');
		el.className = "swal-title";
		if (changeAmount < 0 && $('#split-payment1').is(":checked") == false) {
			changeAmount = Math.abs(changeAmount);
			el.innerHTML = 'Booking Payment Done.<br/>There is still unpaid amount is <span style="color: #f14f2f;">$' + changeAmount.toFixed(2) + '</span>';
		} else {
			if (changeAmount < 0) {
				changeAmount = Math.abs(changeAmount);
				el.innerHTML = 'Booking Payment Done.<br/>There is still unpaid amount is <span style="color: #f14f2f;">$' + changeAmount.toFixed(2) + '</span>';
			} else {
				el.innerHTML = 'Booking Payment Done. Page will reload.';
			}
		}
		swal({
			content: el,
			icon: 'success'
		})
			.then(function () {
				var urlw = window.location.href
				var spliturlCheck = urlw.slice(-7);
				var splitslice = spliturlCheck.slice(0, 5);
				if (splitCount != "Please select option") {
					if (splitslice == "split") {
						var setUrl = urlw.slice(0, -1);
						setUrl = setUrl + splitCount;
						window.location.replace(setUrl);
					} else {
						urlw = urlw + "?split=" + splitCount;
						window.location.replace(urlw);
					}

				} else {
					if (splitslice == "split") {
						var setUrl = urlw.slice(0, -8);
						window.location.replace(setUrl);
					} else {
						location = location; // location.reload() keeps data in form which isn't desirable
					}

				}
			});
	}
}

