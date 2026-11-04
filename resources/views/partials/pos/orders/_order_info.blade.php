<div class="alert alert-primary {{ (Auth::check()) ? 'd-none' : ''}} order-info-sec-title" role="alert">
Order will be visible here when you scan the QR code.
</div>
<div id="order-info" class="thank-you-wrap d-none">
    <div class="text-ty d-flex align-items-center justify-content-center"> Order Details</div>

    <div class="info-wrap">
        <div class="accordion" id="orderDetails">
            <div class="accordion-item">
                <h2 class="accordion-header d-flex justify-content-between">
                    <button class="accordion-button one d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderDetailscollapse" aria-expanded="true" aria-controls="orderDetailscollapse">Order ID Info</button>
                    <span class="infoID" id="orderInfoId"></span>
                </h2>
                <div id="orderDetailscollapse" class="accordion-collapse" data-bs-parent="#orderDetails">
                    <div class="accordion-body p-0">
                        <div class="details d-flex align-items-center justify-content-between">
                            <h4>Date</h4>
                            <h6 id="orderInfoDate"></h6>
                        </div>
                        <div class="details d-flex align-items-center justify-content-between">
                            <h4>Time</h4>
                            <h6 id="orderInfoTime"></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderBookingsInfo">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button two d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderBookingsInfocollapse" aria-expanded="true" aria-controls="orderBookingsInfocollapse">Total Order Info</button>
                </h2>
                <div id="orderBookingsInfocollapse" class="accordion-collapse" data-bs-parent="#orderBookingsInfo">
                    <div class="accordion-body p-0" id="orderBookings">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderContactInfo">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button three d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderContactcollapse" aria-expanded="true" aria-controls="orderContactcollapse">Contact Info</button>
                </h2>
                <div id="orderContactcollapse" class="accordion-collapse" data-bs-parent="#orderContactInfo">
                    <div class="accordion-body p-0" id="orderContactDetails">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderPaymentInfo">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button three d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderPaymentcollapse" aria-expanded="true" aria-controls="orderPaymentcollapse">Payment Info</button>
                </h2>
                <div id="orderPaymentcollapse" class="accordion-collapse" data-bs-parent="#orderPaymentInfo">
                    <div class="accordion-body p-0" id="orderPaymentDetails">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="digitalCopy">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button three d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#digitalCopycollapse" aria-expanded="true" aria-controls="orderPaymentcollapse">Digital Copy</button>
                </h2>
                <div id="digitalCopycollapse" class="accordion-collapse" data-bs-parent="#digitalCopy">
                    <div class="accordion-body p-0" id="orderInfoSendReceipt">

                        <div class="input-group align-items-center">
                            <input type="email" class="form-control input-no-shadow email-receipt" name="email" id="email" placeholder="Email Address" required>
                            <div class="input-group-append">
                                <button class="signupbtn send-receipt" id="send-receipt">Send</button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <button class="printReceipt"><i class="fa fa-print" aria-hidden="true"></i>Print Receipt</button>
    </div>
    <div class="info-wrap">
    @if(isset($managerRequestEnable['POS Cancel Order']) && $managerRequestEnable['POS Cancel Order'] == 'Yes')
        <button class="cancel-btn" id="cancel-btn"><i class="fa fa-times" aria-hidden="true"></i>Cancel Order</button>
    @endif
    @if(isset($managerRequestEnable['POS Cancel Order']) && $managerRequestEnable['POS Refund Order'] == 'Yes')    
        <button class="refund-btn" id="refund-btn"><i class="fa fa-usd" aria-hidden="true"></i>Refund</button>
    </div>
    @endif
</div>