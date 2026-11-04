@extends('layouts.default-2')

@section('main-content')
    <div class="page-content">
        <div class="content-title">
            <h2><i class="fas fa-layer-group title-icon"></i> Tour Rates</h2>
        </div>

        <div class="sub-nav">
            <a href="{{ route(prefixedRouteName('package-rate.index')) }}">
                <i class="fas fa-link sub-nav-icon"></i> General Rates
            </a>
            <span class="sub-nav-sep">|</span>
            <a class="active" href="{{ route(prefixedRouteName('package-rate.custom-date-rate')) }}">
                <i class="fas fa-link sub-nav-icon"></i> Custom Date Rates
            </a>
            <a href="{{ route(prefixedRouteName('tour-operator.setting.edit')) }}?tab=standard-rate-dates" class="float-right global-rate-link">
                <i class="fas fa-link sub-nav-icon"></i> Standard Rate Dates
            </a>
        </div>

        <div class="main-content">
            @if($errors->any())
                @include('layouts._errors')
            @endif

            @if(session('message'))
                @include('layouts._message')
            @endif

            <div class="row">
                <div class="col-12 col-md-3 mb-3">
                    <label class="form-label">Scheduling Year</label>
                    <select class="form-select select2" id="general-scheduling-year">
                        <option value="">Select Scheduling Year</option>
                        @for($i = date('Y'); $i < date('Y') + 7; $i++)
                            <option {{ request()->year == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-12 col-md-3 mb-3">
                    <label class="form-label">Custom Dates</label>
                    <select class="form-select select2" id="general-custom-dates">
                        <option value="">Select Custom Date</option>
                        @foreach($standardRateDates as $standardRateDate)
                            <option 
                                data-year="{{ date('Y', strtotime($standardRateDate->date_range_start)) }}" 
                                value="{{ $standardRateDate->id }}">
                                {{ $standardRateDate->custom_date_name . ' (' . date('Y', strtotime($standardRateDate->date_range_start)) . ')' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 mb-3">
                    <label class="form-label">Packages</label>
                    <select class="form-select select2" id="general-packages">
                        <option value="">Select Packages</option>
                        @foreach($packages as $package)
                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 mb-3 d-none">
                    <label class="form-label">Affiliates</label>
                    <select class="form-select select2" id="general-affiliates">
                        <option value="">Select Affiliates</option>
                        @foreach($affiliates as $affiliate)
                            <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <div class="mb-3 d-flex justify-content-end filter-general-rate-group">
                    <button type="button" id="btn-filter-general-rate-group" class="btn btn-primary btn-filter-general-rate-group me-2">Filter</button>
                    <button type="button" id="btn-clear-general-rate-group" class="btn btn-warning btn-clear-general-rate-group">Clear</button>
                </div>
            </div>

            <hr class="general-rate-group-separator">

            <div class="row mt-4">
                <div class="col-12 mb-3">
                    <table width="100%" id="table-general-rate-group">
                        <tbody id="tbody-pckg-rate-grps"></tbody>
                    </table>

                    <div class="mt-2">
                        <button type="button" id="add-rate-group-btn" class="btn btn-info">Add Rate Group</button>
                    </div>
                </div>
            </div>

            <hr class="general-rate-group-separator" id="slots-rate-group-separator">

            <div id="slots-section">
                <!-- Title -->
                <h4>Map With Time Slots</h4>

                <!-- Slots container where the time slots will be dynamically inserted -->
                <div class="slots-container" id="slots-container"></div>
            </div>
        </div>
    </div>
@endsection

@section('css-libraries')
    <link rel="stylesheet" type="text/css" href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('plugins/daterangepicker-3.1/daterangepicker-latest.css') }}">

    <link rel="stylesheet" type="text/css" href="{{ asset('css/user-common/package-rates/style.css') }}">
@endsection

@section('css-custom')
    <style>
        /* */
    </style>
@endsection

@section('js-libraries')
    <script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
    <script src="{{ asset('plugins/daterangepicker-3.1/daterangepicker.min.js') }}"></script>
    <script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>

    <x-delete-record-js-file />

    <script src="{{ asset('js/user-common/package-rates/custom-date-rates.js') }}"></script>
@endsection

@section('js-custom')
    <script>
        $(document).ready(() => {
            const getPackageRatesUrl = "{{ route( prefixedRouteName('package-rate.get-package-rates')) }}";
            const savePackageRatesUrl = "{{ route( prefixedRouteName('package-rate.save-package-rates')) }}";
            const deletePackageRatesUrl = "{{ route( prefixedRouteName('package-rate.delete-package-rates')) }}";
            const manageSlotAssignmentUrl = "{{ route( prefixedRouteName('package-rate.manage-slot-assignment')) }}";

            // Instantiate the General Rates class
            const generalRates = new GeneralRates(getPackageRatesUrl, savePackageRatesUrl, deletePackageRatesUrl, manageSlotAssignmentUrl);
        });
    </script>
@endsection
