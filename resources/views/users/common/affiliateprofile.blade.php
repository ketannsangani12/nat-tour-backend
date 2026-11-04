@extends('layouts.default-2')

@section('main-content')
@php
	$routePrefix = routePrefix();
	$action = "";
		 if(Route::currentRouteName() == 'affiliate.profileupdate')
			$action = 'Update';
	@endphp
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Edit Profile</h2>
					</div>

					<div class="main-content">
						<form id="affiliateProfileForm" class="form" method="post" enctype="multipart/form-data" action="{{route('affiliate.profileupdate', $affiliate->id)}}" onsubmit="return submitAffiliateProfileForm(this)" data-on-success-msg="Profile updated" data-redirect-on-success="{{ route('affiliate.booking.calendar') }}">

							@if($action == 'Update')
								@method('PUT')
							@endif

							<div class="flex flex-wrap -mx-3">

								{{-- Left: profile fields --}}
								<div class="w-full md:w-1/2 max-w-full px-3">
									<div class="mb-3">
										<label for="name" class="form-label form-custom-label">Name <req>*</req></label>
										<input class="form-control form-custom-input" name="name" id="name" maxlength="50" value="{{ $affiliate->name }}" placeholder="Enter name" onblur="validateCharacters('#name')" required>
									</div>

									<div class="mb-3">
										<label for="email" class="form-label form-custom-label">Email Address <req>*</req></label>
										<input type="email" class="form-control form-custom-input" name="email" id="email" maxlength="50" value="{{ $affiliate->email }}" placeholder="Enter email address" required>
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
											<option {{ $affiliate->status == 'Active' ? 'selected' : '' }}>Active</option>
											<option {{ $affiliate->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
										</select>
									</div>
								</div>

								{{-- Right: profile picture --}}
								<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
									<label class="form-label form-custom-label">Profile Picture</label>
									<div class="flex flex-col items-center gap-3 p-5 border border-dashed border-[#D1D5DB] rounded-lg bg-[#F9FAFB]">
										<div class="relative">
											<img id="affProfilePicPreview"
												src="{{ !empty($affiliate->profile_photo_path) ? $affiliate->profile_photo_path : asset('images/no-photo.png') }}"
												alt="Profile Picture"
												class="rounded-full object-cover border-2 border-[#E5E7EB] shadow-sm" style="width:150px;height:150px;">
										</div>
										<input type="file" name="profile_picture" id="affProfilePicInput"
											accept="image/*"
											class="form-control form-custom-input w-full"
											onchange="previewAffProfilePic(this)">
										<p class="text-xs text-gray-400 text-center m-0">Please only upload image files.</p>
										<div id="affUploadProgressWrap" style="display:none;width:100%;">
											<span style="font-size:11px;color:#374151;font-weight:600;display:block;margin-bottom:4px;">Uploading...</span>
											<div style="width:100%;background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">
												<div id="affUploadProgressBar" style="height:100%;width:0%;background:#1d4ed8;border-radius:999px;transition:width 0.2s ease;"></div>
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
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Access Levels</h2>
					</div>

					<div class="main-content">
						<div class="flex flex-wrap -mx-3">
							<div class="w-full md:w-1/2 max-w-full px-3 mb-3">
								<input type="hidden" name="id" id="id" @if(isset($id)) value="{{$id}}" @endif>
								<label class="form-label form-custom-label">Roles </label>
								<select name="role" id="role" class="form-select form-custom-input" disabled> 
									@foreach($roles as $role)
											<option value="{{ $role->id }}">{{ $role->display_name }}</option>
									@endforeach
								</select>
							</div>
						</div>
					
						<div>
							<label hidden class="label-permissions form-label form-custom-label">Permissions </label>
						</div>
						<div class="row permission-role-wise">
							
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
   	var urlAccessLevel = "{{route($routePrefix . '.package.affiliate-user-access-level')}}";

	function previewAffProfilePic(input) {
		if (input.files && input.files[0]) {
			var reader = new FileReader();
			reader.onload = function (e) {
				document.getElementById('affProfilePicPreview').src = e.target.result;
			};
			reader.readAsDataURL(input.files[0]);
		}
	}

	function submitAffiliateProfileForm(form) {
		if (!validateEmailAddressFormat()) return false;
		if (!validateCharacters('#name'))    return false;

		var $form    = $(form);
		var formData = new FormData(form);
		var hasFile  = document.getElementById('affProfilePicInput').files.length > 0;

		showLoading();

		if (hasFile) {
			$('#affUploadProgressWrap').show();
			$('#affUploadProgressBar').css('width', '0%');
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
							$('#affUploadProgressBar').css('width', pct + '%');
						}
					};
				}
				return xhr;
			},
		})
		.done(function () {
			if (hasFile) {
				$('#affUploadProgressBar').css('width', '100%');
			}
			hideLoading();
			swal($form.data('on-success-msg'), '', 'success').then(function () {
				showLoading();
				location = $form.data('redirect-on-success');
			});
		})
		.fail(function (xhr) {
			if (hasFile) $('#affUploadProgressWrap').hide();
			hideLoading();
			var msg = xhr.responseJSON && xhr.responseJSON.message
				? xhr.responseJSON.message
				: 'Unable to update profile.';
			swal(msg, '', 'error');
		});

		return false;
	}
</script>
<script src="{{ asset('js/user-common/affiliate-add-edit.js') }}"></script>
<script src="{{ asset('js/user-common/all-permissions.js') }}"></script>
<script>
	allPermissions.callAccessLevel();
</script>
@endsection

@section('js-libraries')
	<script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>
@endsection


@section('js-custom')