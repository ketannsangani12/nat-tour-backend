var affiliateCheck = 0;
var lookupForCashAndPayLaterPaymentMethod = 0;
var totalBeforeDiscountAndServiceCommission = 0;
var totalPaymentAmount = 0;
var totalRefundAmount = 0;
var finalAmount = 0;
var subTotalAmount = '';
var totalInfantsOrChilds = 0;
var totalAddedPeopleCount = 0;
var totalDeletedPeopleCount = 0;
// Package-level rate group ids (tour_package_rate_id) that had at least one person removed in
// this submission — read by the "Update Booking" modal to scope which add-ons it shows/refunds.
var totalDeletedRateGroupIds = [];
// How many people were removed from each rate group id (same keys as totalDeletedRateGroupIds) —
// lets the modal validate that no more distinct people's add-ons are selected for a rate group
// than are actually being removed from it (e.g. removing 1 Adult can't refund 2 Adults' add-ons).
var totalDeletedCountByRateGroupId = {};
var totalAddedInfantsCount = 0;
var serviceCommissionTotal = 0;
var cashAmountChangePeople = 0;
var totalTourists = 0;
var totalSeatsForBooking = 0;
var totalPaxForDropDown = 0;
var emptySeatsOfSlot = 0;
var addedPeopleCountArray = [];

$('#modal_admin').on('hide.bs.modal', function (e) {

    if ($('#modal_admin').data('call-by-edit-booking-change-people') && typeof ($('#make-a-payment').data('call-by-change-people-payment')) != 'undefined') {
        $('#edit_booking_change_people').data('prevent-init-calls', true).modal('show');
        $('#modal_admin').removeData('call-by-edit-booking-change-people');
        $('#discount_type_percentage, #discount_type_fixed_money').prop('checked', false);
        $('#discount_percentage, #discount_fixed_money').val('').prop('disabled', true);
        if (isGroupBooking == '1') {
            applyDiscountChangeGroupPeople();
        } else {
            applyDiscountChangePeople();
        }
    }
    if (typeof ($('#modal_admin').data('call-by-check-in-people')) != 'undefined' && $('#modal_admin').data('call-by-check-in-people')) {
        $('#modal_admin').removeData('call-by-check-in-people');
        $('#modal_admin').removeData('pendingCheckin');
    }
}).on('show.bs.modal', function (e) {
    if ($('#modal_admin').data('call-by-edit-booking-change-people') || $('#modal_admin').data('call-by-switch-package')) {
        if (paymentMethod == 'Cash') {
            $('#modal_admin label[for="pay-with-card1"]').hide();
            $('#modal_admin label[for="pay-with-later"]').hide();
            $('#modal_admin label[for="pay-with-cash1"]').addClass('checked-item');
            $('#modal_admin .card-details').hide();
            $('#modal_admin #pay-with-cash1').prop('checked', true);
        } else if (paymentMethod == 'Card') {
            $('#modal_admin label[for="pay-with-cash1"]').hide();
            $('#modal_admin label[for="pay-with-later"]').hide();
            // The Card option's parent div carries Blade's 'hidden' class when $totalV == 0
            // (fully-paid booking at page load). JS updates the amount dynamically but never
            // removes that class, so the div stays invisible. Force it visible here.
            $('#modal_admin label[for="pay-with-card1"]').closest('.payment-condition-item').show();
            $('#modal_admin label[for="pay-with-card1"]').show().addClass('checked-item');
            $('#modal_admin #pay-with-card1').prop('checked', true);
        } else if (paymentMethod == 'Invoiced') {
            $('#modal_admin label[for="pay-with-cash1"]').hide();
            $('#modal_admin label[for="pay-with-card1"]').hide();
            $('#modal_admin label[for="pay-with-later"]').addClass('checked-item');
            $('#modal_admin .card-details').hide();
            $('#modal_admin #pay-with-later').prop('checked', true);
        } else if (paymentMethod == 'Payment Link') {
            // Original booking was via Payment Link — allow any method for the addition
            $('#modal_admin label[for="pay-with-card1"]').show();
            $('#modal_admin label[for="pay-with-cash1"]').show();
            $('#modal_admin label[for="pay-with-later"]').show();
        }
        if (splitPayment == '' || splitPayment == '0') {
            $('#modal_admin .split-payment-div').hide();
        }
        if (isGroupBooking == '1') {
            applyDiscountChangeGroupPeople();
        } else {
            applyDiscountChangePeople();
        }
        // Sync submit button label with the pre-selected payment method radio
        var checkedMethod = $('#modal_admin input[name="payment_method"]:checked').val();
        $('#btn-make-payment1').val(checkedMethod === 'Payment Link' ? 'Send Payment Link' : 'Make a Payment');
    }
});

// Info button — show pending payment breakdown for admin or affiliate modal
$(document).on('click', '#btn-cp-payment-breakdown, #btn-cp-payment-breakdown-inline, #btn-cp-payment-breakdown-affiliate', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var url = $('#modal_admin').data('breakdown-url') || $('#modal_affilate').data('breakdown-url');
    if (!url) return;
    loadBreakdownItems(url);
});

// Re-compute totals when admin switches payment method in modal_admin for change people
$(document).on('change', '#pay-with-link-cp', function () {
    // Split payment does not apply to payment links
    $('#modal_admin .split-payment-div').hide();
    $('#btn-make-payment1').val('Send Payment Link');
    // Only recalculate amounts in Change People mode — in Make Payment mode finalAmount
    // is 0 (change-people hasn't run), so calling applyDiscount would zero all amounts.
    if ($('#modal_admin').data('call-by-edit-booking-change-people')) {
        if (isGroupBooking == '1') {
            applyDiscountChangeGroupPeople();
        } else {
            applyDiscountChangePeople();
        }
    }
});
$(document).on('change', '#pay-with-card1, #pay-with-cash1, #pay-with-later', function () {
    $('#btn-make-payment1').val('Make a Payment');
    // Only run the change-people recalculation in Change People mode.
    if (!$('#modal_admin').data('call-by-edit-booking-change-people')) return;
    // Restore split payment visibility when switching back to card/cash/invoiced
    if (splitPayment != '' && splitPayment != '0') {
        $('#modal_admin .split-payment-div').show();
    }
    if (isGroupBooking == '1') {
        applyDiscountChangeGroupPeople();
    } else {
        applyDiscountChangePeople();
    }
});

// Re-compute totals when affiliate switches to Payment Link in modal_affilate for change people
$(document).on('change', '#pay-with-link-cp-affiliate', function () {
    $('#modal_affilate .split-payment-div').hide();
    $('#btn-make-payment').val('Send Payment Link');
    if ($('#modal_affilate').data('call-by-edit-booking-change-people')) {
        if (isGroupBooking == '1') {
            applyDiscountChangeGroupPeople();
        } else {
            applyDiscountChangePeople();
        }
    }
});
$(document).on('change', '#pay-with-card, #pay-with-cash, #pay-with-later2', function () {
    $('#btn-make-payment').val('Make a Payment');
    if (!$('#modal_affilate').data('call-by-edit-booking-change-people')) return;
    if (splitPayment != '' && splitPayment != '0') {
        $('#modal_affilate .split-payment-div').show();
    }
    if (isGroupBooking == '1') {
        applyDiscountChangeGroupPeople();
    } else {
        applyDiscountChangePeople();
    }
});

$('#modal_affilate').on('hide.bs.modal', function (e) {
    if (discountPermission == "Yes") {
        if ($('#modal_affilate').data('call-by-edit-booking-change-people') && typeof ($('#make-a-payment').data('call-by-change-people-payment')) != 'undefined') {
            $('#edit_booking_change_people').data('prevent-init-calls', true).modal('show');
            $('#modal_affilate').removeData('call-by-edit-booking-change-people');
            $('#discount_type_percentage, #discount_type_fixed_money').prop('checked', false);
            $('#discount_percentage, #discount_fixed_money').val('').prop('disabled', true);
            if (isGroupBooking == '1') {
                applyDiscountChangeGroupPeople();
            } else {
                applyDiscountChangePeople();
            }
        }
    }
    if (typeof ($('#modal_affilate').data('call-by-check-in-people')) != 'undefined' && $('#modal_affilate').data('call-by-check-in-people')) {
        $('#modal_affilate').removeData('call-by-check-in-people');
        $('#modal_affilate').removeData('pendingCheckin');
    }

}).on('show.bs.modal', function (e) {
    if ($('#modal_affilate').data('call-by-edit-booking-change-people') || $('#modal_affilate').data('call-by-switch-package')) {
        if (paymentMethod == 'Cash') {
            $('#modal_affilate label[for="pay-with-card"]').hide();
            $('#modal_affilate label[for="pay-with-later2"]').hide();
            $('#modal_affilate label[for="pay-with-cash"]').addClass('checked-item');
            $('#modal_affilate .payment-details').hide();
            $('#modal_affilate label[for="pay-with-later"]').hide();
            $('#modal_affilate #pay-with-cash').prop('checked', true);
        } else if (paymentMethod == 'Card') {
            $('#modal_affilate label[for="pay-with-cash"]').hide();
            $('#modal_affilate label[for="pay-with-later2"]').hide();
            $('#modal_affilate label[for="pay-with-card"]').addClass('checked-item');
            $('#modal_affilate #pay-with-card').prop('checked', true);
        } else if (paymentMethod == 'Invoiced') {
            $('#modal_affilate label[for="pay-with-cash"]').hide();
            $('#modal_affilate label[for="pay-with-card"]').hide();
            $('#modal_affilate label[for="pay-with-later2"]').addClass('checked-item');
            $('#modal_affilate #pay-with-card').hide();
            $('#modal_affilate #pay-with-later2').prop('checked', true);
        } else if (paymentMethod == 'Payment Link') {
            if ($('#modal_affilate #pay-with-link-cp-affiliate').length) {
                $('#modal_affilate label[for="pay-with-cash"]').hide();
                $('#modal_affilate label[for="pay-with-card"]').hide();
                $('#modal_affilate label[for="pay-with-later2"]').hide();
                $('#modal_affilate #pay-with-link-cp-affiliate').prop('checked', true);
            } else {
                // Pay with Link isn't rendered when the tenant's PAYMENT_LINK setting is off —
                // fall back to Card instead of leaving no payment method to choose.
                $('#modal_affilate label[for="pay-with-card"]').show().addClass('checked-item');
                $('#modal_affilate #pay-with-card').prop('checked', true);
            }
        }
        // Pay with Link is only for Card bookings (or one already collecting via a pending
        // remaining-payment link / originally paid by link) — same rule as #modal_admin's
        // change-people handler. A Cash or Invoiced booking must not offer it.
        if (paymentMethod == 'Card' || paymentMethod == 'Payment Link') {
            $('#payment-link-cp-affiliate-option').show();
        } else {
            $('#payment-link-cp-affiliate-option').hide();
            $('#modal_affilate #pay-with-link-cp-affiliate').prop('checked', false);
        }
        if (splitPayment == '' || splitPayment == '0') {
            $('#modal_affilate .split-payment-div').hide();
        }
        if (isGroupBooking == '1') {
            applyDiscountChangeGroupPeople();
        } else {
            applyDiscountChangePeople();
        }
        // Sync submit button label with the pre-selected payment method
        var checkedAffiliate = $('#modal_affilate input[name="payment_method"]:checked').val();
        $('#btn-make-payment').val(checkedAffiliate === 'Payment Link' ? 'Send Payment Link' : 'Make a Payment');
    }
});
$('#make-a-payment').on('hide.bs.modal', function (e) {
    if ($('#make-a-payment').data('call-by-change-people-payment')) {
        if (isAffiliate == 'Yes') {
            $('#modal_affilate').data('call-by-edit-booking-change-people', true).modal('show');
            $('#make-a-payment').removeData('call-by-change-people-payment');
        } else {
            $('#modal_admin').data('call-by-edit-booking-change-people', true).modal('show');
            $('#make-a-payment').removeData('call-by-change-people-payment');
        }
    }
});
function setRateGroupsChangePeople() {
    var $tblRateGrps = $('#table-rate-groups-change-people');
    $tblRateGrps.html('');
    var k = 0;
    for (var a = 0; a < rateGroups.length; a++) {
        var $tmpl = $('#tmpl-rate-group-change-people tr').clone(true);
        $hidRateGroup = $tmpl.find('.hid-rate-group');
        $hidRateGroup.val(rateGroups[a].id).attr('name', $hidRateGroup.data('name'));
        $selTourists = $tmpl.find('.tourists');
        $selTourists.data('pre', (rateGroups[a].infants > 0) ? rateGroups[a].infants : rateGroups[a].tourists);
        $selTourists.data('id', rateGroups[a].id);
        $selTourists.attr('name', $selTourists.data('name'));
        $selPermitFees = $tmpl.find('.permit-fee');
        $selPermitFees.attr('name', $selPermitFees.data('name'));

        // if rate is zero then no other fees will be applicable
        if (parseFloat(rateGroups[a].rate) == 0) {
            rateGroups[a].processing_charge_percentage =
                rateGroups[a].additional_tax_percentage =
                rateGroups[a].additional_charge =
                '0.00';
        }

        $tmpl.find('.age').html('<span class="rate-for" data-tourists="' + (rateGroups[a].infants > 0 ? rateGroups[a].infants : rateGroups[a].tourists) + '">' + encLT(rateGroups[a].rate_for) + '</span>');
        
        if(cashPriceEnabled == 1 && paymentMethod == 'Cash' && rateGroups[a].is_cash_rate == 1) {
            rateGroups[a].rate = rateGroups[a].cash_rate;
            rateGroups[a].additional_charge = rateGroups[a].cash_tax;
        } 
         $tmpl.find('.price .tag').text(currencyFormation.symbol + rateGroups[a].rate);
    
        $tmpl.find('.permit-fee').addClass('permit-fee1');
        $tmpl.find('.permit-fee1').removeClass('permit-fee');
        $tmpl.find('.proc-fee').addClass('processing-fee-' + a);
        var pFee = Number(rateGroups[a].permit_fee);
        var zeroselected = (pFee == 0.00) ? 'selected' : '';
        var dynamicPermitFee = (pFee > 0) ? '<option selected>' + pFee + '</option>' : '';

        $tmpl.find('.permit-fee1').html('<option ' + zeroselected + '>0</option>' + dynamicPermitFee);
        if (paymentMethod == 'Card') {
            if (Number(bookingId) < Number(oldBookingId)) {
                if (rateGroups[a].old_processing_charge_percentage > 0) {
                    $tmpl.find('.proc-fee').text(rateGroups[a].old_processing_charge_percentage + '%');
                } else {
                    if (rateGroups[a].rate > 0) {
                        $tmpl.find('.proc-fee').text('2.9%');
                    } else {
                        $tmpl.find('.proc-fee').text('0.0%');
                    }

                }
            } else {
                $tmpl.find('.proc-fee').text(rateGroups[a].processing_charge_percentage + '%');
            }
        } else {
            $tmpl.find('.proc-fee').text('0.00%');
        }
        if (isPartnerBooking == 1 && rateGroups[a].partner_fee_amount > 0) {
            $tmpl.find('.partner-fee').text(currencyFormation.symbol + rateGroups[a].partner_fee_amount);
        }
        $tmpl.find('.adnl-tax').text(rateGroups[a].additional_tax_percentage + '%');
        $tmpl.find('.adnl-charge').text(currencyFormation.symbol + rateGroups[a].additional_charge);
        $tblRateGrps.append($tmpl);
        k++;
    }

    totalSansDiscount = 0;
    addTaxColsChangePeople();
    updateRatesTableChangePeople();
    permitFeeTableChangePeople();
}

function addTaxColsChangePeople() {
    $('.custom-tax').remove();
    $hdRateGrp = $('#headings-rate-group').children();
    $tblRateGrps = $('#table-rate-groups-change-people').children();
    var initRateGrpCols = $hdRateGrp.length;
    var taxCellStart = '<td class="normal custom-tax">';
    var taxCellEnd = '</td>';
    var taxDisplayValue = '';

    for (var a = 0; a < taxes.length; a++) {
        $('<th class="custom-tax">' + taxes[a].name + '</th>')
            .insertBefore($hdRateGrp[$hdRateGrp.length - 4]);

        for (var b = 0; b < $tblRateGrps.length; b++) {
            // if rate is zero for age/rate group then display 0 tax value
            if ($tblRateGrps[b].children[2].innerText == currencyFormation.symbol + '0.00')
                taxDisplayValue = (taxes[a].value_type == 'Percent' ? '0.00%' : currencyFormation.symbol + '0.00');
            else
                taxDisplayValue = (taxes[a].value_type == 'Percent' ? taxes[a].value + '%' : currencyFormation.symbol + taxes[a].value);

            $(taxCellStart + taxDisplayValue + taxCellEnd)
                .insertBefore($tblRateGrps[b].children[$tblRateGrps[b].children.length - 4]);
        }
    }

    $('.subtotal-price, .total').attr('colspan', initRateGrpCols + taxes.length);
}

function updateRatesTableChangePeople() {
    if (isGroupBooking == '1') {
        updateGroupRatesTableChangePeople();
    } else {
        $('.discount-applied').val(0);

        if ($('#slot_time').val() == 0) {
            $('#table-rate-groups-change-people .tourists').html('<option>0</option>');
            return;
        }

        //This is for old bookings
        if (Number(bookingId) < Number(oldBookingId)) {
            if (isAffiliate != 'Yes') {
                serviceCommissionPercentage = 6;
            } else {
                serviceCommissionPercentage = 0;
            }
        } else {
            if (isAffiliate == 'Yes') {
                serviceCommissionPercentage = rateGroups[0].affiliate_processing_percentage;
            } else {
                serviceCommissionPercentage = rateGroups[0].service_commission_percentage;
            }
        }

        var $tblRateGrps = $('#table-rate-groups-change-people');
        var total = 0;
        var lookup = 0;
        var totalpayment = 0;
        var totalrefund = 0;
        var infantsorchilds = 0;
        var addedPeopleCount = 0;
        var deletedPeopleCount = 0;
        var touristsTotal = 0;
        var allTouristsCount = 0;
        serviceCommissionTotal = 0;
        for (var a = 0; a < rateGroups.length; a++) {
            $row = $($tblRateGrps.children()[a]);
            var $selTourists = $row.find('.tourists');
            var tourists = parseInt($selTourists.val()) || 0;
            allTouristsCount += tourists;
            var refundedPermitFeesAmount = rateGroups[a].permitfees_refunded;
            var oldTourists = rateGroups[a].tourists;
            var oldFinalAmount = 0;
            
            // FIXED: Calculate oldFinalAmount using effective permit fee instead of original total + refund
            // For bookings with partial permit fee refunds, we need to use the effective permit fee
            // to get the correct baseline for comparison
            if (oldTourists > 0) {
                var effectivePermitFee = parseFloat($row.find('.permit-fee').val()) || parseFloat($row.find('.permit-fee1').val()) || 0;
                var baseRate = parseFloat(rateGroups[a].rate) || 0;
                var additionalCharge = parseFloat(rateGroups[a].additional_charge) || 0;
                var partnerFeeAmount = isPartnerBooking ? parseFloat(rateGroups[a].partner_fee_amount) || 0 : 0;
                
                // Calculate the base amount per person using effective permit fee
                var baseAmountPerPerson = baseRate + additionalCharge + effectivePermitFee + partnerFeeAmount;
                
                // Calculate taxes
                var stateTaxPerPerson = 0;
                for (var taxIdx = 0; taxIdx < taxes.length; taxIdx++) {
                    stateTaxPerPerson += (taxes[taxIdx].value_type == 'Percent'
                        ? baseRate * taxes[taxIdx].value / 100
                        : taxes[taxIdx].value
                    );
                }
                
                var baseTotal = oldTourists * (baseAmountPerPerson + stateTaxPerPerson);
                
                // Add processing fees if applicable
                if (baseTotal > 0) {
                    var processingCharge = Number(bookingId) < Number(oldBookingId) ? 2.9 : Number(rateGroups[a].processing_charge_percentage);
                    
                    if (rateGroups[a].additional_tax_percentage != 0) {
                        var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
                        baseTotal += roundout((baseTotal * ad_tax_pe_n_processing_fee / 100), 2);
                    } else {
                        if (processingCharge) {
                            baseTotal += roundout((baseTotal * processingCharge / 100), 2);
                        }
                    }
                }
                
                oldFinalAmount = baseTotal;
            }

            var $selpermitfee = $row.find('.permit-fee');
            if (isNaN(parseInt($selpermitfee.val()))) {
                $selpermitfee = $row.find('.permit-fee1');
            }
            var permitfee = parseInt($selpermitfee.val());

            var groupTotal = 0;
            var stateTax = 0;
            var lookTotal = 0;

            var paymentAmount = 0;
            var partnerFee = 0;
            var refundAmount = 0;
            // rate may be zero for one or more age/rate group and
            // if rate is non-zero then only add other amounts
            if (parseFloat(rateGroups[a].rate) > 0 || parseFloat(permitfee) > 0) {
                for (var b = 0; b < taxes.length; b++) {
                    stateTax += tourists * (taxes[b].value_type == 'Percent'
                        ? rateGroups[a].rate * taxes[b].value / 100
                        : taxes[b].value
                    );
                }

                //Calculate partner fees for partner package
                if (isPartnerBooking) {
                    partnerFee = rateGroups[a].partner_fee_amount;
                }

                groupTotal = tourists * (
                    parseFloat(rateGroups[a].rate)
                    + parseFloat(rateGroups[a].additional_charge)
                    + parseFloat(permitfee)
                    + parseFloat(partnerFee)
                );

                if (tourists) {
                    if (Number(bookingId) < Number(oldBookingId)) {
                        var processingCharge = 2.9;
                    } else {
                        // Calculate service commission only for ADDED tourists, not all tourists
                        if (getDiscountStatus(groupTotal) == 0) {
                            var addedTouristsForGroup = (tourists > oldTourists) ? (tourists - oldTourists) : 0;
                            if (addedTouristsForGroup > 0) {
                                var serviceCommissionPax = roundout(((parseFloat(rateGroups[a].rate) + parseFloat(rateGroups[a].additional_charge) + parseFloat(permitfee) + parseFloat(partnerFee)) * serviceCommissionPercentage / 100), 2);
                                serviceCommissionPax = addedTouristsForGroup * serviceCommissionPax;
                                serviceCommissionTotal += serviceCommissionPax;
                            }
                        }
                        var processingCharge = Number(rateGroups[a].processing_charge_percentage);
                    }

                    if (rateGroups[a].additional_tax_percentage != 0) {
                        var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
                        lookTotal += roundout((groupTotal * Number(rateGroups[a].additional_tax_percentage) / 100), 2);
                        groupTotal += roundout((groupTotal * ad_tax_pe_n_processing_fee / 100), 2);
                    } else {
                        lookTotal += groupTotal;
                        if (processingCharge) {
                            groupTotal += roundout((groupTotal * processingCharge / 100), 2);
                        }
                    }
                }
            } else if (rateGroups[a].is_infant_rate == '1' && tourists > 0) {
                infantsorchilds += tourists;
            }
            
            if (groupTotal) {
                if (paymentMethod == 'Card' || (isCashFees == '1' && paymentMethod == 'Cash')) {
                    $row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
                } else {
                    $row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
                }
            } else {
                $row.find('.final').text(currencyFormation.symbol + '0.00');
            }

            //Calculation of payment and refund amounts when changed people in booking
            if (oldTourists > 0 && tourists > 0 && oldTourists != tourists) {

                if (paymentMethod == 'Card' || (isCashFees == '1' && paymentMethod == 'Cash')) {
                    //This is for increase people
                    if (groupTotal > oldFinalAmount && tourists > oldTourists) {
                        paymentAmount += (groupTotal - oldFinalAmount);
                        addedPeopleCount += tourists - oldTourists;
                    }

                    //This is for decrease people
                    if (oldFinalAmount > 0 && groupTotal < oldFinalAmount && tourists < oldTourists) {
                        refundAmount += (oldFinalAmount - groupTotal);
                        deletedPeopleCount += oldTourists - tourists;
                    }
                } else {
                    //This is for increase people
                    if (lookTotal > oldFinalAmount && tourists > oldTourists) {
                        paymentAmount += (lookTotal - oldFinalAmount);
                        addedPeopleCount += tourists - oldTourists;
                    }

                    //This is for decrease people
                    if (oldFinalAmount > 0 && lookTotal < oldFinalAmount && tourists < oldTourists) {
                        refundAmount += (oldFinalAmount - lookTotal);
                        deletedPeopleCount += oldTourists - tourists;
                    }
                }
            } else if (oldTourists > 0 && tourists == 0 && oldTourists != tourists) {
                if (oldFinalAmount > 0 && lookTotal < oldFinalAmount && tourists < oldTourists) {
                    refundAmount += (oldFinalAmount - lookTotal);
                    //deletedPeopleCount += oldTourists - tourists;
                }
            } else if (oldTourists == 0 && oldTourists != tourists) {
                //This is for increase people
                if (paymentMethod == 'Card' || (isCashFees == '1' && paymentMethod == 'Cash')) {
                    groupTotal = Number(groupTotal);
                    paymentAmount += groupTotal;
                } else {
                    lookTotal = Number(lookTotal);
                    paymentAmount += lookTotal;
                }
                addedPeopleCount += tourists - oldTourists;
            }

            if (tourists > oldTourists && rateGroups[a].is_infant_rate != '1') {
                touristsTotal += tourists - oldTourists;
            }

            total += groupTotal;
            lookup += lookTotal;

            totalpayment += paymentAmount;
            totalrefund += refundAmount;
            
            if ($selTourists.children().length > 1) {
                var rateGroupId = $row.find('.hid-rate-group').val();

                for (var b = 0; b < touristsGroups.length; b++) {
                    if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
                        touristsGroups[b].tourists = tourists;
                        touristsGroups[b].selectedtourists = oldTourists;
                        break;
                    }
                }

                if (b >= touristsGroups.length) { // rate group isn't in the array, so add it
                    touristsGroups.push({
                        rate_for: $row.find('.rate-for').text(),
                        tour_package_rate_id: rateGroupId,
                        tourists: tourists,
                        selectedtourists: oldTourists
                    });
                }

            }
        }
        
        totalBeforeDiscountAndServiceCommission = total;
        lookupForCashAndPayLaterPaymentMethod = lookup;
        cashAmountChangePeople = lookupForCashAndPayLaterPaymentMethod;
        totalTourists = touristsTotal;

        if (userType == 'Affiliate' && totalSeatsForBooking == 0) {
            var seats = parseInt(totalTourists);
        } else {
            var seats = parseInt(totalSeatsForBooking - totalTourists);
        }

        for (var a = 0; a < rateGroups.length; a++) {
            $row = $($tblRateGrps.children()[a]);
            var $selTourists = $row.find('.tourists');
            var touristsA = parseInt($selTourists.val());
            var preViousVal = $selTourists.data('pre');
            $selTourists.data('pre', touristsA);
            var rateGroupId = $row.find('.hid-rate-group').val();

            if (!isNaN(seats) && totalTourists > 0 && rateGroups[a].is_infant_rate == 0) {
                if (parseInt($selTourists.val()) == 0) {
                    $row.find('.tourists option').filter(function () {
                        if (userType == 'Affiliate' && totalSeatsForBooking == 0) {
                            return parseInt(this.value, 10) > 0;
                        } else {
                            if (seats < 0) {
                                return parseInt(this.value, 10) > 0;
                            } else {
                                return parseInt(this.value, 10) > seats;
                            }
                        }
                    }).remove();
                } else {
                    $row.find('.tourists option').filter(function () {
                        if (userType == 'Affiliate' && totalSeatsForBooking == 0) {
                            if (rateGroups[a].tourists > 0) {
                                return (parseInt(this.value, 10) > parseInt(rateGroups[a].tourists) && parseInt(this.value, 10) > 0);
                            } else {
                                return (parseInt(this.value, 10) > parseInt($selTourists.val()) && parseInt(this.value, 10) > 0);
                            }
                        } else if (userType == 'Affiliate' && totalSeatsForBooking > 0) {
                            return (parseInt(this.value, 10) > parseInt($selTourists.val()) + seats);
                        } else {
                            return (parseInt(this.value, 10) > parseInt($selTourists.val()) && parseInt(this.value, 10) > seats);
                        }
                    }).remove();
                }
            }

            // This is for update tourists count dropdowns of rate groups simultaneously
            if (addedPeopleCount > 0 || deletedPeopleCount > 0) {
                if (preViousVal != touristsA && touristsA < preViousVal) {
                    if (totalPaxForDropDown <= 0 && userType != 'Affiliate') {
                        var seatsOptions = preViousVal - touristsA;
                        for (var c = 0; c < rateGroups.length; c++) {
                            if (rateGroups[c].id != parseInt(rateGroupId)) {
                                var $rowAgain = $($tblRateGrps.children()[c]);
                                var lastValue = $rowAgain.find('.tourists option:last-child').val();

                                for (var d = parseInt(lastValue); d < parseInt(lastValue) + parseInt(seatsOptions); d++) {
                                    $rowAgain.find(".tourists").append(new Option(d + 1));
                                }
                            }
                        }
                    } else {
                        var seatsOptions = preViousVal - touristsA;
                        var lastValue = $row.find('.tourists option:last-child').val();
                        var rateGroupId = parseInt($row.find('.hid-rate-group').val());
                        for (var b = 0; b < rateGroups.length; b++) {
                            if (rateGroups[b].id != parseInt(rateGroupId)) {
                                var $rowAgain = $($tblRateGrps.children()[b]);
                                var lastValue = $rowAgain.find('.tourists option:last-child').val();
                                for (var e = parseInt(lastValue); e < parseInt(lastValue) + parseInt(seatsOptions); e++) {
                                    $rowAgain.find(".tourists").append(new Option(e + 1));
                                }
                            }
                        }
                    }
                }
            } else if (addedPeopleCount == 0 && deletedPeopleCount == 0 && totalPaxForDropDown > 0 && userType != 'Affiliate') {
                if (rateGroups[a].tourists == 0) {
                    $row.find('.tourists option').filter(function () {
                        return (parseInt(this.value, 10) > totalPaxForDropDown);
                    }).remove();
                }
            } else if (addedPeopleCount == 0 && deletedPeopleCount == 0 && totalPaxForDropDown < 0 && userType == 'Affiliate') {
                if (rateGroups[a].tourists == 0) {
                    $row.find('.tourists option').filter(function () {
                        return (parseInt(this.value, 10) > 0);
                    }).remove();
                }
            }
        }

        // These are additional calculations for change people feature
        totalPaymentAmount = Number(totalpayment);
        totalRefundAmount = Number(totalrefund);

        finalAmount = totalPaymentAmount - totalRefundAmount;
        if (finalAmount) {
            $('.total-before-service-commission-and-discount-change-people').text(currencyFormation.symbol + finalAmount.toFixed(2));

            if (finalAmount >= 0) {
                $(".total-before-service-commission-and-discount-change-people").css("color", "#4abd5d");
            } else if (finalAmount < 0) {
                $(".total-before-service-commission-and-discount-change-people").css("color", "#ff5e5e");
            } else {
                $(".total-before-service-commission-and-discount-change-people").css("color", "#4abd5d");
            }
        } else {
            $('.total-before-service-commission-and-discount-change-people').text('$0.00');
            $(".total-before-service-commission-and-discount-change-people").css("color", "#4abd5d");
        }

        totalInfantsOrChilds = infantsorchilds;
        $('#infantorchilds').val(totalInfantsOrChilds);
        $('input[name="total_tourist"]').val(allTouristsCount);
        if (infantsorchilds > 0 && (totalPaymentAmount == 0 && totalRefundAmount == 0)) {
            subTotalAmount = 0;
            $('#subtotalchangepeople').val(0);
        } else {
            subTotalAmount = '';
            $('#subtotalchangepeople').empty();
        }

        applyDiscountChangePeople();
    }
}

function permitFeeTableChangePeople() {
    $('.discount-applied').val(0);

    if ($('#slot_time').val() == 0) {
        $('#table-rate-groups-change-people .tourists').html('<option>0</option>');
        return;
    }

    var $tblRateGrps = $('#table-rate-groups-change-people');
    var total = 0;
    var lookup = 0;
    var totalpayment = 0;
    var totalrefund = 0;
    var infantsorchilds = 0;
    var allTouristsCount = 0;
    // serviceCommissionTotal = 0;

    for (var a = 0; a < rateGroups.length; a++) {
        $row = $($tblRateGrps.children()[a]);
        var $selTourists = $row.find('.tourists');
        var tourists = parseInt($selTourists.val()) || 0;
        allTouristsCount += tourists;
        var oldTourists = rateGroups[a].tourists;
        var refundedPermitFeesAmount = rateGroups[a].permitfees_refunded;
        var oldFinalAmount = 0;
        
        // FIXED: Calculate oldFinalAmount using effective permit fee instead of original total + refund
        // For bookings with partial permit fee refunds, we need to use the effective permit fee
        // to get the correct baseline for comparison
        if (oldTourists > 0) {
            var effectivePermitFee = parseFloat($row.find('.permit-fee').val()) || parseFloat($row.find('.permit-fee1').val()) || 0;
            var baseRate = parseFloat(rateGroups[a].rate) || 0;
            var additionalCharge = parseFloat(rateGroups[a].additional_charge) || 0;
            var partnerFeeAmount = isPartnerBooking ? parseFloat(rateGroups[a].partner_fee_amount) || 0 : 0;
            
            // Calculate the base amount per person using effective permit fee
            var baseAmountPerPerson = baseRate + additionalCharge + effectivePermitFee + partnerFeeAmount;
            
            // Calculate taxes
            var stateTaxPerPerson = 0;
            for (var taxIdx = 0; taxIdx < taxes.length; taxIdx++) {
                stateTaxPerPerson += (taxes[taxIdx].value_type == 'Percent'
                    ? baseRate * taxes[taxIdx].value / 100
                    : taxes[taxIdx].value
                );
            }
            
            var baseTotal = oldTourists * (baseAmountPerPerson + stateTaxPerPerson);
            
            // Add processing fees if applicable
            if (baseTotal > 0) {
                var processingCharge = Number(bookingId) < Number(oldBookingId) ? 2.9 : Number(rateGroups[a].processing_charge_percentage);
                
                if (rateGroups[a].additional_tax_percentage != 0) {
                    var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
                    baseTotal += roundout((baseTotal * ad_tax_pe_n_processing_fee / 100), 2);
                } else {
                    if (processingCharge) {
                        baseTotal += roundout((baseTotal * processingCharge / 100), 2);
                    }
                }
            }
            
            oldFinalAmount = baseTotal;
        }
        var $selpermitfee = $row.find('.permit-fee');
        if (isNaN(parseInt($selpermitfee.val()))) {
            $selpermitfee = $row.find('.permit-fee1');
        }
        var permitfee = parseInt($selpermitfee.val());
        var partnerFee = 0;
        var groupTotal = 0;
        var lookTotal = 0;
        var stateTax = 0;
        var paymentAmount = 0;
        var refundAmount = 0;

        // rate may be zero for one or more age/rate group and
        // if rate is non-zero then only add other amounts
        if (parseFloat(rateGroups[a].rate) || parseFloat(permitfee)) {
            for (var b = 0; b < taxes.length; b++) {
                stateTax += tourists * (taxes[b].value_type == 'Percent'
                    ? rateGroups[a].rate * taxes[b].value / 100
                    : taxes[b].value
                );
            }

            //Calculate partner fees for partner package
            if (isPartnerBooking) {
                partnerFee = rateGroups[a].partner_fee_amount;
            }
            groupTotal = tourists * (
                parseFloat(rateGroups[a].rate)
                + parseFloat(rateGroups[a].additional_charge)
                + parseFloat(permitfee)
                + parseFloat(partnerFee)
            );

            if (tourists) {
                if (Number(bookingId) < Number(oldBookingId)) {
                    var processingCharge = (rateGroups[a].old_processing_charge_percentage > 0) ? Number(rateGroups[a].old_processing_charge_percentage) : 2.9;
                } else {
                    // if (getDiscountStatus(groupTotal) == 0) {
                    //     var serviceCommissionPax = roundout(((parseFloat(rateGroups[a].rate) + parseFloat(rateGroups[a].additional_charge) + parseFloat(permitfee) + parseFloat(partnerFee)) * serviceCommissionPercentage / 100), 2);
                    //     serviceCommissionPax = tourists * serviceCommissionPax;
                    //     serviceCommissionTotal += serviceCommissionPax;
                    // }
                    var processingCharge = Number(rateGroups[a].processing_charge_percentage);
                }
                if (rateGroups[a].additional_tax_percentage != 0) {
                    var ad_tax_pe_n_processing_fee = Number(rateGroups[a].additional_tax_percentage) + processingCharge;
                    lookTotal += roundout((groupTotal * Number(rateGroups[a].additional_tax_percentage) / 100), 2);
                    groupTotal += roundout((groupTotal * ad_tax_pe_n_processing_fee / 100), 2);
                } else {
                    lookTotal += groupTotal;
                    if (processingCharge) {
                        groupTotal += roundout((groupTotal * processingCharge / 100), 2);
                    }
                }
            }
        } else if (rateGroups[a].is_infant_rate == '1' && tourists > 0) {
            infantsorchilds += tourists;
        }

        if (groupTotal) {
            if (paymentMethod == 'Card' || (isCashFees == '1' && paymentMethod == 'Cash')) {
                $row.find('.final').text(currencyFormation.symbol + groupTotal.toFixed(2));
            } else {
                $row.find('.final').text(currencyFormation.symbol + lookTotal.toFixed(2));
            }
        } else {
            $row.find('.final').text(currencyFormation.symbol + '0.00');
        }

        //Calculation of payment and refund amounts when changed people in booking
        if (oldTourists > 0 && oldTourists != tourists) {
            if (paymentMethod == 'Card' || (isCashFees == '1' && paymentMethod == 'Cash')) {
                if (groupTotal > oldFinalAmount && tourists > oldTourists) {
                    paymentAmount += (groupTotal - oldFinalAmount);
                }

                if (oldFinalAmount > 0 && groupTotal < oldFinalAmount && tourists < oldTourists) {
                    refundAmount += (oldFinalAmount - groupTotal);
                }
            } else {
                if (lookTotal > oldFinalAmount && tourists > oldTourists) {
                    paymentAmount += (lookTotal - oldFinalAmount);
                }

                if (oldFinalAmount > 0 && lookTotal < oldFinalAmount && tourists < oldTourists) {
                    refundAmount += (oldFinalAmount - lookTotal);
                }
            }
        } else if (oldTourists == 0 && oldTourists != tourists) {
            if (paymentMethod == 'Card' || (isCashFees == '1' && paymentMethod == 'Cash')) {
                groupTotal = Number(groupTotal);
                paymentAmount += groupTotal;
            } else {
                lookTotal = Number(lookTotal);
                paymentAmount += lookTotal;
            }
        }

        total += groupTotal;
        lookup += lookTotal;

        totalpayment += paymentAmount;
        totalrefund += refundAmount;

        if ($selTourists.children().length > 1) {
            var rateGroupId = $row.find('.hid-rate-group').val();

            for (var b = 0; b < touristsGroups.length; b++) {
                if (touristsGroups[b].tour_package_rate_id == rateGroupId) {
                    touristsGroups[b].tourists = tourists;
                    touristsGroups[b].selectedtourists = oldTourists;
                    break;
                }
            }

            if (b >= touristsGroups.length) { // rate group isn't in the array, so add it
                touristsGroups.push({
                    rate_for: $row.find('.rate-for').text(),
                    tour_package_rate_id: rateGroupId,
                    tourists: tourists,
                    selectedtourists: oldTourists
                });
            }
        }
    }

    totalBeforeDiscountAndServiceCommission = total;
    lookupForCashAndPayLaterPaymentMethod = lookup;

    // These are additional calculations for change people feature
    totalPaymentAmount = Number(totalpayment);
    totalRefundAmount = Number(totalrefund);

    finalAmount = totalPaymentAmount - totalRefundAmount;

    if (finalAmount) {
        $('.total-before-service-commission-and-discount-change-people').text(currencyFormation.symbol + finalAmount.toFixed(2));
    } else {
        $('.total-before-service-commission-and-discount-change-people').text(currencyFormation.symbol + '0.00');
    }

    totalInfantsOrChilds = infantsorchilds;
    $('#infantorchilds').val(totalInfantsOrChilds);
    $('input[name="total_tourist"]').val(allTouristsCount);
    if (infantsorchilds > 0 && (totalPaymentAmount == 0 && totalRefundAmount == 0)) {
        subTotalAmount = 0;
        $('#subtotalchangepeople').val(0);
    } else {
        subTotalAmount = '';
        $('#subtotalchangepeople').empty();
    }

    applyDiscountChangePeople();
}

function showSlotInfoChangePeople(selSlotTime) {
    showSelectionInfoChangePeople();

    if (selSlotTime.selectedIndex == 0) {
        $('#span-seats-info-change-people').html('0 of 0 left');
        $('.tour-process').css('width', '50%');
        return;
    }

    var $opt = $(selSlotTime.options[selSlotTime.selectedIndex]);
    var tourists = $opt.data('tourists');
    var seats = $opt.data('seats');

    if (userType == 'Affiliate') {
        var emptySeats = $opt.data('booked');
    } else {
        var emptySeats = seats - tourists;
    }

    emptySeatsOfSlot = emptySeats;
    if (emptySeats <= 0 && userType == 'Affiliate') {
        $('#span-seats-info-change-people').html('0 of ' + seats + ' left');
    } else {
        $('#span-seats-info-change-people').html(emptySeats + ' of ' + seats + ' left');
    }

    $('.tour-process').css('width', ((tourists * 100) / seats) + '%');
    var seatsForOptions = emptySeats + overBookSeats;
    totalPaxForDropDown = seatsForOptions;

    if (userType == 'Affiliate') {
        if (emptySeats <= 0) {
            seatsForOptions = 0;
        }
    } else {
        if (emptySeats < 0 && seatsForOptions <= 0) {
            seatsForOptions = 0;
        }
    }

    var touristsRateGroupWise = [];
    var totalTouristsOfBooking = 0;
    $('#table-rate-groups-change-people .rate-for').each(function () {
        touristsRateGroupWise.push($(this).data('tourists'));
        totalTouristsOfBooking += $(this).data('tourists');
    });

    // `tourists` above is the slot's TOTAL booked pax across ALL bookings, including this one's
    // own already-reserved seats — so `emptySeats` (seats - tourists) treats this booking's own
    // pax as unavailable to itself. Credit them back for the overbooking-warning ceiling, same as
    // the "X of Y seats left" header (which does `slotSeats - slotTourists + currentBookingTourists`)
    // — otherwise the warning can fire even when the header shows plenty of room, especially on a
    // pure removal which should never be able to trigger it.
    if (userType != 'Affiliate') {
        emptySeatsOfSlot = emptySeats + totalTouristsOfBooking;
    }
    $('#table-rate-groups-change-people .tourists').each(function (index) {
        if (userType == 'Affiliate') {
            if (seatsForOptions == 0) {
                addTouristsOptsChangePeople($(this), touristsRateGroupWise[index]);
            } else {
                addTouristsOptsChangePeople($(this), touristsRateGroupWise[index] + emptySeats);
            }
        } else {
            if (seatsForOptions <= 0) {
                addTouristsOptsChangePeople($(this), touristsRateGroupWise[index]);
            } else {
                addTouristsOptsChangePeople($(this), touristsRateGroupWise[index] + seatsForOptions);
            }
        }
    });

    totalSeatsForBooking = seatsForOptions;

    var slot_time = $("#slot_time").val();
    $("#selected_slotTime").val(slot_time);

    setTouristsGroupsChangePeople();
    updateRatesTableChangePeople();
    permitFeeTableChangePeople();
}

function showSelectionInfoChangePeople() {
    if ($('#tour_package').val())
        $('.tour-title').text($('#tour_package')[0].options[$('#tour_package')[0].selectedIndex].text);
    else
        $('.tour-title').text('-');

    $('.booking-selected-date').text(moment($('#tour_slot_date').datepicker('getDate')).format('dddd, MMMM Do, Y'));

    if ($('#slot_time').val())
        $('.booking-selected-time').text($('#slot_time')[0].options[$('#slot_time')[0].selectedIndex].text);
    else
        $('.booking-selected-time').text('-');
}

function addTouristsOptsChangePeople($selTourists, emptySeats) {
    for (var a = 0, $selTourists; a < emptySeats; a++)
        $selTourists.append(new Option(a + 1));
}

function setTouristsGroupsChangePeople() {
    updateRatesTableChangePeople();
    $hidRateGroup = $('#table-rate-groups-change-people .hid-rate-group');
    $rateFor = $('#table-rate-groups-change-people .rate-for');
    $selTourists = $('#table-rate-groups-change-people .tourists');

    for (var a = 0; a < touristsGroups.length; a++) {
        for (var b = 0; b < $hidRateGroup.length; b++) {
            if (
                touristsGroups[a].tour_package_rate_id == $hidRateGroup[b].value ||
                touristsGroups[a].rate_for == encLT($rateFor[b].innerHTML)
            ) {
                $($selTourists[b]).val(touristsGroups[a].selectedtourists);
                break;
            }
        }
    }

    updateRatesTableChangePeople();
    permitFeeTableChangePeople();
}

function showMakePaymentForEditBookingChangePeople(form) {
    if (isGroupBooking == '1') {
        showMakePaymentForEditGroupBookingChangePeople(form);
        return false;
    } else {
        var addedPeopleCount = 0;
        var deletedPeopleCount = 0;
        var deletedRateGroupIds = [];
        var deletedCountByRateGroupId = {};

        var originalCountsMap = {};
        var isInfantRateMap = {};
        for (var i = 0; i < rateGroups.length; i++) {
            var rgId = rateGroups[i].id || rateGroups[i].tour_package_rate_id;
            isInfantRateMap[String(rgId)] = rateGroups[i].is_infant_rate == '1';
            // Infant rate groups store their count in rateGroups[i].infants, not .tourists
            originalCountsMap[String(rgId)] = isInfantRateMap[String(rgId)]
                ? (parseInt(rateGroups[i].infants) || 0)
                : (parseInt(rateGroups[i].tourists) || 0);
        }

        var addedInfantsCount = 0;
        var $tblRateGrps = $('#table-rate-groups-change-people');
        $tblRateGrps.children().each(function () {
            var $row = $(this);
            var rateGroupId = $row.find('.hid-rate-group').val();
            var currentTourists = parseInt($row.find('.tourists').val()) || 0;
            var originalTourists = originalCountsMap[String(rateGroupId)] || 0;
            var isInfant = isInfantRateMap[String(rateGroupId)] || false;

            if (currentTourists > originalTourists) {
                addedPeopleCount += (currentTourists - originalTourists);
                if (isInfant) {
                    addedInfantsCount += (currentTourists - originalTourists);
                }
            }
            if (currentTourists < originalTourists) {
                var removedFromThisGroup = originalTourists - currentTourists;
                deletedPeopleCount += removedFromThisGroup;
                deletedRateGroupIds.push(rateGroupId);
                deletedCountByRateGroupId[String(rateGroupId)] = (deletedCountByRateGroupId[String(rateGroupId)] || 0) + removedFromThisGroup;
            }
        });

        var hasV2Changes = (typeof changePeopleV2 !== 'undefined' && changePeopleV2.hasChanges && changePeopleV2.hasChanges());

        if (hasV2Changes) {
            // Reset counters because old table is irrelevant for V2 — including
            // addedInfantsCount, which otherwise keeps whatever stale value the old-table loop
            // above left behind (that loop never runs against current V2 state) and leaks into
            // the overbooking check below.
            addedPeopleCount = 0;
            deletedPeopleCount = 0;
            addedInfantsCount = 0;
            deletedRateGroupIds = [];
            deletedCountByRateGroupId = {};

            $('.cp-pax-row').each(function () {

                var $row = $(this);

                var original = parseInt($row.data('original')) || 0;
                var current  = Number($row.find('.cp-pax-count').text().trim()) || 0;
                var rateGroupId = $row.data('rate-id');
                var isInfant = isInfantRateMap[String(rateGroupId)] || false;

                if (current > original) {
                    addedPeopleCount += (current - original);
                    if (isInfant) {
                        addedInfantsCount += (current - original);
                    }
                }

                if (current < original) {
                    var removedFromThisGroup = original - current;
                    deletedPeopleCount += removedFromThisGroup;
                    deletedRateGroupIds.push(rateGroupId);
                    deletedCountByRateGroupId[String(rateGroupId)] = (deletedCountByRateGroupId[String(rateGroupId)] || 0) + removedFromThisGroup;
                }
            });
        }

        if (addedPeopleCount == 0 && deletedPeopleCount == 0 && !hasV2Changes) {
            swal('', 'You must need to select tourists to change.', 'error');
            return false;
        }

        if (hasV2Changes) {
            const { isValid, hasRequired } = changePeopleV2.validateAddons();
            if (hasRequired && !isValid) {
                swal(
                    'Required Fields',
                    'Please complete all required add-ons for each passenger.',
                    'error'
                );
                return false;
            }
        }

        totalAddedPeopleCount = addedPeopleCount;
        totalDeletedPeopleCount = deletedPeopleCount;
        totalDeletedRateGroupIds = Array.from(new Set(deletedRateGroupIds));
        totalDeletedCountByRateGroupId = deletedCountByRateGroupId;
        totalAddedInfantsCount = addedInfantsCount;

        // Determine which deleted pax are unpaid (payment link unsent → never charged to Stripe).
        // Build "itemId:transactId:count" entries so the backend can remove exact pax per rate group.
        var cpPendingEntries = [], cpPendingHistoryIds = [];
        if (typeof pendingPaxInfoByRateGroupId !== 'undefined') {
            for (var rgId in deletedCountByRateGroupId) {
                var unpaidInfo = pendingPaxInfoByRateGroupId[String(rgId)];
                if (unpaidInfo && unpaidInfo.added_count > 0 && deletedCountByRateGroupId[rgId] > 0) {
                    if (unpaidInfo.item_id) {
                        // Cap at unpaid count — paid pax in the same rate group are handled by normal refund
                        var removedUnpaid = Math.min(deletedCountByRateGroupId[rgId], unpaidInfo.added_count);
                        if (unpaidInfo.transact_id) {
                            cpPendingEntries.push(unpaidInfo.item_id + ':' + unpaidInfo.transact_id + ':' + removedUnpaid);
                        } else {
                            // Fallback for legacy items without transact_id — proportional removal
                            cpPendingEntries.push(String(unpaidInfo.item_id) + ':' + removedUnpaid);
                        }
                    } else if (!unpaidInfo.item_id && unpaidInfo.history_id && cpPendingHistoryIds.indexOf(unpaidInfo.history_id) === -1) {
                        cpPendingHistoryIds.push(unpaidInfo.history_id);
                    }
                }
            }
        }
        // Unique item IDs for the has-unpaid-deletion flag (length check below)
        var cpPendingItemIds = [];
        cpPendingEntries.forEach(function(e) {
            var id = e.split(':')[0];
            if (cpPendingItemIds.indexOf(id) === -1) cpPendingItemIds.push(id);
        });
        // Mixed path: set on #changePeopleForm (cp_ prefix to avoid name clash with #form_id)
        $('#cp_pending_item_ids, #cp_pending_item_ids_v2').val(cpPendingEntries.join(','));
        $('#cp_pending_history_ids, #cp_pending_history_ids_v2').val(cpPendingHistoryIds.join(','));
        // Delete-only path: set on #form_id (both forms are merged on submit)
        $('#pending_item_ids').val(cpPendingEntries.join(','));
        $('#pending_history_ids').val(cpPendingHistoryIds.join(','));

        // Subtract unpaid pax cost from the displayed refund — Stripe was never charged for them
        if (typeof pendingPaxInfoByRateGroupId !== 'undefined') {
            var unpaidRefundAdj = 0;
            for (var rgId in deletedCountByRateGroupId) {
                var unpaidInfo = pendingPaxInfoByRateGroupId[String(rgId)];
                if (unpaidInfo && unpaidInfo.added_count > 0 && unpaidInfo.per_pax_cost > 0) {
                    var unpaidCount = Math.min(deletedCountByRateGroupId[rgId] || 0, unpaidInfo.added_count);
                    unpaidRefundAdj += unpaidCount * unpaidInfo.per_pax_cost;
                }
            }
            if (unpaidRefundAdj > 0) {
                totalRefundAmount = Math.max(0, totalRefundAmount - unpaidRefundAdj);
                var adj = totalPaymentAmount - totalRefundAmount;
                if (adj) {
                    $('.total-before-service-commission-and-discount-change-people').text(currencyFormation.symbol + adj.toFixed(2));
                    $('.total-before-service-commission-and-discount-change-people').css('color', adj >= 0 ? '#4abd5d' : '#ff5e5e');
                } else {
                    $('.total-before-service-commission-and-discount-change-people').text(currencyFormation.symbol + '0.00').css('color', '#4abd5d');
                }
                // Flag so the validation-check bypass in validateAndSubmitFormChangePeople knows
                // to make the real update call (dont_refund) instead of showing success early.
                $(form).data('has-unpaid-deletion', cpPendingItemIds.length > 0 || cpPendingHistoryIds.length > 0);
                // When payment is still owed after removing unpaid pax, force Payment Link.
                // Payment method radios live in #modal_admin (not inside #changePeopleForm),
                // so $(form).find('input[name="payment_method"]') always returns empty.
                // Store the override as jQuery data so validateAndSubmitFormChangePeople can read it.
                // Force Payment Link path whenever unpaid pax are removed, including $0 net
                // (the PHP Payment Link path handles $tt=0 by cancelling the old link only).
                if (cpPendingItemIds.length > 0 || cpPendingHistoryIds.length > 0) {
                    $(form).data('cp-payment-method-override', 'Payment Link');
                }
            }
        }

        if (packageHasContacts == 1) {
            if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount > 0) {
                tourBookingInfoContacts.changePeopleResetDeleteContactPopup();
            } else {
                if (totalDeletedPeopleCount > 0 && totalAddedPeopleCount > 0) {
                    tourBookingInfoContacts.changePeopleResetDeleteContactPopup();
                    tourBookingInfoContacts.changePeopleEditContactsValidation(true);
                } else {
                    tourBookingInfoContacts.editBookingChangePeopleRenderNewContactsPopup(touristsGroups, rateGroups);
                }
            }
        }

        $hidRateGroup = $('#table-rate-groups-change-people .hid-rate-group');
        $('#payment_amount').val(totalPaymentAmount.toFixed(2));
        $('#refund_amount').val(totalRefundAmount.toFixed(2));
        $(form).data('validation_check', true);
        if (paymentMethod == 'Pay Later') {
            $(form).data('unpaid', true);
        }
        if (isGroupBooking == '1') {
            validateAndSubmitFormGroupChangePeople(form);
        } else {
            validateAndSubmitFormChangePeople(form);
        }

        return false;
    }
}

function validateAndSubmitFormChangePeople(form, additionalForm, paymentIntentId = '') {
    if (isGroupBooking == '1') {
        validateAndSubmitFormGroupChangePeople(form, additionalForm);
        return false;
    } else {
        var submitBtn = isAffiliate == 'Yes' ? $('#btn-make-payment')[0] : $('#btn-make-payment1')[0],
            includeValidationCheckField = false,
            includePaymentCheckField = false,
            formObj = $(form),
            formData;

        if (typeof additionalForm === "undefined") {
            includeValidationCheckField = true;
            submitBtn = $('#update_booking_change_people_submit')[0];
        } else {
            includeValidationCheckField = true;
            formObj = formObj.add(additionalForm);
        }

        // Payment method radios are in #modal_admin (not #changePeopleForm), so the .find() below
        // only returns a value when additionalForm merges that form in. For the first-pass call
        // (no additionalForm), use the override stored by showMakePaymentForEditBookingChangePeople.
        var cpMethodOverride = $(form).data('cp-payment-method-override');
        // cpMethodOverride is always preferred over the fallback paymentMethod (original booking),
        // including in the second call via resubmitWithRefundType (where additionalForm is #form_id,
        // not the payment modal, so the radio .find() returns nothing and the override must win).
        var effectivePaymentMethod = $(formObj).find('input[name="payment_method"]:checked').val()
            || cpMethodOverride
            || paymentMethod;

        if (effectivePaymentMethod == 'Pay Later') {
            includePaymentCheckField = true;
        }
        if (effectivePaymentMethod == 'Card' && $(form).data('validation_check') == false && finalAmount >= 0) {
            if (paymentIntentId == "") {
                var inputId = (isAffiliate == 'Yes') ? 'cardnumber' : 'cardnumber1';
                var inputIdCvv = (isAffiliate == 'Yes') ? 'cvv' : 'cvv1';
                if (!validateCardnumber({ inputId: inputId }))
                    return false;

                if (!validateCvv({ inputId: inputIdCvv }))
                    return false;

            }
        }
        if (effectivePaymentMethod == 'Cash' && $(form).data('validation_check') == false && finalAmount >= 0) {
            if (isAffiliate == 'No') {
                var paidCashAmount = $('#cash-paid-amount').val();
            } else if (isAffiliate == 'Yes') {
                var paidCashAmount = $('#cash-paid-amount1').val();
            }
            if (cashAmountChangePeople > 0 && paidCashAmount != '' && (paidCashAmount == 0 || Number(paidCashAmount) < Number(cashAmountChangePeople))) {
                swal('Paid amount should be greator than or equal to total', '', 'warning');
                return false;
            }
        }

        if (typeof changePeopleV2 !== 'undefined' && changePeopleV2.appendAddonsToForm) {
            changePeopleV2.appendAddonsToForm($(form));
        }

        formData = $(formObj).serialize();

        // Ensure payment_method is included in the form data (backend expects payment_method, not payment_type)
        if (formData.indexOf('payment_method=') === -1) {
            formData += '&payment_method='.concat(encodeURIComponent(effectivePaymentMethod));
        }

        // Add payment_intent_id if provided (from card reader)
        if (paymentIntentId && paymentIntentId !== '') {
            formData += '&payment_intent_id='.concat(encodeURIComponent(paymentIntentId));
        }

        if (packageHasContacts == 1) {
            formData = tourBookingInfoContacts.changePeopleDeleteAndNewContactsValidation(formData);

            if (!formData) {
                return false;
            }
        }

        // Add-on-only Change People edit that nets to a refund: show the same "Remove Passenger
        // Add-on(s)" popup the direct delete-pax-addons flow uses (tour-booking-info.blade.php's
        // #modal_pax_addon_delete_refund), instead of the tour-focused "Update Booking" modal.
        // #refund_Type lives inside #form_id, which validateAndSubmitFormChangePeople merges into
        // the real submission whenever an additionalForm is passed — so setting its value here and
        // resubmitting through the exact same function/args the blade's own confirm buttons use
        // (editBookingChangePeopleForm === this closure's `form`, additionalForm === #form_id) is
        // enough; #deleteBooking never needs to be shown or flagged for this path.
        function resubmitWithRefundType(refundType) {
            $('#refund_Type').val(refundType);
            $(form).data('validation_check', false);
            validateAndSubmitFormChangePeople(form, $('#form_id')[0]);
        }

        function mapPdrRefundType(pdrValue) {
            return pdrValue === 'ticket_refund' ? 'full_refund'
                : pdrValue === 'full_refund' ? '100_refund'
                    : 'dont_refund';
        }

        // A pure option downgrade (e.g. a size/quantity dropdown changed to a cheaper choice) —
        // shows the "Refund to Customer" modal the bulk pax-addon-edit flow uses
        // (#modal_addon_edit_refund, tour-booking-addons.js's TourBookingAddons._showRefundModal),
        // instead of the removal-focused "Remove Passenger Add-on(s)" modal.
        function showAddonEditRefundModal(breakdown) {
            var $refundModal = $('#modal_addon_edit_refund');

            $('#addon-edit-refund-breakdown').html(breakdown.html);
            $('input[name="addon_edit_refund_type"]').prop('checked', false);
            $('#addon-edit-refund-notes').val('');
            $('#addon-edit-refund-type-section').removeClass('hidden');
            $('#addon-edit-refund-info-only').addClass('hidden');
            $('#addon-edit-refund-title').text('Refund to Customer');
            $('#addon-edit-refund-subtitle').text('The changes result in a credit due to the customer');
            $('#addon-edit-refund-submit').text('Process Refund');

            $refundModal.modal('show');

            // TourBookingAddons._showRefundModal() binds this same button with vanilla
            // addEventListener (using its own clone-and-replace reset for ITS repeat calls) —
            // jQuery's .off() can't remove that listener. Replacing the node first strips ANY
            // prior handler (either mechanism), matching their own reset technique, so only this
            // flow's handler fires regardless of which flow opened the modal last.
            var btn = document.getElementById('addon-edit-refund-submit');
            var freshBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(freshBtn, btn);

            $(freshBtn).on('click', function () {
                var refundType = $('input[name="addon_edit_refund_type"]:checked').val();
                if (!refundType) {
                    swal('Please select a refund type.', '', 'warning');
                    return;
                }
                $refundModal.modal('hide');
                resubmitWithRefundType(mapPdrRefundType(refundType));
            });
        }

        // Every add-on this edit removes/changes is UNPAID (e.g. added via a payment link that's
        // still pending): nothing to give back, so — like deleting an unpaid pax — the popup offers
        // only "Don't Refund" (shared #modal_addon_edit_refund in its info-only mode). The backend
        // drops the pending link line(s) for them.
        function showAddonDontRefundOnlyModal(breakdown) {
            var $refundModal = $('#modal_addon_edit_refund');

            $('#addon-edit-refund-breakdown').html(breakdown.unpaidHtml);
            $('#addon-edit-refund-notes').val('');
            $('#addon-edit-refund-type-section').addClass('hidden');
            $('#addon-edit-refund-info-only').removeClass('hidden');
            $('#addon-edit-refund-title').text('Update Add-on');
            $('#addon-edit-refund-subtitle').text('No payment is recorded for this add-on — nothing will be refunded');

            var btn = document.getElementById('addon-edit-refund-submit');
            var freshBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(freshBtn, btn);
            $(freshBtn).text('Confirm Change');

            $refundModal.modal('show');

            $(freshBtn).on('click', function () {
                $refundModal.modal('hide');
                resubmitWithRefundType('dont_refund');
            });
        }

        function showAddonOnlyRefundPopup() {
            if (typeof changePeopleV2 === 'undefined') {
                processRefundNormalChangePeople(formData, form.action);
                return;
            }

            var editBreakdown = changePeopleV2.buildAddonEditRefundBreakdown
                ? changePeopleV2.buildAddonEditRefundBreakdown()
                : null;

            if (editBreakdown && editBreakdown.hasDowngrade) {
                showAddonEditRefundModal(editBreakdown);
                return;
            }

            if (!changePeopleV2.buildAddonRemovalBreakdown) {
                processRefundNormalChangePeople(formData, form.action);
                return;
            }

            var breakdown = changePeopleV2.buildAddonRemovalBreakdown();
            var $refundModal = $('#modal_pax_addon_delete_refund');

            // A stale handler from the unrelated direct delete-pax-addons flow would otherwise
            // reopen its own #paxAddonsListModal when this modal closes.
            $refundModal.off('hidden.bs.modal.pdr');

            if (!breakdown.hasPaidTxn) {
                // Nothing was actually paid on the removed/changed add-ons — confirm with a
                // "Don't Refund"-only popup (same idea as deleting an unpaid pax).
                if (editBreakdown && editBreakdown.hasUnpaidItem) {
                    showAddonDontRefundOnlyModal(editBreakdown);
                    return;
                }
                resubmitWithRefundType('dont_refund');
                return;
            }

            $('input[name="pax_delete_refund_type"]').prop('checked', false);
            $('#pax-delete-refund-notes').val('');
            $('#pax-refund-summary-bar').addClass('hidden');
            $('.pdr-option').css({ 'border-color': '', 'background-color': '' });
            $('#pax-delete-refund-breakdown').html(breakdown.html);

            $refundModal.modal({ backdrop: false, keyboard: true });
            $refundModal.modal('show');

            // Reuse the .pdr namespace the direct delete-pax-addons flow uses for this same modal
            // so whichever flow opened it most recently owns these handlers exclusively.
            $refundModal.off('change.pdr').on('change.pdr', 'input[name="pax_delete_refund_type"]', function (e) {
                var cs = (typeof currencyFormation !== 'undefined') ? currencyFormation.symbol : '$';
                var val = e.target.value;
                var refundAmt = val === 'full_refund' ? (breakdown.totalPrice + breakdown.totalFee)
                    : val === 'ticket_refund' ? breakdown.totalPrice
                        : 0;

                $('.pdr-option').css({ 'border-color': '#E5E7EB', 'background-color': '' });
                $(e.target).closest('.pdr-option').css({ 'border-color': '#3B82F6', 'background-color': '#EFF6FF' });

                $('#pax-refund-summary-amount').text(cs + refundAmt.toFixed(2));
                $('#pax-refund-summary-bar').removeClass('hidden');
                if (val === 'dont_refund') {
                    $('#pax-refund-summary-bar').css({ 'background': '#F9FAFB', 'border-color': '#E5E7EB' });
                    $('#pax-refund-summary-amount').css('color', '#6B7280');
                } else {
                    $('#pax-refund-summary-bar').css({ 'background': '#F0FDF4', 'border-color': '#86EFAC' });
                    $('#pax-refund-summary-amount').css('color', '#15803D');
                }
            });

            $('#pax-addon-delete-refund-submit').off('click.pdr').on('click.pdr', function () {
                var refundType = $('input[name="pax_delete_refund_type"]:checked').val();
                if (!refundType) {
                    swal('Please select a refund type.', '', 'warning');
                    return;
                }
                $refundModal.modal('hide');
                resubmitWithRefundType(mapPdrRefundType(refundType));
            });
        }

        function doSubmit() {
            submitBtn.disabled = true;

            showLoading();
            $.ajax({
                headers: { Accept: 'application/json' },
                dataType: 'json',
                type: 'POST',
                url: form.action,
                data: formData + (includeValidationCheckField ? ('&validation_check=' + $(form).data('validation_check')) : ''),
            }).done(function (response) {
            // CRITICAL FIX
            var refundAmountFromForm = (
                parseFloat(totalRefundAmount) - parseFloat(totalPaymentAmount)
            ).toFixed(2);
            
            var needsRefundModal = (refundAmountFromForm > 0 );

            // Only show immediate success if: no refund needed AND one of the bypass conditions is met.
            // paymentMethod == 'Pay Later' alone is NOT enough to bypass the payment-method modal —
            // a Split Booking-created booking also has $trans == null (so paymentMethod defaults to
            // 'Pay Later') but has a real, active pending payment link that a newly added pax's cost
            // must be collected into; only skip the modal here for a GENUINE Billable/Pay Later
            // booking, i.e. one with no pending remaining-payment link at all.
            if (!needsRefundModal && ((paymentMethod == 'Pay Later') || (totalInfantsOrChilds > 0 && totalPaymentAmount == 0 && totalRefundAmount == 0) || (totalPaymentAmount.toFixed(2) == totalRefundAmount.toFixed(2)))) {
                if ($(form).data('has-unpaid-deletion')) {
                    // Unpaid pax deletion — the validation-only call just returned; now make the
                    // actual update call (dont_refund) which includes #form_id with pending_item_ids.
                    // Set skip flag so this call's .always() doesn't hide the loader that
                    // resubmitWithRefundType's doSubmit() is about to show for the second call.
                    $(form).data('has-unpaid-deletion', false);
                    $(form).data('skip-always-hide', true);
                    resubmitWithRefundType('dont_refund');
                } else {
                    hideLoading();
                    swal('Booking updated successfully', 'Page will reload', 'success')
                        .then(function () {
                            location = location;
                        });
                }
            } else {
                if ($(form).data('validation_check')) {
                    submitBtn.disabled = false;
                    // Show Delete Booking Modal when there's a refund needed (regardless of people count)
                    if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount > 0) {
                        if (packageHasContacts == 1) {
                            showLoading();
                            tourBookingInfoContacts.changePeopleEditContactsValidation();
                        } else {
                            hideLoading(); // Hide loader when showing modal
                            $('#deleteBookingTitle').text('Update Booking');
                            // Clear any people rows still checked from an earlier, abandoned
                            // direct "Edit Booking" delete attempt — #deleteBooking's add-ons
                            // block (tour-booking-info.blade.php) reads .ads_Checkbox:checked
                            // first to scope itself to exact people, and stale checks left over
                            // from that other flow would otherwise be mistaken for this one's.
                            $('.ads_Checkbox').prop('checked', false);
                            $('#deleteBooking').data('call-by-edit-booking-change-people', true).modal('show');
                        }
                    } else if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount == 0 && finalAmount < 0) {
                        // No passengers added or removed at all — this edit only touched add-ons,
                        // and it nets to a refund. Previously this fell through to the silent
                        // processRefundNormalChangePeople() path below with no refund_type ever
                        // asked for, which the backend then defaulted to a fixed guess. Show the
                        // dedicated "Remove Passenger Add-on(s)" popup (same one the direct
                        // delete-pax-addons flow uses) instead of the generic "Update Booking"
                        // modal, since there's no tour/pax breakdown to review here — just add-ons.
                        hideLoading();
                        showAddonOnlyRefundPopup();
                    } else {
                        if (packageHasContacts == 1) {
                            $('#edit_booking_add_contacts').data('prevent-hide-callback', true).modal('hide');

                            if (totalDeletedPeopleCount > 0) {
                                var contactIds = $("input[name='deleted_contact_id[]']").map(function () {
                                    if ($(this).val()) {
                                        return $(this).val();
                                    }
                                }).get();

                                if (contactIds.length == 0) {
                                    return false;
                                }
                            }
                        }
                        if (finalAmount >= 0) {
                            hideLoading(); // Hide loader when showing payment modals
                            if (isAffiliate == 'Yes') {
                                $('#modal_affilate').data('call-by-edit-booking-change-people', true).modal('show');
                                if (paymentMethod == 'Cash') {
                                    $('#cash-paid-amount1').prop("readonly", false);
                                } else {
                                    $('#cash-paid-amount1').prop("readonly", true);
                                }
                                $('#model_affiliate .split-payment-div').hide();
                            } else {
                                $('#modal_admin').data('call-by-edit-booking-change-people', true).modal('show');
                                if (paymentMethod == 'Cash') {
                                    $('#cash-paid-amount').prop("readonly", false);
                                } else {
                                    $('#cash-paid-amount').prop("readonly", true);
                                }
                                $('#modal_admin .split-payment-div').hide();
                            }
                        } else if (finalAmount < 0) {
                            // "Pay Later" has collected zero payment (confirmed: every
                            // reproduction of bookings 348578-348585, tenant_kens had zero
                            // `transactions` rows) — there is nothing to refund regardless of
                            // what finalAmount computes to, so skip the automatic resubmission
                            // entirely instead of sending it; treat it like the bypass above.
                            // Card/Cash is left as the original, pre-existing behavior below —
                            // a targeted fix was attempted here but reverted, since it turned out
                            // to be built on an incorrect assumption (that the first call already
                            // persists the add-on selection) that caused a confirmed data-loss
                            // regression elsewhere; this path remains untested either way.
                            if (paymentMethod == 'Pay Later') {
                                hideLoading();
                                swal('Booking updated successfully', 'Page will reload', 'success')
                                    .then(function () {
                                        location = location;
                                    });
                            } else {
                                // Payment Link additions never need a Card-transaction selector.
                                // Pass effectivePaymentMethod so the .done handler can show success
                                // even when the original booking paymentMethod is 'Card'.
                                processRefundNormalChangePeople(formData, form.action, effectivePaymentMethod === 'Payment Link', effectivePaymentMethod);
                            }
                        }
                    }
                    $('#edit_booking_change_people').data('prevent-hide-callback', true).modal('hide');
                } else {
                    var pathname = window.location.href;
                    var spliturlCheck = pathname.slice(-7);
                    var splitslice = spliturlCheck.slice(0, 5);

                    if (effectivePaymentMethod == 'Payment Link') {
                        if (response.errors && response.errors.length) {
                            if (typeof (response.errors) != "undefined") {
                                hideLoading();
                                if (typeof (response.errors_message) != "undefined") {
                                    response.errors = response.errors_message;
                                }
                                swal('Error(s) Occurred', response.errors, 'error');
                                showErrorsInHistory(response);
                                submitBtn.disabled = false;
                            }
                        } else {
                            swal(response.message || 'Payment link sent successfully.', 'Page will reload', 'success')
                                .then(function () { location = location; });
                        }
                    } else if (effectivePaymentMethod == 'Card') {
                        if (response.errors && response.errors.length) {
                            if (isAffiliate == 'Yes') {
                                $('#btn-make-payment')[0].disabled = false;
                            } else {
                                $('#btn-make-payment1')[0].disabled = false;
                            }

                            if (response.errors.indexOf('payment') != -1)
                                swal('Payment initialization error', response.errors_message, 'error');

                            if (typeof (response.errors) != "undefined") {
                                hideLoading();
                                if (typeof (response.errors_message) != "undefined") {
                                    response.errors = response.errors_message;
                                }
                                swal('Error(s) Occurred', response.errors, 'error');
                                showErrorsInHistory(response);
                                submitBtn.disabled = false;
                            }

                        } else {
                            if (typeof (response.require3ds) != "undefined" && response.require3ds == '1') {
                                window.location.href = response.nextAction.redirect_to_url.url;
                            } else {
                                swal('Booking updated successfully', 'Page will reload', 'success')
                                    .then(function () {
                                        if (splitslice == "split") {
                                            pathname = pathname.split('?')[0];
                                            location = pathname;
                                        } else {
                                            location = location;
                                        }

                                    });
                            }
                        }
                    } else {
                        if (effectivePaymentMethod == 'Cash') {
                            if (isAffiliate == 'No') {
                                var paidCashAmount = $('#cash-paid-amount').val();
                            } else if (isAffiliate == 'Yes') {
                                var paidCashAmount = $('#cash-paid-amount1').val();
                            }
                            if (Number(cashAmountChangePeople) > 0 && paidCashAmount > 0 && Number(paidCashAmount) >= Number(cashAmountChangePeople)) {
                                var changeAmount = paidCashAmount - cashAmountChangePeople;
                                if (changeAmount > 0) {

                                    var elemdiv = document.createElement("div");
                                    elemdiv.classList.add("swal-title");
                                    let message = `Change<br>${currency(changeAmount).format()}`;
                                    let amountColor = '#15803D';
                                    elemdiv.innerHTML = `
                                                                                                <div style="
                                                                                                        font-size: 26px;
                                                                                                        font-weight: 600;
                                                                                                        color: ${amountColor};
                                                                                                ">
                                                                                                        ${message}
                                                                                                </div>
                                                                                                <div class="swal-title">
                                                                                                        Booking Payment Done.
                                                                                                </div>
                                                                                        `;
                                    swal({
                                        icon: "success",
                                        content: elemdiv
                                    }).then(function () {
                                        location = location;
                                    })
                                } else {
                                    swal('Booking updated successfully', 'Page will reload', 'success')
                                        .then(function () {
                                            location = location;
                                        });
                                }

                            } else {
                                swal('Booking updated successfully', 'Page will reload', 'success')
                                    .then(function () {
                                        location = location;
                                    });
                            }
                        } else {
                            swal('Booking updated successfully', 'Page will reload', 'success')
                                .then(function () {
                                    location = location;
                                });
                        }
                    }
                }
            }
        })
            .fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
                submitBtn.disabled = false;
            })
            .always(function() {
                // Skip hiding the loader when resubmitWithRefundType was just triggered —
                // it already called showLoading() for the second AJAX and will hide it via
                // its own .always(). Hiding here would kill the second call's loader early.
                if ($(form).data('skip-always-hide')) {
                    $(form).removeData('skip-always-hide');
                    return;
                }
                // Only skip hiding loading if we're calling processRefundNormalChangePeople
                // (when validation_check=true, finalAmount < 0, and not showing delete booking modal)
                var isShowingDeleteModal = ($(form).data('validation_check') &&
                                          totalAddedPeopleCount == 0 &&
                                          totalDeletedPeopleCount > 0);
                var isCallingProcessRefund = ($(form).data('validation_check') &&
                                            finalAmount < 0 &&
                                            !isShowingDeleteModal);

                if (!isCallingProcessRefund) {
                    hideLoading();
                }
            });
        } // end doSubmit

        // Prefer changePeopleV2's own seat accounting — it's the SAME math already shown in the
        // "X of Y seats left" header, so it can never disagree with what the user sees. The legacy
        // netChange/emptySeatsOfSlot path depends on showSlotInfoChangePeople() having run and
        // correctly recomputed emptySeatsOfSlot for THIS booking; it has an early-return branch
        // (unselected slot) and other edge cases that can leave emptySeatsOfSlot stuck at its
        // default 0, which falsely reports "overbooked" on any add even when seats are free.
        var hasV2Seats = (typeof changePeopleV2 !== 'undefined' && changePeopleV2.totalSeatsForBooking);
        var overbooked = hasV2Seats
            ? (changePeopleV2.getTotalPaxCount() - changePeopleV2.totalSeatsForBooking)
            : (totalAddedPeopleCount - totalDeletedPeopleCount - totalAddedInfantsCount - emptySeatsOfSlot);

        if (overbooked > 0 && $(form).data('validation_check') === true) {
            if (userType == 'Affiliate') {
                swal('Sorry!', 'You are not allowed to overbook', 'warning');
                return false;
            }
            var addText = overbooked > 0
                ? ' This slot will be overbooked by ' + overbooked + ' seats.'
                : ' This slot will be full.';
            addText += ' Do you want to proceed?';
            swal({
                title: 'An overbooking event',
                text: addText,
                icon: 'warning',
                dangerMode: true,
                buttons: {
                    no: { text: 'Cancel', value: false },
                    yes: { text: 'Proceed', value: true, className: 'btn-danger' }
                }
            }).then(function (result) {
                if (result) {
                    doSubmit();
                }
            });
            return false;
        }

        doSubmit();
        return false;
    }
}

function processRefundNormalChangePeople(formData, url, skipTransactionSelection, effectiveMethod) {
    if (!skipTransactionSelection && paymentMethod == 'Card' && paymentTransCount > 1) {
        hideLoading(); // Hide loader when showing modal
        $('#updateBooking').data('call-by-edit-booking-change-people', true).modal('show');
        return false;
    }
    var includeValidationCheckField = false;
    // effectiveMethod may differ from paymentMethod when the mixed-unpaid path overrides
    // the method to 'Payment Link' even though the original booking used Card.
    var resolvedMethod = effectiveMethod || paymentMethod;
    // Don't call showLoading() here as the parent method already has loader active
    $.ajax({
        headers: { Accept: 'application/json' },
        dataType: 'json',
        type: 'POST',
        url: url,
        data: formData + '&validation_check=' + false,
    })
        .done(function (response) {
            if (resolvedMethod != 'Card' || paymentTransCount == 1) {
                var successMsg = (response && response.message) ? response.message : 'Booking updated successfully';
                swal(successMsg, 'Page will reload', 'success')
                    .then(function () {
                        location = location;
                    });
            }
        })
        .fail(function (jqXHR) {
            showSubmissionError(jqXHR);
            //submitBtn.disabled = false;
        })
        .always(hideLoading);
}

function applyDiscountChangePeople() {
    var discount = 0;
    var discount_card = 0;
    var discount_cash = 0;
    var serviceCommission = 0;
    if (Number(bookingId) < Number(oldBookingId)) {
        if (isAffiliate == 'Yes') {
            var span_invoiced = finalAmount;
            var span_cash = finalAmount;
            var span_card = finalAmount;
        } else {
            var span_invoiced = finalAmount;
            var span_cash = finalAmount;
            serviceCommission = roundout((finalAmount * serviceCommissionPercentage / 100), 2);
            var span_card = finalAmount + serviceCommission;
        }
    } else {
        var span_invoiced = finalAmount;
        var span_cash = finalAmount;
        if (getDiscountStatus(finalAmount) > 0) {
            serviceCommission = roundout((finalAmount * serviceCommissionPercentage / 100), 2);
        } else {
            serviceCommission = serviceCommissionTotal;
        }
        var span_card = finalAmount + serviceCommission;
    }

    if (($('#discount_type_percentage').length > 0 && $('#discount_type_fixed_money').length > 0) && ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {
        if ($('#discount_type_percentage')[0]?.checked) {
            var inputVal = parseFloat($('#discount_percentage').val());

            if (isNaN(inputVal)) // blank or something else
                inputVal = 0;

            discount = (span_invoiced * inputVal) / 100;
            discount_cash = (span_cash * inputVal) / 100;
            // discount_card = (span_card * inputVal) / 100;
            if (Number(bookingId) < Number(oldBookingId)) {
                discount_card = (span_card * inputVal) / 100;
            } else {
                discount_card = span_cash * inputVal / 100;
            }
            if (discount < 0 || discount_cash < 0 || discount_card < 0) {
                $('#discount_percentage').val('');
                applyDiscountChangePeople();
            }

            if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
                $('#pr_amount').val(discount);
            }
            if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
                $('#pr_amount').val(discount_cash);
            }
            if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
                $('#pr_amount').val(discount_card);
            }
        } else {
            var inputVal = parseFloat($('#discount_fixed_money').val());

            if (isNaN(inputVal)) {
                discount = discount_cash = discount_card = 0;
            } else {
                discount = discount_cash = discount_card = inputVal;
            }

            $('#pr_amount').val(discount);
        }
    }

    if (discount_cash > span_cash || discount > span_invoiced) {
        $('#discount_fixed_money').val('');
        $('#discount_percentage').val('');
        discount = 0;
        discount_cash = 0;
    }

    span_invoiced = span_invoiced - discount;
    span_cash = span_cash - discount_cash;
    if (Number(bookingId) < Number(oldBookingId)) {
        span_card = span_card - discount_card;
    } else {
        span_card = span_cash + roundout((span_cash * natServiceCommission / 100), 2);
        if (isCashFees == '1') {
            span_cash = span_card;
        }
    }

    if (isAffiliate == 'Yes') {
        var pendingAmountAffiliate = parseFloat($('#modal_affilate').data('pending-amount')) || 0;
        var aff_invoiced = span_invoiced + pendingAmountAffiliate;
        var aff_cash     = span_cash     + pendingAmountAffiliate;
        var aff_card     = span_card     + pendingAmountAffiliate;
        // Cash due = exactly what "Pay with Cash" shows (incl. cash fees + pending link amount),
        // so the paid-amount check and the "Change" popup use the same figure the admin sees.
        cashAmountChangePeople = aff_cash.toFixed(2);
        $('#span_invoiced').text(currencyFormation.symbol + aff_invoiced.toFixed(2));
        $('#span_cash').text(currencyFormation.symbol + aff_cash.toFixed(2));
        $('#span_card').text(currencyFormation.symbol + aff_card.toFixed(2));
        $('.link-cp-affiliate-amount').text(currencyFormation.symbol + aff_card.toFixed(2));
        if (pendingAmountAffiliate > 0) {
            $('#pending-items-summary-affiliate').removeClass('hidden');
            $('#pending-items-amount-affiliate').text(currencyFormation.symbol + pendingAmountAffiliate.toFixed(2));
        } else {
            $('#pending-items-summary-affiliate').addClass('hidden');
        }
    } else {
        // Combine with any pending payment link amount so every option shows the true total
        var pendingAmountCpDisplay = parseFloat($('#modal_admin').data('pending-amount')) || 0;
        var display_invoiced = span_invoiced + pendingAmountCpDisplay;
        var display_cash    = span_cash    + pendingAmountCpDisplay;
        var display_card    = span_card    + pendingAmountCpDisplay;
        // Cash due = exactly what "Pay with Cash" shows (incl. cash fees + pending link amount),
        // so the paid-amount check and the "Change" popup use the same figure the admin sees.
        cashAmountChangePeople = display_cash.toFixed(2);

        $('.invoiced-amount').text(currencyFormation.symbol + display_invoiced.toFixed(2));
        $('.cash-amount').text(currencyFormation.symbol + display_cash.toFixed(2));
        $('.card-amount').text(currencyFormation.symbol + display_card.toFixed(2));
        $('.link-cp-amount').text(currencyFormation.symbol + display_card.toFixed(2));

        var selectedMethod = $('#modal_admin input[name="payment_method"]:checked').val() || paymentMethod;
        if (selectedMethod == 'Cash') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_cash.toFixed(2));
        } else if (selectedMethod == 'Card') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_card.toFixed(2));
        } else if (selectedMethod == 'Invoiced') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_invoiced.toFixed(2));
        } else if (selectedMethod == 'Payment Link') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_card.toFixed(2));
        } else if (paymentMethod == 'Cash') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_cash.toFixed(2));
        } else if (paymentMethod == 'Card') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_card.toFixed(2));
        } else if (paymentMethod == 'Invoiced') {
            $('#modal_admin .set-total').text(currencyFormation.symbol + display_invoiced.toFixed(2));
        }

        // Show/hide the pending items summary row
        if (pendingAmountCpDisplay > 0) {
            $('#pending-items-summary-cp').removeClass('hidden');
            $('#pending-items-amount-cp').text(currencyFormation.symbol + pendingAmountCpDisplay.toFixed(2));
            $('#btn-cp-payment-breakdown').removeClass('hidden');
        } else {
            $('#pending-items-summary-cp').addClass('hidden');
            $('#btn-cp-payment-breakdown').addClass('hidden');
        }
    }

    if ($('input[type=radio][name=payment_method]:checked').val() == 'Pay Later') {
        $('#htt').val(span_invoiced);
        $('#totalcost').text(currencyFormation.symbol + span_invoiced.toFixed(2));
    }

    if ($('input[type=radio][name=payment_method]:checked').val() == 'Cash') {
        $('#htt').val(span_cash);
        $('#totalcost').text(currencyFormation.symbol + span_cash.toFixed(2));
    }

    if ($('input[type=radio][name=payment_method]:checked').val() == 'Card') {
        // For non-affiliate bookings, display_card already includes any pending link amount.
        // Use it for the card reader and hidden total so they both charge the combined amount.
        var effectiveCardTotal = (typeof display_card !== 'undefined') ? display_card : span_card;
        $('#htt').val(effectiveCardTotal);
        $('#totalcost').text(currencyFormation.symbol + effectiveCardTotal.toFixed(2));
        backendCardReaderInfo.getTotal(effectiveCardTotal);
        backendCardReaderInfo.getFees(serviceCommission);
    }

    if ($('input[type=radio][name=payment_method]:checked').val() == 'Payment Link') {
        var linkTotal = (isAffiliate == 'Yes' && typeof aff_card !== 'undefined') ? aff_card : span_card;
        $('#htt').val(linkTotal);
        $('#totalcost').text(currencyFormation.symbol + linkTotal.toFixed(2));
    }

}

function roundout(amount, places = 2) {
    if (places < 0) places = 0;
    let factor = Math.pow(10, places);
    return Math.round(amount * factor) / factor;
}

function getDiscountStatus(groupTotal) {
    var discountTotal = 0;

    if (($('#discount_type_percentage').length > 0 && $('#discount_type_fixed_money').length > 0) && ($('#discount_type_percentage')[0]?.checked || $('#discount_type_fixed_money')[0]?.checked)) {

        if ($('#discount_type_percentage')[0]?.checked) {

            var inputVal = parseFloat($('#discount_percentage').val());

            if (isNaN(inputVal)) // blank or something else
                inputVal = 0;

            discountTotal = (groupTotal * inputVal) / 100;
        } else {
            var inputVal = parseFloat($('#discount_fixed_money').val());

            if (isNaN(inputVal))
                discountTotal = 0;
            else
                discountTotal = inputVal;
        }
    }

    return discountTotal;
}
