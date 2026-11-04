@extends('layouts.default-2')
@php
$routePrefix = routePrefix();
if(session('userType') == 'Admin') {
$uTypeAdmin = true;
} else {
$uTypeAdmin = false;
}
$category_type = category_type();
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
								<h2 class="section-title !mb-0">Update Tour Package</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.package.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
								<a class="btn-primary-small" href="{{ route($routePrefix . '.package.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Update Tour Package</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.package.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.package.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($errors->any())
						@include('layouts._errors')
						@endif

						@if(session('message'))
						@include('layouts._message')
						@endif
						<div class="card">
							<nav class="nav nav-tabs tab-list p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-x-auto" id="nav-tab" role="tablist">
								<button type="button" class="tabs-custom-link !px-4 active" id="nav-general" data-bs-toggle="tab" data-bs-target="#tab-general" role="tab" aria-controls="tab-general" aria-selected="true">General</button>
								@can('pt-seo')
								<button type="button" class="tabs-custom-link !px-4" id="nav-seo" data-bs-toggle="tab" data-bs-target="#tab-seo" role="tab" aria-controls="tab-seo" aria-selected="true">SEO</button>
								@endcan
								@can('pt-photos')
								<button type="button" class="tabs-custom-link !px-4" id="nav-photos" data-bs-toggle="tab" data-bs-target="#tab-photos" role="tab" aria-controls="tab-photos" aria-selected="true">Photos</button>
								@endcan
								<!-- <button type="button" class="tabs-custom-link !px-4" id="nav-rate-groups" data-bs-toggle="tab" data-bs-target="#tab-rate-groups" role="tab" aria-controls="tab-rate-groups" aria-selected="true">Rates</button> -->
								@can('pt-waiver')
								<button type="button" class="tabs-custom-link !px-4" id="nav-waiver" data-bs-toggle="tab" data-bs-target="#tab-waiver" role="tab" aria-controls="tab-waiver" aria-selected="true">Waiver</button>
								@endcan
								@if (hasSetting('HOTEL_PICKUP'))
								@can('pt-hotels')
								<button type="button" class="tabs-custom-link !px-4" id="nav-hotels" data-bs-toggle="tab" data-bs-target="#tab-hotels" role="tab" aria-controls="tab-hotels" aria-selected="true">Hotels</button>
								@endcan
								@endif
								@can('pt-relations')
								@if ($allTourPackages->count() > 1 || $nonBundlePackages->count() > 1)
								<button type="button" class="tabs-custom-link !px-4" id="nav-relations-groups" data-bs-toggle="tab" data-bs-target="#tab-relation-groups" role="tab" aria-controls="tab-relation-groups" aria-selected="true">Relations</button>
								@endcan
								@endif
								@can('pt-self-check-in')
								<button type="button" class="tabs-custom-link !px-4" id="nav-categories-groups" data-bs-toggle="tab" data-bs-target="#tab-categories-groups" role="tab" aria-controls="tab-categories-groups" aria-selected="true">Self Check-In</button>
								@endcan
								@can('pt-frontend-link')
								<button type="button" class="tabs-custom-link !px-4" id="nav-iframe-groups" data-bs-toggle="tab" data-bs-target="#tab-iframe-groups" role="tab" aria-controls="tab-iframe-groups" aria-selected="true">Frontend Link</button>
								@endcan
								@if ($affiliates->count() > 0)
								@can('pt-assign-affiliates')
								<button type="button" class="tabs-custom-link !px-4" id="nav-affiliate-groups" data-bs-toggle="tab" data-bs-target="#tab-affiliate-groups" role="tab" aria-controls="tab-affiliate-groups" aria-selected="true">Assign Affiliates</button>
								@endcan
								@endif
								@can('pt-cancellation-policies')
								<button type="button" class="tabs-custom-link !px-4" id="nav-cancellation-policies" data-bs-toggle="tab" data-bs-target="#tab-cancellation-policies" role="tab" aria-controls="tab-cancellation-policies" aria-selected="true">Cancellation Policies</button>
								@endcan
								@if (hasSetting('CUSTOM_FIELDS'))
								@can('pt-add-ons')
								<button type="button" class="tabs-custom-link !px-4" id="nav-custom-form-fields" data-bs-toggle="tab" data-bs-target="#tab-custom-form-groups" role="tab" aria-controls="tab-custom-form-groups" aria-selected="true">Add-Ons</button>
								@endcan
								@endif
								@can('pt-settings')
								<button type="button" class="tabs-custom-link !px-4" id="nav-setting" data-bs-toggle="tab" data-bs-target="#tab-settings" role="tab" aria-controls="tab-settings" aria-selected="true">Settings</button>
								@endcan
							</nav>

							<div class="tab-content">
								<div class="tab-pane active" id="tab-general" role="tabpanel" aria-labelledby="tab-general">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form class="form" method="post" action="{{ route($routePrefix . '.package.update-general', [ 'id' => $tourPackage->id ]) }}" onsubmit="if(!validateCharacters('#name'))return false;return submitForm(this)" data-on-success-msg="Tour package's general details updated.">
											@csrf

											<div class="flex flex-wrap -mx-3">
												<div class="w-full max-w-full px-3 mb-2">
													<label for="name" class="form-label form-custom-label">Package Name <req>*</req></label>
													<input class="form-control form-custom-input" name="name" id="name" maxlength="100" value="{{ $tourPackage->name }}" placeholder="Enter package name" required>
												</div>
											</div>

											<div class="mb-3">
												<label for="short_description" class="form-label form-custom-label">Short Description</label>
												<textarea class="form-control form-custom-input max-h-16 min-h-16" name="short_description" id="short_description" placeholder="Enter short description of the package">{{ $tourPackage->short_description }}</textarea>
											</div>

											<div class="mb-3">
												<label for="long_description" class="form-label form-custom-label">Full Description</label>
												<textarea class="form-control form-custom-input max-h-16 min-h-16" name="long_description" id="long_description" placeholder="Enter full description">{{ $tourPackage->long_description }}</textarea>
											</div>

											<div><label class="form-label form-custom-label">Duration</label></div>
											<div class="flex flex-wrap -mx-3 mb-3">
												<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
													<label for="hours" class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours <req>*</req></label>
													<input type="number" class="form-control form-custom-input !rounded-l-none" name="hours" id="hours" min="0" max="255" value="{{ $tourPackage->hours }}" placeholder="Enter hours" required>
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
													<label for="minutes" class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Minutes <req>*</req></label>
													<input type="number" class="form-control form-custom-input !rounded-l-none" name="minutes" value="{{ $tourPackage->minutes }}" placeholder="Enter minutes" required>
												</div>
											</div>
											<div class="flex flex-wrap -mx-3">
												@if(!hasSetting('CALL_TO_BOOK'))
												<div class="w-full md:w-1/2 max-w-full px-3"><label class="form-label form-custom-label">Block timeslot duration </label></div>
												@endif
												@if(hasSetting('CALL_TO_BOOK'))
												<div class="w-full md:w-1/2 max-w-full px-3"><label class="form-label form-custom-label">Block call to book duration </label></div>
												@endif
											</div>
											<div class="flex flex-wrap -mx-3 mb-3">
												@if(!hasSetting('CALL_TO_BOOK'))
												<div class="w-full md:w-1/2 max-w-full px-3">
													<div class="input-group flex items-center">
														<label for="hours" class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours <req>*</req></label>
														<input type="number" class="form-control form-custom-input !rounded-l-none" name="duration" id="duration" min="0" value="{{ $tourPackage->duration }}" placeholder="Enter day or hours" required>
													</div>
												</div>
												@endif
												@if(hasSetting('CALL_TO_BOOK'))
												<div class="w-full md:w-1/2 max-w-full px-3">
													<div class="input-group flex items-center">
														<label for="hours" class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours </label>
														<input type="number" class="form-control form-custom-input !rounded-l-none" name="block_ctb_duration" id="block_ctb_duration" min="0" value="{{ $tourPackage->block_ctb_duration }}" placeholder="Enter block call to book duration">
													</div>
												</div>
												@endif
											</div>
											@if(hasSetting('CALL_TO_BOOK'))
											<div class="mb-3">
												<label class="form-label form-custom-label">Call To Book Description</label>
												<div id="quillArea2" style="height: 200px;">
													{!! $tourPackage->ctb_description !!}
												</div>
												<textarea class="max-h-16 min-h-16" style="display:none" id="ctb_description" name="ctb_description">{!! $tourPackage->ctb_description !!}</textarea>
											</div>
											@endif
											<div class="flex flex-wrap -mx-3">
												<div class="w-full md:w-1/2 max-w-full px-3"><label class="form-label form-custom-label">Block timeslot duration (For OTAs)</label></div>
											</div>
											<div class="flex flex-wrap -mx-3 mb-3">
												<div class="w-full md:w-1/2 max-w-full px-3">
													<div class="input-group flex items-center">
														<label class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">Hours</label>
														<input type="number" class="form-control form-custom-input !rounded-l-none" name="ota_duration" id="ota_duration" min="0" value="{{ $tourPackage->ota_duration ?? 0 }}" placeholder="Enter OTA block duration">
													</div>
												</div>
											</div>
											<div class="mb-3">
												<label for="things_to_bring" class="form-label form-custom-label">Things to be Brought by Tourists</label> <small>(For Example: [{"value":"Bottled Water"},{"value":"Closed-Toed Shoes"}] )</small>
												<textarea class="form-control form-custom-input max-h-16 min-h-16" name="things_to_bring" id="things_to_bring">{!! fromDbToTagify($tourPackage->things_to_bring) !!}</textarea>
											</div>

											<div class="mb-3">
												<label for="important_notes" class="form-label form-custom-label">Important Notes for Tourists</label>
												<textarea class="form-control form-custom-input max-h-16 min-h-16" name="important_notes" id="important_notes">{{ $tourPackage->important_notes }}</textarea>
											</div>

											<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">Update</button></div>
										</form>
									</div>
								</div>

								<div class="tab-pane" id="tab-seo" role="tabpanel" aria-labelledby="tab-seo">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form class="form" method="post" action="{{ route($routePrefix . '.package.update-seo', [ 'id' => $tourPackage->id ]) }}" onsubmit="return submitForm(this)" data-on-success-msg="Tour package's SEO details updated.">
											@csrf

											<div class="mb-3">
												<label for="seo_title" class="form-label form-custom-label">Title</label>
												<input class="form-control form-custom-input" name="seo_title" id="seo_title" value="{{ $tourPackage->seo_title }}" placeholder="Enter title for SEO">
											</div>

											<div class="mb-3">
												<label for="seo_description" class="form-label form-custom-label">Description</label>
												<textarea class="form-control form-custom-input max-h-16 min-h-16" name="seo_description" id="seo_description" placeholder="Enter description for SEO">{{ $tourPackage->seo_description }}</textarea>
											</div>

											<div class="mb-3">
												<label for="seo_keywords" class="form-label form-custom-label">Keywords</label>
												<textarea class="form-control form-custom-input max-h-16 min-h-16" name="seo_keywords" id="seo_keywords" placeholder="Enter keywords for SEO">{{ $tourPackage->seo_keywords }}</textarea>
											</div>

											<div class="mt-4 text-end"><button class="btn btn-main btn-primary-small">Update</button></div>
										</form>
									</div>
								</div>

								<div class="tab-pane" id="tab-photos" role="tabpanel" aria-labelledby="tab-photos">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form class="form" method="post" action="{{ route($routePrefix . '.package.add-photos', [ 'id' => $tourPackage->id ]) }}" enctype="multipart/form-data">
											@csrf

											<h3 class="mb-3 font-normal text-gray-600 text-lg">Upload Photos <small>(JPEG, PNG, WebP, GIF)</small></h3>

											<div class="mb-3">
												<label for="photo_header" class="form-label form-custom-label">Header Photo</label> <small>(Uploading new photo will delete the existing header photo if there is one already)</small>
												<input type="file" name="photo_header" id="photo_header" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkHeaderImgAndShowPreview(this)">
											</div>

											<div><img id="img-header" style="max-height:200px"></div>

											<div class="mb-3">
												<label for="photos_other" class="form-label form-custom-label">Other Photos</label>
												<input type="file" name="photos_other[]" id="photos_other" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkFilesAndShowPreview(this)" multiple>
											</div>

											<div class="flex flex-wrap -mx-3">
												<div id="tmpl-img-pckg" class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 max-w-full px-3 hidden mb-4 img-pckg-container">
													<img class="img-pckg">
												</div>
											</div>

											<div class="mb-3">
												<label for="header_one" class="form-label form-custom-label">Header Image One</label>
												<input type="file" name="header_one" id="header_one" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkHeaderOneImgAndShowPreview(this)">
											</div>

											<div><img id="img-header-one" style="max-height:200px"></div>

											<div class="mb-3">
												<label for="header_two" class="form-label form-custom-label">Header Image two</label>
												<input type="file" name="header_two" id="header_two" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkHeaderTwoImgAndShowPreview(this)">
											</div>

											<div><img id="img-header-two" style="max-height:200px"></div>

											<div class="mb-3">
												<label for="date-time-section-image" class="form-label form-custom-label">Change Date time section image (Vue js payment page)</label>
												<input type="file" name="date_time_section_image" id="date-time-section-image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkTimeDateSectionAndShowPreview(this)">
											</div>

											<div><img id="img-date-time-section-image" style="max-height:200px"></div>

											<div class="mb-3">
												<label for="frontend-package-image" class="form-label form-custom-label">Frontend package image (Vue js package list page)</label>
												<input type="file" name="frontend_package_image" id="frontend-package-image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkFrontendPackageImageAndShowPreview(this)">
											</div>

											<div><img id="img-frontend-package-image" style="max-height:200px"></div>

											<div class="mb-3">
												<label for="email-template-image" class="form-label form-custom-label">Email Template Image</label>
												<input type="file" name="email_template_image" id="email-template-image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="checkEmailTemplateImageAndShowPreview(this)">
											</div>

											<div><img id="img-email-template-image" style="max-height:200px"></div>

											<div class="flex justify-end"><button class="btn btn-main btn-primary-small">Upload</button></div>
										</form>

										<h3 class="mb-3 font-normal text-gray-600 text-lg">Uploaded Photos</h3>

										<div class="flex flex-wrap -mx-3">
											@if(!count($packagePhotos))
											<div class="w-full max-w-full px-3 font-normal text-gray-600 text-sm">No photo has been uploaded yet.</div>
											@endif

											@foreach($packagePhotos as $photo)
											<div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 max-w-full px-3 mb-3 img-pckg-container">
												<div class="form-custom-label"><b>Placement:</b> {{ $photo->placement == 1 ? 'Header' : '' }} {{ $photo->placement == 3 ? 'Header Image one' : '' }} {{ $photo->placement == 4 ? 'Header Image two' : '' }} {{ $photo->placement == 5 ? 'Change Date time section image (Vue js payment page)' : '' }} {{ $photo->placement == 6 ? 'Frontend package image (Vue js package list page)' : '' }} {{ $photo->placement == 7 ? 'Email template image' : '' }}</div>
												@if (!Storage::disk('s3')->missing('images/tour-operator/' . $tourPackage->tourOperator->tenant_id . '/package/' . $tourPackage->id . '/' . $photo->path))
												<div><img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourPackage->tourOperator->tenant_id . '/package/' . $tourPackage->id . '/' . $photo->path) }}" class="img-pckg"></div>
												@else
												<div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg"></div>
												@endif
												<div class="mt-2 flex items-center justify-between">
													<label class="form-custom-label inline-block" title="Activate/deactivate photo">
														<input type="checkbox" value="{{ $photo->id }}" onchange="updatePhotoStatus(this)" {{ $photo->status == 'Active' ? 'checked' : '' }}>
														<span>{{ $photo->status }}</span>
													</label>

													<button type="button" class="btn btn-sm btn-delete btn-danger-small" data-url="{{ route($routePrefix . '.package.delete-photo', [ 'id' => $photo->id ]) }}" data-tag-to-remove=".img-pckg-container"><i class="far fa-trash-alt"></i></button>
												</div>
											</div>
											@endforeach
										</div>
									</div>
								</div>

								<div class="tab-pane" id="tab-rate-groups" role="tabpanel" aria-labelledby="tab-rate-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										@include('layouts._list-package-rate-for')

										<div class="row">
											<div class="col-12 col-md-2 mb-3">
												<label class="form-label form-custom-label">Scheduling Year</label>
												<select class="form-select form-custom-input" id="scheduling_year" onchange="return updateUrlForYear(this)" required>
													<option value="">Select Scheduling Year</option>
													@for($i = date('Y'); $i < date('Y') + 7; $i++)
														<option {{ request()->year == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
														@endfor
												</select>
											</div>
										</div>

										<table width="100%">
											<tbody id="tbody-pckg-rate-grps"></tbody>

											<tfoot class="d-none">
												<tr id="tmpl-pckg-rate-grp" class="pckg-rate-grp">
													<td>
														<form method="post" class="form-pckg-rate-grp" action="{{ route($routePrefix . '.package.add-update-rate', [ 'tourPackageId' => $tourPackage->id ]) }}" onsubmit="if(!validateCharacters('#form-age-rate-grp-' + this.id + ' .txt-rate-for'))return false;return submitForm(this)" data-on-success-msg="Rate details saved." data-call-on-success="pckgRateGrpSaved">
															<input type="hidden" data-name="tour_package_rate_id" class="form-control form-custom-input hid-rate-id">
															<input type="hidden" data-name="scheduling_year" class="form-control form-custom-input schedulingYear">
															<table width="100%" class="mb-2">
																<tr>
																	<th class="pb-1">Rate For <req>*</req>
																	</th>
																	<th class="pb-1">Age From</th>
																	<th class="pb-1">Age To</th>
																	<th class="pb-1">Rate <req>*</req>
																	</th>
																	<th class="pb-1">Permit Fee</th>
																	<th class="pb-1">Tax</th>
																	<th class="pb-1">Min Pax Allowed</th>
																	<th class="pb-1">Max Pax Allowed </th>
																	<td rowspan="2" class="text-center">
																		<button class="btn btn-primary btn-sm btn-save btn-primary-small" title="Add If Not Exist Else Update Package Rate Group">Save</button>
																		<button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="deletePckgRateGrp(this)" title="Remove Package Rate Group">X</button>
																	</td>
																</tr>

																<tr>
																	<td><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" placeholder="e.g.: Children" onblur="validateCharacters('.txt-rate-for')" data-user-display-label="Rate For (Age/Rate Group Title)"></td>
																	<td><input data-name="age_from" class="form-control form-custom-input txt-age-from" type="number" value="0" min="0" max="255" placeholder="Age From"></td>
																	<td><input data-name="age_to" class="form-control form-custom-input txt-age-to" type="number" value="0" min="0" max="255" placeholder="Age To"></td>
																	<td style="width: 126px">
																		<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
																			<span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">{{ currencyFormation(false, true) }}</span>
																			<input data-name="rate" class="form-control form-custom-input !rounded-l-none txt-rate txt-required" type="number" value="0" min="0" step="0.01" placeholder="Rate">
																		</div>
																	</td>
																	<td style="width: 126px">
																		<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
																			<span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">{{ currencyFormation(false, true) }}</span>
																			<input data-name="permit_fee" class="form-control form-custom-input !rounded-l-none txt-permit-fee" type="number" value="0" min="0" step="0.01" placeholder="Permit Fee">
																		</div>
																	</td>
																	<td>
																		<div class="w-full md:w-1/2 max-w-full px-3 input-group flex items-center">
																			<span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">{{ currencyFormation(false, true) }}</span>
																			<input data-name="additional_charge" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="0" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
																		</div>
																	</td>
																	<td>
																		<div class="input-group">
																			<input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="0" min="0" max="255" placeholder="Enter minimum people allowed" required>
																		</div>
																	</td>
																	<td>
																		<div class="input-group">
																			<input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="0" min="0" max="255" placeholder="Enter maximum people allowed">
																		</div>
																	</td>
																</tr>

																<tr>
																	<th colspan="2" class="py-1 descth">Description</th>
																	<th class="py-1">Order <req>*</req>
																	</th>
																	<th class="py-1">Mapping Key <req>*</req>
																	</th>
																	<th class="py-1">Global Rate</th>
																	<th class="py-1">Status</th>
																	@if($tourPackage->is_group_rate_enabled == '1')
																	<th class="py-1">Group Rate Min Pax</th>
																	<th class="py-1">Group Rate Max Pax</th>
																	@endif
																</tr>

																<tr>
																	<td colspan="2" class="descth">
																		<textarea data-name="description" class="form-control form-custom-input txt-desc max-h-16 min-h-16" placeholder="Description"></textarea>
																	</td>
																	<td class="align-top">
																		<div class="input-group">
																			<input data-name="orderby" class="form-control form-custom-input txt-orderby" type="number" value="0" min="0" max="100" placeholder="Order No." required>
																		</div>
																	</td>
																	<td class="align-top">
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
																	<td class="align-top">
																		<select data-name="global_rate" class="form-control form-custom-input select-global-rate">
																			<option value="0">No</option>
																			<option value="1">Yes</option>
																		</select>
																	</td>
																	<td class="align-top">
																		<select data-name="status" class="form-control form-custom-input sel-status">
																			<option>Active</option>
																			<option>Inactive</option>
																		</select>
																	</td>
																	@if($tourPackage->is_group_rate_enabled == '1')
																	<td class="align-top">
																		<div class="input-group">
																			<input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="0" min="0" placeholder="Enter minimum people for group">
																		</div>
																	</td>
																	<td class="align-top">
																		<div class="input-group">
																			<input data-name="max_group_size" class="form-control form-custom-input txt-max-group-size" type="number" value="0" min="0" placeholder="Enter maximum people for group">
																		</div>
																	</td>
																	@endif
																</tr>
															</table>
														</form>
													</td>
												</tr>
											</tfoot>
										</table>

										<div class="mt-2">
											<button type="button" id="btn-add-pckg-rate-group" class="btn btn-info btn-primary-small" onclick="addPckgRateGrp()">Add More Age/Rate Group</button>
										</div>
									</div>
								</div>
								<div class="tab-pane" id="tab-waiver" role="tabpanel" aria-labelledby="tab-waiver">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form class="form" method="post" action="{{ route($routePrefix . '.package.update-waiver', [ 'id' => $tourPackage->id ]) }}" onsubmit="return submitWaiverForm(this)" data-on-success-msg="Tour package's Waiver details updated.">
											@csrf

											<div class="mb-3">
												<label class="form-label form-custom-label">Waiver Content</label>
												<p class="text-xs text-[#6B7280] mb-2">Used for the standard customer-facing liability waiver.</p>
												<div id="quillArea">
													<?php echo $tourPackage->waiver_content; ?>
												</div>
												<textarea style="display:none" class="form-control form-custom-input max-h-16 min-h-16" name="waiver_content" id="waiver_content" placeholder="Enter content for Waiver"></textarea>
											</div>

											<hr class="my-4 border-[#E5E7EB]">

											<div class="mb-3">
												<label class="form-label form-custom-label">School Group Waiver Content</label>
												<p class="text-xs text-[#6B7280] mb-2">Shown on the school waiver link shared with parents/guardians. If left empty, the standard Waiver Content above is used.</p>
												<div id="quillSchoolWaiverArea">
													<?php echo $tourPackage->school_waiver_content; ?>
												</div>
												<textarea style="display:none" class="form-control form-custom-input max-h-16 min-h-16" name="school_waiver_content" id="school_waiver_content" placeholder="Enter content for School Group Waiver"></textarea>
											</div>
											@if($tourPackage->attach_waiver_pdf == 1)
											<div class="mb-3">
												<label for="waiver_pdf" class="form-label form-custom-label">Waiver PDF</label> 
												<input type="file" name="waiver_pdf" id="waiver_pdf" class="form-control form-custom-input" data-allowed-ext="pdf">
											</div>
											@endif
											@if($tourPackage->attach_waiver_pdf == 1 && $tourPackage->waiver_pdf != '')
												<div id="divWaiverPdf">
													<a target="_blank" class="btn btn-md btn-primary btn-info-small mb-3" href="{{ waiverPdfPath($tourPackage->waiver_pdf) }}">View PDF</a>
													<button type="button" id="deleteWaiverPdf" class="btn btn-sm btn-danger-small mb-3">Delete PDF</button>
												</div>
											@endif

											<div class="mt-4 text-end"><button class="btn btn-main btn-primary-small">Update</button></div>
										</form>
									</div>
								</div>
								@if (hasSetting('HOTEL_PICKUP'))
								<div class="tab-pane" id="tab-hotels" role="tabpanel" aria-labelledby="tab-hotels">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form method="post" enctype="multipart/form-data" class="form-pckg-hotels-grp" action="{{ route($routePrefix . '.package.add-hotel', [ 'tourPackageId' => $tourPackage->id ]) }}"> <!-- onsubmit="return submitForm(this)" data-on-success-msg="Hotel saved." data-call-on-success="pckgHotelGrpSaved" -->
											@csrf
											<input type="hidden" name="tour_operator_id" value="{{ $tourPackage->tour_operator_id }}">
											<input type="hidden" name="tour_package_id" value="{{ $tourPackage->id }}">
											<div class="row">
												<div class="col-12 col-md-3">
													<label for="name" class="form-label form-custom-label">Hotel Name <req>*</req></label>
													<input class="form-control form-custom-input" name="name" id="hotel-name" maxlength="100" placeholder="Enter hotel name" required>
												</div>

												<div class="col-12 col-md-3">
													<label for="email" class="form-label form-custom-label">Email </label>
													<input type="email" class="form-control form-custom-input" name="email" id="hotel-email" maxlength="100" placeholder="Enter hotel email">
												</div>

												<div class="col-12 col-md-3">
													<label for="phone_number" class="form-label form-custom-label">Phone Number </label>
													<input type="number" class="form-control form-custom-input" name="phone_number" id="hotel-phone-number" maxlength="30" placeholder="Enter hotel phone number">
												</div>

												<div class="col-12 col-md-3">
													<label for="status" class="form-label form-custom-label">Status <req>*</req></label>
													<select class="form-select form-custom-input" name="status" id="hotel-status" required>
														<option value="Active">Active</option>
														<option value="Inactive">Inactive</option>
													</select>
												</div>

												<div class="col-12 col-md-9 mt-1">
													<label for="address" class="form-label form-custom-label">Address <req>*</req></label>
													<textarea class="form-control form-custom-input max-h-16 min-h-16" name="address" id="address" required></textarea>
												</div>

												<div class="col-12 col-md-3 mt-1">
													<label for="hotel_image" class="form-label form-custom-label">Hotel Image</label>
													<input type="file" name="hotel_image" id="hotel_image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,png">
												</div>

												<div class="col-12 col-md-9 mt-1">
													<label for="location_url" class="form-label form-custom-label">Location URL <req>*</req></label>
													<input type="text" class="form-control form-custom-input" name="location_url" id="hotel-location-url" placeholder="Enter hotel location URL" required>
												</div>
											</div>

											<div class="mt-2 text-end"><button class="btn btn-md btn-primary btn-primary-small">Add</button></div>
										</form>

										<div>
											<table class="table table-bordered table-hover mt-3 mb-0 tbl-price w-full">
												<thead>
													<tr>
														<th class="th-custom-css text-left">Image</th>
														<th class="th-custom-css text-left">Name</th>
														<th class="th-custom-css text-left">URL</th>
														<th class="th-custom-css text-left">Address</th>
														<th class="th-custom-css text-left">Status</th>
														<th class="th-custom-css text-left">Action</th>
													</tr>
												</thead>
												<tbody style="vertical-align: middle;">
													@if ($hotels->count())
													@foreach ($hotels as $hotel)
													<tr>
														<td class="td-custom-css"><img src="{{ hotelImage($tourPackage->tourOperator->tenant_id, $tourPackage->id, $hotel->id, $hotel->image) }}" width="50" height="50" class="img-hotel"></td>
														<td class="td-custom-css">{{ $hotel->name }}</td>
														<td class="td-custom-css text-center"><a href="{{ $hotel->location_url }}" target="__blank"><i class="fa fa-link mr-1"></i></a></td>
														<td class="td-custom-css">{{ $hotel->address }}</td>
														<td class="td-custom-css">{!! $hotel->status == 'Active' ? '<span class="badge green-bg-color">Active</span>' : '<span class="badge red-bg-color">Inactive</span>' !!}</td>
														<td class="td-custom-css">
															<button class="btn btn-sm btn-edit btn-primary-small" data-bs-toggle="modal" data-id="{{$hotel->id}}" data-bs-target="#editHotel-{{ $hotel->id }}">
																<i class="fa fa-edit mr-2"></i>
																Edit
															</button>
															<button data-url="{{ route($routePrefix . '.package.delete-hotel', [ 'hotelId' => $hotel->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
																<i class="far fa-trash-alt mr-2"></i>
																Delete
															</button>
														</td>
													</tr>
													<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="editHotel-{{ $hotel->id }}" tabindex="-1" role="dialog" aria-labelledby="hotelModalLongTitle-{{ $hotel->id }}" aria-hidden="true">
														<div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center" role="document">
															<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
																<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
																	<h5 class="modal-title text-lg font-semibold text-[#111827]" id="hotelModalLongTitle-{{ $hotel->id }}">Edit Hotel</h5>
																	<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
																</div>
																<form method="post" enctype="multipart/form-data" class="form-pckg-relation-grp" action="{{ route("{$routePrefix}.package.update-hotel", $hotel->id) }}">
																	@csrf
																	<div class="modal-body p-4">
																		<div class="flex flex-wrap -mx-3">
																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="name" class="form-label form-custom-label">Hotel Name <req>*</req></label>
																				<input class="form-control form-custom-input" name="name" value="{{ $hotel->name }}" id="update-hotel-name-{{ $hotel->id }}" maxlength="100" placeholder="Enter hotel name" required>
																			</div>

																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="email" class="form-label form-custom-label">Email </label>
																				<input type="email" class="form-control form-custom-input" name="email" value="{{ $hotel->email }}" id="update-hotel-email-{{ $hotel->id }}" maxlength="100" placeholder="Enter hotel email">
																			</div>

																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="phone_number" class="form-label form-custom-label">Phone Number </label>
																				<input type="number" class="form-control form-custom-input" name="phone_number" value="{{ $hotel->phone_number }}" id="update-hotel-phone-number-{{ $hotel->id }}" maxlength="30" placeholder="Enter hotel phone number">
																			</div>

																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="status" class="form-label form-custom-label">Status <req>*</req></label>
																				<select class="form-select form-custom-input" name="status" id="update-hotel-status-{{ $hotel->id }}" required>
																					<option value="Active" {{ $hotel->status == 'Active' ? 'selected' : '' }}>Active</option>
																					<option value="Inactive" {{ $hotel->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
																				</select>
																			</div>

																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="address" class="form-label form-custom-label">Address <req>*</req></label>
																				<textarea class="form-control form-custom-input max-h-16 min-h-16" name="address" id="address-{{ $hotel->id }}" required>{{ $hotel->address }}</textarea>
																			</div>

																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="hotel_image" class="form-label form-custom-label">Hotel Image</label>
																				<input type="file" name="hotel_image" id="update-hotel-image-{{ $hotel->id }}" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,png">
																			</div>

																			<div class="w-full max-w-full px-3 mb-2">
																				<label for="location_url" class="form-label form-custom-label">Location URL <req>*</req></label>
																				<input type="text" class="form-control form-custom-input" name="location_url" value="{{ $hotel->location_url }}" id="update-hotel-location-url-{{ $hotel->id }}" placeholder="Enter hotel location URL" required>
																			</div>
																		</div>
																	</div>
																	<div class="modal-footer flex items-center px-4 pb-4">
																		<button class="btn btn-md btn-primary btn-primary-small mr-2">Update</button>
																		<button type="button" class="btn btn-secondary btn-danger-small" data-bs-dismiss="modal">Close</button>
																	</div>
																</form>
															</div>
														</div>
													</div>
													@endforeach
													@else
													<tr>
														<td colspan="6" align="center">
															No Hotels Found.
														</td>
													</tr>
													@endif
												</tbody>
											</table>
										</div>
									</div>
								</div>
								@endif
								<div class="tab-pane" id="tab-relation-groups" role="tabpanel" aria-labelledby="tab-relation-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										@if ($nonBundlePackages->count() > 0)
										<fieldset class="border border-[#ccc] rounded-md p-1.5 mt-3">
											<legend class="-mt-6 ml-3 bg-white text-center inline-block px-5"> Bundle Package </legend>
											<form method="post" class="form-pckg-relation-grp" action="{{ route($routePrefix . '.package.add-bundle-relation', [ 'tourPackageId' => $tourPackage->id ]) }}" onsubmit="return submitForm(this)" data-on-success-msg="Bundle Relation details saved." data-call-on-success="pckgBundleRelationSaved">
												@csrf
												<input type="hidden" name="tour_operator_id" value="{{ $tourPackage->tour_operator_id }}">
												<input type="hidden" name="parent_package_id" value="{{ $tourPackage->id }}">
												<div class="flex flex-wrap -mx-3" style="padding-left: 15px;">
													<div class="w-full md:w-1/3 max-w-full px-3 mb-3">
														<label for="tour-package" class="form-label form-custom-label">Tour Package</label>
														<select class="form-select form-custom-input" name="tour_package_id" id="tour-package">
															@foreach ($nonBundlePackages as $package)
															<option value="{{ $package->id }}" {{ $package->id == $tourPackage->id ? 'selected' : '' }}>{{ $package->name }}</option>
															@endforeach
														</select>
													</div>
													<div class="w-full md:w-1/3 max-w-full px-3 mb-3">
														<button class="btn btn-md btn-primary btn-primary-small" style="margin-top: 1.8rem;">Add</button>
													</div>
												</div>
												<!-- <div class="mt-2 text-end"><button class="btn btn-md btn-primary">Add</button></div> -->
											</form>

											<div class="px-3 pb-3">
												<table class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse text-left  tbl-price">
													<thead>
														<tr>
															<th class="th-custom-css">Parent Package</th>
															<th class="th-custom-css">Package Name</th>
															<th class="th-custom-css">Action</th>
														</tr>
													</thead>
													<tbody style="vertical-align: middle;">
														@if ($bundlePackages->count())
														@foreach ($bundlePackages as $package)
														<tr>
															<td class="td-custom-css">{{ $tourPackage->name }}</td>
															<td class="td-custom-css">{{ $package->package_name }}</td>
															<td class="td-custom-css">
																<button data-url="{{ route($routePrefix . '.package.delete-bundle-relation', [ 'tourBundlePackageId' => $package->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
																	<i class="far fa-trash-alt mr-2"></i>
																	Delete
																	</a>
															</td>
														</tr>
														@endforeach
														@else
														<tr>
															<td class="td-custom-css" colspan="3" align="center">
																No Records Found.
															</td>
														</tr>
														@endif
													</tbody>
												</table>
											</div>
										</fieldset>
										@endif

						@if ($allTourPackages->count() > 1)
						<fieldset style="border: 1px solid #ccc; border-radius: 5px; padding:5px; margin-top: 35px;">
							<legend style="margin-top:-25px;margin-left: 12px;background-color:#fff;text-align:center;width: 17%;"> Package Relations </legend>
							<form method="post" class="form-pckg-relation-grp" action="{{ route($routePrefix . '.package.add-relation', [ 'tourPackageId' => $tourPackage->id ]) }}" onsubmit="return submitForm(this)" data-on-success-msg="Relation details saved." data-call-on-success="pckgRelationGrpSaved">
								@csrf
								<input type="hidden" name="tour_operator_id" value="{{ $tourPackage->tour_operator_id }}">
								<input type="hidden" name="parent_package_id" value="{{ $tourPackage->id }}">
								<input type="hidden" name="relation_year" value="{{ $year }}">
								<div class="flex flex-wrap -mx-3" style="padding: 0px 15px 0px 15px;">
													<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-3">
														<label for="tour-package" class="form-label form-custom-label">Tour Package</label>
														<select class="form-select form-custom-input" name="tour_package_id" id="tour-package">
															@foreach ($allTourPackages as $package)
															<option value="{{ $package->id }}" {{ $package->id == $tourPackage->id ? 'selected' : '' }}>{{ $package->name }}</option>
															@endforeach
														</select>
													</div>

													<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-3">
														<label for="time-from" class="form-label form-custom-label">Time From</label>
														<div class="flex items-center">
															<input type="hidden" name="time_from" class="hid-time hid-time-from" value="00:00:00">
															<select class="form-select mr-1 form-custom-input sel-time-hour sel-time-from-hour" onchange="setTime(this)" onblur="setTime(this)">
																@for($a = 1; $a < 12; $a++)
																	<option>{{ $a }}</option>
																	@endfor
																	<option value="0">12</option>
															</select>:
															<select class="form-select ml-1 form-custom-input sel-time-minute sel-time-from-minute" onchange="setTime(this)" onblur="setTime(this)">
																@for($a = 0; $a < 60; $a++)
																	<option>{{ sprintf('%02d', $a) }}</option>
																	@endfor
															</select>
															<select class="form-select ml-1 form-custom-input sel-time-am-pm sel-time-from-am-pm" onchange="setTime(this)" onblur="setTime(this)">
																<option>am</option>
																<option>pm</option>
															</select>
														</div>
													</div>

													<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-3">
														<label for="group-index" class="form-label form-custom-label">Group Index</label>
														<select class="form-select form-custom-input" name="group_index" id="group-index">
															@for($groupIndex = 1; $groupIndex <= 50; $groupIndex++)
																<option value="{{ $groupIndex }}">{{ $groupIndex }}</option>
																@endfor
														</select>
													</div>

													<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-3">
														<label for="is-parent" class="form-label form-custom-label">Is Parent?</label>
														<select class="form-select form-custom-input" name="is_parent" id="is-parent">
															<option value="1">Yes</option>
															<option value="0">No</option>
														</select>
													</div>
												</div>

												<div class="w-full max-w-full px-3 flex justify-end"><button class="btn btn-md btn-primary btn-primary-small">Add</button></div>
											</form>

											<div class="overflow-y-auto" style="padding: 0px 15px 10px 15px;">
												<table class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse text-left tbl-price" id="tbl-package-relations">
													<thead>
														<tr>
															<th class="th-custom-css">Package Name</th>
															<th class="th-custom-css">Time From</th>
															<th class="th-custom-css">Group Index</th>
															<th class="th-custom-css">Is Parent?</th>
															<th class="th-custom-css">Action</th>
														</tr>
													</thead>
													<tbody style="vertical-align: middle;">
														@if ($tourPackageFamilies->count())
														@foreach ($tourPackageFamilies as $family)
														<tr>
															<td class="td-custom-css">{{ $family->tourPackage->name }}</td>
															<td class="td-custom-css">{{ formatTime($family->time_from) }}</td>
															<td class="td-custom-css" style="background-color: {{ relationColor($family->group_index) }};">{{ $family->group_index }}</td>
															<td class="td-custom-css">{!! $family->is_parent ? '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full">Yes</span>' : '<span class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#1E40AF] border border-[#DBEAFE] bg-[#DBEAFE] rounded-full">No</span>' !!}</td>
															<td class="td-custom-css">
																<button data-url="{{ route($routePrefix . '.package.delete-relation', [ 'tourPackageFamilyId' => $family->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
																	<i class="far fa-trash-alt mr-2"></i>
																	Delete
																	</a>
															</td>
														</tr>
														@endforeach
														@else
														<tr>
															<td class="td-custom-css" colspan="5" align="center">
																No Records Found.
															</td>
														</tr>
														@endif
													</tbody>
												</table>
											</div>
										</fieldset>
										@endif
									</div>
								</div>

								<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="editCategory" tabindex="-1" role="dialog" aria-labelledby="exampleModalLongTitle" aria-hidden="true">
									<div class="modal-dialog modal-lg max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center" role="document">
										<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
											<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
												<h5 class="modal-title text-lg font-semibold text-[#111827]" id="exampleModalLongTitle">Edit Category</h5>
												<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
											</div>
											<form method="post" enctype="multipart/form-data" class="form-pckg-relation-grp" action="{{ route($routePrefix . '.package.update-category') }}">
												@csrf
												<input type="hidden" name="tour_operator_id" value="{{ $tourPackage->tour_operator_id }}">
												<input type="hidden" name="tour_package_id" value="{{ $tourPackage->id }}">
												<input type="hidden" name="tour_package_image_id" value="" id="tour_package_image_id">
												<div class="modal-body p-4">
													<div class="mb-3">
														<label for="cap" class="form-label form-custom-label">Caption <req>*</req></label>
														<input class="form-control form-custom-input" name="caption" id="cap" maxlength="100" placeholder="Caption" required>
													</div>

													<div class="mb-3">
														<label for="cat" class="form-label form-custom-label">Category <req>*</req></label>
														<!-- <input class="form-control form-custom-input" name="category" id="cat" maxlength="100" placeholder="Category" required> -->
														<select class="form-control form-custom-input" name="category" id="cat" required>
															<option selected disabled>Please select category</option>
															@foreach($category_type as $category)
															<option value="{{$category}}">{{$category}}</option>
															@endforeach
														</select>
													</div>

													<div class="mb-3">
														<label for="cat_image" class="form-label form-custom-label">Image <req>*</req></label>
														<input type="file" name="category_image" id="cat_image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,png">
													</div>
													<div class="flex items-center">
														<button type="button" class="btn btn-secondary w-full btn-danger-small" data-bs-dismiss="modal">Close</button>
														<button class="btn btn-md btn-primary ml-3 w-full btn-primary-small">Update</button>
													</div>
												</div>
											</form>
										</div>
									</div>
								</div>

								<div class="tab-pane" id="tab-categories-groups" role="tabpanel" aria-labelledby="tab-categories-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form method="post" enctype="multipart/form-data" action="{{ route($routePrefix . '.package.add-category') }}">
											@csrf
											<input type="hidden" name="tour_operator_id" value="{{ $tourPackage->tour_operator_id }}">
											<input type="hidden" name="tour_package_id" value="{{ $tourPackage->id }}">
											<div class="flex flex-wrap -mx-3">
												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3">
													<label for="caption" class="form-label form-custom-label">Caption <req>*</req></label>
													<input class="form-control form-custom-input" name="caption" id="caption" maxlength="100" placeholder="Caption" required>
												</div>

												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3">
													<label for="category" class="form-label form-custom-label">Category <req>*</req></label>
													<!-- <input class="form-control form-custom-input" name="category" id="category" maxlength="100" placeholder="Category" required> -->
													<select class="form-control form-custom-input" name="category" id="category" required>
														<option selected disabled>Please select category</option>
														@foreach($category_type as $category)
														<option value="{{$category}}">{{$category}}</option>
														@endforeach
													</select>
												</div>

												<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3">
													<label for="category_image" class="form-label form-custom-label">Image <req>*</req></label>
													<input type="file" name="category_image" id="category_image" class="form-control form-custom-input" data-allowed-ext="jpg,jpeg,png" required>
												</div>
											</div>

											<div class="mt-2 flex justify-end"><button class="btn btn-md btn-primary btn-primary-small">Add</button></div>
										</form>

										<div class="overflow-y-auto">
											<table class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse text-left tbl-price">
												<thead>
													<tr>
														<th class="th-custom-css">Image</th>
														<th class="th-custom-css">Caption</th>
														<th class="th-custom-css">Category</th>
														<th class="th-custom-css">Action</th>
													</tr>
												</thead>
												<tbody style="vertical-align: middle;">
													@if ($tourPackageImage->count())
													@foreach ($tourPackageImage as $category)
													<tr>
														<td class="td-custom-css"><img src="{{ $category->url }}" width="50" height="50" class="img-hotel"></td>
														<td class="td-custom-css">{{ $category->caption }}</td>
														<td class="td-custom-css">{{ $category->image}}</td>
														<td class="td-custom-css">
															<button class="btn btn-sm btn-edit btn-primary-small" data-bs-toggle="modal" data-captions="{{$category->caption}}" data-image="{{$category->image}}" data-id="{{$category->id}}" data-cat-image="{{$category->url}}" data-bs-target="#editCategory">
																<i class="fa fa-edit mr-2"></i>
																Edit
															</button>
															<button data-url="{{ route($routePrefix . '.package.delete-category', [ 'categoryId' => $category->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
																<i class="far fa-trash-alt mr-2"></i>
																Delete
															</button>
														</td>
													</tr>
													@endforeach
													@else
													<tr>
														<td class="td-custom-css" colspan="6" align="center">
															No Category Found.
														</td>
													</tr>
													@endif
												</tbody>
											</table>
										</div>
									</div>
								</div>

								<div class="tab-pane" id="tab-iframe-groups" role="tabpanel" aria-labelledby="tab-iframe-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<fieldset class="border border-[#ccc] rounded-md p-1.5">
											<legend class="-mt-6 ml-3 bg-white text-center inline-block px-5"> Single Package iFrame </legend>
											<div class="flex flex-wrap -mx-3" style="padding-left: 15px;">
												<div class="w-full md:w-2/3 max-w-full px-3">
													@if(app()->environment('staging'))
													<input class="form-custom-input" type="text" id="iframe_url" value="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&pid=' . $tourPackage->id}}" name="iframe_url">
													@elseif (app()->environment('production'))
													<input class="form-custom-input" type="text" id="iframe_url" value="{{ config('constants.environment_iframe.production') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&pid=' . $tourPackage->id}}" name="iframe_url">
													@elseif (app()->environment('development'))
													<input class="form-custom-input" type="text" id="iframe_url" value="{{ config('constants.environment_iframe.development') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&pid=' . $tourPackage->id}}" name="iframe_url">
													@else
													<input class="form-custom-input" type="text" id="iframe_url" value="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&pid=' . $tourPackage->id}}" name="iframe_url">
													@endif
												</div>
												<div class="w-full md:w-1/3 max-w-full px-3">
													<div style="display: flex;">
														<button class="btn btn-secondary btn-sm btn-primary-small" onclick="iFrameCopy('iframe_url')">Link Copy</button>
														<span class="hidden p-2" id="custom-tooltip">copied!</span>
													</div>
												</div>
											</div>

											@if(app()->environment('staging'))
											<iframe class="max-w-full" src="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&pid='.$tourPackage->id }}" title="Iframe" height="400" width="1300"></iframe>
											@elseif (app()->environment('production'))
											<iframe class="max-w-full" src="{{ config('constants.environment_iframe.production') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&pid='.$tourPackage->id }}" title="Iframe" height="400" width="1300"></iframe>
											@elseif (app()->environment('development'))
											<iframe class="max-w-full" src="{{ config('constants.environment_iframe.development') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&pid='.$tourPackage->id }}" title="Iframe" height="400" width="1300"></iframe>
											@else
											<iframe class="max-w-full" src="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&pid='.$tourPackage->id }}" title="Iframe" height="400" width="1300"></iframe>
											@endif

										</fieldset>
										@if($tourPackage->is_combo_package == 1)
										<fieldset class="border border-[#ccc] rounded-md p-1.5 mt-9">
											<legend class="-mt-6 ml-3 bg-white text-center inline-block px-5"> Combo Package iFrame </legend>
											<div class="flex flex-wrap -mx-3" style="padding-left: 15px;">
												<div class="w-full md:w-3/4 max-w-full px-3 panel">
													<h5 class="categorized-title font-normal text-[#020817] text-sm my-2">Mandatory Package</h5>
													<div class="form-switch form-switch-wrap document-panel">
														<label class="form-custom-label inline-block">{{ $tourPackage->name }}</label>
														<input class="" type="checkbox" id="selectedPackage" name="selectedPackage" disabled checked>
													</div>
													<h5 class="categorized-title font-normal text-[#020817] text-sm my-2">Secondary Packages</h5>
													@foreach($comboTourPackages as $comboTourPackage)
													<div class="form-switch form-switch-wrap document-panel">
														<label class="form-custom-label inline-block">{{ $comboTourPackage->name }}</label>
														<input class="secondary_package" type="checkbox" value="{{ $comboTourPackage->id }}" id="{{ $comboTourPackage->id }}" name="secondarypackage">
													</div>
													@endforeach
												</div>
												<div class="w-full md:w-3/4 max-w-full px-3">
													<div class="flex flex-wrap -mx-3">
														<div class="w-full md:w-2/3 max-w-full px-3">
															@if(app()->environment('staging'))
															<textarea class="form-control form-custom-input max-h-16 min-h-16" id="iframe_combo_url" name="iframe_combo_url">{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&cids=' . $tourPackage->id}}</textarea>
															@elseif (app()->environment('production'))
															<textarea class="form-control form-custom-input max-h-16 min-h-16" id="iframe_combo_url" name="iframe_combo_url">{{ config('constants.environment_iframe.production') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&cids=' . $tourPackage->id}}</textarea>
															@elseif (app()->environment('development'))
															<textarea class="form-control form-custom-input max-h-16 min-h-16" id="iframe_combo_url" name="iframe_combo_url">{{ config('constants.environment_iframe.development') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&cids=' . $tourPackage->id}}</textarea>
															@else
															<textarea class="form-control form-custom-input max-h-16 min-h-16" id="iframe_combo_url" name="iframe_combo_url">{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid=' .$tourPackage->tour_operator_id .'&cids=' . $tourPackage->id}}</textarea>
															@endif
														</div>
														<div class="w-full md:w-1/3 max-w-full px-3">
															<div style="display: flex;">
																<button class="btn btn-secondary btn-sm btn-primary-small" onclick="iFrameCopy('iframe_combo_url')">Link Copy</button>

																<span id="custom-tooltip-combo">copied!</span>
															</div>
														</div>
													</div>
													<div class="flex flex-wrap -mx-3">
														@if(app()->environment('staging'))
														<iframe class="max-w-full" id="iframe_combo" src="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&cids='.$tourPackage->id }}" title="Iframe" height="800" width="1300"></iframe>
														@elseif (app()->environment('production'))
														<iframe class="max-w-full" id="iframe_combo" src="{{ config('constants.environment_iframe.production') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&cids='.$tourPackage->id }}" title="Iframe" height="800" width="1300"></iframe>
														@elseif (app()->environment('development'))
														<iframe class="max-w-full" id="iframe_combo" src="{{ config('constants.environment_iframe.development') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&cids='.$tourPackage->id }}" title="Iframe" height="800" width="1300"></iframe>
														@else
														<iframe class="max-w-full" id="iframe_combo" src="{{ config('constants.environment_iframe.staging') .  'iframe=true&tid='.tourOperator()->tenant_id.'&oid='.$tourPackage->tour_operator_id.'&cids='.$tourPackage->id }}" title="Iframe" height="800" width="1300"></iframe>
														@endif
													</div>
												</div>
											</div>

										</fieldset>
										@endif
									</div>
								</div>

								<div class="tab-pane" id="tab-affiliate-groups" role="tabpanel" aria-labelledby="tab-affiliate-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<div class="flex flex-wrap -mx-3">
											<div class="w-full md:w-1/3 max-w-full px-3 panel">
												<h6 class="categorized-title my-2 font-normal text-gray-600 text-base">Affiliates</h6>
												@foreach($affiliates as $affiliate)
												<div class="form-switch-package document-panel form-switch-wrap mb-2 min-h-[55px]">
													<label class="form-custom-label inline-block">{{ $affiliate->name }}</label>
													<input class="assigned_affiliate w-4 h-4 ml-2 align-text-top" type="checkbox" id="affiliate_{{ $affiliate->id }}" value="{{ $affiliate->id }}" name="assignedAffiliate" {{ ($assignedAffiliates->contains('affiliate_id',$affiliate->id) ? 'checked' : '' )}}>
												</div>
												@endforeach
											</div>
											<div class="w-full md:w-1/4 max-w-full px-3 panel">
												<h6 class="categorized-title my-2 font-normal text-gray-600 text-base">Show All Package Bookings</h6>
												@foreach($affiliates as $affiliate)
												@php
												$showAllPackageBookings = 0;
												if($assignedAffiliates->contains('affiliate_id',$affiliate->id)){
												$packageAffiliateAssignment = $assignedAffiliates->filter(function ($item) use ($affiliate){
												return $item->affiliate_id == $affiliate->id;
												})->values();
												$showAllPackageBookings = $packageAffiliateAssignment[0]->show_all_package_bookings;
												}
												@endphp
												<div class="form-switch form-switch-wrap document-panel mb-2">
													<select class="form-control form-custom-input show_all_package_bookings_package" name="show_all_package_bookings_package" id="{{ $affiliate->id }}">
														<option value="0" {{ ($showAllPackageBookings == 0) ? 'selected' : '' }}>No</option>
														<option value="1" {{ ($showAllPackageBookings == 1) ? 'selected' : '' }}>Yes</option>
													</select>
												</div>
												@endforeach
											</div>
										</div>
									</div>
								</div>

								<div class="tab-pane" id="tab-cancellation-policies" role="tabpanel" aria-labelledby="tab-cancellation-policies">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form method="post" enctype="multipart/form-data" action="{{ route($routePrefix . '.package.store-cancellation-policy') }}">
											@csrf
											<input type="hidden" name="operator_id" value="{{ $tourPackage->tour_operator_id }}">
											<input type="hidden" name="package_id" value="{{ $tourPackage->id }}">
											<div class="flex flex-wrap -mx-3">
												<div class="w-full max-w-full px-3 mb-3">
													<label for="policy_title" class="form-label form-custom-label">Title <req>*</req></label>
													<input type="text" class="form-control form-custom-input" name="title" id="policy_title" maxlength="500" placeholder="Title" required>
												</div>

												<div class="w-full max-w-full px-3 mb-3">
													<label for="policy_description" class="form-label form-custom-label">Description <req>*</req></label>
													<textarea class="form-control form-custom-input max-h-16 min-h-16" name="description" id="policy_description" rows="2"></textarea>
												</div>
												<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="policy_days_hours_before" class="form-label form-custom-label">Days Hours Before <req>*</req></label>
													<input type="number" class="form-control form-custom-input" name="days_hours_before" id="policy_days_hours_before" placeholder="Days Hours Before" required>
												</div>

												<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="policy_type" class="form-label form-custom-label">Type <req>*</req></label>
													<select class="form-control form-custom-input" name="type" required>
														<option value="days">Days</option>
														<option value="hours">Hours</option>
													</select>
												</div>

												<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="policy_refund_basis" class="form-label form-custom-label">Refund Basis <req>*</req></label>
													<select class="form-control form-custom-input" name="refund_basis" id="refund_basis" onchange="changeRefundPercent()" required>
														<option value="ticket_cost">Ticket Cost</option>
														<option value="no_refund">No Refund</option>
													</select>
												</div>

												<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="policy_refund_percent" class="form-label form-custom-label">Refund Percent <req>*</req></label>
													<input type="number" class="form-control form-custom-input" name="refund_percent" id="refund_percent" placeholder="Refund Percent" required>
												</div>

											</div>

											<div class="mt-2 flex justify-end"><button class="btn btn-md btn-primary btn-primary-small">Add</button></div>
										</form>

										<div class="w-full overflow-y-auto mt-2">
											<table id="table-cancellation-policies" class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse text-left tbl-price">
												<thead>
													<tr>
														<th class="th-custom-css">Title</th>
														<th class="th-custom-css">Days Hours Before</th>
														<th class="th-custom-css">Refund Basis</th>
														<th class="th-custom-css">Refund Percent</th>
														<th class="th-custom-css">Action</th>
													</tr>
												</thead>
												<tbody style="vertical-align: middle;">
													@if ($cancellationPolicies->count())
													@foreach ($cancellationPolicies as $cancellationPolicy)
													<tr>
														<td class="td-custom-css">{{ $cancellationPolicy->title }}</td>
														<td class="td-custom-css">{{ $cancellationPolicy->days_hours_before .' '. $cancellationPolicy->type }}</td>
														<td class="td-custom-css">{{ $cancellationPolicy->refund_basis }}</td>
														<td class="td-custom-css">{{ $cancellationPolicy->refund_percent }}</td>
														<td class="td-custom-css whitespace-nowrap">
															<button data-url="{{ route($routePrefix . '.package.delete-cancellation-policy', [ 'id' => $cancellationPolicy->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small">
																<i class="far fa-trash-alt mr-2"></i>
																Delete
															</button>
														</td>
													</tr>
													@endforeach
													@else
													<tr>
														<td class="td-custom-css" colspan="6" align="center">
															No Cancellation Policies Found.
														</td>
													</tr>
													@endif
												</tbody>
											</table>
										</div>
									</div>
								</div>

								<div class="tab-pane" id="tab-custom-form-groups" role="tabpanel" aria-labelledby="tab-custom-form-groups">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md" id="tour-package-custom-form">

									</div>
								</div>

								<div class="tab-pane" id="tab-settings" role="tabpanel" aria-labelledby="tab-settings">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">

										@if(get_class(auth()->user()) == "App\Models\Admin")
										<div class="flex flex-wrap -mx-3 p-2">
											<fieldset class="border border-[#ccc] rounded-md p-3 w-full">
												<legend class="-mt-6 ml-3 bg-white text-center inline-block px-5"> Bank Debit:</legend>
												<div class="flex flex-wrap -mx-3" style="padding:20px;">
													<div class="form-switch flex items-center justify-between py-2.5 w-full document-panel">
														@if ( $tourPackage->stripe_destination_id )
														<label class="form-custom-label">Enable Stripe Connected Account Bank Debit On Negative Stripe Balance</label>
														<input class="form-check-input stripe-settings w-4 h-4"
															data-cmd="debit_negative_balances_on"
															data-acc="{{$tourPackage->stripe_destination_id}}"
															type="checkbox" id="debit_negative_balances" name="debit_negative_balances" />
														@else
														Stripe Account ID Missing.
														@endif
													</div>
												</div>
											</fieldset>
										</div>
										@endif

										<form method="post" enctype="multipart/form-data" action="{{ route($routePrefix . '.update-settings', [ 'id' => $tourPackage->id ]) }}" onsubmit="return submitForm(this);" data-on-success-msg="Tour package's settings updated.">
											@csrf
											@if(get_class(auth()->user()) == "App\Models\Admin")
											<div class="flex flex-wrap -mx-3 p-2">
												<fieldset class="border border-[#ccc] rounded-md p-1.5 mt-3 w-full">
													<legend class="-mt-6 ml-3 bg-white text-center inline-block px-5"> Stripe Information:</legend>

													<div class="flex flex-wrap -mx-3" style="padding:10px;">
														<div class="w-full md:w-1/3 max-w-full px-3 mb-3">
															<label for="service_commission_percentage" class="form-label form-custom-label">Service Commission Percentage <req>*</req></label>
															<input type="text" class="form-control form-custom-input" name="service_commission_percentage" id="service_commission_percentage" value="{{ $tourPackage->service_commission_percentage }}" placeholder="Enter service commission percentage" required>
														</div>

										<div class="w-full md:w-1/3 max-w-full px-3 mb-3">
											<label for="stripe_commission_percentage" class="form-label form-custom-label">Stripe Commission Percentage <req>*</req></label>
											<input type="text" class="form-control form-custom-input" name="stripe_commission_percentage" id="stripe_commission_percentage" value="{{ $tourPackage->stripe_commission_percentage }}" placeholder="Enter stripe commission percentage" required>
										</div>
									</div>
									<div class="flex flex-wrap -mx-3" style="padding:10px;">
										<div class="w-full md:w-1/3 max-w-full px-3 mb-3">
											<label for="next_year_service_commission_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - Service Commission Percentage <req>*</req></label>
											<input type="text" class="form-control form-custom-input" name="next_year_service_commission_percentage" id="next_year_service_commission_percentage" value="{{ $tourPackage->next_year_service_commission_percentage }}" placeholder="Enter service commission percentage" required>
										</div>

										<div class="w-full md:w-1/3 max-w-full px-3 mb-3">
											<label for="next_year_stripe_commission_percentage" class="form-label form-custom-label"><span style="color: red">{{ date('Y') + 1 }}</span> - Stripe Commission Percentage <req>*</req></label>
											<input type="text" class="form-control form-custom-input" name="next_year_stripe_commission_percentage" id="next_year_stripe_commission_percentage" value="{{ $tourPackage->next_year_stripe_commission_percentage }}" placeholder="Enter stripe commission percentage" required>
										</div>
									</div>
								</fieldset>
							</div>
							@endif

										
											<div class="flex flex-wrap -mx-3 mt-3">
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="from_name" class="form-label form-custom-label">Email From Name <req>*</req></label>
													<input type="text" class="form-control form-custom-input" name="from_name" id="from_name" value="{{ $tourPackage->from_name }}" placeholder="Enter email sender title" required>
												</div>

												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="from_email" class="form-label form-custom-label">Email From Address <req>*</req></label>
													<input type="text" class="form-control form-custom-input" name="from_email" id="from_email" value="{{ $tourPackage->from_email }}" placeholder="Enter sender email address" required>
												</div>

												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="latitude" class="form-label form-custom-label">Latitude </label>
													<input type="text" class="form-control form-custom-input" name="latitude" id="latitude" value="{{ $tourPackage->latitude }}" placeholder="Enter latitude">
												</div>

												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="longitude" class="form-label form-custom-label">Longitude </label>
													<input type="text" class="form-control form-custom-input" name="longitude" id="longitude" value="{{ $tourPackage->longitude }}" placeholder="Enter longitude">
												</div>
											</div>
											<div class="flex flex-wrap -mx-3 mt-3">
												<div class="w-full sm:w-1/2 lg:w-1/2 max-w-full px-3 mb-3">
													<label for="from_email_hotmail" class="form-label form-custom-label">Email From Address (For Hotmail Customers)</label>
													<p class="text-xs text-[#6B7280] mb-2">Optional. Only add an email address here if the Email From Address above is not reaching customers with Hotmail, Outlook, Live, or MSN emails — when set, it will be used as the sender address for those customers instead.</p>
													<input type="text" class="form-control form-custom-input" name="from_email_hotmail" id="from_email_hotmail" value="{{ $tourPackage->from_email_hotmail }}" placeholder="Enter Hotmail/Outlook sender email">
												</div>
											</div>
											<div class="flex flex-wrap -mx-3">
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="Order_by" class="form-label form-custom-label">Order No. <req>*</req></label>
													<input type="text" class="form-control form-custom-input" name="Order_by" id="Order_by" value="{{ $tourPackage->Order_by }}" placeholder="Enter package order no." required>
												</div>
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="overbook_seats" class="form-label form-custom-label">Overbook Seats <req>*</req></label>
													<input type="number" class="form-control form-custom-input" name="overbook_seats" id="overbook_seats" value="{{ $tourPackage->overbook_seats }}" placeholder="Enter Overbook Seats">
												</div>

												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="travelDuration" class="form-label form-custom-label">Buffer Time <req>*</req></label>
													<input type="text" name="travel_duration" value="{{ $tourPackage->travel_duration }}" id="travel_duration" class="form-control form-custom-input travel_duration" placeholder="XX:XX:00" data-inputmask-mask="99:99:00">
												</div>
												
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="Order_by" class="form-label form-custom-label">Package Colour <req>*</req></label>
													<input type="text" class="form-control form-custom-input colorpicker" name="package_colour" id="package_colour" value="{{ $tourPackage->package_colour }}" placeholder="Enter package colour" required>
												</div>
											</div>
											<div class="flex flex-wrap -mx-3">
												{{--<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="productCode" class="form-label form-custom-label" title="TripAdvisor Product Code">TripAdvisor Code</label>
													<input type="text" class="form-control form-custom-input" name="productCode" id="productCode" value="{{ $tourPackage->productCode }}" placeholder="Enter TripAdvisor Product Code">
												</div>
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="get_your_guide_product_code" class="form-label form-custom-label" title="GetYourGuide Product Code">GetYourGuide Product Code</label>
													<input type="text" class="form-control form-custom-input" name="get_your_guide_product_code" id="get_your_guide_product_code" value="{{ $tourPackage->get_your_guide_product_code }}" placeholder="Enter GetYourGuide Product Code" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');" title="Only alphabets and numbers allowed">
												</div>--}}
												@if(get_class(auth()->user()) == "App\Models\Admin")
													<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
														<label for="bokun_product_code" class="form-label form-custom-label" title="Bokun Product Code">Bokun Product Code</label>
														<input type="text" class="form-control form-custom-input" name="bokun_product_code" id="bokun_product_code" value="{{ $tourPackage->bokun_product_code }}" placeholder="Enter Bokun Product Code" oninput="this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');" title="Only alphabets and numbers allowed">
														@if(!empty($suggestedBokunCodes))
															<div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-gray-500">
																<span>Suggestion:</span>
																<code class="px-1 bg-gray-100 rounded cursor-pointer" onclick="document.getElementById('bokun_product_code').value='{{ $suggestedBokunCodes[0] }}'">
																	{{ $suggestedBokunCodes[0] }}
																</code>
																<code class="px-1 bg-gray-100 rounded cursor-pointer" onclick="document.getElementById('bokun_product_code').value='{{ $suggestedBokunCodes[1] }}'">
																	{{ $suggestedBokunCodes[1] }}
																</code>
															</div>
														@endif
													</div>
												@endif
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3" style="{{ ($tourPackage->checkin_type != 1) ? 'display:none;': '' }}" id="booking_expiration">
													<label for="expire_in_days" class="form-label form-custom-label">Booking Expire In Days</label>
													<input type="number" class="form-control form-custom-input" name="expire_in_days" id="expire_in_days" min="0" value="{{ $tourPackage->expire_in_days }}" placeholder="Enter expire duration in days">
												</div>
												<div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
													<label for="arrival_time_duration" class="form-label form-custom-label">Arrival Time Duration In Minutes</label>
													<input type="number" class="form-control form-custom-input" name="arrival_time_duration" id="arrival_time_duration" min="0" value="{{ $tourPackage->arrival_time_duration }}" placeholder="Enter arrival time duration in minutes">
												</div>
											</div>
											<div class="mt-2 text-end"><button class="btn btn-md btn-primary btn-primary-small">Save</button></div>
										</form>

										<div class="flex flex-wrap -mx-3 pb-3">
											<div class="w-full md:w-1/2 max-w-full px-3 panel">
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">General</h5>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Status</label>
													<input class="form-check-input setting" type="checkbox" id="status" name="status" {{ $tourPackage->status == 'Active' ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Enabled In Frontend</label>
													<input class="form-check-input setting" type="checkbox" id="frontend_enabled" name="frontend_enabled" {{ $tourPackage->frontend_enabled == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Show Seat Availability In Frontend</label>
													<input class="form-check-input setting" type="checkbox" id="show_seat_availability" name="show_seat_availability" {{ $tourPackage->show_seat_availability == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Show In Agenda</label>
													<input class="form-check-input setting" type="checkbox" id="show_in_agenda" name="show_in_agenda" {{ $tourPackage->show_in_agenda == 1 ? 'checked' : '' }}>
												</div>
												@if (hasSetting('POS_VIEW'))
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Show In POS</label>
													<input class="form-check-input setting" type="checkbox" id="show_in_pos" name="show_in_pos" {{ $tourPackage->show_in_pos == 1 ? 'checked' : '' }}>
												</div>
												@endif
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Sign Waiver</label>
													<input class="form-check-input setting" type="checkbox" id="package_has_waiver" name="package_has_waiver" {{ $tourPackage->package_has_waiver == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Attach Waiver PDF</label>
													<input class="form-check-input setting" type="checkbox" id="attach_waiver_pdf" name="attach_waiver_pdf" {{ $tourPackage->attach_waiver_pdf == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Include In Operation Board</label>
													<input class="form-check-input setting" type="checkbox" id="include_in_operation_board" name="include_in_operation_board" {{ $tourPackage->include_in_operation_board == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Include In Add-ons</label>
													<input class="form-check-input setting" type="checkbox" id="include_in_addons" name="include_in_addons" {{ $tourPackage->include_in_addons == 1 ? 'checked' : '' }}>
												</div>
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">Combo</h5>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Combo Package</label>
													<input class="form-check-input setting" type="checkbox" id="is_combo_package" name="is_combo_package" {{ $tourPackage->is_combo_package == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Bundle Package</label>
													<input class="form-check-input setting" type="checkbox" id="bundle_package" name="bundle_package" {{ $tourPackage->bundle_package == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Group Rate</label>
													<input class="form-check-input setting" type="checkbox" id="is_group_rate_enabled" name="is_group_rate_enabled" {{ $tourPackage->is_group_rate_enabled == 1 ? 'checked' : '' }}>
												</div>
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">Package</h5>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Package Has Slots</label>
													<input class="form-check-input setting" type="checkbox" id="package_has_slots" name="package_has_slots" {{ $tourPackage->package_has_slots == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Package Has Permit</label>
													<input class="form-check-input setting" type="checkbox" id="package_has_permit" name="package_has_permit" {{ $tourPackage->package_has_permit == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Package Has Tax</label>
													<input class="form-check-input setting" type="checkbox" id="tax_applicable" name="tax_applicable" {{ $tourPackage->tax_applicable == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Booking Has Contacts</label>
													<input class="form-check-input setting" type="checkbox" id="has_contacts" name="has_contacts" {{ $tourPackage->has_contacts == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Partner Package</label>
													<input class="form-check-input setting" type="checkbox" id="is_partner_package" name="is_partner_package" {{ $tourPackage->is_partner_package == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Disable Slot Overlap Blocking</label>
													<input class="form-check-input setting" type="checkbox" id="disable_slot_overlap_blocking" name="disable_slot_overlap_blocking" {{ $tourPackage->disable_slot_overlap_blocking == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Close Slot on Booking</label>
													<input class="form-check-input setting" type="checkbox" id="slot_close_on_booking" name="slot_close_on_booking" {{ $tourPackage->slot_close_on_booking == 1 ? 'checked' : '' }}>
												</div>
											</div>
											<div class="w-full md:w-1/2 max-w-full px-3 panel">
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">Category</h5>
												@foreach($categories as $category)
													<div class="form-switch form-switch-wrap document-panel">
														<label class="form-custom-label inline-block">{{ $category->name }}</label>
														<input class="form-check-input setting-categorized"
															type="radio"
															name="category_id"
															value="{{ $category->id }}"
															{{ $tourPackage->category_id == $category->id ? 'checked' : '' }}>
													</div>
												@endforeach
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">Type</h5>
												@foreach(config('constants.categories') as $typeKey => $typeValue)
													<div class="form-switch form-switch-wrap document-panel">
														<label class="form-custom-label inline-block">{{ $typeValue }}</label>
														<input class="form-check-input setting-categorized"
															type="radio"
															value="{{ $typeKey }}"
															name="type"
															{{ $tourPackage->type == $typeKey ? 'checked' : '' }}>
													</div>
												@endforeach
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">Check-In</h5>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Show Check-ins</label>
													<input class="form-check-input setting"
														type="checkbox"
														id="checkin"
														name="checkin"
														{{ $tourPackage->checkin == 1 ? 'checked' : '' }}>
												</div>
												<h5 class="categorized-title font-medium text-[#020817] text-sm mt-5 mb-2">Type of Check-In</h5>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Tickets</label>
													<input class="form-check-input setting-categorized"
														value="1"
														type="checkbox"
														id="checkin_type"
														name="checkin_type"
														{{ $tourPackage->checkin_type == 1 ? 'checked' : '' }}>
												</div>
												<div class="form-switch form-switch-wrap document-panel">
													<label class="form-custom-label inline-block">Check-ins</label>
													<input class="form-check-input setting-categorized"
														value="0"
														type="checkbox"
														id="checkin_type"
														name="checkin_type"
														{{ $tourPackage->checkin_type == 0 ? 'checked' : '' }}>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('css-custom')
<style>
	tr.pckg-rate-grp tr:last-child td {
		padding-bottom: 10px;
	}

	.close {
		float: right;
		font-size: 1.5rem;
		font-weight: 700;
		line-height: 1;
		color: #000;
		text-shadow: 0 1px 0 #fff;
		opacity: .5;
	}

	iframe {
		width: 100%;
	}

	.modal-header .btn-close {
		padding: 0.5rem;
		margin: -0.5rem 0.5rem -0.5rem auto;
	}

	button.close {
		-webkit-appearance: none;
		padding: 0;
		cursor: pointer;
		background: 0 0;
		border: 0;
	}

</style>
@endsection

@section('css-libraries')
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/css/bootstrap-colorpicker.min.css" rel="stylesheet">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/quill-1.3.6/quill.bubble.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/quill-1.3.6/quill.snow.css') }}">
@if(App::environment('local'))
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/tagify-4.8.1/tagify.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/daterangepicker-3.1/daterangepicker-latest.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}">
<link rel="stylesheet" type="text/css" href="{{ asset('plugins/bootstrap-notify/animate.min.css') }}">
@else
<link rel="stylesheet" type="text/css" href="https://unpkg.com/@yaireo/tagify@4.8.1/dist/tagify.css">
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.2.3/animate.min.css">
@endif
<link rel="stylesheet" type="text/css" href="{{ asset('css/user-common/tour-package-add-edit.css') }}">
@endsection

@section('js-custom')
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-colorpicker/2.5.3/js/bootstrap-colorpicker.min.js"></script>
<script>
	$('.colorpicker').colorpicker();
</script>
<script type="text/javascript">
	var action = 'Update';
	var packageRates = @json($packageRates);
	var isGroupRateEnabled = "{{ $tourPackage->is_group_rate_enabled }}";
	var updatePhotoStatusUrl = "{{ route($routePrefix . '.package.update-photo-status', [ 'id' => 0 ]) }}";
	var deletePckgRateGrpUrl = "{{ route($routePrefix . '.package.delete-rate', [ 'id' => 0 ]) }}";
	var updateSettingUrl = "{{ route($routePrefix . '.update-setting', [ 'id' => $tourPackage->id ]) }}";
	var deleteWaiverPdfUrl = "{{ route($routePrefix . '.package.delete-waiver-pdf', [ 'id' => $tourPackage->id ]) }}";
	var getPackageListUrl = "{{ route($routePrefix . '.get-packages', ':id') }}";
	var isCurrentTourPackage = "{{ $tourPackage->id }}";
	var assignAffiliateUrl = "{{ route($routePrefix . '.package.assign-affiliate', [ 'id' => $tourPackage->id ]) }}";

	var scheduling_year = $('#scheduling_year').val();
	$('.schedulingYear').val(scheduling_year);
	$(".travel_duration").inputmask({
		"mask": "99:99"
	});

	var quill = new Quill('#quillArea', {
		modules: {
			toolbar: [
				[{
					header: [1, 2, false]
				}],
				['bold', 'italic', 'underline'],
				['image', 'code-block']
			]
		},
		placeholder: 'Enter content for Waiver...',
		theme: 'snow' // or 'bubble'
	});

	quill.on('text-change', function() {
		const content = quill.root.innerHTML.trim();
		$('#waiver_content').html(content);
	});

	var quillSchoolWaiver = new Quill('#quillSchoolWaiverArea', {
		modules: {
			toolbar: [
				[{ header: [1, 2, false] }],
				['bold', 'italic', 'underline'],
				['image', 'code-block']
			]
		},
		placeholder: 'Enter content for School Group Waiver...',
		theme: 'snow'
	});

	quillSchoolWaiver.on('text-change', function() {
		const content = quillSchoolWaiver.root.innerHTML.trim();
		$('#school_waiver_content').html(content);
	});

	const CALL_TO_BOOK = "{{ hasSetting('CALL_TO_BOOK') }}";
	if (CALL_TO_BOOK) {
		var quill2 = new Quill('#quillArea2', {
			modules: {
				toolbar: [
					[{
						header: [1, 2, false]
					}],
					['bold', 'italic', 'underline'],
					['image', 'code-block']
				]
			},
			placeholder: 'Enter call to book description',
			theme: 'snow' // or 'bubble'
		});

		quill2.on('text-change', function() {
			const content = quill2.root.innerHTML.trim();
			$('#ctb_description').html(content);
		});
	}

	function iFrameCopy(selector) {
		// Get the text field
		var copyText = document.getElementById(selector);

		// Select the text field
		copyText.select();
		copyText.setSelectionRange(0, 99999); // For mobile devices

		// Copy the text inside the text field
		navigator.clipboard.writeText(copyText.value);

		if (selector == 'iframe_combo_url') {
			var customToolTipSelector = "custom-tooltip-combo";
		} else {
			var customToolTipSelector = "custom-tooltip";
		}
		// Display the message to copied text
		document.getElementById(customToolTipSelector).style.display = "inline";
		document.execCommand("copy");
		setTimeout(function() {
			document.getElementById(customToolTipSelector).style.display = "none";
		}, 1000);
	}

	function iFrameUpdate() {
		var iframe_url = $("#iframe_url").val();
		$('#iframe').val(iframe_url);
	}

	$(document).on('shown.bs.modal', '#editCategory', function(event) {
		var button = $(event.relatedTarget)
		var caption = button.data('captions');
		var image = button.data('image');
		var id = button.data('id');

		// Set the form value for each edit category
		var modal = $(this);
		modal.find('.modal-body #cap').val(caption);
		modal.find('.modal-body #cat').val(image);
		$('#tour_package_image_id').val(id);
	});

	$(document).on('shown.bs.modal', '#editAssignAffiliate', function(event) {
		var button = $(event.relatedTarget)
		var affilite = button.data('affilite');
		var status = button.data('status');
		var id = button.data('id');

		var modal = $(this);
		modal.find('.modal-header .affilitename').html(affilite);
		modal.find('.modal-body #rel-id').val(id);
		modal.find('.modal-body .edit_show_all_package_bookings').val(status);
	});

	$(document).on('shown.bs.modal', '#editAssignAffiliate', function(event) {
		var button = $(event.relatedTarget)
		var affilite = button.data('affilite');
		var status = button.data('status');
		var id = button.data('id');

		var modal = $(this);
		modal.find('.modal-header .affilitename').html(affilite);
		modal.find('.modal-body #rel-id').val(id);
		modal.find('.modal-body .edit_show_all_package_bookings').val(status);
	});
</script>

<script type="text/javascript" src="{{ asset('js/user-common/tour-package-add-edit.js') }}"></script>

<x-delete-record-js-file />
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/tagify-4.8.1/tagify.min.js') }}"></script>
<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker-3.1/moment.min.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker-3.1/daterangepicker.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-notify/bootstrap-notify.min.js') }}"></script>
@else
<script type="text/javascript" src="https://unpkg.com/@yaireo/tagify@4.8.1/dist/tagify.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mouse0270-bootstrap-notify/3.1.5/bootstrap-notify.min.js"></script>
@endif
<script type="text/javascript" src="{{ asset('plugins/quill-1.3.6/quill.js') }}"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery.inputmask/5.0.6/jquery.inputmask.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>


{{--<script type="text/javascript" src="{{ asset('js/user-common/tour-package-custom-form-builder.js') }}"></script>--}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script type="text/javascript" src="{{ asset('js/user-common/addons/form-builder.js') }}?v={{ filemtime(public_path('js/user-common/addons/form-builder.js')) }}"></script>
<script type="text/javascript" src="{{ asset('js/user-common/addons/form-preview.js') }}"></script>
<script type="text/javascript">
	// on custom form tab click init the custom form builder
	// let customFormBuilder = false;
	let mainAddonFormBuilder = false;
	$(document).ready((_evt) => {
		$("#nav-custom-form-fields").click((_evt) => {
			if (mainAddonFormBuilder) {
				return true;
			}

			//add the function to the view
			mainAddonFormBuilder = MainAddonForm.initMainAddonForm({
				tour_package_id: "{{ $tourPackage->id }}",
				save_url: "{{ route($routePrefix . '.package.save-addon-form') }}",
				delete_url: "{{ route($routePrefix . '.package.delete-addon-form') }}",
				get_url: `{{ route($routePrefix . '.package.get-addon-form') }}`,
				categories_url: "{{ route($routePrefix . '.package.get-addon-categories') }}",
				packages_url: "{{ route($routePrefix . '.package.get-addon-packages') }}",
			});

			return true;
		});
		/*$("#nav-custom-form-fields").click((_evt) => {

			//if form is loaded
			if (customFormBuilder) {
				return true;
			}

			// if we are creating a custom form then fetch the need jquery ui methods
			const jquerySortUi = document.createElement("script");
			jquerySortUi.type = "text/javascript";
			jquerySortUi.src = "{{asset('plugins/jquery-ui-1.13.2/jquery-ui.1.13.2.js')}}";
			$("head").append(jquerySortUi);

			//add the function to the view
			let saveFormUrl = "{{ route($routePrefix . '.package.upsert-package-form') }}";
			let getFormUrl = "{{ route($routePrefix . '.package.get-package-form') }}";
			let deleteFormUrl = "{{ route($routePrefix . '.package.delete-package-form') }}";
			customFormBuilder = CustomFormBuilder.initCustomFormBuilder({
				get_url: `${getFormUrl}`,
				save_url: `${saveFormUrl}`,
				del_url: `${deleteFormUrl}`,
				tour_package_id: "{{ $tourPackage->id }}",
			});

			return true;

		});*/ //end on custom form on click

		/**
		 * @description Update the stripe bank debit settings
		 * @returns void
		 */
		$(".stripe-settings").click((event) => {
			var routePrefixUrl = "{{ $routePrefix}}";
			routePrefixUrl = routePrefixUrl.replace('.', '/');
			let ele = $(event.target);
			let cmd = "debit_negative_balances_off";
			if (ele.prop("checked") === true) {
				cmd = "debit_negative_balances_on";
				ele.data('cmd', cmd);
			}

			let payload = {
				command: cmd,
				connected_account_id: ele.data('acc')
			}
			$.ajax({
				type: 'PUT',
				url: "/" + routePrefixUrl + "/setting/update-stripe-setting",
				data: payload,
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				success: (resp) => {
					if (resp.success) {
						swal(`${resp.message}`, '', 'success')
					}
				},
				error: (err) => {
					swal(`Error: ${JSON.stringify(err.responseJSON.errors.common)}`, '', 'error');
				}
			});
		});
		// end debit_negative_balances toggle



		const getStripeSettings = (id) => {
			var routePrefixUrl = "{{ $routePrefix}}";
			routePrefixUrl = routePrefixUrl.replace('.', '/');
			console.log("/" + routePrefixUrl + `/setting/get-stripe-settings?id=${id}`);
			if (!id || id == "") {
				return false;
			}

			$.ajax({
				type: 'GET',
				url: "/" + routePrefixUrl + `/setting/get-stripe-settings?id=${id}`,
				headers: {
					'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
				},
				success: (resp) => {
					let _debit = false;
					if (resp.success) {
						const _s = resp.settings;
						try {
							if (_s.hasOwnProperty("payouts")) {
								const _p = _s.payouts;
								if (_p.hasOwnProperty("debit_negative_balances")) {
									_debit = _p.debit_negative_balances;
								}
							}
						} catch (err) {
							console.log(err)
						}
					}


					$("#debit_negative_balances").attr("checked", _debit);
				},
				error: (err) => {
					console.log(err);
				}
			});


		} // end get stripe settings

		getStripeSettings("{{$tourPackage->stripe_destination_id}}");

	}); // end on doc ready
</script>
@endsection