<div class="modal fade" id="refundOrderModal" tabindex="-1" aria-labelledby="refundOrderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 700px !important;">
                <div class="modal-content">
                    <!-- Modal Header -->
                    <div class="modal-header bg-light">
                        <h5 class="modal-title" id="refundOrderModalLabel">Refund Order</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label ">Bookings</label>
                            <div class="row" id="orderBookings">

                            </div>
                        </div>
                        <hr>
                        <!-- Refund Options -->
                        <div class="mb-4 refund-options">
                            <div class="form-check">
                                <input type="radio" name="refundTypeOpt" id="ticketRefundOpt" class="form-check-input refund-type" value="full_refund" checked>
                                <label class="form-check-label" for="ticketRefundOpt">Ticket Refund</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="refundTypeOpt" id="fullRefundOpt" class="form-check-input refund-type" value="100_refund">
                                <label class="form-check-label" for="fullRefundOpt">100% Refund (Weather)</label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="refundTypeOpt" id="noRefundOpt" class="form-check-input refund-type" value="dont_refund">
                                <label class="form-check-label" for="noRefundOpt">Don't Refund</label>
                            </div>
                        </div>

                        <!-- Payment Method -->
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" name="isCash" id="isCash" class="form-check-input is-cash-chkbox" value="Cash">
                                <label class="form-check-label" for="isCash">Cash</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" name="isCash" id="isWire" class="form-check-input is-cash-chkbox" value="Wire Transfer">
                                <label class="form-check-label" for="isWire">Check/Wire</label>
                            </div>
                        </div>

                        <!-- Reason for Cancellation -->
                        <div class="mb-3">
                            <label for="refundNote" class="form-label">Add Note</label>
                            <textarea id="refundNote" class="form-control" rows="3" placeholder="Please specify the reason to refund"></textarea>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="modal-footer">
                        <button type="button" id="refundOrderBtn" class="btn btn-pos-primary">Submit</button>
                    </div>
                </div>
            </div>
        </div>