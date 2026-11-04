class AgendaCheckinOverview {

    constructor({ buttonSelector, overviewContainer, slotsContainer, url, uTypeAdmin }) {
        this.$button = $(buttonSelector);
        this.$overviewContainer = $(overviewContainer);
        this.$slotsContainer = $(slotsContainer);
        this.url = url;
        this.uTypeAdmin = uTypeAdmin;
        this.currentDate = null;
        this.selectedSlotId = '';

        this.registerEvents();
    }

    registerEvents() {
        this.$button.on('click', (e) => {
            e.preventDefault();
            const date = this.$slotsContainer.find('#date').datepicker('getDate');
            if (!date) return;

            this.currentDate = date;
            this.loadOverview();
        });

        $(document).off("click", "#backToAgenda").on("click", "#backToAgenda", this.backToAgenda.bind(this));
    }

    loadOverview() {
        // let checkinStatus = [];
        const date = moment(this.currentDate).format('YYYYMMDD');
        let tourPackagesId = this.$overviewContainer.find('#tour_package_overview').val() || '';
        let paymentStatus  = this.$overviewContainer.find('#payment_status_overview').val() || 0;
        let slotId = this.$overviewContainer.find('#time-slots-overview, #booking-hotels-overview').filter(':visible').val() || '';
        this.selectedSlotId = slotId;

        const checkinStatus = this.$overviewContainer
            .find('.checkin-checkbox:checked')
            .map(function () { return $(this).val(); })
            .get();
        // this.$overviewContainer.find('.checkin-checkbox:checked').each(function () {
        //     checkinStatus.push($(this).val());
        // });

        $.ajax({
            url: this.url,
            type: "POST",
            data: { 
                date,
                tourPackagesId,
                paymentStatus,
                slotId,
                checkinStatus
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            beforeSend: () => {
                showLoading(); // show overview container (empty for now)
            },
            success: (html) => {
                hideLoading();
                
                this.$slotsContainer.hide(); // hide the agenda slot wise list view
                this.$overviewContainer.html(html).show(); // inject the returned blade HTML
                
                const $dateInput = this.$overviewContainer.find('#agenda-checkin-overview-date');
                if (this.currentDate && $dateInput.length) {
                    $dateInput.val(
                        moment(this.currentDate).format('dddd, MMMM D yy')
                    );
                }

                // restore + bind checkbox filters
                this.initCheckinDropdown(checkinStatus);
                this.initFilters();
                this.initDate();
                this.initDatatable();
                this.toggleCheckinView();
                this.setDefaultOpenSection();
                this.initTimeSlots();
                this.bindTimeSlotFilter();
            },
            error: (xhr) => {
                this.$overviewContainer.html('<p style="color:red;">Failed to load overview.</p>');
                this.$slotsContainer.show(); // fallback → show slots again
            }
        });
    }

    initDate() {
        const $date = this.$overviewContainer.find('#agenda-checkin-overview-date');

        // Destroy old instance
        if ($date.hasClass('hasDatepicker')) {
            $date.datepicker('destroy');
        }

        $date.datepicker({
            dateFormat: 'DD, MM d yy',
            onSelect: () => {
                this.currentDate = $date.datepicker('getDate'); // REAL Date object
                if (this.currentDate) this.loadOverview();
                // if (!this.currentDate) return;

                // this.loadOverview();
            }
        });

        this.initPrevNextButtons();
    }

    initTimeSlots() {
        if (typeof slots === 'undefined') return;

        const $select = this.$overviewContainer.find('#time-slots-overview');
        if (!$select.length) return;

        const selected = this.selectedSlotId || '';

        $select.empty().append('<option value="">Show All Time</option>');

        slots.forEach(slot => {
            if (slot.packageHasSlots) {
                const isSelected = String(slot.id) === String(selected) ? 'selected' : '';

                $select.append(
                    `<option value="${slot.id}" data-time="${slot.tFrom}-${slot.tTo}" ${isSelected}>
                        ${formatTime(slot.tFrom)} - ${formatTime(slot.tTo)}
                    </option>`
                );
            }
        });

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2({ width: '100%' });
    }

    bindTimeSlotFilter() {
        const self = this;

        this.$overviewContainer
            .find('#time-slots-overview, #booking-hotels-overview')
            .off('change.overviewSlot')
            .on('change.overviewSlot', function () {
                const selectedId = $(this).val() || '';
                self.selectedSlotId = selectedId;
                self.loadOverview(); // your existing ajax reload method
            });
    }

    initFilters() {
        let $payment = this.$overviewContainer.find('#payment_status_overview');
        let $tourPackage = this.$overviewContainer.find('#tour_package_overview');

        // Destroy old Select2
        [$payment, $tourPackage].forEach($el => {
            if ($el.hasClass('select2-hidden-accessible')) {
                $el.select2('destroy');
            }
            $el.select2({ width: '100%' });
        });

        // Re-init
        // $payment.select2({ width: '100%' });
        // $tourPackage.select2({ width: '100%' });

        // Bind change
        $payment.off('change.agenda').on('change.agenda', () => this.loadOverview());
        $tourPackage.off('change.agenda').on('change.agenda', () => this.loadOverview());
    }

    initCheckinDropdown(selectedStatuses = []) {
        // let self = this;

        this.$overviewContainer.find('.checkin-checkbox').each(function () {
            $(this).prop('checked', selectedStatuses.includes($(this).val()));
        });

        this.$overviewContainer
            .find('.checkin-checkbox')
            .off('change.agenda')
            .on('change.agenda', () => this.loadOverview());

        // this.$overviewContainer
        //     .find('.checkin-checkbox')
        //     .off('change.agenda')
        //     .on('change.agenda', function () {
        //         self.loadOverview();
        //     });
    }

    initPrevNextButtons() {
        // $('.next-day, .prev-day').off('click.slots');

        $('.next-day-o, .prev-day-o').off('.agenda').on('click.agenda', (e) => {
            if (!this.currentDate) return;

            const incr = parseInt($(e.currentTarget).data('incr-decr'), 10);
            this.currentDate = new Date(this.currentDate);
            this.currentDate.setDate(this.currentDate.getDate() + incr);

            this.loadOverview();
        });
    }

    backToAgenda() {
        window.location = tourBookingDayTimeSlotsUrl();
    }

    initDatatable() {
        let tables = [];

        $('.agenda-checkin-overview-table').each(function () {
            if ($.fn.DataTable.isDataTable(this)) {
                $(this).DataTable().destroy();
            }

            const dt = $(this).DataTable({
                paging: false,
                lengthChange: false,
                searching: false,
                info: false,
                ordering: true,
                columnDefs: [{ targets: 0, type: "time-ampm" }],
                drawCallback: function () {
                    /* ===============================
                    PAID / UNPAID TOTAL CALCULATION
                    =============================== */
                    let api = this.api();
                    let $table = $(api.table().node());
                    let paidTotal = 0;
                    let unpaidTotal = 0;

                    api.rows({ page: 'current' }).nodes().each(function (row) {
                        let $cell = $(row).find('.total-amount');
                        if (!$cell.length) return;

                        const amount = parseFloat($cell.text().replace(/[^0-9.-]+/g, '')) || 0;
                        $cell.data('paid') == 1 ? paidTotal += amount : unpaidTotal += amount;
                        // let amount = parseFloat(
                        //     $cell.text().replace(/[^0-9.-]+/g, '')
                        // ) || 0;

                        // if ($cell.data('paid') == 1) {
                        //     paidTotal += amount;
                        // } else {
                        //     unpaidTotal += amount;
                        // }
                    });

                    $table.find('.paid-total').text(
                        currencyFormation.symbol + paidTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    );

                    $table.find('.unpaid-total').text(
                        currencyFormation.symbol + unpaidTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    );
                    // $table.find('.paid-total').text(currencyFormation.symbol + paidTotal.toFixed(2));
                    // $table.find('.unpaid-total').text(currencyFormation.symbol + unpaidTotal.toFixed(2));
                }
                /*,
                columnDefs: [
                    { targets: 0, type: "time-ampm" },
                    { targets: 0, orderable: true }
                ]*/
            });

            tables.push(dt);
        });

        // First table = master sorter
        if (tables.length > 1) {
            const master = tables[0];

            master.off('order.sync').on('order.sync', function () {
                const order = master.order();
                tables.slice(1).forEach(dt => dt.order(order).draw(false));
                // tables.forEach((dt, index) => {
                //     if (index === 0) return; // skip master itself
                //     dt.order(order).draw(false);
                // });
            });

            $('#master-sort-table tbody').hide();
        }
    }

    toggleCheckinView() {
        const self = this;

        this.$overviewContainer
            .find('.toggle-checkin')
            .off('click.collapse')
            .on('click.collapse', function () {

                const $clickedHeader = $(this);
                const target = $clickedHeader.data('target');
                const $currentBox = $(target);
                const $currentIcon = $clickedHeader.find('.collapse-icon');

                // Close all others
                self.$overviewContainer.find('.checkin-collapse').not($currentBox).slideUp(200);
                self.$overviewContainer.find('.collapse-icon').not($currentIcon).css('transform', 'rotate(0deg)');

                // Toggle current
                if ($currentBox.is(':visible')) {
                    $currentBox.slideUp(200);
                    $currentIcon.css('transform', 'rotate(0deg)');   // collapsed → down
                } else {
                    $currentBox.slideDown(200);
                    $currentIcon.css('transform', 'rotate(180deg)'); // expanded → up
                }
            });
    }

    setDefaultOpenSection() {
        // Close all
        this.$overviewContainer.find('.checkin-collapse').hide();
        this.$overviewContainer.find('.collapse-icon').css('transform', 'rotate(0deg)');

        // Open only the first section
        const $firstHeader = this.$overviewContainer.find('.toggle-checkin').first();
        const target = $firstHeader.data('target');
        const $firstBox = $(target);
        const $firstIcon = $firstHeader.find('.collapse-icon');

        $firstBox.show();
        $firstIcon.css('transform', 'rotate(180deg)'); // arrow up
    }
}

const agendaCheckinOverview = new AgendaCheckinOverview({
    buttonSelector: '#agendaCheckinOverview',
    overviewContainer: '.checkin-overview-container',
    slotsContainer: '.day-time-slots',
    url: agendaCheckinOverviewUrl,
    uTypeAdmin: uTypeAdmin
});

$.extend($.fn.dataTable.ext.type.order, {
    "time-ampm-pre": function (data) {
        return Date.parse('01/01/2000 ' + data);
    }
});

// ===== Check-in dropdown toggle (bind ONCE) =====
$(document).on('click', '#checkinToggle', function (e) {
    e.stopPropagation();
    $('#checkinMenu').toggleClass('hidden');
});

$(document).on('click', function () {
    $('#checkinMenu').addClass('hidden');
});