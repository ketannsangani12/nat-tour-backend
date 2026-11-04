@extends('layouts.default')

@section('content')
<div class="card">
	<div class="card-body">
	@if($errors->any())
		@include('layouts._errors')
	@endif

	@if(session('message'))
		@include('layouts._message')
	@endif

		<table class="table table-bordered table-hover" id="datatable">
			<thead>
				<tr>
					<th>Booking #</th>
					<th>Tour Operator</th>
					<th>Tour Package</th>
					<th>Tour Date</th>
					<th>Tour Time</th>
					<th>Booking Status</th>
					<th>Liability Waiver</th>
					<th></th>
				</tr>
			</thead>
			
			<tbody></tbody>
		</table>
	</div>
</div>
@endsection

@section('styles')
	@if(App::environment('local'))
<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif

<style>
.status-Booked, .status-Completed, .status-Cancelled { font-weight: bold; }
.status-Booked { color: var(--bs-warning); }
.status-Completed { color: var(--bs-success); }
.status-Cancelled { color: var(--bs-danger); }
</style>
@endsection

@section('scripts')
	@if(App::environment('local'))
<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	@endif

<script>
$(document).ready(function () {
	var table = $('#datatable').DataTable({
		processing: true,
		serverSide: true,
		responsive: true,
		ajax: {
			method: 'POST',
			url: "{{ route('customer.tour-bookings-ahr') }}",
		},
		order: [ [3, 'desc'], [4, 'desc'], [5, 'desc'], [6, 'asc'] ],
		columns: [
			{
				data: 'id',
				name: 'tbm.id'
			},
			{
				data: 'tour_operator',
				name: 't_o.name'
			},
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
				data: 'status',
				name: 'tbm.status',
				className: 'text-center'
			},
			{
				data: 'liability_waiver_link',
				className: 'text-center',
				orderable: false,
				searchable: false
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
@endsection
