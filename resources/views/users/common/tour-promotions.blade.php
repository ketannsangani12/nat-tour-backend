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
								<h2 class="section-title !mb-0">Promotions</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-warning-small" href="{{ route($routePrefix . '.promotion.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
								<!-- <a class="btn-outline-primary-small active" href="{{ route($routePrefix . '.promotion.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a> -->
							</div>
						</div>
					</div>
					<div class="main-content">
						@if($errors->any())
							@include('layouts._errors')
						@endif

						@if(session('message'))
							@include('layouts._message')
						@endif

						<h5 class="text-base font-semibold text-gray-700 mb-3">Active Promotions</h5>
						<table class="table table-bordered table-hover w-full border-collapse text-left" id="datatable-active">
							<thead>
								<tr>
									<th class="th-custom-css">Tour Package</th>
									<th class="th-custom-css">Promo. Code</th>
									<th class="th-custom-css">Start Date</th>
									<th class="th-custom-css">End Date</th>
									<th class="th-custom-css">Status</th>
									<th class="th-custom-css">Action</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>

		<div class="flex flex-wrap -mx-3 mt-6">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg overflow-y-auto">
					<div class="main-content">
						<h5 class="text-base font-semibold text-gray-700 mb-3">Expired Promotions</h5>
						<table class="table table-bordered table-hover w-full border-collapse text-left" id="datatable-inactive">
							<thead>
								<tr>
									<th class="th-custom-css">Tour Package</th>
									<th class="th-custom-css">Promo. Code</th>
									<th class="th-custom-css">Start Date</th>
									<th class="th-custom-css">End Date</th>
									<th class="th-custom-css">Status</th>
									<th class="th-custom-css">Action</th>
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
	<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
	<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif
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
var dtblActive = null;
var dtblInactive = null;

var promotionColumns = [
	{
		data: 'tour_package',
		name: 't_p.name',
		className: 'td-custom-css'
	},
	{
		data: 'code',
		name: 'tp.code',
		className: 'td-custom-css fw-bold'
	},
	{
		data: 'start_date',
		name: 'tp.start_date',
		render: formatDate,
		className: 'td-custom-css'
	},
	{
		data: 'end_date',
		name: 'tp.end_date',
		render: formatDate,
		className: 'td-custom-css'
	},
	{
		data: 'status',
		name: 'tp.status',
		className: 'td-custom-css text-center'
	},
	{
		data: 'action',
		className: 'td-custom-css text-center',
		orderable: false,
		searchable: false
	},
];

$(function() {
	dtblActive = $('#datatable-active').DataTable({
		processing: true,
		serverSide: true,
		responsive: true,
		ajax: {
			method: 'POST',
			url: "{{ route($routePrefix . '.promotion.index-ahr') }}",
			data: { status: 'Active' }
		},
		order: [ [1, 'desc'], [2, 'desc'] ],
		columns: promotionColumns
	});

	dtblInactive = $('#datatable-inactive').DataTable({
		processing: true,
		serverSide: true,
		responsive: true,
		ajax: {
			method: 'POST',
			url: "{{ route($routePrefix . '.promotion.index-ahr') }}",
			data: { status: 'Inactive' }
		},
		order: [ [1, 'desc'], [2, 'desc'] ],
		columns: promotionColumns
	});
});
</script>

<x-delete-record-js-file />
@endsection
