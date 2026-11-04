(function () {
    var cfg = window.tpSelectorConfig || {};
    var _open = false;
    var _comboPrimaryId   = cfg.comboPrimaryId   || null;
    var _comboPrimaryName = cfg.comboPrimaryName  || '';

    function _isPrimaryInCart() {
        if (!_comboPrimaryId) return false;
        return !!document.getElementById('cart-package-' + _comboPrimaryId);
    }

    function _tpUnlockAddons() {
        var sec = document.getElementById('tp-addon-section');
        if (sec) { sec.style.pointerEvents = 'auto'; sec.style.opacity = '1'; }
    }

    function _tpLockAddons() {
        var sec = document.getElementById('tp-addon-section');
        if (sec) { sec.style.pointerEvents = 'none'; sec.style.opacity = '0.4'; }
    }

    function _closeDropdown() {
        _open = false;
        document.getElementById('tourPackageDropdown').style.display = 'none';
    }

    window.tpToggleDropdown = function (e) {
        e.stopPropagation();
        _open = !_open;
        document.getElementById('tourPackageDropdown').style.display = _open ? 'flex' : 'none';
        if (_open) {
            if (_isPrimaryInCart()) { _tpUnlockAddons(); } else { _tpLockAddons(); }
            _checkComboCartState();
        }
    };

    window.tpSwitchTab = function (tab) {
        ['single', 'combo', 'bundles'].forEach(function (t) {
            var panel = document.getElementById('tp-panel-' + t);
            var btn   = document.getElementById('tp-tab-' + t);
            if (!panel || !btn) return;
            var active = t === tab;
            panel.style.display    = active ? 'block' : 'none';
            btn.style.color        = active ? '#1e3a5c' : '#94a3b8';
            btn.style.borderBottom = active ? '2.5px solid #f5891f' : '2.5px solid transparent';
            var badge = btn.querySelector('span');
            if (badge) {
                badge.style.background = active ? '#fff7ed' : '#f1f5f9';
                badge.style.color      = active ? '#c2410c' : '#94a3b8';
            }
            var pin = btn.querySelectorAll('span')[1];
            if (pin) pin.style.color = active ? '#f5891f' : '#cbd5e1';
        });
    };

    function _resetSingleItems() {
        document.querySelectorAll('.tp-single-item').forEach(function (row) {
            row.classList.remove('tp-si-selected');
            row.style.background = 'transparent';
            var wrap = row.querySelector('.si-radio-wrap');
            if (wrap) wrap.style.borderColor = '#d1d5db';
            var dot = row.querySelector('.si-radio-dot');
            if (dot) dot.style.display = 'none';
        });
    }

    function _applySingleSelected(id) {
        var row = document.querySelector('.tp-single-item[data-pkg-id="' + id + '"]');
        if (!row) return;
        row.classList.add('tp-si-selected');
        row.style.background = '#fff7ed';
        var wrap = row.querySelector('.si-radio-wrap');
        if (wrap) wrap.style.borderColor = '#F97316';
        var dot = row.querySelector('.si-radio-dot');
        if (dot) dot.style.display = 'block';
    }

    function _resetComboAddonItems() {
        document.querySelectorAll('.tp-combo-item').forEach(function (row) {
            row.classList.remove('tp-ci-selected');
            row.style.background = 'transparent';
            var wrap = row.querySelector('.si-radio-wrap');
            if (wrap) wrap.style.borderColor = '#d1d5db';
            var dot = row.querySelector('.si-radio-dot');
            if (dot) dot.style.display = 'none';
        });
    }

    function _resetComboPrimaryDisplay() {
        var row = document.querySelector('.tp-combo-primary');
        if (!row) return;
        row.style.background = 'transparent';
        var wrap = row.querySelector('.si-radio-wrap');
        if (wrap) wrap.style.borderColor = '#d1d5db';
        var dot = row.querySelector('.si-radio-dot');
        if (dot) dot.style.display = 'none';
    }

    function _applyComboPrimaryDisplay() {
        var row = document.querySelector('.tp-combo-primary');
        if (!row) return;
        row.style.background = '#fff7ed';
        var wrap = row.querySelector('.si-radio-wrap');
        if (wrap) wrap.style.borderColor = '#F97316';
        var dot = row.querySelector('.si-radio-dot');
        if (dot) dot.style.display = 'block';
    }

    function _applyComboAddonSelected(id) {
        var row = document.querySelector('.tp-combo-item[data-pkg-id="' + id + '"]');
        if (!row) return;
        row.classList.add('tp-ci-selected');
        row.style.background = '#fff7ed';
        var wrap = row.querySelector('.si-radio-wrap');
        if (wrap) wrap.style.borderColor = '#F97316';
        var dot = row.querySelector('.si-radio-dot');
        if (dot) dot.style.display = 'block';
    }

    function _resetBundleCards() {
        document.querySelectorAll('input[name="tp_bundle_sel"]').forEach(function (r) { r.checked = false; });
        document.querySelectorAll('.tp-bundle-card').forEach(function (card) {
            card.style.borderColor = '#E5E7EB';
            card.style.boxShadow = 'none';
            card.style.background = '#fff';
            var dot = card.querySelector('.bnd-radio-dot');
            if (dot) dot.style.display = 'none';
            var circle = card.querySelector('.bnd-radio-circle');
            if (circle) circle.style.borderColor = '#E2E8F0';
            card.querySelectorAll('.bnd-pkg-num').forEach(function (n) { n.style.background = '#1E3A5C'; });
            card.querySelectorAll('.bnd-pkg-row').forEach(function (r) { r.style.background = '#F8FAFC'; });
            var footer = card.querySelector('.bnd-footer');
            if (footer) footer.style.background = '#F8FAFC';
        });
    }

    function _resetSingleComboRadios() {
        document.querySelectorAll('input[name="tp_single_sel"], input[name="tp_combo_sel"]').forEach(function (r) { r.checked = false; });
    }

    function _checkComboCartState() {
        var primaryInCart = !!(_comboPrimaryId && document.getElementById('cart-package-' + _comboPrimaryId));

        // Disable Single + Bundle tabs when a combo package is in cart
        ['single', 'bundles'].forEach(function (t) {
            var btn = document.getElementById('tp-tab-' + t);
            if (!btn) return;
            if (primaryInCart) {
                btn.style.opacity = '0.35';
                btn.style.pointerEvents = 'none';
                btn.title = 'Remove combo package from cart first';
            } else {
                btn.style.opacity = '';
                btn.style.pointerEvents = '';
                btn.title = '';
            }
        });

        // Force combo tab if a disabled panel is currently showing
        if (primaryInCart) {
            var sPanel = document.getElementById('tp-panel-single');
            var bPanel = document.getElementById('tp-panel-bundles');
            if ((sPanel && sPanel.style.display !== 'none') || (bPanel && bPanel.style.display !== 'none')) {
                window.tpSwitchTab('combo');
            }
        }

        // Mark primary combo row as "In Cart" and neutralise its selection display
        var primaryRow = document.querySelector('.tp-combo-primary');
        if (primaryRow) {
            var pb = primaryRow.querySelector('.tp-in-cart-badge');
            if (primaryInCart) {
                primaryRow.style.pointerEvents = 'none';
                primaryRow.style.background = '#f8fafc';
                var pw = primaryRow.querySelector('.si-radio-wrap');
                if (pw) pw.style.borderColor = '#d1d5db';
                var pd = primaryRow.querySelector('.si-radio-dot');
                if (pd) pd.style.display = 'none';
                if (!pb) {
                    pb = document.createElement('span');
                    pb.className = 'tp-in-cart-badge';
                    pb.textContent = 'In Cart';
                    pb.style.cssText = 'font-size:9px;font-weight:700;padding:2px 8px;border-radius:20px;background:#dcfce7;color:#16a34a;border:1px solid #86efac;white-space:nowrap;flex-shrink:0;margin-left:auto;align-self:center;';
                    primaryRow.appendChild(pb);
                }
            } else {
                primaryRow.style.pointerEvents = '';
                if (pb) pb.remove();
            }
        }

        // Mark combo addon items already in cart as disabled
        var addonSection = document.getElementById('tp-addon-section');
        var addonRows = addonSection
            ? Array.prototype.slice.call(addonSection.querySelectorAll('.tp-combo-item[data-pkg-id]'))
            : [];
        addonRows.forEach(function (row) {
            var pkgId = row.getAttribute('data-pkg-id');
            var inCart = !!document.getElementById('cart-package-' + pkgId);
            var badge = row.querySelector('.tp-in-cart-badge');
            if (inCart) {
                row.style.opacity = '0.45';
                row.style.pointerEvents = 'none';
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'tp-in-cart-badge';
                    badge.textContent = 'In Cart';
                    badge.style.cssText = 'font-size:9px;font-weight:700;padding:2px 8px;border-radius:20px;background:#dcfce7;color:#16a34a;border:1px solid #86efac;white-space:nowrap;flex-shrink:0;margin-left:auto;align-self:center;';
                    row.appendChild(badge);
                }
            } else {
                row.style.opacity = '';
                row.style.pointerEvents = '';
                if (badge) badge.remove();
            }
        });

        // Sort addon rows so "In Cart" items appear at the top
        if (addonSection && addonRows.length) {
            var inCart = addonRows.filter(function (r) { return !!document.getElementById('cart-package-' + r.getAttribute('data-pkg-id')); });
            var notInCart = addonRows.filter(function (r) { return !document.getElementById('cart-package-' + r.getAttribute('data-pkg-id')); });
            inCart.concat(notInCart).forEach(function (r) { addonSection.appendChild(r); });
        }
    }

    window.tpRefreshComboCartState = _checkComboCartState;

    window.tpSelectPackage = function (id, name, bundleLabel) {
        var sel = document.getElementById('tour_package');
        sel.value = id;
        document.getElementById('tourPackageLabel').textContent = name;
        _closeDropdown();
        // bubbles:true is required — AddonFormReader's per-package reset/refetch handler
        // (form-reader.js) is delegated on document via jQuery .on('change', '#tour_package', ...),
        // which only fires once the event bubbles there. A non-bubbling Event() silently skips it,
        // leaving the previously selected package's add-on form/state cached (wrong-package bug).
        sel.dispatchEvent(new Event('change', { bubbles: true }));
        if (bundleLabel) {
            // Selecting a bundle — reset single/combo radios then apply bundle selected state
            _resetSingleComboRadios();
            _resetSingleItems();
            _resetComboAddonItems();
            _resetBundleCards();
            bundleLabel.style.borderColor = '#F97316';
            bundleLabel.style.boxShadow = '0 4px 18px rgba(245,137,31,.16)';
            bundleLabel.style.background = '#fffbf7';
            var dot = bundleLabel.querySelector('.bnd-radio-dot');
            if (dot) dot.style.display = 'block';
            var circle = bundleLabel.querySelector('.bnd-radio-circle');
            if (circle) circle.style.borderColor = '#F97316';
            bundleLabel.querySelectorAll('.bnd-pkg-num').forEach(function (n) { n.style.background = '#F97316'; });
            bundleLabel.querySelectorAll('.bnd-pkg-row').forEach(function (r) { r.style.background = 'rgba(245,137,31,.05)'; });
            var footer = bundleLabel.querySelector('.bnd-footer');
            if (footer) footer.style.background = 'rgba(245,137,31,.04)';
            // Re-check this bundle's radio so onchange fires again next time
            var radio = bundleLabel.querySelector('input[name="tp_bundle_sel"]');
            if (radio) radio.checked = true;
        } else {
            // Selecting a single — reset bundles, reset combo addons, reset other single items, apply selected state
            _resetBundleCards();
            _resetComboAddonItems();
            _resetSingleItems();
            _applySingleSelected(id);
        }
    };

    window.tpResetSelector = function () {
        var lbl = document.getElementById('tourPackageLabel');
        if (lbl) lbl.textContent = 'Select Tour Package';
        document.querySelectorAll('input[name="tp_combo_sel"]').forEach(function (r) { r.checked = false; });
        if (_isPrimaryInCart()) { _tpUnlockAddons(); } else { _tpLockAddons(); }
    };

    window.tpComboPrimaryId = _comboPrimaryId;
    window.tpOnCartPackageRemoved = function (removedPackageId) {
        if (_comboPrimaryId && parseInt(removedPackageId) === _comboPrimaryId) {
            _tpLockAddons();
            var lbl = document.getElementById('tourPackageLabel');
            if (lbl) lbl.textContent = 'Select Tour Package';
            document.querySelectorAll('input[name="tp_combo_sel"]').forEach(function (r) { r.checked = false; });
        }
        _checkComboCartState();
    };

    window.tpSelectAddon = function (id, name) {
        var sel = document.getElementById('tour_package');
        sel.value = id;
        document.getElementById('tourPackageLabel').textContent = name;
        _resetSingleItems();
        _resetBundleCards();
        _resetComboAddonItems();
        _resetComboPrimaryDisplay();
        _applyComboAddonSelected(id);
        _closeDropdown();
        // bubbles:true — see tpSelectPackage() above for why this matters for AddonFormReader.
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    };

    window.tpConfirmCombo = function () {
        if (!_comboPrimaryId) return;
        var sel = document.getElementById('tour_package');
        sel.value = _comboPrimaryId;
        document.getElementById('tourPackageLabel').textContent = _comboPrimaryName;
        // Re-check the primary radio — auto-unchecks all other tp_combo_sel radios (addons)
        var primaryRadio = document.querySelector('input[name="tp_combo_sel"][value="' + _comboPrimaryId + '"]');
        if (primaryRadio) primaryRadio.checked = true;
        _resetSingleItems();
        _resetBundleCards();
        _resetComboAddonItems();
        _applyComboPrimaryDisplay();
        _closeDropdown();
        // bubbles:true — see tpSelectPackage() above for why this matters for AddonFormReader.
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    };

    document.addEventListener('click', function (e) {
        var wrp = document.getElementById('tourPackageSelectorWrp');
        if (wrp && !wrp.contains(e.target)) _closeDropdown();
    });

    /* Pre-select if preSelectPackageId is set (replaces @if($tourPackageId) Blade block) */
    var preSelectId = cfg.preSelectPackageId;
    if (preSelectId) {
        // Server-set flag is the reliable signal; DOM check is a fallback.
        var isComboAddon = cfg.reBookIsComboAddon
            || (_comboPrimaryId
                && parseInt(preSelectId) !== parseInt(_comboPrimaryId)
                && !!document.querySelector('.tp-combo-item[data-pkg-id="' + preSelectId + '"]'));
        var isComboMain = _comboPrimaryId && parseInt(preSelectId) === parseInt(_comboPrimaryId);

        if (isComboAddon) {
            // Rebook of a combo addon — primary must be added to cart first.
            // Clear the server-pre-selected addon from the hidden <select> and label.
            var addonSel = document.getElementById('tour_package');
            if (addonSel) addonSel.value = '';
            var addonLbl = document.getElementById('tourPackageLabel');
            if (addonLbl) addonLbl.textContent = 'Select Tour Package';
            window.tpSwitchTab('combo');
            setTimeout(function () {
                swal('Add Primary Package First', 'Please first select and add the primary package to cart before selecting this add-on package.', 'warning');
            }, 400);
        } else if (isComboMain) {
            // Rebook of the primary combo package itself — auto-confirm it.
            window.tpConfirmCombo();
        } else {
            // Single or bundle
            var existing = document.querySelector('input[name="tp_single_sel"][value="' + preSelectId + '"]')
                || document.querySelector('input[name="tp_bundle_sel"][value="' + preSelectId + '"]');
            if (existing) existing.checked = true;
        }
    }
})();

/* ── Booking Source Custom Dropdown ── */
(function () {
    var cfg = window.bsddConfig || {};
    var bsddCurrent = null;
    var bsddDefault = cfg.savedDefault || '';

    function bsddApply(el) {
        document.querySelectorAll('.bsdd-option').forEach(function (o) { o.classList.remove('selected'); });
        el.classList.add('selected');
        bsddCurrent = el.dataset.value;

        var bookingSel = document.getElementById('booking_source');
        if (bookingSel) bookingSel.value = bsddCurrent;

        var iconWrap = document.getElementById('bsddBtnIcon');
        if (iconWrap) {
            iconWrap.style.background = el.dataset.bg;
            var optSvg = el.querySelector('.bsdd-opt-icon svg');
            if (optSvg) {
                var clone = optSvg.cloneNode(true);
                clone.setAttribute('width', '11');
                clone.setAttribute('height', '11');
                iconWrap.innerHTML = '';
                iconWrap.appendChild(clone);
            }
        }

        var lbl = document.getElementById('bsddBtnLabel');
        if (lbl) { lbl.textContent = el.dataset.label; lbl.classList.add('chosen'); }

        var row = document.getElementById('bsddSetDefaultRow');
        if (!row) return;
        if (bsddCurrent === bsddDefault) {
            row.classList.remove('visible');
        } else {
            row.classList.add('visible');
            var setBtn = document.getElementById('bsddSetDefaultBtn');
            if (setBtn) setBtn.classList.remove('is-default');
            var setText = document.getElementById('bsddSetDefaultText');
            if (setText) setText.textContent = 'Set as default';
        }
    }

    window.bsddToggle = function (e) {
        e.stopPropagation();
        var menu    = document.getElementById('bsddMenu');
        var btn     = document.getElementById('bsddBtn');
        var chevron = document.getElementById('bsddChevron');
        if (!menu) return;
        var isOpen = menu.classList.contains('open');
        menu.classList.toggle('open', !isOpen);
        btn.classList.toggle('open', !isOpen);
        chevron.classList.toggle('rotated', !isOpen);
    };

    window.bsddSelect = function (el) {
        bsddApply(el);
        var menu    = document.getElementById('bsddMenu');
        var btn     = document.getElementById('bsddBtn');
        var chevron = document.getElementById('bsddChevron');
        if (menu)    menu.classList.remove('open');
        if (btn)     btn.classList.remove('open');
        if (chevron) chevron.classList.remove('rotated');
    };

    window.bsddSetDefault = function () {
        if (!bsddCurrent || !cfg.setDefaultUrl) return;
        var setBtn = document.getElementById('bsddSetDefaultBtn');
        if (setBtn) setBtn.disabled = true;
        $.ajax({
            url: cfg.setDefaultUrl,
            type: 'POST',
            data: { booking_source: bsddCurrent, _token: $('meta[name="csrf-token"]').attr('content') },
            success: function () {
                bsddDefault = bsddCurrent;
                cfg.savedDefault = bsddCurrent;
                document.querySelectorAll('.bsdd-opt-star').forEach(function (s) { s.classList.remove('visible'); });
                var star = document.getElementById('bsStar-' + bsddCurrent);
                if (star) star.classList.add('visible');
                var row = document.getElementById('bsddSetDefaultRow');
                if (row) row.classList.remove('visible');
                if (setBtn) setBtn.disabled = false;
            },
            error: function () { if (setBtn) setBtn.disabled = false; }
        });
    };

    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('#bsddWrap')) {
            var menu    = document.getElementById('bsddMenu');
            var btn     = document.getElementById('bsddBtn');
            var chevron = document.getElementById('bsddChevron');
            if (menu)    menu.classList.remove('open');
            if (btn)     btn.classList.remove('open');
            if (chevron) chevron.classList.remove('rotated');
        }
    });

    var preSelected = document.querySelector('.bsdd-option.selected');
    if (preSelected) bsddApply(preSelected);
})();
