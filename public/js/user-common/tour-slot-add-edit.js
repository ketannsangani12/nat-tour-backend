// ((0?[1-9])|(1[012])):[0-5][0-9] [AaPp][Mm]
var $dateFrom = null;
var $dateTo = null;

$(function() {

	//action is not defined do nothing.
	if( !('action' in window) ){
		console.log("landing page does not have action var set");
		return false;
	}

	if(action == 'Repeat') {
		var dateFormat = 'mm/dd/yy';

		function getDate(el) {
			var date;

			try {
				date = $.datepicker.parseDate(dateFormat, el.value);
			}
			catch(err) {
				date = null;
			}

			return date;
		}

		$dateFrom = $('#txt-date_from').datepicker({
			altField: '#hid-date_from',
			altFormat: 'yy-mm-dd',
			dateFormat: dateFormat,
			minDate: 0,
		});

		$dateTo = $('#txt-date_to').datepicker({
			altField: '#hid-date_to',
			altFormat: 'yy-mm-dd',
			dateFormat: dateFormat,
			minDate: 0,
		});

		$dateFrom.on('change', function() {
			$dateTo.datepicker('option', 'minDate', getDate(this));
		});

	} else { // Add, Update
		$('#txt-date').datepicker({
			altField: '#hid-date',
			altFormat: 'yy-mm-dd',
			dateFormat: 'mm/dd/yy',
			minDate: 0,
		});
	}

	if(action == 'Add' || action == 'BulkEdit')
		addTimeSlot();
	else // Update, Repeat
		fillTimeSlots();

	if(  $(".select2-days").length > 0 ){

		$('.select2-days').select2({
			placeholder: "Select a days",
			allowClear: true,
			closeOnSelect: false
		});

		$('#days-block .select2-selection, #days-block .select2-search__field').css({'min-height':'26px'});

	}

});

function clearDateTo() {
	$dateTo.datepicker('setDate', null);
	$('#hid-date_to').val('');
	$('#txt-date_from').datepicker('option', 'maxDate', null);
}

function addTimeSlot() {
	var tmpl = $('#tmpl-time-slot')[0].children[0].cloneNode(true);
	tmpl.id = '';
	var txts = tmpl.getElementsByClassName('hid-time');

	for(var i = 0; i < txts.length; i++)
		txts[i].setAttribute('name', txts[i].getAttribute('data-name'));

	var selStatus = tmpl.getElementsByClassName('sel-bookable-status')[0];
	var timeslotseats = tmpl.getElementsByClassName('time-slot-seats')[0];
	selStatus.name = selStatus.getAttribute('data-name');
	timeslotseats.name = timeslotseats.getAttribute('data-name');

	$('#tbody-time-slots').append(tmpl);
	return tmpl;
}

function setTime(sel) {
	$sel = $(sel);
	var $td = $sel.parents('td');
	var $h = $td.find('.sel-time-hour');
	var $m = $td.find('.sel-time-minute');
	var $ap = $td.find('.sel-time-am-pm');
	var h = $h.val();
	var m = $m.val();
	var ap = $ap.val();

	if(h !== '')
		h = parseInt(h);

	if($sel.hasClass('sel-time-hour')) { // hour changed
		if(h !== '') {
			if(m === '')
				$m.val('00');

			if(ap === '')
				$ap.val('am');
		}
	}
	else if($sel.hasClass('sel-time-minute')) { // minute changed
		if(m !== '') {
			if(h === '')
				$h.val(0);

			if(ap === '')
				$ap.val('am');
		}
	}
	else { // if($sel.hasClass('sel-time-am-pm')) { // am-pm changed
		if(ap !== '') {
			if(h === '')
				$h.val(0);

			if(m === '')
				$m.val('00');
		}
	}

	if(ap == 'pm' && h !== '')
		h += 12;

	if(h === '' || m === '' || ap === '')
		$td.find('.hid-time').val('');
	else
		$td.find('.hid-time').val((h < 10 ? '0' : '') + h + ':' + m + ':00');
}

function bulkEditTimeSlot(form) {
	if ($('#tour_package').val().length == 0) {
		swal('Please select a tour package.', '', 'warning');
		return;
	}
	var operationType = $('#operation_type option:selected').val();
	if(operationType==''){
		swal('Please select Operation Type.', '', 'warning');
		return;
	}
	var title = '';
	var buttonText = '';
    if(operationType=='Update Seats'){
       title = 'Are you sure to update seats for time slots?';
       buttonText = 'Yes, update & continue!';
	}else{
	   title = 'Are you sure to delete the previous time slots?';
	   buttonText = 'Yes, delete & continue!';
	}
	swal({
		title: title,
		text: '',
		icon: "warning",
		buttons: {
			no: {
				text: 'No, cancel!',
				value: false,
			},
			yes: {
				text: buttonText,
				className: 'btn-danger',
			}
		}
	}).then(function(result) {
		if (!result) {
			return;
		}

		$('#bulk-edit-time-slot').attr('action', formSubmitRoute);

		// Get the form element
		const form = document.getElementById("bulk-edit-time-slot");
		validateAndSubmitForm(form);
	});
}

function validateAndSubmitForm(form) {
	if(action == 'Repeat') {
		if($('#hid-date_to').val() < $('#hid-date_from').val()) {
			swal('"From Date" can not be greater than "To Date".', '', 'warning');
			return false;
		}
	}

	$rows = $('#tbody-time-slots').children();

	if(!$rows.length) {
		swal('Please specify at least one time slot.', '', 'warning');
		return false;
	}

	var $row = null;

	for(var a = 0; a < $rows.length; a++) {
		$row = $($rows[a]);

		if(!validateTimeTo($row))
			break;
	}

	return submitForm(form);
}

function validateTimeTo($row) {
	var h = $row.find('.sel-time-to-hour').val();
	var m = $row.find('.sel-time-to-minute').val();
	var ap = $row.find('.sel-time-to-am-pm').val();

	if(! ( (h === '' && m === '' && ap === '') || (h !== '' && m !== '' && ap !== '') ) ) {
		// ! (all blank OR all filled)
		swal('Please select proper "To Time".', '', 'warning')
			.then(function() {
				$row.find('.sel-time-to-hour').focus();
			});

		return false;
	}

	// whether from-time is greater than to-time isn't checked intentionally as
	// to-time may be of the next day

	return true;
}

function fillTimeSlots() {
	var $newRow = null, timeFrom = null, timeTo = null, h = null;

	function hour(hr) {
		var h = parseInt(hr);

		if(h > 12)
			h -= 12;

		if(h == 12)
			h = 0;

		return h;
	}

	for(var i = 0; i < timeSlots.length; i++) {
		$newRow = $(addTimeSlot());

		if(action == 'Update')
			$newRow.find('.hid-time-slot-id').val(timeSlots[i].id);

		timeFrom = timeSlots[i].time_from.split(':');
		h = hour(timeFrom[0]);
		$newRow.find('.hid-time-from').val(timeSlots[i].time_from);
		$newRow.find('.sel-time-from-hour').val(h);
		$newRow.find('.sel-time-from-minute').val(timeFrom[1]);
		$newRow.find('.sel-time-from-am-pm').val(timeFrom[0] < 12 ? 'am' : 'pm');

		if(timeSlots[i].time_to != null) {
			timeTo = timeSlots[i].time_to.split(':');
			h = hour(timeTo[0]);
			$newRow.find('.hid-time-to').val(timeSlots[i].time_to);
			$newRow.find('.sel-time-to-hour').val(h);
			$newRow.find('.sel-time-to-minute').val(timeTo[1]);
			$newRow.find('.sel-time-to-am-pm').val(timeTo[0] < 12 ? 'am' : 'pm');
		}

		$newRow.find('.sel-bookable-status').val(timeSlots[i].bookable_status);
		$newRow.find('.time-slot-seats').val(timeSlots[i].time_slot_seats);
		$newRow.find('.hide-slot').val(timeSlots[i].hide_slot);
		if(timeSlots[i].hide_slot == 1) {
			$newRow.find('.hide-slot').prop('checked', true);
		} else {
			$newRow.find('.hide-slot').prop('checked', false);
		}
		
	}
}

function addUpdateTimeSlot(btn) {
	$tr = $(btn).parents('tr');

	if(!validateTimeTo($tr))
		return;

	showLoading();
	$hidTimeSlotId = $tr.find('.hid-time-slot-id');

	$.ajax({
		method: 'post',
		url: addUpdateTimeSlotUrl,
		data: {
			tour_slot_id: tourSlotId,
			time_slot_id: $hidTimeSlotId.val(),
			time_from: $tr.find('.hid-time-from').val(),
			time_to: $tr.find('.hid-time-to').val(),
			time_slot_seats: $tr.find('.time-slot-seats').val(),
			bookable_status: $tr.find('.sel-bookable-status').val(),
			hide_slot: $tr.find('.hide-slot').val(),
		}
	})
		.done(function(response) {
			swal('Time slot saved.', '', 'success');
			$hidTimeSlotId.val(response.timeSlotId);
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function addUpdateTimeSlots() {
	var $rows = $('#tbody-time-slots').children();
	var $row = null, timeSlots = [];
	for(var a = 0; a < $rows.length; a++) {
		$row = $($rows[a]);

		if(!validateTimeTo($row))
			return;

		timeSlots[a] = {
			time_slot_id: $row.find('.hid-time-slot-id').val(),
			time_from: $row.find('.hid-time-from').val(),
			time_to: $row.find('.hid-time-to').val(),
			bookable_status: $row.find('.sel-bookable-status').val(),
			time_slot_seats: $row.find('.time-slot-seats').val(),
			hide_slot: $row.find('.hide-slot').val(),
		};
	}

	showLoading();

	$.ajax({
		method: 'post',
		url: addUpdateTimeSlotsUrl,

		data: {
			tour_slot_id: tourSlotId,
			time_slots: timeSlots
		}
	})
		.done(function(response) {
			if(response.timeSlotIds.length == $rows.length) {
				swal('Time slots saved.', '', 'success');

				for(var a = 0; a < $rows.length; a++)
					$($rows[a]).find('.hid-time-slot-id').val(response.timeSlotIds[a]);
			} else {
				swal('Some time slots might have not been saved.', 'The page will refresh to reflect updated data.', 'warning')
					.then(function() {
						showLoading();
						location = location; // location.reload() keeps data in form which isn't desirable
					});
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
}

function deleteTimeSlot(btn) {
	var timeSlotId = $(btn).parents('tr').find('.hid-time-slot-id').val();

	if(typeof timeSlotId == 'undefined' || timeSlotId == '')
		$(btn).parents('tr').remove();
	else {
		swal({
			title: 'Are you sure to delete the time slot?',
			text: '',
			icon: "warning",
			buttons: {
				no: {
					text: 'No, cancel!',
					value: false,
				},
				yes: {
					text: 'Yes, delete!',
					className: 'btn-danger',
				}
			}
		}).then(function(result) {
			if(!result)
				return;

			showLoading();

			$.ajax({
				type: 'POST',
				url: deleteTimeSlotUrl + timeSlotId,
				data: { _method: 'DELETE' }
			})
				.done(function() {
					swal('Time slot deleted', '', 'success');
					$(btn).parents('tr').remove();
				})
				.fail(showSubmissionError)
				.always(hideLoading);
		});

		return;
	}
}

function deleteallTimeSlot(btn) {
var $rows = $('#tbody-time-slots').children();
// var current_index = $("#nav-slot-time").tabs('index');

	if(typeof timeSlots == 'undefined' || timeSlots == '')

		$(btn).parents('tr').remove();
	else {
		swal({
			title: 'Are you sure to delete the time slot?',
			text: '',
			icon: "warning",
			buttons: {
				no: {
					text: 'No, cancel!',
					value: false,
				},
				yes: {
					text: 'Yes, delete!',
					className: 'btn-danger',
				}
			}
		}).then(function(result) {
			if(!result)
				return;

			showLoading();

			var $rows = $('#tbody-time-slots').children();
	var $row = null, timeSlots = [];

	for(var a = 0; a < $rows.length; a++) {
		$row = $($rows[a]);

		if(!validateTimeTo($row))
			return;

		timeSlots[a] = {
			time_slot_id: $row.find('.hid-time-slot-id').val(),
			time_from: $row.find('.hid-time-from').val(),
			time_to: $row.find('.hid-time-to').val(),
			bookable_status: $row.find('.sel-bookable-status').val(),
			time_slot_seats: $row.find('.time-slot-seats').val()
		};
	}

	showLoading();

	$.ajax({
		method: 'delete',
		url: deleteallTimeSlotUrl,
		data: {
			tour_slot_id: tourSlotId,
			time_slots: timeSlots
		}
	})
		.done(function(response) {
			if(response.timeSlotIds.length == $rows.length) {
				swal('Time slots deleted.', '', 'success').then(function() {
					showLoading();
					location = location;
				});

				for(var a = 0; a < $rows.length; a++)
					$($rows[a]).find('.hid-time-slot-id').val(response.timeSlotIds[a]);
			} else {
				swal('Some time slots might have not been deleted.', 'The page will refresh to reflect updated data.', 'warning')
					.then(function() {
						showLoading();
						location = location; // location.reload() keeps data in form which isn't desirable
					});
			}
		})
		.fail(showSubmissionError)
		.always(hideLoading);
		});

		return;
	}
}

function clearToTime(btn) {
	$(btn)
		.parents('td')
		.find('.hid-time-to, .sel-time-to-hour, .sel-time-to-minute, .sel-time-to-am-pm')
		.val('');
}

const rednderDateRangePicker = () => {
	const start = moment();
	const end = moment().add(31, 'days');
    const setStartAndEndDate = (start, end) => {
        $('#date_range').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
    }

    $('#date_range').daterangepicker({
        startDate: start,
        endDate: end,
		linkedCalendars: false,
		ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, setStartAndEndDate);

    setStartAndEndDate(start, end);
}

$(document).ready(()=>{

	if(document.querySelector("#date_range")){
		rednderDateRangePicker();
	}

	$('#operation_type').change(function() {
		var operation_type = $(this).val();
		if(operation_type=='Update Seats'){
		  $('#time_slots').hide();
		}else{
			$('#time_slots').show();
		}

	});
});
