<table style="padding:0; margin:0 auto; max-width:600px;" width="600" cellspacing="0" cellpadding="0" bgcolor="#f5f5f5" align="center">
    <tbody>
        <tr>
            <td style="padding:17px 20px;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f5f5f5" align="center">
                    <tbody>
                        <tr>
                            <td style="font-size:15px; font-weight:400; line-height:1.5; color:#000000; font-family:'Open Sans', sans-serif; padding:0; margin:0;">
                                @if($order['logo'])
                                <img src="{{ $order['logo'] }}" alt="Dixie's Lower Antelope Canyon Tours" width="112" height="100">
                                @endif
                            </td>
                            <td style="font-size:20px; font-weight:800; line-height:1.25; color:#000000; font-family:'Open Sans', sans-serif; padding:0 165px; margin:0; text-align:center;">
                                {{ $order['tourOperator'] }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 11px 20px; background-color: #ffffff; border-bottom: 2px solid #f5f5f5;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" align="center">
                    <tbody>
                        <tr>
                            <td style="font-size: 13px;font-weight: 400;line-height: 1.25;color: #000000;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align: left;">
                                <a href="tel:9286401761" style="white-space: nowrap; color:#000000; text-decoration:none;"><img src="{{ asset('images/phone-icon.png') }}" alt="Phone Icon" width="10" height="10" style="margin-right: 5px;">{{ $order['tourOpPhone'] }} </a>
                            </td>
                            <td style="font-size: 13px;font-weight: 400;line-height: 1.25;color: #000000;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align: center;">
                                <a href="mailto:info@nativeamericantours.com" style="white-space: nowrap; color:#000000; text-decoration:none;"><img src="{{ asset('images/mail-icon.png') }}" alt="Mail Icon" width="10" height="10" style="margin-right: 5px;"> {{ $order['tourOpEmail'] }}</a>
                            </td>
                            <td style="font-size: 13px;font-weight: 400;line-height: 1.25;color: #000000;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align: right;">
                                <a href="www.antelopecanyon.com" style="white-space: nowrap; color: #000000; text-decoration:none;"><img src="{{ asset('images/web-icon.png') }}" alt="Web Icon" width="11" height="10" style="margin-right: 5px;">{{ $order['tourOpWebsite'] }}</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        
        <tr>
            <td style="padding: 15px 0px 20px; background: #FFFFFF;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
                    <tbody>
                        <tr>
                            <td style="font-size: 36px;font-weight: 700;line-height: 1.25;color: #0187CC;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                Hey,
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 16px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                Please find attached digital copy of your order.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding:17px 20px;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f5f5f5" align="center">
                    <tbody>
                        <tr>
                            <td>
                                <img src="{{ $order['qrCode'] }}" alt="QR Code" width="157" height="154">
                            </td>
                            <td style="text-align: right;">

                                <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
                                    <tbody>
                                        <tr>
                                            <td style="text-align:right; padding-bottom: 10px;">
                                                @if($order['logo'])
                                                <img src="{{ $order['logo'] }}" alt="Rambo Main Logo" width="112" height="100">
                                            </td>
                                            @endif
                                        </tr>
                                        <tr>
                                            <td style="font-size: 14px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0; padding-bottom: 10px;margin: 0;text-align:right; ">
                                                {{ $order['tourOperator'] }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 14px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:right;">
                                                <a href="tel:9286401761" style="white-space: nowrap; color:#000000; text-decoration:none;">{{ $order['tourOpPhone'] }}</a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 14px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:right;">
                                                <a href="mailto:johnj@ramboadventures.com" style="white-space: nowrap; color: #000000; text-decoration:none;">{{ $order['tourOpEmail'] }}</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        @php
        $customChargeTotal = 0;
        $addOnTotal = 0;
        $feeTotal = 0;
        $bookingTotal = 0;
        $comboBookings = $order['comboBookings'];
        @endphp
        @foreach($comboBookings as $comboBooking)
        @php
        $bookingM = $comboBooking['bookingM'];
        $bookingT = $comboBooking['bookingT'];
        $customChargePrice = $comboBooking['customChargePrice'];
        $pricedAddons = $comboBooking['pricedAddons'];
        $payLaterAddons = $comboBooking['payLaterAddons'];

        if ($customChargePrice['custom_charge_total'] > 0){
        $customChargeTotal += $customChargePrice['custom_charge_total'];
        }

        if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0){
        $addOnTotal += $payLaterAddons['addons_price'];
        } elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0){
        $addOnTotal += $pricedAddons['addons_final_price'];
        }

        if (isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0){
        $feeTotal += $bookingM->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee'];
        } else {
        $feeTotal += $bookingM->service_commission + $customChargePrice['custom_charge_fees'];
        }

        if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0){
        $bookingTotal += $comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price'];
        } elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0){
        // comboBooking['bookingTotal'] is the gross, never-decremented-by-refund figure, so
        // add-ons must use the matching gross figure here too — not addons_payment_total, which
        // nets out any add-on refund and would understate this line by exactly that amount.
        $bookingTotal += $comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total'];
        } else {
        $bookingTotal += $comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'];
        }


        @endphp
        <tr>
            <td style="padding: 0px 0px 10px; background: #FFFFFF;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center">
                    <tbody>
                        <tr>
                            <td style="font-size: 14px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                Booking #{{ bookingPrefix($bookingM->id) }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 14px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                {{ $bookingM->custName }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 14px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                <a href="mailto:ketan@glorywebs.com" style="white-space: nowrap; color: #000000; text-decoration:none;">{!! $order['custPhoneEmail'] !!}</a>
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 14px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                {{ $bookingM->tour_package }}
                            </td>
                        </tr>
                        <tr>
                            <td style="font-size: 14px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left;">
                                {{ formatDateTime($bookingM->date . ' ' . $bookingM->time_from) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <td style="padding: 0px 0px 20px; background: #FFFFFF;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center" style="border: 1px solid #d5d5d5;">
                    <tbody>
                        <tr>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Age Group</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Persons</th>
                            @if($bookingM->is_group_booking != '1')
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Rate</th>
                            @if($bookingM->package_has_permit)
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Permit Fee</th>
                            @endif
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">{{ taxLabel() }}</th>
                            @endif
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">Total</th>
                        </tr>
                        @foreach($bookingT as $bt)
                        <tr>
                            <td style="font-size: 12px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">{{ ($bookingM->is_group_booking == '1') ? 'Person(s)' : $bt->rate_for }}</td>
                            <td style="font-size: 12px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">{{ $bt->tourists }}</td>
                            @if($bookingM->is_group_booking != '1')
                            <td style="font-size: 12px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($bt->rate) }}</td>
                            @if($bookingM->package_has_permit)
                            <td style="font-size: 12px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($bt->permit_fee) }}</td>
                            @endif
                            <td style="font-size: 12px;font-weight: 400;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($bt->additional_charge) }}</td>
                            @endif
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($bt->total) }}</td>
                        </tr>
                        @endforeach

                        @if($bookingM->discount2_value != 0)
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Discount</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">
                                -
                                @if($bookingM->discount2_percentage != 0)
                                ({{ str_replace('.00', '', $bookingM->discount2_percentage) }} %)
                                @endif

                                ${{ $bookingM->discount2_value }}
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Subtotal</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat(sprintf('%.2f', $bookingM->total - $bookingM->service_commission)) }}</td>
                        </tr>
                        @if($customChargePrice['custom_charge_total'] > 0)
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Custom Charge</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($customChargePrice['custom_charge_total']) }}</td>
                        </tr>
                        @endif
                        @if(isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Add-ons</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($payLaterAddons['addons_price']) }}</td>
                        </tr>

                        @elseif (isset($pricedAddons['addons_final_price']) && $pricedAddons['addons_final_price'] > 0)
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Add-ons</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($pricedAddons['addons_final_price']) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Booking Fees</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">
                                @if (isset($pricedAddons['addons_final_fee']) && $pricedAddons['addons_final_fee'] > 0)
                                {{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees'] + $pricedAddons['addons_final_fee']) }}
                                @else
                                {{ moneyFormat($bookingM->service_commission + $customChargePrice['custom_charge_fees']) }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Booking Total</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">
                                @if (isset($payLaterAddons['addons_price']) && $payLaterAddons['addons_price'] > 0)
                                {{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $payLaterAddons['addons_price']) }}
                                @elseif (isset($pricedAddons['addons_gross_total']) && $pricedAddons['addons_gross_total'] > 0)
                                {{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge'] + $pricedAddons['addons_gross_total']) }}
                                @else
                                {{ moneyFormat($comboBooking['bookingTotal'] + $customChargePrice['overall_custom_charge']) }}
                                @endif
                            </td>
                        </tr>
                        @if ($comboBooking['ageGroup'])
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Refunded Pax</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{!! $comboBooking['ageGroup'] !!}</td>
                        </tr>
                        @endif
                        @if ($comboBooking['refundKeepUs'])
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Non-Refundable Fees</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($comboBooking['refundKeepUs']) }}</td>
                        </tr>
                        @endif
                        @if ($comboBooking['refundAmount'])
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5; border-bottom: 1px solid #d5d5d5;">Refund Amount</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">{{ moneyFormat($comboBooking['refundAmount']) }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td colspan="{{ ($bookingM->is_group_booking == '1') ? "2" : ($bookingM->package_has_permit ? "5" : "4") }}" style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">Payment Total</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px;">{{ moneyFormat($comboBooking['paymentTotal'])  }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        @endforeach
        @if(count($comboBookings) > 1)
        <tr>
            <td style="padding: 0px 0px 20px; background: #FFFFFF;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" align="center" style="border: 1px solid #d5d5d5;">
                    <tbody>
                        <tr>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Subtotal</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Custom Charge</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Addons</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Booking Fees Total</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Booking Total</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Non-Refundable Fees Total</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;  border-bottom: 1px solid #d5d5d5;">Combo Refund Total</th>
                            <th style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-bottom: 1px solid #d5d5d5;">Combo Payment Total</th>
                        </tr>
                        <tr>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($order['comboSubtotal']) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($customChargeTotal) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($addOnTotal) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($feeTotal) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($bookingTotal) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($order['comboNonRefundableFeesTotal']) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px; border-right: 1px solid #d5d5d5;">{{ moneyFormat($order['comboRefundTotal']) }}</td>
                            <td style="font-size: 12px;font-weight: 600;line-height: 1.7;color: #333333;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align:left; padding: 5px 10px;">{{ moneyFormat($order['comboPaymentTotal']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        @endif
        ` <!-- <tr>
            <td style="padding: 24px 20px;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f5f5f5" align="center">
                    <tbody>
                        <tr>
                            <td style="font-size: 15px;font-weight: 400;line-height: 1.5;color: rgb(0,0,0);font-family: 'Open Sans', sans-serif;padding: 0;margin: 0; width: 215px;">
                                <img src="footer-logo.png" alt="Dixie's Lower Antelope Canyon Tours" width="169" height="152">
                            </td>
                            <td style="font-size: 16px;font-weight: 600;line-height: 1.5;color: #7b7b7b;font-family: 'Open Sans', sans-serif;padding: 0 0 0 40px;margin: 0;border-left: 1px solid #EBEBEB;">
                                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#f5f5f5" align="center">
                                    <tbody>
                                        <tr>
                                            <td style="font-size: 24px;font-weight: 700;line-height: 1;color: rgb(0,0,0);font-family: 'Open Sans', sans-serif;padding: 0 0 16px;margin: 0;">
                                                Dixie's Lower Antelope Canyon Tours
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 16px;font-weight: 600;line-height: 1.25;color: #7B7B7B;font-family: 'Open Sans', sans-serif;padding: 0 0 6px;margin: 0;">
                                                <a href="tel:9286401761" style="white-space: nowrap; color:#7B7B7B; text-decoration:none;">
                                                    <img src="phone-icon-large.png" alt="Phone Icon" width="13" height="14" style="margin-right: 5px;"> 928-640-1761
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 16px;font-weight: 600;line-height: 1.25;color: #7B7B7B;font-family: 'Open Sans', sans-serif;padding: 0 0 6px;margin: 0;">
                                                <a href="mailto:info@nativeamericantours.com" style="white-space: nowrap; color:#7B7B7B; text-decoration:none;">
                                                    <img src="mail-icon-large.png" alt="Mail Icon" width="13" height="12" style="margin-right: 5px;"> info@nativeamericantours.com
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="font-size: 16px;font-weight: 600;line-height: 1.25;color: #7B7B7B;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;">
                                                <a href="www.antelopecanyon.com" style="white-space: nowrap; color:#7B7B7B; text-decoration:none;">
                                                    <img src="web-icon-large.png" alt="Web Icon" width="15" height="16" style="margin-right: 5px;"> www.antelopecanyon.com
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>` -->

        <tr>
            <td style="padding: 11px 20px; background-color: #ffffff;">
                <table width="100%" cellspacing="0" cellpadding="0" border="0" bgcolor="#ffffff" align="center">
                    <tbody>
                        <tr>
                            <td style="font-size: 14px;font-weight: 400;line-height: 1.25;color: #777;font-family: 'Open Sans', sans-serif;padding: 0;margin: 0;text-align: center;">
                                Powered by Native American Tours
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </tbody>
</table>