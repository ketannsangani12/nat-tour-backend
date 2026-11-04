@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
	$routePrefix = routePrefix();
	$date = explode('-', $tourSlot->date);
	$date = $date[1] . "/{$date[2]}/" . $date[0];
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
								<h2 class="section-title !mb-0">Edit Tour Slots</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.slot.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
								<a class="btn-primary-small" href="{{ route($routePrefix . '.slot.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Edit Tour Slots</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.slot.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a href="{{ route($routePrefix . '.slot.bulk-edit-time-slot') }}"><i class="fas fa-minus sub-nav-icon"></i> Bulk Edit</a>
						<span class="sub-nav-sep">|</span>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.slot.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<div class="card">
							<nav class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-y-auto mb-5" id="nav-tab" role="tablist">
								<button type="button" class="nav-item nav-link text-sm font-medium text-[#64748B] py-1.5 px-6 rounded-lg active" id="nav-slot-date" data-bs-toggle="tab" data-bs-target="#tab-slot-date" role="tab" aria-controls="tab-slot-date" aria-selected="true">Date Slot</button>
								<button type="button" class="nav-item nav-link text-sm font-medium text-[#64748B] py-1.5 px-6 rounded-lg" id="nav-slot-time" data-bs-toggle="tab" data-bs-target="#tab-slot-time" role="tab" aria-controls="tab-slot-time" aria-selected="true">Time Slots</button>
							</nav>

							<div class="tab-content">
								<div class="tab-pane active" id="tab-slot-date" role="tabpanel" aria-labelledby="tab-slot-date">
									<div class="card-body">
										<form class="form" method="post" action="{{ route($routePrefix . '.slot.update', [ 'slot' => $tourSlot->id ]) }}" onsubmit="return validateAndSubmitForm(this)" data-on-success-msg="Tour slot updated">
											@method('PUT')
											<div class="flex flex-wrap -mx-3">
												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-2">
													<label for="tour_package" class="form-label form-custom-label">Tour Package <req>*</req></label>
													<select class="form-select form-custom-input" name="tour_package" id="tour_package" required>
														@if($uTypeAdmin)
														<option value="">Select Tour Package</option>
														@endif

														@foreach($tourPackages as $tp)
														<option value="{{ $tp->id }}" {{ $tourSlot->tour_package_id == $tp->id ? 'selected' : '' }}>{{ $tp->name }}</option>
														@endforeach
													</select>
												</div>
											
												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-2">
													<label for="txt-date" class="form-label form-custom-label">Date <req>*</req></label>
													<input name="txt-date" id="txt-date" class="form-control form-custom-input" value="{{ $date }}" placeholder="Enter tour date" readonly required>
													<input type="hidden" name="date" id="hid-date" value="{{ $tourSlot->date }}">
												</div>

												{{-- <div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-2">
													<label for="seats" class="form-label form-custom-label">Seats Per Time Slots <req>*</req></label>
													<input type="number" class="form-control" name="seats" id="seats" min="1" max="999999" placeholder="Enter total number of seats" value="{{ $tourSlot->seats }}" required>
												</div> --}}
											
												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-2">
													<label for="status" class="form-label form-custom-label">Status</label>
													<select class="form-select form-custom-input" name="status" id="status">
														<option {{ $tourSlot->status == 'Active' ? 'selected' : '' }}>Active</option>
														<option {{ $tourSlot->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
													</select>
												</div>
												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-2">
													<label class="form-label form-custom-label">&nbsp;</label>
													<button class="btn btn-main btn-primary-small !block">Update</button>
												</div>
											</div>
										</form>
									</div>{{-- /.card-body --}}
								</div>{{-- /#tab-slot-date.tab-pane --}}

								<div class="tab-pane" id="tab-slot-time" role="tabpanel" aria-labelledby="tab-slot-time">
									<div class="card-body">
										<table class="table table-bordered w-full border-collapse text-left">
											<thead><tr>
												<th class="th-custom-css">From Time <req>*</req></th>
												<th class="th-custom-css">To Time</th>
												<th class="th-custom-css">Seats</th>
												<th class="th-custom-css">Booking Status</th>
												<td class="th-custom-css"><b>Hidden</b></td>
												<td class="th-custom-css"><button type="button" class="btn btn-primary btn-sm btn-primary-small" onclick="addUpdateTimeSlots()">Save All</button></td>
												<td class="th-custom-css"><button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="deleteallTimeSlot()">Delete All</button></td>
											</thead>

											<tbody id="tbody-time-slots"></tbody>

											<tfoot id="tmpl-time-slot" class="d-none">
												<tr>
													<td class="td-custom-css td-time-from">
														<div class="flex items-center">
															<input type="hidden" data-name="time_slot_id[]" class="hid-time-slot-id">
															<input type="hidden" data-name="time_from[]" class="hid-time hid-time-from">
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
															<select class="form-select sel-time-to-hour sel-time-hour mr-1 form-custom-input" onchange="setTime(this)" onblur="setTime(this)">
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
															<button type="button" class="btn btn-warning btn-sm ml-4 btn-warning-small" title="clear" onclick="clearToTime(this)"><i class="fa fa-minus-circle"></i></button>
														</div>
													</td>
													<td class="td-custom-css">
														<input type="number" class="form-control time-slot-seats  form-custom-input" data-name="time_slot_seats[]" id="time-slot-seats" value="0" min="0" max="999999" placeholder="Enter total number of seats">
													</td>
													<td class="td-custom-css">
														<select class="form-select sel-bookable-status form-custom-input">
															<option>Open</option>
															<option>Only by Phone</option>
															<option>Closed</option>
														</select>
													</td>
													<td class="td-custom-css align-middle" style="text-align: center;">
														<input type="hidden" name="hide_slot[]" value="0" />
														<input class="hide-slot w-4 h-4 rounded" name="hide[]" type="checkbox" value="0" onchange="this.value = this.checked ? this.previousElementSibling.value = 1 : this.previousElementSibling.value == 0;" />
													</td>
													<td class="td-custom-css text-center align-middle"><button type="button" class="btn btn-primary btn-sm btn-primary-small" onclick="addUpdateTimeSlot(this)" title="Add / update time slot">Save</button></td>
													<td class="td-custom-css text-center align-middle"><button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="deleteTimeSlot(this)"><i class="fa fa-times" aria-hidden="true"></i></button></td>
												</tr>
											</tfoot>
										</table>

										<div class="mt-3"><button type="button" class="btn btn-info btn-info-small" onclick="addTimeSlot()"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add More Time Slot</button></div>
									</div>{{-- /.card-body --}}
								</div>{{-- /#tab-slot-time.tab-pane --}}
							</div>{{-- /.tab-content --}}
						</div>{{-- /.card --}}
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
	<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
@endsection

@section('css-custom')
	<style>
	.sel-time-from-hour, .sel-time-from-minute { text-align: center; }
	</style>
@endsection

@section('js-libraries')
	@if(App::environment('local'))
	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	@else
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	@endif
@endsection

@section('js-custom')
	<script>
	var action = 'Update';
	var tourSlotId = {{ $tourSlot->id }};
	var timeSlots = @json($timeSlots);
	var addUpdateTimeSlotUrl = "{{ route($routePrefix . '.slot.add-update-time-slot') }}";
	var addUpdateTimeSlotsUrl = "{{ route($routePrefix . '.slot.add-update-time-slots') }}";
	var deleteTimeSlotUrl = "{{ route($routePrefix . '.slot.delete-time-slot', [ 'timeSlotId' => 0 ]) }}";
	var deleteallTimeSlotUrl = "{{ route($routePrefix . '.slot.delete-time-slot-all') }}";

	@if($uTypeAdmin)
	var getPackagesUrl = "{{ route($routePrefix . '.get-packages', [ 'tourOperatorId' => 0 ]) }}";
	@endif
	</script>

	<script src="{{ asset('js/user-common/tour-slot-add-edit.js') }}"></script>

	@if($uTypeAdmin)
	<script src="{{ asset('js/admin/get-packages-of-tour-op.js') }}"></script>
	@endif
@endsection