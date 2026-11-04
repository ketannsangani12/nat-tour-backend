class SoftBlockBooking {
    constructor(props = {}) {
        this.props = props;
        this.previewSoftBlocksUrl = props.previewSoftBlocksUrl;
        this.createSoftBlocksUrl = props.createSoftBlocksUrl;
        this.fetchSoftBlocksUrl = props.fetchSoftBlocksUrl;
        this.fetchTimeSlotsUrl = props.fetchTimeSlotsUrl;
        this.bookingShowUrl = props.bookingShowUrl;
        this.slotCount = 1;
        this.modifiedSeats = {};
        this.calendar = null;
        this.initSelect2();
        this.initDateRangePickers();
        this.initializeValidation();
        this.bindEvents();
        this.bindOverlapValidation();
        this.fetchTimeSlots(0);
        this.updateCheckmarks(0);
        this.initCalendar();
    }

    initSelect2() {
        $('.days-input').select2({
            placeholder: "Select days",
            allowClear: false,
            closeOnSelect: false
        }).val(['0']).trigger('change');
        $('.time-input').select2({
            placeholder: "Select times",
            allowClear: false,
            closeOnSelect: false
        });
        $('.affiliates').select2({
            placeholder: "All affiliates",
            allowClear: false,
            closeOnSelect: false
        });
    }

    initDateRangePickers() {
        this.initDateRangePicker(0);
    }

    initDateRangePicker(index) {
        const $input = $(`#time_slots_${index}_date_range`);
        const start = moment();
        const end = moment().add(7, 'days');

        const setStartAndEndDate = (startDate, endDate) => {
            $input.val(`${startDate.format('MMMM DD, YYYY')} / ${endDate.format('MMMM DD, YYYY')}`);
            this.validateInput($input);
            $input.trigger('change');

            this.fetchTimeSlots(index);
        };

        $input.daterangepicker({
            startDate: start,
            endDate: end,
            minDate: moment(),
            autoUpdateInput: false,
            ranges: {
                'Today': [moment(), moment()],
                'Next 7 Days': [moment(), moment().add(6, 'days')],
                'Next 30 Days': [moment(), moment().add(29, 'days')],
                'This Month': [moment().startOf('month'), moment().endOf('month')],
                'Next Month': [moment().add(1, 'month').startOf('month'), moment().add(1, 'month').endOf('month')]
            }
        }, setStartAndEndDate);

        $input.on('apply.daterangepicker', (ev, picker) => {
            setStartAndEndDate(picker.startDate, picker.endDate);
        });

        setStartAndEndDate(start, end);
    }

    async fetchTimeSlots(slotIndex) {
        const $timeInput = $(`#time_slots_${slotIndex}_times`);
        const $dateRangeInput = $(`#time_slots_${slotIndex}_date_range`);
        const packageId = $('#package-id').val();
        const affiliateId = $('#affiliate-id').val();
        const dateRange = $dateRangeInput.val();

        if (!packageId || !affiliateId || !dateRange) {
            $timeInput.html('<option value="" disabled selected>Select a package and date range first</option>');
            return;
        }

        try {
            const response = await $.ajax({
                url: this.fetchTimeSlotsUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    packageId,
                    affiliateId,
                    dateRange
                })
            });

            $timeInput.empty();
            if (response.times && response.times.length > 0) {
                response.times.forEach(time => {
                    $timeInput.append(`<option value="${time}">${time}</option>`);
                });
            } else {
                $timeInput.append('<option value="" disabled selected>No times available</option>');
            }
            $timeInput.trigger('change');
        } catch (error) {
            console.error('Error fetching time slots:', error);
            swal('Error', 'Failed to load time slots. Please try again.', 'error');
        }
    }

    updateCheckmarks(slotIndex) {
        const $timeInput = $(`#time_slots_${slotIndex}_times`);
        const $daysInput = $(`#time_slots_${slotIndex}_days`);
        const $seatPreference = $(`#time_slots_${slotIndex}_seat_preference`);
        const $seatsInput = $(`#time_slots_${slotIndex}_seats`);
        const $timeSelect2 = $timeInput.next('.select2-container').find('.select2-selection--multiple');
        const $daysSelect2 = $daysInput.next('.select2-container').find('.select2-selection--multiple');

        const timeIsValid = $timeInput.val() && $timeInput.val().length > 0;
        const daysIsValid = $daysInput.val() && $daysInput.val().length > 0;
        $timeSelect2.toggleClass('has-selection', timeIsValid && !$timeInput.hasClass('is-invalid'));
        $daysSelect2.toggleClass('has-selection', daysIsValid && !$daysInput.hasClass('is-invalid'));

        $seatPreference.on('change', () => {
            if ($seatPreference.val() === '1') {
                $seatsInput.removeClass('is-valid').addClass('is-invalid');
                $seatsInput.siblings('.invalid-feedback').text('Seats will be adjusted based on availability.');
                $seatsInput.siblings('.invalid-feedback').show();
            } else {
                this.validateInput($seatsInput);
            }
        });
    }

    initializeValidation() {
        $('#package-id, #affiliate-id, #is-reserved-for-affiliate, .seat-preference-input').each((_, el) => {
            this.validateDropdown($(el));
        });
        $('.date-range-input, .seats-input').each((_, el) => {
            this.validateInput($(el));
        });
        $('.time-input').each((_, el) => {
            this.validateTimeInput($(el));
        });
        $('.days-input').each((_, el) => {
            this.validateDropdown($(el));
        });
    }

    bindEvents() {
        $('#add-slot-btn').on('click', () => this.addTimeSlot());
        $(document).on('click', '.remove-slot-btn', (e) => this.removeTimeSlot(e));
        $(document).on('change', '#package-id, #affiliate-id, #is-reserved-for-affiliate, .seat-preference-input, .days-input', (e) => {
            const $input = $(e.target);
            this.validateDropdown($input);
            if ($input.hasClass('days-input')) {
                const slotIndex = $input.closest('.time-slot').data('slot-index');
                this.updateCheckmarks(slotIndex);
            }
        });
        $(document).on('input change', '.date-range-input, .seats-input', (e) => this.validateInput($(e.target)));
        $(document).on('change', '.time-input', (e) => {
            const $input = $(e.target);
            this.validateTimeInput($input);
            const slotIndex = $input.closest('.time-slot').data('slot-index');
            this.updateCheckmarks(slotIndex);
        });
        $('#soft-block-preview-button').on('click', () => this.handlePreview());
        $('#soft-block-create-button').on('click', () => this.handleCreateBookings());
        $('#soft-block-calendar-button').on('click', () => {
            const currentMonth = moment(this.calendar.getDate()).format('YYYY-MM');
            this.handleCalendar(currentMonth);
        });
        $('#affiliates').on('change', () => {
            const currentMonth = moment(this.calendar.getDate()).format('YYYY-MM');
            this.handleCalendar(currentMonth);
        });
    }

    bindOverlapValidation() {
        $(document).on('change', '.time-input, .date-range-input', (e) => {
            const $input = $(e.target);
            const slotIndex = $input.closest('.time-slot').data('slot-index');
            const $timeInput = $(`#time_slots_${slotIndex}_times`);
            const $dateRangeInput = $(`#time_slots_${slotIndex}_date_range`);
            const times = $timeInput.val() || [];
            const dateRange = $dateRangeInput.val();

            if (times.length > 0 && dateRange && this.validateTimeInput($timeInput) && this.validateInput($dateRangeInput)) {
                const overlapCheck = this.checkForDateRangeOverlap(times, dateRange, slotIndex);
                if (overlapCheck.hasOverlap) {
                    $dateRangeInput.removeClass('is-valid').addClass('is-invalid');
                    $dateRangeInput.siblings('.invalid-feedback').text(`Date range overlap with another slot (${overlapCheck.conflictingRange}) for the same time(s).`);
                    $dateRangeInput.siblings('.invalid-feedback').show();
                } else {
                    $dateRangeInput.removeClass('is-invalid').addClass('is-valid');
                    $dateRangeInput.siblings('.invalid-feedback').text('Please select a valid date range.');
                    $dateRangeInput.siblings('.invalid-feedback').hide();
                }
            }
        });
    }

    validateDropdown($element) {
        if ($element.is('select')) {
            if ($element.hasClass('days-input')) {
                $element.removeClass('is-invalid').addClass('is-valid');
                return true;
            }

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
        if ($element.hasClass('seats-input')) {
            const value = parseInt($element.val());
            const isValid = !isNaN(value) && value > 0;
            if (isValid) {
                $element.removeClass('is-invalid').addClass('is-valid');
                $element.siblings('.invalid-feedback').hide();
            } else {
                $element.removeClass('is-valid').addClass('is-invalid');
                $element.siblings('.invalid-feedback').show();
            }
            return isValid;
        } else if ($element.hasClass('date-range-input')) {
            const isValid = !!$element.val();
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

    validateTimeInput($element) {
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

    checkForDateRangeOverlap(times, newDateRange, currentIndex) {
        const [newStartDateStr, newEndDateStr] = newDateRange.split(' / ');
        const newStartDate = moment(newStartDateStr, 'MMMM DD, YYYY');
        const newEndDate = moment(newEndDateStr, 'MMMM DD, YYYY');
        const newDays = $(`#time_slots_${currentIndex}_days`).val() || [];
        const newFilteredDays = newDays.filter(day => day !== '0');

        const existingSlots = $('.time-slot').filter(function () {
            return $(this).data('slot-index') !== currentIndex;
        });

        for (let i = 0; i < existingSlots.length; i++) {
            const $slot = $(existingSlots[i]);
            const slotTimes = $slot.find('.time-input').val() || [];
            const slotDateRange = $slot.find('.date-range-input').val();
            const slotDays = $slot.find('.days-input').val() || [];
            const slotFilteredDays = slotDays.filter(day => day !== '0');

            if (slotTimes.some(slotTime => times.includes(slotTime)) && slotDateRange) {
                const [existingStartDateStr, existingEndDateStr] = slotDateRange.split(' / ');
                const existingStartDate = moment(existingStartDateStr, 'MMMM DD, YYYY');
                const existingEndDate = moment(existingEndDateStr, 'MMMM DD, YYYY');

                if (newStartDate.isSameOrBefore(existingEndDate) && newEndDate.isSameOrAfter(existingStartDate)) {
                    if (newFilteredDays.length > 0 && slotFilteredDays.length > 0) {
                        const commonDays = newFilteredDays.filter(day => slotFilteredDays.includes(day));
                        if (commonDays.length > 0) {
                            const dayNames = commonDays.map(day => {
                                const daysMap = { '1': 'Monday', '2': 'Tuesday', '3': 'Wednesday', '4': 'Thursday', '5': 'Friday', '6': 'Saturday', '7': 'Sunday' };
                                return daysMap[day];
                            }).join(', ');
                            return {
                                hasOverlap: true,
                                conflictingRange: `${slotDateRange} on ${dayNames}`
                            };
                        }
                    } else {
                        return {
                            hasOverlap: true,
                            conflictingRange: slotDateRange
                        };
                    }
                }
            }
        }
        return { hasOverlap: false };
    }

    getNextSlotIndex() {
        const existingIndices = $('.time-slot').map((_, el) => parseInt($(el).data('slot-index'))).get();
        return existingIndices.length > 0 ? Math.max(...existingIndices) + 1 : 0;
    }

    addTimeSlot() {
        const index = this.getNextSlotIndex();
        const slotHtml = `
            <div class="flex flex-wrap -mx-3 items-start time-slot mb-2" data-slot-index="${index}">
                <div class="w-full sm:w-1/2 lg:w-1/4 max-w-full px-3">
                    <label for="time_slots_${index}_date_range" class="form-label form-custom-label">Date Range <req>*</req></label>
                    <input type="text" name="time_slots[${index}][date_range]" id="time_slots_${index}_date_range" class="form-control  form-custom-input date-range-input" placeholder="Select date range" required aria-label="Date range for slot ${index + 1}">
                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select a valid date range.</div>
                </div>
                <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                    <label for="time_slots_${index}_times" class="form-label form-custom-label">Times <req>*</req></label>
                    <select class="form-select time-input time_slots_times form-custom-input" name="time_slots[${index}][times][]" id="time_slots_${index}_times" multiple required aria-label="Times for slot ${index + 1}">
                        <option value="" disabled selected>Loading times...</option>
                    </select>
                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select at least one time.</div>
                </div>
                <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                    <label for="time_slots_${index}_seats" class="form-label form-custom-label">Seats <req>*</req></label>
                    <input type="number" name="time_slots[${index}][seats]" id="time_slots_${index}_seats" class="form-control form-custom-input seats-input" placeholder="Number of seats" required aria-label="Number of seats for slot ${index + 1}">
                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please enter a positive number.</div>
                </div>
                <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                    <label for="time_slots_${index}_seat_preference" class="form-label form-custom-label">Seats Preference <req>*</req></label>
                    <select class="form-select seat-preference-input form-custom-input" name="time_slots[${index}][seat_preference]" id="time_slots_${index}_seat_preference" required aria-label="Seats preference for slot ${index + 1}">
                        <option value="1" selected>Available</option>
                        <option value="0">Fixed</option>
                    </select>
                    <div class="invalid-feedback mt-2 text-xs text-[#FF0000]">Please select an option.</div>
                </div>
                <div class="w-full sm:w-1/2 lg:w-1/6 max-w-full px-3">
                    <label for="time_slots_${index}_days" class="form-label form-custom-label">Days</label>
                    <select class="form-select days-input custom-select2 time_slots_seat_preference" name="time_slots[${index}][days][]" id="time_slots_${index}_days" multiple aria-label="Days for slot ${index + 1}">
                        <option value="0" selected>All Days</option>
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="7">Sunday</option>
                    </select>
                </div>
                <div class="w-full sm:w-1/2 lg:w-1/12 max-w-full px-3">
                    <label class="form-label form-custom-label">&nbsp;</label>
                    <button type="button" class="btn btn-danger remove-slot-btn btn-danger-small">Remove</button>
                </div>
            </div>
        `;

        $('#time-slots-container').append(slotHtml);

        $(`#time_slots_${index}_days`).select2({
            placeholder: "Select days",
            allowClear: false,
            closeOnSelect: false
        }).val(['0']).trigger('change');

        $(`#time_slots_${index}_times`).select2({
            placeholder: "Select times",
            allowClear: false,
            closeOnSelect: false
        });

        this.initDateRangePicker(index);
        this.fetchTimeSlots(index);

        this.validateDropdown($(`#time_slots_${index}_seat_preference`));
        this.validateInput($(`#time_slots_${index}_date_range`));
        this.validateInput($(`#time_slots_${index}_seats`));
        this.validateTimeInput($(`#time_slots_${index}_times`));
        this.updateCheckmarks(index);

        this.slotCount++;
        $('.remove-slot-btn').prop('disabled', this.slotCount === 1);
    }

    removeTimeSlot(e) {
        if (this.slotCount <= 1) return;
        const $slot = $(e.target).closest('.time-slot');
        const index = $slot.data('slot-index');

        $(`#time_slots_${index}_days`).select2('destroy');
        $(`#time_slots_${index}_times`).select2('destroy');
        $(`#time_slots_${index}_date_range`).data('daterangepicker').remove();

        $slot.remove();
        this.slotCount--;
        $('.remove-slot-btn').prop('disabled', this.slotCount === 1);
    }

    validateAndCollectData() {
        const $form = $('#soft-block-form');
        $form.addClass('was-validated');

        let isValid = true;

        $('#package-id, #affiliate-id, #is-reserved-for-affiliate, .seat-preference-input').each((_, el) => {
            if (!this.validateDropdown($(el))) {
                isValid = false;
            }
        });

        $('.seats-input').each((_, el) => {
            if (!this.validateInput($(el))) {
                isValid = false;
            }
        });

        $('.date-range-input').each((_, el) => {
            const $input = $(el);
            const slotIndex = $input.closest('.time-slot').data('slot-index');
            const times = $(`#time_slots_${slotIndex}_times`).val() || [];
            const dateRange = $input.val();

            if (!this.validateInput($input)) {
                isValid = false;
            } else if (times.length > 0 && dateRange) {
                const overlapCheck = this.checkForDateRangeOverlap(times, dateRange, slotIndex);
                if (overlapCheck.hasOverlap) {
                    $input.removeClass('is-valid').addClass('is-invalid');
                    $input.siblings('.invalid-feedback').text(`Date range overlaps with another slot (${overlapCheck.conflictingRange}) for the same time(s).`);
                    $input.siblings('.invalid-feedback').show();
                    isValid = false;
                } else {
                    $input.removeClass('is-invalid').addClass('is-valid');
                    $input.siblings('.invalid-feedback').text('Please select a valid date range.');
                    $input.siblings('.invalid-feedback').hide();
                }
            } else {
                isValid = false;
            }
        });

        $('.time-input').each((_, el) => {
            if (!this.validateTimeInput($(el))) {
                isValid = false;
            }
        });

        if (!isValid) {
            return { isValid: false, data: null };
        }

        const slots = [];
        $('.time-slot').each((_, slotElement) => {
            const $slot = $(slotElement);
            const date_range = $slot.find('.date-range-input').val();
            const times = $slot.find('.time-input').val() || [];
            const seats = parseInt($slot.find('.seats-input').val()) || 1;
            const seat_preference = $slot.find('.seat-preference-input').val();
            let days = $slot.find('.days-input').val() || [];

            days = days.filter(day => day !== '0');

            const modified_times = {};
            const booking_ids = {};
            if (times.length > 0 && date_range) {
                const [startDateStr, endDateStr] = date_range.split(' / ');
                const startDate = moment(startDateStr, 'MMMM DD, YYYY');
                const endDate = moment(endDateStr, 'MMMM DD, YYYY');
                if (startDate.isValid() && endDate.isValid()) {
                    let currentDate = startDate.clone();
                    while (currentDate.isSameOrBefore(endDate)) {
                        const formattedDate = currentDate.format('MMMM DD, YYYY');
                        times.forEach(time => {
                            const key = `${time}_${formattedDate}`;
                            const $row = $(`.slot-availability-table tr[data-time="${time}"][data-date="${formattedDate}"]`);
                            if ($row.length) {
                                const bookingId = $row.data('booking-id');
                                if (bookingId) {
                                    booking_ids[key] = bookingId;
                                }
                                if (this.modifiedSeats.hasOwnProperty(key)) {
                                    modified_times[key] = this.modifiedSeats[key];
                                }
                            }
                        });
                        currentDate.add(1, 'day');
                    }
                }
            }

            slots.push({ date_range, times, seats, seat_preference, days, modified_times, booking_ids });
        });

        const data = {
            packageId: $('#package-id').val(),
            affiliateId: $('#affiliate-id').val(),
            timeSlots: slots,
            adminEmail: $('#admin-email').val(),
            isReservedForAffiliate: $('#is-reserved-for-affiliate').val(),
            updateExistingBookings: $('#update_existing_bookings').is(':checked')
        };

        return { isValid: true, data };
    }

    async handlePreview() {
        const { isValid, data } = this.validateAndCollectData();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        try {
            const response = await $.ajax({
                url: this.previewSoftBlocksUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: $('meta[name="csrf-token"]').attr('content'),
                    ...data
                })
            });

            this.renderSlotAvailability(response.availability, data);
        } catch (error) {
            console.error('Error fetching slot availability:', error);
            swal('Error', 'Failed to fetch slot availability. Please try again.', 'error');
        }
    }

    async handleCreateBookings() {
        const { isValid, data } = this.validateAndCollectData();

        if (!isValid) {
            swal('Invalid Fields', 'Please correct the invalid fields.', 'warning');
            return;
        }

        // Neither of these blocks the save -- createSoftBlocks() itself just
        // skips ineligible bookings and lets the rest of the batch go
        // through, so this is a heads-up before submit, not a hard stop.
        const ineligibleCount = $('.slot-availability-table [data-ineligible-for-mass-update="true"]').length;
        const $yellowRows = $('.slot-availability-table .table-warning');

        if (ineligibleCount > 0 || $yellowRows.length > 0) {
            const notices = [];
            if (ineligibleCount > 0) {
                notices.push(`${ineligibleCount} booking(s) already have a split, removed pax, and/or a payment, and will be skipped (edit those directly instead).`);
            }
            if ($yellowRows.length > 0) {
                notices.push('Some rows exceed available seats.');
            }
            notices.push('Continue with the rest?');

            return swal({
                title: 'Please Confirm',
                text: notices.join(' '),
                icon: 'warning',
                buttons: true,
                dangerMode: true,
            }).then((override) => {
                if (!override) return; // stop completely
                return this.confirmAndCreate(data);
            });
        }

        // Otherwise go directly to main save flow
        return this.confirmAndCreate(data);
    }

    async confirmAndCreate(data) {
        swal({
            title: 'Are you sure?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            buttons: true,
            dangerMode: true,
        }).then(async (willCreate) => {
            if (!willCreate) return;

            try {
                const response = await $.ajax({
                    url: this.createSoftBlocksUrl,
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        _token: $('meta[name="csrf-token"]').attr('content'),
                        ...data
                    })
                });

                const skippedCount = response?.warnings?.length || 0;
                if (skippedCount > 0) {
                    swal('Done', `Bookings processed. ${skippedCount} booking(s) were skipped (already split, removed pax, and/or already paid).`, 'warning');
                } else {
                    swal('Success', 'Bookings created successfully!', 'success');
                }
                $('#soft-block-form')[0].reset();
                $('#time-slots-container').empty();
                this.slotCount = 0;
                this.modifiedSeats = {};
                this.addTimeSlot();
                $('#slot-availability-accordion').empty();
                $('#bookings-container').empty();
            } catch (error) {
                console.error('Error creating bookings:', error);
                swal('Error', error.responseJSON?.error || 'Something went wrong', 'error');
            }
        });
    }

    initCalendar() {
        const calendarEl = document.getElementById('affiliate-bookings-calendar');
        if (!calendarEl) {
            console.error('Calendar element #affiliate-bookings-calendar not found.');
            swal('Error', 'Calendar container not found. Please check the page structure.', 'error');
            return;
        }

        this.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            initialDate: moment().format('YYYY-MM-DD'),
            headerToolbar: {
                left: 'today prev,next',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },
            height: 'auto',
            events: [],
            eventContent: (arg) => {
                const bookings = arg.event.extendedProps.bookings || [];
                const totalSeats = bookings.reduce((sum, b) => sum + (b.seats || 0), 0);

                const timeSlots = {};
                bookings.forEach(booking => {
                    if (booking.time) {
                        if (!timeSlots[booking.time]) {
                            timeSlots[booking.time] = { seats: 0, bookings: [] };
                        }
                        timeSlots[booking.time].seats += booking.seats || 0;
                        timeSlots[booking.time].bookings.push(booking);
                    }
                });

                const sortedTimes = Object.keys(timeSlots).sort();
                const maxInitialSlots = 3;
                const initialSlots = sortedTimes.slice(0, maxInitialSlots);
                const remainingSlots = sortedTimes.slice(maxInitialSlots);

                const timeSlotHtml = initialSlots.map(time => {
                    const { seats } = timeSlots[time];
                    return `
                        <div class="fc-time-slot" style="font-size: 12px; margin-top: 4px;">
                            <span>${time}</span>: ${seats} seat${seats === 1 ? '' : 's'}
                        </div>
                    `;
                }).join('');

                const showMoreHtml = remainingSlots.length > 0 ? `
                    <div class="fc-show-more" style="font-size: 12px; margin-top: 4px; cursor: pointer; color: #007bff;">
                        Show ${remainingSlots.length} more...
                    </div>
                ` : '';

                return {
                    html: `
                        <div class="fc-event-content" style="padding: 4px; font-size: 14px;">
                            <div class="fc-total-seats" style="font-weight: bold; margin-bottom: 4px;">
                                ${totalSeats} seat${totalSeats === 1 ? '' : 's'} booked
                            </div>
                            ${timeSlotHtml}
                            ${showMoreHtml}
                        </div>
                    `
                };
            },
            eventDidMount: (info) => {
                info.el.style.backgroundColor = 'darkorange';
                info.el.style.color = '#fff';

                const $showMore = $(info.el).find('.fc-show-more');
                if ($showMore.length) {
                    $showMore.on('click', () => {
                        this.showDayDetails(info.event);
                    });
                }
            },
            eventClick: (info) => this.showDayDetails(info.event),
            datesSet: (info) => {
                const currentMonth = moment(info.view.currentStart).format('YYYY-MM');
                this.handleCalendar(currentMonth);
            }
        });

        this.calendar.render();
        console.log('Calendar initialized and rendered.');
    }

    async fetchAndRenderBookings(month) {
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        const affiliateIds = $('#affiliates').val() || [];

        try {
            const response = await $.ajax({
                url: this.fetchSoftBlocksUrl,
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    _token: csrfToken,
                    affiliateId: affiliateIds,
                    month: month
                }),
                beforeSend: () => {
                    $('#affiliate-bookings-calendar').addClass('loading');
                },
                complete: () => {
                    $('#affiliate-bookings-calendar').removeClass('loading');
                }
            });

            console.log('Backend response:', response);

            if (!response.bookings || !Array.isArray(response.bookings)) {
                console.warn('Invalid or empty bookings in response:', response);
                this.calendar.setOption('events', []);
                swal('Info', 'No bookings found for the selected month.', 'info');
                return;
            }

            const events = this.processCalendarBookings(response.bookings);
            console.log('Processed events:', events);
            this.calendar.setOption('events', events);
        } catch (error) {
            console.error('Error fetching calendar bookings:', error);
            this.calendar.setOption('events', []);
            swal('Error', 'Failed to fetch bookings. Please check the console for details.', 'error');
        }
    }

    processCalendarBookings(bookings) {
        const events = [];
        const groupedByDate = {};

        bookings.forEach((booking, index) => {
            if (!booking.date || !moment(booking.date, 'MMMM DD, YYYY', true).isValid()) {
                console.warn(`Invalid date in booking at index ${index}:`, booking);
                return;
            }
            const date = booking.date;
            if (!groupedByDate[date]) {
                groupedByDate[date] = [];
            }
            groupedByDate[date].push(booking);
        });

        Object.keys(groupedByDate).forEach(date => {
            const bookings = groupedByDate[date];
            const totalSeats = bookings.reduce((sum, b) => sum + (b.seats || 0), 0);
            const event = {
                title: `${totalSeats} seat${totalSeats === 1 ? '' : 's'}`,
                start: moment(date, 'MMMM DD, YYYY').format('YYYY-MM-DD'),
                bookings: bookings,
                allDay: true
            };
            console.log('Created event for date:', date, event);
            events.push(event);
        });

        return events;
    }

    renderBookingDetailsModal(bookings, date) {
        const $accordion = $('#booking-details-accordion');
        $accordion.empty();

        if (!bookings || bookings.length === 0) {
            $accordion.html('<p class="text-muted">No bookings found for this date.</p>');
            return;
        }

        // Group bookings by affiliate
        const groupedByAffiliate = {};
        bookings.forEach(booking => {
            const affiliateId = booking.affiliate_id || 'unknown';
            const affiliateName = booking.affiliate_name || 'Unknown Affiliate';
            if (!groupedByAffiliate[affiliateId]) {
                groupedByAffiliate[affiliateId] = {
                    name: affiliateName,
                    bookings: []
                };
            }
            groupedByAffiliate[affiliateId].bookings.push(booking);
        });

        // Sort bookings by time in ascending order for each affiliate
        Object.keys(groupedByAffiliate).forEach(affiliateId => {
            groupedByAffiliate[affiliateId].bookings.sort((a, b) => {
                const timeA = a.time || '24:00:00';
                const timeB = b.time || '24:00:00';
                return timeA.localeCompare(timeB);
            });
        });

        // Generate accordion items for each affiliate
        const accordionHtml = Object.keys(groupedByAffiliate).map((affiliateId) => {
            const affiliate = groupedByAffiliate[affiliateId];
            const tableRows = affiliate.bookings.map(b => `
                <tr class="hover:bg-[#EFF6FF] transition-all duration-300 ease-in-out">
                    <td class="td-custom-css">${b.time || 'N/A'}</td>
                    <td class="td-custom-css">${b.seats || 0}</td>
                    <td class="td-custom-css"><a href="${this.bookingShowUrl.replace('BOOKING_ID_PLACEHOLDER', b.id || '')}" target="_blank">${b.id || 'N/A'}</a></td>
                    <td class="td-custom-css">${b.status || 'N/A'}</td>
                </tr>
            `).join('');

            return `
                <div class="accordion-item mb-4">
                    <h2 class="accordion-header" id="affiliate-heading-${affiliateId}">
                        <button class="accordion-button accordion-button-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#affiliate-collapse-${affiliateId}" aria-expanded="true" aria-controls="affiliate-collapse-${affiliateId}">
                            ${affiliate.name}
                            <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </button>
                    </h2>
                    <div id="affiliate-collapse-${affiliateId}" class="accordion-collapse collapse show" aria-labelledby="affiliate-heading-${affiliateId}" data-bs-parent="#booking-details-accordion">
                        <div class="accordion-body p-3 border border-[#E5E7EB] rounded-lg">
                            <table class="table table-striped w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th class="th-custom-css">Time</th>
                                        <th class="th-custom-css">Seats</th>
                                        <th class="th-custom-css">Booking ID</th>
                                        <th class="th-custom-css">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        $accordion.html(accordionHtml);

        // Update modal title and show it
        $('#bookingDetailsModalLabel').text(`Bookings on ${date}`);
        $('#bookingDetailsModal').modal('show');
    }

    showDayDetails(event) {
        const bookings = event.extendedProps.bookings || [];
        const date = moment(event.start).format('MMMM DD, YYYY');
        this.renderBookingDetailsModal(bookings, date);
    }

    handleCalendar(month) {
        $('#affiliate-bookings-calendar').show();
        this.fetchAndRenderBookings(month);
    }

    renderSlotAvailability(availability, data) {
        const $accordion = $('#slot-availability-accordion');
        $accordion.empty();

        if (!availability || Object.keys(availability).length === 0) {
            $accordion.html('<p class="text-muted">No availability data found for the selected time slots.</p>');
            return;
        }

        const times = Object.keys(availability).sort();
        const accordionHtml = times.map((time, index) => {
            const dates = availability[time].sort((a, b) => moment(a.date).diff(moment(b.date)));
            const tableRows = dates.map(entry => {
                const date = moment(entry.date, 'MMMM DD, YYYY').format('MMMM DD, YYYY');
                let seatsAvailable = entry.seatsAvailable;
                if (data.updateExistingBookings) {
                    seatsAvailable = entry.seatsAvailable + entry.seatsExist;
                }
                const isOverbooked = entry.seatsRequested > seatsAvailable;
                const isFullyBooked = seatsAvailable === 0;

                // The mass tool skips (rather than fails on) any booking
                // that's ever had a split, a pax removed, or any payment
                // (it's only for unpaid "Pay Later" bookings) -- flag it
                // here so the admin sees which ones will be left untouched
                // before they even submit.
                const ineligibleReasons = [];
                if (data.updateExistingBookings && entry.bookingExists) {
                    if (entry.hasSplitOrRemoved) {
                        ineligibleReasons.push('already has a split and/or removed pax');
                    }
                    if (entry.hasPayment) {
                        ineligibleReasons.push('has already received a payment (this tool is only for unpaid bookings)');
                    }
                }
                const isIneligible = ineligibleReasons.length > 0;

                let rowClass = 'table-success bg-[#e8ffea]';
                let statusIcon = '<span class="px-3 py-[5px] bg-[#e8ffea] rounded-[100px] border border-[#2ea135] font-semibold text-[#2ea135] text-sm inline-flex items-center gap-2"><i class="fas fa-check"></i>Available</span>';

                let seatsRequestedContent = `
                    <input type="number" class="form-control form-custom-input seats-input-preview"
                        data-time="${time}" data-date="${date}"
                        value="${entry.seatsRequested}" min="1" ${isIneligible ? 'disabled' : ''}
                        aria-label="Seats requested for ${date} at ${time}">
                `;

                if (data.updateExistingBookings) {
                    if (isIneligible) {
                        rowClass = 'table-danger bg-[#ffe8e8]';
                        statusIcon = '<span class="px-3 py-[5px] bg-[#ffe8e8] rounded-[100px] border border-[#ff0000] font-semibold text-[#ff0000] text-sm inline-flex items-center gap-2"><i class="fas fa-times"></i>Not Eligible</span>';
                    } else if (isOverbooked) {
                        rowClass = 'table-warning bg-orange-50';
                        statusIcon = '<span class="px-3 py-[5px] bg-orange-50 rounded-[100px] border border-[#ffc107] font-semibold text-[#ffc107] text-sm inline-flex items-center gap-2"><i class="fas fa-times"></i>Overbooked</span>';
                    }
                } else {
                    if (entry.bookingExists || isFullyBooked) {
                        rowClass = 'table-danger bg-[#ffe8e8]';
                        statusIcon = '<span class="px-3 py-[5px] bg-[#ffe8e8] rounded-[100px] border border-[#ff0000] font-semibold text-[#ff0000] text-sm inline-flex items-center gap-2"><i class="fas fa-times"></i>Unavailable</span>';
                        seatsRequestedContent = entry.seatsRequested;
                    } else if (isOverbooked) {
                        rowClass = 'table-warning';
                        statusIcon = '<span class="px-3 py-[5px] bg-orange-50 rounded-[100px] border border-[#ffc107] font-semibold text-[#ffc107] text-sm inline-flex items-center gap-2"><i class="fas fa-times"></i>Overbooked</span>';
                    }
                }

                const bookingLink = entry.bookingExists
                    ? `<a href="${this.bookingShowUrl.replace('BOOKING_ID_PLACEHOLDER', entry.bookingExists)}" target="_blank">${entry.bookingExists}</a>`
                    : 'N/A';

                const row = `
                    <tr class="${rowClass}" data-time="${time}" data-date="${date}" data-seats-available="${entry.seatsAvailable}" data-seats-exist="${entry.seatsExist}" data-booking-id="${entry.bookingExists || ''}" ${isIneligible ? 'data-ineligible-for-mass-update="true"' : ''}>
                        <td class="td-custom-css">${date}</td>
                        <td class="td-custom-css">${entry.day}</td>
                        <td class="td-custom-css">${seatsRequestedContent}</td>
                        <td class="td-custom-css">${seatsAvailable}</td>
                        <td class="td-custom-css text-center">${statusIcon}</td>
                        <td class="td-custom-css">${bookingLink}</td>
                    </tr>
                `;

                const errorRow = isIneligible ? `
                    <tr class="ineligible-for-mass-update-row bg-[#ffe8e8]">
                        <td class="td-custom-css" colspan="6">
                            <span class="text-sm text-[#ff0000]"><i class="fas fa-exclamation-triangle mr-1"></i>Booking #${entry.bookingExists} ${ineligibleReasons.join(' and it ')} and will be skipped. Please edit that booking directly instead.</span>
                        </td>
                    </tr>
                ` : '';

                return row + errorRow;
            }).join('');

            return `
                <div class="accordion-item mb-4">
                    <h2 class="accordion-header" id="heading-${index}">
                        <button class="accordion-button accordion-button-wrap" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-${index}" aria-expanded="true" aria-controls="collapse-${index}">
                            Time Slot: ${time}
                      <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                        </button>
                    </h2>
                    <div id="collapse-${index}" class="accordion-collapse collapse show" aria-labelledby="heading-${index}" data-bs-parent="#slot-availability-accordion">
                        <div class="accordion-body p-3 border border-[#E5E7EB] rounded-lg">
                            <table class="table table-striped slot-availability-table w-full border-collapse text-left">
                                <thead>
                                    <tr>
                                        <th class="th-custom-css">Date</th>
                                        <th class="th-custom-css">Day</th>
                                        <th class="th-custom-css">Seats Requested</th>
                                        <th class="th-custom-css">Seats Available</th>
                                        <th class="th-custom-css">Availability</th>
                                        <th class="th-custom-css">Booking ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${tableRows}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        $accordion.html(accordionHtml);
        this.bindSeatsInputEvents();
    }

    bindSeatsInputEvents() {
        $(document).off('change', '.seats-input-preview').on('change', '.seats-input-preview', (e) => {
            const $input = $(e.target);
            const time = $input.data('time');
            const date = $input.data('date');
            const seatsRequested = parseInt($input.val());
            const $row = $input.closest('tr');
            const bookingId = $row.data('booking-id');
            const seatsAvailable = parseInt($row.data('seats-available'));
            const seatsExist = parseInt($row.data('seats-exist'));
            const seatsTotal = bookingId ? seatsAvailable + seatsExist : seatsAvailable;
            // if (seatsRequested < 1) {
            //     seatsRequested = 1;
            //     console.log('Seats requested cannot be less than 1. Resetting to 1.');
            //     $input.val(1);
            // }

            const key = `${time}_${date}`;
            this.modifiedSeats[key] = seatsRequested;
            let rowClass = 'bg-[#e8ffea]';
            let statusIcon = '<span class="px-3 py-[5px] bg-[#e8ffea] rounded-[100px] border border-[#2ea135] font-semibold text-[#2ea135] text-sm inline-flex items-center gap-2"><i class="fas fa-check"></i> Available</span>';
            if (seatsRequested > seatsTotal) {
                rowClass = 'bg-orange-50';
                statusIcon = '<span class="px-3 py-[5px] bg-orange-50 rounded-[100px] border border-[#ffc107] font-semibold text-[#ffc107] text-sm inline-flex items-center gap-2"><i class="fas fa-times"></i> Overbooked</span>';
            }
            $row.removeClass('table-success bg-orange-50 bg-[#e8ffea]').addClass(rowClass);
            $row.find('td:nth-child(5)').html(statusIcon);
        });
    }

    renderBookingsTable(bookings) {
        const $container = $('#bookings-container');
        $container.empty();

        if (!bookings || bookings.length === 0) {
            $container.html('<p class="text-muted">No bookings found for the selected criteria.</p>');
            return;
        }

        const tableHtml = `
            <table class="table table-striped bookings-table">
                <thead>
                    <tr>
                        <th class="th-custom-css">Booking ID</th>
                        <th class="th-custom-css">Date</th>
                        <th class="th-custom-css">Time</th>
                        <th class="th-custom-css">Seats</th>
                        <th class="th-custom-css">Total Cost</th>
                        <th class="th-custom-css">Status</th>
                    </tr>
                </thead>
                <tbody>
                    ${bookings.map(booking => `
                        <tr>
                            <td class="td-custom-css"><a href="${this.bookingShowUrl.replace('BOOKING_ID_PLACEHOLDER', booking.id)}" target="_blank">${booking.id}</a></td>
                            <td class="td-custom-css">${booking.date}</td>
                            <td class="td-custom-css">${booking.time}</td>
                            <td class="td-custom-css">${booking.seats}</td>
                            <td class="td-custom-css">$${parseFloat(booking.total).toFixed(2)}</td>
                            <td class="td-custom-css">${booking.status}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;

        $container.html(tableHtml);
    }
}
