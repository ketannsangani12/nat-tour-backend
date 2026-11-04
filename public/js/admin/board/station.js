document.addEventListener('DOMContentLoaded', function () {
    const { DateTime } = luxon;
    let currentMonth = DateTime.now().setZone(timezone).startOf("month");
    let selectedDate = null;
    let assignments = {}; // global — shared between both fetches

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

      // Blank boxes for alignment
      for (let i = 0; i < firstWeekday; i++) {
        const emptyDiv = document.createElement("div");
        emptyDiv.className = "day-box empty";
        emptyDiv.style.visibility = "hidden";
        daysGrid.appendChild(emptyDiv);
      }

      const today = DateTime.now().setZone(timezone).startOf("day");

      // Days loop
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
    function handleDateClick(day, evt, silent = false) {
      selectedDate = day;
      document.querySelectorAll(".day-box").forEach(d => d.classList.remove("active"));
      evt.currentTarget.classList.add("active");

      const formattedDate = day.setZone(timezone).toFormat("yyyy-MM-dd HH:mm:ss");
      const fromDate = currentMonth.setZone(timezone).startOf("month").toFormat("yyyy-MM-dd HH:mm:ss");
      const toDate = currentMonth.setZone(timezone).endOf("month").toFormat("yyyy-MM-dd HH:mm:ss");

      if (!silent) showLoading();

      fetch(`${indexUrl}?date=${encodeURIComponent(formattedDate)}&from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
        .then(res => res.json())
        .then(html => {
          //  Only update board content — no calendar re-render here
          document.getElementById("boardData").innerHTML = html.slotsHtml;
          document.getElementById("stationTableBody").innerHTML = html.stationListHtml;
          document.getElementById("stationAdd").innerHTML = html.stationAddHtml;
          document.getElementById("stationEdit").innerHTML = html.stationEditHtml;
          document.getElementById("tourGuideList").innerHTML = html.guideListHtml;
          initializeGuideDropdowns();

          //  Don't re-render calendar on date click
          // (No need to call renderCalendar again)
        })
        .catch(err => console.error("Error loading data:", err))
        .finally(() => { if (!silent) hideLoading(); });
    }

    // --- Month Navigation ---
    document.getElementById("prevMonth").addEventListener("click", () => {
      currentMonth = currentMonth.minus({ months: 1 });
      fetchMonthData(true); // indicate this came from month navigation
    });

    document.getElementById("nextMonth").addEventListener("click", () => {
      currentMonth = currentMonth.plus({ months: 1 });
      fetchMonthData(true); // indicate this came from month navigation
    });

    // --- Fetch Month Data ---
    function fetchMonthData(fromNavigation = false, silent = false, preserveDate = false) {
      const fromDate = currentMonth.startOf("month").toFormat("yyyy-MM-dd HH:mm:ss");
      const toDate = currentMonth.endOf("month").toFormat("yyyy-MM-dd HH:mm:ss");

      if (!silent) showLoading();

      fetch(`${indexUrl}?from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
      })
        .then(res => res.json())
        .then(html => {
          if (html.assignments) assignments = html.assignments;

          renderCalendar();

          if (preserveDate && selectedDate) {
            // Re-select the same date the user had chosen — don't jump to today
            const dateBox = document.querySelector(`[data-date="${selectedDate.toFormat("yyyy-MM-dd")}"]`);
            if (dateBox) {
              dateBox.classList.add("active");
              handleDateClick(selectedDate, { currentTarget: dateBox }, silent);
            }
          } else if (fromNavigation) {
            const today = DateTime.now().setZone(timezone).startOf("day");
            const isCurrentMonth = today.hasSame(currentMonth, "month");
            const autoSelectDate = isCurrentMonth ? today : currentMonth.startOf("month");

            selectedDate = autoSelectDate;
            const dateBox = document.querySelector(`[data-date="${autoSelectDate.toFormat("yyyy-MM-dd")}"]`);
            if (dateBox) {
              dateBox.classList.add("active");
              handleDateClick(autoSelectDate, { currentTarget: dateBox }, silent);
            }
          } else {
            selectTodayIfNone(silent);
          }
        })
        .catch(err => console.error("Error fetching month data:", err))
        .finally(() => { if (!silent) hideLoading(); });
    }

    // --- Select today by default if none ---
    function selectTodayIfNone(silent = false) {
      if (!selectedDate) {
        selectedDate = DateTime.now().setZone(timezone).startOf("day");
        const todayBox = document.querySelector(`[data-date="${selectedDate.toFormat("yyyy-MM-dd")}"]`);
        if (todayBox) {
          todayBox.classList.add("active");
          handleDateClick(selectedDate, { currentTarget: todayBox }, silent);
        }
      }
    }

    // --- Global refresh function ---
    window.loadBoardData = function (date = null, time = null, silent = false) {
      if (selectedDate) {
        fetchMonthData(false, silent, true); // update calendar counts and re-select current date
      } else {
        selectTodayIfNone();
      }
    };

    // --- Initial Load ---
    fetchMonthData();
    document.getElementById("board-tab").addEventListener("click", loadBoardData);

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.station-btn');
        if (!btn) return;

        // Get slot info
        const timeFrom = btn.getAttribute('data-time-from');
        const date = btn.getAttribute('data-date');

        // Set to hidden inputs
        document.getElementById('station_time_from').value = timeFrom;
        document.getElementById('station_date').value = date;
    });

   const addModalEl = document.getElementById('stationModal');

    // Parent-Child toggle logic with indeterminate state
    addModalEl.addEventListener('change', function (e) {
        const target = e.target;

        // Parent checkbox toggles all children
        if (target.classList.contains('parent-checkbox')) {
            const bspId = target.value;
            const children = document.querySelectorAll(`#addStationForm .bsp-child-${bspId}`);
            children.forEach(cb => cb.checked = target.checked);

            // Remove indeterminate when parent is clicked
            target.indeterminate = false;
        }

        // Child checkbox toggles parent
        if (target.classList.contains('child-checkbox')) {
            const classList = Array.from(target.classList).filter(c => c.startsWith('bsp-child-'));
            classList.forEach(cls => {
                const bspId = cls.replace('bsp-child-', '');
                const parent = document.getElementById(`bsp_${bspId}`);
                const children = document.querySelectorAll(`#addStationForm .bsp-child-${bspId}`);

                const allChecked = [...children].every(c => c.checked);
                const someChecked = [...children].some(c => c.checked);

                parent.checked = allChecked;
                parent.indeterminate = !allChecked && someChecked; // Show dash if some children selected
            });
        }
    });

    const addStationForm = document.getElementById('addStationForm');

    addStationForm.addEventListener('submit', function (e) {
        e.preventDefault(); // Stop native form submission

        const checkboxes = document.querySelectorAll('#addStationForm input[name="tour_packages[]"]');
        const generalCheckboxes = document.querySelectorAll('#addStationForm input[name="general_packages[]"]');
        const errorDiv = document.getElementById('tourPackageError');

        // Check if any checkbox from either group is checked
        const anyChecked =
            Array.from(checkboxes).some(cb => cb.checked) ||
            Array.from(generalCheckboxes).some(cb => cb.checked);

        if (!anyChecked) {
            errorDiv.style.display = 'block';
            return; // ❌ Stop here if no box is checked
        } else {
            errorDiv.style.display = 'none';
        }

        let formData = new FormData(this);
        showLoading();

        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': formData.get('_token'),
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();

            if (data.success) {
                // Remove 'No stations' row if exists
                let noDataRow = document.querySelector('#stationTableBody tr td[colspan]');
                if (noDataRow) {
                    noDataRow.parentElement.remove();
                }

                // Parse package names from response
                let assignedPackages = [];
                if (data.station.tour_packages) {
                    try {
                        assignedPackages = JSON.parse(data.station.tour_packages);
                    } catch (e) {
                        assignedPackages = [];
                    }
                }

                // Build package badges
                const packageBadges = (data.package_names && data.package_names.length > 0)
                    ? data.package_names.map(name => `<span class="badge bg-info me-1 mb-1">${name}</span>`).join('')
                    : '<span class="text-muted">No Packages Assigned</span>';

                // Build new row
                let row = `
                    <tr data-id="${data.station.id}">
                        <td>${data.station.name}</td>
                        <td>${packageBadges}</td>
                        <td>${data.station.status}</td>
                        <td>
                            <button 
                                class="btn btn-warning btn-sm editStationBtn"
                                data-id="${data.station.id}"
                                data-name="${data.station.name}"
                                data-status="${data.station.status}"
                                data-packages='${data.station.tour_packages ?? "[]"}'
                                data-url="${data.updateUrl}"
                                data-bs-toggle="modal" 
                                data-bs-target="#editStationModal">
                                <i class="bi bi-pencil me-1"></i> Edit
                            </button>
                            <button 
                                class="btn btn-danger btn-sm deleteBtn"
                                data-id="${data.station.id}"
                                data-url="${data.deleteUrl}">
                                <i class="bi bi-trash me-1"></i> Delete
                            </button>
                        </td>
                    </tr>
                `;

                document.querySelector("#stationTableBody").insertAdjacentHTML('beforeend', row);

                showSuccessMessage("Station added successfully!");
                addStationForm.reset();
                bootstrap.Modal.getInstance(document.getElementById('stationModal')).hide();

                //  NEW: Check hidden inputs — call loadBoardData if both are filled
                const timeFrom = document.getElementById('station_time_from')?.value?.trim();
                const date = document.getElementById('station_date')?.value?.trim();
                if (timeFrom && date && typeof loadBoardData === 'function') {
                    loadBoardData();
                }

            } else {
                Swal.fire('Error', data.message || 'Error saving station', 'error');
            }
        })
        .catch(err => {
            hideLoading();
            console.error(err);
            Swal.fire('Error', 'Something went wrong while saving station', 'error');
        });
    });
   
    // Fill the Edit modal whenever it is about to show
    const editModalEl = document.getElementById('editStationModal');

    function safeParse(json) {
        try { return JSON.parse(json || '[]'); } catch { return []; }
    }

    editModalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget; // the button that opened the modal
        if (!button) return;

        const stationId = button.dataset.id || '';
        const stationName = button.dataset.name || '';
        const stationStatus = button.dataset.status || '';
        const updateUrl = button.dataset.url || '';
        const selected = safeParse(button.dataset.packages); // array of tour_package IDs

        // Set form action + values
        const form = document.getElementById('editStationForm');
        form.action = updateUrl;
        document.getElementById('editStationId').value = stationId;
        document.getElementById('editStationName').value = stationName;
        document.getElementById('editStationStatus').value = stationStatus;

        // Reset all checkboxes first
        document.querySelectorAll('#editStationForm input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });

        // Pre-check children (tour packages)
       const selectedArray = Array.isArray(selected)
            ? selected
            : (selected ? [selected] : []);

        const selectedSet = new Set(selectedArray.map(String));
        document.querySelectorAll('#editStationForm input.child-checkbox').forEach(cb => {
            if (selectedSet.has(cb.value)) {
                cb.checked = true;
            }
        });

        // Pre-check parent checkboxes only if all children are checked
        document.querySelectorAll('#editStationForm input.parent-checkbox').forEach(parent => {
            const bspId = parent.value;
            const children = document.querySelectorAll(`#editStationForm .bsp-child-${bspId}`);
            const childrenArray = Array.from(children);
            parent.checked = childrenArray.length && childrenArray.every(c => c.checked);
        });

         // Pre-check general packages
        document.querySelectorAll('#editStationForm input[name="general_packages[]"]').forEach(cb => {
            if (selectedSet.has(cb.value)) {
                cb.checked = true;
            }
        });
    });

    // ----------------------------
    // Parent-Child + General Sync (EDIT MODAL)
    // ----------------------------
    editModalEl.addEventListener('change', function (e) {
        const target = e.target;

        // -----------------------------
        // 1️ Parent checkbox toggles children
        // -----------------------------
        if (target.classList.contains('parent-checkbox')) {
            const bspId = target.value;
            const children = document.querySelectorAll(`#editStationForm .bsp-child-${bspId}`);

            children.forEach(cb => {
                cb.checked = target.checked;

                // Sync with General Packages
                const general = document.querySelector(`#editStationForm input[name="general_packages[]"][value="${cb.value}"]`);
                if (general) general.checked = target.checked;
            });
        }

        // -----------------------------
        // 2️ Child checkbox change
        // -----------------------------
        if (target.classList.contains('child-checkbox')) {

            // Sync with General Packages
            const general = document.querySelector(`#editStationForm input[name="general_packages[]"][value="${target.value}"]`);
            if (general) general.checked = target.checked;

            // Update parent state
            const classList = Array.from(target.classList).filter(c => c.startsWith('bsp-child-'));

            classList.forEach(cls => {
                const bspId = cls.replace('bsp-child-', '');
                const parent = document.getElementById(`edit_bsp_${bspId}`);
                const children = document.querySelectorAll(`#editStationForm .bsp-child-${bspId}`);

                const allChecked = [...children].every(c => c.checked);
                const someChecked = [...children].some(c => c.checked);

                parent.checked = allChecked;
                parent.indeterminate = !allChecked && someChecked;
            });
        }

        // -----------------------------
        // 3️ General package change
        // -----------------------------
        if (target.name === "general_packages[]") {

            // Sync with Board Station children
            const children = document.querySelectorAll(`#editStationForm input.child-checkbox[value="${target.value}"]`);

            children.forEach(cb => {
                cb.checked = target.checked;

                // Update parent state
                const match = cb.className.match(/bsp-child-(\d+)/);
                if (!match) return;

                const bspId = match[1];
                const parent = document.getElementById(`edit_bsp_${bspId}`);
                const allChildren = document.querySelectorAll(`#editStationForm .bsp-child-${bspId}`);

                const allChecked = Array.from(allChildren).every(c => c.checked);
                const someChecked = Array.from(allChildren).some(c => c.checked);

                parent.checked = allChecked;
                parent.indeterminate = !allChecked && someChecked;
            });
        }
    });


    // Submit Edit Station Form with Validation
    const editStationForm = document.getElementById('editStationForm');

    editStationForm.addEventListener('submit', function (e) {
        e.preventDefault();

        // Collect all child checkboxes
        const childCheckboxes = document.querySelectorAll('#editStationForm input.child-checkbox');
        const generalCheckboxes = document.querySelectorAll('#editStationForm input[name="general_packages[]"]');
    
        const errorDiv = document.getElementById('editTourPackageError');

        const anyChecked =
            Array.from(childCheckboxes).some(cb => cb.checked) ||
            Array.from(generalCheckboxes).some(cb => cb.checked);

        if (!anyChecked) {
            errorDiv.style.display = 'block';
            return; //  Stop here if no box is checked
        } else {
            errorDiv.style.display = 'none';
        }

        // Create FormData manually to include only checked children
        let formData = new FormData(this);

        showLoading();

        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': formData.get('_token'),
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            hideLoading();

            if (data.success) {
                // Find and update the existing row
                const row = document.querySelector(`#stationTableBody tr[data-id="${data.station.id}"]`);
                if (row) {
                    // Build package badges (names)
                    const packageBadges = (data.package_names && data.package_names.length > 0)
                        ? data.package_names.map(name => `<span class="badge bg-info me-1 mb-1">${name}</span>`).join('')
                        : '<span class="text-muted">No Packages Assigned</span>';

                    //  Update row columns
                    row.querySelector('td:nth-child(1)').textContent = data.station.name;
                    row.querySelector('td:nth-child(2)').innerHTML = packageBadges;
                    row.querySelector('td:nth-child(3)').textContent = data.station.status;

                    // Update edit button data attributes
                    const editBtn = row.querySelector('.editStationBtn');
                    editBtn.dataset.name = data.station.name;
                    editBtn.dataset.status = data.station.status;
                    editBtn.dataset.packages = data.station.tour_packages;
                    editBtn.dataset.url = data.updateUrl;
                }

                showSuccessMessage("Station updated successfully!");
                bootstrap.Modal.getInstance(document.getElementById('editStationModal')).hide();
            } else {
                Swal.fire('Error', data.message || 'Error updating station', 'error');
            }
        })
        .catch(err => {
            hideLoading();
            console.error(err);
            Swal.fire('Error', 'Something went wrong while updating station', 'error');
        });
    });


    // Success message
    function showSuccessMessage(message) {
        const msgBox = document.getElementById('successMessage');
        msgBox.innerText = message;
        msgBox.classList.remove('d-none');

        // auto-hide after 3 seconds
        setTimeout(() => {
            msgBox.classList.add('d-none');
        }, 3000);
    }

    // Handle delete button click
    document.addEventListener('click', function (e) {
        if (e.target.closest('.deleteBtn')) {
            let btn = e.target.closest('.deleteBtn');
            let stationId = btn.dataset.id;
            let deleteUrl = btn.dataset.url.replace('__id__', stationId);

            Swal.fire({
                title: "Are you sure?",
                text: "This action cannot be undone!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#d33",
                cancelButtonColor: "#6c757d",
                confirmButtonText: "Yes, delete it!"
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    fetch(deleteUrl, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            hideLoading();
                            // Remove row from table
                            btn.closest('tr').remove();

                            Swal.fire({
                                title: "Deleted!",
                                text: "Station has been deleted.",
                                icon: "success",
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire("Error", data.message || "Unable to delete station", "error");
                        }
                    })
                    .catch(err => {
                        Swal.fire("Error", "Something went wrong!", "error");
                        console.error(err);
                    });
                }
            });
        }
    });

    // Enable drag & drop sorting
    $("#stationTableBody").sortable({
        placeholder: "ui-state-highlight",
        update: function(event, ui) {
            let order = [];
            $("#stationTableBody tr").each(function(index, element) {
                order.push($(element).find('.editStationBtn').data('id'));
            });

            // Send new order to server
            $.ajax({
                url: updateStationOrderUrl,
                method: "POST",
                data: {
                    _token: csrfToken,
                    order: order
                },
                success: function(response) {
                    if (response.success) {
                        $("#successMessage").text("Station order updated successfully!")
                            .removeClass("d-none");
                        setTimeout(() => $("#successMessage").addClass("d-none"), 3000);
                    }
                }
            });
        }
    }).disableSelection();

    // When Parent Checkbox is clicked
    document.querySelectorAll('.parent-checkbox').forEach(parent => {
        parent.addEventListener('change', function () {
            const bspId = this.id.replace('bsp_', '');
            const childCheckboxes = document.querySelectorAll('.bsp-child-' + bspId);
            childCheckboxes.forEach(child => {
                child.checked = this.checked;
            });
        });
    });

    // When Child Checkbox changes (update parent state)
    document.querySelectorAll('.child-checkbox').forEach(child => {
        child.addEventListener('change', function () {
            const match = this.className.match(/bsp-child-(\d+)/);
            if (!match) return;

            const bspId = match[1];
            const parent = document.getElementById('bsp_' + bspId);
            const allChildren = document.querySelectorAll('.bsp-child-' + bspId);
            const allChecked = Array.from(allChildren).every(c => c.checked);
            const someChecked = Array.from(allChildren).some(c => c.checked);

            parent.checked = allChecked;
            parent.indeterminate = !allChecked && someChecked;
        });
    });

     // Select / Deselect All
    $('#selectAllPackages').on('click', () => $('.pkg-checkbox').prop('checked', true));
    $('#deselectAllPackages').on('click', () => $('.pkg-checkbox').prop('checked', false));

});

// Edit specific station name + packages start
let stationDefaultPackages = [];
let stationPkgXhr = null;       // tracks in-flight override request
let stationPkgInteracted = false; // true once user touches a checkbox

$(document).on('click', '.edit-station-btn', function () {

    const modal = $('#editSpecificStationModal');

    // Abort any previous in-flight override request
    if (stationPkgXhr) {
        stationPkgXhr.abort();
        stationPkgXhr = null;
    }
    stationPkgInteracted = false;

    const stationId = $(this).data('station-id');
    const stationName = $(this).data('station-name');
    const stationSpecific = $(this).data('station-specific');
    const date = $(this).data('date');
    const time = $(this).data('time-from');

    modal.find('input[name="station_id"]').val(stationId);
    modal.find('input[name="station_name"]').val(stationName);
    modal.find('input[name="station_specific"]').val(stationSpecific);
    modal.find('input[name="date"]').val(date);
    modal.find('input[name="time_from"]').val(time);

    // Store station default packages
    stationDefaultPackages = ($(this).data('packages') || '').toString().split(',').filter(Boolean);

    // Reset all checkboxes then pre-check the station defaults immediately
    modal.find('.station-specific-pkg-cb').prop('checked', false);
    stationDefaultPackages.forEach(function (pkgId) {
        modal.find('.station-specific-pkg-cb[value="' + pkgId + '"]').prop('checked', true);
    });

    // Load specific override — only applies if user has not yet interacted
    stationPkgXhr = $.get(specificStationsPackagesUrl, { station_id: stationId, date: date, time_from: time }, function (res) {
        stationPkgXhr = null;
        if (stationPkgInteracted) return; // user already changed checkboxes — don't overwrite
        if (res.packages && res.packages.length > 0) {
            modal.find('.station-specific-pkg-cb').prop('checked', false);
            res.packages.forEach(function (pkgId) {
                modal.find('.station-specific-pkg-cb[value="' + pkgId + '"]').prop('checked', true);
            });
        }
    });

    $('#editStationSlotInfo').text(
        formatSlotDateTime(date, time, timezone)
    );

    modal.modal('show');
});

// Once user touches any package checkbox, abort the pending request and lock out override
$('#editSpecificStationModal').on('change', '.station-specific-pkg-cb', function () {
    stationPkgInteracted = true;
    if (stationPkgXhr) {
        stationPkgXhr.abort();
        stationPkgXhr = null;
    }
});


$('#editSpecificStationForm').on('submit', function (e) {
    e.preventDefault();
    showLoading();

    const form = $(this);
    const stationId = form.find('input[name="station_id"]').val();
    const date = form.find('input[name="date"]').val();
    const timeFrom = form.find('input[name="time_from"]').val();
    const selectedPackages = form.find('.station-specific-pkg-cb:checked').map(function () {
        return $(this).val();
    }).get();

    // Run name update and package update in parallel
    const nameReq = $.ajax({
        url: specificStationsUpdateNameUrl,
        type: "POST",
        data: form.serialize(),
    });

    const packageData = { station_id: stationId, date: date, time_from: timeFrom, _token: $('meta[name="csrf-token"]').attr('content') };
    selectedPackages.forEach(function (id, i) { packageData['packages[' + i + ']'] = id; });

    const pkgReq = $.ajax({
        url: specificStationsUpdatePackagesUrl,
        type: "POST",
        data: packageData,
    });

    $.when(nameReq, pkgReq).done(function (nameRes, pkgRes) {
        hideLoading();
        if (nameRes[0].success && pkgRes[0].success) {
            $('#editSpecificStationModal').modal('hide');
            showToast('Updated successfully', 'success');
            loadBoardData();
            if (currentDate) {
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
            }
        }
    }).fail(function () {
        hideLoading();
        toastr.error("Something went wrong!");
    });
});
// Edit specific station name + packages end

// Edit specific guide capacity start
let currentCapacity = 1;
const MIN_CAPACITY = 1;

$(document).on('click', '.edit-guide-capacity-btn', function () {

    let g = null;

    const guides = $(this).data('guides');

    // CASE 1: bulk / station-level edit
    if (Array.isArray(guides) && guides.length > 0) {
        g = guides[0];
    }

    // CASE 2: single guide edit (your second image)
    if (!g) {
        const guideId = $(this).data('guide-id');
        if (!guideId) return;

        g = {
            id: guideId,
            name: $(this).data('guide-name'),
            capacity: $(this).data('capacity')
        };
    }

    // ---- populate modal ----
    currentCapacity = parseInt(g.capacity, 10) || 1;

    $('#editGuideId').val(g.id);
    $('#editGuideName').text(g.name);
    $('#editGuideCapacityDisplay').text(currentCapacity);
    $('#editGuideCapacityModal .capacity-display').text(currentCapacity);

    const date = $(this).data('date');
    const time = $(this).data('time-from');
    const timezone = window.appTimezone || 'UTC';

    $('#editSlotId').val($(this).data('slot-id') || '');
    $('#editStationId').val($(this).data('station-id') || '');
    $('#editTimeFrom').val(time || '');
    $('#editDate').val(date || '');

    if (date && time) {
        $('#editGuideSlotInfo').text(
            formatSlotDateTime(date, time, timezone)
        );
    }

    $('#editGuideCapacityModal').modal('show');
});


 // Plus / Minus buttons
$(document).on('click', '.capacity-plus', function () {
    currentCapacity++;
    $(this).siblings('.capacity-display').text(currentCapacity);
});

/* ---------------------------
 | MINUS
 --------------------------- */
$(document).on('click', '.capacity-minus', function () {
    if (currentCapacity > MIN_CAPACITY) {
        currentCapacity--;
        $(this).siblings('.capacity-display').text(currentCapacity);
    }
});

$('#saveGuideCapacityBtn').on('click', function () {

    if (currentCapacity < 1) {
        Swal.fire({
            icon: 'warning',
            title: 'Invalid Capacity',
            text: 'Capacity must be greater than 0',
            confirmButtonText: 'OK',
        });
        return;
    }

    const payload = {
        guide_id: $('#editGuideId').val(),
        slot_id: $('#editSlotId').val(),
        station_id: $('#editStationId').val(),
        time_from: $('#editTimeFrom').val(),
        date: $('#editDate').val(),
        capacity: currentCapacity
    };

    showLoading();

    $.post(updateGuideCapacityUrl, payload)
        .done(() => {
            $('#editGuideCapacityModal').modal('hide');
            showToast('Updated successfully', 'success');
            loadBoardData();
            if (currentDate) {
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
            }
        })
        .fail(() => {
            showToast('Failed to update capacity', 'error');
        });
});

// Edit specific guide capacity end

function formatSlotDateTime(date, time, timezone) {
    const formattedDate = DateTime
        .fromISO(date, { zone: timezone })
        .toFormat('LLL dd yyyy');

    const formattedTime = DateTime
        .fromFormat(time, 'HH:mm:ss', { zone: timezone })
        .toFormat('hh:mm a');

    return `${formattedDate} • ${formattedTime}`;
}

// Bulk capacity update start
$(document).on('click', '.bulk-capacity-btn', function () {

    const slotId = $(this).data('slot-id');
    const date = $(this).data('date');
    const time = $(this).data('time-from');

    currentCapacity = 15;
     $('#bulkCapacityModal .capacity-display')
        .text(currentCapacity);

    $('#bulkSlotId').val(slotId);
    $('#bulkDate').val(date);
    $('#bulkTimeFrom').val(time);

    $('#bulkCapacitySlotInfo').text(
        formatSlotDateTime(date, time, timezone)
    );

    $('#bulkCapacityModal').modal('show');
});

$('#saveBulkCapacityBtn').on('click', function () {

    const payload = {
        slot_id: $('#bulkSlotId').val(),
        date: $('#bulkDate').val(),
        time_from: $('#bulkTimeFrom').val(),
        capacity: currentCapacity
    };

    showLoading();

    $.post(updateBulkCapacityUrl, payload)
        .done((res) => {
            $('#bulkCapacityModal').modal('hide');
            if (res.status === 'warning') {
                showToast(res.message, 'warning');
                return;
            }

            // success
            showToast(res.message || 'Bulk capacity updated', 'success');
            loadBoardData();
        })
        .fail((xhr) => {

            // 404 warning from backend
            if (xhr.status === 404 && xhr.responseJSON?.status === 'warning') {
                showToast(xhr.responseJSON.message, 'warning');
                return;
            }

            showToast('Failed to update capacity', 'error');
        })
        .always(() => {
            hideLoading(); // ALWAYS hide loader
        });
});

document.addEventListener("click", function (e) {

    if (e.target.closest(".unlock-station-btn")) {

        const btn = e.target.closest(".unlock-station-btn");

        const stationId = btn.dataset.stationId;
        const timeFrom = btn.dataset.timeFrom;
        const date = btn.dataset.date;

        Swal.fire({
            title: "Unlock Station?",
            text: "This will reopen this station.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Yes, Unlock",
            cancelButtonText: "Cancel",
            target: '#guideManagementModal'
        }).then((result) => {

            if (!result.isConfirmed) return;

            showLoading();

            fetch(unlockStationUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrfToken
                },
                body: JSON.stringify({
                    station_id: stationId,
                    time_from: timeFrom,
                    date: date
                })
            })
            .then(res => res.json())
            .then(data => {

                hideLoading();

                if (data.success) {

                    showToast(data.message, "success");
                    if (currentDate) {
                        loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                    }

                } else {
                    showToast(data.message || "Unlock failed", "error");
                }

            })
            .catch(err => {
                hideLoading();
                showToast("Something went wrong", "error");
                console.error(err);
            });

        });
    }

});




