class CustomFormBuilder {

    /**
     * This is called from the template blade view to init the builder
     * @param {*} props 
     * @returns 
     */
    static initCustomFormBuilder(props = {}) {
        return new CustomFormBuilder(props);
    }

    /**
     * Set base vars and call init
     * @param {*} props 
     */
    constructor(props) {
        this.package_id = parseInt(props.tour_package_id);
        this.form = { id: null, name: "", description: "", fields: [], package_id: this.package_id, status: "inactive" };
        this.field = null;
        this.get_url = props.get_url;
        this.save_url = props.save_url;
        this.del_url = props.del_url;
        this.enablePrice = true;
        this.formtypes = this.fieldTypes();
        this.visibilityOptions = this.visibilityOptions();
        this.getFormBySelectedPackageId();
    }

    /**
     * We have to check to see if the user saved any forms for this package
     */
    getFormBySelectedPackageId() {
        const endpoint = `${this.get_url}/${this.package_id}`;
        $.get(endpoint).done(this.getFormBySelectedPackageOK.bind(this)).fail(this.onError.bind(this));
    }

    /**
     * after we check if the users has a form saved for this package init the view
     * @param {*} response 
     */
    getFormBySelectedPackageOK(resp) {

        // add all the dom elements to the screen
        this.init();

        // if we have a saved from then load it so the user can view it / edit it
        if (resp.form) {
            // if we have a new form allow the user to delete it 
            $("#custom-form-delete-btn").css('display', 'block');
            //set the form values
            $("#custom-form-name").val(resp.form.form_name);
            $("#custom-form-name-ui").html(resp.form.form_name);
            $("#custom-form-description").val(resp.form.description);
            $("#custom-form-status").val(resp.form.status).change();
            this.form.id = parseInt(resp.form.id);
            this.form.name = resp.form.form_name;
            this.form.description = resp.form.description
            this.form.status = resp.form.status || 'inactive';

            let formFields = resp.form.form_fields;

            if (typeof formFields === 'string') {
                formFields = JSON.parse(formFields);
            }

            $.each(formFields, function(key, field) {
                if (!field.hasOwnProperty('visibility')) {
                    field.visibility = "both";
                }
            });

            resp.form.form_fields = formFields;
            this.form.fields = resp.form.form_fields;
            // this.form.fields = JSON.parse(resp.form.form_fields);
            this.form.package_id = parseInt(resp.form.form_package_id);
            this.renderFormFields();

        } else {
            // default the name to Package Options
            let default_name = $("#name").val() + " Addons";
            $("#custom-form-name").val(default_name);
            $("#custom-form-name-ui").html(default_name);
            this.form.name = default_name;
        }

    }

    /**
     * append the form builder to the main div 
     */
    init() {
        // add form to dom 
        const view = $('#tour-package-custom-form');
        view.html(this.formTemplate());
        view.append(this.modalTemplate());

        const toast = `<div id="custom-form-messages" style="opacity:.8;  position: fixed;
                        margin-left: auto;
                        margin-right: auto;
                        z-index: 1000;
                        width: 50%;
                        left: 0;
                        top: 40%;
                        right: 0; "> </div>`;
        //messages
        $("body").prepend(toast);

        //add events
        this.eventListeners();
    }

    /**
     * attach events to form builder dom elements 
     */
    eventListeners() {

        // on keyup update the form name in the this.form.name config object
        $(document).off("keyup", "#custom-form-name").on("keyup", "#custom-form-name", this.setFormName.bind(this));
        // on keyup update the form description in the this.form.name config object
        $(document).off("keyup", "#custom-form-description").on("keyup", "#custom-form-description", this.setFormDescription.bind(this));
        // on change set publish status 
        $(document).off("change", "#custom-form-status").on("change", "#custom-form-status", this.setFormStatus.bind(this));
        //add field btn 
        $(document).off("click", "#custom-form-type-next-btn").on("click", "#custom-form-type-next-btn", this.addField.bind(this));
        //preview btn
        $(document).off("click", ".custom-form-field-preview").on("click", ".custom-form-field-preview", this.formFieldPreview.bind(this));
        //cancel edit btn
        $(document).off("click", "#custom-form-type-next-cancel").on("click", "#custom-form-type-next-cancel", this.resetForm.bind(this));
        // on click remove a field from the form 
        $(document).off("click", ".delete-field").on("click", ".delete-field", this.removeField.bind(this));
        // on click edit field
        $(document).off("click", ".edit-field").on("click", ".edit-field", this.editField.bind(this));
        // on field type select update the add field form
        $(document).off("change", "#custom-form-type-select").on("change", "#custom-form-type-select", this.onFieldSelectChange.bind(this));
        // on sort adjust order of items 
        $("#custom-form-field-list").sortable({ stop: this.reorderFields.bind(this) });
        // add a option field adds the option to this div nested in the form option custom-form-radio-options
        $(document).off("click", ".custom-form-radio-option-add").on("click", ".custom-form-radio-option-add", this.addRadioOptionField.bind(this));
        $(document).off("click", ".delete-option-field").on("click", ".delete-option-field", this.removeRadioOption.bind(this));//(evt)=>{$(evt.target).parent().remove();});
        // add a option to select 
        $(document).off("click", ".custom-form-select-option-add").on("click", ".custom-form-select-option-add", this.addSelectOptionField.bind(this));
        $(document).off("click", ".delete-select-field").on("click", ".delete-select-field", this.removeSelectOption.bind(this));//(evt)=>{$(evt.target).parent().remove();});
        // display price options
        $(document).off("click", "#custom-form-field-price").on("click", "#custom-form-field-price", this.renderPriceOptions.bind(this));
        //show info popup
        $(document).on("click", ".show-popup", (evt) => {
            // $('.show-popup').popover('hide');
            const ele = $(evt.target);
            const content = ele.attr('data');
            ele.popover({ content: `${content}`, placement: "top", html: true });
        });

        // save the form
        $(document).off("click", "#custom-form-save-btn").on("click", "#custom-form-save-btn", this.saveForm.bind(this));

        // delete the form 
        $(document).off("click", "#custom-form-delete-btn").on("click", "#custom-form-delete-btn", this.deleteForm.bind(this));

        // debug the json object // uncomment the btn below with id custom-form-json-btn
        $(document).off("click", "#custom-form-json-btn").on("click", "#custom-form-json-btn", (_evt) => {
            this.modalShow("Form Object", JSON.stringify(this.form));
        });

    }

    /**
     * Allows Form Items
     * @returns array[object]
     */
    fieldTypes() {
        return [
            { "id": "1", "name": "Text", "type": "text" },
            { "id": "2", "name": "Text Box", "type": "textarea" },
            { "id": "3", "name": "Check Box", "type": "checkbox" },
            { "id": "4", "name": "Radio Button", "type": "radio" },
            { "id": "5", "name": "Drop Down Box", "type": "select" },
            { "id": "6", "name": "Number", "type": "number" }
        ]
    }

    /* On keydown event on the form name input update the this.form.name value */
    setFormName(evt = null) {

        if (evt && evt !== undefined) {
            this.form.name = evt.target.value.trim();
        }

        if (this.form.name == "") {
            $("#custom-form-name-ui").html("New Form: <small>Please Enter A Name For Your Form Above</small>");
        } else {
            $("#custom-form-name-ui").html(this.form.name);
        }
    }

    /* on Keydown event of form description input update this.form.description value */

    setFormDescription(evt = null) {

        this.form.description = evt.target.value.trim();


    }


    /**
     * Set inactive / active to show on the front end
     * @param {*} evt 
     */
    setFormStatus(evt) {
        this.form.status = evt.target.value;
    }

    /** 
     * form names need to be unique check if the form field name exists 
    */
    formFieldExists(name) {
        let fields = this.form.fields.filter((f) => f.name === name);
        return fields.length > 0;
    }


    formFieldPreview(evt) {

        const type = $("#custom-form-type-select").val();
        if (type === "") { return false; }

        // collect the data for the preview
        let content = "";
        let name = $("#custom-form-field-name").val();
        let title = `${name} Field Preview`;
        let values = this.getNewFormFieldData();
        let field = values.field

        if (!field) {
            return false;
        }

        // text input
        if (field.type === 'text') {
            content += this.textField(field, this.editorLabel(field));
        }

        // textarea
        if (field.type === 'textarea') {
            content += this.textAreaField(field, this.editorLabel(field));
        }

        if (field.type === 'checkbox') {
            content += this.checkboxField(field, this.editorLabel(field));
        }

        // radio 
        if (field.type === 'radio') {
            field.attrs.options = this.collectOptionFields("#custom-form-radio-options", this.toID(field.name));
            content += this.radioField(field, this.editorLabel(field));
        }

        //select 
        if (field.type === 'select') {
            field.attrs.options = this.collectOptionFields("#custom-form-select-options");
            content += this.selectField(field, this.editorLabel(field));
        }

        // number 
        if (field.type === 'number') {
            content += this.numberField(field, this.editorLabel(field));
        }

        // display the modal win with field preview 
        this.modalShow(title, `<div class="p-3">${content}</div>`);

    }

    /**
     * This shows the add form field form 
     * on form type select show add form config for each field type
     * @param {*} evt 
     */
    onFieldSelectChange(evt) {

        const fid = $("#custom-form-field-id").val().trim();
        let field = {};
        if (fid != "") {
            field = this.form.fields.filter((f) => f.id === fid)[0];
        }

        this.renderFieldForm($(evt.target).val(), field);
    }

    /**
     * Display field SubForm by type 
     * display a form for each custom type of field to render the dynamic form to the editor 
     * @param {*} typ  the type of field we are adding to the form 
     * @param {*} id  if we edit this is the id to update in the form.fields object
     * @param {*} field if we edit a field we pass values via this object
     */
    renderFieldForm(typ, field = {}) {
        let input = "";
        if (typ === "number") {
            input = `
                <div>
                    ${this.renderLabel("Min", "custom-attr-for-num-min")}
                    <input class="form-control form-custom-input" type="number" id="custom-attr-for-num-min" value="${this.editVal('attrs.min', field, -1)}"  /> 
                    ${this.renderLabel("Max", "custom-attr-for-num-max")}
                    <input class="form-control form-custom-input" type="number" id="custom-attr-for-num-max" value="${this.editVal('attrs.max', field, -1)}" />
                    <div><small>-1/-1 for no min max</small></div>
                </div>
            `;
        }

        if (typ === "checkbox") {
            input = `<input class="" type="hidden" id="custom-attr-for-ckbox-val" value="true" />`;
        }

        if (typ === "radio") {
            input = `
            <div>
                <strong>Radio Options</strong> <br />
                <div class="p-3" id="custom-form-radio-options"> </div>
                
                <button class="btn btn-sm btn-primary custom-form-radio-option-add btn-primary-small">Add Option</button> 
            </div>
            `;
        }

        if (typ === "select") {
            input = `
            <div>
                <strong>Select Options</strong> <br />
                <div class="p-3" id="custom-form-select-options"> </div>
                
                <button class="btn btn-sm btn-primary custom-form-select-option-add btn-primary-small">Add Option</button> 
            </div>
            `;
        }

        $("#custom-field-load-template").html(input);
        if (typ === "select") { this.addSelectOptionField(null, field); }
        if (typ === "radio") { this.addRadioOptionField(null, field); }

    }


    /**
     * compile the input from the from into a field 
     * @returns object
     */
    getNewFormFieldData() {

        const typ = $("#custom-form-type-select").val();
        const nme = $("#custom-form-field-name").val();
        const req = $('#custom-form-field-req:checked').length ? true : false;
        const err = []; this.updateFormError(err); //reset error 
        const fid = $("#custom-form-field-id").val().trim(); //existing field id
        const visibility = $("#custom-form-visibility-select").val();

        // make sure the name is not blank 
        if (nme.trim() === "") { err.push('Field Name can not be empty'); }

        //make sure the type is not blank 
        if (typ.trim() === "") { err.push('Field Type can not be empty'); }

        // if we are not updating then we have to check if the field already exists 
        if (!fid || fid === "") {
            if (this.formFieldExists(nme)) { err.push('Field name already exists'); }
        }

        //make sure they have no empty values 
        if (typ === 'radio' || typ === 'select') {
            let options = this.collectOptionFields(`#custom-form-${typ}-options`, this.toID(nme));
            options.map((o) => { if (o.value.trim() === '') { err.push(`Field ${nme} : ${o.name} option can not be blank`) } });
        }


        // if error return false
        if (err.length > 0) { this.updateFormError(err); return false; }

        // init the form field model 
        return {
            field: {
                id: `input_${this.slugify(nme.trim())}_${Date.now()}`,
                name: nme.trim(),
                type: typ,
                description: $("#custom-form-field-info").val(),
                default: "",
                order: this.form.fields.length,
                required: req,
                attrs: {},
                priceInfo: this.price(),
                visibility: visibility

            }, existing_id: fid
        };
    }


    /**
     * push the add form data into: this.form.fields object
     * @param {*} evt 
     * @returns 
     */
    addField(_evt) {

        //get the field data from the new/edit field form
        const values = this.getNewFormFieldData();
        const field = values.field;
        const existing_id = values.existing_id;

        if (!values.field) {
            return false;
        }

        // add the new form field to the fields array based on type
        if (field.type === 'text') {
            this.upsertField(field, existing_id);
        }

        if (field.type === 'textarea') {
            this.upsertField(field, existing_id);
        }

        if (field.type === 'number') {
            field.attrs.min = $("#custom-attr-for-num-min").val();
            field.attrs.max = $("#custom-attr-for-num-max").val();
            this.upsertField(field, existing_id);
        }

        if (field.type === 'checkbox') {
            field.default = $("#custom-attr-for-ckbox-val").val();
            this.upsertField(field, existing_id);
        }

        if (field.type === 'radio') {
            field.attrs.options = this.collectOptionFields("#custom-form-radio-options", this.toID(field.name));
            this.upsertField(field, existing_id);
        }

        if (field.type === 'select') {
            field.attrs.options = this.collectOptionFields("#custom-form-select-options");
            this.upsertField(field, existing_id);
        }

        this.renderFormFields();
        this.resetForm();
        this.renderSaveReminder();

        return true;
    }

    /**
     * upsertField 
     * @param {*} field // the field object 
     * @param {*} id // string id if updating a field
     */
    upsertField(field, id = "") {
        // if id exists then make the form update the existing object in the fields array 
        if (id && id != "") {
            const fields = this.form.fields;
            const idx = fields.map(f => f.id).indexOf(id);
            field.id = id;
            fields[idx] = field;
            this.form.fields = [...fields];
        } else {
            // if this is a new field then append it to the fields array 
            this.form.fields.push(field);
        }

    }

    /**
     * saveForm
     * Save the custom form 
     * Take the values in this.form object and save the json to the db
     */
    saveForm() {
        this.renderSaveReminder(false);
        // validate tour pkg
        if ($("#tour_packages").val() === "") {
            return this.onError("Please Select Tour Package");
        }
        // validate field name
        if (this.form.name === "") {
            $("#custom-form-name").focus();
            return this.onError("Custom Form Name Can Not Be Blank");
        }
        // vallidate a field is set
        if (this.form.fields.length == 0) {
            return this.onError("Custom Form Contains No Fields");
        }
        // disable the save btn so not to save add a field twice
        $("#custom-form-save-btn").prop('disabled', true);

        const csrf = $('meta[name="csrf-token"]').attr('content');

        $.ajax({
            type: "POST",
            url: `${this.save_url}`,
            data: this.form,
            headers: { 'X-CSRF-TOKEN': csrf },
            success: this.saveFormOk.bind(this),
            dataType: 'json'
        }).fail((err) => this.onError(err.responseJSON.message));
    }

    /**
     * Save custom form success method 
     * @param {*} response 
     */
    saveFormOk(response) {
        if (!response.success) {
            this.onError(response.error);
            return false;
        }

        if (response.action === 'insert') {
            this.form.id = parseInt(response.id);
        }

        // if we have a new form allow the user to delete it 
        if (this.form.id) {
            $("#custom-form-delete-btn").css('display', 'block');
        }


        this.renderMsg("Form Saved Successfully", "success", 3000);

        $("#custom-form-save-btn").prop('disabled', false);

        return true;
    }

    /**
     * @param {*} msg // str / html message to display 
     * @param {*} typ // success, info, danger 
     * @param {*} exp // number to sections to remove messge -1 for never
     */
    renderMsg(msg = "", typ = "success", exp = 5000) {

        let alert = `<div class="alert alert-${typ} fade show" role="alert">
                        <div class="btn btn-sm close" style="float:right; clear: none; margin-top: -10px;" onclick="this.parentElement.style.display = 'none';" >&times;</div>
                        <div> ${msg} </div>
                    </div>`;


        $("#custom-form-messages").html(alert);

        if (exp > -1) {
            setTimeout(() => {
                $("#custom-form-messages").html("");
            }, exp);
        }

    }

    /**
     * Destory this form
     */
    deleteForm() {
        const deleteOnConfirm = (deleteIt = false) => {
            if (!deleteIt) { return false; }
            this.renderSaveReminder(false);
            const csrf = $('meta[name="csrf-token"]').attr('content');
            const data = { _token: csrf, _method: "delete" };
            $.ajax({
                type: "POST",
                url: `${this.del_url}/${this.form.id}/${this.form.package_id}`,
                data: data,
                headers: { 'X-CSRF-TOKEN': csrf },
                success: this.deleteFormOk.bind(this),
                dataType: 'json'
            }).fail((err) => this.onError(err.responseJSON.message));
        }; //end deleteOnConfirm

        swal({
            title: "Are you sure you want to delete?",
            text: "You will not be able to recover this form",
            icon: "warning",
            buttons: ['Cancel', 'Delete'],
            dangerMode: true,
        }).then((del) => deleteOnConfirm(del))
    }

    /**
     * Delete the form 
     * @param {*} response 
     */
    deleteFormOk(response) {
        if (response.success) {
            this.form = { id: null, description: "", name: "Checkout Options", fields: [], package_id: this.package_id };
            this.resetForm();
            this.renderFormFields();
            $("#custom-form-delete-btn").css("display", "none");
        }
    }


    /** ----------  form templates --------  */
    /**
     *  Radio Button Options
     *  Add or Edit a Radio Group 
     * @param {*} evt 
     */
    addRadioOptionField(_evt, field = {}) {
        // add a option field adds the option to this div nested in the form option custom-form-radio-options
        const tag = (props) => {
            return `<div class="form-control form-custom-input !h-auto radio-option" id="radioOption${props.len}">
                    <input type="text" class="option-name" id="optionName${props.len}" value="${props.name}" placeholder="Label" /> <br />
                    <input type="text" class="option-value" id="optionValue${props.len}" value="${props.value}" placeholder="Value" /> 
                    ${this.renderIcon("fas fa-trash-alt delete-option-field float-end btn")}
                </div>`
        }
        // EDIT: if we are editing a field then we need to loop over the items in the attrs list
        if (Object.keys(field).length > 0 && field.hasOwnProperty('attrs')) {
            let tags = field.attrs.options.map((o, i) => tag({ len: i, name: o.name, value: o.value }));
            $("#custom-form-radio-options").html(tags.join("\n"));
        } else {
            // ADD: else this is a new form and we can show the empty field to add 
            const length = $("#custom-form-radio-options").children().length;
            $("#custom-form-radio-options").append(tag({ len: length, name: "", value: "" }));
        }
    }


    /**
     * Radio or select options
     * display a list of Options
     */
    collectOptionFields(id, prefix = "radioOption") {
        let radiosBtnsOptions = [];
        $(id).children().each((idx, ele) => {
            let name = $(`#optionName${idx}`);
            let value = $(`#optionValue${idx}`);
            radiosBtnsOptions.push({
                id: `${prefix}${idx}`,
                name: name.val(),
                value: value.val()
            });
        });

        return radiosBtnsOptions;

    }
    /**
     * remove item from options of dropdown
     * @param {*} evt 
     */
    removeSelectOption(evt) {
        $(evt.target).parent().remove();
        $("#custom-form-select-options .select-option").each(function (index) {
            var row = $(this);
            row.attr('id', `selectOption${index}`);
            row.find('.option-name').attr('id', `optionName${index}`);
            row.find('.option-value').attr('id', `optionValue${index}`);
        });
    }
    /**
     * remove item from radio buttons
     * @param {*} evt 
     */
    removeRadioOption(evt) {
        $(evt.target).parent().remove();
        $("#custom-form-radio-options .radio-option").each(function (index) {
            var row = $(this);
            row.attr('id', `radioOption${index}`);
            row.find('.option-name').attr('id', `optionName${index}`);
            row.find('.option-value').attr('id', `optionValue${index}`);
        });
    }

    /** Select Options */
    addSelectOptionField(_evt, field = {}) {
        const tag = (props) => {
            return ` <div class="form-control form-custom-input !h-auto select-option" id="selectOption${props.len}">
                        <input type="text" class="option-name" id="optionName${props.len}" value="${props.name}" placeholder="Label" /> <br />
                        <input type="text" class="option-value" id="optionValue${props.len}" value="${props.value}" placeholder="Value" /> 
                        ${this.renderIcon("fas fa-trash-alt delete-select-field float-end btn ")}
                    </div>`;
        }

        // EDIT: if we are editing a field then we need to loop over the items in the attrs list
        if (Object.keys(field).length > 0 && field.hasOwnProperty('attrs')) {
            let tags = field.attrs.options.map((o, i) => tag({ len: i, name: o.name, value: o.value }));
            $("#custom-form-select-options").html(tags.join("\n"));
        } else {
            // ADD: else if we are adding an option for the select than display empty name/value field set
            let length = $("#custom-form-select-options").children().length;
            $("#custom-form-select-options").append(tag({ len: length, name: "", value: "" }));
        }
    }

    /**
     * remove item form this.form.fields object
     * @param {*} evt 
     */
    removeField(evt) {
        const deleteOnConfirm = (del) => {
            if (!del) { return false; }
            const ele = $(evt.target);
            const id = ele.attr('data');
            const fields = this.form.fields.filter(f => f.id !== id);
            this.form.fields = [...fields];
            this.renderFormFields();
            this.resetForm();
            this.renderSaveReminder();
        };

        swal({
            title: "Are you sure you want to delete this field?",
            text: "You will not be able to recover this field",
            icon: "warning",
            buttons: ['Cancel', 'Delete'],
            dangerMode: true,
        }).then((del) => deleteOnConfirm(del));

    }

    /**
     * Edit a field 
     * @param {*} evt 
     */
    editField(evt) {

        // get the form id from the dom object and search the form fields array
        const ele = $(evt.target);
        const id = ele.attr('data');
        this.resetForm();
        this.field = this.form.fields.filter(f => f.id === id).shift();

        //populate the field form with the values from the form field  object
        $("#custom-form-field-id").val(this.field.id);
        $("#custom-form-field-name").val(this.field.name);
        $("#custom-form-type-select").val(this.field.type);
        $("#custom-form-field-info").val(this.field.description);
        //if the price is enabled then fill out the price fields and expand the dropdown
        if (this.toBool(this.field.priceInfo.enabled)) {
            $("#custom-form-field-price").prop("checked", false).trigger("click");
            $("#custom-form-field-price-type").val(this.field.priceInfo.unit).trigger("change");
            $("#custom-form-field-price-input").val(parseFloat(this.field.priceInfo.price));
        }
        // if required check the req. checkbox
        $("#custom-form-field-req").prop("checked", this.toBool(this.field.required));
        if(this.field.visibility == undefined) {
            $("#custom-form-visibility-select").val("both");
        } else {
            $("#custom-form-visibility-select").val(this.field.visibility);
        }
        // change the form so the user knows they are updating and turn on the cancel btn
        $("#custom-form-field-legend").html(this.field.name);
        $("#custom-form-type-next-btn").html("Update Field");
        $("#custom-form-type-next-cancel").css("display", "inline-block");

        //update the view with the field types and populate the form values in attrs if any
        this.renderFieldForm(this.field.type, this.field);
    }

    /**
     * this method resets the add field form values 
     */
    resetForm() {
        // reset the form 
        $("#custom-form-type-next-cancel").css("display", "none");
        $("#custom-form-field-legend").html("New Field");
        $("#custom-form-type-next-btn").html("Add Field");
        $("#custom-form-field-id").val("");
        $("#custom-form-field-name").val("");
        $("#custom-form-field-info").val("");
        $("#custom-form-type-select").val("text").trigger("change");
        $("#custom-form-visibility-select").val("both").trigger("change");
        $("#custom-form-price-wrap").css("display", "none");
        $("#custom-form-price-wrap").attr("data", "closed");
        $("#custom-form-field-req").prop("checked", false);
        $("#custom-form-field-price").prop("checked", false);
        $("#custom-form-field-price-type").val("setprice").trigger("change");
        $("#custom-form-field-price-input").val(0.00);
        $("#custom-form-field-price-inventory-input").val(0);

        this.field = null;
    }

    /**
     * @param {*} path pass in dot notaion path and reduce to value
     * @param {*} obj the object to parse the field from 
     * @param {*} def the default value to return if object is null
     * @returns any
     */
    editVal(path, obj = {}, def = "") {
        try {
            if (!obj || Object.keys(obj).length === 0) { return def; }
            let val = path.split('.').reduce((o, i) => o[i], obj);
            return (val && val !== undefined) ? val : def;
        } catch (err) {
            console.log(`${path} not found in object ${JSON.stringify(obj)}`);
            return def; // return default value
        }

    };

    /**
     * Show a <label> tag
     * @param {*} label 
     * @param {*} id 
     * @param {*} className 
     * @returns 
     */
    renderLabel(label, id = "", className = "form-custom-label", info = "") {
        return `<label for=${id}  class="${className}" >${label} ${info}</label>`;
    }

    /**
     * render price info if any
     */
    price() {
        const enabled = $("#custom-form-field-price").is(':checked');
        let price = 0.0;
        let type = currencyFormation.code;
        let unit = 'n';
        let inventory = '';

        if (enabled) {
            price = parseFloat($("#custom-form-field-price-input").val()).toFixed(2);
            unit = $("#custom-form-field-price-type").val();
            inventory = parseInt($("#custom-form-field-price-inventory-input").val());
        }

        return { enabled, price, unit, type, inventory };

    }

    /**
     * show the custom form 
     */
    renderFormFields() {
        let fields = [];

        for (let key in this.form.fields) {
            fields.push(this.getFieldTemplate(this.form.fields[key]));
        }

        $("#custom-form-field-list").html(fields.join("<br/>"));
    }

    editorLabel(field, icon = "") {
        let addonVisibilityText = '';
        let addonVisibilityStyle = '';
        var addonVisibility = field.visibility;
        addonVisibility = addonVisibility.charAt(0).toUpperCase() + addonVisibility.slice(1);

        if (field.type !== 'checkbox' && field.type !== 'radio') {
            addonVisibilityStyle = `display: inline-block; width: 100%;`;
            addonVisibilityText = `<span class="p-1 text-sm block w-full text-right" style="display: inline-block;width: 100%;text-align: right;margin-top: 5px;">Visibility: <strong style="color: #10b11a;">${addonVisibility}</strong></span>`;
        }

        return `
        <label class="form-custom-label !mb-0" for="${field.id}" style="${addonVisibilityStyle}"> 
            ${icon}
            ${this.renderReqMark(field.required)} ${this.toTitleCase(field.name)} ${this.renderPrice(field)}
        </label>
        ${addonVisibilityText}

        ${this.renderDescription(field)}`;

    }

    editorBtns(field) {
        return ` ${this.renderIcon("fas fa-trash-alt delete-field float-end ml-2 mt-2 btn btn-danger-small", field.id)} 
                 ${this.renderIcon("far fa-edit edit-field float-end ml-2 mt-2 btn btn-primary-small", field.id)} `;
    }

    textField(field, label = "") {
        return `${label}<input class="form-control form-custom-input" type="${field.type}" id="${field.id}" value="${field.default}" placeholder="${this.toTitleCase(field.name)}" /> `;
    }

    textAreaField(field, label = "") {
        return `${label}<textarea class="form-control form-custom-input" id="${field.id}">${field.default}</textarea>`;
    }

    checkboxField(field, label = "") {
        return `<div class="form-control form-custom-input !h-auto"> <input class="form-check-input w-4 h-4 mr-1 align-middle" type="${field.type}" id="${field.id}" value="${field.default}" /> ${label} </div>`;
    }

    numberField(field, label = "") {
        let min = "";
        let max = "";
        if (field.attrs.min > -1) { min = `min="${field.attrs.min}"` }
        if (field.attrs.max > -1) { max = `max="${field.attrs.max}"` }
        return `${label} <input class="form-control form-custom-input" type="${field.type}"  id="${field.id}" value="${field.default}"  ${min}  ${max} placeholder="${this.toTitleCase(field.name)}" /> `;
    }

    radioField(field, label) {
        return `<div class="form-control form-custom-input !h-auto">
            ${label} 
            ${field.attrs.options.map((o) => `
            <div class="form-check">
                <input class="form-check-input" value="${o.value}" type="radio" name="${this.toID(field.name)}" id="${o.id}" />
                <label class="form-check-label form-custom-label" for="${o.id}"> ${o.name}</label>
            </div>` ).join("\n")}
        </div>`;
    }

    selectField(field, label) {
        return `
        <div class=""> 
            ${label} 
            <select class="form-select form-custom-input" id="${field.id}">
                ${field.attrs.options.map((r) => `<option value="${r.value}">${r.name}</option>`).join("\n")}
            </select>
        </div>`;
    }

    /**
     * render the form field in the custom form editor
     * @param {*} field 
     * @returns 
     */
    getFieldTemplate(field) {

        let input = '';
        let addonVisibilityText = '';

        if (!field.hasOwnProperty('visibility')) {
            field.visibility = "both";
        }

        var addonVisibility = field.visibility;
        addonVisibility = addonVisibility.charAt(0).toUpperCase() + addonVisibility.slice(1);
        let label = this.editorLabel(field, this.renderIcon("fas fa-grip-vertical drag-pointer"));

        if (field.type === 'text') {
            input += this.textField(field, label);
        }

        if (field.type === 'textarea') {
            input += this.textAreaField(field, label);
        }

        if (field.type === 'checkbox') {
            input += this.checkboxField(field, label);
        }

        if (field.type === 'number') {
            input += this.numberField(field, label);
        }

        if (field.type === 'radio') {
            input += this.radioField(field, label);
        }

        if (field.type === 'select') {
            input += this.selectField(field, label);
        }

        // add edit and remove icons btns 
        input += this.editorBtns(field);

        if (field.type === 'checkbox' || field.type === 'radio') {
            addonVisibilityText += `<span class="text-sm block mx-2 mt-4 w-full text-right">Visibility: <strong style="color: #10b11a;">${addonVisibility}</strong></span>`;
        }

        return `${addonVisibilityText}<div class="custom-field p-1" data="${field.id}"> ${input} </div>`;
    }

    /**
     * add a new field to form custom-form-type-select custom-form-field-name
     * see the event addField
     * @returns string html 
     */
    addFieldView() {
        return `

        <fieldset class="border p-4 rounded">
            <legend class="float-none w-auto" id="custom-form-field-legend"> New Field</legend>
            <div id="custom-form-field-error"></div>
            <div class="mb-3">
                ${this.renderLabel("Unique Field Name ", "custom-form-field-name")}
                <input class="form-control form-custom-input" type="text" value="" id="custom-form-field-name" name="custom-form-field-name" /> 
                <input type="hidden" value=""  id="custom-form-field-id" />
            </div>
        
            <div class="mb-3">
                ${this.renderLabel("Field Type ", "custom-form-type-select")}
                <select class="form-select form-custom-input" id="custom-form-type-select" name="custom-form-type-select" > 
                    <option value="">Select A Form Field Type</option>
                    ${this.formtypes.map(f => `<option value="${f.type}">${f.name}</option>`).join("\n")}
                </select>
            </div>

            <!-- onFieldSelectChange will add extra functions based on the input type in the div below -->
            <div id="custom-field-load-template">  </div>

            <div class="mb-3">   
                ${this.renderLabel("Additional Info", "custom-form-field-info")}
                <input class="form-control form-custom-input" type="text" id="custom-form-field-info"  placeholder="Help Text Or Description" value="" />
            </div>

                ${this.renderPriceForm()}

            <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="custom-form-field-req">
                ${this.renderLabel("Field Required", "custom-form-field-req")}
            </div>

            <div class="mb-3 mt-3">
                ${this.renderLabel("Visibility ", "custom-form-visibility-select")}
                <select class="form-select form-custom-input" id="custom-form-visibility-select" name="custom-form-visibility-select" > 
                    ${this.visibilityOptions.map(f => `<option value="${f.value}">${f.label}</option>`).join("\n")}
                </select>
            </div>

            <div class="btn-group btn-sm mt-3">
                <button class="btn btn-primary btn-primary-small" id="custom-form-type-next-btn">Add Field</button>
                <button class="btn btn-default custom-form-field-preview btn-primary-small">Preview</button>
                <button class="btn btn-default btn-danger-small" id="custom-form-type-next-cancel" style="display: none;"> Cancel </button>
            </div>
            </fieldset>`;
    }


    renderPriceForm() {

        return `
        
        <div class="form-check">

       <input class="form-check-input" style="${this.enablePrice ? '' : 'display:none;'}" type="checkbox" value="1" id="custom-form-field-price">
       ${(this.enablePrice) ? this.renderLabel("Additional Fee", "custom-form-field-price") : ''}
       
        <div class="card card-body" id="custom-form-price-wrap" style="display: none">

            ${this.renderLabel("Unit Type", "custom-form-field-price-type")}
            <select class="form-select mb-3 form-custom-input" id="custom-form-field-price-type" name="custom-form-field-price-type">
                <option value="setprice">One Time Price</option>
                <option value="priceperpax">Price Per Person</option>
                <option value="priceperunit">Price Per Unit</option>
            </select>

            ${this.renderLabel("Price", "custom-form-field-price-input")}
            <input type="text" class="form-control mb-3 form-custom-input" id="custom-form-field-price-input" value="" placeholder="0.00" />
            <div class="mb-3" style="display:none">
                ${this.renderLabel("Inventory", "custom-form-field-price-inventory-input", "", "<small>Leave Blank For No Limit </small>")}
                <input type="number" class="form-control form-custom-input" id="custom-form-field-price-inventory-input" value="" placeholder="" />
            </div>
        </div>

        </div>
        `;

    }


    /**
     * the main form template 
     * this allows the user to create new form fields 
     * @returns string html
     */
    formTemplate() {
        return `

            <div id="new-custom-form" >
                <div id="custom-form-save-reminder"> </div>

                ${this.renderLabel("Form Name", "custom-form-name")}
                <input class="form-control form-custom-input" type="text" id="custom-form-name" value="" name="custom-form-name" placeholder="Form Name" />
                
                ${this.renderLabel("Form Description", "custom-form-description")}
                <input class="form-control form-custom-input"  type="text" value="" name="custom-form-description" id="custom-form-description" placeholder="Description" />

                ${this.renderLabel("Form Published Status <small class='text-muted'>Set to active to show up in checkout</small>", "custom-form-status")}
                <select class="form-select form-custom-input" id="custom-form-status">
                    <option value="inactive">Inactive</option>
                    <option value="active">Active</option>
                </select>
                
                <div id="add-new-custom-field-form-view" class="flex flex-wrap -mx-3 py-4"> 
                
                <div class="w-full md:w-1/3 max-w-full px-3" id='custom-form-builder'>
                    ${this.addFieldView()}
                </div>

                
                <div class="w-full md:w-2/3 max-w-full px-3"> 

                    <fieldset class="border p-4 rounded">
                        <legend class="float-none w-auto" id="custom-form-name-ui"> New Form </legend>
                        <div id='custom-form-field-list'></div>
                    </fieldset>
                
                </div>

                <div class="w-full max-w-full px-3"> 
                    <div class="btn-group btn-group-sm float-end flex items-center mt-2">
                        <button class="btn btn-success ml-2 btn-primary-small" id="custom-form-save-btn">Save Form</button>
                        <button class="btn btn-success ml-2 btn-primary-small" style="display: none;" id="custom-form-json-btn">View Object</button>
                        <button class="btn btn-danger ml-2 btn-danger-small" style="display: none;" id="custom-form-delete-btn">Delete Form</button>
                    </div> 

                </div>
        
                </div>
            </div>
        `;
    }

    /**
     * toggle the display the price option form elements 
     * @returns void
     */
    renderPriceOptions() {
        const wrap = $("#custom-form-price-wrap");
        if (wrap.attr("data") === 'open') {
            wrap.attr("data", "closed");
            wrap.css("display", "none");
            return ""
        } else {
            wrap.attr("data", "open");
            wrap.css("display", "block");
        }
    }

    /**
     * Add a little * required mark next to the field label 
     * @param {*} req 
     * @returns 
     */
    renderReqMark(req) {
        return this.toBool(req) ? "*" : "";
    }

    /**
     * remind the user when they update fields they still have to save the form
     * @param {*} show 
     */
    renderSaveReminder(show = true) {

        let alert = `
        <div class="alert alert-warning" role="alert">
            You have unsaved changes, remember to save your form.
        </div>
        `;

        if (!show) { alert = ""; }

        $("#custom-form-save-reminder").html(alert);
    }

    /**
     * 
     * @param {*} className icon name 
     * @useage this.renderIcon("fas fa-grip-vertical");
     * @returns string html tag
     */
    renderIcon(className, data = "") {
        return `<i class="${className}" data="${data}"></i>`
    }

    /**
     * if field description is set add a info icon and collapse slider to the field 
     * @param {*} field 
     * @returns 
     */
    renderDescription(field) {
        let id = this.toID(`collapse${field.name}`);
        let popover = `
        
        <i class="fas fa-info-circle cursor-pointer" type="button" data-bs-toggle="collapse" data-bs-target="#${id}" aria-expanded="false" aria-controls="${id}"></i>
        
        <div class="collapse mt-1" id="${id}">
            <div class="card card-body w-full resize-none outline-none shadow-none border border-[#E2E8F0] rounded-md px-4 py-1.5 max-h-80 text-sm text-[#020817] overflow-y-auto">
                ${field.description}
            </div>
        </div>
        `;
        return field.description !== "" ? popover : "";
    }

    /**
     * render the price info on the screen if any 
     */
    renderPrice(field) {

        let html = "";

        if (this.toBool(field.priceInfo.enabled)) {

            if (field.priceInfo.price.toString() === "") {
                field.priceInfo.price = 0;
            }

            field.priceInfo.price = field.priceInfo.price.toString().replace(/[^0-9.]/gi);

            if (isNaN(field.priceInfo.price)) {
                field.priceInfo.price = 0;
            }

            html += `
            <span style="display: block;">
                <small>Additional Fee:</small> <strong title="${field.priceInfo.unit}">${this.toCurrency(field.priceInfo.price)}</strong>     
            </span>`;
        }

        return html;
    }

    /** Display any Errors */
    onError(err) {
        if (typeof (err) === 'object') {
            if (err.hasOwnProperty('errorInfo')) {
                err = err.errorInfo.join(" ");
            }
        }

        $("#custom-form-save-btn").prop('disabled', false);

        swal({
            title: 'Error',
            text: `${err}`,
            icon: 'error'
        });
    }

    // add the error to the screen
    updateFormError(errs, id = "#custom-form-field-error") {
        if (errs.length === 0) {
            $(id).html('');
        } else {
            $(id).html(`<div class="alert alert-danger">${errs.map(e => e).join("<br/>")}</div>`);
        }
    }

    // update a string like ( This is My Form ) to this this-is-my-form
    slugify(str) {
        return str.trim().toLowerCase().replace(/[^\w\s-]/g, '').replace(/[\s_-]+/g, '-').replace(/^-+|-+$/g, '');
    }

    // update a string like ( my new from ) to My New From
    toTitleCase(str) {
        return str.replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
    }

    //update a string like ( !This IS my CrazY-- Text! ) to thisismycrazytext
    toID(str) {
        return str.replace(/[^a-zA-Z0-9]/gi).toLowerCase();
    }

    // return Currency format input (10.0) -> output ($10.00)
    toCurrency(num) {
        return `${currencyFormation.symbol}${new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num)}`;
    }

    //string val to boolean 
    toBool(val) {
        return (val === true || val === "true" || val === 1 || val.toString().toLowerCase() === 'y') ? true : false
    }

    /** 
      * on Drag and Drop Reorder the form object array 
    */
    reorderFields(_event, _ui) {
        $(".custom-field").each((newIndex, ele) => {
            const id = $(ele).attr("data");
            const oldIndex = this.form.fields.findIndex(f => f.id == id);
            this.form.fields[oldIndex].order = newIndex;
            this.form.fields = this.reorderFieldArray({ oldIndex, newIndex }, this.form.fields);
        });
        this.renderFormFields();
    }

    /**
     * Splice the this.form.fields array on drag and drop 
     * @param {*} event 
     * @param {*} originalArray 
     * @returns 
     */
    reorderFieldArray(event, originalArray) {
        const movedItem = originalArray.find((_item, index) => index === event.oldIndex);
        const remainingItems = originalArray.filter((item, index) => index !== event.oldIndex);
        const reorderedItems = [...remainingItems.slice(0, event.newIndex), movedItem, ...remainingItems.slice(event.newIndex)];
        return reorderedItems;
    }

    /**
     * Display a modal 
     * @param {*} label 
     * @param {*} content 
     */
    modalShow(label, content) {
        $("#customFormFieldModalLabel").html(label);
        $("#customFormFieldModalContent").html(content);
        $("#customFormFieldModal").modal("show");
    }

    /**
     * Add a default modal to the screen
     * @returns html string
     */
    modalTemplate() {

        return `
        <div class="modal fade" id="customFormFieldModal" tabindex="-1" aria-labelledby="customFormFieldModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="customFormFieldModalLabel"></h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="customFormFieldModalContent">

            </div>
            </div>
        </div>
        </div>`;
    }

    /**
     * Allows Addons Visibility [Frontend | Backend | Both]
     * @returns array
     */
    visibilityOptions() {
        return [
            { value: "both", label: "Both" },
            { value: "frontend", label: "Frontend" },
            { value: "backend", label: "Backend" }
        ];
    }
}