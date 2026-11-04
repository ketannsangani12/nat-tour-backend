@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
	$routePrefix = routePrefix();
	$dateYMD = date('Y-m-d');
	$dateMDY = date('m/d/Y');
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6  overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Repeat Tour Slots</h2>
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
						<h2 class="section-title">Repeat Tour Slots</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-2">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.slot.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a href="{{ route($routePrefix . '.slot.bulk-edit-time-slot') }}"><i class="fas fa-minus sub-nav-icon"></i> Bulk Edit</a>
						<span class="sub-nav-sep">|</span>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.slot.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form class="form" method="post" action="{{ route($routePrefix . '.slot.repeat-slot') }}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.slot.index') }}" data-on-success-msg="Tour slots added">
							<div class="flex flex-wrap -mx-3 mb-2">
								<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-1">
									<label for="tour_package" class="form-label text-sm font-normal text-[#020817] mb-1 inline-block">Tour Package <req>*</req></label>
									<select class="form-select form-custom-input" name="tour_package" id="tour_package" required>
										@foreach($tourPackages as $tp)
										<option value="{{ $tp->id }}"{{ $tp->id == $slotInfo->tour_package_id ? ' selected' : '' }}>{{ $tp->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-1">
									<span class="fw-bold text-sm font-normal text-[#020817] mb-1 inline-block">Repeat for Dates</span>
									<label for="date_from" class="form-label text-sm font-normal text-[#020817] mb-1 inline-block">From <req>*</req></label>
									<input class="form-control form-custom-input" name="txt-date_from" id="txt-date_from" value="{{ $dateMDY }}" placeholder='Select "From Date"' readonly required>
									<input type="hidden" name="date_from" id="hid-date_from" value="{{ $dateYMD }}">
								</div>
								<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-1">
									<label for="date_to" class="form-label text-sm font-normal text-[#020817] mb-1 inline-block">To (Inclusive)</label>
									<div class="flex items-center">
										<input class="form-control form-custom-input" name="txt-date_to" id="txt-date_to" value="{{ $dateMDY }}" placeholder='Select "To Date"' readonly>
										<input type="hidden" name="date_to" id="hid-date_to" value="{{ $dateYMD }}">
										<button type="button" class="btn btn-sm btn-secondary ml-1 btn-danger-small" onclick="clearDateTo()" tabindex="0" title="Clear (Repeat only for one day - Start Date)"><i class="fa fa-times" aria-hidden="true"></i></button>
									</div>
								</div>
								<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-1">
									{{-- <div class="col-12 col-md-6 mb-3">
										<label for="seats" class="form-label text-sm font-normal text-[#020817] mb-1 inline-block">Seats Per Time Slots <req>*</req></label>
										<input type="number" class="form-control form-custom-input" name="seats" id="seats" min="1" max="999999" placeholder="Select date" value="{{ $slotInfo->seats }}" required>
									</div> --}}
										
									<div class="col-12 col-md-6 mb-3">
										<label for="status" class="form-label text-sm font-normal text-[#020817] mb-1 inline-block">Status for All Dates</label>
										<select class="form-select form-custom-input" name="status" id="status">
											<option>Active</option>
											<option>Inactive</option>
										</select>
									</div>
								</div>
							</div>
							<div class="w-full overflow-y-auto">
								<table class="table table-bordered w-full">
									<thead>
										<tr>
											<th class="min-w-[200px] th-custom-css">From Time <req>*</req></th>
											<th class="min-w-[150px] th-custom-css fw-bold">To Time</th>
											<th class="min-w-[100px] th-custom-css fw-bold">Seats</th>
											<th class="min-w-[150px] th-custom-css fw-bold">Booking Status</th>
											<th class="min-w-[100px] th-custom-css fw-bold"><b>Hidden</b></th>
											<th class="min-w-[100px] th-custom-css text-center"></th>
										</tr>
									</thead>

									<tbody id="tbody-time-slots"></tbody>

									<tfoot id="tmpl-time-slot" class="hidden">
										<tr>
											<td class="td-time-from td-custom-css whitespace-nowrap">
												<div class="flex items-center">
													<input type="hidden" data-name="time_from[]" class="hid-time hid-time-from">
													<select class="form-select mr-1 form-custom-input sel-time-hour sel-time-from-hour" onchange="setTime(this)" onblur="setTime(this)">
														@for($a = 1; $a < 12; $a++)
														<option>{{ $a }}</option>
														@endfor
														<option value="0">12</option>
													</select>:<select class="form-select ml-1 form-custom-input sel-time-minute sel-time-from-minute" onchange="setTime(this)" onblur="setTime(this)">
														@for($a = 0; $a < 60; $a++)
														<option>{{ sprintf('%02d', $a) }}</option>
														@endfor
													</select>
													<select class="form-select ml-1 form-custom-input sel-time-am-pm sel-time-from-am-pm" onchange="setTime(this)" onblur="setTime(this)">
														<option>am</option>
														<option>pm</option>
													</select>
												</div>
											</td>
											<td class="td-time-to td-custom-css whitespace-nowrap">
												<div class="flex items-center">
													<input type="hidden" data-name="time_to[]" class="hid-time hid-time-to">
													<select class="form-select mr-1 form-custom-input sel-time-to-hour sel-time-hour" onchange="setTime(this)" onblur="setTime(this)">
														<option></option>
														@for($a = 1; $a < 12; $a++)
														<option>{{ $a }}</option>
														@endfor
														<option value="0">12</option>
													</select>:<select class="form-select ml-1 form-custom-input sel-time-minute sel-time-to-minute" onchange="setTime(this)" onblur="setTime(this)">
														<option></option>
														@for($a = 0; $a < 60; $a++)
														<option>{{ sprintf('%02d', $a) }}</option>
														@endfor
													</select>
													<select class="form-select ml-1 form-custom-input sel-time-am-pm sel-time-to-am-pm" onchange="setTime(this)" onblur="setTime(this)">
														<option></option>
														<option>am</option>
														<option>pm</option>
													</select>
												</div>
											</td>
											<td class="td-custom-css whitespace-nowrap">
												<input type="number" class="form-control time-slot-seats form-custom-input" data-name="time_slot_seats[]" id="time-slot-seats" value="0" min="0" max="999999" placeholder="Enter total number of seats">
											</td>
											<td class="td-custom-css whitespace-nowrap">
												<select data-name="bookable_status[]" class="form-select sel-bookable-status inline-block form-custom-input">
													<option>Open</option>
													<option>Only by Phone</option>
													<option>Closed</option>
												</select>
											</td>
											<td class="align-middle td-custom-css text-center whitespace-nowrap">
												<input type="hidden" name="hide_slot[]" value="0" />
												<input class="hide-slot w-4 h-4 rounded" name="hide[]" type="checkbox" value="0" onchange="this.value = this.checked ? this.previousElementSibling.value = 1 : this.previousElementSibling.value == 0;" />
											</td>
											<td class="align-middle text-center td-custom-css whitespace-nowrap"><button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="deleteTimeSlot(this)"><i class="fa fa-times" aria-hidden="true"></i></button></td>
										</tr>
									</tfoot>
								</table>
							</div>
							
							<div class="flex items-center justify-between mt-3">
								<button type="button" class="btn btn-info btn-info-small" onclick="addTimeSlot()"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add More Time Slot</button>
								<button class="btn btn-main btn btn-info ml-2 btn-primary-small"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add Time Slots</button>
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
	<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
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
	var action = 'Repeat';
	var tourSlotId = {{ $slotInfo->id }};
	var timeSlots = @json($timeSlots);
	</script>

	<script src="{{ asset('js/user-common/tour-slot-add-edit.js') }}"></script>
	@if($uTypeAdmin)
	<script src="{{ asset('js/admin/get-packages-of-tour-op.js') }}"></script>
	@endif
@endsection
