/**
 * Change People V2 - Enhanced Booking Modification
 * Handles the new UI with left/right layout matching new booking flow
 * Works exclusively with price_per_pax addons (pax_fields)
 */

var changePeopleV2 = {
    bookingId: null,
    rateGroups: [],
    paxAddonsState: {},
    currencySymbol: "$",
    addonFormUrl: "",
    addonFormData: null,
    pendingPaxAddition: null,
    originalTourists: {},
    originalPaxData: {},
    totalSeatsForBooking: 0,
    packageId: null,
    addonsLoaded: false,
    isGroupRateBooking: false,

    init: function () {
        if ($("#change-people-rate-groups-pills").length === 0) {
            return;
        }

        var self = this;
        self.currencySymbol =
            typeof currencyFormation !== "undefined"
                ? currencyFormation.symbol
                : "$";

        self.bindEvents();
    },

    bindEvents: function () {
        var self = this;

        $(document)
            .off("click.changePeopleV2Inc")
            .on(
                "click.changePeopleV2Inc",
                "#change-people-rate-groups-pills .cp-inc-btn",
                function () {
                    self.changePaxCount($(this).closest(".cp-pax-row"), 1);
                },
            );

        $(document)
            .off("click.changePeopleV2Dec")
            .on(
                "click.changePeopleV2Dec",
                "#change-people-rate-groups-pills .cp-dec-btn",
                function () {
                    self.changePaxCount($(this).closest(".cp-pax-row"), -1);
                },
            );

        // 1. The .cp-remove-pax Button (The minus icon)
        /*$(document)
            .off("click.changePeopleV2Remove")
            .on(
                "click.changePeopleV2Remove",
                "#change-people-pax-details .cp-remove-pax",
                function () {
                    var $detail = $(this).closest(".cp-pax-detail");
                    var isOriginal = $detail.data("is-original") == "1" || $detail.data("is-original") == 1;

                    if (isOriginal) {
                        // Toggle the checkbox - this will trigger the 'change' event below
                        var $cb = $detail.find(".cp-remove-checkbox");
                        $cb.prop("checked", !$cb.is(":checked")).trigger("change");
                    } else {
                        // New guests are just deleted
                        self.removePaxDetail($detail);
                    }
                    // self.removePaxDetail($(this).closest(".cp-pax-detail"));
                },
            );*/

        // 2. Individual Checkbox (.cp-remove-checkbox)
        // This is the "Master" logic that calls your existing methods
        $(document)
            .off("change.changePeopleV2Checkbox")
            .on("change.changePeopleV2Checkbox", ".cp-remove-checkbox", function () {
                var $detail = $(this).closest(".cp-pax-detail");
                var isChecked = $(this).is(":checked");
                var uid = $detail.data("uid");
                var rateId = $detail.data("rate-id");

                // Sync state so it survives re-renders when adding new pax
                if (self.paxAddonsState.fields[rateId]) {
                    var paxEntry = self.paxAddonsState.fields[rateId].addons.find(function(a) { 
                        return a.uid === uid; 
                    });
                    if (paxEntry) {
                        paxEntry.markedForRemoval = isChecked;
                    }
                }

                if (isChecked) {
                    // Calls your existing method as-is
                    self.markExistingPaxAsRemoved($detail);
                } else {
                    // Calls your existing undo method as-is
                    self.undoRemovePax($detail);
                }
                
                // Ensure the Select All checkbox and Bulk button update their state
                self.updateSelectAllState();
                self.toggleBulkRemoveButton();
            });

        // 3. Select All Logic
        $(document)
            .off("change.changePeopleV2SelectAll")
            .on("change.changePeopleV2SelectAll", "#cp-select-all", function () {
                var isAllChecked = $(this).is(":checked");
                
                $("#change-people-pax-details .cp-remove-checkbox").each(function () {
                    // Set the property and manually trigger 'change' to fire the logic above
                    $(this).prop("checked", isAllChecked).trigger("change");
                });
            });

        $(document)
            .off("input.changePeopleV2 change.changePeopleV2")
            .on(
                "input.changePeopleV2 change.changePeopleV2",
                '#change-people-pax-details [data-fee], select, input[type="number"], input[type="checkbox"], input[type="radio"], textarea',
                function () {
                    var $detail = $(this).closest(".cp-pax-detail");
                    if ($detail.length) {
                        self.updatePaxDetailPrice($detail);
                    }
                    self.calculateTotal();
                },
            );

        $(document)
            .off("click.changePeopleV2AddonRemove")
            .on(
                "click.changePeopleV2AddonRemove",
                "#change-people-pax-details .cp-addon-remove",
                function (e) {
                    e.preventDefault();
                    self.removeAddon($(this).closest(".addon-card"));
                },
            );

        $(document)
            .off("click.changePeopleV2Undo")
            .on(
                "click.changePeopleV2Undo",
                "#change-people-pax-details .cp-undo-remove",
                function (e) {
                    e.preventDefault();
                    self.undoRemovePax($(this).closest(".cp-pax-detail"));
                },
            );

        /*$(document)
            .off("change.changePeopleV2CheckboxRemove")
            .on(
                "change.changePeopleV2CheckboxRemove",
                "#change-people-pax-details .cp-remove-checkbox",
                function () {
                    var $checkbox = $(this); // The specific one clicked
                    var isChecked = $checkbox.is(":checked");
                    
                    var $detail = $checkbox.closest(".cp-pax-detail");
                    var uid = $detail.data("uid");
                    var rateId = $detail.data("rate-id");

                    // 1. Update the state for THIS specific passenger
                    if (self.paxAddonsState.fields[rateId]) {
                        var paxEntry = self.paxAddonsState.fields[rateId].addons.find(a => a.uid === uid);
                        if (paxEntry) paxEntry.markedForRemoval = isChecked;
                    }

                    // 2. UI visual update for THIS specific row
                    if (isChecked) {
                        self.markExistingPaxAsRemoved($detail);
                    } else {
                        self.undoRemovePax($detail);
                    }

                    // 3. Sync the "Select All" master checkbox (checked if all items are checked)
                    self.updateSelectAllState();

                    // 4. Re-evaluate the button text (Update vs Remove)
                    self.toggleBulkRemoveButton();
                },
            );

        $(document)
            .off("change.changePeopleV2SelectAll")
            .on(
                "change.changePeopleV2SelectAll",
                "#cp-select-all",
                function () {
                    var isChecked = $(this).is(":checked");
                    var $checkboxes = $("#change-people-pax-details .cp-remove-checkbox");

                    $checkboxes.each(function () {
                        var $checkbox = $(this);
                        if ($checkbox.prop("checked") !== isChecked) {
                            $checkbox.prop("checked", isChecked);
                            
                            var $detail = $checkbox.closest(".cp-pax-detail");
                            var uid = $detail.data("uid");
                            var rateId = $detail.data("rate-id");

                            // Update internal state
                            if (self.paxAddonsState.fields[rateId]) {
                                var paxEntry = self.paxAddonsState.fields[rateId].addons.find(function(p) { return p.uid === uid; });
                                if (paxEntry) paxEntry.markedForRemoval = isChecked;
                            }

                            if (isChecked) {
                                self.markExistingPaxAsRemoved($detail);
                            } else {
                                self.undoRemovePax($detail);
                            }
                        }
                    });
                    // Update the button state once after all changes
                    self.toggleBulkRemoveButton();
                }
            );*/

        $(document)
            .off("click.changePeopleV2Submit")
            .on(
                "click.changePeopleV2Submit",
                "#cp-bulk-update-and-remove-btn",
                function (e) {
                    e.preventDefault();
                    // Trigger the original submit button's click event
                    $("#update_booking_change_people_submit").trigger("click");
                }
            );
    },

    removeAddon: function ($addonCard) {
        var self = this;
        var $detail = $addonCard.closest(".cp-pax-detail");

        $addonCard.find("input, select, textarea").each(function () {
            var $input = $(this);
            if ($input.is(":checkbox") || $input.is(":radio")) {
                $input.prop("checked", false);
            } else if ($input.is("select")) {
                $input.val("");
            } else {
                $input.val("");
            }
            $input.trigger("change");
        });

        $addonCard.css({ opacity: "0.5", background: "#FEE2E2" });
        $addonCard.find(".cp-addon-remove").hide();
        $addonCard.find(".cp-addon-undo").show();

        if ($detail.length) {
            self.updatePaxDetailPrice($detail);
        }
        self.calculateTotal();
    },

    open: function (
        bookingId,
        packageId,
        rateGroups,
        totalSeats,
        getAddonFormUrl,
    ) {
        var self = this;
        $(".pax-addons-screen").hide();

        self.bookingId = bookingId;
        self.packageId = packageId;
        self.totalSeatsForBooking = totalSeats || 100;
        self.addonFormUrl = getAddonFormUrl || "";
        self.addonsLoaded = false;
        self.addonFormData = null;

        // Group-rate packages price the WHOLE group as one tiered total looked up by headcount
        // (paxWiseGroupRates[headcount]) rather than a flat per-person rate. Each entry in the
        // incoming `rateGroups` is really just an alternative price BRACKET for that one combined
        // headcount (e.g. "1-3 Person Group" / "4-6 Person Group" / "7-8 Person Group"), not an
        // independently-fillable rate group like a genuine Adult/Child row — rendering/summing one
        // per bracket produced 3 separately-incrementable rows and a "Total Payable" that never
        // reflected the real tiered total. Collapse them into ONE synthetic row up front instead:
        // everything downstream (renderRateGroupRows, changePaxCount, calculateTotal,
        // renderPaxDetails, getAddonsPayload) already treats self.rateGroups generically as "one
        // row per group", so a correct single entry fixes all of them without per-function
        // group-rate branches — mirroring the already-working group-rate pattern in
        // tour-booking-change-group-rate.js and AddonFormReader.initAddonForm().
        self.isGroupRateBooking = typeof isGroupBooking !== 'undefined' && String(isGroupBooking) === '1';

        if (self.isGroupRateBooking && Array.isArray(rateGroups) && rateGroups.length > 0) {
            var headcount = rateGroups.reduce(function (sum, g) {
                return sum + (parseInt(g.tourists) || 0);
            }, 0);
            var pricing = self.resolveGroupRatePricing(headcount);
            var activeGroup = rateGroups.find(function (g) {
                return (parseInt(g.tourists) || 0) > 0;
            }) || rateGroups[0];

            self.rateGroups = [{
                id: pricing.rateId || activeGroup.id,
                rate_for: "Group",
                tourists: headcount,
                rate: pricing.rate,
                additional_charge: pricing.additionalCharge,
                permit_fee: 0,
            }];
        } else {
            self.rateGroups = rateGroups || [];
        }

        // showMakePaymentForEditGroupBookingChangePeople() (the shared group-rate submit
        // handler in tour-booking-change-group-rate.js) unconditionally reads
        // touristsGroups[0].tourists regardless of what the admin actually changed. That global
        // is normally seeded by updateGroupRatesTableChangePeople() before this modal opens, but
        // that function operates on #table-rate-groups-change-people — which doesn't exist in
        // this pax-addons-aware modal — so it silently never runs, leaving touristsGroups empty.
        // An edit that ONLY touches add-ons (the stepper never clicked, so
        // changePeopleV2.syncTouristsGroups() never runs either) then hits
        // touristsGroups[0].tourists on an undefined touristsGroups[0], throwing mid-submit and
        // aborting the AJAX handler — which falls back to a raw, unguarded browser form POST the
        // server rejects. Seed it here so it always exists once this modal is open, using the
        // exact same shape (and same "update in place if present" convention)
        // updateGroupRatesTableChangePeople()/syncTouristsGroups() already use.
        if (self.isGroupRateBooking && typeof touristsGroups !== "undefined" && self.rateGroups[0]) {
            var seedGroup = self.rateGroups[0];
            if (touristsGroups[0] !== undefined) {
                touristsGroups[0].tour_package_rate_id = seedGroup.id;
                touristsGroups[0].tourists = seedGroup.tourists;
                touristsGroups[0].selectedtourists = seedGroup.tourists;
                touristsGroups[0].rate_for = seedGroup.rate_for;
            } else {
                touristsGroups.push({
                    rate_for: seedGroup.rate_for,
                    tour_package_rate_id: seedGroup.id,
                    tourists: seedGroup.tourists,
                    selectedtourists: seedGroup.tourists,
                });
            }
        }

        self.originalTourists = {};
        self.originalPaxData = {};
        self.paxAddonsState = { form_id: 0, fields: {} };

        self.rateGroups.forEach(function (group) {
            self.originalTourists[group.id] = parseInt(group.tourists) || 0;
        });

        self.renderModalContent();

        self.fetchAddonsAndExistingData().then(function () {
            self.renderPaxDetails();
            self.calculateTotal();
        });
    },

    // Resolves group-rate pricing for an exact headcount from the same paxWiseGroupRates /
    // paxWiseGroupRateIds / paxWiseGroupAdditionalCharges globals tour-booking-group-rates.js and
    // tour-booking-change-group-rate.js already use — falls back to the highest priced headcount
    // the table defines when asked for one beyond its range, same convention as those files' own
    // "lastelement" fallback.
    resolveGroupRatePricing: function (headcount) {
        var rate = 0, additionalCharge = 0, rateId = null;

        if (typeof paxWiseGroupRates !== "undefined" && typeof paxWiseGroupRateIds !== "undefined"
            && Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0) {
            var keys = Object.keys(paxWiseGroupRates);
            var lastKey = keys[keys.length - 1];
            var chargesAvailable = typeof paxWiseGroupAdditionalCharges !== "undefined";

            if (paxWiseGroupRates[headcount] !== undefined && paxWiseGroupRateIds[headcount] !== undefined) {
                rate = paxWiseGroupRates[headcount];
                rateId = paxWiseGroupRateIds[headcount];
                additionalCharge = (chargesAvailable && paxWiseGroupAdditionalCharges[headcount] !== undefined)
                    ? paxWiseGroupAdditionalCharges[headcount] : 0;
            } else {
                rate = paxWiseGroupRates[lastKey];
                rateId = paxWiseGroupRateIds[lastKey];
                additionalCharge = (chargesAvailable && paxWiseGroupAdditionalCharges[lastKey] !== undefined)
                    ? paxWiseGroupAdditionalCharges[lastKey] : 0;
            }
        }

        return {
            rate: parseFloat(rate) || 0,
            additionalCharge: parseFloat(additionalCharge) || 0,
            rateId: rateId,
        };
    },

    fetchAddonsAndExistingData: async function () {
        var self = this;

        if (!self.addonFormUrl || !self.packageId) {
            self.addonsLoaded = true;
            return;
        }

        try {
            var url = self.addonFormUrl.replace("PACKAGE_ID", self.packageId);
            var response = await $.ajax({
                url: url,
                method: "GET",
                dataType: "json",
                timeout: 15000,
            });

            if (response && response.success === true && response.form) {
                self.addonFormData = response;
                self.paxAddonsState.form_id = parseInt(response.form.id) || 0;
            }
        } catch (err) {
            console.warn("Failed to fetch addon form:", err);
        }

        try {
            var rateGroupIds = self.rateGroups.map(function (g) {
                return g.id;
            });
            var paxAddonsUrl =
                typeof getBookingPaxAddonsUrl !== "undefined"
                    ? getBookingPaxAddonsUrl
                    : self.addonFormUrl.replace(
                        /\/get-addon-form\/PACKAGE_ID.*/,
                        "/get-booking-pax-addons",
                    );

            var existingAddons = await $.ajax({
                url: paxAddonsUrl,
                method: "POST",
                data: {
                    _token: $('meta[name="csrf-token"]').attr("content"),
                    booking_id: self.bookingId,
                    rate_groups: rateGroupIds,
                },
                dataType: "json",
                timeout: 15000,
            });

            if (Array.isArray(existingAddons)) {
                existingAddons.forEach(function (rateGroupData) {
                    var rateGroupId = rateGroupData.rate_group_id;
                    self.originalPaxData[rateGroupId] = rateGroupData;

                    if (!self.paxAddonsState.fields[rateGroupId]) {
                        self.paxAddonsState.fields[rateGroupId] = {
                            addons: [],
                        };
                    }

                    if (rateGroupData.pax && rateGroupData.pax.length > 0) {
                        rateGroupData.pax.forEach(function (pax) {
                            var existingDbIds = {};

                            function collectNodeIds(nodes) {
                                if (!nodes || !Array.isArray(nodes)) return;
                                nodes.forEach(function (node) {
                                    if (node.id && node.field_id) {
                                        // Map field_id to database id for later lookup
                                        existingDbIds[node.field_id] = node.id;
                                    }
                                    if (
                                        node.children &&
                                        Array.isArray(node.children)
                                    ) {
                                        collectNodeIds(node.children);
                                    }
                                });
                            }

                            if (pax.tree && pax.tree.length > 0) {
                                collectNodeIds(pax.tree);
                            }

                            self.paxAddonsState.fields[rateGroupId].addons.push(
                                {
                                    uid:
                                        "existing_" +
                                        pax.pax_id +
                                        "_" +
                                        pax.pax_index,
                                    pax_id: pax.pax_id,
                                    pax_index: pax.pax_index,
                                    person_ordinal: pax.person_ordinal,
                                    isOriginal: true,
                                    tree: pax.tree || [],
                                    existingDbIds: existingDbIds,
                                    deletedFieldIds: [],
                                    markedForRemoval: false,
                                },
                            );
                        });
                    }
                });
            }
        } catch (err) {
            console.warn("Failed to fetch existing pax addons:", err);
        }

        self.addonsLoaded = true;
    },

    renderModalContent: function () {
        var self = this;
        var $container = $(
            "#edit_booking_change_people #change-people-rate-groups-pills",
        );

        if ($container.length === 0) {
            $container = $("#change-people-rate-groups-pills");
        }

        $("#change-people-addons-container").hide();
        $(".pax-addons-screen").hide();

        $("#edit_booking_change_people .tour-detail-process")
            .closest(".tour-detail-process")
            .hide();
        $("#span-seats-info-change-people")
            .closest(".tour-detail-process")
            .hide();

        $container
            .closest(".col-lg-7")
            .removeClass("col-lg-7")
            .addClass("col-lg-12");
        $container
            .closest(".col-12")
            .css("flex", "0 0 100%")
            .css("max-width", "100%");

        $container.empty();

        var totalPax = self.getTotalPaxCount();
        var remaining = Math.max(0, self.totalSeatsForBooking - totalPax);

        var html = "";

        html +=
            '<div class="cp-header-row mb-4" style="display: flex; align-items: center; width: 100%;">';
        html +=
            '<div class="w-full md:w-1/2 px-3">'+
                '<span class="cp-header-label" style="font-semibold text-lg max-[1350px]:text-base text-[#111827] max-[1350px]:mb-3">Please select group of people:</span>'+
            '</div>';
        html += '<div class="w-full md:w-1/2 px-3 flex items-center justify-between">';
        html += '<div class="flex items-center gap-2 check-all-pax-addons" style="display: none;">'+
                '<label class="cp-select-all-wrap cursor-pointer flex items-center justify-center shrink-0" style="width: 24px; height: 24px; margin-left: 14px;">'+
                    '<input type="checkbox" id="cp-select-all" class="w-5 h-5 cursor-pointer" style="width: 20px; height: 20px;">'+
                '</label>'+
                '<span class="text-sm font-semibold text-[#111827]">All</span>'+
            '</div>';
        // html +=
        //     '<label class="cp-select-all-wrap cursor-pointer flex items-center justify-center" style="display: none; width: 24px; height: 24px; flex-shrink: 0;">' +
        //         '<input type="checkbox" id="cp-select-all" class="w-5 h-5 cursor-pointer" style="width: 20px; height: 20px;">' +
        //         // '<span style="font-size:13px; font-weight:500;">Select All</span>' +
        //     '</label>';
        html += 
            '<div class="cp-header-actions flex items-center gap-2" style="display: none;">';
        html += 
            '<div class="cp-seats-badge" style="background: #16A34A; color: #fff; padding: 8px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px;">';
        // html +=
        //     '<div class="cp-seats-badge" style="background: #16A34A; color: #fff; padding: 8px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; font-size: 13px;">';
        html +=
            '<img src="/images/checked-white.png" width="14" alt="checked" style="filter: brightness(0) invert(1);">';
        html +=
            '<span class="cp-seats-text">' +
            remaining +
            " of " +
            self.totalSeatsForBooking +
            " seats left</span>";
        html += "</div>";
        html += '<button type="button" id="cp-bulk-update-and-remove-btn" class="btn-danger-small" style="display:none; font-size: 12px; padding: 8px 14px;">'+
                '<span>Remove & Confirm Booking</span>'+
            '</button>';
        html += "</div>";
        html += "</div>";
        html += "</div>";

        html += '<div class="flex flex-wrap -mx-3">';

        html +=
            '<div class="cp-left-panel w-full md:w-1/2 max-w-full px-3">';
        html +=
            '<div class="cp-rate-groups-section">';
        html += '<div class="cp-pax-list"></div>';
        html += "</div>";
        html += '<div id="cp-left-summary-container" class="mt-4" style="display: none;"></div>';
        html += "</div>";

        html += '<div class="cp-right-panel w-full md:w-1/2 max-w-full px-3">';
        html +=
            '<div id="change-people-pax-details" class="cp-pax-details-container" style="max-height: calc(100vh - 325px);overflow-y: auto;min-height: 185px;">';
        html += '<div class="cp-loader"><div class="spinner"></div><p>Loading guest details...</p></div>';    
        html += '</div>';
        html += "</div>";

        html += "</div>";

        $container.html(html);

        $("#cp-left-summary-container").html(`
            <div class="cp-total-box py-2 px-3 bg-[#F0FDF4] rounded-md border border-[#BBF7D0]">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-semibold text-[#15803D]">
                        Total Payable:
                    </span>
                    <div class="text-right leading-tight">
                        <div class="text-md font-bold total-before-service-commission-and-discount-change-people">
                            ${this.currencySymbol}0.00
                        </div>
                        <div class="text-sm text-[11px] text-[#6B7280]">
                            inclusive all taxes
                        </div>
                    </div>
                </div>
            </div>
        `);

        self.renderRateGroupRows();
    },

    getTotalPaxCount: function () {
        var self = this;
        var total = 0;
        $("#change-people-rate-groups-pills .cp-pax-row").each(function () {
            var countText = $(this).find(".cp-pax-count").text();
            total += parseInt(countText) || 0;
        });
        if (total === 0) {
            self.rateGroups.forEach(function (group) {
                total += parseInt(group.tourists) || 0;
            });
        }
        return total;
    },

    updateSeatsDisplay: function () {
        var self = this;
        var totalPax = self.getTotalPaxCount();
        var remaining = Math.max(0, self.totalSeatsForBooking - totalPax);
        $(".cp-seats-text").text(
            remaining + " of " + self.totalSeatsForBooking + " seats left",
        );
    },

    renderRateGroupRows: function () {
        var self = this;
        var $paxList = $(
            "#edit_booking_change_people #change-people-rate-groups-pills .cp-pax-list",
        );
        if ($paxList.length === 0) {
            $paxList = $("#change-people-rate-groups-pills .cp-pax-list");
        }
        $paxList.empty();

        self.rateGroups.forEach(function (group) {
            var tourists = parseInt(group.tourists) || 0;
            var originalCount = self.originalTourists[group.id] || 0;
            var rate = parseFloat(group.rate) || 0;
            var permitFee = parseFloat(group.permit_fee) || 0;
            var additionalCharge = parseFloat(group.additional_charge) || 0;
            var groupTotal = rate + permitFee + additionalCharge;

            var rowHtml = '<div class="cp-pax-row flex flex-wrap border border-[#F3F4F6] rounded-lg px-3 py-2 items-center mb-2" ';
            rowHtml += 'data-rate-id="' + group.id + '" ';
            rowHtml += 'data-type="' + (group.rate_for || "Guest") + '" ';
            rowHtml += 'data-price="' + groupTotal.toFixed(2) + '" ';
            rowHtml += 'data-min="0" ';
            rowHtml += 'data-max="' + self.totalSeatsForBooking + '" ';
            rowHtml += 'data-original="' + originalCount + '" ';
            rowHtml += '>';

            rowHtml +=
                '<div class="cp-pax-spinner me-2 flex gap-2">';
            rowHtml +=
                '<button type="button" class="cp-dec-btn btn-primary-small">-</button>';
            rowHtml +=
                '<span class="cp-pax-count w-12 !font-bold text-center h-[34px] resize-none rounded-md border border-slate-200 px-3 py-2 text-xs leading-[14px] text-[#020817] shadow-none outline-none focus:outline-2 focus:outline-transparent">' +
                tourists +
                "</span>";
            rowHtml +=
                '<button type="button" class="cp-inc-btn btn-primary-small">+</button>';
            rowHtml += "</div>";

            rowHtml += '<div class="cp-pax-info">';
            rowHtml +=
                '<strong>' +
                (group.rate_for || "Guest") +
                "</strong>";
            rowHtml += "</div>";

            rowHtml +=
                '<div class="cp-pax-price ml-auto">' +
                self.currencySymbol +
                groupTotal.toFixed(2) +
                "</div>";

            rowHtml += '<input type="hidden" class="hid-rate-group-id" name="rate_group[]" value="' + group.id + '">';
            rowHtml += '<input type="hidden" class="hid-tourists-count" name="tourists[]" value="' + tourists + '">';
            rowHtml += '<input type="hidden" class="hid-permit-fee" name="permit_fee[]" value="' + permitFee + '">';

            rowHtml += '</div>';

            $paxList.append(rowHtml);
        });

        self.updateSpinnerStates();
    },

    renderPaxDetails: function () {
        var self = this;
        var $container = $(
            "#edit_booking_change_people #change-people-pax-details",
        );
        if ($container.length === 0) {
            $container = $("#change-people-pax-details");
        }
        $container.empty();

        if (!self.addonsLoaded) {
            $container.html(
                '<div class="text-center text-gray-500 py-4">Loading add-ons...</div>',
            );
            return;
        }

        var paxFields = self.getPaxFields();
        var hasAnyPax = false;

        // Iterate through rate groups to ensure all pax are rendered
        self.rateGroups.forEach(function (rateGroup) {
            var rateGroupId = rateGroup.id;
            var currentTourists = parseInt(rateGroup.tourists) || 0;

            // Ensure the rate group exists in state
            if (!self.paxAddonsState.fields[rateGroupId]) {
                self.paxAddonsState.fields[rateGroupId] = { addons: [] };
            }

            var groupData = self.paxAddonsState.fields[rateGroupId];
            var existingAddonsCount = groupData.addons ? groupData.addons.length : 0;

            // If current tourists > existing addons, create empty addon entries to fill the
            // gap. Each already-loaded pax must keep ITS OWN true person_ordinal slot (0-based,
            // same convention as tour-booking-addons.js's initPaxAddonForm() and the
            // getBookingPaxAddons()/getPaxAddonsIndexes() backend) instead of just being
            // appended first — otherwise a lone add-on that actually belongs to, say, the 2nd
            // of 3 pax renders at the TOP of the list as if it belonged to the 1st (this was
            // the reported bug: remove pax #1's add-on, add pax #2's, and the modal still shows
            // it on the first card). Any ordinal that no longer fits (shouldn't normally happen
            // here, but mirrors the same overflow handling used elsewhere) is compacted into
            // whichever slots are still unclaimed.
            if (currentTourists > existingAddonsCount) {
                var slotAssignment = new Array(currentTourists).fill(null);
                var overflowPax = [];
                groupData.addons
                    .slice()
                    .sort(function (a, b) {
                        return Number(a.person_ordinal) - Number(b.person_ordinal);
                    })
                    .forEach(function (pax) {
                        var ordinal = Number(pax.person_ordinal);
                        if (
                            Number.isInteger(ordinal) &&
                            ordinal >= 0 &&
                            ordinal < currentTourists &&
                            !slotAssignment[ordinal]
                        ) {
                            slotAssignment[ordinal] = pax;
                        } else {
                            overflowPax.push(pax);
                        }
                    });
                for (var s = 0; s < currentTourists && overflowPax.length; s++) {
                    if (!slotAssignment[s]) {
                        slotAssignment[s] = overflowPax.shift();
                    }
                }

                var slotId = $("#slot_time").val() || self.packageId || "";
                for (var i = 0; i < currentTourists; i++) {
                    if (!slotAssignment[i]) {
                        var timestamp = Date.now();
                        var random = Math.random().toString(36).substring(2, 15);
                        var paxIndex = slotId + "-" + timestamp + "-" + random + "-" + i;

                        slotAssignment[i] = {
                            uid: paxIndex,
                            pax_id: null,
                            pax_index: paxIndex,
                            person_ordinal: i,
                            isOriginal: false,
                            tree: [],
                            existingDbIds: {},
                            deletedFieldIds: [],
                            markedForRemoval: false,
                        };
                    }
                }

                groupData.addons = slotAssignment;
            }

            // Now render all pax for this rate group
            if (groupData.addons && groupData.addons.length > 0) {
                hasAnyPax = true;

                groupData.addons.forEach(function (pax, index) {
                    var $detail = self.createPaxDetailCard(
                        {
                            uid: pax.uid,
                            rateId: rateGroupId,
                            type: rateGroup.rate_for || "Guest",
                            label: rateGroup.rate_for || "Guest",
                            price:
                                parseFloat(rateGroup.rate) +
                                parseFloat(rateGroup.permit_fee || 0) +
                                parseFloat(rateGroup.additional_charge || 0),
                            index: index,
                            isOriginal: pax.isOriginal || false,
                            existingTree: pax.tree || [],
                        },
                        paxFields,
                    );

                    $container.append($detail);
                });
            }
        });

        if (!hasAnyPax) {
            $container.html(
                '<div style="text-align: center; padding: 40px 20px; color: #94A3B8;"><i class="fas fa-user-plus" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i><p style="font-size: 15px; margin: 0;">Use the + buttons on the left to add guests</p></div>',
            );

            $(".check-all-pax-addons").hide();
            $("#cp-bulk-update-and-remove-btn").hide();
        } else {
            $(".check-all-pax-addons").show();
            self.toggleBulkRemoveButton();
        }

        self.updateSelectAllState();
        $('.cp-header-actions').fadeIn(); // Use fadeIn() for a smooth transition or .show()
        $("#cp-left-summary-container").show();
        $(".pax-addons-screen").fadeIn();
    },

    createPaxDetailCard: function (options, paxFields) {
        var self = this;
        var uid = options.uid;
        var rateId = options.rateId;
        var label = options.label || "Guest";
        var price = parseFloat(options.price) || 0;
        var isOriginal = options.isOriginal || false;
        var existingTree = options.existingTree || [];

        var $root = $(
            '<div class="cp-pax-detail" style="background: #fff; border: 1px solid #E2E8F0; border-radius: 12px; margin-bottom: 16px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.04);"></div>',
        );
        $root.attr("data-uid", uid);
        $root.attr("data-rate-id", rateId);
        $root.attr("data-is-original", isOriginal ? "1" : "0");

        var headerBg = isOriginal
            ? "background: #DCFCE7; color: #166534;"
            : "background: #FEF2F2; color: #B91C1C";
        var headerHtml =
            '<div class="cp-pax-header !flex items-center justify-between w-full px-4 py-3 !mb-0 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label" style=" ' + headerBg + '">';
        headerHtml +=
            '<div style="display: flex; align-items: center; gap: 10px;">';
        headerHtml +=
            '<label class="cp-remove-checkbox-wrap cursor-pointer flex items-center gap-2 mr-2">' +
                '<input type="checkbox" class="cp-remove-checkbox w-5 h-5 cursor-pointer">' +
            '</label>';
        headerHtml +=
            '<span style="font-weight: 600; font-size: 15px;">' +
            label +
            "</span>";
        if (isOriginal) {
            headerHtml +=
                '<span class="text-xs py-1 px-2 border border-[#E5E7EB] rounded-full overflow-hidden">existing</span>';
        } else {
            headerHtml +=
                '<span class="text-xs py-1 px-2 text-[#264F73] border border-[#E2E8F0] rounded-full bg-white">new</span>';
        }
        headerHtml += '<span style="font-size: 11px; opacity: 0.9;">—</span>';
        headerHtml +=
            '<span class="cp-pax-price" data-base-price="' +
            price.toFixed(2) +
            '" style="font-weight: 700; font-size: 15px;">' +
            self.currencySymbol +
            price.toFixed(2) +
            "</span>";
        headerHtml += "</div>";

        /*if (!isOriginal) {
            headerHtml +=
                '<button type="button" class="cp-remove-pax ml-2 text-red-600 hover:text-red-800 transition" title="Remove this guest">' +
                    '<i class="fas fa-user-minus"></i>' +
                '</button>'
        } else {
            headerHtml +=
                '<button type="button" class="cp-remove-pax ml-2 text-red-600 hover:text-red-800 transition" title="Mark for removal">' +
                    '<i class="fas fa-user-minus"></i>' +
                '</button>'
        }*/
        // if (!isOriginal) {
        //     headerHtml +=
        //         '<button type="button" class="cp-remove-pax text-[#A32624] ml-2 cursor-pointer" title="Remove this guest">&times;</button>';
        // } else {
        //     headerHtml +=
        //         '<button type="button" class="cp-remove-pax text-[#A32624] ml-2 cursor-pointer" title="Mark for removal">&times;</button>';
        // }
        headerHtml += "</div>";

        $root.append(headerHtml);

        var $addonsWrap = $(
            '<div class="cp-addon-fields pt-1 pb-4 px-4"></div>',
        );

        if (paxFields.length > 0) {
            paxFields.forEach(function (field) {
                var fieldEl = self.renderAddonField(field, uid);
                if (fieldEl) {
                    $addonsWrap.append(fieldEl);
                }
            });

            if (
                typeof Components !== "undefined" &&
                Components.attachLogicToContainer
            ) {
                Components.attachLogicToContainer($addonsWrap[0], paxFields);
            }

            setTimeout(function () {
                // 1. Prefill from database (original data)
                if (existingTree.length > 0) {
                    self.prefillExistingAddons($root, existingTree);
                }

                // 2. NEW: Prefill from temporary UI state (unsaved selections)
                // Find the state for this specific UID again to get the uiState
                var currentState = null;
                if (self.paxAddonsState.fields[rateId]) {
                    currentState = self.paxAddonsState.fields[rateId].addons.find(function(p) {
                        return p.uid === uid;
                    });
                }

                if (currentState && currentState.uiState && currentState.uiState.length > 0) {
                    self.prefillExistingAddons($root, currentState.uiState);
                }

                // 3. Trigger logic for nested rules
                if ($addonsWrap[0] && $addonsWrap[0]._logicRunAll) {
                    $addonsWrap[0]._logicRunAll();
                }
            }, 100);
            /*if (existingTree.length > 0) {
                setTimeout(function () {
                    self.prefillExistingAddons($root, existingTree);

                    if ($addonsWrap[0] && $addonsWrap[0]._logicRunAll) {
                        $addonsWrap[0]._logicRunAll();
                    }
                }, 100);
            }*/
        } else {
            $addonsWrap.html(
                '<div class="text-gray-500 text-sm">No add-ons available for this tour</div>',
            );
        }

        $root.append($addonsWrap);

        var paxState = null;

        if (self.paxAddonsState.fields[rateId]) {
            paxState = self.paxAddonsState.fields[rateId].addons.find(
                function (p) {
                    return p.uid === uid;
                }
            );
        }

        if (paxState && paxState.markedForRemoval) {
            $root.addClass("cp-marked-removed");
            $root.find(".cp-remove-checkbox").prop("checked", true);
            $root.css({
                background: "#FEF2F2",
                "border-color": "#FECACA",
            });
            $root.find(".cp-pax-header").css({
                background: "linear-gradient(135deg, #E13737 0%, #EF4444 100%)",
                color: "#721818"
            });
            $root.find(".cp-addon-fields input, .cp-addon-fields select, .cp-addon-fields textarea")
                .prop("disabled", true);
        }

        return $root;
    },

    renderAddonField: function (field, paxUid) {
        if (typeof Components !== "undefined" && Components.renderField) {
            var el = Components.renderField(field, paxUid);
            return el;
        }

        var self = this;
        var fieldId = field.id;
        var uniqueId = paxUid ? fieldId + "_" + paxUid : fieldId;
        var fee = parseFloat(field.price || 0);
        var label = field.label || "";

        var $wrapper = $(
            '<div class="addon-card" style="border: 1px solid #E2E8F0; border-radius: 8px; margin-top: 12px; background: #F8FAFC; padding: 14px; position: relative;"></div>',
        );
        $wrapper.attr("data-node-id", fieldId);
        $wrapper.attr("data-field-id", fieldId);
        $wrapper.attr("data-label", label);
        $wrapper.attr("data-fee", fee);

        var html = "";

        if (field.type === "checkbox") {
            html = '<div style="display: flex; align-items: center;">';
            html +=
                '<input type="checkbox" id="' +
                uniqueId +
                '" data-fee="' +
                fee +
                '" style="width: 18px; height: 18px; margin-right: 10px; accent-color: #3B82F6;">';
            html +=
                '<label for="' +
                uniqueId +
                '" style="font-size: 14px; color: #374151; margin: 0; cursor: pointer;">' +
                label;
            if (field.required)
                html += ' <span style="color: #DC2626;">*</span>';
            html += "</label>";
            html += "</div>";
        } else if (field.type === "radio" && field.options) {
            html =
                '<span style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">' +
                label;
            if (field.required)
                html += ' <span style="color: #DC2626;">*</span>';
            html += "</span>";

            field.options.forEach(function (opt, i) {
                var optId = uniqueId + "_" + i;
                var optFee = parseFloat(opt.price || 0);
                html +=
                    '<div style="display: flex; align-items: center; margin-top: 6px;">';
                html +=
                    '<input type="radio" name="' +
                    uniqueId +
                    '" id="' +
                    optId +
                    '" ';
                html +=
                    'value="' +
                    (opt.value || "") +
                    '" data-option-id="' +
                    (opt.id || "") +
                    '" data-fee="' +
                    optFee +
                    '" style="width: 16px; height: 16px; margin-right: 8px; accent-color: #3B82F6;">';
                html +=
                    '<label for="' +
                    optId +
                    '" style="font-size: 13px; color: #4B5563; margin: 0; cursor: pointer;">' +
                    (opt.label || opt.value);
                if (optFee > 0)
                    html +=
                        ' <span style="color: #059669;">(' +
                        self.currencySymbol +
                        optFee.toFixed(2) +
                        ")</span>";
                html += "</label></div>";
            });
        } else if (field.type === "dropdown" && field.options) {
            html =
                '<span style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">' +
                label;
            if (field.required)
                html += ' <span style="color: #DC2626;">*</span>';
            html += "</span>";
            html +=
                '<select id="' +
                uniqueId +
                '" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px; background: #fff; color: #374151;">';
            html += '<option value="">Select an option</option>';
            field.options.forEach(function (opt) {
                var optFee = parseFloat(opt.price || 0);
                html +=
                    '<option value="' +
                    (opt.value || "") +
                    '" data-option-id="' +
                    (opt.id || "") +
                    '" data-fee="' +
                    optFee +
                    '">';
                html += opt.label || opt.value;
                if (optFee > 0)
                    html +=
                        " (" + self.currencySymbol + optFee.toFixed(2) + ")";
                html += "</option>";
            });
            html += "</select>";
        } else if (field.type === "number") {
            html =
                '<span style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">' +
                label;
            if (field.required)
                html += ' <span style="color: #DC2626;">*</span>';
            html += "</span>";
            html += '<input type="number" id="' + uniqueId + '" ';
            html +=
                'data-fee="' +
                fee +
                '" min="' +
                (field.min || 0) +
                '" max="' +
                (field.max || "") +
                '" step="1" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px;">';
        } else if (field.type === "text" || field.type === "textbox") {
            html =
                '<span style="display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 8px;">' +
                label;
            if (field.required)
                html += ' <span style="color: #DC2626;">*</span>';
            html += "</span>";
            if (field.type === "textbox") {
                html +=
                    '<textarea id="' +
                    uniqueId +
                    '" rows="3" data-fee="' +
                    fee +
                    '" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px; resize: vertical;"></textarea>';
            } else {
                html +=
                    '<input type="text" id="' +
                    uniqueId +
                    '" data-fee="' +
                    fee +
                    '" style="width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 6px; font-size: 14px;">';
            }
        }

        $wrapper.html(html);
        return $wrapper[0];
    },

    prefillExistingAddons: function ($detail, tree) {
        var self = this;

        tree.forEach(function (node) {
            self.fillNodeValue($detail, node);
        });
    },

    fillNodeValue: function ($container, node) {
        var self = this;
        var nodeLabel = node.field_label || "";
        var $card = null;

        if (nodeLabel) {
            $container.find(".addon-card").each(function () {
                var $thisCard = $(this);
                var dataLabel = $thisCard.attr("data-label") || "";
                if (dataLabel && dataLabel === nodeLabel) {
                    $card = $thisCard;
                    return false;
                }
            });
        }

        if (!$card || !$card.length) {
            $container.find(".addon-card").each(function () {
                var $thisCard = $(this);
                var cardLabel = $thisCard
                    .find(".form-label, label")
                    .first()
                    .text()
                    .trim();
                cardLabel = cardLabel
                    .replace(/\s*\*\s*$/, "")
                    .replace(/\s*\([^)]*\)\s*$/, "")
                    .trim();

                if (cardLabel && nodeLabel) {
                    var normalizedNodeLabel = nodeLabel
                        .replace(/\s*\([^)]*\)\s*$/, "")
                        .trim();
                    if (
                        cardLabel === normalizedNodeLabel ||
                        cardLabel.includes(
                            normalizedNodeLabel.substring(0, 25),
                        ) ||
                        normalizedNodeLabel.includes(cardLabel.substring(0, 25))
                    ) {
                        $card = $thisCard;
                        return false;
                    }
                }
            });
        }

        if (!$card || !$card.length) {
            $card = $container
                .find('.addon-card[data-node-id="' + node.id + '"]')
                .first();
        }

        if (!$card || !$card.length) {
            $card = $container
                .find('.addon-card[data-field-id="' + node.id + '"]')
                .first();
        }

        if ($card && $card.length) {
            var $input = $card.find("input, select, textarea").first();

            if ($input.is(":checkbox")) {
                $input.prop(
                    "checked",
                    node.value === "checked" ||
                    node.value === true ||
                    node.value === "1",
                );
            } else if ($input.is(":radio")) {
                // Match by option_id FIRST — it's an exact identifier, unlike the label-substring
                // fallback below, which mismatches whenever one option's label is a substring of
                // another's (e.g. "Large" is a substring of "X-Large": looking for "X-Large" would
                // wrongly check the "Large" radio instead, silently pre-selecting the cheaper
                // option and producing a phantom price delta with zero user interaction).
                var radioMatchedById = false;
                if (node.option_id !== undefined && node.option_id !== null) {
                    var $radioById = $card.find(
                        'input[type="radio"][data-option-id="' + node.option_id + '"]',
                    );
                    if ($radioById.length) {
                        $radioById.prop("checked", true);
                        radioMatchedById = true;
                    }
                }
                if (!radioMatchedById && node.option_label) {
                    $card.find('input[type="radio"]').each(function () {
                        var $radio = $(this);
                        var optLabel = $radio.siblings("label").text().trim();
                        if (
                            optLabel.includes(node.option_label) ||
                            $radio.val() === node.value
                        ) {
                            $radio.prop("checked", true);
                        }
                    });
                }
            } else if ($input.is("select")) {
                // See the radio branch above — match by option_id first to avoid the same
                // label-substring collision (e.g. "X-Large" vs "Large") before falling back to
                // fuzzy label matching for legacy data that lacks an option_id.
                var selectMatchedById = false;
                if (node.option_id !== undefined && node.option_id !== null) {
                    var $optById = $input.find(
                        'option[data-option-id="' + node.option_id + '"]',
                    );
                    if ($optById.length) {
                        $input.val($optById.val());
                        selectMatchedById = true;
                    }
                }
                if (!selectMatchedById && node.option_label) {
                    var optionFound = false;
                    $input.find("option").each(function () {
                        var optText = $(this).text();
                        if (
                            optText.includes(node.option_label) ||
                            node.option_label.includes(optText.split(" (")[0].trim())
                        ) {
                            $input.val($(this).val());
                            optionFound = true;
                            return false;
                        }
                    });
                    if (!optionFound && node.value) {
                        $input.val(node.value);
                    }
                } else if (!selectMatchedById && node.value) {
                    $input.val(node.value);
                }
            } else {
                if (node.value) {
                    $input.val(node.value);
                }
            }

            $input.trigger("change");

            $card.attr("data-db-id", node.id);
            if (node.field_id) {
                $card.attr("data-field-id", node.field_id);
            }
            if (node.pax_id) {
                $card.attr("data-pax-id", node.pax_id);
            }
        }

        if (node.children && node.children.length > 0) {
            node.children.forEach(function (child) {
                self.fillNodeValue($container, child);
            });
        }
    },

    getPaxFields: function () {
        var self = this;

        if (
            self.addonFormData &&
            self.addonFormData.form &&
            self.addonFormData.form.addons
        ) {
            // getPackageAddonForm()'s pax_fields/fields split routes a price_per_pax field
            // flagged display_once into "fields" for NEW-booking checkout rendering only
            // (show once vs once-per-traveler at booking time). Once a booking exists, it's
            // stored exactly like any other price_per_pax field, so Change People needs
            // every price_per_pax field regardless of which bucket it landed in — that field's
            // OWN unit_type is still literally "price_per_pax" wherever it landed (the display_once
            // split only changes which bucket it's grouped into, never the field's unit_type
            // value), so a strict equality check already finds it without any fallback.
            //
            // There must be NO `|| !field.unit_type` fallback here: a genuinely non-priced
            // general field (Meal Preference, Special Requests, Accessibility/Medical Notes,
            // Equipment Rental Needed, etc.) was never priced at all, so it legitimately has
            // unit_type = null on addon_fields (nullable enum, only ever set for priced fields) —
            // a loose falsy check swept those into this "render once per passenger" list right
            // alongside the one display_once edge case it was meant to catch, duplicating every
            // general field inside each pax's own card in the "Change People" modal.
            var paxFields = self.addonFormData.form.addons.pax_fields || [];
            var otherFields = self.addonFormData.form.addons.fields || [];
            var allFields = paxFields.concat(otherFields);

            return allFields.filter(function (field) {
                return field.unit_type === "price_per_pax";
            });
        }

        if (
            self.addonFormData &&
            self.addonFormData.form &&
            self.addonFormData.form.fields
        ) {
            return self.addonFormData.form.fields.filter(function (field) {
                return field.unit_type === "price_per_pax";
            });
        }

        return [];
    },

    changePaxCount: function ($row, delta) {
        var self = this;
        var rateId = $row.data("rate-id");
        var type = $row.data("type");
        var label = $row.find(".cp-pax-info span").first().text() || type;
        var price = parseFloat($row.data("price"));
        var min = parseInt($row.data("min")) || 0;
        var max = parseInt($row.data("max")) || 100;
        var originalCount = parseInt($row.data("original")) || 0;
        var $countSpan = $row.find(".cp-pax-count");

        var currentVal = parseInt($countSpan.text()) || 0;
        var newVal = currentVal + delta;

        var totalPax = self.getTotalPaxCount();
        if (delta > 0 && totalPax >= self.totalSeatsForBooking) {
            swal(
                "Seat Limit Reached",
                "No more seats available for this booking.",
                "warning",
            );
            return;
        }

        if (newVal < min || newVal > max) return;

        if (delta < 0 && newVal < originalCount) {
            swal(
                "Cannot Decrease",
                "You cannot remove existing guests. To remove existing guests, you must first delete their add-ons from the booking.",
                "warning",
            );
            return;
        }

        // Crossing a tier boundary (e.g. 3 -> 4 people moves from the "1-3" bracket into the
        // "4-6" one) means this row's REAL tour_package_rate_id changes. The backend resolves
        // each add-on's owning transact row by matching rate_group_id against
        // tour_bookings_transact's CURRENT tour_package_rate_id (see
        // ChangePeopleAddonService::buildTransactMap()) and updates that column to this same new
        // id before processing add-ons (EditBookingService::updateTblTransactChangeGroupPeople(),
        // called before ChangePeopleAddonService::processAddons() in both add-people branches of
        // V3\TourBookingRepository::editBooking()) — so every add-on payload entry for this group
        // must carry the NEW id from this point on, not the bracket it started the edit on. All
        // state keyed by the old id is migrated to the new one so nothing downstream (rendering,
        // hasChanges(), getAddonsPayload()) silently keeps looking under the stale key.
        if (self.isGroupRateBooking) {
            var pricing = self.resolveGroupRatePricing(newVal);
            var grpRow = self.rateGroups[0];

            if (pricing.rateId && grpRow) {
                if (pricing.rateId != rateId) {
                    if (self.paxAddonsState.fields[rateId]) {
                        self.paxAddonsState.fields[pricing.rateId] = self.paxAddonsState.fields[rateId];
                        delete self.paxAddonsState.fields[rateId];
                    }
                    self.originalTourists[pricing.rateId] = self.originalTourists[rateId];
                    delete self.originalTourists[rateId];

                    if (typeof touristsGroups !== "undefined") {
                        var staleIndex = touristsGroups.findIndex(function (g) {
                            return g.tour_package_rate_id == rateId;
                        });
                        if (staleIndex > -1) touristsGroups.splice(staleIndex, 1);
                    }

                    $row.attr("data-rate-id", pricing.rateId).data("rate-id", pricing.rateId);
                    rateId = pricing.rateId;
                }

                grpRow.id = pricing.rateId;
                grpRow.rate = pricing.rate;
                grpRow.additional_charge = pricing.additionalCharge;

                price = pricing.rate + pricing.additionalCharge;
                $row.attr("data-price", price.toFixed(2)).data("price", price);
                $row.find(".cp-pax-price").text(self.currencySymbol + price.toFixed(2));
                $row.find(".hid-rate-group-id").val(pricing.rateId);
            }
        }

        if (!self.paxAddonsState.fields[rateId]) {
            self.paxAddonsState.fields[rateId] = { addons: [] };
        }

        if (delta > 0) {
            var slotId = $("#slot_time").val() || self.packageId || "";
            var currentAddons = self.paxAddonsState.fields[rateId].addons;
            var originalCount = self.originalTourists[rateId] || 0;
            var newPersonOrdinal = originalCount + (currentAddons.length - originalCount);

            var timestamp = Date.now();
            var random = Math.random().toString(36).substring(2, 15);
            var paxIndex = slotId + "-" + timestamp + "-" + random + "-" + newPersonOrdinal;

            self.paxAddonsState.fields[rateId].addons.push({
                uid: paxIndex,
                isOriginal: false,
                pax_index: paxIndex,
                person_ordinal: newPersonOrdinal,
                tree: []
            });
        }

        if (delta < 0) {
            var addons = self.paxAddonsState.fields[rateId].addons;
            for (var i = addons.length - 1; i >= 0; i--) {
                if (!addons[i].isOriginal) {
                    addons.splice(i, 1);
                    break;
                }
            }
        }

        $countSpan.text(newVal);
        var rateGroup = self.rateGroups.find(function (g) { return g.id == rateId; });
        $row.find(".hid-tourists-count").val(newVal);

        var rateGroup = self.rateGroups.find(function (g) {
            return g.id == rateId;
        });
        if (rateGroup) {
            rateGroup.tourists = newVal;
        }

        self.syncTouristsGroups(rateId, newVal, originalCount);

        self.captureCurrentAddonState();
        self.updateSeatsDisplay();
        self.updateSpinnerStates();
        self.renderPaxDetails();
        self.calculateTotal();
        self.toggleBulkRemoveButton();
    },

    syncTouristsGroups: function (rateId, newCount, originalCount) {
        if (typeof touristsGroups === "undefined") return;

        var found = false;
        for (var i = 0; i < touristsGroups.length; i++) {
            if (touristsGroups[i].tour_package_rate_id == rateId) {
                touristsGroups[i].tourists = newCount;
                touristsGroups[i].selectedtourists = originalCount;
                found = true;
                break;
            }
        }

        if (!found) {
            var rateGroup = this.rateGroups.find(function (g) {
                return g.id == rateId;
            });
            touristsGroups.push({
                rate_for: rateGroup ? rateGroup.rate_for : "",
                tour_package_rate_id: rateId,
                tourists: newCount,
                selectedtourists: originalCount,
            });
        }
    },

    removePaxDetail: function ($detail) {
        var self = this;
        var uid = $detail.data("uid");
        var rateId = $detail.data("rate-id");
        var isOriginal =
            $detail.data("is-original") === "1" ||
            $detail.data("is-original") === 1;

        if (isOriginal) {
            self.markExistingPaxAsRemoved($detail);
            return;
        }

        self.markExistingPaxAsRemoved($detail);

        // 3. Update the state: Mark for removal instead of splicing (deleting)
        if (self.paxAddonsState.fields[rateId]) {
            var paxEntry = self.paxAddonsState.fields[rateId].addons.find(function (p) {
                return p.uid === uid;
            });
            if (paxEntry) {
                paxEntry.markedForRemoval = true;
            }
        }

        var $row = $('#change-people-rate-groups-pills .cp-pax-row[data-rate-id="' + rateId + '"]');
        var remaining = $('#change-people-pax-details .cp-pax-detail[data-rate-id="' + rateId + '"]:not(.cp-marked-removed)').length;
        var originalCount = parseInt($row.data('original')) || 0;
        $row.find('.cp-pax-count').text(remaining);
        var rateGroup = self.rateGroups.find(function (g) { return g.id == rateId; });
        var $row = $(
            '#change-people-rate-groups-pills .cp-pax-row[data-rate-id="' +
            rateId +
            '"]',
        );
        var remaining = $(
            '#change-people-pax-details .cp-pax-detail[data-rate-id="' +
            rateId +
            '"]:not(.cp-marked-removed)',
        ).length;
        var originalCount = parseInt($row.data("original")) || 0;
        $row.find(".cp-pax-count").text(remaining);
        $row.find(".hid-tourists-count").val(remaining);

        var rateGroup = self.rateGroups.find(function (g) {
            return g.id == rateId;
        });
        if (rateGroup) {
            rateGroup.tourists = remaining;
        }

        self.syncTouristsGroups(rateId, remaining, originalCount);

        self.updateSeatsDisplay();
        self.updateSpinnerStates();
        self.calculateTotal();
        self.toggleBulkRemoveButton();

        if (
            $(
                "#change-people-pax-details .cp-pax-detail:not(.cp-marked-removed)",
            ).length === 0
        ) {
            $("#change-people-pax-details").html(
                '<div style="text-align: center; padding: 40px 20px; color: #94A3B8;"><i class="fas fa-user-plus" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.5;"></i><p style="font-size: 15px; margin: 0;">Use the + buttons on the left to add guests</p></div>',
            );
        }
    },

    markExistingPaxAsRemoved: function ($detail) {
        var self = this;
        var uid = $detail.data("uid");
        var rateId = $detail.data("rate-id");

        $detail.find(".cp-remove-checkbox").prop("checked", true);
        $detail.addClass("cp-marked-removed");
        $detail.css({
            background: "#FEF2F2",
            "border-color": "#FECACA",
        });

        $detail
            .find(".cp-pax-header")
            .css({
                "background": "linear-gradient(135deg, #E13737 0%, #EF4444 100%)",
                "color": "#721818"
            });

        // $detail.find(".cp-remove-pax").hide();

        // var $undoBtn = $(
        //     '<button type="button" class="cp-undo-remove btn-success-small">Undo</button>',
        // );
        // $detail.find(".cp-pax-header").append($undoBtn);

        // $detail.find("input, select, textarea").prop("disabled", true);
        $detail.find(".cp-addon-fields input, .cp-addon-fields select, .cp-addon-fields textarea").prop("disabled", true);

        if (self.paxAddonsState.fields[rateId]) {
            var paxEntry = self.paxAddonsState.fields[rateId].addons.find(
                function (p) {
                    return p.uid === uid;
                },
            );

            if (paxEntry) {
                paxEntry.markedForRemoval = true;
                if (paxEntry.existingDbIds) {
                    paxEntry.deletedFieldIds = Object.values(paxEntry.existingDbIds);
                }
            }
        }

        var $row = $(
            '#change-people-rate-groups-pills .cp-pax-row[data-rate-id="' +
            rateId +
            '"]',
        );
        var activeCount = $(
            '#change-people-pax-details .cp-pax-detail[data-rate-id="' +
            rateId +
            '"]:not(.cp-marked-removed)',
        ).length;
        var originalCount = parseInt($row.data("original")) || 0;
        $row.find(".cp-pax-count").text(activeCount);
        $row.find(".hid-tourists-count").val(activeCount);

        var rateGroup = self.rateGroups.find(function (g) {
            return g.id == rateId;
        });
        if (rateGroup) {
            rateGroup.tourists = activeCount;
        }

        self.syncTouristsGroups(rateId, activeCount, originalCount);

        self.updateSeatsDisplay();
        self.updateSpinnerStates();
        self.calculateTotal();
    },

    undoRemovePax: function ($detail) {
        var self = this;
        var uid = $detail.data("uid");
        var rateId = $detail.data("rate-id");

        $detail.removeClass("cp-marked-removed");
        $detail.css({
            opacity: "1",
            background: "",
            "border-color": "",
        });

        $detail
            .find(".cp-pax-header")
            .css({
                "background": "#DCFCE7",
                "color": "#166534"
            });

        $detail.find(".cp-undo-remove").remove();
        // $detail.find(".cp-remove-pax").show();

        // $detail.find("input, select, textarea").prop("disabled", false);
        $detail.find(".cp-addon-fields input, .cp-addon-fields select, .cp-addon-fields textarea").prop("disabled", false);
        $detail.find(".cp-remove-checkbox").prop("checked", false);

        if (self.paxAddonsState.fields[rateId]) {
            var paxEntry = self.paxAddonsState.fields[rateId].addons.find(
                function (p) {
                    return p.uid === uid;
                },
            );

            if (paxEntry) {
                paxEntry.markedForRemoval = false;
                paxEntry.deletedFieldIds = [];
            }
        }

        var $row = $(
            '#change-people-rate-groups-pills .cp-pax-row[data-rate-id="' +
            rateId +
            '"]',
        );
        var activeCount = $(
            '#change-people-pax-details .cp-pax-detail[data-rate-id="' +
            rateId +
            '"]:not(.cp-marked-removed)',
        ).length;
        var originalCount = parseInt($row.data("original")) || 0;
        $row.find(".cp-pax-count").text(activeCount);
        $row.find(".hid-tourists-count").val(activeCount);

        var rateGroup = self.rateGroups.find(function (g) {
            return g.id == rateId;
        });
        if (rateGroup) {
            rateGroup.tourists = activeCount;
        }

        self.syncTouristsGroups(rateId, activeCount, originalCount);

        self.updateSeatsDisplay();
        self.updateSpinnerStates();
        self.calculateTotal();
    },

    updateSpinnerStates: function () {
        var self = this;
        var totalUsed = 0;

        $("#change-people-rate-groups-pills .cp-pax-row").each(function () {
            totalUsed += parseInt($(this).find(".cp-pax-count").text()) || 0;
        });

        var remaining = Math.max(0, self.totalSeatsForBooking - totalUsed);

        $("#change-people-rate-groups-pills .cp-pax-row").each(function () {
            var $row = $(this);
            var current = parseInt($row.find(".cp-pax-count").text()) || 0;
            var originalCount = parseInt($row.data("original")) || 0;

            var $incBtn = $row.find(".cp-inc-btn");
            var $decBtn = $row.find(".cp-dec-btn");

            if (remaining === 0) {
                $incBtn
                    .prop("disabled", true)
                    .css({ opacity: "0.5", cursor: "not-allowed" });
            } else {
                $incBtn
                    .prop("disabled", false)
                    .css({ opacity: "1", cursor: "pointer" });
            }

            if (current <= originalCount) {
                $decBtn
                    .prop("disabled", true)
                    .css({ opacity: "0.5", cursor: "not-allowed" });
            } else {
                $decBtn
                    .prop("disabled", false)
                    .css({ opacity: "1", cursor: "pointer" });
            }
        });
    },

    updatePaxDetailPrice: function ($detail) {
        var self = this;
        var basePrice =
            parseFloat($detail.find(".cp-pax-price").data("base-price")) || 0;
        var addonsTotal = 0;

        // data-fee is set on every checkbox/radio/option regardless of whether that field or
        // option actually has additional_fee enabled (Components.js only uses the flag to decide
        // whether to show the price badge) — data-option-additional-fee carries the real gate.
        // Without checking it here, a field/option with a leftover non-zero price but
        // additional_fee=0 gets counted as a fee on this "current" side while
        // getOriginalAddonTotal() (which does gate on it) correctly excludes it, producing a
        // phantom delta the instant the modal opens, before any edit is made.
        $detail.find("input:checked").each(function () {
            var hasFee = Number($(this).data("option-additional-fee")) === 1;
            var fee = hasFee ? (parseFloat($(this).data("fee")) || 0) : 0;
            addonsTotal += fee;
        });

        // A dropdown field can carry its own base fee (data-field-fee, set by
        // Components.buildDropdown()) independent of whichever option is selected (e.g.
        // "Photography Package" = $15 field price + up to $10 more depending on option) — both
        // must be summed, not just whichever one this used to pick via `||`.
        $detail.find("select").each(function () {
            var $selected = $(this).find("option:selected");
            var fieldFee = parseFloat($(this).data("field-fee")) || 0;
            var optionHasFee = Number($selected.data("option-additional-fee")) === 1;
            var optionFee = optionHasFee ? (parseFloat($selected.data("fee")) || 0) : 0;
            addonsTotal += fieldFee + optionFee;
        });

        $detail.find('input[type="number"]').each(function () {
            var hasFee = Number($(this).data("option-additional-fee")) === 1;
            var val = parseInt($(this).val()) || 0;
            var fee = hasFee ? (parseFloat($(this).data("fee")) || 0) : 0;
            addonsTotal += val * fee;
        });

        var totalPrice = basePrice + addonsTotal;
        $detail
            .find(".cp-pax-price")
            .text(self.currencySymbol + totalPrice.toFixed(2));
    },

    calculateTotal: function () {
        var self = this;
        var addedPaxTotal = 0;
        var removedPaxTotal = 0;
        var addedAddonTotal = 0;
        var removedAddonTotal = 0;
        var totalPax = 0;

        var removedOriginalPaxByRate = {};
        $("#change-people-pax-details .cp-pax-detail.cp-marked-removed").each(
            function () {
                var rateId = $(this).data("rate-id");
                var basePrice =
                    parseFloat(
                        $(this).find(".cp-pax-price").data("base-price"),
                    ) || 0;
                // Every card for the group-rate row is stamped with the SAME base price — the
                // price for the WHOLE group (see renderPaxDetails()), not a per-person share — so
                // summing basePrice per removed card here would double-count the removal on top
                // of the single headcount-based group-total delta computed below. Add-on refunds
                // for that person still count normally.
                var isGroupRateRow = self.isGroupRateBooking
                    && self.rateGroups[0]
                    && self.rateGroups[0].id == rateId;

                if (!removedOriginalPaxByRate[rateId]) {
                    removedOriginalPaxByRate[rateId] = { count: 0, total: 0 };
                }
                removedOriginalPaxByRate[rateId].count++;
                removedOriginalPaxByRate[rateId].total += basePrice;

                var originalAddonsTotal = self.getOriginalAddonTotal($(this));
                if (!isGroupRateRow) {
                    removedPaxTotal += basePrice;
                }
                removedAddonTotal += originalAddonsTotal;
            },
        );

        $("#change-people-rate-groups-pills .cp-pax-row").each(function () {
            var $row = $(this);
            var rateId = $row.data("rate-id");
            var countText = $row.find(".cp-pax-count").text();
            var activeCount = parseInt(countText) || 0;
            var originalCount = parseInt($row.data("original")) || 0;
            var price = parseFloat($row.data("price")) || 0;

            totalPax += activeCount;

            // Group-rate: price is a single tiered total for the WHOLE group, not a per-person
            // rate, so "how many NEW people * price" is meaningless — markExistingPaxAsRemoved()/
            // undoRemovePax() already keep activeCount in sync with removals, so the correct
            // payment/refund is simply the difference between the group's total at the CURRENT
            // headcount and its total at the ORIGINAL headcount.
            if (self.isGroupRateBooking && self.rateGroups[0] && self.rateGroups[0].id == rateId) {
                var startPricing = self.resolveGroupRatePricing(originalCount);
                var currentPricing = self.resolveGroupRatePricing(activeCount);
                var groupDelta = (currentPricing.rate + currentPricing.additionalCharge)
                    - (startPricing.rate + startPricing.additionalCharge);

                if (groupDelta > 0) {
                    addedPaxTotal += groupDelta;
                } else if (groupDelta < 0) {
                    removedPaxTotal += Math.abs(groupDelta);
                }
                return;
            }

            var removedOriginals = removedOriginalPaxByRate[rateId]
                ? removedOriginalPaxByRate[rateId].count
                : 0;
            var effectiveOriginal = originalCount - removedOriginals;

            var newPaxCount = Math.max(0, activeCount - effectiveOriginal);
            if (newPaxCount > 0) {
                addedPaxTotal += newPaxCount * price;
            }
        });

        $(
            "#change-people-pax-details .cp-pax-detail:not(.cp-marked-removed)",
        ).each(function () {
            var $detail = $(this);
            var isOriginal =
                $detail.data("is-original") === "1" ||
                $detail.data("is-original") === 1 ||
                $detail.data("is-original") === true;
            var detailAddonsTotal = 0;

            // data-fee is set on every checkbox/radio/option regardless of whether that field or
            // option actually has additional_fee enabled (Components.js only uses the flag to
            // decide whether to show the price badge) — data-option-additional-fee carries the
            // real gate. Without checking it here, a field/option with a leftover non-zero price
            // but additional_fee=0 gets counted on this "current" side while
            // getOriginalAddonTotal() (which does gate on it) correctly excludes it, producing a
            // phantom delta the instant the modal opens, before any edit is made.
            $detail.find('input[type="checkbox"]:checked').each(function () {
                var hasFee = Number($(this).data("option-additional-fee")) === 1;
                var fee = hasFee ? (parseFloat($(this).data("fee")) || 0) : 0;
                detailAddonsTotal += fee;
            });

            $detail.find('input[type="radio"]:checked').each(function () {
                var hasFee = Number($(this).data("option-additional-fee")) === 1;
                var fee = hasFee ? (parseFloat($(this).data("fee")) || 0) : 0;
                detailAddonsTotal += fee;
            });

            $detail.find("select").each(function () {
                // The field itself can carry its own base fee (data-field-fee, set by
                // Components.buildDropdown()) independent of whichever option is selected (e.g.
                // "Photography Package" = $15 field price + up to $10 more depending on option).
                // Reading only the option's fee here (dropping the field's own price) made an
                // ORIGINAL pax's "current" addon total look smaller than its correctly-computed
                // baseline (getOriginalAddonTotal(), which sums both), producing a false negative
                // delta — and thus a bogus "refund owed" total — the moment the modal opens, with
                // no edits made at all.
                var $selected = $(this).find("option:selected");
                var fieldFee = parseFloat($(this).data("field-fee")) || 0;
                var optionHasFee = Number($selected.data("option-additional-fee")) === 1;
                var optionFee = optionHasFee ? (parseFloat($selected.data("fee")) || 0) : 0;
                var fee = fieldFee + optionFee;
                detailAddonsTotal += fee;
            });

            $detail.find('input[type="number"]').each(function () {
                var hasFee = Number($(this).data("option-additional-fee")) === 1;
                var val = parseInt($(this).val()) || 0;
                var fee = hasFee ? (parseFloat($(this).data("fee")) || 0) : 0;
                detailAddonsTotal += val * fee;
            });

            if (isOriginal) {
                var originalAddonsTotal = self.getOriginalAddonTotal($detail);
                var addonDelta = detailAddonsTotal - originalAddonsTotal;
                if (addonDelta > 0) {
                    addedAddonTotal += addonDelta;
                } else if (addonDelta < 0) {
                    removedAddonTotal += Math.abs(addonDelta);
                }
            } else {
                addedAddonTotal += detailAddonsTotal;
            }
        });

        var totalPayment = addedPaxTotal + addedAddonTotal;
        var totalRefund = removedPaxTotal + removedAddonTotal;
        var finalAmount = totalPayment - totalRefund;

        if (typeof totalPaymentAmount !== 'undefined') {
            totalPaymentAmount = totalPayment;
        }
        if (typeof totalRefundAmount !== 'undefined') {
            totalRefundAmount = totalRefund;
        }
        if (typeof finalAmount !== 'undefined' && typeof window.finalAmount !== 'undefined') {
            window.finalAmount = finalAmount;
        }

        $('#payment_amount').val(totalPayment.toFixed(2));
        $('#refund_amount').val(totalRefund.toFixed(2));
        $('input[name="total_tourist"]').val(totalPax);

        var formattedTotal =
            self.currencySymbol + Math.abs(finalAmount).toFixed(2);
        if (finalAmount < 0) {
            formattedTotal = "-" + formattedTotal;
        }

        var $totalDisplay = $("#change-people-total");
        if ($totalDisplay.length) {
            $totalDisplay.text(formattedTotal);
        }

        var $totalEl = $(
            ".total-before-service-commission-and-discount-change-people",
        );
        $totalEl.text(formattedTotal);

        if (finalAmount >= 0) {
            $totalEl.css("color", "#16A34A");
        } else {
            $totalEl.css("color", "#DC2626");
        }

        var $payableAmount = $(
            "#edit_booking_change_people .total-payable-amount, #edit_booking_change_people .booking-total",
        );
        if ($payableAmount.length) {
            $payableAmount.text(formattedTotal);
        }

        var $paxCountDisplay = $("#change-people-pax-count");
        if ($paxCountDisplay.length) {
            $paxCountDisplay.text(totalPax);
        }
    },

    getOriginalAddonTotal: function ($detail) {
        var self = this;
        var uid = $detail.data("uid");
        var rateId = $detail.data("rate-id");
        var total = 0;

        if (self.paxAddonsState.fields[rateId]) {
            var paxEntry = self.paxAddonsState.fields[rateId].addons.find(
                function (p) {
                    return p.uid === uid;
                },
            );

            if (paxEntry && paxEntry.originalAddonTotal !== undefined) {
                return paxEntry.originalAddonTotal;
            }

            if (paxEntry && paxEntry.tree) {
                // Sum the DEFINITIONAL price of each already-selected field/option
                // (field_meta.price / option_meta.price), not `node.price` (never sent
                // by the server) and not `paid_price` (actual billing history) — an
                // existing selection is already "on the booking" regardless of whether
                // it was historically billed, so it must not be re-counted as newly added
                // just because it lacks a paid transaction record. Recurses into children
                // so nested/conditional addon fields are included too.
                var sumTree = function (nodes) {
                    if (!nodes || !nodes.length) return;
                    nodes.forEach(function (node) {
                        var fieldMeta = node.field_meta || {};
                        var optionMeta = node.option_meta || {};
                        if (fieldMeta.additional_fee && parseFloat(fieldMeta.price) > 0) {
                            total += parseFloat(fieldMeta.price) || 0;
                        }
                        if (optionMeta.additional_fee && parseFloat(optionMeta.price) > 0) {
                            total += parseFloat(optionMeta.price) || 0;
                        }
                        if (node.children && node.children.length) {
                            sumTree(node.children);
                        }
                    });
                };
                sumTree(paxEntry.tree);
            }
        }

        return total;
    },

    collectPaxAddonSelections: function () {
        var self = this;

        var rateCardIndices = {};

        $("#change-people-pax-details .cp-pax-detail").each(function () {
            var $detail = $(this);
            var uid = $detail.data("uid");
            var rateId = $detail.data("rate-id");

            if (!rateCardIndices[rateId]) {
                rateCardIndices[rateId] = 0;
            }
            var cardIndex = rateCardIndices[rateId];
            rateCardIndices[rateId]++;

            if (!self.paxAddonsState.fields[rateId]) {
                self.paxAddonsState.fields[rateId] = { addons: [] };
            }

            var paxEntry = self.paxAddonsState.fields[rateId].addons.find(
                function (p) {
                    return p.uid === uid;
                },
            );

            if (!paxEntry) {
                // Generate proper pax_index if creating new entry
                var slotId = $("#slot_time").val() || self.packageId || "";
                var timestamp = Date.now();
                var random = Math.random().toString(36).substring(2, 15);
                var paxIndex = slotId + "-" + timestamp + "-" + random + "-" + cardIndex;

                paxEntry = { uid: uid, selections: {}, pax_index: paxIndex, person_ordinal: cardIndex };
                self.paxAddonsState.fields[rateId].addons.push(paxEntry);
            } else {
                // If pax_index is missing or is just a number, generate proper one
                if (!paxEntry.pax_index || typeof paxEntry.pax_index === 'number') {
                    var slotId = $("#slot_time").val() || self.packageId || "";
                    var timestamp = Date.now();
                    var random = Math.random().toString(36).substring(2, 15);
                    paxEntry.pax_index = slotId + "-" + timestamp + "-" + random + "-" + cardIndex;
                }
                paxEntry.person_ordinal = cardIndex;
            }

            paxEntry.selections = {};
            var activeFieldIds = {};

            $detail.find(".addon-card").each(function () {
                var $card = $(this);
                var fieldId = $card.data("node-id") || $card.data("field-id");
                if (!fieldId) return;

                var isVisible =
                    $card.is(":visible") ||
                    $card.closest(".indent").css("display") !== "none";
                if (!isVisible) return;
                
                activeFieldIds[fieldId] = true;

                var uniqueId = fieldId + "_" + uid;
                var $input = $card.find("#" + uniqueId).first();
                if (!$input.length) {
                    $input = $card.find("input, select, textarea").first();
                }

                if ($input.length) {
                    var parentId = $card.data("parent-id") || null;
                    var unitType = $card.data("unit-type") || "price_per_pax";
                    var fieldLabel = $card.data("label") || "";
                    var fieldType = $card.data("type") || "";
                    var cardPaxId = $card.data("pax-id") || null;

                    var ruleId = $card.data("rule-id") || null;
                    var appliedRuleSnapshot = null;
                    var appliedRuleSnapshotStr = $card.data("applied-rule-snapshot") || null;
                    if (appliedRuleSnapshotStr) {
                        try {
                            appliedRuleSnapshot = typeof appliedRuleSnapshotStr === 'string'
                                ? JSON.parse(appliedRuleSnapshotStr)
                                : appliedRuleSnapshotStr;
                        } catch (e) {
                            appliedRuleSnapshot = null;
                        }
                    }

                    var paxIndex = paxEntry.pax_index !== undefined ? paxEntry.pax_index : 0;
                    var rateGroup = self.rateGroups.find(function (g) {
                        return g.id == rateId;
                    });
                    var personOrdinal = (rateGroup ? rateGroup.rate_for : "Guest") + " " + (parseInt(paxIndex) + 1);

                    if (cardPaxId) {
                        paxEntry.pax_id = parseInt(cardPaxId);
                    }

                    var selection = {
                        field_id: parseInt(fieldId),
                        field_label: fieldLabel,
                        type: fieldType,
                        value: null,
                        option_id: null,
                        option_label: null,
                        unit_type: unitType,
                        unit: 1,
                        rule_id: ruleId ? parseInt(ruleId) : null,
                        applied_rule_snapshot: appliedRuleSnapshot,
                        parent_id: parentId ? parseInt(parentId) : null,
                        person_ordinal: personOrdinal,
                        pax_id: paxEntry.pax_id || null,
                    };

                    if ($input.is(":checkbox")) {
                        selection.value = $input.prop("checked")
                            ? "checked"
                            : null;
                        selection.option_id = null;
                        selection.option_label = null;
                    } else if ($input.is(":radio")) {
                        var $checked = $card.find(
                            'input[type="radio"]:checked',
                        );
                        if ($checked.length) {
                            selection.value = $checked.val();
                            var optId = $checked.data("option-id");
                            selection.option_id = optId ? parseInt(optId) : null;
                            selection.option_label = optId ? $checked.siblings("label").text().trim() : null;
                        }
                    } else if ($input.is("select")) {
                        selection.value = $input.val();
                        var $selectedOpt = $input.find("option:selected");
                        var optId = $selectedOpt.data("option-id");
                        selection.option_id = optId ? parseInt(optId) : null;
                        selection.option_label = optId ? $selectedOpt.text().trim() : null;
                    } else {
                        selection.value = $input.val();
                        selection.option_id = null;
                        selection.option_label = null;
                    }

                    if (selection.value) {
                        paxEntry.selections[fieldId] = selection;
                    }
                }
            });

            // Check if the whole passenger is marked for removal first
            if (paxEntry.markedForRemoval) {
                // If the whole person is gone, we must delete EVERYTHING for them
                if (paxEntry.existingDbIds) {
                    paxEntry.deletedFieldIds = Object.values(paxEntry.existingDbIds);
                }
            } else if (paxEntry.isOriginal && paxEntry.existingDbIds) {
                // ONLY run this "unselected" logic if the passenger themselves is staying
                paxEntry.deletedFieldIds = [];

                Object.keys(paxEntry.existingDbIds).forEach(function (fieldId) {
                    var isFieldStillActive = activeFieldIds[fieldId];
                    var hasActiveSelection =
                        paxEntry.selections &&
                        paxEntry.selections[fieldId] &&
                        paxEntry.selections[fieldId].value;

                    /*
                    * DELETE when:
                    * 1. Field is no longer active (hidden by parent/rule)
                    * 2. OR field active but value cleared
                    */
                    if (!isFieldStillActive || !hasActiveSelection) {
                        paxEntry.deletedFieldIds.push(
                            paxEntry.existingDbIds[fieldId]
                        );
                    }
                });
            }
        });
    },

    getSubmissionData: function () {
        var self = this;

        self.collectPaxAddonSelections();

        var data = {
            booking_id: self.bookingId,
            form_id: self.paxAddonsState.form_id,
            rate_groups: [],
            added: [],
            updated: [],
            deleted: [],
        };

        $("#change-people-rate-groups-pills .cp-pax-row").each(function () {
            var $row = $(this);
            var groupId = $row.data("rate-id");
            var count = parseInt($row.find(".cp-pax-count").text()) || 0;

            data.rate_groups.push({
                rate_group_id: groupId,
                tourists: count,
            });
        });

        Object.keys(self.paxAddonsState.fields).forEach(function (rateId) {
            var groupData = self.paxAddonsState.fields[rateId];

            if (groupData.addons) {
                groupData.addons.forEach(function (pax, paxIndex) {
                    var isOriginal = pax.isOriginal || false;

                    if (pax.selections) {
                        Object.keys(pax.selections).forEach(function (fieldId) {
                            var sel = pax.selections[fieldId];
                            if (sel.value) {
                                var addonEntry = {
                                    field_id: sel.field_id,
                                    option_id: sel.option_id,
                                    option_label: sel.option_label,
                                    value: sel.value,
                                    unit_type: sel.unit_type || "price_per_pax",
                                    unit: sel.unit || 1,
                                    rule_id: sel.rule_id || null,
                                    applied_rule_snapshot:
                                        sel.applied_rule_snapshot || null,
                                    parent_id: sel.parent_id || null,
                                    pax_id: pax.pax_id || null,
                                    pax_index: pax.pax_index !== undefined ? pax.pax_index : paxIndex,
                                };

                                if (
                                    isOriginal &&
                                    pax.existingDbIds &&
                                    pax.existingDbIds[fieldId]
                                ) {
                                    addonEntry.db_id =
                                        pax.existingDbIds[fieldId];
                                    data.updated.push(addonEntry);
                                } else if (!isOriginal) {
                                    data.added.push(addonEntry);
                                }
                            }
                        });
                    }

                    if (isOriginal && pax.deletedFieldIds) {
                        pax.deletedFieldIds.forEach(function (dbId) {
                            data.deleted.push({ db_id: dbId });
                        });
                    }
                });
            }
        });

        return data;
    },

    hasChanges: function () {
        var self = this;
        var hasChanges = false;

        // Check if tourist counts have changed
        self.rateGroups.forEach(function (rateGroup) {
            var currentCount = parseInt(rateGroup.tourists) || 0;
            var originalCount = self.originalTourists[rateGroup.id] || 0;
            if (currentCount !== originalCount) {
                hasChanges = true;
            }
        });

        // Check if any pax are marked for removal
        Object.keys(self.paxAddonsState.fields).forEach(function (rateId) {
            var groupData = self.paxAddonsState.fields[rateId];
            if (groupData.addons) {
                groupData.addons.forEach(function (pax) {
                    if (pax.markedForRemoval) {
                        hasChanges = true;
                    }
                });
            }
        });

        if (hasChanges) {
            return true;
        }

        // Collect current addon selections before checking for changes
        self.collectPaxAddonSelections();

        // Check if there are any addon changes (added, updated, or deleted)
        var payload = self.getAddonsPayload();
        return (
            payload.added_addons.length > 0 ||
            payload.removed_addons.length > 0 ||
            payload.updated_addons.length > 0
        );
    },

    resetState: function () {
        this.paxAddonsState = { form_id: 0, fields: {} };
        this.pendingPaxAddition = null;
        this.originalTourists = {};
        this.originalPaxData = {};
        this.addonsLoaded = false;
        this.addonFormData = null;
    },

    getAddonsPayload: function () {
        var self = this;

        var addedAddons = [];
        var removedAddons = [];
        var updatedAddons = [];

        Object.keys(self.paxAddonsState.fields).forEach(function (rateId) {
            var groupData = self.paxAddonsState.fields[rateId];
            var rateGroup = self.rateGroups.find(function (g) {
                return g.id == rateId;
            });
            var rateFor = rateGroup ? rateGroup.rate_for : "";

            if (groupData.addons) {
                groupData.addons.forEach(function (pax, paxIndex) {
                    var isOriginal = pax.isOriginal || false;
                    var isMarkedRemoved = pax.markedForRemoval || false;

                    if (isOriginal && isMarkedRemoved) {
                        if (
                            pax.deletedFieldIds &&
                            pax.deletedFieldIds.length > 0
                        ) {
                            pax.deletedFieldIds.forEach(function (dbId) {
                                removedAddons.push({
                                    db_id: dbId,
                                    pax_id: pax.pax_id,
                                    pax_index: pax.pax_index !== undefined ? pax.pax_index : paxIndex,
                                    rate_group_id: rateId,
                                    rate_for: rateFor,
                                });
                            });
                        }
                        return;
                    }

                    if (pax.selections) {
                        Object.keys(pax.selections).forEach(function (fieldId) {
                            var sel = pax.selections[fieldId];
                            if (!sel.value) return;

                            var personOrdinal = pax.person_ordinal !== undefined ? pax.person_ordinal : paxIndex;
                            var addonEntry = {
                                form_id: self.paxAddonsState.form_id,
                                field_id: sel.field_id,
                                field_label: sel.field_label || "",
                                option_id: sel.option_id || null,
                                option_label: sel.option_label || null,
                                value: sel.value,
                                unit_type: sel.unit_type || "price_per_pax",
                                unit: sel.unit || 0,
                                rule_id: sel.rule_id || null,
                                applied_rule_snapshot:
                                    sel.applied_rule_snapshot || null,
                                parent_id: sel.parent_id || null,
                                pax_id: pax.pax_id || null,
                                pax_index: pax.pax_index !== undefined ? pax.pax_index : paxIndex,
                                rate_group_id: rateId,
                                rate_for: rateFor,
                                person_ordinal: personOrdinal,
                            };

                            if (isOriginal) {
                                if (
                                    pax.existingDbIds &&
                                    pax.existingDbIds[fieldId]
                                ) {
                                    // Existing field being updated
                                    addonEntry.db_id =
                                        pax.existingDbIds[fieldId];
                                    updatedAddons.push(addonEntry);
                                } else {
                                    // New field being added to existing pax
                                    addedAddons.push(addonEntry);
                                }
                            } else {
                                // New pax with new fields
                                addedAddons.push(addonEntry);
                            }
                        });
                    }

                    if (
                        isOriginal &&
                        pax.deletedFieldIds &&
                        pax.deletedFieldIds.length > 0 &&
                        !isMarkedRemoved
                    ) {
                        pax.deletedFieldIds.forEach(function (dbId) {
                            removedAddons.push({
                                db_id: dbId,
                                pax_id: pax.pax_id,
                                pax_index: pax.pax_index !== undefined ? pax.pax_index : paxIndex,
                                rate_group_id: rateId,
                                rate_for: rateFor,
                            });
                        });
                    }
                });
            }
        });

        return {
            form_id: self.paxAddonsState.form_id,
            added_addons: addedAddons,
            removed_addons: removedAddons,
            updated_addons: updatedAddons,
        };
    },

    // Builds the "Remove Passenger Add-on(s)" popup's breakdown (same markup/IDs as the direct
    // delete-pax-addons flow in tour-booking-pax-addons-operations.js) for an add-on-only Change
    // People edit. removed_addons only carries ids (db_id/pax_index/rate_group_id) — the price/fee
    // per field lives in originalPaxData's snapshot (fetched once via get-booking-pax-addons and
    // never mutated by removal), keyed by rate_group_id -> pax -> tree node (node.id === db_id).
    buildAddonRemovalBreakdown: function () {
        var self = this;
        var cs = self.currencySymbol || "$";
        var removed = self.getAddonsPayload().removed_addons || [];

        function findNode(nodes, id) {
            if (!nodes) return null;
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].id == id) return nodes[i];
                var found = findNode(nodes[i].children, id);
                if (found) return found;
            }
            return null;
        }

        var totalPrice = 0;
        var totalFee = 0;
        var hasPaidTxn = false;
        var rowsByPax = {};
        var order = [];

        removed.forEach(function (item) {
            var rgData = self.originalPaxData[item.rate_group_id];
            var paxEntry = rgData && rgData.pax
                ? rgData.pax.find(function (p) { return p.pax_index == item.pax_index; })
                : null;
            var node = paxEntry ? findNode(paxEntry.tree, item.db_id) : null;
            if (!node) return;

            var price = parseFloat(node.paid_price || 0);
            var fee = parseFloat(node.paid_fee || 0);
            if (price > 0 || fee > 0) hasPaidTxn = true;
            totalPrice += price;
            totalFee += fee;

            var key = item.rate_group_id + "_" + item.pax_index;
            if (!rowsByPax[key]) {
                var ordinal = (paxEntry.person_ordinal !== null && paxEntry.person_ordinal !== undefined)
                    ? " #" + (parseInt(paxEntry.person_ordinal) + 1) : "";
                rowsByPax[key] = {
                    rateFor: item.rate_for || "Passenger",
                    ordinal: ordinal,
                    labels: [],
                    price: 0,
                    fee: 0,
                };
                order.push(key);
            }
            rowsByPax[key].price += price;
            rowsByPax[key].fee += fee;
            if (node.field_label) rowsByPax[key].labels.push(node.field_label);
        });

        var rowsHtml = order.map(function (key) {
            var row = rowsByPax[key];
            return (
                '<div class="flex items-start justify-between py-2.5 border-b border-[#F3F4F6] last:border-0">' +
                '<div class="flex-1 min-w-0 pr-3">' +
                '<div class="text-xs font-semibold text-[#374151] uppercase tracking-wide leading-tight mb-0.5">' + row.rateFor + row.ordinal + '</div>' +
                '<div class="text-sm text-[#111827] leading-snug truncate">' + (row.labels.join(", ") || "Add-on") + '</div>' +
                '</div>' +
                '<div class="text-right flex-shrink-0">' +
                '<div class="text-sm font-semibold text-[#111827]">' + cs + row.price.toFixed(2) + '</div>' +
                (row.fee > 0 ? '<div class="text-xs text-[#9CA3AF]">fee ' + cs + row.fee.toFixed(2) + '</div>' : '') +
                '</div>' +
                '</div>'
            );
        }).join('');

        var grand = totalPrice + totalFee;
        var html = rowsHtml ? (
            '<div class="rounded-xl border border-[#E5E7EB] overflow-hidden mb-1">' +
            '<div class="px-4 py-2.5 flex items-center justify-between" style="background:#F8FAFC;border-bottom:1px solid #E5E7EB">' +
            '<span class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide">Add-ons to remove</span>' +
            '<span class="text-xs text-[#9CA3AF]">' + order.length + ' passenger' + (order.length > 1 ? 's' : '') + '</span>' +
            '</div>' +
            '<div class="bg-white px-4 divide-y divide-[#F9FAFB]">' + rowsHtml + '</div>' +
            '<div class="px-4 py-3 flex items-center justify-between" style="background:#F8FAFC;border-top:1px solid #E5E7EB">' +
            '<span class="text-xs font-semibold text-[#374151]">Total originally charged</span>' +
            '<span class="text-sm font-bold text-[#111827]">' + cs + grand.toFixed(2) + '</span>' +
            '</div>' +
            '</div>'
        ) : '';

        $('#pax-refund-ticket-amount').text(cs + totalPrice.toFixed(2));
        $('#pax-refund-full-amount').text(cs + grand.toFixed(2));

        return { html: html, totalPrice: totalPrice, totalFee: totalFee, hasPaidTxn: hasPaidTxn };
    },

    // Builds the "Refund to Customer" popup's breakdown (same markup/IDs the bulk pax-addon-edit
    // flow's TourBookingAddons._showRefundModal() uses, in tour-booking-addons.js) for an
    // add-on-only Change People edit that includes at least one option DOWNGRADE (option changed
    // to a cheaper one), optionally alongside full removals. The OLD side of each delta is the
    // actual paid_price/paid_fee from originalPaxData's tree snapshot (not the field's current
    // definitional price) so a field that already had a prior partial refund isn't double-counted;
    // the NEW side is read live off the field's own .addon-card (data-fee/data-field-fee), which is
    // the field's current definitional price regardless of which addon renderer (the fallback here
    // or Components.renderField in addons/components.js) built it — both tag the same attributes.
    buildAddonEditRefundBreakdown: function () {
        var self = this;
        var cs = self.currencySymbol || "$";
        var payload = self.getAddonsPayload();
        var updated = payload.updated_addons || [];
        var removed = payload.removed_addons || [];

        function findNode(nodes, id) {
            if (!nodes) return null;
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].id == id) return nodes[i];
                var found = findNode(nodes[i].children, id);
                if (found) return found;
            }
            return null;
        }

        function resolveNode(item) {
            var rgData = self.originalPaxData[item.rate_group_id];
            var paxEntry = rgData && rgData.pax
                ? rgData.pax.find(function (p) { return p.pax_index == item.pax_index; })
                : null;
            return paxEntry ? findNode(paxEntry.tree, item.db_id) : null;
        }

        function liveFieldTotal($card) {
            // data-fee is set regardless of whether additional_fee is actually enabled
            // (Components.js only uses the flag for the price badge) — data-option-additional-fee
            // carries the real gate. See updatePaxDetailPrice()/calculateTotal() above for why
            // skipping this check inflates the total for fields/options with a leftover
            // non-zero price but additional_fee=0.
            var total = 0;
            $card.find('input[type="checkbox"]:checked, input[type="radio"]:checked').each(function () {
                var hasFee = Number($(this).data('option-additional-fee')) === 1;
                total += hasFee ? (parseFloat($(this).data('fee')) || 0) : 0;
            });
            $card.find('select').each(function () {
                var $selected = $(this).find('option:selected');
                var optionHasFee = Number($selected.data('option-additional-fee')) === 1;
                total += (parseFloat($(this).data('field-fee')) || 0) + (optionHasFee ? (parseFloat($selected.data('fee')) || 0) : 0);
            });
            $card.find('input[type="number"]').each(function () {
                var hasFee = Number($(this).data('option-additional-fee')) === 1;
                total += (parseInt($(this).val()) || 0) * (hasFee ? (parseFloat($(this).data('fee')) || 0) : 0);
            });
            return total;
        }

        // getAddonsPayload() pushes an updated_addons entry for EVERY pax with an existing
        // selection on this field — it's also used just to keep field rows in sync, so it doesn't
        // filter out pax whose selection didn't actually change in this edit. Without this check,
        // every OTHER pax sharing the same field (e.g. everyone with a "Sweater" selection) would
        // show up as "downgraded" here too, not just the one the admin actually edited.
        function selectionUnchanged(item, node) {
            var itemOptionId = (item.option_id !== undefined && item.option_id !== null) ? String(item.option_id) : null;
            var nodeOptionId = (node.option_id !== undefined && node.option_id !== null) ? String(node.option_id) : null;
            if (itemOptionId !== null || nodeOptionId !== null) {
                return itemOptionId === nodeOptionId;
            }
            return String(item.value) === String(node.value);
        }

        // Whether the field's CURRENT saved selection carries a price at all. A free add-on has
        // nothing to pay, so it never belongs in the "no payment recorded" list below.
        function nodeIsPriced(node) {
            var fm = node.field_meta || {};
            var om = node.option_meta || {};
            return (!!fm.additional_fee && parseFloat(fm.price || 0) > 0)
                || (!!om.additional_fee && parseFloat(om.price || 0) > 0);
        }

        function escapeText(text) {
            return $('<div>').text(text == null ? '' : String(text)).html();
        }

        var toRefund = [];
        var toCharge = [];
        // PRICED add-ons edited/removed with NOTHING recorded as paid toward them (e.g. added via
        // a payment link that's still pending) — no money to give back, so the popup offers only
        // "Don't Refund", same as deleting an unpaid pax.
        var unpaidItems = [];

        updated.forEach(function (item) {
            var node = resolveNode(item);
            if (!node) return;
            if (selectionUnchanged(item, node)) return;
            if (parseFloat(node.paid_price || 0) <= 0 && nodeIsPriced(node)) {
                unpaidItems.push({ label: item.field_label || node.field_label || 'Add-on', reason: 'changed' });
            }

            // paid_price/paid_fee are tracked as separate columns server-side (price vs. the
            // nat_fees_percentage service surcharge), but liveFieldTotal() above only ever reads
            // definitional PRICE (data-fee/data-field-fee hold field.price/opt.price, not a fee —
            // this file names that variable "fee" everywhere, but it's price). Comparing a
            // fee-inclusive old total against a fee-exclusive new total would inflate every
            // delta by ~the fee amount, so only paid_price is used here.
            var oldPaid = parseFloat(node.paid_price || 0);
            if (oldPaid <= 0) return;

            var uid = "existing_" + item.pax_id + "_" + item.pax_index;
            var $card = $('.cp-pax-detail[data-uid="' + uid + '"] .addon-card[data-field-id="' + item.field_id + '"]');
            if (!$card.length) return;

            var delta = liveFieldTotal($card) - oldPaid;
            var label = item.field_label || node.field_label || 'Add-on';

            if (delta < -0.001) {
                toRefund.push({ label: label, amount: parseFloat(Math.abs(delta).toFixed(2)), reason: 'downgraded' });
            } else if (delta > 0.001) {
                toCharge.push({ label: label, amount: parseFloat(delta.toFixed(2)) });
            }
        });

        removed.forEach(function (item) {
            var node = resolveNode(item);
            if (!node) return;
            var amount = parseFloat(node.paid_price || 0) + parseFloat(node.paid_fee || 0);
            if (amount > 0) {
                toRefund.push({ label: node.field_label || 'Add-on', amount: parseFloat(amount.toFixed(2)), reason: 'removed' });
            } else if (nodeIsPriced(node)) {
                unpaidItems.push({ label: node.field_label || 'Add-on', reason: 'removed' });
            }
        });

        var unpaidHtml = '';
        if (unpaidItems.length) {
            unpaidHtml += '<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">Unpaid Add-ons</div>';
            unpaidHtml += '<div class="rounded-lg border border-[#E5E7EB] overflow-hidden mb-3">';
            unpaidItems.forEach(function (item) {
                var badge = '<span style="font-size:0.7rem;padding:2px 8px;border-radius:20px;background:#F3F4F6;color:#374151;font-weight:600">' + (item.reason === 'removed' ? 'Removed' : 'Changed') + '</span>';
                unpaidHtml += '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #F3F4F6">' +
                    '<div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">' +
                    '<span style="font-size:0.875rem;color:#111827;font-weight:500">' + escapeText(item.label) + '</span>' + badge +
                    '</div>' +
                    '<span style="font-size:0.8rem;font-weight:600;color:#6B7280;white-space:nowrap;margin-left:12px">No payment recorded — no refund</span>' +
                    '</div>';
            });
            unpaidHtml += '</div>';
        }

        var totalRefund = parseFloat(toRefund.reduce(function (s, i) { return s + i.amount; }, 0).toFixed(2));
        var totalCharge = parseFloat(toCharge.reduce(function (s, i) { return s + i.amount; }, 0).toFixed(2));
        var netAmount = parseFloat((totalRefund - totalCharge).toFixed(2));

        var reasonMap = { removed: 'Removed', downgraded: 'Downgraded' };
        var html = '';
        if (toRefund.length) {
            html += '<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">Items Being Refunded</div>';
            html += '<div class="rounded-lg border border-[#FED7AA] overflow-hidden mb-3">';
            toRefund.forEach(function (item) {
                var badge = '<span style="font-size:0.7rem;padding:2px 8px;border-radius:20px;background:#FEF3C7;color:#92400E;font-weight:600">' + (reasonMap[item.reason] || item.reason) + '</span>';
                html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #FFF7ED">' +
                    '<div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">' +
                    '<span style="font-size:0.875rem;color:#111827;font-weight:500">' + item.label + '</span>' + badge +
                    '</div>' +
                    '<span style="font-size:0.9rem;font-weight:700;color:#EA580C;white-space:nowrap;margin-left:12px">' + cs + item.amount.toFixed(2) + '</span>' +
                    '</div>';
            });
            html += '</div>';
        }
        if (toCharge.length) {
            html += '<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">New Charges (offset against refund)</div>';
            html += '<div class="rounded-lg border border-[#D1FAE5] overflow-hidden mb-3">';
            toCharge.forEach(function (item) {
                html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #F0FDF4">' +
                    '<span style="font-size:0.875rem;color:#111827;font-weight:500">' + item.label + '</span>' +
                    '<span style="font-size:0.9rem;font-weight:700;color:#059669;white-space:nowrap;margin-left:12px">-' + cs + item.amount.toFixed(2) + '</span>' +
                    '</div>';
            });
            html += '</div>';
        }
        html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:8px;margin-bottom:4px">' +
            '<span style="font-size:0.875rem;font-weight:600;color:#374151">Net Refund Amount</span>' +
            '<span style="font-size:1.05rem;font-weight:800;color:#EA580C">' + cs + Math.abs(netAmount).toFixed(2) + '</span>' +
            '</div>';

        return {
            html: html,
            netAmount: Math.abs(netAmount),
            hasDowngrade: toRefund.some(function (i) { return i.reason === 'downgraded'; }),
            hasAnyItem: toRefund.length > 0 || toCharge.length > 0,
            hasUnpaidItem: unpaidItems.length > 0,
            unpaidHtml: unpaidHtml,
        };
    },

    appendAddonsToForm: function ($form) {
        var self = this;
        self.collectPaxAddonSelections();
        var payload = self.getAddonsPayload();

        $form.find('input[name^="addons_"]').remove();

        var hasAddonChanges =
            payload.added_addons.length > 0 ||
            payload.removed_addons.length > 0 ||
            payload.updated_addons.length > 0;

        if (!hasAddonChanges) {
            return payload;
        }

        $form.append(
            '<input type="hidden" name="addons_form_id" value="' +
            payload.form_id +
            '">',
        );
        $form.append(
            '<input type="hidden" name="addons_payload" value=\'' +
            JSON.stringify(payload) +
            "'>",
        );

        payload.added_addons.forEach(function (addon, idx) {
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][form_id]" value="' +
                addon.form_id +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][field_id]" value="' +
                addon.field_id +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][field_label]" value="' +
                (addon.field_label || "") +
                '">',
            );
            if (addon.option_id !== null && addon.option_id !== undefined) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][option_id]" value="' +
                    addon.option_id +
                    '">',
                );
            }
            if (addon.option_label !== null && addon.option_label !== undefined) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][option_label]" value="' +
                    addon.option_label +
                    '">',
                );
            }
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][value]" value="' +
                addon.value +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][unit_type]" value="' +
                addon.unit_type +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][unit]" value="' +
                addon.unit +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][pax_id]" value="' +
                (addon.pax_id || "") +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][pax_index]" value="' +
                addon.pax_index +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_added[' +
                idx +
                '][rate_group_id]" value="' +
                addon.rate_group_id +
                '">',
            );
            if (addon.rule_id) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][rule_id]" value="' +
                    addon.rule_id +
                    '">',
                );
            }
            if (addon.parent_id) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][parent_id]" value="' +
                    addon.parent_id +
                    '">',
                );
            }
            if (addon.rate_for) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][rate_for]" value="' +
                    addon.rate_for +
                    '">',
                );
            }
            if (addon.person_ordinal) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][person_ordinal]" value="' +
                    addon.person_ordinal +
                    '">',
                );
            }
            if (addon.applied_rule_snapshot) {
                $form.append(
                    '<input type="hidden" name="addons_added[' +
                    idx +
                    '][applied_rule_snapshot]" value=\'' +
                    JSON.stringify(addon.applied_rule_snapshot) +
                    "'>",
                );
            }
        });

        payload.removed_addons.forEach(function (addon, idx) {
            $form.append(
                '<input type="hidden" name="addons_removed[' +
                idx +
                '][db_id]" value="' +
                addon.db_id +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_removed[' +
                idx +
                '][pax_id]" value="' +
                (addon.pax_id || "") +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_removed[' +
                idx +
                '][pax_index]" value="' +
                addon.pax_index +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_removed[' +
                idx +
                '][rate_group_id]" value="' +
                addon.rate_group_id +
                '">',
            );
        });

        payload.updated_addons.forEach(function (addon, idx) {
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][db_id]" value="' +
                addon.db_id +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][form_id]" value="' +
                addon.form_id +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][field_id]" value="' +
                addon.field_id +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][field_label]" value="' +
                (addon.field_label || "") +
                '">',
            );
            if (addon.option_id !== null && addon.option_id !== undefined) {
                $form.append(
                    '<input type="hidden" name="addons_updated[' +
                    idx +
                    '][option_id]" value="' +
                    addon.option_id +
                    '">',
                );
            }
            if (addon.option_label !== null && addon.option_label !== undefined) {
                $form.append(
                    '<input type="hidden" name="addons_updated[' +
                    idx +
                    '][option_label]" value="' +
                    addon.option_label +
                    '">',
                );
            }
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][value]" value="' +
                addon.value +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][unit_type]" value="' +
                addon.unit_type +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][unit]" value="' +
                addon.unit +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][pax_id]" value="' +
                (addon.pax_id || "") +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][pax_index]" value="' +
                addon.pax_index +
                '">',
            );
            $form.append(
                '<input type="hidden" name="addons_updated[' +
                idx +
                '][rate_group_id]" value="' +
                addon.rate_group_id +
                '">',
            );
        });

        return payload;
    },

    // Shared by both trigger points that build a Card PaymentIntent for a Change People
    // submission (affiliated-payment.js's validateAndSubmitForm1 and new-payment.js's
    // validateAndSubmitForm2) — previously duplicated verbatim in both files. Appends the
    // "change_people" feature marker, the priced add-on delta (so the PaymentIntent is created
    // for the correct combined tour+add-on amount), and ensures payment_method is present since
    // the form doesn't reliably carry it at this point.
    prepareCardPaymentForm: function ($form) {
        $form.append('<input type="hidden" name="feature" value="change_people">');
        if (this.appendAddonsToForm) {
            this.appendAddonsToForm($form);
        }
        if ($form.find('input[name="payment_method"]').length === 0) {
            $form.append('<input type="hidden" name="payment_method" value="Card">');
        } else {
            $form.find('input[name="payment_method"]').val('Card');
        }
    },

    validateAddons: function () {
        let isValid = true;
        let hasRequired = false;

        const paxFields = this.getPaxFields();

        if (typeof Components === "undefined" || !Components.validateContainer) {
            return { isValid: true, hasRequired: false };
        }

        document
            .querySelectorAll('#change-people-pax-details .cp-addon-fields')
            .forEach(container => {

                // Ignore removed pax
                if (container.closest('.cp-pax-detail.cp-marked-removed')) {
                    return;
                }

                paxFields.forEach(fieldDef => {
                    const result = Components.validateContainer(container, fieldDef);

                    if (!result.isValid) isValid = false;
                    if (result.hasRequired) hasRequired = true;
                });
            });

        return { isValid, hasRequired };
    },

    toggleBulkRemoveButton: function () {
        var $btn = $("#cp-bulk-update-and-remove-btn");
        
        // Check if there are any "new" guests (identified by the badge/data-is-original)
        var isAddingNew = $(".cp-pax-detail[data-is-original='0']").length > 0;
        
        // Check if any guest is selected for removal via checkbox
        var isRemovingSelected = $(".cp-remove-checkbox:checked").length > 0;

        // Show button if we are either adding new people OR removing existing ones
        if (isAddingNew || isRemovingSelected) {
            if ($btn.length === 0) {
                // Create the button if it doesn't exist in the header actions
                $(".cp-header-actions").append('<button type="button" id="cp-bulk-update-and-remove-btn"></button>');
                $btn = $("#cp-bulk-update-and-remove-btn");
            }

            /**
             * LOGIC REQUIREMENTS:
             * 1. Any pax added + No checkbox selected -> "Update & Confirm Booking"
             * 2. Any removal selected + No new pax added -> "Remove & Confirm Booking"
             * 3. Removal selected + New pax added      -> "Update & Confirm Booking"
             */
            if (isAddingNew) {
                // Cases 1 & 3: If new guests are present, always prioritize "Update"
                $btn.text("Update & Confirm Booking")
                    .removeClass("btn-danger-small")
                    .addClass("btn-primary-small")
                    .css({ "display": "inline-block", "width": "auto", "margin-top": "0" });
            } else {
                // Case 2: Only removing existing pax, no new additions
                $btn.text("Remove & Confirm Booking")
                    .removeClass("btn-primary-small")
                    .addClass("btn-danger-small")
                    .css({ "display": "inline-block", "width": "auto", "margin-top": "0" });
            }
            $btn.show();
        } else {
            // Hide button if no changes are detected
            $btn.hide();
        }
    },

    updateSelectAllState: function () {
        var total = $("#change-people-pax-details .cp-remove-checkbox").length;
        var checked = $("#change-people-pax-details .cp-remove-checkbox:checked").length;

        $("#cp-select-all").prop("checked", total > 0 && total === checked);
    },

    captureCurrentAddonState: function () {
        var self = this;
        $("#change-people-pax-details .cp-pax-detail").each(function () {
            var $detail = $(this);
            var uid = $detail.data("uid");
            var rateId = $detail.data("rate-id");

            if (self.paxAddonsState.fields[rateId]) {
                var paxEntry = self.paxAddonsState.fields[rateId].addons.find(function(p) {
                    return p.uid === uid;
                });
                
                if (paxEntry) {
                    // Initialize/clear temporary state for current UI values
                    paxEntry.uiState = []; 

                    $detail.find(".addon-card").each(function () {
                        var $card = $(this);
                        var $input = $card.find("input, select, textarea").first();
                        var value = "";
                        var optionLabel = "";
                        var optionId = null;

                        if ($input.is(":checkbox")) {
                            value = $input.is(":checked") ? "checked" : "";
                        } else if ($input.is(":radio")) {
                            var $checkedRadio = $card.find('input[type="radio"]:checked');
                            value = $checkedRadio.val() || "";
                            optionLabel = $checkedRadio.siblings("label").text().trim();
                            optionId = $checkedRadio.data("option-id");
                        } else if ($input.is("select")) {
                            value = $input.val();
                            var $selectedOption = $input.find("option:selected");
                            optionLabel = $selectedOption.text().trim();
                            optionId = $selectedOption.data("option-id");
                        } else {
                            value = $input.val();
                        }

                        // option_id must be captured here too — fillNodeValue() matches by
                        // option_id first specifically to avoid the fuzzy label-substring
                        // matching bug (e.g. "Large" vs "X-Large"); without it, every re-render
                        // triggered by a pax-count change (changePaxCount() calls this before
                        // renderPaxDetails()) falls back to that same broken matching and can
                        // silently re-select the wrong option.
                        paxEntry.uiState.push({
                            id: $card.data("node-id") || $card.data("field-id"),
                            field_label: $card.attr("data-label"),
                            value: value,
                            option_label: optionLabel,
                            option_id: (optionId !== undefined && optionId !== null && optionId !== "") ? optionId : null
                        });
                    });
                }
            }
        });
    },
};

$(document).ready(function () {
    changePeopleV2.init();
});
