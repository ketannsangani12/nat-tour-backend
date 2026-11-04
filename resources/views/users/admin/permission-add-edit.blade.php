@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(Route::currentRouteName() == $routePrefix . '.permission.create')
			$action = 'Add';
		else // if(Route::currentRouteName() == $routePrefix . '.permission.edit')
			$action = 'Update';
	@endphp

<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">{{ $action }}  Permission</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.permission.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.permission.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div>

					<div class="main-content">
						<form method="post" action="{{ $formSubmitRoute }}" onsubmit="if(!validateCharacters('#name'))return false;return submitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.permission.index') }}" data-on-success-msg=
								@if($action == 'Add')
									"Permission added"
								@else {{-- update --}}
									"Permission updated"
								@endif >

								@if($action == 'Update')
									@method('PUT')
								@endif
						
							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
									<input class="form-control form-custom-input" name="display_name" id="name" maxlength="30" value="{{ $permission->display_name }}" placeholder="Enter permission name" data-allowed-characters="A-Z a-z space - + ( ) , . : /" onblur="validateCharacters('#name')" required>
								</div>
							
								<!-- <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="display_order" class="form-label form-custom-label">Display Order <req>*</req></label>
									<input type="number" class="form-control form-custom-input" name="display_order" id="display_order" min="0" max="255" value="{{ $permission->display_order }}" placeholder="Enter display order for the permission" required>
								</div> -->
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="display_order" class="form-label form-custom-label">Description <req>*</req></label>
									<textarea class="form-control form-custom-input" name="description" placeholder="Enter description" required> {{ $permission->description }}</textarea>
								</div>
							
								<!-- <div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option {{ $permission->status == 'Active' ? 'selected' : '' }}>Active</option>
										<option {{ $permission->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
									</select>
								</div> -->
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
