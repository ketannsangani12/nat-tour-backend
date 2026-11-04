var affiliateCheck = 0;
var lookupForCashAndPayLaterPaymentMethod = 0;
var lookupForCashPaymentMethod = 0;
var cashAmount = 0;
var serviceCommissionTotal = 0;
var serviceCommissionTotalCashNewPricing = 0;
var overBookSeats = 50;
var totalTourists = 0;
var totalSeatsForBooking = 0;
var totalBeforeDiscountAndServiceCommissionPackage = 0;
var lookupForCashAndPayLaterPaymentMethodPackage = 0;
var lookupForCashPaymentMethodPackage = 0;
var serviceCommissionPackage = 0;
var selectedTouristGroupId = '';
var cachedPromoDetails = null;

function assignVarsForGroupRates(data) {
	paxWiseGroupRateIds = data.paxWiseGroupRateIds;
	paxWiseGroupRates = data.paxWiseGroupRates;
	paxWiseGroupAdditionalCharges = data.paxWiseGroupAdditionalCharges;
	paxWiseGroupCashRates = data.paxWiseGroupCashRates;
	paxWiseGroupCashTaxes = data.paxWiseGroupCashTaxes;
}

function setGroupRates() {
	var paymentMethod = $('.checkset:checked').val();
	var $tblRateGrps = $('#table-rate-groups');
	$('.rategrpth').hide();
	$('.rategrptd').hide();
	$tblRateGrps.html('');
	var a = 0;
	if (rateGroups.length) {
		var $tmpl = $('#tmpl-rate-group tr').clone(true);
		$hidRateGroup = $tmpl.find('.hid-rate-group');
		$hidRateGroup.attr('name', $hidRateGroup.data('name'));
		$selTourists = $tmpl.find('.tourists');
		$selTourists.select2();
		$selTourists.attr('name', $selTourists.data('name'));
		$selTourists.attr('id', 'tourists-' + rateGroups[a].id);

		$tmpl.find('.age').html('<span class="rate-for">' + encLT(rateGroups[a].rate_for) + '</span>');
		$tmpl.find('.price .tag').text(currencyFormation.symbol + rateGroups[a].rate);
		$tmpl.find('.adnl-charge').text('$0.00');
		$tblRateGrps.append($tmpl);
		totalSansDiscount = 0;
		updateGroupRatesTable();
	}

}

function showSlotInfoForGroupRates(selSlotTime) {
	// Check if `applyOnSlots` is true and the slot is not already processed	
	if (applyOnSlots && selSlotTime.value) {
		// Find the object with matching ID
		const selectedSlot = slotTimes.find(slot => slot.id == selSlotTime.value);
		fetchSlotBasedRateGroups(selSlotTime, selectedSlot.custom_rate); // Call function only for new selection
	} else {
		groupRateManipulateRateGroups(selSlotTime);
	}
}

function groupRateManipulateRateGroups(selSlotTime) {
	showSelectionInfo();
	// A new slot's headcount options reset to 0 here, and the first genuine re-selection could
	// coincidentally match whatever headcount the PREVIOUS slot last had — without this reset,
	// AddonFormReader's headcount-changed guard (see initAddonForm() in form-reader.js, added to
	// stop a payment-method switch from reopening the add-ons popup) would wrongly treat that
	// first real selection as a no-op echo and skip auto-opening it.
	if (typeof addonFormReader !== 'undefined') addonFormReader._lastGroupRatePaxCount = null;
	$('#table-rate-groups .tourists').html('<option value="0">' + currency(0).format() + ' - 0</option>');
	$('#table-rate-groups .adnl-charge').text('$0.00');
	if (selSlotTime.selectedIndex == 0) {
		$('#span-seats-info').html('0 of 0 left');
		$('.tour-process').css('width', '50%');
		return;
	}

	var $opt = $(selSlotTime.options[selSlotTime.selectedIndex]);

	var tourists = $opt.data('tourists');
	var seats = $opt.data('seats');

	if (userType == 'Affiliate') {
		var emptySeats = $opt.data('booked');
	} else {
		var emptySeats = seats - tourists;
	}

	if (typeof emptySeats != 'undefined' && typeof seats != 'undefined') {
		$('#span-seats-info').html(emptySeats + ' of ' + seats + ' left');
	}
	$('.tour-process').css('width', ((tourists * 100) / seats) + '%');

	$('#table-rate-groups .tourists').each(function () {
		addTouristsOptsForGroupRates($(this), emptySeats + overBookSeats);
	});
	totalSeatsForBooking = emptySeats + overBookSeats;

	var slot_time = $("#slot_time").val();
	$("#selected_slotTime").val(slot_time);
	updateGroupRatesTable();
}

function addTouristsOptsForGroupRates($selTourists, emptySeats) {
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

function updateGroupRatesTable() {

	var paymentMethod = $('.checkset:checked').val();
	var tourPackageId = $('#tour_package').val();
	$('.discount-applied').val(0);
	$('.sub-total, .fees-total, .grand-total').text('$0.00');

	if ($('#slot_time').val() == 0) {
		$('#table-rate-groups .tourists').html('<option>0</option>');
		return;
	}

	var cashPayment = (paymentMethod == 'Cash') ? 1 : 0;
	var $tblRateGrps = $('#table-rate-groups');
	var total = 0;
	var lookup = 0;
	var cashLookup = 0;
	serviceCommissionTotal = 0;
	serviceCommissionTotalCashNewPricing = 0;
	serviceCommissionPackage = 0;
	var touristsTotal = 0;
	var a = 0;

	$row = $($tblRateGrps.children()[a]);

	var $selTourists = $row.find('.tourists');
	var oldTourists = 0;

	for (var b = 0; b < rateGroups.length; b++) {
		if (rateGroups[b].cart_tourists > 0) {
			oldTourists = rateGroups[b].cart_tourists;
		}
	}

	if (oldTourists > 0 && $selTourists.val() == 0) {
		$selTourists.val(oldTourists);
	}

	if ($selTourists.val() == null) {
		$selTourists.find('option').prop('selected', true);
		var tourists = 0;
	} else {
		var tourists = parseInt($selTourists.val());
	}

	touristsTotal += tourists;

	var groupTotal = 0;
	var groupCashTotal = 0;
	var additionalCharge = 0;
	var lookTotal = 0;
	var cashLookTotal = 0;
	var rateGrpId = $selTourists.data('rategrp');
	if (Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0 && tourists > 0) {
		var lastelement = Object.keys(paxWiseGroupRates)[Object.keys(paxWiseGroupRates).length - 1];
		if (paxWiseGroupRates[tourists] !== undefined && paxWiseGroupRateIds[tourists] !== undefined && paxWiseGroupAdditionalCharges[tourists] !== undefined) {
			additionalCharge = paxWiseGroupAdditionalCharges[tourists];
			groupTotal = paxWiseGroupRates[tourists] + additionalCharge;
			rateGrpId = paxWiseGroupRateIds[tourists];
			var cashTax = (paxWiseGroupCashTaxes && paxWiseGroupCashTaxes[tourists] !== undefined) ? paxWiseGroupCashTaxes[tourists] : additionalCharge;
			groupCashTotal = (paxWiseGroupCashRates && paxWiseGroupCashRates[tourists] !== undefined) ? paxWiseGroupCashRates[tourists] + cashTax : groupTotal;
		} else {
			additionalCharge = paxWiseGroupAdditionalCharges[lastelement];
			groupTotal = paxWiseGroupRates[lastelement] + additionalCharge;
			rateGrpId = paxWiseGroupRateIds[lastelement];
			var cashTax = (paxWiseGroupCashTaxes && paxWiseGroupCashTaxes[lastelement] !== undefined) ? paxWiseGroupCashTaxes[lastelement] : additionalCharge;
			groupCashTotal = (paxWiseGroupCashRates && paxWiseGroupCashRates[lastelement] !== undefined) ? paxWiseGroupCashRates[lastelement] + cashTax : groupTotal;
		}
		if (cashPayment == 1 && cashPriceEnabled == 1) {
			$row.find('.adnl-charge').text(currencyFormation.symbol + cashTax.toFixed(2));
		} else {
			$row.find('.adnl-charge').text(currencyFormation.symbol + additionalCharge.toFixed(2));
		}
	}
	if (tourists == 0) {
		$row.find('.adnl-charge').text(currencyFormation.symbol + '0.00');
	}
	$hidRateGroup = $row.find('.hid-rate-group');
	$hidRateGroup.val(rateGrpId);


	if (tourists) {

		if (getDiscountStatus(groupTotal) == 0) {
			serviceCommissionPackage = parseFloat((parseFloat(groupTotal) * serviceCommissionPercentage / 100).toFixed(2));
			serviceCommissionTotal = parseFloat((parseFloat(groupTotal) * serviceCommissionPercentage / 100).toFixed(2));
			serviceCommissionTotalCashNewPricing = parseFloat((parseFloat(groupCashTotal) * serviceCommissionPercentage / 100).toFixed(2));
		}

		lookTotal = groupTotal;
		cashLookTotal = groupCashTotal;
	}
	if (groupTotal) {
		if (paymentMethod === 'Card') {
			$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
		} else if (cashPayment == 1 && cashPriceEnabled == 1) {
			$row.find('.final').text(currencyFormation.symbol + groupCashTotal.toFixed(2));
		} else {
			$row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
		}
	} else {
		$row.find('.final').text(currencyFormation.symbol + '0.00');
	}

	total = groupTotal;
	lookup = lookTotal;
	cashLookup = cashLookTotal;

	totalTourists = touristsTotal;

	//Display ticket cost of package selected at the end of rategroups table

	if (total) {
		if (paymentMethod === 'Card') {
			$('.total-before-service-commission-and-discount-package').text(currencyFormation.symbol + total.toFixed(2));
		} else if (cashPayment == 1 && cashPriceEnabled == 1) {
			$('.total-before-service-commission-and-discount-package').text(currencyFormation.symbol + cashLookup.toFixed(2));
		} else {
			$('.total-before-service-commission-and-discount-package').text(currencyFormation.symbol + lookup.toFixed(2));
		}
	} else {
		$('.total-before-service-commission-and-discount-package').text(currencyFormation.symbol + '0.00');
		$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + '0.00');
		$('.service-commission').text(currencyFormation.symbol + '0.00');
		$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + '0.00');
		$('.cash-set').text(currencyFormation.symbol + '0.00');
		$('.total-').val('0.00');
		$('.fee-').val('0.00');
		$('.custom-charge-').val('0.00');
	}
	totalBeforeDiscountAndServiceCommissionPackage = total;
	lookupForCashAndPayLaterPaymentMethodPackage = lookup;
	lookupForCashPaymentMethodPackage = cashLookup;

	if (cartView == 1) {
		//Cart packages(Which are already in cart) total added in new package selected
		if (tourPackageId != '' && $('#cart-package-' + tourPackageId).length == 0) {
			//If package not in cart
			total += totalCartTicketCost;
			lookup += totalCartTicketCost;
			cashLookup += totalCartCashTicketCost;
			serviceCommissionTotal += totalCartServiceCommission;
			serviceCommissionTotalCashNewPricing += totalCartCashServiceCommission;
		} else {
			//If package is already in cart
			var cartPackageDetails = getCartPackageDetails(tourPackageId);

			if (Object.keys(cartPackageDetails).length > 0) {
				if (total > cartPackageDetails.subtt) {
					lookup = total = total - cartPackageDetails.subtt;
					total += totalCartTicketCost;
					lookup += totalCartTicketCost;
					cashLookup = totalCartCashTicketCost;
					if (serviceCommissionTotal > cartPackageDetails.fee) {
						serviceCommissionTotal = serviceCommissionTotal - cartPackageDetails.fee;
						serviceCommissionTotal += totalCartServiceCommission;
					}
					serviceCommissionTotalCashNewPricing = totalCartCashServiceCommission;
				} else if (total < cartPackageDetails.subtt) {
					lookup = total = cartPackageDetails.subtt - total;
					lookup = total = totalCartTicketCost - total;
					cashLookup = totalCartCashTicketCost;
					if (serviceCommissionTotal < cartPackageDetails.fee) {
						serviceCommissionTotal = cartPackageDetails.fee - serviceCommissionTotal;
						serviceCommissionTotal = totalCartServiceCommission - serviceCommissionTotal;
					}
					serviceCommissionTotalCashNewPricing = totalCartCashServiceCommission;
				} else {
					total = totalCartTicketCost;
					lookup = totalCartTicketCost;
					cashLookup = totalCartCashTicketCost;
					serviceCommissionTotal = totalCartServiceCommission;
					serviceCommissionTotalCashNewPricing = totalCartCashServiceCommission;
				}
			} else {
				total = totalCartTicketCost;
				lookup = totalCartTicketCost;
				cashLookup = totalCartCashTicketCost;
				serviceCommissionTotal = totalCartServiceCommission;
				serviceCommissionTotalCashNewPricing = totalCartCashServiceCommission;
			}

		}
	}

	totalBeforeDiscountAndServiceCommission = total;
	lookupForCashAndPayLaterPaymentMethod = lookup;
	lookupForCashPaymentMethod = cashLookup;

	if (total) {
		if (paymentMethod === 'Card') {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + total.toFixed(2));
		} else if (cashPayment == 1 && cashPriceEnabled == 1) {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + cashLookup.toFixed(2));
		} else {
			$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + lookup.toFixed(2));
		}
	} else {
		$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + '0.00');
		$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + '0.00');
		$('.service-commission').text(currencyFormation.symbol + '0.00');
		$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + '0.00');
		$('.cash-set').text(currencyFormation.symbol + '0.00');
		$('.total-').val('0.00');
		$('.fee-').val('0.00');
		$('.custom-charge-').val('0.00');
	}

	let addonPrice = (totalCartAddonsPrice - (window.editingCartPackageAddonPrice || 0)) + initTourBookingAddCheckoutAddons.getPrice() + (typeof addonFormReader !== 'undefined' ? addonFormReader.getPrice() : 0);
	$('.addons-price-input').val(addonPrice);
	$('.addons-price-display').text(currencyFormation.symbol + addonPrice.toFixed(2));

	var discountType = $("input[name='discount_type']:checked").val();
	if (discountType == 'Promo Code' && $("#is_applied").val() == 1 && totalTourists != 0) {
		if (cachedPromoDetails) {
			applyPromoDiscountCalculation(cachedPromoDetails);  // ← sync, no flicker
		} else {
			applyPromoDiscount();   // fallback: first time or cache missing
		}
	} else {
		applyDiscountGroupRate();
	}
}

function applyDiscountGroupRate() {
	var paymentMethod = $('.checkset:checked').val();
	var cashPayment = (paymentMethod == 'Cash') ? 1 : 0;
	var discount = 0;
	var totalAfterDiscountBeforeServiceCommission = totalBeforeDiscountAndServiceCommission;
	var totalAfterDiscountBeforeServiceCommissionCash = lookupForCashPaymentMethod;
	var lookupAfterDiscountBeforeServiceCommission = lookupForCashAndPayLaterPaymentMethod;
	var lookupAfterDiscountBeforeServiceCommissionCash = lookupForCashPaymentMethod;
	var totalAfterDiscountBeforeServiceCommissionPackage = totalBeforeDiscountAndServiceCommissionPackage;
	var currentPackageTotalAfterDiscountBeforeServiceCommission = totalBeforeDiscountAndServiceCommissionPackage;
	var lookupAfterDiscountBeforeServiceCommissionPackage = lookupForCashAndPayLaterPaymentMethodPackage;
	var discountForCard = 0;
	var discountForCashAndPayLater = 0;
	var discountForNewCashPrice = 0;

	//Check for old discount
	var oldDiscount = 0;
	var oldDiscountCash = 0;
	var otherPackageDiscount = 0;
	var otherPackageDiscountPercentage = 0;
	var otherPackageDiscountCash = 0;
	var tourPackageId = $('#tour_package').val();

	if (cartView == 1) {
		var cartPackageDetails = getCartPackageDetails(tourPackageId);

		if (Object.keys(cartPackageDetails).length > 0) {
			oldDiscount = cartPackageDetails.discount_amount;
			oldDiscountCash = cartPackageDetails.discount_amount_cash;
		}

		for (var a = 0; a < cartPackages.length; a++) {
			if (cartPackages[a].tour_package_id != tourPackageId) {
				otherPackageDiscount = cartPackages[a].discount_amount;
				otherPackageDiscountPercentage = cartPackages[a].discount_percentage;
				otherPackageDiscountCash = cartPackages[a].discount_amount_cash;
			}
		}
	}

	if (totalBeforeDiscountAndServiceCommission && ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {
		if ($('#discount_type_percentage')[0]?.checked) {

			var inputVal = $('#discount_percentage').val();
			if (isNaN(inputVal)) // blank or something else
				inputVal = 0;

			var subtotal = ($("#sub-total").text());
			subtotal = subtotal.slice(1);
			subtotal = parseFloat(subtotal);


			if (cartView == 1) {

				if (Object.keys(cartPackageDetails).length > 0) {
					currentPackageSubTotal = cartPackageDetails.subtt;
					currentPackageSubTotalCash = cartPackageDetails.cashSubtt;
					discountForCashAndPayLater = ((currentPackageSubTotal) * inputVal) / 100;
					discountForCard = (currentPackageSubTotal * inputVal) / 100;
					discountForNewCashPrice = (currentPackageSubTotalCash * inputVal) / 100;
				} else {
					discountForCashAndPayLater = (lookupAfterDiscountBeforeServiceCommissionPackage * inputVal) / 100;
					discountForCard = (totalAfterDiscountBeforeServiceCommissionPackage * inputVal) / 100;
					discountForNewCashPrice = (lookupForCashPaymentMethodPackage * inputVal) / 100;
				}

				discountForCard += otherPackageDiscount;
				discountForCashAndPayLater += otherPackageDiscount;
				discountForNewCashPrice += otherPackageDiscountCash;

			} else {
				discountForCashAndPayLater = (lookupForCashAndPayLaterPaymentMethod * inputVal) / 100;
				discountForCard = (totalBeforeDiscountAndServiceCommission * inputVal) / 100;
				discountForNewCashPrice = (lookupForCashPaymentMethod * inputVal) / 100;
			}

			totalAfterDiscountBeforeServiceCommissionPackage -= discountForCard;
			totalAfterDiscountBeforeServiceCommission -= discountForCard;
			lookupAfterDiscountBeforeServiceCommission -= discountForCashAndPayLater;
			lookupAfterDiscountBeforeServiceCommissionCash -= discountForNewCashPrice;
			totalAfterDiscountBeforeServiceCommissionCash -= discountForNewCashPrice;

			if (paymentMethod != 'Card') {
				if (cashPayment == 1 && cashPriceEnabled == 1) {
					discount = discountForNewCashPrice;
				} else {
					discount = discountForCashAndPayLater;
				}
			} else {
				discount = discountForCard;
			}

			if (inputVal == "") {
				var TotalDiscount = parseFloat(parseFloat(otherPackageDiscountPercentage).toFixed(2));
			} else {
				var TotalDiscount = parseFloat((parseFloat(inputVal) + parseFloat(otherPackageDiscountPercentage)).toFixed(2));
			}

			$('.discount-info').text('(' + TotalDiscount + '%)');

		} else {
			var inputVal = parseFloat($('#discount_fixed_money').val());
			if (cartView == 1) {
				//check if old discount for the package and editing cart
				if (isNaN(inputVal) || (oldDiscount > 0 && totalCartDiscount > 0 && oldDiscount == inputVal)) {
					discount = 0;
				} else {
					discount = inputVal;
				}
				//If old discount for package and if discount decrease
				if (totalCartDiscount > 0 && oldDiscount > 0 && oldDiscount != inputVal && oldDiscount > discount) {
					totalCartDiscount -= oldDiscount;
				}

				if (totalCartDiscount > 0) {
					discount += totalCartDiscount;
				}
			} else {
				if (isNaN(inputVal))
					discount = 0;
				else
					discount = inputVal;
			}
			$('.discount-info').text('');

			totalAfterDiscountBeforeServiceCommissionPackage -= discount;
			totalAfterDiscountBeforeServiceCommission -= discount;
			lookupAfterDiscountBeforeServiceCommission -= discount;
			lookupAfterDiscountBeforeServiceCommissionCash -= discount;
			totalAfterDiscountBeforeServiceCommissionCash -= discount;
		}
	} else if (cartView == 1 && totalCartDiscount > 0) {
		$('.discount-info').text('(' + parseFloat(parseFloat(otherPackageDiscountPercentage).toFixed(2)) + '%)');
		totalCartDiscount = discount = otherPackageDiscount;
		totalCartDiscountCash = otherPackageDiscountCash;
		if (cashPayment == 1 && cashPriceEnabled == 1) {
			discount = totalCartDiscountCash;
		}
		totalAfterDiscountBeforeServiceCommission -= totalCartDiscount;
		lookupAfterDiscountBeforeServiceCommission -= totalCartDiscount;
		lookupAfterDiscountBeforeServiceCommissionCash -= totalCartDiscountCash;
		totalAfterDiscountBeforeServiceCommissionCash -= totalCartDiscountCash;
	} else {
		totalCartDiscount = otherPackageDiscount;
		totalCartDiscountCash = otherPackageDiscountCash;
		totalAfterDiscountBeforeServiceCommission -= totalCartDiscount;
		lookupAfterDiscountBeforeServiceCommission -= totalCartDiscount;
		lookupAfterDiscountBeforeServiceCommissionCash -= totalCartDiscountCash;
		totalAfterDiscountBeforeServiceCommissionCash -= totalCartDiscountCash;
		$('.discount-info').text('(0%)');
	}

	var serviceCommission = 0;
	var serviceCommissionCash = 0;
	var discountType = '';

	if ($('#discount_type_percentage')[0]?.checked) {
		var discountType = 'Percent';
	} else if ($('#discount_type_fixed_money')[0]?.checked) {
		var discountType = 'Fixed Money';
	}

	if (isNaN(inputVal)) // blank or something else
		inputVal = 0;

	const addOnFee = initTourBookingAddCheckoutAddons.getFees() + (typeof addonFormReader !== 'undefined' ? addonFormReader.getFees() : 0);
	serviceCommission = calculateServiceCommission(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommission, oldDiscount, 'applyDiscount');
	serviceCommission = updateCustomChargeFees(serviceCommission);
	serviceCommission = parseFloat((serviceCommission + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + customChargeFeesTotal).toFixed(2));

	serviceCommissionCash = calculateServiceCommissionCashNewPricing(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommissionCash, oldDiscount, 'applyDiscount');
	serviceCommissionCash = updateCustomChargeFees(serviceCommissionCash);
	serviceCommissionCash = parseFloat((serviceCommissionCash + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + customChargeFeesTotal).toFixed(2));

	updateUIWithDiscount(cashFees, serviceCommission, serviceCommissionCash, lookupAfterDiscountBeforeServiceCommission, lookupAfterDiscountBeforeServiceCommissionCash, paymentMethod, totalAfterDiscountBeforeServiceCommission, discount, cartView, totalCartDiscount, subtotal);
}

function applyPromoDiscountGroupRate() {
	var promoCode = $("#discount_promo_code").val();
	var timeSloat = $('#slot_time').val();
	var tourPackageId = $('#tour_package').val();

	if (!promoCode) {
		swal('Please enter promo code', '', 'warning');
		return;
	}

	if (!tourPackageId) {
		swal('Please select tour package', '', 'warning');
		return;
	}

	if (!timeSloat) {
		swal('Please select time slot', '', 'warning');
		return;
	}

	if (totalTourists == 0) {
		swal('Please select group of people', '', 'warning');
		return;
	}

	$.ajax({
		url: "get-promo-detail",
		// 'tourists' lets the backend enforce the promo's own min_allowed_tourists (infants
		// excluded, matching totalTourists' own already-infant-excluded convention) -- without
		// it the server has no way to know how many people this in-progress booking has.
		data: { 'promoCode': promoCode, 'tourPackageId': tourPackageId, 'tourSlotId': timeSloat, 'tourists': totalTourists },
		type: 'POST',
		dataType: 'json',
		success: function (result) {
			$("#is_applied").val(1);
			cachedPromoDetails = result;
			applyPromoDiscountGroupRateCalculation(result);
		},
		error: function (jqXHR) {
			showSubmissionError(jqXHR);
		}
	});
}

function applyPromoDiscountGroupRateCalculation(promoDetails) {

	var paymentMethod = $('.checkset:checked').val();
	var cashPayment = (paymentMethod == 'Cash') ? 1 : 0;
	var discount = 0;
	var totalAfterDiscountBeforeServiceCommission = totalBeforeDiscountAndServiceCommission;
	var totalAfterDiscountBeforeServiceCommissionCash = lookupForCashPaymentMethod;
	var lookupAfterDiscountBeforeServiceCommission = lookupForCashAndPayLaterPaymentMethod;
	var lookupAfterDiscountBeforeServiceCommissionCash = lookupForCashPaymentMethod;
	var totalAfterDiscountBeforeServiceCommissionPackage = totalBeforeDiscountAndServiceCommissionPackage;
	var currentPackageTotalAfterDiscountBeforeServiceCommission = totalBeforeDiscountAndServiceCommissionPackage;
	var lookupAfterDiscountBeforeServiceCommissionPackage = lookupForCashAndPayLaterPaymentMethodPackage;
	var discountForCard = 0;
	var discountForCashAndPayLater = 0;
	var discountForNewCashPrice = 0;
	var subtotal = 0;
	var inputVal = 0;

	if (promoDetails.discount_value_type) {

		inputVal = parseFloat(promoDetails.discount_value);

		if (isNaN(inputVal)) // blank or something else
			inputVal = 0;

		if (promoDetails.discount_value_type === 'Percent') {

			subtotal = parseFloat(($("#sub-total").text()).slice(1));

			discountForCashAndPayLater = (lookupForCashAndPayLaterPaymentMethod * inputVal) / 100;
			discountForCard = (totalBeforeDiscountAndServiceCommission * inputVal) / 100;
			discountForNewCashPrice = (lookupForCashPaymentMethod * inputVal) / 100;

			totalAfterDiscountBeforeServiceCommissionPackage -= discountForCard;
			totalAfterDiscountBeforeServiceCommission -= discountForCard;
			lookupAfterDiscountBeforeServiceCommission -= discountForCashAndPayLater;
			lookupAfterDiscountBeforeServiceCommissionCash -= discountForNewCashPrice;
			totalAfterDiscountBeforeServiceCommissionCash -= discountForNewCashPrice;

			if (paymentMethod != 'Card') {
				if (cashPayment == 1 && cashPriceEnabled == 1) {
					discount = discountForNewCashPrice;
				} else {
					discount = discountForCashAndPayLater;
				}
			} else {
				discount = discountForCard;
			}

			$('.discount-info').text('(' + inputVal + '%)');

		} else {

			if (isNaN(inputVal))
				discount = 0;
			else
				discount = inputVal;

			$('.discount-info').text('');

			totalAfterDiscountBeforeServiceCommissionPackage -= discount;
			totalAfterDiscountBeforeServiceCommission -= discount;
			lookupAfterDiscountBeforeServiceCommission -= discount;
			lookupAfterDiscountBeforeServiceCommissionCash -= discount;
			totalAfterDiscountBeforeServiceCommissionCash -= discount;
		}
	}

	var serviceCommission = 0;
	var serviceCommissionCash = 0;
	var discountType = '';

	if (promoDetails.discount_value_type) {
		var discountType = promoDetails.discount_value_type;
	}

	const addOnFee = initTourBookingAddCheckoutAddons.getFees() + (typeof addonFormReader !== 'undefined' ? addonFormReader.getFees() : 0);
	serviceCommission = calculateServiceCommission(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommission, oldDiscount, 'applyPromoDiscountCalculation');
	serviceCommission = updateCustomChargeFees(serviceCommission);
	serviceCommission = parseFloat((serviceCommission + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + customChargeFeesTotal).toFixed(2));

	serviceCommissionCash = calculateServiceCommissionCashNewPricing(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommissionCash, oldDiscount, 'applyPromoDiscountCalculation');
	serviceCommissionCash = updateCustomChargeFees(serviceCommissionCash);
	serviceCommissionCash = parseFloat((serviceCommissionCash + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + customChargeFeesTotal).toFixed(2));

	updateUIWithDiscount(cashFees, serviceCommission, serviceCommissionCash, lookupAfterDiscountBeforeServiceCommission, lookupAfterDiscountBeforeServiceCommissionCash, paymentMethod, totalAfterDiscountBeforeServiceCommission, discount, cartView, totalCartDiscount, subtotal);

	// Show or hide promo buttons based on whether a promo code is applied
	if (!$('#discount_promo_code').val()) {
		$(".apply-promo-btn").show();
		$(".remove-promo-btn").hide();
	} else {
		$(".apply-promo-btn").hide();
		$(".remove-promo-btn").show();
	}
}

function calculateServiceCommissionGroupRate(discountType, inputVal, serviceCommissionPercentage, discount, totalAfterDiscountBeforeServiceCommission) {

	serviceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission * serviceCommissionPercentage / 100).toFixed(2));

	return serviceCommission;
}




