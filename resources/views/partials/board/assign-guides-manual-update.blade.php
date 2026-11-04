<!-- Assign Tour Guides Modal Start -->
<div class="modal fade" id="assignGuidesModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4>Manual Update</h4>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="loadGuideManagement(currentSlotId, currentTimeFrom, currentDate)"></button>
			</div>

			<div class="modal-body">
				<div id="assigned-guides-body">
					<form id="assigneGuidesForm">
						<div class="row">
							<div class="col-12 mb-3">
								<div class="booking-info p-3 bg-light rounded">
									<h6 class="mb-2">Booking Details</h6>
									<div class="row" id="booking-info-div">

									</div>
								</div>
							</div>
						</div>
						<div id="guideOptsDiv">
							<div class="row mb-3 guide-opt align-items-end">
								<div id="guideCapacityInfo" style="margin: 5px 0; font-weight: bold; color:#333;"></div>

								<div class="col-5">
									<label class="form-label" for="tour_guide">Guide</label>
									<select class="form-control tour-guide" name="tour_guide">
										@foreach($tourGuides as $guide)
										<option value="{{ $guide->id }}">{{ $guide->name }}</option>
										@endforeach
									</select>
									<div class="invalid-feedback">Please select guide.</div>
								</div>
								<div class="col-7">
									<label class="form-label" for="guide_tourists">Tourists</label>
									<div class="d-flex align-items-start">
										<input type="number" min="1" style="width: 70%;" class="form-control guide-tourists form-control" name="guide_tourists" placeholder="Please enter tourists" required="">
										<button type="button" class="btn btn-primary btn-sm  addGuideOptbtn" style="margin-left: 7px;">

										<input type="number" min="0" style="width: 70%;" class="form-control guide-infants form-control" name="guide_infants" placeholder="Please enter infants" required="">
										<button type="button" class="btn btn-primary btn-sm  addGuideOptbtn" style="margin-left: 7px;">	
											<i class="fas fa-plus me-1"></i>Add
										</button>
									</div>
									<div class="invalid-feedback">Please enter tourists.</div>

								</div>

							</div>
						</div>
						<div class="row mt-4">
							<div class="col-12">
								<div class="selected-guides-preview p-3 bg-light rounded">
									<h6 class="mb-2">
										<i class="fas fa-eye me-2"></i>
										Assigned Guides
									</h6>
									<div id="selectedGuidesPreview" class="d-flex flex-wrap gap-2">

									</div>
								</div>
							</div>

						</div>
						<hr>
						<div>
							<button type="button" class="btn btn-main btn-main-sm themed-btn short-btn" id="assignGuideSubmit">Submit</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- Assign Tour Guides Modal End -->