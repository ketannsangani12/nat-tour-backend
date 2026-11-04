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
						<h2 class="section-title">Countries</h2>
					</div>

					<div class="sub-nav">
						<span class="sub-nav-sep"></span>
					</div>

					<div class="main-content">
						<div class="flex flex-wrap -mx-3">
							<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
								<label for="id" class="form-label form-custom-label">Country <req>*</req></label>
								<select class="form-select form-custom-input" name="id" id="id" required>
									@foreach($allCountries as $country)
									<option value="{{ $country->id }}" {{ $country->id == $activeCountry->id ? 'selected' : '' }}>{{ $country->name }}</option>
									@endforeach
								</select>
							</div>

							<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
								<label for="code" class="form-label form-custom-label">Code</label>
								<input class="form-control form-custom-input" name="code" id="country_code" maxlength="3" value="{{ $activeCountry->code }}" placeholder="Enter country code" required readonly>
							</div>

							<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
								<label for="currency" class="form-label form-custom-label">Currency</label>
								<input class="form-control form-custom-input" name="currency" id="currency_code" maxlength="3" value="{{ $activeCountry->currency }}" placeholder="Enter currency" required readonly>
							</div>

							<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
								<label for="currency_symbol" class="form-label form-custom-label">Currency Symbol</label>
								<input class="form-control form-custom-input" name="currency_symbol" id="currency_symbol" maxlength="3" value="{{ $activeCountry->currency_symbol }}" placeholder="Enter currency symbol" required readonly>
							</div>

							<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
								<label for="phone_code" class="form-label form-custom-label">Phone Code</label>
								<div class="flex items-stretch">
									<span class="inline-flex items-center px-3 border border-r-0 border-[#D1D5DB] rounded-l-lg bg-[#F3F4F6] text-[#6B7280] text-sm">+</span>
									<input class="form-control form-custom-input !rounded-l-none" name="phone_code" id="phone_code" maxlength="5" value="{{ $activeCountry->phone_code }}" placeholder="1" readonly>
								</div>
							</div>

							<div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-3">
								<label for="locale" class="form-label form-custom-label">Locale</label>
								<input class="form-control form-custom-input" name="locale" id="locale" maxlength="10" value="{{ $activeCountry->locale }}" placeholder="en-US" readonly>
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
<!-- <link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet" type="text/css" /> -->
@endsection

@section('js-libraries')
<!-- <script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script> -->
@endsection

@section('js-custom')
	<script>
		window.countrySwitcherData = {
			allCountries: @json($allCountries),
			activeCountry: @json($activeCountry),
			setActiveCountryUrl: "{{ route($routePrefix . '.set.active.country') }}",
			csrfToken: "{{ csrf_token() }}"
		};
	</script>
	<script src="{{ asset('js/admin/CountrySwitcher.js') }}"></script>
@endsection
