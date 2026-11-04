@extends('layouts.default-6')

@section('main-content')
<!-- Main Content -->
<div class="container my-4">
    <div class="row justify-content-center">
        <div class="col-md-10 col-lg-8">


            <div class="card shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <i class="fas fa-user text-primary me-2"></i>Update Profile
                    </h5>

                    @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    <form method="post" id="updateProfileDetailsForm"
                        action="{{ route(routePrefix().'.tip-center.update-profile') }}"
                        enctype="multipart/form-data">
                        @csrf

                        <div class="mb-3">
                            <label for="profilePicture" class="form-label">Name</label>
                            <input class="form-control" name="name"
                                id="name" type="text" disabled
                                value="{{ $guide->first_name }} {{ $guide->last_name }}">

                        </div>
                        <!-- Upload -->
                        <div class="mb-3">
                            <label for="profilePicture" class="form-label">Profile Picture <req>*</req></label>
                            <input class="form-control" name="profile_picture"
                                data-allowed-ext="jpg,jpeg,jpe,png,webp,gif"
                                id="profilePicture" type="file" required>
                            <div class="invalid-feedback">Please upload a valid profile picture.</div>
                        </div>

                        <!-- Current Image Preview -->
                        <div class="mb-3 text-center">
                            @if ($guide->profile_photo_path != '' && !Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/users/'. $guide->profile_photo_path))
                            <img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/users/' . $guide->profile_photo_path) }}"
                                class="img-thumbnail rounded-circle"
                                id="profilePicturePreview"
                                style="height: 150px; width: 150px; object-fit: cover;">
                            @else
                            <img src="{{ asset('images/no-photo.png') }}"
                                class="img-thumbnail rounded-circle"
                                id="profilePicturePreview"
                                style="height: 150px; width: 150px; object-fit: cover;">
                            @endif
                        </div>

                        <!-- Submit -->
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary" id="update-profile">Update</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
@else
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
@endif
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/tip-center/guides.js') }}"></script>
<script>
    const searchGuideUrl = "{{ route(routePrefix().'.tip-center.search-guide') }}";
    const editGuideUrl = "{{ route(routePrefix().'.tip-center.edit-guide',['id' => ':id']) }}";
    $(document).ready(() => {
        const guides = new Guides({
            searchGuideUrl,
            editGuideUrl
        });
    });
</script>
@endsection