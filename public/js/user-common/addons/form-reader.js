class AddonFormReader {
  constructor(props = {}) {
    // config
    this.admin = props.admin || false;
    this.tblRateGrps = props.tblRateGrps || $('#table-rate-groups');
    this.paxSection = $(props.paxSection || '#paxSection');
    this.addonSection = $(props.addonSection || '.addons-content');
    this.getAddonFormUrl = props.getAddonFormUrl;
    this.isCashFees = props.isCashFees;

    // DOM
    this.btnAddAddonsSelector = '.btn-add-addons';
    this.addonSectionSelector = '.addons-section';
    this.paxAddonsModal = $('#paxAddonsModal');
    this.addonsModal = $('#addonsModal');
    this.paxDetailsSelector = '#paxDetails';
    this.addonFieldsSelector = '#addonFields';

    // state
    // This is for Price per pax add-ons
    this.paxAddonsState = {
      form_id: 0,
      fields: {}
    };
    // This is for other types of add-ons [One time | Price per unit]
    this.addonState = {
      form_id: 0,
      fields: {}
    };

    this.addonsData = null;
    this.activeModal = null;
    this.lastSavedPaxAddonsState = {};
    this.lastSavedAddonsState = {};
    this.addonsFetched = false;
    this.bookingServiceCommission = 0;
    this.totalSeatsForBooking = 0;
    this.addonsPrice = 0;
    this.addonsFees = 0;
    this._runLogics = [];
    this._lastUsedSeats = null;
    this._lastGroupRatePaxCount = null;
    this._inputEvent = new Event("input", { bubbles: true });
    this._changeEvent = new Event("change", { bubbles: true });

    this.initEvents();
    this.bindAddonModalEvents();
  }

  // -------------------------
  // Init and event wiring
  // -------------------------
  initEvents() {
    // Keep single handlers (use delegation where possible)
    $(document).off('click.paxAddons').on('click.paxAddons', '.save-addons', this.saveAddonForm.bind(this));
    $(document).off('click.addonCancel').on('click.addonCancel', '.btn-cancel-addon', this.cancelAddonForm.bind(this));

    $(document).off('change.paxPackage').on('change.paxPackage', '#tour_package', (evt) => {
      const packageId = $(evt.target).val();

      // Clear old addon data and state immediately
      this.addonsData = null;
      this._fieldCache = null;
      this.paxAddonsState = { form_id: 0, fields: {} };
      this.addonState = { form_id: 0, fields: {} };
      this.addonsFetched = false;
      // A new package's headcount could coincidentally start at the same number the previous
      // package last had — without resetting this, that "first real selection" on the new
      // package would look like a no-op echo to the group-rate headcount-changed guard in
      // initAddonForm() and wrongly stay silent.
      this._lastGroupRatePaxCount = null;

      // Clear UI elements
      this.paxSection.find('.pax-list, .addon-section-title, .addons-content, .addon-divider, .payment-panel').remove();
      $(this.paxDetailsSelector).empty();
      $(this.addonSectionSelector).empty();

      // Hide addon button immediately when package changes
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');

      if (!packageId) {
        return;
      }

      // Fetch addons for new package
      this.fetchAddons(packageId, true).then((addons) => {
        if (addons) {
          // keep data; user will call initAddonForm when needed
          this.addonsData = addons;

          // Only show button if new package has addons AND there are pax selected
          const totalPax = this.getTotalPaxCount();
          if (this.hasAddons() && totalPax > 0) {
            $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass('d-none');
          }
        } else {
          this.addonsData = null;
          // Button already hidden above
        }
      });
    });

    // reopen button (delegated)
    $(document).off('click.reopenPaxAddons').on('click.reopenPaxAddons', this.btnAddAddonsSelector, (e) => {
      if (this.activeModal === 'pax') this.paxModalShow();
      else if (this.activeModal === 'addons') this.addonsModalShow();

      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
    });

    // centralized listeners to update pricing and logic evaluation (less per-node binding)
    // price updates: any change/input in relevant controls inside #paxDetails
    $(this.paxDetailsSelector).on('input.paxPricing change.paxPricing', '[data-fee], select, input[type="number"], input[type="checkbox"], input[type="radio"], textarea', (ev) => {
      const $detail = $(ev.target).closest('.pax-detail');
      if ($detail.length) {
        this.updatePricePerPaxUI($detail);
      }
      this.updatePricePerUnitPreview(ev.target);
      this.updateAddonsTotalUI();
      this.renderFinalCost();
    });

    $(this.paxSection).on('input.paxPricing change.paxPricing', '[data-fee], select, input[type="number"], input[type="checkbox"], input[type="radio"], textarea', (ev) => {
      const $detail = $(ev.target).closest(this.addonFieldsSelector);
      if ($detail.length) {
        this.updatePricePerPaxUI($detail);
      }
      this.updatePricePerUnitPreview(ev.target);
      this.updateAddonsTotalUI();
      this.renderFinalCost();
    });

    this.bindGlobalPaxButtons();
  }

  bindAddonModalEvents() {
    // Live price updates inside the one-time/price-per-unit addons modal
    $('#addonsModal').on('input.addonPricing change.addonPricing', '[data-fee], select, input[type="number"], input[type="checkbox"], input[type="radio"], textarea', (ev) => {
      this.updatePricePerUnitPreview(ev.target);
      this.updateAddonsTotalUI();
    });

    // Unified hidden handler for both modals
    [this.paxAddonsModal, this.addonsModal].forEach(($modal) => {
      $modal.on('hidden.bs.modal', () => {
        // Check if package has addons before showing button
        if (!this.hasAddons()) {
          $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
          return;
        }

        const totalPax = $(".pax-row .pax-count")
          .toArray()
          .reduce((sum, el) => sum + parseInt($(el).val() || 0), 0);

        const totalTouristsVal = (typeof totalTourists !== 'undefined') ? totalTourists : 0;

        if (totalPax > 0 || totalTouristsVal > 0) {
          $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass('d-none');
        } else {
          $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
        }
      });
    });
  }

  // -------------------------
  // Fetching addon form
  // -------------------------
  async fetchAddons(packageId) {
    if (!this.getAddonFormUrl) return null;
    const url = this.getAddonFormUrl.replace('PACKAGE_ID', packageId);

    try {
      const response = await $.ajax({
        url,
        method: 'GET',
        dataType: 'json',
        timeout: 15000   // 15s safety timeout
      });

      this.addonsFetched = true;

      // Validate response structure
      if (
        response &&
        response.success === true &&
        response.form &&
        Array.isArray(response.form.fields) &&
        response.form.fields.length > 0
      ) {
        this.addonsData = response;
        this._fieldCache = null;
        return this.addonsData;
      }

      // Response received but invalid format
      this.addonsData = null;
      // Hide addon button if no addons available
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
      return null;
    } catch (err) {
      // Catch network errors, timeouts, server errors
      console.error("Failed to fetch addons:", err);
      this.addonsData = null;
      // Hide addon button on error
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');

      return null;
    }
  }

  // -------------------------
  // Check if package has addons
  // -------------------------
  hasAddons() {
    if (!this.addonsData || !this.addonsData.form) {
      return false;
    }

    const addons = this.addonsData.form.addons || {};
    const paxFields = addons.pax_fields || [];
    const otherFields = addons.fields || [];

    return paxFields.length > 0 || otherFields.length > 0;
  }

  hasPricePerPaxAddons() {
    if (!this.addonsData || !this.addonsData.form) {
      return false;
    }

    const addons = this.addonsData.form.addons || {};
    const paxFields = addons.pax_fields || [];

    return paxFields.length > 0;
  }

  // -------------------------
  // Show / Hide modal
  // -------------------------
  paxModalShow() {
    this.activeModal = 'pax';

    // Ensure modal exists
    if (!this.paxAddonsModal || this.paxAddonsModal.length === 0) {
      this.paxAddonsModal = $('#paxAddonsModal');
    }

    if (!this.paxAddonsModal || this.paxAddonsModal.length === 0) {
      console.error('Pax addons modal not found in DOM');
      return;
    }

    this.lastSavedPaxAddonsState = structuredClone(
      this.paxAddonsState || {
        form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
        fields: {}
      }
    );
    this.lastSavedAddonsState = structuredClone(
      this.addonState || {
        form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
        fields: {}
      }
    );
    this.paxAddonsModal.modal({ backdrop: 'static', keyboard: false }).modal('show');
    this.renderPaxDetails();
    this.preFillUserSelections();
  }

  addonsModalShow() {
    this.activeModal = 'addons';

    // Ensure modal exists
    if (!this.addonsModal || this.addonsModal.length === 0) {
      this.addonsModal = $('#addonsModal');
    }

    if (!this.addonsModal || this.addonsModal.length === 0) {
      console.error('Addons modal not found in DOM');
      return;
    }

    this.lastSavedPaxAddonsState = structuredClone(
      this.paxAddonsState || {
        form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
        fields: {}
      }
    );
    this.lastSavedAddonsState = structuredClone(
      this.addonState || {
        form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
        fields: {}
      }
    );
    this.addonsModal.modal({ backdrop: 'static', keyboard: false }).modal('show');
  }

  hide() {
    if (this.activeModal === 'pax' && this.paxAddonsModal) {
      this.paxAddonsModal.modal('hide');
    } else if (this.activeModal === 'addons' && this.addonsModal) {
      this.addonsModal.modal('hide');
    } else {
      // fallback: hide both if state is somehow missing
      this.paxAddonsModal?.modal('hide');
      this.addonsModal?.modal('hide');
    }
  }

  getTotalPaxCount() {
    return Object.values(this.getTouristsByRateId()).reduce((sum, c) => sum + c, 0);
  }

  // Traveler count per rate group, read directly from the booking form's own rate-group
  // table (this.tblRateGrps) — NOT the per-pax addon modal's `.pax-row` (which only gets
  // created by initPaxRates() when a genuine per-pax field exists — see initAddonForm()'s
  // "CASE 2: ONLY other add-ons" branch, which never calls it). This source is always
  // present regardless of what addon fields (if any) a package has.
  getTouristsByRateId() {
    const map = {};

    // Group-rate packages: this.currentRateGroups[0].id is the REAL #table-rate-groups row's
    // id, fixed once from whichever headcount tier was selected when that row was first built
    // and never updated afterward (see changePaxCount()'s own comment for why) — it is NOT the
    // CURRENT tier's rate id once the headcount has since changed. The addon modal's own
    // .pax-row (and every other price_per_pax lookup keyed off it, e.g.
    // collectPaxAddonSelections()) is keyed by the CURRENT tier's id instead
    // (paxWiseGroupRateIds[headcount] — see initAddonForm()). Using the stale id here made a
    // display-once add-on (expandDisplayOnceAddons() calls this) fan out under a rate id
    // nothing else in paxAddonsState uses, so insertPricePerPaxBookingAddons() found no
    // matching booking transaction for it and threw — rolling back the WHOLE addon insert in
    // one shared DB transaction, silently dropping every genuine per-pax selection too.
    if (typeof isGroupRateEnabled !== 'undefined' && String(isGroupRateEnabled) === '1') {
      const $grRow = $(this.tblRateGrps.children()[0]);
      const tourists = parseInt($grRow.find('.tourists').val() || 0);
      const rateId = (typeof paxWiseGroupRateIds !== 'undefined' && paxWiseGroupRateIds[tourists])
        ? paxWiseGroupRateIds[tourists] : null;
      if (rateId && tourists > 0) {
        map[rateId] = tourists;
      }
      return map;
    }

    const rateGroups = this.currentRateGroups || [];
    const $rows = this.tblRateGrps.children();
    rateGroups.forEach((group, index) => {
      const $row = $($rows[index]);
      const tourists = parseInt($row.find('.tourists').val() || 0);
      if (group.id && tourists > 0) {
        map[group.id] = (map[group.id] || 0) + tourists;
      }
    });
    return map;
  }

  // A price_per_pax field flagged display_once is answered once (it lands in the general
  // addons_data selections) but must be billed per traveler — fan it into every rate
  // group's pax_addons_data entries here, client-side, before anything is submitted, so
  // EVERY backend consumer (PaymentIntent creation, booking creation, cart checkout,
  // Cash/Invoiced/Make-a-Payment, etc.) receives ordinary, already-fanned price_per_pax
  // data and needs no special-casing of its own — mirrors what a genuine price_per_pax
  // field's own per-traveler UI already produces naturally.
  expandDisplayOnceAddons() {
    const generalFields = this.addonState.fields?.addons?.[0];
    if (!generalFields) return;

    const displayOnceEntries = {};
    Object.keys(generalFields).forEach(fieldId => {
      const fieldData = generalFields[fieldId];
      if (fieldData && typeof fieldData === 'object' && fieldData.unit_type === 'price_per_pax') {
        displayOnceEntries[fieldId] = fieldData;
        delete generalFields[fieldId];
      }
    });

    if (!Object.keys(displayOnceEntries).length) return;

    // Stashed purely so preFillUserSelections() can restore the general "Add-ons:" dropdown/
    // checkbox back to its previously-picked value after a DOM rebuild (initAddonForm() +
    // renderPaxDetails() — e.g. a Group-rate payment-method switch, which rebuilds the whole
    // addon section from scratch). The field itself must stay OUT of this.addonState (deleted
    // just above) so it's never resubmitted through #addons_data — insertBookingAddons() would
    // otherwise create a single untied pax_id=null row for it instead of the real one-row-per-
    // traveler rows below. Without this snapshot, a rebuild left the field's own control reset
    // to its unanswered default even though the real answer was still correctly saved in
    // paxAddonsState — and if the user then saved again (e.g. after switching payment method),
    // collectAddonSelections() re-read that now-blank control and the field silently dropped off
    // every traveler, even ones who already had it.
    this._displayOnceSnapshot = displayOnceEntries;

    const touristsByRateId = this.getTouristsByRateId();
    this.paxAddonsState.fields = this.paxAddonsState.fields || {};

    Object.keys(touristsByRateId).forEach(rateId => {
      const count = touristsByRateId[rateId];
      if (count < 1) return;

      if (!this.paxAddonsState.fields[rateId] || !Array.isArray(this.paxAddonsState.fields[rateId].addons)) {
        this.paxAddonsState.fields[rateId] = { addons: [] };
      }
      const addonsArr = this.paxAddonsState.fields[rateId].addons;

      // addonsArr is SPARSE — collectPaxAddonSelections() only keeps travelers who actually
      // picked something in the real per-pax modal, so a traveler with no per-pax selection at
      // all simply has no entry here yet. Each EXISTING entry's own uid still carries its TRUE
      // ordinal in its trailing "-N" segment — 1-based for a genuine per-pax-modal entry (see
      // createPaxDetail()), already 0-based for an earlier expansion's own placeholder — the
      // same dual convention the backend's insertPricePerPaxBookingAddons() parses these uids
      // with. Padding must fill in only the ordinals NOT already claimed by an existing entry:
      // blindly appending by array length (the prior behavior) assumes existing entries already
      // occupy slots 0..N-1, which silently collides once they don't — e.g. only travelers #3/#4
      // of 4 picked something: the 2 existing entries carry true ordinals 2 and 3, but a
      // length-based pad appends placeholders ALSO numbered 2 and 3, giving travelers #3/#4 a
      // duplicate entry each and leaving travelers #1/#2 with none at all.
      const claimedOrdinals = new Set();
      addonsArr.forEach(entry => {
        const uid = entry && entry.uid;
        const m = uid ? /-(\d+)$/.exec(uid) : null;
        if (!m) return;
        claimedOrdinals.add(uid.startsWith('display-once-') ? parseInt(m[1], 10) : parseInt(m[1], 10) - 1);
      });

      for (let i = 0; i < count; i++) {
        if (claimedOrdinals.has(i)) continue;
        addonsArr.push({ uid: `display-once-${rateId}-${i}` });
      }

      addonsArr.forEach(entry => Object.assign(entry, displayOnceEntries));
    });

    // saveAddonForm()'s save flow only calls collectPaxAddonSelections()/writes
    // #pax_addons_data when the form already has a GENUINE pax_fields entry — for a form
    // with ONLY display-once fields that whole step is skipped, so nothing else would ever
    // write the data we just added back into the actual submitted hidden field. Write it
    // ourselves whenever we've actually expanded something, regardless of what the
    // surrounding call site already did (or didn't) decide about #pax_addons_data.
    if (!this.paxAddonsState.form_id) {
      this.paxAddonsState.form_id = parseInt(this.addonsData?.form?.id || 0) || null;
    }
    $('#pax_addons_data').val(JSON.stringify(this.paxAddonsState));
  }

  bindGlobalPaxButtons() {
    const self = this;

    // Increment Pax
    $(document)
      .off('click', '.inc-btn')
      .on('click', '.inc-btn', function () {
        self.changePaxCount($(this).closest('.pax-row'), 1);
        self.updateSeatAvailability();
      });

    // Decrement Pax
    $(document).off('click', '.dec-btn').on('click', '.dec-btn', function () {
      const $row = $(this).closest('.pax-row');
      self.changePaxCount($row, -1);
      self.updateSeatAvailability();
    });

    // Remove Pax Detail
    $(document).off('click', '.remove-pax').on('click', '.remove-pax', function () {
      const $detail = $(this).closest('.pax-detail');
      self.removePaxDetail($detail);
    });
  }

  // -------------------------
  // Init rows for pax (from rate groups)
  // -------------------------
  initAddonForm(rateGroups = [], totalSeatsForBooking = 0, silent = false) {
    // Captured unconditionally (before any branch below) so getTouristsByRateId() has a
    // traveler-count source even for "CASE 2: ONLY other add-ons", which never calls
    // initPaxRates() and therefore never creates the .pax-row elements.
    this.currentRateGroups = rateGroups;
    this.totalSeatsForBooking = totalSeatsForBooking;
    this.paxSection.find('.pax-list, .addon-section-title, .addons-content, .addon-divider, .payment-panel').remove();
    this.addonSection.empty();

    if (typeof isGroupRateEnabled !== 'undefined' && String(isGroupRateEnabled) === '1') {
      if (!this.hasAddons()) {
        $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
        return;
      }
      const grAddons     = this.addonsData?.form?.addons || {};
      const grPaxFields  = grAddons.pax_fields || [];
      const grOtherFields = grAddons.fields   || [];

      if (!grPaxFields.length && !grOtherFields.length) {
        $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
        return;
      }

      const grHasPax   = grPaxFields.length  > 0;
      const grHasOther = grOtherFields.length > 0;

      if (!this.paxAddonsModal || !this.paxAddonsModal.length) this.paxAddonsModal = $('#paxAddonsModal');
      if (!this.addonsModal    || !this.addonsModal.length)    this.addonsModal    = $('#addonsModal');

      const renderGrOther = () => {
        const $c = $('<div class="addons-content" id="addonFields"></div>');
        grOtherFields.forEach(field => {
          const $wrap = $('<div class="addon-fields pb-2"></div>');
          this.renderFields([field], $wrap);
          $c.append($wrap);
        });
        return $c;
      };

      if (grHasPax) {
        // Build a synthetic single rate group from the currently selected group pax count.
        // The group rate UI has one row in #table-rate-groups whose .tourists select holds
        // the chosen group size — initPaxRates reads that value via $rows[index].
        const $grRow     = $(this.tblRateGrps.children()[0]);
        const grPaxCount = parseInt($grRow.find('.tourists').val()) || 0;
        const grRateId   = (typeof paxWiseGroupRateIds !== 'undefined' && paxWiseGroupRateIds[grPaxCount])
          ? paxWiseGroupRateIds[grPaxCount] : 0;
        const grRate     = (typeof paxWiseGroupRates !== 'undefined' && paxWiseGroupRates[grPaxCount])
          ? paxWiseGroupRates[grPaxCount] : 0;
        const syntheticGroups = [{ id: grRateId, rate_for: 'Group', rate: grRate, additional_charge: 0 }];

        this.activeModal = 'pax';

        // Cap the row's max to the group-rate table's own highest priced headcount (not the raw
        // venue capacity, which can be far larger than this package's group-rate pricing actually
        // covers) — mirrors how the main page's own "Number of People" dropdown only offers
        // headcounts addTouristsOptsForGroupRates() finds a paxWiseGroupRates price for.
        // Previously this was capped to the CURRENTLY selected headcount itself, which made max
        // always equal the initial count and permanently disabled the "+" stepper — changePaxCount()
        // syncs back to the main page's own .tourists select precisely so headcount CAN be
        // increased from inside this modal too.
        const groupRateHeadcounts = (typeof paxWiseGroupRates !== 'undefined')
          ? Object.keys(paxWiseGroupRates).map(Number).filter(n => !isNaN(n))
          : [];
        const maxGroupRatePax = groupRateHeadcounts.length ? Math.max(...groupRateHeadcounts) : 0;

        const prevTotalSeats = this.totalSeatsForBooking;
        this.totalSeatsForBooking = maxGroupRatePax > 0
          ? Math.min(prevTotalSeats || maxGroupRatePax, maxGroupRatePax)
          : (prevTotalSeats || grPaxCount);
        this.initPaxRates(syntheticGroups);
        this.totalSeatsForBooking = prevTotalSeats;

        if (grHasOther) {
          this.paxSection.append(`<hr class="addon-divider" style="border:0;border-top:1px dashed #ddd;margin:10px 0;">`);
          this.paxSection.append(`<h5 class="addon-section-title text-lg font-semibold text-[#111827]">Add-ons:</h5>`);
          this.paxSection.append(renderGrOther());
        }

        // Switching payment method (Card/Cash/Pay Later) rebuilds this SAME select's <option>
        // list to show the right price per method, then re-applies the SAME headcount and fires
        // a namespaced 'change.select2' purely so Select2's own widget re-renders its label (see
        // PaymentHandler.handleNewPricingOnPaymentMethodChange()) — jQuery still invokes this
        // element's plain `onchange="getSelectedTouristsGroup(this)"` for that trigger regardless
        // of namespace, which lands right back here with the SAME grPaxCount. Only pop the modal
        // open for a genuine headcount change, not this same-value refresh echo — the pax-list/
        // add-ons DOM above is still rebuilt either way so its content stays correct even while
        // hidden.
        const groupRateHeadcountChanged = this._lastGroupRatePaxCount !== grPaxCount;
        this._lastGroupRatePaxCount = grPaxCount;

        if (!silent && groupRateHeadcountChanged) this.paxModalShow();
        this.updateSeatAvailability();
        return;
      }

      // Only other fields (one_time / price_per_unit)
      this.activeModal = 'addons';
      grOtherFields.forEach(field => {
        const $wrap = $('<div class="addon-fields pb-2"></div>');
        this.renderFields([field], $wrap);
        this.addonSection.append($wrap);
      });
      if (!silent) this.addonsModalShow();
      this.updateSeatAvailability();
      return;
    }

    // Check if package has addons before proceeding
    if (!this.hasAddons()) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
      return;
    }

    // const allAddons = this.addonsData?.form?.addons || {};
    const addons = this.addonsData?.form?.addons || {};
    const paxFields = addons.pax_fields || [];
    const otherFields = addons.fields || [];

    const hasPax = paxFields.length > 0;
    const hasOther = otherFields.length > 0;

    // ---------- INTERNAL HELPER FUNCTIONS ----------
    const renderOtherAddons = () => {
      const $container = $('<div class="addons-content" id="addonFields"></div>');
      otherFields.forEach(field => {
        const $wrap = $('<div class="addon-fields pb-2"></div>');
        this.renderFields([field], $wrap);
        $container.append($wrap);
      });
      return $container;
    };

    const openModalIfNeeded = () => {
      if (silent) return;
      if (this.activeModal === 'pax') this.paxModalShow();
      else if (this.activeModal === 'addons') this.addonsModalShow();
    };

    // Ensure modals exist (don't remove them, they're part of the DOM)
    const ensureModalsExist = () => {
      // Re-initialize modal references if they were lost
      if (!this.paxAddonsModal || this.paxAddonsModal.length === 0) {
        this.paxAddonsModal = $('#paxAddonsModal');
      }
      if (!this.addonsModal || this.addonsModal.length === 0) {
        this.addonsModal = $('#addonsModal');
      }
    };

    // ---------- CASE 1: BOTH paxFields + otherFields ----------
    if (hasPax && hasOther) {
      this.activeModal = 'pax';
      ensureModalsExist();

      // Render pax rate rows
      this.initPaxRates(rateGroups);

      // Divider + Title
      this.paxSection.append(`
          <hr class="addon-divider" style="border:0; border-top:1px dashed #ddd; margin:10px 0;">
      `);
      this.paxSection.append(`<h5 class="addon-section-title text-lg font-semibold text-[#111827]">Add-ons:</h5>`);

      // Render OTHER add-ons inside pax modal area
      this.paxSection.append(renderOtherAddons());

      openModalIfNeeded();
      this.updateSeatAvailability();
      return;
    }

    // ---------- CASE 2: ONLY other add-ons ----------
    if (hasOther && !hasPax) {
      this.activeModal = 'addons';
      ensureModalsExist();

      // Render other add-ons in main addonSection
      otherFields.forEach(field => {
        const $wrap = $('<div class="addon-fields pb-2"></div>');
        this.renderFields([field], $wrap);
        this.addonSection.append($wrap);
      });

      openModalIfNeeded();
      this.updateSeatAvailability();
      return;
    }

    // ---------- CASE 3: ONLY pax add-ons ----------
    if (hasPax && !hasOther) {
      this.activeModal = 'pax';
      ensureModalsExist();

      this.initPaxRates(rateGroups);
      this.renderFinalCost();
      openModalIfNeeded();
      this.updateSeatAvailability();
      return;
    }

    // ---------- CASE 4: No addons at all ----------
    this.updateSeatAvailability();
  }

  finalPriceDisplay() {
    return `
      <div class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 payment-panel">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium">Cash</span>
          <span class="text-base font-medium cash-price">$0.00</span>
        </div>
      </div>
      <div class="mt-1 rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 payment-panel">
        <div class="flex items-center justify-between">
          <span class="text-sm font-medium">Card</span>
          <span class="text-base font-medium card-price">$0.00</span>
        </div>
      </div>
    `;
  }

  // Integrate rate groups on modal [paxAddonsModal | addonsModal]
  initPaxRates(rateGroups) {
    this.paxSection.append('<div class="pax-list"></div>');
    // Final Price Display
    this.paxSection.append(this.finalPriceDisplay());

    const $paxList = this.paxSection.find('.pax-list');
    const totalSeats = this.totalSeatsForBooking || 0;
    const $rows = this.tblRateGrps.children(); // cache

    rateGroups.forEach((group, index) => {
      const $row = $($rows[index]);

      // --- Fetch tourists value ---
      const tourists = parseInt($row.find('.tourists').val() || 0);

      // --- Fetch permit fee (two different inputs possible) ---
      let $permitInput = $row.find('.permit-fee');
      if (isNaN(parseInt($permitInput.val()))) {
        $permitInput = $row.find('.permit-fee1');
      }
      const permitFee = parseFloat($permitInput.val()) || 0;
      const partnerFee =
        (typeof isPartnerPackage !== 'undefined' && isPartnerPackage)
          ? (group.partner_fee_amount || 0)
          : 0;
      const groupTotal =
        parseFloat(group.rate || 0) +
        parseFloat(group.additional_charge || 0) +
        parseFloat(permitFee || 0) +
        parseFloat(partnerFee || 0);

      // This is only for Price per pax add-ons
      const rowHtml = this.createPaxRow({
        rate: group.id,
        type: group.rate_for,
        label: group.rate_for,
        price: groupTotal.toFixed(2),
        min: 0,
        max: totalSeats,
        initial: tourists
      });

      $paxList.append(rowHtml);
    });
  }

  updateSeatAvailability() {
    const totalSeats = this.totalSeatsForBooking || 0;
    if (!totalSeats) return;

    const $rows = this.paxSection.find('.pax-row');
    if ($rows.length === 0) return;

    let used = 0;
    // Sum all current pax counts
    $rows.each((_, row) => {
      const count = parseInt(row.querySelector('.pax-count')?.value || 0);
      used += count;
    });

    if (this._lastUsedSeats === used) return;
    this._lastUsedSeats = used;

    const remaining = Math.max(0, totalSeats - used);

    // Update buttons based on remaining seats
    $rows.each((_, row) => {
      const countInput = row.querySelector('.pax-count');
      const incBtn = row.querySelector('.inc-btn');
      const decBtn = row.querySelector('.dec-btn');

      const current = parseInt(countInput?.value || 0);

      // Disable "+" if no remaining seats
      if (incBtn) incBtn.disabled = remaining === 0;

      // Disable "-" if at 0
      if (decBtn) decBtn.disabled = current <= 0;
    });
  }

  // -------------------------
  // Create pax row
  // -------------------------
  changePaxCount($row, delta) {
    const formId = parseInt(this.addonsData?.form?.id || 0) || 0;
    if (!this.paxAddonsState || typeof this.paxAddonsState !== "object") {
      this.paxAddonsState = { form_id: formId, fields: {} };
    }
    if (!this.addonState || typeof this.addonState !== "object") {
      this.addonState = { form_id: formId, fields: {} };
    }

    // --------------------------------------
    // 1. SAVE CURRENT STATE BEFORE ANY CHANGES
    // --------------------------------------
    this.collectPaxAddonSelections();
    this.collectAddonSelections();

    // --------------------------------------
    // 2. Read row details (cached)
    // --------------------------------------
    const rateId = $row.data('rate-id');
    const type = $row.data('type');
    const label = $row.find('.pax-info strong').text();
    const price = parseFloat($row.data('price'));
    const min = parseInt($row.data('min'));
    const max = parseInt($row.data('max'));
    const $input = $row.find('.pax-count');
    // The synthetic "Group" row (group-rate packages) is stamped with the CURRENT tier's own
    // rate id (paxWiseGroupRateIds[headcount] — see initAddonForm()), which changes every time
    // the headcount/tier changes and does NOT match the real #table-rate-groups .tourists
    // select's id — that id is set once, from the slot's rateGroups[0].id, when the row is first
    // built, and never updated afterward. An id-based lookup therefore silently finds nothing for
    // group rate, so incrementing/decrementing here never synced back to the main page's own
    // headcount/pricing. Group rate only ever renders ONE row, so resolve it positionally instead
    // — exactly how initAddonForm()/initPaxRates() already locate it via tblRateGrps.children()[0].
    const isGroupRateRow = type === 'Group'
      && typeof isGroupRateEnabled !== 'undefined'
      && String(isGroupRateEnabled) === '1';
    const $pageRow = isGroupRateRow
      ? $('#table-rate-groups').children().eq(0).find('.tourists')
      : $('#table-rate-groups').find(`#tourists-${rateId}`);

    let val = parseInt($input.val()) || 0;
    val += delta;

    // Validate min/max
    if (val < min || val > max) return;

    // Ensure rate group exists in state
    if (!this.paxAddonsState.fields[rateId]) {
      this.paxAddonsState.fields[rateId] = { addons: [] };
    }
    // this.addonState.fields = this.addonState.fields || {};
    const $detailsContainer = $(this.paxDetailsSelector);

    // --------------------------------------
    // 3. ADD PAX
    // --------------------------------------
    if (delta > 0) {
      const $detail = this.createPaxDetail({ type, label, price, rateId });
      $detailsContainer.append($detail);

      const uid = $detail.data("uid");
      this.paxAddonsState.fields[rateId].addons.push({ uid });
    }

    // --------------------------------------
    // 4. REMOVE PAX
    // --------------------------------------
    if (delta < 0) {
      const $details = $detailsContainer.find(`.pax-detail[data-rate-id="${rateId}"]`);
      const $last = $details.last();

      if ($last.length) {
        this.removePaxDetail($last);
      }
    }

    // Recalculate actual count after add/remove
    const remaining = $detailsContainer.find(`.pax-detail[data-rate-id="${rateId}"]`).length;
    $input.val(remaining);
    if ($pageRow.length) $pageRow.val(remaining).trigger('change');

    if (remaining === 0) {
      // Remove empty entry from paxAddonsState
      delete this.paxAddonsState.fields[rateId];

      // Hide modal + addon sections only if no pax details remain
      if ($detailsContainer.find(".pax-detail").length === 0) {
        this.hide();
        $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass("d-none");
      }
    } else {
      // Show addon button if we have pax and addons available
      if (this.hasAddons() && $detailsContainer.find(".pax-detail").length > 0) {
        $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass("d-none");
      }
    }

    this.preFillUserSelections();
    this.collectPaxAddonSelections();
    this.collectAddonSelections();
    this.saveAllToTextarea();
    this.updateSeatAvailability();
  }

  createPaxRow({ rate, type, label, price, min, max, initial }) {
    // Create root div
    const row = document.createElement("div");
    row.className = "pax-row flex flex-wrap border border-[#F3F4F6] rounded-lg px-3 py-2 items-center mb-2";
    row.dataset.type = type;
    row.dataset.rateId = rate;
    row.dataset.price = price;
    row.dataset.min = min;
    row.dataset.max = max;

    // Spinner container
    const spinner = document.createElement("div");
    spinner.className = "pax-spinner me-2 flex gap-2";

    // "-" button
    const decBtn = document.createElement("button");
    decBtn.type = "button";
    decBtn.className = "dec-btn btn-primary-small";
    decBtn.textContent = "-";

    // Input
    const input = document.createElement("input");
    input.type = "number";
    input.className = "pax-count form-custom-input !font-bold text-center text-base";
    input.min = min;
    input.max = max;
    input.value = initial || 0;
    input.readOnly = true;

    // "+" button
    const incBtn = document.createElement("button");
    incBtn.type = "button";
    incBtn.className = "inc-btn btn-primary-small";
    incBtn.textContent = "+";

    // Append spinner children
    spinner.appendChild(decBtn);
    spinner.appendChild(input);
    spinner.appendChild(incBtn);

    // Label
    const info = document.createElement("div");
    info.className = "pax-info";
    info.innerHTML = `<strong>${label}</strong>`; // safe because label comes from backend

    // Price with currency
    const priceDiv = document.createElement("div");
    priceDiv.className = "pax-price ml-auto";

    const cf = Components.currencyFormation; // uses your getter
    priceDiv.textContent = `${cf.symbol}${price}`;

    // Assemble row
    row.appendChild(spinner);
    row.appendChild(info);
    row.appendChild(priceDiv);

    // Return as jQuery object to preserve compatibility
    return $(row);
  }

  // -------------------------
  // Pax detail creation
  // -------------------------
  createPaxDetail({ type, index, label, price, rateId }) {
    // Unique UID
    var slotTime = $('#slot_time').val();
    const uid = slotTime + '-' + Date.now() + this.generatePaxIndex(10) + '-' + index;

    // Root container
    const root = document.createElement("div");
    root.className = "pax-detail border border-[#E5E7EB] rounded-lg mb-3 overflow-hidden";
    root.dataset.type = type;
    root.dataset.rateId = rateId;
    root.dataset.uid = uid;

    // Wrapper for title + remove button
    const headerWrap = document.createElement("div");
    headerWrap.className = "pax-header !flex items-center justify-between w-full px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label"; // add your own class if needed

    // Title section
    const h4 = document.createElement("h4");

    const labelNode = document.createTextNode((index ? `${label} ${index}` : label) + " — ");

    const priceSpan = document.createElement("span");
    priceSpan.className = "pax-price";
    priceSpan.dataset.basePrice = price;

    const cf = Components.currencyFormation; // dynamic getter
    priceSpan.textContent = `${cf.symbol}${price}`;

    h4.appendChild(labelNode);
    h4.appendChild(priceSpan);

    // Remove button
    const removeBtn = document.createElement("div");
    removeBtn.className = "remove-pax text-[#A32624] ml-2 cursor-pointer";
    removeBtn.textContent = "×";

    // Add h4 & removeBtn into one wrapper
    headerWrap.appendChild(h4);
    headerWrap.appendChild(removeBtn);

    // Addon fields wrapper
    const addonsWrap = document.createElement("div");
    addonsWrap.className = "addon-fields p-3";

    // Assemble structure
    root.appendChild(headerWrap);
    root.appendChild(addonsWrap);

    // Render addon fields into addonsWrap
    const fields = this.addonsData?.form?.addons?.pax_fields || [];
    this.renderFields(fields, $(addonsWrap), uid);

    // Remove event (kept 100% same behaviour)
    $(removeBtn).on("click", () => this.removePaxDetail($(root)));

    // Return as jQuery object for compatibility
    return $(root);
  }

  generatePaxIndex(length) {
    return Math.random().toString(36).substring(2, 2 + length);
  }

  /** Handle pax detail removal */
  removePaxDetail($detail) {
    if (!$detail || !$detail.length) return;

    // ------- 1) Capture selection state before removal (VERY important)
    this.collectPaxAddonSelections();
    this.collectAddonSelections();

    const uid = $detail.data("uid");
    const rateId = $detail.data("rate-id");
    const $row = this.paxSection.find(`.pax-row[data-rate-id="${rateId}"]`);
    const $input = $row.find('.pax-count');
    const $container = $(this.paxDetailsSelector);

    // ------- 2) Ensure state object exists
    if (!this.paxAddonsState || typeof this.paxAddonsState !== "object") {
      this.paxAddonsState = {
        form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
        fields: {}
      };
    }

    // ------- 3) Remove this pax instance from state
    const group = this.paxAddonsState.fields?.[rateId];
    if (group && Array.isArray(group.addons)) {
      const idx = group.addons.findIndex(p => p.uid === uid);
      if (idx !== -1) group.addons.splice(idx, 1);

      // If group is empty → delete whole group
      if (group.addons.length === 0) {
        delete this.paxAddonsState.fields[rateId];
      }
    }

    // ------- 4) Remove DOM element
    $detail.remove();

    // ------- 5) Recount pax of this rateId
    const remaining = $container
      .find(`.pax-detail[data-rate-id="${rateId}"]`)
      .length;

    // Update input spinner
    $input.val(remaining);

    // Update rate-group table
    const $pageRow = this.tblRateGrps.find(`#tourists-${rateId}`);
    if ($pageRow.length) $pageRow.val(remaining).trigger("change");

    // ------- 6) If no pax left at all → full reset
    const totalPax = this.getTotalPaxCount();
    if (totalPax <= 0) {
      this.hide();
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass("d-none");
      $(this.paxDetailsSelector).empty();
      return;
    } else {
      // Show addon button if we have pax and addons available
      if (this.hasAddons()) {
        $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass("d-none");
      }
    }

    // ------- 7) Reassign indexes for each pax of this rate (if needed)
    $container.find(`.pax-detail[data-rate-id="${rateId}"]`).each((i, el) => {
      $(el).attr("data-rate-id-display", `${rateId}-${i}`);
    });

    // ------- 8) Recollect and save state again
    this.collectPaxAddonSelections();
    this.collectAddonSelections();
    this.saveAllToTextarea();

    // ------- 9) Recalculate UI prices
    this.updateAddonsTotalUI();
    this.renderFinalCost();
    this.preFillUserSelections();
  }

  // -------------------------
  // Render helpers (fragment-based)
  // -------------------------
  renderPaxAddonBtn() {
    const $container = $(this.addonSectionSelector);

    // Clear before adding new content
    $container.empty();

    // Only render button if package has addons
    if (!this.hasAddons()) {
      $container.addClass('d-none');
      return;
    }

    // Wrapper for icons + button
    const $wrapper = $('<div class="addon-btn-wrapper" style="position: relative; display: inline-block;"></div>');

    // Warning icon (hidden by default)
    const $warning = $('<i class="fa fa-exclamation-circle addon-required-warning"></i>').css({
      color: 'red',
      fontSize: '18px',
      position: 'absolute',
      transform: 'translate(25%, 60%)',
      display: 'none'
    });

    // Fulfilled icon (hidden by default)
    const $fulfilled = $('<i class="fa fa-check-circle addon-required-fulfilled"></i>').css({
      color: '#0ded0d',
      fontSize: '18px',
      position: 'absolute',
      transform: 'translate(25%, 60%)',
      display: 'none'
    });

    // Add-ons button
    const $btn = $(`
      <button type="button" class="btn-add-addons btn-primary-regular">
        <i class="fas fa-plus-circle"></i> Add-ons
      </button>
    `);

    // Append to wrapper
    $wrapper.append($warning, $fulfilled, $btn);

    // Inject into target section
    $container.append($wrapper);

    // Show container if we have addons
    $container.removeClass('d-none');
  }

  renderPaxDetails() {
    const $container = $(this.paxDetailsSelector);

    // Always render button first to ensure it exists
    this.renderPaxAddonBtn();

    if (!$container || !$container.length) {
      return;
    }

    // Check if package has addons
    if (!this.hasAddons()) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
      return;
    }

    // IMPORTANT: Collect current state BEFORE clearing
    // BUT only if there's actually content to collect (not during initial cart edit load)
    if ($container.children('.pax-detail').length > 0) {
      this.collectPaxAddonSelections();
    }

    $container.empty();

    this._runLogics = this._runLogics || [];

    let paxCount = 0;
    const frag = document.createDocumentFragment();

    // Iterate all pax-row items
    this.paxSection.find('.pax-row').each((_, row) => {
      const $row = $(row);
      const type = $row.data('type');
      const label = $row.find('.pax-info strong').text();
      const price = $row.data('price');
      const count = parseInt($row.find('.pax-count').val() || 0);
      const rateId = $row.data('rate-id');

      const savedAddons = this.paxAddonsState.fields?.[rateId]?.addons || [];

      // Build each pax detail node  
      for (let i = 0; i < count; i++) {
        const pindex = i + 1;

        const $detail = this.createPaxDetail({
          type,
          index: pindex,
          label,
          price,
          rateId
        });

        // Prefill previously saved values (if any) — match by the TRUE 1-based position
        // embedded in the saved entry's uid trailing "-N" segment (see createPaxDetail()),
        // NOT savedAddons[i]. savedAddons is SPARSE — collectPaxAddonSelections() only keeps
        // pax that actually have a selection — so a plain positional lookup silently compacts
        // gaps: e.g. pax #1 and #3 have selections, #2 doesn't → savedAddons is [pax1, pax3],
        // and savedAddons[1] (this loop's card #2, pindex=2) would wrongly resolve to pax #3's
        // data instead of staying blank, moving the add-on onto the wrong passenger.
        const saved = savedAddons.find(a => {
          if (!a || !a.uid) return false;
          const m = /-(\d+)$/.exec(a.uid);
          return m && parseInt(m[1], 10) === pindex;
        });
        if (saved) {
          // Update the UID to match saved state
          if (saved.uid) {
            $detail.attr('data-uid', saved.uid);
            $detail.data('uid', saved.uid);
          }

          Object.keys(saved).forEach(nodeId => {
            if (nodeId !== 'uid') {
              this.recursiveFillNode($detail[0], nodeId, saved[nodeId]);
            }
          });
        }

        frag.appendChild($detail[0]);
        paxCount++;
      }
    });

    // Append all at once (fast)
    $container[0].appendChild(frag);

    // Button visibility logic
    $('.btn-pax-addons').toggle(paxCount > 0);

    // Show/hide addon button based on pax count and addon availability
    if (paxCount > 0 && this.hasAddons()) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass('d-none');
    } else if (paxCount === 0) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass('d-none');
    }

    // Prefill BEFORE collecting (important for cart edit)
    this.preFillUserSelections();

    // Sync states AFTER prefilling
    this.collectPaxAddonSelections();
    this.collectAddonSelections();
    this.saveAllToTextarea();

    // Re-run logic after DOM settles
    setTimeout(() => {
      // Run logic for pax detail fields
      $(`${this.paxDetailsSelector} .addon-fields`).each((_, wrapEl) => {
        if (typeof wrapEl._logicRunAll === 'function') {
          try { wrapEl._logicRunAll(); } catch (_) { }
        }
      });

      // Run rule logic for other addon sections
      $('.addons-content .addon-fields').each((_, wrapEl) => {
        if (typeof wrapEl._logicRunAll === 'function') {
          try { wrapEl._logicRunAll(); } catch (_) { }
        }
      });

      // Global logic runners
      if (Array.isArray(this._runLogics)) {
        this._runLogics.forEach(fn => {
          try { fn(); } catch (_) { }
        });
      }

      // Trigger UI update
      $(`${this.paxDetailsSelector} .addon-fields`)
        .find('input, select, textarea')
        .trigger('change');

      $('.addons-content .addon-fields')
        .find('input, select, textarea')
        .trigger('change');

      // Recalculate pax totals
      $(`${this.paxDetailsSelector} .pax-detail`).each((_, el) => {
        this.updatePricePerPaxUI($(el));
      });

      this.updateAddonsTotalUI();
      this.renderFinalCost();
    }, 0);
  }

  // Save current state to a hidden textarea (same behavior as original)
  saveAllToTextarea() {
    const $paxTextarea = $('#pax_addons_data');
    const $addonTextarea = $('#addons_data');

    // ---- Helper: check if a pax fields structure is empty ----
    const isPaxEmpty = (state) => {
      if (!state?.fields) return true;

      return Object.values(state.fields).every(group => {
        if (!group?.addons || !group.addons.length) return true;

        // Each addon must have only { uid } or be empty
        return group.addons.every(addon =>
          !addon || Object.keys(addon).length <= 1 // only uid or empty
        );
      });
    };

    // ---- Helper: check if addonState.fields is empty ----
    const isAddonEmpty = (state) => {
      if (!state?.fields) return true;

      return Object.keys(state.fields).length === 0;
    };

    // -----------------------------
    // 1. Save paxAddonsState
    // -----------------------------
    if ($paxTextarea.length) {
      const paxState = this.paxAddonsState || { form_id: 0, fields: {} };
      $paxTextarea.val(isPaxEmpty(paxState) ? '' : JSON.stringify(paxState));
    }

    // -----------------------------
    // 2. Save addonState
    // -----------------------------
    if ($addonTextarea.length) {
      const addonState = this.addonState || { form_id: 0, fields: {} };
      $addonTextarea.val(isAddonEmpty(addonState) ? '' : JSON.stringify(addonState));
    }
  }

  // Mostly work for edit cart to fetch and retrieve user selections
  loadStateFromTextareas() {
    if (typeof addonFormReader === "undefined") return;

    const parseJSON = (str, fallback) => {
      try {
        return str ? JSON.parse(str) : fallback;
      } catch (e) {
        console.warn("Invalid JSON in textarea:", e);
        return fallback;
      }
    };

    // --- Defaults ---
    const emptyPax = { form_id: 0, fields: {} };
    const emptyAddon = { form_id: 0, fields: {} };

    // --- Read from textareas ---
    const paxStr = $("#pax_addons_data").val();
    const addonStr = $("#addons_data").val();

    // --- Parse safely ---
    this.paxAddonsState = parseJSON(paxStr, emptyPax);
    this.addonState = parseJSON(addonStr, emptyAddon);

    // --- Keep a deep snapshot for Cancel button ---
    // this.lastSavedPaxAddonsState = JSON.parse(JSON.stringify(this.paxAddonsState));
    this.lastSavedPaxAddonsState = structuredClone(this.paxAddonsState);
    this.lastSavedAddonsState = structuredClone(this.addonState);
  }

  // renderFields: builds DOM for many nodes efficiently and attaches logic
  renderFields(nodes, $container, paxUid = null) {
    if (!Array.isArray(nodes) || !$container?.length) return;

    const wrapEl = $container[0];

    // Build everything inside a fragment → fastest way
    const frag = document.createDocumentFragment();

    for (const node of nodes) {
      const fieldEl = Components.renderField(node, paxUid);
      if (!fieldEl) continue;

      // Ensure nodeId exists for lookup
      if (!fieldEl.dataset.nodeId) {
        fieldEl.dataset.nodeId = node.id;
      }

      frag.appendChild(fieldEl);
    }

    // Append fragment to container
    wrapEl.appendChild(frag);

    // Now attach logic rules AFTER DOM is placed
    Components.attachLogicToContainer(wrapEl, nodes);

    // Add numeric validation (optimized)
    // Use one query, not many
    $container
      .find('input[type="number"]')
      .off('input.numVal')
      .on('input.numVal', function () {
        const $input = $(this);
        const min = parseFloat(this.min);
        const max = parseFloat(this.max);
        let val = parseFloat(this.value);

        // Reset
        $input.removeClass('is-invalid');
        $input.siblings('.invalid-feedback').remove();

        if (!isNaN(val)) {
          let invalid = false;
          let msg = '';

          if (!isNaN(min) && val < min) {
            invalid = true;
            msg = `Minimum value is ${min}`;
            val = min;
          } else if (!isNaN(max) && val > max) {
            invalid = true;
            msg = `Maximum value is ${max}`;
            val = max;
          }

          if (invalid) {
            $input.addClass('is-invalid');
            $('<div class="invalid-feedback mt-1 text-xs text-[#020817]">')
              .text(msg)
              .insertAfter($input);
          }

          // Update final value
          this.value = val;
        }
      });
  }

  // -------------------------
  // Prefill logic (recursive)
  // -------------------------
  recursiveFillNode(container, nodeId, nodeData) {
    if (!nodeId || !nodeData) return;

    const root = container instanceof jQuery ? container[0] : container;
    if (!root) return;

    // Find this field
    const el = root.querySelector(`.addon-card[data-node-id="${nodeId}"]`);
    if (!el) return;

    const $el = $(el);
    const $input = $el.find('> .form-check input, > label.addon-radio-option input[type="radio"], > select, > input, > textarea').first();

    if ($input.length) {
      const { type, value, option_id } = nodeData;

      switch (type) {
        case "checkbox":
          $input.prop("checked", value === "checked");
          break;

        case "radio": {
          const selector = option_id != null
            ? `input[type="radio"][data-option-id="${option_id}"]`
            : `input[type="radio"][value="${value}"]`;

          const radio = el.querySelector(selector);
          if (radio) radio.checked = true;
          break;
        }

        case "select": {
          let sel = $input;
          if (option_id != null) {
            const $opt = sel.find(`option[data-option-id="${option_id}"]`);
            if ($opt.length) {
              sel.val($opt.val());
              break;
            }
          }
          sel.val(value);
          break;
        }

        case "number":
          $input.val(value);
          break;

        case "text":
        case "textbox":
        case "textarea":
          $input.val(value);
          break;
      }

      // Trigger change AFTER setting the main field
      $input.trigger("change");
    }

    // Now fill children recursively (if any)
    if (Array.isArray(nodeData.children) && nodeData.children.length) {
      for (const childObj of nodeData.children) {
        const childId = Object.keys(childObj)[0];
        if (!childId) continue;

        const childData = childObj[childId];
        if (!childData) continue;

        this.recursiveFillNode(el, childId, childData);
      }
    }
  }

  // -------------------------
  // Field lookup helper
  // -------------------------
  getFieldDefById(fieldId, fields = null) {
    if (!fieldId) return null;

    const targetId = parseInt(fieldId);

    // ------------------------------
    // 1. Build cache if not built yet
    // ------------------------------
    if (!this._fieldCache) {
      this._fieldCache = {};

      const allFields = this.addonsData?.form?.fields || [];

      const walk = (nodes) => {
        if (!Array.isArray(nodes)) return;
        for (const node of nodes) {
          const id = parseInt(node.id);
          if (!isNaN(id)) {
            this._fieldCache[id] = node;
          }

          // rules → children
          if (Array.isArray(node.rules)) {
            for (const rule of node.rules) {
              if (Array.isArray(rule.children)) walk(rule.children);
            }
          }

          // direct children
          if (Array.isArray(node.children)) {
            walk(node.children);
          }
        }
      };

      walk(allFields);
    }

    // ------------------------------
    // 2. If cached, return instantly
    // ------------------------------
    if (this._fieldCache[targetId]) {
      return this._fieldCache[targetId];
    }

    // ------------------------------
    // 3. Fallback (should rarely run)
    // ------------------------------
    const search = (nodes) => {
      if (!Array.isArray(nodes)) return null;

      for (const node of nodes) {
        const id = parseInt(node.id);
        if (id === targetId) return node;

        // rules
        if (Array.isArray(node.rules)) {
          for (const rule of node.rules) {
            const found = search(rule.children);
            if (found) return found;
          }
        }

        // direct children
        if (Array.isArray(node.children)) {
          const found = search(node.children);
          if (found) return found;
        }
      }
      return null;
    };

    const allFields = fields || this.addonsData?.form?.fields || [];
    const result = search(allFields);

    // Cache the fallback result for next time
    if (result) this._fieldCache[targetId] = result;

    return result;
  }

  // -------------------------
  // Collect single node's data into serializable object
  // -------------------------
  collectNodeData($node) {
    if (!$node || !$node.length) return null;

    const nodeId = parseInt($node.data('nodeId') || $node.attr('data-node-id'));
    if (!nodeId) return null;

    // -----------------------------
    // Fast lookup from optimized cache
    // -----------------------------
    const nodeDef = this.getFieldDefById(nodeId);
    if (!nodeDef) return null;

    // -----------------------------
    // Cache DOM values
    // -----------------------------
    const $input = $node.find('> .form-check input, > label.addon-radio-option input[type="radio"], > select, > input, > textarea').first();
    if ($input.length === 0) return null;

    const dataNode = $node[0]; // direct DOM access is faster
    const nodePrice = parseFloat(dataNode.dataset.fee) || 0;
    const fieldPriceEnabled = parseInt(dataNode.dataset.additionalFee || 0);

    let type = null;
    let value = null;
    let optionId = null;
    let optionLabel = null;
    let optionPrice = 0;
    let optionPriceEnabled = 0;

    // ---------------------------------
    // VALUE EXTRACTION
    // ---------------------------------

    if ($input[0].type === 'radio') {
      const checked = $node.find("input[type='radio']:checked")[0];
      if (checked) {
        type = 'radio';
        value = checked.value;
        optionId = checked.dataset.optionId || checked.dataset.optionId;
        optionPrice = parseFloat(checked.dataset.fee) || 0;
        optionPriceEnabled = parseInt(checked.dataset.optionAdditionalFee || 0);

        // resolve label from def
        const optDef = nodeDef.options?.find(o => o.id == optionId);
        if (optDef) optionLabel = optDef.label;
      }

    } else if ($input[0].type === 'checkbox') {
      if ($input[0].checked) {
        type = 'checkbox';
        value = 'checked';
        optionId = $input[0].dataset.optionId || null;
      }

    } else if ($input[0].tagName === 'SELECT') {
      const opt = $input[0].selectedOptions[0];
      if (opt && opt.value !== '') {
        type = 'select';
        value = opt.value;
        optionId = opt.dataset.optionId || null;
        optionPrice = parseFloat(opt.dataset.fee) || 0;
        optionPriceEnabled = parseInt(opt.dataset.optionAdditionalFee || 0);

        const optDef = nodeDef.options?.find(o => o.id == optionId);
        if (optDef) optionLabel = optDef.label;
      }

    } else if ($input[0].type === 'number') {
      const qty = parseFloat($input.val()) || 0;
      if (qty > 0) {
        type = 'number';
        value = qty;
      }

    } else {
      const val = $input.val().trim();
      if (val !== '') {
        type = ($input[0].tagName === 'TEXTAREA') ? 'textarea' : 'text';
        value = val;
      }
    }

    if (!type) return null;

    // ---------------------------------
    // FEE CALCULATIONS
    // ---------------------------------
    const fieldFee = Math.round(nodePrice * this.bookingServiceCommission / 100 * 100) / 100;
    const optionFee = Math.round(optionPrice * this.bookingServiceCommission / 100 * 100) / 100;

    // ---------------------------------
    // RULE SNAPSHOT (unchanged)
    // ---------------------------------
    let ruleId = null;
    let appliedRuleSnapshot = null;

    if (nodeDef.rules?.length) {
      const match = nodeDef.rules.find(r => {
        if (r.option_id && r.option_id == optionId) return true;
        if (r.operator === 'checked' && value === 'checked') return true;
        if (r.operator === 'equals' && value == r.value) return true;
        return false;
      });

      if (match) {
        ruleId = match.id;
        appliedRuleSnapshot = {
          operator: match.operator,
          value: match.value,
          option_id: match.option_id || null
        };
      }
    }

    // ---------------------------------
    // BUILD DATA OBJECT
    // ---------------------------------
    const data = {
      type,
      field_id: nodeId,
      field_label: nodeDef.label || '',
      option_label: optionLabel,
      rule_id: ruleId,
      unit_type: nodeDef.unit_type || null,
      applied_rule_snapshot: appliedRuleSnapshot,
      value,
      option_id: optionId,
      field_price_enabled: fieldPriceEnabled,
      option_price_enabled: optionPriceEnabled,
      field_price: nodePrice,
      option_price: optionPrice,
      field_fee: fieldFee,
      option_fee: optionFee
    };

    // ---------------------------------
    // CHILDREN (fast + safe)
    // ---------------------------------
    const children = [];
    $node.children('.indent').children('.addon-card').each((_, el) => {
      const childData = this.collectNodeData($(el));
      if (childData) children.push(childData);
    });

    if (children.length) {
      data.children = children;
    }

    return { [nodeId]: data };
  }

  // -------------------------
  // Build paxState (all selections)
  // -------------------------
  collectPaxAddonSelections() {
    // ensure structure exists
    const state = this.paxAddonsState;
    state.fields = state.fields || {};

    const paxRows = this.paxSection[0].querySelectorAll('.pax-row');

    paxRows.forEach(row => {
      const rateId = row.dataset.rateId;
      const countInput = row.querySelector('.pax-count');
      const count = countInput ? parseInt(countInput.value || 0) : 0;

      if (!rateId || count <= 0) {
        delete state.fields[rateId];
        return;
      }

      // Will hold addons for all pax under this rateId
      const addonsArr = [];

      // Select pax-details efficiently via DOM, avoid jQuery selector cost
      const detailsSelector = `${this.paxDetailsSelector} .pax-detail[data-rate-id="${rateId}"]`;
      const paxDetails = document.querySelectorAll(detailsSelector);

      paxDetails.forEach(paxEl => {
        const uid = paxEl.dataset.uid;
        const selections = { uid };
        let hasData = false;

        // Only direct addon-card children under .addon-fields
        const addonNodes = paxEl.querySelectorAll(':scope > .addon-fields > .addon-card');

        addonNodes.forEach(nodeEl => {
          const nodeData = this.collectNodeData($(nodeEl)); // collectNodeData still expects jQuery
          if (nodeData) {
            Object.assign(selections, nodeData);
            hasData = true;
          }
        });

        if (hasData) addonsArr.push(selections);
      });

      // Update state for this rateId
      if (addonsArr.length > 0) {
        state.fields[rateId] = { addons: addonsArr };
      } else {
        delete state.fields[rateId];
      }
    });

    return state;
  }

  collectAddonSelections() {
    this.addonState.fields = this.addonState.fields || {};

    const $section = $('.addons-content');
    let hasData = false;
    const selections = {};
    const addonsArr = [];

    // FIX: Do NOT use > selector, it breaks your structure
    $section.find('.addon-fields > .addon-card').each((_, nodeEl) => {
      const nodeData = this.collectNodeData($(nodeEl));
      if (nodeData && Object.keys(nodeData).length) {
        Object.assign(selections, nodeData);
        hasData = true;
      }
    });

    if (hasData) addonsArr.push(selections);

    // BUG FIX: this used to only reassign `fields` when there was something to save
    // (`addonsArr.length > 0`), so clearing the LAST/only booking-level addon field back
    // to empty/0 left `hasData` false and `addonsArr` empty — the guard then skipped the
    // reassignment entirely, leaving the PREVIOUS state (still holding the old value)
    // untouched. That stale state got written straight back into #addons_data by
    // saveAllToTextarea() and re-applied by preFillUserSelections() on the next open, so
    // a removed price_per_unit quantity (or any other single addon field) reappeared
    // after being cleared. collectPaxAddonSelections() above already gets this right
    // (explicit `else { delete state.fields[rateId] }`) — this mirrors that: always
    // reflect the CURRENT DOM state, including "nothing selected any more".
    this.addonState.fields = addonsArr.length > 0 ? { addons: addonsArr } : {};

    // collectPaxAddonSelections() always runs immediately before this at every call site,
    // so this.paxAddonsState is freshly rebuilt from the DOM by the time this runs.
    this.expandDisplayOnceAddons();

    return this.addonState;
  }

  // -------------------------
  // Prefill previously saved selections
  // -------------------------
  preFillUserSelections() {
    const state = this.paxAddonsState?.fields || {};
    const addonState = this.addonState?.fields || {};

    //--------------------------------------------
    // APPLY SELECTION UTILITY
    //--------------------------------------------
    const applySelections = (selections, container) => {
      if (!selections || !container) return;

      for (const nodeId in selections) {
        if (nodeId === "uid") continue;

        const nodeData = selections[nodeId];
        const nodeEl = container.querySelector(`.addon-card[data-node-id="${nodeId}"]`);
        if (!nodeEl) continue;

        switch (nodeData.type) {

          case "radio": {
            let input = null;

            if (nodeData.option_id != null) {
              input = nodeEl.querySelector(`input[type="radio"][data-option-id="${nodeData.option_id}"]`);
            }

            if (!input) {
              input = nodeEl.querySelector(`input[type="radio"][value="${nodeData.value}"]`);
            }

            if (input) {
              input.checked = true;
              // input.dispatchEvent(new Event("change", { bubbles: true }));
              input.dispatchEvent(this._changeEvent);
            }
            break;
          }

          case "checkbox": {
            const chk = nodeEl.querySelector(`.form-check input[type="checkbox"]`);
            if (chk) {
              const isChecked = nodeData.value === "checked";
              chk.checked = isChecked;
              chk.value = isChecked ? "checked" : "";
              // chk.dispatchEvent(new Event("change", { bubbles: true }));
              chk.dispatchEvent(this._changeEvent);
            }
            break;
          }

          case "number": {
            const inp = nodeEl.querySelector(`input[type="number"]`);
            if (inp) {
              inp.value = nodeData.value;
              // inp.dispatchEvent(new Event("input", { bubbles: true }));
              inp.dispatchEvent(this._inputEvent);
            }
            break;
          }

          case "select": {
            const sel = nodeEl.querySelector("select");
            if (sel) {
              let done = false;

              if (nodeData.option_id != null) {
                const opt = sel.querySelector(`option[data-option-id="${nodeData.option_id}"]`);
                if (opt) {
                  opt.selected = true;
                  done = true;
                }
              }

              if (!done) sel.value = nodeData.value;

              // sel.dispatchEvent(new Event("change", { bubbles: true }));
              sel.dispatchEvent(this._changeEvent);
            }
            break;
          }

          case "text": {
            const inp = nodeEl.querySelector(`input[type="text"]`);
            if (inp) {
              inp.value = nodeData.value;
              // inp.dispatchEvent(new Event("input", { bubbles: true }));
              inp.dispatchEvent(this._inputEvent);
            }
            break;
          }

          case "textarea":
          case "textbox": {
            const ta = nodeEl.querySelector("textarea");
            if (ta) {
              ta.value = nodeData.value;
              // ta.dispatchEvent(new Event("input", { bubbles: true }));
              ta.dispatchEvent(this._inputEvent);
            }
            break;
          }
        }

        //--------------------------------------------
        // APPLY CHILDREN RECURSIVELY
        //--------------------------------------------
        if (nodeData.children?.length) {
          for (const childObj of nodeData.children) {
            const childId = Object.keys(childObj)[0];
            const childData = childObj[childId];
            const indent = nodeEl.querySelector(`.indent .addon-card[data-node-id="${childId}"]`)?.closest(".indent");
            if (indent) {
              applySelections({ [childId]: childData }, indent);
            }
          }
        }
      }
    };

    //--------------------------------------------
    // APPLY TO EACH PAX
    //--------------------------------------------
    for (const rateId in state) {
      const addonsArr = state[rateId].addons || [];
      if (!addonsArr.length) continue;

      addonsArr.forEach(paxSel => {
        const uid = paxSel.uid;
        const paxEl = document.querySelector(`${this.paxDetailsSelector} .pax-detail[data-uid="${uid}"]`);
        if (paxEl) applySelections(paxSel, paxEl);
      });
    }

    //--------------------------------------------
    // APPLY GLOBAL ADDONS
    //--------------------------------------------
    const globalArr = addonState.addons || [];
    globalArr.forEach(fields => {
      const globalEl = document.querySelector(".addons-content");
      if (globalEl) applySelections(fields, globalEl);
    });

    // A display-once field's own answer lives ONLY in paxAddonsState once
    // expandDisplayOnceAddons() has run (it's deliberately removed from addonState so it's never
    // resubmitted as a stray general-bucket field) — restore it into its general "Add-ons:"
    // control here too, from the snapshot taken at that point, or any later DOM rebuild (e.g. a
    // Group-rate payment-method switch) leaves that control showing its unanswered default even
    // though the real answer is still saved correctly.
    if (this._displayOnceSnapshot) {
      const globalEl = document.querySelector(".addons-content");
      if (globalEl) applySelections(this._displayOnceSnapshot, globalEl);
    }

    //--------------------------------------------
    // DELAYED: RUN RULE LOGIC + RECALC
    //--------------------------------------------
    setTimeout(() => {
      // run all field logic runners
      document.querySelectorAll(`${this.paxDetailsSelector} .addon-fields, .addons-content .addon-fields`)
        .forEach(wrapEl => {
          if (wrapEl._logicRunAll) {
            try { wrapEl._logicRunAll(); } catch { }
          }
        });

      // old global logic backups
      if (this._runLogics?.length) {
        this._runLogics.forEach(fn => {
          try { fn(); } catch { }
        });
      }

      // trigger synthetic change for recalculation
      document.querySelectorAll(`${this.paxDetailsSelector} .addon-fields input, 
          ${this.paxDetailsSelector} .addon-fields select, 
          ${this.paxDetailsSelector} .addon-fields textarea,
          .addons-content .addon-fields input,
          .addons-content .addon-fields select,
          .addons-content .addon-fields textarea`)
        .forEach(el => el.dispatchEvent(this._changeEvent));
      // .forEach(el => el.dispatchEvent(new Event("change", { bubbles: true })));

      // recalc UI prices
      document.querySelectorAll(`${this.paxDetailsSelector} .pax-detail`)
        .forEach(el => this.updatePricePerPaxUI($(el)));

      this.updateAddonsTotalUI();
      this.renderFinalCost();
    });
  }

  // -------------------------
  // Validation
  // -------------------------
  validateAddons() {
    let isValid = true;
    let hasRequired = false;

    const paxFields = this.addonsData?.form?.addons?.pax_fields || [];
    document.querySelectorAll(`${this.paxDetailsSelector} .pax-detail`).forEach(paxEl => {
      paxFields.forEach(f => {
        const result = Components.validateContainer(paxEl, f);
        if (!result.isValid) isValid = false;
        if (result.hasRequired) hasRequired = true;
      });
    });

    const otherFields = this.addonsData?.form?.addons?.fields || [];
    document.querySelectorAll('.addons-content .addon-fields').forEach(otherEl => {
      const nodeId = otherEl.querySelector('.addon-card')?.dataset.nodeId;
      if (!nodeId) return;
      const def = this.getFieldDefById(nodeId, otherFields);
      if (!def) return;
      const result = Components.validateContainer(otherEl, def);
      if (!result.isValid) isValid = false;
      if (result.hasRequired) hasRequired = true;
    });

    return { isValid, hasRequired };
  }

  handleAddonValidationState({ hasRequired, isValid, showShake = false, hideModal = false }) {
    const $section = $(this.addonSectionSelector);
    const $fulfilled = $section.find('.addon-required-fulfilled');
    const $warning = $section.find('.addon-required-warning');
    const $btn = $(this.btnAddAddonsSelector);

    // No required fields: reset UI
    if (!hasRequired) {
      $fulfilled.hide();
      $warning.hide();
      $btn.css('padding', '8px 15px');
      return;
    }

    // Required fields exist → show appropriate message
    if (isValid) {
      // All required fields completed
      $fulfilled.show();
      $warning.hide();
    } else {
      // Some required fields missing
      $fulfilled.hide();
      $warning.show();

      // Shake button (visual feedback)
      if (showShake) {
        $btn.addClass('shake');
        setTimeout(() => $btn.removeClass('shake'), 500);
      }

      // Hide modal if requested
      if (hideModal && this.activeModal) {
        const modalRef = this.activeModal === 'pax'
          ? this.paxAddonsModal
          : this.activeModal === 'addons'
            ? this.addonsModal
            : null;

        if (modalRef && modalRef.length) {
          try { modalRef.modal('hide'); } catch (e) { /* ignore errors */ }
        }
      }
    }

    // Always apply compact button padding
    $btn.css('padding', '8px 12px 8px 26px');
  }

  // -------------------------
  // Save / Cancel handlers
  // -------------------------
  saveAddonForm() {
    this.paxAddonsState = this.paxAddonsState || {};
    this.addonState = this.addonState || {};

    const { isValid, hasRequired } = this.validateAddons();

    // Required but invalid → show warning & abort
    if (hasRequired && !isValid) {
      swal('Add-ons Required', 'Please complete all required add-on fields before saving.', 'warning');
      this.handleAddonValidationState({ hasRequired, isValid });
      return;
    }

    // Update required UI state (valid or no-required)
    this.handleAddonValidationState({ hasRequired, isValid });

    // Group-rate mode: save pax addons (if form has price_per_pax fields) + one_time / price_per_unit.
    // Skip the "sync pax counts back to rate table" step — group size is owned by the rate selector.
    if (typeof isGroupRateEnabled !== 'undefined' && String(isGroupRateEnabled) === '1') {
      const formId  = parseInt(this.addonsData?.form?.id || 0);
      const grAddons = this.addonsData?.form?.addons || {};

      // 1) Pax addons — only when the form contains price_per_pax fields
      if ((grAddons.pax_fields || []).length > 0) {
        this.removeInactiveChildren();
        const savedPax = this.collectPaxAddonSelections();
        const hasPaxAddonData = savedPax?.fields &&
          Object.values(savedPax.fields).some(rg =>
            rg.addons?.length && rg.addons.some(a => Object.keys(a).length > 0)
          );
        if (hasPaxAddonData) {
          this.paxAddonsState = { form_id: formId, fields: savedPax.fields };
          $('#pax_addons_data').val(JSON.stringify(this.paxAddonsState));
        } else {
          this.paxAddonsState = { form_id: formId, fields: {} };
          $('#pax_addons_data').val('');
        }
      }

      // 2) Other addons (one_time / price_per_unit)
      const savedOneTime = this.collectAddonSelections();
      const hasOneTimeData = savedOneTime?.fields && Object.keys(savedOneTime.fields).length > 0;
      if (hasOneTimeData) {
        this.addonState = { form_id: formId, fields: savedOneTime.fields };
        $('#addons_data').val(JSON.stringify(this.addonState));
      } else {
        this.addonState = { form_id: formId, fields: {} };
        $('#addons_data').val('');
      }

      this.updateAddonsTotalUI();

      if (typeof updateRatesTable === 'function') {
        try { updateRatesTable(); } catch (e) { }
      }

      this.breakdownItems = this.collectAllBreakdownItems();
      this.renderRightPanelBreakdown();

      const grAddonsFee = this.addonsFees;
      const $grFeeRow = $('.addons-fee-row');
      if ($grFeeRow.length) {
        if (grAddonsFee > 0) {
          $grFeeRow.find('.addons-fee-display').text(`${Components.currencyFormation.symbol}${grAddonsFee.toFixed(2)}`);
          $grFeeRow.show();
        } else {
          $grFeeRow.hide();
        }
      }

      this.hide();
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass('d-none');
      this.lastSavedPaxAddonsState = structuredClone(this.paxAddonsState);
      this.lastSavedAddonsState    = structuredClone(this.addonState);
      return;
    }

    const formId = parseInt(this.addonsData?.form?.id || 0);

    /* -----------------------------------------------------------------------
      1) Save Price-per-Pax Addons
    ----------------------------------------------------------------------- */
    this.removeInactiveChildren();
    const savedPax = this.collectPaxAddonSelections();
    const hasPaxAddonData =
      savedPax?.fields &&
      Object.values(savedPax.fields).some(rateGroup =>
        rateGroup.addons?.length &&
        rateGroup.addons.some(addon => Object.keys(addon).length > 0)
      );

    if (hasPaxAddonData) {
      this.paxAddonsState = { form_id: formId, fields: savedPax.fields };
      $('#pax_addons_data').val(JSON.stringify(this.paxAddonsState));
    } else {
      this.paxAddonsState = { form_id: formId, fields: {} };
      $('#pax_addons_data').val('');
    }

    /* -----------------------------------------------------------------------
      2) Save One-time / Price-per-unit Addons
    ----------------------------------------------------------------------- */
    const savedOneTime = this.collectAddonSelections();
    const hasOneTimeData = savedOneTime?.fields && Object.keys(savedOneTime.fields).length > 0;

    if (hasOneTimeData) {
      this.addonState = { form_id: formId, fields: savedOneTime.fields };
      $('#addons_data').val(JSON.stringify(this.addonState));
    } else {
      this.addonState = { form_id: formId, fields: {} };
      $('#addons_data').val('');
    }

    /* -----------------------------------------------------------------------
      3) Sync Pax Counts Back to Rate Table
    ----------------------------------------------------------------------- */
    this.paxSection.find('.pax-row').each((_, rowEl) => {
      const $row = $(rowEl);
      const rateId = $row.data('rate-id');
      const count = parseInt($row.find('.pax-count').val() || 0);
      const $pageRow = $(`#table-rate-groups #tourists-${rateId}`);
      if ($pageRow.length) $pageRow.val(count);
    });

    /* -----------------------------------------------------------------------
      4) Recalculate totals and close modal
    ----------------------------------------------------------------------- */
    if (typeof updateRatesTable === 'function') {
      try { updateRatesTable(); } catch (e) { }
    }

    this.hide();

    // Show addon button after saving if we have pax and addons
    const totalPax = this.getTotalPaxCount();
    if (totalPax > 0 && this.hasAddons()) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass("d-none");
    } else if (totalPax === 0) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass("d-none");
    }

    // Deep clone saved state (for cancel function)
    this.lastSavedPaxAddonsState = structuredClone(this.paxAddonsState);
    this.lastSavedAddonsState = structuredClone(this.addonState);

    // Persist breakdown items for right panel
    this.breakdownItems = this.collectAllBreakdownItems();
    this.renderRightPanelBreakdown();

    const addonsFee = this.addonsFees;
    const $feeRow = $('.addons-fee-row');
    if ($feeRow.length) {
      if (addonsFee > 0) {
        $feeRow.find('.addons-fee-display').text(`${Components.currencyFormation.symbol}${addonsFee.toFixed(2)}`);
        $feeRow.show();
      } else {
        $feeRow.hide();
      }
    }
  }

  cancelAddonForm(e) {
    // Determine which modal context we're in
    const $target = $(e.currentTarget);
    const isPax = this.activeModal === 'pax' || $target.closest('#paxAddonsModal').length > 0;
    const modal = isPax ? this.paxAddonsModal : this.addonsModal;

    // Validate
    const { isValid, hasRequired } = this.validateAddons();

    if (hasRequired && !isValid) {
      this.handleAddonValidationState({ hasRequired, isValid, showShake: true, hideModal: true });
      return;
    }

    // Pax-specific restoration logic
    if (isPax) {
      this.paxAddonsState = structuredClone(
        this.lastSavedPaxAddonsState || {
          form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
          fields: {}
        }
      );

      // Rebuild UI from restored state
      this.renderPaxDetails();
      this.updateSeatAvailability();
    }

    this.addonState = structuredClone(
      this.lastSavedAddonsState || {
        form_id: parseInt(this.addonsData?.form?.id || 0) || 0,
        fields: {}
      }
    );
    // this.addonState = structuredClone(this.lastSavedAddonsState);

    // Update validation state UI
    this.handleAddonValidationState({ hasRequired, isValid });

    // Hide correct modal
    modal.modal('hide');

    // Show addon button after closing modal if we have pax and addons
    const totalPax = this.getTotalPaxCount();
    if (totalPax > 0 && this.hasAddons()) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).removeClass("d-none");
    } else if (totalPax === 0) {
      $(`${this.addonSectionSelector}, ${this.btnAddAddonsSelector}`).addClass("d-none");
    }
  }

  // -------------------------
  // Totals & pricing
  // -------------------------
  calculateAddonTotal($card, parentActive = true) {
    if (!parentActive) return 0;
    let totalFee = 0;
    const fieldFee = parseFloat($card.data('fee')) || 0;
    const fieldPriceEnabled = parseInt($card.data('additionalFee') || $card.data('additional-fee') || 0);
    const unitType = ($card.data('unitType') || $card.data('unit-type') || '').toString();
    const displayOnce = parseInt($card.data('displayOnce') || $card.data('display-once') || 0);
    let fieldActive = false;
    let pricePerUnitHandled = false;

    $card.find('> .form-check input, > label.addon-radio-option input[type="radio"], > select, > input, > textarea').each((_, el) => {
      const $el = $(el);
      if ($el.is(':checkbox')) {
        if ($el.is(':checked')) {
          fieldActive = true;
          // Fee lives on the card (fieldFee); the post-loop handles it — don't add optFee here.
        }
      } else if ($el.is(':radio')) {
        if ($el.is(':checked')) {
          fieldActive = true;
          const optFee = parseFloat($el.data('fee')) || 0;
          const optionPriceEnabled = parseInt($el.data('option-additional-fee') || $el.data('optionAdditionalFee') || 0);
          if ((fieldPriceEnabled || optionPriceEnabled) && optFee > 0) {
            // display_once: this option is answered once but billed per traveler — mirror
            // AddonBookingService::fanOutDisplayOnceAddons()'s server-side traveler count.
            const qty = (unitType === 'price_per_pax' && displayOnce) ? this.getTotalPaxCount() : 1;
            totalFee += optFee * qty;
          }
        }
      } else if ($el.is('select')) {
        const opt = $el.find('option:selected');
        if (opt.length && opt.val() !== '') {
          fieldActive = true;
          const optFee = parseFloat(opt.data('fee')) || 0;
          const optionPriceEnabled = parseInt(opt.data('option-additional-fee') || opt.data('optionAdditionalFee') || 0);
          if ((fieldPriceEnabled || optionPriceEnabled) && optFee > 0) {
            const qty = (unitType === 'price_per_pax' && displayOnce) ? this.getTotalPaxCount() : 1;
            totalFee += optFee * qty;
          }
        }
      } else if ($el.is("input[type='number']")) {
        const qty = parseFloat($el.val()) || 0;
        if (qty > 0) {
          fieldActive = true;
          if (unitType === 'price_per_unit' && fieldPriceEnabled && fieldFee > 0) {
            totalFee += fieldFee * qty;
            pricePerUnitHandled = true;
          }
        }
      } else if ($el.is("input[type='text'], textarea")) {
        if ($el.val().trim() !== '') {
          fieldActive = true;
        }
      }
    });

    if (fieldActive && fieldFee > 0 && fieldPriceEnabled && !pricePerUnitHandled) {
      // Covers checkbox-type fields (no options, price lives on the field itself) — a checked
      // display_once checkbox needs the same traveler-count multiplier as the radio/select
      // branches above.
      const qty = (unitType === 'price_per_pax' && displayOnce) ? this.getTotalPaxCount() : 1;
      totalFee += fieldFee * qty;
    }

    // children recursion
    $card.find('> .indent > .addon-card').each((_, childEl) => {
      if (!fieldActive) return;
      totalFee += this.calculateAddonTotal($(childEl), fieldActive);
    });

    return totalFee;
  }

  // Returns flat list of priced active addon items for breakdown display
  collectBreakdownItems($section) {
    const items = [];

    const processCard = ($card) => {
      const fieldFee = parseFloat($card.data('fee')) || 0;
      const fieldPriceEnabled = parseInt($card.data('additionalFee') || $card.data('additional-fee') || 0);
      const unitType = ($card.data('unitType') || $card.data('unit-type') || '').toString();
      const displayOnce = parseInt($card.data('displayOnce') || $card.data('display-once') || 0);
      const label = ($card.data('label') || '').toString();
      let fieldActive = false;
      let itemTotal = 0;
      let itemQty = 1;
      let optionLabel = '';
      let optRawPrice = 0;

      $card.find('> .form-check input, > label.addon-radio-option input[type="radio"], > select, > input, > textarea').each((_, el) => {
        const $el = $(el);
        if ($el.is(':checkbox')) {
          if ($el.is(':checked')) {
            fieldActive = true;
            // Fee lives on the card (fieldFee); the post-loop handles it — don't add optFee here.
          }
        } else if ($el.is(':radio')) {
          if ($el.is(':checked')) {
            fieldActive = true;
            const optFee = parseFloat($el.data('fee')) || 0;
            const optionPriceEnabled = parseInt($el.data('option-additional-fee') || $el.data('optionAdditionalFee') || 0);
            if ((fieldPriceEnabled || optionPriceEnabled) && optFee > 0) {
              optionLabel = ($el.data('label') || '').toString().split('(')[0].trim();
              // display_once: answered once, billed per traveler — mirror
              // AddonBookingService::fanOutDisplayOnceAddons()'s server-side traveler count.
              const qty = (unitType === 'price_per_pax' && displayOnce) ? this.getTotalPaxCount() : 1;
              itemQty = qty;
              itemTotal += optFee * qty;
              optRawPrice = optFee;
            }
          }
        } else if ($el.is('select')) {
          const opt = $el.find('option:selected');
          if (opt.length && opt.val() !== '') {
            fieldActive = true;
            const optFee = parseFloat(opt.data('fee')) || 0;
            const optionPriceEnabled = parseInt(opt.data('option-additional-fee') || opt.data('optionAdditionalFee') || 0);
            if ((fieldPriceEnabled || optionPriceEnabled) && optFee > 0) {
              optionLabel = opt.text().trim().split('(')[0].trim();
              const qty = (unitType === 'price_per_pax' && displayOnce) ? this.getTotalPaxCount() : 1;
              itemQty = qty;
              itemTotal += optFee * qty;
              optRawPrice = optFee;
            }
          }
        } else if ($el.is("input[type='number']")) {
          const qty = parseFloat($el.val()) || 0;
          if (qty > 0) {
            fieldActive = true;
            itemQty = qty;
            if (unitType === 'price_per_unit' && fieldPriceEnabled && fieldFee > 0) {
              itemTotal = fieldFee * qty;
            }
          }
        } else if ($el.is("input[type='text'], textarea")) {
          if ($el.val().trim() !== '') fieldActive = true;
        }
      });

      if (fieldActive && fieldFee > 0 && fieldPriceEnabled && unitType !== 'price_per_unit') {
        // Covers checkbox-type fields (price lives on the field itself, no options) — a checked
        // display_once checkbox needs the same traveler-count multiplier as the radio/select
        // branches above.
        const qty = (unitType === 'price_per_pax' && displayOnce) ? this.getTotalPaxCount() : 1;
        itemQty = qty;
        itemTotal += fieldFee * qty;
      }

      if (fieldActive && itemTotal > 0) {
        // price_per_unit's per-unit price lives on the field itself (fieldFee); a display_once
        // price_per_pax radio/dropdown instead prices its OPTIONS (fieldFee is 0 there) — fall
        // back to optRawPrice so the "$X × qty" hint below shows the real per-traveler price.
        const unitPrice = unitType === 'price_per_unit'
          ? fieldFee
          : ((unitType === 'price_per_pax' && displayOnce) ? (fieldFee > 0 ? fieldFee : optRawPrice) : itemTotal);
        const fieldRawPrice = unitType === 'price_per_unit' ? fieldFee : (fieldPriceEnabled ? fieldFee : 0);
        items.push({ label, optionLabel, qty: itemQty, unitPrice, unitType, total: itemTotal, fieldRawPrice, optRawPrice });
      }

      $card.find('> .indent > .addon-card').each((_, childEl) => {
        if (fieldActive) processCard($(childEl));
      });
    };

    $section.find('> .addon-fields > .addon-card').each((_, cardEl) => processCard($(cardEl)));
    return items;
  }

  collectAllBreakdownItems() {
    const otherItems = this.collectBreakdownItems($('.addons-content'));

    const paxItems = [];
    $('.pax-detail').each((_, paxEl) => {
      const rawText = $(paxEl).find('.pax-header h4').text().trim();
      const paxLabel = rawText.replace(/\s*—.*$/, '').trim();
      this.collectBreakdownItems($(paxEl)).forEach(item => {
        paxItems.push({ ...item, paxContext: paxLabel });
      });
    });

    return [...otherItems, ...paxItems];
  }

  // Fee for one breakdown item — round PER UNIT, then multiply by qty. Deliberate policy
  // (2026-08-10, reversing the prior "round on the combined total" convention) applied
  // uniformly here: a price_per_unit fee and a price_per_pax fee (including a display_once
  // field folded into one preview item, qty = traveler count) now use the same formula.
  // Must stay consistent with AddonBookingService::calculateFieldAmounts() — see that
  // function for the full rationale.
  computeItemFee(item) {
    const fFee = (Math.round(item.fieldRawPrice * this.bookingServiceCommission / 100 * 100) / 100) * item.qty;
    const oFee = (Math.round(item.optRawPrice * this.bookingServiceCommission / 100 * 100) / 100) * item.qty;
    return fFee + oFee;
  }

  renderModalBreakdown() {
    const cf = Components.currencyFormation;
    const items = this.collectAllBreakdownItems();
    const total = this.finalAddonsTotal();
    const fee = items.reduce((sum, item) => sum + this.computeItemFee(item), 0);

    const isPaxModal = this.activeModal === 'pax';

    let $panel;
    if (isPaxModal) {
      // Panel is a sibling of #paxDetails (the scrolling cards area), pinned at the
      // bottom of the right column. It lives OUTSIDE the scroll, so totals/fees/price
      // stay visible on the right while the pax detail cards scroll.
      $panel = $('#paxAddonsModal .addon-breakdown-panel');
    } else {
      $panel = $('#addonsModal .addon-breakdown-panel');
    }

    if (!$panel.length) return;

    if (!items.length) {
      $panel.hide();
      return;
    }

    let rows = '';
    items.forEach(item => {
      const nameHtml = item.optionLabel
        ? `${item.label} <span style="color:#6B7280;font-size:0.78rem">(${item.optionLabel})</span>`
        : item.label;
      const paxHtml = item.paxContext
        ? `<span style="color:#9CA3AF;font-size:0.73rem"> · ${item.paxContext}</span>`
        : '';
      const calcHtml = (item.unitType === 'price_per_unit' || item.unitType === 'price_per_pax') && item.qty > 1
        ? `<span style="color:#9CA3AF;font-size:0.78rem;margin-left:4px">${cf.symbol}${item.unitPrice.toFixed(2)} × ${item.qty}</span>`
        : '';

      rows += `<div style="display:flex;align-items:baseline;justify-content:space-between;padding:3px 0;border-bottom:1px solid #F3F4F6;gap:8px">
        <div style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1">
          <span style="font-size:0.82rem;color:#374151">${nameHtml}${paxHtml}</span>${calcHtml}
        </div>
        <span style="font-weight:600;color:#111827;white-space:nowrap;font-size:0.82rem">${cf.symbol}${item.total.toFixed(2)}</span>
      </div>`;
    });

    const hasFee = fee > 0 && this.bookingServiceCommission > 0;
    const addonsCostHtml = `<div style="display:flex;justify-content:space-between;padding-top:6px;font-size:0.78rem;color:#6B7280;border-top:1px solid #E5E7EB;margin-top:4px">
        <span>Add-ons Cost:</span>
        <span>${cf.symbol}${total.toFixed(2)}</span>
      </div>`;
    const feeHtml = hasFee
      ? `<div style="display:flex;justify-content:space-between;padding-top:3px;font-size:0.78rem;color:#9CA3AF">
          <span>Booking Fee:</span>
          <span>${cf.symbol}${fee.toFixed(2)}</span>
        </div>`
      : '';
    const addonsTotal = total + (hasFee ? fee : 0);
    const addonsTotalHtml = `<div style="display:flex;justify-content:space-between;padding-top:4px;font-size:0.82rem;font-weight:600;color:#111827">
        <span>Add-ons Total:</span>
        <span>${cf.symbol}${addonsTotal.toFixed(2)}</span>
      </div>`;

    const innerPadding = isPaxModal ? '10px 14px' : '10px 12px';
    const innerRadius = '8px';
    const innerBg = '#F9FAFB';
    const innerBorder = '1px solid #E5E7EB';
    // No cap needed any more: the panel lives in its own row below both columns
    // (addons-modal.blade.php), not squeezed inside the pax-cards column, so it's
    // free to hug its own content just like the non-pax modal's panel already does.
    const cardMaxHeight = 'none';

    $panel.html(`
      <div style="background:${innerBg};border:${innerBorder};border-radius:${innerRadius};padding:${innerPadding};display:flex;flex-direction:column;max-height:${cardMaxHeight}">
        <div style="margin-bottom:6px;flex:0 0 auto">
          <span style="font-size:0.7rem;font-weight:600;color:#9CA3AF;text-transform:uppercase;letter-spacing:0.06em">Add-ons Summary</span>
        </div>
        <div style="flex:1 1 auto;min-height:0;overflow-y:auto">${rows}</div>
        <div style="flex:0 0 auto">
          ${addonsCostHtml}
          ${feeHtml}
          ${addonsTotalHtml}
        </div>
      </div>
    `).show();
  }

  renderRightPanelBreakdown() {
    const cf = Components.currencyFormation;
    const items = this.breakdownItems || [];
    const $container = $('.addons-right-breakdown');
    if (!$container.length) return;

    if (!items.length) {
      $container.hide();
      return;
    }

    let rows = '';
    items.forEach(item => {
      const nameHtml = item.optionLabel ? `${item.label} (${item.optionLabel})` : item.label;
      const qtyHtml = (item.unitType === 'price_per_unit' || item.unitType === 'price_per_pax') && item.qty > 1
        ? ` <span style="color:#9CA3AF;font-size:0.75rem">×${item.qty}</span>`
        : '';
      const paxHtml = item.paxContext
        ? ` <span style="color:#9CA3AF;font-size:0.75rem">· ${item.paxContext}</span>`
        : '';

      rows += `<div style="display:flex;justify-content:space-between;font-size:0.78rem;color:#6B7280;padding:1px 0">
        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-right:8px">${nameHtml}${qtyHtml}${paxHtml}</span>
        <span style="white-space:nowrap">${cf.symbol}${item.total.toFixed(2)}</span>
      </div>`;
    });

    $container.html(`<div style="margin-top:4px;padding:6px 8px;background:#F9FAFB;border-radius:6px;border:1px solid #F3F4F6">${rows}</div>`).show();
  }

  addonsTotal($section) {
    let addonsTotal = 0;

    $section.find('> .addon-fields > .addon-card').each((_, cardEl) => {
      addonsTotal += this.calculateAddonTotal($(cardEl), true);
    });

    return addonsTotal;
  }

  calculateAllPaxAddonTotal($section) {
    // For Price Per Pax
    let paxAddonsTotal = $section.toArray().reduce((sum, paxEl) => {
      return sum + this.addonsTotal($(paxEl));
    }, 0);

    return paxAddonsTotal;
  }

  finalAddonsTotal() {
    // Others [One time | Price per unit]
    const others = this.addonsTotal($('.addons-content'));

    // Price per pax
    const pricePerPax = this.calculateAllPaxAddonTotal($('.pax-detail'));

    const addonsPrice = others + pricePerPax;

    return addonsPrice;
  }

  updatePricePerPaxUI($paxDetail) {
    const $priceSpan = $paxDetail.find('.pax-price');
    const basePrice = parseFloat($priceSpan.data('base-price')) || 0;
    var total = basePrice + this.addonsTotal($paxDetail);
    const addonsFees = parseFloat(basePrice * this.bookingServiceCommission / 100);
    var paymentMethod = $('.checkset:checked').val();

    if(paymentMethod === 'Card' || (this.isCashFees == 1 && paymentMethod === 'Cash') || paymentMethod === 'Link') {
      total = total + addonsFees;
    }

    $priceSpan.text(`${Components.currencyFormation.symbol}${total.toFixed(2)}`);
  }

  updateAddonsTotalUI() {
    const addonsPrice = this.finalAddonsTotal();

    $('.pax-addons-total').text(`${Components.currencyFormation.symbol}${addonsPrice.toFixed(2)}`);

    const items = this.collectAllBreakdownItems();
    // See computeItemFee() for why price_per_unit and price_per_pax round differently.
    const addonsFees = items.reduce((sum, item) => sum + this.computeItemFee(item), 0);

    this.addonsPrice = addonsPrice;
    this.addonsFees = addonsFees;

    this.renderModalBreakdown();
  }

  renderFinalCost() {
    let total = 0;
    let cashTotal = 0;
    let cardTotal = 0;
    var paymentMethod = $('.checkset:checked').val();

    // Group-rate packages render one .pax-detail card per headcount slot (see initAddonForm()'s
    // synthetic `rate_for: 'Group'` row), but every card in that row carries the SAME
    // data-base-price — the price for the WHOLE group (paxWiseGroupRates[headcount] is already a
    // total-for-N-people lookup, not a per-person rate). Summing every card's base price here
    // multiplied that group total by the headcount again (e.g. a $200 group price across 6 people
    // became $1200/$1317.60 instead of ~$200/$219.60). Count a "Group" row's base price only once
    // per rate id; genuine per-person rate rows are untouched and still sum every card, since
    // their base price really is per-person and summing N cards correctly reproduces price * count.
    const seenGroupRateIds = new Set();
    document.querySelectorAll('#paxDetails .pax-detail').forEach(detail => {
      const priceEl = detail.querySelector('.pax-price');
      if (!priceEl) return;
      const price = parseFloat(priceEl.dataset.basePrice) || 0;
      const isGroupRateRow = typeof isGroupRateEnabled !== 'undefined'
        && String(isGroupRateEnabled) === '1'
        && detail.dataset.type === 'Group';

      if (isGroupRateRow) {
        const rateId = detail.dataset.rateId;
        if (seenGroupRateIds.has(rateId)) return;
        seenGroupRateIds.add(rateId);
      }

      total += price;
    });

    const addonsFees = parseFloat(total * this.bookingServiceCommission / 100);
    cashTotal = total;

    if(this.isCashFees == 1) {
      cashTotal = total + addonsFees;
    }

    cardTotal = total + addonsFees;

    $(".cash-price").text(`${Components.currencyFormation.symbol}${cashTotal.toFixed(2)}`);
    $(".card-price").text(`${Components.currencyFormation.symbol}${cardTotal.toFixed(2)}`);
  }

  // -------------------------
  // Small helpers
  // -------------------------
  roundout(amount, places = 2) {
    if (places < 0) places = 0;
    const factor = Math.pow(10, places);
    return Math.round(amount * factor) / factor;
  }

  // Updates the live "price per unit" badge inside a number-field addon card when qty changes
  updatePricePerUnitPreview(inputEl) {
    if (!inputEl || inputEl.type !== 'number') return;
    const $card = $(inputEl).closest('.addon-card');
    if (!$card.length) return;
    const unitType = ($card.data('unitType') || $card.data('unit-type') || '').toString();
    if (unitType !== 'price_per_unit') return;

    const unitPrice = parseFloat($card.data('fee')) || 0;
    const qty = parseFloat(inputEl.value) || 0;
    const total = unitPrice * qty;
    const cf = Components.currencyFormation;

    const $preview = $card.find('.price-preview');
    const $totalSpan = $card.find('.price-per-unit-total');

    if (qty > 0 && unitPrice > 0) {
      $totalSpan.text(`= ${cf.symbol}${total.toFixed(2)}`);
      $preview.show();
    } else {
      $preview.hide();
    }
  }

  setServiceCommissionPercentage(val) {
    this.bookingServiceCommission = val;
  }

  getPrice() { return this.addonsPrice; }
  getFees() { return this.addonsFees; }

  // called by "Make payment" flow in original code
  checkRequiredAddonsSummary() {
    const { isValid, hasRequired } = this.validateAddons();

    if (hasRequired && !isValid) {
      swal('Add-ons Required', 'Please complete all required add-on fields before proceeding.', 'warning');
    }

    this.handleAddonValidationState({ hasRequired, isValid });
    return !hasRequired || isValid;
  }

  removeInactiveChildren() {
    $(".addon-card").each(function () {
      const $card = $(this);
      const $indent = $card.closest(".indent");

      if (!$indent.length) return;

      if ($indent[0].offsetParent === null) {
        $card.find("input, select, textarea").each(function () {
          const $input = $(this);

          if ($input.is(":checkbox,:radio")) {
            $input.prop("checked", false);
          } else {
            $input.val("");
          }
        });
      }
    });
  }
}

// -------------------------
// Create instance (keeps original global variable name)
// -------------------------
const addonFormReader = new AddonFormReader({
  admin: typeof userType !== 'undefined' ? userType : false,
  paxSection: '#paxSection',
  addonSection: '.addons-content',
  tblRateGrps: $('#table-rate-groups'),
  getAddonFormUrl: typeof getAddonFormUrl !== 'undefined' ? getAddonFormUrl : null,
  isCashFees: isCashFees
});

// -------------------------
// UI helpers (kept as in original file)
// -------------------------
function adjustDividerHeight() {
  const body = document.querySelector('#paxAddonsModal .modal-body');
  if (!body) return;
  const divider = body.querySelector('.divider');
  const left = body.querySelector('.left');
  const right = body.querySelector('.right');
  if (divider && left && right) {
    const maxHeight = Math.max(left.scrollHeight, right.scrollHeight);
    divider.style.height = maxHeight + 'px';
  }
}

$('#paxAddonsModal').on('shown.bs.modal', adjustDividerHeight);
window.addEventListener('resize', adjustDividerHeight);
const paxDetailsEl = document.getElementById('paxDetails');
if (paxDetailsEl) {
  const observer = new MutationObserver(adjustDividerHeight);
  observer.observe(paxDetailsEl, { childList: true, subtree: true });
}