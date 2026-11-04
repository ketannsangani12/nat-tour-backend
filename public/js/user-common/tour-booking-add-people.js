var affiliateCheck = 0;
var lookupForCashAndPayLaterPaymentMethod = 0;
var totalBeforeDiscountAndServiceCommission = 0;
var subTotalAmount = 0;
$('#modal_admin').on('hide.bs.modal', function (e) {
	if ($('#modal_admin').data('call-by-edit-booking-add-people')) {
		$('#edit_booking_add_people').data('prevent-init-calls', true).modal('show');
		$('#modal_admin').removeData('call-by-edit-booking-add-people');
		$('#discount_type_percentage, #discount_type_fixed_money').prop('checked', false);
		$('#discount_percentage, #discount_fixed_money').val('').prop('disabled', true);
		applyDiscountAddPeople();
	}
}).on('show.bs.modal', function (e) {
	if ($('#modal_admin').data('call-by-edit-booking-add-people')) {
		if (paymentMethod == 'Cash') {
			$('#modal_admin label[for="pay-with-card1"]').hide();
			$('#modal_admin label[for="pay-with-later"]').hide();
			$('#modal_admin label[for="pay-with-cash1"]').addClass('checked-item');
			$('#modal_admin .card-details').hide();
			$('#modal_admin #pay-with-cash1').prop('checked', true);
		} else if (paymentMethod == 'Card') {
			$('#modal_admin label[for="pay-with-cash1"]').hide();
			$('#modal_admin label[for="pay-with-later"]').hide();
			$('#modal_admin label[for="pay-with-card1"]').addClass('checked-item');
			$('#modal_admin #pay-with-card1').prop('checked', true);
		} else if (paymentMethod == 'Invoiced') {
			$('#modal_admin label[for="pay-with-cash1"]').hide();
			$('#modal_admin label[for="pay-with-card1"]').hide();
			$('#modal_admin label[for="pay-with-later"]').addClass('checked-item');
			$('#modal_admin .card-details').hide();
			$('#modal_admin #pay-with-later').prop('checked', true);
		}
		if (splitPayment == '' || splitPayment == '0') {
			$('#modal_admin .split-payment-div').hide();
		}
		applyDiscountAddPeople();
	}
});
$('#modal_affilate').on('hide.bs.modal', function (e) {
	if ($('#modal_affilate').data('call-by-edit-booking-add-people')) {
		$('#edit_booking_add_people').data('prevent-init-calls', true).modal('show');
		$('#modal_affilate').removeData('call-by-edit-booking-add-people');
		$('#discount_type_percentage, #discount_type_fixed_money').prop('checked', false);
		$('#discount_percentage, #discount_fixed_money').val('').prop('disabled', true);
		applyDiscountAddPeople();
	}
}).on('show.bs.modal', function (e) {
	if ($('#modal_affilate').data('call-by-edit-booking-add-people')) {
		if (paymentMethod == 'Cash') {
			$('#modal_affilate label[for="pay-with-card"]').hide();
			$('#modal_affilate label[for="pay-with-later2"]').hide();
			$('#modal_affilate label[for="pay-with-cash"]').addClass('checked-item');
			$('#modal_affilate .payment-details').hide();
			$('#modal_affilate label[for="pay-with-later"]').hide();
			$('#modal_affilate #pay-with-cash').prop('checked', true);
		} else if (paymentMethod == 'Card') {
			$('#modal_affilate label[for="pay-with-cash"]').hide();
			$('#modal_affilate label[for="pay-with-later2"]').hide();
			$('#modal_affilate label[for="pay-with-card"]').addClass('checked-item');
			$('#modal_affilate #pay-with-card').prop('checked', true);
		} else if (paymentMethod == 'Invoiced') {
			$('#modal_affilate label[for="pay-with-cash"]').hide();
			$('#modal_affilate label[for="pay-with-card"]').hide();
			$('#modal_affilate label[for="pay-with-later2"]').addClass('checked-item');
			$('#modal_affilate #pay-with-card').hide();
			$('#modal_affilate #pay-with-later2').prop('checked', true);
		}
		if (splitPayment == '' || splitPayment == '0') {
			$('#modal_affilate .split-payment-div').hide();
		}
		applyDiscountAddPeople();
	}
});

function setRateGroups() {
	//var paymentMethod = $('.checkset:checked').val();
	var $tblRateGrps = $('#table-rate-groups');
	$tblRateGrps.html('');
	var k = 0;
	for (var a = 0; a < rateGroups.length; a++) {
		var $tmpl = $('#tmpl-rate-group tr').clone(true);
		$hidRateGroup = $tmpl.find('.hid-rate-group');
		$hidRateGroup.val(rateGroups[a].id).attr('name', $hidRateGroup.data('name'));
		$selTourists = $tmpl.find('.tourists');
		$selTourists.attr('name', $selTourists.data('name'));
		$selPermitFees = $tmpl.find('.permit-fee');
		$selPermitFees.attr('name', $selPermitFees.data('name'));

		$tmpl.find('.age').html('<span class="rate-for">' + encLT(rateGroups[a].rate_for) + '</span>');
		$tmpl.find('.price .tag').text(currencyFormation.symbol + rateGroups[a].rate);
		$tmpl.find('.permit-fee').addClass('permit-fee1');
		$tmpl.find('.permit-fee1').removeClass('permit-fee');
		$tmpl.find('.proc-fee').addClass('processing-fee-' + a);
		var pFee = Number(rateGroups[a].permit_fee);
		var zeroselected = (pFee == 0.00) ? 'selected' : '';
		var dynamicPermitFee = (pFee > 0) ? '<option selected>' + pFee + '</option>' : '';
		$tmpl.find('.permit-fee1').html('<option ' + zeroselected + '>0</option>' + dynamicPermitFee);
		if (paymentMethod == 'Card') {
			$tmpl.find('.proc-fee').text(rateGroups[a].processing_charge_percentage + '%');
		} else {
			$tmpl.find('.proc-fee').text('0.00%');
		}
		$tmpl.find('.adnl-tax').text(rateGroups[a].additional_tax_percentage + '%');
		$tmpl.find('.adnl-charge').text(currencyFormation.symbol + rateGroups[a].additional_charge);
		$tblRateGrps.append($tmpl);
		k++;
	}

	totalSansDiscount = 0;
	addTaxCols();
	updateRatesTable();
	permitFeeTable();
}
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
			if ($tblRateGrps[b].children[2].innerText == currencyFormation.symbol + '0.00')
				taxDisplayValue = (taxes[a].value_type == 'Percent' ? '0.00%' : currencyFormation.symbol + '0.00');
			else
				taxDisplayValue = (taxes[a].value_type == 'Percent' ? taxes[a].value + '%' : currencyFormation.symbol + taxes[a].value);

			$(taxCellStart + taxDisplayValue + taxCellEnd)
				.insertBefore($tblRateGrps[b].children[$tblRateGrps[b].children.length - 4]);
		}
	}

	$('.subtotal-price, .total').attr('colspan', initRateGrpCols + taxes.length);
}
function updateRatesTable() {
	//var paymentMethod = $('.checkset:checked').val();
	$('.discount-applied').val(0);
	//$('.sub-total, .fees-total, .grand-total').text('$0.00');

	if ($('#slot_time').val() == 0) {
		$('#table-rate-groups .tourists').html('<option>0</option>');
		return;
	}

	// Update when tour operator change
	serviceCommissionPercentage = rateGroups[0].service_commission_percentage;

	var $tblRateGrps = $('#table-rate-groups');
	var total = 0;
	var lookup = 0;
	subTotalAmount = 0;
	for (var a = 0; a < rateGroups.length; a++) {
		$row = $($tblRateGrps.children()[a]);
		var $selTourists = $row.find('.tourists');
		var tourists = parseInt($selTourists.val());
		var $selpermitfee = $row.find('.permit-fee');
		if (isNaN(parseInt($selpermitfee.val()))) {
			$selpermitfee = $row.find('.permit-fee1');
		}
		var permitfee = parseInt($selpermitfee.val());

		var groupTotal = 0;
		var stateTax = 0;
		var lookTotal = 0;
		// rate may be zero for one or more age/rate group and
		// if rate is non-zero then only add other amounts
		for (var b = 0; b < taxes.length; b++) {
			stateTax += tourists * (taxes[b].value_type == 'Percent'
				? rateGroups[a].rate * taxes[b].value / 100
				: taxes[b].value
			);
		}

		groupTotal = tourists * (
			parseFloat(rateGroups[a].rate)
			+ parseFloat(rateGroups[a].additional_charge)
			+ parseFloat(permitfee)
		);

		if (tourists) {
			var processingCharge = Number(rateGroups[a].processing_charge_percentage);
			if (rateGroups[a].additional_tax_percentage != 0) {
				var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
				lookTotal += groupTotal * Number(rateGroups[a].additional_tax_percentage) / 100;
				groupTotal += groupTotal * ad_tax_pe_n_processing_fee / 100;
			} else {
				lookTotal += groupTotal;
				if (processingCharge) {
					groupTotal += groupTotal * processingCharge / 100;
				}
			}
		}

		if (groupTotal) {
			if (paymentMethod == 'Card') {
				$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
			} else {
				$row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
			}
		} else {
			$row.find('.final').text(currencyFormation.symbol + '0.00');
		}
		subTotalAmount += groupTotal;
		total += groupTotal;
		lookup += lookTotal;

		if ($selTourists.children().length > 1) {
			var rateGroupId = $row.find('.hid-rate-group').val();

			for (var b = 0; b < touristsGroups.length; b++) {
				if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
					touristsGroups[b].tourists = tourists;
					break;
				}
			}

			if (b >= touristsGroups.length) { // rate group isn't in the array, so add it
				touristsGroups.push({
					rate_for: $row.find('.rate-for').text(),
					tour_package_rate_id: rateGroupId,
					tourists: tourists
				});
			}
		}
	}

	totalBeforeDiscountAndServiceCommission = total;
	lookupForCashAndPayLaterPaymentMethod = lookup;

	if (lookup) {
		if (paymentMethod == 'Card') {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + total.toFixed(2));
		} else {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + lookup.toFixed(2));
		}
	} else {
		$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + '0.00');
	}
	$('#subtotaladdpeople').val(subTotalAmount);
	applyDiscountAddPeople();
}
function permitFeeTable() {
	//var paymentMethod = $('.checkset:checked').val();
	$('.discount-applied').val(0);
	//$('.sub-total, .fees-total, .grand-total').text('$0.00');
	if ($('#slot_time').val() == 0) {
		$('#table-rate-groups .tourists').html('<option>0</option>');
		return;
	}
	var $tblRateGrps = $('#table-rate-groups');
	var total = 0;
	var lookup = 0;
	subTotalAmount = 0;

	for (var a = 0; a < rateGroups.length; a++) {
		$row = $($tblRateGrps.children()[a]);
		var $selTourists = $row.find('.tourists');
		var tourists = parseInt($selTourists.val());
		var $selpermitfee = $row.find('.permit-fee');
		if (isNaN(parseInt($selpermitfee.val()))) {
			$selpermitfee = $row.find('.permit-fee1');
		}
		var permitfee = parseInt($selpermitfee.val());
		var groupTotal = 0;
		var lookTotal = 0;
		var stateTax = 0;

		// rate may be zero for one or more age/rate group and
		// if rate is non-zero then only add other amounts
		// if (parseFloat(rateGroups[a].rate)) {
		for (var b = 0; b < taxes.length; b++) {
			stateTax += tourists * (taxes[b].value_type == 'Percent'
				? rateGroups[a].rate * taxes[b].value / 100
				: taxes[b].value
			);
		}

		groupTotal = tourists * (
			parseFloat(rateGroups[a].rate)
			+ parseFloat(rateGroups[a].additional_charge)
			+ parseFloat(permitfee)
		);

		if (tourists) {
			var processingCharge = Number(rateGroups[a].processing_charge_percentage);
			if (rateGroups[a].additional_tax_percentage != 0) {
				var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
				lookTotal += groupTotal * Number(rateGroups[a].additional_tax_percentage) / 100;
				groupTotal += groupTotal * ad_tax_pe_n_processing_fee / 100;
			} else {
				lookTotal += groupTotal;
				if (processingCharge) {
					groupTotal += groupTotal * processingCharge / 100;
				}
			}
		}
		// }

		if (groupTotal) {
			if (paymentMethod === 'Card') {
				$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
			} else {
				$row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
			}
		} else {
			$row.find('.final').text(currencyFormation.symbol + '0.00');
		}

		total += groupTotal;
		lookup += lookTotal;
		subTotalAmount += groupTotal;
		if ($selTourists.children().length > 1) {
			var rateGroupId = $row.find('.hid-rate-group').val();

			for (var b = 0; b < touristsGroups.length; b++) {
				if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
					touristsGroups[b].tourists = tourists;
					break;
				}
			}

			if (b >= touristsGroups.length) { // rate group isn't in the array, so add it
				touristsGroups.push({
					rate_for: $row.find('.rate-for').text(),
					tour_package_rate_id: rateGroupId,
					tourists: tourists
				});
			}
		}
	}


	totalBeforeDiscountAndServiceCommission = total;
	lookupForCashAndPayLaterPaymentMethod = lookup;

	if (isNaN(total)) {
		$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + '0.00');
	} else {
		if (paymentMethod == 'Card') {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + total.toFixed(2));
		} else {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + lookup.toFixed(2));

		}
	}
	$('#subtotaladdpeople').val(subTotalAmount);
	applyDiscountAddPeople();
}
function showSlotInfo(selSlotTime) {
	showSelectionInfo();
	$('#table-rate-groups .tourists').html('<option>0</option>');
	$('#table-rate-groups .permit-fee').html('<option>0</option>');

	if (selSlotTime.selectedIndex == 0) {
		$('#span-seats-info').html('0 of 0 left');
		$('.tour-process').css('width', '50%');
		return;
	}

	var $opt = $(selSlotTime.options[selSlotTime.selectedIndex]);
	var tourists = $opt.data('tourists');
	var seats = $opt.data('seats');

	var emptySeats = seats - tourists;

	$('#span-seats-info').html(emptySeats + ' of ' + seats + ' left');
	$('.tour-process').css('width', ((tourists * 100) / seats) + '%');

	$('#table-rate-groups .tourists').each(function () {
		addTouristsOpts($(this), emptySeats + 50);
	});

	var slot_time = $("#slot_time").val();
	$("#selected_slotTime").val(slot_time);

	setTouristsGroups();
	updateRatesTable();
	permitFeeTable();
}
function showSelectionInfo() {
	if ($('#tour_package').val())
		$('.tour-title').text($('#tour_package')[0].options[$('#tour_package')[0].selectedIndex].text);
	else
		$('.tour-title').text('-');

	$('.booking-selected-date').text(moment($('#tour_slot_date').datepicker('getDate')).format('dddd, MMMM Do, Y'));

	if ($('#slot_time').val())
		$('.booking-selected-time').text($('#slot_time')[0].options[$('#slot_time')[0].selectedIndex].text);
	else
		$('.booking-selected-time').text('-');
}
function addTouristsOpts($selTourists, emptySeats) {
	for (var a = 0, $selTourists; a < emptySeats; a++)
		$selTourists.append(new Option(a + 1));
}
function setTouristsGroups() {
	updateRatesTable();
	$hidRateGroup = $('#table-rate-groups .hid-rate-group');
	$rateFor = $('#table-rate-groups .rate-for');
	$selTourists = $('#table-rate-groups .tourists');

	for (var a = 0; a < touristsGroups.length; a++) {
		for (var b = 0; b < $hidRateGroup.length; b++) {
			if (
				touristsGroups[a].tour_package_rate_id == $hidRateGroup[b].value ||
				touristsGroups[a].rate_for == encLT($rateFor[b].innerHTML)
			) {
				$($selTourists[b]).val(touristsGroups[a].tourists);
				break;
			}
		}
	}

	updateRatesTable();
	permitFeeTable();
}
function showMakePaymentForEditBooking(form) {
	$(form).data('validation_check', true);
	if (paymentMethod == 'Pay Later') {
		$(form).data('unpaid', true);
	}
	validateAndSubmitFormAddPeople(form);
	return false;
}
function validateAndSubmitFormAddPeople(form, additionalForm) {
	var submitBtn = $('#btn-make-payment')[0],
		includeValidationCheckField = false,
		includePaymentCheckField = false,
		formObj = $(form),
		formData;

	if (typeof additionalForm === "undefined") {
		includeValidationCheckField = true;
		submitBtn = $('#update_booking_add_people_submit')[0];
	} else {
		includeValidationCheckField = true;
		formObj = formObj.add(additionalForm);
	}

	if (paymentMethod == 'Pay Later') {
		includePaymentCheckField = true;
	}

	if (paymentMethod == 'Card' && $(form).data('validation_check') == false) {
		var inputId = (isAffiliate == 'Yes') ? 'cardnumber' : 'cardnumber1';
		if (!validateCardnumber({ inputId: inputId }))
			return false;
	}

	formData = $(formObj).serialize();

	submitBtn.disabled = true;

	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: formData + (includeValidationCheckField ? ('&validation_check=' + $(form).data('validation_check')) : ''),
	}).done(function (response) {
		if (paymentMethod == 'Pay Later' || subTotalAmount == 0) {
			swal('Booking updated successfully', 'Page will reload', 'success')
				.then(function () {
					location = location;
				});
		} else {
			if ($(form).data('validation_check')) {
				submitBtn.disabled = false;
				if (isAffiliate == 'Yes') {
					$('#modal_affilate').data('call-by-edit-booking-add-people', true).modal('show');
				} else {
					$('#modal_admin').data('call-by-edit-booking-add-people', true).modal('show');
				}
				$('#edit_booking_add_people').data('prevent-hide-callback', true).modal('hide');
			} else {
				swal('Booking updated successfully', 'Page will reload', 'success')
					.then(function () {
						location = location;
					});
			}
		}
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
		submitBtn.disabled = false;
	}).always(hideLoading);

	return false;
}

function applyDiscountAddPeople() {
	var discount = 0;
	var discount_card = 0;
	var discount_cash = 0;
	var serviceCommission = 0;
	if (isAffiliate == 'Yes') {
		var span_invoiced = lookupForCashAndPayLaterPaymentMethod;
		var span_cash = lookupForCashAndPayLaterPaymentMethod;
		var span_card = totalBeforeDiscountAndServiceCommission;
	} else {
		var span_invoiced = lookupForCashAndPayLaterPaymentMethod;
		var span_cash = lookupForCashAndPayLaterPaymentMethod;
		serviceCommission = totalBeforeDiscountAndServiceCommission * serviceCommissionPercentage / 100;
		var span_card = totalBeforeDiscountAndServiceCommission + serviceCommission;
	}
	if (($('#discount_type_percentage').length > 0 && $('#discount_type_fixed_money').length > 0) && ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {
		if ($('#discount_type_percentage')[0]?.checked) {
			var inputVal = parseFloat($('#discount_percentage').val());

			if (isNaN(inputVal)) // blank or something else
				inputVal = 0;

			discount = (span_invoiced * inputVal) / 100;
			discount_cash = (span_cash * inputVal) / 100;
			discount_card = (span_card * inputVal) / 100;
			if (discount < 0 || discount_cash < 0 || discount_card < 0) {
				$('#discount_percentage').val('');
				applyDiscountAddPeople();
			}

			if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
				$('#pr_amount').val(discount);
			}
			if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
				$('#pr_amount').val(discount_cash);
			}
			if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
				$('#pr_amount').val(discount_card);
			}

		} else {
			var inputVal = parseFloat($('#discount_fixed_money').val());

			if (isNaN(inputVal))
				discount = discount_cash = discount_card = 0;
			else
				discount = discount_cash = discount_card = inputVal;
		}
	}

	span_invoiced = span_invoiced - discount;
	span_cash = span_cash - discount_cash;
	span_card = span_card - discount_card;

	if (isAffiliate == 'Yes') {
		$('#span_invoiced').text(currencyFormation.symbol + span_invoiced.toFixed(2));
		$('#span_cash').text(currencyFormation.symbol + span_cash.toFixed(2));
		$('#span_card').text(currencyFormation.symbol + span_card.toFixed(2));
	} else {
		$('.invoiced-amount').text(currencyFormation.symbol + span_invoiced.toFixed(2));
		$('.cash-amount').text(currencyFormation.symbol + span_cash.toFixed(2));
		$('.card-amount').text(currencyFormation.symbol + span_card.toFixed(2));

		if (paymentMethod == 'Cash') {
			$('#modal_admin .set-total').text(currencyFormation.symbol + span_cash.toFixed(2));
		} else if (paymentMethod == 'Card') {
			$('#modal_admin .set-total').text(currencyFormation.symbol + span_card.toFixed(2));
		} else if (paymentMethod == 'Invoiced') {
			$('#modal_admin .set-total').text(currencyFormation.symbol + span_invoiced.toFixed(2));
		}
	}

	if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
		$('#htt').val(span_invoiced);
		$('#totalcost').text(currencyFormation.symbol + span_invoiced.toFixed(2));
	};
	if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
		$('#htt').val(span_cash);
		$('#totalcost').text(currencyFormation.symbol + span_cash.toFixed(2));
	}
	if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
		$('#htt').val(span_card);
		$('#totalcost').text(currencyFormation.symbol + span_card.toFixed(2));
	}

}
