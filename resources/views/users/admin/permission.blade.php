@extends('layouts.default-2')
@php
	$routePrefix = routePrefix();
@endphp
@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Permissions</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.permission.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.permission.index') }}" class="active"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div>

					<div class="main-content">
						@if($errors->any())
							@include('layouts._errors')
						@endif

						@if(session('message'))
							@include('layouts._message')
						@endif
						<div class="w-full overflow-y-auto">
							<table class="table table-bordered table-hover" id="datatable">
								<thead>
									<tr>
										<th class="th-custom-css">Name</th>
										<th class="th-custom-css">Description</th>
										<!-- <th class="th-custom-css">Status</th> -->
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
			url: "{{ route($routePrefix . '.permission.index-ahr') }}",
		},
		// order: [ [1, 'asc'], [0, 'asc'] ],
		columns: [
			{
				data: 'name',
				className: 'td-custom-css fw-bold'
			},
			{
				data: 'description',
				className: 'td-custom-css'
			},
			// {
			// 	data: 'status',
			// 	className: 'td-custom-css text-center'
			// },
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
