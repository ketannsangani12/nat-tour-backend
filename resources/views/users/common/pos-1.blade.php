@extends('layouts.default-5')

@section('main-content')

<!-- Category Section  -->
<section class="category-section">
    <div class="container">
        <div class="row">
            <div class="col-xl-8 col-12 p-0">
                <div class="main-content">
                    <div class="category-btn-wrapper">
                        <div class="button-scroll">
                            @foreach($categories as $key=>$category)
                            <button class="tour-category {{ ($key == 0) ? 'active' : '' }}" data-id="{{ $category->id }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="category-items">
                        <div class="masonry" id="tourPackages">

                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-12 p-0">

                <!-- Manage Booking Step -->
                <div id="cart-section">
                    <div class="booking-wrap" id="selected-packages">
                    </div>
                    <div class="bottom-box">
                        <div class="payment-wrap">
                            <div class="payment-details d-flex align-items-center justify-content-between">
                                <h6>Fees</h6>
                                <p id="pos-subtotal">{{ moneyFormat(0.00) }}</p>
                            </div>
                            <div class="payment-details d-flex align-items-center justify-content-between">
                                <h6>Subtotal</h6>
                                <p id="pos-fees">{{ moneyFormat(0.00) }}</p>
                            </div>
                            <div class="payment-details d-flex align-items-center justify-content-between">
                                <h6>Total</h6>
                                <p id="pos-final-total">{{ moneyFormat(0.00) }}</p>
                            </div>
                        </div>
                        <div class="add-wrap">
                            <div class="accordion" id="addExample">
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#addcollapse" aria-expanded="true" aria-controls="addcollapse">Add</button>
                                    </h2>
                                    <div id="addcollapse" class="accordion-collapse collapse" data-bs-parent="#addExample">
                                        <div class="accordion-body p-0">
                                            <div class="btn-wrap d-flex justify-content-between">
                                                <button class="active">+ Discount</button>
                                                <button>+ Note</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="payment-btn-wrap">
                            <div class="accordion" id="paymentExample">
                                <div class="accordion-item border-0">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#paymentcollapse" aria-expanded="true" aria-controls="paymentcollapse">Payment</button>
                                    </h2>
                                    <div id="paymentcollapse" class="accordion-collapse collapse show" data-bs-parent="#paymentExample">
                                        <div class="accordion-body p-0">
                                            <ul class="nav nav-tabs" id="myTab" role="tablist">
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link book-pay-method active" id="Cash" data-bs-toggle="tab" data-bs-target="#cash" type="button" role="tab" aria-controls="cash" aria-selected="true"><i class="fa fa-money" aria-hidden="true"></i>Cash</button>
                                                </li>
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link book-pay-method" id="Card" data-bs-toggle="tab" data-bs-target="#card" type="button" role="tab" aria-controls="card" aria-selected="true"><i class="fa fa-credit-card" aria-hidden="true"></i>Card</button>
                                                </li>
                                            </ul>
                                            <div class="tab-content" id="myTabContent">
                                                <div class="tab-pane fade show active " id="cash" role="tabpanel" aria-labelledby="cash-tab"></div>
                                                <div class="tab-pane fade" id="card" role="tabpanel" aria-labelledby="card-tab"></div>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="pay-btn-wrap">
                            <button class="hold" id="hold-order"><i class="fa fa-pause-circle" aria-hidden="true"></i> Hold</button>
                            <button class="final-pay" id="confirm-pay-button"><span class="price" id="final-total">{{ moneyFormat(0.00) }}</span> <span class="pay">Pay</span></button>
                        </div>
                    </div>
                </div>
                <!-- Confirm Payment Step -->
                <div class="confirm-payment-wrap d-none" id="confirm-payment">
                    <div class="back-btn text-end">
                        <a href="#" id="back-to-cart-step"><i class="fa fa-angle-left" aria-hidden="true"></i> Back</a>
                    </div>
                    <div class="total-wrap">
                        <div class="accordion" id="cartTourPackagesTotals">
                            <div class="accordion-item border-0">
                                <div id="cartTourPackagesTotalcollapse" class="accordion-collapse collapse" data-bs-parent="#cartTourPackagesTotals">
                                    <div class="accordion-body p-0" id="cartPkgTotals">


                                    </div>
                                </div>
                                <h2 class="accordion-header d-flex justify-content-between">
                                    <button class="accordion-button collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#cartTourPackagesTotalcollapse" aria-expanded="true" aria-controls="cartTourPackagesTotalcollapse">Total</button>
                                    <span class="total-price" id="total">{{ moneyFormat(0.00) }}</span>
                                </h2>
                            </div>
                        </div>
                    </div>
                    <div class="payment-btn-wrap pb-0">
                        <div class="accordion" id="confirmPaymentTypes">
                            <div class="accordion-item border-0">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#confirmpaymentcollapse" aria-expanded="true" aria-controls="confirmpaymentcollapse">Payment</button>
                                </h2>
                                <div id="confirmpaymentcollapse" class="accordion-collapse collapse show" data-bs-parent="#confirmPaymentTypes">
                                    <div class="accordion-body p-0">
                                        <ul class="nav nav-tabs mb-0" id="myTab" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link confirm-pay-method" id="Cash" data-bs-toggle="tab" data-bs-target="#cash" role="tab" type="button"><i class="fa fa-money" aria-hidden="true"></i>Cash</button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link confirm-pay-method" id="Card" data-bs-toggle="tab" role="tab" data-bs-target="#card" type="button"><i class="fa fa-credit-card" aria-hidden="true"></i>Card</button>
                                            </li>
                                        </ul>
                                        <div class="tab-content" id="myTabContent">
                                            <div class="tab-pane fade show active " id="cash" role="tabpanel" aria-labelledby="cash-tab"></div>
                                            <div class="tab-pane fade" id="card" role="tabpanel" aria-labelledby="card-tab"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="calculator-wrap" id="calculator">
                        <input type="text" id="credit-amount-input" class="final-price form-group" style="width: 100%;" placeholder="0.00">
                        <div class="calculate d-flex align-items-center justify-content-between">
                            <h4>Credit</h4>
                            <h6 id="credit-amount">0.00</h6>
                        </div>
                        <div class="calculate d-flex align-items-center justify-content-between">
                            <h4>Balance</h4>
                            <h6 id="balance-amount">0.00</h6>
                        </div>
                        <div class="calculator-box d-flex justify-content-between flex-wrap">
                            <button class="calculator-keys" data-value="1">1</button>
                            <button class="calculator-keys" data-value="2">2</button>
                            <button class="calculator-keys" data-value="3">3</button>
                            <button class="calculator-keys" data-value="4">4</button>
                            <button class="calculator-keys" data-value="5">5</button>
                            <button class="calculator-keys" data-value="6">6</button>
                            <button class="calculator-keys" data-value="7">7</button>
                            <button class="calculator-keys" data-value="8">8</button>
                            <button class="calculator-keys" data-value="9">9</button>
                            <button class="calculator-keys" data-value="0">0</button>
                            <button class="calculator-keys" data-value="00">00</button>
                            <button class="calculator-keys" data-value="1"><i class="fa fa-times" aria-hidden="true"></i></button>
                            <button class="calculator-keys" data-value=".">.</button>
                            <button style="width: 66%;">Cancel</button>
                        </div>
                    </div>
                    <div class="confirm-pay-btn-wrap">
                        <button class="confirm-btn" id="confirm-payment-button">Confirm Payment</button>
                    </div>
                </div>


                <!-- Thank you Step -->
                <div class="thank-you-wrap d-none">
                    <div class="back-btn text-end">
                        <a href="#"><i class="fa fa-angle-left" aria-hidden="true"></i> Back</a>
                    </div>
                    <div class="text-ty d-flex align-items-center justify-content-center"><img src="./images/thank-you-icon.png" width="25" height="24"> Thank you!</div>
                    <div class="info-wrap">
                        <div class="accordion" id="infoExample">
                            <div class="accordion-item">
                                <h2 class="accordion-header d-flex justify-content-between">
                                    <button class="accordion-button one collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#infocollapse" aria-expanded="true" aria-controls="infocollapse">Order ID Info</button>
                                    <span class="infoID">#0542145</span>
                                </h2>
                                <div id="infocollapse" class="accordion-collapse collapse" data-bs-parent="#infoExample">
                                    <div class="accordion-body p-0">
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Date</h4>
                                            <h6>Wed, Aug 9, 2024</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Time</h4>
                                            <h6>11:06 AM</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="info-wrap">
                        <div class="accordion" id="infoOneExample">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button two collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#infoOnecollapse" aria-expanded="true" aria-controls="infoOnecollapse">Total Order Info</button>
                                </h2>
                                <div id="infoOnecollapse" class="accordion-collapse collapse" data-bs-parent="#infoOneExample">
                                    <div class="accordion-body p-0">
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>General Admission (3)</h4>
                                            <h6>$180.00</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Skywalk (2)</h4>
                                            <h6>$100.00</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Zipline (1)</h4>
                                            <h6>$95.00</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Subtotal</h4>
                                            <h6>$375.00</h6>
                                        </div>
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Fees</h4>
                                            <h6>$25.00</h6>
                                        </div>
                                        <div class="details other d-flex align-items-center justify-content-between">
                                            <h4>Total</h4>
                                            <h6>$400.00</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="info-wrap">
                        <div class="accordion" id="infothreeExample">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button three collapsed d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#infothreecollapse" aria-expanded="true" aria-controls="infothreecollapse">Payment Info</button>
                                </h2>
                                <div id="infothreecollapse" class="accordion-collapse collapse" data-bs-parent="#infothreeExample">
                                    <div class="accordion-body p-0">
                                        <div class="details d-flex align-items-center justify-content-between">
                                            <h4>Cash</h4>
                                            <h6>$500.00</h6>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="info-wrap">
                        <button><i class="fa fa-print" aria-hidden="true"></i>Print Invoice</button>
                    </div>
                    <div class="info-wrap">
                        <button class="home-btn"><i class="fa fa-angle-left" aria-hidden="true"></i>Home</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@section('css-custom')
<link href="{{ asset('css/pos.css') }}" rel="stylesheet">
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
<link href="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.css') }}" rel="stylesheet">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
@endif
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script src="{{ asset('plugins/select2-4.1.0-rc.0/select2.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
<script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
@else
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.17.0/jquery.validate.min.js"></script>
<script src="https://igorescobar.github.io/jQuery-Mask-Plugin/js/jquery.mask.min.js"></script>
@endif

<script src="https://js.stripe.com/v3/"></script>
<script src="https://cdnjs.cloudflare.com/polyfill/v3/polyfill.min.js?version=3.52.1&features=fetch"></script>
<script src="{{ asset('plugins/pusher.js') }}"></script>
<script src="{{ asset('plugins/card-reader.js') }}"></script>
<script src="{{ asset('js/utils/helpers.js') }}"></script>
@endsection

@section('js-custom')
<script>
    var countryCode = "{{ countryCode() }}";
    var userType = "{{ session('userType')  }}";
    var categoryId = "{{ $defaultCategoryId }}";
    var stripePublicKey = "{{ env('STRIPE_API_PUBLIC_KEY')}}";
    $('.hamburger-menu').on('click', function() {
        $('.hamburger-menu').toggleClass('open');
    });
    // document.addEventListener('DOMContentLoaded', async () => {
    //     const stripe = Stripe('<?= env('STRIPE_API_PUBLIC_KEY'); ?>');
    //     const elements = stripe.elements();
    //     const cardElement = elements.create('card',{hidePostalCode: true});
    //     cardElement.mount('#card-element');
    //  });
</script>
<script src="{{ asset('js/user-common/tour-booking-pos.js') }}"></script>

@endsection