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
					<div class="content-title">
						<h2 class="section-title">{{$page_title}}</h2>
					</div>
					<div class="main-content">
						<div class="card-body">
							<form method="post" enctype="multipart/form-data" action="{{ route($routePrefix . '.add-blackout-date') }}" id="blackoutDateForm">
								@csrf
								<div class="flex flex-wrap -mx-3">
									<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
										<label for="caption" class="form-label form-custom-label">Desciption <req>*</req></label>
										<input class="form-control form-custom-input" name="description" id="description" maxlength="100" placeholder="Description" required>
									</div>
									<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
										<label for="txt-from_date" class="form-label form-custom-label">From Date <req>*</req></label>
										<input name="from_date" id="txt-from_date" class="form-control form-custom-input" value="{{ date('m/d/Y') }}"  placeholder="Enter from date" required>
									</div>
									<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
										<label for="txt-to_date" class="form-label form-custom-label">To Date <req>*</req></label>
										<input name="to_date" id="txt-to_date" class="form-control form-custom-input" value=""  placeholder="Enter to date" required>
									</div>
									<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
										<label class="max-[767px]:hidden form-custom-label">&nbsp;</label>
										<button class="btn btn-md btn-primary w-full btn-primary-small">Add</button>
									</div>
								</div>
							</form>

							<div class="w-full overflow-y-auto">
								<table class="table table-bordered table-hover w-full border-collapse mt-3 mb-0 tbl-price">
									<thead>
										<tr>
											<th class="th-custom-css">Desciption</th>
											<th class="th-custom-css">From date</th>
											<th class="th-custom-css">To date</th>
											<th class="px-4 py-2.5 bg-gray-50 text-black text-sm text-center border border-[#E5E7EB] !shadow-none whitespace-nowrap font-medium">Action</th>
										</tr>
									</thead>
									<tbody style="vertical-align: middle;">
										@if ($blackoutDates->count())
										@foreach ($blackoutDates as $blackoutDate)
										<tr>
											<td class="td-custom-css"> {{$blackoutDate->description}} </td>
											<td class="td-custom-css"> {{$blackoutDate->from_date}} </td>
											<td class="td-custom-css"> {{$blackoutDate->to_date}} </td>
											<td class="td-custom-css text-center whitespace-nowrap">
												<button class="btn btn-sm btn-edit mr-2 btn-primary-small" data-bs-toggle="modal" data-description="{{$blackoutDate->description}}" data-from_date="{{$blackoutDate->from_date}}" data-id="{{$blackoutDate->id}}" data-to_date="{{$blackoutDate->to_date}}" data-bs-target="#editBlackoutDate">
													<i class="fa fa-edit mr-2"></i>
													Edit
												</button>
												<button data-url="{{ route($routePrefix . '.delete-blackout-date', [ 'id' => $blackoutDate->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
													<i class="far fa-trash-alt mr-2"></i>
													Delete
												</button>
											</td>
										</tr>
										@endforeach
										@else
										<tr>
											<td class="td-custom-css" colspan="6" align="center">
												No Blackout Dates Found.
											</td>
										</tr>
										@endif
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="editBlackoutDate" tabindex="-1" role="dialog" aria-labelledby="exampleModalLongTitle" aria-hidden="true">
						<div class="modal-dialog modal-fullscreen max-w-[480px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center" role="document">
							<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
								<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
									<h5 class="modal-title text-lg font-semibold text-[#111827]" id="exampleModalLongTitle">Edit Blackout Date</h5>
									<button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
								</div>
								<form method="post" enctype="multipart/form-data" class="form-pckg-relation-grp" action="{{ route($routePrefix . '.update-blackout-date') }}" id="editBlackoutDateForm">
									@csrf
									<input type="hidden" name="id" value="" id="blackout-date-id">
									<div class="modal-body p-4">
										<div class="container-fluid px-3">
											<div class="flex flex-wrap -mx-3 mb-3">
												<div class="w-full max-w-full px-3">
													<label for="description-modal" class="form-label form-custom-label">Description <req>*</req></label>
													<input type="text" class="form-control form-custom-input" name="description" id="description-modal" maxlength="100" placeholder="Description" required>
												</div>
											</div>

											<div class="flex flex-wrap -mx-3">
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="txt-from_date-modal" class="form-label form-custom-label">From Date <req>*</req></label>
													<input type="text" name="from_date" id="txt-from_date-modal" class="form-control form-custom-input" value="{{ date('m/d/Y') }}" placeholder="Enter from date" required>
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="txt-to_date-modal" class="form-label form-custom-label">To Date <req>*</req></label>
													<input type="text" name="to_date" id="txt-to_date-modal" class="form-control form-custom-input" placeholder="Enter to date" required>
												</div>
											</div>
										</div>
									</div>
									<div class="modal-footer px-4 pb-4 flex justify-center">
										<button type="button" class="btn btn-secondary mr-2 btn-outline-small" data-bs-dismiss="modal">Close</button>
										<button class="btn btn-md btn-primary btn-primary-small">Update</button>
									</div>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>	
@endsection

@section('css-libraries')
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
@endsection

@section('js-libraries')
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="{{ asset('js/admin/blackout-dates.js?v=44') }}"></script>
@endsection

@section('js-custom')
<x-delete-record-js-file />
@endsection