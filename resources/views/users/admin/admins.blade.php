@extends('layouts.default-2')
@php
$routePrefix = routePrefix();
$userRole = userRole();
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
									<h2 class="section-title !mb-0">Administrators</h2>
								</div>
							</div>
							<div class="w-full md:w-1/2 max-w-full px-3">
								<div class="sub-nav flex items-center justify-center md:justify-end">
									@if ($userRole == 'super-admin' || $userRole == 'admin')
									<a class="btn-warning-small" href="{{ route($routePrefix . '.admin-user.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
									@endif
								</div>
							</div>
						</div>

						<!-- <div class="content-title">
							<h2 class="section-title">Administrators</h2>
						</div>

						<div class="sub-nav flex items-center justify-end mb-8">
							@if ($userRole == 'super-admin' || $userRole == 'admin')
							<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.admin-user.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							@endif
							<a href="{{ route($routePrefix . '.admin-user.index') }}" class="btn-outline-primary-small active"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
						</div> -->

						<div class="main-content overflow-y-auto">
							@if($errors->any())
								@include('layouts._errors')
							@endif

						@if(session('message'))
						@include('layouts._message')
						@endif

						<table class="table table-bordered table-hover w-full border-collapse" id="datatable">
							<thead>
								<tr>
									<th class="th-custom-css border-t-0 font-medium">Name</th>
									<th class="th-custom-css border-t-0 font-medium">Email Address</th>
									<th class="th-custom-css border-t-0 font-medium">Status</th>
									<th class="th-custom-css border-t-0 font-medium">Action</th>
								</tr>
							</thead>

							<tbody></tbody>
						</table>
					</div> {{-- /.main-content --}}
				</div>
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
			processing: false,
			serverSide: false,
			responsive: true,
			ajax: {
				method: 'POST',
				url: "{{ route($routePrefix . '.admin-user.index-ahr') }}",
			},
			order: [
				[0, 'asc'],
				[1, 'asc']
			],
			columnDefs: [{
				"targets": '_all',
				"defaultContent": ""
			}],
			columns: [{
					data: 'name',
					className: 'td-custom-css fw-bold'
				},
				{
					data: 'email',
					className: 'td-custom-css '
				},
				{
					data: 'status',
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