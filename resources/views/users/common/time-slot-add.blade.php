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
								<h2 class="section-title !mb-0">Add Time Slots</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.time-slot.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Add Time Slots</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.time-slot.create') }}" class="active"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.time-slot.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form method="post" action="{{ $formSubmitRoute }}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.time-slot.index') }}" data-on-success-msg="Time slot has been updated.">
							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="tour_package" class="form-label form-custom-label">Tour Package <req>*</req></label>
									<select class="form-select form-custom-input" name="tour_package" id="tour_package" required>
										<option value="">Select Tour Package</option>
										@foreach($tourPackages as $tp)
											<option value="{{ $tp->id }}">{{ $tp->name }}</option>
										@endforeach
									</select>
								</div>
							</div>

							<h4 class="font-normal text-gray-600 text-base mb-4">Time Slots</h4>

							<div class="w-full overflow-y-auto">
								<table class="table table-bordered w-full border-collapse">
									<thead><tr>
										<th class="th-custom-css">From Time <req>*</req></th>
										<th class="th-custom-css">To Time</th>
										<th class="th-custom-css">Status</th>
										<td class="th-custom-css"></td>
									</tr></thead>

									<tbody id="tbody-time-slots"></tbody>

									<tfoot id="tmpl-time-slot" class="hidden">
										<tr>
											<td class="td-custom-css whitespace-nowrap td-time-from">
												<div class="flex items-center">
													<input type="hidden" data-name="time_from[]" class="hid-time hid-time-from" value="00:00:00">
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
											<td class="td-custom-css whitespace-nowrap td-time-to">
												<div class="flex items-center">
													<input type="hidden" data-name="time_to[]" class="hid-time hid-time-to">
													<select class="form-select mr-1 form-custom-input sel-time-hour sel-time-to-hour" onchange="setTime(this)" onblur="setTime(this)">
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
												<select data-name="status[]" class="form-select sel-b-status">
													<option>Active</option>
													<option>Inactive</option>
												</select>
											</td>
											<td class="td-custom-css whitespace-nowrap"><button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="$(this).parents('tr').remove()"><i class="fa fa-times" aria-hidden="true"></i></button></td>
										</tr>
									</tfoot>
								</table>
							</div>

							<div class="mt-3"><button type="button" class="btn btn-info btn-info-small" onclick="addTimeSlot()"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add More Time Slot</button></div>

							<div class="mt-3 flex justify-end">
								<button class="btn btn-main btn-primary-small">Add</button>
								<button type="reset" class="btn btn-main btn-warning ml-3 btn-warning-small">Reset Inputs</button>
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
	var action = 'Add';
	var uType = '{{ session("userType") }}';
	@if($uTypeAdmin)
		var getPackagesUrl = "{{ route($routePrefix . '.get-packages', [ 'tourOperatorId' => 0 ]) }}";
	@endif
</script>

<script src="{{ asset('js/user-common/time-slot-add-edit.js') }}"></script>

@if($uTypeAdmin)
	<x-admin-js />
	<script src="{{ asset('js/admin/get-packages-of-tour-op.js') }}"></script>
@endif
@endsection