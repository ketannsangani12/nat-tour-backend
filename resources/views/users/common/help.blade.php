@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
@endphp


@section('main-content')
	@if($errors->any())
		@include('layouts._errors')
	@endif

	@if(session('message'))
		@include('layouts._message')
	@endif
  <!-- Global site tag (gtag.js) - Google Analytics --><script async src="https://www.googletagmanager.com/gtag/js?id=UA-223629951-1"></script><script>  window.dataLayer = window.dataLayer || [];  function gtag(){dataLayer.push(arguments);}  gtag('js', new Date());   gtag('config', 'UA-223629951-1');</script>

<!-- Google Tag Manager --><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','GTM-W4CHJCP');</script><!-- End Google Tag Manager -->

<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="on3TqImFNqXOb2nUCKN9xnDCRzDrsc32aVBFEWM4" />
<title>Admin Log-in | Native American Tours</title>
<link href="https://staging.nativeamericantours.com/images/favicon.png" rel="icon">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<link href="{{ asset('css/app.css') }}" rel="stylesheet">
<style>
  .traning-video-list-sec .nav-link.active {
    background-color: #60BEBD;
    color: #fff;
  }
  
  @media (max-width: 1023px) {
    .accordion-button {
      background-color: #60BEBD;
      color: #fff;
      margin-bottom:8px;
    }
    .accordion-button.collapsed {
      background-color: #F3F4F6;
      color: #64748B;
      margin-bottom:0;
    }
    .tab-content>.tab-pane {
        display: block;
        opacity: 1;
    }
  }
</style>
  <body class="font-inter bg-[#F9FAFB]">
  <!-- Google Tag Manager (noscript) --><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-W4CHJCP"height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript><!-- End Google Tag Manager (noscript) -->

  <section class="traning-video-list-sec py-10 lg:py-14 bg-[#F9FAFB]">
    <div class="container">
      <div class="flex flex-wrap -mx-3">
        <div class="w-full max-w-full px-3">
          <div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg overflow-y-auto">
            <div class="traning-video-list-wrp flex flex-wrap -mx-3">
              <div class="w-full lg:w-1/4 max-w-full px-3 max-[1023px]:hidden">
                <div class="traning-video-list-nav ">
                  <div class="mb-2">
                    <input id="searchhelp" placeholder="Search" class="form-control mt-1 text-sm text-[#7C706A] border border-[#E5E7EB] py-[9px] pl-10 pr-3 rounded-lg w-full outline-none">
                  </div>
                  <div class="nav flex-column nav-pills h-[calc(100vh-200px)] overflow-y-auto">
                    @if(session('userType') != 'Affiliate')
                      <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-0">General Overview</div>
                      <button class="nav-link active block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium" id="v-pills-calendar-view-tab" data-bs-toggle="pill" data-bs-target="#v-pills-calendar-view" type="button" role="tab" aria-controls="v-pills-calendar-view" aria-selected="true">Calendar View</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-agenda-view-tab" data-bs-toggle="pill" data-bs-target="#v-pills-agenda-view" type="button" role="tab" aria-controls="v-pills-agenda-view" aria-selected="false">Agenda View</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-reports-view-tab" data-bs-toggle="pill" data-bs-target="#v-pills-reports-view" type="button" role="tab" aria-controls="v-pills-reports-view" aria-selected="false">Reports View</button>

                      <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4">Booking Detail</div>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-booking-details-tab" data-bs-toggle="pill" data-bs-target="#v-pills-booking-details" type="button" role="tab" aria-controls="v-pills-booking-details" aria-selected="false">Booking Details</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-check-in-a-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-check-in-a-booking" type="button" role="tab" aria-controls="v-pills-how-to-check-in-a-booking" aria-selected="false">How to Check-In a Booking</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-reschedule-a-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-reschedule-a-booking" type="button" role="tab" aria-controls="v-pills-how-reschedule-a-booking" aria-selected="false">How Reschedule a Booking</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-rebook-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-rebook" type="button" role="tab" aria-controls="v-pills-how-to-rebook" aria-selected="false">How to Rebook</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-delete-passengers-from-a-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-delete-passengers-from-a-booking" type="button" role="tab" aria-controls="v-pills-how-to-delete-passengers-from-a-booking" aria-selected="false">How to Delete Passengers From a Booking</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-permit-fee-tab" data-bs-toggle="pill" data-bs-target="#v-pills-permit-fee" type="button" role="tab" aria-controls="v-pills-permit-fee" aria-selected="false">Permit Fee</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-change-guest-category-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-change-guest-category" type="button" role="tab" aria-controls="v-pills-how-to-change-guest-category" aria-selected="false">How to Change Guest Category</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-add-people-to-a-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-add-people-to-a-booking" type="button" role="tab" aria-controls="v-pills-how-to-add-people-to-a-booking" aria-selected="false">How to Add People to a Booking</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-tip-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-tip" type="button" role="tab" aria-controls="v-pills-how-to-tip" aria-selected="false">How to Tip</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-waiver-tab" data-bs-toggle="pill" data-bs-target="#v-pills-waiver" type="button" role="tab" aria-controls="v-pills-waiver" aria-selected="false">Waiver</button>

                      <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4">Make a Booking</div>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-make-a-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-make-a-booking" type="button" role="tab" aria-controls="v-pills-how-to-make-a-booking" aria-selected="false">How to Make a Booking</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-make-a-cash-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-make-a-cash-booking" type="button" role="tab" aria-controls="v-pills-how-to-make-a-cash-booking" aria-selected="false">How to Make a Cash Booking</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-pay-later-invoice-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-pay-later-invoice" type="button" role="tab" aria-controls="v-pills-how-to-pay-later-invoice" aria-selected="false">How to Pay Later/Invoice</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-make-a-split-payment-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-make-a-split-payment" type="button" role="tab" aria-controls="v-pills-how-to-make-a-split-payment" aria-selected="false">How to Make a Split Payment</button>

                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-make-a-split-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-make-a-split-booking" type="button" role="tab" aria-controls="v-pills-how-to-make-a-split-booking" aria-selected="false">How to Make a Split Booking</button>

                      <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4">Cancel/Refund</div>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-cancel-and-refund-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-cancel-and-refund" type="button" role="tab" aria-controls="v-pills-how-to-cancel-and-refund" aria-selected="false">How to Cancel and Refund</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-refund-from-multiple-accounts-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-refund-from-multiple-accounts" type="button" role="tab" aria-controls="v-pills-how-to-refund-from-multiple-accounts" aria-selected="false">How to Refund from Multiple Accounts</button>

                      <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4">Setting Menu</div>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-close-tour-times-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-close-tour-times" type="button" role="tab" aria-controls="v-pills-how-to-close-tour-times" aria-selected="false">How to Close Tour Times</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-add-staff-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-add-staff" type="button" role="tab" aria-controls="v-pills-how-to-add-staff" aria-selected="false">How to Add Staff</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-create-a-invoice-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-create-a-invoice" type="button" role="tab" aria-controls="v-pills-how-to-create-a-invoice" aria-selected="false">How to Create a Invoice</button>
                      <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4">Manager Training</div>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-update-staff-role-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-update-staff-role" type="button" role="tab" aria-controls="v-pills-how-to-update-staff-role" aria-selected="false">How to Update Staff Role</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-add-or-modify-tour-slots-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-add-or-modify-tour-slots" type="button" role="tab" aria-controls="v-pills-how-to-add-or-modify-tour-slots" aria-selected="false">How to Add or Modify Tour Slots</button>
                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-send-an-email-closure-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-send-an-email-closure" type="button" role="tab" aria-controls="v-pills-how-to-send-an-email-closure" aria-selected="false">How to Send an Email Closure</button>

                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-affiliate-invoice-insights-report-tab" data-bs-toggle="pill" data-bs-target="#v-pills-affiliate-invoice-insights-report" type="button" role="tab" aria-controls="v-pills-affiliate-invoice-insights-report" aria-selected="false">Affiliate Invoice Insights Report</button>

                      <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-affiliate-invoice-insights-report-multi-tab" data-bs-toggle="pill" data-bs-target="#v-pills-affiliate-invoice-insights-report-multi" type="button" role="tab" aria-controls="v-pills-affiliate-invoice-insights-report-multi" aria-selected="false">Affiliate Invoice Insights Report/Multi-Invoice</button>
                    @endif
                    <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4">Affiliates</div>
                    <button class="nav-link {{ session('userType') == 'Affiliate' ? 'active' : ''  }} block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium" id="v-pills-how-to-book-an-affiliate-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-book-an-affiliate" type="button" role="tab" aria-controls="v-pills-how-to-book-an-affiliate" aria-selected="{{ session('userType') == 'Affiliate' ? 'true' : 'false' }}">How to Book an Affiliate</button>
                    <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-how-to-remove-guests-from-affiliate-bookings-tab" data-bs-toggle="pill" data-bs-target="#v-pills-how-to-remove-guests-from-affiliate-bookings" type="button" role="tab" aria-controls="v-pills-how-to-remove-guests-from-affiliate-bookings" aria-selected="false">How to Remove Guests from Affiliate Bookings</button>
                    <button class="nav-link block w-full text-left bg-[#F3F4F6] text-[#64748B] px-3 py-2 mb-1 rounded-md text-sm font-medium border border-transparent hover:bg-white hover:text-[#60BEBD] hover:border-[#60BEBD] transition-all duration-300 ease-in-out" id="v-pills-affiliate-reference-booking-tab" data-bs-toggle="pill" data-bs-target="#v-pills-affiliate-reference-booking" type="button" role="tab" aria-controls="v-pills-affiliate-reference-booking" aria-selected="false">Affiliate Reference Booking</button>
                  </div>
                </div>
              </div>
              <div class="tab-content accordion w-full lg:w-3/4 max-w-full px-3" id="v-pills-tabContent">
                <!-- General Overview Start -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 mt-0 lg:hidden">General Overview</div>
                <div class="tab-pane fade  {{ session('userType') != 'Affiliate' ? 'show active' : ''  }} accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-calendar-view" role="tabpanel" aria-labelledby="v-pills-calendar-view-tab">
                  <div class="accordion-header lg:hidden" id="calendar-view-accordion">
                    <button id="calendar-view-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium" type="button" data-bs-toggle="collapse" data-bs-target="#calendar-view" aria-expanded="true" aria-controls="calendar-view">Calendar View</button>
                  </div>

                  <div id="calendar-view" class="accordion-collapse collapse p-4 show min-[1024px]:block min-[1024px]:visible" aria-labelledby="calendar-view-accordion" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+1.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="calendar-view-contant" class="iframe-detail-wrap">
                      <div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%">
                        <iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+1.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Calendar View | Native American Tours"></iframe>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-agenda-view" role="tabpanel" aria-labelledby="v-pills-agenda-view-tab">
                  <div class="accordion-header lg:hidden" id="agenda-view-acc">
                    <button id="agenda-view-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#agenda-view" aria-expanded="false" aria-controls="agenda-view">Agenda View</button>
                  </div>

                  <div id="agenda-view" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="agenda-view-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+2.mp4/" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="agenda-view-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-reports-view" role="tabpanel" aria-labelledby="v-pills-reports-view-tab">
                  <div class="accordion-header lg:hidden" id="reports-view-acc">
                    <button id="reports-view-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#reports-view" aria-expanded="false" aria-controls="reports-view">Reports View</button>
                  </div>

                  <div id="reports-view" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="reports-view-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+3.mp4/" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="reports-view-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- General Overview End -->

                <!-- Booking Detail Start -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 lg:hidden">Booking Detail</div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-booking-details" role="tabpanel" aria-labelledby="v-pills-booking-details-tab">
                  <div class="accordion-header lg:hidden" id="booking-details-acc">
                    <button id="booking-details-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#booking-details" aria-expanded="false" aria-controls="booking-details">Booking Details</button>
                  </div>

                  <div id="booking-details" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="booking-details-acc" data-bs-parent="#v-pills-tabContent">
                  <div class="d-flex shareButtonDiv">
                    <div id="social-links">
                      <ul>
                        <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+4.mp4/" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                      </ul>
                    </div>
                  </div>
                    <div id="booking-details-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-check-in-a-booking" role="tabpanel" aria-labelledby="v-pills-how-to-check-in-a-booking-tab">
                  <div class="accordion-header lg:hidden" id="how-to-check-in-a-booking-acc">
                    <button id="how-to-check-in-a-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-check-in-a-booking" aria-expanded="false" aria-controls="how-to-check-in-a-booking">How to Check-In a Booking</button>
                  </div>

                  <div id="how-to-check-in-a-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-check-in-a-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+5.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-check-in-a-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-reschedule-a-booking" role="tabpanel" aria-labelledby="v-pills-how-reschedule-a-booking-tab">
                  <div class="accordion-header lg:hidden" id="how-reschedule-a-booking-acc">
                    <button id="how-reschedule-a-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-reschedule-a-booking" aria-expanded="false" aria-controls="how-reschedule-a-booking">How Reschedule a Booking</button>
                  </div>

                  <div id="how-reschedule-a-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-reschedule-a-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+6.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-reschedule-a-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-rebook" role="tabpanel" aria-labelledby="v-pills-how-to-rebook-tab">
                  <div class="accordion-header lg:hidden" id="how-to-rebook-acc">
                    <button id="how-to-rebook-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-rebook" aria-expanded="false" aria-controls="how-to-rebook">How to Rebook</button>
                  </div>

                  <div id="how-to-rebook" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-rebook-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+7.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-rebook-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-delete-passengers-from-a-booking" role="tabpanel" aria-labelledby="v-pills-how-to-delete-passengers-from-a-booking-tab">
                  <div class="accordion-header lg:hidden" id="how-to-delete-passengers-from-a-booking-acc">
                    <button id="how-to-delete-passengers-from-a-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-delete-passengers-from-a-booking" aria-expanded="false" aria-controls="how-to-delete-passengers-from-a-booking">How to Delete Passengers From a Booking</button>
                  </div>

                  <div id="how-to-delete-passengers-from-a-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-delete-passengers-from-a-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+8.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-delete-passengers-from-a-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-permit-fee" role="tabpanel" aria-labelledby="v-pills-permit-fee-tab">
                  <div class="accordion-header lg:hidden" id="permit-fee-acc">
                    <button id="permit-fee-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#permit-fee" aria-expanded="false" aria-controls="permit-fee">Permit Fee</button>
                  </div>

                  <div id="permit-fee" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="permit-fee-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+9.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="permit-fee-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-change-guest-category" role="tabpanel" aria-labelledby="v-pills-how-to-change-guest-category-tab">
                  <div class="accordion-header lg:hidden" id="how-to-change-guest-category-acc">
                    <button id="how-to-change-guest-category-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-change-guest-category" aria-expanded="false" aria-controls="how-to-change-guest-category">How to Change Guest Category</button>
                  </div>

                  <div id="how-to-change-guest-category" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-change-guest-category-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+10.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-change-guest-category-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-add-people-to-a-booking" role="tabpanel" aria-labelledby="v-pills-how-to-add-people-to-a-booking-tab">
                  <div class="accordion-header lg:hidden" id="how-to-add-people-to-a-booking-acc">
                    <button id="how-to-add-people-to-a-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-add-people-to-a-booking" aria-expanded="false" aria-controls="how-to-add-people-to-a-booking">How to Add People to a Booking</button>
                  </div>

                  <div id="how-to-add-people-to-a-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-add-people-to-a-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+11.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-add-people-to-a-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-tip" role="tabpanel" aria-labelledby="v-pills-how-to-tip-tab">
                  <div class="accordion-header lg:hidden" id="how-to-tip-acc">
                    <button id="how-to-tip-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-tip" aria-expanded="false" aria-controls="how-to-tip">How to Tip</button>
                  </div>

                  <div id="how-to-tip" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-tip-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+12.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-tip-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-waiver" role="tabpanel" aria-labelledby="v-pills-waiver-tab">
                  <div class="accordion-header lg:hidden" id="waiver-acc">
                    <button id="waiver-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#waiver" aria-expanded="false" aria-controls="waiver">Waiver</button>
                  </div>

                  <div id="waiver" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="waiver-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+13.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="waiver-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- Booking Detail End -->

                <!-- Make a Booking Start -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 lg:hidden">Make a Booking</div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-make-a-booking" role="tabpanel" aria-labelledby="v-pills-how-to-make-a-booking-tab">
                  <div class="accordion-header lg:hidden" id="how-to-make-a-booking-acc">
                    <button id="how-to-make-a-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-make-a-booking" aria-expanded="false" aria-controls="how-to-make-a-booking">How to Make a Booking</button>
                  </div>

                  <div id="how-to-make-a-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-make-a-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+24.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-make-a-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-make-a-cash-booking" role="tabpanel" aria-labelledby="v-pills-how-to-make-a-cash-booking-tab">
                  <div class="accordion-header lg:hidden" id="how-to-make-a-cash-booking-acc">
                    <button id="how-to-make-a-cash-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-make-a-cash-booking" aria-expanded="false" aria-controls="how-to-make-a-cash-booking">How to Make a Cash Booking</button>
                  </div>

                  <div id="how-to-make-a-cash-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-make-a-cash-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+25.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-make-a-cash-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-pay-later-invoice" role="tabpanel" aria-labelledby="v-pills-how-to-pay-later-invoice-tab">
                  <div class="accordion-header lg:hidden" id="how-to-pay-later-invoice-acc">
                    <button id="how-to-pay-later-invoice-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-pay-later-invoice" aria-expanded="false" aria-controls="how-to-pay-later-invoice">How to Pay Later/Invoice</button>
                  </div>

                  <div id="how-to-pay-later-invoice" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-pay-later-invoice-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+26.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-pay-later-invoice-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-make-a-split-payment" role="tabpanel" aria-labelledby="v-pills-how-to-make-a-split-payment-tab">
                  <div class="accordion-header lg:hidden" id="how-to-make-a-split-payment-acc">
                    <button id="how-to-make-a-split-payment-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-make-a-split-payment" aria-expanded="false" aria-controls="how-to-make-a-split-payment">How to Make a Split Payment</button>
                  </div>

                  <div id="how-to-make-a-split-payment" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-make-a-split-payment-acc" data-bs-parent="#v-pills-tabContent">
                      <div class="d-flex shareButtonDiv">
                        <div id="social-links">
                          <ul>
                            <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+27.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                          </ul>
                        </div>
                      </div>
                    <div id="how-to-make-a-split-payment-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- Make a Booking End -->

                <!-- Make a Split Booking Start -->
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-make-a-split-booking" role="tabpanel" aria-labelledby="v-pills-how-to-make-a-split-booking-tab">
                    <div class="accordion-header lg:hidden" id="how-to-make-a-split-booking-acc">
                      <button id="how-to-make-a-split-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-make-a-split-booking" aria-expanded="false" aria-controls="how-to-make-a-split-booking">How to Make a Split Booking</button>
                    </div>

                    <div id="how-to-make-a-split-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-make-a-split-booking-acc" data-bs-parent="#v-pills-tabContent">
                      <div class="d-flex shareButtonDiv">
                          <div id="social-links">
                          <ul>
                              <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+28.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                          </ul>
                          </div>
                      </div>
                      <div id="how-to-make-a-split-booking-contant" class="iframe-detail-wrap"></div>
                    </div>
                </div>
                <!-- Make a Split Booking End -->
                <!-- Make a Booking End -->

                <!-- Cancel/Refund Start -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 lg:hidden">Cancel/Refund</div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-cancel-and-refund" role="tabpanel" aria-labelledby="v-pills-how-to-cancel-and-refund-tab">
                  <div class="accordion-header lg:hidden" id="how-to-cancel-and-refund-acc">
                    <button id="how-to-cancel-and-refund-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-cancel-and-refund" aria-expanded="false" aria-controls="how-to-cancel-and-refund">How to Cancel and Refund</button>
                  </div>

                  <div id="how-to-cancel-and-refund" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-cancel-and-refund-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+14.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-cancel-and-refund-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-refund-from-multiple-accounts" role="tabpanel" aria-labelledby="v-pills-how-to-refund-from-multiple-accounts-tab">
                  <div class="accordion-header lg:hidden" id="how-to-refund-from-multiple-accounts-acc">
                    <button id="how-to-refund-from-multiple-accounts-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-refund-from-multiple-accounts" aria-expanded="false" aria-controls="how-to-refund-from-multiple-accounts">How to Refund from Multiple Accounts</button>
                  </div>

                  <div id="how-to-refund-from-multiple-accounts" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-refund-from-multiple-accounts-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                      <ul>
                        <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+29.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                      </ul>
                      </div>
                    </div>
                    <div id="how-to-refund-from-multiple-accounts-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- Cancel/Refund End -->

                <!-- Setting Menu Start -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 lg:hidden">Setting Menu</div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-close-tour-times" role="tabpanel" aria-labelledby="v-pills-how-to-close-tour-times-tab">
                  <div class="accordion-header lg:hidden" id="how-to-close-tour-times-acc">
                    <button id="how-to-close-tour-times-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-close-tour-times" aria-expanded="false" aria-controls="how-to-close-tour-times">How to Close Tour Times</button>
                  </div>

                  <div id="how-to-close-tour-times" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-close-tour-times-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                      <ul>
                        <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+15.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                      </ul>
                      </div>
                    </div>
                    <div id="how-to-close-tour-times-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-add-staff" role="tabpanel" aria-labelledby="v-pills-how-to-add-staff-tab">
                  <div class="accordion-header lg:hidden" id="how-to-add-staff-acc">
                    <button id="how-to-add-staff-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-add-staff" aria-expanded="false" aria-controls="how-to-add-staff">How to Add Staff</button>
                  </div>

                  <div id="how-to-add-staff" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-add-staff-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+17.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-add-staff-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-create-a-invoice" role="tabpanel" aria-labelledby="v-pills-how-to-create-a-invoice-tab">
                  <div class="accordion-header lg:hidden" id="how-to-create-a-invoice-acc">
                    <button id="how-to-create-a-invoice-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-create-a-invoice" aria-expanded="false" aria-controls="how-to-create-a-invoice">How to Create a Invoice</button>
                  </div>

                  <div id="how-to-create-a-invoice" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-create-a-invoice-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+16.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-create-a-invoice-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- Setting Menu End -->

                <!-- Manager Training -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 lg:hidden">Manager Training</div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-update-staff-role" role="tabpanel" aria-labelledby="v-pills-how-to-update-staff-role-tab">
                  <div class="accordion-header lg:hidden" id="how-to-update-staff-role-acc">
                    <button id="how-to-update-staff-role-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-update-staff-role" aria-expanded="false" aria-controls="how-to-update-staff-role">How to Update Staff Role</button>
                  </div>

                  <div id="how-to-update-staff-role" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-update-staff-role-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+18.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-update-staff-role-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-add-or-modify-tour-slots" role="tabpanel" aria-labelledby="v-pills-how-to-add-or-modify-tour-slots-tab">
                  <div class="accordion-header lg:hidden" id="how-to-add-or-modify-tour-slots-acc">
                    <button id="how-to-add-or-modify-tour-slots-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-add-or-modify-tour-slots" aria-expanded="false" aria-controls="how-to-add-or-modify-tour-slots">How to Add or Modify Tour Slots</button>
                  </div>

                  <div id="how-to-add-or-modify-tour-slots" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-add-or-modify-tour-slots-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+19.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-add-or-modify-tour-slots-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-send-an-email-closure" role="tabpanel" aria-labelledby="v-pills-how-to-send-an-email-closure-tab">
                  <div class="accordion-header lg:hidden" id="how-to-send-an-email-closure-acc">
                    <button id="how-to-send-an-email-closure-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-send-an-email-closure" aria-expanded="false" aria-controls="how-to-send-an-email-closure">How to Send an Email Closure</button>
                  </div>
                  <div id="how-to-send-an-email-closure" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-send-an-email-closure-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+20.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-send-an-email-closure-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-affiliate-invoice-insights-report" role="tabpanel" aria-labelledby="v-pills-affiliate-invoice-insights-report-tab">
                  <div class="accordion-header lg:hidden" id="affiliate-invoice-insights-report-acc">
                    <button id="affiliate-invoice-insights-report-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#affiliate-invoice-insights-report" aria-expanded="false" aria-controls="affiliate-invoice-insights-report">Affiliate Invoice Insights Report</button>
                  </div>
                  <div id="affiliate-invoice-insights-report" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="affiliate-invoice-insights-report-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+30.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="affiliate-invoice-insights-report-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-affiliate-invoice-insights-report-multi" role="tabpanel" aria-labelledby="v-pills-affiliate-invoice-insights-report-multi-tab">
                  <div class="accordion-header lg:hidden" id="affiliate-invoice-insights-report-multi-acc">
                    <button id="affiliate-invoice-insights-report-multi-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#affiliate-invoice-insights-report-multi" aria-expanded="false" aria-controls="affiliate-invoice-insights-report-multi">Affiliate Invoice Insights Report/Multi-Invoice</button>
                  </div>
                  <div id="affiliate-invoice-insights-report-multi" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="affiliate-invoice-insights-report-multi-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+31.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="affiliate-invoice-insights-report-multi-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- Manager Training End -->
                <!-- Affiliates Menu Start -->
                <div class="traning-video-list-sub-title text-sm text-[#020817] font-medium mb-2 mt-4 lg:hidden">Affiliates</div>
                <div class="tab-pane fade {{ session('userType') == 'Affiliate' ? 'show active' : ''  }} accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-book-an-affiliate" role="tabpanel" aria-labelledby="v-pills-how-to-book-an-affiliate-tab">
                  <div class="accordion-header lg:hidden" id="how-to-book-an-affiliate-acc">
                    <button id="how-to-book-an-affiliate-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-book-an-affiliate" aria-expanded="false" aria-controls="how-to-book-an-affiliate">How to Book an Affiliate</button>
                  </div>

                  <div id="how-to-book-an-affiliate" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-book-an-affiliate-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+21.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="how-to-book-an-affiliate-contant" class="iframe-detail-wrap">
                      <div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%">
                        <iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+21.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Book an Affiliate | Native American Tours">
                        </iframe>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-how-to-remove-guests-from-affiliate-bookings" role="tabpanel" aria-labelledby="v-pills-how-to-remove-guests-from-affiliate-bookings-tab">
                  <div class="accordion-header lg:hidden" id="how-to-remove-guests-from-affiliate-bookings-acc">
                    <button id="how-to-remove-guests-from-affiliate-bookings-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#how-to-remove-guests-from-affiliate-bookings" aria-expanded="false" aria-controls="how-to-remove-guests-from-affiliate-bookings">How to Remove Guests from Affiliate Bookings</button>
                  </div>

                  <div id="how-to-remove-guests-from-affiliate-bookings" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="how-to-remove-guests-from-affiliate-bookings-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+22.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>

                        </ul>
                      </div>
                    </div>
                    <div id="how-to-remove-guests-from-affiliate-bookings-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <div class="tab-pane fade accordion-item border border-[#E5E7EB] rounded-lg mb-2" id="v-pills-affiliate-reference-booking" role="tabpanel" aria-labelledby="v-pills-affiliate-reference-booking-tab">
                  <div class="accordion-header lg:hidden" id="affiliate-reference-booking-acc">
                    <button id="affiliate-reference-booking-getdetail" class="accordion-button w-full text-left px-3 py-2 mb-1 rounded-md text-sm font-medium collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#affiliate-reference-booking" aria-expanded="false" aria-controls="affiliate-reference-booking">Affiliate Reference Booking</button>
                  </div>

                  <div id="affiliate-reference-booking" class="accordion-collapse collapse p-4 min-[1024px]:block min-[1024px]:visible" aria-labelledby="affiliate-reference-booking-acc" data-bs-parent="#v-pills-tabContent">
                    <div class="d-flex shareButtonDiv">
                      <div id="social-links">
                        <ul>
                          <li><a class="mb-2 text-xs text-white font-semibold shadow-sm rounded-lg px-10 py-2 inline-block bg-[#60BEBD] border border-[#60BEBD] hover:border-[#60BEBD] hover:bg-white hover:text-[#60BEBD] transition-all duration-300 ease-in-out copy_text js-tooltip" href="https://nat-help-videos.s3.amazonaws.com/Video+NA+23.mp4" data-toggle="tooltip" title="Copy to Clipboard"><span class="fa fa-copy"></span></a></li>
                        </ul>
                      </div>
                    </div>
                    <div id="affiliate-reference-booking-contant" class="iframe-detail-wrap"></div>
                  </div>
                </div>
                <!-- Affiliates Menu End -->
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  @section('css-libraries')
	  @if(App::environment('local'))
		  <link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
	  @else
		  <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
	  @endif
  @endsection
  @section('js-libraries')
	  @if(App::environment('local'))
		  <script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	  @else
		  <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	  @endif
  @endsection
  <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha256-4+XzXVhsDmqanXGHaHvgh1gMQKX40OUvDEBTu8JcmNs=" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
  <script src="https://unpkg.com/sweetalert@2.1.2/dist/sweetalert.min.js"></script>

  @section('js-custom')
	  <script src="{{ asset('js/custom.js') }}"></script>
	  <script>
		  $( function() {
			  var helptopics = [
				  {
					  value: "v-pills-calendar-view-tab",
					  label: "Calendar View",
				  },
				  {
					  value: "v-pills-agenda-view-tab",
					  label: "Agenda View"
				  },
				  {
					  value: "v-pills-reports-view-tab",
					  label: "Reports View"
				  },
				  {
					  value: "v-pills-booking-details-tab",
					  label: "Booking Details"
				  },
				  {
					  value: "v-pills-how-to-check-in-a-booking-tab",
					  label: "How to Check-In a Booking"
				  },
				  {
					  value: "v-pills-how-reschedule-a-booking-tab",
					  label: "How Reschedule a Booking"
				  },
				  {
					  value: "v-pills-how-to-rebook-tab",
					  label: "How to Rebook"
				  },
				  {
					  value: "v-pills-how-to-delete-passengers-from-a-booking-tab",
					  label: "How to Delete Guests From a Booking"
				  },
				  {
					  value: "v-pills-permit-fee-tab",
					  label: "Permit Fee"
				  },
				  {
					  value: "v-pills-how-to-change-guest-category-tab",
					  label: "How to Change Guest Category"
				  },
				  {
					  value: "v-pills-how-to-add-people-to-a-booking-tab",
					  label: "How to Add People to a Booking"
				  },
				  {
					  value: "v-pills-how-to-add-people-to-a-booking-tab",
					  label: "How to Add People to a Booking"
				  },
				  {
					  value: "v-pills-how-to-tip-tab",
					  label: "How to Tip"
				  },
				  {
					  value: "v-pills-waiver-tab",
					  label: "Waiver"
				  },
				  {
					  value: "v-pills-how-to-make-a-booking-tab",
					  label: "How to Make a Booking"
				  },
				  {
					  value: "v-pills-how-to-make-a-cash-booking-tab",
					  label: "How to Make a Cash Booking"
				  },
				  {
					  value: "v-pills-how-to-pay-later-invoice-tab",
					  label: "How to Pay Later/Invoice"
				  },
				  {
					  value: "v-pills-how-to-make-a-split-payment-tab",
					  label: "How to Make a Split Payment"
				  },
          {
            value: "v-pills-how-to-make-a-split-booking-tab",
            label: "How to Make a Split Booking"
          },
				  {
					  value: "v-pills-how-to-cancel-and-refund-tab",
					  label: "How to Cancel and Refund"
				  },
				  {
					  value: "v-pills-how-to-refund-from-multiple-accounts-tab",
					  label: "How to Refund from Multiple Accounts"
				  },
				  {
					  value: "v-pills-how-to-close-tour-times-tab",
					  label: "How to Close Tour Times"
				  },
				  {
					  value: "v-pills-how-to-add-staff-tab",
					  label: "How to Add Staff"
				  },
				  {
					  value: "v-pills-how-to-create-a-invoice-tab",
					  label: "How to Create a Invoice"
				  },
				  {
					  value: "v-pills-how-to-update-staff-role-tab",
					  label: "How to Update Staff Role"
				  },
				  {
					  value: "v-pills-how-to-add-or-modify-tour-slots-tab",
					  label: "How to Add or Modify Tour Slots"
				  },
				  {
					  value: "v-pills-how-to-send-an-email-closure-tab",
					  label: "How to Send an Email Closure"
				  },
          {
            value: "v-pills-affiliate-invoice-insights-report-tab",
            label: "Affiliate Invoice Insights Report"
          },
           {
            value: "v-pills-affiliate-invoice-insights-report-multi-tab",
            label: "Affiliate Invoice Insights Report/Multi-Invoice"
          },
				  {
					  value: "v-pills-how-to-book-an-affiliate-tab",
					  label: "How to Book an Affiliate"
				  },
				  {
					  value: "v-pills-how-to-remove-guests-from-affiliate-bookings-tab",
					  label: "How to Remove Guests from Affiliate Bookings"
				  }

			  ];

			  $("#searchhelp").autocomplete({
				  minLength: 2,
				  source: helptopics,
				  focus: function (event, ui) {
					  return false;
				  },
				  select: function (event, ui) {
					   $( "#searchhelp" ).val( ui.item.label );
					   $('#'+ui.item.value).trigger('click');

					  return false;
				  }
			  });
		  });
	  </script>
  @endsection

  <script type="text/javascript">
  $(function(){

    // General Overview
    $('#v-pills-calendar-view-tab, #calendar-view-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#calendar-view-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+1.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Calendar View | Native American Tours"></iframe></div>');
    });

    $('#v-pills-agenda-view-tab, #agenda-view-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#agenda-view-contant').html('<div style="position: relative; padding-bottom: calc(57.822724568709106% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+2.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Agenda View | Native American Tours"></iframe></div>');
    });

    $('#v-pills-reports-view-tab, #reports-view-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#reports-view-contant').html('<div style="position: relative; padding-bottom: calc(57.822724568709106% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+3.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Reports View | Native American Tours"></iframe></div>');
    });

    // Booking Detail
    $('#v-pills-booking-details-tab, #booking-details-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#booking-details-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+4.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Booking Details | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-check-in-a-booking-tab, #how-to-check-in-a-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-check-in-a-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+5.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Check-In a Booking | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-reschedule-a-booking-tab, #how-reschedule-a-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-reschedule-a-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+6.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How Reschedule a Booking | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-rebook-tab, #how-to-rebook-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-rebook-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+7.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Rebook | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-delete-passengers-from-a-booking-tab, #how-to-delete-passengers-from-a-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-delete-passengers-from-a-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+8.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Delete Passengers From a Booking | Native American Tours"></iframe></div>');
    });

    $('#v-pills-permit-fee-tab, #permit-fee-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#permit-fee-contant').html('<div style="position: relative; padding-bottom: calc(57.822724568709106% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+9.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Permit Fee | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-change-guest-category-tab, #how-to-change-guest-category-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-change-guest-category-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+10.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Change Guest Category | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-add-people-to-a-booking-tab, #how-to-add-people-to-a-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-add-people-to-a-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+11.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Add People to a Booking | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-tip-tab, #how-to-tip-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-tip-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+12.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Tip | Native American Tours"></iframe></div>');
    });

    $('#v-pills-waiver-tab, #waiver-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#waiver-contant').html('<div style="position: relative; padding-bottom: calc(57.822724568709106% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+13.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Waiver | Native American Tours"></iframe></div>');
    });

    // Make a Booking
    $('#v-pills-how-to-make-a-booking-tab, #how-to-make-a-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-make-a-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+24.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Make a Booking | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-make-a-cash-booking-tab, #how-to-make-a-cash-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-make-a-cash-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+25.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Make a Cash Booking | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-pay-later-invoice-tab, #how-to-pay-later-invoice-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-pay-later-invoice-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+26.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Pay Later/Invoice | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-make-a-split-payment-tab, #how-to-make-a-split-payment-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-make-a-split-payment-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+27.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Make a Split Payment | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-make-a-split-booking-tab, #how-to-make-a-split-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-make-a-split-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+28.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Make a Split Booking | Native American Tours"></iframe></div>');
    });

    // Cancel/Refund
    $('#v-pills-how-to-cancel-and-refund-tab, #how-to-cancel-and-refund-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-cancel-and-refund-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+14.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Cancel and Refund | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-refund-from-multiple-accounts-tab, #how-to-refund-from-multiple-accounts-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-refund-from-multiple-accounts-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+29.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Refund from Multiple Accounts | Native American Tours"></iframe></div>');
    });

    // Setting Menu
    $('#v-pills-how-to-close-tour-times-tab, #how-to-close-tour-times-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-close-tour-times-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+15.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Close Tour Times | Native American Tours"></iframe></div>');
    });

    $('#v-pills-how-to-add-staff-tab, #how-to-add-staff-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-add-staff-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+17.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Add Staff | Native American Tours"></iframe></div>');
    });

     $('#v-pills-how-to-create-a-invoice-tab, #how-to-create-a-invoice-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-create-a-invoice-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+16.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Create a Invoice | Native American Tours"></iframe></div>');
    });

    // Manager Training
    $('#v-pills-how-to-update-staff-role-tab, #how-to-update-staff-role-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-update-staff-role-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+18.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Update Staff Role | Native American Tours"></iframe></div>');
    });
    $('#v-pills-how-to-add-or-modify-tour-slots-tab, #how-to-add-or-modify-tour-slots-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-add-or-modify-tour-slots-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+19.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Add or Modify Tour Slots | Native American Tours"></iframe></div>');
    });
    $('#v-pills-how-to-send-an-email-closure-tab, #how-to-send-an-email-closure-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-send-an-email-closure-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+20.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Send an Email Closure | Native American Tours"></iframe></div>');
    });
    $('#v-pills-affiliate-invoice-insights-report-tab, #affiliate-invoice-insights-report-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#affiliate-invoice-insights-report-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+30.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Affiliate Invoice Insights Report | Native American Tours"></iframe></div>');
    });
    $('#v-pills-affiliate-invoice-insights-report-multi-tab, #affiliate-invoice-insights-report-multi-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#affiliate-invoice-insights-report-multi-contant').html('<div style="position: relative; padding-bottom: calc(57.870370370370374% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+31.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Affiliate Invoice Insights Report/Multi-Invoice | Native American Tours"></iframe></div>');
    });
    // Manager Training End
    // Affiliates Start
    $('#v-pills-how-to-book-an-affiliate-tab, #how-to-book-an-affiliate-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-book-an-affiliate-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+21.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Book an Affiliate | Native American Tours"></iframe></div>');
    });
    $('#v-pills-how-to-remove-guests-from-affiliate-bookings-tab, #how-to-remove-guests-from-affiliate-bookings-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#how-to-remove-guests-from-affiliate-bookings-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+22.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="How to Remove Guests from Affiliate Bookings | Native American Tours"></iframe></div>');
    });
    $('#v-pills-affiliate-reference-booking-tab, #affiliate-reference-booking-getdetail').click(function(){
      $('.iframe-detail-wrap').html('');
      $('#affiliate-reference-booking-contant').html('<div style="position: relative; padding-bottom: calc(56.25% + 41px); height: 0; width: 100%"><iframe src="https://nat-help-videos.s3.amazonaws.com/Video+NA+23.mp4?embed" frameborder="0" loading="lazy" webkitallowfullscreen mozallowfullscreen allowfullscreen style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;color-scheme: light;" title="Affiliate Reference Booking | Native American Tours"></iframe></div>');
    });
    // Affiliates End
  });
  $('.copy_text').click(function (e) {
	  e.preventDefault();
	  var copyText = $(this).attr('href');

	  document.addEventListener('copy', function(e) {
		  e.clipboardData.setData('text/plain', copyText);
		  e.preventDefault();
	  }, true);
	  document.execCommand('copy');
	  $(this).attr('title', 'Copied');
	  $(this).tooltip('show');
  });

  </script>
</body>
@endsection

