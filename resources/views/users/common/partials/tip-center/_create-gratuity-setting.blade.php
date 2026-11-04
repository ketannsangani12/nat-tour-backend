<div class="card shadow-sm" style="display: none;" id="addGratuitySetting">
    <form id="gratuitySettingForm" method="POST" action="#">
        @csrf
        <div class="card-header bg-white border-bottom">
            <h5 class="card-title mb-1">
                <i class="fas fa-cog text-primary me-2"></i>Add Gratuity Setting
            </h5>
            <p class="text-muted mb-0 small">Configure how gratuity are handled for your tour</p>
        </div>
        <div class="card-body">
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="gratuity_name">Gratuity Name</label>
                    <input type="text" class="form-control gratuity-name-input" id="gratuity-name" name="gratuity_name" placeholder="Enter Gratuity Name">
                    <div class="invalid-feedback">Please Enter Gratuity Name.</div>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-6">
                    <label class="form-label" for="tour_packages">Tour Packages</label>
                    <select class="form-select select2" id="tour-packages-create" name="tour_packages[]" multiple>
                        @foreach($tourPackages as $package)
                        <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-12">
                    <label class="form-label fw-semibold">Gratuity Type</label>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="card gratuity-option active" data-value="fixed_percentage">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Fixed Percentage</h6>
                                    <p class="card-text small text-muted">Set percentage of total</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card gratuity-option" data-value="fixed_amount">
                                <div class="card-body text-center">
                                    <h6 class="card-title">Fixed Amount</h6>
                                    <p class="card-text small text-muted">Set dollar amount</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 d-none">
                            <div class="card gratuity-option" data-value="flexible">
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
                <div class="row mb-4 percentage-gratuity-opt">
                    <div class="col-md-3">
                        <label class="form-label" for="percentages">Percentage (%)</label>
                        <input type="number" name="percentages[]" class="form-control percentages-input" min="0" max="100" placeholder="Enter Percentage">
                        <div class="invalid-feedback">Please enter a positive number.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="descriptions">Description</label>
                        <div class="d-flex align-items-start">
                            <input type="text" name="descriptions[]" class="form-control me-2 descriptions-input" placeholder="Enter Description"
                                style="width: 70%;">
                            <button type="button" class="btn btn-primary addPercentageGratuitybtn">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                        <div class="invalid-feedback">Please enter description.</div>

                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="fw-semibold mb-3">Gratuity Options</h6>
                    <div class="row g-3" id="gratuity-check-group">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input gratuity-checkbox" type="checkbox" id="pre-gratuity" name="pre_gratuity">
                                <label class="form-check-label" for="pre-gratuity">
                                    Pre-Tour Gratuity
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input gratuity-checkbox" type="checkbox" id="post-gratuity" name="post_gratuity">
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
                                <input class="form-check-input" type="checkbox" id="custom-gratuity-pre" name="custom_gratuity_pre">
                                <label class="form-check-label" for="custom-gratuity-pre">
                                    Allow Custom Amounts (Pre-Tour)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="custom-gratuity-post" name="custom_gratuity_post">
                                <label class="form-check-label" for="custom-gratuity-post">
                                    Allow Custom Amounts (Post-Tour)
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="gratuity-mandatory" name="gratuity_mandatory">
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
                            <div class="card distribution-option active" data-value="equal">
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
                            <div class="card distribution-option" data-value="custom">
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
                    <input type="number" class="form-control" name="service_provider_cut" id="service-provider-cut" min="0" max="100">
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