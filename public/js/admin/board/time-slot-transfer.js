let { DateTime } = luxon;

document.querySelectorAll(".timeSlotBtn").forEach(btn => {
    btn.addEventListener("click", function () {
        // Inside Guide Management modal → use the board's currentDate (the displayed date).
        // Outside (e.g. main board page button) → fall back to the calendar selected date.
        const inGuideModal = this.closest('#guideManagementModal');
        const formattedDate = (inGuideModal && typeof currentDate !== 'undefined' && currentDate)
            ? currentDate
            : getCalendarSelectedDate();

        if (!formattedDate) {
            Swal.fire("No Date Selected", "Please select a date from the calendar.", "warning");
            return;
        }

        // Clear search box before opening modal
        const searchInput = document.getElementById("timeSlotSearch");
        if (searchInput) searchInput.value = "";

        const modalBody = document.getElementById("timeSlotAccordion");
        modalBody.innerHTML = `<div class="text-center text-muted">Loading time slots...</div>`;

        const timeSlotTransferUrl = `${timeSlotTransfer}?date=${encodeURIComponent(formattedDate)}`;
        showLoading();

        fetch(timeSlotTransferUrl)
        .then(res => res.json())
        .then(data => {
            hideLoading();
            modalBody.innerHTML = "";

            const dateFormatted = DateTime.fromISO(formattedDate, { zone: timezone })
                .toFormat("cccc, LLLL dd, yyyy");

            $('#transferDate').text(dateFormatted);

            if (!data || Object.keys(data).length === 0) {
                modalBody.innerHTML = `<div class="text-center text-muted">No bookings found for ${dateFormatted}.</div>`;
                new bootstrap.Modal(document.getElementById("timeSlotModal")).show();
                return;
            }

            // Compare with Blade-passed currentSlotTime (e.g. "09:30:00")
            const currentTime = DateTime.fromFormat(currentSlotTime, "HH:mm:ss", { zone: timezone });
            const today = DateTime.now().setZone(timezone).toFormat("yyyy-MM-dd");
            const isToday = formattedDate === today;

            let index = 0;
            for (const [slotTime, bookings] of Object.entries(data)) {
                const slotId = `slot-${index++}`;

                // Convert slotTime "09:30 AM" to 24hr format for comparison
                const slotTimeObj = DateTime.fromFormat(slotTime, "hh:mm a", { zone: timezone });

                let bookingHtml = bookings.map(booking => {
                    const assignedGuests = booking.tourists || 0;
                    const checkIns = { "1": 0, "2": 0, "A": 0, ...(booking.checkInsCount || {}) };
                    const totalCheckIns = (checkIns["1"] || 0) + (checkIns["2"] || 0);
                    const pending = Math.max(assignedGuests - totalCheckIns - (checkIns["A"] || 0), 0);

                    let checkInHtml = "";
                    if (pending > 0 && checkIns["1"] === 0 && checkIns["2"] === 0 && checkIns["A"] === 0) {
                        checkInHtml = `<span style="background:#ffcccc; color:#b30000">Not Checked In: ${pending}</span>`;
                    } else {
                        const parts = [];
                        if (checkIns["1"] > 0)
                            parts.push(`<span style="background:#fff3cd; padding:2px 6px; border-radius:4px;">1st Check In: ${checkIns["1"]}</span>`);
                        if (checkIns["2"] > 0)
                            parts.push(`<span style="background:#ffe066; padding:2px 6px; border-radius:4px;">2nd Check In: ${checkIns["2"]}</span>`);
                        if (checkIns["A"] > 0)
                            parts.push(`<span style="background:#ffcccc; color:#b30000; padding:2px 6px; border-radius:4px;">Absent: ${checkIns["A"]}</span>`);
                        if (pending > 0)
                            parts.push(`<span style="background:#ffcccc; color:#b30000;">Not Checked In: ${pending}</span>`);
                        checkInHtml = parts.join(" ");
                    }

                    return `
                        <div class="assigned-booking card p-2 mb-2 border-0 bg-light shadow-sm"
                             data-booking-id="${booking.id}"
                             data-date="${booking.date}"
                             data-time="${booking.time_from}"
                             style="cursor:pointer;">
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                <span><strong>${typeof tenantId !== "undefined" ? tenantId + "-" : ""}${booking.id}</strong></span>
                                <span>${booking.name || ""}</span>
                                <span><strong>${booking.tour_package || ""}</strong></span>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:6px; font-size:0.9rem;">
                                <span>👥 ${assignedGuests} guests</span>
                                <span> ${booking.infants} infants</span>
                                ${checkInHtml}
                            </div>
                        </div>
                    `;
                }).join("");

                // ✅ Collapse logic: collapse past slots if today
                const shouldCollapse = isToday && slotTimeObj < currentTime;
                const collapseClass = shouldCollapse ? "collapse" : "collapse show";

                const accordionItem = `
                    <div class="accordion-item mb-2">
                        <h2 class="accordion-header" id="heading-${slotId}">
                            <button class="accordion-button fw-bold ${shouldCollapse ? 'collapsed' : ''}" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#collapse-${slotId}"
                                aria-expanded="${shouldCollapse ? 'false' : 'true'}"
                                aria-controls="collapse-${slotId}">
                                🕒 ${slotTime}
                            </button>
                        </h2>
                        <div id="collapse-${slotId}" class="accordion-collapse ${collapseClass}" aria-labelledby="heading-${slotId}">
                            <div class="accordion-body p-2">
                                ${bookingHtml || '<div class="text-muted small">No bookings for this slot</div>'}
                            </div>
                        </div>
                    </div>
                `;
                modalBody.insertAdjacentHTML("beforeend", accordionItem);
            }

            new bootstrap.Modal(document.getElementById("timeSlotModal")).show();
        })
        .catch(err => {
            console.error("Error fetching time slots:", err);
            modalBody.innerHTML = `<div class="text-danger text-center">Failed to load time slots.</div>`;
        });
    });
});

// Click handler for bookings
document.getElementById("timeSlotAccordion").addEventListener("click", function (e) {
    const card = e.target.closest(".assigned-booking");
    if (card) {
        const bookingId = card.dataset.bookingId;
        const timeSlotModalEl = document.getElementById("timeSlotModal");
        const timeSlotModal = bootstrap.Modal.getInstance(timeSlotModalEl);
        if (timeSlotModal) timeSlotModal.hide();

        document.getElementById("bookingSearchInput").value = bookingId;
        document.getElementById("bookingSearchBtn").click();
    }
});

// Live Search Filter inside Modal
document.getElementById("timeSlotSearch").addEventListener("input", function () {
    const searchValue = this.value.toLowerCase();

    document.querySelectorAll("#timeSlotAccordion .assigned-booking").forEach(card => {
        const text = card.innerText.toLowerCase();
        card.style.display = text.includes(searchValue) ? "" : "none";
    });

    document.querySelectorAll("#timeSlotAccordion .accordion-item").forEach(item => {
        const visibleBookings = item.querySelectorAll(".assigned-booking:not([style*='display: none'])").length;
        item.style.display = visibleBookings > 0 ? "" : "none";
    });
});

// Admin tour guide feature start
function applyStatusColor(dropdown) {
    const value = dropdown.value;

    dropdown.classList.remove(
        'bg-success', 'bg-secondary', 'bg-warning', 'bg-danger',
        'text-dark', 'text-white'
    );

    switch (value) {
        case 'Active':
            dropdown.classList.add('bg-success', 'text-white');
            break;
        case 'Inactive':
            dropdown.classList.add('bg-secondary', 'text-white');
            break;
    }
}

// Apply color on page load
function initializeGuideDropdowns() {
    document.querySelectorAll('.status-dropdown').forEach(dd => {
        applyStatusColor(dd);

        dd.addEventListener('change', function () {
            applyStatusColor(this);
            const todayDate = DateTime.now().setZone(timezone).toFormat("yyyy-MM-dd");
            showLoading();

            const queryParams = new URLSearchParams({
                guide_status: this.value,
                id: this.dataset.guideId,
                todayDate: todayDate
            });

            const url = `${updateGuideStatusUrl}?${queryParams.toString()}`;

            fetch(url, {
                method: "GET",
                headers: { "X-Requested-With": "XMLHttpRequest" }
            })
            .then(res => res.json())
            .then(data => {
                hideLoading();
                showToast('Updated successfully', 'success');
            })
            .catch(err => console.error("Error updating guide status:", err));
        });
    });
}

$(document).on('click', '#activateAllGuidesBtn', function () {
    Swal.fire({
        title: 'Activate all guides?',
        text: 'This will mark all guides as Active.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, Activate',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#198754'
    }).then((result) => {
        if (!result.isConfirmed) return;
        showLoading();
        $.ajax({
            url: guideStatusActiveAllUrl,
            type: "POST",
            data: {
                _token: csrfToken
            },
            success: function (res) {
                    // Update all dropdowns instantly
                    $('.status-dropdown').each(function () {
                        $(this).val('Active');
                        applyStatusColor(this);
                    });
                    hideLoading();
                    showToast('Updated successfully', 'success');
            },
            error: function () {
                console.error("Error updating guide status:", 'err');
            }
        });
    });
});

// Admin tour guide feature end

// Admin auto assig guide start
$(document).on('click', '#autoAssignGuideBtn', function () {
    showLoading();
    $.ajax({
            url: autoAssignGuideUrl,
            type: "POST",
            data: {
                slot_id: currentSlotId,
                time_from: currentTimeFrom,
                date: currentDate,
                _token: csrfToken
            },
            success: function (response) {
                hideLoading();
                if (response.success) {
                    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                    showToast('Auto-assign completed!', 'success');
                }
                if (response.assigned) {
                    showToast('No free guides or stations available!', 'success');
                }
            },
            error: function () {
                hideLoading();
                showToast('Something went wrong. Please try again!', 'error');
            }
        });
});

// Admin auto assig guide end
