@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
	@if($errors->any())
		@include('layouts._errors')
	@endif

	@if(session('message'))
		@include('layouts._message')
	@endif

<div class="datatable datatable-default datatable-bordered datatable-loaded">
	<table class="table table-bordered table-hover" id="datatable">
		<thead>
			<tr>
	@if($uTypeAdmin)
				<th>Tour Operator</th>
	@endif
				<th>Tour Package</th>
				<th>Slot Date</th>
				<th>Slot Time</th>
				<th>Customer Name</th>
				<th>Booking Status</th>
				<th>Action</th>
			</tr>
		</thead>
		
		<tbody></tbody>
	</table>
</div>
@endsection

@section('css-libraries')
	@if(App::environment('local'))
<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif
@endsection

@section('css-custom')
<style>
.status-Booked, .status-Completed, .status-Cancelled { font-weight: bold; }
.status-Booked { color: var(--bs-warning); }
.status-Completed { color: var(--bs-success); }
.status-Cancelled { color: var(--bs-danger); }
</style>
@endsection

@section('js-libraries')
	@if(App::environment('local'))
<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	@endif
@endsection

@section('js-custom')
<script>
var dtbl = null;

$(document).ready(function () {
	dtbl = $('#datatable').DataTable({
		processing: true,
		serverSide: true,
		responsive: true,
		ajax: {
			method: 'POST',
			url: "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.index-ahr') }}",
		},
		order: [
	@if($uTypeAdmin)
			[2, 'desc'], [3, 'desc'], [4, 'desc'], [5, 'asc']
	@else
			[1, 'desc'], [2, 'desc'], [3, 'desc'], [4, 'asc']
	@endif
		],
		columns: [
	@if($uTypeAdmin)
			{
				data: 'tour_operator',
				name: 't_o.name'
			},
	@endif
			{	data: 'tour_package',
				name: 'tp.name'
			},
			{
				data: 'date',
				name: 'tsd.date',
				render: formatDate
			},
			{
				data: 'time_from',
				name: 'tst.time_from',
				render: formatTime
			},
			{
				data: 'customer',
				name: 'c.name',
				className: 'fw-bold'
			},
			{
				data: 'status',
				name: 'tbm.status',
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
</script>

<x-delete-record-js-file />
@endsection
