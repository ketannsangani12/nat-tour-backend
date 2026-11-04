class GratuitySettings {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.userType = props.admin;
        this.fetchSettingsUrl = props.fetchSettingsUrl;
        this.saveGratuitySettingUrl = props.saveGratuitySettingUrl;
        this.initializeGratuityOptions();
        this.initializeDistributionOptions();
        this.initializeGratuityAmounts();
        this.bindEvents();
        this.initializeFlexibleGratuitySettings();
        this.addGlobalListeners();
    }

    initializeGratuityOptions() {
        const self = this;
        $('.gratuity-option').on('click', function () {
            $('.gratuity-option').removeClass('active');
            $(this).addClass('active');

            const value = $(this).data('value');
            self.updateGratuityValueSection(value);
        });
    }

    updateGratuityValueSection(type) {
        const $section = $('#gratuityValueSection');
        if (type === 'fixed_percentage') {
            this.renderFixedPercentageGratuityOptions();
        } else if (type === 'fixed_amount') {
            this.renderFixedAmountGratuityOptions();
        } else if (type === 'flexible') {
            this.renderFlexibleGratuityOptions();
        }
    }

    initializeDistributionOptions() {
        $('.distribution-option').on('click', function () {
            $('.distribution-option').removeClass('active');
            $(this).addClass('active');
        });
    }

    initializeGratuityAmounts() {
        const self = this;
        $('.gratuity-amount').on('click', function () {
            $('.gratuity-amount').removeClass('active');
            $(this).addClass('active');

            const selectedAmount = $(this).data('amount');
            self.updateSelectedGratuity(selectedAmount);
        });
    }
    renderFixedPercentageGratuityOptions() {
        const $section = $('#gratuityValueSection');
        $section.html(`
            <div class="row mb-4 percentage-gratuity-opt">
                    <div class="col-md-3">
                        <label class="form-label" for="percentages">Percentage (%)</label>
                        <input type="number" name="percentages[]" class="form-control percentages-input" value="" min="0" max="100" placeholder="Enter Percentage">
                        <div class="invalid-feedback">Please enter a positive number.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="descriptions">Description</label>
                        <div class="d-flex align-items-start">
                            <input type="text" name="descriptions[]" class="form-control me-2 descriptions-input" placeholder="Enter Description"
                                style="width: 70%;">
                            <button type="button" class="btn btn-primary addPercentageGratuitybtn">
                                <i class="fas fa-plus me-1"></i>Add
                            </button>
                        </div>
                        <div class="invalid-feedback">Please enter description.</div> 
                    </div>
                </div>`);
    }
    renderFixedAmountGratuityOptions() {
        const $section = $('#gratuityValueSection');
        $section.html(`
            <div class="row mb-4 fixed-amount-gratuity-opt">
                        <div class="col-md-3">
                            <label class="form-label" for="amounts">Amount ($)</label>
                            <input type="number" name="amounts[]" class="form-control amounts-input" value="" min="0" max="100" placeholder="Enter Amount">
                            <div class="invalid-feedback">Please enter a positive number.</div>
                            </div>
                        <div class="col-md-4">
                            <label class="form-label" for="descriptions">Description</label>
                            <div class="d-flex align-items-start">
                                <input type="text" name="descriptions[]" class="form-control me-2 descriptions-input" placeholder="Enter Description"
                                    style="width: 70%;">
                                <button type="button" class="btn btn-primary addFixedAmountGratuitybtn">
                                    <i class="fas fa-plus me-1"></i>Add
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter description.</div> 
                        </div>
                    </div>`);
    }
    renderFlexibleGratuityOptions() {
        const $section = $('#gratuityValueSection');
        $section.html(`
            <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label">Minimum ($)</label>
                <input type="number" class="form-control flexible-gratuity-input" name="min_gratuity" id="min-gratuity"  min="0" step="0.01">
                <div class="invalid-feedback">Please enter a positive number.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Maximum ($)</label>
                <input type="number" class="form-control flexible-gratuity-input" name="max_gratuity" id="max-gratuity"  min="0" step="0.01">
                <div class="invalid-feedback">Please enter a positive number.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label">Default ($)</label>
                <input type="number" class="form-control flexible-gratuity-input" name="default_gratuity" id="default-gratuity"  min="0" step="0.01">
                <div class="invalid-feedback">Please enter a positive number.</div>
            </div>
            </div>`);
    }
    appendPercentageGratuityOptions(evt) {
        const percentageOptHtml = `<div class="row mb-4 percentage-gratuity-opt">
                            <div class="col-md-3">
                                <label class="form-label">Percentage (%)</label>
                                <input type="number" name="percentages[]" class="form-control percentages-input" value="" min="0" max="100" placeholder="Enter Percentage">
                                <div class="invalid-feedback">Please enter a positive number.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Description</label>
                                <div class="d-flex align-items-start">
                                    <input type="text" name="descriptions[]" class="form-control me-2 descriptions-input" placeholder="Enter Description"
                                        style="width: 70%;">
                                    <button type="button" class="btn btn-danger removePercentageGratuitybtn">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please enter description.</div> 
                            </div>
                        </div>`;
        $('#gratuityValueSection .percentage-gratuity-opt:last').after(percentageOptHtml);

    }
    removePercentageGratuityOptions(evt) {
        const $btn = $(evt.currentTarget);
        const $row = $btn.closest('.percentage-gratuity-opt');
        if ($('.percentage-gratuity-opt').length > 1) {
            $row.remove();
        } else {
            this.showNotification('At least one percentage gratuity option is required.', 'warning');
        }
    }
    appendFixedAmountGratuityOptions(evt) {
        const fixedAmountOptHtml = `<div class="row mb-4 fixed-amount-gratuity-opt">
                            <div class="col-md-3">
                                <label class="form-label">Amount ($)</label>
                                <input type="number" name="amounts[]" class="form-control amounts-input" value="" min="0" max="100" placeholder="Enter Amount">
                                <div class="invalid-feedback">Please enter a positive number.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Description</label>
                                <div class="d-flex align-items-start">
                                    <input type="text" name="descriptions" class="form-control me-2 descriptions-input" placeholder="Enter Description"
                                        style="width: 70%;">
                                    <button type="button" class="btn btn-danger removeFixedAmountGratuitybtn">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                                <div class="invalid-feedback">Please enter description.</div> 
                            </div>
                        </div>`;
        $('#gratuityValueSection .fixed-amount-gratuity-opt:last').after(fixedAmountOptHtml);

    }
    removeFixedAmountGratuityOptions(evt) {
        const $btn = $(evt.currentTarget);
        const $row = $btn.closest('.fixed-amount-gratuity-opt');
        if ($('.fixed-amount-gratuity-opt').length > 1) {
            $row.remove();
        } else {
            this.showNotification('At least one fixed amount gratuity option is required.', 'warning');
        }
    }
    updateSelectedGratuity(amount) {
        $('.alert-success .fw-bold').text(`${currencyFormation.symbol + amount}`);
        $('.text-success').each(function () {
            const newTotal = this.baseTotal + parseFloat(amount);
            $(this).text(`${currencyFormation.symbol + newTotal.toLocaleString()}`);
        });
    }

    initializeFlexibleGratuitySettings() {
        const self = this;
        $('.btn-link').on('click', function (e) {
            e.preventDefault();
            self.showCustomAmountInput();
        });
    }

    showCustomAmountInput() {
        const $amountGrid = $('.gratuity-amount').closest('.row');
        $amountGrid.html(`
            <div class="custom-amount-section">
                <label class="form-label">Custom gratuity amount</label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" placeholder="0.00" min="0" step="0.01">
                </div>
                <div class="form-text">Suggested range: $5 - $100</div>
                <button class="btn btn-success w-100 mt-3 custom-apply-btn">Apply Custom Amount</button>
            </div>`);

        $('.custom-apply-btn').on('click', () => this.applyCustomAmount());
    }

    applyCustomAmount() {
        const customAmount = parseFloat($('.custom-amount-section input').val());
        if (customAmount && customAmount > 0) {
            this.updateSelectedGratuity(customAmount);
            this.showNotification('Custom gratuity amount applied successfully!', 'success');
        } else {
            this.showNotification('Please enter a valid amount', 'warning');
        }
    }
    bindEvents() {
        $(document).off('click', '.addPercentageGratuitybtn').on('click', '.addPercentageGratuitybtn', (evt) => {
            this.appendPercentageGratuityOptions(evt);
        });
        $(document).off('click', '.removePercentageGratuitybtn').on('click', '.removePercentageGratuitybtn', (evt) => {
            this.removePercentageGratuityOptions(evt);
        });
        $(document).off('click', '.addFixedAmountGratuitybtn').on('click', '.addFixedAmountGratuitybtn', (evt) => {
            this.appendFixedAmountGratuityOptions(evt);
        });
        $(document).off('click', '.removeFixedAmountGratuitybtn').on('click', '.removeFixedAmountGratuitybtn', (evt) => {
            this.removeFixedAmountGratuityOptions(evt);
        });
        $(document).on('input change', '.gratuity-name-input, .percentages-input, .amounts-input, .descriptions-input, .flexible-gratuity-input', (e) => this.validateInput($(e.target)));
        $(document).off('click', '#pre-gratuity').on('click', '#pre-gratuity', (evt) => {
            $("#post-gratuity").prop("checked", false);
            $("#custom-gratuity-post").prop("checked", false);
        });
        $(document).off('click', '#post-gratuity').on('click', '#post-gratuity', (evt) => {
            $("#pre-gratuity").prop("checked", false);
            $("#custom-gratuity-pre").prop("checked", false);
        });
        $('#pre-gratuity, #post-gratuity').on('change', function () {
            if ($('#pre-gratuity').is(':checked') || $('#post-gratuity').is(':checked')) {
                $('#gratuity-check-group .gratuity-checkbox').removeClass('is-invalid');
                $('#gratuity-check-group .invalid-feedback').hide();
            }
        });
    }

    validateInput($element) {
        if ($element.hasClass('gratuity-name-input')) {
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
        } else if ($element.hasClass('percentages-input')) {
            const value = parseFloat($element.val());
            const isValid = !isNaN(value) && value >= 0 && value <= 100;
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('amounts-input')) {
            const value = parseFloat($element.val());
            const isValid = !isNaN(value) && value >= 0;
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('descriptions-input')) {
            const value = $element.val();
            const isValid = value && value.trim() != '';
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.closest('.col-md-4').find('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.closest('.col-md-4').find('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('flexible-gratuity-input')) {
            const value = parseFloat($element.val());
            const isValid = !isNaN(value) && value >= 0;
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
        const $form = $('#gratuitySettingForm');
        // $form.addClass('was-validated');

        let isValid = true;

        if (!this.validateInput($(`#gratuity-name`))) {
            isValid = false;
        }
        const gratuityType = $('.gratuity-option.active').data('value');

        if (gratuityType == 'fixed_percentage') {
            $('.percentages-input').each((_, el) => {
                if (!this.validateInput($(el))) {
                    isValid = false;
                }
            });
            $('.descriptions-input').each((_, el) => {
                if (!this.validateInput($(el))) {
                    isValid = false;
                }
            });

        } else if (gratuityType == 'fixed_amount') {
            $('.amounts-input').each((_, el) => {
                if (!this.validateInput($(el))) {
                    isValid = false;
                }
            });
            $('.descriptions-input').each((_, el) => {
                if (!this.validateInput($(el))) {
                    isValid = false;
                }
            });
        } else if (gratuityType == 'flexible') {
            $('#min-gratuity, #max-gratuity, #default-gratuity').each((_, el) => {
                if (!this.validateInput($(el))) {
                    isValid = false;
                }
            });
        }

        // At least one must be checked
        if (!$('#pre-gratuity').is(':checked') && !$('#post-gratuity').is(':checked')) {
            isValid = false;

            // highlight checkboxes
            $('#gratuity-check-group .gratuity-checkbox').addClass('is-invalid');
            $('#gratuity-check-group .invalid-feedback').show();

        } else {
            // remove highlight if valid
            $('#gratuity-check-group .gratuity-checkbox').removeClass('is-invalid');
            $('#gratuity-check-group .invalid-feedback').hide();
        }

        if (!isValid) {
            return { isValid: false, data: null };
        }

        const gratuityAmounts = [];

        if (gratuityType == 'fixed_percentage') {
            $('.percentage-gratuity-opt').each((_, element) => {
                const $gratuityOpt = $(element);
                const percentage = parseFloat($gratuityOpt.find('.percentages-input').val());
                const description = $gratuityOpt.find('.descriptions-input').val().trim();
                gratuityAmounts.push({ percentage, description });
            });
        } else if (gratuityType == 'fixed_amount') {
            $('.fixed-amount-gratuity-opt').each((_, element) => {
                const $gratuityOpt = $(element);
                const amount = parseFloat($gratuityOpt.find('.amounts-input').val());
                const description = $gratuityOpt.find('.descriptions-input').val().trim();
                gratuityAmounts.push({ amount, description });
            });
        }


        var tourPackages = $('#tour-packages-create').length
            ? $('#tour-packages-create').val()
            : $('#tour-packages').val();

        const data = {
            gratuityName: $('#gratuity-name').val(),
            tourPackages: tourPackages,
            gratuitySettingId: $('#gratuity-setting-id').val(),
            gratuityType: gratuityType,
            gratuityAmounts: gratuityAmounts,
            minGratuity: (gratuityType == 'flexible') ? parseFloat($('#min-gratuity').val()) : 0,
            maxGratuity: (gratuityType == 'flexible') ? parseFloat($('#max-gratuity').val()) : 0,
            defaultGratuity: (gratuityType == 'flexible') ? parseFloat($('#default-gratuity').val()) : 0,
            preGratuity: $('#pre-gratuity').is(':checked'),
            postGratuity: $('#post-gratuity').is(':checked'),
            customGratuityPre: $('#custom-gratuity-pre').is(':checked'),
            customGratuityPost: $('#custom-gratuity-post').is(':checked'),
            gratuityMandatory: $('#gratuity-mandatory').is(':checked'),
            gratuityDistribution: $('.distribution-option.active').data('value'),
            serviceProviderCut: parseFloat($('#service-provider-cut').val()),
        };
        console.log('isValid after detailed checks:', isValid);
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
                url: this.saveGratuitySettingUrl,
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
        $('#tour-packages').select2({
            placeholder: "Select Packages",
        });
        const self = this;


        var dtbl = $('#datatable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                method: 'POST',
                url: this.fetchSettingsUrl,
            },
            order: [[0, 'asc']],
            columns: [
                {
                    data: 'gratuity_name',
                    name: 'gs.gratuity_name',
                    className: 'fw-bold'
                },
                {
                    data: 'pre_gratuity_enabled',
                    name: 'pre_gratuity_enabled',
                    className: 'fw-bold'
                },
                {
                    data: 'gratuity_type',
                    name: 'gs.gratuity_type',
                    className: 'text-center'
                },
                {
                    data: 'distribution_type',
                    name: 'gs.distribution_type',
                    className: 'text-center'
                },
                {
                    data: 'assigned_packages',
                    name: 'tp.name',
                    className: 'text-center',
                    orderable: false
                },
                {
                    data: 'status',
                    name: 'gs.status',
                    className: 'text-center',
                    searchable: false
                },
                {
                    data: 'action',
                    className: 'text-center',
                    orderable: false,
                    searchable: false
                },
            ],
            pageLength: 25
        });

        $('#save-gratuity-setting').on('click', () => this.handleCreateGratuitySetting());
        $('#addGratuitySettingbtn').on('click', function () {
            $('#listGratuitySetting').hide();
            $('#addGratuitySetting').show();


            // Re-init select2 fresh after div is visible
            $('#addGratuitySetting #tour-packages-create').select2({
                placeholder: "Select Packages",
                dropdownParent: $('#addGratuitySetting') // ensures dropdown stays inside
            });
        });


        $('form').on('submit', function () {
            const $submitBtn = $(this).find('button[type="submit"]');
            $submitBtn.addClass('loading');
            setTimeout(() => {
                $submitBtn.removeClass('loading');
            }, 2000);
        });

    }
}


