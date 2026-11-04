<div class="offcanvas offcanvas-start border  fixed top-0 left-0 bottom-0 z-[1045] flex flex-col w-[400px] max-w-full border-r border-r-[rgba(0,0,0,0.2)] translate-x-[-100%] invisible bg-white [background-clip:padding-box] outline-0 transition-transform duration-300 ease-in-out" tabindex="-1" id="leftNaveReportSelect" aria-labelledby="leftNaveReportSelectLabel">
    <div class="offcanvas-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
      <h5 class="offcanvas-title text-lg max-[1350px]:text-base font-medium text-[#111827]" id="leftNaveReportSelectLabel">Reports</h5>
      <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
    </div>
    <div class="offcanvas-body p-4 h-[calc(100vh-60px)] overflow-y-auto">
        @if($uTypeAdmin)
            <div class="w-full md:w-1/2 hidden">
                <div class="select-tour-wrp">
                    <select id="tour_operator" class="form-select form-custom-input" onchange="getTourPackages(this)">
                        <option value="">Select Tour Operator</option>
                        @foreach($tourOperators as $to)
                            <option value="{{ $to->id }}" selected="selected">{{ $to->name }}</option>
                        @endforeach
                    </select>
                    <span name="tourOperatorValue" id="tourOperatorValue" style="display: none"></span>
                </div>
            </div>
        @else
            <input type="hidden" value="{{$tourOperatorId}}"  id="tour_operator" />
            <input type="hidden" value="{{$uTypeAdmin}}" id="tour_operator_role" />
        @endif
        
        <select id="tour_package" class="form-select form-custom-input mb-2">
            <option value="">All Tour Packages</option>
            @foreach($tourPackages as $tp)
                <option value="{{ $tp->id }}"{{ $tp->id == $tourPackageId ? ' selected' : '' }}>{{ $tp->name }}</option>
            @endforeach
        </select>
        
        <input type="text" placeholder="Search Reports" class="form-control w-full m-0 border border-[#E5E7EB] bg-[#FAFAFA] rounded-lg px-3 py-[9px] text-sm text-[#000] shadow-none outline-none" id="search-report-dropdown" role="presentation" autocomplete="off" autocomplete="false" />
        <input type="hidden" id="select-report" value="" /> 
        <ul id="report-list" class="form-control rounded border text-sm text-left">
            @if(!$uTypeAdmin)
                @if(!empty($reports))
                    @if($reports->reports != null)

                    <?php $reportList = json_decode($reports->reports,true);
                            sort($reportList); 
                    ?>
                        @foreach($reportList as $report)
                            @if($uTypeAffiliate)
                                @if($report == 'Affiliate Booked Report' || $report == 'Affiliate Comprehensive Report' || $report == 'Affiliate Seats Report')
                                    <li class="nat-list-item block px-4 py-2 text-[#4B5563] hover:text-[#F97316] cursor-pointer" data-value="{{ $report }}">{{ $report }}</li>
                                @endif
                            @else
                                <li class="nat-list-item block px-4 py-2 text-[#4B5563] hover:text-[#F97316] cursor-pointer" data-value="{{ $report }}">{{ $report }}</li>
                            @endif
                        @endforeach
                    @endif
                @endif
            @else

            <!-- Find staic report list function in helpers -->
            @foreach($staicReportList as $reportName)
                <li class="nat-list-item block px-4 py-2 text-[#4B5563] hover:text-[#F97316] cursor-pointer" data-value="{{ $reportName }}">{{$reportName}}</li>
            @endforeach
            
            <!-- Find staic admin reports list function in helpers -->
            @if($uTypeAdmin)
                @foreach($adminStaicReportList as $reportName)
                    <li class="nat-list-item block px-4 py-2 text-[#4B5563] hover:text-[#F97316] cursor-pointer" data-value="{{ $reportName }}" value="{{$reportName}}">{{$reportName}}</li>
                @endforeach
            @endif
            @endif
        </ul>
        <button type="button" class="btn btn-sm btn-default mt-2 btn-danger-small" data-bs-dismiss="offcanvas" >Close</button>
    </div>
</div>