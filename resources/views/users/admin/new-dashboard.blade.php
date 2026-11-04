@extends('layouts.default-2')

@section('main-content')

@if (isPermissionGranted() == false)
<style>
    .range-item {
        padding: 8px 12px;
        cursor: pointer;
    }
    .range-item:hover {
        background-color: #f3f4f6;
    }
</style>
<section class="bg-[#F9FAFB] py-6">
    <div class="container mx-auto px-4">

        <!-- HEADER -->
        <div class="flex flex-wrap items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold text-gray-800">Dashboard</h1>
            <div class="flex gap-3 items-center">
                <div class="card-tools flex items-center">
                    <label
                        id="dashboardRangeLabel"
                        class="text-sm font-medium text-gray-800 mr-2">
                        Today
                    </label>

                    <button
                        type="button"
                        id="dashboardRangeBtn"
                        class="bg-white border border-gray-300 p-2 rounded-lg">
                        <svg width="16" height="17" viewBox="0 0 16 17" fill="none">
                            <path d="M5.3 1.8V4.5M10.6 1.8V4.5M12.6 3.1H3.3C2.6 3.1 2 3.7 2 4.5V13.8C2 14.5 2.6 15.1 3.3 15.1H12.6C13.4 15.1 14 14.5 14 13.8V4.5C14 3.7 13.4 3.1 12.6 3.1Z"
                                  stroke="#4B5563" stroke-width="1.3"/>
                            <path d="M2 7.1H14" stroke="#4B5563" stroke-width="1.3"/>
                        </svg>
                    </button>
                </div>

                <!-- Multi-select request from dropdown -->
                <div class="relative" id="requestFromDropdownWrap">
                    <button type="button" id="requestFromBtn"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700 w-[190px]">
                        <span id="requestFromBtnLabel" class="truncate flex-1 text-left">All Backend Sources</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="requestFromDropdown"
                        class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-30 w-[200px]">

                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
                            <button type="button" id="requestFromSelectAll"
                                class="text-sm font-medium text-gray-700 hover:text-gray-900">Select All</button>
                            <button type="button" id="requestFromClear"
                                class="text-sm font-medium text-gray-400 hover:text-gray-600">Clear</button>
                        </div>

                        <div class="flex flex-col py-1 px-2">
                            @foreach([['BACKEND','Backend'],['FRONTEND','Frontend'],['HEADOUT','Headout'],['TRIPADVISOR','TripAdvisor']] as [$val,$label])
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 text-sm text-gray-700">
                                <input type="checkbox"
                                    class="request-from-cb rounded accent-[#E89A42] flex-shrink-0 w-4 h-4"
                                    value="{{ $val }}"
                                    checked />
                                <span class="leading-snug">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Multi-select payment type dropdown -->
                <div class="relative" id="paymentTypeDropdownWrap">
                    <button type="button" id="paymentTypeBtn"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700 w-[190px]">
                        <span id="paymentTypeBtnLabel" class="truncate flex-1 text-left">All Payment Types</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="paymentTypeDropdown"
                        class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-30 w-[200px]">

                        <!-- Select All / Clear -->
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
                            <button type="button" id="paymentTypeSelectAll"
                                class="text-sm font-medium text-gray-700 hover:text-gray-900">Select All</button>
                            <button type="button" id="paymentTypeClear"
                                class="text-sm font-medium text-gray-400 hover:text-gray-600">Clear</button>
                        </div>

                        <!-- Payment type list -->
                        <div class="flex flex-col py-1 px-2">
                            @foreach([['Cash','Cash'],['Card','Card'],['Check/Wire','Check/Wire'],['Invoiced','Invoiced']] as [$val,$label])
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 text-sm text-gray-700">
                                <input type="checkbox"
                                    class="payment-type-cb rounded accent-[#E89A42] flex-shrink-0 w-4 h-4"
                                    value="{{ $val }}"
                                    checked />
                                <span class="leading-snug">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Multi-select third party platform dropdown -->
                <div class="relative" id="thirdPartyDropdownWrap">
                    <button type="button" id="thirdPartyBtn"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700 w-[190px]">
                        <span id="thirdPartyBtnLabel" class="truncate flex-1 text-left">All Platforms</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="thirdPartyDropdown"
                        class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-30 w-[200px]">

                        <!-- Select All / Clear -->
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
                            <button type="button" id="thirdPartySelectAll"
                                class="text-sm font-medium text-gray-700 hover:text-gray-900">Select All</button>
                            <button type="button" id="thirdPartyClear"
                                class="text-sm font-medium text-gray-400 hover:text-gray-600">Clear</button>
                        </div>

                        <!-- Platform list -->
                        <div class="flex flex-col py-1 px-2">
                            @foreach([['TripAdvisor','TripAdvisor'],['Headout','Headout'],['FareHarbor','FareHarbor']] as [$val,$label])
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 text-sm text-gray-700">
                                <input type="checkbox"
                                    class="third-party-cb rounded accent-[#E89A42] flex-shrink-0 w-4 h-4"
                                    value="{{ $val }}"
                                    checked />
                                <span class="leading-snug">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Multi-select tour package dropdown -->
                <div class="relative" id="tourPackageDropdownWrap">
                    <button type="button" id="tourPackageBtn"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700 w-[190px]">
                        <span id="tourPackageBtnLabel" class="truncate flex-1 text-left">All Packages</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="tourPackageDropdown"
                        class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-30 w-[380px]">

                        <!-- Select All / Clear -->
                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
                            <button type="button" id="tourPkgSelectAll"
                                class="text-sm font-medium text-gray-700 hover:text-gray-900">Select All</button>
                            <button type="button" id="tourPkgClear"
                                class="text-sm font-medium text-gray-400 hover:text-gray-600">Clear</button>
                        </div>

                        <!-- Package list -->
                        <div class="flex flex-col max-h-72 overflow-y-auto py-1 px-2">
                            @foreach($tourPackages as $package)
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 text-sm text-gray-700">
                                <input type="checkbox"
                                    class="tour-pkg-cb rounded accent-[#E89A42] flex-shrink-0 w-4 h-4"
                                    value="{{ $package->id }}"
                                    data-name="{{ $package->name }}" />
                                <span class="leading-snug">{{ $package->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Multi-select booking source dropdown -->
                <div class="relative" id="bookingSourceDropdownWrap">
                    <button type="button" id="bookingSourceBtn"
                        class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700 w-[190px]">
                        <span id="bookingSourceBtnLabel" class="truncate flex-1 text-left">All Booking Sources</span>
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div id="bookingSourceDropdown"
                        class="hidden absolute right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-xl z-30 w-[200px]">

                        <div class="flex items-center justify-between px-4 py-2.5 border-b border-gray-100">
                            <button type="button" id="bookingSourceSelectAll"
                                class="text-sm font-medium text-gray-700 hover:text-gray-900">Select All</button>
                            <button type="button" id="bookingSourceClear"
                                class="text-sm font-medium text-gray-400 hover:text-gray-600">Clear</button>
                        </div>

                        <div class="flex flex-col py-1 px-2">
                            @foreach([['phone_call','Phone Call'],['walk_in','Walk In'],['online','Online'],['email','Email'],['other','Other']] as [$val,$label])
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer hover:bg-gray-50 text-sm text-gray-700">
                                <input type="checkbox"
                                    class="booking-source-cb rounded accent-[#E89A42] flex-shrink-0 w-4 h-4"
                                    value="{{ $val }}" />
                                <span class="leading-snug">{{ $label }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- TAB NAV -->
        <div class="flex items-center justify-between border-b border-gray-200 mb-6">
            <div class="flex">
                <button type="button" id="tab-btn-overview"
                    class="dashboard-tab-btn px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors text-[#00b1ff] border-[#00b1ff]">
                    Overview &amp; Revenue Trend
                </button>
                <button type="button" id="tab-btn-payment"
                    class="dashboard-tab-btn px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors text-gray-400 border-transparent hover:text-gray-600">
                    Payment &amp; Groups
                </button>
                <button type="button" id="tab-btn-customers"
                    class="dashboard-tab-btn px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors text-gray-400 border-transparent hover:text-gray-600">
                    Customers
                </button>
                <button type="button" id="tab-btn-geographic"
                    class="dashboard-tab-btn px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors text-gray-400 border-transparent hover:text-gray-600">
                    Geographic
                </button>
                <button type="button" id="tab-btn-insights"
                    class="dashboard-tab-btn px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors text-gray-400 border-transparent hover:text-gray-600">
                    Insights
                </button>
                <button type="button" id="tab-btn-affiliates"
                    class="dashboard-tab-btn px-5 py-2.5 text-sm font-medium border-b-2 -mb-px transition-colors text-gray-400 border-transparent hover:text-gray-600">
                    Affiliates
                </button>
            </div>

            <!-- Export PDF button -->
            <div class="pb-1">
                <button type="button" id="dashboardExportPdf"
                    class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm bg-white text-gray-700 hover:bg-gray-50">
                    <svg class="w-4 h-4 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    <span>Export PDF</span>
                </button>
            </div>
        </div>

        <!-- TAB PANEL: Overview & Revenue Trend -->
        <div id="tab-overview">

        <!-- KPI ROW 1 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">

            <!-- TOTAL GROSS -->
            <div class="bg-emerald-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL GROSS</p>
                </div>

                <h2 class="text-2xl font-bold mt-3" id="totalGross">0</h2>

                <p id="grossComparison"
                   class="text-sm mt-1 hidden"></p>
            </div>

            <!-- TOTAL NET -->
            <div class="bg-sky-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL NET</p>
                </div>

                <h2 class="text-2xl font-bold mt-3" id="totalNet">0</h2>

                <p id="netComparison"
                   class="text-sm mt-1 hidden"></p>
            </div>

            <!-- TOTAL REFUND -->
            <div class="bg-red-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL REFUND</p>
                </div>

                <h2 class="text-2xl font-bold mt-3" id="totalRefund">0</h2>

                <p id="refundComparison"
                   class="text-sm mt-1 hidden"></p>
            </div>

            <!-- TOTAL DISCOUNT -->
            <div class="bg-orange-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL DISCOUNT</p>
                </div>

                <h2 class="text-2xl font-bold mt-3" id="totalDiscount">0</h2>

                <p id="discountComparison"
                   class="text-sm mt-1 hidden"></p>
            </div>

        </div>

        <!-- KPI ROW 2 -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">

            <!-- TOTAL TOURISTS -->
            <div class="bg-purple-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL TOURISTS</p>
                </div>
                <h2 class="text-2xl font-bold mt-3" id="totalTourists">0</h2>
                <p id="touristsComparison" class="text-sm mt-1 hidden"></p>
            </div>

            <!-- TOTAL BOOKINGS -->
            <div class="bg-cyan-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL BOOKINGS</p>
                </div>
                <h2 class="text-2xl font-bold mt-3" id="totalBookings">0</h2>
                <p id="bookingsComparison" class="text-sm mt-1 hidden"></p>
            </div>

            <!-- TOTAL TAX -->
            <div class="bg-gray-100 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL TAX</p>
                </div>
                <h2 class="text-2xl font-bold mt-3" id="totalTax">0</h2>
                <p class="text-sm mt-1 hidden" id="taxComparison"></p>
            </div>

            <!-- TOTAL PERMIT FEE -->
            <div class="bg-yellow-50 rounded-xl p-5 shadow-sm">
                <div class="flex justify-between items-start">
                    <p class="text-sm text-gray-500">TOTAL PERMIT FEE</p>
                </div>
                <h2 class="text-2xl font-bold mt-3" id="totalPermitFee">0</h2>
                <p class="text-sm mt-1 hidden" id="permitFeeComparison"></p>
            </div>

        </div>

        <!-- CHARTS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3" id="charts-row-1">
            <div class="lg:col-span-2 bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">TOTAL NET BY TOUR DATE</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="netByDateChart" data-title="TOTAL NET BY TOUR DATE" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                    </button>
                </div>

                <div class="relative h-72">
                    <canvas id="netByDateChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">NET BY PURCHASE TYPE</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="purchaseTypeChart" data-title="NET BY PURCHASE TYPE" data-legend="purchase" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/>
                        </svg>
                    </button>
                </div>

                <div class="relative h-72 flex items-center justify-center">
                    <canvas id="purchaseTypeChart"></canvas>
                </div>

                <!-- Custom Legend -->
                <div class="flex justify-center gap-6 mt-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-[#5FBDB8]"></span>
                        <span class="text-[#5FBDB8]">Booked</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-[#D9381E]"></span>
                        <span class="text-[#D9381E]">Cancelled / Refund</span>
                    </div>
                </div>
            </div>

        </div>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-3">
            <div class="lg:col-span-2 bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">NET & BOOKINGS BY TOUR PACKAGE</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="netByPackageChart" data-title="NET &amp; BOOKINGS BY TOUR PACKAGE" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>

                <div class="relative h-80">
                    <canvas id="netByPackageChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">PEAK BOOKING TIMES</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="peakBookingChart" data-title="PEAK BOOKING TIMES" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>

                <div class="relative h-72 w-full">
                    <canvas id="peakBookingChart"></canvas>
                </div>

            </div>
        </div>

        <!-- THREE ANALYTICS CHARTS -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-3">

            <!-- MOST PROFITABLE DAYS -->
            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">MOST PROFITABLE DAYS</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="profitableDaysChart" data-title="MOST PROFITABLE DAYS" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>

                <div class="relative h-72">
                    <canvas id="profitableDaysChart"></canvas>
                </div>
            </div>


            <!-- WEEKENDS VS WEEKDAYS -->
            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">WEEKENDS VS WEEKDAYS</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="weekendWeekdayChart" data-title="WEEKENDS VS WEEKDAYS" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>

                <div class="relative h-72">
                    <canvas id="weekendWeekdayChart"></canvas>
                </div>
            </div>


            <!-- NET BY PAYMENT DATE -->
            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">NET BY PAYMENT DATE</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="netPaymentDateChart" data-title="NET BY PAYMENT DATE" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>

                <div class="relative h-72">
                    <canvas id="netPaymentDateChart"></canvas>
                </div>
            </div>

        </div>

        <!-- TOURISTS & BOOKINGS — place after the THREE ANALYTICS CHARTS grid -->
        <div class="grid grid-cols-1 gap-3 mt-3">
            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">TOURISTS &amp; BOOKINGS</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="touristsBookingsChart" data-title="TOURISTS &amp; BOOKINGS" data-legend="tourists" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>
                <div class="relative h-80">
                    <canvas id="touristsBookingsChart"></canvas>
                </div>
                <!-- Legend -->
                <div class="flex justify-center gap-6 mt-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-[#E05C3A]"></span>
                        <span class="text-gray-600">Tourists</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded bg-[#5FBDB8]"></span>
                        <span class="text-gray-600">Bookings</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- STATE TAX BY TOUR DATE + AVG TAX PER PERSON -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mt-3">

            <!-- STATE TAX BY TOUR DATE (spans 2/3) -->
            <div class="lg:col-span-2 bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">STATE TAX BY TOUR DATE</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="stateTaxByDateChart" data-title="STATE TAX BY TOUR DATE" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>
                <div class="relative h-72">
                    <canvas id="stateTaxByDateChart"></canvas>
                </div>
            </div>

            <!-- AVG TAX PER PERSON (spans 1/3) -->
            <div class="bg-white rounded-xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">AVG TAX PER PERSON</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="avgTaxPerPersonChart" data-title="AVG TAX PER PERSON" title="Expand chart">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>
                <div class="relative h-72">
                    <canvas id="avgTaxPerPersonChart"></canvas>
                </div>
            </div>

        </div>

       <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-3">
            <!-- AFFILIATE SUMMARY (left half) -->
            <div id="affiliateSummarySection" class="bg-white rounded-xl p-5 shadow-sm flex flex-col" style="height: 520px;">

                <!-- Header -->
                <div class="flex flex-wrap items-center justify-between mb-4 flex-shrink-0">
                    <h3 class="text-sm font-medium text-gray-600">AFFILIATE SUMMARY</h3>
                    <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-1.5">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input
                            type="text"
                            id="affiliateSearch"
                            placeholder="Search affiliates..."
                            class="text-sm focus:outline-none w-36 bg-transparent text-gray-700 placeholder-gray-400"
                        />
                    </div>
                </div>

                <!-- Table with scroll -->
                <div class="overflow-x-auto flex-1 overflow-y-auto" style="scrollbar-width: none; -ms-overflow-style: none;">
                    <style>#affiliateScrollBody::-webkit-scrollbar { display: none; }</style>
                    <table class="w-full text-sm text-left" id="affiliateTable">
                        <thead class="sticky top-0 bg-white z-10">
                            <tr class="border-b border-gray-100">
                                <th class="pb-3 pr-3 text-xs font-medium text-gray-400 cursor-pointer select-none whitespace-nowrap" data-col="0">
                                    Affiliate <span class="sort-icon text-gray-300">↕</span>
                                </th>
                                <th class="pb-3 pr-3 text-xs font-medium text-gray-400 cursor-pointer select-none whitespace-nowrap" data-col="1">
                                    Bookings <span class="sort-icon text-gray-300">↕</span>
                                </th>
                                <th class="pb-3 pr-3 text-xs font-medium text-gray-400 cursor-pointer select-none whitespace-nowrap" data-col="2">
                                    Total Revenue <span class="sort-icon text-gray-300">↕</span>
                                </th>
                                <th class="pb-3 pr-3 text-xs font-medium text-gray-400 cursor-pointer select-none whitespace-nowrap" data-col="3">
                                    Top Tour <span class="sort-icon text-gray-300">↕</span>
                                </th>
                                <th class="pb-3 pr-3 text-xs font-medium text-gray-400 whitespace-nowrap" data-col="4">
                                    Status
                                </th>
                                <th class="pb-3 text-xs font-medium text-gray-400 cursor-pointer select-none whitespace-nowrap change-col hidden" data-col="5">
                                    Change % <span class="sort-icon text-gray-300">↕</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="affiliateTableBody">
                            <tr>
                                <td colspan="6" class="py-8 text-center text-gray-300 text-sm">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>

           <!-- TOUR PACKAGE PERFORMANCE (right half) -->
            <div id="packagePerformanceSection" class="bg-white rounded-xl p-5 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">TOUR PACKAGE PERFORMANCE</h3>
                    <div class="relative">
                        <button id="packageFilterBtn"
                            class="flex items-center gap-2 text-sm text-gray-500 border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 4a1 1 0 0 1 1-1h16a1 1 0 0 1 .707 1.707L13 13.414V19a1 1 0 0 1-1.447.894l-4-2A1 1 0 0 1 7 17v-3.586L3.293 4.707A1 1 0 0 1 3 4z"/>
                            </svg>
                            Filter
                            <!-- active dot — shown when any package is unchecked -->
                            <span id="packageFilterDot" class="hidden w-2 h-2 rounded-full" style="background:#00b1ff"></span>
                        </button>

                        <!-- Dropdown -->
                        <div id="packageFilterDropdown"
                            class="hidden absolute right-0 mt-1 bg-white border border-gray-100 rounded-xl shadow-lg z-20 p-3 w-52">

                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Packages</p>
                                <button id="packageFilterSelectAll"
                                        class="text-xs font-medium"
                                        style="color:#00b1ff">
                                    All
                                </button>
                            </div>

                            <!-- Checkboxes injected by JS -->
                            <div id="packageFilterList" class="flex flex-col gap-1 max-h-48 overflow-y-auto"></div>

                        </div>
                    </div>
                </div>

                <!-- Package cards -->
                <div id="packagePerformanceList" class="flex flex-col gap-4 overflow-y-auto max-h-[420px] pr-1">
                    <div class="text-center text-gray-300 text-sm py-8">Loading...</div>
                </div>
            </div>

        </div>

        </div><!-- /tab-overview -->

        <!-- TAB PANEL: Payment & Groups -->
        <div id="tab-payment" class="hidden">

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6" id="pgKpiRow">
                <!-- Card Revenue -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#5FBDB8;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1" id="pgCardRevenueLabel">REVENUE (100%)</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="pgCardRevenue">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="pgCardRevenueDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#e0f7f6;color:#0d9488;">Payments</span>
                </div>
                <!-- Most Common Group -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#1e3a5f;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">MOST COMMON GROUP</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="pgMostCommon">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="pgMostCommonDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#e0eaff;color:#1e3a5f;">Most Freq</span>
                </div>
                <!-- Highest Revenue Segment -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#5FBDB8;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">HIGHEST REVENUE SEGMENT</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="pgTopRevGroup">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="pgTopRevDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#e0f7f6;color:#0d9488;">Top Rev</span>
                </div>
                <!-- Highest Rev/Booking -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#E89A42;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">HIGHEST REV/BOOKING</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="pgPremiumGroup">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="pgPremiumDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#fff3e0;color:#E89A42;">Premium</span>
                </div>
            </div>

            <!-- Doughnut Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-1">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Booking Count by Group Size</h3>
                            <p class="text-xs text-gray-400">Number of bookings per party size segment</p>
                        </div>
                        <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="pgBookingCountChart" data-title="Booking Count by Group Size" title="Expand chart">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="relative" style="height:360px;">
                        <canvas id="pgBookingCountChart"></canvas>
                    </div>
                </div>

                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-1">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Revenue by Group Size</h3>
                            <p class="text-xs text-gray-400">Total revenue contribution per party size</p>
                        </div>
                        <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="pgRevenueChart" data-title="Revenue by Group Size" title="Expand chart">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="relative" style="height:360px;">
                        <canvas id="pgRevenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Group Size Full Breakdown Table -->
            <div class="bg-white rounded-xl shadow-sm mt-3 overflow-hidden">
                <div class="px-5 pt-5 pb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Group Size — Full Breakdown</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Count, revenue, tourists and average revenue per booking</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="pgBreakdownTable">
                        <thead>
                            <tr style="background:#1e3a5f;">
                                <th class="text-left px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Group Size</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Bookings</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">% of Bookings</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Revenue</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">% of Revenue</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Tourists</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Avg Rev/Booking</th>
                                <th class="text-right px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide">Rev/Tourist</th>
                            </tr>
                        </thead>
                        <tbody id="pgBreakdownBody">
                            <tr><td colspan="8" class="py-8 text-center text-gray-300 text-sm">Loading...</td></tr>
                        </tbody>
                        <tfoot id="pgBreakdownFoot"></tfoot>
                    </table>
                </div>

                <!-- Insights -->
                <div class="px-5 py-4 border-t border-gray-100" id="pgInsightsWrap" style="background:#f8faff;">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 20 20" fill="#E89A42"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 11H9v-2h2v2zm0-4H9V5h2v4z"/></svg>
                        <span class="text-xs font-bold uppercase tracking-widest" style="color:#1e3a5f;">Group Size Pattern</span>
                    </div>
                    <ul id="pgInsightsList" class="space-y-1">
                        <li class="text-xs text-gray-300">Loading...</li>
                    </ul>
                </div>
            </div>

        </div>

        <!-- TAB PANEL: Customers -->
        <div id="tab-customers" class="hidden">

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <!-- Top Customer -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#5FBDB8;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">TOP CUSTOMER</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="custTopRevenue">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="custTopDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#e0f7f6;color:#0d9488;">#1</span>
                </div>
                <!-- Top 3 Combined -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#1e3a5f;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">TOP 3 COMBINED</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="custTop3Revenue">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="custTop3Names">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" id="custTop3Badge" style="background:#e0eaff;color:#1e3a5f;">—</span>
                </div>
                <!-- Repeat Customers -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#3DAA5E;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">REPEAT CUSTOMERS</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="custRepeat">—</h2>
                    <p class="text-xs text-gray-400 mb-3">Customers with 2+ bookings in period</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#dcfce7;color:#15803d;">Loyal</span>
                </div>
                <!-- Avg Rev / Tourist -->
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#E89A42;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">AVG REV / TOURIST</p>
                    <h2 class="text-3xl font-bold text-gray-800 mb-1" id="custAvgRevTourist">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="custAvgRevTouristDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#fff3e0;color:#E89A42;">Stable Yield</span>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                <!-- Top 12 Customers by Revenue -->
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-1">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Top 12 Customers by Revenue</h3>
                            <p class="text-xs text-gray-400" id="custChartDateRange">— · all card payments</p>
                        </div>
                        <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="custTopChart" data-title="Top 12 Customers by Revenue" title="Expand chart">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="relative" style="height:420px;">
                        <canvas id="custTopChart"></canvas>
                    </div>
                </div>

                <!-- Revenue per Booking by Group Size -->
                <div class="bg-white rounded-xl p-5 shadow-sm flex flex-col">
                    <div class="flex items-center justify-between mb-1">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-700">Revenue per Booking by Group Size</h3>
                            <p class="text-xs text-gray-400">How much each booking is worth depending on party size</p>
                        </div>
                        <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100" data-chart="custGroupAvgChart" data-title="Revenue per Booking by Group Size" title="Expand chart">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="relative flex-1" style="height:280px;">
                        <canvas id="custGroupAvgChart"></canvas>
                    </div>

                    <!-- Customer Pattern Insights -->
                    <div class="mt-4 rounded-lg px-4 py-3 border border-blue-100" style="background:#f0f6ff;">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 20 20" fill="#E89A42"><path d="M10 2a8 8 0 100 16A8 8 0 0010 2zm1 11H9v-2h2v2zm0-4H9V5h2v4z"/></svg>
                            <span class="text-xs font-bold uppercase tracking-widest" style="color:#1e3a5f;">Customer Pattern</span>
                        </div>
                        <ul id="custInsightsList" class="space-y-1">
                            <li class="text-xs text-gray-300">Loading...</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Top 12 Customer Detail Table -->
            <div class="bg-white rounded-xl shadow-sm mt-3 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-700">Top 12 Customer Detail</h3>
                    <p class="text-xs text-gray-400">Revenue, bookings, tourists and revenue per tourist</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left" id="custDetailTable">
                        <thead>
                            <tr class="text-xs font-semibold text-white uppercase" style="background:#1e3a5f;">
                                <th class="px-4 py-3 w-10">#</th>
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3">Revenue</th>
                                <th class="px-4 py-3">Bookings</th>
                                <th class="px-4 py-3">Tourists</th>
                                <th class="px-4 py-3">Avg Pax/Booking</th>
                                <th class="px-4 py-3">Rev/Tourist</th>
                            </tr>
                        </thead>
                        <tbody id="custDetailTableBody">
                            <tr><td colspan="7" class="px-4 py-4 text-xs text-gray-400 text-center">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /tab-customers -->

         <!-- TAB PANEL: Geographic -->
        <div id="tab-geographic" class="hidden">

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#5FBDB8;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">TOP BOOKINGS</p>
                    <h2 class="text-2xl font-bold text-gray-800 mb-1" id="geoTopBookings">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="geoTopBookingsDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#e0f7f6;color:#0d9488;" id="geoTopCountryBadge">#1</span>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#1e3a5f;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">OTHER BOOKINGS</p>
                    <h2 class="text-2xl font-bold text-gray-800 mb-1" id="geoIntlPct">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="geoIntlDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#e0eaff;color:#1e3a5f;">Countries</span>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#3DAA5E;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">EARLIEST BOOKER</p>
                    <h2 class="text-2xl font-bold text-gray-800 mb-1" id="geoEarliestCountry">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="geoEarliestDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#dcfce7;color:#15803d;">Early planner</span>
                </div>
                <div class="bg-white rounded-xl p-5 shadow-sm border-t-4" style="border-color:#E89A42;">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">LATEST BOOKER</p>
                    <h2 class="text-2xl font-bold text-gray-800 mb-1" id="geoLatestCountry">—</h2>
                    <p class="text-xs text-gray-400 mb-3" id="geoLatestDesc">—</p>
                    <span class="inline-block text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#fff3e0;color:#E89A42;">Last minute</span>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">

                <!-- Bookings by Country -->
                <div class="lg:col-span-2 bg-white rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-600">BOOKINGS BY COUNTRY</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Top 15 countries</p>
                        </div>
                        <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100"
                            data-chart="geoBookingsChart" data-title="BOOKINGS BY COUNTRY" title="Expand">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="relative" style="height:380px;">
                        <canvas id="geoBookingsChart"></canvas>
                    </div>
                </div>

                <!-- Lead Days Distribution -->
                <div class="bg-white rounded-xl p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-sm font-medium text-gray-600">BOOKING LEAD DAYS DISTRIBUTION</h3>
                        <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100"
                            data-chart="geoLeadDistChart" data-title="BOOKING LEAD DAYS DISTRIBUTION" title="Expand">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        </button>
                    </div>
                    <div class="relative flex items-center justify-center" style="height:280px;">
                        <canvas id="geoLeadDistChart"></canvas>
                    </div>
                    <!-- Lead Time Insights -->
                    <div class="rounded-xl p-3 mt-3" style="background:#f0f9ff;border:1px solid #bae6fd;">
                        <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#0369a1;">
                            &nbsp; LEAD TIME PATTERN
                        </p>
                        <ul id="geoLeadInsightsList" class="space-y-1.5">
                            <li class="text-xs text-gray-300 opacity-50">Loading...</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Avg Lead Days by Country -->
            <div class="bg-white rounded-xl p-5 shadow-sm mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-600">AVG BOOKING LEAD DAYS BY COUNTRY</h3>
                    <button type="button" class="chart-zoom-btn text-gray-400 hover:text-gray-600 p-1 rounded hover:bg-gray-100"
                        data-chart="geoLeadDaysChart" data-title="AVG BOOKING LEAD DAYS BY COUNTRY" title="Expand">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                    </button>
                </div>
                <div class="relative" style="height:340px;">
                    <canvas id="geoLeadDaysChart"></canvas>
                </div>
            </div>

            <!-- Geographic Insights -->
            <div class="rounded-xl p-4 mt-3" style="background:#f0f9ff;border:1px solid #bae6fd;">
                <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:#0369a1;">
                    &nbsp; GEOGRAPHIC PATTERN
                </p>
                <ul id="geoInsightsList" class="space-y-1.5">
                    <li class="text-xs text-gray-300 opacity-50">Loading...</li>
                </ul>
            </div>

            <!-- Top Countries Table -->
            <div class="bg-white rounded-xl shadow-sm mt-3 overflow-hidden">
                <div class="px-5 pt-5 pb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Top 15 Countries – Summary</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Bookings, tourists and avg lead days</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="geoTable">
                        <thead>
                            <tr style="background:#1e3a5f;">
                                <th class="text-left px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide w-10">#</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Country</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Bookings</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Tourists</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Avg Lead Days</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide">Booking Style</th>
                            </tr>
                        </thead>
                        <tbody id="geoTableBody">
                            <tr><td colspan="8" class="py-8 text-center text-gray-300 text-sm">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div><!-- /tab-geographic -->

        <!-- TAB PANEL: Insights -->
        <div id="tab-insights" class="hidden">

            <div id="insightsSections">
                <!-- Populated by JS -->
                <div class="flex items-center justify-center py-16 text-gray-300 text-sm">Loading insights…</div>
            </div>

            <!-- Key Patterns — Summary Table (populated by JS) -->
            <div id="keyPatternsSummary" class="mt-8 mb-4" style="display:none;">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-3 px-1">Key Patterns — Summary Table</p>
                <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="background:#1e3a5f;">
                                <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-white w-10">#</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-white">Pattern</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-white">Data Point</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-white">Scale</th>
                                <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-white">Period</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

        </div><!-- /tab-insights -->

        <!-- TAB PANEL: Affiliates -->
        <div id="tab-affiliates" class="hidden">

            <!-- Row 1: Gross, Refund, Total Net Collected -->
            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-3 gap-3 mb-3">
                <div class="bg-emerald-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">TOTAL GROSS</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affBookingAmount">—</h2>
                </div>
                <div class="bg-red-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">REFUND AMOUNT</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affRefundAmount">—</h2>
                </div>
                <div class="bg-teal-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">TOTAL NET COLLECTED</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affTotalNetCollected">—</h2>
                </div>
            </div>

            <!-- Row 2: Payment breakdown + TripAdvisor -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                <div class="bg-blue-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">NET CARD</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affNetCard">—</h2>
                </div>
                <div class="bg-green-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">NET CASH</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affNetCash">—</h2>
                </div>
                <div class="bg-purple-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">NET INVOICED</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affNetInvoiced">—</h2>
                </div>
                <div class="bg-yellow-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">NET TRIPADVISOR</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affNetTripAdvisor">—</h2>
                </div>
            </div>

            <!-- Row 3: Affiliate platforms, summary & cancellations -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                <div class="bg-rose-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">NET HEADOUT</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affNetHeadout">—</h2>
                </div>
                <div class="bg-amber-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">NET FAREHARBOR</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affNetFareHarbor">—</h2>
                </div>
                <div class="bg-indigo-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">TOTAL AFFILIATES</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affTotalAffiliates">—</h2>
                    <p class="text-xs text-gray-400 mt-1" id="affTotalBookings">— bookings</p>
                </div>
                <div class="bg-orange-50 rounded-xl p-5 shadow-sm">
                    <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide">CANCEL BOOKING</p>
                    <h2 class="text-2xl font-bold mt-3 text-gray-800" id="affCancelBookings">—</h2>
                </div>
            </div>

            <!-- Affiliates Booking Table -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden mt-1">
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="flex items-center gap-2 w-64 border border-gray-200 rounded-lg px-3 py-2 bg-white focus-within:border-gray-400">
                        <svg class="w-4 h-4 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                        </svg>
                        <input type="text" id="affTableSearch" placeholder="Search..."
                            class="flex-1 text-sm bg-transparent outline-none" />
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm" id="affTable">
                        <thead>
                            <tr style="background:#3b6fb3;">
                                <th class="text-left px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="id">ID <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="name">Name <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="package">Package <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="tourists">Tourists <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="booking_status">Booking Status <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="booking_amount">Booking Amount <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="refund_amount">Refund Amount <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                                <th class="text-right px-5 py-3 text-xs font-semibold text-white uppercase tracking-wide cursor-pointer select-none" data-sort-col="paid_amount">TOTAL NET <span class="aff-sort-icon" style="opacity:.4">↕</span></th>
                            </tr>
                        </thead>
                        <tbody id="affTableBody">
                            <tr><td colspan="8" class="py-8 text-center text-gray-300 text-sm">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="affPagination" class="hidden flex items-center justify-between px-4 py-3 border-t border-gray-100 text-sm text-gray-500">
                    <span id="affPagInfo"></span>
                    <div class="flex items-center gap-1" id="affPagButtons"></div>
                </div>
            </div>

        </div><!-- /tab-affiliates -->

        <!-- Chart Zoom Modal -->
        <div id="chartZoomModal" class="fixed inset-0 z-50 hidden flex-col bg-white" style="padding: 24px;">
            <div class="flex items-center justify-between mb-4 flex-shrink-0">
                <h3 id="chartZoomModalTitle" class="text-base font-semibold text-gray-700"></h3>
                <button type="button" id="chartZoomClose" class="text-gray-400 hover:text-gray-700 p-1.5 rounded hover:bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="relative flex-1" style="min-height:0;">
                <canvas id="chartZoomCanvas"></canvas>
            </div>
            <div id="chartZoomLegendPurchase" class="hidden justify-center gap-6 mt-4 text-sm flex-shrink-0">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded bg-[#5FBDB8]"></span>
                    <span class="text-[#5FBDB8]">Booked</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded bg-[#D9381E]"></span>
                    <span class="text-[#D9381E]">Cancelled / Refund</span>
                </div>
            </div>
            <div id="chartZoomLegendTourists" class="hidden justify-center gap-6 mt-4 text-sm flex-shrink-0">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded bg-[#E05C3A]"></span>
                    <span class="text-gray-600">Tourists</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded bg-[#5FBDB8]"></span>
                    <span class="text-gray-600">Bookings</span>
                </div>
            </div>
        </div>

    </div>
</section>
@endif
@endsection

@if (isPermissionGranted() == false)
@section('css-libraries')
<link href="{{ asset('plugins/daterangepicker-3.1/daterangepicker.css') }}" rel="stylesheet">
@endsection

@section('css-custom')
<link href="{{ asset('css/user-common/dashboard.css') }}" rel="stylesheet">
@endsection

@section('js-libraries')
<script src="{{ asset('plugins/chart.js-2.9.4/Chart.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js')}}"></script>
<script src="{{ asset('plugins/moment-timezone-with-data.min.js') }}"></script>
<script src="{{ asset('plugins/daterangepicker-3.1/daterangepicker.js')}}"></script>
@endsection

@section('js-custom')
<script>
    var dashboardMetricsUrl      = "{{ route('admin.dashboard.metrics') }}";
    var paymentGroupsMetricsUrl  = "{{ route('admin.dashboard.payment-groups') }}";
    var customersMetricsUrl      = "{{ route('admin.dashboard.customers') }}";
    var insightsMetricsUrl       = "{{ route('admin.dashboard.insights') }}";
    var geographicMetricsUrl     = "{{ route('admin.dashboard.geographic') }}";
    var affiliatesMetricsUrl     = "{{ route('admin.dashboard.affiliates') }}";
    var dashboardExportPdfUrl    = "{{ route('admin.dashboard.export-pdf') }}";
    var csrfToken                = "{{ csrf_token() }}";
    var timezone = "{{ $timezone }}";
    var bookingInfoUrl = "{{ route('admin.tour-operator.booking.show', [ 'booking' => 0 ]) }}";
</script>
<script src="{{ asset('js/user-common/dashboard-chart.js') }}"></script>
@endsection
@endif