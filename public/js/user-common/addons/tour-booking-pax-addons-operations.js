class TourBookingPaxAddonsOperations { 
    
    /**
     * This is called from the template blade tour booking details view to delete pax addons for the booking
     * @param {*} props 
     * @returns 
     */

    constructor(props){
        this.props = props;
        this.admin = ( props.admin === 'Admin' ) ? '/admin' : '';
        this.bookingId = props.bookingId;
        this.paxAddonsModal = $('#paxAddonsListModal');
        this.selectedPaxData = {};
        this.paxAddonsData = null;
        this.bookingOperation = '';
        this.splitBookingEd = null;
        this._paxDeleteProcessing = false;
        this._paxRefundTotals = { price: 0, fee: 0 };
        
        if (props.admin === 'Admin') {
            this.admin = "/admin/tour-operator";
        } else if (props.admin === 'Tour Operator') {
            this.admin = "/tour-operator";
        } else if (props.admin === 'Affiliate') {
            this.admin = "/affiliate";
        } else {
            this.admin = "";
        }
        
        this.getBookingPaxAddonsUrl = `${this.admin}/booking/get-booking-pax-addons`;
        this.deleteOnlyPaxAddonsUrl = `${this.admin}/booking/delete-pax-addons`;

        // add events
        this.eventListeners();
    }

    eventListeners() {
        $(document).off("change", ".pax-addon-checkbox").on("change", ".pax-addon-checkbox", this.paxAddonValidation.bind(this));
        $(document).off("click", ".delete-or-split-pax-addons").on("click", ".delete-or-split-pax-addons", this.deleteOrSplitPaxAddons.bind(this));
        $(document).off("click", "#delete-only-pax-addons").on("click", "#delete-only-pax-addons", this.handleDeleteOnlyPaxAddons.bind(this));
    }

    getBookingPaxAddons(rateGroupIds, bookingOperation, splitBookingEd = null) {
        const selectionCountByGroup = rateGroupIds.reduce((acc, id) => {
            acc[id] = (acc[id] || 0) + 1;
            return acc;
        }, {});
        this.selectedPaxData = selectionCountByGroup;
        this.bookingOperation = bookingOperation;
        this.splitBookingEd = splitBookingEd;

        showLoading();

        return $.ajax({
            url: this.getBookingPaxAddonsUrl,
            method: "POST",
            dataType: "json",
            data: {
                booking_id: this.bookingId,
                rate_groups: rateGroupIds,
                _token: $('meta[name="csrf-token"]').attr('content')
            }
        })
        .done((response) => {
            hideLoading();
            if(response.length > 0) {
                // Early exit → no pax inside a single response item
                if (response.length === 1 && (!response[0].pax || response[0].pax.length === 0)) {
                    editBookingDeletePeople();
                    return;
                }
                $(".delete-or-split-pax-addons").removeClass('d-none');
                if ($('#edit_booking').hasClass('show')) {
                    $('#edit_booking').one('hidden.bs.modal', () => {
                        this.renderPaxAddonsModal(response, 1);
                    });
                    $('#edit_booking').modal('hide');
                } else {
                    this.renderPaxAddonsModal(response, 1);
                }
            }
        })
        .fail((jqXHR, textStatus, errorThrown) => {
            console.error("Error fetching Pax Addons:", textStatus, errorThrown);
        });
    }

    renderPaxAddonsModal(data, isSplitOrDelete = 0) {
        this.paxAddonsData = data;
        const container = $('.pax-addons-list');
        // container.html("");
        container.html(`<form id="paxAddonsForm"></form>`);
        const form = container.find("#paxAddonsForm");

        // if (isSplitOrDelete === 1) {
            form.append(`
                <div class="mb-3 px-3 pb-2 border-b border-gray-100">
                    <div class="flex items-center justify-between">
                        <!-- Left Side -->
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" id="pax-addon-select-all" 
                                data-pax-addon-operation="${
                                isSplitOrDelete === 1 ? 'pax_with_addon' : 'addon_only'
                            }" class="w-5 h-5 cursor-pointer">
                            <span class="text-sm font-bold text-gray-700">All</span>
                        </label>
                        <!-- Right Side -->
                        ${isSplitOrDelete === 0 ? `
                            <button type="button" id="delete-only-pax-addons" style="display: none;"
                                    class="btn btn-danger btn-danger-small delete-only-pax-addons">
                                Delete Add-on(s)
                            </button>
                        ` : ''}
                    </div>
                </div>
            `);
        // }

        // Track the pending addon link item ID for the delete flow.
        this._pendingAddonLinkItemId = null;
        data.forEach(group => {
            (group.pax || []).forEach(pax => {
                if (pax.pending_link_item_id && !this._pendingAddonLinkItemId) {
                    this._pendingAddonLinkItemId = pax.pending_link_item_id;
                }
            });
        });

        data.forEach(group => {
            if(group.pax.length > 0) {
                const wrapper = $(`
                    <div class="rate-group">
                        <div class="rate-group-pax-list"></div>
                    </div>
                `);

                // container.append(wrapper);
                form.append(wrapper);

                const rateGroupContainer = wrapper.find('.rate-group-pax-list');

                const cs = (typeof currencyFormation !== 'undefined') ? currencyFormation.symbol : '$';

                group.pax.forEach((pax, index) => {
                    const rows = this.renderAddonTableRows(pax.tree, 0, null, cs);

                    const chargedTotal = (pax.charged_price || 0) + (pax.charged_fee || 0);
                    const subtotalHtml = chargedTotal > 0
                        ? `<div class="-mx-3 mt-3 px-4 py-2.5 border-t border-[#E5E7EB] bg-[#F9FAFB] flex items-center justify-between rounded-b-md" style="margin-bottom:-12px">
                               <span class="text-xs font-semibold uppercase tracking-wide" style="color:#6B7280">Charged Total</span>
                               <span class="text-sm font-bold" style="color:#111827">${cs}${chargedTotal.toFixed(2)}</span>
                           </div>`
                        : '';

                    const treeHasPrice = this._treeHasPrice(pax.tree);
                    // A $0 selection (e.g. "No Thank You") can still have a paid transaction
                    // row recorded — require a nonzero charged total before showing "Paid",
                    // so a free selection reads as "No Charge" instead.
                    const statusBadge = (pax.has_paid_transaction && chargedTotal > 0)
                        ? `<span class="inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full" style="color:#166534;background:#BBF7D0">
                               <svg width="10" height="10" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#166534" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                               Paid
                           </span>`
                        : treeHasPrice
                            ? `<span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full" style="color:#92400E;background:#FEF3C7">Pending</span>`
                            : `<span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded-full" style="color:#6B7280;background:#F3F4F6">No Charge</span>`;

                    const paxBlock = `
                        <div class="pax-addon-block border rounded-md mb-4 px-3 pb-3 pt-0 bg-white">
                            <div class="flex items-center justify-between gap-3 bg-[#DCFCE7] -mx-3 -mt-3 px-3 py-2 rounded-t-md">
                                <label class="flex items-center gap-2 text-base cursor-pointer select-none">
                                    <input type="checkbox"
                                        class="pax-addon-checkbox w-5 h-5 cursor-pointer"
                                        data-pax-addon-operation="${
                                            isSplitOrDelete === 1 ? 'pax_with_addon' : 'addon_only'
                                        }"
                                        data-rate-group-id="${group.rate_group_id}"
                                        data-rate-group-name="${group.rate_for}"
                                        data-pax-id="${pax.pax_id}"
                                        data-pax-index="${pax.pax_index}"
                                        data-has-paid-txn="${pax.has_paid_transaction ? '1' : '0'}"
                                        data-has-pending-link="${pax.pending_link_item_id ? '1' : '0'}">
                                    <span class="pax-label cursor-pointer text-[#166534] font-semibold">${group.rate_for}</span>
                                </label>
                                <div class="flex items-center gap-2">
                                    ${statusBadge}
                                    ${isSplitOrDelete == 0 ? `
                                        <button type="button" class="edit-pax-addons text-gray-400 hover:text-blue-600 transition" title="Edit">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>` : ``}
                                </div>
                            </div>
                            <div class="addon-rows mt-2">
                                ${rows}
                            </div>
                            ${subtotalHtml}
                        </div>
                    `;

                    rateGroupContainer.append(paxBlock);
                });
            }
        });

        // Important: bind toggle logic AFTER table is inserted
        this.bindAddonToggleEvents();
        this.bindSelectAll();

        this.paxAddonsModal.modal('show');
    }

    paxAddonValidation(e, silent = false) {
        const checkbox = $(e.target);
        const groupId = checkbox.data('rate-group-id');
        const groupName = checkbox.data('rate-group-name');
        const operation = checkbox.data('pax-addon-operation');

        // If operation is addon_only, we exit here and skip all logic below
        if (operation == 'addon_only') {
            return true; 
        }

        const groupData = this.paxAddonsData.find(g => g.rate_group_id == groupId);
        if (!groupData) return true;

        const totalTouristsPerRate = groupData.total_tourists;
        const addonCountPerRate = groupData.pax.length;
        const selectedPaxCount = this.selectedPaxData[groupId] || 0;
        const remaining = totalTouristsPerRate - selectedPaxCount;

        const requiredAddonUpdates = Math.max(0, addonCountPerRate - remaining);

        const checkedCount = this.paxAddonsModal.find(
            `.pax-addon-checkbox[data-rate-group-id="${groupId}"]:checked`
        ).length;

        if (requiredAddonUpdates === 0) {
            return true;
        }

        // DO NOT allow selecting more than required addon deletes
        if (checkedCount > requiredAddonUpdates) {
            checkbox.prop('checked', false);
            // Uncheck "Select All" because this individual check was rejected
            $('#pax-addon-select-all').prop('checked', false);
            if (!silent) {
                swal(`You can select only ${requiredAddonUpdates} add-on(s) in ${groupName}.`, '', 'warning');
            }
            return false;
            // swal(`You can select only ${requiredAddonUpdates} add-on(s) in ${groupName}.`, '', 'warning');
            // return false;
        }

        return true;
    }

    deleteOrSplitPaxAddons() {
        for (const groupId in this.selectedPaxData) {

            const selectedPaxCount = this.selectedPaxData[groupId]; // requested deletes/selection for this rate group

            const groupData = this.paxAddonsData.find(g => String(g.rate_group_id) === String(groupId));
            if (!groupData) continue;

            const totalTouristsPerRate = groupData.total_tourists;      // total pax in this rate group
            const addonCountPerRate = groupData.pax.length;          // pax that have addons
            const groupName = groupData.rate_for || 'this group';

            // How many addon pax user actually selected to delete/split (X)
            const checkedAddonCount = this.paxAddonsModal.find(
                `.pax-addon-checkbox[data-rate-group-id="${groupId}"]:checked`
            ).length;

            // How many pax add-on(s) MUST be selected to keep addons consistent
            const remainingPaxAfterSelection = totalTouristsPerRate - selectedPaxCount; // this is fixed by request
            const requiredAddonUpdates = Math.max(0, addonCountPerRate - remainingPaxAfterSelection);

            // CASE 1: Rule: user must select at least requiredAddonUpdates addon pax
            if (checkedAddonCount < requiredAddonUpdates) {
                const needMore = requiredAddonUpdates - checkedAddonCount;

                swal(
                    `Please select ${needMore} add-on(s) to delete in ${groupName}.`,
                    '',
                    'warning'
                );
                return false;
            }

            // CASE 2: User selected too many (The new safety net for "Select All")
            if (checkedAddonCount > requiredAddonUpdates) {
                swal(`You have selected too many add-ons in ${groupName}. Max allowed: ${requiredAddonUpdates}.`, '', 'warning');
                return false;
            }
        }

        // If we reach here → all groups valid
        const selectedPaxIndexes = this.paxAddonsModal.find('.pax-addon-checkbox:checked')
            .map(function() {
                return $(this).data('pax-index');
            })
            .get();

        $("#pax_addons_ids").val(JSON.stringify(selectedPaxIndexes));

        this.paxAddonsModal.modal('hide');
        
        if(this.bookingOperation === 'deletePeople') {
            editBookingDeletePeople();
        }
        if(this.bookingOperation === 'splitPeople') {
            splitPeopleBooking(this.splitBookingEd);
        }

        return true;
    }

    _treeHasPrice(nodes) {
        return (nodes || []).some(node => {
            const { price, fee } = this._nodePrice(node);
            return price > 0 || fee > 0 || this._treeHasPrice(node.children);
        });
    }

    _nodePrice(node) {
        const paid  = node.paid_price || 0;
        const fee   = node.paid_fee   || 0;
        if (paid > 0 || fee > 0) return { price: paid, fee, charged: true };

        // Fallback to list price for uncharged addons
        const optPrice   = (node.option_meta?.additional_fee && parseFloat(node.option_meta?.price || 0) > 0)
            ? parseFloat(node.option_meta.price) : 0;
        const fieldPrice = (!optPrice && node.field_meta?.additional_fee && parseFloat(node.field_meta?.price || 0) > 0)
            ? parseFloat(node.field_meta.price) : 0;
        const raw = optPrice || fieldPrice;
        const qty = (node.unit_type === 'price_per_unit' && node.unit > 1) ? node.unit : 1;
        return { price: raw * qty, fee: 0, charged: false };
    }

    _priceHtml(node, cs) {
        const { price, fee, charged } = this._nodePrice(node);
        if (price <= 0 && fee <= 0) return '';
        const priceStyle = charged ? 'color:#111827' : 'color:#9CA3AF';
        return `<div class="text-right flex-shrink-0 ml-3">
                    <div class="text-sm font-semibold" style="${priceStyle}">${cs}${price.toFixed(2)}</div>
                    ${fee > 0 ? `<div class="text-xs mt-0.5" style="color:#6B7280">+ ${cs}${fee.toFixed(2)} fee</div>` : ''}
                    ${!charged && price > 0 ? `<div class="text-xs mt-0.5" style="color:#9CA3AF">list price</div>` : ''}
                </div>`;
    }

    renderAddonTableRows(nodes, depth = 0, parentId = null, cs = '$') {
        let html = "";

        nodes.forEach(node => {
            const id = node.field_id ?? ('fld_' + Math.random().toString(36).slice(2,9));
            const hasChildren = node.children && node.children.length > 0;
            const displayValue = this.getAddonDisplayValue(node);

            html += `
                <div class="addon-parent border border-[#E5E7EB] rounded-lg overflow-hidden mt-2 bg-white"
                    data-id="${id}"
                    data-parent-id="${parentId ?? ''}"
                    data-tour-booking-addon-id="${node.id}">
                    <div class="flex items-start justify-between gap-2 px-3 py-2.5">
                        <div class="flex-1 min-w-0">
                            <div class="addon-label text-sm font-medium leading-tight" style="color:#111827">${node.field_label}</div>
                            ${displayValue ? `<div class="mt-1.5">
                                <span class="addon-value-cell inline-block py-0.5 px-2.5 text-xs font-semibold whitespace-nowrap rounded-full" style="color:#166534;background:#DCFCE7;border:1px solid #BBF7D0">
                                    ${displayValue}
                                </span>
                            </div>` : ''}
                        </div>
                        ${this._priceHtml(node, cs)}
                    </div>`;

            if (hasChildren) {
                node.children.forEach(child => {
                    html += this.renderChildRow(child, depth + 1, node.id, cs);
                });
            }

            html += `</div>`;
        });

        return html;
    }

    renderChildRow(node, depth, parentId, cs = '$') {
        const id = node.field_id ?? ('fld_' + Math.random().toString(36).slice(2,9));
        const hasChildren = node.children && node.children.length > 0;
        const displayValue = this.getAddonDisplayValue(node);

        let html = `
            <div class="addon-child"
                data-id="${id}"
                data-parent-id="${parentId}">
                <div class="flex items-start justify-between gap-2 px-3 py-2" style="padding-left:28px;border-top:1px solid #F3F4F6;background:#FAFAFA">
                    <div class="flex-1 min-w-0" style="padding-left:10px;border-left:2px solid #E5E7EB">
                        <div class="addon-label text-sm font-medium leading-tight" style="color:#374151">${node.field_label}</div>
                        ${displayValue ? `<div class="mt-1.5">
                            <span class="addon-value-cell inline-block py-0.5 px-2.5 text-xs font-semibold whitespace-nowrap rounded-full" style="color:#166534;background:#DCFCE7;border:1px solid #BBF7D0">
                                ${displayValue}
                            </span>
                        </div>` : ''}
                    </div>
                    ${this._priceHtml(node, cs)}
                </div>
            </div>
        `;

        if (hasChildren) {
            node.children.forEach(child => {
                html += this.renderChildRow(child, depth + 1, id, cs);
            });
        }

        return html;
    }

    getAddonDisplayValue(node) {
        const type = node.field_meta?.type;
        const value = node.value;

        if (!value && !node.option_label) return '';

        switch (type) {
            case 'dropdown':
            case 'radio':
                return node.option_label 
                    ?? node.option_meta?.label
                    ?? value;

            case 'checkbox':
                // value usually "checked"
                return value === 'checked' ? 'Yes' : 'No';

            case 'number':
            case 'text':
            case 'textarea':
                return value;

            default:
                return value;
        }
    }

    bindAddonToggleEvents() {
        // Toggle on parent row click
        this.paxAddonsModal.off('click', '.addon-parent').on('click', '.addon-parent', function (e) {

            // ignore if clicking on an input in future
            if ($(e.target).is('input, select, textarea')) return;

            const row = $(this);
            // const id = row.data('id');
            const id = row.data('tour-booking-addon-id');
            const arrow = row.find('.addon-arrow');
            const table = row.closest('.addon-table'); // scope ✔

            const children = table.find(`tr[data-parent-id="${id}"]`); // ✔ correct relation

            // const children = $(`tr[data-parent-id="${id}"]`);
            if (!children.length) return;

            const isClosed = children.first().hasClass('hidden');

            if (isClosed) {
                // expand
                children.each(function () {
                    $(this).removeClass('hidden').slideDown(150);
                });
                arrow.css('transform', 'rotate(180deg)');
            } else {
                // collapse all descendants
                const allDesc = findDescendants(id);
                allDesc.each(function () {
                    $(this).slideUp(120, () => $(this).addClass('hidden'));
                });
                arrow.css('transform', 'rotate(0deg)');
            }
        });

        // Helper: recursively hide nested children
        function findDescendants(rootId) {
            return $(`tr[data-parent-id="${rootId}"]`).add(
                $(`tr[data-parent-id="${rootId}"]`).map(function () {
                    return findDescendants($(this).data('id')).get();
                })
            );
        }
    }

    bindSelectAll() {
        const self = this;

        // Handle "Select All" click
        this.paxAddonsModal
            .off("change", "#pax-addon-select-all")
            .on("change", "#pax-addon-select-all", function () {
                const isChecked = $(this).is(":checked");
                const operation = $(this).data('pax-addon-operation');
                self.paxAddonsModal.find(".pax-addon-checkbox").prop("checked", isChecked);

                // Handle Button Visibility
                if (operation === 'addon_only') {
                    if (isChecked) {
                        $("#delete-only-pax-addons").show();
                    } else {
                        $("#delete-only-pax-addons").hide();
                    }
                }
                // Find all individual pax checkboxes
                // const checkboxes = self.paxAddonsModal.find(".pax-addon-checkbox");

                // checkboxes.each(function () {
                //     const cb = $(this);
                //     // Only change if different to avoid redundant validation triggers
                //     if (cb.prop("checked") !== isChecked) {
                //         cb.prop("checked", isChecked).trigger("change");
                //     }
                // });
            });

        // Sync "Select All" state if user manually unchecks an individual item
        this.paxAddonsModal.off("change.sync").on("change.sync", ".pax-addon-checkbox", function() {
            const total = self.paxAddonsModal.find(".pax-addon-checkbox").length;
            const checked = self.paxAddonsModal.find(".pax-addon-checkbox:checked").length;

            $('#pax-addon-select-all').prop('checked', total > 0 && total === checked);

            // Hide button if no checkboxes are active
            if (checked === 0) {
                $("#delete-only-pax-addons").hide();
            } else {
                // Show button if at least one is checked (and it's addon_only mode)
                if ($(this).data('pax-addon-operation') === 'addon_only') {
                    $("#delete-only-pax-addons").show();
                }
            }
        });
    }

    handleDeleteOnlyPaxAddons() {
        const checkedBoxes = this.paxAddonsModal.find('.pax-addon-checkbox:checked');
        const selectedPaxIndexes = checkedBoxes.map(function() {
            return $(this).data('pax-index');
        }).get();

        if (selectedPaxIndexes.length === 0) {
            swal("Please select at least one add-on to remove.", "", "warning");
            return;
        }

        const hasPaidTxn      = checkedBoxes.toArray().some(el => $(el).data('has-paid-txn') == '1');
        const hasPendingLink  = checkedBoxes.toArray().some(el => $(el).data('has-pending-link') == '1');
        const pendingItemId   = this._pendingAddonLinkItemId;

        if (!hasPaidTxn && hasPendingLink) {
            // Addon was sent via payment link but customer hasn't paid yet — mirror edit-booking delete behaviour.
            swal({
                title: "Remove Add-on(s)?",
                text: "The selected add-on(s) have a pending payment link that has not been paid yet. Removing them will cancel or update the payment link. No refund will be issued.",
                icon: "warning",
                buttons: { no: { text: 'No', value: false }, yes: { text: 'Yes, Remove', className: 'btn-danger' } },
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    this._submitDeletePaxAddons(selectedPaxIndexes, 'dont_refund', null, pendingItemId);
                }
            });
            return;
        }

        if (hasPaidTxn) {
            const $refundModal = $('#modal_pax_addon_delete_refund');

            // Reset state
            $('input[name="pax_delete_refund_type"]').prop('checked', false);
            $('#pax-delete-refund-notes').val('');
            $('#pax-refund-summary-bar').addClass('hidden');
            $('.pdr-option').css({ 'border-color': '', 'background-color': '' });

            // Populate breakdown and refund amounts
            $('#pax-delete-refund-breakdown').html(this._buildRefundBreakdown(selectedPaxIndexes));

            this._paxDeleteProcessing = false;

            // Hide the pax list modal, then show the refund modal once it's fully gone.
            // backdrop:false prevents Bootstrap from injecting its own backdrop div
            // (which would conflict with our z-index:2000 inline style).
            const showRefundModal = () => {
                $refundModal.modal({ backdrop: false, keyboard: true });
                $refundModal.modal('show');
            };

            if (this.paxAddonsModal.hasClass('show')) {
                this.paxAddonsModal.one('hidden.bs.modal', showRefundModal);
                this.paxAddonsModal.modal('hide');
            } else {
                showRefundModal();
            }

            // Re-open pax modal when refund modal is cancelled (not when confirmed)
            $refundModal.off('hidden.bs.modal.pdr').on('hidden.bs.modal.pdr', () => {
                if (!this._paxDeleteProcessing) {
                    this.paxAddonsModal.modal('show');
                }
            });

            // Radio change — highlight selected option and update summary bar
            $refundModal.off('change.pdr').on('change.pdr', 'input[name="pax_delete_refund_type"]', (e) => {
                const cs = (typeof currencyFormation !== 'undefined') ? currencyFormation.symbol : '$';
                const val = e.target.value;
                const t = this._paxRefundTotals || { price: 0, fee: 0 };
                const refundAmt = val === 'full_refund' ? (t.price + t.fee)
                                : val === 'ticket_refund' ? t.price
                                : 0;

                // Highlight selection
                $('.pdr-option').css({ 'border-color': '#E5E7EB', 'background-color': '' });
                $(e.target).closest('.pdr-option').css({ 'border-color': '#3B82F6', 'background-color': '#EFF6FF' });

                // Summary bar
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

            // Submit
            $('#pax-addon-delete-refund-submit').off('click.pdr').on('click.pdr', () => {
                const refundType = $('input[name="pax_delete_refund_type"]:checked').val();
                if (!refundType) {
                    swal("Please select a refund type.", "", "warning");
                    return;
                }
                const description = $('#pax-delete-refund-notes').val().trim() || null;
                this._paxDeleteProcessing = true;
                $refundModal.modal('hide');
                this._submitDeletePaxAddons(selectedPaxIndexes, refundType, description, pendingItemId);
            });

        } else {
            swal({
                title: "Are you sure?",
                text: "This will remove the selected add-on(s) from this booking.",
                icon: "warning",
                buttons: { no: { text: 'No', value: false }, yes: { text: 'Yes', className: 'btn-danger' } },
                dangerMode: true,
            }).then((willDelete) => {
                if (willDelete) {
                    this._submitDeletePaxAddons(selectedPaxIndexes, 'dont_refund', null);
                }
            });
        }
    }

    _buildRefundBreakdown(selectedPaxIndexes) {
        const cs = (typeof currencyFormation !== 'undefined') ? currencyFormation.symbol : '$';
        const groups = this.paxAddonsData || [];
        let totalPrice = 0;
        let totalFee   = 0;
        let rowsHtml   = '';

        for (const group of groups) {
            for (const pax of (group.pax || [])) {
                if (!selectedPaxIndexes.includes(pax.pax_index)) continue;
                if (!pax.has_paid_transaction) continue;

                const price = parseFloat(pax.charged_price || 0);
                const fee   = parseFloat(pax.charged_fee   || 0);
                totalPrice += price;
                totalFee   += fee;

                const ordinal = (pax.person_ordinal !== null && pax.person_ordinal !== undefined)
                    ? ` #${parseInt(pax.person_ordinal) + 1}` : '';
                const addonNames = (pax.tree || []).map(n => n.field_label).filter(Boolean).join(', ') || 'Add-on';

                rowsHtml += `
                    <div class="flex items-start justify-between py-2.5 border-b border-[#F3F4F6] last:border-0">
                        <div class="flex-1 min-w-0 pr-3">
                            <div class="text-xs font-semibold text-[#374151] uppercase tracking-wide leading-tight mb-0.5">${group.rate_for || 'Passenger'}${ordinal}</div>
                            <div class="text-sm text-[#111827] leading-snug truncate">${addonNames}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-semibold text-[#111827]">${cs}${price.toFixed(2)}</div>
                            ${fee > 0 ? `<div class="text-xs text-[#9CA3AF]">fee ${cs}${fee.toFixed(2)}</div>` : ''}
                        </div>
                    </div>`;
            }
        }

        if (!rowsHtml) return '';

        const grand = totalPrice + totalFee;
        this._paxRefundTotals = { price: totalPrice, fee: totalFee };

        // Push amounts into the radio option labels
        $('#pax-refund-ticket-amount').text(cs + totalPrice.toFixed(2));
        $('#pax-refund-full-amount').text(cs + grand.toFixed(2));

        return `<div class="rounded-xl border border-[#E5E7EB] overflow-hidden mb-1">
            <div class="px-4 py-2.5 flex items-center justify-between" style="background:#F8FAFC;border-bottom:1px solid #E5E7EB">
                <span class="text-xs font-semibold text-[#6B7280] uppercase tracking-wide">Add-ons to remove</span>
                <span class="text-xs text-[#9CA3AF]">${selectedPaxIndexes.length} passenger${selectedPaxIndexes.length > 1 ? 's' : ''}</span>
            </div>
            <div class="bg-white px-4 divide-y divide-[#F9FAFB]">${rowsHtml}</div>
            <div class="px-4 py-3 flex items-center justify-between" style="background:#F8FAFC;border-top:1px solid #E5E7EB">
                <span class="text-xs font-semibold text-[#374151]">Total originally charged</span>
                <span class="text-sm font-bold text-[#111827]">${cs}${grand.toFixed(2)}</span>
            </div>
        </div>`;
    }

    _submitDeletePaxAddons(selectedPaxIndexes, refundType, description, pendingLinkItemId = null) {
        showLoading();
        const data = {
            booking_id:  this.bookingId,
            rate_groups: selectedPaxIndexes,
            refund_type: refundType,
            _token:      $('meta[name="csrf-token"]').attr('content')
        };
        if (description) data.description = description;
        if (pendingLinkItemId) data.pending_link_item_id = pendingLinkItemId;

        $.ajax({
            url:      this.deleteOnlyPaxAddonsUrl,
            method:   "POST",
            dataType: "json",
            data:     data
        })
        .done((response) => {
            hideLoading();
            if (response === true) {
                swal("Done!", "The add-ons have been removed.", "success")
                    .then(() => { location.reload(); });
            } else {
                swal("Error", response.message || "Failed to remove add-ons.", "error");
            }
        })
        .fail((jqXHR, textStatus, errorThrown) => {
            hideLoading();
            console.error("Error removing add-ons:", textStatus, errorThrown);
            swal("Error", "A server error occurred.", "error");
        });
    }
}

const tourBookingPaxAddonsOperations = new TourBookingPaxAddonsOperations({ admin: userType, bookingId: bookingId });