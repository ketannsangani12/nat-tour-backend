@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(Route::currentRouteName() == $routePrefix . '.tax.create')
			$action = 'Add';
		else // if(Route::currentRouteName() == $routePrefix . '.tax.edit')
			$action = 'Update';
	@endphp

<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">{{ $action }} Tax</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.tax.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">{{ $action }} Tax</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.tax.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.tax.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form method="post" action="{{ $formSubmitRoute }}" onsubmit="if(!validateCharacters('#name'))return false;return submitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.tax.index') }}" data-on-success-msg=
						@if($action == 'Add')
						"Tax details added" 
							@else {{-- edit-update --}}
						"Tax details updated"
							@endif >

						@if($action == 'Update')
							@method('PUT')
						@endif
						
							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="country" class="form-label form-custom-label">Country <req>*</req></label>
									<select class="form-select form-custom-input" name="country" id="country" onchange="getStates(this, '#state')" required>
										<option value="">Select Country</option>
										@foreach($countries as $country)
											<option value="{{ $country->id }}" {{ $tax->country_id == $country->id ? 'selected' : '' }}>{{ $country->name }}</option>
										@endforeach
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="state" class="form-label form-custom-label">State <req>*</req></label>
									<select class="form-select form-custom-input" name="state" id="state" required>
										@if(!isset($states))
										<option value="">Select a Country First</option>
										@else {{-- if(isset($states)) --}}
											@foreach($states as $state)
												<option value="{{ $state->id }}" {{ $tax->state_id == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
											@endforeach
										@endif
									</select>
								</div>
						
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Tax Name <req>*</req></label>
									<input class="form-control form-custom-input" name="name" id="name" maxlength="80" value="{{ $tax->name }}" placeholder="Enter tax name" data-allowed-characters="A-Z a-z space ' - ( ) , . + : /" onblur="validateCharacters('#name')" required>
								</div>
						
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="value" class="form-label form-custom-label">Tax Value <req>*</req></label>
									<div class="input-group flex items-center">
										<input type="number" class="form-control form-custom-input !rounded-r-none" name="value" id="value" min="0" max="99999.99" step="0.01" value="{{ $tax->value }}" placeholder="Enter tax value" required>
										<div class="mr-2">
											<select name="value_type" id="value_type" class="form-select form-custom-input !rounded-l-none">
												<option {{ $tax->value_type == 'Percent' ? 'selected' : '' }}>Percent</option>
												<option {{ $tax->value_type == 'Fixed Money' ? 'selected' : '' }}>Fixed Money</option>
											</select>
										</div>
									</div>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option {{ $tax->status == 'Active' ? 'selected' : '' }}>Active</option>
										<option {{ $tax->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
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

@section('js-custom')
<script>
var getStatesUrl = "{{ route($routePrefix . '.get-states', [ 'countryId' => 0 ]) }}";
</script>
@endsection
