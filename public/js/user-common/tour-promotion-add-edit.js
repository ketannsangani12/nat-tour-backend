var $startDate = null;
var $endDate = null;

$(function () {
	var dateFormat = 'mm/dd/yy';

	function getDate(el) {
		var date;

		try {
			date = $.datepicker.parseDate(dateFormat, el.value);
		} catch (err) {
			date = null;
		}

		return date;
	}

	$startDate = $('#txt-start_date').datepicker({
		altField: '#hid-start_date',
		altFormat: 'yy-mm-dd',
		dateFormat: dateFormat,
		minDate: 0,
	});

	$endDate = $('#txt-end_date').datepicker({
		altField: '#hid-end_date',
		altFormat: 'yy-mm-dd',
		dateFormat: dateFormat,
		minDate: 0,
	});

	$startDate.on('change', function () {
		$endDate.datepicker('option', 'minDate', getDate(this));
	});

	$endDate.on('change', function () {
		$startDate.datepicker('option', 'maxDate', getDate(this));
	});
});

function clearEndDate() {
	$endDate.datepicker('setDate', null);
	$('#hid-end-date').val('');
	$('#txt-start_date').datepicker('option', 'maxDate', null);
}

const rednderDateRangePicker = () => {	
	const start = $("#startdate").val();
	const end = $("#enddate").val();

	if ($("#startdate").val() == "") {
		const start = moment();
	}

	if ($("#enddate").val() == "") {
		const end = moment().add(31, 'days');
	}

	const setStartAndEndDate = (start, end) => {
		$('#date_range').val(start + '-' + end);
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

$(document).ready(() => {
	if (document.querySelector("#date_range")) {
		rednderDateRangePicker();
	}
});

function validateAndSubmitForm(form) {
	var startDt = $('#start_date').val();
	var endDt = $('#end_date').val();

	if (startDt == '' && endDt == '') {
		swal('Please choose either start date or end date.', '', 'info')
		return false;
	}

	if (startDt != '' && endDt != '' && startDt > endDt) {
		swal('Start date can not be greater than end date.', '', 'info')
		return false;
	}

	if (!validateCharacters('#code'))
		return false;

	return submitForm(form);
}
