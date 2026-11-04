@extends('layouts.default-6')

@section('main-content')
@php
$routePrefix = routePrefix();
@endphp

<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="tab-content" id="mainTabContent">

        <!-- Gratuity Configuration -->
        <div class="tab-pane fade show active" id="configuration" role="tabpanel">
            @include('users.common.partials.tip-center._create-gratuity-setting')
         
        
        <div class="card shadow-sm" id="listGratuitySetting">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">
                            <i class="fas fa-cog text-primary me-2"></i></i>Gratuity Settings
                        </h5>
                        <p class="text-muted mb-0 small">Configure how gratuities are handled for your tours</p>
                    </div>
                    <button class="btn btn-primary" id="addGratuitySettingbtn">
                        <i class="fas fa-plus me-2"></i>Add Gratuity Setting
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="datatable">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Pre/Post</th>
                                    <th>Gratuity Type</th>
                                    <th>Distribution Type</th>
                                    <th>Packages</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                               
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


@section('css-libraries')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	@if(App::environment('local'))
	<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
	@else
	<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
	@endif
  
@endsection

@section('js-libraries')
	@if(App::environment('local'))
	<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
	<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
	<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	@endif
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
@endsection

@section('js-custom')
<script>
    var userType = "{{ session('userType')  }}";
    var routePrefixUrl = "{{ routePrefix() }}";    
    
</script>
<script src="{{ asset('js/user-common/tip-center/gratuity-settings.js') }}"></script>
<script>
    $(document).ready(() => {
        const fetchSettingsUrl = "{{ route(routePrefix().'.tip-center.settings-list') }}";
        const saveGratuitySettingUrl = "{{ route(routePrefix().'.tip-center.save-gratuity-setting') }}";
        const gratuitySettings = new GratuitySettings({ fetchSettingsUrl,saveGratuitySettingUrl, userType });
    });
</script>
<x-delete-record-js-file />
@endsection
