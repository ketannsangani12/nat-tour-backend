// ((0?[1-9])|(1[012])):[0-5][0-9] [AaPp][Mm]
var $dateFrom = null;
var $dateTo = null;

$(function() {
	// Add, Update
		$('#txt-date').datepicker({
			altField: '#hid-date',
			altFormat: 'yy-mm-dd',
			dateFormat: 'mm/dd/yy',
			minDate: 0,
		});

	if(action != 'View'){
		if(action == 'Add')
			addTimeSlot();
		else // Update
			fillTimeSlots();
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

	var selStatus = tmpl.getElementsByClassName('sel-b-status')[0];
	selStatus.name = selStatus.getAttribute('data-name');

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

function validateAndSubmitForm(form) {
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

	if(timeSlot != null){
		$newRow = $(addTimeSlot());
		
		if(action == 'Update')
			$newRow.find('.hid-time-slot-id').val(timeSlot.id);

		timeFrom = timeSlot.time_from.split(':');
		h = hour(timeFrom[0]);
		$newRow.find('.hid-time-from').val(timeSlot.time_from);
		$newRow.find('.sel-time-from-hour').val(h);
		$newRow.find('.sel-time-from-minute').val(timeFrom[1]);
		$newRow.find('.sel-time-from-am-pm').val(timeFrom[0] < 12 ? 'am' : 'pm');

		if(timeSlot.time_to != null) {
			timeTo = timeSlot.time_to.split(':');
			h = hour(timeTo[0]);
			$newRow.find('.hid-time-to').val(timeSlot.time_to);
			$newRow.find('.sel-time-to-hour').val(h);
			$newRow.find('.sel-time-to-minute').val(timeTo[1]);
			$newRow.find('.sel-time-to-am-pm').val(timeTo[0] < 12 ? 'am' : 'pm');
		}
		$newRow.find('.sel-b-status').val(timeSlot.status);
	}
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

function clearToTime(btn) {
	$(btn)
		.parents('td')
		.find('.hid-time-to, .sel-time-to-hour, .sel-time-to-minute, .sel-time-to-am-pm')
		.val('');
}
