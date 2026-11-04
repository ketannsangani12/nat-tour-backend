class CustomFormReader {
    /**
     * @param {*} props 
     * nested prop values: 
     * path url endpoint to get the form data from the api
     * dom id to inject the rendered form 
     * onSubmit function to call when form is submitted 
     */
    constructor(props) {
        this.path = props.path;
        this.dom = props.dom;
        this.onMount = props.onMount || null;
        this.onSubmit = props.onSubmit;
        this.onCancel = props.onCancel;
        this.formClass = props.formClass || "";
        this.submitAction = props.submitAction || "";
        this.fields_indexed = {};
        this.form = props.form || null;
        this.existing_form = null;
        this.infoIcon = props.icon || '&#x24D8;';
        this.getForm();
    }

    async getForm() {
        // if the user passed in a form 
        if (this.form !== null) {
            //parse the fields 
            if (!this.form.hasOwnProperty('fields')) {
                this.form.fields = JSON.parse(this.form.form_fields);
            }
            //index the fields by id
            this.form.fields.forEach(f => { this.fields_indexed[`${f.id}`] = f; });
        }

        //check to see if we have form in the database
        const response = await fetch(this.path);
        const data = await response.json();

        //call the monuted callback
        if (this.onMount !== null) {
            this.onMount(data);
        }

        //if we have a form reponse object 
        if (data && data.success === true && data.form && data.form.status === 'active') {
            if (this.form === null) {
                this.form = {
                    id: data.form.id,
                    form_name: data.form.form_name,
                    description: data.form.description,
                    status: data.form.status,
                    form_package_id: data.form.form_package_id,
                    form_fields: data.form.form_fields,
                    fields: []
                };
            }

            this.form.description = data.form.description;

            this.existing_form = data.form;
            this.existing_form.form_fields = JSON.parse(this.existing_form.form_fields);
            this.existing_form.form_fields.forEach(f => {
                if (this.fields_indexed[`${f.id}`] === undefined) {
                    f.value = (f.default && f.default !== undefined && f.default !== "") ? f.default : "";
                    if (f.type === 'checkbox' || f.type === 'radio') { f.value = ""; }
                    this.form.fields.push(f);
                    this.form.form_package_id = data.form.form_package_id;
                    this.form.id = data.form.id;
                    this.fields_indexed[`${f.id}`] = f;
                }
            });

            //sort the form 
            // this.form.fields.sort((a, b) => { return (parseInt(a.order) < parseInt(b.order)) ? -1 : 1 });
        }

        if (this.form !== null) {
            //render the fields 
            await this.renderFrom();

            // construct the form meta data 
            let formData = {
                form_id: parseInt(this.form.id),
                package_id: parseInt(this.form.form_package_id),
                submission: []
            };

            // attach the events to the form object
            this.addEvents(`custom-form-${this.form.id}`, formData, this.onSubmit);
        }
    }

    /**
     * Append the form to the this.dom element 
     * @returns string html
     */
    async renderFrom() {
        const form = `<form id='custom-form-${this.form.id}' class="${this.formClass}" >

            <input type='hidden' data="meta-field" name="form_id" id='custom-form-id-${this.form.id}' value='${this.form.id}' />
            <input type='hidden' data="meta-field" name="package_id" id='custom-form-package-id-${this.form.form_package_id}' value='${this.form.form_package_id}' />
            <div class="flex flex-wrap -mx-3">${this.form.fields.map(f => this.renderFormFields(f)).join("\n")}</div>

            <div class="">
                <div class="btn-group btn-sm flex flex-wrap -mx-3">
                    <div class="w-1/2 px-3">
                        <input data="meta-field" id="custom-form-submit" type="submit" class="btn btn-sm btn-primary btn-primary-small w-full" value="${this.submitAction}" />
                    </div>
                    <div class="w-1/2 px-3">
                        <input data="meta-field" id="custom-form-cancel" type="button" class="btn btn-sm btn-default bg-[#E5E7EB] border border-[#E5E7EB] text-[#4B5563] text-[12px] font-semibold p-2 w-full rounded-[6px] cursor-pointer hover:bg-[#A32624] hover:text-white" value="Cancel"  /> 
                    </div>
                </div>
            </div>
        </form>`;

        document.getElementById(`${this.dom}`).innerHTML = form;
        return true;
    }

    renderFormFields(field) {
        const visibility = field.hasOwnProperty('visibility') ? field.visibility : 'both';

        // Display fields visible only for backend|both
        if (visibility === 'frontend') return "";

        let input = `<div class="w-full mb-5 px-3"><div class="custom-field" data="${field.id}"> `;
        let label = `<label class="flex mb-1 form-custom-label" for="${field.id}" > <span>${this.toTitleCase(field.name)}</span> ${this.renderReqMark(field.required)} ${this.renderPrice(field)} ${this.renderDescription(field)}</label> ${this.renderDescriptionTexts(field)}`;
        let labelcheckbox = `<label class="ml-3 mb-1 form-custom-label" for="${field.id}" ><div class="flex items-center"><span>${this.toTitleCase(field.name)}</span> ${this.renderReqMark(field.required)}</div> <div class="flex items-center">${this.renderPrice(field)} ${this.renderDescription(field)}</div></label>`;

        if (field.type === 'text') {
            input += `${label}<input class="form-control form-custom-input" data-id="${field.id}" type="${field.type}" name="${field.name}" id="${field.id}" value="${field.value}" placeholder="${this.toTitleCase(field.name)}" ${this.renderReq(field)} /> `;
        }

        if (field.type === 'textarea') {
            input += `${label}<textarea class="form-control form-custom-input" data-id="${field.id}" name="${field.name}" id="${field.id}" ${this.renderReq(field)} style="max-height: 38px; resize: none;">${field.value}</textarea>`;
        }

        if (field.type === 'checkbox') {
            input += `<div class="flex">
            <input class="form-check-input mr-2.5 w-5 h-5 border border-[#767676] text-[#767676] align-top mt-1" data-id="${field.id}" ${(field.value !== "" ? 'checked' : '')} type="${field.type}" name="${field.name}" id="${field.id}" value="${field.value}" ${this.renderReq(field)} style=""/> 
            ${labelcheckbox}</div> ${this.renderDescriptionTexts(field)}
            `;
        }

        if (field.type === 'number') {
            let min = ""; let max = "";
            if (field.attrs.min > -1) { min = `min="${field.attrs.min}"` }
            if (field.attrs.max > -1) { max = `max="${field.attrs.max}"` }
            input += `${label}<input class="form-control form-custom-input"  data-id="${field.id}" type="${field.type}" name="${field.name}" id="${field.id}" value="${field.value}"  ${min}  ${max} placeholder="${this.toTitleCase(field.name)}" ${this.renderReq(field)} /> `;
        }

        if (field.type === 'radio') {
            input += `${label} 
                <div class="form-control d-flex">
                    ${field.attrs.options.map((o) => `
                    <div class="form-check ps-0 pe-3 mb-0">
                        <input class="form-check-input" data-id="${field.id}" ${o.value === field.value ? 'checked' : ''} value="${o.value}" type="radio" name="${field.name}" id="${o.id}" data="${field.id}" style="margin: 3px 5px 3px 0; transform: none;"/>
                        <label class="form-check-label" for="${o.id}"> ${o.name}</label>
                    </div>` ).join("\n")}
                </div>`
        } //if radio btn

        if (field.type === 'select') {
            input += `
                    <div class=""> 
                        ${label} 
                        <select class="form-select form-custom-input bg-[url(\"data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e\")] bg-no-repeat bg-[position:right_0.75rem_center] bg-[length:16px_12px]" id="${field.id}" data-id="${field.id}" name="${field.name}" ${this.renderReq(field)}>
                            ${field.attrs.options.map((o) => `<option data-id="${field.id}" ${o.value === field.value ? 'checked' : ''} value="${o.value}">${o.name}</option>`).join("\n")}
                        </select>
                    </div>
                `;
        }//if select drop down

        input += `</div></div>`

        return input;
    }

    /**
     * Attach Form Submit Callback
     * @param {*} formId 
     * @param {*} formData 
     * @param {*} callBack 
     */
    addEvents(formId, formData, callBack) {
        //add event to cancel btn
        const cancel = document.getElementById("custom-form-cancel");
        cancel.addEventListener('click', (evt) => this.onCancel(evt));

        //add event to submit button
        const form = document.getElementById(formId);
        form.addEventListener('submit', (evt) => {
            // stop the from from refreshing the page
            evt.preventDefault();
            //reset the formdate object
            formData.fields = this.fields_indexed;
            formData.submission = [];
            formData.html_form_id = formId;
            // get form elements
            const elements = [...document.getElementById(formId).elements];
            // collect the form elemets
            elements.forEach((input) => {
                //skip if this is a form field helper 
                if (input.getAttribute('data') === 'meta-field') { return false; }
                let selected = false;
                let id = input.getAttribute('data-id');
                // look up the data for the field 
                let meta = formData.fields[`${id}`];
                // check if the field has been modified or selected
                if (input.type === 'radio' || input.type === 'checkbox') {
                    if (input.checked === true) {
                        meta.value = (input.type === 'checkbox') ? 'true' : input.value;
                        selected = true;
                    }
                } else {
                    if (input.value.toString().trim() !== '') {
                        meta.value = input.value;
                        selected = true;
                    }
                }
                // add the form fields to a
                if (selected) {
                    formData.submission.push(meta);
                }
            });
            // call the on submit call back
            callBack(evt, formData);
        });
    }

    /**
     * Add a little * required mark next to the field label 
     * @param {*} req 
     * @returns 
     */
    renderReqMark(req) {
        return this.toBool(req) ? `<span style="color: red">*</span>` : ``;
    }

    /**
     * add required validation 
     * @param {*} field 
     * @returns 
     */
    renderReq(field) {
        if (this.toBool(field.required)) {
            return `required='true'`;
        }
        return '';
    }

    /**
     * if field description is set add a info icon and collapse slider to the field 
     * @param {*} field 
     * @returns 
     */
    renderDescription(field) {
        let id = this.toID(`collapse${field.name}`);
        let popover = `<span class="ms-2" data-bs-toggle="collapse" data-bs-target="#${id}" aria-expanded="false" aria-controls="${id}" style="cursor:pointer;">${this.infoIcon}</span>`;
        return field.description !== "" ? popover : "";
    }

    /**
     * if field description is set add a info icon and collapse slider to the field 
     * @param {*} field 
     * @returns 
     */
    renderDescriptionTexts(field) {
        let id = this.toID(`collapse${field.name}`);
        let popover = `<div class="collapse" id="${id}">
            <div class="card card-body mb-2 p-2 border border-[#D5D5D5] overflow-y-auto overflow-x-hidden rounded-[6px] text-xs" style="max-height:300px;">
                ${field.description}
            </div>
        </div>`;
        return field.description !== "" ? popover : "";
    }

    /**
     * render the price info on the screen if any 
     */
    renderPrice(field) {
        let html = "";
        if (this.toBool(field.priceInfo.enabled)) {
            html += `
            <span class="">
                <small>Additional Fee:</small> <strong title="${field.priceInfo.unit}">${this.toCurrency(field.priceInfo.price)}</strong>
            </span>`;
        }
        return html;
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
}
