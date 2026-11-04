@extends('layouts.default-6')

@section('main-content')
@php
$routePrefix = routePrefix();
$bookingPrefix = bookingPrefixSetting();
@endphp
<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="tab-content" id="mainTabContent">

        <!-- Guide Management -->
        <div class="tab-pane fade show active" id="guides" role="tabpanel">
            <div class="card shadow-sm">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">
                            <i class="fas fa-user text-primary me-2"></i>Tour Guide Directory
                        </h5>
                        <p class="text-muted mb-0 small">Manage tour guides and their capacity settings</p>
                    </div>
                    <div class="input-group" style="width: 20%;">
                        <input type="text" name="search_tour_guide" id="searchTourGuideInput" class="form-control" placeholder="Search Tour Guide">
                        <button class="btn btn-outline-secondary" type="button" id="searchTourGuideBtn">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="btn btn-outline-secondary" type="button" id="resetTourGuideBtn">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                </div>
                <div class="card-body">
                    <div class="vertical-scroll-wrapper"
                        style="max-height: 700px; overflow-y: auto;">

                        <div class="d-flex flex-wrap" style="gap: 1rem;" id="guidesListDiv">
                            @foreach($guides as $guide)
                            <div class="card guide-card" style="flex: 0 0 calc(33.33% - 0.5rem);">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div class="d-flex align-items-center">
                                            <div class="guide-avatar me-3">
                                                @if ($guide->profile_photo_path != '')
                                                <div><img src="{{ Storage::disk('s3')->url('images/tour-operator/' . tenant()->id . '/users/' . $guide->profile_photo_path) }}"
                                                        class="rounded-circle shadow-sm"
                                                        style="width: 60px; height: 60px; object-fit: cover;"></div>
                                                @else
                                                <i class="fas fa-user text-white"></i>
                                                @endif
                                            </div>
                                            <div>
                                                <h6 class="mb-0">{{ $guide->name }}</h6>
                                                <small class="text-muted">{{ $guide->email }}</small><br>
                                                <small class="text-muted">{{ $guide->phone_number }}</small>
                                            </div>
                                        </div>
                                        <a href="{{ route(routePrefix().'.tip-center.edit-guide',['id' => $guide->id]) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit me-1"></i>Edit
                                        </a>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="text-muted">Capacity:</span>
                                            <span class="fw-semibold">{{ $guide->total_tourists }}/{{ $guide->capacity }} guests</span>
                                        </div>
                                        <div class="progress" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: {{ ($guide->capacity > 0) ? ($guide->total_tourists/$guide->capacity) * 100 : 0 }}%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>



                </div>

            </div>
        </div>


@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
@endif
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