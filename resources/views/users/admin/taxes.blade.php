@extends('layouts.default-2')
@php
	$routePrefix = routePrefix();
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
								<h2 class="section-title !mb-0">Taxes</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-warning-small" href="{{ route($routePrefix . '.tax.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Taxes</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.tax.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.tax.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($errors->any())
							@include('layouts._errors')
						@endif

						@if(session('message'))
							@include('layouts._message')
						@endif

						<div class="w-full overflow-y-auto">
							<table class="table table-bordered table-hover w-full border-collapse" id="datatable">
								<thead>
									<tr>
										<th class="th-custom-css">Country</th>
										<th class="th-custom-css">State</th>
										<th class="th-custom-css">Tax</th>
										<th class="th-custom-css">Tax Value</th>
										<th class="th-custom-css">Value Type</th>
										<th class="th-custom-css">Status</th>
										<th class="th-custom-css">Action</th>
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

$(function() {
	dtbl = $('#datatable').DataTable({
		processing: true,
		serverSide: true,
		responsive: true,
		ajax: {
			method: 'POST',
			url: "{{ route($routePrefix . '.tax.index-ahr') }}",
		},
		order: [ [0, 'asc'], [1, 'asc'], [2, 'asc'] ],
		columns: [
			{
				data: 'country',
				name: 'c.name',
				className: 'td-custom-css'
			},
			{
				data: 'state',
				name: 's.name',
				className: 'td-custom-css'
			},
			{
				data: 'tax_name',
				name: 't.name',
				className: 'td-custom-css fw-bold'
			},
			{
				data: 'value',
				name: 't.value',
				className: 'td-custom-css'
			},
			{
				data: 'value_type',
				name: 't.value_type',
				className: 'td-custom-css'
			},
			{
				data: 'status',
				name: 't.status',
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
</script>

<x-delete-record-js-file />
@endsection
