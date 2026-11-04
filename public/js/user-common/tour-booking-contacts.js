class TourBookingContacts { 
    
    /**
     * This is called from the template blade create booking view for package with enabled has contacts feature
     * @param {*} props 
     * @returns 
     */

    constructor(props){
        this.props = props;
        this.admin = ( props.admin === 'Admin' ) ? '/admin' : '';

        this.eventListeners();
    }

    /**
     * attach events to form builder dom elements 
     */
    eventListeners() {
        $(document).off("click", "#add_contacts").on("click", "#add_contacts", this.addContacts.bind(this));
        $(document).off("click", "#add-contact-cancel").on("click", "#add-contact-cancel", this.contactFormCancelBtn.bind(this));
        $(document).off("click", ".delete-contact").on("click", ".delete-contact", this.deleteContact.bind(this));
        $(document).off("click", ".add-contact").on("click", ".add-contact", this.contactFormPopupAddContact.bind(this));
        $(document).off("blur", ".age-field").on("blur", ".age-field", this.validateNumbers.bind(this));
        $(document).off("blur", ".cust-name-field").on("blur", ".cust-name-field", this.validateCharacters.bind(this));
        $(document).off("click", "#add-contact-submit").on("click", "#add-contact-submit", this.saveContacts.bind(this));
    }

    // Event called on `Add Contacts` button
    addContacts() {
        var name = $("#customer_name").val();

        if (isGroupRateEnabled == '1') {

            var groupRate = this.getGroupRatesData();

            if($("#c_name_" + groupRate.rateId +"_" + groupRate.index).val() != "") {
                var customer_name = $("#c_name_" + groupRate.rateId +"_" + groupRate.index).val();
                $("#c_name_" + groupRate.rateId +"_" + groupRate.index).val(customer_name);
            } else {
                if(name != "") {
                    $("#c_name_" + groupRate.rateId +"_" + groupRate.index).val(name);
                }
            }
        } else {
            for (var a = 0; a < rateGroups.length; a++) {
                if(a == 0) {
                    if($("#c_name_" + rateGroups[a].id +"_" + a).val() != "") {
                        var customer_name = $("#c_name_" + rateGroups[a].id +"_" + a).val();
                        $("#c_name_" + rateGroups[a].id +"_" + a).val(customer_name);
                    } else {
                        if(name != "") {
                            $("#c_name_" + rateGroups[a].id +"_" + a).val(name);
                        }
                    }
                }
            }
        }

        $("#booking-add-contacts").modal("show");
    }

    // Event called on `Cancel` button of contact popup
    contactFormCancelBtn() {
        $("#booking-add-contacts").modal("hide");
    }

    validateNumbers() {
        validateNumbers(".age-field");
    }

    validateCharacters() {
        validateCharacters(".cust-name-field");
    }

    initContactForm() {
        $('#add-contacts-form-view').html(bootstrapModalHtml({ id: 'booking-add-contacts', size: "xl", title: 'Add Contacts', content: this.contactFormBodyContent(), close: "  " }));
    }

    // Create/Initialize contact form with empty fields for given index
    initEmptyContactForm(index) {
        var contactFormData = {
            index: index,
            name: "",
            age: "",
            weight: "",
            note: ""
        };

        return contactFormData;
    }

    contactFormBodyContent() {
        let setContactForm = '';
        const getContactData =  $("textarea#contact_data").val();

        if(getContactData == "") {
            if(isGroupRateEnabled == 1) {
                setContactForm += this.createGroupRateContactForm();
            } else {
                setContactForm += this.createContactForm();
            }
        } else {
            if(isGroupRateEnabled == 1) {
                setContactForm += this.editGroupRateContactForm(getContactData);
            } else {
                setContactForm += this.editContactForm(getContactData);
            }
        }

        return setContactForm;
    }

    createContactForm() {
        let createContactForm = '';
        var tourPackageId = $('#tour_package').val();

        if(rateGroupWiseContactsCount > 0) {
            createContactForm += `<form class="contact-form" id="contact-form-${tourPackageId}">`;

            for (var a = 0; a < rateGroups.length; a++) {
                var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
                var tourists = parseInt(selPax.val());

                if(tourists > 0) {
                    createContactForm += `<div id="contact-section-${rateGroups[a].id}">`;

                    createContactForm += this.renderRateHeader(rateGroups[a].id, rateGroups[a].rate_for, a, tourists);

                    // Add more contact button if deleted and needed to add
                    createContactForm += `<div class="row add-contact-section" id="rate-contact-section-${rateGroups[a].id}">`;

                    for (var i = 0; i < tourists; i++) {
                        let setContactFormFields = '';

                        createContactForm += this.contactFormFieldsHTML(setContactFormFields, this.initEmptyContactForm(i), rateGroups[a].id);
                    }

                    createContactForm += `</div>`;

                    createContactForm += `</div>`;
                }
            }

            createContactForm += this.contactFormFooter();
        }

        return createContactForm;
    }

    createGroupRateContactForm() {
        let createContactForm = '';
        var tourPackageId = $('#tour_package').val();
        var groupRate = this.getGroupRatesData();

        if(rateGroupWiseContactsCount > 0) {
            createContactForm += `<form class="contact-form" id="contact-form-${tourPackageId}">`;

            if(groupRate.tourists > 0) {
                createContactForm += `<div id="contact-section-${groupRate.rateId}">`;

                createContactForm += this.renderRateHeader(groupRate.rateId, rateGroups[groupRate.index].rate_for, groupRate.index, groupRate.tourists);

                // Add more contact button if deleted and needed to add
                createContactForm += `<div class="row add-contact-section" id="rate-contact-section-${groupRate.rateId}">`;

                for (var i = 0; i < groupRate.tourists; i++) {
                    let setContactFormFields = '';

                    createContactForm += this.contactFormFieldsHTML(setContactFormFields, this.initEmptyContactForm(i), groupRate.rateId);
                }

                createContactForm += `</div>`;

                createContactForm += `</div>`;
            }

            createContactForm += this.contactFormFooter();
        }

        return createContactForm;
    }

    editContactForm(getContactData) {
        var editContactForm = '';
        var tourPackageId = $('#tour_package').val();
        var cartPackageDetails = getCartPackageDetails(tourPackageId);

        if (cartPackages.length > 0 && cartPackageDetails != "") {
            var contactData = JSON.parse(cartPackageDetails.contact_data);
        }

        if(rateGroups.length > 0) {
            var contactData = this.fillContactFormDataForRateGroups(getContactData);
        }

        var contactJSON = contactData;
        $("#contact_data").val(JSON.stringify(contactJSON));
        editContactForm += this.renderContactForm(contactJSON, tourPackageId);

        return editContactForm;
    }

    editGroupRateContactForm(getContactData) {
        var editGroupRateContactForm = '';
        var tourPackageId = $('#tour_package').val();
        var cartPackageDetails = getCartPackageDetails(tourPackageId);

        if (cartPackages.length > 0 && cartPackageDetails != "") {
            var contactData = JSON.parse(cartPackageDetails.contact_data);
        }

        if(rateGroups.length > 0) {
            var contactData = this.fillGroupRateContactFormDataForRateGroups(getContactData);
        }

        var groupRateContactDetails = contactData;
        $("#contact_data").val(JSON.stringify(groupRateContactDetails));
        editGroupRateContactForm += this.renderContactForm(groupRateContactDetails, tourPackageId);

        return editGroupRateContactForm;
    }

    renderContactForm(contactDetails, tourPackageId) {
        let contactForm = '';

        if(contactDetails.length > 0) {
            contactForm += `<form class="contact-form" id="contact-form-${tourPackageId}">`;

            for(var i = 0; i < contactDetails.length; i++) {
                if(typeof contactDetails[i].data !== undefined) {
                    if(contactDetails[i].data.length > 0) {
                        contactForm += `<div id="contact-section-${contactDetails[i].rate}">`;

                        contactForm += this.renderRateHeader(contactDetails[i].rate, contactDetails[i].rate_for, i, contactDetails[i].data.length);

                        contactForm += this.renderRateWiseContactSection(contactDetails[i].data, contactDetails[i].rate);

                        contactForm += `</div>`;
                    }
                }
            }

            contactForm += this.contactFormFooter();
        }

        return contactForm;
    }

    // Rendered dynamic contact forms content
    renderRateWiseContactSection(contacts, rateId) {
        let setContactSection = '';

        // Add more contact button if deleted and needed to add
        setContactSection += `<div class="row add-contact-section" id="rate-contact-section-${rateId}">`;

        for(var a = 0; a < contacts.length; a++) {
            var setContactFormFields = '';

            setContactSection += this.contactFormFieldsHTML(setContactFormFields, contacts[a], rateId);
        }

        setContactSection += `</div>`;

        return setContactSection;
    }

    // Fill contact form details for integrated contact data JSON with new updates in tourists
    fillContactFormDataForRateGroups(contactInfo) {
        var contactFormInfo = JSON.parse(contactInfo);

        var rates = [];
        var contacts = [];
        var $tblRateGrps = $('#table-rate-groups');

        $.each(contactFormInfo, function(key, value) {
            rates.push(value.rate);
        });

        for (var i = 0; i < rateGroups.length; i++) {
            $row = $($tblRateGrps.children()[i]);
			var $selTourists = $row.find('.tourists');

			if ($selTourists.val() == null) {
				$selTourists.find('option').prop('selected', true);
				var tourists = 0;
			} else {
				var tourists = parseInt($selTourists.val());
			}
            
            if ($selTourists.children().length > 1) {
                if($.inArray(rateGroups[i].id, rates) === -1) {
                    for (var z = 0; z < tourists; z++) {
                        contacts.push(this.initEmptyContactForm(z));
                    }

                    contactFormInfo.push({
                        rate: rateGroups[i].id,
                        rate_for: rateGroups[i].rate_for,
                        data: contacts
                    });
                }

                for (let y = 0; y < contactFormInfo.length; y++) {
                    if(contactFormInfo[y].rate == rateGroups[i].id) {
                        if(tourists == 0) {
                            for(var a = 0; a < contactFormInfo[y].data.length; a++) {
                                contactFormInfo[y].data = [];
                            }
                        } else {
                            if(contactFormInfo[y].data.length > tourists) {
                                // If tourists updated and decreased
                                var remainingTourists = contactFormInfo[y].data.length - tourists;

                                for(var a = 0; a < remainingTourists; a++) {
                                    contactFormInfo[y].data.pop();
                                }
                            }

                            if(contactFormInfo[y].data.length < tourists) {
                                // If tourists updated and increased
                                for(var a = contactFormInfo[y].data.length; a < tourists; a++) {
                                    contactFormInfo[y].data.push(this.initEmptyContactForm(a));
                                }
                            }
                        }
                    }
                }
            }
        }

        return contactFormInfo;
    }

    // Fill contact form details for integrated contact data JSON with new updates in tourists for Group Rates
    fillGroupRateContactFormDataForRateGroups(contactJSON) {
        var groupRateContactDetails = JSON.parse(contactJSON);
        var groupRate = this.getGroupRatesData();

        for (var i = 0; i < groupRateContactDetails.length; i++) {
            if(groupRateContactDetails[i].rate != groupRate.rateId) {
                var newRateId = groupRate.rateId;
            } else {
                var newRateId = groupRateContactDetails[i].rate;
            }

            // Update rate Id in case of pax changed
            groupRateContactDetails[i].rate = newRateId;

            if(newRateId == groupRate.rateId) {
                if(groupRate.tourists == 0) {
                    for(var a = 0; a < groupRateContactDetails[i].data.length; a++) {
                        groupRateContactDetails[i].data = [];
                    }
                } else {
                    if(groupRateContactDetails[i].data.length > groupRate.tourists) {
                        // If tourists updated and decreased
                        var remainingTourists = groupRateContactDetails[i].data.length - groupRate.tourists;

                        for(var a = 0; a < remainingTourists; a++) {
                            groupRateContactDetails[i].data.pop();
                        }
                    }

                    if(groupRateContactDetails[i].data.length < groupRate.tourists) {
                        // If tourists updated and increased
                        for(var a = groupRateContactDetails[i].data.length; a < groupRate.tourists; a++) {
                            groupRateContactDetails[i].data.push(this.initEmptyContactForm(a));
                        }
                    }
                }
            }
        }

        return groupRateContactDetails;
    }

    contactFormFooter() {
        let footerContent = '';

        footerContent += `
            <div class="w-full">
                <div class="btn-group btn-sm mt-3 flex items-center pb-4 px-4 gap-3">
                    <input data="meta-field" id="add-contact-submit" type="submit" class="btn btn-sm btn-primary btn-primary-small !w-full" value="Save Contacts"> 
                    <input data="meta-field" id="add-contact-cancel" type="button" class="btn btn-sm btn-secondary btn-light-small !w-full" value="Cancel"> 
                </div>
            </div>
        </form>`;

        return footerContent;
    }

    // Rendered dynamic rate group header
    renderRateHeader(rateId, rateTitle, index, pax) {
        let setRateHeader = '';

        // Add more contact button if deleted and needed to add
        if(index == 0) {
            setRateHeader += `<div class="rate-group-header bg-[#F9FAFB] border border-[#E5E7EB] rounded-md py-3 px-4 text-sm font-medium text-[#111827] mb-2 flex items-center justify-between" id="rate_${rateId}">`;
        } else {
            setRateHeader += `<div class="rate-group-header bg-[#F9FAFB] border border-[#E5E7EB] rounded-md py-3 px-4 text-sm font-medium text-[#111827] my-2 flex items-center justify-between" id="rate_${rateId}">`;
        }
        setRateHeader += `<span><b>${rateTitle}</b><span>   [Pax: ${pax}]</span></span>
            <button class="btn btn-success btn-sm add-contact btn-secondary-small d-none">
                <i class="fa fa-plus-circle mr-2"></i> Add Contact</button>
        </div>`;

        return setRateHeader;
    }

    // Rendered HTML fields of contact form
    contactFormFieldsHTML(setContactFormFields, element, rateId) {
        setContactFormFields += `<div class="w-full contact-section" id="contact_section_${element.index}">`;    

        setContactFormFields += `<div class="card w-full border border-[#E5E7EB] rounded-md my-2 overflow-hidden">
                <div class="card-header contact-header bg-[#F9FAFB] border-b border-[#E5E7EB] py-3 px-4 text-sm font-medium text-[#111827] flex items-center justify-between">
                    <span>Contact #${element.index + 1}</span>
                    <button type="button" id="delete_contact_${element.index}" class="delete-contact w-5 h-5 ml-2 text-[#B91C1C]" aria-label="Close">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="card-body add-contact-form py-3 px-4">
                    <input type="hidden" name="contact_index" id="contact_index" value="${element.index}">
                    <div class="form-group flex items-center justify-content-between mb-2">
                        <label for="c_name_${rateId}_${element.index}" class="form-custom-label min-w-28">Name</label>
                        <div class="field-icon-wrp w-full">
                            <input type="text" id="c_name_${rateId}_${element.index}" name="c_name[]" class="form-control form-custom-input w-full cust-name-field" placeholder="Contact Name" maxlength="80" value="${element.name}" required>
                        </div>
                    </div>
                    <div class="form-group flex items-center justify-content-between mb-2">
                        <label for="c_age_${rateId}_${element.index}" class="form-custom-label min-w-28">Age</label>
                        <div class="field-icon-wrp w-full">
                            <input type="text" id="c_age_${rateId}_${element.index}" name="c_age[]" class="form-control form-custom-input w-full age-field" placeholder="Age" value="${element.age}" data-allowed-numbers="0-9" data-user-display-label="Age" required>
                        </div>
                    </div>
                    <div class="form-group flex items-center justify-content-between mb-2">
                        <label for="c_weight_${rateId}_${element.index}" class="form-custom-label min-w-28">Weight</label>
                        <div class="field-icon-wrp w-full">
                            <input type="number" id="c_weight_${rateId}_${element.index}" name="c_weight[]" class="form-control form-custom-input w-full weight-field" placeholder="Weight (lbs)" value="${element.weight}" min="0" step="0.01" required>
                        </div>
                    </div>
                    <div class="form-group flex items-center justify-content-between mb-2">
                        <label for="c_note_${rateId}_${element.index}" class="form-custom-label min-w-28">Special Needs</label>
                        <div class="field-icon-wrp w-full">
                            <textarea id="c_note_${rateId}_${element.index}" name="c_note[]" class="form-control form-custom-input w-full min-h-16 note-field" placeholder="Special Needs">${element.note}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;

        return setContactFormFields;
    }

    // Event called on click of `Add Contact` on contact popup when contact deleted and again added
    contactFormPopupAddContact(event) {
        var getSection = $(event.currentTarget).parent().parent();
        let setContactFormFields = '';
        let renderContactForm;

        if (isGroupRateEnabled == '1') {
            var groupRate = this.getGroupRatesData();
            var tourists = groupRate.tourists;

            if(tourists > 0) {
                renderContactForm = this.addContactBtnRenderContactForm(tourists, renderContactForm, getSection, setContactFormFields, groupRate.rateId);
            }
        } else {
            for (var a = 0; a < rateGroups.length; a++) {
                var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');
                var tourists = parseInt(selPax.val());

                if(tourists > 0) {
                    renderContactForm = this.addContactBtnRenderContactForm(tourists, renderContactForm, getSection, setContactFormFields, rateGroups[a].id);
                }
            }
        }

        var section = ($(event.currentTarget).parent().siblings()).attr('id');
        var rateId = section.substring(21);
        $("#" + section).append(renderContactForm);
        this.initAddMoreContact(rateId);
    }

    addContactBtnRenderContactForm(tourists, renderContactForm, getSection, setContactFormFields, rateId) {
        var contactIndexes = [];

        for (var i = 0; i < tourists; i++) {
            var findIndex = $("#" + getSection.attr('id')).find("#contact_section_" + i);

            if(findIndex.length == 0) {
                contactIndexes.push(i);
            }
        }

        if(contactIndexes.length > 0) {
            contactIndexes.sort(function(a, b){
                return b-a;
            });

            for (var y = 0; y < contactIndexes.length; y++) {
                var contactDetails = this.initEmptyContactForm(contactIndexes[y]);
                renderContactForm = this.contactFormFieldsHTML(setContactFormFields, contactDetails, rateId);
            }
        }

        return renderContactForm;
    }
    
    initAddMoreContact(rateId) {
        if (isGroupRateEnabled == '1') {
            var groupRate = this.getGroupRatesData();

            if(groupRate.tourists > 0) {
                var sectionCount = $("#rate-contact-section-" + groupRate.rateId).find('.contact-section').length;

                if(groupRate.tourists > sectionCount && groupRate.rateId == rateId) {
                    $("#rate_" + rateId).find(".add-contact").removeClass('d-none');
                }

                if(groupRate.tourists == sectionCount && groupRate.rateId == rateId) {
                    $("#rate_" + rateId).find(".add-contact").addClass('d-none');
                }
            }
        } else {
            for (var a = 0; a < rateGroups.length; a++) {
                var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');

                if(parseInt(selPax.val()) > 0) {
                    var sectionCount = $("#rate-contact-section-" + rateGroups[a].id).find('.contact-section').length;

                    if(parseInt(selPax.val()) > sectionCount && rateGroups[a].id == rateId) {
                        $("#rate_" + rateId).find(".add-contact").removeClass('d-none');
                    }

                    if(parseInt(selPax.val()) == sectionCount && rateGroups[a].id == rateId) {
                        $("#rate_" + rateId).find(".add-contact").addClass('d-none');
                    }
                }
            }
        }
    }

    // This function is only for group rates feature like (CDC)
    getGroupRatesData() {
        var a = 0;
        var $tblRateGrps = $('#table-rate-groups');
        $row = $($tblRateGrps.children()[a]);
        var $selTourists = $row.find('.tourists');
        var rateGrpId = $selTourists.data('rategrp');
        var oldTourists = 0;

        for (var b = 0; b < rateGroups.length; b++) {
            if(rateGroups[b].cart_tourists > 0) {
                oldTourists = rateGroups[b].cart_tourists;
            }
        }

        if(oldTourists > 0 && $selTourists.val() == 0) {
            $selTourists.val(oldTourists);
        }

        if ($selTourists.val() == null) {
            $selTourists.find('option').prop('selected', true);
            var contactCount = 0;
        } else {
            var contactCount = parseInt($selTourists.val());
        }

        if (Object.keys(paxWiseGroupRates).length > 0 && Object.keys(paxWiseGroupRateIds).length > 0 && contactCount > 0) {
            var lastelement = Object.keys(paxWiseGroupRates)[Object.keys(paxWiseGroupRates).length - 1];

            if (paxWiseGroupRates[contactCount] !== undefined && paxWiseGroupRateIds[contactCount] !== undefined && paxWiseGroupAdditionalCharges[contactCount] !== undefined) {
                rateGrpId = paxWiseGroupRateIds[contactCount];
            } else {
                rateGrpId = paxWiseGroupRateIds[lastelement];
            }
        }

        return {
            rateId: rateGrpId,
            tourists: contactCount,
            index: a
        }
    }

    // Event called on `cross/delete` button of contact form
    deleteContact(event) {
        var contactSection = $(event.currentTarget).parent().parent().parent().parent().parent();
        var rateId = (contactSection.attr('id')).substring(16);
        const getContactData =  $("textarea#contact_data").val();

        if(getContactData != "") {
            var contactSec = $(event.currentTarget).parent().parent().parent().attr('id');
            var contactSectionId = contactSec.substring(16);

            var contactData = JSON.parse(getContactData);

            for (var y = 0; y < contactData.length; y++) {
                if(contactData[y].data.length > 0) {
                    for (var z = 0; z < contactData[y].data.length; z++) {
                        if(contactSectionId == contactData[y].data[z].index) {
                            contactData[y].data[z] = {
                                index: z,
                                name: "",
                                age: "",
                                weight: "",
                                note: ""
                            };
                        }
                    }
                }
            }

            $("#contact_data").val(JSON.stringify(contactData));
        }

        $(event.currentTarget).parent().parent().parent().remove();
        this.initAddMoreContact(rateId);
    }

    // Event called on `Save Contacts` button on contact popup
    saveContacts() {
        // Save contacts in local storage when click on save button
        let rateWiseContactDetails = [];

        var formId = $("#booking-add-contacts").find('form').attr('id');
        const form = document.getElementById(formId);

        form.addEventListener('submit', (evt) => {
            // stop the from from refreshing the page
            evt.preventDefault();
            
            if (isGroupRateEnabled == '1') {
                let contactData = [];
                var groupRate = this.getGroupRatesData();

                if(groupRate.tourists > 0) {
                    var sectionCount = $("#rate-contact-section-" + groupRate.rateId).find('.contact-section').length;

                    if(groupRate.tourists != sectionCount) {
                        var remainingPax = groupRate.tourists - sectionCount;
                        swal('Please add contact information for ' + remainingPax + ' pax', '', 'warning');
                        return false;
                    }

                    var section = $("#rate-contact-section-" + groupRate.rateId + " .contact-section");

                    if(section.length > 0) {
                        for (var i = 0; i < section.length; i++) {
                            var cname = section.find("#c_name_" + groupRate.rateId + "_" + i).val();
                            var cage = section.find("#c_age_" + groupRate.rateId + "_" + i).val();
                            var cweight = section.find("#c_weight_" + groupRate.rateId + "_" + i).val();
                            var cnote = section.find("textarea#c_note_" + groupRate.rateId + "_" + i).val();

                            contactData.push({
                                index: i,
                                name: cname,
                                age: cage,
                                weight: cweight,
                                note: cnote
                            });
                        }
                    }

                    // Initialize rate wise contact details array
                    rateWiseContactDetails.push({
                        rate: groupRate.rateId,
                        rate_for: groupRate.rate_for,
                        data: contactData
                    });
                }
            } else {
                for (var a = 0; a < rateGroups.length; a++) {
                    let contactData = [];
                    var selPax = $($('#table-rate-groups').children()[a]).find('.tourists');

                    if(parseInt(selPax.val()) > 0) {
                        var sectionCount = $("#rate-contact-section-" + rateGroups[a].id).find('.contact-section').length;

                        if(parseInt(selPax.val()) != sectionCount) {
                            var remainingPax = parseInt(selPax.val()) - sectionCount;
                            swal('Please add contact information for ' + remainingPax + ' ' + rateGroups[a].rate_for, '', 'warning');
                            return false;
                        }

                        var section = $("#rate-contact-section-" + rateGroups[a].id + " .contact-section");

                        if(section.length > 0) {
                            for (var i = 0; i < section.length; i++) {
                                var cname = section.find("#c_name_" + rateGroups[a].id + "_" + i).val();
                                var cage = section.find("#c_age_" + rateGroups[a].id + "_" + i).val();
                                var cweight = section.find("#c_weight_" + rateGroups[a].id + "_" + i).val();
                                var cnote = section.find("textarea#c_note_" + rateGroups[a].id + "_" + i).val();

                                contactData.push({
                                    index: i,
                                    name: cname,
                                    age: cage,
                                    weight: cweight,
                                    note: cnote
                                });
                            }
                        }

                        // Initialize rate wise contact details array
                        rateWiseContactDetails.push({
                            rate: rateGroups[a].id,
                            rate_for: rateGroups[a].rate_for,
                            data: contactData
                        });
                    }
                }
            }

            // Save data in local storage
            $("#contact_data").val(JSON.stringify(rateWiseContactDetails));
            $("#booking-add-contacts").modal("hide");
        });
    }

    newBookingValidation() {
        const getContactData =  $("textarea#contact_data").val();

		if (isGroupRateEnabled == '1') {
			var groupRate = this.getGroupRatesData();

			if(getContactData == "") {
				swal('Please add contact information for selected tourists.', '', 'warning');
				return false;
			} else {
				var contactData = JSON.parse(getContactData);
				
				for (var y = 0; y < contactData.length; y++) {
					if(contactData[y].rate == groupRate.rateId) {
						var selectedTourists = parseInt($selTourists.val());
	
						if(selectedTourists > contactData[y].data.length) {
							var remainingPax = selectedTourists - contactData[y].data.length;
							swal('Please add contact information for selected ' + remainingPax + ' pax', '', 'warning');
							return false;
						}
	
						if(contactData[y].data.length > 0) {
							for (var z = 0; z < contactData[y].data.length; z++) {
								var cc = contactData[y].data[z];
								
								if(cc.name == "" || $("#c_name_" + groupRate.rateId + "_" + z).val() == "") {
									swal('Please add contact name(s) for selected pax.', '', 'warning');
									return false;
								}
	
								if(cc.age == "" || $("#c_age_" + groupRate.rateId + "_" + z).val() == "") {
									swal('Please add age for selected pax.', '', 'warning');
									return false;
								}
	
								if(cc.weight == "" || $("#c_weight_" + groupRate.rateId + "_" + z).val() == "") {
									swal('Please add weight for selected pax.', '', 'warning');
									return false;
								}
							}
						}
					}
				}	
			}
		} else {
			for (var i = 0; i < rateGroups.length; i++) {
				var selPax = $($('#table-rate-groups').children()[i]).find('.tourists');
		
				if(getContactData == "") {
					swal('Please add contact information for selected tourists.', '', 'warning');
					return false;
				} else {
					var contactData = JSON.parse(getContactData);
					
					for (var y = 0; y < contactData.length; y++) {
						if(contactData[y].rate == rateGroups[i].id) {
							var selectedTourists = parseInt(selPax.val());
		
							if(selectedTourists > contactData[y].data.length) {
								var remainingPax = selectedTourists - contactData[y].data.length;
								swal('Please add contact information for ' + remainingPax + ' ' + rateGroups[i].rate_for, '', 'warning');
								return false;
							}
		
							if(contactData[y].data.length > 0) {
								for (var z = 0; z < contactData[y].data.length; z++) {
									var cc = contactData[y].data[z];
									
									if(cc.name == "" || $("#c_name_" + rateGroups[i].id + "_" + z).val() == "") {
										swal('Please add contact name(s) for remaining ' + rateGroups[i].rate_for + '.', '', 'warning');
										return false;
									}
		
									if(cc.age == "" || $("#c_age_" + rateGroups[i].id + "_" + z).val() == "") {
										swal('Please add age for remaining ' + rateGroups[i].rate_for + '.', '', 'warning');
										return false;
									}
		
									if(cc.weight == "" || $("#c_weight_" + rateGroups[i].id + "_" + z).val() == "") {
										swal('Please add weight for remaining ' + rateGroups[i].rate_for + '.', '', 'warning');
										return false;
									}
								}
							}
						}
					}	
				}		
			}
		}

        return true;
    }
}

const tourBookingContacts = new TourBookingContacts({ admin: userType });