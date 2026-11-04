class BookingRequestNewBooking {

	/**
	 * This is called from the template blade create booking view for manual override of the booking request
	 * @param {*} props 
	 * @returns 
	 */

	constructor(props) {
		this.props = props;
		this.admin = (props.admin === 'Admin') ? '/admin' : '';
		this.requestType = '';


		$(document).off('click', '#cancelBookingRequestManual').on('click', '#cancelBookingRequestManual', (evt) => {
			this.manualOverRide();
		});
		$(document).off('click', '#request-manual-id').on('click', '#request-manual-id', (evt) => {
			this.checkManagerId();
		})
		$(document).off('click', '#request-manual-pin').on('click', '#request-manual-pin', (evt) => {
			this.checkManagerPin();
		});
	}
	manualOverRide() {
		var user_id = $('#manager_id').val();
		if (user_id == null) {
			$('#manager-id-required').text('manager name is required');
			$('#manager-id-required').addClass('text-[#FF0000]');
			return false;
		}
		$('#manager-id-required').text('');
		$("#requestManualId").modal("show");
		$('#manualManagerId').val('');
		$('#manual-manager-id').text('');
		$('#manual-manager-pin').text('');
	}
	checkManagerId() {
		var user_id = $('#manager_id').val();
		var manager_id = $('#manualManagerId').val();
		if (manager_id == '') {
			$('#manual-manager-id').text('manager id is required');
			return false;
		}
		$.ajax({
			headers: { Accept: 'application/json' },
			type: 'POST',
			dataType: 'json',
			url: manualIdCheck,
			data: { manager_id: manager_id, user_id: user_id },
			success: this.onManagerIdCheckOk.bind(this)
		});
	}
	onManagerIdCheckOk(data) {
		if (data.message == 'Correct') {
			$('#manual-manager-id').text('');
			$("#requestManualId").modal("hide");
			$("#requestManualPin").modal("show");
			$('#manualManagerPin').val('');
		} else {
			$('#manual-manager-id').text('Manager id is incorrect');
			return false;
		}
	}
	checkManagerPin() {
		var manager_pin = $('#manualManagerPin').val();
		if (manager_pin == '') {
			$('#manual-manager-pin').text('manager pin is required');
			return false;
		}
		$('#manual-manager-pin').text('');
		$.ajax({
			headers: { Accept: 'application/json' },
			type: 'POST',
			dataType: 'json',
			url: manualPinCheckBooking,
			data: { manager_pin: manager_pin, request_type: this.requestType },
			success: this.onManagerPinCheckOk.bind(this)
		});
	}
	onManagerPinCheckOk(data) {
		if (data.message == 'Correct') {
			$('#manual-manager-pin').text('');
			$("#requestManualPin").modal("hide");
			$("#cancelBooking").modal("hide");
			$("#cancelBookingRequestSent").modal("hide");
			$("#requestBooking").modal("hide");
			swal({
				title: "",
				text: "Approved",
				type: 'success'
			}).then(function () {
				bookingRequestNewBooking.onManualPinCheckOk();
			});
		} else {
			$('#manual-manager-pin').text(data.message);
			return false;
		}
	}
	manualOverriderManagerRequest(val) {
		if(val == "Tax On Custom Charge"){
			$("#customCharge").modal("hide");
		}
		$('#requestBooking').modal('show');
		$('#manager-id-required').text('');
		$('#status-button').text('');
		$('#status-button').attr('hidden', true);
		$('#manager_id').val('select');
		this.requestType = val;
	}
	onManualPinCheckOk() {
		if (this.requestType == 'Discount') {
			var html = '';
			html += "<div class='form-field-wrp discount-code-wrp payment-total-count'>"
				+ "<div class='form-group col-12 d-flex align-items-center justify-content-between'>"
				+ "<label class='checkbox-wrap get-email-updates mb-0 text-start'>" + "Promo Code:"
				+ "<input type='radio' name='discount_type' id='discount_type_promo_code' value='Promo Code' onclick='enableDiscount(this)'>"
				+ "<span class='checkmark'></span>"
				+ "</label>"

				+ "<div class='discount-code-input diff'>"
				+ "<input type='text' name='discount_promo_code' disabled id='discount_promo_code' class='form-control' placeholder='Enter Promo Code' onkeyup='removeDiscount()''  style='max-width: 212px;'>"
				+ "<span class='field-icon per-icon'></span>"
				+ "</div>"

				+ "<input type='hidden' name='is_applied' id='is_applied'>"

				+ "<div>"
				+ "<button type='button' class='btn-primary btn-color apply-promo-btn' onclick='applyPromoDiscount()''>" + "Apply" + "</button>"
				+ "<button type='button' class='btn-primary btn-color remove-promo-btn' onclick='removePromoDiscount()' style='display:none;'>" + "Remove" + "</button>"
				+ "</div>"
				+ "</div>"

				+ "<div class='form-group col-12 d-flex align-items-center justify-content-between'>"
				+ "<label class='checkbox-wrap get-email-updates mb-0'>" + "Percentage:"
				+ "<input type='radio' name='discount_type' id='discount_type_percentage' value='Percentage' onclick='enableDiscount(this)'>"
				+ "<span class='checkmark'></span>"
				+ "</label>"

				+ "<div class='discount-code-input'>"
				+ "<input type='number' name='discount_percentage' id='discount_percentage' class='form-control' placeholder='Enter Discount Percentage' min='0' step='0.01' onkeyup='applyDiscount(this)'>"
				+ "<span class='field-icon per-icon'></span>"
				+ "</div>"
				+ "</div>"

				+ "<div class='form-group col-12 d-flex align-items-center justify-content-between'>"
				+ "<label class='checkbox-wrap get-email-updates mb-0'>" + "Amount:"
				+ "<input type='radio' name='discount_type' id='discount_type_fixed_money' value='Fixed Money' onclick='enableDiscount(this)'>"
				+ "<span class='checkmark'></span>"
				+ "</label>"
				+ "<div class='discount-code-input'>"
				+ "<input type='number' name='discount_fixed_money' id='discount_fixed_money' class='form-control' placeholder='Enter Discount Amount' min='0' step='0.01' onkeyup='applyDiscount()'>"
				+ "<span class='field-icon'></span>"
				+ "</div>"
				+ "</div>"
				+ "</div>"
				+ "<br>"
				+ "<button class='btn-add-package d-none' id='btn-add-to-cart' type='button'><i class='fas fa-plus'></i> Add Package</button>"
			$('#permission-granted').html(html);
		} else if (this.requestType == 'rateGroupsApproval') {
			if (this.props.cartView == 1) {
				checkValidationsOfCart(true);
			} else {
				var form = $('#payment-form');
				validateAndSubmitForm(form, true);
			}
		} else if (this.requestType == "Tax On Custom Charge"){
			var html = '';
			html += "<label class='checkbox-wrap get-email-updates mb-0'>"
					+"<input type='checkbox' name='taxable' id='is_taxed' value='1'>"
					+"<span class='checkmark'></span> Taxed" 
				+"</label>"
				$('#customCheckBox').html(html);
				document.cookie = "customTaxOnPermission=Yes; max-age=" + 60*60;;
		}
		this.requestType = '';
	}
}

const bookingRequestNewBooking = new BookingRequestNewBooking({ admin: userType, cartView: cartView });



