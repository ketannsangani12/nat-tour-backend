
$(function () {
	var maxLength = 50;
	$(".show-read-more").each(function () {
		var myStr = $.trim($(this).text());

		if (myStr.length > maxLength) {
			var newStr = myStr.substring(0, maxLength);
			var removedStr = myStr.substring(maxLength);

			$(this).html(
				newStr +
				' <a href="javascript:void(0);" class="read-more">read more...</a>' +
				'<span class="more-text" style="display:none;">' + removedStr + '</span>'
			);
		}
	});

	$(document).on("click", ".read-more", function () {
		$(this).siblings(".more-text").show();
		$(this).remove();
	});
	$(document).on('click', '.show-note', function () {
		let note = $(this).data('note');
		$('#fullNoteText').text(note);
		$('#noteModal').modal('show');
	});
	$(document).on('click', '.show-comment', function () {
		let note = $(this).data('comment');
		$('#fullCommentText').text(note);
		$('#commentModal').modal('show');
	});
	// Tab switching
	$(document).on('click', '.tab-btn', function () {
		const target = $(this).data('tab');

		$('.tab-btn')
			.removeClass('active text-blue-600 border-blue-600')
			.addClass('text-gray-500');

		$(this)
			.addClass('active text-blue-600 border-blue-600')
			.removeClass('text-gray-500');

		$('.tab-content').addClass('hidden');
		$('#' + target).removeClass('hidden');
	});


	$(document).on('click', '.add-note-btn', function () {

		const bookingId = $(this).data('booking-id');
		const comment = $(this).data('comment') || '';

		$('#bookingId').val(bookingId);

		// NOTE: always reset
		$('#noteText').val('');

		// COMMENT: prefill if exists
		if (comment.trim() !== '') {
			$('#commentText').val(comment);
		} else {
			$('#commentText').val('');
		}

		// Default tab = Note
		$('.tab-btn[data-tab="noteTab"]').click();

		$('#noteCommentModal').modal('show');
	});
	$('#saveNoteComment').on('click', function () {

		const bookingId = $('#bookingId').val();
		const csrfToken = $('meta[name="csrf-token"]').attr('content');

		// Check active tab
		if ($('.tab-btn.active').data('tab') === 'noteTab') {
			var newSaveNoteURL = saveNoteUrl.replace('_BOOKING_ID_', bookingId);
			var note = $('#noteText').val();
            if (note == '' || !$.trim(note)) {
                swal('Sorry!', 'Please add a note.', 'warning');
                return false;
            }
			const postData = { note: note };
			showLoading();
			$.post(newSaveNoteURL, postData)
				.done(function (resp) {
					swal('Success', 'Details Updated!', 'success');
					location.reload();
				})
				.fail(showSubmissionError)
				.always(hideLoading);

		} else {
			var newSaveCommentURL = saveCommentUrl.replace('_BOOKING_ID_', bookingId);
			var comment = $('#commentText').val();
            if (comment == '' || !$.trim(comment)) {
                swal('Sorry!', 'Please add a comment.', 'warning');
                return false;
            }
			const postData = { comments: comment };
			showLoading();
			$.post(newSaveCommentURL, postData)
				.done(function (resp) {
					swal('Success', 'Details Updated!', 'success');
					location.reload();
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		}
	});

	$('#booking-id').change(function () {
		var id = $('#booking-id').val();
		var payment_status = null;
		payment_status = $('#payment_status').val();
		var tour_operator = $('#tour_operator').val();
		var tour_package = $('#tour_package').val();
		var date = moment($('#date').datepicker('getDate')).format('yyyyMMDD');
		$.ajax({
			type: 'get',
			url: agendaUrl,
			data: {
				id: id,
				payment_status: payment_status,
				tour_operator: tour_operator,
				tour_package: tour_package,
				date: date
			},
			success: function (data) {
				location.reload();
			}
		});
	});
	$.each(slots, function (key, val) {
		var waiverColumn = '';
		if (typeof (val.packageHasWaiver) != 'undefined' && val.packageHasWaiver == 1) {
			waiverColumn = { "orderable": false, "targets": 7 };
		}
		if (val.id != '') {
			var oTable = $('#datatable_' + val.id).DataTable({
				paging: false,
				searching: false,
				info: false,
				bSortCellsTop: true,
				columnDefs: [
					{ "orderable": false, "targets": 0 },
					{ "orderable": false, "targets": 2 },
					{ "orderable": false, "targets": 4 },
					{ "orderable": false, "targets": 5 },
					{ "orderable": false, "targets": 6 },
					waiverColumn
				],
				order: [
					[
						1, 'asc'
					]
				]
			});
		} else {
			var oTable = $('#datatable_0').DataTable({
				paging: false,
				searching: false,
				info: false,
				bSortCellsTop: true,
				columnDefs: [
					{ "orderable": false, "targets": 0 },
					{ "orderable": false, "targets": 2 },
					{ "orderable": false, "targets": 4 },
					{ "orderable": false, "targets": 5 },
					{ "orderable": false, "targets": 6 },
					waiverColumn
				],
				order: [
					[
						1, 'asc'
					]
				]
			});
		}
	});

	for (var i = 0, $timeSlots = $('#time-slots'), $bookingHotels = $('#booking-hotels'); i < slots.length; i++) {
		if (slots[i].tTo != undefined) {
			if (slots[i].packageHasSlots) {
				$timeSlots.append(
					'<option value=' + slots[i].id + ' data-time="' + slots[i].tFrom + '-' + slots[i].tTo + '">' +
					formatTime(slots[i].tFrom) + ' - ' + formatTime(slots[i].tTo) +
					'</option>'
				);
			}
		} else {
			$bookingHotels.append(
				'<option value=' + slots[i].id + ' data-time="' + slots[i].id + '">' + (slots[i].hotel_name != null ? slots[i].hotel_name : 'Custom Pickup') + '</option>'
			);
		}
	}

	var allTouristsCount = 0;
	var firstCheckIns = 0;
	var secondCheckIns = 0;
	var absent = 0;
	var redeemedTickets = 0;
	var notRedeemedTickets = 0;
	var totalTouristsCount = 0;
	var redeemTicketFlag = 0;
	var checkInFlag = 0;
	var assignedGuides = 0;
	for (var i = 0; i < seatsInfo.length; i++) {
		var slotId = seatsInfo[i].slotId;
		var ageGroupWiseTourists = [];
		var slotCheckIns = [];
		var slotCheckInsCount = seatsInfo[i].checkInsCount['1'] + seatsInfo[i].checkInsCount['2'];
		totalTouristsCount = seatsInfo[i].redeemTicketsCount['not_redeemed'] + seatsInfo[i].redeemTicketsCount['redeemed'];
		var slotRedeemTicketsCount = seatsInfo[i].redeemTicketsCount['redeemed'];

		// all 1st check in
		if (seatsInfo[i].checkInsCount['1'] == (seatsInfo[i].touristsCount + seatsInfo[i].infantsCount))
			$('#check-in-status-wise-tourists-count-' + slotId).html('All 1st Check In');
		// all 2nd check in
		else if (seatsInfo[i].checkInsCount['2'] == (seatsInfo[i].touristsCount + seatsInfo[i].infantsCount))
			$('#check-in-status-wise-tourists-count-' + slotId).html('All 2nd Check In');
		// all absent
		else if (seatsInfo[i].checkInsCount['A'] == (seatsInfo[i].touristsCount + seatsInfo[i].infantsCount))
			$('#check-in-status-wise-tourists-count-' + slotId).html('All Absent');
		else {
			var slotCheckIns = [];

			var notCheckInCount = (seatsInfo[i].touristsCount + seatsInfo[i].infantsCount) - (
				seatsInfo[i].checkInsCount['1'] + seatsInfo[i].checkInsCount['2'] + seatsInfo[i].checkInsCount['A']
			);

			if (seatsInfo[i].checkInType == 0) {
				if (notCheckInCount)
					slotCheckIns.push(notCheckInCount + ' Not Checked In');
			}

			if (seatsInfo[i].checkInsCount['1'])
				slotCheckIns.push(seatsInfo[i].checkInsCount['1'] + ' 1st Check In');

			if (seatsInfo[i].checkInsCount['2'])
				slotCheckIns.push(seatsInfo[i].checkInsCount['2'] + ' 2nd Check In');

			if (seatsInfo[i].checkInsCount['A'])
				slotCheckIns.push(seatsInfo[i].checkInsCount['A'] + ' Absent');

			$('#check-in-status-wise-tourists-count-' + slotId).html(slotCheckIns.join('<br>'));
		}

		if (seatsInfo[i].checkInType == 1) {
			// If all tickets are redeemed
			if (seatsInfo[i].redeemTicketsCount['redeemed'] == totalTouristsCount)
				$('#check-in-status-wise-tourists-count-' + slotId).html('All Tickets Redeemed');

			else {
				var slotTickets = [];

				if (seatsInfo[i].redeemTicketsCount['not_redeemed'])
					slotTickets.push(seatsInfo[i].redeemTicketsCount['not_redeemed'] + ' Tickets Not Redeemed');

				if (seatsInfo[i].redeemTicketsCount['redeemed'])
					slotTickets.push(seatsInfo[i].redeemTicketsCount['redeemed'] + ' Tickets Redeemed');

				$('#check-in-status-wise-tourists-count-' + slotId).html(slotTickets.join('<br>'));
			}
		}

		for (agtc in seatsInfo[i].ageGroupWiseTouristsCount)
			ageGroupWiseTourists.push(seatsInfo[i].ageGroupWiseTouristsCount[agtc] + ' ' + encLT(agtc));

		$('#age-wise-tourists-count-' + slotId).html(ageGroupWiseTourists.join('<br>'));

		$('#tourists-' + slotId).html(seatsInfo[i].bookingsCount);
		$('#bookings-count-' + slotId).html(seatsInfo[i].bookingsCount);
		$('#tourists-count-' + slotId).html(seatsInfo[i].touristsCount + seatsInfo[i].infantsCount);
		$('#guides-count-' + slotId).html(seatsInfo[i].guidesCount);

		if (uTypeAdmin == 'Affiliate') {
			if (seatsInfo[i].aflRemainingSeats == 0) {
				$('#empty-' + slotId).html(0);
			} else {
				$('#empty-' + slotId).html(seatsInfo[i].aflRemainingSeats);
			}
		} else {
			let value = seatsInfo[i].seatsCount - seatsInfo[i].touristsCount;
			let span = $('#empty-' + slotId);

			span.html(value);

			if (value < 0) {
				span.css('color', 'rgb(255 0 0)'); // make text red
			} else {
				span.css('color', ''); // reset color if needed
			}

		}
		$('#checked-in-' + slotId).html(slotCheckInsCount + ' / ' + (seatsInfo[i].touristsCount + seatsInfo[i].infantsCount));
		$('#check-in-count-' + slotId).html(slotCheckInsCount);

		// For tickets
		if (seatsInfo[i].checkInType == 1) {
			$('#checked-in-' + slotId).html(slotRedeemTicketsCount + ' / ' + totalTouristsCount);
			$('#check-in-count-' + slotId).html(slotRedeemTicketsCount);
		}

		if (seatsInfo[i].totalWaiver > 0) {
			$('#waiver-signs-count-' + slotId).html(seatsInfo[i].totalWaiver);
		} else {
			$('#waiver-signs-count-' + slotId).css('display', 'none');
			$('#waiver-signs-count-label-' + slotId).css('display', 'none');
		}
		if (seatsInfo[i].totalNotSigned > 0) {
			$('#waiver-not-signs-count-' + slotId).html(seatsInfo[i].totalNotSigned);
		} else {
			$('#waiver-not-signs-count-' + slotId).css('display', 'none');
			$('#waiver-not-signs-count-label-' + slotId).css('display', 'none');
		}
		$('#waiver-signs-count-' + slotId).html(seatsInfo[i].totalWaiver);
		$('#waiver-not-signs-count-' + slotId).html(seatsInfo[i].totalNotSigned);

		allTouristsCount += parseFloat(seatsInfo[i].touristsCount.toFixed(2)) + parseFloat(seatsInfo[i].infantsCount.toFixed(2));
		firstCheckIns += parseFloat(seatsInfo[i].checkInsCount[1].toFixed(2));
		secondCheckIns += parseFloat(seatsInfo[i].checkInsCount[2].toFixed(2));
		absent += parseFloat(seatsInfo[i].checkInsCount['A'].toFixed(2));
		notRedeemedTickets += parseFloat(seatsInfo[i].redeemTicketsCount['not_redeemed'].toFixed(2));
		redeemedTickets += parseFloat(seatsInfo[i].redeemTicketsCount['redeemed'].toFixed(2));

		$('#progress-seats-' + slotId).css('width', ((seatsInfo[i].tourists * 100) / seatsInfo[i].seats) + '%');
		$('#progress-checked-in-' + slotId).css('width', (slotCheckInsCount * 100 / seatsInfo[i].touristsCount) + '%');
		// Check weather checkin/redeem ticket feature is enabled package wise
		if (seatsInfo[i].checkInType == 0) {
			checkInFlag++;
		} else {
			redeemTicketFlag++;
		}
		
		// Guides Summary
		var guidesSummary = [];
		if (seatsInfo[i].guidesSummary) {

		    for (var guide in seatsInfo[i].guidesSummary) {
		        guidesSummary.push(guide + ': ' + seatsInfo[i].guidesSummary[guide]);
		    }

		    $('#guides-summary-' + slotId).html(guidesSummary.join('<br>'));
		}
	}

	$('#all_tourists_count').html(allTouristsCount);
	$('#all_bookings_count').html(totalBookingsCount);
	if (checkInFlag > 0) {
		$('#not_checked_in').html(allTouristsCount - (firstCheckIns + secondCheckIns + absent));
	}

	$('#first_check_ins').html(firstCheckIns);
	$('#second_check_ins').html(secondCheckIns);
	$('#absent').html(absent);

	if (redeemTicketFlag > 0) {
		$('#redeemed_tickets').html(redeemedTickets);
		$('#not_redeemed_tickets').html(notRedeemedTickets);
	}

	formatTimes();

	$('.select-tour-time').select2();

	$('.tour-guide').select2({
		width: '100%',
		dropdownParent: $('#assignGuidesModal')
	});

	$('#date').datepicker({
		dateFormat: 'DD, MM d yy'
	});

	$('.next-day, .prev-day').on('click', function () {
		var date = $('#date').datepicker('getDate');
		date.setDate(date.getDate() + parseInt($(this).data('incr-decr')));
		$('#date').datepicker('setDate', date);
		location.href = tourBookingDayTimeSlotsUrl();
	});

	$('#date, #tour_operator, #tour_package, #payment_status').on('change', function () {
		if ($('.checkin-overview-container').children().length > 0) {
			agendaCheckinOverview.loadOverview();
			return;
		}
		location.href = tourBookingDayTimeSlotsUrl();
	});
});

$(document).click(function () {
	var container = $("#container");
	if (!container.is(event.target) && !container.has(event.target).length) {
		container.hide();
	}
});

$(document).click(function (e) {
	var myTarget = $(".checkins");
	var clicked = e.target.className;

	if ($.trim(myTarget) != '') {
		if ("checkins" != clicked) {
			$("ul.ul-check-in").hide();
		}
	}
});

function showCheckInOptions(bookingId, checkedIn, evt) {
	if (uTypeAdmin == 'Affiliate') {
		return false;
	}
	if (bookingId != '') {
		// if ($('#checkinoptions_' + bookingId + '_' + checkedIn).is(":hidden")) {
		// 	$('#checkinoptions_' + bookingId + '_' + checkedIn).show();
		// 	$('.ul-check-in').not('#checkinoptions_' + bookingId + '_' + checkedIn).hide();
		// } else if ($('#checkinoptions_' + bookingId + '_' + checkedIn).is(":visible")) {
		// 	$('#checkinoptions_' + bookingId + '_' + checkedIn).hide();
		// }
		evt.stopPropagation(); // Keep dropdown open when clicking inside

		const $target = $('#checkinoptions_' + bookingId + '_' + checkedIn);
		const $others = $('.ul-check-in').not($target);

		// Hide all other dropdowns first
		$others.addClass('hidden').css('display', 'none');

		// Toggle current one properly
		if ($target.hasClass('hidden') || $target.css('display') === 'none') {
			$target.removeClass('hidden').css('display', 'block');
		} else {
			$target.addClass('hidden').css('display', 'none');
		}
	}
}

function markCheckIn(selOrCheckInStatus, bookingId, totaltourists, allSameCheckedIn = '', oldCheckInStatus = '') {
	var bookingRow = $('[data-slot-booking-id="' + bookingId + '"]');
	if (bookingRow.length && bookingRow.data('is-unpaid') == '1') {
		var bookingInfoUrl = bookingRow.data('booking-url');
		swal({
			title: 'Payment Required',
			text: 'This booking has an outstanding balance. Please collect payment before checking in.',
			icon: 'warning',
			showCancelButton: true,
			confirmButtonText: 'Make Payment',
			cancelButtonText: 'Cancel',
			confirmButtonColor: '#264f73',
		}).then(function(result) {
			if (result.isConfirmed) {
				window.open(bookingInfoUrl, '_blank');
			}
		});
		return false;
	}
	showLoading();

	var postData = { booking_id: bookingId, all_same_checked_in: allSameCheckedIn };
	if (selOrCheckInStatus == '3') {
		selOrCheckInStatus = "A";
	}
	if (selOrCheckInStatus == '4') {
		selOrCheckInStatus = "N";
	}
	if (oldCheckInStatus == '3') {
		oldCheckInStatus = "A";
	}
	if (oldCheckInStatus == '4') {
		oldCheckInStatus = "N";
	}

	postData.check_in_status = selOrCheckInStatus;
	postData.old_check_in_status = oldCheckInStatus;
	$.post(updateCheckInStatusUrl, postData)
		.done(function (response) {
			hideLoading();
			if (selOrCheckInStatus == '1') {
				if (oldCheckInStatus == 'N') {
					oldCheckInStatus = "4";
				}

				if (allSameCheckedIn == '0') {
					if ($('#allcheckedin_' + bookingId + "_1").length > 0) {
						var firstCheckedInTouristsCount = $('#allcheckedin_' + bookingId + "_1").data('tourists');
						totaltourists += parseInt(firstCheckedInTouristsCount);
						$('#allcheckedin_' + bookingId + "_1").replaceWith('<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-[#19875430] rounded-[100px] border border-[#198754] font-normal inline-block" id="allcheckedin_' + bookingId + '_1" onclick="showCheckInOptions(' + bookingId + ',1, event)"  style="border: 1px solid #198754; background-color: #19875430;"><span class="font-semibold text-[#0f5132]">' + totaltourists + '</span> <span class="text-black"> 1st Check In </span></div>');
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).remove();
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
						if (Object.keys(tourOperatorCheckInStatues).length > 0) {
							var checkInOptions = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_1" style="min-width: 9rem;">';
							if (Object.values(tourOperatorCheckInStatues).indexOf('N') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Not Checked In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('2') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('A') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li>';
							}
							checkInOptions += '</ul>';
							$('#allcheckedin_' + bookingId + "_1").after(checkInOptions);
						} else {
							$('#allcheckedin_' + bookingId + "_1").after('<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_1" style="min-width: 9rem;"><li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Not Checked In</li><li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li><li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li></ul>');
						}
					} else {
						$('#allcheckedin_' + bookingId + "_4").replaceWith('<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-[#19875430] rounded-[100px] border border-[#198754] font-normal inline-block" id="allcheckedin_' + bookingId + '_1" data-tourists="' + totaltourists + '" onclick="showCheckInOptions(' + bookingId + ',1, event)"  style="border: 1px solid #198754; background-color: #19875430;"><span class="font-semibold text-[#0f5132]">' + totaltourists + '</span> <span class="text-black"> 1st Check In </span></div>');
						$('#checkinoptions_' + bookingId + "_4").remove();
						if (Object.keys(tourOperatorCheckInStatues).length > 0) {
							var checkInOptions = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_1" style="min-width: 9rem;">';
							if (Object.values(tourOperatorCheckInStatues).indexOf('N') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Not Checked In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('2') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('A') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li>';
							}
							checkInOptions += '</ul>';
							$('#allcheckedin_' + bookingId + "_1").after(checkInOptions);
						} else {
							$('#allcheckedin_' + bookingId + "_1").after('<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_1" style="min-width: 9rem;"><li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Not Checked In</li><li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li><li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li></ul>');
						}
					}
				} else {
					$('#allcheckedin_' + bookingId + "_4").replaceWith('<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-[#19875430] rounded-[100px] border border-[#198754] font-normal inline-block" id="allcheckedin_' + bookingId + '_1" data-tourists="' + totaltourists + '" onclick="showCheckInOptions(' + bookingId + ',1, event)"  style="border: 1px solid #198754; background-color: #19875430;"><span class="font-semibold text-[#0f5132]">' + totaltourists + '</span> <span class="text-black"> 1st Check In </span></div>');
					$('#checkinoptions_' + bookingId + "_4").remove();
					if (Object.keys(tourOperatorCheckInStatues).length > 0) {
						var checkInOptions = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_1" style="min-width: 9rem;">';
						if (Object.values(tourOperatorCheckInStatues).indexOf('N') > -1) {
							checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Not Checked In</li>';
						}
						if (Object.values(tourOperatorCheckInStatues).indexOf('2') > -1) {
							checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li>';
						}
						if (Object.values(tourOperatorCheckInStatues).indexOf('A') > -1) {
							checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li>';
						}
						checkInOptions += '</ul>';
						$('#allcheckedin_' + bookingId + "_1").after(checkInOptions);
					} else {
						$('#allcheckedin_' + bookingId + "_1").after('<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_1" style="min-width: 9rem;"><li class="px-4 py-1 cursor-pointer bg-[#ffc10720] check-in-status-N" onclick="markCheckIn(4,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Not Checked In</li><li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li><li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li></ul>');
					}
				}
			} else if (selOrCheckInStatus == '2') {
				if (oldCheckInStatus == 'N') {
					oldCheckInStatus = "4";
				}
				if (allSameCheckedIn == '0') {
					if ($('#allcheckedin_' + bookingId + "_2").length > 0) {
						var secondCheckedInTouristsCount = $('#allcheckedin_' + bookingId + "_2").data('tourists');
						totaltourists += parseInt(secondCheckedInTouristsCount);
						$('#allcheckedin_' + bookingId + "_2").replaceWith('<div  class="checkins px-[13px] py-[5px] mb-2 mt-2 text-sm whitespace-nowrap bg-[#0dcaf020] rounded-[100px] border border-[#b6effb] font-normal inline-block" id="allcheckedin_' + bookingId + '_2" data-tourists="' + totaltourists + '" style="border: 1px solid #b6effb; background-color: #0dcaf020;"><span class="font-semibold text-[#055160]">' + totaltourists + '</span> <span class="text-black"> 2nd Check In </span></div>');
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).remove();
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
					} else {
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).replaceWith('<div class="checkins px-[13px] py-[5px] mb-2 mt-2 text-sm whitespace-nowrap bg-[#0dcaf020] rounded-[100px] border border-[#b6effb] font-normal inline-block" id="allcheckedin_' + bookingId + '_2" data-tourists="' + totaltourists + '" style="border: 1px solid #b6effb; background-color: #0dcaf020;"><span class="font-semibold text-[#055160]">' + totaltourists + '</span> <span class="text-black"> 2nd Check In </span></div>');
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
					}
				} else {
					$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).replaceWith('<div class="checkins px-[13px] py-[5px] mb-2 mt-2 text-sm whitespace-nowrap bg-[#0dcaf020] rounded-[100px] border border-[#b6effb] font-normal inline-block" id="allcheckedin_' + bookingId + '_2" data-tourists="' + totaltourists + '" style="border: 1px solid #b6effb; background-color: #0dcaf020;"><span class="font-semibold text-[#055160]">' + totaltourists + '</span> <span class="text-black"> 2nd Check In </span></div>');
					$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
				}
			} else if (selOrCheckInStatus == 'A') {
				if (oldCheckInStatus == 'N') {
					oldCheckInStatus = "4";
				}
				if (allSameCheckedIn == '0') {
					if ($('#allcheckedin_' + bookingId + "_3").length > 0) {
						var absentCheckedInTouristsCount = $('#allcheckedin_' + bookingId + "_3").data('tourists');
						totaltourists += parseInt(absentCheckedInTouristsCount);
						$('#allcheckedin_' + bookingId + "_3").replaceWith('<div class="checkins px-[13px] py-[5px] mb-2 text-sm whitespace-nowrap bg-[#dc354520] rounded-[100px] border border-[#f5c2c7] font-normal inline-block" id="allcheckedin_' + bookingId + '_3" data-tourists="' + totaltourists + '" style="border: 1px solid #f5c2c7; background-color: #dc354520;"><span class="font-semibold text-[#842029]">' + totaltourists + '</span> <span class="text-black"> Absent </span></div>');
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).remove();
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
					} else {
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).replaceWith('<div class="checkins px-[13px] py-[5px] mb-2 text-sm whitespace-nowrap bg-[#dc354520] rounded-[100px] border border-[#f5c2c7] font-normal inline-block" id="allcheckedin_' + bookingId + '_3" data-tourists="' + totaltourists + '" style="border: 1px solid #f5c2c7; background-color: #dc354520;"><span class="font-semibold text-[#842029]">' + totaltourists + '</span> <span class="text-black"> Absent </span></div>');
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
					}
				} else {
					$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).replaceWith('<div class="checkins px-[13px] py-[5px] mb-2 text-sm whitespace-nowrap bg-[#dc354520] rounded-[100px] border border-[#f5c2c7] font-normal inline-block" id="allcheckedin_' + bookingId + '_3" data-tourists="' + totaltourists + '" style="border: 1px solid #f5c2c7; background-color: #dc354520;"><span class="font-semibold text-[#842029]">' + totaltourists + '</span> <span class="text-black"> Absent </span></div>');
					$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
				}
			} else if (selOrCheckInStatus == 'N') {
				if (selOrCheckInStatus == 'N') {
					selOrCheckInStatus = "4";
				}
				if (allSameCheckedIn == '0') {
					if ($('#allcheckedin_' + bookingId + "_4").length > 0) {
						var noCheckedInTouristsCount = $('#allcheckedin_' + bookingId + "_4").data('tourists');
						totaltourists += parseInt(noCheckedInTouristsCount);
						$('#allcheckedin_' + bookingId + "_4").replaceWith('<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-orange-50 rounded-[100px] border border-[#a32624] font-normal inline-block" id="allcheckedin_' + bookingId + '_4" onclick="showCheckInOptions(' + bookingId + ',4, event)" data-tourists="' + totaltourists + '" style="border: 1px solid #ffc107; background-color: #ffc10720;"><span class="font-semibold text-[#a32624]">' + totaltourists + '</span> <span class="text-black"> Not Checked In </span></div>');
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).next("br").remove();
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).remove();
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
						if (Object.keys(tourOperatorCheckInStatues).length > 0) {
							var checkInOptions = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_4" data-tourists="' + totaltourists + '" style="min-width: 9rem;">';
							if (Object.values(tourOperatorCheckInStatues).indexOf('1') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">1st Check In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('2') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('A') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li>';
							}
							checkInOptions += '</ul>';
							$('#allcheckedin_' + bookingId + "_4").after(checkInOptions);
						} else {
							$('#allcheckedin_' + bookingId + "_4").after('<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_4" data-tourists="' + totaltourists + '" style="min-width: 9rem;"><li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">1st Check In</li><li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li><li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li></ul>');
						}
					} else {
						$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).replaceWith('<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-orange-50 rounded-[100px] border border-[#a32624] font-normal inline-block" id="allcheckedin_' + bookingId + '_4" onclick="showCheckInOptions(' + bookingId + ',4, event)" style="border: 1px solid #ffc107; background-color: #ffc10720;"><span class="font-semibold text-[#a32624]">' + totaltourists + '</span> <span class="text-black"> Not Checked In </span></div>');
						$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
						if (Object.keys(tourOperatorCheckInStatues).length > 0) {
							var checkInOptions = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_4" data-tourists="' + totaltourists + '" style="min-width: 9rem;">';
							if (Object.values(tourOperatorCheckInStatues).indexOf('1') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">1st Check In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('2') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li>';
							}
							if (Object.values(tourOperatorCheckInStatues).indexOf('A') > -1) {
								checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li>';
							}
							checkInOptions += '</ul>';
							$('#allcheckedin_' + bookingId + "_4").after(checkInOptions);
						} else {
							$('#allcheckedin_' + bookingId + "_4").after('<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_4" style="min-width: 9rem;"><li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">1st Check In</li><li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li><li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li></ul>');
						}
					}
				} else {
					$('#allcheckedin_' + bookingId + "_" + oldCheckInStatus).replaceWith('<div class="checkins px-[13px] py-[5px] text-sm whitespace-nowrap bg-orange-50 rounded-[100px] border border-[#a32624] font-normal inline-block" id="allcheckedin_' + bookingId + '_4" onclick="showCheckInOptions(' + bookingId + ',4, event)" style="border: 1px solid #ffc107; background-color: #ffc10720;"><span class="font-semibold text-[#a32624]">' + totaltourists + '</span> <span class="text-black"> Not Checked In </span></div>');
					$('#checkinoptions_' + bookingId + "_" + oldCheckInStatus).remove();
					if (Object.keys(tourOperatorCheckInStatues).length > 0) {
						var checkInOptions = '<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_4" style="min-width: 9rem;">';
						if (Object.values(tourOperatorCheckInStatues).indexOf('1') > -1) {
							checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">1st Check In</li>';
						}
						if (Object.values(tourOperatorCheckInStatues).indexOf('2') > -1) {
							checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li>';
						}
						if (Object.values(tourOperatorCheckInStatues).indexOf('A') > -1) {
							checkInOptions += '<li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li>';
						}
						checkInOptions += '</ul>';
						$('#allcheckedin_' + bookingId + "_4").after(checkInOptions);
					} else {
						$('#allcheckedin_' + bookingId + "_4").after('<ul class="dropdown-menu ul-check-in absolute z-[1000] hidden min-w-[10rem] m-0 text-base text-[#212529] text-left list-none bg-white bg-clip-padding border border-[rgba(0,0,0,0.15)] rounded-md" id="checkinoptions_' + bookingId + '_4" style="min-width: 9rem;"><li class="px-4 py-1 cursor-pointer bg-[#19875430] check-in-status-1" onclick="markCheckIn(1,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">1st Check In</li><li class="px-4 py-1 cursor-pointer bg-[#0dcaf020] check-in-status-2" onclick="markCheckIn(2,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">2nd Check In</li><li class="px-4 py-1 cursor-pointer bg-[#dc354520] check-in-status-A" onclick="markCheckIn(3,' + bookingId + ',' + totaltourists + ',' + allSameCheckedIn + ',' + selOrCheckInStatus + ');" tabindex="0">Absent</li></ul>');
					}
				}
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading)
}

function checkDateTime(btn) {
	if (btn.getAttribute('data-package-has-slots') == 1 && serverDT.format('YYYY-MM-DD kk:mm:ss') >= btn.getAttribute('data-slot-date-time')) {
		swal('', "The tour's date-time has passed, booking isn't possible in the past.", 'warning');
		btn.setAttribute('disabled', 1);
	} else {
		location.href = btn.getAttribute('data-go-to');
	}
}

function filterSlots(selTimeSlots) {
	var slotTime = selTimeSlots.options[selTimeSlots.selectedIndex].getAttribute('data-time');

	$('.div-slot').each(function () {
		var $this = $(this);

		if (slotTime) {
			if (this.getAttribute('data-time') == slotTime) {
				$this.removeClass('hidden');
			} else {
				$this.addClass('hidden');
			}
		} else {
			$this.removeClass('hidden');
		}
	});
}

/* Redeem Ticket Status Update Start */
function updateRedeemTicketStatus(bookingId, parentPackageId, packageId, redeemTicketsCount, notRedeemTicketsCount) {
	if (uTypeAdmin == 'Affiliate') {
		return false;
	}

	var postData = {
		booking_id: bookingId,
		all_redeem_tickets: 'all',
		parent_package_id: parentPackageId,
		package_id: packageId,
		redeem_tickets: redeemTicketsCount,
		not_redeem_tickets: notRedeemTicketsCount
	};

	showLoading();

	$.post(updateRedeemTicketStatusUrl, postData)
		.done(function (response) {
			hideLoading();
			if (response.status == 'Inactive') {
				$("#redeem_ticket_" + bookingId + '_' + packageId).html(response.tourists);
				$("#all_redeem_ticket_" + bookingId + '_' + packageId).removeClass('success-text-color success-bg-color success-border-color');
				$("#all_redeem_ticket_" + bookingId + '_' + packageId).addClass('alert-text-color alert-bg-color alert-border-color');
				$("#all_redeem_ticket_" + bookingId + '_' + packageId).css('cursor', 'unset');
				$("#all_redeem_ticket_" + bookingId + '_' + packageId).prop("onclick", null);
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading)
}
/* Redeem Ticket Status Update End */

/* WWR Roster CSV Report Start */
$("#roster_report_csv_download").click(function () {
	var rosterReportCSVDownload = rosterReportCSVUrl;
	rosterReportCSVDownload = rosterReportCSVUrl.replace('_DATE_', moment($('#date').datepicker('getDate')).format('yyyyMMDD'));

	if (uTypeAdmin) {
		rosterReportCSVDownload = rosterReportCSVDownload.replace('_TOUR_OPERATOR_', $('#tour_operator').val());
	}

	rosterReportCSVDownload = rosterReportCSVDownload.replace('_TOUR_PACKAGE_', $('#tour_package').val())
		.replace('_PAYMENT_STATUS_', (($('#payment_status').val() != "") ? $('#payment_status').val() : 0))
		.replace('_SLOTID_', $('#time-slots').val());

	window.location = rosterReportCSVDownload;
});
/* WWR Roster CSV Report End */

/* WWR Roster PDF Report Start */
$("#roster_report_pdf_download").click(function () {
	var rosterReportPDFDownload = rosterReportPDFUrl;
	rosterReportPDFDownload = rosterReportPDFUrl.replace('_DATE_', moment($('#date').datepicker('getDate')).format('yyyyMMDD'));

	if (uTypeAdmin) {
		rosterReportPDFDownload = rosterReportPDFDownload.replace('_TOUR_OPERATOR_', $('#tour_operator').val());
	}

	rosterReportPDFDownload = rosterReportPDFDownload.replace('_TOUR_PACKAGE_', $('#tour_package').val())
		.replace('_PAYMENT_STATUS_', (($('#payment_status').val() != "") ? $('#payment_status').val() : 0))
		.replace('_SLOTID_', $('#time-slots').val());

	window.location = rosterReportPDFDownload;
});
/* WWR Roster PDF Report End */

/* Booking Contacts Start */
function bookingContacts(bookingId) {
	newBookingContactsURL = bookingContactsURL.replace('_BOOKING_ID_', bookingId);
	showLoading();

	$.get(newBookingContactsURL)
		.done(function (response) {
			if (response.length == 0) {
				swal('Contact Details Not Found!!', '', 'warning');
				return false;
			}

			$("#contact-body").empty();
			renderContactDetails(response);
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function renderContactDetails(contactDetails) {
	let setContacts = '';
	var count = 0;

	$.each(contactDetails, function (key, contact) {
		var tourists = contact.contact_data.length;

		if (tourists > 0) {
			/* Rate Header Start */
			if (count == 0) {
				setContacts += `<div class="rate-group-header px-3 py-2 bg-[#F9FAFB] border border-[#E5E7EB] rounded mt-2" id="rate_${contact.rate_id}">`;
			} else {
				setContacts += `<div class="rate-group-header px-3 py-2 bg-[#F9FAFB] border border-[#E5E7EB] rounded mt-2" id="rate_${contact.rate_id}">`;
			}

			setContacts += `<b>${contact.rate_for}</b><span>   [Pax: ${tourists}]</span></div>`;
			/* Rate Header End */

			/* Rate Wise Content(Contact Forms) Start */
			setContacts += `<div class="row add-contact-section " id="rate-contact-section-${contact.rate_id}">`;

			for (var y = 0; y < tourists; y++) {
				var note = (contact.contact_data[y].note != null && contact.contact_data[y].note != "") ? contact.contact_data[y].note : "";

				setContacts += `<div class="contact-section mt-2 border border-[#E5E7EB] rounded-lg overflow-hidden" id="contact_section_${y}">`;

				setContacts += `<div class="card">
						<div class="card-header contact-header px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label !mb-0 w-full">
							Contact #${y + 1}
						</div>
						<div class="card-body">
							<div class="form-group flex items-center border border-[#E5E7EB] -mt-[1px] p-2 contact-fields">
								<label for="c_name_${contact.rate_id}_${y}" class="col-form-label text-sm text-[#020817] font-medium min-w-24"><b>Name: </b></label>
								<span class="contact-title text-sm text-[#6B7280]">${contact.contact_data[y].name}</span>
							</div>
							<div class="form-group flex items-center border border-[#E5E7EB] -mt-[1px] p-2 contact-fields">
								<label for="c_age_${contact.rate_id}_${y}" class="col-form-label text-sm text-[#020817] font-medium" min-w-24><b>Age: </b></label>
								<span class="contact-title text-sm text-[#6B7280]">${contact.contact_data[y].age}</span>
							</div>
							<div class="form-group flex items-center border border-[#E5E7EB] -mt-[1px] p-2 contact-fields">
								<label for="c_weight_${contact.rate_id}_${y}" class="col-form-label text-sm text-[#020817] font-medi min-w-24um"><b>Weight: </b></label>
								<span class="contact-title text-sm text-[#6B7280]">${contact.contact_data[y].weight} lbs</span>
							</div>
							<div class="form-group flex items-center border border-[#E5E7EB] -mt-[1px] p-2 contact-fields">
								<label for="c_note_${contact.rate_id}_${y}" class="col-form-label text-sm text-[#020817] font-medium min-w-24"><b>Note: </b></label>
								<span class="contact-title text-sm text-[#6B7280]">${note}</span>
							</div>
						</div>
					</div>
				</div>`;
			}

			setContacts += `</div>`;
			/* Rate Wise Content(Contact Forms) End */
			count++;
		}
	});

	$("#contact-body").append(setContacts);
	$("#booking_contacts").modal('show');
}
/* Booking Contacts End */