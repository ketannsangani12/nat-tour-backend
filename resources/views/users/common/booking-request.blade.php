@extends('layouts.default-2')

@php
$routePrefix = routePrefix();
$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">

	<div class="main-content w-full container">

		<div class="row flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg overflow-y-auto">
					<h2 class="section-title">Manager Request</h2>
					<table class="table table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left" id="table-1">
						<thead>
							<tr>
								<th class="w-[120px] th-custom-css">Staff Name</th>
								<th class="w-[300px] th-custom-css">Order ID</th>
								<th class="w-[300px] th-custom-css">Bookings</th>
								<th class="w-[300px] th-custom-css">Customer Name</th>
								<th class="w-[300px] th-custom-css">Request Date and Time</th>
								<th class="w-[300px] th-custom-css">Request</th>
								<th class="w-[120px] th-custom-css">Action</th>
							</tr>
						</thead>
						<tbody>
							@foreach($managerRequests as $managerRequest)
							<tr>
								<td>{{ $managerRequest->name }}</td>
								<td>{{ $managerRequest->order_id}}</td>
								<td>{{ $managerRequest->bookings}}</td>
								<td>{{ $managerRequest->customer_name}}</td>
								<td> {{\Carbon\Carbon::parse($managerRequest->created_at)->format('m-d-y g:i A')}}</td>
								<td>{{ $managerRequest->request_type}}</td>
								<td class=" text-center td-custom-css whitespace-nowrap">
									<a class="btn btn-sm btn-primary btn-warning-small" onclick="showRequestInfo({{ $managerRequest->id }})">Info</a>
									<a class="btn btn-sm btn-success btn-primary-small" onclick="approveRequest({{ $managerRequest->id }})">Approve</a>
									<a class="btn btn-sm btn-danger btn-danger-small" onclick="deleteRequest({{ $managerRequest->id }})">Deny</a>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="row flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6  overflow-y-auto">
					<h2 class="section-title">Past Request</h2>

					<table class="table table-bordered table-hover table-sm w-full border-collapse min-w-[800px] text-left" id="table-2">
						<thead>
							<tr>
								<th class="w-[200px] th-custom-css">Staff Name</th>
								<th class="w-[150px] th-custom-css">Order ID</th>
								<th class="w-[150px] th-custom-css">Bookings</th>
								<th class="w-[300px] th-custom-css">Request Date and Time</th>
								<th class="w-[150px] th-custom-css">Request</th>
								<th class="w-[150px] th-custom-css">Status</th>
								<th class="w-[100px] th-custom-css">Manager</th>
								<th class="w-[120px] th-custom-css">Action</th>
							</tr>
						</thead>
						<tbody>
							@foreach($pastManagerRequests as $pastManagerRequest)
							<tr class="align-middle normal-booking hover:bg-[#F3F4F6]">
								<td class="td-custom-css">{{ $pastManagerRequest->name }}</td>
								<td class="td-custom-css">{{ $pastManagerRequest->order_id}}</td>
								<td class="td-custom-css">{{ $pastManagerRequest->bookings}}</td>
								<td class="td-custom-css"> {{\Carbon\Carbon::parse($pastManagerRequest->created_at)->format('m-d-y g:i A')}}</td>
								<td class="td-custom-css">{{ $pastManagerRequest->request_type}}</td>
								<td class="td-custom-css"> {{ $pastManagerRequest->status }}</td>
								<td class="td-custom-css"> {{ $pastManagerRequest->manager_name }}</td>
								<td class="td-custom-css">
									<a class="btn btn-sm btn-primary btn-warning-small" onclick="showRequestInfo({{ $pastManagerRequest->id }})">Info</a>
								</td>
							</tr>
							@endforeach
						</tbody>
					</table>
				</div>
			</div>
		<div>
	</div> {{-- /.main-content --}}
</div> {{-- /.page-content --}}

<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="bookingRequestInfo" tabindex="-1" role="dialog" aria-modal="true">
	<div class="modal-dialog modal-xl max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h4 class="modal-title text-lg font-semibold text-[#111827]">Manager Request Details</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
			</div>

			<div class="modal-body py-7 px-5">
				<div class="contact-section" id="requestDetails"></div>
				<div class="mt-4" id="bookingsInfo"></div>
			</div>
		</div>
	</div>
</div>
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
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
@else
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
@endif
@endsection
@section('js-custom')
<script>
	var showRequestInfoUrl = "{{route(routePrefix() . '.booking-request-info',':id')}}";
	var bookingRequestDenyUrl = "{{route(routePrefix() . '.booking-deny')}}";
	var bookingRequestApproveUrl = "{{route(routePrefix() . '.booking-approve')}}";
</script>
<script src="{{ asset('js/user-common/manager-booking-request.js') }}"></script>
@endsection