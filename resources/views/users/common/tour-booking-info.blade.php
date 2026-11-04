@extends('layouts.default-2')

@php
    $setting = hasSetting('INVOICE_ENABLE_DISABLE');
    $isPaymentLink = hasSetting('PAYMENT_LINK');
    $uTypeAdmin = session('userType') == 'Admin';
    $routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
    $splitRefundCheck;
    $oldBookingId = oldBookingId();
    $pay = round($paymentOnlyCheck);
    $bok = round($bookingM->booking_total);
    $do_not_check = 0;
    $subtotal = 0;
    if (!empty($trans) && $transP->via == "Card" && $trans->payment_type == 1) {
        $do_not_check = 1;
    }
    $isCashFees = cashFees();
    $cashPriceEnabled = cashPriceEnabled();
    $isGroupBooking = $bookingM->is_group_booking;
    $today = date('Y-m-d');
    $customerInfoUpdateCount = 0;
    $disableCardSave = hasSetting('DISABLE_CARD_SAVE');
    $disableAffiliateCardSave = disableAffiliateCardSave();
@endphp
@if (session('userType') == 'Admin')
    @php
        $prefixForCustomerUpdate = 'admin.';
        $prefixHotelRoute = 'admin.tour-operator.';
       @endphp
@elseif (session('userType') == 'Tour Operator')
    @php
        $prefixForCustomerUpdate = 'tour-operator.';
        $prefixHotelRoute = 'tour-operator.';
    @endphp
@elseif (session('userType') == 'Affiliate')
    @php
        $prefixForCustomerUpdate = 'affiliate.';
        $prefixHotelRoute = 'affiliate.';
    @endphp
@endif
@php
    $isUnpaidBooking = (!empty($trans) && $trans->payment_type == 1 && $bok > $pay) || empty($trans);
    // Payment Link doesn't account for a split-payment leg's amount (it always bills the full
    // remaining booking total, ignoring what's already been collected on the other leg/legs) —
    // hide it from every payment modal (Make Payment, Change People, Custom Charge, Discount)
    // whenever the booking already shows the "Split" payment-status badge.
    //
    // Must match the "Split" badge's OWN condition exactly (see it further down this file:
    // !empty($trans) && $trans->payment_type == 1 && $bok > $pay) — payment_type == 1 alone just
    // means "this booking has ever gone through a split/divided payment", not "still owes money
    // across legs". Without the $bok > $pay check, a booking that already shows "Paid in Full"
    // (both legs settled) wrongly still hid Pay with Link on every later, unrelated payment (e.g.
    // a brand-new Custom Charge on that same, now fully-paid booking).
    $isSplitPaymentBooking = !empty($trans) && $trans->payment_type == 1 && $bok > $pay;
@endphp

@section('main-content')

    @if ($errors->any())
        @include('layouts._errors')
    @endif

    @if (session('message'))
        @include('layouts._message')
    @endif
    @if (Session::has('error'))
        <div
            class="alert alert-danger alert-dismissible mb-6 flex items-center justify-between py-2 px-4 text-sm font-normal text-[#721c24] rounded-md border border-[#f5c6cb] bg-[#f8d7da]">
            <ul>
                {{Session::get('error')}}
            </ul>
            <button type="button" class="btn-close ml-2" data-bs-dismiss="alert" aria-label="Close"><svg width="16" height="16"
                    viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round">
                    </path>
                    <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round">
                    </path>
                </svg></button>
        </div>
    @endif

    <section class="select-edit-booking {{ $bookingM->background_colour_class }}">
        <div class="w-full container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-full max-w-full">
                    <div class="px-6 py-4 border-b border-[#E5E7EB]">
                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full md:w-1/3 max-w-full px-3">
                                <h3 class="text-lg text-[#020817] font-semibold mb-1" id="custNameHeading"><span
                                        id="customerName">{{ $bookingM->customer }}</span></h3>

                                <svg class="inline-block mr-1" width="16" height="16" viewBox="0 0 16 16" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M13.3359 2.66406H2.66927C1.93289 2.66406 1.33594 3.26102 1.33594 3.9974V11.9974C1.33594 12.7338 1.93289 13.3307 2.66927 13.3307H13.3359C14.0723 13.3307 14.6693 12.7338 14.6693 11.9974V3.9974C14.6693 3.26102 14.0723 2.66406 13.3359 2.66406Z"
                                        stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                    <path
                                        d="M14.6693 4.66406L8.68927 8.46406C8.48345 8.59301 8.24548 8.6614 8.0026 8.6614C7.75973 8.6614 7.52176 8.59301 7.31594 8.46406L1.33594 4.66406"
                                        stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                        stroke-linejoin="round" />
                                </svg>
                                <a href="mailto:{{ $bookingM->cust_email }}"
                                    class="contactemail text-base text-[#4B5563] mr-3" id="customerEmail">
                                    {{ $bookingM->cust_email }}
                                </a>

                                @if (!empty($bookingM->cust_phone))
                                    <svg class="inline-block mr-1" width="15" height="16" viewBox="0 0 15 16" fill="none"
                                        xmlns="http://www.w3.org/2000/svg">
                                        <g clip-path="url(#clip0_2316_3804)">
                                            <path
                                                d="M7.5 15.5C3.36437 15.5 0 12.1356 0 8C0 3.86437 3.36437 0.5 7.5 0.5C11.6356 0.5 15 3.86437 15 8C15 12.1356 11.6356 15.5 7.5 15.5ZM7.5 1.75C4.05375 1.75 1.25 4.55375 1.25 8C1.25 11.4462 4.05375 14.25 7.5 14.25C10.9462 14.25 13.75 11.4462 13.75 8C13.75 4.55375 10.9462 1.75 7.5 1.75ZM9.555 8.69687L8.44562 9.80625C7.17875 9.32312 6.245 8.42625 5.69375 7.05438L6.80313 5.945L5.10813 4.25L4.24687 5.11125C3.90937 5.44812 3.75 5.89187 3.75 6.33437C3.75 8.78063 6.895 11.7506 9.16625 11.7506C9.60875 11.7506 10.0525 11.5906 10.3894 11.2537L11.2506 10.3925L9.55562 8.6975L9.555 8.69687Z"
                                                fill="#020817" />
                                        </g>
                                        <defs>
                                            <clipPath id="clip0_2316_3804">
                                                <rect width="15" height="15" fill="white" transform="translate(0 0.5)" />
                                            </clipPath>
                                        </defs>
                                    </svg>
                                    <a href="tel:{{ $bookingM->cust_phone }}" class="contactnum text-base text-[#4B5563] mr-3"
                                        id="customerNum">
                                        {{ $bookingM->cust_phone }}
                                    </a>
                                @endif

                                @if ($hotel)
                                    <p class="contacthotel text-base font-medium text-[#020817] mt-1" id="customerHotel">
                                        <i class="far fa-building"></i>
                                        <span class="contacthotelname">{{ $hotel->name }}</span>
                                        <br>
                                    <p class="m-1"></p>
                                    <span class="contacthoteladdress">{{ $hotel->address }}</span>
                                    </p>
                                @endif

                                <div class="text-base text-[#4B5563] mt-1">Booked Online
                                    {{ formatDateTime($bookingM->created_at) }}
                                </div>
                            </div>
                            {{-- Middle column: Priority Note badge --}}
                            <div class="w-full md:w-1/3 max-w-full px-3" style="display:flex;align-items:center;justify-content:center;">
                                <div id="priorityNoteBadgeArea" style="width:100%;">
                                    @if (!empty($priorityNote))
                                        <div class="flex items-center rounded-lg px-3 py-2 cursor-pointer"
                                             style="background:#FEF2F2;border:1.5px solid #DC2626;gap:8px;"
                                             onclick="openPriorityNoteModal()">
                                            <i class="fas fa-flag text-xs" style="color:#DC2626;flex-shrink:0;"></i>
                                            <div style="flex:1;min-width:0;">
                                                <div class="text-xs font-bold uppercase tracking-wide" style="color:#DC2626;line-height:1;">High Priority</div>
                                                <div class="text-xs truncate" style="color:#6B7280;margin-top:2px;">{{ $priorityNote->details }}</div>
                                            </div>
                                            <i class="fas fa-pencil-alt text-xs" style="color:#DC2626;flex-shrink:0;"></i>
                                        </div>
                                    @else
                                        <button type="button" class="text-xs font-medium" style="color:#DC2626;background:none;border:none;cursor:pointer;display:block;width:100%;text-align:center;"
                                                onclick="openPriorityNoteModal()">
                                            <i class="fas fa-flag mr-1 text-xs"></i> Add Priority Note
                                        </button>
                                    @endif
                                </div>
                            </div>
                            {{-- Right column: Booking IDs --}}
                            <div class="w-full md:w-1/3 max-w-full px-3 text-right">
                                <div
                                    class="inline-block text-sm font-medium text-[#15803D] bg-[#DCFCE7] rounded-xl px-3 py-1">
                                    Booking ID #<a href="" target="_blank">{{ bookingPrefix($bookingM->id) }}</a></div>
                                @if($bookingM->order_id != '')
                                    <div
                                        class="inline-block text-sm font-medium text-[#1E40AF] bg-[#DBEAFE] rounded-xl px-3 py-1 mt-1">
                                        Order ID #<a href="" target="_blank">{{ bookingPrefix($bookingM->order_id) }}</a></div>
                                @endif
                                @if ($bookingM->via == "TripAdvisor" && $bookingM->tripadvisor_id)
                                    <div
                                        class="inline-block text-sm font-medium text-[#1E40AF] bg-[#DBEAFE] rounded-xl px-3 py-1 mt-1">
                                        TripAdvisor Booking Reference #<a href="#">{{ $bookingM->tripadvisor_id }}</a></div>
                                @endif

                                @if ($bookingM->via == "AffiliatePartner" && $bookingM->external_booking_id)
                                    <div
                                        class="inline-block text-sm font-medium text-[#1E40AF] bg-[#DBEAFE] rounded-xl px-3 py-1 mt-1">
                                        Affiliate Partner Booking Reference #<a href="#">{{ $bookingM->external_booking_id}}</a>
                                    </div>
                                @endif

                                @if ($bookingM->via == "GetYourGuide" && $bookingM->get_your_guide_booking_id)
                                    <div
                                        class="inline-block text-sm font-medium text-[#1E40AF] bg-[#DBEAFE] rounded-xl px-3 py-1 mt-1">
                                        GetYourGuide Booking Reference #<a
                                            href="#">{{ $bookingM->get_your_guide_booking_id }}</a></div>
                                @endif

                                @if (($bookingM->via === $bookingM->ota_title) && !empty($bookingM->ota_booking_id))
                                    <div
                                        class="inline-block text-sm font-medium text-[#1E40AF] bg-[#DBEAFE] rounded-xl px-3 py-1 mt-1">
                                        {{ $bookingM->ota_title }} Booking Reference #<a
                                            href="#">{{ $bookingM->ota_booking_id }}</a></div>
                                @endif
                                @if (!empty($bookingM->booking_source) && $bookingM->booking_source === 'online')
                                    <div class="inline-block text-sm font-medium rounded-xl px-3 py-1 mt-1"
                                         style="color:#92400E;background:#FEF3C7;border:1px solid #FCD34D;">
                                        <i class="fas fa-tag mr-1" style="font-size:11px;"></i>Online
                                    </div>
                                @elseif (!empty($bookingM->booking_source))
                                    @php
                                        $bookingSourceLabels = [
                                            'phone_call' => 'Phone Call',
                                            'walk_in'    => 'Walk-In',
                                            'email'      => 'Email',
                                            'other'      => 'Other',
                                        ];
                                        $bookingSourceLabel = $bookingSourceLabels[$bookingM->booking_source] ?? $bookingM->booking_source;
                                    @endphp
                                    <div class="inline-block text-sm font-medium rounded-xl px-3 py-1 mt-1"
                                         style="color:#92400E;background:#FEF3C7;border:1px solid #FCD34D;">
                                        <i class="fas fa-tag mr-1" style="font-size:11px;"></i>{{ $bookingSourceLabel }}
                                    </div>
                                @endif
                                @if (empty($bookingM->booking_source) || $bookingM->booking_source !== 'online')
                                <div
                                    class="inline-block text-sm font-medium text-white bg-[#047857] rounded-xl px-3 py-1 mt-1">
                                    @if (!empty($user['name'])) {{$user['name']}} @endif: {{$user['type']}}
                                </div>
                                @endif

                            </div>
                        </div>
                    </div>
                    <div class="px-6 py-4">
                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3 booking-details relative">
                                <button type="button"
                                    class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] rounded-lg inline-block cursor-pointer"
                                    data-bs-toggle="modal" data-bs-target="#modal-customer"><img
                                        class="inline-block align-text-top mr-2" src="{{ asset('images/edit-btn.png') }}"
                                        alt="edit"> Edit Contact Info</button>

                                @if (session('userType') != 'Affiliate')
                                    <button type="button"
                                        class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                        onclick="newBookingForCust({{ $bookingM->customer_id . ', ' . $bookingM->id }})"><img
                                            class="inline-block align-text-top mr-2" src="{{ asset('images/add-btn.png') }}"
                                            alt="+"> New Booking for Contact</button>
                                @endif
                                {{-- Print Dropdown Menu --}}
                                <div class="inline-block align-top ml-1 mb-1" style="position: relative;">
                                    <button id="printDropdownBtn" class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] rounded-lg inline-flex items-center cursor-pointer">
                                        <img class="inline-block align-text-top mr-2" src="{{ asset('images/pring-icon.png') }}" height="14" alt="print"> Print <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                    </button>
                                    <ul id="printDropdownMenu" class="hidden w-56 bg-white shadow-lg rounded border text-sm text-left" style="position: absolute; top: 100%; left: 0; z-index: 9999; margin-top: 4px;">
                                        <li id="receipt-print-option" class="hidden">
                                            <a class="block px-4 py-2 text-[#4B5563] hover:text-[#F97316] hover:bg-[#F9FAFB] cursor-pointer" id="openReceiptPrintModal">
                                                <i class="fas fa-receipt mr-2"></i> Receipt Print (Thermal)
                                            </a>
                                        </li>
                                        <li id="receipt-print-divider" class="hidden"><hr class="my-1 border-gray-200"></li>
                                        <li>
                                            <a class="block px-2 py-2 text-[#4B5563] hover:text-[#F97316] hover:bg-[#F9FAFB]" href="{{ route(prefixedRouteName($routePrefix . 'booking.print-info'), ['bookingId' => $bookingM->id]) }}" target="_blank">
                                                <i class="fas fa-print mr-2"></i> Print
                                            </a>
                                        </li>
                                        <li>
                                            <a class="block px-2 py-2 text-[#4B5563] hover:text-[#F97316] hover:bg-[#F9FAFB]" href="{{ route('booking-invoice', ['bookingId' => $bookingM->id]) }}" target="_blank">
                                                <i class="fas fa-file-invoice mr-2"></i> Print Invoice
                                            </a>
                                        </li>
                                        <li>
                                            <a class="block px-2 py-2 text-[#4B5563] hover:text-[#F97316] hover:bg-[#F9FAFB]" href="{{ route(prefixedRouteName($routePrefix .'booking.invoice.no-price'), ['bookingId' => $bookingM->id]) }}" target="_blank">
                                                <i class="fas fa-file-alt mr-2"></i> Print Without Price
                                            </a>
                                        </li>
                                        @if ($bookingM->cart_id != null)
                                        <li>
                                            <a class="block px-2 py-2 text-[#4B5563] hover:text-[#F97316] hover:bg-[#F9FAFB]" href="{{ route(prefixedRouteName($routePrefix . 'booking.combo-booking-invoice'), ['cartId' => $bookingM->cart_id]) }}" target="_blank">
                                                <i class="fas fa-layer-group mr-2"></i> Combo Receipt
                                            </a>
                                        </li>
                                        @endif
                                    </ul>
                                </div>
                                <a class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                    href="{{ route(prefixedRouteName($routePrefix . 'booking.send-email'), ['bookingId' => $bookingM->id]) }}"
                                    target="_blank"
                                    style="{{ ((($bookingM->via == "TripAdvisor" && $bookingM->tripadvisor_id != null) || ($bookingM->via == "GetYourGuide" && $bookingM->get_your_guide_booking_id != null) || (($bookingM->via === $bookingM->ota_title) && !empty($bookingM->ota_booking_id))) ? "pointer-events: none; color: #999; background-color: #d3d3d3;" : '') }}"><img
                                        class="inline-block align-text-top mr-2" src="{{ asset('images/email.png') }}"
                                        height="14" alt=""> Send Email
                                </a>
                                
                                <button type="button"
                                    class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                    data-bs-toggle="modal" data-bs-target="#modal-resend-booking"
                                    style="{{ ((($bookingM->via == "TripAdvisor" && $bookingM->tripadvisor_id != null) || ($bookingM->via == "GetYourGuide" && $bookingM->get_your_guide_booking_id != null) || (($bookingM->via === $bookingM->ota_title) && !empty($bookingM->ota_booking_id))) ? "pointer-events: none; color: #999; background-color: #d3d3d3;" : '') }}"><img
                                        class="inline-block align-text-top mr-2" src="{{ asset('images/edit-btn.png') }}"
                                        alt=""> Resend Booking</button>

                                @if($bookingM->package_has_waiver)
                                @php $schoolWaiverUrl = route('customer.school-waiver-form', ['bookingId' => \Illuminate\Support\Facades\Crypt::encryptString($bookingM->id)]); @endphp
                                <button type="button"
                                    class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                    onclick="copySchoolWaiverLink('{{ $schoolWaiverUrl }}')">
                                    <svg class="inline-block align-text-top mr-1" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="#020817" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="#020817" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    Copy School Waiver Link</button>
                                @endif


                                <!-- <button type="button"
                                    class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-3 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                    data-bs-toggle="modal" data-bs-target="#modal-send-sms-message"><img
                                        class="inline-block align-text-top mr-2" src="{{ asset('images/phone.png') }}"
                                        alt="send sms text message"> SMS Message</button> -->

                                @if($managerRequestEnable['Closure Email'] == 'Yes')
                                    @if ($uTypeAdmin || (Session::get('roleNames') && Session::get('roleNames')->contains("id", "2")))
                                        <span
                                            class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                            data-booking-id="{{ $bookingM->id }}" data-date="{{ $bookingM->date }}"
                                            data-slot-id="{{ $bookingM->time_id }}"
                                            data-tour-operator-id="{{ $bookingM->tour_operator_id }}"
                                            data-tour-operator-name="{{ $bookingM->tour_operator }}"
                                            onclick="return lowerAntelopeCanyonClosure(this);"
                                            style="{{ ((($bookingM->via == "TripAdvisor" && $bookingM->tripadvisor_id != null) || ($bookingM->via == "GetYourGuide" && $bookingM->get_your_guide_booking_id != null) || (($bookingM->via === $bookingM->ota_title) && !empty($bookingM->ota_booking_id))) ? "pointer-events: none; color: #999; background-color: #d3d3d3;" : '') }}"><img
                                                class="inline-block align-text-top mr-2" src="{{ asset('images/email.png') }}"
                                                height="14" alt=""> Closure Email</span>
                                    @endif
                                @endif
                                @if ($uTypeAdmin || (Session::get('roleNames') && Session::get('roleNames')->contains("id", "2")) || session('userType') == 'Affiliate')
                                    @if (session('userType') != 'Affiliate')

                                    @endif
                                    @if ($bookingM->is_affiliate == 'Yes')
                                        <span
                                            class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                            data-id="{{ $bookingM->id }}"
                                            data-is_reserved_for_affiliate="{{ $bookingM->is_reserved_for_affiliate }}"
                                            onclick="return unblockAffiliateSoftBooking(this);"
                                            style="{{ (($bookingM->via == 'TripAdvisor' && !empty($bookingM->tripadvisor_id))
                                                || ($bookingM->via == 'GetYourGuide' && !empty($bookingM->get_your_guide_booking_id))
                                                || ($bookingM->via === $bookingM->ota_title && !empty($bookingM->ota_booking_id))) ? 'pointer-events: none; color: #999; background-color: #d3d3d3;' : '' }}">
                                            <img class="inline-block align-text-top mr-2" src="{{ asset('images/edit-btn.png') }}"
                                                height="14" alt="">
                                            @if ($bookingM->is_reserved_for_affiliate)
                                                Soft Unblock
                                            @else
                                                Soft Block
                                            @endif
                                        </span>
                                    @endif
                                    @if (hasSetting('HOTEL_PICKUP'))
                                        <button type="button"
                                            class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer"
                                            data-bs-toggle="modal" data-bs-target="#modal-hotel"><img
                                                class="inline-block align-text-top mr-2" src="{{ asset('images/edit-btn.png') }}"
                                                alt=""> Hotel Pickup</button>
                                    @endif
                                @endif
                                @if($isPaymentLink == true)
                                    @can('payment-link')
                                        @if($trans == null || !empty($pendingRemainingLink))
                                            <span
                                                class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-2 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer max-h-[38px]"
                                                style="cursor: pointer;" data-booking-id="{{ $bookingM->id }}"
                                                id="initPaymentLinkModal"><img class="inline-block align-text-top mr-2"
                                                    src="{{ asset('images/payment-link.png') }}" alt="Payment Link"> Payment Link</span>
                                        @endif
                                    @endcan
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @if (count($comboBookingsData) > 1 && (session('userType') == 'Tour Operator' || session('userType') == 'Admin'))
    <section class="px-6 py-4">
        <div class="w-full container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-full max-w-full">
                    @include('users.common.partials.booking._combo-hub-card')
                </div>
            </div>
        </div>
    </section> 
    @endif         
    <section class="px-6 py-4">
        <div class="w-full container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-full max-w-full">
                    <div class="border border-[#E5E7EB] rounded-lg overflow-hidden">
                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3 info-booking-sec">
                                <div class="p-4" style="background-color:{{ $bookingM->package_colour }}">
                                    @php
                                        $splitBookingCount = collect($history)->where('operation', 'Split Booking Original')->count();
                                    @endphp
                                    <div class="flex flex-wrap -mx-3 items-center" style="position:relative;">
                                        <div class="w-full lg:w-1/2 max-w-full px-3">
                                            {{-- @if ($uTypeAdmin)
                                            <h3>{{ $bookingM->tour_operator }}</h3>
                                            @endif --}}
                                            <h3 class="text-base font-medium text-[#111827] mb-1">
                                                {{ $bookingM->tour_package }}
                                            </h3>
                                            <a class="text-sm text-[#4B5563]" href="#">{{ $bookingM->tour_date_time }}</a>
                                        </div>
                                        @if ($splitBookingCount > 0)
                                        <div class="split-badge-wrap w-full mt-2 lg:mt-0 flex justify-center">
                                            <span style="display:inline-flex;align-items:center;gap:6px;background:#f0ebff;color:#6d28d9;border:1px solid #c4b5fd;border-radius:9999px;padding:6px 14px;font-size:13px;font-weight:600;white-space:nowrap;">
                                                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                                </svg>
                                                Split · {{ $splitBookingCount }} {{ Str::plural('booking', $splitBookingCount) }} created
                                            </span>
                                        </div>
                                        <style>
                                            @media (min-width: 1024px) {
                                                .split-badge-wrap {
                                                    position: absolute;
                                                    left: 50%;
                                                    transform: translateX(-50%);
                                                    width: auto;
                                                }
                                            }
                                        </style>
                                        @endif
                                        <div class="w-full lg:w-1/2 max-w-full px-3 mt-3 lg:mt-0 lg:text-right">
                                            <button type="button" type="button" onclick="getAssignedTourGuides({{ $bookingM->id }})" class="print-booking-btn text-xs font-medium text-[#020817] border border-[#E2E8F0] bg-white hover:bg-[#F9FAFB] px-3 py-[10px] ml-1 mb-1 rounded-lg inline-block cursor-pointer" ><img class="inline-block align-text-top mr-2" src="{{ asset('images/user.png') }}" alt=""> 
                                     Assign Tour Guides
                                     @if($assignedGuides && $assignedGuides->count())
                                        <div class="mt-2 text-xs text-gray-700">
                                            @foreach($assignedGuides as $guide)
                                                <div>
                                                    {{ $guide->guide_name }} ({{ $guide->tourists }}) @if($guide->station_name) ({{ $guide->station_name }}) @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                    </button>
                                </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3">
                                <div class="p-4">
                                    @include('users.common.tour-booking-info-button')
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3">
                                <div class="px-4">
                                    <div class="payments-col">
                                        <div class="payment-startus sm:flex sm:items-center">
                                            <div class="text-sm text-[#020817] mr-3">Payment:</div>
                                            @php
                                                for ($a = 0, $ac = count($payments), $paid = 0; $a < $ac; $a++) {
                                                    $paid = $payments[$a]->amount;
                                                }
                                                $clrPaid = $paid ? 'success' : 'gray';
                                                $clrUnpaid = $bookingM->total - $paid ? 'alert' : 'gray';
                                                $amount = 0;
                                                $paidF = 0;
                                                $paidFV = 0;
                                                $refund_keep_us = 0;
                                                $amountV = 0;
                                                // Pending payment link amount (unpaid custom charges / addons sent via link)
                                                $pendingPaymentLinkAmount = !empty($pendingRemainingLink) ? (float) $pendingRemainingLink->amount : 0;
                                                // Old flow: a pending history record (is_processed=0) was inserted when the link was sent,
                                                // so it's included in $paymentOnlyCheck and must be deducted from $paidF.
                                                // New flow: no pending history record exists, so $paymentOnlyCheck is already correct —
                                                // deducting would incorrectly reduce the displayed paid amount.
                                                $pendingChangePeopleItem = ($pendingRemainingItems ?? collect())->first(fn($i) => $i->type === 'change_people');
                                                $pendingPaymentLinkDeduction = ($pendingChangePeopleItem && $pendingChangePeopleItem->reference_id !== null)
                                                    ? $pendingPaymentLinkAmount
                                                    : 0;
                                                if (!empty($trans)) {
                                                    // $trans->amount alone only reflects ONE ticket-side transaction — wrong for a
                                                    // booking whose ticket cost was collected across multiple separate charges (e.g.
                                                    // two Change People / Payment Link additions, each producing their own Completed
                                                    // transaction — confirmed on booking #348666, tenant kens: two $80.50 rows, but
                                                    // this only counted one, understating Payment Total by exactly $80.50).
                                                    // $paymentOnlyCheck already sums every ticket-side "Payment" history row correctly
                                                    // (same source the refund-history override branch below already relies on, and
                                                    // what "Split" badge / outstanding-balance math elsewhere on this page already use)
                                                    // — for the common single-payment case the two are equal, so this changes nothing
                                                    // there.
                                                    $paidF = $paymentOnlyCheck;
                                                }
                                                // Default the displayed "paid" badge to the actual paid amount. The
                                                // tourBookingHistory block below only overrides this when a genuine
                                                // post-payment Refund/Cancelled history row exists — without this
                                                // default, $paidFV stayed at its initial 0 for the common case of a
                                                // paid booking with no refund/cancellation history at all.
                                                $paidFV = $paidF;
                                                // $tourBookingHistory is always an Eloquent Collection (never null), even when it
                                                // matched zero rows — !empty() on a non-null object is ALWAYS true regardless of
                                                // its contents, so this block used to run unconditionally whenever
                                                // $historyPaymentTime existed at all, not just when a genuine post-payment
                                                // Refund/Cancelled row (this collection's own query scope) actually exists.
                                                // That silently overwrote $checkAmount below with $historyPaymentTime's own
                                                // amount even for a booking with no refund/cancellation history whatsoever.
                                                if (!empty($tourBookingHistory)) {
                                                    // Ticket-side-only mirror of $amount, used ONLY for the $paidF
                                                    // ("Payment Total:") calculation just below — add-on refunds are
                                                    // already fully accounted for via $addonsNetPaidAmount (added back
                                                    // further down), so subtracting them here too double-counts them
                                                    // (e.g. a Split Booking that left both a ticket-side reallocation
                                                    // refund and an unrelated, still-valid add-on's own refund in this
                                                    // same history silently cancelled the add-on's credit out of
                                                    // Payment Total instead of counting it). $amount itself stays
                                                    // comprehensive (ticket + add-ons) — that's what "Refund Amount:"
                                                    // displays, and must not change.
                                                    $ticketOnlyRefundAmount = 0;
                                                    foreach ($tourBookingHistory as $bookingHistory) {
                                                        $amount += $bookingHistory->ticket_cost + $bookingHistory->processing_fees + $bookingHistory->platform_fees;
                                                        if (($bookingHistory->charge_type ?? null) !== 'addons') {
                                                            $ticketOnlyRefundAmount += $bookingHistory->ticket_cost + $bookingHistory->processing_fees + $bookingHistory->platform_fees;
                                                        }
                                                    }
                                                    $amount = number_format((float) $amount, 2, '.', '');
                                                    $amount = (float) ($amount);
                                                    $ticketOnlyRefundAmount = (float) number_format((float) $ticketOnlyRefundAmount, 2, '.', '');
                                                    $checkAmount = json_decode($historyPaymentTime->details);
                                                    $changeTheformateWithtypeFoat = number_format((float) $checkAmount->amount, 2, '.', '');
                                                    $tt = (float) ($changeTheformateWithtypeFoat);
                                                    $paidF = $paymentOnlyCheck - $ticketOnlyRefundAmount - $pendingPaymentLinkDeduction;
                                                    // Outstanding balance: refunds + pay-later addons + pending link
                                                    $amountV = $amount + $payLaterAddons['addons_total'] + $pendingPaymentLinkAmount;
                                                    $paidFV = $paidF;

                                                    if ($bookingM->via == "Invoiced") {
                                                        if (!empty($invoiced) && $invoiced->status == "paid") {
                                                            $paidFV = $paidF;
                                                        } else {
                                                            $paidFV = 0;
                                                            $amountV = $paidF;
                                                        }
                                                    }
                                                }
                                                // Add-ons are billed (and often paid) via their own separate payment link/transaction,
                                                // outside $trans/the history block above (both scoped to the tour side only) — fold in
                                                // whatever has actually been collected for them so "Payment:"/"Payment Total:" reflect
                                                // the full amount paid, not just the ticket portion.
                                                $paidF += ($addonsNetPaidAmount ?? 0);
                                                $paidFV += ($addonsNetPaidAmount ?? 0);
                                                $splitPendingAmount = $bookingM->total - $paymentOnlyCheck;
                                            @endphp
                                            <span
                                                class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#047857] border border-[#BBF7D0] bg-[#F0FDF4] rounded-full">{{ moneyFormat(sprintf('%.2f', $paidFV)) }}</span>
                                            @if ($paid == 0)
                                                <span
                                                    class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">{{ moneyFormat(sprintf('%.2f', $bookingM->total + $customChargePrice['custom_charge_total'] + $payLaterAddons['addons_price'])) }}</span>
                                            @elseif (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck)
                                                <span
                                                    class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">{{ moneyFormat(sprintf('%.2f', $bookingM->total - $paymentOnlyCheck)) }}</span>
                                            @else
                                                <span
                                                    class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FECACA] bg-[#FEF2F2] rounded-full">{{ moneyFormat(sprintf('%.2f', $amountV)) }}</span>
                                            @endif
                                            @if ($trans != null && $trans->status == "Completed" && $trans->payment_type != 1)
                                                @if ($amount > 0)
                                                    @if ($bookingM->statusm === 'Cancelled')
                                                        @if ((string) $Refund_Amount != (string) $paymentOnlyCheck)
                                                            <span
                                                                class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FEE2E2] bg-[#FEE2E2] rounded-full"><i
                                                                    class="fa fa-check-circle"></i> Partial Refund</span>
                                                        @else
                                                            <span
                                                                class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FEE2E2] bg-[#FEE2E2] rounded-full"><i
                                                                    class="fa fa-check-circle"></i> Refunded</span>
                                                        @endif
                                                    @else
                                                        <span
                                                            class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FEE2E2] bg-[#FEE2E2] rounded-full"><i
                                                                class="fa fa-check-circle"></i> Partial Refund</span>
                                                    @endif
                                                @else
                                                    @if ($bookingM->via == "Invoiced")
                                                        @if (!empty($invoiced) && $invoiced->status == "paid")
                                                            <span
                                                                class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full"><i
                                                                    class="fa fa-check-circle"></i> Paid in Full</span>
                                                        @else
                                                            <span
                                                                class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FEE2E2] bg-[#FEE2E2] rounded-full"><i
                                                                    class="fa fa-times-circle"></i> UnPaid </span>
                                                        @endif
                                                    @else
                                                        <span
                                                            class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full"><i
                                                                class="fa fa-check-circle"></i> Paid in Full</span>
                                                    @endif
                                                @endif
                                            @elseif (!empty($trans) && $trans->payment_type == 1)
                                                @if ($bok > $pay)
                                                    <span
                                                        class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FEE2E2] bg-[#FEE2E2] rounded-full"><i
                                                            class="fa fa-times-circle"></i> Split </span>
                                                @else
                                                    <span
                                                        class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full"><i
                                                            class="fa fa-check-circle"></i> Paid in Full</span>
                                                @endif
                                            @else
                                                <span
                                                    class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#B91C1C] border border-[#FEE2E2] bg-[#FEE2E2] rounded-full"><i
                                                        class="fa fa-times-circle"></i> UnPaid </span>
                                            @endif
                                            @if ($bookingM->via == "Invoiced")
                                                @if (!empty($invoiced))
                                                    <span
                                                        class="{{ $bookingM->statusm === 'Cancelled' ? 'py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#C2410C] border border-[#FFEDD5] bg-[#FFEDD5] rounded-full' : (in_array($bookingM->via, ['Invoiced']) ? 'py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full' : 'py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#C2410C] border border-[#FFEDD5] bg-[#FFEDD5] rounded-full') }}">
                                                        <i class="fa fa-info-circle"></i>
                                                        {{ $bookingM->statusm === 'Cancelled' ? $bookingM->statusm : (in_array($bookingM->via, ['Invoiced']) ? $bookingM->via : 'Pay Later') }}
                                                    </span>
                                                @else
                                                    <span
                                                        class="py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-black border border-[#FFFFD9] bg-[#ffc107] rounded-full">
                                                        <i class="fa fa-info-circle"></i>
                                                        Billable
                                                    </span>
                                                @endif
                                            @else
                                                <span
                                                    class="badge {{ $bookingM->statusm === 'Cancelled' ? 'py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#C2410C] border border-[#FFEDD5] bg-[#FFEDD5] rounded-full' : (in_array($bookingM->via, array_merge(['Card', 'Cash', 'Invoiced'], $otaPlatforms)) ? 'py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#166534] border border-[#DCFCE7] bg-[#DCFCE7] rounded-full' : 'py-1.5 px-2 mr-2 mb-1 text-xs font-semibold inline-block whitespace-nowrap text-[#C2410C] border border-[#FFEDD5] bg-[#FFEDD5] rounded-full') }}">
                                                    <i class="fa fa-info-circle"></i>
                                                    {{ $bookingM->statusm === 'Cancelled' ? $bookingM->statusm : (in_array($bookingM->via, array_merge(['Card', 'Cash', 'Invoiced', 'Payment Link'], $otaPlatforms)) ? $bookingM->via : (!empty($pendingPaymentLinkForTotal) ? 'Payment Link' : 'Pay Later')) }}
                                                </span>
                                            @endif
                                             <!-- Card Logo -->
                                            @if($savedCard && $bookingM->via == "Card")
                                            @if(!$disableCardSave && !$disableAffiliateCardSave)
                                            <button
                                                type="button"
                                                id="openSavedCardsModal"
                                                class="flex items-center gap-1 mr-2 mb-1 cursor-pointer hover:bg-gray-100 px-2 py-1 rounded-md transition"
                                            >
                                                    <img src="{{ getCardBrandLogo($savedCard->brand) }}" class="max-h-5">
                                                    <span class="text-sm text-gray-600">{{ $savedCard->last4 }}</span>
                                            </button>

                                            @else
                                                <div class="flex items-center gap-1 mr-2 mb-1">
                                                    <img src="{{ getCardBrandLogo($savedCard->brand) }}" class="max-h-5">
                                                    <span class="text-sm text-gray-600">{{ $savedCard->last4 }}</span>
                                                </div>
                                            @endif
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dispute Information Section -->
                        @include('users.common.partials.dispute-info')

                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3">
                                <div class="p-4">
                                    <div
                                        class="table-respons custom-scrollbar max-h-80 overflow-y-auto border border-[#E2E8F0] rounded-lg">
                                        <table class="table text-left w-full" id="billing-table">
                                            <thead class="thead-fixed">
                                                <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                                                    <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                        Total Booked People: {{ $bookingM->tourists + $bookingM->infants }}</th>
                                                    @if($bookingM->has_contacts == 1)
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">Age
                                                        </th>
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                            Weight (lbs) </th>
                                                    @endif
                                                    @if($bookingM->is_check_in)
                                                        @if($bookingM->bundle_package == 0)
                                                            @if($bookingM->checkin_type == 1)
                                                                @if($requestPermissions['Ticket Redeem'] == "Yes")
                                                                    <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap"
                                                                        id="redeemPending">({{ $redeemedTickets }}) Redeemed /
                                                                        ({{ $notRedeemedTickets }}) Pending</th>
                                                                @endif
                                                            @else
                                                                @if($requestPermissions['Check-ins'] == "Yes")
                                                                    <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                                        Checked In / Not Checked In</th>
                                                                @endif
                                                            @endif
                                                        @else
                                                            @if(count($bundlePackageCheckInsList['checkins']) > 0)
                                                                @if($requestPermissions['Check-ins'] == "Yes")
                                                                    <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                                        Checked In / Not Checked In</th>
                                                                @endif
                                                            @endif
                                                            @if(count($bundlePackageCheckInsList['tickets']) > 0)
                                                                @if($requestPermissions['Ticket Redeem'] == "Yes")
                                                                    <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                                        ({{ $redeemedTickets }}) Redeemed / ({{ $notRedeemedTickets }})
                                                                        Pending</th>
                                                                @endif
                                                            @endif
                                                        @endif
                                                    @endif
                                                    @if($bookingM->package_has_waiver)
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                            Liability Waiver</th>
                                                    @endif
                                                    @if($isGroupBooking != 1)
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                            Price (Per Person)</th>
                                                        @if ($bookingM->package_has_permit)
                                                            <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                                Permit Fee</th>
                                                        @endif
                                                        @if ($bookingM->is_partner_booking)
                                                            <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">
                                                                Partner Fee</th>
                                                        @endif
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap">Tax
                                                        </th>
                                                        <th
                                                            class="text-end text-xs font-medium text-[#374151] p-4 whitespace-nowrap border-l border-[#E2E8F0]">
                                                            Total Cost</th>
                                                    @endif

                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $promoDiscount = 0;
                                                    $touristNum = 0;

                                                    $totalBeforeServiceCommission = $bookingM->total - $bookingM->service_commission;

                                                    if ($totalBeforeServiceCommission != 0) {
                                                        $serviceCommissionPercentage = round($bookingM->service_commission * 100 / $totalBeforeServiceCommission, 2);
                                                    }

                                                    $forCardProcChargePercentage = 0;
                                                    $totalSubtotal = 0;
                                                    $totalTicketCost = 0;
                                                    $bookingTArray = array();

                                                @endphp

                                                @foreach($bookingT as $bt)
                                                    @php
                                                        $isAdult = $bt->age_from > config('app.child_max_age') ? 1 : 0;

                                                        if ($bt->rate > 0) {
                                                            if ($isGroupBooking == '1') {
                                                                $rate = $bt->rate + $bt->additional_charge + $bt->partner_fees_amount;
                                                                $per = $rate * ($bt->additional_tax_percentage + $bt->processing_charge_percentage) / 100;
                                                                $subtotal = $rate + $per;
                                                                $touristSubtotal = $subtotal;
                                                                $totalSubtotal += $subtotal;

                                                                // Group Rate tiers price the WHOLE group as one fixed amount per
                                                                // headcount range (e.g. 1-3 pax = $280.90, 4 pax = $307.40, 5-6 pax =
                                                                // $339.20), so the "Delete Booking" modal's per-person refund preview
                                                                // can't just divide this group's current total evenly by its headcount
                                                                // (that used to be exactly what happened here, e.g. showing
                                                                // $339.20 / 6 = $56.53 for removing 1 of 6 people when 5 and 6 are the
                                                                // SAME tier and the real refund is $0). Snapshot the true original
                                                                // headcount now, before the is_infant_rate mutation below can overwrite
                                                                // $bt->tourists, and fetch every sibling tier sharing this rate's exact
                                                                // context, so the JS can look up "price for N people" for whatever
                                                                // headcount actually results from the admin's checkbox selection
                                                                // instead of guessing from a static per-checkbox share.
                                                                $groupRateOriginalTourists = $bt->tourists;
                                                                $groupRateTierTable = getGroupRateTierTable($bt->tour_package_rate_id);
                                                            } else {
                                                                $rate = $bt->rate + $bt->additional_charge + $bt->permit_fee + $bt->partner_fees_amount;
                                                                $per = $rate * ($bt->additional_tax_percentage + $bt->processing_charge_percentage) / 100;
                                                                $subtotal = $rate + $per;
                                                                $touristSubtotal = $subtotal * $bt->tourists;
                                                                $totalSubtotal += $subtotal * $bt->tourists;
                                                            }

                                                            $cardProccharge = number_format((float) ($touristSubtotal) * $bt->original_processing_charge_percentage / 100, 2, '.', '');
                                                            $forCardProcChargePercentage += $cardProccharge;

                                                        }
                                                        if($bt->is_infant_rate == 1) {
                                                            $bt->tourists = $bt->infants;
                                                        }
                                                    @endphp

                                                    @for($a = 0; $a < $bt->tourists; $a++, $touristNum++)
                                                        @php
                                                            $permit_fee = sprintf('%.2f', round($bt->permit_fee));
                                                            $refund_permitfee_status = ($bt->refund_permitfee_status != '') ? json_decode($bt->refund_permitfee_status) : '';
                                                            if ($refund_permitfee_status == '') {
                                                                $totalamtafterpermitfeesrefunded = $bt->total / $bt->tourists;
                                                            } else {
                                                                // Calculate effective permit fee after refunds
                                                                $effectivePermitFee = $bt->permit_fee; // Start with original permit fee
                                                                $processingFeesRefunded = 0;
                                                                
                                                                if (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y") {
                                                                    // Check if this is a partial refund with new_permit_fee
                                                                    if (isset($refund_permitfee_status[$a]->new_permit_fee)) {
                                                                        $effectivePermitFee = $refund_permitfee_status[$a]->new_permit_fee;
                                                                    } else {
                                                                        $effectivePermitFee = 0; // Full refund case
                                                                    }
                                                                    
                                                                    if (isset($refund_permitfee_status[$a]->processing_fees)) {
                                                                        $processingFeesRefunded = $refund_permitfee_status[$a]->processing_fees;
                                                                    }
                                                                }
                                                                
                                                                // Calculate total with effective permit fee
                                                                $totalamtafterpermitfeesrefunded = ($bt->rate + $bt->additional_charge + $effectivePermitFee + $bt->partner_fees_amount) - $processingFeesRefunded;
                                                            }
                                                            if($isGroupBooking == '1') {
                                                                $totalTicketCost = $subtotal;
                                                            } else {
                                                                $totalTicketCost += $totalamtafterpermitfeesrefunded;
                                                            }
                                                            $paxPendingInfo = ($pendingPaxInfo ?? [])[$bt->id] ?? null;
                                                            $isPendingPax   = $paxPendingInfo !== null
                                                                && $a >= ($bt->tourists - ($paxPendingInfo['added_count'] ?? 0));
                                                        @endphp
                                                        <tr class="border-t border-[#E2E8F0]{{ $isPendingPax ? ' bg-amber-50' : ' hover:bg-[#F0FDF4]' }}">
                                                            @php
                                                                $cid = $cname = $cage = $cweight = $cnote = '';
                                                            @endphp
                                                            @if($bookingM->has_contacts == 1)
                                                                @foreach($bookingContacts as $key => $bookingContact)
                                                                    @foreach($bookingContact['contact_data'] as $keyc => $contact)
                                                                        @if($key == $bt->id && $keyc == $a)
                                                                            @php
                                                                                $cid = $contact->contact_id;
                                                                                $cname = $contact->name;
                                                                                $cage = $contact->age;
                                                                                $cweight = $contact->weight;
                                                                            @endphp
                                                                        @endif
                                                                    @endforeach
                                                                @endforeach
                                                                @if($cid)
                                                                    <td class="text-sm text-[#4B5563] p-4">
                                                                        <span class="edit-contact" title="Edit Contact" id="{{ $cid }}">
                                                                            <i class="fas fa-pencil-alt pl-1"></i>
                                                                        </span>&nbsp;&nbsp;
                                                                        <span class="contact_name_{{ $cid }}">{{ $cname }}</span>
                                                                    </td>
                                                                    <td class="text-sm text-[#4B5563] p-4"><span
                                                                            class="contact_age_{{ $cid }}">{{ $cage }}</span></td>
                                                                    <td class="text-sm text-[#4B5563] p-4"><span
                                                                            class="contact_weight_{{ $cid }}">{{ $cweight }}</span></td>
                                                                @else
                                                                    <td class="text-sm text-[#4B5563] p-4">
                                                                        <span class="edit-contact-pos" title="Edit Contact"
                                                                            id="{{ $bt->id }}" data-id="{{ ($a + 1) }}" data-contact-id="">
                                                                            <i class="fas fa-pencil-alt pl-1"></i>
                                                                        </span>&nbsp;&nbsp;
                                                                        <span class="contact_name_" id="cont_name_{{ ($a + 1) }}">-</span>
                                                                    </td>
                                                                    <td class="text-sm text-[#4B5563] p-4"><span class="contact_age_"
                                                                            id="cont_age_{{ ($a + 1) }}">-</span></td>
                                                                    <td class="text-sm text-[#4B5563] p-4"><span class="contact_weight_"
                                                                            id="cont_weight_{{ ($a + 1) }}">-</span></td>
                                                                @endif
                                                            @else
                                                                <td class="text-sm text-[#4B5563] p-4 flex items-center">
                                                                    {{ ($isGroupBooking == '1') ? 'Person #' . ($a + 1) : $bt->rate_for . ' #' . ($a + 1) }}
                                                                    @if($isPendingPax)
                                                                        <span class="ml-1 text-xs font-semibold text-amber-700 bg-amber-100 border border-amber-300 rounded px-1.5 py-0.5">Unpaid</span>
                                                                    @endif
                                                                    @if(hasSetting('CUSTOM_FIELDS') && session('userType') != 'Affiliate' && $packageHasPaxAddons)
                                                                        @php
                                                                            $hasPaxAddons = in_array($a, $bt->pax_addons_indexes ?? []);
                                                                        @endphp
                                                                        @if ($hasPaxAddons)
                                                                            {{-- View existing addons --}}
                                                                            <span class="pax-addons-preview individual-pax-addons cursor-pointer ml-2" data-rate-group-id="{{ $bt->tour_package_rate_id }}" data-pax-id="{{ $bt->id }}" data-pax-index="{{ $a }}" data-has-addons="true">
                                                                                <i class="fas fa-plus-circle text-green-600 text-lg" title="View addons"></i>
                                                                            </span>
                                                                        @else
                                                                            {{-- Add new addons --}}
                                                                            <span class="pax-addons-preview individual-pax-addons cursor-pointer ml-2" data-rate-group-id="{{ $bt->tour_package_rate_id }}" data-pax-id="{{ $bt->id }}" data-pax-index="{{ $a }}" data-has-addons="false">
                                                                                <i class="fas fa-plus-circle text-gray-400 text-lg hover:text-red-700" title="Add addons"></i>
                                                                            </span>
                                                                        @endif
                                                                    @endif
                                                                </td>
                                                            @endif
                                                            @if($bookingM->is_check_in)
                                                                @if($bookingM->bundle_package == 0)
                                                                    @if($bookingM->checkin_type == 1)
                                                                        @if($requestPermissions['Ticket Redeem'] == "Yes")
                                                                            @include('users.common.partials.booking.tickets._tickets_pax')
                                                                        @endif
                                                                    @else
                                                                        @if($requestPermissions['Check-ins'] == "Yes")
                                                                            @include('users.common.partials.booking.checkins._checkins_pax')
                                                                        @endif
                                                                    @endif
                                                                @else
                                                                    @if($requestPermissions['Ticket Redeem'] == "Yes")
                                                                        @include('users.common.partials.booking.bundle_package._checkins_pax')
                                                                    @endif
                                                                @endif
                                                            @endif

                                                            @if($bookingM->package_has_waiver)
                                                                <td class="text-sm text-[#4B5563] p-4">
                                                                    @if (isset($bookingM->liability_waiver_signing[$touristNum]))
                                                                        <a href="{{ route(prefixedRouteName($routePrefix . 'booking.liability-waiver-form'), ['bookingId' => $bookingM->id, 'touristNum' => $touristNum, 'isAdult' => $isAdult]) }}"
                                                                            class="btn btn-outline-secondary px-4 py-1 rounded-full border border-[#E2E8F0] text-xs text-[#020817] bg-white hover:bg-[#E2E8F0]"
                                                                            {!! request('modal') ? 'target="_blank"' : '' !!}
                                                                            style="background-color: #19875430; border-color:#035a32;"
                                                                            id="waiverbtn">Waiver</a>
                                                                    @else
                                                                        <a href="{{ route(prefixedRouteName($routePrefix . 'booking.liability-waiver-form'), ['bookingId' => $bookingM->id, 'touristNum' => $touristNum, 'isAdult' => $isAdult]) }}"
                                                                            class="btn btn-outline-secondary px-4 py-1 rounded-full border border-[#E2E8F0] text-xs text-[#020817] bg-white hover:bg-[#E2E8F0]"
                                                                            {!! request('modal') ? 'target="_blank"' : '' !!}>Waiver</a>
                                                                    @endif
                                                                </td>
                                                            @endif

                                                            @if ($isGroupBooking != 1)
                                                                <td class="text-sm text-[#4B5563] p-4">{{ moneyFormat($bt->rate) }}</td>
                                                                @if($bookingM->package_has_permit)
                                                                    <td class="text-sm text-[#4B5563] p-4">
                                                                        @if (($permit_fee) == '8.00' && (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "N"))
                                                                            {{ moneyFormat(sprintf('%.2f', round(8.00))) }}
                                                                        @elseif (isset($refund_permitfee_status[$a]) && !empty($refund_permitfee_status[$a]->is_pax_added) && isset($refund_permitfee_status[$a]->permit_fee_override))
                                                                            {{ moneyFormat(sprintf('%.2f', round($refund_permitfee_status[$a]->permit_fee_override))) }}
                                                                        @elseif ((($permit_fee) == '0.00' || (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y")))
                                                                            @php
                                                                                // Show effective permit fee after refund
                                                                                $effectivePermitFee = 0.00;
                                                                                if (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y") {
                                                                                    if (isset($refund_permitfee_status[$a]->new_permit_fee)) {
                                                                                        $effectivePermitFee = $refund_permitfee_status[$a]->new_permit_fee;
                                                                                    }
                                                                                }
                                                                            @endphp
                                                                            {{ moneyFormat(sprintf('%.2f', round($effectivePermitFee))) }}
                                                                        @else
                                                                            {{ moneyFormat(sprintf('%.2f', round($bt->permit_fee))) }}
                                                                        @endif
                                                                    </td>
                                                                @endif
                                                                @if($bookingM->is_partner_booking)
                                                                    <td class="text-sm text-[#4B5563] p-4">
                                                                        {{ moneyFormat(sprintf('%.2f', $bt->partner_fees_amount)) }}
                                                                    </td>
                                                                @endif
                                                                <td class="text-sm text-[#4B5563] p-4">
                                                                    {{ moneyFormat($bt->additional_charge) }}
                                                                </td>
                                                                <td
                                                                    class="text-end text-sm text-[#4B5563] p-4 border-l border-[#E2E8F0]">
                                                                    {{ moneyFormat(sprintf('%.2f', round($totalamtafterpermitfeesrefunded, 2))) }}
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endfor
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="bg-[#F9FAFB] rounded-md px-4 py-3 mt-3">
                                        <div class="grand-total flex flex-wrap -mx-3">
                                            <div class="tsub flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Ticket Cost:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    @if ($totalTicketCost >= 0)
                                                        {{ moneyFormat(sprintf('%.2f', $totalTicketCost)) }}
                                                    @else
                                                        {{ moneyFormat(sprintf('%.2f', $bookingM->total)) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="discount flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Discount:</div>
                                                <div class="w-1/2 text-sm text-right text-[#FF0000]">-
                                                    @php
                                                        if ($bookingM->discount2_percentage != 0) {
                                                            echo '(' . str_replace('.00', '', $bookingM->discount2_percentage) . '%) ';
                                                        }
                                                        echo moneyFormat($bookingM->discount2_value);
                                                    @endphp
                                                </div>
                                            </div>
                                            <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Subtotal:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    @if ($totalTicketCost - $bookingM->discount2_value >= 0)
                                                        {{ moneyFormat($totalTicketCost - $bookingM->discount2_value) }}
                                                    @else
                                                        {{ moneyFormat(sprintf('%.2f', $bookingM->total)) }}
                                                    @endif
                                                </div>
                                            </div>
                                            @if ($customChargePrice['custom_charge_total'] > 0)
                                                <div
                                                    class="tcustomcharge flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                    <div class="w-1/2 text-sm text-[#020817]">Custom Charge:</div>
                                                    <div class="w-1/2 text-sm text-[#020817] text-right">
                                                        {{ moneyFormat($customChargePrice['custom_charge_total']) }}
                                                    </div>
                                                </div>
                                            @endif
                                            {{-- Gross, like Ticket Cost/Booking Fees/Booking Total right below (all keyed off the
                                            never-decremented-by-refund figures) — addons_final_price nets out any add-on refund,
                                            so a cancelled-and-refunded booking hid this line entirely (falsy $0) even though
                                            Booking Total below it still bakes in the full addons_gross_total, leaving the receipt
                                            with no visible breakdown for the gap between Ticket Cost and Booking Total. --}}
                                            @if ($pricedAddons['addons_gross_price'] || $payLaterAddons['addons_price'])
                                                <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                    <div class="w-1/2 text-sm text-[#020817]">Add-ons:</div>
                                                    <div class="w-1/2 text-sm text-[#020817] text-right">
                                                        {{ $pricedAddons['addons_gross_price'] ? moneyFormat($pricedAddons['addons_gross_price']) : moneyFormat($payLaterAddons['addons_price']) }}
                                                    </div>
                                                </div>
                                            @endif
                                            @if($tipAmounts['tip_amount'] > 0)
                                            <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Tip:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">{{ moneyFormat($tipAmounts['tip_amount']) }}</div>
                                            </div>
									        @endif	
                                            <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Booking Fees:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    {{-- Both figures here must be the gross, never-decremented-by-split value — matching
                                                    booking_total's own convention. addons_gross_fee already is (never nets out refunds).
                                                    service_commission is NOT (Split Booking decrements it directly, unlike booking_total),
                                                    so grossServiceCommission adds back any split-reallocated amount for display only — see
                                                    BookingService::fetchSplitReallocatedTourFee(). Not addons_final_fee, which nets out any
                                                    add-on refunds. --}}
                                                    {{ moneyFormat($grossServiceCommission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_gross_fee'] + $tipAmounts['tip_fees']) }}
                                                </div>
                                            </div>
                                            @if ((!empty($trans) && $transP->payment_type == 1) || (!empty($trans) && ($transP->payment_type == 0 || $transP->payment_type == '') && $paymentTransCount > 1) || !empty($paymentLinkGenerated))
                                                <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                    <div class="w-1/2 text-sm text-[#020817]">Booking Total:</div>
                                                    <div class="w-1/2 text-sm text-[#020817] text-right">
                                                        {{-- booking_total is the gross tour-side total ever charged (never decremented on
                                                        refund by design), so add-ons must use the matching gross figure here too — not
                                                        addons_payment_total, which nets out any add-on refunds and would understate this
                                                        line by exactly the refunded amount. --}}
                                                        {{ moneyFormat($bookingM->booking_total + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total'] + $tipAmounts['tip_total']) }}
                                                    </div>
                                                </div>
                                            @elseif (!empty($checkAmount))
                                                <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                    <div class="w-1/2 text-sm text-[#020817]">Booking Total:</div>
                                                    <div class="w-1/2 text-sm text-[#020817] text-right">
                                                        {{ moneyFormat(sprintf('%.2f', $checkAmount->amount + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total'] + $tipAmounts['tip_total'] + $totalDiscountPayment)) }}
                                                    </div>
                                                </div>
                                            @else
                                                <div class="tbooking flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                    <div class="w-1/2 text-sm text-[#020817]">Total Cost:</div>
                                                    <div class="w-1/2 text-sm text-[#020817] text-right">
                                                        {{ moneyFormat(sprintf('%.2f', $bookingM->total + $customChargePrice['custom_charge_total'] + $pricedAddons['addons_payment_total'] + $payLaterAddons['addons_price'] + $tipAmounts['tip_amount'])) }}
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Refund Amount:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    {{ moneyFormat(sprintf('%.2f', $amount)) }}
                                                </div>
                                            </div>
                                            <div class="tbooking flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Payment Total:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    {{ moneyFormat(sprintf('%.2f', $paidF)) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(!empty($pendingRemainingLink))
                        <div class="flex flex-wrap -mx-3">
                            <div class="w-full max-w-full px-3">
                                <div class="px-4 pb-2">
                                    <div class="bg-amber-50 border border-amber-300 rounded-md px-4 py-3">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span class="text-sm font-semibold text-amber-700">Pending Payment Link</span>
                                                <button type="button"
                                                    class="btn-remaining-items-info text-[#264f73] hover:text-[#1a3a56] ml-1"
                                                    data-booking-id="{{ $bookingM->id }}"
                                                    data-url="{{ route(prefixedRouteName($routePrefix . 'booking.get-payment-link-items'), ['bookingId' => $bookingM->id]) }}"
                                                    title="View pending items breakdown">
                                                    <i class="fa fa-info-circle"></i>
                                                </button>
                                            </div>
                                            <span class="text-sm font-bold text-amber-700">{{ moneyFormat($pendingRemainingLink->amount) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Submitted Student Waivers — only shown when at least one entry came from the school waiver link --}}
    @if($bookingM->package_has_waiver && $hasSchoolWaiverEntries)
    <section class="px-6 py-4">
        <div class="w-full container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-full max-w-full">
                    <div class="flex flex-wrap detail-wrp w-full mb-8">
                        <div class="border border-[#E5E7EB] rounded-lg overflow-hidden w-full">
                            <div class="flex items-center justify-between px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB]">
                                <div>
                                    <h3 class="text-sm font-semibold text-[#020817]">Submitted Student Waivers</h3>
                                    <p class="text-xs text-[#6B7280] mt-0.5">{{ count($schoolWaiverEntries) }} waiver(s) received for this booking</p>
                                </div>
                                <span class="text-xs font-bold text-[#15803D] bg-[#DCFCE7] rounded-full px-3 py-1">
                                    {{ count($schoolWaiverEntries) }} SIGNED
                                </span>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[#E5E7EB]">
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">#</th>
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">STUDENT NAME</th>
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">AGE</th>
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">PARENT / GUARDIAN EMAIL</th>
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">SUBMITTED</th>
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">REF</th>
                                            <th class="text-xs font-semibold text-[#6B7280] px-4 py-3 text-left">SOURCE</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($schoolWaiverEntries as $idx => $entry)
                                        @php $isSchoolWaiver = isset($entry[4]); @endphp
                                        <tr class="border-b border-[#F3F4F6] hover:bg-[#F9FAFB]">
                                            <td class="px-4 py-3 text-[#6B7280]">{{ $idx + 1 }}</td>
                                            <td class="px-4 py-3 font-medium text-[#020817]">{{ $entry[0] ?? '-' }}</td>
                                            <td class="px-4 py-3 text-[#374151]">{{ $entry[1] ?? '-' }}</td>
                                            <td class="px-4 py-3">
                                                @if($isSchoolWaiver && !empty($entry[3]))
                                                    <a href="mailto:{{ $entry[3] }}" class="text-[#2563EB] hover:underline">{{ $entry[3] }}</a>
                                                @else
                                                    <span class="text-[#9CA3AF]">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-[#374151]">
                                                @if(!empty($entry[2]))
                                                    {{ date('d M Y g:i A', strtotime($entry[2])) }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($isSchoolWaiver)
                                                    <span class="font-mono text-xs bg-[#F3F4F6] text-[#374151] px-2 py-0.5 rounded">{{ $entry[4] }}</span>
                                                @else
                                                    <span class="text-[#9CA3AF]">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($isSchoolWaiver)
                                                    <span class="text-xs font-semibold bg-[#DBEAFE] text-[#1D4ED8] px-2 py-0.5 rounded-full">School Link</span>
                                                @else
                                                    <span class="text-xs font-semibold bg-[#F3F4F6] text-[#6B7280] px-2 py-0.5 rounded-full">Direct</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    <section class="px-6 py-4">
        <div class="w-full container">
            <div class="flex flex-wrap -mx-3">
                <div class="w-full max-w-full">
                    @if(hasSetting('CUSTOM_FIELDS') && session('userType') != 'Affiliate' && (empty($trans) || (!empty($trans) && $trans->payment_type == 0)) && $packageHasGeneralAddons)
                        <div id="booking-addons-wrapper" class="flex flex-wrap detail-wrp w-full mb-8">
                            <div class="border border-[#E5E7EB] rounded-lg overflow-hidden w-full">
                                <h3 class="col-form-label block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full"
                                    id="booking-addons-section-title"></h3>
                                <div class="p-4">
                                    <div id="booking-addons-section" style="max-height: 450px; overflow-y:auto;"></div>
                                    <div id="booking-addons-form-section" class="booking-addons-form-section"></div>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Old Add-ons Section (for legacy bookings - Read Only) --}}
                    @if(hasSetting('CUSTOM_FIELDS') && session('userType') != 'Affiliate' && isset($hasOldAddons) && $hasOldAddons)
                        <div id="booking-addons-wrap" class="flex flex-wrap detail-wrp w-full mb-8">
                            <div class="border border-[#E5E7EB] rounded-lg overflow-hidden w-full">
                                <h3 class="col-form-label block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full"
                                    id="booking-addons-title"></h3>
                                <div class="p-4">
                                    <div id="booking-addons-msg" class="mb-3"></div>
                                    <div id="booking-addons" class="overflow-y-auto" style="max-height: 450px;"></div>
                                    <div id="booking-addons-form-view"></div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(count($customChargeList) > 0)
                        <div class="border border-[#E5E7EB] rounded-lg overflow-hidden mb-8" id="cc_section">
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full max-w-full px-3">
                                    <div id="booking-custom-charge-wrap" class="row detail-wrp">
                                        <h3 class="w-full px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label"
                                            id="booking-custom-charge-title">
                                            Custom Charges
                                            <x-cost-fees-total-chips
                                                :cost="$customChargePrice['custom_charge_total']"
                                                :fees="$customChargePrice['custom_charge_fees']"
                                                :total="$customChargePrice['custom_charge_final_total']"
                                            />
                                        </h3>
                                        <div class="px-4 py-3">
                                            <div class="max-h-60 overflow-y-auto border border-[#E2E8F0] rounded-lg"
                                                id="booking-custom-charge">
                                                <table id="custom-charge-table"
                                                    class="table table-sm table-stripe table-hover w-full text-left">
                                                    <thead>
                                                        <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                                                            <th
                                                                class="refund-custom-charge-heading text-xs font-medium text-[#374151] p-4">
                                                            </th>
                                                            <th class="cc-heading text-xs font-medium text-[#374151] p-4">Value
                                                            </th>
                                                            <th class="cc-heading text-xs font-medium text-[#374151] p-4">
                                                                Taxable</th>
                                                            <th class="cc-reason text-xs font-medium text-[#374151] p-4">Reason
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    @foreach($customChargeList as $customCharge)
                                                        @php $isUnpaidCharge = $customCharge->status === 'Open'; @endphp
                                                        <tbody>
                                                            <tr class="border-t border-[#E2E8F0]{{ $isUnpaidCharge ? ' bg-amber-50' : ' hover:bg-[#F0FDF4]' }}">
                                                                @if($isCustomCharge == 1)
                                                                    <td class="refund-custom-charge text-sm text-[#4B5563] p-4">
                                                                        <form method="post"
                                                                            action="{{ route(prefixedRouteName($routePrefix . 'booking.refund-custom-charge'), [$bookingM->id]) }}"
                                                                            id="custom-form">
                                                                            @csrf
                                                                            <p><strong></strong></p>
                                                                            <strong>
                                                                                <input type="hidden" name="tour_package_id"
                                                                                    id="custom_tour_package_id"
                                                                                    value="{{ $bookingM->tour_package_id }}">
                                                                                <input type="hidden" name="tour_booking_id"
                                                                                    id="custom_tour_booking_id"
                                                                                    value="{{ $bookingM->id }}">
                                                                                <div>
                                                                                    <input
                                                                                        class="form-check-input custom-charge-refund shadow-none border border-[#E2E8F0] rounded-[6px] px-[15px] py-[6px] h-[20px] w-[20px] text-[14px] text-[#020817] outline-none"
                                                                                        type="checkbox" name="custom_charge_ids[]"
                                                                                        id="custom_charge_id_{{ $customCharge->custom_charge_id }}"
                                                                                        data-id="{{ $customCharge->custom_charge_id }}"
                                                                                        data-status="{{ $customCharge->status }}"
                                                                                        value="{{ $customCharge->custom_charge_id }}">
                                                                                </div>
                                                                            </strong>
                                                                        </form>
                                                                    </td>
                                                                @else
                                                                    <td class="refund-custom-charge text-sm text-[#4B5563] p-4"></td>
                                                                @endif
                                                                @if(!empty($trans))
                                                                    @php
                                                                        $customChargeAmount = $customCharge->amount + $customCharge->fees;
                                                                    @endphp
                                                                @else
                                                                    @php
                                                                        $customChargeAmount = $customCharge->amount;
                                                                    @endphp
                                                                @endif
                                                                <td class="cc-child text-sm text-[#4B5563] p-4">
                                                                    {{ moneyFormat($customChargeAmount) }}
                                                                    @if($isUnpaidCharge)
                                                                        <span class="ml-1 text-xs font-semibold text-amber-700 bg-amber-100 border border-amber-300 rounded px-1.5 py-0.5">Unpaid</span>
                                                                    @endif
                                                                </td>
                                                                <td class="cc-child text-sm text-[#4B5563] p-4">
                                                                    {{ ($customCharge->taxable == 1) ? 'Yes' : 'No' }}
                                                                </td>
                                                                <td class="cc-reason text-sm text-[#4B5563] p-4">
                                                                    {{ $customCharge->description }}
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    @endforeach
                                                </table>
                                            </div>
                                            @if($managerRequestEnable['Refund Custom Charge'] == 'Yes')
                                                <div class="text-right">
                                                    <button
                                                        class="edit-custom-charge-form btn btn-sm btn-delete refund-cc mt-4 ml-auto bg-[#264F73] hover:bg-white border border-[#264F73] text-white hover:text-[#264F73] text-[12px] font-semibold p-2 rounded-[6px] cursor-pointer transition-all duration-300 ease-in-out">
                                                        <i class="fas fa-times pl-1"></i> Refund
                                                    </button>
                                                </div>
                                            @endif
                                            <!-- <div id="booking-custom-charge-form-view"></div> -->
                                            <!-- <div id="booking-custom-charge-modal"></div> -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="flex flex-wrap -mx-3">
                        <div class="w-full md:w-1/2 max-[767px]:mb-6 max-w-full px-3">
                            <div class="border border-[#E5E7EB] rounded-lg overflow-hidden agent-detail-wrp note-compose-card">
                                <div class="px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full">Add a note</div>

                                <form id="noteForm" method="post"
                                    action="{{ route(prefixedRouteName($routePrefix . 'booking.save-note'), ['bookingId' => $bookingM->id]) }}"
                                    onsubmit="return submitNoteWithAttachments(this)">
                                    @csrf

                                    <div class="p-4 note-compose-body">
                                        <textarea name="note" id="noteTextarea"
                                            class="note-compose-textarea agent-notes form-custom-input"
                                            placeholder="Write your note here…"></textarea>

                                        <input type="file" id="internalFileInput" style="display:none"
                                            accept="image/*,application/pdf"
                                            onchange="noteHandleAttach(this)">

                                        <div class="note-compose-attach-zone" id="noteAttachZone"
                                            onclick="document.getElementById('internalFileInput').click()">
                                            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32"/>
                                            </svg>
                                            <span class="note-compose-attach-label">Attach a file</span>
                                            <span class="note-compose-attach-hint">Image or PDF only</span>
                                        </div>

                                        <p class="note-compose-file-note">Please only upload image or PDF files.</p>
                                        <div id="noteAttachPreview"></div>
                                        <div id="noteUploadProgressWrap" style="display:none;width:100%;margin-top:8px;">
                                            <span style="font-size:11px;color:#374151;font-weight:600;display:block;margin-bottom:4px;">Uploading...</span>
                                            <div style="width:100%;background:#e5e7eb;border-radius:999px;height:8px;overflow:hidden;">
                                                <div id="noteUploadProgressBar" style="height:100%;width:0%;background:#1d4ed8;border-radius:999px;transition:width 0.2s ease;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="note-compose-footer">
                                        <button type="submit" class="note-compose-submit">
                                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20 6L9 17l-5-5"/>
                                            </svg>
                                            Add Note
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <script>
                            var noteFile = null;

                            function noteHandleAttach(input) {
                                noteFile = input.files[0] || null;
                                input.value = '';
                                noteRenderPreviews();
                            }

                            function noteRenderPreviews() {
                                var preview = document.getElementById('noteAttachPreview');
                                var zone    = document.getElementById('noteAttachZone');
                                if (!preview) return;
                                if (!noteFile) {
                                    preview.innerHTML = '';
                                    if (zone) zone.style.display = '';
                                    return;
                                }
                                if (zone) zone.style.display = 'none';
                                var icon = noteFile.type.startsWith('image/')
                                    ? '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>'
                                    : '📄';
                                preview.innerHTML = '<div class="attach-chip" onclick="noteRemoveAttach()">'
                                    + icon + ' ' + noteFile.name
                                    + ' <span class="attach-chip-remove">×</span></div>';
                            }

                            function noteRemoveAttach() {
                                noteFile = null;
                                noteRenderPreviews();
                            }

                            function submitNoteWithAttachments(form) {
                                var noteText = document.getElementById('noteTextarea').value.trim();
                                if (!noteText && !noteFile) {
                                    swal('Please enter a note or attach a file.', '', 'warning');
                                    return false;
                                }
                                var fd      = new FormData(form);
                                var hasFile = !!noteFile;
                                if (hasFile) fd.append('attachment', noteFile);

                                showLoading();

                                if (hasFile) {
                                    $('#noteUploadProgressWrap').show();
                                    $('#noteUploadProgressBar').css('width', '0%');
                                }

                                $.ajax({
                                    url: form.action,
                                    type: 'POST',
                                    data: fd,
                                    processData: false,
                                    contentType: false,
                                    headers: { Accept: 'application/json' },
                                    xhr: function () {
                                        var xhr = new XMLHttpRequest();
                                        if (hasFile) {
                                            xhr.upload.onprogress = function (e) {
                                                if (e.lengthComputable) {
                                                    var pct = Math.min(Math.round((e.loaded / e.total) * 100), 90);
                                                    $('#noteUploadProgressBar').css('width', pct + '%');
                                                }
                                            };
                                        }
                                        return xhr;
                                    },
                                }).done(function(data) {
                                    if (hasFile) {
                                        $('#noteUploadProgressBar').css('width', '100%');
                                    }
                                    hideLoading();
                                    if (typeof noteBookedByUpdated === 'function') noteBookedByUpdated(form, data);
                                    form.reset();
                                    noteFile = null;
                                    noteRenderPreviews();
                                    $('#noteUploadProgressWrap').hide();
                                }).fail(function (xhr) {
                                    if (hasFile) $('#noteUploadProgressWrap').hide();
                                    hideLoading();
                                    if (xhr.status === 419) {
                                        swal('Session Expired', 'Your session has expired. Please refresh the page and try again.', 'warning');
                                    } else {
                                        swal('Error', 'Failed to save the note. Please try again.', 'error');
                                    }
                                });
                                return false;
                            }
                        </script>

                        <div class="w-full md:w-1/2 max-w-full px-3 agent-detail-wrp">
                            <div class="border border-[#E5E7EB] rounded-lg overflow-hidden">
                                <form method="post"
                                    action="{{ route(prefixedRouteName($routePrefix . 'booking.save-comments'), ['bookingId' => $bookingM->id]) }}"
                                    onsubmit="return submitForm(this)" data-call-on-success="showCommentsInHistory">
                                    <div class="form-group">
                                        <label for="comments"
                                            class="col-form-label block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full">Comment
                                            from tourist:</label>
                                        <div class="p-4">
                                            <textarea name="comments" id="comments"
                                                class="agent-notes form-custom-input min-h-16"
                                                placeholder="Any additional comment or request from tourist"
                                                required>{{ $bookingM->comments }}</textarea>
                                        </div>
                                    </div>

                                    <div class="px-4 pb-4">
                                        <button type="submit" class="themed-btn short-btn btn-outline-small">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>


                    @if ($bookingM->is_affiliate == "Yes")
                        <div class="flex flex-wrap -mx-3 mt-8">
                            <div class="w-full md:w-1/2 max-w-full px-3">
                                <div class="border border-[#E5E7EB] rounded-lg overflow-hidden">
                                    <form method="post"
                                        action="{{ route(prefixedRouteName($routePrefix . 'booking.save-reference-booking'), ['bookingId' => $bookingM->id]) }}"
                                        onsubmit="return submitForm(this)" data-call-on-success="showReferencesInHistory">
                                        <div class="form-group">
                                            <label for="reference_booking"
                                                class="form-label block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full">Reference
                                                Booking:</label>
                                            <div class="input-group mb-1 p-4">
                                                <input name="reference_booking" value="{{ $bookingM->reference_booking }}"
                                                    id="reference_booking" class="form-control form-custom-input min-h-16"
                                                    placeholder="Please enter reference booking number" />
                                                <button type="submit"
                                                    class="themed-btn short-btn mt-4 btn-outline-small">Update</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form method="post" id="form-cancel-booking"
                        action="{{ route(prefixedRouteName($routePrefix . 'booking.cancel'), ['bookingId' => $bookingM->id]) . (request('modal') ? '?modal=1' : '') }}"
                        onsubmit="return confirmAndSubmitForm(this)">
                        @csrf
                        <input type="hidden" name="cancel_booking" value="1">
                    </form>

                    <form method="post" id="form-refund-booking"
                        action="{{ route(prefixedRouteName($routePrefix . 'booking.refund'), ['bookingId' => $bookingM->id]) . (request('modal') ? '?modal=1' : '') }}"
                        onsubmit="return confirmAndSubmitForm(this)">
                        @csrf
                        <input type="hidden" name="refund_booking" value="1">
                    </form>

                    <div class="overflow-y-auto border border-[#E2E8F0] rounded-lg mt-8">
                        <table class="booking-history table table-striped w-full d-none">
                            <tbody id="booking-history">
                                {{-- when updating code here, code in views > customer > tour-booking-info.blade.php may
                                also need to be updated --}}
                               

                                <tr class="border-b border-[#E5E7EB]">
                                    <td class="book-hist-d-t text-xs text-[#6B7280] p-2 whitespace-nowrap">
                                        {{ formatDateTime($bookingM->created_at) }}
                                    </td>
                                    <td class="book-hist-d-t text-xs text-[#6B7280] p-2">
                                        <span
                                            class="inline-block text-xs font-semibold text-[#264F73] border border-[#E2E8F0] rounded-full px-3 py-1.5 whitespace-nowrap">
                                            @if (count($comboBookingsData))
                                                @if (!empty($trans))
                                                    Combo Booked
                                                @elseif (!empty($transF))
                                                    Combo Failed
                                                @else
                                                    Combo Pending
                                                @endif
                                            @else
                                                @if (!empty($trans))
                                                    Booked
                                                @elseif (!empty($transF))
                                                    Failed
                                                @else
                                                    Pending
                                                @endif
                                            @endif
                                        </span>
                                    </td>
                                    <td class="text-sm text-[#374151] p-2">
                                        @if (!empty($transP))
                                            @if ($transP->request_from == \App\Constants\Constants::FRONTEND)
                                                <b>Booked From Frontend</b>
                                            @elseif ($transP->request_from == strtoupper($getOTAChannelName))
    							                <b>Booked From {{ $getOTAChannelName }}</b>
                                            @elseif ($transP->request_from == \App\Constants\Constants::GETYOURGUIDE)
    							                <b>Booked From GetYourGuide</b>
                                            @elseif ($transP->request_from == \App\Constants\Constants::TRIPADVISOR)
    							                <b>Booked From TripAdvisor</b>
                                            @else
                                                <b>Booked From Backend</b>
                                            @endif
                                        @elseif (!empty($transF))
                                            Failed
                                        @else
                                            Pending
                                        @endif

                                        @if (!empty($details->created_by_with_name))
                                            {{$details->created_by_with_name}}
                                        @endif

                                        @if (count($comboBookingsData))
                                            <br>
                                            @foreach($comboBookingsData as $comboBooking)
                                                <b>
                                                    <a class="ml-auto btn-secondary-small mb-2 mt-1"
                                                        href="{{ route($prefixHotelRoute . 'booking.show', ['booking' => $comboBooking['id']]) }}">{{ $comboBooking['id'] }}
                                                        - {{ $comboBooking['packageName'] }}</a>
                                                </b>
                                                <br>
                                            @endforeach
                                        @endif
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                    </div>
                    @include('users.common.partials.booking._history-notes',['bookingM' => $bookingM, 'comboBookingsData' => $comboBookingsData, 'transP'=>$transP, 'transF' => $transF])
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-hotel" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="modal-title text-lg font-semibold text-[#111827]">Hotel Pickup</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="border border-[#E5E7EB] rounded-lg overflow-hidden">
                        <form method="post"
                            action="{{ route($prefixHotelRoute . 'booking.add-or-update-hotel', ['bookingId' => $bookingM->id]) }}"
                            onsubmit="return validateAddOrEditHotelPickupFormAndSubmit(this)"
                            data-on-success-msg="Pickup hotel updated"
                            data-redirect-on-success="{{ route($prefixHotelRoute . 'booking.show', ['booking' => $bookingM->id]) }}">
                            @csrf
                            <label for="Hotel Pickup"
                                class="form-label block px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full">Select
                                hotel for pickup</label>
                            <div class="p-4 flex items-center">
                                <select class="form-select form-custom-input" id="hotel-pickup" name="hotel_id" required>
                                    <option value="0">Select Hotel</option>
                                    @foreach ($hotels as $hotel)
                                        <option value="{{ $hotel->id }}" {{ $hotel->id == $bookingM->hotel_id ? 'selected' : '' }}>{{ $hotel->name }}</option>
                                    @endforeach
                                </select>


                                <button class="btn btn-main btn-main-sm ml-2 btn-outline-small">Save</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-customer" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Edit Customer Information</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <form method="post" id="editCustomerForm"
                        action="{{ route($prefixForCustomerUpdate . 'customer-update', ['id' => $bookingM->id]) }}"
                        onsubmit="return validateEditCustFormAndSubmit(this)" data-call-on-success="contactInfoByUpdated">
                        @csrf
                        <div class="mb-3">
                            <label for="name" class="form-label form-custom-label">Name</label>
                            <input name="name" id="name" maxlength="80" class="form-control form-custom-input"
                                value="{{ $bookingM->customer }}" onblur="validateCharacters('#name')">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label form-custom-label">Email Address</label>
                            <input type="email" name="email" id="email" maxlength="50"
                                class="form-control form-custom-input" value="{{ $bookingM->cust_email }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="phone_number" class="form-label form-custom-label">Phone Number</label>
                            <input type="tel" name="phone_number" id="phone_number" maxlength="30"
                                class="form-control form-custom-input" value="{{ $bookingM->cust_phone }}"
                                data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#phone_number')">
                        </div>

                        <div><button class="btn btn-main btn-main-sm btn-primary-small">Save</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Cancel Booking Model -->
    @if (!empty($transP) && ($transP->payment_type == 1 || $paymentTransCount > 1))
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="cancelBooking" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Cancel Booking</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4">
                        <form method="post"
                            action="{{ route(prefixedRouteName($routePrefix . 'booking.cancel'), ['bookingId' => $bookingM->id]) }}"
                            id="cancelbookingform">
                            @csrf
                            <input type="hidden" name="cancel_booking" value="1">
                            <input type="hidden" name="request_type" value="Cancel">
                            <div class="flex flex-wrap -mx-3 mb-2">
                                @can('cancel-ticket-refund')
                                    <div class="form-check-wrap w-1/2 px-3">
                                        <div class="form-check text-sm font-normal text-[#020817] flex items-center">
                                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                id="ticket-refund" value="full_refund" checked>
                                            <label class="form-check-label" for="ticket-refund">Ticket Refund</label>
                                        </div>
                                    </div>
                                @endcan
                                @can('cancel-weather-refund')
                                    <div class="form-check-wrap w-1/2 px-3 ">
                                        <div
                                            class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                id="inlineRadio4" value="100_refund">
                                            <label class="form-check-label" for="inlineRadio4">Weather /<br />100% Refund</label>
                                        </div>
                                    </div>
                                @endcan
                                @can('cancel-dont-refund')
                                    @unless($isOctoCancellationEligible)
                                        <div class="form-check-wrap w-1/2 px-3">
                                            <div
                                                class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                                <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                    id="inlineRadio1" value="dont_refund">
                                                <label class="form-check-label" for="inlineRadio1">Don't Refund</label>
                                            </div>
                                        </div>
                                    @endunless
                                @endcan
                            </div>
                            @if (!$isOctoCancellationEligible && $transP->via == 'Card' && $transP->transaction_number == '' && $transP->payment_type == 1)
                                <div class="flex flex-wrap -mx-3 mb-2">
                                    <div class="form-check-wrap w-1/2 px-3">
                                        <label
                                            class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                            <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isCashCancelBooking" value="Cash">
                                            <span class="checkmark"></span>
                                            Cash
                                        </label>
                                    </div>
                                    <div class="form-check-wrap-bg w-1/2 px-3">
                                        <label
                                            class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                            <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isWireCancelBooking"
                                                value="Wire Transfer">
                                            <span class="checkmark"></span>
                                            Check/Wire
                                        </label>
                                    </div>
                                </div>
                            @endif
                            <div class="flex flex-wrap -mx-3 mb-2">
                                <div class="w-full px-3 mb-3">
                                    <label for="comment" class="form-label text-sm font-normal text-[#020817]">Add a
                                        note</label>
                                    <textarea name="comment" id="comment" rows="4" cols="50"
                                        class="cancel-notes form-custom-input min-h-16"
                                        placeholder="Please specify the reason to cancel the booking" required></textarea>
                                </div>
                                @unless($isOctoCancellationEligible)
                                    <div class="w-full px-3 mb-3">
                                        <label class="form-label text-sm font-normal text-[#020817] flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="send_cancel_mail" value="Yes" checked class="w-4 h-4">
                                            Send cancellation email to guest
                                        </label>
                                    </div>
                                @endunless
                                <div class="w-full px-3">
                                    <button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                        id="cancelsubmit">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="cancelBooking" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Cancel Booking</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4">
                        <form method="post"
                            action="{{ route(prefixedRouteName($routePrefix . 'booking.cancel'), ['bookingId' => $bookingM->id])}}"
                            id="cancelbookingform">
                            @csrf
                            <input type="hidden" name="cancel_booking" value="1">
                            <input type="hidden" name="request_type" value="Cancel">
                            <div class="flex flex-wrap -mx-3 mb-2">
                                @can('cancel-ticket-refund')
                                    <div class="form-check-wrap w-1/2 px-3">
                                        <div
                                            class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                id="inlineRadio3" value="full_refund" checked>
                                            <label class="form-check-label" for="inlineRadio3">Ticket Refund</label>
                                        </div>
                                    </div>
                                @endcan
                                @can('cancel-partial-refund')
                                    @unless($isOctoCancellationEligible)
                                        <div class="form-check-wrap w-1/2 px-3">
                                            <div
                                                class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                                <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                    id="inlineRadio2" value="partial_refund">
                                                <label class="form-check-label" for="inlineRadio2">Partial Refund</label>
                                            </div>
                                            <div id="partial_refund_value" hidden>
                                                <div class="flex items-center text-sm font-normal text-[#020817] mt-2">
                                                    <label class="form-check-label mr-2" for="partial_refund_value_field">Amount</label>
                                                    <input type="float" min="1" id="partial_refund_value_field"
                                                        name="partial_refund_value" class="form-custom-input" style="max-width: 85px;">
                                                </div>
                                            </div>
                                        </div>
                                    @endunless
                                @endcan
                            </div>
                            <div class="flex flex-wrap -mx-3 mb-2">
                                @can('cancel-weather-refund')
                                    <div class="form-check-wrap w-1/2 px-3 ">
                                        <div
                                            class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                            <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                id="inlineRadio4" value="100_refund">
                                            <label class="form-check-label" for="inlineRadio4">Weather / 100% Refund</label>
                                        </div>
                                    </div>
                                @endcan
                                @can('cancel-dont-refund')
                                    @unless($isOctoCancellationEligible)
                                        <div class="form-check-wrap w-1/2 px-3">
                                            <div
                                                class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                                <input class="form-check-input w-4 h-4 mr-2" type="radio" name="refund_type"
                                                    id="inlineRadio1" value="dont_refund">
                                                <label class="form-check-label" for="inlineRadio1">Don't Refund</label>
                                            </div>
                                        </div>
                                    @endunless
                                @endcan
                            </div>
                            {{-- @if ($bookingT[0]->tour_operator_id == 1)
                            <div class="flex flex-wrap -mx-3 mb-2">
                                <div class="form-check-wrap w-1/2 px-3">
                                    <div class="form-check form-check-inline flex items-center">
                                        <input class="form-check-input w-4 h-4 mr-2" type="checkbox" name="fareharbor"
                                            id="fareharbor" value="fareharbor">
                                        <label class="form-check-label" for="fareharbor">Fareharbor</label>
                                    </div>
                                </div>
                            </div>
                            @endif --}}
                            @unless($isOctoCancellationEligible)
                                <div class="flex flex-wrap -mx-3 mb-2">
                                    <div class="form-check-wrap w-1/2 px-3">
                                        <label
                                            class="checkbox-wrap get-email-updates mb-0 text-sm font-normal text-[#020817] flex items-center">
                                            <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isCashCancelBooking" value="Cash">
                                            <span class="checkmark"></span>
                                            Cash
                                        </label>
                                    </div>
                                    <div class="form-check-wrap-bg w-1/2 px-3">
                                        <label
                                            class="checkbox-wrap get-email-updates mb-0 text-sm font-normal text-[#020817] flex items-center">
                                            <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isWireCancelBooking"
                                                value="Wire Transfer">
                                            <span class="checkmark"></span>
                                            Check/Wire
                                        </label>
                                    </div>
                                </div>
                            @endunless
                            <div class="flex flex-wrap -mx-3 mb-2">
                                <div class="w-full px-3 mb-3">
                                    <label for="comment" class="form-label text-sm font-normal text-[#020817]">Add a
                                        note</label>
                                    <textarea name="comment" id="comment" rows="4" cols="50"
                                        class="cancel-notes form-custom-input min-h-16"
                                        placeholder="Please specify the reason to cancel the booking" required></textarea>
                                </div>
                                @unless($isOctoCancellationEligible)
                                    <div class="w-full px-3 mb-3">
                                        <label class="form-label text-sm font-normal text-[#020817] flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" name="send_cancel_mail" value="Yes" checked class="w-4 h-4">
                                            Send cancellation email to guest
                                        </label>
                                    </div>
                                @endunless
                                <div class="w-full px-3">
                                    <button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                        id="cancelsubmit">Submit</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="requestBooking" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Manager Request</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <input type="hidden" name="cancel_booking" value="1">
                    <div class="flex mb-4">
                        <select class="form-control form-custom-input" name="manager_id" id="manager_id" required>
                            <option value="select" disabled selected>Select a manager for request</option>
                            @foreach($managers as $manager)
                                <option value="{{$manager->id}}">{{$manager->name}}</option>
                            @endforeach
                        </select>
                    </div>
                    <span class="text-sm font-medium mb-4" id="manager-id-required"></span>
                    <div class="mb-4">
                        <button id="status-button"
                            class="btn btn-sm text-white text-[12px] font-semibold p-2 w-full rounded-[6px] hover:opacity-95 cursor-pointer transition-all duration-300 ease-in-out"
                            hidden></button>
                    </div>
                    <div class="flex flex-wrap -mx-3 mb-4">
                        <div class="w-1/2 px-3">
                            <button type="button"
                                class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small !w-full"
                                id="cancelBookingRequest" onclick=cancelBookingRequest()>Request</button>
                        </div>
                        <div class="w-1/2 px-3">
                            <button type="button"
                                class="btn btn-main btn-main-sm themed-btn short-btn float-end btn-light-small !w-full"
                                id="cancelBookingRequestManual">Manual Override</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="cancelBookingRequestSent" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Manager request has been sent</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <input type="hidden" name="cancel_booking" value="1">
                    <div class="block text-center">
                        <button type="button"
                            class="btn btn-main btn-main-sm themed-btn short-btn inline-block btn-light-small"
                            id="cancelBookingRequestManual">Manual Override</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Manual Override Start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="requestManualId" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Manual Override</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>
                <div class="modal-body text-center p-4">
                    <h4 class="text-lg font-semibold text-[#111827] mb-2">Manager Id</h4>
                    <div class="mb-4">
                        <input class="form-custom-input" type="password" name="manager_id" id="manualManagerId"
                            placeholder="Enter manager id" required>
                    </div>
                    <span class="block text-sm font-medium text-[#FF0000] mb-4" id="manual-manager-id"></span>
                    <div class="text-center">
                        <button type="button"
                            class="btn btn-main btn-main-sm themed-btn short-btn inline-block btn-primary-small"
                            id="request-manual-id">Next</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="requestManualPin" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Manual Override</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body text-center p-4">
                    <h4 class="text-lg font-semibold text-[#111827] mb-2">Manager Pin</h4>
                    <div class="mb-4">
                        <input class="form-custom-input" type="password" name="manager_pin" id="manualManagerPin"
                            placeholder="Enter manager id" required>
                    </div>
                    <span class="block text-sm font-medium text-[#FF0000] mb-4" id="manual-manager-pin"></span>
                    <div class="text-center">
                        <button type="button"
                            class="btn btn-main btn-main-sm themed-btn short-btn inline-block btn-primary-small"
                            id="request-manual-pin">Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Manual Override End -->

    <!-- Switch Package Refund Modal -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-switch-package-refund" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Switch Package Refund</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <form method="post" action="#" id="switchpackagerefundform"
                        onsubmit="return submitRefundForSwitchPackage(this)">
                        @csrf
                        <div class="flex flex-wrap -mx-3">
                            <div class="form-check-wrap w-1/2 px-3 mb-2">
                                <div
                                    class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                    <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                        id="inlineRadioTicketRefundSwitchPackage" value="full_refund" checked>
                                    <label class="form-check-label" for="inlineRadioTicketRefundSwitchPackage">Ticket Refund</label>
                                </div>
                            </div>

                            <div class="form-check-wrap w-1/2 px-3 mb-2">
                                <div
                                    class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                    <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                        id="inlineRadio5" value="partial_refund">
                                    <label class="form-check-label" for="inlineRadio5">Partial Refund</label>
                                </div>
                                <div id="partial_refund_value_switch_package" hidden>
                                    <div class="flex items-center text-sm font-normal text-[#020817] mt-2">
                                        <label class="form-check-label mr-1"
                                            for="partial_refund_value_field_switch_package">Amount</label>
                                        <input type="float" min="1" id="partial_refund_value_field_switch_package"
                                            name="partial_refund_value" class="form-custom-input" style="max-width: 85px;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-check-wrap w-1/2 px-3 mb-2">
                                <div
                                    class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                    <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                        id="inlineRadioFullRefundSwitchPackage" value="100_refund">
                                    <label class="form-check-label" for="inlineRadioFullRefundSwitchPackage">Weather / <br />100% Refund</label>
                                </div>
                            </div>

                            <div class="form-check-wrap w-1/2 px-3 mb-2">
                                <div
                                    class="form-check form-check-inline text-sm font-normal text-[#020817] flex items-center">
                                    <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                        id="inlineRadioDontRefundSwitchPackage" value="dont_refund">
                                    <label class="form-check-label" for="inlineRadioDontRefundSwitchPackage">Don't Refund</label>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-wrap -mx-3 mb-2">
                            <div class="form-check-wrap w-1/2 px-3">
                                <label
                                    class="checkbox-wrap get-email-updates mb-0 text-sm font-normal text-[#020817] flex items-center">
                                    <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isCashSwitchPackage" value="Cash">
                                    <span class="checkmark"></span>
                                    Cash
                                </label>
                            </div>
                            <div class="form-check-wrap-bg w-1/2 px-3">
                                <label
                                    class="checkbox-wrap get-email-updates mb-0 text-sm font-normal text-[#020817] flex items-center">
                                    <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash" id="isWireSwitchPackage"
                                        value="Wire Transfer">
                                    <span class="checkmark"></span>
                                    Check/Wire
                                </label>
                            </div>
                        </div>

                        <div class="block">
                            <button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                id="switchpackagerefundsubmit">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Switch Package Refund Modal -->

    <!-- Refund Booking Modal -->
    @if (!empty($transP) && ($transP->payment_type == 1 || $paymentTransCount > 1) && $transP->via == "Card")
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="refundBooking" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Refund Booking</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4">
                        <form method="post"
                            action="{{ route(prefixedRouteName($routePrefix . 'booking.refund'), ['bookingId' => $bookingM->id])}}"
                            id="refundform">
                            @csrf
                            <input type="hidden" name="refund_booking" value="2">
                            <input type="hidden" name="refund_type" value="partial_refund">
                            <input type="hidden" name="request_type" value="Refund">

                            <div class="flex flex-wrap -mx-3 mb-2">
                                <div class="text-sm font-semibold text-[#111827] w-[56%] px-3">Amount available</div>
                                <div class="text-sm font-semibold text-[#111827] w-[44%] px-3 whitespace-nowrap">Amount to be
                                    refunded</div>
                            </div>
                            @php
                                $k = 0;
                            @endphp
                            @foreach($paymentTransactions as $transAmountValue)
                                @if ($transAmountValue->amount > 0 && $transAmountValue->transaction_number != '' && ($transAmountValue->refund_amount == 0 || (int) $transAmountValue->amount != (int) $transAmountValue->refund_amount) && ($transAmountValue->amount - $transAmountValue->refund_amount > 0))
                                    @php
                                        $k++;
                                    @endphp
                                    <div class="flex flex-wrap -mx-3 mb-2">
                                        <div
                                            class="text-sm font-semibold text-[#111827] w-[56%] px-3 whitespace-nowrap flex items-center">
                                            <input type="checkbox" id="" class="check_transactions_refund w-4 h-4 mr-2"
                                                name="check[{{$k}}]" value="{{$transAmountValue->transaction_number}}">
                                            <label class="form-check-label" style="margin-right: 10px;"
                                                for="partial_refund">Amount</label>
                                            {{ moneyFormat($transAmountValue->amount - $transAmountValue->refund_amount) }} [<i
                                                class="fa fa-info-circle" title="Card Last 4 Digit"></i>
                                            {{ str_pad($transAmountValue->bookingHistory->card_last_4_digit ?? '0', 4, '0', STR_PAD_LEFT) ?? 'N/A' }}]
                                        </div>
                                        <div class="text-sm font-semibold text-[#111827] w-[44%] px-3">
                                            <input type="float" min="1" id="partial_refund" name="partial_refund[{{$k}}]"
                                                class="form-custom-input" required>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                            <div class="mb-2">
                                <label for="comment-1" class="form-label text-sm font-normal text-[#020817]">Add a note</label>
                                <textarea name="comment" id="comment-1" rows="4" cols="50"
                                    class="cancel-notes form-custom-input min-h-16"
                                    placeholder="Please specify the reason to refund" required></textarea>
                            </div>
                            <div class="mb-2 text-center"><button
                                    class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                    id="submitrefund">Submit</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="refundBooking" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Refund Booking</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4">
                        <form method="post"
                            action="{{ route(prefixedRouteName($routePrefix . 'booking.refund'), ['bookingId' => $bookingM->id])}}"
                            id="refundform">
                            @csrf
                            <input type="hidden" name="refund_booking" value="1">
                            <input type="hidden" name="refund_type" value="partial_refund">
                            <input type="hidden" name="request_type" value="Refund">
                            <div class="flex flex-wrap -mx-3 mb-2">
                                <div class="form-check-wrap w-full px-3">
                                    <div class="flex items-center">
                                        <label
                                            class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center min-w-32">
                                            <input type="checkbox" name="refund_type_percentage" id="refund_type_percentage"
                                                value="Percentage" class="w-4 h-4 mr-2"
                                                onclick="calculatePercentagePartialRefund(this)">
                                            <span class="checkmark"></span>
                                            Percentage:
                                        </label>
                                        <input type="number" min="0" step="0.01" max="100" id="percentage_refund_value"
                                            name="percentage_refund_value" onkeyup="calculatePercentagePartialRefund()"
                                            class="form-control form-custom-input w-full" disabled>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3 mb-2">
                                <div class="form-check-wrap w-full px-3">
                                    <div class="flex items-center" id="partial_refund_value">
                                        <label for="partial_refund_value_refund_booking"
                                            class="text-sm font-medium text-[#020817] whitespace-nowrap min-w-32">
                                            Amount:
                                        </label>
                                        <input type="number" min="1" step="0.01" id="partial_refund_value_refund_booking"
                                            name="partial_refund_value" class="form-custom-input w-full" required>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap -mx-3">
                                <div class="form-check-wrap w-1/2 px-3 mb-2">
                                    <label
                                        class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                        <input type="checkbox" name="is_cash" id="isCashRefundBooking" value="Cash" class="w-4 h-4 mr-2">
                                        <span class="checkmark"></span>
                                        Cash
                                    </label>
                                </div>
                                <div class="form-check-wrap-bg w-1/2 px-3 mb-2">
                                    <label
                                        class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                        <input type="checkbox" name="is_cash" id="isWireRefundBooking" value="Wire Transfer"
                                            class="w-4 h-4 mr-2">
                                        <span class="checkmark"></span>
                                        Check/Wire
                                    </label>
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="comment-" class="form-label text-sm font-normal text-[#020817]">Add a note</label>
                                <textarea name="comment" id="comment-" rows="4" cols="50"
                                    class="cancel-notes form-custom-input min-h-16"
                                    placeholder="Please specify the reason to refund" required></textarea>
                            </div>
                            <div class="mb-2 text-center">
                                <button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                    id="submitrefund">Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @include('users.common.partials.booking._update-booking-modal')

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="permitFeesRefund" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Edit Booking</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div id="Partial-delete"
                        class="empty:hidden mb-4 px-4 py-3 bg-[#FEF2F2] border border-[#FECACA] rounded-lg text-sm text-[#991B1B] font-medium leading-relaxed"></div>
                    <div class="flex flex-wrap -mx-3 mb-4">
                        <div class="form-check-wrap w-1/2 px-3">
                            <label
                                class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash_refund" id="is_cash_refund"
                                    value="Cash">
                                <span class="checkmark"></span>
                                Cash
                            </label>
                        </div>
                        <div class="form-check-wrap w-1/2 px-3">
                            <label
                                class="checkbox-wrap get-email-updates text-sm font-normal text-[#020817] flex items-center">
                                <input class="w-4 h-4 mr-2" type="checkbox" name="is_cash_refund" id="is_wire_refund"
                                    value="Wire Transfer">
                                <span class="checkmark"></span>
                                Check/Wire
                            </label>
                        </div>
                    </div>
                    <div class="text-center">
                        <button class="btn btn-main btn-main-sm themed-btn short-btn inline-block mx-auto btn-primary-small"
                            id="button_id_cash_refund">Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="updateBooking" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Update Booking</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    @if (!empty($transP) && ($paymentTransCount > 1) && $transP->via == "Card")
                        @php
                            $k = 0;
                        @endphp
                        @foreach($paymentTransactions as $transAmountValue)
                            @if (($transAmountValue->amount - $transAmountValue->refund_amount > 0) && ($transAmountValue->refund_amount == 0 || (int) $transAmountValue->amount != (int) $transAmountValue->refund_amount) && $transAmountValue->transaction_number != '')
                                @php
                                    $k++;
                                @endphp
                                <div class="mb-2">
                                    <span class="text-sm font-normal text-[#020817]">
                                        <input type="checkbox" id="check_transactions_update{{$transAmountValue->id}}"
                                            class="check_transactions_update ml-2"
                                            name="check_transactions_update{{$transAmountValue->id}}"
                                            value="{{$transAmountValue->transaction_number}}">
                                        <label class="form-check-label" for="partial_refund">Amount:
                                            {{ moneyFormat($transAmountValue->amount - $transAmountValue->refund_amount) }} [<i
                                                class="fa fa-info-circle" title="Card Last 4 Digit"></i>
                                            {{ str_pad($transAmountValue->bookingHistory->card_last_4_digit ?? '0', 4, '0', STR_PAD_LEFT) ?? 'N/A' }}]</label>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    @endif
                    <div class="text-center"><button
                            class="btn btn-main btn-main-sm themed-btn short-btn inline-block mx-auto btn-primary-small"
                            id="update_button">Submit</button></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="splitBookingUpdate" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Split Booking</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <p id="calculated_amount" class="text-base font-semibold text-[#111827]"></p><br>
                    @if (!empty($transP) && ($paymentTransCount > 1) && $transP->via == "Card")
                        @php
                            $k = 0;
                        @endphp
                        @foreach($paymentTransactions as $transAmountValue)
                            @if (($transAmountValue->amount - $transAmountValue->refund_amount > 0) && ($transAmountValue->refund_amount == 0 || (int) $transAmountValue->amount != (int) $transAmountValue->refund_amount) && $transAmountValue->transaction_number != '')
                                @php
                                    $k++;
                                @endphp
                                <div class="mb-2">
                                    <span class="text-sm font-normal text-[#020817]">
                                        <input type="checkbox" id="check_transactions_split_update{{$transAmountValue->id}}"
                                            class="check_transactions_split_update ml-2"
                                            name="check_transactions_split_update{{$transAmountValue->id}}"
                                            value="{{$transAmountValue->transaction_number}}">
                                        <label class="form-check-label" for="partial_refund">Amount:
                                            {{ moneyFormat($transAmountValue->amount - $transAmountValue->refund_amount) }} [<i
                                                class="fa fa-info-circle" title="Card Last 4 Digit"></i>
                                            {{ str_pad($transAmountValue->bookingHistory->card_last_4_digit ?? '0', 4, '0', STR_PAD_LEFT) ?? 'N/A' }}]</label>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    @endif
                    <div class="text-center"><button
                            class="btn btn-main btn-main-sm themed-btn short-btn inline-block mx-auto btn-primary-small"
                            id="split_update_button">Submit</button></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resend Modal open -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-resend-booking" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Resend Booking Information</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <form method="get"
                        action="{{ route(prefixedRouteName($routePrefix . 'booking.ResendEmail'), ['bookingId' => $bookingM->id]) }}"
                        onsubmit="return validateEditCustFormAndSubmit(this)" data-call-on-success="resendBooking">

                        <div class="mb-4">
                            <label for="Mail Type" class="form-label form-custom-label">Type of Mail</label>
                            <select class="form-select form-custom-input" id="" name="mail_type">
                                <option value="1">Tour Booking Acknowledgement Mail</option>
                                @if ($bookingM->statusm === 'Cancelled')
                                    <option value="2">Booking Cancellation Mail</option>
                                @endif
                            </select>
                        </div>
                        <div class="mb-4">
                            <label for="email" class="form-label form-custom-label">Email Address</label>
                            <input type="email" name="email" id="email" maxlength="50"
                                class="form-control form-custom-input" value="{{ $bookingM->cust_email }}" required>
                        </div>
                        <div class="text-center"><button
                                class="btn btn-main btn-main-sm themed-btn short-btn inline-block mx-auto btn-primary-small">Resend</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Sms Message -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-send-sms-message" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Send SMS Message</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>
                <div id="sms-form-modal" class="modal-body">

                </div>
            </div>
        </div>
    </div>

    <!-- Edit Discount Modal Open-->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_edit_discount" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Discount Code</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>
                <div class="modal-body p-4">
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form method="post" id="editdiscountForm"
                                action="{{ route(prefixedRouteName($routePrefix . 'booking.edit-discount')) }}">
                                <input type="hidden" value="{{ $bookingM->id }}" name="bookingID">
                                <input type="hidden" value="{{ (!empty($trans)) ? $trans->via : 'Pay Later' }}"
                                    name="payment_type">
                                <input type="hidden" name="edit_is_applied" id="edit_is_applied"
                                    value="{{ ($bookingM->discount_promo_code != '') ? 1 : 0 }}">
                                <input type="hidden" name="payment_method"
                                    value="{{ (in_array($bookingM->via, array_merge(['Card', 'Cash', 'Invoiced'], $otaPlatforms)) ? $bookingM->via : 'Pay Later') }}">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full px-3">
                                        <div class="tourist-booking-sidebar">
                                            <div class="form-field-wrp discount-code-wrp payment-total-count">

                                                <div class="flex items-center mb-4">
                                                    <label
                                                        class="checkbox-wrap flex items-center get-email-updates text-start text-sm font-normal text-[#020817] mr-2 min-w-32">
                                                        <input class="w-4 h-4 mr-2" type="radio" name="edit_discount_type"
                                                            id="edit_discount_type_promo_code" value="Promo Code"
                                                            onclick="enableEditDiscount(this)"
                                                            @if($bookingM->discount_promo_code != "") checked @endif>
                                                        Promo Code:
                                                        <span class="checkmark"></span>
                                                    </label>
                                                    <div class="flex items-center w-full">
                                                        <div class="discount-code-input w-full diff mr-2">
                                                            <input type="text" @if($bookingM->discount_promo_code != "")
                                                            value="{{ $bookingM->discount_promo_code }}" @else disabled
                                                                @endif name="edit_discount_promo_code"
                                                                id="edit_discount_promo_code"
                                                                class="form-control form-custom-input"
                                                                placeholder="Enter Promo Code" onkeyup="removeDiscount()">
                                                        </div>
                                                        <div>
                                                            <button type="button"
                                                                class="btn-primary btn-color apply-edit-promo-btn btn-primary-small"
                                                                @if($bookingM->discount_promo_code != "")
                                                                style="display:none;" @endif
                                                                onclick="applyEditPromoDiscount()">Apply</button>
                                                            <button type="button"
                                                                class="btn-primary btn-color remove-edit-promo-btn btn-danger-small"
                                                                @if($bookingM->discount_promo_code == "")
                                                                style="display:none;" @endif
                                                                onclick="removePromoDiscount()">Remove</button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="flex items-center mb-4">
                                                    <label
                                                        class="checkbox-wrap flex items-center get-email-updates text-sm font-normal text-[#020817] mr-2 min-w-32">
                                                        <input class="w-4 h-4 mr-2" type="radio" name="edit_discount_type"
                                                            id="edit_discount_type_percentage" value="Percentage"
                                                            onclick="enableEditDiscount(this)"
                                                            @if($bookingM->discount2_percentage > 0 && $bookingM->discount_promo_code == "") checked @endif>
                                                        Percentage:
                                                        <span class="checkmark"></span>
                                                    </label>

                                                    <div class="discount-code-input w-full">
                                                        <input type="number" name="edit_discount_percentage"
                                                            id="edit_discount_percentage"
                                                            @if($bookingM->discount2_percentage > 0 && $bookingM->discount_promo_code == "")
                                                            value="{{ $bookingM->discount2_percentage }}" @else disabled
                                                            @endif class="form-control form-custom-input"
                                                            placeholder="Enter Discount Percentage" min="0" step="0.01">
                                                        <span class="field-icon per-icon"></span>
                                                    </div>
                                                </div>

                                                <div class="flex items-center mb-4">
                                                    <label
                                                        class="checkbox-wrap flex items-center get-email-updates text-sm font-normal text-[#020817] mr-2 min-w-32">
                                                        <input class="w-4 h-4 mr-2" type="radio" name="edit_discount_type"
                                                            id="edit_discount_type_fixed_money" value="Fixed Money"
                                                            onclick="enableEditDiscount(this)"
                                                            @if($bookingM->discount2_value > 0 && $bookingM->discount2_percentage <= 0 && $bookingM->discount_promo_code == "") checked @endif>
                                                        Amount:
                                                        <span class="checkmark"></span>
                                                    </label>
                                                    <div class="discount-code-input w-full">
                                                        <input type="number" name="edit_discount_fixed_money"
                                                            id="edit_discount_fixed_money"
                                                            class="form-control form-custom-input"
                                                            @if($bookingM->discount2_value > 0 && $bookingM->discount2_percentage <= 0 && $bookingM->discount_promo_code == "")
                                                            value="{{ $bookingM->discount2_value }}" @else disabled @endif
                                                            placeholder="Enter Discount Amount" min="0" step="0.01">
                                                        <span class="field-icon"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            <div class="makeaayment-wrap">
                                                <div class="form-group col-12 mb-0 text-center">
                                                    <input type="button" onclick="editDiscount()" id="btn-edit-discount"
                                                        class="btn-make-payment btn-primary-small w-full"
                                                        value="Apply Discount">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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

    <!-- Edit Discount Cash Payment Modal Open-->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_edit_discount_cash_payment" tabindex="-1" role="dialog" aria-modal="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Make Payment Admin</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>
                <div class="modal-body p-4">
                    <div class="payment-booking-sec">
                        <form id="modal-edit-discount-cash-payment" method="post" action="">
                            <div class="tourist-booking-sidebar edit-payment-method">
                                <div class="col-12">
                                    <div class="payment-method">
                                        <div
                                            class="payment-method-title font-semibold text-base text-[#111827] mb-5  max-[1350px]:mb-3">
                                            Payment Method:</div>
                                        <input name="bookingID" id="booking_id_discount_cash_payment" type="text" hidden=""
                                            value="{{$bookingM->id}}">
                                        @can('payment-method-cash')
                                            <div class="form-field-wrp">
                                                <div class="form-group payment-condition-item col-12">
                                                    <label for="pay-with-csh1" class="checkbox-wrap block cursor-pointer">
                                                        <input type="radio" name="payment_method_discount" id="pay-with-csh1"
                                                            class="sr-only peer w-4 h-4 mr-2" value="Cash" checked>
                                                        <div
                                                            class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                            <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                viewBox="0 0 20 20" fill="none"
                                                                xmlns="http://www.w3.org/2000/svg">
                                                                <g clip-path="url(#clip0_2188_2274)">
                                                                    <path
                                                                        d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path
                                                                        d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                    <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round"></path>
                                                                </g>
                                                                <defs>
                                                                    <clipPath id="clip0_2188_2274">
                                                                        <rect width="20" height="20" fill="white"></rect>
                                                                    </clipPath>
                                                                </defs>
                                                            </svg>
                                                            <span
                                                                class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                with Cash</span>
                                                            <span
                                                                class="amount-text total-cash-payment font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat(0) }}</span>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        @endcan
                                        @if(hasSetting('PAYMENT_LINK') && !$isSplitPaymentBooking)
                                            <div class="form-field-wrp mt-2">
                                                <div class="form-group payment-condition-item col-12">
                                                    <label for="pay-with-link-discount-cash" class="checkbox-wrap block cursor-pointer">
                                                        <input type="radio" name="payment_method_discount" id="pay-with-link-discount-cash"
                                                            class="sr-only peer w-4 h-4 mr-2" value="Payment Link">
                                                        <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                            <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M8.333 11.667a4.167 4.167 0 006.246.453l2.5-2.5a4.167 4.167 0 00-5.893-5.893l-1.433 1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M11.667 8.333a4.167 4.167 0 00-6.246-.453l-2.5 2.5a4.167 4.167 0 005.893 5.893l1.426-1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <span class="font-inter font-semibold text-black text-base flex-1">Pay with Link</span>
                                                            <span class="amount-text total-cash-payment font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat(0) }}</span>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full px-3">
                                    <div class="payment-method-form-wrp">
                                        <div class="row align-items-start mt-3">
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md">
                                                <div class="total-payment-text">Total payment</div>
                                                <div class="total-cash-payment">{{ moneyFormat(0.00) }}</div>
                                            </div>
                                            <div class="makeaayment-wrap mt-4">
                                                <div class="form-group col-12 mb-0">
                                                    <input type="button" onclick="updatePayment()"
                                                        id="btn-make-cash-payment"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
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
        </div>
    </div>

    <!-- Edit Discount Card Payment Modal Open-->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_edit_discount_card_payment" tabindex="-1" role="dialog" aria-modal="true"
        data-pending-amount="{{ !empty($pendingPaymentLinkForTotal) ? $pendingPaymentLinkForTotal->amount : 0 }}"
        data-breakdown-url="{{ route(prefixedRouteName($routePrefix . 'booking.get-payment-link-items'), ['bookingId' => $bookingM->id]) }}">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Make Payment Admin</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <div class="payment-booking-sec">
                        <form id="modal-edit-discount-card-payment" method="post" action="">
                            <div class="tourist-booking-sidebar edit-payment-method">
                                <div class="col-12">
                                    <div class="payment-method">
                                        <div
                                            class="payment-method-title font-semibold text-base text-[#111827] mb-5  max-[1350px]:mb-3">
                                            Payment Method:</div>
                                        <input name="bookingID" id="booking_id_discount_card_payment" type="text" hidden=""
                                            value="{{$bookingM->id}}">
                                        <input type="hidden" name="combined_charge_total" id="discount-combined-charge-total" value="0">
                                        @if(hasSetting('PAYMENT_LINK') && !$isSplitPaymentBooking)
                                            <div class="form-field-wrp">
                                                <div class="form-group payment-condition-item col-12">
                                                    <label for="pay-with-link-discount-card" class="checkbox-wrap block cursor-pointer">
                                                        <input type="radio" name="payment_method_discount" id="pay-with-link-discount-card"
                                                            class="sr-only peer w-4 h-4 mr-2" value="Payment Link">
                                                        <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                            <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M8.333 11.667a4.167 4.167 0 006.246.453l2.5-2.5a4.167 4.167 0 00-5.893-5.893l-1.433 1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M11.667 8.333a4.167 4.167 0 00-6.246-.453l-2.5 2.5a4.167 4.167 0 005.893 5.893l1.426-1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                            <span class="font-inter font-semibold text-black text-base flex-1">Pay with Link</span>
                                                            <span class="amount-text total-cash-payment font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        @endif
                                        <div class="form-field-wrp mt-2">
                                            <div class="form-group payment-condition-item col-12">
                                                <label class="checkbox-wrap block cursor-pointer">
                                                    <input type="radio" name="payment_method_discount" id="pay-with-crd"
                                                        class="sr-only peer w-4 h-4 mr-2" value="Card" checked>
                                                    <div
                                                        class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                        <svg class="w-5 h-5 mr-3" width="20" height="20" viewBox="0 0 20 20"
                                                            fill="none" xmlns="http://www.w3.org/2000/svg">
                                                            <path
                                                                d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                            <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                stroke-width="1.5" stroke-linecap="round"
                                                                stroke-linejoin="round" />
                                                        </svg>
                                                        <span
                                                            class="font-inter font-semibold text-black text-base flex-1">Pay
                                                            with Card</span>
                                                        <span
                                                            class="amount-text total-cash-payment font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <div class="payment-method-form-wrp">
                                        <div class="row align-items-start" style="margin-top: 10px">
                                            <!-- Pending payment row — shown by JS when there is an existing unpaid payment link -->
                                            <div id="pending-items-summary-discount" class="hidden mb-1">
                                                <div class="flex items-center justify-between px-2 py-1 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                                    <div class="flex items-center gap-1">
                                                        <span>Pending payment</span>
                                                        <button type="button" id="btn-discount-payment-breakdown-inline"
                                                            class="text-[#264f73] hover:text-[#1a3a56]"
                                                            title="View breakdown">
                                                            <i class="fa fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                    <span id="pending-items-amount-discount" class="font-semibold"></span>
                                                </div>
                                            </div>
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md">
                                                <div class="total-payment-text">Total payment</div>
                                                <div class="total-cash-payment"></div>
                                                </div>
                                            <div id="transaction-container">
                                            </div>
                                            <div id="mutliple-transaction-container" class="mt-4 hidden">
                                                @if (!empty($transP) && ($paymentTransCount > 1) && $transP->via == "Card")
                                                    @foreach($paymentTransactions as $transAmountValue)
                                                        @if (($transAmountValue->amount - $transAmountValue->refund_amount > 0) && ($transAmountValue->refund_amount == 0 || (int) $transAmountValue->amount != (int) $transAmountValue->refund_amount) && $transAmountValue->transaction_number != '')
                                                            <div class="" style="margin-bottom: 10px;">
                                                                <div class="">
                                                                    <div>
                                                                        <span>
                                                                            <input type="checkbox"
                                                                                id="check_multiple_transactions_refund{{$transAmountValue->id}}"
                                                                                class="check_multiple_transactions_refund"
                                                                                name="check_multiple_transactions_refund{{$transAmountValue->id}}"
                                                                                value="{{$transAmountValue->transaction_number}}">
                                                                            <label class="form-check-label mb-1"
                                                                                style="margin-right: 10px;" for="partial_refund">Amount:
                                                                                {{ moneyFormat($transAmountValue->amount - $transAmountValue->refund_amount) }}
                                                                                [<i class="fa fa-info-circle"
                                                                                    title="Card Last 4 Digit"></i>
                                                                                {{ str_pad($transAmountValue->bookingHistory->card_last_4_digit ?? '0', 4, '0', STR_PAD_LEFT) ?? 'N/A' }}]</label>
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                            </div>
                                            <div class="makeaayment-wrap mt-4">
                                                <div class="form-group col-12 mb-0">
                                                    <input type="button" onclick="updateCardPayment()"
                                                        id="btn-make-card-payment"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
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
        </div>
    </div>

    <!-- Affilated Payment Modal Open-->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_affilate" tabindex="-1" role="dialog" aria-hidden="true"
        data-pending-amount="{{ !empty($pendingPaymentLinkForTotal) ? $pendingPaymentLinkForTotal->amount : 0 }}"
        data-breakdown-url="{{ route(prefixedRouteName($routePrefix . 'booking.get-payment-link-items'), ['bookingId' => $bookingM->id]) }}">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]"></h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>
                <div class="modal-body p-4">
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form method="post"
                                action="{{ route(prefixedRouteName($routePrefix . 'booking.payment-new-booking')) }}"
                                onsubmit="return validateAndSubmitForm1(this)" data-call-on-success="affilated payment"
                                id="makePaymentForm">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full px-3">
                                        @if ($bookingM->discount2_value == 0 && (empty($trans) || (!empty($trans)) && $trans->payment_type != 1) && $pricedAddons['addons_final_total'] == 0 && $payLaterAddons['addons_total'] == 0 && userType() != "Affiliate")
                                            <div class="tourist-booking-sidebar">
                                                <div class="form-field-wrp discount-code-wrp payment-total-count">

                                                    <div class="flex items-center mb-4">
                                                        <label
                                                            class="checkbox-wrap flex items-center get-email-updates text-start text-sm font-normal text-[#020817] mr-2 min-w-32">
                                                            <input class="w-4 h-4 mr-2" type="radio" name="discount_type"
                                                                id="discount_type_promo_code" value="Promo Code"
                                                                onclick="enableDiscount(this)">
                                                            Promo Code:
                                                            <span class="checkmark"></span>
                                                        </label>
                                                        <div class="flex items-center w-full">
                                                            <div class="discount-code-input w-full diff mr-2">
                                                                <input type="text" name="discount_promo_code" disabled
                                                                    id="discount_promo_code"
                                                                    class="form-control form-custom-input"
                                                                    placeholder="Enter Promo Code" onkeyup="removeDiscount()">
                                                            </div>

                                                            <input type="hidden" name="is_applied" id="is_applied">

                                                            <div>
                                                                <button type="button"
                                                                    class="btn-primary btn-color apply-promo-btn btn-primary-small"
                                                                    onclick="applyPromoDiscount()">Apply</button>
                                                                <button type="button"
                                                                    class="btn-primary btn-color remove-promo-btn btn-danger-small"
                                                                    onclick="removePromoDiscount()"
                                                                    style="display:none;">Remove</button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center mb-4">
                                                        <label
                                                            class="checkbox-wrap flex items-center get-email-updates text-sm font-normal text-[#020817] mr-2 min-w-32">
                                                            <input class="w-4 h-4 mr-2" type="radio" name="discount_type"
                                                                id="discount_type_percentage" value="Percentage"
                                                                onclick="enableDiscount(this)">
                                                            Percentage:
                                                            <span class="checkmark"></span>
                                                        </label>

                                                        <div class="discount-code-input w-full">
                                                            <input type="number" name="discount_percentage"
                                                                id="discount_percentage" class="form-control form-custom-input"
                                                                placeholder="Enter Discount Percentage" min="0" step="0.01"
                                                                onkeyup="applyDiscount(this)">
                                                            <span class="field-icon per-icon"></span>
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center mb-4">
                                                        <label
                                                            class="checkbox-wrap flex items-center get-email-updates text-sm font-normal text-[#020817] mr-2 min-w-32">
                                                            <input class="w-4 h-4 mr-2" type="radio" name="discount_type"
                                                                id="discount_type_fixed_money" value="Fixed Money"
                                                                onclick="enableDiscount(this)">
                                                            Amount:
                                                            <span class="checkmark"></span>
                                                        </label>
                                                        <div class="discount-code-input w-full">
                                                            <input type="number" name="discount_fixed_money"
                                                                id="discount_fixed_money" class="form-control form-custom-input"
                                                                placeholder="Enter Discount Amount" min="0" step="0.01"
                                                                onkeyup="applyDiscount()">
                                                            <span class="field-icon"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                        @endif
                                        <div class="payment-method">
                                            <span id="validate1" style="color: red"></span>
                                            <div class="payment-method-title text-lg font-semibold text-[#111827]">Payment
                                                Method:</div>
                                            <input name="bookingID" id="booking-id-affiliate-payment" hidden value="{{$bookingM->id}}">
                                            <input name="remaining_link_id" type="hidden"
                                                value="{{ !empty($pendingRemainingLink) ? $pendingRemainingLink->id : '' }}">
                                            <input id="addons-price" hidden value="{{ $payLaterAddons['addons_price'] }}">
                                            <input id="addons-fee" hidden value="{{ $payLaterAddons['addons_fee'] }}">
                                            <textarea class="form-custom-input min-h-16" id="payment_link_data"
                                                name="payment_link_data"
                                                style="visibility: hidden; display:none;"></textarea>

                                            <div class="form-field-wrp">
                                                <div class="form-group payment-condition-item my-2">
                                                    @php

                                                        if (isset($bookingT[0]->processing_charge_percentage) && $bookingT[0]->processing_charge_percentage != "0.00") {
                                                            $subtotal = $bookingM->total - $forCardProcChargePercentage;
                                                        } else {
                                                            $subtotal = $makePaymentValueForCardFailed;
                                                        }
                                                        if (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
                                                            $subtotal = $bookingM->total - $paymentOnlyCheck;
                                                        }
                                                        if ($bookingM->discount2_value > 0 && empty($trans) && $bookingM->is_affiliate == "Yes") {
                                                            $subtotal = $subtotal - $bookingM->discount2_value;
                                                        }
                                                                                
                                                        $subtotal = $subtotal + $payLaterAddons['addons_price'] + $customChargePrice['custom_charge_total'] + $tipAmounts['tip_amount'];
                                                        // When a pending remaining_payment link exists, every payment method here must show
                                                        // that same outstanding balance, not the full/original booking amount — Pay with
                                                        // Link/Card/Cash already get this override further below (once $subtotalaffiliatecard
                                                        // is computed), but Billable renders its own amount right here, BEFORE that override
                                                        // runs, so without repeating it here Billable showed the stale pre-override
                                                        // $subtotal instead of the actual amount still owed. Mirrors the identical fix
                                                        // already applied to the admin ("Make Payment Admin" / #modal_admin) copy of this
                                                        // same block further down this file.
                                                        if (!empty($pendingRemainingLink)) {
                                                            $subtotal = $pendingRemainingLink->amount;
                                                        }
                                                        $subtotalaffiliatecash = $subtotal;
                                                    @endphp
                                                    @if ($setting)
                                                        @if ($bookingM->is_billable == 1)
                                                            {{-- Billable ("Pay Later") only applies before the booking's first payment —
                                                            once any completed transaction exists, hide it (affiliate bookings included,
                                                            since is_billable there is a customer-level flag, not a booking-state one). --}}
                                                            <label for="pay-with-later2" {{ !empty($trans) ? 'hidden' : '' }}
                                                                class="checkbox-wrap block cursor-pointer bg-[#fcce22] hover:bg-[#fcce22] overflow-hidden rounded-md">
                                                                <input type="radio" name="payment_method" id="pay-with-later2"
                                                                    class="sr-only peer w-4 h-4 mr-2" value="Pay Later">
                                                                <div
                                                                    class="w-full h-12 bg-[#ff03] hover:bg-[#ff03] border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                    <svg class="w-5 h-5 mr-3 min-w-5" width="24" height="24"
                                                                        viewBox="0 0 24 24" fill="none"
                                                                        xmlns="http://www.w3.org/2000/svg">
                                                                        <path
                                                                            d="M15.2727 7.63636C15.2727 7.92569 15.1578 8.20317 14.9532 8.40775C14.7486 8.61234 14.4711 8.72727 14.1818 8.72727H5.45455C5.16522 8.72727 4.88774 8.61234 4.68316 8.40775C4.47857 8.20317 4.36364 7.92569 4.36364 7.63636C4.36364 7.34704 4.47857 7.06956 4.68316 6.86497C4.88774 6.66039 5.16522 6.54545 5.45455 6.54545H14.1818C14.4711 6.54545 14.7486 6.66039 14.9532 6.86497C15.1578 7.06956 15.2727 7.34704 15.2727 7.63636ZM14.1818 10.9091H5.45455C5.16522 10.9091 4.88774 11.024 4.68316 11.2286C4.47857 11.4332 4.36364 11.7107 4.36364 12C4.36364 12.2893 4.47857 12.5668 4.68316 12.7714C4.88774 12.976 5.16522 13.0909 5.45455 13.0909H14.1818C14.4711 13.0909 14.7486 12.976 14.9532 12.7714C15.1578 12.5668 15.2727 12.2893 15.2727 12C15.2727 11.7107 15.1578 11.4332 14.9532 11.2286C14.7486 11.024 14.4711 10.9091 14.1818 10.9091ZM9.81818 15.2727H5.45455C5.16522 15.2727 4.88774 15.3877 4.68316 15.5922C4.47857 15.7968 4.36364 16.0743 4.36364 16.3636C4.36364 16.653 4.47857 16.9304 4.68316 17.135C4.88774 17.3396 5.16522 17.4545 5.45455 17.4545H9.81818C10.1075 17.4545 10.385 17.3396 10.5896 17.135C10.7942 16.9304 10.9091 16.653 10.9091 16.3636C10.9091 16.0743 10.7942 15.7968 10.5896 15.5922C10.385 15.3877 10.1075 15.2727 9.81818 15.2727ZM24 12V20.7273C24 21.5953 23.6552 22.4277 23.0414 23.0414C22.4277 23.6552 21.5953 24 20.7273 24H3.27273C2.40475 24 1.57232 23.6552 0.95856 23.0414C0.344804 22.4277 0 21.5953 0 20.7273V3.27273C0 2.40475 0.344804 1.57232 0.95856 0.95856C1.57232 0.344804 2.40475 0 3.27273 0H16.3636C17.2316 0 18.064 0.344804 18.6778 0.95856C19.2916 1.57232 19.6364 2.40475 19.6364 3.27273V10.9091H22.9091C23.1984 10.9091 23.4759 11.024 23.6805 11.2286C23.8851 11.4332 24 11.7107 24 12ZM17.4545 3.27273C17.4545 2.9834 17.3396 2.70592 17.135 2.50134C16.9304 2.29675 16.653 2.18182 16.3636 2.18182H3.27273C2.9834 2.18182 2.70592 2.29675 2.50134 2.50134C2.29675 2.70592 2.18182 2.9834 2.18182 3.27273V20.7273C2.18182 21.0166 2.29675 21.2941 2.50134 21.4987C2.70592 21.7032 2.9834 21.8182 3.27273 21.8182H17.4545V3.27273ZM21.8182 13.0909H19.6364V21.8182H20.7273C21.0166 21.8182 21.2941 21.7032 21.4987 21.4987C21.7032 21.2941 21.8182 21.0166 21.8182 20.7273V13.0909Z"
                                                                            fill="#60BEBD" />
                                                                    </svg>
                                                                    <span
                                                                        class="font-inter font-semibold text-black text-base flex-1">Billable</span>
                                                                    <span id="span_invoiced"
                                                                        class="amount-text total-after-discount-and-service-commission font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($subtotal) }}</span>
                                                                    <input type="hidden" id="hideeninvoiced"
                                                                        value="{{ sprintf('%.2f', ($subtotal)) }}">
                                                                </div>
                                                            </label>

                                                        @endif
                                                    @endif
                                                </div>
                                                @php
                                                    $paymentTotalForCardAffiliate = 0;
                                                    $ticketCostForCashAffiliate = 0;
                                                    if ($isCashFees != 1) {
                                                        $paymentForCashAffiliate = $subtotal;
                                                    }
                                                    if ($isCashFees == 1) {
                                                        $ticketCostForCashAffiliate = $subtotal;                            
                                                    }
                                                    $bookingFees = 0;
                                                    if ($bookingM->discount2_value > 0) {
                                                        $bookingFees = roundout($bookingM->total * $bookingT[0]->nat_fees_percentage / 100);
                                                        $subtotal = $bookingM->total + $bookingFees;
                                                        $subtotal += $customChargePrice['pay_later_custom_charge_total'] + $payLaterAddons['addons_total'] + $tipAmounts['pay_later_tip_total'];
                                                    } else {
                                                        foreach ($bookingT as $key => $booking_t) {
                                                            if ($isGroupBooking == '1') {
                                                                $rate = $booking_t->rate + $booking_t->additional_charge + $booking_t->partner_fees_amount;
                                                                $per = roundout($rate * $booking_t->nat_fees_percentage / 100);
                                                                $bookingFees += $per;
                                                            } else {
                                                                $rate = $booking_t->rate + $booking_t->additional_charge + $booking_t->permit_fee + $booking_t->partner_fees_amount;
                                                                $per = roundout($rate * $booking_t->nat_fees_percentage / 100);
                                                                $bookingFees += $booking_t->tourists * $per;
                                                            }
                                                        }
                                                        $subtotal += $bookingFees;
                                                        $subtotal += $payLaterAddons['addons_fee'] + $customChargePrice['pay_later_custom_charge_fees'] + $tipAmounts['pay_later_tip_fees'];
                                                    }

                                                    if (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
                                                        $subtotal = $bookingM->total - $paymentOnlyCheck;
                                                    }

                                                    $subtotalaffiliatecard = $subtotal;
                                                    // Inputs for re-pricing "Pay with Link" when a discount is entered (see
                                                    // updateAffiliateLinkAmount()): ticket base + everything else the link adds.
                                                    $linkAffiliateDynamic = empty($pendingRemainingLink) && !(!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck);
                                                    $linkAffiliateExtras  = round($subtotalaffiliatecard - $bookingM->total - $bookingFees, 2);
                                                    if ($isCashFees == 1) {
                                                        $paymentForCashAffiliate = $subtotal;
                                                    }

                                                    if (empty($trans) && $cashPriceEnabled == 1) {
                                                    if ($isCashFees == 1) {
                                                        $affiliateCashAmountDisplay = $cashPrices['cashPriceTotal'] + $payLaterAddons['addons_price'] + $customChargePrice['custom_charge_total'] + $payLaterAddons['addons_fee'] + $customChargePrice['pay_later_custom_charge_fees'] + $tipAmounts['pay_later_tip_fees'];
                                                    } else {
                                                        $affiliateCashAmountDisplay =
                                                            $cashPrices['cashPriceSubtotal']
                                                            + $payLaterAddons['addons_price']
                                                            + $customChargePrice['custom_charge_total'];   
                                                    }
                                                    } else {
                                                        $affiliateCashAmountDisplay = $paymentForCashAffiliate;
                                                    }
                                                $subtotalaffiliatecash =  $affiliateCashAmountDisplay;
                                                if (!empty($pendingRemainingLink)) {
                                                    $subtotal              = $pendingRemainingLink->amount;
                                                    $subtotalaffiliatecard = $pendingRemainingLink->amount;
                                                    $subtotalaffiliatecash = $pendingRemainingLink->amount;
                                                }
                                                @endphp

                                                {{-- Affiliate "Pay with Link" only when the PAYMENT_LINK setting is enabled --}}
                                                @if(!$isSplitPaymentBooking && $isPaymentLink)
                                                @can('payment-method-payment-link')
                                                    <div class="form-group payment-condition-item mb-2" id="payment-link-cp-affiliate-option">
                                                        <label for="pay-with-link-cp-affiliate" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-link-cp-affiliate"
                                                                class="sr-only peer checkset w-4 h-4 mr-2" value="Payment Link">
                                                            <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M8.333 11.667a4.167 4.167 0 006.246.453l2.5-2.5a4.167 4.167 0 00-5.893-5.893l-1.433 1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    <path d="M11.667 8.333a4.167 4.167 0 00-6.246-.453l-2.5 2.5a4.167 4.167 0 005.893 5.893l1.426-1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                                <span class="font-inter font-semibold text-black text-base flex-1">Pay with Link</span>
                                                                <span class="amount-text total-after-discount-and-service-commission link-cp-affiliate-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($subtotalaffiliatecard) }}</span>
                                                                <input type="hidden" id="hidden_link_affiliate"
                                                                    data-dynamic="{{ $linkAffiliateDynamic ? 1 : 0 }}"
                                                                    data-base="{{ $bookingM->total }}"
                                                                    data-extras="{{ $linkAffiliateExtras }}"
                                                                    data-fee-pct="{{ $bookingT[0]->nat_fees_percentage ?? 0 }}"
                                                                    data-original="{{ $subtotalaffiliatecard }}">
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                                @endif

                                                @can('payment-method-cash')
                                                    <div {{ (!empty($trans) && $trans->payment_type == 1 && $trans->via == "Card") || !empty($pendingRemainingLink) ? 'hidden' : ''}} class="form-group payment-condition-item mb-2">
                                                        <label for="pay-with-cash" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-cash"
                                                                class="sr-only peer w-4 h-4 mr-2" value="Cash" {{ !empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash" ? 'Checked' : ''}}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_2188_2274)">
                                                                        <path
                                                                            d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path
                                                                            d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_2188_2274">
                                                                            <rect width="20" height="20" fill="white"></rect>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>
                                                               
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Cash</span>
                                                                <span id="span_cash"
                                                                    class="amount-text total-after-discount-and-service-commission font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($affiliateCashAmountDisplay) }}</span>
                                                            </div>

                                                            <div class="mt-2" id="cash-paid-amount-div1">
                                                                <input type="number" name="cash_paid_amount"
                                                                    id="cash-paid-amount1"
                                                                    class="form-control form-custom-input border !border-[#ED9C45] placeholder:font-semibold placeholder:text-[#020817]  h-[40px]"
                                                                    placeholder="Paid amount" min="false" step="0.01" {{ !empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash" ? '' : 'readonly' }}>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                                <input type="hidden" id="hideencash"
                                                                value="{{ sprintf('%.2f', ($affiliateCashAmountDisplay)) }}">
                                                <input type="hidden" id="hideencashwithoutfees"
                                                                value="{{ sprintf('%.2f', ($ticketCostForCashAffiliate)) }}">
                                                @can('payment-method-card')
                                                    <div class="form-group payment-condition-item mb-2" {{ (!empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash" ? 'hidden' : '') }} {{ ($subtotal == 0) ? 'hidden' : ''  }}>

                                                        <label for="pay-with-card" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-card"
                                                                class="sr-only peer w-4 h-4 mr-2" value="Card" {{ !empty($trans) && $trans->payment_type == 1 && $trans->via == "Card" ? 'Checked' : ''}} {{ empty($trans) ? 'Checked' : ''}}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                                    <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round" />
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Card</span>
                                                                <span id="span_card"
                                                                    class="amount-text total-after-discount-and-service-commission font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($subtotal) }}</span>
                                                            </div>
                                                            <input id="hideencard" type="hidden" value="{{ $subtotal }}">
                                                            <input id="hideencustomcharge" type="hidden"
                                                                value="{{ $customChargePrice['custom_charge_total'] }}">
                                                            <input id="hideencustomchargefees" type="hidden"
                                                                value="{{ $customChargePrice['pay_later_custom_charge_fees'] }}">
                                                            <input id="hidden_total_for_affiliate" type="hidden"
                                                                value="{{ $subtotalaffiliatecard }}">
                                                            @php
                                                            if (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
                                                                $subtAffiliate = $bookingM->total - $paymentOnlyCheck;
                                                                $platformFees = roundout(($subtAffiliate * ($bookingT[0]->stripe_fees_percentage) / 100), 2);
                                                            } else {
                                                                $platformFees = $bookingFees;
                                                            }
                                                            @endphp
                                                            <input id="hidden_fees_for_affiliate" type="hidden"
                                                                value="{{ $platformFees + $payLaterAddons['addons_fee'] + $customChargePrice['pay_later_custom_charge_fees'] + $tipAmounts['pay_later_tip_fees'] }}">
                                                        </label>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full px-3">
                                        <div class="payment-method-form-wrp">

                                            @if ($isPartnerBooking != 1 && ($pricedAddons['addons_final_total'] == 0 && $payLaterAddons['addons_total'] == 0) && $customChargePrice['custom_charge_total'] == 0 && empty($pendingRemainingLink))
                                                <div class="flex flex-wrap -mx-3 items-start mt-2">
                                                    <div class="w-1/2 px-3 split-payment-div">
                                                        <div class="mt-0">
                                                            <label class="form-custom-label !font-semibold">Divided by</label>
                                                            <select class="form-custom-input" id="dividedBy" name="dividedBy">
                                                                <option selected>Please select option</option>
                                                                <option value="2">2</option>
                                                                <option value="3">3</option>
                                                                <option value="4">4</option>
                                                                <option value="5">5</option>
                                                                <option value="6">6</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="w-1/2 px-3 split-payment-div">
                                                        <div id="checkcard">
                                                            <label class="form-custom-label !font-semibold">&nbsp</label>
                                                            <div class="form-group payment-condition-item">
                                                                <label for="split-payment"
                                                                    class="checkbox-wrap checked-item form-custom-label !font-semibold whitespace-nowrap !flex items-center">
                                                                    <input type="checkbox" name="split_checkbox"
                                                                        id="split-payment" value="1"
                                                                        class="checkset w-4 h-4 mr-2 relative">
                                                                    <span
                                                                        class="amount-text split-check bg-[#E5E7EB] py-0.5 px-1 rounded font-semibold text-[#4B5563] mr-2">{{ moneyFormat(0.00) }}</span>
                                                                    Split Payment
                                                                </label>
                                                            </div>
                                                            <div class="w-full mobile-full">
                                                                <div id="split_value" class="form-group" hidden>
                                                                    <div class="discount-code-input">
                                                                        <input type="float" name="split_value"
                                                                            id="split_value_field"
                                                                            class="form-control form-custom-input mr-1"
                                                                            placeholder="Split amount" min="1" step="0.01">
                                                                        <span class="field-icon per-icon"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Pending items summary row — shown when a pending remaining_payment link exists -->
                                            <div id="pending-items-summary-affiliate" class="{{ empty($pendingRemainingLink) ? 'hidden' : '' }} mb-1">
                                                <div class="flex items-center justify-between px-2 py-1 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                                    <div class="flex items-center gap-1">
                                                        <span>Pending payment</span>
                                                        <button type="button" id="btn-cp-payment-breakdown-affiliate"
                                                            class="text-[#264f73] hover:text-[#1a3a56]"
                                                            title="View breakdown">
                                                            <i class="fa fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                    <span id="pending-items-amount-affiliate" class="font-semibold">{{ !empty($pendingRemainingLink) ? moneyFormat($pendingRemainingLink->amount) : '' }}</span>
                                                </div>
                                            </div>
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md mt-2">
                                                <input name="tt_cost" id="htt" type="hidden"
                                                    value="@if (!empty($pendingRemainingLink)){{ sprintf('%.2f', $pendingRemainingLink->amount) }}@elseif (!empty($bookingT[0]->total)){{ sprintf('%.2f', ($bookingT[0]->total))}}@endif">
                                                <input name="pr_amount" id="pr_amount" type="hidden" value="">
                                                <div class="total-payment">Total payment</div>
                                                <div id="totalcost"
                                                    class="total-price total-after-discount-and-service-commission">
                                                    @if (!empty($pendingRemainingLink))
                                                    ${{ sprintf('%.2f', $pendingRemainingLink->amount) }}
                                                    @elseif (!empty($bookingT[0]->total))
                                                    ${{ sprintf('%.2f', ($bookingT[0]->total)) }} @endif
                                                </div>
                                            </div>

                                            <div class="makeaayment-wrap mt-2">
                                                <div class="form-group">
                                                    <input type="submit" id="btn-make-payment"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    @php
        if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
            $paymentTotalForCardAffiliate = $subtotal;
        }
    @endphp
    <!-- Affilated Payment Modal Colse -->

    <!-- Tip Modal Start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="add_tips" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Tip</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form id="tips-payment-form" method="post"
                                action="{{ route(prefixedRouteName($routePrefix . 'booking.tip-payment')) }}"
                                onsubmit="return validateAndSubmitForm3(this)"
                                data-call-on-success="Tip payment successfully done">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            <input type="hidden" name="bookingID" id="booking-id-add-tips" value="{{$bookingM->id}}">
                                            <div class="flex flex-wrap -mx-3">
                                                <div class="w-full px-3 mb-4">
                                                    <div class="form-group cardnum flex items-center">
                                                        <label
                                                            class="form-label text-sm font-normal text-[#020817] mb-1 mr-2 inline-block min-w-20">Send
                                                            Mail
                                                        </label>
                                                        <input type="checkbox" name="send_tip_mail" value="Yes"
                                                            class="w-4 h-4 align-text-top" />
                                                    </div>
                                                </div>
                                                <div class="w-full px-3 mb-4">
                                                    <div class="form-group cardnum flex items-center">
                                                        <label for="exampleFormControlInput1"
                                                            class="form-label text-sm font-normal text-[#020817] mb-1 mr-2 inline-block min-w-20">Amount</label>
                                                        <input type="text" id="amount"
                                                            class="form-control form-custom-input" name="amount"
                                                            placeholder="Please enter Amount" required>
                                                    </div>
                                                </div>
                                                <div class="w-full px-3 mb-4">
                                                    <div class="form-group cardnum flex items-center">
                                                        <label for="exampleFormControlInput1"
                                                            class="form-label text-sm font-normal text-[#020817] mb-1 mr-2 inline-block min-w-20">Staff
                                                            Name</label>
                                                        <input type="hidden" name="send_guide_mail" value="Yes" />
                                                        <select class="form-select form-custom-input w-full" id="staffname"
                                                            name="staffname">
                                                            @foreach($staffUsers as $staffUser)
                                                                <option value="{{$staffUser->id}}">{{$staffUser->first_name}}
                                                                    {{$staffUser->last_name}}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                            <p id="card-error" role="alert" class="hidden text-xs text-[#ff0000] mb-2"></p>
                                            <div class="makeaayment-wrap">
                                                <div class="form-group mb-2">
                                                    <input type="submit" id="btn-make-tip"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Tip">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    <!-- Tip Modal Colose -->

    <!-- Edit Booking feature Modal start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="edit_booking" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Edit Booking</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>
                <form class="p-4" method="post"
                    action="{{ route(prefixedRouteName($routePrefix . 'booking.refund_and_booking_edit')) }}" id="form_id">
                    @csrf
                    @if($managerRequestEnable['Delete'] == 'Yes')
                        <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-danger-small"
                            id="delete">Delete</button>
                    @endif
                    @if($isGroupBooking != '1')
                        @if (session('userType') != 'Affiliate')
                            @if($managerRequestEnable['Permit'] == 'Yes')
                                @if (!empty($transP) && ($paymentTransCount > 1) && $transP->via == "Card")
                                    <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-primary-small"
                                        id="updatePermitFees">Permit</button>
                                @else
                                    <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-primary-small"
                                        id="update">Permit</button>
                                @endif
                            @endif
                        @endif
                    @endif
                    @if (!empty($trans) && $transP->via == "Card" && $trans->payment_type == 1)
                        @if($managerRequestEnable['Change'] == 'Yes' && !$hasRateGroupWithDifferentPermitFees)
                            <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-info-small" data-bs-toggle="modal"
                                data-bs-target="#edit_booking_change_people" id="change_people">Change</button>
                        @endif
                        @if($isGroupBooking != '1' && $Refund_Amount != $bookingM->booking_total)
                            @if($managerRequestEnable['Split Booking'] == 'Yes')
                                <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-warning-small" id="split_people">Split
                                    Booking</button>
                            @endif
                        @endif
                        @if(userType() != "Affiliate")
                            @if($managerRequestEnable['Discount'] == 'Yes')
                                <button type="button" data-bs-toggle="modal" data-bs-target="#modal_edit_discount"
                                    class="themed-btn short-btn mb-4 mr-2 text-xs text-white font-semibold shadow-sm rounded-lg px-4 py-2 inline-block bg-[#F97316] border border-[#F97316] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] transition-all duration-300 ease-in-out">Edit
                                    Discount</button>
                            @endif
                        @endif
                    @else
                        @if($managerRequestEnable['Change'] == 'Yes' && !$hasRateGroupWithDifferentPermitFees)
                            <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-info-small" data-bs-toggle="modal"
                                data-bs-target="#edit_booking_change_people" id="change_people">Change</button>
                        @endif
                        @if($managerRequestEnable['Split Booking'] == 'Yes')
                            @if($isGroupBooking != '1' && $Refund_Amount != $bookingM->booking_total)
                                <button type="button" class="themed-btn short-btn mb-4 mr-2 btn-warning-small" id="split_people">Split
                                    Booking</button>
                            @endif
                        @endif
                        @if(userType() != "Affiliate")
                            @if($managerRequestEnable['Discount'] == 'Yes')
                                <button type="button" data-bs-toggle="modal" data-bs-target="#modal_edit_discount"
                                    class="themed-btn short-btn mb-4 mr-2 text-xs text-white font-semibold shadow-sm rounded-lg px-4 py-2 inline-block bg-[#F97316] border border-[#F97316] hover:border-[#4B5563] hover:bg-white hover:text-[#4B5563] transition-all duration-300 ease-in-out">Edit
                                    Discount</button>
                            @endif
                        @endif

                    @endif

                    <br>
                    <input type="hidden" name="refund_Type" id="refund_Type">
                    <input type="hidden" name="is_cash" id="is_cash">
                    <input type="hidden" name="partial_Refund_value" id="partial_Refund_value">
                    <input name="action_type" hidden value="" id="action_type">
                    <input name="tourits_numbers" hidden id="tourits_numbers" value="">
                    <input name="contacts" hidden id="contact_ids" value="">
                    <input name="pax_addons_ids" hidden id="pax_addons_ids" value="">
                    <input name="pending_history_ids" hidden id="pending_history_ids" value="">
                    <input name="pending_item_ids" hidden id="pending_item_ids" value="">
                    <input name="bookingID" id="booking-id-edit-booking" hidden value="{{$bookingM->id}}">
                    <input hidden id="parent_url" name="parent_url" value="">
                    <input name="tour_operator_id" id="tourOperatorId" hidden value="{{$bookingM->tour_operator_id}}">
                    <input name="payment_type" id="payment_type" hidden value="{{$bookingM->via}}">
                    <input name="total_tourist" id="total_tourist" hidden value="{{$bookingM->tourists + $bookingM->infants}}">
                    <input name="checked_transactions" hidden id="checked_transactions" value="">
                    {{-- Which specific add-on line items (tour_booking_addon_fields.id) the admin
                    chose to refund in the "Update Booking" modal — JSON array, e.g. "[816,823]".
                    Left empty when the modal never rendered an add-ons block (no price-per-pax
                    add-ons on this booking), so the backend's "no selection submitted → refund
                    everything in scope" fallback keeps today's behavior for those bookings. --}}
                    <input name="selected_addon_field_ids" hidden id="selected_addon_field_ids" value="">
                    {{-- Every add-on field id (tour_booking_addon_fields.id) belonging to the
                    person(s) checked for deletion in "Edit Booking" — JSON array, e.g. "[1043]".
                    Populated directly from the "Update Booking" modal's own add-ons block (same
                    data the admin sees there), so the direct delete flow no longer needs the
                    separate "Passenger Add-ons" selection modal to resolve this. Left empty for
                    the split-people flow, which still uses that modal and pax_addons_ids instead. --}}
                    <input name="delete_booking_addon_scope_ids" hidden id="delete_booking_addon_scope_ids" value="">
                    <input type="hidden" name="splitPendingAmount" value="{{$splitPendingAmount}}">
                    <input type="hidden" name="split_is_all_unpaid" id="split_is_all_unpaid" value="0">
                    <input type="hidden" name="split_pending_item_ids" id="split_pending_item_ids" value="">
                    <input type="hidden" name="split_unpaid_check_counts" id="split_unpaid_check_counts" value="">

                    <input name="is_calculate_amount" hidden id="is_calculate_amount" value="0">

                    <div class="modal-body">
                        <div class="row default-table-design">
                            <div class="row details-table">
                                <div class="col-12 p-0">
                                    <div
                                        class="table-respons custom-scrollbar max-h-80 overflow-y-auto border border-[#E2E8F0] rounded-lg">
                                        <table class="table w-full" id="billing-table">
                                            <thead class="thead-fixed">
                                                <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                                                    <th
                                                        class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                        Total Booked People: {{ $bookingM->tourists + $bookingM->infants }}</th>
                                                    @if($bookingM->has_contacts == 1)
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">Age
                                                        </th>
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                            Weight (lbs) </th>
                                                    @endif
                                                    @if($isGroupBooking != '1')
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                            Price (Per Person)</th>
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                            Permit Fee</th>
                                                        @if ($bookingM->is_partner_booking)
                                                            <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                                Partner Fee</th>
                                                        @endif
                                                         <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left hidden">
                                                            <abbr title="Additional">Adnl.</abbr> Tax&nbsp;%
                                                        </th> 
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">Tax
                                                        </th>
                                                        <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                            Total Cost</th>
                                                    @endif
                                                    <th class="text-xs font-medium text-[#374151] p-4 whitespace-nowrap text-left">
                                                        Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @php
                                                    $promoDiscount = 0;
                                                    $touristNum = 0;

                                                    $totalBeforeServiceCommission = $bookingM->total - $bookingM->service_commission;

                                                    if ($totalBeforeServiceCommission != 0) {
                                                        $serviceCommissionPercentage = round($bookingM->service_commission * 100 / $totalBeforeServiceCommission, 2);
                                                    }

                                                    $cid = $cname = $cage = $cweight = $cnote = '';
                                                @endphp

                                                @foreach($bookingT as $bt)
                                                    @php
                                                        $isAdult = $bt->age_from > config('app.child_max_age') ? 1 : 0;
                                                        $rate = $bt->rate + $bt->additional_charge + $bt->permit_fee + $bt->partner_fees_amount;
                                                        $per = $rate * $bt->processing_charge_percentage / 100;
                                                        $subtotal = ($rate + $per);
                                                    @endphp

                                                    @for($a = 0; $a < $bt->tourists; $a++, $touristNum++)
                                                        @php
                                                            $cid = $cname = $cage = $cweight = $cnote = '';
                                                            $paxPendingInfo = ($pendingPaxInfo ?? [])[$bt->id] ?? null;
                                                            $isPendingPax = $paxPendingInfo !== null && $a >= ($bt->tourists - ($paxPendingInfo['added_count'] ?? 0));
                                                            $pendingHistoryId = $isPendingPax ? ($paxPendingInfo['history_id'] ?? 0) : 0;
                                                            $pendingItemId = $isPendingPax ? ($paxPendingInfo['item_id'] ?? 0) : 0;
                                                        @endphp
                                                        <tr class="border-t border-[#E2E8F0]{{ $isPendingPax ? ' bg-amber-50' : ' hover:bg-[#F0FDF4]' }}">
                                                            @if($bookingM->has_contacts == 1)
                                                                @foreach($bookingContacts as $key => $bookingContact)
                                                                    @foreach($bookingContact['contact_data'] as $keyc => $contact)
                                                                        @if($key == $bt->id && $keyc == $a)
                                                                            @php
                                                                                $cid = $contact->contact_id;
                                                                                $cname = $contact->name;
                                                                                $cage = $contact->age;
                                                                                $cweight = $contact->weight;
                                                                            @endphp
                                                                        @endif
                                                                    @endforeach
                                                                @endforeach
                                                                <td class="text-sm text-[#4B5563] p-4"><span
                                                                        class="contact_name_{{ ($cid) ? $cid : '' }}"
                                                                        id="edit_booking_cont_name_{{ ($a + 1) }}">{{ ($cname) ? $cname : "-" }}</span>
                                                                </td>
                                                                <td class="text-sm text-[#4B5563] p-4"><span
                                                                        class="contact_age_{{ ($cid) ? $cid : '' }}"
                                                                        id="edit_booking_cont_age_{{ ($a + 1) }}">{{ ($cage) ? $cage : "-" }}</span>
                                                                </td>
                                                                <td class="text-sm text-[#4B5563] p-4"><span
                                                                        class="contact_weight_{{ ($cid) ? $cid : '' }}"
                                                                        id="edit_booking_cont_weight_{{ ($a + 1) }}">{{ ($cweight) ? $cweight : "-" }}</span>
                                                                </td>
                                                            @else
                                                                <td class="text-sm text-[#4B5563] p-4">
                                                                    {{ ($isGroupBooking == '1') ? 'Person #' . ($a + 1) : $bt->rate_for . ' #' . ($a + 1) }}
                                                                    @if($isPendingPax)
                                                                        <span class="ml-1 text-xs font-semibold text-amber-700 bg-amber-100 border border-amber-300 rounded px-1.5 py-0.5">Unpaid</span>
                                                                    @endif
                                                                </td>
                                                            @endif
                                                            @if($isGroupBooking != '1')
                                                                <td class="text-sm text-[#4B5563] p-4">{{ moneyFormat($bt->rate) }}</td>
                                                                @php
                                                                    $permit_fee = sprintf('%.2f', round($bt->permit_fee));
                                                                    $refund_permitfee_status = ($bt->refund_permitfee_status != '') ? json_decode($bt->refund_permitfee_status) : '';
                                                                @endphp
                                                                <td class="p-2">
                                                                    <select
                                                                        class="px-3 py-2 text-xs text-[#020817] rounded-lg border border-[#0dcaf0] bg-[#0dcaf020]"
                                                                        name="permitF[{{$bt->rate_for}}.{{$a}}]"
                                                                        id="permitF-{{$bt->rate_for}}.{{$a}}">
                                                                        @php
                                                                            if ($refund_permitfee_status == '') {
                                                                                $totalamtafterpermitfeesrefunded = $bt->total / $bt->tourists;
                                                                                
                                                                                // Check if this is a 2026 booking with $20 permit fee
                                                                                $is2026Booking = (strpos($bookingM->date, '2026') !== false);
                                                                                $currentPermitFee = sprintf('%.2f', round($bt->permit_fee));
                                                                        @endphp
                                                                        @if($is2026Booking && $currentPermitFee == '20.00')
                                                                            <option value="20" selected>20 (Current)</option>
                                                                            <option value="15">15 (Adjusted - $5 refund)</option>
                                                                            <option value="0">0 (Full refund)</option>
                                                                        @else
                                                                            @if($currentPermitFee != '0.00')
                                                                                <option value="{{$currentPermitFee}}" selected>{{$currentPermitFee}}</option>
                                                                            @endif
                                                                            <option {{($permit_fee) == '0.00' ? 'selected' : ''}} value="0">0.00</option>
                                                                        @endif
                                                                        @php
                                                                            } else {
                                                                                // Calculate effective permit fee after refunds
                                                                                $effectivePermitFee = $bt->permit_fee; // Start with original permit fee
                                                                                $processingFeesRefunded = 0;

                                                                                if (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y") {
                                                                                    // Check if this is a partial refund with new_permit_fee
                                                                                    if (isset($refund_permitfee_status[$a]->new_permit_fee)) {
                                                                                        $effectivePermitFee = $refund_permitfee_status[$a]->new_permit_fee;
                                                                                    } else {
                                                                                        $effectivePermitFee = 0; // Full refund case
                                                                                    }

                                                                                    if (isset($refund_permitfee_status[$a]->processing_fees)) {
                                                                                        $processingFeesRefunded = $refund_permitfee_status[$a]->processing_fees;
                                                                                    }
                                                                                }

                                                                                // Calculate total with effective permit fee
                                                                                $totalamtafterpermitfeesrefunded = ($bt->rate + $bt->additional_charge + $effectivePermitFee + $bt->partner_fees_amount) - $processingFeesRefunded;
                                                                                
                                                                                // Check if this is a 2026 booking with $20 permit fee
                                                                                $is2026Booking = (strpos($bookingM->date, '2026') !== false);
                                                                                $currentPermitFee = sprintf('%.2f', round($bt->permit_fee));
                                                                                
                                                                                // Determine current effective permit fee (after any previous refunds or unpaid overrides)
                                                                                $effectivePermitFee = $currentPermitFee;
                                                                                if (isset($refund_permitfee_status[$a]) && !empty($refund_permitfee_status[$a]->is_pax_added) && isset($refund_permitfee_status[$a]->permit_fee_override)) {
                                                                                    $effectivePermitFee = sprintf('%.2f', $refund_permitfee_status[$a]->permit_fee_override);
                                                                                } elseif (isset($refund_permitfee_status[$a]) && $refund_permitfee_status[$a]->status == "Y") {
                                                                                    if (isset($refund_permitfee_status[$a]->new_permit_fee)) {
                                                                                        $effectivePermitFee = sprintf('%.2f', $refund_permitfee_status[$a]->new_permit_fee);
                                                                                    } else {
                                                                                        $effectivePermitFee = '0.00'; // Full refund case
                                                                                    }
                                                                                }
                                                                        @endphp
                                                                        @if($is2026Booking && $currentPermitFee == '20.00')
                                                                            <option value="20" {{$effectivePermitFee == '20.00' ? 'selected' : ''}}>20 (Original)</option>
                                                                            <option value="15" {{$effectivePermitFee == '15.00' ? 'selected' : ''}}>15 (Adjusted - $5 refund)</option>
                                                                            <option value="0" {{$effectivePermitFee == '0.00' ? 'selected' : ''}}>0 (Full refund)</option>
                                                                        @else
                                                                            @if($currentPermitFee != '0.00')
                                                                                <option value="{{$currentPermitFee}}" {{$effectivePermitFee == $currentPermitFee ? 'selected' : ''}}>{{$currentPermitFee}}</option>
                                                                            @endif
                                                                            <option {{$effectivePermitFee == '0.00' ? 'selected' : ''}} value="0">0.00</option>
                                                                        @endif
                                                                        @php
                                                                            }
                                                                        @endphp
                                                                    </select>
                                                                </td>
                                                                @if($bookingM->is_partner_booking)
                                                                    <td class="text-sm text-[#4B5563] p-4">
                                                                        {{ moneyFormat(sprintf('%.2f', $bt->partner_fees_amount)) }}
                                                                    </td>
                                                                @endif
                                                                <td class="text-sm text-[#4B5563] p-4 hidden">
                                                                    {{ $bt->additional_tax_percentage }}%
                                                                </td>
                                                                <td class="text-sm text-[#4B5563] p-4">
                                                                    {{ moneyFormat($bt->additional_charge) }}
                                                                </td>
                                                                <td class="text-sm text-[#4B5563] p-4">
                                                                    {{ moneyFormat(sprintf('%.2f', round($totalamtafterpermitfeesrefunded, 2))) }}
                                                                </td>
                                                            @endif
                                                            @if($bookingM->has_contacts == 1)
                                                                @if(count($bookingContacts) > 0)
                                                                    @foreach($bookingContacts as $key => $bookingContact)
                                                                        @foreach($bookingContact['contact_data'] as $keyc => $contact)
                                                                            @if($key == $bt->id && $keyc == $a)
                                                                                @php
                                                                                    $cid = $contact->contact_id;
                                                                                @endphp
                                                                            @endif
                                                                        @endforeach
                                                                    @endforeach
                                                                @endif
                                                            @endif
                                                            <td
                                                                class="text-sm text-[#4B5563] p-4 {{ ($isGroupBooking == '1') ? 'text-center' : '' }}">
                                                                <input type="checkbox"
                                                                    data-contact-id="{{ isset($cid) ? $cid : '' }}"
                                                                    data-pax-id="{{ $bt->id }}"
                                                                    data-pax-index="{{ $a }}"
                                                                    data-pending="{{ $isPendingPax ? '1' : '0' }}"
                                                                    data-pending-history-id="{{ $pendingHistoryId }}"
                                                                    data-pending-item-id="{{ $pendingItemId }}"
                                                                    data-pending-transact-id="{{ $isPendingPax ? $bt->id : 0 }}"
                                                                    {{-- This person's own ticket cost (and its proportional platform fee), for the
                                                                    "Delete Booking" modal's Tour preview — Block 4 there sums exactly the checked
                                                                    people's own amounts instead of the whole PaymentIntent's outstanding balance,
                                                                    which could cover people who aren't being removed at all. Non-group rows reuse
                                                                    the SAME $totalamtafterpermitfeesrefunded already shown in this row's own "Total
                                                                    Cost" cell (only defined there, hence the ternary). Group-rate rows keep this as
                                                                    an even-split APPROXIMATION too (kept only so `found` detection in
                                                                    delete-booking-refund.js still works) — the actual ticket amount summed for a
                                                                    checked group-rate row comes from data-group-tier-table below instead, since no
                                                                    per-checkbox static share can correctly sum for removing more than one person
                                                                    from a tiered group rate. --}}
                                                                    @php
                                                                        $paxTicketAmount = ($isGroupBooking == '1') ? ($bt->total / max(1, $bt->tourists)) : $totalamtafterpermitfeesrefunded;
                                                                        // nat_fees_percentage is the package's configured commission rate and is
                                                                        // always set regardless of payment method — but the fee is only actually
                                                                        // charged to the guest for Card, or for Cash when cash fees are enabled
                                                                        // (see service_commission's own assignment in TourBookingRepository).
                                                                        // $bookingM->service_commission > 0 reflects what was ACTUALLY charged on
                                                                        // this booking, so a Cash booking made without fees keeps its "Weather /
                                                                        // 100%" preview equal to the tour amount instead of inventing a fee that
                                                                        // was never collected.
                                                                        $paxFeePercentage = ($bookingM->service_commission > 0) ? $bt->nat_fees_percentage : 0;
                                                                    @endphp
                                                                    data-ticket-amount="{{ sprintf('%.2f', $paxTicketAmount) }}"
                                                                    data-fee-amount="{{ sprintf('%.2f', round($paxTicketAmount * $paxFeePercentage / 100, 2)) }}"
                                                                    @if($isGroupBooking == '1')
                                                                        data-is-group-rate="1"
                                                                        data-group-original-tourists="{{ $groupRateOriginalTourists }}"
                                                                        data-group-fee-percentage="{{ $paxFeePercentage }}"
                                                                        data-group-tier-table="{{ json_encode($groupRateTierTable) }}"
                                                                    @endif
                                                                    id="check-{{$bt->rate_for}}.{{$a}}" autocomplete="off"
                                                                    class="ads_Checkbox" name="check[{{$bt->rate_for}}.{{$a}}]"
                                                                    value="{{$bt->tour_package_rate_id}}">
                                                            </td>
                                                        </tr>
                                                    @endfor
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="bg-[#F9FAFB] rounded-md px-4 py-3 mt-3">
                                        <div class="grand-total flex flex-wrap -mx-3">
                                            <div class="tsub flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Ticket Cost:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    @if ($totalTicketCost >= 0)
                                                        {{ moneyFormat(sprintf('%.2f', $totalTicketCost)) }}
                                                    @else
                                                        {{ moneyFormat(sprintf('%.2f', $bookingM->total)) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="discount flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm">Discount:</div>
                                                <div class="w-1/2 text-sm text-right text-[#FF0000]">-
                                                    @php
                                                        if ($bookingM->discount2_percentage != 0) {
                                                            echo '(' . str_replace('.00', '', $bookingM->discount2_percentage) . '%)';
                                                        }

                                                        echo moneyFormat($bookingM->discount2_value);
                                                    @endphp
                                                </div>
                                            </div>
                                            <div class="tsub flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Subtotal:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    @if ($totalTicketCost - $bookingM->discount2_value >= 0)
                                                        {{ moneyFormat(sprintf('%.2f', $totalTicketCost - $bookingM->discount2_value)) }}
                                                    @else
                                                        {{ moneyFormat(sprintf('%.2f', $bookingM->total)) }}
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="tfee flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Booking Fees:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    {{ moneyFormat($bookingM->service_commission) }}
                                                </div>
                                            </div>

                                            <div class="tbooking flex items-center w-full md:w-1/2 max-w-full px-3 mb-2">
                                                <div class="w-1/2 text-sm text-[#020817]">Total Cost:</div>
                                                <div class="w-1/2 text-sm text-[#020817] text-right">
                                                    {{ moneyFormat($bookingM->total) }}
                                                </div>
                                            </div>
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

    @if ($isBookingPaxAddonsExists)
        <style>
            /* .cp-pax-row {
                display: flex;
                flex-wrap: wrap;
                border: 1px solid #E5E7EB;
                border-radius: 0.5rem;
                padding: 0.5rem 0.75rem;
                align-items: center;
                margin-bottom: 0.5rem;
                background: white;
            } */

            /* .cp-pax-spinner {
                display: flex;
                gap: 0.5rem;
                margin-right: 0.5rem;
            } */

            /* .cp-pax-count {
                width: 48px;
                text-align: center;
                font-weight: bold;
                background: transparent;
                border: 1px solid #E5E7EB;
                border-radius: 0.25rem;
            } */

            /* .cp-dec-btn,
            .cp-inc-btn {
                width: 32px;
                height: 32px;
                border-radius: 9999px;
                font-weight: bold;
                font-size: 1.25rem;
                border: none;
                cursor: pointer;
                transition: background 0.2s;
            }

            .cp-dec-btn {
                background: #E5E7EB;
                color: #374151;
            }

            .cp-dec-btn:hover:not(:disabled) {
                background: #D1D5DB;
            }

            .cp-dec-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            }

            .cp-inc-btn {
                background: #2563EB;
                color: white;
            }

            .cp-inc-btn:hover:not(:disabled) {
                background: #1D4ED8;
            }

            .cp-inc-btn:disabled {
                opacity: 0.5;
                cursor: not-allowed;
            } */

            /* .cp-pax-info {
                flex: 1;
            }

            .cp-pax-price {
                margin-left: auto;
                font-weight: 600;
                color: #059669;
            } */

            /* .cp-rate-groups-section,
            .cp-pax-details-section {
                padding: 1rem;
            } */

            .cp-pax-details-container {
                max-height: 60vh;
                overflow-y: auto;
            }

            .cp-pax-detail {
                border: 1px solid #E5E7EB;
                border-radius: 0.5rem;
                margin-bottom: 0.75rem;
                overflow: hidden;
                background: white;
            }

            .cp-loader {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 50px;
                color: #64748b;
            }
            .spinner {
                width: 40px;
                height: 40px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 10px;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* .cp-pax-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                width: 100%;
                padding: 0.75rem 1rem;
                background: #F9FAFB;
                border-bottom: 1px solid #E5E7EB;
            }

            .cp-pax-header h4 {
                margin-bottom: 0;
                font-weight: 500;
                font-size: 0.95rem;
            } */

            /* .cp-remove-pax {
                color: #DC2626;
                font-size: 1.5rem;
                cursor: pointer;
                line-height: 1;
            }

            .cp-remove-pax:hover {
                color: #991B1B;
            }

            .cp-addon-fields {
                padding: 0.75rem;
            } */

            .cp-addon-fields .addon-card {
                margin-top: 0.5rem !important;
            }
        </style>
        <!-- Edit Booking Add-ons change people feature Modal start -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="edit_booking_change_people" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden"
                    style="max-height: calc(100vh - 40px); overflow-y: auto;">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Edit Booking - Change People</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="modal-body p-4">
                        <form method="post"
                            action="{{ route(prefixedRouteName($routePrefix . 'booking.change-people-booking')) }}"
                            onsubmit="return showMakePaymentForEditBookingChangePeople(this)" id="changePeopleForm"
                            class="change-people-dual-layout">
                            @csrf
                            <input name="action_type" hidden value="UPDATE_BOOKING_CHANGE_PEOPLE" />
                            <input name="bookingID" hidden value="{{$bookingM->id}}" />
                            <input name="tour_operator_id" hidden value="{{$bookingM->tour_operator_id}}" />
                            <input name="total_tourist" hidden value="{{$bookingM->tourists + $bookingM->infants}}" />
                            <input name="payment_amount" id="payment_amount" hidden value="0" />
                            <input name="refund_amount" id="refund_amount" hidden value="0" />
                            <input name="slot_time" hidden value="{{ $bookingM->tour_slot_time_id }}" />
                            <input name="subtt" hidden id="subtotalchangepeople" />
                            <input name="infantorchilds" hidden id="infantorchilds" />
                            <input name="old_total" hidden
                                value="{{ trimDeci($totalBeforeServiceCommission + $bookingM->discount2_value) }}" />
                            <input name="fee" hidden class="fee-" />
                            <input name="checked_transactions" hidden id="checked_transactions_update" value="">
                            <input name="cp_pending_item_ids" hidden id="cp_pending_item_ids_v2" value="">
                            <input name="cp_pending_history_ids" hidden id="cp_pending_history_ids_v2" value="">

                            <div class="flex flex-wrap -mx-3 justify-end">
                                <div class="w-full max-w-full px-3">
                                    <div id="change-people-rate-groups-pills" class="space-y-3"></div>
                                </div>
                                <!-- Total -->
                                 <div class="w-full md:w-1/2 max-w-full px-3 pax-addons-screen" style="display: none;">
                                    <div class="mt-2 py-2 px-4 bg-[#F0FDF4] rounded-lg border-2 border-[#BBF7D0] w-full">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xl font-semibold text-[#15803D]">Total Payable:</span>
                                            <div class="text-right">
                                                <div class="text-2xl font-bold">
                                                    <span class="total-before-service-commission-and-discount-change-people text-[#2C2421]">{{ moneyFormat(0.00) }}</span>
                                                </div>
                                                <small class="text-[#4B5563]">inclusive all taxes</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden slot selector -->
                                    <select id="slot_time" class="mt-2 border border-[#E5E7EB] bg-[#FAFAFA] text-sm text-[#2C2421] font-medium rounded-lg px-3 py-2 mr-3 flex items-center whitespace-nowrap w-full" style="display: none">
                                        <option value="{{ $bookingM->tour_slot_time_id }}" selected
                                            data-seats="{{ $bookingM->slot_seats }}"
                                            data-tourists="{{ $bookingM->slot_all_tourists_count }}"
                                            data-booked="{{ $bookingM->available_tourists ?? 0 }}">
                                        </option>
                                    </select>

                                    <!-- Submit Button -->
                                    <div class="text-center mt-5 w-full">
                                        <button type="submit" id="update_booking_change_people_submit"
                                            class="btn btn-primary save-addons btn-primary-small w-full">
                                            Update & Confirm Booking
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Edit Booking Add-ons change people feature Modal close -->
    @else
        <!-- Change Booking feature Modal start -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="edit_booking_change_people" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Edit Booking - Change People</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>
                    <div class="modal-body p-4">
                        <form method="post"
                            action="{{ route(prefixedRouteName($routePrefix . 'booking.change-people-booking')) }}"
                            onsubmit="return showMakePaymentForEditBookingChangePeople(this)" id="changePeopleForm">
                            @csrf
                            <input name="action_type" hidden value="UPDATE_BOOKING_CHANGE_PEOPLE" id="action_type_change_people" />
                            <input name="bookingID" id="booking-id-change-people" hidden value="{{$bookingM->id}}" />
                            <input name="tour_operator_id" id="tour_operator_id" hidden
                                value="{{$bookingM->tour_operator_id}}" />
                            <input name="total_tourist" id="totalTourist" hidden value="{{$bookingM->tourists + $bookingM->infants}}" />
                            <input name="payment_amount" id="payment_amount" hidden value="0" />
                            <input name="refund_amount" id="refund_amount" hidden value="0" />
                            <input name="slot_time" hidden value="{{ $bookingM->tour_slot_time_id }}" />
                            <input name="subtt" hidden class="total-" id="subtotalchangepeople" />
                            <input name="infantorchilds" hidden id="infantorchilds" />
                            <input name="old_total" hidden class="old-total-"
                                value="{{ trimDeci($totalBeforeServiceCommission + $bookingM->discount2_value) }}" />
                            <input name="fee" hidden class="fee-" />
                            <input name="checked_transactions" hidden id="checked_transactions_update" value="">
                            <input name="cp_pending_item_ids" hidden id="cp_pending_item_ids" value="">
                            <input name="cp_pending_history_ids" hidden id="cp_pending_history_ids" value="">
                            <div class="row default-table-design">
                                <div class="col-12">
                                    <div class="booking-left-wrp max-[479px]:mb-2 min-[480px]:float-end">
                                        <div class="tour-detail-item">
                                            <div
                                                class="tour-detail-process flex items-center justify-center gap-2 px-[13px] py-1.5 bg-green-600 rounded-[5px] font-semibold text-white text-base max-[1350px]:text-sm">
                                                <img src="{{ asset('images/checked-white.png') }}" alt="checked">
                                                <span id="span-seats-info-change-people">0 of 0 left</span>
                                            </div>

                                            <div class="tour-process-wrp">
                                                <div class="tour-process success-border-color" style="width:50%"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="font-semibold text-lg max-[1350px]:text-base text-[#111827] mb-4 max-[1350px]:mb-3">
                                        Please select group of people:</div>
                                    <div class="scroll-table">
                                        <div class="table-responsive w-full max-w-full overflow-y-auto">
                                            <table class="table mb-1 tbl-rate-info w-full">
                                                <thead>
                                                    <tr id="headings-rate-group">
                                                        <th
                                                            class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                            Age Group</th>
                                                        <th
                                                            class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                            Number of<br> People</th>
                                                        @if($isGroupBooking != '1')
                                                            <th
                                                                class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                                Rate</th>
                                                            <th
                                                                class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                                Permit Fee</th>
                                                            @if($isPartnerBooking == '1')
                                                                <th
                                                                    class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                                    Partner Fee</th>
                                                            @endif
                                                            <th
                                                                class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap hidden">
                                                                Adnl. Tax %</th>
                                                        @endif
                                                        <th
                                                            class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                            Tax</th>
                                                        <th
                                                            class="h-12 bg-[#264F73] text-white font-bold text-sm py-2 px-3 border border-solid whitespace-nowrap">
                                                            Final Cost</th>
                                                    </tr>
                                                </thead>

                                                <tbody class="hidden" id="tmpl-rate-group-change-people">
                                                    <tr>
                                                        <td
                                                            class="bg-white border px-3 py-2 border-solid font-medium text-black text-base age">
                                                        </td>
                                                        <td class="group bg-white border px-3 py-2 border-solid">
                                                            <input type="hidden" class="hid-rate-group"
                                                                data-name="rate_group[]">
                                                            <select
                                                                style="background-image: url('{{ asset('images/arrowDown.png') }}')"
                                                                class="form-select tourists h-10 w-22 w-full text-center border border-solid border-[#D5D5D5] outline-0 safari-select appearance-none pr-6 bg-no-repeat bg-[position:90%_center]"
                                                                onchange="updateRatesTableChangePeople()" data-name="tourists[]"
                                                                style="{{ ($isGroupBooking != '1') ? 'width:100px;' : 'width:200px;' }}">
                                                                <option>0</option>
                                                            </select>
                                                        </td>
                                                        @if($isGroupBooking != '1')
                                                            <td class="price bg-white border px-3 py-2 border-solid text-center">
                                                                <span
                                                                    class="tag font-semibold text-green-600 text-base">{{ moneyFormat(0.00) }}</span>
                                                            </td>
                                                            <td class="group bg-white border px-3 py-2 border-solid">
                                                                <select
                                                                    style="background-image: url('{{ asset('images/arrowDown.png') }}')"
                                                                    class="form-select permit-fee h-10 w-22 w-full text-center border border-solid border-[#D5D5D5] outline-0 safari-select appearance-none pr-6 bg-no-repeat bg-[position:90%_center]"
                                                                    onchange="permitFeeTableChangePeople()" data-name="permit_fee[]"
                                                                    style="pointer-events: none;">
                                                                    <option>0</option>
                                                                </select>
                                                            </td>
                                                            @if($isPartnerBooking == '1')
                                                                <td
                                                                    class="normal partner-fee bg-white border px-3 py-2 border-solid text-center font-medium text-black text-base">
                                                                    {{ moneyFormat(0.00) }}
                                                                </td>
                                                            @endif
                                                            <td
                                                                class="normal adnl-tax bg-white border px-3 py-2 border-solid text-center font-medium text-black text-base hidden">
                                                                {{ moneyFormat(0.00) }}
                                                            </td>
                                                        @endif
                                                        <td
                                                            class="normal adnl-charge bg-white border px-3 py-2 border-solid text-center font-medium text-black text-base">
                                                            {{ moneyFormat(0.00) }}
                                                        </td>
                                                        <td
                                                            class="final bg-white border px-3 py-2 border-solid text-center font-semibold text-green-600 text-base">
                                                            {{ moneyFormat(0.00) }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                                <tbody id="table-rate-groups-change-people"></tbody>
                                                <tfoot>
                                                    <tr class="w-full mt-0 bg-green-50 border border-solid">
                                                        <td
                                                            class="cost font-medium text-[#264F73] text-xl px-7 py-6 whitespace-nowrap">
                                                            Total Payable:</td>
                                                        <td class="total font-bold text-[#264F73] text-xl px-7 py-6 text-right"
                                                            colspan="7">
                                                            <span
                                                                class="total-before-service-commission-and-discount-change-people">{{ moneyFormat(0.00) }}</span>
                                                            <span class="taxes">(inclusive all taxes)</span>
                                                        </td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" id="update_booking_change_people_submit"
                                    class="themed-btn mt-4 btn-primary-small">Update & Confirm Booking</button>
                                <select id="slot_time" class="mt-4 form-custom-input bg-[#FAFAFA] max-w-48"
                                    style="display: none">
                                    <option value="">Select Time Slot</option>
                                    <option value="{{ $bookingM->tour_slot_time_id }}" selected
                                        data-seats="{{ $bookingM->slot_seats }}"
                                        data-tourists="{{ $bookingM->slot_all_tourists_count }}"
                                        data-bookable-status="{{ $bookingM->slot_bookable_status }}"
                                        data-booked="{{ $bookingM->available_tourists ?? 0 }}"></option>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Change Booking feature Modal end -->
    @endif

    <!-- Edit Booking feature Modal close -->

    <!-- New Booking payment Modal start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_admin" tabindex="-1" role="dialog" aria-hidden="true"
        data-pending-amount="{{ !empty($pendingPaymentLinkForTotal) ? $pendingPaymentLinkForTotal->amount : 0 }}"
        data-breakdown-url="{{ route(prefixedRouteName($routePrefix . 'booking.get-payment-link-items'), ['bookingId' => $bookingM->id]) }}">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Make Payment Admin</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <span class="text-xs text-[#ff0000] mb-2" id="validate"></span>
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form id="modal-affilate1-form" method="post"
                                action="{{ route(prefixedRouteName($routePrefix . 'booking.payment-new-booking')) }}"
                                onsubmit="return validateAndSubmitForm2(this)"
                                data-call-on-success="Payment successfully done">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method">
                                            <div class="payment-method-title text-lg font-semibold text-[#111827]">Payment
                                                Method:</div>
                                            <input name="bookingID" id="booking-id-admin-payment" type="text" hidden
                                                value="{{$bookingM->id}}">
                                            <input name="remaining_link_id" type="hidden"
                                                value="{{ !empty($pendingRemainingLink) ? $pendingRemainingLink->id : '' }}">
                                            <div class="form-field-wrp">
                                                <div class="form-group payment-condition-item mb-2">
                                                    @php
                                                       
                                                        if ($bookingM->service_commission == null || $bookingM->service_commission == "0.00") {
                                                            $subtotal = $bookingM->total - $bookingM->service_commission;
                                                        } else {
                                                            $subtotal = $makePaymentValueForCardFailed;
                                                        }
                                                        if (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
                                                            if($Refund_Amount > 0) {
                                                                $subtotal = $bookingM->total + $Refund_Amount - $paymentOnlyCheck;
                                                            } else {
                                                                $subtotal = $bookingM->total - $paymentOnlyCheck;
                                                            }
                                                        }
                                                        if ($bookingM->discount2_value > 0 && empty($trans) && $bookingM->is_affiliate == "Yes") {
                                                            $subtotal = $subtotal - $bookingM->discount2_value;
                                                        }
                                                        $subtotal = $subtotal + $payLaterAddons['addons_price'] + $customChargePrice['custom_charge_total'];
                                                        // When a pending remaining_payment link exists, every payment method here must show
                                                        // that same outstanding balance, not the full/original booking amount — Card and
                                                        // Pay with Link already get this override further below (once $totalV is computed),
                                                        // but Billable renders its own amount right here, BEFORE that override runs, so
                                                        // without repeating it here Billable showed the stale pre-override $subtotal (e.g.
                                                        // $75.00, the booking's remaining ticket total) while Card/Link correctly showed the
                                                        // actual $32.94 still owed.
                                                        if (!empty($pendingRemainingLink)) {
                                                            $subtotal = $pendingRemainingLink->amount;
                                                        }
                                                    @endphp
                                                    @if ($setting)
                                                        @if ($bookingM->is_billable == 1)
                                                            {{-- Billable ("Pay Later") only applies before the booking's first payment —
                                                            once any completed transaction exists, hide it. Mirrors the same rule in
                                                            #modal_affilate's copy of this block further up this file. --}}
                                                            <label {{ !empty($trans) ? 'hidden' : '' }}
                                                                for="pay-with-later"
                                                                class="checkbox-wrap flex items-center text-sm font-semibold text-[#111827]">
                                                                <input type="radio" name="payment_method" id="pay-with-later"
                                                                    class="checkset w-4 h-4 mr-2" value="Pay Later">
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission invoiced-amount py-1 px-2 bg-[#f1f1f1] text-xs inline-block rounded mr-1">{{ moneyFormat($subtotal) }}</span>
                                                                <span class="payment-method-text">Billable </span>
                                                                <span class="checkmark"></span>
                                                            </label>
                                                        @endif
                                                    @endif
                                                </div>
                                                @php
                                                    $forCardProcChargePercentageForOld = 0;
                                                    $serviceCommissionTotal = 0;
                                                    $paymentTotalForCard = 0;

                                                    foreach ($bookingT as $bt) {
                                                        if ($isGroupBooking == '1') {
                                                            $rate = $bt->rate + $bt->additional_charge + $bt->partner_fees_amount;
                                                            $per = roundout(($rate * ($bt->nat_fees_percentage) / 100), 2);
                                                            $serviceCommissionTotal += $per;
                                                        } else {
                                                            $rate = $bt->rate + $bt->additional_charge + $bt->permit_fee + $bt->partner_fees_amount;
                                                            $per = roundout(($rate * ($bt->nat_fees_percentage) / 100), 2);
                                                            $serviceCommissionTotal += $bt->tourists * $per;
                                                        }
                                                    }
                                                    
                                                    if ($bookingM->discount2_value > 0 && isset($bookingT[0]->nat_fees_percentage)) {
                                                        $subtotalWithoutAddOnsCustomCharge = $subtotal - $payLaterAddons['addons_price'] - $customChargePrice['custom_charge_total'];                            
                                                        $serviceCommissionTotal = roundout(($subtotalWithoutAddOnsCustomCharge * ($bookingT[0]->nat_fees_percentage) / 100), 2);
                                                        $totalV = $subtotal + $serviceCommissionTotal + $customChargePrice['pay_later_custom_charge_fees'];
                                                        if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
                                                            $paymentTotalForCard = $subtotal + $serviceCommissionTotal;
                                                        }
                                                    } else {
                                                        if ($serviceCommissionTotal > 0 && empty($trans) || $bookingM->via == "Payment Link") {
                                                            $totalV = $subtotal + $serviceCommissionTotal;
                                                        } else {
                                                            $totalV = $bookingM->total;
                                                        }
                                                        if (($trans != null) && $trans->payment_type == 0 && ($trans->via == "Invoiced" || $trans->via == "Cash")) {
                                                            $paymentTotalForCard = $subtotal + $serviceCommissionTotal;
                                                        }
                                                    }
                                                    if (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
                                                        if($Refund_Amount > 0 && $trans->via == "Cash") {
                                                                $totalV = $bookingM->total + $Refund_Amount - $paymentOnlyCheck;
                                                            } else {
                                                                $totalV = $bookingM->total - $paymentOnlyCheck;
                                                            }
                                                    }
                                                    $totalV = $totalV + $payLaterAddons['addons_fee'] + $tipAmounts['pay_later_tip_fees'];
                                                    if ($bookingM->discount2_value == 0) {
                                                         $totalV = $totalV + $customChargePrice['pay_later_custom_charge_fees'];
                                                    }
                                                    // When a pending remaining_payment link exists, show only that outstanding balance
                                                    if (!empty($pendingRemainingLink)) {
                                                        $subtotal = $pendingRemainingLink->amount;
                                                        $totalV   = $pendingRemainingLink->amount;
                                                        $platformFees = 0;
                                                    }
                                                @endphp
                                                @if($isPaymentLink && !$isSplitPaymentBooking)
                                                @can('payment-method-payment-link')
                                                    <div class="form-group payment-condition-item mb-2" id="payment-link-cp-option">
                                                        <label for="pay-with-link-cp" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-link-cp"
                                                                class="sr-only peer checkset w-4 h-4 mr-2" value="Payment Link">
                                                            <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                    <path d="M8.333 11.667a4.167 4.167 0 006.246.453l2.5-2.5a4.167 4.167 0 00-5.893-5.893l-1.433 1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    <path d="M11.667 8.333a4.167 4.167 0 00-6.246-.453l-2.5 2.5a4.167 4.167 0 005.893 5.893l1.426-1.426" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                                <span class="font-inter font-semibold text-black text-base flex-1">Pay with Link</span>
                                                                <span class="amount-text total-after-discount-and-service-commission link-cp-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($totalV) }}</span>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                                @endif
                                                @can('payment-method-cash')
                                                    <div {{ (!empty($trans) && $trans->payment_type == 1 && $trans->via == "Card") || !empty($pendingRemainingLink) ? 'hidden' : '' }} class="form-group payment-condition-item mb-2">

                                                        <!-- Pay with Cash -->
                                                        <label for="pay-with-cash1" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-cash1"
                                                                class="sr-only peer checkset w-4 h-4 mr-2" value="Cash" {{ ($trans != null) && $trans->payment_type == 1 && $trans->via == "Cash" ? 'Checked' : (($trans != null) && $trans->via == "Invoiced" ? 'Checked' : '') }}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_2188_2274)">
                                                                        <path
                                                                            d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path
                                                                            d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_2188_2274">
                                                                            <rect width="20" height="20" fill="white"></rect>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Cash</span>
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission cash-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base">
                                                                   @php
                                                                        if (empty($trans) && $cashPriceEnabled == 1) {
                                                                            $cashAmountDisplay = $isCashFees == 1
                                                                                ? $cashPrices['cashPriceTotal'] + $payLaterAddons['addons_price'] + $customChargePrice['custom_charge_total'] + $payLaterAddons['addons_fee'] + $customChargePrice['pay_later_custom_charge_fees'] + $tipAmounts['pay_later_tip_fees']
                                                                                : $cashPrices['cashPriceSubtotal'] + $payLaterAddons['addons_price'] + $customChargePrice['custom_charge_total'];
                                                                        } else {
                                                                            $cashAmountDisplay = $isCashFees == 1 ? $totalV : $subtotal;
                                                                        }
                                                                    @endphp
                                                                    {{ moneyFormat(sprintf('%.2f', ($cashAmountDisplay))) }}
                                                                </span>
                                                            </div>
                                                            <div class="mt-2" id="cash-paid-amount-div" {{ empty($trans) ? 'hidden' : ''}} {{ (!empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash") || $payLaterAddons['addons_total'] == 0 || $customChargePrice['custom_charge_total'] == 0 ? '' : 'hidden' }}>
                                                                <input type="number" name="cash_paid_amount"
                                                                    id="cash-paid-amount"
                                                                    class="form-control form-custom-input font-semibold border !border-[#ED9C45] placeholder:font-semibold placeholder:text-[#020817]  h-[40px]"
                                                                    placeholder="Paid amount" min="false" step="0.01" {{ (!empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash") || $payLaterAddons['addons_total'] == 0 || $customChargePrice['custom_charge_total'] == 0 ? '' : 'readonly' }}>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                                @can('payment-method-card')
                                                    <div class="form-group payment-condition-item mb-2 {{ ($totalV == 0) ? 'hidden' : '' }}">
                                                        <!-- Pay with Card -->
                                                        <label for="pay-with-card1" {{ ($trans != null) && $trans->payment_type == 1 && ($trans->via == "Cash") ? 'hidden' : (($trans != null) && $trans->via == "Invoiced" ? 'hidden' : '') }}
                                                            class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-card1"
                                                                class="sr-only peer checkset w-4 h-4 mr-2" value="Card" {{ !empty($trans) && $trans->payment_type == 1 && $trans->via == "Card" ? 'Checked' : ''}} {{ empty($trans) ? 'Checked' : ''}}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                                    <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round" />
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Card</span>
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission card-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($totalV) }}</span>
                                                            </div>
                                                            <input id="hidden_total_for_admin" type="hidden"
                                                                value="{{ $totalV }}">
                                                            @php
                                                            if (!empty($trans) && $trans->payment_type == 1 && $bookingM->total > $paymentOnlyCheck) {
                                                                $totalAmountPay = $bookingM->total - $paymentOnlyCheck;
                                                                $platformFees = roundout(($totalAmountPay * ($bookingT[0]->stripe_fees_percentage) / 100), 2);
                                                            } else {
                                                                $platformFees = $serviceCommissionTotal;
                                                            }
                                                            @endphp
                                                            <input id="hidden_fees_for_admin_make_payment" type="hidden"
                                                                value="{{ $platformFees + $payLaterAddons['addons_fee'] + $customChargePrice['pay_later_custom_charge_fees'] + $tipAmounts['pay_later_tip_fees'] }}">
                                                        </label>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            {{-- <div id="card-element" class="stripe-card-element">
                                                <!-- Stripe.js injects the Card Element -->
                                            </div> --}}

                                            @if ($isPartnerBooking != 1 && ($pricedAddons['addons_final_total'] == 0 && $payLaterAddons['addons_total'] == 0) && $customChargePrice['custom_charge_total'] == 0 && $tipAmounts['pay_later_tip_total'] == 0)
                                                <div class="flex flex-wrap -mx-3 items-start {{ !empty($pendingRemainingLink) ? 'hidden' : '' }}">
                                                    <div class="w-1/2 max-w-full px-3 split-payment-div">
                                                        <div class="mt-0">
                                                            <label class="form-custom-label !font-semibold">Divided by</label>
                                                            <div class="mt-0">
                                                                <select class="form-custom-input" id="dividedBy_2"
                                                                    name="dividedBy">
                                                                    <option selected>Please select option</option>
                                                                    <option value="2">2</option>
                                                                    <option value="3">3</option>
                                                                    <option value="4">4</option>
                                                                    <option value="5">5</option>
                                                                    <option value="6">6</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="w-1/2 max-w-full px-3 split-payment-div">
                                                        <div id="checkcard" class="mt-0">
                                                            <label class="form-custom-label !font-semibold">&nbsp</label>
                                                            <div class="form-group payment-condition-item">
                                                                <label for="split-payment"
                                                                    class="checkbox-wrap checked-item form-custom-label !font-semibold whitespace-nowrap !flex items-center">
                                                                    <input type="checkbox" name="split_checkbox"
                                                                        id="split-payment1" value="1"
                                                                        class="w-4 h-4 mr-2 relative checkset">
                                                                    <span
                                                                        class="amount-text split-check bg-[#E5E7EB] py-0.5 px-1 rounded font-semibold text-[#4B5563] mr-2">{{ moneyFormat(0.00) }}</span>
                                                                    Split Payment
                                                                </label>
                                                            </div>
                                                            <div class="w-full mobile-full">
                                                                <div id="split_value1" class="form-group mb-2" hidden>
                                                                    <div class="discount-code-input">
                                                                        <input type="float" name="split_value"
                                                                            id="split_value_field1"
                                                                            class="form-control form-custom-input"
                                                                            placeholder="Split amount" min="1" step="0.01">
                                                                        <span class="field-icon per-icon"></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                            <!-- Pending items summary row — shown when a pending remaining_payment link exists -->
                                            <div id="pending-items-summary-cp" class="{{ empty($pendingRemainingLink) ? 'hidden' : '' }} mb-1">
                                                <div class="flex items-center justify-between px-2 py-1 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                                    <div class="flex items-center gap-1">
                                                        <span>Pending payment</span>
                                                        <button type="button" id="btn-cp-payment-breakdown-inline"
                                                            class="text-[#264f73] hover:text-[#1a3a56]"
                                                            title="View breakdown">
                                                            <i class="fa fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                    <span id="pending-items-amount-cp" class="font-semibold">{{ !empty($pendingRemainingLink) ? moneyFormat($pendingRemainingLink->amount) : '' }}</span>
                                                </div>
                                            </div>
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md mt-2">
                                                <div class="total-payment">Total payment</div>
                                                <div
                                                    class="total-price total-after-discount-and-service-commission set-total">
                                                    {{ moneyFormat($subtotal) }}
                                                </div>
                                            </div>
                                            <div class="makeaayment-wrap mt-2">
                                                <div class="form-group">
                                                    <input type="submit" id="btn-make-payment1"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    <!-- New booking modal close -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_cart_payment" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Make Cart Payment</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <span id="validate" style="color: red"></span>
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form id="modal-cart-payment-form" method="post"
                                action="{{ route(prefixedRouteName($routePrefix . 'booking.payment-cart-bookings')) }}"
                                onsubmit="return validateAndSubmitForm5(this)"
                                data-call-on-success="Payment successfully done">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method">
                                            <div class="payment-method-title text-base font-semibold text-[#111827]">Payment
                                                Method:</div>
                                            <input name="bookingID" id="booking-id-cart-payment" type="text" hidden
                                                value="{{$bookingM->id}}">
                                            <input name="cartID" id="cartID" type="text" hidden
                                                value="{{$bookingM->cart_id}}">
                                            <div class="form-field-wrp">
                                                <div class="form-group payment-condition-item mb-2">
                                                    @if ($setting)
                                                        @if ($bookingM->is_billable == 1)
                                                            <label for="pay-with-late2"
                                                                class="checkbox-wrap text-sm font-semibold text-[#111827]"><span
                                                                    class="amount-text total-after-discount-and-service-commission invoiced-amount py-1 px-2 bg-[#f1f1f1] text-xs inline-block rounded mr-1">{{ moneyFormat($cartPaymentTotalForCash) }}</span>
                                                                <span class="payment-method-text">Billable </span>
                                                                <input type="radio" name="payment_method_cart_payment"
                                                                    id="pay-with-late2" class="checkset2" value="Pay Later">
                                                                <span class="checkmark"></span>
                                                            </label>
                                                        @endif
                                                    @endif
                                                </div>
                                                @can('payment-method-cash')
                                                    <div class="form-group payment-condition-item mb-2">

                                                        <label for="pay-with-cash2" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method_cart_payment"
                                                                id="pay-with-cash2" class="sr-only peer checkset2 w-4 h-4 mr-2"
                                                                value="Cash">
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_2188_2274)">
                                                                        <path
                                                                            d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path
                                                                            d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_2188_2274">
                                                                            <rect width="20" height="20" fill="white"></rect>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Cash</span>
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission cash-amount3 font-inter font-bold text-[rgba(38,79,115,1)] text-base">
                                                                    @php
                                                                        $cartCashAmountDisplay = $cashPriceEnabled == 1
                                                                            ? ($isCashFees == 1 ? $cartCashNewPricingTotal : $cartCashNewPricingSubtt)
                                                                            : ($isCashFees == 1 ? $cartPaymentTotalForCard : $cartPaymentTotalForCash);
                                                                    @endphp
                                                                    
                                                                    {{ moneyFormat(sprintf('%.2f', $cartCashAmountDisplay)) }}</span>
                                                            </div>

                                                        </label>
                                                    </div>
                                                @endcan
                                                @can('payment-method-card')
                                                    <div class="form-group payment-condition-item mb-2">
                                                        <label for="pay-with-card2" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method_cart_payment"
                                                                id="pay-with-card2" class="sr-only peer checkset2 w-4 h-4 mr-2"
                                                                value="Card" {{ empty($trans) ? 'Checked' : ''}}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                                    <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round" />
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Card</span>
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission card-amount3 font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($cartPaymentTotalForCard) }}</span>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md mt-2">
                                                <div class="total-payment">Total payment</div>
                                                <div class="total-price total-cart-payment">
                                                    {{ moneyFormat($cartPaymentTotalForCard) }}
                                                </div>
                                            </div>
                                            <div class="makeaayment-wrap mt-2">
                                                <div class="form-group col-12 mb-0">
                                                    <input type="submit" id="btn-make-cart-payment"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    <!-- Change payment type Modal start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_change_payment_type" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Make Payment</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <span class="text-xs text-[#ff0000]" id="validate"></span>
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form id="modal-change-payment-type-form" method="post" action=""
                                onsubmit="return validateAndSubmitForm4(this)"
                                data-call-on-success="Payment successfully done">
                                @csrf
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method">
                                            <div class="payment-method-title text-base font-semibold text-[#111827]">Payment
                                                Method:</div>
                                            <input name="bookingID" id="booking-id-change-payment-type" type="text" hidden
                                                value="{{$bookingM->id}}">
                                            <div class="form-field-wrp">
                                                @if ($setting)
                                                    @if ($bookingM->is_billable == 1)
                                                        <div class="form-group payment-condition-item mb-2">
                                                            <label {{ !empty($trans) && $trans->payment_type == 0 && $trans->via == "Invoiced" ? 'hidden' : '' }} for="pay-with-later3"
                                                                class="checkbox-wrap block cursor-pointer bg-[#fcce22] hover:bg-[#fcce22] overflow-hidden rounded-md">
                                                                <input type="radio" name="payment_method" id="pay-with-later3"
                                                                    class="sr-only peer checkset1 w-4 h-4 mr-2" value="Pay Later">
                                                                 <div
                                                                class="w-full h-12 bg-[#ff03] hover:bg-[#ff03] border rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors bg-white border-gray-300">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_2188_2274)">
                                                                        <path
                                                                            d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path
                                                                            d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_2188_2274">
                                                                            <rect width="20" height="20" fill="white"></rect>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>
                                                                 <span class="font-inter font-semibold text-black text-base flex-1 payment-method-text">Billable </span>
                                                                    <span
                                                                    class="amount-text total-after-discount-and-service-commission invoiced-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base">
                                                                    @php
                                                                        if ($cashPriceEnabled == 1) {
                                                                            $billableAmountDisplay = $cardPrices['cardPriceSubtotal'];
                                                                        } else {
                                                                            $billableAmountDisplay = $subtotal;
                                                                        }
                                                                    @endphp
                                                                    {{ moneyFormat($billableAmountDisplay) }}</span>
                                                               
                                                                 </div>                       
                                                                <span class="checkmark"></span>
                                                            </label>
                                                        </div>
                                                    @endif
                                                @endif
                                                @can('payment-method-cash')
                                                    <div class="form-group payment-condition-item mb-2">
                                                        <label {{ !empty($trans) && $trans->payment_type == 0 && $trans->via == "Cash" ? 'hidden' : '' }} for="pay-with-cash3"
                                                            class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-cash3"
                                                                class="sr-only peer checkset1 w-4 h-4 mr-2" value="Cash"
                                                                checked>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_2188_2274)">
                                                                        <path
                                                                            d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path
                                                                            d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_2188_2274">
                                                                            <rect width="20" height="20" fill="white"></rect>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Cash</span>
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission cash-amount2 font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat(paymentAmountForChangePaymentType($subtotal, $paymentTotalForCard, $paymentTotalForCardAffiliate, $bookingM->is_affiliate, $cashPrices, $cashPriceEnabled)) }}</span>
                                                            </div>
                                                            <div class="mt-2">
                                                                <input type="number" name="cash_paid_amount"
                                                                    id="cash-paid-amount2"
                                                                    class="form-control form-custom-input"
                                                                    placeholder="Paid amount" min="false" step="0.01" readonly>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                                @can('payment-method-card')
                                                    <div class="form-group payment-condition-item mb-2">
                                                        <label for="pay-with-card3" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="pay-with-card3"
                                                                class="sr-only peer checkset1 w-4 h-4 mr-2" value="Card"
                                                                checked>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                                    <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round" />
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Card</span>
                                                                <span
                                                                    class="amount-text total-after-discount-and-service-commission card-amount2 font-inter font-bold text-[rgba(38,79,115,1)] text-base">
                                                                    
                                                                    @php
                                                                        if ($cashPriceEnabled == 1) {
                                                                            $cardAmountDisplay = $cardPrices['cardPriceTotal'];
                                                                        } else {
                                                                            $cardAmountDisplay = ($bookingM->is_affiliate == 'Yes') ? $paymentTotalForCardAffiliate : $paymentTotalForCard;
                                                                        }
                                                                    @endphp
                                                                    {{ moneyFormat(sprintf('%.2f', ($cardAmountDisplay))) }}
                                                                </span>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md mt-2">
                                                <div class="total-payment">Total payment</div>
                                                <div class="total-price total-after-discount-and-service-commission set-total1">
                                                    @can('payment-method-card')
                                                        {{ moneyFormat($cardAmountDisplay) }}
                                                    @elsecan('payment-method-cash')
                                                        {{ moneyFormat(paymentAmountForChangePaymentType($subtotal, $paymentTotalForCard, $paymentTotalForCardAffiliate, $bookingM->is_affiliate, $cashPrices, $cashPriceEnabled)) }}
                                                    @elseif ($setting && $bookingM->is_billable == 1)
                                                        {{ moneyFormat($billableAmountDisplay) }}
                                                    @endcan
                                                </div>
                                            </div>
                                            <div class="makeaayment-wrap mt-2">
                                                <div class="form-group col-12 mb-0">
                                                    <input type="submit" id="btn-make-payment2"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    <!-- Change payment type Modal close -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-reschedule" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Reschedule Booking</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <form method="post"
                        action="{{ route(prefixedRouteName($routePrefix . 'booking.reschedule'), ['bookingId' => $bookingM->id]) }}"
                        data-call-on-success="rescheduled" id="rescheduleform">
                        @csrf

                        <input type="hidden" id="tour_package_id" value="{{ $bookingM->tour_package_id }}">
                        <input type="hidden" id="tour_date_time" value="{{ $bookingM->date . ' ' . $bookingM->time_from }}">
                        <input type="hidden" name="request_type" value="Reschedule">
                        <input type="hidden" id="booking_tourists" value="{{ $bookingM->tourists }}">

                        <div class="mb-3 min-[400px]:flex items-center">
                            <label class="form-label text-sm font-semibold text-[#111827] mr-2 min-w-32">Send Mail</label>
                            <input type="checkbox" name="send_rb_mail" value="Yes" class="align-text-top w-4 h-4" />
                        </div>

                        <div class="mb-3 min-[400px]:flex items-center">
                            <label for="tour_slot_date"
                                class="form-label text-sm font-semibold text-[#111827] mr-2 whitespace-nowrap min-w-32">Select
                                Date</label>
                            <input id="tour_slot_date" name="tour_slot_date"
                                class="form-control form-custom-input bg-[#FAFAFA] !pl-10"
                                value="{{ date('l, F j, Y', strtotime($bookingM->date)) }}">
                        </div>

                        <div class="mb-3 package-has-slots-reschedule">
                            <div class="min-[400px]:flex items-center">
                                <label for="tour_slot_time"
                                    class="form-label text-sm font-semibold text-[#111827] mr-2 whitespace-nowrap min-w-32">Select
                                    Time Slot</label>
                                <select name="tour_slot_time" id="tour_slot_time"
                                    class="form-select form-custom-input bg-[#FAFAFA]" required></select>
                            </div>
                        </div>

                        <div class="text-end"><button type="submit" id="reschedulebtn"
                                class="btn btn-main btn-main-sm btn-primary-small w-full">Reschedule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal-switch-package" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Switch Package</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <form method="post"
                        action="{{ route(prefixedRouteName($routePrefix . 'booking.switch-package-booking')) }}"
                        onsubmit="return showMakePaymentForSwitchPackage(this)" id="switchForm">
                        @csrf
                        <input name="bookingID" id="booking-id-switch-package" hidden value="{{$bookingM->id}}" />
                        <input id="old_total_amount" name="old_total_amount" type="hidden" value="{{ $totalV }}">
                        <div class="mb-3 min-[480px]:flex items-center">
                            <label for="tour_package_switch"
                                class="form-label text-sm font-semibold text-[#111827] mr-2 whitespace-nowrap min-w-32">Select
                                Package</label>
                            <select name="tour_package_switch" id="tour_package_switch"
                                class="form-select form-custom-input bg-[#FAFAFA]" required>
                                <option value="">Select Tour Package</option>
                                @if($switchPackage == '1')
                                    @foreach($tourPackages as $tourPackage)
                                        <option value="{{ $tourPackage->id }}">{{ $tourPackage->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="mb-3 min-[480px]:flex items-center">
                            <label for="tour_slot_date_switch"
                                class="form-label text-sm font-semibold text-[#111827] mr-2 whitespace-nowrap min-w-32">Select
                                Date</label>
                            <input id="tour_slot_date_switch" name="tour_slot_date_switch"
                                class="form-control form-custom-input bg-[#FAFAFA]"
                                value="{{ date('l, F j, Y', strtotime($bookingM->date)) }}">
                        </div>

                        <div class="mb-3 package-has-slots">
                            <div class="min-[480px]:flex items-center">
                                <label for="tour_slot_time_switch"
                                    class="form-label text-sm font-semibold text-[#111827] mr-2 whitespace-nowrap min-w-32">Select
                                    Time Slot</label>
                                <select name="tour_slot_time_switch" id="tour_slot_time_switch"
                                    class="form-select form-custom-input bg-[#FAFAFA]" required>
                                    <option value="">Select Slot Time</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end"><button type="submit" id="switchpackagebtn"
                                class="btn btn-main btn-main-sm btn-primary-small w-full">Submit</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Charge Modal Start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="customChargeBooking" tabindex="-1" role="dialog" aria-hidden="true"
        data-pending-amount="{{ !empty($pendingPaymentLinkForTotal) ? $pendingPaymentLinkForTotal->amount : 0 }}"
        data-pending-link-id="{{ !empty($pendingRemainingLink) ? $pendingRemainingLink->id : '' }}">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Custom Charge</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-5">
                    @php $submitCustomCharge = ''; @endphp
                    @if(empty($trans))
                        @php $submitCustomCharge = 'return showMakePaymentForCustomCharge(this)'; @endphp
                    @endif
                    <form method="post" id="customChargeForm"
                        action="{{ route(prefixedRouteName($routePrefix . 'booking.add-custom-charge'), ['bookingId' => $bookingM->id])}}"
                        {{$submitCustomCharge}}>
                        @csrf
                        <input type="hidden" name="custom_charge_booking" value="1">
                        <div class="mb-2">
                            <div class="form-check-wrap">
                                <div id="custom_charge_value">
                                    <label class="form-check-label block form-custom-label mb-1"
                                        for="custom_charge_value_booking">Amount</label>
                                    <input class="form-custom-input" type="float" min="1" step="0.01"
                                        id="custom_charge_value_booking" name="custom_charge_amount" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="form-check-wrap">
                                <label class="checkbox-wrap get-email-updates form-custom-label mb-1 !flex items-center">
                                    <input class="w-4 h-4 mr-2" type="checkbox" name="taxable" id="is_taxed" value="1"
                                        checked>
                                    <span class="checkmark"></span>
                                    Taxed
                                </label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="custom_charge_comment" class="form-label form-custom-label mb-1">Add a note</label>
                            <textarea name="comment" id="custom_charge_comment" rows="4" cols="50"
                                class="cancel-notes form-custom-input min-h-16"
                                placeholder="Please specify the reason for custom charge" required></textarea>
                        </div>
                        <input type="hidden" class="custom-charge-fees" name="custom_charge_fees" value="">
                        <input type="hidden" class="custom-charge-total-amount" name="custom_charge_total" value="">
                        <span class="block mb-3 text-sm font-semibold text-[#111827]">Amount to be paid: <span
                                class="final-amount py-1 px-2 bg-[#f1f1f1] text-xs inline-block rounded mr-1">{{ currencyFormation(false, true) }}0.00</span></span>

                        <div><button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small w-full"
                                id="submitCustomCharge" type="button">Submit</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Custom Charge Modal Close -->

    <!-- Add Custom Charge Modal start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="bookingAddCustomCharge" tabindex="-1" role="dialog" aria-hidden="true"
        data-breakdown-url="{{ route(prefixedRouteName($routePrefix . 'booking.get-payment-link-items'), ['bookingId' => $bookingM->id]) }}">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h4 class="text-lg font-semibold text-[#111827]">Custom Charge Make Payment</h4>
                        <button type="button" id="btn-custom-charge-payment-breakdown"
                            class="hidden text-[#264f73] hover:text-[#1a3a56]"
                            title="View payment breakdown">
                            <i class="fa fa-info-circle text-lg"></i>
                        </button>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    @php $submitCustomCharge = ''; @endphp
                    @if(!empty($trans))
                        @php $submitCustomCharge = 'return showMakePaymentForCustomCharge(this)'; @endphp
                    @endif
                    <span class="text-xs text-[#ff0000]" id="validate"></span>
                    <div class="payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form id="modal-custom-charge-form" method="post"
                                data-call-on-success="Payment successfully done"
                                action="{{ route(prefixedRouteName($routePrefix . 'booking.add-custom-charge'), ['bookingId' => $bookingM->id])}}"
                                onsubmit="{{$submitCustomCharge}}">
                                @csrf
                                <input type="hidden" name="combined_charge_total" id="combined-charge-total" value="0">
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method">
                                            <div class="payment-method-title text-base font-semibold text-[#111827] mb-1">
                                                Payment Method:</div>
                                            <input name="bookingID" id="booking-id-custom-charge" type="text" hidden
                                                value="{{$bookingM->id}}">
                                            <div class="form-field-wrp">
                                                @if(!empty($bookingM->ota_booking_id) && empty($otaCustomChargeLockedMethod))
                                                    {{-- OTA bookings (TripAdvisor/GetYourGuide/Headout/etc.) are already paid via the OTA.
                                                         Any custom charge is collected on-site directly from the guest, so staff choose
                                                         Cash or Card here — but only for the first one; once this booking has an active
                                                         custom charge, $otaCustomChargeLockedMethod is set and the branches below (shared
                                                         with genuine Cash/Card bookings) take over so every later charge stays locked to
                                                         that same method, same as the rest of the system (one payment method per booking). --}}
                                                    @can('payment-method-cash')
                                                        <div class="form-group payment-condition-item mb-2">
                                                            <label for="custom-charge-pay-with-cash"
                                                                class="checkbox-wrap block cursor-pointer">
                                                                <input type="radio" name="payment_method"
                                                                    id="custom-charge-pay-with-cash"
                                                                    class="sr-only peer checkset w-4 h-4 mr-2" value="Cash">
                                                                <div
                                                                    class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                    <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                        viewBox="0 0 20 20" fill="none"
                                                                        xmlns="http://www.w3.org/2000/svg">
                                                                        <g clip-path="url(#clip0_2188_2274)">
                                                                            <path
                                                                                d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                                stroke="#60BEBD" stroke-width="1.5"
                                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                            </path>
                                                                            <path
                                                                                d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                                stroke="#60BEBD" stroke-width="1.5"
                                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                            </path>
                                                                            <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                            </path>
                                                                        </g>
                                                                        <defs>
                                                                            <clipPath id="clip0_2188_2274">
                                                                                <rect width="20" height="20" fill="white"></rect>
                                                                            </clipPath>
                                                                        </defs>
                                                                    </svg>
                                                                    <span
                                                                        class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                        with Cash</span>
                                                                    <span
                                                                        class="amount-text custom-charge-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                                </div>
                                                                <div class="mt-2">
                                                                    <input type="number" name="cash_paid_amount"
                                                                        id="cash-paid-amount"
                                                                        class="form-control form-custom-input border !border-[#ED9C45] placeholder:font-semibold placeholder:text-[#020817]  h-[40px]"
                                                                        placeholder="Paid amount" min="false" step="0.01">
                                                                </div>
                                                            </label>
                                                        </div>
                                                    @endcan
                                                    @can('payment-method-card')
                                                        <div class="form-group payment-condition-item mb-2">
                                                            <label for="custom-charge-pay-with-card"
                                                                class="checkbox-wrap block cursor-pointer">
                                                                <input type="radio" name="payment_method"
                                                                    id="custom-charge-pay-with-card"
                                                                    class="sr-only peer checkset w-4 h-4 mr-2" value="Card">
                                                                <div
                                                                    class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                    <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                        viewBox="0 0 20 20" fill="none"
                                                                        xmlns="http://www.w3.org/2000/svg">
                                                                        <path
                                                                            d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                            stroke-width="1.5" stroke-linecap="round"
                                                                            stroke-linejoin="round" />
                                                                    </svg>
                                                                    <span
                                                                        class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                        with Card</span>
                                                                    <span
                                                                        class="amount-text custom-charge-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    @endcan
                                                @elseif(!empty($trans) && $trans->via == "Invoiced")
                                                    <div class="form-group payment-condition-item mb-2">
                                                        <label for="custom-charge-pay-with-billable"
                                                            class="checkbox-wrap flex items-center checked-item text-sm font-semibold text-[#111827]">
                                                            <input type="radio" name="payment_method"
                                                                id="custom-charge-pay-with-billable"
                                                                class="checkset w-4 h-4 mr-2" value="Invoiced">
                                                            <span
                                                                class="amount-text custom-charge-amount py-1 px-2 bg-[#f1f1f1] text-xs inline-block rounded mr-1"></span>
                                                            Billable
                                                            <span class="checkmark"></span>
                                                        </label>
                                                    </div>
                                                @elseif(!empty($trans) && ($trans->via == "Cash" || $otaCustomChargeLockedMethod == "Cash"))
                                                    @can('payment-method-cash')
                                                        <div class="form-group payment-condition-item mb-2">
                                                            <label for="custom-charge-pay-with-cash"
                                                                class="checkbox-wrap block cursor-pointer">
                                                                <input type="radio" name="payment_method"
                                                                    id="custom-charge-pay-with-cash"
                                                                    class="sr-only peer checkset w-4 h-4 mr-2" value="Cash" {{ ($trans != null) && $trans->via == "Cash" ? 'Checked' : ((($trans != null) && $trans->via == "Invoiced") || $otaCustomChargeLockedMethod == "Cash" ? 'Checked' : '') }}>
                                                                <div
                                                                    class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                    <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                        viewBox="0 0 20 20" fill="none"
                                                                        xmlns="http://www.w3.org/2000/svg">
                                                                        <g clip-path="url(#clip0_2188_2274)">
                                                                            <path
                                                                                d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                                stroke="#60BEBD" stroke-width="1.5"
                                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                            </path>
                                                                            <path
                                                                                d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                                stroke="#60BEBD" stroke-width="1.5"
                                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                            </path>
                                                                            <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                            </path>
                                                                        </g>
                                                                        <defs>
                                                                            <clipPath id="clip0_2188_2274">
                                                                                <rect width="20" height="20" fill="white"></rect>
                                                                            </clipPath>
                                                                        </defs>
                                                                    </svg>
                                                                    <span
                                                                        class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                        with Cash</span>
                                                                    <span
                                                                        class="amount-text custom-charge-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                                </div>
                                                                <div class="mt-2">
                                                                    <input type="number" name="cash_paid_amount"
                                                                        id="cash-paid-amount"
                                                                        class="form-control form-custom-input border !border-[#ED9C45] placeholder:font-semibold placeholder:text-[#020817]  h-[40px]"
                                                                        placeholder="Paid amount" min="false" step="0.01" {{ (!empty($trans) && $trans->payment_type == 1 && $trans->via == "Cash") || $payLaterAddons['addons_total'] == 0 ? '' : 'readonly' }}>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    @endcan
                                                @elseif(!empty($trans) && $trans->via == "Card")
                                                    @if($isPaymentLink == 1 && !$isSplitPaymentBooking)
                                                        @can('payment-method-payment-link')
                                                            <div class="form-group payment-condition-item mb-2">
                                                                <label for="custom-charge-pay-with-link"
                                                                    class="checkbox-wrap block cursor-pointer">
                                                                    <input type="radio" name="payment_method"
                                                                        id="custom-charge-pay-with-link"
                                                                        class="sr-only peer checkset w-4 h-4 mr-2"
                                                                        value="Pay Later">
                                                                    <div
                                                                        class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                        <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                                            <path d="M7.5 12.5L12.5 7.5" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                            <path d="M9.16667 5.83333L9.55833 5.44167C10.3092 4.69074 11.3339 4.27002 12.4017 4.27002C13.4694 4.27002 14.4942 4.69074 15.245 5.44167C15.9959 6.1926 16.4167 7.21731 16.4167 8.285C16.4167 9.35269 15.9959 10.3774 15.245 11.1283L14.8533 11.52" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                            <path d="M10.8333 14.1667L10.4417 14.5583C9.69074 15.3093 8.66603 15.73 7.59833 15.73C6.53064 15.73 5.50593 15.3093 4.755 14.5583C4.00407 13.8074 3.58334 12.7827 3.58334 11.715C3.58334 10.6473 4.00407 9.62259 4.755 8.87167L5.14667 8.48" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                                        </svg>
                                                                        <span
                                                                            class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                            with Link</span>
                                                                        <span
                                                                            class="amount-text custom-charge-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                                    </div>
                                                                </label>
                                                            </div>
                                                        @endcan
                                                    @endif
                                                    @can('payment-method-card')
                                                        <div class="form-group payment-condition-item mb-2">
                                                            <label for="custom-charge-pay-with-card"
                                                                class="checkbox-wrap block cursor-pointer">
                                                                <input type="radio" name="payment_method"
                                                                    id="custom-charge-pay-with-card"
                                                                    class="sr-only peer checkset w-4 h-4 mr-2" value="Card" {{ (!empty($trans) && $trans->via == "Card") || $otaCustomChargeLockedMethod == "Card" ? 'Checked' : ''}}>
                                                                <div
                                                                    class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                    <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                        viewBox="0 0 20 20" fill="none"
                                                                        xmlns="http://www.w3.org/2000/svg">
                                                                        <path
                                                                            d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round" />
                                                                        <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                            stroke-width="1.5" stroke-linecap="round"
                                                                            stroke-linejoin="round" />
                                                                    </svg>
                                                                    <span
                                                                        class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                        with Card</span>
                                                                    <span
                                                                        class="amount-text custom-charge-amount font-inter font-bold text-[rgba(38,79,115,1)] text-base"></span>
                                                                </div>
                                                            </label>
                                                        </div>
                                                    @endcan
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            <!-- Pending items summary row — shown by JS when pendingAmount > 0 -->
                                            <div id="pending-items-summary" class="hidden mb-1">
                                                <div class="flex items-center justify-between px-2 py-1 bg-amber-50 border border-amber-200 rounded text-xs text-amber-700">
                                                    <div class="flex items-center gap-1">
                                                        <span>Pending payment</span>
                                                        <button type="button" id="btn-custom-charge-payment-breakdown-inline"
                                                            class="text-[#264f73] hover:text-[#1a3a56]"
                                                            title="View breakdown">
                                                            <i class="fa fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                    <span id="pending-items-amount" class="font-semibold"></span>
                                                </div>
                                            </div>
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md mt-2">
                                                <div class="total-payment">Total payment</div>
                                                <div class="total-price custom-charge-total"></div>
                                            </div>
                                            <div class="makeaayment-wrap mt-2">
                                                <div class="form-group">
                                                    <input type="submit" id="btn-make-custom-charge"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    {{-- #remainingItemsBreakdownModal is created dynamically by JS (appended to <body>) to avoid CSS-transform parent issues with position:fixed --}}
    <!-- Refund Custom Charge Modal Start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="refundCustomChargeBooking" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Refund Custom Charge</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div id="Partial-delete" class="mb-2 text-xs text-[#ff0000]"></div>
                    <div id="cc-pending-note" class="hidden mb-3 text-xs font-medium" style="color:#92400E;background:#FEF3C7;border:1px solid #FCD34D;border-radius:6px;padding:8px 10px">
                        The pending (Unpaid) charge will be automatically cancelled. Select a refund type for the paid charge(s) below.
                    </div>

                    <div class="form-check-wrap mb-2">
                        <div class="form-check form-check-inline flex items-center">
                            <input class="w-4 h-4 mr-2 cc-refund-radio" type="radio" name="refund_type" id="cc1"
                                value="full_refund">
                            <label class="form-check-label text-sm font-semibold text-[#111827]" for="cc1">Full
                                Refund</label>
                        </div>
                    </div>
                    <div class="form-check-wrap mb-2">
                        <div class="form-check form-check-inline flex items-center">
                            <input class="w-4 h-4 mr-2 cc-refund-radio" type="radio" name="refund_type" id="cc2"
                                value="100_refund">
                            <label class="form-check-label text-sm font-semibold text-[#111827]" for="cc2">Weather / 100%
                                Refund</label>
                        </div>
                    </div>
                    <div class="form-check-wrap mb-2">
                        <div class="form-check form-check-inline flex items-center">
                            <input class="w-4 h-4 mr-2 cc-refund-radio" type="radio" name="refund_type" id="cc3"
                                value="dont_refund">
                            <label class="form-check-label text-sm font-semibold text-[#111827]" for="cc3">Don't
                                Refund</label>
                        </div>
                    </div>
                    <div class="text-center"><button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                            id="refund_custom_charge">Submit</button></div>
                </div>
            </div>
        </div>
    </div>
    <!-- New booking modal close -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_addon_payment" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Make Addons Payment</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="addon-payment-section payment-booking-sec">
                        <div class="tourist-booking-sidebar">
                            <form id="modal-addons-payment-form" method="post" action="javascript:void(0);"
                                onsubmit="validateAndSubmitAddonsPaymentForm(event)">
                                @csrf
                                <div class="flex flex-wrap -mx-3 addons-payment-form">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method">
                                            <div class="payment-method-title text-base font-semibold text-[#111827] mb-1">
                                                Payment Method:</div>
                                            <div class="form-field-wrp">
                                                <div class="form-group payment-condition-item mb-2" {{ $trans == null ? "" : "hidden" }}>
                                                    <label for="pay-with-later"
                                                        class="checkbox-wrap flex items-center checked-item text-sm font-semibold text-[#111827]">
                                                        <input type="radio" name="payment_method" id="addon-pay-with-later"
                                                            class="checkset w-4 h-4 mr-2" value="Pay Later" {{ $trans == null ? "checked" : "" }}>
                                                        <span
                                                            class="amount-text py-1 px-2 bg-[#f1f1f1] text-xs inline-block rounded mr-1">{{ moneyFormat($subtotal) }}</span>
                                                        <span
                                                            class="payment-method-text">{{ $bookingM->is_billable == 1 ? "Billable" : "Pay Later" }}</span>
                                                        <span class="checkmark"></span>
                                                    </label>
                                                </div>
                                                @can('payment-method-cash')
                                                    <div class="form-group payment-condition-item mb-2" {{ $trans != NULL && $trans->via == "Cash" ? "" : "hidden" }}>
                                                        <label for="pay-with-cash" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="addon-pay-with-cash"
                                                                class="sr-only peer checkset w-4 h-4 mr-2" value="Cash" {{ $trans != NULL && $trans->via == "Cash" ? "checked" : "" }}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <g clip-path="url(#clip0_2188_2274)">
                                                                        <path
                                                                            d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path
                                                                            d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699"
                                                                            stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                        <path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5"
                                                                            stroke-linecap="round" stroke-linejoin="round">
                                                                        </path>
                                                                    </g>
                                                                    <defs>
                                                                        <clipPath id="clip0_2188_2274">
                                                                            <rect width="20" height="20" fill="white"></rect>
                                                                        </clipPath>
                                                                    </defs>
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Cash</span>
                                                                <span
                                                                    class="amount-text font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat(paymentAmountForChangePaymentType($subtotal, $paymentTotalForCard, $paymentTotalForCardAffiliate, $bookingM->is_affiliate,  $cashPrices, $cashPriceEnabled)) }}</span>
                                                            </div>

                                                        </label>
                                                    </div>
                                                @endcan
                                                @can('payment-method-card')
                                                    <div class="form-group payment-condition-item mb-2" style="margin-top:10px;"
                                                        {{ $trans != NULL && $trans->via == "Card" ? "" : "hidden" }}>
                                                        <label for="pay-with-card" class="checkbox-wrap block cursor-pointer">
                                                            <input type="radio" name="payment_method" id="addon-pay-with-card"
                                                                class="sr-only peer checkset w-4 h-4 mr-2" value="Card" {{ $trans != NULL && $trans->via == "Card" ? "checked" : "" }}>
                                                            <div
                                                                class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
                                                                <svg class="w-5 h-5 mr-3" width="20" height="20"
                                                                    viewBox="0 0 20 20" fill="none"
                                                                    xmlns="http://www.w3.org/2000/svg">
                                                                    <path
                                                                        d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z"
                                                                        stroke="#60BEBD" stroke-width="1.5"
                                                                        stroke-linecap="round" stroke-linejoin="round" />
                                                                    <path d="M1.66992 8.33008H18.3366" stroke="#60BEBD"
                                                                        stroke-width="1.5" stroke-linecap="round"
                                                                        stroke-linejoin="round" />
                                                                </svg>
                                                                <span
                                                                    class="font-inter font-semibold text-black text-base flex-1">Pay
                                                                    with Card</span>
                                                                <span
                                                                    class="amount-text card-amount-add-on font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($bookingM->is_affiliate == 'Yes' ? $paymentTotalForCardAffiliate : $paymentTotalForCard) }}</span>
                                                            </div>
                                                        </label>
                                                    </div>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap -mx-3 addons-payment-form">
                                    <div class="w-full max-w-full px-3">
                                        <div class="payment-method-form-wrp">
                                            <div
                                                class="total-payment-wrp p-2 flex items-center justify-between bg-[#818181] text-white text-base font-medium rounded-md mt-2">
                                                <div class="total-payment">Total payment</div>
                                                <div class="total-price set-total">{{-- TOTAL AMOUNT --}}</div>
                                            </div>
                                            <div class="makeaayment-wrap">
                                                <div class="form-group mt-2">
                                                    <input type="submit" id="btn-make-payment-add-ons"
                                                        class="btn-make-payment btn-success-regular !w-full"
                                                        value="Make a Payment">
                                                    <div class="payment-btn-icon"></div>
                                                </div>
                                            </div>
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
    <!-- Addon payment modal close -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_addon_refund" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Refund Addons</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                            height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="addon-refund-section">
                        <div class="tourist-booking-sidebar">
                            <form id="modal-addons-refund-form" method="post" action="javascript:void(0);"
                                onsubmit="validateAndSubmitAddonsRefundForm(event)">
                                @csrf
                                <div class="form-check-wrap mb-2">
                                    <div class="form-check flex items-center">
                                        <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                            id="addons-ticket-refund" value="ticket_refund">
                                        <label class="form-check-label text-sm font-semibold text-[#111827]"
                                            for="addons-ticket-refund">Ticket Refund</label>
                                    </div>
                                </div>
                                <div class="form-check-wrap mb-2">
                                    <div class="form-check form-check-inline flex items-center">
                                        <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                            id="addons-full-refund" value="full_refund">
                                        <label class="form-check-label text-sm font-semibold text-[#111827]"
                                            for="addons-full-refund">Weather / 100% Refund</label>
                                    </div>
                                </div>
                                <div class="form-check-wrap mb-2">
                                    <div class="form-check form-check-inline flex items-center">
                                        <input class="w-4 h-4 mr-2 refund_type" type="radio" name="refund_type"
                                            id="addons-dont-refund" value="dont_refund">
                                        <label class="form-check-label text-sm font-semibold text-[#111827]"
                                            for="addons-dont-refund">Don't Refund</label>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="comment" class="form-label text-sm font-semibold text-[#111827]">Add a
                                        note</label><br>
                                    <textarea name="comment" id="addons-refund-notes" rows="3" cols="50"
                                        class="form-control clearable form-custom-input min-h-16"
                                        placeholder="Please specify the reason to refund the addons" required></textarea>
                                </div>
                                <div class="text-right">
                                    <button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small"
                                        id="addonsrefundsubmit">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Addon payment modal close -->

    <!-- ================================================================
         Addon Edit — Charge Modal
         Shown when editing add-ons results in a net additional charge.
         Content is populated by TourBookingAddons._showChargeModal()
    ================================================================ -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_addon_edit_charge" tabindex="-1" role="dialog" aria-hidden="true"
        data-pending-amount="{{ !empty($pendingPaymentLinkForTotal) ? $pendingPaymentLinkForTotal->amount : 0 }}"
        data-breakdown-url="{{ route(prefixedRouteName($routePrefix . 'booking.get-payment-link-items'), ['bookingId' => $bookingM->id]) }}">
        <div class="modal-dialog mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center"
            style="max-width:460px">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between border-b border-[#E5E7EB]">
                    <div>
                        <h4 class="text-lg font-semibold text-[#111827]">Payment Required</h4>
                        <p class="text-xs text-[#6B7280] mt-0.5">Select how the customer will pay for the updated add-ons</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body p-5" style="max-height:70vh;overflow-y:auto">
                    <!-- JS-populated charge breakdown (items + net total) -->
                    <div id="addon-edit-charge-breakdown"></div>
                    <!-- JS-populated payment method info (auto from booking) -->
                    <div class="mt-1">
                        <div class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide mb-2">Payment Method</div>
                        <div id="addon-edit-charge-methods"></div>
                    </div>
                </div>
                <div class="modal-footer px-5 py-4 bg-[#F9FAFB] border-t border-[#E5E7EB] flex items-center justify-end gap-3">
                    <button type="button" class="btn btn-secondary px-4 py-2 text-sm rounded-lg border border-[#D1D5DB] text-[#374151] bg-white hover:bg-[#F3F4F6]" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="addon-edit-charge-submit"
                        class="btn btn-main btn-primary-small themed-btn px-5 py-2 text-sm font-semibold rounded-lg">
                        Confirm Payment
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Addon Edit Charge Modal close -->

    <!-- ================================================================
         Addon Edit — Refund Modal
         Shown when editing add-ons results in a net refund to the customer.
         Content is populated by TourBookingAddons._showRefundModal()
    ================================================================ -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_addon_edit_refund" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center"
            style="max-width:460px">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#FFF7ED] flex items-center justify-between border-b border-[#FED7AA]">
                    <div>
                        <h4 id="addon-edit-refund-title" class="text-lg font-semibold text-[#111827]">Refund to Customer</h4>
                        <p id="addon-edit-refund-subtitle" class="text-xs text-[#6B7280] mt-0.5">The changes result in a credit due to the customer</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                <div class="modal-body p-5" style="max-height:70vh;overflow-y:auto">
                    <!-- JS-populated refund breakdown (items + net total) -->
                    <div id="addon-edit-refund-breakdown"></div>

                    <!-- Informational-only notice — shown instead of the Refund Type section
                         when nothing has actually been paid (see TourBookingAddons._showRefundModal()):
                         no real money moves either way, so there is nothing to select. -->
                    <div id="addon-edit-refund-info-only" class="mt-4 hidden">
                        <div class="rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 py-3 text-sm text-[#1E3A8A]">
                            Nothing has been paid toward this add-on yet, so no refund is due. Submitting will just
                            update the add-on and record the change in the booking history.
                        </div>
                    </div>

                    <!-- Refund type -->
                    <div id="addon-edit-refund-type-section" class="mt-4">
                        <div class="text-sm font-semibold text-[#111827] mb-2">Refund Type <span class="text-[#B91C1C]">*</span></div>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-3 px-4 py-3 border border-[#E5E7EB] rounded-lg cursor-pointer hover:bg-[#F9FAFB] transition-colors">
                                <input class="w-4 h-4" type="radio" name="addon_edit_refund_type"
                                    id="addon-edit-ticket-refund" value="ticket_refund">
                                <div>
                                    <div class="text-sm font-medium text-[#111827]">Ticket Refund</div>
                                    <div class="text-xs text-[#6B7280]">Standard refund to original payment method</div>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 px-4 py-3 border border-[#E5E7EB] rounded-lg cursor-pointer hover:bg-[#F9FAFB] transition-colors">
                                <input class="w-4 h-4" type="radio" name="addon_edit_refund_type"
                                    id="addon-edit-full-refund" value="full_refund">
                                <div>
                                    <div class="text-sm font-medium text-[#111827]">Weather / 100% Refund</div>
                                    <div class="text-xs text-[#6B7280]">Full refund due to weather or extenuating circumstances</div>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 px-4 py-3 border border-[#E5E7EB] rounded-lg cursor-pointer hover:bg-[#F9FAFB] transition-colors">
                                <input class="w-4 h-4" type="radio" name="addon_edit_refund_type"
                                    id="addon-edit-dont-refund" value="dont_refund">
                                <div>
                                    <div class="text-sm font-medium text-[#111827]">Don't Refund</div>
                                    <div class="text-xs text-[#6B7280]">Remove the add-on without issuing a refund</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Note -->
                    <div class="mt-4">
                        <label class="text-sm font-semibold text-[#111827] mb-1 block" for="addon-edit-refund-notes">Note</label>
                        <textarea id="addon-edit-refund-notes" rows="3"
                            class="form-control form-custom-input min-h-16 w-full"
                            placeholder="Optional: reason for the refund…"></textarea>
                    </div>
                </div>
                <div class="modal-footer px-5 py-4 bg-[#FFF7ED] border-t border-[#FED7AA] flex items-center justify-end gap-3">
                    <button type="button" class="btn btn-secondary px-4 py-2 text-sm rounded-lg border border-[#D1D5DB] text-[#374151] bg-white hover:bg-[#F3F4F6]" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="addon-edit-refund-submit"
                        class="btn btn-main btn-primary-small themed-btn px-5 py-2 text-sm font-semibold rounded-lg">
                        Process Refund
                    </button>
                </div>
            </div>
        </div>
    </div>
    <!-- Addon Edit Refund Modal close -->

    <!-- Pax Addon Delete Refund Modal Start -->
    <div class="modal fade fixed top-0 left-0 w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="modal_pax_addon_delete_refund" tabindex="-1" role="dialog" aria-hidden="true" style="z-index:2000">
        <div class="modal-dialog modal-fullscreen max-w-[500px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">

                <!-- Header -->
                <div class="modal-header px-5 py-4 border-b border-[#FECACA] flex items-start justify-between gap-3" style="background:linear-gradient(135deg,#FEF2F2 0%,#FFF7ED 100%)">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-[#FEE2E2] flex items-center justify-center flex-shrink-0 mt-0.5">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none"><path d="M8 5.33v2.67M8 10.67h.007M14 8A6 6 0 1 1 2 8a6 6 0 0 1 12 0z" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </div>
                        <div>
                            <h4 class="text-base font-semibold text-[#111827] mb-0">Remove Passenger Add-on(s)</h4>
                            <p class="text-xs text-[#6B7280] mt-0.5 mb-0">These paid add-ons will be permanently removed — choose a refund option</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close flex-shrink-0" data-bs-dismiss="modal" aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>

                <!-- Body -->
                <div class="modal-body p-5" style="max-height:65vh;overflow-y:auto">

                    <!-- Breakdown (JS-populated) -->
                    <div id="pax-delete-refund-breakdown" class="mb-4"></div>

                    <!-- Refund type heading -->
                    <div class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide mb-2">Select Refund Type <span class="text-[#DC2626]">*</span></div>

                    <!-- Refund options -->
                    <div class="flex flex-col gap-2">

                        <label class="pdr-option flex items-center gap-3 px-4 py-3 border-2 border-[#E5E7EB] rounded-xl cursor-pointer transition-all" for="pax-delete-ticket-refund">
                            <input class="w-4 h-4 flex-shrink-0" type="radio" name="pax_delete_refund_type" id="pax-delete-ticket-refund" value="ticket_refund">
                            <div class="flex-1 flex items-center justify-between gap-3 min-w-0">
                                <div>
                                    <div class="text-sm font-medium text-[#111827]">Ticket Refund</div>
                                    <div class="text-xs text-[#6B7280]">Addon price returned; service fees retained</div>
                                </div>
                                <div class="text-sm font-bold text-[#15803D] whitespace-nowrap" id="pax-refund-ticket-amount">—</div>
                            </div>
                        </label>

                        <label class="pdr-option flex items-center gap-3 px-4 py-3 border-2 border-[#E5E7EB] rounded-xl cursor-pointer transition-all" for="pax-delete-full-refund">
                            <input class="w-4 h-4 flex-shrink-0" type="radio" name="pax_delete_refund_type" id="pax-delete-full-refund" value="full_refund">
                            <div class="flex-1 flex items-center justify-between gap-3 min-w-0">
                                <div>
                                    <div class="text-sm font-medium text-[#111827]">Weather / 100% Refund</div>
                                    <div class="text-xs text-[#6B7280]">Full refund including all service fees</div>
                                </div>
                                <div class="text-sm font-bold text-[#15803D] whitespace-nowrap" id="pax-refund-full-amount">—</div>
                            </div>
                        </label>

                        <label class="pdr-option flex items-center gap-3 px-4 py-3 border-2 border-[#E5E7EB] rounded-xl cursor-pointer transition-all" for="pax-delete-dont-refund">
                            <input class="w-4 h-4 flex-shrink-0" type="radio" name="pax_delete_refund_type" id="pax-delete-dont-refund" value="dont_refund">
                            <div class="flex-1 flex items-center justify-between gap-3 min-w-0">
                                <div>
                                    <div class="text-sm font-medium text-[#111827]">Don't Refund</div>
                                    <div class="text-xs text-[#6B7280]">Remove the add-on without any refund</div>
                                </div>
                                <div class="text-sm font-semibold text-[#9CA3AF] whitespace-nowrap">{{ currencyFormation(false, true) }}0.00</div>
                            </div>
                        </label>

                    </div>

                    <!-- Dynamic refund summary (shown on radio select) -->
                    <div id="pax-refund-summary-bar" class="hidden mt-4 flex items-center justify-between rounded-xl px-4 py-3 border-2" style="background:#F0FDF4;border-color:#86EFAC">
                        <span class="text-sm font-medium text-[#166534]">Amount to refund customer</span>
                        <span class="text-base font-bold text-[#15803D]" id="pax-refund-summary-amount">{{ currencyFormation(false, true) }}0.00</span>
                    </div>

                    <!-- Note -->
                    <div class="mt-4">
                        <label class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide mb-1 block" for="pax-delete-refund-notes">Note (optional)</label>
                        <textarea id="pax-delete-refund-notes" rows="2"
                            class="form-control form-custom-input w-full text-sm"
                            placeholder="Reason for removing this add-on…"></textarea>
                    </div>
                </div>

                <!-- Footer -->
                <div class="modal-footer px-5 py-4 border-t border-[#E5E7EB] bg-[#F9FAFB] flex items-center justify-between">
                    <button type="button" class="text-sm font-medium text-[#6B7280] hover:text-[#374151] px-3 py-2 rounded-lg hover:bg-[#F3F4F6] transition-colors" data-bs-dismiss="modal">
                        ← Back
                    </button>
                    <button type="button" id="pax-addon-delete-refund-submit"
                        class="btn btn-danger btn-danger-small px-6 py-2 text-sm font-semibold rounded-lg">
                        Confirm &amp; Remove
                    </button>
                </div>

            </div>
        </div>
    </div>
    <!-- Pax Addon Delete Refund Modal End -->

    <!-- Bundle Package Booking Update Check-ins Modal Start -->
    <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="bundlePackageUpdateCheckIn" tabindex="-1" role="dialog" aria-hidden="true">
        <div
            class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="text-lg font-semibold text-[#111827]">Choose Check-In Status For All</h4>
                    <button type="button" id="refund-popup-close" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none"
                            xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="bundle-pkg-section"></div>
                </div>
            </div>
        </div>
    </div>
    <!-- Bundle Package Booking Update Check-ins Modal Close -->

    <!-- Edit Contact Info Modal Start -->
    @if($bookingM->has_contacts == 1)
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="booking_contacts" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Edit Contact Info</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4">
                        <form method="post" id="contact-form" class="edit-contact-form">
                            <input type="hidden" name="contact_id" id="contact_id" value="">
                            <input type="hidden" name="transact_id" id="transact_id" value="">
                            <input type="hidden" name="tourist_index" id="tourist_index" value="">
                            <input type="hidden" name="booking_id" id="booking_id" value="{{ $bookingM->id }}">
                            <div class="flex items-center mb-2">
                                <label for="c_name"
                                    class="form-label text-sm font-semibold text-[#111827] min-w-20 mr-2">Name</label>
                                <input type="text" name="c_name" id="c_name" maxlength="80"
                                    class="form-control form-custom-input" value="" placeholder="Contact Name"
                                    onblur="validateCharacters('#c_name')" required>
                            </div>

                            <div class="flex items-center mb-2">
                                <label for="c_age"
                                    class="form-label text-sm font-semibold text-[#111827] min-w-20 mr-2">Age</label>
                                <input type="text" name="c_age" id="c_age" class="form-control form-custom-input" value=""
                                    placeholder="Age" onblur="validateNumbers('#c_age')" data-allowed-numbers="0-9"
                                    data-user-display-label="Age" required>
                            </div>

                            <div class="flex items-center mb-2">
                                <label for="c_weight"
                                    class="form-label text-sm font-semibold text-[#111827] min-w-20 mr-2">Weight</label>
                                <input type="number" name="c_weight" id="c_weight" class="form-control form-custom-input"
                                    value="" placeholder="Weight (lbs)" min="0" step="0.01" required>
                            </div>

                            <div class="flex items-center mb-2">
                                <label for="c_note"
                                    class="form-label text-sm font-semibold text-[#111827] min-w-20 mr-2">Special
                                    Needs</label>
                                <textarea id="c_note" name="c_note" class="form-control form-custom-input min-h-16"
                                    placeholder="Special Needs" value=""></textarea>
                            </div>

                            <div class="text-end"><button type="submit" class="btn btn-main btn-main-sm btn-primary-small"
                                    id="edit-contacts">Save</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <!-- Edit Contact Info Modal End -->
    @if($bookingM->has_contacts == 1)
        <!-- Edit Booking Change People Edit Contacts(Remove Contacts) Modal Start -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="edit_contacts" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Edit Contacts</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4" id="edit-contacts-body">
                        <div class="contact-list"></div>
                        <form id="change_people_edit_contact_form">
                            <span class="delete-contact-text">Please select contact(s) you want to remove.</span>
                            @php $contact_index = 0; @endphp
                            @if(count($bookingContacts) > 0)
                                @foreach($bookingContacts as $key => $bookingContact)
                                    <div id="contact-section-{{ $bookingContact['rate_id'] }}"
                                        class="c-sec hidden {{ ($contact_index == 0) ? 'mt-2' : '' }}">
                                        <div class="rate-group-header {{ ($contact_index == 0) ? '' : 'mt-2' }}"
                                            id="rate_{{ $bookingContact['rate_id'] }}"><b>{{ $bookingContact['rate_for'] }}</b><span>
                                                [Pax: {{ count($bookingContact['contact_data']) }}]</span>
                                        </div>
                                        <div class="row change-people-remove-contact-section"
                                            id="rate-contact-section-{{ $bookingContact['rate_id'] }}">
                                            @foreach($bookingContact['contact_data'] as $keyc => $contact)
                                                <div class="col-sm-6 col-md-4 contact-section mt-4" id="contact_section_{{ $keyc }}">
                                                    <div class="card contact-card">
                                                        <i class="fa fa-check select-contact-inactive d-none"></i>
                                                        <div class="card-header contact-header text-base font-semibold text-[#111827] mb-2">
                                                            Contact #{{ $keyc + 1 }}
                                                        </div>
                                                        <div class="card-body">
                                                            <input type="hidden" name="contactId" id="contactId"
                                                                value="{{ $contact->contact_id }}">
                                                            <input type="hidden" class="contact-delete" name="deleted_contact_id[]"
                                                                id="deleted_contact_id" value="">
                                                            <div class="form-group flex items-center mb-2 contact-fields">
                                                                <label for="c_name_{{ $bookingContact['rate_id'] }}_{{ $keyc }}"
                                                                    class="col-form-label text-sm font-semibold text-[#111827]"><b>Name:
                                                                    </b></label>
                                                                <span class="contact-title">{{ $contact->name }}</span>
                                                            </div>
                                                            <div class="form-group flex items-center mb-2 contact-fields">
                                                                <label for="c_age_{{ $bookingContact['rate_id'] }}_{{ $keyc }}"
                                                                    class="col-form-label text-sm font-semibold text-[#111827]"><b>Age:
                                                                    </b></label>
                                                                <span class="contact-title">{{ $contact->age }}</span>
                                                            </div>
                                                            <div class="form-group flex items-center mb-2 contact-fields">
                                                                <label for="c_weight_{{ $bookingContact['rate_id'] }}_{{ $keyc }}"
                                                                    class="col-form-label text-sm font-semibold text-[#111827]"><b>Weight:
                                                                    </b></label>
                                                                <span class="contact-title">{{ $contact->weight }} lbs</span>
                                                            </div>
                                                            <div class="form-group flex items-center mb-2 contact-fields">
                                                                <label for="c_note_{{ $bookingContact['rate_id'] }}_{{ $keyc }}"
                                                                    class="col-form-label text-sm font-semibold text-[#111827]"><b>Note:
                                                                    </b></label>
                                                                <span class="contact-title">{{ $contact->note }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @php $contact_index++; @endphp
                                @endforeach
                            @endif

                            <button type="button" id="change_people_edit_contacts" class="themed-btn btn-primary-small">Update &
                                Confirm Booking</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Edit Booking Change People Edit Contacts(Remove Contacts) Modal End -->

        <!-- Edit Booking Change People Edit Contacts(Add Contacts) Modal Start -->
        <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
            id="edit_booking_add_contacts" tabindex="-1" role="dialog" aria-hidden="true">
            <div
                class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
                <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
                    <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                        <h4 class="text-lg font-semibold text-[#111827]">Add Contacts</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16"
                                height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                                <path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round"
                                    stroke-linejoin="round"></path>
                            </svg></button>
                    </div>

                    <div class="modal-body p-4">
                        <form class="contact-form" id="new-contact-form"
                            onsubmit="return validateAndSubmitEditBookingAddContactForm(this)">
                            <div id="new-contacts-section"></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- Edit Booking Change People Edit Contacts(Add Contacts) Modal End -->
        <!-- START: Stripe Elements -->

        <!-- END: Stripe Elements -->
    @endif
    <!-- </section> -->
    <!-- START: Stripe Elements -->
    <div class="modal fade custom-payment-modal fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="make-a-payment" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden shadow-lg">

                <!-- Header -->
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between border-b border-gray-200">
                    <h4 class="modal-title text-lg font-semibold text-[#111827]">Secure Payment</h4>
                    <button type="button" class="btn-close text-gray-600 hover:text-gray-900" data-bs-dismiss="modal"
                        aria-label="Close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 4L4 12" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                            <path d="M4 4L12 12" stroke="currentColor" stroke-width="1.33333" stroke-linecap="round"
                                stroke-linejoin="round"></path>
                        </svg>
                    </button>
                </div>

                <div class="modal-body py-7 px-5">

                    <!-- Tabs -->
                    <div class="flex border-b border-gray-200 mb-6">
                        <button type="button"
                            class="tab-btn flex-1 py-3 text-sm font-medium transition-colors border-b-2 border-blue-600 text-blue-600"
                            data-tab="online-payment">
                            Card Online
                        </button>
                        <button type="button"
                            class="tab-btn flex-1 py-3 text-sm font-medium transition-colors border-b-2 border-transparent text-gray-500 hover:text-gray-700"
                            data-tab="card-reader">
                            Card Reader
                        </button>
                    </div>

                    <!-- Online Card Tab -->
                    <div id="online-payment" class="tab-pane">
                        <div class="payment-box text-start">
                            <div class="card-detail-main">
                                <div class="card-detail-wrp card-form-field">
                                    <div id="saved-cards-container" style="display: none; margin-bottom: 20px;"></div>
                                    <div id="payment-element"></div>

                                    <div class="form-check my-3 save-card-checkmark flex items-center">
                                        <input class="form-check-input w-4 h-4 mr-2" type="checkbox"
                                            id="save-card-checkbox">
                                        <label class="form-check-label form-custom-label !mb-0" for="save-card-checkbox">
                                            Save this card for future payments
                                        </label>
                                    </div>

                                    <div class="form-group text-start mb-2 mt-3">
                                        <button class="btn btn-primary payment-btn btn-success-regular !w-full"
                                            id="makePaymentBtn">
                                            <i class="fas fa-lock mr-2"></i> Pay Securely
                                        </button>
                                    </div>

                                    <p class="secure-info mt-3 text-xs text-[#777777] text-center">
                                        Your payment is encrypted & secure.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Card Reader Tab -->
                    @include('partials.pos.cart._cardreader_tab')
                </div>
            </div>
        </div>
    </div>
    <!-- END: Stripe Elements -->
     @include('users.common.partials.booking._assign-guides-popup',['tourGuides' => $tourGuides])
    <!-- Edit Contact Info Modal End -->
    @if($isPaymentLink == 1)
        @include('users.common.partials.create-booking.payment-link', ['page' => 'booking-info'])
        @include('users.common.partials.create-booking.payment-link-details', ['page' => 'booking-info'])
    @endif
    @include('users.common.partials.addons.tour-booking-pax-addons')
    @if ($isBookingPaxAddonsExists)
        @include('users.common.partials.addons.addons-modal')
    @endif
    <!-- Saved Cards List Modal -->
    @include('users.common.partials.booking.saved-cards-list')

    <!-- Priority Note Modal -->
    @include('users.common.partials.booking.priority-note-modal')
    @include('users.common.partials.booking.priority-note-alert-modal')
    <!-- END: Priority Note Modal -->

    <!-- Receipt Print Modal -->
    <div class="modal fade fixed top-0 left-0 w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden"
        id="receiptPrintModal" tabindex="-1" aria-labelledby="receiptPrintModalLabel" aria-hidden="true" style="z-index: 10001;">
        <div
            class="modal-dialog modal-fullscreen max-w-[500px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
            <div class="modal-content rounded-xl bg-white w-full overflow-hidden" style="max-height: calc(100vh - 40px);">
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h4 class="modal-title text-lg font-semibold text-[#111827]" id="receiptPrintModalLabel">Receipt Print</h4>
                    <button type="button" class="text-[#6B7280] hover:text-[#111827] text-2xl leading-none" id="closeReceiptPrintModal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="modal-body p-0 relative" style="height: calc(100vh - 140px); overflow: hidden;">
                    <div id="receiptPrintLoading" class="absolute inset-0 flex items-center justify-center bg-white">
                        <div class="text-center">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-[#F97316] border-t-transparent mb-3"></div>
                            <p class="text-[#6B7280] text-sm">Loading receipt...</p>
                        </div>
                    </div>
                    <iframe id="receiptPrintIframe" src="" style="width: 100%; height: 100%; border: none;" onload="document.getElementById('receiptPrintLoading').style.display='none';"></iframe>
                </div>
            </div>
        </div>
    </div>
    <!-- END: Receipt Print Modal -->
@endsection

@section('css-libraries')
    @if (App::environment('local'))
        <link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
        <link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
    @else
        <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @endif
    <link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
@endsection

@section('js-libraries')
    @if (App::environment('local'))
        <script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
        <script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
        <script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
    @else
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
    @endif
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
@endsection

@section('js-custom')
    <script>
        // Check if Epson printer is configured in localStorage and show receipt print option
        (function() {
            var printerConfig = localStorage.getItem('epsonPrinterConfig');
            if (printerConfig) {
                try {
                    var config = JSON.parse(printerConfig);
                    if (config && config.printerIP) {
                        var receiptOption = document.getElementById('receipt-print-option');
                        var receiptDivider = document.getElementById('receipt-print-divider');
                        if (receiptOption) receiptOption.classList.remove('hidden');
                        if (receiptDivider) receiptDivider.classList.remove('hidden');
                    }
                } catch (e) {
                    console.error('Error parsing printer config:', e);
                }
            }
        })();

        // Print dropdown toggle (same pattern as profileDropdownBtn)
        $('#printDropdownBtn').on('click', function(e) {
            e.stopPropagation();
            var isVisible = !$('#printDropdownMenu').hasClass('hidden');
            $('#printDropdownMenu').addClass('hidden'); // Close first
            if (!isVisible) {
                $('#printDropdownMenu').removeClass('hidden');
            }
        });

        // Close print dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#printDropdownBtn, #printDropdownMenu').length) {
                $('#printDropdownMenu').addClass('hidden');
            }
        });

        // Receipt Print Modal handlers
        var receiptPrintUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.receipt-print'), ['bookingId' => $bookingM->id]) }}";

        $('#openReceiptPrintModal').on('click', function(e) {
            e.preventDefault();
            $('#printDropdownMenu').addClass('hidden');
            $('#receiptPrintLoading').show();
            $('#receiptPrintIframe').attr('src', receiptPrintUrl);
            $('#receiptPrintModal').removeClass('hidden');
            $('body').addClass('overflow-hidden');
        });

        $('#closeReceiptPrintModal').on('click', function() {
            $('#receiptPrintModal').addClass('hidden');
            $('#receiptPrintIframe').attr('src', '');
            $('body').removeClass('overflow-hidden');
        });

        // Close modal when clicking outside the content
        $('#receiptPrintModal').on('click', function(e) {
            if ($(e.target).is('#receiptPrintModal')) {
                $('#receiptPrintModal').addClass('hidden');
                $('#receiptPrintIframe').attr('src', '');
                $('body').removeClass('overflow-hidden');
            }
        });

        // Listen for close message from iframe
        window.addEventListener('message', function(e) {
            if (e.data && e.data.action === 'closeReceiptPrintModal') {
                $('#receiptPrintModal').addClass('hidden');
                $('#receiptPrintIframe').attr('src', '');
                $('body').removeClass('overflow-hidden');
            }
        });

        var formatedDate = '{{date("Y-m-d H:i:s")}}';
        var affiliateProcessingPercentage = 0;
        @if (isset($customerData->affiliate_processing_percentage) && floatval($customerData->affiliate_processing_percentage) > 0)
            affiliateProcessingPercentage = {{floatval($customerData->affiliate_processing_percentage)}};
        @endif
        var countryCode = "{{ countryCode() }}";
        var stripe = Stripe("{{ config('constants.stripe_keys.' . config('app.env') . '.0.public_key') }}");
        var serverDT = moment(formatedDate);
        var isAdminVal = "{{session('userType') == 'Admin' ? '/admin' : ''}}";
        var packageId = "{{ $bookingM->tour_package_id }}";
        var bookingId = "{{ $bookingM->id }}";
        var bookingIsCancelled = {{ $bookingM->statusm === 'Cancelled' ? 'true' : 'false' }};
        var orderId = "{{ $bookingM->order_id }}";
        var packageHasSlots = "{{ $bookingM->package_has_slots }}";
        var newBookingUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.create')) }}";
        var updateCheckInStatusUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.update-check-in-status')) }}";
        var noPhotoUrl = "{{ asset('images/no-photo.png') }}";
        var currentUserName = "{{ Auth::user()->name ?? '' }}";
        var currentUserRole = "{{ session('userType') ?? '' }}";
        var currentUserProfilePhoto = "{{ Auth::user()->profile_photo_path ?? '' }}";
        var getTourSlotTimesByDateUrl = "{{ route(prefixedRouteName($routePrefix . 'get-tour-slot-times-by-date')) }}";
        var paymentnewbookingUrl = "{{ route(($uTypeAdmin ? 'admin.' : '') . 'tour-operator.booking.payment-new-booking') }}";
        var tippaymentUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.tip-payment')) }}";
        var checkOverbooking = "{{ route(prefixedRouteName($routePrefix . 'booking.check-overbooking')) }}";
        var restoreBookingRoute = "{{ route(prefixedRouteName($routePrefix . 'booking.restore'), ':id')}}";
        var changePaymentTypeRoute = "{{ route(prefixedRouteName($routePrefix . 'booking.change-payment-type'), ':id')}}";
        var rateGroups = @json($rateGroups);
        // Maps a rate group's package-level id (tour_package_rate_id, what the Change People
        // table tracks per row) to this booking's own tour_bookings_transact.id — the id
        // tour_booking_addon_fields.pax_id actually references. Lets the "Update Booking" modal
        // work out which specific add-ons belong to a rate group the admin is removing people from.
        var rateGroupIdToPaxId = @json($bookingT->pluck('id', 'tour_package_rate_id'));
        var pendingPaxInfoByRateGroupId = @json($pendingPaxInfoByRateGroupId ?? []);
        var taxes = [];
        var touristsGroups = [];
        var isTourBookingInfo = true;
        var isAffiliate = "{{$bookingM->is_affiliate}}";
        {{-- A locked OTA booking (an active custom charge already exists) behaves exactly like a
             genuine Cash/Card booking from here on — paymentMethod is seeded with the locked
             method instead of the OTA title, so none of the OTA-specific JS below ever engages
             for it; only an OTA booking that's still unlocked (first custom charge, or every
             prior one fully refunded) needs the Cash-or-Card choice UI. --}}
        var paymentMethod = "{{ ($trans == null) ? 'Pay Later' : ($otaCustomChargeLockedMethod ?: $trans->via) }}";
        // { addonFieldDbId: { net, fee } } — what each add-on still pending on the link is worth
        // on it. An upgrade of one of these replaces its pending line, so the add-ons "Payment
        // Required" preview uses this to show the real difference owed and the real total.
        var pendingAddonLinkLineShares = @json((object) \App\Services\Booking\PaymentLinkService::pendingAddonLinkLineShares((int) $bookingM->id));
        var isOtaBookingCustomCharge = {{ (!empty($bookingM->ota_booking_id) && empty($otaCustomChargeLockedMethod)) ? 'true' : 'false' }};
        var splitPayment = "{{ (!empty($trans)) ? $trans->payment_type : ''}}";
        var lowerAntelopeCanyonClosureUrl = "{{ route(prefixedRouteName($routePrefix . 'lower-antelope-canyon-closure-email'), ':id')}}";
        var unblockAffiliateSoftBookingUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.unblock-affiliate-soft-booking'), ':id') }}";
        var splitBookingUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.split-people-booking')) }}";
        var NewExtendedbookingUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.show'), ':id') }}";
        var totalForCash = "{{ ($trans == null) ? sprintf('%.2f', $cashPrices['cashPriceSubtotal']) : '' }}";
        var bookingContactsUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.rate-group-contacts'), ':bookingId') }}";
        var assignTourGuideUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.save-tour-guide'), ['bookingId' => $bookingM->id]) }}";
        var createPaymentIntentUrl = "{{ route(prefixedRouteName($routePrefix . 'stripe.create-payment-intent-make-payment')) }}";
        var deletePaymentMethodUrl = "{{ route(prefixedRouteName($routePrefix . 'stripe.delete-payment-method')) }}";
        var createPaymentLinkUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.create-payment-link')) }}";
        var getAddonFormUrl = "{{ route(prefixedRouteName($routePrefix . 'package.get-addon-form'), ['tourPackageId' => 'PACKAGE_ID']) }}";
        var getBookingPaxAddonsUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.get-booking-pax-addons')) }}";

        setInterval(function () {
            serverDT.add(15, 'seconds');
        }, 15000);

        var totalForCashSplit = '';
        if (splitPayment == 1) {
            totalForCashSplit = "{{sprintf('%.2f', $subtotal)}}";
        }

        var totalForCard = "{{ ($trans == null) ? sprintf('%.2f', $totalV) : '' }}";
        var cashPriceEnabled = "{{ $cashPriceEnabled }}";
        var cashPriceTotalWithFees = "{{ sprintf('%.2f', $cashPrices['cashPriceTotal']) }}";
        var isBookedFromFrontend = "{{ $bookedFromFrontend }}";
        var natServiceCommission = "{{ $bookingT[0]->nat_fees_percentage }}";
        var stripeServiceCommission = "{{ $bookingT[0]->stripe_fees_percentage }}";
        if (isAffiliate == 'Yes') {
            var totalForCashAffiliate = "{{ ($trans == null) ? sprintf('%.2f', $subtotalaffiliatecash) : '' }}";
            var totalForCardAffiliate = "{{ ($trans == null) ? sprintf('%.2f', $subtotalaffiliatecard) : '' }}";
            if (isBookedFromFrontend == 0) {
                natServiceCommission = affiliateProcessingPercentage;
            }
        }
        var customChargeServiceCommission = natServiceCommission;
        @if(!empty($bookingM->ota_booking_id))
            {{-- $bookingT[0]->nat_fees_percentage is 0 for OTA bookings created before it started
                 being backfilled onto tour_bookings_transact; fall back to the package's own
                 commission percentage (resolved server-side, not a $bookingT column) instead of
                 silently charging a $0 fee. --}}
            customChargeServiceCommission = "{{ !empty($bookingT[0]->nat_fees_percentage) ? $bookingT[0]->nat_fees_percentage : $otaCustomChargeServiceCommission }}";
        @endif
        var timezone = "{{ $timezone }}"
        var paymentTransCount = "{{$paymentTransCount}}";
        var oldBookingId = "{{ $oldBookingId }}";
        var maxTourists = "{{ $max_tourits }}";
        var overBookSeats = Number("{{ $bookingM->overbook_seats }}");
        var cashAmountWithDiscount = 0;
        var paymentTotalForCard = "{{ sprintf('%.2f', $paymentTotalForCard) }}";
        var totalForCashChangePaymentType = "{{ ($cashPriceEnabled == 1) ? sprintf('%.2f', $cashPrices['cashPriceSubtotal']) : sprintf('%.2f', $subtotal) }}";
        var isMultipleCardPayments = {{ (!empty($transP) && $paymentTransCount > 1 && $transP->via == "Card") ? 1 : 0 }};
        var IscardPayment = {{ (!empty($transP) && $transP->via == "Card") ? 1 : 0 }};
        var parentUrl = (window.location != window.parent.location)
            ? document.referrer
            : document.location.href;
        var custEmail = "{{ $bookingM->cust_email }}";
        var custPhoneNo = "{{ $bookingM->cust_phone }}";
        var userType = '{{ session("userType") }}';
        var tourSlotId = '{{ $bookingM->tour_slot_time_id }}';
        var packageName = '{{ $bookingM->tour_package }}';
        var bookingT = @json($bookingT);;
        var splitPendingAmount = '{{ sprintf('%.2f', $splitPendingAmount) }}';
        var isImportedBooking = '{{ $isImportedBooking }}';
        var isCashFees = '{{ $isCashFees }}';
        if (isCashFees == '1') {
            var totalForCashChangePaymentType = "{{ ($cashPriceEnabled == 1) ? sprintf('%.2f', $cashPrices['cashPriceTotal'])  : sprintf('%.2f', $totalV) }}";
        }
        var cartPaymentTotalForCash = "{{ $cartPaymentTotalForCash }}";
        var cartPaymentTotalForCard = "{{ $cartPaymentTotalForCard }}";
        var cartServiceCommissionTotal = "{{ $cartServiceCommissionTotal }}";
        var isGroupBooking = "{{ $isGroupBooking }}";
        var isPartnerBooking = "{{ $isPartnerBooking }}";
        var bookingTourists = "{{ $bookingM->tourists }}";
        var paxWiseGroupRates = @json($paxWiseGroupRates);
        var paxWiseGroupRateIds = @json($paxWiseGroupRateIds);
        var paxWiseGroupAdditionalCharges = @json($paxWiseGroupAdditionalCharges);
        var customChargeTotal = "{{ $customChargePrice['custom_charge_total'] }}";
        var packageHasContacts = "{{ $bookingM->has_contacts }}";
        var requestToManager = "{{route(routePrefix() . '.booking.request-to-manager')}}";
        var manualIdCheck = "{{route(routePrefix() . '.booking.manual-id-check')}}";
        var manualPinCheck = "{{route(routePrefix() . '.booking.manual-pin-check')}}";
        var expiryDate = "{{ $bookingM->expire_at }}";
        var redeemCount = "{{ $redeemedTickets }}";
        var discountPermission = "{{ $requestPermissions['Discount'] }}";
        var actionStore = "{{route(routePrefix() . '.booking.action-store')}}";
        var refundCustomChargePermission = "{{ $requestPermissions['Refund Custom Charge'] }}";
        var rebookPermission = "{{ $requestPermissions['Rebook'] }}";
        var closureEmailPermission = "{{ $requestPermissions['Closure Email'] }}";
        var refundAddonsPermission = "{{ $requestPermissions['Refund Addons'] }}";
        var taxOnCustomChargePermission = "{{ $requestPermissions['Tax On Custom Charge'] }}";
        var changePeoplePermission = "{{ $requestPermissions['Change'] }}";
        var switchPermission = "{{ $requestPermissions['Switch'] }}";
        var fieldRequestPermissions = @json($fieldRequestPermissions);
        var cancellPermission = "{{ $requestPermissions['Cancel'] }}";
        var deletePermission = "{{ $requestPermissions['Delete'] }}";
        var permitPermission = "{{ $requestPermissions['Permit'] }}";
        var refundPermission = "{{ $requestPermissions['Refund'] }}";
        var reschedulePermission = "{{ $requestPermissions['Reschedule'] }}";
        var managerRequestEnableRefundAddons = "{{ $managerRequestEnable['Refund Addons'] }}";;
        var hasOldAddons = "{{ isset($hasOldAddons) && $hasOldAddons ? 'true' : 'false' }}";
        var isPaymentLink = "{{ $isPaymentLink }}";
        var paymentLinkActive = "{{ $paymentLinkActive }}";
        var isPaymentLinkButtonVisible = "{{ $bookingM->paymentLinkEnabled }}";
        var getPaymentLinkReasonUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.payment-link-reason')) }}";
        var getActivePaymentLinkUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.active-payment-link')) }}";
        var getInActivePaymentLinkUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.inactive-payment-link')) }}";
        var paymentLinkStatusUpdateUrl = "{{ route(prefixedRouteName($routePrefix . 'booking.payment-link-status-update')) }}";
        var expiryDateOverride = "{{ route(prefixedRouteName($routePrefix . 'booking.expiry-date-override')) }}";
        var checkStatus = "{{ route(prefixedRouteName($routePrefix . 'booking.payment-link-status-check')) }}";
        var getAssignedGuidesURL = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.get-assigned-guides'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	    var assignTourGuideUrl = "<?php echo e(route(prefixedRouteName($routePrefix . 'booking.save-tour-guide'), ['bookingId' => '_BOOKING_ID_'])); ?>";
	    var tourGuides = @json($tourGuides);
	    var gratuityType = "{{ $gratuitySetting->gratuity_type ?? '' }}";
        var isBookingPaxAddonsExists = "{{ $isBookingPaxAddonsExists }}";
        var isUnpaidBooking = "{{ $isUnpaidBooking }}";
        // Payment Link doesn't account for a split-payment leg's amount (it always bills the
        // full remaining booking total) — true whenever the booking already shows the "Split"
        // payment-status badge, not just when the Make Payment modal was opened via a
        // booking/{id}?split=N link. See the show.bs.modal handlers below.
        var isSplitPaymentBooking = {{ $isSplitPaymentBooking ? 'true' : 'false' }};
        var disableAffiliateCardSave = "{{ disableAffiliateCardSave() }}";
        var disableCardSave = "{{ hasSetting('DISABLE_CARD_SAVE') }}";
        var csrfToken = $('meta[name="csrf-token"]').attr('content');
        var saveCardListUrl = "{{ route(prefixedRouteName($routePrefix . 'stripe.save-card-list')) }}";

        window.priorityNoteData = @json($priorityNote ?? null);
        window.priorityNoteSaveUrl = '{{ route(prefixedRouteName($routePrefix . "booking.save-priority-note"), ["bookingId" => $bookingM->id]) }}';
        window.priorityNoteAutoPopup = {{ (!empty($priorityNote) && $priorityNote->charge_type == '1') ? 'true' : 'false' }};
        window.priorityNoteBookingRef = '{{ $bookingM->customer }} — Booking #{{ bookingPrefix($bookingM->id) }}';
    </script>
    <script type="text/javascript"
        src="https://cdnjs.cloudflare.com/ajax/libs/jquery-cookie/1.4.1/jquery.cookie.min.js"></script>
    <script src="{{ asset('js/user-common/new-tip.js') }}"></script>
    <script src="{{ asset('js/user-common/booking-request.js') }}"></script>
    <script src="{{ asset('js/user-common/new-payment.js') }}"></script>
    <script src="{{ asset('js/user-common/affiliated-payment.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-change-people.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-priority-note.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-change-group-rate.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-restore.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-info.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-info-custom-charge.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-bundle-booking-info.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-info-contacts.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-info-payment-link.js') }}"></script>
    <script src="{{ asset('js/user-common/tour-booking-assign-guides.js') }}"></script>
    <script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
    <script src="https://momentjs.com/downloads/moment-timezone.min.js"></script>
    <script src="https://js.stripe.com/terminal/v1/"></script>
    <script src="{{ asset('js/utils/stripe-reader-identity.js')}}"></script>
    <script src="{{ asset('js/pos/stripe-card-reader.js')}}"></script>
    <script src="{{ asset('js/user-common/backend-card-reader-info.js')}}"></script>
    <script src="{{ asset('js/user-common/tour-booking-stripe-payment.js') }}"></script>
    <!-- Datatables -->
    <script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>

    <!-- custom form option addons -->
    <script type="text/javascript" src="{{ asset('js/user-common/tour-package-custom-form-display.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/user-common/tour-package-custom-form-reader.js') }}"></script>
    <!-- Add-ons -->
    <script type="text/javascript" src="{{ asset('js/user-common/addons/components.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/user-common/addons/tour-booking-addons.js') }}?v={{ filemtime(public_path('js/user-common/addons/tour-booking-addons.js')) }}"></script>
    <script type="text/javascript" src="{{ asset('js/user-common/addons/tour-booking-pax-addons-operations.js') }}"></script>
    @if ($isBookingPaxAddonsExists)
        <script type="text/javascript" src="{{ asset('js/user-common/addons/form-reader.js') }}"></script>
        <script type="text/javascript" src="{{ asset('js/user-common/tour-booking-change-people-v2.js') }}"></script>
    @endif

    <script type="text/javascript">
        var routePrefixUrl = "{{ routePrefix() }}";
        var discountHistoryArray = null;

        /////////
        // Card Reader
        //////////
        const backendCardReaderInfo = new BackendCardReaderInfo();

        $("#delete").click(function (e) {
            if ($('.ads_Checkbox:checked').length == 0) {
                swal('Sorry!', 'Please select people to delete.', 'warning');
                return false;
            }
            // The "Update Booking" modal's own add-ons block already knows exactly which add-on
            // fields belong to the checked person(s) (matched via each checkbox's own
            // data-pax-id/data-pax-index), so the direct delete flow no longer needs the separate
            // "Passenger Add-ons" modal to resolve that — it goes straight to "Update Booking".
            editBookingDeletePeople();
        });

        function editBookingDeletePeople() {
            var checkboxesList = $('.ads_Checkbox');
            var checkArray = [];
            var contactIdsArray = [];
            var pendingHistoryIdSet = {};
            // {itemId: {transactId: count}} — tracks exactly which transact rows lose how many pax
            var pendingItemTransactMap = {};
            var anyUnpaid = false;
            var allUnpaid = true;

            checkboxesList.each(function (index) {
                if (this.checked) {
                    checkArray.push(index);
                    var isPending = $(this).data('pending') == 1;
                    var phId = parseInt($(this).data('pending-history-id') || 0);
                    var piId = parseInt($(this).data('pending-item-id') || 0);
                    var btId = parseInt($(this).data('pending-transact-id') || 0);
                    if (isPending && (phId > 0 || piId > 0)) {
                        anyUnpaid = true;
                        if (phId > 0) pendingHistoryIdSet[phId] = true;
                        if (piId > 0 && btId > 0) {
                            if (!pendingItemTransactMap[piId]) pendingItemTransactMap[piId] = {};
                            pendingItemTransactMap[piId][btId] = (pendingItemTransactMap[piId][btId] || 0) + 1;
                        }
                    } else {
                        allUnpaid = false;
                    }
                    if (packageHasContacts == 1) {
                        var contactIds = $(this).data('contact-id');
                        if (contactIds != '') contactIdsArray.push(contactIds);
                    }
                }
            });

            // Serialize as "itemId:transactId:count,..." so the backend removes the exact pax per rate group
            var pendingItemEntries = [];
            Object.keys(pendingItemTransactMap).forEach(function(itemId) {
                Object.keys(pendingItemTransactMap[itemId]).forEach(function(transactId) {
                    pendingItemEntries.push(itemId + ':' + transactId + ':' + pendingItemTransactMap[itemId][transactId]);
                });
            });

            $('#action_type').val('delete');
            $('#tourits_numbers').val(checkArray.join());
            $('#pending_history_ids').val(Object.keys(pendingHistoryIdSet).join(','));
            $('#pending_item_ids').val(pendingItemEntries.join(','));

            // Reset refund option state before applying new state
            $('.refund-type-option').removeClass('opacity-50 pointer-events-none');
            $('#Partial-delete').html('');

            if (anyUnpaid) {
                if (allUnpaid) {
                    // All selected pax are unpaid — force "Don't Refund"
                    $('#Radio1').prop('checked', true).trigger('change');
                    $('#Partial-delete').html('These pax have a pending payment link and have not paid yet. They will be removed with no refund and the payment link will be updated or cancelled.');
                    $('.refund-type-option:not(:has(#Radio1))').addClass('opacity-50 pointer-events-none');
                } else {
                    // Mixed: some paid, some unpaid — show all refund options
                    // Selected refund type applies to paid pax; unpaid pax are always removed without refund
                    $('#Partial-delete').html('Some selected pax have a pending payment link. The selected refund type will apply to paid pax only — unpaid pax will be removed without refund and the payment link will be updated or cancelled.');
                }
            }

            if (packageHasContacts == 1 && contactIdsArray.length > 0) {
                $('#contact_ids').val(JSON.stringify(contactIdsArray));
            }

            $('#deleteBooking').modal('toggle');
            $('#edit_booking').modal('hide');
        }

        $("#update").click(function () {
            if ($('.ads_Checkbox:checked').length == 0) {
                swal('Sorry!', 'Please select people to update permit fees.', 'warning');
                return false;
            }
            if (isImportedBooking == '1') {
                $('#permitFeesRefund').modal('toggle');
                $('#edit_booking').modal('hide');
                $('#action_type').val('update');
            } else {
                $('#action_type').val('update');
                var permitFeeBookings = $('#form_id').serializeArray();
                var additional = "";
                if (permitPermission == "No") {
                    validationMangerRequest(permitFeeBookings, 'Permit', permitPermission, '#edit_booking', '#form_id', additional);
                    return false;
                } else {
                    showLoading();
                    $(this).prop('disabled', true);
                    $('#form_id').submit();
                }
            }
        });

        $("#updatePermitFees").click(function () { //data-bs-toggle="modal" data-bs-target="#updateBooking"
            if ($('.ads_Checkbox:checked').length == 0) {
                swal('Sorry!', 'Please select people to update permit fees.', 'warning');
                return false;
            }
            var permitFeeBookings = $('#form_id').serializeArray();
            var additional = "";
            if (permitPermission == "No") {
                validationMangerRequest(permitFeeBookings, 'Permit', permitPermission, '#edit_booking', '#updatePermitFees', additional);
                return false;
            }
            $('#updateBooking').modal('toggle');
            $('#edit_booking').modal('hide');
        });

        $(document).on('click', '#cancelsubmit', function () {
            var cancelNote = $('#cancelBooking #comment').val();
            if (cancelNote == '' || !$.trim(cancelNote)) {
                swal('Sorry!', 'Please add a note.', 'warning');
                return false;
            } else {
                var cancelbookingform = $('#cancelbookingform').serializeArray();
                var additional = "";
                if (cancellPermission == 'No') {
                    validationMangerRequest(cancelbookingform, 'Cancel', cancellPermission, '#cancelBooking', '#cancelbookingform', additional);
                    return false;
                }
            }
            showLoading();
            $(this).prop('disabled', true);
            $('#cancelbookingform').submit();
        });

        var total_amount = "{{ $bookingM->total }}";
        var total_amount_paid = "{{ $remainingAmount }}";

        var customChargeAmount = "";
        var userType = "{{ session('userType') }}";
        $(document).ready(function () {
            /**
             * display a sms message window
            */
            $('#modal-send-sms-message').on('show.bs.modal', function (e) {
                var sms_page = '';
                if (userType == 'Affiliate') {
                    sms_page = `${isAdminVal}/affiliate/checkin/sms-index?modal=1&id=${bookingId}`;
                } else {
                    sms_page = `${isAdminVal}/tour-operator/checkin/sms-index?modal=1&id=${bookingId}`;
                }
                const sms_html = `<iframe border="0" height="450px" width="100%" src="${sms_page}"></iframe>`;
                $("#sms-form-modal").html(sms_html);
            });

            var is_affiliate = "{{ $bookingM->is_affiliate }}";
            var do_not_check = "{{$do_not_check}}";

            if (do_not_check == 1) {
                $("#Radio3").prop('checked', false);
                $("#Radio1").prop('checked', true).change();
            }

            $('#parent_url').val(parentUrl);
            var pathname = window.location.href;
            var spliturlCheck = pathname.slice(-7);
            var splitslice = spliturlCheck.slice(0, 5);
            if (splitslice == "split") {
                var splitcount = spliturlCheck.slice(-1);
                if (splitcount != 2) {
                    var sv = splitcount - 1;
                    $("#dividedBy_2").val(sv).change();
                }

                if (is_affiliate == "No") {
                    $('#modal_admin').modal('show');
                } else {
                    $('#modal_affilate').modal('show');
                }

                if (splitcount != 2) {
                    var sv = splitcount - 1;
                    $("#dividedBy").val(sv).change();
                }

                // Payment Link doesn't account for the split/divided-by amount (it always bills
                // the full remaining booking total), so hide it whenever this modal is opened for
                // a split-payment leg to avoid sending the wrong amount.
                $('#payment-link-cp-option, #payment-link-cp-affiliate-option').hide();
                if ($('#pay-with-link-cp').is(':checked')) {
                    $('#pay-with-card1').prop('checked', true).trigger('click');
                }
                if ($('#pay-with-link-cp-affiliate').is(':checked')) {
                    $('#pay-with-card').prop('checked', true).trigger('click');
                }
            }

            $('#modal_admin').on('show.bs.modal', function (e) {
                if ($('#modal_admin').data('call-by-edit-booking-change-people')) {
                    $('#split-payment1').prop('checked', false);
                    $('#split_value_field1').val('');
                    $('#dividedBy_2').val("Please select option");
                    $(".split-check").text(currency(0).format());
                    $("#split_value_field1").attr("required", false);
                    $("#split_value1").attr("hidden", true);
                    $('#modal_admin .split-payment-div').hide();
                    // Pay with Link is relevant for Change People on Card bookings, or on a
                    // booking that already has an active pending remaining-payment link (e.g. a
                    // Split Booking-created booking — 'Pay Later' by default since it has never
                    // had its own transaction, but genuinely needs to keep collecting via that
                    // same link rather than only ever offering Card).
                    if (paymentMethod === 'Card') {
                        $('#payment-link-cp-option').show();
                    } else {
                        $('#payment-link-cp-option').hide();
                    }
                } else {
                    // Hide split/divide option when a pending payment link exists
                    if ($('#modal_admin').data('pendingAmount') > 0) {
                        $('#modal_admin .split-payment-div').hide();
                    } else {
                        $('#modal_admin .split-payment-div').show();
                    }
                    // Payment Link doesn't account for a split-payment leg's amount (it always
                    // bills the full remaining booking total), so keep it hidden whenever this
                    // modal is (re)opened for a split-payment booking — either opened directly via
                    // a booking/{id}?split=N link, or (isSplitPaymentBooking) any time the booking
                    // already shows the "Split" payment-status badge, e.g. clicking "Make Payment"
                    // directly with no ?split= in the URL at all. This handler re-runs on every
                    // open and would otherwise re-show it even after the initial page-load hide.
                    if (splitslice == "split" || isSplitPaymentBooking) {
                        $('#payment-link-cp-option').hide();
                        if ($('#pay-with-link-cp').is(':checked')) {
                            $('#pay-with-card1').prop('checked', true).trigger('change');
                        }
                    } else {
                        $('#payment-link-cp-option').show();
                    }
                }
                // Default to Pay with Card if no option is pre-selected
                if (!$('#modal_admin input[name="payment_method"]:checked').length) {
                    $('#pay-with-card1').prop('checked', true).trigger('change');
                }
            });

            // Same Payment Link visibility rule as #modal_admin above, scoped to the plain Make
            // Payment case only — Change People / Switch Package go through their own separate
            // flows (upsertRemainingPaymentLink-based item links) that already account for the
            // split/remaining balance correctly, so they're left untouched here.
            $('#modal_affilate').on('show.bs.modal', function (e) {
                if ($('#modal_affilate').data('call-by-edit-booking-change-people') || $('#modal_affilate').data('call-by-switch-package')) {
                    return;
                }
                if (splitslice == "split" || isSplitPaymentBooking) {
                    $('#payment-link-cp-affiliate-option').hide();
                    if ($('#pay-with-link-cp-affiliate').is(':checked')) {
                        $('#pay-with-card').prop('checked', true).trigger('change');
                    }
                } else {
                    $('#payment-link-cp-affiliate-option').show();
                }
            });

            $('#model_affiliate').on('show.bs.modal', function (e) {
                if ($('#model_affiliate').data('call-by-edit-booking-change-people')) {
                    $('#model_affiliate .split-payment-div').hide();
                } else {
                    $('#model_affiliate .split-payment-div').show();
                }
            });

            $('#modal-resend-booking').on('show.bs.modal', function (e) {
                var custEmail = $('#customerEmail').text();
                $('#modal-resend-booking #email').val(custEmail);
            });

            $('#edit_booking_change_people').on('hide.bs.modal', function (e) {
                if (!$('#edit_booking_change_people').data('prevent-hide-callback')) {
                    if (paymentMethod == 'Pay Later') {
                        if (isAffiliate == 'Yes') {
                            $('#span_card').text(currency(totalForCardAffiliate).format());
                            $('#span_cash').text(currency(totalForCashAffiliate).format());
                            $('#span_invoiced').text(currency(totalForCashAffiliate).format());
                        } else {
                            $('.card-amount').text(currency(totalForCard).format());
                            $('.cash-amount').text(currency(totalForCash).format());
                            $('.invoiced-amount').text(currency(totalForCash).format());
                        }
                    }

                    if (splitPendingAmount > 0 && paymentMethod == 'Card') {
                        if (isAffiliate == 'Yes') {
                            $('#span_card').text(currency(splitPendingAmount).format());
                        } else {
                            $('.card-amount').text(currency(splitPendingAmount).format());
                        }
                    }

                    if (changePeoplePermission == "Yes") {
                        $('#edit_booking').modal('show');
                    }
                } else {
                    $('#edit_booking_change_people').removeData('prevent-hide-callback');
                }
            }).on('show.bs.modal', function (e) {
                if (!$('#edit_booking_change_people').data('prevent-init-calls')) {
                    if (isGroupBooking == '1') {
                        setGroupRateChangePeople();
                        showSlotInfoGroupRateChangePeople($('#slot_time')[0]);
                    } else {
                        setRateGroupsChangePeople();
                        showSlotInfoChangePeople($('#slot_time')[0]);
                    }

                    if (typeof changePeopleV2 !== 'undefined' && typeof getAddonFormUrl !== 'undefined') {
                        var bookingId = {{ $bookingM->id }};
                        var packageId = {{ $bookingM->tour_package_id }};

                        var $slotOption = $('#slot_time option:selected');
                        var slotSeats = parseInt($slotOption.data('seats')) || 0;
                        var slotTourists = parseInt($slotOption.data('tourists')) || 0;
                        var currentBookingTourists = {{ $bookingM->tourists ?? 0 }};
                        var totalSeats = slotSeats - slotTourists + currentBookingTourists;

                        var rateGroupsWithTourists = rateGroups.map(function (g) {
                            var $row = $('#table-rate-groups-change-people tr[data-rate-group-id="' + g.id + '"]');
                            var tourists = parseInt($row.find('.tourists').val()) || parseInt(g.tourists) || 0;
                            return Object.assign({}, g, { tourists: tourists });
                        });

                        changePeopleV2.open(bookingId, packageId, rateGroupsWithTourists, totalSeats, getAddonFormUrl);
                    }
                } else {
                    $('#edit_booking_change_people').removeData('prevent-init-calls');
                }
            });

            $('#edit_contacts').on('hide.bs.modal', function (e) {
                if (!$('#edit_contacts').data('prevent-hide-callback')) {
                    $('#edit_booking_change_people').modal('show');
                } else {
                    $('#edit_contacts').removeData('prevent-hide-callback');
                }
            }).on('show.bs.modal', function (e) {
                if (!$('#edit_contacts').data('prevent-init-calls')) {

                } else {
                    $('#edit_contacts').removeData('prevent-init-calls');
                }
            });

            var span_invoiced = $('#span_invoiced').text();
            var span_cash = $('#span_cash').text();
            var span_card = $('#span_card').text();

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
                $(".payment-details").hide();
                $('#totalcost').text(span_invoiced);
                $('#htt').val(span_invoiced);
                $('#cash-paid-amount-div1').hide();
            };

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                $(".payment-details").hide();
                $('#totalcost').text(span_cash);
                $('#htt').val(span_cash);
                $('#cash-paid-amount-div1').show();
            }

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                $(".payment-details").show();
                $('#totalcost').text(span_card);
                $('#htt').val(span_card);
                $('#cash-paid-amount-div1').hide();
            }

            $('input[type=radio][name=payment_method]').change(function () {
                // Same reasoning as the .checkset handler below: don't re-show Split Payment for
                // an incremental top-up payment (Change People / Switch Package), regardless of
                // which payment method gets clicked.
                var isChangePeopleOrSwitchPackageTopUpRadio = !!($(this).closest('.modal').data('call-by-edit-booking-change-people') || $(this).closest('.modal').data('call-by-switch-package'));
                if (this.value == 'Pay Later') {
                    $(".payment-details").hide();
                    $('#cash-paid-amount-div1').hide();
                    $('#totalcost').text(span_invoiced);
                    $('#htt').val(span_invoiced);
                    $(".split-check").text(currency(0).format());
                    $("#split_value_field").val('')
                    $("#split_value").attr("hidden", true);
                    $("#split_value_field").attr("required", false);
                    $("#split_value_field").attr("min", false);
                    $("#split-payment").prop('checked', false);
                    $(".split-set").text(currency(0).format());
                };

                if (this.value == 'Cash') {
                    $(".payment-details").hide();
                    $('#cash-paid-amount-div1').show();
                    $('#totalcost').text(span_cash);
                    $('#htt').val(span_cash);
                    if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                        var M = $(".cash-amount").text();
                        M = parseFloat(M.replace(currencyFormation.symbol, ""));
                        var vl = $('#dividedBy').find('option:selected').val();
                        var setValue = M / vl;
                        setValue = parseFloat(setValue).toFixed(2);
                        $(".split-check").text(currency(setValue).format());
                        $("#split_value_field").val(setValue)
                        $("#split_value").attr("hidden", false);
                        $("#split_value_field").attr("required", true);
                        $("#split_value_field").attr("min", true);
                        $('#totalcost').text(setValue);
                        $("#split-payment").prop('checked', true);
                    }
                }

                if (this.value == 'Card') {
                    $(".payment-details").show();
                    $('#cash-paid-amount-div1').hide();
                    $('#totalcost').text(span_card);
                    $('#htt').val(span_card);
                    if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                        var K = $("#span_card").text();
                        K = parseFloat(K.replace(currencyFormation.symbol, ""));
                        var vl = $('#dividedBy').find('option:selected').val();
                        var setValue = K / vl;
                        setValue = parseFloat(setValue).toFixed(2);
                        $(".split-check").text(currency(setValue).format());
                        $("#split_value_field").val(setValue)
                        $("#split_value").attr("hidden", false);
                        $("#split_value_field").attr("required", true);
                        $("#split_value_field").attr("min", true);
                        $('#totalcost').text(setValue);
                        $("#split-payment").prop('checked', true);
                    }
                }

                if (isChangePeopleOrSwitchPackageTopUpRadio) {
                    $(".split-payment-div").hide();
                } else if (this.value == 'Pay Later' || this.value == 'Payment Link' || isPartnerBooking == 1) {
                    $('#dividedBy_2').val("Please select option");
                    $("#split_value_field1").val(0);
                    $("#split-payment").prop('checked', false);
                    $(".split-payment-div").hide();
                } else {
                    $('#dividedBy').val("Please select option").trigger('change');
                    $(".split-payment-div").show();
                }
            });
        });

        $('input[type=radio][name=payment_method_cart_payment]').change(function () {
            if (this.value == 'Pay Later' || this.value == 'Cash') {
                if(this.value == 'Cash' && isCashFees == 1) {
                    $('.total-cart-payment').text(currency(parseFloat(cartPaymentTotalForCard).toFixed(2)).format());                                                    
                } else {
                    $('.total-cart-payment').text(currency(parseFloat(cartPaymentTotalForCash).toFixed(2)).format());
                }
                
                $('.card-details-cart-payment').hide();
            }
            if (this.value == 'Card') {
                $('.total-cart-payment').text(currency(parseFloat(cartPaymentTotalForCard).toFixed(2)).format());
                $('.card-details-cart-payment').show();
            }
        });

        $(document).on('change', '#cancelBooking input[type=checkbox][name=is_cash]', function () {
            if (this.value == 'Cash') {
                $('#cancelBooking #isWireCancelBooking').prop('checked', false)
            }
            if (this.value == 'Wire Transfer') {
                $('#cancelBooking #isCashCancelBooking').prop('checked', false)
            }
        });

        $(document).on('change', '#refundBooking input[type=checkbox][name=is_cash]', function () {
            if (this.value == 'Cash') {
                $('#refundBooking #isWireRefundBooking').prop('checked', false);
            }
            if (this.value == 'Wire Transfer') {
                $('#refundBooking #isCashRefundBooking').prop('checked', false)
            }
        });

        // These checkboxes live outside #form_id, so their state only reaches the backend via
        // the hidden #edit_booking #is_cash field they mirror into. `change` fires on BOTH check
        // AND uncheck, but `this.value` never changes either way — so setting the hidden field
        // unconditionally inside `if (this.value == 'Cash')` re-wrote it to 'Cash' even when the
        // Cash box was the one just being UNCHECKED, leaving it permanently stuck once checked
        // once. Must gate on `this.checked` and re-derive the hidden value from whichever (if
        // any) box is actually still checked, so unchecking both clears it back to '' — the
        // state the backend reads as "no explicit Cash/Wire override, refund the original way".
        $(document).on('change', '#deleteBooking input[type=checkbox][name=is_cash]', function () {
            if (this.checked) {
                if (this.value == 'Cash') {
                    $('#deleteBooking #isWireDeleteBooking').prop('checked', false);
                } else if (this.value == 'Wire Transfer') {
                    $('#deleteBooking #isCashDeleteBooking').prop('checked', false);
                }
            }
            $('#edit_booking #is_cash').val($('#deleteBooking input[type=checkbox][name=is_cash]:checked').val() || '');
        });

        $(document).on('change', '#permitFeesRefund input[type=checkbox][name=is_cash_refund]', function () {
            if (this.checked) {
                if (this.value == 'Cash') {
                    $('#permitFeesRefund #is_wire_refund').prop('checked', false);
                } else if (this.value == 'Wire Transfer') {
                    $('#permitFeesRefund #is_cash_refund').prop('checked', false);
                }
            }
            $('#edit_booking #is_cash').val($('#permitFeesRefund input[type=checkbox][name=is_cash_refund]:checked').val() || '');
        });

        $(function () {
            $("#discount_percentage, #discount_fixed_money").prop('disabled', true);

            var $radioButtons = $('.payment-condition-item input[type="radio"]');

            $radioButtons.on('change', function () {
                $radioButtons.each(function () {
                    $(this).parent().toggleClass('checked-item', this.checked);
                });

                if (this.id == 'pay-with-cash')
                    $('#card-element').addClass('d-none');
                else if (this.id == 'pay-with-card') // there are other radio buttons so only 'else' can not be specified
                    $('#card-element').removeClass('d-none');

                if (this.id == 'pay-with-cash' && pricedAddonAdded == 0) {
                    $('#cash-paid-amount1').prop("readonly", false);
                } else {
                    $('#cash-paid-amount1').prop("readonly", true);
                }
            });
        });

        function enableEditDiscount(chk) {
            var discountFixedMoney = $("#edit_discount_fixed_money");
            var discountPercentage = $("#edit_discount_percentage");
            var discountPromoCode = $("#edit_discount_promo_code");
            var applyPromoBtn = $(".apply-promo-btn");
            var removePromoBtn = $(".remove-promo-btn");

            if (chk.value == 'Percentage') {
                disableAndClear(discountFixedMoney, discountPromoCode);
                enableAndFocus(discountPercentage);
                applyPromoBtn.show();
                removePromoBtn.hide();
            } else if (chk.value == 'Promo Code') {
                disableAndClear(discountFixedMoney, discountPercentage);
                enableAndFocus(discountPromoCode);
            } else {
                enableAndFocus(discountFixedMoney);
                disableAndClear(discountPercentage, discountPromoCode);
                applyPromoBtn.show();
                removePromoBtn.hide();
            }
        }

        function enableDiscount(chk) {
            $('#discount_applied').val(0);
            $("#is_applied").val(0);

            var discountFixedMoney = $("#discount_fixed_money");
            var discountPercentage = $("#discount_percentage");
            var discountPromoCode = $("#discount_promo_code");
            var applyPromoBtn = $(".apply-promo-btn");
            var removePromoBtn = $(".remove-promo-btn");

            if (chk.value == 'Percentage') {
                disableAndClear(discountFixedMoney, discountPromoCode);
                enableAndFocus(discountPercentage);
                applyPromoBtn.show();
                removePromoBtn.hide();
            } else if (chk.value == 'Promo Code') {
                disableAndClear(discountFixedMoney, discountPercentage);
                enableAndFocus(discountPromoCode);
            } else {
                enableAndFocus(discountFixedMoney);
                disableAndClear(discountPercentage, discountPromoCode);
                applyPromoBtn.show();
                removePromoBtn.hide();
            }

            if ($('#modal_affilate').data('call-by-edit-booking-add-people')) {
                applyDiscountAddPeople();
            } else if ($('#modal_affilate').data('call-by-edit-booking-change-people')) {
                applyDiscountChangePeople();
            } else {
                applyDiscount();
            }
        }

        function disableAndClear(element1, element2) {
            element1.prop('disabled', true).val('');
            element2.prop('disabled', true).val('');
        }

        function enableAndFocus(element) {
            element.prop('disabled', false).focus();
        }

        function removeDiscount() {
            if ($("#is_applied").val() == 1) {
                $(".apply-promo-btn").show();
                $(".remove-promo-btn").hide();
                $("#discount_type_promo_code").prop('checked', false);
                $("#discount_promo_code").prop('disabled', true).val('');
                $("#is_applied").val(0);
                applyDiscount();
            }

            if ($("#edit_is_applied").val() == 1) {
                $(".apply-edit-promo-btn").show();
                $(".remove-edit-promo-btn").hide();
                $("#edit_is_applied").val(0);
                $("#edit_discount_type_promo_code").prop('checked', false);
                $("#edit_discount_promo_code").prop('disabled', true).val('');
            }
        }

        function sendDiscountPaymentLink() {
            showLoading();
            $.ajax({
                headers: { Accept: 'application/json' },
                dataType: 'json',
                type: 'POST',
                url: "{{ route(prefixedRouteName($routePrefix . 'booking.edit-discount-payment-link')) }}",
                data: {
                    '_token': '{{ csrf_token() }}',
                    'discountHistory': JSON.stringify(discountHistoryArray)
                },
                success: function (result) {
                    hideLoading();
                    $('#modal_edit_discount_cash_payment').modal('hide');
                    $('#modal_edit_discount_card_payment').modal('hide');
                    swal(result.message || 'Payment link sent successfully.', 'Page will reload', 'success')
                        .then(function () { location = location; });
                },
                error: function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                }
            }).fail(function () { hideLoading(); });
        }

        function updatePayment() {
            var selectedMethod = $('input[name="payment_method_discount"]:checked').val();
            if (selectedMethod === 'Payment Link') {
                sendDiscountPaymentLink();
                return;
            }
            var formData = $('#modal-edit-discount-cash-payment').serializeArray();
            formData.push({ name: "discountHistory", value: JSON.stringify(discountHistoryArray) });
            var amountDiscount = $('.total-cash-payment').html();
            var field_type = amountDiscount;
            var inArray = $.inArray(field_type, fieldRequestPermissions);
            if (inArray !== -1) {
                discountPermission = "Yes";
            }
            if (discountPermission == 'No') {
                formData.push({ name: "field_type", value: field_type });
                formData.push({ name: "request_type", value: 'Discount' });
                requestType(formData, '#modal_edit_discount_cash_payment');
                return false;
            }
            if (discountPermission == "Yes") {
                showLoading();
                $.ajax({
                    headers: { Accept: 'application/json' },
                    dataType: 'json',
                    type: 'POST',
                    url: $('#modal-edit-discount-cash-payment').attr('action'),
                    data: formData,
                    success: function (result) {
                        hideLoading();
                        $('#modal_edit_discount_cash_payment').modal('hide');
                        if (result.discountHistory.payment_method == 'Cash') {
                            if (result.discountHistory.new_discount2_percentage > 0) {
                                var discountVal = '- (' + result.discountHistory.new_discount2_percentage + '%) $' + result.discountHistory.new_discount2_value.toFixed(2);
                            } else {
                                var discountVal = '- $' + parseFloat(result.discountHistory.new_discount2_value).toFixed(2);
                            }
                            var paymentTotal = (parseFloat(result.discountHistory.new_total) + parseFloat(result.discountHistory.service_commission)).toFixed(2);
                            $("#totalPaument").html(currencyFormation.symbol + paymentTotal);
                        }
                        swal('Booking updated successfully', 'Page will reload', 'success')
                            .then(function () {
                                // location.reload();
                                const url = window.location.href;
                                const urlObj = new URL(url);
                                if (urlObj.searchParams.has('split')) {
                                    urlObj.searchParams.delete('split');
                                }
                                const updatedUrl = urlObj.toString();
                                window.location.replace(updatedUrl);
                            });
                    },
                    error: function (jqXHR) {
                        showSubmissionError(jqXHR);
                    }
                }).fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                }).always(hideLoading);
            }
        }

        function updateCardPayment(paymentIntentId = '') {
            var selectedMethod = $('input[name="payment_method_discount"]:checked').val();
            if (selectedMethod === 'Payment Link') {
                sendDiscountPaymentLink();
                return;
            }
            var formData = $('#modal-edit-discount-card-payment').serializeArray();
            formData.push({ name: "discountHistory", value: JSON.stringify(discountHistoryArray) });
            if (discountHistoryArray['payment_type'] == "refund") {
                formData.push({ name: "check_multiple_transactions_refund", value: $('.check_multiple_transactions_refund:checked').val() });
                if (isMultipleCardPayments == 1 || splitPayment == '1') {
                    if ($('.check_multiple_transactions_refund:checked').length == 0) {
                        swal('Sorry!', 'Choose from which account you want to refund', 'warning');
                        return false;
                    }
                    if ($('.check_multiple_transactions_refund:checked').length > 1) {
                        $(this).prop('checked', false);
                        swal('', 'You can select only one payment transaction.', 'error');
                        return false;
                    }
                }
            }
            var form_data = (document.getElementById("modal-edit-discount-card-payment"));
            var history = JSON.stringify(discountHistoryArray);
            var feature = 'edit_discount';
            $(form_data).append(`<input type="hidden" name="feature" value="edit_discount">`);
            $(form_data).append(`<input type="hidden" name="discountHistory" value='${history}'>`);
            var amountDiscount = $('.total-cash-payment').html();
            var field_type = amountDiscount;
            var inArray = $.inArray(field_type, fieldRequestPermissions);
            if (inArray !== -1) {
                discountPermission = "Yes";
            }
            if (discountPermission == 'No') {
                formData.push({ name: "field_type", value: field_type });
                formData.push({ name: "request_type", value: 'Discount' });
                requestType(formData, '#modal_edit_discount_card_payment');
                return false;
            }
            if (discountHistoryArray['payment_type'] == "refund") {
                if (discountPermission == "Yes") {
                    submitEditDiscount(formData, 'refund');
                }
            } else {
                if (discountPermission == "Yes") {
                    if (!paymentIntentId) {
                        initTourBookingStripePayment.initializeStripe(form_data, feature);
                        return false;
                    } else {
                        formData.push({ name: "payment_intent_id", value: paymentIntentId });
                        submitEditDiscount(formData, 'card reader');
                    }

                }
            }

        }

        function editDiscount() {
            var formData = $('#editdiscountForm').serializeArray();
            const editDiscountType = formData.find(a => a.name === "edit_discount_type")?.value;
            if (editDiscountType == "undefined" || editDiscountType == undefined) {
                swal('Please enter valid discount', '', 'warning');
                return false;
            }
            if ((editDiscountType == 'Fixed Money' && $('#edit_discount_fixed_money').val() == '') || (editDiscountType == 'Percentage' && $('#edit_discount_percentage').val() == '') || (editDiscountType == 'Promo Code' && $('#edit_discount_promo_code').val() == '')) {
                swal('Please enter valid discount', '', 'warning');
                return false;
            }

            dataObj = {};
            $(formData).each(function (i, field) {
                dataObj[field.name] = field.value;
            });
            if (discountPermission == 'No' && dataObj['payment_method'] == "Pay Later") {
                validationMangerRequest(formData, 'Discount', discountPermission, '#modal_edit_discount', '#editdiscountForm', 'additional');
                return false;
            }
            
        const isZeroCash = dataObj['payment_method'] === 'Pay Later'
                && editDiscountType === 'Percentage'
                && parseFloat($('#edit_discount_percentage').val()) == 100;

            if (isZeroCash) {
                swal({
                    title: 'Confirm $0 Booking',
                    text: 'Applying a 100% discount will set this booking total to $0 and record it as a Cash payment. Do you want to proceed?',
                    icon: 'warning',
                    buttons: {
                        cancel: { text: 'Cancel', value: null, visible: true },
                        confirm: { text: 'Yes, Confirm', value: true, className: 'btn-danger' }
                    }
                }).then(function (confirmed) {
                    if (confirmed) {
                        showLoading();
                        $.ajax({
                            headers: { Accept: 'application/json' },
                            dataType: 'json',
                            type: 'POST',
                            url: $('#editdiscountForm').attr('action'),
                            data: formData,
                            success: function (result) {
                                hideLoading();
                                $('#modal_edit_discount').modal('hide');
                                swal('Booking updated successfully', 'Page will reload', 'success')
                                    .then(function () {
                                        const urlObj = new URL(window.location.href);
                                        if (urlObj.searchParams.has('split')) {
                                            urlObj.searchParams.delete('split');
                                        }
                                        window.location.replace(urlObj.toString());
                                    });
                            },
                            error: function (xhr) {
                                hideLoading();
                                swal('Something went wrong', '', 'error');
                            }
                        });
                    }
                });
                return false;
            }
            showLoading();
            $.ajax({
                headers: { Accept: 'application/json' },
                dataType: 'json',
                type: 'POST',
                url: $('#editdiscountForm').attr('action'),
                data: formData,
                success: function (result) {
                    hideLoading();

                    $('#modal_edit_discount').modal('hide');
                    discountHistoryArray = result.discountHistory;
                    if (result.discountHistory.payment_method == 'Pay Later' || result.discountHistory.is_split == 1) {
                        if (result.discountHistory.new_discount2_percentage > 0) {
                            var discountVal = '- (' + result.discountHistory.new_discount2_percentage + '%) ' + currencyFormation.symbol + result.discountHistory.new_discount2_value.toFixed(2);
                        } else {
                            var discountVal = '- ' + currencyFormation.symbol + result.discountHistory.new_discount2_value.toFixed(2);
                        }
                        $("#payLater").html(currencyFormation.symbol + result.discountHistory.new_total.toFixed(2));
                        swal('Booking updated successfully', 'Page will reload', 'success')
                            .then(function () {
                                // location.reload();
                                const url = window.location.href;
                                const urlObj = new URL(url);
                                if (urlObj.searchParams.has('split')) {
                                    urlObj.searchParams.delete('split');
                                }
                                const updatedUrl = urlObj.toString();
                                window.location.replace(updatedUrl);
                            });
                    } else if (result.discountHistory.payment_method == 'Cash' || result.discountHistory.payment_method == "Invoiced") {
                        if (result.paymentStatus == null) {
                            swal('Booking updated successfully.', 'Page will reload', 'success')
                                .then(function () {
                                    const urlObj = new URL(window.location.href);
                                    if (urlObj.searchParams.has('split')) {
                                        urlObj.searchParams.delete('split');
                                    }
                                    window.location.replace(urlObj.toString());
                                });
                            return;
                        }
                        var payment = (parseFloat(result.discountHistory.rate) - parseFloat(result.discountHistory.new_total)).toFixed(2);
                        if (result.discountHistory.payment_method == "Invoiced") {
                            $("#pay-type").html('Invoiced');
                        }

                        if (result.paymentStatus == 'additional') {
                            $('#modal_edit_discount_cash_payment').modal('toggle');
                            $(".edit-payment-method").show();
                            if (result.discountHistory.payment_method == "Invoiced") {
                                $(".total-cash-payment").html(currencyFormation.symbol + result.discountHistory.new_discount2_value);
                            } else {
                                $(".total-cash-payment").html(currencyFormation.symbol + result.cashAmount.toFixed(2));
                            }
                            $(".total-payment-text").html('Total payment');
                            $("#btn-make-cash-payment").val('Make a Payment');
                            $('#modal-edit-discount-cash-payment').attr('action', "{{ route(prefixedRouteName($routePrefix . 'booking.edit-discount-payment')) }}");
                        } else if (result.paymentStatus == 'refund') {
                            $('#modal_edit_discount_cash_payment').modal('toggle');
                            $(".edit-payment-method").hide();
                            if (result.discountHistory.payment_method == "Invoiced") {
                                $(".total-cash-payment").html(currencyFormation.symbol + result.discountHistory.new_discount2_value);
                            } else {
                                $(".total-cash-payment").html(currencyFormation.symbol + result.cashAmount.toFixed(2));
                            }
                            $(".total-payment-text").html('Total Refundable');
                            $("#btn-make-cash-payment").val('Make a Refund');
                            $('#modal-edit-discount-cash-payment').attr('action', "{{ route(prefixedRouteName($routePrefix . 'booking.edit-discount-refund')) }}");
                        }
                    } else if (result.discountHistory.payment_method == 'Card') {
                        var payment = (parseFloat(result.discountHistory.rate) - parseFloat(result.discountHistory.new_total)).toFixed(2);
                        if (result.paymentStatus == 'additional') {
                            $('#modal_edit_discount_card_payment').modal('toggle');
                            $(".edit-payment-method").show();
                            // Reset to Card selected by default each time the modal opens.
                            $('#pay-with-crd').prop('checked', true);
                            $("#btn-make-card-payment").val('Make a Payment');
                            $('#btn-make-card-payment').attr('onclick', 'updateCardPayment()');
                            // Combine with any existing unpaid payment link amount.
                            var discountPendingAmt  = parseFloat($('#modal_edit_discount_card_payment').data('pending-amount')) || 0;
                            var discountNewAmt      = parseFloat(result.cashAmount) || 0;
                            var discountCombinedAmt = discountNewAmt + discountPendingAmt;
                            $(".total-cash-payment").html(currencyFormation.symbol + discountCombinedAmt.toFixed(2));
                            $(".total-payment-text").html('Total payment');
                            if (discountPendingAmt > 0) {
                                $('#pending-items-summary-discount').removeClass('hidden');
                                $('#pending-items-amount-discount').text(currencyFormation.symbol + discountPendingAmt.toFixed(2));
                                $('#discount-combined-charge-total').val(discountCombinedAmt.toFixed(2));
                            } else {
                                $('#pending-items-summary-discount').addClass('hidden');
                                $('#discount-combined-charge-total').val('0');
                            }
                            $('#modal-edit-discount-card-payment').attr('action', "{{ route(prefixedRouteName($routePrefix . 'booking.edit-discount-payment')) }}");
                            $('.connect-to-card-reader').show();
                            var fees = result.discountHistory.service_commission;
                            backendCardReaderInfo.getTotal(discountCombinedAmt.toFixed(2));
                            backendCardReaderInfo.getFees(fees);
                        } else if (result.paymentStatus == 'refund') {
                            $('#modal_edit_discount_card_payment').modal('toggle');
                            $('#mutliple-transaction-container').removeClass('hidden');
                            $(".edit-payment-method").hide();
                            $(".total-cash-payment").html(currencyFormation.symbol + result.cashAmount.toFixed(2));
                            $(".total-payment-text").html('Total Refundable');
                            $("#btn-make-card-payment").val('Make a Refund');
                            $('#modal-edit-discount-card-payment').attr('action', "{{ route(prefixedRouteName($routePrefix . 'booking.edit-discount-refund')) }}");
                            $('.connect-to-card-reader').hide();
                            $('.dis-connect-to-card-reader').hide();
                            $('.card-reader-status-output').hide();
                        }
                    }
                },
                error: function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                }
            }).fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            }).always(hideLoading);
        }

        function applyEditPromoDiscount() {
            var promoCode = $("#edit_discount_promo_code").val();
            var tourPackageId = '{{$bookingM->tour_package_id}}';

            if (!promoCode) {
                swal('Please enter promo code', '', 'warning');
                return;
            }

            $.ajax({
                url: "get-promo-detail",
                // 'bookingId' lets the backend check the promo's own min_allowed_tourists
                // against this EXISTING booking's already infant-excluded tourist count.
                data: { 'promoCode': promoCode, 'tourPackageId': tourPackageId, 'tourSlotId': tourSlotId, 'bookingId': '{{$bookingM->id}}' },
                type: 'POST',
                dataType: 'json',
                success: function (result) {
                    $(".apply-edit-promo-btn").hide();
                    $(".remove-edit-promo-btn").show();
                    $("#edit_is_applied").val(1);
                },
                error: function (jqXHR) {
                    showSubmissionError(jqXHR);
                }
            });
        }

        function applyPromoDiscount() {
            var promoCode = $("#discount_promo_code").val();
            var tourPackageId = '{{$bookingM->tour_package_id}}';

            if (!promoCode) {
                swal('Please enter promo code', '', 'warning');
                return;
            }

            $.ajax({
                url: "get-promo-detail",
                // 'bookingId' lets the backend check the promo's own min_allowed_tourists
                // against this EXISTING booking's already infant-excluded tourist count.
                data: { 'promoCode': promoCode, 'tourPackageId': tourPackageId, 'tourSlotId': tourSlotId, 'bookingId': '{{$bookingM->id}}' },
                type: 'POST',
                dataType: 'json',
                success: function (result) {
                    $("#is_applied").val(1);
                    var discount = calculateDiscount(result);
                },
                error: function (jqXHR) {
                    showSubmissionError(jqXHR);
                }
            });
        }

        // Affiliate "Pay with Link" must follow the discount too — mirrors the backend link
        // amount (DiscountService::applyPaymentLinkDiscount() + handleMakePaymentSection()):
        // (ticket total - discount) + platform fee on that + unpaid add-ons/custom charges/tips.
        function updateAffiliateLinkAmount(discount) {
            var $h = $('#hidden_link_affiliate');
            if (!$h.length || $h.data('dynamic') != 1) return;
            var amount = parseFloat($h.data('original')) || 0;
            discount = parseFloat(discount) || 0;
            if (discount > 0) {
                var base = (parseFloat($h.data('base')) || 0) - discount;
                var fee = roundout(base * (parseFloat($h.data('fee-pct')) || 0) / 100, 2);
                amount = base + fee + (parseFloat($h.data('extras')) || 0);
            }
            $('.link-cp-affiliate-amount').text(currency(amount).format());
            if ($('#pay-with-link-cp-affiliate').is(':checked')) {
                $('#totalcost').text(currency(amount).format());
            }
        }

        function calculateDiscount(promoDetails) {
            var discount = 0;
            var discount_card = 0;
            var discount_cash = 0;
            var span_invoiced = $('#hideeninvoiced').val();
            if (isCashFees == '1') {
                var span_cash = $('#hideencashwithoutfees').val();
            } else {
                var span_cash = $('#hideencash').val();
            }
            var span_card = $('#hideencard').val();
            var customcharge = parseFloat($('#hideencustomcharge').val());
            var customchargefees = parseFloat($('#hideencustomchargefees').val());
            var inputVal = promoDetails.discount_value;

            if (promoDetails.discount_value_type === 'Percent') {

                if (isNaN(inputVal)) // blank or something else
                    inputVal = 0;

                // remove custom charge from total amount to apply discount on ticket subtotal price
                span_invoiced_cc = (customcharge > 0) ? (span_invoiced - customcharge) : span_invoiced;
                span_cash_cc = (customcharge > 0) ? (span_cash - customcharge) : span_cash;
                span_card_cc = (customcharge > 0) ? (span_card - customcharge) : span_card;

                discount = (span_invoiced_cc * inputVal) / 100;
                discount_cash = (span_cash_cc * inputVal) / 100;
                discount_card = (span_card_cc * inputVal) / 100;
                if (discount < 0 || discount_cash < 0 || discount_card < 0) {
                    $('#discount_promo_code').val('');
                    applyPromoDiscount();
                }
                if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
                    $('#pr_amount').val(discount);
                }

                if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                    $('#pr_amount').val(discount_cash);
                }

                if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                    $('#pr_amount').val(discount_card);
                }

            } else {
                if (isNaN(inputVal)) {
                    discount = discount_cash = discount_card = 0;
                } else {
                    discount = discount_cash = discount_card = inputVal;
                }

                $('#pr_amount').val(discount);
            }
    
            if (discount_cash > (span_cash - customcharge) || discount > span_invoiced) {
                swal('Invalid Discount', 'Discount amount cannot exceed the ticket cost.', 'error');
                $('#discount_promo_code').val('');
                return false;
            }

            span_invoiced = span_invoiced - discount;
            span_cash = span_cash - discount_cash;
            cashAmountWithDiscount = span_cash;
            if (Number(bookingId) < Number(oldBookingId)) {
                span_card = span_card - discount_card;
            } else {
                if (isCashFees == '1') {
                    span_cash = span_cash - customcharge; // Only if custom charge applied
                    span_card = parseFloat(span_cash) + roundout((span_cash * natServiceCommission / 100), 2);
                    span_card = span_cash = span_card + customcharge + customchargefees; // Only if custom charge and fees applied
                } else {
                    if (discount_cash > 0) {
                        span_cash = span_cash - customcharge; // Only if custom charge applied
                        span_card = parseFloat(span_cash) + roundout((span_cash * natServiceCommission / 100), 2);
                        span_card = span_card + customcharge + customchargefees; // Only if custom charge and fees applied
                        span_cash = span_cash + customcharge;
                    } else {
                        span_card = parseFloat(totalForCardAffiliate);
                    }
                }
            }

            if (span_invoiced < 0 || span_cash < 0 || span_card < 0) {
                $('#discount_percentage').val('');
                $('#discount_fixed_money').val('')
                applyDiscount();
                return false;
            }

            $('#span_invoiced').text(currency(span_invoiced).format());
            $('#span_cash').text(currency(span_cash).format());
            $('#span_card').text(currency(span_card).format());
            updateAffiliateLinkAmount(discount);
            if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                var vl = $('#dividedBy').find('option:selected').val();
                if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                    var splitValue = span_card.toFixed(2) / vl;
                    $('#split_value_field').val(splitValue.toFixed(2));
                    $('.split-check').text(splitValue.toFixed(2));
                    $('#totalcost').text(splitValue.toFixed(2));
                }

                if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                    var splitValue = span_cash.toFixed(2) / vl;
                    $('#split_value_field').val(splitValue.toFixed(2));
                    $('.split-check').text(splitValue.toFixed(2));
                    $('#totalcost').text(splitValue.toFixed(2));
                }
            }

            $('input[type=radio][name=payment_method]').change(function () {
                if (this.value == 'Pay Later') {
                    $('#htt').val(span_invoiced);
                    $('#totalcost').text(currency(span_invoiced).format());
                }

                if (this.value == 'Cash') {
                    $('#htt').val(span_cash);
                    if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                        var vl = $('#dividedBy').find('option:selected').val();
                        var splitValue = span_cash.toFixed(2) / vl;
                        $('#split_value_field').val(splitValue.toFixed(2));
                        $('.split-check').text(splitValue.toFixed(2));
                        $('#totalcost').text(currency(splitValue.toFixed(2)).format());
                    } else {
                        $('#totalcost').text(currency(span_cash).format());
                    }
                }

                if (this.value == 'Card') {
                    $('#htt').val(span_card);
                    if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                        var vl = $('#dividedBy').find('option:selected').val();
                        var splitValue = span_card.toFixed(2) / vl;
                        $('#split_value_field').val(splitValue.toFixed(2));
                        $('.split-check').text(splitValue.toFixed(2));
                        $('#totalcost').text(currency(splitValue.toFixed(2)).format());
                    } else {
                        $('#totalcost').text(currency(span_card).format());
                    }
                }
            });

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
                $('#htt').val(span_invoiced);
                $('#totalcost').text(currency(span_invoiced).format());
            }

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                $('#htt').val(span_cash);
                $('#totalcost').text(currency(span_cash).format());
            }

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                $('#htt').val(span_card);
                $('#totalcost').text(currency(span_card).format());
            }

            // Show or hide promo buttons based on whether a promo code is applied
            if (!$('#discount_promo_code').val()) {
                $(".apply-promo-btn").show();
                $(".remove-promo-btn").hide();
            } else {
                $(".apply-promo-btn").hide();
                $(".remove-promo-btn").show();
            }
        }

        function removePromoDiscount() {
            var addText = 'Are you sure you want to remove this promo code?';
            swal({
                title: 'Remove Promo Code',
                text: addText,
                icon: "warning",
                dangerMode: true,
                buttons: true,
                buttons: {
                    no: {
                        text: 'Cancel',
                        value: false,
                    },
                    yes: {
                        text: 'Remove',
                        value: true,
                        className: 'btn-danger',
                    }
                }
            }).then(function (result) {
                if (result == true) {
                    removeDiscount();
                }
            });
        }

        function applyDiscount() {
            if ($('#modal_affilate').data('call-by-edit-booking-add-people')) {
                applyDiscountAddPeople();
            } else if ($('#modal_affilate').data('call-by-edit-booking-change-people')) {
                applyDiscountChangePeople();
            } else {
                var addonsPrice = Number($('#addons-price').val());
                var addonsFee = Number($('#addons-fee').val());
                var addonsTotal = Number(addonsPrice) + Number(addonsFee);

                var discount = 0;
                var discount_card = 0;
                var discount_cash = 0;
                var span_invoiced = $('#hideeninvoiced').val();
                if (isCashFees == '1') {
                    var span_cash = $('#hideencashwithoutfees').val();
                } else {
                    var span_cash = $('#hideencash').val();
                }

                var span_card = $('#hideencard').val();
                var customcharge = parseFloat($('#hideencustomcharge').val());
                var customchargefees = parseFloat($('#hideencustomchargefees').val());

                if (($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {
                    if ($('#discount_type_percentage')[0]?.checked) {
                        var inputVal = parseFloat($('#discount_percentage').val());

                        if (isNaN(inputVal)) // blank or something else
                            inputVal = 0;

                        // remove custom charge from total amount to apply discount on ticket subtotal price
                        span_invoiced_cc = (customcharge > 0) ? (span_invoiced - customcharge) : span_invoiced;
                        span_cash_cc = (customcharge > 0) ? (span_cash - customcharge) : span_cash;
                        span_card_cc = (customcharge > 0) ? (span_card - customcharge) : span_card;

                        discount = (span_invoiced_cc * inputVal) / 100;
                        discount_cash = (span_cash_cc * inputVal) / 100;
                        discount_card = (span_card_cc * inputVal) / 100;
                        if (discount < 0 || discount_cash < 0 || discount_card < 0) {
                            $('#discount_percentage').val('');
                            applyDiscount();
                        }

                        if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
                            $('#pr_amount').val(discount);
                        }

                        if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                            $('#pr_amount').val(discount_cash);
                        }

                        if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                            $('#pr_amount').val(discount_card);
                        }
                    } else {
                        var inputVal = parseFloat($('#discount_fixed_money').val());

                        if (isNaN(inputVal)) {
                            discount = discount_cash = discount_card = 0;
                        } else {
                            discount = discount_cash = discount_card = inputVal;
                        }

                        $('#pr_amount').val(discount);
                    }
                }

                if (discount_cash > (span_cash - customcharge) || discount > span_invoiced) {
                    swal('Invalid Discount', 'Discount amount cannot exceed the ticket cost.', 'error');
                    $('#discount_percentage').val('');
                    $('#discount_fixed_money').val('');
                    applyDiscount();
                    return false;
                }
                span_invoiced = span_invoiced - discount;
                span_cash = span_cash - discount_cash;
                cashAmountWithDiscount = span_cash;
                if (Number(bookingId) < Number(oldBookingId)) {
                    span_card = span_card - discount_card;
                } else {
                    if (isCashFees == '1') {
                        span_cash = span_cash - customcharge; // Only if custom charge applied
                        span_card = parseFloat(span_cash) + roundout((span_cash * natServiceCommission / 100), 2);
                        span_card = span_cash = span_card + customcharge + customchargefees; // Only if custom charge and fees applied
                    } else {
                        if (discount_cash > 0) {
                            span_cash = span_cash - customcharge; // Only if custom charge applied
                            span_card = parseFloat(span_cash) + roundout((span_cash * natServiceCommission / 100), 2);
                            span_card = span_card + customcharge + customchargefees; // Only if custom charge and fees applied
                            span_cash = span_cash + customcharge;
                        } else {
                            span_card = parseFloat(totalForCardAffiliate);
                        }
                    }
                }

                if (span_invoiced < 0 || span_cash < 0 || span_card < 0) {
                    $('#discount_percentage').val('');
                    $('#discount_fixed_money').val('')
                    applyDiscount();
                    return false;
                }

                $('#span_invoiced').text(currency(span_invoiced).format());
                $('#span_cash').text(currency(span_cash).format());
                $('#span_card').text(currency(span_card).format());
                updateAffiliateLinkAmount(discount);
                if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                    var vl = $('#dividedBy').find('option:selected').val();
                    if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                        var splitValue = span_card.toFixed(2) / vl;
                        $('#split_value_field').val(splitValue.toFixed(2));
                        $('.split-check').text(splitValue.toFixed(2));
                        $('#totalcost').text(splitValue.toFixed(2));
                    }

                    if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                        var splitValue = span_cash.toFixed(2) / vl;
                        $('#split_value_field').val(splitValue.toFixed(2));
                        $('.split-check').text(splitValue.toFixed(2));
                        $('#totalcost').text(splitValue.toFixed(2));
                    }
                }

                $('input[type=radio][name=payment_method]').change(function () {
                    if (this.value == 'Pay Later') {
                        $('#htt').val(span_invoiced);
                        $('#totalcost').text(currency(span_invoiced).format());
                    }

                    if (this.value == 'Cash') {
                        $('#htt').val(span_cash);
                        if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                            var vl = $('#dividedBy').find('option:selected').val();
                            var splitValue = span_cash.toFixed(2) / vl;
                            $('#split_value_field').val(splitValue.toFixed(2));
                            $('.split-check').text(splitValue.toFixed(2));
                            $('#totalcost').text(currency(splitValue.toFixed(2)).format());
                        } else {
                            $('#totalcost').text(currency(span_cash).format());
                        }
                    }

                    if (this.value == 'Card') {
                        $('#htt').val(span_card);
                        if ($('#dividedBy').find('option:selected').val() != "Please select option" && $('#dividedBy').find('option:selected').val() != undefined) {
                            var vl = $('#dividedBy').find('option:selected').val();
                            var splitValue = span_card.toFixed(2) / vl;
                            $('#split_value_field').val(splitValue.toFixed(2));
                            $('.split-check').text(splitValue.toFixed(2));
                            $('#totalcost').text(currency(splitValue.toFixed(2)).format());
                        } else {
                            $('#totalcost').text(currency(span_card).format());
                        }
                    }
                });

                if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
                    $('#htt').val(span_invoiced);
                    $('#totalcost').text(currency(span_invoiced).format());
                }

                if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                    $('#htt').val(span_cash);
                    $('#totalcost').text(currency(span_cash).format());
                }

                if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                    $('#htt').val(span_card);
                    $('#totalcost').text(currency(span_card).format());
                }
            }
        }

        function calculatePercentagePartialRefund() {
            var partial_refund = 0;
            if ($('#refund_type_percentage')[0].checked) {
                $('#percentage_refund_value').removeAttr('Disabled');
                var inputVal = parseFloat($('#percentage_refund_value').val());

                if (isNaN(inputVal)) // blank or something else
                    inputVal = 0;

                partial_refund = (total_amount_paid * inputVal) / 100;
                $('#partial_refund_value_refund_booking').val(partial_refund.toFixed(2));
            } else {
                $('#percentage_refund_value').attr('disabled', 'disabled');
                $('#partial_refund_value_refund_booking').val('');
            }
        }

        $(document).ready(function () {
            // "i" button in the Edit Discount pending payment row — load breakdown via shared loadBreakdownItems()
            $(document).on('click', '#btn-discount-payment-breakdown-inline', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $('#modal_edit_discount_card_payment').data('breakdown-url');
                if (!url) return;
                loadBreakdownItems(url);
            });

            $('input[name="payment_method_discount"]').on('change', function () {
                var isLink = $(this).val() === 'Payment Link';
                var label = isLink ? 'Send Payment Link' : 'Make a Payment';
                $('#btn-make-cash-payment').val(label);
                $('#btn-make-card-payment').val(label);
                // Wire the onclick directly so the correct handler fires regardless of
                // any selector race or caching issue at click time.
                if (isLink) {
                    $('#btn-make-cash-payment').attr('onclick', 'sendDiscountPaymentLink(); return false;');
                    $('#btn-make-card-payment').attr('onclick', 'sendDiscountPaymentLink(); return false;');
                } else {
                    $('#btn-make-cash-payment').attr('onclick', 'updatePayment()');
                    $('#btn-make-card-payment').attr('onclick', 'updateCardPayment()');
                }
            });

            $("#inlineRadio2").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value").attr("hidden", false);
                    $("#partial_refund_value_field").attr("required", true);
                }
            });

            $("#inlineRadio1").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value").attr("hidden", true);
                    $("#partial_refund_value_field").attr("required", false);
                }
            });

            $("#inlineRadio3").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value").attr("hidden", true);
                    $("#partial_refund_value_field").attr("required", false);
                }
            });

            $("#inlineRadio4").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value").attr("hidden", true);
                    $("#partial_refund_value_field").attr("required", false);
                }
            });

        });
        $(document).on('click', '.refund_type', function () {
            var radioValue = $(this).val();
            if (radioValue == 'partial_refund') {
                $("#partial_refund_value_switch_package").attr("hidden", false);
                $("#partial_refund_value_field_switch_package").attr("required", true);
            } else {
                $("#partial_refund_value_switch_package").attr("hidden", true);
                $("#partial_refund_value_field_switch_package").attr("required", false);
            }

            initCustomFormDisplay.getRefundType(radioValue);
        });
        $(document).ready(function () {
            $("#Radio2").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value2").attr("hidden", false);
                    $("#partial_refund_value_field2").attr("required", true);
                }
            });

            $("#Radio1").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value2").attr("hidden", true);
                    $("#partial_refund_value_field2").attr("required", false);
                }
            });

            $("#Radio3").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value2").attr("hidden", true);
                    $("#partial_refund_value_field2").attr("required", false);
                }
            });

            $("#Radio4").click(function () {
                var radioValue = $("input[name='refund_type']:checked").val();
                if (radioValue) {
                    $("#partial_refund_value2").attr("hidden", true);
                    $("#partial_refund_value_field2").attr("required", false);
                }
            });
        });

        $('#button_id').on('click', function (e) {
            var bk = "{{ $bookingM->total }}";
            var paymentType = 1;
            var value = $("#partial_refund_value_field2").val();
            if ($("#refund_Type").val() == "partial_refund" && value == "") {
                swal('Sorry!', 'Please enter partial refund amount.', 'warning');
                return false;
            }
            value = parseFloat(value);
            bk = parseFloat(bk);

            if (value > bk) {
                // $('#Partial-delete').text('Unable to refund booking. Please enter amount less than total.');
                // return  false;
            }

            // Partial Refund never touches add-ons (see recalcDeleteBookingBreakdown), so this
            // check only applies to the refund types that actually refund add-on money. Validated
            // PER RATE GROUP (not just a total count) — removing 1 Adult and 1 Child must not
            // allow selecting 2 Adults' or 2 Children's add-ons, even though the total (2) would
            // otherwise look fine. Works for both trigger flows: Flow 1 (direct "Edit Booking"
            // delete) has checked .ads_Checkbox rows at this point, each tagged with its rate
            // group (data-pax-id); Flow 2 (Change People) doesn't, so it falls back to the
            // per-rate-group counts tour-booking-change-people.js already computed before opening
            // this modal, mapped rate group id -> pax_id via rateGroupIdToPaxId.
            //
            // Skipped entirely for a pure add-on-only Change People edit (no passengers added or
            // removed at all) — the whole point of this check is "don't refund more people's
            // add-ons than are actually being removed," which is meaningless when nobody is being
            // removed. Block 3's own checkbox list already scopes itself to exactly the add-ons
            // Change People V2 is removing (getRemovalScope()'s 'exact' mode), so there's nothing
            // left to validate here — the count-based fallback below has no pax removals to count
            // and would otherwise reject every selection with a false "exceeded" positive.
            var isAddonOnlyChangePeopleEdit = (typeof totalDeletedPeopleCount !== 'undefined' && totalDeletedPeopleCount === 0)
                && (typeof totalAddedPeopleCount !== 'undefined' && totalAddedPeopleCount === 0);

            if (!isAddonOnlyChangePeopleEdit && $("#refund_Type").val() != "partial_refund") {
                var allowedByPaxId = {};
                $('.ads_Checkbox:checked').each(function () {
                    var paxId = $(this).attr('data-pax-id');
                    if (paxId) {
                        allowedByPaxId[paxId] = (allowedByPaxId[paxId] || 0) + 1;
                    }
                });
                if (Object.keys(allowedByPaxId).length === 0) {
                    var deletedCountByRateGroupId = (typeof totalDeletedCountByRateGroupId !== 'undefined') ? totalDeletedCountByRateGroupId : {};
                    var rateGroupMap = (typeof rateGroupIdToPaxId !== 'undefined') ? rateGroupIdToPaxId : {};
                    Object.keys(deletedCountByRateGroupId).forEach(function (rateGroupId) {
                        var paxId = rateGroupMap[String(rateGroupId)];
                        if (paxId !== undefined) {
                            allowedByPaxId[paxId] = (allowedByPaxId[paxId] || 0) + deletedCountByRateGroupId[rateGroupId];
                        }
                    });
                }

                var selectedPeopleByPaxId = {};
                $('#delete-booking-addons-list .delete-booking-addon-checkbox:checked').each(function () {
                    var paxId = $(this).attr('data-pax-id');
                    var paxIndex = $(this).attr('data-pax-index');
                    if (!selectedPeopleByPaxId[paxId]) {
                        selectedPeopleByPaxId[paxId] = {};
                    }
                    selectedPeopleByPaxId[paxId][paxIndex] = true;
                });

                var exceededRateGroup = Object.keys(selectedPeopleByPaxId).some(function (paxId) {
                    var distinctSelected = Object.keys(selectedPeopleByPaxId[paxId]).length;
                    return distinctSelected > (allowedByPaxId[paxId] || 0);
                });
                if (exceededRateGroup) {
                    swal('Sorry!', 'You have selected add-ons for more people than are being removed in one of the rate groups.', 'warning');
                    return false;
                }
            }

            if (isMultipleCardPayments == 1) {
                if ($('.check_transactions:checked').length == 0 && paymentType == 0) {
                    swal('Sorry!', 'Choose from which account you want to refund', 'warning');
                    return false;
                }
            }

            $("#partial_Refund_value").val(value);
            if (typeof isTourBookingInfo !== "undefined"
                && isTourBookingInfo === true
                && $('#deleteBooking').data('call-by-edit-booking-change-people')) {
                var editBookingChangePeopleForm = $('#edit_booking_change_people').find('form');
                editBookingChangePeopleForm.data('validation_check', false);
                var form = $('#form_id')[0];
                validateAndSubmitFormChangePeople(editBookingChangePeopleForm[0], form);
                return false;
            }

            var deleteBookings = $('#form_id').serializeArray();
            var additional = '';
            if (deletePermission == 'No') {
                validationMangerRequest(deleteBookings, 'Delete', deletePermission, '#deleteBooking', '#form_id', additional);
                return false;
            }
            if (deletePermission == 'Yes') {
                var $submitBtn = $(this);
                $submitBtn.prop('disabled', true);
                showLoading();
                // AJAX instead of a plain form submit so a validation failure (e.g. "must select
                // payment transaction" or "refund amount is greater than the unrefunded amount")
                // shows as a SweetAlert on top of this same page — via the existing
                // showSubmissionError() helper — instead of a full page reload that loses the
                // admin's in-progress selections and shows the error as a banner up top.
                $.ajax({
                    headers: { Accept: 'application/json' },
                    type: 'POST',
                    dataType: 'json',
                    url: $('#form_id').attr('action'),
                    data: $('#form_id').serialize(),
                }).done(function (response) {
                    window.location.replace(response.redirect || window.location.href);
                }).fail(function (jqXHR) {
                    hideLoading();
                    $submitBtn.prop('disabled', false);
                    showSubmissionError(jqXHR);
                });
            }
        });

        $('#button_id_cash_refund').on('click', function (e) {
            if (!$('#permitFeesRefund #is_wire_refund').is(':checked') && !$('#permitFeesRefund #is_cash_refund').is(':checked')) {
                swal('Sorry!', 'Please select payment method type.', 'warning');
                return false;
            }

            $(this).prop('disabled', true);
            showLoading();
            $('#form_id').submit();
        });

        $('#update_button').on('click', function () {
            //This is for change people feature
            if (typeof isTourBookingInfo !== "undefined"
                && isTourBookingInfo === true
                && $('#updateBooking').data('call-by-edit-booking-change-people')) {
                $('#checked_transactions').val('');

                if ($('.check_transactions_update:checked').length == 0) {
                    $(this).prop('checked', false);
                    swal('Sorry!', 'Choose from which account you want to refund', 'warning');
                    return false;
                }

                var editBookingChangePeopleForm = $('#edit_booking_change_people').find('form');
                editBookingChangePeopleForm.data('validation_check', false);
                var form = $('#form_id')[0];
                validateAndSubmitFormChangePeople(editBookingChangePeopleForm[0], form);
                return false;
            }

            if (isMultipleCardPayments == 1) {
                if ($('.check_transactions_update:checked').length == 0) {
                    swal('Sorry!', 'Choose from which account you want to refund', 'warning');
                    return false;
                }
            }

            $('#action_type').val('update');
            $(this).prop('disabled', true);
            showLoading();
            $('#form_id').submit();
        });

        $('#submitrefund').on('click', function () {
            if ($('#partial_refund_value_refund_booking').val() == '') {
                swal('Sorry!', 'Please enter Amount.', 'warning');
                return false;
            }

            if (IscardPayment == 1 && (isMultipleCardPayments == 1 || splitPayment == '1')) {
                if ($('.check_transactions_refund:checked').length == 0) {
                    swal('Sorry!', 'Choose from which account you want to refund', 'warning');
                    return false;
                }
            }

            if (IscardPayment == 1) {

                if ((isMultipleCardPayments == 1 && splitPayment == '1') || (isMultipleCardPayments == 0 && splitPayment == '1') || isMultipleCardPayments == 1) {
                    if ($('#comment-1').val() == '' || !$.trim($('#comment-1').val())) {
                        swal('Sorry!', 'Please add a note.', 'warning');
                        return false;
                    }
                } else {
                    if ($('#comment-').val() == '' || !$.trim($('#comment-').val())) {
                        swal('Sorry!', 'Please add a note.', 'warning');
                        return false;
                    }
                }
            } else {
                if ((isMultipleCardPayments == 1 && splitPayment == '1') || isMultipleCardPayments == 1) {
                    if ($('#comment-1').val() == '' || !$.trim($('#comment-1').val())) {
                        swal('Sorry!', 'Please add a note.', 'warning');
                        return false;
                    }
                } else {
                    if ($('#comment-').val() == '' || !$.trim($('#comment-').val())) {
                        swal('Sorry!', 'Please add a note.', 'warning');
                        return false;
                    }
                }
            }

            var refundform = $('#refundform').serializeArray();
            var additional = "";
            if (refundPermission == 'No') {
                validationMangerRequest(refundform, 'Refund', refundPermission, '#refundBooking', '#refundform', additional);
                return false;
            } else {
                showLoading();
                $(this).prop('disabled', true);
                $('#refundform').submit();
            }

        });

        $(document).on('click', '#split_update_button', function () {
            if ($('.check_transactions_split_update:checked').length == 0) {
                swal('Sorry!', 'Choose from which accounts you want to split', 'warning');
                return false;
            }
            var form = $('#form_id')[0];
            splitPeople(form);
        });

        $(document).on('click', '#combo-payment-button', function () {
            if (paymentLinkActive == 'Yes') {
                comboPaymentValidation();
                return false;
            }
            comboPayment();

        });

        function comboPayment() {
            var confirmTitle = "Pay For All?";
            var confirmText = "Do you really want to pay for every combo bookings you've made?";
            swal({
                title: confirmTitle,
                text: confirmText,
                icon: "warning",
                buttons: {
                    no: {
                        text: 'No',
                        value: false,
                    },
                    yes: {
                        text: 'Yes',
                        className: 'btn-danger',
                    }
                }
            }).then(function (result) {
                if (result) {
                    $('#modal_cart_payment').modal('toggle');
                }
            });
        }

        function comboPaymentValidation() {
            swal({
                title: 'An active payment link',
                text: 'Are you still want to proceed? Existing payment link will be cancelled',
                icon: "warning",
                dangerMode: true,
                buttons: true,
                buttons: {
                    no: {
                        text: 'No',
                        value: false,
                    },
                    yes: {
                        text: 'Yes',
                        value: true,
                        className: 'btn-danger',
                    }
                }
            }).then(function (result) {
                if (result) {
                    comboPayment();
                } else {
                    return false;
                }
            });
        }

        $(document).on('click', '#split_people', function () {
            var splitBookingEd = "{{ $requestPermissions['Split Booking'] }}";
            if ($('.ads_Checkbox:checked').length == 0) {
                swal('Sorry!', 'Please select people to split booking', 'warning');
                return false;
            }
            var confirmTitle = "Split Booking?";
            var confirmText = "Are you sure want to seperate these guests from this booking?";
            swal({
                title: confirmTitle,
                text: confirmText,
                icon: "warning",
                buttons: {
                    no: {
                        text: 'No',
                        value: false,
                    },
                    yes: {
                        text: 'Yes',
                        className: 'btn-danger',
                    }
                }
            }).then(function (result) {
                if (result) {
                    // Add-on scope for the split pax is now resolved server-side from the same
                    // "check[]" positions the tour side already submits — no "Passenger Add-ons"
                    // modal round trip needed before submitting.
                    splitPeopleBooking(splitBookingEd);
                }
            });
        });
        
        function splitPeopleBooking(splitBookingEd) {
            var checkArray = [];
            var contactIdsArray = [];
            // {itemId: {transactId: count}} — same precise shape editBookingDeletePeople() uses,
            // so a pending item shared by MULTIPLE unpaid pax (e.g. two unpaid children under one
            // change_people item) only loses the split-off pax's own count instead of being
            // cancelled outright when some of its pax stay behind on the old booking.
            var pendingItemTransactMap = {};
            var unpaidRateCountMap = {}; // {rateId: count} for mixed-split detection
            var allUnpaid = true;
            var anyChecked = false;
            var checkboxesList = $('.ads_Checkbox');
            checkboxesList.each(function (index) {
                if (checkboxesList[index].checked == true) {
                    anyChecked = true;
                    checkArray.push(index);

                    if ($(checkboxesList[index]).data('pending') != '1') {
                        allUnpaid = false;
                    } else {
                        var piId = parseInt($(checkboxesList[index]).data('pending-item-id') || 0);
                        var btId = parseInt($(checkboxesList[index]).data('pending-transact-id') || 0);
                        if (piId > 0 && btId > 0) {
                            if (!pendingItemTransactMap[piId]) pendingItemTransactMap[piId] = {};
                            pendingItemTransactMap[piId][btId] = (pendingItemTransactMap[piId][btId] || 0) + 1;
                        }
                        // Track unpaid pax count per rate_id for backend mixed-split handling
                        var rateId = $(checkboxesList[index]).val();
                        unpaidRateCountMap[rateId] = (unpaidRateCountMap[rateId] || 0) + 1;
                    }

                    if (packageHasContacts == 1) {
                        var contactIds = $(checkboxesList[index]).data('contact-id');
                        if (contactIds != "") {
                            contactIdsArray.push(contactIds);
                        }
                    }
                }
            });

            // Serialize as "itemId:transactId:count,..." so the backend removes/reduces the exact
            // pax per rate group instead of cancelling the whole shared item (see
            // PaymentLinkService::cancelPendingChangePeopleItems()'s partial-removal path).
            var pendingItemEntries = [];
            Object.keys(pendingItemTransactMap).forEach(function (itemId) {
                Object.keys(pendingItemTransactMap[itemId]).forEach(function (transactId) {
                    pendingItemEntries.push(itemId + ':' + transactId + ':' + pendingItemTransactMap[itemId][transactId]);
                });
            });

            // Reset all split flags from any previous run
            $('#split_is_all_unpaid').val('0');
            $('#split_pending_item_ids').val('');
            $('#split_unpaid_check_counts').val('');

            if (anyChecked && allUnpaid) {
                // All selected pax are unpaid — bypass payment modal entirely.
                $('#split_is_all_unpaid').val('1');
                $('#split_pending_item_ids').val(pendingItemEntries.join(','));
            } else if (anyChecked && !allUnpaid && pendingItemEntries.length > 0) {
                // Mixed: some paid, some unpaid — pass unpaid info for post-split payment link.
                $('#split_pending_item_ids').val(pendingItemEntries.join(','));
                $('#split_unpaid_check_counts').val(JSON.stringify(unpaidRateCountMap));
            }

            $('#tourits_numbers').val(checkArray.join());
            if (packageHasContacts == 1) {
                if (contactIdsArray.length > 0) {
                    $('#contact_ids').val(JSON.stringify(contactIdsArray));
                }
            }

            var form = $('#form_id')[0];

            // Only show the payment selection modal when pax are NOT all unpaid.
            if (!anyChecked || !allUnpaid) {
                if (isMultipleCardPayments == 1) {
                    if (splitPayment != 1) {
                        $('#is_calculate_amount').val('1');
                    }
                }
            }

            var splitBooking = $(form).serializeArray();
            var additional = 'Split Booking';
            if (splitBookingEd == "No") {
                validationMangerRequest(splitBooking, 'Split Booking', splitBookingEd, '#edit_booking', form, additional);
                return false;
            } else {
                splitPeople(form);
            }
        }

        $("#refund_Type").val("full_refund");
        $(document).on("change", ".Refcheck", function () {
            $("#refund_Type").val($(this).val());
        });

        $(document).on("change", ".Refcheck", function () {
            $("#refund_Type").val($(this).val());
        });

        function validateAndSubmitAddonsRefundForm(event) {
            event.preventDefault();
            initCustomFormDisplay.makeAddonsRefund();
        }

        function validateAndSubmitAddonsPaymentForm(event) {
            event.preventDefault();
            initCustomFormDisplay.makeAddonsPayment();
        }
    </script>
    <!-- Make Payment Admin -->
    <script type="text/javascript">
        $(document).on("click", ".checkset", function () {
            // Split Payment only makes sense when this popup is collecting a brand-new, full
            // payment. When it's really an incremental top-up on a booking that's already been
            // paid (opened with call-by-edit-booking-change-people/call-by-switch-package, e.g.
            // after adding pax via Change People), the modal's own show.bs.modal handler already
            // hides .split-payment-div — but this delegated click handler runs on every payment
            // method click afterward and was unconditionally re-showing it for Card/Cash, with no
            // awareness of that flag. Scope the show/hide decisions to skip re-showing it here too.
            var $checksetModal = $(this).closest('.modal');
            var isChangePeopleOrSwitchPackageTopUp = !!($checksetModal.data('call-by-edit-booking-change-people') || $checksetModal.data('call-by-switch-package'));
            if ($(this).val() == "Cash" || $(this).val() == "Pay Later") {
                var cashamount = ($(".cash-amount").text());
                $(".set-total").text(cashamount);
                if (($('#dividedBy_2').find('option:selected').val() != "Please select option")) {
                    var vl = $('#dividedBy_2').find('option:selected').val();
                    var M = $(".cash-amount").text();
                    M = parseFloat(M.replace(currencyFormation.symbol, ""));
                    var setValue = M / vl;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field1").val(setValue)
                    $("#split_value1").attr("hidden", false);
                    $("#split_value_field1").attr("required", true);
                    $("#split_value_field1").attr("min", true);
                    $('.set-total').text('');
                    $('.set-total').text(setValue);
                    $("#split-payment1").prop('checked', true);
                }

                if ($("input[name='split_checkbox']:checked").serialize() == "split_checkbox=1") {
                    $(".set-total").text('');
                    $(".set-total").text($("#split_value_field1").val());
                }

                if (this.value == 'Pay Later' || isPartnerBooking == 1) {
                    $('#dividedBy_2').val("Please select option").trigger('change');
                    $("#split_value_field1").val(0);
                    $("#split-payment").prop('checked', false);
                    $(".split-payment-div").hide();
                } else if (!isChangePeopleOrSwitchPackageTopUp) {
                    $(".split-payment-div").show();
                }

                if (isChangePeopleOrSwitchPackageTopUp || (($(this).val() == "Pay Later" && pricedAddonAdded == 1) || isPartnerBooking == 1)) {
                    $(".split-payment-div").hide();
                } else {
                    $(".split-payment-div").show();
                }
                if ($(this).val() == 'Cash' && pricedAddonAdded == 0) {
                    $('#cash-paid-amount-div').show();
                    $('#cash-paid-amount').prop("readonly", false);
                } else {
                    $('#cash-paid-amount-div').hide();
                    $(".split-payment-div").hide();
                    $('#cash-paid-amount').prop("readonly", true);
                }
            }

            if ($(this).val() == "Card") {
                if (!isChangePeopleOrSwitchPackageTopUp && pricedAddonAdded == 0 && customChargeTotal == 0 && isPartnerBooking != 1) {
                    $(".split-payment-div").show();
                } else {
                    $(".split-payment-div").hide();
                }
                var cardamount = ($(".card-amount").text());
                $(".set-total").text(cardamount);

                    
                if (($('#dividedBy_2').find('option:selected').val() != "Please select option")) {
                    var vl = $('#dividedBy_2').find('option:selected').val();
                    var K = $(".card-amount").text();
                    K = parseFloat(K.replace(currencyFormation.symbol, ""));
                    var setValue = K / vl;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field1").val(setValue)
                    $("#split_value1").attr("hidden", false);
                    $("#split_value_field1").attr("required", true);
                    $("#split_value_field1").attr("min", true);
                    $('.set-total').text(setValue);
                    $("#split-payment1").prop('checked', true);
                }

                if ($("input[name='split_checkbox']:checked").serialize() == "split_checkbox=1") {
                    $(".set-total").text('');
                    $(".set-total").text($("#split_value_field1").val());
                }

                $('#cash-paid-amount-div').hide();
                $('#cash-paid-amount').prop("readonly", true)
            }

            if ($(this).val() == "Payment Link") {
                var linkamount = ($(".link-cp-amount").text());
                $(".set-total").text(linkamount);
                $(".payment-details").hide();
                $('#cash-paid-amount-div').hide();
                $(".split-payment-div").hide();
            }

            if ($("input[name='split_checkbox']:checked").serialize() == "split_checkbox=1") {
                if ($('#pay-with-later').is(":checked") == true || $('#split-payment1').is(":checked") == false) {
                    $(".split-check").text(currency(0).format());
                    
                    $("#split_value_field").val('')
                    $("#split_value").attr("hidden", true);
                    $("#split_value_field").attr("required", false);
                    $("#split_value_field").attr("min", false);
                    
                    $("#split_value_field1").val('')
                    $("#split_value1").attr("hidden", true);
                    $("#split_value_field1").attr("required", false);
                    $("#split_value_field1").attr("min", false);
                   
                    $(".split-set").text(currency(0).format());
                    $("#split-payment1").prop('checked', false);
                    $("#split-payment").prop('checked', false);
                } else {
                    $("#split_value").attr("hidden", false);
                    $("#split_value_field").attr("required", true);
                    $("#split_value1").attr("hidden", false);
                    $("#split_value_field1").attr("required", true);
                    $("#split-payment1").prop('checked', true);
                    $("#split-payment").prop('checked', true);
                }
            }

            if ($("input[name='split_checkbox']:checked").serialize() == "" || $('input[type=radio][name=payment_method]:checked').val() == 'Pay Later' || $(this).val() == "Pay Later") {
                $(".split-check").text(currency(0).format());
                $("#split_value_field").val('')
                $("#split_value").attr("hidden", true);
                $("#split_value_field").attr("required", false);
                $("#split_value_field").attr("min", false);
                $("#split_value_field1").val('')
                $("#split_value1").attr("hidden", true);
                $("#split_value_field1").attr("required", false);
                $("#split_value_field1").attr("min", false);
                $(".split-set").text(currency(0).format());
                $("#split-payment").prop('checked', false);
                $("#split-payment1").prop('checked', false);
                if ($(this).val() == "Card") {
                    $(".set-total").text($(".card-amount").text());
                    $("#totalcost").text($("#span_card").text());
                }
                if ($(this).val() == "Cash" || $(this).val() == "Pay Later") {
                    $(".set-total").text($(".cash-amount").text());
                    $("#totalcost").text($("#span_cash").text());
                }
            }
        });
        $(".checkset1").click(function () {
            if ($(this).val() == 'Cash') {
                $('#cash-paid-amount2').prop("readonly", false);
            } else {
                $('#cash-paid-amount2').prop("readonly", true);
            }
        });

        $(".checkset2").click(function () {
            if ($(this).val() == "Cash" || $(this).val() == "Pay Later") {
                $(".card-details-cart-payment").hide();
            }

            if ($(this).val() == "Card") {
                $(".card-details-cart-payment").show();
            }
        });

        $('input[name="split_value"]').keyup(function () {
            var split_value = $(this).val() !== '' ? $(this).val() : Number(0).toFixed(2);
            $(".split-check").text(currency(split_value).format());
            $(".split-set").text(currency(split_value).format());
            $(".set-total").text(currency(split_value).format());
        });

        $(document).ready(function () {
            if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                $(".set-total").text($(".card-amount").text());
            }

            $('#btn-make-payment1').on('click', function (e) {
                var K = $(".card-amount").text();
                var I = ($(".invoiced-amount").length) ? $(".invoiced-amount").text() : '0';
                var M = ($(".cash-amount").length) ? $(".cash-amount").text() : '0';
                var V = $('#split_value_field1').val();
                K = K.replace(currencyFormation.symbol, "");
                I = I.replace(currencyFormation.symbol, "");
                M = M.replace(currencyFormation.symbol, "");
                K = parseFloat(K);
                I = parseFloat(I);
                M = parseFloat(M);
                V = parseFloat(V);
                if (V > K || ($(".invoiced-amount").length && V > I) || ($(".cash-amount").length && V > M)) {
                    $('#validate').text('Split amount is greater than payment amount please check.');
                    e.preventDefault();
                }
            });
        });

        $(document).ready(function () {
            $('#btn-make-payment').on('click', function (e) {
                var K = $(".card-amount").text();
                var I = $(".invoiced-amount").text();
                var M = $(".cash-amount").text();
                var V = $('#split_value_field').val();

                K = K.replace(currencyFormation.symbol, "");
                I = I.replace(currencyFormation.symbol, "");
                M = M.replace(currencyFormation.symbol, "");
                K = parseFloat(K);
                I = parseFloat(I);
                M = parseFloat(M);
                V = parseFloat(V);

                if (V > K || V > I || V > M) {
                    $('#validate1').text('Split amount is greater than payment amount please check.');
                    e.preventDefault();
                }
                var formData = $('#makePaymentForm').serializeArray();
                var additional = 'Change people discount_';
                dataObj = {};
                $(formData).each(function (i, field) {
                    dataObj[field.name] = field.value;
                })
                if (discountPermission == "No" && dataObj['pr_amount'] > 0) {
                    validationMangerRequest(formData, 'Discount', discountPermission, '#modal_affilate', '#makePaymentForm', additional);
                    return false;
                }
            });
        });

        $('#dividedBy').change(function () {
            var $option = $(this).find('option:selected');
            var value = $option.val();
            if (value == "Please select option") {
                $(".split-check").text(currency(0).format());
                $("#split_value_field").val('')
                $("#split_value").attr("hidden", true);
                $("#split_value_field").attr("required", false);
                $("#split_value_field").attr("min", false);
                $(".split-set").text(currency(0).format());
                $("#split-payment").prop('checked', false);

                if ($('input[name=payment_method]:checked').val() == "Card") {
                    $("#totalcost").text($("#span_card").text());
                }

                if ($('input[name=payment_method]:checked').val() == "Cash" || $('input[name=payment_method]:checked').val() == "Pay Later") {
                    $("#totalcost").text($("#span_cash").text());
                }
            } else {
                if ($('input[type=radio][name=payment_method]:checked').val() == "Card") {
                    var K = $("#span_card").text().trim();
                    K = K.replace(currencyFormation.symbol, "").replace(/,/g, "");
                    K = parseFloat(K);
                    var setValue = K / value;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field").val(setValue)
                    $("#split_value").attr("hidden", false);
                    $("#split_value_field").attr("required", true);
                    $("#split_value_field").attr("min", true);
                    $('#totalcost').text(currencyFormation.symbol + setValue);
                    $("#split-payment").prop('checked', true);
                }

                if ($('input[name=payment_method]:checked').val() == "Cash") {
                    var M = $("#span_cash").text().trim();
                    M = M.replace(currencyFormation.symbol, "").replace(/,/g, "");
                    M = parseFloat(M);
                    var setValue = M / value;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field").val(setValue)
                    $("#split_value").attr("hidden", false);
                    $("#split_value_field").attr("required", true);
                    $("#split_value_field").attr("min", true);
                    $('#totalcost').text(currencyFormation.symbol + setValue);
                    $("#split-payment").prop('checked', true);
                }

                if ($('input[name=payment_method]:checked').val() == "Pay Later") {
                    var M = $("#span_invoiced").text();
                    M = M.replace(currencyFormation.symbol, "").replace(/,/g, "");
                    M = parseFloat(M);
                    var setValue = M / value;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field").val(setValue)
                    $("#split_value").attr("hidden", false);
                    $("#split_value_field").attr("required", true);
                    $("#split_value_field").attr("min", true);
                    $('#totalcost').text(currencyFormation.symbol + setValue);
                    $("#split-payment").prop('checked', false);
                }

                if ($('#modal_affilate').data('call-by-edit-booking-change-people') || $('#modal_affilate').data('call-by-switch-package')) {
                    $(".split-payment-div").hide();
                } else if ($('input[name=payment_method]:checked').val() == 'Pay Later' || isPartnerBooking == 1) {
                    $('#dividedBy').val("Please select option");
                    $("#split_value_field").val(0);
                    $("#split-payment").prop('checked', false);
                    $(".split-payment-div").hide();
                } else {
                    $(".split-payment-div").show();
                }
            }
        });

        $('#dividedBy_2').change(function () {
            var $option = $(this).find('option:selected');
            var value = $option.val();
            if (value == "Please select option") {
                $(".split-check").text(currency(0).format());
                $("#split_value_field1").val('')
                $("#split_value1").attr("hidden", true);
                $("#split_value_field1").attr("required", false);
                $("#split_value_field1").attr("min", false);
                $(".split-set").text(currency(0).format());
                $("#split-payment1").prop('checked', false);

                if ($('input[name=payment_method]:checked').val() == "Card") {
                    $('.set-total').text($(".card-amount").text());
                }

                if ($('#pay-with-cash1').is(":checked") == true || $('input[name=payment_method]:checked').val() == "Pay Later") {
                    $('.set-total').text($(".cash-amount").text());
                }
            } else {
                if ($('input[type=radio][name=payment_method]:checked').val() == "Card") {
                    var K = $(".card-amount").text();
                    K = K.replace(currencyFormation.symbol, "").replace(/,/g, "");
                    K = parseFloat(K);
                    var setValue = K / value;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field1").val(setValue)
                    $("#split_value1").attr("hidden", false);
                    $("#split_value_field1").attr("required", true);
                    $("#split_value_field1").attr("min", true);
                    $('.set-total').text(currencyFormation.symbol + setValue);
                    $("#split-payment1").prop('checked', true);
                }

                if ($('#pay-with-cash1').is(":checked") == true) {
                    var M = $(".cash-amount").text();
                    M = M.replace(currencyFormation.symbol, "").replace(/,/g, "");
                    M = parseFloat(M);
                    var setValue = M / value;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field1").val(setValue)
                    $("#split_value1").attr("hidden", false);
                    $("#split_value_field1").attr("required", true);
                    $("#split_value_field1").attr("min", true);
                    $('.set-total').text(currencyFormation.symbol + setValue);
                    $("#split-payment1").prop('checked', true);
                }

                if ($('#pay-with-later').is(":checked") == true) {
                    var M = $(".invoiced-amount").text();
                    M = M.replace(currencyFormation.symbol, "").replace(/,/g, "");
                    M = parseFloat(M);
                    var setValue = M / value;
                    setValue = parseFloat(setValue).toFixed(2);
                    $(".split-check").text(currency(setValue).format());
                    $("#split_value_field1").val(setValue)
                    $("#split_value1").attr("hidden", false);
                    $("#split_value_field1").attr("required", true);
                    $("#split_value_field1").attr("min", true);
                    $('.set-total').text(currencyFormation.symbol + setValue);
                    $("#split-payment1").prop('checked', true);
                }
            }
        });

        $(document).on("change", ".check_transactions", function () {
            // Radio buttons (shared name="check_transactions_radio") — the browser already
            // guarantees at most one is checked, so there's no need to manually enforce/uncheck
            // a second selection or accumulate a comma-separated list here.
            $('#checked_transactions').val('');

            if (typeof isTourBookingInfo !== "undefined"
                && isTourBookingInfo === true
                && $('#deleteBooking').data('call-by-edit-booking-change-people')) {
                $('#checked_transactions_update').val($(this).val());
                $('#form_id #checked_transactions').val($(this).val());
            } else {
                $('#checked_transactions').val($(this).val());
            }
        });

    </script>
    <script src="{{ asset('js/user-common/delete-booking-refund.js') }}"></script>
    <script type="text/javascript">

        $(document).on("change", ".check_transactions_update", function () {
            var radioValue = $("#refund_Type").val();
            $('#checked_transactions').val('');
            $('#checked_transactions_update').val('');
            if ($('.check_transactions_update:checked').length > 1) {
                $(this).prop('checked', false);
                swal('', 'You can select only one payment transaction.', 'error');
                return false;
            }

            var val = $('#checked_transactions_update').val();
            if (val == '') {
                $('#checked_transactions_update').val($(this).val());
            } else {
                $('#checked_transactions_update').val(val + ',' + $(this).val());
            }

            var val1 = $('#checked_transactions').val();
            if (val1 == '') {
                $('#checked_transactions').val($(this).val());
            } else {
                $('#checked_transactions').val(val1 + ',' + $(this).val());
            }
        });

        $(document).on("change", ".check_transactions_split_update", function () {
            var checkedTrans = '';
            $('#checked_transactions').val('');
            $('.check_transactions_split_update').each(function () {
                if (this.checked) {
                    checkedTrans += $(this).val() + ',';
                }
            });
            $('#checked_transactions').val(checkedTrans);
        });


        $(document).on("change", ".check_multiple_transactions_refund", function () {
            if ($('.check_multiple_transactions_refund:checked').length > 1) {
                $(this).prop('checked', false);
                swal('', 'You can select only one payment transaction.', 'error');
                return false;
            }
        });

        // Only For Bundle Package Bookings
        function updateCheckInAndRedeemTicketStatus(index, rate_id, type, status, checkin_type) {
            bundleBookingInfo.updateCheckInAndRedeemTicketStatus(index, rate_id, type, status, checkin_type);
        }

        function validateAndSubmitEditBookingAddContactForm(form) {
            tourBookingInfoContacts.validateAndSubmitEditBookingAddContactForm(form);
        }

        function postTourBooking(form) {
            showLoading();
            $.ajax({
                headers: { Accept: 'application/json' },
                dataType: 'json',
                type: 'POST',
                url: $(form).attr('action'),
                data: $(form).serialize(),
            })
                .done(function (response) {
                    if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
                        window.location.href = response.nextAction.redirect_to_url.url;
                    } else {
                        tourBooked(form, response);
                    }
                })
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                    $('#btn-make-payment')[0].disabled = false;
                })
                .always(hideLoading);
        }

        function copySchoolWaiverLink(url) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    showToast('School waiver link copied to clipboard!');
                }).catch(function() {
                    fallbackCopySchoolWaiverLink(url);
                });
            } else {
                fallbackCopySchoolWaiverLink(url);
            }
        }

        function fallbackCopySchoolWaiverLink(url) {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(url).select();
            document.execCommand('copy');
            $temp.remove();
            showToast('School waiver link copied to clipboard!');
        }

        function showToast(msg) {
            var $toast = $('<div>')
                .text(msg)
                .css({
                    position: 'fixed', bottom: '24px', left: '50%',
                    transform: 'translateX(-50%)',
                    background: '#047857', color: '#fff',
                    padding: '10px 20px', borderRadius: '8px',
                    fontSize: '13px', zIndex: 99999,
                    boxShadow: '0 2px 8px rgba(0,0,0,0.18)'
                });
            $('body').append($toast);
            setTimeout(function() { $toast.fadeOut(300, function() { $(this).remove(); }); }, 2500);
        }

    </script>
    <script>
        SocketManager.register(new BoardSocketModule());
        SocketManager.subscribe('tenant.{{ tenancy()->tenant->id }}');
    </script>
@endsection
