@extends('layouts.default-2')

@php
	$routePrefix = routePrefix();
	if(session('userType') == 'Admin') {
		$msgOnSuccess = 'Tour operator staff user added';
		$uTypeAdmin = true;
	} else {
		$msgOnSuccess = 'Staff user added';
		$uTypeAdmin = false;
	}
	$userRole = userRole();
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Add @if($uTypeAdmin) Tour Operator @endif Staff User</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.staff.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Add @if($uTypeAdmin) Tour Operator @endif Staff User</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						@if ($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
						<a class="mr-2 btn-warning-small active" href="{{ route($routePrefix . '.staff.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						@endif
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.staff.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form method="post" action="{{ route($routePrefix . '.staff.store') }}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.staff.index') }}" data-on-success-msg="{{ $msgOnSuccess }}">
							@csrf

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<div class="flex flex-wrap -mx-3">
										<div class="w-1/2 max-w-full px-3">
											<label for="first_name" class="form-label form-custom-label">First Name</label>
											<input class="form-control form-custom-input" name="first_name" id="first_name" maxlength="50" placeholder="Enter first name" onblur="validateCharacters('#first_name')" required>
										</div>
										<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
										<div class="w-1/2 max-w-full px-3">
											<label for="last_name" class="form-label form-custom-label">Last Name</label>
											<input class="form-control form-custom-input" name="last_name" id="last_name" maxlength="50" placeholder="Enter last name" onblur="validateCharacters('#last_name')">
										</div>
									</div>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="email" class="form-label form-custom-label">Email Address <req>*</req> </label>
									<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50" placeholder="Enter email address" required>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="password" class="form-label flex items-center form-custom-label mb-1">Password <req>*</req><div id="password-strength-status" class=""> </div> </label>
									<div class="input-group flex items-center">
										<input type="password" class="form-control form-custom-input" name="password" id="password" placeholder="Enter password">
										<div class="input-group-text ml-1">
											<button type="button" data-toggle="#password" class="btn btn-sm toggle-password btn-info-small"><i class="fa fa-fw fa-eye"></i></button>
										</div>
									</div>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="phone_number" class="form-label form-custom-label">Phone Number</label>
									<input type="tel" class="form-control form-custom-input" name="phone_number" id="phone_number" maxlength="30" placeholder="Enter phone number" data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#phone_number')">
								</div>	

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Status</label>
									<select class="form-select form-custom-input" name="status" id="status">
										<option>Active</option>
										<option>Inactive</option>
									</select>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label class="form-label form-custom-label">Department <req>*</req></label>
									<select name="department_id" id="department_id" class="form-select form-custom-input" required> 
										<option disabled selected>Please select</option>
										@foreach($departments as $department)
												<option value="{{ $department->id }}" }}>{{ $department->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label class="form-label form-custom-label">Roles <req>*</req></label>
									<select name="role" id="role" class="form-select form-custom-input" required> 
										<option disabled selected>Please select</option>
										@foreach($roles as $role)
												<option value="{{ $role->id }}">{{ $role->display_name }}</option>
										@endforeach
									</select>
								</div>

								<div class="w-full md:w-1/2 max-w-full px-3 mb-3" id="sub_role_div">
									<label class="form-label form-custom-label">Sub Role </label>
									<select name="sub_role" id="sub_role" class="form-select form-custom-input"> 
										<option value="">Please select</option>
										@foreach($subRoles as $role)
												<option value="{{ $role->id }}">{{ $role->display_name }}</option>
										@endforeach
									</select>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="capacity" class="form-label form-custom-label">Capacity </label>
									<input type="number" class="form-control form-custom-input" name="capacity" id="capacity" placeholder="Enter capacity" >
								</div>	
							</div>
							<div>
								<div>
									<label hidden class="label-permissions form-label form-custom-label">Permissions <req>*</req></label>
								</div>
								<div class="row permission-role-wise">
									
								</div>
							</div>

							<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">Add</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
<link href="{{ asset('plugins/multi-selection-checkbox-dropdown/multi-selection-checkbox-dropdown.css') }}" rel="stylesheet">
@endsection

@section('js-libraries')
<script src="{{ asset('plugins/multi-selection-checkbox-dropdown/multi-selection-checkbox-dropdown.js') }}"></script>
@endsection

@section('js-custom')
	@if(session('userType') == 'Admin')
<x-admin-js />
	@endif

<script src="{{ asset('js/user-common/tour-operator-staff-add-edit.js') }}"></script>
<script src="{{ asset('js/utils/password-strength.js') }}"></script>
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script>
	$(document).ready(function() {
		$("#sub_role_div").hide();
		// note this function is added from js/utils/password-strength.js
		if( !window.hasOwnProperty("_PasswordStrength") ){
			window._PasswordStrength = new PasswordStrength();
		}
	
	});
</script>
<script >
	$('#role').change(function() {
    	var id = $(this).val();
    	var userId = $('#id').val();
	    $.ajax({
		  type: "GET",
		  url: "{{route($routePrefix . '.user-permission')}}",
		  data: {id: id,  userId: userId},
		  success: function(data)
		  {
		  	if (data.html.length > 0) { 
		  		$('.label-permissions').attr('hidden', false);
		  		$('.permission-role-wise').html(data.html);
		  	} else {
		  		$('.label-permissions').attr('hidden', true);
		  		$('.permission-role-wise').html('');
		  	}

		  	$("#sub_role_div").hide();
		  	if (data.role.name != "tour-guide") {
		  		$("#sub_role_div").show();
		  	}
		  }
		});	  
	});

</script>

@endsection
