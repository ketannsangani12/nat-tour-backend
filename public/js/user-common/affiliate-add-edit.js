function validateAndSubmitForm(form) {

	if (window.hasOwnProperty("_PasswordStrength")) {
		if (window._PasswordStrength.getPasswordStrength() !== "strong") {
			window._PasswordStrength.setFocus()
			return false;
		}
	}

	if (!validateEmailAddressFormat())
		return false;

	if (!validateCharacters('#name'))
		return false;

	return submitForm(form);
}
function validateAndSubmitUpdateSettingForm(form) {

	if ($('[name="is_ota"]').is(':checked')) {
		if (!validateOtaAffiliatePercentage())
			return false;

		if (!validateNextYearOtaAffiliatePercentage())
			return false;
	} else {
		if (!validateAffiliateServiceCommissionPercentage())
			return false;

		if (!validateAffiliateStripeCommissionPercentage())
			return false;
	}

	return submitForm(form);
}

function addTimeSlot() {
	// var tmpl = $('#tmpl-time-slot')[0].children[0].cloneNode(true);
	var tmpl = $('#tmpl-time-slot')[0].cloneNode(true);
	tmpl.id = '';
	var txts = tmpl.getElementsByClassName('hid-time');

	for (var i = 0; i < txts.length; i++)
		txts[i].setAttribute('name', txts[i].getAttribute('data-name'));

	var selStatus = tmpl.getElementsByClassName('sel-b-status')[0];
	selStatus.name = selStatus.getAttribute('data-name');

	$('#tbody-time-slots').append(tmpl);
	return tmpl;
}

function fillTimeSlots() {
	var $newRow = null, timeFrom = null, timeTo = null, h = null;

	function hour(hr) {
		var h = parseInt(hr);

		if (h > 12)
			h -= 12;

		if (h == 12)
			h = 0;

		return h;
	}

	if (timeSlots.length > 0) {
		$(".save-affiliate-seats").prop('disabled', false);
		for (var i = 0; i < timeSlots.length; i++) {
			$newRow = $(addTimeSlot());

			if (action == 'Update')
				$newRow.find('.hid-time-slot-id').val(timeSlots[i].time_slot_id);

			timeFrom = timeSlots[i].time_from.split(':');
			h = hour(timeFrom[0]);
			$newRow.find('.hid-time-from').val(timeSlots[i].time_from);
			$newRow.find('.sel-time-from-hour').val(h);
			$newRow.find('.sel-time-from-minute').val(timeFrom[1]);
			$newRow.find('.sel-time-from-am-pm').val(timeFrom[0] < 12 ? 'am' : 'pm');

			if (timeSlots[i].time_to != null) {
				timeTo = timeSlots[i].time_to.split(':');
				h = hour(timeTo[0]);
				$newRow.find('.hid-time-to').val(timeSlots[i].time_to);
				$newRow.find('.sel-time-to-hour').val(h);
				$newRow.find('.sel-time-to-minute').val(timeTo[1]);
				$newRow.find('.sel-time-to-am-pm').val(timeTo[0] < 12 ? 'am' : 'pm');
			}

			$newRow.find('.sel-b-status').val(timeSlots[i].status);
			$newRow.find('.hid-seat-limit').val(timeSlots[i].seats);
			$newRow.find('.hid-affiliate-seat-id').val(timeSlots[i].affiliate_seat_id);
		}
	} else {
		$(".save-affiliate-seats").prop('disabled', true);
	}
}


function addUpdateTimeSlots() {
	var $rows = $('#tbody-time-slots').children();
	var $row = null, timeSlots = [];

	for (var a = 0; a < $rows.length; a++) {
		$row = $($rows[a]);

		timeSlots[a] = {
			affiliate_seat_id: $row.find('.hid-affiliate-seat-id').val(),
			time_slot_id: $row.find('.hid-time-slot-id').val(),
			tour_operator_id: $row.find('.hid-tour-operator').val(),
			affiliate_id: $(".hid-affiliate-id").val(),
			seats: $row.find('.hid-seat-limit').val()
		};
	}

	showLoading();

	$.ajax({
		method: 'post',
		url: addUpdateTimeSlotsUrl,

		data: {
			time_slots: timeSlots
		}
	})
		.done(function (response) {
			if (response.timeSlotIds.length == $rows.length) {
				swal('Time slots saved.', '', 'success');

				for (var a = 0; a < $rows.length; a++)
					$($rows[a]).find('.hid-affiliate-seat-id').val(response.timeSlotIds[a]);
			} else {
				swal('Some time slots might have not been saved.', 'The page will refresh to reflect updated data.', 'warning')
					.then(function () {
						showLoading();
						location = location; // location.reload() keeps data in form which isn't desirable
					});
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function addUpdateTimeSlot(btn) {
	$tr = $(btn).parents('tr');

	showLoading();
	$hidTimeSlotId = $tr.find('.hid-affiliate-seat-id');

	$.ajax({
		method: 'post',
		url: addUpdateTimeSlotUrl,
		data: {
			time_slot_id: $tr.find('.hid-time-slot-id').val(),
			affiliate_seat_id: $tr.find('.hid-affiliate-seat-id').val(),
			affiliate_id: $(".hid-affiliate-id").val(),
			seat_limit: $tr.find('.hid-seat-limit').val()
		}
	})
		.done(function (response) {
			swal('Time slot saved.', '', 'success');
			$hidTimeSlotId.val(response.timeSlotId);
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function addPckgAffTimeSlots() {
	var tmpl = $('#tmpl-time-slot')[0].cloneNode(true);
	tmpl.id = '';

	txts = tmpl.getElementsByClassName('form-control');

	for (var i = 0; i < txts.length; i++) {
		txts[i].setAttribute('name', txts[i].getAttribute('data-name'));

		if (txts[i].className.indexOf('txt-required') != -1)
			txts[i].setAttribute('required', 1);
	}

	var selRateStatus = tmpl.getElementsByClassName('sel-b-status')[0]
	$('#tbody-time-slots').append(tmpl);

	return tmpl;
}
function assignedPackageUpdated(dataObj) {
	$.post(assignPackageUrl, dataObj)
		.done(function () {
			$.notify({
				// options
				title: '<strong>Success</strong>',
				message: "<br>Details updated successfully",
				icon: 'glyphicon glyphicon-ok'
			}, {
				// settings
				element: 'body',
				type: "success",
				allow_dismiss: true,
				showProgressbar: false,
				placement: {
					from: "top",
					align: "right"
				},
				offset: 20,
				spacing: 10,
				z_index: 1031,
				delay: 3300,
				timer: 1000,
				animate: {
					enter: 'animated fadeInDown',
					exit: 'animated fadeOutRight'
				}
			});
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

$('.document-panel .assigned_package').on('change', function () {
	var packageId = $(this).val();
	var value = ($(this).is(":checked")) ? 1 : 0;
	var dataObj = { packageId: packageId, isAssigned: value };
	if(value == 0) {
		$('#'+packageId).val(0);
	}
	assignedPackageUpdated(dataObj);
});
$('.document-panel .show_all_package_bookings').on('change', function () {
	var packageId = $(this).attr('id');
	var value = $(this).val();
	if(!$('#package_'+packageId).is(":checked")){
		swal('Please assign the package first.', '', 'error');
		$(this).val(0);
		return false;
	}
	var dataObj = { packageId: packageId, showAllPackageBookings: value };
	assignedPackageUpdated(dataObj);
});

$(document).ready(function() {
	// Mirror affiliate name into OTA label on Add form
	$('#AddForm [name="name"]').on('input', function() {
		var name = $(this).val().trim();
		$('.ota-affiliate-name-label').text(name ? name + ' ' : '');
	});

	// Toggle OTA commission fields based on Is Reseller(OTA) checkbox
	$('[name="is_ota"]').on('change', function() {
		var isOta = $(this).is(':checked');
		$('.ota-commission-fields, .ota-commission-fields-settings').toggle(isOta);
		$('.pax-limit-fields, .pax-limit-fields-settings').toggle(isOta); // Show min/max pax only for OTA affiliates
		$('.ota-commission-fields input, .ota-commission-fields-settings input').prop('required', isOta);
		$('#affiliate_processing_percentage, #stripe_affiliate_percentage, #next_year_affiliate_processing_percentage, #next_year_stripe_affiliate_percentage').prop('required', !isOta);
		$('label[for="affiliate_processing_percentage"] req, label[for="stripe_affiliate_percentage"] req, label[for="next_year_affiliate_processing_percentage"] req, label[for="next_year_stripe_affiliate_percentage"] req').toggle(!isOta);
		// Hide/show standard commission rows in Settings tab
		$('#UpdateSettingsForm #affiliate_processing_percentage').closest('.flex.flex-wrap.-mx-3').toggle(!isOta);
		$('#UpdateSettingsForm #next_year_affiliate_processing_percentage').closest('.flex.flex-wrap.-mx-3').toggle(!isOta);
		// Clear stale OTA values when OTA is unchecked
		if (!isOta) {
			$('#ota_affiliate_percentage, #ota_affiliate_percentage_settings').val('');
			$('#next_year_ota_affiliate_percentage, #next_year_ota_affiliate_percentage_settings').val('');
		}
	}).trigger('change');
});