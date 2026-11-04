class RoleTypesAddEdit {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.userType = props.admin;
        this.saveRoleTypeUrl = props.saveRoleTypeUrl;
        this.fetchRoleTypesUrl = props.fetchRoleTypesUrl;
        this.deleteRoleTypeUrl = props.deleteRoleTypeUrl;
        //this.initializeRoleTypeOptions();
        this.bindEvents();
        this.addGlobalListeners();
    }
    bindEvents() {

        $(document).off('click', '.addRoleTypebtn').on('click', '.addRoleTypebtn', (evt) => {
            this.appendRoleTypeOptions(evt);
        });
        $(document).off('click', '.removeRoleTypebtn').on('click', '.removeRoleTypebtn', (evt) => {
            this.removeRoleTypeOptions(evt);
        });
        $(document).off('click', '.deleteRoleTypebtn').on('click', '.deleteRoleTypebtn', (evt) => {
            this.deleteRoleTypeOptions(evt);
        });
        $(document).off('change', '#role').on('change', '#role', (evt) => {
            this.fetchRoleTypeOptions(evt);
        });
        $(document).on('input change', '.names-input, .percentages-input', (e) => this.validateInput($(e.target)));
    }

    fetchRoleTypeOptions(evt) {
        showLoading();
        var $select = $(evt.currentTarget);
        var roleId = $select.val();
        $.ajax({
            url: this.fetchRoleTypesUrl,
            method: 'POST',
            data: { role_id: roleId },
            dataType: 'json',
            success: this.renderRoleTypeOptions.bind(this)
        }).always(hideLoading);
    }
    renderRoleTypeOptions(data) {
        var roleTypes = data.role_types;
        const $section = $('#roleTypeSection');

        var html = '';
        $section.empty();
        $('#addRoleTypebtn').removeClass('d-none');
        if (roleTypes && roleTypes.length > 0) {
            for (let i = 0; i < roleTypes.length; i++) {
                html += ` <div class="row mb-4 role-type-opt">
                            <div class="col-md-3">
                                <label class="form-label" for="names">Name <req>*</req></label>
                                <input type="text" name="names[]" class="form-control me-2 names-input" value="${roleTypes[i].display_name}" placeholder="Enter Role Type Name">
                             <div class="invalid-feedback">Please enter Role type name.</div> 

                                </div>
                            <div class="col-md-4">
                                <label class="form-label" for="percentages">Tip Distribution Percentage (%) <req>*</req></label>
                                <div class="d-flex align-items-start">
                                    <input type="number" style="width: 76%;"  name="percentages[]" class="form-control percentages-input" value="${roleTypes[i].tip_distribution_percentage}" min="0" max="100" placeholder="Enter Tip Distribution Percentage">
                                    <button type="button" style="margin-left: 7px;" data-id="${roleTypes[i].id}" class="btn btn-danger deleteRoleTypebtn">
                                        <i class="fas fa-trash me-1"></i>Delete</button></div>
                                 <div class="invalid-feedback">Please enter a positive number.</div>
                            </div>
                        </div>`;

            }
        }

        $section.html(html);
    }
    appendRoleTypeOptions(evt) {
        const roleTypeOptHtml = `<div class="row mb-4 role-type-opt">
                            <div class="col-md-3">
                                <label class="form-label" for="names">Name <req>*</req></label>
                                <input type="text" name="names[]" class="form-control me-2 names-input" placeholder="Enter Role Type Name">
                             <div class="invalid-feedback">Please enter Role type name.</div> 
                            </div>
                            <div class="col-md-4">
                                 <label class="form-label" for="percentages">Tip Distribution Percentage (%) <req>*</req></label>
                                <div class="d-flex align-items-start">
                                   <input type="number" style="width: 76%;" name="percentages[]" class="form-control percentages-input" value="" min="0" max="100" placeholder="Enter Tip Distribution Percentage">
                                    <button type="button" style="margin-left: 7px;" class="btn btn-danger removeRoleTypebtn">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please enter description.</div> 
                            </div>
                        </div>`;
        if ($('#roleTypeSection').find('.role-type-opt').length > 0) {
            $('#roleTypeSection .role-type-opt:last').after(roleTypeOptHtml);
        } else {
            $('#roleTypeSection').html(roleTypeOptHtml);
        }

    }
    removeRoleTypeOptions(evt) {
        const $btn = $(evt.currentTarget);
        const $row = $btn.closest('.role-type-opt');
        if ($('.role-type-opt').length > 1) {
            $row.remove();
        } else {
            swal('Error', 'At least one role type option is required.', 'warning');
        }
    }
    deleteRoleTypeOptions(evt) {
        const $btn = $(evt.currentTarget);
        const roleTypeId = $btn.data('id');
        var deleteRoleTypeUrl = this.deleteRoleTypeUrl.replace(':id', roleTypeId);
        var confirmTitle = "Delete?";
        var confirmText = "Are you sure to delete the role type?";
        var delSuccessMsg = "The role type has been deleted.";

        swal({
            title: confirmTitle,
            text: confirmText,
            icon: "warning",
            buttons: {
                no: {
                    text: 'No',
                    value: false,
                },
                yes: {
                    text: 'Yes',
                    className: 'btn-danger',
                }
            }
        }).then(function (result) {
            if (result) {
                showLoading();

                $.ajax({
                    type: "post",
                    url: deleteRoleTypeUrl,
                    data: {
                        _method: 'DELETE',
                    },
                    success: function (data) {
                        hideLoading();
                        const $btn = $(evt.currentTarget);
                        const $row = $btn.closest('.role-type-opt');
                        if ($('.role-type-opt').length > 1) {
                            $row.remove();
                        }
                        swal(delSuccessMsg, '', 'success');
                    },
                    error: function (data) {
                        hideLoading();
                        showSubmissionError(data);
                    }
                });
            }
        });
    }
    async handleCreatedRoleTypes() {
        const { isValid, data } = this.validateAndCollectData();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        showLoading();
        try {
            $.ajax({
                url: this.saveRoleTypeUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    ...data
                })

            })
                .done(function (response) {
                    swal('Success',
                        'Role type Saved successfully!',
                        'success')
                        .then(function () {
                            location = location;
                        });
                })
                .always(hideLoading)
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                });

        } catch (error) {
            console.error('Error saving Role Type:', error);
            swal('Error', 'Failed to save Role Type. Please try again.', 'error');
        }
    }

    validateDropdown($element) {
        if ($element.is('select')) {
            const isValid = $element.val() && $element.val().length > 0;
            const $select2 = $element.next('.select2-container').find('.select2-selection--multiple');

            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $select2.removeClass('is-invalid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $select2.addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        }
        return false;
    }

    validateInput($element) {
        if ($element.hasClass('percentages-input')) {
            const value = parseFloat($element.val());
            const isValid = !isNaN(value) && value >= 0 && value <= 100;
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.closest('.col-md-4').find('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.closest('.col-md-4').find('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('names-input')) {
            const value = $element.val();
            const isValid = value && value.trim() != '';
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        }
        return false;
    }

    validateAndCollectData() {
        const $form = $('#roleTypesForm');

        let isValid = true;

        $('#role').each((_, el) => {
            if (!this.validateDropdown($(el))) {
                isValid = false;
            }
        });

        $('.names-input').each((_, el) => {
            if (!this.validateInput($(el))) {
                isValid = false;
            }
        });
        $('.percentages-input').each((_, el) => {
            if (!this.validateInput($(el))) {
                isValid = false;
            }
        });


        if (!isValid) {
            return { isValid: false, data: null };
        }

        const roleTypes = [];

        $('.role-type-opt').each((_, element) => {
            const $roleTypeOpt = $(element);
            const percentage = parseFloat($roleTypeOpt.find('.percentages-input').val());
            const name = $roleTypeOpt.find('.names-input').val().trim();
            roleTypes.push({ percentage, name, roleTypeId });
        });


        const data = {
            roleId: $('#role').val(),
            roleTypes: roleTypes,
        };

        return { isValid: true, data };

    }

    async handleCreateGratuitySetting() {
        const { isValid, data } = this.validateAndCollectData();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        showLoading();
        try {
            $.ajax({
                url: this.saveRoleTypeUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    ...data
                })

            })
                .done(function (response) {
                    swal('Success',
                        'Gratuity Setting Saved successfully!',
                        'success')
                        .then(function () {
                            location = location;
                        });
                })
                .always(hideLoading)
                .fail(function (jqXHR) {
                    hideLoading();
                    showSubmissionError(jqXHR);
                });

        } catch (error) {
            console.error('Error saving Gratuity Setting:', error);
            swal('Error', 'Failed to save Gratuity Setting. Please try again.', 'error');
        }
    }
    addGlobalListeners() {
        const self = this;
        $('#save-role-type').on('click', () => this.handleCreatedRoleTypes());
    }
}


