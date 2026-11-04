<!-- Pax Addons | One Time | Per Unit Addons Modal Start -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="paxAddonsModal" tabindex="-1" aria-labelledby="paxAddonsLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full max-h-[calc(100vh-40px)]" >

      <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="paxAddonsLabel">Add-ons</h5>
        <button type="button" class="btn-close btn-cancel-addon" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
      </div>

      <div class="modal-body p-4">
        <div class="flex flex-wrap -mx-3" style="align-items:flex-start; max-height:calc(100vh - 200px); overflow-y:auto;">
          <!-- Left -->
          <div class="left w-full md:w-1/2 max-w-full px-3" id="paxSection">
            <div class="pax-list"></div>
            <div class="addons-content" id="addonFields"></div>
          </div>

          <!-- Right: just the pax detail cards. No special height/flex/scroll styling —
               it renders at its own natural height, same as .left does, so it can never
               fight the summary panel below for space. -->
          <div class="right w-full md:w-1/2 max-w-full px-3">
            <div id="paxDetails"></div>
          </div>

          <!-- Add-ons Summary: pulled out of .right entirely and given its own row here,
               spanning the FULL width of the modal body (both .left and .right's combined
               width), not just the width of one column. Three earlier attempts tried to
               keep it nested inside .right and make IT solve the "stay at the bottom"
               problem via matched heights, a duplicated height calc, or position:sticky —
               all of them still left it fighting #paxDetails for space in the same box.
               Simplest fix: it isn't in that box any more. It just flows after both
               columns, at the bottom of this same scrollable area, full width, full
               height, no cap. -->
          <div class="w-full max-w-full px-3 mt-3">
            <div class="addon-breakdown-panel" style="display:none;"></div>
          </div>
        </div>
      </div>

      <div class="modal-footer p-4 border-t border-[#E5E7EB]" style="display:flex; justify-content:flex-end; gap:10px;">
        <button class="btn btn-primary save-addons btn-primary-small">Save</button>
        <button class="btn btn-secondary btn-cancel-addon btn-light-small" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<!-- Pax Addons | One Time | Per Unit Addons Modal End -->

<!-- One Time | Per Unit Addons Modal Start -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="addonsModal" tabindex="-1" aria-labelledby="addonsLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden">

      <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="addonsLabel">Add-ons</h5>
        <button type="button" class="btn-close btn-cancel-addon" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
      </div>

      <div class="modal-body p-4">
        <div class="addons-content"></div>
        <!-- Summary renders below the addon fields, scrolls naturally with body -->
        <div class="addon-breakdown-panel mt-3" style="display:none"></div>
      </div>

      <div class="modal-footer pb-4 px-4 border-t border-[#E5E7EB]" style="display:flex; justify-content:flex-end; gap:10px;">
        <button class="btn btn-primary save-addons btn-primary-small">Save</button>
        <button class="btn btn-secondary btn-cancel-addon btn-light-small" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>
<!-- One Time | Per Unit Addons Modal End -->
