<!-- Pay with Cash -->
 <div class="form-group payment-condition-item mt-3" >
    <label for="pay-with-cash" class="block cursor-pointer">
        <input type="radio" name="payment_method" id="pay-with-cash" class="checkset book-pay-method sr-only peer" value="Cash" {{ (!checkPermission('payment-method-card')) ? 'checked' : '' }}>
        <div class="w-full h-12 bg-white border border-gray-300 rounded-md flex items-center px-4 peer-checked:bg-[#264f731a] peer-checked:border-[#264f73] hover:bg-gray-50 transition-colors">
            <svg class="w-5 h-5 mr-3 min-w-5" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><g clip-path="url(#clip0_2188_2274)"><path d="M10.0003 18.3332C14.6027 18.3332 18.3337 14.6022 18.3337 9.99984C18.3337 5.39746 14.6027 1.6665 10.0003 1.6665C5.39795 1.6665 1.66699 5.39746 1.66699 9.99984C1.66699 14.6022 5.39795 18.3332 10.0003 18.3332Z" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.3337 6.6665H8.33366C7.89163 6.6665 7.46771 6.8421 7.15515 7.15466C6.84259 7.46722 6.66699 7.89114 6.66699 8.33317C6.66699 8.7752 6.84259 9.19912 7.15515 9.51168C7.46771 9.82424 7.89163 9.99984 8.33366 9.99984H11.667C12.109 9.99984 12.5329 10.1754 12.8455 10.488C13.1581 10.8006 13.3337 11.2245 13.3337 11.6665C13.3337 12.1085 13.1581 12.5325 12.8455 12.845C12.5329 13.1576 12.109 13.3332 11.667 13.3332H6.66699" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 15V5" stroke="#60BEBD" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></g><defs><clipPath id="clip0_2188_2274"><rect width="20" height="20" fill="white"/></clipPath></defs></svg>
            <span class="font-inter font-semibold text-black text-base flex-1 whitespace-nowrap">Pay with Cash</span>
            <!-- <span class="font-inter font-bold text-[rgba(38,79,115,1)] text-base">$180.81</span> -->
            <span id="M" class="amount-text cash-set font-inter pl-2 font-bold text-[rgba(38,79,115,1)] text-base whitespace-nowrap">
            @php
                $ticketCost = $cashPriceEnabled ? $cartCashTicketCost : $cartTicketCost;
                $totalAmount = $cashPriceEnabled ? $cartCashTotal : $cartTotal;
                $discountCart = $cashPriceEnabled ? $cartDiscountCash : $cartDiscount;

                if ($cashFees == 1) {
                    $cashAmountDisplay = $totalAmount;
                } else {
                    $cashAmountDisplay = ($ticketCost - $discountCart) + $cartAddonsPrice + $cartCustomChargeAmount + $cartTipAmount;
                }
            @endphp   
            {{ moneyFormat($cashAmountDisplay) }}</span>
            {{--<input type="number" name="cash_paid_amount" id="cash-paid-amount" class="form-control ml-2 form-custom-input" placeholder="Paid amount" min="1" step="0.01" readonly {{ ($cartView==1) ? 'hidden' : '' }}>--}}
        </div>
    </label>
    <div class="mt-2">
        <input type="number" name="cash_paid_amount" id="cash-paid-amount" class="form-control form-custom-input border !border-[#ED9C45] font-semibold placeholder:font-semibold placeholder:text-[#020817] h-[40px] d-none" placeholder="Paid amount" min="1" step="0.01" readonly>
    </div>
</div>