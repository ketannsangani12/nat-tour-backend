$(function () {
	$(document).ready(function () {
		$("#txt-date_from").datepicker({
			format: 'yyyy-mm-dd',
			todayHighlight: true,
			autoclose: true,
			todayBtn: true,
			startDate: new Date()
		});

		dtbl = $('#datatable').DataTable({
			processing: true,
			serverSide: true,
			responsive: true,
			bDestroy: true,
			bPaginate: false,
			ajax: {
				method: 'POST',
				url: customerListIndexAhr,
			},
			order: [[1, 'asc'], [2, 'asc']], // Shift order index by 1 because of checkbox column
			columns: [
				{
					data: 'id', // or any unique identifier
					orderable: false,
					searchable: false,
					render: function (data, type, row) {
						return `<input type="checkbox" class="row-checkbox" value="${data}">`;
					}
				},
				{
					data: 'name',
					name: 'name'
				},
				{
					data: 'phone_number',
					name: 'phone_number',
				},
				{
					data: 'email',
					name: 'email',
				},
				{
					data: 'action',
					className: 'text-center',
					orderable: false,
					searchable: false
				},
			]
		});
	});

	$("#btnSearch").click(function () {
		var date_from = $("#txt-date_from").val();
		var requestFilter = {
			date_from: date_from,
		};

		var from = new Date(date_from);
		var fromdd = String(from.getDate()).padStart(2, '0');
		var frommm = String(from.getMonth() + 1).padStart(2, '0'); //January is 0!
		var fromyyyy = from.getFullYear();

		from = fromyyyy + '-' + frommm + '-' + fromdd;

		$('#select-all').prop('checked', false);
		localStorage.setItem("requestFilter", JSON.stringify(requestFilter));
		var url = searchUrl;

		$.getJSON(url.replace('_FROM_DATE_', from))
			.done(function (data) {
				$("#datatable").css("display", "inline-table");
				$('#datatable').empty();
				$(function () {
					dtbl = $('#datatable').DataTable({
						processing: true,
						serverSide: true,
						responsive: true,
						bDestroy: true,
						bPaginate: false,
						ajax: {
							method: 'GET',
							url: url.replace('_FROM_DATE_', from),
						},
						columns: [
							{
								data: 'id', // or any unique identifier
								orderable: false,
								searchable: false,
								render: function (data, type, row) {
									return `<input type="checkbox" class="row-checkbox" value="${data}">`;
								}
							},
							{
								data: 'name',
								name: 'name'
							},
							{
								data: 'phone_number',
								name: 'phone_number',
							},
							{
								data: 'email',
								name: 'email',
							},
							{
								data: 'action',
								className: 'text-center',
								orderable: false,
								searchable: false
							},
						]
					});
				});
			});
	});
});

$(document).on("click", "#select-all", function (evt) {
	var isChecked = $(this).prop("checked");

	var rows = dtbl.rows({ 'search': 'applied' }).nodes();
	$('input[type="checkbox"].row-checkbox', rows).prop('checked', isChecked);
});

$(document).on("click", "#datatable .send-email", function (evt) {
	var id = $(evt.target).data('id');
	showLoading();
	$.ajax({
		url: sendEmailToCustomerUrl,
		data: { 'id': id },
		type: 'POST',
		success: function (result) {
			if (result == "Email sent") {
				swal('Success!', 'Email has been sent to the customer.', 'success');
			}
		},
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
	}).always(hideLoading);
});

function bulkSendEmail() {
	let selectedIds = [];
	$('.row-checkbox:checked').each(function () {
		selectedIds.push($(this).val());
	});

	if (selectedIds.length == 0) {
		swal('No Customers Selected', 'Please select at least one customer before proceeding.', 'warning');
		return false;
	}

	showLoading();
	$.ajax({
		url: sendEmailToCustomerUrl,
		data: { 'id': selectedIds },
		type: 'POST',
		success: function (result) {
			if (result == "Email sent") {
				swal('Success!', 'Emails successfully sent to the selected customers.', 'success');
			}
		},
	}).fail(function (jqXHR) {
		hideLoading();
		showSubmissionError(jqXHR);
	}).always(hideLoading);
}
