class GeneralRates {
    constructor(getPackageRatesUrl, savePackageRatesUrl, deletePackageRatesUrl, manageSlotAssignmentUrl) {
        this.getPackageRatesUrl = getPackageRatesUrl;
        this.savePackageRatesUrl = savePackageRatesUrl;
        this.deletePackageRatesUrl = deletePackageRatesUrl;
        this.manageSlotAssignmentUrl = manageSlotAssignmentUrl;

        this.schedulingYear = moment().format('YYYY');
        this.customDateId = null;
        this.packageId = 0;
        this.affiliateId = null;
        this.is_group_rate_enabled = 0;

        // By default add more button will be hidden
        $('#add-rate-group-btn, #slots-rate-group-separator, #slots-section').hide();

        // Apply select2 to the filter dropdown
        $("#general-scheduling-year, #general-packages, #general-affiliates").select2();

        // Hide show the options based on year for custom dates
        $("#general-custom-dates").select2({
            matcher: function (params, data) {
                // Only show options that match the year
                const year = $('#general-scheduling-year').val() || new Date().getFullYear();

                if (!year || data.element.dataset.year == year.toString()) {
                    return data;  // Option is valid, show it
                }
                return null;  // Option doesn't match, hide it
            }
        });

        // Filter the rate groups on Filter button click event
        $(document).on('click', '#btn-filter-general-rate-group', (evt) => {
            this.schedulingYear = $('#general-scheduling-year').val();
            this.customDateId = $('#general-custom-dates').val();
            this.packageId = $('#general-packages').val();
            this.affiliateId = $('#general-affiliates').val();

            if (this.schedulingYear && this.customDateId && this.packageId && this.affiliateId == 0) {
                this.getGeneralPackageRates();
                $('#add-rate-group-btn').show();
            }

            if (this.schedulingYear && this.customDateId && this.packageId && this.affiliateId) {
                this.getGeneralAffiliateRates();
                $('#add-rate-group-btn').show();
            }
        });

        // Add new rate group on click of Add Rate Group button
        $(document).on('click', '#add-rate-group-btn', (evt) => {
            this.addNewGeneralRateGroup();
        });

        // Clear the content and filter dropdown on click of Clear button
        $(document).on('click', '#btn-clear-general-rate-group', (evt) => {
            this.clearData();
        });

        // Clear the dynamic content on change of any filter dropdown
        $(document).on('change', '#general-scheduling-year, #general-custom-dates, #general-packages, #general-affiliates', (evt) => {
            document.getElementById('tbody-pckg-rate-grps').innerHTML = '';
            document.getElementById('slots-container').innerHTML = '';
            $('#add-rate-group-btn, #slots-rate-group-separator, #slots-section').hide();
        });
    }

    /**
     * Clear the data added on filter
     */
    clearData() {
        // Clear existing rows
        document.getElementById('tbody-pckg-rate-grps').innerHTML = '';
        document.getElementById('slots-container').innerHTML = '';
        $('#general-scheduling-year, #general-custom-dates, #general-packages, #general-affiliates').val('').trigger('change');

        this.customDateId = null;
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
        document.getElementById('slots-container').innerHTML = '';

        // Clear rateGroups to prepare for new data
        let rateGroups = [];

        $.post(this.getPackageRatesUrl, {
            'scheduling_year': this.schedulingYear,
            'custom_date_id': this.customDateId,
            'tour_package_id': this.packageId,
            'affiliate_id': 0
        }).then((response) => { // Use arrow function to retain 'this' context
            hideLoading();
            this.is_group_rate_enabled = response.is_group_rate_enabled;
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

            if (response.slots.length) {
                this.renderSlotTimes(response.slots);
                $('#slots-rate-group-separator, #slots-section').show();
            } else {
                $('#slots-rate-group-separator, #slots-section').hide();
            }
        }).fail(function(jqXHR) {
            hideLoading();
            showSubmissionError(jqXHR);
        });

        // Template function to generate HTML for each rate group
        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td>
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="custom_date_id" class="form-control customDateId" value="${data.custom_date_id}">
                        <input type="hidden" data-name="tour_package_id" class="form-control packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control affiliateId" value="">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="pb-1">Rate For <req>*</req></th>
                                <th class="pb-1">Age From</th>
                                <th class="pb-1">Age To</th>
                                <th class="pb-1">Rate <req>*</req></th>
                                <th class="pb-1">Permit Fee</th>
                                <th class="pb-1">Tax</th>
                                <th class="pb-1">Min Pax Allowed</th>
                                <th class="pb-1">Max Pax Allowed</th>
                                <th class="pb-1">Actions</th>
                            </tr>
                            <tr>
                                <td><input data-name="rate_for" class="form-control txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td><input data-name="age_from" class="form-control txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td><input data-name="age_to" class="form-control txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td style="width: 126px">
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="rate" class="form-control txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td style="width: 126px">
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="permit_fee" class="form-control txt-permit-fee" type="number" value="${data.permit_fee}" min="0" step="0.01" placeholder="Permit Fee">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Min Pax" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Max Pax">
                                    </div>
                                </td>
                                <td rowspan="2" class="text-center align-middle">
                                    <button type="button" class="btn btn-primary btn-sm btn-general-rate-group-save mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button><br>
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove" title="Remove Package Rate Group">X</button><br>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="3" class="py-1 descth">Description</th>
                                <th class="py-1">Order <req>*</req></th>
                                <th class="py-1">Mapping Key <req>*</req></th>
                                <th class="py-1">Status</th>
                                ${this.is_group_rate_enabled ? `
                                    <th class="py-1">Group Rate Min Pax</th>
                                    <th class="py-1">Group Rate Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td colspan="3" class="descth">
                                    <textarea data-name="description" class="form-control txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="align-top">
                                    <div class="input-group">
                                        <input data-name="orderby" class="form-control txt-orderby" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="input-group">
                                        <input data-name="mapping_key" class="form-control txt-mappingkey" type="number" value="${data.mapping_key}" min="0" max="100" placeholder="Mapping Key" required>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <select data-name="status" class="form-control sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.is_group_rate_enabled ? `
                                    <td class="align-top">
                                        <div class="input-group">
                                            <input data-name="min_group_size" class="form-control txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        <div class="input-group">
                                            <input data-name="max_group_size" class="form-control txt-max-group-size" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                        </table>
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
        document.getElementById('slots-container').innerHTML = '';

        // Clear rateGroups to prepare for new data
        let rateGroups = [];

        $.post(this.getPackageRatesUrl, {
            'scheduling_year': this.schedulingYear,
            'custom_date_id': this.customDateId,
            'tour_package_id': this.packageId,
            'affiliate_id': this.affiliateId,
        }).then((response) => { // Use arrow function to retain 'this' context
            hideLoading();
            this.is_group_rate_enabled = response.is_group_rate_enabled;
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

            if (response.slots.length) {
                this.renderSlotTimes(response.slots);
                $('#slots-rate-group-separator, #slots-section').show();
            } else {
                $('#slots-rate-group-separator, #slots-section').hide();
            }
        }).fail(function(jqXHR) {
            hideLoading();
            showSubmissionError(jqXHR);
        });

        // Template function to generate HTML for each rate group
        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td>
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="custom_date_id" class="form-control customDateId" value="${data.custom_date_id}">
                        <input type="hidden" data-name="tour_package_id" class="form-control packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control affiliateId" value="${data.affiliate_id}">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="pb-1">Rate For <req>*</req></th>
                                <th class="pb-1">Age From</th>
                                <th class="pb-1">Age To</th>
                                <th class="pb-1">Rate <req>*</req></th>
                                <th class="pb-1">Permit Fee</th>
                                <th class="pb-1">Tax</th>
                                <th class="pb-1">Min Pax Allowed</th>
                                <th class="pb-1">Max Pax Allowed</th>
                                <th class="pb-1">Actions</th>
                            </tr>
                            <tr>
                                <td><input data-name="rate_for" class="form-control txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td><input data-name="age_from" class="form-control txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td><input data-name="age_to" class="form-control txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td style="width: 126px">
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="rate" class="form-control txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td style="width: 126px">
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="permit_fee" class="form-control txt-permit-fee" type="number" value="${data.permit_fee}" min="0" step="0.01" placeholder="Permit Fee">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Min Pax" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Max Pax">
                                    </div>
                                </td>
                                <td rowspan="2" class="text-center align-middle">
                                    <button type="button" class="btn btn-primary btn-sm btn-general-rate-group-save mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button><br>
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove" title="Remove Package Rate Group">X</button><br>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="3" class="py-1 descth">Description</th>
                                <th class="py-1">Order <req>*</req></th>
                                <th class="py-1">Mapping Key <req>*</req></th>
                                <th class="py-1">Status</th>
                                ${this.is_group_rate_enabled ? `
                                    <th class="py-1">Group Rate Min Pax</th>
                                    <th class="py-1">Group Rate Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td colspan="3" class="descth">
                                    <textarea data-name="description" class="form-control txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="align-top">
                                    <div class="input-group">
                                        <input data-name="orderby" class="form-control txt-orderby" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="input-group">
                                        <input data-name="mapping_key" class="form-control txt-mappingkey" type="number" value="${data.mapping_key}" min="0" max="100" placeholder="Mapping Key" required>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <select data-name="status" class="form-control sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.is_group_rate_enabled ? `
                                    <td class="align-top">
                                        <div class="input-group">
                                            <input data-name="min_group_size" class="form-control txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        <div class="input-group">
                                            <input data-name="max_group_size" class="form-control txt-max-group-size" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                        </table>
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
            custom_date_id: this.customDateId,
            tour_package_id: this.packageId,
            affiliate_id: this.affiliateId,
            rate_for: '',
            age_from: 0,
            age_to: 0,
            rate: 0,
            permit_fee: 0,
            additional_charge: 0,
            min_pax_allowed: 0,
            max_pax_allowed: 0,
            description: '',
            orderby: 0,
            mapping_key: 0,
            status: 'Active',
            min_group_size: 0, // Add default values for group size if necessary
            max_group_size: 0
        };

        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td>
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="custom_date_id" class="form-control customDateId" value="${data.custom_date_id}">
                        <input type="hidden" data-name="tour_package_id" class="form-control packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control affiliateId" value="${data.affiliate_id}">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="pb-1">Rate For <req>*</req></th>
                                <th class="pb-1">Age From</th>
                                <th class="pb-1">Age To</th>
                                <th class="pb-1">Rate <req>*</req></th>
                                <th class="pb-1">Permit Fee</th>
                                <th class="pb-1">Tax</th>
                                <th class="pb-1">Min Pax Allowed</th>
                                <th class="pb-1">Max Pax Allowed</th>
                                <th class="pb-1">Actions</th>
                            </tr>
                            <tr>
                                <td><input data-name="rate_for" class="form-control txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td><input data-name="age_from" class="form-control txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td><input data-name="age_to" class="form-control txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td style="width: 126px">
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="rate" class="form-control txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td style="width: 126px">
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="permit_fee" class="form-control txt-permit-fee" type="number" value="${data.permit_fee}" min="0" step="0.01" placeholder="Permit Fee">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <span class="input-group-text">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Enter minimum people allowed" required>
                                    </div>
                                </td>
                                <td>
                                    <div class="input-group">
                                        <input type="number" class="form-control txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Enter maximum people allowed">
                                    </div>
                                </td>
                                <td rowspan="2" class="text-center align-middle">
                                    <button type="button" class="btn btn-primary btn-sm btn-general-rate-group-save mb-2" title="Add If Not Exist Else Update Package Rate Group">Save</button><br>
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove" title="Remove Package Rate Group">X</button><br>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="3" class="py-1 descth">Description</th>
                                <th class="py-1">Order <req>*</req></th>
                                <th class="py-1">Mapping Key <req>*</req></th>
                                <th class="py-1">Status</th>
                                ${this.is_group_rate_enabled ? `
                                    <th class="py-1">Group Rate Min Pax</th>
                                    <th class="py-1">Group Rate Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td colspan="3" class="descth">
                                    <textarea data-name="description" class="form-control txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="align-top">
                                    <div class="input-group">
                                        <input data-name="orderby" class="form-control txt-orderby" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <div class="input-group">
                                        <input data-name="mapping_key" class="form-control txt-mappingkey" type="number" value="${data.mapping_key}" min="0" max="100" placeholder="Mapping Key" required>
                                    </div>
                                </td>
                                <td class="align-top">
                                    <select data-name="status" class="form-control sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.is_group_rate_enabled ? `
                                    <td class="align-top">
                                        <div class="input-group">
                                            <input data-name="min_group_size" class="form-control txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        <div class="input-group">
                                            <input data-name="max_group_size" class="form-control txt-max-group-size" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
                                        </div>
                                    </td>
                                ` : ''}
                            </tr>
                        </table>
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
     * Render time slots and add interactive selection.
     * @param {Array} slots - Array of time slot values.
     */
    renderSlotTimes(slots) {
        const slotsContainer = document.getElementById('slots-container');
        slotsContainer.innerHTML = ''; // Clear existing slots if any

        // Dynamically generate slots
        slots.forEach((slot, index) => {
            // Create the slot container div
            const slotDiv = document.createElement('div');
            if (slot.selected) {
                slotDiv.classList.add('slot', 'selected');
            } else {
                slotDiv.classList.add('slot');
            }

            // Create the checkbox input (hidden visually)
            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.id = `slot${index + 1}`;
            checkboxInput.value = slot.time_from;
            checkboxInput.checked = slot.selected;
            checkboxInput.classList.add('slot-checkbox');

            // Create the label for the slot (showing the time)
            const label = document.createElement('label');
            label.setAttribute('for', checkboxInput.id);
            label.classList.add('slot-label');
            label.textContent = slot.time_from;

            // Append the checkbox and label to the slot div
            slotDiv.appendChild(checkboxInput);
            slotDiv.appendChild(label);

            // Add slot div to the container
            slotsContainer.appendChild(slotDiv);

            // Use change event on checkbox for single execution per change
            checkboxInput.addEventListener('change', () => {
                slotDiv.classList.toggle('selected', checkboxInput.checked);

                // Call slot assignment function based on checkbox state
                this.manageSlotAssignment(checkboxInput.value, checkboxInput.checked);
            });
        });
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
                if(!result)
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
        return new Promise((resolve, reject) => {
            $.ajax({
                url: this.deletePackageRatesUrl, // Replace with your actual endpoint
                method: 'DELETE',
                data: { id: rateId },
                success: function (response) {
                    if (response.status === true) {
                        resolve();
                    } else {
                        reject(response);
                    }
                },
                error: function (xhr, status, error) {
                    reject(error);
                }
            });
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
            custom_date_id: row.querySelector('.customDateId').value || null,
            tour_package_id: row.querySelector('.packageId').value,
            affiliate_id: row.querySelector('.affiliateId').value || null,
            rate_for: row.querySelector('.txt-rate-for').value,
            age_from: row.querySelector('.txt-age-from').value,
            age_to: row.querySelector('.txt-age-to').value,
            rate: row.querySelector('.txt-rate').value,
            permit_fee: row.querySelector('.txt-permit-fee').value,
            additional_charge: row.querySelector('.txt-adnl-chrg').value,
            min_pax_allowed: row.querySelector('.txt-min-pax-allowed').value,
            max_pax_allowed: row.querySelector('.txt-max-pax-allowed').value,
            description: row.querySelector('.txt-desc').value,
            orderby: row.querySelector('.txt-orderby').value,
            mapping_key: row.querySelector('.txt-mappingkey').value,
            status: row.querySelector('.sel-status').value,
            min_group_size: row.querySelector('.txt-min-group-size') ? row.querySelector('.txt-min-group-size').value : 0, // New field
            max_group_size: row.querySelector('.txt-max-group-size') ? row.querySelector('.txt-max-group-size').value : 0 // New field
        };

        $.ajax({
            url: this.savePackageRatesUrl,
            method: 'POST',
            data: {
                rateGroup: rateData,
                _token: $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
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
            error: function(error) {
                hideLoading();
                swal('Error saving rate group: ' + error, '', 'error');
            }
        });
    }

    /**
     * Assign slot to the tour package rates
     */
    manageSlotAssignment(timeSlot, status) {
        showLoading();

        // Collect rate group data
        const rateData = {
            scheduling_year: this.schedulingYear,
            custom_date_id: this.customDateId,
            tour_package_id: this.packageId,
            affiliate_id: this.affiliateId,
            time_from: timeSlot,
            status: status
        };

        $.ajax({
            url: this.manageSlotAssignmentUrl,
            method: 'POST',
            data: rateData,
            success: function(response) {
                hideLoading();

                if (response.status == 'success') {
                    swal(response.message, '', 'success');
                } else {
                    swal(response.message, '', 'error');
                }
            },
            error: function(error) {
                hideLoading();
                swal('Error saving slot: ' + error, '', 'error');
            }
        });
    }
}
