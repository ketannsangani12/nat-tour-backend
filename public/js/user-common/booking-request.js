function requestType(deleteBookings, hide) {
	$('#manager-id-required').text('');
 	$('#status-button').text('');
 	$('#status-button').attr('hidden', true);
 	$('#manager_id').val('select');
	$.ajax({ 
	    headers: { Accept: 'application/json' },
 		type: 'POST',
			dataType: 'json',
		url: actionStore,
		data: deleteBookings,
     	success: function(response){ 
	      if (response.message == 'Store') {
	      	$('#manual-manager-id').text('');
	      	$(hide).modal("hide");
	      	$("#requestBooking").modal("show");
	      }
	    } 
	}); 
}

function cancelBookingRequest() {
	 var manager_id = $('#manager_id').val();
	 if(manager_id == null){
	 	$('#manager-id-required').text('manager name is required');
	 	$('#manager-id-required').addClass('text-[#FF0000]');
	 	return false;
	 }
	 $('#manager-id-required').text('');
	 $('#status-button').attr('hidden', true);
	 $.ajax({
		headers: { Accept: 'application/json' },
	 	type: 'POST',
	  	dataType: 'json',
	  	url: requestToManager,
	  	data: {bookingId: bookingId, manager_id:manager_id, orderId:orderId},
	  	success: function(result)
	  	{
	  	if(result.status == 'error'){
	  		$('#status-button').attr('hidden', false);
	  		$('#status-button').text('Status: ' + result.request_status);
	  		$('#manager-id-required').text(result.message);
	  		if(result.request_status == "Approved"){
	  			$('#status-button').removeClass('bg-[#A32624]');
	  			$('#status-button').addClass('bg-[#2FA136]');
	  			$('#manager-id-required').removeClass('text-[#FF0000]');
	  			$('#manager-id-required').addClass('text-[#2EA135]');

	  		}else{
	  			$('#status-button').removeClass('bg-[#2FA136]');
	  			$('#status-button').addClass('bg-[#A32624]');
	  			$('#manager-id-required').removeClass('text-[#2EA135]');
	  			$('#manager-id-required').addClass('text-[#FF0000]');
	  		}
 			return false;
	  	}
	  	$("#requestBooking").modal("hide");
	  	swal({
			   title: "Request has been sent to the manager",
			   text: "page will be reload in 5 seconds.",
			   timer: 5000,
			   buttons: false,
			}).then(function(){
					   location.reload();
			});
 		
	  }

	});	
}

// Manual Request
$(document).on('click', '#cancelBookingRequestManual', function() {
	var user_id = $('#manager_id').val();
	if(user_id == null){
	 	$('#manager-id-required').text('manager name is required');
	 	$('#manager-id-required').addClass('text-[#FF0000]');
	 	return false;
	}
	$('#manager-id-required').text('');
	$("#requestManualId").modal("show");
	$('#manual-manager-id').text('');
	$('#manual-manager-pin').text('');
	$('#status-button').text('');
	$('#status-button').attr('hidden', true);
	$("#request-manual-id").on('click', function(){
		var manager_id = $('#manualManagerId').val();
		 if(manager_id == ''){
		 	$('#manual-manager-id').text('manager id is required');
		 	return false;
		 }
 		$.ajax({ 
		    headers: { Accept: 'application/json' },
	 		type: 'POST',
	  		dataType: 'json',
  			url: manualIdCheck,
  			data: {manager_id:manager_id, user_id:user_id},
		     success: function(response){ 
		      if(response.message == 'Correct'){
		      	$('#manual-manager-id').text('');
		      	$("#requestManualId").modal("hide");
		      	$("#requestManualPin").modal("show");
		      }else{
		      	$('#manual-manager-id').text('Manager id is incorrect');
		 		return false;
		      }
		    } 
		 }); 
	})
});


$("#request-manual-pin").on('click', function() {
	var manager_pin = $('#manualManagerPin').val();
	 if(manager_pin == ''){
	 	$('#manual-manager-pin').text('manager pin is required');
	 	return false;
	 }
	 $('#manual-manager-pin').text('');
		 $.ajax({ 
	     	headers: { Accept: 'application/json' },
	 		type: 'POST',
	  		dataType: 'json',
				url: manualPinCheck,
				data: {bookingId: bookingId, manager_pin:manager_pin, orderId:orderId},
	     	success: function(response){ 
	      	if(response.message == 'Correct'){
		      	$('#manual-manager-pin').text('');
		      	$("#requestManualPin").modal("hide");
		      	$("#cancelBooking").modal("hide");
	 			$("#cancelBookingRequestSent").modal("hide");
	 			$("#requestBooking").modal("hide");
		      	swal({
					   title: "",
					   text: "Approved",
					   type: 'success'
				}).then(function(){
				   location.reload();
				});
	      	}else{
	      		$('#manual-manager-pin').text(response.message);
	 			return false;
	      	}
	    } 
	 }); 
})

function validationMangerRequest(formData, moduleType, permission, hide, form, additional) {
    var dataObj = {};
    $(formData).each(function(i, field) {
        dataObj[field.name] = field.value;
    });

    var field_type = '';
    
    // Handle Split Booking case
    if (moduleType === "Split Booking") {
        $('input[type="checkbox"]:checked').each(function() {
            var name = this.name;
            field_type += additional + '_' + name;
        });
    }
    
    // Handle Discount case
    if (moduleType === "Discount") {
        if (additional === "Change people discount_") {
            field_type = additional + dataObj['pr_amount'];
        } else if (dataObj['payment_method'] === "Pay Later") {
            if (dataObj['edit_discount_percentage']) {
                field_type = 'edit_discount_percentage' + dataObj['edit_discount_percentage'];
            } else if (dataObj['edit_discount_fixed_money']) {
                field_type = 'edit_discount_fixed_money' + dataObj['edit_discount_fixed_money'];
            } else if (dataObj['edit_discount_promo_code']) {
                field_type = 'edit_discount_promo_code' + dataObj['edit_discount_promo_code'];
            }
        }
    }

    // Handle Switch case
    if (moduleType === "Switch") {
        if (!$.trim(dataObj["tour_package_switch"]) || !$.trim(dataObj["tour_slot_time_switch"])) {
            $('#switchForm').submit();
            return false;
        }
        field_type = dataObj["tour_package_switch"] + '_' + dataObj["tour_slot_date_switch"] + '_' + dataObj["tour_slot_time_switch"];
    }

    // Handle Change case (only once)
    if (moduleType === "Change") {
        $("input[name='rate_group[]']").each(function() {
            var name = this.value;
            field_type += 'Change' + '_' + name;
        });
        $("select[name='tourists[]']").each(function() {
            var name = this.value;
            field_type += 'Change' + '_' + name;
        });
    }

    // Handle Cancel, Refund, 
    if (moduleType === "Cancel" || moduleType === "Refund") {
        field_type = dataObj['request_type'] + '_' + dataObj['refund_type'];
        if (dataObj['refund_type'] === "partial_refund") {
            field_type += '_' + dataObj['partial_refund_value'];
        }
    }
    
    // HandleDelete, Permit cases
    if (moduleType === "Delete" || moduleType === "Permit") {
        $('input[type="checkbox"]:checked').each(function() {
            var name = this.name;
            field_type += (moduleType === "Delete" ? 'Delete' : 'Permit') + '_' + dataObj['refund_Type'] + '_' + name + '_';
        });
        if (dataObj['refund_Type'] === "partial_refund") {
            field_type += '_' + dataObj['partial_Refund_value'];
        }
    }

    // Handle Reschedule case
    if (moduleType === "Reschedule") {
        if (!$.trim(dataObj["tour_slot_time"])) {
            $("#reschedulebtn").submit();
            return false;
        }
        field_type = dataObj['request_type'] + '_' + dataObj['tour_slot_time'] + '_' + dataObj['tour_slot_date'];
    }

    // Check if field_type exists in fieldRequestPermissions array
    if ($.inArray(field_type, fieldRequestPermissions) !== -1) {
        permission = "Yes";
    }

    // Handle Permission checks and form submission
    if (permission === 'No') {
        formData.push({ name: "field_type", value: field_type });
        formData.push({ name: "request_type", value: moduleType });
        requestType(formData, hide);
        return false;
    }

    if (permission === "Yes") {
        switch (moduleType) {
            case "Split Booking":
                splitPeople(form);
                break;
            case "Permit":
                if (form === "#updatePermitFees") {
                    $('#updateBooking').modal('toggle');
                    $('#edit_booking').modal('hide');
                } else {
                    showLoading();
                    $(this).prop('disabled', true);
                    $(form).submit();
                }
                break;
            case "Switch":
            case "Delete":
            case "Refund":
            case "Reschedule":
            case "Cancel":
                showLoading();
                $(this).prop('disabled', true);
                $(form).submit();
                break;
            case "Change":
                $(form).submit();
                break;
            case "Discount":
                if (dataObj['payment_method'] !== "Pay Later") {
                    showLoading();
                    $(this).prop('disabled', true);
                    $(form).submit();
                } else {
                    showLoading();
                    $.ajax({
                        headers: { Accept: 'application/json' },
                        dataType: 'json',
                        type: 'POST',
                        url: $('#editdiscountForm').attr('action'),
                        data: formData,
                        success: function(result) {
                            hideLoading();
                            $('#modal_edit_discount').modal('hide');
                            discountHistoryArray = result.discountHistory;
                            if (result.discountHistory.payment_method === 'Pay Later' || result.discountHistory.is_split === 1) {
                                var discountVal = result.discountHistory.new_discount2_percentage > 0 ?
                                    '- (' + result.discountHistory.new_discount2_percentage + '%) $' + result.discountHistory.new_discount2_value.toFixed(2) :
                                    '- $' + result.discountHistory.new_discount2_value.toFixed(2);

                                $("#payLater").html(currencyFormation.symbol + result.discountHistory.new_total.toFixed(2));
                                swal('Booking updated successfully', 'Page will reload', 'success')
                                    .then(function() {
                                        const url = new URL(window.location.href);
                                        if (url.searchParams.has('split')) {
                                            url.searchParams.delete('split');
                                        }
                                        window.location.replace(url.toString());
                                    });
                            }
                        },
                        error: function(jqXHR) {
                            hideLoading();
                            showSubmissionError(jqXHR);
                        }
                    }).fail(function(jqXHR) {
                        hideLoading();
                        showSubmissionError(jqXHR);
                    }).always(hideLoading);
                }
                break;
        }
    }
}


$("#switchpackagebtn").off().on('click',function(e) {
	if(switchPermission == "No"){
		var formData = $('#switchForm').serializeArray();
		var additional = '';
		validationMangerRequest(formData, 'Switch', switchPermission, '#modal-switch-package', '#switchForm', additional);
		return false;
	}
});

$("#update_booking_change_people_submit").off().on('click', function() {
	var changePeopleBookings = $('#changePeopleForm').serializeArray();
	$('.dis-connect-to-card-reader').hide();
	$('.card-reader-status-output').hide();
	if(changePeoplePermission == "No"){
		validationMangerRequest(changePeopleBookings, 'Change', changePeoplePermission, '#edit_booking_change_people', '#changePeopleForm', 'additional');
		return false;	
	}
		
});

$('#reschedulebtn').off().on('click', function(e) {
	if ($(this).prop('disabled')) return false;
	var refundform = $('#rescheduleform').serializeArray();
	var additional = '';
	if(reschedulePermission == "No"){
		validationMangerRequest(refundform, 'Reschedule', reschedulePermission, '#modal-reschedule', '#rescheduleform', additional);
		return false;
	}
	e.preventDefault();
	showLoading();
	$(this).prop('disabled', true);
	document.getElementById('rescheduleform').submit();
});