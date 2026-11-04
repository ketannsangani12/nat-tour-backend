{{-- Payment Breakdown Report - HTML Content --}}
{{-- Uses Tailwind CSS for styling --}}

<!-- Filter Card -->
<div class="bg-white rounded-lg shadow-sm mb-4">
    <div class="px-4 py-3 border-b border-gray-200">
        <div class="flex items-center justify-between">
            <h5 class="text-base font-semibold text-gray-900">
                <i class="fas fa-filter mr-2 text-orange-500"></i>
                Report Filters
            </h5>
        </div>
    </div>

    <div id="pbFilterCollapse">
        <div class="p-4">
            <form id="pbFilterForm">
                <!-- Row 1: Date Range, Package, Affiliate, View By -->
                <div class="flex flex-wrap items-end gap-3 mb-3">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Date Range</label>
                        <input type="text"
                            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            id="pbDateRange"
                            placeholder="Select date range"
                            readonly>
                        <input type="hidden" id="pbDateFrom">
                        <input type="hidden" id="pbDateTo">
                        <input type="hidden" id="pbExcludeAllAffiliates" value="0">
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tour Package</label>
                        <div class="relative">
                            <button type="button" id="pbTourPackageBtn" 
                                class="w-full px-3 py-2 text-sm text-left border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white hover:bg-gray-50 flex items-center justify-between">
                                <span id="pbTourPackageLabel" class="truncate">All Packages</span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs ml-2"></i>
                            </button>
                            <div id="pbTourPackageDropdown" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-64 overflow-y-auto">
                                <div class="sticky top-0 bg-gray-50 border-b border-gray-200 p-2 flex gap-2">
                                    <button type="button" id="pbSelectAllPackages" class="flex-1 px-2 py-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded">
                                        Select All
                                    </button>
                                    <button type="button" id="pbDeselectAllPackages" class="flex-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded">
                                        Clear
                                    </button>
                                </div>
                                <div id="pbTourPackageList" class="p-1">
                                    <!-- Checkboxes will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Affiliate</label>
                        <div class="relative">
                            <button type="button" id="pbAffiliateBtn" 
                                class="w-full px-3 py-2 text-sm text-left border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white hover:bg-gray-50 flex items-center justify-between">
                                <span id="pbAffiliateLabel" class="truncate">All Affiliates</span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs ml-2"></i>
                            </button>
                            <div id="pbAffiliateDropdown" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-64 overflow-y-auto">
                                <div class="sticky top-0 bg-gray-50 border-b border-gray-200 p-2 flex gap-2">
                                    <button type="button" id="pbSelectAllAffiliates" class="flex-1 px-2 py-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded">
                                        Select All
                                    </button>
                                    <button type="button" id="pbExcludeAllAffiliatesBtn" class="flex-1 px-2 py-1 text-xs text-red-600 hover:text-red-700 hover:bg-red-50 rounded">
                                        Exclude All
                                    </button>
                                    <button type="button" id="pbDeselectAllAffiliates" class="flex-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded">
                                        Clear
                                    </button>
                                </div>
                                <div id="pbAffiliateList" class="p-1">
                                    <!-- Checkboxes will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">View By</label>
                        <select class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            id="pbViewBy">
                            <option value="tourdate">Tour Date</option>
                            <option value="createddate">Created Date</option>
                            <option value="purchasedate">Purchase Date</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Status Filters (Booking, Check-in, Waiver, Platform) -->
                <div class="flex flex-wrap items-end gap-3 mb-3">
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Booking Status</label>
                        <select class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            id="pbBookingStatus">
                            <option value="">All Statuses</option>
                            <option value="Booked">Booked</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Check-in Status</label>
                        <div class="relative">
                            <button type="button" id="pbCheckInStatusBtn" 
                                class="w-full px-3 py-2 text-sm text-left border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white hover:bg-gray-50 flex items-center justify-between">
                                <span id="pbCheckInStatusLabel" class="truncate">All Check-in Status</span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs ml-2"></i>
                            </button>
                            <div id="pbCheckInStatusDropdown" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-64 overflow-y-auto">
                                <div class="sticky top-0 bg-gray-50 border-b border-gray-200 p-2 flex gap-2">
                                    <button type="button" id="pbSelectAllCheckInStatus" class="flex-1 px-2 py-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded">
                                        Select All
                                    </button>
                                    <button type="button" id="pbDeselectAllCheckInStatus" class="flex-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded">
                                        Clear
                                    </button>
                                </div>
                                <div id="pbCheckInStatusList" class="p-1">
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                        <input type="checkbox" class="pb-checkin-status-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
                                            value="Not Check-In" data-name="Not Check-In">
                                        <span class="text-sm text-gray-700">Not Check-In</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                        <input type="checkbox" class="pb-checkin-status-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
                                            value="1st Check-in" data-name="1st Check-in">
                                        <span class="text-sm text-gray-700">1st Check-in</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                        <input type="checkbox" class="pb-checkin-status-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
                                            value="2nd Check-in" data-name="2nd Check-in">
                                        <span class="text-sm text-gray-700">2nd Check-in</span>
                                    </label>
                                    <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                        <input type="checkbox" class="pb-checkin-status-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" 
                                            value="Absent" data-name="Absent">
                                        <span class="text-sm text-gray-700">Absent</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex-1 min-w-[150px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Waiver Status</label>
                        <select class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            id="pbWaiverStatus">
                            <option value="">All Waiver Status</option>
                            <option value="Signed">Signed</option>
                            <option value="Not Signed">Not Signed</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[180px]">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Third Party Platform</label>
                        <input type="hidden" id="pbExcludeAllThirdParty" value="0">
                        <div class="relative">
                            <button type="button" id="pbThirdPartyBtn" 
                                class="w-full px-3 py-2 text-sm text-left border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white hover:bg-gray-50 flex items-center justify-between">
                                <span id="pbThirdPartyLabel" class="truncate">All Platforms</span>
                                <i class="fas fa-chevron-down text-gray-400 text-xs ml-2"></i>
                            </button>
                            <div id="pbThirdPartyDropdown" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-64 overflow-y-auto">
                                <div class="sticky top-0 bg-gray-50 border-b border-gray-200 p-2 flex gap-2">
                                    <button type="button" id="pbSelectAllThirdParty" class="flex-1 px-2 py-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded">
                                        Select All
                                    </button>
                                    <button type="button" id="pbExcludeAllThirdPartyBtn" class="flex-1 px-2 py-1 text-xs text-red-600 hover:text-red-700 hover:bg-red-50 rounded">
                                        Exclude All
                                    </button>
                                    <button type="button" id="pbDeselectAllThirdParty" class="flex-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded">
                                        Clear
                                    </button>
                                </div>
                                <div id="pbThirdPartyList" class="p-1">
                                    <!-- Checkboxes will be populated dynamically via JavaScript based on enabled platforms -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Payment Types (Inline) -->
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-medium text-gray-700">Payment Types</label>
                        <button type="button" 
                            id="pbToggleAdditionalFilters"
                            class="text-xs font-medium text-orange-600 hover:text-orange-700 flex items-center gap-1">
                            <i class="fas fa-sliders-h"></i>
                            <span>Additional Filters</span>
                            <i class="fas fa-chevron-down transition-transform duration-200" id="pbAdditionalFiltersIcon"></i>
                        </button>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex gap-2">
                            <button type="button"
                                class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                id="pbSelectAllPayments">
                                <i class="fas fa-check-square mr-1"></i>
                                Select All
                            </button>
                            <button type="button"
                                class="px-3 py-1 text-xs font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                id="pbDeselectAllPayments">
                                <i class="fas fa-square mr-1"></i>
                                Deselect All
                            </button>
                        </div>
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="pb-payment-type w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" value="Card" checked>
                                <span class="text-sm text-gray-700">Card</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="pb-payment-type w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" value="Cash" checked>
                                <span class="text-sm text-gray-700">Cash</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="pb-payment-type w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" value="Unpaid" checked>
                                <span class="text-sm text-gray-700">Unpaid</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="pb-payment-type w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" value="Invoice" checked>
                                <span class="text-sm text-gray-700">Invoice</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="pb-payment-type w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" value="Billable" checked>
                                <span class="text-sm text-gray-700">Billable</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" class="pb-payment-type w-4 h-4 text-orange-500 border-gray-300 rounded focus:ring-orange-500" value="Wire" checked>
                                <span class="text-sm text-gray-700">Wire</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Additional Filters (Number Filters + Soft Blocks + Reallocated Funds) - Hidden by default -->
                <div id="pbAdditionalFiltersSection" class="mb-3 hidden">
                    <!-- Number Filters Row -->
                    <div class="flex flex-wrap items-end gap-2 mb-3">
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Min Amount</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-2 text-xs text-gray-700 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                                    $
                                </span>
                                <input type="number"
                                    class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    id="pbMinAmount"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00">
                            </div>
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Max Amount</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-2 text-xs text-gray-700 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                                    $
                                </span>
                                <input type="number"
                                    class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    id="pbMaxAmount"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00">
                            </div>
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Rate</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-2 text-xs text-gray-700 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                                    $
                                </span>
                                <input type="number"
                                    class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    id="pbRate"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00">
                            </div>
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Permit Fee</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-2 text-xs text-gray-700 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                                    $
                                </span>
                                <input type="number"
                                    class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    id="pbPermitFee"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00">
                            </div>
                        </div>
                        <div class="flex-1 min-w-[120px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Tax</label>
                            <div class="flex">
                                <span class="inline-flex items-center px-2 text-xs text-gray-700 bg-gray-100 border border-r-0 border-gray-300 rounded-l-md">
                                    $
                                </span>
                                <input type="number"
                                    class="flex-1 px-2 py-2 text-sm border border-gray-300 rounded-r-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                    id="pbTax"
                                    min="0"
                                    step="0.01"
                                    placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <!-- Soft Blocks and Reallocated Funds Row (Inline) -->
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Soft Blocks</label>
                            <select class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                id="pbSoftBlocks">
                                <option value="">All Bookings</option>
                                <option value="1">Soft Blocks Only</option>
                                <option value="0">Exclude Soft Blocks</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Reallocated Funds</label>
                            <select class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                id="pbReallocatedFunds">
                                <option value="">All Bookings</option>
                                <option value="0">Exclude Reallocated Funds</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block text-xs font-medium text-gray-700 mb-1">Charge Type</label>
                            <div class="relative">
                                <button type="button" id="pbChargeTypeBtn"
                                    class="w-full px-3 py-2 text-sm text-left border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500 bg-white hover:bg-gray-50 flex items-center justify-between">
                                    <span id="pbChargeTypeLabel" class="truncate">All Charge Types</span>
                                    <i class="fas fa-chevron-down text-gray-400 text-xs ml-2"></i>
                                </button>
                                <div id="pbChargeTypeDropdown" class="hidden absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-64 overflow-y-auto">
                                    <div class="sticky top-0 bg-gray-50 border-b border-gray-200 p-2 flex gap-2">
                                        <button type="button" id="pbSelectAllChargeTypes" class="flex-1 px-2 py-1 text-xs text-orange-600 hover:text-orange-700 hover:bg-orange-50 rounded">
                                            Select All
                                        </button>
                                        <button type="button" id="pbDeselectAllChargeTypes" class="flex-1 px-2 py-1 text-xs text-gray-600 hover:text-gray-700 hover:bg-gray-100 rounded">
                                            Clear
                                        </button>
                                    </div>
                                    <div id="pbChargeTypeList" class="p-1">
                                        <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                            <input type="checkbox" class="pb-charge-type-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" value="tours">
                                            <span class="text-sm text-gray-700">Tours</span>
                                        </label>
                                        <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                            <input type="checkbox" class="pb-charge-type-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" value="custom">
                                            <span class="text-sm text-gray-700">Custom</span>
                                        </label>
                                        <label class="flex items-center px-3 py-2 hover:bg-gray-50 cursor-pointer rounded">
                                            <input type="checkbox" class="pb-charge-type-checkbox rounded border-gray-300 text-orange-600 focus:ring-orange-500 mr-2" value="addons">
                                            <span class="text-sm text-gray-700">Add-ons</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Action Buttons -->
                <div class="flex items-center justify-between gap-2 mt-4 pt-3 border-t border-gray-200">
                    <div class="flex gap-2">
                        <button type="submit"
                            class="px-4 py-2 text-sm bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-md transition-colors">
                            <i class="fas fa-search mr-1"></i>
                            Generate Report
                        </button>
                        <button type="button"
                            class="px-4 py-2 text-sm bg-white hover:bg-gray-50 text-gray-700 font-medium border border-gray-300 rounded-md transition-colors"
                            id="pbClearFilters">
                            <i class="fas fa-times mr-1"></i>
                            Clear
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button"
                            class="px-4 py-2 text-sm bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-md transition-colors"
                            id="pbSavePresetBtn">
                            <i class="fas fa-save mr-1"></i>
                            Save Preset
                        </button>
                        <div class="relative">
                            <button type="button"
                                class="px-4 py-2 text-sm bg-white hover:bg-gray-50 text-gray-700 font-medium border border-gray-300 rounded-md transition-colors flex items-center gap-2"
                                id="pbLoadPresetBtn">
                                <i class="fas fa-folder-open"></i>
                                Load Preset
                                <i class="fas fa-chevron-down text-xs"></i>
                            </button>
                            <div id="pbPresetDropdown" class="absolute right-0 mt-1 w-64 bg-white border border-gray-300 rounded-md shadow-lg z-50 hidden">
                                <div class="max-h-64 overflow-y-auto">
                                    <div id="pbPresetList" class="py-1">
                                        <!-- Populated via JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Save Preset Modal -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 hidden" id="pbSavePresetModal" tabindex="-1" aria-labelledby="pbSavePresetModalLabel" style="background-color: rgba(0, 0, 0, 0.75);">
    <div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
            <div class="modal-header border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h5 class="modal-title text-lg font-semibold text-gray-900 m-0" id="pbSavePresetModalLabel">Save Filter Preset</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none ml-auto" data-bs-dismiss="modal" aria-label="Close" id="pbCloseSaveModalBtn">&times;</button>
            </div>
            <div class="modal-body px-6 py-4">
                <div class="mb-4">
                    <label for="pbPresetNameInput" class="block text-sm font-medium text-gray-700 mb-2">Preset Name</label>
                    <input type="text"
                        id="pbPresetNameInput"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Enter a name for this preset"
                        maxlength="100">
                    <p class="mt-1 text-xs text-gray-500">Give your filter preset a memorable name (max 100 characters)</p>
                </div>
            </div>
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-2">
                <button type="button"
                    class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md transition-colors"
                    id="pbCancelSaveBtn">
                    Cancel
                </button>
                <button type="button"
                    class="px-4 py-2 text-sm bg-blue-500 hover:bg-blue-600 text-white font-medium rounded-md transition-colors"
                    id="pbConfirmSaveBtn">
                    <i class="fas fa-save mr-1"></i>
                    Save Preset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Confirm Overwrite Modal -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 hidden" id="pbConfirmOverwriteModal" tabindex="-1" aria-labelledby="pbConfirmOverwriteModalLabel" style="background-color: rgba(0, 0, 0, 0.75);">
    <div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
            <div class="modal-header border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h5 class="modal-title text-lg font-semibold text-gray-900 m-0" id="pbConfirmOverwriteModalLabel">Preset Already Exists</h5>
                <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none ml-auto" data-bs-dismiss="modal" aria-label="Close" id="pbCloseOverwriteModalBtn">&times;</button>
            </div>
            <div class="modal-body px-6 py-4">
                <p class="text-sm text-gray-700">A preset with this name already exists. Do you want to overwrite it?</p>
                <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle text-yellow-600 mr-2 mt-0.5"></i>
                        <p class="text-xs text-yellow-800">This action will replace the existing preset with your current filter settings.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-t border-gray-200 px-6 py-4 flex justify-end gap-2">
                <button type="button"
                    class="px-4 py-2 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-md transition-colors"
                    id="pbCancelOverwriteBtn">
                    Cancel
                </button>
                <button type="button"
                    class="px-4 py-2 text-sm bg-yellow-500 hover:bg-yellow-600 text-white font-medium rounded-md transition-colors"
                    id="pbConfirmOverwriteBtn">
                    <i class="fas fa-exclamation-triangle mr-1"></i>
                    Overwrite Preset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Loading Indicator -->
<div id="pbLoadingIndicator" class="text-center py-12 hidden">
    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-orange-500"></div>
    <p class="mt-4 text-gray-600">Generating payment breakdown report...</p>
</div>

<!-- Results Container -->
<div id="pbResultsContainer" class="hidden">
    <!-- Report Header - For Print/Export -->
    <div id="pbReportHeader" class="bg-white rounded-lg shadow-sm mb-4 p-4 print:shadow-none">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h4 class="text-lg font-bold text-gray-900">
                    <i class="fas fa-chart-pie mr-2 text-orange-500"></i>
                    Payment Breakdown Report
                </h4>
                <p id="pbReportDateRange" class="text-sm text-gray-600 mt-1"></p>
            </div>
            <div class="flex items-center gap-3">
                <span id="pbReportGeneratedAt" class="text-xs text-gray-500"></span>
                <button type="button" id="pbExportBtnTop"
                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-lg transition-colors shadow-sm flex items-center gap-2"
                    onclick="$('#pbExportModal').removeClass('hidden').css('display', 'flex');">
                    <i class="fas fa-download"></i>
                    Export Report
                </button>
            </div>
        </div>
        <div id="pbAppliedFilters" class="text-xs text-gray-500 border-t border-gray-200 pt-2 mt-2">
            <!-- Applied filters will be populated here -->
        </div>
    </div>

    <!-- Summary Cards - Inline Layout -->
    <div class="flex flex-wrap gap-3 mb-4">
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Bookings</p>
                    <h3 id="pbTotalBookings" class="text-xl font-bold text-gray-900">0</h3>
                </div>
                <i class="fas fa-calendar-check text-2xl text-blue-500 opacity-50"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Tourists</p>
                    <h3 id="pbTotalPax" class="text-xl font-bold text-gray-900">0</h3>
                </div>
                <i class="fas fa-users text-2xl text-cyan-500 opacity-50"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Revenue</p>
                    <h3 id="pbTotalRevenue" class="text-xl font-bold text-gray-900">$0</h3>
                </div>
                <i class="fas fa-dollar-sign text-2xl text-green-500 opacity-50"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Refunds</p>
                    <h3 id="pbTotalRefunds" class="text-xl font-bold text-gray-900">$0</h3>
                </div>
                <i class="fas fa-undo text-2xl text-red-500 opacity-50"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Fees</p>
                    <h3 id="pbTotalFees" class="text-xl font-bold text-gray-900">$0</h3>
                </div>
                <i class="fas fa-percentage text-2xl text-yellow-500 opacity-50"></i>
            </div>
        </div>
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Discount</p>
                    <h3 id="pbTotalDiscount" class="text-xl font-bold text-gray-900">$0</h3>
                </div>
                <i class="fas fa-tags text-2xl text-gray-500 opacity-50"></i>
            </div>
        </div>
        @if(hasSetting('DISPUTE_REVERSAL'))
        <div class="bg-white rounded-lg p-3 shadow-sm hover:shadow-md transition-shadow flex-1 min-w-[150px]">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Disputes</p>
                    <h3 id="pbTotalDisputes" class="text-xl font-bold text-gray-900">$0</h3>
                </div>
                <i class="fas fa-gavel text-2xl text-red-400 opacity-50"></i>
            </div>
        </div>
        @endif
    </div>

    <!-- Net Total Highlight Card -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg p-6 text-center mb-4 shadow-lg" style="background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);">
        <div class="flex items-center justify-center mb-2">
            <div class="bg-white bg-opacity-30 rounded-full p-3" style="background-color: rgba(255, 255, 255, 0.3);">
                <i class="fas fa-calculator text-2xl" style="color: #ffffff !important; font-size: 1.5rem;"></i>
            </div>
        </div>
        <p class="text-sm font-bold uppercase tracking-wider mb-2" style="color: #ffffff !important; font-size: 0.875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Total Net Amount</p>
        <h1 id="pbTotalNet" class="text-5xl font-extrabold mb-2" style="color: #ffffff !important; font-size: 3rem; font-weight: 900; text-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">$0</h1>
        <p class="text-sm font-medium" style="color: #ffffff !important; font-size: 0.875rem; font-weight: 500;">After deducting fees, refunds{{ hasSetting('DISPUTE_REVERSAL') ? ', and dispute reversals' : '' }}</p>
    </div>

    <!-- Check-in Status Cards - Inline Layout -->
    <div class="mb-4">
        <h5 class="text-sm font-semibold text-gray-900 mb-3">
            <i class="fas fa-clipboard-check mr-2 text-orange-500"></i>
            Check-in Status Overview
        </h5>
        <div style="display: flex; flex-wrap: wrap; gap: 12px;">
            <div class="bg-white border-l-4 border-green-500 rounded-lg p-3 shadow-sm" style="flex: 1 1 0; min-width: 150px;">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 id="pbCheckedIn2nd" class="text-xl font-bold text-green-600">0</h4>
                        <p class="text-xs text-gray-500 mt-1">2nd Check-in</p>
                    </div>
                    <i class="fas fa-check-double text-2xl text-green-500 opacity-50"></i>
                </div>
            </div>
            <div class="bg-white border-l-4 border-yellow-500 rounded-lg p-3 shadow-sm" style="flex: 1 1 0; min-width: 150px;">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 id="pbCheckedIn1st" class="text-xl font-bold text-yellow-600">0</h4>
                        <p class="text-xs text-gray-500 mt-1">1st Check-in</p>
                    </div>
                    <i class="fas fa-check text-2xl text-yellow-500 opacity-50"></i>
                </div>
            </div>
            <div class="bg-white border-l-4 border-gray-500 rounded-lg p-3 shadow-sm" style="flex: 1 1 0; min-width: 150px;">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 id="pbNotCheckedIn" class="text-xl font-bold text-gray-600">0</h4>
                        <p class="text-xs text-gray-500 mt-1">Not Check-In</p>
                    </div>
                    <i class="fas fa-clock text-2xl text-gray-500 opacity-50"></i>
                </div>
            </div>
            <div class="bg-white border-l-4 border-red-500 rounded-lg p-3 shadow-sm" style="flex: 1 1 0; min-width: 150px;">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 id="pbAbsent" class="text-xl font-bold text-red-600">0</h4>
                        <p class="text-xs text-gray-500 mt-1">Absent</p>
                    </div>
                    <i class="fas fa-times text-2xl text-red-500 opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section - Inline Layout -->
    <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
        <div class="bg-white rounded-lg p-4 shadow-sm" style="flex: 1 1 0; min-width: 280px;">
            <h6 class="text-sm font-semibold text-gray-900 mb-3">Payment Type Distribution</h6>
            <canvas id="pbPaymentTypeChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm" style="flex: 1 1 0; min-width: 280px;">
            <h6 class="text-sm font-semibold text-gray-900 mb-3">Revenue vs Refunds</h6>
            <canvas id="pbRevenueRefundChart" height="200"></canvas>
        </div>
        <div class="bg-white rounded-lg p-4 shadow-sm" style="flex: 1 1 0; min-width: 280px;">
            <h6 class="text-sm font-semibold text-gray-900 mb-3">Check-in Status</h6>
            <canvas id="pbCheckInChart" height="200"></canvas>
        </div>
    </div>

    <!-- Tabs for Summary and Payment Breakdown -->
    <div class="bg-white rounded-lg shadow-sm mb-4">
        <!-- Tab Headers -->
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px">
                <button type="button"
                    id="pbSummaryTab"
                    class="pb-tab-button px-6 py-3 text-sm font-semibold border-b-2 border-orange-500 text-orange-600 transition-colors"
                    onclick="switchPaymentBreakdownTab('summary')">
                    <i class="fas fa-chart-pie mr-2"></i>
                    Summary Breakdown Details
                </button>
                <button type="button"
                    id="pbPaymentTab"
                    class="pb-tab-button px-6 py-3 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 transition-colors"
                    onclick="switchPaymentBreakdownTab('payment')">
                    <i class="fas fa-table mr-2"></i>
                    Payment Breakdown Details
                </button>
            </nav>
        </div>

        <!-- Tab Content: Summary Breakdown Details -->
        <div id="pbSummaryTabContent" class="p-4">
            <!-- Payment Type Breakdown Section -->
            <div class="mb-6">
                <h6 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-credit-card mr-2 text-blue-500"></i>
                    Payment Type Breakdown
                </h6>

                <!-- Credit Card Payments -->
                <div id="pbSummaryCardSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #3b82f6;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">Credit Card Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #dbeafe;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #1e40af;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #1e40af;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #1e40af;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #1e40af;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #1e40af;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #1e40af;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryCardBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #dbeafe; border-top: 2px solid #3b82f6;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #1e40af;">TOTAL</td>
                                    <td id="pbSummaryCardTotalPax" class="px-3 py-2 text-center" style="color: #1e40af;">0</td>
                                    <td id="pbSummaryCardTotalRate" class="px-3 py-2 text-right" style="color: #1e40af;">$0.00</td>
                                    <td id="pbSummaryCardTotalTax" class="px-3 py-2 text-right" style="color: #1e40af;">$0.00</td>
                                    <td id="pbSummaryCardTotalPermit" class="px-3 py-2 text-right" style="color: #1e40af;">$0.00</td>
                                    <td id="pbSummaryCardTotalAmount" class="px-3 py-2 text-right" style="color: #1e40af;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryCardNotes" class="mt-2 text-xs text-gray-600">
                        <!-- Notes populated via JavaScript -->
                    </div>
                </div>

                <!-- Affiliate/Billable Payments -->
                <div id="pbSummaryBillableSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #8b5cf6;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">Affiliate/Billable Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #f3e8ff;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #6b21a8;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #6b21a8;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #6b21a8;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #6b21a8;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #6b21a8;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #6b21a8;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryBillableBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #f3e8ff; border-top: 2px solid #8b5cf6;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #6b21a8;">TOTAL</td>
                                    <td id="pbSummaryBillableTotalPax" class="px-3 py-2 text-center" style="color: #6b21a8;">0</td>
                                    <td id="pbSummaryBillableTotalRate" class="px-3 py-2 text-right" style="color: #6b21a8;">$0.00</td>
                                    <td id="pbSummaryBillableTotalTax" class="px-3 py-2 text-right" style="color: #6b21a8;">$0.00</td>
                                    <td id="pbSummaryBillableTotalPermit" class="px-3 py-2 text-right" style="color: #6b21a8;">$0.00</td>
                                    <td id="pbSummaryBillableTotalAmount" class="px-3 py-2 text-right" style="color: #6b21a8;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryBillableNotes" class="mt-2 text-xs text-gray-600">
                        <!-- Notes populated via JavaScript -->
                    </div>
                </div>

                <!-- Invoiced Payments -->
                <div id="pbSummaryInvoicedSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #f59e0b;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">Invoiced Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #fef3c7;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #92400e;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #92400e;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #92400e;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #92400e;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #92400e;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #92400e;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryInvoicedBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #fef3c7; border-top: 2px solid #f59e0b;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #92400e;">TOTAL</td>
                                    <td id="pbSummaryInvoicedTotalPax" class="px-3 py-2 text-center" style="color: #92400e;">0</td>
                                    <td id="pbSummaryInvoicedTotalRate" class="px-3 py-2 text-right" style="color: #92400e;">$0.00</td>
                                    <td id="pbSummaryInvoicedTotalTax" class="px-3 py-2 text-right" style="color: #92400e;">$0.00</td>
                                    <td id="pbSummaryInvoicedTotalPermit" class="px-3 py-2 text-right" style="color: #92400e;">$0.00</td>
                                    <td id="pbSummaryInvoicedTotalAmount" class="px-3 py-2 text-right" style="color: #92400e;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryInvoicedNotes" class="mt-2 text-xs text-gray-600">
                        <!-- Notes populated via JavaScript -->
                    </div>
                </div>

                <!-- Cash Payments -->
                <div id="pbSummaryCashSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #10b981;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">Cash Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #d1fae5;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #065f46;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #065f46;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #065f46;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #065f46;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #065f46;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #065f46;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryCashBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #d1fae5; border-top: 2px solid #10b981;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #065f46;">TOTAL</td>
                                    <td id="pbSummaryCashTotalPax" class="px-3 py-2 text-center" style="color: #065f46;">0</td>
                                    <td id="pbSummaryCashTotalRate" class="px-3 py-2 text-right" style="color: #065f46;">$0.00</td>
                                    <td id="pbSummaryCashTotalTax" class="px-3 py-2 text-right" style="color: #065f46;">$0.00</td>
                                    <td id="pbSummaryCashTotalPermit" class="px-3 py-2 text-right" style="color: #065f46;">$0.00</td>
                                    <td id="pbSummaryCashTotalAmount" class="px-3 py-2 text-right" style="color: #065f46;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryCashNotes" class="mt-2 text-xs text-gray-600">
                        <!-- Notes populated via JavaScript -->
                    </div>
                </div>

                <!-- Headout Payments -->
                <div id="pbSummaryHeadoutSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #ec4899;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">Headout Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #f3f4f6;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #374151;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #374151;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryHeadoutBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #f3f4f6; border-top: 2px solid #6b7280;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #374151;">TOTAL</td>
                                    <td id="pbSummaryHeadoutTotalPax" class="px-3 py-2 text-center" style="color: #374151;">0</td>
                                    <td id="pbSummaryHeadoutTotalRate" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryHeadoutTotalTax" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryHeadoutTotalPermit" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryHeadoutTotalAmount" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryHeadoutNotes" class="mt-2 text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Third-party platform bookings</span>
                    </div>
                </div>

                <!-- TripAdvisor Payments -->
                <div id="pbSummaryTripAdvisorSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #14b8a6;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">TripAdvisor Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #f3f4f6;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #374151;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #374151;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryTripAdvisorBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #f3f4f6; border-top: 2px solid #6b7280;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #374151;">TOTAL</td>
                                    <td id="pbSummaryTripAdvisorTotalPax" class="px-3 py-2 text-center" style="color: #374151;">0</td>
                                    <td id="pbSummaryTripAdvisorTotalRate" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryTripAdvisorTotalTax" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryTripAdvisorTotalPermit" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryTripAdvisorTotalAmount" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryTripAdvisorNotes" class="mt-2 text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Third-party platform bookings</span>
                    </div>
                </div>

                <!-- GetYourGuide Payments -->
                <div id="pbSummaryGetYourGuideSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #8b5cf6;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">GetYourGuide Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #f3f4f6;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #374151;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #374151;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryGetYourGuideBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #f3f4f6; border-top: 2px solid #6b7280;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #374151;">TOTAL</td>
                                    <td id="pbSummaryGetYourGuideTotalPax" class="px-3 py-2 text-center" style="color: #374151;">0</td>
                                    <td id="pbSummaryGetYourGuideTotalRate" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryGetYourGuideTotalTax" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryGetYourGuideTotalPermit" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryGetYourGuideTotalAmount" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryGetYourGuideNotes" class="mt-2 text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Third-party platform bookings</span>
                    </div>
                </div>

                <!-- FareHarbor Payments -->
                <div id="pbSummaryFareHarborSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #f97316;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">FareHarbor Payments</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #f3f4f6;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #374151;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #374151;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryFareHarborBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #f3f4f6; border-top: 2px solid #6b7280;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #374151;">TOTAL</td>
                                    <td id="pbSummaryFareHarborTotalPax" class="px-3 py-2 text-center" style="color: #374151;">0</td>
                                    <td id="pbSummaryFareHarborTotalRate" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryFareHarborTotalTax" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryFareHarborTotalPermit" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryFareHarborTotalAmount" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryFareHarborNotes" class="mt-2 text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Third-party platform bookings</span>
                    </div>
                </div>

                <!-- Unpaid/Free -->
                <div id="pbSummaryUnpaidSection" class="mb-6" style="display: none;">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-3 h-3 rounded-full" style="background-color: #6b7280;"></div>
                        <h6 class="text-sm font-semibold text-gray-700">No Payment Collected</h6>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs border border-gray-200 rounded-lg">
                            <thead style="background-color: #f3f4f6;">
                                <tr>
                                    <th class="px-3 py-2 text-left font-semibold border-b" style="color: #374151;">Rate Group</th>
                                    <th class="px-3 py-2 text-center font-semibold border-b" style="color: #374151;">Tourist</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Rate</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Tax</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Permit</th>
                                    <th class="px-3 py-2 text-right font-semibold border-b" style="color: #374151;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="pbSummaryUnpaidBody">
                                <!-- Populated via JavaScript -->
                            </tbody>
                            <tfoot style="background-color: #f3f4f6; border-top: 2px solid #6b7280;">
                                <tr class="font-semibold">
                                    <td class="px-3 py-2 text-left" style="color: #374151;">TOTAL</td>
                                    <td id="pbSummaryUnpaidTotalPax" class="px-3 py-2 text-center" style="color: #374151;">0</td>
                                    <td id="pbSummaryUnpaidTotalRate" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryUnpaidTotalTax" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryUnpaidTotalPermit" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                    <td id="pbSummaryUnpaidTotalAmount" class="px-3 py-2 text-right" style="color: #374151;">$0.00</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div id="pbSummaryUnpaidNotes" class="mt-2 text-xs text-gray-600">
                        <i class="fas fa-info-circle mr-1"></i>
                        <span>Exempt from tax and permit fees</span>
                    </div>
                </div>
            </div>

            <!-- Financial Summary Section -->
            <div class="mb-6">
                <h6 class="text-sm font-semibold text-gray-800 mb-4 flex items-center">
                    <i class="fas fa-calculator mr-2 text-green-500"></i>
                    Financial Summary
                </h6>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <!-- Revenue Breakdown Card -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gradient-to-br from-green-50 to-white">
                        <h6 class="text-xs font-semibold text-gray-700 mb-3">Revenue Components</h6>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Subtotal Revenue:</span>
                                <span id="pbFinancialSubtotal" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Tax Collected:</span>
                                <span id="pbFinancialTax" class="font-semibold text-amber-600">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Permit Fees:</span>
                                <span id="pbFinancialPermit" class="font-semibold text-purple-600">$0.00</span>
                            </div>
                            <div class="border-t border-gray-200 pt-2 flex justify-between items-center text-xs">
                                <span class="font-medium text-gray-700">Gross Total:</span>
                                <span id="pbFinancialGrossTotal" class="font-semibold text-gray-800">$0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Custom Charge:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowCustomChargeModal" class="text-green-500 hover:text-green-700 transition-colors" title="View custom charge bookings">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialCustomCharge" class="font-semibold text-green-600">$0.00</span>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Overpayment:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowOverpaymentModal" class="text-green-500 hover:text-green-700 transition-colors" title="View overpayment bookings">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialOverpayment" class="font-semibold text-green-600">$0.00</span>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Prior Payments:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowPriorPaymentsModal" class="text-blue-500 hover:text-blue-700 transition-colors" title="View prior period payments">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialPriorPayments" class="font-semibold text-red-600">$0.00</span>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Refunded:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowRefundedModal" class="text-red-500 hover:text-red-700 transition-colors" title="View refunded bookings">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialRefunded" class="font-semibold text-red-600">$0.00</span>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Unpaid:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowUnpaidModal" class="text-yellow-500 hover:text-yellow-700 transition-colors" title="View unpaid bookings">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialUnpaid" class="font-semibold text-red-600">$0.00</span>
                                </span>
                            </div>
                            <div id="pbOtherPeriodPaymentsRow" class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Other Period:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowOtherPeriodPaymentsModal" class="text-orange-500 hover:text-orange-700 transition-colors" title="View other period payments">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialOtherPeriodPayments" class="font-semibold text-red-600">$0.00</span>
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Discount:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowDiscountModal" class="text-purple-500 hover:text-purple-700 transition-colors" title="View discount bookings">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialDiscount" class="font-semibold text-red-600">$0.00</span>
                                </span>
                            </div>
                            @if(hasSetting('DISPUTE_REVERSAL'))
                            <div id="pbFinancialDisputeReversalsRow" class="flex justify-between items-center text-xs">
                                <span class="text-gray-600">Dispute Reversals:</span>
                                <span class="flex items-center gap-1">
                                    <button id="pbShowDisputeReversalsModal" class="text-red-400 hover:text-red-600 transition-colors" title="View dispute reversals">
                                        <i class="fas fa-info-circle text-sm"></i>
                                    </button>
                                    <span id="pbFinancialDisputeReversals" class="font-semibold text-red-600">$0.00</span>
                                </span>
                            </div>
                            @endif
                            <div class="border-t border-gray-300 pt-2 flex justify-between items-center text-sm">
                                <span class="font-semibold text-gray-800">Net Total:</span>
                                <span id="pbFinancialGrandTotal" class="font-bold text-green-600 text-base">$0.00</span>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-200">
                            <p class="text-xs text-gray-500">
                                <i class="fas fa-info-circle mr-1"></i>
                                Net = Gross - Discount + Custom Charge + Overpayment - Prior Payments - Refunded - Unpaid - Other Period.{{ hasSetting('DISPUTE_REVERSAL') ? ' Dispute Reversals are shown separately as a period-level deduction.' : '' }}
                            </p>
                        </div>
                    </div>

                    <!-- By Payment Type Card -->
                    <div class="border border-gray-200 rounded-lg p-4 bg-gradient-to-br from-blue-50 to-white">
                        <h6 class="text-xs font-semibold text-gray-700 mb-3">By Payment Type</h6>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-1 text-gray-600 font-medium">Type</th>
                                        <th class="text-center py-1 text-gray-600 font-medium">Tourist</th>
                                        <th class="text-right py-1 text-gray-600 font-medium">Gross</th>
                                        <th class="text-right py-1 text-gray-600 font-medium">Net</th>
                                    </tr>
                                </thead>
                                <tbody id="pbFinancialByPaymentType">
                                    <!-- Populated via JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tax & Permit Info Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Tax Information -->
                    <div class="border border-amber-200 rounded-lg p-4 bg-amber-50 flex flex-col">
                        <h6 class="text-xs font-semibold text-amber-800 mb-3 flex items-center">
                            <i class="fas fa-percentage mr-2"></i>
                            Tax Information
                        </h6>
                        <div class="flex-1"></div>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-700">Tax Rate:</span>
                                <span id="pbTaxRate" class="font-semibold text-gray-900">0.0%</span>
                            </div>
                            <div class="flex justify-between hidden">
                                <span class="text-gray-700">Taxable Amount:</span>
                                <span id="pbTaxableAmount" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="flex justify-between border-t border-amber-200 pt-2">
                                <span class="text-gray-700 font-medium">Total Tax Collected:</span>
                                <span id="pbTotalTaxCollected" class="font-bold text-amber-700">$0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Permit Fee Information -->
                    <div class="border border-purple-200 rounded-lg p-4 bg-purple-50">
                        <h6 class="text-xs font-semibold text-purple-800 mb-3 flex items-center justify-between">
                            <span class="flex items-center">
                                <i class="fas fa-ticket-alt mr-2"></i>
                                Permit Fee Information
                            </span>
                            <button id="pbPermitFeeBreakdownBtn" 
                                    class="text-gray-500 hover:text-orange-500 transition-colors cursor-pointer"
                                    title="View breakdown by check-in status">
                                <i class="fas fa-cog text-base"></i>
                            </button>
                        </h6>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-gray-700">Permit Fee per Tourist:</span>
                                <span id="pbPermitFeePerPax" class="font-semibold text-gray-900">$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-700">Total Tourists with Permit:</span>
                                <span id="pbTotalPaxWithPermit" class="font-semibold text-gray-900">0</span>
                            </div>
                            <div class="flex justify-between border-t border-purple-200 pt-2">
                                <span class="text-gray-700 font-medium">Total Permit Fees:</span>
                                <span id="pbTotalPermitFees" class="font-bold text-purple-700">$0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Content: Payment Breakdown Details -->
        <div id="pbPaymentTabContent" class="hidden">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <div>
                    <h5 class="text-sm font-semibold text-gray-900">
                        <i class="fas fa-table mr-2 text-orange-500"></i>
                        Payment Breakdown Details
                    </h5>
                    <small id="pbRecordCount" class="text-xs text-gray-500">Showing 0 records</small>
                </div>
                <div class="flex gap-2">
                    <!-- Column Visibility Dropdown -->
                    <div class="relative inline-block">
                        <button type="button"
                            class="px-3 py-1.5 text-sm bg-white hover:bg-gray-50 text-gray-700 font-medium border border-gray-300 rounded-md transition-colors flex items-center gap-1"
                            onclick="document.getElementById('pbColumnDropdown').classList.toggle('hidden')">
                            <i class="fas fa-columns"></i>
                            Columns
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div id="pbColumnDropdown" class="hidden absolute right-0 mt-1 w-56 bg-white border border-gray-300 rounded-md shadow-lg z-50 max-h-96 overflow-y-auto">
                            <div class="p-2">
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="booking-id" checked>
                                    <span class="text-sm text-gray-700">Booking ID</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="tour-date" checked>
                                    <span class="text-sm text-gray-700">Tour Date</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="tour-time">
                                    <span class="text-sm text-gray-700">Tour Time</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="created-date">
                                    <span class="text-sm text-gray-700">Created Date</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="purchase-date">
                                    <span class="text-sm text-gray-700">Purchase Date</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="tour-package">
                                    <span class="text-sm text-gray-700">Tour Package</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="customer" checked>
                                    <span class="text-sm text-gray-700">Customer</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="customer-email">
                                    <span class="text-sm text-gray-700">Email</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="customer-phone">
                                    <span class="text-sm text-gray-700">Phone</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="payment-type" checked>
                                    <span class="text-sm text-gray-700">Payment Type</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="payment" checked>
                                    <span class="text-sm text-gray-700">Payment Total</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="unpaid">
                                    <span class="text-sm text-gray-700">Unpaid Total</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="fees" checked>
                                    <span class="text-sm text-gray-700">Fee Total</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="refund" checked>
                                    <span class="text-sm text-gray-700">Refund Total</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="discount" checked>
                                    <span class="text-sm text-gray-700">Total Discount</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="net" checked>
                                    <span class="text-sm text-gray-700">Total Net</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="pax">
                                    <span class="text-sm text-gray-700">Tourist</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="rate">
                                    <span class="text-sm text-gray-700">Rate</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="tax">
                                    <span class="text-sm text-gray-700">Tax</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="permit-fee">
                                    <span class="text-sm text-gray-700">Permit Fee</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="status" checked>
                                    <span class="text-sm text-gray-700">Status</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="checkin">
                                    <span class="text-sm text-gray-700">Check-in</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="waiver">
                                    <span class="text-sm text-gray-700">Waiver</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="platform">
                                    <span class="text-sm text-gray-700">Platform</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="affiliate">
                                    <span class="text-sm text-gray-700">Affiliate</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="reallocated">
                                    <span class="text-sm text-gray-700">Reallocated Fund</span>
                                </label>
                                <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-gray-50 rounded cursor-pointer">
                                    <input type="checkbox" class="pb-column-toggle w-4 h-4 text-orange-500 border-gray-300 rounded" data-column="soft-block">
                                    <span class="text-sm text-gray-700">Soft Block</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Export Button -->
                    <button type="button"
                        class="px-3 py-1.5 text-sm bg-orange-500 hover:bg-orange-600 text-white font-medium rounded-md transition-colors"
                        onclick="$('#pbExportModal').removeClass('hidden').css('display', 'flex');">
                        <i class="fas fa-download mr-1"></i>
                        Export
                    </button>
                </div>
            </div>

            <!-- Table Controls -->
            <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700">Show:</label>
                        <select class="px-2 py-1 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            id="pbPageSize">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100" selected>100</option>
                        </select>
                        <span class="text-sm text-gray-700">entries</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-sm text-gray-700">Search:</label>
                        <input type="text"
                            class="px-3 py-1 text-sm border border-gray-300 rounded-md focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                            id="pbTableSearch"
                            placeholder="Search in table...">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="pbDataTable">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="pb-col-booking-id px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="booking_id">
                                <div class="flex items-center gap-1">
                                    Booking ID
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-tour-date px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="tour_date">
                                <div class="flex items-center gap-1">
                                    Tour Date
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-tour-time px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase hover:bg-gray-100 hidden">
                                <div class="flex items-center gap-1">
                                    Tour Time
                                </div>
                            </th>
                            <th class="pb-col-created-date px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="created_date">
                                <div class="flex items-center gap-1">
                                    Created Date
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-purchase-date px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="purchase_date">
                                <div class="flex items-center gap-1">
                                    Purchase Date
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-tour-package px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="tour_package">
                                <div class="flex items-center gap-1">
                                    Tour Package
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-customer px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="customer_name">
                                <div class="flex items-center gap-1">
                                    Customer
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-customer-email px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="customer_email">
                                <div class="flex items-center gap-1">
                                    Email
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-customer-phone px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="customer_phone">
                                <div class="flex items-center gap-1">
                                    Phone
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-payment-type px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="purchase_type">
                                <div class="flex items-center gap-1">
                                    Payment Type
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-payment px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="amount">
                                <div class="flex items-center justify-end gap-1">
                                    Payment
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-unpaid px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="unpaid_total">
                                <div class="flex items-center justify-end gap-1">
                                    Unpaid
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-fees px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="fee_amount">
                                <div class="flex items-center justify-end gap-1">
                                    Fees
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-refund px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="refund_amount">
                                <div class="flex items-center justify-end gap-1">
                                    Refund
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-discount px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="discount">
                                <div class="flex items-center justify-end gap-1">
                                    Discount
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-net px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="net_total">
                                <div class="flex items-center justify-end gap-1">
                                    Net
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-pax px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="total_pax">
                                <div class="flex items-center justify-center gap-1">
                                    Tourist
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-rate px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="pax_rate">
                                <div class="flex items-center justify-end gap-1">
                                    Rate
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-tax px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="pax_tax">
                                <div class="flex items-center justify-end gap-1">
                                    Tax
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-permit-fee px-3 py-2 text-right text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="pax_permit_fee">
                                <div class="flex items-center justify-end gap-1">
                                    Permit Fee
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-status px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100" data-sort="booking_status">
                                <div class="flex items-center gap-1">
                                    Status
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-checkin px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="check_in_status">
                                <div class="flex items-center gap-1">
                                    Check-in
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-waiver px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="waiver_status">
                                <div class="flex items-center gap-1">
                                    Waiver
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-platform px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="third_party_platform">
                                <div class="flex items-center gap-1">
                                    Platform
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-affiliate px-3 py-2 text-left text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="affiliate_name">
                                <div class="flex items-center gap-1">
                                    Affiliate
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-reallocated px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="is_reallocated">
                                <div class="flex items-center justify-center gap-1">
                                    Reallocated
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                            <th class="pb-col-soft-block px-3 py-2 text-center text-xs font-semibold text-gray-700 uppercase cursor-pointer hover:bg-gray-100 hidden" data-sort="is_soft_block">
                                <div class="flex items-center justify-center gap-1">
                                    Soft Block
                                    <i class="fas fa-sort text-gray-400"></i>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="pbTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Data populated via JavaScript -->
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr class="font-semibold text-xs">
                            <th class="pb-col-booking-id px-3 py-2 text-right text-gray-700"></th>
                            <th class="pb-col-tour-date px-3 py-2 text-right text-gray-700"></th>
                            <th class="pb-col-tour-time px-3 py-2 text-right text-gray-700 hidden"></th>
                            <th class="pb-col-created-date px-3 py-2 text-right text-gray-700 hidden"></th>
                            <th class="pb-col-purchase-date px-3 py-2 text-right text-gray-700 hidden"></th>
                            <th class="pb-col-tour-package px-3 py-2 text-right text-gray-700 hidden"></th>
                            <th class="pb-col-customer px-3 py-2 text-right text-gray-700"></th>
                            <th class="pb-col-customer-email px-3 py-2 text-right text-gray-700 hidden"></th>
                            <th class="pb-col-customer-phone px-3 py-2 text-right text-gray-700 hidden"></th>
                            <th class="pb-col-payment-type px-3 py-2 text-right text-gray-700">Totals:</th>
                            <th id="pbFooterPayment" class="pb-col-payment px-3 py-2 text-right text-gray-900">$0.00</th>
                            <th id="pbFooterUnpaid" class="pb-col-unpaid px-3 py-2 text-right text-gray-900 hidden">$0.00</th>
                            <th id="pbFooterFees" class="pb-col-fees px-3 py-2 text-right text-gray-900">$0.00</th>
                            <th id="pbFooterRefund" class="pb-col-refund px-3 py-2 text-right text-gray-900">$0.00</th>
                            <th id="pbFooterDiscount" class="pb-col-discount px-3 py-2 text-right text-gray-900">$0.00</th>
                            <th id="pbFooterNet" class="pb-col-net px-3 py-2 text-right text-gray-900">$0.00</th>
                            <th class="pb-col-pax px-3 py-2 hidden"></th>
                            <th class="pb-col-rate px-3 py-2 hidden"></th>
                            <th class="pb-col-tax px-3 py-2 hidden"></th>
                            <th class="pb-col-permit-fee px-3 py-2 hidden"></th>
                            <th class="pb-col-status px-3 py-2"></th>
                            <th class="pb-col-checkin px-3 py-2 hidden"></th>
                            <th class="pb-col-waiver px-3 py-2 hidden"></th>
                            <th class="pb-col-platform px-3 py-2 hidden"></th>
                            <th class="pb-col-affiliate px-3 py-2 hidden"></th>
                            <th class="pb-col-reallocated px-3 py-2 hidden"></th>
                            <th class="pb-col-soft-block px-3 py-2 hidden"></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-4 py-3 border-t border-gray-200 bg-gray-50">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700" id="pbPaginationInfo">
                        Showing 0 to 0 of 0 entries
                    </div>
                    <nav>
                        <ul class="flex items-center gap-1" id="pbPagination">
                            <!-- Pagination populated via JavaScript -->
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
        <!-- End Payment Tab Content -->
    </div>
    <!-- End Tabs Container -->
</div>

<!-- Empty State -->
<div id="pbEmptyState" class="text-center py-16">
    <i class="fas fa-chart-bar text-6xl text-gray-300 mb-4"></i>
    <h5 class="text-xl font-semibold text-gray-600 mb-2">No Data Available</h5>
    <p class="text-gray-500">Please configure filters and generate the report.</p>
</div>

<!-- Custom Charge Bookings Modal -->
<div id="pbCustomChargeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Custom Charge Bookings (Rounding Gains)</h3>
            <button id="pbCloseCustomChargeModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium">Total Bookings</div>
                    <div id="pbCustomChargeTotalBookings" class="text-2xl font-bold text-green-700">-</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium">Total Custom Charge</div>
                    <div id="pbCustomChargeTotalAmount" class="text-2xl font-bold text-green-700">-</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium">Total Tourists</div>
                    <div id="pbCustomChargeTotalPax" class="text-2xl font-bold text-green-700">-</div>
                </div>
            </div>
            
            <!-- Info Note -->
            <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>What is Custom Charge?</strong> These bookings collected slightly more than the calculated gross amount due to rounding. 
                            This represents additional revenue gained from rounding adjustments (e.g., $123.52 rounded to $124.00 = $0.48 gain).
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Search and Pagination Controls -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbCustomChargeSearch" placeholder="Search bookings..." 
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <select id="pbCustomChargePageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div id="pbCustomChargePaginationInfo" class="text-sm text-gray-600"></div>
            </div>
            
            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gain</th>
                        </tr>
                    </thead>
                    <tbody id="pbCustomChargeTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows will be dynamically inserted here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="pbCustomChargePagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Refunded Bookings Modal -->
<div id="pbRefundedModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Refunded Bookings Breakdown</h3>
            <button id="pbCloseRefundedModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Tabs -->
            <div class="border-b border-gray-200 mb-4">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <button type="button" id="pbRefundedTabIncluded" class="pb-refunded-tab border-red-500 text-red-600 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2" data-tab="included">
                        <span>Counted in Total</span>
                        <span id="pbRefundedTabBadge" class="bg-red-100 text-red-600 py-0.5 px-2 rounded-full text-xs font-semibold">0</span>
                    </button>
                    <button type="button" id="pbRefundedTabExcluded" class="pb-refunded-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm flex items-center gap-2" data-tab="excluded">
                        <span>Not Counted</span>
                        <span id="pbExcludedRefundsTabBadge" class="bg-gray-100 text-gray-600 py-0.5 px-2 rounded-full text-xs font-semibold">0</span>
                    </button>
                </nav>
            </div>

            <!-- Included Refunds Tab Content -->
            <div id="pbRefundedTabContentIncluded" class="pb-refunded-tab-content">
                <!-- Summary Cards -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="text-sm text-red-600 font-medium">Total Bookings</div>
                        <div id="pbRefundedTotalBookings" class="text-2xl font-bold text-red-700">-</div>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="text-sm text-red-600 font-medium">Total Refunded Amount</div>
                        <div id="pbRefundedTotalAmount" class="text-2xl font-bold text-red-700">-</div>
                    </div>
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="text-sm text-red-600 font-medium">Total Tourists</div>
                        <div id="pbRefundedTotalPax" class="text-2xl font-bold text-red-700">-</div>
                    </div>
                </div>

                <!-- Info Note -->
                <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                <strong>What are Refunded Bookings?</strong> These are bookings where a refund resulted in collecting less than the current booking value (Net &lt; Gross).
                            </p>
                            <p class="text-sm text-blue-600 mt-2">
                                <strong>Note:</strong> Rate change refunds (e.g., Adult changed to Military) may not appear here if the net collected equals the new booking value.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Search and Pagination Controls -->
                <div class="mb-4 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <input type="text" id="pbRefundedSearch" placeholder="Search bookings..." 
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        <select id="pbRefundedPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="10">10 per page</option>
                            <option value="25" selected>25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    <div id="pbRefundedPaginationInfo" class="text-sm text-gray-600"></div>
                </div>
                
                <!-- Data Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Refunded</th>
                            </tr>
                        </thead>
                        <tbody id="pbRefundedTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be dynamically inserted here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="pbRefundedPagination" class="mt-4 flex justify-end"></div>
            </div>

            <!-- Excluded Refunds Tab Content -->
            <div id="pbRefundedTabContentExcluded" class="pb-refunded-tab-content hidden">
                <!-- Summary Cards -->
                <div class="grid grid-cols-3 gap-4 mb-6">
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="text-sm text-gray-600 font-medium">Total Bookings</div>
                        <div id="pbExcludedRefundsTotalBookings" class="text-2xl font-bold text-gray-700">-</div>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="text-sm text-gray-600 font-medium">Total Refund Amount</div>
                        <div id="pbExcludedRefundsTotalAmount" class="text-2xl font-bold text-gray-700">-</div>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="text-sm text-gray-600 font-medium">Total Tourists</div>
                        <div id="pbExcludedRefundsTotalPax" class="text-2xl font-bold text-gray-700">-</div>
                    </div>
                </div>

                <!-- Info Note -->
                <div class="mb-4 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-yellow-700">
                                <strong>Why are these not counted?</strong> These bookings have refunds, but the Net collected still equals or exceeds the Gross.
                            </p>
                            <p class="text-sm text-yellow-600 mt-2">
                                <strong>Common reasons:</strong> Customer originally overpaid (e.g., rate change from Adult to Military), and the refund simply returned the excess amount without reducing actual revenue.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Search and Pagination Controls -->
                <div class="mb-4 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <input type="text" id="pbExcludedRefundsSearch" placeholder="Search bookings..." 
                            class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-gray-500 focus:border-gray-500">
                        <select id="pbExcludedRefundsPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="10">10 per page</option>
                            <option value="25" selected>25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                        </select>
                    </div>
                    <div id="pbExcludedRefundsPaginationInfo" class="text-sm text-gray-600"></div>
                </div>
                
                <!-- Data Table -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Refund</th>
                            </tr>
                        </thead>
                        <tbody id="pbExcludedRefundsTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be dynamically inserted here -->
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <div id="pbExcludedRefundsPagination" class="mt-4 flex justify-end"></div>
            </div>
        </div>
    </div>
</div>

<!-- Unpaid Bookings Modal -->
<div id="pbUnpaidModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Unpaid Bookings Breakdown</h3>
            <button id="pbCloseUnpaidModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="text-sm text-yellow-600 font-medium">Total Bookings</div>
                    <div id="pbUnpaidTotalBookings" class="text-2xl font-bold text-yellow-700">-</div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="text-sm text-yellow-600 font-medium">Total Unpaid Amount</div>
                    <div id="pbUnpaidTotalAmount" class="text-2xl font-bold text-yellow-700">-</div>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="text-sm text-yellow-600 font-medium">Total Tourists</div>
                    <div id="pbUnpaidTotalPax" class="text-2xl font-bold text-yellow-700">-</div>
                </div>
            </div>
            
            <!-- Search and Pagination Controls -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbUnpaidSearch" placeholder="Search bookings..." 
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500">
                    <select id="pbUnpaidPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div id="pbUnpaidPaginationInfo" class="text-sm text-gray-600"></div>
            </div>
            
            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross Amount</th>
                        </tr>
                    </thead>
                    <tbody id="pbUnpaidTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows will be dynamically inserted here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="pbUnpaidPagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Discount Bookings Modal -->
<div id="pbDiscountModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Discount Bookings Breakdown</h3>
            <button id="pbCloseDiscountModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="text-sm text-purple-600 font-medium">Total Bookings</div>
                    <div id="pbDiscountTotalBookings" class="text-2xl font-bold text-purple-700">-</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="text-sm text-purple-600 font-medium">Total Discount Amount</div>
                    <div id="pbDiscountTotalAmount" class="text-2xl font-bold text-purple-700">-</div>
                </div>
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="text-sm text-purple-600 font-medium">Total Tourists</div>
                    <div id="pbDiscountTotalPax" class="text-2xl font-bold text-purple-700">-</div>
                </div>
            </div>
            
            <!-- Search and Pagination Controls -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbDiscountSearch" placeholder="Search bookings..." 
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <select id="pbDiscountPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div id="pbDiscountPaginationInfo" class="text-sm text-gray-600"></div>
            </div>
            
            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Discount</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                        </tr>
                    </thead>
                    <tbody id="pbDiscountTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows will be dynamically inserted here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="pbDiscountPagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Dispute Reversals Modal -->
<div id="pbDisputeReversalsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-red-500 to-red-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Dispute Reversals Breakdown</h3>
            <button id="pbCloseDisputeReversalsModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <div class="grid grid-cols-2 gap-4 mb-6">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="text-sm text-red-600 font-medium">Total Disputes</div>
                    <div id="pbDisputeReversalsTotalCount" class="text-2xl font-bold text-red-700">-</div>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="text-sm text-red-600 font-medium">Total Reversed Amount</div>
                    <div id="pbDisputeReversalsTotalAmount" class="text-2xl font-bold text-red-700">-</div>
                </div>
            </div>
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbDisputeReversalsSearch" placeholder="Search disputes..."
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <select id="pbDisputeReversalsPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
                <div id="pbDisputeReversalsPaginationInfo" class="text-sm text-gray-600"></div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Dispute Amount</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Reversed Amount</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reason</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Lost At</th>
                        </tr>
                    </thead>
                    <tbody id="pbDisputeReversalsTableBody" class="bg-white divide-y divide-gray-200"></tbody>
                </table>
            </div>
            <div id="pbDisputeReversalsPagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Prior Payments Breakdown Modal -->
<div id="pbPriorPaymentsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Prior Payments Breakdown</h3>
            <button id="pbClosePriorPaymentsModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-sm text-blue-600 font-medium">Total Bookings</div>
                    <div id="pbPriorPaymentsTotalBookings" class="text-2xl font-bold text-blue-700">-</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-sm text-blue-600 font-medium">Total Prior Payments</div>
                    <div id="pbPriorPaymentsTotalAmount" class="text-2xl font-bold text-blue-700">-</div>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-sm text-blue-600 font-medium">Total Tourists</div>
                    <div id="pbPriorPaymentsTotalPax" class="text-2xl font-bold text-blue-700">-</div>
                </div>
            </div>
            
            <!-- Info Note -->
            <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                <p class="text-sm text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    Prior Payments: These bookings had payments collected before the selected date range. They appear in this report because a refund or cancellation occurred within the date range.
                </p>
            </div>
            
            <!-- Search and Pagination Controls -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbPriorPaymentsSearch" placeholder="Search bookings..." 
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <select id="pbPriorPaymentsPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div id="pbPriorPaymentsPaginationInfo" class="text-sm text-gray-600"></div>
            </div>
            
            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Prior Payment</th>
                        </tr>
                    </thead>
                    <tbody id="pbPriorPaymentsTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows will be dynamically inserted here -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div id="pbPriorPaymentsPagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Overpayment Breakdown Modal -->
<div id="pbOverpaymentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Overpayment Breakdown</h3>
            <button id="pbCloseOverpaymentModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium">Total Bookings</div>
                    <div id="pbOverpaymentTotalBookings" class="text-2xl font-bold text-green-700">-</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium">Total Overpayment</div>
                    <div id="pbOverpaymentTotalAmount" class="text-2xl font-bold text-green-700">-</div>
                </div>
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <div class="text-sm text-green-600 font-medium">Total Tourists</div>
                    <div id="pbOverpaymentTotalPax" class="text-2xl font-bold text-green-700">-</div>
                </div>
            </div>

            <!-- Info Note -->
            <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>What is Overpayment?</strong> These bookings have collected more money than the customer currently owes based on the booking details.
                        </p>
                        <p class="text-sm text-blue-600 mt-2">
                            <strong>Note:</strong> This can occur when people are removed from a booking with partial refunds, or when payments exceed the booking total.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Search and Pagination Controls -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbOverpaymentSearch" placeholder="Search bookings..." 
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <select id="pbOverpaymentPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div id="pbOverpaymentPaginationInfo" class="text-sm text-gray-600"></div>
            </div>

            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Overpayment</th>
                        </tr>
                    </thead>
                    <tbody id="pbOverpaymentTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows will be dynamically inserted here -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pbOverpaymentPagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Other Period Payments Breakdown Modal -->
<div id="pbOtherPeriodPaymentsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Other Period Payments Breakdown</h3>
            <button id="pbCloseOtherPeriodPaymentsModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Summary Cards -->
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="text-sm text-orange-600 font-medium">Total Bookings</div>
                    <div id="pbOtherPeriodPaymentsTotalBookings" class="text-2xl font-bold text-orange-700">-</div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="text-sm text-orange-600 font-medium">Total Other Period Amount</div>
                    <div id="pbOtherPeriodPaymentsTotalAmount" class="text-2xl font-bold text-orange-700">-</div>
                </div>
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="text-sm text-orange-600 font-medium">Total Tourists</div>
                    <div id="pbOtherPeriodPaymentsTotalPax" class="text-2xl font-bold text-orange-700">-</div>
                </div>
            </div>

            <!-- Info Note -->
            <div class="mb-4 bg-blue-50 border-l-4 border-blue-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-info-circle text-blue-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-700">
                            <strong>What are Other Period Payments?</strong> These bookings have payments split across multiple periods. The amount shown represents payments made outside the selected date range.
                        </p>
                        <p class="text-sm text-blue-600 mt-2">
                            <strong>Note:</strong> This typically occurs when filtering by Purchase Date and a booking has transactions in different months.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Search and Pagination Controls -->
            <div class="mb-4 flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <input type="text" id="pbOtherPeriodPaymentsSearch" placeholder="Search bookings..." 
                        class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-orange-500 focus:border-orange-500">
                    <select id="pbOtherPeriodPaymentsPageSize" class="px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="10">10 per page</option>
                        <option value="25" selected>25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div id="pbOtherPeriodPaymentsPaginationInfo" class="text-sm text-gray-600"></div>
            </div>

            <!-- Data Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tour Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Type</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tourists</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Gross</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net (This Period)</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Other Period</th>
                        </tr>
                    </thead>
                    <tbody id="pbOtherPeriodPaymentsTableBody" class="bg-white divide-y divide-gray-200">
                        <!-- Rows will be dynamically inserted here -->
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div id="pbOtherPeriodPaymentsPagination" class="mt-4 flex justify-end"></div>
        </div>
    </div>
</div>

<!-- Permit Fee Check-In Breakdown Modal -->
<div id="pbPermitFeeBreakdownModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-5xl w-full mx-4 max-h-[90vh] overflow-hidden">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-white">Permit Fee Breakdown by Check-In Status</h3>
            <button id="pbCloseBreakdownModal" class="text-white hover:text-gray-200">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
        <!-- Modal Body -->
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
            <!-- Loading State -->
            <div id="pbBreakdownLoading" class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-4xl text-orange-500"></i>
                <p class="mt-4 text-gray-600">Loading breakdown data...</p>
            </div>
            
            <!-- Error State -->
            <div id="pbBreakdownError" class="hidden text-center py-8">
                <i class="fas fa-exclamation-triangle text-4xl text-red-500"></i>
                <p class="mt-4 text-gray-600" id="pbBreakdownErrorMessage"></p>
            </div>
            
            <!-- Data Table -->
            <div id="pbBreakdownContent" class="hidden">
                <!-- Expand/Collapse All Buttons -->
                <div class="mb-4 flex gap-2">
                    <button id="pbExpandAll" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">
                        <i class="fas fa-chevron-down mr-1"></i> Expand All
                    </button>
                    <button id="pbCollapseAll" class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 rounded">
                        <i class="fas fa-chevron-up mr-1"></i> Collapse All
                    </button>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/2">
                                    Check-In Status / Rate Group
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Total Tourists
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Tourists with Permit
                                </th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Permit Fees
                                </th>
                            </tr>
                        </thead>
                        <tbody id="pbBreakdownTableBody" class="bg-white divide-y divide-gray-200">
                            <!-- Rows will be dynamically inserted here -->
                        </tbody>
                        <tfoot class="bg-gray-100 font-semibold border-t-2 border-gray-300">
                            <tr>
                                <td class="px-6 py-4 text-left text-sm text-gray-900">GRAND TOTAL</td>
                                <td id="pbBreakdownTotalPax" class="px-6 py-4 text-right text-sm text-gray-900">-</td>
                                <td id="pbBreakdownTotalWithPermit" class="px-6 py-4 text-right text-sm text-gray-900">-</td>
                                <td id="pbBreakdownTotalFees" class="px-6 py-4 text-right text-sm text-gray-900">-</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Info Note -->
                <div class="mt-6 bg-blue-50 border-l-4 border-blue-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-blue-700">
                                <strong>Note:</strong> Click on any check-in status to expand and view the breakdown by rate groups. 
                                This shows how different tourist types (Adult, Child, Infant, etc.) are distributed across check-in statuses.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
