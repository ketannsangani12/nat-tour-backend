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
                                        <a class="tabs-custom-link !px-4" href="{{ route(routePrefix() . '.package-rate.index') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> General Rates
                                        </a>
                                        <a class="tabs-custom-link !px-4" href="{{ route(routePrefix() . '.package-rate.custom-date-rate') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> Custom Date Rates
                                        </a>
                                        <a class="tabs-custom-link !px-4 active" href="{{ route(routePrefix() . '.package-rate.global-rate') }}">
                                            <i class="fas fa-link sub-nav-icon mr-2"></i> Global Rates
                                        </a>
                                    </div>
                                </span>
                            </div>
                        </div>

                        <div id="form-container" class="">
                            <form id="global-rate-form" method="POST" action="#">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <!-- Left Section: Accordion for Packages and Affiliates -->
                                    <div class="w-full lg:w-1/4 max-w-full px-3">
                                        <div class="accordion" id="packageAffiliateAccordion">
                                            <!-- Packages Section -->
                                            <div class="accordion-item mb-4">
                                                <h2 class="accordion-header" id="headingPackages">
                                                    <button
                                                        class="accordion-button accordion-button-wrap"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapsePackages"
                                                        aria-expanded="true"
                                                        aria-controls="collapsePackages">
                                                        Packages
                                                        <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                                    </button>
                                                </h2>
                                                <div id="collapsePackages" class="accordion-collapse collapse show" aria-labelledby="headingPackages">
                                                    <div class="accordion-body p-3 border border-[#E5E7EB] rounded-lg">
                                                        <!-- Search Bar for Packages -->
                                                        <input
                                                            type="text"
                                                            id="search-packages"
                                                            class="form-control mb-3 form-custom-input"
                                                            placeholder="Search Packages..." />
                                                        <!-- Packages List -->
                                                        <div id="packages-list" class="packages-list list-group"></div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Affiliates Section -->
                                            <div class="accordion-item mb-4">
                                                <h2 class="accordion-header" id="headingAffiliates">
                                                    <button
                                                        class="accordion-button accordion-button-wrap collapsed"
                                                        type="button"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#collapseAffiliates"
                                                        aria-expanded="false"
                                                        aria-controls="collapseAffiliates">
                                                        Affiliates
                                                        <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                                    </button>
                                                </h2>
                                                <div id="collapseAffiliates" class="accordion-collapse collapse" aria-labelledby="headingAffiliates">
                                                    <div class="accordion-body p-3 border border-[#E5E7EB] rounded-lg">
                                                        <!-- Search Bar for Affiliates -->
                                                        <input
                                                            type="text"
                                                            id="search-affiliates"
                                                            class="form-control mb-3 form-custom-input"
                                                            placeholder="Search Affiliates..." />
                                                        <!-- Affiliates List -->
                                                        <div id="affiliates-list" class="affiliates-list list-group"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Right Section: Rate Group Management -->
                                    <div class="w-full lg:w-9/12 max-w-full px-3">
                                        <div id="rate-group-title-section">
                                            <div class="flex justify-between items-center mb-3">
                                                <h5 class="text-secondary text-secondary font-normal text-gray-600 text-base mb-2">Rate Group Management</h5>

                                                <select id="scheduling-year" class="form-select form-select-sm form-custom-input" style="width: 15%; margin-right: 2px;">
                                                    @for($i = date('Y') - 1; $i < date('Y') + 7; $i++)
                                                        <option {{ date('Y') == $i? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                                                        @endfor
                                                </select>
                                            </div>
                                        </div>

                                        <div id="rate-group-section">
                                            <!-- Selected Items Display -->
                                            <div id="selected-items">
                                                <div class="card">
                                                    <div class="card-body lg:flex align-items-center border border-[#E5E7EB] p-4 rounded-lg">
                                                        <!-- Left Section: Selected Items -->
                                                        <div class="lg:mb-0 mb-3 lg:max-w-56 lg:pr-5 lg:w-full">
                                                            <h6 class="card-title text-base font-medium text-[#111827] mb-2">Selected Items</h6>
                                                            <p class="card-text form-custom-label">
                                                                <strong>Package:</strong> <span id="selected-package-name" class="text-primary">None</span><br>
                                                                <strong>Affiliate:</strong> <span id="selected-affiliate-name" class="text-primary">None</span>
                                                            </p>
                                                        </div>
                                                        <!-- Right Section: Inputs and Buttons -->
                                                        <div class="flex flex-wrap items-center -mx-3">
                                                            <!-- Number Input -->
                                                            <div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-2">
                                                                <input type="number" id="charge-amount" value="0" min="0" step="0.01" class="form-control form-control-sm form-custom-input" placeholder="Number">
                                                            </div>
                                                            <!-- Upcharge/Downcharge Dropdown -->
                                                            <div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-2">
                                                                <select id="charge-type" class="form-select form-select-sm form-custom-input">
                                                                    <option value="upcharge">Upcharge</option>
                                                                    <option value="downcharge">Downcharge</option>
                                                                </select>
                                                            </div>
                                                            <!-- Percentage/Fixed Dropdown -->
                                                            <div class="w-full md:w-1/2 lg:w-1/4 max-w-full px-3 mb-2">
                                                                <select id="charge-mode" class="form-select form-select-sm form-custom-input">
                                                                    <option value="percentage">%</option>
                                                                    <option value="fixed">Fixed</option>
                                                                </select>
                                                            </div>
                                                            <!-- Buttons -->
                                                            <div class="w-full md:w-1/2 lg:w-1/4 max-w-full flex items-center px-3 mb-2">
                                                                <button type="button" id="charge-button" class="btn btn-success btn-sm mr-2 btn-info-small">Preview</button>
                                                                <button type="button" id="reset-button" class="btn btn-secondary btn-sm  mr-2 btn-primary-small">Reset</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Rate Group Table -->
                                            <div class="w-full overflow-y-auto">
                                            <table class="table table-bordered mt-3 w-full border border-collapse">
                                                <tbody id="tbody-pckg-rate-grps"></tbody>
                                            </table>

                                            <!-- Add Rate Group Button -->
                                            <div class="text-end mt-3 mb-2">
                                                <!-- <button type="button" id="add-rate-group-btn" class="btn btn-info">Add Rate Group</button> -->
                                                <button type="button" id="add-rate-schedule-btn" class="btn btn-warning mr-2 btn-info-small">Schedule</button>
                                                <button type="button" id="save-rate-group-btn" class="btn btn-secondary mr-2 btn-primary-small">Save</button>
                                            </div>
                                        </div>

                                        <div id="schedule-event-table" class="table-responsive mt-3 overflow-y-auto"></div>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-fullscreen max-w-[480px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center" role="document">
                                <div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
                                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                                        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="scheduleModalLabel">Schedule Event - 2024</h5>
                                        <button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                                    </div>
                                    <div class="modal-body p-4">
                                        <div class="mb-4">
                                            <h6 class="text-muted">Event Details</h6>
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="w-full lg:w-1/2 max-w-full px-3">
                                                    <p class="text-sm text-[#020817]"><strong>Package:</strong> <span class="md-package">None</span></p>
                                                </div>
                                                <div class="w-full lg:w-1/2 max-w-full px-3">
                                                    <p class="text-sm text-[#020817]"><strong>Affiliate:</strong> <span class="md-affiliate">None</span></p>
                                                </div>
                                                <div class="w-full lg:w-1/2 max-w-full px-3">
                                                    <p class="text-sm text-[#020817]"><strong>Amount:</strong> <span class="md-number">0</span></p>
                                                </div>
                                                <div class="w-full lg:w-1/2 max-w-full px-3">
                                                    <p class="text-sm text-[#020817]"><strong>Type:</strong> <span class="md-type">Upcharge</span></p>
                                                </div>
                                                <div class="w-full lg:w-1/2 max-w-full px-3">
                                                    <p class="text-sm text-[#020817]"><strong>Mode:</strong> <span class="md-method">Percentage</span></p>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="scheduling-date-time" class="form-label form-custom-label">Date & Time</label>
                                            <input type="text" class="form-control form-custom-input" id="scheduling-date-time" placeholder="Select date and time">
                                        </div>
                                    </div>
                                    <div class="modal-footer flex items-center px-4 pb-4">
                                        <button type="button" class="btn btn-secondary mr-2 w-1/2 text-xs text-[#020817] font-semibold shadow-sm rounded-lg px-4 py-2.5 inline-block bg-white border border-[#E2E8F0] hover:border-[#E2E8F0] hover:bg-[#E2E8F0] hover:text-[#020817] transition-all duration-300 ease-in-out" data-bs-dismiss="modal">Close</button>
                                        <button type="button" class="btn btn-primary w-1/2 text-xs text-white font-semibold shadow-sm rounded-lg px-4 py-2.5 inline-block bg-[#264F73] border border-[#264F73] hover:border-[#264F73] hover:bg-white hover:text-[#264F73] transition-all duration-300 ease-in-out" id="save-schedule-event">Save Event</button>
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
<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

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
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="{{ asset('js/user-common/package-rates/globalRateManager.js') }}"></script>
@endsection

@section('js-custom')
<script>
    $(document).ready(function() {
        // Check is setting enabled for staff users
        const departmentPackage = "{{ is_array(departmentPackage()) ?: 0 }}";        

        // Fetch package and affiliate data from the Laravel Blade template variables
        const packages = @json($packages);
        const affiliates = @json($affiliates);

        // Instantiate the tax data for the js class
        const taxPercent = "{{ $taxPercent->value ?? 0 }}";
        const taxPercentType = "{{ $taxPercent->value_type ?? '' }}";
        const cashPriceEnabled = "{{ $cashPriceEnabled }}";

        // Instantiate the URL for fetching package and affiliate rates from the API endpoint
        const getPackageRatesUrl = "{{ route( routePrefix() . '.package-rate.get-package-rates') }}";
        const deletePackageRatesUrl = "{{ route( routePrefix() . '.package-rate.delete-package-rates') }}";
        const saveGlobalRatesUrl = "{{ route( routePrefix() . '.package-rate.save-global-rates') }}";
        const saveScheduleEventUrl = "{{ route( routePrefix() . '.package-rate.save-schedule-event') }}";
        const fetchScheduleEventUrl = "{{ route( routePrefix() . '.package-rate.fetch-schedule-events') }}";
        const deleteScheduleEventUrl = "{{ route( routePrefix() . '.package-rate.delete-schedule-event') }}";

        // Instantiate the Global Rates class
        const globalRateManager = new GlobalRateManager(packages, affiliates, taxPercent, taxPercentType, departmentPackage, getPackageRatesUrl, deletePackageRatesUrl, saveGlobalRatesUrl, saveScheduleEventUrl, fetchScheduleEventUrl, deleteScheduleEventUrl, cashPriceEnabled);
    });
</script>
@endsection
