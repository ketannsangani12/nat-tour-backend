$(document).on('click', '.btn-edit-cart', function () {
	var cartPackageId = $(this).data('id');
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: editCartUrl,
		data: { tourPackageId: cartPackageId },
	}).done(function (response) {
		if (response) {
			var packageDetails = response.cartPackageDetails;
			var customerDetails = response.customerDetails;

			// Track the editing package's addon amounts so the payment breakdown
			// subtracts them from totalCartAddonsPrice and uses addonFormReader.getPrice()
			// instead — preventing double-counting while the addon form is active.
			window.editingCartPackageAddonPrice = Number(packageDetails.addon_price || 0);
			window.editingCartPackageAddonFee   = Number(packageDetails.addon_fee   || 0);

			// Pre-seed addonFormReader price and fees so the red block still shows
			// the correct amounts even before the user opens the addon modal.
			if (typeof addonFormReader !== 'undefined') {
				addonFormReader.addonsPrice = window.editingCartPackageAddonPrice;
				addonFormReader.addonsFees  = window.editingCartPackageAddonFee;
			}

			// Clear stale custom charge state before loading new package data
			$("#custom_charge_data").val("");
			$(".custom-charge-body").empty();
			if (typeof customChargeTotal !== 'undefined') {
				customChargeTotal = 0;
			}
			$("#tip_data").val(packageDetails.tip_data);
			$("#pax_addons_data").val(packageDetails.pax_addons_data);
			$("#addons_data").val(packageDetails.addons_data);
			var $selSlotTime = $('#slot_time');
			var affiliateCust = customerDetails.affiliatecust;
			var discountType = packageDetails.discount_type;
			
			var discountAmount = packageDetails.discount_amount;
			var discountPercentage = packageDetails.discount_percentage;
			$('#tour_slot_date').val(moment(packageDetails.date).format('dddd, MMMM D Y'));
			$('#hid-tour_slot_date').val(packageDetails.date);
			$("#selected_slotTime").val(packageDetails.slot_time);
			if ($('#tour_package').hasClass('select2-hidden-accessible')) {
				$('#tour_package').select2().val(cartPackageId).trigger("change");
			} else {
				$('#tour_package').val(cartPackageId).trigger("change");
				var pkgLabel = document.getElementById('tourPackageLabel');
				if (pkgLabel && packageDetails.tour_package_name) {
					pkgLabel.textContent = packageDetails.tour_package_name;
				}
			}
			$('#customer_name').val(customerDetails.customer_name);
			$('#customer_phone_number').val(customerDetails.customer_phone_number);
			$('#customer_email').val(customerDetails.customer_email);

			if (affiliateCust > 0) {
				$('#affiliatecust').val(affiliateCust).trigger("change");
				$('#reserve-seats-div').show();
				$('#reserve-seats-div').val(packageDetails.is_reserved_for_affiliate);
			}

			if (discountType == 'Fixed Money') {

				if (discountPercentage == 0) {
					$('#discount_type_fixed_money').prop('checked', false);
					$('#discount_fixed_money').val('');
					$("#discount_fixed_money").prop('disabled', true).focus();
				} else {
					$('#discount_type_fixed_money').prop('checked', true);
					$('#discount_fixed_money').val(discountAmount);
					$("#discount_fixed_money").prop('disabled', false).focus();
				}
			} else if (discountType == 'Percentage') {

				if (discountPercentage == 0) {
					$('#discount_type_percentage').prop('checked', false);
					$('#discount_percentage').val('');
					$("#discount_percentage").prop("disabled", true).focus();
				} else {
					$('#discount_type_percentage').prop('checked', true);
					$('#discount_percentage').val(discountPercentage);
					$("#discount_percentage").prop("disabled", false).focus();
				}
			} else if (discountType == 'Promo Code' && packageDetails.discount_promo_code) {
				if (discountPercentage == 0) {
					$('#discount_type_promo_code').prop('checked', false);
					$('#discount_promo_code').val(packageDetails.discount_promo_code);
					$("#discount_promo_code").prop("disabled", true).focus();
				} else {
					$('#discount_type_promo_code').prop('checked', true);
					$('#discount_promo_code').val(packageDetails.discount_promo_code);
					$("#discount_promo_code").prop("disabled", false).focus();
				}
				$(".apply-promo-btn").hide();
				$(".remove-promo-btn").show();
				$("#is_applied").val(1);
			} else {
				$('#discount_type_fixed_money').prop('checked', false);
				$('#discount_type_percentage').prop('checked', false);
				$('#discount_type_promo_code').prop('checked', false);
				$('#discount_fixed_money').val('');
				$('#discount_percentage').val('');
				$('#discount_promo_code').val('');
				$(".apply-promo-btn").show();
				$(".remove-promo-btn").hide();
				$("#is_applied").val(0);
				applyDiscount();
				getCustomChargeDetails(packageDetails);
			}
			if (bookingHasContacts == 1 && packageDetails.contact_data != "") {
				var rateWiseContactsCount = 0;
				$("#contact_data").val(packageDetails.contact_data);

				for (var a = 0; a < packageDetails.tourists.length; a++) {
					rateWiseContactsCount += parseInt(packageDetails.tourists[a]);
				}

				rateGroupWiseContactsCount = rateWiseContactsCount;

				if (rateGroupWiseContactsCount > 0) {
					$(".add-contacts-section").removeClass('hidden');
				} else {
					$(".add-contacts-section").addClass('hidden');
				}

				tourBookingContacts.initContactForm();
			}
			if (parseInt(isAddonsEnabled) === 1 && typeof addonFormReader !== "undefined") {
				const packageId = packageDetails.tour_package_id;
				addonFormReader.loadStateFromTextareas();

				// Wait for slot_time change event to populate global rateGroups
				setTimeout(() => {
					addonFormReader.fetchAddons(packageId, true).then((addons) => {
						if (!addons) return;

						// Calculate available seats from slot data
						const selectedSlotOption = $('#slot_time option:selected');
						const slotSeats = parseInt(selectedSlotOption.data('seats')) || 0;
						const slotTourists = parseInt(selectedSlotOption.data('tourists')) || 0;
						const overBookSeats = parseInt($('#tour_package option:selected').data('overbook-seats')) || 50;
						const availableSeats = (slotSeats - slotTourists) + overBookSeats;

						// Initialize addon form with global rateGroups
						addonFormReader.initAddonForm(rateGroups, availableSeats, true);

						// Wait for pax rows to be created in DOM
						setTimeout(() => {
							// Restore pax counts from packageDetails.tourists
							if (packageDetails.tourists && Array.isArray(packageDetails.tourists)) {
								packageDetails.tourists.forEach((touristCount, index) => {
									if (touristCount > 0 && rateGroups[index]) {
										const $paxRow = addonFormReader.paxSection.find(`.pax-row[data-rate-id="${rateGroups[index].id}"]`);
										if ($paxRow.length) {
											$paxRow.find('.pax-count').val(touristCount);
										}
									}
								});
							}

							// Update max seats for all pax rows
							addonFormReader.paxSection.find('.pax-row').each((_, row) => {
								$(row).attr('data-max', availableSeats).data('max', availableSeats);
							});

							// Reset cache and render
							addonFormReader._lastUsedSeats = null;
							addonFormReader.renderPaxDetails();
							$(".addons-section, .btn-add-addons").removeClass("d-none");
						}, 100);
					});
				}, 300);
			}
		}
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
	}).always(hideLoading);
});

$(document).on('click', '.btn-delete-cart', function () {
	var cartPackageId = $(this).data('id');
	var isPrimary = (typeof window.tpComboPrimaryId !== 'undefined' && parseInt(cartPackageId) === window.tpComboPrimaryId);

	// Collect addon packages to also remove when primary is deleted
	var addonIdsToRemove = [];
	if (isPrimary && typeof cartPackages !== 'undefined') {
		for (var i = 0; i < cartPackages.length; i++) {
			var pkgId = cartPackages[i].tour_package_id;
			if (parseInt(pkgId) !== window.tpComboPrimaryId) {
				addonIdsToRemove.push(pkgId);
			}
		}
	}

	var addText = isPrimary && addonIdsToRemove.length > 0
		? 'Removing the primary package will also remove all combo add-ons from the cart. Continue?'
		: 'Are you sure you want to remove this item?';

	swal({
		title: 'Remove Package',
		text: addText,
		icon: "warning",
		dangerMode: true,
		buttons: true,
		buttons: {
			no: {
				text: 'Cancel',
				value: false,
			},
			yes: {
				text: 'Remove',
				value: true,
				className: 'btn-danger',
			}
		}
	}).then(function (result) {
		if (result) {
			showLoading();

			// Build list of all package IDs to clean up in the DOM/local state (primary + any addons)
			var allIdsToRemove = [cartPackageId].concat(addonIdsToRemove);

			// Send a single removeFromCart request for the clicked package only. When it's the
			// combo primary, the backend cascades and drops the whole cart itself in one
			// read-modify-write — firing one AJAX call per id here raced against itself on the
			// shared session (whichever request read last would overwrite the others' removal
			// and resurrect an add-on after refresh).
			var removePromises = [
				$.ajax({
					headers: { Accept: 'application/json' },
					dataType: 'json',
					type: 'POST',
					url: removeCartUrl,
					data: { tourPackageId: cartPackageId },
				})
			];

			$.when.apply($, removePromises)
				.done(function () {
					// Remove all from DOM and cartPackages array
					allIdsToRemove.forEach(function (pkgId) {
						$('#tourist-package-list #cart-package-' + pkgId).remove();
						removeCartPackageDetails(pkgId);
					});

					// Notify combo selector about primary removal — re-locks ADD TO THIS TICKET
					if (isPrimary && typeof window.tpOnCartPackageRemoved === 'function') {
						window.tpOnCartPackageRemoved(cartPackageId);
					} else if (typeof tpRefreshComboCartState === 'function') {
						tpRefreshComboCartState();
					}

					calculateCartTotal();
					setRateGroups();
					setTouristsGroups();
					if (cartPackages.length == 0) {
						$('.total-before-service-commission-and-discount-package').text(currencyFormation.symbol + '0.00');
						$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + '0.00');
						$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + '0.00');
						$('.service-commission').text(currencyFormation.symbol + '0.00');
						$('.custom-charge').text(currencyFormation.symbol + '0.00');
						$('#tip-amount-display').text(currencyFormation.symbol + '0.00');
						$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + '0.00');
						$('.cash-set').text(currencyFormation.symbol + '0.00');
						$('.total-').val('0.00');
						$('.fee-').val('0.00');
						$('.fee-').val('0.00');
						$(".discount-info").html('(0%)');
						$(".discount-value").html(currencyFormation.symbol + '0.00');
						$('.tourist-package-list-wrap').addClass('hidden');
						$('#btn-add-to-cart').hide();
					} else {
						$(".discount-value").html(currencyFormation.symbol + totalCartDiscount);
						$(".discount-info").html('(' + totalCartDiscountPercentage + '%)');
						var subtt = totalCartTicketCost - totalCartDiscount;
						$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + subtt.toFixed(2));
						$('.addons-price-display').text(currencyFormation.symbol + totalCartAddonsPrice.toFixed(2));
						$('.total-before-service-commission-and-discount').text(currencyFormation.symbol + totalCartTicketCost.toFixed(2));
						$('.service-commission').text(currencyFormation.symbol + totalCartServiceCommission.toFixed(2));
						$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + cartTotal.toFixed(2));
						$('.custom-charge').text(currencyFormation.symbol + totalCustomChargeAmount.toFixed(2));

						if (totalCustomChargeAmount > 0) {
							$('.cash-set').text(currencyFormation.symbol + (totalCartTicketCost + totalCustomChargeAmount).toFixed(2));
						} else {
							$('.cash-set').text(currencyFormation.symbol + totalCartTicketCost.toFixed(2));
						}
						$('#tip-amount-display').text(currencyFormation.symbol + totalCartTipAmount.toFixed(2));
						if (cashFees == '1') {
							$('#M').text(currencyFormation.symbol + cartTotal.toFixed(2));
						}
					}
					$("#custom_charge_data").val("");
					$("#contact_data").val("");
					$("#add-contacts-form-view").empty();
					$(".custom-charge-body").empty();
					clearDateTime();
					initTourBookingGratuity.calculateUpdatedTipAmounts();
					updateRatesTable();
					hideShowSplitInputUI();
					swal({
						title: 'Update Cart',
						text: 'Package removed successfully',
						icon: 'success'
					});
				})
				.fail(function (jqXHR) {
					showSubmissionError(jqXHR);
				})
				.always(hideLoading);
		}
	});
});

$('#btn-add-to-cart').on('click', function (e) {
	checkValidationsOfCart();
});
function checkValidationsOfCart(validationRateGroupsApproval = false) {

	if ($('#tour_package').val() == '') {
		$("#addCustomCharge").addClass('hidden');
		$(".add-contacts-section").addClass('hidden');
		swal('Please select a tour package', '', 'warning');
		return false;
	}

	if ($('#slot_time').val() == '') {
		swal('Please select a time slot', '', 'warning');
		return false;
	}
	if (subDomainFromHost == 'goto') {
		if ($('#hotel_id').length && $('#hotel-id-div').is(":visible")) {
			var hotel = $('#hotel_id option:selected').val();
			if (hotel == '') {
				swal('Please select a Hotel.', '', 'warning');
				return false;
			}
		}
	}
	if (userType == 'Tour Operator' && rateGroupsNeedsApproval.length > 0 && validationRateGroupsApproval == false) {
		var checkManagerRequestOverride = false;
		for (var a = 0; a < rateGroups.length; a++) {
			var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
			var tourists = parseInt(selPax.val());
			if (tourists > 0 && rateGroupsNeedsApproval.indexOf(rateGroups[a].id) > -1) {
				checkManagerRequestOverride = true;
			}
		}
		if (checkManagerRequestOverride == true) {
			bookingRequestNewBooking.manualOverriderManagerRequest('rateGroupsApproval');
			return false;
		}

	}
	if (bookingHasContacts == 1) {
		if (rateGroupWiseContactsCount > 0) {
			var contactsValidation = tourBookingContacts.newBookingValidation();

			if (!contactsValidation) {
				return false;
			}
		}
	}

	if (parseInt(isAddonsEnabled) == 1) {
		// Only validate the active addon system (AddonFormReader)
		// The TourBookingAddCheckoutAddons system is not active (UI is commented out)
		var paxAddonsValidation = addonFormReader.checkRequiredAddonsSummary();

		if (!paxAddonsValidation) {
			return false;
		}
	}

	checkOverBooking();
}
function checkOverBooking() {
	var slotTime = $('#slot_time').val();
	var touristsCount = 0;
	for (var a = 0; a < rateGroups.length; a++) {
		var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
		touristsCount += (rateGroups[a].is_infant_rate != 1) ? parseInt(selPax.val()) : 0;
	}

	$.post(checkOverbooking, {
		'slot_time': slotTime
	}).then(function (response) {
		if (response) {
			var overbooked = (parseInt(response.booked_seats) + parseInt(touristsCount)) - parseInt(response.seats);

			if (overbooked > 0) {
				if (userType == 'Affiliate') {
					swal('Sorry!', 'You are not allowed to overbook', 'warning');
					$('#btn-make-payment')[0].disabled = false;
					return false;
				} else {

					var addText = '';
					if (overbooked > 0) {
						addText += ' This slot will be overbooked by ' + overbooked + ' seats.';
					} else if (overbooked == 0) {
						addText += ' This slot will be full.';
					}

					addText += ' Do you want to proceed?';

					swal({
						title: 'An overbooking event',
						text: addText,
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
							addToCart();
						} else {
							return false;
						}
					});
				}
			} else {
				addToCart();
			}
		} else {
			addToCart();
		}
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
	});
}

function addToCart() {
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: addToCartUrl,
		data: $('#payment-form').serialize(),
	}).done(function (response) {
		var cart = response.cart;
		cartPackages = cart.tour_packages;
		cartCustomerDetails = cart.customer_details;
		window.cartPackages = cartPackages;
		//window.paymentHandler.cartPackages = cartPackages;
		if (cartPackages.length == 0) {
			swal('Add Tour Package', 'Your cart is empty.Please add tour package.', 'warning');
		}

		$('.tourist-package-list-wrap').removeClass('hidden');
		$("#addCustomCharge").addClass('hidden');
		$(".add-contacts-section").addClass('hidden');
		$(".add-gratuity-section").addClass('d-none');
		var $tblPkgGrps = $('#tourist-package-list');
		$tblPkgGrps.html('');

		var firstKey = Object.keys(cartPackages)[0];

		for (var a = firstKey; a < cartPackages.length; a++) {
			var $tmpl = $('#tourist-package').clone(true);
			$tmpl.removeClass('hidden');
			$tmpl.attr('id', 'cart-package-' + cartPackages[a].tour_package_id);
			$tmpl.find('.tourist-package-title').text(cartPackages[a].tour_package_name);
			$tmpl.find('.tourist-package-btn-wrap .btn-edit').attr('data-id', cartPackages[a].tour_package_id);
			$tmpl.find('.tourist-package-btn-wrap .btn-delete').attr('data-id', cartPackages[a].tour_package_id);

			if (cartPackages[a].date != '' && cartPackages[a].time_from != '') {
				if (cartPackages[a].package_has_slots) {
					$tmpl.find('.tourist-package-date').text(moment(cartPackages[a].date).format('dddd, MMMM Do, Y') + ' @ ' + formatTime(cartPackages[a].time_from));
					$tmpl.find('#cartDate').val(moment(cartPackages[a].date).format('dddd, MMMM Do, Y'));
				} else {
					$tmpl.find('.tourist-package-date').text(moment(cartPackages[a].date).format('dddd, MMMM Do, Y'));
				}
			}

			var rateGrpsHtml = '';
			for (var b = 0; b < cartPackages[a].tourist_groups.length; b++) {
				rateGrpsHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title font-medium text-base text-black">' + cartPackages[a].tourist_groups[b] + '</div><div class="tourist-group-age-count font-medium text-base text-right text-black">' + cartPackages[a].tourists[b] + '</div></div>';
			}

			$tmpl.find('.tourist-package-age-group-list').html(rateGrpsHtml);
			var costGrpHtml = '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-subtotal font-medium text-sm text-[#4B5563]">Ticket Cost</div><div class="font-medium text-sm text-right text-black" id="package-ticketcost-' + cartPackages[a].tour_package_id + '">' + currency(cartPackages[a].subtt).format() + '</div></div>';

			costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-subtotal font-medium text-sm text-[#4B5563]">Discount</div><div class="font-medium text-sm text-right text-black" id="package-discount-' + cartPackages[a].tour_package_id + '">-(' + parseInt(cartPackages[a].discount_percentage) + '%) ' + currency(cartPackages[a].discount_amount).format() + '</div></div>';

			var subtt = cartPackages[a].subtt - cartPackages[a].discount_amount;

			costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-subtotal font-medium text-sm text-[#4B5563]">Subtotal</div><div class="font-medium text-sm text-right text-black" id="package-subtotal-' + cartPackages[a].tour_package_id + '">' + currency(subtt).format() + '</div></div>';

			let total = 0;
			if ($('input[name=payment_method]:checked').val() == "Card" || (cashFees == 1 && $('input[name=payment_method]:checked').val() == "Cash")) {
				total = Number(parseFloat(cartPackages[a].total)) + Number(parseFloat(cartPackages[a].custom_charge)) + Number(parseFloat(cartPackages[a].custom_charge_fees)) + Number(parseFloat(cartPackages[a].tip_total));
			} else {
				subtt = Number(parseFloat(cartPackages[a].subtt)) + Number(parseFloat(cartPackages[a].custom_charge)) + Number(parseFloat(cartPackages[a].tip_amount));
			}

			let fee = Number(parseFloat(cartPackages[a].fee)) + Number(parseFloat(cartPackages[a].custom_charge_fees)) + Number(parseFloat(cartPackages[a].tip_fees));

			let addonsPrice = Number(parseFloat(cartPackages[a].addon_price || 0));
			let addonsFees  = Number(parseFloat(cartPackages[a].addon_fee   || 0));
			if (addonsPrice > 0 || addonsFees > 0) {
				if ($('input[name=payment_method]:checked').val() == "Card" || (cashFees == 1 && $('input[name=payment_method]:checked').val() == "Cash")) {
					total = total + addonsPrice + addonsFees;
				} else {
					subtt = subtt + addonsPrice;
				}
				fee = fee + addonsFees;
				costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-fees font-medium text-sm text-[#4B5563]">Add-ons</div><div class="font-medium text-sm text-right text-black" id="addon-price-' + cartPackages[a].tour_package_id + '">' + currency(addonsPrice).format() + '</div></div>';
			}

			if (cartPackages[a].custom_charge > 0) {
				costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-custom-charge font-medium text-sm text-[#4B5563]">Custom Charge</div><div class="font-medium text-sm text-right text-black" id="package-custom-charge-' + cartPackages[a].tour_package_id + '">' + currency(cartPackages[a].custom_charge).format() + '</div></div>';
			}
			if (cartPackages[a].tip_amount > 0) {
				costGrpHtml += '<div class="tourist-package-age-group mb-4 justify-between"><div class="tourist-group-age-title font-medium text-sm text-[#4B5563]">Tip</div><div class="font-medium text-sm text-right text-black" id="package-tip-' + cartPackages[a].tour_package_id + '">' + currency(cartPackages[a].tip_amount).format() + '</div></div>';
			}

			if ($('input[name=payment_method]:checked').val() == "Card" || (cashFees == 1 && $('input[name=payment_method]:checked').val() == "Cash")) {
				costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-fees font-medium text-sm text-[#4B5563]">Booking Fees</div><div class="font-medium text-sm text-right text-black" id="package-fees-' + cartPackages[a].tour_package_id + '">' + currency(fee).format() + '</div></div>';
			} else {
				costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-fees font-medium text-sm text-[#4B5563]">Booking Fees</div><div class="font-medium text-sm text-right text-black" id="package-fees-' + cartPackages[a].tour_package_id + '">' + currency(0).format() + '</div></div>';
			}

			if ($('input[name=payment_method]:checked').val() == "Card" || (cashFees == 1 && $('input[name=payment_method]:checked').val() == "Cash")) {
				costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-total font-medium text-sm text-[#4B5563]"><b>Total Cost</b></div><div class="font-medium text-sm text-right text-black" id="package-totalcost-' + cartPackages[a].tour_package_id + '">' + currency(total).format() + '</div></div>';
			} else {
				costGrpHtml += '<div class="tourist-package-age-group mb-4 flex justify-between"><div class="tourist-group-age-title tourist-package-total font-medium text-sm text-[#4B5563]"><b>Total Cost</b></div><div class="font-medium text-sm text-right text-black" id="package-totalcost-' + cartPackages[a].tour_package_id + '">' + currency(subtt).format() + '</div></div>';
			}

			$tmpl.find('.tourist-package-total-cost').html(costGrpHtml);
			$tblPkgGrps.append($tmpl);
		}

		$('#discount_fixed_money').val('');

		if ($("#is_applied").val() == 1) {
			$(".apply-promo-btn").show();
			$(".remove-promo-btn").hide();
			$("#discount_type_promo_code").prop('checked', false);
			$("#discount_promo_code").prop('disabled', true).val('');
			$("#is_applied").val(0);
		}

		$('#discount_percentage').val('');
		$('input:radio[name="discount_type"]').prop('checked', false);
		calculateCartTotal();
		//hideShowSplitInputUI();
		hideShowSplitInputUIForCart(cartPackages.length);

		$('#slot_time').html('<option value="">Select Time Slot</option>');
		$("#selected_slotTime").val('');
		if ($('#tour_package').hasClass('select2-hidden-accessible')) {
			$('#tour_package').select2('destroy').val('').select2();
		} else {
			$('#tour_package').val('');
			if (typeof tpResetSelector === 'function') tpResetSelector();
		}
		if (typeof tpRefreshComboCartState === 'function') tpRefreshComboCartState();
		$('#table-rate-groups').empty();
		$('.total-before-service-commission-and-discount-package').text('$0.00');
		$('#customer_comment').val('');
		$('#tour_operator_note').val('');
		$('#booked_by_staff').val('').select2();
		$('#is_reserved_for_affiliate select').val('0');
		$("#booking-addons-count").html("");
		$("#btn-booking-addons").remove();
		$(".addon-required").hide();
		$(".addon-required-fulfilled").hide();
		$(".addons-section").empty();
		$("#pax_addons_data").val("");
		$("#addons_data").val("");
		if (typeof addonFormReader !== 'undefined') {
			addonFormReader.paxAddonsState = { form_id: 0, fields: {} };
			addonFormReader.addonState     = { form_id: 0, fields: {} };
			addonFormReader.addonsPrice    = 0;
			addonFormReader.addonsFees     = 0;
		}
		window.editingCartPackageAddonPrice = 0;
		window.editingCartPackageAddonFee   = 0;
		$("#custom_charge_data").val("");
		$("#tip_data").val("");
		initTourBookingGratuity.resetTipData();
		$("#contact_data").val("");
		tourBookingPaymentLink.togglePaymentLinkSection("slot_time", "payment-link-section");
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
	}).always(hideLoading);
}
function hideShowSplitInputUIForCart(totalCartPackages) {
	var paymentMethod = $('input[name=payment_method]:checked').val();

	// Check if custom charge or add-ons exist in cart
	let hasCustomCharge = false;
	let hasAddons = false;
	if (typeof cartPackages !== 'undefined' && cartPackages.length > 0) {
		hasCustomCharge = cartPackages.some(pkg => pkg.custom_charge && pkg.custom_charge > 0);
		hasAddons = cartPackages.some(pkg => (Number(pkg.addon_price) > 0) || (Number(pkg.addon_total) > 0));
	}

	if (totalCartPackages > 1 || customChargeTotal > 0 || hasCustomCharge || hasAddons) {
		$("#dividedBy").prop("selectedIndex", 0);
		$("#split-payment").prop('checked', false);
		$(".split-check").text("$0.00");
		$("#split_value_field").val("").prop('required', false).hide();
		$("#split-payment-div").hide();

		// Split payment was showing the "Total payment" row (#psplit) with the now-stale
		// per-split amount — swap back to the full-total row for the active payment method,
		// same as PaymentHandler.resetSplitValues() does when split is reset elsewhere.
		$(".split-set").text("$0.00");
		$("#psplit").hide();
		if (paymentMethod === 'Card' || (paymentMethod === 'Cash' && cashFees == 1)) {
			$("#pcard").show();
		} else if (paymentMethod === 'Cash' || paymentMethod === 'Pay Later') {
			$("#pcash").show();
		}
	} else if (paymentMethod == 'Pay Later' || paymentMethod == 'Link') {
		$("#split-payment-div").hide();
	} else {
		// Show split payment for Card and Cash payment methods only
		$("#split-payment-div").show();
	}
}
function clearDateTime() {
	var lastDate = new Date();
	$("#tour_slot_date").datepicker('setDate', lastDate).trigger("change");
	$('#tour_slot_date').val(moment(lastDate).format('dddd, MMMM D Y'));
	$('#hid-tour_slot_date').val(lastDate);
	$('#slot_time').val('');
	$("#selected_slotTime").val('');
	if ($('#tour_package').hasClass('select2-hidden-accessible')) {
		$('#tour_package').select2().val('').trigger('change');
	} else {
		$('#tour_package').val('').trigger('change');
		if (typeof tpResetSelector === 'function') tpResetSelector();
	}
	$('.total-before-service-commission-and-discount-package').text('$0.00');
}

function calculateCartTotal() {
	totalCartTicketCost = 0;
	totalCartAddonsPrice = 0;
	totalCartAddonsFees = 0;
	totalCartAddonsTotal = 0;
	totalCartServiceCommission = 0;
	totalCartCashTicketCost = 0;
	totalCartCashServiceCommission = 0;
	cartTotal = 0;
	cartCashTotal = 0;
	totalCartDiscount = 0;
	totalCartDiscountPercentage = 0;
	totalCustomChargeAmount = 0;
	totalCustomChargeFees = 0;
	totalCartTipAmount = 0;
	totalCartTipFees = 0;
	if (Object.keys(cartPackages).length > 0) {
		for (var a = 0; a < cartPackages.length; a++) {
			totalCartAddonsPrice += Number(parseFloat(cartPackages[a].addon_price || 0));
			totalCartAddonsFees  += Number(parseFloat(cartPackages[a].addon_fee   || 0));
			totalCartAddonsTotal += Number(parseFloat(cartPackages[a].addon_total  || 0));
			totalCartTicketCost += parseFloat(cartPackages[a].subtt);
			totalCartCashTicketCost += parseFloat(cartPackages[a].cashSubtt);
			totalCartServiceCommission += parseFloat(cartPackages[a].fee);
			totalCartCashServiceCommission += parseFloat(cartPackages[a].cashFee);
			cartTotal += parseFloat(cartPackages[a].total);
			cartCashTotal += parseFloat(cartPackages[a].cashTotal);
			totalCartDiscount += parseFloat(cartPackages[a].discount_amount);
			totalCartDiscountPercentage += parseFloat(cartPackages[a].discount_percentage);
			totalCustomChargeAmount += parseFloat(cartPackages[a].custom_charge);
			totalCustomChargeFees += parseFloat(cartPackages[a].custom_charge_fees);
			totalCartTipAmount += parseFloat(cartPackages[a].tip_amount);
			totalCartTipFees += parseFloat(cartPackages[a].tip_fees);
		}
	}

	// Sync customChargeTotal with cart's total custom charge
	if (typeof customChargeTotal !== 'undefined') {
		customChargeTotal = totalCustomChargeAmount;
	}
}

function roundout(amount, places = 2) {
	if (places < 0) places = 0;
	let factor = Math.pow(10, places);
	return Math.round(amount * factor) / factor;
}