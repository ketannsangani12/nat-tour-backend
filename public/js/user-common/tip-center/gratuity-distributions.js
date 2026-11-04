class GratuityDistributions {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.fetchDistributionsUrl = props.fetchDistributionsUrl;
        this.fetchDistributionInfoUrl = props.fetchDistributionInfoUrl;
        this.saveDistributionAmountsUrl = props.saveDistributionAmountsUrl;
        this.processAcceptTipsUrl = props.processAcceptTipsUrl;
        this.processBulkAcceptTipsUrl = props.processBulkAcceptTipsUrl;
        this.processBulkCompleteTipsUrl = props.processBulkCompleteTipsUrl;
        this.exportDistributionsUrl = props.exportDistributionsUrl;
        this.bookingInfoUrl = props.bookingInfoUrl;
        this.distributionSchedules = props.distributionSchedules;
        this.todayDate = props.todayDate;
        this.defaultDate = props.defaultDate;
        this.currentYear = moment().year();
        this.currentMonth = moment().month() + 1;
        this.startDate = '';
        this.endDate = '';
        this.payrollDate = '';
        this.totalPendingBookingsCount = 0;
        this.totalReadyBookingsCount = 0;
        this.totalCompletedBookingsCount = 0;
        this.bookingPrefix = props.bookingPrefix;
        this.guides = [];
        this.bookings = [];
        this.directTips = [];
        this.readyBookings = [];
        this.readyDirectTips = [];
        this.resources = this.getMonthResources();
        this.dp = null; // scheduler instance
        this.addGlobalListeners();
        this.bindEvents();
    }
    getMonthResources() {
        return [
            { name: "January", id: "1", days: 31 },
            { name: "February", id: "2", days: 28 }, // leap year adjust later
            { name: "March", id: "3", days: 31 },
            { name: "April", id: "4", days: 30 },
            { name: "May", id: "5", days: 31 },
            { name: "June", id: "6", days: 30 },
            { name: "July", id: "7", days: 31 },
            { name: "August", id: "8", days: 31 },
            { name: "September", id: "9", days: 30 },
            { name: "October", id: "10", days: 31 },
            { name: "November", id: "11", days: 30 },
            { name: "December", id: "12", days: 31 },
        ];
    }
    bindEvents() {
        $(document).off('click', '.guide-header').on('click', '.guide-header', (evt) => {
            this.toggleSlot(evt);
        });
        $(document).off('click', '.pending-btn').on('click', '.pending-btn', (evt) => {
            this.processAcceptTip(evt);
        });
        $(document).off('click', '.ready-all-btn').on('click', '.ready-all-btn', (evt) => {
            this.processBulkAcceptTips(evt);
        });
        $(document).off('click', '#ready-schedule-btn').on('click', '#ready-schedule-btn', (evt) => {
            var offset = $(evt.currentTarget).offset();
            $('#datepicker-container').css({
                top: offset.top + $(evt.currentTarget).outerHeight() + 5,
                left: offset.left,
                display: 'block'
            });
        });

        $(document).mouseup(function (e) {
            var container = $("#datepicker-container");
            if (!container.is(e.target) && container.has(e.target).length === 0 &&
                !$('#ready-schedule-btn').is(e.target)) {
                container.hide();
            }
        });
        $(document).off('click', '#datepicker-done-btn').on('click', '#datepicker-done-btn', (evt) => {
            if (this.totalPendingBookingsCount == 0) {
                var selectedDate = $('#datepicker-container').datepicker('getFormattedDate', 'yyyy-mm-dd');
                this.processBulkCompleteTips(selectedDate, this.startDate, this.endDate);
            }
            $('#datepicker-container').hide();
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
        this.initScheduler();
        $('.custom-slider').each(function () {
            this.updateSliderLabel($(this));
        });
        // Initialize inline datepicker inside the container
        $('#datepicker-container').datepicker({
            startDate: '-0d',
            todayHighlight: true,
            autoclose: false
        });

        // Add Done button inside the calendar
        $('#datepicker-container').append(
            '<div class="datepicker-footer"><button id="datepicker-done-btn" class="btn btn-sm btn-primary w-100">Done</button></div>'
        );

    }
    initScheduler() {
        const resources = this.getMonthResources();
        const firstDayOfYear = moment().startOf('year').format("YYYY-MM-DD");
        this.dp = new DayPilot.Scheduler("timeline", {
            startDate: firstDayOfYear,
            days: 31, // always show 31 columns
            scale: "Day",
            cellWidth: 60,
            timeHeaders: [{
                groupBy: "Day",
                format: "d"
            }],
            resources: this.resources,
            heightSpec: "Max",
            moveBy: "None",
            eventHeight: 50,
            eventResizeHandling: "Update",
            // Context Menu
            contextMenu: new DayPilot.Menu({
                items: [
                    {
                        text: "View",
                        onClick: (args) => this.handleViewClick(args),
                    },
                    {
                        text: "Delete",
                        onClick: (args) => this.dp.events.remove(args.source),
                        hidden: true
                    }
                ],
                onShow: function (args) {
                    const e = args.source;   // the event being right-clicked
                    const data = e.data;

                    // hide or show the Delete item based on status
                    const deleteItem = this.items.find(item => item.text === "Delete");
                    if (data.status === "New") {
                        deleteItem.hidden = false;  // show for new events
                    } else {
                        deleteItem.hidden = true;   // hide for others
                    }
                }
            }),
            onBeforeCellRender: (args) => this.handleBeforeCellRender(args),
            onTimeRangeSelected: (args) => this.handleTimeRangeSelected(args),
            onBeforeEventRender: (args) => this.handleBeforeEventRender(args),
            onEventResize: (args) => this.handleEventResize(args),
            onEventMove: (args) => this.handleEventMove(args),
            onEventClick: (args) => this.handleEventClick(args),
            onEventResized: (args) => this.handleEventChanged(args),
        });
        this.dp.init();
        this.loadSchedulerData();
    }
    // inside GratuityDistributions class
    isOverlapInResource(resourceId, newStart, newEnd, ignoreEventId = null) {
        // normalize to moments for comparison
        const newStartM = moment(newStart.toString());
        const newEndM = moment(newEnd.toString());

        // iterate existing events in scheduler
        for (const ev of this.dp.events.list) {
            const evRes = String(ev.resource);
            if (evRes !== String(resourceId)) {
                continue; // different resource/month, skip
            }

            // skip the event being moved/edited (if provided)
            const evId = (typeof ev.id === 'function') ? ev.id() : ev.id;
            if (ignoreEventId && String(evId) === String(ignoreEventId)) {
                continue;
            }

            const evStartM = moment(ev.start.toString());
            const evEndM = moment(ev.end.toString());

            // overlap condition: not (newEnd <= evStart || newStart >= evEnd)
            if (!(newEndM.isSameOrBefore(evStartM) || newStartM.isSameOrAfter(evEndM))) {
                return true;
            }
        }

        return false;
    }

    normalizeEvent(month, dayStart, dayEnd, text, color, status, payrollScheduleId = '', payrollDate = '') {
        // Clamp end to valid days in month
        const daysInMonth = moment(`${this.currentYear}-${month}`, "YYYY-MM").daysInMonth();
        if (dayEnd > daysInMonth) {
            dayEnd = daysInMonth;
        }
        return {
            id: DayPilot.guid(),
            start: `${this.currentYear}-01-${String(dayStart).padStart(2, "0")}`,
            end: `${this.currentYear}-01-${String(dayEnd + 1).padStart(2, "0")}`, // DayPilot end exclusive
            resource: String(month),  // row = actual month
            text,
            backColor: color,
            fontColor: "#fff",
            status,
            payrollDate,
            payrollScheduleId
        };
    }

    loadSchedulerData() {
        var events = [];
        var distributionSchedules = this.distributionSchedules;
        if (distributionSchedules.length > 0) {
            distributionSchedules.forEach((schedule, index) => {
                // Pick color based on status
                let color = "#6c757d"; // default gray
                if (schedule.status === "Paid") {
                    color = "#28a745"; // green
                } else if (schedule.status === "Schedule") {
                    color = "#fd7e14"; // orange
                }
                // ✅ Parse start/end into month & day
                let start = moment(schedule.start_date);
                let end = moment(schedule.end_date);

                let month = start.month() + 1; // months are 0-based in moment.js
                let dayStart = start.date();
                let dayEnd = end.date();

                var text = `${moment(schedule.start_date).format("DD MMM YYYY")} - ${moment(schedule.end_date).format("DD MMM YYYY")}`;

                // ✅ Push normalized event
                events.push(
                    this.normalizeEvent(
                        month,
                        dayStart,
                        dayEnd,
                        text, // text
                        color,
                        schedule.status,
                        schedule.id,
                        schedule.payroll_date
                    )
                );

            });
        }
        var resources = this.resources;
        this.dp.update({
            resources,
            events
        });
    }
    /* ---------------- Handlers ---------------- */
    handleBeforeCellRender(args) {
        const month = parseInt(args.cell.resource);
        const maxDays = this.resources.find(r => r.id === String(month)).days;
        const day = args.cell.start.getDay(); // 1–31

        if (day > maxDays) {
            args.cell.properties.disabled = true;
            args.cell.properties.backColor = "#eee"; // greyed out
            args.cell.properties.html = ""; // blank cell
        }
        const todayDay = parseInt(this.todayDate.split("-")[2], 10);
        let  currentMonth = this.currentMonth;
        // // Highlight today's cell background
        if (month == currentMonth && day == todayDay) {
            args.cell.properties.backColor = "#f2dc11ff"; // soft yellow
        }
    }
    handleBeforeEventRender(args) {
        args.data.cssClass = "pending-event";
        const text = DayPilot.Util.escapeHtml(args.data.text);
        var label = 'New'
        if (args.data.status == 'Schedule') {
            label = 'Pending';
        } else if (args.data.status == 'Paid') {
            label = 'Paid';
        }
        const payrollDate = (args.data.payrollDate != '')
            ? DayPilot.Util.escapeHtml(args.data.payrollDate)
            : "";
        var formattedPayrollDate = (payrollDate != '') ? moment(payrollDate).format("MMM DD,YYYY") : "";
        // overwrite the default label
        args.data.text = "";
        args.data.html = `<div style="position:relative; width:100%; height:100%; box-sizing:border-box; padding:2px 6px;">
        <div style="text-align:left; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
          ${text}
        </div>
        <div style="
          position:absolute;
          top:2px;
          right:4px;
          font-size:11px;
          font-weight:bold;
          color:#fff;
          background:#3c7fe7;
          padding:1px 4px;
          border-radius:3px;
          text-align:center;
        ">
          ${label}
          <div style="font-size:10px; font-weight:normal; margin-top:2px;">
            ${formattedPayrollDate}
          </div>
        </div>
      </div>
    `;
    }
    handleEventResize(args) {
        const status = args.e.data.status;
        if (status === "Schedule" || status === "Paid") {
            args.preventDefault();
            swal("Not Allowed", "You cannot modify schedule with status Schedule or Paid.", "warning");
            return;
        }
    }
    handleEventMove(args) {
        let sourceResource = null;
        if (args.e && args.e.data && args.e.data.resource !== undefined) {
            sourceResource = args.e.data.resource;
        } else if (args.e && typeof args.e.resource === "function") {
            sourceResource = args.e.resource();
            // older API
        } else if (args.e && args.e.resource !== undefined) {
            sourceResource = args.e.resource; // some builds
        }
        let targetResource = args.newResource; // id of row currently under the shadow 

        if (String(sourceResource) !== String(targetResource)) {
            args.preventDefault();
        }
        const status = args.e.data.status;
        if (status === "Schedule" || status === "Paid") {
            args.preventDefault();
            swal("Not Allowed", "You cannot modify schedule with status Schedule or Paid.", "warning");
            return;
        }
        const event = args.e;
        sourceResource = event && event.data && event.data.resource !== undefined ? event.data.resource : (event.resource ? event.resource() : null);
        targetResource = args.newResource || args.resource || sourceResource;
        const eventId = (typeof event.id === 'function') ? event.id() : (event.data && event.data.id);

        const newStart = args.newStart || args.start;
        const newEnd = args.newEnd || args.end;

        if (this.isOverlapInResource(targetResource, newStart, newEnd, eventId)) {
            // block the move
            if (typeof args.preventDefault === 'function') {
                args.preventDefault();
            } else {
                args.allowed = false;
            }
            swal('Overlap', 'Cannot move schedule here — it would overlap another schedule.', 'warning');
        }

        const data = event.data;
        const month = parseInt(data.resource);
        const start = newStart.toString("yyyy-MM-dd");
        const end = newEnd.toString("yyyy-MM-dd");
        this.payrollDate = '';
        this.payrollScheduleId = '';
        this.setStartAndEndDate(start, end, month);
        event.data.text = `${moment(this.startDate).format("DD MMM YYYY")} → ${moment(this.endDate).format("DD MMM YYYY")}`;
        this.dp.events.update(event);
        this.fetchDistributions();
    }

    async handleTimeRangeSelected(args) {
        this.dp.clearSelection();

        const month = parseInt(args.resource);   // Y-axis month
        const startDay = parseInt(args.start.toString("d"));
        let endDay = parseInt(args.end.toString("d"));

        const maxDays = this.resources.find(r => r.id === String(month)).days;
        if (startDay > maxDays) return;

        // If jumped into next month, clamp to last day of current month
        if (args.start.toString("yyyy-MM") !== args.end.toString("yyyy-MM")) {
            endDay = maxDays + 1;   // +1 because DayPilot end is exclusive
        }

        // --- real inclusive end day ---
        let inclusiveEndDay = endDay - 1; // because DayPilot end is exclusive
        if (inclusiveEndDay > maxDays) {
            inclusiveEndDay = maxDays;
        }

        // --- actual DB dates (with real month) ---
        const start = new DayPilot.Date(
            `${this.currentYear}-${String(month).padStart(2, "0")}-${String(startDay).padStart(2, "0")}`
        );
        const inclusiveEnd = new DayPilot.Date(
            `${this.currentYear}-${String(month).padStart(2, "0")}-${String(inclusiveEndDay).padStart(2, "0")}`
        );

        // --- exclusive end for DB ---
        const exclusiveEnd = new DayPilot.Date(
            `${this.currentYear}-${String(month).padStart(2, "0")}-${String(endDay).padStart(2, "0")}`
        );

        // --- display dates (always in January / fake month "01") ---
        const displayStart = new DayPilot.Date(
            `${this.currentYear}-01-${String(startDay).padStart(2, "0")}`
        );
        const displayEnd = new DayPilot.Date(
            `${this.currentYear}-01-${String(inclusiveEndDay + 1).padStart(2, "0")}`
        );

        // Overlap check (compare with fake display dates)
        if (this.isOverlapInResource(month, displayStart, new DayPilot.Date(`${this.currentYear}-01-${String(inclusiveEndDay).padStart(2, "0")}`))) {
            swal('Overlap', 'Selected dates overlap with an existing schedule. Please choose another slot.', 'warning');
            return;
        }

        // --- save inclusive dates for DB ---
        this.startDate = start.toString("yyyy-MM-dd");
        this.endDate = inclusiveEnd.toString("yyyy-MM-dd");
        this.payrollDate = '';
        this.payrollScheduleId = '';
        // --- add event to scheduler (with January hack) ---
        this.dp.events.add({
            start: displayStart,
            end: displayEnd, // exclusive
            id: DayPilot.guid(),
            resource: String(month),
            text: `${moment(this.startDate).format("DD MMM YYYY")} - ${moment(this.endDate).format("DD MMM YYYY")}`,
            backColor: "#edae45",
            fontColor: "#fff",
            status: "New",
            payrollDate: '',
            payrollScheduleId: ''
        });

        this.fetchDistributions();
    }

    async handleViewClick(args) {
        const source = args.source;
        const data = source.data;
        const month = parseInt(data.resource); // Y-axis month
        const start = data.start.toString("yyyy-MM-dd");
        const end = data.end.toString("yyyy-MM-dd");
        this.setStartAndEndDate(start, end, month);
        this.fetchDistributions();
    }
    async handleEventClick(args) {
        const event = args.e;
        const data = event.data;
        const month = parseInt(data.resource); // Y-axis month
        const start = data.start.toString("yyyy-MM-dd");
        const end = data.end.toString("yyyy-MM-dd");
        const payrollDate = data.payrollDate;
        const payrollScheduleId = data.payrollScheduleId;
        this.payrollScheduleId = payrollScheduleId;
        this.payrollDate = (payrollDate != '') ? moment(payrollDate).format("MMM DD,YYYY") : '';
        this.setStartAndEndDate(start, end, month);
        this.fetchDistributions();
    }
    async handleEventChanged(args) {
        const event = args.e;
        const data = event.data;
        const status = data.status;
        const month = parseInt(data.resource);
        const start = data.start.toString("yyyy-MM-dd");
        const end = data.end.toString("yyyy-MM-dd");
        this.payrollDate = '';
        this.payrollScheduleId = '';
        this.setStartAndEndDate(start, end, month);
        event.data.text = `${moment(this.startDate).format("DD MMM YYYY")} → ${moment(this.endDate).format("DD MMM YYYY")}`;
        this.dp.events.update(event);
        this.fetchDistributions();
    }

    setStartAndEndDate(start, end, month) {
        // Extract day numbers from those ISO strings
        const startDay = parseInt(start.split("-")[2], 10);
        const endDay = parseInt(end.split("-")[2], 10);
        this.startDate = `${this.currentYear}-${String(month).padStart(2, "0")}-${String(startDay).padStart(2, "0")}`;
        this.endDate = `${this.currentYear}-${String(month).padStart(2, "0")}-${String(endDay - 1).padStart(2, "0")}`;
    }
    updateDistribtutionAmounts(evt) {
        const $slider = $(evt.currentTarget);
        // Update label text and position
        this.updateSliderLabel($slider);

        const $row = $slider.closest('tr');
        const fullId = $row.data('id');
        const bookingId = fullId.split('_')[0];
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
        //var packageId = $('#tour-package-filter').val();
        //var guideId = $('#guide-filter').val();
        //var slotId = $('#slot-filter').val();
        const fromDate = this.startDate;
        const toDate = this.endDate;
        const payrollScheduleId = this.payrollScheduleId;
        if (!fromDate || !toDate) {
            swal('Invalid Input', 'Please select a tour package and a date.', 'warning');
            return;
        }

        showLoading();
        $.ajax({
            url: this.fetchDistributionsUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                //packageId: packageId,
                //guideId: guideId,
                //slotId: slotId,
                fromDate: fromDate,
                toDate: toDate,
                payrollScheduleId: payrollScheduleId
                //searchTerm: search,
            }),
            success: this.renderDistributions.bind(this),
        }).always(hideLoading)
            .fail(function (jqXHR) {
                hideLoading();
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
                                            <h5 class="mb-0 fw-bold">`;
            if (distribution.time_from != 'Direct Tips') {
                html += `${formatTime(distribution.time_from)} - ${formatTime(distribution.time_to)}`;
            } else {
                html += `Direct Tips`;
            }
            html += `</h5></div>
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
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted text-center">Total</th>
                                            <th class="py-2 px-3 text-uppercase small fw-medium text-muted text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>`;

            distribution.bookings.forEach(booking => {
                var guides = booking.guides || [];
                html += this.renderBookingDistribution(booking, distribution, guides);
                if (booking.distribution_status == null) {
                    if (booking.booking_id != '') {
                        this.bookings.push(booking.booking_id);
                    } else {
                        this.directTips.push(booking.gratuity_id);
                    }
                } else if (booking.distribution_status == 'Pending') {

                }
                if (booking.distribution_status == 'Ready') {
                    if (booking.booking_id != '') {
                        this.readyBookings.push(booking.booking_id);
                    } else {
                        this.readyDirectTips.push(booking.gratuity_id);
                    }
                }
            });
            html += `</tbody>
                                </table>
                            </div>
                            </div>
                        </div>
                    </div>`;
        });
        if (distributions.length == 0) {
            $('.ready-all-btn').addClass('d-none');
            $('#btn-export').addClass('d-none');
            $('#ready-schedule-btn').attr('disabled', true);
        } else {
            if (data.totalPendingBookingsCount == 0) {
                $('.ready-all-btn').addClass('d-none');
            } else {
                $('.ready-all-btn').removeClass('d-none');
            }
            $('#btn-export').removeClass('d-none');
        }
        this.totalPendingBookingsCount = data.totalPendingBookingsCount;
        this.totalReadyBookingsCount = data.totalReadyBookingsCount;
        this.totalCompletedBookingsCount = data.totalCompletedBookingsCount;
        $('#dateRange').text(`${moment(this.startDate).format('MMM D, YYYY')} - ${moment(this.endDate).format('MMM D, YYYY')}`);
        $('#distributionsListDiv').removeClass('d-none');
        $('#bookings-container').html(html);
        if (this.totalPendingBookingsCount == 0 && this.totalReadyBookingsCount == 0 && this.totalCompletedBookingsCount > 0) {
            $('#scheduleDiv').addClass('d-none');
            $('#scheduleCompletedDiv').removeClass('d-none');
            $('.ready-all-btn').addClass('d-none');
        } else if (this.totalPendingBookingsCount > 0) {
            $('.ready-all-btn').removeClass('d-none');
            $('#scheduleDiv').removeClass('d-none');
            $('#scheduleCompletedDiv').addClass('d-none');
            $('#btn-export').addClass('d-none');
        }

        if (this.payrollDate != '') {
            $('#payrollScheduleDate').text(this.payrollDate);
        } else {
            $('#payrollScheduleDate').text('Not Scheduled');
        }
        if (data.totalPaidDistributionsCount > 0) {
            $('#payrollScheduleDate').text('Paid on ' + moment(this.payrollDate).format("MMM DD, YYYY"));
        }

        $('#totalBookingsCount').text(data.totalBookingsCount);
        $('#totalPendingBookingsCount').text(data.totalPendingBookingsCount);
        $('#totalReadyBookingsCount').text(data.totalReadyBookingsCount);
        $('#totalCompletedBookingsCount').text(this.totalCompletedBookingsCount);
        $('#totalGratuityAmount').text(`${currencyFormation.symbol + data.totalGratuity.toFixed(2)}`);
        this.updateReadyScheduleButtonState();
        if (slots.length > 0) {
            var slotFilter = $('#slot-filter');
            slotFilter.empty();
            slotFilter.append('<option value="0">Show All Time</option>');
            slots.forEach(slot => {
                slotFilter.append(`<option value="${slot.tour_slot_id}">${formatTime(slot.time_from)} - ${formatTime(slot.time_to)}</option>`);
            });
        }
        const target = document.getElementById("distributionsListDiv");
        if (target) {
            target.scrollIntoView({ behavior: "smooth", block: "start" });
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
            } else if (booking.booking_id == '') {
                guideHtml += `<div class="ps-4">
                                <div class="text-muted small">-</div>
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
                                                        <div class="text-muted d-none" style="font-size: 11px;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2 px-3 text-center">
                                                <div class="fw-semibold amount d-inline-flex align-items-center position-relative" data-total="${booking.total_gratuity}">
                                                <span class="amount-value">${currencyFormation.symbol + guide.distribution_amount}</span>`;
            if (guides.length > 1 && booking.distribution_status != 'Schedule' && booking.distribution_status != 'Paid') {
                guideHtml += `<i class="fas fa-edit ms-2 text-primary edit-amount" style="cursor: pointer; font-size: 12px;"></i>
       <div class="edit-wrapper d-none ms-2"  style="display: inline-flex;">
            <input type="text" class="form-control amount-input me-1" style="width: 90px; height: 28px; font-size: 14px; padding: 2px 6px;">
            <button type="button" class="btn btn-success btn-sm btn-done me-1" style="padding: 2px 8px;">✔</button>
            <button type="button" class="btn btn-danger btn-sm btn-cancel" style="padding: 2px 8px;">✖</button>
        </div>
                                               `;
            }
            guideHtml += `</div>`;
            if (index === 0 && guides.length > 1 && booking.distribution_status != 'Schedule' && booking.distribution_status != 'Paid') {
                guideHtml += `<div class="slider-wrapper position-relative">
                                                <input type="range" class="form-range custom-slider" min="0" max="100" step="1" value="${guide.expected_share}">
                                                <span class="slider-label">${parseFloat(guide.expected_share)}%</span>
                                            </div>`;
            }
            guideHtml += `</td>`;
            guideHtml += `<td class="py-2 px-3 text-center">`;
            if (index > 0) {
                guideHtml += `<div class="text-muted small">—</div>`;
            } else {
                guideHtml += `<div class="fw-bold">${currencyFormation.symbol + booking.total_gratuity}</div>`;
            }
            guideHtml += `</td>
            <td class="py-2 px-3 text-center">`;

            if (index === 0) {
                if (booking.distribution_status == 'Ready') {
                    guideHtml += `<button class="badge bg-success border border-success border-opacity-25 mt-1">
                                    <i class="fas fa-check me-1" style="font-size: 10px;"></i>
                                    Ready
                                </button>`;
                } else if (booking.distribution_status == 'Schedule' || booking.distribution_status == 'Paid') {
                    guideHtml += `<button class="badge bg-success border border-success border-opacity-25 mt-1">
                                    <i class="fas fa-check me-1" style="font-size: 10px;"></i>
                                    Completed
                                </button>`;
                } else {
                    guideHtml += `<button class="badge bg-warning border border-warning border-opacity-25 mt-1 pending-btn" data-id="${(booking.booking_id != '') ? booking.booking_id : booking.gratuity_id}" data-type="${(booking.booking_id != '') ? 'booking' : 'direct'}">
                                    <i class="fas fa-clock me-1" style="font-size: 10px;"></i>
                                    Pending
                                </button>`;
                }
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
        const fromDate = this.startDate;
        const toDate = this.endDate;
        const payrollScheduleId = this.payrollScheduleId;

        if (!fromDate || !toDate) {
            swal('Invalid Input', 'Please select a date range.', 'warning');
            return;
        }

        var exportDistributionsUrl = this.exportDistributionsUrl;
        exportDistributionsUrl = exportDistributionsUrl.replace(':fromDate', fromDate)
            .replace(':toDate', toDate)
            .replace(':payrollScheduleId', payrollScheduleId);

        window.location = exportDistributionsUrl;

    }
    processAcceptTip(evt) {
        const $button = $(evt.currentTarget);
        const bookingId = $button.data('id');
        const type = $button.data('type'); // booking or direct
        var processAcceptTipsUrl = this.processAcceptTipsUrl;
        showLoading();
        $.ajax({
            url: processAcceptTipsUrl,
            method: 'POST',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                bookingId: bookingId,
                type: type
            }),
            contentType: 'application/json',
            success: (response) => this.renderAcceptedTip($button, response),
        })
            .always(hideLoading)
            .fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            });

    }
    renderAcceptedTip($button, response) {
        $button.replaceWith(`<button class="badge bg-success border border-success border-opacity-25 mt-1">
                                    <i class="fas fa-check me-1" style="font-size: 10px;"></i>
                                    Ready
                                </button>`);
        if (this.totalPendingBookingsCount > 0) {
            this.totalPendingBookingsCount -= 1;
            this.totalReadyBookingsCount += 1;
        }
        $('#totalPendingBookingsCount').text(this.totalPendingBookingsCount);
        $('#totalReadyBookingsCount').text(this.totalReadyBookingsCount);
        if (this.totalPendingBookingsCount == 0) {
            $('.ready-all-btn').addClass('d-none');
        } else {
            $('.ready-all-btn').removeClass('d-none');
        }
        this.updateReadyScheduleButtonState();
    }
    processBulkAcceptTips() {
        let bookings = this.bookings;
        let directTips = this.directTips;

        if (bookings.length === 0 && directTips.length === 0) {
            swal('No Pending Tips', 'There are no pending tips to accept.', 'info');
            return;
        }

        var processBulkAcceptTipsUrl = this.processBulkAcceptTipsUrl;
        swal({
            title: 'Confirm Accept Tips',
            text: 'Are you sure you want to accept these tips?',
            icon: 'warning',
            buttons: {
                cancel: 'Cancel',
                confirm: {
                    text: 'Accept Tips',
                    value: true,
                    className: 'btn btn-primary'
                }
            },
        }).then((willTransfer) => {
            if (willTransfer) {
                showLoading();
                $.ajax({
                    url: processBulkAcceptTipsUrl,
                    method: 'POST',
                    data: JSON.stringify({
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        bookings: bookings,
                        directTips: directTips
                    }),
                    contentType: 'application/json',
                    success: (response) => this.successAcceptedTips(response),
                })
                    .always(hideLoading)
                    .fail(function (jqXHR) {
                        hideLoading();
                        showSubmissionError(jqXHR);
                    });
            }
        });
    }
    successAcceptedTips() {
        swal('Success', 'Tips has been accepted successfully.', 'success');
        this.bookings = [];
        this.fetchDistributions();
    }
    processBulkCompleteTips(payrollDate, startDate, endDate) {
        let bookings = this.readyBookings;
        let directTips = this.readyDirectTips;

        if (bookings.length === 0 && directTips.length === 0) {
            swal('No Pending Tips', 'There are no tips to complete.', 'info');
            return;
        }

        var processBulkCompleteTipsUrl = this.processBulkCompleteTipsUrl;

        showLoading();
        $.ajax({
            url: processBulkCompleteTipsUrl,
            method: 'POST',
            data: JSON.stringify({
                _token: $('meta[name="csrf-token"]').attr('content'),
                bookings: bookings,
                payrollDate: payrollDate,
                startDate: startDate,
                endDate: endDate,
                directTips: directTips
            }),
            contentType: 'application/json',
            success: (response) => this.successCompletedTips(response, payrollDate),
        })
            .always(hideLoading)
            .fail(function (jqXHR) {
                hideLoading();
                showSubmissionError(jqXHR);
            });
    }
    successCompletedTips(data, payrollDate) {
        $('#payrollScheduleDate').text(moment(payrollDate).format("MMM DD, YYYY"));
        swal('Success', 'Tips has been completed successfully.', 'success');
        this.readyBookings = [];
        this.fetchDistributions();
    }
    updateReadyScheduleButtonState() {
        if (this.totalPendingBookingsCount > 0) {
            $('#ready-schedule-btn').attr('disabled', true);
        } else if (this.totalPendingBookingsCount == 0 && this.totalReadyBookingsCount > 0) {
            $('#ready-schedule-btn').attr('disabled', false);
        }
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
