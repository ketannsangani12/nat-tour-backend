@extends('layouts.default-2')

@section('main-content')
    <div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
        <div class="w-full container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-full max-w-full px-3">
                    <div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full lg:w-1/3 max-w-full px-3">
                                <div class="content-title">
                                    <h2 class="section-title">Dynamic Pricing</h2>
                                </div>
                            </div>
                            <div class="w-full lg:w-2/3 max-w-full px-3 lg:text-right">
                                <span class="inline-block">
                                    <div class="sub-nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-x-auto justify-center lg:justify-end mb-4">
                                        <a class="tabs-custom-link !px-4 active" href="{{ route(routePrefix() . '.package-rate.index') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> General Rates
                                        </a>
                                        <a class="tabs-custom-link !px-4" href="{{ route(routePrefix() . '.package-rate.custom-date-rate') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> Custom Date Rates
                                        </a>
                                        <a class="tabs-custom-link !px-4" href="{{ route(routePrefix() . '.package-rate.global-rate') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> Global Rates
                                        </a>
                                    </div>
                                </span>
                            </div>
                        </div>

                        <div class="main-content">
                            @if($errors->any())
                                @include('layouts._errors')
                            @endif

                            @if(session('message'))
                                @include('layouts._message')
                            @endif

                            <div class="flex flex-wrap -mx-3 package-rate-filter-wrap">
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-2">
                                    <label class="form-label form-custom-label">Scheduling Year</label>
                                    <select class="form-select select2 form-custom-input" id="general-scheduling-year">
                                        <option value="">Select Scheduling Year</option>
                                        @for($i = date('Y') - 1; $i < date('Y') + 7; $i++)
                                            <option {{ (request()->year == $i || (!request()->has('year') && $i == date('Y'))) ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-2">
                                    <label class="form-label form-custom-label">Packages</label>
                                    <select class="form-select select2 form-custom-input" id="general-packages">
                                        <option value="">Select Packages</option>
                                        @foreach($packages as $package)
                                            <option value="{{ $package->id }}">{{ $package->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-2">
                                    <label class="form-label form-custom-label">Affiliates</label>
                                    <select class="form-select select2 form-custom-input" id="general-affiliates">
                                        <option value="">Select Affiliates</option>
                                        @foreach($affiliates as $affiliate)
                                            <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3 mb-2 filter-general-rate-group">
                                    <label class="form-label form-custom-label">&nbsp;</label>
                                    <div class="flex items-center">
                                        <button type="button" id="btn-filter-general-rate-group" class="btn btn-primary btn-filter-general-rate-group btn-primary-small">Filter</button>
                                        <button type="button" id="btn-clear-general-rate-group" class="btn btn-warning ml-2 btn-clear-general-rate-group btn-warning-small">Clear</button>
                                    </div>
                                </div>
                            </div>

                            <hr class="general-rate-group-separator">

                            <div class="flex flex-wrap -mx-3 mt-4">
                                <div class="w-full max-w-full px-3 mb-3">
                                    <table width="100%" id="table-general-rate-group">
                                        <tbody id="tbody-pckg-rate-grps"></tbody>
                                    </table>

                                    <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                        <button type="button" id="add-rate-group-btn" class="btn btn-info btn-primary-small">Add Rate Group</button>
                                        @if(routePrefix() === 'admin.tour-operator')
                                            <button type="button" id="copy-rate-group-btn" class="btn btn-secondary-small">Copy to Next Year</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css-libraries')
    <link rel="stylesheet" type="text/css" href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('plugins/daterangepicker-3.1/daterangepicker-latest.css') }}">

    <!-- <link rel="stylesheet" type="text/css" href="{{ asset('css/user-common/package-rates/style.css') }}"> -->
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

    <script src="{{ asset('js/user-common/package-rates/generalRates.js') }}"></script>
@endsection

@section('js-custom')
    <script>
        $(document).ready(() => {
            $(".select2").select2();

            const getPackageRatesUrl = "{{ route( routePrefix() . '.package-rate.get-package-rates') }}";
            const savePackageRatesUrl = "{{ route( routePrefix() . '.package-rate.save-package-rates') }}";
            const deletePackageRatesUrl = "{{ route( routePrefix() . '.package-rate.delete-package-rates') }}";

            // Instantiate the tax data for the js class
            const taxPercent = "{{ $taxPercent->value ?? 0 }}";
            const taxPercentType = "{{ $taxPercent->value_type ?? '' }}";
            const cashPriceEnabled = "{{ $cashPriceEnabled }}";

            // Instantiate the General Rates class
            const generalRates = new GeneralRates(getPackageRatesUrl, savePackageRatesUrl, deletePackageRatesUrl, taxPercent, taxPercentType, cashPriceEnabled);
        });
    </script>
@endsection
