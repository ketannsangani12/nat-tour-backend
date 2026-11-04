 <div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="paymentLinkDetails" tabindex="-1" aria-labelledby="" aria-hidden="true" data-mdb-backdrop="true" data-focus-on="input:first" data-mdb-keyboard="true" >
    <div class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
        <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
            <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                <h4 class="text-lg font-semibold text-[#111827]">Payment Link Details</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
            </div>
            <div class="modal-body p-4">
                <button class="button btn-primary link-action ml-auto btn-secondary-small" data-type="send-new">Resend</button>
                <h3 class="text-base font-semibold text-[#111827] text-center mb-1">Active Link</h3>
                <div class="max-h-80 overflow-y-auto border border-[#E2E8F0] rounded-lg">
                    <table class="table table-bordered table-striped table-hover table-responsive datatable datatable-filter w-full" id="detailsPaymentLink">
                        <thead>
                            <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                                <th class="text-xs font-medium text-[#374151] p-4">Order Id</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Amount</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Expiry Date</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Override Status</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Status</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Created Date</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Created By</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Action</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">

                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-body p-4">
                <h3 class="text-base font-semibold text-[#111827] text-center mb-1">Inactive Link</h3>
                <div class="max-h-80 overflow-y-auto border border-[#E2E8F0] rounded-lg">
                    <table class="table table-bordered table-striped table-hover table-responsive datatable datatable-filter w-full" id="InActivePaymentLink">
                        <thead>
                            <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                                <th class="text-xs font-medium text-[#374151] p-4">Order Id</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Amount</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Expiry Date</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Override Status</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Status</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Created Date</th>
                                <th class="text-xs font-medium text-[#374151] p-4">Created By</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>  