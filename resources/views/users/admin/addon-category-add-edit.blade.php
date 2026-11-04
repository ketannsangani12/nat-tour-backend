@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(Route::currentRouteName() == $routePrefix . '.addon-category.create')
			$action = 'Add';
		else // if(Route::currentRouteName() == $routePrefix . '.addon-category.edit')
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
								<h2 class="section-title !mb-0">{{ $action }} Addon Category</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.addon-category.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<div class="main-content">
						<form method="post" action="{{ $formSubmitRoute }}" onsubmit="if(!validateCharacters('#slug'))
						return false;return submitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.addon-category.index') }}" data-on-success-msg=
						@if($action == 'Add')
					"Addon category added"
						@else {{-- edit-update --}}
					"Addon category details updated"
						@endif >

						@if($action == 'Update')
							@method('PUT')
						@endif

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Category Name <req>*</req></label>
									<input class="form-control form-custom-input" name="name" id="name" maxlength="80" value="{{ $addonCategory->name }}" placeholder="Enter category name" required>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="slug" class="form-label form-custom-label">Slug <req>*</req></label>
									<input class="form-control form-custom-input" name="slug" id="slug" maxlength="80" value="{{ $addonCategory->slug }}" placeholder="Enter slug" data-allowed-characters="a-z" onblur="validateCharacters('#slug')" required>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="sort_order" class="form-label form-custom-label">Sort Order</label>
									<input type="number" class="form-control form-custom-input" name="sort_order" id="sort_order" min="0" value="{{ $addonCategory->sort_order ?? 0 }}" placeholder="Enter sort order">
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option {{ $addonCategory->status == 'Active' ? 'selected' : '' }}>Active</option>
										<option {{ $addonCategory->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
									</select>
								</div>
							</div>

							<div class="mt-3 flex flex-wrap"><button class="btn btn-main btn-primary-small">{{ $action }}</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection
