// ---------- Globals ----------
window.availableGuides = []; // filled when modal loads

// ---------- Event bindings ----------
$(document).off('click', '.addGuideOptbtn').on('click', '.addGuideOptbtn', (evt) => {
    appendGuideOption(evt);
});
$(document).off('click', '.removeGuideOptbtn').on('click', '.removeGuideOptbtn', (evt) => {
    removeGuideOption(evt);
});
$(document).on('input change', '.guide-tourists', (e) => {
    validateInput($(e.target));
    updateGuideCapacityInfo();
});
$(document).off('change', '#assignGuidesModal .tour-guide').on('change', '#assignGuidesModal .tour-guide', function () {
    // Keep duplicates prevented and refresh capacity/balance when guide selection changes
    removeDuplicateSelections();
    restoreOptionFullText($(this));
    updateGuideCapacityInfo();
    balanceTouristsAcrossGuides();

    const $row = $(this).closest('.guide-opt');
    const $infants = $row.find('.guide-infants');

    if (!$infants.val() || parseInt($infants.val()) === 0) {
        $infants.val($('#modalPendingInfants').text());
    }

    updatePendingInfants();
    balanceInfantsAcrossGuides();
});
$(document).on('input change', '.guide-infants', function () {
    balanceInfantsAcrossGuides(this);
    validateInfants($(this));
    updatePendingInfants();
});

function validateInfants($input) {
    const val = parseInt($input.val()) || 0;
    const total = parseInt($('#totalInfants').val()) || 0;

    let others = 0;
    $('.guide-infants').not($input).each(function () {
        others += parseInt($(this).val()) || 0;
    });

    const isValid = (val >= 0 && (val + others) <= total);

    $input
        .removeClass('is-invalid is-valid')
        .addClass(isValid ? 'is-valid' : 'is-invalid');

    return isValid;
}

function updatePendingInfants() {
    const total = parseInt($('#totalInfants').val()) || 0;
    let assigned = 0;

    $('.guide-infants').each(function () {
        assigned += parseInt($(this).val()) || 0;
    });

    $('#modalPendingInfants').text(Math.max(0, total - assigned));
}

function balanceInfantsAcrossGuides(changedEl) {
    const totalInfants = parseInt($('#totalInfants').val()) || 0;
    const inputs = $('.guide-infants').toArray();

    let assigned = 0;
    inputs.forEach(el => {
        assigned += parseInt(el.value) || 0;
    });

    if (assigned <= totalInfants) return;

    let overflow = assigned - totalInfants;

    // Reduce from OTHER guides first
    for (let el of inputs) {
        if (el === changedEl) continue;
        if (overflow <= 0) break;

        let cur = parseInt(el.value) || 0;
        let reduce = Math.min(cur, overflow);
        el.value = cur - reduce;
        overflow -= reduce;
    }

    // Final safety
    if (overflow > 0) {
        let cur = parseInt(changedEl.value) || 0;
        changedEl.value = Math.max(0, cur - overflow);
    }
}


// ---------- AJAX loader ----------
function getAssignedTourGuides(bookingId) {
    var newGetAssignedGuidesURL = getAssignedGuidesURL.replace('_BOOKING_ID_', bookingId);
    showLoading();
    $.get(newGetAssignedGuidesURL)
        .done(function (response) {
            // store guides returned by API in global local var
            window.availableGuides = Array.isArray(response.guides) ? response.guides : [];
            renderAssignedGuidesDetails(response, bookingId);
        })
        .fail(showSubmissionError)
        .always(hideLoading);
}

// ---------- Render main modal content ----------
function renderAssignedGuidesDetails(data, bookingId) {
    const bookingInfo = data.bookingInfo;
    const assignedGuides = data.assignedGuides || [];
    const guides = data.guides || [];

    // store globally for use in updateGuideCapacityInfo()
    window.assignedGuides = assignedGuides;

    // --- Booking info section ---
    let bookingInfoHtml = `
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center">
            <div class="w-1/2 text-sm text-[#020817] font-bold">Booking ID:</div>
            <div class="w-1/2 text-sm text-[#020817] text-right" id="modalBookingId" style="color:#0d6efd;">#${bookingInfo.bookingId}</div>
            <input type="hidden" id="booking-id" value="${bookingId}">
            <input type="hidden" id="totalTourists" value="${bookingInfo.tourists}" />
            <input type="hidden" id="totalInfants" value="${bookingInfo.infants || 0}" />
            <input type="hidden" id="booking-package-id" value="${bookingInfo.tour_package_id}" />
            <input type="hidden" id="include-in-operation-board" value="${bookingInfo.include_in_operation_board}" />
        </div>
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center"><div class="w-1/2 text-sm text-[#020817] font-bold">Customer:</div> <div class="w-1/2 text-sm text-[#020817] text-right">${bookingInfo.customer_name}</div></div>
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center"><div class="w-1/2 text-sm text-[#020817] font-bold">Tour:</div> <div class="w-1/2 text-sm text-[#020817] text-right">${bookingInfo.package_name}</div></div>
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center"><div class="w-1/2 text-sm text-[#020817] font-bold">Date:</div> <div class="w-1/2 text-sm text-[#020817] text-right">${bookingInfo.date}</div></div>
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center"><div class="w-1/2 text-sm text-[#020817] font-bold">Tourists:</div> <div class="w-1/2 text-sm text-[#020817] text-right">${bookingInfo.tourists}</div></div>
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center">
            <div class="w-1/2 text-sm text-[#020817] font-bold">Pending:</div> <span class="w-1/2 text-sm text-[#020817] font-bold text-right" id="modalPending" style="color: red; font-weight: bold;">${bookingInfo.tourists}</span>
        </div>
        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center">
            <div class="w-1/2 text-sm font-bold">Infants:</div>
            <div class="w-1/2 text-sm text-right">${bookingInfo.infants || 0}</div>
        </div>

        <div class="w-full md:w-1/2 max-w-full px-3 mb-2 flex items-center">
            <div class="w-1/2 text-sm font-bold">Pending Infants:</div>
            <div class="w-1/2 text-sm text-right">
                <span id="modalPendingInfants" class="text-blue-600 font-bold">
                    ${bookingInfo.infants || 0}
                </span>
            </div>
            
        </div>
    `;
    $("#assignGuidesModal #booking-info-div").html(bookingInfoHtml);

    // fetch flag
    const includeInOperationBoard = parseInt($('#assignGuidesModal #include-in-operation-board').val()) || 0;

    // --- Helper: render guide dropdown options ---
    const buildGuideOptions = (selectedId = null) => {
        const bookingPkgId = parseInt($('#assignGuidesModal #booking-package-id').val()) || null;

        const sortedGuides = [...guides].sort((a, b) => {
            if (a.station_name && !b.station_name) return -1;
            if (!a.station_name && b.station_name) return 1;
            return 0;
        });

        let opts = `<option value="">Select Guide</option>`;

        sortedGuides.forEach(g => {

            const capacityText = `(${g.tourists_assigned}/${g.capacity})`;
            const stationText = g.station_name
                ? ` <span class="station-name">${g.station_name}</span>`
                : "";

            // normalize tour_packages into array of numbers
            let allowedPackages = [];
            try {
                if (Array.isArray(g.tour_packages)) {
                    allowedPackages = g.tour_packages.map(x => parseInt(x));
                } else if (typeof g.tour_packages === 'string' && g.tour_packages.trim() !== '') {
                    const parsed = JSON.parse(g.tour_packages);
                    if (Array.isArray(parsed)) allowedPackages = parsed.map(x => parseInt(x));
                }
            } catch (e) {
                allowedPackages = [];
            }

            // apply validation ONLY IF includeInOperationBoard == 1
            let allowedForBooking = true;
            if (includeInOperationBoard == 1) {
                allowedForBooking =
                    bookingPkgId === null ||
                    allowedPackages.length === 0 ||
                    allowedPackages.includes(bookingPkgId);
            }

            const notForText = allowedForBooking ? '' : ' — Package not same';
            const disabledAttr = allowedForBooking ? '' : ' disabled';

            const selected = g.id == selectedId ? "selected" : "";

            opts += `<option value="${g.id}" ${selected}${disabledAttr}
                        data-packages='${JSON.stringify(allowedPackages)}'
                        data-display-name="${escapeHtml(g.guide_name)}">
                        ${escapeHtml(g.guide_name)} ${escapeHtml(capacityText)} ${stationText} ${escapeHtml(notForText)}
                    </option>`;
        });

        return opts;
    };

    // --- Build assigned guide list and form inputs ---
    let guideOptionsHtml = '';
    let assignedGuidesHtml = '';

    if (assignedGuides.length > 0) {
        $.each(assignedGuides, function (index, guide) {
            assignedGuidesHtml += `<span class="badge bg-success me-1 px-3 py-[5px] bg-[#e8ffea] rounded-[100px] border border-[#2ea135] font-semibold text-[#2ea135] text-sm inline-flex items-center gap-1">${guide.guide_name}</span>`;
            let guideOptions = buildGuideOptions(guide.guide_id);
            guideOptionsHtml += renderGuideOptionDiv(guideOptions, guide.booking_guide_id, guide.tourists, guide.infants);
        });

        guideOptionsHtml += `
            <div class="w-full text-right"><button type="button" class="btn btn-primary btn-sm addGuideOptbtn btn-primary-small ml-auto">
                <i class="fas fa-plus"></i>
            </button></div>`;
        $('#assignGuidesModal #guideOptsDiv').html(guideOptionsHtml);
    } else {
        let guideOptions = buildGuideOptions();
        renderGuideOptionDivNew(bookingInfo.tourists,bookingInfo.infants, guideOptions);
        assignedGuidesHtml = '<p>No guides assigned.</p>';
    }

    // --- Preview assigned guides ---
    $('#assignGuidesModal #selectedGuidesPreview').html(assignedGuidesHtml);

    // --- Final setup ---
    $("#assignGuidesModal").modal('show');
   
    updateGuideCapacityInfo();
    balanceTouristsAcrossGuides();
    updatePendingInfants();
    balanceInfantsAcrossGuides();
}

// ---------- Append new guide row ----------
function appendGuideOption(evt) {

    const includeInOperationBoard = parseInt($('#assignGuidesModal #include-in-operation-board').val()) || 0;

    const selectedGuideIds = getSelectedGuideIds();
    const bookingPkgId = parseInt($('#assignGuidesModal #booking-package-id').val()) || null;

    // Sort guides so station guides appear first
    const sortedGuides = [...(window.availableGuides || [])].sort((a, b) => {
        if (a.station_name && !b.station_name) return -1;
        if (!a.station_name && b.station_name) return 1;
        return 0;
    });

    let options = '<option value="">Select Guide</option>';

    for (let g of sortedGuides) {

        if (selectedGuideIds.includes(parseInt(g.id))) continue; // prevent duplicate

        const capacityText = `(${g.tourists_assigned || 0}/${g.capacity || 0})`;
        const stationText = g.station_name
            ? ` <span class="station-name">${escapeHtml(g.station_name)}</span>`
            : '';

        // Normalize tour_packages into array of numbers
        let allowedPackages = [];
        try {
            if (Array.isArray(g.tour_packages)) {
                allowedPackages = g.tour_packages.map(x => parseInt(x));
            } else if (typeof g.tour_packages === 'string' && g.tour_packages.trim() !== '') {
                const parsed = JSON.parse(g.tour_packages);
                if (Array.isArray(parsed)) allowedPackages = parsed.map(x => parseInt(x));
            }
        } catch (e) {
            allowedPackages = [];
        }

        // ------------------------------
        // APPLY / SKIP VALIDATION BASED ON includeInOperationBoard
        // ------------------------------
        let allowedForBooking = true;

        if (includeInOperationBoard == 1) {
            allowedForBooking =
                bookingPkgId === null ||
                allowedPackages.length === 0 ||
                allowedPackages.includes(bookingPkgId);
        }

        const notForText = allowedForBooking ? '' : ' — Package not same';
        const disabledAttr = allowedForBooking ? '' : ' disabled';

        options += `<option value="${g.id}" ${disabledAttr}
                        data-packages='${JSON.stringify(allowedPackages)}'
                        data-display-name="${escapeHtml(g.guide_name)}">
                        ${escapeHtml(g.guide_name)} ${escapeHtml(capacityText)} 
                        ${stationText} ${escapeHtml(notForText)}
                    </option>`;
    }

    if (options === '<option value="">Select Guide</option>') {
        options = '<option value="">No guides available</option>';
    }

    const guideOptHtml = renderGuideOptionDiv(options);
    $('#assignGuidesModal form .guide-opt:last').after(guideOptHtml);

    updateGuideCapacityInfo();
    balanceTouristsAcrossGuides();
}

// ---------- Remove guide row ----------
function removeGuideOption(evt) {
    const assignmentId = $(evt.currentTarget).data('id');
    const $row = $(evt.currentTarget).closest('.guide-opt');

    if (!assignmentId) {
        // unsaved row — simply remove if more than one row exists
        if ($('.guide-opt').length > 1) {
            $row.remove();
            updateGuideCapacityInfo();
            balanceTouristsAcrossGuides();
        } else {
            swal('Sorry!', 'At least one guide option is required.', 'warning');
        }
        return;
    }

    // saved assignment — ask server to mark deleted
    swal({
        title: 'Remove Tour Guide',
        text: 'Are you sure you want to remove this guide from this booking?',
        icon: "warning",
        buttons: {
            no: { text: 'No', value: false, className: 'btn-danger' },
            yes: { text: 'Yes', className: 'btn-success' }
        }
    }).then(function (result) {
        if (!result) return;
        const bookingId = $('#assignGuidesModal #booking-id').val();
        const postData = { guides: [{ id: assignmentId, is_deleted: 1 }] };
        const newassignTourGuideURL = assignTourGuideUrl.replace('_BOOKING_ID_', bookingId);

        showLoading();
        $.post(newassignTourGuideURL, postData)
            .done(function (resp) {
                // remove row from UI and re-balance capacities
                if ($('.guide-opt').length > 1) {
                    $row.remove();
                } else {
                    renderGuideOptionDivNew($('#assignGuidesModal #totalTourists').val());
                    
                }
                updateGuideCapacityInfo();
                balanceTouristsAcrossGuides();
                swal('Tour guide removed successfully!', 'Page will reload', 'success')
                .then(function () {
                        window.location.reload();
                });
            })
            .fail(showSubmissionError)
            .always(hideLoading);
    });
}

// ---------- Render row templates ----------
function renderGuideOptionDiv(guideOptionsHtml, bookingGuideId = '', tourists = '', infants = '') {
    const guideMeta = (window.availableGuides || []).find(g => parseInt(g.id) === parseInt(bookingGuideId)) || {};
    const prefillTourists = tourists || guideMeta.tourists_assigned || 0;
    const prefillInfants = infants || 0;
    return `<div class="flex flex-wrap -mx-3 mb-4 guide-opt align-items-end">
        <div class="w-full md:w-2/5 max-w-full px-3 position-relative">
            <label class="form-label form-custom-label">Guide</label>
            <select class="form-select tour-guide form-custom-input" name="tour_guide" style="width:100%;">${guideOptionsHtml}</select>
            <div class="invalid-feedback text-sm text-[#A32624] mt-1 hidden">Please select guide.</div>
        </div>
        <div class="w-full md:w-1/5 max-w-full px-3">
            <label class="form-label form-custom-label">Tourists</label>
            <input type="number" min="1" value="${prefillTourists}" class="form-control guide-tourists form-custom-input" name="guide_tourists" placeholder="Tourists">
            <div class="invalid-feedback text-sm text-[#A32624] mt-1 hidden">Please enter tourists.</div>
        </div>
        <div class="w-full md:w-1/5 max-w-full px-3">
                <label class="form-label form-custom-label">Infants</label>
                <input type="number" min="1" value="${prefillInfants}" class="form-control guide-infants form-custom-input" name="guide_infants" placeholder="Infants">
        </div>
        <div class="w-full md:w-1/5 max-w-full px-3">
            <label class="form-label form-custom-label w-full">&nbsp;</label>
            <button type="button" class="btn btn-danger btn-sm removeGuideOptbtn btn-danger-small" data-id="${bookingGuideId}">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>`;
}

function renderGuideOptionDivNew(defaultTourists = '', defaultInfants = '' , guideOptionsHtml = null) {
    let options = guideOptionsHtml;
    if (!options) {
        options = '<option value="">Select Guide</option>';
        for (let g of (window.availableGuides || [])) {
            const suffix = ` (${g.tourists_assigned || 0}/${g.capacity || 0})`;
            options += `<option value="${g.id}" data-display-name="${escapeHtml(g.guide_name)}">${escapeHtml(g.guide_name)}${escapeHtml(suffix)}</option>`;
        }
    }

    const html = `<div class="flex flex-wrap -mx-3 mb-4 guide-opt">
        <div class="w-full md:w-2/5 max-w-full px-3">
            <label class="form-label form-custom-label">Guide</label>
            <select class="form-select tour-guide form-custom-input" name="tour_guide" style="width:100%;">${options}</select>
            <div class="invalid-feedback text-sm text-[#A32624] mt-1 hidden">Please select guide.</div>
        </div>
        <div class="w-full md:w-1/5 max-w-full px-3">
            <label class="form-label form-custom-label">Tourists</label>
            <input type="number" min="1" value="${defaultTourists}" class="form-control guide-tourists form-custom-input" name="guide_tourists" placeholder="Tourists">
            <div class="invalid-feedback text-sm text-[#A32624] mt-1 hidden">Please enter tourists.</div>
        </div>
        <div class="w-full md:w-1/5 max-w-full px-3">
                <label class="form-label form-custom-label">Infants</label>
                <input type="number" min="1" value="${defaultInfants}" class="form-control guide-infants form-custom-input" name="guide_infants" placeholder="Infants">
        </div>
        <div class="w-full md:w-1/5 max-w-full px-3">
            <label class="form-label form-custom-label w-full">&nbsp;</label>
            <button type="button" class="btn btn-primary btn-sm addGuideOptbtn btn-primary-small">
                <i class="fas fa-plus"></i>
            </button>
        </div>
    </div>`;
    $('#assignGuidesModal #guideOptsDiv').html(html);
}

// ---------- Validation helpers ----------
function validateInput($element) {
    if (!$element.hasClass('guide-tourists')) return false;

    let entered = parseInt($element.val(), 10) || 0;
    const totalAllowed = parseInt($('#assignGuidesModal #totalTourists').val()) || 0;

    const $row = $element.closest('.guide-opt');

    // HARD STOP: cannot exceed booking total
    if (entered > totalAllowed) {
        $element
            .addClass('is-invalid')
            .removeClass('is-valid')
            .siblings('.invalid-feedback')
            .text(`Assigned tourists more than booking total (${totalAllowed}).`)
            .show();
        return false;
    }

    // Sum other rows
    let otherRows = [];
    let otherSum = 0;

    $('.guide-opt').each(function () {
        const $r = $(this);
        if ($r.is($row)) return;

        const $input = $r.find('.guide-tourists');
        const val = parseInt($input.val()) || 0;

        otherRows.push({ $input, val });
        otherSum += val;
    });

    const remaining = totalAllowed - entered;

    // Rebalance ONLY if needed
    if (otherSum > remaining) {
        let toReduce = otherSum - remaining;

        for (let r of otherRows) {
            if (toReduce <= 0) break;

            const reducible = Math.min(r.val, toReduce);
            r.val -= reducible;
            toReduce -= reducible;
            r.$input.val(r.val);
        }
    }

    // UI cleanup
    $element
        .removeClass('is-invalid')
        .addClass('is-valid')
        .siblings('.invalid-feedback')
        .hide();

    updateGuideCapacityInfo(); // keep totals + button state in sync
    return true;
}

function validateDropdown($element) {
    if ($element.is('select')) {
        const isValid = $element.val() && $element.val().length > 0;
        $element.toggleClass('is-invalid', !isValid).toggleClass('is-valid', isValid);
        $element.siblings('.invalid-feedback').toggle(!isValid);
        return isValid;
    }
    return false;
}

// ---------- Capacity and balancing ----------
function updateGuideCapacityInfo() {
    const totalAllowed = parseInt($('#assignGuidesModal #totalTourists').val()) || 0;

    // Sum of tourists entered in all rows (THIS booking only)
    let assignedSum = 0;
    $('.guide-tourists').each((_, el) => {
        const val = parseInt($(el).val()) || 0;
        assignedSum += val;
    });

    const remaining = totalAllowed - assignedSum;
    $('#modalPending').text(Math.max(0, remaining));

    // Remove any previous booking-level message
    $('#assignGuidesModal #remainingTouristsInfo').remove();

    let hasRowInvalid = false;

    $('.guide-opt').each(function () {
        const $row = $(this);
        const guideId = $row.find('.tour-guide').val();
        const currentVal = parseInt($row.find('.guide-tourists').val()) || 0;

        if (!guideId) {
            $row.find('.guide-capacity-hint').remove();
            $row.find('.guide-tourists').removeClass('is-invalid is-valid');
            return;
        }

        const guideMeta = (window.availableGuides || []).find(
            g => parseInt(g.id) === parseInt(guideId)
        ) || {};

        const capacity = parseInt(guideMeta.capacity || 0);

        // ---------------- booking-level assigned (edit case) ----------------
        const bookingAssignedGuide = (window.assignedGuides || []).find(
            g => parseInt(g.guide_id) === parseInt(guideId)
        );

        const bookingAssigned = bookingAssignedGuide
            ? parseInt(bookingAssignedGuide.tourists || 0)
            : 0;

        // ---------------- guide-level assigned (other bookings) --------------
        const totalAssignedOnGuide = parseInt(guideMeta.tourists_assigned || 0);

        // If this guide is already assigned in this booking,
        // subtract bookingAssigned to avoid double counting
        const guideAssignedOtherBookings = bookingAssigned > 0
            ? Math.max(0, totalAssignedOnGuide - bookingAssigned)
            : totalAssignedOnGuide;


        // Total already on guide BEFORE this input
        const alreadyOnGuide = guideAssignedOtherBookings + bookingAssigned;

        // ---------------- booking-level remaining ----------------
        const sumOtherRows = assignedSum - currentVal;
        const bookingRemainingIfThisRow = Math.max(0, totalAllowed - sumOtherRows);

        // ---------------- max allowed for this row ----------------
        const maxTotalAllowedForThisRow = capacity === 0
            ? bookingRemainingIfThisRow
            : Math.min(
                capacity - guideAssignedOtherBookings + bookingAssigned,
                bookingRemainingIfThisRow
            );

        // ---------------- UI hint ----------------
        $row.find('.guide-capacity-hint').remove();
        $row.append(`
            <div class="guide-capacity-hint small text-muted text-sm text-[#020817] my-2 mx-3 border border-[#F3F4F6] bg-[#F9FAFB] rounded-lg px-3 py-2 w-full text-center">
                Capacity: ${capacity}
                | Already: <span style="color:#dc3545">${guideAssignedOtherBookings}</span>
                | Incoming: <span style="color:#28a745">${currentVal}</span>
                | Total: ${guideAssignedOtherBookings + currentVal}
            </div>
        `);

        // ---------------- validation ----------------
        let rowInvalid = false;

        // Capacity exceeded?
        if (capacity <= 0 || currentVal > maxTotalAllowedForThisRow) {
            rowInvalid = true;
        }

        // Booking limit exceeded?
        if (currentVal > bookingRemainingIfThisRow) {
            rowInvalid = true;
        }

        if (rowInvalid) {
            hasRowInvalid = true;
            $row.find('.guide-tourists')
                .addClass('is-invalid')
                .removeClass('is-valid');
            $row.find('.invalid-feedback')
            .text(capacity <= 0 ? 'Guide capacity is not set.' : 'Exceeds guide capacity!')
            .show();
        } else {
            $row.find('.guide-tourists')
                .removeClass('is-invalid')
                .addClass('is-valid');
            $row.find('.invalid-feedback').hide();
        }
    });

    // ---------------- booking-level validation ----------------
    if (assignedSum > totalAllowed) {
        if ($('#assignGuidesModal #remainingTouristsInfo').length === 0) {
            $('#assignGuidesModal #guideOptsDiv').after(
                `<div id="remainingTouristsInfo" class="text-end small text-danger mb-3" style="margin-top:8px;"></div>`
            );
        }
        $('#assignGuidesModal #remainingTouristsInfo')
            .text(`Total assigned (${assignedSum}) exceeds booking tourists (${totalAllowed}).`);
        hasRowInvalid = true;
    } else {
        $('#assignGuidesModal #remainingTouristsInfo').remove();
    }

    $('#assignGuideSubmit').prop('disabled', hasRowInvalid);

    return !hasRowInvalid;
}

// simple balancing: evenly distribute across rows (keeps things predictable)
function balanceTouristsAcrossGuides() {
    const totalAllowed = parseInt($('#assignGuidesModal #totalTourists').val()) || 0;
    const rows = $('.guide-opt').toArray();
    if (rows.length === 0) return;

    const asInt = v => parseInt(v) || 0;

    // Step 1: Calculate total currently assigned
    let totalAssigned = 0;
    const rowData = rows.map(r => {
        const $r = $(r);
        const $input = $r.find('.guide-tourists');
        const val = asInt($input.val());
        totalAssigned += val;
        return { $input, val };
    });

    // ✅ Step 2: If total exceeds booking limit — reduce other guides automatically
    if (totalAssigned > totalAllowed) {
        let excess = totalAssigned - totalAllowed;

        // Try reducing from bottom-most guides first (or others except the most recently changed)
        for (let i = rowData.length - 1; i >= 0 && excess > 0; i--) {
            const rd = rowData[i];
            if (rd.val > 0) {
                const reduceBy = Math.min(rd.val, excess);
                rd.$input.val(rd.val - reduceBy);
                excess -= reduceBy;

                // Optional: highlight the adjusted input for visibility
                rd.$input.addClass('auto-adjusted');
                setTimeout(() => rd.$input.removeClass('auto-adjusted'), 600);
            }
        }
    }

    // Step 3: Recalculate after adjustments
    const assignedTotal = rows.reduce((sum, r) => sum + (asInt($(r).find('.guide-tourists').val()) || 0), 0);
    let remainingToAssign = totalAllowed - assignedTotal;

    if (remainingToAssign <= 0) {
        updateGuideCapacityInfo();
        return;
    }

    // Step 4: Compute remaining capacity
    const available = rows.map(r => {
        const $r = $(r);
        const gid = asInt($r.find('.tour-guide').val());
        const guideMeta = (window.availableGuides || []).find(g => asInt(g.id) === gid) || {};
        const capacity = asInt(guideMeta.capacity || 0);
        const alreadyAssigned = asInt(guideMeta.tourists_assigned || 0);
        const inputVal = asInt($r.find('.guide-tourists').val());
        const remainingCap = Math.max(0, capacity - alreadyAssigned - inputVal);

        return { $input: $r.find('.guide-tourists'), gid, remainingCap, current: inputVal };
    });

    // Step 5: Auto-distribute remaining tourists only to unfilled guides
    const unfilled = available.filter(a => a.current === 0 && a.gid);

    if (unfilled.length === 0) {
        updateGuideCapacityInfo();
        return;
    }

    // Step 6: Round-robin distribute remaining tourists
    let i = 0;
    while (remainingToAssign > 0 && unfilled.length > 0) {
        const a = unfilled[i % unfilled.length];
        if (a.remainingCap > 0) {
            a.$input.val(asInt(a.$input.val()) + 1);
            a.remainingCap--;
            remainingToAssign--;
        }
        i++;
        if (i > 10000) break; // safeguard
    }

    // Step 7: Finalize validation and UI
    unfilled.forEach(a => validateInput(a.$input));
    updateGuideCapacityInfo();
}


// ---------- Submit handler ----------
$(document).off('click', '#assignGuideSubmit').on('click', '#assignGuideSubmit', function () {
    let isValid = true;
    const selectedGuideIds = [];

    $('.tour-guide').each((_, el) => {
        const $dd = $(el);
        if (!validateDropdown($dd)) isValid = false;
        const val = $dd.val();
        if (val) {
            if (selectedGuideIds.includes(val)) {
                $dd.addClass('is-invalid');
                $dd.siblings('.invalid-feedback').text('Duplicate selection not allowed.').show();
                isValid = false;
            } else {
                selectedGuideIds.push(val);
            }
        }
    });

    let sum = 0;
    let zeroTouristGuide = null;

    $('.guide-opt').each((_, el) => {
        const $row = $(el);
        const guideId = $row.find('.tour-guide').val();
        const tourists = parseInt($row.find('.guide-tourists').val()) || 0;

        if (guideId) {
            // 🚫 FINAL RULE: minimum 1 tourist per selected guide
            if (tourists < 1) {
                zeroTouristGuide = $row;
                return false; // break loop
            }
        }

        if (!validateInput($row.find('.guide-tourists'))) {
            isValid = false;
        }

        sum += tourists;
    });

    if (zeroTouristGuide) {
        const $input = zeroTouristGuide.find('.guide-tourists');
        $input
            .addClass('is-invalid')
            .removeClass('is-valid')
            .siblings('.invalid-feedback')
            .text('At least 1 tourist is required for the selected guide.')
            .show();

        $('#assignGuidesModal .modal-body').animate({
            scrollTop: $input.offset().top - $('#assignGuidesModal .modal-body').offset().top
        }, 300);

        return false;
    }

    const totalAllowed = parseInt($('#assignGuidesModal #totalTourists').val()) || 0;
    if (!isValid) return false;
    if (sum > totalAllowed) {
        swal('Sorry!', `Total assigned tourists (${sum}) exceeds allowed (${totalAllowed}).`, 'warning');
        return false;
    }

    const includeInOperationBoard = parseInt($('#assignGuidesModal #include-in-operation-board').val()) || 0;

    if(includeInOperationBoard == 1) {

        // ✅ NEW VALIDATION: Check if any selected guide has no station assigned
        for (const gid of selectedGuideIds) {
            const guideMeta = (window.availableGuides || []).find(g => parseInt(g.id) === parseInt(gid));
            if (!guideMeta) continue; // safety
            if (!guideMeta.station_name || guideMeta.station_name.trim() === '') {
                const guideName = guideMeta.guide_name || 'This guide';
                swal(
                    'Guide Not Assigned',
                    `${guideName} is not assigned to any station. Please assign a station first.`,
                    'warning'
                );
                return false; // ❌ Stop submission
            }

        }
        
            // ---------- NEW: Ensure selected guides support the booking's package ----------
        const bookingPkgId = parseInt($('#assignGuidesModal #booking-package-id').val()) || null;
        if (bookingPkgId !== null) {
            for (const gid of selectedGuideIds) {
                const guideMeta = (window.availableGuides || []).find(g => parseInt(g.id) === parseInt(gid));
                if (!guideMeta) continue; // safety

                // normalize tour_packages
                let allowedPackages = [];
                try {
                    if (Array.isArray(guideMeta.tour_packages)) allowedPackages = guideMeta.tour_packages.map(x => parseInt(x));
                    else if (typeof guideMeta.tour_packages === 'string' && guideMeta.tour_packages.trim() !== '') {
                        const parsed = JSON.parse(guideMeta.tour_packages);
                        if (Array.isArray(parsed)) allowedPackages = parsed.map(x => parseInt(x));
                    }
                } catch (e) {
                    allowedPackages = [];
                }

                // If guide has explicit package list and booking package not present => reject
                if (allowedPackages.length > 0 && !allowedPackages.includes(bookingPkgId)) {
                    const guideName = guideMeta.guide_name || 'This guide';
                    swal(
                        'Guide Not Available For Package',
                        `${guideName} does not serve the selected tour/package. Please choose another guide.`,
                        'warning'
                    );
                    return false;
                }
            }
        }
    }

    // ---------- Continue normal submit ----------
    const guidesPayload = [];
    $('.guide-opt').each((_, el) => {
        const $r = $(el);
        const gid = $r.find('.tour-guide').val();
        const tourists = parseInt($r.find('.guide-tourists').val()) || 0;
        const infants = parseInt($r.find('.guide-infants').val()) || 0;
        if (gid) guidesPayload.push({ guide_id: gid, tourists , infants});
    });

    const bookingId = $('#assignGuidesModal #booking-id').val();
    const postData = { booking_id: bookingId, guides: guidesPayload };
    const newassignTourGuideURL = assignTourGuideUrl.replace('_BOOKING_ID_', bookingId);

    showLoading();
    $.post(newassignTourGuideURL, postData)
        .done(function (resp) {
            swal('Tour Guides assigned successfully.', 'Page will reload', 'success')
            .then(function () {
                    window.location.reload();
                });
        })
        .fail(showSubmissionError)
        .always(hideLoading);
});

// ---------- Utility helpers ----------
function getSelectedGuideIds() {
    const ids = [];
    $('#assignGuidesModal .tour-guide').each(function () {
        const v = $(this).val();
        if (v) ids.push(parseInt(v));
    });
    return ids;
}
function removeDuplicateSelections() {
    const seen = new Set();
    $('#assignGuidesModal .tour-guide').each(function () {
        const val = $(this).val();
        if (!val) return;
        if (seen.has(val)) {
            $(this).val('').trigger('change.select2'); // clear second duplicate
        } else {
            seen.add(val);
        }
    });
}
function restoreOptionFullText($select) {
    // ensure selected option text shows display-name (prevents 'undefined' in UI)
    const $opt = $select.find('option:selected');
    const display = $opt.data('display-name');
    if (display) {
        // set option text to display name (Select2 will show it)
        $opt.text(display + ($opt.text().match(/\(/) ? ' ' + $opt.text().slice($opt.text().indexOf('(')) : ''));
    }
}
// small HTML escaper
function escapeHtml(s) {
    if (s === null || s === undefined) return '';
    return String(s).replace(/[&<>"'`=\/]/g, function (c) {
        return {
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
            "'": '&#39;', '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'
        }[c];
    });
}

