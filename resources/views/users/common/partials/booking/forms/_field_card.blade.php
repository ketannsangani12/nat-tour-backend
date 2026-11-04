<!-- <div class="form-group payment-condition-item col-12 mt-2">
    <label for="pay-with-card" class="checkbox-wrap checked-item mb-0">
        <span id="K" class="amount-text total-after-discount-and-service-commission">{{ moneyFormat($cartTotal) }}</span> Pay with <span class="payment-method-text">Card</span>
        <input type="radio" name="payment_method" id="pay-with-card" value="Card" class="checkset book-pay-method" checked>
        <span class="checkmark"></span>
    </label>
</div> -->

<!-- Pay with Card (Default Selected) -->
<div class="form-group payment-condition-item mt-3">
<label for="pay-with-card" class="block cursor-pointer">
    <input type="radio" name="payment_method" id="pay-with-card" value="Card" class="checkset book-pay-method sr-only peer" checked>
    <div class="w-full h-12 bg-[#264f731a] border border-[#264f73] rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-[#264f7320] transition-colors">
        <svg class="w-5 h-5 mr-3" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16.6699 4.16992H3.33659C2.41611 4.16992 1.66992 4.91611 1.66992 5.83659V14.1699C1.66992 15.0904 2.41611 15.8366 3.33659 15.8366H16.6699C17.5904 15.8366 18.3366 15.0904 18.3366 14.1699V5.83659C18.3366 4.91611 17.5904 4.16992 16.6699 4.16992Z" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M1.66992 8.33008H18.3366" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="font-inter font-semibold text-black text-base flex-1">Pay with Card</span>
        <span id="K" class="amount-text total-after-discount-and-service-commission font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat($cartTotal) }}</span>
    </div>
</label>
</div>