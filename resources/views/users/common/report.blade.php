@extends('layouts.default-2', ['wrapclass' => 'container-fluid'])
@php
	$uTypeAdmin = session('userType') == 'Admin';
    $uTypeAffiliate = session('userType') == 'Affiliate';
    $isCashFees = cashFees();
@endphp

@section('main-content')

@if($errors->any())
	@include('layouts._errors')
@endif

@if(session('message'))
	@include('layouts._message')
@endif
<section class="select-tour-sec booking-action-sec">
	<div class="details-table">
		<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="trans-report-booking-history-modal" tabindex="-1" aria-labelledby="rev-report-filter-modal-label" aria-hidden="true" data-mdb-backdrop="true" data-focus-on="input:first" data-mdb-keyboard="true" >
			<div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
				<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
					<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
						<h5 class="modal-title text-lg font-semibold text-[#111827]" id="trans-report-booking-history-modal-label">Booking Detail</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
					</div>
					<div class="modal-body p-4" id="trans-history-modal-body" style="max-height:600px; overflow-x:auto;">
						<div class="text-base font-semibold text-[#111827] mb-1">Transaction Detail</div>
						<div class="w-full overflow-y-auto mt-2">
							<table width="100%" class="table table-striped w-full border-collapse text-left" id="booking-transact-deatils-trans">

							</table>
						</div>
						<div class="text-base font-semibold text-[#111827] mt-4 mb-1"> History Detail </div>
						<div class="w-full overflow-y-auto mt-2">
							<table width="100%" class="w-full border-collapse text-left" id="transR-booking-history-deatils-body">

							</table>
						</div>
					</div>
        		</div>
     		</div>
		</div>

		<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="trans-report-filter-modal" tabindex="-1" aria-labelledby="rev-report-filter-modal-label" aria-hidden="true" data-mdb-backdrop="true" data-focus-on="input:first" data-mdb-keyboard="true">
			<div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
				<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
					<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
						<h5 class="modal-title text-lg font-semibold text-[#111827]" id="trans-report-filter-modal-label">Filter List</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
					</div>
					<div class="modal-body p-4" id="revenue-modal-view">
						<form class="form-control">
							<input type="hidden" id="reportCheck" name="report_check" value="">
							<div class="form-group mb-4">
								<div class="flex items-center mb-1"><input type="checkbox" class="checkCash w-4 h-4 mr-2" id="checkCash" name="cashcheckbox" value="1" checked> <label class="inline-block text-sm font-normal text-[#020817]" for="checkCash">Cash</label></div>
								<div class="flex items-center mb-1"><input type="checkbox" class="checkCard w-4 h-4 mr-2" id="checkCard" name="cardcheckbox" value="1" checked> <label class="inline-block text-sm font-normal text-[#020817]" for="checkCard">Card</label></div>
								<div class="flex items-center mb-1"><input type="checkbox" class="checkWire w-4 h-4 mr-2" id="checkWire" name="wirecheckbox" value="1" checked> <label class="inline-block text-sm font-normal text-[#020817]" for="checkWire">Check/Wire</label></div>
								<div class="flex items-center mb-1"><input type="checkbox" class="checkInvoiced w-4 h-4 mr-2" id="checkInvoiced" name="invoicedcheckbox" value="1" checked> <label class="inline-block text-sm font-normal text-[#020817]" for="checkInvoiced">Invoiced</label></div>
							</div>

							<div class="form-group mb-4">
								<div class="nat-accordion collapsed form-custom-input flex items-center" data-bs-target="#collapseExternalBookingsArea" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="collapseExternalBookingsArea">
									Bookings Imported From External System
									<svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#9CA3AF" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
								</div>

								<div id="collapseExternalBookingsArea" class="hide collapse w-full border border-solid border-[#E2E8F0] p-4 rounded-lg">
									<div class="flex items-center mb-1">
										<input id="rev-filter-bookingsystemnone" name="bookingsys" class="rev-filter excludeExternal resize-none outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-4 w-4 text-[14px] text-[#020817]" data-type="bookingsystemnone" type="radio" value="0">
										<label class="inline-block text-sm font-normal text-[#020817] ml-2" for="rev-filter-bookingsystemnone">Exclude External Bookings</label>
									</div>
									<div class="flex items-center mb-1">
										<input id="rev-filter-bookingsystem" checked name="bookingsys" class="rev-filter includeExternal resize-none outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-4 w-4 text-[14px] text-[#020817]" data-type="bookingsystem" type="radio" value="1">
										<label class="inline-block text-sm font-normal text-[#020817] ml-2" for="rev-filter-bookingsystem">Include External Bookings</label>
									</div>
									<div class="flex items-center mb-1">
										<input id="rev-filter-bookingsystemwithfee" checked name="bookingsys" class="rev-filter includeExternalWithFee resize-none outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-4 w-4 text-[14px] text-[#020817]" data-type="bookingsystemwithfee" type="radio" value="1">
										<label class="inline-block text-sm font-normal text-[#020817] ml-2" for="rev-filter-bookingsystemwithfee">Include External Bookings With Fees</label>
									</div>
									<div class="flex items-center mb-1">
										<input id="rev-filter-bookingsystemonly" name="bookingsys" class="rev-filter onlyShowExternal resize-none outline-none shadow-none border border-[#E2E8F0] rounded-[6px] px-3 py-1 h-4 w-4 text-[14px] text-[#020817]" data-type="bookingsystemonly" type="radio" value="1">
										<label class="inline-block text-sm font-normal text-[#020817] ml-2" for="rev-filter-bookingsystemonly">Only Show External Bookings</label>
									</div>
								</div>
							</div>

							<div class="form-group flex items-center mb-2" id="checkUnpaidDiv" style="display:none">
								<input type='checkbox' id="checkUnpaid" class="checkUnpaid w-4 h-4 mr-2" value="1" />
								<label class="inline-block text-sm font-normal text-[#020817] ml-2" for="checkUnpaid">Only Show Unpaid Bookings</label>
							</div>
							<div class="form-group flex items-center mb-2">
								<input type='checkbox' id="checkCancel" class="checkCancel w-4 h-4 mr-2" value="1" />
								<label class="inline-block text-sm font-normal text-[#020817] ml-2" for="checkCancel">Remove Cancelled Bookings</label>
							</div>
						</form>
					</div>
					<div class="modal-footer px-4 pb-4">
						<button type="button" class="btn btn-primary trans-report-reload btn-primary-small w-full">Filter Results</button>
					</div>
				</div>
			</div>
		</div>

		<div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="affiliate-seats-report-modal" tabindex="-1" aria-labelledby="" aria-hidden="true" data-mdb-backdrop="true" data-focus-on="input:first" data-mdb-keyboard="true" >
			<div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
				<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
					<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
						<h3 class="text-lg font-semibold text-[#111827]"> Affiliate Seat Report Detail</h3>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
					</div>
					<div class="modal-body p-4">
						<div class="w-full overflow-y-auto mt-2">
							<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="affiliate-seat-report-time-details" >
								<thead>
								<tr>
									<th class="th-custom-css">Slot Time</th>
									<th class="th-custom-css">Assinged Seats</th>
									<th class="th-custom-css">Booked Seats</th>
									<th class="th-custom-css">Cancelled Seats</th>
									<th class="th-custom-css">Fulfilled Seats</th>
									<th class="th-custom-css">No-Show</th>
								</tr>
								</thead>
								<tbody id="bookingTableBody">

								</tbody>
							</table>
						</div>
					</div>
				</div>
        	</div>
        </div>

        <div class="modal top fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="addons-value-report-modal" tabindex="-1" aria-labelledby="" aria-hidden="true" data-mdb-backdrop="true" data-focus-on="input:first" data-mdb-keyboard="true" >
			<div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
				<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
					<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
						<h3 class="text-lg font-semibold text-[#111827]"> Add-ons Package Value Report</h3>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
					</div>
					<div class="modal-body p-4" style="max-height:600px; overflow-x:auto;">
						<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="addons-value-report">
							<thead>
								<tr>
									<th class="th-custom-css">Booking ID</th>
									<th class="th-custom-css">Value</th>
									<th class="th-custom-css">Price</th>
								</tr>
							</thead>
							<tbody id="bookingTableBody">

							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>	

        

	<div class="col-md-3 user-details">
		<h4 id="reportHeadingPrint" style="display: none">Summary Report</h4>
	</div>

	@include("/users/common/partials/reports/_report_select", ["reports" => $reports ?? [],
													"staicReportList" => $staicReportList ?? [],
													"tourOperators" => $tourOperators ?? [],
													"tourPackages" => $tourPackages ?? [],
													"uTypeAdmin" => $uTypeAdmin ?? false,
													"uTypeAffiliate" => $uTypeAffiliate ?? false,
													"adminStaicReportList" => $adminStaicReportList ?? [],
													"tourOperatorId" => $tourOperatorId ?? 1
													])



	@include("/users/common/partials/reports/_report_drop_filters", ["affiliates" => $affiliates ?? [],
																"tourPackages" => $tourPackages ?? [],
																"uTypeAdmin" => $uTypeAdmin ?? false,
																"uTypeAffiliate" => $uTypeAffiliate ?? false,
																"promocodes" => $promocodes ?? []  ])
</section>

	@include('users.admin.invoice._updateInvoice')

	<section class="select-edit-booking py-6 overflow-y-auto hidden bg-[#F9FAFB]" id="sectionForDisplay">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full lg:w-3/4 max-w-full px-3 user-details">
					<h4 id="reportHeading" class="hidden font-normal text-gray-600 text-2xl max-[1350px]:text-lg mb-2">Summary Report</h4>
					<div class="text-sm" id="final_msg" style="color: green;"> </div>
				</div>
				<div class="w-full lg:w-1/4 max-w-full px-3 booking-details">
					<div class="row">
						<div class="btn-group btn-group-sm flex items-center justify-end" role="group" aria-label="Report Actions">
							<button id='syncRun' hidden class='btn btn-secondary ml-2 btn-info-small group'><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_44_6)"><path class="group-hover:!stroke-[#60BEBD]"  d="M1.75 7C1.75 5.60761 2.30312 4.27225 3.28769 3.28769C4.27225 2.30312 5.60761 1.75 7 1.75C8.46771 1.75552 9.87644 2.32821 10.9317 3.34833L12.25 4.66667" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#60BEBD]" d="M12.2477 1.75V4.66667H9.33105" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#60BEBD]" d="M12.25 7C12.25 8.39239 11.6969 9.72776 10.7123 10.7123C9.72776 11.6969 8.39239 12.25 7 12.25C5.53229 12.2445 4.12356 11.6718 3.06833 10.6517L1.75 9.33333" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path class="group-hover:!stroke-[#60BEBD]" d="M4.66667 9.33105H1.75V12.2477" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_44_6"><rect width="14" height="14" fill="white"/></clipPath></defs></svg></button>
							<button id='btnDownload' class='btn btn-secondary ml-2 btn-info-small group'><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_50_2)"><path class="group-hover:!fill-[#60BEBD]" fill-rule="evenodd" clip-rule="evenodd" d="M1.54009 9.52002C1.92669 9.52002 2.24009 9.83343 2.24009 10.22V11.62C2.24009 11.8057 2.31383 11.9837 2.44513 12.115C2.57639 12.2463 2.75443 12.32 2.94009 12.32H11.3401C11.5257 12.32 11.7037 12.2463 11.835 12.115C11.9664 11.9837 12.0401 11.8057 12.0401 11.62V10.22C12.0401 9.83343 12.3535 9.52002 12.7401 9.52002C13.1267 9.52002 13.4401 9.83343 13.4401 10.22V11.62C13.4401 12.1769 13.2189 12.7111 12.825 13.1049C12.4312 13.4988 11.897 13.72 11.3401 13.72H2.94009C2.38315 13.72 1.84899 13.4988 1.45515 13.1049C1.06135 12.7111 0.840088 12.1769 0.840088 11.62V10.22C0.840088 9.83343 1.15349 9.52002 1.54009 9.52002Z" fill="white"/><path class="group-hover:!fill-[#60BEBD]" fill-rule="evenodd" clip-rule="evenodd" d="M3.01166 5.26264C3.29414 4.96584 3.75213 4.96584 4.03461 5.26264L7.1398 8.52527L10.245 5.26264C10.5274 4.96584 10.9855 4.96584 11.2679 5.26264C11.5504 5.55944 11.5504 6.04065 11.2679 6.33745L7.65124 10.1375C7.36878 10.4342 6.91078 10.4342 6.62832 10.1375L3.01166 6.33745C2.72919 6.04065 2.72919 5.55944 3.01166 5.26264Z" fill="white"/><path class="group-hover:!fill-[#60BEBD]" fill-rule="evenodd" clip-rule="evenodd" d="M7.13994 0.280029C7.52658 0.280029 7.83994 0.602388 7.83994 1.00003V9.64C7.83994 10.0377 7.52658 10.36 7.13994 10.36C6.75335 10.36 6.43994 10.0377 6.43994 9.64V1.00003C6.43994 0.602388 6.75335 0.280029 7.13994 0.280029Z" fill="white"/></g><defs><clipPath id="clip0_50_2"><rect width="14" height="14" fill="white"/></clipPath></defs></svg></button>
							@if(!$uTypeAffiliate)<button id='openInvoiceModal' class='btn btn-secondary ml-2 btn-info-small'><i class='fa fa-receipt'></i></button>@endif
							<button onclick="printThis()" id="btnPrint" class="btn btn-secondary ml-2 btn-info-small"><i class="fa fa-print"></i></button>
						</div>
					</div>
				</div>
			</div>
		</div>
	</section>

	<!-- Summary Report -->
	<section class="booking-action-sec overflow-y-auto hidden bg-[#F9FAFB]" id="summaryreport">
		<div class="w-full container" id="printAreaReport">
			<div class="max-[1350px]:p-4 px-[20px] py-[30px] border rounded-xl bg-white mb-6">
				<div class="flex flex-wrap -mx-3 items-center" id="printDivReport" >
					@if($tourOperatorLogo != '')
					<img src="{{ $tourOperatorLogo }}" class="max-w-[120px] w-[120px]">
					@endif
					<div class=" {{ ($tourOperatorLogo != '') ? 'w-[calc(100%-120px)]' : 'w-full'}} max-w-full px-3">
						<h2 class="font-normal text-gray-600 text-2xl max-[1350px]:text-lg mb-2" id="reportHeadingPrintDiv"></h2>
						<p class="text-sm text-[#4B5563] mb-1" id="printPackage" ></p>
						<div id="printDates" class="text-sm text-[#4B5563]"></div>
						<div id="printFilterBy" class="text-sm text-[#4B5563]" style="display:none;"></div>
					</div>
					
				</div>
			</div>
			
			<div class="max-[1350px]:p-4 px-[20px] py-[30px] border rounded-xl bg-white mb-6">
				<div class="flex flex-wrap -mx-3 details-table">
					<div class="w-full max-w-full px-3">
						<div class="table-respons" id="mainTableDiv"></div>

						<!-- Affiliate Invoice Report Tabs -->
						<div id="affiliateInvoiceTabs" class="hidden">
							<div class="border-b border-gray-200 mb-4">
								<nav class="flex space-x-4" aria-label="Tabs">
									<button type="button" class="affiliate-tab-btn px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-transparent hover:border-gray-300" data-tab="billable" onclick="switchAffiliateTab('billable')">
										Billable Bookings
									</button>
									<button type="button" class="affiliate-tab-btn px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-transparent hover:border-gray-300" data-tab="invoices" onclick="switchAffiliateTab('invoices')">
										Current Invoices
									</button>
									<button type="button" class="affiliate-tab-btn px-4 py-2 text-sm font-medium rounded-t-lg border-b-2 border-transparent hover:border-gray-300" data-tab="dueAmount" onclick="switchAffiliateTab('dueAmount')">
										Affiliate Amount Owed
									</button>
								</nav>
							</div>

							<!-- Tab Content: Billable Bookings -->
							<div id="affiliateTab-billable" class="affiliate-tab-content"></div>

							<!-- Tab Content: Current Invoices -->
							<div id="affiliateTab-invoices" class="affiliate-tab-content hidden">
								<div id="invoicesLoading" class="text-center py-8">
									<i class="fa fa-spinner fa-spin fa-2x"></i>
									<p class="mt-2 text-gray-600">Loading invoices...</p>
								</div>
								<div id="invoicesTableContainer" class="hidden"></div>
							</div>

							<!-- Tab Content: Affiliate Amount Owed -->
							<div id="affiliateTab-dueAmount" class="affiliate-tab-content hidden">
								<div id="dueAmountLoading" class="text-center py-8">
									<i class="fa fa-spinner fa-spin fa-2x"></i>
									<p class="mt-2 text-gray-600">Loading affiliate amounts...</p>
								</div>
								<div id="dueAmountTableContainer" class="hidden"></div>
							</div>
						</div>

						<!-- daily-modal-container is used to store a modal for daily act. report filters form --->
						<div id="daily-modal-container"> </div>

						<div class="table-respons" id="bookingTableDiv1">
							<div class="w-full overflow-y-auto">
								<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForBookingDetails" style="display: none;">
									<thead>
									<tr>
										<th class="th-custom-css">ID</th>
										<th class="th-custom-css">Name</th>
										<th class="th-custom-css">Booking Date</th>
										<th class="th-custom-css">Create Date</th>
										<th class="th-custom-css">Tourists</th>
										<th class="th-custom-css">Amount</th>
										<th class="th-custom-css">Payment</th>
										<th class="th-custom-css">Status</th>
									</tr>
									</thead>
									<tbody id="bookingTableBody">

									</tbody>
								</table>
							</div>
						</div>

						<div class="table-respons" id="bookingTableDiv2">
							<div class="w-full overflow-y-auto">
								<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForRefundDetails" style="display: none;">
									<thead>
									<tr>
										<!-- <th class="th-custom-css">#</th> -->
										<th class="th-custom-css">ID</th>
										<th class="th-custom-css">Name</th>
										<th class="th-custom-css">Booking Date</th>
										<th class="th-custom-css">Phone</th>
										<th class="th-custom-css">Email</th>
										<th class="th-custom-css">Amount</th>
										<th class="th-custom-css">Refund</th>
										<!-- <th class="th-custom-css">Refund type</th> -->
										<th class="th-custom-css">Booking Status</th>
										<th class="th-custom-css">Refund Date</th>
									</tr>
									</thead>
									<tbody id="bookingTableBody">

									</tbody>
								</table>
							</div>
						</div>

						<div class="table-respons" id="bookingTableDiv3">
							<div class="w-full overflow-y-auto">
								<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForFarHarborDetails" style="display: none;">
									<thead>
									<tr>
										<!-- <th class="px-4 py-2.5 bg-gray-50 font-bold text-black text-sm text-left border border-[#E5E7EB] border-t-0 !shadow-none whitespace-nowrap">#</th> -->
										<th class="th-custom-css">Booking</th>
										<th class="th-custom-css">Booking Date</th>
										<th class="th-custom-css">Via</th>
										<th class="th-custom-css">Amount</th>
										<th class="th-custom-css">Customer Name</th>
										<th class="th-custom-css">Phone</th>
										<th class="th-custom-css">Email</th>
										<th class="th-custom-css">Booking Status</th>
										<th class="th-custom-css">Comments</th>
									</tr>
									</thead>
									<tbody id="bookingTableBody">

									</tbody>
								</table>
							</div>
						</div>

						<div class="table-respons" id="bookingTableDiv4">
							<div class="w-full overflow-y-auto">
								<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableFortipDetails" style="display: none;">
									<thead>
									<tr>
										<!-- <th class="px-4 py-2.5 bg-gray-50 font-bold text-black text-sm text-left border border-[#E5E7EB] border-t-0 !shadow-none whitespace-nowrap">#</th> -->
										<th class="th-custom-css">ID</th>
										<th class="th-custom-css">Name</th>
										<th class="th-custom-css">Phone</th>
										<th class="th-custom-css">Booking Date</th>
										<th class="th-custom-css">Tip Date</th>
										<th class="th-custom-css">Staff Name</th>
										<th class="th-custom-css">Num of Person</th>
										<th class="th-custom-css">Amount</th>
										<th class="th-custom-css">Tip Amount</th>
									</tr>
									</thead>
									<tbody id="bookingTableBody">

									</tbody>
									<tfoot>
									<tr>
										<th class="th-custom-css" colspan="7" style="text-align:right"></th>
										<th class="th-custom-css"></th>
										<th class="th-custom-css"></th>
									</tr>
									</tfoot>
								</table>
							</div>
						</div>

						<div class="table-respons" id="bookingTableDiv5">
							<div class="w-full overflow-y-auto">
								<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForTransaction" style="display: none;">
									<thead>
									<tr>
										<th class="th-custom-css !min-w-16">ID</th>
										<th class="th-custom-css">Tour Date</th>
										<th class="th-custom-css">Status</th>
										<th class="th-custom-css">Payment Total</th>
										<th class="th-custom-css">Discount</th>
										<th class="th-custom-css">Refund Amount</th>
										<th class="th-custom-css">Net Total</th>
									</tr>
									</thead>
									<tbody id="bookingTransactionTableBody">

									</tbody>
								</table>
							</div>
						</div>

						<div class="table-respons" id="bookingTableDiv6">
							<div class="w-full">
								<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForTransactionSummary" style="display: none;">
									<thead>
									<tr>
										<th class="th-custom-css"> Tour Date</th>
										<th class="th-custom-css"> Transaction Date</th>
										<th class="th-custom-css"> Confirmation </th>
										<th class="th-custom-css"> Status </th>
										<th class="th-custom-css"> Contact Name </th>
										<th class="th-custom-css"> Tourist </th>
										<th class="th-custom-css"> Purchase Type </th>
										<th class="th-custom-css"> Gross </th>
										<th class="th-custom-css"> Discount </th>
										<th class="th-custom-css"> Fees </th>
										<th class="th-custom-css"> Refund </th>
										<th class="th-custom-css"> Net </th>

									</tr>
									</thead>
									<tbody id="bookingTableBody">

									</tbody>

								</table>
							</div>
						</div>

						<div class="table-respons" id="bookingTableDiv7">
							<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForaffiliateSeatsReport" style="display: none;">
								<thead>
								<tr>
									<th class="th-custom-css"> Name</th>
									<th class="th-custom-css"> Package </th>
									<th class="th-custom-css"> Slot Date </th>
									<th class="th-custom-css"> Assigned Seats </th>
									<th class="th-custom-css"> Booked Seats </th>
									<th class="th-custom-css"> Cancelled Seats </th>
									<th class="th-custom-css"> Fulfilled Seats </th>
									<th class="th-custom-css"> No Show </th>
								</tr>
								</thead>
								<tbody id="bookingTableBody">

								</tbody>

							</table>
						</div>

						<div class="table-respons" id="bookingTableDiv8">
							<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForAddonsPackageReport" style="display: none;">
								<thead>
								<tr>
									<th class="th-custom-css"> Add-On Type </th>
									<th class="th-custom-css"> Tour Package Name </th>
									<th class="th-custom-css"> Number of booking </th>
									<th class="th-custom-css"> Priced </th>
									<th class="th-custom-css"> Price </th>
									<th class="th-custom-css"> Paid </th>
									<th class="th-custom-css"> Refunded </th>
									<th class="th-custom-css"> Net </th>
								</tr>
								</thead>
								<tbody id="bookingTableBody">

								</tbody>

							</table>
						</div>

						<div class="table-respons" id="bookingTableDiv9">
							<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForAddonsPackageDetailsReport" style="display: none;">
								<thead>
								<tr>
									<th class="th-custom-css"> Add-On Type </th>
									<th class="th-custom-css"> Tour Package Name </th>
									<th class="th-custom-css"> Booking IDs </th>
									<th class="th-custom-css"> Priced </th>
									<th class="th-custom-css"> Price </th>
									<th class="th-custom-css"> Paid </th>
									<th class="th-custom-css"> Refunded </th>
									<th class="th-custom-css"> Net </th>
								</tr>
								</thead>
								<tbody id="bookingTableBody">

								</tbody>

							</table>
						</div>

						<div class="table-respons" id="bookingTableDiv10">
							<table class="table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left" id="datatableForActivityLogsReport" style="display: none;">
								<thead>
								<tr>
									<th class="th-custom-css"> ID </th>
									<th class="th-custom-css"> Role </th>
									<th class="th-custom-css"> User Name </th>
									<th class="th-custom-css"> Event Type </th>
									<th class="th-custom-css"> Data </th>
									<th class="th-custom-css"> Created Date and Time </th>
									<th class="th-custom-css"> Auditable Type </th>
								</tr>
								</thead>
								<tbody id="bookingTableBody">

								</tbody>

							</table>
						</div>

					</div>
				</div>
			</div>
		</div>

		@if(!$uTypeAffiliate)
		<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-invoice" tabindex="-1" role="dialog" aria-hidden="true">
			<div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
				<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
					<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
						<h5 class="text-lg font-semibold text-[#111827]">Generate Invoice</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
					</div>

					<!-- Loading State -->
					<div id="invoice-modal-loading" class="modal-body p-4 text-center">
						<div class="py-8">
							<i class="fa fa-spinner fa-spin fa-2x"></i>
							<p class="mt-2 text-gray-600">Checking invoice status...</p>
						</div>
					</div>

					<!-- Warning Message (shown when invoice exists) -->
					<div id="invoice-modal-warning" class="modal-body p-4 hidden">
						<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
							<div class="flex items-start">
								<svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
									<path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
								</svg>
								<div>
									<h4 class="text-yellow-800 font-semibold">Invoice Already Exists</h4>
									<p id="invoice-warning-message" class="text-yellow-700 text-sm mt-1"></p>
								</div>
							</div>
						</div>
						<div class="text-center">
							<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
						</div>
					</div>

					<!-- Invoice Form (shown when no existing invoice) -->
					<div id="invoice-modal-form" class="hidden">
						<form method="post" action="{{ route( ($uTypeAdmin ? 'admin.' : '') .'tour-operator.invoice.store') }}">
							@csrf
							<div class="modal-body p-4">
								<!-- Invoice Info Summary -->
								<div class="bg-gray-50 rounded-lg p-4 mb-4">
									<div class="grid grid-cols-2 gap-4 text-sm">
										<div>
											<span class="text-gray-500">Affiliate:</span>
											<span id="invoice_info_affiliate" class="font-medium text-gray-900 ml-1"></span>
										</div>
										<div>
											<span class="text-gray-500">Amount:</span>
											<span id="invoice_info_amount" class="font-medium text-gray-900 ml-1"></span>
										</div>
										<div>
											<span class="text-gray-500">From Date:</span>
											<span id="invoice_info_from_date" class="font-medium text-gray-900 ml-1"></span>
										</div>
										<div>
											<span class="text-gray-500">To Date:</span>
											<span id="invoice_info_to_date" class="font-medium text-gray-900 ml-1"></span>
										</div>
									</div>
								</div>

								<div class="">
									<label for="invoice_due_date" class="form-label form-custom-label">Due Date</label>
									<input name="due_date" id="invoice_due_date" class="form-control form-custom-input" placeholder="Add Due Date" required>
								</div>
								<input type="hidden" name="tour_operator_id" id="invoice_tour_operator" required>
								<input type="hidden" name="tour_package" id="invoice_package" required>
								<input type="hidden" name="customer_id" id="invoice_affiliate" required>
								<input type="hidden" name="from_date" id="invoice_from_date" required>
								<input type="hidden" name="to_date" id="invoice_to_date" required>
								<input type="hidden" name="amount" id="invoice_amount" required>
							</div>

							<div class="modal-footer px-4 pb-4">
								<button type="submit" class="btn btn-main btn-main-sm btn-primary-small w-full">Generate</button>
							</div>
						</form>
					</div>
				</div>

			</div>
		</div>
		@endif
	</section>
	<!-- End SummaryReport -->

	<!-- Modal HTML -->
	<div class="modal fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="jsonPreviewModal" tabindex="-1" role="dialog" aria-labelledby="jsonPreviewModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-fullscreen max-w-[1155px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
			<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
				<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
					<h5 class="modal-title text-lg font-semibold text-[#111827]" id="jsonPreviewModalLabel">JSON Preview</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
				</div>
				<div class="modal-body p-4">
					<pre id="jsonPreviewText" style="white-space: pre-wrap; word-wrap: break-word;"></pre>
				</div>
			</div>
		</div>
	</div>
@endsection


@section('css-libraries')
	<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/datatables-combo/datatables.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/daterange/daterangepicker.2.1.25.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/datatables-combo/datatables.checkbox.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('css/forms/multi-dropdown.css') }}" rel="stylesheet" type="text/css" />
	<link rel="stylesheet" href="https://cdn.datatables.net/rowgroup/1.1.4/css/rowGroup.dataTables.min.css">
@endsection

@section('css-custom')
	<link href="{{ asset('css/user-common/report-print.css') }}" media="print" rel="stylesheet" />
	<style>
		#revenue-report-data-table-body tr td {
			font-size: 13px;
		}
		.ota-report .btn-sm {
			height: 34px;
			min-width: 20px;
			padding: 8px 15px;
		}
	</style>
@endsection

@section('js-libraries')

	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	<script src="{{ asset('plugins/lodash/lodash.4.17.2.js')}}"></script>
	<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
	<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/pdfmake/pdfmake.min.0.2.7.js') }}"></script>
	<script src="{{ asset('plugins/pdfmake/pdfmakefonts.min.0.2.7.js') }}"></script>
	<script src="{{ asset('plugins/datatables-combo/datatables.js')}}"></script>
	<script src="{{ asset('plugins/printThis/printThis.js') }}"></script>
	<script src="{{ asset('plugins/chart.js-2.9.4/Chart.min.js') }}"></script>
	<script src="{{ asset('plugins/daterange/daterangepicker.2.1.25.js')}}"></script>
	<script src="{{ asset('plugins/datatables-combo/datatables.checkbox.1.2.12.js') }}"></script>
	<script src="https://cdn.datatables.net/rowgroup/1.1.4/js/dataTables.rowGroup.min.js"></script>

	<script src="{{ asset('js/user-common/reports/promotion-report.js') }}"></script>
	<script src="{{ asset('js/user-common/reports/revenue-report.js') }}"></script>
	<script src="{{ asset('js/user-common/reports/transaction-report.js') }}"></script>
	<script src="{{ asset('js/user-common/reports/staff-booking-report.js') }}"></script>
	<script src="{{ asset('js/user-common/reports/invoice-insights-report.js') }}?v={{ @filemtime(public_path('js/user-common/reports/invoice-insights-report.js')) ?: time() }}"></script>
	<script src="{{ asset('js/user-common/reports/stripe-refund-report.js') }}"></script>
	<script src="{{ asset('js/user-common/reports/revenue-summation-report.js') }}"></script>
	<script src="{{ asset('js/utils/helpers.js')  }}"></script>
	<script src="{{ asset('js/forms/multi-dropdown.js') }}" ></script>
	<script src="{{ asset('js/user-common/reports/ota-report.js') }}"></script>

@endsection

@section('js-custom')
<script>


	const adminStatus = "{{$uTypeAdmin}}"
	const userSession = '{{ session("userType") }}';
	const invoiceUpdateUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.invoice.update', [ 'invoice' => ':id'])}}"
	const invoiceCheckExistsUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.invoice.check-exists')}}"
	const invoiceListUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.invoice.list')}}"
	const affiliateDueAmountUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.invoice.affiliate-due-amount-ahr')}}"
	const invoiceShowUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.invoice.show', ['uuid' => '_UUID_'])}}"

	var isAdmin = false;
	var isCashFees = '{{ $isCashFees }}';
	var getCustomersUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.get-customers') }}/";
	var closureReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.closure-report', [ 'fromDate' => '_DATE_', 'toDate' => '_TO_DATE_' ]) }}/";
	var availabilityReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.today_avialability_report', [ 'fromDate' => '_DATE_', 'toDate' => '_TO_DATE_' ]) }}/";
	var availabilityDetailsReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.today_avialability_report-details', [ 'fromDate' => '_DATE_' ]) }}/";
	var bookingsReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.booking-summary-list-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}/";
	var bookingsSummaryReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.filtered-data-for-summary-details', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}/";
	// var financialReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.financial-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}/";
	var refundReportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.refund-payment-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ])}}"
	var fareharborbookingReportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.fareharabor-booking-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ])}}"
	var pendingpaymentReportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.pending-payment-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ])}}"
	@if($uTypeAffiliate)
		var affiliatereportUrl = "{{route('affiliate.report.affiliate-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'customerId' => '_CUSTOMER_ID_' ])}}"
		var affiliatecomprehensivereportUrl = "{{route('affiliate.report.affiliate-comprehensive-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'customerId' => '_CUSTOMER_ID_' ])}}"
		var affiliateSeatsReportUrl = "{{route('affiliate.report.affiliate-seats-report')}}"
		var affiliateSeatsReportDetailsUrl = "{{ route('affiliate.report.affiliate-seats-report-detail') }}";
		var affiliateSeatsSyncUrl = "{{ route('affiliate.report.affiliate-seats-sync') }}";
	@else
		var affiliatereportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.affiliate-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'customerId' => '_CUSTOMER_ID_' ])}}"
		var affiliatecomprehensivereportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.affiliate-comprehensive-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'customerId' => '_CUSTOMER_ID_' ])}}"
		var affiliateSeatsReportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.affiliate-seats-report')}}"
		var affiliateSeatsReportDetailsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.affiliate-seats-report-detail') }}";
		var affiliateSeatsSyncUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.affiliate-seats-sync') }}";
	@endif

	var affiliateallreportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.affiliate-all-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'customerId' => '_CUSTOMER_ID_' ])}}"
	var cancelbookingreportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.cancel-booking-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_'])}}"
	var emailreportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.email-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ])}}"
	var dailyreportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.daily-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ])}}"
	var dailyreportdetailsUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.daily-report-details', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ])}}"
	var revenueReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.revenue-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}"
	var promotionReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.promotion-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}"
	var invoiceInsightsReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.invoice-insights-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}"
	var otaReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.ota-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}"

	var action = 'Add';
	var tourOpearatorName = "{{ $tourOperatorName }}";
	var getTourSlotTimesByDateUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.get-tour-slot-times-by-date') }}";
	var customerSearchUrl = "{{ route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'search-customer') }}";
	var tipreportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.tip-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}";
	var dailyactivityreportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.daily-activity-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}";
	var invalidbookingsreportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.invalid-bookings-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}";

	// Check if we need to preselect a report (coming from v2 structure)
	$(document).ready(function() {
		@if(isset($preselectedReport) && $preselectedReport)
			$("#select-report").val("{{ $preselectedReport }}").trigger("change");
		@endif
	});
	var tipsummaryreportUrl1 = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.tip-summary-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}";
	var lowerAntelopeCanyonClosureUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.lower-antelope-canyon-closure-email') }}";
	@if($uTypeAdmin)
		//var taxCollectionReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.tax-collection') }}";
		var sofrtwarefeeReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.sofrtware-fee') }}";
		var getTourOperatorAffiliateUrl = "{{ route('admin.get-tour-operator-affiliate-data', [ 'tourOperatorId' => 0 ]) }}";
	@endif
	var financialReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.financial-report') }}";
	var paymentsReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.payments') }}";

	var cashcollectionReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.cashcollection') }}";
	var transactionReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.transactionreport') }}";
	var transactionSummaryReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.transactionsummaryreport') }}";
	var transactionReportBookingDetailsUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.history-and-transactions', [ 'bookingId' => 0 ]) }}";
	var addonsPackageReportReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.addonspackagereport') }}";
	var addonsPackageDetailsReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.addonspackagedetailsreport') }}";
	var addonsPackageValueReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.addonspackagevaluereport') }}";
	@if($uTypeAffiliate)
		var bookingInfoUrl = "{{ route('affiliate.booking.show', [ 'booking' => 0 ]) }}";
	@else
		var bookingInfoUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.show', [ 'booking' => 0 ]) }}";
	@endif
	var wirecollectionReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.wirecollection') }}";
	var taxCollectionReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.tax-collection') }}";
	var staffBookingReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.staff-booking-report', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_' ]) }}"
	var totalSansDiscount = 0;
	var rateGroups = @json($rateGroups);
	@if($uTypeAdmin)
		var taxes = @json($taxes);
	@endif
	var touristsGroups = @json($touristsGroups);
	var serverDT = moment('{{ date('Y-m-d H:i:s') }}');
	var serverTZ = '{{ date('P') }}';
	var invoiceTourOperator = null;
	var invoicePackage = null;
	var invoiceAffiliate = null;
	var invoiceFromDate = null;
	var invoiceToDate = null;
	var invoiceAmount = null;
	var bookingids = null;
	var otaPlatforms = @json($ota_platforms);
	const routePrefix = '{{ routePrefixForJs() }}';

	@if($uTypeAdmin)
		isAdmin = true;
		var getTourOperatorDataUrl = "{{ route('admin.get-tour-operator-data', [ 'tourOperatorId' => 0 ]) }}";
		var getTourOperatorReportUrl = "{{ route('admin.get-tour-operator-reports', [ 'tourOperatorId' => 0 ]) }}";
	@endif
	var activityLogsReportUrl = "{{route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.report.activity-logs-report')}}";
	@if( isset($tourOperators) && count($tourOperators) > 0 )
		const tourOperatorDefault =  @json($tourOperators[0])
	@else
		const tourOperatorDefault = { id: 1 };
	@endif


/**
 * rednderDateRangePicker
 * display combo datepicker for start / end date.
 * note you can add custom ranges to the default list.
*/
const rednderDateRangePicker = () => {
        const start = moment();
        const end = moment().add(31, 'days');
        const setStartAndEndDate = (start, end) => {
            $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
        }

        $('#reportrange').daterangepicker({
            startDate: start,
            endDate: end,
            ranges: {
              'Today': [moment(), moment()],
              'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
              'Last 7 Days': [moment().subtract(6, 'days'), moment()],
              'Last 30 Days': [moment().subtract(29, 'days'), moment()],
              'This Month': [moment().startOf('month'), moment().endOf('month')],
              'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, setStartAndEndDate);

        setStartAndEndDate(start, end);
};

const rednderDateTimeRangePicker = () => {
    const start = moment().startOf('hour');
    const end = moment().startOf('hour').add(32, 'hour');

    const setStartAndEndDate = (start, end) => {
        $('#reportdatetimerange span').html(start.format('MMMM D, YYYY hh:mm A') + ' - ' + end.format('MMMM D, YYYY hh:mm A'));
    }

    $('#reportdatetimerange').daterangepicker({
        timePicker: true,  
        timePicker24Hour: false, 
        timePickerIncrement: 1,
        startDate: start,
        endDate: end,
        locale: {
            format: 'MMMM D, YYYY hh:mm A', 
        },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
        }
    }, setStartAndEndDate);

    setStartAndEndDate(start, end);
};

/**
 * getCustomers
 * populate the Affilate dropdown with Affilate list
*/
const getCustomers = (selectedReport) => {

	let reports = [ 'Affiliate Booked Report',
					'Affiliate Seats Report',
					'Affiliate Invoice Report',
					'Affiliate Comprehensive Report',
					'Affiliate Invoice Insights Report'];
	if(isAdmin && ( reports.includes(selectedReport.trim()) )) {
		let url = `${getCustomersUrl}${$("#tour_operator").val()}/${$("#tour_package").val()}`;
		$.get(url)
			.done(function(data) {
				$('#customer-data option:not(:first)').remove();
				var itemsList = data;
				itemsList.sort(function(a, b) {
					return a.name.localeCompare(b.name);
				});
				var options = "<option value='0'>All</option>";
				for (var i = 0; i < itemsList.length; i++) {
					options += '<option value= "' + itemsList[i].id + '">' + itemsList[i].name + '</option>';
				}
				$('#customer-data').html(options);
			});
	}
};

/**
 * search the report list
 * this is an input in _report_select partial
 * on keyup search the report dropdown and only list items that contain the
 * the search term
*/
const searchReportDropDown = () => {
	$(document).off('keyup', '#search-report-dropdown').on('keyup', '#search-report-dropdown', (evt)=>{
		let val = evt.target.value.toLowerCase();
		let ele = document.getElementById("report-list");
		if( val.trim() === '' ){
			[...ele.children].forEach((item) => {
				item.classList.remove("hidden");
			})
			return true;
		}

		[...ele.children].forEach((item) => {
			if (item.innerText.toLowerCase().includes(val)) {
					item.classList.remove("hidden");
			}else{
				if( !item.classList.contains('hidden')){
					item.classList.add("hidden");
				}
			}
		})
	});

};

$(document).ready(()=>{

		//set up the date range picker
		rednderDateRangePicker();
		//add search report dropdown listner
		searchReportDropDown();

		//add listeners to report select
		$(document).off('click', '#report-list li.nat-list-item ').on('click', '#report-list li.nat-list-item ',
			(evt)=> {
				const val = evt.target.getAttribute('data-value');
				if(val == "Activity Logs Report"){
		            rednderDateTimeRangePicker();
				} else { 
					rednderDateRangePicker();
				}
				$("#select-report").val(val).trigger("change");
				$('.offcanvas').offcanvas('hide');


			}
		);

		//disable auto sorting for booking report
		$('#datatableBookingReport').DataTable({ "ordering": false, info: false});

		// not sure why this is added?
		setInterval(function() { serverDT.add(15, 'seconds'); }, 15000);

		//Current Date fetch in textbox
		let date = new Date().toUTCString().slice(5, 16);
		$('#toDate').val(moment('{{ $date }}').format('MM/DD/YYYY'));
		$('#fromDate').val(moment('{{ $date }}').format('MM/DD/YYYY'));
		$("#toDate").datepicker( { dateFormat: "mm/dd/yy"});
		$("#fromDate").datepicker({ autoclose: true, dateFormat: "mm/dd/yy"});

		// add click event to generate report btn
		$("#generate-report-btn").click((_evt)=>reportSearch());

		// add click event to smaller drop down on main filter tool bar
		$(document).on('click', '.tour-package-update', (evt)=>{
				const val = evt.target.getAttribute("data-value");
				$('#tour_package').val(`${val}`).trigger("change");
			});

		// I dont understand why we have to check if admin here to show list of tour operators I feel this is a remneant from
		// when all the tour operators were all on one db
		@if($uTypeAdmin)

			getTourPackages(tourOperatorDefault.id, false);

			$('#tour_operator').on('change', function() {
				$('#tourOperatorValue').text("Tour Operator: " + this.options[this.selectedIndex].text);
			});

		@endif

		$('#tour_package').on('change', function() {
			if(this.options[this.selectedIndex].value == ''){
				$('#tourPackageValue').html('All Tour Packages');
			}else{
				$('#tourPackageValue').html(this.options[this.selectedIndex].text);
			}
		});
		$('#user_name').select2({
            placeholder: "Select User Name",
            allowClear: true
        });

		$('#ota-platform-dropdown-menu-list .dropdown-item').on('click', function(e) {
			e.preventDefault();

			// Get the selected text and value
			var selectedText = $(this).text();
			var selectedValue = $(this).data('value');

			// Update the button text
			$('#otaValue').text(selectedText);

			// Optionally store the value somewhere (hidden input or attribute)
			$('#otaValue').attr('data-selected-value', selectedValue);
			$("#otaPlatform").val(selectedValue);
		});

		});

		$("#select-report").change(function() {

			const selectedReport = $("#select-report").val();
			document.title = selectedReport + ' | {{ config('app.name') }}';
			$("#syncRun").attr("hidden", true).css("display", "none");
			$("#selected-report-name").show().html( `${truncatedString(selectedReport, 25)}`);
			$("#selected-report-wrap").removeClass("flex-grow-1");
			$("#tour-package-btn-select").show();
			$("#btnDownload").prop("disabled", false);

			// set default reset items here
			$("#openInvoiceModal").css("display", "none");
			$("#viewBy").css("display", "none");
			$('#viewByReportType').css("display", "none");
			$("#fromAndToDateDiv").css("display", "none");
			$("#fromDateDiv").css("display", "none");
			$("#toDateDiv").css("display", "none");
			$("#promotionreport").css("display", "none");
			$("#add-on-report").css("display", "none");
			$("#affiliatereport").css("display", "none");
			$("#BookingIdDiv").css("display", "none");
			$("#btnserchprint").css("display", "block");
			$("#viewByEvent").css("display", "none");
			$("#fromToDateTimeDiv").css("display", "none");
			$("#user-name-btn-select").css("display", "none");
			$("#ota_platforms").css("display", "none");
			$('#checkUnpaidDiv').css("display", 'none');
			$("#printFilterBy").hide();
			$('#affiliateInvoiceTabs').addClass('hidden');

			switch($("#select-report").val()) {
				case "Payment Breakdown Report":
					// Store selected report in session and redirect to new report structure (v2)
					$.post(`/${routePrefix}/report/set-selected-report`, {
						_token: '{{ csrf_token() }}',
						report: "Payment Breakdown Report"
					}).done(function() {
						window.location.href = `/${routePrefix}/report/v2`;
					});
					return; // Stop execution
				case "Affiliate Booked Report":
				case "Affiliate Seats Report":
				case "Affiliate Invoice Report":
				case "Affiliate Comprehensive Report":
				case "Affiliate Report":
				case "Affiliate Invoice Insights Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#affiliatereport").css("display", "block");
				break;
				case "Email Report":
				case "Closure Emails Report":
				case "Availability Report":
				case "Today Availability Details Report":
				case "Permit Fee Report":
				case "Permit Fee Details Report":
				case "Summary Report":
				// case "Cash Collection Report":
				// case "Stripe Payment Report":
				// case "Check/Wire Collection Report":
				case "FareHarbor Booking Report":
				case "Financial Report":
				case "Booking Details Report":
				case "Tax Collection Report":
				case "Software Fee Report":
				case "Refund payment":
				case "Failed Payment Report":
				case "Cancel Booking Report":
				case "Tip Report":
				case "Tip Summary Report":
				// case "Daily Activity Report":
				case "Staff Booking Report":
				case "Invalid Bookings Report":
				// case "Daily Activity Report":
					$("#fromAndToDateDiv").css("display", "block");
				break;
				case "OTAs Booking Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#ota_platforms").css("display", "block");
					$("#viewBy").css("display", "block");
					$('#viewby-select option[value="purchasedate"]').removeAttr('selected');
					$('#viewby-select option[value="servicesrendered"]').attr("selected", true);
				break;
				/*case "Daily Activity Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
				break;*/
				case "Revenue Summary Report":
					$("#btnDownload").prop("disabled", true);
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
				break;
				case "Revenue Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
					$('#viewby-select option[value="purchasedate"]').removeAttr('selected');
					$('#viewby-select option[value="servicesrendered"]').attr("selected",true);
				break;
				case "Promotion Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#promotionreport").css("display", "block");
					$('#viewby-select option[value="purchasedate"]').removeAttr('selected');
					$('#viewby-select option[value="servicesrendered"]').attr("selected",true);
				break;

				case "Stripe Refund Lookup":
					$("#BookingIdDiv").css("display", "block");
				break;

				case "Card Collection Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
					$('#viewby-select option[value="purchasedate"]').removeAttr('selected');
					$('#viewby-select option[value="servicesrendered"]').attr("selected",true);
					if(isCashFees=='1') {
						$('#viewByReportType').css("display", "block");
					}
				break;
				case "Cash Collection Report":
				case "Stripe Payment Report":
				case "Transaction Report":
				case "Check/Wire Collection Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
					$('#viewby-select option[value="servicesrendered"]').removeAttr('selected');
					$('#viewby-select option[value="purchasedate"]').attr("selected",true);
				break;
				case "Transaction Summary Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
					$('#viewby-select option[value="servicesrendered"]').removeAttr('selected');
					$('#viewby-select option[value="purchasedate"]').attr("selected",true);
					// Show unpaid checkbox only when Tour Date is selected
					toggleUnpaidCheckbox();
				break;
				case "Daily Activity Report":
					$("#fromAndToDateDiv").css("display", "block");
					$("#viewBy").css("display", "block");
					$('#viewby-select option[value="purchasedate"]').removeAttr('selected');
					$('#viewby-select option[value="servicesrendered"]').attr("selected",true);
				break;	
				case "Activity Logs Report":
					$("#viewByEvent").css("display", "block");
					$("#fromToDateTimeDiv").css("display", "block");
					$("#tour-package-btn-select").hide();
					$("#user-name-btn-select").css("display", "block");
				break;
				case "Add-ons Package Report":
					$("#add-on-report").css("display", "block");
					$("#fromAndToDateDiv").css("display", "block");
				break;
				case "Add-ons Package Details Report":
					$("#add-on-report").css("display", "block");
					$("#fromAndToDateDiv").css("display", "block");
				break;
			}

			getCustomers(selectedReport);
		});



		function params() {
			var params = {
				dateFrom: moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD'),
				dateTo: moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD'),
				selectedReport:  $("#select-report").val(),
			};

			@if($uTypeAdmin)
			if($('#tour_operator').val() == '') {
				swal('Please select a tour operator.', '', 'warning');
				return false;
			}

			params.tourOperator = $('#tour_operator').val();
			@endif

			if($('#tour_package').val() != '')
				params.tourPackage = $('#tour_package').val();

			return params;
		}

		function dateRangeParams() {
			var dateRange = reportDateRange();
			var params = {
				// dateFrom: moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD'),
				// dateTo: moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD'),
				dateFrom: dateRange.currentDate,
				dateTo: dateRange.toDate,
				selectedReport:  $("#select-report").val(),
			};

			@if($uTypeAdmin)
			if($('#tour_operator').val() == '') {
				swal('Please select a tour operator.', '', 'warning');
				return false;
			}

			params.tourOperator = $('#tour_operator').val();
			@endif

			if($('#tour_package').val() != '')
				params.tourPackage = $('#tour_package').val();

			if(isCashFees=='1'){
				params.type = $('#reportType').val();
			}

			return params;
		}

		// Function to get Filter By text based on current filter selections
		function getFilterByText() {
			var checkUnpaid = $('.checkUnpaid').is(":checked");
			var checkCard = $('.checkCard').is(":checked");
			var checkCash = $('.checkCash').is(":checked");
			var checkInvoiced = $('.checkInvoiced').is(":checked");
			var checkWire = $('.checkWire').is(":checked");
			var checkCancel = $('.checkCancel').is(":checked");
			var excludeExternal = $('.excludeExternal').is(":checked");
			var includeExternal = $('.includeExternal').is(":checked");
			var onlyShowExternal = $('.onlyShowExternal').is(":checked");
			
			// Check for unpaid filter first (highest priority)
			if (checkUnpaid) {
				return "Unpaid Bookings";
			}
			
			// Check for payment method filters
			var paymentMethods = [];
			if (checkCard) paymentMethods.push("Card");
			if (checkCash) paymentMethods.push("Cash");
			if (checkInvoiced) paymentMethods.push("Invoiced");
			if (checkWire) paymentMethods.push("Wire");
			
			// Check for booking source filters
			var sourceFilters = [];
			if (excludeExternal) sourceFilters.push("Exclude External");
			if (onlyShowExternal) sourceFilters.push("Only External");

			// Check for cancellation filter
			var otherFilters = [];
			if (checkCancel) otherFilters.push("Remove Cancelled");

			// Combine all filters
			var allFilters = [];
			if (paymentMethods.length > 0) allFilters.push(paymentMethods.join(", "));
			if (sourceFilters.length > 0) allFilters.push(sourceFilters.join(", "));
			if (otherFilters.length > 0) allFilters.push(otherFilters.join(", "));
			
			if (allFilters.length > 0) {
				return allFilters.join(" | ");
			} else {
				return "No Filter";
			}
		}

		// Function to toggle unpaid bookings checkbox visibility
		function toggleUnpaidCheckbox() {
			var selectedReport = $("#select-report").val();
			var viewBy = $("#viewby-select").val();

			if (selectedReport === "Transaction Summary Report" && viewBy === "servicesrendered") {
				$("#checkUnpaidDiv").css("display", "flex");
			} else {
				$("#checkUnpaidDiv").css("display", "none");
				$("#checkUnpaid").prop('checked', false); // Uncheck when hidden
			}
		}

		// Add event listener for viewBy select change
		$(document).on('change', '#viewby-select', function() {
			toggleUnpaidCheckbox();
		});

		function reportSearch() {

			switch($("#select-report").val()) {
				case "Availability Report": getDetailsTodayAvailability(); break;
				case "Closure Emails Report": getClosureReport(); break;
				case "Today Availability Details Report": getDetailsTodayAvailabilityDetails(); break;
				case "Financial Report": getFinancialReport(); break;
				case "Summary Report": getDetailsSummaryReport(); break;
				case "Booking Details Report": getDetailsSummaryDetails(); break;
				case "Tax Collection Report": taxCollectionReport(); break;
				// case "Payments Report": paymentsReport(); break;
				case "Cash Collection Report":
					cashCollectionReport(); break;
				case "Transaction Report":
					transactionReport(); break;
				case "Transaction Summary Report":
					transactionSummaryReport(); break;
				case "Card Collection Report":
					paymentsReport(); break;
					break;
				case "Check/Wire Collection Report":
					wireCollectionReport(); break;
					break;
				case "Stripe Payment Report":
					paymentsReport(); break;
					break;
				// case "Card Collection Report": CardCollectionReport(); break;
				case "Software Fee Report":sofrtwarefeeReport(); break;
				case "Refund payment": refundReport(); break;
				// case "Pending Payment Report": pendingpaymentReport(); break;
				case "Failed Payment Report": failedpaymentReport(); break;
				case "Affiliate Booked Report": affiliatereport(); break;
				case "Affiliate Seats Report": affiliateSeatsReport(); break;
				case "Affiliate Invoice Report": affiliateallreport(); break;
				case "Affiliate Comprehensive Report": affiliatecomprehensivereport(); break;
				case "Cancel Booking Report": cancelbookingreport(); break;
				case "Add-ons Package Report": addonsPackageReport(); break;
				case "Add-ons Package Details Report": addonsPackageDetailsReport(); break;
				case "Email Report": emailreport(); break;
				case "FareHarbor Booking Report" : fareharborbookingReport(); break;
				case "Permit Fee Report": dailyreport(true); break;
				case "Permit Fee Details Report": dailyreportdetails(); break;
				case "Tip Report": tipreport(); break;
				case "Tip Summary Report": tipsummaryreport();break;
				case "Daily Activity Report": dailyactivityReport();break;
				case "Invalid Bookings Report": invalidbookingsReport();break;
				case "Revenue Report": revenueReportInit({endpoint: revenueReportUrl, admin: userSession});break;
				case "Revenue Summary Report": revenueSummationReport.initReport( {admin: userSession} ); break;
				case "Stripe Refund Lookup": stripeRefundReportInit({admin: userSession});break;
				case "Promotion Report": promotionReportInit({endpoint: promotionReportUrl, admin: userSession});break;
				case "OTAs Booking Report": otaReportInit({endpoint: otaReportUrl, admin: userSession});break;
				case "Affiliate Invoice Insights Report":
					InvoiceInsightsReportInit({ endpoint:invoiceInsightsReportUrl,
						compDownLoadURL: "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_comprehensive_report_export')}}",
						bookedDownLoadURL: "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_report_export')}}",
						admin: userSession,
						invoiceUrl: invoiceUpdateUrl
					});
					break;
				case "Staff Booking Report": staffBookingReportInit({endpoint: staffBookingReportUrl, admin: userSession}); break;
				case "Activity Logs Report": activityLogsReport();break;
				case "Save Credit Card Report": saveCreditCardReport();break;	
			}
		}

		const dailyactivityReportGetUrl = (base_url) => {
			const selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			if (!selectedPackage){
				selectedPackage = 0;
			}
			const viewBy = $("#viewby-select").val();
			// const currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
			// const toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
			var dateRange = reportDateRange();
			return base_url.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage + "/" + viewBy;

		}

		const dailyactivityReportFilters = (evt) => {
			$("#daily-act-report-filters-modal").modal('show');
		};

		const dailyActReportFiltersForm = () => {
			const filterInputField = (val) => {
				const checked = (val === 'TripAdvisor' || val === 'Booking System') ? '' : 'checked';
            	return `<input id="daily-filter-${val}" class="daily-filter" data-type="${val.toLowerCase().replace(' ', '')}"  type='checkbox' ${checked} value="1" /> <label class="inline-block text-sm font-normal text-[#020817]" for="daily-filter-${val}">${val}</label> <br />`;
       		 }
			const filters = [ "Card", "Cash", "Invoiced", "Billable", "Check Wire", "Pending Unpaid", "Booking System", "TripAdvisor",];
			return `<div>
				<form class="form-control" id='daily-act-report-filters-form'>
						<div class="form-group">
							${filters.map(f=>filterInputField(f)).join("\n") }
						</div>
					</form>
				</div>`;
			}

		const dailyActReportReloadWithFilters = () => {

			console.log("form load...");
			const url = dailyactivityReportGetUrl(dailyactivityreportUrl);

			let filters = {};
			$(".daily-filter:checkbox:checked").each((_idx, ele) => {
				let el = $(ele);
				let tp = el.data('type');
				if (!_.has(filters, tp)) { filters[tp] = []; }
				filters[tp].push(encodeURIComponent(el.val()));
			});

			let query = '?filters=1'
			for (let key in filters) {
				if (filters[key].length > 0) {
					query += `&${key}=${filters[key].join("|")}`
				}
			}

			showLoading();
			$.get(url + query).done(dailyactivityReportLoad);

		};

		// Daily Activity Report
		function dailyactivityReport() {

			// if($('#tour_package').val() == '') {
			// 	swal('Please select a tour package.', '', 'warning');
			// 	return false;
			// }

			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForaffiliateSeatsReport').css('display','none');
            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');

			if(params() === false) { return; }

			//add event listener for filters
			$(document).off('click', ".daily-act-report-filters").on("click", ".daily-act-report-filters", (evt)=>{
				dailyactivityReportFilters();
			});

			//add event to handle the filter report event
			$(document).off('click', '.daily-act-report-reload').on('click', '.daily-act-report-reload', (evt)=>{
				dailyActReportReloadWithFilters();
			});

			//add filters modal to html
			const filtersModal = bootstrapModalHtml({ id:"daily-act-report-filters-modal",
													  size: "lg",
													  title: 'Report Filters',
													  content:  dailyActReportFiltersForm(),
													  action: '<button type="button" data-bs-dismiss="modal" class="btn btn-sm btn-primary daily-act-report-reload bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-sm font-semibold px-4 py-[7px] mx-2 mb-4 rounded-md cursor-pointer transition-all duration-300 ease-in-out">Filter Results</button>',
													  // close: '',
													  footer: '',
												});

			$("#daily-modal-container").html(`${filtersModal}`);

			showLoading();
			$.get(dailyactivityReportGetUrl(dailyactivityreportUrl)).done(dailyactivityReportLoad);
		}

		// Permit Fee Report Filter Functions
		let permitFeeReportFilterState = {
			paymentStatus: 'all',
			paymentMethod: 'all',
			bookingType: 'all'
		};

		const permitFeeReportFilters = (evt) => {
			$("#permit-fee-report-filters-modal").modal('show');
			// Restore previous filter selections
			restorePermitFeeFilterState();
		};

		const permitFeeReportFiltersForm = () => {
			return `<div>
				<form class="form-control" id='permit-fee-report-filters-form'>
					<!-- Payment Status Filter -->
					<div class="form-group mb-4">
						<label class="block text-sm font-medium text-[#020817] mb-2">Payment Status</label>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-all-payment-status" name="paymentStatus" class="permit-fee-filter w-4 h-4 mr-2" data-type="allpaymentstatus" type="radio" value="all" checked>
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-all-payment-status">All</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-paid" name="paymentStatus" class="permit-fee-filter w-4 h-4 mr-2" data-type="paid" type="radio" value="paid">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-paid">Paid Bookings</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-unpaid" name="paymentStatus" class="permit-fee-filter w-4 h-4 mr-2" data-type="unpaid" type="radio" value="unpaid">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-unpaid">Unpaid Bookings</label>
						</div>
					</div>

					<!-- Payment Method Filter -->
					<div class="form-group mb-4">
						<label class="block text-sm font-medium text-[#020817] mb-2">Payment Method</label>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-all-payment-methods" name="paymentMethod" class="permit-fee-filter w-4 h-4 mr-2" data-type="allpaymentmethods" type="radio" value="all" checked>
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-all-payment-methods">All</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-cash" name="paymentMethod" class="permit-fee-filter w-4 h-4 mr-2" data-type="cash" type="radio" value="cash">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-cash">Cash</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-card" name="paymentMethod" class="permit-fee-filter w-4 h-4 mr-2" data-type="card" type="radio" value="card">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-card">Card</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-invoice" name="paymentMethod" class="permit-fee-filter w-4 h-4 mr-2" data-type="invoice" type="radio" value="invoice">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-invoice">Invoice</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-billable" name="paymentMethod" class="permit-fee-filter w-4 h-4 mr-2" data-type="billable" type="radio" value="billable">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-billable">Billable</label>
						</div>

					</div>

					<!-- Booking Type Filter -->
					<div class="form-group mb-4">
						<label class="block text-sm font-medium text-[#020817] mb-2">Booking Type</label>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-all-booking-types" name="bookingType" class="permit-fee-filter w-4 h-4 mr-2" data-type="allbookingtypes" type="radio" value="all" checked>
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-all-booking-types">All</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-normal" name="bookingType" class="permit-fee-filter w-4 h-4 mr-2" data-type="normal" type="radio" value="normal">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-normal">Normal Bookings</label>
						</div>
						<div class="flex items-center mb-1">
							<input id="permit-fee-filter-affiliate" name="bookingType" class="permit-fee-filter w-4 h-4 mr-2" data-type="affiliate" type="radio" value="affiliate">
							<label class="inline-block text-sm font-normal text-[#020817]" for="permit-fee-filter-affiliate">Affiliate Bookings</label>
						</div>
					</div>
				</form>
			</div>`;
		};

		const restorePermitFeeFilterState = () => {
			// Restore payment status
			$(`input[name="paymentStatus"][value="${permitFeeReportFilterState.paymentStatus}"]`).prop('checked', true);
			
			// Restore payment method
			$(`input[name="paymentMethod"][value="${permitFeeReportFilterState.paymentMethod}"]`).prop('checked', true);
			
			// Restore booking type
			$(`input[name="bookingType"][value="${permitFeeReportFilterState.bookingType}"]`).prop('checked', true);
		};

		const savePermitFeeFilterState = () => {
			permitFeeReportFilterState.paymentStatus = $('input[name="paymentStatus"]:checked').val();
			permitFeeReportFilterState.paymentMethod = $('input[name="paymentMethod"]:checked').val();
			permitFeeReportFilterState.bookingType = $('input[name="bookingType"]:checked').val();
		};

		const resetPermitFeeFilterState = () => {
			permitFeeReportFilterState = {
				paymentStatus: 'all',
				paymentMethod: 'all',
				bookingType: 'all'
			};
		};

		const getPermitFeeReportFilters = () => {
			const paymentStatus = $('input[name="paymentStatus"]:checked').val();
			const paymentMethod = $('input[name="paymentMethod"]:checked').val();
			const bookingType = $('input[name="bookingType"]:checked').val();

			let params = [];
			
			if (paymentStatus && paymentStatus !== 'all') {
				params.push('paymentStatus=' + encodeURIComponent(paymentStatus));
			}
			
			if (paymentMethod && paymentMethod !== 'all') {
				params.push('paymentMethod=' + encodeURIComponent(paymentMethod));
			}
			
			if (bookingType && bookingType !== 'all') {
				params.push('bookingType=' + encodeURIComponent(bookingType));
			}

			return params.length > 0 ? params.join('&') : null;
		};

		const permitFeeReportReloadWithFilters = () => {
			// Save current filter state before reloading
			savePermitFeeFilterState();
			dailyreport(false); // Reload the permit fee report with filters (don't reset filters)
		};


		function dailyactivityReportLoad(data) {
				hideLoading();
				updateReportInfo();
				updateReportView();
				updateActionBtn(downloadReport, (data.length === 0));

				if(data.length == 0) {
					return renderDataNotFound();
				}

				let arrayKeys = [];
				let overallTotalBookingsCount = 0;
				let overallTotalBookingAmount = 0;
				let overallTotalSoftwareFeeAmount = 0;
				for (let main in data) {
					arrayKeys = Object.keys(data);
				}
				let html = "";
				let arrayKeyCount = 0;
				if(data.data1 != undefined) {
					const tableClass = 'table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left daily-activity-report';
					html += `<div class="w-full overflow-y-auto mt-2">
								<table class='${tableClass}' id='dailyReportHeader'>
									<thead>
										<tr>
											<td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' width='10%;' align='center'>
												<button class="btn btn-small daily-act-report-filters inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out" id="dailyActReportFilters"> <i class="fas fa-filter"></i> filters</button>
											</td>

											<td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' align='center' style="border-right: none;">
												${data.data1.tour_operator_name}
											</td>

											<td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' width='10%;' align='center' style="border-left: none;">
											</td>

										</tr>
									</thead>
									<tbody>
									</tbody>
								</table>
							</div>
							<div class="w-full overflow-y-auto mt-2">
								<table class='${tableClass}' id='dailyReportHeader'>
									<thead>
										<tr>
											<td colspan='14' align='center'>${data.data2.date_range}</td>
											<tr>
												<th class="th-custom-css">Payment Type</th>
												<th class="th-custom-css">Package</th>
												<th class="th-custom-css">Total Bookings</th>
												<th class="th-custom-css">Ticket Cost</th>
												<th class="th-custom-css">Ticket Refund</th>
												<th class="th-custom-css">Ticket Net</th>
												<th class="th-custom-css">Platform Fees</th>
												<th class="th-custom-css">Platform Fees Refund</th>
												<th class="th-custom-css">Platform Fees Net</th>
												<th class="th-custom-css">Discount</th>
												<th class="th-custom-css">Payment Total</th>
												<th class="th-custom-css">Refund Total</th>
												<th class="th-custom-css">Total Net</th>
											</tr>
									</thead>
									<tbody>
										<tr>
											<td class='td-custom-css'>All</td>
											<td class='td-custom-css'>All</td>
											<td class='td-custom-css'>${data.data2.total_bookings.toLocaleString('en-US')}</td>
											<td class='td-custom-css'>${currency(data.data2.ticket_cost_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.ticket_cost_refund_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.ticket_cost_net_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.platform_fees_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.platform_fees_refund_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.platform_fees_net_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.discount_total.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.payment_total_amount.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.total_refunds.toFixed(2)).format()}</td>
											<td class='td-custom-css'>${currency(data.data2.ticket_cost_net_total.toFixed(2)).format()}</td>
										</tr>
									</tbody>
								</table>
							</div>
							<div class="w-full overflow-y-auto mt-2">
							<table class='${tableClass}' id='dailyReportHeader'>
							
								<thead>
									<tr>
										<th class="th-custom-css">Payment Type</th>
										<th class="th-custom-css">Package</th>
										<th class="th-custom-css">Total Bookings</th>
										<th class="th-custom-css" colspan='3' style='text-align: center;'>Ticket Cost</th>
										<th class="th-custom-css" colspan='3' style='text-align: center;'>Platform Fees</th>
										<th class="th-custom-css">Discount</th>
										<th class="th-custom-css">Payment Total</th>
										<th class="th-custom-css">Refund Total</th>
										<th class="th-custom-css">Total Net</th>
									</tr>
								</thead>
							<tbody>
								<tr>
									<th class="th-custom-css"></th>
									<th class="th-custom-css"></th>
									<th class="th-custom-css"></th>
									<th class="th-custom-css">Total</th>
									<th class="th-custom-css">Refund</th>
									<th class="th-custom-css">Net</th>
									<th class="th-custom-css">Total</th>
									<th class="th-custom-css">Refund</th>
									<th class="th-custom-css">Net</th>
									<th class="th-custom-css"></th>
									<th class="th-custom-css"></th>
									<th class="th-custom-css"></th>
									<th class="th-custom-css"></th>`;

					for (var main in data.data3) {
						var totalBooking = 0;
						var totalBookingAmount = 0;
						var totalRefundedBookingAmount = 0;
						var totalBookingAmountNet = 0;
						var totalProcessingFeeAmount = 0;
						var totalRefundedProcessingFeeAmount = 0;
						var totalProcessingFeeAmountNet = 0;

						var totalSoftwarefeeAmount = 0;
						var totalRefundedSoftwareFeeAmount = 0;
						var totalSoftwarefeeAmountNet = 0;

						var totalDiscountAmount = 0;
						var netAmount = 0;
						var finalNetAmount = 0;

						var totalNetAmount = 0;
						for (var children in data.data3[main]) {
							var netamount = 0
							if (typeof (data.data3[main][children].BookingCount) == "undefined" && data.data3[main][children].BookingCount == null) {
								data.data3[main][children].BookingCount = 0;
							}
							if (typeof (data.data3[main][children].BookingAmount) == "undefined" && data.data3[main][children].BookingAmount == null) {
								data.data3[main][children].BookingAmount = 0;
							}
							if (typeof (data.data3[main][children].RefundedBookingAmount) == "undefined" && data.data3[main][children].RefundedBookingAmount == null) {
								data.data3[main][children].RefundedBookingAmount = 0;
							}
							if (typeof (data.data3[main][children].BookingAmountNet) == "undefined" && data.data3[main][children].BookingAmountNet == null) {
								data.data3[main][children].BookingAmountNet = 0;
							}
							if (typeof (data.data3[main][children].ProcessingFeeAmount) == "undefined" && data.data3[main][children].ProcessingFeeAmount == null) {
								data.data3[main][children].ProcessingFeeAmount = 0;
							}
							if (typeof (data.data3[main][children].RefundedProcessingFeeAmount) == "undefined" && data.data3[main][children].RefundedProcessingFeeAmount == null) {
								data.data3[main][children].RefundedProcessingFeeAmount = 0;
							}
							if (typeof (data.data3[main][children].ProcessingFeeAmountNet) == "undefined" && data.data3[main][children].ProcessingFeeAmountNet == null) {
								data.data3[main][children].ProcessingFeeAmountNet = 0;
							}
							if (typeof (data.data3[main][children].SoftwareFeeAmount) == "undefined" && data.data3[main][children].SoftwareFeeAmount == null) {
								data.data3[main][children].SoftwareFeeAmount = 0;
							}
							if (typeof (data.data3[main][children].RefundedSoftwareFeeAmount) == "undefined" && data.data3[main][children].RefundedSoftwareFeeAmount == null) {
								data.data3[main][children].RefundedSoftwareFeeAmount = 0;
							}
							if (typeof (data.data3[main][children].SoftwareFeeAmountNet) == "undefined" && data.data3[main][children].SoftwareFeeAmountNet == null) {
								data.data3[main][children].SoftwareFeeAmountNet = 0;
							}
							if (typeof (data.data3[main][children].DiscountAmount) == "undefined" && data.data3[main][children].DiscountAmount == null) {
								data.data3[main][children].DiscountAmount = 0;
							}
							if (typeof (data.data3[main][children].PaymentAmount) == "undefined" && data.data3[main][children].PaymentAmount == null) {
								data.data3[main][children].PaymentAmount = 0;
							}
							if (typeof (data.data3[main][children].FinalNetAmount) == "undefined" && data.data3[main][children].FinalNetAmount == null) {
								data.data3[main][children].FinalNetAmount = 0;
							}

							totalBooking += data.data3[main][children].BookingCount;
							totalBookingAmount += data.data3[main][children].BookingAmount;
							totalRefundedBookingAmount += data.data3[main][children].RefundedBookingAmount;
							totalBookingAmountNet += data.data3[main][children].BookingAmountNet;

							totalProcessingFeeAmount += data.data3[main][children].ProcessingFeeAmount;
							totalRefundedProcessingFeeAmount += data.data3[main][children].RefundedProcessingFeeAmount;
							totalProcessingFeeAmountNet += data.data3[main][children].ProcessingFeeAmountNet;

							totalSoftwarefeeAmount += data.data3[main][children].SoftwareFeeAmount;
							totalRefundedSoftwareFeeAmount += data.data3[main][children].RefundedSoftwareFeeAmount;
							totalSoftwarefeeAmountNet += data.data3[main][children].SoftwareFeeAmountNet;

							totalDiscountAmount += data.data3[main][children].DiscountAmount;
							netAmount += data.data3[main][children].PaymentAmount;
							finalNetAmount += data.data3[main][children].BookingAmountNet;

							if(main=='Wire'){
								main1 = 'Check / Wire';
							}else{
								main1 = main;
							}

							html += "<tr><td class='td-custom-css'>"
								+ main1 + "</td><td class='td-custom-css'>"
								+ children + "</td><td class='td-custom-css'>"
								+ parseFloat(data.data3[main][children].BookingCount).toLocaleString('en-US') + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].BookingAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].RefundedBookingAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].BookingAmountNet).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].SoftwareFeeAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].RefundedSoftwareFeeAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].SoftwareFeeAmountNet).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].DiscountAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].PaymentAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].RefundAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
								+ currency(parseFloat(data.data3[main][children].BookingAmountNet).toFixed(2)).format() + "</td></tr>";
						}
						html += "<tr><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>"
							+ totalBooking.toLocaleString('en-US') + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalBookingAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalRefundedBookingAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalBookingAmountNet).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalSoftwarefeeAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalRefundedSoftwareFeeAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalSoftwarefeeAmountNet).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalDiscountAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(netAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(data.data3[main][children].RefundAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>"
							+ currency(parseFloat(totalBookingAmountNet).toFixed(2)).format() + "</td></tr>";
						html += "<tr><td class='td-custom-css' colspan=13></td></tr>";
					}

					if (data.data4 != undefined && data.data4 != null) {
						html += `<div class="w-full overflow-y-auto mt-2"><table class='${tableClass}' id='dailyReportHeader'>`;
						html += "<thead><tr><th class='th-custom-css'>Payment Type</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Total Bookings</th><th class='th-custom-css'>NAT Gross</th><th class='th-custom-css'>Total Software Fees</th><th class='th-custom-css'>Refunds</th><th class='th-custom-css'>Net</th></tr></thead><tbody>";
						$.each(data.data4, function (key, value) {
							if (typeof (value.UnpaidRefundAmout) == "undefined" && value.UnpaidRefundAmout == null) {
								value.UnpaidRefundAmout = 0;
							}
							if (typeof (value.UnpaidBookingCount) == "undefined" && value.UnpaidBookingCount == null) {
								value.UnpaidBookingCount = 0;
							}
							if (typeof (value.UnpaidBookingAmount) == "undefined" && value.UnpaidBookingAmount == null) {
								value.UnpaidBookingAmount = 0;
							}
							if (typeof (value.UnpaidSoftwareFeeAmount) == "undefined" && value.UnpaidSoftwareFeeAmount == null) {
								value.UnpaidSoftwareFeeAmount = 0;
							}
							html += "<tr><td class='td-custom-css'>Pending (Unpaid)</td><td class='td-custom-css'>" + key + "</td><td class='td-custom-css'>" + parseFloat(value.UnpaidBookingCount).toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(parseFloat(value.UnpaidBookingAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>" + currency(parseFloat(value.UnpaidSoftwareFeeAmount).toFixed(2)).format() + "</td><td class='td-custom-css'>" + currency(parseFloat(value.UnpaidRefundAmout).toFixed(2)).format() + "</td><td class='td-custom-css'>" + currency(parseFloat(value.UnpaidNetAmout).toFixed(2)).format() + "</td></tr>";
						});
						html += "</tbody></table></div>";
					}

					$('#mainTableDiv').append(html);
					
					// Add booking details table as a separate full-width table
					if(data.data5 != undefined && data.data5 != null){
						let bookingDetailsHtml = "";
						bookingDetailsHtml += "<div class='w-full overflow-y-auto mt-4'>";
						bookingDetailsHtml += "<div id='bookingDetailsTableContainer' style='padding: 20px; border-radius: 8px; border: 1px solid #dee2e6;'>";
						bookingDetailsHtml += "<h5 style='margin-bottom: 20px; color: #264F73; font-weight: 600; font-size: 18px;'>Unpaid Booking Details</h5>";
						bookingDetailsHtml += "<table class='table table-bordered table-striped table-hover w-full' id='bookingDetailsTable' style='margin-bottom: 0; width: 100%;'>";
						bookingDetailsHtml += "<thead><tr>";
						bookingDetailsHtml += "<th class='th-custom-css'>Booking ID</th>";
						bookingDetailsHtml += "<th class='th-custom-css'>Customer Name</th>";
						bookingDetailsHtml += "<th class='th-custom-css'>Email</th>";
						bookingDetailsHtml += "<th class='th-custom-css'>Tourists</th>";
						bookingDetailsHtml += "<th class='th-custom-css'>Infants</th>";
						bookingDetailsHtml += "<th class='th-custom-css'>Total Pax</th>";
						bookingDetailsHtml += "</tr></thead><tbody>";

						$.each(data.data5, function (key, booking) {
							bookingDetailsHtml += "<tr>";
							bookingDetailsHtml += "<td class='td-custom-css'><a target='_blank' href='" + bookingInfoUrl + booking.booking_id + "'>" + booking.booking_id + "</a></td>";
							bookingDetailsHtml += "<td class='td-custom-css'>" + (booking.customer_name || '') + "</td>";
							bookingDetailsHtml += "<td class='td-custom-css'>" + (booking.customer_email || '') + "</td>";
							bookingDetailsHtml += "<td class='td-custom-css'>" + (booking.tourists || 0) + "</td>";
							bookingDetailsHtml += "<td class='td-custom-css'>" + (booking.infants || 0) + "</td>";
							bookingDetailsHtml += "<td class='td-custom-css'>" + (booking.total_pax || 0) + "</td>";
							bookingDetailsHtml += "</tr>";
						});

						bookingDetailsHtml += "</tbody></table></div></div>";
						$('#mainTableDiv').append(bookingDetailsHtml);
					}

					// Initialize DataTable for booking details if it exists
					if(data.data5 != undefined && data.data5 != null && data.data5.length > 0){
						setTimeout(function() {
							$('#bookingDetailsTable').DataTable({
								"pageLength": 10,
								"lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
								"searching": true,
								"ordering": true,
								"info": true,
								"responsive": true,
								"language": {
									"search": "Search bookings:",
									"lengthMenu": "Show _MENU_ bookings per page",
									"info": "Showing _START_ to _END_ of _TOTAL_ bookings",
									"infoEmpty": "No bookings available",
									"infoFiltered": "(filtered from _MAX_ total bookings)"
								},
								"columnDefs": [
									{ "orderable": true, "targets": [0, 1, 2, 3, 4, 5] }
								],
								"order": [[0, "asc"]]
							});
						}, 100);
					}
				}else{
					html = "";
					if (data.data4 != undefined && data.data4 != null) {
						html += `<div class="w-full overflow-y-auto mt-2"><table class='${tableClass}' id='dailyReportHeader'>`;
						html += "<thead><tr><th class='th-custom-css'>Payment Type</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Total Bookings</th><th class='th-custom-css'>NAT Gross</th><th class='th-custom-css'>Total Software Fees</th><th class='th-custom-css'>Refunds</th><th class='th-custom-css'>Net</th></tr></thead><tbody>";
						$.each(data.data4, function (key, value) {
							if (typeof (value.UnpaidRefundAmout) == "undefined" && value.UnpaidRefundAmout == null) {
								value.UnpaidRefundAmout = 0;
							}
							if (typeof (value.UnpaidBookingCount) == "undefined" && value.UnpaidBookingCount == null) {
								value.UnpaidBookingCount = 0;
							}
							if (typeof (value.UnpaidBookingAmount) == "undefined" && value.UnpaidBookingAmount == null) {
								value.UnpaidBookingAmount = 0;
							}
							if (typeof (value.UnpaidSoftwareFeeAmount) == "undefined" && value.UnpaidSoftwareFeeAmount == null) {
								value.UnpaidSoftwareFeeAmount = 0;
							}
							html += "<tr><td class='td-custom-css'>Pending (Unpaid)</td><td class='td-custom-css'>" + key + "</td><td class='td-custom-css'>" + parseFloat(value.UnpaidBookingCount).toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(value.UnpaidBookingAmount).format() + "</td><td class='td-custom-css'>" + currency(value.UnpaidSoftwareFeeAmount).format() + "</td><td class='td-custom-css'>" + currency(value.UnpaidRefundAmout).format() + "</td><td class='td-custom-css'>" + currency(value.UnpaidNetAmout).format() + "</td></tr>";
						});
						html += "</tbody></table></div>";
						$('#mainTableDiv').append(html);
					}else {
						$('#mainTableDiv').append(`<div class="w-full overflow-y-auto mt-2"><table class='${tableClass}' id='dailyReportHeader'><thead><tr><th class="th-custom-css">Payment Type</th><th class="th-custom-css">Package</th><th class="th-custom-css">Total Bookings</th><th class="th-custom-css">Total Amount</th><th class="th-custom-css">Total Software Fee</th></tr></thead><tbody><tr class=odd><td class="td-custom-css" valign=top colspan=8 class=dataTables_empty><center>No data available in table</center></td></tr></tbody></table></div>`);
					}
				}
			}

		// Invalid Bookings Report
		function invalidbookingsReport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');

			//NEW URL
			var dateRange = reportDateRange();
			var invalidbookingsreportUrl2 = invalidbookingsreportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage;
			showLoading();
			$.get(invalidbookingsreportUrl2)
				.done(function(data) {
					updateReportView();
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length === 0));
					hideLoading();

					if(data.length != 0) {
						$('#mainTableDiv').empty();
						$('#bookingTableBody').empty();
						var html = "";
						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>";

						html+= "<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Booking ID</th><th class='th-custom-css'>Table Name</th></tr></thead>";
						html += "<tbody>";
						i = 1;
						for (var main in data) {
								html +="<tr><td class='td-custom-css'>"+i+"</td><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + data[main].tour_booking_id + ">"+ data[main].tour_booking_id +"</a></td><td class='td-custom-css'>"+data[main].table_name+"</td>";
							i++;
						}
						html += "</tbody>"
						html += "</table></div>";
						$('#mainTableDiv').append(html);

					}else{
						$('#datatableFortipDetails').empty();
						$('#datatableBookingReport').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').empty();
						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='dailyReportHeader'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Booking ID</th></tr></thead><tbody><tr class=odd><td class='td-custom-css' valign=top colspan=8 class=dataTables_empty><center>No data available in table</center></td></tr></tbody></table></div>");
					}
				});
		}

		// Tip Report Start
		function tipreport(){
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var customerId = $("#customer-data").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
             $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');

			//NEW
			var dateRange = reportDateRange();
			var tipreportUrl2 = tipreportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage ;
			showLoading();

			$.get(tipreportUrl2)
				.done(function(data) {
					updateReportInfo();
					hideLoading();
					$("#datatableFortipDetails").css("display", "inline-table");
					$('#mainTableDiv').empty();
					if(data) {
						if(data.data.length >= 0) {
							var imageName = data?.data[0]?.touroperatorlogo;
							$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' +imageName);
							$('#bookingTableBody').empty();
						}
					}

					updateActionBtn( downloadReport, (data.data.length === 0));

					if(data.length != 0) {
						$(function() {
							var table = $('#datatableFortipDetails').DataTable({
								"footerCallback": function ( row, data, start, end, display ) {
									var api = this.api();

									// Remove the formatting to get integer data for summation
									var intVal = function ( i ) {
										return typeof i === 'string' ?
											i.replace(/[\$,]/g, '')*1 :
											typeof i === 'number' ?
												i : 0;
									};

									// Total over all pages
									total = api
										.column( 8 )
										.data()
										.reduce( function (a, b) {
											return intVal(a) + intVal(b);
										}, 0 );
									//AMOUNttotal
									amountTotal = api
										.column( 7, { page: 'current'} )
										.data()
										.reduce( function (a, b) {
											return intVal(a) + intVal(b);
										}, 0 );
									// Total over this page
									pageTotal = api
										.column( 8, { page: 'current'} )
										.data()
										.reduce( function (a, b) {
											return intVal(a) + intVal(b);
										}, 0 );

									// Update footer
									$( api.column(7).footer() ).html(
										currency(amountTotal).format()
									);
									$( api.column(8).footer() ).html(
										currency(pageTotal).format()
									);
								},
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: false,
								ordering: false,
								paging: false,
								info: false,
								ajax:  {
									method: 'GET',
									url: tipreportUrl2,
								},
								columns: [
									{
										data: 'id',
										name: 'id',
										"render": function(data,type,row,meta) {
											var id = '<a target="_blank" href="' + bookingInfoUrl + data + '">' + data + '</a>';
											return id;
										},
										className: 'td-custom-css'
									},
									{
										data: 'name',
										name: 'name',
										className: 'td-custom-css'
									},
									{
										data: 'phone_number',
										name: 'phone_number',
										className: 'td-custom-css'
									},
									{
										data: 'bookingdate',
										name: 'bookingdate',
										"render": function(data,type,row,meta) {
											var bookingdate = moment(data).format('MM/DD/YYYY');
											return bookingdate;
										},
										className: 'td-custom-css'
									},

									{
										data: 'createdate',
										name: 'createdate',
										"render": function(data,type,row,meta) {
											var createdate = moment(data).format('MM/DD/YYYY');
											return createdate;
										},
										className: 'td-custom-css'
									},
									{
										data:'firstName',
										render: function ( data, type, row ) {
											return row.firstName  + '  ' + row.lastName
										},
										className: 'td-custom-css'
									},
									{
										data: 'tourists',
										name: 'tourists',
										className: 'td-custom-css'
									},
									{
										data: 'amount',
										"fnCreatedCell": function (nTd, data, oData, iRow, iCol) {
											$(nTd).text(currency(data).format());
										},
										className: 'td-custom-css'
									},
									{
										data: 'tipamount',
										"fnCreatedCell": function (nTd, data, oData, iRow, iCol) {
											$(nTd).text(currency(data).format());
										},
										className: 'td-custom-css'
									},
								],
							});
						});
					}else{
						$('#datatableBookingReport').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
					}
				});
		}
		// Tip Report End

		// Tip Summary Report Start
		function tipsummaryreport(){
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
             $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');

			showLoading();
			//NEW
			var dateRange = reportDateRange();
			var tipsummaryreportUrl2 = tipsummaryreportUrl1.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage ;

			$.get(tipsummaryreportUrl2)
				.done(function(data) {
					updateReportInfo();
					hideLoading();
					$('#mainTableDiv').empty();
					$('#datatableFortipDetails').css('display','none');
					$('#datatableForBookingDetails').css('display','none');
					$('#datatableForRefundDetails').css('display','none');
					$('#datatableForFarHarborDetails').css('display','none');

					updateActionBtn(downloadReport, (data.length === 0));

					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].imageName);
									break outerLoop;
								}
							}
					}

					if(data.length != 0) {

						$('#mainTableDiv').empty();
						$('#datatableForBookingDetails').css('display','none');
						$('#datatableForRefundDetails').css('display','none');
						$('#bookingTableBody').empty();

						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						var arraykeyCount = 0;
						for(var main in data)
						{
							var i = 1;
							var html = "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable w-full border-collapse text-left' id='datatableBookingReport'><span>Date - "+arrayKeys[arraykeyCount]+"</span><thead><tr><th class='th-custom-css'>#</th><th class='th-custom-css'>Staff Name</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Tip Amount</th><th class='th-custom-css'>Total Booking</th></tr></thead>";

							var amountTotal = 0;
							for(var children in data[main]) {
								amountTotal += parseFloat(data[main][children].amountTotal.toFixed(2));

								html += "<tbody><tr><td class='td-custom-css'>"+i+"</td> <td class='td-custom-css'>"+data[main][children].staffname+"</td> <td class='td-custom-css'>"+arrayKeys[arraykeyCount]+"</td> <td class='td-custom-css'>"+currency(data[main][children].amountTotal).format()+"</td> <td class='td-custom-css'>"+data[main][children].tourists+"</tr></tbody>";

								i++;
							}

							html += "</table></div>";
							$('#mainTableDiv').append(html);
							arraykeyCount++;
						};
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').empty();
						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>#</th><th class='th-custom-css'>Staff Name</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Tip Amount</th><th class='th-custom-css'>Total Booking</th></tr></thead><tbody><tr class=odd><td class='td-custom-css' valign=top colspan=5 class=dataTables_empty><center>No data available in table</center></td></tr></tbody></table></div>");
					}
				});
		}
		// Tip Summary Report End

		// Daily Report Start
		function dailyreport(resetFilters = false) {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForaffiliateSeatsReport').css('display','none');
            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');

			// Reset filters if this is a report regeneration (not just filtering)
			if (resetFilters) {
				resetPermitFeeFilterState();
			}

            showLoading();
			@if(session('userType') == 'Tour Operator')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif
			var dateRange = reportDateRange();
			var dailyreportUrl2 = dailyreportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage;

			// Add filter parameters if filters are applied
			var filterParams = getPermitFeeReportFilters();
			if (filterParams) {
				dailyreportUrl2 += '?' + filterParams;
			}

			$.get(dailyreportUrl2)
				.done(function(data) {
					hideLoading();
					updateReportView();
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length===0));


					if(data.length != 0) {
						$('#mainTableDiv').empty();
						$('#bookingTableBody').empty();

						var firstCheckinRateCount = 0;
						var firstCheckinPermitFeeCount = 0;
						var firstCheckinRefundPermitFeeCount = 0;
						var secondCheckinRateCount = 0;
						var secondCheckinPermitFeeCount = 0;
						var secondCheckinRefundPermitFeeCount = 0;
						var notCheckinRateCount = 0;
						var notCheckinPermitFeeCount = 0;
						var notCheckinRefundPermitFeeCount = 0;
						var absentRateCount = 0;
						var absentPermitFeeCount = 0;
						var absentRefundPermitFeeCount = 0;
						var nodataCount = 0;

						var html = "";
						// Add filter button
						html += "<button class='btn btn-sm permit-fee-report-filters inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out mb-2' title='Filter Data'><i class='fas fa-funnel-dollar'></i> Filters</button>";
						
						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='dailyReportHeader'>";
						html += "<thead><tr><th class='th-custom-css'>Check in status</th><th class='th-custom-css'>Customer Type</th><th class='th-custom-css'># of Pax</th><th class='th-custom-css'>Permit Exempt</th><th class='th-custom-css'>Permit Pax</th><th class='th-custom-css'>Permit Fee</th></tr></thead><tbody>";

						$.each(data, function(key, rate_group){
							if(key != "totalTourists" && key != "permitFeeTourists" && key != "totalPermitFees"){
								if(Object.keys(rate_group).length > 2){
									html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + key + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td></tr>";
									$.each(rate_group, function(keys, value){
										if(keys != "keyValue" && keys != "keyCheckInStatus"){
											$.each(value, function(ky, vl) {
												if (key == "1st Checked In") {
													firstCheckinRateCount = firstCheckinRateCount + vl.rateCount;
													firstCheckinRefundPermitFeeCount = firstCheckinRefundPermitFeeCount + vl.RefundPermitFeeCount;
													firstCheckinPermitFeeCount = firstCheckinPermitFeeCount + vl.PermitFeeCount;
												} else if (key == "2nd Check In") {
													secondCheckinRateCount = secondCheckinRateCount + vl.rateCount;
													secondCheckinRefundPermitFeeCount = secondCheckinRefundPermitFeeCount + vl.RefundPermitFeeCount;
													secondCheckinPermitFeeCount = secondCheckinPermitFeeCount + vl.PermitFeeCount;
												} else if (key == "Not Checked In") {
													notCheckinRateCount = notCheckinRateCount + vl.rateCount;
													notCheckinRefundPermitFeeCount = notCheckinRefundPermitFeeCount + vl.RefundPermitFeeCount;
													notCheckinPermitFeeCount = notCheckinPermitFeeCount + vl.PermitFeeCount;
												} else if (key == "Absent") {
													absentRateCount = absentRateCount + vl.rateCount;
													absentRefundPermitFeeCount = absentRefundPermitFeeCount + vl.RefundPermitFeeCount;
													absentPermitFeeCount = absentPermitFeeCount + vl.PermitFeeCount;
												}
												
												// Calculate Permit Pax (0 for Absent, calculated for others)
												var permitPax = (key == "Absent") ? 0 : (vl.rateCount - vl.RefundPermitFeeCount);
												
												html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'>" + ky.replace(/_/g, ' ') + "</td><td class='td-custom-css'>" + vl.rateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + vl.RefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + permitPax.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(vl.PermitFeeCount).format() + "</td></tr>";
											});
										}
									});
									if(key == "1st Checked In"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + firstCheckinRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + firstCheckinRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + (firstCheckinRateCount - firstCheckinRefundPermitFeeCount).toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(firstCheckinPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									} else if(key == "2nd Check In"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + secondCheckinRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + secondCheckinRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + (secondCheckinRateCount - secondCheckinRefundPermitFeeCount).toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(secondCheckinPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									} else if(key == "Not Checked In"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + notCheckinRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + notCheckinRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + (notCheckinRateCount - notCheckinRefundPermitFeeCount).toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(notCheckinPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									} else if(key == "Absent"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + absentRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + absentRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>0</td><td class='td-custom-css'>" + currency(absentPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									}
								}else{
									html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + key + "</td><td class='td-custom-css'>Infant (Ages 3 & Under)</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(nodataCount.toFixed(2)).format() + "</td></tr>";
									html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + currency(nodataCount.toFixed(2)).format() + "</td></tr>";
								}
								html+="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css' colspan=6></td></tr>";
							}
						});
						html += "</tbody></table></div>";
						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' ><tfoot><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' colspan='9'><div class='grand-total'><div class='flex items-center justify-between tsub'><div class='tbooking'>"+
							"Total Tourist:</div><div>"+(data.totalTourists.toLocaleString('en-US'))+"</div></div><div class='flex items-center justify-between discount'><div class='tbooking'>"+
							"Permit Fee Tourist:</div><div>"+(data.permitFeeTourists.toLocaleString('en-US'))+"</div></div><div class='flex items-center justify-between discount'><div class='tbooking'>"+
							"Permit Fees:</div><div>"+currency(data.totalPermitFees.toFixed(2)).format()+"</div></div></div></td></tr></tfoot></table></div>";
						$('#mainTableDiv').append(html);
					}else{
						$('#datatableFortipDetails').empty();
						$('#datatableBookingReport').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').empty();

						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='dailyReportHeader'><thead><tr><th class='th-custom-css'>Check in status</th><th class='th-custom-css'>Customer Type</th><th class='th-custom-css'># of Pax</th><th class='th-custom-css'>Permit Exempt</th><th class='th-custom-css'>Permit Pax</th><th class='th-custom-css'>Permit Fee</th></tr></thead><tbody><tr class=odd><td class='td-custom-css' valign=top colspan=6 class=dataTables_empty><center>No data available in table</center></td></tr></tbody></table></div>");
					}

					// Add event listeners for permit fee report filters
					$(document).off('click', ".permit-fee-report-filters").on("click", ".permit-fee-report-filters", (evt)=>{
						permitFeeReportFilters();
					});

					$(document).off('click', '.permit-fee-report-reload').on('click', '.permit-fee-report-reload', (evt)=>{
						permitFeeReportReloadWithFilters();
					});

					// Add filters modal to html
					const permitFeeFiltersModal = bootstrapModalHtml({ 
						id:"permit-fee-report-filters-modal",
						size: "lg",
						title: 'Permit Fee Report Filters',
						content: permitFeeReportFiltersForm(),
						action: '<button type="button" data-bs-dismiss="modal" class="btn btn-sm btn-primary permit-fee-report-reload bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-sm font-semibold px-4 py-[7px] mx-2 mb-4 rounded-md cursor-pointer transition-all duration-300 ease-in-out">Filter Results</button>',
						footer: '',
					});

					$("#daily-modal-container").html(`${permitFeeFiltersModal}`);
				});
		}
		// Daily Report end

		// Daily Report Start
		function dailyreportdetails() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');

            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
 			$('#datatableForaffiliateSeatsReport').css('display','none');
 			$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
             $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
            showLoading();
			@if(session('userType') == 'Tour Operator')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif
			var dateRange = reportDateRange();
			var dailyreportUrl2 = dailyreportdetailsUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage;

			// Add filter parameters if filters are applied
			var filterParams = [];
			
			if (permitFeeReportFilterState.paymentStatus && permitFeeReportFilterState.paymentStatus !== 'all') {
				filterParams.push('paymentStatus=' + encodeURIComponent(permitFeeReportFilterState.paymentStatus));
			}
			
			if (permitFeeReportFilterState.paymentMethod && permitFeeReportFilterState.paymentMethod !== 'all') {
				filterParams.push('paymentMethod=' + encodeURIComponent(permitFeeReportFilterState.paymentMethod));
			}
			
			if (permitFeeReportFilterState.bookingType && permitFeeReportFilterState.bookingType !== 'all') {
				filterParams.push('bookingType=' + encodeURIComponent(permitFeeReportFilterState.bookingType));
			}

			if (filterParams.length > 0) {
				dailyreportUrl2 += '?' + filterParams.join('&');
			}

			$.get(dailyreportUrl2)
				.done(function(data) {
					updateReportInfo();
					hideLoading();
					$('#mainTableDiv').empty();
					$('#datatableFortipDetails').css('display','none');
					$('#datatableForBookingDetails').css('display','none');
					$('#datatableForRefundDetails').css('display','none');
					$('#datatableForFarHarborDetails').css('display','none');

					updateActionBtn(downloadReport, (data.length===0));


					if(data.length != 0) {
						$('#mainTableDiv').empty();
						$('#bookingTableBody').empty();

						var firstCheckinRateCount = 0;
						var firstCheckinPermitFeeCount = 0;
						var firstCheckinRefundPermitFeeCount = 0;
						var secondCheckinRateCount = 0;
						var secondCheckinPermitFeeCount = 0;
						var secondCheckinRefundPermitFeeCount = 0;
						var notCheckinRateCount = 0;
						var notCheckinPermitFeeCount = 0;
						var notCheckinRefundPermitFeeCount = 0;
						var absentRateCount = 0;
						var absentPermitFeeCount = 0;
						var absentRefundPermitFeeCount = 0;
						var nodataCount = 0;

						var html = "";
						// Add filter button
						html += "<button class='btn btn-sm permit-fee-details-report-filters inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out mb-2' title='Filter Data'><i class='fas fa-funnel-dollar'></i> Filters</button>";
						
						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='dailyReportHeader'>";
						html += "<thead><tr><th class='th-custom-css'>Check in status</th><th class='th-custom-css'>Source</th><th class='th-custom-css'>Customer Type</th><th class='th-custom-css'># of Pax</th><th class='th-custom-css'># Permit Exempt</th><th class='th-custom-css'>Permit Pax</th><th class='th-custom-css'>Booking ID</th><th class='th-custom-css'>Exempt Booking ID</th><th class='th-custom-css'>Permit Fee</th></tr></thead><tbody>";

						$.each(data, function(key, rate_group){
							if(key != "totalTourists" && key != "permitFeeTourists" && key != "totalPermitFees"){
								if(Object.keys(rate_group).length > 2){
									html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + key + "</td><td class='td-custom-css'>Online</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td></tr>";
									$.each(rate_group, function(keys, value){
										if(keys != "keyValue" && keys != "keyCheckInStatus"){
											$.each(value, function(ky, vl) {
												if (key == "1st Checked In") {
													firstCheckinRateCount = firstCheckinRateCount + vl.rateCount;
													firstCheckinRefundPermitFeeCount = firstCheckinRefundPermitFeeCount + vl.RefundPermitFeeCount;
													firstCheckinPermitFeeCount = firstCheckinPermitFeeCount + vl.PermitFeeCount;
												} else if (key == "2nd Check In") {
													secondCheckinRateCount = secondCheckinRateCount + vl.rateCount;
													secondCheckinRefundPermitFeeCount = secondCheckinRefundPermitFeeCount + vl.RefundPermitFeeCount;
													secondCheckinPermitFeeCount = secondCheckinPermitFeeCount + vl.PermitFeeCount;
												} else if (key == "Not Checked In") {
													notCheckinRateCount = notCheckinRateCount + vl.rateCount;
													notCheckinRefundPermitFeeCount = notCheckinRefundPermitFeeCount + vl.RefundPermitFeeCount;
													notCheckinPermitFeeCount = notCheckinPermitFeeCount + vl.PermitFeeCount;
												} else if (key == "Absent") {
													absentRateCount = absentRateCount + vl.rateCount;
													absentRefundPermitFeeCount = absentRefundPermitFeeCount + vl.RefundPermitFeeCount;
													absentPermitFeeCount = absentPermitFeeCount + vl.PermitFeeCount;
												}
												// Calculate Permit Pax (0 for Absent, calculated for others)
												var permitPax = (key == "Absent") ? 0 : (vl.rateCount - vl.RefundPermitFeeCount);
												
												html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + ky.replace(/_/g, ' ') + "</td><td class='td-custom-css'>" + vl.rateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + vl.RefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + permitPax.toLocaleString('en-US') + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(vl.PermitFeeCount).format() + "</td></tr>";

												if((vl.detailsBooking != null && vl.detailsBooking.length > 0) || (vl.detailsRefundedBooking != null && vl.detailsRefundedBooking.length > 0)){
													for(var bookings in vl.detailsBooking) {
														html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'><a target='_blank' href='" + bookingInfoUrl + vl.detailsBooking[bookings] + "'>" + vl.detailsBooking[bookings] + "</a><td class='td-custom-css'>";
														if(typeof(vl.detailsRefundedBooking) != "undefined" && vl.detailsRefundedBooking.hasOwnProperty(bookings)){
															html +=	"<a target='_blank' href='" + bookingInfoUrl + vl.detailsRefundedBooking[bookings] + "'>" + vl.detailsRefundedBooking[bookings] + "</a></td><td class='td-custom-css'></td></tr>";
														}else{
															html +=	"</td><td class='td-custom-css'></td></tr>";
														}
													};
												}
											});
										}
									});
									if(key == "1st Checked In"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + firstCheckinRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + firstCheckinRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + (firstCheckinRateCount - firstCheckinRefundPermitFeeCount).toLocaleString('en-US') + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(firstCheckinPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									} else if(key == "2nd Check In"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + secondCheckinRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + secondCheckinRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + (secondCheckinRateCount - secondCheckinRefundPermitFeeCount).toLocaleString('en-US') + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(secondCheckinPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									} else if(key == "Not Checked In"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + notCheckinRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + notCheckinRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + (notCheckinRateCount - notCheckinRefundPermitFeeCount).toLocaleString('en-US') + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(notCheckinPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									} else if(key == "Absent"){
										html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + absentRateCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + absentRefundPermitFeeCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>0</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(absentPermitFeeCount.toFixed(2)).format() + "</td></tr>";
									}
								}else{
									html += "<tr><td class='td-custom-css'>" + key + "</td><td class='td-custom-css'>Online</td><td class='td-custom-css'>Infant (Ages 3 & Under)</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(nodataCount.toFixed(2)).format() + "</td></tr>";
									html += "<tr><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'>" + nodataCount.toLocaleString('en-US') + "</td><td class='td-custom-css'></td><td class='td-custom-css'></td><td class='td-custom-css'>" + currency(nodataCount.toFixed(2)).format() + "</td></tr>";
								}
								html+="<tr><td class='td-custom-css' colspan=9></td></tr>";
							}
						});
						html += "</tbody></table></div>";
						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' ><tfoot><tr><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' colspan='9'><div class='grand-total'><div class='flex items-center justify-between tsub'><div class='tbooking'>"+
							"Total Tourist:</div><div>"+(data.totalTourists.toLocaleString('en-US'))+"</div></div><div class='flex items-center justify-between discount'><div class='tbooking'>"+
							"Permit Fee Tourist:</div><div>"+(data.permitFeeTourists.toLocaleString('en-US'))+"</div></div><div class='flex items-center justify-between discount'><div class='tbooking'>"+
							"Permit Fees:</div><div>"+currency(data.totalPermitFees.toFixed(2)).format()+"</div></div></div></td></tr></tfoot></table></div>";
						$('#mainTableDiv').append(html);
					}else{
						$('#datatableFortipDetails').empty();
						$('#datatableBookingReport').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').empty();

						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='dailyReportHeader'><thead><tr><th class='th-custom-css'>Check in status</th><th class='th-custom-css'>Source</th><th class='th-custom-css'>Customer Type</th><th class='th-custom-css'># of Pax</th><th class='th-custom-css'># Permit Exempt</th><th class='th-custom-css'>Permit Pax</th><th class='th-custom-css'>Booking ID</th><th class='th-custom-css'>Exempt Booking ID</th><th class='th-custom-css'>Permit Fee</th></tr></thead><tbody><tr class=odd><td valign=top colspan=9 class='td-custom-css dataTables_empty'><center>No data available in table</center></td></tr></tbody></table></div>");
					}

					// Add event listeners for permit fee details report filters
					$(document).off('click', ".permit-fee-details-report-filters").on("click", ".permit-fee-details-report-filters", (evt)=>{
						permitFeeReportFilters(); // Reuse the same filter modal
					});

					// Add filters modal to html (reuse the same modal)
					const permitFeeDetailsFiltersModal = bootstrapModalHtml({ 
						id:"permit-fee-report-filters-modal",
						size: "lg",
						title: 'Permit Fee Details Report Filters',
						content: permitFeeReportFiltersForm(),
						action: '<button type="button" data-bs-dismiss="modal" class="btn btn-sm btn-primary permit-fee-details-report-reload bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-sm font-semibold px-4 py-[7px] mx-2 mb-4 rounded-md cursor-pointer transition-all duration-300 ease-in-out">Filter Results</button>',
						footer: '',
					});

					$("#daily-modal-container").html(`${permitFeeDetailsFiltersModal}`);

					// Add reload event listener for details report
					$(document).off('click', '.permit-fee-details-report-reload').on('click', '.permit-fee-details-report-reload', (evt)=>{
						savePermitFeeFilterState();
						dailyreportdetails(); // Reload the permit fee details report with filters
					});
				});
		}
		// Daily Report end

		// Transaction Report start
		function transactionReport(checkCard = true, checkCash = true, checkInvoiced = true, checkCancel = false, checkWire = true, excludeExternal = false, includeExternal = true, onlyShowExternal = false, includeExternalWithFee = false, dropSelect = 'month') {
			var params2 = dateRangeParams();
			var viewBy = $("#viewby-select").val();
			var selectedPackage = $("#tour_package").val();
			params2.viewDate = viewBy;
			params2.checkCard = checkCard;
			params2.checkCash = checkCash;
			params2.checkWire = checkWire;
			params2.checkInvoiced = checkInvoiced;
			params2.checkCancel = checkCancel;
			params2.selectedPackage = selectedPackage;
			params2.excludeExternal = excludeExternal;
			params2.includeExternal = includeExternal;
			params2.onlyShowExternal = onlyShowExternal;
			params2.includeExternalWithFee = includeExternalWithFee;
			params2.dropSelect = dropSelect;
			$("#reportCheck").val('1');

			if(params2 == false)
				return;

			showLoading();
			$.get(transactionReportUrl, params2)
				.done(function(data) {
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length===0));
					hideLoading();
					$("#datatableForTransaction").css("display", "inline-table");
					$('#mainTableDiv').empty();
					$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForTransactionSummary').css('display','none');
                    $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForaffiliateSeatsReport').css('display','none');
    				$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
           			$('#datatableForRefundDetails').css('display','none');
           			$('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
           			$('#datatableForAddonsPackageReport').css('display','none');
           			$('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
           			$('#datatableForAddonsPackageDetailsReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');

					var html = "";
					html +=	"<button class='btn btn-sm trans-display-filters inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' title='Filter Data' onclick='this.style.color='blue';'><i class='fas fa-funnel-dollar'></i></button>";

					html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
					html += "<thead><tr><th class='th-custom-css'>Payment Total</th><th class='th-custom-css'>No. of Bookings</th><th class='th-custom-css'>Refund Total</th><th class='th-custom-css'>Fee Total</th><th class='th-custom-css'>Total Discount</th><th class='th-custom-css'>Total Net</th></tr></thead>";

					html += "<tbody>";
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + currency(data.totalPayment.toFixed(2)).format() + "</td><td class='td-custom-css'>" + data.totalBookings + "</td> <td class='td-custom-css'>" + currency(data.totalRefund.toFixed(2)).format() + "</td> <td class='td-custom-css'>" + currency(data.totalFees.toFixed(2)).format() + "</td> <td class='td-custom-css'>" + currency(data.totalDiscount.toFixed(2)).format()  + "</td> <td class='td-custom-css'>" + currency(data.totalNet.toFixed(2)).format() + "</td> </tr>";
					html += "</tbody></table></div>";

					$('#mainTableDiv').append(html);

					if(data.length != 0) {
						$(function() {
							var collapsedGroups = {};

							var tableTr = $('#datatableForTransaction').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: true,
								responsive: true,
								bDestroy: true,
								searching: true,
								ordering: true,
								paging: true,
								info: false,
								bLengthChange: false,
								pageLength: 200,
								ajax:  {
									method: 'GET',
									url: transactionReportUrl,
									data: params2,
								},
								columns: [
									{
										data: 'bookingId',
										name: 'bookingId',
										"render": function(data,type,row,meta) {
											var id =  '<i class="fa fa-plus-circle trans-report-row-detail" data-id="' + data + '" size="xs"></i> | <a target="_blank" href="' + bookingInfoUrl + data + '">' + data + '</a>';
											return id;
										},
										className: 'td-custom-css'
									},
									{
										data: 'date',
										name: 'date',
										className: 'td-custom-css'
									},
									{
										data: 'booking_status',
										name: 'booking_status',
										className: 'td-custom-css'
									},
									{
										data: 'amount',
										name: 'amount',
										"render": function(data,type,row,meta) {
											var amount = parseFloat(data);
											return currency(amount.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
									{
										data: 'discount',
										name: 'discount',
										className: 'td-custom-css'
									},
									{
										data: 'refund',
										name: 'refund',
										"render": function(data,type,row,meta) {
											var refund = parseFloat(data);
											return currency(refund.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
									{
										data: 'net_total',
										name: 'net_total',
										"render": function(data,type,row,meta) {
											var net_total = parseFloat(data);
											return currency(net_total.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
								],
							});
						});
					} else {
						$('#datatableBookingReport').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
					}
				});
		}


		 $(document).off('click', ".trans-display-filters").on("click", ".trans-display-filters", (evt) => {
            $("#trans-report-filter-modal").modal("toggle");
        });

		$(document).on("click", ".trans-report-reload", (evt) => {
            var checkCard = $('.checkCard').is(":checked");
            var checkCash = $('.checkCash').is(":checked");
            var checkInvoiced = $('.checkInvoiced').is(":checked");
            var checkCancel = $('.checkCancel').is(":checked");
            var checkWire = $('.checkWire').is(":checked");
            var excludeExternal = $('.excludeExternal').is(":checked");
            var includeExternal = $('.includeExternal').is(":checked");
            var onlyShowExternal = $('.onlyShowExternal').is(":checked");
            var includeExternalWithFee = $('.includeExternalWithFee').is(":checked");
            var checkUnpaid = $('.checkUnpaid').is(":checked");
            $("#trans-report-filter-modal").modal("toggle");
            var reportCheck = $("#reportCheck").val();
            var dropSelect = $('#trans-total-by-select').val();

             if(reportCheck == 1){
             	transactionReport(checkCard, checkCash, checkInvoiced, checkCancel, checkWire, excludeExternal, includeExternal, onlyShowExternal, includeExternalWithFee, dropSelect);
             } 

             if(reportCheck == 2){
             	transactionSummaryReport(checkCard, checkCash, checkInvoiced, checkCancel, checkWire, excludeExternal, includeExternal, onlyShowExternal, includeExternalWithFee, checkUnpaid);
             }
        });

    
	    $(document).on("change", "#trans-total-by-select", (evt) => {

	    	var checkCard = $('.checkCard').is(":checked");
            var checkCash = $('.checkCash').is(":checked");
            var checkInvoiced = $('.checkInvoiced').is(":checked");
            var checkCancel = $('.checkCancel').is(":checked");
            var checkWire = $('.checkWire').is(":checked");
            var excludeExternal = $('.excludeExternal').is(":checked");
            var includeExternal = $('.includeExternal').is(":checked");
            var onlyShowExternal = $('.onlyShowExternal').is(":checked");
            var includeExternalWithFee = $('.includeExternalWithFee').is(":checked");
	    	var dropSelect = $('#trans-total-by-select').val();
	    	transactionReport(checkCard, checkCash, checkInvoiced, checkCancel, checkWire, excludeExternal, includeExternal, onlyShowExternal, dropSelect);
	    });

        $(document).on("click", "#generate-report-btn", (evt) => {
        	$(".checkCard").prop('checked', true);
        	$(".checkCash").prop('checked', true);
        	$(".checkInvoiced").prop('checked', true);
        	$(".checkCancel").prop('checked', false);
        	$(".checkWire").prop('checked', true);
        	$(".excludeExternal").prop('checked', false);
        	$(".includeExternal").prop('checked', true);
        	$(".onlyShowExternal").prop('checked', false);
        	$(".includeExternalWithFee").prop('checked', false);
        });

        $(document).off('click', ".trans-report-row-detail").on("click", ".trans-report-row-detail", (evt) => {
        	var id = $(evt.target).data('id');
        	var transactionReportBookingDetailsUrl2 = transactionReportBookingDetailsUrl.replace(0, id);
        	$.get(transactionReportBookingDetailsUrl2).done(function(data) {
        		var data = data;
        		var booking = data.booking[0];
        		var refund = 0;
        		var paymentTotal = 0;
        		var bookingTotal = 0;
        		var tableHistory = "";
        		var transAsyncView = "";
    				$.each(data.history, function (key, val) {
    					if(val.operation == "Refund"){
    						var details = $.parseJSON(val.details);
    						refund += parseFloat(details.amount);
    					}
    					if(val.operation == "Payment"){
    						var detail = $.parseJSON(val.details);
    						bookingTotal += parseFloat(detail.amount);
    					}
			       });
    				refund = refund.toFixed(2);
    				bookingTotal = bookingTotal.toFixed(2);
    		 		paymentTotal = bookingTotal - refund;
    		 		transAsyncView = transAsyncViews(data, data.booking, data.id, refund, paymentTotal, bookingTotal);
    		 		tableHistory = historyAsyncView(data , data.id);
    		 		$('#booking-transact-deatils-trans').html(transAsyncView);
    		 		$("#transR-booking-history-deatils-body").html(tableHistory);
        			$("#trans-report-booking-history-modal").modal("toggle");
	        });
        });

		// Transaction report end


		// Transaction Summary Report Start

		function transactionSummaryReport(checkCard = true, checkCash = true, checkInvoiced = true, checkCancel = false, checkWire = true, excludeExternal = false, includeExternal = true, onlyShowExternal = false, includeExternalWithFee = false, checkUnpaid = false) {
			var params2 = dateRangeParams();
			var viewBy = $("#viewby-select").val();
			var selectedPackage = $("#tour_package").val();
			params2.viewDate = viewBy;
			params2.checkCard = checkCard;
			params2.checkCash = checkCash;
			params2.checkInvoiced = checkInvoiced;
			params2.checkCancel = checkCancel;
			params2.selectedPackage = selectedPackage;
			params2.checkWire = checkWire;
			params2.excludeExternal = excludeExternal;
			params2.includeExternal = includeExternal;
			params2.onlyShowExternal = onlyShowExternal;
			params2.includeExternalWithFee = includeExternalWithFee;
			params2.checkUnpaid = checkUnpaid;
			$("#reportCheck").val('2');

			if(params2 == false)
				return;

			showLoading();
			$.get(transactionSummaryReportUrl, params2)
				.done(function(data) {
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length===0));
					hideLoading();
					
					// Update Filter By display
					var filterText = getFilterByText();
					if (filterText !== "No Filter") {
						$("#printFilterBy").text("Filter By: " + filterText).show();
					} else {
						$("#printFilterBy").hide();
					}

					$("#datatableForTransactionSummary").css("display", "inline-table");
					$('#mainTableDiv').empty();
					$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForTransaction').css('display','none');
    				$('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForaffiliateSeatsReport').css('display','none');
    				$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForRefundDetails').css('display','none');
            		$('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageReport').css('display','none');
            		$('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageDetailsReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');

					var html = "";
					html +=	"<button class='btn btn-sm trans-display-filters inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out' title='Filter Data' onclick='this.style.color='blue';'><i class='fas fa-funnel-dollar'></i></button>";

					html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
					html += "<thead><tr><th class='th-custom-css'>Total Tourist</th><th class='th-custom-css'>Payment Total</th><th class='th-custom-css'>Refund Total</th><th class='th-custom-css'>Fee Total</th><th class='th-custom-css'>Total Discount</th><th class='th-custom-css'>Total Net</th></tr></thead>";

					html += "<tbody>";
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + data.totalTourist + "</td><td class='td-custom-css'>" + currency(data.totalPayment.toFixed(2)).format() + "</td> <td class='td-custom-css'>" + currency(data.totalRefund.toFixed(2)).format() + "</td> <td class='td-custom-css'>" + currency(data.totalFees.toFixed(2)).format()  + "</td><td class='td-custom-css'>" + currency(data.totalDiscount.toFixed(2)).format()  + "</td> <td class='td-custom-css'>" + currency(data.totalNet.toFixed(2)).format() + "</td> </tr>";
					html += "</tbody></table></div>";

					$('#mainTableDiv').append(html);

					if(data.length != 0) {
						$(function() {
							var table = $('#datatableForTransactionSummary').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: true,
								responsive: true,
								bDestroy: true,
								searching: true,
								ordering: true,
								paging: true,
								info: false,
								bLengthChange: false,
								pageLength: 200,
								ajax:  {
									method: 'GET',
									url: transactionSummaryReportUrl,
									data: params2,
								},
								columns: [
									{
										data: 'date',
										name: 'date',
										className: 'td-custom-css'
									},
									{
										data: 'payment_date',
										name: 'payment_date',
										className: 'td-custom-css'
									},
									{
										data: 'bookingId',
										name: 'bookingId',
										"render": function(data,type,row,meta) {
											var id =  '<i class="fa fa-plus-circle trans-report-row-detail" data-id="' + data + '" size="xs"></i> | <a target="_blank" href="' + bookingInfoUrl + data + '">' + data + '</a>';
											return id;
										},
										className: 'td-custom-css'
									},
									{
										data: 'booking_status',
										name: 'booking_status',
										className: 'td-custom-css'
									},
									{
										data: 'customer_name',
										name: 'customer_name',
										className: 'td-custom-css'
									},
									{
										data: 'tourists',
										name: 'tourists',
										className: 'td-custom-css'
									},
									{
										data: 'purchase_type',
										name: 'purchase_type',
										className: 'td-custom-css'
									},
									{
										data: 'amount',
										name: 'amount',
										"render": function(data,type,row,meta) {
											var refund = parseFloat(data);
											return currency(refund.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
									
									{
										data: 'discount',
										name: 'discount',
										"render": function(data,type,row,meta) {
											var refund = parseFloat(data);
											return currency(refund.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
									{
										data: 'fee_amount',
										name: 'fee_amount',
										"render": function(data,type,row,meta) {
											var refund = parseFloat(data);
											return currency(refund.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
									{
										data: 'refund',
										name: 'refund',
										"render": function(data,type,row,meta) {
											var refund = parseFloat(data);
											return currency(refund.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},
									{
										data: 'net_total',
										name: 'net_total',
										"render": function(data,type,row,meta) {
											var net_total = parseFloat(data);
											return currency(net_total.toFixed(2)).format();
										},
										className: 'td-custom-css'
									},

								],
							});
						});
					}else{
						$('#datatableBookingReport').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
					}
				});
		}

		// Transaction Summary Report End

		// Affiliate Seats Report Start

        function affiliateSeatsReport(){
            var params2 = dateRangeParams();
            var selectedOperator = $("#tour_operator").val();
            var selectedPackage = $("#tour_package").val();
            var customerId = $("#customer-data").val();
            params2.selectedOperator = selectedOperator
            params2.selectedPackage = selectedPackage
            params2.customerId = customerId
            $("#syncRun").attr("hidden", false).css("display", "inline-block");

            @if(session('userType') == 'Tour Operator' || session('userType') == 'Affiliate')
                params2.selectedOperator = "{{auth()->user()->tour_operator_id}}";
            @endif

            showLoading();
            $.get(affiliateSeatsReportUrl, params2)
                .done(function(data) {
                    updateReportInfo();
                    updateActionBtn(downloadReport, (data.length===0));
                    hideLoading();
                    $("#datatableForaffiliateSeatsReport").css("display", "inline-table");

                    $('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false,  info: false});
					$('#datatableForTransaction').css('display','none');
		            
		            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		            $('#datatableForTransactionSummary').css('display','none');
		            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForRefundDetails').css('display','none');
            		$('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageReport').css('display','none');
            		$('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageDetailsReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');
		            $('#mainTableDiv').empty();

                    var html = "";

                    html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
                    html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Total Assigned Seats</th><th class='th-custom-css'>Total Booked Seats</th><th class='th-custom-css'>Total Cancelled Seats</th><th class='th-custom-css'>Total Fulfilled Seats</th><th class='th-custom-css'>Total No-Show</th></thead>";

                    html += "<tbody>";

                    html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + data.dateRange + "</td><td class='td-custom-css'>" + data.totalAssignedSeat + "</td> <td class='td-custom-css'> <span style='color: green;'>" + data.totalBookedSeat + "</span> </td> <td class='td-custom-css'> <span style='color: red;'>" + data.totalCancelledSeat + " </span> </td> <td class='td-custom-css'>" + data.totalFulfilledSeats  + "</td> <td class='td-custom-css'>" + data.totalNoShow  + "</td> </tr>";
                    html += "</tbody></table></div>";

                    $('#mainTableDiv').append(html);

                    if(data.length != 0) {
                        $(function() {
                            var tableTr = $('#datatableForaffiliateSeatsReport').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
                                processing: true,
                                serverSide: false,
                                responsive: true,
                                bDestroy: true,
                                searching: true,
                                ordering: false,
                                paging: true,
                                info: false,
                                bLengthChange: false,
                                pageLength: 200,
                                ajax:  {
                                    method: 'GET',
                                    url: affiliateSeatsReportUrl,
                                    data: params2,
                                },
                                columns: [
                                    {
                                        data: 'name',
                                        name: 'name',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'package_name',
                                        name: 'package_name',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'date',
                                        name: 'date',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'assigned_seats',
                                        name: 'assigned_seats',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'booked_seats',
                                        name: 'booked_seats',
                                        "render": function(data,type,row,meta) {
                                            var booked_seats = '<span style="color: green;">' + data + '</span>';;
                                            return booked_seats;
                                        },
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'cancelled_seats',
                                        name: 'cancelled_seats',
                                        "render": function(data,type,row,meta) {
                                            var cancelled_seats = '<span style="color: red;">' + data + '</span>';;
                                            return cancelled_seats;
                                        },
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'fulfilled_seats',
                                        name: 'fulfilled_seats',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'no_show',
                                        name: 'no_show',
										className: 'td-custom-css'
                                    },
                                ],
                                 rowGroup: {
                                    dataSrc: 'date',
									startRender: function (rows, group) {
										return $('<tr/>')
										.append('<td colspan="100%" class="td-custom-css">' + group + '</td>');
									}
                                },
                            });

                        });
                    } else {
                        $('#datatableBookingReport').empty();
                        $('#datatableForBookingDetails').empty();
                        $('#datatableForRefundDetails').empty();
                        $('#datatableFortipDetails').empty();
                        $('#datatableForFarHarborDetails').empty();
                    }
                });
        }

        $(document).off('click', ".affiliate-seats-report").on("click", ".affiliate-seats-report", (evt) => {
            
            var affiliate_id = $(evt.target).data('affiliate_id');
            var package_id = $(evt.target).data('package_id');
            var date = $(evt.target).data('date');

            var params2 = dateRangeParams();
            params2.date = date
            params2.affiliate_id = affiliate_id
            params2.package_id = package_id

            $.get(affiliateSeatsReportDetailsUrl, params2).done(function(data) {
                var data = data;
                if(data.length != 0) {
                        $(function() {
                            var tableTr = $('#affiliate-seat-report-time-details').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
                                processing: true,
                                serverSide: true,
                                responsive: true,
                                bDestroy: true,
                                searching: false,
                                ordering: false,
                                paging: false,
                                info: false,
                                bLengthChange: false,
                                pageLength: 200,
                                ajax:  {
                                    method: 'GET',
                                    url: affiliateSeatsReportDetailsUrl,
                                    data: params2,
                                },
                                columns: [
                                    {
                                        data: 'time_from',
                                        name: 'time_from',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'assigned_seats',
                                        name: 'assigned_seats',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'booked_seats',
                                        name: 'booked_seats',
                                        "render": function(data,type,row,meta) {
                                            var booked_seats = '<span style="color: green;">' + data + '</span>';;
                                            return booked_seats;
                                        },
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'cancelled_seats',
                                        name: 'cancelled_seats',
                                        "render": function(data,type,row,meta) {
                                            var cancelled_seats = '<span style="color: red;">' + data + '</span>';;
                                            return cancelled_seats;
                                        },
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'fulfilled_seats',
                                        name: 'fulfilled_seats',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'no_show',
                                        name: 'no_show',
										className: 'td-custom-css'
                                    },
                                ]
                            });

                        });
                    } else {
                        $('#datatableBookingReport').empty();
                        $('#datatableForBookingDetails').empty();
                        $('#datatableForRefundDetails').empty();
                        $('#datatableFortipDetails').empty();
                        $('#datatableForFarHarborDetails').empty();
                    }
                    $("#affiliate-seats-report-modal").modal("toggle");

            });

         });

        $(document).off('click', "#syncRun").on("click", "#syncRun", (evt) => {
        	 $.get(affiliateSeatsSyncUrl).done(function(data) {
        	 	$('#final_msg').fadeIn().html('The sync process has been done. Now you can check the latest updated report');
        	 	setTimeout(function() {
				 $('#final_msg').fadeOut('slow');
					}, 10000 );
				});
        });

        // Affiliate Seats Report End

        // Add-ons Package Report Start

        // Shared helpers for the add-ons summary/details reports: priced badge,
        // expandable child (nested add-on) rows, and the toggle click binding.
        function addonsPricedBadge(isPriced) {
            if (isPriced === null || isPriced === undefined) {
                return '<span class="badge" style="background:#E5E7EB;color:#374151;">Legacy</span>';
            }
            if (isPriced) {
                return '<span class="badge" style="background:#DCFCE7;color:#166534;">Priced</span>';
            }
            return '<span class="badge" style="background:#F1F5F9;color:#475569;">Free</span>';
        }

        function addonsMoney(value) {
            return currency(parseFloat(value || 0).toFixed(2)).format();
        }

        // The "Price" column shows a range label instead of a currency value
        // when an add-on's options are priced differently from each other
        // (e.g. "Spanish $5 / Navajo $10") - there is no single correct number
        // to format as currency in that case, so price_label is shown verbatim.
        function addonsPriceColumn(data, row) {
            if (row && row.price_label) {
                return row.price_label;
            }
            return addonsMoney(data);
        }

        function addonsChildRowsHtml(children, isDetails) {
            if (!children || children.length === 0) {
                return '';
            }
            var html = '<table class="table table-bordered table-striped w-full border-collapse text-left" style="margin:0;">';
            children.forEach(function (child) {
                var countOrIds = isDetails ? (child.tour_booking_id || []).join(', ') : child.booking_count;
                html += '<tr class="hover:bg-[#EFF6FF]">';
                html += '<td class="td-custom-css" style="padding-left:28px;">↳ ' + child.addons_name + '</td>';
                html += '<td class="td-custom-css">' + child.tour_package_name + '</td>';
                html += '<td class="td-custom-css">' + countOrIds + '</td>';
                html += '<td class="td-custom-css">' + addonsPricedBadge(child.is_priced) + '</td>';
                html += '<td class="td-custom-css">' + addonsPriceColumn(child.price, child) + '</td>';
                html += '<td class="td-custom-css">' + addonsMoney(child.paid_total) + '</td>';
                html += '<td class="td-custom-css">' + addonsMoney(child.refunded_total) + '</td>';
                html += '<td class="td-custom-css">' + addonsMoney(child.net_total) + '</td>';
                html += '</tr>';
            });
            html += '</table>';
            return html;
        }

        // Binds the parent/child expand-collapse toggle for one add-ons DataTable
        // instance. `isDetails` picks whether the 3rd column renders a booking-id
        // list (details report) or a booking count (summary report).
        function bindAddonsExpandToggle(tableSelector, dataTableInstance, isDetails) {
            $(document).off('click', tableSelector + ' td.td-addon-name').on('click', tableSelector + ' td.td-addon-name', function () {
                var tr = $(this).closest('tr');
                var row = dataTableInstance.row(tr);
                var rowData = row.data();
                if (!rowData || !rowData.children || rowData.children.length === 0) {
                    return;
                }
                if (row.child.isShown()) {
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    row.child(addonsChildRowsHtml(rowData.children, isDetails)).show();
                    tr.addClass('shown');
                }
            });
        }

        function addonsNameColumn(data, type, row) {
            var hasChildren = row.children && row.children.length > 0;
            var caret = hasChildren ? '<i class="fa fa-caret-right" style="margin-right:6px;"></i>' : '';
            return caret + data;
        }

        function addonsBookingIdsColumn(data) {
            return (data || []).join(', ');
        }

        function addonsPackageReport(){
        	var params2 = dateRangeParams();
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var customerId = $("#customer-data").val();
			var addOnName = $("#add-on-data").val();
			params2.selectedOperator = selectedOperator
			params2.selectedPackage = selectedPackage
			params2.customerId = customerId
			params2.addOnName = addOnName
			@if(session('userType') == 'Tour Operator' || session('userType') == 'Affiliate')
				params2.selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif

			showLoading();
			$.get(addonsPackageReportReportUrl, params2)
				.done(function(data) {
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length===0));
					hideLoading();
					$("#datatableForAddonsPackageReport").css("display", "inline-table");
					$('#mainTableDiv').empty();
					 $("#datatableForaffiliateSeatsReport").css("display", "inline-table");

                    $('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false,  info: false});
					$('#datatableForTransaction').css('display','none');
		            
		            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		            $('#datatableForTransactionSummary').css('display','none');
		            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForRefundDetails').css('display','none');
            		$('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForaffiliateSeatsReport').css('display','none');
            		$('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageDetailsReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');

					var html = "";

					html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
					html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Net Total </th></tr></thead>";

					html += "<tbody>";

					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + data.dateRange + "</td><td class='td-custom-css'>" + currency(data.totalPrice.toFixed(2)).format() +  "</td> </tr>";
					html += "</tbody></table></div>";

					$('#mainTableDiv').append(html);

					if(data.length != 0) {
						$(function() {
							var tableTr = $('#datatableForAddonsPackageReport').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: true,
								ordering: false,
								paging: true,
								info: false,
								bLengthChange: false,
								pageLength: 200,
								ajax:  {
									method: 'GET',
									url: addonsPackageReportReportUrl,
									data: params2,
								},
								columns: [
									{
										data: 'addons_name',
										name: 'addons_name',
										className: 'td-custom-css td-addon-name',
										render: addonsNameColumn,
									},
									{
										data: 'tour_package_name',
										name: 'tour_package_name',
										className: 'td-custom-css'
									},
									{
										data: 'booking_count',
										name: 'booking_count',
										className: 'td-custom-css'
									},
									{
										data: 'is_priced',
										name: 'is_priced',
										"render": function(data,type,row,meta) {
											return addonsPricedBadge(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'price',
										name: 'price',
										"render": function(data,type,row,meta) {
											return addonsPriceColumn(data, row);
										},
										className: 'td-custom-css'
									},
									{
										data: 'paid_total',
										name: 'paid_total',
										"render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'refunded_total',
										name: 'refunded_total',
										"render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'net_total',
										name: 'net_total',
										"render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
									},

								],
							});
							bindAddonsExpandToggle('#datatableForAddonsPackageReport', tableTr, false);

						});
					} else {
						$('#datatableBookingReport').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
					}
				});
        }

         $(document).off('click', ".add-ons-value-report").on("click", ".add-ons-value-report", (evt) => {
            var add_ons_type = $(evt.target).data('add_ons_type');
            var field_id = $(evt.target).data('field_id');
            var is_legacy = $(evt.target).data('is_legacy');
            var tour_package_id = $(evt.target).data('tour_package_id');
            var params2 = dateRangeParams();
                params2.selectedPackage = tour_package_id;
                if (is_legacy || !field_id) {
                    params2.addOnName = add_ons_type;
                } else {
                    params2.fieldId = field_id;
                }
             $.get(addonsPackageValueReportUrl, params2).done(function(data) {
                var data = data;
                	if(data.length != 0) {
                        $(function() {
                            var tableTr = $('#addons-value-report').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								},
                                processing: true,
                                serverSide: true,
                                responsive: true,
                                bDestroy: true,
                                searching: false,
                                ordering: false,
                                paging: false,
                                info: false,
                                bLengthChange: false,
                                pageLength: 200,
                                ajax:  {
                                    method: 'GET',
                                    url: addonsPackageValueReportUrl,
                                    data: params2,
                                },
                                columns: [
                                    {
                                        data: 'tour_booking_id',
                                        name: 'tour_booking_id',
                                        "render": function(data,type,row,meta) {
											var id =  '<a target="_blank" href="' + bookingInfoUrl + data + '">' + data + '</a>';
											return id;
										},
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'value',
                                        name: 'value',
										className: 'td-custom-css'
                                    },
                                    {
                                        data: 'price',
                                        name: 'price',
                                        "render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
                                    },
                                ]
                            });

                        });
                    } else {
                        $('#datatableBookingReport').empty();
                        $('#datatableForBookingDetails').empty();
                        $('#datatableForRefundDetails').empty();
                        $('#datatableFortipDetails').empty();
                        $('#datatableForFarHarborDetails').empty();
                    }
                    $("#addons-value-report-modal").modal("toggle");

            });

         });
         // Add-ons Package Report End

         // Add-on Package Details Report start
         function addonsPackageDetailsReport() {
          	var params2 = dateRangeParams();
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var customerId = $("#customer-data").val();
			var addOnName = $("#add-on-data").val();
			params2.selectedOperator = selectedOperator
			params2.selectedPackage = selectedPackage
			params2.customerId = customerId
			params2.addOnName = addOnName
			@if(session('userType') == 'Tour Operator' || session('userType') == 'Affiliate')
				params2.selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif

			showLoading();
			$.get(addonsPackageDetailsReportUrl, params2)
				.done(function(data) {
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length===0));
					hideLoading();
					$("#datatableForAddonsPackageDetailsReport").css("display", "inline-table");
					$('#mainTableDiv').empty();
					 $("#datatableForaffiliateSeatsReport").css("display", "inline-table");

                    $('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false,  info: false});
					$('#datatableForTransaction').css('display','none');
		            
		            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		            $('#datatableForTransactionSummary').css('display','none');
		            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForRefundDetails').css('display','none');
            		$('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForaffiliateSeatsReport').css('display','none');
            		$('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');

					var html = "";

					html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
					html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Net Total </th></tr></thead>";

					html += "<tbody>";

					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + data.dateRange + "</td><td class='td-custom-css'>" + currency(data.totalPrice.toFixed(2)).format() +  "</td> </tr>";
					html += "</tbody></table></div>";
					$('#mainTableDiv').append(html);

					if(data.length != 0) {
						$(function() {
							var tableTr = $('#datatableForAddonsPackageDetailsReport').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: true,
								ordering: false,
								paging: true,
								info: false,
								bLengthChange: false,
								pageLength: 200,
								ajax:  {
									method: 'GET',
									url: addonsPackageDetailsReportUrl,
									data: params2,
								},
								columns: [
									{
										data: 'addons_name',
										name: 'addons_name',
										className: 'td-custom-css td-addon-name',
										render: addonsNameColumn,
									},
									{
										data: 'tour_package_name',
										name: 'tour_package_name',
										className: 'td-custom-css'
									},
									{
										data: 'tour_booking_id',
										name: 'tour_booking_id',
										"render": function(data,type,row,meta) {
											return addonsBookingIdsColumn(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'is_priced',
										name: 'is_priced',
										"render": function(data,type,row,meta) {
											return addonsPricedBadge(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'price',
										name: 'price',
										"render": function(data,type,row,meta) {
											return addonsPriceColumn(data, row);
										},
										className: 'td-custom-css'
									},
									{
										data: 'paid_total',
										name: 'paid_total',
										"render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'refunded_total',
										name: 'refunded_total',
										"render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
									},
									{
										data: 'net_total',
										name: 'net_total',
										"render": function(data,type,row,meta) {
											return addonsMoney(data);
										},
										className: 'td-custom-css'
									},

								],
							});
							bindAddonsExpandToggle('#datatableForAddonsPackageDetailsReport', tableTr, true);

						});
					} else {
						$('#datatableBookingReport').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
					}
				});

          }
		function reportDateRange() {
			let d = $('#reportrange').data('daterangepicker');
			return {
		        currentDate: d.startDate.format('YYYY-MM-DD'),
		        toDate: d.endDate.format('YYYY-MM-DD'),
		        checkYear: d.endDate.format('YYYY')
		    };
		}

		function reportDateTimeRange() {
			let d = $('#reportdatetimerange').data('daterangepicker');
			return {
				currentDate: d.startDate.format('YYYY-MM-DD HH:mm:ss'),  
				toDate: d.endDate.format('YYYY-MM-DD HH:mm:ss'),  
				checkYear: d.endDate.format('YYYY')
			};
        }


		// Affiliate Report Start
		function affiliatereport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var customerId = $("#customer-data").val();
			@if(session('userType') == 'Tour Operator' || session('userType') == 'Affiliate')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif

			var dateRange = reportDateRange();
			var affiliatereportUrl2 = affiliatereportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate).replace('_CUSTOMER_ID_', customerId) + "/" + selectedOperator + "/" + selectedPackage ;
            showLoading();
			$.get(affiliatereportUrl2)
				.done(function(data) {
					updateReportInfo();
					hideLoading();
					$('#mainTableDiv').empty();
					$('#datatableForBookingDetails').css('display','none');
					$('#datatableFortipDetails').css('display','none');
					$('#datatableForRefundDetails').css('display','none');
					$('#datatableForFarHarborDetails').css('display','none');
					$("#openInvoiceModal").css('display','none');
					$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForTransaction').css('display','none');
    				$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForTransactionSummary').css('display','none');
                     $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForaffiliateSeatsReport').css('display','none');
    				$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForRefundDetails').css('display','none');
            		$('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageReport').css('display','none');
            		$('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForAddonsPackageDetailsReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');
					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].touroperatorlogo);
									break outerLoop;
								}
							}
					}

					updateActionBtn(downloadReport, (data.length===0));

					if(data.length != 0) {

						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						var html = "";

						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
						html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Created</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Unpaid Amount</th></tr></thead>";

						html += "<tbody>";
						html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.booking_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.paid_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.refund_amount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" + currency(data.unpaid_amount.toFixed(2)).format() + "</td> </tr>";
						html += "</tbody></table></div>";

						var arraykeyCount = 0;
						for (var main in data) {
							i = 1;
							AmountTotal = 0;
							UnpaidAmountTotal = 0;
							PaymentAmountTotal = 0;
							RefundAmountTotal = 0;
							FinalTouristTotal = 0;
							html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>";

							if(arrayKeys[arraykeyCount] != "unpaid_amount" && arrayKeys[arraykeyCount] != "paid_amount" && arrayKeys[arraykeyCount] != "booking_amount" && arrayKeys[arraykeyCount] != "refund_amount" && arrayKeys[arraykeyCount] != 'total_tourists'){
								html += "<span>Date - "+arrayKeys[arraykeyCount]+"</span>";
								html+= "<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css min-w-32'>Time</th><th class='th-custom-css'>Total Tourist</th><th class='th-custom-css'>Booking Status</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Unpaid Amount</th></tr></thead>";
							}
							for(var children in data[main]) {
								html += "<tbody>";
								html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + data[main][children].bookingid + ">"+ data[main][children].bookingid +"</a></td><td class='td-custom-css'>"+data[main][children].booking_details.name+"</td>";
								html += "<td class='td-custom-css'>";

								if (data[main][children].rate) {
									var unique = data[main][children].rate.filter(function(itm, i, a) {
									    return i == a.indexOf(itm);
									});
									$.each(unique, function(key, value){
										html += "" + value + "<br/>";
									});
								}

								if(data[main][children].billable_invoiced_status == "Billable"){
									var class_name = "warning-text-color fw-normal";
									var clas = "warning-text-color fw-normal";
								}else if(data[main][children].billable_invoiced_status == "Invoiced"){
									var class_name = "alert-text-color fw-normal";
									var clas = "alert-text-color fw-normal";
								}else if(data[main][children].billable_invoiced_status == "Paid"){
									data[main][children].billable_invoiced_status = "Invoiced";
									var class_name = "success-text-color fw-normal";
									var clas = "normal-text-color fw-normal";
								}

								if(data[main][children].billable_invoiced_status == null){
									if((data[main][children].payment_type)){
										data[main][children].billable_invoiced_status = data[main][children].payment_type;
										var class_name = "success-text-color fw-normal";
										var clas = "normal-text-color fw-normal";
									}else{
										data[main][children].billable_invoiced_status = '';
									}

								}

								html += "</td>";
								html +="<td class='td-custom-css'>" + data[main][children].booking_details.package + "</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.bookingdate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.created_at).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>"+data[main][children].booking_details.time_from + ' - ' + data[main][children].booking_details.time_to  +"</td><td class='td-custom-css'>"+data[main][children].TouristTotal+"</td><td class='td-custom-css success-text-color fw-normal'>" + data[main][children].booking_status +"</br>" + "<span class=" + class_name + ">"+ data[main][children].billable_invoiced_status +"</span>"+ "</td>";

								if(data[main][children].booking_details.status == "Cancelled"){
									var className = "info-text-color fw-normal";
								}
								if(data[main][children].unpaid_amount > 0){
									var class_name = "alert-text-color fw-normal";
									var clas = "alert-text-color fw-normal";
								}else{
									var class_name = "success-text-color fw-normal";
									var clas = "normal-text-color fw-normal";
								}
								if((data[main][children].refundamount > 0) || (data[main][children].payment_amount > 0)){
									var className = "success-text-color fw-normal";
								}else{
									var className = "normal-text-color fw-normal";
								}
								// bookingamount is computed server-side (ReportRepository::affiliateReportsCalculation)
								// and already accounts for custom charges AND add-ons (paid or still-owed) —
								// recomputing it here from raw fields duplicated that logic and silently dropped
								// add-on amounts from this column and the per-date footer total below.
								var completed_amount = parseFloat(data[main][children].bookingamount) || 0;
								html += "<td class='td-custom-css " + class_name + "'>"+currency(completed_amount).format()+"</td><td class='td-custom-css " + className + "'>"+currency(data[main][children].payment_amount.toFixed(2)).format()+"</td><td class='td-custom-css " + className + "'>"+currency(data[main][children].refundamount.toFixed(2)).format()+"</td><td class='td-custom-css " + clas + "'>"+currency(parseFloat(data[main][children].unpaid_amount).toFixed(2)).format()+"</td></tr>";
								html += "</tbody>"

								AmountTotal += parseFloat(completed_amount);
								PaymentAmountTotal += parseFloat(data[main][children].payment_amount);
								RefundAmountTotal += parseFloat(data[main][children].refundamount);
								UnpaidAmountTotal += parseFloat(data[main][children].unpaid_amount);
								FinalTouristTotal += parseFloat(data[main][children].TouristTotal);
							}

							if(arrayKeys[arraykeyCount] != "unpaid_amount" && arrayKeys[arraykeyCount] != "paid_amount" && arrayKeys[arraykeyCount] != "booking_amount" && arrayKeys[arraykeyCount] != "refund_amount" && arrayKeys[arraykeyCount] != 'total_tourists'){
								html +="<tfoot><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' colspan='7'></td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+FinalTouristTotal.toLocaleString('en-US')+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'></td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(AmountTotal.toFixed(2)).format()+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(PaymentAmountTotal.toFixed(2)).format()+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(RefundAmountTotal.toFixed(2)).format()+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(UnpaidAmountTotal.toFixed(2)).format()+"</td></tr></tfoot>";
							}
							arraykeyCount++;
							i++;
						}
						html += "</table></div>";
						$('#mainTableDiv').append(html);
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('datatableForRefundDetails').empty();
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Due Amount</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table><div>");
					};
				}
			);
		}
		// Affiliate Report End

		// Affiliate all Report (Invoice) Start
		function affiliateallreport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var customerId = $("#customer-data").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
            showLoading();
			@if(session('userType') == 'Tour Operator')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif

			var dateRange = reportDateRange();
			var affiliateallreportUrl2 = affiliateallreportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate).replace('_CUSTOMER_ID_', customerId) + "/" + selectedOperator + "/" + selectedPackage ;

			$.get(affiliateallreportUrl2)
				.done(function(data) {
					hideLoading();
					updateReportView();
					updateReportInfo(null,null,'Tour Date');
					updateActionBtn(downloadReport, (data.length===0));

					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].touroperatorlogo);
									break outerLoop;
								}
							}
					}


					if(data.length != 0) {
						@if(session('userType') == 'Tour Operator')
							invoiceTourOperator = "{{auth()->user()->tour_operator_id}}";
						@elseif($uTypeAdmin)
							invoiceTourOperator = selectedOperator;
						@endif
						invoicePackage = selectedPackage;
						invoiceAffiliate = customerId;
						invoiceFromDate = dateRange.currentDate;
						invoiceToDate = dateRange.toDate;
						invoiceAmount = data.unpaid_amount.toFixed(2);
						// Disable invoice button if "All Affiliates" is selected (customerId == 0)
						// Invoices must be created for a specific affiliate
						updateActionBtn(openInvoiceModal, (customerId == 0), "#openInvoiceModal");

						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						var html = "";
							html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left affiliate-invoice-report' id='affiliateReportHeader'>";
							html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Created</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Tourists Total</th><th class='th-custom-css'>Infants Total</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Unpaid Amount</th></tr></thead>";
							html += "<tbody>";
							html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM/DD/YYYY') + " - " + moment(dateRange.toDate).format('MM/DD/YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.booking_amount.toFixed(2)).format() + "</td><td class='td-custom-css'>"+data.tourists_total.toLocaleString('en-US')+"</td><td class='td-custom-css'>"+data.infants_total.toLocaleString('en-US')+"</td><td class='td-custom-css success-text-color fw-normal'>" + currency(data.paid_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.refund_amount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" + currency(data.unpaid_amount.toFixed(2)).format() + "</td> </tr>";
							html += "</tbody></table></div>";

						var arraykeyCount = 0;
						for (var main in data) {
							i = 1;
							AmountTotal = 0;
							FinalTouristTotal = 0;
						    if(arrayKeys[arraykeyCount] == 'invoice_data') {
								html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left affiliate-invoice-report' id='affiliateinvoices'>";

								if (arrayKeys[arraykeyCount] != "unpaid_amount" && arrayKeys[arraykeyCount] != "paid_amount" && arrayKeys[arraykeyCount] != "booking_amount" && arrayKeys[arraykeyCount] != "refund_amount" && arrayKeys[arraykeyCount] != "due_amount") {
									html += "<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css'>Total Tourist</th><th class='th-custom-css'>Total Infants</th><th class='th-custom-css'>Booking Status</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Unpaid Amount</th></tr></thead>";
								}
								for (var children in data[main]) {
									html += "<tbody>"

									html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href=" + bookingInfoUrl + data[main][children].bookingid + ">" + data[main][children].bookingid + "</a></td><td class='td-custom-css'>" + data[main][children].booking_details.name + "</td>";
									html += "<td class='td-custom-css'>";

									if (data[main][children].rate.length) {
										var unique = data[main][children].rate.filter(function (itm, i, a) {
											return i == a.indexOf(itm);
										});
										$.each(unique, function (key, value) {
											html += "" + value + "<br/>";
										});
									}
									if(data[main][children].billable_invoiced_status == "Billable"){
	                                    var class_name = "warning-text-color fw-normal";
	                                    var clas = "warning-text-color fw-normal";
	                                }else if(data[main][children].billable_invoiced_status == "Invoiced"){
	                                    var class_name = "alert-text-color fw-normal";
	                                    var clas = "alert-text-color fw-normal";
	                                }else if(data[main][children].billable_invoiced_status == "Paid"){
	                                    data[main][children].billable_invoiced_status = "Invoiced";
	                                    var class_name = "success-text-color fw-normal";
	                                    var clas = "normal-text-color fw-normal";
	                                }

									html += "</td>";
									html += "<td class='td-custom-css'>" + data[main][children].booking_details.package + "</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.bookingdate).format('MM/DD/YYYY') + ' @ ' + data[main][children].booking_details.time_from + "</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.created_at).format('MM/DD/YYYY') + "</td><td class='td-custom-css'>" + data[main][children].TouristTotal + "</td><td class='td-custom-css'>" + data[main][children].InfantsTotal + "</td><td class='td-custom-css success-text-color fw-normal'>" + data[main][children].booking_status +"</br>" + "<span class=" + class_name + ">"+ data[main][children].billable_invoiced_status +"</span>" + "</td>";

									if (data[main][children].booking_details.payment_status != null) {
										var className = "success-text-color fw-normal";
									}
									if (data[main][children].booking_details.payment_status == null) {
										var className = "alert-text-color fw-normal";
									}
									if (data[main][children].booking_details.status == "Cancelled") {
										var className = "info-text-color fw-normal";
									}
									if (data[main][children].unpaid_amount != 0) {
										var clas = "alert-text-color fw-normal";
										var className = "alert-text-color fw-normal";
									} else {
										var clas = "normal-text-color fw-normal";
									}
									html += "<td class='td-custom-css " + className + "'>" + currency(data[main][children].bookingamount).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency(data[main][children].payment_amount).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency(data[main][children].refundamount).format() + "</td><td class='td-custom-css " + clas + "'>" + currency(data[main][children].unpaid_amount).format() + "</td></tr>";
									html += "</tbody>"

									AmountTotal += parseFloat(data[main][children].booking_details.amount);
									FinalTouristTotal += parseFloat(data[main][children].TouristTotal);
								}
								html += "</table></div>";
							}
							if(arrayKeys[arraykeyCount] == 'due_amount') {
								html += "<h2 class='text-lg font-semibold text-[#111827] my-2'>Affiliate Due Amount</h2>";
								html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left affiliate-invoice-report' >";
								html += "<thead><tr><th class='th-custom-css'>Affiliate</th><th class='th-custom-css'>Amount</th></tr></thead>";
								html += "<tbody>";
								for (var children in data[main]) {
									html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>"+data[main][children].name+"</td><td class='td-custom-css'>" + currency(data[main][children].amount).format() + "</td>";
								}
								html += "</tbody>";
								html += "</table></div>";
							}
							arraykeyCount++;
							i++;
						}

						// Show tabs and put billable bookings in first tab
						$('#mainTableDiv').empty();
						$('#affiliateInvoiceTabs').removeClass('hidden');
						$('#affiliateTab-billable').html(html);
						resetAffiliateInvoiceTabs();
						switchAffiliateTab('billable');
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						updateActionBtn(openInvoiceModal, true, "#openInvoiceModal");
						$('#affiliateInvoiceTabs').addClass('hidden');
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Due Amount</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table></div>");
					};
				}
			);
		}
		// Affiliate All Report (Invoice) End

		// Affiliate Comprehensive Report (Invoice) Start
		function affiliatecomprehensivereport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var customerId = $("#customer-data").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
			$('#datatableForTransaction').css('display','none');
			$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			@if(session('userType') == 'Tour Operator' || session('userType') == 'Affiliate')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif
			showLoading();

			var dateRange = reportDateRange();
			var affiliateallreportUrl2 = affiliatecomprehensivereportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate).replace('_CUSTOMER_ID_', customerId) + "/" + selectedOperator + "/" + selectedPackage ;
			$.get(affiliateallreportUrl2)
				.done(function(data) {
                    hideLoading();
					updateReportView();
					updateReportInfo();

					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].touroperatorlogo);
									break outerLoop;
								}
							}
					}

					updateActionBtn(downloadReport, (typeof(data.comprehensive_data) == 'undefined'));

					if(data.length != 0) {
						invoiceTourOperator = selectedOperator;
						invoiceAffiliate = customerId;
						invoiceFromDate = dateRange.currentDate;
						invoiceToDate = dateRange.toDate;
						invoiceAmount = data.paid_amount.toFixed(2);
						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}
						var html = "";
						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left affiliate-invoice-report' id='affiliateReportHeader'>";
						html += "<thead><tr><th class='th-custom-css'>"+
							"Date Range</th><th class='th-custom-css'>"+
							"Created</th><th class='th-custom-css'>"+
							"Booking Amount</th><th class='th-custom-css'>"+
							"Cancellation Amount</th><th class='th-custom-css'>"+
							"Net Booked</th><th class='th-custom-css'>"+
							"Refund Amount</th><th class='th-custom-css'>"+
							"Net Card</th><th class='th-custom-css'>"+
							"Net Cash</th><th class='th-custom-css'>"+
							"Net Invoiced</th><th class='th-custom-css'>"+
							"Paid Invoices</th><th class='th-custom-css'>"+
							"Unpaid Invoices</th><th class='th-custom-css'>"+
							"Total Net Collected</th><th class='th-custom-css'>"+
							"Unpaid Amount</th></th></thead>";
						html += "<tbody>";
						html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM/DD/YYYY') + " - " + moment(dateRange.toDate).format('MM/DD/YYYY') + "</td><td class='td-custom-css'>" +
							moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.booking_amount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" +
							currency(data.cancellation_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.paid_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.refund_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.card_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.cash_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.invoiced_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.paid_invoiced_amount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" +
							currency(data.unpaid_invoiced_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" +
							currency(data.total_net_amount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" +
							currency(data.unpaid_amount.toFixed(2)).format() + "</td> </tr>";
						html += "</tbody></table></div>";
						var arraykeyCount = 0;
						for (var main in data) {
							i = 1;
							AmountTotal = 0;
							FinalTouristTotal = 0;
							html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left affiliate-invoice-report' id='datatableBookingReport'>";

							if(arrayKeys[arraykeyCount] != "unpaid_amount" && arrayKeys[arraykeyCount] != "paid_amount" && arrayKeys[arraykeyCount] != "booking_amount" && arrayKeys[arraykeyCount] != "refund_amount" && arrayKeys[arraykeyCount] != "cancellation_amount" && arrayKeys[arraykeyCount] != "card_amount" && arrayKeys[arraykeyCount] != "cash_amount" && arrayKeys[arraykeyCount] != "invoiced_amount" && arrayKeys[arraykeyCount] != "paid_invoiced_amount" && arrayKeys[arraykeyCount] != "billable_invoiced_amount" && arrayKeys[arraykeyCount] != "unpaid_invoiced_amount" && arrayKeys[arraykeyCount] != "total_net_amount" && arrayKeys[arraykeyCount] != "total_tourists"){

								html+= "<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Tour Date</th><th class='th-custom-css'>Tour Time</th><th class='th-custom-css'>Payment Date Time</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css'>Total Tourist</th><th class='th-custom-css'>Booking Status</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Unpaid Amount</th></tr></thead>";
							}
							for(var children in data[main]) {
								html += "<tbody>";

								html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + data[main][children].bookingid + ">"+ data[main][children].bookingid +"</a></td><td class='td-custom-css'>"+data[main][children].booking_details.name+"</td>";
								html += "<td class='td-custom-css'>";

								if(data[main][children].rate != undefined && data[main][children].rate.length){
									var unique = data[main][children].rate.filter(function(itm, i, a) {
									    return i == a.indexOf(itm);
									});
									$.each(unique, function(key, value){
										html += "" + value + "<br/>";
									});
								}

								html += "</td>";


								if(data[main][children].billable_invoiced_status == "Billable"){
                                    var class_name = "warning-text-color fw-normal";
                                    var clas = "warning-text-color fw-normal";
                                }else if(data[main][children].billable_invoiced_status == "Invoiced"){
                                    var class_name = "alert-text-color fw-normal";
                                    var clas = "alert-text-color fw-normal";
                                }else if(data[main][children].billable_invoiced_status == "Paid"){
                                    data[main][children].billable_invoiced_status = "Invoiced";
                                    var class_name = "success-text-color fw-normal";
                                    var clas = "normal-text-color fw-normal";
                                }

                                if(data[main][children].billable_invoiced_status == null){
                                    if((data[main][children].payment_type)){
                                        data[main][children].billable_invoiced_status = data[main][children].payment_type;
                                        var class_name = "success-text-color fw-normal";
                                        var clas = "normal-text-color fw-normal";
                                    }else{
                                        data[main][children].billable_invoiced_status = '';
                                    }

                                }

								html +="<td class='td-custom-css'>"+data[main][children].booking_details.package+"</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.bookingdate).format('MM/DD/YYYY') + "</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.time_from, "HH:mm:ss").format("hh:mm A") + "</td><td class='td-custom-css'>" + (data[main][children].payment_datetime || '') +"</td><td class='td-custom-css'>" + moment(data[main][children].booking_details.created_at).format('MM/DD/YYYY') +"</td><td class='td-custom-css'>"+data[main][children].TouristTotal+"</td><td class='td-custom-css success-text-color fw-normal'>" + data[main][children].booking_status + "</br>" + "<span class=" + class_name + ">"+ data[main][children].billable_invoiced_status + "</span>" + "</td>";

								if(data[main][children].booking_details.status == "Cancelled"){
									var className = "alert-text-color fw-normal";
								}
								if((data[main][children].payment_amount > 0) || (data[main][children].refundamount > 0)){
									var class_name = "success-text-color fw-normal";
								}else{
									var class_name = "normal-text-color fw-normal";
								}
								if(data[main][children].unpaid_amount > 0){
									var class_name = "alert-text-color fw-normal";
								}else{
									var class_name = "success-text-color fw-normal";
								}
								// Same fix as affiliatereport() above — use the server-computed bookingamount
								// instead of re-deriving it here, which silently dropped add-on amounts.
								var bt = parseFloat(data[main][children].bookingamount) || 0;
 								html += "<td class='td-custom-css " + class_name + "'>"+currency(bt).format()+"</td>";
								html += "</td><td class='td-custom-css " + class_name + "'>"+currency(data[main][children].payment_amount).format()+"</td><td class='td-custom-css " + class_name + "'>"+currency(data[main][children].refundamount).format()+"</td><td class='td-custom-css " + class_name + "'>"+currency(data[main][children].unpaid_amount).format()+"</td></tr>";
								html += "</tbody>"
								AmountTotal += parseFloat(bt);
								FinalTouristTotal += parseFloat(data[main][children].TouristTotal);
							}
							arraykeyCount++;
							i++;
						}
						html += "</table></div>";
						$('#mainTableDiv').append(html);
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$("#openInvoiceModal").css('display', 'none');
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Due Amount</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table></div>");
					};
				}
			);
		}
		// Affiliate Comprehensive Report (Invoice) End

		// Email Report Start
		function emailreport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();

			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForaffiliateSeatsReport').css('display','none');
            $('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			var dateRange = reportDateRange();
			var emailreportUrl2 = emailreportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage ;
			showLoading();
			$.get(emailreportUrl2)
				.done(function(data) {
					updateReportInfo();
					updateReportView();
					hideLoading();
					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].touroperatorlogo);
									break outerLoop;
								}
							}
					}
					updateActionBtn(downloadReport, (data.length===0));
					if(data.length != 0) {
						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						let dueAmountTotal = 0;
						// let AmountTotal = 0;
						for (var main1 in data) {
							for(var children1 in data[main1]) {
								dueAmountTotal += parseFloat(data[main1][children1].amount);
							}
						}

						var html = "";



						var arraykeyCount = 0;
						for (var main in data) {
							i = 1;
							AmountTotal = 0;
							FinalTouristTotal = 0;
							html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>";
							html += "<span>Date - "+arrayKeys[arraykeyCount]+"</span>";
							html+= "<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css min-w-32'>Time</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Email</th></tr></thead>";
							html += "<tbody>"
							for(var children in data[main]) {
								html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + data[main][children].bookingid + ">"+ data[main][children].bookingid +"</a></td><td class='td-custom-css' style='min-width: 80px;max-width: 80px;'>"+data[main][children].time_from + ' - ' + data[main][children].time_to  +"</td><td class='td-custom-css'>"+data[main][children].tourists+"</td><td class='td-custom-css' style='min-width: 50px;'>"+data[main][children].email+"</td>";
							}
							html += "</tbody>"
							arraykeyCount++;
							i++;
						}
						html += "</table></div>";
						$('#mainTableDiv').append(html);
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css min-w-32'>Time</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Email</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table></div>");
					};
				});
		}
		// Email Report End

		// Cancellation Booking Report Start
		function cancelbookingreport() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
			var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
			$('#datatableForTransaction').css('display','none');
			$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
             $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			@if(session('userType') == 'Tour Operator')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif
			showLoading();

			var dateRange = reportDateRange();
			var cancelbookingreportUrl2 = cancelbookingreportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage ;
			$.get(cancelbookingreportUrl2)
				.done(function(data) {
					updateReportInfo();
					updateReportView();
					hideLoading();
					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].touroperatorlogo);
									break outerLoop;
								}
							}
					}
					updateActionBtn(downloadReport, (data.length===0));

					if(data.length != 0) {

						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						let dueAmountTotal = 0;
						for (var main1 in data) {
							for(var children1 in data[main1]) {
								dueAmountTotal = parseFloat(data.paid_amount + data.unpaid_amount);
							}
						}
						var html = "";

						html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
						html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Created</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Unpaid Amount</th><th class='th-custom-css'>Total</th></tr></thead>";
						html += "<tbody>";
						html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.booking_amount.toFixed(2)).format() + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.paid_amount.toFixed(2)).format() + "</td> <td class='td-custom-css alert-text-color fw-normal'>" + currency(data.unpaid_amount.toFixed(2)).format() + "</td> <td class='td-custom-css'>" + currency(dueAmountTotal.toFixed(2)).format() + "</td> </tr>";
						html += "</tbody></table></div>";
						var arraykeyCount = 0;
						for (var main in data) {
							i = 1;
							AmountTotal = 0;
							PaymentAmountTotal = 0;
							UnpaidAmountTotal = 0;
							FinalTouristTotal = 0;
							html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>";
							if(arrayKeys[arraykeyCount] != "unpaid_amount" && arrayKeys[arraykeyCount] != "paid_amount" && arrayKeys[arraykeyCount] != "booking_amount"){
								html += "<span>Date - "+arrayKeys[arraykeyCount]+"</span>";
								html+= "<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Created Date</th><th class='th-custom-css'>Total Tourist</th><th class='th-custom-css'>Booking Status</th><th class='th-custom-css'>Booking Amount</th><th class='th-custom-css'>Paid Amount</th><th class='th-custom-css'>Unpaid Amount</th></tr></thead>";
							}
							for(var children in data[main]) {
								html += "<tbody>"
								html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + data[main][children].bookingid + ">"+ data[main][children].bookingid +"</a></td><td class='td-custom-css'>"+data[main][children].name+"</td>";
								html += "<td class='td-custom-css'>";
								$.each(data[main][children].ageGroups, function(keys, value){
									html += ""+value+"<br/>";

								});

								html += "</td>";
								html +="<td class='td-custom-css'>"+data[main][children].package+"</td><td class='td-custom-css'>" + moment(data[main][children].bookingdate).format('MM-DD-YYYY') +"</td><td class='td-custom-css'>" + moment(data[main][children].created_at).format('MM-DD-YYYY') +"</td><td class='td-custom-css'>"+data[main][children].TouristTotal+"</td><td class='td-custom-css success-text-color fw-normal'>" + data[main][children].booking_status + "</td>";
								if(data[main][children].payment_status != null){
									var className = "success-text-color fw-normal";
								}
								if(data[main][children].payment_status == null || data[main][children].payment_status=='Failed'){
									var className = "alert-text-color fw-normal";
								}
								if(data[main][children].unpaid_amount != 0){
									var clas = "alert-text-color fw-normal";
									var className = "alert-text-color fw-normal";
								}else{
									var clas = "success-text-color fw-normal";
								}
								var bt = 0;
								if(data[main][children].booking_total !="0.00"){
									bt = data[main][children].booking_total;
								}else{
									bt = data[main][children].amount;
								}
								html += "<td class='td-custom-css " + className + "'>"+currency(bt).format()+"</td><td class='td-custom-css " + className + "'>"+currency(data[main][children].payment_amount).format()+"</td><td class='td-custom-css " + clas + "'>"+currency(data[main][children].unpaid_amount).format()+"</td></tr>";
								html += "</tbody>"

								AmountTotal += parseFloat(bt);
								PaymentAmountTotal += parseFloat(data[main][children].payment_amount);
								UnpaidAmountTotal += parseFloat(data[main][children].unpaid_amount);
								FinalTouristTotal += parseFloat(data[main][children].TouristTotal);
							}
							if(arrayKeys[arraykeyCount] != "unpaid_amount" && arrayKeys[arraykeyCount] != "paid_amount" && arrayKeys[arraykeyCount] != "booking_amount"){
								html +="<tfoot><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' colspan='6'></td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+FinalTouristTotal.toLocaleString('en-US')+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'></td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(AmountTotal.toFixed(2)).format()+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(PaymentAmountTotal.toFixed(2)).format()+"</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>"+currency(UnpaidAmountTotal.toFixed(2)).format()+"</td></tr></tfoot>";
							}
							arraykeyCount++;
							i++;
						}
						html += "</table></div>";
						$('#mainTableDiv').append(html);
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Package</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Due Amount</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table></div>");
					};
				});
		}
		// Cancellation Booking Report End

		// refund Report
		function refundReport() {
			showLoading();
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForTransaction').css('display','none');
			$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForaffiliateSeatsReport').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');

			@if(session('userType') == 'Tour Operator')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif

			var dateRange = reportDateRange();
			var refundReportUrl2 = refundReportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage ;
			$.get(refundReportUrl2)
				.done(function(data) {
					hideLoading();
					$('#datatableForFarHarborDetails').empty();
					updateReportInfo();
					$("#datatableForRefundDetails").css("display", "inline-table");
					$('#mainTableDiv').empty();
					updateActionBtn(downloadReport, (data.totalRefund<=0));

					var html = "";

					html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
					html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Created</th><th class='th-custom-css'>Refund Amount</th></tr></thead>";

					html += "<tbody>";
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + "</td><td class='td-custom-css'>" + moment(new Date()).format('MM-DD-YYYY') + "</td> <td class='td-custom-css success-text-color fw-normal'>" + currency(data.totalRefund.toFixed(2)).format() + "</td> </tr>";
					html += "</tbody></table></div>";

					$('#mainTableDiv').append(html);

					if(data.length != 0) {
						$(function() {
							var table = $('#datatableForRefundDetails').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: false,
								ordering: false,
								paging: false,
								info: false,
								ajax:  {
									method: 'GET',
									url: refundReportUrl2,
								},
								columns: [
									{
										data: 'bookingId',
										name: 'bookingId',
										render: function (data, type, row, meta) {
											const pathname = '{{ request()->segment(1) }}';
											const BaseUrl ='{{ url('/') }}';
											var URL = '';
											if(pathname == "admin"){
												URL = BaseUrl+ '/admin/tour-operator/booking/';
											}else{
												URL = BaseUrl+ '/tour-operator/booking/';
											}
											var set = '<a target="_blank" href='+URL + data + '>' + data + '</a>';
											return set;
										},
										className: 'td-custom-css'
									},
									{
										data: 'name',
										name: 'name',
										className: 'td-custom-css'
									},
									{
										data: 'bookingdate',
										name: 'bookingdate',
										render: function (data, type, row, meta) {
											return row.bookingdate + ' @ ' + row.time_from;
										},
										className: 'td-custom-css'
									},
									{
										data: 'phone',
										name:'phone',
										className: 'td-custom-css'
									},
									{
										data: 'email',
										name:'email',
										className: 'td-custom-css'
									},
									{
										data: 'amount',
										render: function (data, type, row, meta) {
											return currency(data).format();
										},
										className: 'td-custom-css'
									},
									{
										data: 'refund',
										name: 'refund',
										render: function (data, type, row, meta) {
											let refundarr = '';
											if (data.length > 0) {
												$.each(data, function(key, value) {
													refundarr += "[" + currency(value.amount).format();
													if (value.payment_type != '') {
														refundarr += ' , ' + value.payment_type;
													}
													if (value.refund_type != '') {
														refundarr += ' , ' + value.refund_type;
													}
													refundarr += '] <br>';
												});
											}
											return refundarr;
										},
										className: 'td-custom-css'
									},
									{
										data: 'booking_status',
										name: 'booking_status',
										className: 'td-custom-css'
									},
									{
										data:'refunddate',
										name:'refunddate',
										"render": function(data,type,row,meta) {
											return data;
										},
										className: 'td-custom-css'
									},
								],
							});
						});
					}else{
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
					}

				});
		}
		//fareharbor booking Report
		function fareharborbookingReport() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			@if(session('userType') == 'Tour Operator')
				selectedOperator = "{{auth()->user()->tour_operator_id}}";
			@endif
			var dateRange = reportDateRange();
			var fareharborbookingReportUrl2 = fareharborbookingReportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage ;
			showLoading();
			$.get(fareharborbookingReportUrl2)
				.done(function(data) {
					hideLoading();
					updateReportInfo();
					$("#datatableForFarHarborDetails").css("display", "inline-table");
					$('#mainTableDiv').empty();
					$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForTransaction').css('display','none');
					$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForTransactionSummary').css('display','none');
            		$('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		    		$('#datatableForaffiliateSeatsReport').css('display','none');
		    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		            $('#datatableForRefundDetails').css('display','none');
		            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		            $('#datatableForAddonsPackageReport').css('display','none');
		            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
		            $('#datatableForAddonsPackageDetailsReport').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');
					if(data) {
						if(data.data.length >= 0) {
							var imageName = data?.data[0]?.logo;
							$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' +imageName);
							$('#bookingTableBody').empty();
						}
					}

					updateActionBtn(downloadReport, (data.data.length === 0));

					var totalCardAmount = 0;
					var totalCashAmount = 0;
					var totalInvoicedAmount = 0;
					var total = 0;
					var totalCancelledCardAmount = 0;
					var totalCancelledCashAmount = 0;
					var totalCancelledInvoicedAmount = 0;
					var totalCancelled = 0;
					for (var main1 in data) {
						for(var children1 in data[main1]) {
							if(data[main1][children1].via=='Card'){
								if(data[main1][children1].booking_status=='Booked') {
									totalCardAmount += parseFloat(data[main1][children1].amount);
								}
								if(data[main1][children1].booking_status=='Cancelled') {
									totalCancelledCardAmount += parseFloat(data[main1][children1].amount);
								}
							}else if(data[main1][children1].via=='Cash'){
								if(data[main1][children1].booking_status=='Booked') {
									totalCashAmount += parseFloat(data[main1][children1].amount);
								}
								if(data[main1][children1].booking_status=='Cancelled') {
									totalCancelledCashAmount += parseFloat(data[main1][children1].amount);
								}
							}else if(data[main1][children1].via=='Invoiced'){
								if(data[main1][children1].booking_status=='Booked') {
									totalInvoicedAmount += parseFloat(data[main1][children1].amount);
								}
								if(data[main1][children1].booking_status=='Cancelled') {
									totalCancelledInvoicedAmount += parseFloat(data[main1][children1].amount);
								}
							}

						}
					}
					total = totalCardAmount+totalCashAmount+totalInvoicedAmount;
					totalCancelled = totalCancelledCardAmount + totalCancelledCashAmount + totalCancelledInvoicedAmount;
					var html = "";

					html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='affiliateReportHeader'>";
					html += "<thead><tr><th class='th-custom-css'>Date Range</th><th class='th-custom-css'>Total Card Amount</th><th class='th-custom-css'>Total Cash Amount</th><th class='th-custom-css'>Total Invoiced Amount</th><th class='th-custom-css'>Total Amount</th></tr></thead>";

					html += "<tbody>";
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + " (Booked)</td><td class='td-custom-css success-text-color fw-normal'>" + currency(totalCardAmount.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency(totalCashAmount.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency(totalInvoicedAmount.toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency(total.toFixed(2)).format() + "</td> </tr>";
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + moment(dateRange.currentDate).format('MM-DD-YYYY') + " - " + moment(dateRange.toDate).format('MM-DD-YYYY') + " (Cancelled)</td><td class='td-custom-css alert-text-color fw-normal'>" + currency(totalCancelledCardAmount.toFixed(2)).format() + "</td><td class='td-custom-css alert-text-color fw-normal'>" + currency(totalCancelledCashAmount.toFixed(2)).format() + "</td><td class='td-custom-css alert-text-color fw-normal'>" + currency(totalCancelledInvoicedAmount.toFixed(2)).format() + "</td><td class='td-custom-css alert-text-color fw-normal'>" + currency(totalCancelled.toFixed(2)).format() + "</td> </tr>";
					html += "<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'><b>Total</b></td><td class='td-custom-css success-text-color fw-normal'>" + currency((totalCancelledCardAmount+totalCardAmount).toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency((totalCashAmount+totalCancelledCashAmount).toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency((totalInvoicedAmount+totalCancelledInvoicedAmount).toFixed(2)).format() + "</td><td class='td-custom-css success-text-color fw-normal'>" + currency((total+totalCancelled).toFixed(2)).format() + "</td> </tr>";

					html += "</tbody></table></div>";

					$('#mainTableDiv').append(html);

					if(data.length != 0) {
						$("#datatableForFarHarborDetails").css("display", "inline-table");
						$(function() {
							var table = $('#datatableForFarHarborDetails').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: false,
								ordering: false,
								paging: false,
								info: false,
								ajax:  {
									method: 'GET',
									url: fareharborbookingReportUrl2,
								},
								columns: [
									{
										data: 'bookingid',
										name: 'bookingId',
										render: function (data, type, row, meta) {
											const pathname = '{{ request()->segment(1) }}';
											const BaseUrl ='{{ url('/') }}';
											var URL = '';
											if(pathname == "admin"){
												URL = BaseUrl+ '/admin/tour-operator/booking/';
											}else{
												URL = BaseUrl+ '/tour-operator/booking/';
											}
											var set = '<a target="_blank" href='+URL + data + '>' + data + '</a>';
											return set;
										},
										className: 'td-custom-css'
									},
									{
										data: 'bookingdate',
										name: 'bookingdate',
										render: function (data, type, row, meta) {
											return moment(row.bookingdate).format('MM-DD-YYYY') + ' @ ' + row.time_from;
										},
										className: 'td-custom-css'
									},
									{
										data: 'via',
										name: 'via',
										className: 'td-custom-css'
									},
									{
										data: 'amount',
										render: function (data, type, row, meta) {
											return currency(data).format()
										},
										className: 'td-custom-css'
									},
									{
										data: 'name',
										name: 'name',
										className: 'td-custom-css'
									},
									{
										data: 'phone',
										name:'phone',
										className: 'td-custom-css'
									},
									{
										data: 'email',
										name:'email',
										className: 'td-custom-css'
									},
									{
										data: 'booking_status',
										name: 'booking_status',
										className: 'td-custom-css'
									},
									{
										data: 'comments',
										name: 'comments',
										className: 'td-custom-css'
									},
								],
							});
						});
					}else{
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableBookingReport').empty();
					}
				});
		}

		//Today availability report
		function getDetailsTodayAvailability() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
			var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
			$('#datatableForTransaction').css('display','none');
			$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
             $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			showLoading();

			var dateRange = reportDateRange();
			$.get(availabilityReportUrl.replace('_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + selectedOperator + "/" + selectedPackage)
				.done(function(data) {
					updateReportInfo();
					hideLoading();
					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].imageName);
									break outerLoop;
								}
							}
					}

					updateReportView();
					updateActionBtn(downloadReport, (data.length === 0));


					if(data.length != 0) {
						var overallTotalBookedAmount = 0;
						var overallRefundAmount = 0
						var overallProfitAmount = 0;

						for(var main in data) {
							for(var children in data[main]) {
								overallTotalBookedAmount += data[main][children].amountTotal;
							}
						}
						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						var arraykeyCount = 0;
						for(var main in data)
						{
							var html = "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><span>Date - "+main+"</span><thead><tr><th class='th-custom-css'>#</th><th class='th-custom-css'>Time Slot</th><th class='th-custom-css'>Total Seats</th><th class='th-custom-css'>Available Seats</th><th class='th-custom-css'>Booked Seats</th><th class='th-custom-css'>Cancelled Seats</th></tr></thead>";

							var i = 1;
							var particularTotalBookedAmount = 0;
							var particularRefundAmount = 0
							var particularProfitAmount = 0;
							for(var children in data[main]) {
								particularTotalBookedAmount += parseFloat(data[main][children].amountTotal);

								html += "<tbody><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>"+i+"</td> <td class='td-custom-css'>"+data[main][children].timeslot+"</td><td class='td-custom-css'>"+data[main][children].seats+"</td><td class='td-custom-css'>"+data[main][children].availableSeats+"</td> <td class='td-custom-css'>"+data[main][children].bookedSeatsTotal+"</td> <td class='td-custom-css'>"+data[main][children].cancelledSeatsTotal+"</td></tr></tbody>";

								i++;
							}
							particularTotalBookedAmount += parseFloat(data[main][children].amountTotal);
							html += "</table></div>";
							$('#mainTableDiv').append(html);
							arraykeyCount++;
						};
					} else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>#</th><th class='th-custom-css'>Time Slot</th><th class='th-custom-css'>Total Seat</th><th class='th-custom-css'>Available Seat</th><th class='th-custom-css'>Booked Seat</th><th class='th-custom-css'>Cancelled Seat</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table></div>");
					};
				});
		}

		// Closure Report
		function getClosureReport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
 			$('#datatableForaffiliateSeatsReport').css('display','none');
 			$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			showLoading();

			var dateRange = reportDateRange();
			$.get(closureReportUrl.replace('_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + selectedOperator + '/' + selectedPackage).done(function(data) {
				updateReportInfo();
				updateReportView();
				hideLoading();
				updateActionBtn(downloadReport, (data.length === 0));

				if(data.length != 0) {

					var html = "";
					for(var main in data) {
                            html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>"
								+ "<span>Date - " + data[main].bookings[0].formatted_date + "<br> <b>@  "+data[main].slotTime+"</b></span>"
								+ '<thead><tr>'
								+ '<th class="th-custom-css">#</th>'
								+ '<th class="th-custom-css">Name</th>'
								+ '<th class="th-custom-css">Email</th>'
								+ '<th class="th-custom-css">Phone</th>'
								+ '<th class="th-custom-css">Status</th>'
								+ '<th class="th-custom-css">Email Sent?</th>'
								+ '<th class="th-custom-css">Action</th>'
								+ "</tr></thead>";

							for (var children in data[main].bookings) {
								html += "<tbody><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>" + data[main].bookings[children].tbm_id + "</td> <td class='td-custom-css'>"
									+ data[main].bookings[children].customer_name + "</td> <td class='td-custom-css'>"
									+ data[main].bookings[children].customer_email + "</td> <td class='td-custom-css'>"
									+ data[main].bookings[children].customer_phone + "</td> <td class='td-custom-css " + (data[main].bookings[children].status == 'Booked' ? 'success-text-color' : 'alert-text-color') + "'>"
									+ data[main].bookings[children].status + "</td> <td class='td-custom-css " + (data[main].bookings[children].closure_email_sent == true ? 'success-text-color' : 'alert-text-color') + "'>"
									+ (data[main].bookings[children].closure_email_sent == true ? 'Yes' : 'No') + "</td><td class='td-custom-css'>"
									+ (data[main].bookings[children].closure_email_sent == false ? "<button data-booking-id='" + data[main].bookings[children].tbm_id + "' data-date='" + data[main].bookings[children].date + "' data-slot-id='" + data[main].bookings[children].tour_slot_time_id + "' onclick='return lowerAntelopeCanyonClosure(this);' id='btnPrint' class='btn btn-secondary inline-block bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-xs font-semibold py-2 px-10 rounded-md cursor-pointer transition-all duration-300 ease-in-out'><i class='fa fa-paper-plane'></i></button>" : "") + "</td></tr></tbody>";
							};

							html += "</table></div>";
						};
					$('#mainTableDiv').append(html);
				} else {
					$('#datatableBookingReport').empty();
					$('#datatableFortipDetails').empty();
					$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>"
					+'<thead><tr>'
					+'<th class="th-custom-css">#</th>'
					+'<th class="th-custom-css">Name</th>'
					+'<th class="th-custom-css">Email</th>'
					+'<th class="th-custom-css">Phone</th>'
					+'<th class="th-custom-css">Status</th>'
					+'<th class="th-custom-css">Email Sent?</th>'
					+'<th class="th-custom-css">Action</th>'
					+'</tr></thead><tbody>'
					+'<tr class=odd>'
					+'<td class="td-custom-css dataTables_empty" valign=top colspan=10>'
					+'<center>No data available in table</center>'
					+'</td></tr></tbody></table></div>');
				};
			});
		}

		//Today availability detail report
		function getDetailsTodayAvailabilityDetails() {
			var selectedPackage = $("#tour_package").val();
			var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForTransaction').css('display','none');
			$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			$.get(availabilityDetailsReportUrl.replace('_DATE_', currentDate) + selectedPackage)
				.done(function(data){
					updateReportView();
					updateReportInfo();
					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									for(var internalChild in data[main][children]) {
										$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children][internalChild].touroperatorlogo);
										break outerLoop;
									}
								}
							}
					}

					if(data.length != 0) {
						var overallTotalBookedAmount = 0;
						var overallRefundAmount = 0
						var overallProfitAmount = 0;

						for(var main in data) {
							for(var children in data[main]) {
								overallTotalBookedAmount += (data[main][children].amountTotal);
							}
						}
						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						var arraykeyCount = 0;
						for(var main in data)
						{
							var arrayKeysInternal = [];
							for (var main in data) {
								arrayKeysInternal = Object.keys(data[main]);
							}

							var html = "";

							var i = 0;
							var particularTotalBookedAmount = 0;
							var particularRefundAmount = 0
							var particularProfitAmount = 0;

							for(var children in data[main]) {
								html += "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'>";

								if(i==0) {
									html += "<span>Date - "+arrayKeys[arraykeyCount]+"</span>";
								}

								html+= "<br/><span>Timesolts - "+arrayKeysInternal[i]+"</span>	<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Phone</th><th class='th-custom-css'>Create Date</th><th class='th-custom-css'>Check In</th><th class='th-custom-css'>Wavier sign</th><th class='th-custom-css'> Person</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Payment</th><th class='th-custom-css'>Status</th></tr></thead>";
								html += "<tbody>"
								for(var internalChild in data[main][children]) {
									if(data[main][children][internalChild].is_affiliate=='Yes'){
										html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out af_warning' style='background-color: #ff03;'><td class='td-custom-css'>"+data[main][children][internalChild].bookingid+"</td> <td class='td-custom-css'>"+data[main][children][internalChild].name+"</td><td class='td-custom-css'>"+data[main][children][internalChild].phone_number+"</td><td class='td-custom-css'>"+data[main][children][internalChild].createdate+"</td> ";

										if(data[main][children][internalChild].remainingCheckin == data[main][children][internalChild].tourists) {
											html += "<td class='td-custom-css'>"+data[main][children][internalChild].remainingCheckin+" Not Checked In<br/> </td>";
										} else {
											html += "<td class='td-custom-css'>";

											if(data[main][children][internalChild].remainingCheckin !== 0) {
												html += ""+data[main][children][internalChild].remainingCheckin+" Not Checked In<br/>";
											}
											if(data[main][children][internalChild].firstCheckIn !== 0) {
												html += ""+data[main][children][internalChild].firstCheckIn+" 1st Checked In<br/>";
											}
											if(data[main][children][internalChild].secondCheckIn !== 0) {
												html += ""+data[main][children][internalChild].secondCheckIn+" 2nd Checked In<br/>";
											}
											if(data[main][children][internalChild].absent !== 0) {
												html += ""+data[main][children][internalChild].absent+" Absent Checked In<br/>";
											}

											html += "</td>";
										}

										html+=" <td class='td-custom-css'>"+data[main][children][internalChild].liability_waiver_signing+"</td>";

										html += "<td class='td-custom-css'>";

										if(data[main][children][internalChild].Adult !== 0) {
											html += ""+data[main][children][internalChild].Adult+" Adult<br/>";
										}
										if(data[main][children][internalChild].Children !== 0) {
											html += ""+data[main][children][internalChild].Children+" Children<br/>";
										}
										if(data[main][children][internalChild].SeniorCitizen !== 0) {
											html += ""+data[main][children][internalChild].SeniorCitizen+" SeniorCitizen<br/>";
										}
										if(data[main][children][internalChild].Teenager !== 0) {
											html += ""+data[main][children][internalChild].Teenager+" Teenager<br/>";
										}
										if(data[main][children][internalChild].infant !== 0) {
											html += ""+data[main][children][internalChild].infant+" infant<br/>";
										}

										html += "</td>";

										html += "<td class='td-custom-css'>" + currency(data[main][children][internalChild].amount).format() + "</td><td class='td-custom-css'>"+data[main][children][internalChild].payment+"</td><td class='td-custom-css'>"+data[main][children][internalChild].status+"</td></tr>";
									}else{
										html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>"+data[main][children][internalChild].bookingid+"</td> <td class='td-custom-css'>"+data[main][children][internalChild].name+"</td><td class='td-custom-css'>"+data[main][children][internalChild].phone_number+"</td><td class='td-custom-css'>"+data[main][children][internalChild].createdate+"</td> ";

										if(data[main][children][internalChild].remainingCheckin == data[main][children][internalChild].tourists) {
											html += "<td class='td-custom-css'>"+data[main][children][internalChild].remainingCheckin+" Not Checked In<br/> </td>";
										} else {
											html += "<td class='td-custom-css'>";

											if(data[main][children][internalChild].remainingCheckin !== 0) {
												html += ""+data[main][children][internalChild].remainingCheckin+" Not Checked In<br/>";
											}
											if(data[main][children][internalChild].firstCheckIn !== 0) {
												html += ""+data[main][children][internalChild].firstCheckIn+" 1st Checked In<br/>";
											}
											if(data[main][children][internalChild].secondCheckIn !== 0) {
												html += ""+data[main][children][internalChild].secondCheckIn+" 2nd Checked In<br/>";
											}
											if(data[main][children][internalChild].absent !== 0) {
												html += ""+data[main][children][internalChild].absent+" Absent Checked In<br/>";
											}

											html += "</td>";
										}

										html+=" <td class='td-custom-css'>"+data[main][children][internalChild].liability_waiver_signing+"</td>";

										html += "<td class='td-custom-css'>";

										if(data[main][children][internalChild].Adult !== 0) {
											html += ""+data[main][children][internalChild].Adult+" Adult<br/>";
										}
										if(data[main][children][internalChild].Children !== 0) {
											html += ""+data[main][children][internalChild].Children+" Children<br/>";
										}
										if(data[main][children][internalChild].SeniorCitizen !== 0) {
											html += ""+data[main][children][internalChild].SeniorCitizen+" SeniorCitizen<br/>";
										}
										if(data[main][children][internalChild].Teenager !== 0) {
											html += ""+data[main][children][internalChild].Teenager+" Teenager<br/>";
										}
										if(data[main][children][internalChild].infant !== 0) {
											html += ""+data[main][children][internalChild].infant+" infant<br/>";
										}

										html += "</td>";

										html += "<td class='td-custom-css'>" + currency(data[main][children][internalChild].amount).format() + "</td><td class='td-custom-css'>"+data[main][children][internalChild].payment+"</td><td class='td-custom-css'>"+data[main][children][internalChild].status+"</td></tr>";
									}
								}
								html += "</tbody>"
								i++;
							}
							particularProfitAmount = particularTotalBookedAmount - particularRefundAmount;

							html += "</table></div>";
							$('#mainTableDiv').append(html);
							arraykeyCount++;
						};
					}
					else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#mainTableDiv').html("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport'><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Phone</th><th class='th-custom-css'>Create Date</th><th class='th-custom-css'>Check In</th><th class='th-custom-css'>Wavier sign</th><th class='th-custom-css'> Person</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Payment</th><th class='th-custom-css'>Status</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=10><center>No data available in table</center></td></tr></tbody></table></div>");

					};
				});
		}

		// Summary report
		function getDetailsSummaryReport(){
			var selectedPackage = $("#tour_package").val();
			var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
			var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');

			$.get(bookingsReportUrl.replace('_FROM_DATE_', currentDate).replace('_TO_DATE_', toDate) + selectedPackage)
				.done(function(data) {
					updateReportView();
					updateReportInfo();
					if(data) {
						outerLoop:
							for(var main in data) {
								for(var children in data[main]) {
									$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + data[main][children].imageName);
									break outerLoop;
								}
							}
					}
					if(data.length != 0) {
						var overallTotalBookedAmount = 0;
						var overallRefundAmount = 0
						var overallProfitAmount = 0;
						$('#mainTableDiv').empty();
						$('#datatableForBookingDetails').css('display','none');
						$('#datatableForRefundDetails').css('display','none');
						$('#bookingTableBody').empty();
						$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
	    				$('#datatableForTransaction').css('display','none');
						$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            			$('#datatableForTransactionSummary').css('display','none');
						$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                        $('#datatableForActivityLogsReport').css('display','none');

						for(var main in data) {
							for(var children in data[main]) {
								overallTotalBookedAmount += parseFloat(data[main][children].amountTotal.toFixed(2));
								overallRefundAmount+= parseFloat( data[main][children].refundTotal.toFixed(2));
								overallProfitAmount += parseFloat(data[main][children].profit.toFixed(2));
							}
						}
						var arrayKeys = [];
						for (var main in data) {
							arrayKeys = Object.keys(data);
						}

						var arraykeyCount = 0;
						for(var main in data) {
							var i = 1;
							var html = "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable w-full border-collapse text-left' id='datatableBookingReport'><span>Date - "+arrayKeys[arraykeyCount]+"</span><thead><tr><th class='th-custom-css'>#</th><th class='th-custom-css'>Time Slot</th><th class='th-custom-css'>Available Seats</th><th class='th-custom-css'>Booked Seats</th><th class='th-custom-css'>Canceled Seat</th><th class='th-custom-css'>Amount Total</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Profit</th></tr></thead>";

							var particularTotalBookedAmount = 0;
							var particularRefundAmount = 0
							var particularProfitAmount = 0;
							for(var children in data[main]) {
								particularTotalBookedAmount += parseFloat(data[main][children].amountTotal.toFixed(2));
								particularRefundAmount+= parseFloat(data[main][children].refundTotal.toFixed(2));
								particularProfitAmount += parseFloat(data[main][children].profit.toFixed(2));

								html += "<tbody><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='td-custom-css'>"+i+"</td> <td class='td-custom-css'>"+data[main][children].timeslot+"</td> <td class='td-custom-css'>"+data[main][children].availableSeats+"</td> <td class='td-custom-css'>"+data[main][children].bookedSeatsTotal+"</td> <td class='td-custom-css'>"+data[main][children].cancelledSeatsTotal+"</td> <td class='td-custom-css'>" + currency(data[main][children].amountTotal.toFixed(2)).format() + "</td> <td class='td-custom-css'>" + currency(data[main][children].refundTotal.toFixed(2)).format() + "</td><td class='td-custom-css'>" + currency(data[main][children].profit.toFixed(2)).format() + "</td></tr></tbody>";

								i++;
							}
							particularProfitAmount = particularTotalBookedAmount - particularRefundAmount;

							html += "<tfoot><tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out'><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' colspan='5'></td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>" + currency(particularTotalBookedAmount.toFixed(2)).format() + "</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm'>" + currency(particularRefundAmount.toFixed(2)).format() + "</td><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' style='text-align: left;padding-right: 20px;'>" + currency(particularProfitAmount).format() + "</td></tr></tfoot>";

							html += "</table></div>";
							$('#mainTableDiv').append(html);
							arraykeyCount++;
						};

						overallProfitAmount = overallTotalBookedAmount - overallRefundAmount;

						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='datatableBookingReport' ><tfoot><tr><td class='px-4 py-2.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm' colspan='9'><div class='grand-total'><div class='flex items-center justify-between tsub'><div>Total Amount:</div><div>"+currency(overallTotalBookedAmount).format()+"</div></div><div class='flex items-center justify-between discount'><div>Total Refund Amount:</div><div>"+currency(overallRefundAmount).format()+"</div></div><div class='flex items-center justify-between tbooking'><div>Profit:</div><div>"+currency(overallProfitAmount).format()+"</div></div></div></td></tr></tfoot></table></div>");
					} else {
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#mainTableDiv').empty();
						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left'  ><thead><tr><th class='th-custom-css'>#</th><th class='th-custom-css'>Time Slot</th><th class='th-custom-css'>Available Seat</th><th class='th-custom-css'>Booked Seat</th><th class='th-custom-css'>Cancle Seat</th><th class='th-custom-css'>Amount Total</th><th class='th-custom-css'>Refund Amount</th><th class='th-custom-css'>Profit</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=8><center>No data available in table</center></td></tr></tbody></table></div>");
					}
				});
		}

		// Booking Details report
		function getDetailsSummaryDetails()  {
			var selectedPackage = $("#tour_package").val();
			var currentDate = moment($('#fromDate').datepicker('getDate')).format('YYYY-MM-DD');
			var toDate = moment($('#toDate').datepicker('getDate')).format('YYYY-MM-DD');
			var bookingsSummaryReportUrl2 = bookingsSummaryReportUrl.replace('_FROM_DATE_', currentDate).replace('_TO_DATE_', toDate) + selectedPackage;

			$.get(bookingsSummaryReportUrl2)
				.done(function(data) {
					updateReportView();
					updateReportInfo();
					updateActionBtn(downloadReport, (data.data.length === 0));
					$("#datatableForBookingDetails").css("display", "inline-table");
					$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    				$('#datatableForTransaction').css('display','none');
					$('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            		$('#datatableForTransactionSummary').css('display','none');
					$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForActivityLogsReport').css('display','none');


					if(data.data) {
						if(data.data.length > 0) {
							var imageName = data.data[0].touroperatorlogo;
							$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + imageName);
							$('#datatableBookingReport').empty()
						}
					}

					if(data.length != 0) {
						$(function() {
							var table = $('#datatableForBookingDetails').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: false,
								ordering: false,
								paging: false,
								info: false,
								ajax: {
									method: 'GET',
									url: bookingsSummaryReportUrl2,
								},
								columns: [
									{ data: 'bookingid', name: 'bookingid', className: 'td-custom-css'}, //ID
									{ data: 'name', name: 'c.name', className: 'td-custom-css' }, //Name
									{ data: 'bookingdate', name:'tsd.date', render: formatDate, className: 'td-custom-css' }, //Booking Date
									{ data: 'createdate', name:'tsd.created_at', render: formatDate, className: 'td-custom-css' }, //Create Date
									{ data: 'tourists', name:'tbt.tourists', className: 'td-custom-css' }, //Tourists
									{ data: 'amount', className: 'td-custom-css', render: (_type, data, _row, _meta)=>`${currency(data).format()}`}, //Amount
									{ data: 'payment', name: 't.status', className: 'td-custom-css'}, //Payment Status
									{ data: 'status', name: 'tbm.status', className: 'td-custom-css'} //Booking Status
								],
								createdRow: function(row, data, dataIndex) {
									if(data['is_affiliate'] ==  `Yes`){
										$(row).addClass('af_warning');
									}
								}
							});
						});
					}else{
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
					}
				});
		}

		// Failed Payment Report
		function failedpaymentReport() {
			var selectedOperator = $("#tour_operator").val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();

			$('#datatableForTransaction').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransaction').css('display','none');
            $('#datatableForTransactionSummary').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForTransactionSummary').css('display','none');
            $('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
    		$('#datatableForaffiliateSeatsReport').css('display','none');
    		$('#datatableForRefundDetails').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForRefundDetails').css('display','none');
            $('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageReport').css('display','none');
            $('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForAddonsPackageDetailsReport').css('display','none');
			$('#datatableForActivityLogsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
            $('#datatableForActivityLogsReport').css('display','none');
			var dateRange = reportDateRange();
			var pendingpaymentReportUrl2 = pendingpaymentReportUrl.replace('_FROM_DATE_', dateRange.currentDate).replace('_TO_DATE_', dateRange.toDate) + "/" + selectedOperator + "/" + selectedPackage;
			showLoading();
			$.get(pendingpaymentReportUrl2)
				.done(function(data) {
					updateReportView();
					updateReportInfo();
					updateActionBtn(downloadReport, (data.length === 0));
					hideLoading();

					if(data.data) {
						if(data.data.length > 0) {
							var imageName = data.data[0].touroperatorlogo;
							$('#mainLogoPrint').prop('src', rootPath + 'images/tour-operator/logo/' + imageName);
							$('#bookingTableBody').empty();
						}
					}
					if(data.length != 0) {
						var html = "<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover datatable datatable-filter w-full border-collapse text-left' id='tableFailedReport'>" +
							"<thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Create Date</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Payment</th><th class='th-custom-css'>Status</th></tr></thead><tbody>";
						for(var main in data) {
							var afflclass = (data[main].is_affiliate=='Yes')?'af_warning':'';
							html +="<tr class='hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out "+afflclass+"'><td class='td-custom-css'><a target=_blank href="+ bookingInfoUrl + data[main].bookingid + ">"+ data[main].bookingid +"</a></td><td class='td-custom-css'>"+data[main].name+"</td><td class='td-custom-css'>"+formatDate(data[main].bookingdate)+"</td><td class='td-custom-css'>"+formatDate(data[main].createdate)+"</td><td class='td-custom-css'>"+data[main].tourists+"</td><td class='td-custom-css'>"+currency(data[main].amount).format()+"</td><td class='td-custom-css'>"+currency(data[main].Payment).format()+"</td><td class='td-custom-css'>"+data[main].status+"</td></tr>";
						}
						html += "</tbody></table></div>";
						$('#mainTableDiv').append(html);


					}else{
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#mainTableDiv').empty();
						$('#mainTableDiv').append("<div class='w-full overflow-y-auto mt-2'><table class='table table-bordered table-striped table-hover w-full border-collapse text-left'  ><thead><tr><th class='th-custom-css'>ID</th><th class='th-custom-css'>Name</th><th class='th-custom-css'>Booking Date</th><th class='th-custom-css'>Create Date</th><th class='th-custom-css'>Tourists</th><th class='th-custom-css'>Amount</th><th class='th-custom-css'>Payment</th><th class='th-custom-css'>Status</th></tr></thead><tbody><tr class=odd><td class='td-custom-css dataTables_empty' valign=top colspan=8><center>No data available in table</center></td></tr></tbody></table></div>");

					}
				});
		}

		function activityLogsReport() {
			var dateRange = reportDateTimeRange();
			var eventType = $('#event_type :selected').val();
			var userName = $('#user_name :selected').val();
		    var activityLogsReportUrl2 = activityLogsReportUrl+'?fromDate='+encodeURIComponent(dateRange.currentDate)+'&toDate='+dateRange.toDate+'&eventType='+eventType+'&userName='+userName;
			$.get(activityLogsReportUrl2).done(function(data) {
					updateReportView();
					updateReportInfo();
					updateActionBtn(downloadReport, (data.data.length === 0));
					$("#datatableForActivityLogsReport").css("display", "inline-table");
					$('#datatableForBookingDetails').dataTable({
						bDestroy: true,
						searching: false,
						ordering: false,
						paging: false,
						info: false
					});
					$('#datatableForBookingDetails').css('display', 'none');
					$('#datatableForTransaction').dataTable({
						bDestroy: true,
						searching: false,
						ordering: false,
						paging: false,
						info: false
					});
					$('#datatableForTransaction').css('display', 'none');
					$('#datatableForTransactionSummary').dataTable({
						bDestroy: true,
						searching: false,
						ordering: false,
						paging: false,
						info: false
					});
					$('#datatableForTransactionSummary').css('display', 'none');
					$('#datatableForAddonsPackageReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForAddonsPackageReport').css('display','none');
					$('#datatableForaffiliateSeatsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForaffiliateSeatsReport').css('display','none');
					$('#datatableForAddonsPackageDetailsReport').dataTable({ bDestroy: true, searching: false, ordering: false, paging: false, info: false});
                    $('#datatableForAddonsPackageDetailsReport').css('display','none');

					if (data.data) {
						if (data.data.length > 0) {
							var imageName = data.data[0].touroperatorlogo;
							$('#mainLogoPrint').prop('src', "rootPath + 'images/tour-operator/logo/' + imageName");
							$('#datatableBookingReport').empty();
							$('#bookingTableBody').empty();
						}
					}
					if (data.length != 0) {
						$(function() {
							var table = $('#datatableForActivityLogsReport').DataTable({
								createdRow: function(row, data, dataIndex) {
									$(row).addClass('hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out');
								}, 
								processing: true,
								serverSide: false,
								responsive: true,
								bDestroy: true,
								searching: true,
								ordering: true,
								paging: true,
								info: false,
								ajax: {
									method: 'GET',
									url: activityLogsReportUrl2,
								},
								order: [[5, 'desc']], 
								columns: [
									{
										data: 'id',
										name: 'ID',
										className: 'td-custom-css'
									},
									{
										data: 'display_name',
										name: 'Role',
										className: 'td-custom-css'
									},
									{
										data: 'user_id',
										name: 'User Name',
										className: 'td-custom-css'
									},
									{
										data: 'event',
										name: 'Event Type',
										className: 'td-custom-css'
									},
									{
										data: 'new_values',
										name: 'Data',
										className: 'td-custom-css',
										render: function(data, type, row) {
											return `<button class="btn btn-info btn-sm json-preview-btn btn-warning-small group whitespace-nowrap" data-json='${JSON.stringify(data)}'>JSON Preview</button>`;
										}
									},
									{
										data: 'created_at',
										name: 'Created Date and Time',
										type:'date',
										className: 'td-custom-css'
									},
									{
										data: 'auditable_type',
										name: 'Auditable Type',
										className: 'td-custom-css'
									},
								],
							});

							$('#datatableForActivityLogsReport').on('click', '.json-preview-btn', function() {
								var jsonString = $(this).data('json');
								try {
									jsonString = jsonString.replace(/^"|"$/g, '');
									var jsonData = JSON.parse(jsonString);
									var jsonPreview = JSON.stringify(jsonData, null, 2);
									$('#jsonPreviewText').text(jsonPreview);
									$('#jsonPreviewModal').modal('show');
								} catch (e) {
									$('#jsonPreviewText').text("Error parsing JSON: " + e.message);
									$('#jsonPreviewModal').modal('show');
								}
							});
						});
					} else {
						$('#datatableForActivityLogsReport').empty();
						$('#datatableForBookingDetails').empty();
						$('#datatableForRefundDetails').empty();
						$('#datatableForFarHarborDetails').empty();
						$('#datatableBookingReport').empty();
						$('#datatableFortipDetails').empty();
					}
			});
        }
		$('.close, .btn-secondary').click(function() {
			$('#jsonPreviewModal').modal('hide');
		});
	    // Activity Logs Report End

		function downloadReport()  {
			switch($("#select-report").val()) {
				case "Permit Fee Report": dailyreportDownload(); break;
				case "Permit Fee Details Report": dailyreportDetailsDownload(); break;
				case "Availability Report": getDetailsTodayAvailabilityDownload(); break;
				case "Financial Report": financialReportDownload(); break;
				case "Booking Details Report": bookingdetailsreportDownload(); break;
				case "Tip Report": TipReportDownload(); break;
				case "Tip Summary Report": TipSummaryReportDownload(); break;
				case "Software Fee Report": softwarefeereportDownload(); break;
				case "Tax Collection Report": taxcollectionreportDownload(); break;
				case "Cash Collection Report":
					cashcollectionreportDownload();
					break;
				case "Transaction Report":
					transactionReportDownload();
					break;
				case "Transaction Summary Report":
					transactionSummaryReportDownload();
					break;
				case "Card Collection Report":
					cashcardcollectionreportDownload();
					break;
				case "Check/Wire Collection Report":
					wirecollectionreportDownload();
					break;
				case "Stripe Payment Report":
					cashcardcollectionreportDownload();
					break;
				case "Failed Payment Report": failedPaymentReportDownload(); break;
				case "Refund payment": refundpaymentreportDownload(); break;
				case "FareHarbor Booking Report": fareharborbookingreportDownload(); break;
				case "Affiliate Booked Report": affiliatereportDownload(); break;
				case "Affiliate Seats Report": affiliateSeatsReportDownload(); break;
				case "Affiliate Invoice Report": affiliatereportAllDownload(); break;
				case "Affiliate Comprehensive Report": affiliatereportComprehensiveDownload(); break;
				case "Cancel Booking Report": cancelbookingreportDownload(); break;
				case "Add-ons Package Report": addonsPackageReportDownload(); break;
				case "Add-ons Package Details Report": addonsPackageDetailsReportDownload(); break;
				case "Email Report": emailreportDownload(); break;
				case "Daily Activity Report": dailyactivityReportDownload(); break;
				case "Invalid Bookings Report": invalidBookingsReportDownload(); break;
				case "Closure Emails Report": lowerAntelopeCanyonClosureDownload(); break;
				case "Revenue Report": revenueReportDownload( "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'revenue_report_export')}}" ); break;
				case "Promotion Report": promotionReportDownload( "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'promotion_report_export')}}" ); break;
				case "Affiliate Invoice Insights Report": invoiceInsightsReportDownload( "{{$uTypeAdmin}}", "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_all_report_export')}}" ); break;
				case "Staff Booking Report": staffBookingReportDownload( "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'staff_booking_report_export')}}" ); break;
				case "Activity Logs Report": activityLogsReportDownload( "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'activity_logs_report_export')}}" ); break;
				case "OTAs Booking Report": otaReportDownload( "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'ota_report_export')}}" ); break;
			}
		}

		function dailyactivityReportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			selectedPackage = selectedPackage ? selectedPackage : 0
			var viewBy = $("#viewby-select").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadDailyReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.daily-activity-report', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':viewBy', ':export'])}}";
			downloadDailyReportUrl = downloadDailyReportUrl.replace(':fromDate', fromDate).replace(':toDate', toDate).replace(':selectedOperator', selectedOperator).replace(':selectedPackage', selectedPackage).replace(':viewBy', viewBy).replace(':export', 1);

			let filters = {};
			$(".daily-filter:checkbox:checked").each((_idx, ele) => {
				let el = $(ele);
				let tp = el.data('type');
				if (!_.has(filters, tp)) { filters[tp] = []; }
				filters[tp].push(encodeURIComponent(el.val()));
			});

			let query = '?filters=1'
			for (let key in filters) {
				if (filters[key].length > 0) {
					query += `&${key}=${filters[key].join("|")}`
				}
			}

			window.location = downloadDailyReportUrl + query;
		}

		function dailyreportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			selectedPackage = selectedPackage ? selectedPackage : 0;
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;

			var downloadDailyReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'daily_report_export')}}";

			// Build filter parameters from stored state instead of DOM inputs
			var filterParams = [];

			if (permitFeeReportFilterState.paymentStatus && permitFeeReportFilterState.paymentStatus !== 'all') {
				filterParams.push('paymentStatus=' + encodeURIComponent(permitFeeReportFilterState.paymentStatus));
			}

			if (permitFeeReportFilterState.paymentMethod && permitFeeReportFilterState.paymentMethod !== 'all') {
				filterParams.push('paymentMethod=' + encodeURIComponent(permitFeeReportFilterState.paymentMethod));
			}

			if (permitFeeReportFilterState.bookingType && permitFeeReportFilterState.bookingType !== 'all') {
				filterParams.push('bookingType=' + encodeURIComponent(permitFeeReportFilterState.bookingType));
			}

			var urlParams = 'tourOperatorId=' + encodeURIComponent(selectedOperator) + 
							'&tourPackageId=' + encodeURIComponent(selectedPackage) + 
							'&fromDate=' + encodeURIComponent(fromDate) + 
							'&toDate=' + encodeURIComponent(toDate) + 
							'&csv=' + encodeURIComponent(csv_export);

			if (filterParams.length > 0) {
				urlParams += '&' + filterParams.join('&');
			}

			console.log('Export URL params:', urlParams); // Debug log
			window.location = downloadDailyReportUrl + '?' + urlParams;
		}

		function financialReportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			var downloadFinancialReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'financial_report_export')}}";

			window.location = downloadFinancialReportUrl+'?tourOperator='+selectedOperator+'&tourPackage='+selectedPackage+'&dateFrom='+fromDate+'&dateTo='+toDate+'&csv='+csv_export;
		}

		function cancelbookingreportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			var downloadCancelBookingReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'cancel_booking_report_export')}}";

			window.location = downloadCancelBookingReportUrl+'?tourOperatorId='+selectedOperator+'&tourPackageId='+selectedPackage+'&fromDate='+fromDate+'&toDate='+toDate+'&csv='+csv_export;
		}


		function dailyreportDetailsDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			selectedPackage = selectedPackage ? selectedPackage : 0;
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;

			var downloadDailyReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'daily_report_details_export')}}";

			// Build filter parameters from stored state
			var filterParams = [];
			
			if (permitFeeReportFilterState.paymentStatus && permitFeeReportFilterState.paymentStatus !== 'all') {
				filterParams.push('paymentStatus=' + encodeURIComponent(permitFeeReportFilterState.paymentStatus));
			}
			
			if (permitFeeReportFilterState.paymentMethod && permitFeeReportFilterState.paymentMethod !== 'all') {
				filterParams.push('paymentMethod=' + encodeURIComponent(permitFeeReportFilterState.paymentMethod));
			}
			
			if (permitFeeReportFilterState.bookingType && permitFeeReportFilterState.bookingType !== 'all') {
				filterParams.push('bookingType=' + encodeURIComponent(permitFeeReportFilterState.bookingType));
			}

			var urlParams = 'tourOperatorId=' + encodeURIComponent(selectedOperator) + 
							'&tourPackageId=' + encodeURIComponent(selectedPackage) + 
							'&fromDate=' + encodeURIComponent(fromDate) + 
							'&toDate=' + encodeURIComponent(toDate) + 
							'&csv=' + encodeURIComponent(csv_export);
			
			if (filterParams.length > 0) {
				urlParams += '&' + filterParams.join('&');
			}

			console.log('Export Details URL params:', urlParams); // Debug log
			window.location = downloadDailyReportUrl + '?' + urlParams;
		}

		function bookingdetailsreportDownload() {
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadBookingDetailsReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'booking_details_report_export')}}";

			window.location = downloadBookingDetailsReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage;
		}

		function TipReportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadTipReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'tip_report_export')}}";

			window.location = downloadTipReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator;
		}

		function TipSummaryReportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			var downloadTipSummaryReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'tip_summary_report_export')}}";
			window.location = downloadTipSummaryReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourOperatorId='+selectedOperator+'&tourPackageId='+selectedPackage+'&csv='+csv_export;
		}

		function softwarefeereportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var selectedReport = $("#select-report").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadSoftwareFeeReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'software_fee_report_export')}}";

			var demourl = downloadSoftwareFeeReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator;
			$.get(demourl)
				.done(function(data) {
					swal('success', 'download success.', 'success');
				})
				.fail(function() {
					swal('Error occurred', 'Unable to get data from server.', 'error');
				})
				.always(hideLoading);
		}

		function cashcardcollectionreportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			tourPackageId = tourPackageId ? tourPackageId : 0
			var selectedReport = $("#select-report").val();
			var viewBy = $("#viewby-select").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			if (isCashFees == '1') {
				var type = $("#reportType").val();
			} else {
				var type = 'Card';
			}

			var downloadCardCollectionReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.payments-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':selectedReport', ':viewBy', ':export', ':type'])}}";
			downloadCardCollectionReportUrl = downloadCardCollectionReportUrl.replace(':fromDate', fromDate)
				.replace(':toDate', toDate)
				.replace(':selectedOperator', tourOperatorId)
				.replace(':selectedPackage', tourPackageId)
				.replace(':selectedReport', selectedReport)
				.replace(':viewBy', viewBy)
				.replace(':export', 1)
				.replace(':type', type);

			window.location = downloadCardCollectionReportUrl;
		}

		function cashcollectionreportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			tourPackageId = tourPackageId ? tourPackageId : 0;
			var selectedReport = $("#select-report").val();
			var viewBy = $("#viewby-select").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadCardCollectionReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.cash-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':selectedReport', ':viewBy', ':export'])}}";
			downloadCardCollectionReportUrl = downloadCardCollectionReportUrl.replace(':fromDate', fromDate)
				.replace(':toDate', toDate)
				.replace(':selectedOperator', tourOperatorId)
				.replace(':selectedPackage', tourPackageId)
				.replace(':selectedReport', selectedReport)
				.replace(':viewBy', viewBy)
				.replace(':export', 1);

			window.location = downloadCardCollectionReportUrl;
		}

		function transactionReportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			var viewDate = $("#viewby-select").val();
			tourPackageId = tourPackageId ? tourPackageId : 0;
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var checkCard = $('.checkCard').is(":checked");
            var checkCash = $('.checkCash').is(":checked");
            var checkInvoiced = $('.checkInvoiced').is(":checked");
            var checkCancel = $('.checkCancel').is(":checked");
            var checkWire = $('.checkWire').is(":checked");
            var excludeExternal = $('.excludeExternal').is(":checked");
            var includeExternal = $('.includeExternal').is(":checked");
            var onlyShowExternal = $('.onlyShowExternal').is(":checked");
            var includeExternalWithFee = $('.includeExternalWithFee').is(":checked");

			var downloadTransactionReportReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.transaction-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':export', ':checkCard', ':checkCash', ':checkInvoiced', ':checkCancel', ':checkWire', ':excludeExternal', ':includeExternal', ':onlyShowExternal', ':includeExternalWithFee', ':viewDate'])}}";
			downloadTransactionReportReportUrl = downloadTransactionReportReportUrl.replace(':fromDate', fromDate)
				.replace(':toDate', toDate)
				.replace(':selectedOperator', tourOperatorId)
				.replace(':selectedPackage', tourPackageId)
				.replace(':export', 1)
				.replace(':checkCard', checkCard)
				.replace(':checkCash', checkCash)
				.replace(':checkInvoiced', checkInvoiced)
				.replace(':checkCancel', checkCancel)
				.replace(':checkWire', checkWire)
				.replace(':excludeExternal', excludeExternal)
				.replace(':includeExternal', includeExternal)
				.replace(':onlyShowExternal', onlyShowExternal)
				.replace(':includeExternalWithFee', includeExternalWithFee)
				.replace(':viewDate', viewDate);

			window.location = downloadTransactionReportReportUrl;
		}
		function transactionSummaryReportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			var viewDate = $("#viewby-select").val();
			tourPackageId = tourPackageId ? tourPackageId : 0;
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var checkCard = $('.checkCard').is(":checked");
            var checkCash = $('.checkCash').is(":checked");
            var checkInvoiced = $('.checkInvoiced').is(":checked");
            var checkCancel = $('.checkCancel').is(":checked");
            var checkWire = $('.checkWire').is(":checked");
            var excludeExternal = $('.excludeExternal').is(":checked");
            var includeExternal = $('.includeExternal').is(":checked");
            var onlyShowExternal = $('.onlyShowExternal').is(":checked")
            var includeExternalWithFee = $('.includeExternalWithFee').is(":checked");
            var checkUnpaid = $('.checkUnpaid').is(":checked");

			var downloadTransactionSummaryReportReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.transaction-summary-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':export', ':checkCard', ':checkCash', ':checkInvoiced', ':checkCancel', ':checkWire', ':excludeExternal', ':includeExternal', ':onlyShowExternal', ':includeExternalWithFee', ':viewDate', ':checkUnpaid'])}}";
			downloadTransactionSummaryReportReportUrl = downloadTransactionSummaryReportReportUrl.replace(':fromDate', fromDate)
				.replace(':toDate', toDate)
				.replace(':selectedOperator', tourOperatorId)
				.replace(':selectedPackage', tourPackageId)
				.replace(':export', 1)
				.replace(':checkCard', checkCard)
				.replace(':checkCash', checkCash)
				.replace(':checkInvoiced', checkInvoiced)
				.replace(':checkCancel', checkCancel)
				.replace(':checkWire', checkWire)
				.replace(':excludeExternal', excludeExternal)
				.replace(':includeExternal', includeExternal)
				.replace(':onlyShowExternal', onlyShowExternal)
				.replace(':includeExternalWithFee', includeExternalWithFee)
				.replace(':viewDate', viewDate)
				.replace(':checkUnpaid', checkUnpaid);

			window.location = downloadTransactionSummaryReportReportUrl;
		}

		function taxcollectionreportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			var downloadReportUrl = "{{ route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'tax_collection_report_export') }}";

			window.location = downloadReportUrl+'?currentDate='+fromDate+'&toDate='+toDate+'&tourOperatorId='+tourOperatorId+'&tourPackageId='+tourPackageId+'&csv='+csv_export;
		}
		function wirecollectionreportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			tourPackageId = tourPackageId ? tourPackageId : 0;
			var selectedReport = $("#select-report").val();
			var viewBy = $("#viewby-select").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadWireCollectionReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.wire-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':viewBy', ':export'])}}";
			downloadWireCollectionReportUrl = downloadWireCollectionReportUrl.replace(':fromDate', fromDate)
				.replace(':toDate', toDate)
				.replace(':selectedOperator', tourOperatorId)
				.replace(':selectedPackage', tourPackageId)
				.replace(':viewBy', viewBy)
				.replace(':export', 1);

			window.location = downloadWireCollectionReportUrl;
		}

		function failedPaymentReportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			var downloadFailedBookingReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'failed_payment_report_export')}}";

			window.location = downloadFailedBookingReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
		}

		function refundpaymentreportDownload() {
			var tourOperatorId = $('#tour_operator').val();
			var tourPackageId = $('#tour_package').val();
			tourPackageId = tourPackageId ? tourPackageId : 0;
			var selectedReport = $("#select-report").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;

			var downloadRefundCollectionReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.refund-payment-report', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':export'])}}";
			downloadRefundCollectionReportUrl = downloadRefundCollectionReportUrl.replace(':fromDate', fromDate)
				.replace(':toDate', toDate)
				.replace(':selectedOperator', tourOperatorId)
				.replace(':selectedPackage', tourPackageId)
				.replace(':export', 1);

			window.location = downloadRefundCollectionReportUrl;
		}

		function fareharborbookingreportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			var downloadFareHarborBookingReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'fareharborbooking_booking_report_export')}}";

			window.location = downloadFareHarborBookingReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
		}

		function affiliatereportDownload() {
			var selectedOperator = $('#tour_operator').val();
			var selectedPackage = $("#tour_package").val();
			var selectedReport = $("#select-report").val();
			var selectedCustomer = $("#customer-data").val();
			var dateRange = reportDateRange();
			var fromDate = dateRange.currentDate;
			var toDate = dateRange.toDate;
			var csv_export = 1;
			@if(session('userType') == 'Affiliate')
			var downloadAffiliateReportUrl = "{{route('affiliate.affiliate_report_export')}}";
			@else
			var downloadAffiliateReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_report_export')}}";
			@endif


	window.location = downloadAffiliateReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&customerId='+selectedCustomer+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
}

	function affiliateSeatsReportDownload() {
				var selectedOperator = $('#tour_operator').val();
				var selectedPackage = $("#tour_package").val();
				var selectedReport = $("#select-report").val();
				var customerId = $("#customer-data").val();
				var dateRange = reportDateRange();
				var dateFrom = dateRange.currentDate;
				var dateTo = dateRange.toDate;
				var csv_export = 1;
				@if(session('userType') == 'Affiliate')
				var downloadAffiliateSeatsReportUrl = "{{route('affiliate.affiliate_seats_report_export')}}";
				@else
				var downloadAffiliateSeatsReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_seats_report_export')}}";
				@endif


		window.location = downloadAffiliateSeatsReportUrl+'?dateFrom='+dateFrom+'&dateTo='+dateTo+'&customerId='+customerId+'&selectedPackage='+selectedPackage+'&selectedOperator='+selectedOperator+'&csv='+csv_export;
	}
function affiliatereportAllDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedPackage = $("#tour_package").val();
	var selectedReport = $("#select-report").val();
	var selectedCustomer = $("#customer-data").val();
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var downloadAffiliateReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_all_report_export')}}";
	window.location = downloadAffiliateReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&customerId='+selectedCustomer+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
}

function affiliatereportComprehensiveDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedPackage = $("#tour_package").val();
	var selectedReport = $("#select-report").val();
	var selectedCustomer = $("#customer-data").val();
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	@if(session('userType') == 'Affiliate')
	var downloadAffiliateReportUrl = "{{route('affiliate.affiliate_comprehensive_report_export')}}";
	@else
	var downloadAffiliateReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_comprehensive_report_export')}}";
	@endif

	window.location = downloadAffiliateReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&customerId='+selectedCustomer+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
}

function emailreportDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedPackage = $("#tour_package").val();
	var selectedReport = $("#select-report").val();
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var downloadEmailReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'email_report_export')}}";

	window.location = downloadEmailReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourOperatorId='+selectedOperator+'&tourPackageId='+selectedPackage+'&csv='+csv_export;
}

function getDetailsTodayAvailabilityDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedPackage = $("#tour_package").val();
	var selectedReport = $("#select-report").val();
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var downloadgetDetailsTodayAvailabilityUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'today_avialability_report_export')}}";

	window.location = downloadgetDetailsTodayAvailabilityUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourOperatorId='+selectedOperator+'&tourPackageId='+selectedPackage+'&csv='+csv_export;
}

function invalidBookingsReportDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedPackage = $("#tour_package").val();
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var downloadInvalidBookingReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'invalid_payment_report_export')}}";

	window.location = downloadInvalidBookingReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
}
function lowerAntelopeCanyonClosureDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedPackage = $("#tour_package").val();
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var downloadClosureReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'closure_report_export')}}";

	window.location = downloadClosureReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&tourPackageId='+selectedPackage+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
}

function affiliateSeatsReportDownload() {
				var selectedOperator = $('#tour_operator').val();
				var selectedPackage = $("#tour_package").val();
				var selectedReport = $("#select-report").val();
				var customerId = $("#customer-data").val();
				var dateRange = reportDateRange();
				var dateFrom = dateRange.currentDate;
				var dateTo = dateRange.toDate;
				var csv_export = 1;
				@if(session('userType') == 'Affiliate')
				var downloadAffiliateSeatsReportUrl = "{{route('affiliate.affiliate_seats_report_export')}}";
				@else
				var downloadAffiliateSeatsReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'affiliate_seats_report_export')}}";
				@endif


		window.location = downloadAffiliateSeatsReportUrl+'?dateFrom='+dateFrom+'&dateTo='+dateTo+'&customerId='+customerId+'&selectedPackage='+selectedPackage+'&selectedOperator='+selectedOperator+'&csv='+csv_export;


}

function addonsPackageReportDownload() {
	var tourOperatorId = $('#tour_operator').val();
	var tourPackageId = $('#tour_package').val();
	tourPackageId = tourPackageId ? tourPackageId : 0;
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var addOnName = $("#add-on-data").val();
	var downloadAddonsPackageReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.addons-package-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':export', ':addOnName'])}}";
	downloadAddonsPackageReportUrl = downloadAddonsPackageReportUrl.replace(':fromDate', fromDate)
	.replace(':toDate', toDate)
	.replace(':selectedOperator', tourOperatorId)
	.replace(':selectedPackage', tourPackageId)
	.replace(':export', 1)
	.replace(':addOnName', addOnName);
	window.location = downloadAddonsPackageReportUrl;
}

function addonsPackageDetailsReportDownload() {
	var tourOperatorId = $('#tour_operator').val();
	var tourPackageId = $('#tour_package').val();
	tourPackageId = tourPackageId ? tourPackageId : 0;
	var dateRange = reportDateRange();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var addOnName = $("#add-on-data").val();
	var downloadAddonsPackageDetailsReportUrl = "{{ route( ( $uTypeAdmin ? 'admin.' : '' ) . 'tour-operator.report.addons-package-details-export', [':fromDate', ':toDate', ':selectedOperator', ':selectedPackage', ':export', ':addOnName'])}}";
	downloadAddonsPackageDetailsReportUrl = downloadAddonsPackageDetailsReportUrl.replace(':fromDate', fromDate)
	.replace(':toDate', toDate)
	.replace(':selectedOperator', tourOperatorId)
	.replace(':selectedPackage', tourPackageId)
	.replace(':export', 1)
	.replace(':addOnName', addOnName);
	window.location = downloadAddonsPackageDetailsReportUrl;
}

function activityLogsReportDownload() {
	var selectedOperator = $('#tour_operator').val();
	var selectedReport = $("#select-report").val();
	var dateRange = reportDateRange();
	var eventType = $('#event_type :selected').val();
	var userName = $('#user_name :selected').val();
	var fromDate = dateRange.currentDate;
	var toDate = dateRange.toDate;
	var csv_export = 1;
	var activityLogsReportUrl = "{{route(($uTypeAdmin ? 'admin.' : 'tour-operator.') . 'activity_logs_report_export')}}";

	window.location = activityLogsReportUrl+'?fromDate='+fromDate+'&toDate='+toDate+'&eventType='+eventType+'&userName='+userName+'&tourOperatorId='+selectedOperator+'&csv='+csv_export;
}

function openInvoiceModal() {
	// Get current affiliate selection from dropdown
	var currentCustomerId = $("#customer-data").val();
	var affiliateName = $("#customer-data option:selected").text() || 'Unknown';

	// Validate: Must have a specific affiliate selected (not "All")
	if (!currentCustomerId || currentCustomerId == 0 || currentCustomerId === '0') {
		$('#invoice-modal-loading').addClass('hidden');
		$('#invoice-modal-form').addClass('hidden');
		$('#invoice-warning-message').text('Please select a specific affiliate to create an invoice. Invoices cannot be created for "All" affiliates.');
		$('#invoice-modal-warning').removeClass('hidden');
		$('#modal-invoice').modal('show');
		return;
	}

	// Reset modal state - show loading, hide others
	$('#invoice-modal-loading').removeClass('hidden');
	$('#invoice-modal-warning').addClass('hidden');
	$('#invoice-modal-form').addClass('hidden');

	// Show the modal
	$('#modal-invoice').modal('show');

	// Do preflight check to see if invoice already exists
	$.ajax({
		url: invoiceCheckExistsUrl,
		method: 'POST',
		data: {
			_token: '{{ csrf_token() }}',
			from_date: invoiceFromDate,
			to_date: invoiceToDate,
			customer_id: invoiceAffiliate
		},
		success: function(response) {
			$('#invoice-modal-loading').addClass('hidden');

			if (response.exists) {
				// Invoice already exists - show warning
				var warningMsg = response.message || 'An invoice already exists for this date range.';
				if (response.invoice) {
					warningMsg += ' (Status: ' + response.invoice.status + ', Amount: $' + parseFloat(response.invoice.amount).toFixed(2) + ')';
				}
				$('#invoice-warning-message').text(warningMsg);
				$('#invoice-modal-warning').removeClass('hidden');
			} else {
				// No existing invoice - show form
				$('#invoice-modal-form').removeClass('hidden');

				// Set up datepicker
				$("#invoice_due_date").datepicker({
					dateFormat: "yy-mm-dd",
					minDate: 0
				});

				// Populate info display
				$('#invoice_info_affiliate').text(affiliateName);
				$('#invoice_info_amount').text('$' + parseFloat(invoiceAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
				$('#invoice_info_from_date').text(moment(invoiceFromDate).format('MM/DD/YYYY'));
				$('#invoice_info_to_date').text(moment(invoiceToDate).format('MM/DD/YYYY'));

				// Populate form hidden fields
				$('#invoice_tour_operator').val(invoiceTourOperator);
				$('#invoice_package').val(invoicePackage);
				$("#invoice_affiliate").val(invoiceAffiliate);
				$("#invoice_from_date").val(invoiceFromDate);
				$("#invoice_to_date").val(invoiceToDate);
				$("#invoice_amount").val(invoiceAmount);
			}
		},
		error: function(xhr) {
			$('#invoice-modal-loading').addClass('hidden');
			// On error, just show the form anyway (backend will validate)
			$('#invoice-modal-form').removeClass('hidden');

			$("#invoice_due_date").datepicker({
				dateFormat: "yy-mm-dd",
				minDate: 0
			});

			$('#invoice_info_affiliate').text(affiliateName);
			$('#invoice_info_amount').text('$' + parseFloat(invoiceAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
			$('#invoice_info_from_date').text(moment(invoiceFromDate).format('MM/DD/YYYY'));
			$('#invoice_info_to_date').text(moment(invoiceToDate).format('MM/DD/YYYY'));

			$('#invoice_tour_operator').val(invoiceTourOperator);
			$('#invoice_package').val(invoicePackage);
			$("#invoice_affiliate").val(invoiceAffiliate);
			$("#invoice_from_date").val(invoiceFromDate);
			$("#invoice_to_date").val(invoiceToDate);
			$("#invoice_amount").val(invoiceAmount);
		}
	});
}

// Affiliate Invoice Report Tab Functions
var affiliateInvoicesLoaded = false;
var affiliateDueAmountLoaded = false;

function switchAffiliateTab(tabName) {
	// Update tab button styles
	$('.affiliate-tab-btn').removeClass('border-[#264F73] text-[#264F73]').addClass('border-transparent text-gray-500');
	$('.affiliate-tab-btn[data-tab="' + tabName + '"]').removeClass('border-transparent text-gray-500').addClass('border-[#264F73] text-[#264F73]');

	// Hide all tab contents
	$('.affiliate-tab-content').addClass('hidden');

	// Show selected tab content
	$('#affiliateTab-' + tabName).removeClass('hidden');

	// Load data if not already loaded
	if (tabName === 'invoices' && !affiliateInvoicesLoaded) {
		loadAffiliateInvoices();
	} else if (tabName === 'dueAmount' && !affiliateDueAmountLoaded) {
		loadAffiliateDueAmount();
	}
}

function loadAffiliateInvoices() {
	var selectedOperator = $("#tour_operator").val();
	var customerId = $("#customer-data").val();
	var dateRange = reportDateRange();

	@if(session('userType') == 'Tour Operator')
		selectedOperator = "{{auth()->user()->tour_operator_id}}";
	@endif

	var url = invoiceListUrl + '/' + (customerId || 0) + '/' + (selectedOperator || 0) + '?from_date=' + dateRange.currentDate + '&to_date=' + dateRange.toDate;

	$('#invoicesLoading').removeClass('hidden');
	$('#invoicesTableContainer').addClass('hidden');

	$.get(url)
		.done(function(response) {
			affiliateInvoicesLoaded = true;
			$('#invoicesLoading').addClass('hidden');
			$('#invoicesTableContainer').removeClass('hidden');

			var data = response.results || [];
			var html = '';

			if (data.length === 0) {
				html = '<div class="text-center py-8 text-gray-500">No invoices found for this date range.</div>';
			} else {
				html = '<div class="w-full overflow-x-auto"><table class="table table-bordered table-striped table-hover w-full border-collapse text-left">';
				html += '<thead><tr>';
				html += '<th class="th-custom-css">Invoice #</th>';
				html += '<th class="th-custom-css">Affiliate</th>';
				html += '<th class="th-custom-css">From Date</th>';
				html += '<th class="th-custom-css">To Date</th>';
				html += '<th class="th-custom-css">Due Date</th>';
				html += '<th class="th-custom-css">Amount</th>';
				html += '<th class="th-custom-css">Status</th>';
				html += '<th class="th-custom-css">Action</th>';
				html += '</tr></thead><tbody>';

				data.forEach(function(invoice) {
					var statusClass = getInvoiceStatusClass(invoice.status);
					html += '<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out">';
					html += '<td class="td-custom-css">' + (invoice.invoice_number || '-') + '</td>';
					html += '<td class="td-custom-css">' + (invoice.customer_name || '-') + '</td>';
					html += '<td class="td-custom-css">' + (invoice.from_date ? moment(invoice.from_date).format('MM/DD/YYYY') : '-') + '</td>';
					html += '<td class="td-custom-css">' + (invoice.to_date ? moment(invoice.to_date).format('MM/DD/YYYY') : '-') + '</td>';
					html += '<td class="td-custom-css">' + (invoice.due_date ? moment(invoice.due_date).format('MM/DD/YYYY') : '-') + '</td>';
					html += '<td class="td-custom-css">' + currency(parseFloat(invoice.amount || 0).toFixed(2)).format() + '</td>';
					html += '<td class="td-custom-css"><span class="px-2 py-1 rounded text-xs font-medium ' + statusClass + '">' + (invoice.status || '-') + '</span></td>';
					html += '<td class="td-custom-css"><a href="' + invoiceShowUrl.replace('_UUID_', invoice.uuid) + '" target="_blank" class="text-blue-600 hover:underline">View</a></td>';
					html += '</tr>';
				});

				html += '</tbody></table></div>';
			}

			$('#invoicesTableContainer').html(html);
		})
		.fail(function() {
			$('#invoicesLoading').addClass('hidden');
			$('#invoicesTableContainer').removeClass('hidden').html('<div class="text-center py-8 text-red-500">Failed to load invoices.</div>');
		});
}

function loadAffiliateDueAmount() {
	$('#dueAmountLoading').removeClass('hidden');
	$('#dueAmountTableContainer').addClass('hidden');
	var dateRange = reportDateRange();
	var selectedPackage = $("#tour_package").val();
	var customerId = $("#customer-data").val();
							
	$.post(affiliateDueAmountUrl, { _token: '{{ csrf_token() }}',fromDate: dateRange.currentDate, toDate: dateRange.toDate, packageId: selectedPackage, customerId: customerId })
		.done(function(response) {
			affiliateDueAmountLoaded = true;
			$('#dueAmountLoading').addClass('hidden');
			$('#dueAmountTableContainer').removeClass('hidden');

			var data = response.data || [];
			var html = '';

			if (data.length === 0) {
				html = '<div class="text-center py-8 text-gray-500">No affiliate amounts owed.</div>';
			} else {
				html = '<div class="w-full overflow-x-auto"><table class="table table-bordered table-striped table-hover w-full border-collapse text-left">';
				html += '<thead><tr>';
				html += '<th class="th-custom-css">Affiliate</th>';
				html += '<th class="th-custom-css">Tourists</th>';
				html += '<th class="th-custom-css">Amount Owed</th>';
				html += '</tr></thead><tbody>';

				var totalAmount = 0;
				var totalTourists = 0;

				data.forEach(function(row) {
					var amount = parseFloat(row.amount || 0);
					var tourists = parseInt(row.tourists || 0);
					totalAmount += amount;
					totalTourists += tourists;

					html += '<tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out">';
					html += '<td class="td-custom-css">' + (row.name || '-') + '</td>';
					html += '<td class="td-custom-css">' + tourists.toLocaleString('en-US') + '</td>';
					html += '<td class="td-custom-css alert-text-color fw-normal">' + currency(amount.toFixed(2)).format() + '</td>';
					html += '</tr>';
				});

				html += '<tr class="bg-gray-100 font-semibold">';
				html += '<td class="td-custom-css">Total</td>';
				html += '<td class="td-custom-css">' + totalTourists.toLocaleString('en-US') + '</td>';
				html += '<td class="td-custom-css alert-text-color">' + currency(totalAmount.toFixed(2)).format() + '</td>';
				html += '</tr>';

				html += '</tbody></table></div>';
			}

			$('#dueAmountTableContainer').html(html);
		})
		.fail(function() {
			$('#dueAmountLoading').addClass('hidden');
			$('#dueAmountTableContainer').removeClass('hidden').html('<div class="text-center py-8 text-red-500">Failed to load affiliate amounts.</div>');
		});
}

function getInvoiceStatusClass(status) {
	switch(status) {
		case 'draft': return 'bg-gray-100 text-gray-800';
		case 'sent': return 'bg-blue-100 text-blue-800';
		case 'viewed': return 'bg-indigo-100 text-indigo-800';
		case 'approved': return 'bg-green-100 text-green-800';
		case 'partial': return 'bg-yellow-100 text-yellow-800';
		case 'paid': return 'bg-emerald-100 text-emerald-800';
		case 'overdue': return 'bg-red-100 text-red-800';
		case 'unpaid': return 'bg-orange-100 text-orange-800';
		case 'cancelled': return 'bg-gray-100 text-gray-500 line-through';
		default: return 'bg-gray-100 text-gray-800';
	}
}

function resetAffiliateInvoiceTabs() {
	affiliateInvoicesLoaded = false;
	affiliateDueAmountLoaded = false;
	$('#invoicesTableContainer').empty();
	$('#dueAmountTableContainer').empty();
}

function printThis() {
	$('#summaryreport').printThis();
}


function lowerAntelopeCanyonClosure(arg) {
	swal({
		title: 'Lower Antelope Canyon Closure',
		text: 'Are you sure you want to send email to tourist about a closure for this tour?',
		icon: "warning",
		buttons: {
			no: {
				text: 'No',
				value: false,
				className: 'btn-danger',
			},
			yes: {
				text: 'Yes',
				className: 'btn-success',
			}
		}
	})
	.then(function(result) {
		if(!result)
			return;

		var date = $(arg).attr('data-date');
		var slotId = $(arg).attr('data-slot-id');
		var bookingId = $(arg).attr('data-booking-id');
		var tourOpId = $(arg).attr('data-tour-operator-id');
		showLoading();

		$.post(lowerAntelopeCanyonClosureUrl, {
			date: date,
			slot_id: slotId,
			booking_id: bookingId,
			tour_operator_id: tourOpId
		})
		.done(function(response) {
			console.log(response);
			swal('A closure email was sent.', '', 'success');
		})
		.fail(showSubmissionError)
		.always(hideLoading);
	})
}
</script>

@if($uTypeAdmin)
<script src="{{ asset('js/admin/tour-booking-add-edit.js') }}"></script>
@endif

<script src="{{ asset('js/user-common/report.js') }}"></script>
@endsection
