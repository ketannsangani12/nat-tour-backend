@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(Route::currentRouteName() == $routePrefix . '.country.create')
			$action = 'Add';
		else // if(Route::currentRouteName() == $routePrefix . '.country.edit')
			$action = 'Update';
	@endphp

<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">{{ $action }} Country</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.country.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.country.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div>

					<div class="main-content">
						<form class="form" method="post" action="{{ $formSubmitRoute }}" onsubmit="if(!validateCharacters('#name'))return false;return submitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.country.index') }}" data-on-success-msg=
					@if($action == 'Add')
				"Country added"
					@else {{-- edit-update --}}
				"Country details updated"
					@endif >

					@if($action == 'Update')
						@method('PUT')
					@endif

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 lg:w-1/3 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
									<input class="form-control form-custom-input" name="name" id="name" maxlength="80" value="{{ $country->name }}" placeholder="Enter country name" onblur="validateCharacters('#name')" required>
								</div>

								<div class="w-full md:w-1/2 lg:w-2/12 max-w-full px-3 mb-3">
									<label for="code" class="form-label form-custom-label">Code</label>
									<input class="form-control form-custom-input" name="code" id="code" maxlength="3" value="{{ $country->code }}" placeholder="Enter country code" onblur="validateCharacters('#code')" required>
								</div>

								<div class="w-full md:w-1/2 lg:w-2/12 max-w-full px-3 mb-3">
									<label for="currency" class="form-label form-custom-label">Currency</label>
									<input class="form-control form-custom-input" name="currency" id="currency" maxlength="3" value="{{ $country->currency }}" placeholder="Enter currency" onblur="validateCharacters('#currency')" required>
								</div>

								<div class="w-full md:w-1/2 lg:w-2/12 max-w-full px-3 mb-3">
									<label for="currency_symbol" class="form-label form-custom-label">Currency Symbol</label>
									<input class="form-control form-custom-input" name="currency_symbol" id="currency_symbol" maxlength="3" value="{{ $country->currency_symbol }}" placeholder="Enter currency symbol" onblur="validateCharacters('#currency_symbol')" required>
								</div>

								<div class="w-full md:w-1/2 lg:w-2/12 max-w-full px-3 mb-3">
									<label for="phone_code" class="form-label form-custom-label">Phone Code</label>
									<div class="flex items-stretch">
										<span class="inline-flex items-center px-3 border border-r-0 border-[#D1D5DB] rounded-l-lg bg-[#F3F4F6] text-[#6B7280] text-sm">+</span>
										<input class="form-control form-custom-input !rounded-l-none" name="phone_code" id="phone_code" maxlength="5" value="{{ $country->phone_code }}" placeholder="1">
									</div>
								</div>

								<div class="w-full md:w-1/2 lg:w-2/12 max-w-full px-3 mb-3">
									<label for="locale" class="form-label form-custom-label">Locale</label>
									<input class="form-control form-custom-input" name="locale" id="locale" maxlength="10" value="{{ $country->locale }}" placeholder="en-US">
								</div>

								<div class="w-full md:w-1/2 lg:w-2/12 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option {{ $country->status == 'Active' ? 'selected' : '' }}>Active</option>
										<option {{ $country->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
									</select>
								</div>
							</div>

							<div class="mt-3 flex justify-end"><button class="btn btn-main btn-primary-small">{{ $action }}</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection
