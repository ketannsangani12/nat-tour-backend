@extends('layouts.default-2')

@php
	$routePrefix = routePrefix();
	$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6  overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Tour Slots</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								@can('tour-slots-bulk-manager')
								<a href="{{ route($routePrefix . '.slot.bulk-manager') }}" class="btn-outline-primary-small mr-2"><i class="fas fa-layer-group sub-nav-icon mr-2"></i> Bulk Manager</a>
								@endcan
								<a href="{{ route($routePrefix . '.slot.create') }}" class="btn-warning-small active"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							</div>
						</div>
					</div>
					
					<!-- <div class="content-title">
						<h2 class="section-title">Tour Slots</h2>
					</div> -->

					<div class="main-content">
						@if($errors->any())
							@include('layouts._errors')
						@endif

						@if(session('message'))
							@include('layouts._message')
						@endif

						<div class="flex flex-wrap items-center -mx-3 mb-4">
							<div class="w-full lg:w-1/2 max-w-full px-3">
								<div class="flex flex-wrap -mx-3 mb-4">
									<div class="w-full md:w-1/3 max-w-full px-3">
										<label for="txt-date_from" class="form-label form-custom-label">From Date <req>*</req></label>
										<div class="relative">
											<span class="absolute top-[18px] left-2.5 -translate-y-1/2 z-0"><svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1 align-text-top"><path d="M5.33594 1.83594V4.5026" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10.6641 1.83594V4.5026" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path><path d="M12.6667 3.16406H3.33333C2.59695 3.16406 2 3.76102 2 4.4974V13.8307C2 14.5671 2.59695 15.1641 3.33333 15.1641H12.6667C13.403 15.1641 14 14.5671 14 13.8307V4.4974C14 3.76102 13.403 3.16406 12.6667 3.16406Z" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path><path d="M2 7.16406H14" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path></svg></span>
											<input name="txt-date_from" id="txt-date_from" class="form-control form-custom-input !pl-8 z-10" value="{{ date('m/d/Y') }}"  placeholder="Enter tour date" readonly required>
										</div>
										<input type="hidden" name="date" id="hid-date">
									</div>
									<div class="w-full md:w-1/3 max-w-full px-3">
										<label for="txt-date_to" class="form-label form-custom-label">To Date <req>*</req></label>
										<div class="relative">
											<span class="absolute top-[18px] left-2.5 -translate-y-1/2 z-0"><svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block mr-1 align-text-top"><path d="M5.33594 1.83594V4.5026" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path><path d="M10.6641 1.83594V4.5026" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path><path d="M12.6667 3.16406H3.33333C2.59695 3.16406 2 3.76102 2 4.4974V13.8307C2 14.5671 2.59695 15.1641 3.33333 15.1641H12.6667C13.403 15.1641 14 14.5671 14 13.8307V4.4974C14 3.76102 13.403 3.16406 12.6667 3.16406Z" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path><path d="M2 7.16406H14" stroke="#264f73" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"></path></svg></span>
											<input name="txt-date_to" id="txt-date_to" class="form-control form-custom-input !pl-8 z-10" value=""  placeholder="Enter tour date" readonly required>
										</div>
										<input type="hidden" name="date" id="hid-date">
									</div>
									<div class ="w-full md:w-1/3 max-w-full px-3">
										<label class="form-label form-custom-label">&nbsp;</label>
										<button type="button" class="btn btn-secondary btn-primary-small !block" id="btnSearch" name="btnSearch">Filter</button>
									</div>
								</div>
							</div>
							<div class="w-full lg:w-1/2 max-w-full px-3">
								<!-- <div class="sub-nav flex items-center justify-end">
									<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.slot.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
									<a href="{{ route($routePrefix. '.slot.bulk-edit-time-slot') }}"><i class="fas fa-minus sub-nav-icon"></i> Bulk Edit</a>
									<span class="sub-nav-sep">|</span>
									<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.slot.index') }}" class="active"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
								</div> -->
							</div>
						</div>
						<table class="table table-bordered table-hover w-full" id="datatable">
							<thead>
								<tr>
									<th class="min-w-[200px] th-custom-css text-start">Tour Package</th>
									<th class="min-w-[150px] th-custom-css text-start">Date</th>
									<th class="min-w-[150px] th-custom-css text-start">Status</th>
									<th class="min-w-[200px] th-custom-css text-start" style="width: 300px;">Action</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
	@if(App::environment('local'))
	<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
	<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
	<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
	<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif
	<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
@endsection

@section('css-custom')
<style>
	.btn-repeat { color: var(--fg-green); background-color: var(--bg-green); border: 1px solid; padding-top: 0.15rem; padding-bottom: 0.1rem; }
	.btn-repeat:hover, .btn-repeat:active, .btn-repeat:focus { color: white; background-color: var(--fg-green); }
</style>
@endsection

@section('js-libraries')
	@if(App::environment('local'))
	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
	@endif
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/tour-slot-add-edit.js') }}"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script>
	$(function () {
		var dateFormat = 'yy-mm-dd';
		function getDate(el) {
			var date;
			try {
				date = $.datepicker.parseDate(dateFormat, el.value);
			} catch(err) {
				date = null;
			}

			return date;
		}
		var date2 = new Date();
		var date1= moment(date2, "DD-MM-YYYY").add(7, 'days').format('MM/DD/YYYY');
		$('#txt-date_to').val($('#txt-date_to').val() + date1);

		$(document).ready(function() {
			$( "#txt-date_from" ).datepicker({
				onClose: function() {
					var date2 = $('#txt-date_from').datepicker('getDate');
					date2.setDate(date2.getDate() + 7)
					$( "#txt-date_to" ).datepicker("setDate", date2);
				}
			});

			$( "#txt-date_to" ).datepicker({
				dateFormat: "mm/dd/yy",
			});

			var selectedTourOperator = $("#tour_operator").val();

			dtbl = $('#datatable').DataTable({
				processing: true,
				serverSide: true,
				responsive: true,
				bDestroy: true,
				ajax: {
					method: 'POST',
					url: "{{ route($routePrefix . '.slot.index-ahr') }}" + "/" + selectedTourOperator,
				},
				order: [ [0, 'asc'], [1, 'asc'] ],
				columns: [
					{
						data: 'tour_package',
						name: 'tp.name',
						className: 'td-custom-css'
					},
					{
						data: 'date',
						name: 'tsd.date',
						render: formatDate,
						className: 'fw-bold td-custom-css'
					},
					{
						data: 'status',
						name: 'tsd.status',
						className: 'text-center td-custom-css'
					},
					{
						data: 'action',
						className: 'text-center td-custom-css whitespace-nowrap',
						orderable: false,
						searchable: false
					},
				]
			});
		});

		$("#btnSearch").click(function() {
			var selectedTourOperator = $("#tour_operator").val();
			var date_from = $("#txt-date_from").val();
			var date_to = $("#txt-date_to").val();
			// var bookingsReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.slot.searchdate', [ 'fromDate' => 'date_from', 'toDate' => 'date_from' ]) }}/";
			var requestFilter = {
				date_from: date_from,
				date_to: date_to,
			};

			var from = new Date(date_from);
			var fromdd = String(from.getDate()).padStart(2, '0');
			var frommm = String(from.getMonth() + 1).padStart(2, '0'); //January is 0!
			var fromyyyy = from.getFullYear();

			from = fromyyyy + '-' + frommm + '-' + fromdd;

			var to = new Date(date_to);
			var todd = String(to.getDate()).padStart(2, '0');
			var tomm = String(to.getMonth() + 1).padStart(2, '0'); //January is 0!
			var toyyyy = to.getFullYear();

			to = toyyyy + '-' + tomm + '-' + todd;

			localStorage.setItem("requestFilter", JSON.stringify(requestFilter));
			
			var url = "{{ route($routePrefix . '.slot.searchdate', ['fromdate' => '_FROM_DATE_', 'todate' => '_TO_DATE_', 'tourOpId' => '_TOUR_OP_ID'])}}";
			
			$.getJSON(url.replace('_FROM_DATE_', from).replace('_TO_DATE_', to).replace('_TOUR_OP_ID', selectedTourOperator))
				.done(function(data) {
					$("#datatable").css("display", "inline-table");
					$('#datatable').empty();
					$(function() {
						dtbl = $('#datatable').DataTable({
							processing: true,
							serverSide: true,
							responsive: true,
							bDestroy: true,
							ajax: {
								method: 'GET',
								url:url.replace('_FROM_DATE_', from).replace('_TO_DATE_', to).replace('_TOUR_OP_ID', selectedTourOperator),
							},
							columns: [
							{
								data: 'tour_package',
								name: 'tp.name',
								className: 'td-custom-css'
							},
							{
								data: 'date',
								name: 'tsd.date',
								render: formatDate,
								className: 'td-custom-css fw-bold'
							},
							{
								data: 'status',
								name: 'tsd.status',
								className: 'td-custom-css text-center'
							},
							{
								data: 'action',
								className: 'td-custom-css text-center',
								orderable: false,
								searchable: false
							},
							],
						});
					});
				});
		});

		$('#tour_operator').change(function() {
			var selectedTourOperator = $("#tour_operator").val();
			var dtbl = null;

			$(function() {
				dtbl = $('#datatable').DataTable({
					processing: true,
					serverSide: true,
					responsive: true,
					bDestroy: true,
					ajax: {
						method: 'POST',
						url: "{{ route($routePrefix . '.slot.index-ahr') }}" + "/" + selectedTourOperator,
					},
					order: [ [1, 'desc'], [0, 'asc'] ],
					columns: [
						{
							data: 'tour_package',
							name: 'tp.name',
							className: 'td-custom-css'
						},
						{
							data: 'date',
							name: 'tsd.date',
							render: formatDate,
							className: 'td-custom-css fw-bold'
						},
						{
							data: 'status',
							name: 'tsd.status',
							className: 'td-custom-css text-center'
						},
						{
							data: 'action',
							className: 'td-custom-css text-center',
							orderable: false,
							searchable: false
						},
					]
				});
			});
		});
	});
</script>

<x-delete-record-js-file />
@endsection
