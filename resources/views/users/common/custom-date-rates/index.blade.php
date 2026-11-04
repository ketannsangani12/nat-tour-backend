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
                                <a class="btn-warning-small add-custom-date-rate mr-5" role="button">
                                    <i class="fas fa-plus sub-nav-icon mr-2"></i> <span>Add New</span>
                                </a>
                                <span class="inline-block">
                                    <div class="sub-nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-x-auto justify-center lg:justify-end mb-4">
                                        <a class="tabs-custom-link !px-4" href="{{ route(routePrefix() . '.package-rate.index') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> General Rates
                                        </a>
                                        <a class="tabs-custom-link !px-4 active" href="{{ route(routePrefix() . '.package-rate.custom-date-rate') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> Custom Date Rates
                                        </a>
                                        <a class="tabs-custom-link !px-4" href="{{ route(routePrefix() . '.package-rate.global-rate') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> Global Rates
                                        </a>
                                    </div>
                                </span>                                
                            </div>
                        </div>

                        <!-- Table Content -->
                        <div id="table-container">
                            <h5 class="font-normal text-gray-600 text-base mb-2"><i class="fa fa-compass" style="margin-right: 5px"></i>Custom Date Rates</h5>
                            <div class="main-content mb-6" style="padding-top: 0%">
                                <div class="overflow-x-auto">
                                    <table id="table-surcharge" class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse tbl-price" style="text-align: left;">
                                        <thead>
                                            <tr>
                                                <th class="th-custom-css">Custom Date Name</th>
                                                <th class="th-custom-css">Package Name</th>
                                                <th class="th-custom-css">Affiliate Name</th>
                                                <th class="th-custom-css">Start Date</th>
                                                <th class="th-custom-css">End Date</th>
                                                <th class="th-custom-css">Apply On Slots</th>
                                                <th class="th-custom-css">Order</th>
                                                <th class="th-custom-css">Author</th>
                                                <th class="th-custom-css">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody style="vertical-align: middle; width: 200px">
                                            @if ($standardRateDates->count())
                                                @foreach ($standardRateDates as $standardRateDate)
                                                <tr>
                                                    <td class="td-custom-css">{{ $standardRateDate->custom_date_name }}</td>
                                                    <td class="td-custom-css">{{ $standardRateDate->tourPackage->name ?? '' }}</td>
                                                    <td class="td-custom-css">{{ $standardRateDate->affiliate->name ?? '-' }}</td>
                                                    <td class="td-custom-css">{{ date('m/d/Y', strtotime($standardRateDate->date_range_start)) }}</td>
                                                    <td class="td-custom-css">{{ date('m/d/Y', strtotime($standardRateDate->date_range_end)) }}</td>
                                                    <td class="td-custom-css">{{ $standardRateDate->apply_on_slots ? 'Yes' : 'No' }}</td>
                                                    <td class="td-custom-css">{{ $standardRateDate->priority }}</td>
                                                    <td class="td-custom-css">{{ $standardRateDate->created_by_with_name }}</td>
                                                    <td class="td-custom-css whitespace-nowrap">
                                                        <!-- Edit Button -->
                                                        <button type="button" class="btn btn-sm btn-edit edit-custom-date-rate mr-2 btn-primary-small" 
                                                            data-id="{{ $standardRateDate->id }}" 
                                                            data-package="{{ $standardRateDate->tour_package_id }}" 
                                                            data-affiliate="{{ $standardRateDate->affiliate_id }}" 
                                                            data-name="{{ $standardRateDate->custom_date_name }}" 
                                                            data-start="{{ $standardRateDate->date_range_start }}" 
                                                            data-end="{{ $standardRateDate->date_range_end }}" 
                                                            data-apply="{{ $standardRateDate->apply_on_slots }}">
                                                            <i class="far fa-edit mr-2"></i> Edit
                                                        </button>

                                                        <!-- Delete Button -->
                                                        <button type="button" class="btn btn-sm btn-delete delete-custom-date-rate btn-danger-small" 
                                                            data-id="{{ $standardRateDate->id }}">
                                                            <i class="far fa-trash-alt mr-2"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td class="td-custom-css" colspan="9" align="center">
                                                        No custom date rates found.
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <h5 class="font-normal text-gray-600 text-base mb-2"><i class="fa fa-check-circle" style="margin-right: 5px"></i>Past Custom Date Rates</h5>
                            <div id="table-container" class="main-content" style="padding-top: 0%">
                                <div class="overflow-x-auto">
                                    <table id="table-surcharge" class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse tbl-price" style="text-align: left;">
                                        <thead>
                                            <tr>
                                                <th class="th-custom-css">Custom Date Name</th>
                                                <th class="th-custom-css">Package Name</th>
                                                <th class="th-custom-css">Affiliate Name</th>
                                                <th class="th-custom-css">Start Date</th>
                                                <th class="th-custom-css">End Date</th>
                                                <th class="th-custom-css">Apply On Slots</th>
                                                <th class="th-custom-css">Order</th>
                                                <th class="th-custom-css">Author</th>
                                                <th class="th-custom-css">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody style="vertical-align: middle; width: 200px">
                                            @if ($pastStandardRateDates->count())
                                                @foreach ($pastStandardRateDates as $pastStandardRateDate)
                                                <tr>
                                                    <td class="td-custom-css">{{ $pastStandardRateDate->custom_date_name }}</td>
                                                    <td class="td-custom-css">{{ $pastStandardRateDate->tourPackage->name ?? '' }}</td>
                                                    <td class="td-custom-css">{{ $pastStandardRateDate->affiliate->name ?? '-' }}</td>
                                                    <td class="td-custom-css">{{ date('m/d/Y', strtotime($pastStandardRateDate->date_range_start)) }}</td>
                                                    <td class="td-custom-css">{{ date('m/d/Y', strtotime($pastStandardRateDate->date_range_end)) }}</td>
                                                    <td class="td-custom-css">{{ $pastStandardRateDate->apply_on_slots ? 'Yes' : 'No' }}</td>
                                                    <td class="td-custom-css">{{ $pastStandardRateDate->priority }}</td>
                                                    <td class="td-custom-css">{{ $pastStandardRateDate->created_by_with_name }}</td>
                                                    <td class="td-custom-css whitespace-nowrap">
                                                        <!-- Edit Button -->
                                                        <button type="button" class="btn btn-sm btn-edit edit-custom-date-rate mr-2 btn-primary-small" 
                                                            data-id="{{ $pastStandardRateDate->id }}" 
                                                            data-package="{{ $pastStandardRateDate->tour_package_id }}" 
                                                            data-affiliate="{{ $pastStandardRateDate->affiliate_id }}" 
                                                            data-name="{{ $pastStandardRateDate->custom_date_name }}" 
                                                            data-start="{{ $pastStandardRateDate->date_range_start }}" 
                                                            data-end="{{ $pastStandardRateDate->date_range_end }}" 
                                                            data-apply="{{ $pastStandardRateDate->apply_on_slots }}">
                                                            <i class="far fa-edit mr-2"></i> Edit
                                                        </button>

                                                        <!-- Delete Button -->
                                                        <button type="button" class="btn btn-sm btn-delete delete-custom-date-rate btn-danger-small" 
                                                            data-id="{{ $pastStandardRateDate->id }}">
                                                            <i class="far fa-trash-alt mr-2"></i> Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            @else
                                                <tr>
                                                    <td class="td-custom-css" colspan="9" align="center">
                                                        No past custom date rates found.
                                                    </td>
                                                </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Form Content (Initially Hidden) -->
                        <div id="form-container" class="" style="display: none;">
                            <form id="custom-date-form" method="POST" action="#">
                                @csrf
                                <div class="">
                                    <div class="flex flex-wrap -mx-3">
                                        <!-- Custom Date ID -->
                                        <input type="hidden" id="custom_date_id">

                                        <!-- Scheduling Year -->
                                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/6 max-w-full px-3">
                                            <label for="scheduling_year" class="form-label form-custom-label fw-bold">Scheduling Year</label>
                                            <select class="form-select form-custom-input" id="scheduling_year" name="scheduling_year">
                                                <option value="" selected>Select scheduling year</option>
                                                @for($i = date('Y') - 1; $i < date('Y') + 7; $i++)
                                                    <option {{ request()->year == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                                                @endfor
                                            </select>
                                        </div>

                                        <!-- Custom Date Name -->
                                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/6 max-w-full px-3">
                                            <label for="custom_date_name" class="form-label form-custom-label fw-bold">Custom Date Name</label>
                                            <input type="text" class="form-control form-custom-input" id="custom_date_name" name="custom_date_name" placeholder="Enter custom date name" required>
                                        </div>

                                        <!-- Package Name -->
                                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/6 max-w-full px-3">
                                            <label for="package_name" class="form-label form-custom-label fw-bold">Package Name</label>
                                            <select class="form-select form-custom-input" id="package_name" name="package_name" required>
                                                <option value="" selected disabled>Select package</option>
                                                @foreach($packages as $package)
                                                    <option data-is-group-rate-enabled="{{ $package->is_group_rate_enabled }}" value="{{ $package->id }}">{{ $package->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Affiliate Name -->
                                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/6 max-w-full px-3">
                                            <label for="affiliate_name" class="form-label form-custom-label fw-bold">Affiliate Name</label>
                                            <select class="form-select form-custom-input" id="affiliate_name" name="affiliate_name">
                                                <option value="" selected>Select affiliate</option>
                                                @foreach($affiliates as $affiliate)
                                                    <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <!-- Date Range -->
                                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/6 max-w-full px-3">
                                            <label for="date_range" class="form-label form-custom-label fw-bold">Date Range</label>
                                            <input type="text" class="form-control form-custom-input" id="date_range" name="date_range" placeholder="Select date range" required>
                                        </div>

                                        <!-- Apply On Slots -->
                                        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/6 max-w-full px-3">
                                            <label for="apply_on_slots" class="form-label form-custom-label fw-bold">Apply On Slots</label>
                                            <select class="form-select form-custom-input" id="apply_on_slots" name="apply_on_slots" required>
                                                <option value="0">No</option>
                                                <option value="1">Yes</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Rate Group Section (Initially hidden) -->
                                    <div id="rate-group-section" class="mt-4" style="display: none;">
                                        <h5 class="text-secondary font-normal text-gray-600 text-base mb-2">Rate Group Management</h5>
                                        <table class="table table-bordered mt-3">
                                            <tbody id="tbody-pckg-rate-grps"></tbody>
                                        </table>
                                        <div class="mt-3 mb-2">
                                            <button type="button" id="add-rate-group-btn" class="btn btn-info btn-primary-small">Add Rate Group</button>
                                        </div>
                                    </div>

                                    <!-- Slots Section (Initially hidden) -->
                                    <div id="slots-section-container" class="mt-4" style="display: none;">
                                        <div id="slots-section">
                                            <!-- Title and Button -->
                                            <div class="flex items-center justify-between mb-3">
                                                <h5 class="text-secondary font-normal text-gray-600 text-base mb-2">Map With Time Slots</h5>
                                                <button type="button" id="select-all-btn" class="btn btn-secondary btn-sm btn-info-small">Select All</button>
                                            </div>

                                            <!-- Slots container where the time slots will be dynamically inserted -->
                                            <div class="p-3 border rounded" id="slots-container"></div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css-libraries')
    <link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
	<link href="{{ asset('plugins/daterange/daterangepicker.2.1.25.css') }}" rel="stylesheet">

    <!-- <link href="{{ asset('css/user-common/package-rates/style.css') }}" rel="stylesheet"> -->
@endsection

@section('css-custom')
    <style>
        /* */
    </style>
@endsection

@section('js-libraries')
    <script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
    <script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/daterange/daterangepicker.2.1.25.js') }}"></script>
    <script src="{{ asset('js/user-common/package-rates/customDateRateManager.js') }}"></script>
@endsection

@section('js-custom')
    <script>
        $(document).ready(() => {
            const getPackageRatesUrl = "{{ route( routePrefix() . '.package-rate.get-package-rates') }}";
            const saveCustomDateRatesUrl = "{{ route( routePrefix() . '.package-rate.save-custom-date-rates') }}";
            const deletePackageRatesUrl = "{{ route( routePrefix() . '.package-rate.delete-package-rates') }}";
            const getTimeSlotsUrl = "{{ route( routePrefix() . '.package-rate.get-time-slots') }}";
            const unassignSlotToDateRateUrl = "{{ route( routePrefix() . '.package-rate.unassign-slot-to-date-rate') }}";
            const deleteCustomDateRateUrl = "{{ route(routePrefix() . '.package-rate.delete-custom-date-rate') }}";

            // Instantiate the tax data for the js class
            const taxPercent = "{{ $taxPercent->value ?? 0 }}";
            const taxPercentType = "{{ $taxPercent->value_type ?? '' }}";
            const cashPriceEnabled = "{{ $cashPriceEnabled }}";

            // Instantiate the General Rates class
            const generalRates = new CustomDateRateManager(getPackageRatesUrl, getTimeSlotsUrl, saveCustomDateRatesUrl, deleteCustomDateRateUrl, deletePackageRatesUrl, taxPercent, taxPercentType, cashPriceEnabled);
        });
    </script>
@endsection
