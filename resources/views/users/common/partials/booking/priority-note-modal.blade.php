{{-- Priority Note Modal --}}
<div id="priorityNoteModal" tabindex="-1" aria-labelledby="priorityNoteModalLabel" aria-hidden="true"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;overflow-x:hidden;overflow-y:auto;background:rgba(0,0,0,0.6);z-index:10001;">
    <div style="max-width:560px;width:calc(100% - 40px);margin:60px auto;">
        <div class="rounded-xl w-full overflow-hidden" style="background:#fff;box-shadow:0 10px 40px rgba(0,0,0,0.18);">
            {{-- Modal Header --}}
            <div class="px-6 py-4 flex items-center justify-between" style="border-bottom:1px solid #E5E7EB;">
                <h4 class="text-lg font-bold" style="color:#111827;margin:0;" id="priorityNoteModalLabel">
                    Priority Note
                </h4>
                <button type="button" class="text-xl leading-none" style="color:#9CA3AF;background:none;border:none;cursor:pointer;"
                        onmouseover="this.style.color='#374151'" onmouseout="this.style.color='#9CA3AF'"
                        onclick="closePriorityNoteModal()" aria-label="Close">
                    &times;
                </button>
            </div>

            {{-- Modal Body --}}
            <div class="px-6 py-5">
                {{-- Note Textarea --}}
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2" style="color:#6B7280;">Note</label>
                    <textarea id="priorityNoteText" rows="4"
                        class="w-full rounded-lg px-3 py-2 text-sm resize-y"
                        style="border:1px solid #D1D5DB;color:#111827;outline:none;"
                        onfocus="this.style.borderColor='#DC2626'" onblur="this.style.borderColor='#D1D5DB'"
                        placeholder="Enter priority note..."></textarea>
                </div>

                {{-- Popup Toggle --}}
                <div id="priorityNotePopupToggle"
                     class="flex items-center rounded-lg px-3 py-3 cursor-pointer"
                     style="gap:10px;background:#FFF7ED;border:1.5px solid #F59E0B;"
                     onclick="togglePriorityNotePopup()">
                    <input type="checkbox" id="priorityNotePopup" class="cursor-pointer" style="width:16px;height:16px;flex-shrink:0;accent-color:#DC2626;"
                        {{ (!empty($priorityNote) && $priorityNote->charge_type == '1') ? 'checked' : '' }}
                        onclick="event.stopPropagation()">
                    <div>
                        <span class="text-sm font-semibold" style="color:#111827;">Pop up when booking is opened</span>
                        <p class="text-xs" style="color:#6B7280;margin-top:2px;margin-bottom:0;">Anyone who opens this booking will see this note immediately as an alert</p>
                    </div>
                </div>

                {{-- Set By Information --}}
                @if (!empty($priorityNote))
                    <div class="mt-4 flex items-center text-xs" style="gap:8px;color:#6B7280;" id="priorityNoteSetBy">
                        @if (!empty($priorityNote->set_by_photo))
                            <img src="{{ $priorityNote->set_by_photo }}" alt="{{ $priorityNote->set_by_name }}"
                                 style="width:32px;height:32px;border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid #E5E7EB;">
                        @else
                            <span style="width:32px;height:32px;border-radius:50%;background:#E5E7EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="fas fa-user text-xs" style="color:#9CA3AF;"></i>
                            </span>
                        @endif
                        <span>Set by <strong style="color:#374151;">{{ $priorityNote->set_by_name }}</strong>
                            &bull; {{ \Carbon\Carbon::parse($priorityNote->operation_at)->format('M j, Y g:i A') }}
                        </span>
                    </div>
                @else
                    <div id="priorityNoteSetBy"></div>
                @endif
            </div>

            {{-- Modal Footer --}}
            <div class="px-6 py-4 flex items-center justify-end" style="border-top:1px solid #E5E7EB;gap:12px;">
                <button type="button" class="px-5 py-2 text-sm font-medium rounded-lg"
                        style="color:#374151;background:#fff;border:1px solid #D1D5DB;cursor:pointer;"
                        onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='#fff'"
                        onclick="closePriorityNoteModal()">Cancel</button>
                <button type="button" id="savePriorityNoteBtn"
                        class="px-5 py-2 text-sm font-medium rounded-lg text-white"
                        style="background:#DC2626;border:none;cursor:pointer;"
                        onmouseover="this.style.background='#B91C1C'" onmouseout="this.style.background='#DC2626'"
                        onclick="savePriorityNote()">
                    Save Priority Note
                </button>
            </div>
        </div>
    </div>
</div>
{{-- END: Priority Note Modal --}}
