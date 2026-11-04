<div id="thank-you-step" class="thank-you-wrap d-none">
    <div class="back-btn text-end">
        <a href="javascript:void(0);" id="back-to-home-btn"><i class="fa fa-angle-left" aria-hidden="true"></i> Home</a>
    </div>
    <div class="text-ty d-flex align-items-center justify-content-center"><img src="{{ asset('images/thank-you-icon.png') }}" width="25" height="24"> Thank you!</div>
    <div class="info-wrap">
        <div class="accordion" id="orderInfo">
            <div class="accordion-item">
                <h2 class="accordion-header d-flex justify-content-between">
                    <button class="accordion-button one d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderInfocollapse" aria-expanded="true" aria-controls="orderInfocollapse">Order ID Info</button>
                    <span class="infoID" id="orderId"></span>
                </h2>
                <div id="orderInfocollapse" class="accordion-collapse" data-bs-parent="#orderInfo">
                    <div class="accordion-body p-0">
                        <div class="details d-flex align-items-center justify-content-between">
                            <h4>Date</h4>
                            <h6 id="orderDate"></h6>
                        </div>
                        <div class="details d-flex align-items-center justify-content-between">
                            <h4>Time</h4>
                            <h6 id="orderTime"></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderPackagesInfo">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button two d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderPackagesInfocollapse" aria-expanded="true" aria-controls="orderPackagesInfocollapse">Total Order Info</button>
                </h2>
                <div id="orderPackagesInfocollapse" class="accordion-collapse" data-bs-parent="#orderPackagesInfo">
                    <div class="accordion-body p-0" id="orderPackages">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderContactInfoDetails">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button three d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderContactDetailscollapse" aria-expanded="true" aria-controls="orderContactDetailscollapse">Contact Info</button>
                </h2>
                <div id="orderContactDetailscollapse" class="accordion-collapse" data-bs-parent="#orderContactInfoDetails">
                    <div class="accordion-body p-0" id="orderContactDet">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderPaymentTypeInfo">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button three d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderPaymentTypecollapse" aria-expanded="true" aria-controls="orderPaymentTypecollapse">Payment Info</button>
                </h2>
                <div id="orderPaymentTypecollapse" class="accordion-collapse" data-bs-parent="#orderPaymentTypeInfo">
                    <div class="accordion-body p-0" id="orderPaymentType">

                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="info-wrap">
        <div class="accordion" id="orderDigitalCopy">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button three d-flex align-items-center border-0 bg-white p-0" type="button" data-bs-toggle="collapse" data-bs-target="#orderDigitalCopycollapse" aria-expanded="true" aria-controls="orderPaymentcollapse">Digital Copy</button>
                </h2>
                <div id="orderDigitalCopycollapse" class="accordion-collapse" data-bs-parent="#orderDigitalCopy">
                    <div class="accordion-body p-0" id="orderSendReceipt">
                        <div class="input-group align-items-center">
                            <input type="email" class="form-control input-no-shadow email-receipt" name="email" placeholder="Email Address" required>
                            <div class="input-group-append">
                                <button class="signupbtn send-receipt" id="send-order-receipt">Send</button>
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
    <div class="info-wrap home">
        <button class="home-btn" id="home-btn"><i class="fa fa-angle-left" aria-hidden="true"></i>Home</button>
    </div>
</div>
