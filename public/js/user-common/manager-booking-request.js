$(document).ready(function () {
    $('#table-1').DataTable({
        aaSorting: [[5, 'asc']]
    });
    $('#table-2').DataTable({
          aaSorting: [[5, 'asc']]
    });
});
function deleteRequest(id) {
    swal({
        title: 'Are you sure',
        text: 'Deny record?',
        buttons: [
            'No, cancel it!',
            'Yes, I am sure!'
        ],
        icon: "warning",
        dangerMode: true,
    }).then(function (isConfirm) {
        if (isConfirm) {
            $.ajax({
                type: "GET",
                url: bookingRequestDenyUrl,
                data: {
                    id: id
                },
                success: function (result) {
                    swal('', 'Deny', 'success');
                    location.reload();
                }
            });
        }
    });
}

function approveRequest(id) {
    $.ajax({
        type: "GET",
        url: bookingRequestApproveUrl,
        data: {
            id: id
        },
        success: function (result) {
            swal('', 'Approved', 'success');
            location.reload();
        }
    });
}

function showRequestInfo(id) {
    var showRequestInfoRoute = showRequestInfoUrl.replace(':id', id);
    $.ajax({
        type: "GET",
        url: showRequestInfoRoute,
        contentType: 'application/json;',
        success: function (result) {
            var managerRequestInfo = result.managerRequestInfo;
            var tourBookings = result.tourBookings;
            var bookingsInfoHtml = ``;
            var detailsHtml = ``;
            var details = managerRequestInfo.details;
            var requestType = managerRequestInfo.request_type;
            var paymentMethod = result.paymentMethod;

            detailsHtml += `
                    <div class="card contact-card border border-[#E5E7EB] rounded-lg overflow-hidden">
                        <div class="card-header contact-header bg-[#FEFCE8] px-4 py-3 font-bold text-black text-base text-left border-b border-[#E5E7EB]">Request Details</div>
                        <div class="card-body p-5">
                            <div class="form-group col-12 d-flex align-items-center manager-request-fields">
                                <label class="col-form-label text-base font-bold text-[#4B5563]">Request: </label>
                                <span class="manager-request-field-title">${requestType}</span>
                            </div>`
            if (paymentMethod != '') {
                detailsHtml += `<div class="form-group col-12 d-flex align-items-center manager-request-fields">
                                <label  class="col-form-label text-base font-bold text-[#4B5563]">Payment Method: </label>
                                <span class="manager-request-field-title btn-sm btn-success inline-block relative px-4 py-1 border rounded text-[#0f5132] bg-[#d1e7dd] border-[#badbcc]">${paymentMethod}</span>
                            </div>`;
            } else {
                detailsHtml += `<div class="form-group col-12 d-flex align-items-center manager-request-fields">
                                <label class="col-form-label text-base font-bold text-[#4B5563]">Payment Status: </label>
                                <span class="manager-request-field-title btn-sm btn-danger inline-block relative px-4 py-1 border rounded text-[#842029] bg-[#f8d7da] border-[#f5c2c7]">Pending</span>
                            </div>`;
            }

            if (details.length > 0) {
                for (var j = 0; j < details.length; j++) {
                    detailsHtml += `<div class="form-group col-12 d-flex align-items-center manager-request-fields">
                            <label class="col-form-label text-base font-bold text-[#4B5563]">${details[j].name}: </label>
                            <span class="manager-request-field-title">${details[j].value}</span>
                        </div>`;
                }
            }

            detailsHtml += `</div>
                    </div>`;
            $('#bookingRequestInfo #requestDetails').html(detailsHtml);

            if (tourBookings.length > 0) {
                for (var i = 0; i < tourBookings.length; i++) {
                    var tourDateTime = tourBookings[i].date + ' ' + tourBookings[i].time_from;
                    bookingsInfoHtml += `<div class="card contact-card border border-[#E5E7EB] rounded-lg overflow-hidden">
                        <div class="card-header contact-header bg-[#FEFCE8] px-4 py-3 font-bold text-black text-base text-left border-b border-[#E5E7EB]">
                      Booking <b>#${tourBookings[i].tourBookingId}</b>
                        </div>
                        <div class="card-body p-5">
                            <div class="form-group col-12 d-flex align-items-center manager-request-fields">
                                <label class="col-form-label text-base font-bold text-[#4B5563]">Package Name: </label>
                                <span class="manager-request-field-title">${tourBookings[i].tourPackageName}</span>
                            </div>
                            <div class="form-group col-12 d-flex align-items-center manager-request-fields">
                                <label class="col-form-label text-base font-bold text-[#4B5563]">Tour Date and Time: </label>
                                <span class="manager-request-field-title">${moment(tourDateTime).format('D MMM YYYY, hh:mm a')}</span>
                            </div>
                            <div class="form-group col-12 d-flex align-items-center manager-request-fields">
                                <label class="col-form-label text-base font-bold text-[#4B5563]">Customer Name: </label>
                                <span class="manager-request-field-title">${tourBookings[i].customerName}</span>
                            </div>
                        </div>
                    </div>`;
                }
                $('#bookingRequestInfo #bookingsInfo').html(bookingsInfoHtml);
            }


            $('#bookingRequestInfo').modal('show');
        }
    });
}