<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="tipModal" tabindex="-1" aria-labelledby="tipModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden tip-modal-content">
            <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                @if($page == 'booking-info')
                <form id="tips-payment-form" method="post" action="{{ route(prefixedRouteName($routePrefix.'booking.tip-payment')) }}" onsubmit="return validateAndSubmitForm3(this)" data-call-on-success="Tip payment successfully done">
                    @csrf
                    @else
                    <form id="sendTipForm" action="#">
                        @endif
                        <div class="text-center mb-4">
                            <div class="heart-icon mb-3">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h2 class="tip-title mb-3">Leave a {{ ($page == 'booking-info') ? 'Post-Tip' : 'Pre-Tipe' }} for Your Guide</h2>
                            <p class="tip-subtitle mb-4">Show your appreciation in advance! Your tip will be passed to your guide before your tour.</p>
                        </div>

                        <div class="tip-form">
                            <div class="mb-4" id="tipAmountSection">
                                <label class="form-label tip-label">Choose an amount</label>
                                <div class="row g-3" id="tipAmounts">
                                    @if($page == 'booking-info' && $gratuitySetting)
                                    @php
                                    $gratuityRules = json_decode($gratuitySetting->gratuity_rules);
                                    @endphp
                                    @if(!empty($gratuityRules) && is_array($gratuityRules))
                                    @foreach($gratuityRules as $rule)
                                    @if($gratuitySetting->gratuity_type == 'fixed_amount')
                                    <div class="col-6 col-md-3"><button type="button" class="btn amount-btn w-100 " data-amount="{{ $rule->amount }}">${{ $rule->amount }}</button></div>
                                    @elseif($gratuitySetting->gratuity_type == 'fixed_percentage')
                                    <div class="col-6 col-md-3"><button type="button" class="btn amount-btn w-100 " data-amount="{{ $rule->percentage }}">{{ $rule->percentage }}%</button></div>
                                    @endif
                                    @endforeach
                                    @endif
                                    @endif
                                </div>
                            </div>
                            <div class="mb-4 {{ ($page == 'booking-info' && $gratuitySetting && $gratuitySetting->gratuity_type == 'fixed_amount') ? 'd-none' : ''}}" id="tipCustomPercentageSection">
                                <label class="form-label tip-label">Or custom percentage</label>
                                <div id="tip-percentage-slider" class="custom-slider">
                                </div>

                                <p class="mt-3">
                                    <span class="badge span-percentage"><span id="percentage-value">0</span></span>
                                    <input type="hidden" id="tipPercentage" name="tip_percentage" value="0">
                                </p>
                            </div>
                            <div class="mb-4" id="tipCustomAmountSection">
                                <label class="form-label tip-label">Or enter custom amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control custom-amount" name="custom_amount" id="customAmount" placeholder="0" min="0" step="1">
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label tip-label">
                                    <i class="fas fa-comment-dots me-2"></i>
                                    Optional message for your guide
                                </label>
                                <textarea class="form-control message-textarea" id="message" name="message" rows="4" placeholder="Thank you for making our desert adventure special!"></textarea>
                            </div>

                            <div class="mb-3">
                                @if($page == 'booking-info' && $gratuitySetting)
                                <input type="hidden" name="booking_id" value="{{ $bookingM->id }}">
                                <input type="hidden" name="tip_amount" id="tipAmount" value="0">
                                <input type="hidden" name="tip_fees" id="tipFees" value="0">
                                <input type="hidden" name="tip_setting_id" id="tipSettingId" value="{{ $gratuitySetting->gratuity_setting_id }}">
                                <button id="sendTipBtn" type="submit" class="btn btn-main btn-main-sm themed-btn short-btn send-tip-btn">
                                    <span class="btn-text">Send Tip - $0</span>
                                </button>
                                @else
                                <button id="sendTipBtn" type="button" class="btn btn-main btn-main-sm themed-btn short-btn send-tip-btn">
                                    <span class="btn-text">Add Tip - $0</span>
                                </button>
                                @endif

                            </div>

                            <div class="text-center">
                                <p class="disclaimer-text">Your tip will be securely processed and delivered to your guide before your tour begins.</p>
                            </div>
                        </div>
                    </form>
            </div>
        </div>
    </div>
</div>