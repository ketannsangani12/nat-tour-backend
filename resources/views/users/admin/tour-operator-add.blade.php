@extends('layouts.default-2')

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Add Tour Operator</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route('admin.tour-operator.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route('admin.tour-operator.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div>

					<div class="main-content">
					@if($errors->any())
						@include('layouts._errors')
					@endif

					@if(session('message'))
						@include('layouts._message')
					@endif

						<form method="post" action="{{ route('admin.tour-operator.store') }}" enctype="multipart/form-data" onsubmit="return validateForm()">
							@csrf

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
									<input class="form-control form-custom-input" name="name" id="name" maxlength="100" value="{{ old('name') }}" placeholder="Enter name"  onblur="validateCharacters('#name')" required>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="email" class="form-label form-custom-label">Email Address</label>
									<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50" value="{{ old('email') }}" placeholder="Enter email address">
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="phone_number" class="form-label form-custom-label">Phone Number</label>
									<input type="tel" class="form-control form-custom-input" name="phone_number" id="phone_number" maxlength="30" value="{{ old('phone_number') }}" placeholder="Enter phone number"  onblur="validateCharacters('#phone_number')">
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="website" class="form-label form-custom-label">Website</label>
									<input type="url" class="form-control form-custom-input" name="website" id="website" maxlength="200" value="{{ old('website') }}" placeholder="Enter website URL">
								</div>
							</div>

							<div class="mb-3">
								<label for="description" class="form-label form-custom-label">Description</label>
								<textarea class="form-control form-custom-input min-h-16 max-h-16" name="description" id="description" placeholder="Enter description">{{ old('description') }}</textarea>
							</div>

							<div class="mb-3">
								<label for="cancellation_policy" class="form-label form-custom-label">Booking Cancellation Policy</label>
								<textarea class="form-control form-custom-input min-h-16 max-h-16" name="cancellation_policy" id="cancellation_policy" placeholder="Enter booking cancellation policy">{{ old('cancellation_policy') }}</textarea>
							</div>

							<div class="mb-3">
								<label for="logo" class="form-label form-custom-label">Logo <small>(JPEG / PNG / WebP / GIF)</small></label>
								<input type="file" class="form-control form-custom-input" name="logo" id="logo" accept="image/*" data-allowed-ext="jpg,jpeg,jpe,png,webp,gif" onchange="chkFileExt(this)" placeholder="Select logo image">
							</div>

							<div class="flex flex-wrap -mx-3 mb-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="subdomain" class="form-label form-custom-label">Subdomain</label>
									<input type="text" class="form-control form-custom-input" name="subdomain" id="subdomain" maxlength="20" value="{{ old('subdomain') }}" pattern="[a-z]+" placeholder="Enter subdomain">
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="country" class="form-label form-custom-label">Country <req>*</req></label>
									<select class="form-select form-custom-input" name="country" id="country" onchange="getStates(this, '#state')" required>
										<option value="">Select Country</option>
										@foreach($countries as $country)
										<option value="{{ $country-> id }}" {{ old('country') == $country->id ? 'selected' : '' }}>{{ $country->name }}</option>
										@endforeach
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">State <req>*</req></label>
									<select class="form-select form-custom-input" name="state" id="state" onchange="getCities(this, '#city')" required>
										<option value="">Select a Country First</option>
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="city" class="form-label form-custom-label">City <req>*</req></label>
									<select class="form-select form-custom-input" name="city" id="city" required>
										<option value="">Select a State First</option>
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="postal_code" class="form-label form-custom-label">Postal Code</label>
									<input class="form-control form-custom-input" name="postal_code" id="postal_code" maxlength="20" value="{{ old('postal_code') }}" data-allowed-characters="A-Z a-z 0-9 -" onblur="validateCharacters('#postal_code')" placeholder="Enter postal code">
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="address" class="form-label form-custom-label">Address</label>
									<input class="form-control form-custom-input" name="address" id="address" value="{{ old('address') }}" placeholder="Enter address">
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="statement_descriptor" class="form-label form-custom-label">Stripe Statement Descriptor<req>*</req></label>
									<input class="form-control form-custom-input" name="statement_descriptor" id="statement_descriptor" maxlength="21" value="{{ old('statement_descriptor') }}" placeholder="Enter Statement Descriptior" data-allowed-characters="A-Z a-z 0-9 -" onblur="validateCharacters('#statement_descriptor')" required>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="card_reader_prefix" class="form-label form-custom-label">Card Reader Prefix<req>*</req></label>
									<input class="form-control form-custom-input" name="card_reader_prefix" id="card_reader_prefix" maxlength="21" value="{{ old('card_reader_prefix') }}" placeholder="Enter Card Reader Prefix" data-allowed-characters="A-Z a-z 0-9 -" onblur="validateCharacters('#card_reader_prefix')" required>
								</div>
							</div>

							<h3 class="text-base font-medium text-[#111827] mb-2"><abbr title="Search Engine Optimization">SEO</abbr></h3>

							<div class="mb-3">
								<label for="seo_title" class="form-label form-custom-label">Title</label>
								<input class="form-control form-custom-input" name="seo_title" id="seo_title" value="{{ old('seo_title') }}" placeholder="Enter title for SEO">
							</div>

							<div class="mb-3">
								<label for="seo_description" class="form-label form-custom-label">Description</label>
								<textarea class="form-control form-custom-input min-h-16 max-h-16" name="seo_description" id="seo_description" placeholder="Enter description for SEO">{{ old('seo_description') }}</textarea>
							</div>

							<div class="mb-6">
								<label for="seo_keywords" class="form-label form-custom-label">Keywords</label>
								<textarea class="form-control form-custom-input min-h-16 max-h-16" name="seo_keywords" id="seo_keywords" placeholder="Enter keywords for SEO">{{ old('seo_keywords') }}</textarea>
							</div>

							<h3 class="text-base font-medium text-[#111827] mb-2">Social Media</h3>

							@include('layouts._list-social-media')

							<table width="100%">
								<thead>
									<tr>
										<th class="th-custom-css"><abbr title="Social Media">SM</abbr> Site Name</th>
										<th class="th-custom-css">Page's Full URL</th>
										<th class="th-custom-css"></th>
									</tr>
								</thead>

								<tbody id="tbl-social-media"></tbody>

								<tfoot id="tmpl-social-media" class="hidden">
									<tr>
										<td class="td-custom-css">
											<input data-name="sm_name[]" class="form-control form-custom-input" list="list-social-medias" placeholder="e.g.: Facebook">
										</td>
										<td class="td-custom-css">
											<input type="url" data-name="sm_url[]" class="form-control form-custom-input">
										</td>
										<td class="td-custom-css text-center">
											<button type="button" class="btn btn-danger btn-sm" onclick="removeSocialMedia(this)" title="Remove Social Media">X</button>
										</td>
									</tr>
								</tfoot>
							</table>

							<div class="mt-2">
								<button type="button" id="btn-add-social-media" class="btn btn-info btn-info-small" onclick="addSocialMedia()">Add More SM</button>
							</div>

							<hr>

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="cancellation_within_days" class="form-label form-custom-label">Cancellation Days Within</label>
									<input class="form-control form-custom-input" type="number" name="cancellation_within_days" id="cancellation_within_days" min="1" step="1" value="{{ old('cancellation_within_days') }}" placeholder="Enter Cancellation Days Within" >
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option {{ old('status') == 'Active' ? 'selected' : '' }}>Active</option>
										<option {{ old('status') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
									</select>
								</div>
							</div>

							<div class="mt-3 text-end"><button class="btn btn-main btn-primary-small">Add</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-custom')
<style>
.select2-container--default .select2-selection--single { height: 34px; padding-top: 0.375rem; }
.select2-container--default .select2-selection--single .select2-selection__rendered { padding-left: 0.75rem; }
.select2-container--default .select2-selection--single .select2-selection__arrow { top: 6px; right: 10px; }
</style>
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
var action = 'add';
var getStatesUrl = "{{ route('admin.get-states', [ 'countryId' => 0 ]) }}";
var getCitiesUrl = "{{ route('admin.get-cities', [ 'stateId' => 0 ]) }}";
</script>

<script src="{{ asset('js/admin/tour-operator-add-edit.js') }}"></script>
@endsection
