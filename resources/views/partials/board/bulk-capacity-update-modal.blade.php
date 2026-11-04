<div class="modal fade" id="bulkCapacityModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">

            <!-- Header -->
            <div class="modal-header align-items-center">
                <div class="w-100 text-center">
                    <div class="fw-semibold" style="font-size:1.05rem;">
                        Bulk Update Capacity
                    </div>
                    <div id="bulkCapacitySlotInfo"
                         class="text-success fw-semibold"
                         style="font-size:0.85rem;">
                    </div>
                </div>

                <button type="button"
                        class="btn-close position-absolute end-0 me-3"
                        data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body text-center">

                <input type="hidden" id="bulkSlotId">
                <input type="hidden" id="bulkDate">
                <input type="hidden" id="bulkTimeFrom">

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

            <!-- Footer -->
            <div class="modal-footer">
                <button class="btn btn-primary" id="saveBulkCapacityBtn">
                    Save
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>

        </div>
    </div>
</div>
