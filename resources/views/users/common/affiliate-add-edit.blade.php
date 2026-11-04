@extends('layouts.default-2')

@section('main-content')
@php
$routePrefix = routePrefix();
if(strpos(Route::currentRouteName(), $routePrefix . '.affiliate.create') !== false)
$action = 'Add';
else
$action = 'Update';


if(session('userType') == 'Admin') {
$uTypeAdmin = true;
} else {
$uTypeAdmin = false;
}
$userRole = userRole();
@endphp

<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">{{ $action }} Affiliate</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.affiliate.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">{{ $action }} Affiliate</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						@if ($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.affiliate.create') }}" {!! $action=='Add' ? 'class="active"' : '' !!}><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						@endif
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.affiliate.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($action == 'Add')
						<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="helpModel" tabindex="-1" role="dialog" aria-labelledby="exampleModalLongTitle" aria-hidden="true">
							<div class="modal-dialog modal-lg max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center" role="document">
								<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
									<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
										<h5 class="modal-title text-lg font-semibold text-[#111827]" id="exampleModalLongTitle">HELP</h5>
										<button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
									</div>

									<div class="modal-body">
										<div class="row flex flex-wrap -mx-3">
											<div class="w-full overflow-y-auto">
												<table class="table w-full overflow-y-auto">
													<thead>
														<tr>
															<th class="th-custom-css" scope="col">Name</th>
															<th class="th-custom-css" scope="col">Service Commission Percentage</th>
															<th class="th-custom-css" scope="col">Stripe Commission Percentage</th>
															<th class="th-custom-css" scope="col">Action</th>
														</tr>
													</thead>
													<tbody>
														@foreach($affiliateDetails as $aff)
														<tr>
															<td class="td-custom-css">{{$aff->name}}</td>
															<td class="td-custom-css">{{$aff->affiliate_processing_percentage}}</td>
															<td class="td-custom-css">{{$aff->stripe_affiliate_percentage}}</td>
															<td class="td-custom-css">
																<button class="btn btn-sm btn-edit helpSet" data-bs-toggle="modal" data-affiliate="{{$aff->affiliate_processing_percentage}}" data-stripe="{{$aff->stripe_affiliate_percentage}}" data-target="#helpSet">
																	<i class="fa "></i>
																	Apply
																</button>
															</td>
														</tr>
														@endforeach
													</tbody>
												</table>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						@endif
						@if($action == 'Update')
						<div class="card">
							<nav class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-y-auto" id="nav-tab" role="tablist">
								<button type="button" class="tabs-custom-link active" id="nav-general" data-bs-toggle="tab" data-bs-target="#tab-general" role="tab" aria-controls="tab-general" aria-selected="true">General</button>
								<!-- <button type="button" class="tabs-custom-link" id="nav-rate-groups" data-bs-toggle="tab" data-bs-target="#tab-rate-groups" role="tab" aria-controls="tab-rate-groups" aria-selected="true">Rates</button> -->
								<button type="button" class="tabs-custom-link" id="nav-time-slots" data-bs-toggle="tab" data-bs-target="#tab-time-slots" role="tab" aria-controls="tab-time-slots" aria-selected="true">Time Slots</button>
								<button type="button" class="tabs-custom-link" id="nav-iFrame" data-bs-toggle="tab" data-bs-target="#tab-iFrame" role="tab" aria-controls="tab-iFrame" aria-selected="true">Frontend iFrame</button>
								<button type="button" class="tabs-custom-link" id="nav-packages" data-bs-toggle="tab" data-bs-target="#tab-packages" role="tab" aria-controls=tab-packages" aria-selected="true">Assign Packages</button>
								@if(session('userType') == 'Admin')
								<button type="button" class="tabs-custom-link" id="nav-settings" data-bs-toggle="tab" data-bs-target="#tab-settings" role="tab" aria-controls=tab-packages" aria-selected="true">Settings</button>
								@endif
							</nav>

							<div class="tab-content">
								<div class="tab-pane active" id="tab-general" role="tabpanel" aria-labelledby="tab-general">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										@endif
										<form method="post" action="{{ $formSubmitRoute }}" id="AddForm" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.affiliate.index') }}" data-on-success-msg=@if($action=='Add' ) "Affiliate added"
											@else {{-- update --}} "Affiliate's details updated"
											@endif>

											@if($action == 'Update')
											@method('PUT')
											@endif

											<div class="flex flex-wrap -mx-3">

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="name" class="form-label form-custom-label">{{ session('userType') == 'Admin' ? 'Affiliate ' : '' }}Name <req>*</req></label>
													<input class="form-control form-custom-input" name="name" id="name" maxlength="80" value="{{ $affiliate->name }}" placeholder="Enter name" onblur="validateCharacters('#name')" required>
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="email" class="form-label form-custom-label">Email Address <req>*</req></label>
													<input type="email" name="email" id="email" class="form-control form-custom-input" value="{{ $affiliate->email }}" placeholder="Enter email address" required>
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="phone_number" class="form-label form-custom-label">Phone Number</label>
													<input type="tel" name="phone_number" id="phone_number" class="form-control form-custom-input" value="{{ $affiliate->phone_number }}" placeholder="Enter phone number" data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#phone_number')">
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="password" class="form-label form-custom-label">Password
														@if($action == 'Add')<req>*</req>@endif
														<span class="inline-block" id="password-strength-status" class="">@if($action != 'Add')<small>(Enter only if need to be changed)</small>@endif</span>
													</label>
													<div class="input-group flex items-center">
														<input type="password" class="form-control form-custom-input" name="password" id="password" placeholder="Enter password" @if($action=='Add' ) required @endif>
														<div class="input-group-text ml-1">
															<button type="button" class="btn btn-sm toggle-password btn-info-small" data-toggle="#password"><i class="fa fa-fw fa-eye"></i></button>
														</div>
													</div>
												</div>

												{{--<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="seats" class="form-label form-custom-label">Seats</label>
													<input type="number" name="seats" id="seats" class="form-control form-custom-input" value="{{ $affiliate->seats ?? 0 }}" placeholder="Enter Seats" min="0" data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#seats')">
											</div>--}}
										</div>

										<div><label class="form-label form-custom-label">Block booking duration</label></div>
										<div class="flex flex-wrap -mx-3 mb-3">
											<div class="w-1/2 max-w-full px-3 input-group">
												<input type="number" class="form-control form-custom-input" name="duration" id="duration" value="{{ $affiliate->duration ?? 0 }}" placeholder="Enter day or hours" required>
											</div>

											<div class="w-1/2 max-w-full px-3 input-group">
												<select class="form-select form-custom-input" name="day_hours" id="day_hours">
													<option value="" {{ ($affiliate->day_hours == null) ? "selected" : "" }}>
														Please Select Day Hours
													</option>
													<!-- <option value="1" {{ ($affiliate->day_hours == 1) ? "selected" : "" }}>Day</option> -->
													<option value="2" {{ ($affiliate->day_hours == 2) ? "selected" : "" }} {{ ($affiliate->day_hours == 0) ? "selected" : "" }}>Hours</option>
												</select>
											</div>
										</div>
										@if($action == 'Add')
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="affiliate_processing_percentage" class="form-label form-custom-label"><span style="color: green">{{ date('Y') }}</span> - Service Commission Percentage <req>*</req>  <button type="button" class="btn btn-sm btn-edit" id="help">
														<i class="fa fa-edit"></i>
														Help
													</button>  </label>
												<input class="form-control form-custom-input" name="affiliate_processing_percentage" id="affiliate_processing_percentage" maxlength="80" value="{{ $affiliate->affiliate_processing_percentage }}" placeholder="Enter {{ date('Y') }} service commission percentage" required>
											</div>

											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="stripe_affiliate_percentage" class="form-label form-custom-label"><span style="color: green">{{ date('Y') }}</span> - Stripe Commission Percentage <req>*</req></label>
												<input type="text" name="stripe_affiliate_percentage" id="stripe_affiliate_percentage" class="form-control form-custom-input" value="{{ $affiliate->stripe_affiliate_percentage }}" placeholder="Enter {{ date('Y') }} stripe commission percentage" required>
											</div>
										</div>
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="next_year_affiliate_processing_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - Service Commission Percentage <req>*</req></label>
												<input class="form-control form-custom-input" name="next_year_affiliate_processing_percentage" id="next_year_affiliate_processing_percentage" maxlength="80" value="{{ $affiliate->next_year_affiliate_processing_percentage }}" placeholder="Enter {{ date('Y') + 1 }} service commission percentage" required>
											</div>

											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="next_year_stripe_affiliate_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - Stripe Commission Percentage <req>*</req></label>
												<input type="text" name="next_year_stripe_affiliate_percentage" id="next_year_stripe_affiliate_percentage" class="form-control form-custom-input" value="{{ $affiliate->next_year_stripe_affiliate_percentage }}" placeholder="Enter {{ date('Y') + 1 }} stripe commission percentage" required>
											</div>
										</div>
										<div class="ota-commission-fields flex flex-wrap -mx-3" style="display:none">
											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="ota_affiliate_percentage" class="form-label form-custom-label"><span style="color: green">{{ date('Y') }}</span> - <span class="ota-affiliate-name-label"></span>(OTA) Commission Percentage <req>*</req></label>
												<input class="form-control form-custom-input" name="ota_affiliate_percentage" id="ota_affiliate_percentage" maxlength="80" value="{{ $affiliate->ota_affiliate_percentage }}" placeholder="Enter {{ date('Y') }} OTA commission percentage">
											</div>
											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="next_year_ota_affiliate_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - <span class="ota-affiliate-name-label"></span>(OTA) Commission Percentage <req>*</req></label>
												<input class="form-control form-custom-input" name="next_year_ota_affiliate_percentage" id="next_year_ota_affiliate_percentage" maxlength="80" value="{{ $affiliate->next_year_ota_affiliate_percentage }}" placeholder="Enter {{ date('Y') + 1 }} OTA commission percentage">
											</div>
										</div>
										<div class="pax-limit-fields flex flex-wrap -mx-3" style="display:none">
											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="min_pax_add" class="form-label form-custom-label">Min Pax <small class="text-muted">(Per booking)</small></label>
												<input type="number" name="min_pax" id="min_pax_add" min="1" class="form-control form-custom-input" value="{{ $affiliate->min_pax }}" placeholder="Min. pax per booking">
											</div>
											<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
												<label for="max_pax_add" class="form-label form-custom-label">Max Pax <small class="text-muted">(Per booking)</small></label>
												<input type="number" name="max_pax" id="max_pax_add" min="1" class="form-control form-custom-input" value="{{ $affiliate->max_pax }}" placeholder="Max. pax per booking">
											</div>
										</div>
										@endif
										<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
										<div class="mb-3">
											<label class="form-custom-label flex items-center">
												<input class="w-4 h-4 mr-2" type="checkbox" name="is_subscribed" value="Yes" {{ $affiliate->is_subscribed == 'Yes' ? 'checked' : '' }}>
												<span><b>Is subscribed?</b></span>
											</label>
										</div>

										<div class="mb-3">
											<label class="form-custom-label flex items-center">
												<input class="w-4 h-4 mr-2" type="checkbox" name="is_price_show" value="Yes" {{ $affiliate->is_price_show == 'Yes' ? 'checked' : '' }}>
												<span><b>Show ticket price in receipts?</b></span>
											</label>
										</div>
										@if($setting)
										<div class="mb-3">
											<label class="form-custom-label flex items-center">
												<input class="w-4 h-4 mr-2" type="checkbox" name="is_billable" value="1" {{ $affiliate->is_billable == '1' ? 'checked' : '' }}>
												<span><b>Is Billable?</b></span>
											</label>
										</div>
										@endif
										<div class="mb-3">
											<label class="form-custom-label flex items-center">
												<input class="w-4 h-4 mr-2" type="checkbox" name="is_ota" value="1" {{ $affiliate->is_ota == '1' ? 'checked' : '' }}>
												<span><b>Is Reseller(OTA)?</b></span>
											</label>
										</div>
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
												<label for="expire_in_days" class="form-label form-custom-label">Booking Expire In Days</label>
												<input type="number" class="form-control form-custom-input" name="expire_in_days" id="expire_in_days" min="0" value="{{ $affiliate->expire_in_days }}" placeholder="Enter expire duration in days">
											</div>
											<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
												<label for="status" class="form-label form-custom-label">Status</label>
												<select class="form-select form-custom-input" name="status" id="status">
													<option {{ $affiliate->status == 'Active' ? 'selected' : '' }}>Active</option>
													<option {{ $affiliate->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
												</select>
											</div>

											<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
												<label class="form-label form-custom-label">Department <req>*</req></label>
												<select name="department_id" id="department_id" class="form-select form-custom-input" required> 
													<option disabled selected>Please select</option>
													@foreach($departments as $department)
															<option value="{{ $department->id }}" @if(!empty($affiliate)) {{ ($department->id == $affiliate->department_id) ? 'selected' : '' }} @endif>{{ $department->name }}</option>
													@endforeach
												</select>
											</div>
										
										</div>
										@if (userRole() == 'super-admin' || userRole() == 'admin' || userRole() == 'manager')
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
												<label class="form-label form-custom-label">Roles <req>*</req></label>
												<select name="role" id="role" class="form-select form-custom-input" required> 
													<option disabled selected>Please select</option>
													@foreach($roles as $role)
															<option value="{{ $role->id }}" @if(!empty($modelHasRole)) {{ ($role->id == $modelHasRole->role_id) ? 'selected' : '' }} @endif>{{ $role->display_name }}</option>
													@endforeach
												</select>
											</div>
										</div>
										<div>
											<div>
												<label hidden class="label-permissions form-label form-custom-label">Permissions <req>*</req></label>
											</div>
											<div class="permission-role-wise">
												
											</div>
										</div>
										@endif
										<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">{{ $action }}</button></div>
										</form>

										@if($action == 'Update')
									</div>
								</div>

								<div class="tab-pane" id="tab-rate-groups" role="tabpanel" aria-labelledby="tab-rate-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										@include('layouts._list-package-rate-for')
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-{{ $uTypeAdmin ? '1/2' : 'full' }} max-w-full px-3">
												<div class="select-tour-wrp">
													<select id="tour_package" class="form-select select-tour form-custom-input">
														<option value="">Select Tour Package</option>
														@foreach($tourPackageList as $tp)
														@php
														$affTourPackageId = ((isset($aff_tourPackage) && isset($aff_tourPackage->tour_package_id) && $aff_tourPackage->tour_package_id ))? $aff_tourPackage->tour_package_id : '';
														@endphp
														<option value="{{ $tp->id }}" {{ $tp->id == $affTourPackageId ? 'selected' : '' }}>
															{{ $tp->name }} {{ $tp->id }}
														</option>
														@endforeach
													</select>
													<span class="text-danger error-text tour_package_id_error_" id="tour_package_id_error_"> </span>
												</div>
											</div>
										</div>

										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 lg:w-1/6 max-w-full px-3 mb-3 mt-3">
												<label class="form-label form-custom-label">Scheduling Year</label>
												<select class="form-select form-custom-input" id="scheduling_year" onchange="return updateUrlForYear(this)" required>
													<option value="">Select Scheduling Year</option>
													@for($i = date('Y'); $i < date('Y') + 7; $i++)
														<option {{ request()->year == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
														@endfor
												</select>
											</div>
										</div>
										<div class="overflow-y-auto">
											<table width="100%">
												<tbody id="tbody-pckg-rate-grps"></tbody>
												<tfoot class="hidden">
													<tr id="tmpl-pckg-rate-grp" class="pckg-rate-grp">
														<td class="px-2 py-1.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm">
															<form method="post" class="form-pckg-rate-grp" action="{{ route($routePrefix . '.package.affiliate-add-update-rate') }}" onsubmit="if(!validateCharacters('#form-age-rate-grp-' + this.tour_package + ' .txt-rate-for'))return false;return submitForm(this)" data-on-success-msg="Rate details saved." data-call-on-success="pckgRateGrpSaved">
																<input type="hidden" data-name="tour_package_rate_id" class="form-control hid-rate-id">
																<input type="hidden" data-name="affiliate_id" value="{{$affiliate->id}}" class="form-control hid-affiliate-id">
																<input type="hidden" data-name="tour_package_id" class="form-control hid-package-id">
																<input type="hidden" data-name="scheduling_year" class="form-control sel-year set-year" value="{{ date('Y') }}">

																<table width="100%" class="border border-collapse mb-2">
																	<tr>
																		<th class="th-custom-css min-w-56">Rate For <req>*</req></th>
																		<th class="th-custom-css min-w-28">Age From</th>
																		<th class="th-custom-css min-w-32">Age To</th>
																		<th class="th-custom-css min-w-32">Rate <req>*</req></th>
																		<th class="th-custom-css min-w-32">Permit Fee</th>
																		<th class="th-custom-css min-w-28">Tax</th>
																		<th class="th-custom-css min-w-20"></th>
																		<td rowspan="2" class="th-custom-css font-medium min-w-24 text-center">
																			<button class="btn btn-primary btn-sm btn-save btn-primary-small mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button>
																			<button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="deletePckgRateGrp(this)" title="Remove Package Rate Group">X</button><br>
																		</td>
																	</tr>

																	<tr>
																		<td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" placeholder="e.g.: Children" onblur="validateCharacters('.txt-rate-for')" data-user-display-label="Rate For (Age/Rate Group Title)"></td>
																		<td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from" type="number" value="0" min="0" max="255" placeholder="Age From"></td>
																		<td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to" type="number" value="0" min="0" max="255" placeholder="Age To"></td>
																		<td class="td-custom-css" style="width: 126px">
																			<div class="input-group">
																				<span class="input-group-text">{{ currencyFormation(false, true) }}</span>
																				<input data-name="rate" class="form-control form-custom-input txt-rate txt-required" type="number" value="0" min="0" step="0.01" placeholder="Rate">
																			</div>
																		</td>
																		<td class="td-custom-css" style="width: 126px">
																			<div class="input-group">
																				<span class="input-group-text">{{ currencyFormation(false, true) }}</span>
																				<input data-name="permit_fee" class="form-control form-custom-input txt-permit-fee" type="number" value="0" min="0" step="0.01" placeholder="Permit Fee">
																			</div>
																		</td>
																		<td class="td-custom-css">
																			<div class="input-group">
																				<span class="input-group-text">{{ currencyFormation(false, true) }}</span>
																				<input data-name="additional_charge" class="form-control form-custom-input txt-adnl-chrg" type="number" value="0" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
																			</div>
																		</td>
																		<td class="td-custom-css">
																			<div class="input-group">
																				<input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="0" min="0" max="255" placeholder="Enter minimum people allowed" required>
																			</div>
																		</td>
																		<td class="td-custom-css">
																			<div class="input-group">
																				<input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="0" min="0" max="255" placeholder="Enter maximum people allowed">
																			</div>
																		</td>
																		<td class="td-custom-css">
																		</td>
																	</tr>

																	<tr>
																		<th colspan="2" class="th-custom-css descth">Description</th>
																		<th class="th-custom-css">Order</th>
																		<th class="th-custom-css">Mapping Key <req>*</req></th>
																		<th class="th-custom-css">Margin</th>
																		<th class="th-custom-css">Global Rate</th>
																		<th class="th-custom-css">Status</th>
																	</tr>

																	<tr>
																		<td colspan="2" class="td-custom-css descth">
																			<textarea data-name="description" class="form-control form-custom-input txt-desc" placeholder="Description"></textarea>
																		</td>
																		<td class="td-custom-css">
																			<input data-name="orderby" class="form-control form-custom-input txt-orderby" type="number" value="0" min="0" max="100" placeholder="Order No.">
																		</td>
																		<td class="td-custom-css">
																			<select data-name="mapping_key" class="form-control form-custom-input select-mapping-key" required>
																				<option value="0">Default</option>
																				<option value="1">Adults</option>
																				<option value="2">Children</option>
																				<option value="3">Infant</option>
																				<option value="4">Adults - Military Teachers</option>
																				<option value="5">Children - Military Teachers</option>
																				<option value="6">Native Americans</option>
																				<option value="7">Locals</option>
																				<option value="8">Tour Guides</option>
																				<option value="9">Veteran</option>
																				<option value="10">Senior Citizen</option>
																			</select>
																		</td>
																		<td class="td-custom-css">
																			<div class="input-group">
																				<span class="input-group-text">{{ currencyFormation(false, true) }}</span>
																				<input data-name="margin" class="form-control form-custom-input txt-margin" type="number" value="0" min="0" max="99999.99" step="0.01" placeholder="Margin">
																			</div>
																		</td>
																		<td class="td-custom-css">
																			<select data-name="global_rate" class="form-control form-custom-input select-global-rate">
																				<option value="0">No</option>
																				<option value="1">Yes</option>
																			</select>
																		</td>
																		<td class="td-custom-css">
																			<select data-name="status" class="form-control form-custom-input sel-status">
																				<option>Active</option>
																				<option>Inactive</option>
																			</select>
																		</td>
																		<td class="td-custom-css groupratetd" style="display: none;">
																			<div class="input-group">
																				<input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="0" min="0" max="255" placeholder="Enter minimum people for group">
																			</div>
																		</td>
																		<td class="td-custom-css groupratetd" style="display: none;">
																			<div class="input-group">
																				<input data-name="max_group_size" class="form-control form-custom-input txt-max-group-size" type="number" value="0" min="0" max="255" placeholder="Enter maximum people for group">
																			</div>
																		</td>
																	</tr>
																</table>
															</form>
														</td>
													</tr>
												</tfoot>
											</table>
										</div>

										<div class="mt-2">
											<button type="button" id="btn-add-pckg-rate-group" class="btn btn-info btn-primary-small" onclick="addPckgRateGrp()">Add More Age/Rate Group</button>
										</div>
									</div> {{-- /.card-body --}}
								</div>

								<div class="tab-pane" id="tab-time-slots" role="tabpanel" aria-labelledby="tab-time-slots">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<div class="flex flex-wrap -mx-3">
											<div class="w-full lg:w-5/6 max-w-full px-3 mb-3">
												<div class="flex items-center">
													<div class="select-tour-wrp w-1/2 me-2">
														<!-- {{isset($aff_tourPackage->affiliate_id) ? 'disabled' : ''}} -->
														<select id="aff_tour_package" class="form-select select-tour form-custom-input">
															<option value="">Select Tour Package</option>
															@foreach($tourPackageList as $tp)
															@php
															$affTourPackageId = ((isset($aff_tourPackage_for_timeslots) && isset($aff_tourPackage_for_timeslots->tour_package_id) && $aff_tourPackage_for_timeslots->tour_package_id ))? $aff_tourPackage_for_timeslots->tour_package_id : '';
															@endphp
															<option value="{{ $tp->id }}" {{ $tp->id == $affTourPackageId ? 'selected' : '' }}>
																{{ $tp->name }} {{ $tp->id }}
															</option>
															@endforeach
														</select>
														<span class="text-danger error-text text-sm tour_package_id_error_" id="tour_package_id_error_"> </span>
													</div>
													<div class="">
														<button class="btn btn-secondary btn-primary-small sync me-3"><i class="fas fa-sync"></i></button>
													</div>
													<div class="text-sm">
														<span id="error_msg" style="color: red"></span>
														<span id="success_msg" style="color: green"></span>
													</div>
												</div>
											</div>
										</div>
										<div class="w-full overflow-y-auto">
											<table class="table table-bordered w-full border-collapse">
												<thead>
													<th class="th-custom-css">From Time </th>
													<th class="th-custom-css">To Time</th>
													<th class="th-custom-css">Seats <req>*</req></th>
													<th class="th-custom-css">Status</th>
													<td class="th-custom-css font-medium text-center"><button type="button" class="btn btn-primary btn-sm save-affiliate-seats btn-primary-small" onclick="addUpdateTimeSlots()">Save All</button></td>
												</thead>
												<tbody id="tbody-time-slots"></tbody>
												<tfoot class="hidden">
													<tr>
														<td class="td-custom-css">
															<form method="post" class="form-pckg-aff-slots" action="{{ route($routePrefix . '.package.affiliate-add-update-affiliate-time-slots') }}" onsubmit="if(!validateCharacters('#form-age-rate-grp-' + this.tour_package + ' .txt-rate-for'))return false;return submitForm(this)" data-on-success-msg="Affiliate timeslots saved." data-call-on-success="pckgAffSlotSaved">
																<input type="hidden" data-name="tour_package_rate_id" class="form-control hid-rate-id">
																<input type="hidden" data-name="affiliate_id" value="{{$affiliate->id}}" class="form-control hid-affiliate-id">
																<input type="hidden" data-name="tour_package_id" class="form-control hid-package-id">

																<table width="100%" class="mb-2">
																	<tr id="tmpl-time-slot">
																		<td class="td-custom-css whitespace-nowrap td-time-from">
																			<div class="flex items-center">
																				<input type="hidden" data-name="tour_operator" class="hid-tour-operator" value="{{ \Cookie::get('tourOpId') }}">
																				<input type="hidden" data-name="time_slot_id[]" class="hid-time-slot-id">
																				<input type="hidden" data-name="time_from[]" class="hid-time hid-time-from">
																				<select class="form-select sel-time-hour sel-time-from-hour mr-1 form-custom-input" disabled="">
																					@for($a = 1; $a < 12; $a++)
																						<option>{{ $a }}</option>
																						@endfor
																						<option value="0">12</option>
																				</select>:<select class="form-select sel-time-minute sel-time-from-minute ml-1 form-custom-input" disabled="">
																					@for($a = 0; $a < 60; $a++)
																						<option>{{ sprintf('%02d', $a) }}</option>
																						@endfor
																				</select>
																				<select class="form-select sel-time-am-pm sel-time-from-am-pm ml-1 form-custom-input" disabled="">
																					<option>am</option>
																					<option>pm</option>
																				</select>
																			</div>
																		</td>
																		<td class="td-custom-css whitespace-nowrap td-time-to">
																			<div class="flex items-center">
																				<input type="hidden" data-name="time_to[]" class="hid-time hid-time-to">
																				<select class="form-select sel-time-to-hour sel-time-hour mr-1 form-custom-input" disabled="">
																					<option></option>
																					@for($a = 1; $a < 12; $a++)
																						<option>{{ $a }}</option>
																						@endfor
																						<option value="0">12</option>
																				</select>:<select class="form-select sel-time-minute sel-time-to-minute ml-1 form-custom-input" disabled="">
																					<option></option>
																					@for($a = 0; $a < 60; $a++)
																						<option>{{ sprintf('%02d', $a) }}</option>
																						@endfor
																				</select>
																				<select class="form-select sel-time-am-pm sel-time-to-am-pm ml-1 form-custom-input" disabled="">
																					<option></option>
																					<option>am</option>
																					<option>pm</option>
																				</select>
																			</div>
																		</td>
																		<td class="td-custom-css">
																			<input type="number" min="0" name="seat_limit[]" value="0" class="form-control hid-seat-limit form-custom-input">
																			<input type="hidden" name="affiliate_seat_id[]" class="hid-affiliate-seat-id" value="">
																		</td>
																		<td class="td-custom-css">
																			<select class="form-select sel-b-status form-custom-input" disabled="">
																				<option>Active</option>
																				<option>Inactive</option>
																			</select>
																		</td>
																		<td class="td-custom-css text-center"><button type="button" class="btn btn-primary btn-sm btn-primary-small" onclick="addUpdateTimeSlot(this)" title="Add / update time slot">Save</button></td>
																	</tr>
																</table>
															</form>
														</td>
													</tr>
												</tfoot>
											</table>
										</div>
									</div>
								</div>

								<div class="tab-pane" id="tab-iFrame" role="tabpanel" aria-labelledby="tab-iFrame">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mt-3 mb-3" style="margin-top: 0px !important;">
												<label for="tour-package" class="form-label form-custom-label">Tour Package</label>
												<select onchange="getiFrameLink()" class="form-select form-custom-input" name="tour_package_id" id="tour-package">
													@foreach ($tourPackageList as $package)
													<option value="{{ $package->id }}">{{ $package->name }}</option>
													@endforeach
												</select>
												@if(app()->environment('staging'))
												<input type="hidden" name="app_url" class="app_url" value="{{ config('constants.environment_iframe.staging') }}">
												@elseif (app()->environment('production'))
												<input type="hidden" name="app_url" class="app_url" value="{{ config('constants.environment_iframe.production') }}">
												@elseif (app()->environment('development'))
												<input type="hidden" name="app_url" class="app_url" value="{{ config('constants.environment_iframe.development') }}">
												@else
												<input type="hidden" name="app_url" class="app_url" value="{{ config('constants.environment_iframe.staging') }}">
												@endif
											</div>
										</div>
										<div class="md:flex md:items-center">
											@if(app()->environment('staging'))
											<input class="mb-2 form-custom-input" type="text" id="iframeurl" value="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid=' .$tourOperators->id .'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}">
											@elseif (app()->environment('production'))
											<input class="mb-2 form-custom-input" type="text" id="iframeurl" value="{{ config('constants.environment_iframe.production') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid=' .$tourOperators->id .'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}">
											@elseif (app()->environment('development'))
											<input class="mb-2 form-custom-input" type="text" id="iframeurl" value="{{ config('constants.environment_iframe.development') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid=' .$tourOperators->id .'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}">
											@else
											@if(!empty($tourPackage))
											<input class="mb-2 form-custom-input" type="text" id="iframeurl" value="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid=' .$tourOperators->id .'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}">
											@endif
											@endif
											<button class="btn btn-secondary btn-sm mb-2 ml-2 whitespace-nowrap btn-info-small" onclick="iFrameCopy()">Link Copy</button>
										</div>
										<span class="hidden p-2" id="custom-tooltip">copied!</span>
										<div class="w-full overflow-y-auto">
											@if(app()->environment('staging'))
											<iframe src="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid='.$tourOperators->id.'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}" title="Iframe" height="400" width="1300" id="iframe_tag"></iframe>
											@elseif (app()->environment('production'))
											<iframe src="{{ config('constants.environment_iframe.production') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid='.$tourOperators->id.'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}" title="Iframe" height="400" width="1300" id="iframe_tag"></iframe>
											@elseif (app()->environment('development'))
											<iframe src="{{ config('constants.environment_iframe.development') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid='.$tourOperators->id.'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}" title="Iframe" height="400" width="1300" id="iframe_tag"></iframe>
											@else
											@if(!empty($tourPackage))
											<iframe src="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.$tourOperators->tenant_id.'&oid='.$tourOperators->id.'&pid='.$tourPackage->id.'&aid='. $affiliate->id}}" title="Iframe" height="400" width="1300" id="iframe_tag"></iframe>
											@endif
											@endif
										</div>
									</div>
								</div>
								<div class="tab-pane" id="tab-packages" role="tabpanel" aria-labelledby="tab-iFrame">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 panel mb-3">
												<h5 class="categorized-title font-normal text-gray-600 text-base mb-4">Tour Packages</h5>
												@foreach($tourPackageList as $tp)
												<div class="form-switch-package document-panel flex items-center justify-between py-2 border-b border-[#F1F2F4] mb-2 min-h-[55px]">
													<label class="form-custom-label">{{ $tp->name }}</label>
													<input class="assigned_package  w-4 h-4 ml-2 align-text-top" type="checkbox" id="package_{{ $tp->id }}" value="{{ $tp->id }}"  name="assignedPackage"  {{ ($assignedPackages->contains('package_id',$tp->id) ? 'checked' : '' )}}>
												</div>
												@endforeach
											</div>
											<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 panel mb-3">
											<h5 class="categorized-title font-normal text-gray-600 text-base mb-4">Show All Package Bookings</h5>
												@foreach($tourPackageList as $tp)
												@php
												$showAllPackageBookings = 0;
												if($assignedPackages->contains('package_id',$tp->id)){
													$packageAffiliateAssignment = $assignedPackages->filter(function ($item) use ($tp){
															return $item->package_id == $tp->id;
														})->values();
													$showAllPackageBookings = $packageAffiliateAssignment[0]->show_all_package_bookings;
												}
												@endphp
												<div class="form-switch document-panel form-switch-wrap document-panel mb-2">
													<select class="form-control show_all_package_bookings form-custom-input" name="show_all_package_bookings"  id="{{ $tp->id }}">
														<option value="0" {{ ($showAllPackageBookings == 0) ? 'selected' : '' }}>No</option>
														<option value="1" {{ ($showAllPackageBookings == 1) ? 'selected' : '' }}>Yes</option>
													</select>
												</div>
												@endforeach
											</div>
										</div>
									</div>
								</div>
								<div class="tab-pane" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings">
									@php
									@endphp
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form method="post" action="{{ route($routePrefix . '.update-settings-affiliate', ['id' => $id]) }}" id="UpdateSettingsForm" onsubmit="return validateAndSubmitUpdateSettingForm(this)" data-redirect-on-success="{{ route($routePrefix . '.affiliate.index') }}" data-on-success-msg=@if($action=='Add' ) "Affiliate added"
												@else {{-- update --}} "Affiliate's details updated"
												@endif>
												<div class="flex flex-wrap -mx-3">
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="affiliate_processing_percentage" class="form-label form-custom-label"><span style="color: green">{{ date('Y') }}</span> - Service Commission Percentage <req>*</req> @if($action == 'Add') <button type="button" class="btn btn-sm btn-edit btn-primary-small" id="help">
																<i class="fa fa-edit"></i>
																Help
															</button> @endif </label>
														<input class="form-control form-custom-input" name="affiliate_processing_percentage" id="affiliate_processing_percentage" maxlength="80" value="{{ $affiliate->affiliate_processing_percentage }}" placeholder="Enter {{ date('Y') }} service commission percentage" required>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="stripe_affiliate_percentage" class="form-label form-custom-label"><span style="color: green">{{ date('Y') }}</span> - Stripe Commission Percentage <req>*</req></label>
														<input type="text" name="stripe_affiliate_percentage" id="stripe_affiliate_percentage" class="form-control form-custom-input" value="{{ $affiliate->stripe_affiliate_percentage }}" placeholder="Enter {{ date('Y') }} stripe commission percentage" required>
													</div>
												</div>
												<div class="flex flex-wrap -mx-3">
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="next_year_affiliate_processing_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - Service Commission Percentage <req>*</req></label>
														<input class="form-control form-custom-input" name="next_year_affiliate_processing_percentage" id="next_year_affiliate_processing_percentage" maxlength="80" value="{{ $affiliate->next_year_affiliate_processing_percentage }}" placeholder="Enter {{ date('Y') + 1 }} service commission percentage" required>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="next_year_stripe_affiliate_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - Stripe Commission Percentage <req>*</req></label>
														<input type="text" name="next_year_stripe_affiliate_percentage" id="next_year_stripe_affiliate_percentage" class="form-control form-custom-input" value="{{ $affiliate->next_year_stripe_affiliate_percentage }}" placeholder="Enter {{ date('Y') + 1 }} stripe commission percentage" required>
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3 hidden">
														<label for="stripe_destination_id" class="form-label form-custom-label">Stripe Connected Account ID <req>*</req></label>
														<input type="password" name="stripe_destination_id" id="stripe_destination_id" class="form-control form-custom-input" value="{{ $affiliate->stripe_destination_id }}" placeholder="Enter Stripe Connected Account ID">
													</div>
												</div>
												<div class="ota-commission-fields-settings flex flex-wrap -mx-3" style="{{ $affiliate->is_ota ? '' : 'display:none' }}">
													@if($affiliate->is_ota && $affiliate->ota_id)
													<div class="w-full px-3 mb-4">
														<label class="form-label form-custom-label">OCTO API Reseller ID  <code class="px-3 py-2 bg-gray-100 border border-gray-300 rounded text-sm font-mono text-gray-800 select-all">{{ $affiliate->ota_id }}</code></label>
													</div>
													@endif
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="ota_affiliate_percentage_settings" class="form-label form-custom-label"><span style="color: green">{{ date('Y') }}</span> - {{ $affiliate->name }} (OTA) Commission Percentage <req>*</req></label>
														<input class="form-control form-custom-input" name="ota_affiliate_percentage" id="ota_affiliate_percentage_settings" maxlength="80" value="{{ $affiliate->ota_affiliate_percentage }}" placeholder="Enter {{ date('Y') }} OTA commission percentage">
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="next_year_ota_affiliate_percentage_settings" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - {{ $affiliate->name }} (OTA) Commission Percentage <req>*</req></label>
														<input class="form-control form-custom-input" name="next_year_ota_affiliate_percentage" id="next_year_ota_affiliate_percentage_settings" maxlength="80" value="{{ $affiliate->next_year_ota_affiliate_percentage }}" placeholder="Enter {{ date('Y') + 1 }} OTA commission percentage">
													</div>
												</div>

												<div class="pax-limit-fields-settings flex flex-wrap -mx-3" style="{{ $affiliate->is_ota ? '' : 'display:none' }}">
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="min_pax" class="form-label form-custom-label">Min Pax <small class="text-muted">(Per booking)</small></label>
														<input type="number" name="min_pax" id="min_pax" min="1" class="form-control form-custom-input" value="{{ $affiliate->min_pax }}" placeholder="Min. units per booking">
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="max_pax" class="form-label form-custom-label">Max Pax <small class="text-muted">(Per booking)</small></label>
														<input type="number" name="max_pax" id="max_pax" min="1" class="form-control form-custom-input" value="{{ $affiliate->max_pax }}" placeholder="Max. units per booking">
													</div>
												</div>

												<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">Update</button></div>
										</form>

									</div>
							</div>
						</div> {{-- /.card --}}
						@endif

					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/bootstrap-notify/animate.min.css') }}">
@else
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.2.3/animate.min.css">
@endif
@endsection
@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/bootstrap-notify/bootstrap-notify.min.js') }}"></script>
@else
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mouse0270-bootstrap-notify/3.1.5/bootstrap-notify.min.js"></script>
@endif
@endsection
@section('js-custom')
<script>
	$(document).ready(function() {
		$(document).on("click", "#help", function() {
			$('#helpModel').modal('show');
		});

		$(document).on("click", '.helpSet', function(e) {
			var affiliate = $(this).data('affiliate');
			var stripe = $(this).data('stripe');
			$("#affiliate_processing_percentage").val(affiliate);
			$("#stripe_affiliate_percentage").val(stripe);
		});
	});
</script>

@if($action == 'Update')

<script>
	function getiFrameLink() {
		var app_url = $(".app_url").val();
		var pid = $('#tour-package').val();
		var iframe = app_url + 'iframe=true&tid=' + "{{ $tourOperators->tenant_id }}" + '&oid=' + "{{ $tourOperators->id }}" + '&pid=' + pid + '&aid=' + "{{ $affiliate->id }}";

		document.getElementById('iframeurl').value = iframe;
		document.getElementById('iframe_tag').src = iframe;
	}

	function iFrameCopy() {
		// Get the text field
		var copyText = document.getElementById("iframeurl");

		// Select the text field
		copyText.select();
		copyText.setSelectionRange(0, 99999); // For mobile devices

		// Copy the text inside the text field
		navigator.clipboard.writeText(copyText.value);

		// Display the message to copied text
		document.getElementById("custom-tooltip").style.display = "inline";
		document.execCommand("copy");
		setTimeout(function() {
			document.getElementById("custom-tooltip").style.display = "none";
		}, 1000);
	}

	/*function iFrameUpdate(){
		 var iframe_url = $("#iframe_url").val();
		 $('#iframe').val(iframe_url);
	}*/
</script>

<script>
	var action = 'Update';
	var packageRates = @json($packageRates);
	var timeSlots = @json($timeSlots);
	var isGroupRateEnabled = "{{ $isGroupRateEnabled }}";
	var addUpdateTimeSlotUrl = "{{ route($routePrefix . '.time-slot.add-update-time-slot') }}";
	var addUpdateTimeSlotsUrl = "{{ route($routePrefix . '.time-slot.add-update-time-slots') }}";
	var deletePckgRateGrpUrl = "{{ route($routePrefix . '.package.delete-rate', [ 'id' => 0 ]) }}";
	var assignPackageUrl = "{{ route($routePrefix . '.assign-package', [ 'id' => $affiliate->id ]) }}";

	$(document).ready(function() {

		$(document).on('click', '.form-pckg-rate-grp', function() {
			let tour_package_id_ = $('#tour_package').val();
			if (tour_package_id_ == "" || tour_package_id_ == null) {
				$('#tour_package_id_error_').text('This filed is required');
				return false;
			} else {
				$('#tour_package_id_error_').text('').remove();
			}
		});

		$("#tour_package option:selected").each(function() {
			var tour_package = $(this).val();
			$('.hid-package-id').val(tour_package);
			//getRateList();
		});
		$("#tour_package").change(function() {
			var tour_package = $(this).val();
			$('.hid-package-id').val(tour_package);

			var url = "{{ route($routePrefix . '.package.affiliate-add-update-rate')}}";
			$(".form-pckg-rate-grp").attr("action", url);

			getRateList();
		});

		// Get package wise affiliate timeslots
		$("#aff_tour_package").change(function() {
			var tourPackage = $(this).val();
			if (tourPackage != '') {
				$('.hid-package-id').val(tourPackage);

				var url = "{{ route($routePrefix . '.package.affiliate-add-update-affiliate-time-slots')}}";
				$(".form-pckg-aff-slots").attr("action", url);

				getTimeSlotList();
			} else {
				$('#tbody-time-slots').empty();
				$(".save-affiliate-seats").prop('disabled', true);
			}
		});

		var getRateListUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.package.affiliatepackagelist') }}";
		var getTimeSlotListUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.package.affiliatetimeslotlist') }}";

		function getRateList() {
			var selectedPackage = $("#tour_package").val();
			var affiliate_id = $(".hid-affiliate-id").val();
			var year = $(".set-year").val();

			if (selectedPackage) {
				$.ajax({
					url: getRateListUrl,
					method: "POST",
					data: {
						selectedPackage: selectedPackage,
						affiliate_id: affiliate_id,
						year: year
					},
					success: function(result) {
						$('#tbody-pckg-rate-grps').empty();
						var packageRates = result.packageRates;
						var isGroupRateEnabled = result.isGroupRateEnabled;
						for (var i = 0; i < packageRates.length; i++) {
							var tmpl = addPckgRateGrp();
							tmpl.getElementsByClassName('hid-rate-id')[0].value = packageRates[i].id;
							tmpl.getElementsByClassName('txt-rate-for')[0].value = packageRates[i].rate_for;
							tmpl.getElementsByClassName('txt-age-from')[0].value = packageRates[i].age_from;
							tmpl.getElementsByClassName('txt-age-to')[0].value = packageRates[i].age_to;
							tmpl.getElementsByClassName('txt-rate')[0].value = packageRates[i].rate;
							tmpl.getElementsByClassName('txt-adnl-chrg')[0].value = packageRates[i].additional_charge;
							tmpl.getElementsByClassName('txt-margin')[0].value = packageRates[i].margin;
							tmpl.getElementsByClassName('txt-desc')[0].value = packageRates[i].description;
							tmpl.getElementsByClassName('txt-permit-fee')[0].value = packageRates[i].permit_fee;
							tmpl.getElementsByClassName('txt-orderby')[0].value = packageRates[i].orderby;

							var selectMappingKey = tmpl.getElementsByClassName('select-mapping-key')[0];
							for (var j = 0; j < selectMappingKey.options.length; j++) {
								if (selectMappingKey.options[j].value == packageRates[i].mapping_key) {
									selectMappingKey.options[j].selected = 1;
									break;
								}
							}

							var selStatus = tmpl.getElementsByClassName('sel-status')[0];
							for (var j = 0; j < selStatus.options.length; j++) {
								if (selStatus.options[j].text == packageRates[i].status) {
									selStatus.options[j].selected = 1;
									break;
								}
							}

							var selectGlobalRate = tmpl.getElementsByClassName('select-global-rate')[0];
							for (var j = 0; j < selectGlobalRate.options.length; j++) {
								if (selectGlobalRate.options[j].value == packageRates[i].global_rate) {
									selectGlobalRate.options[j].selected = 1;
									break;
								}
							}

							if (isGroupRateEnabled == '1') {
								tmpl.getElementsByClassName('txt-min-group-size')[0].value = packageRates[i].min_group_size;
								tmpl.getElementsByClassName('txt-max-group-size')[0].value = packageRates[i].max_group_size;
							}
						}

						if (isGroupRateEnabled == '1') {
							$('.grouprateth').show();
							$('.groupratetd').show();
							$('.descth').attr('colspan', 3);
						} else {
							$('.grouprateth').hide();
							$('.groupratetd').hide();
							$('.descth').attr('colspan', 5);
						}
					}
				});
			}
		}

		// Get list of affiliate timeslots package wise
		function getTimeSlotList() {
			var selectedPackage = $("#aff_tour_package").val();
			var affiliateId = $(".hid-affiliate-id").val();
			var timeFrom = null,
				timeTo = null,
				h = null;

			function hour(hr) {
				var h = parseInt(hr);

				if (h > 12)
					h -= 12;

				if (h == 12)
					h = 0;

				return h;
			}

			if (selectedPackage) {
				$.ajax({
					url: getTimeSlotListUrl,
					method: "POST",
					data: {
						selectedPackage: selectedPackage,
						affiliateId: affiliateId
					},
					success: function(result) {
						$('#tbody-time-slots').empty();
						if (result.length > 0) {
							$(".save-affiliate-seats").prop('disabled', false);

							for (var i = 0; i < result.length; i++) {
								var tmpl = addPckgAffTimeSlots();
								tmpl.getElementsByClassName('hid-time-slot-id')[0].value = result[i].time_slot_id;
								tmpl.getElementsByClassName('hid-time-from')[0].value = result[i].time_from;
								tmpl.getElementsByClassName('hid-time-to')[0].value = result[i].time_to;
								tmpl.getElementsByClassName('sel-b-status')[0].value = result[i].status;
								if (result[i].seats == null) {
									tmpl.getElementsByClassName('hid-seat-limit')[0].value = 0;
								} else {
									tmpl.getElementsByClassName('hid-seat-limit')[0].value = result[i].seats;
								}
								tmpl.getElementsByClassName('hid-affiliate-seat-id')[0].value = result[i].affiliate_seat_id;
								$(tmpl).removeClass('hidden');

								timeFrom = result[i].time_from.split(':');
								h = hour(timeFrom[0]);
								tmpl.getElementsByClassName('sel-time-from-hour')[0].value = h;
								tmpl.getElementsByClassName('sel-time-from-minute')[0].value = timeFrom[1];
								tmpl.getElementsByClassName('sel-time-from-am-pm')[0].value = (timeFrom[0] < 12 ? 'am' : 'pm');

								if (result[i].time_to != null) {
									timeTo = result[i].time_to.split(':');
									h = hour(timeTo[0]);
									tmpl.getElementsByClassName('sel-time-to-hour')[0].value = h;
									tmpl.getElementsByClassName('sel-time-to-minute')[0].value = timeTo[1];
									tmpl.getElementsByClassName('sel-time-to-am-pm')[0].value = (timeTo[0] < 12 ? 'am' : 'pm');
								}
								var selStatus = tmpl.getElementsByClassName('sel-b-status')[0];

								for (var j = 0; j < selStatus.options.length; j++) {
									if (selStatus.options[j].text == result[i].status) {
										selStatus.options[j].selected = 1;
										break;
									}
								}
							}
						} else {
							$(".save-affiliate-seats").prop('disabled', true);
						}
					}
				});
			}
		}

		var $dateFrom = null;
		var $dateTo = null;

		if (action == 'Add') {
			addTimeSlot();
		} else { // Update
			fillTimeSlots();
		}


	});
	var formSubmitRoute = "{{$formSubmitRoute}}";
</script>

<script src="{{ asset('js/user-common/tour-package-add-edit.js') }}"></script>
<x-delete-record-js-file />
@endif

@if($action == 'Add' && session('userType') == 'Admin')
<x-admin-js />
@endif

@if( $action == 'Add' )
<script>
	$(document).ready(function() {
		// note this function is added from js/utils/password-strength.js
		window._PasswordStrength = new PasswordStrength();
	});
</script>
@else
<script>
	$(document).ready(function() {
		$(document).on('click', '#password', () => {
			if (!window.hasOwnProperty("_PasswordStrength")) {
				// only validate the password on update if the user clicks it
				window._PasswordStrength = new PasswordStrength();
			}
		});
	});
</script>
@endif
<script>
	$(document).ready(function() {
		$(document).on("click", ".sync", function() {
			var getTimeSlotListUrl = "{{ route($routePrefix . '.package.affiliatetimeslotsync') }}";
			var selectedPackage = $("#aff_tour_package").val();
			var affiliateId = $(".hid-affiliate-id").val();
			
			if (selectedPackage) {
				$.ajax({
					url: getTimeSlotListUrl,
					method: "POST",
					data: {
						selectedPackage: selectedPackage,
						affiliateId: affiliateId
					},
					success: function(result) {
						if ($.isEmptyObject(result.error)) {

							$('#success_msg').fadeIn().html(result.success);
							setTimeout(function() {
								$('#success_msg').fadeOut('slow');
							}, 10000);
							location.reload();
						} else {

							$('#error_msg').fadeIn().html(result.error);
							setTimeout(function() {
								$('#error_msg').fadeOut('slow');
							}, 10000);

						}
					}
				});
			}
		});

	})
</script>

<script src="{{ asset('js/user-common/affiliate-add-edit.js') }}"></script>
<script src="{{ asset('js/utils/password-strength.js') }}"></script>
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script >
	$('#role').change(function() {
    	var id = $(this).val();
    	var userId = $('#id').val();
	    $.ajax({
		  type: "GET",
		  url: "{{route($routePrefix . '.package.affiliate-user-permission')}}",
		  data: {id: id,  userId: userId},
		  success: function(html)
		  {
		  	if(html.length > 0){
		  		$('.label-permissions').attr('hidden', false);
		  		$('.permission-role-wise').html(html);
		  	}else{
		  		$('.label-permissions').attr('hidden', true);
		  		$('.permission-role-wise').html('');
		  	}
		   
		  }
		});	  
	});

	$(document).ready(function() {
    	var roleId = ($("#role").val());
    	var userId = $('#id').val();
    	 $.ajax({
		  type: "GET",
		  url: "{{route($routePrefix . '.package.affiliate-user-permission-edit')}}",
		  data: {roleId: roleId, userId: userId},
		  success: function(html)
		  {
		  	if(html.length > 0){
		  		$('.label-permissions').attr('hidden', false);
		  		$('.permission-role-wise').html(html);
		  	}else{
		  		$('.label-permissions').attr('hidden', true);
		  		$('.permission-role-wise').html('');
		  	}
		   
			allPermissions.editModuleInitPermission();
		  }
		});	  
	});
</script>

@endsection
