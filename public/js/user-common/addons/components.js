class Components {
    // ---------------------------------------
    // COMMON: currency formatter
    // ---------------------------------------
    static get currencyFormation() {
        return typeof currencyFormation !== 'undefined' ? currencyFormation : { symbol: '', format: (a) => a };
    }

    static buildId(nodeId, paxUid) {
        return paxUid == null ? `${nodeId}` : `${nodeId}_${paxUid}`;
    }

    static buildOptionId(nodeId, paxUid, index) {
        return paxUid == null
            ? `${nodeId}_${index}`
            : `${nodeId}_${paxUid}_${index}`;
    }

    // ---------------------------------------
    // renderField()
    // ---------------------------------------
    static renderField(node, paxUid = null) {
        // wrapper
        const holder = Components.createFieldWrapper(node);

        // label (skip for checkboxes)
        if (node.type !== 'checkbox') {
            const labelHtml = Components.createLabel(node);
            holder.insertAdjacentHTML("beforeend", labelHtml);
        }

        // input field (HTML builder)
        const fieldHtml = Components.buildFieldByType(node, paxUid);
        holder.insertAdjacentHTML("beforeend", fieldHtml);

        // additional fee (if enabled)
        if (Number(node.additional_fee) === 1) {
            const addFeeHtml = Components.createAdditionalFee(node);
            holder.insertAdjacentHTML("beforeend", addFeeHtml);
        }

        // RULE CHILDREN (recursive)
        Components.appendRuleChildren(holder, node, paxUid);

        return holder;
    }

    // ---------------------------------------------------------
    // FIELD WRAPPER
    // ---------------------------------------------------------
    static createFieldWrapper(node) {
        const wrapper = document.createElement("div");
        wrapper.className = "addon-card fade-in border border-[#E5E7EB] rounded-lg overflow-hidden mt-3 bg-[#F9FAFB] p-3";
        wrapper.dataset.nodeId = node.id;
        wrapper.dataset.fieldId = node.id;
        wrapper.dataset.label = node.label || '';
        wrapper.dataset.fee = node.price || 0;
        wrapper.dataset.additionalFee = node.additional_fee || 0;
        wrapper.dataset.type = node.type || '';
        wrapper.dataset.unitType = node.unit_type || 'price_per_pax';
        wrapper.dataset.displayOnce = node.display_once ? 1 : 0;
        if (node.parent_id) {
            wrapper.dataset.parentId = node.parent_id;
        }
        if (node.rule_id) {
            wrapper.dataset.ruleId = node.rule_id;
        }
        if (node.applied_rule_snapshot) {
            wrapper.dataset.appliedRuleSnapshot = JSON.stringify(node.applied_rule_snapshot);
        }
        if (node.pax_id) {
            wrapper.dataset.paxId = node.pax_id;
        }
        return wrapper;
    }

    // ---------------------------------------------------------
    // LABEL + DESCRIPTION ICON
    // ---------------------------------------------------------
    static createLabel(node) {
        // Show a price badge in the header for fields that have a fixed fee on the field itself.
        // Radio/checkbox handle their own price display inline, per-option, and are always
        // fully visible, so they don't need a label badge too.
        // Dropdown is different: it's a native <select>, so only the CURRENTLY selected
        // option's text (and its own dropdown-price-hint, once chosen) is visible — a user
        // looking at "No" selected has no way to tell "Yes" costs anything until they open
        // the list. createDropdownPriceBadge() covers that: it looks at every option up
        // front, not just node's own field-level price, and only renders anything for
        // dropdowns that actually have at least one priced option.
        const showBadge = Number(node.additional_fee) === 1
            && parseFloat(node.price) > 0
            && !['radio', 'dropdown', 'checkbox'].includes(node.type);
        const badgeHtml = node.type === 'dropdown'
            ? Components.createDropdownPriceBadge(node)
            : (showBadge ? Components.createPriceBadge(node) : '');

        return `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:4px">
                <span class="form-label preview-label form-custom-label !mb-0">
                    ${node.label || ""}
                    ${node.required ? '<span class="text-[#B91C1C]">*</span>' : ""}
                    ${node.description?.trim() ? Components.createDescIcon(node.description) : ""}
                </span>
                ${badgeHtml}
            </div>
            ${node.description?.trim() ? Components.createDescBox(node.description) : ""}
        `;
    }

    static createPriceBadge(node) {
        const sym = Components.currencyFormation.symbol;
        const price = parseFloat(node.price) || 0;
        if (price <= 0) return '';
        const isPerUnit = node.unit_type === 'price_per_unit';
        const perPerson = Components.perPersonSuffix(node);
        const text = isPerUnit
            ? `${sym}${price.toFixed(2)}/unit`
            : (perPerson ? `+${sym}${price.toFixed(2)} per person` : `+${sym}${price.toFixed(2)}`);
        return `<span style="flex-shrink:0;display:inline-flex;align-items:center;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:20px;padding:2px 10px;font-size:0.73rem;font-weight:600;white-space:nowrap">${text}</span>`;
    }

    // Up-front price badge for a DROPDOWN field: unlike radio (every option's price is
    // already visible at once), a <select>'s other options are hidden until it's opened,
    // so a priced option can be completely invisible to the user beforehand — this is
    // what surfaces "this field has pricing" before they ever click it. Only fields with
    // at least one priced option get a badge; a plain, entirely-unpriced dropdown (e.g.
    // Meal Preference with no fees) renders nothing, same as today.
    static createDropdownPriceBadge(node) {
        const priced = (node.options || []).filter(o =>
            (o.additional_fee == 1 || o.additional_fee === true) && parseFloat(o.price) > 0
        );
        if (!priced.length) return '';

        const sym = Components.currencyFormation.symbol;
        const perPerson = Components.perPersonSuffix(node);
        const prices = priced.map(o => parseFloat(o.price));
        const min = Math.min(...prices);
        const max = Math.max(...prices);
        const text = min === max
            ? `+${sym}${min.toFixed(2)}${perPerson}`
            : `+${sym}${min.toFixed(2)} to ${sym}${max.toFixed(2)}${perPerson}`;

        return `<span style="flex-shrink:0;display:inline-flex;align-items:center;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:20px;padding:2px 10px;font-size:0.73rem;font-weight:600;white-space:nowrap">${text}</span>`;
    }

    // A price_per_pax field flagged display_once is answered once (shown in the general
    // add-ons section, not duplicated per traveler) but billed by traveler count — surfacing
    // " per person" wherever its price is shown makes that obvious without checking the flag.
    static perPersonSuffix(node) {
        return (node.unit_type === 'price_per_pax' && !!node.display_once) ? ' per person' : '';
    }

    static createDescIcon(text) {
        return `
            <span class="desc-icon ms-2" style="cursor:pointer;color:#0d6efd">
                <i class="fas fa-info-circle"></i>
            </span>
        `;
    }

    static createDescBox(text) {
        return `
            <div class="desc-box mt-1 text-[14px]" style="display:none">${text}</div>
        `;
    }

    // ---------------------------------------------------------
    // ROUTE FIELD TYPES
    // ---------------------------------------------------------
    static buildFieldByType(node, paxUid) {
        switch (node.type) {
            case "checkbox": return Components.buildCheckbox(node, paxUid);
            case "radio": return Components.buildRadio(node, paxUid);
            case "dropdown": return Components.buildDropdown(node, paxUid);
            case "number": return Components.buildNumber(node, paxUid);
            case "text": return Components.buildText(node, paxUid);
            case "textbox": return Components.buildTextbox(node, paxUid);
            default: return Components.buildDefault(node, paxUid);
        }
    }

    // ---------------------------------------------------------
    // FIELD BUILDERS (HTML VERSION)
    // ---------------------------------------------------------

    // 1. CHECKBOX
    static buildCheckbox(node, paxUid) {
        const id = Components.buildId(node.id, paxUid);
        const hasPrice = Number(node.additional_fee) === 1 && parseFloat(node.price) > 0;
        const sym = Components.currencyFormation.symbol;
        const perPerson = Components.perPersonSuffix(node);
        const priceHtml = hasPrice
            ? `<span style="margin-left:auto;flex-shrink:0;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:20px;padding:2px 10px;font-size:0.73rem;font-weight:600">+${sym}${parseFloat(node.price).toFixed(2)}${perPerson}</span>`
            : '';

        return `
            <div class="form-check" style="display:flex;align-items:center;gap:8px">
                <input type="checkbox"
                    class="form-check-input"
                    id="${id}"
                    data-fee="${node.price || 0}"
                    data-option-additional-fee="${node.additional_fee || 0}"
                    style="width:16px;height:16px;flex-shrink:0;margin:0;cursor:pointer">
                <label class="form-check-label form-custom-label !mb-0" for="${id}" style="flex:1;cursor:pointer">
                    ${node.label || ""} ${node.required ? '<span class="text-danger">*</span>' : ""}
                </label>
                ${priceHtml}
            </div>
        `;
    }

    // 2. RADIO
    static buildRadio(node, paxUid) {
        const sym = Components.currencyFormation.symbol;
        const perPerson = Components.perPersonSuffix(node);
        return node.options?.map((o, i) => {
            const rid = Components.buildOptionId(node.id, paxUid, i);
            const hasPrice = (o.additional_fee == 1 || o.additional_fee === true) && parseFloat(o.price) > 0;
            const priceHtml = hasPrice
                ? `<span style="flex-shrink:0;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:20px;padding:2px 10px;font-size:0.73rem;font-weight:600">+${sym}${parseFloat(o.price).toFixed(2)}${perPerson}</span>`
                : '';

            return `
                <label for="${rid}" class="addon-radio-option" style="display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border:1px solid #E5E7EB;border-radius:8px;margin-bottom:6px;cursor:pointer;gap:10px;background:#fff;transition:background 0.12s,border-color 0.12s">
                    <div style="display:flex;align-items:center;gap:8px;min-width:0">
                        <input
                            type="radio"
                            class="form-check-input"
                            name="${paxUid === null ? node.id : node.id + "_" + paxUid}"
                            id="${rid}"
                            value="${o.value || ''}"
                            data-option-id="${o.id || ''}"
                            data-fee="${o.price || 0}"
                            data-label="${(o.label || '').replace(/"/g, '&quot;')}"
                            data-option-additional-fee="${o.additional_fee || 0}"
                            style="margin:0;width:16px;height:16px;flex-shrink:0;cursor:pointer">
                        <span style="font-size:0.875rem;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${o.label || ""}</span>
                    </div>
                    ${priceHtml}
                </label>
            `;
        }).join("");
    }

    // 3. DROPDOWN
    static buildDropdown(node, paxUid) {
        const id = Components.buildId(node.id, paxUid);
        const sym = Components.currencyFormation.symbol;
        const perPerson = Components.perPersonSuffix(node);

        // The field itself can carry its own base price independent of whichever option is
        // selected (e.g. "Photography Package" = $15 field price + up to $10 more depending on
        // option) — that field-level fee must be attached somewhere in the DOM, or any reader
        // computing a "current total" from data-fee attributes (calculateTotal(),
        // updatePaxDetailPrice() in tour-booking-change-people-v2.js) can only ever see the
        // option's own increment and silently drops the field's base price entirely.
        const fieldHasFee = (node.additional_fee == 1 || node.additional_fee === true) && parseFloat(node.price) > 0;
        const fieldFee = fieldHasFee ? parseFloat(node.price) : 0;

        const opts = node.options?.map(o => {
            const hasPrice = (o.additional_fee == 1 || o.additional_fee === true) && parseFloat(o.price) > 0;
            const suffix = hasPrice ? ` (+${sym}${parseFloat(o.price).toFixed(2)}${perPerson})` : '';
            return `<option
                value="${o.value || ""}"
                data-option-id="${o.id || ''}"
                data-fee="${o.price || 0}"
                data-label="${(o.label || '').replace(/"/g, '&quot;')}"
                data-option-additional-fee="${o.additional_fee || 0}">${o.label || ""}${suffix}</option>`;
        }).join("");

        return `
            <select class="form-select form-custom-input" id="${id}" data-field-fee="${fieldFee}">
                <option value="">Select an option</option>
                ${opts}
            </select>
            <div class="dropdown-price-hint" style="display:none;margin-top:6px"></div>
        `;
    }

    // 4. NUMBER INPUT
    static buildNumber(node, paxUid) {
        // const id = paxUid === null ? node.id : `${node.id}_${paxUid}`;
        const id = Components.buildId(node.id, paxUid);

        return `
            <input type="number"
                class="form-control node-input form-custom-input"
                id="${id}"
                data-fee="${node.price || 0}"
                data-option-additional-fee="${node.additional_fee || 0}"
                min="${node.min ?? 0}"
                max="${node.max ?? ''}"
                step="1">
        `;
    }

    // 5. TEXT INPUT
    static buildText(node, paxUid) {
        // const id = paxUid === null ? node.id : `${node.id}_${paxUid}`;
        const id = Components.buildId(node.id, paxUid);
        return `
            <input type="text"
                class="form-control node-input form-custom-input"
                id="${id}"
                data-fee="${node.price || 0}">
        `;
    }

    // 6. TEXTAREA
    static buildTextbox(node, paxUid) {
        // const id = paxUid === null ? node.id : `${node.id}_${paxUid}`;
        const id = Components.buildId(node.id, paxUid);
        return `
            <textarea 
                class="form-control form-custom-input min-h-16"
                id="${id}"
                data-fee="${node.price || 0}"
                ></textarea>
        `;
    }

    // 7. DEFAULT
    static buildDefault(node, paxUid) {
        // const id = paxUid === null ? node.id : `${node.id}_${paxUid}`;
        const id = Components.buildId(node.id, paxUid);
        return `
            <input type="text"
                class="form-control form-custom-input"
                id="${id}">
        `;
    }

    // ---------------------------------------------------------
    // ADDITIONAL FEE LABEL
    // Only used for price_per_unit — shows the live "× qty = total" running total.
    // All other types show their price in the label header badge (createPriceBadge).
    // ---------------------------------------------------------
    static createAdditionalFee(node) {
        if (node.unit_type !== 'price_per_unit') return '';

        const price = parseFloat(node.price) || 0;
        if (price <= 0) return '';
        const sym = Components.currencyFormation.symbol;

        return `
            <div class="price-preview mt-2" style="display:none">
                <span style="
                    display:inline-flex;align-items:center;gap:6px;
                    background:#f0fdf4;border:1px solid #bbf7d0;
                    color:#15803d;border-radius:6px;padding:4px 12px;font-size:0.8rem;font-weight:600
                ">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
                    ${sym}${price.toFixed(2)} per unit
                    <span class="price-per-unit-total" style="font-weight:700;border-left:1px solid #86efac;padding-left:8px"></span>
                </span>
            </div>
        `;
    }

    // ---------------------------------------------------------
    // RULE CHILDREN
    // ---------------------------------------------------------
    static appendRuleChildren(holder, node, paxUid) {
        // Rule-based children
        (node.rules || []).forEach((r, idx) => {
            const wrap = document.createElement("div");
            wrap.className = "indent mt-2";
            wrap.dataset.ruleIndex = idx;
            wrap.style.display = "none";

            r.children?.forEach(child => {
                child.parent_id = node.id;
                child.rule_id = r.id || null;
                child.applied_rule_snapshot = {
                    value: r.value || null,
                    operator: r.operator || 'equals',
                    option_id: r.option_id || null
                };
                if (!child.unit_type && node.unit_type) {
                    child.unit_type = node.unit_type;
                }
                const childEl = Components.renderField(child, paxUid);
                wrap.appendChild(childEl);
            });

            holder.appendChild(wrap);
        });

        // Direct children
        /*(node.children || []).forEach(child => {
            const wrap = document.createElement("div");
            wrap.className = "indent mt-2";
            wrap.style.display = "none";

            const childEl = Components.renderField(child, paxUid);
            wrap.appendChild(childEl);

            holder.appendChild(wrap);
        });*/
    }

    static attachLogicToContainer(wrap, nodes) {
        if (!wrap || !nodes) return;

        // helper to read a node wrap's value according to type
        const getValueOf = (elWrap, type) => {
            if (type === 'checkbox') {
                const inp = elWrap.querySelector('.form-check-input');
                return inp && inp.checked ? (inp.dataset.optionId ? String(inp.dataset.optionId) : 'checked') : 'not_checked';
            }
            if (type === 'radio') {
                const sel = elWrap.querySelector('input[type="radio"]:checked');
                return sel ? (sel.dataset.optionId ? String(sel.dataset.optionId) : sel.value) : '';
            }
            if (type === 'dropdown') {
                const sel = elWrap.querySelector('select');
                if (!sel) return '';
                const opt = sel.selectedOptions[0];
                return opt && opt.dataset.optionId ? String(opt.dataset.optionId) : sel.value;
            }
            return '';
        };

        const evalRule = (val, type, rule, elWrap) => {
            if (type === 'checkbox') return rule.operator === 'checked' ? val === 'checked' : val !== 'checked';
            if (type === 'radio' || type === 'dropdown') {
                const selectedEl = type === 'radio' ? elWrap.querySelector('input[type="radio"]:checked') : elWrap.querySelector('select');
                if (!selectedEl) return false;
                const selectedOptionId = type === 'radio' ? String(selectedEl.dataset.optionId) : String(selectedEl.selectedOptions[0]?.dataset.optionId);
                if (rule.option_id) return rule.operator === 'equals' ? selectedOptionId === String(rule.option_id) : selectedOptionId !== String(rule.option_id);
                return rule.operator === 'equals' ? String(val) === String(rule.value) : String(val) !== String(rule.value);
            }
            return false;
        };

        const walk = (node) => {
            const elWrap = wrap.querySelector(`[data-node-id="${node.id}"]`);
            if (!elWrap) return;
            const val = getValueOf(elWrap, node.type);

            (node.rules || []).forEach((r, idx) => {
                const childWrap = elWrap.querySelector(`.indent[data-rule-index="${idx}"]`);
                if (!childWrap) return;
                let show = evalRule(val, node.type, r, elWrap);
                const parentOptionId = childWrap.getAttribute('data-parent-option-id');
                if (!show && parentOptionId) {
                show = String(parentOptionId) === String(val);
                }
                childWrap.style.display = show ? '' : 'none';
                (r.children || []).forEach(ch => walk(ch));
            });
        };

        const runAll = () => nodes.forEach(n => walk(n));

        // attach minimal event handlers to the wrapper for delegation
        wrap.addEventListener('input', runAll);
        wrap.addEventListener('change', runAll);

        // initial evaluation
        runAll();

        // store runner for prefill usage
        try { wrap._logicRunAll = runAll; } catch (e) { /* ignore */ }

        this._runLogics = this._runLogics || [];
        if (!this._runLogics.includes(runAll)) this._runLogics.push(runAll);
    }

    static validateContainer(container, node) {
        let isValid = true;
        let firstInvalidEl = null;
        let hasRequired = false;

        // -------------------------
        // Helper: attach real-time validation
        // -------------------------
        const attachLiveValidation = (input, wrapper, type, required) => {
            if (!input) return;
            const evt = (type === 'checkbox' || type === 'radio' || input.tagName === 'SELECT') ? 'change' : 'input';

            input.addEventListener(evt, () => {
                let valid = true;

                if (type === 'checkbox') {
                valid = input.checked;
                if (valid) wrapper.classList.remove('is-invalid');
                } 
                else if (type === 'radio') {
                const group = wrapper.querySelectorAll('input[type="radio"]');
                valid = Array.from(group).some(r => r.checked);
                if (valid) wrapper.classList.remove('is-invalid');
                } 
                else {
                valid = input.value.trim() !== '';
                if (valid) input.classList.remove('is-invalid');
                }

                if (!valid && required) {
                if (type === 'radio' || type === 'checkbox') wrapper.classList.add('is-invalid');
                else input.classList.add('is-invalid');
                }
            });
        };

        // -------------------------
        // Helper: Validate one node
        // -------------------------
        const validateNode = (container, node, parentActive = true) => {
            if (!node) return;

            const required = Number(node.required) === 1;
            const type = node.type;

            let hasValue = false;
            let wrapper = container.querySelector(`.addon-card[data-node-id="${node.id}"]`);
            if (!wrapper) return;

            if (required) hasRequired = true;

            // cleanup older errors
            wrapper.classList.remove('is-invalid');
            wrapper.querySelectorAll('.invalid-feedback').forEach(m => m.remove());

            // ---- CHECKBOX ----
            if (type === 'checkbox') {
                const chk = wrapper.querySelector('input[type="checkbox"]');
                hasValue = !!chk?.checked;

                if (required && parentActive && !hasValue) {
                isValid = false;
                wrapper.classList.add('is-invalid');
                wrapper.insertAdjacentHTML('beforeend', `<div class="invalid-feedback mt-1 text-xs text-[#A32624]">This field is required.</div>`);
                if (!firstInvalidEl) firstInvalidEl = chk;
                attachLiveValidation(chk, wrapper, 'checkbox', required);
                }
            }

            // ---- RADIO ----
            else if (type === 'radio') {
                const radios = wrapper.querySelectorAll('input[type="radio"]');
                const checked = Array.from(radios).find(r => r.checked);
                hasValue = !!checked;

                if (required && parentActive && !hasValue) {
                isValid = false;
                wrapper.classList.add('is-invalid');
                wrapper.insertAdjacentHTML('beforeend', `<div class="invalid-feedback mt-1 text-xs text-[#A32624]">This field is required.</div>`);
                if (!firstInvalidEl) firstInvalidEl = radios[0] || wrapper;
                radios.forEach(r => attachLiveValidation(r, wrapper, 'radio', required));
                }
            }

            // ---- TEXT / SELECT / NUMBER ----
            else {
                const input = wrapper.querySelector('input, select, textarea');
                hasValue = input && input.value.trim() !== '';

                if (required && parentActive && !hasValue && input) {
                isValid = false;
                input.classList.add('is-invalid');
                input.insertAdjacentHTML('afterend', `<div class="invalid-feedback  mt-1 text-xs text-[#A32624]">This field is required.</div>`);
                if (!firstInvalidEl) firstInvalidEl = input;
                attachLiveValidation(input, wrapper, input.tagName.toLowerCase(), required);
                }
            }

            // ---------------------------------------
            // Validate nested children only when active
            // ---------------------------------------
            // const childrenActive = parentActive && (required || hasValue);
            // if (!childrenActive) return;
            if (!parentActive) return;

            // Rule children
            (node.rules || []).forEach((rule, idx) => {
                const ruleWrap = wrapper.querySelector(`.indent[data-rule-index="${idx}"]`);
                if (!ruleWrap) return;

                const isVisible = ruleWrap.style.display !== 'none';
                if (!isVisible) return;

                (rule.children || []).forEach(childNode => validateNode(wrapper, childNode, true));
            });

            // Normal children
            (node.children || []).forEach(childNode => {
                const childWrap = wrapper.querySelector(
                    `.addon-card[data-node-id="${childNode.id}"]`
                );
                if (!childWrap) return;

                const isVisible = childWrap.offsetParent !== null;
                if (!isVisible) return;

                validateNode(wrapper, childNode, true)
            });
        };

        validateNode(container, node);

        // Scroll to first invalid element
        if (firstInvalidEl) {
        try {
            firstInvalidEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => firstInvalidEl.focus(), 250);
        } catch (e) {}
        }

        return { isValid, hasRequired };
    }
}

document.addEventListener("click", function (e) {
    const icon = e.target.closest(".desc-icon");
    if (!icon) return;

    const card = icon.closest(".addon-card");
    if (!card) return;

    const box = card.querySelector(".desc-box");
    if (!box) return;

    box.style.display = (box.style.display === "none" || box.style.display === "")
        ? "block"
        : "none";
});

// Radio option card: highlight selected, clear others in the same group
document.addEventListener("change", function (e) {
    if (e.target.type === 'radio') {
        const card = e.target.closest('.addon-card');
        if (!card) return;
        const name = e.target.getAttribute('name');
        card.querySelectorAll('.addon-radio-option').forEach(opt => {
            const r = opt.querySelector(`input[type="radio"][name="${CSS.escape(name)}"]`);
            if (r && r.checked) {
                opt.style.background = '#EFF6FF';
                opt.style.borderColor = '#3B82F6';
            } else {
                opt.style.background = '#fff';
                opt.style.borderColor = '#E5E7EB';
            }
        });
    }

    // Dropdown: show a price hint below the select when a priced option is chosen
    if (e.target.tagName === 'SELECT') {
        const card = e.target.closest('.addon-card');
        if (!card) return;
        const hint = card.querySelector('.dropdown-price-hint');
        if (!hint) return;

        const opt = e.target.selectedOptions[0];
        const price = parseFloat(opt?.dataset?.fee) || 0;
        const hasFee = opt?.dataset?.optionAdditionalFee === '1';
        const sym = typeof currencyFormation !== 'undefined' ? currencyFormation.symbol : '$';
        const perPerson = (card.dataset.unitType === 'price_per_pax' && card.dataset.displayOnce === '1') ? ' per person' : '';

        if (hasFee && price > 0) {
            hint.innerHTML = `<span style="display:inline-flex;align-items:center;gap:5px;background:#EFF6FF;border:1px solid #BFDBFE;color:#1D4ED8;border-radius:20px;padding:2px 10px;font-size:0.73rem;font-weight:600">+${sym}${price.toFixed(2)}${perPerson}</span>`;
            hint.style.display = 'block';
        } else {
            hint.style.display = 'none';
        }
    }
});