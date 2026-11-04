// globalRateManager.js

class GlobalRateManager {
    constructor(packages, affiliates, taxPercent, taxPercentType, departmentPackage, getPackageRatesUrl, deletePackageRatesUrl, saveGlobalRatesUrl, saveScheduleEventUrl, fetchScheduleEventUrl, deleteScheduleEventUrl, cashPriceEnabled) {
        // Initialize properties
        this.packages = packages;
        this.affiliates = affiliates;
        this.scheduling_year = new Date().getFullYear();

        // Initialize department package
        this.departmentPackage = departmentPackage;

        // Initialize tax percentages
        this.taxPercent = taxPercent;
        this.taxPercentType = taxPercentType;
        this.cashPriceEnabled = cashPriceEnabled;

        // Initialize properties related to rate fetching and filtering
        this.getPackageRatesUrl = getPackageRatesUrl;
        this.deletePackageRatesUrl = deletePackageRatesUrl;
        this.saveGlobalRatesUrl = saveGlobalRatesUrl;
        this.saveScheduleEventUrl = saveScheduleEventUrl;
        this.fetchScheduleEventUrl = fetchScheduleEventUrl;
        this.deleteScheduleEventUrl = deleteScheduleEventUrl;

        // Initialize payload object for rate group details
        this.payload = {};
        this.rateGroups = [];

        // Initialize properties related to selected package and affiliate
        this.selectedPackageId = null;
        this.selectedAffiliateId = null;
        this.natFeesPercentage = 0;
        this.isGroupRateEnabled = false;
        this.isPartnerPackage = 0;

        this.init();

        $(document).on('change', '#scheduling-year', (evt) => {
            // Update the scheduling year
            this.scheduling_year = evt.target.value || new Date().getFullYear();

            // Determine the type based on the selected affiliate or package
            if (this.selectedAffiliateId || this.selectedPackageId) {
                const type = this.selectedAffiliateId ? "affiliates" : "packages";

                const selectedItem = type === "packages"
                    ? this.packages.find(pkg => pkg.id === this.selectedPackageId)
                    : this.affiliates.find(aff => aff.id === this.selectedAffiliateId);

                if (selectedItem) {
                    // Call the respective handler
                    if (type === "packages") {
                        this.updateRightSection(selectedItem, 'package');
                    } else {
                        this.updateRightSection(selectedItem, 'affiliate');
                    }
                } else {
                    console.error(`Selected ${type.slice(0, -1)} not found.`);
                }
            }
        });

        $(document).on('click', '#add-rate-group-btn', (evt) => {
            if (this.selectedPackageId || this.selectedAffiliateId) {
                this.addNewGeneralRateGroup(evt);
            } else {
                swal('Select an option to add rate groups.', '', 'error');
            }
        });

        $(document).on('click', '#add-rate-schedule-btn', (evt) => {
            if (this.selectedPackageId) {
                var chargeNumber = $('#charge-amount').val();
                if (chargeNumber > 0) {
                    this.openSchedulePopup();
                } else {
                    swal('Add charge amount or percentage to schedule event for rate groups.', '', 'error');
                }
            } else {
                swal('Select package to schedule event for rate groups.', '', 'error');
            }
        });

        $(document).on('click', '#save-schedule-event', (evt) => {
            var scheduleDateTime = $('#scheduling-date-time').val();
            console.log(scheduleDateTime);
            if (scheduleDateTime) {
                this.saveScheduleEvent(scheduleDateTime);
            } else {
                swal('Select date time to schedule event for rate groups.', '', 'error');
            }
        });

        $(document).on('click', '#save-rate-group-btn', (evt) => {
            if (this.selectedPackageId || this.selectedAffiliateId) {
                this.validateRateGroup(evt);
            } else {
                swal('Select an option to save rate groups.', '', 'error');
            }
        });

        $(document).on('click', '#charge-button', (evt) => {
            if (this.selectedPackageId || this.selectedAffiliateId) {
                this.validateCharge(evt, 'charge');
            } else {
                swal('Select an option to charge the rate groups.', '', 'error');
            }
        });

        $(document).on('click', '#reset-button', (evt) => {
            if (this.selectedPackageId || this.selectedAffiliateId) {
                this.validateCharge(evt, 'reset');
            } else {
                swal('Select an option to reset the applied charge.', '', 'error');
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
    }

    init() {
        this.populatePackages();
        this.setupSearch("packages", "search-packages", this.packages, "packages-list");
        this.setupSearch("affiliates", "search-affiliates", this.affiliates, "affiliates-list");
    }

    populatePackages() {
        const packagesList = document.getElementById("packages-list");
        packagesList.innerHTML = ''; // Clear the list first

        this.packages.forEach((pkg) => {
            const button = this.createListItem(pkg, "packages");
            packagesList.appendChild(button);
        });

        // If a package is selected, highlight it again
        if (this.selectedPackageId) {
            this.highlightSelectedItem("packages-list", this.selectedPackageId);
        }
    }

    populateAffiliates() {
        const affiliatesList = document.getElementById("affiliates-list");
        affiliatesList.innerHTML = ''; // Clear the list first

        let affiliates = this.affiliates;
        // Include the "All Affiliates" item in the list
        if (this.departmentPackage == 0) {
            affiliates = [
                { id: "all", name: "All Affiliates" },
                ...this.affiliates,
            ];
        }

        affiliates.forEach((affiliate) => {
            const button = this.createListItem(affiliate, "affiliates");
            affiliatesList.appendChild(button);
        });

        // If an affiliate is selected, highlight it again
        if (this.selectedAffiliateId) {
            this.highlightSelectedItem("affiliates-list", this.selectedAffiliateId);
        }
    }

    createListItem(item, type) {
        const button = document.createElement("button");
        button.classList.add("list-group-item", "list-group-item-action", "px-4", "py-2", "border", "border-[#E5E7EB]", "text-sm", "text-[#020817]", "text-left", "w-full", '-mt-[1px]', 'font-medium');
        button.setAttribute("type", "button");
        button.setAttribute("data-id", item.id);
        button.innerText = item.name;

        button.addEventListener("click", () => {
            if (type === "packages") {
                this.handlePackageSelection(item, button);
            } else {
                this.handleAffiliateSelection(item, button);
            }
        });

        return button;
    }

    setupSearch(type, searchBarId, list, listContainerId) {
        const searchBar = document.getElementById(searchBarId);
        const listContainer = document.getElementById(listContainerId);

        // Include the "All Affiliates" item in the list
        if (type === "affiliates") {
            if (this.departmentPackage == 0) {
                list = [
                    { id: "all", name: "All Affiliates" },
                    ...list,
                ];
            }
        }

        searchBar.addEventListener("input", (event) => {
            const query = event.target.value.toLowerCase();
            listContainer.innerHTML = ""; // Clear list

            // Filter and display the items
            list
                .filter((item) => item.name.toLowerCase().includes(query))
                .forEach((filteredItem) => {
                    const button = this.createListItem(filteredItem, type);
                    listContainer.appendChild(button);
                });

            // After the search, highlight the selected item again if it was selected
            if (type === "packages" && this.selectedPackageId) {
                this.highlightSelectedItem("packages-list", this.selectedPackageId);
            }
            if (type === "affiliates" && this.selectedAffiliateId) {
                this.highlightSelectedItem("affiliates-list", this.selectedAffiliateId);
            }
        });
    }

    highlightSelectedItem(listContainerId, selectedId) {
        const listContainer = document.getElementById(listContainerId);
        const buttons = listContainer.querySelectorAll(".list-group-item");

        buttons.forEach((button) => {
            if (button.getAttribute("data-id") == selectedId) {
                button.classList.add("selected-item");
            }
        });
    }

    handlePackageSelection(pkg, button) {
        // Reset the selected affiliate when the package is changed or deselected
        if (this.selectedPackageId !== pkg.id) {
            this.selectedAffiliateId = null; // Deselect affiliate when package is changed
            this.clearRightSection('package'); // Clear the right section when package changes
        }

        // Toggle package selection
        if (this.selectedPackageId === pkg.id) {
            this.selectedPackageId = null; // Deselect if already selected
            button.classList.remove("selected-item");
            this.clearRightSection('package');
        } else {
            this.selectedPackageId = pkg.id;
            this.updateSelectedItem(button, "packages-list");
            this.updateRightSection(pkg, 'package');

            // Enable Affiliates section and populate it
            this.populateAffiliates();
        }
    }

    handleAffiliateSelection(affiliate, button) {
        if (!this.selectedPackageId) {
            swal('Please select a package first!', '', 'error');
            return;
        }

        // Toggle affiliate selection
        if (this.selectedAffiliateId === affiliate.id) {
            this.selectedAffiliateId = null; // Deselect if already selected
            button.classList.remove("selected-item");
            this.clearRightSection('affiliate');
        } else {
            this.selectedAffiliateId = affiliate.id;
            this.updateSelectedItem(button, "affiliates-list");
            this.updateRightSection(affiliate, 'affiliate');
        }
    }

    updateSelectedItem(button, listType) {
        const listContainer = document.getElementById(listType);
        const items = listContainer.querySelectorAll(".list-group-item");

        // Remove the selected class from all items in the list
        items.forEach((item) => item.classList.remove("selected-item"));

        // Add the selected class to the clicked item
        button.classList.add("selected-item");
    }

    updateRightSection(selectedItem, type) {
        const tbody = document.getElementById("tbody-pckg-rate-grps");
        const rateGroupSection = document.getElementById("rate-group-section");
        rateGroupSection.style.display = "block";
        tbody.innerHTML = ""; // Clear previous details

        // Update selected item display
        this.updateSelectedDisplay(selectedItem.name, type);
        this.initPackageRateGroup();
    }

    clearRightSection(type) {
        // Remove affiliate selection if clearing a package
        if (type === "package") {
            this.deselectButton(".affiliates-list .selected-item");
            this.selectedAffiliateId = null;
        }

        // Clear display and reset IDs based on type
        this.updateSelectedDisplay("None", type);

        // Hide section if nothing is selected
        const rateGroupSection = document.getElementById("rate-group-section");
        const tbody = document.getElementById("tbody-pckg-rate-grps");
        tbody.innerHTML = "";
        const isActive = this.selectedPackageId || this.selectedAffiliateId;

        rateGroupSection.style.display = isActive ? "block" : "none";
        if (!isActive) {
            tbody.innerHTML = ""; // Clear details if no active selection
        }

        if (type === "affiliate") {
            this.initPackageRateGroup();
        }
    }

    updateSelectedDisplay(itemName, type) {
        const packageDisplay = document.getElementById("selected-package-name");
        const affiliateDisplay = document.getElementById("selected-affiliate-name");

        if (type === "package") {
            packageDisplay.textContent = itemName;
            if (itemName === "None") {
                affiliateDisplay.textContent = "None"; // Clear affiliate if no package
            }
        }

        if (type === "affiliate") {
            affiliateDisplay.textContent = itemName;
        }
    }

    deselectButton(selector) {
        const button = document.querySelector(selector);
        if (button) {
            button.classList.remove("selected-item");
        }
    }

    initPackageRateGroup() {
        // Add rate group info
        const tbody = document.getElementById("tbody-pckg-rate-grps");
        tbody.innerHTML = "";
        this.payload = {};

        if (this.selectedPackageId && this.selectedAffiliateId) {
            this.payload = {
                scheduling_year: this.scheduling_year,
                tour_package_id: this.selectedPackageId,
                affiliate_id: this.selectedAffiliateId
            };
        } else if (this.selectedPackageId) {
            this.payload = {
                scheduling_year: this.scheduling_year,
                tour_package_id: this.selectedPackageId,
                affiliate_id: 0
            };
        }

        if (Object.keys(this.payload).length) {
            showLoading();

            // Clear rateGroups to prepare for new data
            let rateGroups = [];

            $.post(this.getPackageRatesUrl, {
                'scheduling_year': this.payload.scheduling_year,
                'tour_package_id': this.payload.tour_package_id,
                'affiliate_id': this.payload.affiliate_id,
                'global_rate': 1
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
                });

                this.showScheduledEvents();
            }).fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            });

            // Template function to generate HTML for each rate group
            const packageRateGroupTemplate = (data) => `
                <tr class="pckg-rate-grp">
                    <td class="px-2 py-1.5 border border-[#E5E7EB] !shadow-none font-medium text-black text-sm">
                        <form method="post" class="form-pckg-rate-grp" action="#">
                            <input type="hidden" data-name="id" class="form-control hid-rate-id" value="${data.id}">
                            <input type="hidden" data-name="scheduling_year" class="form-control schedulingYear" value="${data.scheduling_year}">
                            <input type="hidden" data-name="tour_package_id" class="form-control packageId" value="${data.tour_package_id}">
                            <input type="hidden" data-name="affiliate_id" class="form-control affiliateId" value="${data.affiliate_id || 0}">
                            <table width="100%" class="mb-2">
                                <tr>
                                    <th class="th-custom-css min-w-56">Rate For <req>*</req></th>
                                    <th class="th-custom-css min-w-28">Age From</th>
                                    <th class="th-custom-css min-w-32">Age To</th>
                                    <th class="th-custom-css min-w-32">Rate <req>*</req></th>
                                    <th class="th-custom-css min-w-32">Permit Fee</th>
                                    ${this.isPartnerPackage ? `
                                        <th class="th-custom-css min-w-36">Partner Fee Type</th>
                                        <th class="th-custom-css min-w-32">Partner Fee</th>
                                    ` : ''}
                                    <th class="th-custom-css min-w-28">Tax</th>
                                    <th colspan="2" class="th-custom-css min-w-24">Actions</th>
                                </tr>
                                <tr>
                                    <td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                    <td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                    <td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                            <input data-name="rate" data-original-value="${data.primary_rate}" class="form-control form-custom-input !rounded-l-none txt-rate txt-required" type="number" value="${data.primary_rate}" min="0" step="0.01" placeholder="Rate">
                                        </div>
                                    </td>
                                    <td class="td-custom-css" style="width: 126px">
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
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">$</span>
                                            <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                        </div>
                                    </td>
                                    ` : ''}
                                    <td class="td-custom-css" style="width: 126px">
                                        <div class="input-group flex items-center">
                                            <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                            <input data-name="additional_charge" data-original-value="${data.primary_additional_charge}" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.primary_additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                        </div>
                                    </td>
                                    <td colspan="2" class="td-custom-css text-center align-middle whitespace-nowrap">
                                        <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button>
                                    </td>
                                </tr>
                                <tr>
                                    <th colspan="2" class="th-custom-css descth">Description</th>
                                    <th class="th-custom-css">Min Pax Allowed</th>
                                    <th class="th-custom-css">Max Pax Allowed</th>
                                    <th class="th-custom-css">Order <req>*</req></th>
                                    <th class="th-custom-css">Mapping Key <req>*</req></th>
                                    <th class="th-custom-css">Infant Rate</th>
                                    <th class="th-custom-css">Status</th>
                                    ${this.isGroupRateEnabled ? `
                                        <th class="th-custom-css">Group Rate Min Pax</th>
                                        <th class="th-custom-css">Group Rate Max Pax</th>
                                    ` : ''}
                                </tr>
                                <tr>
                                    <td colspan="2" class="td-custom-css descth">
                                        <textarea data-name="description" class="form-control form-custom-input txt-desc" placeholder="Description">${data.description}</textarea>
                                    </td>
                                    <td class="td-custom-css">
                                        <div class="input-group">
                                            <input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Min Pax" required>
                                        </div>
                                    </td>
                                    <td class="td-custom-css">
                                        <div class="input-group">
                                            <input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Max Pax">
                                        </div>
                                    </td>
                                    <td class="td-custom-css">
                                        <div class="input-group">
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
                                        <select data-name="status" class="form-control form-custom-input sel-status">
                                            <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                            <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                        </select>
                                    </td>
                                    ${this.isGroupRateEnabled ? `
                                        <td class="td-custom-css">
                                            <div class="input-group">
                                                <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                            </div>
                                        </td>
                                        <td class="td-custom-css">
                                            <div class="input-group">
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
                            ${this.payload.affiliate_id != 'all' ? `
                            <div class="rate-group-total flex justify-between rounded-md text-base text-white bg-[#264F73] px-4 py-2 whitespace-nowrap">
                                <span class="created-by mr-2">Author: <span>${data.created_by_with_name}</span></span>
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
                            </div>` : ''}
                        </form>
                    </td>
                </tr>
            `;
        }
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
            method: 'POST',  // Laravel does not accept DELETE body reliably
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
     * Add new rate group to the package
     */
    addNewGeneralRateGroup(evt) {
        const newGeneralRateGroup = {
            id: '',
            scheduling_year: this.scheduling_year,
            tour_package_id: this.selectedPackageId,
            affiliate_id: this.selectedAffiliateId || 0,
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
            status: 'Active',
            min_group_size: 0,
            max_group_size: 0
        };

        const packageRateGroupTemplate = (data) => `
            <tr class="pckg-rate-grp">
                <td class="td-custom-css">
                    <form method="post" class="form-pckg-rate-grp" action="#">
                        <input type="hidden" data-name="id" class="form-control hid-rate-id" value="${data.id}">
                        <input type="hidden" data-name="scheduling_year" class="form-control schedulingYear" value="${data.scheduling_year}">
                        <input type="hidden" data-name="tour_package_id" class="form-control packageId" value="${data.tour_package_id}">
                        <input type="hidden" data-name="affiliate_id" class="form-control affiliateId" value="${data.affiliate_id}">
                        <table width="100%" class="mb-2">
                            <tr>
                                <th class="th-custom-css min-w-56">Rate For <req>*</req></th>
                                <th class="th-custom-css min-w-28">Age From</th>
                                <th class="th-custom-css min-w-32">Age To</th>
                                <th class="th-custom-css min-w-32">Rate <req>*</req></th>
                                <th class="th-custom-css min-w-32">Permit Fee</th>
                                ${this.isPartnerPackage ? `
                                    <th class="th-custom-css min-w-36">Partner Fee Type</th>
                                    <th class="th-custom-css min-w-32">Partner Fee</th>
                                ` : ''}
                                <th class="th-custom-css min-w-28">Tax</th>
                                <th class="th-custom-css min-w-36">Min Pax Allowed</th>
                                <th class="th-custom-css min-w-36">Max Pax Allowed</th>
                                <th class="th-custom-css min-w-28">Actions</th>
                            </tr>
                            <tr>
                                <td class="td-custom-css"><input data-name="rate_for" class="form-control form-custom-input txt-rate-for txt-required" list="list-pckg-rate-for" value="${data.rate_for}" placeholder="e.g.: Children"></td>
                                <td class="td-custom-css"><input data-name="age_from" class="form-control form-custom-input txt-age-from txt-required" type="number" value="${data.age_from}" min="0" max="255" placeholder="Age From"></td>
                                <td class="td-custom-css"><input data-name="age_to" class="form-control form-custom-input txt-age-to txt-required" type="number" value="${data.age_to}" min="0" max="255" placeholder="Age To"></td>
                                <td class="td-custom-css" style="width: 126px">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="rate" data-original-value="${data.rate}" class="form-control form-custom-input !rounded-l-none txt-rate txt-required" type="number" value="${data.rate}" min="0" step="0.01" placeholder="Rate">
                                    </div>
                                </td>
                                <td class="td-custom-css" style="width: 126px">
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
                                <td class="td-custom-css" style="width: 126px">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">$</span>
                                        <input data-name="partner_fee_value" class="form-control form-custom-input !rounded-l-none txt-partner-fee-value" type="number" value="${data.partner_fee_type == 'Percentage' ? data.partner_fee_percentage : data.partner_fee_amount}" min="0" step="0.01" placeholder="Partner Fee">
                                    </div>
                                </td>
                                ` : ''}
                                <td class="td-custom-css" style="width: 126px">
                                    <div class="input-group flex items-center">
                                        <span class="input-group-text bg-[#F3F4F6] !w-auto form-custom-input !rounded-r-none whitespace-nowrap">${currencyFormation.symbol}</span>
                                        <input data-name="additional_charge" data-original-value="${data.additional_charge}" class="form-control form-custom-input !rounded-l-none txt-adnl-chrg" type="number" value="${data.additional_charge}" min="0" max="99999.99" step="0.01" placeholder="Additional Charge">
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group">
                                        <input type="number" class="form-control form-custom-input txt-min-pax-allowed" data-name="min_pax_allowed" value="${data.min_pax_allowed}" min="0" max="255" placeholder="Enter minimum people allowed" required>
                                    </div>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group">
                                        <input type="number" class="form-control form-custom-input txt-max-pax-allowed" data-name="max_pax_allowed" value="${data.max_pax_allowed}" min="0" max="255" placeholder="Enter maximum people allowed">
                                    </div>
                                </td>
                                <td rowspan="" class="text-center align-middle">
                                    <button type="button" class="btn btn-danger btn-sm btn-general-rate-group-remove btn-danger-small" title="Remove Package Rate Group">X</button><br>
                                </td>
                            </tr>
                            <tr>
                                <th colspan="2" class="th-custom-css descth">Description</th>
                                <th class="th-custom-css">Order <req>*</req></th>
                                <th class="th-custom-css">Mapping Key <req>*</req></th>
                                <th class="th-custom-css">Infant Rate</th>
                                <th class="th-custom-css">Status</th>
                                ${this.isGroupRateEnabled ? `
                                    <th class="th-custom-css">Group Rate Min Pax</th>
                                    <th class="th-custom-css">Group Rate Max Pax</th>
                                ` : ''}
                            </tr>
                            <tr>
                                <td colspan="2" class="descth">
                                    <textarea data-name="description" class="form-control form-custom-input txt-desc" placeholder="Description">${data.description}</textarea>
                                </td>
                                <td class="td-custom-css">
                                    <div class="input-group">
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
                                    <select data-name="status" class="form-control form-custom-input sel-status">
                                        <option ${data.status === 'Active' ? 'selected' : ''}>Active</option>
                                        <option ${data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                                    </select>
                                </td>
                                ${this.isGroupRateEnabled ? `
                                    <td class="td-custom-css">
                                        <div class="input-group">
                                            <input data-name="min_group_size" class="form-control form-custom-input txt-min-group-size txt-required" type="number" value="${data.min_group_size}" min="0" placeholder="Min Group Size">
                                        </div>
                                    </td>
                                    <td class="td-custom-css">
                                        <div class="input-group">
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
     * Function to validate and gather rate group data
     */
    validateRateGroup(event) {
        this.rateGroups = [];

        const form = event.target.closest('form');  // Get the form of the clicked save button
        const rateGroupInputs = form.querySelectorAll('input, select, textarea');
        let isRatesValid = true;

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

        if (isRatesValid) {
            // Proceed to collect the data if valid
            const rateGroupData = this.getRateGroupData(form);  // Collect data from all rate groups
            this.rateGroups = rateGroupData;  // Dynamic rate groups
            this.saveData();
        } else {
            swal(`There are validation errors`, '', 'error');
        }
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
                    const value = input.value.trim();
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
     * Method to handle saving data when Save button is clicked
     */
    saveData() {
        // Gather data from form fields (you can customize this as needed)
        const globalRateDate = {
            scheduling_year: this.scheduling_year,
            tour_package_id: this.selectedPackageId,
            affiliate_id: this.selectedAffiliateId,
            rate_groups: this.rateGroups
        };

        // Log data to verify
        console.log('Saving data:', globalRateDate);

        // Add actual save logic here, such as making an AJAX request to the server
        $.post(this.saveGlobalRatesUrl, globalRateDate, (response) => {
            if (response == true) {
                console.log('Data saved successfully:', response);
                swal('Data saved successfully', '', 'success');
            } else {
                swal(response.message, '', 'error');
            }
        }).fail((error) => {
            swal('Error saving data', '', 'error');
            console.error('Error saving data:', error);
        });
    }

    /**
     * Function to validate the charge data
     */
    validateCharge(event, button) {
        const form = event.target.closest('form');  // Get the form of the clicked save button
        const rateGroupInputs = form.querySelectorAll('input, select, textarea');
        let isRatesValid = true;

        const chargeAmount = document.getElementById('charge-amount').value;
        if (!chargeAmount) {
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

        if (!isRatesValid) {
            swal(`There are validation errors`, '', 'error');
        } else {
            if (button === 'charge') {
                this.updateRateAndCharge(event);
            } else {
                this.resetRateAndCharge(event);
            }
        }
    }

    /**
     * Updates the rate and additional_charge fields dynamically.
     * @param {Event} evt - The event object.
     */
    updateRateAndCharge(event) {
        const tbody = document.getElementById('tbody-pckg-rate-grps');
        const rows = tbody.querySelectorAll('.pckg-rate-grp');

        // Get input values
        const amount = parseFloat(document.getElementById('charge-amount').value) || 0;
        const chargeType = document.getElementById('charge-type').value; // upcharge/downcharge
        const chargeMode = document.getElementById('charge-mode').value; // % or fixed

        rows.forEach((row) => {
            // Get references to dynamic fields
            const rateInput = row.querySelector('input[data-name="rate"]');
            const additionalChargeInput = row.querySelector('input[data-name="additional_charge"]');

            // Get original values
            const originalRate = parseFloat(rateInput.getAttribute('data-original-value')) || 0;
            const originalAdditionalCharge = parseFloat(additionalChargeInput.getAttribute('data-original-value')) || 0;

            let updatedRate = originalRate;
            let updatedAdditionalCharge = originalAdditionalCharge;

            // Calculate new values based on input
            if (chargeMode === 'percentage') {
                // Percentage based calculation
                const percentageChange = (originalRate * amount) / 100;
                if (chargeType === 'upcharge') {
                    updatedRate += percentageChange;
                } else if (chargeType === 'downcharge') {
                    updatedRate -= percentageChange;
                }
            } else if (chargeMode === 'fixed') {
                // Fixed amount calculation
                if (chargeType === 'upcharge') {
                    updatedRate += amount;
                } else if (chargeType === 'downcharge') {
                    updatedRate -= amount;
                }
            }

            // Update additional charge based on taxPercent
            updatedAdditionalCharge = this.roundout((updatedRate * this.taxPercent) / 100);
            if (this.taxPercentType === 'Fixed Money') {
                updatedAdditionalCharge = this.taxPercent; // Set as fixed amount
            }

            // Update inputs with new values
            rateInput.value = updatedRate.toFixed(2);
            additionalChargeInput.value = updatedAdditionalCharge.toFixed(2);

            this.renderTotalInformation(row, rateInput.value, additionalChargeInput.value);
        });
    }

    /**
     * Resets the rate and additional_charge fields to their original values.
     */
    resetRateAndCharge(event) {
        const tbody = document.getElementById('tbody-pckg-rate-grps');
        const rows = tbody.querySelectorAll('.pckg-rate-grp');

        rows.forEach((row) => {
            // Get references to dynamic fields
            const rateInput = row.querySelector('input[data-name="rate"]');
            const additionalChargeInput = row.querySelector('input[data-name="additional_charge"]');

            // Reset values to data-original-value
            rateInput.value = rateInput.getAttribute('data-original-value');
            additionalChargeInput.value = additionalChargeInput.getAttribute('data-original-value');

            this.renderTotalInformation(row, rateInput.value, additionalChargeInput.value);
        });

        // Clear charge input fields
        document.getElementById('charge-amount').value = 0;
        document.getElementById('charge-type').value = 'upcharge';
        document.getElementById('charge-mode').value = 'percentage';
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
        let total = subtotal + this.roundout(subtotal * this.natFeesPercentage / 100);
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
     * Open the schedule popup
     */
    openSchedulePopup() {
        // Initialize Flatpickr
        flatpickr('#scheduling-date-time', {
            enableTime: true,      // Enable time selection
            dateFormat: 'm/d/Y H:i', // Set date and time format
            minDate: 'today',      // Prevent selection of past dates
            time_24hr: true,        // Use 24-hour time format
        });

        const selectedPackage = this.packages.find(pkg => pkg.id === this.selectedPackageId);
        $('.md-package').text(selectedPackage.name);

        if (this.selectedAffiliateId == 'all') {
            $('.md-affiliate').text('All');
        } else {
            const selectedAffiliate = this.affiliates.find(aff => aff.id === this.selectedAffiliateId);
            $('.md-affiliate').text(selectedAffiliate?.name || 'None');
        }

        $('.md-number').text($('#charge-amount').val());
        $('.md-type').text($('#charge-type').val());
        $('.md-method').text($('#charge-mode').val());

        $('#scheduleModal').modal('show');
    }

    /**
     * Save the schedule event
     * @param {string} scheduleDateTime - The selected date and time in ISO 8601 format.
     */
    saveScheduleEvent(scheduleDateTime) {
        // Gather data from form fields (you can customize this as needed)
        const schedulingEventParams = {
            scheduling_year: this.scheduling_year,
            tour_package_id: this.selectedPackageId,
            affiliate_id: this.selectedAffiliateId || 0,
            charge_amount: $('#charge-amount').val(),
            charge_type: $('#charge-type').val(),
            charge_mode: $('#charge-mode').val(),
            date_time: scheduleDateTime
        };

        // Log data to verify
        console.log('Saving event:', schedulingEventParams);

        // Add actual save logic here, such as making an AJAX request to the server
        $.post(this.saveScheduleEventUrl, schedulingEventParams, (response) => {
            if (response == true) {
                console.log('Event saved successfully:', response);
                swal('Event saved successfully', '', 'success');
                $('#scheduleModal').modal('hide');
                this.showScheduledEvents();
            } else {
                swal(response.message, '', 'error');
            }
        }).fail((error) => {
            swal('Error saving event', '', 'error');
            console.error('Error saving event:', error);
        });
    }

    /**
     * Fetch scheduled events for the selected year, package, and affiliate,
     * and render them dynamically in a table.
     */
    showScheduledEvents() {
        // Gather data from form fields
        const schedulingEventParams = {
            scheduling_year: this.scheduling_year,
            tour_package_id: this.selectedPackageId,
            affiliate_id: this.selectedAffiliateId || null
        };

        // Log data to verify
        console.log('Fetching event:', schedulingEventParams);

        // Show a loading spinner while fetching data
        showLoading();

        // Get the table container
        const tableContainer = document.getElementById('schedule-event-table');
        tableContainer.innerHTML = ""; // Clear previous table data

        // Fetch schedule events
        $.post(this.fetchScheduleEventUrl, schedulingEventParams, (response) => {
            hideLoading();

            // Check if pending events are present
            if (response.present_events.length > 0) {
                console.log('Events fetched successfully:', response);

                // Create dynamic title
                const title = document.createElement('div');
                title.className = 'table-title'; // Add a class for styling
                title.innerHTML = `<h4 class="my-3">
                    <i class="fas fa-layer-group sub-nav-icon"></i> <span>Scheduled Events</span>
                </h4>`;

                // Append the title to the container
                tableContainer.appendChild(title);

                // Create table structure dynamically
                const table = document.createElement('table');
                table.className = 'table table-bordered table-striped table-hover w-full border-collapse';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th class="th-custom-css">Event ID</th>
                            <th class="th-custom-css">Scheduling Year</th>
                            <th class="th-custom-css">Package</th>
                            <th class="th-custom-css">Affiliate</th>
                            <th class="th-custom-css">Date</th>
                            <th class="th-custom-css">Number</th>
                            <th class="th-custom-css">Type</th>
                            <th class="th-custom-css">Mode</th>
                            <th class="th-custom-css">Author</th>
                            <th class="th-custom-css">Status</th>
                            <th class="th-custom-css">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                `;

                // Populate table rows dynamically
                const tbody = table.querySelector('tbody');
                response.present_events.forEach((event) => {
                    // Determine badge class based on status
                    let statusBadgeClass = '';
                    if (event.status === 'Active') {
                        statusBadgeClass = 'badge-active';
                    } else if (event.status === 'Inactive') {
                        statusBadgeClass = 'badge-inactive';
                    } else if (event.status === 'Executed') {
                        statusBadgeClass = 'badge-executed';
                    }

                    // Create table row with badges
                    // <td class="td-custom-css">${moment(event.date_time).format('dddd, MMMM D Y, h:mm A')}</td>
                    // <button type="button" class="btn btn-sm btn-primary btn-edit-event" data-id="${event.id}">Edit</button>
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="td-custom-css">${event.id}</td>
                        <td class="td-custom-css">${event.scheduling_year}</td>
                        <td class="td-custom-css">${event.tour_package?.name}</td>
                        <td class="td-custom-css">${event.affiliate_id == 0 ? 'All' : (event.affiliate?.name || '-')}</td>
                        <td class="td-custom-css whitespace-nowrap">${moment(event.date_time).format('MMMM D Y, h:mm A')}</td>
                        <td class="td-custom-css">${event.charge_amount}</td>
                        <td class="td-custom-css">${event.charge_type}</td>
                        <td class="td-custom-css">${event.charge_mode}</td>
                        <td class="td-custom-css">${event.created_by_with_name}</td>
                        <td class="td-custom-css"><span class="${statusBadgeClass}">${event.status}</span></td>
                        <td class="td-custom-css table-actions">
                            <button type="button" class="btn btn-sm btn-danger btn-delete-event btn-danger-small" data-id="${event.id}">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Append the table to the container
                tableContainer.appendChild(table);

                // Attach event listeners to Edit and Delete buttons
                // document.querySelectorAll('.btn-edit-event').forEach((button) => {
                //     button.addEventListener('click', (event) => {
                //         const eventId = event.target.getAttribute('data-id');
                //         this.editScheduledEvent(eventId); // Call edit function
                //     });
                // });

                document.querySelectorAll('.btn-delete-event').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        const eventId = event.target.getAttribute('data-id');
                        this.deleteScheduledEvent(eventId); // Call delete function
                    });
                });
            } else {
                // swal('No events found', '', 'info');
            }

            // Check if past events are present
            if (response.past_events.length > 0) {
                console.log('Events fetched successfully:', response);

                // Create dynamic title
                const title = document.createElement('div');
                title.className = 'table-title'; // Add a class for styling
                title.innerHTML = `<h4 class="mb-2">
                    <i class="fas fa-clock sub-nav-icon"></i> <span>Past Events</span>
                </h4>`;

                // Append the title to the container
                tableContainer.appendChild(title);

                // Create table structure dynamically
                const table = document.createElement('table');
                table.className = 'table table-bordered table-striped table-hover';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th class="th-custom-css">Event ID</th>
                            <th class="th-custom-css">Scheduling Year</th>
                            <th class="th-custom-css">Package</th>
                            <th class="th-custom-css">Affiliate</th>
                            <th class="th-custom-css">Date</th>
                            <th class="th-custom-css">Number</th>
                            <th class="th-custom-css">Type</th>
                            <th class="th-custom-css">Mode</th>
                            <th class="th-custom-css">Author</th>
                            <th class="th-custom-css">Status</th>
                            <th class="th-custom-css">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                `;

                // Populate table rows dynamically
                const tbody = table.querySelector('tbody');
                response.past_events.forEach((event) => {
                    // Determine badge class based on status
                    let statusBadgeClass = '';
                    if (event.status === 'Active') {
                        statusBadgeClass = 'badge-active';
                    } else if (event.status === 'Inactive') {
                        statusBadgeClass = 'badge-inactive';
                    } else if (event.status === 'Executed') {
                        statusBadgeClass = 'badge-executed';
                    }

                    // Create table row with badges
                    // <td class="td-custom-css">${moment(event.date_time).format('dddd, MMMM D Y, h:mm A')}</td>
                    // <button type="button" class="btn btn-sm btn-primary btn-edit-event" data-id="${event.id}">Edit</button>
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td class="td-custom-css">${event.id}</td>
                        <td class="td-custom-css">${event.scheduling_year}</td>
                        <td class="td-custom-css">${event.tour_package?.name}</td>
                        <td class="td-custom-css">${event.affiliate_id == 0 ? 'All' : (event.affiliate?.name || '-')}</td>
                        <td class="td-custom-css whitespace-nowrap">${moment(event.date_time).format('MMMM D Y, h:mm A')}</td>
                        <td class="td-custom-css">${event.charge_amount}</td>
                        <td class="td-custom-css">${event.charge_type}</td>
                        <td class="td-custom-css">${event.charge_mode}</td>
                        <td class="td-custom-css">${event.created_by_with_name}</td>
                        <td class="td-custom-css"><span class="${statusBadgeClass}">${event.status}</span></td>
                        <td class="td-custom-css table-actions">
                            <button type="button" class="btn btn-sm btn-danger btn-delete-event btn-danger-small" data-id="${event.id}">Delete</button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });

                // Append the table to the container
                tableContainer.appendChild(table);

                // Attach event listeners to Edit and Delete buttons
                // document.querySelectorAll('.btn-edit-event').forEach((button) => {
                //     button.addEventListener('click', (event) => {
                //         const eventId = event.target.getAttribute('data-id');
                //         this.editScheduledEvent(eventId); // Call edit function
                //     });
                // });

                document.querySelectorAll('.btn-delete-event').forEach((button) => {
                    button.addEventListener('click', (event) => {
                        const eventId = event.target.getAttribute('data-id');
                        this.deleteScheduledEvent(eventId); // Call delete function
                    });
                });
            } else {
                // swal('No events found', '', 'info');
            }
        }).fail((error) => {
            hideLoading();
            swal('Error fetching events', '', 'error');
            console.error('Error fetching event:', error);
        });
    }

    editScheduledEvent(eventId) {
        console.log(`Editing event with ID: ${eventId}`);
        // Logic to fetch and edit event details
        // For example, open a modal to edit event information
        // You can populate the modal with AJAX fetched data
    }

    deleteScheduledEvent(eventId) {
        console.log(`Deleting event with ID: ${eventId}`);
        // Confirmation dialog before deleting
        swal({
            title: 'Are you sure?',
            text: 'This will permanently delete the event.',
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                // Make an AJAX request to delete the event
                $.post(this.deleteScheduleEventUrl, { id: eventId }, (response) => {
                    if (response == true) {
                        swal('Event deleted successfully!', '', 'success');
                        this.showScheduledEvents(); // Refresh the table
                    } else {
                        swal('Failed to delete event', '', 'error');
                    }
                }).fail((error) => {
                    swal('Error deleting event', '', 'error');
                    console.error('Error:', error);
                });
            }
        });
    }
}
