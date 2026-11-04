<!-- <div class="form-group payment-condition-item col-12">
    <label for="pay-with-link" class="checkbox-wrap mb-2">
        <span id="K" class="amount-text total-after-discount-and-service-commission">{{ moneyFormat($cartTotal) }}</span> Pay with <span class="payment-method-text">Link</span>
        <input type="radio" name="payment_method" id="pay-with-link" value="Link" class="checkset book-pay-method" checked>
        <span class="checkmark"></span>
    </label>
</div> -->

<div class="form-group payment-condition-item mt-3">
    <label for="pay-with-link" class="block cursor-pointer">
        <!-- <input type="radio" name="payment_method" value="link" class="sr-only peer"> -->
        <input type="radio" name="payment_method" id="pay-with-link" value="Link" class="checkset book-pay-method sr-only peer" checked>
        <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
            <svg class="w-5 h-5 mr-3" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2188_2258)"><path d="M8.33301 10.8335C8.69088 11.3119 9.14747 11.7078 9.6718 11.9942C10.1961 12.2807 10.7759 12.4511 11.3719 12.4937C11.9678 12.5364 12.566 12.4504 13.1258 12.2416C13.6856 12.0328 14.1939 11.706 14.6163 11.2835L17.1163 8.78347C17.8753 7.99762 18.2953 6.94511 18.2858 5.85263C18.2763 4.76014 17.8381 3.71509 17.0656 2.94256C16.2931 2.17002 15.248 1.73182 14.1555 1.72233C13.063 1.71283 12.0105 2.13281 11.2247 2.8918L9.79134 4.3168" stroke="#60BEBD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.6668 9.16653C11.309 8.68809 10.8524 8.29221 10.328 8.00575C9.80371 7.71928 9.22391 7.54893 8.62796 7.50625C8.03201 7.46357 7.43384 7.54956 6.87405 7.75838C6.31425 7.9672 5.8059 8.29397 5.3835 8.71653L2.8835 11.2165C2.12451 12.0024 1.70453 13.0549 1.71402 14.1474C1.72352 15.2399 2.16172 16.2849 2.93426 17.0574C3.70679 17.83 4.75184 18.2682 5.84433 18.2777C6.93681 18.2872 7.98932 17.8672 8.77517 17.1082L10.2002 15.6832" stroke="#60BEBD" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_2188_2258"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>
            <span class="font-inter font-semibold text-black text-base flex-1">Pay with Link</span>
            <span id="payWithLinkAmount" class="font-inter font-bold text-[rgba(38,79,115,1)] text-base total-after-discount-and-service-commission">{{ moneyFormat($cartTotal) }}</span>
        </div>
    </label>
</div>