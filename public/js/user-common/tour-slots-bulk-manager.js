/*
 * Tour Slots Bulk Manager
 * Extracted from resources/views/users/common/tour-slots-bulk-manager.blade.php
 *
 * NOTE: Tailwind JIT is configured to scan this file (see the content globs in
 * tailwind.config.js). Many utility classes on this page exist ONLY inside the
 * template strings below, so this path must stay in Tailwind's content list or
 * the dynamically-rendered UI will lose its styling after a build.
 */
/* ===== CHUNK 1 ===== */
  const ALL_TIME_SLOTS = [
    '6:45 AM','7:15 AM','7:45 AM','8:15 AM','8:45 AM',
    '9:15 AM','9:45 AM','10:15 AM','10:45 AM','11:15 AM',
    '11:45 AM','12:15 PM','1:00 PM','1:30 PM','2:00 PM','2:30 PM','3:00 PM'
  ];
  const DAYS   = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
  const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const SLOT_DATA = {};

  let queue = [];
  let queueCounter = 0;
  let editingQueueId = null; // local id of the queued op currently being edited (kept & highlighted)
  let selectedAction = null;
  let selectedSlots = new Set();       // base slots
  let changePoints = [];               // [{ id, date, slots: Set }]
  let cpCounter = 0;
  let perSlotSeats = {};               // { slotName: { newCount, currentCount, isNew, fromDate } }
  let selectedOnline = null;           // null | true | false — for open/close actions
  let convertTimes = {};               // { oldSlot: newTimeString }
  let convertTimeParts = {};           // { oldSlot: { h, m, ap } }
  let convertSeats  = {};              // { oldSlot: seatCount }
  let convertStatus = {};              // { oldSlot: 'Open'|'Closed'|null }
  let convertOnline = {};              // { oldSlot: true|false|null }
  let historyLog = [];
  let historyCounter = 0;
  let currentUser = 'Lane Gilmore';

  // ── Booking simulation ──
  const BOOKING_DATA = {};
  const GUEST_NAMES  = ['Smith, J.','Garcia, M.','Johnson, A.','Williams, T.','Brown, K.',
    'Davis, R.','Miller, E.','Wilson, C.','Moore, D.','Taylor, P.'];

  function getBookings(date, time) {
    const key = `${date}|${time}`;
    if (!BOOKING_DATA[key]) {
      const h = (date+time).split('').reduce((a,c)=>a+c.charCodeAt(0),0);
      const count = h % 5;
      const arr = [];
      for (let i = 0; i < count; i++) {
        arr.push({
          name:  GUEST_NAMES[(h + i * 7) % GUEST_NAMES.length],
          count: 1 + (h + i * 3) % 4,
          id:    `B${h % 1000}_${i}`
        });
      }
      BOOKING_DATA[key] = arr;
    }
    return BOOKING_DATA[key];
  }

  function totalGuests(date, time) {
    return getBookings(date, time).reduce((s, b) => s + b.count, 0);
  }

  // ── Init ──
  function init() {
    buildSlotsGrid();
    syncPkgTriggerLabel();
    initDateRangePicker();
    initRepeatDatePickers();
    updateBaseDateRangeBadge();
    updatePreview();
    renderQueue();
    hydrateQueueFromServer();   // repopulate the queue (status: queued) on page load
    hydrateHistoryFromServer(); // populate Change History with applied operations
  }

  // Load previously-queued operations from the DB and render them in the queue.
  function hydrateQueueFromServer() {
    const url = window.BULK_MGR_CONFIG && window.BULK_MGR_CONFIG.loadOperationsUrl;
    if (!url) return;
    $.getJSON(url).done(rows => {
      queue = [];          // reset so this also works as a refresh (e.g. after Apply All)
      editingQueueId = null;
      (Array.isArray(rows) ? rows : []).forEach(r => {
        const item = mapServerOperation(r);
        queue.push(item);
        if (item.id > queueCounter) queueCounter = item.id; // keep new local ids above loaded DB ids
      });
      renderQueue();
    });
  }

  // Map a tour_slot_bulk_operations row (server JSON) into the in-page operation shape used by both
  // the queue and the Change History list.
  function mapServerOperation(r) {
    const payload = r.payload || {};
    return {
      id: r.id,
      dbId: r.id,
      pkg: r.tour_package_id != null ? String(r.tour_package_id) : '',
      pkgName: r.package_name || '',
      from: r.from_date,
      to: r.to_date,
      baseSlots: Array.isArray(r.slots) ? r.slots : [],
      changePoints: [],
      action: r.action,
      status: r.status || 'applied',
      seatSnapshot: r.action === 'seats' ? (payload.perSlotSeats || {}) : null,
      affectedSlots: payload.affectedSlots || [],
      online: null,
      visible: r.action === 'visibility' ? (payload.visible !== false) : null,
      statusValue: payload.statusValue || null,
      statusSnapshot: payload.perSlotStatus || {},
      visibleSnapshot: payload.perSlotVisible || {}, // combined Status & Visibility: per-slot show/hide
      convertTimes:  r.action === 'convert' ? (payload.conversions   || {}) : {},
      convertSeats:  r.action === 'convert' ? (payload.convertSeats  || {}) : {},
      convertStatus: r.action === 'convert' ? (payload.convertStatus || {}) : {},
      convertOnline: r.action === 'convert' ? (payload.convertOnline || {}) : {},
      convertSlotTimes: r.action === 'convert' ? (payload.slotTimes || []) : [],
      createRows:  r.action === 'create' ? (payload.createRows || []) : undefined,
      days:        r.action === 'create' ? (payload.days || []) : undefined,
      slotsPerDay: r.action === 'create' ? (payload.slotsPerDay || 0) : undefined,
      numDays:     r.action === 'create' ? (payload.numDays || 0) : undefined,
      total: r.total_slots || 0
    };
  }

  // Load applied operations from the DB and show them in the Change History section.
  function hydrateHistoryFromServer() {
    const url = window.BULK_MGR_CONFIG && window.BULK_MGR_CONFIG.loadAppliedOperationsUrl;
    if (!url) return;
    $.getJSON(url).done(rows => {
      historyLog = (Array.isArray(rows) ? rows : []).map(r => {
        const op = mapServerOperation(r);
        return {
          id: op.dbId,
          appliedAt: r.applied_at ? new Date(String(r.applied_at).replace(' ', 'T')) : new Date(),
          appliedBy: r.applied_by || '',
          status: r.status || 'applied',
          ops: [op],
          totalApplied: op.total
        };
      });
      renderHistory();
    });
  }

  // ── Repeat date pickers (jQuery UI datepicker, same as the platform Repeat Tour Slots page) ──
  // Visible #repeatFromDateTxt/#repeatToDateTxt show mm/dd/yy; hidden #repeatFromDate/#repeatToDate
  // mirror the value as yy-mm-dd (altField) — that's what the rest of the repeat logic reads.
  let $repeatFromDP = null, $repeatToDP = null;
  function initRepeatDatePickers() {
    if (!window.jQuery || !$.fn.datepicker) return;
    $repeatFromDP = $('#repeatFromDateTxt').datepicker({
      altField: '#repeatFromDate', altFormat: 'yy-mm-dd', dateFormat: 'mm/dd/yy', minDate: 0,
      onSelect: function () {
        $repeatToDP && $repeatToDP.datepicker('option', 'minDate', $repeatFromDP.datepicker('getDate'));
        repeatUpdatePreview();
      }
    });
    $repeatToDP = $('#repeatToDateTxt').datepicker({
      altField: '#repeatToDate', altFormat: 'yy-mm-dd', dateFormat: 'mm/dd/yy', minDate: 0,
      onSelect: repeatUpdatePreview
    });
  }

  // Reflect programmatic changes of the hidden #repeatFromDate/#repeatToDate (yy-mm-dd) back into
  // the visible text inputs + datepicker widgets.
  function syncRepeatDatePickers() {
    if (!$repeatFromDP) return;
    const f = document.getElementById('repeatFromDate').value;
    const t = document.getElementById('repeatToDate').value;
    const toMDY = ymd => { const [y, m, d] = ymd.split('-'); return `${m}/${d}/${y}`; };
    if (f) { $repeatFromDP.datepicker('setDate', toMDY(f)); } else { $repeatFromDP.datepicker('setDate', null); document.getElementById('repeatFromDate').value = ''; }
    if (t) { $repeatToDP.datepicker('setDate', toMDY(t)); } else { $repeatToDP.datepicker('setDate', null); document.getElementById('repeatToDate').value = ''; }
    if (f) $repeatToDP.datepicker('option', 'minDate', $repeatFromDP.datepicker('getDate'));
  }

  // ── Date range picker (bootstrap daterangepicker, same as Add Tour Slot page) ──
  // Writes selected range into the hidden #fromDate / #toDate (YYYY-MM-DD) that all the logic reads.
  function applyDateRange(start, end) {
    document.getElementById('fromDate').value = start.format('YYYY-MM-DD');
    document.getElementById('toDate').value   = end.format('YYYY-MM-DD');
    $('#dateRange').val(start.format('MM/DD/YYYY') + ' - ' + end.format('MM/DD/YYYY'));
  }

  function initDateRangePicker() {
    // Default to a custom range of one month from today (same as the Add Tour Slot page).
    const start = moment();
    const end   = moment().add(1, 'month');
    // No preset ranges — show the calendar directly for picking a custom range.
    $('#dateRange').daterangepicker({
      startDate: start,
      endDate: end,
      linkedCalendars: false,
      autoUpdateInput: true,
      showDropdowns: true,
      alwaysShowCalendars: true
    }, function (s, e) {
      applyDateRange(s, e);
      onDateChange();
    });
    applyDateRange(start, end);
  }

  // Reflect programmatic changes to the hidden inputs back into the visible picker.
  function syncDateRangeFromHidden() {
    const f = document.getElementById('fromDate').value;
    const t = document.getElementById('toDate').value;
    const dp = $('#dateRange').data('daterangepicker');
    if (f && t && dp) {
      const s = moment(f, 'YYYY-MM-DD'), e = moment(t, 'YYYY-MM-DD');
      dp.setStartDate(s);
      dp.setEndDate(e);
      $('#dateRange').val(s.format('MM/DD/YYYY') + ' - ' + e.format('MM/DD/YYYY'));
    }
  }

  // Local Y-m-d (NOT toISOString, which shifts the day in timezones east of UTC and would
  // make preview dates miss the real slot rows keyed by the server's dates).
  function fmtDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  }
  function parseLocal(s) { const [y,m,d]=s.split('-').map(Number); return new Date(y,m-1,d); }
  function fmtShort(s) { const d=parseLocal(s); return `${MONTHS[d.getMonth()]} ${d.getDate()}`; }

  function getDates(from, to) {
    const arr=[], cur=new Date(parseLocal(from)), end=parseLocal(to);
    while(cur<=end){ arr.push(fmtDate(cur)); cur.setDate(cur.getDate()+1); }
    return arr;
  }

  function getSlotData(date, slot) {
    const key=`${date}|${slot}`;
    // Real data once slots are loaded: returns the matching slot for this date (or exists:false).
    if (slotsLoaded) {
      const r = slotIndex[key];
      if (r) return { status: r.status || 'Open', seats: r.seats, booked: r.booked || 0, available: (r.available != null ? r.available : Math.max(0, r.seats - (r.booked || 0))), id: r.id, tourSlotId: r.tourSlotId, timeTo: r.to, hide: r.hide, exists: true };
      return { status: '—', seats: 0, booked: 0, available: 0, id: null, tourSlotId: null, exists: false };
    }
    // Demo fallback (before any real fetch)
    if(!SLOT_DATA[key]){
      const h=(date+slot).split('').reduce((a,c)=>a+c.charCodeAt(0),0);
      SLOT_DATA[key]={ status:h%7===0?'Closed':'Open', seats:[8,10,12,15,18,20][h%6] };
    }
    return { ...SLOT_DATA[key], booked: 0, available: SLOT_DATA[key].seats, id: null, exists: true };
  }

  // Returns the set of slots active on a given date (base + any change points whose range covers that date)
  function getSlotsForDate(date) {
    const active = new Set(selectedSlots);
    changePoints.forEach(cp => {
      const from = cp.date, to = cp.dateTo || cp.date;
      if (from <= date && date <= to) cp.slots.forEach(s => active.add(s));
    });
    return active;
  }

  // Returns which change point (if any) starts on this exact date
  function getChangePointForDate(date) {
    return changePoints.find(cp => cp.date === date) || null;
  }

  // Tailwind class string for the base slot chip (replaces .slot-chip + group-data state)
  const SLOT_CHIP_CLS = "group flex items-center gap-[7px] px-2.5 py-2 border-[1.5px] border-[#e2e8f0] rounded-[7px] cursor-pointer transition-all duration-150 bg-[#f8fafc] select-none hover:border-[#1e3a5c] hover:bg-[#eef2ff] data-[selected=true]:border-[#1e3a5c] data-[selected=true]:bg-[#1e3a5c]";

  // ── Base Slots Grid ──
  // Existing slots are loaded from the DB (distinct "from times") once a package + date
  // range are chosen — see loadExistingSlots(). Until then the grid shows a hint.
  let existingSlots = [];
  let slotsGridMsg = 'Select a tour package and date range above to load existing slots.';
  // Real slots fetched for the chosen package + range, keyed `${date}|${from}` →
  // { id (slot_id), tourSlotId, date, from, to, seats, status, hide }. slotsLoaded flips
  // true once a fetch succeeds so the preview can switch from demo data to real data.
  let slotIndex = {};
  let slotsLoaded = false;
  let slotsLoading = false; // true while fetching slots → the grid shows a spinner

  // "6:45 AM" → minutes since midnight (for chronological sorting of the slot list)
  function slotTimeToMinutes(t) {
    const m = /^(\d+):(\d+)\s*(AM|PM)$/i.exec((t || '').trim());
    if (!m) return 0;
    let h = parseInt(m[1], 10) % 12;
    if (/pm/i.test(m[3])) h += 12;
    return h * 60 + parseInt(m[2], 10);
  }

  // Chronologically order an iterable of slot "from times". Works with real DB times
  // (which may not be in the demo ALL_TIME_SLOTS list), unlike filtering that array.
  function sortSlots(iterable) {
    return Array.from(iterable).sort((a, b) => slotTimeToMinutes(a) - slotTimeToMinutes(b));
  }

  function buildSlotsGrid() {
    const grid = document.getElementById('slotsGrid');
    if (!grid) return;
    grid.innerHTML = '';
    if (slotsLoading) {
      grid.innerHTML = `<div class="col-span-full flex items-center justify-center gap-2.5 py-7 px-4 text-[13px] font-medium text-[#64748b] bg-[#f8fafc] border-[1.5px] border-dashed border-[#e2e8f0] rounded-[7px]">
        <span class="inline-block w-4 h-4 border-2 border-[#e2e8f0] border-t-[#1e3a5c] rounded-full animate-spin"></span>
        Loading slots…
      </div>`;
      return;
    }
    if (!existingSlots.length) {
      grid.innerHTML = `<div class="col-span-full text-center py-6 px-4 text-[13px] font-medium text-[#94a3b8] bg-[#f8fafc] border-[1.5px] border-dashed border-[#e2e8f0] rounded-[7px]">${slotsGridMsg}</div>`;
      return;
    }
    existingSlots.forEach(slot => {
      const isSel = selectedSlots.has(slot);
      const chip = document.createElement('label');
      chip.className = SLOT_CHIP_CLS;
      chip.dataset.slot = slot;
      chip.dataset.selected = isSel ? 'true' : 'false';
      chip.innerHTML = `
        <input type="checkbox" class="hidden" ${isSel ? 'checked' : ''} onchange="toggleSlot('${slot}',this)">
        <div class="w-[15px] h-[15px] rounded-[4px] border-[1.5px] border-[#cbd5e1] flex items-center justify-center flex-shrink-0 bg-white group-data-[selected=true]:border-white/40 group-data-[selected=true]:bg-white/20"><svg class="hidden group-data-[selected=true]:block" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg></div>
        <span class="text-xs font-semibold text-[#334155] group-data-[selected=true]:text-white">${slot}</span>`;
      grid.appendChild(chip);
    });
  }

  // Drop any selected slots that are no longer in the loaded list.
  function pruneSelectionToExisting() {
    Array.from(selectedSlots).forEach(s => {
      if (!existingSlots.includes(s)) { selectedSlots.delete(s); delete perSlotSeats[s]; }
    });
  }

  // Fetch the real slots for the chosen package + date range. Builds slotIndex (per date+time,
  // with slot_id/seats/status) for the preview, and derives the distinct "from times" for the grid.
  function loadExistingSlots() {
    const pkg  = document.getElementById('tourPackage').value;
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;

    if (!pkg || !from || !to || from > to) {
      slotsLoading = false;
      existingSlots = [];
      slotIndex = {};
      slotsLoaded = false;
      slotsGridMsg = 'Select a tour package and date range above to load existing slots.';
      pruneSelectionToExisting();
      buildSlotsGrid(); updateSlotCount(); renderSeatEditor(); updatePreview();
      return $.Deferred().resolve().promise();
    }

    slotsLoading = true;
    buildSlotsGrid();

    const url = (window.BULK_MGR_CONFIG && window.BULK_MGR_CONFIG.slotsInRangeUrl) || '';
    return $.getJSON(url, { tourPackageId: pkg, fromDate: from, toDate: to })
      .done(rows => {
        slotsLoading = false;
        rows = Array.isArray(rows) ? rows : [];
        slotIndex = {};
        const froms = new Set();
        rows.forEach(r => {
          slotIndex[`${r.date}|${r.from}`] = r;
          froms.add(r.from);
        });
        existingSlots = Array.from(froms).sort((a, b) => slotTimeToMinutes(a) - slotTimeToMinutes(b));
        slotsLoaded = true;
        slotsGridMsg = 'No time slots exist for this package in the selected date range.';
        pruneSelectionToExisting();
        buildSlotsGrid();
        updateSlotCount(); renderSeatEditor();
        if (selectedAction === 'convert') renderConvertRules();
        updatePreview();
      })
      .fail(() => {
        slotsLoading = false;
        existingSlots = [];
        slotIndex = {};
        slotsLoaded = false;
        slotsGridMsg = 'Could not load slots. Please try again.';
        buildSlotsGrid(); updatePreview();
      });
  }

  function toggleSlot(slot, cb) {
    const chip = cb.closest('label[data-slot]');
    if (cb.checked) {
      selectedSlots.add(slot);
      chip.dataset.selected = 'true';
      const from = document.getElementById('fromDate').value;
      const cur = from ? getSlotData(from, slot) : { seats: 70 };
      perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: false, fromDate: null };
    } else {
      selectedSlots.delete(slot);
      chip.dataset.selected = 'false';
      delete perSlotSeats[slot];
    }
    // Sync base slot selection to all CP cards in real time
    changePoints.forEach(c => {
      const card = document.getElementById(`cp-${c.id}`);
      if (!card) return;
      const input = card.querySelector(`label[data-cpchip] input[data-slot="${slot}"]`);
      if (!input) return;
      const label = input.closest('label[data-cpchip]');
      input.checked = cb.checked;
      input.disabled = cb.checked;
      if (cb.checked) { label.dataset.selected = 'true'; label.dataset.inbase = 'true'; }
      else { label.dataset.selected = 'false'; label.dataset.inbase = 'false'; }
    });
    updateSlotCount(); renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    if (selectedAction === 'repeat') repeatOnStep2Toggle(slot, cb.checked);
    if (selectedAction === 'delete') delRefresh(true);
    updatePreview();
  }

  function selectAllSlots() {
    const from = document.getElementById('fromDate').value;
    document.querySelectorAll('#slotsGrid label[data-slot]').forEach(c => {
      c.dataset.selected = 'true'; c.querySelector('input').checked = true;
      const slot = c.dataset.slot; selectedSlots.add(slot);
      if (!perSlotSeats[slot]) {
        const cur = from ? getSlotData(from, slot) : { seats: 70 };
        perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: false, fromDate: null };
      }
    });
    syncBaseSlotsToAllCps();
    updateSlotCount(); renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    if (selectedAction === 'repeat') repeatSyncFromSelectionBulk();
    if (selectedAction === 'delete') delRefresh(true);
    updatePreview();
  }

  function clearAllSlots() {
    document.querySelectorAll('#slotsGrid label[data-slot]').forEach(c => {
      c.dataset.selected = 'false'; c.querySelector('input').checked = false;
    });
    selectedSlots.clear();
    Object.keys(perSlotSeats).forEach(k => { if (!perSlotSeats[k].isNew) delete perSlotSeats[k]; });
    syncBaseSlotsToAllCps();
    updateSlotCount(); renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    if (selectedAction === 'repeat') repeatSyncFromSelectionBulk();
    if (selectedAction === 'delete') delRefresh(true);
    updatePreview();
  }

  function syncBaseSlotsToAllCps() {
    changePoints.forEach(c => {
      const card = document.getElementById(`cp-${c.id}`);
      if (!card) return;
      card.querySelectorAll('label[data-cpchip] input[data-slot]').forEach(input => {
        const slot = input.getAttribute('data-slot');
        const label = input.closest('label[data-cpchip]');
        const inBase = selectedSlots.has(slot);
        input.checked = inBase || c.slots.has(slot);
        input.disabled = inBase;
        if (inBase) { label.dataset.selected = 'true'; label.dataset.inbase = 'true'; }
        else if (!c.slots.has(slot)) { label.dataset.selected = 'false'; label.dataset.inbase = 'false'; }
        else { label.dataset.inbase = 'false'; }
      });
    });
  }

  function updateSlotCount() {
    const cpCount = changePoints.reduce((s,cp)=>s+cp.slots.size, 0);
    let text = `${selectedSlots.size} base slot${selectedSlots.size!==1?'s':''} selected`;
    if (cpCount > 0) text += ` · ${cpCount} new slot${cpCount!==1?'s':''} via schedule changes`;
    document.getElementById('selectedSlotCount').textContent = text;
    updateBaseDateRangeBadge();
  }

  function updateBaseDateRangeBadge() {
    const from  = document.getElementById('fromDate').value;
    const to    = document.getElementById('toDate').value;
    const badge = document.getElementById('baseDateRange');
    const label = document.getElementById('baseDateRangeText');
    if (!from || !to) { badge.style.display = 'none'; return; }

    let baseEnd = to;
    if (changePoints.length > 0) {
      const earliest = changePoints.reduce((min, cp) => cp.date < min ? cp.date : min, changePoints[0].date);
      const d = new Date(parseLocal(earliest));
      d.setDate(d.getDate() - 1);
      const dayBefore = fmtDate(d);
      if (dayBefore >= from) baseEnd = dayBefore;
    }

    label.textContent = `${fmtShort(from)} → ${fmtShort(baseEnd)}`;
    badge.style.display = 'inline-flex';
  }

  function calcCpTo(index) {
    const to = document.getElementById('toDate').value;
    const sorted = [...changePoints].sort((a,b) => a.date.localeCompare(b.date));
    const next = sorted[index + 1];
    if (next) {
      const d = new Date(parseLocal(next.date));
      d.setDate(d.getDate() - 1);
      return fmtDate(d);
    }
    return to || '';
  }

  function onDateChange() {
    const from = document.getElementById('fromDate').value;
    updateBaseDateRangeBadge();
    if (from) {
      selectedSlots.forEach(slot => {
        const cur = getSlotData(from, slot);
        if (perSlotSeats[slot]) perSlotSeats[slot].currentCount = cur.seats;
      });
      changePoints.forEach(cp => {
        cp.slots.forEach(slot => {
          const cur = getSlotData(cp.date || from, slot);
          if (perSlotSeats[slot]) perSlotSeats[slot].currentCount = cur.seats;
        });
      });
      renderSeatEditor();
    }
    // Date range changed → reload the existing slots for the new range (also calls updatePreview).
    loadExistingSlots();
  }

  // ── Schedule Change Points ──
  function addChangePoint() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    if (!from || !to) { alert('Please set a date range first.'); return; }

    cpCounter++;
    const defaultFrom = new Date(parseLocal(from));
    defaultFrom.setDate(defaultFrom.getDate() + 1);
    const cpFrom = defaultFrom <= parseLocal(to) ? fmtDate(defaultFrom) : from;
    changePoints.push({ id: cpCounter, date: cpFrom, dateTo: to, slots: new Set() });
    renderChangePoints();
    updateBaseDateRangeBadge();
    updatePreview();
  }

  function removeChangePoint(id) {
    const cp = changePoints.find(c => c.id === id);
    if (cp) {
      cp.slots.forEach(slot => delete perSlotSeats[slot]);
    }
    changePoints = changePoints.filter(c => c.id !== id);
    renderChangePoints();
    renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    updateBaseDateRangeBadge();
    updatePreview();
  }

  function onCpDateChange(id, field, input) {
    const cp = changePoints.find(c => c.id === id);
    if (!cp) return;
    cp.date = input.value;
    cp.slots.forEach(slot => {
      if (perSlotSeats[slot]) {
        const cur = getSlotData(cp.date, slot);
        perSlotSeats[slot].currentCount = cur.seats;
        perSlotSeats[slot].fromDate = cp.date;
      }
    });
    renderChangePoints();
    renderSeatEditor();
    updateBaseDateRangeBadge();
    updatePreview();
  }

  function toggleCpSlot(cpId, slot, cb) {
    const cp = changePoints.find(c => c.id === cpId);
    if (!cp) return;
    changePoints.forEach(c => {
      if (cb.checked) {
        c.slots.add(slot);
        const cur = getSlotData(c.date, slot);
        perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: true, fromDate: c.date };
      } else {
        c.slots.delete(slot);
        delete perSlotSeats[slot];
      }
      const card = document.getElementById(`cp-${c.id}`);
      if (!card) return;
      const inputs = card.querySelectorAll('label[data-cpchip] input[type="checkbox"]');
      inputs.forEach(input => {
        const label = input.closest('label[data-cpchip]');
        if (label && label.textContent.trim() === slot) {
          input.checked = cb.checked;
          if (cb.checked) label.dataset.selected = 'true';
          else label.dataset.selected = 'false';
        }
      });
      const badge = document.getElementById(`cp-count-${c.id}`);
      if (badge) badge.textContent = `${c.slots.size} new slot${c.slots.size!==1?'s':''}`;
    });
    updateSlotCount(); renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    updatePreview();
  }

  function cpSelectAll(cpId) {
    ALL_TIME_SLOTS.forEach(slot => {
      if (selectedSlots.has(slot)) return;
      changePoints.forEach(c => {
        c.slots.add(slot);
        const cur = getSlotData(c.date, slot);
        perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: true, fromDate: c.date };
      });
    });
    renderChangePoints();
    updateSlotCount(); renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    updatePreview();
  }

  function cpClearAll(cpId) {
    changePoints.forEach(c => {
      c.slots.forEach(slot => { delete perSlotSeats[slot]; });
      c.slots.clear();
    });
    renderChangePoints();
    updateSlotCount(); renderSeatEditor();
    if (selectedAction === 'convert') renderConvertRules();
    updatePreview();
  }

  // Tailwind class string for cp-chip (data-selected / data-inbase drive state)
  const CP_CHIP_CLS = "flex items-center gap-[5px] px-2.5 py-[5px] border-[1.5px] border-[#e2e8f0] rounded-md cursor-pointer transition-all duration-150 bg-[#f8fafc] select-none text-xs font-semibold text-[#334155] hover:border-[#7c3aed] hover:bg-[#f5f3ff] data-[selected=true]:border-[#7c3aed] data-[selected=true]:bg-[#7c3aed] data-[selected=true]:text-white data-[inbase=true]:border-dashed data-[inbase=true]:opacity-45 data-[inbase=true]:cursor-not-allowed data-[inbase=true]:pointer-events-none";

  function renderChangePoints() {
    const list = document.getElementById('cpList');
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;

    if (changePoints.length === 0) { list.innerHTML = ''; return; }

    list.innerHTML = changePoints.map(cp => {
      const chips = ALL_TIME_SLOTS.map(slot => {
        const inBase    = selectedSlots.has(slot);
        const isSelected = cp.slots.has(slot) || inBase;
        const disabled = inBase;
        const title = inBase ? 'Selected in base schedule' : '';
        return `<label data-cpchip class="${CP_CHIP_CLS}" data-selected="${isSelected?'true':'false'}" data-inbase="${disabled?'true':'false'}" title="${title}">
          <input type="checkbox" class="hidden" data-slot="${slot}" ${isSelected?'checked':''} ${disabled?'disabled':''} onchange="toggleCpSlot(${cp.id},'${slot}',this)">
          ${slot}
        </label>`;
      }).join('');

      const sortedForRender = [...changePoints].sort((a,b)=>a.date.localeCompare(b.date));
      const cpIndex = sortedForRender.findIndex(c => c.id === cp.id);
      const autoTo = calcCpTo(cpIndex);
      cp.dateTo = autoTo;

      return `<div class="border-[1.5px] border-[#e9d5ff] bg-[#faf5ff] rounded-[10px] overflow-hidden animate-[slideIn_0.2s_ease]" id="cp-${cp.id}">
        <div class="flex items-center gap-2.5 px-[14px] py-2.5 bg-[#f3e8ff] border-b border-[#e9d5ff]">
          <span class="text-xs font-bold text-[#6d28d9] whitespace-nowrap">From</span>
          <div class="relative h-8">
            <input type="date" class="absolute inset-0 opacity-0 w-full h-full cursor-pointer z-[2]" value="${cp.date}"
              min="${from || ''}" max="${to || ''}"
              onchange="onCpDateChange(${cp.id},'from',this)">
            <div class="bg-white border-[1.5px] border-[#d8b4fe] rounded-md px-2.5 py-[5px] text-[13px] font-bold text-[#6d28d9] h-8 flex items-center gap-1.5 whitespace-nowrap cursor-pointer transition-[border-color,background] duration-150 relative z-[1] hover:bg-[#f3e8ff] hover:border-[#a78bfa]">
              <svg class="opacity-50 flex-shrink-0" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
              ${cp.date ? fmtShort(cp.date) : 'Pick date'}
            </div>
          </div>
          <span class="text-xs font-bold text-[#6d28d9] whitespace-nowrap" style="margin-left:4px;">To</span>
          <div class="bg-[#ede9fe] border-[1.5px] border-[#d8b4fe] rounded-md px-3 py-[5px] text-[13px] font-bold text-[#6d28d9] h-8 flex items-center whitespace-nowrap select-none cursor-default">${autoTo ? fmtShort(autoTo) : '—'}</div>
          <div class="ml-auto flex items-center gap-1.5">
            <span class="text-[11px] font-semibold text-[#7c3aed] bg-[#ede9fe] px-2 py-0.5 rounded-[10px]" id="cp-count-${cp.id}">${cp.slots.size} new slot${cp.slots.size!==1?'s':''}</span>
            <button class="w-[26px] h-[26px] rounded-md flex items-center justify-center cursor-pointer border-[1.5px] border-[#e9d5ff] bg-white transition-all duration-150 text-[#a78bfa] hover:bg-[#fee2e2] hover:border-[#fca5a5] hover:text-[#dc2626]" onclick="removeChangePoint(${cp.id})" title="Remove change point">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>
        <div class="px-[14px] py-3">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:2px;">
            <div>
              <div class="text-[11px] font-bold text-[#6d28d9] mb-1.5">New time slots added from this date</div>
              <div class="text-[11px] text-[#94a3b8] font-medium mb-1.5">Slots already in the base schedule are shown dimmed.</div>
            </div>
            <div style="display:flex;gap:10px;flex-shrink:0;">
              <button class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]" onclick="cpSelectAll(${cp.id})">Select All</button>
              <button class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]" onclick="cpClearAll(${cp.id})">Clear All</button>
            </div>
          </div>
          <div class="flex flex-wrap gap-1.5 mt-2.5">${chips}</div>
        </div>
      </div>`;
    }).join('');
  }

/* ===== CHUNK 2 ===== */
  // ── Tab helpers (markup uses data-active on each tab; active tab = its own key, others = 'none') ──
  const ACTION_TABS = { repeat:'tabRepeat', status:'tabStatus', seats:'tabSeats', convert:'tabConvert', visibility:'tabVisibility', delete:'tabDelete' };
  function setTabActive(activeKey) {
    Object.entries(ACTION_TABS).forEach(([key, id]) => {
      const el = document.getElementById(id);
      if (el) el.dataset.active = (key === activeKey) ? key : 'none';
    });
  }
  // Editor show/hide helpers (markup uses the `hidden` class)
  function showEditor(id) { const el = document.getElementById(id); if (el) el.classList.remove('hidden'); }
  function hideEditor(id) { const el = document.getElementById(id); if (el) el.classList.add('hidden'); }

  // Tailwind for the create/repeat time-picker selects (.cs-tp) and status (.cs-status)
  const CS_TP_CLS   = "border-[1.5px] border-[#e2e8f0] rounded-md px-1.5 py-[5px] text-[13px] font-semibold text-[#334155] bg-white outline-none cursor-pointer transition-[border-color] duration-150 appearance-none text-center focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]";
  const CS_TP_H_CLS = CS_TP_CLS + " w-[50px]";
  const CS_TP_M_CLS = CS_TP_CLS + " w-[58px]";
  const CS_TP_AP_CLS= CS_TP_CLS + " w-[58px]";
  const CS_COLON_CLS= "text-[15px] font-bold text-[#94a3b8]";
  const CS_SEATS_CLS= "w-[68px] border-[1.5px] border-[#e2e8f0] rounded-md px-2 py-1.5 text-[13px] font-semibold text-[#334155] bg-white outline-none text-center transition-[border-color] duration-150 focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]";
  const CS_DEL_CLS  = "w-7 h-7 rounded-md flex items-center justify-center cursor-pointer border-0 bg-[#dc2626] text-white transition-colors duration-150 hover:bg-[#b91c1c]";
  const CS_TD_CLS   = "px-3 py-2.5 border-b border-[#f1f5f9] align-middle";
  // .cs-status base + per-value backgrounds (no native arrow image; rely on appearance)
  function csStatusCls(status) {
    const base = "border-[1.5px] rounded-md py-[5px] pl-2.5 pr-3 text-xs font-bold outline-none cursor-pointer transition-all duration-150";
    if (status === 'Open')   return base + " bg-[#dcfce7] border-[#86efac] text-[#16a34a]";
    if (status === 'Closed') return base + " bg-[#fee2e2] border-[#fca5a5] text-[#dc2626]";
    return base + " bg-white border-[#e2e8f0] text-[#475569] focus:border-[#1e3a5c]";
  }

  // ── Action ──
  function setAction(a) {
    selectedAction = a;
    repeatPanelOpen = false;
    hideEditor('repeatEditor');
    delDeactivate();
    setTabActive(a);
    if (a === 'convert') {
      showEditor('convertEditor');
      renderConvertRules();
    } else {
      hideEditor('convertEditor');
    }
    // Status & Visibility share one editor now (status + per-slot show/hide + bulk "set all").
    if (a === 'status') showEditor('statusModeEditor'); else hideEditor('statusModeEditor');
    renderSeatEditor();
    renderStatusEditor();
    updatePreview();
  }

  // ─────────────────────────────────────────────
  // Repeat logic migrated from standalone RepeatTourSlots page
  // ─────────────────────────────────────────────
  let repeatPanelOpen = false;
  let statusForAllDates = 'Active';
  let repeatRows = [];
  let repeatRowCounter = 0;

  const REPEAT_HOURS = ['1','2','3','4','5','6','7','8','9','10','11','12'];
  const REPEAT_MINS  = Array.from({length:60},(_,i)=>String(i).padStart(2,'0'));

  function repeatHrOpts(sel) { return REPEAT_HOURS.map(h => `<option value="${h}"${sel===h?' selected':''}>${h}</option>`).join(''); }
  function repeatMnOpts(sel) { return REPEAT_MINS.map(m => `<option value="${m}"${sel===m?' selected':''}>${m}</option>`).join(''); }
  function repeatApOpts(sel) { return ['am','pm'].map(p => `<option value="${p}"${sel===p?' selected':''}>${p}</option>`).join(''); }

  function repeatParseSlot(slot) {
    const m = String(slot).match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (!m) return { h: '7', m: '00', ap: 'am' };
    return { h: String(parseInt(m[1], 10)), m: m[2], ap: m[3].toLowerCase() };
  }
  function repeatTo24(h, m, ap) { let hh = parseInt(h, 10) % 12; if (ap === 'pm') hh += 12; return hh * 60 + parseInt(m, 10); }
  function repeatFrom24(mins) {
    mins = ((mins % 1440) + 1440) % 1440;
    let hh = Math.floor(mins / 60), mm = mins % 60;
    const ap = hh >= 12 ? 'pm' : 'am';
    let h12 = hh % 12; if (h12 === 0) h12 = 12;
    return { h: String(h12), m: String(mm).padStart(2, '0'), ap };
  }

  function repeatBuildRowFor(slot) {
    repeatRowCounter++;
    const fp = repeatParseSlot(slot);
    const t  = repeatFrom24(repeatTo24(fp.h, fp.m, fp.ap) + 60);
    return {
      id: String(Date.now()) + '_' + repeatRowCounter,
      slot,
      fromHour: fp.h, fromMinute: fp.m, fromPeriod: fp.ap,
      toHour: t.h, toMinute: t.m, toPeriod: t.ap,
      seats: 50, bookingStatus: 'Open', hidden: false
    };
  }

  function repeatSeedFromSelection() {
    repeatRows = Array.from(selectedSlots).map(slot => repeatBuildRowFor(slot));
  }

  function repeatAddNewRow() {
    repeatRowCounter++;
    repeatRows.push({
      id: String(Date.now()) + '_' + repeatRowCounter,
      slot: null,
      fromHour: '7', fromMinute: '00', fromPeriod: 'am',
      toHour: '8', toMinute: '00', toPeriod: 'am',
      seats: 50, bookingStatus: 'Open', hidden: false
    });
    repeatRenderRows();
  }

  function repeatOnStep2Toggle(slot, isSelected) {
    if (selectedAction !== 'repeat') return;
    if (isSelected) {
      if (!repeatRows.some(r => r.slot === slot)) repeatRows.push(repeatBuildRowFor(slot));
    } else {
      repeatRows = repeatRows.filter(r => r.slot !== slot);
    }
    repeatRenderRows();
  }

  function repeatSyncFromSelectionBulk() {
    if (selectedAction !== 'repeat') return;
    selectedSlots.forEach(slot => { if (!repeatRows.some(r => r.slot === slot)) repeatRows.push(repeatBuildRowFor(slot)); });
    repeatRows = repeatRows.filter(r => r.slot === null || selectedSlots.has(r.slot));
    repeatRenderRows();
  }

  function toggleRepeat() {
    if (selectedAction === 'repeat') {
      selectedAction = null;
      repeatPanelOpen = false;
      setTabActive(null);
      hideEditor('repeatEditor');
      updatePreview();
      return;
    }
    selectedAction = 'repeat';
    repeatPanelOpen = true;
    setTabActive('repeat');
    hideEditor('convertEditor');
    hideEditor('seatEditor');
    hideEditor('statusModeEditor');
    hideEditor('statusEditor');
    hideEditor('visModeEditor');
    delDeactivate();
    showEditor('repeatEditor');
    const rFrom = document.getElementById('repeatFromDate');
    const rTo   = document.getElementById('repeatToDate');
    if (rFrom && !rFrom.value) rFrom.value = document.getElementById('fromDate').value || '';
    if (rTo   && !rTo.value)   rTo.value   = document.getElementById('toDate').value   || '';
    syncRepeatDatePickers();
    if (repeatRows.length === 0) repeatSeedFromSelection();
    repeatRenderRows();
    updatePreview();
  }

  function editRepeatQueueItem(id) {
    const q = queue.find(item => item.id === id);
    if (!q) return;
    queue = queue.filter(item => item.id !== id);
    renderQueue();

    document.getElementById('tourPackage').value = q.pkg;
    syncPkgTriggerLabel();
    repeatRows = (q.repeatRows || []).map(r => ({ ...r }));
    statusForAllDates = q.statusForAllDates || 'Active';

    selectedAction = 'repeat';
    repeatPanelOpen = true;
    setTabActive('repeat');
    hideEditor('convertEditor');
    hideEditor('seatEditor');
    hideEditor('statusModeEditor');
    hideEditor('statusEditor');
    hideEditor('visModeEditor');
    delDeactivate();
    showEditor('repeatEditor');

    const rf = document.getElementById('repeatFromDate'); if (rf) rf.value = q.from || '';
    const rt = document.getElementById('repeatToDate');   if (rt) rt.value = q.to   || '';
    syncRepeatDatePickers();
    const rs = document.getElementById('repeatStatusSel'); if (rs) rs.value = statusForAllDates;

    repeatRenderRows();
    updatePreview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function repeatStatusForAllDates(v) { statusForAllDates = v; }

  function repeatRenderRows() {
    const tbody = document.getElementById('repeatSlotRows');
    if (!tbody) return;
    if (repeatRows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:18px;color:#b45309;font-size:12px;font-weight:600;">No slots to repeat — select time slots in Step 2, then click Reset to load them.</td></tr>`;
      repeatUpdatePreview();
      return;
    }
    tbody.innerHTML = repeatRows.map(r => {
      return `<tr id="repeatrow-${r.id}">
        <td class="${CS_TD_CLS}">
          <div class="flex items-center gap-[3px]">
            <select class="${CS_TP_H_CLS}" onchange="repeatSet('${r.id}','fromHour',this.value)">${repeatHrOpts(r.fromHour)}</select>
            <span class="${CS_COLON_CLS}">:</span>
            <select class="${CS_TP_M_CLS}" onchange="repeatSet('${r.id}','fromMinute',this.value)">${repeatMnOpts(r.fromMinute)}</select>
            <select class="${CS_TP_AP_CLS}" onchange="repeatSet('${r.id}','fromPeriod',this.value)">${repeatApOpts(r.fromPeriod)}</select>
          </div>
        </td>
        <td class="${CS_TD_CLS}">
          <div class="flex items-center gap-[3px]">
            <select class="${CS_TP_H_CLS}" onchange="repeatSet('${r.id}','toHour',this.value)">${repeatHrOpts(r.toHour)}</select>
            <span class="${CS_COLON_CLS}">:</span>
            <select class="${CS_TP_M_CLS}" onchange="repeatSet('${r.id}','toMinute',this.value)">${repeatMnOpts(r.toMinute)}</select>
            <select class="${CS_TP_AP_CLS}" onchange="repeatSet('${r.id}','toPeriod',this.value)">${repeatApOpts(r.toPeriod)}</select>
          </div>
        </td>
        <td class="${CS_TD_CLS}"><input type="number" class="${CS_SEATS_CLS}" value="${r.seats}" min="0" max="999" onchange="repeatSet('${r.id}','seats',+this.value)" onfocus="this.select()"></td>
        <td class="${CS_TD_CLS}">
          <select class="${csStatusCls(r.bookingStatus)}" onchange="repeatOnStatus('${r.id}',this)">
            <option value="Open"${r.bookingStatus==='Open'?' selected':''}>Open</option>
            <option value="Closed"${r.bookingStatus==='Closed'?' selected':''}>Closed</option>
          </select>
        </td>
        <td class="${CS_TD_CLS}" style="text-align:center;"><span data-hidetoggle class="inline-flex items-center cursor-pointer align-middle group/ht" data-on="${r.hidden?'true':'false'}" onclick="repeatToggleHidden('${r.id}')" title="${r.hidden?'Hidden':'Visible'}"><span class="relative w-[34px] h-[18px] rounded-[10px] bg-[#cbd5e1] transition-colors duration-150 group-data-[on=true]/ht:bg-[#0d9488] after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-[14px] after:h-[14px] after:rounded-full after:bg-white after:transition-transform after:duration-150 after:shadow-[0_1px_2px_rgba(0,0,0,0.2)] group-data-[on=true]/ht:after:translate-x-[16px]"></span></span></td>
        <td class="${CS_TD_CLS}">
          <button class="${CS_DEL_CLS}" onclick="repeatDel('${r.id}')" title="Don't repeat this slot">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </td>
      </tr>`;
    }).join('');
    repeatUpdatePreview();
  }

  function repeatSet(id, field, value) {
    const r = repeatRows.find(x => x.id === id);
    if (r) r[field] = value;
  }

  function repeatOnStatus(id, sel) {
    const r = repeatRows.find(x => x.id === id);
    if (!r) return;
    r.bookingStatus = sel.value;
    sel.className = csStatusCls(sel.value);
  }

  function repeatDel(id) {
    repeatRows = repeatRows.filter(r => r.id !== id);
    repeatRenderRows();
  }

  function repeatToggleHidden(id) {
    const r = repeatRows.find(x => x.id === id);
    if (!r) return;
    r.hidden = !r.hidden;
    repeatRenderRows();
  }

  function repeatUpdatePreview() {
    const el = document.getElementById('repeatPreviewLine');
    if (!el || selectedAction !== 'repeat') return;
    const Z = repeatRows.length;
    if (Z === 0) { el.innerHTML = 'No slots to repeat — load them from Step 2'; return; }
    const from = document.getElementById('repeatFromDate').value;
    const to   = document.getElementById('repeatToDate').value;
    if (!from || !to || from > to) { el.innerHTML = 'Set a Repeat date range above to see preview'; return; }
    const Y = getDates(from, to).length;
    el.innerHTML = `This will repeat <strong>${Z} slot${Z!==1?'s':''}</strong> across <strong>${Y} day${Y!==1?'s':''}</strong>`;
  }

  function repeatReset() {
    statusForAllDates = 'Active';
    const sel = document.getElementById('repeatStatusSel');
    if (sel) sel.value = 'Active';
    const rFrom = document.getElementById('repeatFromDate');
    const rTo   = document.getElementById('repeatToDate');
    if (rFrom) rFrom.value = document.getElementById('fromDate').value || '';
    if (rTo)   rTo.value   = document.getElementById('toDate').value   || '';
    syncRepeatDatePickers();
    repeatSeedFromSelection();
    repeatRenderRows();
  }

  function repeatAddToQueue() {
    const pkg     = document.getElementById('tourPackage').value;
    const pkgEl   = document.getElementById('tourPackage');
    const pkgName = pkgEl.options[pkgEl.selectedIndex].text;
    const from    = document.getElementById('repeatFromDate').value;
    const to      = document.getElementById('repeatToDate').value;

    if (repeatRows.length === 0)  { alert('No slots to repeat — select slots in Step 2 and click Reset to load them.'); return; }
    if (!pkg)                     { alert('Select a Tour Package in Step 1 first.'); return; }
    if (!from || !to)             { alert('Set the Repeat date range above first.'); return; }
    if (from > to)                { alert('Repeat From Date must be on or before the To Date.'); return; }

    const fromKey = r => `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toLowerCase()}`;
    const seen = new Set(), dups = new Set();
    repeatRows.forEach(r => { const k = fromKey(r); if (seen.has(k)) dups.add(k); else seen.add(k); });
    if (dups.size > 0) {
      showAlertPopup('Please remove or change the repeated From time(s): ' + [...dups].join(', '), 'Duplicate time slots');
      return;
    }

    const zeroSeat = repeatRows.filter(r => !(Number(r.seats) > 0));
    if (zeroSeat.length > 0) {
      const labels = zeroSeat.map(fromKey).join(', ');
      showAlertPopup(`Every time slot must have at least 1 seat. Please set seats for: ${labels}`, 'Seats required');
      return;
    }

    const newKeys = new Set();
    const fromLabels = repeatRows.map(fromKey);
    getDates(from, to).forEach(d => fromLabels.forEach(t => newKeys.add(`${d}|${t}`)));
    const conflicts = new Set();
    queue.filter(it => it.action === 'repeat' && it.pkg === pkg).forEach(it => {
      const itLabels = (it.repeatRows || []).map(fromKey);
      getDates(it.from, it.to).forEach(d => itLabels.forEach(t => {
        if (newKeys.has(`${d}|${t}`)) conflicts.add(`${fmtShort(d)} ${t}`);
      }));
    });
    if (conflicts.size > 0) {
      const list = [...conflicts].slice(0, 12).join(', ') + (conflicts.size > 12 ? `, +${conflicts.size - 12} more` : '');
      showAlertPopup(`These date/time slots are already in another queued Repeat operation for this package: ${list}`, 'Overlapping slots');
      return;
    }

    const Z = repeatRows.length;
    const Y = getDates(from, to).length;
    const X = Y * Z;

    queueCounter++;
    queue.push({
      id: queueCounter,
      pkg, pkgName, from, to,
      action:        'repeat',
      repeatRows:    repeatRows.map(r => ({ ...r })),
      statusForAllDates,
      baseSlots:     [],
      changePoints:  [],
      seatSnapshot:  null,
      total:         X,
      slotsPerDay:   Z,
      numDays:       Y
    });
    renderQueue();

    selectedAction = null;
    repeatPanelOpen = false;
    repeatRows = [];
    clearAllSlots();
    statusForAllDates = 'Active';
    const rs = document.getElementById('repeatStatusSel'); if (rs) rs.value = 'Active';
    const rf = document.getElementById('repeatFromDate');  if (rf) rf.value = '';
    const rt = document.getElementById('repeatToDate');    if (rt) rt.value = '';
    syncRepeatDatePickers();
    setTabActive(null);
    hideEditor('repeatEditor');
    updatePreview();
  }

/* ===== CHUNK 3 ===== */
  // ── Seat Editor ──
  function renderSeatEditor() {
    const body   = document.getElementById('seatEditorBody');
    if (selectedAction !== 'seats') { hideEditor('seatEditor'); return; }
    showEditor('seatEditor');

    const baseSlots = Array.from(selectedSlots);
    const sortedCps = [...changePoints].sort((a,b)=>a.date.localeCompare(b.date));
    const from = document.getElementById('fromDate').value;

    if (baseSlots.length === 0 && sortedCps.every(cp=>cp.slots.size===0)) {
      body.innerHTML = '<div class="p-5 text-center text-xs text-[#94a3b8] font-semibold bg-[#fffdf5]">Select time slots above to set seat counts</div>';
      return;
    }

    let html = '';

    if (baseSlots.length > 0) {
      const firstCpDate = sortedCps.length > 0 ? sortedCps[0].date : null;
      const rangeLabel = firstCpDate
        ? `${fmtShort(from)} → ${fmtShort(prevDay(firstCpDate))}`
        : `All dates`;
      html += `<div class="flex items-center gap-2 px-[14px] py-[7px] text-[11px] font-bold border-b border-[#fde68a] bg-[#fffdf5] text-[#92400e]">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Base schedule
        <span class="text-[10px] font-medium opacity-65">${rangeLabel}</span>
      </div>`;
      html += baseSlots.map(slot => buildSeatRow(slot, false)).join('');
    }

    sortedCps.forEach((cp, idx) => {
      if (cp.slots.size === 0) return;
      const nextCp = sortedCps[idx + 1];
      const to = document.getElementById('toDate').value;
      const endDate = nextCp ? prevDay(nextCp.date) : to;
      const newSlots = Array.from(cp.slots);

      const cpEndDisplay = cp.dateTo ? fmtShort(cp.dateTo) : (endDate ? fmtShort(endDate) : '');
      html += `<div class="flex items-center gap-2 px-[14px] py-[7px] text-[11px] font-bold bg-[#f5f3ff] text-[#6d28d9] border-t border-[#e9d5ff] border-b border-b-[#e9d5ff]">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Schedule change
        <span class="text-[10px] font-medium opacity-65">${fmtShort(cp.date)}${cpEndDisplay ? ' → '+cpEndDisplay : ''}</span>
        <span class="bg-[#ede9fe] text-[#6d28d9] text-[10px] font-bold px-1.5 py-px rounded-[10px]">+${newSlots.length} new</span>
      </div>`;
      html += newSlots.map(slot => buildSeatRow(slot, true)).join('');
    });

    body.innerHTML = html || '<div class="p-5 text-center text-xs text-[#94a3b8] font-semibold bg-[#fffdf5]">Select time slots above to set seat counts</div>';
  }

  function buildSeatRow(slot, isNew) {
    const data = perSlotSeats[slot] || { newCount: null, currentCount: 70 };
    const displayNew = data.newCount !== null ? data.newCount : '';
    const isChanged = data.newCount !== null && data.newCount !== data.currentCount;
    const rowId = 'srow-' + slot.replace(/[:\s]/g,'_');
    const newTag = isNew ? `<span class="inline-flex items-center gap-[3px] text-[9px] font-bold text-[#7c3aed] bg-[#ede9fe] px-[5px] py-px rounded-[4px] ml-1 uppercase tracking-[0.3px]">New</span>` : '';
    // grid: 1fr 90px 28px 90px auto ; row bg / changed styling via data attributes
    const rowCls = "group grid grid-cols-[1fr_90px_28px_90px_auto] items-center gap-2.5 px-[14px] py-[9px] border-b transition-colors duration-100 "
      + (isNew
          ? "border-b-[#ede9fe] bg-[#faf5ff] hover:bg-[#f3e8ff] data-[changed=true]:bg-[#f3e8ff]"
          : "border-b-[#fef3c7] bg-[#fffdf5] hover:bg-[#fffbeb] data-[changed=true]:bg-[#fffbeb]");
    const inputCls = "w-full border-[1.5px] rounded-md px-2 py-[5px] text-[13px] font-bold text-[#1e293b] outline-none bg-white text-center transition-[border-color] duration-150 "
      + (isNew
          ? "border-[#e2e8f0] focus:border-[#7c3aed] focus:shadow-[0_0_0_3px_rgba(124,58,237,0.12)] group-data-[changed=true]:border-[#a78bfa] group-data-[changed=true]:bg-[#faf5ff]"
          : "border-[#e2e8f0] focus:border-[#d97706] focus:shadow-[0_0_0_3px_rgba(217,119,6,0.12)] group-data-[changed=true]:border-[#f59e0b] group-data-[changed=true]:bg-[#fffbeb]");
    const badgeCls = "text-[10px] font-bold px-[7px] py-0.5 rounded-[10px] whitespace-nowrap opacity-0 transition-opacity duration-150 group-data-[changed=true]:opacity-100 "
      + (isNew ? "text-[#7c3aed] bg-[#ede9fe]" : "text-[#d97706] bg-[#fef3c7]");
    return `<div class="${rowCls}" data-changed="${isChanged?'true':'false'}" id="${rowId}">
      <span class="text-[13px] font-bold text-[#1e293b]">${slot}${newTag}</span>
      <div class="text-xs font-semibold text-[#94a3b8] text-center bg-[#f1f5f9] rounded-md px-2 py-[5px] border border-[#e2e8f0]" title="Current seat count">${data.currentCount}</div>
      <div class="text-[#94a3b8] flex items-center justify-center"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></div>
      <input class="${inputCls}" type="number" min="0" max="999"
        value="${displayNew}" placeholder="${data.currentCount}"
        oninput="onSeatInput('${slot}',this)" onfocus="this.select()">
      <span class="${badgeCls}">changed</span>
    </div>`;
  }

  function prevDay(dateStr) {
    const d = new Date(parseLocal(dateStr));
    d.setDate(d.getDate() - 1);
    return fmtDate(d);
  }

  function onSeatInput(slot, input) {
    const val = input.value.trim();
    const parsed = val === '' ? null : parseInt(val);
    if (!perSlotSeats[slot]) perSlotSeats[slot] = { newCount: null, currentCount: 70 };
    perSlotSeats[slot].newCount = parsed;
    const rowId = 'srow-' + slot.replace(/[:\s]/g,'_');
    const row = document.getElementById(rowId);
    if (row) row.dataset.changed = (parsed !== null && parsed !== perSlotSeats[slot].currentCount) ? 'true' : 'false';
    updatePreview();
  }

  function applyQuickFill() {
    const val = parseInt(document.getElementById('quickFillInput').value);
    if (isNaN(val) || val < 0) return;
    Object.keys(perSlotSeats).forEach(slot => { perSlotSeats[slot].newCount = val; });
    renderSeatEditor();
    updatePreview();
  }

  // ── Convert Editor (slot-driven, no dropdown needed) ──
  function onTimePartChange(slot, part, value) {
    if (!convertTimeParts[slot]) convertTimeParts[slot] = { h: '', m: '00', ap: 'AM' };
    convertTimeParts[slot][part] = value;
    const p = convertTimeParts[slot];
    convertTimes[slot] = (p.h && p.m && p.ap) ? `${p.h}:${p.m} ${p.ap}` : '';
    renderConvertRules();
    updatePreview();
  }

  function onConvertSeatInput(slot, value) {
    const parsed = parseInt(value);
    convertSeats[slot] = isNaN(parsed) || parsed < 1 ? null : parsed;
    updatePreview();
  }

  function onConvertStatusChange(slot, value) {
    convertStatus[slot] = value;
    renderConvertRules();
    updatePreview();
  }

  function onConvertOnlineChange(slot, value) {
    convertOnline[slot] = value === 'true';
    renderConvertRules();
    updatePreview();
  }

  function toggleConvertGroup(gi) {
    const rows    = document.querySelectorAll(`tr[data-cv-group="${gi}"]`);
    const details = document.querySelectorAll(`tr[data-cv-detail^="${gi}-"]`);
    const chevron = document.querySelector(`tr[data-cv-header="${gi}"] .cv-chevron`);
    const isCollapsed = rows.length > 0 && rows[0].style.display === 'none';
    rows.forEach(r => r.style.display = isCollapsed ? '' : 'none');
    if (!isCollapsed) details.forEach(r => { r.style.display = 'none'; });
    if (chevron) chevron.style.transform = isCollapsed ? '' : 'rotate(-90deg)';
  }

  function toggleConvertDay(gdi) {
    const detail  = document.querySelector(`tr[data-cv-detail="${gdi}"]`);
    const chevron = document.querySelector(`tr[data-cv-day="${gdi}"] .cv-day-chevron`);
    if (!detail) return;
    const isHidden = detail.style.display === 'none';
    detail.style.display = isHidden ? '' : 'none';
    if (chevron) chevron.style.transform = isHidden ? 'rotate(0deg)' : 'rotate(-90deg)';
  }

  // Tailwind for convert row sub-elements
  const TP_SEL_CLS = "border-[1.5px] border-[#a5f3fc] rounded-[7px] px-1.5 py-[5px] text-[13px] font-bold text-[#0e7490] bg-white outline-none cursor-pointer transition-[border-color] duration-150 appearance-none text-center focus:border-[#0891b2] focus:shadow-[0_0_0_3px_rgba(8,145,178,0.12)] data-[filled=true]:bg-[#ecfeff] data-[filled=true]:border-[#0891b2]";
  function csrFieldSelectCls(status) {
    const base = "w-[150px] border-[1.5px] rounded-[7px] px-2.5 py-[5px] text-xs font-bold font-[inherit] outline-none cursor-pointer transition-all duration-150 focus:border-[#0891b2] focus:shadow-[0_0_0_3px_rgba(8,145,178,0.12)]";
    if (status === 'Open')   return base + " bg-[#dcfce7] border-[#86efac] text-[#16a34a]";
    if (status === 'Only by Phone') return base + " bg-[#e0e7ff] border-[#c7d2fe] text-[#4338ca]";
    if (status === 'Closed') return base + " bg-[#fee2e2] border-[#fca5a5] text-[#dc2626]";
    return base + " bg-white border-[#e2e8f0] text-[#475569]";
  }

  function renderConvertRules() {
    const list = document.getElementById('convertRulesList');
    if (!list) return;

    const cpSlots = new Set();
    changePoints.forEach(cp => cp.slots.forEach(s => cpSlots.add(s)));
    const slots = sortSlots(new Set([...selectedSlots, ...cpSlots]));
    if (slots.length === 0) {
      list.innerHTML = '<div style="color:#94a3b8;font-size:13px;text-align:center;padding:14px 0;font-weight:600;">Select time slots in Step 2 — they\'ll appear here</div>';
      return;
    }

    const mergeMap = {};
    slots.forEach(s => {
      const nt = (convertTimes[s] || '').trim();
      if (nt) { if (!mergeMap[nt]) mergeMap[nt] = []; mergeMap[nt].push(s); }
    });

    const fromDate = document.getElementById('fromDate').value;

    const convHeader = `<div class="flex items-center gap-x-7 px-4 pt-0.5 pb-1.5" style="min-width:max-content">
      <span class="w-[150px] flex-shrink-0 text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b]">Current slot</span>
      <span class="w-4 flex-shrink-0"></span>
      <span class="w-[200px] flex-shrink-0 text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b]">New time</span>
      <span class="w-[72px] flex-shrink-0 text-center text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b]">Seats</span>
      <span class="w-[150px] flex-shrink-0 text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b]">Status</span>
      <span class="w-[150px] flex-shrink-0 text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b]">Online</span>
    </div>`;
    list.innerHTML = convHeader + slots.map(slot => {
      const newTime  = (convertTimes[slot] || '').trim();
      const dupGroup = newTime && mergeMap[newTime] && mergeMap[newTime].length > 1 ? mergeMap[newTime] : null;
      const isDup = !!dupGroup;
      const isMerge = false, isMergeLeader = false, isMergeFollower = false;
      const safeSlot = slot.replace(/\\/g,'\\\\').replace(/'/g,"\\'");

      const slotInfo    = fromDate ? getSlotData(fromDate, slot) : null;
      const currentSeats = slotInfo ? slotInfo.seats : null;

      const sameAsOld = !!newTime && newTime === slot;
      const invalid   = sameAsOld || isDup;
      const mergeNote  = '';
      const sameNoteCls = "inline-flex items-center gap-[3px] ml-auto text-[10px] font-bold text-[#dc2626] whitespace-nowrap";
      const dupNote    = (isDup && !sameAsOld) ? `<span class="${sameNoteCls}"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Same new time as ${dupGroup.filter(s=>s!==slot).join(', ')} — merging not allowed</span>` : '';
      const sameNote   = sameAsOld ? `<span class="${sameNoteCls}"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Same as current time — choose a different one</span>` : '';
      const deleteNote = newTime && !invalid ? `<span class="ml-auto text-[10px] text-[#dc2626] font-semibold flex items-center gap-[3px] whitespace-nowrap"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg> old slot deleted</span>` : '';

      if (!convertTimeParts[slot]) {
        const match = newTime.match(/^(\d+):(\d+)\s*(AM|PM)$/i);
        convertTimeParts[slot] = match
          ? { h: match[1], m: match[2], ap: match[3].toUpperCase() }
          : { h: '', m: '00', ap: 'AM' };
      }
      const p = convertTimeParts[slot];
      const hourOpts = Array.from({length:12},(_,i)=>i+1)
        .map(h => `<option value="${h}"${String(p.h)===String(h)?' selected':''}>${h}</option>`).join('');
      const minOpts = Array.from({length:60},(_,i)=>String(i).padStart(2,'0'))
        .map(m => `<option value="${m}"${p.m===m?' selected':''}>${m}</option>`).join('');

      const settingsKey = slot;
      const safeSettingsKey = settingsKey.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      if (convertSeats[settingsKey] == null && currentSeats != null) convertSeats[settingsKey] = currentSeats;
      const seatVal = convertSeats[settingsKey] != null ? convertSeats[settingsKey] : '';
      if (convertStatus[settingsKey] == null) convertStatus[settingsKey] = 'Open';
      const status = convertStatus[settingsKey];
      // Online visibility: default to the slot's current visibility (show unless it's hidden).
      if (convertOnline[settingsKey] == null) convertOnline[settingsKey] = slotInfo ? !slotInfo.hide : true;
      const online = convertOnline[settingsKey];
      const onlineSelCls = "w-[150px] border-[1.5px] rounded-[7px] px-2.5 py-[5px] text-xs font-bold font-[inherit] outline-none cursor-pointer transition-all duration-150 focus:border-[#0891b2] focus:shadow-[0_0_0_3px_rgba(8,145,178,0.12)] "
        + (online ? "bg-[#dcfce7] border-[#86efac] text-[#16a34a]" : "bg-[#fee2e2] border-[#fca5a5] text-[#dc2626]");

      const seatInputCls = "w-[72px] border-[1.5px] border-[#a5f3fc] rounded-[7px] px-2 py-[5px] text-[13px] font-bold text-[#0e7490] bg-white outline-none text-center transition-[border-color] duration-150 focus:border-[#0891b2] focus:shadow-[0_0_0_3px_rgba(8,145,178,0.12)]"
        + (seatVal ? " bg-[#ecfeff] border-[#0891b2]" : "");
      const settingsHtml = `
        <input class="${seatInputCls}" type="number" min="1" max="999"
          value="${seatVal}" placeholder="—"
          oninput="onConvertSeatInput('${safeSettingsKey}',this.value)" onfocus="this.select()">
        <select class="${csrFieldSelectCls(status)}"
          onchange="onConvertStatusChange('${safeSettingsKey}',this.value)">
          <option value="Open"${status==='Open'?' selected':''}>Open</option>
          <option value="Only by Phone"${status==='Only by Phone'?' selected':''}>Only by Phone</option>
          <option value="Closed"${status==='Closed'?' selected':''}>Closed</option>
        </select>
        <select class="${onlineSelCls}"
          onchange="onConvertOnlineChange('${safeSettingsKey}',this.value)">
          <option value="true"${online ? ' selected' : ''}>Show online</option>
          <option value="false"${!online ? ' selected' : ''}>Hide online</option>
        </select>`;

      const rowCls = "flex items-center gap-x-7 gap-y-2.5 px-4 py-3 border-[1.5px] rounded-lg mb-[7px] "
        + (invalid ? "border-[#fca5a5] bg-[#fef2f2]" : "border-[#a5f3fc] bg-[#f0fdfe]");
      const oldTimeCls = "text-[13px] font-bold text-[#0e7490] w-[150px] flex-shrink-0 flex items-center gap-[5px]"
        + (newTime && !invalid ? " line-through !text-[#94a3b8]" : "");
      return `<div class="${rowCls}" style="min-width:max-content">
        <span class="${oldTimeCls}">
          ${slot}
          ${currentSeats != null ? `<span class="text-[10px] font-bold bg-[#cffafe] text-[#0e7490] px-1.5 py-px rounded-[4px] no-underline whitespace-nowrap">${currentSeats} seats</span>` : ''}
        </span>
        <span class="text-[15px] font-bold text-[#0891b2] flex-shrink-0 w-4 text-center">→</span>
        <div class="flex items-center gap-[5px] w-[200px] flex-shrink-0">
          <select class="${TP_SEL_CLS} w-[56px]" data-filled="${p.h?'true':'false'}" onchange="onTimePartChange('${safeSlot}','h',this.value)">
            <option value="">--</option>
            ${hourOpts}
          </select>
          <span class="text-base font-bold text-[#0e7490] leading-none">:</span>
          <select class="${TP_SEL_CLS} w-[62px]" data-filled="${(p.m&&p.h)?'true':'false'}" onchange="onTimePartChange('${safeSlot}','m',this.value)">
            ${minOpts}
          </select>
          <select class="${TP_SEL_CLS} w-[58px]" data-filled="${p.h?'true':'false'}" onchange="onTimePartChange('${safeSlot}','ap',this.value)">
            <option value="AM"${p.ap==='AM'?' selected':''}>AM</option>
            <option value="PM"${p.ap==='PM'?' selected':''}>PM</option>
          </select>
        </div>
        ${settingsHtml}
        ${deleteNote}
        ${mergeNote}
        ${sameNote}
        ${dupNote}
      </div>`;
    }).join('');
  }

  // ── Update Status — single action; global default + per-slot overrides ──
  let statusValue = ''; // '' = "Select Status" placeholder until the user picks one
  let perSlotStatus = {};
  let perSlotVisible = {}; // { slotName: true (show online) | false (hide) } — per-slot visibility

  function statusAffectedSlots() {
    const set = new Set(selectedSlots);
    changePoints.forEach(cp => cp.slots.forEach(s => set.add(s)));
    return sortSlots(set);
  }
  function slotStatus(slot) { return perSlotStatus[slot] || statusValue; }
  function slotVisible(slot) { return perSlotVisible[slot] !== false; } // default: show online

  // Normalize any stored status string to a canonical value (handles casing/wording variants).
  function normalizeStatus(s) {
    if (!s) return 'Open';
    if (/phone/i.test(s)) return 'Only by Phone';
    if (/clos/i.test(s))  return 'Closed';
    return 'Open';
  }
  // The slot's real current bookable status, from the first date in range where it exists.
  function currentSlotStatus(slot) {
    if (slotsLoaded) {
      const from = document.getElementById('fromDate').value;
      const to   = document.getElementById('toDate').value;
      if (from && to && from <= to) {
        for (const date of getDates(from, to)) {
          const r = slotIndex[`${date}|${slot}`];
          if (r) return normalizeStatus(r.status);
        }
      }
    }
    return statusValue || 'Open';
  }

  // Map status value to the markup's data-val (green/red/grey) for #statusModeSelect / #visModeSelect
  function statusModeVal(v) { return v === 'Open' ? 'green' : v === 'Closed' ? 'red' : 'grey'; }

  function onStatusModeChange(v) {
    statusValue = v;
    const sel = document.getElementById('statusModeSelect');
    if (sel) sel.dataset.val = v ? statusModeVal(v) : 'grey';
    // Only push the chosen status onto every slot once a real status is picked (not the placeholder).
    if (v) statusAffectedSlots().forEach(s => { perSlotStatus[s] = v; });
    renderStatusEditor();
    updatePreview();
  }
  function onPerSlotStatusChange(slot, v) {
    perSlotStatus[slot] = v;
    renderStatusEditor();
    updatePreview();
  }

  // Bulk "set visibility for all slots" (Status & Visibility mode editor).
  function onVisibilityModeChange(v) {
    const sel = document.getElementById('visModeSelect');
    if (sel) sel.dataset.val = v === 'true' ? 'green' : (v === 'false' ? 'red' : 'grey');
    if (v !== '') {
      const show = (v === 'true');
      statusAffectedSlots().forEach(s => { perSlotVisible[s] = show; });
    }
    renderStatusEditor();
    updatePreview();
  }
  // Per-slot online visibility in the Status & Visibility editor ('' = Select Visibility → no change).
  function onPerSlotVisibleChange(slot, v) {
    if (v === '') delete perSlotVisible[slot];
    else perSlotVisible[slot] = (v === 'true');
    renderStatusEditor();
    updatePreview();
  }

  // .status-row-select state classes (st-open/st-close/st-phone)
  function statusRowSelectCls(s) {
    const base = "border-[1.5px] rounded-[7px] px-2.5 py-[5px] text-xs font-bold font-[inherit] bg-white outline-none cursor-pointer transition-all duration-150 focus:border-[#16a34a] focus:shadow-[0_0_0_3px_rgba(22,163,74,0.12)]";
    if (s === 'Open')   return base + " bg-[#dcfce7] border-[#86efac] text-[#16a34a]";
    if (s === 'Closed') return base + " bg-[#fee2e2] border-[#fca5a5] text-[#dc2626]";
    if (s === 'Only by Phone') return base + " bg-[#e0e7ff] border-[#c7d2fe] text-[#4338ca]";
    return base + " bg-white border-[#e2e8f0] text-[#475569]"; // "Select Status" (no selection)
  }
  function statusBadgeClass(s) { return s === 'Open' ? 'st-open' : s === 'Closed' ? 'st-close' : 'st-phone'; }

  function renderStatusEditor() {
    const ed = document.getElementById('statusEditor');
    if (!ed) return;
    if (selectedAction !== 'status') { hideEditor('statusEditor'); return; }
    showEditor('statusEditor');
    const body = document.getElementById('statusEditorBody');
    const slots = statusAffectedSlots();
    if (slots.length === 0) {
      body.innerHTML = `<div class="py-[18px] px-[14px] text-center text-xs font-semibold text-[#94a3b8]">Select time slots in Step 2 to set their status</div>`;
      return;
    }
    const cpSet = new Set(changePoints.flatMap(cp => [...cp.slots]));
    body.innerHTML = slots.map(slot => {
      const st = perSlotStatus[slot] || ''; // default to the "Select Status" placeholder
      const vis = perSlotVisible[slot];     // undefined = "Select Visibility", true = show, false = hide
      const isNew = !selectedSlots.has(slot) && cpSet.has(slot);
      const safe = slot.replace(/\\/g,'\\\\').replace(/'/g,"\\'");
      return `<div class="flex items-center justify-between gap-2.5 px-[14px] py-2 border-b border-[#f1f5f9] last:border-b-0 hover:bg-[#f8fafc]">
        <span class="text-[13px] font-bold text-[#1e293b] flex items-center gap-1.5">${slot}${isNew?`<span class="text-[9px] font-bold text-[#6d28d9] bg-[#ede9fe] px-[5px] py-px rounded-[4px] uppercase tracking-[0.3px]">New</span>`:''}</span>
        <div class="flex items-center gap-2 flex-shrink-0">
          <select class="${statusRowSelectCls(st)}" onchange="onPerSlotStatusChange('${safe}',this.value)">
            <option value=""${st===''?' selected':''}>Select Status</option>
            <option value="Open"${st==='Open'?' selected':''}>Open</option>
            <option value="Closed"${st==='Closed'?' selected':''}>Closed</option>
            <option value="Only by Phone"${st==='Only by Phone'?' selected':''}>Only by Phone</option>
          </select>
          <select class="${visRowSelectCls(vis)}" onchange="onPerSlotVisibleChange('${safe}',this.value)">
            <option value=""${vis == null ? ' selected' : ''}>Select Visibility</option>
            <option value="true"${vis === true ? ' selected' : ''}>Show</option>
            <option value="false"${vis === false ? ' selected' : ''}>Hide</option>
          </select>
        </div>
      </div>`;
    }).join('');
  }

  // Per-slot online visibility select styling (green = show, red = hide, grey = "Select Visibility").
  function visRowSelectCls(vis) {
    const base = "border-[1.5px] rounded-[7px] px-2.5 py-[5px] text-xs font-bold font-[inherit] bg-white outline-none cursor-pointer transition-all duration-150 focus:border-[#16a34a] focus:shadow-[0_0_0_3px_rgba(22,163,74,0.12)]";
    if (vis === true)  return base + " bg-[#dcfce7] border-[#86efac] text-[#16a34a]";
    if (vis === false) return base + " bg-[#fee2e2] border-[#fca5a5] text-[#dc2626]";
    return base + " bg-white border-[#e2e8f0] text-[#475569]"; // "Select Visibility" (unset)
  }

  // Status badge for the preview "Change To" column (.status-badge + .badge-*)
  function ocBadge(status) {
    const s = status || statusValue;
    if (s === 'Open')   return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#dcfce7] text-[#16a34a]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Open</span>`;
    if (s === 'Closed') return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#fee2e2] text-[#dc2626]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Closed</span>`;
    if (s === 'Only by Phone') return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#e0e7ff] text-[#4338ca]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Only by Phone</span>`;
    return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#94a3b8]">Select status</span>`;
  }

  // ── Visibility (Show/Hide) ──
  let visibilityValue = true;
  function onVisibilitySelectChange(v) {
    visibilityValue = (v === 'true');
    const sel = document.getElementById('visModeSelect');
    if (sel) sel.dataset.val = visibilityValue ? 'green' : 'red';
    updatePreview();
  }
  function visBadge() {
    return visibilityValue
      ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#e1f5ee] text-[#085041]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Visible</span>`
      : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f3f0ff] text-[#4c1d95]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Hidden</span>`;
  }

/* ===== CHUNK 4 ===== */
  // preview thead <th> shared classes (matches the static markup)
  const PREV_TH_CLS = "px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0]";
  function setPreviewCount(text, has) {
    const el = document.getElementById('previewCount');
    el.textContent = text;
    el.dataset.has = has ? 'true' : 'false';
  }
  // .empty-preview wrapper used inside the preview tbody empty state
  const EMPTY_PREV = inner => `<div class="py-7 px-4 text-center text-[#94a3b8] [&_svg]:mx-auto [&_svg]:mb-2 [&_svg]:block [&_svg]:opacity-35 [&_p]:text-xs [&_p]:font-semibold [&_small]:text-[11px]">${inner}</div>`;

  // ── Preview ──
  function updatePreview() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    const pkg  = document.getElementById('tourPackage').value;

    let dates = [];
    if (from && to && from <= to) dates = getDates(from, to);

    const tbody = document.getElementById('previewBody');

    // ── Repeat ──
    if (selectedAction === 'repeat') {
      document.getElementById('addToQueueBtn').disabled = true;
      setPreviewCount('0 slots', false);
      tbody.innerHTML = `<tr><td colspan="5">${EMPTY_PREV(`
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
        <p>Repeat slots use their own panel above</p><small>Fill the Repeat panel and click “Add to Queue” there</small>`)}</td></tr>`;
      document.getElementById('formFooterInfo').innerHTML = 'Repeat mode — use the panel above to add to the queue';
      repeatUpdatePreview();
      return;
    }

    // ── Delete ──
    if (selectedAction === 'delete') {
      document.getElementById('addToQueueBtn').disabled = true;
      setPreviewCount('0 slots', false);
      tbody.innerHTML = `<tr><td colspan="5">${EMPTY_PREV(`
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        <p>Delete mode uses its own panel above</p><small>Pick a mode, choose dates, and delete slots there</small>`)}</td></tr>`;
      document.getElementById('formFooterInfo').innerHTML = 'Delete mode — manage deletions in the panel above';
      delRefresh();
      return;
    }

    // ── Convert preview ──
    if (selectedAction === 'convert') {
      document.getElementById('previewThead').innerHTML =
        `<tr><th class="${PREV_TH_CLS}">Date</th><th class="${PREV_TH_CLS}">Day</th><th class="${PREV_TH_CLS}">From</th><th class="${PREV_TH_CLS}">Guests → Seats</th><th class="${PREV_TH_CLS}">Capacity</th><th class="${PREV_TH_CLS}">Status</th><th class="${PREV_TH_CLS}">Online</th></tr>`;

      const groups = {};
      Array.from(selectedSlots).forEach(s => {
        const nt = (convertTimes[s] || '').trim();
        if (nt) { if (!groups[nt]) groups[nt] = []; groups[nt].push(s); }
      });
      const mappedCount = Array.from(selectedSlots).filter(s => (convertTimes[s]||'').trim()).length;
      const canAdd = pkg && dates.length > 0 && mappedCount > 0;
      setPreviewCount(`${mappedCount} remapped`, mappedCount>0);
      document.getElementById('addToQueueBtn').disabled = !canAdd;

      if (!canAdd) {
        tbody.innerHTML = `<tr><td colspan="7">${EMPTY_PREV(`
          <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          <p>Nothing to preview yet</p><small>Select slots in Step 2, enter new times, set tour &amp; dates</small>`)}</td></tr>`;
        document.getElementById('formFooterInfo').innerHTML = 'Select slots, enter new times, then choose tour &amp; date range';
        return;
      }

      let rows = '', grandGuests = 0, totalOverbookedDates = 0;

      Object.entries(groups).forEach(([newTime, oldSlots], gi) => {
        const isMerge = oldSlots.length > 1;
        const leaderSlot = oldSlots[0];
        const userSeats = convertSeats[leaderSlot] != null ? convertSeats[leaderSlot] : null;
        // Chosen bookable status + online visibility for this conversion (shown per row).
        const setStatus = convertStatus[leaderSlot] || 'Open';
        const setStatusPill = qlStatusPill(setStatus);
        const setOnline = convertOnline[leaderSlot] !== false; // default to show online
        const onlinePill = setOnline
          ? `<span style="display:inline-block;font-size:11px;font-weight:700;background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:20px;">Show</span>`
          : `<span style="display:inline-block;font-size:11px;font-weight:700;background:#fee2e2;color:#dc2626;padding:2px 8px;border-radius:20px;">Hide</span>`;

        let grpTotalGuests = 0, grpOverbookedDates = 0;
        const dateRows = dates.map((date, di) => {
          const day = DAYS[parseLocal(date).getDay()];
          const gdi = `${gi}-${di}`;

          const slotData = oldSlots.map(s => {
            const info = getSlotData(date, s); // real booked tourists from tour_bookings_master
            return { slot: s, guests: (info && info.exists) ? (info.booked || 0) : 0 };
          });
          const combinedGuests = slotData.reduce((a, b) => a + b.guests, 0);
          grpTotalGuests += combinedGuests;
          grandGuests    += combinedGuests;

          const seatCap = userSeats != null ? userSeats : getSlotData(date, newTime).seats;
          const isOverbooked = combinedGuests > 0 && combinedGuests > seatCap;
          if (isOverbooked) { grpOverbookedDates++; totalOverbookedDates++; }

          const hasBookings = combinedGuests > 0;

          const fromCell = slotData.map(sd =>
            `<span style="text-decoration:line-through;color:#94a3b8;font-weight:700;">${sd.slot}</span>`
            + (sd.guests > 0 ? ` <span style="color:#64748b;font-size:10px;">${sd.guests}g</span>` : '')
          ).join(' <span style="color:#cbd5e1;">+</span> ');

          const countCell = `<div class="flex items-center gap-1.5 text-xs font-bold">
            <span class="text-[#dc2626]">${combinedGuests} guest${combinedGuests!==1?'s':''}</span>
            <span class="text-[#cbd5e1] text-xs">→</span>
            <span class="text-[#0891b2]">${seatCap} seats</span>
          </div>`;

          const over = combinedGuests - seatCap;
          const statusCell = !hasBookings
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#94a3b8]">no bookings</span>`
            : isOverbooked
              ? `<span class="bg-[#fee2e2] text-[#dc2626] font-bold px-2 py-[3px] rounded-[5px] text-[11px] inline-flex items-center gap-1 whitespace-nowrap">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                  +${over} over
                </span>`
              : `<span class="bg-[#dcfce7] text-[#16a34a] font-bold px-2 py-[3px] rounded-[5px] text-[11px] inline-flex items-center gap-1 whitespace-nowrap">
                  <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                  ${seatCap - combinedGuests} open
                </span>`;

          const detailInner = slotData.map(sd =>
            `<div class="flex flex-wrap items-baseline gap-1 text-[11px]">
              <span class="font-bold text-[#94a3b8] line-through min-w-[64px]">${sd.slot}</span>
              ${sd.guests > 0
                ? `<span class="text-[#64748b] text-[10px] font-bold">${sd.guests} guest${sd.guests!==1?'s':''} booked</span>`
                : `<span style="color:#94a3b8;font-size:11px;font-style:italic;">no bookings</span>`}
            </div>`
          ).join('');

          // row bg + hover via Tailwind; clickable rows get cursor + hover
          const dayRowBase = isOverbooked
            ? "[&>td]:bg-[#fff1f2]"
            : "[&>td]:bg-white [&:hover>td]:bg-[#f0fdfe]";
          const dayRowClickable = hasBookings ? " cursor-pointer" + (isOverbooked ? " [&:hover>td]:bg-[#ffe4e6]" : "") : "";
          const dayRowClass = dayRowBase + dayRowClickable;
          const dayChevron  = hasBookings
            ? `<svg class="cv-day-chevron inline-flex items-center transition-transform duration-200 text-[#94a3b8] mr-1 flex-shrink-0" style="transform:rotate(-90deg);" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>`
            : '';
          const clickAttr = hasBookings ? `onclick="toggleConvertDay('${gdi}')"` : '';

          return `<tr class="${dayRowClass} [&>td]:px-3 [&>td]:py-2 [&>td]:border-b [&>td]:border-[#f1f5f9]" data-cv-group="${gi}" data-cv-day="${gdi}" ${clickAttr}>
            <td style="font-weight:600;"><div style="display:flex;align-items:center;">${dayChevron}${fmtShort(date)}</div></td>
            <td style="color:#64748b;">${day}</td>
            <td>${fromCell}</td>
            <td>${countCell}</td>
            <td>${statusCell}</td>
            <td>${setStatusPill}</td>
            <td>${onlinePill}</td>
          </tr>
          <tr data-cv-detail="${gdi}" style="display:none;">
            <td colspan="7" class="!bg-[#f8fafc] !border-b !border-[#f1f5f9] !py-1.5 !pr-3 !pl-7"><div class="flex flex-col gap-1">${detailInner}</div></td>
          </tr>`;
        }).join('');

        const mergeTag  = isMerge ? `<span style="background:#ede9fe;color:#7c3aed;font-size:10px;font-weight:800;padding:2px 6px;border-radius:4px;margin-left:6px;">Merge</span>` : '';
        const warnTag   = grpOverbookedDates > 0
          ? `<span style="background:#fee2e2;color:#dc2626;font-size:10px;font-weight:800;padding:2px 7px;border-radius:4px;margin-left:6px;">
              <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" style="vertical-align:-1px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              Overbooked ${grpOverbookedDates} of ${dates.length} day${dates.length!==1?'s':''}
            </span>`
          : '';
        const totalGuests = grpTotalGuests;
        const grpHeadCls = grpOverbookedDates > 0
          ? "[&>td]:!bg-[#fff7ed] [&>td]:!text-[#9a3412] [&>td]:!border-b [&>td]:!border-[#fed7aa] [&>td]:cursor-pointer [&>td]:select-none [&:hover>td]:opacity-85"
          : "[&>td]:bg-[#f0fdfe] [&>td]:font-bold [&>td]:text-[#0e7490] [&>td]:text-[11px] [&>td]:py-1.5 [&>td]:px-3 [&>td]:border-b [&>td]:border-[#a5f3fc] [&>td]:cursor-pointer [&>td]:select-none [&:hover>td]:opacity-85";
        rows += `<tr class="${grpHeadCls}" data-cv-header="${gi}" onclick="toggleConvertGroup(${gi})">
          <td colspan="7">
            <div style="display:flex;align-items:center;gap:0;">
              <svg class="cv-chevron inline-flex items-center mr-1.5 transition-transform duration-200 opacity-70 flex-shrink-0" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="vertical-align:-2px;"><polyline points="6 9 12 15 18 9"/></svg>
              <strong>${oldSlots.join(' + ')}</strong>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="margin:0 4px;flex-shrink:0;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              <strong>${newTime}</strong>
              ${mergeTag}${warnTag}
              <span style="margin-left:auto;font-size:11px;color:inherit;opacity:.75;white-space:nowrap;">${totalGuests} guest${totalGuests!==1?'s':''} moving · old slot${oldSlots.length>1?'s':''} deleted</span>
            </div>
          </td>
        </tr>${dateRows}`;
      });

      const warnBanner = totalOverbookedDates > 0
        ? `<tr><td colspan="7" class="!py-2.5 !px-3 !bg-transparent">
            <div class="flex items-start gap-2 bg-[#fef2f2] border-[1.5px] border-[#fca5a5] rounded-lg px-[13px] py-[9px] text-[#991b1b] text-xs leading-[1.4] [&>svg]:flex-shrink-0 [&>svg]:mt-px">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
              <span><strong>Overbooking warning</strong> — ${totalOverbookedDates} date${totalOverbookedDates!==1?'s':''} will exceed the new slot's seat capacity. Increase the seat count in the convert editor or contact affected guests before applying.</span>
            </div>
          </td></tr>`
        : '';

      tbody.innerHTML = warnBanner + rows;
      document.getElementById('formFooterInfo').innerHTML =
        `<strong>${mappedCount} slot${mappedCount!==1?'s':''} remapped</strong> · <strong>${grandGuests} guest${grandGuests!==1?'s':''}</strong> moving across <strong>${dates.length}</strong> day${dates.length!==1?'s':''}` +
        (totalOverbookedDates > 0 ? ` · <span style="color:#dc2626;font-weight:700;">Overbooking on ${totalOverbookedDates} date${totalOverbookedDates!==1?'s':''}</span>` : '');
      return;
    }

    // ── Standard preview (open / close / phone / visibility / seats) ──
    const oc = (selectedAction === 'status');
    const isVis = (selectedAction === 'visibility');
    const isSeats = (selectedAction === 'seats');
    if (oc) renderStatusEditor();
    // Update Status, Update Seats & Visibility show explicit Seats + Booked Seats columns.
    const showSeatCols = oc || isVis || isSeats;
    // Status & Visibility adds "Current Online" + "Online" (chosen show/hide) columns → 9 columns.
    const colCount = oc ? 9 : (showSeatCols ? 7 : 5);
    document.getElementById('previewThead').innerHTML = oc
      ? `<tr><th class="${PREV_TH_CLS}">Date</th><th class="${PREV_TH_CLS}">Day</th><th class="${PREV_TH_CLS}">Time</th><th class="${PREV_TH_CLS}">Seats</th><th class="${PREV_TH_CLS}">Booked Seats</th><th class="${PREV_TH_CLS}">Current</th><th class="${PREV_TH_CLS}">Change To</th><th class="${PREV_TH_CLS}">Current Online</th><th class="${PREV_TH_CLS}">Online</th></tr>`
      : showSeatCols
        ? `<tr><th class="${PREV_TH_CLS}">Date</th><th class="${PREV_TH_CLS}">Day</th><th class="${PREV_TH_CLS}">Time</th><th class="${PREV_TH_CLS}">Seats</th><th class="${PREV_TH_CLS}">Booked Seats</th><th class="${PREV_TH_CLS}">Current</th><th class="${PREV_TH_CLS}">Change To</th></tr>`
        : `<tr><th class="${PREV_TH_CLS}">Date</th><th class="${PREV_TH_CLS}">Day</th><th class="${PREV_TH_CLS}">Time</th><th class="${PREV_TH_CLS}">Current</th><th class="${PREV_TH_CLS}">Change To</th></tr>`;
    // Once real slots are loaded, only count date+slot combinations that actually exist.
    let total = 0;
    dates.forEach(date => {
      getSlotsForDate(date).forEach(slot => {
        if (!slotsLoaded || getSlotData(date, slot).exists) total++;
      });
    });

    // Spell out exactly what's still required so a disabled "Add to Queue" is never a mystery.
    const missing = [];
    if (!pkg)                     missing.push('a tour package');
    if (dates.length === 0)       missing.push('a valid date range');
    if (selectedSlots.size === 0) missing.push('at least one time slot');
    if (!selectedAction)          missing.push('a bulk action');
    const canAdd = missing.length === 0;
    setPreviewCount(`${total} slot${total!==1?'s':''}`, total>0);
    document.getElementById('addToQueueBtn').disabled = !canAdd;

    if (!canAdd || total === 0) {
      const footer = !canAdd
        ? `Select ${missing.join(', ')} to add to queue`
        : 'No existing slots fall on the selected dates';
      tbody.innerHTML = `<tr><td colspan="${colCount}">${EMPTY_PREV(`
        <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <p>Nothing to preview yet</p><small>${!canAdd ? 'Complete steps 1–3' : 'Try a different date range'}</small>`)}</td></tr>`;
      document.getElementById('formFooterInfo').innerHTML = footer;
      return;
    }

    // standard body cell classes (matches .preview-table td)
    const PT_TD = "px-3 py-2 border-b border-[#f1f5f9] text-[#334155] font-medium";
    let rows = '';
    dates.forEach(date => {
      const day = DAYS[parseLocal(date).getDay()];
      const cp  = getChangePointForDate(date);
      const slots = Array.from(getSlotsForDate(date));

      if (cp && cp.slots.size > 0) {
        const newSlotTags = Array.from(cp.slots).map(s =>
          `<span class="bg-[#7c3aed] text-white text-[10px] font-bold px-[7px] py-0.5 rounded-[4px]">${s}</span>`).join('');
        const cpEndStr = cp.dateTo ? ` → ${fmtShort(cp.dateTo)}` : '';
        rows += `<tr>
          <td colspan="${colCount}" class="bg-gradient-to-r from-[#f3e8ff] to-[#faf5ff] border-t-[1.5px] border-t-[#d8b4fe] border-b-[1.5px] border-b-[#d8b4fe] py-[7px] px-3">
            <div class="flex items-center gap-2 text-[11px] font-bold text-[#6d28d9] [&>svg]:flex-shrink-0">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
              Schedule change ${fmtShort(date)}${cpEndStr} — new slot${cp.slots.size!==1?'s':''} active:
              <div class="flex flex-wrap gap-1 mt-1">${newSlotTags}</div>
            </div>
          </td>
        </tr>`;
      }

      slots.forEach(slot => {
        const cur = getSlotData(date, slot);
        // Only preview the slot on dates where it actually exists (matched by from-time).
        if (slotsLoaded && !cur.exists) return;
        const isNewOnThisDate = cp && cp.slots.has(slot);
        let changeBadge;
        if (oc) {
          const stv = slotStatus(slot);
          changeBadge = stv
            ? ocBadge(stv)
            : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#94a3b8]">No change</span>`;
        } else if (isVis) {
          changeBadge = visBadge();
        } else {
          const sd = perSlotSeats[slot];
          const nv = sd && sd.newCount !== null ? sd.newCount : null;
          changeBadge = nv !== null
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#fef3c7] text-[#d97706]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>${cur.seats} → ${nv} seats</span>`
            : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#94a3b8]">no change</span>`;
        }
        const newIndicator = isNewOnThisDate ? `<span class="inline-flex items-center gap-[3px] text-[9px] font-bold text-[#7c3aed] bg-[#ede9fe] px-[5px] py-px rounded-[4px] ml-1">New</span>` : '';
        const rowCls = isNewOnThisDate ? '[&>td]:!bg-[#fdf8ff] [&:hover>td]:!bg-[#f3e8ff]' : '[&:hover>td]:bg-[#f8fafc]';
        const bookedPill = `<span class="font-bold ${cur.available <= 0 && cur.seats > 0 ? 'text-[#dc2626]' : 'text-[#334155]'}">${cur.booked}</span>`;
        // Update Status, Update Seats & Visibility: explicit Seats + Booked Seats columns, plus a Current pill.
        let midCells;
        if (oc || isSeats) {
          midCells = `<td class="${PT_TD}" style="font-weight:600;">${slot}${newIndicator}</td>
             <td class="${PT_TD}">${cur.seats}</td>
             <td class="${PT_TD}">${bookedPill}</td>
             <td class="${PT_TD}">${ocBadge(normalizeStatus(cur.status))}</td>`;
        } else if (isVis) {
          const visPill = cur.hide
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#64748b]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Hidden</span>`
            : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#dcfce7] text-[#16a34a]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Visible</span>`;
          midCells = `<td class="${PT_TD}" style="font-weight:600;">${slot}${newIndicator}</td>
             <td class="${PT_TD}">${cur.seats}</td>
             <td class="${PT_TD}">${bookedPill}</td>
             <td class="${PT_TD}">${visPill}</td>`;
        } else {
          midCells = `<td class="${PT_TD}" style="font-weight:600;">${slot}${newIndicator}</td>
             <td class="${PT_TD}"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#64748b]">${cur.status} / ${cur.seats}</span></td>`;
        }
        // Status & Visibility: separate "Current Online" (current) + "Online" (chosen) columns.
        let onlineCell = '';
        if (oc) {
          const curVisPill = cur.hide
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#64748b]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Hidden</span>`
            : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#64748b]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Visible</span>`;
          const chosenVis = perSlotVisible[slot];
          const newVisPill = chosenVis === true
            ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#dcfce7] text-[#16a34a]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Show</span>`
            : chosenVis === false
              ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#fee2e2] text-[#dc2626]"><span class="w-[5px] h-[5px] rounded-full bg-current"></span>Hide</span>`
              : `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[20px] text-[11px] font-bold bg-[#f1f5f9] text-[#94a3b8]">No change</span>`;
          onlineCell = `<td class="${PT_TD}">${curVisPill}</td><td class="${PT_TD}">${newVisPill}</td>`;
        }
        rows += `<tr class="${rowCls}">
          <td class="${PT_TD}" style="font-weight:600;">${fmtShort(date)}</td>
          <td class="${PT_TD}" style="color:#64748b;">${day}</td>
          ${midCells}
          <td class="${PT_TD}">${changeBadge}</td>
          ${onlineCell}
        </tr>`;
      });
    });
    tbody.innerHTML = rows;

    const cpCount = changePoints.reduce((s,cp)=>s+cp.slots.size,0);
    const cpNote = cpCount > 0 ? ` · <strong>${changePoints.length} schedule change${changePoints.length!==1?'s':''}</strong>` : '';
    document.getElementById('formFooterInfo').innerHTML =
      `<strong>${total} slot${total!==1?'s':''}</strong> across <strong>${dates.length}</strong> day${dates.length!==1?'s':''}${cpNote}`;
  }

  // ── Pre-add validation ──
  // Find slots in the current selection that are already part of another queued operation
  // for the same package over an overlapping date range. Returns human-readable lines.
  function findQueueOverlap() {
    const pkg  = document.getElementById('tourPackage').value;
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    if (!pkg || !from || !to || from > to) return [];

    const curSlots = new Set(selectedSlots);
    changePoints.forEach(cp => cp.slots.forEach(s => curSlots.add(s)));
    if (curSlots.size === 0) return [];
    const curDates = new Set(getDates(from, to));

    const bySlot = {}; // slot -> set of queue operation numbers it already appears in
    queue.forEach((q, i) => {
      if (editingQueueId != null && q.id === editingQueueId) return; // don't flag the op being edited against itself
      if (String(q.pkg) !== String(pkg)) return;
      const qDates = (q.from && q.to) ? getDates(q.from, q.to) : [];
      if (!qDates.some(d => curDates.has(d))) return; // no overlapping date
      const qSlots = q.action === 'convert' ? Object.keys(q.convertTimes || {}) : (q.baseSlots || []);
      qSlots.forEach(slot => {
        if (curSlots.has(slot)) (bySlot[slot] = bySlot[slot] || new Set()).add(i + 1);
      });
    });

    return Object.entries(bySlot).map(([slot, ops]) =>
      `${slot} — already in operation #${[...ops].join(', #')}`
    );
  }

  function preAddCheck() {
    const issues = [];

    const emptyCps = changePoints.filter(cp => cp.slots.size === 0);
    if (emptyCps.length > 0) {
      const labels = emptyCps.map(cp => fmtShort(cp.date)).join(', ');
      issues.push(`${emptyCps.length} schedule change${emptyCps.length > 1 ? 's' : ''} ha${emptyCps.length > 1 ? 've' : 's'} no slots selected (${labels})`);
    }

    // Update Status requires a real status — block (with an alert) if any slot is left on "Select Status".
    if (selectedAction === 'status') {
      const affected = statusAffectedSlots();
      // Status & Visibility: each slot must have a status OR a visibility set (either alone is allowed).
      const unset = affected.filter(s => !slotStatus(s) && perSlotVisible[s] == null);
      if (unset.length > 0) {
        const msg = unset.length === affected.length
          ? 'Please set a status or visibility before adding to the queue.'
          : `Please set a status or visibility for: ${unset.join(', ')}`;
        showAlertPopup(msg, 'Nothing to update');
        return;
      }
    }

    // Update Seats requires a seat count for every selected slot — hard block (no "add anyway").
    if (selectedAction === 'seats') {
      const allSlots = [...Array.from(selectedSlots), ...changePoints.flatMap(cp => Array.from(cp.slots))];
      const unset = allSlots.filter(s => !perSlotSeats[s] || perSlotSeats[s].newCount === null || perSlotSeats[s].newCount === undefined);
      if (unset.length > 0) {
        const msg = unset.length === allSlots.length
          ? 'Please enter the number of seats for all selected slots before adding to the queue.'
          : `Please enter the number of seats for: ${unset.join(', ')}`;
        showAlertPopup(msg, 'Seats required');
        return;
      }
    }

    if (selectedAction === 'convert') {
      const sameTime = Array.from(selectedSlots).filter(s => (convertTimes[s] || '').trim() === s);
      if (sameTime.length > 0) {
        showAlertPopup(`A slot cannot be converted to its own time. Please choose a different new time for: ${sameTime.join(', ')}`, 'Invalid conversion');
        return;
      }
      const byNewTime = {};
      Array.from(selectedSlots).forEach(s => {
        const nt = (convertTimes[s] || '').trim();
        if (nt) { (byNewTime[nt] = byNewTime[nt] || []).push(s); }
      });
      const merged = Object.entries(byNewTime).filter(([, arr]) => arr.length > 1);
      if (merged.length > 0) {
        const detail = merged.map(([nt, arr]) => `${arr.join(' + ')} → ${nt}`).join('; ');
        showAlertPopup(`Merging slots is not allowed — each new time must be unique. Conflicts: ${detail}`, 'Merging not allowed');
        return;
      }
      const cvPkg  = document.getElementById('tourPackage').value;
      const cvFrom = document.getElementById('fromDate').value;
      const cvTo   = document.getElementById('toDate').value;
      const cvDates = (cvFrom && cvTo && cvFrom <= cvTo) ? getDates(cvFrom, cvTo) : [];
      const mapping = {};
      Array.from(selectedSlots).forEach(s => { const nt = (convertTimes[s] || '').trim(); if (nt) mapping[s] = nt; });
      const touched = new Set();
      cvDates.forEach(d => Object.entries(mapping).forEach(([o, n]) => { touched.add(`${d}|${o}`); touched.add(`${d}|${n}`); }));
      const cvConflicts = new Set();
      queue.filter(it => it.action === 'convert' && it.pkg === cvPkg && it.id !== editingQueueId).forEach(it => {
        getDates(it.from, it.to).forEach(d => Object.entries(it.convertTimes || {}).forEach(([o, n]) => {
          if (touched.has(`${d}|${o}`)) cvConflicts.add(`${fmtShort(d)} ${o}`);
          if (touched.has(`${d}|${n}`)) cvConflicts.add(`${fmtShort(d)} ${n}`);
        }));
      });
      if (cvConflicts.size > 0) {
        const list = [...cvConflicts].slice(0, 12).join(', ') + (cvConflicts.size > 12 ? `, +${cvConflicts.size - 12} more` : '');
        showAlertPopup(`These date/time slots overlap another queued Convert Times operation for this package: ${list}`, 'Overlapping conversion');
        return;
      }
      // Block converting into a time that already has a slot — it would update that existing slot.
      // (A target that is itself one of the slots being converted away is a shift, so it's allowed.)
      if (slotsLoaded) {
        const mappedOld = new Set(Object.keys(mapping));
        const targetExists = new Set();
        cvDates.forEach(d => Object.entries(mapping).forEach(([o, n]) => {
          if (slotIndex[`${d}|${o}`] && slotIndex[`${d}|${n}`] && !mappedOld.has(n)) {
            targetExists.add(`${fmtShort(d)} ${n}`);
          }
        }));
        if (targetExists.size > 0) {
          const list = [...targetExists].slice(0, 12).join(', ') + (targetExists.size > 12 ? `, +${targetExists.size - 12} more` : '');
          showAlertPopup(`Cannot convert into a time that already has a slot — it would update the existing slot: ${list}`, 'Slot already exists');
          return;
        }
      }
      const unmapped = Array.from(selectedSlots).filter(s => !(convertTimes[s] || '').trim());
      if (unmapped.length > 0) {
        issues.push(`${unmapped.length} slot${unmapped.length > 1 ? 's' : ''} ha${unmapped.length > 1 ? 've' : 's'} no new time set (${unmapped.join(', ')})`);
      }
    }

    // Warn (and ask to confirm) if any date+slot is already part of another queued op for this package.
    let hasOverlap = false;
    if (selectedAction !== 'convert') {
      const overlaps = findQueueOverlap();
      if (overlaps.length) { hasOverlap = true; issues.push(...overlaps); }
    }

    if (issues.length === 0) { addToQueue(); return; }

    const warnTitleEl = document.getElementById('warnTitle');
    const warnDescEl  = document.getElementById('warnDesc');
    if (warnTitleEl) warnTitleEl.textContent = hasOverlap ? 'Slots already in the queue' : 'Incomplete Configuration';
    if (warnDescEl)  warnDescEl.innerHTML = hasOverlap
      ? 'The following slots are already added in another queued operation for this tour and date range. Do you want to add them to this operation as well?'
      : 'The following items haven\'t been fully set up. Do you want to add to queue anyway?';
    document.getElementById('warnList').innerHTML = issues.map(i => `<li>${i}</li>`).join('');
    document.getElementById('warnOverlay').dataset.open = 'true';
  }

  function closeWarnModal() { document.getElementById('warnOverlay').dataset.open = 'false'; }
  function closeWarnOutside(e) { if (e.target === document.getElementById('warnOverlay')) closeWarnModal(); }
  function proceedAddToQueue() { closeWarnModal(); addToQueue(); }

/* ===== CHUNK 5 ===== */
  // ── Shared Tailwind class strings for queue cards ──
  const QI_CARD    = "bg-white border-[1.5px] border-[#e2e8f0] rounded-[10px] overflow-hidden animate-[slideIn_0.2s_ease] hover:border-[#cbd5e1]";
  const QI_HEADER  = "flex items-center justify-between px-[14px] py-2.5 bg-[#f8fafc] border-b border-[#f1f5f9]";
  const QI_NUM     = "flex items-center gap-2";
  const QI_BADGE   = "w-[22px] h-[22px] rounded-full bg-[#1e3a5c] text-white text-[11px] font-bold flex items-center justify-center";
  const QI_TITLE   = "text-xs font-bold text-[#334155]";
  const QI_PILL    = "inline-flex items-center gap-1 px-2 py-[3px] rounded-[20px] text-[11px] font-bold";
  // Highlight applied to a queue card while its operation is being edited.
  const QI_EDITING = "!border-[#1e3a5c] shadow-[0_0_0_2px_rgba(30,58,92,0.25)]";
  const QI_EDIT_BADGE = '<span class="ml-1.5 inline-flex items-center px-1.5 py-px rounded-[4px] text-[9px] font-bold uppercase tracking-[0.3px] bg-[#e0f2fe] text-[#0369a1]">Editing</span>';
  const QI_BODY    = "px-[14px] py-2.5";
  const QI_DETAIL  = "flex items-baseline gap-1.5 text-xs font-medium text-[#64748b] mb-1 [&_strong]:text-[#334155] [&_strong]:font-bold [&_strong]:text-xs";
  const QI_SLOTSW  = "flex flex-wrap gap-1 mt-1.5";
  const QI_AFFECT  = "mt-2 px-2.5 py-1.5 bg-[#f1f5f9] rounded-md text-[11px] font-semibold text-[#475569] flex items-center justify-between [&_strong]:text-[#1e293b] [&_strong]:text-[13px]";
  const QI_FOOTER  = "flex items-center justify-end gap-1.5 px-[14px] py-2 border-t border-[#f1f5f9]";
  const QI_ICONEDIT= "w-7 h-7 rounded-md flex items-center justify-center cursor-pointer border-[1.5px] border-[#e2e8f0] bg-white transition-all duration-150 text-[#64748b] hover:bg-[#eff6ff] hover:border-[#93c5fd] hover:text-[#2563eb]";
  const QI_ICONDEL = "w-7 h-7 rounded-md flex items-center justify-center cursor-pointer border-[1.5px] border-[#e2e8f0] bg-white transition-all duration-150 text-[#64748b] hover:bg-[#fee2e2] hover:border-[#fca5a5] hover:text-[#dc2626]";
  const SLOT_MINI  = "bg-[#e0f2fe] text-[#0369a1] text-[10px] font-bold px-1.5 py-0.5 rounded-[4px]";
  const SLOT_MINI_NEW = "bg-[#ede9fe] text-[#6d28d9] text-[10px] font-bold px-1.5 py-0.5 rounded-[4px]";
  const QSEAT_GRID = "flex flex-col gap-[3px] mt-2";
  const QSEAT_ROW  = "flex items-center justify-between text-[11px] font-medium text-[#64748b] py-[3px] border-b border-[#f1f5f9] last:border-b-0 [&_.slot-name]:font-bold [&_.slot-name]:text-[#334155]";
  const QL_MORE    = "bg-[#e0f2fe] text-[#0369a1] border-0 rounded-[4px] text-[10px] font-bold px-2 py-0.5 cursor-pointer self-center hover:bg-[#bae6fd]";
  const QL_VIEWALL = "inline-flex items-center gap-[5px] mt-1.5 bg-[#eff6ff] text-[#1e3a5c] border border-[#bfdbfe] rounded-md text-[11px] font-bold px-2.5 py-[5px] cursor-pointer [&>svg]:flex-shrink-0 hover:bg-[#dbeafe]";
  const PILL_REPEAT= "bg-[#fef3c7] text-[#d97706]";

  function qiPillCls(action) {
    if (action === 'convert')    return "bg-[#cffafe] text-[#0e7490]";
    if (action === 'status')     return "bg-[#dcfce7] text-[#16a34a]";
    if (action === 'visibility') return "bg-[#f3f0ff] text-[#4c1d95]";
    return "bg-[#fef3c7] text-[#d97706]";   // seats
  }

  // ── Queue ──
  function addToQueue() {
    const from    = document.getElementById('fromDate').value;
    const to      = document.getElementById('toDate').value;
    const pkg     = document.getElementById('tourPackage').value;
    const pkgEl   = document.getElementById('tourPackage');
    const pkgName = pkgEl.options[pkgEl.selectedIndex].text;
    const dates   = getDates(from, to);

    queueCounter++;

    let item;
    if (selectedAction === 'convert') {
      const ctSnapshot = {};
      Array.from(selectedSlots).forEach(s => {
        const nt = (convertTimes[s] || '').trim();
        if (nt) ctSnapshot[s] = nt;
      });
      let totalBkgs = 0; // total guests booked across the converted slots (from tour_bookings_master)
      dates.forEach(date => {
        Object.keys(ctSnapshot).forEach(s => {
          const info = getSlotData(date, s);
          if (info && info.exists) totalBkgs += (info.booked || 0);
        });
      });
      const csSnapshot  = {}, stSnapshot = {}, onSnapshot = {};
      Object.keys(ctSnapshot).forEach(s => {
        if (convertSeats[s]  != null) csSnapshot[s] = convertSeats[s];
        if (convertStatus[s] != null) stSnapshot[s] = convertStatus[s];
        if (convertOnline[s] != null) onSnapshot[s] = convertOnline[s];
      });

      item = {
        id: queueCounter, pkg, pkgName, from, to,
        baseSlots: [], changePoints: [],
        action: 'convert',
        convertTimes:  ctSnapshot,
        convertSeats:  csSnapshot,
        convertStatus: stSnapshot,
        convertOnline: onSnapshot,
        seatSnapshot: null,
        total: totalBkgs
      };
      item.affectedSlots = captureAffectedSlots(from, to, Object.keys(ctSnapshot));
      item.record = buildConvertOperationRecord(item);
    } else {
      let total = 0;
      dates.forEach(date => total += getSlotsForDate(date).size);

      const seatSnapshot = selectedAction === 'seats' ? JSON.parse(JSON.stringify(perSlotSeats)) : null;
      const cpSnapshot = changePoints.filter(cp=>cp.slots.size>0).map(cp => ({
        date: cp.date,
        dateTo: cp.dateTo || to,
        slots: Array.from(cp.slots)
      }));

      item = {
        id: queueCounter, pkg, pkgName, from, to,
        baseSlots: Array.from(selectedSlots),
        changePoints: cpSnapshot,
        action: selectedAction,
        seatSnapshot,
        online: selectedOnline,
        visible: selectedAction === 'visibility' ? visibilityValue : null,
        statusValue: selectedAction === 'status' ? statusValue : null,
        statusSnapshot: selectedAction === 'status'
          ? statusAffectedSlots().reduce((m, s) => { m[s] = slotStatus(s); return m; }, {})
          : null,
        // Combined "Status & Visibility": per-slot online show/hide snapshot (only slots the user set).
        visibleSnapshot: selectedAction === 'status'
          ? statusAffectedSlots().reduce((m, s) => { if (perSlotVisible[s] != null) m[s] = perSlotVisible[s]; return m; }, {})
          : null,
        total
      };
      // Snapshot the affected slots' current status/seats now (slotIndex is accurate here).
      item.affectedSlots = captureAffectedSlots(from, to, item.baseSlots);
      // Prepare the DB record (tour_slot_bulk_operations) so the op is ready to persist.
      if (selectedAction === 'status') item.record = buildStatusOperationRecord(item);
      else if (selectedAction === 'seats') item.record = buildSeatsOperationRecord(item);
      else if (selectedAction === 'visibility') item.record = buildVisibilityOperationRecord(item);
    }

    // Editing an existing queued op → replace it in place (keep its id + DB id) so persist updates it.
    if (editingQueueId != null) {
      const idx = queue.findIndex(x => x.id === editingQueueId);
      const existing = idx >= 0 ? queue[idx] : null;
      item.id = editingQueueId;
      if (existing && existing.dbId) item.dbId = existing.dbId;
      if (idx >= 0) queue[idx] = item; else queue.push(item);
      editingQueueId = null;
    } else {
      queue.push(item);
    }
    renderQueue();

    // Persist, then fully reset the form (package + dates + slots) once the save is confirmed.
    // On a server rejection the form is kept so the user can fix it (persistOperation shows the
    // message and rolls back the optimistic queue item).
    const xhr = item.record ? persistOperation(item) : null;
    const finalize = (res) => {
      if (res && res.success === false) return;
      resetEditorToDefault();
    };
    if (xhr && typeof xhr.done === 'function') xhr.done(finalize);
    else finalize({});
  }

  // Persist a queued operation to tour_slot_bulk_operations. CSRF is handled globally via
  // $.ajaxSetup (custom.js). On success the DB id is stored on the queue item.
  function persistOperation(item) {
    const cfg = window.BULK_MGR_CONFIG || {};
    if (!item || !item.record) return;
    // Existing op (has a DB id) → update via PUT; new op → insert via POST.
    const isUpdate = !!item.dbId;
    const url = isUpdate
      ? (cfg.updateOperationUrl ? cfg.updateOperationUrl.replace('__OP_ID__', item.dbId) : null)
      : cfg.storeOperationUrl;
    if (!url) return;
    if (typeof showLoading === 'function') showLoading(); // global overlay loader (custom.js)
    return $.ajax({
      url: url,
      method: isUpdate ? 'PUT' : 'POST',
      // Send the operation as a raw JSON body. processData:false + JSON content-type means the
      // payload is NOT form-encoded, so PHP's max_input_vars (1000) never truncates a large
      // slotTimes array — the whole payload is stored intact even for very large date ranges.
      contentType: 'application/json',
      processData: false,
      data: JSON.stringify(item.record)
    }).done(res => {
      // Server rejected the op (duplicate create slots, or missing package/dates/slots) → surface
      // the message and roll back a brand-new insert. An edited op keeps its existing row.
      if (res && res.success === false) {
        showAlertPopup(res.message || 'This operation could not be added to the queue.', res.title || 'Cannot add to queue');
        if (!isUpdate) {
          const idx = queue.findIndex(x => x.id === item.id);
          if (idx >= 0) { queue.splice(idx, 1); renderQueue(); }
        }
        return;
      }
      if (res && res.id) item.dbId = res.id;
    }).fail(() => {
      showAlertPopup(isUpdate
        ? 'The operation could not be updated on the server. Please try again.'
        : 'The operation was added to the queue but could not be saved to the server. Please try again.', 'Save failed');
    }).always(() => {
      if (typeof hideLoading === 'function') hideLoading();
    });
  }

  // ── Persistence: build a tour_slot_bulk_operations record for an Update Status op ──
  // Maps a queued status operation to the DB columns; `slots` and `payload` are stored as JSON.
  function buildStatusOperationRecord(q) {
    const perSlotStatus  = q.statusSnapshot || {};
    const perSlotVisible = q.visibleSnapshot || {};

    // Resolve every affected slot to its real tour_slot_times.id (per date in the range) so the
    // queued op references concrete slot rows — robust even if a slot's time is changed later.
    const slotTimes = [];
    getDates(q.from, q.to).forEach(date => {
      (q.baseSlots || []).forEach(slot => {
        const r = slotIndex[`${date}|${slot}`];
        if (r && r.id) {
          const entry = {
            id: r.id,                                             // tour_slot_times.id
            status: perSlotStatus[slot] || q.statusValue || null  // target status for this slot
          };
          // Only carry a visibility change when the user picked one (else leave the slot's hide_slot as-is).
          if (perSlotVisible[slot] === true)  entry.hide = 0; // show online
          if (perSlotVisible[slot] === false) entry.hide = 1; // hide
          slotTimes.push(entry);
        }
      });
    });

    return {
      tour_package_id: q.pkg ? parseInt(q.pkg, 10) : null,
      action: 'status',
      from_date: q.from,                       // YYYY-MM-DD
      to_date: q.to,                           // YYYY-MM-DD
      // slots column (JSON): the selected slot start-times this operation applies to
      slots: q.baseSlots || [],                // e.g. ["6:45 AM", "8:00 AM"]
      // payload column (JSON): everything needed to apply the status + visibility change
      payload: {
        statusValue: q.statusValue || null,    // global "set all" status (null if only per-slot picks)
        perSlotStatus: perSlotStatus,          // { "6:45 AM": "Closed", "8:00 AM": "Open" }
        perSlotVisible: perSlotVisible,        // { "6:45 AM": false, "8:00 AM": true } (true = show online)
        slotTimes: slotTimes,                  // each affected slot_id + target status + hide flag
        affectedSlots: q.affectedSlots || []   // per date+slot current status/seats (for the affected-slots view)
      },
      total_slots: slotTimes.length,           // actual number of slot rows the op will update
      status: 'queued'
    };
  }

  // ── Persistence: build a tour_slot_bulk_operations record for a Convert Times op ──
  // For each old slot → new time, per date, capture the source slot_time id and the target slot id
  // (if a slot already exists at the new time). targetId === null means no slot exists there, so the
  // apply step should UPDATE the existing (source) slot's time to the new time instead of moving.
  function buildConvertOperationRecord(q) {
    const conversions = q.convertTimes || {};
    const slotTimes = [];
    getDates(q.from, q.to).forEach(date => {
      Object.keys(conversions).forEach(oldSlot => {
        const nt = (conversions[oldSlot] || '').trim();
        if (!nt) return;
        const src = slotIndex[`${date}|${oldSlot}`];
        if (!src || !src.id) return;                 // no source slot on this date → nothing to convert
        const tgt = slotIndex[`${date}|${nt}`];
        slotTimes.push({
          id: src.id,                                // source slot_time id (carries the bookings)
          to: nt,                                    // new time
          targetId: (tgt && tgt.id) ? tgt.id : null, // existing slot at the new time, or null → update source's time
          seats:  (q.convertSeats  && q.convertSeats[oldSlot]  != null) ? q.convertSeats[oldSlot]  : null,
          status: (q.convertStatus && q.convertStatus[oldSlot] != null) ? q.convertStatus[oldSlot] : null,
          online: (q.convertOnline && q.convertOnline[oldSlot] != null) ? q.convertOnline[oldSlot] : null
        });
      });
    });

    return {
      tour_package_id: q.pkg ? parseInt(q.pkg, 10) : null,
      action: 'convert',
      from_date: q.from,
      to_date: q.to,
      slots: Object.keys(conversions),               // old slot times being converted
      payload: {
        conversions:   conversions,                  // { "8:00 AM": "8:15 AM" }
        convertSeats:  q.convertSeats  || {},
        convertStatus: q.convertStatus || {},
        convertOnline: q.convertOnline || {},
        slotTimes:     slotTimes,                    // per source slot: id, to, targetId (null = update existing), overrides
        affectedSlots: q.affectedSlots || []         // per date+slot snapshot (status/seats/booked) — for the count & affected view on reload
      },
      // Store the affected slot-row count (consistent with the column name and the other actions).
      // The bookings-moving figure is derived from payload.affectedSlots when displayed.
      total_slots: slotTimes.length,
      status: 'queued'
    };
  }

  // Total valid bookings moving for a convert op = sum of booked guests across its affected slots
  // (per date). Falls back to the stored total only if no per-slot booked data was captured.
  function convertBookingsMoving(q) {
    if (q.affectedSlots && q.affectedSlots.some(a => a.booked != null)) {
      return q.affectedSlots.reduce((s, a) => s + (a.booked || 0), 0);
    }
    return q.total || 0;
  }

  // Number of slot rows a convert op affects (one per date × converted slot that exists).
  function convertSlotsAffected(q) {
    if (q.affectedSlots && q.affectedSlots.length) return q.affectedSlots.length;
    if (q.record && q.record.payload && Array.isArray(q.record.payload.slotTimes)) return q.record.payload.slotTimes.length;
    if (Array.isArray(q.convertSlotTimes)) return q.convertSlotTimes.length;
    return 0;
  }

  // Slots affected by a queued op (for the "Total slots" header). For convert, q.total holds the
  // bookings count, so use the slot-row count instead.
  function opSlotsAffected(q) {
    return q.action === 'convert' ? convertSlotsAffected(q) : (q.total || 0);
  }

  // Snapshot each affected slot's real current status + seats (from slotIndex) at queue time, so
  // the "View Affected Slots" table stays accurate even after the form's date range changes / reload.
  function captureAffectedSlots(from, to, baseSlots) {
    const out = [];
    if (!from || !to || from > to) return out;
    getDates(from, to).forEach(date => {
      (baseSlots || []).forEach(slot => {
        const r = slotIndex[`${date}|${slot}`];
        if (r && r.id) out.push({ date: date, slot: slot, status: r.status, seats: r.seats, booked: (r.booked || 0) });
      });
    });
    return out;
  }

  // ── Persistence: build a tour_slot_bulk_operations record for an Update Seats op ──
  function buildSeatsOperationRecord(q) {
    const seatSnapshot = q.seatSnapshot || {};

    // Resolve every affected slot to its real tour_slot_times.id (per date in the range) with
    // the new seat count, so the queued op targets concrete slot rows.
    const slotTimes = [];
    getDates(q.from, q.to).forEach(date => {
      (q.baseSlots || []).forEach(slot => {
        const r  = slotIndex[`${date}|${slot}`];
        const sd = seatSnapshot[slot];
        if (r && r.id && sd && sd.newCount !== null && sd.newCount !== undefined) {
          slotTimes.push({ id: r.id, seats: sd.newCount });
        }
      });
    });

    return {
      tour_package_id: q.pkg ? parseInt(q.pkg, 10) : null,
      action: 'seats',
      from_date: q.from,                       // YYYY-MM-DD
      to_date: q.to,                           // YYYY-MM-DD
      slots: q.baseSlots || [],                // e.g. ["6:45 AM", "8:00 AM"]
      payload: {
        perSlotSeats: seatSnapshot,            // { "6:45 AM": { newCount, currentCount, isNew, fromDate } }
        slotTimes: slotTimes,                  // each affected slot_id + new seat count
        affectedSlots: q.affectedSlots || []   // per date+slot current status/seats (for the affected-slots view)
      },
      total_slots: slotTimes.length,           // actual number of slot rows the op will update
      status: 'queued'
    };
  }

  // ── Persistence: build a tour_slot_bulk_operations record for a Create Slots op ──
  // These slots don't exist yet, so there are no slot_ids — the payload carries the rows to create.
  function buildCreateOperationRecord(q) {
    const slots = (q.createRows || []).map(r => `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toUpperCase()}`);
    return {
      tour_package_id: q.pkg ? parseInt(q.pkg, 10) : null,
      action: 'create',
      from_date: q.from,                       // YYYY-MM-DD
      to_date: q.to,                           // YYYY-MM-DD
      slots: slots,                            // new slot start-times to create
      payload: {
        createRows:  q.createRows || [],       // [{ fromHour, fromMinute, fromPeriod, toHour, toMinute, toPeriod, seats, bookableStatus, hidden }]
        days:        q.days || [],             // day-of-week filter [1..7]
        slotsPerDay: q.slotsPerDay || 0,
        numDays:     q.numDays || 0
      },
      total_slots: q.total || 0,               // numDays × slotsPerDay = total slots to create
      status: 'queued'
    };
  }

  // ── Persistence: build a tour_slot_bulk_operations record for a Visibility op ──
  function buildVisibilityOperationRecord(q) {
    const slotTimes = [];
    getDates(q.from, q.to).forEach(date => {
      (q.baseSlots || []).forEach(slot => {
        const r = slotIndex[`${date}|${slot}`];
        if (r && r.id) slotTimes.push({ id: r.id });
      });
    });

    return {
      tour_package_id: q.pkg ? parseInt(q.pkg, 10) : null,
      action: 'visibility',
      from_date: q.from,                       // YYYY-MM-DD
      to_date: q.to,                           // YYYY-MM-DD
      slots: q.baseSlots || [],                // e.g. ["6:45 AM", "8:00 AM"]
      payload: {
        visible: q.visible !== false,          // true = show online, false = hide
        slotTimes: slotTimes,                  // affected slot_ids (apply: set hide_slot = visible ? 0 : 1)
        affectedSlots: q.affectedSlots || []   // per date+slot current status/seats (for the affected-slots view)
      },
      total_slots: slotTimes.length,
      status: 'queued'
    };
  }

  function removeFromQueue(id) {
    const item = queue.find(q => q.id === id);
    if (!item) return;

    const removeLocally = () => { queue = queue.filter(q => q.id !== id); renderQueue(); };
    const cfg = window.BULK_MGR_CONFIG || {};

    swal({
      title: 'Delete operation?',
      text: 'Are you sure you want to remove this operation from the queue?',
      icon: 'warning',
      buttons: { no: { text: 'No', value: false }, yes: { text: 'Yes', className: 'btn-danger' } }
    }).then(confirmed => {
      if (!confirmed) return;

      // Persisted op → soft-delete from the DB first, then drop it from the queue.
      if (item.dbId && cfg.deleteOperationUrl) {
        if (typeof showLoading === 'function') showLoading();
        $.ajax({
          url: cfg.deleteOperationUrl.replace('__OP_ID__', item.dbId),
          method: 'POST',
          data: { _method: 'DELETE' }
        }).done(res => {
          if (res && res.success === false) {
            showAlertPopup(res.message || 'Could not delete the operation.', 'Delete failed');
            return;
          }
          removeLocally();
        }).fail(() => {
          showAlertPopup('Could not delete the operation. Please try again.', 'Delete failed');
        }).always(() => { if (typeof hideLoading === 'function') hideLoading(); });
      } else {
        removeLocally();
      }
    });
  }

  // Edit a queued "create slots" op → restore its inputs into the Create Slots editor.
  function editCreateQueueItem(q) {
    if (typeof showLoading === 'function') showLoading(); // global overlay loader (custom.js)

    // Keep the op in the queue, flagged as editing (card stays + highlighted); re-adding updates it in place.
    queue.forEach(x => x.editing = false);
    q.editing = true;
    editingQueueId = q.id;
    renderQueue();

    // Restore package + date range.
    document.getElementById('tourPackage').value = q.pkg;
    syncPkgTriggerLabel();
    document.getElementById('fromDate').value = q.from;
    document.getElementById('toDate').value = q.to;
    syncDateRangeFromHidden();
    updateBaseDateRangeBadge();

    // Restore the time-slot rows + day filter (fresh row ids so the row controls stay unique).
    s2Rows = (q.createRows || []).map(r => ({ ...r, id: String(Date.now()) + '_' + (++s2RowIdCounter) }));
    s2SelectedDays = new Set((q.days && q.days.length) ? q.days : [0, 1, 2, 3, 4, 5, 6]);

    // Show the Create Slots editor (renders the rows/days, clears any existing-slot action, sets footer hint).
    switchS2Mode('create');

    // Render the op's slots in the preview section and unlock Add to Queue (the restored config is valid).
    const showEditPreview = () => {
      const filteredDays = s2GetFilteredDays();
      if (!filteredDays.length || !s2Rows.length) return;
      const pkgEl = document.getElementById('tourPackage');
      s2RenderCreatePreview({
        pkg: pkgEl.value,
        pkgName: pkgEl.options[pkgEl.selectedIndex].text,
        from: q.from, to: q.to,
        filteredDays,
        days: [...s2SelectedDays].sort((a, b) => a - b),
        Z: s2Rows.length,
        Y: filteredDays.length,
        X: filteredDays.length * s2Rows.length
      });
      const btn = document.getElementById('addToQueueBtn');
      if (btn) btn.disabled = false;
    };

    // Load existing DB slots in range so the "slot already exists" validation stays accurate while editing.
    // Render the preview AFTER the load settles — loadExistingSlots() calls updatePreview()/s2UpdatePreview()
    // on done, which would otherwise re-disable the button right after we enabled it.
    const req = loadExistingSlots();
    if (req && typeof req.always === 'function') {
      req.always(() => { showEditPreview(); if (typeof hideLoading === 'function') hideLoading(); });
    } else {
      showEditPreview();
      if (typeof hideLoading === 'function') hideLoading();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function editQueueItem(id) {
    const q = queue.find(item => item.id === id);
    if (!q) return;

    if (q.action === 'create') { editCreateQueueItem(q); return; }

    if (typeof showLoading === 'function') showLoading(); // global overlay loader (custom.js)
    if (s2Mode === 'create') switchS2Mode('select'); // existing-slot ops are edited in the Select Existing UI

    // Keep the op in the queue but flag it as being edited (its card stays, highlighted).
    // Re-adding will replace it in place; the DB row is updated (not duplicated).
    queue.forEach(x => x.editing = false);
    q.editing = true;
    editingQueueId = q.id;
    renderQueue();

    document.getElementById('tourPackage').value = q.pkg;
    syncPkgTriggerLabel();
    document.getElementById('fromDate').value = q.from;
    document.getElementById('toDate').value = q.to;
    syncDateRangeFromHidden();
    updateBaseDateRangeBadge();

    clearAllSlots();

    // Restore the op's slots. The Select-Existing grid loads from the DB, so seed it with these
    // slots for an immediate render; loadExistingSlots() below refreshes the full list from the DB.
    const opSlots = q.action === 'convert'
      ? Object.keys(q.convertTimes || {})
      : (q.baseSlots || []);
    existingSlots = opSlots.slice();
    opSlots.forEach(slot => {
      selectedSlots.add(slot);
      if (q.action !== 'convert') {
        const cur = getSlotData(q.from, slot);
        perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: false, fromDate: null };
      }
    });
    buildSlotsGrid();

    changePoints = (q.changePoints || []).map((cp, i) => ({
      id: ++cpCounter,
      date: cp.date,
      dateTo: cp.dateTo,
      slots: new Set(cp.slots)
    }));
    changePoints.forEach(cp => {
      cp.slots.forEach(slot => {
        if (!perSlotSeats[slot]) {
          const cur = getSlotData(q.from, slot);
          perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: true, fromDate: cp.date };
        }
      });
    });
    renderChangePoints();
    updateSlotCount();

    selectedAction = q.action;

    if (q.action === 'convert') {
      convertTimes  = { ...(q.convertTimes  || {}) };
      convertSeats  = { ...(q.convertSeats  || {}) };
      convertStatus = { ...(q.convertStatus || {}) };
      convertOnline = { ...(q.convertOnline || {}) };
      convertTimeParts = {};
      Object.entries(convertTimes).forEach(([slot, time]) => {
        const match = time.match(/^(\d+):(\d+)\s*(AM|PM)$/i);
        if (match) convertTimeParts[slot] = { h: match[1], m: match[2], ap: match[3].toUpperCase() };
      });
    } else if (q.action === 'seats' && q.seatSnapshot) {
      perSlotSeats = JSON.parse(JSON.stringify(q.seatSnapshot));
    }

    setTabActive(['status','seats','convert','visibility'].includes(q.action) ? q.action : null);

    if (q.action === 'convert') { showEditor('convertEditor'); renderConvertRules(); }
    else hideEditor('convertEditor');

    if (q.action === 'status') {
      // Preserve the real saved value (may be empty) — don't fall back to 'Open', otherwise an
      // unset slot would silently resolve to Open and skip the "select a status" validation.
      statusValue = q.statusValue || '';
      perSlotStatus = { ...(q.statusSnapshot || {}) };
      perSlotVisible = { ...(q.visibleSnapshot || {}) }; // combined Status & Visibility: restore per-slot show/hide
      const ssel = document.getElementById('statusModeSelect');
      ssel.value = statusValue;
      ssel.dataset.val = statusValue ? statusModeVal(statusValue) : 'grey';
      const vsel = document.getElementById('visModeSelect');
      if (vsel) { vsel.value = ''; vsel.dataset.val = 'grey'; } // per-slot values restored on rows; bulk starts unset
      showEditor('statusModeEditor');
      renderStatusEditor();
    } else {
      hideEditor('statusModeEditor');
      hideEditor('statusEditor');
    }

    if (q.action === 'visibility') {
      visibilityValue = q.visible !== false;
      const vsel = document.getElementById('visModeSelect');
      vsel.value = String(visibilityValue);
      vsel.dataset.val = visibilityValue ? 'green' : 'red';
      showEditor('visModeEditor');
    } else {
      hideEditor('visModeEditor');
    }

    renderSeatEditor();
    // Refresh grid from the DB; hide the overlay loader once the fetch settles.
    const req = loadExistingSlots();
    if (req && typeof req.always === 'function') {
      req.always(() => { if (typeof hideLoading === 'function') hideLoading(); });
    } else if (typeof hideLoading === 'function') {
      hideLoading();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function clearQueue() {
    if (!queue.length) return;
    queue = []; renderQueue();
  }

  // ── Queue list "+N more" popup helpers ──
  let qlCache = {}, qlSeq = 0;
  function qlCapped(items, cap, title, wrapCls, popupHtml) {
    const wrap = wrapCls || QI_SLOTSW;
    if (items.length <= cap) return `<div class="${wrap}">${items.join('')}</div>`;
    const key = 'ql' + (++qlSeq);
    qlCache[key] = { title, html: popupHtml || `<div class="${wrap}">${items.join('')}</div>` };
    return `<div class="${wrap}">${items.slice(0, cap).join('')}<button type="button" class="${QL_MORE}" onclick="qlShow('${key}')">+${items.length - cap} more</button></div>`;
  }
  function qlShow(key) {
    const d = qlCache[key]; if (!d) return;
    document.getElementById('slotListTitle').textContent = d.title;
    document.getElementById('slotListBody').innerHTML = d.html;
    document.getElementById('slotListOverlay').dataset.open = 'true';
  }
  function qlClose() { document.getElementById('slotListOverlay').dataset.open = 'false'; }

  // .ql-table popup table
  const QL_TH = "px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap";
  const QL_TD = "px-3 py-2 border-b border-[#f1f5f9] text-xs font-semibold text-[#334155] whitespace-nowrap";
  function qlTable(headers, rows) {
    return `<div class="border border-[#e2e8f0] rounded-lg overflow-hidden max-h-[65vh] overflow-y-auto"><table class="w-full border-collapse">
      <thead class="[&_tr]:bg-[#f8fafc]"><tr>${headers.map(h => `<th class="${QL_TH}">${h}</th>`).join('')}</tr></thead>
      <tbody class="[&_tr:last-child_td]:border-b-0 [&_tr:hover_td]:bg-[#f8fafc]">${rows.map(r => `<tr>${r.map(c => `<td class="${QL_TD}">${c}</td>`).join('')}</tr>`).join('')}</tbody>
    </table></div>`;
  }
  function qlStatusPill(st) {
    const c = st === 'Open' ? ['#dcfce7','#16a34a'] : st === 'Closed' ? ['#fee2e2','#dc2626'] : ['#e0e7ff','#4338ca'];
    return `<span style="display:inline-block;font-size:11px;font-weight:700;background:${c[0]};color:${c[1]};padding:2px 8px;border-radius:20px;">${st}</span>`;
  }
  function qlRegister(title, html) { const key = 'ql' + (++qlSeq); qlCache[key] = { title, html }; return key; }

  function seatsAffectedRows(q) {
    const rows = [];
    const addRow = (date, slot, status, curSeats, newCount) => {
      const changed = curSeats !== newCount;
      rows.push([
        fmtShort(date), slot, qlStatusPill(status), curSeats,
        `<strong style="color:${changed?'#d97706':'#94a3b8'};">${newCount}</strong>`
      ]);
    };
    // Current seats come from the seat snapshot's currentCount (the real value shown in the seat
    // editor, always stored with the op) — not from the live slotIndex, which may no longer cover
    // the op's range and would show 0. Use the captured per-date status when available.
    if (q.affectedSlots && q.affectedSlots.length) {
      q.affectedSlots.forEach(a => {
        const sd = q.seatSnapshot[a.slot];
        if (sd && sd.newCount !== null && sd.newCount !== undefined) {
          const curSeats = (sd.currentCount !== null && sd.currentCount !== undefined) ? sd.currentCount : a.seats;
          addRow(a.date, a.slot, a.status, curSeats, sd.newCount);
        }
      });
      return rows;
    }
    getDates(q.from, q.to).forEach(date => {
      const active = new Set(q.baseSlots);
      (q.changePoints || []).forEach(cp => {
        const cpTo = cp.dateTo || date;
        if (cp.date <= date && date <= cpTo) cp.slots.forEach(s => active.add(s));
      });
      [...active].forEach(slot => {
        const sd = q.seatSnapshot[slot];
        if (sd && sd.newCount !== null) {
          const info = getSlotData(date, slot);
          const curSeats = (sd.currentCount !== null && sd.currentCount !== undefined) ? sd.currentCount : info.seats;
          addRow(date, slot, info.exists ? info.status : '—', curSeats, sd.newCount);
        }
      });
    });
    return rows;
  }

  // Every slot a "create" op will actually produce: each selected date × each time-slot row.
  function createAllSlotsRows(q) {
    const dates = getDates(q.from, q.to).filter(d => (q.days || []).includes(parseLocal(d).getDay()));
    const rows = [];
    dates.forEach(date => {
      const day = DAYS[parseLocal(date).getDay()];
      (q.createRows || []).forEach(r => {
        rows.push([
          fmtShort(date),
          day,
          `${r.fromHour}:${r.fromMinute} ${r.fromPeriod}`,
          r.toHour ? `${r.toHour}:${r.toMinute} ${r.toPeriod}` : '—',
          r.seats,
          qlStatusPill(r.bookableStatus || 'Open'),
          r.hidden ? 'Yes' : 'No'
        ]);
      });
    });
    return rows;
  }

  function renderQueue() {
    const list  = document.getElementById('queueList');
    const empty = document.getElementById('queueEmpty');
    const totalSlots = queue.reduce((s,q)=>s+opSlotsAffected(q), 0);
    qlCache = {}; qlSeq = 0;

    document.getElementById('statQueueItems').textContent = queue.length;
    document.getElementById('statQueueTotal').textContent = totalSlots;
    document.getElementById('applyAllNum').textContent = totalSlots;
    document.getElementById('applyAllBtn').disabled = queue.length === 0;

    if (!queue.length) { list.innerHTML=''; empty.style.display='block'; return; }
    empty.style.display = 'none';

    list.innerHTML = queue.map((q, i) => {

      // ── Create queue card ──
      if (q.action === 'create') {
        const dayFilterLabel = s2DaysLabel(q.days);
        const createTable = qlTable(
          ['From time','To time','Seats','Booking status','Hidden'],
          (q.createRows || []).map(r => [
            `${r.fromHour}:${r.fromMinute} ${r.fromPeriod}`,
            `${r.toHour}:${r.toMinute} ${r.toPeriod}`,
            r.seats, qlStatusPill(r.bookableStatus), r.hidden ? 'Yes' : 'No'
          ])
        );
        const slotChips = qlCapped((q.createRows || []).map(r =>
          `<span class="${SLOT_MINI}">${r.fromHour}:${r.fromMinute} ${r.fromPeriod}</span>`
        ), 12, `${q.pkgName} — ${q.slotsPerDay} time slot${q.slotsPerDay!==1?'s':''}/day`, undefined, createTable);
        // "View all slots" → full list of every slot that will be created (each date × time slot).
        const createAllTable = qlTable(
          ['Date','Day','From time','To time','Seats','Booking status','Hidden'],
          createAllSlotsRows(q)
        );
        const createAllKey = qlRegister(`${q.pkgName} — ${q.total} slot${q.total!==1?'s':''} to create`, createAllTable);
        const createViewAll = `<button type="button" class="${QL_VIEWALL}" onclick="qlShow('${createAllKey}')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
          View all ${q.total} slot${q.total!==1?'s':''}
        </button>`;
        return `<div class="${QI_CARD}${q.editing ? ' ' + QI_EDITING : ''}">
          <div class="${QI_HEADER}">
            <div class="${QI_NUM}">
              <div class="${QI_BADGE}">${i+1}</div>
              <span class="${QI_TITLE}">${q.pkgName}</span>${q.editing ? QI_EDIT_BADGE : ''}
            </div>
            <span class="${QI_PILL}" style="background:#dcfce7;color:#16a34a;">Create</span>
          </div>
          <div class="${QI_BODY}">
            <div class="${QI_DETAIL}"><strong>Dates:</strong> ${fmtShort(q.from)} → ${fmtShort(q.to)} <span style="color:#cbd5e1;">(${q.numDays} day${q.numDays!==1?'s':''})</span></div>
            <div class="${QI_DETAIL}" style="margin-top:2px;"><strong>Days:</strong> ${dayFilterLabel}</div>
            <div class="${QI_DETAIL}" style="margin-top:4px;"><strong>${q.slotsPerDay} slot${q.slotsPerDay!==1?'s':''}/day:</strong></div>
            ${slotChips}
            <div class="${QI_AFFECT}"><span>Total slots to create</span><strong>${q.total} slots</strong></div>
            ${createViewAll}
          </div>
          <div class="${QI_FOOTER}">
            <button class="${QI_ICONEDIT}" onclick="editQueueItem(${q.id})" title="Edit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="${QI_ICONDEL}" onclick="removeFromQueue(${q.id})" title="Remove">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>
        </div>`;
      }

      // ── Repeat queue card ──
      if (q.action === 'repeat') {
        const repeatTable = qlTable(
          ['From time','To time','Seats','Status','Hidden'],
          (q.repeatRows || []).map(r => [
            `${r.fromHour}:${r.fromMinute} ${r.fromPeriod}`,
            `${r.toHour}:${r.toMinute} ${r.toPeriod}`,
            r.seats, qlStatusPill(r.bookingStatus), r.hidden ? 'Yes' : 'No'
          ])
        );
        const rowsHtml = qlCapped((q.repeatRows || []).map(r => {
          const statusColor = r.bookingStatus === 'Open' ? '#16a34a' : '#dc2626';
          const statusBg    = r.bookingStatus === 'Open' ? '#dcfce7' : '#fee2e2';
          const hiddenTag   = r.hidden ? `<span style="font-size:9px;font-weight:700;background:#f1f5f9;color:#64748b;padding:1px 6px;border-radius:4px;">Hidden</span>` : '';
          return `<div class="${QSEAT_ROW}">
            <span class="slot-name">${r.fromHour}:${r.fromMinute} ${r.fromPeriod} <span style="color:#cbd5e1;">→</span> ${r.toHour}:${r.toMinute} ${r.toPeriod}</span>
            <div class="flex items-center" style="gap:6px;">
              <span style="font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;padding:1px 6px;border-radius:4px;">${r.seats} seats</span>
              <span style="font-size:10px;font-weight:700;color:${statusColor};background:${statusBg};padding:1px 6px;border-radius:4px;">${r.bookingStatus}</span>
              ${hiddenTag}
            </div>
          </div>`;
        }), 8, `${q.pkgName} — ${q.slotsPerDay} slot${q.slotsPerDay!==1?'s':''}/day`, QSEAT_GRID, repeatTable);
        return `<div class="${QI_CARD}${q.editing ? ' ' + QI_EDITING : ''}">
          <div class="${QI_HEADER}">
            <div class="${QI_NUM}">
              <div class="${QI_BADGE}">${i+1}</div>
              <span class="${QI_TITLE}">${q.pkgName}</span>${q.editing ? QI_EDIT_BADGE : ''}
            </div>
            <span class="${QI_PILL} ${PILL_REPEAT}">Repeat</span>
          </div>
          <div class="${QI_BODY}">
            <div class="${QI_DETAIL}"><strong>Repeat dates:</strong> ${fmtShort(q.from)} → ${fmtShort(q.to)} <span style="color:#cbd5e1;">(${q.numDays} day${q.numDays!==1?'s':''})</span></div>
            <div class="${QI_DETAIL}" style="margin-top:2px;"><strong>Status:</strong> ${q.statusForAllDates}</div>
            <div class="${QI_DETAIL}" style="margin-top:4px;"><strong>${q.slotsPerDay} slot${q.slotsPerDay!==1?'s':''}/day:</strong></div>
            ${rowsHtml}
            <div class="${QI_AFFECT}"><span>Total slots to create</span><strong>${q.total} slots</strong></div>
          </div>
          <div class="${QI_FOOTER}">
            <button class="${QI_ICONEDIT}" onclick="editRepeatQueueItem(${q.id})" title="Edit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="${QI_ICONDEL}" onclick="removeFromQueue(${q.id})" title="Remove">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>
        </div>`;
      }

      // ── Delete queue card ──
      if (q.action === 'delete') {
        const multi = q.numDays > 1;
        const delItems = (q.deleteItems || []).map(r =>
          `<span class="${SLOT_MINI}"${r.bookingCount>0?' style="background:#fef3c7;color:#92400e;"':''}>${multi?fmtShort(r.date)+' · ':''}${r.fromTime}${r.bookingCount>0?` (${r.bookingCount})`:''}</span>`
        );
        const delTable = qlTable(
          (multi ? ['Date'] : []).concat(['From time','To time','Seats','Status','Bookings']),
          (q.deleteItems || []).map(r => (multi ? [fmtShort(r.date)] : []).concat([
            r.fromTime, r.toTime, r.seats, qlStatusPill(r.status),
            r.bookingCount > 0 ? `<strong style="color:#92400e;">${r.bookingCount}</strong>` : '0'
          ]))
        );
        const chips = qlCapped(delItems, 10, `${q.pkgName} — ${q.total} slot${q.total!==1?'s':''} to delete`, undefined, delTable);
        const bookWarn = q.bookedCount > 0
          ? `<div class="${QI_DETAIL}" style="color:#92400e;font-weight:700;margin-top:4px;">⚠ ${q.bookedCount} slot${q.bookedCount!==1?'s':''} with active bookings</div>`
          : '';
        return `<div class="${QI_CARD}${q.editing ? ' ' + QI_EDITING : ''}">
          <div class="${QI_HEADER}">
            <div class="${QI_NUM}">
              <div class="${QI_BADGE}">${i+1}</div>
              <span class="${QI_TITLE}">${q.pkgName}</span>${q.editing ? QI_EDIT_BADGE : ''}
            </div>
            <span class="${QI_PILL}" style="background:#fcebeb;color:#a32d2d;">Delete</span>
          </div>
          <div class="${QI_BODY}">
            <div class="${QI_DETAIL}"><strong>Dates:</strong> ${fmtShort(q.from)} → ${fmtShort(q.to)} <span style="color:#cbd5e1;">(${q.numDays} day${q.numDays!==1?'s':''})</span></div>
            <div class="${QI_DETAIL}" style="margin-top:4px;"><strong>${q.total} slot${q.total!==1?'s':''} to delete:</strong></div>
            ${chips}
            ${bookWarn}
            <div class="${QI_AFFECT}"><span>Total slots to delete</span><strong>${q.total} slots</strong></div>
          </div>
          <div class="${QI_FOOTER}">
            <button class="${QI_ICONEDIT}" onclick="editDeleteQueueItem(${q.id})" title="Edit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="${QI_ICONDEL}" onclick="removeFromQueue(${q.id})" title="Remove">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>
        </div>`;
      }

      const isConvert = q.action === 'convert';
      const pillClass = qiPillCls(q.action);
      const pillLabel = isConvert ? 'Convert Times'
        : q.action==='status'?'Update Status':q.action==='visibility'?'Visibility':'Update Seats';

      // ── Convert queue card ──
      if (isConvert) {
        const ct = q.convertTimes || {};
        const grps = {};
        Object.entries(ct).forEach(([old, nw]) => { if (!grps[nw]) grps[nw]=[]; grps[nw].push(old); });
        const cs = q.convertSeats || {};
        const rulesHtml = Object.entries(grps).map(([newTime, oldSlots]) => {
          const isMerge = oldSlots.length > 1;
          const seats  = oldSlots.map(s => cs[s]).find(v => v != null);
          const stat   = oldSlots.map(s => (q.convertStatus||{})[s]).find(v => v != null);
          const onl    = oldSlots.map(s => (q.convertOnline||{})[s]).find(v => v != null);
          const seatTag   = seats != null ? `<span style="font-size:10px;font-weight:700;background:#cffafe;color:#0e7490;padding:2px 7px;border-radius:4px;">${seats} seats</span>` : '';
          const statTag   = stat  != null ? `<span style="font-size:10px;font-weight:700;background:${stat==='Open'?'#dcfce7':'#fee2e2'};color:${stat==='Open'?'#16a34a':'#dc2626'};padding:2px 7px;border-radius:4px;">${stat}</span>` : '';
          const onlTag    = onl   != null ? `<span style="font-size:10px;font-weight:700;background:${onl?'#dbeafe':'#f1f5f9'};color:${onl?'#2563eb':'#475569'};padding:2px 7px;border-radius:4px;">Online: ${onl?'Yes':'No'}</span>` : '';
          return `<div class="${QI_DETAIL}" style="align-items:center;gap:6px;display:flex;flex-wrap:wrap;">
            ${isMerge ? `<span style="font-size:9px;font-weight:800;background:#ede9fe;color:#7c3aed;padding:2px 6px;border-radius:4px;">Merge</span>` : ''}
            <span style="font-size:12px;font-weight:700;color:#dc2626;text-decoration:line-through;">${oldSlots.join(' + ')}</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            <span style="font-size:12px;font-weight:700;color:#0891b2;">${newTime}</span>
            ${seatTag}${statTag}${onlTag}
          </div>`;
        }).join('');
        return `<div class="${QI_CARD}${q.editing ? ' ' + QI_EDITING : ''}">
          <div class="${QI_HEADER}">
            <div class="${QI_NUM}">
              <div class="${QI_BADGE}">${i+1}</div>
              <span class="${QI_TITLE}">${q.pkgName}</span>${q.editing ? QI_EDIT_BADGE : ''}
            </div>
            <span class="${QI_PILL} ${pillClass}">${pillLabel}</span>
          </div>
          <div class="${QI_BODY}">
            <div class="${QI_DETAIL}"><strong>Dates:</strong> ${fmtShort(q.from)} → ${fmtShort(q.to)} <span style="color:#cbd5e1;">(${getDates(q.from,q.to).length} days)</span></div>
            ${rulesHtml}
            <div class="${QI_AFFECT}"><span>Total affected</span><strong>${convertSlotsAffected(q)} slots</strong></div>
          </div>
          <div class="${QI_FOOTER}">
            <button class="${QI_ICONEDIT}" onclick="editQueueItem(${q.id})" title="Edit">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="${QI_ICONDEL}" onclick="removeFromQueue(${q.id})" title="Remove">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
            </button>
          </div>
        </div>`;
      }

      // ── Standard queue card ──
      const baseHasStatus = q.action === 'status' && q.statusSnapshot;
      const visSnap = q.visibleSnapshot || {};
      const baseTable = qlTable(
        baseHasStatus ? ['Time slot','Status','Online'] : ['Time slot'],
        q.baseSlots.map(s => baseHasStatus
          ? [s, qlStatusPill(q.statusSnapshot[s] || q.statusValue), (visSnap[s] === true ? 'Show' : visSnap[s] === false ? 'Hide' : '—')]
          : [s])
      );
      const baseChips = qlCapped(q.baseSlots.map(s=>`<span class="${SLOT_MINI}">${s}</span>`), 12, `${q.pkgName} — ${q.baseSlots.length} base slot${q.baseSlots.length!==1?'s':''}`, undefined, baseTable);
      const statusColors = { 'Open':['#dcfce7','#16a34a'], 'Closed':['#fee2e2','#dc2626'], 'Only by Phone':['#e0e7ff','#4338ca'] };
      // Only slots that actually got a status (visibility-only ops leave these empty).
      const statusVals = [...new Set((q.statusSnapshot ? Object.values(q.statusSnapshot) : [q.statusValue]).filter(Boolean))];
      const statusMixed = statusVals.length > 1;
      const statusLabel = statusMixed ? 'Mixed' : (statusVals[0] || null);
      // Visibility summary for the badge when it's a visibility-only op.
      const visVals = [...new Set(Object.values(visSnap))];
      const visLabel = visVals.length > 1 ? 'Show/Hide' : (visVals.length === 1 ? (visVals[0] ? 'Show' : 'Hide') : null);
      const onlineBadge = q.action === 'status'
        ? (statusLabel
            ? (sc => `<span style="font-size:10px;font-weight:700;background:${sc[0]};color:${sc[1]};padding:2px 7px;border-radius:4px;margin-left:6px;">${statusLabel}</span>`)(statusMixed ? ['#f1f5f9','#475569'] : (statusColors[statusLabel] || ['#f1f5f9','#64748b']))
            : (visLabel ? `<span style="font-size:10px;font-weight:700;background:#f3f0ff;color:#4c1d95;padding:2px 7px;border-radius:4px;margin-left:6px;">${visLabel}</span>` : ''))
        : (q.action === 'visibility'
            ? `<span style="font-size:10px;font-weight:700;background:${q.visible?'#e1f5ee':'#f3f0ff'};color:${q.visible?'#085041':'#4c1d95'};padding:2px 7px;border-radius:4px;margin-left:6px;">${q.visible?'Show':'Hide'}</span>`
            : '');

      let cpHtml = '';
      if (q.changePoints && q.changePoints.length > 0) {
        cpHtml = `<div class="flex flex-col gap-1.5 mt-2">` + q.changePoints.map(cp => {
          const endLabel = cp.dateTo ? ` → ${fmtShort(cp.dateTo)}` : '';
          return `<div class="flex items-start gap-2 px-2.5 py-[7px] bg-[#faf5ff] border border-[#e9d5ff] rounded-[7px] text-[11px]">
            <div class="font-bold text-[#6d28d9] whitespace-nowrap mt-px">${fmtShort(cp.date)}${endLabel}</div>
            <div class="flex flex-wrap gap-[3px]">${cp.slots.map(s=>`<span class="${SLOT_MINI_NEW}">${s}</span>`).join('')}</div>
          </div>`;
        }).join('') + `</div>`;
      }

      let seatsHtml = '';
      if (q.action === 'seats' && q.seatSnapshot) {
        const allSlots = [...q.baseSlots, ...q.changePoints.flatMap(cp=>cp.slots)];
        const seatRows = allSlots.map(slot => {
          const sd = q.seatSnapshot[slot];
          const cur = sd ? sd.currentCount : '—';
          const nxt = sd && sd.newCount !== null ? sd.newCount : null;
          const isNew = sd && sd.isNew;
          return `<div class="${QSEAT_ROW}">
            <span class="slot-name">${slot}${isNew?` <span style="font-size:9px;color:#7c3aed;font-weight:800;">[new]</span>`:''}</span>
            <div class="flex items-center gap-[5px]">
              <span class="text-[#94a3b8]">${cur}</span>
              <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="2.5" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              ${nxt !== null ? `<span class="font-bold ${isNew?'text-[#7c3aed] bg-[#ede9fe]':'text-[#d97706] bg-[#fef3c7]'} px-1.5 py-px rounded-[4px]">${nxt}</span>` : `<span class="text-[#cbd5e1] italic text-[10px]">no change</span>`}
            </div>
          </div>`;
        });
        const occTable = qlTable(['Date','Time slot','Status','Current seats','New seats'], seatsAffectedRows(q));
        const occKey = qlRegister(`${q.pkgName} — ${q.total} affected slot${q.total!==1?'s':''}`, occTable);
        const viewAll = `<button class="${QL_VIEWALL}" onclick="qlShow('${occKey}')">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          View all ${q.total} affected slot${q.total!==1?'s':''}
        </button>`;
        seatsHtml = qlCapped(seatRows, 8, `${q.pkgName} — seats for ${seatRows.length} slot${seatRows.length!==1?'s':''}`, QSEAT_GRID, occTable) + viewAll;
      }

      let statusHtml = '';
      if (q.action === 'status' && q.statusSnapshot && statusMixed) {
        const statusRows = Object.entries(q.statusSnapshot).map(([slot, st]) => {
          const sc = statusColors[st] || ['#f1f5f9','#64748b'];
          return `<div class="${QSEAT_ROW}">
            <span class="slot-name">${slot}</span>
            <span style="font-size:10px;font-weight:700;background:${sc[0]};color:${sc[1]};padding:1px 7px;border-radius:4px;">${st}</span>
          </div>`;
        });
        const statusTable = qlTable(
          ['Time slot','Status'],
          Object.entries(q.statusSnapshot).map(([slot, st]) => [slot, qlStatusPill(st)])
        );
        statusHtml = qlCapped(statusRows, 8, `${q.pkgName} — status for ${statusRows.length} slot${statusRows.length!==1?'s':''}`, QSEAT_GRID, statusTable);
      }

      return `<div class="${QI_CARD}${q.editing ? ' ' + QI_EDITING : ''}">
        <div class="${QI_HEADER}">
          <div class="${QI_NUM}">
            <div class="${QI_BADGE}">${i+1}</div>
            <span class="${QI_TITLE}">${q.pkgName}</span>${q.editing ? QI_EDIT_BADGE : ''}
          </div>
          <span class="${QI_PILL} ${pillClass}">${pillLabel}</span>
        </div>
        <div class="${QI_BODY}">
          <div class="${QI_DETAIL}"><strong>Dates:</strong> ${fmtShort(q.from)} → ${fmtShort(q.to)} <span style="color:#cbd5e1;">(${getDates(q.from,q.to).length} days)</span>${onlineBadge}</div>
          <div class="${QI_DETAIL}"><strong>Base slots:</strong></div>
          ${baseChips}
          ${cpHtml}
          ${q.action !== 'seats' ? '' : seatsHtml}
          ${statusHtml}
          <div class="${QI_AFFECT}"><span>Total affected</span><strong>${q.total} slots</strong></div>
        </div>
        <div class="${QI_FOOTER}">
          <button class="${QI_ICONEDIT}" onclick="editQueueItem(${q.id})" title="Edit">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="${QI_ICONDEL}" onclick="removeFromQueue(${q.id})" title="Remove">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          </button>
        </div>
      </div>`;
    }).join('');
  }

/* ===== CHUNK 6 ===== */
  // .modal-queue-item base + mq-* left border colors
  const MQ_BASE = "bg-[#f8fafc] rounded-lg px-[13px] py-2.5 border-l-[3px] text-xs font-medium text-[#475569] leading-[1.7] [&_strong]:text-[#1e293b] [&_strong]:font-bold";
  function mqCls(kind) {
    if (kind === 'open')    return MQ_BASE + " border-l-[#16a34a]";
    if (kind === 'phone')   return MQ_BASE + " border-l-[#6366f1]";
    if (kind === 'close')   return MQ_BASE + " border-l-[#dc2626]";
    if (kind === 'seats')   return MQ_BASE + " border-l-[#d97706]";
    if (kind === 'convert') return MQ_BASE + " border-l-[#0891b2]";
    if (kind === 'warn')    return MQ_BASE + " border-l-[#dc2626] !bg-[#fff5f5]";
    return MQ_BASE + " border-l-[#e2e8f0]";
  }

  // Coloured pill for the operation type, shown next to each op in the Apply-All confirmation list.
  function mqBadge(action) {
    const map = {
      create:     ['#dcfce7', '#16a34a', 'Create Slots'],
      status:     ['#dcfce7', '#16a34a', 'Update Status'],
      seats:      ['#fef3c7', '#d97706', 'Update Seats'],
      convert:    ['#cffafe', '#0e7490', 'Convert Times'],
      visibility: ['#f3f0ff', '#4c1d95', 'Visibility'],
      repeat:     ['#fef3c7', '#d97706', 'Repeat'],
      delete:     ['#fcebeb', '#a32d2d', 'Delete'],
    };
    const [bg, color, label] = map[action] || ['#f1f5f9', '#475569', action];
    return `<span class="${QI_PILL}" style="background:${bg};color:${color};">${label}</span>`;
  }

  // Coloured pill for an operation's processing status, shown in the Change History list.
  function statusBadge(status) {
    const map = {
      applied:    ['#dcfce7', '#16a34a', 'Applied'],
      processing: ['#dbeafe', '#1d4ed8', 'Processing'],
      queued:     ['#f1f5f9', '#475569', 'Queued'],
      failed:     ['#fee2e2', '#dc2626', 'Failed'],
    };
    const [bg, color, label] = map[status] || ['#f1f5f9', '#475569', status || 'Unknown'];
    return `<span class="${QI_PILL}" style="background:${bg};color:${color};">${label}</span>`;
  }

  // ── Confirm + Apply ──
  function getPendingFormSummary() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    const pkg  = document.getElementById('tourPackage').value;
    if (!from || !to || !pkg || !selectedAction) return null;

    const hasSlots = selectedAction === 'convert'
      ? Array.from(selectedSlots).some(s => (convertTimes[s]||'').trim())
      : selectedSlots.size > 0;
    if (!hasSlots) return null;

    const pkgEl   = document.getElementById('tourPackage');
    const pkgName = pkgEl.options[pkgEl.selectedIndex].text;
    const actionLabel = { status:'Update Status', seats:'Update Seats', convert:'Convert Times' }[selectedAction] || selectedAction;
    const days = getDates(from, to).length;
    const slotCount = selectedAction === 'convert'
      ? Array.from(selectedSlots).filter(s => (convertTimes[s]||'').trim()).length
      : selectedSlots.size;
    return `${pkgName} · ${fmtShort(from)} → ${fmtShort(to)} (${days} day${days!==1?'s':''}) · ${slotCount} slot${slotCount!==1?'s':''} · ${actionLabel}`;
  }

  function openConfirm() {
    if (!queue.length) return;
    const totalSlots = queue.reduce((s,q)=>s+opSlotsAffected(q), 0);

    const pendingSummary = getPendingFormSummary();
    const warnEl = document.getElementById('modalPendingWarn');

    const hasAnyWarning = !!pendingSummary;
    document.getElementById('modalIcon').style.background = hasAnyWarning ? '#fff7ed' : '#dcfce7';
    document.getElementById('modalIcon').innerHTML = hasAnyWarning
      ? `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`
      : `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>`;

    let warnHtml = '';
    if (pendingSummary) {
      warnHtml += `<div style="display:flex;gap:10px;align-items:flex-start;background:#fff7ed;border:1.5px solid #fdba74;border-radius:9px;padding:11px 13px;margin-bottom:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <div>
          <div style="font-weight:700;font-size:13px;color:#92400e;margin-bottom:3px;">Un-queued changes will not be applied</div>
          <div style="font-size:12px;color:#b45309;line-height:1.4;">The form has a configuration not yet added to the queue:<br><strong>${pendingSummary}</strong><br>Click <em>Add to Queue</em> first, or proceed to apply only what's listed below.</div>
        </div>
      </div>`;
    }
    warnEl.style.display = warnHtml ? 'block' : 'none';
    warnEl.innerHTML = warnHtml;

    document.getElementById('modalTitle').textContent = `Apply ${queue.length} Operation${queue.length!==1?'s':''}?`;
    document.getElementById('modalDesc').textContent = `This will apply all ${queue.length} queued operation${queue.length!==1?'s':''}, affecting ${totalSlots} time slot${totalSlots!==1?'s':''} in total.`;
    document.getElementById('modalQueueList').innerHTML = queue.map((q,i) => {
      if (q.action === 'convert') {
        const ct = q.convertTimes || {};
        const rulesSummary = Object.entries(ct).map(([o,n])=>`${o}→${n}`).join(', ');
        const ctCount = Object.keys(q.convertTimes||{}).length;
        return `<div class="${mqCls('convert')}">
          <strong>#${i+1} ${q.pkgName}</strong> ${mqBadge('convert')}<br>
          ${fmtShort(q.from)} → ${fmtShort(q.to)} · ${ctCount} slot${ctCount!==1?'s':''} remapped · <strong>${convertBookingsMoving(q)} bookings moving</strong><br>
          <span style="font-size:10px;color:#0e7490;">${rulesSummary}</span>
        </div>`;
      }
      if (q.action === 'repeat') {
        return `<div class="${mqCls('seats')}" style="border-left-color:#d97706;">
          <strong>#${i+1} ${q.pkgName}</strong> ${mqBadge('repeat')}<br>
          ${fmtShort(q.from)} → ${fmtShort(q.to)} · ${q.slotsPerDay} slot${q.slotsPerDay!==1?'s':''}/day · Status: ${q.statusForAllDates} · <strong>${q.total} total slots</strong>
        </div>`;
      }
      if (q.action === 'delete') {
        const bookNote = q.bookedCount > 0 ? ` · <span style="color:#a32d2d;font-weight:700;">${q.bookedCount} with bookings</span>` : '';
        return `<div class="${MQ_BASE}" style="border-left-color:#a32d2d;">
          <strong>#${i+1} ${q.pkgName}</strong> ${mqBadge('delete')}<br>
          ${fmtShort(q.from)} → ${fmtShort(q.to)} · <strong>${q.total} slot${q.total!==1?'s':''}</strong> will be deleted${bookNote}
        </div>`;
      }
      if (q.action === 'create') {
        const spd = q.slotsPerDay || (q.createRows ? q.createRows.length : 0);
        return `<div class="${mqCls('open')}">
          <strong>#${i+1} ${q.pkgName}</strong> ${mqBadge('create')}<br>
          ${fmtShort(q.from)} → ${fmtShort(q.to)} · ${spd} slot${spd!==1?'s':''}/day · <strong>${q.total} total slot${q.total!==1?'s':''} to create</strong>
        </div>`;
      }
      const needsCpWarn = q.action !== 'convert' && q.action !== 'repeat' && q.action !== 'create' && q.action !== 'delete' && q.action !== 'visibility' && getDates(q.from, q.to).length > 1 && (!q.changePoints || q.changePoints.length === 0);
      const kind = q.action==='status'?'open':q.action==='visibility'?'phone':'seats';
      const cpNote = q.changePoints.length ? ` · ${q.changePoints.length} schedule change${q.changePoints.length!==1?'s':''}` : '';
      const onlineNote = q.action==='status' ? statusNoteFor(q) : (q.action==='visibility' ? ` · ${q.visible ? 'Show' : 'Hide'}` : '');
      const cpWarnTag = needsCpWarn
        ? `<br><span style="font-size:10px;font-weight:700;color:#dc2626;">⚠ No schedule changes — new tour times on later dates may be missing</span>`
        : '';
      return `<div class="${mqCls(kind)}${needsCpWarn?' border-l-[#dc2626] !bg-[#fff5f5]':''}">
        <strong>#${i+1} ${q.pkgName}</strong> ${mqBadge(q.action)}<br>
        ${fmtShort(q.from)} → ${fmtShort(q.to)}${cpNote}${onlineNote} · <strong>${q.total} total slots</strong>${cpWarnTag}
      </div>`;
    }).join('');
    document.getElementById('modalOverlay').dataset.open = 'true';
  }

  function closeConfirm() { document.getElementById('modalOverlay').dataset.open = 'false'; }
  function closeConfirmOutside(e) { if(e.target===document.getElementById('modalOverlay')) closeConfirm(); }

  // After Apply All: return the editor to a clean default state — Select Existing tab, no package,
  // the default date range, an empty preview, and a fresh create builder.
  // Full reset to the default state: no package, default date range (synced), empty slots + preview.
  function resetEditorToDefault() {
    switchS2Mode('select');
    s2Reset();
    resetForm(); // clears package, selected slots, editors and the preview section
    // Reset the date range to the page default (today → +1 month) and refresh the visible picker + badge.
    const start = moment(), end = moment().add(1, 'month');
    document.getElementById('fromDate').value = start.format('YYYY-MM-DD');
    document.getElementById('toDate').value   = end.format('YYYY-MM-DD');
    syncDateRangeFromHidden();
    updateBaseDateRangeBadge();
  }

  // Apply All → dispatch every queued operation on the server as a chained queue (runs one-by-one
  // in the background). The server claims the ops (status → processing), so the list clears here.
  function applyAll() {
    closeConfirm();
    const cfg = window.BULK_MGR_CONFIG || {};
    if (!cfg.applyOperationsUrl) return;

    if (typeof showLoading === 'function') showLoading();
    $.ajax({ url: cfg.applyOperationsUrl, method: 'POST' })
      .done(res => {
        if (!res || res.success === false) {
          showAlertPopup((res && res.message) || 'Could not apply the operations. Please try again.', 'Apply failed');
          return;
        }
        const n = res.dispatched || 0;

        // Reload the page so the queue and the Change History are re-fetched from the server and
        // reflect the applied operations. Brief success message first, then refresh.
        if (typeof swal === 'function') {
          swal('Processing started', `${n} operation${n !== 1 ? 's' : ''} ${n !== 1 ? 'are' : 'is'} being applied.`, 'success');
        }
        setTimeout(() => window.location.reload(), 1000);
      })
      .fail(() => {
        showAlertPopup('Could not apply the operations. Please try again.', 'Apply failed');
      })
      .always(() => {
        if (typeof hideLoading === 'function') hideLoading();
      });
  }

  function showToast(ops, total) {
    const toast = document.getElementById('toast');
    document.getElementById('toastIcon').style.background = '#16a34a22';
    document.getElementById('toastIcon').innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>`;
    document.getElementById('toastTitle').textContent = `${ops} Operation${ops!==1?'s':''} Applied`;
    document.getElementById('toastMsg').textContent = `${total} time slot${total!==1?'s':''} updated successfully.`;
    toast.dataset.show = 'true';
    setTimeout(() => toast.dataset.show = 'false', 4000);
  }

  // ── Reset ──
  // keepStep1=true preserves the tour package & date range (used after queuing an op so the
  // next modification doesn't start with a disabled button until Step 1 is re-selected).
  function resetForm(keepStep1 = false) {
    if (!keepStep1) { editingQueueId = null; queue.forEach(x => x.editing = false); renderQueue(); } // Clear cancels editing
    if (!keepStep1) {
      document.getElementById('tourPackage').value = '';
      syncPkgTriggerLabel();
      const today = new Date(), end = new Date(today);
      end.setDate(end.getDate() + 13);
      document.getElementById('fromDate').value = fmtDate(today);
      document.getElementById('toDate').value = fmtDate(end);
      existingSlots = [];
      slotsGridMsg = 'Select a tour package and date range above to load existing slots.';
      buildSlotsGrid();
    }
    clearAllSlots();
    changePoints = [];
    renderChangePoints();
    selectedAction = null;
    perSlotSeats  = {};
    convertTimes     = {};
    convertTimeParts = {};
    convertSeats     = {};
    convertStatus    = {};
    convertOnline    = {};
    selectedOnline = null;
    setTabActive(null);
    hideEditor('seatEditor');
    hideEditor('convertEditor');
    hideEditor('statusModeEditor');
    hideEditor('statusEditor');
    statusValue = '';
    perSlotStatus = {};
    perSlotVisible = {};
    const statusSel = document.getElementById('statusModeSelect');
    if (statusSel) { statusSel.value = ''; statusSel.dataset.val = 'grey'; }
    visibilityValue = true;
    const visSel = document.getElementById('visModeSelect');
    if (visSel) { visSel.value = ''; visSel.dataset.val = 'grey'; }
    document.getElementById('quickFillInput').value = '';
    updatePreview();
  }

  // ── User name (topbar dropped — keep functions, guard missing elements) ──
  function startEditUser() {
    const disp = document.getElementById('userDisplay');
    const wrap = document.getElementById('userEditWrap');
    const input = document.getElementById('userNameInput');
    if (disp) disp.style.display = 'none';
    if (wrap) wrap.classList.add('visible');
    if (input) { input.value = currentUser; input.focus(); input.select(); }
  }

  function saveUserName() {
    const input = document.getElementById('userNameInput');
    const val = input ? input.value.trim() : '';
    if (val) currentUser = val;
    const dispName = document.getElementById('userDisplayName');
    if (dispName) dispName.textContent = currentUser;
    const disp = document.getElementById('userDisplay');
    if (disp) disp.style.display = '';
    const wrap = document.getElementById('userEditWrap');
    if (wrap) wrap.classList.remove('visible');
  }

  function cancelEditUser() {
    const disp = document.getElementById('userDisplay');
    if (disp) disp.style.display = '';
    const wrap = document.getElementById('userEditWrap');
    if (wrap) wrap.classList.remove('visible');
  }

  // ── History ──
  function fmtDateTime(d) {
    return d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' })
      + ' ' + d.toLocaleTimeString('en-US', { hour:'numeric', minute:'2-digit', hour12:true });
  }

  function toggleHistoryEntry(id) {
    const body    = document.getElementById(`hbody-${id}`);
    const chevron = document.getElementById(`hchev-${id}`);
    const isOpen  = body.dataset.open === 'true';
    body.dataset.open = isOpen ? 'false' : 'true';
    body.style.display = isOpen ? 'none' : 'block';
    if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
  }

  function clearHistory() {
    const cfg = window.BULK_MGR_CONFIG || {};
    if (!historyLog.length) return;
    if (!cfg.clearHistoryUrl) { historyLog = []; renderHistory(); return; }

    swal({
      title: 'Clear history?',
      text: 'This will remove all applied operations from the Change History. This cannot be undone.',
      icon: 'warning',
      buttons: { no: { text: 'No', value: false }, yes: { text: 'Yes, clear', className: 'btn-danger' } }
    }).then(confirmed => {
      if (!confirmed) return;
      if (typeof showLoading === 'function') showLoading();
      $.ajax({ url: cfg.clearHistoryUrl, method: 'POST' })
        .done(res => {
          if (res && res.success === false) {
            showAlertPopup(res.message || 'Could not clear the history. Please try again.', 'Clear failed');
            return;
          }
          historyLog = [];
          hydrateHistoryFromServer(); // refresh from the server (soft-deleted ops are gone)
        })
        .fail(() => showAlertPopup('Could not clear the history. Please try again.', 'Clear failed'))
        .always(() => { if (typeof hideLoading === 'function') hideLoading(); });
    });
  }

  // .history-op base + hop-* left border colors
  const HOP_BASE = "bg-[#f8fafc] rounded-lg px-3 py-[9px] border-l-[3px] text-xs text-[#475569] leading-[1.6] [&_strong]:text-[#1e293b]";
  function hopCls(action) {
    if (action === 'status')  return HOP_BASE + " border-l-[#16a34a]";
    if (action === 'seats')   return HOP_BASE + " border-l-[#d97706]";
    if (action === 'convert') return HOP_BASE + " border-l-[#0891b2]";
    return HOP_BASE + " border-l-[#e2e8f0]";
  }

  // " · <status>" note for an Update Status op: the global value, the single per-slot value, or
  // "Mixed" when slots have different targets. Returns '' (never "null") when there's nothing to show.
  function statusNoteFor(q) {
    if (q.statusValue) return ` · ${q.statusValue}`;
    const vals = [...new Set(Object.values(q.statusSnapshot || {}).filter(Boolean))];
    if (vals.length === 1) return ` · ${vals[0]}`;
    if (vals.length > 1) return ' · Mixed';
    return '';
  }

  function renderHistory() {
    const list    = document.getElementById('historyList');
    const empty   = document.getElementById('historyEmpty');
    const countEl = document.getElementById('historyCount');
    const total   = historyLog.reduce((s, h) => s + h.ops.length, 0);
    countEl.textContent = total;

    if (!historyLog.length) { list.innerHTML = ''; empty.style.display = 'block'; return; }
    empty.style.display = 'none';

    list.innerHTML = historyLog.map(entry => {
      const opCount    = entry.ops.length;
      const pkgNames   = [...new Set(entry.ops.map(o => o.pkgName))].join(', ');
      const minFrom    = entry.ops.reduce((a, o) => a < o.from ? a : o.from, entry.ops[0].from);
      const maxTo      = entry.ops.reduce((a, o) => a > o.to   ? a : o.to,   entry.ops[0].to);
      // Distinct operation-type badges, shown in the collapsed header (outside the accordion body).
      const actionBadges = [...new Set(entry.ops.map(o => o.action))].map(a => mqBadge(a)).join('');

      const opsHtml = entry.ops.map(q => {
        const days = getDates(q.from, q.to).length;
        const cpNote = (q.changePoints||[]).length ? ` · ${q.changePoints.length} schedule change${q.changePoints.length!==1?'s':''}` : '';
        const onlineNote = q.action==='status' ? statusNoteFor(q) : (q.action==='visibility' ? ` · ${q.visible ? 'Show' : 'Hide'}` : '');

        let detail = '';
        if (q.action === 'convert') {
          const rules = Object.entries(q.convertTimes||{}).map(([o,n]) =>
            `<span class="${SLOT_MINI}">${o}</span><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2.5" stroke-linecap="round" style="flex-shrink:0"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg><span class="${SLOT_MINI}" style="background:#cffafe;color:#0e7490;">${n}</span>`
          ).join(' ');
          detail = `<div class="flex flex-wrap gap-[3px] mt-[5px] items-center">${rules}</div>`;
        } else if (q.action === 'seats' && q.seatSnapshot) {
          const changed = Object.entries(q.seatSnapshot).filter(([,v]) => v.newCount !== null);
          detail = `<div class="flex flex-wrap gap-[3px] mt-[5px]">${changed.map(([slot, v]) =>
            `<span class="${SLOT_MINI}">${slot}: ${v.currentCount}→${v.newCount}</span>`).join('')}</div>`;
        } else {
          const slots = [...(q.baseSlots||[]), ...(q.changePoints||[]).flatMap(cp => cp.slots||[])];
          detail = `<div class="flex flex-wrap gap-[3px] mt-[5px]">${slots.map(s => `<span class="${SLOT_MINI}">${s}</span>`).join('')}</div>`;
        }

        return `<div class="${hopCls(q.action)}">
          <strong>${q.pkgName}</strong> ${mqBadge(q.action)}${onlineNote}<br>
          ${fmtShort(q.from)} → ${fmtShort(q.to)} · ${days} day${days!==1?'s':''} · <strong>${q.action==='convert'?convertBookingsMoving(q):q.total} ${q.action==='convert'?'bookings':'slots'} affected</strong>${cpNote}
          ${detail}
        </div>`;
      }).join('');

      return `<div class="bg-white border-[1.5px] border-[#e2e8f0] rounded-xl overflow-hidden">
        <div class="flex items-center justify-between gap-4 px-4 py-3 bg-[#f8fafc] border-b border-[#f1f5f9] cursor-pointer select-none hover:bg-[#f1f5f9]" onclick="toggleHistoryEntry(${entry.id})">
          <div class="flex flex-col gap-1 min-w-[150px] flex-shrink-0 items-start">
            <span class="text-[11px] font-bold text-[#64748b] whitespace-nowrap">${fmtDateTime(entry.appliedAt)}</span>
            <span class="text-[11px] font-bold text-[#7c3aed] bg-[#ede9fe] px-[7px] py-px rounded-[4px] whitespace-nowrap">${entry.appliedBy || 'Unknown'}</span>
          </div>
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <span class="flex-1 min-w-0 text-left text-[13px] font-bold text-[#1e293b] whitespace-nowrap overflow-hidden text-ellipsis">${pkgNames}</span>
            <span class="flex items-center gap-1 flex-shrink-0">${actionBadges}${statusBadge(entry.status)}</span>
            <span class="text-[11px] text-[#64748b] font-semibold whitespace-nowrap bg-[#f1f5f9] px-2 py-0.5 rounded-[4px] flex-shrink-0">${fmtShort(minFrom)} → ${fmtShort(maxTo)}</span>
            <span class="text-[11px] font-bold text-[#475569] whitespace-nowrap flex-shrink-0">${opCount} op${opCount!==1?'s':''} · ${entry.totalApplied} affected</span>
            <svg id="hchev-${entry.id}" class="text-[#94a3b8] transition-transform duration-200 flex-shrink-0" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
        </div>
        <div class="px-4 py-3 border-t border-[#f1f5f9]" style="display:none;" data-open="false" id="hbody-${entry.id}">
          <div class="flex flex-col gap-2">${opsHtml}</div>
        </div>
      </div>`;
    }).join('');
  }

/* ===== CHUNK 7 ===== */
  // ─────────────────────────────────────────────
  // Step 2 — Create Slots mode
  // ─────────────────────────────────────────────
  let s2Mode = 'select';
  let s2RowIdCounter = 0;
  let s2Rows = [];
  let s2SelectedDays = new Set([0,1,2,3,4,5,6]);

  const S2_HOURS = ['1','2','3','4','5','6','7','8','9','10','11','12'];
  const S2_MINS  = Array.from({length:60},(_,i)=>String(i).padStart(2,'0'));
  const S2_DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

  // .cs-day-chip / .cs-day-remove / .cs-days-clear / .s2-days-placeholder / .s2-day-opt
  const CS_DAY_CHIP   = "inline-flex items-center gap-[3px] bg-[#f1f5f9] border border-[#e2e8f0] rounded-[5px] pl-2 pr-[7px] py-0.5 text-xs font-semibold text-[#334155]";
  const CS_DAY_REMOVE = "inline-flex items-center justify-center w-[14px] h-[14px] bg-none border-0 cursor-pointer text-[#94a3b8] text-sm p-0 transition-all duration-100 rounded-[3px] leading-none hover:text-[#dc2626] hover:bg-[#fee2e2]";
  const CS_DAYS_CLEAR = "ml-auto inline-flex items-center justify-center w-[18px] h-[18px] bg-none border-0 cursor-pointer text-[#94a3b8] text-[17px] p-0 transition-all duration-100 leading-none flex-shrink-0 hover:text-[#dc2626]";
  const S2_DAYS_PLACEHOLDER = "text-[13px] text-[#94a3b8] font-medium";
  const S2_DAY_OPT = "flex items-center gap-[9px] px-2 py-1.5 rounded-md text-[13px] font-semibold text-[#334155] cursor-pointer select-none hover:bg-[#f8fafc] [&_input]:w-[15px] [&_input]:h-[15px] [&_input]:cursor-pointer [&_input]:accent-[#1e3a5c]";
  const CS_HIDDEN_CHK = "w-4 h-4 cursor-pointer accent-[#1e3a5c]";

  // ── Days multi-select ──
  function s2ToggleDaysPanel(e) {
    if (e && (e.target.closest('[data-csdayremove]') || e.target.closest('[data-csdaysclear]'))) return;
    const dd = document.getElementById('s2DaysDD');
    const open = dd.dataset.open === 'true';
    dd.dataset.open = open ? 'false' : 'true';
    document.getElementById('s2DaysPanel').classList.toggle('hidden', open);
  }

  function s2RenderDays() {
    const box = document.getElementById('s2DaysBox');
    if (!box) return;
    // Caret needs ml-auto only when there's no clear button (placeholder); otherwise the clear
    // button carries ml-auto and the caret sits right next to it — keeping both flush right.
    const caret = pushRight => `<svg class="${pushRight ? 'ml-auto ' : ''}flex-shrink-0 text-[#94a3b8] transition-transform duration-150 group-data-[open=true]:rotate-180" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>`;
    const idxs = [...s2SelectedDays].sort((a,b)=>a-b);
    let html;
    if (idxs.length === 0) {
      html = `<span class="${S2_DAYS_PLACEHOLDER}">Select days…</span>` + caret(true);
    } else if (idxs.length === 7) {
      html = `<span class="${CS_DAY_CHIP}">All days</span>
        <button data-csdaysclear class="${CS_DAYS_CLEAR}" onclick="s2ClearDays(event)" title="Clear">×</button>` + caret(false);
    } else {
      html = idxs.map(i => `<span class="${CS_DAY_CHIP}">
        <button data-csdayremove class="${CS_DAY_REMOVE}" onclick="s2RemoveDay(event,${i})" title="Remove">×</button>
        ${S2_DAY_NAMES[i]}
      </span>`).join('');
      html += `<button data-csdaysclear class="${CS_DAYS_CLEAR}" onclick="s2ClearDays(event)" title="Clear">×</button>` + caret(false);
    }
    box.innerHTML = html;
  }

  function s2RenderDayOptions() {
    const opts = document.getElementById('s2DaysOptions');
    if (!opts) return;
    opts.innerHTML = S2_DAY_NAMES.map((name, i) =>
      `<label class="${S2_DAY_OPT}">
        <input type="checkbox" value="${i}" ${s2SelectedDays.has(i)?'checked':''} onchange="s2ToggleDay(${i},this.checked)">
        ${name}
      </label>`
    ).join('');
  }

  function s2ToggleDay(i, checked) {
    if (checked) s2SelectedDays.add(i); else s2SelectedDays.delete(i);
    s2RenderDays(); s2UpdatePreview();
  }

  function s2RemoveDay(e, i) {
    e.stopPropagation();
    s2SelectedDays.delete(i);
    s2RenderDays(); s2RenderDayOptions(); s2UpdatePreview();
  }

  function s2ClearDays(e) {
    if (e) e.stopPropagation();
    s2SelectedDays.clear();
    s2RenderDays(); s2RenderDayOptions(); s2UpdatePreview();
  }

  function s2SetDayPreset(p) {
    s2SelectedDays = new Set(
      p === 'all'      ? [0,1,2,3,4,5,6] :
      p === 'weekdays' ? [1,2,3,4,5]     :
      p === 'weekends' ? [0,6]           : []
    );
    s2RenderDays(); s2RenderDayOptions(); s2UpdatePreview();
  }

  function s2DaysLabel(daysArr) {
    if (!daysArr || daysArr.length === 0) return 'No days';
    const set = new Set(daysArr);
    if (set.size === 7) return 'All days';
    if (set.size === 5 && [1,2,3,4,5].every(d => set.has(d))) return 'Weekdays only';
    if (set.size === 2 && set.has(0) && set.has(6)) return 'Weekends only';
    return [...set].sort((a,b)=>a-b).map(i => DAYS[i]).join(', ');
  }

  // Close the days panel when clicking outside it
  document.addEventListener('click', e => {
    const dd = document.getElementById('s2DaysDD');
    if (dd && dd.dataset.open === 'true' && !dd.contains(e.target)) {
      dd.dataset.open = 'false';
      document.getElementById('s2DaysPanel').classList.add('hidden');
    }
  });

  function s2HrOpts(sel) {
    return S2_HOURS.map(h => `<option value="${h}"${sel===h?' selected':''}>${h}</option>`).join('');
  }
  function s2MnOpts(sel) {
    return S2_MINS.map(m => `<option value="${m}"${sel===m?' selected':''}>${m}</option>`).join('');
  }
  function s2ApOpts(sel) {
    return ['AM','PM'].map(p => `<option value="${p}"${sel===p?' selected':''}>${p}</option>`).join('');
  }

  function switchS2Mode(mode) {
    s2Mode = mode;
    document.getElementById('s2BtnCreate').dataset.active = (mode === 'create') ? 'true' : 'false';
    document.getElementById('s2BtnSelect').dataset.active = (mode === 'select') ? 'true' : 'false';
    document.getElementById('s2SelectMode').style.display   = mode === 'select' ? 'block' : 'none';
    document.getElementById('s2CreateMode').style.display   = mode === 'create' ? 'block' : 'none';
    const footer = document.getElementById('s2CreateFooter');
    footer.style.display = mode === 'create' ? 'flex' : 'none';
    // The Step 3 operation tabs (Status/Seats/Convert/Visibility…) apply to existing slots only.
    const actionTabs = document.getElementById('actionTabs');
    if (actionTabs) actionTabs.style.display = mode === 'select' ? '' : 'none';

    if (mode === 'create') {
      // Leaving the existing-slot flow → clear any active operation/editor so nothing stale shows.
      selectedAction = null;
      setTabActive(null);
      ['repeatEditor', 'deleteEditor', 'statusModeEditor', 'statusEditor', 'visModeEditor', 'convertEditor', 'seatEditor']
        .forEach(id => hideEditor(id));
      // In create mode the queue action lives on the bottom button; require a Preview first.
      const qbtn = document.getElementById('addToQueueBtn');
      if (qbtn) qbtn.disabled = true;
      document.getElementById('formFooterInfo').innerHTML = 'Configure slots, click <strong>Preview</strong>, then Add to Queue';
      if (s2Rows.length === 0) s2AddRow();
      else s2RenderRows();
      s2RenderDays();
      s2RenderDayOptions();
      s2UpdatePreview();
    } else {
      updatePreview(); // refresh the existing-slot preview
    }
  }

  function s2RenderRows() {
    const tbody = document.getElementById('s2SlotRows');
    if (!tbody) return;
    if (s2Rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:18px;color:#94a3b8;font-size:12px;font-weight:600;">No time slots added yet</td></tr>`;
      s2UpdatePreview();
      return;
    }
    tbody.innerHTML = s2Rows.map(r => {
      return `<tr id="s2row-${r.id}">
        <td class="${CS_TD_CLS}">
          <div class="flex items-center gap-[3px]">
            <select class="${CS_TP_H_CLS}" onchange="s2Set('${r.id}','fromHour',this.value)">${s2HrOpts(r.fromHour)}</select>
            <span class="${CS_COLON_CLS}">:</span>
            <select class="${CS_TP_M_CLS}" onchange="s2Set('${r.id}','fromMinute',this.value)">${s2MnOpts(r.fromMinute)}</select>
            <select class="${CS_TP_AP_CLS}" onchange="s2Set('${r.id}','fromPeriod',this.value)">${s2ApOpts(r.fromPeriod)}</select>
          </div>
        </td>
        <td class="${CS_TD_CLS}">
          <div class="flex items-center gap-[3px]">
            <select class="${CS_TP_H_CLS}" onchange="s2Set('${r.id}','toHour',this.value)">${s2HrOpts(r.toHour)}</select>
            <span class="${CS_COLON_CLS}">:</span>
            <select class="${CS_TP_M_CLS}" onchange="s2Set('${r.id}','toMinute',this.value)">${s2MnOpts(r.toMinute)}</select>
            <select class="${CS_TP_AP_CLS}" onchange="s2Set('${r.id}','toPeriod',this.value)">${s2ApOpts(r.toPeriod)}</select>
          </div>
        </td>
        <td class="${CS_TD_CLS}">
          <input type="number" class="${CS_SEATS_CLS}" value="${r.seats}" min="0" max="999"
            onchange="s2Set('${r.id}','seats',+this.value)" onfocus="this.select()">
        </td>
        <td class="${CS_TD_CLS}">
          <select class="${csStatusCls(r.bookableStatus)}" onchange="s2OnStatus('${r.id}',this)">
            <option value="Open"${r.bookableStatus==='Open'?' selected':''}>Open</option>
            <option value="Closed"${r.bookableStatus==='Closed'?' selected':''}>Closed</option>
          </select>
        </td>
        <td class="${CS_TD_CLS}" style="text-align:center;">
          <input type="checkbox" class="${CS_HIDDEN_CHK}" ${r.hidden?'checked':''}
            onchange="s2Set('${r.id}','hidden',this.checked)">
        </td>
        <td class="${CS_TD_CLS}">
          <button class="${CS_DEL_CLS}" onclick="s2Del('${r.id}')" title="Remove row">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </td>
      </tr>`;
    }).join('');
    s2UpdatePreview();
  }

  function s2AddRow() {
    s2RowIdCounter++;
    s2Rows.push({
      id: String(Date.now()) + '_' + s2RowIdCounter,
      fromHour: '7', fromMinute: '00', fromPeriod: 'AM',
      toHour:   '8', toMinute:   '00', toPeriod:   'AM',
      seats: 0,
      bookableStatus: 'Open',
      hidden: false
    });
    s2RenderRows();
  }

  // "Apply to All" (Create Slots) → set every time-slot row's seats to the entered bulk value.
  function s2ApplySeatsToAll() {
    const val = parseInt(document.getElementById('s2BulkSeats').value, 10);
    if (isNaN(val) || val < 1) return;
    s2Rows.forEach(r => { r.seats = val; });
    s2RenderRows(); // re-render the seat inputs + refresh the preview line (requires a fresh Preview)
  }

  function s2Set(id, field, value) {
    const r = s2Rows.find(x => x.id === id);
    if (r) r[field] = value;
    s2UpdatePreview();
  }

  function s2OnStatus(id, sel) {
    const r = s2Rows.find(x => x.id === id);
    if (!r) return;
    r.bookableStatus = sel.value;
    sel.className = csStatusCls(sel.value);
  }

  function s2Del(id) {
    s2Rows = s2Rows.filter(r => r.id !== id);
    s2RenderRows();
  }

  function s2GetFilteredDays() {
    const from = document.getElementById('fromDate').value;
    const to   = document.getElementById('toDate').value;
    if (!from || !to) return [];
    if (s2SelectedDays.size === 0) return [];
    return getDates(from, to).filter(d => s2SelectedDays.has(parseLocal(d).getDay()));
  }

  function s2UpdatePreview() {
    const el = document.getElementById('s2PreviewLine');
    if (!el || s2Mode !== 'create') return;
    // Any change to the create inputs invalidates the rendered preview → require Preview again.
    const qb = document.getElementById('addToQueueBtn');
    if (qb) qb.disabled = true;
    const Z = s2Rows.length;
    if (Z === 0) { el.innerHTML = 'Add at least one time slot row'; return; }
    const days = s2GetFilteredDays();
    if (days.length === 0) { el.innerHTML = 'Set a date range in Step 1 to see preview'; return; }
    const Y = days.length;
    const X = Y * Z;
    el.innerHTML = `This will create <strong>${X} slot${X!==1?'s':''}</strong> across <strong>${Y} day${Y!==1?'s':''}</strong> · <strong>${Z} time slot${Z!==1?'s':''}</strong> per day`;
  }

  function s2Reset() {
    s2Rows = [];
    s2SelectedDays = new Set([0,1,2,3,4,5,6]);
    s2RenderDays();
    s2RenderDayOptions();
    s2AddRow();
  }

  // Validate the create inputs. Returns the computed config, or null (after showing an alert).
  function s2ValidateCreate() {
    const pkg   = document.getElementById('tourPackage').value;
    const pkgEl = document.getElementById('tourPackage');
    const pkgName = pkgEl.options[pkgEl.selectedIndex].text;
    const from  = document.getElementById('fromDate').value;
    const to    = document.getElementById('toDate').value;

    if (!pkg)        { showAlertPopup('Select a Tour Package in Step 1 first.', 'Missing information'); return null; }
    if (!from || !to){ showAlertPopup('Set a date range in Step 1 first.', 'Missing information'); return null; }
    if (s2Rows.length === 0) { showAlertPopup('Add at least one time slot row.', 'Missing information'); return null; }
    if (s2Rows.some(r => !r.fromHour)) { showAlertPopup('Each row must have a From Time.', 'Missing information'); return null; }
    if (s2SelectedDays.size === 0) { showAlertPopup('Select at least one day.', 'Missing information'); return null; }

    const s2FromKey = r => `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toLowerCase()}`;
    const s2Seen = new Set(), s2Dups = new Set();
    s2Rows.forEach(r => { const k = s2FromKey(r); if (s2Seen.has(k)) s2Dups.add(k); else s2Seen.add(k); });
    if (s2Dups.size > 0) { showAlertPopup('Remove or change the repeated From time(s): ' + [...s2Dups].join(', '), 'Duplicate time slots'); return null; }

    const zeroSeat = s2Rows.filter(r => !(Number(r.seats) > 0));
    if (zeroSeat.length > 0) {
      const labels = zeroSeat.map(r => `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toLowerCase()}`).join(', ');
      showAlertPopup(`Every time slot must have at least 1 seat. Please set seats for: ${labels}`, 'Seats required');
      return null;
    }

    // "To Time" (when set) must be later than "From Time".
    const badRange = s2Rows.filter(r => {
      if (!r.toHour) return false; // To Time is optional
      const fromMin = slotTimeToMinutes(`${r.fromHour}:${r.fromMinute} ${r.fromPeriod}`);
      const toMin   = slotTimeToMinutes(`${r.toHour}:${r.toMinute} ${r.toPeriod}`);
      return toMin <= fromMin;
    });
    if (badRange.length > 0) {
      const labels = badRange.map(r => `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toUpperCase()}`).join(', ');
      showAlertPopup(`The "To Time" must be later than the "From Time" for: ${labels}.`, 'Invalid time range');
      return null;
    }

    const filteredDays = s2GetFilteredDays();

    // Don't create a slot that already exists on any of the selected dates.
    if (slotsLoaded) {
      const newTimes = s2Rows.map(r => `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toUpperCase()}`);
      const existsTimes = new Set();
      filteredDays.forEach(date => {
        newTimes.forEach(t => { if (slotIndex[`${date}|${t}`]) existsTimes.add(t); });
      });
      if (existsTimes.size > 0) {
        showAlertPopup(`These time slot${existsTimes.size > 1 ? 's' : ''} already exist on one or more of the selected dates: ${[...existsTimes].join(', ')}. Remove them or choose different times.`, 'Slots already exist');
        return null;
      }
    }

    const Z = s2Rows.length;
    const Y = filteredDays.length;
    return { pkg, pkgName, from, to, filteredDays, days: [...s2SelectedDays].sort((a,b)=>a-b), Z, Y, X: Y * Z };
  }

  // Render every slot that would be created (per date × row) into the main preview section.
  function s2RenderCreatePreview(v) {
    document.getElementById('previewThead').innerHTML =
      `<tr><th class="${PREV_TH_CLS}">Date</th><th class="${PREV_TH_CLS}">Day</th><th class="${PREV_TH_CLS}">From Time</th><th class="${PREV_TH_CLS}">To Time</th><th class="${PREV_TH_CLS}">Seats</th><th class="${PREV_TH_CLS}">Status</th><th class="${PREV_TH_CLS}">Hidden</th></tr>`;
    const PT_TD = "px-3 py-2 border-b border-[#f1f5f9] text-[#334155] font-medium";
    let rows = '';
    v.filteredDays.forEach(date => {
      const day = DAYS[parseLocal(date).getDay()];
      s2Rows.forEach(r => {
        const fromT = `${r.fromHour}:${r.fromMinute} ${String(r.fromPeriod).toUpperCase()}`;
        const toT   = r.toHour ? `${r.toHour}:${r.toMinute} ${String(r.toPeriod).toUpperCase()}` : '—';
        rows += `<tr class="[&:hover>td]:bg-[#f8fafc]">
          <td class="${PT_TD}" style="font-weight:600;">${fmtShort(date)}</td>
          <td class="${PT_TD}" style="color:#64748b;">${day}</td>
          <td class="${PT_TD}" style="font-weight:600;">${fromT}</td>
          <td class="${PT_TD}" style="color:#64748b;">${toT}</td>
          <td class="${PT_TD}">${r.seats}</td>
          <td class="${PT_TD}">${qlStatusPill(r.bookableStatus || 'Open')}</td>
          <td class="${PT_TD}">${r.hidden ? 'Yes' : 'No'}</td>
        </tr>`;
      });
    });
    document.getElementById('previewBody').innerHTML = rows;
    setPreviewCount(`${v.X} slot${v.X !== 1 ? 's' : ''}`, v.X > 0);
    document.getElementById('formFooterInfo').innerHTML =
      `<strong>${v.X} new slot${v.X !== 1 ? 's' : ''}</strong> · ${v.Z} time${v.Z !== 1 ? 's' : ''}/day across <strong>${v.Y}</strong> day${v.Y !== 1 ? 's' : ''} — review, then Add to Queue`;
  }

  // "Preview" in create mode → validate the inputs and render every slot into the preview section.
  // Queuing then happens from the bottom "Add to Queue" button.
  function s2Preview() {
    const v = s2ValidateCreate();
    if (!v) return;
    s2RenderCreatePreview(v);                              // show the slots that would be created
    const btn = document.getElementById('addToQueueBtn');
    if (btn) btn.disabled = false;                         // unlock the queue action
  }

  // Bottom "Add to Queue" button — dispatch by Step 2 mode.
  function onAddToQueueClick() {
    if (s2Mode === 'create') { s2QueueCreate(); return; }
    preAddCheck();
  }

  // "Add to Queue" in create mode → validate, show the slots in the preview section, then queue.
  function s2QueueCreate() {
    const v = s2ValidateCreate();
    if (!v) return;

    s2RenderCreatePreview(v); // show what's being added in the preview section

    queueCounter++;
    const item = {
      id: queueCounter,
      pkg: v.pkg, pkgName: v.pkgName, from: v.from, to: v.to,
      action:       'create',
      createRows:   s2Rows.map(r => ({...r})),
      days:         v.days,
      baseSlots:    [],
      changePoints: [],
      seatSnapshot: null,
      total:        v.X,
      slotsPerDay:  v.Z,
      numDays:      v.Y
    };
    item.record = buildCreateOperationRecord(item);

    // Editing an existing create op → replace it in place (keep its id + DB id) so persist updates it.
    const editing = editingQueueId != null;
    if (editing) {
      const idx = queue.findIndex(x => x.id === editingQueueId);
      const existing = idx >= 0 ? queue[idx] : null;
      item.id = editingQueueId;
      if (existing && existing.dbId) item.dbId = existing.dbId;
      if (idx >= 0) queue[idx] = item; else queue.push(item);
      editingQueueId = null;
    } else {
      queue.push(item);
    }
    renderQueue(); // optimistic — hidden behind the persist loader until the server responds

    // Persist to tour_slot_bulk_operations (PUT when editing, else POST). Backend re-checks for
    // duplicate (date + time) slots; only finalize the toast/reset once the save is confirmed.
    const finalize = (res) => {
      if (res && res.success === false) return; // duplicates: alert + rollback handled in persistOperation; keep the form to fix
      s2Toast(null, `${v.X} slot${v.X !== 1 ? 's' : ''} ${editing ? 'updated in' : 'added to'} queue`, 'success');
      s2Reset();
      const btn = document.getElementById('addToQueueBtn');
      if (btn) btn.disabled = true; // inputs were reset → require a fresh Preview
    };
    const xhr = persistOperation(item);
    if (xhr && typeof xhr.done === 'function') xhr.done(finalize);
    else finalize({});
  }

  function s2Toast(msg, detail, type) {
    const toast = document.getElementById('toast');
    const icon  = document.getElementById('toastIcon');
    const ttl   = document.getElementById('toastTitle');
    const tmsg  = document.getElementById('toastMsg');
    if (type === 'success') {
      icon.style.background = '#16a34a';
      icon.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>`;
      ttl.textContent  = 'Added to Queue';
      tmsg.textContent = detail || '';
    } else {
      icon.style.background = '#d97706';
      icon.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/></svg>`;
      ttl.textContent  = 'Missing Information';
      tmsg.textContent = msg || '';
    }
    toast.dataset.show = 'true';
    setTimeout(() => toast.dataset.show = 'false', 3000);
  }

  // Hook into date changes so the Create Slots preview stays live
  const _origOnDateChange = onDateChange;
  onDateChange = function() { _origOnDateChange(); s2UpdatePreview(); };
  const _origUpdatePreview = updatePreview;
  // In Create mode the preview is managed by the create flow (s2RenderCreatePreview / s2UpdatePreview);
  // the existing-slot preview logic must not run, or it would overwrite #previewBody.
  updatePreview = function() {
    if (s2Mode === 'create') { s2UpdatePreview(); return; }
    _origUpdatePreview();
    s2UpdatePreview();
  };

  // ── Searchable Tour Package dropdown (wraps the hidden native #tourPackage select) ──
  let pkgActiveIndex = -1;

  function pkgGetOptions() {
    return Array.from(document.getElementById('tourPackage').options)
      .filter(o => o.value !== '')
      .map(o => ({ value: o.value, text: o.text }));
  }

  function syncPkgTriggerLabel() {
    const sel   = document.getElementById('tourPackage');
    const label = document.getElementById('pkgTriggerLabel');
    const trig  = document.getElementById('pkgTrigger');
    const opt   = sel.options[sel.selectedIndex];
    if (sel.value && opt) { label.textContent = opt.text; trig.dataset.placeholder = 'false'; }
    else { label.textContent = 'Select Tour Package'; trig.dataset.placeholder = 'true'; }
  }

  // .ss-option / .ss-empty
  const SS_OPTION = "shrink-0 px-2.5 py-2 rounded-md text-[13px] font-semibold text-[#334155] cursor-pointer whitespace-nowrap overflow-hidden text-ellipsis hover:bg-[#f1f5f9] data-[selected=true]:bg-[#eff6ff] data-[selected=true]:text-[#1e3a5c] data-[active=true]:bg-[#e0f2fe]";

  function renderPkgOptions(filter) {
    const box = document.getElementById('pkgOptions');
    const q   = (filter || '').trim().toLowerCase();
    const cur = document.getElementById('tourPackage').value;
    const list = pkgGetOptions().filter(o => o.text.toLowerCase().includes(q));
    if (list.length === 0) { box.innerHTML = `<div class="py-3 px-2.5 text-xs font-semibold text-[#94a3b8] text-center">No packages found</div>`; pkgActiveIndex = -1; return; }
    if (pkgActiveIndex < 0 || pkgActiveIndex >= list.length) pkgActiveIndex = 0;
    box.innerHTML = list.map((o, i) =>
      `<div class="${SS_OPTION}" data-ssoption data-selected="${o.value===cur?'true':'false'}" data-active="${i===pkgActiveIndex?'true':'false'}"
        data-value="${o.value}" onclick="selectPkg('${o.value}')"
        onmousemove="pkgSetActive(${i})">${o.text}</div>`
    ).join('');
  }

  function pkgSetActive(i) {
    pkgActiveIndex = i;
    document.querySelectorAll('#pkgOptions [data-ssoption]').forEach((el, idx) => el.dataset.active = (idx === i) ? 'true' : 'false');
  }

  function filterPkgOptions(v) { pkgActiveIndex = 0; renderPkgOptions(v); }

  function openPkgDropdown() {
    const wrap = document.getElementById('pkgSelectWrap');
    wrap.dataset.open = 'true';
    document.getElementById('pkgPanel').classList.remove('hidden');
    const search = document.getElementById('pkgSearch');
    search.value = '';
    pkgActiveIndex = -1;
    renderPkgOptions('');
    setTimeout(() => search.focus(), 0);
  }
  function closePkgDropdown() {
    document.getElementById('pkgSelectWrap').dataset.open = 'false';
    document.getElementById('pkgPanel').classList.add('hidden');
  }

  function togglePkgDropdown(e) {
    if (e) e.stopPropagation();
    const wrap = document.getElementById('pkgSelectWrap');
    if (wrap.dataset.open === 'true') closePkgDropdown();
    else openPkgDropdown();
  }

  function selectPkg(value) {
    const sel = document.getElementById('tourPackage');
    const changed = sel.value !== value;
    sel.value = value;
    syncPkgTriggerLabel();
    closePkgDropdown();

    // Changing the package invalidates the current selection — those slots belong to the old package.
    // Reset the selected slots, their per-slot data, change points, convert mappings and the preview
    // (the chosen action is kept) before loading the new package's slots.
    if (changed) {
      selectedSlots.clear();
      perSlotSeats = {};
      changePoints = [];
      convertTimes = {}; convertTimeParts = {}; convertSeats = {}; convertStatus = {}; convertOnline = {};
      document.querySelectorAll('#slotsGrid label[data-slot]').forEach(c => {
        c.dataset.selected = 'false';
        const cb = c.querySelector('input'); if (cb) cb.checked = false;
      });
      renderChangePoints();
      updateSlotCount();
      if (selectedAction === 'convert') renderConvertRules();
      renderSeatEditor();
      updatePreview();
    }

    loadExistingSlots();
    if (selectedAction === 'delete') delRefresh(true);
  }

  function pkgSearchKeydown(e) {
    const opts = document.querySelectorAll('#pkgOptions [data-ssoption]');
    if (e.key === 'ArrowDown')      { e.preventDefault(); if (opts.length) pkgSetActive(Math.min(pkgActiveIndex + 1, opts.length - 1)); }
    else if (e.key === 'ArrowUp')   { e.preventDefault(); if (opts.length) pkgSetActive(Math.max(pkgActiveIndex - 1, 0)); }
    else if (e.key === 'Enter')     { e.preventDefault(); const el = opts[pkgActiveIndex]; if (el) selectPkg(el.dataset.value); }
    else if (e.key === 'Escape')    { e.preventDefault(); closePkgDropdown(); }
  }

  // Close the package dropdown on outside click
  document.addEventListener('click', e => {
    const wrap = document.getElementById('pkgSelectWrap');
    if (wrap && wrap.dataset.open === 'true' && !wrap.contains(e.target)) closePkgDropdown();
  });

  syncPkgTriggerLabel();

  // ─────────────────────────────────────────────
  // DeleteSlotsPanel
  // ─────────────────────────────────────────────
  let deletePanelOpen   = false;
  let deleteSlots       = [];
  let deleteSelectedIds = new Set();
  let deleteLoading     = false;
  let deleteError       = null;
  let deleteVisibleCount = 0;
  let delPendingRowId   = null;
  let delLastSig        = null;
  let delPendingPreselect = null;
  const DEL_PAGE = 20;

  function delPkg()     { return document.getElementById('tourPackage').value; }
  function delStepFrom() { return document.getElementById('fromDate').value; }
  function delStepTo()   { return document.getElementById('toDate').value; }
  function delSelectedSlots() { return sortSlots(selectedSlots); }
  function delDatesList() { const f = delStepFrom(), t = delStepTo(); return (f && t && f <= t) ? getDates(f, t) : []; }
  function delIsMultiDate() { return delDatesList().length > 1; }
  function delSignature() { return `${delPkg()}|${delStepFrom()}|${delStepTo()}|${delSelectedSlots().join(',')}`; }

  function delToTime(slot) {
    const fp = repeatParseSlot(slot);
    const t  = repeatFrom24(repeatTo24(fp.h, fp.m, fp.ap) + 60);
    return `${t.h}:${t.m} ${t.ap.toUpperCase()}`;
  }
  function delBuildRow(date, slot) {
    const sd = getSlotData(date, slot);
    const bk = getBookings(date, slot);
    return { id: `${date}|${slot}`, date, fromTime: slot, toTime: delToTime(slot),
             seats: sd.seats, status: sd.status === 'Closed' ? 'Closed' : 'Open', bookingCount: bk.length };
  }

  function delApiDelay(ms) { return new Promise(res => { let n = 0; (function spin(){ n++; if (n > (ms||400)/16) res(); else requestAnimationFrame(spin); })(); }); }
  async function delApiGetSelected(pkg, from, to, slots) {
    await delApiDelay(400);
    const rows = [];
    getDates(from, to).forEach(d => slots.forEach(s => rows.push(delBuildRow(d, s))));
    return rows;
  }

  function delDeactivate() {
    deletePanelOpen = false;
    const t = document.getElementById('tabDelete'); if (t) t.dataset.active = 'none';
    hideEditor('deleteEditor');
  }

  function toggleDelete() {
    if (selectedAction === 'delete') { selectedAction = null; delDeactivate(); updatePreview(); return; }
    selectedAction = 'delete';
    deletePanelOpen = true;
    setTabActive('delete');
    hideEditor('repeatEditor');
    hideEditor('convertEditor');
    hideEditor('seatEditor');
    hideEditor('statusModeEditor');
    hideEditor('statusEditor');
    hideEditor('visModeEditor');
    showEditor('deleteEditor');
    delPendingRowId = null;
    delRefresh(true);
    updatePreview();
  }

  function editDeleteQueueItem(id) {
    const q = queue.find(item => item.id === id);
    if (!q) return;
    queue = queue.filter(item => item.id !== id);
    renderQueue();

    document.getElementById('tourPackage').value = q.pkg;
    syncPkgTriggerLabel();
    document.getElementById('fromDate').value = q.from;
    document.getElementById('toDate').value = q.to;
    syncDateRangeFromHidden();
    updateBaseDateRangeBadge();

    clearAllSlots();
    // Seed the Select-Existing grid with this op's slots for an immediate render;
    // loadExistingSlots() below refreshes the full list from the DB.
    const delSlots = [...new Set((q.deleteItems || []).map(r => r.fromTime))];
    existingSlots = delSlots.slice();
    delSlots.forEach(slot => {
      selectedSlots.add(slot);
      const cur = getSlotData(q.from, slot);
      perSlotSeats[slot] = { newCount: null, currentCount: cur.seats, isNew: false, fromDate: null };
    });
    buildSlotsGrid();
    loadExistingSlots();
    updateSlotCount();

    selectedAction = 'delete';
    deletePanelOpen = true;
    setTabActive('delete');
    hideEditor('repeatEditor');
    hideEditor('convertEditor');
    hideEditor('seatEditor');
    hideEditor('statusModeEditor');
    hideEditor('statusEditor');
    hideEditor('visModeEditor');
    showEditor('deleteEditor');
    delPendingRowId = null;
    delPendingPreselect = new Set((q.deleteItems || []).map(r => r.id));
    delLastSig = null;
    delRefresh(true);
    updatePreview();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function delRenderInfo() {
    const el = document.getElementById('delInfo'); if (!el) return;
    const icon = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`;
    const slots = delSelectedSlots(), dates = delDatesList();
    let txt;
    if (!delPkg())            txt = 'Select a tour package in Step 1 to manage deletions.';
    else if (slots.length===0) txt = 'Select time slots in Step 2 — they will appear here for deletion.';
    else if (dates.length===0) txt = 'Set a valid date range in Step 1.';
    else if (dates.length===1) txt = `Showing <strong>${slots.length}</strong> selected slot${slots.length!==1?'s':''} on <strong>${fmtShort(dates[0])}</strong>`;
    else                       txt = `Showing <strong>${slots.length}</strong> selected slot${slots.length!==1?'s':''} across <strong>${fmtShort(dates[0])} → ${fmtShort(dates[dates.length-1])}</strong> (${dates.length} days)`;
    el.innerHTML = icon + `<span>${txt}</span>`;
  }

  async function delRefresh(force) {
    if (selectedAction !== 'delete' || !deletePanelOpen) return;
    delRenderInfo();
    const sig = delSignature();
    if (!force && sig === delLastSig) { delRenderContent(); return; }
    delLastSig = sig;
    delPendingRowId = null;

    const from = delStepFrom(), to = delStepTo(), slots = delSelectedSlots();
    if (!delPkg() || !from || !to || from > to || slots.length === 0) {
      deleteSlots = []; deleteSelectedIds.clear(); deleteLoading = false; deleteError = null;
      delRenderContent();
      return;
    }
    deleteLoading = true; deleteError = null; delRenderContent();
    try {
      const rows = await delApiGetSelected(delPkg(), from, to, slots);
      if (delSignature() !== sig) return;
      deleteSlots = rows;
      deleteSelectedIds.clear();
      deleteVisibleCount = Math.min(DEL_PAGE, rows.length);
      if (delPendingPreselect) {
        deleteSelectedIds = new Set(rows.filter(r => delPendingPreselect.has(r.id)).map(r => r.id));
        deleteVisibleCount = rows.length;
        delPendingPreselect = null;
      }
      deleteLoading = false;
      delRenderContent();
    } catch (err) {
      deleteLoading = false; deleteError = 'Failed to load slots. Please try again.';
      delRenderContent();
    }
  }

  // Delete table Tailwind class strings
  const DEL_EMPTY   = "py-7 px-4 text-center text-[#94a3b8] [&>svg]:opacity-40 [&>svg]:mx-auto [&>svg]:mb-2 [&>svg]:block [&>p]:text-[13px] [&>p]:font-semibold";
  const DEL_TBLWRAP = "border border-[#e2e8f0] rounded-lg overflow-hidden";
  const DEL_TH      = "px-2.5 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap";
  const DEL_TD      = "px-2.5 py-[9px] border-b border-[#f1f5f9] text-xs font-medium text-[#334155] align-middle";
  const DEL_CHK     = "w-[15px] h-[15px] cursor-pointer accent-[#a32d2d]";
  const DEL_LINK    = "text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]";
  function delStatusCls(statusCls) {
    const base = "inline-flex items-center px-2 py-0.5 rounded-[20px] text-[11px] font-bold";
    if (statusCls === 'open')   return base + " bg-[#e1f5ee] text-[#085041]";
    if (statusCls === 'closed') return base + " bg-[#fcebeb] text-[#791f1f]";
    return base + " bg-[#e0e7ff] text-[#4338ca]";
  }

  function delRenderContent() {
    const c = document.getElementById('delContent');
    const bottom = document.getElementById('delBottomBar');
    if (!c) return;
    const clip = `<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>`;
    const empty = msg => { c.innerHTML = `<div class="${DEL_EMPTY}">${clip}<p>${msg}</p></div>`; bottom.style.display = 'none'; };
    if (!delPkg())                 { empty('Please select a tour package in Step 1 first.'); return; }
    if (deleteError)               { empty(deleteError); return; }
    if (deleteLoading)             { c.innerHTML = delSkeleton(); bottom.style.display = 'none'; return; }
    if (delSelectedSlots().length === 0) { empty('No slots selected — choose time slots in Step 2.'); return; }
    if (delDatesList().length === 0)     { empty('Set a valid date range in Step 1.'); return; }
    if (deleteSlots.length === 0)        { empty('No slots found for the selected criteria.'); return; }
    c.innerHTML = delTableHtml();
    bottom.style.display = 'flex';
    delUpdateBottomBar();
  }

  function delSkeleton() {
    const row = `<tr><td colspan="8" style="padding:10px 12px;"><div class="h-3 rounded-md bg-[linear-gradient(90deg,#f1f5f9_25%,#e2e8f0_37%,#f1f5f9_63%)] bg-[length:400%_100%] animate-[delShimmer_1.2s_ease-in-out_infinite]"></div></td></tr>`;
    return `<div class="${DEL_TBLWRAP}"><table class="w-full border-collapse"><tbody>${row+row+row}</tbody></table></div>`;
  }

  function delVisible() { return deleteSlots.slice(0, deleteVisibleCount); }

  function delTableHtml() {
    const isRange = delIsMultiDate();
    const dates = delDatesList();
    const visible = delVisible();
    const deletableVisible = visible.filter(r => r.bookingCount === 0);
    const allChecked = deletableVisible.length > 0 && deletableVisible.every(r => deleteSelectedIds.has(r.id));

    const foundText = isRange
      ? `<strong>${deleteSlots.length}</strong> slot${deleteSlots.length!==1?'s':''} across ${fmtShort(dates[0])}–${fmtShort(dates[dates.length-1])}`
      : `<strong>${deleteSlots.length}</strong> slot${deleteSlots.length!==1?'s':''} on ${dates[0]?fmtShort(dates[0]):''}`;
    const toolbar = `<div class="flex items-center justify-between gap-3 mb-2 flex-wrap">
      <span class="text-xs font-semibold text-[#64748b] [&_strong]:text-[#1e293b]">${foundText}</span>
      <span class="flex gap-3"><button class="${DEL_LINK}" onclick="delSelectAll()">Select all</button><button class="${DEL_LINK}" onclick="delClearSelection()">Clear</button></span>
    </div>`;

    const dateTh = isRange ? `<th class="${DEL_TH}">Date</th>` : '';
    const headCb = deletableVisible.length === 0
      ? `<input type="checkbox" class="${DEL_CHK}" disabled>`
      : `<input type="checkbox" class="${DEL_CHK}" ${allChecked?'checked':''} onchange="delToggleAll(this.checked)">`;
    const head = `<thead class="bg-[#f8fafc]"><tr>
      <th class="${DEL_TH}" style="width:34px;">${headCb}</th>
      ${dateTh}<th class="${DEL_TH}">From time</th><th class="${DEL_TH}">To time</th><th class="${DEL_TH}" style="width:70px;">Seats</th><th class="${DEL_TH}" style="width:90px;">Status</th>
    </tr></thead>`;

    let lastDate = null;
    const body = visible.map(r => {
      const booked = r.bookingCount > 0;
      const checked = deleteSelectedIds.has(r.id);
      const statusCls = r.status === 'Open' ? 'open' : r.status === 'Closed' ? 'closed' : 'phone';
      const warn = booked ? `<div class="text-[10px] text-[#92400e] font-semibold mt-0.5">${r.bookingCount} active booking${r.bookingCount!==1?'s':''} · can't delete</div>` : '';
      const cb = booked
        ? `<input type="checkbox" class="${DEL_CHK}" disabled title="Has active bookings — can't be deleted">`
        : `<input type="checkbox" class="${DEL_CHK}" ${checked?'checked':''} onchange="delToggleRow('${r.id}',this.checked)">`;
      let dateCell = '';
      if (isRange) { const show = r.date !== lastDate; lastDate = r.date; dateCell = `<td class="${DEL_TD}">${show ? `<span class="font-extrabold text-[#1e293b] whitespace-nowrap">${fmtShort(r.date)}</span>` : ''}</td>`; }
      return `<tr class="${booked?'[&>td]:bg-[#fef3c7]':''} [&:last-child>td]:border-b-0">
        <td class="${DEL_TD}">${cb}</td>
        ${dateCell}
        <td class="${DEL_TD}"><div style="font-weight:700;color:#1e293b;">${r.fromTime}</div>${warn}</td>
        <td class="${DEL_TD}">${r.toTime}</td>
        <td class="${DEL_TD}">${r.seats}</td>
        <td class="${DEL_TD}"><span class="${delStatusCls(statusCls)}">${r.status}</span></td>
      </tr>`;
    }).join('');

    const colspan = isRange ? 6 : 5;
    let loadMore = '';
    if (deleteVisibleCount < deleteSlots.length) {
      const remaining = deleteSlots.length - deleteVisibleCount;
      loadMore = `<tr><td colspan="${colspan}" class="!p-0 bg-white"><button class="w-full py-[9px] bg-[#f8fafc] border-0 text-xs font-bold text-[#475569] cursor-pointer hover:bg-[#f1f5f9]" onclick="delLoadMore()">Load ${Math.min(remaining, DEL_PAGE)} more (${remaining} remaining)</button></td></tr>`;
    }

    const bookedCount = deleteSlots.filter(r => r.bookingCount > 0).length;
    const selWarn = bookedCount > 0
      ? `<div class="flex items-center gap-[7px] mt-2.5 px-3 py-2 bg-[#fffbeb] border-[1.5px] border-[#fde68a] rounded-lg text-xs font-semibold text-[#92400e] [&>svg]:flex-shrink-0"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>${bookedCount} slot${bookedCount!==1?'s':''} with active bookings can't be deleted and are locked.</div>`
      : '';

    return toolbar + `<div class="${DEL_TBLWRAP}"><table class="w-full border-collapse">${head}<tbody>${body}${loadMore}</tbody></table></div>` + selWarn;
  }

  function delToggleRow(id, checked) {
    const r = deleteSlots.find(x => x.id === id);
    if (r && r.bookingCount > 0) return;
    if (checked) deleteSelectedIds.add(id); else deleteSelectedIds.delete(id);
    delRenderContent();
  }
  function delToggleAll(checked)     { delVisible().filter(r => r.bookingCount === 0).forEach(r => { if (checked) deleteSelectedIds.add(r.id); else deleteSelectedIds.delete(r.id); }); delRenderContent(); }
  function delSelectAll()            { deleteSlots.filter(r => r.bookingCount === 0).forEach(r => deleteSelectedIds.add(r.id)); deleteVisibleCount = deleteSlots.length; delRenderContent(); }
  function delClearSelection()       { deleteSelectedIds.clear(); delRenderContent(); }

  function delLoadMore() {
    deleteVisibleCount = Math.min(deleteVisibleCount + DEL_PAGE, deleteSlots.length);
    delRenderContent();
  }

  function delUpdateBottomBar() {
    const n = deleteSelectedIds.size;
    const line = document.getElementById('delPreviewLine');
    const btn  = document.getElementById('delBulkBtn');
    if (!line || !btn) return;
    const dates = delDatesList();
    const text = dates.length > 1
      ? `${n} selected · will delete ${n} slot${n!==1?'s':''} across ${fmtShort(dates[0])}–${fmtShort(dates[dates.length-1])}`
      : `${n} selected · will delete ${n} slot${n!==1?'s':''} on ${dates[0]?fmtShort(dates[0]):'—'}`;
    line.innerHTML = text;
    btn.textContent = `Add to Queue (${n})`;
    btn.disabled = n === 0;
  }

  function delBulkDeleteClick() {
    if (deleteSelectedIds.size === 0) return;
    const bookedSel = deleteSlots.filter(r => deleteSelectedIds.has(r.id) && r.bookingCount > 0);
    if (bookedSel.length > 0) {
      bookedSel.forEach(r => deleteSelectedIds.delete(r.id));
      delRenderContent();
      showAlertPopup('Slots with active bookings cannot be deleted. They have been removed from your selection.', 'Cannot delete booked slots');
      return;
    }
    delAddToQueue();
  }
  function delCloseConfirm() { document.getElementById('delConfirmOverlay').dataset.open = 'false'; }
  function delConfirmAddToQueue() { delCloseConfirm(); delAddToQueue(); }

  function delAddToQueue() {
    const ids = Array.from(deleteSelectedIds);
    if (ids.length === 0) return;
    const pkgEl   = document.getElementById('tourPackage');
    const pkgName = pkgEl.options[pkgEl.selectedIndex] ? pkgEl.options[pkgEl.selectedIndex].text : '';
    const items   = deleteSlots.filter(r => deleteSelectedIds.has(r.id)).map(r => ({ ...r }));
    const numDays = new Set(items.map(r => r.date)).size;
    const bookedCount = items.filter(r => r.bookingCount > 0).length;

    queueCounter++;
    queue.push({
      id: queueCounter,
      pkg: delPkg(), pkgName,
      from: delStepFrom(), to: delStepTo(),
      action: 'delete',
      deleteItems: items,
      bookedCount,
      baseSlots: [], changePoints: [], seatSnapshot: null,
      total: items.length,
      numDays
    });
    renderQueue();
    delToast(`${items.length} slot${items.length!==1?'s':''} added to queue`, 'Deletion runs on Apply All', 'success');

    deleteSelectedIds.clear();
    deleteSlots = [];
    delLastSig = null;
    delPendingPreselect = null;
    selectedAction = null;
    delDeactivate();
    resetForm(true); // keep package + date range so the next op doesn't start disabled
  }

  function delToast(title, msg, type) {
    const toast = document.getElementById('toast');
    const icon  = document.getElementById('toastIcon');
    icon.style.background = type === 'error' ? '#fcebeb' : '#dcfce7';
    icon.innerHTML = type === 'error'
      ? `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#a32d2d" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`
      : `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>`;
    document.getElementById('toastTitle').textContent = title;
    document.getElementById('toastMsg').textContent = msg || '';
    toast.dataset.show = 'true';
    setTimeout(() => toast.dataset.show = 'false', 3000);
  }

  function showAlertPopup(msg, title) {
    document.getElementById('alertMsg').textContent = msg;
    document.getElementById('alertTitle').textContent = title || 'Notice';
    document.getElementById('alertOverlay').dataset.open = 'true';
  }
  function closeAlertPopup() { document.getElementById('alertOverlay').dataset.open = 'false'; }

  init();
