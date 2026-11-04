<div class="d-flex flex-column flex-md-row flex-lg-row justify-content-around nat-toolbar py-6 bg-white border-b border-[#E5E7EB]" style="position: relative;">
    <div class="w-full container">
        <div class="flex flex-wrap -mx-3">
            <div class="w-full max-w-full px-3 mb-2">
                <h1 class="font-normal text-gray-900 text-2xl mb-2">Report</h1>
            </div>

            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2 flex-grow-1" id="selected-report-wrap" >
                <label class="nat-label form-custom-label mb-1" id="selected-report-name" style="display:none;"> </label>
                <button class="btn btn-secondary btn-sm  bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-sm font-semibold px-4 py-[7px] w-full rounded-[6px] cursor-pointer transition-all duration-300 ease-in-out"
                data-bs-toggle="offcanvas" id="select-report-toggle" 
                href="#leftNaveReportSelect" role="button" aria-controls="leftNaveReportSelect">Select Report</button>
            </div>

            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="tour-package-btn-select" style="display: none;">
                <label class="nat-label form-custom-label mb-1">Tour Package</label>
                <div class="dropdown show relative">
                    <a class="btn btn-default btn-sm dropdown-toggle flex items-center justify-between form-custom-input whitespace-nowrap overflow-hidden"
                    name="tourPackageValue" id="tourPackageValue"
                    href="#" role="button"  data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        Tour Package
                        <svg class="ml-2 max-w-[10px]" width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.4001 1.20019L7.0001 6.8002L12.6001 1.2002" stroke="#6b7a90" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div id="tourpackage-dropdown-menu-list" class="dropdown-menu absolute left-1/2 -translate-x-1/2 mt-2 w-64 bg-white shadow-lg z-50 rounded border text-sm text-left hidden max-h-80 overflow-y-auto" aria-labelledby="tourPackageValue">
                        @if(!$departmentPackage)
                            <a class="dropdown-item tour-package-update block px-4 py-2 text-sm text-[#4B5563] hover:text-[#F97316]" data-value="" href="#">All Tour Packages</a>
                        @endif
                        @foreach($tourPackages as $tp)
                            <a class="dropdown-item tour-package-update block px-4 py-2 text-sm text-[#4B5563] hover:text-[#F97316]"  href="#" data-value="{{ $tp->id }}">{{ $tp->name }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="ota_platforms" style="display: none;">
                <label class="nat-label form-custom-label mb-1">OTAs</label>
                <div class="dropdown show relative">
                    <a class="btn btn-default btn-sm dropdown-toggle flex items-center justify-between form-custom-input" name="otaValue" id="otaValue"
                    href="#" role="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        All OTAs
                        <svg class="ml-2 max-w-[10px]" width="14" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.4001 1.20019L7.0001 6.8002L12.6001 1.2002" stroke="#6b7a90" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div id="ota-platform-dropdown-menu-list" class="dropdown-menu absolute left-1/2 -translate-x-1/2 mt-2 w-64 bg-white shadow-lg z-50 rounded border text-sm text-left hidden" aria-labelledby="otaValue">
                        <a class="dropdown-item block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" data-value="" href="#">All OTAs</a>
                        @foreach($ota_platforms as $ota)
                            <a class="dropdown-item block w-full px-4 py-2 text-[#4B5563] hover:text-[#F97316]" href="#" data-value="{{ $ota }}">{{ $ota }}</a>
                        @endforeach
                        <input type="hidden" id="otaPlatform" name="ota_platform" value="">
                    </div>
                </div>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="affiliatereport" style="display:none">
                <label id="toLabelMain1" class="nat-label form-custom-label mb-1">Customers</label>
                <select class="form-select w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" id="customer-data">
                    @if(!$uTypeAffiliate)
                    <option value="0">All</option>
                    @endif
                    @foreach($affiliates as $af)
                        <option value="{{$af->id}}">{{ $af->name }}</option>
                    @endforeach
                </select>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="promotionreport" style="display:none">
                <label id="toLabelMain2" class="nat-label form-custom-label mb-1">Promo Codes</label>
                <select class="form-select w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" id="promotion-data">
                    @if(!$uTypeAffiliate)
                    <option value="0">All</option>
                    @endif
                    @foreach($promocodes as $code)
                        <option value="{{ $code->id }}">{{ $code->code }} {{ ($code->status == 'Inactive') ? '(Expired)' : '' }}</option>
                    @endforeach
                </select>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="add-on-report" style="display:none">
                <label id="toLabelMain3" class="nat-label form-custom-label mb-1">Add-ons</label>
                <select class="form-select w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" id="add-on-data">
                    <option value="">All Add-ons</option>
                    @foreach($addonsName as $addon)
                        <option value="{{ $addon }}">{{ $addon }}</option>
                    @endforeach
                </select>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="viewByEvent" style="display: none;">
                <label id="toLabelMain7" class="nat-label form-custom-label mb-1">Event Type</label>
                <select class="form-select w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" id="event_type">
                    <option value="" selected>Please select</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                </select>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="user-name-btn-select" style="display: none;">
                <label class="nat-label form-custom-label mb-1">User Name0</label>
                <select id="user_name" class="form-select select-tour w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none">
                    <option value="" selected>Select User Name</option>
                    @foreach($userNames as $data)
                    <option value="{{ $data['id'] }}">{{ $data['name'] }}  @if(!empty($data['role'])) ({{ $data['role'] }}) @endif</option>
                    @endforeach
                </select>
            </div>
        
            <div class="w-full sm:w-full md:w-2/3 lg:w-1/2 xl:w-2/5 max-w-full px-3 mb-2" id="fromToDateTimeDiv" style="display: none;">
                <div style="overflow:hidden;">
                    <label id="toLabelMain4" class="nat-label form-custom-label mb-1">Date Time Range</label>
                    <div class="reportdatetimerange w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none">
                        <div id="reportdatetimerange" class="pull-right rounded form-control nat-daterange">
                            <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>
                            <span></span> <b class="caret"></b>
                        </div>
                    </div>
                </div>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="fromDateDiv" style="display: none;">
                <div class="date-select">
                    <label id="fromLabelMain" class="nat-label form-custom-label mb-1">From Date</label>
                    <input class="w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" type="text" name="fromDate" id="fromDate">
                    <span name="fromDateValue" id="fromDateValue" style="display: none;"></span>
                </div>
            </div>
        
            <div class="w-full sm:w-full md:w-2/3 lg:w-1/2 xl:w-2/5 max-w-full px-3 mb-2" id="fromAndToDateDiv" style="display: none;">
                <div style="overflow:hidden;">
                    <label id="toLabelMain4" class="nat-label form-custom-label mb-1">Date Range</label>
                    <div class="reportrange w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" style="min-width: 287px;">
                        <div id="reportrange" class="pull-right rounded form-control nat-daterange">
                            <i class="glyphicon glyphicon-calendar fa fa-calendar"></i>
                            <span></span> <b class="caret"></b>
                        </div>
                    </div>
                </div>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="toDateDiv" style="display: none;">
                <div class="date-select">
                    <label id="toLabelMain3" class="nat-label form-custom-label mb-1">To Date</label>
                    <input class="w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" type="text" name="toDate" id="toDate">
                    <span name="toDateValue" id="toDateValue" style="display: none; margin-top:-20px;"></span>
                </div>
            </div>

            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="viewByReportType" style="display: none;">
                <label id="toLabelMain7" class="nat-label form-custom-label mb-1">Report Type</label>
                <select class="form-select w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" id="reportType">
                    <option value="Card" selected>Card</option>
                    <option value="Cash">Cash</option>
                </select>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="viewBy" style="display:none">
                <label id="toLabelMain5" class="nat-label form-custom-label mb-1">View By</label>
                <select class="form-select w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" id="viewby-select">
                    <option value="servicesrendered" selected>Tour Date</option>
                    <option value="purchasedate" >Purchase Date</option>
                </select>
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" style="display:none">
                <label id="toLabelMain6" class="nat-label form-custom-label mb-1">Booking ID</label>
                <input type="text" id="bookingId" value="" class="form-control w-full border border-solid border-[#E2E8F0] rounded-lg px-4 py-2 bg-white text-sm text-[#64748B] outline-none" placeholder="Booking ID" />
            </div>
        
            <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2" id="btnserchprint" style="display: none;">
                <label for="generate-report-btn" class="nat-label form-custom-label mb-1">&nbsp;</label>
                <button class="btn btn-sm btn-primary btn-primary-small !w-full" style="text-wrap: nowrap; overflow-x: hidden;" name="generate-report-btn" id="generate-report-btn">Generate Report</button>
            </div>
        </div>
    </div>
</div>