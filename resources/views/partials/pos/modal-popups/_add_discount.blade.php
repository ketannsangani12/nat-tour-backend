<div class="modal modal-small fade" id="addDiscountModal" tabindex="-1" aria-labelledby="addDiscountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="addDiscountModalLabel">Add Discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal Body -->
            <div class="modal-body">
                <div class="" id="radioButtonContainerId">
                    <div class="form-group mb-2 d-flex align-items-center">
                        <label class="checkbox-wrap mb-0 text-start"> <input type="radio" name="discount_type" id="discount_type_promo_code" value="Promo Code">
                            <span class="checkmark"></span>
                            Promo Code:
                        </label>
                        <div class="discount-code-input diff ms-3">
                            <input type="text" name="discount_promo_code" disabled="" id="discount_promo_code" class="form-control" placeholder="Enter Promo Code">
                            <span class="field-icon per-icon"></span>
                        </div>
                        <input type="hidden" name="is_applied" id="is_applied" value="0">
                    </div>
                    <div class="form-group mb-2 d-flex align-items-center">
                        <label class="checkbox-wrap mb-0"> <input type="radio" name="discount_type" id="discount_type_percentage" value="Percent">
                            <span class="checkmark"></span>
                            Percentage:
                        </label>
                        <div class="discount-code-input ms-3">
                            <input type="number" name="discount_percentage" id="discount_percentage" class="form-control" placeholder="Enter Discount Percentage" min="0" step="0.01" disabled="">
                            <span class="field-icon per-icon"></span>
                        </div>
                    </div>
                    <div class="form-group mb-2 d-flex align-items-center">
                        <label class="checkbox-wrap mb-0"> <input type="radio" name="discount_type" id="discount_type_fixed_money" value="Fixed Money">
                            <span class="checkmark"></span>
                            Amount:
                        </label>
                        <div class="discount-code-input ms-3">
                            <input type="number" name="discount_fixed_money" id="discount_fixed_money" class="form-control" placeholder="Enter Discount Amount" min="0" step="0.01" disabled="">
                            <span class="field-icon"></span>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal Footer -->
            <div class="modal-footer">
                <button type="button" id="applyDiscountBtn" class="btn btn-pos-primary">Apply</button>
            </div>
        </div>
    </div>
</div>