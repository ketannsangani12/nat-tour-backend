<div class="confirm-payment-wrap d-none" id="confirm-payment">
    <div class="back-btn text-end">
        <a href="javascript:void(0);" id="back-to-cart-step"><i class="fa fa-angle-left" aria-hidden="true"></i> Back</a>
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
                    <button class="accordion-button accordion-button-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#confirmpaymentcollapse" aria-expanded="true" aria-controls="confirmpaymentcollapse">Payment</button>
                </h2>
                <div id="confirmpaymentcollapse" class="accordion-collapse collapse show" data-bs-parent="#confirmPaymentTypes">
                    <div class="accordion-body p-0">
                        {{-- Scoped to #myTab specifically — style.css has no
                             existing rule for this tab bar at all (it was
                             fitting 2 short labels by accident, not by
                             design), and a bare selector here would risk
                             affecting unrelated .nav-tabs elsewhere on the
                             site. Bootstrap's .nav-tabs is flex-wrap:wrap by
                             default, which is what was pushing "Cash" onto
                             its own row once a 3rd, wider tab was added. --}}
                        <style>
                            #myTab { flex-wrap: nowrap; }
                            #myTab .nav-item { flex: 1 1 0; min-width: 0; }
                            #myTab .nav-link { padding-left: 6px; padding-right: 6px; font-size: 14px; white-space: nowrap; text-align: center; }
                        </style>
                        <ul class="nav nav-tabs mb-0 {{ (!Auth::check()) ? 'd-none' : ''}}" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link confirm-pay-method" id="Card" data-bs-toggle="tab" role="tab" data-bs-target="#cardInputs" type="button"><i class="fa fa-credit-card" aria-hidden="true"></i>Card</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="pos-card-reader-tab-btn" data-bs-toggle="tab" role="tab" data-bs-target="#posCardReaderPane" type="button"><i class="fa fa-wifi" aria-hidden="true"></i> Reader</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link confirm-pay-method" id="Cash" data-bs-toggle="tab" data-bs-target="#cashCalculator" role="tab" type="button"><i class="fa fa-money" aria-hidden="true"></i>Cash</button>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="cardInputs" role="tabpanel" aria-labelledby="Card" style="margin-bottom: 30px;">
                            <div class="payment-box text-start">    
                            <div class="card-detail-main">
                                    <div class="card-detail-wrp card-form-field">
                                        <!-- <div id="link-authentication-element"></div> -->
                                        <div id="payment-element"></div>
                                    </div>
                                </div>
                            </div>
                                <div class="row  d-none">
                                    <div class="col-12">
                                        <input type="text" class="form-control" id="cardnumber" name="cardnumber" maxlength="16" placeholder="Card number" required>
                                    </div>
                                    <div class="col-12">
                                        <input type="text" class="form-control" id="card-name" name="card-name" placeholder="Name on card" required>
                                    </div>
                                </div>
                                <div class="row  d-none">
                                    <div class="col-6 pe-1">
                                        <input type="text" class="form-control" id="expiration" name="expiration" maxlength="5" placeholder="MM/YY" required>
                                    </div>
                                    <div class="col-6 ps-1">
                                        <input type="text" class="form-control" id="cvv" name="cvv" maxlength="3" placeholder="CVV" required>
                                    </div>
                                </div>

                            </div>
                            {{-- Bootstrap-only markup, not the Tailwind-based
                                 _cardreader_btns partial used elsewhere — this
                                 page's layout (layouts.default-5) never loads
                                 css/app.css, so Tailwind/custom @apply classes
                                 have no effect here. See
                                 TourBookingPos.renderCardReaderState(). --}}
                            <div class="tab-pane fade" id="posCardReaderPane" role="tabpanel" aria-labelledby="pos-card-reader-tab-btn" style="margin-bottom: 30px;">
                                <div id="card-reader-identity-chip" class="mb-3 d-none"></div>
                                <div id="card-reader-generic-placeholder" class="text-center py-4">
                                    <i class="fa fa-credit-card" style="font-size:44px; opacity:.35;" aria-hidden="true"></i>
                                    <p class="mt-3 mb-0" style="font-weight:600;">Ready to accept card</p>
                                    <p class="text-muted mb-0" style="font-size:13px;">Tap or insert card on the reader</p>
                                </div>
                                <div id="card-reader-state-area" class="d-none mb-3 text-center"></div>
                                <div class="d-grid gap-2 mb-2">
                                    <button type="button" class="btn btn-primary connect-to-card-reader">Connect To Card Reader</button>
                                    <button type="button" style="display:none;" class="btn btn-outline-secondary btn-sm dis-connect-to-card-reader">Disconnect for today</button>
                                </div>
                                <div class="card-reader-status-output mt-2"></div>
                            </div>
                            <div class="tab-pane fade " id="cashCalculator" role="tabpanel" aria-labelledby="Cash">
                                <div class="calculator-wrap">
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
                                        <button class="calculator-keys" data-value="x"><svg style="max-width: 25px;" data-value="x" width="50" height="38" viewBox="0 0 70 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path data-value="x" d="M41.9593 47.9469C35.4249 47.9469 28.8906 47.8948 22.3563 47.9698C19.3172 48.0052 16.9696 46.9199 15.0324 44.5724C10.7582 39.3962 6.37762 34.3053 2.04708 29.177C-0.692055 25.9338 -0.679557 21.9969 2.06791 18.7475C6.50468 13.5025 10.954 8.26794 15.3699 3.0063C17.0321 1.02746 19.0985 0.0026298 21.6855 0.00471279C35.1708 0.00887877 48.654 -0.0161171 62.1393 0.0192937C66.8427 0.0317917 69.9984 3.33125 69.9984 8.05964C70.0005 18.6537 70.0005 29.2499 69.9984 39.844C69.9984 44.687 66.7948 47.9281 61.9498 47.9406C55.2884 47.9614 48.6228 47.9469 41.9593 47.9469Z" fill="black" />
                                                <path data-value="x" d="M32.577 13.315C33.3623 13.2879 33.9768 13.6191 34.5163 14.1628C36.4431 16.1 38.3927 18.0163 40.3049 19.9702C40.6882 20.3618 40.8819 20.3618 41.2652 19.9702C43.1565 18.0351 45.0812 16.1291 46.9997 14.219C48.1578 13.065 49.7055 12.9921 50.7386 14.0295C51.7718 15.0647 51.6947 16.6061 50.5387 17.7705C48.6307 19.691 46.7247 21.6136 44.7875 23.505C44.3855 23.8987 44.4209 24.0924 44.8021 24.4653C46.7185 26.342 48.6057 28.2459 50.497 30.1456C51.6614 31.3162 51.7656 32.8556 50.7657 33.8887C49.7326 34.9552 48.1495 34.8823 46.9601 33.6971C45.1146 31.8599 43.2461 30.0456 41.4547 28.1584C40.8819 27.5543 40.6111 27.6606 40.0987 28.1917C38.2532 30.0998 36.3535 31.9557 34.4767 33.8346C33.7019 34.6115 32.7853 34.8448 31.7626 34.4824C30.0212 33.8658 29.5734 31.7016 30.9106 30.3435C32.8457 28.3771 34.7954 26.4233 36.768 24.4944C37.17 24.1007 37.2263 23.9029 36.7868 23.4758C34.8079 21.5532 32.8728 19.5869 30.9211 17.6351C30.117 16.8311 29.9254 15.8729 30.3378 14.8418C30.7273 13.8566 31.5105 13.3504 32.577 13.315Z" fill="#FEFEFE" />
                                            </svg></button>
                                        <button class="calculator-keys" data-value=".">.</button>
                                        <button class="calculator-keys" data-value="clear">Clear</button>
                                    </div>
                                </div>
                            </div>



                            <div class="confirm-pay-btn-wrap">
                                <button class="confirm-btn" id="confirm-payment-button">Confirm Payment</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>