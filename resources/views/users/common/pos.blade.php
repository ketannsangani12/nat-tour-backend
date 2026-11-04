@extends('layouts.default-5')

@section('main-content')
    <section class="category-section">
        <div class="container">
            <div class="row" id="pos-main">
                <div class="col-lg-8 col-12 p-0">
                    <div class="main-content" id="mainContent">
                        <div class="category-btn-wrapper">
                            <div class="button-scroll">
                                @foreach($categories as $key=>$category)
                                <button class="tour-category {{ ($key == 0) ? 'active' : '' }}" data-id="{{ $category->id }}">{{ $category->name }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="category-items">
                            <div class="catalogue" id="tourPackages">
                                <!-- Order List -->
                            </div>
                        </div>
                    </div>
                    <div class="main-content order-wrap d-none" id="mainContentOnHold">
                        <div class="row" id="onHoldOrders">
                            <!-- On Hold Orders -->
                        </div>
                        <div class="row" id="ordersList">
                            <!-- Order List -->
                        </div>
                        <div class="row {{ (Auth::check()) ? 'd-none' : '' }}" id="customerOrder">
                            <h3>Scan Order QR Code </h3>
                            <div class="col-md-2"></div>
                            <div class="col-md-8"><div id="qr-reader" style="width: 500px;"></div>
                            <div id="qr-reader-results"></div></div>
                            <div class="col-md-2"></div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-12 p-0">
                    <!-- Manage Booking Step -->
                    <div id="cart-section">
                        <div class="booking-wrap" id="selected-packages">
                            <!-- Packages -->
                        </div>
                        <!-- Cart totals and note section-->
                        @include('partials.pos.cart._cart_totals')
                    </div>

                    <!-- Confirm Payment Step -->
                    @include('partials.pos.cart._confirm_payment')

                    <!-- Thank you Step -->
                    @include('partials.pos.cart._thank_you')

                    <!-- Order Info -->
                    @include('partials.pos.orders._order_info')
                </div>
            </div>

            <div class="row d-none" id="setting-main">
                <div class="main-content">
                    <div class="row tour-package-order-main" id="tourPackageOrder">
                        <!-- Drag-and-drop categories will be inserted here -->
                    </div>
                </div>
            </div>

            <!-- Calendar Model Start -->
            @include('partials.pos.modal-popups._calendar')
            <!-- Calendar Model End -->

            <!-- Discount Model Start -->
            @include('partials.pos.modal-popups._add_discount')
            <!-- Discount Model End -->

            <!-- Cancel Order Model Start -->
            @include('partials.pos.modal-popups._cancel_order')
            <!-- Cancel Order Model End -->

            <!-- Refund Order Model Start -->
            @include('partials.pos.modal-popups._refund_order')
            <!-- Refund Order Model End -->

            <!-- Add Note Model Start -->
            @include('partials.pos.modal-popups._add_note')
            <!-- Add Note Model End -->

            <!-- Manager Request Model Start -->
            @include('partials.pos.modal-popups._manager_request')
            <!-- Manager Request Model End -->
        </div>
    </section>
@endsection

@section('css-custom')
    <link href="{{ asset('css/pos.css') }}" rel="stylesheet">
@endsection

@section('css-libraries')
    @if(App::environment('local'))
        <link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
        <link rel="stylesheet" type="text/css" href="{{ asset('plugins/bootstrap-notify/animate.min.css') }}">
        <link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
    @else
        <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.2.3/animate.min.css">
    @endif
@endsection

@section('js-libraries')
    @if(App::environment('local'))
        <script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
        <script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
        <script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
        <script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
        <script src="{{ asset('plugins/bootstrap-notify/bootstrap-notify.min.js') }}"></script>
    @else
        <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
        <script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
        <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/mouse0270-bootstrap-notify/3.1.5/bootstrap-notify.min.js"></script>
    @endif

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
    <script src="{{ asset('plugins/pusher.js') }}"></script>
    <script src="{{ asset('plugins/card-reader.js') }}"></script>

    @if(!Auth::check())
        <script src="{{ asset('plugins/html5-qrcode/html5-qrcode.js')  }}"></script>
        <script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>
    @endif
    {{-- <script src="{{ asset('js/utils/helpers.js') }}"></script> --}}

    <script src="https://js.stripe.com/terminal/v1/"></script>
    <script src="{{ asset('js/utils/stripe-reader-identity.js')}}"></script>
    <script src="{{ asset('js/pos/stripe-card-reader.js')}}"></script>
@endsection

@section('js-custom')
    <script>
        var countryCode = "{{ countryCode() }}";
        var userType = "{{ session('userType')  }}";
        var categoryId = "{{ $defaultCategoryId }}";
        var stripePublicKey = "{{ env('STRIPE_API_PUBLIC_KEY')}}";
        var minMaxPaxApply = "{{ $minMaxPaxApply}}";
        var customerInfoMandatory = "{{ $customerInfoMandatory }}";
        var serverDatenow = moment('{{$currentTime}}');
        var cashFees = "{{ $cashFees }}";
        var checkPermissionOfEnableDiscount = "{{ checkPermission('discount') }}";
        var managerRequestEnableDiscount = "{{ $managerRequestEnable['Discount'] ?? '' }}";
        var allowedCancelOrderPermission = "{{ $allowedPermissions['POS Cancel Order'] ?? '' }}";
        var allowedRefundOrderPermission = "{{ $allowedPermissions['POS Refund Order'] ?? '' }}";
        var stripe = Stripe("{{ config('constants.stripe_keys.' . config('app.env') . '.0.public_key') }}");
        var routePrefixUrl = "{{ routePrefix() }}";
    </script>
    <script src="{{ asset('js/user-common/tour-booking-pos.js') }}"></script>
@endsection
