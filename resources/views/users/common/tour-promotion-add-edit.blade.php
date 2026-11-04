@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(strpos(Route::currentRouteName(), $routePrefix . '.promotion.create') !== false) {
			$action = 'Add';

			$endDateMDY = date('m/d/Y');
			$endDateYMD = date('Y-m-d');
		} else {
			$action = 'Update';

			if($tourPromotion->end_date == '0000-00-00') {
				$endDateMDY = '';
				$endDateYMD = '';
			} else {
				$endDateMDY = date('m/d/Y', strtotime($tourPromotion->end_date));
				$endDateYMD = $tourPromotion->end_date;
			}
		}
	@endphp

<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">{{ $action }} Tour Promotion</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.promotion.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>
					<!-- <div class="content-title">
						<h2 class="section-title">{{ $action }} Tour Promotion</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.promotion.create') }}" {!! $action == 'Add' ? 'class="active"' : '' !!}><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.promotion.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form class="form" method="post" action="{{ $formSubmitRoute }}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.promotion.index') }}" data-on-success-msg=
						@if($action == 'Add')
						"Promotion added"
						@else {{-- update --}}
						"Promotion details updated"
						@endif >

						@if($action == 'Update')
							@method('PUT')
						@endif
							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="tour_package" class="form-label form-custom-label">Tour Packages <req>*</req></label>
									<select class="form-select form-custom-input" name="tour_package" id="tour_package" required>

										@foreach($tourPackages as $to)
										<option value="{{ $to->id }}" {{ $to->id == $tourPromotion->tour_package_id ? 'selected' : '' }} >{{ $to->name }}</option>
										@endforeach
									</select>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="code" class="form-label form-custom-label">Promo. Code <req>*</req></label>
									<input class="form-control form-custom-input" name="code" id="code" maxlength="20" value="{{ $tourPromotion->code }}" placeholder="Enter promotion code" data-allowed-characters="A-Z a-z 0-9" onblur="validateCharacters('#code')" required>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="max_allowed_usages" class="form-label form-custom-label">Max Allowed Usages <req>*</req> <span class="text-[8px]">(Enter Zero for Unlimited Usages)</span></label>
									<input type="number" class="form-control form-custom-input" name="max_allowed_usages" id="max_allowed_usages" value="{{ $tourPromotion->max_allowed_usages }}" placeholder="Enter max allowed usages" required>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="min_allowed_tourists" class="form-label form-custom-label">Min Allowed Tourists <req>*</req> <span class="text-[8px]"></span></label>
									<input type="number" class="form-control form-custom-input" name="min_allowed_tourists" id="min_allowed_tourists"  value="{{ $tourPromotion->min_allowed_tourists }}" placeholder="Enter min allowed tourists" required>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="date-range" class="form-label form-custom-label">Promo. Date Range <req>*</req></label>
									<input type="text" name="date_range" id="date_range" class="form-control form-custom-input" placeholder="Enter date range" required>
								</div>

								<input type="hidden" name="startdate" id="startdate"  value="{{ date('m/d/Y', strtotime($tourPromotion->start_date)) }}">

								<input type="hidden" name="enddate" id="enddate" value="{{ date('m/d/Y', strtotime($tourPromotion->end_date)) }}">

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="tour_package" class="form-label form-custom-label">Promotion Type <req>*</req></label>
									<select class="form-select form-custom-input" name="is_extra_charge" id="is_extra_charge" required>
										<option value="0" @if($tourPromotion->is_extra_charge == '0') selected @endif>Discount</option>
										<option value="1" @if($tourPromotion->is_extra_charge == '1') selected @endif>Extra Charge</option>
									</select>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="discount_value" class="form-label form-custom-label">Amount <req>*</req></label>
									<div class="input-group flex items-center">
										<input type="number" class="form-control !rounded-tr-none !rounded-br-none form-custom-input" name="discount_value" id="discount_value" min="0.01" step="0.01" value="{{ trimDeci($tourPromotion->discount_value) }}" placeholder="Enter discount" required>
										<div class="input-group-append bg-light">
											<select class="form-select !rounded-tl-none !rounded-bl-none form-custom-input" name="discount_value_type">
												<option {{ $tourPromotion->discount_value_type == 'Percent' ? 'selected' : '' }}>Percent</option>
												<option {{ $tourPromotion->discount_value_type == 'Fixed Money' ? 'selected' : '' }}>Fixed Money</option>
											</select>
										</div>
									</div>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option {{ $tourPromotion->status == 'Active' ? 'selected' : '' }}>Active</option>
										<option {{ $tourPromotion->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
									</select>
								</div>

								<div class="w-full md:w-1/3 lg:w-1/4 max-w-full px-3 mb-4"><button class="btn btn-main btn-primary-small">{{ $action }}</button></div>
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
    
@endsection

@section('js-libraries')
	@if(App::environment('local'))
		<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	@else
		<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	@endif

	<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
	<script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
	
@endsection

@section('js-custom')
	@if($action == 'Add' && session('userType') == 'Admin')
		<x-admin-js />
	@endif
	
	<script src="{{ asset('js/user-common/tour-promotion-add-edit.js') }}?v=4"></script>
@endsection
