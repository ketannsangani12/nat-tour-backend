<div class="bottom-box">
    <div class="add-wrap">
        <div class="payment-wrap">
            <div class="payment-details d-flex align-items-center justify-content-between">
                <h6>Ticket Cost</h6>
                <p id="pos-ticketcost">{{ moneyFormat(0.00) }}</p>
            </div>
            <div class="payment-details d-flex align-items-center justify-content-between">
                <h6>Discount</h6>
                <p id="pos-discounttotal">{{ moneyFormat(0.00) }}</p>
            </div>
            <div class="payment-details d-flex align-items-center justify-content-between">
                <h6>Subtotal</h6>
                <p id="pos-subtotal">{{ moneyFormat(0.00) }}</p>
            </div>
            <div class="payment-details d-flex align-items-center justify-content-between">
                <h6>Fees</h6>
                <p id="pos-fees">{{ moneyFormat(0.00) }}</p>
            </div>
            <div class="payment-details d-flex align-items-center justify-content-between">
                <h6>Total</h6>
                <p id="pos-final-total">{{ moneyFormat(0.00) }}</p>
            </div>
        </div>
    </div>
    <div class="payment-btn-wrap {{ (!Auth::check()) ? 'justify-content-end' : ''}}">
        <ul class="nav {{ (!Auth::check()) ? 'd-none' : ''}}">
            <li class="nav-item" role="presentation">
                <button class="nav-link book-pay-method active" id="Card" data-bs-toggle="tab"><i class="fa fa-credit-card" aria-hidden="true"></i>Card</button>
            </li>
            <li class="nav-item ">
                <button class="nav-link book-pay-method" id="Cash" data-bs-toggle="tab"><i class="fa fa-money" aria-hidden="true"></i>Cash</button>
            </li>
        </ul>
        <div class="d-flex popup-btn-wrap">
            <button type="button" class="popup-btn me-2" id="add-info" data-bs-toggle="modal" data-bs-target="#addInfoModal"><svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18.0291 15.5041C18.0291 16.5888 18.0359 17.6737 18.0253 18.7584C18.0217 19.1274 18.0195 19.5063 17.9383 19.8628C17.6641 21.0674 16.5691 21.9411 15.3302 21.9436C11.4228 21.9517 7.5153 21.9531 3.60788 21.9434C2.08533 21.9396 0.896984 20.6939 0.896127 19.1312C0.893726 14.8165 0.895212 10.5018 0.895212 6.18709C0.895212 5.37988 0.89344 4.57262 0.895612 3.76542C0.899957 2.15341 2.10465 0.950546 3.71357 0.950432C7.57823 0.950146 11.4429 0.950089 15.3075 0.950432C16.9092 0.950603 18.113 2.15038 18.1153 3.74804C18.1161 4.31237 18.112 4.87677 18.1181 5.44104C18.1195 5.56433 18.0835 5.62761 17.9672 5.68134C17.4963 5.89889 17.0839 6.20183 16.7172 6.56937C15.4002 7.88912 14.1033 9.22997 12.7552 10.5173C11.7885 11.4403 11.1634 12.5107 10.9529 13.8328C10.8656 14.3804 10.7086 14.9166 10.6096 15.4629C10.4272 16.4684 11.3243 17.8891 12.8129 17.7573C13.4492 17.701 14.0792 17.5579 14.7075 17.4311C15.8274 17.205 16.8008 16.6977 17.5942 15.8651C17.7206 15.7325 17.8393 15.5923 17.9615 15.4556C17.9841 15.4718 18.0066 15.4879 18.0291 15.5041ZM9.41733 5.78548C10.5099 5.78548 11.6025 5.78554 12.6951 5.78543C13.0093 5.78543 13.3237 5.79292 13.6376 5.78303C14.176 5.76605 14.5145 5.42064 14.5129 4.90397C14.5113 4.37948 14.157 4.03567 13.6108 4.03161C13.3037 4.02932 12.9966 4.03109 12.6896 4.03109C10.2188 4.03109 7.74793 4.03024 5.27711 4.03166C4.61206 4.03212 4.15661 4.67699 4.40114 5.27322C4.56199 5.66534 4.89014 5.78526 5.28259 5.78537C6.66088 5.78577 8.0391 5.78548 9.41733 5.78548ZM7.70083 7.63162C6.87242 7.63162 6.04396 7.62825 5.21555 7.63299C4.75907 7.63562 4.39399 7.95971 4.34598 8.38892C4.27916 8.98647 4.63361 9.37904 5.26087 9.38475C6.00354 9.39155 6.74633 9.38653 7.48906 9.38653C8.34605 9.38653 9.20304 9.38933 10.06 9.38527C10.5435 9.38298 10.8576 9.166 10.96 8.77955C11.1312 8.13302 10.7503 7.63419 10.079 7.63202C9.28626 7.62945 8.49352 7.63139 7.70083 7.63162Z" fill="#2F9DD5" />
                    <path d="M21.105 9.0334C21.0878 9.60328 20.8876 10.042 20.515 10.413C19.0981 11.8236 17.6864 13.2395 16.2718 14.6525C16.2013 14.7229 16.1216 14.784 16.0462 14.8494C15.4695 15.3501 14.7649 15.541 14.039 15.6863C13.6203 15.77 13.2049 15.8709 12.7881 15.9647C12.4569 16.0393 12.2485 15.8292 12.3256 15.4954C12.4841 14.8087 12.644 14.1223 12.8034 13.4358C12.908 12.9849 13.1396 12.6033 13.4619 12.2796C14.9533 10.7818 16.4447 9.2841 17.9452 7.7955C18.4889 7.25614 19.332 7.1181 20.0319 7.43677C20.7312 7.75526 21.0627 8.32577 21.105 9.0334Z" fill="#2F9DD5" />
                </svg> Add Info</button>
        </div>
    </div>
    <div class="pay-btn-wrap">
        <button class="hold" id="hold-order"><i class="fa fa-pause-circle" aria-hidden="true"></i> Hold</button>
        <button class="final-pay" id="confirm-pay-button"><span class="price" id="final-total">{{ moneyFormat(0.00) }}</span> <span class="pay">Pay</span></button>
    </div>
</div>