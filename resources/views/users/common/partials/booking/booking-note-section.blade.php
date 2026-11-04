<input type="hidden" name="note_type" id="note_type_input" value="note">
<div class="form-group col-12">
    <div class="mb-2" style="display:flex;align-items:center;gap:28px;">
        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:15px;font-weight:500;color:#374151;margin:0;">
            <input type="radio" name="note_type_selector" value="note" checked
                   style="accent-color:#111827;width:18px;height:18px;cursor:pointer;flex-shrink:0;"
                   onchange="switchNoteTab('note')">
            Note
        </label>
        <label style="display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:15px;font-weight:500;color:#374151;margin:0;">
            <input type="radio" name="note_type_selector" value="priority"
                   style="accent-color:#DC2626;width:18px;height:18px;cursor:pointer;flex-shrink:0;"
                   onchange="switchNoteTab('priority')">
            <i class="fas fa-flag" style="color:#DC2626;font-size:12px;"></i> Priority Note
        </label>
    </div>

    <div id="noteSection">
        <textarea name="tour_operator_note" id="tour_operator_note"
                  class="agent-notes w-full border border-solid border-[#D5D5D5] rounded-lg resize-none p-4 min-h-16 font-light text-base outline-none shadow-none"
                  placeholder="Any important note or comment from booked by person, ex: booked via phone, etc ..."></textarea>
    </div>

    <div id="priorityNoteSection" style="display:none;">
        <textarea name="priority_note" id="new_booking_priority_note"
                  class="w-full rounded-lg resize-none p-4 min-h-16 font-light text-base outline-none shadow-none"
                  style="border:1px solid #D5D5D5;"
                  onfocus="this.style.borderColor='#DC2626'" onblur="this.style.borderColor='#D5D5D5'"
                  placeholder="Enter priority note..."></textarea>
        <div id="newBookingPopupToggle"
             class="flex items-center rounded-lg px-4 py-3 cursor-pointer mt-2"
             style="gap:12px;background:#FFF7ED;border:1.5px solid #F59E0B;"
             onclick="toggleNewBookingPopup()">
            <input type="checkbox" id="new_booking_priority_popup" name="priority_note_popup" value="1"
                   class="cursor-pointer" style="width:16px;height:16px;accent-color:#DC2626;"
                   onclick="event.stopPropagation()">
            <div>
                <span class="text-sm font-medium" style="color:#374151;">Pop up when booking is opened</span>
                <p class="text-xs" style="color:#6B7280;margin-top:2px;margin-bottom:0;">Anyone who opens this booking will see this note immediately as an alert</p>
            </div>
        </div>
    </div>
</div>
