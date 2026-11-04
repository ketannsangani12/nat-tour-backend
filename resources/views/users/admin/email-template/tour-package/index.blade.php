@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="content-title text-center md:text-left">
                                <h2 class="section-title !mb-0">Email Templates</h2>
                            </div>
                        </div>
						@php
							$routePrefix = routePrefix();
						@endphp
                        <div class="w-full md:w-1/2 max-w-full px-3">
                            <div class="sub-nav flex items-center justify-center md:justify-end">
                                @if(session('userType') === 'Admin' || Session::get('roleNames')->contains("id", "2"))
								<a class="btn-warning-small" href="{{ route($routePrefix . '.email-template.create', ['tour_package' => $tourPackage->id]) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
								@endif
                            </div>
                        </div>
                    </div>

					<!-- <div class="content-title">
						<h2 class="section-title">Email Templates</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						@if(session('userType') === 'Admin' || Session::get('roleNames')->contains("id", "2"))
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.email-template.create', ['tour_package' => $tourPackage->id]) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						@endif
						<a class="btn-outline-primary-small active" href="{{ route($routePrefix . '.email-template.index', ['tour_package' => $tourPackage->id]) }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($errors->any())
						@include('layouts._errors')
						@endif

						@if(session('message'))
						@include('layouts._message')
						@endif

						<table class="table table-bordered table-hover w-full border-collapse text-left" id="datatable">
							<thead>
								<tr>
									<th class="th-custom-css">Name</th>
									<th class="th-custom-css">Subject</th>
									<th class="th-custom-css">Created By</th>
									<th class="th-custom-css">Created From</th>
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
<script src="{{ asset('js/admin/tour-package-email-template.js') }}" data-url="{{ route($routePrefix . '.email-template.index-ahr', ['tour_package' => $tourPackage->id]) }}">
</script>
@endsection

@section('js-custom')
<x-delete-record-js-file />
@endsection