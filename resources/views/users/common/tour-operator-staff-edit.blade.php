@extends('layouts.default-2')

@php
$routePrefix = routePrefix();
if(session('userType') == 'Admin') {
$generalMsgOnSuccess = "Tour operator staff user's general details updated";
$uTypeAdmin = true;
} else {
$generalMsgOnSuccess = "Staff user's general details updated";
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
								<h2 class="section-title !mb-0">@if($uTypeAdmin) Tour Operator @endif Staff Users</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								@if ($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
								<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.staff.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
								@endif
								<a class="btn-primary-small active" href="{{ route($routePrefix . '.staff.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">Update @if($uTypeAdmin) Tour Operator @endif Staff User</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						@if ($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . '.staff.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						@endif
						<a class="btn-outline-primary-small active" href="{{ route($routePrefix . '.staff.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<div class="card">
							<nav class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-x-auto" id="nav-tab" role="tablist">
								<button type="button" class="tabs-custom-link active" id="nav-general" data-bs-toggle="tab" data-bs-target="#tab-general" role="tab" aria-controls="tab-general" aria-selected="true">General</button>
							</nav>

							<div class="tab-content">
								<div class="tab-pane active" id="tab-general" role="tabpanel" aria-labelledby="tab-general">
									<div class="card-body p-5 border border-[#E5E7EB] rounded-b-md">
										<form class="form" method="post" action="{{ route($routePrefix . '.staff.update-general', [ 'id' => $staff->id ]) }}" onsubmit="return validateAndSubmitForm(this)" data-on-success-msg="{{ $generalMsgOnSuccess }}">
											@csrf
											<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
											<div class="flex flex-wrap -mx-3">
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<div class="flex flex-wrap -mx-3">
														<div class="w-1/2 max-w-full px-3">
															<label for="first_name" class="form-label form-custom-label">First Name</label>
															<input class="form-control form-custom-input" name="first_name" id="first_name" maxlength="50" placeholder="Enter first name" value="{{ $staff->first_name }}" onblur="validateCharacters('#first_name')" required>
														</div>
														<div class="w-1/2 max-w-full px-3">
															<label for="last_name" class="form-label form-custom-label">Last Name</label>
															<input class="form-control form-custom-input" name="last_name" id="last_name" maxlength="50" placeholder="Enter last name" value="{{ $staff->last_name }}" onblur="validateCharacters('#last_name')">
														</div>
													</div>
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="email" class="form-label form-custom-label">Email Address <req>*</req></label>
													<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50" placeholder="Enter email address" value="{{ $staff->email }}" required>
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="password" class="form-label form-custom-label">Password <span id="password-strength-status" class=""> </span> </label>
													<div class="input-group flex items-center">
														<input type="password" class="form-control form-custom-input" name="password" id="password" placeholder="Enter password">
														<div class="input-group-text ml-1">
															<button type="button" data-toggle="#password" class="btn btn-sm toggle-password btn-info-small"><i class="fa fa-fw fa-eye"></i></button>
														</div>
													</div>
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="phone_number" class="form-label form-custom-label">Phone Number</label>
													<input type="tel" class="form-control form-custom-input" name="phone_number" id="phone_number" maxlength="30" placeholder="Enter phone number" value="{{ $staff->phone_number }}" data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#phone_number')">
												</div>

												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="status" class="form-label form-custom-label">Status</label>
													<select class="form-select form-custom-input" name="status" id="status">
														<option {{($staff->status=='Active'?'selected':'')}}>Active</option>
														<option {{($staff->status=='Inactive'?'selected':'')}}>Inactive</option>
													</select>
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label class="form-label form-custom-label">Department <req>*</req></label>
													<select name="department_id" id="department_id" class="form-select form-custom-input" required>
														<option disabled selected>Please select</option>
														@foreach($departments as $department)
														<option value="{{ $department->id }}" @if(!empty($staff)) {{ ($department->id == $staff->department_id) ? 'selected' : '' }} @endif>{{ $department->name }}</option>
														@endforeach
													</select>
												</div>
												@if ($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label class="form-label form-custom-label">Roles <req>*</req></label>
													<select name="role" id="role" class="form-select form-custom-input" required>
														<option disabled selected>Please select</option>
														@foreach($roles as $role)
														<option value="{{ $role->id }}" @if(!empty($modelHasRole)) {{ ($role->id == $modelHasRole->role_id) ? 'selected' : '' }} @endif>{{ $role->display_name }}</option>
														@endforeach
													</select>
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3" id="sub_role_div">
													<label class="form-label form-custom-label">Sub Role </label>
													<select name="sub_role" id="sub_role" class="form-select form-custom-input">
														<option value="">Please select</option>
														@foreach($subRoles as $role)
														<option value="{{ $role->id }}" @if(!empty($tourGuideRole)) {{ ($role->id == $tourGuideRole->role_id) ? 'selected' : '' }} @endif>{{ $role->display_name }}</option>
														@endforeach
													</select>
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="capacity" class="form-label form-custom-label">Capacity </label>
													<input type="number" class="form-control form-custom-input" name="capacity" id="capacity" value="{{ $staff->capacity }}" placeholder="Enter capacity">
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3" id="role_type_div">
													<label class="form-label form-custom-label">Role Type</label>
													<select name="role_type_id" id="role_type_id" class="form-select form-custom-input">
														<option value="">Please select</option>
														@foreach($roleTypes as $roleType)
														<option value="{{ $roleType->id }}" {{ ($roleType->id == $staff->role_type_id) ? 'selected' : '' }}>{{ $roleType->display_name }}</option>
														@endforeach
													</select>
												</div>
												<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
													<label for="phone_number" class="form-label">Tip Distribution Percentage (%)</label>
													<input type="number" class="form-control form-custom-input" name="tip_distribution_percentage" id="tip_distribution_percentage" min="0" max="100" placeholder="Enter Tip Distribution Percentage" value="{{ $staff->tip_distribution_percentage }}">
												</div>
												@endif
											</div>
											<div>
												<div>
													<label hidden class="label-permissions form-label form-custom-label">Permissions <req>*</req></label>
												</div>
												<div class="row permission-role-wise">

												</div>
											</div>

											<div class="mt-4 flex justify-end"><button class="btn btn-main btn-primary-small">Update</button></div>
										</form>
									</div>{{-- /.card-body --}}
								</div>{{-- /#tab-general.tab-pane --}}
							</div> {{-- /.tag-content --}}
						</div>{{-- /.card --}}
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-custom')
<style>
	.div-chk-roles input[type=checkbox] {
		position: relative;
		top: 2px;
		margin-right: 6px;
	}
</style>
@endsection

@section('js-custom')
<script>
	var updateRoleUrl = "{{ route($routePrefix . '.staff.update-role', [ 'id' => $staff->id ]) }}";
</script>

<script src="{{ asset('js/user-common/tour-operator-staff-add-edit.js') }}"></script>
<script src="{{ asset('js/utils/password-strength.js') }}"></script>
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script>
	$(document).ready(function() {
		$("#sub_role_div").hide();
		var roleCount = "{{ $roleCount }}"

		if (roleCount == 2) {
			$("#sub_role_div").show();
		}
		// on edit only validate the password if the user interacts with the field 
		$(document).on('click', '#password', () => {
			// note this function is added from js/utils/password-strength.js
			if (!window.hasOwnProperty("_PasswordStrength")) {
				window._PasswordStrength = new PasswordStrength();
			}
		});

	});
</script>
<script>
	$('#role').change(function() {
		var id = $(this).val();
		var userId = $('#id').val();
		$.ajax({
			type: "GET",
			url: "{{route($routePrefix . '.user-permission')}}",
			data: {
				id: id,
				userId: userId
			},
			success: function(data) {

				if (data.html.length > 0) {
					$('.label-permissions').attr('hidden', false);
					$('.permission-role-wise').html(data.html);
				} else {
					$('.label-permissions').attr('hidden', true);
					$('.permission-role-wise').html('');
				}

				$("#sub_role_div").hide();
				$('#sub_role').prop('selectedIndex', 0);
				if (data.role.name != "tour-guide") {
					$("#sub_role_div").show();
				}

			}
		});
	});

	$(document).ready(function() {
		var roleId = ($("#role").val());
		var userId = $('#id').val();
		$.ajax({
			type: "GET",
			url: "{{route($routePrefix . '.user-permission-edit')}}",
			data: {
				roleId: roleId,
				userId: userId
			},
			success: function(data) {
				if (data.html.length > 0) {
					$('.label-permissions').attr('hidden', false);
					$('.permission-role-wise').html(data.html);
				} else {
					$('.label-permissions').attr('hidden', true);
					$('.permission-role-wise').html('');
				}

				allPermissions.editModuleInitPermission();
			}
		});
	});
</script>
@endsection