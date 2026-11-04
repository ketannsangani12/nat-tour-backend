// generalRates.js

class GeneralRates {
    constructor(getPackageRatesUrl, savePackageRatesUrl, deletePackageRatesUrl, taxPercent, taxPercentType, cashPriceEnabled) {
        this.getPackageRatesUrl = getPackageRatesUrl;
        this.savePackageRatesUrl = savePackageRatesUrl;
        this.deletePackageRatesUrl = deletePackageRatesUrl;

        // Tax percentages
        this.taxPercent = taxPercent;
        this.taxPercentType = taxPercentType;

        this.schedulingYear = moment().format('YYYY');
        this.cashPriceEnabled = cashPriceEnabled;

        this.packageId = 0;
        this.affiliateId = null;
        this.natFeesPercentage = 0;
        this.isGroupRateEnabled = 0;
        this.isPartnerPackage = 0;

        // By default add more button will be hidden
        document.getElementById('add-rate-group-btn').style = 'display: none;';
        if (document.getElementById('copy-rate-group-btn')) {
            document.getElementById('copy-rate-group-btn').style = 'display: none;';
        }

        $(document).on('click', '#btn-filter-general-rate-group', (evt) => {
            this.schedulingYear = $('#general-scheduling-year').val();
            this.packageId = $('#general-packages').val();
            this.affiliateId = $('#general-affiliates').val();

            if (this.schedulingYear && this.packageId && this.affiliateId == 0) {
                this.getGeneralPackageRates();
                document.getElementById('add-rate-group-btn').style = 'display: block;';
                if (document.getElementById('copy-rate-group-btn')) {
                    document.getElementById('copy-rate-group-btn').style = 'display: block;';
                }
            }

            if (this.schedulingYear && this.packageId && this.affiliateId) {
                this.getGeneralAffiliateRates();
                document.getElementById('add-rate-group-btn').style = 'display: block;';
                if (document.getElementById('copy-rate-group-btn')) {
                    document.getElementById('copy-rate-group-btn').style = 'display: block;';
                }
            }
        });

        $(document).on('click', '#add-rate-group-btn', (evt) => {
            this.addNewGeneralRateGroup();
        });

        $(document).on('click', '#copy-rate-group-btn', (evt) => {
            this.copyRatesToNextYear();
        });

        $(document).on('click', '#btn-clear-general-rate-group', (evt) => {
            this.clearData();
        });

        $(document).on('change', '#general-scheduling-year, #general-custom-dates, #general-packages, #general-affiliates', (evt) => {
            document.getElementById('tbody-pckg-rate-grps').innerHTML = '';
            document.getElementById('add-rate-group-btn').style = 'display: none;';
            if (document.getElementById('copy-rate-group-btn')) {
                document.getElementById('copy-rate-group-btn').style = 'display: none;';
            }
        });

        $(document).on('input', '.txt-rate', (evt) => {
            this.updateAdditionalChargeOnRateChange(evt);
        });

        $(document).on('input', '.txt-permit-fee', (evt) => {
            this.updateAdditionalChargeOnRateChange(evt);
            if (this.cashPriceEnabled == 1) {
                this.updateAdditionalChargeCashOnRateChange(evt);
            }
        });

        $(document).on('input', '.txt-adnl-chrg', (evt) => {
            this.updateAdditionalChargeOnRateChange(evt);
        });

        $(document).on('input', '.txt-cash-rate', (evt) => {
            this.updateAdditionalChargeCashOnRateChange(evt);
        });

        $(document).on('input', '.txt-cash-tax', (evt) => {
            this.updateAdditionalChargeCashOnRateChange(evt);
        });

        $(document).on('change', '.select-partner-fee-type', (evt) => {
            this.updateAdditionalChargeOnRateChange(evt);
        });

        $(document).on('change', '.chk-is-cash-rate', function () {
            const isChecked = $(this).is(':checked');
            const row = $(this).closest('tr');

            row.find('.txt-cash-rate, .txt-cash-tax')
                .prop('disabled', !isChecked);
        });
    }

    /**
     * Clear the data added on filter
     */
    clearData() {
        // Clear existing rows
        document.getElementById('tbody-pckg-rate-grps').innerHTML = '';
        $('#general-scheduling-year, #general-packages, #general-affiliates').val('').trigger('change');

        this.packageId = 0;
        this.affiliateId = null;
    }

    /**
     * Update route param data for filter.
     */
    getGeneralPackageRates() {
        showLoading();

        // Clear existing rows
        document.getElementById('tbody-pckg-rate-grps').innerHTML = '';

        // Clear rateGroups to prepare for new data
        let rateGroups = [];

        $.post(this.getPackageRatesUrl, {
            'scheduling_year': this.schedulingYear,
            'tour_package_id': this.packageId,
            'affiliate_id': 0
        }).then((response) => { // Use arrow function to retain 'this' context
            hideLoading();
            this.natFeesPercentage = response.nat_fees_percentage;
            this.isGroupRateEnabled = response.is_group_rate_enabled;
            this.isPartnerPackage = response.is_partner_package;
            rateGroups = response.rates;

            // Render each rate group dynamically
            rateGroups.forEach(rateGroup => {
                document.getElementById('tbody-pckg-rate-grps').insertAdjacentHTML('beforeend', packageRateGroupTemplate(rateGroup));

                // Attach the remove event to the new button
                const removeButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-remove');
                removeButton.addEventListener('click', (event) => this.removeGeneralRateGroup(event));

                const saveButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-save');
                saveButton.addEventListener('click', (event) => this.saveRateGroups(event));
            });
        }).fail(function (jqXHR) {
            hideLoading();
            showSubmissionError(jqXHR);
        });

        // Template function to generate HTML for each rate group
        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td class="px-2 py-1.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm">
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control form-custom-input hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control form-custom-input schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="tour_package_id" class="form-control form-custom-input packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control form-custom-input affiliateId" value="">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="th-custom-css" style="${this.isGroupRateEnabled ? `width: 310px;` : `width: 230px;`}">Rate For <req>*</req></th>
                                <th class="th-custom-css">Age From</th>
                                <th class="th-custom-css">Age To</th>
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Rate <req>*</req></th>
                                <th class="th-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``}>Permit Fee</th>
                                ${this.isPartnerPackage ? `
                                    <th class="th-custom-css">Partner Fee Type</th>
                                    <th class="th-custom-css">Partner Fee</th>
                                ` : ''}
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Tax</th>
                                
                                <th colspan="2" class="th-custom-css">Actions</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td class="td-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`} style="${this.isPartnerPackage ? `width: 210px` : `width: 165px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="rate" class="form-control form-custom-input !rounded-l-none txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``} style="${this.isPartnerPackage ? `width: 180px` : `width: 165px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="permit_fee" class="form-control form-custom-input !rounded-l-none txt-permit-fee" type="number" value="${data.permit_fee}" min="0" step="0.01" placeholder="Permit Fee">
                                    </div>
                                </td>
                                ${this.isPartnerPackage ? `
                                    <td class="td-custom-css" style="width: 126px">
                                    <select data-name="partner_fee_type" class="form-control form-custom-input select-partner-fee-type">
                                        <option value="Fixed" ${data.partner_fee_type == 'Fixed' ? 'selected' : ''}>Fixed</option>
                                        <option value="Percentage" ${data.partner_fee_type == 'Percentage' ? 'selected' : ''}>Percentage</option>
                                       
                                    </select>
                                </td>
                                <td class="td-custom-css" style="width: 165px;">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                    </div>
                                </td>
                                ` : ''}
                                <td class="td-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`} style="${this.isPartnerPackage ? `width: 200px` : `width: 140px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                
                                <td colspan="2" class="td-custom-css text-center ${this.isPartnerPackage ? `` : `whitespace-nowrap`}">
                                    <button type="button" class="btn btn-primary btn-sm btn-general-rate-group-save btn-primary-small mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button>
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button><br>
                                </td>
                            </tr>
                            <tr>
                                <th class="th-custom-css descth">Description</th>    
                                <th class="th-custom-css">Min Pax</th>
                                <th class="th-custom-css">Max Pax</th>
                                <th class="th-custom-css">Order <req>*</req></th>
                                <th class="th-custom-css">Mapping Key <req>*</req></th>
                                <th class="th-custom-css">Global Rate</th>
                                <th class="th-custom-css">Infant Rate</th>
                                <th class="th-custom-css">Approval</th>
                                <th class="th-custom-css" style="${this.isGroupRateEnabled ? `min-width: 110px;` : ``}">Status</th>
                                ${this.isGroupRateEnabled ? `
                                    <th class="th-custom-css !whitespace-normal" style="width: 126px">Group Min Pax</th>
                                    <th class="th-custom-css !whitespace-normal"" style="width: 126px">Group Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td class="td-custom-css descth">
                                    <textarea data-name="description" class="form-control form-custom-input txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Min Pax" required>
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Max Pax">
                                    </div>
                                </td>
                                
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input data-name="orderby" class="form-control form-custom-input txt-orderby" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
                                    </div>
                                </td>
                                 <td class="td-custom-css">
                                    <select data-name="mapping_key" class="form-control form-custom-input select-mapping-key">
                                        <option value="0" ${data.mapping_key == 0 ? 'selected' : ''}>Default</option>
                                        <option value="1" ${data.mapping_key == 1 ? 'selected' : ''}>Adults</option>
                                        <option value="2" ${data.mapping_key == 2 ? 'selected' : ''}>Children</option>
                                        <option value="3" ${data.mapping_key == 3 ? 'selected' : ''}>Infant</option>
                                        <option value="4" ${data.mapping_key == 4 ? 'selected' : ''}>Adults - Military Teachers</option>
                                        <option value="5" ${data.mapping_key == 5 ? 'selected' : ''}>Children - Military Teachers</option>
                                        <option value="6" ${data.mapping_key == 6 ? 'selected' : ''}>Native Americans</option>
                                        <option value="7" ${data.mapping_key == 7 ? 'selected' : ''}>Locals</option>
                                        <option value="8" ${data.mapping_key == 8 ? 'selected' : ''}>Tour Guides</option>
                                        <option value="9" ${data.mapping_key == 9 ? 'selected' : ''}>Veteran</option>
                                        <option value="10" ${data.mapping_key == 10 ? 'selected' : ''}>Senior Citizen</option>
                                    </select>
                                </td>
                                 <td class="td-custom-css">
                                    <select data-name="global_rate" class="form-control form-custom-input select-global-rate">
                                        <option value="0" ${data.global_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.global_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="is_infant_rate" class="form-control form-custom-input select-infant-rate">
                                        <option value="0" ${data.is_infant_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.is_infant_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="approval" class="form-control form-custom-input select-approval">
                                        <option value="0" ${data.approval == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.approval == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                
                                <td class="td-custom-css">
                                    <select data-name="status" class="form-control form-custom-input sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.isGroupRateEnabled ? `
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <input data-name="max_group_size" class="form-control form-custom-input txt-max-group-size" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                           ${this.cashPriceEnabled == 1 ?
                `<tr>
                                <th class="th-custom-css">Cash Rate</th>
                                <th class="th-custom-css">Cash Tax</th>
                                <th class="th-custom-css descth">Is Cash Rate?</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_rate" class="form-control form-custom-input !rounded-l-none txt-cash-rate" ${data.is_cash_rate == 0 ? 'disabled' : ''} value="${data.cash_rate}" type="number" value="0" min="0" step="0.01" placeholder="Cash Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" style="${this.isGroupRateEnabled == 1 ? `width:250px;` : `width:165px;`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_tax" class="form-control form-custom-input !rounded-l-none txt-cash-tax" type="number" ${data.is_cash_rate == 0 ? 'disabled' : ''} value="${data.cash_tax}" min="0" max="99999.99" step="0.01" placeholder="Cash Tax">
                                    </div>
                                </td>
                                <td class="td-custom-css text-center align-middle">
                                    <input type="checkbox" data-name="is_cash_rate" class="form-check-input chk-is-cash-rate" ${data.is_cash_rate ? 'checked' : ''}>
                                </td>
                            </tr>` : ''}    
                        </table>
                        <div class="rate-group-total flex justify-between rounded-md text-base text-white bg-[#264F73] px-4 py-2 whitespace-nowrap">
                            <span class="created-by mr-3">Author: <span>${data.created_by_with_name}</span></span>
                            <div>
                            ${this.cashPriceEnabled == 1 ?
                `Cash Subtotal: <span class="cash-subtotal">${this.getCashSubtotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>
                                Cash Total with Fees: <span class="cash-total">${this.getCashTotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>` :
                ``}
                                Subtotal: <span class="subtotal">${this.getSubtotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>
                                Total with Fees: <span class="total">${this.getTotal(data)}</span>
                            </div>
                        </div>
                    </form>
                    <hr>
                </td>
            </tr>
        `;
    }

    /**
     * Update route param data for filter.
     */
    getGeneralAffiliateRates() {
        showLoading();

        // Clear existing rows
        document.getElementById('tbody-pckg-rate-grps').innerHTML = '';

        // Clear rateGroups to prepare for new data
        let rateGroups = [];

        $.post(this.getPackageRatesUrl, {
            'scheduling_year': this.schedulingYear,
            'tour_package_id': this.packageId,
            'affiliate_id': this.affiliateId,
        }).then((response) => { // Use arrow function to retain 'this' context
            hideLoading();
            this.natFeesPercentage = response.nat_fees_percentage;
            this.isGroupRateEnabled = response.is_group_rate_enabled;
            this.isPartnerPackage = response.is_partner_package;
            rateGroups = response.rates;

            // Render each rate group dynamically
            rateGroups.forEach(rateGroup => {
                document.getElementById('tbody-pckg-rate-grps').insertAdjacentHTML('beforeend', packageRateGroupTemplate(rateGroup));

                // Attach the remove event to the new button
                const removeButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-remove');
                removeButton.addEventListener('click', (event) => this.removeGeneralRateGroup(event));

                const saveButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-save');
                saveButton.addEventListener('click', (event) => this.saveRateGroups(event));
            });
        }).fail(function (jqXHR) {
            hideLoading();
            showSubmissionError(jqXHR);
        });

        // Template function to generate HTML for each rate group
        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td class="px-2 py-1.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm">
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control form-custom-input hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control form-custom-input schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="tour_package_id" class="form-control form-custom-input packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control form-custom-input affiliateId" value="${data.affiliate_id}">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="th-custom-css" style="${this.isGroupRateEnabled ? `width: 310px;` : `width: 230px;`}">Rate For <req>*</req></th>
                                <th class="th-custom-css">Age From</th>
                                <th class="th-custom-css">Age To</th>
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Rate <req>*</req></th>
                                <th class="th-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``}>Permit Fee</th>
                                ${this.isPartnerPackage ? `
                                <th class="th-custom-css">Partner Fee Type</th>
                                <th class="th-custom-css">Partner Fee</th>
                                ` : ''}
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Tax</th>
                                
                                <th colspan="2" class="th-custom-css">Actions</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td class="td-custom-css"${this.isPartnerPackage ? `` : `colspan="2"`} style="${this.isPartnerPackage ? `width: 210px` : `width: 165px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="rate" class="form-control form-custom-input !rounded-l-none txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``} style="${this.isPartnerPackage ? `width: 180px` : `width: 165px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="permit_fee" class="form-control form-custom-input !rounded-l-none txt-permit-fee" type="number" value="${data.permit_fee}" min="0" step="0.01" placeholder="Permit Fee">
                                    </div>
                                </td>
                                ${this.isPartnerPackage ? `
                                     <td class="td-custom-css" style="width: 126px;">
                                    <select data-name="partner_fee_type" class="form-control form-custom-input select-partner-fee-type">
                                        <option value="Fixed" ${data.partner_fee_type == 'Fixed' ? 'selected' : ''}>Fixed</option>
                                        <option value="Percentage" ${data.partner_fee_type == 'Percentage' ? 'selected' : ''}>Percentage</option>
                                       
                                    </select>
                                </td>
                                    <td class="td-custom-css" style="width: 165px;">
                                        <div class="input-group flex items-center">
                                            <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                            <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                        </div>
                                    </td>
                                    ` : ''}
                                <td class="td-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`} style="${this.isPartnerPackage ? `width: 200px` : `width: 140px`}">
                                    <div class="input-group flex items-center" style=""width: 140px">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                
                                <td colspan="2" class="td-custom-css text-center ${this.isPartnerPackage ? `` : `whitespace-nowrap`}">
                                    <button type="button" class="btn btn-primary btn-sm btn-general-rate-group-save btn-primary-small mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button>
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button>
                                </td>
                            </tr>
                            <tr>
                                <th  class="th-custom-css descth">Description</th>
                                <th class="th-custom-css">Min Pax</th>
                                <th class="th-custom-css">Max Pax</th>
                                <th class="th-custom-css">Order <req>*</req></th>
                                <th class="th-custom-css">Mapping Key <req>*</req></th>
                                <th class="th-custom-css">Global Rate</th>
                                <th class="th-custom-css">Infant Rate</th>
                                <th class="th-custom-css">Approval</th>
                                <th class="th-custom-css" style="${this.isGroupRateEnabled ? `min-width: 110px;` : ``}">Status</th>
                                ${this.isGroupRateEnabled ? `
                                    <th class="th-custom-css !whitespace-normal" style="width: 126px">Group Min Pax</th>
                                    <th class="th-custom-css !whitespace-normal" style="width: 126px">Group Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td class="td-custom-css descth" style="width: 126px">
                                    <textarea data-name="description" class="form-control form-custom-input txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Min Pax" required>
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Max Pax">
                                    </div>
                                </td>
                               
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input data-name="orderby" class="form-control form-custom-input txt-orderby" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="mapping_key" class="form-control form-custom-input select-mapping-key">
                                        <option value="0" ${data.mapping_key == 0 ? 'selected' : ''}>Default</option>
                                        <option value="1" ${data.mapping_key == 1 ? 'selected' : ''}>Adults</option>
                                        <option value="2" ${data.mapping_key == 2 ? 'selected' : ''}>Children</option>
                                        <option value="3" ${data.mapping_key == 3 ? 'selected' : ''}>Infant</option>
                                        <option value="4" ${data.mapping_key == 4 ? 'selected' : ''}>Adults - Military Teachers</option>
                                        <option value="5" ${data.mapping_key == 5 ? 'selected' : ''}>Children - Military Teachers</option>
                                        <option value="6" ${data.mapping_key == 6 ? 'selected' : ''}>Native Americans</option>
                                        <option value="7" ${data.mapping_key == 7 ? 'selected' : ''}>Locals</option>
                                        <option value="8" ${data.mapping_key == 8 ? 'selected' : ''}>Tour Guides</option>
                                        <option value="9" ${data.mapping_key == 9 ? 'selected' : ''}>Veteran</option>
                                        <option value="10" ${data.mapping_key == 10 ? 'selected' : ''}>Senior Citizen</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="global_rate" class="form-control form-custom-input select-global-rate">
                                        <option value="0" ${data.global_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.global_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                 <td class="td-custom-css">
                                    <select data-name="is_infant_rate" class="form-control form-custom-input select-infant-rate">
                                        <option value="0" ${data.is_infant_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.is_infant_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="approval" class="form-control form-custom-input select-approval">
                                        <option value="0" ${data.approval == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.approval == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="status" class="form-control form-custom-input sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.isGroupRateEnabled ? `
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <input data-name="max_group_size" class="form-control form-custom-input txt-max-group-size" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                            ${this.cashPriceEnabled == 1 ?
                `<tr>
                                <th class="th-custom-css">Cash Rate</th>
                                <th class="th-custom-css">Cash Tax</th>
                                <th class="th-custom-css">Is Cash Rate?</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_rate" class="form-control form-custom-input !rounded-l-none txt-cash-rate txt-required" type="number" ${data.is_cash_rate == 0 ? 'disabled' : ''} value="${data.cash_rate}" min="0" step="0.01" placeholder="Cash Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" style="${this.isGroupRateEnabled == 1 ? `width:250px;` : `width:165px;`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_tax" class="form-control form-custom-input !rounded-l-none txt-cash-tax" type="number" value="${data.cash_tax}" ${data.is_cash_rate == 0 ? 'disabled' : ''} min="0" max="99999.99" step="0.01" placeholder="Cash Tax">
                                    </div>
                                </td>
                                <td class="td-custom-css text-center align-middle">
                                    <input type="checkbox" data-name="is_cash_rate" class="form-check-input chk-is-cash-rate" ${data.is_cash_rate ? 'checked' : ''}>
                                </td>
                            </tr>` : ''} 
                        </table>
                        <div class="rate-group-total flex justify-between rounded-md text-base text-white bg-[#264F73] px-4 py-2 whitespace-nowrap">
                            <span class="created-by mr-3">Author: <span>${data.created_by_with_name}</span></span>
                            <div>
                                ${this.cashPriceEnabled == 1 ?
                `Cash Subtotal: <span class="cash-subtotal">${this.getCashSubtotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>
                                Cash Total with Fees: <span class="cash-total">${this.getCashTotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>` :
                ``}
                                Subtotal: <span class="subtotal">${this.getSubtotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>
                                Total with Fees: <span class="total">${this.getTotal(data)}</span>
                            </div>
                        </div>
                    </form>
                    <hr>
                </td>
            </tr>
        `;
    }

    /**
     * Add new rate group to the package
     */
    addNewGeneralRateGroup() {
        const newGeneralRateGroup = {
            id: '',
            scheduling_year: this.schedulingYear,
            tour_package_id: this.packageId,
            affiliate_id: this.affiliateId,
            rate_for: '',
            age_from: 0,
            age_to: 0,
            rate: 0,
            permit_fee: 0,
            partner_fee: 0,
            margin: 0,
            additional_charge: 0,
            min_pax_allowed: 0,
            max_pax_allowed: 0,
            description: '',
            orderby: 0,
            mapping_key: 0,
            global_rate: 0,
            approval: 0,
            status: 'Active',
            min_group_size: 0, // Add default values for group size if necessary
            max_group_size: 0
        };

        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td class="px-2 py-1.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm">
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control form-custom-input hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control form-custom-input schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="tour_package_id" class="form-control form-custom-input packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control form-custom-input affiliateId" value="${data.affiliate_id}">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="th-custom-css" style="${this.isGroupRateEnabled ? `width: 310px;` : `width: 230px;`}">Rate For <req>*</req></th>
                                <th class="th-custom-css">Age From</th>
                                <th class="th-custom-css">Age To</th>
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Rate <req>*</req></th>
                                <th class="th-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``}>Permit Fee</th>
                                ${this.isPartnerPackage ? `
                                <th class="th-custom-css">Partner Fee Type</th>
                                <th class="th-custom-css">Partner Fee</th>
                                ` : ''}
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Tax</th>
                                
                                <th colspan="2" class="th-custom-css">Actions</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td class="td-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`} style="${this.isPartnerPackage ? `width: 210px` : `width: 165px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="rate" class="form-control form-custom-input !rounded-l-none txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``} style="${this.isPartnerPackage ? `width: 180px` : `width: 165px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="permit_fee" class="form-control form-custom-input !rounded-l-none txt-permit-fee" type="number" value="${data.permit_fee}" min="0" step="0.01" placeholder="Permit Fee">
                                    </div>
                                </td>
                                ${this.isPartnerPackage ? `
                                      <td class="td-custom-css" style="width: 126px;">
                                    <select data-name="partner_fee_type" class="form-control form-custom-input select-partner-fee-type">
                                        <option value="Fixed" ${data.partner_fee_type == 'Fixed' ? 'selected' : ''}>Fixed</option>
                                        <option value="Percentage" ${data.partner_fee_type == 'Percentage' ? 'selected' : ''}>Percentage</option>
                                       
                                    </select>
                                </td>
                                    <td class="td-custom-css" style="width: 165px;">
                                        <div class="input-group flex items-center">
                                            <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                            <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                        </div>
                                    </td>
                                    ` : ''}
                                <td class="td-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`} style="${this.isPartnerPackage ? `width: 200px` : `width: 140px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                
                                <td colspan="2" class="td-custom-css text-center ${this.isPartnerPackage ? `` : `whitespace-nowrap`}">
                                    <button type="button" class="btn btn-primary btn-sm btn-general-rate-group-save btn-primary-small mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button>
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button><br>
                                </td>
                            </tr>
                            <tr>
                                <th class="th-custom-css descth">Description</th>    
                                <th class="th-custom-css">Min Pax</th>
                                <th class="th-custom-css">Max Pax</th>
                                <th class="th-custom-css">Order <req>*</req></th>
                                <th class="th-custom-css">Mapping Key <req>*</req></th>
                                <th class="th-custom-css">Global Rate</th>
                                <th class="th-custom-css">Infant Rate</th>
                                <th class="th-custom-css">Approval</th>
                                <th class="th-custom-css" style="${this.isGroupRateEnabled ? `min-width: 110px;` : ``}">Status</th>
                                ${this.isGroupRateEnabled ? `
                                    <th class="th-custom-css !whitespace-normal" style="width: 126px">Group Rate Min Pax</th>
                                    <th class="th-custom-css !whitespace-normal" style="width: 126px">Group Rate Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td class="td-custom-css descth">
                                    <textarea data-name="description" class="form-control form-custom-input txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Enter minimum people allowed" required>
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Enter maximum people allowed">
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <input data-name="orderby" class="form-control form-custom-input txt-orderby" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="mapping_key" class="form-control form-custom-input select-mapping-key">
                                        <option value="0" ${data.mapping_key == 0 ? 'selected' : ''}>Default</option>
                                        <option value="1" ${data.mapping_key == 1 ? 'selected' : ''}>Adults</option>
                                        <option value="2" ${data.mapping_key == 2 ? 'selected' : ''}>Children</option>
                                        <option value="3" ${data.mapping_key == 3 ? 'selected' : ''}>Infant</option>
                                        <option value="4" ${data.mapping_key == 4 ? 'selected' : ''}>Adults - Military Teachers</option>
                                        <option value="5" ${data.mapping_key == 5 ? 'selected' : ''}>Children - Military Teachers</option>
                                        <option value="6" ${data.mapping_key == 6 ? 'selected' : ''}>Native Americans</option>
                                        <option value="7" ${data.mapping_key == 7 ? 'selected' : ''}>Locals</option>
                                        <option value="8" ${data.mapping_key == 8 ? 'selected' : ''}>Tour Guides</option>
                                        <option value="9" ${data.mapping_key == 9 ? 'selected' : ''}>Veteran</option>
                                        <option value="10" ${data.mapping_key == 10 ? 'selected' : ''}>Senior Citizen</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="global_rate" class="form-control form-custom-input select-global-rate">
                                        <option value="0" ${data.global_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.global_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="is_infant_rate" class="form-control form-custom-input select-infant-rate">
                                        <option value="0" ${data.is_infant_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.is_infant_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="approval" class="form-control form-custom-input select-approval">
                                        <option value="0" ${data.approval == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.approval == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="status" class="form-control form-custom-input sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.isGroupRateEnabled ? `
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <input data-name="max_group_size" class="form-control form-custom-input txt-max-group-size" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                            ${this.cashPriceEnabled == 1 ?
                `<tr>
                                <th class="th-custom-css">Cash Rate</th>
                                <th class="th-custom-css">Cash Tax</th>
                                <th class="th-custom-css">Is Cash Rate?</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_rate" class="form-control form-custom-input !rounded-l-none txt-cash-rate" disabled type="number" value="0.00" min="0" step="0.01" placeholder="Cash Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" style="${this.isGroupRateEnabled == 1 ? `width:250px;` : `width:165px;`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_tax" class="form-control form-custom-input !rounded-l-none txt-cash-tax" disabled type="number" value="0.00" min="0" max="99999.99" step="0.01" placeholder="Cash Tax">
                                    </div>
                                </td>
                                <td class="td-custom-css text-center align-middle">
                                    <input type="checkbox" data-name="is_cash_rate" class="form-check-input chk-is-cash-rate">
                                </td>
                            </tr>` : ''} 
                        </table>
                        <div class="rate-group-total flex justify-between rounded-md text-base text-white bg-[#264F73] px-4 py-2 whitespace-nowrap">
                            <span class="created-by mr-3">Author: <span>You</span></span>
                            <div>
                                ${this.cashPriceEnabled == 1 ?
                `Cash Subtotal: <span class="cash-subtotal">${this.getCashSubtotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>
                                Cash Total with Fees: <span class="cash-total">${this.getCashTotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>` :
                ``}
                                Subtotal: <span class="subtotal">${this.getSubtotal(data)}</span>
                                <span class="rate-group-pipe mx-3"> | </span>
                                Total with Fees: <span class="total">${this.getTotal(data)}</span>
                            </div>
                        </div>
                    </form>
                    <hr>
                </td>
            </tr>
        `;

        // Append the new rate group to the table body
        document.getElementById('tbody-pckg-rate-grps').insertAdjacentHTML('beforeend', packageRateGroupTemplate(newGeneralRateGroup));

        // Attach the remove event to the new button
        const removeButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-remove');
        removeButton.addEventListener('click', (event) => this.removeGeneralRateGroup(event));

        const saveButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-save');
        saveButton.addEventListener('click', (event) => this.saveRateGroups(event));
    }

    /**
     * Remove the rate group from the package or affiliate
     */
    removeGeneralRateGroup(event) {
        const row = event.target.closest('.pckg-rate-grp');
        const rateId = row.querySelector('.hid-rate-id').value;

        if (rateId) {
            swal({
                title: 'Are you sure to delete the rate group?',
                text: '',
                icon: "warning",
                buttons: {
                    no: {
                        text: 'No, cancel!',
                        value: false,
                    },
                    yes: {
                        text: 'Yes, delete!',
                        className: 'btn-danger',
                    }
                }
            }).then((result) => {
                if (!result)
                    return;

                // If the rate group is existing, make an AJAX request to delete it from the database
                this.deleteGeneralRateGroup(rateId)
                    .then(() => {
                        row.remove(); // Remove the row from the table
                        swal(`Rate group with ID ${rateId} deleted from database.`, '', 'success');
                    })
                    .catch(err => {
                        swal(`Failed to delete rate group: ${err.message}`, '', 'error');
                    });
            });
        } else {
            // If it's a new rate group, simply remove the row from the table
            row.remove();
            swal("New rate group removed from the table.", '', 'success');
        }
    }

    /**
     * AJAX request to delete rate group from the database
     */
    deleteGeneralRateGroup(rateId) {
        $.ajax({
            url: this.deletePackageRatesUrl,
            method: 'POST',  // Laravel needs POST + _method for DELETE
            data: {
                id: rateId,
                _method: 'DELETE',
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (response.status === true) {
                    swal('Rate group deleted successfully!', '', 'success');
                } else {
                    swal(response.message || 'Failed to delete rate group.', '', 'error');
                }
            },
            error: function (xhr, status, error) {
                let msg = 'Error deleting rate group';

                // Try to extract meaningful error message
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (error) {
                    msg = error;
                }

                swal(msg, '', 'error');
            }
        });
    }

    /**
     * Save the rate groups
     */
    saveRateGroups(event) {
        showLoading();

        const row = event.target.closest('.pckg-rate-grp');
        const rateId = row.querySelector('.hid-rate-id').value;

        // Collect rate group data
        const rateData = {
            id: rateId || null,
            scheduling_year: row.querySelector('.schedulingYear').value,
            tour_package_id: row.querySelector('.packageId').value,
            affiliate_id: row.querySelector('.affiliateId').value || null,
            rate_for: row.querySelector('.txt-rate-for').value,
            age_from: row.querySelector('.txt-age-from').value,
            age_to: row.querySelector('.txt-age-to').value,
            rate: row.querySelector('.txt-rate').value,
            permit_fee: row.querySelector('.txt-permit-fee').value,
            partner_fee_type: (this.isPartnerPackage == 1) ? row.querySelector('.select-partner-fee-type').value : '',
            partner_fee_value: (this.isPartnerPackage == 1) ? row.querySelector('.txt-partner-fee-value').value : 0,
            additional_charge: row.querySelector('.txt-adnl-chrg').value,
            min_pax_allowed: row.querySelector('.txt-min-pax-allowed').value,
            max_pax_allowed: row.querySelector('.txt-max-pax-allowed').value,
            description: row.querySelector('.txt-desc').value,
            orderby: row.querySelector('.txt-orderby').value,
            mapping_key: row.querySelector('.select-mapping-key').value,
            global_rate: row.querySelector('.select-global-rate').value,
            is_infant_rate: row.querySelector('.select-infant-rate').value,
            is_cash_rate: row.querySelector('.chk-is-cash-rate') ? (row.querySelector('.chk-is-cash-rate').checked ? 1 : 0) : 0,
            cash_rate: row.querySelector('.txt-cash-rate') ? row.querySelector('.txt-cash-rate').value : 0,
            cash_tax: row.querySelector('.txt-cash-tax') ? row.querySelector('.txt-cash-tax').value : 0,
            approval: row.querySelector('.select-approval').value,
            status: row.querySelector('.sel-status').value,
            min_group_size: row.querySelector('.txt-min-group-size') ? row.querySelector('.txt-min-group-size').value : 0, // New field
            max_group_size: row.querySelector('.txt-max-group-size') ? row.querySelector('.txt-max-group-size').value : 0 // New field
        };

        if (this.cashPriceEnabled == 1 && row.querySelector('.chk-is-cash-rate') && row.querySelector('.chk-is-cash-rate').checked) {
            const cashRateValue = parseFloat(row.querySelector('.txt-cash-rate') ? row.querySelector('.txt-cash-rate').value : 0);
            if (!(cashRateValue > 0)) {
                hideLoading();
                swal('Cash rate must be greater than 0 when cash rate is enabled.', '', 'error');
                return;
            }
        }

        $.ajax({
            url: this.savePackageRatesUrl,
            method: 'POST',
            data: {
                rateGroup: rateData,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                hideLoading();
                if (response.status == 'success') {
                    // Update rateData with returned ID if it was newly created
                    rateData.id = response.data.id;
                    row.querySelector('.hid-rate-id').value = rateData.id;
                    swal('Rate group saved successfully!', '', 'success');
                } else {
                    swal(response.message, '', 'error');
                }
            },
            error: function (error) {
                hideLoading();
                swal('Error saving rate group: ' + error, '', 'error');
            }
        });
    }

    /**
     * Prompt for a target scheduling year and copy every currently-rendered
     * rate group row (with whatever edits are on-screen) into that year.
     */
    copyRatesToNextYear() {
        const rows = document.querySelectorAll('#tbody-pckg-rate-grps tr.pckg-rate-grp');

        if (!rows.length) {
            swal('Filter a package/year with rate groups first.', '', 'warning');
            return;
        }

        const currentYear = new Date().getFullYear();
        const minYear = currentYear - 1;
        const maxYear = currentYear + 7;
        const defaultTargetYear = parseInt(this.schedulingYear) + 1;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <p>Copy the ${rows.length} rate group(s) currently shown (including any unsaved edits) into a new scheduling year. Existing rows are left untouched.</p>
            <input type="number" id="copy-target-year" class="form-control" value="${defaultTargetYear}" min="${minYear}" max="${maxYear}">
        `;

        swal({
            title: 'Copy Rate Groups to Year',
            content: wrapper,
            buttons: {
                cancel: 'Cancel',
                confirm: { text: 'Copy', value: true }
            }
        }).then((confirmed) => {
            if (!confirmed) {
                return;
            }

            const targetYear = parseInt(document.getElementById('copy-target-year').value);

            if (!targetYear || targetYear < minYear || targetYear > maxYear) {
                swal(`Please enter a year between ${minYear} and ${maxYear}.`, '', 'error');
                return;
            }

            this.runCopyToYear(rows, targetYear);
        });
    }

    /**
     * POST a clone of each given row to the save endpoint, targeting the
     * given scheduling year, without touching the original rows/year.
     */
    runCopyToYear(rows, targetYear) {
        showLoading();

        const requests = Array.from(rows).map((row) => {
            const label = row.querySelector('.txt-rate-for').value || '(unnamed)';

            const rateData = {
                id: null,
                scheduling_year: targetYear,
                tour_package_id: row.querySelector('.packageId').value,
                affiliate_id: row.querySelector('.affiliateId').value || null,
                rate_for: row.querySelector('.txt-rate-for').value,
                age_from: row.querySelector('.txt-age-from').value,
                age_to: row.querySelector('.txt-age-to').value,
                rate: row.querySelector('.txt-rate').value,
                permit_fee: row.querySelector('.txt-permit-fee').value,
                partner_fee_type: (this.isPartnerPackage == 1) ? row.querySelector('.select-partner-fee-type').value : '',
                partner_fee_value: (this.isPartnerPackage == 1) ? row.querySelector('.txt-partner-fee-value').value : 0,
                additional_charge: row.querySelector('.txt-adnl-chrg').value,
                min_pax_allowed: row.querySelector('.txt-min-pax-allowed').value,
                max_pax_allowed: row.querySelector('.txt-max-pax-allowed').value,
                description: row.querySelector('.txt-desc').value,
                orderby: row.querySelector('.txt-orderby').value,
                mapping_key: row.querySelector('.select-mapping-key').value,
                global_rate: row.querySelector('.select-global-rate').value,
                is_infant_rate: row.querySelector('.select-infant-rate').value,
                is_cash_rate: row.querySelector('.chk-is-cash-rate') ? (row.querySelector('.chk-is-cash-rate').checked ? 1 : 0) : 0,
                cash_rate: row.querySelector('.txt-cash-rate') ? row.querySelector('.txt-cash-rate').value : 0,
                cash_tax: row.querySelector('.txt-cash-tax') ? row.querySelector('.txt-cash-tax').value : 0,
                approval: row.querySelector('.select-approval').value,
                status: row.querySelector('.sel-status').value,
                min_group_size: row.querySelector('.txt-min-group-size') ? row.querySelector('.txt-min-group-size').value : 0,
                max_group_size: row.querySelector('.txt-max-group-size') ? row.querySelector('.txt-max-group-size').value : 0
            };

            return $.ajax({
                url: this.savePackageRatesUrl,
                method: 'POST',
                data: {
                    rateGroup: rateData,
                    _token: $('meta[name="csrf-token"]').attr('content')
                }
            }).then(
                (response) => ({ ok: response.status === 'success', label, message: response.message }),
                (xhr) => ({ ok: false, label, message: (xhr.responseJSON && xhr.responseJSON.message) || 'Request failed' })
            );
        });

        Promise.all(requests).then((results) => {
            hideLoading();

            const succeeded = results.filter((result) => result.ok);
            const failed = results.filter((result) => !result.ok);

            let summary = `${succeeded.length} of ${results.length} rate group(s) copied to ${targetYear}.`;
            if (failed.length) {
                summary += '\n\nFailed:\n' + failed.map((result) => `- ${result.label}: ${result.message}`).join('\n');
            }
            summary += '\n\nSet the Scheduling Year filter to ' + targetYear + ' and click Filter to review.';

            swal(summary, '', failed.length ? 'warning' : 'success');
        });
    }

    /**
     * Update the additional charge input
     * @param {*} evt
     * @returns
     */
    updateAdditionalChargeOnRateChange(evt) {
        const rateElement = $(evt.target); // The rate input being changed
        const rowElement = rateElement.closest('.pckg-rate-grp'); // Get the closest rate group row
        const additionalChargeInput = rowElement.find('.txt-adnl-chrg'); // Find the additional charge input in the same row

        if (additionalChargeInput.length === 0) return;

        // Get the new rate value
        const newRateValue = parseFloat(parseFloat(rowElement.find('.txt-rate').val()));

        // Calculate the additional charge
        let additionalCharge = 0;
        if ($(rateElement).is('input.form-control.txt-adnl-chrg')) {
            additionalCharge = parseFloat(rateElement.val());
        } else {
            if (this.taxPercentType === 'Percent') {
                additionalCharge = this.roundout((newRateValue * this.taxPercent) / 100);
            } else if (this.taxPercentType === 'Fixed Money') {
                additionalCharge = this.taxPercent;
            }
        }

        // Update the additional charge input value
        additionalChargeInput.val(additionalCharge.toFixed(2));

        this.renderTotalInformation(rowElement[0], newRateValue, additionalCharge);
    }
    /**
     * Update the additional charge cash input
     * @param {*} evt 
     * @returns 
     */
    updateAdditionalChargeCashOnRateChange(evt) {
        const rateElement = $(evt.target); // The rate input being changed
        const rowElement = rateElement.closest('.pckg-rate-grp'); // Get the closest rate group row
        const additionalChargeInput = rowElement.find('.txt-cash-tax'); // Find the additional charge input in the same row

        if (additionalChargeInput.length === 0) return;

        // Get the new rate value
        const newRateValue = parseFloat(parseFloat(rowElement.find('.txt-cash-rate').val()));

        // Calculate the additional charge
        let additionalCharge = 0;
        if ($(rateElement).is('input.form-control.txt-cash-tax')) {
            additionalCharge = parseFloat(rateElement.val());
        } else {
            if (this.taxPercentType === 'Percent') {
                additionalCharge = this.roundout((newRateValue * this.taxPercent) / 100);
            } else if (this.taxPercentType === 'Fixed Money') {
                additionalCharge = this.taxPercent;
            }
        }

        // Update the additional charge cash input value
        additionalChargeInput.val(additionalCharge.toFixed(2));
        this.renderCashTotalInformation(rowElement[0], newRateValue, additionalCharge);
    }

    /**
     * Render the info section when charge or rate is updated
     * @param {*} row 
     * @param {*} updatedRate 
     * @param {*} updatedAdditionalCharge 
     */
    renderTotalInformation(row, updatedRate, updatedAdditionalCharge) {
        const permitInput = row.querySelector('input[data-name="permit_fee"]');
        const partnerFeeTypeSelect = row.querySelector('select[data-name="partner_fee_type"]');
        const partnerFeeValueInput = row.querySelector('input[data-name="partner_fee_value"]');
        let permitFee = parseFloat(permitInput.value);

        const subtotalText = row.querySelector('.subtotal');
        let subtotal = parseFloat(updatedRate) + parseFloat(permitFee) + parseFloat(updatedAdditionalCharge);
        let partnerFee = 0;
        if (this.isPartnerPackage == 1) {
            if (partnerFeeTypeSelect.value === 'Percentage') {
                partnerFee = this.roundout((subtotal * partnerFeeValueInput.value) / 100);
            } else if (partnerFeeTypeSelect.value === 'Fixed') {
                partnerFee = parseFloat(partnerFeeValueInput.value);
            }
        }

        subtotal = parseFloat(subtotal) + parseFloat(partnerFee);
        subtotalText.textContent = currencyFormation.symbol + subtotal.toFixed(2);

        const totalText = row.querySelector('.total');
        let total = parseFloat(subtotal) + this.roundout(subtotal * this.natFeesPercentage / 100);
        totalText.textContent = currencyFormation.symbol + total.toFixed(2);
    }

    /**
     * Get the subtotal charge
     * @param {*} data 
     * @returns 
     */
    getSubtotal(data) {
        let subtotal = (parseFloat(data.rate) + parseFloat(data.permit_fee) + parseFloat(data.additional_charge)).toFixed(2);
        let partnerFee = 0;
        if (this.isPartnerPackage == 1) {
            if (data.partner_fee_type === 'Percentage') {
                partnerFee = this.roundout((subtotal * data.partner_fee_percentage) / 100);
            } else if (data.partner_fee_type === 'Fixed') {
                partnerFee = parseFloat(data.partner_fee_amount);
            }
        }

        subtotal = parseFloat(subtotal) + parseFloat(partnerFee);
        return currency(subtotal).format();
    }

    /**
     * Get the total charge
     * @param {*} data 
     * @returns 
     */
    getTotal(data) {
        let subtotal = parseFloat(data.rate) + parseFloat(data.permit_fee) + parseFloat(data.additional_charge);
        let partnerFee = 0;
        if (this.isPartnerPackage == 1) {
            if (data.partner_fee_type === 'Percentage') {
                partnerFee = this.roundout((subtotal * data.partner_fee_percentage) / 100);
            } else if (data.partner_fee_type === 'Fixed') {
                partnerFee = parseFloat(data.partner_fee_amount);
            }
        }

        subtotal = parseFloat(subtotal) + parseFloat(partnerFee);
        let fees = this.roundout(subtotal * this.natFeesPercentage / 100);
        let total = parseFloat(subtotal) + parseFloat(fees);
        return currency(total).format();
    }
    /**
    * Get the subtotal charge
    * @param {*} data 
    * @returns 
    */
    getCashSubtotal(data) {
        var subtotal = 0;
        if (data.is_cash_rate == 1 && data.cash_rate > 0) {
            subtotal = (parseFloat(data.cash_rate) + parseFloat(data.permit_fee) + parseFloat(data.cash_tax)).toFixed(2);
            let partnerFee = 0;

            if (this.isPartnerPackage == 1) {
                if (data.partner_fee_type === 'Percentage') {
                    partnerFee = this.roundout((subtotal * data.partner_fee_percentage) / 100);
                } else if (data.partner_fee_type === 'Fixed') {
                    partnerFee = parseFloat(data.partner_fee_amount);
                }
            }

            subtotal = parseFloat(subtotal) + parseFloat(partnerFee);
        }
        return currency(subtotal).format();
    }

    /**
     * Get the total charge
     * @param {*} data 
     * @returns 
     */
    getCashTotal(data) {
        var total = 0;
        if (data.is_cash_rate == 1 && data.cash_rate > 0) {
            let subtotal = parseFloat(data.cash_rate) + parseFloat(data.permit_fee) + parseFloat(data.cash_tax);
            let partnerFee = 0;
            if (this.isPartnerPackage == 1) {
                if (data.partner_fee_type === 'Percentage') {
                    partnerFee = this.roundout((subtotal * data.partner_fee_percentage) / 100);
                } else if (data.partner_fee_type === 'Fixed') {
                    partnerFee = parseFloat(data.partner_fee_amount);
                }
            }

            subtotal = parseFloat(subtotal) + parseFloat(partnerFee);
            let fees = this.roundout(subtotal * this.natFeesPercentage / 100);
            total = parseFloat(subtotal) + parseFloat(fees);
        }
        return currency(total).format();
    }

    /**
     * Render the info section when charge or rate is updated
     * @param {*} row 
     * @param {*} updatedRate 
     * @param {*} updatedAdditionalCharge 
     */
    renderCashTotalInformation(row, updatedRate, updatedAdditionalCharge) {
        const permitInput = row.querySelector('input[data-name="permit_fee"]');
        const partnerFeeTypeSelect = row.querySelector('select[data-name="partner_fee_type"]');
        const partnerFeeValueInput = row.querySelector('input[data-name="partner_fee_value"]');
        let permitFee = parseFloat(permitInput.value);

        const subtotalText = row.querySelector('.cash-subtotal');
        let subtotal = (updatedRate > 0) ? parseFloat(updatedRate) + parseFloat(permitFee) + parseFloat(updatedAdditionalCharge) : 0;
        let partnerFee = 0;
        if (this.isPartnerPackage == 1) {
            if (partnerFeeTypeSelect.value === 'Percentage') {
                partnerFee = this.roundout((subtotal * partnerFeeValueInput.value) / 100);
            } else if (partnerFeeTypeSelect.value === 'Fixed') {
                partnerFee = parseFloat(partnerFeeValueInput.value);
            }
        }

        subtotal = parseFloat(subtotal) + parseFloat(partnerFee);
        subtotalText.textContent = currencyFormation.symbol + subtotal.toFixed(2);

        const totalText = row.querySelector('.cash-total');
        let total = parseFloat(subtotal) + this.roundout(subtotal * this.natFeesPercentage / 100);
        totalText.textContent = currencyFormation.symbol + total.toFixed(2);

    }

    /**
     * Positive rounded value
     * @param {*} amount 
     * @param {*} places 
     * @returns 
     */
    roundout(amount, places = 2) {
        if (places < 0) places = 0;
        let factor = Math.pow(10, places);
        return Math.round(amount * factor) / factor;
    }
}
