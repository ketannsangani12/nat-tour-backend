{{-- Payment Breakdown Report - Main View --}}
{{-- This is the entry point for the Payment Breakdown Report --}}
{{-- Modular structure: Each report has its own folder with separate files for content, styles, scripts, and modals --}}

<div id="paymentBreakdownReportContent" class="w-full">
    {{-- Include the HTML structure --}}
    @include('partials.reports.payment-breakdown.content')
</div>

{{-- Include CSS --}}
@include('partials.reports.payment-breakdown.styles')
