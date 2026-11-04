<div class="modal modal-small fade" id="managerRequestModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-light">
				<h5 class="modal-title" id="managerRequestModalLabel">Manager Request</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<div class="modal-body">
				<input type="hidden" name="cancel_booking" value="1">
				<div class="d-flex mb-2">
					<select class="form-control" name="manager" id="manager">
						<option disabled selected value="">Select a manager for request</option>
						@foreach($managers as $manager)
						<option value="{{$manager->id}}">{{$manager->name}}</option>
						@endforeach
					</select>
				</div>

				<div class="modal-footer">
				    <button type="button" class="btn btn-pos-primary" id="managerRequestBtn">Request</button>
					<button type="button" class="btn btn-pos-primary" id="requestManualOverrideBtn">Manual Override</button>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Manual Override Start -->
<div class="modal modal-small fade" id="requestManualIdModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header bg-light">
				<h5 class="modal-title" id="managerRequestModalLabel">Manual Override</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<div class="modal-body text-center">
				<div class="form-container contact-us-wrap">
					<div class="form-group d-flex justify-content-between align-items-center">
						<div class="input-group customer-info-input-group">
							<input type="password" class="form-control input-no-shadow" id="manualManagerId" name="manualManagerId" placeholder="Enter Manager Id">
							<span class="input-icon"><i class="fa fa-user" aria-hidden="true"></i></span>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-pos-primary" id="requestManualManagerIdBtn">Next</button>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="modal modal-small fade" id="requestManualPinModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-header">
			<h5 class="modal-title" id="managerRequestModalLabel">Manual Override</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>

			<div class="modal-body text-center">
			<div class="form-container contact-us-wrap">
					<div class="form-group d-flex justify-content-between align-items-center">
						<div class="input-group customer-info-input-group">
							<input type="password" class="form-control input-no-shadow" id="manualManagerPin" name="manualManagerPin" placeholder="Enter Manager PIN">
							<span class="input-icon"><i class="fa fa-user" aria-hidden="true"></i></span>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-pos-primary" id="requestManualManagerPinBtn">Submit</button>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- Manual Override End -->