class BundleBookingInfo{

    /**
     * This is called from the template blade booking info view for bundle bookings
     * @param {*} props 
     * @returns 
     */

    constructor(props){
        this.props = props;
        this.admin = ( props.admin === 'Admin' ) ? '/admin' : '';
        this.bookingId = props.bookingId;
        this.packageId = props.packageId;
        this.updateBundlePackageCheckInStatusUrl = `${this.admin}/tour-operator/booking/update-package-redeem-status`;

        //add events
        this.eventListeners();
    }

    /**
     * attach events to form builder dom elements 
     */
    eventListeners() {
        // on field type select update the add field form
        $(document).off("change", "#bundle-pkg-check-in").on("change", "#bundle-pkg-check-in", this.updateBundlePackageCheckIn.bind(this));
        $(document).off("click", "#update-check-in").on("click", "#update-check-in", this.updateCheckIn.bind(this));
    }

    updateCheckInAndRedeemTicketStatus(packageId, rate_id, type, index, checkin_type) {
        const csrf = $('meta[name="csrf-token"]').attr('content');
        var postData = {
            booking_id: this.bookingId,
            parent_package_id: this.packageId,
            package_id: packageId,
            checkin_type: checkin_type
        };

        var packageName = '';
        if(typeof index == 'number') {
            postData.index = index;

            if(checkin_type == 0) {
                var bundleClass = "#bundle_package_checkin_" + index + "_" + postData.package_id + "_" + rate_id;
                postData.status = $(bundleClass + ' :selected').val();
                postData.tpr_id = $(bundleClass).attr('data-tpr-id');
            } else {
                var bundleClass = $("#bundle_package_" + index + "_" + postData.package_id + "_" + rate_id);
                postData.tpr_id = bundleClass.attr('data-tpr-id');
                postData.status = bundleClass.attr('data-status');
            }
        } else {
            var bundleClass = $(".bundle_package_" + postData.package_id);
            packageName = $(".redeem-ticket-" + postData.package_id).attr('data-package-name');
        }

        const confirmRedeemTicket = (postData) => {
            this.packageRedeemTicket(postData, csrf, bundleClass);
        };

        if(type == 'all') {
            if(checkin_type == 1) {
                swal({
                    title: "Redeem Ticket",
                    text: "Which status do you want to update for " + packageName + "?",
                    icon: "warning",
                    buttons: {
                        no: {
                            text: 'Inactive',
                            className: 'btn-danger'
                        },
                        yes: {
                            text: 'Active',
                            className: 'btn-success'
                        }
                    }
                }).then(function(result) {
                    if(result != null) {
                        postData.status = (result == "yes") ? 'Active' : 'Inactive';
                        showLoading();
                        confirmRedeemTicket(postData);
                    }
                });
            } else {
                $(".bundle-pkg-section").empty();
                $('#bundlePackageUpdateCheckIn').modal('show');
                $(".bundle-pkg-section").html(this.bundlePackageCheckInHTML(packageName, postData.package_id, checkin_type));
            }
        } else {
            showLoading();
            confirmRedeemTicket(postData);
        }
    }

    packageRedeemTicket(postData, csrf, bundleClass, checkin_all = 0) {
        var histDetails = '';

        $.ajax({
            type: "POST",
            url: this.updateBundlePackageCheckInStatusUrl,
            data: postData,
            headers: { 'X-CSRF-TOKEN': csrf },
            dataType: 'json'
        }).done((response) => {
            if(response.updated) {
                if(response.checkin_type == 0) {
                    var check_in_status = 'check-in-status-' + postData.status;

                    var setCheckInStatus = function () {
                        $(bundleClass)
                            .removeClass('check-in-status-N check-in-status-1 check-in-status-2 check-in-status-A')
                            .addClass(check_in_status);
                    };

                    $('.sel-check-in').each(function () {
                        if(checkin_all == 0) {
                            var bClass = bundleClass.substring(1);
                            if($(this).attr('id') == bClass) {
                                $("#" + $(this).attr('id')).prop("selected", false);
                                $("#" + $(this).attr('id') + ' option:selected').attr("selected", "selected");
                            }
                        } else {
                            $(".bundle_package_checkin_" + postData.package_id).prop("selected", false);
                            $(".bundle_package_checkin_" + postData.package_id + ' option[value=' + postData.status + ']').prop('selected',true);
                        }
    
                        setCheckInStatus(this);

                        if (response.status == null) {
                            if (response.is_affiliate == 'No') {
                                $('#modal_admin').modal('show');
                            } else {
                                $('#modal_affilate').modal('show');
                            }
                        }
                    });

                    histDetails = 'Set checked-in status to <b>' +
                    checkInStatuses[response.status] + '</b> for <b>' +
                    encLT(response.whoCheckedIn) + '</b>';

                    addInHistory(moment(response.checkInDateTime).format('M/D/yyyy h:ma'), histDetails, 'Check-in');

                    $('#bundlePackageUpdateCheckIn').modal('hide');
                    $(".bundle-pkg-section").empty();
                } else {
                    var updatedClassName = "redeem-ticket-section bundle_package_" + postData.package_id;
                    histDetails = 'Redeemed ticket for package <b>' + response.package_name + '</b> status <b>' + response.status + '</b> for <b>' + encLT(response.whoCheckedIn) + '</b>';
                    addInHistory(moment(response.checkInDateTime).format('M/D/yyyy h:ma'), histDetails, 'Redeemed');
                    bundleClass.attr('data-status', response.status);

                    if(response.status == 'Active') {
                        bundleClass.removeClass(updatedClassName + " redeem-ticket-danger");
                        bundleClass.addClass(updatedClassName + " redeem-ticket-success");
                    } else {
                        bundleClass.removeClass(updatedClassName + " redeem-ticket-success");
                        bundleClass.addClass(updatedClassName + " redeem-ticket-danger");
                    }
                }
            } else {
                swal({
                    icon: 'error',
                    title: 'error',
                    text: 'Something went while going to redeem ticket for ' + response.package_name + ', Please try again.'
                });
            }
        }).fail((error) => {
            swal(error.responseJSON.errors.common[0], '', 'error');
        }).always(hideLoading);
    }

    bundlePackageCheckInHTML(package_name, package_id, checkin_type) {
        const form = `
            <input type="hidden" name="pkg_id" id="pkg_id" value="${package_id}">
            <input type="hidden" name="checkin_type" id="checkin_type" value="${checkin_type}">
            <p>Which status do you want to update for <b><span class="pkg-name">${package_name}</span></b>?</p>
            <select class="form-select mt-3 mb-3 sel-check-in check-in-status-N" id="bundle-pkg-check-in">
                <option class="check-in-status-N" value="N">Not Checked In</option>
                <option class="check-in-status-1" value="1">1st Check In</option>
                <option class="check-in-status-2" value="2">2nd Check In</option>
                <option class="check-in-status-A" value="A">Absent</option>
            </select>
            <div class="text-end">
                <button type="submit" id="update-check-in" class="btn btn-main btn-main-sm">Update</button>
            </div>
        `;

	    return form;
    }

    updateBundlePackageCheckIn(event) {
        var checkin = $(event.target).val();

        $("#bundle-pkg-check-in").removeClass('check-in-status-N check-in-status-1 check-in-status-2 check-in-status-A')
        .addClass('form-select mt-3 mb-3 sel-check-in check-in-status-' + checkin);
    }

    updateCheckIn() {
        const csrf = $('meta[name="csrf-token"]').attr('content');
        var checkin_value = $('#bundle-pkg-check-in option:selected').val();
        var package_id = $("#pkg_id").val();
        var bundleClass = ".bundle_package_checkin_" + package_id;
        var checkin_type = $("#checkin_type").val();

        var postData = {
            booking_id: this.bookingId,
            parent_package_id: this.packageId,
            package_id: package_id,
            checkin_type: checkin_type,
            status: checkin_value
        };

        showLoading();
        this.packageRedeemTicket(postData, csrf, bundleClass, 1);
    }
}

const bundleBookingInfo = new BundleBookingInfo({ admin: userType, packageId: packageId, bookingId: bookingId } );