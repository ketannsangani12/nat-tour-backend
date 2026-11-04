@extends('layouts.default-2')
@php
	$routePrefix = routePrefix();
@endphp

@section('main-content')
	<div class="page-content py-6 bg-[#F9FAFB]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full max-w-full px-3">
					<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
						<div class="content-title">
							<h2 class="section-title">Edit Tour Operator</h2>
						</div>

						<div class="sub-nav flex items-center justify-end mb-8">
							@if (isPermissionGranted())
								<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.tour-operator.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							@endif
							<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.tour-operator.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
						</div>

						<div class="main-content">
							@if($errors->any())
								@include('layouts._errors')
							@endif

							@if(session('message'))
								@include('layouts._message')
							@endif

							<div class="card mb-4">
								<div class="flex flex-wrap -mx-3 items-center">
									<div class="w-1/2 md:w-1/3 lg:w-1/4 max-w-full px-3 col-logo text-center">
										<div class="p-5 border border-[#E6E6E6] rounded-xl shadow-sm bg-[#F3F4F6]">
											@if (!Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/logo/' . $tourOperator->logo))
												<div><img
														src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/logo/' . $tourOperator->logo) }}"
														class="img-fluid rounded-md mx-auto max-h-60 max-w-full" alt="{{ $tourOperator->name }} Logo" onerror="noPhoto(this)"></div>
											@else
												<div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg rounded-md mx-auto max-h-60 max-w-full"></div>
											@endif

											<form method="post" action="{{ route($routePrefix . '.update-logo', ['id' => $tourOperator->id]) }}"
												enctype="multipart/form-data">
												@csrf

												<input type="file" name="logo" id="logo" accept="image/*"
													data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" class="hidden"
													onchange="if(chkFileExt(this) && this.value != '') { showLoading(); $(this).parents('form').trigger('submit'); }">
												<button type="button" class="btn btn-info btn-sm btn-change-logo mt-3 btn-primary-small"
													onclick="$('#logo').trigger('click')">Change Logo</button>
											</form>
										</div>
									</div>
									<div class="w-1/2 md:w-1/3 lg:w-1/4 max-w-full px-3 col-logo text-center">
										<div class="p-5 border border-[#E6E6E6] rounded-xl shadow-sm bg-[#F3F4F6]">
											@if (!Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/logo/' . $tourOperator->logo_print))
												<div><img
														src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/logo/' . $tourOperator->logo_print) }}"
														class="img-fluid rounded-md mx-auto max-h-60 max-w-full" alt="{{ $tourOperator->name }} Logo" onerror="noPhoto(this)"></div>
											@else
												<div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg rounded-md mx-auto max-h-60 max-w-full"></div>
											@endif

											<form method="post" action="{{ route($routePrefix . '.update-logo', ['id' => $tourOperator->id]) }}"
												enctype="multipart/form-data">
												@csrf

												<input type="file" name="logo_print" id="logo_print" accept="image/*"
													data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" class="hidden"
													onchange="if(chkFileExt(this) && this.value != '') { showLoading(); $(this).parents('form').trigger('submit'); }">
												<button type="button" class="btn btn-info btn-sm btn-change-logo mt-3 btn-primary-small"
													onclick="$('#logo_print').trigger('click')">Change Logo(Print)</button>
											</form>
										</div>
									</div>
									<div class="w-1/2 md:w-1/3 lg:w-1/4 max-w-full px-3 col-logo text-center">
										<div class="p-5 border border-[#E6E6E6] rounded-xl shadow-sm bg-[#F3F4F6]">
											@if (!Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/logo/' . $tourOperator->logo_navigation))
												<div><img
														src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/logo/' . $tourOperator->logo_navigation) }}"
														class="img-fluid rounded-md mx-auto max-h-60 max-w-full" alt="{{ $tourOperator->name }} Logo" onerror="noPhoto(this)"></div>
											@else
												<div><img src="{{ asset('images/no-photo.png') }}" class="img-pckg rounded-md mx-auto max-h-60 max-w-full"></div>
											@endif

											<form method="post" action="{{ route($routePrefix . '.update-logo', ['id' => $tourOperator->id]) }}"
												enctype="multipart/form-data">
												@csrf

												<input type="file" name="logo_navigation" id="logo_navigation" accept="image/*"
													data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" class="hidden"
													onchange="if(chkFileExt(this) && this.value != '') { showLoading(); $(this).parents('form').trigger('submit'); }">
												<button type="button" class="btn btn-info btn-sm btn-change-logo mt-3 btn-primary-small"
													onclick="$('#logo_navigation').trigger('click')">Change Logo(Menu Navigation)</button>
											</form>
										</div>
									</div>
									<div class="w-1/2 md:w-1/3 lg:w-1/4 max-w-full px-3 py-5">
										<form method="post"
											action="{{ route($routePrefix . '.update-status', ['id' => $tourOperator->id]) }}"
											onsubmit="return submitForm(this)" data-on-success-msg="Tour operator's status updated.">
											@csrf

											<div class="mb-3">
												<label for="status" class="form-label form-custom-label">Status</label>
												<select class="form-select form-custom-input" name="status" id="status">
													<option {{ $tourOperator->status == 'Active' ? 'selected' : '' }}>Active</option>
													<option {{ $tourOperator->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
												</select>
											</div>

											<div><button class="btn btn-main btn-main-sm mt-3 btn-primary-small">Update Status</button></div>
										</form>
									</div> {{-- /.col --}}
								</div> {{-- /.flex flex-wrap -mx-3 --}}
							</div>

							<div class="card">
								<nav class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-y-auto" id="nav-tab" role="tablist">
									<button type="button" class="nav-item nav-link tabs-custom-link active" id="nav-general" data-bs-toggle="tab"
										data-bs-target="#tab-general" role="tab" aria-controls="tab-general"
										aria-selected="true">General</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-seo" data-bs-toggle="tab"
										data-bs-target="#tab-seo" role="tab" aria-controls="tab-seo" aria-selected="false">SEO</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-social-media" data-bs-toggle="tab"
										data-bs-target="#tab-social-media" role="tab" aria-controls="tab-social-media"
										aria-selected="false">Social Media</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-report-setup" data-bs-toggle="tab"
										data-bs-target="#tab-report-setup" role="tab" aria-controls="tab-report-setup"
										aria-selected="false">Report Setup</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-report-setup" data-bs-toggle="tab"
										data-bs-target="#tab-affiliate-setup" role="tab" aria-controls="tab-affiliate-setup"
										aria-selected="false">Affiliate Setup</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-checkins-setup" data-bs-toggle="tab"
										data-bs-target="#tab-checkins-setup" role="tab" aria-controls="tab-checkins-setup"
										aria-selected="false">Check-In Setup</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-qr-code-setup" data-bs-toggle="tab"
										data-bs-target="#tab-qr-code-setup" role="tab" aria-controls="tab-qr-code-setup"
										aria-selected="false">QR-Code</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-partner-packages" data-bs-toggle="tab"
										data-bs-target="#tab-partner-packages" role="tab" aria-controls="tab-partner-packages"
										aria-selected="false">Partner Packages</button>
									<button type="button" class="nav-item nav-link tabs-custom-link" id="nav-checkout-terms" data-bs-toggle="tab"
										data-bs-target="#tab-checkout-terms" role="tab" aria-controls="tab-checkout-terms"
										aria-selected="false">Checkout Terms</button>
								</nav>

								<div class="tab-content">
									<div class="tab-pane active" id="tab-general" role="tabpanel" aria-labelledby="tab-general">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form method="post"
												action="{{ route($routePrefix . '.update-general', ['id' => $tourOperator->id]) }}"
												onsubmit="if(!validateForm())return false;return submitForm(this)"
												data-on-success-msg="Tour operator's general details updated.">
												@csrf

												<div class="flex flex-wrap -mx-3">
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
														<input class="form-control form-custom-input" name="name" id="name" maxlength="100"
															value="{{ old('name') ?? $tourOperator->name }}" placeholder="Enter admin name"
															onblur="validateCharacters('#name')" required>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="email" class="form-label form-custom-label">Email Address</label>
														<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50"
															value="{{ old('email') ?? $tourOperator->email }}"
															placeholder="Enter email address">
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="phone_number" class="form-label form-custom-label">Phone Number</label>
														<input type="tel" class="form-control form-custom-input" name="phone_number" id="phone_number"
															maxlength="30" value="{{ old('phone_number') ?? $tourOperator->phone_number }}"
															placeholder="Enter phone number" data-allowed-characters="0-9 space - ( ) +"
															onblur="validateCharacters('#phone_number')">
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="website" class="form-label form-custom-label">Website</label>
														<input type="url" class="form-control form-custom-input" name="website" id="website" maxlength="200"
															value="{{ old('website') ?? $tourOperator->website }}"
															placeholder="Enter website URL">
													</div>
												</div>

												<div class="mb-3">
													<label for="description" class="form-label form-custom-label">Description</label>
													<textarea class="form-control form-custom-input max-h-16 min-h-16" name="description" id="description"
														placeholder="Enter description">{{ old('description') ?? $tourOperator->description }}</textarea>
												</div>

												<div class="mb-3">
													<label for="cancellation_policy" class="form-label form-custom-label">Booking Cancellation Policy</label>
													<textarea class="form-control form-custom-input max-h-16 min-h-16" name="cancellation_policy" id="cancellation_policy"
														placeholder="Enter booking cancellation policy">{{ old('cancellation_policy') ?? $tourOperator->cancellation_policy }}</textarea>
												</div>

												<div class="flex flex-wrap -mx-3">
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="country" class="form-label form-custom-label">Country <req>*</req></label>
														<select class="form-select form-custom-input" name="country" id="country"
															onchange="getStates(this, '#state')" required>
															<option value="">Select Country</option>
															@foreach($countries as $country)
																<option value="{{ $country->id }}" {{ old('country') ? (old('country') == $country->id ? 'selected' : '') : ($tourOperator->country_id == $country->id ? 'selected' : '') }}>
																	{{ $country->name }}
																</option>
															@endforeach
														</select>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="name" class="form-label form-custom-label">State <req>*</req></label>
														<select class="form-select form-custom-input" name="state" id="state"
															onchange="getCities(this, '#city')" required>
															<option value="">Select State</option>
															@foreach($states as $state)
																<option value="{{ $state->id }}" {{ old('state') ? (old('state') == $state->id ? 'selected' : '') : ($tourOperator->state_id == $state->id ? 'selected' : '') }}>{{ $state->name }}</option>
															@endforeach
														</select>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="city" class="form-label form-custom-label">City <req>*</req></label>
														<select class="form-select form-custom-input" name="city" id="city" required>
															<option value="">Select City</option>
															@foreach($cities as $city)
																<option value="{{ $city->id }}" {{ old('city') ? (old('city') == $city->id ? 'selected' : '') : ($tourOperator->city_id == $city->id ? 'selected' : '') }}>
																	{{ $city->name }}
																</option>
															@endforeach
														</select>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="postal_code" class="form-label form-custom-label">Postal Code</label>
														<input class="form-control form-custom-input" name="postal_code" id="postal_code" maxlength="20"
															value="{{ old('postal_code') ?? $tourOperator->postal_code }}"
															placeholder="Enter postal code" data-allowed-characters="A-Z a-z 0-9 -"
															onblur="validateCharacters('#postal_code')">
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="timezone" class="form-label form-custom-label">Timezone <req>*</req></label>
														<select name="timezone" id="timezone" class="form-select form-custom-input" required>
															<option value="">Select timezone</option>
															@foreach(timezone_identifiers_list() as $timezone)
																<option value="{{ $timezone }}" {{ $tourOperator->timezone == $timezone ? 'selected' : '' }}>{{ $timezone }}</option>
															@endforeach
														</select>
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="address" class="form-label form-custom-label">Address</label>
														<input class="form-control form-custom-input" name="address" id="address"
															value="{{ old('address') ?? $tourOperator->address }}"
															placeholder="Enter address">
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="statement_descriptor" class="form-label form-custom-label">Statement Descriptor<req>*
															</req></label>
														<div class="input-group">
															<input class="form-control form-custom-input" name="statement_descriptor"
																id="statement_descriptor" maxlength="21"
																value="{{ old('statement_descriptor') ?? $tourOperator->statement_descriptor }}"
																placeholder="Enter Statement Descriptior"
																data-allowed-characters="A-Z a-z 0-9 -"
																onblur="validateCharacters('#statement_descriptor')" required>
														</div>
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="card_reader_prefix" class="form-label form-custom-label">Card Reader Prefix<req>*</req>
														</label>
														<div class="input-group">
															<input class="form-control form-custom-input" name="card_reader_prefix" id="card_reader_prefix"
																maxlength="21"
																value="{{ old('card_reader_prefix') ?? $tourOperator->card_reader_prefix }}"
																placeholder="Enter Card Reader Prefix"
																data-allowed-characters="A-Z a-z 0-9 -" required>
														</div>
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="stripe_destination_id" class="form-label form-custom-label">Stripe Connected Account ID
															<req>*</req>
														</label>
														<div class="input-group flex items-center">
															<input class="form-control form-custom-input toggle-encrypted-value" type="password"
																name="stripe_destination_id" id="stripe_destination_id" min="1" step="1"
																value="{{ old('stripe_destination_id') ?? $tourOperator->stripe_destination_id }}"
																placeholder="Enter stripe connected account ID" required>
															<button class="btn btn-outline-secondary ml-1 btn-info-small" type="button"
																id="toggleEncryptedValue">
																<i class="fas fa-eye" id="toggleIcon"></i>
															</button>
															<button class="btn btn-outline-secondary ml-1 btn-primary-small !rounded-full" type="button" id="toggleInfo"
																title="Get info of stripe connected account ID"
																onclick="getTourOperatorConnectedAccountIdInfo(this)">
																<i class="fas fa-info"></i>
															</button>
														</div>
														<p class="hidden" id="connected_account_name"
															style="margin-left: 10px;margin-top: 10px;color: #198754;font-weight: bold;">
														</p>
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="stripe_terminal_location_id" class="form-label form-custom-label">Stripe Terminal Location Id</label>
														<input class="form-control form-custom-input" type="text" name="stripe_terminal_location_id"
															id="stripe_terminal_location_id" 
															value="{{ old('stripe_terminal_location_id') ?? $tourOperator->stripe_terminal_location_id }}"
															placeholder="Enter tripe Terminal Location Id">
													</div>
													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="cancellation_within_days" class="form-label form-custom-label">Cancellation Days
															Within</label>
														<input class="form-control form-custom-input" type="number" name="cancellation_within_days"
															id="cancellation_within_days" min="1" step="1"
															value="{{ old('cancellation_within_days') ?? $tourOperator->cancellation_within_days }}"
															placeholder="Enter Cancellation Days Within">
													</div>

													<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
														<label for="tenant_id" class="form-label form-custom-label">Tenant ID </label>
														<input class="form-control form-custom-input" type="text" name="tenant_id" id="tenant_id"
															value="{{ old('tenant_id') ?? $tourOperator->tenant_id }}"
															placeholder="Enter Tenant ID" required>
													</div>

												</div>

												<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">Update General Details</button>
												</div>
											</form>
										</div> {{-- /.card-body - general --}}
									</div> {{-- /#tab-general.tab-pane --}}

									<div class="tab-pane" id="tab-seo" role="tabpanel" aria-labelledby="tab-seo">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form class="form" method="post"
												action="{{ route($routePrefix . '.update-seo', ['id' => $tourOperator->id]) }}"
												onsubmit="return submitForm(this)"
												data-on-success-msg="Tour package's SEO details updated.">
												@csrf

												<div class="mb-3">
													<label for="seo_title" class="form-label form-custom-label">Title</label>
													<input class="form-control form-custom-input" name="seo_title" id="seo_title"
														value="{{ old('seo_title') ?? $tourOperator->seo_title }}"
														placeholder="Enter title for SEO">
												</div>

												<div class="mb-3">
													<label for="seo_description" class="form-label form-custom-label">Description</label>
													<textarea class="form-control form-custom-input max-h-16 min-h-16" name="seo_description" id="seo_description"
														placeholder="Enter description for SEO">{{ old('seo_description') ?? $tourOperator->seo_description }}</textarea>
												</div>

												<div class="mb-3">
													<label for="seo_keywords" class="form-label form-custom-label">Keywords</label>
													<textarea class="form-control form-custom-input max-h-16 min-h-16" name="seo_keywords" id="seo_keywords"
														placeholder="Enter keywords for SEO">{{ old('seo_keywords') ?? $tourOperator->seo_keywords }}</textarea>
												</div>

												<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">Update SEO Details</button></div>
											</form>
										</div> {{-- /.card-body - seo --}}
									</div> {{-- /#tab-seo.tab-pane --}}

									<div class="tab-pane" id="tab-social-media" role="tabpanel" aria-labelledby="tab-social-media">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form class="form" method="post"
												action="{{ route($routePrefix . '.update-social-media', ['id' => $tourOperator->id]) }}"
												onsubmit="return submitForm(this)"
												data-on-success-msg="Tour operator's social media details updated.">
												@csrf

												@include('layouts._list-social-media')

												<table class="text-left" width="100%">
													<thead>
														<tr class="flex flex-wrap -mx-3">
															<th class="w-full md:w-1/3 lg:w-1/6 max-w-full px-3 pb-2 form-custom-label"><abbr
																	title="Social Media">SM</abbr> Site Name</th>
															<th class="w-11/12 md:w-7/12 lg:w-9/12 max-w-full px-3 pb-2 form-custom-label">Page's Full URL</th>
														</tr>
													</thead>

													<tbody id="tbl-social-media"></tbody>

													<tfoot id="tmpl-social-media" class="hidden">
														<tr class="flex flex-wrap -mx-1">
															<td class="w-full md:w-1/3 lg:w-1/6 max-w-full px-1 pb-2">
																<input data-name="sm_name[]" class="form-control form-custom-input" list="list-social-medias"
																	placeholder="e.g.: Facebook">
															</td>
															<td class="w-10/12 md:w-6/12 lg:w-8/12 max-w-full px-1 pb-2">
																<input type="url" data-name="sm_url[]" class="form-control form-custom-input">
															</td>
															<td class="w-2/12 max-w-full px-1 pb-2">
																<button type="button" class="btn btn-danger btn-sm btn-danger-small"
																	onclick="removeSocialMedia(this)" title="Remove Social Media"><i class="fa fa-times" aria-hidden="true"></i></button>
															</td>
														</tr>
													</tfoot>
												</table>

												<div class="mt-2 mb-3">
													<button type="button" id="btn-add-social-media" class="btn btn-info btn-primary-small"
														onclick="addSocialMedia()">Add More SM</button>
												</div>

												<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">Update Social Media Details</button>
												</div>
											</form>
										</div> {{-- /.card-body - general --}}
									</div> {{-- /#tab-social-media.tab-pane --}}

									<div class="tab-pane" id="tab-report-setup" role="tabpanel" aria-labelledby="tab-seo">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form method="post"
												action="{{ route($routePrefix . '.update-report-setup', ['id' => $tourOperator->id]) }}"
												onsubmit="return submitForm(this)"
												data-on-success-msg="Tour operator's Report Setup updated.">
												@csrf
												@php
													$to_reports = json_decode($tourOperator->reports, true);
												@endphp
												@if(count($reports) > 0)
													<div class="flex flex-wrap -mx-3">
														@foreach($reports as $report)
															<div class="w-full sm:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
																<label class="flex items-center mb-2 text-sm text-[#4A4F59]">
																	<input class="m-0 me-2 w-4 h-4 cursor-pointer" type="checkbox" name="reports[]" value="{{ $report }}" @if($to_reports != null) @if(in_array($report, $to_reports)) checked="" @endif @endif>
																	<span>{{ $report }}</span>
																</label>
															</div>
														@endforeach
													</div>
												@endif

												<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">Update Report Setup</button></div>
											</form>
										</div> {{-- /.card-body - seo --}}
									</div>

									<div class="tab-pane" id="tab-affiliate-setup" role="tabpanel" aria-labelledby="tab-seo">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form method="post"
												action="{{ route($routePrefix . '.update-affiliate-setup', ['id' => $tourOperator->id]) }}"
												onsubmit="return submitForm(this)"
												data-on-success-msg="Tour operator's Affiliate Setup updated.">
												@csrf
												<div class="mb-3">
													<label class="flex items-center mb-2 text-sm text-[#4A4F59]">
														<input class="m-0 me-2 w-4 h-4 cursor-pointer" type="radio" name="is_affiliate" value="1" {{ ($tourOperator->is_affiliate == 1) ? "checked" : "" }}>
														<span>Yes</span>
													</label>
												</div>
												<div class="mb-3">
													<label class="flex items-center mb-2 text-sm text-[#4A4F59]">
														<input class="m-0 me-2 w-4 h-4 cursor-pointer" type="radio" name="is_affiliate" value="0" {{ ($tourOperator->is_affiliate != '' && $tourOperator->is_affiliate == 0) ? "checked" : "" }}>
														<span>No</span>
													</label>
												</div>
												<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">Update Affiliate Setup</button>
												</div>
											</form>
										</div> {{-- /.card-body - seo --}}
									</div>

									<div class="tab-pane" id="tab-checkins-setup" role="tabpanel" aria-labelledby="tab-seo">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<form method="post"
												action="{{ route($routePrefix . '.update-checkin-setup', ['id' => $tourOperator->id]) }}"
												onsubmit="return submitForm(this)"
												data-on-success-msg="Tour operator's Checkin Setup updated.">
												@csrf
												@php
													$to_checkins = json_decode($tourOperator->checkins, true);
												@endphp
												@if(count($checkins) > 0)
												<div class="flex flex-wrap -mx-3">
													@foreach($checkins as $key => $checkin)
														<div class="w-full sm:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
															<label class="flex items-center mb-2 text-sm text-[#4A4F59]">
																<input class="m-0 me-2 w-4 h-4 cursor-pointer" type="checkbox" name="checkins[]" value="{{ $key }}" @if($to_checkins != null)
																@if(in_array($key, $to_checkins)) checked="" @endif @endif>
																<span>{{ $checkin }}</span>
															</label>
														</div>
													@endforeach
												</div>
												@endif

												<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">Update Check-in Setup</button></div>
											</form>
										</div> {{-- /.card-body - seo --}}
									</div>

									<div class="tab-pane" id="tab-qr-code-setup" role="tabpanel" aria-labelledby="tab-seo">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											@if($tourOperator->website != '')
												{{QrCode($tourOperator->website)}}
											@endif
										</div> {{-- /.card-body - seo --}}
									</div>
									<div class="tab-pane" id="tab-partner-packages" role="tabpanel" aria-labelledby="tab-partner-packages">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<div class="w-full overflow-y-auto">
												<table class="table table-bordered w-full border-collapse">
													<thead>
														<tr>
															<th class="th-custom-css">Package</th>
															<th class="th-custom-css">Stripe Connected Account ID</th>
															<td class="th-custom-css">Action</td>
													</thead>

													<tbody id="tbody-time-slots">
														@foreach($packages as $tp)
															<tr>
																<td class="td-custom-css">
																	{{ $tp->name }}
																</td>
																<td class="td-custom-css">
																	<div class="input-group flex items-center">
																		<input type="hidden" name="package_id" class="package_id"
																			value="{{ $tp->id }}">
																		<input type="password"
																			class="form-control form-custom-input toggle-encrypted-value-package stripe_destination_id_package"
																			name="stripe_destination_id_package"
																			value="{{ $tp->stripe_destination_id }}"
																			placeholder="Enter stripe connected account ID" required>
																		<button class="btn btn-outline-secondary ml-2 btn-info-small" type="button"
																			onclick="showConnectedAccountId(this)">
																			<i class="fas fa-eye toggleIconPackage"></i>
																		</button>

																	</div>
																	<p class="connected_account_name hidden"
																		style="margin-left: 10px;margin-top: 10px;color: #198754;font-weight: bold;">
																	</p>

																</td>

																<td class="td-custom-css">
																	<button type="button" class="btn btn-success btn-sm mr-2 btn-primary-small"
																		onclick="getConnectedAccountIdInfo(this)"
																		title="Get info of stripe connected account ID">Info</button>
																	<button type="button" class="btn btn-primary btn-sm btn-primary-small"
																		onclick="addUpdateConnectedAccount(this)"
																		title="Add / update stripe connected account ID">Save</button>
																</td>
															</tr>
														@endforeach
													</tbody>

												</table>
											</div>
										</div>
									</div>

									<div class="tab-pane" id="tab-checkout-terms" role="tabpanel" aria-labelledby="tab-checkout-terms">
										<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
											<div class="mb-4">
												<p class="text-sm text-gray-600 mb-2">Add custom terms/checkboxes that will appear on the frontend checkout page.</p>
											</div>

											<form method="post"
												action="{{ route($routePrefix . '.update-checkout-terms', ['id' => $tourOperator->id]) }}"
												onsubmit="if(!validateCheckoutTermsForm()) return false; return submitForm(this)"
												data-on-success-msg="Checkout terms updated successfully.">
												@csrf

												<div class="w-full overflow-x-auto">
													<table class="table table-bordered w-full border-collapse min-w-[800px]">
														<thead>
															<tr>
																<th class="th-custom-css" style="width: 20%;">Key <req>*</req></th>
																<th class="th-custom-css" style="width: 45%;">Label <req>*</req></th>
																<th class="th-custom-css" style="width: 12%;">Required</th>
																<th class="th-custom-css" style="width: 12%;">Status</th>
																<th class="th-custom-css" style="width: 11%;">Action</th>
															</tr>
														</thead>
														<tbody id="checkout-terms-container">
															@if(count($checkoutTerms) > 0)
																@foreach($checkoutTerms as $index => $term)
																	<tr class="checkout-term-row">
																		<td class="td-custom-css align-top">
																			<input type="text" class="form-control form-custom-input" name="checkout_terms[{{ $index }}][key]" 
																				value="{{ $term['key'] ?? '' }}" placeholder="e.g., permit_ack" required
																				pattern="[a-z0-9_]+" title="Only lowercase letters, numbers, and underscores"
																				onblur="validateCheckoutTermKeys()">
																		</td>
																		<td class="td-custom-css align-top">
																			<textarea class="form-control form-custom-input" name="checkout_terms[{{ $index }}][label]" 
																				rows="4" style="min-height: 80px;" placeholder="Text displayed to customer" required>{{ $term['label'] ?? '' }}</textarea>
																		</td>
																		<td class="td-custom-css align-top">
																			<select class="form-select form-custom-input" name="checkout_terms[{{ $index }}][required]">
																				<option value="1" {{ ($term['required'] ?? false) ? 'selected' : '' }}>Yes</option>
																				<option value="0" {{ !($term['required'] ?? false) ? 'selected' : '' }}>No</option>
																			</select>
																		</td>
																		<td class="td-custom-css align-top">
																			<select class="form-select form-custom-input" name="checkout_terms[{{ $index }}][status]">
																				<option value="active" {{ ($term['status'] ?? 'active') == 'active' ? 'selected' : '' }}>Active</option>
																				<option value="inactive" {{ ($term['status'] ?? 'active') == 'inactive' ? 'selected' : '' }}>Inactive</option>
																			</select>
																		</td>
																		<td class="td-custom-css align-top text-center">
																			<button type="button" class="btn btn-danger btn-sm btn-danger-small" onclick="removeCheckoutTerm(this)">
																				<i class="fa fa-times"></i>
																			</button>
																		</td>
																	</tr>
																@endforeach
															@endif
														</tbody>
													</table>
												</div>

												<div class="mt-4 mb-4">
													<button type="button" class="btn btn-info btn-primary-small" onclick="addCheckoutTerm()">
														<i class="fa fa-plus mr-2"></i> Add Term
													</button>
												</div>

												<div class="mt-3 flex justify-end">
													<button class="btn btn-main btn-primary-small">Update Checkout Terms</button>
												</div>
											</form>
										</div>
									</div>

								</div> {{-- /.tab-content --}}
							</div> {{-- /.card - general --}}
						</div> {{-- /.main-content --}}
					</div>
				</div>
			</div>
		</div>
	</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
	@if(App::environment('local'))
		<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
	@else
		<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	@endif
@endsection

@section('js-libraries')
	@if(App::environment('local'))
		<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
	@else
		<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	@endif
@endsection

@section('js-custom')
	<script>
		var action = 'update';
		var socialMedia = JSON.parse(@json($tourOperator->social_media_urls));
		var getStatesUrl = "{{ route($routePrefix . '.get-states', ['countryId' => 0]) }}";
		var getCitiesUrl = "{{ route($routePrefix . '.get-cities', ['stateId' => 0]) }}";
		var addUpdateConnectedAccountUrl = "{{ route($routePrefix . '.update-connected-accounts') }}";
		var getConnectedAccountInfoUrl = "{{ route($routePrefix . '.get-connected-account-info') }}";
		var checkoutTermIndex = {{ count($checkoutTerms) }};
		var deleteCheckoutTermUrl = "{{ route($routePrefix . '.delete-checkout-term') }}";
	</script>
	<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
	<script src="{{ asset('js/admin/tour-operator-add-edit.js') }}"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	<x-delete-record-js-file />

@endsection