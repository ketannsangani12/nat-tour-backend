@extends('layouts.default-2')

@php
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
								<h2 class="section-title !mb-0">Bulk Edit Tour Slots</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="mr-2 btn-warning-small" href="{{ route(prefixedRouteName('tour-operator.slot.create')) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
								<a class="btn-primary-small" href="{{ route(prefixedRouteName('tour-operator.slot.index')) }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Bulk Edit Tour Slots</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route(prefixedRouteName('tour-operator.slot.create')) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a href="{{ route(prefixedRouteName('tour-operator.slot.bulk-edit-time-slot')) }}" class="active"><i class="fas fa-minus sub-nav-icon"></i> Bulk Edit</a>
						<span class="sub-nav-sep">|</span>
						<a class="btn-outline-primary-small" href="{{ route(prefixedRouteName('tour-operator.slot.index')) }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form method="post" action="javascript:void(0);" id="bulk-edit-time-slot" data-on-success-msg="Time slots saved." data-redirect-on-success="{{ route(prefixedRouteName('tour-operator.slot.index')) }}">
							<div class="flex flex-wrap -mx-3 mb-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="tour_package" class="form-label form-custom-label">Tour Package <req>*</req></label>
									<select class="form-select form-custom-input" name="tour_package" id="tour_package" required>
										@if(count($tourPackages))
										<option value="">Select Tour Package</option>
										@foreach($tourPackages as $tp)
										<option value="{{ $tp->id }}">{{ $tp->name }}</option>
										@endforeach
										@elseif($uTypeAdmin)
										<option value="">Select a Tour Operator First</option>
										@endif
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="date-range" class="form-label form-custom-label">Date Range <req>*</req><sub>(For this time frame, all previous time slots will be quietly erased.)</sub></label>
									<input type="text" name="date_range" id="date_range" class="form-control form-custom-input" placeholder="Enter date range" required>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="seats" class="form-label form-custom-label">Seats Per Below Time Slots <req>*</req></label>
									<input type="number" class="form-control form-custom-input" name="seats" id="seats" value="1" min="1" max="999999" placeholder="Enter total number of seats" required>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3" id="days-block">
									<label for="days" class="form-label form-custom-label">Days </label>
									<select class="form-select select2-days form-custom-input" id="days" name="days[]" multiple>
										<option value="1">Monday</option>
										<option value="2">Tuesday</option>
										<option value="3">Wednesday</option>
										<option value="4">Thursday</option>
										<option value="5">Friday</option>
										<option value="6">Saturday</option>
										<option value="7">Sunday</option>
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option>Active</option>
										<option>Inactive</option>
									</select>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="operation_type" class="form-label form-custom-label">Operation Type</label>
									<select class="form-select form-custom-input" name="operation_type" id="operation_type" required>
										<option value="">Select Operation Type</option>
										<option value="Update Seats">Update Seats</option>
										<option value="Update Time Slots">Update Time Slots</option>
										<option value="Update Seats & Time Slots">Update Seats & Time Slots</option>
									</select>
								</div>
							</div>

							<div class="mt-3" id="time_slots" style="display: none;">
								<h4 class="section-title mb-2">Time Slots</h4>
								<div class="w-full overflow-y-auto">
									<table class="table table-bordered w-full border-collapse">
										<thead><tr>
											<th class="th-custom-css">From Time <req>*</req></th>
											<th class="th-custom-css">To Time</th>
											<th class="th-custom-css">Seats</th>
											<th class="th-custom-css">Bookable Status</th>
											<td class="th-custom-css"></td>
										</tr></thead>

										<tbody id="tbody-time-slots"></tbody>

										<tfoot id="tmpl-time-slot" class="d-none" >
											<tr>
												<td class="td-custom-css td-time-from">
													<div class="flex items-center">
														<input type="hidden" data-name="time_from[]" class="hid-time hid-time-from" value="00:00:00">
														<select class="form-select sel-time-hour sel-time-from-hour mr-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
															@for($a = 1; $a < 12; $a++)
															<option>{{ $a }}</option>
															@endfor
															<option value="0">12</option>
														</select>:<select class="form-select sel-time-minute sel-time-from-minute ml-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
															@for($a = 0; $a < 60; $a++)
															<option>{{ sprintf('%02d', $a) }}</option>
															@endfor
														</select>
														<select class="form-select sel-time-am-pm sel-time-from-am-pm ml-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
															<option>am</option>
															<option>pm</option>
														</select>
													</div>
												</td>
												<td class="td-custom-css td-time-to">
													<div class="flex items-center">
														<input type="hidden" data-name="time_to[]" class="hid-time hid-time-to">
														<select class="form-select sel-time-hour sel-time-to-hour mr-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
															<option></option>
															@for($a = 1; $a < 12; $a++)
															<option>{{ $a }}</option>
															@endfor
															<option value="0">12</option>
														</select>:<select class="form-select sel-time-minute sel-time-to-minute ml-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
															<option></option>
															@for($a = 0; $a < 60; $a++)
															<option>{{ sprintf('%02d', $a) }}</option>
															@endfor
														</select>
														<select class="form-select sel-time-am-pm sel-time-to-am-pm ml-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
															<option></option>
															<option>am</option>
															<option>pm</option>
														</select>
													</div>
												</td>
												<td class="td-custom-css">
													<input type="number" class="form-control time-slot-seats form-custom-input" data-name="time_slot_seats[]" id="time-slot-seats" value="0" min="0" max="999999" placeholder="Enter total number of seats">
												</td>
												<td class="td-custom-css">
													<select data-name="bookable_status[]" class="form-select sel-bookable-status form-custom-input">
														<option>Open</option>
														<option>Only by Phone</option>
														<option>Closed</option>
													</select>
												</td>
												<td class="td-custom-css text-center"><button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="$(this).parents('tr').remove()">X</button></td>
											</tr>
										</tfoot>
									</table>
								</div>

								<div class="mt-3"><button type="button" class="btn btn-info btn-info-small" onclick="addTimeSlot()"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add More Time Slot</button></div>
							</div>
							<div class="mt-3 text-end">
								<button class="btn btn-main btn-primary-small mr-2" onclick="return bulkEditTimeSlot(this)">Update</button>
								<button type="reset" class="btn btn-main btn-warning btn-light-small">Reset Inputs</button>
							</div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
	@if(App::environment('local'))
	<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
	@else
	<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
	@endif
    <link href="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css" rel="stylesheet" type="text/css" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endsection

@section('js-libraries')
	@if(App::environment('local'))
	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	@else
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	@endif
	<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
	<script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('js-custom')
<script>
	var action = 'BulkEdit';
	var uType = '{{ session("userType") }}';
	var formSubmitRoute = '{{ $formSubmitRoute }}';
	@if($uTypeAdmin)
	var getPackagesUrl = "{{ route('admin.tour-operator.get-packages', [ 'tourOperatorId' => 0 ]) }}";
	@endif
</script>

<script src="{{ asset('js/user-common/tour-slot-add-edit.js') }}"></script>

@if($uTypeAdmin)
<x-admin-js />
<script src="{{ asset('js/admin/get-packages-of-tour-op.js') }}"></script>
@endif

@endsection
