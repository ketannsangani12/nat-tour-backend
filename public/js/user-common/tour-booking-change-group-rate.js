var affiliateCheck = 0;
var lookupForCashAndPayLaterPaymentMethod = 0;
var totalBeforeDiscountAndServiceCommission = 0;
var totalPaymentAmount = 0;
var totalRefundAmount = 0;
var finalAmount = 0;
var subTotalAmount = '';
var totalInfantsOrChilds = 0;
var totalAddedPeopleCount = 0;
var totalDeletedPeopleCount = 0;
var serviceCommissionTotal = 0;
var cashAmountChangePeople = 0;
var totalTourists = 0;
var totalSeatsForBooking = 0;
var totalPaxForDropDown = 0;
var emptySeatsOfSlot = 0;
var addedPeopleCountArray = [];
function setGroupRateChangePeople() {
	var $tblRateGrps = $('#table-rate-groups-change-people');
	$tblRateGrps.html('');
	var selectedRateGrpId = '';
	for (var b = 0; b < rateGroups.length; b++) {
		if (rateGroups[b].tourists > 0) {
			selectedRateGrpId = rateGroups[b].id;
		}
	}

	var a = 0;
	if (rateGroups.length) {
		var $tmpl = $('#tmpl-rate-group-change-people tr').clone(true);
		$hidRateGroup = $tmpl.find('.hid-rate-group');
		$hidRateGroup.val(selectedRateGrpId).attr('name', $hidRateGroup.data('name'));
		$selTourists = $tmpl.find('.tourists');
		$selTourists.select2({
			dropdownParent: $("#edit_booking_change_people")
		});
		$selTourists.attr('name', $selTourists.data('name'));
		$selTourists.attr('id', 'tourists-' + rateGroups[a].id);

		$tmpl.find('.age').html('<span class="rate-for" data-tourists="' + rateGroups[a].tourists + '">' + encLT(rateGroups[a].rate_for) + '</span>');
		$tmpl.find('.price .tag').text(currencyFormation.symbol + rateGroups[a].rate);
		$tmpl.find('.adnl-charge').text('$0.00');
		$tblRateGrps.append($tmpl);
	}

	totalSansDiscount = 0;
	updateGroupRatesTableChangePeople();
}


function updateGroupRatesTableChangePeople() {
	$('.discount-applied').val(0);

	if ($('#slot_time').val() == 0) {
		$('#table-rate-groups-change-people .tourists').html('<option>0</option>');
		return;
	}

	//This is for old bookings
	if (Number(bookingId) < Number(oldBookingId)) {
		if (isAffiliate != 'Yes') {
			serviceCommissionPercentage = 6;
		} else {
			serviceCommissionPercentage = 0;
		}
	} else {
		if (isAffiliate == 'Yes') {
			serviceCommissionPercentage = rateGroups[0].affiliate_processing_percentage;
		} else {
			serviceCommissionPercentage = rateGroups[0].service_commission_percentage;
		}
	}

	var $tblRateGrps = $('#table-rate-groups-change-people');
	var total = 0;
	var lookup = 0;
	var totalpayment = 0;
	var totalrefund = 0;
	var infantsorchilds = 0;
	var addedPeopleCount = 0;
	var deletedPeopleCount = 0;
	var touristsTotal = 0;
	var groupTotal = 0;
	var additionalCharge = 0;
	var totalpayment = 0;
	var totalrefund = 0;
	var a = 0;

	$row = $($tblRateGrps.children()[a]);
	var $selTourists = $row.find('.tourists');
	var rateGrpId = $selTourists.data('rategrp');

	if ($selTourists.val() == null) {
		$selTourists.find('option').prop('selected', true);
		var tourists = 0;
	} else {
		var tourists = parseInt($selTourists.val());
	}
	if (Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0 && tourists > 0) {

		if (paxWiseGroupRates[tourists] !== undefined && paxWiseGroupRateIds[tourists] !== undefined && paxWiseGroupAdditionalCharges[tourists] !== undefined) {
			additionalCharge = paxWiseGroupAdditionalCharges[tourists];
			groupTotal = paxWiseGroupRates[tourists] + additionalCharge;
			rateGrpId = paxWiseGroupRateIds[tourists];
			$row.find('.adnl-charge').text(currencyFormation.symbol + additionalCharge.toFixed(2));
		}
	

	}
	$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));


	var oldTourists = 0;
	var oldFinalAmount = 0;
	for (var b = 0; b < rateGroups.length; b++) {
		if (rateGroups[b].tourists > 0) {
			oldTourists = rateGroups[b].tourists;
			oldFinalAmount = rateGroups[b].total;
		}
	}
	var paymentAmount = 0;
	var refundAmount = 0;
	if (oldTourists > 0 && tourists > 0 && oldTourists != tourists) {
		if (tourists > oldTourists) {
			paymentAmount = (groupTotal - oldFinalAmount);
			addedPeopleCount = tourists - oldTourists;
		} else if (tourists < oldTourists) {
			refundAmount += (oldFinalAmount - groupTotal);
			deletedPeopleCount = oldTourists - tourists;
		}
	}
	
	totalpayment = paymentAmount;
	totalrefund = refundAmount;

	if (tourists > 0 && oldTourists != tourists) {
		$hidRateGroup = $row.find('.hid-rate-group');
		$hidRateGroup.val(rateGrpId);
	}
	if ($selTourists.children().length > 1) {
		var rateGroupId = $row.find('.hid-rate-group').val();

		var b = 0;
		if (touristsGroups[b] !== undefined) {
			if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
				touristsGroups[b].tourists = tourists;
				touristsGroups[b].selectedtourists = oldTourists;
			} else {
				touristsGroups[b].tour_package_rate_id = rateGroupId;
				touristsGroups[b].tourists = tourists;
				touristsGroups[b].selectedtourists = oldTourists;
			}
		} else {
			touristsGroups.push({
				rate_for: $row.find('.rate-for').text(),
				tour_package_rate_id: rateGroupId,
				tourists: tourists,
				selectedtourists: oldTourists
			});
		}

	}


	totalBeforeDiscountAndServiceCommission = groupTotal;
	lookupForCashAndPayLaterPaymentMethod = groupTotal;
	cashAmountChangePeople = lookupForCashAndPayLaterPaymentMethod;


	// These are additional calculations for change people feature
	totalPaymentAmount = Number(totalpayment);
	totalRefundAmount = Number(totalrefund);
	

	finalAmount = totalPaymentAmount - totalRefundAmount;

	if (finalAmount) {
		$('.total-before-service-commission-and-discount-change-people').text(currencyFormation.symbol + finalAmount.toFixed(2));

		if (finalAmount >= 0) {
			$(".total-before-service-commission-and-discount-change-people").css("color", "#4abd5d");
		} else if (finalAmount < 0) {
			$(".total-before-service-commission-and-discount-change-people").css("color", "#ff5e5e");
		} else {
			$(".total-before-service-commission-and-discount-change-people").css("color", "#4abd5d");
		}
	} else {
		$('.total-before-service-commission-and-discount-change-people').text('$0.00');
		$(".total-before-service-commission-and-discount-change-people").css("color", "#4abd5d");
	}

	totalInfantsOrChilds = infantsorchilds;

	if (totalPaymentAmount == 0 && totalRefundAmount == 0) {
		subTotalAmount = 0;
		$('#subtotalchangepeople').val(0);
	} else {
		subTotalAmount = '';
		$('#subtotalchangepeople').empty();
	}

	applyDiscountChangeGroupPeople();
}

function showSlotInfoGroupRateChangePeople(selSlotTime) {
	showSelectionInfoGroupChangePeople();
	$('#table-rate-groups-change-people .tourists').html('<option>' + currency(0).format() + ' - 0</option>');

	if (selSlotTime.selectedIndex == 0) {
		$('#span-seats-info-change-people').html('0 of 0 left');
		$('.tour-process').css('width', '50%');
		return;
	}

	var $opt = $(selSlotTime.options[selSlotTime.selectedIndex]);
	var tourists = $opt.data('tourists');
	var seats = $opt.data('seats');

	var emptySeats = seats - tourists;
	emptySeatsOfSlot = emptySeats;

	if (emptySeats < 0 && userType == 'Affiliate') {
		$('#span-seats-info-change-people').html('0 of ' + seats + ' left');
	} else {
		$('#span-seats-info-change-people').html(emptySeats + ' of ' + seats + ' left');
	}

	$('.tour-process').css('width', ((tourists * 100) / seats) + '%');
	var seatsForOptions = emptySeats + overBookSeats;
	totalPaxForDropDown = seatsForOptions;

	if (userType == 'Affiliate') {
		if (emptySeats <= 0) {
			seatsForOptions = 0;
		}
	} else {
		if (emptySeats < 0 && seatsForOptions <= 0) {
			seatsForOptions = 0;
		}
	}

	var touristsRateGroupWise = [];
	var totalTouristsOfBooking = 0;
	$('#table-rate-groups-change-people .rate-for').each(function () {
		touristsRateGroupWise.push(parseInt(bookingTourists));
	});
	totalTouristsOfBooking = parseInt(bookingTourists);
	$('#table-rate-groups-change-people .tourists').each(function (index) {
		if (userType == 'Affiliate') {
			if (seatsForOptions == 0) {
				addTouristsOptsGroupChangePeople($(this), bookingTourists);
			} else {
				addTouristsOptsGroupChangePeople($(this), touristsRateGroupWise[index] + emptySeats);
			}
		} else {
			if (seatsForOptions <= 0) {
				addTouristsOptsGroupChangePeople($(this), bookingTourists);
			} else {
				addTouristsOptsGroupChangePeople($(this), touristsRateGroupWise[index] + seatsForOptions);
			}
		}
	});

	totalSeatsForBooking = seatsForOptions;

	var slot_time = $("#slot_time").val();
	$("#selected_slotTime").val(slot_time);

	setGroupTouristsChangePeople();
	updateGroupRatesTableChangePeople();
}

function showSelectionInfoGroupChangePeople() {
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

function addTouristsOptsGroupChangePeople($selTourists, emptySeats) {
	for (var a = 1, $selTourists; a <= emptySeats; a++) {
		if (Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0) {
			var lastelement = Object.keys(paxWiseGroupRates)[Object.keys(paxWiseGroupRates).length - 1];
			if (paxWiseGroupRates[a] !== undefined && paxWiseGroupRateIds[a] !== undefined) {
				var touristOptText = currencyFormation.symbol + paxWiseGroupRates[a].toFixed(2) + " - " + (a);
				var option = document.createElement("option");
				option.value = a;
				option.text = touristOptText;
				option.setAttribute('data-rategrp', paxWiseGroupRateIds[a]);
				$selTourists.append(option);
			}
		}
	}

}

function setGroupTouristsChangePeople() {
	updateGroupRatesTableChangePeople();

	$hidRateGroup = $('#table-rate-groups-change-people .hid-rate-group');
	$rateFor = $('#table-rate-groups-change-people .rate-for');
	$selTourists = $('#table-rate-groups-change-people .tourists');

	for (var a = 0; a < touristsGroups.length; a++) {
		for (var b = 0; b < $hidRateGroup.length; b++) {
			if (
				touristsGroups[a].tour_package_rate_id == $hidRateGroup[b].value ||
				touristsGroups[a].rate_for == encLT($rateFor[b].innerHTML)
			) {
				$($selTourists[b]).val(touristsGroups[a].selectedtourists);
				break;
			}
		}
	}

	updateGroupRatesTableChangePeople();
}

function showMakePaymentForEditGroupBookingChangePeople(form) {
	var addedPeopleCount = 0;
	var deletedPeopleCount = 0;
	var oldTourists = 0;
	for (var b = 0; b < rateGroups.length; b++) {
		if (rateGroups[b].tourists > 0) {
			oldTourists += rateGroups[b].tourists;
		}
	}

	var a = 0;

	// Set total_tourist from touristsGroups which is correctly maintained by v2 code
	if (touristsGroups[a] && touristsGroups[a].tourists) {
		$(form).find('input[name="total_tourist"]').val(touristsGroups[a].tourists);
	}

	if (touristsGroups[a].tourists > oldTourists) {
		addedPeopleCount = Number(touristsGroups[a].tourists - oldTourists);
	}
	if (touristsGroups[a].tourists < oldTourists) {
		deletedPeopleCount = Number(oldTourists - touristsGroups[a].tourists);
	}

	// This function has no concept of add-ons — it only ever compared headcounts — so an edit
	// that changes ONLY pax add-ons (no +/- click, no removal) for a group-rate booking always
	// looked like "nothing changed" and got blocked here, even though changePeopleV2 correctly
	// tracked real add-on changes to submit. Guarded the same way the non-group sibling
	// (showMakePaymentForEditBookingChangePeople()) already guards its own equivalent check.
	// paxAddonsState.fields is only ever set by changePeopleV2.open() (i.e. the pax-addons-aware
	// modal actually ran for this booking) — checking it first keeps this a no-op for group-rate
	// bookings without add-ons, where changePeopleV2 was never opened at all.
	var hasV2AddonOnlyChanges = false;
	if (typeof changePeopleV2 !== 'undefined' && changePeopleV2.paxAddonsState && changePeopleV2.paxAddonsState.fields
		&& typeof changePeopleV2.hasChanges === 'function') {
		try {
			hasV2AddonOnlyChanges = changePeopleV2.hasChanges();
		} catch (e) {
			hasV2AddonOnlyChanges = false;
		}
	}

	if (addedPeopleCount == 0 && deletedPeopleCount == 0 && !hasV2AddonOnlyChanges) {
		swal('', 'You must need to select tourists to change.', 'error');
		return false;
	}

	totalAddedPeopleCount = addedPeopleCount;
	totalDeletedPeopleCount = deletedPeopleCount;

	// The "You have selected add-ons for more people than are being removed" check in
	// tour-booking-info.blade.php (shared with the non-group Change People flow) falls back to
	// this map — keyed by tour_package_rate_id, matching rateGroupIdToPaxId's own key — whenever
	// there's no per-checkbox rate-group tag to read directly, which is always the case for
	// Change People V2 (see that check's own comment). This file never populated it, so it
	// stayed permanently empty for every group-rate edit — the fallback then saw "0 people
	// removed" for every rate group no matter how many the admin actually removed, so selecting
	// ANY add-on for the person being removed always looked like "more add-ons than people
	// removed" and blocked the refund, even when the selection was exactly correct.
	totalDeletedCountByRateGroupId = {};
	if (deletedPeopleCount > 0) {
		var activeRateGroup = rateGroups.find(function (rg) { return rg.tourists > 0; });
		if (activeRateGroup && activeRateGroup.id) {
			totalDeletedCountByRateGroupId[String(activeRateGroup.id)] = deletedPeopleCount;
		}
	}

	if (packageHasContacts == 1) {
		if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount > 0) {
			tourBookingInfoContacts.changePeopleResetDeleteContactPopup();
		} else {
			if (totalDeletedPeopleCount > 0 && totalAddedPeopleCount > 0) {
				tourBookingInfoContacts.changePeopleResetDeleteContactPopup();
				tourBookingInfoContacts.changePeopleEditContactsValidation(true);
			} else {
				tourBookingInfoContacts.editBookingChangePeopleRenderNewContactsPopup(touristsGroups, rateGroups);
			}
		}
	}

	$('#payment_amount').val(totalPaymentAmount.toFixed(2));
	$('#refund_amount').val(totalRefundAmount.toFixed(2));
	$(form).data('validation_check', true);
	if (paymentMethod == 'Pay Later') {
		$(form).data('unpaid', true);
	}

	validateAndSubmitFormGroupChangePeople(form);
	return false;
}

function validateAndSubmitFormGroupChangePeople(form, additionalForm) {
	var submitBtn = $('#btn-make-payment')[0],
		includeValidationCheckField = false,
		includePaymentCheckField = false,
		formObj = $(form),
		formData;

	if (typeof additionalForm === "undefined") {
		includeValidationCheckField = true;
		submitBtn = $('#update_booking_change_people_submit')[0];
	} else {
		includeValidationCheckField = true;
		formObj = formObj.add(additionalForm);
	}

	if (paymentMethod == 'Pay Later') {
		includePaymentCheckField = true;
	}

	
	if (paymentMethod == 'Cash' && $(form).data('validation_check') == false && finalAmount >= 0) {
		if (isAffiliate == 'No') {
			var paidCashAmount = $('#cash-paid-amount').val();
		} else if (isAffiliate == 'Yes') {
			var paidCashAmount = $('#cash-paid-amount1').val();
		}
		if (cashAmountChangePeople > 0 && paidCashAmount != '' && (paidCashAmount == 0 || Number(paidCashAmount) < Number(cashAmountChangePeople))) {
			swal('Paid amount should be greator than or equal to total', '', 'warning');
			return false;
		}
	}

	// Unlike its non-group sibling (validateAndSubmitFormChangePeople()), this function never
	// attached changePeopleV2's addons_added[]/addons_updated[]/addons_removed[] hidden inputs
	// to the form before serializing it — so a group-rate add-ons-only edit reached the server
	// with NO addon fields at all, making $hasAddonChanges evaluate false there and throw "You
	// must need to select tourists to change." even though changePeopleV2.hasChanges() (checked
	// client-side above, in showMakePaymentForEditGroupBookingChangePeople()) correctly saw a
	// real change. Mirrors validateAndSubmitFormChangePeople()'s own call exactly.
	if (typeof changePeopleV2 !== 'undefined' && changePeopleV2.appendAddonsToForm) {
		changePeopleV2.appendAddonsToForm($(form));
	}

	formData = $(formObj).serialize();

	if (packageHasContacts == 1) {
		formData = tourBookingInfoContacts.changePeopleDeleteAndNewContactsValidation(formData);

		if (!formData) {
			return false;
		}
	}

	submitBtn.disabled = true;

	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: form.action,
		data: formData + (includeValidationCheckField ? ('&validation_check=' + $(form).data('validation_check')) : ''),
	}).done(function (response) {
		if (paymentMethod == 'Pay Later' || (totalInfantsOrChilds > 0 && totalPaymentAmount == 0 && totalRefundAmount == 0) || (totalPaymentAmount.toFixed(2) == totalRefundAmount.toFixed(2)) || finalAmount.toFixed(2) == -0.00) {
			swal('Booking updated successfully', 'Page will reload', 'success')
				.then(function () {
					location = location;
				});
		} else {
			if ($(form).data('validation_check')) {
				submitBtn.disabled = false;
				if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount > 0) {
					if (packageHasContacts == 1) {
						tourBookingInfoContacts.changePeopleEditContactsValidation();
					} else {
						$('#deleteBooking').data('call-by-edit-booking-change-people', true).modal('show');
					}
				} else {
					if (packageHasContacts == 1) {
						$('#edit_booking_add_contacts').data('prevent-hide-callback', true).modal('hide');

						if (totalDeletedPeopleCount > 0) {
							var contactIds = $("input[name='deleted_contact_id[]']").map(function () {
								if ($(this).val()) {
									return $(this).val();
								}
							}).get();

							if (contactIds.length == 0) {
								return false;
							}
						}
					}
					if (finalAmount >= 0) {
						if (isAffiliate == 'Yes') {
							$('#modal_affilate').data('call-by-edit-booking-change-people', true).modal('show');
							if (paymentMethod == 'Cash') {
								$('#cash-paid-amount1').prop("readonly", false);
							} else {
								$('#cash-paid-amount1').prop("readonly", true);
							}
							$('#model_affiliate .split-payment-div').hide();
						} else {
							$('#modal_admin').data('call-by-edit-booking-change-people', true).modal('show');
							if (paymentMethod == 'Cash') {
								$('#cash-paid-amount').prop("readonly", false);
							} else {
								$('#cash-paid-amount').prop("readonly", true);
							}
							$('#modal_admin .split-payment-div').hide();
						}
					} else if (finalAmount < 0) {
						processRefund(formData, form.action);
						if (paymentMethod != 'Card' || (paymentMethod == 'Card' && paymentTransCount == 1)) {
							swal('Booking updated successfully', 'Page will reload', 'success')
								.then(function () {
									location = location;
								});
						}
					}
				}
				$('#edit_booking_change_people').data('prevent-hide-callback', true).modal('hide');
			} else {
				var pathname = window.location.href;
				var spliturlCheck = pathname.slice(-7);
				var splitslice = spliturlCheck.slice(0, 5);

				if (paymentMethod == 'Card') {
					if (response.errors && response.errors.length) {
						if (isAffiliate == 'Yes') {
							$('#btn-make-payment')[0].disabled = false;
						} else {
							$('#btn-make-payment1')[0].disabled = false;
						}

						if (response.errors.indexOf('payment') != -1)
							swal('Payment initialization error', response.errors_message, 'error');

						if (typeof (response.errors) != "undefined") {
							hideLoading();
							if (typeof (response.errors_message) != "undefined") {
								response.errors = response.errors_message;
							}
							swal('Error(s) Occurred', response.errors, 'error');
							showErrorsInHistory(response);
							submitBtn.disabled = false;
						}

					} else {
						if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
							window.location.href = response.nextAction.redirect_to_url.url;
						} else {
							swal('Booking updated successfully', 'Page will reload', 'success')
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
						if (isAffiliate == 'No') {
							var paidCashAmount = $('#cash-paid-amount').val();
						} else if (isAffiliate == 'Yes') {
							var paidCashAmount = $('#cash-paid-amount1').val();
						}
						if (Number(cashAmountChangePeople) > 0 && paidCashAmount > 0 && Number(paidCashAmount) >= Number(cashAmountChangePeople)) {
							var changeAmount = paidCashAmount - cashAmountChangePeople;
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
													Booking Payment Done.
												</div>
											`;
								swal({
									icon: "success",
									content: elemdiv
								}).then(function () {
									location = location;
								})
							} else {
								swal('Booking updated successfully', 'Page will reload', 'success')
									.then(function () {
										location = location;
									});
							}

						} else {
							swal('Booking updated successfully', 'Page will reload', 'success')
								.then(function () {
									location = location;
								});
						}
					} else {
						swal('Booking updated successfully', 'Page will reload', 'success')
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
			submitBtn.disabled = false;
		})
		.always(hideLoading);

	return false;
}

function processRefund(formData, url) {
	if (paymentMethod == 'Card' && paymentTransCount > 1) {
		$('#updateBooking').data('call-by-edit-booking-change-people', true).modal('show');
		return false;
	}
	var includeValidationCheckField = false;
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: url,
		data: formData + '&validation_check=' + false,
	})
		.done(function (response) {
			//submitBtn.disabled = false;
		})
		.fail(function (jqXHR) {
			hideLoading();
			showSubmissionError(jqXHR);
			//submitBtn.disabled = false;
		})
		.always(hideLoading);
}

function applyDiscountChangeGroupPeople() {
	var discount = 0;
	var discount_card = 0;
	var discount_cash = 0;
	var serviceCommission = 0;
	if (Number(bookingId) < Number(oldBookingId)) {
		if (isAffiliate == 'Yes') {
			var span_invoiced = finalAmount;
			var span_cash = finalAmount;
			var span_card = finalAmount;
		} else {
			var span_invoiced = finalAmount;
			var span_cash = finalAmount;
			serviceCommission = roundout((finalAmount * serviceCommissionPercentage / 100), 2);
			var span_card = finalAmount + serviceCommission;
		}
	} else {
		var span_invoiced = finalAmount;
		var span_cash = finalAmount;
		if (getDiscountStatus(finalAmount) > 0) {
			serviceCommission = roundout((finalAmount * serviceCommissionPercentage / 100), 2);
		} else {
			serviceCommission = serviceCommissionTotal;
		}
		var span_card = finalAmount + serviceCommission;
	}

	if (($('#discount_type_percentage').length > 0 && $('#discount_type_fixed_money').length > 0) && ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {
		if ($('#discount_type_percentage')[0]?.checked) {
			var inputVal = parseFloat($('#discount_percentage').val());

			if (isNaN(inputVal)) // blank or something else
				inputVal = 0;

			discount = (span_invoiced * inputVal) / 100;
			discount_cash = (span_cash * inputVal) / 100;
			// discount_card = (span_card * inputVal) / 100;
			if (Number(bookingId) < Number(oldBookingId)) {
				discount_card = (span_card * inputVal) / 100;
			} else {
				discount_card = span_cash * inputVal / 100;
			}
			if (discount < 0 || discount_cash < 0 || discount_card < 0) {
				$('#discount_percentage').val('');
				applyDiscountChangeGroupPeople();
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

			if (isNaN(inputVal)) {
				discount = discount_cash = discount_card = 0;
			} else {
				discount = discount_cash = discount_card = inputVal;
			}

			$('#pr_amount').val(discount);
		}
	}

	if (discount_cash > span_cash || discount > span_invoiced) {
		$('#discount_fixed_money').val('');
		$('#discount_percentage').val('');
		discount = 0;
		discount_cash = 0;
	}

	span_invoiced = span_invoiced - discount;
	span_cash = span_cash - discount_cash;
	if (Number(bookingId) < Number(oldBookingId)) {
		span_card = span_card - discount_card;
	} else {
		span_card = span_cash + roundout((span_cash * natServiceCommission / 100), 2);
		if (isCashFees == '1') {
			span_cash = span_card;
		}
	}
	// Captured after the cash-fees adjustment so the paid-amount check and the "Change" popup
	// use the same cash total "Pay with Cash" displays.
	cashAmountChangePeople = span_cash.toFixed(2);

	if (isAffiliate == 'Yes') {
		$('#span_invoiced').text(currencyFormation.symbol + span_invoiced.toFixed(2));
		$('#span_cash').text(currencyFormation.symbol + span_cash.toFixed(2));
		$('#span_card').text(currencyFormation.symbol + span_card.toFixed(2));
		$('.link-cp-affiliate-amount').text(currencyFormation.symbol + span_card.toFixed(2));
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
	}
	;

	if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
		$('#htt').val(span_cash);
		$('#totalcost').text(currencyFormation.symbol + span_cash.toFixed(2));
	}

	if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
		$('#htt').val(span_card);
		$('#totalcost').text(currencyFormation.symbol + span_card.toFixed(2));
	}

	if ($('input[type=radio][name=payment_method]:checked').val() == 'Payment Link') {
		$('#htt').val(span_card);
		$('#totalcost').text(currencyFormation.symbol + span_card.toFixed(2));
	}
}

function roundout(amount, places = 2) {
	if (places < 0) places = 0;
	let factor = Math.pow(10, places);
	return Math.round(amount * factor) / factor;
}

function getDiscountStatus(groupTotal) {
	var discountTotal = 0;

	if (($('#discount_type_percentage').length > 0 && $('#discount_type_fixed_money').length > 0) && ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {

		if ($('#discount_type_percentage')[0]?.checked) {

			var inputVal = parseFloat($('#discount_percentage').val());

			if (isNaN(inputVal)) // blank or something else
				inputVal = 0;

			discountTotal = (groupTotal * inputVal) / 100;
		} else {
			var inputVal = parseFloat($('#discount_fixed_money').val());

			if (isNaN(inputVal))
				discountTotal = 0;
			else
				discountTotal = inputVal;
		}
	}

	return discountTotal;
}
