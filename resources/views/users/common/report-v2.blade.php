@extends('layouts.default-2', ['wrapclass' => 'container-fluid'])
@php
	$uTypeAdmin = session('userType') == 'Admin';
    $uTypeAffiliate = session('userType') == 'Affiliate';
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
		<div class="col-md-3 user-details">
			<h4 id="reportHeadingPrint" style="display: none">Payment Breakdown Report</h4>
		</div>

		@include("/users/common/partials/reports/_report_select", [
			"reports" => $reports ?? [],
			"staicReportList" => $staicReportList ?? [],
			"tourOperators" => $tourOperators ?? [],
			"tourPackages" => $tourPackages ?? [],
			"uTypeAdmin" => $uTypeAdmin ?? false,
			"uTypeAffiliate" => $uTypeAffiliate ?? false,
			"adminStaicReportList" => $adminStaicReportList ?? [],
			"tourOperatorId" => $tourOperatorId ?? 1
		])
	</div>
</section>

<!-- Payment Breakdown Report Section (Modular Structure) -->
<section class="booking-action-sec overflow-y-auto bg-[#F9FAFB] py-6" id="paymentBreakdownReportSection">
	<div class="w-full container">
		@include('partials.reports.payment-breakdown.index')
	</div>
</section>
<!-- End Payment Breakdown Report -->

<!-- Export Modal - MUST be outside the overflow section -->
@include('partials.reports.payment-breakdown.export-modal')

@endsection

@section('css-libraries')
	<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/datatables-combo/datatables.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('plugins/daterange/daterangepicker.2.1.25.css') }}" rel="stylesheet" type="text/css" />
	<link href="{{ asset('css/forms/multi-dropdown.css') }}" rel="stylesheet" type="text/css" />
@endsection

@section('js-libraries')
	<script src="{{ asset('plugins/jquery-3.5.1.min.js') }}"></script>
	<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
	<script src="{{ asset('plugins/datatables-combo/datatables.js') }}"></script>
	<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/daterange/daterangepicker.2.1.25.js') }}"></script>
	<script src="{{ asset('plugins/chart.js-2.9.4/Chart.min.js') }}"></script>
	<script src="{{ asset('plugins/sweetalert-2.1.2.min.js') }}"></script>
@endsection

@section('js-custom')
<script>
// ============================================
// PAYMENT BREAKDOWN REPORT V2 - MAIN SCRIPT
// ============================================

// Pass PHP data to JavaScript
window.pbTourPackages = @json($tourPackages ?? []);
window.pbAffiliates = @json($affiliates ?? []);
window.pbEnabledThirdPartyPlatforms = @json($enabledThirdPartyPlatforms);
window.pbTourOperatorId = {{ $tourOperatorId ?? 0 }};
window.pbTourOperatorTimezone = "{{ $tourOperatorTimezone ?? config('app.timezone') }}";

// Global variables
window.pbInitialized = false;

// Initialize Payment Breakdown Report on page load
$(document).ready(function() {
	// Initialize the report
	if (!window.pbInitialized) {
		initializePaymentBreakdownReport();
		window.pbInitialized = true;
	}
	
	// Listen for report selection changes (redirect to V1 for other reports)
	$('#select-report').on('change', function() {
		const selectedReport = $(this).val();
		
		if (selectedReport !== "Payment Breakdown Report") {
			// Redirect to V1 for other reports
			const routePrefix = '{{ routePrefixForJs() }}';
			const form = $('<form>', {
				'method': 'POST',
				'action': `/${routePrefix}/report/set-selected-report`
			});
			
			form.append($('<input>', {
				'type': 'hidden',
				'name': '_token',
				'value': '{{ csrf_token() }}'
			}));
			
			form.append($('<input>', {
				'type': 'hidden',
				'name': 'report',
				'value': selectedReport
			}));
			
			$('body').append(form);
			form.submit();
		}
	});
});
</script>

{{-- Include Payment Breakdown Report JavaScript Functions --}}
@include('partials.reports.payment-breakdown.scripts')
@endsection
