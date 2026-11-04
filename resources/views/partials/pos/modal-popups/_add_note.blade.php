<div class="modal modal-small fade" id="addInfoModal" tabindex="-1" aria-labelledby="addInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="cancelBookingModalLabel">Add Info</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal Body -->
            <div class="modal-body">
                <div class="row">
                    <nav class="nav nav-tabs tab-list" id="nav-tab" role="tablist">
                    <button type="button" class="nav-item nav-link active" id="nav-contact-info" data-bs-toggle="tab" data-bs-target="#tab-contact-info" role="tab" aria-controls="tab-contact-info" aria-selected="true">Contact Info</button>
                        <button type="button" class="nav-item nav-link" id="nav-note" data-bs-toggle="tab" data-bs-target="#tab-note" role="tab" aria-controls="tab-general" aria-selected="true">Note</button>
                    </nav>
                </div>
                <div class="tab-content">
                    <div class="tab-pane mt-2 active" id="tab-contact-info" role="tabpanel" aria-labelledby="tab-contact-info">
                        <div class="form-container contact-us-wrap">
                            <div class="form-group d-flex justify-content-between align-items-center">
                                <div class="input-group customer-info-input-group">
                                    <input type="text" class="form-control input-no-shadow" id="customer_name" name="customer_name" placeholder="Contact Name">
                                    <span class="input-icon"><i class="fa fa-user" aria-hidden="true"></i></span>
                                </div>
                            </div>
                            <div class="form-group d-flex justify-content-between align-items-center">
                                <div class="input-group">
                                    <input type="tel" class="form-control input-no-shadow" id="customer_phone_number" name="customer_phone_number" placeholder="Contact Number" data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#customer_phone_number')">
                                    <span class="input-icon"><i class="fa fa-phone" aria-hidden="true"></i></span>
                                </div>
                            </div>
                            <div class="form-group d-flex justify-content-between align-items-center">
                                <div class="input-group">
                                    <input type="email" class="form-control input-no-shadow" id="customer_email" name="customer_email" placeholder="Contact Email Address">
                                    <span class="input-icon"><i class="fa fa-envelope-o" aria-hidden="true"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane mt-2" id="tab-note" role="tabpanel" aria-labelledby="tab-note">
                        <!-- Note -->
                        <div>
                            <textarea id="pos-note" class="form-control" rows="3" placeholder="Any important note or comment from booked by person "></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" id="addInfoBtn" class="btn btn-pos-primary">Submit</button>
            </div>
        </div>
    </div>
</div>