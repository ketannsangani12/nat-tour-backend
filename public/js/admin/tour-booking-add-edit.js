$(function() {
	if($('#tour_operator').val()) {
		var selTourOp = $('#tour_operator')[0];
		serviceCommissionPercentage = $(selTourOp.options[selTourOp.selectedIndex]).data('service-commission-percentage');
		$('.service-commission-percentage').text(serviceCommissionPercentage + '%');

		var tourOpId = $("#tour_operator").val();
		if(tourOpId != 0){
			$.getJSON(getTourOperatorAffiliateUrl + tourOpId)
				.done(function(data) {
					if(data.data.is_affiliate.is_affiliate == null){
						$("#affiliatecust").hide();
					}else{
						if(data.data.is_affiliate.is_affiliate == 0){
							$("#affiliatecust").hide();
						}else if(data.data.is_affiliate.is_affiliate == 1){
							$("#affiliatecust").show();
						}
					}
				})
				.always(hideLoading);
		}
	}
});

function getTourPackages(selTourOp) {
	var tourOpId = selTourOp.options[selTourOp.selectedIndex].value;
	var $selTourPackage = $('#tour_package');
	var $selBookedByStaff = $('#booked_by_staff');
	var pkgDefaultOpt = '<option value="">Select Tour Package</option>';
	var staffDefaultOpt = '<option value="">Select booked by person name</option>';
	$('#slot_time').html('<option value="">Select Time Slot</option>').trigger('change');

	if(tourOpId == '') {
		serviceCommissionPercentage = 0;
		$selTourPackage.html(pkgDefaultOpt).trigger('change');
		$selBookedByStaff.html(staffDefaultOpt).trigger('change');
		$('#tour_slot_time').html('<option value="">Select Time Slot</option>').trigger('change');
		return;
	} else
		serviceCommissionPercentage = $(selTourOp.options[selTourOp.selectedIndex]).data('service-commission-percentage');
	
	$('.service-commission-percentage').text(serviceCommissionPercentage + '%');

	showLoading();
	
	$.getJSON(getTourOperatorDataUrl + tourOpId)
		.done(function(data) {
			$selTourPackage.html(pkgDefaultOpt);

			for(var i = 0; i < data.tourPackages.length; i++)
				$selTourPackage.append(new Option(data.tourPackages[i].name, data.tourPackages[i].id, false, false));
		
			$selTourPackage.trigger('change');
			$selBookedByStaff.html(staffDefaultOpt);

			for(var i = 0; i < data.staff.length; i++)
				$selBookedByStaff.append(new Option(data.staff[i].first_name + ' ' + data.staff[i].last_name, data.staff[i].id, false, false));

			$selBookedByStaff.trigger('change');
			taxes = data.taxes;
			totalSansDiscount = 0;
			$('#discount_applied').val(0);
			$('#is_applied').val(0);
			addTaxCols();
			applyDiscount();
		})
		.fail(function() {
			swal('Error occurred', 'Unable to get tour packages and staff of the tour operator from server.', 'error');
		})
		.always(hideLoading);
	
	$.getJSON(getTourOperatorAffiliateUrl + tourOpId)
		.done(function(data) {
			if(data.data.is_affiliate.is_affiliate == null){
				$("#affiliatecust").hide();
			}else{
				if(data.data.is_affiliate.is_affiliate == 0){
					$("#affiliatecust").hide();
				}else if(data.data.is_affiliate.is_affiliate == 1){
					$("#affiliatecust").show();
					var affiliateDefaultOpt = '<option value="">No Affiliate</option>';
					$.getJSON(getTourOperatorAffiliates + '/' + tourOpId)
						.done(function(data) {
							$("#affiliatecust").html(affiliateDefaultOpt);
							for(var i = 0; i < data.data.length; i++)
								$("#affiliatecust").append(new Option(data.data[i].name, data.data[i].id, false, false));
						})
				}
			}
		})
		.always(hideLoading);

	showSelectionInfo();
}
