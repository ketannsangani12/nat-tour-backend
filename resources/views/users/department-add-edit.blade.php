@extends('layouts.default-2')

@section('main-content')

@php
	$routePrefix = routePrefix();
	if (Route::currentRouteName() == $routePrefix . '.department.create') {
		$action = 'Add';
	} else {
		$action = 'Update';
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
								<h2 class="section-title !mb-0">{{ $action }} Department</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.department.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">{{ $action }} Department</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.department.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.department.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form 
							method="post" 
							action="{{ $action == 'Add' ? route($routePrefix . '.department.store') : route($routePrefix . '.department.update', $department->id) }}" 
							onsubmit="return submitForm(this)" 
							data-redirect-on-success="{{ route($routePrefix . '.department.index') }}" 
							data-on-success-msg="{{ $action == 'Add' ? 'Department added' : 'Department details updated' }}"
						>
							@csrf
							@if($action == 'Update')
								@method('PUT')
							@endif

							<div class="flex flex-wrap -mx-3">
								<!-- Department Name -->
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Department Name <req>*</req></label>
									<input 
										class="form-control form-custom-input" 
										name="name" 
										id="name" 
										maxlength="80" 
										value="{{ old('name', $department->name ?? '') }}" 
										placeholder="Enter Department name" 
										required
									>
								</div>

								<!-- Status -->
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option value="Active" {{ old('status', $department->status ?? '') == 'Active' ? 'selected' : '' }}>Active</option>
										<option value="Inactive" {{ old('status', $department->status ?? '') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
									</select>
								</div>

								@if (hasSetting('DEPARTMENT_PACKAGE'))
									<!-- Assign Packages -->
									<div class="w-full max-w-full px-3 mb-3">
										<input type="hidden" name="is_package_assigned" value="0">
										<div class="form-check flex items-center">
											<input 
												type="checkbox" 
												class="form-check-input h-5 w-5 mr-2" 
												name="is_package_assigned" 
												id="is_package_assigned" 
												value="1" 
												onclick="togglePackageButtons()" 
												{{ old('is_package_assigned', $department->is_package_assigned == 1) ? 'checked' : '' }}
											>
											<label class="form-check-label form-custom-label" for="is_package_assigned">Assign Packages to Department</label>
										</div>
									</div>

									<!-- Packages List -->
									<div class="w-full md:w-1/2 max-w-full px-3 mb-3 hidden" id="packageButtonsContainer">
										<label class="form-custom-label" for="packages">Select Packages:</label>
										<div class="d-flex flex-wrap gap-2">
											@foreach ($packages as $package)
												<button 
													type="button" 
													class="btn {{ isset($department) && $department->packages->contains($package->id) ? 'btn-primary' : 'btn-outline-primary' }} package-btn" 
													onclick="togglePackageSelection(this)"
												>
													{{ $package->name }}
												</button>
												<input 
													type="checkbox" 
													name="package_ids[]" 
													value="{{ $package->id }}" 
													class="hidden package-checkbox" 
													{{ isset($department) && $department->packages->contains($package->id) ? 'checked' : '' }}
												>
											@endforeach
										</div>
									</div>
								@endif
							</div>

							<div class="mt-3 flex justify-end">
								<button class="btn btn-main btn-primary-small">{{ $action }}</button>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('js-custom')
	<script>
		var departmentIndexUrl = null;
		var isPackageAssigned = "{{ $department->is_package_assigned ?? 0 }}";
	</script>

	<script src="{{ asset('js/user-common/department.js') }}"></script>
@endsection
