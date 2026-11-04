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
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Time Slots</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-warning-small" href="{{ route($routePrefix . '.time-slot.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Time Slots</h2>
					</div>
						</div>
					<div class="w-full md:w-1/2 max-w-full px-3">
					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.time-slot.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.time-slot.index') }}" class="active"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($errors->any())
							@include('layouts._errors')
						@endif

						@if(session('message'))
							@include('layouts._message')
						@endif
						
						<div class="overflow-y-auto">
							<table class="table table-bordered table-hover w-full border-collapse" id="datatable">
								<thead>
									<tr>
										<th class="th-custom-css">Tour Package</th>
										<th class="th-custom-css">From Time</th>
										<th class="th-custom-css" style="width: 73px;">To Time</th>
										<th class="th-custom-css" style="width: 64px;">Status</th>
										<th class="th-custom-css" style="width: 300px;">Action</th>
									</tr>
								</thead>

								<tbody></tbody>
							</table>
						</div>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
	@if(App::environment('local'))
	<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
	<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif
@endsection

<!-- @section('css-custom')
	<style>
	.btn-repeat { color: var(--fg-green); background-color: var(--bg-green); border: 1px solid; padding-top: 0.15rem; padding-bottom: 0.1rem; }
	.btn-repeat:hover, .btn-repeat:active, .btn-repeat:focus { color: white; background-color: var(--fg-green); }
	</style>
@endsection -->

@section('js-libraries')
	@if(App::environment('local'))
	<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
	<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
	@endif
	<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
@endsection

@section('js-custom')
	<script src="{{ asset('js/user-common/time-slot-add-edit.js') }}"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	<script>
		var action = 'View';
		var userType = '{{session('userType')}}';
		var tourOperatorId = '{{ (session('userType')=='Tour Operator')?auth()->user()->tour_operator_id:'' }}';
		$(function () {
			$(document).ready(function(){
				/*if (userType == 'Admin') {*/
					var selectedTourOperator = $("#tour_operator").val();
				/*} else {
					var selectedTourOperator = tourOperatorId;
				}*/
				// Trigger tour packaged based or tour operator
				$('#tour_package option').each(function() {
					var value = $(this).attr('data-id');
					if (value == selectedTourOperator) {
						$(this).css('display', 'inline-block');
					}else{
						$(this).css('display', 'none');
					}
				});

				dtbl = $('#datatable').DataTable({
					processing: true,
					serverSide: true,
					responsive: true,
					bDestroy: true,
					ajax: {
						method: 'POST',
						url: "{{ route($routePrefix . '.time-slot.index-ahr') }}" + "/" + selectedTourOperator,
					},
					order: [ [0, 'asc'], [1, 'desc'] ],
					columns: [
						{
							data: 'tour_package',
							name: 'tp.name',
							className: 'td-custom-css'
						},
						{
							data: 'time_from',
							name: 'tsta.time_from',
							className: 'td-custom-css'
						},
						{
							data: 'time_to',
							name: 'tsta.time_to',
							className: 'td-custom-css'
						},
						{
							data: 'status',
							name: 'tsta.status',
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
			$('#tour_operator').change(function(){
				var selectedTourOperator = $("#tour_operator").val();
				$("#tour_package").prop('selected', false).find('option:first').prop('selected', true);
				$('#tour_package option').each(function() {
					var value = $(this).attr('data-id');
					if (value === selectedTourOperator) {
						$(this).css('display', 'inline-block');
					}else{
						$(this).css('display', 'none');
					}
				});
				var dtbl = null;

				$(function() {
					dtbl = $('#datatable').DataTable({
						processing: true,
						serverSide: true,
						responsive: true,
						bDestroy: true,
						ajax: {
							method: 'POST',
							url: "{{ route($routePrefix . '.time-slot.index-ahr') }}" + "/" + selectedTourOperator,
						},
						order: [ [1, 'desc'], [0, 'asc'] ],
						columns: [
							{
								data: 'tour_package',
								name: 'tp.name',
								className: 'td-custom-css'
							},
							{
								data: 'time_from',
								name: 'tsta.time_from',
								className: 'td-custom-css'
							},
							{
								data: 'time_to',
								name: 'tsta.time_to',
								className: 'td-custom-css'
							},
							{
								data: 'status',
								name: 'tsta.status',
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

			// Display time slots according to tour package selection
			$('#tour_package').change(function(){
				var selectedTourPackage = $("#tour_package").val();
				var dtbll = null;

				$(function() {
					dtbll = $('#datatable').DataTable({
						processing: true,
						serverSide: true,
						responsive: true,
						bDestroy: true,
						ajax: {
							url: "{{ route($routePrefix . '.time-slot.index-package-ahr') }}" + "/" + selectedTourPackage,
							method: 'POST',
						},
						order: [ [1, 'desc'], [0, 'asc'] ],
						columns: [
							{
								data: 'tour_package',
								name: 'tp.name'
							},
							{
								data: 'time_from',
								name: 'tsta.time_from'
							},
							{
								data: 'time_to',
								name: 'tsta.time_to'
							},
							{
								data: 'status',
								name: 'tsta.status',
								className: 'text-center'
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
	</script>

	<x-delete-record-js-file />
@endsection
