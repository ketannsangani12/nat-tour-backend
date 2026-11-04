var modalTitle = 'Booking Information';

var checkInStatuses = {
	N: 'Not Checked In',
	1: '1st Check In',
	2: '2nd Check In',
	A: 'Absent'
};

$(function () {
	$(document).on('shown.bs.modal', function (event) {
		if ($(event.target).attr('id') == 'modal-reschedule') {
			if (parseInt(packageHasSlots)) {
				$(".package-has-slots-reschedule").css('display', 'block');
			} else {
				$(".package-has-slots-reschedule").css('display', 'none');
			}

			$('#tour_slot_date').datepicker({
				dateFormat: 'DD, MM d, yy',
				minDate: 0,
				onSelect: function () {
					getSlotTimes();
				}
			});

			getSlotTimes();
		}

		if ($(event.target).attr('id') == 'modal-switch-package') {
			if (packageHasSlots) {
				$(".package-has-slots").css('display', 'block');
			} else {
				$(".package-has-slots").css('display', 'none');
			}

			$('#tour_slot_date_switch').datepicker({
				dateFormat: 'DD, MM d, yy',
				minDate: 0,
				onSelect: function () {
					getSlotTimesForPackage();
				}
			});
		}
	});
});

function getSlotTimes() {
	showLoading();
	var $selSlotTime = $('#tour_slot_time');
	$selSlotTime.html('<option value="">Select Time Slot</option>');

	var date = $('#tour_slot_date').val();
	const date1 = new Date(date);
	var month = date1.getMonth() + 1;
	var day = date1.getDate();


	month = (month < 10 ? '0' : '') + month;
	day = (day < 10 ? '0' : '') + day;

	params = {
		date: date1.getFullYear() + month + day,
		tourPackageId: $('#tour_package_id').val(),
		getRateGroups: 0
	};
	$.post(getTourSlotTimesByDateUrl, params)
		.done(function (data) {
			var slotTimes = data.slotTimes;
			for (var i = 0; i < slotTimes.length; i++) {
				if (userType == 'Affiliate') {
					var opt = new Option(
						formatTime(slotTimes[i].time_from) +
						(slotTimes[i].time_to == null ? ' onwards' : ' - ' + formatTime(slotTimes[i].time_to)) +
						' (Available: ' + (slotTimes[i].available_tourists) + ')',
						slotTimes[i].id, false, false
					);
				} else {
					var opt = new Option(
						formatTime(slotTimes[i].time_from) +
						(slotTimes[i].time_to == null ? ' onwards' : ' - ' + formatTime(slotTimes[i].time_to)) +
						' (Available: ' + (slotTimes[i].seats - slotTimes[i].tourists) + ')',
						slotTimes[i].id, false, false
					);
				}

				$(opt).data('empty', slotTimes[i].seats - slotTimes[i].tourists);
				$selSlotTime.append(opt);

				if (!parseInt(packageHasSlots)) {
					if (i == 0) {
						$(opt).attr('selected', true);
					}

					if (slotTimes[i].id != tourSlotId) {
						if (expiryDate != "") {
							$("#tour_slot_time").trigger("change");
						}
					}
				}
			}
		})
		.fail(function () {
			swal('Error occurred', 'Unable to get time slots of the selected date from server.', 'error');
		})
		.always(hideLoading);
}

function verifyReschedule(form) {
	var selSlotTime = $('#tour_slot_time')[0];

	if (selSlotTime.selectedIndex == 0) {
		swal('Please select a time slot.', '', 'warning');
		return false;
	}

	var optSlotTime = selSlotTime.options[selSlotTime.selectedIndex];

	if (parseInt($('#booking_tourists').val()) > parseInt(optSlotTime.getAttribute('empty'))) {
		swal('Not enough empty seats available in the selected time slot.', '', 'warning');
		return false;
	}

	return submitForm(form);
}

function rescheduled(form) {
	swal('Booking has been rescheduled', 'Page will reload', 'success')
		.then(function () {
			location = location; // location.reload() keeps data in form which isn't desirable
		});
}

function confirmAndSubmitForm(form) {
	showLoading();
	form.submit();
}

function noteBookedByUpdated(form, response) {
	if (typeof response.updated == 'undefined')
		swal('There was nothing to update.', 'Warning', 'warning')
	else {
		swal("Details updated", "Page will reload", "success")
			.then(function () {
				location = location; // location.reload() keeps data in form which isn't desirable
			});
	}
}

function contactInfoByUpdated(form, response) {
	var customerName = $('#modal-customer #name').val();
	var phoneNumber = $('#modal-customer #phone_number').val();
	var email = $('#modal-customer #email').val();

	if ($('#customerNum').length == 0 && phoneNumber != '') {
		$('#custNameHeading').after('<a href="tel:' + phoneNumber + '" class="contactnum" id="customerNum">' + phoneNumber + '</a>');
	}
	if (phoneNumber == '') {
		$('#customerNum').remove();
	} else {
		$('#customerNum').text(phoneNumber);
	}
	$('#customerName').text(customerName);

	$('#customerEmail').text(email);
	swal("Details updated", "Customer's details updated", "success")
		.then(function () {
			$('#modal-customer').modal('hide');
		});
}

function resendBooking(form, response) {
	swal("Resend Booking details", "", "success")
		.then(function () {
			$('#modal-resend-booking').modal('hide');
		});
}

function showCommentsInHistory(form, data) {
	var profilePhotoUrl = (currentUserProfilePhoto != '') ? currentUserProfilePhoto : noPhotoUrl;
	var dateTime = moment(data.operation_at).format('MMM D, YYYY h:mm A');

	var html =
		'<div class="log-entry" data-type="note">' +
			'<div class="log-time">' + dateTime +
				'<div class="log-user">' +
					'<img class="log-avatar" src="' + profilePhotoUrl + '" alt="">' +
					'<div class="log-user-info">' +
						'<span class="log-user-name">' + encLT(currentUserName) + '</span>' +
						(currentUserRole ? '<span class="log-cred">' + encLT(currentUserRole) + '</span>' : '') +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="log-badge note" style="color:#1d6fa8;">Comments</div>' +
			'<div class="log-content">' +
				'<div class="note-box" style="background:#eef6ff;border-color:#93c5fd;">' +
					'<div class="note-by" style="color:#1d6fa8;">Comment from the customer added / updated</div>' +
					encLT($('#comments').val()) +
				'</div>' +
			'</div>' +
		'</div>';

	$('#historyLog').prepend(html);
	swal('Comment saved.', '', 'success');
}

function showErrorsInHistory(data) {
	var profilePhotoUrl = (currentUserProfilePhoto != '') ? currentUserProfilePhoto : noPhotoUrl;
	var dateTime = moment(data.operation_at).format('MMM D, YYYY h:mm A');

	var html =
		'<div class="log-entry" data-type="system">' +
			'<div class="log-time">' + dateTime +
				'<div class="log-user">' +
					'<img class="log-avatar" src="' + profilePhotoUrl + '" alt="">' +
					'<div class="log-user-info">' +
						'<span class="log-user-name">' + encLT(currentUserName) + '</span>' +
						(currentUserRole ? '<span class="log-cred">' + encLT(currentUserRole) + '</span>' : '') +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="log-badge system" style="color:#b91c1c;">Card Error</div>' +
			'<div class="log-content">' +
				'<div class="note-box" style="background:#fff5f5;border-color:#fca5a5;">' +
					'<div class="note-by" style="color:#b91c1c;">Booking error</div>' +
					'<b>' + encLT(data.errors) + '</b>' +
				'</div>' +
			'</div>' +
		'</div>';

	$('#historyLog').prepend(html);
}

function showReferencesInHistory(form, data) {
	var profilePhotoUrl = (currentUserProfilePhoto != '') ? currentUserProfilePhoto : noPhotoUrl;
	var refNumber = encLT($('#reference_booking').val());
	var dateTime = moment(data.operation_at).format('MMM D, YYYY h:mm A');

	var bookmarkSvg = '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3h14a2 2 0 012 2v16l-7-3.5L7 21V5a2 2 0 012-2z"/></svg>';

	var html =
		'<div class="log-entry" data-type="system">' +
			'<div class="log-time">' + dateTime +
				'<div class="log-user">' +
					'<img class="log-avatar" src="' + profilePhotoUrl + '" alt="">' +
					'<div class="log-user-info">' +
						'<span class="log-user-name">' + encLT(currentUserName) + '</span>' +
						(currentUserRole ? '<span class="log-cred">' + encLT(currentUserRole) + '</span>' : '') +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="log-badge system" style="color:#0d7a4e;">Reference Booking</div>' +
			'<div class="log-content">' +
				'<div class="note-box" style="background:#edfaf3;border-color:#6ee7b7;">' +
					'<div class="note-by" style="color:#0d7a4e;">Reference booking number added / updated</div>' +
					'<b>' + refNumber + '</b>' +
				'</div>' +
			'</div>' +
		'</div>';

	$('#historyLog').prepend(html);
	swal('Reference booking number saved.', '', 'success');
}

function addCheckinInHistory(dateTime, statusCode, statusLabel, whoCheckedIn) {
	var ciClassMap = { 'N': 'ci-not-checked', '1': 'ci-1st', '2': 'ci-2nd', 'A': 'ci-absent' };
	var ciClass = ciClassMap[statusCode] || 'ci-not-checked';

	var shieldSvg = '<svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>';
	var profilePhotoUrl = (currentUserProfilePhoto != '') ? currentUserProfilePhoto : noPhotoUrl;
	var html =
		'<div class="log-entry" data-type="system">' +
			'<div class="log-time">' + dateTime +
				'<div class="log-user">' +
					'<img class="log-avatar" src="' + profilePhotoUrl + '" alt="">' +
					'<div class="log-user-info">' +
						'<span class="log-user-name">' + encLT(currentUserName) + '</span>' +
						(currentUserRole ? '<span class="log-cred">' + encLT(currentUserRole) + '</span>' : '') +
					'</div>' +
				'</div>' +
			'</div>' +
			'<div class="log-badge check-in">Check-in</div>' +
			'<div class="log-content">' +
				'<div class="sys-card check-in">' +
					'<div class="sys-card-icon">' + shieldSvg + '</div>' +
					'<div class="sys-card-body">' +
						'<div class="sys-card-title">Check-In Status Updated</div>' +
						'<div class="sys-card-rows">' +
							'<div class="sys-card-row">' +
								'Set check-in status to ' +
								'<span class="ci-badge ' + ciClass + '">' + encLT(statusLabel) + '</span>' +
								' for <strong>' + encLT(whoCheckedIn) + '</strong>' +
							'</div>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>';

	$('#historyLog').prepend(html);
}

function addInHistory(dateTime, details, type = '') {
	var dt = dateTime.split(' ');
	var tdDateTime = document.createElement('td');
	tdDateTime.classList.add('text-xs', 'text-[#6B7280]', 'p-2', 'whitespace-nowrap');
	tdDateTime.innerHTML = formatDate(dt[0]) + ' @ ' + formatTime(dt[1]);
	if (type != '') {
		var tdDetailsType = document.createElement('td');
		tdDetailsType.innerHTML = '<span class="inline-block text-xs font-semibold text-[#264F73] border border-[#E2E8F0] rounded-full px-3 py-1.5 whitespace-nowrap">' + type + '</span>';
	}
	var tdDetails = document.createElement('td');
	tdDetails.classList.add('border-0', 'bg-transparent', 'resize-none', 'outline-none', 'w-full', 'text-sm', 'text-[#374151]', 'p-2');
	tdDetails.innerHTML = details;
	var trHistRow = document.createElement('tr');
	trHistRow.classList.add('border-b', 'border-[#E5E7EB]');
	trHistRow.appendChild(tdDateTime);
	if (type != '') {
		trHistRow.appendChild(tdDetailsType);
	}
	trHistRow.appendChild(tdDetails);
	$('#booking-history').prepend(trHistRow);
}

function newBookingForCust(customerId, bookingId, rebook) {
	var url = newBookingUrl + '?customer_id=' + customerId + '&booking_id=' + bookingId +
		(typeof rebook == 'undefined' ? '' : '&rebook=1');
	var form = [];
	var field_type = 'Rebook' + '_' + customerId + '_' + bookingId;
	var inArray = $.inArray(field_type, fieldRequestPermissions);
	if (inArray !== -1) {
		rebookPermission = "Yes";
	}
	if (rebookPermission == 'No') {
		form.push({ name: "field_type", value: field_type });
		form.push({ name: "request_type", value: 'Rebook' });
		form.push({ name: "customer_id", value: customerId });
		requestType(form, '#deleteBooking');
		return false;
	}
	if (rebookPermission == "Yes") {
		if (window.parent == window) {
			location.href = url;
		} else {
			location.href = url + '&modal=1';
		}
	}

}

function markCheckIn(selOrCheckInStatus, index) {
	if (isUnpaidBooking == '1') {
		// Build postData as you already do below (just show how to attach)
		var postData = { booking_id: bookingId };

		if (typeof selOrCheckInStatus == 'object') {
			postData.tpr_id = selOrCheckInStatus.getAttribute('data-tpr-id');
			postData.index = index;
			postData.check_in_status = selOrCheckInStatus.options[selOrCheckInStatus.selectedIndex].value;
		} else {
			postData.check_in_status = selOrCheckInStatus;
		}
		selOrCheckInStatus.value = "N";
		if (isAffiliate == 'No') {
			$('#modal_admin').data('pendingCheckin', postData);
			$('#modal_admin').data('call-by-check-in-people', true).modal('show');
		} else {
			$('#modal_affilate').data('pendingCheckin', postData);
			$('#modal_affilate').data('call-by-check-in-people', true).modal('show');
		}
		return false;
	}
	showLoading();

	var postData = { booking_id: bookingId };

	if (typeof selOrCheckInStatus == 'object') { // function called from dropdown form control for particular age/rate group
		postData.tpr_id = selOrCheckInStatus.getAttribute('data-tpr-id');
		postData.index = index;
		postData.check_in_status = selOrCheckInStatus.options[selOrCheckInStatus.selectedIndex].value;
	} else // function called from bootstrap dropdown for all tourists
		postData.check_in_status = selOrCheckInStatus;

	$.post(updateCheckInStatusUrl, postData)
		.done(function (response) {
			var check_in_status = 'check-in-status-' + postData.check_in_status;

			var setCheckInStatus = function (sel) {
				$(sel)
					.removeClass('check-in-status-N check-in-status-1 check-in-status-2 check-in-status-A')
					.addClass(check_in_status);
			};

			if (typeof selOrCheckInStatus == 'object')
				setCheckInStatus(selOrCheckInStatus);
			else {
				$('.sel-check-in').each(function () {
					for (var i = 0; i < this.options.length; i++) {
						if (this.options[i].value == postData.check_in_status) {
							this.options[i].selected = 1;
							break;
						}
					}

					setCheckInStatus(this);
					if (response.status == null) {
						if (response.is_affiliate == 'No') {
							$('#modal_admin').modal('show');
						} else {
							$('#modal_affilate').modal('show');
						}
					}

				});
			}

			addCheckinInHistory(
				moment(response.checkInDateTime).format('M/D/yyyy @ h:mma'),
				postData.check_in_status,
				checkInStatuses[postData.check_in_status],
				response.whoCheckedIn
			);

		})
		.fail(showSubmissionError)
		.always(hideLoading)
}
function performCheckInAfterPayment(postData) {
	$.post(updateCheckInStatusUrl, postData)
		.done(function (response) {
			var check_in_status = 'check-in-status-' + postData.check_in_status;

			var setCheckInStatus = function (sel) {
				$(sel)
					.removeClass('check-in-status-N check-in-status-1 check-in-status-2 check-in-status-A')
					.addClass(check_in_status);
			};

			if (postData.hasOwnProperty('tpr_id') && postData.tpr_id != null) // function called from dropdown form control for particular age/rate group
				setCheckInStatus(postData.check_in_status);
			else {
				$('.sel-check-in').each(function () {
					for (var i = 0; i < this.options.length; i++) {
						if (this.options[i].value == postData.check_in_status) {
							this.options[i].selected = 1;
							break;
						}
					}
					setCheckInStatus(this);
				});
			}

			addCheckinInHistory(
				moment(response.checkInDateTime).format('M/D/yyyy @ h:mma'),
				postData.check_in_status,
				checkInStatuses[postData.check_in_status],
				response.whoCheckedIn
			);

		})
		.fail(showSubmissionError)
		.always(hideLoading)
}
function validateEditCustFormAndSubmit(form) {
	if (!validateEmailAddressFormat())
		return false;

	if (!validateCharacters('#name, #phone_number'))
		return false;

	return submitForm(form);
}

function validateAddOrEditHotelPickupFormAndSubmit(form) {
	return submitForm(form);
}

function editBooking(form) {
	return submitForm(form);
}

function Cancel_Booking_Form(form) {
	return submitForm(form);
}

$("#tour_slot_time").on("change", function () {
	var slotTime = $('#tour_slot_time').val();
	var bookingTourists = $('#booking_tourists').val();

	if (slotTime == tourSlotId) {
		$('#reschedulebtn').prop('disabled', true);
		swal('Sorry!', 'You can not reschedule in same slot.', 'warning');
		return false;
	} else {
		$('#reschedulebtn').prop('disabled', false);
	}

	$.post(checkOverbooking, {
		'slot_time': slotTime,
		'package_id': packageId
	}).then(function (response) {
		if (response) {
			if (expiryDate != "") {
				const expireTime = new Date(expiryDate).getTime();
				const rescheduleTime = new Date(response.date + ' ' + response.time_from).getTime();
				const timeDifference = expireTime - rescheduleTime;

				if (timeDifference <= 0) {
					$('#reschedulebtn').prop('disabled', true);
					swal('Sorry!', 'You cannot reschedule a booking after expiration time.', 'warning');
					return false;
				}
			}

			if (userType == 'Affiliate') {
				var tourists = parseInt(response.booked_seats);
				var seats = response.seats;
				var overBooking = Math.max(0, tourists - seats);
				var availableSeats = seats - tourists + overBooking;

				// True tour-wide remaining seats (already accounts for every
				// customer's usage, including this affiliate's own bookings)
				var remainingSeats = (tourists > seats) ? 0 : availableSeats;

				var aflTourists = response.afl_booked_seats;
				var aflSeats = parseInt(response.afl_seats);

				// Affiliate's own remaining quota against their allocation.
				// NOTE: don't clamp aflSeats to remainingSeats before doing this
				// subtraction — remainingSeats already has aflTourists baked in
				// (it's derived from tour-wide booked_seats), so doing both would
				// subtract the affiliate's own usage twice and could show "no
				// seats available" even when the affiliate's own block still has
				// plenty of room and the tour has physical capacity to spare.
				var ownRemainingSeats = aflSeats - aflTourists;

				// The affiliate can book the smaller of: what's left in their own
				// allocation, and what's physically left on the tour overall.
				availableSeats = Math.min(ownRemainingSeats, remainingSeats);
				if (availableSeats <= 0 || availableSeats < bookingTourists) {
					$('#reschedulebtn').prop('disabled', true);
					swal('Sorry!', 'You can not book slot above limit of available seats.', 'warning');
					return false;
				} else {
					$('#reschedulebtn').prop('disabled', false);
				}
			}

			if (userType != 'Affiliate') {
				var seats = response.seats - bookingTourists;
				var overbooked = response.booked_seats - seats;
			}

			if (overbooked >= 0 && userType != 'Affiliate') {
				var timeFrom = formatTime(response.time_from);
				var timeTo = (response.time_to == null ? ' onwards' : formatTime(response.time_to));

				var addText = '';
				if (overbooked > 0) {
					addText += 'The ' + timeFrom + ' - ' + timeTo + ' slot is already overbooked.';
				} else if (overbooked == 0) {
					addText += ' The ' + timeFrom + ' - ' + timeTo + ' slot will be full.';
				}

				addText += ' Do you wish to reschedule?';
				$('#reschedulebtn').prop('disabled', false);

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
							text: 'Continue',
							value: true,
							className: 'btn-danger',
						}
					}
				}).then(function (result) {
					console.log(result);
				});
			}
		}
	}).fail(function (jqXHR) {
		console.log(jqXHR);
	});
})

function lowerAntelopeCanyonClosure(arg) {
	if (typeof $(arg).attr('data-tour-operator-name') !== "undefined" && $(arg).attr('data-tour-operator-name') != '') {
		var tourOpName = $(arg).attr('data-tour-operator-name') + ' Closure';
	} else {
		var tourOpName = 'Lower Antelope Canyon Closure';
	}
	swal({
		title: tourOpName,
		text: 'Are you sure you want to send bulk emails to tourists about a closure for this tour?',
		icon: "warning",
		buttons: {
			no: {
				text: 'No',
				value: false,
				className: 'btn-danger',
			},
			yes: {
				text: 'Yes',
				className: 'btn-success',
			}
		}
	})
		.then(function (result) {
			if (!result)
				return;

			var date = $(arg).attr('data-date');
			var slotId = $(arg).attr('data-slot-id');
			var bookingId = $(arg).attr('data-booking-id');
			var tourOpId = $(arg).attr('data-tour-operator-id');
			var form = [];
			var field_type = 'Closure Email_' + date + '_' + slotId + bookingId;
			var inArray = $.inArray(field_type, fieldRequestPermissions);
			if (inArray !== -1) {
				closureEmailPermission = "Yes";
			}
			if (closureEmailPermission == 'No') {
				form.push({ name: "field_type", value: field_type });
				form.push({ name: "request_type", value: 'Closure Email' });
				form.push({ name: "date", value: date });
				form.push({ name: "slotId", value: slotId });
				requestType(form, '#closureEmailEd');
				return false;
			}
			if (closureEmailPermission == "Yes") {
				showLoading();
				$.post(lowerAntelopeCanyonClosureUrl, {
					date: date,
					slot_id: slotId,
					booking_id: bookingId,
					tour_operator_id: tourOpId
				})
					.done(function (response) {
						swal('A closure email was sent.', 'Page will reload.', 'success')
							.then(function () {
								location = location;
							});
					})
					.fail(showSubmissionError)
					.always(hideLoading);
			}
		})
}

function unblockAffiliateSoftBooking(arg) {
	var bookingId = $(arg).attr('data-id');
	var isEeservedForAffiliate = $(arg).attr('data-is_reserved_for_affiliate');

	if (isEeservedForAffiliate == 0) {
		var alertMessage = 'Are you sure you want to block a soft booking?';
		var responseMessage = 'A soft booking is blocked.';
	} else {
		var alertMessage = 'Are you sure you want to unblock a soft booking?';
		var responseMessage = 'A soft booking is unblocked.';
	}

	unblockAffiliateSoftBookingUrl = unblockAffiliateSoftBookingUrl.replace(':id', bookingId);

	swal({
		title: bookingId,
		text: alertMessage,
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
	})
		.then(function (result) {
			if (!result)
				return;

			showLoading();

			$.get(unblockAffiliateSoftBookingUrl)
				.done(function (response) {

					swal(responseMessage, 'Page will reload.', 'success')
						.then(function () {
							location = location;
						});
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		})
}

$("#tour_package_switch").on("change", function () {
	getSlotTimesForPackage();
});

function getSlotTimesForPackage() {

	var $selSlotTime = $('#tour_slot_time_switch');
	$selSlotTime.html('<option value="">Select Time Slot</option>');

	var date = $('#tour_slot_date_switch').val();
	var tourPackageId = $('#tour_package_switch option:selected').val();
	if (tourPackageId == '') {
		swal('Please select tour package.', '', 'warning');
		return false;
	}

	const date1 = new Date(date);
	var month = date1.getMonth() + 1;
	var day = date1.getDate();

	month = (month < 10 ? '0' : '') + month;
	day = (day < 10 ? '0' : '') + day;

	showLoading();
	params = {
		date: date1.getFullYear() + month + day,
		tourPackageId: $('#tour_package_switch').val(),
		getRateGroups: 0
	};
	$.post(getTourSlotTimesByDateUrl, params)
		.done(function (data) {
			var selected = false;
			if (data.packageHasSlots == 0) {
				selected = true;
				$(".package-has-slots").css('display', 'none');
			} else {
				$(".package-has-slots").css('display', 'block');
			}

			var slotTimes = data.slotTimes;
			for (var i = 0; i < slotTimes.length; i++) {
				if (userType == 'Affiliate') {
					var opt = new Option(
						formatTime(slotTimes[i].time_from) +
						(slotTimes[i].time_to == null ? ' onwards' : ' - ' + formatTime(slotTimes[i].time_to)) +
						' (Available: ' + (slotTimes[i].available_tourists) + ')',
						slotTimes[i].id, false, selected
					);
				} else {
					var opt = new Option(
						formatTime(slotTimes[i].time_from) +
						(slotTimes[i].time_to == null ? ' onwards' : ' - ' + formatTime(slotTimes[i].time_to)) +
						' (Available: ' + (slotTimes[i].seats - slotTimes[i].tourists) + ')',
						slotTimes[i].id, false, selected
					);
				}
				if (userType == 'Affiliate' && (slotTimes[i].seats - slotTimes[i].tourists) <= 0) {
					opt.setAttribute('disabled', true);

				}
				$(opt).data('seats', slotTimes[i].seats - slotTimes[i].tourists);
				$selSlotTime.append(opt);
			}
		})
		.fail(function () {
			swal('Error occurred', 'Unable to get time slots of the selected date from server.', 'error');
		})
		.always(hideLoading);
}

function showMakePaymentForSwitchPackage(form) {
	var tourPackageId = $('#tour_package_switch option:selected').val();
	var tourSlotId = $('#tour_slot_time_switch option:selected').val();
	if (tourPackageId == '') {
		swal('Please select tour package.', '', 'warning');
		return false;
	}
	if (tourSlotId == '') {
		swal('Please select tour slot.', '', 'warning');
		return false;
	}
	if (paymentMethod == 'Pay Later') {
		$(form).data('validation_check', true);
		validateAndSubmitFormSwitchPackage(form);
	} else {
		$(form).data('validation_check', true);
		validateAndSubmitFormSwitchPackage(form);
	}

	return false;
}

function submitRefundForSwitchPackage(form) {
	var swipePackageForm = $('#modal-switch-package').find('form');
	$(swipePackageForm).data('validation_check', false);

	validateAndSubmitFormSwitchPackage(swipePackageForm[0], form);
	return false;
}

function validateAndSubmitFormSwitchPackage(form, additionalForm, isPayment, paymentIntentId = "") {
	var submitBtn = $('#btn-make-payment')[0],
		includeValidationCheckField = false,
		includePaymentCheckField = false,
		formObj = $(form),
		formData;

	if (typeof additionalForm === "undefined") {
		includeValidationCheckField = true;
		submitBtn = $('#switchpackagebtn')[0];
	} else {
		includeValidationCheckField = true;
		formObj = formObj.add(additionalForm);
	}

	if (paymentMethod == 'Pay Later') {
		includePaymentCheckField = true;
	}
	// Use the modal's currently-selected radio to detect "Pay with Card" rather than the
	// booking's original payment method (paymentMethod), which may differ from the admin's
	// current selection (e.g. booking was Card but admin chose Pay with Link).
	var _currentSwitchMethod = $('#modal_admin input[name="payment_method"]:checked').val()
		|| $('#modal_affilate input[name="payment_method"]:checked').val()
		|| paymentMethod;
	if (_currentSwitchMethod == 'Card' && $(form).data('validation_check') == false && typeof isPayment !== "undefined" && isPayment == true) {
		if (paymentIntentId == "") {
			var inputId = (isAffiliate == 'Yes') ? 'cardnumber' : 'cardnumber1';
			var inputIdCvv = (isAffiliate == 'Yes') ? 'cvv' : 'cvv1';
			if (!validateCardnumber({ inputId: inputId }))
				return false;

			if (!validateCvv({ inputId: inputIdCvv }))
				return false;
		}

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
	})
		.done(function (response) {

			if (paymentMethod == 'Pay Later') {
				swal('Booking updated successfully', 'Page will reload', 'success')
					.then(function () {
						location = location;
					});
			} else {

				if ($(form).data('validation_check')) {
					submitBtn.disabled = false;
					var remainingAmount = response.amount;
					var isPayment = response.payment;

					if (isPayment == '1' && remainingAmount > 0) {
						if (isAffiliate == 'Yes') {
							$('#modal_affilate').data('call-by-switch-package', true).modal('show');
							if (paymentMethod == 'Cash') {
								$('#modal_affilate .span_cash').text(currencyFormation.symbol + remainingAmount);
								$('#cash-paid-amount1').prop("readonly", false);
							} else {
								$('#cash-paid-amount1').prop("readonly", true);
							}
							$('#modal_affilate .total-after-discount-and-service-commission').text(currencyFormation.symbol + remainingAmount);
							$('#modal_affilate .discount-code-wrp').hide();
							$('#modal_affilate .split-payment-div').hide();
						} else {
							$('#modal_admin').data('call-by-switch-package', true).modal('show');
							if (paymentMethod == 'Cash') {
								$('#modal_admin .cash-amount').text(currencyFormation.symbol + remainingAmount);
								$('#cash-paid-amount').prop("readonly", false);
							} else {
								$('#cash-paid-amount').prop("readonly", true);
							}
							console.log('remaining amt:' + remainingAmount);
							$('#modal_admin .total-after-discount-and-service-commission').text(currencyFormation.symbol + remainingAmount);
							$('#modal_admin .split-payment-div').hide();
						}
						$('#modal-switch-package').data('prevent-hide-callback', true).modal('hide');
						var fees = response.fees;
						backendCardReaderInfo.getTotal(remainingAmount);
						backendCardReaderInfo.getFees(fees);
					} else if (isPayment == '0') {
						$('#modal-switch-package-refund').modal('show');
						$('#modal-switch-package').modal('hide');
					} else {
						swal('Booking updated successfully', 'Page will reload', 'success')
							.then(function () {
								location = location;
							});
					}
				} else {
					if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
						window.location.href = response.nextAction;
					} else if ($('#pay-with-link-cp').is(':checked')) {
						hideLoading();
						swal('Payment link sent successfully.', 'Page will reload', 'success')
							.then(function () { location = location; });
					} else {
						swal('Booking updated successfully', 'Page will reload', 'success')
							.then(function () {
								location = location;
							});
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

function formatClientTime(date, timeZone) {
	return new Intl.DateTimeFormat('en-US', {
		timeZone: timeZone,
		year: 'numeric',
		month: '2-digit',
		day: '2-digit',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
		hour12: true
	}).format(date);
}

function updateTimer() {
	if (expiryDate != "") {
		const endTime = new Date(expiryDate).getTime();
		const now = new Date();
		const currentTime = formatClientTime(now, timezone);
		const startTime = new Date(currentTime).getTime();

		const timeDifference = endTime - startTime;
		if (timeDifference <= 0) {
			// If expired
			$('#expire').html("Expired");
			$('#expireIn, #timer, #formatedExpiryDate, #redeemTickets, #redeemPending, #redeemSection').hide();
			clearInterval(timerInterval);
			return;
		}
		const totalDays = Math.floor(timeDifference / (1000 * 60 * 60 * 24)); // Days
		const totalHours = Math.floor((timeDifference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60)); // Hours
		const remainingMinutes = Math.floor((timeDifference % (1000 * 60 * 60)) / (1000 * 60)); // Minutes

		// Format the expiry date
		const date = new Date(expiryDate);

		function getDayWithSuffix(day) {
			const suffixes = ['th', 'st', 'nd', 'rd'];
			const mod = day % 10;
			return day + (mod >= 1 && mod <= 3 && (Math.floor(day / 10) !== 1) ? suffixes[mod] : suffixes[0]);
		}
		const month = date.toLocaleString('en-US', { month: 'long' });
		const day = date.getDate();
		const year = date.getFullYear();
		const dayWithSuffix = getDayWithSuffix(day);
		const formatedExpiryDate = `${month} ${dayWithSuffix} ${year}`;

		$('#redeemPending, #redeemTickets, #redeemSection').show();

		if (redeemCount <= 0) {
			$('#expireIn').html("Expire In");
			$('#formatedExpiryDate').html(formatedExpiryDate);

			let timerText = '';

			if (totalDays > 0) {
				timerText += `${totalDays} days `;
			}

			if (totalHours > 0) {
				timerText += `${totalHours} hours `;
			}

			timerText += `${remainingMinutes} minutes`;

			$('#timer').html(timerText);
		}
	}
}

const timerInterval = setInterval(updateTimer, 1000);
updateTimer();

function toggleGuideDropdown() {
	document.getElementById('tourGuideDisplay').classList.add('hidden');
	document.getElementById('editIconPencil').classList.add('hidden');
	document.getElementById('tourGuideDropdown').classList.remove('hidden');
	document.getElementById('tourGuideDropdown').focus();
}

function submitEditDiscount(form, type) {
	if (type == 'refund' || type == "card reader") {
		var formData = form;
	} else {
		var formData = $(form).serializeArray();
	}
	showLoading();
	$.ajax({
		headers: { Accept: 'application/json' },
		dataType: 'json',
		type: 'POST',
		url: $('#modal-edit-discount-card-payment').attr('action'),
		data: formData,
		success: function (result) {
			$('#modal_edit_discount_card_payment').modal('hide');
			window.location.reload();
			if (result.discountHistory.payment_method == 'Card') {
				if (result.discountHistory.new_discount2_percentage > 0) {
					var discountVal = '- (' + result.discountHistory.new_discount2_percentage + '%) $' + result.discountHistory.new_discount2_value.toFixed(2);
				} else {
					var discountVal = '- $' + parseFloat(result.discountHistory.new_discount2_value).toFixed(2);
				}
				var paymentTotal = (parseFloat(result.discountHistory.new_total) + parseFloat(result.discountHistory.service_commission)).toFixed(2);
				$("#totalPaument").html(currencyFormation.symbol + paymentTotal);
			}
			swal('Booking updated successfully', 'Page will reload', 'success')
				.then(function () {
					location.reload();
				});
		},
		error: function (jqXHR) {
			showSubmissionError(jqXHR);
		}
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
	}).always(hideLoading);
}

 // Open modal
$(document).on('click', '#openSavedCardsModal', function () {
    $('#savedCardsModal').removeClass('hidden').addClass('flex');
    loadSavedCards(); // load cards dynamically
});

// Close modal
$(document).on('click', '#closeSavedCardsModal', function () {
    $('#savedCardsModal').addClass('hidden').removeClass('flex');
});

// Close when clicking outside
$(document).on('click', '#savedCardsModal', function (e) {
    if (e.target.id === 'savedCardsModal') {
        $('#savedCardsModal').addClass('hidden').removeClass('flex');
    }
});

async function loadSavedCards() {
    const container = $('#savedCardsListContainer');
    container.html(`
        <div class="text-sm text-gray-500 text-center py-4">
            Loading saved cards...
        </div>
    `);

    try {

        const response = await fetch(saveCardListUrl, {
             method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
                booking_id: bookingId
            })
        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || "Failed to fetch saved cards.");
        }

        const savedCards = result.data || [];

        container.html('');

        if (savedCards.length > 0) {

            savedCards.forEach((card, index) => {

                container.append(`
                   <div class="flex items-start justify-between py-3 px-2 rounded-lg hover:bg-gray-50 transition">
                        
                        <div class="flex items-center">
                            <div>
                                <div class="text-sm font-semibold text-gray-800 mb-2">
                                   <img src="${card.brand}" class="inline-block max-h-5"> ending in ${card.last4}
                                </div>

                                <div class="text-xs text-gray-500">
                                    Expires: ${card.exp_month}/${card.exp_year}
                                </div>

                                <div class="text-xs text-gray-400 mt-1">
                                    Added via: ${card.request_from} 
                                    | By: ${card.created_by_user_type}
                                    | On: ${card.created_at}
                                </div>
                            </div>
                        </div>

                        <button 
                            class="delete-card flex items-center gap-1 px-2 py-1 text-xs font-medium 
                                   text-red-600 bg-red-50 hover:bg-red-100 
                                   rounded-md transition"
                            data-payment-method-id="${card.id}"
                            title="Delete this card">
                            <i class="fas fa-trash text-sm"></i>
                        </button>

                    </div>
                `);
            });

        } else {

            container.html(`
                <div class="text-sm text-gray-500 text-center py-4">
                    No saved cards found.
                </div>
            `);
        }

    } catch (error) {

        console.error(error);

        container.html(`
            <div class="text-sm text-red-500 text-center py-4">
                ${error.message}
            </div>
        `);
    }
}

$(document).off('click', '.delete-card')
.on('click', '.delete-card', async function () {

    const paymentMethodId = $(this).data('payment-method-id');

    const confirmDelete = await swal({
        title: 'Delete Card?',
        text: 'This will permanently delete the saved card.',
        icon: 'warning',
        buttons: ['Cancel', 'Delete'],
        dangerMode: true,
    });

    if (!confirmDelete) return;

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
            throw new Error(result.message);
        }

        swal('Success', 'Card deleted successfully', 'success');
        await loadSavedCards();

    } catch (error) {
        swal('Error', error.message, 'error');
    }

    hideLoading();
});
