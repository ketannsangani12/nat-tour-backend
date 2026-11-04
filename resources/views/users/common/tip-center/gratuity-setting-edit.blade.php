@extends('layouts.default-6')

@section('main-content')
@php
$routePrefix = routePrefix();
@endphp

<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="tab-content" id="mainTabContent">

        <div class="tab-pane fade show active" id="configuration" role="tabpanel">
            <div class="card shadow-sm">
                <form id="gratuitySettingForm" method="POST" action="#">
                    @csrf
                    <input type="hidden" name="gratuity_setting_id" id="gratuity-setting-id" value="{{ $gratuitySetting->id }}">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="card-title mb-1">
                            <i class="fas fa-cog text-primary me-2"></i>Edit Gratuity Setting
                        </h5>
                        <p class="text-muted mb-0 small">Update gratuity setting for your tour</p>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="gratuity_name">Gratuity Name</label>
                                <input type="text" class="form-control gratuity-name-input" value="{{ $gratuitySetting->gratuity_name }}" id="gratuity-name" name="gratuity_name" placeholder="Enter Gratuity Name">
                                <div class="invalid-feedback">Please Enter Gratuity Name.</div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="tour_packages">Tour Packages</label>
                                <select class="form-select" id="tour-packages" name="tour_packages[]" multiple>
                                    @foreach($tourPackages as $package)
                                    <option value="{{ $package->id }}" {{ in_array($package->id, $assignedPackages) ? 'selected' : '' }}>{{ $package->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Gratuity Type</label>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="card gratuity-option {{ $gratuitySetting->gratuity_type == 'fixed_percentage' ? 'active' : '' }}" data-value="fixed_percentage">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Fixed Percentage</h6>
                                                <p class="card-text small text-muted">Set percentage of total</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card gratuity-option {{ $gratuitySetting->gratuity_type == 'fixed_amount' ? 'active' : '' }}" data-value="fixed_amount">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Fixed Amount</h6>
                                                <p class="card-text small text-muted">Set dollar amount</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 d-none">
                                        <div class="card gratuity-option {{ $gratuitySetting->gratuity_type == 'flexible' ? 'active' : '' }}" data-value="flexible">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Flexible</h6>
                                                <p class="card-text small text-muted">Customer chooses</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="gratuityValueSection">
                            @php
                            $gratuityValues = json_decode($gratuitySetting->gratuity_rules, true);
                            $i = 0;
                            @endphp
                            @if($gratuitySetting->gratuity_type == 'fixed_percentage')
                            @foreach($gratuityValues as $value)
                            <div class="row mb-4 percentage-gratuity-opt">
                                <div class="col-md-3">
                                    <label class="form-label" for="percentages">Percentage (%)</label>
                                    <input type="number" name="percentages[]" value="{{ $value['percentage'] }}" class="form-control percentages-input" min="0" max="100" placeholder="Enter Percentage">
                                    <div class="invalid-feedback">Please enter a positive number.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="descriptions">Description</label>
                                    <div class="d-flex align-items-start">
                                        <input type="text" name="descriptions[]" value="{{ $value['description'] }}" class="form-control me-2 descriptions-input" placeholder="Enter Description" style="width: 70%;">
                                        @if($i > 0)
                                        <button type="button" class="btn btn-danger removePercentageGratuitybtn">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                        @else
                                        <button type="button" class="btn btn-primary addPercentageGratuitybtn">
                                            <i class="fas fa-plus me-1"></i>Add
                                        </button>
                                        @endif
                                    </div>
                                    <div class="invalid-feedback">Please enter description.</div>

                                </div>
                            </div>
                            @php $i++; @endphp
                            @endforeach
                            @elseif($gratuitySetting->gratuity_type == 'fixed_amount')
                            @foreach($gratuityValues as $value)
                            <div class="row mb-4 fixed-amount-gratuity-opt">
                                <div class="col-md-3">
                                    <label class="form-label">Amount ($)</label>
                                    <input type="number" name="amounts[]" class="form-control amounts-input" value="{{ $value['amount'] }}" min="0" max="100" placeholder="Enter Amount">
                                    <div class="invalid-feedback">Please enter a positive number.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Description</label>
                                    <div class="d-flex align-items-start">
                                        <input type="text" name="descriptions" class="form-control me-2 descriptions-input" value="{{ $value['description'] }}" placeholder="Enter Description"
                                            style="width: 70%;">
                                        @if($i > 0)
                                        <button type="button" class="btn btn-danger removeFixedAmountGratuitybtn">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                        @else
                                        <button type="button" class="btn btn-primary addFixedAmountGratuitybtn">
                                            <i class="fas fa-plus me-1"></i>Add
                                        </button>
                                        @endif
                                    </div>
                                    <div class="invalid-feedback">Please enter description.</div>
                                </div>
                            </div>
                            @php $i++; @endphp
                            @endforeach

                            @elseif($gratuitySetting->gratuity_type == 'flexible')
                            <div class="row mb-4">
                                <div class="col-md-4">
                                    <label class="form-label">Minimum ($)</label>
                                    <input type="number" class="form-control flexible-gratuity-input" value="{{ $gratuitySetting->min_gratuity }}" name="min_gratuity" id="min-gratuity" min="0" step="0.01">
                                    <div class="invalid-feedback">Please enter a positive number.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Maximum ($)</label>
                                    <input type="number" class="form-control flexible-gratuity-input" value="{{ $gratuitySetting->max_gratuity }}" name="max_gratuity" id="max-gratuity" min="0" step="0.01">
                                    <div class="invalid-feedback">Please enter a positive number.</div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Default ($)</label>
                                    <input type="number" class="form-control flexible-gratuity-input" value="{{ $gratuitySetting->default_gratuity }}" name="default_gratuity" id="default-gratuity" min="0" step="0.01">
                                    <div class="invalid-feedback">Please enter a positive number.</div>
                                </div>
                            </div>
                            @endif
                        </div>

                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-semibold mb-3">Gratuity Options</h6>
                                <div class="row g-3" id="gratuity-check-group">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input gratuity-checkbox" type="checkbox" id="pre-gratuity" name="pre_gratuity" {{ ($gratuitySetting->pre_gratuity_enabled) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="pre-gratuity">
                                                Pre-Tour Gratuity
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input gratuity-checkbox" type="checkbox" id="post-gratuity" name="post_gratuity" {{ ($gratuitySetting->post_gratuity_enabled) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="post-gratuity">
                                                Post-Tour Gratuity
                                            </label>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback" style="display:none;">
                                        Please select at least one option.
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="custom-gratuity-pre" name="custom_gratuity_pre" {{ ($gratuitySetting->allow_pre_custom_gratuity) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="custom-gratuity-pre">
                                                Allow Custom Amounts (Pre-Tour)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="custom-gratuity-post" name="custom_gratuity_post" {{ ($gratuitySetting->allow_post_custom_gratuity) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="custom-gratuity-post">
                                                Allow Custom Amounts (Post-Tour)
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="gratuity-mandatory" name="gratuity_mandatory" {{ ($gratuitySetting->is_mandatory) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="gratuity-mandatory">
                                                Mandatory
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="fw-semibold mb-3">Distribution Settings</h6>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <div class="card distribution-option {{ $gratuitySetting->distribution_type == 'equal' ? 'active' : '' }}" data-value="equal">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Equal Split</h6>
                                                <p class="card-text small text-muted">Split evenly among guides</p>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- <div class="col-md-4">
                                        <div class="card distribution-option" data-value="percentage_based">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Percentage Based</h6>
                                                <p class="card-text small text-muted">Based on predefined %</p>
                                            </div>
                                        </div>
                                    </div> -->
                                    <div class="col-md-4">
                                        <div class="card distribution-option {{ $gratuitySetting->distribution_type == 'custom' ? 'active' : '' }}" data-value="custom">
                                            <div class="card-body text-center">
                                                <h6 class="card-title">Custom Split</h6>
                                                <p class="card-text small text-muted">Manual allocation</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-4 d-none">
                            <div class="col-md-6">
                                <label class="form-label">Service Provider Cut (%)</label>
                                <input type="number" class="form-control" name="service_provider_cut" id="service-provider-cut" value="{{ $gratuitySetting->service_provider_cut }}" min="0" max="100">
                                <div class="form-text">Percentage of gratuity retained by the platform</div>
                            </div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-md-2">
                                <button type="button" id="save-gratuity-setting" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endsection
    @section('css-libraries')
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
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
            const saveGratuitySettingUrl = "{{ route(routePrefix().'.tip-center.save-gratuity-setting') }}";
            const gratuitySettings = new GratuitySettings({
                saveGratuitySettingUrl,
                userType
            });
        });
    </script>
    <x-delete-record-js-file />
    @endsection