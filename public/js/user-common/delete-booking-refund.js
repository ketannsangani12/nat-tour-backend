// "Update Booking" modal (#deleteBooking) — live tour/add-ons/total breakdown. Tour and add-on
// amounts are precomputed server-side per PaymentIntent (see TourBookingRepository::booking()'s
// $deleteBookingTransactionData) and embedded once as JSON; this only does cheap lookups/
// arithmetic over that data as the admin changes refund type or picks a different transaction —
// it never re-derives the backend's actual refund math (permit fees, partner fees, discounts),
// so the preview can never drift from something this simple recalculation doesn't understand.
// Tour and add-on figures are kept in separate variables end-to-end for the refund preview
// (Block 4's Tour/Add-ons/Total rows) — the one exception is Block 2's own per-card badge, which
// intentionally folds a PaymentIntent's tour + add-on money together to show the card's true
// balance; that merge is display-only and never feeds back into the refund math below.
(function () {
    function readDeleteBookingTransactionData() {
        var el = document.getElementById('delete-booking-transaction-data');
        if (!el) {
            return {};
        }
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return {};
        }
    }

    // Never-paid ("Pending") priced add-ons — see
    // DeleteBookingAddonBreakdownService::attachPending()'s docblock for why these must be
    // merged into the SAME list getAllAddonItems() returns (not just shown separately): their
    // field ids need to flow through to #delete_booking_addon_scope_ids exactly like paid items
    // do, or a pax whose only priced add-on was pending gets left orphaned by deletePaxAddons().
    function readPendingAddonItems() {
        var el = document.getElementById('delete-booking-pending-addon-data');
        if (!el) {
            return [];
        }
        try {
            return JSON.parse(el.textContent || '[]');
        } catch (e) {
            return [];
        }
    }

    function formatMoney(amount) {
        var symbol = (typeof currencyFormation !== 'undefined' && currencyFormation.symbol) ? currencyFormation.symbol : '$';
        return symbol + (Math.round((amount + Number.EPSILON) * 100) / 100).toFixed(2);
    }

    // Ticket Refund keeps the fee; 100% Refund includes it; Don't Refund and Partial Refund
    // (tour side only — see below) don't derive from this table.
    function previewForRefundType(refundType, ticket, fee) {
        if (refundType === '100_refund') {
            return ticket + fee;
        }
        if (refundType === 'full_refund') {
            return ticket;
        }
        return 0;
    }

    function currentRefundType() {
        return $('#deleteBooking input[name="refund_type"]:checked').val() || 'full_refund';
    }

    function highlightSelectedOption(groupSelector, optionSelector) {
        $(groupSelector).each(function () {
            var $option = $(this).closest(optionSelector);
            if ($(this).is(':checked')) {
                $option.css({ 'border-color': '#3B82F6', 'background-color': '#EFF6FF' });
            } else {
                $option.css({ 'border-color': '#E5E7EB', 'background-color': '' });
            }
        });
    }

    // Remembers which specific add-on ids are checked, so switching the selected tour
    // transaction or refund type back and forth doesn't forget what was picked. Seeded
    // UNCHECKED the first time an item is shown — the admin must explicitly opt in to
    // refunding each add-on rather than everything being refunded by default.
    var addonSelection = {};

    // #delete-booking-transaction-data is rendered once, server-side, at page load and never
    // changes for the life of the page — so the parsed JSON, the flattened+sorted item list, and
    // "who is being removed" are all cached per modal-open (reset on show.bs.modal below) instead
    // of being re-parsed/re-derived on every keystroke/click that triggers a recalculation.
    var cachedTransactionData = null;
    var cachedAllAddonItems = null;
    var cachedRemovalScope = null;

    function selectedAddonIds(addonItems) {
        addonItems.forEach(function (item) {
            if (!(item.id in addonSelection)) {
                addonSelection[item.id] = false;
            }
        });
        return addonSelection;
    }

    function writeSelectedAddonFieldIdsInput(selectionMap) {
        var selectedIds = Object.keys(selectionMap || {}).filter(function (id) {
            return selectionMap[id];
        });
        $('#selected_addon_field_ids').val(JSON.stringify(selectedIds.map(Number)));
    }

    // Every add-on field id in scope for the person(s) being removed (checked or not — this is
    // the DELETION scope, distinct from selectedAddonFieldIds' REFUND-only subset). Populated
    // ONLY for 'personKeys' scope — the direct "Edit Booking" delete flow, which no longer needs
    // the "Passenger Add-ons" modal to resolve which fields are in scope, since .ads_Checkbox
    // already carries the exact pax_id + pax_index this modal's own add-ons block matches
    // against. Left empty for 'exact' (Change People V2) and 'paxIds' (split-people) scope so
    // those flows keep using pax_addons_ids + the "Passenger Add-ons" modal exactly as before.
    function writeAddonScopeFieldIdsInput(addonItems, scope) {
        var $input = $('#delete_booking_addon_scope_ids');
        if ($input.length === 0) {
            return;
        }
        if (scope.mode !== 'personKeys') {
            $input.val('');
            return;
        }
        var ids = addonItems.map(function (item) { return item.id; });
        $input.val(JSON.stringify(ids));
    }

    // Every add-on line item across every PaymentIntent on this booking, flattened — Block 3 is
    // no longer scoped to whichever transaction is radio-selected in Block 2 (each rate group's
    // add-ons can live under a different PaymentIntent than another rate group's, so scoping by
    // transaction hid, e.g., a child's add-ons whenever an adult's transaction was the one
    // selected). Each transaction's own items already arrive pre-sorted by rate group order (see
    // booking()), but merging several transactions back together can interleave them out of
    // order again — re-sort by the same (rate_order, pax_index) key so rate groups always read
    // in their real display order (e.g. Adult before Child) regardless of which transaction
    // they're on.
    function getAllAddonItems(transactionData) {
        var items = [];
        Object.keys(transactionData).forEach(function (txNumber) {
            (transactionData[txNumber].addonItems || []).forEach(function (item) {
                items.push(item);
            });
        });
        readPendingAddonItems().forEach(function (item) {
            items.push(item);
        });
        items.sort(function (a, b) {
            if (a.rate_order !== b.rate_order) {
                return a.rate_order - b.rate_order;
            }
            return a.pax_index - b.pax_index;
        });
        return items;
    }

    // Change People V2 already knows EXACTLY which priced add-on field ids are being removed —
    // the admin picked the specific person to remove in the pax-addons modal, and that choice is
    // recorded as db_ids in changePeopleV2's own state. Reading it directly here is unambiguous
    // (no guessing which "Adult 1"/"Adult 2" label matches which removed person), so this is
    // always tried FIRST, before falling back to any rate-group-level approximation.
    function getExactRemovedAddonFieldIds() {
        if (typeof changePeopleV2 === 'undefined' || typeof changePeopleV2.getAddonsPayload !== 'function') {
            return null;
        }
        try {
            if (typeof changePeopleV2.collectPaxAddonSelections === 'function') {
                changePeopleV2.collectPaxAddonSelections();
            }
            var payload = changePeopleV2.getAddonsPayload();
            return (payload.removed_addons || [])
                .map(function (a) { return Number(a.db_id); })
                .filter(function (id) { return !isNaN(id); });
        } catch (e) {
            return null;
        }
    }

    function uniqueValues(list) {
        return Array.from(new Set(list));
    }

    // Who the admin is actually removing right now, so Block 3 only shows/selects add-ons for
    // those specific people — e.g. removing only "Adult 2" must not show "Adult 1"'s add-ons even
    // though they're in the same rate group. A discriminated union (one `mode` + one `ids` list)
    // rather than three always-present arrays, since exactly one of the three sources below is
    // ever populated for a given submission:
    // 1) 'exact' — Change People V2's own exact removed-field-id list (see above) — precise,
    //    preferred whenever available. This is ALSO precise (and must be trusted as-is) when it
    //    resolves to a genuinely empty list: removing people who simply have no add-ons at all
    //    is a perfectly normal outcome, not a sign that V2's state was unavailable — falling
    //    through to the rate-group approximation below in that case wrongly resurfaces some OTHER
    //    person's add-ons in the same rate group (e.g. removing add-on-less "Child 4/5/6" while
    //    "Child 1/2/3" — not being removed — happen to have the Photography Package) instead of
    //    correctly showing no add-ons at all.
    // 2) 'personKeys' — Flow 1 (direct "Edit Booking" delete): each checked person row carries
    //    its own pax_id + pax_index, giving exact per-person precision.
    // 3) 'paxIds' — Flow 2 (older Change People table, no V2 state): only "this rate group's
    //    count went down by N" is known, not which specific person — falls back to showing every
    //    person in the affected rate group(s).
    function getRemovalScope() {
        // getExactRemovedAddonFieldIds() returns null ONLY when V2's state genuinely can't be
        // read (changePeopleV2 missing, or collecting/reading its state threw) — never for "zero
        // add-ons on the removed people", which is a real array, just an empty one. Checking
        // `.length > 0` here treated that legitimate empty answer as "unavailable" and fell
        // through to the imprecise per-rate-group fallback below, which has no concept of WHICH
        // person is being removed and so happily shows a different, still-present person's
        // add-ons instead. Only `null` should trigger the fallback.
        var exactFieldIds = getExactRemovedAddonFieldIds();
        if (exactFieldIds !== null) {
            return { mode: 'exact', ids: exactFieldIds };
        }

        var personKeys = [];
        var paxIds = [];
        $('.ads_Checkbox:checked').each(function () {
            var paxId = $(this).attr('data-pax-id');
            var paxIndex = $(this).attr('data-pax-index');
            if (!paxId) {
                return;
            }
            paxIds.push(Number(paxId));
            personKeys.push(paxId + '_' + paxIndex);
        });
        personKeys = uniqueValues(personKeys);
        if (personKeys.length > 0) {
            return { mode: 'personKeys', ids: personKeys };
        }

        var deletedRateGroupIds = (typeof totalDeletedRateGroupIds !== 'undefined') ? totalDeletedRateGroupIds : [];
        var rateGroupMap = (typeof rateGroupIdToPaxId !== 'undefined') ? rateGroupIdToPaxId : {};
        var fallbackPaxIds = uniqueValues(
            deletedRateGroupIds
                .map(function (rateGroupId) { return rateGroupMap[String(rateGroupId)]; })
                .filter(function (paxId) { return paxId !== undefined; })
                .map(Number)
        );
        return { mode: 'paxIds', ids: fallbackPaxIds };
    }

    // Group Rate bookings price the WHOLE rate group as a single fixed amount per headcount
    // tier (e.g. 1-3 pax = $280.90, 4 pax = $307.40, 5-6 pax = $339.20) rather than a flat
    // per-pax rate — so no static dollar amount can be assigned to an individual checkbox that
    // also sums correctly for any combination of checked people. The refund for removing N
    // people from a group always equals (price for the CURRENT headcount) minus (price for
    // headcount - N), which depends only on how many are checked, never on which ones. Looks
    // up the tier whose [min, max] range contains the given headcount; returns 0 for a
    // headcount of 0 or below every tier's min (nobody left in the group => nothing left to
    // charge for it, so removing everyone refunds the group's full current price).
    function lookupGroupRatePrice(tierTable, headcount) {
        if (headcount <= 0) {
            return 0;
        }
        for (var i = 0; i < tierTable.length; i++) {
            if (headcount >= tierTable[i].min && headcount <= tierTable[i].max) {
                return tierTable[i].price;
            }
        }
        return 0;
    }

    // Reads the group-rate metadata every checkbox in an affected rate group carries
    // identically (data-group-tier-table / data-group-original-tourists /
    // data-group-fee-percentage — see tour-booking-info.blade.php), and returns the correct
    // ticket/fee delta for removing `checkedCount` people from that ONE group — see
    // lookupGroupRatePrice() above for why this can't be a per-checkbox sum. Rounds the same
    // way roundout() elsewhere in this codebase does (this file is loaded standalone, so it
    // doesn't assume that global exists).
    function groupRateDelta($representativeRow, checkedCount) {
        var tierTable;
        try {
            tierTable = JSON.parse($representativeRow.attr('data-group-tier-table') || '[]');
        } catch (e) {
            tierTable = [];
        }
        var originalTourists = parseInt($representativeRow.attr('data-group-original-tourists'), 10) || 0;
        var feePercentage = parseFloat($representativeRow.attr('data-group-fee-percentage')) || 0;

        var currentPrice = lookupGroupRatePrice(tierTable, originalTourists);
        var newPrice = lookupGroupRatePrice(tierTable, originalTourists - checkedCount);
        var ticketDelta = Math.round((currentPrice - newPrice) * 100) / 100;
        var feeDelta = Math.round(ticketDelta * feePercentage) / 100; // (delta * pct/100), then round to cents

        return { ticket: ticketDelta, fee: feeDelta };
    }

    // Sums the checked people's OWN ticket/fee amounts (Flow 1 — direct "Edit Booking" delete;
    // see getRemovalScope() above for why Flow 2 has no such rows to read). Used so the Tour
    // preview reflects only the people actually being removed, not the whole PaymentIntent's
    // outstanding balance — a booking can have several people on one card charge, and removing
    // just one of them must not preview refunding everyone else's ticket too.
    // Group-rate checkboxes are handled separately (see groupRateDelta() above): they're
    // counted per rate group (data-pax-id) instead of summed individually, since a tiered
    // group rate's refund depends on how MANY people are being removed from the group, not on
    // treating each removed person as if they carried their own independent static share.
    // Returns null when no checked row carries these attributes (Flow 2, or Flow 1 rows from a
    // page render that predates this data), so the caller can fall back to the old behavior.
    function sumCheckedPaxTicketAndFee() {
        var ticket = 0, fee = 0, found = false;
        var groupRateCheckedByPaxId = {};

        $('.ads_Checkbox:checked').each(function () {
            var $cb = $(this);
            if (typeof $cb.attr('data-ticket-amount') === 'undefined') {
                return;
            }
            found = true;
            if ($cb.attr('data-is-group-rate') === '1') {
                var paxId = $cb.attr('data-pax-id');
                if (!groupRateCheckedByPaxId[paxId]) {
                    groupRateCheckedByPaxId[paxId] = { count: 0, $row: $cb };
                }
                groupRateCheckedByPaxId[paxId].count++;
                return;
            }
            ticket += parseFloat($cb.attr('data-ticket-amount')) || 0;
            fee += parseFloat($cb.attr('data-fee-amount')) || 0;
        });

        Object.keys(groupRateCheckedByPaxId).forEach(function (paxId) {
            var entry = groupRateCheckedByPaxId[paxId];
            var delta = groupRateDelta(entry.$row, entry.count);
            ticket += delta.ticket;
            fee += delta.fee;
        });

        return found ? { ticket: ticket, fee: fee } : null;
    }

    // Flow 2 (Change People) fallback — no .ads_Checkbox rows are checked (the same-named
    // click handler explicitly unchecks every one of them before showing this modal, precisely
    // to stop this flow from being mistaken for Flow 1's), so sumCheckedPaxTicketAndFee() above
    // always returns null here. What IS known is how many people are being removed FROM EACH
    // rate group (totalDeletedCountByRateGroupId) and which pax_id that rate group maps to
    // (rateGroupIdToPaxId) — read that many of THAT rate group's own .ads_Checkbox rows' own
    // ticket/fee data attributes (same ones Flow 1 reads, still present in the DOM just
    // unchecked) as a representative sample. This is uniform across a rate group's people
    // except in the rare case of an earlier PER-PERSON permit-fee refund override — an accepted
    // approximation for a live preview, same imprecision getRemovalScope()'s own 'paxIds' mode
    // already documents ("not which specific person") for Block 3's add-ons scoping.
    function sumDeletedRateGroupTicketAndFee() {
        var deletedCountByRateGroupId = (typeof totalDeletedCountByRateGroupId !== 'undefined') ? totalDeletedCountByRateGroupId : {};
        var rateGroupMap = (typeof rateGroupIdToPaxId !== 'undefined') ? rateGroupIdToPaxId : {};
        var ticket = 0, fee = 0, found = false;

        Object.keys(deletedCountByRateGroupId).forEach(function (rateGroupId) {
            var count = deletedCountByRateGroupId[rateGroupId];
            if (!count) {
                return;
            }
            var paxId = rateGroupMap[String(rateGroupId)];
            if (paxId === undefined) {
                return;
            }

            var $rows = $('.ads_Checkbox[data-pax-id="' + paxId + '"]').filter(function () {
                return typeof $(this).attr('data-ticket-amount') !== 'undefined';
            });
            if ($rows.length === 0) {
                return;
            }

            // Group-rate rate group: the refund depends on how many are being removed from
            // it (`count`), not on summing `count` individual rows' own static shares — see
            // groupRateDelta() above.
            if ($rows.first().attr('data-is-group-rate') === '1') {
                found = true;
                var delta = groupRateDelta($rows.first(), count);
                ticket += delta.ticket;
                fee += delta.fee;
                return;
            }

            var take = Math.min(count, $rows.length);
            for (var i = 0; i < take; i++) {
                found = true;
                ticket += parseFloat($rows.eq(i).attr('data-ticket-amount')) || 0;
                fee += parseFloat($rows.eq(i).attr('data-fee-amount')) || 0;
            }
        });

        return found ? { ticket: ticket, fee: fee } : null;
    }

    function addonItemsForRemovalScope(allAddonItems, scope) {
        switch (scope.mode) {
            case 'exact':
                return allAddonItems.filter(function (item) {
                    return scope.ids.indexOf(item.id) !== -1;
                });
            case 'personKeys':
                return allAddonItems.filter(function (item) {
                    return scope.ids.indexOf(item.pax_id + '_' + item.pax_index) !== -1;
                });
            case 'paxIds':
                return scope.ids.length > 0
                    ? allAddonItems.filter(function (item) { return scope.ids.indexOf(item.pax_id) !== -1; })
                    : allAddonItems; // defensive fallback only — normal flows always resolve above
            default:
                return allAddonItems;
        }
    }

    function recalcDeleteBookingBreakdown() {
        var $modal = $('#deleteBooking');
        // The JSON payload (#delete-booking-transaction-data) is present for Card AND Cash — only
        // Block 2's picker (#delete-booking-tour-transactions) is Card-only, since Cash has no
        // PaymentIntent to choose between. Gate on the payload itself, not the Card-only picker,
        // or Cash bookings would hit the old early-return and never see their add-ons block.
        if ($('#delete-booking-transaction-data').length === 0) {
            return; // split-payment bookings never render blocks 2-4
        }
        var hasTourTransactionPicker = $modal.find('#delete-booking-tour-transactions').length > 0;

        if (!cachedTransactionData) {
            cachedTransactionData = readDeleteBookingTransactionData();
            cachedAllAddonItems = getAllAddonItems(cachedTransactionData);
        }
        var transactionData = cachedTransactionData;
        var refundType = currentRefundType();
        var selectedTxNumber = $modal.find('.check_transactions:checked').val();
        var txInfo = selectedTxNumber ? transactionData[selectedTxNumber] : null;
        var isPartial = refundType === 'partial_refund';

        highlightSelectedOption('#deleteBooking .Refcheck', '.refund-type-option');
        highlightSelectedOption('#deleteBooking .check_transactions', '.transaction-option');

        // Block 2 — each card's own outstanding balance, exactly as it is on that PaymentIntent.
        // NOT scaled by refund type: this number answers "how much is currently on this card,"
        // a fact of the account, not a preview of what THIS refund will move — Block 4's Tour
        // row already covers the refund-type-scaled preview. Recomputing it per refund type
        // (previous behavior) made a fixed, factual balance appear to shrink to $0 when "Don't
        // Refund" was picked, which reads as "the card doesn't have that money," when it does.
        // Includes addonAmount — any priced add-ons charged on this SAME PaymentIntent (e.g. a
        // booking-time charge that billed ticket + add-ons together in one Stripe PI) — so the
        // badge reads as the card's true total instead of understating it to the tour-only
        // portion. Block 3's own checkboxes / Block 4's breakdown are unaffected by this; it's
        // purely informational here. An "Add-ons only" entry (a PaymentIntent with no tour-side
        // charge at all) has outstandingTicket/outstandingFee pinned to 0 on purpose — that money
        // must never leak into Block 4's Tour row math — so its badge instead uses addonOnlyTotal,
        // the actual balance sitting on that card, computed server-side from its own add-on items.
        $modal.find('#delete-booking-tour-transactions .transaction-option').each(function () {
            var txNumber = $(this).find('.check_transactions').val();
            var info = transactionData[txNumber];
            if (!info) {
                return;
            }
            var amount = info.addonOnlyTotal != null ? info.addonOnlyTotal : (info.outstandingTicket + info.outstandingFee + (info.addonAmount || 0));
            $(this).find('.tour-transaction-amount').text(formatMoney(amount));
        });

        // Flow 1 (direct "Edit Booking" delete, Card OR Cash) knows exactly which people are
        // checked for removal and their own ticket/fee amounts — prefer that over txInfo's whole-
        // PaymentIntent outstanding balance, which could cover people who AREN'T being removed at
        // all (e.g. 3 people share one card charge, only 1 is being deleted). Flow 2 (Change
        // People) has no checked rows to read that way, so it falls back to the deleted-count ×
        // per-rate-group approximation — still scoped to the people actually being removed,
        // unlike txInfo's whole-transaction balance.
        var checkedPaxTotals = sumCheckedPaxTicketAndFee() || sumDeletedRateGroupTicketAndFee();

        var tourAmount = 0;
        if (isPartial) {
            // Partial Refund is a manually-typed amount that only ever applies to the tour side —
            // the backend never routes it to add-ons (dont_refund mapping for the addon settlement
            // on this refund type), so the breakdown must say so plainly rather than implying
            // add-on money moves too. This just echoes back what the admin typed, so — unlike the
            // other refund types below — it never needed txInfo/checkedPaxTotals to begin with.
            tourAmount = parseFloat($('#partial_refund_value_field2').val()) || 0;
        } else if (checkedPaxTotals) {
            tourAmount = previewForRefundType(refundType, checkedPaxTotals.ticket, checkedPaxTotals.fee);
        } else if (txInfo) {
            tourAmount = previewForRefundType(refundType, txInfo.outstandingTicket, txInfo.outstandingFee);
        }

        // Block 3 — every add-on tied to a person the admin is removing, regardless of
        // which PaymentIntent it belongs to or which one is radio-selected in Block 2;
        // each independently selectable, only checked ones count toward the refund
        // (unchecked ones are still removed, just without moving money back).
        if (!cachedRemovalScope) {
            cachedRemovalScope = getRemovalScope();
        }
        var addonItems = addonItemsForRemovalScope(cachedAllAddonItems, cachedRemovalScope);
        var selectionMap = selectedAddonIds(addonItems);

        var addonAmount = 0;
        if (!isPartial) {
            addonItems.forEach(function (item) {
                if (selectionMap[item.id]) {
                    addonAmount += previewForRefundType(refundType, item.price, item.fee);
                }
            });
        }

        var $addonsBlock = $('#delete-booking-addons-block');
        var $addonsList = $('#delete-booking-addons-list');
        $addonsList.empty();
        if (addonItems.length > 0) {
            addonItems.forEach(function (item) {
                var $row = $('<label class="flex items-start gap-2 px-2.5 py-2 border border-[#FDE68A] bg-[#FFFBEB] rounded-lg cursor-pointer"></label>');
                var $text = $('<div class="min-w-0 flex-1"></div>')
                    .append($('<div class="text-xs font-semibold text-[#111827] leading-tight"></div>').text(item.person_label || 'Person'))
                    .append($('<div class="text-[11px] text-[#92400E] leading-tight mt-0.5 truncate"></div>').text(item.label));

                if (item.pending) {
                    // Never paid — nothing to select/refund, but its own field id is still part
                    // of `addonItems` (see getAllAddonItems()), so it still reaches
                    // #delete_booking_addon_scope_ids below and gets cleaned up on submit
                    // (deletePaxAddons() removes it; its pending payment-link item is then
                    // cancelled by PaymentLinkService::cancelPendingAddonLinkItemsForBooking()).
                    $row.removeClass('cursor-pointer');
                    var $badge = $('<span class="text-[10px] font-semibold text-[#92400E] bg-[#FEF3C7] border border-[#FDE68A] rounded-full px-1.5 py-0.5 whitespace-nowrap"></span>').text('Pending — will be cancelled');
                    $row.append($text).append($badge);
                    $addonsList.append($row);
                    return;
                }

                var isChecked = !!selectionMap[item.id];
                var itemAmount = (isPartial || !isChecked) ? 0 : previewForRefundType(refundType, item.price, item.fee);
                var $checkbox = $('<input type="checkbox" class="delete-booking-addon-checkbox w-4 h-4 flex-shrink-0 mt-0.5">')
                    .prop('checked', isChecked)
                    .prop('disabled', isPartial)
                    .attr('data-addon-id', item.id)
                    // Read by the #button_id submit validation (outside this closure) to
                    // check, per rate group, that no more distinct people are selected
                    // here than are actually being removed from that rate group.
                    .attr('data-pax-id', item.pax_id)
                    .attr('data-pax-index', item.pax_index);
                var $amount = $('<div class="text-xs font-semibold text-[#111827] whitespace-nowrap"></div>').text(formatMoney(itemAmount));
                $row.append($checkbox).append($text).append($amount);
                $addonsList.append($row);
            });
            $addonsBlock.prop('hidden', false);
        } else {
            $addonsBlock.prop('hidden', true);
        }
        writeSelectedAddonFieldIdsInput(selectionMap);
        writeAddonScopeFieldIdsInput(addonItems, cachedRemovalScope);

        // Block 4 — breakdown summary, tour and add-ons always shown separately. The "Tour" row
        // is hidden only when there's truly no source for it: neither checkedPaxTotals (Flow 1's
        // checked rows, or Flow 2's deleted-count-per-rate-group fallback) NOR a Block 2 picker to
        // fall back to — a permanent, un-selectable "$0.00" there would wrongly read as "no tour
        // refund happens," when the tour side is actually refunded separately via the Refund Type
        // radios in Block 1. In practice this only leaves Cash bookings with no deleted people at
        // all hitting this hidden state.
        $('#delete-booking-breakdown-tour-row').prop('hidden', !checkedPaxTotals && !hasTourTransactionPicker);
        $('#delete-booking-breakdown-tour').text(formatMoney(tourAmount));
        $('#delete-booking-breakdown-addons').text(formatMoney(addonAmount));
        $('#delete-booking-breakdown-total').text(formatMoney(tourAmount + addonAmount));
        $('#delete-booking-partial-note').prop('hidden', !isPartial);
    }

    $(document).on('change', '#deleteBooking .Refcheck, #deleteBooking .check_transactions', recalcDeleteBookingBreakdown);
    $(document).on('input', '#partial_refund_value_field2', recalcDeleteBookingBreakdown);
    $(document).on('change', '#delete-booking-addons-list .delete-booking-addon-checkbox', function () {
        addonSelection[$(this).attr('data-addon-id')] = $(this).is(':checked');
        recalcDeleteBookingBreakdown();
    });
    $('#deleteBooking').on('show.bs.modal shown.bs.modal', function () {
        addonSelection = {};
        cachedTransactionData = null;
        cachedAllAddonItems = null;
        cachedRemovalScope = null;
        recalcDeleteBookingBreakdown();
    });
})();
