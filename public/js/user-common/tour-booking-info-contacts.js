class TourBookingInfoContacts { 
    
    /**
     * This is called from the template blade create booking view for package with enabled has contacts feature
     * @param {*} props 
     * @returns 
     */

    constructor(props){
        this.props = props;
        this.admin = ( props.admin === 'Admin' ) ? '/admin' : '';
        this.bookingId = props.bookingId;
        this.getContactDetails = `${this.admin}/tour-operator/booking/get-contact-details/:id`;
        this.editContactDetails = `${this.admin}/tour-operator/booking/edit-contact`;

        //add events
        this.eventListeners();
    }

    /**
     * attach events to form builder dom elements 
     */
    eventListeners() {
        $(document).off("click", ".edit-contact").on("click", ".edit-contact", this.editContact.bind(this));
        $(document).off("click", ".edit-contact-pos").on("click", ".edit-contact-pos", this.editContactPOS.bind(this));
        $(document).off("click", "#edit-contacts").on("click", "#edit-contacts", this.validateEditContactFormAndSubmit.bind(this));
        $(document).off("click", ".contact-card").on("click", ".contact-card", this.selectContactForDelete.bind(this));
        $(document).off("click", "#change_people_edit_contacts").on("click", "#change_people_edit_contacts", this.editBookingChangePeopleEditContactsValidation.bind(this));
        $(document).off("click", "#change_people_add_contacts").on("click", "#change_people_add_contacts", this.validateAndSubmitEditBookingAddContactForm.bind(this));
    }

    editContact(event) {
        showLoading();

        var contactId = $(event.currentTarget).attr('id');
        $("#contact_id").val(contactId);
        let getContactDetailsUrl = this.getContactDetails.replace(':id', contactId);

        $.get(getContactDetailsUrl)
            .done(function (response) {
                $("#c_name").val(response.name);
                $("#c_age").val(response.age);
                $("#c_weight").val(response.weight);
                $("#c_note").val(response.note);

                showLoading();
                $("#booking_contacts").modal('show');
            })
            .fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            })
            .always(hideLoading);
    }

    editContactPOS(event) {
        var contactId = $(event.currentTarget).attr('data-contact-id');
        if(contactId.length > 0) {
            this.editContact(event);
        } else {
            $("#booking_contacts").find("input[type='text'], input[type='number'], textarea").val("");
            var transactId = $(event.currentTarget).attr('id');
            $("#transact_id").val(transactId);

            var index = $(event.currentTarget).attr('data-id');
            $("#tourist_index").val(index);
            $("#booking_contacts").modal('show');
        }
    }

    validateEditContactFormAndSubmit() {
        var formId = $("#booking_contacts").find('form').attr('id');
        const form = document.getElementById(formId);

        form.addEventListener('submit', (evt) => {
            // stop the from from refreshing the page
            evt.preventDefault();

            if (!validateCharacters('#c_name'))
                return false;
        
            if (!validateNumbers('#c_age'))
                return false;
        
            showLoading();
            var contactId = $("#contact_id").val();
            var transactId = $("#transact_id").val();
            var bookingId = $("#booking_id").val();
            var index = $("#tourist_index").val();

            var contactData = {
                name: $("#c_name").val(),
                age: $("#c_age").val(),
                weight: $("#c_weight").val(),
                note: $("#c_note").val()
            };

            if(transactId.length > 0 && contactId.length == 0) {
                contactData.transact_id = transactId;
                contactData.booking_id = bookingId;
            } else {
                contactData.contact_id = contactId;
            }

            $('#edit-contacts')[0].disabled = true;
        
            $.post(this.editContactDetails, contactData)
                .done(function (response) {
                    if(transactId.length > 0 && contactId.length == 0) {
                        $(".edit-contact-pos").attr("id", response.id);
                        $(".edit-contact-pos").attr("data-contact-id", response.id);
                        $("#cont_name_" + index).attr("class", "contact_name_" + response.id);
                        $("#cont_age_" + index).attr("class", "contact_age_" + response.id);
                        $("#cont_weight_" + index).attr("class", "contact_weight_" + response.id);
                        $("#edit_booking_cont_name_" + index).attr("class", "contact_name_" + response.id);
                        $("#edit_booking_cont_age_" + index).attr("class", "contact_age_" + response.id);
                        $("#edit_booking_cont_weight_" + index).attr("class", "contact_weight_" + response.id);

                        setTimeout(function() {
                            $(".contact_name_" + response.id).text(response.name);
                            $(".contact_age_" + response.id).text(response.age);
                            $(".contact_weight_" + response.id).text(response.weight);
                        }, 100);
                    } else {
                        $(".contact_name_" + parseInt(contactId)).text(response.name);
                        $(".contact_age_" + parseInt(contactId)).text(response.age);
                        $(".contact_weight_" + parseInt(contactId)).text(response.weight);
                    }
        
                    swal('Contact details updated successfully.', '', 'success');
        
                    hideLoading();
                    $('#edit-contacts')[0].disabled = false;
                    $("#booking_contacts").modal('hide');
                })
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                    $('#edit-contacts')[0].disabled = false;
                })
                .always(hideLoading);
        });
    }

    selectContactForDelete(event) {
        var parentSection = $(event.currentTarget).parent().parent().attr('id');
        var childSection = $(event.currentTarget).parent().attr('id');
        var contactSection = $(event.currentTarget).children().children()[0].id;
        var getContactId = $("#" + parentSection).find("#" + childSection).find("#" + contactSection).val()

        if($($(event.currentTarget).children()[0]).hasClass('d-none')) {
            $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").removeClass('d-none');
            $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").addClass('active-contact');
            $("#" + parentSection).find("#" + childSection).children().css("background-color", "rgb(23 255 23 / 8%)");
            $("#" + parentSection).find("#" + childSection).find("#deleted_contact_id").val(getContactId);
        } else {
            $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").addClass('d-none');
            $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").removeClass('active-contact');
            $("#" + parentSection).find("#" + childSection).children().css('background-color', '#ffffff');
            $("#" + parentSection).find("#" + childSection).find("#deleted_contact_id").val("");
        }

        if(totalDeletedPeopleCount > 0) {
            // When people removed/decreased
            if(isGroupBooking == '1') {
                var selectedContactIds = parseInt($(".change-people-remove-contact-section").find(".select-contact-inactive.active-contact").length);

                if(parseInt(selectedContactIds) > totalDeletedPeopleCount) {
                    $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").addClass('d-none');
                    $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").removeClass('active-contact');
                    $("#" + parentSection).find("#" + childSection).children().css('background-color', '#ffffff');
                    $("#" + parentSection).find("#" + childSection).find("#deleted_contact_id").val("");
                    swal('You can select only ' + totalDeletedPeopleCount + ' contact(s).', '', 'warning');
                    return false;
                }
            } else {
                for (var a = 0; a < touristsGroups.length; a++) {
                    if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists < rateGroups[a].tourists) {
                        var peopleCount = Number(rateGroups[a].tourists - touristsGroups[a].tourists);
                        var selectedContactIds = parseInt($("#contact-section-" + touristsGroups[a].tour_package_rate_id).find(".select-contact-inactive.active-contact").length);

                        if(peopleCount > 0) {
                            if(parseInt(selectedContactIds) > peopleCount) {
                                $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").addClass('d-none');
                                $("#" + parentSection).find("#" + childSection).find(".select-contact-inactive").removeClass('active-contact');
                                $("#" + parentSection).find("#" + childSection).children().css('background-color', '#ffffff');
                                $("#" + parentSection).find("#" + childSection).find("#deleted_contact_id").val("");
                                swal('You can select only ' + peopleCount + ' ' + touristsGroups[a].rate_for + '.', '', 'warning');
                                return false;
                            }
                        }
                    }
                }
            }
        }
    }

    changePeopleResetDeleteContactPopup() {
        $(".change-people-remove-contact-section").find(".select-contact-inactive").removeClass('active-contact');
        $(".change-people-remove-contact-section").find(".select-contact-inactive").addClass('d-none');
        $(".change-people-remove-contact-section").find(".contact-card").css('background-color', '#ffffff');
        $(".change-people-remove-contact-section").find(".contact-delete").val("");
    }

    changePeopleEditContactsValidation(isDeleteAndAdd = false, hasContacts = false) {
        var selectedContactIds = $(".change-people-remove-contact-section").find(".select-contact-inactive.active-contact").length;
									
        if(parseInt(selectedContactIds) == totalDeletedPeopleCount) {
            $('#edit_contacts').data('prevent-hide-callback', true).modal('hide');

            if(!hasContacts) {
                $('#deleteBooking').data('call-by-edit-booking-change-people', true).modal('show');
            }
        } else {
            if(isGroupBooking == '1') {
                var section = $("#change_people_edit_contact_form").children()[1].id;
                $("#" + section).addClass('d-none');

                if(totalDeletedPeopleCount > 0) {
                    $("#" + section).removeClass('d-none');
                }
            } else {
                for (var a = 0; a < touristsGroups.length; a++) {
                    $("#contact-section-" + touristsGroups[a].tour_package_rate_id).addClass('hidden');
                }

                let newBookingContactsUrl = bookingContactsUrl.replace(':bookingId', this.bookingId);
                
                // When people removed/decreased
                $.get(newBookingContactsUrl)
                    .done(function (response) {
                        hideLoading();

                        for (var a = 0; a < touristsGroups.length; a++) {
                            for(var y = 0; y < response.length; y++) {
                                if(response[y].rate_id == touristsGroups[a].tour_package_rate_id) {
                                    if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists < rateGroups[a].tourists) {
                                        var peopleCount = Number(rateGroups[a].tourists - touristsGroups[a].tourists);

                                        if(response[y].contacts == 0 && peopleCount > 0 && totalAddedPeopleCount == 0) {
                                            $('#deleteBooking').data('call-by-edit-booking-change-people', true).modal('show');
                                            return false;
                                        }

                                        if(response[y].contacts == 0 && peopleCount > 0 && totalAddedPeopleCount > 0) {
                                            return tourBookingInfoContacts.editBookingChangePeopleRenderNewContactsPopup(touristsGroups, rateGroups);
                                        }
                
                                        if(hasContacts || isDeleteAndAdd) {
                                            $('#edit_booking_change_people').data('prevent-hide-callback', true).modal('hide');
                                        }

                                        if(peopleCount > 0) {
                                            $("#contact-section-" + touristsGroups[a].tour_package_rate_id).removeClass('hidden');
                                        }
                            
                                        $('#edit_contacts').data('call-by-edit-booking-change-people', true).modal('show');
                                    }
                                }
                            }
                        }
                    })
                    .fail(function (jqXHR) {
                        showSubmissionError(jqXHR);
                    })
                    .always(hideLoading);
                /*for (var a = 0; a < touristsGroups.length; a++) {
                    if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists < rateGroups[a].tourists) {
                        var peopleCount = Number(rateGroups[a].tourists - touristsGroups[a].tourists);
                        var tourist = touristsGroups[a];

                        let newBookingContactsUrl = bookingContactsUrl.replace(':bookingId', this.bookingId)
                            .replace(':rateId', touristsGroups[a].tour_package_rate_id);

                        showLoading();
                        
                        // $.get(newBookingContactsUrl)
                            // .done(function (response) {  
                        var params = {
                            booking_id: this.bookingId,
                            rate_id: touristsGroups[a].tour_package_rate_id
                        };

                        $.post(newBookingContactsUrl, params)
                            .done(function (response) {
                                hideLoading();

                                if(response == 0 && peopleCount > 0 && totalAddedPeopleCount == 0) {
                                    $('#deleteBooking').data('call-by-edit-booking-change-people', true).modal('show');
                                    return false;
                                }

                                // if(response == 0 && peopleCount > 0 && totalAddedPeopleCount > 0) {
                                //     $('#edit_booking_change_people').data('prevent-hide-callback', true).modal('hide');
                                //     $("#edit_booking_add_contacts").data('call-by-edit-booking-change-people', true).modal('show');
                                //     return false;
                                // }

                                if(hasContacts || isDeleteAndAdd) {
                                    $('#edit_booking_change_people').data('prevent-hide-callback', true).modal('hide');
                                }
                    
                                $('#edit_contacts').data('call-by-edit-booking-change-people', true).modal('show');

                                if(peopleCount > 0) {
                                    $("#contact-section-" + tourist.tour_package_rate_id).removeClass('d-none');
                                }
                            })
                            .fail(function (jqXHR) {
                                showSubmissionError(jqXHR);
                            })
                            .always(hideLoading);
                    }
                }*/
            }
        }

        setTimeout(function() {
            $('#change_people_edit_contacts')[0].disabled = false;
        }, 500);
    }

    editBookingChangePeopleEditContactsValidation() {
        if(totalDeletedPeopleCount > 0) {
            // When people removed/decreased
            if(isGroupBooking == '1') {
                var selectedContactIds = parseInt($(".change-people-remove-contact-section").find(".select-contact-inactive.active-contact").length);

                if(parseInt(selectedContactIds) == 0) {
                    swal('Please select any ' + totalDeletedPeopleCount + ' contact(s) you want to remove.', '', 'warning');
                    return false;
                }

                if(parseInt(selectedContactIds) > totalDeletedPeopleCount) {
                    swal('You can select only ' + totalDeletedPeopleCount + ' contact(s).', '', 'warning');
                    return false;
                }
            } else {
                for (var a = 0; a < touristsGroups.length; a++) {
                    if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists < rateGroups[a].tourists) {
                        var peopleCount = Number(rateGroups[a].tourists - touristsGroups[a].tourists);
                        var selectedContactIds = parseInt($("#contact-section-" + touristsGroups[a].tour_package_rate_id).find(".select-contact-inactive.active-contact").length);

                        if(peopleCount > 0) {
                            if(parseInt(selectedContactIds) == 0) {
                                swal('Please select any ' + peopleCount + ' ' + touristsGroups[a].rate_for + ' you want to remove.', '', 'warning');
                                return false;
                            }

                            if(parseInt(selectedContactIds) > peopleCount) {
                                swal('You can select only ' + peopleCount + ' ' + touristsGroups[a].rate_for + '.', '', 'warning');
                                return false;
                            }
                        }
                    }
                }
            }
        }

        if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount > 0) {
            var editBookingChangePeopleForm = $('#edit_booking_change_people').find('form');
            var newForm = $('#form_id')[0];
            validateAndSubmitFormChangePeople(editBookingChangePeopleForm[0], newForm);
        } else {
            this.editBookingChangePeopleRenderNewContactsPopup(touristsGroups, rateGroups, true);
        }

        $('#change_people_edit_contacts')[0].disabled = true;
    }

    editBookingChangePeopleRenderNewContactsPopup(touristsGroups, rateGroups, isDeleteAndAdd = false) {
        $("#new-contacts-section").empty();
        let setContactForm = '';

        for (var a = 0; a < touristsGroups.length; a++) {
            if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists > rateGroups[a].tourists) {
                var peopleCount = Number(touristsGroups[a].tourists - rateGroups[a].tourists);

                if(peopleCount > 0) {
                    /* Rate Header Start */
                    if(a == 0) {
                        setContactForm += `<div class="rate-group-header text-base font-semibold text-[#111827] mb-2" id="rate_${touristsGroups[a].tour_package_rate_id}">`;
                    } else {
                        setContactForm += `<div class="rate-group-header text-base font-semibold text-[#111827] mb-2" id="rate_${touristsGroups[a].tour_package_rate_id}">`;
                    }

                    setContactForm += `<b>${touristsGroups[a].rate_for}</b><span>   [Pax: ${peopleCount}]</span></div>`;
                    /* Rate Header End */

                    /* Rate Wise Content(Contact Forms) Start */
                    setContactForm += `<div class="row add-contact-section" id="rate-contact-section-${touristsGroups[a].tour_package_rate_id}">`;

                    for (var y = 0; y < peopleCount; y++) {
                        setContactForm += `<div class="col-sm-6 col-md-4 contact-section mt-2" id="contact_section_${y}">`;  

                        setContactForm += `<div class="card">
                                <div class="card-header contact-header">
                                    Contact #${y + 1}
                                </div>
                                <div class="card-body add-contact-form">
                                    <input type="hidden" name="contact_rate[]" id="contact_rate" value="${touristsGroups[a].tour_package_rate_id}">
                                    <input type="hidden" name="contact_index[]" id="contact_index" value="${y}">
                                    <div class="form-group flex items-center justify-between mb-2">
                                        <label for="c_name_${touristsGroups[a].tour_package_rate_id}_${y}" class="col-form-label">Name</label>
                                        <div class="field-icon-wrp">
                                            <input type="text" id="c_name_${touristsGroups[a].tour_package_rate_id}_${y}" name="contact_name[]" class="form-control cust-name-field form-custom-input" placeholder="Contact Name" maxlength="80" value="" required>
                                        </div>
                                    </div>
                                    <div class="form-group flex items-center justify-between mb-2">
                                        <label for="c_age_${touristsGroups[a].tour_package_rate_id}_${y}" class="col-form-label">Age</label>
                                        <div class="field-icon-wrp">
                                            <input type="text" id="c_age_${touristsGroups[a].tour_package_rate_id}_${y}" name="contact_age[]" class="form-control age-field form-custom-input" placeholder="Age" value="" data-allowed-numbers="0-9" data-user-display-label="Age" required>
                                        </div>
                                    </div>
                                    <div class="form-group flex items-center justify-between mb-2">
                                        <label for="c_weight_${touristsGroups[a].tour_package_rate_id}_${y}" class="col-form-label">Weight</label>
                                        <div class="field-icon-wrp">
                                            <input type="number" id="c_weight_${touristsGroups[a].tour_package_rate_id}_${y}" name="contact_weight[]" class="form-control weight-field form-custom-input" placeholder="Weight (lbs)" value="" min="0" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="form-group flex items-center justify-between mb-2">
                                        <label for="c_note_${touristsGroups[a].tour_package_rate_id}_${y}" class="col-form-label text-sm font-semibold text-[#111827]">Special Needs</label>
                                        <div class="field-icon-wrp">
                                            <textarea id="c_note_${touristsGroups[a].tour_package_rate_id}_${y}" name="contact_note[]" class="form-control note-field form-custom-input min-h-16" placeholder="Special Needs" value=""></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    }

                    setContactForm += `</div>`;
                    /* Rate Wise Content(Contact Forms) End */
                }
            }
        }

        setContactForm += `<button type="submit" id="change_people_add_contacts" class="themed-btn btn-primary-small">Update & Confirm Booking</button>`;

        $("#new-contacts-section").append(setContactForm);

        if(isDeleteAndAdd) {
            $('#edit_contacts').data('prevent-hide-callback', true).modal('hide');
        } else {
            $('#edit_booking_change_people').data('prevent-hide-callback', true).modal('hide');
        }

        $("#edit_booking_add_contacts").data('call-by-edit-booking-change-people', true).modal('show');
        $('#change_people_add_contacts')[0].disabled = false;
    }

    validateAndSubmitEditBookingAddContactForm(form) {
        var newContacts = [];

        if(isGroupBooking == '1') {
            var a = 0;
            var contactData = [];

            if(totalAddedPeopleCount > 0) {
                for (var y = 0; y < totalAddedPeopleCount; y++) {
					if($("#c_name_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                        swal('Please add contact name for ' + touristsGroups[a].rate_for, '', 'warning');
                        return false;
                    }
                    if($("#c_age_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                        swal('Please add contact age for ' + touristsGroups[a].rate_for, '', 'warning');
                        return false;
                    }
                    if($("#c_weight_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                        swal('Please add contact weight for ' + touristsGroups[a].rate_for, '', 'warning');
                        return false;
                    }

                    contactData.push({
                        index: y,
                        name: $("#c_name_" + touristsGroups[a].tour_package_rate_id + "_" + y).val(),
                        age: $("#c_age_" + touristsGroups[a].tour_package_rate_id + "_" + y).val(),
                        weight: $("#c_weight_" + touristsGroups[a].tour_package_rate_id + "_" + y).val(),
                        note: $("textarea#c_note_" + touristsGroups[a].tour_package_rate_id + "_" + y).val()   
                    });
				}

                newContacts.push({
                    rate: touristsGroups[a].tour_package_rate_id,
                    data: contactData
                });
            }
        } else {
            for (var a = 0; a < touristsGroups.length; a++) {
                var contactData = [];
                
                if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists > rateGroups[a].tourists) {
                    var peopleCount = Number(touristsGroups[a].tourists - rateGroups[a].tourists);

                    if(peopleCount > 0) {
                        for (var y = 0; y < peopleCount; y++) {
                            if($("#c_name_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                                swal('Please add contact name for ' + touristsGroups[a].rate_for, '', 'warning');
                                return false;
                            }
                            if($("#c_age_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                                swal('Please add contact age for ' + touristsGroups[a].rate_for, '', 'warning');
                                return false;
                            }
                            if($("#c_weight_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                                swal('Please add contact weight for ' + touristsGroups[a].rate_for, '', 'warning');
                                return false;
                            }

                            contactData.push({
                                index: y,
                                name: $("#c_name_" + touristsGroups[a].tour_package_rate_id + "_" + y).val(),
                                age: $("#c_age_" + touristsGroups[a].tour_package_rate_id + "_" + y).val(),
                                weight: $("#c_weight_" + touristsGroups[a].tour_package_rate_id + "_" + y).val(),
                                note: $("textarea#c_note_" + touristsGroups[a].tour_package_rate_id + "_" + y).val()   
                            });
                        }

                        newContacts.push({
                            rate: touristsGroups[a].tour_package_rate_id,
                            data: contactData
                        });
                    }
                }
            }
        }

        addedPeopleCountArray = newContacts;
        var editBookingChangePeopleForm = $('#edit_booking_change_people').find('form');
        var newForm = $('#form_id')[0];
        validateAndSubmitFormChangePeople(editBookingChangePeopleForm[0], newForm);
        $('#change_people_add_contacts')[0].disabled = true;

        return false;
    }

    changePeopleDeleteAndNewContactsValidation(formData) {
        var contactIds = $("input[name='deleted_contact_id[]']").map(function(){
            if($(this).val()) {
                return $(this).val();
            }
        }).get();

        if (totalAddedPeopleCount == 0 && totalDeletedPeopleCount > 0) {
            if (paymentMethod == 'Pay Later' || (totalInfantsOrChilds > 0 && totalPaymentAmount == 0 && totalRefundAmount == 0) || (totalPaymentAmount.toFixed(2) == totalRefundAmount.toFixed(2)) || finalAmount.toFixed(2) == -0.00) {
                tourBookingInfoContacts.changePeopleEditContactsValidation(false, true);
    
                if(contactIds.length == 0) {
                    return false;
                }
            }

            formData = formData + '&contacts=' + JSON.stringify(contactIds);
        } else {
            if(totalAddedPeopleCount > 0) {
                if(isGroupBooking == '1') {
                    var a = 0;

                    for (var y = 0; y < totalAddedPeopleCount; y++) {
                        if($("#c_name_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                            return false;
                        }
                        if($("#c_age_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                            return false;
                        }
                        if($("#c_weight_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                            return false;
                        }
                    }
                } else {
                    for (var a = 0; a < touristsGroups.length; a++) {
                        if (typeof (rateGroups[a]) != 'undefined' && touristsGroups[a].tourists > rateGroups[a].tourists) {
                            var peopleCount = Number(touristsGroups[a].tourists - rateGroups[a].tourists);
            
                            if(peopleCount > 0) {
                                for (var y = 0; y < peopleCount; y++) {
                                    if($("#c_name_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                                        return false;
                                    }
                                    if($("#c_age_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                                        return false;
                                    }
                                    if($("#c_weight_" + touristsGroups[a].tour_package_rate_id + "_" + y).val() == "") {
                                        return false;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if(totalDeletedPeopleCount > 0) {
                if (paymentMethod == 'Pay Later' || (totalInfantsOrChilds > 0 && totalPaymentAmount == 0 && totalRefundAmount == 0) || (totalPaymentAmount.toFixed(2) == totalRefundAmount.toFixed(2)) || finalAmount.toFixed(2) == -0.00) {
                    tourBookingInfoContacts.changePeopleEditContactsValidation(false, true);
        
                    if(contactIds.length == 0) {
                        return false;
                    }
                }
            }

            if(totalDeletedPeopleCount > 0 && totalAddedPeopleCount > 0) {
                formData = formData + '&contacts=' + JSON.stringify(contactIds) + '&new_contacts=' + JSON.stringify(addedPeopleCountArray);
            } else {
                formData = formData + '&new_contacts=' + JSON.stringify(addedPeopleCountArray);
            }
        }

        return formData;
    }
}

const tourBookingInfoContacts = new TourBookingInfoContacts({ admin: userType, bookingId: bookingId });