@extends('layouts.default-2')
@php
    $routePrefix = routePrefix();
@endphp
@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-83px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Add Tenant</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.tenant.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<div class="main-content">
						@if($errors->any())
							@include('layouts._errors')
						@endif

						@if(session('message'))
							@include('layouts._message')
						@endif

						<form method="post" action="{{ route($routePrefix . '.tenant.store') }}" enctype="multipart/form-data" onsubmit="return validateForm()">
							@csrf

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/4 max-w-full px-3 mb-3">
									<label for="subdomain" class="form-label form-custom-label">Subdomain <req>*</req></label>
									<input type="text" class="form-control form-custom-input" name="subdomain" id="subdomain" maxlength="20" value="{{ old('subdomain') }}" pattern="[a-z]+" placeholder="Enter subdomain" required>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
									<input type="text" class="form-control form-custom-input" name="name" id="name" maxlength="80" value="{{ old('name') }}" placeholder="Enter name" required>
								</div>
								<div class="w-full md:w-1/4 max-w-full px-3 mb-3">
									<label for="timezone" class="form-label form-custom-label">Timezone <req>*</req></label>
									<select name="timezone" id="timezone" class="form-control form-custom-input" required>
										<option value="">Select timezone</option>
										@foreach(timezone_identifiers_list() as $timezone)
											<option value="{{ $timezone }}" {{ old('timezone') == $timezone ? 'selected' : '' }}>{{ $timezone }}</option>
										@endforeach
									</select>
								</div>
							</div>

							<div class="mt-3 text-end"><button class="btn btn-main btn-primary-small">Create</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection