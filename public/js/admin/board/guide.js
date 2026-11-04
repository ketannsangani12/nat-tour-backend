document.addEventListener('DOMContentLoaded', function () {
    const { DateTime } = luxon;

    // Global state
    let currentMonth = DateTime.now().setZone(timezone).startOf("month");
    let selectedDate = null;
    let assignments = {}; // ✅ declare globally so it's accessible everywhere

    // --- Render Calendar ---
    function renderCalendar() {
        const container = document.getElementById("calendarContainer");
        const header = document.getElementById("calendarHeader");
        if (!container || !header) return;

        container.innerHTML = "";
        header.textContent = currentMonth.toFormat("LLLL yyyy");

        // Weekday row
        const weekdays = ["Su", "Mo", "Tu", "We", "Th", "Fr", "Sa"];
        const weekdaysRow = document.createElement("div");
        weekdaysRow.className = "calendar-weekdays";
        weekdays.forEach(d => {
            const el = document.createElement("div");
            el.textContent = d;
            weekdaysRow.appendChild(el);
        });
        container.appendChild(weekdaysRow);

        const startOfMonth = currentMonth.startOf("month");
        const endOfMonth = currentMonth.endOf("month");
        const firstWeekday = startOfMonth.weekday % 7;
        const daysInMonth = endOfMonth.day;

        const daysGrid = document.createElement("div");
        daysGrid.className = "calendar-days";

        // Empty boxes before first day
        for (let i = 0; i < firstWeekday; i++) {
            const emptyDiv = document.createElement("div");
            emptyDiv.className = "day-box empty";
            emptyDiv.style.visibility = "hidden";
            daysGrid.appendChild(emptyDiv);
        }

        const today = DateTime.now().setZone(timezone).startOf("day");

        // Days rendering
        for (let dayNum = 1; dayNum <= daysInMonth; dayNum++) {
            const day = startOfMonth.plus({ days: dayNum - 1 });
            const dateStr = day.toFormat("yyyy-MM-dd");
            const count = assignments[dateStr] || "";

            const div = document.createElement("div");
            div.className = "day-box";
            div.dataset.date = dateStr;

            if (day.hasSame(today, "day")) div.classList.add("today");
            if (selectedDate && day.hasSame(selectedDate, "day")) div.classList.add("active");

            div.innerHTML = `
                <div class="date-label">${dayNum}</div>
                <div class="count-label">${count}</div>
            `;

            div.addEventListener("click", (evt) => handleDateClick(day, evt));
            daysGrid.appendChild(div);
        }

        container.appendChild(daysGrid);
    }

    // --- Handle Date Click ---
    function handleDateClick(day, evt) {
        selectedDate = day;
        document.querySelectorAll(".day-box").forEach(d => d.classList.remove("active"));
        evt.currentTarget.classList.add("active");

        const formattedDate = day.setZone(timezone).toFormat("yyyy-MM-dd HH:mm:ss");
        const fromDate = currentMonth.setZone(timezone).startOf("month").toFormat("yyyy-MM-dd HH:mm:ss");
        const toDate = currentMonth.setZone(timezone).endOf("month").toFormat("yyyy-MM-dd HH:mm:ss");

        showLoading();
        fetch(`${indexUrl}?date=${encodeURIComponent(formattedDate)}&from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(res => res.json())
        .then(html => {
            document.getElementById("boardData").innerHTML = html.html || "";

            // ✅ Reinitialize dropdown colors after content loads
            initGuideStatusDropdowns();

            if (html.monthlyAssignments) {
                assignments = html.monthlyAssignments;
                renderCalendar(); // re-render with counts
            }
        })
        .catch(err => console.error("Error loading data:", err))
        .finally(() => hideLoading());
    }

    // --- Month Navigation ---
    document.getElementById("prevMonth").addEventListener("click", () => {
        currentMonth = currentMonth.minus({ months: 1 });
        fetchMonthData(true);
    });

    document.getElementById("nextMonth").addEventListener("click", () => {
        currentMonth = currentMonth.plus({ months: 1 });
        fetchMonthData(true);
    });

    // --- Fetch Month Data ---
    function fetchMonthData(fromNavigation = false) {
        const fromDate = currentMonth.startOf("month").toFormat("yyyy-MM-dd HH:mm:ss");
        const toDate = currentMonth.endOf("month").toFormat("yyyy-MM-dd HH:mm:ss");

        showLoading();
        fetch(`${indexUrl}?from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" }
        })
        .then(res => res.json())
        .then(html => {
            if (html.monthlyAssignments) {
                assignments = html.monthlyAssignments;
            }

            renderCalendar();

            if (fromNavigation) {
                const today = DateTime.now().setZone(timezone).startOf("day");
                const isCurrentMonth = today.hasSame(currentMonth, "month");
                const autoSelectDate = isCurrentMonth ? today : currentMonth.startOf("month");

                selectedDate = autoSelectDate;
                const box = document.querySelector(`[data-date="${autoSelectDate.toFormat("yyyy-MM-dd")}"]`);
                if (box) {
                    box.classList.add("active");
                    handleDateClick(autoSelectDate, { currentTarget: box });
                }
            } else {
                selectTodayIfNone();
            }
        })
        .catch(err => console.error("Error fetching month data:", err))
        .finally(() => hideLoading());
    }

    // --- Select today by default if no date chosen ---
    function selectTodayIfNone() {
        if (!selectedDate) {
            selectedDate = DateTime.now().setZone(timezone).startOf("day");
            const todayBox = document.querySelector(`[data-date="${selectedDate.toFormat("yyyy-MM-dd")}"]`);
            if (todayBox) {
                todayBox.classList.add("active");
                handleDateClick(selectedDate, { currentTarget: todayBox });
            }
        }
    }

    // --- Loading Overlay ---
    function showLoading() {
        if (!document.getElementById("loadingOverlay")) {
            document.body.insertAdjacentHTML('beforeend', `
                <div id="loadingOverlay" class="position-fixed w-100 h-100 d-flex align-items-center justify-content-center"
                    style="top:0; left:0; background:rgba(255,255,255,0.7); z-index:9999;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `);
        }
    }

    function hideLoading() {
        const loader = document.getElementById("loadingOverlay");
        if (loader) loader.remove();
    }

    function showToast(message, type = 'info') {
        const toast = $(`
            <div class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `);

        $('body').append(`<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>`);
        $('#toastContainer').append(toast);

        const bsToast = new bootstrap.Toast(toast[0]);
        bsToast.show();

        toast.on('hidden.bs.toast', function () {
            $(this).remove();
        });
    }

    // --- Dropdown Color Handling (Reusable) ---
    function initGuideStatusDropdowns() {
        document.querySelectorAll('#guide_status').forEach(dropdown => {

            function updateColor() {
                const value = dropdown.value;
                dropdown.classList.remove('bg-success', 'bg-secondary', 'bg-warning', 'bg-danger', 'text-dark', 'text-white');

                switch (value) {
                    case 'Active':
                        dropdown.classList.add('bg-success', 'text-white');
                        break;
                    case 'Inactive':
                        dropdown.classList.add('bg-secondary', 'text-white');
                        break;
                }
            }

            // AJAX update to database
            function updateStatusInDB() {
                const newStatus = dropdown.value;
                const guideId   = dropdown.dataset.guideId;
                const todayDate = DateTime.now().setZone(timezone).toFormat("yyyy-MM-dd");
                // Build query string parameters
                const queryParams = new URLSearchParams({
                    guide_status: newStatus,
                    id: guideId,
                    todayDate: todayDate
                });
                showLoading();
                const url = `${updateGuideStatusUrl}?${queryParams.toString()}`;

                fetch(url, {
                    method: "GET",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    },
                })
                .then(res => res.json())
                .then(data => {
                    fetchMonthData(true);
                    hideLoading();
                    showToast('Updated successfully', 'success');
                })
                .catch(err => console.error("Error updating guide status:", err));
            }
            dropdown.addEventListener('change', () => {
                updateColor();
                updateStatusInDB();
            });

            updateColor();
        });
    }

    // --- Initial Load ---
    fetchMonthData();
    initGuideStatusDropdowns(); // Initialize for any dropdown already in the DOM
});