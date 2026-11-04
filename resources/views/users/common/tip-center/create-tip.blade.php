@extends('layouts.default-6')

@section('main-content')
@php
$currencySettings = [
'code' => currencyFormation(true, false),
'symbol' => currencyFormation(false, true),
'separator' => ',',
'decimal' => '.',
'precision' => 2,
];
@endphp
<!-- Main Content -->
<div class="container-fluid py-4">
    <div class="row g-3 justify-content-center">
        <!-- Card 1: QR Code -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm border-0 text-center h-100">
                <div class="card-body d-flex flex-column align-items-center">
                    <!-- Profile Image -->
                    @if ($guide->profile_photo_path != '' && !Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/users/'. $guide->profile_photo_path))
                    <img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/users/' . $guide->profile_photo_path) }}"
                        class="rounded-circle shadow mb-3 border border-3 border-light"
                        style="height: 100px; width: 100px; object-fit: cover;">
                    @else
                    <img src="{{ asset('images/no-photo.png') }}"
                        class="rounded-circle shadow mb-3 border border-3 border-light"
                        style="height: 100px; width: 100px; object-fit: cover;">
                    @endif

                    <h6 class="fw-bold mb-1">{{ $guide->first_name }} {{ $guide->last_name }}</h6>
                    <p class="text-muted small mb-3">Scan to leave a tip for your guide</p>

                    <div  id="qrCodeSection" class="bg-white p-2 rounded shadow-sm d-inline-block">
                        {!! QrCode::size(150)->generate(route('customer.pay-tip', $guideId)) !!}
                    </div>
                    <!-- Print Button -->
                    <button type="button" onclick="printQRCode()" class="btn btn-sm btn-primary mt-3">
                        Print QR Code
                    </button>
                </div>
            </div>
        </div>

        <!-- Card 2: Send payment link -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fas fa-paper-plane text-primary me-2"></i>Send Tip Payment Link
                    </h6>

                    @if(session('success-payment-link'))
                    <div class="alert alert-success alert-dismissible fade show small" role="alert">
                        {{ session('success-payment-link') }}
                        <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    @endif

                    <form method="post" action="{{ route(routePrefix().'.tip-center.send-tip-payment-link') }}">
                        @csrf
                        <div class="mb-3">
                            <input class="form-control form-control-sm" name="email" id="email"
                                type="email" required placeholder="Enter Customer Email">
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-sm">Send</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Card 3: Create Tip -->
        <div class="col-md-4 col-lg-3">
            <div class="card shadow-sm border-0 text-center h-100">
                <div class="card-body">
                    <h6 class="card-title mb-3">
                        <i class="fa-solid fa-money-bill-wave text-primary me-2"></i>Create Tip
                    </h6>

                    <!-- Profile Image -->
                    @if ($guide->profile_photo_path != '' && !Storage::disk('s3')->missing('images/tour-operator/' . $tourOperator->tenant_id . '/users/'. $guide->profile_photo_path))
                    <img src="{{ Storage::disk('s3')->url('images/tour-operator/' . $tourOperator->tenant_id . '/users/' . $guide->profile_photo_path) }}"
                        class="rounded-circle shadow mb-3 border border-3 border-light"
                        style="height: 100px; width: 100px; object-fit: cover;">
                    @else
                    <img src="{{ asset('images/no-photo.png') }}"
                        class="rounded-circle shadow mb-3 border border-3 border-light"
                        style="height: 100px; width: 100px; object-fit: cover;">
                    @endif

                    <h6 class="fw-bold mb-4">{{ $guide->first_name }} {{ $guide->last_name }}</h6>

                    <form id="payment-form" method="post" action="{{ route(routePrefix().'.tip-center.make-tip-payment') }}" onsubmit="return validateAndSubmitForm(this)">
                        @csrf
                        <div class="mb-3">
                            <input class="form-control form-control-sm" name="tip_amount"
                                id="tipAmount" type="number" required placeholder="Enter Tip Amount">
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span>Tip Fees:</span>
                                <span id="tipFees">{{$currencySettings['symbol']}}0.00</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold">
                                <span>Total:</span>
                                <span id="totalAmount">{{$currencySettings['symbol']}}0.00</span>
                            </div>
                        </div>
                        <input type="hidden" name="feature" value="create_tip">
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-sm" id="btn-make-payment">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <input type="hidden" id="currency" value='@json($currencySettings)'>
</div>
@endsection
<div class="modal fade custom-payment-modal" id="make-a-payment" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Secure Payment</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="payment-box text-start">
                    <div class="card-detail-main">
                        <div class="card-detail-wrp card-form-field">

                            <!-- <div id="link-authentication-element"></div> -->
                            <div id="payment-element"></div>

                            <div class="form-group text-start mt-3">
                                <button onclick="return handleMakePayment()" class="btn btn-primary payment-btn">
                                    <i class="fas fa-lock"></i> Pay Securely
                                </button>
                            </div>

                            <p class="secure-info">
                                <i class="fas fa-shield-alt"></i> Your payment is encrypted & secure.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@section('css-custom')
<link href="{{ asset('css/booking.css') }}" rel="stylesheet">
@endsection
@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap-5.1.1/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/currency.min.js') }}"></script>
@else
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/currency.js@2.0.4/dist/currency.min.js"></script>
@endif
<script src="https://js.stripe.com/v3/"></script>
@endsection

@section('js-custom')
<script>
    var createPaymentIntentUrl = "{{ route(prefixedRouteName('stripe.create-payment-intent-make-payment')) }}";
    var makeTipPaymentUrl = "{{ route(prefixedRouteName('tip-center.make-tip-payment')) }}";
    var countryCode = "{{ countryCode() }}";
    var natServiceCommission = "{{ $tourOperator->stripe_commission_percentage }}";

    var stripe = Stripe("{{ config('constants.stripe_keys.' . config('app.env') . '.0.public_key') }}");
</script>
<script src="{{ asset('js/user-common/tip-center/create-tip.js') }}"></script>
@endsection