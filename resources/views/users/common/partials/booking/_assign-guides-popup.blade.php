<!-- Assign Tour Guides Modal Start -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="assignGuidesModal" tabindex="-1" aria-labelledby="assignGuidesModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-fullscreen max-w-[575px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center"> <!-- modal-lg for better width -->
		<div class="modal-content rounded-xl bg-white w-full overflow-hidden">
			<div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
				<h4 class="text-lg font-semibold text-[#111827]" id="assignGuidesModalLabel">Assign Tour Guides</h4>
				<button type="button" data-bs-dismiss="modal" aria-label="Close" class="btn-close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
			</div>

			<div class="modal-body p-4">
				<form id="assigneGuidesForm" class="needs-validation" novalidate>
					
					<!-- Booking Info -->
					<div class="booking-infomb-3 bg-[#F9FAFB] rounded-md px-4 py-3">
						<h6 class="text-base text-[#020817] mb-2">Booking Details</h6>
						<div class="flex flex-wrap -mx-3" id="booking-info-div">
							<!-- Populated dynamically -->
						</div>
					</div>

					<!-- Guide Options -->
					<div class="h-[292px] overflow-y-auto overflow-x-hidden">
						<div id="guideOptsDiv" class="py-4">
							<div class="flex flex-wrap -mx-3 mb-3 guide-opt">
								<div class="w-full md:w-5/12 max-w-full px-3">
									<label class="form-label form-custom-label">Guide</label>
									<select class="form-select tour-guide form-custom-input" name="tour_guide" required>
										<option value="">Select Guide</option>
										@foreach($tourGuides as $guide)
											<option value="{{ $guide->id }}">{{ $guide->name }}</option>
										@endforeach
									</select>
									<div class="invalid-feedback text-sm text-[#A32624] mt-1 hidden">Please select a guide.</div>
								</div>

								<div class="w-full md:w-5/12 max-w-full px-3">
									<label class="form-label form-custom-label">Tourists</label>
									<input type="number" min="1" class="form-control guide-tourists form-custom-input" name="guide_tourists" placeholder="Enter number of tourists" required>
									<div class="invalid-feedback text-sm text-[#A32624] mt-1 hidden">Please enter tourists.</div>
								</div>

								<div class="w-full md:w-1/6 max-w-full px-3">
									<label class="form-label form-custom-label w-full">&nbsp;</label>
									<button type="button" class="btn btn-primary btn-sm addGuideOptbtn flex-shrink-0 btn-primary-small">
										<i class="fas fa-plus me-1"></i>Add
									</button>
								</div>
							</div>
						</div>
					</div>

					<!-- Assigned Guides Preview -->
					<div class="selected-guides-preview mb-3 bg-[#F9FAFB] rounded-md px-4 py-3">
						<h6 class="text-base text-[#020817] mb-2"><i class="fas fa-eye me-2"></i> Assigned Guides</h6>
						<div id="selectedGuidesPreview" class="flex flex-wrap gap-2 text-sm text-[#020817]"></div>
					</div>

					<!-- Remaining Tourists Info -->
					<div id="remainingTouristsInfo" class="text-end small text-muted my-3 text-base text-[#A32624]"></div>

					<!-- Submit Button -->
					<div class="text-end">
						<button type="button" class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-regular" id="assignGuideSubmit">
							<i class="fas fa-save me-2"></i> Submit
						</button>
					</div>

				</form>
			</div>
		</div>
	</div>
</div>
<!-- Assign Tour Guides Modal End -->
