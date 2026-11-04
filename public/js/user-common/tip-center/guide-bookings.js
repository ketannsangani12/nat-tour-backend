class GuideBookings {
    constructor(props) {
        this.init(props);
    }

    init(props) {
        this.userType = props.userType;
        this.userRole = props.userRole;
        this.guideId = props.guideId;
        this.fetchGuideBookingsUrl = props.fetchGuideBookingsUrl;
        this.bookingInfoUrl = props.bookingInfoUrl;
        this.todayDate = props.todayDate;
        this.defaultDate = props.defaultDate;
        this.bookingPrefix = props.bookingPrefix;
        this.initSelect2();
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
    addGlobalListeners() {
        this.renderDateRangePicker();
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
    bindEvents() {
        $(document).off('click', '.guide-header').on('click', '.guide-header', (evt) => {
            this.toggleSlot(evt);
        });
        $(document).off('change', '#tour-package-filter').on('change', '#tour-package-filter', (evt) => {
            this.fetchBookings();
        });
        $(document).off('change', '#guide-filter').on('change', '#guide-filter', (evt) => {
            this.fetchBookings();
        });
        $(document).off('change', '#slot-filter').on('change', '#slot-filter', (evt) => {
            this.renderSlotBookings(evt);
        });
        $(document).off('change', '#search-input').on('change', '#search-input', (evt) => {
            this.fetchBookings();
        });
        $(document).off('click', '#expand-all-btn').on('click', '#expand-all-btn', (evt) => {
            this.toggleAllSlots(evt);
        });
        $(document).off('click', '#clear-filters-btn').on('click', '#clear-filters-btn', (evt) => {
            this.clearFilters();
        });
        var firstVal = $('#tour-package-filter option:not([disabled]):eq(0)').val();
        $('#tour-package-filter').val(firstVal).trigger('change');

        $('#date-range-filter').on('apply.daterangepicker', (ev, picker) => {
            this.fetchBookings();
        });
    }
    fetchBookings() {
        var self = this;
        var packageId = $('#tour-package-filter').val();
        var guideId = (this.userType == 'Admin' || this.userRole == 'staff') ? $('#guide-filter').val() : this.guideId;
        var slotId = $('#slot-filter').val();
        var dateRange = $('#date-range-filter').val();
        const [fromDate, toDate] = dateRange.split(" - ");
        var search = $('#search-input').val().trim();

        if (!fromDate || !toDate) {
            swal('Invalid Input', 'Please select a tour package and a date.', 'warning');
            return;
        }

        this.showLoading();
        $.ajax({
            url: this.fetchGuideBookingsUrl,
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
            success: this.renderBookings.bind(this),
        }).always(this.hideLoading())
            .fail(function (jqXHR) {
                self.hideLoading();
                //showSubmissionError(jqXHR);
            });
    }
    renderBookings(data) {
        var slotsBookings = data.slotsBookings;
        var slots = data.slots;

        var html = ``;

        slotsBookings.forEach(slotsBooking => {

            html += `<div class="guide-card" data-slot="${slotsBooking.tour_slot_id}">
                        <div class="guide-header" data-id="${slotsBooking.tour_slot_id}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-chevron-right chevron-icon rotated" id="chevron-slot-${slotsBooking.tour_slot_id}"></i>
                                        <div class="avatar-fallback d-none">ER</div>
                                    </div>

                                    <div>
                                        <div class="d-flex align-items-center gap-3 mb-1">
                                            <h5 class="mb-0 fw-bold">${formatTime(slotsBooking.time_from)} - ${formatTime(slotsBooking.time_to)}</h5>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-4">
                                    <div class="stat-item">
                                        <div class="stat-value text-dark">${slotsBooking.bookings.length}</div>
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

                        <div class="booking-content show" id="content-slot-${slotsBooking.tour_slot_id}">
                            <div class="p-3">
                                <table class="table mb-0 slot-bookings-table">
                                    <thead>
                                        <tr>
                                            <th>Booking</th>
                                            <th>Tour</th>
                                            <th>Date</th>
                                            <th>Customer</th>
                                            <th>Guests</th>
                                            <th>Payment</th>`;
            if (this.userType == 'Admin' || this.userRole == 'staff') {
                html += `<th>Guide</th>
                                                <th>Action</th>`;
            } else {
                html += `<th>Tip Amount</th>`;
            }
            html += `</tr>
                                    </thead>`;
            html += `<tbody>`;
            slotsBooking.bookings.forEach(booking => {
                var bookingInfoUrl = this.bookingInfoUrl.replace(':id', booking.booking_id);
                html += `<tr>   
                                            <td>
                                                <div>
                                                    <div class="fw-medium">`;
                if (this.userType == 'Admin' || this.userRole == 'staff') {
                    html += `<a href="${(this.userType == 'Admin' || this.userRole == 'staff') ? bookingInfoUrl : ''}" target="_blank">${this.bookingPrefix}${booking.booking_id}</a></div>`;
                } else {
                    html += `${this.bookingPrefix}${booking.booking_id}</div>`;
                }

                html += `</div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="">${booking.tour_package_name}</div>
                                                </div>
                                            </td>
                                             <td>
                                                <div>
                                                    <div class="">${booking.date}</div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="">${booking.customer_name}</span>
                                            </td>
                                            <td>
                                                <span class="">${booking.tourists} people</span>
                                            </td>
                                            <td>
                                              <span class="badge ${booking.paid == 1 ? 'bg-success' : 'bg-danger'}">${booking.paid == 1 ? 'Paid' : 'Unpaid'}</span>
                                            </td>`;
                if (this.userType == 'Admin' || this.userRole == 'staff') {
                    html += `<td>
                                                <div>
                                                    <div class="fw-semibold">`;
                    if (booking.assigned_guides == null) {
                        html += `<span class="badge bg-warning">Not Assigned</span>`;
                    } else {
                        var assignedGuides = booking.assigned_guides.split(',');
                        assignedGuides.forEach(guide => {
                            html += `<span class="guide-badge badge bg-success">${guide} </span><br>`;
                        });
                    }
                    html += `</div>
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" onclick="getAssignedTourGuides(${booking.booking_id})" class="btn assign-guide-btn btn-sm btn-primary-small whitespace-nowrap">
													<i class="fas fa-users me-2"></i>Assign Guides
												</button>
                                            </td>`;
                } else {
                    html += `<td>${currencyFormation.symbol + booking.tip_total ? booking.tip_total : 'N/A'}</td>`;
                }

                html += `</tr>`;
            });

            html += `</tbody>
                                </table>


                            </div>
                        </div>
                    </div>`;

        });
        $('#bookings-container').html(html);
        $('#totalBookingsCount').text(data.totalBookingsCount);
        $('#totalUnAssignedBookingsCount').text(data.totalUnAssignedBookingsCount);
        $('#totalAssignedBookingsCount').text(data.totalAssignedBookingsCount);
        if (slots.length > 0) {
            var slotFilter = $('#slot-filter');
            slotFilter.empty();
            slotFilter.append('<option value="0">Show All Time</option>');
            slots.forEach(slot => {
                slotFilter.append(`<option value="${slot.tour_slot_id}">${formatTime(slot.time_from)} - ${formatTime(slot.time_to)}</option>`);
            });
        }
    }
    renderSlotBookings(evt) {
        var slotId = $(evt.currentTarget).val();

        $('.guide-card').each(function () {
            var $this = $(this);

            if (slotId) {
                if (this.getAttribute('data-slot') == slotId) {
                    $this.removeClass('d-none');
                } else if (slotId == 0) {
                    $this.removeClass('d-none');
                } else {
                    $this.addClass('d-none');
                }
            } else {
                $this.removeClass('d-none');
            }
        });
    }
    clearFilters() {
        var firstVal = $('#tour-package-filter option:not([disabled]):eq(1)').val();
        $('#tour-package-filter').val(firstVal);
        $('#guide-filter').val(0);
        $('#slot-filter').val('0');
        $('#date-range-filter').val(this.defaultDate.format('MMMM D, YYYY') + ' - ' + this.defaultDate.format('MMMM D, YYYY')).change();
        this.fetchBookings();
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

    showLoading() {
        document.getElementById('loading').style.display = 'block';
        document.getElementById('bookings-container').style.display = 'none';
    }

    hideLoading() {
        document.getElementById('loading').style.display = 'none';
        document.getElementById('bookings-container').style.display = 'block';
    }
}


