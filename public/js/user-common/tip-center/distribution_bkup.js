class GratuityDistributions {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.fetchDistributionsUrl = props.fetchDistributionsUrl;
        this.fetchDistributionInfoUrl = props.fetchDistributionInfoUrl;
        this.saveDistributionAmountsUrl = props.saveDistributionAmountsUrl;
        this.processTransferUrl = props.processTransferUrl;
        this.processBulkTransferTipsUrl = props.processBulkTransferTipsUrl;
        this.exportDistributionsUrl = props.exportDistributionsUrl;
        this.bookingInfoUrl = props.bookingInfoUrl;
        this.todayDate = props.todayDate;
        this.defaultDate = props.defaultDate;
        this.bookingPrefix = props.bookingPrefix;
        this.guides = [];
        //this.initSelect2();
        this.addGlobalListeners();
        this.bindEvents();
    }
    initSelect2() {
        $('#tour-package-filter').select2({
            placeholder: "Select Package"
        });
        $('#guide-filter').select2({
            placeholder: "Select Guide"
        });
        $('#slot-filter').select2({
            placeholder: "Select Time Slot"
        });
    }
    bindEvents() {
        $(document).off('click', '.guide-header').on('click', '.guide-header', (evt) => {
            this.toggleSlot(evt);
        });
        $(document).off('change', '#tour-package-filter').on('change', '#tour-package-filter', (evt) => {
            this.fetchDistributions();
        });
        $(document).off('change', '#guide-filter').on('change', '#guide-filter', (evt) => {
            this.fetchDistributions();
        });
        $(document).off('change', '#slot-filter').on('change', '#slot-filter', (evt) => {
            this.renderSlotDistributions(evt);
        });
        $(document).off('change', '#search-input').on('change', '#search-input', (evt) => {
            this.fetchDistributions();
        });
        $(document).off('click', '#expand-all-btn').on('click', '#expand-all-btn', (evt) => {
            this.toggleAllSlots(evt);
        });
        $(document).off('click', '#clear-filters-btn').on('click', '#clear-filters-btn', (evt) => {
            this.clearFilters();
        });

        var firstVal = $('#tour-package-filter option:not([disabled]):eq(1)').val();
        $('#tour-package-filter').val(firstVal).trigger('change');

        $('#date-range-filter').on('apply.daterangepicker', (ev, picker) => {
            this.fetchDistributions();
        });
        $(document).off('click', '.transfer-btn').on('click', '.transfer-btn', (evt) => {
            this.processTransferTip(evt);
        });
        $(document).off('click', '.transfer-all-btn').on('click', '.transfer-all-btn', (evt) => {
            this.processBulkTransferTips(evt);
        });
        $(document).off('click', '#select-all-btn').on('click', '#select-all-btn', (evt) => {
            var checkboxes = $('.chckbox-booking');
            var allChecked = checkboxes.length === checkboxes.filter(':checked').length;
            checkboxes.prop('checked', !allChecked);
        });
        $(document).on('change', '.check-all-slot-bookings', function () {
            // Get the "slot" container for this specific table
            let slotContainer = $(this).closest('table');

            // Toggle only the checkboxes inside this container
            slotContainer.find('.chckbox-booking').prop('checked', $(this).prop('checked'));
        });
        $(document).off('click', '.process-transfer-btn').on('click', '.process-transfer-btn', (evt) => {
            this.renderProcessTransfer(evt);
        });
        $(document).off('click', '#submit-process-transfer-btn').on('click', '#submit-process-transfer-btn', (evt) => {
            this.handleProcessTransfer(evt);
        });
        $(document).off('click', '#btn-export').on('click', '#btn-export', (evt) => {
            this.exportDistributionsData();
        });
        $(document).off('input change', '.custom-slider').on('input change', '.custom-slider', (evt) => {
            this.updateDistribtutionAmounts(evt);
        });
        $(document).off('change', '.custom-slider').on('change', '.custom-slider', (evt) => {
            this.saveDistributionAmountsForGuides(evt);
        });
        // Edit icon click
        $(document).on('click', '.edit-amount', function () {
            const $amount = $(this).closest('.amount');
            const value = parseFloat($amount.find('.amount-value').text().replace('$', '')) || 0;

            $amount.find('.amount-input').val(value.toFixed(2));
            $amount.find('.amount-value, .edit-amount').addClass('d-none');
            $amount.find('.edit-wrapper, .amount-input').removeClass('d-none');
        });
        // Cancel edit
        $(document).on('click', '.btn-cancel', function () {
            const $amount = $(this).closest('.amount');
            $amount.find('.edit-wrapper').addClass('d-none');
            $amount.find('.amount-value, .edit-amount').removeClass('d-none');
        });
        // Done / Save
        $(document).on('click', '.btn-done', (evt) => {
            this.finishAmountEdit(evt);
        });
    }
    addGlobalListeners() {
        //this.renderDateRangePicker();
        $('.custom-slider').each(function () {
            this.updateSliderLabel($(this));
        });
    }
    renderDateRangePicker() {
        const start = moment();
        const end = moment();

        $('#date-range-filter').daterangepicker({
            startDate: start,
            endDate: end,
            autoUpdateInput: false,
            ranges: {
                'Today': [moment(), moment()],
                'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        }, this.setStartAndEndDate);

        this.setStartAndEndDate(start, end);
    }
    setStartAndEndDate(start, end) {
        $('#date-range-filter').val(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
    }
    updateDistribtutionAmounts(evt) {
        const $slider = $(evt.currentTarget);
        // Update label text and position
        this.updateSliderLabel($slider);

        const $row = $slider.closest('tr');
        const fullId = $row.data('id'); // e.g., 39165_0
        const bookingId = fullId.split('_')[0]; // "39165"
        const percent = parseFloat($slider.val());

        const $allRows = $(`tr[data-id^="${bookingId}_"]`);
        const $firstRow = $(`tr[data-id="${bookingId}_0"]`);
        const $firstAmount = $firstRow.find('.amount');
        const totalAmount = parseFloat($firstAmount.data('total'));
        const guideShare = this.roundout(totalAmount * percent / 100);
        const remainingAmount = totalAmount - guideShare;
        const otherRows = $allRows.not($firstRow);
        const numOtherRows = otherRows.length;

        // Update first row amount
        $firstRow.find('.amount-value').text(`${currencyFormation.symbol + guideShare.toFixed(2)}`);

        // Update other rows
        if (numOtherRows > 0) {
            const perGuideAmount = this.roundout(remainingAmount / numOtherRows);
            otherRows.each(function () {
                $(this).find('.amount-value').text(`${currencyFormation.symbol + perGuideAmount.toFixed(2)}`);
            });
        }
    }
    saveDistributionAmountsForGuides(evt) {
        const $slider = $(evt.currentTarget);
        const $row = $slider.closest('tr');
        const fullId = $row.data('id'); // e.g., 39165_0
        const bookingId = fullId.split('_')[0]; // "39165"
        const percent = parseFloat($slider.val());
        const guideId = $row.data('guide-id');

        $.ajax({
            url: this.saveDistributionAmountsUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                bookingId: bookingId,
                percent: percent,
                guideId: guideId
            })
        }).always(hideLoading())
            .fail(function (jqXHR) {
                hideLoading();
            });
    }
    finishAmountEdit(evt) {
        const $input = $(evt.currentTarget).siblings('.amount-input');
        const $amountDiv = $input.closest('.amount');
        const $valueSpan = $amountDiv.find('.amount-value');
        const $editIcon = $amountDiv.find('.edit-amount');
        let newAmount = parseFloat($input.val());
        if (isNaN(newAmount)) {
            swal('Invalid Amount', 'Please enter a valid amount.', 'warning');
            return;
        }
        if (newAmount < 0) newAmount = 0;
        const $row = $input.closest('tr');
        const bookingId = $row.data('id').toString().split('_')[0];
        const index = $row.data('id').toString().split('_')[1];
        const totalAmount = parseFloat($amountDiv.data('total'));
        const percent = totalAmount > 0 ? (this.roundout((newAmount / totalAmount) * 100)) : 0;

        // Update UI
        $valueSpan.text(`${currencyFormation.symbol + newAmount.toFixed(2)}`);
        $input.addClass('d-none');
        $valueSpan.removeClass('d-none');
        $editIcon.removeClass('d-none');
        $amountDiv.find('.edit-wrapper').addClass('d-none');

        // Update other guide rows for the same booking
        let remainingAmount = totalAmount - newAmount;
        const $allRows = $(`tr[data-id^="${bookingId}_"]`);
        const $otherRows = $allRows.not($row);
        const eachAmount = remainingAmount / $otherRows.length;

        // Update slider
        if (index > 0) {
            const percent = totalAmount > 0 ? this.roundout((eachAmount / totalAmount) * 100) : 0;
            const $slider = $(`tr[data-id^="${bookingId}_0"]`).find('.custom-slider');
            const $label = $(`tr[data-id^="${bookingId}_0"]`).find('.slider-label');
            $slider.val(percent);
            $label.text(`${percent.toFixed(2)}%`);
        } else {
            const $slider = $row.find('.custom-slider');
            const $label = $row.find('.slider-label');
            $slider.val(percent);
            $label.text(`${percent.toFixed(2)}%`);
        }

        $otherRows.each(function () {
            const $otherAmount = $(this).find('.amount');
            const $otherValueSpan = $otherAmount.find('.amount-value');

            $otherValueSpan.text(`${currencyFormation.symbol + eachAmount.toFixed(2)}`);
        });

        const guideId = $input.closest('tr').data('guide-id');

        $.ajax({
            url: this.saveDistributionAmountsUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                bookingId: bookingId,
                amount: newAmount,
                guideId: guideId
            })
        }).always(hideLoading())
            .fail(function (jqXHR) {
                hideLoading();
            });

    }
    updateSliderLabel($slider) {
        const $label = $slider.siblings('.slider-label');
        const value = $slider.val();
        $label.text(value + '%');

        const min = parseFloat($slider.attr('min')) || 0;
        const max = parseFloat($slider.attr('max')) || 100;
        const percent = (value - min) / (max - min);
        const sliderWidth = $slider[0].offsetWidth;
        const labelLeft = percent * sliderWidth;

        $label.css('left', `${labelLeft}px`);
    }
    clearFilters() {
        var firstVal = $('#tour-package-filter option:not([disabled]):eq(1)').val();
        $('#tour-package-filter').val(firstVal);
        $('#guide-filter').val(0);
        $('#slot-filter').val('0');
        $('#date-range-filter').val(this.defaultDate.format('MMMM D, YYYY') + ' - ' + this.defaultDate.format('MMMM D, YYYY')).change();
        this.fetchDistributions();
        this.updateExpandButton();
    }
    toggleSlot(evt) {
        var target = $(evt.currentTarget);
        var slotId = target.data('id');
        var chevronIcon = $(`#chevron-slot-${slotId}`);
        var content = $(`#content-slot-${slotId}`);
        if (content.hasClass('show')) {
            content.removeClass('show');
            chevronIcon.removeClass('rotated');
        } else {
            content.addClass('show');
            chevronIcon.addClass('rotated');
        }
        this.updateExpandButton();

    }
    toggleAllSlots(evt) {
        const allCards = document.querySelectorAll('.guide-card');
        allCards.forEach(card => {
            const bookingContent = card.querySelector('.booking-content');
            const chevronIcon = card.querySelector('.chevron-icon');

            if (bookingContent) {
                if (bookingContent.classList.contains('show')) {
                    bookingContent.classList.remove('show');
                } else {
                    bookingContent.classList.add('show');
                }
            }

            if (chevronIcon) {
                if (chevronIcon.classList.contains('rotated')) {
                    chevronIcon.classList.remove('rotated');
                } else {
                    chevronIcon.classList.add('rotated');
                }
            }
        });

        this.updateExpandButton();
    }
    updateExpandButton() {
        const expandBtn = document.getElementById('expand-all-btn');
        const allCards = document.querySelectorAll('.guide-card');
        const allExpanded = Array.from(allCards).every(card => {
            const bookingContent = card.querySelector('.booking-content');
            return bookingContent && bookingContent.classList.contains('show');
        });
        if (allExpanded) {
            expandBtn.innerHTML = '<i class="fas fa-compress"></i> Collapse All';
        } else {
            expandBtn.innerHTML = '<i class="fas fa-expand"></i> Expand All';
        }

    }

    fetchDistributions() {
        var self = this;
        var packageId = $('#tour-package-filter').val();
        var guideId = $('#guide-filter').val();
        var slotId = $('#slot-filter').val();
        var dateRange = $('#date-range-filter').val();
        const [fromDate, toDate] = dateRange.split(" - ");
        var search = $('#search-input').val().trim();

        if (!fromDate || !toDate) {
            swal('Invalid Input', 'Please select a tour package and a date.', 'warning');
            return;
        }

        this.showLoader();
        $.ajax({
            url: this.fetchDistributionsUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                packageId: packageId,
                guideId: guideId,
                slotId: slotId,
                fromDate: fromDate,
                toDate: toDate,
                searchTerm: search,
            }),
            success: this.renderDistributions.bind(this),
        }).always(this.hideLoader())
            .fail(function (jqXHR) {
                self.hideLoader();
            });
    }
    renderDistributions(data) {
        var distributions = data.distributions;
        var slots = data.slots;

        var html = ``;

        distributions.forEach(distribution => {

            html += `<div class="guide-card" data-slot="${distribution.tour_slot_id}">
                        <div class="guide-header" data-id="${distribution.tour_slot_id}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-chevron-right chevron-icon rotated" id="chevron-slot-${distribution.tour_slot_id}"></i>
                                        <div class="avatar-fallback d-none">ER</div>
                                    </div>

                                    <div>
                                        <div class="d-flex align-items-center gap-3 mb-1">
                                            <h5 class="mb-0 fw-bold">${formatTime(distribution.time_from)} - ${formatTime(distribution.time_to)}</h5>
                                        </div>
                                        <p class="text-muted mb-0 d-none">${distribution.package_name}</p>
                                    </div>
                                </div>

                                <div class="d-flex gap-4">
                                    <div class="stat-item">
                                        <div class="stat-value text-dark">${distribution.bookings.length}</div>
                                        <div class="stat-label">Bookings</div>
                                    </div>
                                    <div class="stat-item d-none">
                                        <div class="stat-value text-success">$1,045</div>
                                        <div class="stat-label">Revenue</div>
                                    </div>
                                    <div class="stat-item d-none">
                                        <div class="stat-value text-primary">4</div>
                                        <div class="stat-label">Upcoming</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-content show" id="content-slot-${distribution.tour_slot_id}">
                            <div class="p-3">
                                <div class="bg-white rounded shadow-sm border overflow-hidden">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                        <th class="py-2 px-3 text-uppercase small fw-medium text-muted d-none"><input type="checkbox" class="check-all-slot-bookings" data-id="${distribution.tour_slot_id}"></th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted">Booking</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted">Tour</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted">Date</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted">Guide</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted text-center">Amount</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted text-center">Status</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted text-center">Total</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted text-center d-none">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

            distribution.bookings.forEach(booking => {
                var guides = booking.guides || [];
                html += this.renderBookingDistribution(booking, distribution, guides);
            });
            html += `</tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    </div>`;


        });

        $('#bookings-container').html(html);
        $('#totalBookingsCount').text(data.totalBookingsCount);
        $('#totalPendingBookingsCount').text(data.totalPendingBookingsCount);
        $('#totalCompletedBookingsCount').text(data.totalCompletedBookingsCount);
        $('#totalPendingAmount').text(`${currencyFormation.symbol + data.totalPendingGrauity.toFixed(2)}`);
        $('#totalTransferredAmount').text(`${currencyFormation.symbol + data.totalTransferredGratuity.toFixed(2)}`);
        if (slots.length > 0) {
            var slotFilter = $('#slot-filter');
            slotFilter.empty();
            slotFilter.append('<option value="0">Show All Time</option>');
            slots.forEach(slot => {
                slotFilter.append(`<option value="${slot.tour_slot_id}">${formatTime(slot.time_from)} - ${formatTime(slot.time_to)}</option>`);
            });
        }
    }
    renderBookingDistribution(booking, distribution, guides) {
        let guideHtml = '';

        guides.forEach((guide, index) => {
            guideHtml += `<tr class="table-row-hover" data-id="${booking.booking_id}_${index}" data-guide-id="${guide.guide_id}">
            <td class="py-2 px-3 d-none">`;
            if (index == 0) {
                guideHtml += `<input type="checkbox" class="form-check-input me-2 chckbox-booking" data-id="${booking.booking_id}" >`;
            }

            guideHtml += `</td><td class="py-2 px-3">`;
            if (index > 0) {
                guideHtml += `<div class="ps-4">
                                <div class="text-muted small">↳ Same tour</div>
                            </div>`;
            } else {
                var bookingInfoUrl = this.bookingInfoUrl.replace(':id', booking.booking_id);
                guideHtml += `<div class="d-flex align-items-center">
                                                    
                                                    <div>
                                                       
                                                        <div class="text-muted" style="font-size: 13px;"><a href="${bookingInfoUrl}" target="_blank">${this.bookingPrefix}${booking.booking_id}</a></div>
                                                    </div>
                                                </div>`
            }
            guideHtml += `</td> 
                                            <td class="py-2 px-3">`;
            if (index > 0) {
                guideHtml += `<div class="text-muted small">—</div>`;
            } else {
                guideHtml += `<div class="d-flex align-items-center small">   
                                                    ${distribution.package_name}
                                                </div>`;
            }
            guideHtml += `</td><td class="py-2 px-3"> `;
            if (index > 0) {
                guideHtml += `<div class="text-muted small">—</div>`;
            } else {
                guideHtml += `<div class="d-flex align-items-center small">
                <i class="fas fa-calendar text-muted me-1" style="font-size: 13px;"></i>
                                                    ${booking.date}
                                                </div>`;
            }

            guideHtml += `</td><td class="py-2 px-3">
                                                <div class="d-flex align-items-center">

                                                    <div>
                                                        <div class="fw-medium small">${guide.guide_name}</div>
                                                        <div class="text-muted d-none" style="font-size: 11px;">${guide.role_type}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 text-center">
                                                <div class="fw-semibold amount d-inline-flex align-items-center position-relative" data-total="${booking.total_gratuity}">
                                                <span class="amount-value">${currencyFormation.symbol + guide.distribution_amount}</span>`;
            if (guides.length > 1 && guide.distributed_amount == 0) {
                guideHtml += `<i class="fas fa-edit ms-2 text-primary edit-amount" style="cursor: pointer; font-size: 12px;"></i>
       <div class="edit-wrapper d-none ms-2"  style="display: inline-flex;">
            <input type="text" class="form-control amount-input me-1" style="width: 90px; height: 28px; font-size: 14px; padding: 2px 6px;">
            <button type="button" class="btn btn-success btn-sm btn-done me-1" style="padding: 2px 8px;">✔</button>
            <button type="button" class="btn btn-danger btn-sm btn-cancel" style="padding: 2px 8px;">✖</button>
        </div>
                                               `;
            }
            guideHtml += `</div>`;
            if (index === 0 && guides.length > 1 && guide.distributed_amount == 0) {
                guideHtml += `<div class="slider-wrapper position-relative">
                                                    <input type="range" class="form-range custom-slider" min="0" max="100" step="1" value="${guide.expected_share}">
                                                    <span class="slider-label">${parseFloat(guide.expected_share)}%</span>
                                                </div>`;
            }
            guideHtml += `</td>
                                            <td class="py-2 px-3 text-center">`;
            if (guide.distributed_amount > 0) {
                guideHtml += `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                                                    <i class="fas fa-check me-1" style="font-size: 10px;"></i>
                                                    Transferred
                                                </span>`;
            } else {
                guideHtml += `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25">
                                <i class="fas fa-clock me-1" style="font-size: 10px;"></i>
                                Pending
                            </span>`;
            }


            guideHtml += `</td><td class="py-2 px-3 text-center">`;
            if (index > 0) {
                guideHtml += `<div class="text-muted small">—</div>`;
            } else {
                guideHtml += `<div class="fw-bold">${currencyFormation.symbol + booking.total_gratuity}</div>`;
                if (guide.distributed_amount > 0) {
                    guideHtml += `<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 mt-1">
                                                        <i class="fas fa-check me-1" style="font-size: 10px;"></i>
                                                        Transferred
                                                    </span>`;
                } else {
                    guideHtml += `<span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25 mt-1">
                                    <i class="fas fa-clock me-1" style="font-size: 10px;"></i>
                                    Pending
                                </span>`;
                }
            }
            guideHtml += `</td>
            <td class="py-2 px-3 text-center d-none">`;

            if (index === 0 && guide.distributed_amount == 0) {
                guideHtml += `<button class="btn btn-primary btn-sm transfer-btn" data-id="${booking.booking_id}" style="padding: 2px 8px;">
                                    <i class="fas fa-paper-plane me-1" style="font-size: 10px;"></i>Transfer
                                </button>`;
            } else {
                guideHtml += `-`;
            }

            guideHtml += `</td></tr>`;
        });
        return guideHtml;
    }
    renderSlotDistributions(evt) {
        const slotId = $(evt.currentTarget).val();
        const allCards = document.querySelectorAll('.guide-card');
        allCards.forEach(card => {
            const cardSlotId = card.getAttribute('data-slot');
            const bookingContent = card.querySelector('.booking-content');
            if (bookingContent) {
                if (slotId === '0') {
                    card.classList.remove('d-none');
                } else if (cardSlotId === slotId) {
                    card.classList.remove('d-none');
                } else {
                    card.classList.add('d-none');
                }
            }
        });
    }
    exportDistributionsData() {
        var packageId = $('#tour-package-filter').val();
        var guideId = $('#guide-filter').val();
        var slotId = $('#slot-filter').val();
        var dateRange = $('#date-range-filter').val();
        const [fromDate, toDate] = dateRange.split(" - ");
        var search = $('#search-input').val().trim();

        if (!packageId || !fromDate || !toDate) {
            swal('Invalid Input', 'Please select a tour package and a date.', 'warning');
            return;
        }
        var exportDistributionsUrl = this.exportDistributionsUrl;
        exportDistributionsUrl = exportDistributionsUrl.replace(':fromDate', fromDate)
            .replace(':toDate', toDate)
            .replace(':tourPackageId', packageId)
            .replace(':guideId', guideId)
            .replace(':searchTerm', search);

        window.location = exportDistributionsUrl;

    }

    processTransferTip(evt) {
        const $button = $(evt.currentTarget);
        const bookingId = $button.data('id');
        var processTransferUrl = this.processTransferUrl;
        swal({
            title: 'Confirm Transfer',
            text: 'Are you sure you want to transfer this gratuity?',
            icon: 'warning',
            buttons: {
                cancel: 'Cancel',
                confirm: {
                    text: 'Transfer',
                    value: true,
                    className: 'btn btn-primary'
                }
            },
        }).then((willTransfer) => {
            if (willTransfer) {
                showLoading();
                $.ajax({
                    url: processTransferUrl,
                    method: 'POST',
                    data: JSON.stringify({
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        bookingId: bookingId
                    }),
                    contentType: 'application/json',
                    success: this.refreshDistributions.bind(this),
                })
                    .always(hideLoading)
                    .fail(function (jqXHR) {
                        hideLoading();
                        showSubmissionError(jqXHR);
                    });
            }
        });

    }
    refreshDistributions() {
        this.fetchDistributions();
        swal('Success', 'Tips has been transferred successfully.', 'success');
    }
    // Placeholder for processing all transfer tips
    processBulkTransferTips() {
        let checkedValues = [];
        $('.chckbox-booking:checked').each(function () {
            checkedValues.push($(this).data('id')); // Retrieves the value from the data attribute
        });

        if (checkedValues.length === 0) {
            swal('No Selection', 'Please select at least one booking to transfer tips.', 'warning');
            return;
        }
        var processBulkTransferTipsUrl = this.processBulkTransferTipsUrl;
        swal({
            title: 'Confirm Transfer',
            text: 'Are you sure you want to transfer these tips?',
            icon: 'warning',
            buttons: {
                cancel: 'Cancel',
                confirm: {
                    text: 'Transfer',
                    value: true,
                    className: 'btn btn-primary'
                }
            },
        }).then((willTransfer) => {
            if (willTransfer) {
                showLoading();
                $.ajax({
                    url: processBulkTransferTipsUrl,
                    method: 'POST',
                    data: JSON.stringify({
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        bookingIds: checkedValues
                    }),
                    contentType: 'application/json'
                }).done(function (response) {
                    swal('Success', 'Tips has been transferred successfully.', 'success');
                })
                    .always(hideLoading)
                    .fail(function (jqXHR) {
                        hideLoading();
                        showSubmissionError(jqXHR);
                    });
            }
        });

    }
    renderProcessTransfer(evt) {
        const gratuityId = $(evt.currentTarget).data('id');
        var fetchDistributionInfoUrl = this.fetchDistributionInfoUrl.replace(':id', gratuityId);

        showLoading();
        $.ajax({
            url: fetchDistributionInfoUrl,
            method: 'GET',
            contentType: 'application/json'
        })
            .done(function (response) {
                var tourBooking = response.tourBooking;
                var gratuity = response.gratuity;
                var guides = response.guidesInfo;
                this.guides = guides;
                var totalDistributed = response.totalDistributed;
                let bookingInfoHtml = `<div class="col-md-6">
													<strong>Booking ID:</strong> <span id="modalBookingId" style="color: #0d6efd;">#${tourBooking.booking_id}</span>
													<input type="hidden" id="booking-id" value="${tourBooking.booking_id}">
													<input type="hidden" id="gratuity-id" value="${gratuity.id}" />
                                                    <input type="hidden" id="total-tip" value="${gratuity.amount - totalDistributed}" />
												</div>
												<div class="col-md-6">
													<strong>Customer:</strong> <span id="modalCustomer">${tourBooking.customer_name}</span>
												</div>
												<div class="col-md-6">
													<strong>Tour:</strong> <span id="modalTour">${tourBooking.package_name}</span>
												</div>
												<div class="col-md-6">
													<strong>Date:</strong> <span id="modalDate">${tourBooking.date}</span>
												</div>`;

                $("#processTransferModal #booking-info-div").html(bookingInfoHtml);

                let guideHtml = '';
                for (let i = 0; i < guides.length; i++) {
                    var distributionAmount = guides[i].expected_share - guides[i].total_distributed;
                    guideHtml += `<div class="row guide-opt align-items-end mb-3">
                                <!-- Guide Name Display -->
                                <div class="col-md-6">
                                    <label class="form-label">Guide</label>
                                    <div class="form-control-plaintext fw-semibold">${guides[i].guide_name}</div>
                                    <input type="hidden" name="booking_guide_id" class="booking-guide-id" value="${guides[i].booking_guide_id}"> <!-- Hidden ID if needed -->
                                </div>
                                <div class="col-md-6">
                                    <label for="amount" class="form-label">Amount</label>
                                    <input type="number" class="form-control amount-inputs" id="amount" name="amount" value="${distributionAmount}" placeholder="Enter amount">
                                    <div class="invalid-feedback">Please enter amount.</div>
                                </div>

                            </div>`;
                }
                $("#processTransferModal #guideOptsDiv").html(guideHtml);
                $('#processTransferModal').modal('show');
            })
            .always(hideLoading)
            .fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            });

    }

    showLoader() {
        document.getElementById('loading').style.display = 'block';
        document.getElementById('bookings-container').style.display = 'none';
    }

    hideLoader() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('bookings-container').style.display = 'block';
    }

    roundout(amount, places = 2) {
        if (places < 0) {
            places = 0;
        }

        let x = Math.pow(10, places);
        let formul = (amount * x).toFixed(10);
        return (amount >= 0 ? Math.ceil(formul) : Math.floor(formul)) / x;
    }
}


