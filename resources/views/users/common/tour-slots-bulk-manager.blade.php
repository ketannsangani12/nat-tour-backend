@extends('layouts.default-2')

@section('main-content')
<div class="bg-[#f0f4f8] min-h-[calc(100vh-82px)] pb-10 text-[#1e293b]">

  <style>
    @keyframes slideIn { from { opacity:0; transform:translateY(-8px);} to {opacity:1; transform:translateY(0);} }
    @keyframes delShimmer { 0% { background-position:100% 0;} 100% { background-position:-100% 0;} }
  </style>

  <!-- Light platform-styled header -->
  <div class="w-full max-w-[1600px] mx-auto px-7 pt-6">
    <h1 class="text-2xl font-bold text-[#1e293b] mb-1">Tour Slots Bulk Manager</h1>
    <p class="text-sm text-[#64748b]">Build a queue of operations with multiple date ranges, schedule changes, and per-slot seat counts.</p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-[1fr_420px] gap-5 max-w-[1600px] mx-auto px-7 py-6 items-start">
    <div>

      <!-- Step 1 -->
      <div class="bg-white rounded-xl border border-[#e2e8f0] shadow-[0_1px_4px_rgba(0,0,0,0.06)] mb-5">
        <div class="px-[22px] py-4 border-b border-[#f1f5f9] flex items-center gap-2.5">
          <div class="w-[34px] h-[34px] rounded-lg flex items-center justify-center flex-shrink-0" style="background:#e0f2fe;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#0369a1" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          </div>
          <div><h2 class="text-sm font-bold text-[#1e293b]">Step 1 — Tour Package &amp; Date Range</h2><p class="text-xs text-[#64748b] mt-px font-medium">Which tour and when?</p></div>
        </div>
        <div class="p-[22px]">
          <div class="grid gap-[14px] grid-cols-1 sm:grid-cols-2">
            <div class="flex flex-col gap-1.5">
              <label class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Tour Package</label>
              <div class="group relative" id="pkgSelectWrap" data-open="false">
                <select id="tourPackage" onchange="updatePreview()" style="display:none;">
                  <option value="" selected>Select Tour Package</option>
                  @foreach($tourPackages as $tp)
                  <option value="{{ $tp->id }}">{{ $tp->name }}</option>
                  @endforeach
                </select>
                <button type="button" id="pkgTrigger" data-placeholder="true" onclick="togglePkgDropdown(event)"
                  class="group flex items-center justify-between gap-2 border-[1.5px] border-[#e2e8f0] rounded-lg px-3 py-[9px] h-10 w-full bg-white outline-none cursor-pointer text-sm font-medium text-[#1e293b] text-left transition-[border-color,box-shadow] duration-150 hover:border-[#cbd5e1] data-[placeholder=true]:text-[#94a3b8]">
                  <span class="overflow-hidden text-ellipsis whitespace-nowrap" id="pkgTriggerLabel">Select Tour Package</span>
                  <svg class="flex-shrink-0 text-[#94a3b8] transition-transform duration-150 group-data-[open=true]:rotate-180" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="hidden absolute top-[calc(100%+4px)] left-0 right-0 z-[60] bg-white border-[1.5px] border-[#e2e8f0] rounded-lg shadow-[0_8px_24px_rgba(0,0,0,0.12)] p-1.5" id="pkgPanel">
                  <div class="relative mb-1.5">
                    <svg class="absolute left-[9px] top-1/2 -translate-y-1/2 text-[#94a3b8] pointer-events-none" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="pkgSearch" placeholder="Search packages..." autocomplete="off"
                      class="w-full border-[1.5px] border-[#e2e8f0] rounded-md py-[7px] pr-2.5 pl-[30px] text-[13px] font-medium text-[#1e293b] outline-none focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]"
                      oninput="filterPkgOptions(this.value)" onkeydown="pkgSearchKeydown(event)" onclick="event.stopPropagation()">
                  </div>
                  <div class="max-h-[200px] overflow-y-auto flex flex-col gap-px" id="pkgOptions"></div>
                </div>
              </div>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Date Range</label>
              <input type="text" id="dateRange" placeholder="Select date range" autocomplete="off" readonly
                class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 py-[9px] text-sm font-medium text-[#1e293b] bg-white outline-none transition-[border-color,box-shadow] duration-150 h-10 w-full cursor-pointer focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]">
              <input type="hidden" id="fromDate">
              <input type="hidden" id="toDate">
            </div>
          </div>
        </div>
      </div>

      <!-- Step 2 -->
      <div class="bg-white rounded-xl border border-[#e2e8f0] shadow-[0_1px_4px_rgba(0,0,0,0.06)] mb-5">
        <div class="px-[22px] py-4 border-b border-[#f1f5f9] flex items-center gap-2.5">
          <div class="w-[34px] h-[34px] rounded-lg flex items-center justify-center flex-shrink-0" style="background:#fef3c7;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          </div>
          <div><h2 class="text-sm font-bold text-[#1e293b]">Step 2 — Time Slots &amp; Schedule Changes</h2><p class="text-xs text-[#64748b] mt-px font-medium">Base slots for the full range, plus any mid-range additions</p></div>
        </div>
        <div class="p-[22px]">
          <!-- Mode toggle -->
          <div class="inline-flex bg-[#f1f5f9] rounded-[20px] p-[3px] gap-0.5 mb-4">
            <button id="s2BtnCreate" data-active="false" onclick="switchS2Mode('create')" @cannot('tour-slots-bulk-create-slots') style="display:none;" @endcannot class="px-4 py-[5px] rounded-2xl border-0 text-xs font-bold cursor-pointer bg-transparent text-[#64748b] transition-all duration-150 whitespace-nowrap hover:text-[#334155] data-[active=true]:bg-white data-[active=true]:text-[#1e3a5c] data-[active=true]:shadow-[0_1px_3px_rgba(0,0,0,0.12)]">Create Slots</button>
            <button id="s2BtnSelect" data-active="true" onclick="switchS2Mode('select')" class="px-4 py-[5px] rounded-2xl border-0 text-xs font-bold cursor-pointer bg-transparent text-[#64748b] transition-all duration-150 whitespace-nowrap hover:text-[#334155] data-[active=true]:bg-white data-[active=true]:text-[#1e3a5c] data-[active=true]:shadow-[0_1px_3px_rgba(0,0,0,0.12)]">Select Existing</button>
          </div>

          <!-- SELECT EXISTING mode -->
          <div id="s2SelectMode">
            <div class="flex items-center justify-between mb-2">
              <div class="flex items-center gap-2.5 flex-wrap">
                <span id="selectedSlotCount" class="text-xs font-semibold text-[#64748b]">0 base slots selected</span>
                <span id="baseDateRange" style="display:none;" class="items-center gap-[7px] bg-[#e0f2fe] rounded-[20px] px-4 py-1.5 text-sm font-bold text-[#0369a1] border-[1.5px] border-[#bae6fd]">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <span id="baseDateRangeText"></span>
                </span>
              </div>
              <div class="flex gap-3">
                <button onclick="selectAllSlots()" class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]">Select All</button>
                <button onclick="clearAllSlots()" class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]">Clear All</button>
              </div>
            </div>
            <div class="grid grid-cols-[repeat(auto-fill,minmax(120px,1fr))] gap-[7px]" id="slotsGrid"></div>
          </div>

          <!-- CREATE SLOTS mode -->
          <div id="s2CreateMode" style="display:none;">
            <div class="flex items-center justify-between flex-wrap gap-2.5 mb-2.5">
              <span class="text-xs font-bold text-[#1e293b]">Time slots to create</span>
              <div class="flex items-center gap-2">
                <span class="text-[11px] font-semibold text-[#475569] whitespace-nowrap">Set seats for all:</span>
                <input type="number" id="s2BulkSeats" min="1" max="999" placeholder="Enter seats" class="w-[100px] border-[1.5px] border-[#fbbf24] rounded-md px-2 py-[5px] text-[13px] font-bold text-[#1e293b] outline-none bg-white h-[30px] placeholder:text-[#cbd5e1] placeholder:font-medium focus:border-[#d97706]">
                <button onclick="s2ApplySeatsToAll()" class="px-2.5 py-[5px] bg-[#ed9c45] text-white border-0 rounded-md text-[11px] font-bold cursor-pointer h-[30px] whitespace-nowrap hover:bg-[#d58c3e]">Apply to All</button>
              </div>
            </div>
            <div class="border border-[#e2e8f0] rounded-lg overflow-hidden">
              <table class="w-full border-collapse">
                <thead class="bg-[#f8fafc]">
                  <tr>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap">From Time</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap">To Time</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-[88px]">Seats</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-[140px]">Bookable Status</th>
                    <th class="px-3 py-[9px] text-center text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-[70px]">Hidden</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-10"></th>
                  </tr>
                </thead>
                <tbody id="s2SlotRows"></tbody>
              </table>
            </div>

            <div class="mt-2.5">
              <button onclick="s2AddRow()" class="inline-flex items-center gap-[7px] bg-[#0d9488] text-white border-0 rounded-lg px-4 py-[9px] text-[13px] font-bold cursor-pointer transition-colors duration-150 hover:bg-[#0f766e]">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add More Time Slot
              </button>
            </div>

            <hr class="border-0 border-t border-dashed border-[#e2e8f0] my-4 mb-[14px]">

            <div class="grid grid-cols-1 gap-[14px] mt-[14px]">
              <div class="flex flex-col gap-1.5">
                <label class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Days</label>
                <div class="group relative" id="s2DaysDD" data-open="false">
                  <div class="flex flex-wrap items-center gap-[5px] border-[1.5px] border-[#e2e8f0] rounded-lg px-2 py-[5px] min-h-10 bg-white transition-[border-color] duration-150 cursor-pointer focus-within:border-[#1e3a5c] focus-within:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]" id="s2DaysBox" tabindex="0" onclick="s2ToggleDaysPanel(event)">
                    <svg class="ml-auto flex-shrink-0 text-[#94a3b8] transition-transform duration-150 group-data-[open=true]:rotate-180" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="6 9 12 15 18 9"/></svg>
                  </div>
                  <div class="hidden absolute top-[calc(100%+4px)] left-0 right-0 z-40 bg-white border-[1.5px] border-[#e2e8f0] rounded-lg shadow-[0_8px_24px_rgba(0,0,0,0.12)] p-2" id="s2DaysPanel">
                    <div class="flex gap-3 flex-wrap pt-0.5 px-1 pb-2 border-b border-[#f1f5f9] mb-1.5">
                      <button type="button" onclick="s2SetDayPreset('all')" class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]">All days</button>
                      <button type="button" onclick="s2SetDayPreset('weekdays')" class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]">Weekdays</button>
                      <button type="button" onclick="s2SetDayPreset('weekends')" class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]">Weekends</button>
                      <button type="button" onclick="s2SetDayPreset('none')" class="text-xs font-semibold text-[#1e3a5c] bg-none border-0 cursor-pointer underline p-0 hover:text-[#2563eb]">Clear</button>
                    </div>
                    <div class="flex flex-col gap-px" id="s2DaysOptions"></div>
                  </div>
                </div>
              </div>
            </div>
          </div><!-- end s2CreateMode -->

          <!-- Schedule Change Points — TEMPORARILY HIDDEN (may be used in the future). Remove `hidden` to re-enable. -->
          <div class="hidden mt-4 border-t border-dashed border-[#e2e8f0] pt-4">
            <div class="text-[11px] font-bold text-[#7c3aed] uppercase tracking-[0.6px] flex items-center gap-1.5 mb-3">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
              Schedule Changes within this range
            </div>
            <div class="flex flex-col gap-3 mb-3" id="cpList"></div>
            <button onclick="addChangePoint()" class="inline-flex items-center gap-[7px] px-[14px] py-2 rounded-lg text-xs font-bold cursor-pointer border-[1.5px] border-dashed border-[#d8b4fe] bg-[#faf5ff] text-[#7c3aed] transition-all duration-150 hover:bg-[#f3e8ff] hover:border-[#a78bfa]">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add Schedule Change
            </button>
            <div class="text-[11px] text-[#94a3b8] font-medium mt-2">
              Use this when a new time slot is introduced partway through the date range.
            </div>
          </div>

          <!-- Create mode footer -->
          <div id="s2CreateFooter" style="display:none;" class="mt-[14px] pt-[14px] border-t border-[#f1f5f9] items-center justify-between">
            <span class="text-xs font-semibold text-[#475569]" id="s2PreviewLine">Set a date range in Step 1 to see preview</span>
            <div class="flex gap-2 flex-shrink-0 ml-4">
              <button onclick="s2Reset()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer border-0 transition-all duration-150 bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] hover:bg-[#f8fafc] hover:border-[#cbd5e1]">Reset Inputs</button>
              <button onclick="s2Preview()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer border-[1.5px] transition-all duration-150 bg-[#2563eb] text-white border-[#2563eb] hover:bg-[#1d4ed8]">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                Preview
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Step 3 -->
      <div class="bg-white rounded-xl border border-[#e2e8f0] shadow-[0_1px_4px_rgba(0,0,0,0.06)] mb-5">
        <div class="px-[22px] py-4 border-b border-[#f1f5f9] flex items-center gap-2.5">
          <div class="w-[34px] h-[34px] rounded-lg flex items-center justify-center flex-shrink-0" style="background:#f3e8ff;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
          </div>
          <div><h2 class="text-sm font-bold text-[#1e293b]">Step 3 — Bulk Action</h2><p class="text-xs text-[#64748b] mt-px font-medium">What to do with selected slots?</p></div>
        </div>
        <div class="p-[22px] pb-[14px]">
          <div id="actionTabs" class="flex gap-[7px] mb-[14px] flex-col sm:flex-row">
            {{-- Repeat tab TEMPORARILY HIDDEN (may be used in the future). Remove `hidden` to re-enable. --}}
            <div id="tabRepeat" data-active="none" onclick="toggleRepeat()" class="hidden flex-1 px-2 py-2 rounded-lg border-2 border-[#e2e8f0] bg-[#f8fafc] cursor-pointer transition-all duration-150 flex items-center gap-[9px] hover:border-[#94a3b8] hover:bg-[#f1f5f9] data-[active=repeat]:border-[#d97706] data-[active=repeat]:bg-[#fffbeb]">
              <div class="w-7 h-7 rounded-md flex-shrink-0 flex items-center justify-center" style="background:#fef3c7;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></div>
              <div class="text-left"><span class="text-xs font-bold text-[#334155] block group-data-[active=repeat]:text-[#d97706]">Repeat</span><span class="text-[10px] text-[#94a3b8] font-medium block">Copy slots to dates</span></div>
            </div>
            @can('tour-slots-bulk-status-visibility')
            <div id="tabStatus" data-active="none" onclick="setAction('status')" class="flex-1 px-2 py-2 rounded-lg border-2 border-[#e2e8f0] bg-[#f8fafc] cursor-pointer transition-all duration-150 flex items-center gap-[9px] hover:border-[#94a3b8] hover:bg-[#f1f5f9] data-[active=status]:border-[#16a34a] data-[active=status]:bg-[#f0fdf4]">
              <div class="w-7 h-7 rounded-md flex-shrink-0 flex items-center justify-center" style="background:#dcfce7;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
              <div class="text-left"><span class="text-xs font-bold text-[#334155] block data-[active=status]:text-[#16a34a]">Status &amp; Visibility</span><span class="text-[10px] text-[#94a3b8] font-medium block">Set status &amp; show/hide</span></div>
            </div>
            @endcan
            @can('tour-slots-bulk-update-seats')
            <div id="tabSeats" data-active="none" onclick="setAction('seats')" class="flex-1 px-2 py-2 rounded-lg border-2 border-[#e2e8f0] bg-[#f8fafc] cursor-pointer transition-all duration-150 flex items-center gap-[9px] hover:border-[#94a3b8] hover:bg-[#f1f5f9] data-[active=seats]:border-[#d97706] data-[active=seats]:bg-[#fffbeb]">
              <div class="w-7 h-7 rounded-md flex-shrink-0 flex items-center justify-center" style="background:#fef3c7;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
              <div class="text-left"><span class="text-xs font-bold text-[#334155] block data-[active=seats]:text-[#d97706]">Update Seats</span><span class="text-[10px] text-[#94a3b8] font-medium block">Edit per-slot counts</span></div>
            </div>
            @endcan
            @can('tour-slots-bulk-convert-times')
            <div id="tabConvert" data-active="none" onclick="setAction('convert')" class="flex-1 px-2 py-2 rounded-lg border-2 border-[#e2e8f0] bg-[#f8fafc] cursor-pointer transition-all duration-150 flex items-center gap-[9px] hover:border-[#94a3b8] hover:bg-[#f1f5f9] data-[active=convert]:border-[#0891b2] data-[active=convert]:bg-[#ecfeff]">
              <div class="w-7 h-7 rounded-md flex-shrink-0 flex items-center justify-center" style="background:#cffafe;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#0891b2" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 014-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 01-4 4H3"/></svg></div>
              <div class="text-left"><span class="text-xs font-bold text-[#334155] block data-[active=convert]:text-[#0891b2]">Convert Times</span><span class="text-[10px] text-[#94a3b8] font-medium block">Remap &amp; move bookings</span></div>
            </div>
            @endcan
            {{-- Visibility is now merged into "Status & Visibility" (per-slot show/hide). --}}
            {{-- Delete tab TEMPORARILY HIDDEN (may be used in the future). Remove `hidden` to re-enable. --}}
            <div id="tabDelete" data-active="none" onclick="toggleDelete()" class="hidden flex-1 px-2 py-2 rounded-lg border-2 border-[#e2e8f0] bg-[#f8fafc] cursor-pointer transition-all duration-150 flex items-center gap-[9px] hover:border-[#94a3b8] hover:bg-[#f1f5f9] data-[active=delete]:border-[#f09595] data-[active=delete]:bg-[#fcebeb]">
              <div class="w-7 h-7 rounded-md flex-shrink-0 flex items-center justify-center" style="background:#fcebeb;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a32d2d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg></div>
              <div class="text-left"><span class="text-xs font-bold text-[#334155] block data-[active=delete]:text-[#791f1f]">Delete</span><span class="text-[10px] text-[#94a3b8] font-medium block">Remove slots</span></div>
            </div>
          </div>

          <!-- Repeat editor -->
          <div class="hidden mb-[14px] p-[14px] bg-[#fffbeb] border-[1.5px] border-[#fde68a] rounded-[10px]" id="repeatEditor">
            <div class="flex items-end gap-4 flex-wrap mb-[14px]">
              <div class="flex flex-col gap-1.5 min-w-[180px]">
                <label class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Repeat From Date *</label>
                <input type="text" id="repeatFromDateTxt" placeholder='Select "From Date"' autocomplete="off" readonly class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 py-[9px] text-sm font-medium text-[#1e293b] bg-white outline-none h-10 w-full cursor-pointer focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]">
                <input type="hidden" id="repeatFromDate">
              </div>
              <div class="flex flex-col gap-1.5 min-w-[180px]">
                <label class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Repeat To Date *</label>
                <input type="text" id="repeatToDateTxt" placeholder='Select "To Date"' autocomplete="off" readonly class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 py-[9px] text-sm font-medium text-[#1e293b] bg-white outline-none h-10 w-full cursor-pointer focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]">
                <input type="hidden" id="repeatToDate">
              </div>
              <div class="flex flex-col gap-1.5 min-w-[180px]">
                <label class="text-[11px] font-bold text-[#475569] uppercase tracking-[0.5px]">Status for All Dates</label>
                <select id="repeatStatusSel" onchange="repeatStatusForAllDates(this.value)" class="border-[1.5px] border-[#e2e8f0] rounded-lg px-3 py-[9px] text-sm font-medium text-[#1e293b] bg-white outline-none h-10 w-full cursor-pointer focus:border-[#1e3a5c] focus:shadow-[0_0_0_3px_rgba(30,58,92,0.1)]">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
            </div>
            <div class="text-[11px] text-[#94a3b8] font-medium flex items-center gap-1.5 pb-[9px]">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
              Rows are loaded from your Step 2 selection · edit times/seats/status or remove rows you don't want to repeat · use Reset to reload from Step 2
            </div>

            <div class="text-sm font-bold text-[#92400e] mb-2.5 mt-[14px]">Slots to Repeat</div>
            <div class="border border-[#e2e8f0] rounded-lg overflow-hidden">
              <table class="w-full border-collapse">
                <thead class="bg-[#f8fafc]">
                  <tr>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap">From Time *</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap">To Time</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-[88px]">Seats</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-[140px]">Booking Status</th>
                    <th class="px-3 py-[9px] text-center text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-[70px]">Hidden</th>
                    <th class="px-3 py-[9px] text-left text-[11px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0] whitespace-nowrap w-10"></th>
                  </tr>
                </thead>
                <tbody id="repeatSlotRows"></tbody>
              </table>
            </div>

            <div class="flex items-center justify-between gap-4 mt-[14px] pt-[14px] border-t border-[#fde68a] flex-wrap">
              <span class="text-xs font-semibold text-[#92400e]" id="repeatPreviewLine">Select slots in Step 2 and set a date range to see preview</span>
              <div class="flex gap-2 flex-shrink-0">
                <button onclick="repeatReset()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">Reset</button>
                <button onclick="repeatAddNewRow()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Add New Slot
                </button>
                <button onclick="repeatAddToQueue()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-[#2563eb] text-white border-[1.5px] border-[#2563eb] transition-all duration-150 hover:bg-[#1d4ed8]">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                  Add to Queue
                </button>
              </div>
            </div>
          </div>

          <!-- DeleteSlotsPanel -->
          <div class="hidden mb-[14px] border-[0.5px] border-[#f09595] rounded-xl overflow-hidden" id="deleteEditor">
            <div class="flex items-center gap-2 px-[14px] py-[11px] bg-[#fcebeb] border-b-[0.5px] border-[#f09595]">
              <div class="text-xs font-bold text-[#791f1f] flex items-center gap-1.5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a32d2d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Delete slots
              </div>
            </div>
            <div class="p-[14px] bg-white">
              <div class="flex items-center gap-[7px] mb-[14px] px-3 py-[9px] bg-[#fff7f7] border border-[#f6d4d4] rounded-lg text-xs font-semibold text-[#791f1f] [&_svg]:flex-shrink-0 [&_svg]:text-[#a32d2d]" id="delInfo"></div>
              <div id="delContent"></div>
              <div class="flex items-center justify-between gap-4 mt-[14px] pt-[14px] border-t border-[#f1f5f9] flex-wrap" id="delBottomBar" style="display:none;">
                <span class="text-xs font-semibold text-[#791f1f] [&_strong]:text-[#5a1717]" id="delPreviewLine"></span>
                <div class="flex gap-2 flex-shrink-0">
                  <button onclick="delClearSelection()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">Clear selection</button>
                  <button id="delBulkBtn" onclick="delBulkDeleteClick()" disabled class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer transition-all duration-150 bg-[#a32d2d] text-white border-[1.5px] border-[#a32d2d] disabled:opacity-40 disabled:cursor-not-allowed [&:hover:not(:disabled)]:bg-[#8a2525] [&:hover:not(:disabled)]:border-[#8a2525]">Delete selected (0)</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Update Status + Visibility selectors (bulk "set all") -->
          <div class="hidden mb-[14px] px-[14px] py-2.5 bg-[#f8fafc] border-[1.5px] border-[#e2e8f0] rounded-[10px]" id="statusModeEditor">
            <div class="flex items-center gap-2.5">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              <span class="text-[13px] font-bold text-[#334155]">Set status for all slots to</span>
              <select id="statusModeSelect" data-val="grey" onchange="onStatusModeChange(this.value)" class="border-[1.5px] rounded-md py-1.5 px-2.5 text-[13px] font-bold outline-none cursor-pointer transition-all duration-150 ml-auto data-[val=green]:bg-[#dcfce7] data-[val=green]:border-[#86efac] data-[val=green]:text-[#16a34a] data-[val=red]:bg-[#fee2e2] data-[val=red]:border-[#fca5a5] data-[val=red]:text-[#dc2626] data-[val=grey]:bg-white data-[val=grey]:border-[#e2e8f0] data-[val=grey]:text-[#475569] focus:border-[#0891b2] focus:shadow-[0_0_0_3px_rgba(8,145,178,0.12)]">
                <option value="" selected>Select Status</option>
                <option value="Open">Open</option>
                <option value="Closed">Closed</option>
                <option value="Only by Phone">Only by Phone</option>
              </select>
            </div>
            <div class="flex items-center gap-2.5 mt-2.5 pt-2.5 border-t border-[#e2e8f0]">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
              <span class="text-[13px] font-bold text-[#334155]">Set visibility for all slots to</span>
              <select id="visModeSelect" data-val="green" onchange="onVisibilityModeChange(this.value)" class="border-[1.5px] rounded-md py-1.5 px-2.5 text-[13px] font-bold outline-none cursor-pointer transition-all duration-150 ml-auto data-[val=green]:bg-[#dcfce7] data-[val=green]:border-[#86efac] data-[val=green]:text-[#16a34a] data-[val=red]:bg-[#fee2e2] data-[val=red]:border-[#fca5a5] data-[val=red]:text-[#dc2626] focus:border-[#0891b2] focus:shadow-[0_0_0_3px_rgba(8,145,178,0.12)]">
                <option value="">Select Visibility</option>
                <option value="true">Show (visible online)</option>
                <option value="false">Hide (not visible)</option>
              </select>
            </div>
          </div>

          <!-- Per-slot status overrides -->
          <div class="hidden mb-[14px] border-[1.5px] border-[#bbf7d0] rounded-[10px] overflow-hidden" id="statusEditor">
            <div class="flex items-center justify-between gap-2.5 px-[14px] py-2.5 bg-[#f0fdf4] border-b border-[#bbf7d0] text-xs font-bold text-[#166534]">
              <span>Status &amp; visibility per time slot</span>
              <span class="text-[11px] font-medium text-[#6b9a78]">Set status and show/hide for specific slots</span>
            </div>
            <div class="bg-white max-h-[260px] overflow-y-auto" id="statusEditorBody"></div>
          </div>

          <!-- Convert editor -->
          <div class="hidden mb-[14px]" id="convertEditor">
            <div id="convertRulesList" class="overflow-x-auto"></div>
          </div>

          <!-- Per-slot seat editor -->
          <div class="hidden" id="seatEditor">
            <div class="flex items-center justify-between px-[14px] py-[11px] bg-[#fffbeb] border-[1.5px] border-[#fde68a] rounded-t-[9px] border-b-0">
              <div class="text-xs font-bold text-[#92400e] flex items-center gap-1.5">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                Seat count per time slot
              </div>
              <div class="flex items-center gap-2">
                <span class="text-[11px] font-semibold text-[#92400e] whitespace-nowrap">Enter seats:</span>
                <input type="number" id="quickFillInput" min="0" max="999" placeholder="Enter seats" class="w-[100px] border-[1.5px] border-[#fbbf24] rounded-md px-2 py-[5px] text-[13px] font-bold text-[#1e293b] outline-none bg-white h-[30px] placeholder:text-[#cbd5e1] placeholder:font-medium focus:border-[#d97706]">
                <button onclick="applyQuickFill()" class="px-2.5 py-[5px] bg-[#ed9c45] text-white border-0 rounded-md text-[11px] font-bold cursor-pointer h-[30px] whitespace-nowrap hover:bg-[#d58c3e]">Apply to All</button>
              </div>
            </div>
            <div class="border-[1.5px] border-[#fde68a] rounded-b-[9px] overflow-hidden mb-[14px]" id="seatEditorBody">
              <div class="p-5 text-center text-xs text-[#94a3b8] font-semibold bg-[#fffdf5]">Select time slots above to set seat counts</div>
            </div>
          </div>

        </div>

        <!-- Preview -->
        <div class="px-[22px] pb-[22px] pt-0 border-t border-[#f1f5f9]">
          <div class="flex items-center justify-between mb-3 mt-[14px]">
            <h3 class="text-[13px] font-bold text-[#1e293b]">Preview</h3>
            <span class="text-[11px] font-semibold px-[9px] py-[3px] rounded-[20px] bg-[#f1f5f9] text-[#475569] data-[has=true]:bg-[#e0f2fe] data-[has=true]:text-[#0369a1]" data-has="false" id="previewCount">0 slots</span>
          </div>
          <div class="border border-[#e2e8f0] rounded-[9px] overflow-hidden max-h-[300px] overflow-y-auto">
            <table class="w-full border-collapse text-xs" id="previewTable">
              <thead id="previewThead">
                <tr><th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0]">Date</th><th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0]">Day</th><th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0]">Time</th><th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0]">Current</th><th class="px-3 py-2 text-left text-[10px] font-bold uppercase tracking-[0.4px] text-[#64748b] border-b border-[#e2e8f0]">Change To</th></tr>
              </thead>
              <tbody id="previewBody">
                <tr><td colspan="5"><div class="py-7 px-4 text-center text-[#94a3b8]">
                  <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mx-auto mb-2 block opacity-35"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                  <p class="text-xs font-semibold">Nothing to preview yet</p><small class="text-[11px]">Complete steps 1–3</small>
                </div></td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="flex items-center justify-between px-[22px] py-[14px] bg-[#f8fafc] border-t border-[#e2e8f0] rounded-b-xl">
          <div class="text-xs text-[#64748b] font-semibold [&_strong]:text-[#1e293b]" id="formFooterInfo">Select tour, dates, slots &amp; action to add to queue</div>
          <div class="flex gap-2">
            <button onclick="resetForm()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
              Clear
            </button>
            <button id="addToQueueBtn" disabled onclick="onAddToQueueClick()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-[#2563eb] text-white border-[1.5px] border-[#2563eb] transition-all duration-150 [&:hover:not(:disabled)]:bg-[#1d4ed8] disabled:opacity-40 disabled:cursor-not-allowed">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
              Add to Queue
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- RIGHT: Queue -->
    <div class="lg:sticky lg:top-5">
      <div class="grid grid-cols-2 gap-2.5 mb-[14px]">
        <div class="bg-white border border-[#e2e8f0] rounded-[10px] px-[14px] py-3 text-center"><div class="text-[22px] font-extrabold text-[#1e293b] leading-none mb-[3px]" id="statQueueItems">0</div><div class="text-[10px] font-semibold text-[#94a3b8] uppercase tracking-[0.4px]">Queued Ops</div></div>
        <div class="bg-white border border-[#e2e8f0] rounded-[10px] px-[14px] py-3 text-center"><div class="text-[22px] font-extrabold text-[#1e293b] leading-none mb-[3px]" id="statQueueTotal">0</div><div class="text-[10px] font-semibold text-[#94a3b8] uppercase tracking-[0.4px]">Total Slots</div></div>
      </div>
      <div id="queueList" class="flex flex-col gap-2.5 mb-[14px]"></div>
      <div id="queueEmpty" class="bg-white border-2 border-dashed border-[#e2e8f0] rounded-xl px-5 py-9 text-center mb-[14px]">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="mx-auto mb-2.5 block opacity-30 text-[#94a3b8]"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
        <p class="text-[13px] font-bold text-[#94a3b8] mb-1">Queue is empty</p>
        <small class="text-xs text-[#cbd5e1] font-medium">Add operations using the form on the left</small>
      </div>
      <div class="bg-white border border-[#e2e8f0] rounded-xl p-4 shadow-[0_1px_4px_rgba(0,0,0,0.06)]">
        <div class="text-xs font-semibold text-[#64748b] mb-2.5 text-center [&_strong]:text-[#1e293b] [&_strong]:text-xl [&_strong]:font-extrabold [&_strong]:block"><strong id="applyAllNum">0</strong>total slots across all operations</div>
        <button id="applyAllBtn" disabled onclick="openConfirm()" class="w-full justify-center inline-flex items-center gap-[7px] p-3 rounded-lg text-sm font-bold cursor-pointer bg-[#16a34a] text-white border-[1.5px] border-[#16a34a] transition-all duration-150 [&:hover:not(:disabled)]:bg-[#15803d] disabled:opacity-40 disabled:cursor-not-allowed">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          Apply All Operations
        </button>
        <button onclick="clearQueue()" class="w-full justify-center inline-flex items-center gap-[7px] p-3 mt-2 rounded-lg text-sm font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          Clear Queue
        </button>
      </div>
    </div>
  </div>

  <!-- History Section -->
  <div class="max-w-[1600px] mx-auto mt-6 px-7 pb-10">
    <div class="flex items-center justify-between mb-[14px]">
      <h2 class="text-base font-extrabold text-[#1e293b] flex items-center gap-2">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Change History
        <span class="bg-[#e2e8f0] text-[#64748b] text-[11px] font-bold px-2 py-0.5 rounded-[20px]" id="historyCount">0</span>
      </h2>
      <div class="flex items-center gap-2">
        <button onclick="clearHistory()" class="inline-flex items-center gap-[7px] px-2.5 py-[5px] rounded-lg text-[11px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/></svg>
          Clear History
        </button>
      </div>
    </div>
    <div id="historyList" class="flex flex-col gap-2.5"></div>
    <div id="historyEmpty" class="text-center py-9 px-5 text-[#94a3b8] text-[13px] bg-white border-[1.5px] border-dashed border-[#e2e8f0] rounded-xl">
      <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" class="block mx-auto mb-2.5 opacity-35"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      No operations yet.<br>Your operations will appear here with their status.
    </div>
  </div>

  <!-- Confirm Modal -->
  <div class="fixed inset-0 bg-[rgba(15,23,42,0.5)] backdrop-blur-[4px] z-[500] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200 data-[open=true]:opacity-100 data-[open=true]:pointer-events-auto group" data-open="false" id="modalOverlay" onclick="closeConfirmOutside(event)">
    <div class="bg-white rounded-[14px] p-[26px] w-[500px] max-w-[92vw] shadow-[0_20px_60px_rgba(0,0,0,0.2)] scale-95 transition-transform duration-200 max-h-[90vh] overflow-y-auto group-data-[open=true]:scale-100">
      <div class="w-12 h-12 rounded-[11px] flex items-center justify-center mb-[14px]" id="modalIcon"></div>
      <h3 class="text-base font-extrabold text-[#1e293b] mb-1.5" id="modalTitle">Confirm All Operations</h3>
      <p class="text-[13px] text-[#64748b] font-medium leading-relaxed mb-4" id="modalDesc"></p>
      <div id="modalPendingWarn" style="display:none;" class="mb-3"></div>
      <div class="flex flex-col gap-2 mb-4" id="modalQueueList"></div>
      <div class="flex gap-2.5 justify-end">
        <button onclick="closeConfirm()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">Cancel</button>
        <button onclick="applyAll()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-[#16a34a] text-white border-[1.5px] border-[#16a34a] transition-all duration-150 hover:bg-[#15803d]">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
          Confirm &amp; Apply All
        </button>
      </div>
    </div>
  </div>

  <!-- Generic alert popup -->
  <div class="fixed inset-0 bg-[rgba(15,23,42,0.5)] backdrop-blur-[4px] z-[500] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200 data-[open=true]:opacity-100 data-[open=true]:pointer-events-auto group" data-open="false" id="alertOverlay" onclick="if(event.target===this)closeAlertPopup()">
    <div class="bg-white rounded-[14px] p-[26px] max-w-[420px] w-full shadow-[0_20px_60px_rgba(0,0,0,0.2)] scale-95 transition-transform duration-200 max-h-[90vh] overflow-y-auto group-data-[open=true]:scale-100">
      <div class="w-12 h-12 rounded-[11px] flex items-center justify-center mb-[14px]" style="background:#fff7ed;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <h3 class="text-base font-extrabold text-[#1e293b] mb-1.5" id="alertTitle">Notice</h3>
      <p class="text-[13px] text-[#64748b] font-medium leading-relaxed mb-4" id="alertMsg"></p>
      <div class="flex gap-2.5 justify-end">
        <button onclick="closeAlertPopup()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer text-white" style="background:#1e3a5c;">OK</button>
      </div>
    </div>
  </div>

  <!-- Queue slot-list popup -->
  <div class="fixed inset-0 bg-[rgba(15,23,42,0.5)] backdrop-blur-[4px] z-[500] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200 data-[open=true]:opacity-100 data-[open=true]:pointer-events-auto group" data-open="false" id="slotListOverlay" onclick="if(event.target===this)qlClose()">
    <div class="bg-white rounded-[14px] p-[26px] max-w-[680px] w-[680px] shadow-[0_20px_60px_rgba(0,0,0,0.2)] scale-95 transition-transform duration-200 max-h-[90vh] overflow-y-auto group-data-[open=true]:scale-100">
      <div class="flex items-center justify-between gap-3 mb-[14px]">
        <h3 class="text-base font-extrabold text-[#1e293b] m-0" id="slotListTitle">Selected slots</h3>
        <button onclick="qlClose()" title="Close" class="flex-shrink-0 w-7 h-7 rounded-md flex items-center justify-center cursor-pointer border-[1.5px] border-[#e2e8f0] bg-white text-[#64748b] transition-all duration-150 hover:bg-[#fee2e2] hover:border-[#fca5a5] hover:text-[#dc2626]">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
      <div id="slotListBody"></div>
    </div>
  </div>

  <!-- Delete-with-bookings confirmation -->
  <div class="fixed inset-0 bg-[rgba(15,23,42,0.5)] backdrop-blur-[4px] z-[500] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200 data-[open=true]:opacity-100 data-[open=true]:pointer-events-auto group" data-open="false" id="delConfirmOverlay" onclick="if(event.target===this)delCloseConfirm()">
    <div class="bg-white rounded-[14px] p-[26px] max-w-[440px] w-full shadow-[0_20px_60px_rgba(0,0,0,0.2)] scale-95 transition-transform duration-200 max-h-[90vh] overflow-y-auto group-data-[open=true]:scale-100">
      <div class="w-12 h-12 rounded-[11px] flex items-center justify-center mb-[14px]" style="background:#fcebeb;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#a32d2d" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <h3 class="text-base font-extrabold text-[#1e293b] mb-1.5">Queue deletion of slots with active bookings?</h3>
      <p class="text-[13px] text-[#64748b] font-medium leading-relaxed mb-4" id="delConfirmDesc"></p>
      <div class="flex gap-2.5 justify-end">
        <button onclick="delCloseConfirm()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">Cancel</button>
        <button onclick="delConfirmAddToQueue()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-[#a32d2d] text-white border-[1.5px] border-[#a32d2d] transition-all duration-150 [&:hover:not(:disabled)]:bg-[#8a2525]">Add to queue anyway</button>
      </div>
    </div>
  </div>

  <!-- Incomplete config warning -->
  <div class="fixed inset-0 bg-[rgba(15,23,42,0.5)] backdrop-blur-[4px] z-[500] flex items-center justify-center opacity-0 pointer-events-none transition-opacity duration-200 data-[open=true]:opacity-100 data-[open=true]:pointer-events-auto group" data-open="false" id="warnOverlay" onclick="closeWarnOutside(event)">
    <div class="bg-white rounded-[14px] p-[26px] max-w-[420px] w-full shadow-[0_20px_60px_rgba(0,0,0,0.2)] scale-95 transition-transform duration-200 max-h-[90vh] overflow-y-auto group-data-[open=true]:scale-100">
      <div class="w-12 h-12 rounded-[11px] flex items-center justify-center mb-[14px]" style="background:#fff7ed;">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <h3 class="text-base font-extrabold text-[#1e293b] mb-1.5" id="warnTitle">Incomplete Configuration</h3>
      <p class="text-[13px] text-[#64748b] font-medium leading-relaxed mb-2.5" id="warnDesc">The following items haven't been fully set up. Do you want to add to queue anyway?</p>
      <ul id="warnList" class="m-0 mb-4 text-[13px] text-[#92400e] leading-[1.8] bg-[#fffbeb] border-[1.5px] border-[#fde68a] rounded-lg py-2.5 pr-2.5 pl-7 list-disc"></ul>
      <div class="flex gap-2.5 justify-end">
        <button onclick="closeWarnModal()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer bg-white border-[1.5px] border-[#e2e8f0] text-[#475569] transition-all duration-150 hover:bg-[#f8fafc] hover:border-[#cbd5e1]">Go Back</button>
        <button onclick="proceedAddToQueue()" class="inline-flex items-center gap-[7px] px-4 py-[9px] rounded-lg text-[13px] font-bold cursor-pointer text-white" style="background:#d97706;">Add Anyway</button>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="fixed bottom-6 right-6 bg-[#1e293b] text-white px-[18px] py-3 rounded-[10px] text-[13px] font-semibold flex items-center gap-2.5 shadow-[0_8px_32px_rgba(0,0,0,0.25)] translate-y-[80px] opacity-0 transition-all duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] z-[1000] max-w-[340px] data-[show=true]:translate-y-0 data-[show=true]:opacity-100" data-show="false" id="toast">
    <div class="w-[26px] h-[26px] rounded-full flex items-center justify-center flex-shrink-0" id="toastIcon"></div>
    <div>
      <div id="toastTitle" class="font-bold mb-px"></div>
      <div id="toastMsg" class="text-xs font-medium opacity-75"></div>
    </div>
  </div>

</div>
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
@else
<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
@endif
<link href="{{ asset('css/user-common/jquery-ui-custom-calendar.css') }}" rel="stylesheet" />
<link href="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.css" rel="stylesheet" type="text/css" />
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
@else
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
@endif
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
<script src="//cdn.jsdelivr.net/bootstrap.daterangepicker/2/daterangepicker.js"></script>
@endsection

@section('js-custom')
<script>
  window.BULK_MGR_CONFIG = {
    distinctSlotTimesUrl: "{{ route(routePrefix() . '.slot.distinct-slot-times') }}",
    slotsInRangeUrl: "{{ route(routePrefix() . '.slot.slots-in-range') }}",
    storeOperationUrl: "{{ route(routePrefix() . '.slot.bulk-operation.store') }}",
    loadOperationsUrl: "{{ route(routePrefix() . '.slot.bulk-operations') }}",
    loadAppliedOperationsUrl: "{{ route(routePrefix() . '.slot.bulk-operations.applied') }}",
    clearHistoryUrl: "{{ route(routePrefix() . '.slot.bulk-operations.clear-history') }}",
    updateOperationUrl: "{{ route(routePrefix() . '.slot.bulk-operation.update', ['operation' => '__OP_ID__']) }}",
    deleteOperationUrl: "{{ route(routePrefix() . '.slot.bulk-operation.destroy', ['operation' => '__OP_ID__']) }}",
    applyOperationsUrl: "{{ route(routePrefix() . '.slot.bulk-operations.apply') }}"
  };
</script>
<script src="{{ asset('js/user-common/tour-slots-bulk-manager.js') }}?v={{ @filemtime(public_path('js/user-common/tour-slots-bulk-manager.js')) ?: time() }}"></script>
@endsection
