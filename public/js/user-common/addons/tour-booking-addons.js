class TourBookingAddons {
    constructor(props) {
        this.props = props;
        this.listAddonsContainer = $('#booking-addons-section');
        this.addonsFormContainer = $('#booking-addons-form-section');
        this.paxAddonsFormContainer = $('#add-pax-addons-form-container');
        this.packageId      = props.packageId;
        this.bookingId      = props.bookingId;
        this.tourSlotId     = props.tourSlotId;
        this.bookingPaymentVia   = props.paymentVia || 'Pay Later';
        this.natFeePct           = parseFloat(props.natFeePct) || 0;
        this.isCashFees          = props.isCashFees == '1' || props.isCashFees === true;
        this.isCancelled         = props.isCancelled === true || props.isCancelled === 'true';
        this.userState = null; // Stores current selections
        this.addonsFields = null;
        this.bookingAddonsState = { // Updated add-ons selections
            form_id: 0,
            fields: {}
        };

        if (props.admin === 'Admin') {
            this.admin = "/admin/tour-operator";
        } else if (props.admin === 'Tour Operator') {
            this.admin = "/tour-operator";
        } else if (props.admin === 'Affiliate') {
            this.admin = "/affiliate";
        } else {
            this.admin = "";
        }

        this.getAddonsFormUrl = `${this.admin}/package/get-addon-form/${this.packageId}`;
        this.fetchBookingAddonsUrl = `${this.admin}/booking/get-booking-addons-submission`;
        this.updateBookingAddonsUrl = `${this.admin}/booking/update-booking-addon-submission`;
        this.updateBulkPaxAddonsUrl = `${this.admin}/booking/update-pax-addons`;
        this.getBookingPaxAddonsUrl = `${this.admin}/booking/get-booking-pax-addons`;
        this.addonPaymentLinkUrl    = `${this.admin}/booking/addon-payment-link`;

        this.addEvents();
        this.fetchAddons();
    }

    get currencyFormation() {
        return typeof currencyFormation !== 'undefined' ? currencyFormation : { symbol: '', format: (a) => a };
    }

    /**
     * Add Event Listeners
     */
    addEvents() {
        $(document).off('click', '.edit-addons-form').on('click', '.edit-addons-form', this.onEditForm.bind(this));
        $(document).off('click', '.update-addons').on('click', '.update-addons', this.updateAddonForm.bind(this));
        $(document).off('click', '.cancel-addons').on('click', '.cancel-addons', this.cancelAddonForm.bind(this));
        // $(document).off('click', '.individual-pax-addons').on('click', '.individual-pax-addons', this.showIndividualPaxAddons.bind(this));
        $(document).off('click', '.pax-addons-preview').on('click', '.pax-addons-preview', this.paxAddonsPreview.bind(this));
        $(document).off('click', '.edit-pax-addons').on('click', '.edit-pax-addons', this.editPaxAddons.bind(this));

        $(document).off('hidden.bs.modal.addonCleanup').on('hidden.bs.modal.addonCleanup', () => {
            setTimeout(() => {
                if ($('.modal.show').length === 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css('padding-right', '');
                }
            }, 200);
        });

        $(document).off('click', '.addon-row').on('click', '.addon-row', (e) => {
            const parentId = $(e.currentTarget).data('id');
            if (!parentId) return; // ignore header row
            this.toggleChildren(parentId);
        });

        $(document).off('click', '.nested-row').on('click', '.nested-row', (e) => {
            e.stopPropagation();
            const parentId = $(e.currentTarget).data('id');
            this.toggleChildren(parentId);
        });
    }

    /**
     * Display a list of addons 
     */
    async fetchAddons() {
        this.listAddonsContainer.html(this.renderLoading());

        try {
            const response = await fetch(`${this.fetchBookingAddonsUrl}/${this.bookingId}`);
            const data = await response.json();

            this.userState = data;
            this.addonsList(data);
        } catch (err) {
            console.error("Error loading addons:", err);
            this.onError();
        }
    }

    /**
     * 
     * @param {*} data 
     */
    addonsList(data) {
        if ((!data.addons || data.addons.length === 0) && (!data.pax_addons || data.pax_addons.length === 0)) {
            this.renderFormEditAction();
            return false;
        }

        let addons = [];
        this.fields = data.addons;

        // Sort addon fields 
        data.addons.sort((a, b) => { return (parseInt(a.order) < parseInt(b.order)) ? -1 : 1 });

        if (data.addons) {
            for (let key in data.addons) {
                let f = data.addons[key];
                addons.push(this.renderHtmlField(f));
            }
        }

        if (addons.length > 0) {
            const sym     = (typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$');
            const summary = this._computeAddonsSummary(data.addons, parseFloat(data.retained_fees) || 0);
            const chips   = this._renderAddonsSummaryHeader(summary, sym);
            $("#booking-addons-section-title").html(`Booking Add-ons${chips}`);

            const html = this.renderTable('booking-addons-display',
                addons.join("\n"),
                this.renderHeader(),
                this.renderFooter()
            );
            this.listAddonsContainer.html(html);
        } else {
            // Show empty state with edit button if there are pax addons or addon form exists
            if (data.pax_addons && data.pax_addons.length > 0) {
                const html = this.renderTable('booking-addons-display',
                    "<tr><td class='td-custom-css' colspan='100%'>No Addons</td></tr>",
                    this.renderHeader(),
                    this.renderFooter()
                );
                this.listAddonsContainer.html(html);
            } else {
                this.listAddonsContainer.html("");
            }
        }

        this.paxAddonsList(data);
    }

    countAddonRows(item) {
        let count = 1;
        if (item.children?.length) {
            item.children.forEach(ch => {
                count += this.countAddonRows(ch);
            });
        }
        return count;
    }

    paxAddonsPreview(e) {
        const $target = $(e.currentTarget);
        const hasAddons = $target.data('has-addons');

        if (hasAddons === false || hasAddons === 'false') {
            this.renderAddEditPaxAddonsForm('Add');
            return;
        }

        this.showLoading();

        return $.ajax({
            url: this.getBookingPaxAddonsUrl,
            method: "POST",
            dataType: "json",
            data: {
                booking_id: this.bookingId,
                _token: $('meta[name="csrf-token"]').attr('content')
            }
        })
        .done((response) => {
            this.hideLoading();
            if(response && response.length > 0) {
                tourBookingPaxAddonsOperations.renderPaxAddonsModal(response);
                $(".delete-or-split-pax-addons").addClass('d-none');
            }
        })
        .fail((jqXHR, textStatus, errorThrown) => {
            console.error("Error fetching Pax Addons:", textStatus, errorThrown);
            this.hideLoading();
        });
    }

    editPaxAddons() {
        this.renderAddEditPaxAddonsForm('Edit');
    }

    async renderAddEditPaxAddonsForm(event) {
        this.showLoading();
        this.addPaxAddonsModal = $('#addPaxAddonsModal');
        this.paxAddonsListModal = $('#paxAddonsListModal');

        const formContainer = $('#add-pax-addons-form-container');
        formContainer.html(this.renderLoading());
        
        try {
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            
            const [addonsFormResponse, bookingPaxAddonsResponse] = await Promise.all([
                fetch(this.getAddonsFormUrl, {
                    headers: {
                        'Accept': 'application/json'
                    }
                }),

                fetch(this.getBookingPaxAddonsUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        booking_id: this.bookingId
                    })
                })
            ]);

            // Parse JSON together
            const [addonsFormData, bookingPaxAddonsSelection] = await Promise.all([
                addonsFormResponse.json(),
                bookingPaxAddonsResponse.json()
            ]);

            // ---------- EXISTING LOGIC ----------
            if (!addonsFormData.form || !addonsFormData.form.addons) {
                this.addPaxAddonsModal.modal('hide');
                swal('No Addon Form', 'No addon form is configured for this package.', 'warning');
                return;
            }

            // getPackageAddonForm()'s pax_fields/fields split routes a price_per_pax field
            // flagged display_once into "fields" for NEW-booking checkout rendering only
            // (show once vs once-per-traveler at booking time). Once a booking exists, it's
            // stored exactly like any other price_per_pax field, so this admin edit form
            // needs every price_per_pax field regardless of which bucket it landed in.
            const allAddonFields = [...(addonsFormData.form.addons.pax_fields || []), ...(addonsFormData.form.addons.fields || [])];
            this.addonsFields = allAddonFields.filter(f => String(f.unit_type).toLowerCase() === 'price_per_pax');
            this.formId = addonsFormData.form.id;

            if (this.addonsFields.length === 0) {
                this.addPaxAddonsModal.modal('hide');
                swal('No Pax Addons', 'No per-passenger addons are available for this package.', 'info');
                return;
            }

            // Store selection if needed later
            this.bookingPaxAddonsSelection = bookingPaxAddonsSelection;

            this.hideLoading();
            this.paxAddonsListModal.modal('hide');
            this.addPaxAddonsModal.modal('show');

            formContainer.find('.spinner-border').remove();
            this.initPaxAddonForm();
            $(".pax-addons-event").text(event);
        } catch (err) {
            console.error("Error loading pax addon form:", err);
            this.hideLoading();
            this.addPaxAddonsModal.modal('hide');
            swal('Error', 'Failed to load addon form. Please try again.', 'error');
        }
    }

    paxAddonsList(data) {
        // ============= PAX ADDONS UI ==================
        // Removed: Pax addons are now displayed in the tourists list via the plus button
        // No need to display them here in the booking information section
    }

    // renderPaxAddonRows method removed - pax addons now displayed in tourists list only

    showIndividualPaxAddons(e) {
        const $target = $(e.currentTarget);
        const paxId = $target.data('pax-id');
        const paxIndex = $target.data('pax-index');
        const rateGroupId = $target.data('rate-group-id');
        const hasAddons = $target.data('has-addons');

        if (hasAddons === false || hasAddons === 'false') {
            this.showAddPaxAddonsForm(paxId, paxIndex, rateGroupId);
            return;
        }

        this.showLoading();

        return $.ajax({
            url: this.getBookingPaxAddonsUrl,
            method: "POST",
            dataType: "json",
            data: {
                booking_id: this.bookingId,
                pax_id: paxId,
                pax_index: paxIndex,
                rate_group_id: rateGroupId,
                _token: $('meta[name="csrf-token"]').attr('content')
            }
        })
        .done((response) => {
            this.hideLoading();
            if(response && response.length > 0) {
                tourBookingPaxAddonsOperations.renderPaxAddonsModal(response);
                $(".delete-or-split-pax-addons").addClass('d-none');
            }
        })
        .fail((jqXHR, textStatus, errorThrown) => {
            console.error("Error fetching Pax Addons:", textStatus, errorThrown);
            this.hideLoading();
        });
    }

    async showAddPaxAddonsForm(paxId, paxIndex, rateGroupId) {
        this.currentPaxContext = {
            paxId: paxId,
            paxIndex: paxIndex,
            rateGroupId: rateGroupId
        };

        this.addPaxAddonsModal = $('#addPaxAddonsModal');
        const formContainer = $('#add-pax-addons-form-container');
        
        formContainer.html(this.renderLoading());
        this.addPaxAddonsModal.modal('show');
        
        try {
            const response = await fetch(this.getAddonsFormUrl);
            const data = await response.json();

            if (!data.form || !data.form.addons) {
                this.addPaxAddonsModal.modal('hide');
                swal('No Addon Form', 'No addon form is configured for this package.', 'warning');
                return;
            }

            // See renderAddEditPaxAddonsForm()'s identical fix for why display_once fields
            // (bucketed into "fields" by getPackageAddonForm() for checkout-routing purposes
            // only) must be included here too — once stored, they're ordinary per-pax rows.
            const allAddonFields = [...(data.form.addons.pax_fields || []), ...(data.form.addons.fields || [])];
            this.addonsFields = allAddonFields.filter(f => String(f.unit_type).toLowerCase() === 'price_per_pax');
            this.formId = data.form.id;

            if (this.addonsFields.length === 0) {
                this.addPaxAddonsModal.modal('hide');
                swal('No Pax Addons', 'No per-passenger addons are available for this package.', 'info');
                return;
            }

            this.bookingAddonsState = {
                form_id: this.formId,
                fields: {}
            };

            $('#add-pax-addons-form-container').find('.spinner-border').remove();
            this.initPaxAddonForm();

        } catch (err) {
            console.error("Error loading pax addon form:", err);
            this.addPaxAddonsModal.modal('hide');
            swal('Error', 'Failed to load addon form. Please try again.', 'error');
        }
    }

    generatePaxIndex(length) {
        return Math.random().toString(36).substring(2, 2 + length);
    }

    initPaxAddonForm() {
        if (!this.addonsFields || !this.bookingPaxAddonsSelection) return;

        this.bookingAddonsState = { // Updated add-ons selections
            form_id: this.formId,
            fields: {}
        };

        const container = $('#add-pax-addons-form-container');
        container.empty();

        const rateGroupContainer = $('<div class="rate-group"></div>');

        this.bookingPaxAddonsSelection.forEach(rateGroup => {
            const total = Number(rateGroup.total_tourists) || 0;

            // Map each saved pax to its correct visual slot (0-based), preferring an EXACT
            // person_ordinal match over pure array position. A saved pax whose own ordinal
            // still fits within the CURRENT headcount (0 <= ordinal < total) keeps that exact
            // slot — e.g. only the 2nd of 2 pax has an add-on: slot 0 must stay empty, not
            // silently inherit slot 1's data just because it's the only saved entry (this was
            // the bug — a plain sortedSavedPax[i] positional lookup treated the lone ordinal-1
            // entry as if it belonged in slot 0). Only pax whose ordinal no longer fits at all
            // (an earlier-ordinal sibling was fully removed and the headcount shrank) are
            // "overflow" — those are compacted, in ordinal order, into whichever slots are
            // still unclaimed, so a surviving higher-ordinal pax's add-ons don't just disappear
            // when the count shrinks (the scenario the old positional-only lookup handled).
            const slotAssignment = new Array(total).fill(null);
            const overflowSavedPax = [];
            (rateGroup.pax || [])
                .slice()
                .sort((a, b) => Number(a.person_ordinal) - Number(b.person_ordinal))
                .forEach(pax => {
                    const ordinal = Number(pax.person_ordinal);
                    if (Number.isInteger(ordinal) && ordinal >= 0 && ordinal < total && !slotAssignment[ordinal]) {
                        slotAssignment[ordinal] = pax;
                    } else {
                        overflowSavedPax.push(pax);
                    }
                });
            for (let i = 0; i < total && overflowSavedPax.length; i++) {
                if (!slotAssignment[i]) {
                    slotAssignment[i] = overflowSavedPax.shift();
                }
            }

            for (let i = 0; i < total; i++) {
                const paxNumber = i; // slot index used as person_ordinal on save
                const savedPax = slotAssignment[i] || null;
                let paxUid = savedPax?.pax_index;

                if (!paxUid) {
                    paxUid = `${this.tourSlotId}-${Date.now()}-${this.generatePaxIndex(10)}-${paxNumber}`;
                }

                // ---- Pax wrapper ----
                const paxWrapper = $(`<div class="pax-wrapper border rounded mb-3 bg-white overflow-hidden" data-transact-id="${rateGroup.transact_id}" data-pax-number="${paxNumber}" data-pax-index="${paxUid}"></div>`);

                // Same Paid/Pending distinction the read-only Passenger Add-ons list shows
                // (tour-booking-pax-addons-operations.js's statusBadge, sourced from the same
                // getBookingPaxAddons() response) — this edit form only distinguished "has a
                // saved selection at all" (green/red header) with no payment-status badge,
                // silently dropping the Pending/Paid distinction the moment you opened Edit.
                const chargedTotal = savedPax ? (savedPax.charged_price || 0) + (savedPax.charged_fee || 0) : 0;
                const paxStatusBadge = !savedPax ? '' : (savedPax.has_paid_transaction && chargedTotal > 0)
                    ? `<span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full" style="color:#166534;background:#BBF7D0">Paid</span>`
                    : savedPax.pending_link_item_id
                        ? `<span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full" style="color:#92400E;background:#FEF3C7">Pending</span>`
                        : '';

                paxWrapper.append(`
                    <div class="flex items-center justify-between gap-3 pax-rate-row ${savedPax ? `bg-[#DCFCE7]` : `bg-[#FEF2F2]`} px-3 py-2">
                        <span class="pax-label font-medium ${savedPax ? `text-[#166534]` : `text-[#B91C1C]`}">
                            ${rateGroup.rate_for}
                        </span>
                        ${paxStatusBadge}
                    </div>
                    <div class="border-t border-gray-200 mb-3 -mx-3"></div>
                `);

                // ---- Addon form container ----
                const addonsWrapper = $('<div class="addons-content row px-3"></div>');

                // IMPORTANT: clone schema per pax
                const paxFields = JSON.parse(JSON.stringify(this.addonsFields));

                paxFields.forEach(node => {
                    const col = $('<div class="col-12"></div>');
                    const addonFields = $('<div class="addon-fields pb-3"></div>');
                    const fieldEl = Components.renderField(node, paxUid);

                    addonFields.append(fieldEl);
                    col.append(addonFields);
                    addonsWrapper.append(col);
                });

                paxWrapper.append(addonsWrapper);
                rateGroupContainer.append(paxWrapper);

                // ---- Attach logic ----
                Components.attachLogicToContainer(addonsWrapper[0], paxFields);
            }
        });

        container.append(rateGroupContainer);

        requestAnimationFrame(() => {
            this.bookingPaxAddonsSelection.forEach(rateGroup => {
                rateGroup.pax?.forEach(pax => {
                    if (pax.tree?.length && pax.pax_index) {
                        this.bookingAddonsState = {
                            form_id: this.formId,
                            fields: pax.tree
                        };
                        this.prefillSelections(pax.pax_index);
                    }
                });
            });

            container.find('.addons-content').each(function () {
                this._logicRunAll?.();
            });
        });

        // Save handler (global)
        $(document)
            .off('click', '.save-pax-addons')
            .on('click', '.save-pax-addons', this.saveBulkPaxAddons.bind(this));
        // $(document)
        //     .off('click', '.save-pax-addons')
        //     .on('click', '.save-pax-addons', this.savePaxAddons.bind(this));


        /*const addonsWrapper = $('<div class="addons-content row"></div>');

        this.addonsFields.forEach(node => {
            const col = $('<div class="col-12"></div>');
            const addonFields = $('<div class="addon-fields pb-3"></div>');
            const fieldEl = Components.renderField(node);

            addonFields.append(fieldEl);
            col.append(addonFields);
            addonsWrapper.append(col);
        });

        container.append(addonsWrapper);
        Components.attachLogicToContainer(addonsWrapper[0], this.addonsFields);
        $(document).off('click', '.save-pax-addons').on('click', '.save-pax-addons', this.savePaxAddons.bind(this));

        setTimeout(() => {
            if (addonsWrapper[0]._logicRunAll) {
                addonsWrapper[0]._logicRunAll();
            }
        }, 20);*/
    }

    async saveBulkPaxAddons(e) {
        e.preventDefault();

        if (this.isCancelled) {
            swal('Booking Cancelled', 'This booking has been cancelled — add-ons can no longer be added or updated.', 'error');
            return;
        }

        const paxDiffs = this.diffBulkPaxSelections();

        if (!paxDiffs.length) {
            swal('No Changes', 'No pax add-ons were modified.', 'info');
            return;
        }

        const { isValid, hasRequired } = this.addonValidation();
        if (hasRequired && !isValid) {
            swal('Required Fields', 'Please complete all required add-ons for each passenger.', 'warning');
            return;
        }

        const savedFlatByPax = this._buildSavedFlatByPax();
        const payload        = this.buildBulkAddonPayload(paxDiffs, savedFlatByPax);
        const { action }     = payload.financial;

        if (action === 'none') {
            await this._submitBulkPaxAddonsSave(payload);
            return;
        }

        // Hide pax modal before showing charge/refund modal
        this.addPaxAddonsModal.modal('hide');

        if (action === 'charge') {
            this._showBulkChargeModal(payload);
        } else {
            this._showBulkRefundModal(payload);
        }
    }

    /** Build per-pax flat state lookup (pax_index → flattenedNodes) from loaded DB data */
    _buildSavedFlatByPax() {
        const map = new Map();
        (this.bookingPaxAddonsSelection || []).forEach(group => {
            (group.pax || []).forEach(pax => {
                const saved = this.pruneSelected(this.normalizeSavedNodes(pax.tree || []));
                map.set(pax.pax_index, this.flattenNodes(saved));
            });
        });
        return map;
    }

    /**
     * Build the financial payload for bulk pax changes.
     * Computes to_charge / to_refund / net across ALL pax diffs in one pass.
     */
    buildBulkAddonPayload(paxDiffs, savedFlatByPax) {
        const toCharge = [];
        const toRefund = [];

        for (const pax of paxDiffs) {
            const oldFlat = savedFlatByPax.get(pax.pax_index) || {};

            for (const item of (pax.added || [])) {
                const bd = this._computePriceBreakdown(item.field_id, item.option_id, item.unit);
                if (bd.total > 0) {
                    toCharge.push({
                        field_id:             item.field_id,
                        pax_index:            pax.pax_index,
                        label:                this._buildAddonLabel(item.field_id, item.option_label),
                        option_id:            item.option_id || null,
                        unit:                 item.unit || 1,
                        new_price:            bd.total,
                        old_db_id:            null,
                        old_paid:             0,
                        charge_amount:        bd.total,
                        field_price:          bd.fieldPrice,
                        option_price:         bd.optionPrice,
                        field_additional_fee: bd.fieldAdditionalFee,
                        option_additional_fee: bd.optionAdditionalFee,
                        reason:               'added'
                    });
                }
            }

            for (const item of (pax.updated || [])) {
                const bd      = this._computePriceBreakdown(item.field_id, item.option_id, item.unit);
                const oldSaved = oldFlat[item.field_id];
                const oldPaid  = oldSaved?.paid_price ?? 0;
                // See the identical fix/comment in buildAddonPayload() (single-edit) — direction
                // must come from comparing NOMINAL prices (old selection vs new selection), not
                // from comparing the new price against oldPaid, which is always 0 for a Pay
                // Later / never-charged field and made every change look like an upgrade.
                const oldBd = this._computePriceBreakdown(item.field_id, oldSaved?.option_id, oldSaved?.unit);

                if (bd.total > oldBd.total + 0.001) {
                    const chargeAmount = parseFloat((bd.total - oldPaid).toFixed(2));
                    if (chargeAmount > 0.001) {
                        toCharge.push({
                            field_id:             item.field_id,
                            pax_index:            pax.pax_index,
                            label:                this._buildAddonChangeLabel(item.field_id, oldSaved?.option_id, oldSaved?.value, item.option_label, item.value),
                            option_id:            item.option_id || null,
                            unit:                 item.unit || 1,
                            new_price:            bd.total,
                            old_db_id:            item.db_id,
                            old_paid:             oldPaid,
                            charge_amount:        chargeAmount,
                            field_price:          bd.fieldPrice,
                            option_price:         bd.optionPrice,
                            field_additional_fee: bd.fieldAdditionalFee,
                            option_additional_fee: bd.optionAdditionalFee,
                            reason:               'upgraded'
                        });
                    }
                } else if (bd.total < oldBd.total - 0.001) {
                    toRefund.push({
                        db_id:         item.db_id,
                        field_id:      item.field_id,
                        label:         this._buildAddonChangeLabel(item.field_id, oldSaved?.option_id, oldSaved?.value, item.option_label, item.value),
                        refund_amount: parseFloat(Math.max(0, oldPaid - bd.total).toFixed(2)),
                        credit_amount: parseFloat((oldBd.total - bd.total).toFixed(2)),
                        reason:        'downgraded'
                    });
                }
            }

            for (const item of (pax.deleted || [])) {
                const savedItem = oldFlat[item.field_id];
                const oldPaid   = savedItem?.paid_price ?? 0;
                // Pay Later (and any other not-yet-invoiced) add-ons are stored with a
                // transaction status of 'open', never 'paid' — so paid_price is legitimately
                // $0 even though the item genuinely costs something and is about to stop being
                // owed. Falling back to the item's own computed price (same pricing engine used
                // for "added" items above) lets this removal correctly offset a same-cost
                // addition elsewhere — e.g. reassigning a Photography Package from one pax to
                // another nets to $0 either way, but without this fallback a Pay Later booking
                // wrongly saw it as a fresh $15 charge (nothing to net against) and popped the
                // "Payment Required" modal for a change that doesn't actually add anything owed.
                // creditAmount is ONLY used below to decide whether ANY charge/refund flow is
                // needed at all — refund_amount (real money actually collected) is untouched, so
                // the backend never tries to refund money that was never paid.
                const creditAmount = oldPaid > 0
                    ? oldPaid
                    : this._computePriceBreakdown(item.field_id, savedItem?.option_id, savedItem?.unit).total;
                if (creditAmount > 0) {
                    toRefund.push({
                        db_id:         item.db_id,
                        field_id:      item.field_id,
                        label:         this._buildAddonLabel(item.field_id, savedItem?.option_label),
                        refund_amount: oldPaid,
                        credit_amount: creditAmount,
                        reason:        'removed'
                    });
                }
            }
        }

        const totalCharge = parseFloat(toCharge.reduce((s, i) => s + i.charge_amount, 0).toFixed(2));
        // Net/action must be decided on billable credit (credit_amount — falls back to
        // refund_amount for 'downgraded' items, which don't set it), not on refund_amount alone
        // — a Pay Later item's refund_amount is legitimately $0 pre-payment even though removing
        // it still offsets a same-cost addition elsewhere (see the 'deleted' loop above).
        // total_refund itself stays refund_amount-based (real money) — the backend never reads
        // it, but keep it meaning what its name says.
        const totalRefund = parseFloat(toRefund.reduce((s, i) => s + i.refund_amount, 0).toFixed(2));
        const totalCredit = parseFloat(toRefund.reduce((s, i) => s + (i.credit_amount ?? i.refund_amount), 0).toFixed(2));
        const netRaw      = parseFloat((totalCharge - totalCredit).toFixed(2));

        let action = 'none';
        if (netRaw > 0.001)       action = 'charge';
        else if (netRaw < -0.001) action = 'refund';

        return {
            form_id:    this.formId,
            booking_id: this.bookingId,
            pax:        paxDiffs,
            financial: {
                action,
                net_amount:   parseFloat(Math.abs(netRaw).toFixed(2)),
                total_charge: totalCharge,
                total_refund: totalRefund,
                to_charge:    toCharge,
                to_refund:    toRefund
            }
        };
    }

    /** No-financial path: post directly to save-pax-addons */
    async _submitBulkPaxAddonsSave(payload) {
        try {
            this.showLoading();
            const resp = await fetch(`${this.admin}/booking/save-pax-addons`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body:    JSON.stringify(payload)
            });
            const data = await resp.json();
            this.hideLoading();
            if (data.success) {
                this.addPaxAddonsModal.modal('hide');
                swal('Success', 'Passenger add-ons saved successfully.', 'success').then(() => location.reload());
            } else {
                throw new Error(data.message);
            }
        } catch (err) {
            this.hideLoading();
            console.error(err);
            swal('Error', 'Failed to save passenger add-ons.', 'error');
        }
    }

    /** Show charge modal wired to the bulk pax endpoint */
    _showBulkChargeModal(payload) {
        this._showChargeModal(payload, (p, via) => {
            $('#modal_addon_edit_charge').modal('hide');
            if (via === 'payment_link') {
                this._submitAddonPaymentLink(p);
            } else if (via === 'Card') {
                this._initBulkPaxCardPayment(p);
            } else {
                this._submitBulkPaxAddonsUpdate(p, via);
            }
        });
    }

    /** Show refund modal wired to the bulk pax endpoint */
    _showBulkRefundModal(payload) {
        this._showRefundModal(payload, (p, refundType, note) => {
            this._submitBulkPaxAddonsUpdate({
                ...p,
                financial: { ...p.financial, payment_method: this.bookingPaymentVia, refund_type: refundType, refund_note: note }
            });
        });
    }

    /** Card payment init for bulk pax charge — same Stripe flow, different feature flag */
    _initBulkPaxCardPayment(payload) {
        const form = document.createElement('form');
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="feature"    value="bulk_pax_addon_charge">
            <input type="hidden" name="bookingId"  value="${payload.booking_id}">
            <input type="hidden" name="net_amount" value="${payload.financial.net_amount}">
            <input type="hidden" name="formId"     value="${payload.form_id}">
        `;
        // Itemized breakdown so the backend can fee each addon individually (matching how the
        // ledger will round and store it) instead of re-deriving the fee from net_amount alone,
        // which can land a cent away whenever the per-item roundings and the aggregate rounding
        // fall on different sides of a rounding boundary.
        const financialItemsInput = document.createElement('input');
        financialItemsInput.type = 'hidden';
        financialItemsInput.name = 'financial_items';
        financialItemsInput.value = JSON.stringify({
            to_charge: payload.financial.to_charge,
            to_refund: payload.financial.to_refund
        });
        form.appendChild(financialItemsInput);
        document.body.appendChild(form);
        this._pendingBulkPayload = payload;
        this._addonEditCardForm  = form;
        // See _initAddonEditCardPayment() — same reason the Card Reader tab needs its
        // total/fees set explicitly here, otherwise it falls back to the full booking total.
        // Include any pending payment link amount so the card reader charges the combined total.
        const bulkPendingAmount = parseFloat($('#modal_addon_edit_charge').data('pending-amount')) || 0;
        const bulkCombinedAmount = parseFloat((payload.financial.total_to_charge + bulkPendingAmount - (payload.financial.pending_superseded_amount || 0)).toFixed(2));
        backendCardReaderInfo.getTotal(bulkCombinedAmount);
        backendCardReaderInfo.getFees(payload.financial.fee_amount);
        this._pendingCardReaderFeature = 'bulk_pax_addon_charge';
        initTourBookingStripePayment.initializeStripe(form, 'bulk_pax_addon_charge');
    }

    /** Final POST to update-pax-addons with financial payload */
    async _submitBulkPaxAddonsUpdate(payload, paymentMethod = null, paymentIntentId = null) {
        const enriched = {
            ...payload,
            financial: {
                ...payload.financial,
                payment_method:    paymentMethod || this.bookingPaymentVia,
                payment_intent_id: paymentIntentId
            }
        };
        try {
            this.showLoading();
            const resp = await fetch(this.updateBulkPaxAddonsUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                body:    JSON.stringify(enriched)
            });
            const data = await resp.json();
            this.hideLoading();
            if (data.success) {
                swal('Success', 'Passenger add-ons updated successfully.', 'success').then(() => location.reload());
            } else {
                swal('Error', data.message || 'Something went wrong.', 'error');
            }
        } catch (err) {
            this.hideLoading();
            console.error('Bulk pax addon update error:', err);
            swal('Error', 'Failed to update passenger add-ons.', 'error');
        }
    }

    diffBulkPaxSelections() {
        const result = [];
        const paxStates = this.getBulkPaxAddonsState();

        paxStates.forEach(pax => {
            // const savedEntry = this.bookingPaxAddonsSelection
            //     ?.find(p => p.pax_index === pax.pax_index);

            const savedPaxMap = this.getSavedPaxMap();
            const savedEntry = savedPaxMap.get(pax.pax_index);
            const savedTree = savedEntry?.tree || [];

            const saved = this.pruneSelected(
                this.normalizeSavedNodes(savedTree)
            );
            const current = this.pruneSelected(pax.addons);
            const oldFlat = this.flattenNodes(saved);
            const newFlat = this.flattenNodes(current);
            
            // Pax already existed AND nothing changed → skip completely
            if (savedEntry && this.isSameFlatState(oldFlat, newFlat)) {
                return;
            }

            // Pax never existed AND still has no addons → skip
            if (!savedEntry && Object.keys(newFlat).length === 0) {
                return;
            }

            const added = [];
            const updated = [];
            const deleted = [];

            for (const fid in newFlat) {
                const cur = newFlat[fid];
                const old = oldFlat[fid];

                if (!old) {
                    added.push(cur);
                } else {
                    let changed = false;

                    switch (cur.type) {
                        case "radio":
                        case "dropdown":
                        case "select":
                            changed = cur.option_id !== old.option_id;
                            break;
                        case "checkbox":
                            changed = cur.value !== old.value;
                            break;
                        default:
                            changed = (cur.value ?? "") !== (old.value ?? "");
                    }

                    if (changed) {
                        updated.push({
                            ...cur,
                            db_id: old.db_id
                        });
                    }
                }
            }

            for (const fid in oldFlat) {
                if (!newFlat[fid]) {
                    deleted.push({
                        db_id: oldFlat[fid].db_id,
                        field_id: oldFlat[fid].field_id
                    });
                }
            }

            if (added.length || updated.length || deleted.length) {
                result.push({
                    pax_id: pax.pax_id,
                    person_ordinal: pax.person_ordinal,
                    pax_index: pax.pax_index,
                    added,
                    updated,
                    deleted
                });
            }
        });

        return result;
    }

    getSavedPaxMap() {
        const map = new Map();

        (this.bookingPaxAddonsSelection || []).forEach(group => {
            group.pax?.forEach(pax => {
                map.set(pax.pax_index, pax);
            });
        });

        return map;
    }

    isSameFlatState(oldFlat, newFlat) {
        const oldKeys = Object.keys(oldFlat);
        const newKeys = Object.keys(newFlat);

        if (oldKeys.length !== newKeys.length) return false;

        return oldKeys.every(fid => {
            const a = oldFlat[fid];
            const b = newFlat[fid];
            if (!b) return false;

            // ONLY compare semantic fields
            return (
                a.field_id === b.field_id &&
                a.type === b.type &&
                (a.value ?? "") === (b.value ?? "") &&
                (a.option_id ?? null) === (b.option_id ?? null)
            );
        });
    }

    getBulkPaxAddonsState() {
        const paxStates = [];

        $('#add-pax-addons-form-container')
            .find('.pax-wrapper')
            .each((_, paxEl) => {
                const paxUid = paxEl.dataset.paxIndex;
                const transactId = paxEl.dataset.transactId; // pax_id in `tour_booking_addon_fields` table
                const paxNumber = paxEl.dataset.paxNumber;

                const tree = this._buildAddonStateFromContainer(
                    paxEl,
                    this.addonsFields
                );

                paxStates.push({
                    pax_id: transactId,
                    person_ordinal: paxNumber,
                    pax_index: paxUid,
                    addons: tree
                });
            });

        return paxStates;
    }

    _buildAddonStateFromContainer(containerEl, addonsFields) {
        const read = (node, parentId = null) => {
            const card = containerEl.querySelector(
                `.addon-card[data-node-id="${node.id}"]`
            );

            const out = {
                db_id: null,
                field_id: node.id,
                parent_id: parentId,
                type: node.type,
                value: null,
                option_id: null,
                option_label: null,
                unit_type: node.unit_type ?? null,
                unit: null,
                rule_id: null,
                applied_rule_snapshot: null,
                children: []
            };

            if (card) {
                const t = node.type;

                if (t === "checkbox") {
                    out.value = card.querySelector('input[type="checkbox"]').checked
                        ? "checked"
                        : "not_checked";
                }
                else if (t === "radio") {
                    const r = card.querySelector("input[type=radio]:checked");
                    if (r) {
                        out.value = r.value;
                        out.option_id = r.dataset.optionId ? Number(r.dataset.optionId) : null;
                        out.option_label = r.dataset.label || null;
                    }
                }
                else if (t === "dropdown" || t === "select") {
                    const sel = card.querySelector("select");
                    if (sel) {
                        const opt = sel.selectedOptions[0];
                        out.value = sel.value;
                        out.option_id = opt?.dataset.optionId ? Number(opt.dataset.optionId) : null;
                        out.option_label = opt?.dataset.label || null;
                    }
                }
                else if (t === "number") {
                    out.value = card.querySelector("input[type=number]")?.value ?? null;
                    if (out.unit_type === "price_per_unit" && out.value) {
                        out.unit = Number(out.value);
                    }
                }
                else if (t === "text") {
                    out.value = card.querySelector("input[type=text]")?.value ?? null;
                }
                else if (t === "textbox") {
                    out.value = card.querySelector("textarea")?.value ?? null;
                }

                // RULE LOGIC (unchanged)
                if (node.rules?.length) {
                    const rule = node.rules.find(rule => {
                        if (rule.option_id && rule.option_id == out.option_id) return true;
                        if (rule.operator === 'checked' && out.value === 'checked') return true;
                        if (rule.operator === 'equals' && out.value == rule.value) return true;
                        return false;
                    });

                    if (rule) {
                        out.rule_id = rule.id;
                        // Store as object - Laravel will cast it to JSON automatically
                        out.applied_rule_snapshot = {
                            value: rule.value,
                            operator: rule.operator,
                            option_id: rule.option_id
                        };
                    }
                }
            }

            if (node.children?.length) {
                for (const ch of node.children) {
                    const childCard = containerEl.querySelector(
                        `.addon-card[data-node-id="${ch.id}"]`
                    );

                    if (!childCard || childCard.closest(".indent")?.style.display === "none") {
                        continue;
                    }

                    const childNode = read(ch, node.id);
                    out.children.push(childNode);
                }
            }

            if (!out.children.length) {
                out.rule_id = null;
                out.applied_rule_snapshot = null;
            }

            return out;
        };

        return addonsFields.map(root => read(root));
    }

    async savePaxAddons(e) {
        e.preventDefault();

        if (this.isCancelled) {
            swal('Booking Cancelled', 'This booking has been cancelled — add-ons can no longer be added or updated.', 'error');
            return;
        }

        if (!this.currentPaxContext) {
            swal('Error', 'Pax context is missing. Please try again.', 'error');
            return;
        }

        const formData = this.getPaxAddonsFormData();
        
        const container = $('#add-pax-addons-form-container .addons-content')[0];
        let isValid = true;
        let hasRequired = false;
        
        if (container) {
            this.addonsFields.forEach(f => {
                const result = Components.validateContainer(container, f);
                if (!result.isValid) isValid = false;
                if (result.hasRequired) hasRequired = true;
            });
        }
        
        if (hasRequired && !isValid) {
            swal('Required Fields', 'Please complete all required add-on fields before saving.', 'warning');
            return;
        }

        const payload = {
            form_id: this.formId,
            booking_id: this.bookingId,
            pax_id: this.currentPaxContext.paxId,
            pax_index: this.currentPaxContext.paxIndex,
            rate_group_id: this.currentPaxContext.rateGroupId,
            addons: this.pruneSelected(formData)
        };

        try {
            this.showLoading();
            
            const resp = await fetch(`${this.admin}/booking/save-pax-addons`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(payload)
            });

            const data = await resp.json();

            if (data.success) {
                this.hideLoading();
                this.addPaxAddonsModal.modal('hide');
                swal('Success', 'Passenger add-ons saved successfully. Page will reload.', 'success')
                    .then(function () {
                        location.reload();
                    });
            } else {
                this.hideLoading();
                swal('Error', data.message || 'Failed to save pax addons.', 'error');
            }
        } catch (err) {
            this.hideLoading();
            console.error("Error saving pax addons:", err);
            swal('Error', 'Failed to save pax addons. Please try again.', 'error');
        }
    }

    getPaxAddonsFormData() {
        const container = $('#add-pax-addons-form-container')[0];
        if (!container) return [];

        const read = (node, parentId = null) => {
            const card = container.querySelector(`.addon-card[data-node-id="${node.id}"]`);

            const out = {
                db_id: null,
                field_id: node.id,
                parent_id: parentId,
                type: node.type,
                value: null,
                option_id: null,
                option_label: null,
                unit_type: node.unit_type ?? 'price_per_pax',
                rule_id: node.rule_id ?? null,
                applied_rule_snapshot: null,
                children: []
            };

            if (card) {
                const t = node.type;

                if (t === "checkbox") {
                    out.value = card.querySelector('input[type="checkbox"]').checked
                        ? "checked"
                        : "not_checked";
                }
                else if (t === "radio") {
                    const r = card.querySelector("input[type=radio]:checked");
                    if (r) {
                        out.value = r.value;
                        out.option_id = r.dataset.optionId ? Number(r.dataset.optionId) : null;
                        out.option_label = r.dataset.label || null;
                    }
                }
                else if (t === "dropdown" || t === "select") {
                    const sel = card.querySelector("select");
                    if (sel) {
                        const opt = sel.selectedOptions[0];
                        out.value = sel.value;
                        out.option_id = opt?.dataset.optionId ? Number(opt.dataset.optionId) : null;
                        out.option_label = opt?.dataset.label || null;
                    }
                }
                else if (t === "number") {
                    out.value = card.querySelector("input[type=number]")?.value ?? null;
                }
                else if (t === "text") {
                    out.value = card.querySelector("input[type=text]")?.value ?? null;
                }
                else if (t === "textbox") {
                    out.value = card.querySelector("textarea")?.value ?? null;
                }

                // Rule matching logic
                if (node.rules?.length) {
                    const rule = node.rules.find(rule => {
                        if (rule.operator === "equals") {
                            return (
                                (rule.option_id && rule.option_id == out.option_id) ||
                                (rule.value && rule.value == out.value)
                            );
                        }
                        return false;
                    });

                    if (rule) {
                        out.rule_id = rule.id;
                        // Store as object - Laravel will cast it to JSON automatically
                        out.applied_rule_snapshot = {
                            value: rule.value,
                            operator: rule.operator,
                            option_id: rule.option_id
                        };
                    }
                }
            }

            if (node.children?.length) {
                out.children = [];
                for (const ch of node.children) {
                    const childCard = container.querySelector(`.addon-card[data-node-id="${ch.id}"]`);
                    if (!childCard || childCard.closest(".indent")?.style.display === "none") {
                        continue;
                    }
                    const childNode = read(ch, node.id);
                    // Children inherit rule_id but NOT applied_rule_snapshot
                    if (out.rule_id) {
                        childNode.rule_id = out.rule_id;
                    }
                    out.children.push(childNode);
                }
            }

            return out;
        };

        return this.addonsFields.map(root => read(root));
    }

    cancelPaxAddonForm() {
        this.currentPaxContext = null;
        if (this.addPaxAddonsModal) {
            this.addPaxAddonsModal.modal('hide');
        }
    }

    async renderFormEditAction() {
        this.listAddonsContainer.html("");

        try {
            const response = await fetch(this.getAddonsFormUrl);
            const resp = await response.json();

            if (resp.form !== null && resp.form.status === 'Active') {
                $("#booking-addons-section-title").html("Booking Add-Ons:");
                this.formId = resp.form.id;

                const html = this.renderTable(
                    'booking-addons-display',
                    "<tr><td class='td-custom-css' colspan='100%'>No Addons</td></tr>",
                    "",
                    this.renderFooter()
                );

                this.listAddonsContainer.html(html);
            }
        } catch (err) {
            console.error("Error loading empty addons form:", err);
        }
    }

    async onEditForm() {
        this.listAddonsContainer.hide();
        this.addonsFormContainer.html(this.renderLoading());
        
        await this.fetchAddonsForm();   // <-- wait here!

        this.addonsFormContainer.find('.spinner-border').remove();

        if (this.addonsFields && this.addonsFields.length) {
            this.initAddonForm();
        }
    }

    async fetchAddonsForm() {
        try {
            // Fetch form fields
            const response = await fetch(this.getAddonsFormUrl);
            const data = await response.json();

            // data.form.addons.fields is the checkout-routing "shown once" bucket, which
            // also includes price_per_pax fields flagged display_once (see
            // AddonFormRepository::getPackageAddonForm()) — that bucketing is only
            // meaningful for NEW-booking checkout rendering. This "Booking Add-ons" edit
            // form is for existing-booking one_time/price_per_unit fields only; every
            // price_per_pax field (display_once or not) is edited via the separate
            // "Edit Passenger Add-ons" modal (renderAddEditPaxAddonsForm()) instead.
            this.addonsFields = (data.form?.addons?.fields || []).filter(f => String(f.unit_type).toLowerCase() !== 'price_per_pax');

            // Fetch saved user selections (from same booking)
            const userData = this.userState;
            this.bookingAddonsState = userData.success ? ({ form_id: data.form?.id, fields: userData.addons } || []) : [];

            // All data is ready → init UI
            if (this.addonsFields.length) {
                this.initAddonForm();
            }

        } catch (err) {
            console.error("Error loading addons form & selections:", err);
            this.addonsFields = [];
            this.bookingAddonsState = { // Updated add-ons selections
                form_id: 0,
                fields: {}
            };
        }
    }

    toggleChildren(parentId) {
        const directChildren = $(`tr[data-parent="${parentId}"]`);
        if (!directChildren.length) return;

        const shouldShow = directChildren.first().is(':hidden');
        const arrow = $(`tr[data-id="${parentId}"] .addon-arrow`);

        if (shouldShow) {
            // Show direct children only
            directChildren.show();
            arrow.text("▾"); // open state
        } else {
            // Hide ALL descendants recursively
            this.hideDeep(parentId);
            arrow.text("▸"); // closed state
        }
    }

    hideDeep(id) {
        const children = $(`tr[data-parent="${id}"]`);

        children.each((_, el) => {
            const $child = $(el);
            const childId = $child.data("id");

            $(`tr[data-id="${childId}"] .addon-arrow`).text("▸");

            // Hide deeper children recursively
            this.hideDeep(childId);

            // Hide this child
            $child.hide();

        });
    }

    initAddonForm() {
        if (!this.addonsFields) return;

        const container = $('#booking-addons-form-section');
        container.empty();

        const form = $('<form id="booking-addons-form" class="booking-addons-form"></form>');
        const addonsWrapper = $('<div class="addons-content row"></div>');

        this.addonsFields.forEach(node => {
            const col = $('<div class="col-12 col-md-6"></div>');
            const addonFields = $('<div class="addon-fields pb-2"></div>');
            const fieldEl = Components.renderField(node);
            addonFields.append(fieldEl);
            col.append(addonFields);
            addonsWrapper.append(col);
        });

        form.append(addonsWrapper);
        form.append(this._buildPriceSummaryPanel());

        const footer = $(`
            <div class="addons-form-footer border-top pt-3 mt-3 d-flex justify-content-end gap-2">
                <button class="btn btn-primary-small btn-sm update-addons">Update Addons</button>
                <button class="btn btn-outline-secondary btn-sm cancel-addons btn-light-small">Cancel</button>
            </div>
        `);
        form.append(footer);

        container.append(form);

        Components.attachLogicToContainer(addonsWrapper[0], this.addonsFields);
        $('#booking-addons-section-title').text('Booking Add-ons:');

        this.prefillSelections();

        // Wire live price summary to any input/change inside the form
        addonsWrapper[0].addEventListener('input',  () => this._updatePriceSummary(container[0]));
        addonsWrapper[0].addEventListener('change', () => this._updatePriceSummary(container[0]));

        setTimeout(() => {
            if (addonsWrapper[0]._logicRunAll) {
                addonsWrapper[0]._logicRunAll();
            }
            this._updatePriceSummary(container[0]);
            // Apply visual highlight to any pre-selected radio options
            container[0].querySelectorAll('input[type="radio"]:checked').forEach(r => {
                const opt = r.closest('.addon-radio-option');
                if (opt) { opt.style.background = '#EFF6FF'; opt.style.borderColor = '#3B82F6'; }
            });
            // Apply dropdown price hints for any pre-selected options
            container[0].querySelectorAll('select').forEach(sel => {
                if (!sel.value) return;
                const o = sel.selectedOptions[0];
                const hint = sel.closest('.addon-card')?.querySelector('.dropdown-price-hint');
                if (!hint) return;
                const price = parseFloat(o?.dataset?.fee) || 0;
                const hasFee = o?.dataset?.optionAdditionalFee === '1';
                const sym = typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$';
                if (hasFee && price > 0) {
                    hint.innerHTML = `<span style="display:inline-flex;align-items:center;gap:5px;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:20px;padding:2px 10px;font-size:0.73rem;font-weight:600">+${sym}${price.toFixed(2)} fee</span>`;
                    hint.style.display = 'block';
                }
            });
        }, 50);
    }

    _buildPriceSummaryPanel() {
        return `
            <div id="addon-price-summary-wrapper" style="margin-top:16px;border:1px solid #E5E7EB;border-radius:10px;overflow:hidden">
                <div style="display:flex;align-items:center;gap:6px;padding:10px 14px;background:#F9FAFB;border-bottom:1px solid #E5E7EB">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2.5"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    <span style="font-size:0.7rem;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:0.07em">Price Summary</span>
                </div>
                <div id="addon-price-summary-body" style="padding:10px 14px">
                    <div style="font-size:0.82rem;color:#9CA3AF;text-align:center;padding:6px 0">No priced items selected yet.</div>
                </div>
            </div>
        `;
    }

    _computeLivePricing(container) {
        const items = [];

        // Determine if fees apply for the current payment method. A booking with a real, active
        // pending Payment Link (bookingPaymentVia === 'Pay Later' by default, same as a genuine
        // never-charged booking) still gets charged through Stripe like a Card booking once paid
        // — see the matching fix in _showChargeModal() for why via alone isn't enough here.
        const applyFee = this.bookingPaymentVia === 'Card'
            || (this.bookingPaymentVia === 'Cash' && this.isCashFees);
        const feePct = applyFee ? (this.natFeePct || 0) : 0;

        // Fee is rounded on the item's TOTAL price (unit_price × qty), matching how the
        // backend stores/charges it — rounding per-unit first and then multiplying by qty
        // can differ by a cent (e.g. $3.00 × 5 vs $15.00 at the same fee %).
        const calcFee = (unitPrice, qty = 1) => {
            if (feePct <= 0) return null;
            const totalPrice = Math.round(unitPrice * qty * 100) / 100;
            return Math.round(totalPrice * feePct / 100 * 100) / 100;
        };

        const processField = (node, scope) => {
            const card = (scope || container).querySelector(`.addon-card[data-node-id="${node.id}"]`);
            if (!card) return;

            const additionalFee = parseInt(card.dataset.additionalFee) === 1;
            const fieldPrice    = parseFloat(card.dataset.fee) || 0;

            // Radio and dropdown carry pricing per-option, independent of the field-level additionalFee flag
            if (node.type === 'radio') {
                const sel = card.querySelector('input[type="radio"]:checked');
                if (sel) {
                    const optPrice = parseFloat(sel.dataset.fee) || 0;
                    const optFee   = sel.dataset.optionAdditionalFee === '1';
                    if (optFee && optPrice > 0) {
                        items.push({ label: node.label, optionLabel: sel.dataset.label || '', price: optPrice, qty: 1, total: optPrice, unitType: node.unit_type, fee: calcFee(optPrice) });
                    }
                }
            } else if (node.type === 'dropdown') {
                const sel = card.querySelector('select');
                const opt = sel?.selectedOptions[0];
                if (opt && sel.value) {
                    const optPrice = parseFloat(opt.dataset.fee) || 0;
                    const optFee   = opt.dataset.optionAdditionalFee === '1';
                    if (optFee && optPrice > 0) {
                        items.push({ label: node.label, optionLabel: opt.dataset.label || '', price: optPrice, qty: 1, total: optPrice, unitType: node.unit_type, fee: calcFee(optPrice) });
                    }
                }
            } else if (additionalFee) {
                if (node.type === 'checkbox') {
                    const chk = card.querySelector('input[type="checkbox"]');
                    if (chk?.checked && fieldPrice > 0) {
                        items.push({ label: node.label, optionLabel: null, price: fieldPrice, qty: 1, total: fieldPrice, unitType: node.unit_type, fee: calcFee(fieldPrice) });
                    }
                } else if (node.type === 'number' && node.unit_type === 'price_per_unit') {
                    const input = card.querySelector('input[type="number"]');
                    const qty   = parseFloat(input?.value) || 0;
                    if (qty > 0 && fieldPrice > 0) {
                        items.push({ label: node.label, optionLabel: null, price: fieldPrice, qty, total: fieldPrice * qty, unitType: node.unit_type, fee: calcFee(fieldPrice, qty) });
                    }
                } else if (['text', 'textbox', 'number'].includes(node.type) && node.unit_type !== 'price_per_unit') {
                    const input = card.querySelector('input, textarea');
                    if (input?.value?.trim() && fieldPrice > 0) {
                        items.push({ label: node.label, optionLabel: null, price: fieldPrice, qty: 1, total: fieldPrice, unitType: node.unit_type, fee: calcFee(fieldPrice) });
                    }
                }
            }

            // Recurse into visible rule children
            (node.rules || []).forEach((r, idx) => {
                const ruleWrap = card.querySelector(`.indent[data-rule-index="${idx}"]`);
                if (!ruleWrap || ruleWrap.style.display === 'none') return;
                (r.children || []).forEach(ch => processField(ch, card));
            });
        };

        (this.addonsFields || []).forEach(f => processField(f, null));
        return items;
    }

    _updatePriceSummary(container) {
        const body = container?.querySelector('#addon-price-summary-body');
        if (!body) return;

        const items = this._computeLivePricing(container);
        const sym   = typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$';

        if (!items.length) {
            body.innerHTML = `<div style="font-size:0.82rem;color:#9CA3AF;text-align:center;padding:6px 0">No priced items selected yet.</div>`;
            return;
        }

        const addonsCost = items.reduce((s, i) => s + i.total, 0);
        const totalFee   = items.reduce((s, i) => s + (i.fee || 0), 0);
        const grandTotal = addonsCost + totalFee;
        const hasFees    = totalFee > 0;

        let rows = '';
        items.forEach(item => {
            const namePart = item.optionLabel
                ? `${item.label} <span style="color:#6B7280;font-size:0.75rem">(${item.optionLabel})</span>`
                : item.label;
            const calcPart = item.unitType === 'price_per_unit' && item.qty > 1
                ? ` <span style="color:#9CA3AF;font-size:0.75rem">${sym}${item.price.toFixed(2)} × ${item.qty}</span>`
                : '';
            const feeCell = hasFees
                ? `<span style="font-size:0.78rem;color:#9CA3AF;white-space:nowrap;min-width:48px;text-align:right">${item.fee > 0 ? sym + item.fee.toFixed(2) : '—'}</span>`
                : '';
            rows += `
                <div style="display:flex;justify-content:space-between;align-items:baseline;padding:5px 0;border-bottom:1px solid #F3F4F6;gap:6px">
                    <span style="font-size:0.82rem;color:#374151;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${namePart}${calcPart}</span>
                    ${feeCell}
                    <span style="font-weight:600;font-size:0.82rem;color:#111827;white-space:nowrap;min-width:52px;text-align:right">${sym}${item.total.toFixed(2)}</span>
                </div>`;
        });

        // Column header row (only if fees are present)
        const headerRow = hasFees ? `
            <div style="display:flex;justify-content:space-between;padding-bottom:4px;border-bottom:1px solid #E5E7EB;margin-bottom:2px;gap:6px">
                <span style="font-size:0.7rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.05em;flex:1">Item</span>
                <span style="font-size:0.7rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.05em;min-width:48px;text-align:right">Fee</span>
                <span style="font-size:0.7rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.05em;min-width:52px;text-align:right">Price</span>
            </div>` : '';

        const feeSubtotal = hasFees ? `
            <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:0.78rem;color:#6B7280;gap:6px">
                <span style="flex:1">Add-ons Cost</span>
                <span style="min-width:52px;text-align:right">${sym}${addonsCost.toFixed(2)}</span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:2px 0;font-size:0.78rem;color:#6B7280;gap:6px">
                <span style="flex:1">Processing Fee</span>
                <span style="min-width:52px;text-align:right">${sym}${totalFee.toFixed(2)}</span>
            </div>` : '';

        body.innerHTML = `
            <div>${headerRow}${rows}</div>
            ${feeSubtotal}
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:2px solid #E5E7EB;gap:6px">
                <span style="font-size:0.82rem;font-weight:700;color:#111827">Total Add-ons</span>
                <span style="font-size:0.95rem;font-weight:700;color:#1D4ED8">${sym}${grandTotal.toFixed(2)}</span>
            </div>`;
    }

    // savedRows = your stored JSON of user selections
    normalizeSavedNodes(savedRows = []) {
        const build = (row) => ({
            db_id: row.id || null,
            field_id: row.field_id,
            parent_id: row.parent_id || null,
            type: row.field_meta?.type || null,
            value: row.value ?? null,
            option_id: row.option_id ?? null,
            option_label: row.option_label ?? null,
            unit_type: row.unit_type ?? null,
            // The backend submission tree already carries the saved quantity as `unit` (see
            // TourBookingAddonRepository's node builder) — without picking it up here, every
            // saved price_per_unit field's "old" unit came out null, which _computePriceBreakdown()
            // then defaulted to qty=1 regardless of the real saved quantity. That made ANY new
            // quantity greater than 1 look like an upgrade even when it was actually a decrease
            // (e.g. reducing from 5 to 3 units still computed as 3 > 1, not 3 < 5).
            unit: row.unit ?? null,
            rule_id: row.rule_id ?? null,
            applied_rule_snapshot: row.applied_rule_snapshot || null,
            paid_price: parseFloat(row.paid_price ?? 0),
            paid_fee:   parseFloat(row.paid_fee   ?? 0),
            children: row.children?.length ? row.children.map(build) : []
        });

        return savedRows.map(build);
    }

    /** --------------------------------------------------
    * UPDATED getUpdatedAddonsState() – builds latest UI state
    * -------------------------------------------------- */
    getUpdatedAddonsState() {
        const read = (node, parentId = null) => {
            const card = this.addonsFormContainer[0].querySelector(
                `.addon-card[data-node-id="${node.id}"]`
            );

            const out = {
                db_id: null,
                field_id: node.id,
                parent_id: parentId,
                type: node.type,
                value: null,
                option_id: null,
                option_label: null,
                unit_type: node.unit_type ?? null,
                unit: null,
                rule_id: node.rule_id ?? null,
                applied_rule_snapshot: null,
                children: []
            };

            if (card) {
                const t = node.type;

                if (t === "checkbox") {
                    out.value = card.querySelector('input[type="checkbox"]').checked
                        ? "checked"
                        : "not_checked";
                }
                else if (t === "radio") {
                    const r = card.querySelector("input[type=radio]:checked");
                    if (r) {
                        out.value = r.value;
                        out.option_id = r.dataset.optionId ? Number(r.dataset.optionId) : null;

                        out.option_label = r.dataset.label || null;
                    }
                }
                else if (t === "dropdown" || t === "select") {
                    const sel = card.querySelector("select");
                    if (sel) {
                        const opt = sel.selectedOptions[0];
                        out.value = sel.value;
                        out.option_id = opt?.dataset.optionId ? Number(opt.dataset.optionId) : null;
                        out.option_label = opt?.dataset.label || null;
                    }
                }
                else if (t === "number") {
                    out.value = card.querySelector("input[type=number]")?.value ?? null;
                    // For price_per_unit addons with number fields, set unit to the same value
                    if (out.unit_type === "price_per_unit" && out.value) {
                        out.unit = Number(out.value);
                    }
                }
                else if (t === "text") {
                    out.value = card.querySelector("input[type=text]")?.value ?? null;
                }
                else if (t === "textbox") {
                    out.value = card.querySelector("textarea")?.value ?? null;
                }

                /* ----------------------------------------------------
                RULE MATCHING LOGIC (ADD THIS EXACTLY HERE)
                ---------------------------------------------------- */
                if (node.rules?.length) {
                    const rule = node.rules.find(rule => {
                        if (rule.operator === "equals") {
                            return (
                                (rule.option_id && rule.option_id == out.option_id) ||
                                (rule.value && rule.value == out.value)
                            );
                        }
                        return false;
                    });

                    if (rule) {
                        out.rule_id = rule.id;
                        out.applied_rule_snapshot = {
                            value: rule.value,
                            operator: rule.operator,
                            option_id: rule.option_id
                        };
                    }
                }
            }

            if (node.children?.length) {
                // out.children = node.children.map(ch => read(ch, node.id));
                out.children = [];

                for (const ch of node.children) {
                    // IMPORTANT: check if child DOM is visible
                    const childCard = this.addonsFormContainer[0].querySelector(
                        `.addon-card[data-node-id="${ch.id}"]`
                    );

                    // If there is no DOM, or rule hides it → skip it completely
                    if (!childCard || childCard.closest(".indent")?.style.display === "none") {
                        continue;
                    }

                    const childNode = read(ch, node.id);

                    // Inherit rule_id + snapshot for child nodes if parent had rule
                    if (out.rule_id) {
                        childNode.rule_id = out.rule_id;
                        childNode.applied_rule_snapshot = out.applied_rule_snapshot;
                    }

                    // out.children.push(read(ch, node.id));
                    out.children.push(childNode);
                }
            }

            return out;
        };

        // convert roots to an array of normalized nodes
        return this.addonsFields.map(root => read(root));
    }

    flattenNodes(nodes) {
        const result = {};
        const stack = [...nodes];

        while (stack.length) {
            const n = stack.pop();

            result[n.field_id] = {
                db_id: n.db_id ?? null,
                field_id: n.field_id,
                parent_id: n.parent_id ?? null,
                type: n.type,
                value: n.value,
                option_id: n.option_id,
                option_label: n.option_label,
                unit_type: n.unit_type,
                unit: n.unit ?? null,
                rule_id: n.rule_id,
                applied_rule_snapshot: n.applied_rule_snapshot,
                paid_price: n.paid_price ?? 0,
                paid_fee:   n.paid_fee   ?? 0
            };

            if (n.children?.length) {
                for (let c of n.children) stack.push(c);
            }
        }
        return result;
    }

    isSelected(n) {
        switch (n.type) {
            case "checkbox": return n.value === "checked";
            case "radio": return n.option_id != null;
            case "dropdown":
            case "select": return n.option_id != null;
            case "number": return n.value !== "" && n.value !== null;
            case "text":
            case "textbox": return n.value?.trim() !== "";
            default: return false;
        }
    }

    // Remove unselected nodes + children
    pruneSelected(nodes, parentId = null) {
        const out = [];

        for (const n of nodes) {
            if (this.isSelected(n)) {
                const cleaned = {
                    ...n,
                    parent_id: parentId,
                    children: n.children?.length
                        ? this.pruneSelected(n.children, n.field_id)
                        : []
                };
                out.push(cleaned);
            }
        }

        return out;
    }

    /** --------------------------------------------------
    * CREATE DIFF: added[], updated[], deleted[]
    * -------------------------------------------------- */
    // FINAL DIFF ENGINE
    diffSelections() {
        // 1) Normalize saved state from DB
        const saved = this.pruneSelected(
            this.normalizeSavedNodes(this.bookingAddonsState.fields || [])
        );

        // 2) Normalize UI state
        const current = this.pruneSelected(
            this.getUpdatedAddonsState()
        );

        // 3) Flatten both for easy diff
        const oldFlat = this.flattenNodes(saved);
        const newFlat = this.flattenNodes(current);

        const added = [];
        const updated = [];
        const deleted = [];

        // 4) Added & Updated
        for (const fid in newFlat) {
            const cur = newFlat[fid];
            const old = oldFlat[fid];

            if (!old) {
                // NEW selection
                added.push(cur);
            } else {
                // Changed?
                // detect change based on field type
                let changed = false;

                switch (cur.type) {
                    case "radio":
                    case "dropdown":
                    case "select":
                        changed = cur.option_id !== old.option_id;
                        break;

                    case "checkbox":
                        changed = cur.value !== old.value;
                        break;

                    case "text":
                    case "textbox":
                    case "number":
                        changed = (cur.value ?? "").trim() !== (old.value ?? "").trim();
                        break;

                    default:
                        changed = cur.value !== old.value;
                }

                // only push when really changed
                if (changed) {
                    updated.push({
                        ...cur,
                        db_id: old.db_id,
                        applied_rule_snapshot: cur.applied_rule_snapshot,
                        // Carried through so buildAddonPayload() can tell upgrade from downgrade
                        // by comparing NOMINAL prices (old selection vs new selection), instead of
                        // comparing the new price against what was actually PAID — see that
                        // function for why those are different things. Also lets it build a
                        // "before → after" label (e.g. "DSLR Camera → Basic Camera") for history.
                        old_option_id: old.option_id,
                        old_unit: old.unit,
                        old_value: old.value
                    });
                }
            }
        }

        // 5) Deleted
        for (const fid in oldFlat) {
            if (!newFlat[fid]) {
                deleted.push({
                    db_id: oldFlat[fid].db_id,
                    field_id: oldFlat[fid].field_id,
                    // Carried through so buildAddonPayload() can fall back to this item's own
                    // computed price (via _computePriceBreakdown) when nothing was actually
                    // paid yet (Pay Later/open) — see that function for why.
                    option_id: oldFlat[fid].option_id,
                    unit: oldFlat[fid].unit
                });
            }
        }

        return { added, updated, deleted };
    }

    // --------------------------------------------------
    // PAYLOAD HELPERS
    // --------------------------------------------------

    /** Recursive field-definition lookup (searches rules.children too) */
    _findFieldDef(fieldId, nodes) {
        const list = nodes !== undefined ? nodes : (this.addonsFields || []);
        for (const node of list) {
            if (node.id == fieldId) return node;
            for (const r of (node.rules || [])) {
                const found = this._findFieldDef(fieldId, r.children || []);
                if (found) return found;
            }
        }
        return null;
    }

    /**
     * parseInt() a quantity while treating a genuine 0 as a real value, not "missing" — plain
     * `parseInt(x) || fallback` is falsy-zero-unsafe (0 is falsy in JS), so downgrading a
     * price_per_unit field all the way to 0 units (e.g. "Extra Water Bottles: 5 → 0") silently
     * came back as 1 instead everywhere this pattern was used: the refund modal priced the
     * downgrade as if only 4 of the 5 units were being removed (1 unit's price/fee stayed
     * "kept"), and that wrong amount is exactly what got sent to the backend and actually
     * refunded, since the server trusts the frontend-computed refund_amount as-is. `fallback`
     * only applies when `raw` doesn't parse to a number at all (null/undefined/empty/NaN).
     */
    _parseQty(raw, fallback = 1) {
        const n = parseInt(raw, 10);
        return Number.isNaN(n) ? fallback : n;
    }

    /**
     * Compute the price for a given field+option+unit from the form definition.
     * Handles: field-level price, option-level price, and both together.
     */
    _computePriceBreakdown(fieldId, optionId, unit) {
        const def = this._findFieldDef(fieldId);
        if (!def) return { fieldPrice: 0, optionPrice: 0, fieldAdditionalFee: false, optionAdditionalFee: false, total: 0 };

        let fieldPrice  = 0;
        let optionPrice = 0;
        let fieldAdditionalFee  = false;
        let optionAdditionalFee = false;

        if ((def.additional_fee == 1 || def.additional_fee === true) && parseFloat(def.price) > 0) {
            fieldPrice = parseFloat(def.price);
            fieldAdditionalFee = true;
        }

        if (optionId && def.options?.length) {
            const opt = def.options.find(o => o.id == optionId);
            if (opt && (opt.additional_fee == 1 || opt.additional_fee === true) && parseFloat(opt.price) > 0) {
                optionPrice = parseFloat(opt.price);
                optionAdditionalFee = true;
            }
        }

        if (def.unit_type === 'price_per_unit') {
            const qty = this._parseQty(unit);
            fieldPrice  = parseFloat((fieldPrice  * qty).toFixed(2));
            optionPrice = parseFloat((optionPrice * qty).toFixed(2));
        }

        return {
            fieldPrice:         parseFloat(fieldPrice.toFixed(2)),
            optionPrice:        parseFloat(optionPrice.toFixed(2)),
            fieldAdditionalFee,
            optionAdditionalFee,
            total:              parseFloat((fieldPrice + optionPrice).toFixed(2))
        };
    }

    _computeNewPrice(fieldId, optionId, unit) {
        return this._computePriceBreakdown(fieldId, optionId, unit).total;
    }

    /**
     * Return the base price that was already paid for a DB row (excludes processing fee).
     * Returns 0 if there is no paid transaction (e.g. field was saved but never charged).
     * Searches nested children too — priced rows are often children of a non-priced
     * parent (e.g. a "Yes" radio revealing a priced dropdown), and a top-level-only
     * lookup returned $0 for them, silently skipping their refund on removal.
     */
    _getPaidPrice(dbId) {
        if (!dbId) return 0;
        const findRow = (rows) => {
            for (const r of rows || []) {
                if (r.id == dbId) return r;
                const hit = findRow(r.children);
                if (hit) return hit;
            }
            return null;
        };
        const saved = findRow(this.userState?.addons || []);
        if (!saved?.transaction) return 0;
        return parseFloat(
            ((parseFloat(saved.transaction.field_price) || 0)
           + (parseFloat(saved.transaction.option_price) || 0)).toFixed(2)
        );
    }

    /**
     * Build the complete payload for an addon update.
     *
     * financial.to_charge  – items the customer must pay for (new additions + upgrades)
     * financial.to_refund  – items the customer is owed back (removals + downgrades)
     * financial.action     – 'charge' | 'refund' | 'none'
     * financial.net_amount – absolute net (always positive; action gives direction)
     */
    buildAddonPayload() {
        const { added, updated, deleted } = this.diffSelections();

        const toCharge = [];
        const toRefund = [];

        // --- Added fields ---
        for (const item of added) {
            const bd = this._computePriceBreakdown(item.field_id, item.option_id, item.unit);
            if (bd.total > 0) {
                toCharge.push({
                    field_id:             item.field_id,
                    label:                this._buildAddonLabel(item.field_id, item.option_label),
                    option_id:            item.option_id || null,
                    unit:                 item.unit || 1,
                    new_price:            bd.total,
                    old_db_id:            null,
                    old_paid:             0,
                    charge_amount:        bd.total,
                    field_price:          bd.fieldPrice,
                    option_price:         bd.optionPrice,
                    field_additional_fee: bd.fieldAdditionalFee,
                    option_additional_fee: bd.optionAdditionalFee,
                    reason:               'added'
                });
            }
        }

        // --- Updated fields ---
        for (const item of updated) {
            const bd      = this._computePriceBreakdown(item.field_id, item.option_id, item.unit);
            const oldPaid = this._getPaidPrice(item.db_id);
            // Upgrade vs downgrade DIRECTION must come from comparing NOMINAL prices (what the
            // old selection and the new selection each cost per the form's pricing schema), NOT
            // from comparing the new price against oldPaid (what was actually collected). A Pay
            // Later / never-charged field always has oldPaid === 0, so using it as the "old"
            // baseline made every option change look like an upgrade — even a genuine price
            // DECREASE — because new_price - 0 is always >= 0. That silently routed pure
            // downgrades on unpaid add-ons into the payment/charge modal instead of a refund
            // (or, when nothing was ever paid, informational-only) modal.
            const oldBd = this._computePriceBreakdown(item.field_id, item.old_option_id, item.old_unit);

            if (bd.total > oldBd.total + 0.001) {
                // Real money still owed = new nominal price minus whatever was actually paid
                // toward the old selection (0 if it was never charged — the full new price is owed).
                const chargeAmount = parseFloat((bd.total - oldPaid).toFixed(2));
                if (chargeAmount > 0.001) {
                    toCharge.push({
                        field_id:             item.field_id,
                        label:                this._buildAddonChangeLabel(item.field_id, item.old_option_id, item.old_value, item.option_label, item.value),
                        option_id:            item.option_id || null,
                        unit:                 item.unit || 1,
                        new_price:            bd.total,
                        old_db_id:            item.db_id,
                        old_paid:             oldPaid,
                        charge_amount:        chargeAmount,
                        field_price:          bd.fieldPrice,
                        option_price:         bd.optionPrice,
                        field_additional_fee: bd.fieldAdditionalFee,
                        option_additional_fee: bd.optionAdditionalFee,
                        reason:               'upgraded'
                    });
                }
            } else if (bd.total < oldBd.total - 0.001) {
                // Real money to actually refund is capped by what was actually paid — if nothing
                // was ever paid toward the old (more expensive) selection, refund_amount stays 0
                // and credit_amount (the nominal difference) drives the modal choice/history only,
                // same pattern the 'Deleted fields' bucket below already uses.
                toRefund.push({
                    db_id:         item.db_id,
                    field_id:      item.field_id,
                    label:         this._buildAddonChangeLabel(item.field_id, item.old_option_id, item.old_value, item.option_label, item.value),
                    refund_amount: parseFloat(Math.max(0, oldPaid - bd.total).toFixed(2)),
                    credit_amount: parseFloat((oldBd.total - bd.total).toFixed(2)),
                    reason:        'downgraded'
                });
            }
            // Nominal prices equal → just save, no financial action, regardless of oldPaid.
        }

        // --- Deleted fields ---
        for (const item of deleted) {
            const oldPaid = this._getPaidPrice(item.db_id);
            // Pay Later (and any other not-yet-invoiced) add-ons carry a transaction status of
            // 'open', never 'paid' — _getPaidPrice() legitimately returns $0 for them even
            // though the item genuinely costs something and is about to stop being owed.
            // Falling back to the item's own computed price lets this removal correctly offset
            // a same-cost addition elsewhere instead of always looking like a fresh charge with
            // nothing to net against. Only used to decide whether ANY charge/refund flow is
            // needed at all — refund_amount (real money actually collected) stays untouched.
            const creditAmount = oldPaid > 0
                ? oldPaid
                : this._computePriceBreakdown(item.field_id, item.option_id, item.unit).total;
            if (creditAmount > 0) {
                // deleted[] (see diffSelections()) only carries option_id, not option_label —
                // resolve it from the field definition instead of falling back to the bare field
                // name.
                toRefund.push({
                    db_id:         item.db_id,
                    field_id:      item.field_id,
                    label:         this._buildAddonLabel(item.field_id, this._getOptionLabel(item.field_id, item.option_id)),
                    refund_amount: oldPaid,
                    credit_amount: creditAmount,
                    reason:        'removed'
                });
            }
        }

        const totalCharge = parseFloat(toCharge.reduce((s, i) => s + i.charge_amount, 0).toFixed(2));
        // Net/action decided on billable credit_amount (nominal price difference), not the real
        // refund_amount (money actually collectible) — both 'downgraded' and 'removed' items
        // always set credit_amount now. See the 'Updated'/'Deleted fields' loops above.
        const totalRefund = parseFloat(toRefund.reduce((s, i) => s + i.refund_amount, 0).toFixed(2));
        const totalCredit = parseFloat(toRefund.reduce((s, i) => s + (i.credit_amount ?? i.refund_amount), 0).toFixed(2));
        const netRaw      = parseFloat((totalCharge - totalCredit).toFixed(2));

        let action = 'none';
        if (netRaw > 0.001)       action = 'charge';
        else if (netRaw < -0.001) action = 'refund';

        return {
            form_id:    this.bookingAddonsState.form_id || this.formId,
            booking_id: this.bookingId,
            added,
            updated,
            deleted,
            financial: {
                action,
                net_amount:   parseFloat(Math.abs(netRaw).toFixed(2)),
                total_charge: totalCharge,
                total_refund: totalRefund,
                to_charge:    toCharge,
                to_refund:    toRefund
            }
        };
    }

    // ===================================================================
    // PAYMENT & REFUND MODALS
    // ===================================================================

    _getFieldLabel(fieldId) {
        const def = this._findFieldDef(fieldId);
        return def?.label || `Add-on #${fieldId}`;
    }

    /** Resolve an option's label from the field definition — only needed when a diff item
     *  didn't already carry its own option_label through (see flattenNodes()). */
    _getOptionLabel(fieldId, optionId) {
        if (!optionId) return null;
        const def = this._findFieldDef(fieldId);
        const opt = def?.options?.find(o => o.id == optionId);
        return opt?.label || null;
    }

    /** "Sweater (Medium)" when an option is selected, otherwise just "Sweater" — matches
     *  ChangePeopleAddonService::buildAddonLabel()'s server-side convention, so the same
     *  field+option combination reads identically everywhere (charge/refund modals, History &
     *  Notes). Without this, every add-on with a selected option (e.g. a Sweater's size)
     *  recorded/displayed only the bare field name, silently dropping which option was picked. */
    _buildAddonLabel(fieldId, optionLabel) {
        const base = this._getFieldLabel(fieldId);
        return optionLabel ? `${base} (${optionLabel})` : base;
    }

    /** Human-readable display for a raw stored value on a non-option field (checkbox/text/etc.) —
     *  mirrors renderValue()'s true/false → Yes/No convention used elsewhere in this file. */
    _displayValue(value) {
        if (value === true || value === 'true' || value === 'checked') return 'Yes';
        if (value === false || value === 'false' || value === 'not_checked') return 'No';
        return value ?? '';
    }

    /**
     * "Camera Rental Type: DSLR Camera → Basic Camera" for a same-field option/value change
     * (an 'upgraded'/'downgraded' item in the diff) — shows what it actually changed FROM and TO,
     * not just what it's now set to. Falls back to the plain _buildAddonLabel() when there's
     * nothing meaningful to show on the "before" side (e.g. a brand-new field with no prior
     * selection at all — shouldn't normally reach here, since that's an 'added' item instead).
     */
    _buildAddonChangeLabel(fieldId, oldOptionId, oldValue, newOptionLabel, newValue) {
        const base = this._getFieldLabel(fieldId);
        const oldLabel = oldOptionId ? this._getOptionLabel(fieldId, oldOptionId) : this._displayValue(oldValue);
        const newLabel = newOptionLabel || this._displayValue(newValue);
        if (oldLabel && newLabel && oldLabel !== newLabel) {
            return `${base}: ${oldLabel} → ${newLabel}`;
        }
        return this._buildAddonLabel(fieldId, newOptionLabel);
    }

    /**
     * { net, fee } that an upgrade line's old pending payment-link line is worth — non-zero only
     * for an upgrade (old_db_id) of an add-on still pending on the link. Source:
     * pendingAddonLinkLineShares (tour-booking-info.blade.php →
     * PaymentLinkService::pendingAddonLinkLineShares()).
     */
    _pendingLinkShare(item) {
        const shares = typeof pendingAddonLinkLineShares !== 'undefined' ? pendingAddonLinkLineShares : null;
        const share  = (shares && item && item.old_db_id) ? shares[item.old_db_id] : null;
        return share
            ? { net: parseFloat(share.net) || 0, fee: parseFloat(share.fee) || 0, lumpSum: !!share.lump_sum }
            : { net: 0, fee: 0, lumpSum: false };
    }

    _showChargeModal(payload, onSubmit = null) {
        const sym = typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$';
        const { financial } = payload;

        // --- Build breakdown ---
        let html = '';

        // An upgrade of an add-on that's itself still pending on the payment link is charged its
        // full new price (nothing was paid for the old one), and its old pending line is dropped
        // from the link when this is saved — so that line's value comes back out of both the
        // item shown here (leaving the real difference, e.g. Medium $15 → Large $20 = +$5) and the
        // total, instead of billing the old and new selection together.
        const superseded = { net: 0, fee: 0 };
        for (const item of financial.to_charge) {
            const share = this._pendingLinkShare(item);
            superseded.net += share.net;
            superseded.fee += share.fee;
        }
        superseded.net = Math.round(superseded.net * 100) / 100;
        superseded.fee = Math.round(superseded.fee * 100) / 100;

        if (financial.to_charge.length) {
            html += `<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">Items Being Added / Upgraded</div>`;
            html += `<div class="rounded-lg border border-[#D1FAE5] overflow-hidden mb-3">`;
            for (const item of financial.to_charge) {
                // item.label already includes the selected option (e.g. "Sweater (Medium)") —
                // see buildBulkAddonPayload()/buildAddonPayload() — recomputing from just
                // field_id here silently dropped it from these preview modals.
                const label = item.label;
                const badge = item.reason === 'added'
                    ? `<span style="font-size:0.7rem;padding:2px 8px;border-radius:20px;background:#D1FAE5;color:#065F46;font-weight:600">New</span>`
                    : `<span style="font-size:0.7rem;padding:2px 8px;border-radius:20px;background:#E0E7FF;color:#3730A3;font-weight:600">Upgrade</span>`;
                html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #F0FDF4">
                    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
                        <span style="font-size:0.875rem;color:#111827;font-weight:500">${label}</span>
                        ${badge}
                    </div>
                    <span style="font-size:0.9rem;font-weight:700;color:#059669;white-space:nowrap;margin-left:12px">+${sym}${(item.charge_amount - this._pendingLinkShare(item).net).toFixed(2)}</span>
                </div>`;
            }
            html += `</div>`;
        }

        if (financial.to_refund.length) {
            html += `<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">Credits from Removed Items</div>`;
            html += `<div class="rounded-lg border border-[#FED7AA] overflow-hidden mb-3">`;
            for (const item of financial.to_refund) {
                // item.label already includes the selected option (e.g. "Sweater (Medium)") —
                // see buildBulkAddonPayload()/buildAddonPayload() — recomputing from just
                // field_id here silently dropped it from these preview modals.
                const label = item.label;
                const badge = `<span style="font-size:0.7rem;padding:2px 8px;border-radius:20px;background:#FEF3C7;color:#92400E;font-weight:600">Credit</span>`;
                html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #FFF7ED">
                    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
                        <span style="font-size:0.875rem;color:#111827;font-weight:500">${label}</span>
                        ${badge}
                    </div>
                    <span style="font-size:0.9rem;font-weight:700;color:#D97706;white-space:nowrap;margin-left:12px">-${sym}${item.refund_amount.toFixed(2)}</span>
                </div>`;
            }
            html += `</div>`;
        }

        // Fee + total calculation
        const via = this.bookingPaymentVia || 'Pay Later';
        // A booking with a real, active pending Payment Link (via === 'Pay Later' by default,
        // same as a genuine never-charged booking) still gets charged through Stripe like a Card
        // booking once paid — the server (createAddonPaymentLink()) already applies the platform
        // fee unconditionally regardless of via for exactly this reason. Gating the fee display on
        // via === 'Card' alone under-quoted this preview: the modal showed a lower "Total to
        // Charge" than what the server actually charges/regenerates the link for. Hoisted above
        // its original spot (further down, near the payment-method section) so both the fee
        // calculation and the payment-method choice share one declaration.
        const feePct = (via === 'Card' || (via === 'Cash' && this.isCashFees)) ? this.natFeePct : 0;
        // The backend fees each addon item independently and sums the results (unless a
        // refund credit offsets part of the charge, where it fees the net amount once
        // instead — see stripeCreatePaymentIntentForAddonEditCharge()). Rounding the
        // aggregate net_amount here in every case can land a cent away from that per-item
        // sum, so mirror the same branch to keep this preview in sync with what's charged.
        const hasChargeOffset = financial.to_charge.length > 0 && financial.to_refund.length > 0;
        let feeAmount = 0;
        if (feePct > 0) {
            if (financial.to_charge.length && !hasChargeOffset) {
                feeAmount = financial.to_charge.reduce((sum, item) => {
                    // For 'upgraded' items field_price/option_price are the item's NEW total
                    // price, but only charge_amount (the delta) is actually being charged —
                    // scale them down proportionally first, matching the backend
                    // (processAddonFinancialTransactions()) exactly, otherwise the fee is
                    // computed on the full new price instead of the amount actually charged.
                    const chargeAmount   = item.charge_amount || 0;
                    // Nullish coalescing, NOT `||` — field_price is legitimately 0 for
                    // option-only priced items (e.g. a dropdown), and `||` would wrongly
                    // treat that 0 as "missing" and substitute chargeAmount, corrupting
                    // rawTotal and spuriously triggering the upgrade-scaling branch below.
                    const rawFieldPrice  = item.field_price  ?? chargeAmount;
                    const rawOptionPrice = item.option_price ?? 0;
                    const rawTotal       = rawFieldPrice + rawOptionPrice;

                    let fieldPrice, optionPrice;
                    if (rawTotal > 0 && Math.abs(rawTotal - chargeAmount) > 0.001) {
                        const scale = chargeAmount / rawTotal;
                        fieldPrice  = Math.round(rawFieldPrice * scale * 100) / 100;
                        optionPrice = Math.round((chargeAmount - fieldPrice) * 100) / 100;
                    } else {
                        fieldPrice  = rawFieldPrice;
                        optionPrice = rawOptionPrice;
                    }

                    const fieldFee  = Math.round(fieldPrice  * feePct / 100 * 100) / 100;
                    const optionFee = Math.round(optionPrice * feePct / 100 * 100) / 100;
                    return sum + fieldFee + optionFee;
                }, 0);
                feeAmount = Math.round(feeAmount * 100) / 100;
            } else {
                feeAmount = Math.round(financial.net_amount * feePct / 100 * 100) / 100;
            }
        }
        const totalAmount   = financial.net_amount + feeAmount;

        // Expose the computed incremental amount on the payload so the downstream card
        // payment initializers (Stripe Elements form AND the Card Reader/Terminal flow)
        // charge exactly this delta instead of falling back to the booking's full
        // outstanding total — see _initAddonEditCardPayment()/_initBulkPaxCardPayment().
        financial.total_to_charge = totalAmount;
        financial.fee_amount = feeAmount;
        const pendingAmount = parseFloat($('#modal_addon_edit_charge').data('pending-amount')) || 0;
        // Card charges (server-side PaymentIntent and the Card Reader total) leave the superseded
        // pending lines out too — see _initAddonEditCardPayment()/_initBulkPaxCardPayment().
        financial.pending_superseded_amount = parseFloat((superseded.net + superseded.fee).toFixed(2));
        // Pay Later booking with a pending, fee-INCLUSIVE payment link: the link moves by the
        // (price + fee) of whatever is added or upgraded even though Pay Later / Billable itself adds
        // no fee, so show that fee too (display only — the payload is untouched). A brand-new add-on
        // has no share on the link yet, so this can't depend on one. E.g. a new $20 add-on shows
        // +$20.00 + $1.96 fee, and an upgrade $16 → $18 shows +$2.00 once the superseded fee is out.
        let lumpSumNewFee = 0;
        if (feePct === 0 && pendingAmount > 0) {
            for (const item of financial.to_charge) {
                lumpSumNewFee += Math.round((item.field_price  || 0) * this.natFeePct) / 100
                               + Math.round((item.option_price || 0) * this.natFeePct) / 100;
            }
            lumpSumNewFee = Math.round(lumpSumNewFee * 100) / 100;
        }
        const combinedAmount = parseFloat((totalAmount + lumpSumNewFee + pendingAmount - financial.pending_superseded_amount).toFixed(2));
        const displaySubtotal = financial.net_amount - superseded.net;
        const displayFee      = feeAmount + lumpSumNewFee - superseded.fee;

        // Subtotal / fee / pending / total rows
        html += `<div style="background:#F0FDF4;border:1.5px solid #6EE7B7;border-radius:8px;margin-bottom:16px;overflow:hidden">`;
        html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #D1FAE5">
            <span style="font-size:0.875rem;color:#374151">Subtotal</span>
            <span style="font-size:0.9rem;font-weight:600;color:#374151">${sym}${displaySubtotal.toFixed(2)}</span>
        </div>`;
        if (displayFee > 0.001) {
            html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #D1FAE5">
                <span style="font-size:0.875rem;color:#6B7280">Booking Fee</span>
                <span style="font-size:0.9rem;color:#6B7280">+${sym}${displayFee.toFixed(2)}</span>
            </div>`;
        }
        if (pendingAmount > 0) {
            // UI-only: hide the "view breakdown" (i) button for Pay Later — the row and amount
            // still show as before, only the button is not rendered. No breakdown/data change.
            const breakdownButtonHtml = via === 'Pay Later' ? '' : `
                    <button type="button" id="btn-addon-charge-payment-breakdown"
                        style="background:none;border:none;cursor:pointer;padding:0;color:#264f73;line-height:1"
                        title="View pending payment breakdown">
                        <i class="fa fa-info-circle" style="font-size:0.95rem"></i>
                    </button>`;
            html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #D1FAE5">
                <span style="display:flex;align-items:center;gap:6px;font-size:0.875rem;color:#B45309">
                    Pending Payment Link
                    ${breakdownButtonHtml}
                </span>
                <span style="font-size:0.9rem;color:#B45309">+${sym}${pendingAmount.toFixed(2)}</span>
            </div>`;
        }
        html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#ECFDF5">
            <span style="font-size:0.9rem;font-weight:700;color:#047857">Total to Charge</span>
            <span style="font-size:1.05rem;font-weight:800;color:#047857">${sym}${combinedAmount.toFixed(2)}</span>
        </div>`;
        html += `</div>`;

        document.getElementById('addon-edit-charge-breakdown').innerHTML = html;

        // --- Payment method section ---
        // Payment Link doesn't account for a split-payment leg's amount (it always bills the
        // full remaining booking total) — offering it here on a split-payment booking sends the
        // wrong amount, same class of bug fixed for the Make Payment/Change People/Custom
        // Charge/Discount modals. isSplitPaymentBooking is a page-level global (see
        // tour-booking-info.blade.php); guard its lookup since this file can run on pages that
        // don't define it.
        const isSplitBooking = typeof isSplitPaymentBooking !== 'undefined' && isSplitPaymentBooking;
        // hasPendingLink is computed above (next to the fee calculation) — a booking with one is
        // never simultaneously a split-payment-leg booking (isSplitBooking requires a completed
        // transaction to already exist, which a pending-link booking by definition doesn't have),
        // so no extra guard is needed there.
        // Tenant's PAYMENT_LINK setting (page-level global from tour-booking-info.blade.php, ''
        // when off). Undefined on pages that don't define it — keep the previous behavior there.
        const paymentLinkEnabled = typeof isPaymentLink === 'undefined' || isPaymentLink == '1';
        let methodsHtml;
        if (via === 'Card' && !isSplitBooking) {
            methodsHtml = (paymentLinkEnabled ? `
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#F0FFF4;border:1.5px solid #6EE7B7;border-radius:8px;cursor:pointer;margin-bottom:8px">
                    <input type="radio" name="addon-charge-method" value="payment_link" style="accent-color:#059669;flex-shrink:0">
                    <span style="font-size:1.1rem">🔗</span>
                    <div style="flex:1">
                        <div style="font-size:0.825rem;font-weight:600;color:#059669">Payment Link</div>
                        <div style="font-size:0.72rem;color:#6B7280">Send a payment link to the customer</div>
                    </div>
                    <span style="font-size:0.9rem;font-weight:700;color:#064E3B">${sym}${combinedAmount.toFixed(2)}</span>
                </label>` : '') + `
                <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#F0F7FF;border:1.5px solid #BFDBFE;border-radius:8px;cursor:pointer">
                    <input type="radio" name="addon-charge-method" value="Card" checked style="accent-color:#1D4ED8;flex-shrink:0">
                    <span style="font-size:1.1rem">💳</span>
                    <div style="flex:1">
                        <div style="font-size:0.825rem;font-weight:600;color:#1D4ED8">Card (Stripe)</div>
                        <div style="font-size:0.72rem;color:#6B7280">Using booking's original payment method</div>
                    </div>
                    <span style="font-size:0.9rem;font-weight:700;color:#264F73">${sym}${combinedAmount.toFixed(2)}</span>
                </label>`;
        } else if (via === 'Card' && isSplitBooking) {
            methodsHtml = `
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#F0F7FF;border:1.5px solid #BFDBFE;border-radius:8px">
                    <span style="font-size:1.1rem">💳</span>
                    <div style="flex:1">
                        <div style="font-size:0.825rem;font-weight:600;color:#1D4ED8">Card (Stripe)</div>
                        <div style="font-size:0.72rem;color:#6B7280">Using booking's original payment method</div>
                    </div>
                    <span style="font-size:0.9rem;font-weight:700;color:#264F73">${sym}${combinedAmount.toFixed(2)}</span>
                </div>`;
        } else {
            const viaLabel = via === 'Pay Later' ? 'Pay Later / Billable'
                           : via === 'Cash'      ? 'Cash'
                           : via === 'Invoiced'  ? 'Billable'
                           : via;
            const viaIcon = via === 'Cash' ? '💵' : '🕐';
            methodsHtml = `
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#F0F7FF;border:1.5px solid #BFDBFE;border-radius:8px">
                    <span style="font-size:1.1rem">${viaIcon}</span>
                    <div style="flex:1">
                        <div style="font-size:0.825rem;font-weight:600;color:#1D4ED8">${viaLabel}</div>
                        <div style="font-size:0.72rem;color:#6B7280">Using booking's original payment method</div>
                    </div>
                    <span style="font-size:0.9rem;font-weight:700;color:#264F73">${sym}${combinedAmount.toFixed(2)}</span>
                </div>`;
        }
        document.getElementById('addon-edit-charge-methods').innerHTML = methodsHtml;

        // Store payload
        this._pendingAddonPayload = payload;

        // Wire submit button (clone removes stale listeners)
        const btn = document.getElementById('addon-edit-charge-submit');
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', () => {
            const checkedRadio = document.querySelector('input[name="addon-charge-method"]:checked');
            const submitMethod = checkedRadio ? checkedRadio.value : via;
            if (onSubmit) {
                onSubmit(payload, submitMethod);
            } else {
                this._handleChargeSubmit(payload, submitMethod);
            }
        });

        $('#modal_addon_edit_charge').modal('show');
    }

    _handleChargeSubmit(payload, paymentMethod) {
        if (paymentMethod === 'payment_link') {
            $('#modal_addon_edit_charge').modal('hide');
            this._submitAddonPaymentLink(payload);
        } else if (paymentMethod === 'Card') {
            $('#modal_addon_edit_charge').modal('hide');
            this._initAddonEditCardPayment(payload);
        } else {
            $('#modal_addon_edit_charge').modal('hide');
            this._submitAddonEditCharge(payload, paymentMethod);
        }
    }

    async _submitAddonPaymentLink(payload) {
        try {
            this.showLoading();
            const resp = await fetch(this.addonPaymentLinkUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            this.hideLoading();
            if (data.success) {
                swal('Payment Link Sent', data.message || 'Payment link sent successfully.', 'success')
                    .then(() => location.reload());
            } else {
                swal('Error', data.message || 'Something went wrong.', 'error');
            }
        } catch (err) {
            this.hideLoading();
            console.error('Addon payment link error:', err);
            swal('Error', 'Failed to create payment link. Please try again.', 'error');
        }
    }

    _initAddonEditCardPayment(payload) {
        const form = document.createElement('form');
        form.style.display = 'none';
        form.innerHTML = `
            <input type="hidden" name="feature"    value="addon_edit_charge">
            <input type="hidden" name="bookingId"  value="${payload.booking_id}">
            <input type="hidden" name="net_amount" value="${payload.financial.net_amount}">
            <input type="hidden" name="formId"     value="${payload.form_id}">
        `;
        // See _initBulkPaxCardPayment() for why the itemized breakdown is needed here too.
        const financialItemsInput = document.createElement('input');
        financialItemsInput.type = 'hidden';
        financialItemsInput.name = 'financial_items';
        financialItemsInput.value = JSON.stringify({
            to_charge: payload.financial.to_charge,
            to_refund: payload.financial.to_refund
        });
        form.appendChild(financialItemsInput);
        document.body.appendChild(form);
        this._addonEditCardForm = form;
        // The Card Reader (Stripe Terminal) tab of #make-a-payment reads its charge amount
        // from backendCardReaderInfo, not from this form — without setting it here, its
        // show.bs.modal handler falls back to the booking's full outstanding total instead
        // of this incremental add-on charge (Card Online is unaffected: it derives its
        // amount server-side from net_amount/financial_items above).
        // Re-derive the combined (charge + pending payment link) amount the same way
        // _initBulkPaxCardPayment() does — combinedAmount itself is local to the modal-building
        // method that constructed this payload, not in scope here.
        const pendingAmount = parseFloat($('#modal_addon_edit_charge').data('pending-amount')) || 0;
        const combinedAmount = parseFloat((payload.financial.total_to_charge + pendingAmount - (payload.financial.pending_superseded_amount || 0)).toFixed(2));
        backendCardReaderInfo.getTotal(combinedAmount);
        backendCardReaderInfo.getFees(payload.financial.fee_amount);
        this._pendingCardReaderFeature = 'addon_edit_charge';
        initTourBookingStripePayment.initializeStripe(form, 'addon_edit_charge');
    }

    async _submitAddonEditCharge(payload, paymentMethod, paymentIntentId = null) {
        const enriched = {
            ...payload,
            financial: {
                ...payload.financial,
                payment_method:    paymentMethod,
                payment_intent_id: paymentIntentId
            }
        };
        try {
            this.showLoading();
            const resp = await fetch(this.updateBookingAddonsUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(enriched)
            });
            const data = await resp.json();
            this.hideLoading();
            if (data.success) {
                swal('Payment Recorded', 'Add-ons updated and payment recorded.', 'success').then(() => location.reload());
            } else {
                swal('Error', data.message || 'Something went wrong.', 'error');
            }
        } catch (err) {
            this.hideLoading();
            console.error('Addon charge submit error:', err);
            swal('Error', 'Failed to process payment.', 'error');
        }
    }

    _showRefundModal(payload, onSubmit = null) {
        const sym = typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$';
        const { financial } = payload;

        // Nothing has actually been paid on EITHER side of this edit (every to_refund item's
        // refund_amount is 0 — nothing to give back — and every to_charge item's charge_amount
        // is 0 too) even though the nominal price change (credit_amount) is non-zero — e.g. a
        // downgrade on a Pay Later add-on that was never invoiced. No real money moves in that
        // case, so there is nothing to select: just confirm the change and log it.
        const hasRealMoney = financial.to_refund.some(i => i.refund_amount > 0.001)
            || financial.to_charge.some(i => i.charge_amount > 0.001);

        // financial.net_amount is the NOMINAL net (total_charge vs. the credit_amount side of
        // to_refund — see buildAddonPayload()), which intentionally folds in price drops on
        // add-ons that were never actually paid for (e.g. a Pending/Pay Later item being
        // removed) so the 'charge'/'refund' MODE decision and the charge-offset math elsewhere
        // stay correct. But the dollar figure shown here as "Net Refund Amount" must reflect
        // real money only — otherwise removing a never-paid add-on alongside a genuine paid
        // downgrade inflates the displayed refund by the never-paid item's price (it's already
        // shown per-line as "$X (not refunded)" above; it must not also count toward the total).
        const realNetAmount = hasRealMoney
            ? Math.abs((financial.total_refund ?? 0) - (financial.total_charge ?? 0))
            : financial.net_amount;

        // --- Build breakdown ---
        let html = '';

        if (financial.to_refund.length) {
            html += `<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">Items Being Refunded</div>`;
            html += `<div class="rounded-lg border border-[#FED7AA] overflow-hidden mb-3">`;
            for (const item of financial.to_refund) {
                // item.label already includes the selected option (e.g. "Sweater (Medium)") —
                // see buildBulkAddonPayload()/buildAddonPayload() — recomputing from just
                // field_id here silently dropped it from these preview modals.
                const label = item.label;
                const reasonMap = { removed: 'Removed', downgraded: 'Downgraded' };
                const badge = `<span style="font-size:0.7rem;padding:2px 8px;border-radius:20px;background:#FEF3C7;color:#92400E;font-weight:600">${reasonMap[item.reason] || item.reason}</span>`;
                // An item can carry a non-zero credit_amount (nominal price drop) while its own
                // refund_amount is 0 (nothing was actually paid toward it) — show that plainly
                // instead of implying money is moving for this specific line.
                const itemAmount = item.refund_amount > 0.001
                    ? `<span style="font-size:0.9rem;font-weight:700;color:#EA580C;white-space:nowrap;margin-left:12px">${sym}${item.refund_amount.toFixed(2)}</span>`
                    : `<span style="font-size:0.8rem;font-weight:600;color:#6B7280;white-space:nowrap;margin-left:12px">${sym}${(item.credit_amount ?? 0).toFixed(2)} (not refunded)</span>`;
                html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #FFF7ED">
                    <div style="display:flex;align-items:center;gap:8px;flex:1;min-width:0">
                        <span style="font-size:0.875rem;color:#111827;font-weight:500">${label}</span>
                        ${badge}
                    </div>
                    ${itemAmount}
                </div>`;
            }
            html += `</div>`;
        }

        if (financial.to_charge.length) {
            html += `<div class="text-xs font-semibold uppercase tracking-wide text-[#6B7280] mb-2">New Charges (offset against refund)</div>`;
            html += `<div class="rounded-lg border border-[#D1FAE5] overflow-hidden mb-3">`;
            for (const item of financial.to_charge) {
                // item.label already includes the selected option (e.g. "Sweater (Medium)") —
                // see buildBulkAddonPayload()/buildAddonPayload() — recomputing from just
                // field_id here silently dropped it from these preview modals.
                const label = item.label;
                html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#fff;border-bottom:1px solid #F0FDF4">
                    <span style="font-size:0.875rem;color:#111827;font-weight:500">${label}</span>
                    <span style="font-size:0.9rem;font-weight:700;color:#059669;white-space:nowrap;margin-left:12px">-${sym}${item.charge_amount.toFixed(2)}</span>
                </div>`;
            }
            html += `</div>`;
        }

        // Net row — labelled as an actual refund only when real money is involved; otherwise
        // it's just the nominal price adjustment being recorded, not money changing hands.
        const netLabel = hasRealMoney ? 'Net Refund Amount' : 'Price Adjustment (Not Refunded)';
        const netColor = hasRealMoney ? '#EA580C' : '#6B7280';
        html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:8px;margin-bottom:4px">
            <span style="font-size:0.875rem;font-weight:600;color:#374151">${netLabel}</span>
            <span style="font-size:1.05rem;font-weight:800;color:${netColor}">${sym}${realNetAmount.toFixed(2)}</span>
        </div>`;

        document.getElementById('addon-edit-refund-breakdown').innerHTML = html;

        // Reset form state
        document.querySelectorAll('input[name="addon_edit_refund_type"]').forEach(r => r.checked = false);
        const firstRadio = document.getElementById('addon-edit-ticket-refund');
        if (firstRadio) firstRadio.checked = true;
        const notesEl = document.getElementById('addon-edit-refund-notes');
        if (notesEl) notesEl.value = '';

        // Toggle Refund Type selection vs. the plain informational notice, and adjust the
        // header/button copy to match — nothing to pick when no real money is moving.
        const typeSection = document.getElementById('addon-edit-refund-type-section');
        const infoOnly    = document.getElementById('addon-edit-refund-info-only');
        const titleEl     = document.getElementById('addon-edit-refund-title');
        const subtitleEl  = document.getElementById('addon-edit-refund-subtitle');
        const submitBtnEl = document.getElementById('addon-edit-refund-submit');
        if (typeSection) typeSection.classList.toggle('hidden', !hasRealMoney);
        if (infoOnly)    infoOnly.classList.toggle('hidden', hasRealMoney);
        if (titleEl)     titleEl.textContent = hasRealMoney ? 'Refund to Customer' : 'Update Add-on';
        if (subtitleEl)  subtitleEl.textContent = hasRealMoney
            ? 'The changes result in a credit due to the customer'
            : 'No payment has been collected for this add-on — nothing will be refunded';
        if (submitBtnEl) submitBtnEl.textContent = hasRealMoney ? 'Process Refund' : 'Confirm Change';

        // Store payload
        this._pendingAddonPayload = payload;

        // Wire submit button
        const btn = document.getElementById('addon-edit-refund-submit');
        const fresh = btn.cloneNode(true);
        btn.parentNode.replaceChild(fresh, btn);
        fresh.addEventListener('click', () => {
            // No real money involved → nothing to pick; this is functionally a "don't refund"
            // (nothing was ever collected to refund), which is also exactly what produces the
            // correct "$X not refunded" history line on the backend (see processAddonFinancialTransactions()).
            const refundType = hasRealMoney
                ? document.querySelector('input[name="addon_edit_refund_type"]:checked')?.value
                : 'dont_refund';
            const note = document.getElementById('addon-edit-refund-notes')?.value?.trim() || '';
            if (hasRealMoney && !refundType) {
                swal('Required', 'Please select a refund type.', 'warning');
                return;
            }
            $('#modal_addon_edit_refund').modal('hide');
            if (onSubmit) {
                onSubmit(payload, refundType, note);
            } else {
                this._submitAddonEditRefund(payload, refundType, note);
            }
        });

        $('#modal_addon_edit_refund').modal('show');
    }

    async _submitAddonEditRefund(payload, refundType, note) {
        const enriched = {
            ...payload,
            financial: {
                ...payload.financial,
                payment_method: this.bookingPaymentVia,
                refund_type:    refundType,
                refund_note:    note
            }
        };
        try {
            this.showLoading();
            const resp = await fetch(this.updateBookingAddonsUrl, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(enriched)
            });
            const data = await resp.json();
            this.hideLoading();
            if (data.success) {
                swal('Refund Processed', 'Add-ons updated and refund issued.', 'success').then(() => location.reload());
            } else {
                swal('Error', data.message || 'Something went wrong.', 'error');
            }
        } catch (err) {
            this.hideLoading();
            console.error('Addon refund submit error:', err);
            swal('Error', 'Failed to process refund.', 'error');
        }
    }

    /** --------------------------------------------------
    * updateAddonForm() — validates, builds payload, routes
    * -------------------------------------------------- */
    async updateAddonForm(e) {
        e.preventDefault();

        if (this.isCancelled) {
            swal('Booking Cancelled', 'This booking has been cancelled — add-ons can no longer be added or updated.', 'error');
            return;
        }

        const { isValid, hasRequired } = this.addonValidation();
        if (hasRequired && !isValid) {
            swal('Add-ons Required', 'Please complete all required add-on fields before saving.', 'warning');
            return;
        }

        const payload = this.buildAddonPayload();
        const { action } = payload.financial;

        if (action === 'none') {
            await this._submitAddonSave(payload);
            return;
        }

        if (action === 'charge') {
            this._showChargeModal(payload);
            return;
        }

        if (action === 'refund') {
            this._showRefundModal(payload);
        }
    }

    async _submitAddonSave(payload) {
        try {
            this.showLoading();
            const resp = await fetch(this.updateBookingAddonsUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                body: JSON.stringify(payload)
            });
            const data = await resp.json();
            this.hideLoading();
            if (data.success) {
                swal('Saved', 'Add-ons updated successfully.', 'success').then(() => location.reload());
            } else {
                swal('Error', data.message || 'Something went wrong.', 'error');
            }
        } catch (err) {
            this.hideLoading();
            console.error('Error saving addons:', err);
            swal('Error', 'Failed to save add-ons. Please try again.', 'error');
        }
    }

    addonValidation() {
        let isValid = true;
        let hasRequired = false;

        document.querySelectorAll('.addons-content .addon-fields').forEach(otherEl => {
            this.addonsFields.forEach(f => {
                const result = Components.validateContainer(otherEl, f);
                if (!result.isValid) isValid = false;
                if (result.hasRequired) hasRequired = true;
            });
        });

        return { isValid, hasRequired };
    }

    cancelAddonForm() {
        this.listAddonsContainer.show();
        this.addonsFormContainer.html("");
    }

    onError() {
        // Remove loading...
        this.listAddonsContainer.html(" ");
    }

    renderTable(id, body = "", header = "", footer = "") {
        return `<table id="${id}" class='table table-sm table-stripe table-hover w-full'>
            <thead>${header}</thead>
            <tbody>${body}</tbody>
            <tfoot>${footer}</tfoot>
        </table>`;
    }

    renderHeader() {
        return `<tr>
            <th class="th-custom-css">Name</th>
            <th class="th-custom-css">Value</th>
            <th class="th-custom-css" style="text-align:center;">Type</th>
            <th class="th-custom-css" style="text-align:right;">Price</th>
            <th class="th-custom-css" style="text-align:right;">Fee</th>
            <th class="th-custom-css" style="text-align:right;">Total</th>
        </tr>`;
    }

    renderFooter() {
        if (managerRequestEnableRefundAddons == "Yes") {
            return `
                <tr> 
                    <td class="td-custom-css text-end" colspan="100%">
                       <button class="edit-addons-form btn btn-sm btn-primary-small">
                       <i class="fas fa-pencil-alt pl-1"></i> Edit 
                       </button>
                    </td>
                </tr>`;
        }
    }

    renderHtmlField(field) {
        const hasChildren = field.children && field.children.length > 0;

        return `
            <tr class="addon-row" data-id="${field.id}" data-has-children="${hasChildren}">
                <td class="td-custom-css addon-label">${this.renderTitle(field.field_label)}${hasChildren ? `<span class="addon-arrow" style="display:inline-flex;align-items:center;justify-content:center;margin-left:8px;width:22px;height:22px;background:#EFF6FF;color:#2563EB;border-radius:5px;font-size:13px;vertical-align:middle;flex-shrink:0;cursor:pointer;">▾</span>` : ""}</td>
                <td class="td-custom-css addon-value">${this.renderValue(field)}</td>
                ${this.renderPriceCols(field)}
            </tr>

            ${hasChildren ? this.renderNestedRows(field.children, 1, field.id) : ""}
        `;
    }
   
    renderNestedRows(children, level = 1, parentId) {
        let html = "";

        children.forEach(ch => {
            const hasChildren = ch.children && ch.children.length > 0;

            html += `
                <tr class="nested-row"
                    data-id="${ch.id}"
                    data-parent="${parentId}"
                    data-level="${level}"
                    style="--level:${level};">

                    <td class="td-custom-css" style="padding-left: calc(${level} * 20px);">
                        <span style="color:#9CA3AF;margin-right:5px;">└</span>${this.renderTitle(ch.field_label)}${hasChildren ? `<span class="addon-arrow" style="display:inline-flex;align-items:center;justify-content:center;margin-left:8px;width:22px;height:22px;background:#EFF6FF;color:#2563EB;border-radius:5px;font-size:13px;vertical-align:middle;flex-shrink:0;cursor:pointer;">▾</span>` : ""}
                    </td>
                    <td class="td-custom-css">${this.renderValue(ch)}</td>
                    ${this.renderPriceCols(ch)}
                </tr>
            `;

            if (hasChildren) {
                html += this.renderNestedRows(ch.children, level + 1, ch.id);
            }
        });

        return html;
    }

    renderTitle(s) {
        return s.toString().replace("_", " ").replace("-", " ").replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
    }

    renderValue(field) {
        // 1) Boolean / checkbox
        if (field.value === true || field.value === "true" || field.value === "checked") return "Yes";
        if (field.value === false || field.value === "false" || field.value === "not_checked") return "No";

        // 2) Radio or Dropdown → always show label
        if (field.option_label) {
            return field.option_label;
        }
        if (field.option_meta && field.option_meta.label) {
            return field.option_meta.label;
        }

        // 3) Normal text/number fields
        return field.value ?? "";
    }

    _getUnitPrice(field) {
        // Prefer the actual charged price from the transaction
        if (field.transaction) {
            let base = (field.transaction.field_price || 0) + (field.transaction.option_price || 0);
            if (base > 0) {
                // field_price stores the TOTAL for price_per_unit addons (unit_price × qty).
                // Normalise to per-unit so renderPriceCols can multiply by qty correctly.
                // field.unit may be 0/null for records inserted via AddonBookingService — fall back to field.value.
                if (field.unit_type === 'price_per_unit') {
                    const qty = this._parseQty(field.unit, this._parseQty(field.value, 1));
                    if (qty > 1) base = base / qty;
                }
                return base;
            }
        }
        const hasFee = field.option_meta?.additional_fee || field.field_meta?.additional_fee;
        if (!hasFee) return null;
        const price = field.option_meta?.price ?? field.field_meta?.price ?? null;
        return price !== null ? parseFloat(price) || 0 : null;
    }

    _getFee(field) {
        if (!field.transaction) return null;
        const applyFee = this.bookingPaymentVia === 'Card'
            || (this.bookingPaymentVia === 'Cash' && this.isCashFees);
        if (!applyFee) return null;
        // Read the fee actually charged/stored rather than recomputing it from the current
        // fee % — the backend rounds per-unit then multiplies by qty (e.g. $0.29 × 2 =
        // $0.58), which can differ by a cent from rounding on the total (e.g. $6.00 × 9.82%
        // = $0.59), and recomputing would also silently use today's rate instead of the
        // rate that actually applied when this item was charged.
        const fee = (field.transaction.field_fee || 0) + (field.transaction.option_fee || 0);
        return fee > 0 ? fee : null;
    }

    _formatMoney(amount) {
        const sym = (typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$');
        return `${sym}${parseFloat(amount).toFixed(2)}`;
    }

    _computeAddonsSummary(addons, retainedFeesFromDeletedAddons = 0) {
        let baseCost  = 0;
        let totalFees = 0;
        if (!addons) return { baseCost: 0, totalFees: 0, grandTotal: 0 };
        // Walk the full field tree, not just the top level — a priced child/grandchild
        // (e.g. "Camera Rental Type" under an unpriced "Need Camera Equipment?" toggle)
        // must still be counted even though its parent has no price of its own.
        const visit = (field) => {
            const unitPrice = this._getUnitPrice(field);
            if (unitPrice !== null && unitPrice > 0) {
                const qty = (field.unit_type === 'price_per_unit') ? this._parseQty(field.unit, this._parseQty(field.value, 1)) : 1;
                baseCost  += unitPrice * qty;
                totalFees += this._getFee(field) || 0;
            } else {
                // Price is zero/null (e.g. ticket-cost-only refund left a zero-price active record).
                // Include any stored retained fee directly from the transaction.
                const retainedFee = parseFloat(field.transaction?.field_fee) || 0;
                if (retainedFee > 0) {
                    totalFees += retainedFee;
                }
            }
            if (field.children && field.children.length) {
                field.children.forEach(visit);
            }
        };
        for (const field of addons) {
            visit(field);
        }
        // Add retained fees from hard-deleted addon fields (ticket_refund / dont_refund).
        // The PHP query finds orphaned transactions by tour_booking_id and returns the net sum.
        totalFees += retainedFeesFromDeletedAddons;

        baseCost  = Math.round(baseCost  * 100) / 100;
        totalFees = Math.round(totalFees * 100) / 100;
        return { baseCost, totalFees, grandTotal: Math.round((baseCost + totalFees) * 100) / 100 };
    }

    _renderAddonsSummaryHeader(summary, sym) {
        if (summary.grandTotal <= 0) return '';
        const chip = (label, val, accent) => {
            const bg = accent ? '#EFF6FF' : '#F8FAFC';
            const bc = accent ? '#BFDBFE' : '#E2E8F0';
            const lc = accent ? '#1D4ED8' : '#64748B';
            const vc = accent ? '#1e40af' : '#1e293b';
            return `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;` +
                `border-radius:9999px;border:1px solid ${bc};background:${bg};font-size:0.78rem;vertical-align:middle;">` +
                `<span style="color:${lc};font-weight:500;">${label}</span>` +
                `<span style="color:${vc};font-weight:700;">${sym}${val.toFixed(2)}</span>` +
                `</span>`;
        };
        const sep = `<span style="color:#94A3B8;font-size:0.8rem;vertical-align:middle;margin:0 3px;">+</span>`;
        const eq  = `<span style="color:#94A3B8;font-size:0.8rem;vertical-align:middle;margin:0 3px;">=</span>`;
        if (summary.totalFees > 0) {
            return ` &nbsp;` + chip('Cost', summary.baseCost, false) + sep + chip('Fees', summary.totalFees, false) + eq + chip('Total', summary.grandTotal, true);
        }
        return ` &nbsp;` + chip('Total', summary.grandTotal, true);
    }

    renderPriceCols(field) {
        const unitPrice = this._getUnitPrice(field);
        // No fee when there is no price — net position is zero (fully refunded/downgraded)
        const fee = unitPrice !== null ? this._getFee(field) : null;
        const isPerUnit = field.unit_type === 'price_per_unit';
        const qty = isPerUnit ? this._parseQty(field.unit) : null;
        const baseTotal = unitPrice !== null ? (qty !== null ? unitPrice * qty : unitPrice) : null;
        const grandTotal = baseTotal !== null ? baseTotal + (fee || 0) : null;

        const typeLabels = {
            'one_time':       { text: 'One Time', bg: '#EFF6FF', color: '#1D4ED8' },
            'price_per_unit': { text: 'Per Unit',  bg: '#F0FDF4', color: '#15803D' },
            'price_per_pax':  { text: 'Per Pax',   bg: '#FFF7ED', color: '#C2410C' },
        };
        const typeInfo = typeLabels[field.unit_type];
        const typeBadge = typeInfo
            ? `<span style="display:inline-block;padding:2px 8px;border-radius:9999px;font-size:0.75em;font-weight:600;background:${typeInfo.bg};color:${typeInfo.color};">${typeInfo.text}</span>`
            : '—';

        const paymentMethod = field.transaction?.payment_method;
        const feeLabel = fee !== null
            ? `<span title="${paymentMethod ? paymentMethod + ' fee' : 'processing fee'}">${this._formatMoney(fee)}</span>`
            : '—';

        return `
            <td class="td-custom-css" style="text-align:center;">${typeBadge}</td>
            <td class="td-custom-css" style="text-align:right;">${baseTotal !== null ? this._formatMoney(baseTotal) : '—'}</td>
            <td class="td-custom-css" style="text-align:right;color:#6B7280;">${feeLabel}</td>
            <td class="td-custom-css" style="text-align:right;font-weight:600;">${grandTotal !== null ? this._formatMoney(grandTotal) : '—'}</td>
        `;
    }

    renderLoading() {
        return `<div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div>`;
    }

    prefillSelections(paxUid = null) {
        if (!this.bookingAddonsState || !Array.isArray(this.bookingAddonsState.fields)) return;
        const hasAddonsContainer = this.addonsFormContainer && this.addonsFormContainer.length;
        const hasPaxAddonsContainer = this.paxAddonsFormContainer && this.paxAddonsFormContainer.length;

        if (!hasAddonsContainer && !hasPaxAddonsContainer) return;

        const savedNodes = this.bookingAddonsState.fields;

        let $scope;

        // ---- Pax-wise addons ----
        if (paxUid) {
            if (!hasPaxAddonsContainer) return;

            $scope = this.paxAddonsFormContainer.find(
                `.pax-wrapper[data-pax-index="${paxUid}"]`
            );

            if (!$scope.length) {
                console.warn('Pax wrapper not found:', paxUid);
                return;
            }
        }
        // ---- Normal addons ----
        else {
            if (!hasAddonsContainer) return;

            $scope = this.addonsFormContainer;

            if (!$scope?.length) return;
        }

        const applyNode = (node, $parentEl = null) => {
            if (!node) return;

            const $nodeEl = ($parentEl ?? $scope)
                .find(`.addon-card[data-node-id="${node.field_id}"]`)
                .first();

            if (!$nodeEl.length) return;

            // Always hide indent area before applying value
            $nodeEl.find(".indent").hide();

            const type = node.field_meta?.type;

            switch (type) {
                case "radio": {
                    const match = node.option_id ?? node.value;
                    const $input = $nodeEl.find(
                        `input[type="radio"][data-option-id="${match}"], 
                        input[type="radio"][value="${match}"]`
                    );
                    if ($input.length) {
                        // SET WITHOUT TRIGGER
                        $input.prop("checked", true);
                    }
                    break;
                }

                case "checkbox": {
                    const $input = $nodeEl.find(`.form-check input[type="checkbox"]`);
                    if ($input.length) {
                        const isChecked =
                            node.value === "checked" ||
                            node.value === true ||
                            node.value === "1";

                        // SET WITHOUT TRIGGER
                        $input.prop("checked", isChecked).val(isChecked ? "checked" : "");
                    }
                    break;
                }

                case "number": {
                    const $input = $nodeEl.find(`input[type="number"]`);
                    if ($input.length) $input.val(node.value);
                    break;
                }

                case "text": {
                    const $input = $nodeEl.find(`input[type="text"]`);
                    if ($input.length) $input.val(node.value);
                    break;
                }

                case "textbox": {
                    const $ta = $nodeEl.find("textarea");
                    if ($ta.length) $ta.val(node.value);
                    break;
                }

                case "dropdown":
                case "select": {
                    const $sel = $nodeEl.find("select");
                    if ($sel.length) {
                        let done = false;

                        if (node.option_id != null) {
                            const $opt = $sel.find(
                                `option[data-option-id="${node.option_id}"]`
                            );
                            if ($opt.length) {
                                $opt.prop("selected", true);
                                done = true;
                            }
                        }

                        if (!done) {
                            $sel.val(node.value ?? "");
                        }
                    }
                    break;
                }
            }

            // Recursively apply children, scoped inside this card element.
            if (node.children?.length) {
                node.children.forEach(child => applyNode(child, $nodeEl));
            }
        };

        savedNodes.forEach(node => applyNode(node));
    }

    showLoading() {
        $('#loading-container').css('display', 'block');
    }

    hideLoading() {
        $('#loading-container').css('display', 'none');
    }
}

const tourBookingAddons = new TourBookingAddons({
    admin:        userType,
    packageId:    packageId,
    bookingId:    bookingId,
    tourSlotId:   tourSlotId,
    paymentVia:   (typeof paymentMethod          !== 'undefined' ? paymentMethod          : 'Pay Later'),
    natFeePct:    (typeof natServiceCommission !== 'undefined' ? natServiceCommission : 0),
    isCashFees:   (typeof isCashFees             !== 'undefined' ? isCashFees             : '0'),
    isCancelled:  (typeof bookingIsCancelled     !== 'undefined' ? bookingIsCancelled     : false),
});