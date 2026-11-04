@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(Route::currentRouteName() == $routePrefix . '.admin-user.create'){
			$action = 'Add';
		} else{
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
									<h2 class="section-title !mb-0">{{ $action }} Administrator</h2>
								</div>
							</div>
							<div class="w-full md:w-1/2 max-w-full px-3">
								<div class="sub-nav flex items-center justify-center md:justify-end">
									<a class="btn-primary-small" href="{{ route($routePrefix . '.admin-user.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
								</div>
							</div>
						</div>

						<!-- <div class="content-title">
							<h2 class="section-title">{{ $action }} Administrator</h2>
						</div>

						<div class="sub-nav flex items-center justify-end mb-8">
							<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.admin-user.create') }}" {!! $action == 'Add' ? 'class="active"' : '' !!}><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.admin-user.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
						</div> -->

						<div class="main-content">
							<form class="form" method="post" action="{{ $formSubmitRoute }}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.admin-user.index') }}" data-on-success-msg=
								@if($action == 'Add')
								"Admin user added"
								@else {{-- edit-update --}}
								"Admin user details updated"
								@endif >

								@if($action == 'Update')
									@method('PUT')
								@endif
							
								<div class="flex flex-wrap -mx-3">
									<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
										<label for="name" class="form-label form-custom-label">Admin Name <req>*</req></label>
										<input class="form-control form-custom-input" name="name" id="name" maxlength="50" value="{{ $admin->name }}" placeholder="Enter admin name" onblur="validateCharacters('#name')" required>
									</div>
									<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
									<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
										<label for="email" class="form-label form-custom-label">Email Address <req>*</req></label>
										<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50" value="{{ $admin->email }}" placeholder="Enter email address" required>
									</div>

									<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
										<label for="password" class="form-label form-custom-label">Password 
											@if($action == 'Add') <req>*</req> @endif
											<span id="password-strength-status" class="">@if($action != 'Add')<small>(Enter only if need to be changed)</small>@endif</span>
										</label>
										
										<div class="input-group flex items-center">
											<input type="password" class="form-control form-custom-input" name="password" id="password" placeholder="Enter password" @if($action == 'Add') required @endif> 
											<div class="input-group-text ml-1">
												<button type="button" class="btn btn-sm toggle-password btn-info-small" data-toggle="#password"><i class="fa fa-fw fa-eye"></i></button>
											</div>
										</div>
									</div>
									
									<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
										<label for="status" class="form-label form-custom-label">Status</label>
										<select class="form-select form-custom-input" name="status" id="status" @if($action == 'Update' && $id == auth(session('guard'))->id())  @endif>
											<option {{ $admin->status == 'Active' ? 'selected' : '' }}>Active</option>
											<option {{ $admin->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
										</select>
									</div>

									<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
										<label class="form-label form-custom-label">Department <req>*</req></label>
										<select name="department_id" id="department_id" class="form-select form-custom-input" required> 
											<option disabled selected>Please select</option>
											@foreach($departments as $department)
													<option value="{{ $department->id }}" @if(!empty($admin)) {{ ($department->id == $admin->department_id) ? 'selected' : '' }} @endif>{{ $department->name }}</option>
											@endforeach
										</select>
									</div>
									<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
										<label class="form-label form-custom-label">Roles <req>*</req></label>
										<select name="role" id="role" class="form-select form-custom-input" required> 
											<option disabled selected>Please select</option>
											@foreach($roles as $role)
													<option value="{{ $role->id }}" @if(!empty($modelHasRole)) {{ ($role->id == $modelHasRole->role_id) ? 'selected' : '' }} @endif>{{ $role->display_name }}</option>
											@endforeach
										</select>
									</div>
								</div>
								<div>
									<div>
										<label hidden class="label-permissions form-label form-custom-label">Permissions <req>*</req></label>
									</div>
									<div class="row permission-role-wise">
										
									</div>
								</div>
						
								<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">{{ $action }}</button></div>
							</form>
						</div> {{-- /.main-content --}}
					</div>
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('js-custom')
<script src="{{ asset('js/admin/admin-add-edit.js') }}"></script>
<script src="{{ asset('js/utils/password-strength.js') }}"></script>
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script>
// validate the password 
// js/utils/password-strength.js
$(document).ready(function() {
	$(document).on('click', '#password', ()=>{
		if(!window.hasOwnProperty("_PasswordStrength")){
			// only validate the password on update if the user clicks it
			window._PasswordStrength = new PasswordStrength();
		}
	});
});
</script>
<script >
	$('#role').change(function() {
    	var id = $(this).val();
    	var userId = $('#id').val();
	    $.ajax({
		  type: "GET",
		  url: "{{route($routePrefix . '.admin-user-permission')}}",
		  data: {id: id,  userId: userId},
		  success: function(html)
		  {
		  	if(html.length > 0){
		  		$('.label-permissions').attr('hidden', false);
		  		$('.permission-role-wise').html(html);
		  	}else{
		  		$('.label-permissions').attr('hidden', true);
		  		$('.permission-role-wise').html('');
		  	}
		   
		  }
		});	  
	});

	$(document).ready(function() {
    	var roleId = ($("#role").val());
    	var userId = $('#id').val();
    	 $.ajax({
		  type: "GET",
		  url: "{{route($routePrefix . '.admin-user-permission-edit')}}",
		  data: {roleId: roleId, userId: userId},
		  success: function(html)
		  {
		  	if(html.length > 0){
		  		$('.label-permissions').attr('hidden', false);
		  		$('.permission-role-wise').html(html);
		  	}else{
		  		$('.label-permissions').attr('hidden', true);
		  		$('.permission-role-wise').html('');
		  	}
		   
			allPermissions.editModuleInitPermission();
		  }
		});	  
	});
</script>
@endsection
