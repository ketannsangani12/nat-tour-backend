<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="createPaymentLink" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog max-w-[440px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h4 class="modal-title text-lg font-semibold text-[#111827]">Create Payment Link</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
			</div>

			<div class="modal-body py-7 px-5">
                <form method="post" id="payment-link-form">
                    <div class="mb-5">
                        <label for="pl_amount" class="form-label flex mb-1 form-custom-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" name="pl_amount" min="0" step="0.01" class="form-control form-custom-input pl-amount" value="" readonly placeholder="Enter Amount" required>
                    </div>
                    <div class="mb-5">
                        <label for="pl_customer_email" class="form-label flex mb-1 form-custom-label">Customer Email ID <span class="text-danger">*</span></label>
                        <input type="text" name="pl_customer_email" class="form-control form-custom-input pl-customer-email" readonly placeholder="Customer Email ID" value="" required>
                    </div>
                    <div class="mb-5">
                        <label for="pl_customer_mobile_no" class="form-label flex mb-1 form-custom-label">Customer Mobile Number </label>
                        <input type="text" name="pl_customer_mobile_no" class="form-control form-custom-input pl-customer-mobile-no" readonly value="" placeholder="Enter Customer Mobile Number">
                    </div>
                    <div class="mb-5">
                        <label for="pl_reason" class="form-label flex mb-1 form-custom-label">Reason Of Payment <span class="text-danger">*</span></label>
                        <textarea name="pl_reason" rows="4" class="form-control form-custom-input pl-reason" readonly placeholder="Enter Reason Of Payment" required>The reason of create payment link....</textarea>
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-main btn-main-sm create-payment-link btn-primary-small">
                            @if($page == 'create-booking') Book & Send @else Send @endif
                        </button>
                    </div>
                </form>
			</div>
		</div>
	</div>
</div>