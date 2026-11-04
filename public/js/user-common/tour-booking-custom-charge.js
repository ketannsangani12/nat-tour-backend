/* Custom Charge JS Start */
var customChargeTotal = 0;
var customChargeFeesTotal = 0;

$('#addCustomCharge').on('click', function(e) {
	$("#is_taxed").prop('checked', true);
	var tourPackageId = $("#tour_package").val();
	var timeSlot = $('#slot_time').val();

	if (!tourPackageId) {
		swal('Please select the tour package.', '', 'warning');
		return false;
	}

	if (!timeSlot) {
		swal('Please select a time slot.', '', 'warning');
		return false;
	}

	const getCustomData =  $("textarea#custom_charge_data").val();
	if(getCustomData != "") {
		customChargeRetrieveFormDetails(getCustomData);
		$("#removeCustomCharge").removeAttr('disabled');
	}

	if(selectedPackageId != 0) {
		customChargeIntegration(selectedPackageId, serviceCommissionPercentage);
		
		if(getCustomData != "") {
			$("#removeCustomCharge").removeAttr('disabled');
		}
	}
	$('#customCharge').modal('show');
	e.preventDefault();
});

function customChargeForm(tourPackageId) {
	var input = '';
	var customTaxOnPermission = document.cookie.replace(/(?:(?:^|.*;\s*)customTaxOnPermission\s*\=\s*([^;]*).*$)|^.*$/, "$1")
	if(taxOnCustomChargePermission == "Yes" || customTaxOnPermission == "Yes" || userType == 'Affiliate'){
		input = `<input class="w-4 h-4 mr-2" type="checkbox" name="taxable" id="is_taxed" value="1" checked>`
	}else{
		input = `<input class="w-4 h-4 mr-2" type="checkbox" name="taxable" id="is_taxed" value="1" onclick="managerRequest('Tax On Custom Charge')" checked>`
	}
	const form = `<div id="customForm_${tourPackageId}"><input type="hidden" name="custom_tour_package_id" id="custom_tour_package_id" value="${tourPackageId}">
		<input type="hidden" name="is_custom_charge" value="1">
		<div class="d-flex mb-2">
			<div class="form-check-wrap">
				<div id="custom_charge_value">
					<label class="form-check-label block form-custom-label mb-1" for="custom_charge_amount">Amount</label>
					<input class="form-custom-input" type="text" min="1" step="0.01" id="custom_charge_amount" name="custom_charge_amount" style="max-width: 100%;">
					<input type="hidden" id="custom_charge_val" value="">
				</div>
			</div>
		</div>
		<div class="mb-2">
			<div class="form-check-wrap" id="customCheckBox">
				<label class="checkbox-wrap get-email-updates form-custom-label flex items-center">
					${input}
					<span class="checkmark"></span>
					Taxed
				</label>
			</div>
		</div>
		<div class="mb-3">
			<label for="custom_charge_comment" class="form-label form-custom-label">Add a note</label>
			<textarea name="comment" id="custom_charge_comment" rows="4" cols="50" class="cancel-notes form-custom-input min-h-16" placeholder="Please specify the reason for custom charge"></textarea>
		</div>
		<div class="flex flex-wrap -mx-3">
			<div class="w-1/2 px-3">
				<button class="btn btn-main btn-main-sm themed-btn short-btn btn-primary-small w-full" id="submitCustomCharge">Submit</button>
			</div>
			<div class="w-1/2 px-3">
				<button class="btn btn-sm btn-delete themed-btn short-btn custom-charge-delete bg-[#E5E7EB] border border-[#E5E7EB] text-[#4B5563] text-[12px] font-semibold p-2 w-full rounded-[6px] cursor-pointer hover:bg-[#A32624] hover:text-white" id="removeCustomCharge" disabled>Clear</button>
			</div>
		</div></div>`;

	return form;
}

function validateAndSubmitCustomChargeForm() {
	if ($('#custom_charge_amount').val() == '') {
		swal('Sorry!', 'Please enter an amount.', 'warning');
		return false;
	}

	if ($('#custom_charge_amount').val() != '') {
		if (!numbersDecimalOnly())
			return false;
	}
	
	if($('#custom_charge_amount').val() == 0) {
		swal('Sorry!', 'Please enter an amount greater than zero.', 'warning');
		return false;
	}

	if ($('#custom_charge_comment').val() == '' || !$.trim($('#custom_charge_comment').val())) {
		swal('Sorry!', 'Please add a note.', 'warning');
		return false;
	}

	var tourPackageId = $("#custom_tour_package_id").val();
	var isTaxable = $('#is_taxed:checked').val();
	if(isTaxable == undefined) {
		$(".custom-charge-fees-").val('0.00');
	}
	
	const data = { 
		custom_form_id: tourPackageId, 
		custom_data: {
			custom_charge_amount: parseFloat($('#custom_charge_amount').val()),
			taxable: $('#is_taxed:checked').val() ? 1 : 0,
			comment: $('#custom_charge_comment').val()
		}, 
		tour_package_id: tourPackageId  
	};

	$("#custom_charge_data").val(JSON.stringify(data));
	$("#custom_charge_val").val($('#custom_charge_amount').val())
	customChargePrice(tourPackageId);
	customChargeFeesPrice(tourPackageId, serviceCommissionPercentage);
	updateRatesTable();
	$("#removeCustomCharge").removeAttr('disabled');
	$('#customCharge').modal('hide');

	return false;
}

function customChargePrice(tourPackageId) {
	customChargeTotal = 0;
	var form = $("#customForm_" + tourPackageId);
	const getCustomData =  $("textarea#custom_charge_data").val();
	if(getCustomData != "") {
		customChargeRetrieveFormDetails(getCustomData);
	}
	var customChargeAmount = parseFloat(form.find('#custom_charge_amount').val());
	var cartPackageDetails = getCartPackageDetails(tourPackageId);
	
	if (cartPackages.length > 0 && cartPackageDetails != "") {
		for (var a = 0; a < cartPackages.length; a++) {
			if(tourPackageId == cartPackages[a].tour_package_id) {
				if(!isNaN(customChargeAmount)) {
					if(cartPackages[a].custom_charge != customChargeAmount) {
						customChargeTotal = customChargeTotal - cartPackages[a].custom_charge + customChargeAmount;
					}
				}
			}
		}
	} else {
		if(!isNaN(customChargeAmount)) {
			customChargeTotal = customChargeTotal + customChargeAmount;
		}
	}

	customChargeTotal = customChargeTotal + totalCustomChargeAmount;

	hideShowSplitInputUI();
}

function getCustomChargeDetails(packageDetails) {
	if(packageDetails.custom_charge_data != "") {
		customChargeRetrieveFormDetails(packageDetails.custom_charge_data);
	}
}

function customChargeFeesPrice(tourPackageId, serviceCommissionPercentageCharge) {
	customChargeFeesTotal = 0;
	var form = $("#customForm_" + tourPackageId);
	var customChargeAmount = parseFloat(form.find('#custom_charge_amount').val());
	var isTaxed = form.find('#is_taxed:checked').val();
	isTaxed = (isTaxed != undefined) ? 1 : 0;
	var cartPackageDetails = getCartPackageDetails(tourPackageId);
	
	if (cartPackages.length > 0 && cartPackageDetails != "") {
		for (var a = 0; a < cartPackages.length; a++) {
			if(tourPackageId == cartPackages[a].tour_package_id) {
				if(!isNaN(customChargeAmount)) {
					if(isTaxed == 1) {
						customChargeFeesTotal = customChargeFeesTotal - cartPackages[a].custom_charge_fees + roundout((customChargeAmount * serviceCommissionPercentageCharge / 100), 2);
					} else {
						customChargeFeesTotal = customChargeFeesTotal - cartPackages[a].custom_charge_fees;
					}
				}
			}
		}
	} else {
		if(!isNaN(customChargeAmount)) {
			if(isTaxed == 1) {
				customChargeFeesTotal = customChargeFeesTotal + roundout((customChargeAmount * serviceCommissionPercentage / 100), 2);
			}
		}
	}

	customChargeFeesTotal = customChargeFeesTotal + totalCustomChargeFees;
}

function customChargeRetrieveFormDetails(customChargeDetails) {
	cdata = JSON.parse(customChargeDetails);
	taxFlag = (cdata.custom_data.taxable == 1) ? true : false;
	$("#is_taxed").prop('checked', taxFlag);
	$("#custom_charge_comment").val(cdata.custom_data.comment);

	$("#custom_charge_amount").val(cdata.custom_data.custom_charge_amount);
	$("#custom_charge_val").val(cdata.custom_data.custom_charge_amount);
	$("#custom_charge_data").val(customChargeDetails);
}

$(document).on('click', '#removeCustomCharge', function () {
	var tourPackageId = $("#custom_tour_package_id").val();
	var form = $("#customForm_" + tourPackageId);
	var customChargeAmount = parseFloat(form.find('#custom_charge_amount').val());
	var isTaxed = form.find('#is_taxed:checked').val();
	isTaxed = (isTaxed != undefined) ? 1 : 0;

	swal({
		title: "Are you sure you want to delete the custom charge for the selected tour package?",
		icon: "warning",
		buttons: [
		  'No, cancel it!',
		  'Yes, I am sure!'
		],
		dangerMode: true,
	}).then(function(isConfirm) {
		if (isConfirm) {
			if(customChargeTotal > 0) {
				customChargeTotal = customChargeTotal - customChargeAmount;
				if(isTaxed == 1) {
					customChargeFeesTotal = customChargeFeesTotal - roundout((customChargeAmount * serviceCommissionPercentage / 100), 2);
				}
			}
			form.find('#custom_charge_amount').val('');
			form.find('#custom_charge_val').val('');
			form.find('textarea#custom_charge_comment').val('');
			$(".custom-charge-fees-").val('0.00');
			$("textarea#custom_charge_data").val('');
			$('#customCharge').modal('hide');
			updateRatesTable();

			// Update split payment visibility after removing custom charge
			if (typeof hideShowSplitInputUI === 'function') {
				hideShowSplitInputUI();
			}
			if (typeof hideShowSplitInputUIForCart === 'function' && typeof cartPackages !== 'undefined') {
				hideShowSplitInputUIForCart(cartPackages.length);
			}
		}
	});
});

function customChargeIntegration(tourPackageId, serviceCommissionPercentageCharge) {
	$("#addCustomCharge").removeClass('hidden');
	$(".custom-charge-body").html(customChargeForm(tourPackageId));
	customChargePrice(tourPackageId);
	customChargeFeesPrice(tourPackageId, serviceCommissionPercentageCharge);
}

function numbersDecimalOnly(event) {
	const keyCode = (event != undefined) ? event.keyCode : null;
    const excludedKeys = [8, 37, 39, 46, 32, 190, 110, 16, 17];

	if ($('#custom_charge_amount').val() != '') {
		if(keyCode == undefined || keyCode == null) {
			if($.isNumeric($('#custom_charge_amount').val()) == false) {
				swal('Only decimal and numbers are allowed.', '', 'warning');
				return false;
			}
		} else {
			if (!((keyCode >= 48 && keyCode <= 57) || (keyCode >= 96 && keyCode <= 105) || (excludedKeys.includes(keyCode)))) {
				swal('Only decimal and numbers are allowed.', '', 'warning');
				return false;
			}
		}
	}

	return true;
}

$(document).on('keyup change', '#custom_charge_amount', function (event) {
	numbersDecimalOnly(event);
})
/* Custom Charge JS End */