@extends('layouts.default-2')

@section('main-content')
	@php
	$routePrefix = routePrefix();
	$action = "";
		 if(Route::currentRouteName() == 'tour-operator.operatorprofile')
			$action = 'Update';
	@endphp

<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Edit Profile</h2>
					</div>

					<div class="main-content">
						<form id="operatorProfileForm" class="form" method="post" enctype="multipart/form-data" action="{{ route('tour-operator.operatorprofile', $staff->id) }}" onsubmit="return submitOperatorProfileForm(this)" data-on-success-msg="Profile updated" data-redirect-on-success="{{ route('tour-operator.booking.calendar') }}">

							@if($action == 'Update')
								@method('PUT')
							@endif

							<div class="flex flex-wrap -mx-3">

								{{-- Left: profile fields --}}
								<div class="w-full md:w-1/2 max-w-full px-3">
									<div class="mb-3">
										<label for="first_name" class="form-label form-custom-label">Name <req>*</req></label>
										<input class="form-control form-custom-input" name="first_name" id="first_name" maxlength="50" value="{{ $staff->first_name }}" placeholder="Enter name" onblur="validateCharacters('#first_name')" required>
									</div>

									<div class="mb-3">
										<label for="email" class="form-label form-custom-label">Email Address <req>*</req></label>
										<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50" value="{{ $staff->email }}" placeholder="Enter email address" required>
									</div>

									<div class="mb-3">
										<label for="password" class="form-label form-custom-label">Password @if($action == 'Add') <req>*</req> @endif</label>
										<div class="input-group flex items-center">
											<input type="password" class="form-control form-custom-input" name="password" id="password" placeholder="Enter password" @if($action == 'Add') required @endif>
											<div class="input-group-text ml-1">
												<button type="button" class="btn btn-sm toggle-password btn-info-small" data-toggle="#password"><i class="fa fa-fw fa-eye"></i></button>
											</div>
										</div>
									</div>

									<div class="mb-3">
										<label for="status" class="form-label form-custom-label">Status</label>
										<select class="form-select form-custom-input" name="status" id="status" @if($action == 'Update' && $id == auth(session('guard'))->id()) disabled @endif>
											<option {{ $staff->status == 'Active' ? 'selected' : '' }}>Active</option>
											<option {{ $staff->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
										</select>
									</div>

									@if($modelHasRole->name == "manager")
										<div class="mb-3">
											<label for="manager_id" class="form-label form-custom-label">Manager Id <req>*</req></label>
											<span style="color:red;font-size:12px"> Minimum 4 and maximum 12 digit number is allowed</span>
											<input type="password" class="form-control form-custom-input" value="{{ $staff->manager_id }}" name="manager_id" id="manager_id" placeholder="Enter manager id" pattern="[0-9\s]{4,12}">
										</div>
										<div class="mb-3">
											<label for="manager_pin" class="form-label form-custom-label">Manager Pin <req>*</req></label>
											<span style="color:red;font-size:12px">Minimum 4 and maximum 12 digit number is allowed</span>
											<input type="password" class="form-control form-custom-input" value="{{ $staff->manager_pin }}" name="manager_pin" id="manager_pin" placeholder="Enter manager pin" pattern="[0-9\s]{4,12}">
										</div>
									@endif
								</div>

								{{-- Right: profile picture --}}
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label class="form-label form-custom-label">Profile Picture</label>
									<div class="flex flex-col items-center gap-3 p-5 border border-dashed border-[#D1D5DB] rounded-lg bg-[#F9FAFB]">
										<div class="relative">
											<img id="opProfilePicPreview"
												src="{{ !empty($staff->profile_photo_path) ? $staff->profile_photo_path : asset('images/no-photo.png') }}"
												alt="Profile Picture"
												class="rounded-full object-cover border-2 border-[#E5E7EB] shadow-sm" style="width:150px;height:150px;">
										</div>
										<input type="file" name="profile_picture" id="opProfilePicInput"
											accept="image/*"
											class="form-control form-custom-input w-full"
											onchange="previewOpProfilePic(this)">
										<p class="text-xs text-gray-400 text-center m-0">Please only upload image files.</p>
										<div id="opUploadProgressWrap" style="display:none;width:100%;">
											<span style="font-size:11px;color:#374151;font-weight:600;display:block;margin-bottom:4px;">Uploading...</span>
											<div style="width:100%;background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">
												<div id="opUploadProgressBar" style="height:100%;width:0%;background:#1d4ed8;border-radius:999px;transition:width 0.2s ease;"></div>
											</div>
										</div>
									</div>
								</div>

							</div>

							<div class="mt-4 text-end"><button class="btn btn-main btn-primary-small">Update</button></div>
						</form>
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}

<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Access Levels</h2>
					</div>

					<div class="main-content">
						<div class="flex flex-wrap -mx-3">
							<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
								<label class="form-label form-custom-label">Roles </label>
								<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
								<select name="role" id="role" class="form-select form-custom-input" disabled> 
									@foreach($roles as $role)
											<option value="{{ $role->id }}" @if(!empty($modelHasRole)) {{ ($role->id == $modelHasRole->role_id) ? 'selected' : '' }} @endif>{{ $role->display_name }}</option>
									@endforeach
								</select>
							</div>
							@if(!empty($modelHasSubRole))
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3" id="sub_role_div">
									<label class="form-label form-custom-label">Sub Role </label>
									<select name="sub_role" id="sub_role" class="form-select form-custom-input" disabled> 
										<option value="">Please select</option>
										@foreach($subRoles as $role)
												<option value="{{ $role->id }}" @if(!empty($modelHasSubRole)) {{ ($role->id == $modelHasSubRole->role_id) ? 'selected' : '' }} @endif>{{ $role->display_name }}</option>
										@endforeach
									</select>
								</div>
							@endif	
						</div>
						<div>
							<label hidden class="label-permissions form-label form-custom-label">Permissions </label>
						</div>
						<div class="permission-role-wise">
							
						</div>
						
					</div> {{-- /.main-content --}}
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('js-custom')
<script>
	var userId = $('#id').val();
   	var urlAccessLevel = "{{route($routePrefix . '.user-access-level')}}";

	function previewOpProfilePic(input) {
		if (input.files && input.files[0]) {
			var reader = new FileReader();
			reader.onload = function (e) {
				document.getElementById('opProfilePicPreview').src = e.target.result;
			};
			reader.readAsDataURL(input.files[0]);
		}
	}

	function submitOperatorProfileForm(form) {
		if (!validateEmailAddressFormat()) return false;
		if (!validateCharacters('#first_name')) return false;

		var $form    = $(form);
		var formData = new FormData(form);
		var hasFile  = document.getElementById('opProfilePicInput').files.length > 0;

		showLoading();

		if (hasFile) {
			$('#opUploadProgressWrap').show();
			$('#opUploadProgressBar').css('width', '0%');
		}

		$.ajax({
			headers:     { Accept: 'application/json' },
			type:        'POST',
			url:         form.action,
			data:        formData,
			processData: false,
			contentType: false,
			xhr: function () {
				var xhr = new XMLHttpRequest();
				if (hasFile) {
					xhr.upload.onprogress = function (e) {
						if (e.lengthComputable) {
							var pct = Math.min(Math.round((e.loaded / e.total) * 100), 90);
							$('#opUploadProgressBar').css('width', pct + '%');
						}
					};
				}
				return xhr;
			},
		})
		.done(function () {
			if (hasFile) {
				$('#opUploadProgressBar').css('width', '100%');
			}
			hideLoading();
			swal($form.data('on-success-msg'), '', 'success').then(function () {
				showLoading();
				location = $form.data('redirect-on-success');
			});
		})
		.fail(function (xhr) {
			if (hasFile) $('#opUploadProgressWrap').hide();
			hideLoading();
			var msg = xhr.responseJSON && xhr.responseJSON.message
				? xhr.responseJSON.message
				: 'Unable to update profile.';
			swal(msg, '', 'error');
		});

		return false;
	}
</script>
<script src="{{ asset('js/admin/admin-add-edit.js') }}"></script>
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script>
	allPermissions.callAccessLevel();
</script>
@endsection

@section('js-libraries')
	<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
@endsection

@section('js-custom')