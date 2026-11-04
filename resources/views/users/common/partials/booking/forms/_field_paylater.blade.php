
<!-- Pay Later -->
<div class="form-group payment-condition-item mt-3" >
    <label class="block cursor-pointer">
        <input type="radio" name="payment_method" id="pay-with-later" class="checkset book-pay-method sr-only peer" value="Pay Later">
        <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
            <svg class="w-5 h-5 mr-3" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9.99967 17.4998C13.6816 17.4998 16.6663 14.5151 16.6663 10.8332C16.6663 7.15127 13.6816 4.1665 9.99967 4.1665C6.31778 4.1665 3.33301 7.15127 3.33301 10.8332C3.33301 14.5151 6.31778 17.4998 9.99967 17.4998Z" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 7.5V10.8333L11.6667 12.5" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.16699 2.5L1.66699 5" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M18.333 5L15.833 2.5" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.31634 15.5835L3.33301 17.5002" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.7002 15.5581L16.6669 17.4998" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="font-inter font-semibold text-black text-base flex-1">Pay Later</span>
            <span id="pay_later" class="amount-text cash-set pay-later font-inter font-bold text-[rgba(38,79,115,1)] text-base">{{ moneyFormat(($cartTicketCost - $cartDiscount) + $cartAddonsPrice + $cartCustomChargeAmount + $cartTipAmount) }}</span>
        </div>
    </label>
</div>