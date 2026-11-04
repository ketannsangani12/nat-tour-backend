@extends('layouts.default-2')

@section('main-content')
	@php
		$routePrefix = routePrefix();
		if(Route::currentRouteName() == $routePrefix . '.role.create')
			$action = 'Add';
		else // if(Route::currentRouteName() == $routePrefix . '.role.edit')
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
								<h2 class="section-title !mb-0">{{ $action }} Role</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.role.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">{{ $action }} Role</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.role.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.role.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form method="post" action="{{ $formSubmitRoute }}" onsubmit="if(!validateCharacters('#name'))return false;return submitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.role.index') }}" data-on-success-msg=
							@if($action == 'Add')
							"Tour operator staff role added"
							@else {{-- update --}}
							"Tour operator staff role updated"
							@endif >

							@if($action == 'Update')
								@method('PUT')
							@endif

							<div class="flex flex-wrap -mx-3">
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
									<input class="form-control form-custom-input" name="display_name" id="name" maxlength="30" value="{{ $role->display_name }}" placeholder="Enter role name" data-allowed-characters="A-Z a-z space - + ( ) , . : /" onblur="validateCharacters('#name')" required>
								</div>
								<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="status" class="form-label form-custom-label">Guard <req>*</req></label>
									<select class="form-select form-custom-input" name="guard_name" id="guard_name" @if($action == 'Update' && $id == auth(session('guard'))->id())  @endif>
										<option disabled selected>Please select</option>
										<option {{ $role->guard_name == 'admin' ? 'selected' : '' }} value="admin">Admin</option>
										<option {{ $role->guard_name == 'tour_operator_staff' ? 'selected' : '' }} value="tour_operator_staff">Staff</option>
										<option {{ $role->guard_name == 'affiliate' ? 'selected' : '' }} value="affiliate">Affiliate</option>
									</select>
								</div>
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label for="display_order" class="form-label form-custom-label">Description </label>
									<textarea class="form-control form-custom-input" name="description" placeholder="Enter description" required> {{ $role->description }}</textarea>
								</div>
							</div>
							<div>
								<div>
									<label hidden class="label-permissions form-label form-custom-label">Permissions <req>*</req></label>
								</div>
								<div class="row permission-role-wise">
									
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
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script >
	$(document).ready(function() {
    	var role_id = $('#id').val();
    	var guard_name = $('#guard_name').val();
    	if(role_id){
    		 $.ajax({
				  type: "GET",
				  url: "{{route($routePrefix . '.role-permission-edit')}}",
				  data: {role_id: role_id, guard_name: guard_name},
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
    	}
    	$('#guard_name').change(function() {
    			var guard_name = $('#guard_name').val();
    			var role_id = $('#id').val();
	    		$.ajax({
					  type: "GET",
					  url: "{{route($routePrefix . '.role-permission-add')}}",
					  data: {role_id: role_id, guard_name: guard_name},
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
		});
</script>
@endsection