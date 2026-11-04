var modalTitle = 'Book a Tour';
var cardElement = null;
var cardInputValid = false;
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
var packageHasSlots = 1;
var isComboPackage = 0;
var bookingHasContacts = 0;
var rateGroupWiseContactsCount = 0;
var rateGroupsNeedsApproval = [];
var elements;
let globalCalendarSeats = {};
var gratuitySetting = [];
let selectedMonth = null;
let selectedYear = null;
var cachedPromoDetails = null;

$(function () {
	if (slotVisibility == 1) {
		$('.package-has-slots').css('display', 'block');
	}

	if (typeof ($('#tour_package option:selected').data('overbook-seats')) !== "undefined") {
		overBookSeats = $('#tour_package option:selected').data('overbook-seats');
	} else {
		overBookSeats = 50;
	}

	if (window.location.href.indexOf('affiliate') > 0) {
		var userId = document.getElementById('userId').value;
		$.getJSON(affiliateSearchUrl.replace('_ID_', userId), function (data) {
			$('#customer_name').val(data ? data[0]?.name : "");
			$('#customer_phone_number').val(data ? data[0]?.phone_number : "");
			$('#customer_email').val(data ? data[0]?.email : "");
		})
	} else {
		$('#affiliatecust').on('change', function () {
			if ($(this).val() != null) {
				affiliate_id = $(this).val();
			} else {
				affiliate_id = affiliate;
			}

			$("#is_reserved_for_affiliate").val(0);

			if ($(this).val() != 0) {
				$('#reserve-seats-div').show();
			} else {
				$('#reserve-seats-div').hide();
			}
			//Fixed issue of service commission percentage selected affiliate removed
			if (affiliate_id == 0 && userType != 'Affiliate' && checkAffiliate == 1) {
				checkAffiliate = 0;
			}

			$.getJSON(affiliateSearchUrl.replace('_ID_', affiliate_id), function (data) {
				affiliateCheck = data;
				$('#customer_name').val(data ? data[0]?.name : "");
				$('#customer_phone_number').val(data ? data[0]?.phone_number : "");
				$('#customer_email').val(data ? data[0]?.email : "");
				$('#table-rate-groups').html('');
				getSlotTimes(1, affiliateChange = 1);
			})
		})
	}

	$('[data-toggle="tooltip"]').tooltip();
	$('.select-tour, .select-tour-time, .select-agent-name, #country').select2();
	$('.select-hotel-name').select2({
		placeholder: "Please select pickup hotel",
		allowClear: true
	});

	if ($('#tour_slot_date').val() == '')
		$('#tour_slot_date').val(moment().format('dddd, MMMM Do, Y'));

	$('#tour_slot_date').datepicker({
		dateFormat: 'DD, MM d yy',
		altField: "#hid-tour_slot_date",
		altFormat: "yy-mm-dd",
		minDate: 0,
		onSelect: function () {
			getSlotTimes(1, affiliateChange = 1);
		}
	});

	if (rateGroups.length) { // when calendar > new booking
		setRateGroups();
		showSlotInfo($('#slot_time')[0]);
		setTouristsGroups();
		if (window.location.href.indexOf('affiliate') < 0) {
			getSlotTimes(1);
		}

	} else
		addTaxCols();

	$("#discount_percentage, #discount_fixed_money").prop('disabled', true);

	var $radioButtons = $('.payment-condition-item input[type="radio"]');

	$radioButtons.on('change', function () {
		$radioButtons.each(function () {
			$(this).parent().toggleClass('checked-item', this.checked);
		});
		if (this.id == 'pay-with-cash') {
			$('#card-element').addClass('d-none');
			$('#cash-paid-amount').removeClass('d-none');
			$('#cash-paid-amount').prop("readonly", false);
		} else if (this.id == 'pay-with-card') { // there are other radio buttons so only 'else' can not be specified
			$('#card-element').removeClass('d-none');
		}
		if (this.id != 'pay-with-cash') {
			$('#cash-paid-amount').addClass('d-none');
			$('#cash-paid-amount').val("");
			$('#cash-paid-amount').prop("readonly", true);
		}
	});

	$('#search').autocomplete({
		minLength: 2,
		source: function (request, response) {
			$('#search').parents('.search-box-wrap').addClass('searching');

			$.getJSON(customerSearchUrl, { search: request.term }, function (data) {
				var data2 = [];

				for (var a = 0; a < data.length; a++) {
					data2.push({
						value: data[a].email,
						label: data[a].name,
						email: data[a].email,
						phone_number: data[a].phone_number
					});
				}

				response(data2);
			})
				.always(function () {
					$('#search').parents('.search-box-wrap').removeClass('searching');
				});
		},
		select: function (event, ui) {
			$('#customer_name').val(ui.item.label);
			$('#customer_phone_number').val(ui.item.phone_number);
			$('#customer_email').val(ui.item.email);

			return false;
		}
	})
		.autocomplete('instance')._renderItem = function (ul, item) {
			return $('<li>')
				.append('<div>' + item.label + '<br>' + item.phone_number + ', ' + item.email + '</div>')
				.appendTo(ul);
		};

	$('#search').autocomplete({
		minLength: 2,
		source: function (request, response) {
			$('#search').parents('.search-box-wrap').addClass('searching');

			$.getJSON(customerSearchUrl1, { search: request.term }, function (data) {
				var data2 = [];

				for (var a = 0; a < data.length; a++) {
					data2.push({
						value: data[a].email,
						label: data[a].name,
						email: data[a].email,
						phone_number: data[a].phone_number
					});
				}

				response(data2);
			})
				.always(function () {
					$('#search').parents('.search-box-wrap').removeClass('searching');
				});
		},
		select: function (event, ui) {
			$('#customer_name').val(ui.item.label);
			$('#customer_phone_number').val(ui.item.phone_number);
			$('#customer_email').val(ui.item.email);

			return false;
		}
	})
		.autocomplete('instance')._renderItem = function (ul, item) {
			return $('<li>')
				.append('<div>' + item.label + '<br>' + item.phone_number + ', ' + item.email + '</div>')
				.appendTo(ul);
		};
});

function getColorDate(month, year) {
	if ($('#tour_package').val() !== '') {
		const params = {
			month: month,
			year: year,
			tourPackageId: $('#tour_package').val(),
		};
		showLoading();
		$.post(getColorDateUrl, params)
			.done(function (data) {
				globalCalendarSeats = data;

				// Find first available date
				let firstAvailableDate = null;
				for (const date in globalCalendarSeats) {
					const seatData = globalCalendarSeats[date];
					if (seatData && (seatData.seats - seatData.booked) > 0) {
						firstAvailableDate = date; // format: 'YYYY-MM-DD'
						break;
					}
				}

				// If time slot is not available a for month
				const firstDate = getFirstDateOfMonth(month, year);

				if (firstAvailableDate) {
					const formattedDisplayDate = moment(firstAvailableDate, 'YYYY-MM-DD').format('dddd, MMMM Do, YYYY');

					// Set both hidden and visible input values
					$('#tour_slot_date').val(formattedDisplayDate);
					$('#hid-tour_slot_date').val(firstAvailableDate);
					$('#slot_time').html('<option value="">Select Time Slot</option>');

					// Set the calendar to this date (updates view too)
					$('#tour_slot_date').datepicker('setDate', moment(firstAvailableDate, 'YYYY-MM-DD').toDate());
				} else {
					$('#tour_slot_date').datepicker('setDate', moment(firstDate, 'YYYY-MM-DD').toDate());
				}

				$('#tour_slot_date').datepicker("refresh");
			})
			.always(hideLoading);
	}
}

function getFirstDateOfMonth(month, year = new Date().getFullYear()) {

	return new Date(year, month - 1, 1);
}


function colorDate() {
	const MonthText = $('#tour_slot_date').val();
	let parsedDate = moment(MonthText, 'dddd, MMMM Do, YYYY');

	if (!parsedDate.isValid()) {
		parsedDate = moment();
	}

	selectedMonth = parsedDate.month(); // 0-indexed
	selectedYear = parsedDate.year();

	$('#tour_slot_date').datepicker('destroy');
	$('#hid-tour_slot_date').val('');

	$('#tour_slot_date').datepicker({
		dateFormat: 'DD, MM d yy',
		altField: '#hid-tour_slot_date',
		altFormat: 'yy-mm-dd',
		minDate: 0,
		beforeShowDay: dynamicSeatColoring,
		onSelect: function () {
			getSlotTimes(1, affiliateChange = 1);
		},
		onChangeMonthYear: function (year, month, inst) {
			selectedMonth = month - 1; // jQuery UI gives 1-based month
			selectedYear = year;
			getColorDate(month, year);
		}
	});
}

function dynamicSeatColoring(date) {
	const ymd = $.datepicker.formatDate('yy-mm-dd', date);

	if (date.getMonth() === selectedMonth && date.getFullYear() === selectedYear) {
		const seatData = globalCalendarSeats[ymd];

		if (seatData) {
			const available = seatData.seats - seatData.booked;

			if (available > 0) return [true, 'available-date', 'Seats Available'];
			else if (available === 0) return [true, 'soldout-date', 'Sold Out'];
			else return [true, 'overbooked-date', 'Overbooked'];
		}

		return [false, '', 'Unavailable'];
	}

	return [true, '', ''];
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

function dayIncrDecr(value) {
	var date = $('#tour_slot_date').datepicker('getDate');
	date.setDate(date.getDate() + value);
	$('#tour_slot_date').datepicker('setDate', date);
	getSlotTimes(1, affiliateChange = 1);
}

function refreshAffiliate() {
	var affiliatecust = $("#affiliatecust").val();
	if (affiliatecust != 0 && userType != 'Affiliate' && (cartView == '1' && Object.keys(cartCustomerDetails).length == 0)) {
		$('#affiliatecust').val(0)
		$('#customer_name').val("");
		$('#customer_phone_number').val("");
		$('#customer_email').val("");
		$('#table-rate-groups').html('');
		$("#is_reserved_for_affiliate").val(0);
		$('#reserve-seats-div').hide();
	}
}

function getSlotTimes(getRateGroups, affiliateChange = 0) {
	packageHasSlots = 1;
	applyOnSlots = false;
	slotTimes = [];
	bookingHasContacts = $('#tour_package option:selected').data('multiple-customers');
	$("#package_has_contacts").val(bookingHasContacts);

	if (bookingHasContacts == 0) {
		$(".add-contacts-section").addClass('hidden');
	}

	if (!affiliateChange && checkAffiliate != 1) {
		refreshAffiliate();
	}

	if ($('#tour_package').val() == '') {
		$("#addCustomCharge").addClass('hidden');
		swal('Please select a tour package', '', 'warning');
		return false;
	}

	showLoading();

	var $selSlotTime = $('#slot_time');
	$selSlotTime.html('<option value="">Select Time Slot</option>');
	var date = $('#tour_slot_date').val();
	const date1 = new Date(date);
	var month = date1.getMonth() + 1;
	var day = date1.getDate();
	month = (month < 10 ? '0' : '') + month;
	day = (day < 10 ? '0' : '') + day;
	overBookSeats = $('#tour_package option:selected').data('overbook-seats');

	if (checkAffiliate == 1) {
		var affiliateId = affiliate;
		if (affiliate == '') {
			var affiliateId = $('#affiliatecust').val();
		}
	} else {
		var affiliateId = $('#affiliatecust').val();
	}

	var params = {
		date: date1.getFullYear() + month + day,
		tourPackageId: $('#tour_package').val(),
		getRateGroups: getRateGroups ? 1 : 0,
		affiliateId: affiliateId,
		fromCreateBooking: 1
	};

	$.post(getTourSlotTimesByDateUrl, params)
		.done(function (data) {
			if (data.code == 500) {
				swal('Error occurred', data.message, 'error');
				return;
			}

			slotTimes = data.slotTimes;
			applyOnSlots = data.applyOnSlots;
			packageHasSlots = data.packageHasSlots;
			rateGroupsNeedsApproval = data.rateGroupsNeedsApproval;
			gratuitySetting = data.gratuitySetting;
			isComboPackage = data.isComboPackage;
			var packageSlotTime = data.slotTimes[0]?.id;
			var slotDate = moment().format(params.date.substr(0, 4) + '-' + params.date.substr(4, 2) + '-' + params.date.substr(6), 'America/Phoenix');
			var sDT = serverDatenow.format('YYYY-MM-DD HH:mm:ss');
			globalCalendarSeats = data.calendarSeats;
			colorDate();

			for (var i = 0; i < slotTimes.length; i++) {
				// For affiliates, use available_tourists field; for others, calculate seats - tourists
				var availableCount;
				if (userType == 'Affiliate' && slotTimes[i].hasOwnProperty('available_tourists') && slotTimes[i].available_tourists !== null && slotTimes[i].available_tourists !== undefined) {
					availableCount = parseInt(slotTimes[i].available_tourists);
				} else {
					availableCount = slotTimes[i].seats - slotTimes[i].tourists;
				}

				var displayText = formatTime(slotTimes[i].time_from) +
					(slotTimes[i].time_to == null ? ' onwards' : ' - ' + formatTime(slotTimes[i].time_to)) +
					' (Available ' + availableCount + ' of ' + slotTimes[i].seats + ')';

				var cleanText = formatTime(slotTimes[i].time_from) +
					(slotTimes[i].time_to == null ? ' onwards' : ' - ' + formatTime(slotTimes[i].time_to)) + ' ( ' + availableCount + ' / ' + slotTimes[i].seats + ' )';

				var isSelected = (slotTimes[i].id == $("#selected_slotTime").val());
				var opt = new Option(displayText, slotTimes[i].id, isSelected, isSelected);

				$(opt).data('clean-text', cleanText);


				if (packageHasSlots == 1) {
					var slotDateTime = slotDate + ' ' + slotTimes[i].time_from;

					if (slotDateTime <= sDT) {
						if (slotTimes[i].affiliateFeature != undefined) {
							if (slotTimes[i].affiliateFeature == 0) {
								opt.setAttribute('disabled', true);
							} else {
								opt.setAttribute('disabled', false);
							}
						} else {
							opt.setAttribute('disabled', true);
						}
					}
				}

				if (slotTimes[i].affiliateFeature != undefined) {
					if (slotTimes[i].affiliateFeature == 0) {
						opt.setAttribute('disabled', true);
					} else {
						$(opt).data('seats', slotTimes[i].seats).data('tourists', slotTimes[i].tourists);
					}
				} else {
					$(opt).data('seats', slotTimes[i].seats).data('tourists', slotTimes[i].tourists);
				}

				/* Execute only if payment link feature is enabled and days limitation matched with selected timeslot */
				if (isPaymentLink == 1) {
					$(opt).attr('data-payment-link', slotTimes[i].is_payment_link);
				}

				$(opt).data('booked', slotTimes[i].available_tourists);
				$selSlotTime.append(opt);
			}

			$selSlotTime.select2({
				templateSelection: function (data, container) {
					var selectedOption = $('#slot_time option:selected');
					var cleanText = selectedOption.data('clean-text');
					if (cleanText) {
						return cleanText;
					}
					return data.text;
				}
			});

			$selSlotTime.trigger('change');

			if (packageHasSlots == 1) {
				$('.package-has-slots').css('display', 'block');
			} else {
				$('.package-has-slots').css('display', 'none');
			}
			if (cartView == 1) {
				if (isComboPackage == 1 || Object.keys(cartPackages).length > 0) {
					$('#btn-add-to-cart').show();
				} else {
					$('#btn-add-to-cart').hide();
				}
			}
			var $selHotel = $('#hotel_id');
			if (data?.hotels?.length) {
				$selHotel.find("option:gt(0)").remove();

				for (var i = 0; i < data?.hotels?.length; i++) {
					var opt = new Option(
						data?.hotels[i].name,
						data?.hotels[i].id,
						false,
						false
					);

					$selHotel.append(opt);
				}

				$selHotel.trigger('change');

				$('#hotel-id-div').show();
			} else {
				$selHotel.find("option:gt(0)").remove();
				$selHotel.trigger('change');
				$('#hotel-id-div').hide();
			}
			if (gratuitySetting != undefined && gratuitySetting != null && gratuitySetting != '' && Object.keys(gratuitySetting).length > 0) {
				$(".add-gratuity-section").removeClass('d-none');
			} else {
				$(".add-gratuity-section").addClass('d-none');
			}
			if (getRateGroups == 1) {
				rateGroups = (applyOnSlots == false) ? data.rateGroups : [];
				customer = data.customer;
				tourOperatorId = data.tourOperatorId;
				isGroupRateEnabled = data.isGroupRateEnabled;
				isPartnerPackage = data.isPartnerPackage;
				if (affiliateCheck.length == 1 || checkAffiliate == 1 || userType == 'Affiliate') {
					serviceCommissionPercentage = rateGroups[0]?.affiliate_processing_percentage ? rateGroups[0].affiliate_processing_percentage : 0;
					stripeCommissionPercentage = rateGroups[0]?.stripe_affiliate_percentage ? rateGroups[0].stripe_affiliate_percentage : 0;
				} else {
					serviceCommissionPercentage = rateGroups[0]?.service_commission_percentage ? rateGroups[0].service_commission_percentage : 0;
					stripeCommissionPercentage = rateGroups[0]?.stripe_commission_percentage ? rateGroups[0].stripe_commission_percentage : 0;
				}
				initTourBookingAddCheckoutAddons.setServiceCommissionPercentage(serviceCommissionPercentage);
				addonFormReader.setServiceCommissionPercentage(serviceCommissionPercentage);
				customChargeIntegration($('#tour_package').val(), serviceCommissionPercentage);
				initTourBookingGratuity.setGratuitySetting(gratuitySetting);
				initTourBookingGratuity.setServiceCommissionPercentage(serviceCommissionPercentage);
				initTourBookingGratuity.calculateUpdatedTipAmounts();

				if (isGroupRateEnabled == '1') {
					assignVarsForGroupRates(data);
					setGroupRates();
					$("#slot_time").val($("#selected_slotTime").val());
					showSlotInfoForGroupRates($('#slot_time')[0]);
					return false;
				} else {
					// Update when tour operator change
					if (!affiliateChange && userType != 'Affiliate') {
						$('#affiliatecust').html(customer);
					}

					setRateGroups();

					var selectedSlotTime = $("#selected_slotTime").val();
					$("#slot_time").val(selectedSlotTime);
					if (packageHasSlots == 0) {
						$("#slot_time").val(packageSlotTime);
					}
					const urlParams = new URLSearchParams(window.location.search);
					// Check if the parameter exists
					let optionalTimeSlotId = 0;
					if (urlParams.has("time_slot_id")) {
						// Get the value of the parameter
						optionalTimeSlotId = urlParams.get("time_slot_id");
						$('#slot_time')[0].value = optionalTimeSlotId;
					}

					showSlotInfo($('#slot_time')[0]);

					if (cartView == 1) {
						var tourPackageId = $('#tour_package').val() ? $('#tour_package').val() : 1;
						var packageDetails = getCartPackageDetails(tourPackageId);

						if (Object.keys(packageDetails).length == 0) {
							$('#discount_fixed_money').val('');
							$('#discount_percentage').val('');
							$('#discount_promo_code').val('');
							$("#discount_fixed_money").attr('disabled', 'disabled');
							$("#discount_percentage").attr('disabled', 'disabled');
							$("#discount_promo_code").attr('disabled', 'disabled');
							$(".apply-promo-btn").show();
							$(".remove-promo-btn").hide();
							$('input:radio[name="discount_type"]').prop('checked', false);
						}
					}
				}
			} else {
				updateRatesTable();
			}

			permitFeeTable();
		})
		.fail(function () {
			swal('Error occurred', 'Unable to get time slots of the selected date from server.', 'error');
		})
		.always(hideLoading);

	showSelectionInfo();
}

function showSelectionInfo() {
	if ($('#tour_package').val())
		$('.tour-title').text($('#tour_package')[0].options[$('#tour_package')[0].selectedIndex].text);
	else
		$('.tour-title').text('-');

	$('.booking-selected-date').text(moment($('#tour_slot_date').datepicker('getDate')).format('dddd, MMMM Do, Y'));

	if ($('#slot_time').val()) {
		let cleanText = $('#slot_time option:selected').data('clean-text') || '-';
		$('.booking-selected-time').text(cleanText);
	} else {
		$('.booking-selected-time').text('-');
	}
}

$('#slot_time').on('select2:select', function () {
	let cleanText = $(this).find('option:selected').data('clean-text') || 'Select Time Slot';
	$('#select2-slot_time-container').text(cleanText);
});

function showSlotInfo(selSlotTime) {
	/* Execute only if payment link feature is enabled and days limitation matched with selected timeslot */
	tourBookingPaymentLink.togglePaymentLinkSection("slot_time", "payment-link-section");

	// Check if `applyOnSlots` is true and the slot is not already processed	
	if (applyOnSlots && selSlotTime.value) {
		// Find the object with matching ID
		const selectedSlot = slotTimes.find(slot => slot.id == selSlotTime.value);
		fetchSlotBasedRateGroups(selSlotTime, selectedSlot.custom_rate); // Call function only for new selection
	} else {
		if (isGroupRateEnabled == 1) {
			groupRateManipulateRateGroups(selSlotTime);
		} else {
			manipulateRateGroups(selSlotTime);
		}
	}
}

function fetchSlotBasedRateGroups(selSlotTime, custom_rate_id) {
	showLoading();

	var slotDate = $('#tour_slot_date').val();
	const date = new Date(slotDate);

	var params = {
		scheduling_year: date.getFullYear(),
		tour_package_id: $('#tour_package').val(),
		affiliate_id: $('#affiliatecust').val() || '',
		custom_date_id: custom_rate_id,
		date: slotDate,
		time_slot_id: selSlotTime.value,
	};

	$.post(getPackageRatesUrl, params)
		.done(function (data) {
			rateGroups = data.rates;
			rateGroupsNeedsApproval = data.rateGroupsNeedsApproval;
			isGroupRateEnabled = data.is_group_rate_enabled;

			if (affiliateCheck.length == 1 || checkAffiliate == 1 || userType == 'Affiliate') {
				serviceCommissionPercentage = rateGroups[0]?.affiliate_processing_percentage ? rateGroups[0].affiliate_processing_percentage : 0;
				stripeCommissionPercentage = rateGroups[0]?.stripe_affiliate_percentage ? rateGroups[0].stripe_affiliate_percentage : 0;
			} else {
				serviceCommissionPercentage = rateGroups[0]?.service_commission_percentage ? rateGroups[0].service_commission_percentage : 0;
				stripeCommissionPercentage = rateGroups[0]?.stripe_commission_percentage ? rateGroups[0].stripe_commission_percentage : 0;
			}
			initTourBookingAddCheckoutAddons.setServiceCommissionPercentage(serviceCommissionPercentage);
			addonFormReader.setServiceCommissionPercentage(serviceCommissionPercentage);
			customChargeIntegration($('#tour_package').val(), serviceCommissionPercentage);
			initTourBookingGratuity.calculateUpdatedTipAmounts();

			if (isGroupRateEnabled == 1) {
				assignVarsForGroupRates(data);
				setGroupRates();
				groupRateManipulateRateGroups(selSlotTime);
			} else {
				setRateGroups();
				manipulateRateGroups(selSlotTime);
			}
		})
		.fail(function () {
			swal('Error occurred', 'Unable to get rate groups of the selected slot from server.', 'error');
		})
		.always(hideLoading);
}

function manipulateRateGroups(selSlotTime) {
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
		addTouristsOpts($(this), emptySeats + overBookSeats);
	});
	totalSeatsForBooking = emptySeats + overBookSeats;

	var slot_time = $("#slot_time").val();
	$("#selected_slotTime").val(slot_time);

	setTouristsGroups();
	updateRatesTable();
	permitFeeTable();
}

function addTouristsOpts($selTourists, emptySeats) {
	for (var a = 0, $selTourists; a < emptySeats; a++)
		$selTourists.append(new Option(a + 1));
}

function setRateGroups() {

	const testIDRateName = (rateStr) => {

		//if prod do not add test ids
		if (window.location.hostname.includes('nativeamericantours.com')) {
			return rateStr;
		}

		const rateName = rateStr.toString().toLowerCase().trim().replace(/[^a-zA-Z0-9]/, "");
		if (rateName.search(/adult/gi) != -1 && rateName.search(/military/gi) != -1) {
			return 'adult-military'
		} else if (rateName.search(/adult/gi) != -1) {
			return 'adult'
		} else if (rateName.search(/military/gi) != -1 && rateName.search(/child/gi) != -1) {
			return 'child-military'
		} else if (rateName.search(/child/gi) != -1) {
			return 'child'
		} else if (rateName.search(/infant/gi) != -1) {
			return 'infant'
		} else if (rateName.search(/military/gi) != -1) {
			return 'military'
		} else if (rateName.search(/native/gi) != -1) {
			return 'native'
		} else if (rateName.search(/local/gi) != -1) {
			return 'local'
		} else {
			return rateName
		}

	}

	if (isGroupRateEnabled == '1') {
		setGroupRates();
	} else {
		var paymentMethod = $('.checkset:checked').val();
		var cashPayment = (paymentMethod === 'Cash') ? 1 : 0;
		$('.rategrpth').show();
		$('.rategrptd').show();
		if (isPartnerPackage == 1) {
			$('.partnerfeeth').removeClass('hidden');
			$('.partner-fee').removeClass('hidden');
		} else {
			$('.partnerfeeth').addClass('hidden');
			$('.partner-fee').addClass('hidden');
		}
		var $tblRateGrps = $('#table-rate-groups');
		$tblRateGrps.html('');
		var k = 0;
		for (var a = 0; a < rateGroups.length; a++) {
			var $tmpl = $('#tmpl-rate-group tr').clone(true);
			$hidRateGroup = $tmpl.find('.hid-rate-group');
			$hidRateGroup.val(rateGroups[a].id).attr('name', $hidRateGroup.data('name'));
			$selTourists = $tmpl.find('.tourists');
			$selTourists.attr('name', $selTourists.data('name'));
			$selTourists.attr('id', 'tourists-' + rateGroups[a].id);

			$tmpl.find('.age').html('<span class="rate-for" data-testid="' + testIDRateName(rateGroups[a].rate_for) + '" >' + encLT(rateGroups[a].rate_for) + '</span>');
			if (cashPayment == 1 && cashPriceEnabled == 1) {
				$tmpl.find('.price .tag').text(currencyFormation.symbol + rateGroups[a].cash_rate);
			} else {
				$tmpl.find('.price .tag').text(currencyFormation.symbol + rateGroups[a].rate);
			}
			$tmpl.find('.permit-fee').addClass('permit-fee1');
			$tmpl.find('.permit-fee1').removeClass('permit-fee');
			$tmpl.find('.proc-fee').addClass('processing-fee-' + a);
			var pFee = Number(rateGroups[a].permit_fee);
			var zeroselected = (pFee == 0.00) ? 'selected' : '';
			var dynamicPermitFee = (pFee > 0) ? '<option selected>' + pFee + '</option>' : '';

			// Update permit fee options
			$tmpl.find('.permit-fee1').html(
				'<option value="0" ' + zeroselected + '>0</option>' + dynamicPermitFee
			);

			const $permitSelect = $tmpl.find('.permit-fee1');
			const $permitHidden = $tmpl.find('.permit-fee-hidden');

			// Always sync value
			$permitHidden.val(pFee);

			if (userType === 'Affiliate') {
				// Affiliate: submit hidden input ONLY
				$permitSelect
					.prop('disabled', true)
					.removeAttr('name') //  prevent duplicate submit
					.addClass('opacity-60 cursor-not-allowed');

				$permitHidden
					.attr('name', 'permit_fee[]') // submit this
					.val(pFee);

			} else {
				//  Non-Affiliate: submit select ONLY
				$permitSelect
					.prop('disabled', false)
					.attr('name', 'permit_fee[]');

				$permitHidden.removeAttr('name'); // prevent duplicate submit
			}

			if (paymentMethod === 'Card') {
				$tmpl.find('.proc-fee').text(rateGroups[a].processing_charge_percentage + '%');
			} else {
				$tmpl.find('.proc-fee').text('0.00%');
			}
			if (isPartnerPackage == 1) {
				$tmpl.find('.partner-fee').text((rateGroups[a].partner_fee_type == 'Percentage') ? rateGroups[a].partner_fee_percentage + '%' : currencyFormation.symbol + rateGroups[a].partner_fee_amount);
			}
			$tmpl.find('.adnl-tax').text(rateGroups[a].additional_tax_percentage + '%');
			if (cashPayment == 1 && cashPriceEnabled == 1) {
				$tmpl.find('.adnl-charge').text(currencyFormation.symbol + rateGroups[a].cash_tax);
			} else {
				$tmpl.find('.adnl-charge').text(currencyFormation.symbol + rateGroups[a].additional_charge);
			}

			$tblRateGrps.append($tmpl);
			k++;
		}

		totalSansDiscount = 0;
		addTaxCols();
		updateRatesTable();
		permitFeeTable();
	}

}

function getSelectedTouristsGroup(selTourists) {
	var rateWiseContactsCount = 0;
	var paxCount = 0;
	selectedTouristGroupId = $(selTourists).attr('id');

	var peoples = $(selTourists).val();
	var package = $("#tour_package").val();
	var rateGroupID = selectedTouristGroupId;

	if (parseInt(isAddonsEnabled) == 1) {
		if (isGroupRateEnabled == '1') {
			var a = 0;
			var $tblRateGrps = $('#table-rate-groups');
			$row = $($tblRateGrps.children()[a]);
			var $selTourists = $row.find('.tourists');

			if ($selTourists.val() != null) {
				paxCount = parseInt($selTourists.val());
			}
		} else {
			for (var a = 0; a < rateGroups.length; a++) {
				var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
				paxCount += parseInt(selPax.val());
			}
		}

		// Only proceed if paxCount > 0
		if (paxCount > 0) {
			const initAddons = () => {
				// Check if package has price-per-pax addons
				const hasPricePerPax = addonFormReader.hasPricePerPaxAddons();

				// Pass silent=true if no price-per-pax addons to prevent modal from opening
				addonFormReader.initAddonForm(rateGroups, totalSeatsForBooking, !hasPricePerPax);

				// Only render pax details (which opens modal) if there are price-per-pax addons
				if (hasPricePerPax) {
					addonFormReader.renderPaxDetails();
				} else {
					// Just render the addon button without opening modal
					addonFormReader.renderPaxAddonBtn();
				}
			};
			if (!addonFormReader.addonsFetched) {
				addonFormReader.fetchAddons(package, true).then((addons) => {
					if (addons) initAddons();
				});
			} else if (addonFormReader.addonsData) {
				initAddons();
			}
		}
	}

	// Used only for package with multiple customers feature
	if (bookingHasContacts == 1) {
		if (isGroupRateEnabled == '1') {
			var a = 0;
			var $tblRateGrps = $('#table-rate-groups');
			$row = $($tblRateGrps.children()[a]);
			var $selTourists = $row.find('.tourists');

			if ($selTourists.val() != null) {
				rateWiseContactsCount = parseInt($selTourists.val());
			}
		} else {
			for (var a = 0; a < rateGroups.length; a++) {
				var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
				rateWiseContactsCount += parseInt(selPax.val());
			}
		}

		rateGroupWiseContactsCount = rateWiseContactsCount;

		if (rateGroupWiseContactsCount > 0) {
			$(".add-contacts-section").removeClass('hidden');
			tourBookingContacts.initContactForm();
		} else {
			$(".add-contacts-section").addClass('hidden');
		}
	}

	$.ajax({
		url: "checkRates",
		data: { 'peoples': peoples, 'package': package, 'rateGroupID': rateGroupID },
		type: 'POST',
		dataType: 'json',
		success: function (result) {
			if (isGroupRateEnabled == '1') {
				updateGroupRatesTable();
			} else {
				updateRatesTable();
			}
		},
		error: function (jqXHR) {
			showSubmissionError(jqXHR);
			document.getElementById(rateGroupID).value = 0;
			updateRatesTable();
			removeDiscount();

			if (parseInt(isAddonsEnabled) == 1) {
				const formFields = addonFormReader.addonsData?.form?.fields;
				if (formFields && formFields.length) {
					const totalPax = $("#table-rate-groups")
						.find("input[id^='tourists-']")
						.toArray()
						.reduce((sum, el) => sum + parseInt($(el).val() || 0, 10), 0);

					if (totalPax === 0) {
						$('#paxAddonsModal').one('hidden.bs.modal', () => {
							$(".addons-section, .btn-add-addons").addClass("d-none");
						});
						addonFormReader.paxAddonsState = {
							form_id: parseInt(addonFormReader.addonsData?.form?.id || 0) || 0,
							fields: {}
						};
						const rateId = rateGroupID.replace('tourists-', '');
						$("#paxDetails").find(`.pax-detail[data-rate-id="${rateId}"]`).remove();
						$('#paxAddonsModal').modal('hide');
					}
				}
			}
		}
	});
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
				if (touristsGroups[a].selectedtourists > 0) {
					$($selTourists[b]).val(touristsGroups[a].selectedtourists);
				} else {
					$($selTourists[b]).val(touristsGroups[a].tourists);
				}
				break;
			}
		}
	}

	updateRatesTable();
	permitFeeTable();
}

function updateRatesTable() {
	if (isGroupRateEnabled == '1') {
		updateGroupRatesTable();
	} else {
		var paymentMethod = $('.checkset:checked').val();
		var cashPayment = (paymentMethod == 'Cash') ? 1 : 0;
		var tourPackageId = $('#tour_package').val();
		$('.discount-applied').val(0);
		$('.sub-total, .fees-total, .grand-total').text(currencyFormation.symbol + '0.00');

		if ($('#slot_time').val() == 0) {
			$('#table-rate-groups .tourists').html('<option>0</option>');
			return;
		}
		// Update when tour operator change
		if (affiliateCheck.length == 1 || checkAffiliate == 1 || userType == 'Affiliate') {
			serviceCommissionPercentage = rateGroups[0] ? rateGroups[0].affiliate_processing_percentage : 0;
			stripeCommissionPercentage = rateGroups[0]?.stripe_affiliate_percentage ? rateGroups[0].stripe_affiliate_percentage : 0;
		} else {
			serviceCommissionPercentage = rateGroups[0] ? rateGroups[0].service_commission_percentage : 0;
			stripeCommissionPercentage = rateGroups[0]?.stripe_commission_percentage ? rateGroups[0].stripe_commission_percentage : 0;
		}

		var $tblRateGrps = $('#table-rate-groups');
		var total = 0;
		var lookup = 0;
		var cashLookup = 0;
		var cashTotal = 0;
		serviceCommissionTotal = 0;
		serviceCommissionTotalCashNewPricing = 0;
		serviceCommissionPackage = 0;
		var touristsTotal = 0;

		for (var a = 0; a < rateGroups.length; a++) {
			$row = $($tblRateGrps.children()[a]);

			var $selTourists = $row.find('.tourists');

			if ($selTourists.val() == null) {
				$selTourists.find('option').prop('selected', true);
				var tourists = 0;
			} else {
				var tourists = parseInt($selTourists.val());
			}
			var infants = (rateGroups[a].is_infant_rate == 1) ? parseInt($selTourists.val()) : 0;
			var oldTourists = rateGroups[a].cart_tourists;
			touristsTotal += tourists - infants;
			var $selpermitfee = $row.find('.permit-fee');
			if (isNaN(parseInt($selpermitfee.val()))) {
				$selpermitfee = $row.find('.permit-fee1');
			}
			var permitfee = parseInt($selpermitfee.val());

			var groupTotal = 0;
			var groupCashTotal = 0;
			var stateTax = 0;
			var partnerFee = 0;
			var lookTotal = 0;
			var lookCashTotal = 0;
			// rate may be zero for one or more age/rate group and
			// if rate is non-zero then only add other amounts
			for (var b = 0; b < taxes.length; b++) {
				stateTax += tourists * (taxes[b].value_type == 'Percent'
					? rateGroups[a].rate * taxes[b].value / 100
					: taxes[b].value
				);
			}
			if (isPartnerPackage) {
				partnerFee = rateGroups[a].partner_fee_amount;
			}

			groupTotal = tourists * (
				parseFloat(rateGroups[a].rate)
				+ parseFloat(rateGroups[a].additional_charge)
				+ parseFloat(permitfee)
				+ parseFloat(partnerFee)
			);

			if (cashPriceEnabled == 1) {
				if (rateGroups[a].is_cash_rate == 1) {
					groupCashTotal = tourists * (
						parseFloat(rateGroups[a].cash_rate)
						+ parseFloat(rateGroups[a].cash_tax)
						+ parseFloat(permitfee)
						+ parseFloat(partnerFee)
					);
				} else {
					groupCashTotal = groupTotal;
				}
			}

			if (tourists) {
				// serviceCommissionTotal/serviceCommissionTotalCashNewPricing represent the
				// no-discount baseline commission (per-person rounded, then summed) — they must
				// be computed unconditionally on every refresh, regardless of whether a discount
				// is CURRENTLY entered. Previously this was gated on getDiscountStatus(...) == 0,
				// which left both stuck at their reset value of 0 ("stale") the moment a discount
				// was applied then cleared without an intervening full rate-table refresh — the
				// exact bug commit 308960dc ("Fixed JS calculations issues when discount and
				// payment method switched") was papering over by switching the no-discount branch
				// to a less accurate aggregate-rounded recompute instead of fixing the staleness
				// here. Neither calculateServiceCommission() nor calculateServiceCommissionCashNewPricing()
				// ever reads these values while a discount IS active (those branches use their own
				// aggregate/discounted formula), so there's no reason to zero them out meanwhile.
				var serviceCommissionPax = roundout(((parseFloat(rateGroups[a].rate) + parseFloat(rateGroups[a].additional_charge) + parseFloat(permitfee) + parseFloat(partnerFee)) * serviceCommissionPercentage / 100), 2);
				serviceCommissionPax = tourists * serviceCommissionPax;
				serviceCommissionPackage += serviceCommissionPax;
				serviceCommissionTotal += serviceCommissionPax;
				//To calculate service commission on cash payment if enabled and payment method is cash for new pricing
				if (cashPriceEnabled == 1) {
					var cashCommissionBase = (rateGroups[a].is_cash_rate == 1)
						? (parseFloat(rateGroups[a].cash_rate) + parseFloat(rateGroups[a].cash_tax))
						: (parseFloat(rateGroups[a].rate) + parseFloat(rateGroups[a].additional_charge));
					var serviceCommissionPaxCash = roundout(((cashCommissionBase + parseFloat(permitfee) + parseFloat(partnerFee)) * serviceCommissionPercentage / 100), 2);
					serviceCommissionPaxCash = tourists * serviceCommissionPaxCash;
					serviceCommissionPackage += serviceCommissionPaxCash;
					serviceCommissionTotalCashNewPricing += serviceCommissionPaxCash;
				}

				var processingCharge = Number(rateGroups[a].processing_charge_percentage);
				if (rateGroups[a].additional_tax_percentage != 0) {
					var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
					lookTotal += roundout((groupTotal * Number(rateGroups[a].additional_tax_percentage) / 100), 2);
					groupTotal += roundout((groupTotal * ad_tax_pe_n_processing_fee / 100), 2);
					if (cashPriceEnabled == 1) {
						lookCashTotal += roundout((groupCashTotal * Number(rateGroups[a].additional_tax_percentage) / 100), 2);
						groupCashTotal += roundout((groupCashTotal * ad_tax_pe_n_processing_fee / 100), 2);
					}
				} else {
					lookTotal += groupTotal;
					if (cashPriceEnabled == 1) {
						lookCashTotal += groupCashTotal;
					}
					if (processingCharge) {
						groupTotal += roundout((groupTotal * processingCharge / 100), 2);
					}
					if (processingCharge && cashPriceEnabled == 1) {
						groupCashTotal += roundout((groupCashTotal * processingCharge / 100), 2);
					}
				}
			}

			if (groupTotal) {
				if (paymentMethod === 'Card') {
					$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
				} else if (cashPayment == 1 && cashPriceEnabled == 1) {
					if (rateGroups[a].is_cash_rate == 1) {
						$row.find('.final').text(currencyFormation.symbol + groupCashTotal.toFixed(2));
					} else {
						$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
					}

				} else {
					$row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
				}
			} else {
				$row.find('.final').text(currencyFormation.symbol + '0.00');
			}
			total += groupTotal;

			lookup += lookTotal;
			cashLookup += lookCashTotal;
			cashTotal += groupCashTotal;
			if ($selTourists.children().length > 1) {
				var rateGroupId = $row.find('.hid-rate-group').val();

				for (var b = 0; b < touristsGroups.length; b++) {
					if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
						touristsGroups[b].tourists = tourists;
						touristsGroups[b].selectedtourists = oldTourists;
						break;
					}
				}

				if (b >= touristsGroups.length) { // rate group isn't in the array, so add it
					touristsGroups.push({
						rate_for: $row.find('.rate-for').text(),
						tour_package_rate_id: rateGroupId,
						tourists: tourists,
						selectedtourists: oldTourists
					});
				}
			}
		}

		totalTourists = touristsTotal;
		var seats = parseInt(totalSeatsForBooking - totalTourists);

		for (var a = 0; a < rateGroups.length; a++) {
			$row = $($tblRateGrps.children()[a]);
			var $selTourists = $row.find('.tourists');
			var isInfantRate = rateGroups[a].is_infant_rate;

			// Skip infant rate while adjusting tourists options
			if (isInfantRate == 1) {
				continue;
			}

			if (!isNaN(seats) && totalTourists > 0) {
				if (parseInt($selTourists.val()) == 0) {
					$row.find('.tourists option').filter(function () {
						return parseInt(this.value, 10) > seats;
					}).remove();
					var lastOptionValue = $row.find('.tourists option:last').val();

					if (lastOptionValue > 0 && lastOptionValue < seats) {
						for (var b = parseInt(lastOptionValue), $selTourists; b < parseInt(seats); b++)
							$selTourists.append(new Option(b + 1));
					}
				} else {
					if (selectedTouristGroupId != '') {
						$row.find('.tourists:not(#' + selectedTouristGroupId + ') option').filter(function () {
							return (parseInt(this.value, 10) > parseInt($selTourists.val()) && parseInt(this.value, 10) > seats);
						}).remove();
					} else {
						$row.find('.tourists option').filter(function () {
							return (parseInt(this.value, 10) > parseInt($selTourists.val()) && parseInt(this.value, 10) > seats);
						}).remove();
						var lastOptionValue = $row.find('.tourists option:last').val();
						if (lastOptionValue > 0 && lastOptionValue < seats) {
							for (var b = parseInt(lastOptionValue), $selTourists; b < parseInt(seats); b++)
								$selTourists.append(new Option(b + 1));
						}
					}
				}
			}
		}

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
			$('.fees-total').text(currencyFormation.symbol + '0.00');
			$('.grand-total').text(currencyFormation.symbol + '0.00');
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
						cashLookup = cashLookup - parseFloat(cartPackageDetails.cashSubtt || 0);
						cashLookup += totalCartCashTicketCost;
						if (serviceCommissionTotal > cartPackageDetails.fee) {
							serviceCommissionTotal = serviceCommissionTotal - cartPackageDetails.fee;
							serviceCommissionTotal += totalCartServiceCommission;
						}
					} else if (total < cartPackageDetails.subtt) {
						lookup = total = cartPackageDetails.subtt - total;
						lookup = total = totalCartTicketCost - total;
						cashLookup = parseFloat(cartPackageDetails.cashSubtt || 0) - cashLookup;
						cashLookup = totalCartCashTicketCost - cashLookup;
						if (serviceCommissionTotal < cartPackageDetails.fee) {
							serviceCommissionTotal = cartPackageDetails.fee - serviceCommissionTotal;
							serviceCommissionTotal = totalCartServiceCommission - serviceCommissionTotal;
						}
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
				$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + total.toFixed(2));
			} else if (cashPayment == 1 && cashPriceEnabled == 1) {
				$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + cashLookup.toFixed(2));
			} else {
				$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + lookup.toFixed(2));
			}
		} else {
			$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + '0.00');
			$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + '0.00');
			$('.service-commission').text(currencyFormation.symbol + '0.00');
			$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + '0.00');
			$('.cash-set').text(currencyFormation.symbol + '0.00');
			$('.fees-total').text(currencyFormation.symbol + '0.00');
			$('.grand-total').text(currencyFormation.symbol + '0.00');
			$('.total-').val('0.00');
			$('.fee-').val('0.00');
			$('.custom-charge-').val('0.00');
		}

		let addonPrice = (totalCartAddonsPrice - (window.editingCartPackageAddonPrice || 0)) + initTourBookingAddCheckoutAddons.getPrice() + addonFormReader.getPrice();
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
			applyDiscount();
		}
	}

	hideShowSplitInputUI();
}

function disablePromoRadio() {
	$("#discount_type_promo_code").prop('checked', false);
	$("#discount_type_percentage").prop('checked', false);
	$("#discount_type_fixed_money").prop('checked', false);
}

function enableDiscount(chk) {
	$('#discount_applied').val(0);
	$('#is_applied').val(0);

	var discountFixedMoney = $("#discount_fixed_money");
	var discountPercentage = $("#discount_percentage");
	var discountPromoCode = $("#discount_promo_code");
	var applyPromoBtn = $(".apply-promo-btn");
	var removePromoBtn = $(".remove-promo-btn");
	var tourPackageId = $("#tour_package").val();
	var timeSloat = $('#slot_time').val();

	if (!tourPackageId) {
		disablePromoRadio();
		swal('Please select tour package', '', 'warning');
		return;
	}

	if (!timeSloat) {
		disablePromoRadio();
		swal('Please select time slot', '', 'warning');
		return;
	}

	if (chk.value == 'Percentage') {
		disableAndClear(discountFixedMoney, discountPromoCode);
		enableAndFocus(discountPercentage);
		applyPromoBtn.show();
		removePromoBtn.hide();
	} else if (chk.value == 'Promo Code') {
		disableAndClear(discountFixedMoney, discountPercentage);
		enableAndFocus(discountPromoCode);
	} else {
		enableAndFocus(discountFixedMoney);
		disableAndClear(discountPercentage, discountPromoCode);
		applyPromoBtn.show();
		removePromoBtn.hide();
	}

	applyDiscount();
}

function disableAndClear(element1, element2) {
	element1.prop('disabled', true).val('');
	element2.prop('disabled', true).val('');
}

function enableAndFocus(element) {
	element.prop('disabled', false).focus();
}

function applyPromoDiscount() {
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
			applyPromoDiscountCalculation(result);
		},
		error: function (jqXHR) {
			showSubmissionError(jqXHR);
		}
	});
}

function applyPromoDiscountCalculation(promoDetails) {
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
	var lookupAfterDiscountBeforeServiceCommissionPackageCash = lookupForCashPaymentMethodPackage;
	var discountForCard = 0;
	var discountForCashAndPayLater = 0;
	var discountForNewCashPrice = 0;
	//Check for old discount
	var oldDiscount = 0;
	var otherPackageDiscount = 0;
	var otherPackageDiscountPercentage = 0;
	var oldDiscountCash = 0;
	var otherPackageDiscountCash = 0;
	var tourPackageId = $('#tour_package').val();

	if (cartView == 1) {
		var cartPackageDetails = getCartPackageDetails(tourPackageId);
		if (Object.keys(cartPackageDetails).length > 0) {
			oldDiscount = cartPackageDetails.discount_amount;
			oldDiscountPercentage = cartPackageDetails.discount_percentage;
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

	if (promoDetails.discount_value_type) {
		var inputVal = parseFloat(promoDetails.discount_value);

		if (isNaN(inputVal)) // blank or something else
			inputVal = 0;

		if (promoDetails.discount_value_type === 'Percent') {

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
					discountForNewCashPrice = (lookupAfterDiscountBeforeServiceCommissionPackageCash * inputVal) / 100;
				}

				discountForCard += otherPackageDiscount;
				discountForCashAndPayLater += otherPackageDiscount;
				discountForNewCashPrice += otherPackageDiscountCash;
			} else {
				discountForCashAndPayLater = (lookupForCashAndPayLaterPaymentMethod * inputVal) / 100;
				discountForCard = (totalBeforeDiscountAndServiceCommission * inputVal) / 100;
				discountForNewCashPrice = (lookupForCashPaymentMethod * inputVal) / 100;
			}

			totalAfterDiscountBeforeServiceCommissionPackage = parseFloat((totalAfterDiscountBeforeServiceCommissionPackage - discountForCard).toFixed(10)) || 0;
			totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - discountForCard).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - discountForCashAndPayLater).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - discountForNewCashPrice).toFixed(10)) || 0;
			totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - discountForNewCashPrice).toFixed(10)) || 0;

			if (paymentMethod != 'Card') {
				if (cashPayment == 1 && cashPriceEnabled == 1) {
					discount = discountForNewCashPrice;
				} else {
					discount = discountForCashAndPayLater;
				}
			} else {
				discount = discountForCard;
			}

			var TotalDiscount = roundout(parseFloat(inputVal) + parseFloat(otherPackageDiscountPercentage));
			$('.discount-info').text('(' + TotalDiscount + '%)');
		} else {
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

			totalAfterDiscountBeforeServiceCommissionPackage = parseFloat((totalAfterDiscountBeforeServiceCommissionPackage - discount).toFixed(10)) || 0;
			totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - discount).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - discount).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - discount).toFixed(10)) || 0;
			totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - discount).toFixed(10)) || 0;
		}
	} else if (cartView == 1 && totalCartDiscount > 0) {
		$('.discount-info').text('(' + roundout(otherPackageDiscountPercentage) + '%)');
		totalCartDiscount = discount = otherPackageDiscount;
		totalCartDiscountCash = otherPackageDiscountCash;
		if (cashPayment == 1 && cashPriceEnabled == 1) {
			discount = totalCartDiscountCash;
		}
		totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
		lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
		lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
		totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
	} else {
		totalCartDiscount = otherPackageDiscount;
		totalCartDiscountCash = otherPackageDiscountCash;
		totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
		lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
		lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
		totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
		$('.discount-info').text('(0%)');
	}
	var serviceCommission = 0;
	var serviceCommissionCash = 0;
	var discountType = '';

	if (promoDetails.discount_value_type) {
		var discountType = promoDetails.discount_value_type;
	}
	const addOnFee = initTourBookingAddCheckoutAddons.getFees() + addonFormReader.getFees();
	const tipFee = initTourBookingGratuity.getTipFeesAmount();
	serviceCommission = calculateServiceCommission(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommission, oldDiscount, 'applyPromoDiscountCalculation');
	serviceCommission = updateCustomChargeFees(serviceCommission);
	serviceCommission = roundout(serviceCommission + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + tipFee + customChargeFeesTotal, 2);

	serviceCommissionCash = calculateServiceCommissionCashNewPricing(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommissionCash, oldDiscount, 'applyPromoDiscountCalculation');
	serviceCommissionCash = updateCustomChargeFees(serviceCommissionCash);
	serviceCommissionCash = roundout(serviceCommissionCash + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + tipFee + customChargeFeesTotal, 2);

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

function calculateServiceCommission(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommission, oldDiscount, functionMethod) {
	if (cartPackages.length > 0 && cartPackageDetails != "") {
		var otherPackageServicefee = 0;
		var allPackageServicefee = 0;

		for (var a = 0; a < cartPackages.length; a++) {
			allPackageServicefee += cartPackages[a].fee;
			if (cartPackages[a].tour_package_id != tourPackageId) {
				otherPackageServicefee += cartPackages[a].fee;
			}
		}

		if (discountType == 'Percent') {
			serviceCommission = roundout(((cartPackageDetails.subtt - (cartPackageDetails.subtt * inputVal / 100)) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
		} else if (discountType == 'Fixed Money') {
			serviceCommission = roundout(((cartPackageDetails.subtt - inputVal) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
		} else {
			if (functionMethod == 'applyPromoDiscountCalculation') {
				serviceCommission = roundout(((cartPackageDetails.subtt) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
			} else if (functionMethod == 'applyDiscount') {
				serviceCommission = serviceCommissionTotal;
			}
		}
	} else {
		if (discount > 0 || (cartView == 1 && totalCartDiscount > 0)) {
			if (cartView == 1) {
				if (totalCartDiscount > 0) {
					var otherPackageServicefee = 0;
					var allPackageServicefee = 0;
					for (var a = 0; a < cartPackages.length; a++) {
						allPackageServicefee += cartPackages[a].fee;
						if (cartPackages[a].tour_package_id != tourPackageId) {
							otherPackageServicefee += cartPackages[a].fee;
						}
					}
					if (discountType == 'Percent') {
						serviceCommission = roundout(((currentPackageTotalAfterDiscountBeforeServiceCommission - (currentPackageTotalAfterDiscountBeforeServiceCommission * inputVal / 100)) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
					} else if (discountType == 'Fixed Money') {
						serviceCommission = roundout(((currentPackageTotalAfterDiscountBeforeServiceCommission - inputVal) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
					} else {
						serviceCommission = roundout(((currentPackageTotalAfterDiscountBeforeServiceCommission) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
					}
				} else {
					serviceCommission = roundout((totalAfterDiscountBeforeServiceCommission * serviceCommissionPercentage / 100), 2);
				}
			} else {
				serviceCommission = roundout((totalAfterDiscountBeforeServiceCommission * serviceCommissionPercentage / 100), 2);
			}
		} else if (cartView == 1 && oldDiscount > 0 && discount == 0) {
			serviceCommission = roundout((totalAfterDiscountBeforeServiceCommission * serviceCommissionPercentage / 100), 2);
		} else if (cartView == 1) {
			// Multi-package cart: serviceCommissionTotal is the maintained cart-wide aggregate.
			serviceCommission = serviceCommissionTotal;
		} else {
			// Single, non-cart booking (cart feature disabled, e.g. dixies) with no discount.
			// serviceCommissionTotal IS freshly computed per-person-then-summed by
			// updateRatesTable()/updateGroupRatesTable() (see the serviceCommissionPax loop
			// there) immediately before every call to applyDiscount() that reaches this branch —
			// it is NOT stale here despite the previous comment's claim. Recomputing on the
			// aggregate totalAfterDiscountBeforeServiceCommission instead (as the sibling
			// discount>0 branch above correctly does, since a discount can't be cleanly
			// allocated per person) rounds once on the combined total, which can be a cent off
			// from the correct per-person-rounded sum (e.g. 2 guests at $75/each, 9.809%:
			// per-person 7.35675→$7.36 ×2 = $14.72, vs aggregate 150×9.809%=14.7135→$14.71).
			serviceCommission = serviceCommissionTotal;
		}
	}

	return serviceCommission;
}
function calculateServiceCommissionCashNewPricing(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommissionCash, oldDiscount, functionMethod) {
	if (cartPackages.length > 0 && cartPackageDetails != "") {
		var otherPackageServicefee = 0;
		var allPackageServicefee = 0;

		for (var a = 0; a < cartPackages.length; a++) {
			allPackageServicefee += cartPackages[a].cashFee;
			if (cartPackages[a].tour_package_id != tourPackageId) {
				otherPackageServicefee += cartPackages[a].cashFee;
			}
		}

		if (discountType == 'Percent') {
			serviceCommissionCash = roundout(((cartPackageDetails.cashSubtt - (cartPackageDetails.subtt * inputVal / 100)) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
		} else if (discountType == 'Fixed Money') {
			serviceCommissionCash = roundout(((cartPackageDetails.cashSubtt - inputVal) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
		} else {
			if (functionMethod == 'applyPromoDiscountCalculation') {
				serviceCommissionCash = roundout(((cartPackageDetails.cashSubtt) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
			} else if (functionMethod == 'applyDiscount') {
				serviceCommissionCash = serviceCommissionTotalCashNewPricing;
			}
		}
	} else {
		if (discount > 0 || (cartView == 1 && totalCartDiscount > 0)) {
			if (cartView == 1) {
				if (totalCartDiscount > 0) {
					var otherPackageServicefee = 0;
					var allPackageServicefee = 0;
					for (var a = 0; a < cartPackages.length; a++) {
						allPackageServicefee += cartPackages[a].cashFee;
						if (cartPackages[a].tour_package_id != tourPackageId) {
							otherPackageServicefee += cartPackages[a].cashFee;
						}
					}
					if (discountType == 'Percent') {
						serviceCommissionCash = roundout(((currentPackageTotalAfterDiscountBeforeServiceCommission - (currentPackageTotalAfterDiscountBeforeServiceCommission * inputVal / 100)) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
					} else if (discountType == 'Fixed Money') {
						serviceCommissionCash = roundout(((currentPackageTotalAfterDiscountBeforeServiceCommission - inputVal) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
					} else {
						serviceCommissionCash = roundout(((currentPackageTotalAfterDiscountBeforeServiceCommission) * serviceCommissionPercentage / 100 + otherPackageServicefee), 2);
					}
				} else {
					serviceCommissionCash = roundout((totalAfterDiscountBeforeServiceCommissionCash * serviceCommissionPercentage / 100), 2);
				}
			} else {
				serviceCommissionCash = roundout((totalAfterDiscountBeforeServiceCommissionCash * serviceCommissionPercentage / 100), 2);
			}
		} else if (cartView == 1 && oldDiscount > 0 && discount == 0) {
			serviceCommissionCash = roundout((totalAfterDiscountBeforeServiceCommissionCash * serviceCommissionPercentage / 100), 2);
		} else if (cartView == 1) {
			// Multi-package cart: serviceCommissionTotalCashNewPricing is the maintained cart-wide aggregate.
			serviceCommissionCash = serviceCommissionTotalCashNewPricing;
		} else {
			// Single, non-cart booking (cart feature disabled) with no discount —
			// serviceCommissionTotalCashNewPricing is freshly per-person-then-summed by
			// updateRatesTable() immediately before this runs; see calculateServiceCommission()
			// above for why recomputing on the aggregate total instead is wrong.
			serviceCommissionCash = serviceCommissionTotalCashNewPricing;
		}
	}

	return serviceCommissionCash;
}

function applyDiscount() {

	if (isGroupRateEnabled == '1') {
		applyDiscountGroupRate();
	} else {
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
		var lookupAfterDiscountBeforeServiceCommissionPackageCash = lookupForCashPaymentMethodPackage;
		var discountForCard = 0;
		var discountForCashAndPayLater = 0;
		var discountForNewCashPrice = 0;
		//Check for old discount
		var oldDiscount = 0;
		var otherPackageDiscount = 0;
		var otherPackageDiscountPercentage = 0;
		var oldDiscountCash = 0;
		var otherPackageDiscountCash = 0;
		var tourPackageId = $('#tour_package').val();

		if (cartView == 1) {
			var cartPackageDetails = getCartPackageDetails(tourPackageId);

			if (Object.keys(cartPackageDetails).length > 0) {
				oldDiscount = cartPackageDetails.discount_amount;
				oldDiscountPercentage = cartPackageDetails.discount_percentage;
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
						discountForNewCashPrice = (lookupAfterDiscountBeforeServiceCommissionPackageCash * inputVal) / 100;
					}

					discountForCard += otherPackageDiscount;
					discountForCashAndPayLater += otherPackageDiscount;
					discountForNewCashPrice += otherPackageDiscountCash;
				} else {
					discountForCashAndPayLater = (lookupForCashAndPayLaterPaymentMethod * inputVal) / 100;
					discountForCard = (totalBeforeDiscountAndServiceCommission * inputVal) / 100;
					discountForNewCashPrice = (lookupForCashPaymentMethod * inputVal) / 100;
				}

				totalAfterDiscountBeforeServiceCommissionPackage = parseFloat((totalAfterDiscountBeforeServiceCommissionPackage - discountForCard).toFixed(10)) || 0;
				totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - discountForCard).toFixed(10)) || 0;
				lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - discountForCashAndPayLater).toFixed(10)) || 0;
				lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - discountForNewCashPrice).toFixed(10)) || 0;
				totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - discountForNewCashPrice).toFixed(10)) || 0;

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
					var TotalDiscount = roundout(parseFloat(otherPackageDiscountPercentage));
				} else {
					var TotalDiscount = roundout(parseFloat(inputVal) + parseFloat(otherPackageDiscountPercentage));
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

				totalAfterDiscountBeforeServiceCommissionPackage = parseFloat((totalAfterDiscountBeforeServiceCommissionPackage - discount).toFixed(10)) || 0;
				totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - discount).toFixed(10)) || 0;
				lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - discount).toFixed(10)) || 0;
				lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - discount).toFixed(10)) || 0;
				totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - discount).toFixed(10)) || 0;
			}
		} else if (cartView == 1 && totalCartDiscount > 0) {
			$('.discount-info').text('(' + roundout(otherPackageDiscountPercentage) + '%)');
			totalCartDiscount = discount = otherPackageDiscount;
			totalCartDiscountCash = otherPackageDiscountCash;
			if (cashPayment == 1 && cashPriceEnabled == 1) {
				discount = totalCartDiscountCash;
			}
			totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
			totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
		} else {
			totalCartDiscount = otherPackageDiscount;
			totalCartDiscountCash = otherPackageDiscountCash;
			totalAfterDiscountBeforeServiceCommission = parseFloat((totalAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommission = parseFloat((lookupAfterDiscountBeforeServiceCommission - totalCartDiscount).toFixed(10)) || 0;
			lookupAfterDiscountBeforeServiceCommissionCash = parseFloat((lookupAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
			totalAfterDiscountBeforeServiceCommissionCash = parseFloat((totalAfterDiscountBeforeServiceCommissionCash - totalCartDiscountCash).toFixed(10)) || 0;
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

		const addOnFee = initTourBookingAddCheckoutAddons.getFees() + addonFormReader.getFees();
		var tipFeesTotal = initTourBookingGratuity.getTipFees();
		serviceCommission = calculateServiceCommission(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommission, oldDiscount, 'applyDiscount');
		serviceCommission = updateCustomChargeFees(serviceCommission);
		serviceCommission = roundout(serviceCommission + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + tipFeesTotal + customChargeFeesTotal, 2);

		// Calculate service commission for cash payment method if new pricing enabled
		serviceCommissionCash = calculateServiceCommissionCashNewPricing(cartPackages, cartPackageDetails, discountType, inputVal, serviceCommissionPercentage, tourPackageId, discount, cartView, totalCartDiscount, currentPackageTotalAfterDiscountBeforeServiceCommission, totalAfterDiscountBeforeServiceCommissionCash, oldDiscount, 'applyDiscount');
		serviceCommissionCash = updateCustomChargeFees(serviceCommissionCash);
		serviceCommissionCash = roundout(serviceCommissionCash + (totalCartAddonsFees - (window.editingCartPackageAddonFee || 0)) + addOnFee + tipFeesTotal + customChargeFeesTotal, 2);

		updateUIWithDiscount(cashFees, serviceCommission, serviceCommissionCash, lookupAfterDiscountBeforeServiceCommission, lookupAfterDiscountBeforeServiceCommissionCash, paymentMethod, totalAfterDiscountBeforeServiceCommission, discount, cartView, totalCartDiscount, subtotal);
	}
}

function updateUIWithDiscount(cashFees, serviceCommission, serviceCommissionCash, lookupAfterDiscountBeforeServiceCommission, lookupAfterDiscountBeforeServiceCommissionCash, paymentMethod, totalAfterDiscountBeforeServiceCommission, discount, cartView, totalCartDiscount, subtotal) {
	const currentAddonsPrice = initTourBookingAddCheckoutAddons.getPrice() + addonFormReader.getPrice();
	const otherCartAddonsPrice = totalCartAddonsPrice - (window.editingCartPackageAddonPrice || 0);
	var tipAmountTotal = initTourBookingGratuity.getTipAmountTotal();
	serviceCommission = roundout(serviceCommission, 2);
	serviceCommissionCash = roundout(serviceCommissionCash, 2);
	totalAfterDiscountAndServiceCommission = totalAfterDiscountBeforeServiceCommission + otherCartAddonsPrice + currentAddonsPrice + customChargeTotal + tipAmountTotal + serviceCommission;
	lookupAfterDiscountAndServiceCommission = lookupAfterDiscountBeforeServiceCommission + otherCartAddonsPrice + currentAddonsPrice + customChargeTotal + tipAmountTotal;
	lookupAfterDiscountAndServiceCommissionCash = lookupAfterDiscountBeforeServiceCommissionCash + otherCartAddonsPrice + currentAddonsPrice + customChargeTotal + tipAmountTotal;
	totalAfterDiscountAndServiceCommissionCash = lookupAfterDiscountBeforeServiceCommissionCash + otherCartAddonsPrice + currentAddonsPrice + customChargeTotal + tipAmountTotal + serviceCommissionCash;
	window.cashTotalAfterDiscountAndServiceCommission = (cashFees == 1) ? totalAfterDiscountAndServiceCommissionCash : lookupAfterDiscountAndServiceCommissionCash;
	window.cardTotalAfterDiscountAndServiceCommission = totalAfterDiscountAndServiceCommission;


	// JS hack to avoid 3 or more digits after decimal point
	if (isNaN(discount)) {
		$('.discount-value').text(currencyFormation.symbol + '0.00');
	} else if (cartView == 1 && totalCartDiscount > 0) {
		$('.discount-value').text(currencyFormation.symbol + discount.toFixed(2));
	} else {
		if (discount < 0) {
			swal('Please enter a valid discount.', '', 'warning');
			$('#discount_fixed_money').val('');
			$('#discount_percentage').val('');
			$('.discount-info').text('(0%)');
			return false;
		} else {
			var subtotal = ($("#sub-total").text());
			subtotal = subtotal.slice(1);
			subtotal = parseFloat(subtotal);
			if (subtotal > 0 && discount > subtotal) {
				$('.discount-value').text(currencyFormation.symbol + '0.00');
				swal('Please enter a valid discount.', '', 'warning');
				$('#discount_fixed_money').val('');
				$('#discount_percentage').val('');
				$('.discount-info').text('(0%)');
				if (cartView != 1) {
					applyDiscount();
				}
			} else {
				$('.discount-value').text(currencyFormation.symbol + discount.toFixed(2));
			}
		}
	}

	if (isNaN(lookupAfterDiscountAndServiceCommission)) {
		$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + '0.00');
		$('.cash-set').text(currencyFormation.symbol + '0.00');
		$('.fees-total').text(currencyFormation.symbol + '0.00');
		$('.grand-total').text(currencyFormation.symbol + '0.00');
		$('.total-').val(0.00);
	} else {
		if (paymentMethod == "Card") {
			if (totalAfterDiscountBeforeServiceCommission >= 0) {
				$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + totalAfterDiscountBeforeServiceCommission.toFixed(2));
			}
		} else if (paymentMethod == "Cash" && cashPriceEnabled == 1) {
			if (lookupAfterDiscountBeforeServiceCommissionCash >= 0) {
				cashAmount = lookupAfterDiscountBeforeServiceCommissionCash;
				$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + lookupAfterDiscountBeforeServiceCommissionCash.toFixed(2));
			}

		} else {
			if (lookupAfterDiscountBeforeServiceCommission >= 0) {
				cashAmount = lookupAfterDiscountBeforeServiceCommission;
				$('.total-after-discount-before-service-commission').text(currencyFormation.symbol + lookupAfterDiscountBeforeServiceCommission.toFixed(2));
			}
		}


		// if (lookupAfterDiscountAndServiceCommission >= 0) {
		// 	$('#M').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommission.toFixed(2));
		// 	$('#pay_later').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommission.toFixed(2));
		// 	if (paymentMethod == "Cash" && cashPriceEnabled == 1) {
		// 		$('#div2 .cash-set').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommissionCash.toFixed(2));
		// 	} else {
		// 		$('#div2 .cash-set').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommission.toFixed(2));
		// 	}
		// 	if (paymentMethod == 'Pay Later' || paymentMethod == 'Card') {
		// 		$('#pcash .cash-set').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommission.toFixed(2));
		// 	}
		// 	if ($('#dividedBy').find('option:selected').val() != 'Please select option' && $('input[name=payment_method]:checked').val() == 'Cash') {
		// 		var optionValue = $('#dividedBy').find('option:selected').val();
		// 		var splitValue = lookupAfterDiscountAndServiceCommission.toFixed(2) / optionValue;
		// 		$('#split_value_field').val(splitValue);
		// 		$('.split-check').text(splitValue);
		// 	}
		// }
		// if (paymentMethod == "Cash" && cashPriceEnabled == 1) {
		// 	$('#M').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommissionCash.toFixed(2));
		// 	$('#div2 .cash-set').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommissionCash.toFixed(2));
		// 	$('#pcash .cash-set').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommissionCash.toFixed(2))
		// 	$('#pay_later').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommission.toFixed(2));
		// 	if ($('#dividedBy').find('option:selected').val() != 'Please select option' && $('input[name=payment_method]:checked').val() == 'Cash') {
		// 		var optionValue = $('#dividedBy').find('option:selected').val();
		// 		var splitValue = lookupAfterDiscountAndServiceCommissionCash.toFixed(2) / optionValue;
		// 		$('#split_value_field').val(splitValue);
		// 		$('.split-check').text(splitValue);
		// 	}
		// }

		if (lookupAfterDiscountAndServiceCommission >= 0) {
			const isCashWithNewPricing = (paymentMethod === 'Cash' && cashPriceEnabled == 1);
			const fmt = (val) => currencyFormation.symbol + val.toFixed(2);
			const primaryValue = isCashWithNewPricing
				? lookupAfterDiscountAndServiceCommissionCash
				: lookupAfterDiscountAndServiceCommission;

			// #M and #div2 .cash-set always use cash value if cash+enabled, else standard
			$('#M').text(fmt(primaryValue));
			$('#div2 .cash-set').text(fmt(primaryValue));

			// #pay_later always shows the standard (non-cash) value
			$('#pay_later').text(fmt(lookupAfterDiscountAndServiceCommission));

			// #pcash .cash-set only for Pay Later / Card / Cash+newPricing
			if (paymentMethod === 'Pay Later' || paymentMethod === 'Card') {
				$('#pcash .cash-set').text(fmt(lookupAfterDiscountAndServiceCommission));
			} else if (isCashWithNewPricing) {
				$('#pcash .cash-set').text(fmt(lookupAfterDiscountAndServiceCommissionCash));
			}

			// Split value — use correct base depending on payment method
			if ($('#dividedBy').find('option:selected').val() !== 'Please select option'
				&& paymentMethod === 'Cash') {
				var optionValue = parseFloat($('#dividedBy').find('option:selected').val());
				var splitValue = primaryValue / optionValue; // numeric division, not string
				$('#split_value_field').val(splitValue.toFixed(2));
				$('.split-check').text(splitValue.toFixed(2));
			}
		}


		if (paymentMethod !== 'Card') {
			if (paymentMethod == "Cash" && cashPriceEnabled == 1) {
				if (lookupAfterDiscountBeforeServiceCommissionCash >= 0) {
					$('.total-').val(lookupAfterDiscountBeforeServiceCommissionCash.toFixed(2));
				}
			} else {
				if (lookupAfterDiscountBeforeServiceCommission >= 0) {
					$('.total-').val(lookupAfterDiscountBeforeServiceCommission.toFixed(2));
				}
			}
		} else if (paymentMethod == "Cash" && cashPriceEnabled == 1) {
			if (lookupAfterDiscountBeforeServiceCommissionCash >= 0) {
				$('.total-').val(lookupAfterDiscountBeforeServiceCommissionCash.toFixed(2));
			}
		} else {
			if (totalAfterDiscountBeforeServiceCommission >= 0) {
				$('.total-').val(totalAfterDiscountBeforeServiceCommission.toFixed(2));
			}
		}
	}

	if (isNaN(serviceCommission)) {
		$('.service-commission').text(currencyFormation.symbol + '0.00');
		$('.fees-total').text(currencyFormation.symbol + '0.00');
		$('.fee-').val(0.00);
	} else {

		if (serviceCommissionCash >= 0 && paymentMethod == "Cash" && cashPriceEnabled == 1) {
			$('.service-commission').text(currencyFormation.symbol + serviceCommissionCash.toFixed(2));
			$('.fees-total').text(currencyFormation.symbol + serviceCommissionCash.toFixed(2));
			$('.fee-').val(serviceCommissionCash.toFixed(2));
		} else {
			if (serviceCommission >= 0) {
				$('.service-commission').text(currencyFormation.symbol + serviceCommission.toFixed(2));
				$('.fees-total').text(currencyFormation.symbol + serviceCommission.toFixed(2));
				$('.fee-').val(serviceCommission.toFixed(2));
			}
		}
	}

	// Addon fee sub-row: visible for Card, or Cash when cashFees=1; hidden otherwise
	const $addonFeeRow = $('.addons-fee-row');
	if ($addonFeeRow.length) {
		const addonFeeVal = addonFormReader.getFees() + (typeof initTourBookingAddCheckoutAddons !== 'undefined' ? initTourBookingAddCheckoutAddons.getFees() : 0);
		const showAddonFee = addonFeeVal > 0 && (paymentMethod === 'Card' || paymentMethod === 'Link' || (paymentMethod === 'Cash' && cashFees == 1));
		if (showAddonFee) {
			$addonFeeRow.find('.addons-fee-display').text(currencyFormation.symbol + addonFeeVal.toFixed(2));
			$addonFeeRow.show();
		} else {
			$addonFeeRow.hide();
		}
	}

	// For custom charge only
	$('#customchargecard').find('.custom-charge').text(currencyFormation.symbol + customChargeTotal.toFixed(2));
	$(".custom-charge-").val(customChargeTotal.toFixed(2));
	$(".custom-charge-fees-").val(customChargeFeesTotal.toFixed(2));
	$('#customchargecard').removeClass('d-none');

	//For tip amount only
	$('.tip-amount-input').val(tipAmountTotal.toFixed(2));
	$('.tip-amount-display').text(currencyFormation.symbol + tipAmountTotal.toFixed(2));
	if (isNaN(totalAfterDiscountAndServiceCommission)) {
		$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + '0.00');
		$('.grand-total').text(currencyFormation.symbol + '0.00');
	} else {
		if (totalAfterDiscountAndServiceCommission >= 0 || (paymentMethod == "Cash" && cashPriceEnabled == 1 && totalAfterDiscountAndServiceCommissionCash >= 0)) {
			if (paymentMethod == "Cash" && cashPriceEnabled == 1) {

				$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommissionCash.toFixed(2));
				$('.grand-total').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommissionCash.toFixed(2));
				// Update pay with link and card payment method amounts if new cash price enabled
				$('#K').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommission.toFixed(2));
				$('#payWithLinkAmount').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommission.toFixed(2));
			} else {
				$('.total-after-discount-and-service-commission').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommission.toFixed(2));
				$('.grand-total').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommission.toFixed(2));
			}
			if (cashFees == 1) {
				if (cashPriceEnabled == 1) {
					$('#M').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommissionCash.toFixed(2));
					cashAmount = totalAfterDiscountAndServiceCommissionCash;
				} else {
					$('#M').text(currencyFormation.symbol + totalAfterDiscountAndServiceCommission.toFixed(2));
					cashAmount = totalAfterDiscountAndServiceCommission;
				}
			} else if (cashPriceEnabled == 1) {
				$('#M').text(currencyFormation.symbol + lookupAfterDiscountAndServiceCommissionCash.toFixed(2));
				cashAmount = lookupAfterDiscountAndServiceCommissionCash;
			}
			if (cashPriceEnabled == 1 && paymentMethod == "Cash") {
				var cashDisplayTotal = (cashFees == 1)
					? totalAfterDiscountAndServiceCommissionCash
					: lookupAfterDiscountAndServiceCommissionCash;
				$('#pcash .cash-set').text(currencyFormation.symbol + cashDisplayTotal.toFixed(2));
			}
			if ($('#dividedBy').find('option:selected').val() != 'Please select option' && ($('input[name=payment_method]:checked').val() == 'Card' || (cashFees == '1' && $('input[name=payment_method]:checked').val() == 'Pay Later'))) {
				var optionValue = $('#dividedBy').find('option:selected').val();
				var splitValue = (paymentMethod == "Cash" && cashPriceEnabled == 1) ? totalAfterDiscountAndServiceCommissionCash.toFixed(2) / optionValue : totalAfterDiscountAndServiceCommission.toFixed(2) / optionValue;
				$('#split_value_field').val(splitValue);
				$('.split-check').text(splitValue);
			}
		}
	}

	if (paymentMethod == "Pay Later") {
		tourBookingPaymentLink.paymentLinkModal();
	}
}

function removePromoDiscount() {
	var addText = 'Are you sure you want to remove this promo code?';
	swal({
		title: 'Remove Promo Code',
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
		if (result == true) {
			if ($("#is_applied").val() == 1) {
				$(".apply-promo-btn").show();
				$(".remove-promo-btn").hide();
				$("#discount_type_promo_code").prop('checked', false);
				$("#discount_promo_code").prop('disabled', true).val('');
				$("#is_applied").val(0);
				applyPromoDiscountCalculation(result);
			}
		}
	});
}

function removeDiscount() {
	cachedPromoDetails = null;
	if ($("#is_applied").val() == 1) {
		$(".apply-promo-btn").show();
		$(".remove-promo-btn").hide();
		$("#discount_type_promo_code").prop('checked', false);
		$("#discount_promo_code").prop('disabled', true).val('');
		$("#is_applied").val(0);
		applyPromoDiscountCalculation('');
	}
}

function validateAndSubmitForm(form, validationRateGroupsApproval = false) {
	if (!validateEmailAddressFormat({ inputId: 'customer_email' }))
		return false;

	if (!validateCharacters('#customer_name, #customer_phone_number'))
		return false;

	if (cartView == 0 || (cartView == 1 && isComboPackage == 0 && Object.keys(cartPackages).length == 0)) {
		var touristsCount = 0;
		if (isGroupRateEnabled == '1') {
			var selPax = $($('#table-rate-groups').children()[0]).find('.tourists');
			touristsCount = parseInt(selPax.val());
		} else {
			for (var a = 0; a < rateGroups.length; a++) {
				var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
				touristsCount += parseInt(selPax.val());
			}
		}

		if (touristsCount == 0) {
			swal('Please select at least one tourist.', '', 'warning');
			return false;
		}
	}
	if (cartView == 0 && userType == 'Tour Operator' && rateGroupsNeedsApproval.length > 0 && validationRateGroupsApproval == false) {
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

	if ($('#affiliatecust').length > 0) {
		var affiliate_id = $('#affiliatecust option:selected').val();
		if (affiliate_id > 0 && $('#is_reserved_for_affiliate').length > 0 && $('#is_reserved_for_affiliate option:selected').val() == '1') {
			if (($('#pay-with-card').length && $('#pay-with-card')[0].checked) || ($('#pay-with-cash').length && $('#pay-with-cash')[0].checked)) {
				swal('You do not allow to reserve seats using any payment method.', '', 'warning');
				return false;
			}
		}
	}

	if (cartView == 0 && bookingHasContacts == 1) {
		if (rateGroupWiseContactsCount > 0) {
			var contactsValidation = tourBookingContacts.newBookingValidation();

			if (!contactsValidation) {
				return false;
			}
		}
	}

	if (cartView == 0 && parseInt(isAddonsEnabled) == 1) {
		// Only validate the active addon system (AddonFormReader)
		// The TourBookingAddCheckoutAddons system is not active (UI is commented out)
		var paxAddonsValidation = addonFormReader.checkRequiredAddonsSummary();

		if (!paxAddonsValidation) {
			return false;
		}
	}

	var tipSetting = initTourBookingGratuity.getGratuitySetting();

	//validations for tip amounts
	if (cartView == 0 && tipSetting != undefined && tipSetting != null && tipSetting != '' && Object.keys(tipSetting).length > 0) {
		var tipAmount = initTourBookingGratuity.getTipAmount();
		if (tipSetting.is_mandatory && tipSetting.pre_gratuity_enabled && tipAmount <= 0) {
			swal('Error!', 'Tip must required to book the tour.', 'error');
			return false;
		}
	}

	if (cartView == 1 && isComboPackage == 1 && Object.keys(cartPackages).length == 0) {
		swal('Your cart is empty.Please add packages.', '', 'warning');
		return false;
	}

	if (cartView == 1 && Object.keys(cartPackages).length > 15) {
		swal('You can add up to 15 packages in the cart.', '', 'warning');
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

	if ($('#pay-with-cash').length && $('#pay-with-cash')[0].checked) {
		if ($('#dividedBy').find('option:selected').val() != 'Please select option') {
			cashAmount = $('#split_value_field').val();
		}

		var addonPrice = initTourBookingAddCheckoutAddons.getPrice() + addonFormReader.getPrice();
		var tipAmount = initTourBookingGratuity.getTipAmount();
		if (customChargeTotal > 0 || addonPrice > 0 || tipAmount > 0) {
			var paidCashAmount = $('#cash-paid-amount').val();
			if (cashFees == 0) {
				var cashAmt = cashAmount + customChargeTotal + addonPrice + tipAmount;
			} else {
				var cashAmt = cashAmount + tipAmount;
			}
			if (parseFloat(paidCashAmount) < cashAmt) {
				swal('Please add a paid amount greater than the final booking amount.', '', 'warning');
				return false;
			}
		}

		if (cartView == 1 && isComboPackage == 1 && Object.keys(cartPackages).length > 0) {
			cashAmount = (cashFees == 1) ? cartTotal : totalCartTicketCost;
			if ($('#dividedBy').find('option:selected').val() != 'Please select option') {
				cashAmount = $('#split_value_field').val();
			}
		}
	}

	// if payment method is card

	if ($('#pay-with-card').length && $('#pay-with-card')[0].checked) {
		// Check for overbooking before opening payment modal (only when cart view is disabled)
		if (cartView != 1 || (isComboPackage != 1 && Object.keys(cartPackages).length == 0)) {
			validateOverbooking().then(function (canProceed) {
				if (canProceed) {
					// Initialize Stripe when document is ready
					initializeStripe(form);
				} else {
					// User cancelled or overbooking not allowed
					$('#btn-make-payment')[0].disabled = false;
				}
			}).catch(function (error) {
				console.error('Overbooking validation error:', error);
				$('#btn-make-payment')[0].disabled = false;
			});
		} else {
			// Cart view enabled - skip overbooking check and proceed directly
			initializeStripe(form);
		}
		return false;
	}

	if ($('#pay-with-card').length == 0 || ($('#pay-with-card').length && !$('#pay-with-card')[0].checked)) {
		$('#btn-make-payment')[0].disabled = true;

		var selectedPaymentMethod = $('input[name=payment_method]:checked').val();
		if ((cartView == 0 || (cartView == 1 && Object.keys(cartPackages).length == 0 && isComboPackage == 0)) && selectedPaymentMethod === 'Pay Later' && lookupAfterDiscountAndServiceCommission == 0) {
			swal({
				title: 'Confirm $0 Booking',
				text: 'This booking has a 100% discount. It will be recorded as a $0 Cash payment. Do you want to proceed?',
				icon: 'warning',
				buttons: {
					cancel: {
						text: 'No',
						value: false,
						visible: true,
					},
					confirm: {
						text: 'Yes, proceed',
						value: true,
						className: 'btn-success',
					},
				},
			}).then(function (confirmed) {
				if (!confirmed) {
					$('#btn-make-payment')[0].disabled = false;
					return;
				}
				$('input[name=payment_method][value="Cash"]').prop('checked', true);
				submitCreateBookingForm(form);
			});
			return false;
		}

		submitCreateBookingForm(form);
		return false;
	}
	return false;
}
function submitCreateBookingForm(form) {
	var slotTime = $('#slot_time').val();
	if (cartView == 1 && Object.keys(cartPackages).length > 0) {
		postTourBooking(form);
	} else {
		var touristsCount = 0;
		if (isGroupRateEnabled == '1') {
			var selPax = $($('#table-rate-groups').children()[0]).find('.tourists');
			touristsCount += parseInt(selPax.val());
		} else {
			for (var a = 0; a < rateGroups.length; a++) {
				var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
				touristsCount += (rateGroups[a].is_infant_rate != 1) ? parseInt(selPax.val()) : 0;
			}
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
								postTourBooking(form);
							} else {
								$("#btn-make-payment").prop("disabled", false);

								if (isPaymentLink) {
									$("#is_payment_link").val("0");
									$("#create-payment-link").prop("disabled", false);
								}
							}
						});
					}
				} else {
					postTourBooking(form);
				}
			} else {
				postTourBooking(form);
			}
		})
			.fail(function (jqXHR) {
				hideLoading();
				showSubmissionError(jqXHR);
				$('#btn-make-payment').prop("disabled", false);
			});
	}
	return false;
}

function postTourBooking(form) {
	if (cartView == 0) {
	
	}
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: $(form).attr('action'),
		data: $(form).serialize(),
	})
		.done(function (response) {
			if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
				window.location.href = response.nextAction.redirect_to_url.url;
			} else {
				tourBooked(form, response);
			}
		})
		.fail(function (jqXHR) {
			hideLoading();
			showSubmissionError(jqXHR);
			$('#btn-make-payment')[0].disabled = false;

			if (isPaymentLink) {
				$("#is_payment_link").val("0");
				$('.create-payment-link')[0].disabled = false;
			}
		})
		.always(hideLoading);
}


function initConfetti() {
	(function () {
		'use strict';
		var random = Math.random,
			sin = Math.sin,
			confetti = [],
			frame;

		var sizeMin = 3,
			sizeMax = 9,
			dxThetaMin = -0.1,
			dxThetaMax = 0.2,
			dyMin = 0.13,
			dyMax = 0.18,
			dThetaMin = 0.4,
			dThetaMax = 0.3;

		var colors = ['#FFB347', '#00BFA5', '#FF5252', '#9C27B0', '#3F51B5'];

		function Confetto() {
			this.outer = document.createElement('div');
			this.inner = document.createElement('div');
			this.outer.appendChild(this.inner);

			var outerStyle = this.outer.style, innerStyle = this.inner.style;
			outerStyle.position = 'absolute';
			outerStyle.width = (sizeMin + (sizeMax * random())) + 'px';
			outerStyle.height = (sizeMin + (sizeMax * random())) + 'px';
			innerStyle.width = '100%';
			innerStyle.height = '100%';
			innerStyle.backgroundColor = colors[Math.floor(random() * colors.length)];

			outerStyle.left = (window.innerWidth * random()) + 'px';
			outerStyle.top = '-50px';
			this.x = parseFloat(outerStyle.left);
			this.y = -50;
			this.dx = sin(dxThetaMin + dxThetaMax * random());
			this.dy = dyMin + dyMax * random();
			this.theta = 360 * random();
			this.dTheta = dThetaMin + dThetaMax * random();

			this.update = function () {
				this.x += this.dx * 16; // approximate delta
				this.y += this.dy * 16;
				this.theta += this.dTheta * 16;

				outerStyle.left = this.x + 'px';
				outerStyle.top = this.y + 'px';
				outerStyle.transform = 'rotate(' + this.theta + 'deg)';
			};
		}

		var container = document.createElement('div');
		container.style.position = 'fixed';
		container.style.top = '0';
		container.style.left = '0';
		container.style.width = '100%';
		container.style.height = '0';
		container.style.overflow = 'visible';
		container.style.zIndex = '9999';
		document.body.appendChild(container);

		// Spawn confetti continuously
		var confettiInterval = setInterval(() => {
			var c = new Confetto();
			confetti.push(c);
			container.appendChild(c.outer);
		}, 100);

		// Animation loop
		function loop() {
			confetti.forEach(c => c.update());
			frame = requestAnimationFrame(loop);
		}
		frame = requestAnimationFrame(loop);

		// Stop everything after 5 seconds
		setTimeout(() => {
			clearInterval(confettiInterval); // stop spawning

			// Cancel animation
			if (frame) cancelAnimationFrame(frame);

			// Remove all confetti elements
			confetti.forEach(c => container.removeChild(c.outer));
			confetti = [];

			// Remove container
			if (container.parentNode) container.parentNode.removeChild(container);
		}, 10000);
	})();
}

function tourBooked(form, response) {
	var isPayWithCard = ($('#pay-with-card').length && $('#pay-with-card')[0].checked);

	$('#tour_operator, #slot_time').select2({ disabled: true });
	var _tpTrigger = document.getElementById('tourPackageTrigger');
	if (_tpTrigger) { _tpTrigger.disabled = true; _tpTrigger.style.opacity = '0.5'; _tpTrigger.style.cursor = 'not-allowed'; }
	$('#tour_slot_date, .datepicker-btn, #search, #btn-search, #customer_name, #customer_phone_number, #customer_email, #customer_comment, #booked_by_staff, #tour_operator_note, #discount_type_percentage, #discount_percentage, #discount_type_fixed_money, #discount_fixed_money, .tourists, #pay-with-cash').attr('disabled', true);

	$(form)
		.data('booking-id', response.bookingId)
		.data('booking-token', response.bookingToken);

	if (response.errors.length) {
		$('#btn-make-payment')[0].disabled = false;
		var URL = '';
		if (pathname.slice(1, 3) == "ad") {
			URL = path + '/admin/tour-operator/booking/';
		} else if (pathname.slice(1, 3) == "af") {
			URL = path + '/affiliate/booking/';
		} else {
			URL = path + '/tour-operator/booking/';
		}
		const el = document.createElement('div');

		el.innerHTML = response.errors_message + ' Please  <a class="btn btn-success" href=' + URL + response.bookingId + '>Click</a> here and repayment with using Make Payment option';
		if (isPayWithCard && response.errors.indexOf('payment') != -1)
			swal({ showCancelButton: false, showConfirmButton: false, buttons: false, content: el });
		else {
			swal('Unable to send email to the customer', 'Tour has been booked but error occurred when sending booking acknowledgement email to the customer. Page will reload.', 'warning')
				.then(function () {
					location = location; // location.reload() keeps data in form which isn't desirable
				});
		}
	} else {
		initConfetti();
		if (isPayWithCard) {
			swal({
				title: 'Tour booked and payment verified',
				text: 'Page will reload',
				icon: 'success'
			})
				.then(function () {
					if (response.isAffiliate == 'No') {
						bookingUrl = bookingUrl.replace('_ID_', response.bookingId);
						if (response.splitCount != 0 && response.splitCount != null) {
							bookingUrl = bookingUrl + "?split=" + response.splitCount;
							window.location.replace(bookingUrl);
						} else {
							window.location.replace(bookingUrl);
						}
					} else {
						location = location;
					}
				});
		} else {
			if ($('#pay-with-cash').length && $('#pay-with-cash')[0].checked) {
				if ($('#dividedBy').find('option:selected').val() != 'Please select option') {
					cashAmount = $('#split_value_field').val();
				}

				var paidCashAmount = $('#cash-paid-amount').val();
				if (cashAmount > 0 && paidCashAmount > 0) {
					if ($('#dividedBy').find('option:selected').val() != 'Please select option') {
						var changeAmount = paidCashAmount - cashAmount;
					} else {
						if (cashFees == 0) {
							var addonPrice = initTourBookingAddCheckoutAddons.getPrice() + addonFormReader.getPrice();
							var tipAmount = initTourBookingGratuity.getTipAmount();
							cashAmount = cashAmount + customChargeTotal + addonPrice + tipAmount;
						}
						var changeAmount = paidCashAmount - cashAmount.toFixed(2);
					}

					if (changeAmount != 0 && changeAmount != '') {

						var elemdiv = document.createElement("div");
						elemdiv.classList.add("swal-content");
						let message = ''; // dynamic section (Change / Unpaid)
						let amountColor = '#000'; // default color

						if (changeAmount > 0) {
							message = `Change<br>${currency(changeAmount).format()}`;
							amountColor = '#15803D';
						} else if (changeAmount < 0) {
							changeAmount = Math.abs(changeAmount);
							message = `Unpaid<br>${currency(changeAmount).format()}`;
							amountColor = '#f14f2f';
						}

						elemdiv.innerHTML = `
							<div style="
								font-size: 26px;
								font-weight: 600;
								color: ${amountColor};
							">
								${message}
							</div>
							<div class="swal-title">
								Tour package booked.
							</div>
						`;
						// elemdiv.classList.add("swal-title");

						swal({
							icon: "success",
							content: elemdiv
						})
							.then(function () {
								if (response.isAffiliate == 'No') {
									bookingUrl = bookingUrl.replace('_ID_', response.bookingId);
									if (response.splitCount != 0 && response.splitCount != null) {
										bookingUrl = bookingUrl + "?split=" + response.splitCount;
										window.location.replace(bookingUrl);
									} else {
										window.location.replace(bookingUrl);
									}
								} else {
									location = location;
								}
							});
					} else {
						swal('Tour package booked', 'Page will reload', 'success')
							.then(function () {
								if (response.isAffiliate == 'No') {
									bookingUrl = bookingUrl.replace('_ID_', response.bookingId);
									if (response.splitCount != 0 && response.splitCount != null) {
										bookingUrl = bookingUrl + "?split=" + response.splitCount;
										window.location.replace(bookingUrl);
									} else {
										window.location.replace(bookingUrl);
									}
								} else {
									location = location;
								}
							});
					}
				} else {
					swal('Tour package booked', 'Page will reload', 'success')
						.then(function () {
							if (response.isAffiliate == 'No') {
								bookingUrl = bookingUrl.replace('_ID_', response.bookingId);
								if (response.splitCount != 0 && response.splitCount != null) {
									bookingUrl = bookingUrl + "?split=" + response.splitCount;
									window.location.replace(bookingUrl);
								} else {
									window.location.replace(bookingUrl);
								}
							} else {
								location = location;
							}
						});
				}
			} else {
				swal('Tour package booked', 'Page will reload', 'success')
					.then(function () {
						if (response.isAffiliate == 'No') {
							bookingUrl = bookingUrl.replace('_ID_', response.bookingId);
							if (response.splitCount != 0 && response.splitCount != null) {
								bookingUrl = bookingUrl + "?split=" + response.splitCount;
								window.location.replace(bookingUrl);
							} else {
								window.location.replace(bookingUrl);
							}

						} else {
							location = location;
						}
					});
			}
		}
	}
}

function payWithCard(form, response) {
	var payInt = result.paymentIntent
	payInt.booking_id = $(form).data('booking-id');
	payInt.booking_token = $(form).data('booking-token');
	payInt.transaction_id = response.transactionId;

	$.post(paymentAcknowledgementUrl, payInt)
		.then(function (response) {
			swal({
				title: 'Tour booked and payment verified',
				html: 'Page will reload',
				icon: 'success'
			})
				.then(function () {
					location = location;
				});
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

/**
 * Update the table of rate groups with the number of tourists selected.
 * Applies state taxes, service commission, and any additional taxes/fees.
 * Updates the display of the total cost of the package.
 * @return {undefined}
 */
function permitFeeTable() {
	if (isGroupRateEnabled == '1') {
		updateGroupRatesTable();
	} else {
		var paymentMethod = $('.checkset:checked').val();
		var cashPayment = (paymentMethod == 'Cash') ? 1 : 0;
		var tourPackageId = $('#tour_package').val();
		$('.discount-applied').val(0);
		$('.sub-total, .fees-total, .grand-total').text(currencyFormation.symbol + '0.00');
		if ($('#slot_time').val() == 0) {
			$('#table-rate-groups .tourists').html('<option>0</option>');
			return;
		}
		var $tblRateGrps = $('#table-rate-groups');
		var total = 0;
		var lookup = 0;
		var cashLookup = 0;
		var cashTotal = 0;
		serviceCommissionTotal = 0;
		serviceCommissionPackage = 0;
		for (var a = 0; a < rateGroups.length; a++) {
			$row = $($tblRateGrps.children()[a]);
			var $selTourists = $row.find('.tourists');
			var tourists = parseInt($selTourists.val());
			var oldTourists = rateGroups[a].cart_tourists;
			var $selpermitfee = $row.find('.permit-fee');
			if (isNaN(parseInt($selpermitfee.val()))) {
				$selpermitfee = $row.find('.permit-fee1');
			}
			var permitfee = parseInt($selpermitfee.val());
			var groupTotal = 0;
			var groupCashTotal = 0;
			var lookTotal = 0;
			var lookCashTotal = 0;
			var stateTax = 0;
			var partnerFee = 0;

			// rate may be zero for one or more age/rate group and
			// if rate is non-zero then only add other amounts
			for (var b = 0; b < taxes.length; b++) {
				stateTax += tourists * (taxes[b].value_type == 'Percent'
					? rateGroups[a].rate * taxes[b].value / 100
					: taxes[b].value
				);
			}

			if (isPartnerPackage) {
				partnerFee = rateGroups[a].partner_fee_amount;
			}

			groupTotal = tourists * (
				parseFloat(rateGroups[a].rate)
				+ parseFloat(rateGroups[a].additional_charge)
				+ parseFloat(permitfee)
				+ parseFloat(partnerFee)
			);
			if (cashPriceEnabled == 1) {
				if (rateGroups[a].is_cash_rate == 1) {
					groupCashTotal = tourists * (
						parseFloat(rateGroups[a].cash_rate)
						+ parseFloat(rateGroups[a].cash_tax)
						+ parseFloat(permitfee)
						+ parseFloat(partnerFee)
					);
				} else {
					groupCashTotal = groupTotal;
				}
			}

			if (tourists) {
				// See the identical fix/comment in updateRatesTable() — serviceCommissionTotal
				// must be computed unconditionally so it never goes stale after a discount is
				// applied then cleared.
				var serviceCommissionPax = roundout(((parseFloat(rateGroups[a].rate) + parseFloat(rateGroups[a].additional_charge) + parseFloat(permitfee) + parseFloat(partnerFee)) * serviceCommissionPercentage / 100), 2);
				serviceCommissionPax = tourists * serviceCommissionPax;
				serviceCommissionPackage += serviceCommissionPax;
				serviceCommissionTotal += serviceCommissionPax;
				var processingCharge = Number(rateGroups[a].processing_charge_percentage);
				if (rateGroups[a].additional_tax_percentage != 0) {
					var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
					lookTotal += roundout((groupTotal * Number(rateGroups[a].additional_tax_percentage) / 100), 2);
					groupTotal += roundout((groupTotal * ad_tax_pe_n_processing_fee / 100), 2);
					if (cashPriceEnabled == 1) {
						lookCashTotal += roundout((groupCashTotal * Number(rateGroups[a].additional_tax_percentage) / 100), 2);
						groupCashTotal += roundout((groupCashTotal * ad_tax_pe_n_processing_fee / 100), 2);
					}
				} else {
					lookTotal += groupTotal;
					if (cashPriceEnabled == 1) {
						lookCashTotal += groupCashTotal;
					}
					if (processingCharge) {
						groupTotal += roundout((groupTotal * processingCharge / 100), 2);
					}
					if (processingCharge && cashPriceEnabled == 1) {
						groupCashTotal += roundout((groupCashTotal * processingCharge / 100), 2);
					}
				}
			}

			if (groupTotal) {
				if (paymentMethod === 'Card') {
					$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
				} else if (cashPayment == 1 && cashPriceEnabled == 1) {
					if (rateGroups[a].is_cash_rate == 1) {
						$row.find('.final').text(currencyFormation.symbol + groupCashTotal.toFixed(2));
					} else {
						$row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
					}
				} else {
					$row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
				}
			} else {
				$row.find('.final').text(currencyFormation.symbol + '0.00');
			}

			total += groupTotal;

			lookup += lookTotal;
			cashLookup += lookCashTotal;
			cashTotal += groupCashTotal;
			if ($selTourists.children().length > 1) {
				var rateGroupId = $row.find('.hid-rate-group').val();

				for (var b = 0; b < touristsGroups.length; b++) {
					if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
						touristsGroups[b].tourists = tourists;
						touristsGroups[b].selectedtourists = oldTourists;
						break;
					}
				}

				if (b >= touristsGroups.length) { // rate group isn't in the array, so add it
					touristsGroups.push({
						rate_for: $row.find('.rate-for').text(),
						tour_package_rate_id: rateGroupId,
						tourists: tourists,
						selectedtourists: oldTourists
					});
				}
			}
		}

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
		}

		lookupForCashPaymentMethodPackage = cashLookup;

		if (cartView == 1) {
			//Cart packages(Which are already in cart) total added in new package selected
			if (tourPackageId != '' && $('#cart-package-' + tourPackageId).length == 0) {
				//If package not in cart
				total += totalCartTicketCost;
				lookup += totalCartTicketCost;
				cashLookup += totalCartCashTicketCost;
				serviceCommissionTotal += totalCartServiceCommission;
			} else {
				//If package is already in cart
				var cartPackageDetails = getCartPackageDetails(tourPackageId);

				if (Object.keys(cartPackageDetails).length > 0) {
					//If Pax increased using edit cart
					if (total > cartPackageDetails.subtt) {
						lookup = total = total - cartPackageDetails.subtt;
						total += totalCartTicketCost;
						lookup += totalCartTicketCost;
						cashLookup = cashLookup - parseFloat(cartPackageDetails.cashSubtt || 0);
						cashLookup += totalCartCashTicketCost;
						if (serviceCommissionTotal > cartPackageDetails.fee) {
							serviceCommissionTotal = serviceCommissionTotal - cartPackageDetails.fee;
							serviceCommissionTotal += totalCartServiceCommission;
						}
					} else {
						total = totalCartTicketCost;
						lookup = totalCartTicketCost;
						cashLookup = totalCartCashTicketCost;
						serviceCommissionTotal = totalCartServiceCommission;
					}
				} else {
					total = totalCartTicketCost;
					lookup = totalCartTicketCost;
					cashLookup = totalCartCashTicketCost;
					serviceCommissionTotal = totalCartServiceCommission;
				}
			}
		}

		totalBeforeDiscountAndServiceCommission = total;
		lookupForCashAndPayLaterPaymentMethod = lookup;
		lookupForCashPaymentMethod = cashLookup;

		if (isNaN(total)) {
			$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + '0.00');
		} else {
			if (paymentMethod === 'Card') {
				$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + total.toFixed(2));
			} else if (cashPayment == 1 && cashPriceEnabled == 1) {
				$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + cashLookup.toFixed(2));
			} else {
				$('.total-before-service-commission-and-discount, .sub-total').text(currencyFormation.symbol + lookup.toFixed(2));
			}
		}

		applyDiscount();
	}
}

$(".checkset").click(function () {
	if ($(this).val() == "Card") {
		for (var a = 0; a < rateGroups.length; a++) {
			$('#table-rate-groups tr').find('td.processing-fee-' + a).text(rateGroups[a].processing_charge_percentage + '%');
		}
	}

	if ($(this).val() == "Cash" || $(this).val() == "Pay Later") {
		for (var a = 0; a < rateGroups.length; a++) {
			$('#table-rate-groups tr').find('td.processing-fee-' + a).text('0.00%');
		}
	}

	updateRatesTable($(this).val())
});

function roundout(amount, places = 2) {
	if (places < 0) places = 0;
	let factor = Math.pow(10, places);
	return Math.round(amount * factor) / factor;
}

function getDiscountStatus(groupTotal) {
	var discountTotal = 0;

	if ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked) {

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

function getCartPackageDetails(tourPackageId) {
	var cartPackageInfo = [];
	for (var a = 0; a < cartPackages.length; a++) {
		if (cartPackages[a].tour_package_id == tourPackageId) {
			cartPackageInfo = cartPackages[a];
		}
	}

	return cartPackageInfo;
}

function removeCartPackageDetails(tourPackageId) {
	for (var a = 0; a < cartPackages.length; a++) {
		if (cartPackages[a].tour_package_id == tourPackageId) {
			cartPackages.splice(a, 1);
		}
	}

	touristsGroups = [];
}

function updateCustomChargeFees(serviceCommission) {
	var customFees = parseFloat($(".package-custom-fees").val());
	if (!isNaN(customFees)) {
		var customChargeFee = 0;
		$(".package-custom-fees").each(function () {
			customChargeFee += +$(this).val();
		});
		serviceCommission = roundout(serviceCommission - customChargeFee, 2);
	}

	var addonFees = parseFloat($(".package-addon-fees").val());
	if (!isNaN(addonFees)) {
		var addonFee = 0;
		$(".package-addon-fees").each(function () {
			addonFee += +$(this).val();
		});
		serviceCommission = roundout(serviceCommission - addonFee, 2);
	}

	return serviceCommission;
}

function hideShowSplitInputUI() {
	let formAddonsPrice = initTourBookingAddCheckoutAddons.getPrice() + addonFormReader.getPrice();
	let tipAmount = initTourBookingGratuity.getTipAmountTotal();
	var paymentMethod = $('.checkset:checked').val();

	// Check if split payment should be hidden
	var shouldHide = false;

	// Hide if: custom charges, addons, partner package, multiple packages in cart, or Pay Later payment method
	if (customChargeTotal > 0 || formAddonsPrice > 0 || tipAmount > 0 || isPartnerPackage == 1 || paymentMethod === 'Pay Later' || paymentMethod === 'Link') {
		shouldHide = true;
	}

	// Also hide if multiple packages in cart
	if (cartView == 1 && typeof cartPackages !== 'undefined' && cartPackages.length > 1) {
		shouldHide = true;
	}

	// Also hide if single partner package in cart
	if (cartView == 1 && typeof cartPackages !== 'undefined' && cartPackages.length > 0 && cartPackages[0].is_partner_package == 1) {
		shouldHide = true;
	}

	if (shouldHide) {
		$("#dividedBy").prop("selectedIndex", 0);
		$("#split-payment").prop('checked', false);
		$(".split-check").text(currencyFormation.symbol + '0.00');
		$("#split_value_field").val("").prop('required', false).hide();
		$("#split-payment-div").hide();

		// Split payment was showing the "Total payment" row (#psplit) with the now-stale
		// per-split amount — swap back to the full-total row for the active payment method,
		// same as PaymentHandler.resetSplitValues() does when split is reset elsewhere.
		$(".split-set").text(currencyFormation.symbol + '0.00');
		$("#psplit").hide();
		if (paymentMethod === 'Card' || (paymentMethod === 'Cash' && cashFees == 1)) {
			$("#pcard").show();
		} else if (paymentMethod === 'Cash' || paymentMethod === 'Pay Later') {
			$("#pcash").show();
		}
	} else {
		// Only show if payment method is Card or Cash (not Pay Later or Link)
		if (paymentMethod !== 'Pay Later' && paymentMethod !== 'Link') {
			$("#split-payment-div").show();
		}
	}
}
function managerRequest(requestType) {
	bookingRequestNewBooking.manualOverriderManagerRequest(requestType);
}

async function validateOverbooking() {
	return new Promise((resolve, reject) => {
		var slotTime = $('#slot_time').val();
		var touristsCount = 0;

		if (isGroupRateEnabled == '1') {
			var selPax = $($('#table-rate-groups').children()[0]).find('.tourists');
			touristsCount += parseInt(selPax.val());
		} else {
			for (var a = 0; a < rateGroups.length; a++) {
				var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
				touristsCount += (rateGroups[a].is_infant_rate != 1) ? parseInt(selPax.val()) : 0;
			}
		}

		$.post(checkOverbooking, { 'slot_time': slotTime })
			.then(function (response) {
				if (response) {
					var overbooked = (parseInt(response.booked_seats) + parseInt(touristsCount)) - parseInt(response.seats);

					if (overbooked > 0) {
						if (userType == 'Affiliate') {
							swal('Sorry!', 'You are not allowed to overbook', 'warning');
							resolve(false); // Reject overbooking for Affiliates
						} else {
							hideLoading();
							var addText = overbooked > 0 ? ` This slot will be overbooked by ${overbooked} seats.` : " This slot will be full.";
							addText += " Do you want to proceed?";

							swal({
								title: 'An overbooking event',
								text: addText,
								icon: "warning",
								dangerMode: true,
								buttons: {
									no: { text: 'Cancel', value: false },
									yes: { text: 'Book', value: true, className: 'btn-danger' }
								}
							}).then(function (result) {
								resolve(result); // Resolve with true (yes) or false (no)
							});
						}
						hideLoading();
					} else {
						resolve(true); // No overbooking, proceed
					}
				} else {
					resolve(true); // Proceed if no response
				}
			})
			.fail(function (jqXHR) {
				hideLoading();
				showSubmissionError(jqXHR);
				reject(false); // Reject in case of AJAX error
				$('#btn-make-payment').prop("disabled", false);
			});
	});
}

let clientSecret = null; // Store clientSecret globally

async function initializeStripe(form) {
	// Show the loader
	showLoading();

	try {
		$('#payment-element').empty();
		// $('#link-authentication-element').empty();
		$('#saved-cards-container').empty().hide();

		// Get CSRF token from meta tag
		const csrfToken = $('meta[name="csrf-token"]').attr('content');
		const formData = new FormData(form);
		// get subtt value
		let subtt = formData.get('subtt'); // e.g. "0.00" or "$0.00"

		// normalize to number
		let numericSubtt = parseFloat(
			(subtt || '').replace(/[^0-9.-]+/g, '')
		) || 0;

		if (numericSubtt === 0) {
			swal({
				icon: 'warning',
				title: 'Action Required',
				text: 'This booking has no payable amount. Please use Cash to proceed.',
				confirmButtonText: 'OK'
			});
			return;
		}
		// Append save_card preference
		formData.append('save_card', $('#save-card-checkbox').is(':checked') ? '1' : '0');

		const intentResponse = await fetch(createPaymentIntentUrl, {
			method: 'POST',
			headers: {
				'X-CSRF-TOKEN': csrfToken
			},
			body: formData,
		});

		const responseData = await intentResponse.json();

		if (!intentResponse.ok) {
			throw new Error(responseData.message || 'Unknown backend error');
		} else if (responseData.error) {
			throw new Error(responseData.error);
		}

		// Store clientSecret globally
		clientSecret = responseData.clientSecret;

		// Store savedCards from backend
		const savedCards = responseData.savedCards || [];

		const appearance = {
			theme: 'stripe',
		};

		const loader = 'auto';

		const options = {
			layout: {
				type: 'auto',
			},
			paymentMethodOrder: ['card', 'apple_pay', 'google_pay'],
			wallets: {
				applePay: 'auto',
				googlePay: 'auto'
			},
			defaultValues: {
				billingDetails: {
					email: $('#customer_email').val(),
					address: {
						country: countryCode,
					},
				},
			},
		};

		elements = stripe.elements({ clientSecret, appearance, loader });

		// Create and mount Link Authentication Element
		// const linkAuthenticationElement = elements.create('linkAuthentication');

		// Create and mount Payment Element
		const paymentElement = elements.create('payment', options);

		// Wait until elements are mounted before hiding the loader
		await new Promise((resolve, reject) => {
			paymentElement.mount('#payment-element');
			paymentElement.on('ready', resolve);

			// linkAuthenticationElement.mount('#link-authentication-element');
			// linkAuthenticationElement.on('ready', resolve);

			setTimeout(() => reject(new Error('Element mounting timed out')), 30000);
		});

		// Populate saved cards if available
		if (savedCards && savedCards.length > 0 && !disableAffiliateCardSave && !disableCardSave) {
			const savedCardsContainer = $('#saved-cards-container');
			savedCardsContainer.empty();
			savedCardsContainer.append('<h5 class="text-base font-semibold text-[#111827] mb-3">Saved Cards:</h5>');
			var savedLastCardChecked = false;
			if (savedCards.length >= 1) {
				savedLastCardChecked = true;
			}
			savedCards.forEach(card => {
				// Validate card.id
				if (!card.id || !card.id.startsWith('pm_')) {
					console.error('Invalid PaymentMethod ID in savedCards:', card);
					return; // Skip invalid card
				}
				savedCardsContainer.append(`
                    <div class="form-check flex items-center mb-2 save-card-list">
                        <input class="form-check-input w-4 h-4 mr-2" type="radio" name="saved_payment_method" id="card-${card.id}" value="${card.id}" ${savedLastCardChecked ? 'checked' : ''}>
                        <label class="form-check-label form-custom-label mr-2 !mb-0" for="card-${card.id}">
                            ${card.brand} ending in ${card.last4} (Expires: ${card.exp_month}/${card.exp_year})
                            <span class="checkmark"></span>
                        </label>
                        <i class="fas fa-trash text-danger delete-card text-[#A32624] text-xs" data-payment-method-id="${card.id}" style="cursor: pointer;" title="Delete this card" aria-label="Delete saved card"></i>
                    </div>
                `);
			});
			// Option to use a new card
			savedCardsContainer.append(`
                <div class="form-check flex items-center save-card-list">
                    <input class="form-check-input w-4 h-4 mr-2" type="radio" name="saved_payment_method" id="new-card" value="new" ${savedLastCardChecked ? '' : 'checked'}>
                    <label class="form-check-label form-custom-label !mb-0" for="new-card">
                        Use a new card
                        <span class="checkmark"></span>
                    </label>
                </div>
            `);
			savedCardsContainer.show();

			// Toggle payment element and save card checkbox visibility
			const toggleSaveCardCheckbox = function () {
				const selectedValue = $('input[name="saved_payment_method"]:checked').val();
				if (selectedValue === 'new') {
					$('#save-card-checkbox').prop('checked', false).closest('.form-check').show();
				} else {
					$('#save-card-checkbox').prop('checked', false).closest('.form-check').hide();
				}
			};

			// Initial toggle
			toggleSaveCardCheckbox();
			if (savedLastCardChecked) {
				$('#payment-element').hide();
			}
			// Toggle on change
			$('input[name="saved_payment_method"]').on('change', function () {
				if ($(this).val() === 'new') {
					$('#payment-element').show();
				} else {
					$('#payment-element').hide();
				}
				toggleSaveCardCheckbox();
			});

			// Handle delete card click with confirmation
			$('.delete-card').on('click', async function () {
				const paymentMethodId = $(this).data('payment-method-id');

				// Show confirmation dialog
				const confirmDelete = await swal({
					title: 'Are you sure?',
					text: 'This will permanently delete the saved card.',
					icon: 'warning',
					buttons: ['Cancel', 'Delete'],
					dangerMode: true,
				});

				if (!confirmDelete) {
					return;
				}

				// Validate deletePaymentMethodUrl
				if (!deletePaymentMethodUrl) {
					throw new Error('deletePaymentMethodUrl is not defined');
				}

				$(this).prop('disabled', true); // Disable icon
				showLoading();
				try {
					const response = await fetch(deletePaymentMethodUrl, {
						method: 'POST',
						headers: {
							'X-CSRF-TOKEN': csrfToken,
							'Content-Type': 'application/json',
						},
						body: JSON.stringify({ payment_method_id: paymentMethodId }),
					});

					const result = await response.json();

					if (!response.ok) {
						throw new Error(result.message || 'Unknown backend error');
					}

					// Show success message
					swal('Success', 'Payment method deleted successfully', 'success');

					// Refresh saved cards by re-calling initializeStripe
					await initializeStripe(form);
				} catch (error) {
					console.error('Error deleting PaymentMethod:', error);
					hideLoading();
					swal('Error', error.message || 'Failed to delete payment method', 'error');
				} finally {
					$(this).prop('disabled', false); // Re-enable icon
					hideLoading();
				}
			});
		} else {
			// No saved cards, ensure checkbox is visible and unchecked
			$('#save-card-checkbox').prop('checked', false).closest('.form-check').show();
			$('#payment-element').show(); // Ensure payment element is visible
		}

		// Global setting to hide the card save option for affiliates
		if (disableAffiliateCardSave || disableCardSave) {
			$('#save-card-checkbox').prop('checked', false).closest('.form-check').hide();
		}

		// Show the modal only on success
		$('#make-a-payment').modal('show');
	} catch (error) {
		console.error('Error initializing Stripe elements:', error);
		hideLoading();
		swal('Error occurred', error.message || 'Failed to initialize payment elements', 'error');
	} finally {
		hideLoading();
	}
}

async function handleMakePayment() {
	showLoading();

	try {
		var form = $('#payment-form');

		if (cartView != 1) {
			const isOverbookingAllowed = await validateOverbooking();
			if (isOverbookingAllowed == false) {
				hideLoading();
				return isOverbookingAllowed;
			}
		}

		const selectedPaymentMethod = $('input[name="saved_payment_method"]:checked').val();

		let paymentConfirmation;

		// Base confirmParams without payment_method_data
		const confirmParams = {
			return_url: 'https://google.com',
			save_payment_method: $('#save-card-checkbox').is(':checked')
		};

		if (selectedPaymentMethod && selectedPaymentMethod !== 'new') {
			// Validate PaymentMethod ID
			if (!selectedPaymentMethod.startsWith('pm_')) {
				throw new Error(`Invalid PaymentMethod ID: ${selectedPaymentMethod}. Expected a valid Stripe PaymentMethod ID (e.g., pm_xxx).`);
			}
			// Use saved payment method
			if (!clientSecret) {
				throw new Error('Client secret is missing');
			}
			paymentConfirmation = await stripe.confirmPayment({
				clientSecret,
				confirmParams: {
					...confirmParams,
					payment_method: selectedPaymentMethod
				},
				redirect: 'if_required'
			});
		} else {
			// Use Payment Element for new card
			paymentConfirmation = await stripe.confirmPayment({
				elements,
				confirmParams: {
					...confirmParams,
					payment_method_data: {
						billing_details: {
							name: $('[name="customer_name"]').val(),
							email: $('[name="customer_email"]').val(),
							phone: $('[name="customer_phone_number"]').val(),
						}
					}
				},
				redirect: 'if_required'
			});
		}

		const { paymentIntent, error } = paymentConfirmation;

		if (error) {
			swal('Error occurred', error.message, 'error');
			hideLoading();
		} else {
			// Append the hidden input field if it doesn't already exist
			if ($(form).find('input[name="payment_intent_id"]').length === 0) {
				$(form).append(`<input type="hidden" name="payment_intent_id" value="${paymentIntent.id}">`);
			} else {
				$(form).find('input[name="payment_intent_id"]').val(paymentIntent.id);
			}
			if ($(form).find('input[name="feature"]').length === 0) {
				$(form).append(`<input type="hidden" name="feature" value="create_booking">`);
			} else {
				$(form).find('input[name="feature"]').val('create_booking');
			}
			postTourBooking(form);
		}
	} catch (apiError) {
		console.error('API Error:', apiError);
		swal('Error occurred', apiError.message || apiError, 'error');
		hideLoading();
	}
}
