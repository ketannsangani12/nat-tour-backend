{{-- Priority Note Alert Modal (auto-popup on booking open) --}}
<div id="priorityNoteAlertModal" tabindex="-1" aria-hidden="true"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;overflow-x:hidden;overflow-y:auto;background:rgba(0,0,0,0.65);z-index:10002;">
    <div style="max-width:560px;width:calc(100% - 40px);margin:60px auto;">
        <div class="rounded-xl w-full overflow-hidden" style="background:#fff;box-shadow:0 10px 40px rgba(0,0,0,0.22);">

            {{-- Red Header --}}
            <div class="px-6 py-5" style="background:#DC2626;">
                <div class="flex items-start justify-between">
                    <div class="flex items-start" style="gap:12px;">
                        <i class="fas fa-flag text-white mt-1" style="font-size:18px;flex-shrink:0;"></i>
                        <div>
                            <div class="font-bold text-white" style="font-size:18px;line-height:1.2;">Priority Note</div>
                            <div id="priorityNoteAlertRef" class="text-white text-sm mt-1" style="opacity:0.85;"></div>
                        </div>
                    </div>
                    <span style="background:rgba(255,255,255,0.2);border:1.5px solid rgba(255,255,255,0.7);color:#fff;font-size:11px;font-weight:700;letter-spacing:1px;padding:3px 10px;border-radius:20px;flex-shrink:0;">
                        HIGH
                    </span>
                </div>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5">

                {{-- Warning Banner --}}
                <div class="flex items-center rounded-lg px-4 py-3 mb-4" style="background:#FFFBEB;border:1px solid #FCD34D;gap:10px;">
                    <i class="fas fa-exclamation-triangle" style="color:#D97706;font-size:14px;flex-shrink:0;"></i>
                    <span class="text-sm font-medium" style="color:#92400E;">This note requires your attention before proceeding</span>
                </div>

                {{-- Note Text --}}
                <div class="rounded-lg px-4 py-4 mb-4 text-sm" style="background:#F9FAFB;border:1px solid #E5E7EB;color:#111827;line-height:1.6;">
                    <p id="priorityNoteAlertText" style="margin:0;white-space:pre-wrap;"></p>
                </div>

                {{-- Set By --}}
                <div id="priorityNoteAlertSetBy" class="flex items-center text-xs mb-5" style="gap:10px;color:#6B7280;"></div>

                {{-- Don't show again --}}
                <div id="dontShowToggleBox"
                     class="flex items-center rounded-lg px-4 py-3 cursor-pointer"
                     style="border:1px solid #E5E7EB;gap:12px;background:#fff;"
                     onclick="toggleDontShowNote()">
                    <span id="dontShowIcon" style="width:20px;height:20px;border-radius:50%;border:2px solid #D1D5DB;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;"></span>
                    <span id="dontShowText" class="text-sm" style="color:#6B7280;">Don't show this note again when opening this booking</span>
                    <input type="hidden" id="priorityNoteDontShow" value="0">
                </div>
            </div>

            {{-- Footer --}}
            <div class="px-6 pb-6">
                <button type="button" onclick="continuePriorityNoteAlert()"
                        class="w-full font-semibold rounded-lg text-white"
                        style="background:#16A34A;border:none;cursor:pointer;padding:14px;font-size:15px;"
                        onmouseover="this.style.background='#15803D'" onmouseout="this.style.background='#16A34A'">
                    <i class="fas fa-check mr-2"></i> Continue
                </button>
            </div>

        </div>
    </div>
</div>
{{-- END: Priority Note Alert Modal --}}
