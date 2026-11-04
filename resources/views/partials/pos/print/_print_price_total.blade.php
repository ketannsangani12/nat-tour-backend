<table class="table table-sm table-bordered table-hover mt-3 mb-0 tbl--price" style="{{$style}}">
    <tbody>
        @if($bookingM->discount2_value != 0)
        <tr>
            <th class="text-end">Discount</th>
            <td class="td-amount">-
                @php
                if($bookingM->discount2_percentage != 0) {
                echo '(' . str_replace('.00', '', $bookingM->discount2_percentage) . '%) ';
                }
                echo moneyFormat($bookingM->discount2_value);
                @endphp
            </td>
        </tr>
        @endif
        <tr>
            <th class="text-end">Subtotal</th>
            <td class="td-amount"> {{ moneyFormat(sprintf('%.2f', $bookingM->total - $bookingM->service_commission)) }}</td>
        </tr>
        @if($customChargePrice['custom_charge_total'] > 0)
        <tr>
            <th class="text-end">Custom Charge</th>
            <td class="td-amount"> {{ moneyFormat($customChargePrice['custom_charge_total']) }}</td>
        </tr>
        @endif
        @if(isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
        <tr>
            <th class="text-end">Add-ons</th>
            <td class="td-amount"> {{ moneyFormat($payLaterAddons['addons_price']) }}</td>
        </tr>
        @elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0)
        <tr>
            <th class="text-end">Add-ons</th>
            <td class="td-amount"> {{ moneyFormat($pricedAddons['addons_final_price']) }}</td>
        </tr>
        @endif
        <tr>
            <th class="text-end">Booking Fees</th>
            @if (isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0)
            <td class="td-amount"> {{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee']) }}</td>
            @else
            <td class="td-amount">{{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees']) }}</td>
            @endif
        </tr>
        <tr>
            <th class="text-end">Booking Total</th>
            @if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
            <td class="td-amount">{{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price']) }}</td>
            @elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0)
            {{-- comboBooking['bookingTotal'] is the gross, never-decremented-by-refund figure, so
            add-ons must use the matching gross figure here too — not addons_payment_total, which
            nets out any add-on refund and would understate this line by exactly that amount. --}}
            <td class="td-amount">{{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total']) }}</td>
            @else
            <td class="td-amount">{{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge']) }}</td>
            @endif
        </tr>
        @if($ageGroup)
        <tr>
            <th class="text-end">Refunded Pax</th>
            <td class="td-amount"> {!! $ageGroup !!}</td>
        </tr>
        @endif
        @if($refundKeepUs)
        <tr>
            <th class="text-end">Non-Refundable Fees</th>
            <td class="td-amount"> {{ moneyFormat($refundKeepUs) }}</td>
        </tr>
        @endif
        @if($refundAmount)
        <tr>
            <th class="text-end">Refund Amount</th>
            <td class="td-amount">{{ moneyFormat($refundAmount) }}</td>
        </tr>
        @endif
        <tr>
            <th class="text-end">Payment Total</th>
            <td class="td-amount"> {{ moneyFormat($paymentTotal) }}</td>
        </tr>
    </tbody>
</table>