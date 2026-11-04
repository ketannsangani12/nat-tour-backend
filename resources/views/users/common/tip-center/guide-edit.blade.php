@extends('layouts.default-6')

@section('main-content')
@php
$routePrefix = routePrefix();
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
                            <i class="fas fa-user text-primary me-2"></i>Edit Tour Guide Information
                        </h5>
                        <p class="text-muted mb-0 small">Manage tour guides and their capacity settings</p>
                    </div>
                </div>
                <div class="card-body">
                    <nav class="nav nav-tabs tab-list" id="nav-tab" role="tablist">
                        <button type="button" class="nav-item nav-link active" id="nav-general" data-bs-toggle="tab" data-bs-target="#tab-general" role="tab" aria-controls="tab-general" aria-selected="true">General</button>
                        <button type="button" class="nav-item nav-link" id="nav-stripe-account-info" data-bs-toggle="tab" data-bs-target="#tab-stripe-account-info" role="tab" aria-controls="tab-stripe-account-info" aria-selected="true">Stripe Account Info</button>
                        <button type="button" class="nav-item nav-link" id="nav-bank-account-info" data-bs-toggle="tab" data-bs-target="#tab-bank-account-info" role="tab" aria-controls="tab-bank-account-info" aria-selected="true">Bank Account Info</button>
                    </nav>
                </div>
                <div class="tab-content">
                    <div class="tab-pane active" id="tab-general">
                        <div class="card-body">

                            <form method="post" id="updateGeneralDetailsForm">
                                @csrf
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <input type="hidden" name="tour_guide_id" id="tour_guide_id" value="{{ $tourGuideInfo->id }}">
                                        <label for="capacity" class="form-label">Capacity <req>*</req></label>
                                        <input class="form-control capacity-input" name="capacity" id="capacity" maxlength="100" type="number" value="{{ $tourGuideInfo->capacity }}" placeholder="Enter Guide Capacity">
                                        <div class="invalid-feedback">Please enter a positive number.</div>
                                    </div>

                                </div>
                                <div class="mt-3"><button type="button" class="btn btn-main btn-primary" id="update-general">Update General Details</button></div>
                            </form>
                        </div>

                    </div>

                    <div class="tab-pane" id="tab-stripe-account-info">
                        <div class="card-body">

                            <form method="post" id="updateStripeDetailsForm">
                                @csrf
                                <div class="row">

                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="stripe-account-primary-method" name="stripe_account_primary_method" {{ $tourGuideInfo->payout_primary_method == 'Connected Account' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="stripe_account_primary_method">
                                                Primary Method
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="stripe_destination_id" class="form-label">Stripe Connected Account ID <req>*</req></label>
                                        <div class="input-group">
                                            <input class="form-control toggle-encrypted-value stripe-account-input" type="password" name="stripe_account_id" id="stripe-account-id" value="{{ $tourGuideInfo->stripe_account_id }}" min="1" step="1" placeholder="Enter stripe connected account ID">
                                            <button class="btn btn-outline-secondary" type="button" id="toggleEncryptedValue">
                                                <i class="fas fa-eye" id="toggleIcon"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary d-none" type="button" id="toggleInfo" title="Get info of stripe connected account ID">
                                                <i class="fas fa-info"></i>
                                            </button>
                                        </div>
                                        <p class="d-none" id="connected_account_name" style="margin-left: 10px;margin-top: 10px;color: #198754;font-weight: bold;"></p>
                                        <div class="invalid-feedback">Please Enter Stripe Connected Account ID.</div>
                                    </div>

                                </div>
                                <div class="mt-3"><button type="button" class="btn btn-primary btn-main" id="update-stripe-details">Update</button></div>
                            </form>
                        </div>
                    </div>
                    <div class="tab-pane" id="tab-bank-account-info">
                        <div class="card-body">

                            <form method="post" id="updateBankAccountDetailsForm">
                                @csrf
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="bank_account_number" class="form-label">Bank Account Number <req>*</req></label>
                                        <input type="text" class="form-control bank-account-inputs" name="bank_account_number" id="bank-account-number" value="{{ $tourGuideInfo->bank_account_number }}" placeholder="Enter Bank Account Number">
                                        <div class="invalid-feedback">Please Enter Bank Account Number.</div>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="bank_routing_number" class="form-label">Bank Routing Number <req>*</req></label>
                                        <input type="text" class="form-control bank-account-inputs" name="bank_routing_number" id="bank-routing-number" value="{{ $tourGuideInfo->bank_routing_number }}" placeholder="Enter Bank Routing Number">
                                        <div class="invalid-feedback">Please Enter Bank Routing Number.</div>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="account_holder_name" class="form-label">Account Holder Name <req>*</req></label>
                                        <input type="text" class="form-control bank-account-inputs" name="account_holder_name" id="account-holder-name" value="{{ $tourGuideInfo->account_holder_name }}" placeholder="Enter Account Holder Name">
                                        <div class="invalid-feedback">Please Enter Account Holder Name.</div>
                                    </div>

                                    <div class="col-12 col-md-6 mb-3">
                                        <label for="account_type" class="form-label">Account Type <req>*</req></label>
                                        <select class="form-select" name="account_type" id="account-type">
                                            <option value="Savings" {{ ($tourGuideInfo->bank_account_type == 'Savings') ? 'selected' : '' }}>Savings</option>
                                            <option value="Current" {{ ($tourGuideInfo->bank_account_type == 'Current') ? 'selected' : '' }}>Current</option>
                                        </select>
                                    </div>
                                    <div class="col-12 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="bank-account-primary-method" name="bank_account_primary_method" {{ $tourGuideInfo->payout_primary_method == 'Bank Account' ? 'checked' : '' }}>
                                            <label class="form-check-label" for="bank_account_primary_method">
                                                Primary Method
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3"><button type="button" class="btn btn-main btn-primary" id="update-bank-details">Update</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    @endsection
    @section('js-custom')
    <script>
        var userType = "{{ session('userType')  }}";
        var routePrefixUrl = "{{ routePrefix() }}";
    </script>
    <script src="{{ asset('js/user-common/tip-center/guides-management.js') }}"></script>
    <script>
        $(document).ready(() => {
            const saveGeneralInfoUrl = "{{ route(routePrefix().'.tip-center.update-guide-general-info') }}";
            const saveStripeAccountInfoUrl = "{{ route(routePrefix().'.tip-center.update-guide-stripe-account-info') }}";
            const saveBankAccountInfoUrl = "{{ route(routePrefix().'.tip-center.update-guide-bank-account-info') }}";
            const guidesManagement = new GuidesManagement({
                saveGeneralInfoUrl,
                saveStripeAccountInfoUrl,
                saveBankAccountInfoUrl
            });
        });
    </script>
    <x-delete-record-js-file />
    @endsection