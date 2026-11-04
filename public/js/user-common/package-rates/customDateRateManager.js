// customDateRateManager.js

class CustomDateRateManager {
    constructor(getPackageRatesUrl, getTimeSlotsUrl, saveCustomDateRatesUrl, deleteCustomDateRateUrl, deletePackageRatesUrl, taxPercent, taxPercentType, cashPriceEnabled) {
        // Routes
        this.getPackageRatesUrl = getPackageRatesUrl;
        this.getTimeSlotsUrl = getTimeSlotsUrl;
        this.saveCustomDateRatesUrl = saveCustomDateRatesUrl;
        this.deleteCustomDateRateUrl = deleteCustomDateRateUrl;
        this.deletePackageRatesUrl = deletePackageRatesUrl;

        // Tax percentages
        this.taxPercent = taxPercent;
        this.taxPercentType = taxPercentType;
        this.cashPriceEnabled = cashPriceEnabled;

        // Payload
        this.rateGroups = [];
        this.selectedSlots = [];

        // Select elements
        this.tableContainer = document.getElementById('table-container');
        this.formContainer = document.getElementById('form-container');
        this.addButton = document.querySelector('.add-custom-date-rate');
        this.toggleIcon = this.addButton.querySelector('i');
        this.toggleText = this.addButton.querySelector('span');

        // Additional elements for conditional display
        this.customDateId = document.getElementById('custom_date_id');
        this.schedulingYear = document.getElementById('scheduling_year');
        this.customDateName = document.getElementById('custom_date_name');
        this.packageName = document.getElementById('package_name');
        this.affiliateName = document.getElementById('affiliate_name');
        this.dateRange = document.getElementById('date_range');
        this.applyOnSlots = document.getElementById('apply_on_slots');
        this.rateGroupSection = document.getElementById('rate-group-section');
        this.slotsSection = document.getElementById('slots-section-container');
        this.saveButton = document.getElementById('save-button');

        // Global variable to track the state of group rate
        this.isGroupRateEnabled = false;
        this.isPartnerPackage = 0;
        this.natFeesPercentage = 0;
        this.action = '';

        // Initialize events
        this.initializeEvents();

        // Initialize the date range picker with specified format
        $('#date_range').daterangepicker({
            autoUpdateInput: true,
            locale: {
                format: 'MM/DD/YYYY',
                cancelLabel: 'Clear'
            }
        });

        // Apply event listeners to update the input field with selected date range
        $('#date_range').on('apply.daterangepicker', (ev, picker) => {
            $(this.dateRange).val(picker.startDate.format('MM/DD/YYYY') + ' - ' + picker.endDate.format('MM/DD/YYYY'));
            this.checkFormFields(ev); // Check fields after date is selected
        });

        $('#date_range').on('cancel.daterangepicker', (ev, picker) => {
            $(this.dateRange).val(''); // Clear input on cancel
            this.checkFormFields(ev); // Check fields after clearing date
        });

        // Add new rate group on click of Add Rate Group button
        $(document).on('click', '.add-custom-date-rate', (evt) => {
            this.action = 'add';
        });

        // Add new rate group on click of Add Rate Group button
        $(document).on('click', '.edit-custom-date-rate', (evt) => {
            this.action = 'edit';
            this.editCustomRateDate(evt);
        });

        // Add new rate group on click of Add Rate Group button
        $(document).on('click', '.delete-custom-date-rate', (evt) => {
            this.deleteCustomRateDate(evt);
        });

        // Add new rate group on click of Add Rate Group button
        $(document).on('click', '#add-rate-group-btn', (evt) => {
            this.addNewGeneralRateGroup();
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
     * Method to initialize all event listeners
     */
    initializeEvents() {
        // Reset form fields and toggle the form view when "Add New" is clicked
        this.addButton.addEventListener('click', () => {
            this.resetFormFields();
            this.toggleForm();
        });

        // Add event listeners for form fields to conditionally display sections
        const fieldsToWatch = [
            { element: this.schedulingYear, event: 'change' },
            { element: this.customDateName, event: 'input' },
            { element: this.packageName, event: 'change' },
            { element: this.affiliateName, event: 'change' },
            { element: this.dateRange, event: 'input' },
            { element: this.applyOnSlots, event: 'change' }
        ];

        fieldsToWatch.forEach(({ element, event }) => {
            if (element) {
                element.addEventListener(event, (e) => this.checkFormFields(e));
            }
        });
    }

    /**
     * Resets the form fields and hides associated sections.
     */
    resetFormFields() {
        const currentYear = new Date().getFullYear();
        this.customDateId.value = 0;
        this.schedulingYear.value = currentYear;
        this.customDateName.value = '';
        this.packageName.value = '';
        this.affiliateName.value = '';
        this.dateRange.value = '';
        this.applyOnSlots.value = 0;

        // Clear rate group and slots sections
        document.getElementById('tbody-pckg-rate-grps').innerHTML = '';
        this.rateGroupSection.style.display = 'none';
        document.getElementById('slots-container').innerHTML = '';
        this.slotsSection.style.display = 'none';

        // Enable inputs
        ['scheduling_year', 'package_name', 'affiliate_name', 'date_range', 'apply_on_slots']
            .forEach(id => document.getElementById(id)?.removeAttribute('disabled'));

        // Remove Save button
        this.removeSaveButton();
    }

    /**
     * Method to check if all fields are selected and manage UI visibility accordingly.
     * @param {*} event 
     */
    checkFormFields(event) {
        const customDateId = parseInt(this.customDateId.value || 0, 10);
        const schedulingYear = this.schedulingYear.value.trim();
        const customDateName = this.customDateName.value.trim();
        const packageName = this.packageName.value.trim();
        const dateRange = this.dateRange.value.trim();
        const applyOnSlots = parseInt(this.applyOnSlots.value || 0, 10);

        const areMainFieldsValid = schedulingYear && customDateName && packageName && dateRange;

        // Toggle rate group section
        this.toggleSection(this.rateGroupSection, areMainFieldsValid);

        // Show slots only if conditions are met (even in edit mode)
        const shouldShowSlots = areMainFieldsValid && applyOnSlots === 1;
        this.toggleSection(this.slotsSection, shouldShowSlots);

        if (shouldShowSlots && customDateId === 0) {
            this.fetchSlotTimes();
        }

        // Add Save button dynamically if any section is visible
        if (areMainFieldsValid) {
            this.addSaveButton();
        } else {
            this.removeSaveButton();
        }

        // Handle group rate enablement
        this.setGroupRateEnabled(event);
    }

    /**
     * Helper method to toggle the visibility of a section.
     * @param {HTMLElement} section - The section to show or hide.
     * @param {boolean} shouldShow - Whether the section should be visible.
     */
    toggleSection(section, shouldShow) {
        section.style.display = shouldShow ? 'block' : 'none';
    }

    /**
     * Sets the global `isGroupRateEnabled` based on the selected package option.
     */
    setGroupRateEnabled(event) {
        if (this.packageName.value) {
            const selectedOption = this.packageName.options[this.packageName.selectedIndex];
            this.isGroupRateEnabled = selectedOption?.getAttribute('data-is-group-rate-enabled') === '1';

            if (this.action === 'add') {
                const payload = {
                    custom_date_id: parseInt(this.customDateId.value || 0, 10),
                    scheduling_year: this.schedulingYear.value.trim(),
                    tour_package_id: this.packageName.value.trim(),
                    affiliate_id: this.affiliateName.value.trim() || 0,
                    apply_on_slots: parseInt(this.applyOnSlots.value || 0, 10),
                    executed: false
                };

                this.getGeneralPackageRates(event, payload);
            }
        }
    }

    /**
     * Method to dynamically add the Save button after the appropriate section
     */
    addSaveButton() {
        // Remove any existing Save button
        const existingSaveButtonWrapper = document.getElementById('save-button-wrapper');
        if (existingSaveButtonWrapper) existingSaveButtonWrapper.remove();

        // Create a wrapper div for right alignment
        const saveButtonWrapper = document.createElement('div');
        saveButtonWrapper.id = 'save-button-wrapper';
        saveButtonWrapper.className = 'd-flex justify-content-end mb-2 text-end';

        // Create a new Save button
        const saveButton = document.createElement('button');
        saveButton.id = 'save-button';
        saveButton.className = 'btn btn-warning mt-3 btn-warning-small';
        saveButton.textContent = 'Save';
        saveButton.type = 'button';
        saveButton.addEventListener('click', (event) => {
            this.validateRateGroup(event);
        })

        // Append the Save button to the wrapper div
        saveButtonWrapper.appendChild(saveButton);

        // Append the Save button wrapper based on Slots Section visibility
        if (this.slotsSection.style.display === 'block') {
            this.slotsSection.insertAdjacentElement('afterend', saveButtonWrapper);
        } else {
            this.rateGroupSection.insertAdjacentElement('afterend', saveButtonWrapper);
        }
    }

    /**
     * Remove the save button
     */
    removeSaveButton() {
        // Remove any existing Save button
        const existingSaveButton = document.getElementById('save-button');
        if (existingSaveButton) existingSaveButton.remove();
    }

    /**
     * Function to validate and gather rate group data
     */
    validateRateGroup(event) {
        this.rateGroups = [];
        this.selectedSlots = [];

        const form = event.target.closest('form');  // Get the form of the clicked save button
        const rateGroupInputs = form.querySelectorAll('input, select, textarea');
        let isRatesValid = true;
        let isSlotsValid = true;

        const tbody = document.getElementById('tbody-pckg-rate-grps');
        if (!tbody || tbody.querySelectorAll('tr').length === 0) {
            isRatesValid = false;
        }

        // Loop through the inputs to check if required fields are filled
        rateGroupInputs.forEach(input => {
            const value = input.value.trim();

            // If the input has the 'txt-required' class and it's empty, mark as invalid
            if (input.classList.contains('txt-required') && !value) {
                input.classList.add('is-invalid');
                isRatesValid = false;
            } else {
                input.classList.remove('is-invalid');
            }
        });

        let selectedSlots = [];
        if (this.applyOnSlots.value != 0) {
            // Assuming the dynamically created checkboxes have class 'slot-checkbox'
            const checkedSlots = form.querySelectorAll('.slot-checkbox:checked');
            if (checkedSlots.length === 0) {
                isSlotsValid = false;
                // You can mark the slot section as invalid
                const slotsSection = form.querySelector('#slots-container'); // Assuming slots are in a div with class 'slots'
                if (slotsSection) {
                    slotsSection.classList.add('is-invalid'); // Highlight the slots section with error
                }
            } else {
                // Remove error styling if slots are checked
                const slotsSection = form.querySelector('#slots-container');
                if (slotsSection) {
                    slotsSection.classList.remove('is-invalid');
                }

                // Collect the time_from values of the checked slots
                checkedSlots.forEach(slot => {
                    selectedSlots.push(slot.value);
                });
            }
        }

        if (isRatesValid && isSlotsValid) {
            // Validate cash rate for each rate group row
            const rateGroupRows = form.querySelectorAll('.pckg-rate-grp');
            for (const row of rateGroupRows) {
                if (this.cashPriceEnabled == 1 && row.querySelector('.chk-is-cash-rate') && row.querySelector('.chk-is-cash-rate').checked) {
                    const cashRateValue = parseFloat(row.querySelector('.txt-cash-rate') ? row.querySelector('.txt-cash-rate').value : 0);
                    if (!(cashRateValue > 0)) {
                        swal('Cash rate must be greater than 0 when cash rate is enabled.', '', 'error');
                        return;
                    }
                }
            }

            // Proceed to collect the data if valid
            const rateGroupData = this.getRateGroupData(form);  // Collect data from all rate groups
            this.rateGroups = rateGroupData;  // Dynamic rate groups
            this.selectedSlots = selectedSlots; // Selected slot times
            this.saveData();
        } else {
            swal(`There are validation errors`, '', 'error');
        }
    }

    /**
     * Method to handle saving data when Save button is clicked
     */
    saveData() {
        // Gather data from form fields (you can customize this as needed)
        const customRateDate = {
            custom_date_id: this.customDateId.value || 0,
            custom_date_name: this.customDateName.value,
            tour_package_id: this.packageName.value,
            affiliate_id: this.affiliateName.value || 0,
            date: this.dateRange.value,
            apply_on_slots: this.applyOnSlots.value,
            rate_groups: this.rateGroups,
            slot_times: this.selectedSlots,
            is_group_rate: (this.isGroupRateEnabled == 1) ? 1 : 0
        };

        // Log data to verify
        console.log('Saving data:', customRateDate);

        // Add actual save logic here, such as making an AJAX request to the server
        $.post(this.saveCustomDateRatesUrl, customRateDate, (response) => {
            if (response == true) {
                console.log('Data saved successfully:', response);
                swal('Data saved successfully', '', 'success')
                    .then(function () {
                        location.reload();
                    });
            } else {
                swal(response.message, '', 'error');
            }
        }).fail((error) => {
            swal('Error saving data', '', 'error');
            console.error('Error saving data:', error);
        });
    }

    /**
     * Function to collect the data from all rate group forms
     */
    getRateGroupData(form) {
        const allRateGroups = [];  // Array to store all the rate group data

        // Get all the rows with class 'pckg-rate-grp' inside the tbody of the given form
        const rateGroupRows = form.querySelectorAll('.pckg-rate-grp');

        rateGroupRows.forEach((row, index) => {
            const rateGroup = {};  // Object to hold the data for this specific rate group

            // Loop through each input, select, or textarea inside this row to collect data
            row.querySelectorAll('input, select, textarea').forEach(input => {
                const name = input.getAttribute('data-name');
                if (name) {
                    let value;
                    if (input.type === 'checkbox') {
                        value = input.checked ? 1 : 0;
                    } else {
                        value = input.value.trim();
                    }

                    rateGroup[name] = value;  // Store the value in the rateGroup object
                }
            });

            // Optionally, add an index to each rate group to differentiate them
            rateGroup.index = index;

            // Push the rateGroup object into the allRateGroups array
            allRateGroups.push(rateGroup);
        });

        return allRateGroups;  // Return the array of all rate group data
    }

    /**
     * Toggle between form and table views
     */
    toggleForm() {
        if (this.formContainer.style.display === 'none') {
            this.showForm();
        } else {
            this.showTable();
        }
    }

    /**
     * Show form and hide table
     */
    showForm() {
        this.formContainer.style.display = 'block';
        this.tableContainer.style.display = 'none';

        // Update icon and text to indicate "Cancel"
        this.toggleIcon.classList.remove('fa-plus');
        this.toggleIcon.classList.add('fa-times');
        this.toggleText.textContent = 'Cancel';
    }

    /**
     * Show table and hide form
     */
    showTable() {
        this.formContainer.style.display = 'none';
        this.tableContainer.style.display = 'block';

        // Update icon and text back to "Add New"
        this.toggleIcon.classList.remove('fa-times');
        this.toggleIcon.classList.add('fa-plus');
        this.toggleText.textContent = 'Add New';
    }

    /**
     * Add new rate group to the package
     */
    addNewGeneralRateGroup() {
        const newGeneralRateGroup = {
            id: '',
            scheduling_year: this.schedulingYear.value,
            tour_package_id: this.packageName.value,
            affiliate_id: this.affiliateName.value || 0,
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
                                <th class="th-custom-css" style="width: 230px;">Rate For <req>*</req></th>
                                <th class="th-custom-css">Age From</th>
                                <th class="th-custom-css">Age To</th>
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Rate <req>*</req></th>
                                <th class="th-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``}>Permit Fee</th>
                                ${this.isPartnerPackage ? `
                                    <th class="th-custom-css">Partner Fee Type</th>
                                    <th class="th-custom-css">Partner Fee</th>
                                ` : ''}
                                <th class="th-custom-css">Tax</th>

                                <th colspan="2" class="th-custom-css">Actions</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from txt-required" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to txt-required" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
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
                                <td class="td-custom-css" style="width: 155px">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">$</span>
                                        <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                    </div>
                                </td>
                                ` : ''}
                                <td class="td-custom-css"  style="${this.isPartnerPackage ? `width: 200px` : `width: 180px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>

                                <td class="td-custom-css text-center ${this.isPartnerPackage ? `` : `whitespace-nowrap`}" colspan="2">
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button>
                                </td>
                            </tr>
                            <tr>
                                <th class="th-custom-css descth">Description</th>    
                                <th class="th-custom-css min-w-36">Min Pax</th>
                                <th class="th-custom-css min-w-36">Max Pax</th>
                                <th class="th-custom-css">Order <req>*</req></th>
                                <th class="th-custom-css">Mapping Key <req>*</req></th>
                                <th class="th-custom-css">Infant Rate</th>
                                <th class="th-custom-css">Approval</th>
                                <th class="th-custom-css">Status</th>
                                ${this.isGroupRateEnabled ? `
                                    <th class="th-custom-css">Group Rate Min Pax</th>
                                    <th class="th-custom-css">Group Rate Max Pax</th>
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
                                        <input data-name="orderby" class="form-control form-custom-input txt-orderby txt-required" type="number" value="${data.orderby}" min="0" max="100" placeholder="Order No." required>
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
                                    <select data-name="is_infant_rate" class="form-control form-custom-input select-infant-rate">
                                        <option value="0" ${data.is_infant_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.is_infant_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="approval" class="form-control form-custom-input sel-approval">
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
                                    <td class="td-custom-css">
                                        <div class="input-group flex items-center">
                                            <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size txt-required" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="td-custom-css">
                                        <div class="input-group flex items-center">
                                            <input data-name="max_group_size" class="form-control form-custom-input txt-max-group-size txt-required" type="number" value="${data.max_group_size}" min="0" placeholder="Max Group Size">
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
                                            <input data-name="cash_rate" class="form-control form-custom-input !rounded-l-none txt-cash-rate" ${data.is_cash_rate == 0 ? 'disabled' : ''} value="${data.cash_rate}" type="number" value="0" min="0" step="0.01" placeholder="Cash Rate">
                                        </div>
                                </td>
                                <td class="td-custom-css" style="width:165px;">
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
                            <span class="created-by mr-1">Author: <span>You</span></span>
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
                </td>
            </tr>
        `;

        // Append the new rate group to the table body
        document.getElementById('tbody-pckg-rate-grps').insertAdjacentHTML('beforeend', packageRateGroupTemplate(newGeneralRateGroup));

        // Attach the remove event to the new button
        const removeButton = document.querySelector('#tbody-pckg-rate-grps tr:last-child .btn-general-rate-group-remove');
        removeButton.addEventListener('click', (event) => this.removeGeneralRateGroup(event));
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
            method: 'POST',   // Laravel-friendly for DELETE
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
     * Fetch time slots and add interactive selection.
     */
    fetchSlotTimes() {
        const slotsContainer = document.getElementById('slots-container');
        slotsContainer.innerHTML = ''; // Clear existing slots if any

        if (this.applyOnSlots.value == 1) {
            let payload = {
                scheduling_year: this.schedulingYear.value,
                tour_package_id: this.packageName.value,
                date: this.dateRange.value,
                _token: $('meta[name="csrf-token"]').attr('content')
            }

            $.ajax({
                url: this.getTimeSlotsUrl,
                method: 'POST',
                data: payload,
                success: (response) => {
                    hideLoading();
                    if (response.slots.length) {
                        // Dynamically generate slots
                        this.renderSlotTimes(response.slots);
                    }
                },
                error: function (error) {
                    hideLoading();
                    swal('Error fetching slots: ' + error, '', 'error');
                }
            });
        }
    }

    /**
     * Render time slots and add interactive selection.
     */
    renderSlotTimes(slots) {
        $('#slots-section').show();
        this.slotsSection.style.display = 'block';

        const slotsContainer = document.getElementById('slots-container');

        slots.forEach((slot, index) => {
            // Create the slot container div
            const slotDiv = document.createElement('div');
            if (slot?.selected) {
                slotDiv.classList.add('slot', 'inline-block', 'selected');
            } else {
                slotDiv.classList.add('slot', 'inline-block');
            }

            // Create the checkbox input (hidden visually)
            const checkboxInput = document.createElement('input');
            checkboxInput.type = 'checkbox';
            checkboxInput.id = `slot${index + 1}`;
            checkboxInput.value = slot.time_from;
            checkboxInput.classList.add('slot-checkbox', 'hidden');

            if (slot?.selected) {
                checkboxInput.checked = slot.selected;
            }

            // Create the label for the slot (showing the time)
            const label = document.createElement('label');
            label.setAttribute('for', checkboxInput.id);
            label.classList.add('slot-label', 'bg-[#F3F4F6]', 'px-4', 'py-2', 'rounded-md', 'inline-block', 'text-sm', 'shadow', 'mr-2', 'mb-2', 'min-w-28', 'font-medium', 'text-center');
            label.textContent = slot.time_from;

            // Append the checkbox and label to the slot div
            slotDiv.appendChild(checkboxInput);
            slotDiv.appendChild(label);

            // Add slot div to the container
            slotsContainer.appendChild(slotDiv);

            // Use change event on checkbox for single execution per change
            checkboxInput.addEventListener('change', () => {
                slotDiv.classList.toggle('selected', checkboxInput.checked);
            });
        });

        // Reset and re-attach the Select All button functionality
        const selectAllButton = document.getElementById('select-all-btn');
        selectAllButton.textContent = 'Select All'; // Reset button text
        selectAllButton.replaceWith(selectAllButton.cloneNode(true)); // Remove old listeners
        const newSelectAllButton = document.getElementById('select-all-btn');

        newSelectAllButton.addEventListener('click', () => {
            const allCheckboxes = slotsContainer.querySelectorAll('.slot-checkbox');
            const isAllSelected = Array.from(allCheckboxes).every(checkbox => checkbox.checked);

            // Toggle all checkboxes
            allCheckboxes.forEach(checkbox => {
                checkbox.checked = !isAllSelected;
                checkbox.dispatchEvent(new Event('change')); // Trigger change event
            });

            // Update button text dynamically
            newSelectAllButton.textContent = isAllSelected ? 'Select All' : 'Deselect All';
        });
    }

    /**
     * Method to edit custom date rate
     * @param {*} evt 
     */
    editCustomRateDate(evt) {
        // Get data attributes from the button
        const {
            id,
            package: packageId,
            affiliate: affiliateId,
            name,
            start,
            end,
            apply
        } = $(evt.currentTarget).data();

        // Parse start and end dates
        const startDate = this.parseToLocalMidnight(start);
        const endDate = this.parseToLocalMidnight(end);

        // Extract the year
        const year = startDate.getFullYear();

        // Format dates for daterangepicker ("MM/DD/YYYY")
        const formattedStart = this.formatDate(startDate);
        const formattedEnd = this.formatDate(endDate);

        // Set form data
        $('#custom_date_id').val(id);
        $('#scheduling_year').val(year).prop('disabled', true);
        $('#custom_date_name').val(name);
        $('#package_name').val(packageId).prop('disabled', true);
        $('#affiliate_name').val(affiliateId).prop('disabled', true);
        $('#date_range').prop('disabled', true);
        $('#apply_on_slots').val(apply).prop('disabled', true);

        // Check if daterangepicker is initialized
        if ($('#date_range').data('daterangepicker')) {
            // Set start and end dates in the daterangepicker
            $('#date_range').data('daterangepicker').setStartDate(formattedStart);
            $('#date_range').data('daterangepicker').setEndDate(formattedEnd);
        } else {
            console.error('Date range picker is not initialized.');
        }

        this.toggleForm();

        const payload = {
            custom_date_id: id,
            scheduling_year: year,
            tour_package_id: packageId,
            affiliate_id: affiliateId || 0,
            apply_on_slots: apply,
            executed: true
        };

        this.getGeneralPackageRates(evt, payload);
    }

    /**
     * Update route param data for filter.
     */
    getGeneralPackageRates(event, payload) {
        showLoading();

        // Clear existing rows
        document.getElementById('tbody-pckg-rate-grps').innerHTML = '';
        document.getElementById('slots-container').innerHTML = '';

        // Clear rateGroups to prepare for new data
        let rateGroups = [];

        $.post(this.getPackageRatesUrl, {
            'scheduling_year': payload.scheduling_year,
            'custom_date_id': payload.custom_date_id,
            'tour_package_id': payload.tour_package_id,
            'affiliate_id': payload.affiliate_id
        }).then((response) => { // Use arrow function to retain 'this' context
            hideLoading();

            if (payload.executed) {
                this.checkFormFields(event);
            }

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
            });

            if (payload.apply_on_slots && response.slots.length) {
                this.renderSlotTimes(response.slots);
                $('#slots-section').show();
            } else if (payload.executed) {
                $('#slots-section').hide();
            }
        }).fail(function (jqXHR) {
            hideLoading();
            showSubmissionError(jqXHR);
        });

        // Template function to generate HTML for each rate group
        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td class="px-2 py-1.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm">
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control form-custom-input hid-rate-id" value="${payload.executed ? data.id : ''}">
                        <input type="hidden" data-name="scheduling_year" class="form-control form-custom-input schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="custom_date_id" class="form-control form-custom-input customDateId" value="${data.custom_date_id || ''}">
                        <input type="hidden" data-name="tour_package_id" class="form-control form-custom-input packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control form-custom-input affiliateId" value="${data.affiliate_id || ''}">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="th-custom-css" style="width: 230px;">Rate For <req>*</req></th>
                                <th class="th-custom-css">Age From</th>
                                <th class="th-custom-css">Age To</th>
                                <th class="th-custom-css" ${this.isPartnerPackage ? `` : `colspan="2"`}>Rate <req>*</req></th>
                                <th class="th-custom-css" ${this.isGroupRateEnabled ? `colspan="2"` : ``}>Permit Fee</th>
                                ${this.isPartnerPackage ? `
                                    <th class="th-custom-css">Partner Fee Type</th>
                                    <th class="th-custom-css">Partner Fee</th>
                                ` : ''}
                                <th class="th-custom-css">Tax</th>

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
                                <td class="td-custom-css" style="width: 155px">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">$</span>
                                        <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                    </div>
                                </td>
                                ` : ''}
                                <td class="td-custom-css"  style="${this.isPartnerPackage ? `width: 200px` : `width: 180px`}">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                
                                <td colspan="2" class="td-custom-css text-center ${this.isPartnerPackage ? `` : `whitespace-nowrap`}">
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button>
                                </td>
                            </tr>
                            <tr>
                            <th class="th-custom-css descth">Description</th>
                            <th class="th-custom-css">Min Pax</th>
                                <th class="th-custom-css">Max Pax</th>
                                <th class="th-custom-css">Order <req>*</req></th>
                                <th class="th-custom-css">Mapping Key <req>*</req></th>
                                <th class="th-custom-css">Infant Rate</th>
                                <th class="th-custom-css">Approval</th>
                                <th class="th-custom-css">Status</th>
                                ${this.isGroupRateEnabled ? `
                                    <th class="th-custom-css">Group Rate Min Pax</th>
                                    <th class="th-custom-css">Group Rate Max Pax</th>
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
                                    <select data-name="is_infant_rate" class="form-control form-custom-input select-infant-rate">
                                        <option value="0" ${data.is_infant_rate == 0 ? 'selected' : ''}>No</option>
                                        <option value="1" ${data.is_infant_rate == 1 ? 'selected' : ''}>Yes</option>
                                    </select>
                                </td>
                                <td class="td-custom-css">
                                    <select data-name="approval" class="form-control form-custom-input sel-approval">
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
                                    <td class="td-custom-css">
                                        <div class="input-group flex items-center">
                                            <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="td-custom-css">
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
                                        <input data-name="cash_rate" class="form-control form-custom-input !rounded-l-none txt-cash-rate" ${data.is_cash_rate != 1 ? 'disabled' : ''} type="number" value="${data.cash_rate}" min="0" step="0.01" placeholder="Cash Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" style="width:165px;">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="cash_tax" class="form-control form-custom-input !rounded-l-none txt-cash-tax" type="number" ${data.is_cash_rate != 1 ? 'disabled' : ''} value="${data.cash_tax}" min="0" max="99999.99" step="0.01" placeholder="Cash Tax">
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
                </td>
            </tr>
        `;
    }

    /**
     * Delete the custom dates, rates and it's slots
     */
    deleteCustomRateDate(evt) {
        // Get data attributes from the button
        const id = $(evt.currentTarget).data('id');

        swal({
            title: "Delete?",
            text: "Are you sure to delete the record?",
            icon: "warning",
            buttons: {
                no: {
                    text: 'No',
                    value: false,
                },
                yes: {
                    text: 'Yes',
                    className: 'btn-danger',
                }
            }
        }).then((result) => {
            if (result) {
                showLoading();

                $.ajax({
                    type: "post",
                    url: this.deleteCustomDateRateUrl,
                    data: {
                        id: id
                    },
                    success: (data) => {
                        if (data?.success == true) {
                            swal('The record has been deleted.', '', 'success')
                                .then(function () {
                                    location.reload();
                                });
                        } else {
                            swal(data.message, '', 'error');
                        }

                        hideLoading();
                    },
                    error: (data) => {
                        hideLoading();
                        showSubmissionError(data);
                    }
                });
            }
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

    /**
     * Helper function to set date to local midnight
     * @param {*} dateString 
     * @returns 
     */
    parseToLocalMidnight(dateString) {
        const [year, month, day] = dateString.split('-').map(Number);
        return new Date(year, month - 1, day); // Note: Month is 0-indexed in JS
    }

    /**
     * Function to format date as "MM/DD/YYYY"
     * @param {*} date 
     * @returns 
     */
    formatDate(date) {
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Months are 0-indexed
        const day = String(date.getDate()).padStart(2, '0');
        const year = date.getFullYear();
        return `${month}/${day}/${year}`;
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
}
