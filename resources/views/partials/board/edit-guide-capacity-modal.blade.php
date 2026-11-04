<div class="modal fade" id="editGuideCapacityModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header align-items-center">
                <div class="w-100 text-center">
                    <div class="fw-semibold" style="font-size:1.05rem;">
                        Edit Guide Capacity
                    </div>
                    <div id="editGuideSlotInfo"
                         class="text-success fw-semibold"
                         style="font-size:0.85rem;">
                        <!-- Date | Time injected via JS -->
                    </div>
                </div>

                <button type="button"
                        class="btn-close position-absolute end-0 me-3"
                        data-bs-dismiss="modal">          
                </button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="editGuideId">
                <input type="hidden" id="editSlotId">
                <input type="hidden" id="editStationId">
                <input type="hidden" id="editTimeFrom">
                <input type="hidden" id="editDate">

                <!-- Guide name + capacity row -->
                <div class="d-flex align-items-center justify-content-between">

                    <!-- Guide Name -->
                    <div>
                        <span id="editGuideName"
                              class="fw-semibold text-primary"
                              style="font-size:1.05rem;">
                            Guide Name
                        </span>
                    </div>

                    <!-- Capacity Stepper -->
                    <div class="d-flex align-items-center gap-2">
                        <div class="d-flex justify-content-center align-items-center gap-3">
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm capacity-minus">−</button>

                            <span class="capacity-display fw-bold"
                                  style="min-width:40px; text-align:center;">
                                15
                            </span>

                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm capacity-plus">+</button>

                        </div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-primary" id="saveGuideCapacityBtn">Save</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>
