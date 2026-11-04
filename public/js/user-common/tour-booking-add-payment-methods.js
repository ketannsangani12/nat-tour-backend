class PaymentHandler {
    constructor() {
        this.currencyFormation = currencyFormation || { symbol: '$' };
        this.cartPackages = window.cartPackages || [];
        this.cartView = parseInt(window.cartView) || 0;
        this.isPartnerPackage = parseInt(window.isPartnerPackage) || 0;
        this.cartTotal = window.cartTotal || 0;
        this.totalCartServiceCommission = window.totalCartServiceCommission || 0;
        this.cashFees = parseInt(window.cashFees) || 0;
        this.cashPriceEnabled = parseInt(window.cashPriceEnabled) || 0;
        this.checkAffiliate = window.checkAffiliate || 0;
    }

    initialize() {
        this.setupEventListeners();
        this.initializeAffiliate();
    }

    // Helper method to check if split payment should be shown
    shouldShowSplitPayment() {
        // Don't show if there are custom charges or addons
        if (totalCustomChargeAmount > 0 || totalCartAddonsPrice > 0 || customChargeTotal > 0 || totalCartTipAmount > 0) {
            return false;
        }

        // Don't show if pax add-ons are selected in the form (these don't update totalCartAddonsPrice)
        const formAddonsPrice = (typeof addonFormReader !== 'undefined' ? addonFormReader.getPrice() : 0)
            + (typeof initTourBookingAddCheckoutAddons !== 'undefined' ? initTourBookingAddCheckoutAddons.getPrice() : 0);
        if (formAddonsPrice > 0) {
            return false;
        }

        // Don't show if it's a partner package - check both window and global scope
        const partnerPackageValue = typeof isPartnerPackage !== 'undefined' ? isPartnerPackage : window.isPartnerPackage;
        if (partnerPackageValue == 1) {
            return false;
        }

        // Don't show if multiple packages in cart
        if (this.cartView == 1 && window.cartPackages && window.cartPackages.length > 1) {
            return false;
        }

        // Don't show if cart has a partner package
        if (this.cartView == 1 && window.cartPackages && window.cartPackages.length > 0 && window.cartPackages[0].is_partner_package == 1) {
            return false;
        }

        return true;
    }

    setupEventListeners() {
        document.querySelectorAll('.btn-slide-down').forEach(button => {
            button.addEventListener('click', this.handleSlideToggle.bind(this));
        });

        document.querySelectorAll('input[name="payment_method"]').forEach(input => {
            input.addEventListener('change', this.handlePaymentMethodChange.bind(this));
        });

        document.querySelectorAll('.checkset').forEach(check => {
            check.addEventListener('click', this.handleChecksetClick.bind(this));
        });

        document.querySelectorAll('input[name="split_value"]').forEach(input => {
            input.addEventListener('keyup', this.handleSplitValueInput.bind(this));
        });

        document.querySelector('#btn-make-payment').addEventListener('click', this.handlePaymentValidation.bind(this));

        if (totalCustomChargeAmount === 0 && totalCartAddonsPrice === 0 || totalCartTipAmount === 0) {
            const element = document.querySelector('#dividedBy');

            if (element) {
                element.addEventListener('change', this.handleSplitOptionChange.bind(this));
            }
        }
    }

    initializeAffiliate() {
        if (this.checkAffiliate === 1) {
            document.querySelector('#affiliatecust').dispatchEvent(new Event('change'));
        }
    }

    handleSlideToggle(event) {
        const button = event.currentTarget;
        const packageContainer = button.closest('.tourist-package');
        button.classList.toggle('active');
        packageContainer.querySelector('.tourist-package-age-group-list').style.display =
            packageContainer.querySelector('.tourist-package-age-group-list').style.display === 'none' ? 'block' : 'none';
        packageContainer.querySelector('.tourist-package-total-cost').style.display =
            packageContainer.querySelector('.tourist-package-total-cost').style.display === 'none' ? 'block' : 'none';
    }

    getParsedAmount(selector, defaultValue = 0.0) {
        const element = document.querySelector(selector);
        if (!element) {
            return defaultValue;
        }
        const value = parseFloat(element.value);
        return isNaN(value) ? defaultValue : value;
    }

    getTextAmount(selector) {
        const text = document.querySelector(selector).textContent;
        const value = parseFloat(text.replace(this.currencyFormation.symbol, ''));
        return isNaN(value) ? 0.0 : value;
    }

    getCashDisplayTotal(defaultTotal) {
        if (this.cashPriceEnabled == 1) {
            let cashTotal = null;
            if (typeof window.cashTotalAfterDiscountAndServiceCommission !== 'undefined') {
                cashTotal = parseFloat(window.cashTotalAfterDiscountAndServiceCommission);
            } else if (typeof lookupAfterDiscountAndServiceCommissionCash !== 'undefined') {
                cashTotal = parseFloat(lookupAfterDiscountAndServiceCommissionCash);
            } else {
                cashTotal = this.getTextAmount('#M');
            }
            if (!isNaN(cashTotal) && cashTotal > 0) {
                return cashTotal;
            }
        }
        return defaultTotal;
    }

    updatePackageDisplay(packageId, addonPrice, fee, total, customCharge, tipAmount) {
        const addonEl = document.querySelector(`#package-addons-${packageId}`);
        if (addonEl) addonEl.textContent = `${this.currencyFormation.symbol}${Number(addonPrice).toFixed(2)}`;

        const feeEl = document.querySelector(`#package-fees-${packageId}`);
        if (feeEl) feeEl.textContent = `${this.currencyFormation.symbol}${Number(fee).toFixed(2)}`;

        const totalEl = document.querySelector(`#package-totalcost-${packageId}`);
        if (totalEl) totalEl.textContent = `${this.currencyFormation.symbol}${Number(total).toFixed(2)}`;

        const customChargeEl = document.querySelector(`#package-custom-charge-${packageId}`);
        if (customChargeEl) customChargeEl.textContent = `${this.currencyFormation.symbol}${Number(customCharge).toFixed(2)}`;

        const tipEl = document.querySelector(`#package-tip-${packageId}`);
        if (tipEl) tipEl.textContent = `${this.currencyFormation.symbol}${Number(tipAmount).toFixed(2)}`;
    }

    handlePaymentMethodChange(event) {
        const paymentMethod = event.target.value;

        if (this.cartView != 1) {
            this.handleNewPricingOnPaymentMethodChange(paymentMethod);
        }

        if (this.cartView == 1 && $('#slot_time').val() == 0) {
            this.handleNewPricingOnPaymentMethodChangeCart(paymentMethod);
        }

        const customCharge = this.getParsedAmount('.custom-charge-');
        const addonsAmount = this.getParsedAmount('.addons-price-input');
        const tipAmount = this.getParsedAmount('.tip-amount-input');
        const totalBeforeCommission = this.getTextAmount('.total-after-discount-before-service-commission');
        const totalAfterCommission = this.getTextAmount('.total-after-discount-and-service-commission');

        const total = totalBeforeCommission + customCharge + addonsAmount + tipAmount;
      
        if (paymentMethod === 'Pay Later') {
            this.handlePayLater(total, customCharge, addonsAmount);
        } else if (paymentMethod === 'Cash' && this.cashFees !== 1) {
            this.handleCashPayment(total, customCharge, addonsAmount);
        } else if (paymentMethod === 'Card' || (this.cashFees === 1 && paymentMethod === 'Cash')) {
            this.handleCardPayment(customCharge, addonsAmount);
        } else if (paymentMethod === 'Link') {
            this.handleLinkPayment(customCharge, addonsAmount);
        }

        if (paymentMethod !== 'Pay Later') {
            const cashBase = (this.cashFees == 1) ? totalAfterCommission : total;
            const payWithCashTotal = this.getCashDisplayTotal(cashBase);
            const payWithCashEl = document.querySelector('#pcash .cash-set');
            if (payWithCashEl) {
                payWithCashEl.textContent = `${this.currencyFormation.symbol}${payWithCashTotal.toFixed(2)}`;
            }
            const mEl = document.querySelector('#M');
            if (mEl) {
                mEl.textContent = `${this.currencyFormation.symbol}${payWithCashTotal.toFixed(2)}`;
            }
        }

        // Update split values based on new payment method
        const isSplitChecked = document.querySelector('input[name="split_checkbox"]:checked');

        if (totalCustomChargeAmount === 0 && totalCartAddonsPrice === 0 && totalCartTipAmount === 0) {
            this.handleSplitCalculation(paymentMethod, isSplitChecked);
            this.updateSplitVisibility(isSplitChecked, paymentMethod);
        }
    }

    handlePayLater(total, customCharge, addonsAmount) {
        if (totalCustomChargeAmount === 0 && totalCartAddonsPrice === 0 && totalCartTipAmount === 0) {
            document.querySelector('#split-payment-div').style.display = 'none';
            document.querySelector('#dividedBy').value = 'Please select option';
        }
        document.querySelector('#feescard').style.display = 'none';
        document.querySelector('#div1').style.display = 'none';
        document.querySelector('#pcard').style.display = 'none';
        document.querySelector('#div2').style.display = 'block';
        document.querySelector('#feescash').style.display = 'block';
        document.querySelector('#pcash').style.display = 'none';
        // document.querySelector('#dividedBy').value = 'Please select option';
        document.querySelector('#payment-link-section').style.display = 'none';
        document.querySelector('#make-payment').style.display = 'block';
        document.querySelector('.make-payment').classList.remove('d-none');

        const cashDisplayTotal = this.getCashDisplayTotal(total);
        const payLaterEl = document.querySelector('#pay_later');
        if (payLaterEl) {
            payLaterEl.textContent = `${this.currencyFormation.symbol}${total.toFixed(2)}`;
        }
        const div2CashEl = document.querySelector('#div2 .cash-set');
        if (div2CashEl) {
            div2CashEl.textContent = `${this.currencyFormation.symbol}${total.toFixed(2)}`;
        }
        $('#pcash .cash-set').text(`${this.currencyFormation.symbol}${total.toFixed(2)}`);

        if (this.cashFees === 1) {
            // #M is already set correctly (with service fee) by updateUIWithDiscount
            // via the click handler — do not overwrite it here
        } else {
            document.querySelectorAll('span.cash-set').forEach(span => {
                if (span.id === 'pay_later') return;
                span.textContent = `${this.currencyFormation.symbol}${cashDisplayTotal.toFixed(2)}`;
            });
            const mEl = document.querySelector('#M');
            if (mEl) {
                mEl.textContent = `${this.currencyFormation.symbol}${cashDisplayTotal.toFixed(2)}`;
            }
        }

        if (totalCustomChargeAmount === 0 && totalCartAddonsPrice === 0 && totalCartTipAmount === 0) {
            this.handleSplitCheckboxForPayLater();
        }

        this.updateCartPackages(customCharge, 0);

    }

    handleCashPayment(total, customCharge, addonsAmount) {
        // Show/hide split payment based on conditions
        const splitPaymentDivEl = document.querySelector('split-payment-div');
        if (splitPaymentDivEl) {
            document.querySelector('#split-payment-div').style.display = this.shouldShowSplitPayment() ? 'block' : 'none';
        }
        document.querySelector('#feescard').style.display = 'none';
        document.querySelector('#div1').style.display = 'none';
        document.querySelector('#pcard').style.display = 'none';
        document.querySelector('#div2').style.display = 'block';
        document.querySelector('#feescash').style.display = 'block';
        document.querySelector('#pcash').style.display = 'block';
        document.querySelector('#payment-link-section').style.display = 'none';
        document.querySelector('#make-payment').style.display = 'block';
        document.querySelector('.make-payment').classList.remove('d-none');

        document.querySelectorAll('div.cash-set, span.cash-set').forEach(elem => {
            if (elem.id === 'pay_later') return;
            elem.textContent = `${this.currencyFormation.symbol}${total.toFixed(2)}`;
        });

        const feeSetElement = document.querySelector('div.fee-set');
        if (feeSetElement) {
            feeSetElement.textContent = '0.00';
        }

        if (document.querySelector('input[name="split_checkbox"]:checked')) {
            document.querySelector('#pcash').style.display = 'none';
        }

        this.updateCartPackages(customCharge, 0);

    }

    handleCardPayment(customCharge, addonsAmount) {
        // Show/hide split payment based on conditions
        const splitPaymentDivEl = document.querySelector('split-payment-div');
        if (splitPaymentDivEl) {
            document.querySelector('#split-payment-div').style.display = this.shouldShowSplitPayment() ? 'block' : 'none';
        }
        document.querySelector('#div2').style.display = 'none';
        document.querySelector('#feescash').style.display = 'none';
        document.querySelector('#pcash').style.display = 'none';
        document.querySelector('#div1').style.display = 'block';
        document.querySelector('#feescard').style.display = 'block';
        document.querySelector('#pcard').style.display = 'block';
        document.querySelector('#payment-link-section').style.display = 'none';
        document.querySelector('#make-payment').style.display = 'block';
        document.querySelector('.make-payment').classList.remove('d-none');

        if (document.querySelector('input[name="split_checkbox"]:checked')) {
            document.querySelector('#pcard').style.display = 'none';
        }

        this.updateCartPackages(customCharge, null, true);
    }

    handleLinkPayment(customCharge, addonsAmount) {
        // Hide split payment for Link payment method
        const splitPaymentDivEl = document.querySelector('split-payment-div');
        if (splitPaymentDivEl) {
            document.querySelector('#split-payment-div').style.display = 'none';
        }
        document.querySelector('#div2').style.display = 'none';
        document.querySelector('#pcash').style.display = 'none';
        document.querySelector('#div1').style.display = 'block';
        document.querySelector('#feescard').style.display = 'block';
        document.querySelector('#feescash').style.display = 'none';
        document.querySelector('#pcard').style.display = 'block';
        document.querySelector('#payment-link-section').style.display = 'block';
        document.querySelector('#make-payment').style.display = 'none';

        if (document.querySelector('input[name="split_checkbox"]:checked')) {
            document.querySelector('#pcard').style.display = 'none';
        }

        this.updateCartPackages(customCharge, null, true);
    }

    handleNewPricingOnPaymentMethodChange(paymentMethod) {
        if (isGroupRateEnabled == '1') {
            const isCash = (paymentMethod == 'Cash' && cashPriceEnabled == 1);

            $('#table-rate-groups tr').each(function () {
                const $selTourists = $(this).find('.tourists');
                const selectedVal = parseInt($selTourists.val() || 0);

                // Clear and fully rebuild options with appropriate rates (same as addTouristsOptsForGroupRates)
                $selTourists.empty();
                $selTourists.append($('<option>', { value: 0, text: currencyFormation.symbol + '0.00 - 0' }));
                for (var a = 1; a <= totalSeatsForBooking; a++) {
                    if (paxWiseGroupRates[a] !== undefined && paxWiseGroupRateIds[a] !== undefined) {
                        const rate = (isCash && paxWiseGroupCashRates && paxWiseGroupCashRates[a] !== undefined)
                            ? paxWiseGroupCashRates[a]
                            : paxWiseGroupRates[a];
                        const $opt = $('<option>', {
                            value: a,
                            text: currencyFormation.symbol + parseFloat(rate).toFixed(2) + ' - ' + a
                        });
                        $opt.attr('data-rategrp', paxWiseGroupRateIds[a]);
                        $selTourists.append($opt);
                    }
                }

                // Reinit select2 to pick up rebuilt options, then restore selection
                $selTourists.select2('destroy').select2();
                if (selectedVal > 0) {
                    $selTourists.val(selectedVal).trigger('change.select2');
                }
            });

            updateGroupRatesTable();
            return;
        } else {
            const isCash = (paymentMethod == 'Cash' && this.cashPriceEnabled == 1);
            $('#table-rate-groups tr').each(function (index) {
                const row = $(this);
                const groupData = rateGroups[index];

                if (!groupData) return;

                const tourists = parseInt(groupData.tourists || 0);
                const rate = (isCash && groupData.is_cash_rate == 1 && groupData.cash_rate > 0) ? parseFloat(groupData.cash_rate || 0) : parseFloat(groupData.rate || 0);
                const tax = (isCash && groupData.is_cash_rate == 1 && groupData.cash_rate > 0) ? parseFloat(groupData.cash_tax || 0) : parseFloat(groupData.additional_charge || 0);
                const finalTotal = (rate * tourists) + (tax * tourists);

                row.find('.price .tag').text(currencyFormation.symbol + rate.toFixed(2));
                row.find('.adnl-charge').text(currencyFormation.symbol + tax.toFixed(2));
                row.find('.final').text(currencyFormation.symbol + finalTotal.toFixed(2));
            });
            updateRatesTable();
        }
    }
    handleSplitCheckboxForPayLater() {
        // Disable split payment for Pay Later
        document.querySelector('input[name="split_checkbox"]').checked = false;
        document.querySelector('#dividedBy').value = 'Please select option';
        document.querySelector('.split-check').textContent = `${this.currencyFormation.symbol}0.00`;
        document.querySelector('#split_value').hidden = true;
        document.querySelector('#split_value_field').required = false;
        document.querySelector('#split_value_field').min = false;
        document.querySelector('#split_value_field').value = '';
        document.querySelector('.split-set').textContent = `${this.currencyFormation.symbol}0.00`;
        document.querySelector('#psplit').style.display = 'none';
        document.querySelector('#split-payment').checked = false;
    }

    updateCartPackages(customCharge, fee, isCardOrCashWithFees = false) {
        if (this.cartPackages.length > 0 && this.cartView == 1) {
            this.cartPackages.forEach(pkg => {
                const packageId = parseInt(pkg.tour_package_id, 10);

                let packageSubtotal = this.getTextAmount(`#package-subtotal-${packageId}`);

                if (parseFloat(pkg.custom_charge) > 0) {
                    packageSubtotal += parseFloat(pkg.custom_charge);
                }

                const packageTotal = isCardOrCashWithFees
                    ? (packageSubtotal) + parseFloat(pkg.addon_total) + parseFloat(pkg.custom_charge_total) + parseFloat(pkg.tip_total)
                    : packageSubtotal + parseFloat(pkg.addon_price) + parseFloat(pkg.tip_amount);

                const packageFee = isCardOrCashWithFees
                    ? parseFloat(pkg.fee) + parseFloat(pkg.addon_fee) + parseFloat(pkg.custom_charge_fees) + parseFloat(pkg.tip_fees)
                    : 0;

                this.updatePackageDisplay(packageId, parseFloat(pkg.addon_price), packageFee, packageTotal, parseFloat(pkg.custom_charge), parseFloat(pkg.tip_amount));
            });
        }
    }
    handleNewPricingOnPaymentMethodChangeCart(paymentMethod) {
        if (!cartPackages.length || this.cartView != 1) return;

        const isCash = paymentMethod == 'Cash';
        const isCardOrLink = paymentMethod == 'Card' || paymentMethod === 'Link';
        const isCardOrCashWithFees = isCardOrLink || (isCash && this.cashFees == 1);
        const fmt = (n) => `${this.currencyFormation.symbol}${Number(n).toFixed(2)}`;
        const setEl = (sel, val) => { const el = document.querySelector(sel); if (el) el.textContent = fmt(val); };

        let ticketCost = 0, subTotal = 0, totalFee = 0, totalCost = 0, totalDiscount = 0;

        for (const pkg of cartPackages) {
            const packageTicketCost = parseFloat(isCash ? (this.cashPriceEnabled == 1 ? pkg.cashSubtt : pkg.subtt) : pkg.subtt);
            const packageDiscount = isCash ? (this.cashPriceEnabled == 1 ? parseFloat(pkg.discount_amount_cash) : parseFloat(pkg.discount_amount)) : parseFloat(pkg.discount_amount);
            const packageSubtotal = packageTicketCost - packageDiscount;

            let packageFees = 0;
            let packageTotal = 0;

            if (isCardOrCashWithFees) {
                packageFees = parseFloat(isCash ? (this.cashPriceEnabled == 1 ? pkg.cashFee : pkg.fee) : pkg.fee);
                const baseTotal = parseFloat(isCash ? (this.cashPriceEnabled == 1 ? pkg.cashTotal : pkg.total) : pkg.total);
                packageTotal = baseTotal + parseFloat(pkg.addon_total) + parseFloat(pkg.custom_charge_total) + parseFloat(pkg.tip_total);
            } else {
                packageTotal = packageSubtotal + parseFloat(pkg.custom_charge) + parseFloat(pkg.addon_price) + parseFloat(pkg.tip_amount);
            }

            ticketCost += packageTicketCost;
            totalDiscount += packageDiscount;
            subTotal += packageSubtotal;
            totalFee += packageFees + parseFloat(pkg.addon_fee) + parseFloat(pkg.custom_charge_fees) + parseFloat(pkg.tip_fees);
            totalCost += packageTotal;
        }
        
        setEl('.total-before-service-commission-and-discount', ticketCost);
        setEl('.discount-value', totalDiscount);
        setEl('.total-after-discount-before-service-commission', subTotal);
        setEl('.service-commission', totalFee);
        setEl(paymentMethod === 'Pay Later' ? 'div.cash-set' : '#total_cost', totalCost);
    }

    handleChecksetClick(event) {
        const value = event.target.value;
        const isSplitChecked = document.querySelector('input[name="split_checkbox"]:checked');
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

        if (totalCustomChargeAmount === 0 && totalCartAddonsPrice === 0 && totalCartTipAmount === 0) {
            if (value === 'Cash' || value === 'Pay Later') {
                if (value !== 'Pay Later') {
                    this.handleSplitCalculation(value, isSplitChecked);
                } else {
                    this.handleSplitCheckboxForPayLater();
                }
            } else if (value === 'Card') {
                this.handleSplitCalculation(value, isSplitChecked);
            } else if (value === 'Link') {
                this.handleSplitCalculation(value, isSplitChecked);
            }
            this.updateSplitVisibility(isSplitChecked, paymentMethod);
        }

    }

    handleSplitCalculation(paymentMethod, isSplitChecked) {
        const dividedByElement = document.querySelector('#dividedBy');

        if (!dividedByElement || !dividedByElement.selectedOptions[0]) {
            return; // Element not found or no option selected
        }

        const selectedOption = parseInt(dividedByElement.selectedOptions[0].value, 10);

        if (selectedOption && !isNaN(selectedOption) && isSplitChecked && paymentMethod !== 'Pay Later') {
            const amount = this.calculateSplitAmount(paymentMethod);
            const splitValue = (amount / selectedOption).toFixed(2);

            document.querySelector('.split-check').textContent = `${this.currencyFormation.symbol}${splitValue}`;
            document.querySelector('#split_value_field').value = splitValue;
            document.querySelector('#split_value').hidden = false;
            document.querySelector('#split_value_field').required = true;
            document.querySelector('#split_value_field').min = true;
            document.querySelector('.split-set').textContent = `${this.currencyFormation.symbol}${splitValue}`;
            document.querySelector('#psplit').style.display = 'block';
            document.querySelector('#split-payment').checked = true;

            if (paymentMethod === 'Card' || paymentMethod === 'Link') {
                document.querySelector('#pcard').style.display = 'none';
            } else {
                document.querySelector('#pcash').style.display = 'none';
            }
        } else if (paymentMethod === 'Pay Later') {
            this.handleSplitCheckboxForPayLater();
        }
    }

    calculateSplitAmount(paymentMethod) {
        if (this.cartTotal === 0) {
            const selector = (paymentMethod === 'Card') ? '#K' : '#M';
            return this.getTextAmount(selector);
        }
        return (paymentMethod === 'Card' || (paymentMethod === 'Cash' && this.cashFees === 1))
            ? this.cartTotal
            : this.cartTotal - this.totalCartServiceCommission;
    }

    updateSplitVisibility(isSplitChecked, paymentMethod) {
        // Check if a split option is selected from dropdown
        const dividedByValue = document.querySelector('#dividedBy')?.value;
        const hasSplitOption = dividedByValue && dividedByValue !== 'Please select option' && !isNaN(parseInt(dividedByValue));

        if ((isSplitChecked || hasSplitOption) && paymentMethod !== 'Pay Later' && paymentMethod !== 'Link') {
            document.querySelector('#split_value').hidden = false;
            document.querySelector('#split_value_field').required = true;
            $('#split_value_field').show();
        } else {
            const splitCheckEl = document.querySelector(`.split-check`);
            if (splitCheckEl) splitCheckEl.textContent = `${this.currencyFormation.symbol}0.00`;
            document.querySelector('#split_value_field').value = '';
            document.querySelector('#split_value').hidden = true;
            document.querySelector('#split_value_field').required = false;
            document.querySelector('#split_value_field').min = false;
            document.querySelector('.split-set').textContent = `${this.currencyFormation.symbol}0.00`;

            if (paymentMethod === 'Card' || paymentMethod === 'Link' || (paymentMethod === 'Cash' && this.cashFees === 1)) {
                document.querySelector('#pcard').style.display = 'block';
                document.querySelector('#pcash').style.display = 'none';
                document.querySelector('#psplit').style.display = 'none';
            } else if (paymentMethod === 'Cash' || paymentMethod === 'Pay Later' || paymentMethod === 'Link') {
                document.querySelector('#pcash').style.display = 'block';
                document.querySelector('#pcard').style.display = 'none';
                document.querySelector('#psplit').style.display = 'none';
            }
            // Use helper method to determine split payment visibility, but also check payment method
            if (paymentMethod === 'Pay Later' || paymentMethod === 'Link' || !this.shouldShowSplitPayment()) {
                document.querySelector('#split-payment-div').style.display = 'none';
                document.querySelector('.connect-to-card-reader').style.display = 'none';
            } else {
                document.querySelector('#split-payment-div').style.display = 'block';
                document.querySelector('.connect-to-card-reader').style.display = 'none';
            }
            if (paymentMethod == "Card") {
                document.querySelector('.connect-to-card-reader').style.display = 'block';
            }
        }
    }

    handleSplitValueInput(event) {
        const splitValue = parseFloat(event.target.value) || 0.00;
        document.querySelector('.split-check').textContent = `${this.currencyFormation.symbol}${splitValue.toFixed(2)}`;
        document.querySelector('.split-set').textContent = `${this.currencyFormation.symbol}${splitValue.toFixed(2)}`;
        document.querySelector('#pcard').style.display = 'none';
        document.querySelector('#pcash').style.display = 'none';
        document.querySelector('#psplit').style.display = 'block';
    }

    handlePaymentValidation(event) {
        let kAmount = 0;
        if ($('#K').length) {
            kAmount = this.getTextAmount('#K');
        }
        let mAmount = 0;
        if ($('#M').length) {
            mAmount = this.getTextAmount('#M');
        }
        const splitValueField = document.querySelector('#split_value_field');

        if (!splitValueField) {
            console.warn('Element #split_value_field not found');
            return; // Or handle it however fits your UX
        }

        const splitValue = parseFloat(splitValueField.value);
        if ((kAmount > 0 && splitValue > kAmount) || (this.cashPriceEnabled == 0 && mAmount > 0 && kAmount > 0 && mAmount > kAmount)) {
            document.querySelector('#validate').textContent =
                'Split amount is greater than payment amount please check.';
            event.preventDefault();
        }
    }

    handleSplitOptionChange(event) {
        const selectedValue = parseInt(event.target.selectedOptions[0].value, 10);
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;

        if (isNaN(selectedValue) || selectedValue === 0 || paymentMethod === 'Pay Later') {
            this.resetSplitValues(paymentMethod);
        } else {
            this.calculateAndDisplaySplit(paymentMethod, selectedValue);
        }
    }

    resetSplitValues(paymentMethod) {
        document.querySelector('.split-check').textContent = `${this.currencyFormation.symbol}0.00`;
        document.querySelector('#split_value_field').value = '';
        document.querySelector('#split_value').hidden = true;
        document.querySelector('#split_value_field').required = false;
        document.querySelector('#split_value_field').min = false;
        document.querySelector('.split-set').textContent = `${this.currencyFormation.symbol}0.00`;
        document.querySelector('#psplit').style.display = 'none';
        document.querySelector('#split-payment').checked = false;

        if (paymentMethod === 'Card' || (paymentMethod === 'Cash' && this.cashFees === 1)) {
            document.querySelector('#pcard').style.display = 'block';
        } else if (paymentMethod === 'Cash' || paymentMethod === 'Pay Later') {
            document.querySelector('#pcash').style.display = 'block';
        }
    }

    calculateAndDisplaySplit(paymentMethod, selectedValue) {
        const amount = this.calculateSplitAmount(paymentMethod);
        const splitValue = (amount / selectedValue).toFixed(2);

        document.querySelector('.split-check').textContent = `${this.currencyFormation.symbol}${splitValue}`;
        document.querySelector('#split_value_field').value = splitValue;
        document.querySelector('#split_value').hidden = false;
        document.querySelector('#split_value_field').required = true;
        document.querySelector('#split_value_field').min = true;
        document.querySelector('.split-set').textContent = `${this.currencyFormation.symbol}${splitValue}`;
        document.querySelector('#psplit').style.display = 'block';
        document.querySelector('#split-payment').checked = true;
        $('#split_value_field').show();

        if (paymentMethod === 'Card' || (paymentMethod === 'Cash' && this.cashFees === 1)) {
            document.querySelector('#pcard').style.display = 'none';
        } else {
            document.querySelector('#pcash').style.display = 'none';
            document.querySelector('#pcard').style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const paymentHandler = new PaymentHandler();
    paymentHandler.initialize();
});
