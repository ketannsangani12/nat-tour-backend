{{-- View Pax Addons Modal --}}
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="paxAddonsListModal" tabindex="-1" aria-labelledby="paxAddonsListLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden">

      <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="paxAddonsListLabel">Passenger Add-ons</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
      </div>

      <div class="modal-body p-4" style="max-height:800px; overflow-x:auto;">
        <div class="pax-addons-list"></div>
      </div>

      <div class="modal-footer pb-4 px-4">
        <button class="btn btn-primary btn-primary-small delete-or-split-pax-addons">Update & Confirm Booking</button>
      </div>
    </div>
  </div>
</div>

{{-- Add Pax Addons Form Modal --}}
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="addPaxAddonsModal" tabindex="-1" aria-labelledby="addPaxAddonsLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden">

      <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
        <h5 class="modal-title text-lg font-semibold text-[#111827]" id="addPaxAddonsLabel"><span class="pax-addons-event">Add</span> Passenger Add-ons</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
      </div>

      <div class="modal-body p-4" style="max-height:800px; overflow-x:auto;">
        <div id="add-pax-addons-form-container"></div>
      </div>

      <div class="modal-footer pb-4 px-4 flex justify-end gap-2">
        <button type="button" class="btn btn-primary btn-primary-small save-pax-addons">Save Add-ons</button>
        <button type="button" class="btn btn-outline-secondary btn-light-small" data-bs-dismiss="modal">Cancel</button>
      </div>
    </div>
  </div>
</div>