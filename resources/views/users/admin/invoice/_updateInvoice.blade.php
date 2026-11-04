<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="modal-invoice-status" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden h-full">
            <form method="post" action="" id="form-invoice-status">
                @method('patch')
                @csrf
                <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
                    <h5 class="text-lg font-semibold text-[#111827]" id="invoice-status-update-msg">Update Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
                </div>

                <div class="modal-body p-4">
                    <div class="">
                        <label for="invoice-status" class="form-label block text-sm font-normal text-[#020817] mb-1">Status</label>
                        <select name="status" id="invoice-status" class="form-control form-custom-input" required>
                            @foreach(config('constants.invoice_status') as $statusKey => $statusValue)
                            <option value="{{ $statusKey }}">{{ $statusValue }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" id="invoice-async-form-id" value="0" />
                    </div>
                </div>

                <div class="modal-footer px-4 pb-4">
                    <button type="submit" class="btn btn-main btn-main-sm btn-primary-small w-full">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>