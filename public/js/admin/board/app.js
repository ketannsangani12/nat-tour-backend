// ---------- Helpers ----------
function toArray(maybeArrayOrObject) {
    if (Array.isArray(maybeArrayOrObject)) return maybeArrayOrObject;
    if (maybeArrayOrObject && typeof maybeArrayOrObject === 'object') return Object.values(maybeArrayOrObject);
    return [];
}

// unified getter for a booking's assigned guide id
function getAssignedGuideId(booking) {
    return booking?.assignedGuide ?? booking?.assigned_guide ?? booking?.assigned_guide_id ?? booking?.guide_id ?? null;
}

// unified getter for a station's assigned guide ids (supports id list or nested guide objects)
function getStationAssignedGuides(station) {
    // explicit arrays of ids first
    const explicit = station?.assignedGuides ?? station?.assigned_guides;
    if (Array.isArray(explicit) && explicit.length) return explicit;

    // nested guides (array or object) -> ids
    const nested = station?.guides;
    if (Array.isArray(nested)) return nested.map(g => (typeof g === 'object' ? g.id : g));
    if (nested && typeof nested === 'object') return Object.values(nested).map(g => (typeof g === 'object' ? g.id : g));

    return [];
}

function asInt(v) { return (typeof v === 'string') ? parseInt(v, 10) : v; }

// ---------- Class ----------
class TourismBoard {
    constructor({ guides = [], stations = [], bookings = [] , absentBookings = [], notAvailableGuides = [] } = {}) {
        // Normalize incoming (top-level)
        this.guides   = this._normalizeGuides(toArray(guides));
        this.bookings = this._normalizeBookings(toArray(bookings));
        this.absentBookings = this._normalizeBookings(toArray(absentBookings));
        this.stations = this._normalizeStations(toArray(stations));
        this.notAvailableGuides = this._normalizeNotAvailableGuides(toArray(notAvailableGuides));

        // Ingest nested station.guides / station.bookings into global state
        this._ingestStationEntities();

        // Make guides aware of their current bookings
        this._recalculateGuideState();

        this.sortables = [];
        window.tourismBoard = this;
        this.copiedGuide = null;
    }

    init() {
        this.refresh();
        this.initializeDragAndDrop();
        this.initializeGuidesSearch();
    }

        // add helper
      resetCopiedBooking() {
        this.copiedBooking = null;
        if (typeof this.refresh === 'function') this.refresh();
      }

    updateData({ guides = [], stations = [], bookings = [], absentBookings = [], notAvailableGuides = [] } = {}) {
        this.guides   = this._normalizeGuides(toArray(guides));
        this.bookings = this._normalizeBookings(toArray(bookings));
        this.absentBookings = this._normalizeBookings(toArray(absentBookings));
        this.notAvailableGuides = this._normalizeNotAvailableGuides(toArray(notAvailableGuides));
        this.stations = this._normalizeStations(toArray(stations));

        this._ingestStationEntities();
        this._recalculateGuideState();

        // Reset search when data is updated
        this.originalGuides = [...this.guides];
        const searchInput = document.getElementById('guidesSearchInput');
        if (searchInput) {
            searchInput.value = '';
            const clearButton = document.getElementById('clearGuidesSearch');
            if (clearButton) {
                clearButton.style.display = 'none';
            }
        }

        this.refresh();
    }

    // ---------- Normalizers ----------
    _normalizeGuides(guides) {
        return toArray(guides).map(g => ({
            ...g,
            id: asInt(g.id),
            currentBookings: Array.isArray(g.currentBookings) ? g.currentBookings : [],
            // busy:  g.busy === 'boolean' ? g.busy : false,
            guideName: g.guideName ?? '',
        }));
    }

    _normalizeNotAvailableGuides(guides) {
        return toArray(guides).map(g => ({
            id: asInt(g.id),
            guideName: g.guideName ?? g.name ?? ''
        }));
    }


    _normalizeBookings(bookings) {
        return toArray(bookings).map(b => {
            // Build assignedGuides array canonical form:
            // each item: { guide_id, tourists, station_id (optional), check_in_status (optional) }
            let assignedGuides = [];

            if (Array.isArray(b.assignedGuides) && b.assignedGuides.length) {
                assignedGuides = b.assignedGuides.map(ag => ({
                    guide_id: asInt(ag.guide_id ?? ag.id),
                    tourists: asInt(ag.tourists ?? ag.assignedTourists ?? 0),
                    infants: asInt(ag.infants ?? 0),
                    station_id: ag.station_id != null ? asInt(ag.station_id) : undefined,
                    check_in_status: ag.check_in_status ?? ag.status ?? null
                }));
            } else {
                // backward-compat: single assignedGuide field from older code
                const single = b.assignedGuide ?? b.assigned_guide ?? b.assigned_guide_id ?? b.guide_id ?? null;
                if (single != null) {
                    assignedGuides = [{
                        guide_id: asInt(single),
                        tourists: asInt(b.assignedTourists ?? b.tourists ?? 0),
                        infants: asInt(b.infants ?? 0),
                        station_id: b.station_id != null ? asInt(b.station_id) : undefined,
                        check_in_status: b.check_in_status ?? null
                    }];
                }
            }

            return {
                ...b,
                id: asInt(b.id),
                assignedGuides,
                tour_package: b.tour_package ?? b.packageName ?? '',
                time_from: b.time_from ?? b.slot?.split('-')[0]?.trim() ?? '',
                time_to:   b.time_to   ?? b.slot?.split('-')[1]?.trim() ?? '',
                tourists:  asInt(b.total_tourists ?? b.guestCount ?? 0)
            };
        });
    }

    _ingestStationEntities() {
        // Merge all guides (top-level + nested)
        const guidesById = new Map(this.guides.map(g => [asInt(g.id), g]));

        this.stations.forEach(st => {
            const nestedGuides = toArray(st.guides);
            let stationGuideIds = getStationAssignedGuides(st).map(asInt);

            nestedGuides.forEach(gRaw => {
                const gNorm = this._normalizeGuides([gRaw])[0];
                const existing = guidesById.get(gNorm.id) || {};
                guidesById.set(gNorm.id, { ...existing, ...gNorm });
                stationGuideIds.push(gNorm.id);
            });

            stationGuideIds = Array.from(new Set(stationGuideIds.map(asInt)));
            st.assignedGuides = stationGuideIds;
            st.assigned_guides = stationGuideIds;
        });

        this.guides = Array.from(guidesById.values());

        // Merge all bookings (top-level + nested)
        const bookingsById = new Map(this.bookings.map(b => [asInt(b.id), b]));

        this.stations.forEach(st => {
            const nestedBookings = toArray(st.bookings);

            nestedBookings.forEach(bRaw => {
                const bNorm = this._normalizeBookings([bRaw])[0];
                const existing = bookingsById.get(bNorm.id);

                // Include station_id + check_in_status in assignedGuides
                const normalizedAssigned = (bNorm.assignedGuides || []).map(ag => ({
                    guide_id: asInt(ag.guide_id),
                    tourists: asInt(ag.tourists || 0),
                    infants: asInt(ag.infants || 0),
                    station_id: ag.station_id != null ? asInt(ag.station_id) : asInt(st.id),
                    station_name: st.name ?? `Station ${st.id}`,
                    check_in_status: ag.check_in_status ?? ag.status ?? null
                }));

                if (!existing) {
                    bNorm.assignedGuides = normalizedAssigned;
                    bookingsById.set(bNorm.id, bNorm);
                } else {
                    const merged = { ...existing };
                    merged.assignedGuides = merged.assignedGuides || [];

                    normalizedAssigned.forEach(ag => {
                        const exists = merged.assignedGuides.some(x =>
                            asInt(x.guide_id) === asInt(ag.guide_id) &&
                            asInt(x.station_id ?? 0) === asInt(ag.station_id ?? 0)
                        );

                        if (!exists) {
                            merged.assignedGuides.push(ag);
                        } else {
                            // Merge check_in_status if missing
                            const existingAg = merged.assignedGuides.find(x =>
                                asInt(x.guide_id) === asInt(ag.guide_id) &&
                                asInt(x.station_id ?? 0) === asInt(ag.station_id ?? 0)
                            );
                            if (existingAg && !existingAg.check_in_status && ag.check_in_status) {
                                existingAg.check_in_status = ag.check_in_status;
                            }
                        }
                    });

                    bookingsById.set(bNorm.id, merged);
                }
            });
        });

        this.bookings = Array.from(bookingsById.values());
    }


     _normalizeStations(stations) {
        return toArray(stations).map(s => {
            const id = asInt(s.id);
            // keep any capacity/location/name provided
            const name = s.name ?? `Station ${id}`;
            // ensure we have *some* assignedGuides array (we'll fill it later)
            const assigned = getStationAssignedGuides(s).map(asInt);
            return {
                ...s,
                id,
                name,
                assignedGuides: assigned,
                assigned_guides: assigned, // keep compatibility
                packages: toArray(s.packages).map(p => ({ id: asInt(p.id), name: p.name }))
            };
        });
    }


    // ---------- Recalculate guide state from booking.assignedGuides ----------
    _recalculateGuideState() {
        // Build map: guideId -> array of { bookingId, tourists, stationId }
        const map = new Map();
        this.guides.forEach(g => map.set(asInt(g.id), []));

        this.bookings.forEach(b => {
            (b.assignedGuides || []).forEach(ag => {
                const gid = asInt(ag.guide_id);
                if (!map.has(gid)) map.set(gid, []);
                map.get(gid).push({
                    bookingId: asInt(b.id),
                    tourists: asInt(ag.tourists || 0),
                    stationId: ag.station_id != null ? asInt(ag.station_id) : undefined
                });
            });
        });

        this.guides = this.guides.map(g => {
            const entries = map.get(asInt(g.id)) || [];
            const currentBookings = entries.map(e => e.bookingId);
            const touristsAssigned = entries.reduce((s, e) => s + (e.tourists || 0), 0);
            return {
                ...g,
                currentBookings,
                // busy: currentBookings.length > 0,
                // tourists_assigned: g.tourists_assigned
            };
        });
    }

    // ---------- Rendering ----------

    createGuideElement(guide) {
        if (
            guide.tourists_assigned === undefined ||
            guide.tourists_assigned === null
        ) {
            return null;
        }
        const div = document.createElement('div');
        div.className = 'guide-card';
        div.setAttribute('data-guide-id', guide.id);

        const status = guide.busy ? 'busy' : 'available';

        const isCopiedThisGuide =
            this.copiedGuide && parseInt(this.copiedGuide.guideId) === parseInt(guide.id);

        // COPY / CANCEL / BADGE LOGIC
        let copyControlsHtml = '';

        if (isCopiedThisGuide) {
            copyControlsHtml = `
                <span class="badge bg-info text-dark">COPIED</span>
                <button class="btn btn-sm btn-outline-danger no-drag"
                    title="Cancel copy"
                    onclick="tourismBoard.cancelCopyGuide()">❌</button>
            `;
        } else if (!this.copiedGuide && !guide.busy) {
            copyControlsHtml = `
                <button class="btn btn-sm btn-outline-primary no-drag" 
                    style="width:30px; height:30px; overflow: hidden;"
                    title="Copy guide"
                    onclick="tourismBoard.copyGuide('${guide.id}')">
                    <svg style="max-width:100%;" width="20" height="20" viewBox="0 0 26 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M16.348 30H4.82599C2.16486 30 0 27.8973 0 25.3125V9.43359C0 6.84883 2.16486 4.74609 4.82599 4.74609H16.348C19.0092 4.74609 21.174 6.84883 21.174 9.43359V25.3125C21.174 27.8973 19.0092 30 16.348 30ZM4.82599 7.08984C3.49554 7.08984 2.41299 8.14133 2.41299 9.43359V25.3125C2.41299 26.6048 3.49554 27.6562 4.82599 27.6562H16.348C17.6785 27.6562 18.761 26.6048 18.761 25.3125V9.43359C18.761 8.14133 17.6785 7.08984 16.348 7.08984H4.82599ZM26 22.3828V4.6875C26 2.10274 23.8351 0 21.174 0H7.7819C7.1155 0 6.57541 0.524597 6.57541 1.17188C6.57541 1.81915 7.1155 2.34375 7.7819 2.34375H21.174C22.5045 2.34375 23.587 3.39523 23.587 4.6875V22.3828C23.587 23.0301 24.1271 23.5547 24.7935 23.5547C25.4599 23.5547 26 23.0301 26 22.3828Z" fill="black"/>
                    </svg>
                </button>
            `;
        }

        div.innerHTML = `
            <div class="guide-header"
                 style="display:flex; justify-content:space-between; align-items:center;">
                
                <div class="guide-name">${guide.guideName}</div>

                <div style="display:flex; gap:6px; align-items:center;">
                    ${copyControlsHtml}
                    <span class="status-badge status-${status}">${status}</span>
                </div>
            </div>

            <div class="guide-capacity fw-bold"
                 style="font-size:1rem; margin-top:4px;">
                ${guide.tourists_assigned} / ${guide.capacity}
            </div>
        `;

        return div;
    }

    renderNotAvailableGuides() {
        const container = document.getElementById('notAvailableGuidesList');
        if (!container) return;

        container.innerHTML = '';

        if (!this.notAvailableGuides.length) {
            container.innerHTML = `<div class="text-muted">No blocked guides</div>`;
            return;
        }

        this.notAvailableGuides.forEach(g => {
            container.appendChild(this.createNotAvailableGuideElement(g));
        });
    }

    createNotAvailableGuideElement(guide) {
        const div = document.createElement('div');
        div.className = 'not-available-guide d-flex justify-content-between align-items-center mb-2';

        const nameSpan = document.createElement('span');
        nameSpan.textContent = guide.guideName;

        const btn = document.createElement('button');
        btn.className = 'btn btn-sm btn-success';
        btn.textContent = 'Mark as Available';

        btn.addEventListener('click', () => {
            Swal.fire({
                title: 'Mark guide as available?',
                text: `${guide.guideName} will be available for assignment.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, mark available',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.markGuideAsAvailable(guide.id, currentTimeFrom, currentDate);
                }
            });
        });

        div.appendChild(nameSpan);
        div.appendChild(btn);

        return div;
    }

    markGuideAsAvailable(guideId, currentTimeFrom, currentDate) {
        showLoading();

        $.post(markGuideAvailableUrl, { guide_id: guideId, currentTimeFrom, currentDate })
            .done((res) => {
                hideLoading();
                showToast(res.message || 'Guide marked as available', 'success');
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
            })
            .fail(() => {
                hideLoading();
                showToast('Failed to update guide status', 'error');
            });
    }

    // ---------- Search Functionality ----------
    initializeGuidesSearch() {
        const searchInput = document.getElementById('guidesSearchInput');
        const clearButton = document.getElementById('clearGuidesSearch');

        if (!searchInput) return;

        // Store original guides for filtering
        this.originalGuides = [...this.guides];

        // Search input event
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.trim();
            this.filterGuides(searchTerm);

            // Show/hide clear button
            if (searchTerm) {
                clearButton.style.display = 'block';
            } else {
                clearButton.style.display = 'none';
            }
        });

        // Clear search event
        if (clearButton) {
            clearButton.addEventListener('click', () => {
                searchInput.value = '';
                clearButton.style.display = 'none';
                this.filterGuides('');
                searchInput.focus();
            });
        }

        // Enter key to focus first guide
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                const firstGuide = document.querySelector('#guidesList .guide-card');
                if (firstGuide) {
                    firstGuide.focus();
                }
            }
        });
    }

    filterGuides(searchTerm) {
        const container = document.getElementById('guidesList');
        const noResultsDiv = document.getElementById('guidesNoResults');

        if (!container || !noResultsDiv) return;

        if (!searchTerm) {
            // Show all guides
            this.renderGuides();
            noResultsDiv.style.display = 'none';
            return;
        }

        // Filter guides by name (case insensitive)
        const filteredGuides = this.originalGuides.filter(guide => 
            guide.guideName.toLowerCase().includes(searchTerm.toLowerCase())
        );

        // Clear container
        container.innerHTML = '';

        if (filteredGuides.length === 0) {
            // Show no results message
            container.style.display = 'none';
            noResultsDiv.style.display = 'block';
        } else {
            // Show filtered guides
            container.style.display = 'block';
            noResultsDiv.style.display = 'none';
            filteredGuides.forEach(guide => {
                container.appendChild(this.createGuideElement(guide));
            });
        }
    }

    // Update the renderGuides method to ensure original guides are stored
    renderGuides() {
        const container = document.getElementById('guidesList');
        if (!container) return;

        // Store original guides if not already stored
        if (!this.originalGuides) {
            this.originalGuides = [...this.guides];
        }

        container.innerHTML = '';
        container.style.display = 'block';

        let renderedCount = 0;

        this.guides.forEach(guide => {
            const el = this.createGuideElement(guide);

            // Skip guides that should not be rendered
            if (!el) return;

            container.appendChild(el);
            renderedCount++;
        });

        // Handle "no results"
        const noResultsDiv = document.getElementById('guidesNoResults');
        if (noResultsDiv) {
            noResultsDiv.style.display = renderedCount === 0 ? 'block' : 'none';
        }
    }

    renderBookings() {
        const container = document.getElementById('pendingBookingsList');
        if (!container) return;
        container.innerHTML = '';
        const unassigned = this.bookings.filter(b => b.assignedGuide == null);
        unassigned.forEach(booking => container.appendChild(this.createBookingElement(booking, true)));
    }

    renderAbsentBookings() {
        const container = document.getElementById('absentBookingsList');
        if (!container) return;
        container.innerHTML = '';
        const unassigned = this.absentBookings.filter(b => b.assignedGuide == null);
        unassigned.forEach(booking => container.appendChild(this.createBookingElement(booking, false)));
    }

    createBookingElement(booking, showAssignButton = true) {
        const div = document.createElement('div');
        div.className = 'booking-card';
        div.setAttribute('data-booking-id', booking.id);
        const assignBtnHtml = showAssignButton ? `
            <button 
                style="
                    font-size: 12px; 
                    padding: 4px 8px; 
                    border: 1px solid #007bff; 
                    background-color: transparent; 
                    color: #007bff; 
                    border-radius: 4px; 
                    cursor: pointer; 
                    transition: all 0.2s ease; 
                    margin-top: 5px;
                "
                onmouseover="this.style.backgroundColor='#007bff'; this.style.color='#fff';"
                onmouseout="this.style.backgroundColor='transparent'; this.style.color='#007bff';"
                onclick="tourismBoard.openAssignGuideModal('${booking.id}', '')">
                Assign Tour Guides
            </button>
        ` : '';

        div.innerHTML = `
            <div class="booking-header">
                <span>${tenantId}-${booking.id}</span>
                <div class="booking-name">${booking.name || ''}</div>
            </div>

            <div class="booking-package">${booking.tour_package || ''}</div>

            ${assignBtnHtml}

            <div class="booking-details mt-2">
                <div class="booking-detail">
                    <span style="color:#1E90FF; font-weight:bold;">👥 Total: ${booking.tourists}</span>
                    <span style="color:#28a745; font-weight:bold; margin-left:10px;">✔ Assigned: ${booking.assigned_tourists}</span>
                    <span style="color:#dc3545; font-weight:bold; margin-left:10px;">⏳ Pending: ${booking.tourists - booking.assigned_tourists}</span>
                </div>
            </div>
            <div class="booking-details mt-2">
                <div class="booking-detail">
                    <span style="color:#1E90FF; font-weight:bold;">👶 Total Infants: ${booking.infants}</span>
                    <span style="color:#1E90FF; font-weight:bold; margin-left:10px;">✔ Assigned: ${booking.assigned_infants}</span>
                    <span style="color:#1E90FF; font-weight:bold; margin-left:10px;">⏳ Pending: ${booking.infants - booking.assigned_infants}</span>
                </div>
            </div>
        `;

        return div;
    }

    renderStations() {
        const container = document.getElementById('stationsWrapper');
        if (!container) return;
        container.innerHTML = '';
        this.stations.forEach(station => container.appendChild(this.createStationElement(station)));
    }

    // ---------- createStationElement (only the guideBookingsHtml part changed; replace the block) ----------
    createStationElement(station) {
        const div = document.createElement('div');
        div.className = 'station-card';

        const stationGuideIds = getStationAssignedGuides(station).map(asInt);

        // -------------------- GUIDE PASTE BUTTON --------------------
        const showPasteGuideBtn = this.copiedGuide !== null;

        const pasteGuideBtnHtml = showPasteGuideBtn
            ? `
                <button class="btn btn-sm btn-primary"
                    onclick="tourismBoard.pasteGuide('${station.id}')">
                    Paste Guide
                </button>
              `
            : '';

        // // join package names
        // const packageNames = (station.packages || []).map(p => p.name).join(', ');

        const assignedGuidesHtml = stationGuideIds.length === 0
            ? `<div class="drop-zone-placeholder">Drop guides here to assign to ${station.name ?? station.id}</div>`
            : stationGuideIds.map((guideId, idx) => {
                const gId = asInt(guideId);
                const guide = this.guides.find(g => asInt(g.id) === gId);
                if (!guide) return '';
                const status = guide.busy ? 'busy' : 'available';
                const colorClass = idx % 2 === 0 ? 'assigned-tag-1' : 'assigned-tag-2';

                return `
                    <div class="assigned-guide ${colorClass}" data-guide-id="${guide.id}">
                        <div class="assigned-guide-info">
                            <span class="assigned-guide-name">${guide.guideName}</span>
                            <span class="status-badge status-${status}">${status}</span>
                        </div>
                      <button class="unassign-guide-btn" onclick="tourismBoard.unassignGuideFromStation('${guide.id}', '${station.id}')">✕</button>
                    </div>
                `;
            }).join('');

        let checkInBtnHtml = "";
        let isStationLocked = false;
        let isStationBookingLocked = false;
        const hasAssignedGuide = stationGuideIds.length > 0;
        let hasAssignedBooking = false;
        let unlockBtnHtml = "";

        if (hasAssignedGuide) {
            hasAssignedBooking = this.bookings.some(b =>
                (b.assignedGuides || []).some(ag =>
                    stationGuideIds.includes(asInt(ag.guide_id)) &&
                    asInt(ag.station_id) === asInt(station.id)
                )
            );
        }

        if (hasAssignedGuide && hasAssignedBooking) {
            checkInBtnHtml = stationGuideIds.map(gId => {
                const guideBookings = (station.bookings || []).filter(b =>
                    asInt(b.assignedGuide) === gId
                );

                const totalGuests = guideBookings.reduce((sum, b) => {
                    const ag = (b.assignedGuides || []).find(ag =>
                        asInt(ag.guide_id) === asInt(gId) && asInt(ag.station_id) === asInt(station.id)
                    );
                    return sum + (ag ? (ag.tourists || 0) : 0);
                }, 0);

                let totalCheck1 = 0, totalCheck2 = 0, totalAbsent = 0;
                let allCompleted = true;
                let allSecondOrCompleted = true;
                guideBookings.forEach(b => {
                    const checkIns = b.checkInsCount || {};
                    const sid = String(station.id);
                    if (b.check_in_status !== "Completed") {
                        allCompleted = false;
                    }

                    if (
                        b.check_in_status !== "2nd Check In" &&
                        b.check_in_status !== "Completed"
                    ) {
                        allSecondOrCompleted = false;
                    }
                    totalCheck1 += checkIns['1'] || 0;
                    totalCheck2 += checkIns['2'] || 0;
                });   

                let stationStatus = "Pending";

                if (allCompleted) {
                    stationStatus = "Completed";
                } else if (allSecondOrCompleted) {
                    stationStatus = "2nd Check In";
                }

                if ((totalCheck1 + totalCheck2 + totalAbsent) >= totalGuests && totalCheck2 > 0 && stationStatus == "Completed") {
                    isStationLocked = true;
                    isStationBookingLocked = true;

                    unlockBtnHtml = `
                        <button 
                            class="btn btn-sm btn-warning ms-2 unlock-station-btn"
                            style="padding:2px 6px; font-size:13px; line-height:1;"
                            data-station-id="${station.id}"
                            data-time-from="${currentTimeFrom}"
                            data-date="${currentDate}">
                            <i class="fa fa-unlock"></i>Unlock Station
                        </button>
                    `;
                    return `<button class="btn btn-sm btn-secondary checkin-btn" 
                                    data-station-id="${station.id}" 
                                    data-guide-id="${gId}" 
                                    data-action="completed" disabled>
                                Check In Completed
                            </button>`;
                }

                if (stationStatus === "2nd Check In") {
                    return `
                        <button class="btn btn-sm btn-warning checkin-btn"
                                data-station-id="${station.id}"
                                data-guide-id="${gId}"
                                data-action="second">
                            2nd Check In
                        </button>`;
                }

                return `<button class="btn btn-sm btn-success checkin-btn"
                                data-station-id="${station.id}"
                                data-guide-id="${gId}"
                                data-action="ready">
                            Ready
                        </button>`;
                    
            }).join(" ");
        }

        // ---------- build guide bookings HTML ----------
        let guideBookingsHtml = "";

        // Helper: whether we should show paste controls on this station (not source)
        const showPasteOnStation = this.copiedBooking && asInt(this.copiedBooking.fromStationId) !== asInt(station.id);

        if (stationGuideIds.length > 0) {
            const stationIdInt = asInt(station.id);
            const guidesMap = Object.fromEntries(this.guides.map(g => [asInt(g.id), g]));
            // const isTourOp = userType === 'Tour Operator';

            guideBookingsHtml = stationGuideIds.map(guideId => {
                const gId = asInt(guideId);
                const guide = guidesMap[gId];

                // Pre-filter bookings for this guide + station once
                const guideBookings = this.bookings.filter(b => 
                    (b.assignedGuides || []).some(ag => asInt(ag.guide_id) === gId && asInt(ag.station_id) === stationIdInt)
                );

                // Pre-compute total guests for color class
                const totalGuests = guideBookings.reduce((sum, b) => {
                    const ag = (b.assignedGuides || []).find(ag => asInt(ag.guide_id) === gId && asInt(ag.station_id) === stationIdInt);
                    return sum + (ag?.tourists || 0);
                }, 0);

                let colorClass = 'text-danger fw-bold';

                // Map bookings to HTML once
                let accumulatedGuests = 0;
                const bookingsHtml = guideBookings.length === 0
                    ? '<div style="font-style: italic; font-size: 0.75rem; color: #64748b;">Drop bookings here</div>'
                    : guideBookings.map(booking => {
                        const assignedGuides = booking.assignedGuides || [];
                        const ag = assignedGuides.find(ag => asInt(ag.guide_id) === gId && asInt(ag.station_id) === stationIdInt);
                        var assignedGuests = ag?.tourists || 0;
                        const assignedInfants = ag?.infants || 0;
                        const stationBooking = (station.bookings || []).find(
                            sb => asInt(sb.id) === asInt(booking.id) && asInt(sb.assignedGuide) === gId
                        );

                        const assignedToMultipleGuides = assignedGuides.some(ag => asInt(ag.guide_id) !== gId);

                         const assignBtnHtml = assignedToMultipleGuides
                            ? `<button class="btn btn-sm btn-outline-primary assign-tour-btn dark-outline mt-1"
                                        onclick="tourismBoard.openAssignGuideModal('${booking.id}', '${station.id}')">
                                   Manual Update
                               </button>`
                            : `<button class="btn btn-sm btn-outline-primary assign-tour-btn mt-1"
                                        onclick="tourismBoard.openAssignGuideModal('${booking.id}', '${station.id}')">
                                   Assign Tour Guide
                               </button>`;

                        const checkIns = { '1': 0, '2': 0, ...(booking.checkInsCount || {}) };
                        const stationCheckInCount = { '1': checkIns['1'] || 0, '2': checkIns['2'] || 0 };

                        const pending = Math.max(assignedGuests - (stationCheckInCount['1'] + stationCheckInCount['2']), 0);
                       
                        if (assignedToMultipleGuides) {
                            if (stationCheckInCount['1'] > 0 && ag?.check_in_status === "Pending") {
                                stationCheckInCount['1'] = assignedGuests;
                                stationCheckInCount['2'] = 0;
                            }
                            if (stationCheckInCount['2'] > 0 && ag?.check_in_status === "Completed") {
                                stationCheckInCount['2'] = assignedGuests;
                                stationCheckInCount['1'] = 0;
                            }
                        } else{

                            if (stationCheckInCount['1'] > 0 && ag?.check_in_status === "Pending") {
                                assignedGuests = stationCheckInCount['1'];
                                stationCheckInCount['2'] = 0;
                            }
                            if (stationCheckInCount['2'] > 0 && ag?.check_in_status === "Completed") {
                                assignedGuests = stationCheckInCount['2'];
                                stationCheckInCount['1'] = 0;
                            }

                        }
                        let effectiveGuests = assignedGuests;
                        accumulatedGuests += effectiveGuests;

                        // keep per-booking value unchanged
                        assignedGuests = effectiveGuests;
                        if (guide) {
                            if (accumulatedGuests === guide.capacity) colorClass = 'text-success fw-bold';
                            else if (accumulatedGuests > guide.capacity) colorClass = 'text-warning fw-bold';
                        }

                        let checkInHtml = '';
                        if (pending > 0 && stationCheckInCount['1'] === 0 && stationCheckInCount['2'] === 0) {
                            checkInHtml = `<span class="checked-in-wrap">Not Checked In: ${pending}</span>`;
                        } else {
                            const parts = [];
                            if (stationCheckInCount['1'] > 0) parts.push(`<span class="checked-in-wrap-1st">1st Check In: ${stationCheckInCount['1']}</span>`);
                            if (stationCheckInCount['2'] > 0) parts.push(`<span class="checked-in-wrap-2st">2nd Check In: ${stationCheckInCount['2']}</span>`);
                            if (pending > 0) parts.push(`<span class="checked-in-wrap">Not Checked In: ${pending}</span>`);
                            checkInHtml = parts.join(' ');
                        }

                        const isCopiedThisBooking = this.copiedBooking && asInt(this.copiedBooking.bookingId) === asInt(booking.id);
                        const copiedBadgeHtml = isCopiedThisBooking ? `<span class="badge bg-info text-dark">COPIED</span>` : '';
                        const cancelCopyHtml = isCopiedThisBooking
                            ? `<button class="cancel-copy-btn" title="Cancel copy" onclick="tourismBoard.cancelCopy()">✕</button>`
                            : '';

                        return `
                            <div class="active-bookings-wrap ${isCopiedThisBooking ? 'copied-booking' : ''}" data-booking-id="${booking.id}" draggable="true">
                                <div class="active-bookings-content-wrap">

                                    <!-- ROW 1 : Booking ID + Package -->
                                    <div class="booking-row booking-row-top">
                                        <div class="booking-left">
                                            <label class="booking-checkbox">
                                                <input type="checkbox"
                                                       class="booking-check-toggle booking-check-toggle-18"
                                                       data-booking-id="${booking.id}"
                                                       data-station-id="${station.id}"
                                                       data-guide-id="${gId}"
                                                       ${(stationBooking?.booking_checked ?? booking.booking_checked) === 'Yes' ? 'checked' : ''} />
                                                <span class="checkmark"></span>
                                            </label>

                                            <span class="booking-id-wrap">${tenantId}-${booking.id}</span>
                                            <div class="assigned-booking-package">${booking.tour_package}</div>
                                        </div>
                                    </div>

                                    <!-- ROW 2 : Name + Buttons -->
                                    <div class="booking-row booking-row-bottom">
                                        <div class="booking-name">
                                            ${booking.name || ''}${copiedBadgeHtml}
                                        </div>

                                        <div class="booking-actions">
                                            ${cancelCopyHtml}
                                            ${!isCopiedThisBooking
                                                ? `<button class="copy-booking-btn"
                                                    title="Copy booking"
                                                    onclick="tourismBoard.copyBooking('${booking.id}', '${station.id}', '${gId}')">
                                                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.0052 1.33301H4.00521C3.65159 1.33301 3.31245 1.47348 3.0624 1.72353C2.81235 1.97358 2.67188 2.31272 2.67188 2.66634V13.333C2.67188 13.6866 2.81235 14.0258 3.0624 14.2758C3.31245 14.5259 3.65159 14.6663 4.00521 14.6663H12.0052C12.3588 14.6663 12.698 14.5259 12.948 14.2758C13.1981 14.0258 13.3385 13.6866 13.3385 13.333V4.66634L10.0052 1.33301Z" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.32812 1.33301V3.99967C9.32812 4.3533 9.4686 4.69244 9.71865 4.94248C9.9687 5.19253 10.3078 5.33301 10.6615 5.33301H13.3281" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.66146 6H5.32812" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6615 8.66699H5.32812" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6615 11.333H5.32812" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                  </button>`
                                                : ''
                                            }
                                            <button class="unassign-booking-btn"
                                                onclick="tourismBoard.unassignBookingFromGuide('${booking.id}', '${station.id}', '${gId}')">✕</button>
                                        </div>
                                    </div>

                                </div>

                                <div class="active-bookings-data-wrap">
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="assigned-wrap"><svg style="max-width: 16px; height: auto;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87m-3-12a4 4 0 0 1 0 7.75"/></g></svg> ${assignedGuests} guests</span>
                                        <span class="assigned-wrap"><svg style="max-width: 16px; height: auto;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886"><path stroke-linejoin="round" stroke-width="1.5" d="M19.95 10.896a8.001 8.001 0 0 1-15.9 0a1.5 1.5 0 1 1 .159-2.719a8.003 8.003 0 0 1 15.583 0a1.5 1.5 0 1 1 .159 2.719Z"/><path stroke-linejoin="round" stroke-width="1.5" d="M12 15a1 1 0 0 0 1-1h-2a1 1 0 0 0 1 1Z"/><path stroke-linecap="round" stroke-width="1.5" d="M18 22c0-1.792-.786-3.4-2.031-4.5M6 22c0-1.792.786-3.4 2.031-4.5M12 2c1 0 2 .895 2 2s-1 2-2 2a2.63 2.63 0 0 1-1.5-.5"/><path stroke-linecap="round" stroke-width="2" d="M9 10v.01m6-.01v.01"/></g></svg> ${assignedInfants} infants</span> 
                                        ${checkInHtml}
                                    </div>      
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        ${assignBtnHtml}    
                                    </div>      
                                </div>
                            </div>

                            <div class="d-none ${isCopiedThisBooking ? 'copied-booking' : ''}" 
                                 style="background-color:#e2e3e5; color:#000; border-radius:6px; padding:6px; margin-bottom:6px;" 
                                 data-booking-id="${booking.id}" draggable="true">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                <input type="checkbox"
                                       class="booking-check-toggle booking-check-toggle-18"
                                       data-booking-id="${booking.id}"
                                       data-station-id="${station.id}"
                                       data-guide-id="${gId}"
                                       ${booking.booking_checked === 'Yes' ? 'checked' : ''}
                                />
                                    <span>${tenantId}-${booking.id}</span>
                                    <div class="assigned-booking-customer" style="font-weight:600; color:#212529;margin-right: 18px;">
                                        ${booking.name || ''}${copiedBadgeHtml}
                                    </div>
                                    <div class="assigned-booking-package">${booking.tour_package}</div>
                                    
                                            ${cancelCopyHtml}
                                            ${!isCopiedThisBooking
                                                ? `<button class="copy-booking-btn" style="margin-right: 10px" title="Copy booking"
                                                    onclick="tourismBoard.copyBooking('${booking.id}', '${station.id}', '${gId}')">📋</button>`
                                                : ''
                                            }
                                            <button class="unassign-booking-btn" 
                                                onclick="tourismBoard.unassignBookingFromGuide('${booking.id}', '${station.id}', '${gId}')">✕</button>
                                          
                                </div>
                                <div class="assigned-booking-details" 
                                     style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                                    <span>👥 ${assignedGuests} guests</span>
                                    <span> ${assignedInfants} infants</span>            
                                    ${checkInHtml}
                                    ${assignBtnHtml}
                                </div>
                            </div>
                        `;
                    }).join('');

                const pasteBtnForGuide = showPasteOnStation
                    ? `<button class="btn btn-sm btn-primary paste-zone-btn" onclick="tourismBoard.pasteBooking('${station.id}', '${gId}')">Paste to this guide</button>`
                    : '';

                return `
                    <div class="guide-booking-zone" data-guide-id="${gId}" data-station-id="${station.id}">
                        <div class="guide-booking-header active-bookings-header-wrap">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="guide-booking-name">${guide?.guideName || ''}</span>
                                <span class="guest-count ${colorClass}" style="font-size:1rem;">${accumulatedGuests} / ${guide?.capacity || 0}</span>
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div>${pasteBtnForGuide}</div>
                                <button class="btn btn-outline-primary edit-guide-capacity-btn" style="padding:2px 6px; font-size:14px; line-height:1;" title="Edit capacity" data-time-from="${currentTimeFrom}" data-date="${currentDate}" data-guide-id="${gId}" data-guide-name="${guide?.guideName || ''}" data-capacity="${guide?.capacity || 0}" data-station-id="${station.id}" > <i class="fa fa-edit"></i></button>
                            </div>
                        </div>
                        
                        <div class="d-none guide-booking-header" style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                            <div>
                                <span class="guide-booking-name">${guide?.guideName || ''}</span>
                                <span class="guest-count ${colorClass}" style="font-size:1rem;">${accumulatedGuests} / ${guide?.capacity || 0}
                                <button
                                    class="btn btn-outline-primary edit-guide-capacity-btn"
                                    style="padding:2px 6px; font-size:14px; line-height:1;"
                                    title="Edit capacity"
                                    data-time-from="${currentTimeFrom}"
                                    data-date="${currentDate}"
                                    data-guide-id="${gId}"
                                    data-guide-name="${guide?.guideName || ''}"
                                    data-capacity="${guide?.capacity || 0}"
                                    data-station-id="${station.id}"
                                >
                                    <i class="fa fa-edit"></i>
                                </button>
                                </span>
                            </div>
                            <div>${pasteBtnForGuide}</div>
                        </div>
                        <div class="bookings-container" style="min-height: 60px;">
                            ${bookingsHtml}
                        </div>
                    </div>
                `;
            }).join('');
        } else {
            // Find unassigned bookings for this station
            const unassignedBookings = this.bookings.filter(b =>
                (b.assignedGuides || []).some(ag =>
                    !ag.guide_id && asInt(ag.station_id) === asInt(station.id)
                )
            );
            isStationBookingLocked = true;
            // Only render if we have any
            if (unassignedBookings.length > 0) {
                const bookingsHtml = unassignedBookings.map((booking) => {
                    const ag = (booking.assignedGuides || []).find(ag =>
                        !ag.guide_id && asInt(ag.station_id) === asInt(station.id)
                    );
                    const assignedGuests = ag ? (ag.tourists || 0) : 0;
                    const assignedInfants = ag?.infants || 0;

                    const checkIns = { '1': 0, '2': 0, ...(booking.checkInsCount || {}) };

                    const stationIdStr = String(station.id);
                    const stationCheckInCount = { '1': 0, '2': 0 };

                    if (checkIns[stationIdStr]) {
                        stationCheckInCount['1'] = checkIns[stationIdStr] || 0;
                    } else if (checkIns['1'] || checkIns['2'] || checkIns['A']) {
                        stationCheckInCount['1'] = checkIns['1'] || 0;
                        stationCheckInCount['2'] = checkIns['2'] || 0;
                    }

                    const pending = Math.max(
                        assignedGuests - ((checkIns['1'] || 0) + (checkIns['2'] || 0)),
                        0
                    );

                    let checkInHtml = "";
                    if (pending > 0 && stationCheckInCount['1'] === 0 && stationCheckInCount['2'] === 0) {
                        checkInHtml = `<span class="checked-in-wrap">Not Checked In: ${pending}</span>`;
                    } else {
                        const parts = [];
                        if (stationCheckInCount['1'] > 0) parts.push(`<span class="checked-in-wrap-1st">1st Check In: ${stationCheckInCount['1']}</span>`);
                        if (stationCheckInCount['2'] > 0) parts.push(`<span class="checked-in-wrap-2st">2nd Check In: ${stationCheckInCount['2']}</span>`);
                        if (stationCheckInCount['A'] > 0) parts.push(`<span class="checked-in-wrap-3st">Absent: ${stationCheckInCount['A']}</span>`);
                        if (pending > 0) parts.push(`<span class="checked-in-wrap">Not Checked In: ${pending}</span>`);
                        checkInHtml = parts.join(' ');
                    }

                    const isCopiedThisBooking = this.copiedBooking && asInt(this.copiedBooking.bookingId) === asInt(booking.id);
                    const copiedBadgeHtml = isCopiedThisBooking ? `<span class="badge bg-info text-dark">COPIED</span>` : '';

                    return `
                    <div class="assigned-bookings-wrap assigned-booking" data-booking-id="${booking.id}" draggable="true">
                        <div class="assigned-bookings-content-wrap">

                            <!-- ROW 1 : Booking ID + Package -->
                            <div class="assigned-row assigned-row-top">
                                <span class="booking-id-wrap">${tenantId}-${booking.id}</span>
                                <div class="assigned-booking-package">${booking.tour_package}</div>
                            </div>

                            <!-- ROW 2 : Name + Actions -->
                            <div class="assigned-row assigned-row-bottom">
                                <div class="assigned-booking-customer">
                                    ${booking.name || ''}${copiedBadgeHtml}
                                </div>

                                <div class="assigned-booking-actions">
                                    <button class="copy-booking-btn"
                                        title="Copy booking"
                                        onclick="tourismBoard.copyBooking('${booking.id}', '${station.id}', '')">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.0052 1.33301H4.00521C3.65159 1.33301 3.31245 1.47348 3.0624 1.72353C2.81235 1.97358 2.67188 2.31272 2.67188 2.66634V13.333C2.67188 13.6866 2.81235 14.0258 3.0624 14.2758C3.31245 14.5259 3.65159 14.6663 4.00521 14.6663H12.0052C12.3588 14.6663 12.698 14.5259 12.948 14.2758C13.1981 14.0258 13.3385 13.6866 13.3385 13.333V4.66634L10.0052 1.33301Z" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M9.32812 1.33301V3.99967C9.32812 4.3533 9.4686 4.69244 9.71865 4.94248C9.9687 5.19253 10.3078 5.33301 10.6615 5.33301H13.3281" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.66146 6H5.32812" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6615 8.66699H5.32812" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6615 11.333H5.32812" stroke="#676F7E" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </button>

                                    <button class="unassign-booking-btn"
                                        onclick="tourismBoard.unassignBookingFromGuide('${booking.id}', '${station.id}', '')">✕</button>
                                </div>
                            </div>
                        </div>
                        <div class="assigned-bookings-data-wrap">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="assigned-wrap"><svg style="max-width: 16px; height: auto;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87m-3-12a4 4 0 0 1 0 7.75" /></g></svg> ${assignedGuests} guests</span>
                                    <span class="assigned-wrap"><svg style="max-width: 16px; height: auto;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886"><path stroke-linejoin="round" stroke-width="1.5" d="M19.95 10.896a8.001 8.001 0 0 1-15.9 0a1.5 1.5 0 1 1 .159-2.719a8.003 8.003 0 0 1 15.583 0a1.5 1.5 0 1 1 .159 2.719Z"/><path stroke-linejoin="round" stroke-width="1.5" d="M12 15a1 1 0 0 0 1-1h-2a1 1 0 0 0 1 1Z"/><path stroke-linecap="round" stroke-width="1.5" d="M18 22c0-1.792-.786-3.4-2.031-4.5M6 22c0-1.792.786-3.4 2.031-4.5M12 2c1 0 2 .895 2 2s-1 2-2 2a2.63 2.63 0 0 1-1.5-.5"/><path stroke-linecap="round" stroke-width="2" d="M9 10v.01m6-.01v.01"/></g></svg> ${assignedInfants} infants
                                    </span> 
                            </div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                ${checkInHtml}
                            </div>
                        </div>
                    </div>

                    <div class="d-none assigned-booking" style="background-color:#e2e3e5; color:#000; border-radius:6px; padding:6px; margin-bottom:6px;" data-booking-id="${booking.id}" draggable="true">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span>${tenantId}-${booking.id}</span>
                                <div class="assigned-booking-customer" style="font-weight:600; color:#212529;">${booking.name || ''}${copiedBadgeHtml}</div>
                                <div class="assigned-booking-package">${booking.tour_package}</div>
                            
                                    <button class="copy-booking-btn" title="Copy booking"
                                            onclick="tourismBoard.copyBooking('${booking.id}', '${station.id}', '')">
                                        📋
                                    </button>
                                    <button class="unassign-booking-btn" 
                                            onclick="tourismBoard.unassignBookingFromGuide('${booking.id}', '${station.id}', '')">
                                        ✕
                                    </button>
                            </div>
                            <div class="assigned-booking-details" style="display:flex; justify-content:space-between; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span>👥 ${assignedGuests} guests</span>
                                <span> ${assignedInfants} infants</span>
                                ${checkInHtml}
                            </div>
                        </div>
                    `;
                }).join('');

                // Show paste button for unassigned-zone header (if copying from another station)
                const pasteBtnUnassigned = showPasteOnStation ? `
                    <button class="btn btn-sm btn-primary paste-zone-btn" onclick="tourismBoard.pasteBooking('${station.id}', '')">
                        Paste (unassigned)
                    </button>` : '';

                guideBookingsHtml = `
                    <div class="guide-booking-zone" data-guide-id="" data-station-id="${station.id}">
                        <div class="guide-booking-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="guide-booking-name">Unassigned Bookings</span>
                            <div>${pasteBtnUnassigned}</div>
                        </div>
                        <div class="bookings-container" style="min-height: 60px;">
                            ${bookingsHtml}
                        </div>
                    </div>
                `;
            }
        }

        const allPackages = station.packages || [];
        const packageCount = allPackages.length;

        let packageDisplay = "";
        if (packageCount > 0) {
            const shownPackages = allPackages.slice(0, 2).map(p => p.name).join(", ");
            const hiddenPackages = allPackages.slice(2).map(p => p.name);

            if (packageCount > 2) {
                // show first 2 + "+N more" button with tooltip
                const moreCount = packageCount - 2;
                packageDisplay = `
                    ${shownPackages}
                    <button type="button" class="btn btn-sm btn-outline-secondary view-all-packages-btn"
                        data-bs-toggle="tooltip"
                        data-bs-placement="top"
                        title="${hiddenPackages.join(', ')}">
                        +${moreCount} more
                    </button>
                `;
            } else {
                // only 1–2 packages
                packageDisplay = shownPackages;
            }
        }

        // Initialize the station HTML
        div.innerHTML = `
            <div class="station-header">
                <div>
                    <div class="station-title d-block">
                        📍 ${station.name}
                            <button
                                class="inline-block btn btn-sm btn-outline-primary ms-2 edit-station-btn" style="padding:2px 6px; font-size:14px; line-height:1;"
                                data-station-id="${station.id}"
                                data-station-name="${station.name}"
                                data-station-specific=""
                                data-time-from="${currentTimeFrom}"
                                data-date="${currentDate}"
                                data-packages="${allPackages.map(p => p.id).join(',')}"
                            >
                                <i class="fa fa-edit"></i>
                            </button>
                        ${packageDisplay ? ' (' + packageDisplay + ')' : ''}
                        ${unlockBtnHtml}
                    </div>
                    ${station.location ? `<div class="station-location">${station.location}</div>` : ''}
                </div>
                ${typeof station.capacity !== 'undefined'
                    ? `<span class="capacity-badge">${getStationAssignedGuides(station).length}/${station.capacity}</span>`
                    : ''}
            </div>

            <div class="${isStationLocked ? 'station-locked' : ''}">
                <h3 style="font-size: 0.875rem; font-weight: 500; margin-bottom: 8px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <span>Assigned Guides</span>
                        ${checkInBtnHtml}
                        ${pasteGuideBtnHtml}
                        ${(stationGuideIds.length > 0) 
                            ? `<button class="btn btn-sm btn-success assign-btn"
                                data-station-id="${station.id}">
                                Assign
                              </button>`
                            : "" }
                    
                </h3>

                <div class="drop-zone" data-station-id="${station.id}">
                    ${assignedGuidesHtml}
                </div>
            </div>

            <div class="guide-bookings-section ${isStationBookingLocked ? 'station-locked' : ''}">
                <h3 style="font-size: 0.875rem; font-weight: 500; margin-bottom: 8px;">Active Bookings</h3>
                ${guideBookingsHtml}
            </div>
        `;

        // Initialize tooltips (Bootstrap)
        setTimeout(() => {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
        }, 10);


        return div;
    }
    // ---------------- end createStationElement ----------------

    /* ---------------- copy / paste methods ----------------*/
    copyBooking(bookingId, fromStationId, fromGuideId) {
        this.copiedBooking = {
            bookingId: asInt(bookingId),
            fromStationId: asInt(fromStationId),
            fromGuideId: fromGuideId ? asInt(fromGuideId) : null
        };
        this.refresh(); // re-render so paste buttons appear
    }

    cancelCopy() {
        this.copiedBooking = null;
        this.refresh(); // re-render to remove "paste" buttons and copied badge
    }

    async pasteBooking(toStationId, toGuideId = null) {
        if (!this.copiedBooking) {
            Swal.fire({ icon: 'warning', title: 'Nothing to paste', text: 'Copy a booking first.' });
            return;
        }

        const bookingId = asInt(this.copiedBooking.bookingId);
        const fromStationId = asInt(this.copiedBooking.fromStationId);
        const fromGuideId = this.copiedBooking.fromGuideId ? asInt(this.copiedBooking.fromGuideId) : null;
        const targetStationId = asInt(toStationId);
        const targetGuideId = (toGuideId === '' || toGuideId === null) ? null : asInt(toGuideId);

        // Prevent pasting into same place
        if (targetStationId === fromStationId && (targetGuideId === fromGuideId || (targetGuideId == null && fromGuideId == null))) {
            Swal.fire({ icon: 'warning', title: 'Same place', text: 'Booking is already there.' });
            return;
        }

        // Package validation
        const targetStation = this.stations.find(st => asInt(st.id) === targetStationId);

        const sourceStation = this.stations.find(
            st => asInt(st.id) === fromStationId
        );

        const sourceBooking = sourceStation?.bookings?.find(
            b => asInt(b.id) === bookingId
        );

        if (sourceBooking && targetStation) {
            const allowedPackageIds = (targetStation.packages || []).map(p => asInt(p.id));
            if (!allowedPackageIds.includes(asInt(sourceBooking.tour_package_id))) {
                Swal.fire({
                    title: '⚠️ Package Not Allowed',
                    text: "This booking’s package cannot be pasted into this station.",
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
        }

        //  If pasting into a guide, check capacity (with swap logic)
        if (targetGuideId) {
            const toGuide = this.guides.find(g => asInt(g.id) === targetGuideId);
            const guideCapacity = toGuide ? toGuide.capacity : 0;

            const currentLoad = this.bookings.reduce((sum, b) => {
                const ag = (b.assignedGuides || []).find(ag =>
                    asInt(ag.guide_id) === targetGuideId && asInt(ag.station_id) === targetStationId
                );
                return sum + (ag ? (ag.tourists || 0) : 0);
            }, 0);

            const bookingTourists = sourceBooking?.assignedTourists ?? 0;

            // CASE 2: From another guide zone → Swap if exceeded
            if (currentLoad + bookingTourists > guideCapacity) {
                this.openSwapPopup(
                    targetGuideId,
                    targetStationId,
                    bookingId,
                    fromGuideId,
                    fromStationId
                );
                return;
            }
        }

        // Unassign from source
        if (fromGuideId) {
            await this.unassignBookingFromGuide(bookingId, fromStationId, fromGuideId, { silent: true });
        } else {
            await this.unassignBookingFromGuide(bookingId, fromStationId, '', { silent: true });
        }

        // Assign to destination (null guide => unassigned at that station)
        await this.assignBookingToGuide(bookingId, targetGuideId, targetStationId);

        //  Clear clipboard and refresh UI
        this.copiedBooking = null;
        this.refresh();
    }

    copyGuide(guideId) {
        this.copiedGuide = {
            guideId: parseInt(guideId, 10)
        };
        this.refresh();
    }

    cancelCopyGuide() {
        this.copiedGuide = null;
        this.refresh();
    }

    pasteGuide(stationId) {
        if (!this.copiedGuide) return;

        const guideId = this.copiedGuide.guideId;
        const station = this.stations.find(s => s.id == stationId);

        // Already assigned?
        const alreadyAssigned = getStationAssignedGuides(station)
            .includes(parseInt(guideId, 10));

        if (alreadyAssigned) {
            showToast('Guide already assigned to this station', 'warning');
            return;
        }

        // Respect one-guide-per-station rule
        if (stationOneGuide === true) {
            const count = getStationAssignedGuides(station).length;
            if (count >= 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Limit Reached',
                    text: 'Only one guide can be assigned.',
                    confirmButtonColor: '#3085d6',
                    confirmButtonText: 'OK'
                });

                return;
            }
        }
        showLoading();
        this.assignGuideToStation(guideId, stationId);
        this.copiedGuide = null;
        this.refresh();
    }
    
    // ---------- Drag & Drop ----------
    initializeDragAndDrop() {
        // Clear old instances
        this.sortables.forEach(s => s.destroy && s.destroy());
        this.sortables = [];

        // Guides source (drag from here)
        /*const guidesContainer = document.getElementById('guidesList');
        if (guidesContainer && window.Sortable) {
            this.sortables.push(Sortable.create(guidesContainer, {
                group: 'guides',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                filter: '.no-drag',
                preventOnFilter: true,
                delay: 150,                // wait before drag starts
                delayOnTouchOnly: true,    // only delay on touch devices
                touchStartThreshold: 5,    // allow small movement to scroll

                // Enable auto scroll
                scroll: true,
                scrollSensitivity: 60,
                scrollSpeed: 15
                    }));
        }*/

        // Bookings source (drag from here)
        const bookingsContainer = document.getElementById('pendingBookingsList');
        if (bookingsContainer && window.Sortable) {
            this.sortables.push(Sortable.create(bookingsContainer, {
                group: 'bookings',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                filter: 'button, .assign-tour-btn',
                preventOnFilter: false,
                // Touchpad + scroll fix
                delay: 150,
                delayOnTouchOnly: true,
                touchStartThreshold: 5,
                // Natural scrolling
                scroll: true,
                scrollSensitivity: 60,
                scrollSpeed: 15
            }));
        }

       // Station guide drop zones
       /* document.querySelectorAll('.drop-zone').forEach(zone => {
            const stationId = zone.getAttribute('data-station-id');
            if (window.Sortable) {
                this.sortables.push(Sortable.create(zone, {
                    group: 'guides',
                    animation: 150,
                    onAdd: evt => {
                        const guideId = parseInt(evt.item.getAttribute('data-guide-id'), 10);
                        // Find station data
                        const station = this.stations.find(s => s.id == stationId);

                        // How many guides are already assigned?
                        const assignedCount = Array.isArray(station.guide_ids)
                            ? station.guide_ids.length
                            : (station.assignedGuides?.length || 0);

                        // Prevent if stationOneGuide enabled
                        if (stationOneGuide === true && assignedCount >= 1) {
                            evt.from.appendChild(evt.item); // move guide back
                            Swal.fire({
                                icon: 'warning',
                                title: 'Limit Reached',
                                text: 'Only one guide can be assigned.',
                                confirmButtonColor: '#3085d6',
                                confirmButtonText: 'OK'
                            });

                            return;
                        }

                        // Assign normally
                        this.assignGuideToStation(guideId, stationId);
                        this.refresh();
                    }
                }));
            }
        });*/

        // Guide booking zones, pending bookings list
        const bookingsContainerPending = document.getElementById('pendingBookingsList');
        if (bookingsContainerPending && window.Sortable) {
            this.sortables.push(Sortable.create(bookingsContainerPending, {
                group: 'bookings',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                draggable: '.assigned-booking', // only booking cards are draggable
                filter: 'button, .assign-tour-btn',
                preventOnFilter: false,
                // Touchpad + scroll fix
                delay: 150,
                delayOnTouchOnly: true,
                touchStartThreshold: 5,
                // Natural scrolling
                scroll: true,
                scrollSensitivity: 60,
                scrollSpeed: 15
            }));
        }

        // Guide booking containers (each holds only booking items)
        document.querySelectorAll('.guide-booking-zone .bookings-container').forEach(container => {
            if (!window.Sortable) return;

            this.sortables.push(Sortable.create(container, {
                group: 'bookings',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                draggable: '.assigned-booking',

                onAdd: async evt => {
                    const bookingId = parseInt(evt.item.getAttribute('data-booking-id'), 10);

                    // Destination info
                    const toZone = evt.to.closest('.guide-booking-zone');
                    const toGuideId = parseInt(toZone.getAttribute('data-guide-id'), 10);
                    const toStationId = parseInt(toZone.getAttribute('data-station-id'), 10);

                    // Source info
                    const fromZone = evt.from.closest('.guide-booking-zone');
                    const fromGuideId = fromZone ? parseInt(fromZone.getAttribute('data-guide-id'), 10) : null;
                    const fromStationId = fromZone ? parseInt(fromZone.getAttribute('data-station-id'), 10) : null;

                    // Get target guide capacity
                    const toGuide = this.guides.find(g => asInt(g.id) === toGuideId);
                    const guideCapacity = toGuide ? toGuide.capacity : 0;

                    // Current load (already assigned tourists)
                    const currentLoad = this.bookings.reduce((sum, b) => {
                        const ag = (b.assignedGuides || []).find(ag =>
                            asInt(ag.guide_id) === toGuideId && asInt(ag.station_id) === toStationId
                        );
                        return sum + (ag ? (ag.tourists || 0) : 0);
                    }, 0);

                    // Tourists in the dragged booking
                     const targetStation = this.stations.find(st => asInt(st.id) === toStationId);

                    const sourceStation = this.stations.find(
                        st => asInt(st.id) === fromStationId
                    );

                    const sourceBooking = sourceStation?.bookings?.find(
                        b => asInt(b.id) === bookingId
                    );
                    
                    let bookingTourists = sourceBooking?.assignedTourists ?? 0;

                    // CASE 1: From PENDING LIST
                    if (evt.from.id === "pendingBookingsList") {
                         const booking = this.bookings.find(b => asInt(b.id) === bookingId);
                         bookingTourists = booking.pending_tourists ?? 0;
                        if (currentLoad + bookingTourists > guideCapacity) {
                            Swal.fire({
                                icon: "warning",
                                title: "Over Capacity",
                                text: "This guide’s capacity will be exceeded. Do you want to assign manually?",
                                showCancelButton: true,
                                confirmButtonText: "Yes, Assign",
                                cancelButtonText: "No, Cancel"
                            }).then(async (result) => {
                                if (result.isConfirmed) {
                                    // Open manual assign modal
                                    this.openAssignGuideModal(bookingId, toGuideId, toStationId, bookingTourists);
                                } else {
                                    // Revert move
                                    evt.from.appendChild(evt.item);
                                }
                            });
                            return;
                        }

                        // Normal assignment if within capacity
                        await this.assignBookingToGuide(bookingId, toGuideId, toStationId);
                        this.refresh();
                        return;
                    }
                   
                    if (currentLoad === 0 && bookingTourists > guideCapacity) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Over the Capacity',
                            html: `
                                <p>The number of tourists booked exceeds the guide's capacity.</p>
                                <p><strong>Guide Capacity:</strong> ${guideCapacity}</p>
                                <p><strong>Booking Tourists:</strong> ${bookingTourists}</p>
                            `,
                            confirmButtonColor: '#3085d6',
                            confirmButtonText: 'OK'
                        });
                        // Revert move
                        evt.from.appendChild(evt.item);
                        return;
                    }

                    // CASE 2: From ANOTHER GUIDE ZONE → Swap if exceeded
                    if (currentLoad + bookingTourists > guideCapacity) {
                        this.openSwapPopup(
                            toGuideId,
                            toStationId,
                            bookingId,
                            fromGuideId,
                            fromStationId
                        );
                        return;
                    }

                    const self = this;
                    const result = self.validateStationAndPackage(bookingId, toGuideId);
                    if (!result) return;

                    // Otherwise normal re-assign
                    if (fromGuideId && (fromGuideId !== toGuideId || fromStationId !== toStationId)) {
                        await this.unassignBookingFromGuide(bookingId, fromStationId, fromGuideId, { silent: true });
                    }

                    await this.assignBookingToGuide(bookingId, toGuideId, toStationId);
                    this.refresh();
                }
            }));
        });

    }

    openSwapPopup(fromGuideId, fromStationId, incomingBookingId, toGuideId, toStationId) {
        const guideBookings = this.bookings.filter(b =>
            (b.assignedGuides || []).some(ag =>
                asInt(ag.guide_id) === fromGuideId && asInt(ag.station_id) === fromStationId
            )
        );

        // const incomingBooking = this.bookings.find(b => asInt(b.id) === incomingBookingId);
        //  Find TO station
        const toStationCheck = this.stations.find(
            s => asInt(s.id) === toStationId
        );

        // Find incoming booking inside TO station, assigned to TO guide
        const incomingBooking = toStationCheck?.bookings?.find(b =>
            asInt(b.id) === incomingBookingId &&
            asInt(b.assignedGuide) === toGuideId
        );

        // Station-scoped tourists count
        const incomingTourists =
            incomingBooking?.assignedTourists ??
            incomingBooking?.tourists ??
            incomingBooking?.total_tourists ??
            0;
        const incomingInfants = incomingBooking?.infants ?? 0;    

        // const incomingTourists = incomingBooking ? (incomingBooking.assignedTourists || incomingBooking.tourists || 0) : 0;

        const fromGuide = this.guides.find(g => asInt(g.id) === fromGuideId);
        const toGuide = this.guides.find(g => asInt(g.id) === toGuideId);
        const fromStation = this.stations.find(s => asInt(s.id) === fromStationId);
        const toStation = this.stations.find(s => asInt(s.id) === toStationId);

        const fromGuideName = fromGuide ? fromGuide.guideName : "Unknown Guide";
        const toGuideName = toGuide ? toGuide.guideName : "Unknown Guide";
        const fromStationName = fromStation ? fromStation.name : "Unknown Station";
        const toStationName = toStation ? toStation.name : "Unknown Station";

        const fromGuideCapacity = fromGuide ? fromGuide.capacity : 0;
        const toGuideCapacity = toGuide ? toGuide.capacity : 0;

        // From guide assigned count (original)
        const fromAlreadyAssigned = guideBookings.reduce((sum, b) => {
            const ag = (b.assignedGuides || []).find(ag =>
                asInt(ag.guide_id) === fromGuideId && asInt(ag.station_id) === fromStationId
            );
            return sum + (ag ? (ag.tourists || 0) : 0);
        }, 0);

        // To guide assigned count (original)
        const toGuideBookings = this.bookings.filter(b =>
            (b.assignedGuides || []).some(ag =>
                asInt(ag.guide_id) === toGuideId && asInt(ag.station_id) === toStationId
            )
        );
        const toAlreadyAssigned = toGuideBookings.reduce((sum, b) => {
            const ag = (b.assignedGuides || []).find(ag =>
                asInt(ag.guide_id) === toGuideId && asInt(ag.station_id) === toStationId
            );
            return sum + (ag ? (ag.tourists || 0) : 0);
        }, 0);

        const svgPerson = `<svg style="max-width:16px;height:auto;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87m-3-12a4 4 0 0 1 0 7.75"/></g></svg>`;
        const svgClock  = `<svg style="max-width:16px;height:auto;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886"><path stroke-linejoin="round" stroke-width="1.5" d="M19.95 10.896a8.001 8.001 0 0 1-15.9 0a1.5 1.5 0 1 1 .159-2.719a8.003 8.003 0 0 1 15.583 0a1.5 1.5 0 1 1 .159 2.719Z"/><path stroke-linejoin="round" stroke-width="1.5" d="M12 15a1 1 0 0 0 1-1h-2a1 1 0 0 0 1 1Z"/><path stroke-linecap="round" stroke-width="1.5" d="M18 22c0-1.792-.786-3.4-2.031-4.5M6 22c0-1.792.786-3.4 2.031-4.5M12 2c1 0 2 .895 2 2s-1 2-2 2a2.63 2.63 0 0 1-1.5-.5"/><path stroke-linecap="round" stroke-width="2" d="M9 10v.01m6-.01v.01"/></g></svg>`;
        const badgeStyle = `background:#dbeafe;color:#2563eb;border-radius:20px;padding:2px 12px;font-size:12px;font-weight:500;white-space:nowrap;`;
        const notCheckedStyle = `background:#fde8e8;color:#e53e3e;border-radius:20px;padding:2px 12px;font-size:12px;white-space:nowrap;`;

        // Left column: incoming booking — no checkbox, pink highlighted border
        const fromCardHtml = incomingBooking ? `
            <div style="background:#fff5f6;border:2px solid #fca5a5;border-radius:10px;padding:10px 12px;margin-bottom:8px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                    <span style="font-weight:700;font-size:13px;color:#111827;">#${tenantId}-${incomingBooking.id}</span>
                    ${incomingBooking.tour_package ? `<span style="${badgeStyle}">${incomingBooking.tour_package}</span>` : ''}
                </div>
                <div style="font-weight:600;font-size:14px;color:#111827;margin-bottom:6px;text-align:left;">${incomingBooking.name}</div>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;">${svgPerson} ${incomingTourists} Guests</span>
                    <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;">${svgClock} ${incomingInfants} Infants</span>
                    <span style="${notCheckedStyle}">Not Checked In: ${incomingTourists}</span>
                </div>
            </div>
        ` : `<div class="text-muted text-center">No incoming booking</div>`;

        // Right column: guide bookings — checkbox on left, white card
        const toCardsHtml = guideBookings
            .filter(b => asInt(b.id) !== asInt(incomingBookingId))
            .map(b => `
                <div style="display:flex;align-items:flex-start;background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:8px;gap:10px;">
                    <div style="flex-shrink:0;padding-top:2px;">
                        <input type="checkbox" class="swap-booking-option booking-checkbox" value="${b.id}" data-tourists="${b.assignedTourists || 0}" style="width:16px;height:16px;cursor:pointer;accent-color:#2563eb;" />
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                            <span style="font-weight:700;font-size:13px;color:#111827;">#${tenantId}-${b.id}</span>
                            ${b.tour_package ? `<span style="${badgeStyle}">${b.tour_package}</span>` : ''}
                        </div>
                        <div style="font-weight:600;font-size:14px;color:#111827;margin-bottom:6px;text-align:left;">${b.name}</div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                            <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;">${svgPerson} ${b.assignedTourists || 0} Guests</span>
                            <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;">${svgClock} ${b.infants || 0} Infants</span>
                            <span style="${notCheckedStyle}">Not Checked In: ${b.assignedTourists || 0}</span>
                        </div>
                    </div>
                </div>
            `).join('') || `<div class="text-muted text-center">No bookings found</div>`;

        // Compose full HTML (two-column UI)
        const swapHtml = `
            <div class="d-flex gap-3 py-2" style="align-items:flex-start;">
                <div class="w-50">
                    <div class="mb-3 text-start"><span style="font-size:20px;font-weight:600;color:#d63384;">📍 From Station:</span> ${toStationName}</div>
                    ${fromCardHtml}
                    <div class="text-start" style="margin-top:8px; font-size:14px"><strong>From Guide:</strong> ${toGuideName} <strong>Capacity:</strong> ${toGuideCapacity} | <strong>Assigned:</strong> ${toAlreadyAssigned}</div>
                </div>

                <div class="w-50">
            <div class="d-flex justify-content-between mb-3">
                    <div class="text-start"><span style="font-size:20px;font-weight:600;color:#d63384;">📍 To Station:</span> ${fromStationName}</div>
                        <button type="button" class="swal2-confirm swal2-styled" aria-label="" style="display:inline-block;background:#3730a3;color:white;border:none;border-radius:6px;padding:5px 14px;margin:0;font-size:13px;font-weight:600;cursor:pointer;">Select All
                        </button>
                    </div>
                    
                    ${toCardsHtml}
                    <div class="text-start" style="margin-top:8px; font-size:14px"><strong>To Guide:</strong> ${fromGuideName} <strong>Capacity:</strong> ${fromGuideCapacity} | <strong>Assigned:</strong> ${fromAlreadyAssigned} | <strong>Incoming:</strong> ${incomingTourists}</div>
                </div>
            </div>

            <div class="px-4 w-100 text-center" id="swapValidation" style="font-weight:bold;margin-top:0;">
                <p id="fromValidation" style="color:red;">❌ From Guide after swap: ${toAlreadyAssigned}/${toGuideCapacity}</p>
                <p id="toValidation" style="color:green;">✅ To Guide after swap: ${fromAlreadyAssigned}/${fromGuideCapacity}</p>
            </div>
        `;

        // === SweetAlert (keeps original behavior) ===
        Swal.fire({
            title: "Swap Bookings",
            html: swapHtml,
            showCancelButton: true,
            confirmButtonText: "Swap & Continue",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#3730a3",
            cancelButtonColor: "#6b7280",
            width: "900px",
            didOpen: () => {
                const updateValidation = () => {
                    const selectedEls = document.querySelectorAll(".swap-booking-option:checked");
                    let swappedOut = 0;
                    selectedEls.forEach(el => swappedOut += parseInt(el.getAttribute("data-tourists")) || 0);

                    const fromNewTotal = fromAlreadyAssigned - swappedOut + incomingTourists;
                    const toNewTotal = toAlreadyAssigned - incomingTourists + swappedOut;

                    const fromEl = document.getElementById("fromValidation");
                    const toEl = document.getElementById("toValidation");

                    if (fromNewTotal <= fromGuideCapacity) {
                        fromEl.textContent = `✅ To Guide after swap: ${fromNewTotal}/${fromGuideCapacity}`;
                        fromEl.style.color = "green";
                    } else {
                        fromEl.textContent = `❌ To Guide after swap: ${fromNewTotal}/${fromGuideCapacity} (Over capacity)`;
                        fromEl.style.color = "red";
                    }

                    if (toNewTotal <= toGuideCapacity) {
                        toEl.textContent = `✅ From Guide after swap: ${toNewTotal}/${toGuideCapacity}`;
                        toEl.style.color = "green";
                    } else {
                        toEl.textContent = `❌ From Guide after swap: ${toNewTotal}/${toGuideCapacity} (Over capacity)`;
                        toEl.style.color = "red";
                    }
                };

                // Attach change listener to individual checkboxes
                document.querySelectorAll(".swap-booking-option").forEach(el =>
                    el.addEventListener("change", updateValidation)
                );

                // Add Select All / Deselect All toggle
                const selectAllBtn = document.querySelector(".swal2-popup button.swal2-confirm");
                if (selectAllBtn) {
                    selectAllBtn.addEventListener("click", function (e) {
                        e.preventDefault();
                        const checkboxes = document.querySelectorAll(".swap-booking-option");
                        const allChecked = Array.from(checkboxes).every(cb => cb.checked);

                        checkboxes.forEach(cb => cb.checked = !allChecked);

                        // Toggle button text
                        selectAllBtn.textContent = allChecked ? "Select All" : "Deselect All";

                        updateValidation();
                    });
                }

                updateValidation();
            },

          preConfirm: () => {
                const selected = Array.from(document.querySelectorAll(".swap-booking-option:checked"))
                    .map(el => parseInt(el.value));

                if (selected.length === 0) {
                    Swal.showValidationMessage("Select at least one booking to swap.");
                    return false;
                }

                // Just collect selected bookings and rely on live validation
                const hasOverCapacity = document.querySelectorAll("#swapValidation p")
                    ? Array.from(document.querySelectorAll("#swapValidation p")).some(p => p.textContent.includes("Over capacity"))
                    : false;

                if (hasOverCapacity) {
                    Swal.showValidationMessage("Swap not allowed: one of the guides exceeds capacity.");
                    return false;
                }

                // Return selected booking IDs for swap
                return selected;
            },

            didClose: () => {
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
            }
        }).then(result => {
            if (result.isConfirmed) {
                // filter duplicates (defensive) but otherwise keep same call signature
                const swapOutIds = (result.value || []).filter(id => asInt(id) !== asInt(incomingBookingId));

                this.swapBookingsBetweenStations(
                    fromStationId,
                    fromGuideId,
                    swapOutIds,
                    toStationId,
                    toGuideId,
                    [incomingBookingId]
                );
            }
        });
    }

    async swapBookingsBetweenStations(fromStationId, fromGuideId, fromBookingIds, toStationId, toGuideId, toBookingIds) {

        return new Promise((resolve, reject) => {
        showLoading();
        $.ajax({
            url: swapBookingUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: {
                from_station_id: fromStationId,
                from_guide_id: fromGuideId,
                from_booking_ids: fromBookingIds,
                to_station_id: toStationId,
                to_guide_id: toGuideId,
                to_booking_ids: toBookingIds,
                time_from: currentTimeFrom,
                date: currentDate
            },
            success: function (response) {
                hideLoading();
                if (response.success) {
                    showToast(response.message, 'success');
                    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                    resolve();
                } else {
                    showToast(response.message, 'error');
                    reject();
                }
            },
            error: function (err) {
                hideLoading();
                showToast('Swap request failed', 'error');
                reject(err);
            }
        });
        });
    }

    // ---------- Assign/Unassign ----------
    assignGuideToStation(guideId, stationId) {
        const self = this;

        $.ajax({
            url: assignGuideAndBookingToStationUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: { station_id: stationId, tour_guide_id: guideId, tour_slot_id: currentSlotId, 
                time_from: currentTimeFrom, date: currentDate },
            success: function () {

                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);

                showToast('Guide assigned successfully!', 'success');
            },
            error: function (err) {
                showToast('Failed to assign guide.', 'error');
                self.refresh();
            }
        });
    }

    validateStationAndPackage(bookingId, guideId) {
        const self = this;

        // Find station containing this guide
        const station = self.stations.find(st => {
            const ids = getStationAssignedGuides(st).map(asInt);
            return ids.includes(asInt(guideId));
        });

        if (!station) return null;

        const booking = self.bookings.find(b => asInt(b.id) === asInt(bookingId));
        if (!booking) return null;

        const allowedPackageIds = (station.packages || []).map(p => asInt(p.id));

        // Restrict if booking package not in station
        if (!allowedPackageIds.includes(parseInt(booking.tour_package_id))) {
            Swal.fire({
                title: '⚠️ Package Not Allowed',
                text: "This booking’s package cannot be assigned to this station.",
                icon: 'warning',
                confirmButtonText: 'OK'
            }).then(() => self.refresh());
            return null;
        }

        return { station, booking };
    }

    assignBookingToGuide(bookingId, guideId) {
        const self = this;
        const result = self.validateStationAndPackage(bookingId, guideId);
        if (!result) return; // stop if invalid

        const { station, booking } = result;

        // ---------------- Capacity Validation ----------------
        const gId = asInt(guideId);
        const guide = this.guides.find(g => asInt(g.id) === gId);

        const guideBookings = this.bookings.filter(b =>
            (b.assignedGuides || []).some(ag =>
                asInt(ag.guide_id) === gId && asInt(ag.station_id) === asInt(station.id)
            )
        );

        const totalGuests = guideBookings.reduce((sum, b) => {
            const ag = (b.assignedGuides || []).find(ag =>
                asInt(ag.guide_id) === gId && asInt(ag.station_id) === asInt(station.id)
            );
            return sum + (ag ? (ag.tourists || 0) : 0);
        }, 0);


        if (guide && totalGuests >= guide.capacity) {
            Swal.fire({
                title: '⚠️ Capacity Full',
                text: `Guide ${guide.guideName} already has ${totalGuests} / ${guide.capacity} tourists assigned. Cannot assign more.`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }
        // ------------------------------------------------------
        showLoading();
        $.ajax({
            url: assignGuideAndBookingToStationUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: { 
                station_id: station.id, 
                tour_booking_id: bookingId, 
                tour_guide_id: guideId,
                time_from: currentTimeFrom, 
                date: currentDate
            },
            success: function (response) {
                hideLoading();
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                showToast('Booking assigned successfully!', 'success');
            },
            error: function (err) {
                showToast('Failed to assign booking.', 'error');
                self.refresh();
            }
        });
    }

    unassignBookingFromGuide(bookingId, stationId, guideId) {
        const self = this;
        const result = self.validateStationAndPackage(bookingId, guideId);

        if (!result) return;
        const { station } = result;
        showLoading();

        return new Promise((resolve, reject) => {
            $.ajax({
                url: unAssignBookingUrl,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: {
                    tour_booking_id: bookingId,
                    time_from: currentTimeFrom,
                    date: currentDate,
                    station_id: stationId,
                    tour_guide_id: guideId
                },
                success: function (response) {
                    hideLoading();
                    if (response.success) {
                        loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                        showToast('Booking unassign successfully!', 'success');
                        resolve(response); //resolve after modal confirm
                    } else {
                        reject(new Error("Unassign failed."));
                    }
                },
                error: function (err) {
                    console.error('Failed to unassign booking', err);
                    self.refresh();
                    reject(err);
                }
            });
        });
    }

    bulkAssign(bookingIds, guideId, stationId) {
        showLoading();

        $.ajax({
            url: bulkAssignUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: { station_id: stationId, tour_guide_id: guideId, time_from: currentTimeFrom, date: currentDate , tour_booking_ids: bookingIds},
            success: function (response) {
                hideLoading(); // stop loading

                if (response.success) {
                    Swal.close();
                    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                    showToast('Bookings have been assigned successfully!', 'success');
                }
            },
            error: function () {
                hideLoading(); // stop loading
                showToast('Server error. Please try again.', 'error');
            }
        });
    }

    unassignGuideFromStation(guideId, stationId) {
        showLoading();
         $.ajax({
            url: unAssignGuideUrl,
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            data: { station_id: stationId, tour_guide_id: guideId, time_from: currentTimeFrom, date: currentDate },
            success: function (response) {
                hideLoading();
                loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                showToast('Guide unassign successfully!', 'success');
               
            },
            error: function (err) {
                console.error('Failed to unassign guide', err);
                self.refresh();
            }
        });
    }

    openAssignGuideModal(bookingId, preselectGuideId = null, preselectStationId = null, prefillTourists = null) {
        showLoading();
        const self = this;
        $.ajax({
            url: getAssignedGuidesUrl.replace(':bookingId', bookingId),
            method: 'GET',
            success: function (response) {
                renderAssignedGuidesDetails.call(
                    self,
                    response,
                    bookingId,
                    preselectGuideId,
                    preselectStationId,
                    prefillTourists
                );
                hideLoading();
                $("#assignGuidesModal").modal('show');
            },
            error: function (err) {
                console.error('Failed to load assigned guides', err);
                self.refresh();
            }
        });
    }

    // ---------- Redraw ----------
    refresh() {
        this.renderGuides();
        this.renderNotAvailableGuides();
        this.renderBookings();
        this.renderAbsentBookings();
        this.renderStations();
        this.initializeDragAndDrop();
    }
}

// ---------- AJAX integration ----------
let tourismBoard = null;
let currentSlotId = null;
let currentTimeFrom = null;
let currentDate = null;
let stationOneGuide = true; // or false

function loadGuideManagement(slotId, timeFrom, date, silent = false) {
    currentSlotId = slotId;
    currentTimeFrom = timeFrom;
    currentDate = date;
    if (!silent) showLoading();
    // clear copied booking if TourismBoard instance available
    if (window.tourismBoard && typeof window.tourismBoard.resetCopiedBooking === 'function') {
        window.tourismBoard.resetCopiedBooking();
    }
    $.ajax({
        url: boardGuideManagementUrl,
        type: 'GET',
        data: { tour_slot_id: slotId, time_from: timeFrom, date: date },
        success: function (response) {
            const guides   = toArray(response.guides);
            const stations = toArray(response.stations);
            const bookings = toArray(response.bookings);
            const absentBookings = toArray(response.absent_bookings);
            const notAvailableGuides = toArray(response.notAvailableGuides);
            stationOneGuide = response.stationOneGuide;
            // Set date & time inside modal header
            $('#guideCapacityDisplay').text(
                '(' + response.totalAssignedTourists + '/' + response.totalGuideCapacity + ')'
            );
            // Total pending tourist and infants
            $('#totalPendingTourist').text(response.pendingTourists);
            $('#totalPendingInfants').text(response.pendingInfants);
            // If no unassigned bookings → hide Auto-Assign button
            if (response.bookings && response.bookings.length === 0) {
                $('#autoAssignBtn').hide();
            } else {
                $('#autoAssignBtn').show();
            }

            if (!tourismBoard) {
                tourismBoard = new TourismBoard({ guides, stations, bookings, absentBookings, notAvailableGuides });
                tourismBoard.init();
            } else {
                tourismBoard.updateData({ guides, stations, bookings, absentBookings, notAvailableGuides });
            }
            if (!silent) hideLoading();
        },
        error: function (err) {
            console.error('Failed to load guide management data', err);
        }
    });
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

$('#assignGuidesModal').on('hidden.bs.modal', function () {
    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
});

// Modal open function
$(document).on('click', '.guide-management-btn', function () {
    $('#guidesList').empty();
    $('#pendingBookingsList').empty();
    $('#stationsWrapper').empty();

    const slotId   = $(this).data('slot-id');
    const timeFrom = $(this).data('time-from'); // e.g. "14:30:00"
    const date     = $(this).data('date');      // e.g. "2025-08-26"
    const { DateTime } = luxon;

    // ✅ Combine date + time and format with timezone
    const fullDateTime = DateTime.fromISO(`${date}T${timeFrom}`, { zone: timezone });

    // Format date: Tuesday, August 26, 2025
    const formattedDate = fullDateTime.toFormat("cccc, LLLL dd, yyyy");

    // Format time: 2:30 PM
    const formattedTime = fullDateTime.toFormat("h:mm a");

    // Set date & time inside modal header
    $('#guideDate').text(formattedDate);
    $('#guideTime').text(formattedTime);

    // Show modal + load data
    $('#guideManagementModal').modal('show');
    loadGuideManagement(slotId, timeFrom, date);

    // Save modal state for reload
    sessionStorage.setItem("guideModalOpen", "true");
    sessionStorage.setItem("guideSlotId", slotId);
    sessionStorage.setItem("guideTimeFrom", timeFrom);
    sessionStorage.setItem("guideDate", date);
});


// Clear state when modal closed
$('#guideManagementModal').on('hidden.bs.modal', function () {
    sessionStorage.removeItem("guideModalOpen");
    sessionStorage.removeItem("guideSlotId");
    sessionStorage.removeItem("guideTimeFrom");
    sessionStorage.removeItem("guideDate");
});

// Reopen modal on page reload if it was open
$(window).on("load", function () {
    if (sessionStorage.getItem("guideModalOpen") === "true") {
        const slotId   = sessionStorage.getItem("guideSlotId");
        const timeFrom = sessionStorage.getItem("guideTimeFrom");
        const date     = sessionStorage.getItem("guideDate");
        const { DateTime } = luxon;

        // ✅ Combine date + time and apply timezone
        const fullDateTime = DateTime.fromISO(`${date}T${timeFrom}`, { zone: timezone });

        // Format date: Tuesday, August 26, 2025
        const formattedDate = fullDateTime.toFormat("cccc, LLLL dd, yyyy");

        // Format time: 2:30 PM
        const formattedTime = fullDateTime.toFormat("h:mm a");

        // Set values into modal
        $('#guideDate').text(formattedDate);
        $('#guideTime').text(formattedTime);

        // Show modal and reload data
        $('#guideManagementModal').modal('show');
        loadGuideManagement(slotId, timeFrom, date);
    }
});

document.getElementById('guideManagementModal').addEventListener('hidden.bs.modal', function () {
    const { DateTime } = luxon;
    const formattedDate = getCalendarSelectedDate(); // e.g., "2025-11-13"
    if (!formattedDate) return;

    const day = DateTime.fromISO(formattedDate, { zone: timezone });
    const currentMonth = day.startOf("month");
    const fromDate = currentMonth.toFormat("yyyy-MM-dd HH:mm:ss");
    const toDate = currentMonth.endOf("month").toFormat("yyyy-MM-dd HH:mm:ss");

    showLoading();

    // --- 1️⃣ First call: by selected date ---
    fetch(`${indexUrl}?date=${encodeURIComponent(day.toFormat("yyyy-MM-dd HH:mm:ss"))}`, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(res => res.json())
    .then(html => {
        // Update board-related content
        document.getElementById("boardData").innerHTML = html.slotsHtml;
        document.getElementById("stationTableBody").innerHTML = html.stationListHtml;
        document.getElementById("stationAdd").innerHTML = html.stationAddHtml;
        document.getElementById("stationEdit").innerHTML = html.stationEditHtml;

        // --- 2️⃣ Then call for the full month assignments ---
        return fetch(`${indexUrl}?from_date=${encodeURIComponent(fromDate)}&to_date=${encodeURIComponent(toDate)}`, {
            headers: { "X-Requested-With": "XMLHttpRequest" }
        });
    })
    .then(res => res.json())
    .then(monthHtml => {
        // ✅ Update calendar day boxes
        if (monthHtml.assignments) {
            updateCalendarCounts(monthHtml.assignments, formattedDate);
        }
    })
    .catch(error => {
        console.error("Error loading board data:", error);
    })
    .finally(() => {
        hideLoading();
    });
});

// Function: Update Calendar Counts
function updateCalendarCounts(assignments, activeDate = null) {
    try {
        // store assignments globally so calendar.js stays in sync
        window.assignments = assignments;

        // loop through all calendar day boxes
        document.querySelectorAll('.day-box').forEach(box => {
            const date = box.dataset.date;
            if (!date) return;

            // get count for that date
            const count = assignments[date] || "";
            const countLabel = box.querySelector('.count-label');

            // update or create the count label
            if (countLabel) {
                countLabel.textContent = count;
            } else {
                const newLabel = document.createElement('div');
                newLabel.className = 'count-label';
                newLabel.textContent = count;
                box.appendChild(newLabel);
            }
        });

        // highlight currently active date again
        document.querySelectorAll('.day-box').forEach(box => box.classList.remove('active'));
        if (activeDate) {
            const activeBox = document.querySelector(`.day-box[data-date="${activeDate}"]`);
            if (activeBox) activeBox.classList.add('active');
        }
    } catch (err) {
        console.error("Error updating calendar counts:", err);
    }
}

document.addEventListener('click', function (e) {
    // --- Handle Auto-Assign button ---
    if (e.target && e.target.id === 'autoAssignBtn') {
        showLoading();
        $.ajax({
            url: autoAssignUrl,
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
                    Swal.close();
                    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                    showToast('Auto-assign completed!', 'success');
                }
            },
            error: function () {
                hideLoading();
                showToast('Something went wrong. Please try again!', 'error');
            }
        });
    }

    // --- Handle Assign button click inside station card ---
    if (e.target && e.target.classList.contains('assign-btn')) {
        const stationId = e.target.getAttribute('data-station-id');
        const station = tourismBoard.stations.find(s => asInt(s.id) === asInt(stationId));
        if (!station) return;

        const allowedPackageIds = (station.packages || []).map(p => asInt(p.id));
        const pendingBookings = tourismBoard.bookings.filter(
            b => b.assignedGuide == null && allowedPackageIds.includes(asInt(b.tour_package_id))
        );

        if (pendingBookings.length === 0) {
            Swal.fire({
                title: 'No Pending Bookings',
                text: "There are no pending bookings for this station",
                icon: 'info',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Get assigned guide
        const stationGuideIds = getStationAssignedGuides(station).map(asInt);
        if (stationGuideIds.length === 0) {
            Swal.fire({
                title: '⚠️ No Guide Assigned',
                text: "Please assign a guide to this station first before assigning bookings.",
                icon: 'warning'
            });
            return;
        }

        const guideId = stationGuideIds[0];
        const guide = tourismBoard.guides.find(g => asInt(g.id) === guideId);
        if (!guide) return;

        const guideCapacity = guide.capacity || 0;

        // Already assigned tourists for this guide at this station
        const guideBookings = tourismBoard.bookings.filter(b =>
            (b.assignedGuides || []).some(ag =>
                asInt(ag.guide_id) === guideId && asInt(ag.station_id) === asInt(station.id)
            )
        );
        const alreadyAssigned = guideBookings.reduce((sum, b) => {
            const ag = (b.assignedGuides || []).find(ag =>
                asInt(ag.guide_id) === guideId && asInt(ag.station_id) === asInt(station.id)
            );
            return sum + (ag ? (ag.tourists || 0) : 0);
        }, 0);

        // --- Check if guide is already at full capacity ---
        if (guideCapacity === alreadyAssigned) {
            Swal.fire({
                title: '⚠️ Capacity Full',
                text: `${guide.guideName} already has all ${guideCapacity} tourists assigned. Cannot assign more.`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return; // stop further execution
        }

        const pbSvgPerson = `<svg style="max-width:15px;height:auto;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87m-3-12a4 4 0 0 1 0 7.75"/></g></svg>`;
        const pbSvgInfant = `<svg style="max-width:15px;height:auto;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886"><path stroke-linejoin="round" stroke-width="1.5" d="M19.95 10.896a8.001 8.001 0 0 1-15.9 0a1.5 1.5 0 1 1 .159-2.719a8.003 8.003 0 0 1 15.583 0a1.5 1.5 0 1 1 .159 2.719Z"/><path stroke-linejoin="round" stroke-width="1.5" d="M12 15a1 1 0 0 0 1-1h-2a1 1 0 0 0 1 1Z"/><path stroke-linecap="round" stroke-width="1.5" d="M18 22c0-1.792-.786-3.4-2.031-4.5M6 22c0-1.792.786-3.4 2.031-4.5M12 2c1 0 2 .895 2 2s-1 2-2 2a2.63 2.63 0 0 1-1.5-.5"/><path stroke-linecap="round" stroke-width="2" d="M9 10v.01m6-.01v.01"/></g></svg>`;

        // Add Select All Checkbox + Capacity Info
        const selectAllHtml = `
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <label style="display:flex;align-items:center;gap:6px;font-weight:600;font-size:13px;cursor:pointer;">
                    <input type="checkbox" id="selectAllBookings" style="width:16px;height:16px;accent-color:#2563eb;" /> Select All
                </label>
                <span id="capacityInfo" style="font-weight:700;font-size:13px;color:#2563eb;">
                    Capacity: ${guideCapacity} | Assigned: ${alreadyAssigned}
                </span>
            </div>
        `;

        // Build booking list
        const listHtml = pendingBookings.map((b, idx) => {

            // Check-In Calculation
            const assignedGuests = b.tourists || 0;
            const checkIns = { "1": 0, "2": 0, "A": 0, ...(b.checkInsCount || {}) };
            const totalCheckIns = (checkIns["1"] || 0) + (checkIns["2"] || 0);
            const pending = Math.max(assignedGuests - totalCheckIns, 0);

            let checkInHtml = "";
            if (pending > 0 && checkIns["1"] === 0 && checkIns["2"] === 0 && checkIns["A"] === 0) {
                checkInHtml = `<span style="background:#fde8e8;color:#e53e3e;border-radius:20px;padding:2px 10px;font-size:12px;">Not Checked In: ${pending}</span>`;
            } else {
                const parts = [];
                if (checkIns["1"] > 0) parts.push(`<span style="background:#fef9c3;color:#92400e;border-radius:20px;padding:2px 10px;font-size:12px;">1st Check In: ${checkIns["1"]}</span>`);
                if (checkIns["2"] > 0) parts.push(`<span style="background:#fef08a;color:#78350f;border-radius:20px;padding:2px 10px;font-size:12px;">2nd Check In: ${checkIns["2"]}</span>`);
                if (pending > 0)       parts.push(`<span style="background:#fde8e8;color:#e53e3e;border-radius:20px;padding:2px 10px;font-size:12px;">Not Checked In: ${pending}</span>`);
                checkInHtml = parts.join(" ");
            }

            return `
                <div style="display:flex;align-items:flex-start;background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:8px;gap:10px;">
                    <div style="flex-shrink:0;padding-top:2px;">
                        <input type="checkbox" class="booking-checkbox" value="${b.id}" data-tourists="${b.tourists - b.assigned_tourists}" style="width:16px;height:16px;cursor:pointer;accent-color:#2563eb;" />
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                            <span style="font-weight:700;font-size:13px;color:#111827;">#${tenantId}-${b.id}</span>
                            ${b.tour_package ? `<span style="background:#dbeafe;color:#2563eb;border-radius:20px;padding:2px 12px;font-size:12px;font-weight:500;white-space:nowrap;">${b.tour_package}</span>` : ''}
                            ${b.affiliates ? `<span style="background:#f3f4f6;color:#6b7280;border-radius:20px;padding:2px 10px;font-size:12px;">${b.affiliates}</span>` : ''}
                        </div>
                        <div style="font-weight:600;font-size:14px;color:#111827;margin-bottom:6px;text-align:left;">${b.name || 'Unknown'}</div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
                            <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#1E90FF; font-weight:bold;">${pbSvgPerson} Total: ${b.tourists}</span>
                            <span style="font-size:12px;color:#16a34a;font-weight:600;">✔ Assigned: ${b.assigned_tourists}</span>
                            <span style="font-size:12px;color:#dc3545;font-weight:600;">⏳ Pending: ${b.tourists - b.assigned_tourists}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
                            <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#1E90FF; font-weight:bold;;">${pbSvgInfant} Total Infants: ${b.infants}</span>
                            <span style="font-size:12px;color:#16a34a;font-weight:600;">✔ Assigned: ${b.assigned_infants}</span>
                            <span style="font-size:12px;color:#dc3545;font-weight:600;">⏳ Pending: ${b.infants - b.assigned_infants}</span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">${checkInHtml}</div>
                    </div>
                </div>
            `;
        }).join('');

        Swal.fire({
            title: `📦 Pending Bookings for ${station.name}`,
            html: `
              <div style="text-align:left; border-bottom:1px solid #e5e7eb; padding-bottom:8px; margin-bottom:8px;">
                ${selectAllHtml}
              </div>
              <div style="max-height:340px; overflow-y:auto; text-align:left; padding-right:4px;">
                ${listHtml}
              </div>
              <button id="assignSelectedBtn" style="margin-top:14px;background:#3730a3;color:white;border:none;border-radius:8px;padding:10px 32px;font-size:14px;font-weight:600;cursor:pointer;">Assign</button>
            `,
            showConfirmButton: false,
            showCloseButton: true,
            width: 650,
            didOpen: () => {
                const capacityInfo = document.getElementById("capacityInfo");

                function updateSelectedCount() {
                    const selected = document.querySelectorAll('.booking-checkbox:checked');
                    const selectedGuests = Array.from(selected).reduce((sum, cb) => {
                        return sum + parseInt(cb.getAttribute("data-tourists") || 0);
                    }, 0);

                    const total = alreadyAssigned + selectedGuests;

                    capacityInfo.textContent = `Capacity: ${guideCapacity} | Assigned: ${total}`;

                    if (total > guideCapacity) {
                        capacityInfo.classList.remove("text-primary");
                        capacityInfo.classList.add("text-danger");
                    } else {
                        capacityInfo.classList.remove("text-danger");
                        capacityInfo.classList.add("text-primary");
                    }

                    return { selectedGuests, total };
                }

                // Select All Toggle
                document.getElementById("selectAllBookings").addEventListener("change", function(e) {
                    const checkboxes = document.querySelectorAll('.booking-checkbox');
                    checkboxes.forEach(cb => cb.checked = e.target.checked);
                    updateSelectedCount();
                });

                // Checkbox change updates selected count
                document.querySelectorAll('.booking-checkbox').forEach(cb => {
                    cb.addEventListener("change", updateSelectedCount);
                });

                // Handle Assign Button
                document.getElementById("assignSelectedBtn").addEventListener("click", function () {
                    const selectedIds = Array.from(
                        document.querySelectorAll('.booking-checkbox:checked')
                    ).map(cb => cb.value);

                    const { selectedGuests, total } = updateSelectedCount();

                    if (selectedIds.length === 0) {
                        Swal.fire({
                            title: '⚠️ No Selection',
                            text: "Please select at least one booking to assign.",
                            icon: 'warning'
                        });
                        return;
                    }

                    if (total > guideCapacity) {
                        Swal.fire({
                            title: '⚠️ Capacity Exceeded',
                            text: `This guide can take maximum ${guideCapacity} tourists, but you selected ${selectedGuests} on top of ${alreadyAssigned} already assigned (Total: ${total}).`,
                            icon: 'error'
                        });
                        return;
                    }

                    tourismBoard.bulkAssign(selectedIds, guideId, stationId);
                });
            }
        });
    }

    // --- Handle single booking assign click inside SweetAlert ---
    if (e.target && e.target.classList.contains('assign-booking-btn')) {
        const bookingId = e.target.getAttribute('data-booking-id');
        const stationId = e.target.getAttribute('data-station-id');
        const station = tourismBoard.stations.find(s => asInt(s.id) === asInt(stationId));
        if (!station) return;

        const stationGuideIds = getStationAssignedGuides(station).map(asInt);
        if (stationGuideIds.length === 0) {
            Swal.fire({
                title: '⚠️ No Guide Assigned',
                text: "Please assign a guide to this station first before assigning bookings.",
                icon: 'warning'
            });
            return;
        }

        const guideId = stationGuideIds[0];
        const guide = tourismBoard.guides.find(g => asInt(g.id) === guideId);
        if (!guide) return;

        const booking = tourismBoard.bookings.find(b => asInt(b.id) === asInt(bookingId));
        if (!booking) return;

        const availableCapacity = guide.capacity - (guide.tourists || 0);

        if ((booking.tourists || 0) > availableCapacity) {
            Swal.fire({
                title: '⚠️ Capacity Exceeded',
                text: `${guide.guideName} has only ${availableCapacity} spots left, but this booking requires ${booking.tourists}.`,
                icon: 'error',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Assign to the first guide if capacity is okay
        tourismBoard.assignBookingToGuide(bookingId, guideId);

        Swal.fire({
            title: '✅ Success!',
            text: "Booking has been assigned successfully.",
            icon: 'success',
            confirmButtonText: 'OK'
        }).then(() => {
            loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
        });
    }

    // --- Check-in station wise bulk update ---
    if (e.target.classList.contains('checkin-btn')) {
        const btn = e.target;
        const stationId = btn.getAttribute('data-station-id');
        const guideId   = btn.getAttribute('data-guide-id');
        let action      = btn.getAttribute('data-action');

        // Find bookings for this guide/station
        const guideBookings = (tourismBoard.bookings || []).filter(b =>
            (b.assignedGuides || []).some(ag =>
                asInt(ag.guide_id) === asInt(guideId) && asInt(ag.station_id) === asInt(stationId)
            )
        );

        if (!guideBookings.length) {
            Swal.fire({
                title: '⚠️ No Bookings',
                text: 'No bookings assigned to this guide at this station.',
                icon: 'info',
                confirmButtonText: 'OK'
            });
            return;
        }

        const hasUncheckedBookings = guideBookings.filter(b => {
                const ag = (b.assignedGuides || []).find(ag =>
                    asInt(ag.guide_id) === asInt(guideId) && asInt(ag.station_id) === asInt(stationId)
                );

                const assignedGuests = ag ? (ag.tourists || 0) : 0;
                const checkIns = { '1': 0, '2': 0, 'A': 0, ...(b.checkInsCount || {}) };
                const checkedIn = parseInt(checkIns['1']) + parseInt(checkIns['2']);

                return checkedIn < assignedGuests;
        });

        if (hasUncheckedBookings.length > 0) {
            Swal.fire({
                title: '⚠️ 1st Check-In Missing',
                text: 'Some bookings are not fully 1st-checked in. Please complete all 1st check-ins before moving to 2nd.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }
        // --- First click: Ready → 2nd Check In (no AJAX) ---
        if (action === 'ready') {

            // Flip locally (Ready → 2nd Check In)
            btn.textContent = "2nd Check In";
            btn.classList.remove("btn-success");
            btn.classList.add("btn-warning");
            btn.setAttribute("data-action", "second");

            $.ajax({
                url: readyStatusUpdateUrl,
                type: "POST",
                data: {
                    slot_id: currentSlotId,
                    time_from: currentTimeFrom,
                    date: currentDate,
                    station_id: stationId,
                    guide_id: guideId,
                    action: action,
                    _token: csrfToken
                },
                success: function (response) {
                    
                },
                error: function () {
                    hideLoading();
                    showToast('Something went wrong. Please try again!', 'error');
                }
            });
            return;
        }

        // --- Second click: do actual 2nd Check In via AJAX ---
        showLoading();
        if (action === 'second') {
            $.ajax({
                url: bulkCheckInUrl,
                type: "POST",
                data: {
                    slot_id: currentSlotId,
                    time_from: currentTimeFrom,
                    date: currentDate,
                    station_id: stationId,
                    guide_id: guideId,
                    action: 'second',
                    _token: csrfToken
                },
                success: function (response) {
                    hideLoading();
                    if (response.success) {
                        loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                        showToast(response.message, 'success');
                    } else {
                        showToast('Could not complete 2nd check-in.', 'error');
                    }
                },
                error: function () {
                    hideLoading();
                    showToast('Something went wrong. Please try again!', 'error');
                }
            });
        }
    }

});

// open modal when clicking the new per-booking assign button
$(document).on('click', '.assign-tour-guide-btn', function () {
    const bookingId = $(this).data('booking-id');
    const stationId = $(this).data('station-id');

    const booking = tourismBoard.bookings.find(b => asInt(b.id) === asInt(bookingId));
    if (!booking) return;

    // Gather all assigned entries for this booking
    const assignedEntries = (booking.assignedGuides || []).map(ag => ({
        guide_id: asInt(ag.guide_id),
        station_id: ag.station_id != null ? asInt(ag.station_id) : null,
        tourists: asInt(ag.tourists || 0)
    }));

    // For each assigned entry try to find guide info
    let rows = '';
    assignedEntries.forEach((ae, idx) => {
        const guide = tourismBoard.guides.find(g => asInt(g.id) === asInt(ae.guide_id));
        const guideName = guide ? (guide.guideName) : `Guide #${ae.guide_id}`;

        rows += `
            <tr>
                <td>${guideName} ${ae.station_id ? `<small class="text-muted"> (Station ${ae.station_id})</small>` : ''}</td>
                <td>
                  <input type="number" class="form-control assign-split-input" 
                         data-guide-id="${ae.guide_id}" value="${ae.tourists}" min="0">
                </td>
            </tr>
        `;
    });

    // also show total booking tourists for reference
    const modalHtml = `
        <div>
            <p><strong>Booking:</strong> ${tenantId ? tenantId + '-' : ''}${booking.id} — ${booking.tour_package || ''}</p>
            <p><strong>Total Tourists:</strong> ${booking.tourists}</p>
            <div style="max-height:300px; overflow:auto;">
                <table class="table">
                    <thead><tr><th>Guide</th><th>Assigned Tourists</th></tr></thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
            <div id="assignSplitError" class="text-danger" style="display:none;">Sum of splits must equal total tourists.</div>
        </div>
    `;

    $('#assignTourGuideBody').html(modalHtml);
    $('#assignTourGuideModal').modal('show');

    // store metadata for save
    $('#assignTourGuideModal').data('booking-id', bookingId);
    $('#assignTourGuideModal').data('station-id', stationId);
});

// Manual update start
function removeGuideOption(evt) {
    const assignmentId = $(evt.currentTarget).data('id');
    const guideOpt = $(evt.currentTarget).closest('.guide-opt');

    // Case: No assignment ID (new unsaved guide)
    if (assignmentId === '') {
        if ($('.guide-opt').length > 1) {
            guideOpt.remove();
        } else {
            Swal.fire({
                title: 'Sorry!',
                text: 'At least one guide option is required.',
                icon: 'warning'
            });
        }
        return;
    }

    Swal.fire({
        title: 'Remove Tour Guide',
        text: 'Are you sure you want to remove this guide from the booking?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, remove',
        cancelButtonText: 'No',
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#dc3545'
    }).then((result) => {
        if (!result.isConfirmed) return;

        const bookingId = $('#assignGuidesModal #booking-id').val();
        const postData = {
            guides: [{ id: assignmentId, is_deleted: 1,  time_from: currentTimeFrom,
                    date: currentDate, }]
        };
        const newassignTourGuideURL = assignTourGuideUrl.replace(':bookingId', bookingId);

        // showLoading();

        $.post(newassignTourGuideURL, postData)
            .done(function () {
                if ($('.guide-opt').length > 1) {
                    guideOpt.remove();
                } else {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sorry!',
                        text: 'At least one guide option is required.'
                    });
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Tour guide removed successfully!'
                }).then(() => {
                    $("#assignGuidesModal").modal('hide');
                    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                });

            })
            .fail(showSubmissionError)
            .always(hideLoading);
    });
}

function renderAssignedGuidesDetails(data, bookingId, preselectGuideId = null, preselectStationId = null, prefillTourists = null) {
    var bookingInfo = data.bookingInfo;
    var assignedGuides = data.assignedGuides;
    let bookingInfoHtml = `
        <div class="col-md-6">
            <strong>Booking ID:</strong> <span id="modalBookingId" style="color: #0d6efd;">#${bookingInfo.id}</span>
            <input type="hidden" id="booking-id" value="${bookingId}">
            <input type="hidden" id="totalTourists" value="${bookingInfo.tourists}" />
        </div>
        <div class="col-md-6">
            <strong>Customer:</strong> <span id="modalCustomer">${bookingInfo.customer_name}</span>
        </div>
        <div class="col-md-6">
            <strong>Tour:</strong> <span id="modalTour">${bookingInfo.package_name}</span>
        </div>
        <div class="col-md-6">
            <strong>Date:</strong> <span id="modalDate">${bookingInfo.date}</span>
        </div>
        <div class="col-md-6">
            <strong>Tourists:</strong> <span id="modalTotal">${bookingInfo.tourists}</span>
        </div>
        <div class="col-md-6">
            <strong>Pending:</strong> <span id="modalPending" style="color: red; font-weight: bold;">${bookingInfo.tourists}</span>
        </div>
        <div class="col-md-6">
            <strong>Infants:</strong>
            <span id="modalTotalInfants">${bookingInfo.infants || 0}</span>
            <input type="hidden" id="totalInfants" value="${bookingInfo.infants || 0}">
        </div>

        <div class="col-md-6">
            <strong>Pending Infants:</strong>
            <span id="modalPendingInfants"
                  style="color:#0d6efd;font-weight:bold;">
                ${bookingInfo.infants || 0}
            </span>
        </div>`;

    $("#assignGuidesModal #booking-info-div").html(bookingInfoHtml);

    let assignedGuidesHtml = '';
    let guideOptionsHtml = '';
    const guidesList = tourismBoard.guides || [];

    if (assignedGuides.length > 0) {
        $.each(assignedGuides, function (index, guide) {
            assignedGuidesHtml += `<span class="badge bg-success">${guide.guide_name}</span>`;
            let guideOptions = '<option></option>';
            for (let g of guidesList) {
                const { alreadyAssigned, capacity, stationNames } = getGuideAssignmentInfo(g.id);
                const isAssigned = stationNames && stationNames.length > 0;

                let stationsText = '';
                if (isAssigned) {
                    stationsText = ' : ' + stationNames.join(', ');
                }

                guideOptions += `
                    <option value="${g.id}" ${g.id == guide.guide_id ? "selected" : ""}>
                        ${g.guideName}
                        ${String(alreadyAssigned).padStart(2, '0')}/${capacity}
                        ${stationsText}
                    </option>`;
            }
            guideOptionsHtml += renderGuideOptionDiv(guideOptions, guide.booking_guide_id, guide.tourists, guide.infants);
        });
        guideOptionsHtml += `<button type="button" class="btn btn-primary btn-sm mb-2 addGuideOptbtn" style="margin-left: 7px;">
                                <i class="fas fa-plus me-1"></i>
                             </button>`;
        $('#assignGuidesModal #guideOptsDiv').html(guideOptionsHtml);

    } else {
        let guideOptions = '<option></option>';
        const guidesList = tourismBoard.guides || [];

        // Separate station-assigned and unassigned guides
        const stationAssignedGuides = [];
        const unassignedGuides = [];

        for (let g of guidesList) {
            const { alreadyAssigned, capacity, stationNames } = getGuideAssignmentInfo(g.id);
            const isAssigned = stationNames && stationNames.length > 0;

            let stationsHtml = '';
            if (isAssigned) {
                // Assign different colors for multiple stations
                stationsHtml = stationNames
                    .map(st => `<span style="color:#d63384; font-weight:600;">${st}</span>`)
                    .join(', ');
            }

            const optionHTML = `
                <option value="${g.id}" data-html-option="true" data-display-name="${g.guideName}"
                    ${(preselectGuideId && preselectGuideId == g.id) ? "selected" : ""}>
                    ${g.guideName} ${String(alreadyAssigned).padStart(2, '0')}/${capacity}
                    ${isAssigned ? ` : ${stationsHtml}` : ''}
                </option>`;

            if (isAssigned) {
                stationAssignedGuides.push(optionHTML);
            } else {
                unassignedGuides.push(optionHTML);
            }
        }

        // Combine assigned first, then unassigned
        guideOptions += stationAssignedGuides.join('') + unassignedGuides.join('');

        guideOptionsHtml += `<div class="row mb-3 guide-opt align-items-end">
            <div class="col-md-4">
                <label class="form-label">Guide</label>
                <select class="form-select tour-guide" name="tour_guide">
                    ${guideOptions}
                </select>
                <div class="invalid-feedback">Please select guide.</div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Tourists</label>
                <input type="number"
                       min="1"
                       class="form-control guide-tourists"
                       name="guide_tourists"
                       value="${prefillTourists ? prefillTourists : ''}"
                       placeholder="Tourists"
                       required>
                <div class="invalid-feedback">Please enter tourists.</div>
            </div>

            <div class="col-md-3">
                <label class="form-label">Infants</label>
                <input type="number"
                       min="0"
                       class="form-control guide-infants"
                       name="guide_infants"
                       placeholder="Infants">
            </div>

            <div class="col-md-2">
                <button type="button"
                        class="btn btn-primary btn-sm addGuideOptbtn">
                    <i class="fas fa-plus me-1"></i>
                </button>
            </div>
        </div>`;

        $('#assignGuidesModal #guideOptsDiv').html(guideOptionsHtml);
        assignedGuidesHtml = '<p>No guides assigned for this booking.</p>';
    }

    $('#assignGuidesModal #selectedGuidesPreview').html(assignedGuidesHtml);
    balanceTouristsAcrossGuides();
    //  Rebind guide selection handler (so it works after re-render)
    $('#assignGuidesModal .guide-opt select.tour-guide').trigger('change');
    //  FIX: clear invalid state for prefilled rows
    $('#assignGuidesModal .guide-tourists').each(function () {
        const val = parseInt($(this).val());
        if (val > 0) {
            $(this).removeClass('is-invalid').addClass('is-valid');
            $(this).closest('.guide-opt').find('.invalid-feedback').hide();
        }
    });

}

// Add/remove guide dynamically
$(document).off('click', '.addGuideOptbtn').on('click', '.addGuideOptbtn', appendGuideOption);
$(document).off('click', '.removeGuideOptbtn').on('click', '.removeGuideOptbtn', removeGuideOption);

function appendGuideOption(evt) {
    const selectedGuideIds = [];
    $('#assignGuidesModal .guide-opt select.tour-guide').each(function() {
        const val = $(this).val();
        if (val) selectedGuideIds.push(parseInt(val));
    });

    let guideOptions = '<option></option>';
    const guidesList = tourismBoard.guides || [];

    // Separate station-assigned and unassigned guides
    const stationAssignedGuides = [];
    const unassignedGuides = [];

    for (let guide of guidesList) {
        if (!selectedGuideIds.includes(parseInt(guide.id))) {
            const { alreadyAssigned, capacity, stationNames } = getGuideAssignmentInfo(guide.id);
            const isAssigned = stationNames && stationNames.length > 0;

            // Format station names
            const stationsText = isAssigned
                ? ` : ${stationNames.join(', ')}`
                : '';

            const optionHTML = `
                <option value="${guide.id}" data-display-name="${guide.guideName}">
                    ${guide.guideName} ${String(alreadyAssigned).padStart(2, '0')}/${capacity}${stationsText}
                </option>`;

            // Group by assignment
            if (isAssigned) {
                stationAssignedGuides.push(optionHTML);
            } else {
                unassignedGuides.push(optionHTML);
            }
        }
    }

    // Combine assigned first, then unassigned
    guideOptions += stationAssignedGuides.join('') + unassignedGuides.join('');

    // Append new guide option block
    const guideOptHtml = renderGuideOptionDiv(guideOptions);
    $('#assignGuidesModal #guideOptsDiv .guide-opt:last').after(guideOptHtml);

    // Rebalance tourists automatically
    balanceTouristsAcrossGuides();
}

function getGuideAssignmentInfo(guideId) {
    const asIntFn = (typeof asInt === 'function') ? asInt : (v => parseInt(v) || 0);

    const currentBookingId = asIntFn($('#assignGuidesModal #booking-id').val() || 0);
    const modalStationIdRaw = $('#assignGuidesModal #station-id').val();
    const modalStationId = modalStationIdRaw ? asIntFn(modalStationIdRaw) : null;

    const bookings = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.bookings)) ? tourismBoard.bookings : [];
    const guidesList = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.guides)) ? tourismBoard.guides : [];
    const stations = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.stations)) ? tourismBoard.stations : [];

    const guide = guidesList.find(g => asIntFn(g.id) === asIntFn(guideId));
    if (!guide) return { alreadyAssigned: 0, capacity: 0, stationNames: [] };

    const capacity = parseInt(guide.capacity || 0);
    let alreadyAssigned = 0;
    const stationNamesSet = new Set();

    // Check bookings
    bookings.forEach(b => {
        const bId = b.bookingId || b.id || b.booking_id || 0;
        if (asIntFn(bId) === currentBookingId) return;

        if (typeof currentSlotId !== 'undefined' || typeof currentDate !== 'undefined' || typeof currentTimeFrom !== 'undefined') {
            let slotMatch = true;
            if (typeof currentSlotId !== 'undefined' && b.tour_slot_id && asIntFn(b.tour_slot_id) !== asIntFn(currentSlotId)) slotMatch = false;
            if (typeof currentDate !== 'undefined' && b.date && b.date !== currentDate) slotMatch = false;
            if (typeof currentTimeFrom !== 'undefined' && b.time_from && b.time_from !== currentTimeFrom) slotMatch = false;

            if (!slotMatch) return;
        }

        if (!Array.isArray(b.assignedGuides) || b.assignedGuides.length === 0) return;

        b.assignedGuides.forEach(ag => {
            if (asIntFn(ag.guide_id) !== asIntFn(guideId)) return;
            if (modalStationId && ag.station_id && asIntFn(ag.station_id) !== modalStationId) return;

            alreadyAssigned += parseInt(ag.tourists || 0);
            if (ag.station_name) stationNamesSet.add(ag.station_name);
        });
    });

    //  Add station name if guide is assigned directly to a station (even without bookings)
    stations.forEach(st => {
        if (!Array.isArray(st.assignedGuides)) return;

        st.assignedGuides.forEach(ag => {
            // handle both ID array or object array
            const assignedGuideId = typeof ag === 'object' ? asIntFn(ag.guide_id ?? ag.id) : asIntFn(ag);
            if (assignedGuideId === asIntFn(guideId)) {
                const name = st.station_name || st.name || `Station ${st.id}`;
                stationNamesSet.add(name);
            }
        });
    });

    const stationNames = Array.from(stationNamesSet);
    return { alreadyAssigned, capacity, stationNames };
}

function getPendingTourists() {
    let totalTourists = parseInt($("#assignGuidesModal #totalTourists").val()) || 0;
    let assigned = 0;

    $('#assignGuidesModal .guide-opt input.guide-tourists').each(function() {
        let val = parseInt($(this).val());
        if (!isNaN(val)) assigned += val;
    });

    return Math.max(totalTourists - assigned, 0);
}

function updatePendingInfants() {
    const total = parseInt($('#totalInfants').val()) || 0;
    let assigned = 0;

    $('.guide-infants').each(function () {
        assigned += parseInt($(this).val()) || 0;
    });

    $('#modalPendingInfants').text(Math.max(total - assigned, 0));
}

function getPendingInfants() {
    let total = parseInt($('#totalInfants').val()) || 0;
    let assigned = 0;

    $('.guide-infants').each(function () {
        assigned += parseInt($(this).val()) || 0;
    });

    return Math.max(total - assigned, 0);
}

// Auto-fill tourists when selecting a guide
$(document).off('change', '#assignGuidesModal .guide-opt select.tour-guide')
.on('change', '#assignGuidesModal .guide-opt select.tour-guide', function () {
    const $select = $(this);
    const touristsInput = $select.closest('.guide-opt').find('input.guide-tourists');
    const $infantsInput = $select.closest('.guide-opt').find('.guide-infants');
    const pending = getPendingTourists();
    const pendingInfants  = getPendingInfants();

    // Only auto-fill tourists if empty or 0
    if (!touristsInput.val() || parseInt(touristsInput.val()) === 0) {
        touristsInput.val(pending);
    }

    if (!$infantsInput.val() || parseInt($infantsInput.val()) === 0) {
        $infantsInput.val(pendingInfants);
    }

    // Handle selected option
    const $selected = $select.find('option:selected');

    // Save original full text if not already stored
    if (!$selected.data('fullText')) {
        $selected.data('fullText', $selected.text().trim());
    }

    // If we have a clean display name (preferred)
    const displayName = $selected.data('display-name');
    if (displayName) {
        $selected.text(displayName);
    } else {
        // fallback: strip " — stations" and "08/20" if data-display-name not present
        const cleaned = $selected.text().replace(/\s+\d{1,2}\/\d+(?:\s+—.*)?$/, '').trim();
        $selected.text(cleaned);
    }

    // Refresh balance logic
    balanceTouristsAcrossGuides();
});

// Restore full option texts when dropdown opens
$(document).off('mousedown.restore', '#assignGuidesModal .guide-opt select.tour-guide')
.on('mousedown.restore', '#assignGuidesModal .guide-opt select.tour-guide', function () {
    $(this).find('option').each(function () {
        const $opt = $(this);
        const full = $opt.data('fullText');
        if (full) $opt.text(full);
    });
});

function renderGuideOptionDiv(guideOptions, bookingGuideId = '', tourists = '', infants = '') {
    return `
    <div class="row mb-3 guide-opt align-items-end">

        <div class="col-md-4">
            <label class="form-label">Guide</label>
            <select class="form-select tour-guide" name="tour_guide">
                ${guideOptions}
            </select>
            <div class="invalid-feedback">Please select guide.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Tourists</label>
            <input type="number"
                   min="1"
                   value="${tourists}"
                   class="form-control guide-tourists"
                   name="guide_tourists"
                   placeholder="Tourists"
                   required>
            <div class="invalid-feedback">Please enter tourists.</div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Infants</label>
            <input type="number"
                   min="0"
                   class="form-control guide-infants"
                   name="guide_infants"
                   placeholder="Infants"
                   value="${infants || ''}">
        </div>

        <div class="col-md-2">
            <button type="button"
                    class="btn btn-danger btn-sm removeGuideOptbtn"
                    data-id="${bookingGuideId}">
                <i class="fas fa-trash"></i>
            </button>
        </div>

    </div>`;
}

// Validation
function validateInput($el) {
    const value = parseFloat($el.val());
    const valid = !isNaN(value) && value > 0;
    $el.toggleClass('is-invalid', !valid).toggleClass('is-valid', valid);
    $el.siblings('.invalid-feedback').toggle(!valid);
    return valid;
}

function validateDropdown($el) {
    const valid = $el.val() && $el.val().length > 0;
    const $select2 = $el.next('.select2-container').find('.select2-selection--multiple');
    $el.toggleClass('is-invalid', !valid).toggleClass('is-valid', valid);
    $select2.toggleClass('is-invalid', !valid);
    $el.siblings('.invalid-feedback').toggle(!valid);
    return valid;
}

// When guide or tourists input changes, validate capacity
$(document).on('change keyup', '.tour-guide, .guide-tourists', function () {
    validateGuideCapacity($(this).closest('.guide-opt'));
});

$(document).on('keyup change', '.guide-infants', function () {
    balanceInfantsAcrossGuides();
    updatePendingInfants();
});

function balanceInfantsAcrossGuides() {
    const totalInfants = parseInt($('#totalInfants').val()) || 0;
    const inputs = $('.guide-infants').toArray();

    let sum = inputs.reduce((s, el) => s + (parseInt(el.value) || 0), 0);
    let overflow = sum - totalInfants;

    if (overflow > 0) {
        for (let i = 0; i < inputs.length && overflow > 0; i++) {
            let cur = parseInt(inputs[i].value) || 0;
            let reducible = Math.min(cur, overflow);

            inputs[i].value = cur - reducible;
            overflow -= reducible;
        }
    }

    // update pending infants display
    const pending = Math.max(totalInfants - (sum - overflow), 0);
    $('#modalPendingInfants').text(pending);
}

function validateGuideCapacity($row) {
    const guideId = $row.find('.tour-guide').val();
    const tourists = parseInt($row.find('.guide-tourists').val()) || 0;
    const $input = $row.find('.guide-tourists');

    // FIX 1: if tourists already valid, clear generic error
    if (tourists > 0) {
        $input.removeClass('is-invalid');
        $row.find('.invalid-feedback').hide();
    }

    if (!guideId) return;

    const guideData = (tourismBoard.guides || []).find(g => g.id == guideId);
    if (!guideData) return;

    const alreadyAssigned = guideData.tourists_assigned || 0;
    const capacity = guideData.capacity || 0;
    const remainingCapacity = capacity - alreadyAssigned;

    // FIX 2: capacity validation ONLY
    if (tourists > remainingCapacity) {
        $input.addClass('is-invalid').removeClass('is-valid');
        $row.find('.invalid-feedback')
            .text('Exceeds guide capacity!')
            .show();
    } else {
        $input.removeClass('is-invalid').addClass('is-valid');
        $row.find('.invalid-feedback').hide();
    }
}

function updateGuideCapacityInfo() {
    // fallback for asInt if not defined
    const asIntFn = (typeof asInt === 'function') ? asInt : (v => parseInt(v) || 0);

    // remove previous info blocks
    $('.guide-capacity-info').remove();

    const currentBookingId = asIntFn($('#assignGuidesModal #booking-id').val() || 0);
    const modalStationIdRaw = $('#assignGuidesModal #station-id').val();
    const modalStationId = modalStationIdRaw ? asIntFn(modalStationIdRaw) : null;

    let disableSubmit = false;

    // If tourismBoard or bookings not available, nothing to compute
    const bookings = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.bookings)) ? tourismBoard.bookings : [];
    const guidesList = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.guides)) ? tourismBoard.guides : [];

    $('.guide-opt').each(function () {
        const $row = $(this);
        const guideId = asIntFn($row.find('.tour-guide').val());
        const incoming = parseInt($row.find('.guide-tourists').val() || 0);

        // remove any previous info for this row
        $row.find('.guide-capacity-info').remove();

        if (!guideId) {
            // nothing selected -> no info, but keep checking other rows
            return;
        }

        const guide = guidesList.find(g => asIntFn(g.id) === guideId) || {};
        const capacity = parseInt(guide.capacity || 0);

        // compute alreadyAssigned FROM OTHER BOOKINGS (exclude current booking)
        let alreadyAssigned = 0;

        bookings.forEach(b => {
            // booking id variations
            const bId = b.bookingId || b.id || b.booking_id || 0;
            if (asIntFn(bId) === currentBookingId) return; // skip current booking

            // filter to same slot/date/time if those globals/fields exist:
            if (typeof currentSlotId !== 'undefined' || typeof currentDate !== 'undefined' || typeof currentTimeFrom !== 'undefined') {
                let slotMatch = true;
                if (typeof currentSlotId !== 'undefined' && b.tour_slot_id && asIntFn(b.tour_slot_id) !== asIntFn(currentSlotId)) slotMatch = false;
                if (typeof currentDate !== 'undefined' && b.date && b.date !== currentDate) slotMatch = false;
                if (typeof currentTimeFrom !== 'undefined' && b.time_from && b.time_from !== currentTimeFrom) slotMatch = false;
                if (!slotMatch) return; // different slot/date/time -> ignore
            }

            // sum assignedGuides entries that match this guide (+ station if available)
            (b.assignedGuides || []).forEach(ag => {
                if (asIntFn(ag.guide_id) !== guideId) return;
                if (modalStationId && ag.station_id && asIntFn(ag.station_id) !== modalStationId) return;
                alreadyAssigned += parseInt(ag.tourists || 0);
            });
        });

        const totalAfter = alreadyAssigned + incoming;

        const infoHtml = `
            <div class="guide-capacity-info" style="margin-top:6px; font-size:13px; font-weight:600;">
                Capacity: <span>${capacity}</span> |
                Already: <span style="color:#dc3545">${alreadyAssigned}</span> |
                Incoming: <span style="color:#28a745">${incoming}</span> |
                Total: <span style="${totalAfter > capacity ? 'color:red;font-weight:bold;' : ''}">${totalAfter}</span>
            </div>
        `;
        $row.append(infoHtml);

        // mark validity and possibly disable submit
        if (capacity > 0 && totalAfter > capacity) {
            disableSubmit = true;
            $row.find('.guide-tourists').addClass('is-invalid').removeClass('is-valid');
        } else {
            $row.find('.guide-tourists').removeClass('is-invalid').addClass('is-valid');
        }
    });

    // disable the modal submit if any over-capacity found
    $('#assignGuideSubmit').prop('disabled', disableSubmit);

    return !disableSubmit;
}

function balanceTouristsAcrossGuides() {
    const asIntFn = (typeof asInt === 'function') ? asInt : (v => parseInt(v, 10) || 0);

    // prepare context
    const bookings = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.bookings)) ? tourismBoard.bookings : [];
    const guidesList = (typeof tourismBoard !== 'undefined' && Array.isArray(tourismBoard.guides)) ? tourismBoard.guides : [];

    const currentBookingId = asIntFn($('#assignGuidesModal #booking-id').val() || 0);
    const modalStationIdRaw = $('#assignGuidesModal #station-id').val();
    const modalStationId = modalStationIdRaw ? asIntFn(modalStationIdRaw) : null;
    const bookingTotal = asIntFn($('#assignGuidesModal #totalTourists').val() || 0);

    // collect rows in DOM order
    const rows = $('.guide-opt').toArray();

    // find active (editing) input if any so we prefer reducing others first
    const activeInput = document.activeElement;
    const activeIndex = rows.findIndex(r => r.querySelector('.guide-tourists') === activeInput);

    // build an array with computed values and clamp to remaining capacity
    const arr = rows.map(function (row) {
        const $row = $(row);
        const guideId = asIntFn($row.find('.tour-guide').val());
        const $input = $row.find('.guide-tourists');
        let incoming = asIntFn($input.val() || 0);

        // find guide metadata
        const guideData = guidesList.find(g => asIntFn(g.id) === guideId) || {};
        const capacity = asIntFn(guideData.capacity || 0);

        // compute alreadyAssigned from other bookings (excluding current booking)
        let alreadyAssigned = 0;
        bookings.forEach(b => {
            const bId = b.bookingId || b.id || b.booking_id || 0;
            if (asIntFn(bId) === currentBookingId) return; // skip current booking

            // optionally filter by slot/date/time if those globals exist
            if (typeof currentSlotId !== 'undefined' || typeof currentDate !== 'undefined' || typeof currentTimeFrom !== 'undefined') {
                let slotMatch = true;
                if (typeof currentSlotId !== 'undefined' && b.tour_slot_id && asIntFn(b.tour_slot_id) !== asIntFn(currentSlotId)) slotMatch = false;
                if (typeof currentDate !== 'undefined' && b.date && b.date !== currentDate) slotMatch = false;
                if (typeof currentTimeFrom !== 'undefined' && b.time_from && b.time_from !== currentTimeFrom) slotMatch = false;
                if (!slotMatch) return;
            }

            (b.assignedGuides || []).forEach(ag => {
                if (asIntFn(ag.guide_id) !== guideId) return;
                if (modalStationId && ag.station_id && asIntFn(ag.station_id) !== modalStationId) return;
                alreadyAssigned += asIntFn(ag.tourists || 0);
            });
        });

        const remainingCapacity = Math.max(0, capacity - alreadyAssigned);

        // clamp incoming to remaining capacity (can't assign more than remaining)
        if (incoming > remainingCapacity) incoming = remainingCapacity;

        return {
            $row,
            guideId,
            $input,
            incoming,
            capacity,
            alreadyAssigned,
            remainingCapacity
        };
    });

    // if there are no rows nothing to do
    if (arr.length === 0) {
        updateGuideCapacityInfo();
        return;
    }

    // compute sum and overflow relative to booking total
    let sumIncoming = arr.reduce((s, x) => s + x.incoming, 0);
    let overflow = sumIncoming - bookingTotal;

    // If we exceed booking total => reduce other guides (earliest first) excluding active row
    if (overflow > 0) {
        for (let i = 0; i < arr.length && overflow > 0; i++) {
            if (i === activeIndex) continue; // prefer to reduce others first
            const reducible = Math.min(arr[i].incoming, overflow);
            if (reducible > 0) {
                arr[i].incoming -= reducible;
                overflow -= reducible;
            }
        }

        // if still overflow left, reduce the active row as last resort
        if (overflow > 0 && activeIndex >= 0 && activeIndex < arr.length) {
            const reducible = Math.min(arr[activeIndex].incoming, overflow);
            arr[activeIndex].incoming -= reducible;
            overflow -= reducible;
        }
    }

    // After balancing, ensure no row goes beyond remaining capacity (safety)
    arr.forEach(item => {
        if (item.incoming > item.remainingCapacity) {
            item.incoming = item.remainingCapacity;
        }
        if (item.incoming < 0) item.incoming = 0;
    });

    // Write back updated values to inputs (only if changed)
    arr.forEach(item => {
        const curVal = asIntFn(item.$input.val() || 0);
        if (curVal !== item.incoming) {
            item.$input.val(item.incoming);
        }
    });

    // After adjusting inputs and totals
    let totalTourists = parseInt($('#totalTourists').val(), 10) || 0;
    let assignedSum = arr.reduce((s, x) => s + x.incoming, 0);
    let pending = Math.max(0, totalTourists - assignedSum);

    // Update pending display
    $('#modalPending').text(pending);

    // Refresh UI/status
    updateGuideCapacityInfo();
    balanceInfantsAcrossGuides();
    updatePendingInfants();
}

// Submit Handler
$(document).off('click', '#assignGuideSubmit').on('click', '#assignGuideSubmit', function () {
    const self = window.tourismBoard || window; // adjust to your context
    let isValid = true;

    const totalAllowed = parseInt($('#assignGuidesModal #totalTourists').val());
    const bookingId = asInt($('#assignGuidesModal #booking-id').val());
    const booking = (self.bookings || []).find(b => asInt(b.id) === bookingId);

    let sum = 0;
    const guidesData = [];

    // --- Step 1: Validate tourists count ---
    $('.guide-tourists').each((_, el) => {
        if (!validateInput($(el))) isValid = false;
        sum += parseInt($(el).val() || 0);
    });

    if (sum > totalAllowed) {
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: `Total assigned tourists (${sum}) exceeds booking allowed (${totalAllowed}).`
        });
        return;
    }

    // --- Step 2: Validate guides (station + package logic) ---
    $('.guide-opt').each((_, el) => {
        const $row = $(el);
        const guideId = asInt($row.find('.tour-guide').val());
        const tourists = parseInt($row.find('.guide-tourists').val()) || 0;
        const infants = parseInt($row.find('.guide-infants').val()) || 0;
        if (!guideId) return;

        const guide = (self.guides || []).find(g => asInt(g.id) === guideId);
        if (!guide) return;

        const guideName = guide.guideName || guide.name || `Guide #${guideId}`;
        const allStations = self.stations || [];
        // Find stations where this guide is assigned
        const assignedStations = allStations.filter(st => {
            const assignedIds = (st.guides || []).map(ag => asInt(ag.guide_id || ag.id));
            return assignedIds.includes(guideId);
        });

        // ❌ Guide not assigned to any station
        if (assignedStations.length === 0) {
            isValid = false;
            Swal.fire({
                icon: 'warning',
                title: 'Guide Not Assigned',
                text: `${guideName} is not assigned to any station. Please assign a station first.`,
                confirmButtonText: 'OK'
            });
            return false; // stop loop
        }

        // Check if any of guide’s stations contain the booking’s package
        const bookingPackageId = asInt(booking?.tour_package_id);
        let validStation = null;

        for (const st of assignedStations) {
            const stationPackageIds = (st.packages || []).map(p => asInt(p.id));
            if (stationPackageIds.includes(bookingPackageId)) {
                validStation = st;
                break;
            }
        }

        // ❌ Package mismatch (guide’s stations don’t allow booking’s package)
        if (!validStation) {
            isValid = false;
            Swal.fire({
                icon: 'warning',
                title: 'Package Not Allowed',
                text: `${guideName} cannot be assigned because none of their stations allow the booking’s package.`,
                confirmButtonText: 'OK'
            });
            return false; // stop loop
        }

        // Passed station & package validation
        guidesData.push({
            guide_id: guideId,
            tourists: tourists,
            infants: infants,
            station_id: validStation.id
        });
    });

    if (!isValid) return; // stop submit if invalid

    // --- Step 3: Submit ---
    showLoading();
    $.ajax({
        url: assignTourGuideUrl.replace(':bookingId', bookingId),
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
        data: {
            booking_id: bookingId,
            date: currentDate,
            time_from: currentTimeFrom,
            guides: guidesData
        },
        success: function () {
            $("#assignGuidesModal").modal('hide');
            loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
            showToast('Tour guides assigned successfully!', 'success');
        },
        error: showSubmissionError,
        complete: hideLoading
    });
});
function showSubmissionError(xhr) {
    let message = 'Something went wrong. Please try again.';

    // Laravel validation error (422)
    if (xhr.status === 422 && xhr.responseJSON?.errors) {
        const errors = xhr.responseJSON.errors;
        message = Object.values(errors).flat().join('<br>');
    }
    // Custom / general error
    else if (xhr.responseJSON?.message) {
        message = xhr.responseJSON.message;
    }

    Swal.fire({
        icon: 'error',
        title: 'Assignment Failed',
        html: message,
        confirmButtonText: 'OK',
        confirmButtonColor: '#dc3545'
    });
}


document.addEventListener('input', function(e) {
    if (e.target.classList.contains('guide-tourists')) {
        balanceTouristsAcrossGuides();
    }
});

// Manual update end

// Common function to get selected calendar date in YYYY-MM-DD format (America/Phoenix)
function getCalendarSelectedDate() {
  const { DateTime } = luxon;

  // Find the active date box
  const activeEl = document.querySelector(".day-box.active");

  // If no date selected, fallback to today's Phoenix date
  if (!activeEl) {
    return DateTime.now()
      .setZone(timezone)
      .toFormat("yyyy-MM-dd");
  }

  // Each .day-box should already have a data-date like "2025-11-06"
  const dateStr = activeEl.getAttribute("data-date");
  if (dateStr) return dateStr;

  // Fallback — reconstruct from visible label (if no data-date)
  const dayText = activeEl.querySelector(".date-label")?.textContent;
  const currentMonth = activeEl.closest(".calendar-grid")?.getAttribute("data-month"); // optional, if you store month info

  if (dayText && currentMonth) {
    const [year, month] = currentMonth.split("-"); // e.g. "2025-11"
    const phoenixDate = DateTime.fromObject(
      { year: parseInt(year), month: parseInt(month), day: parseInt(dayText) },
      { zone: timezone }
    );
    return phoenixDate.toFormat("yyyy-MM-dd");
  }

  // Final fallback — today
  return DateTime.now().setZone(timezone).toFormat("yyyy-MM-dd");
}

document.addEventListener("DOMContentLoaded", function () {
    // Search booking
    document.getElementById("bookingSearchBtn").addEventListener("click", function () {
        let searchVal = document.getElementById("bookingSearchInput").value.trim();
        searchVal = searchVal.replace(/\D+/g, "");
        if (!searchVal) {
            Swal.fire({
                icon: "warning",
                title: "Missing Input",
                text: "Please enter or scan a booking ID before searching."
            });
            return;
        }
        
        // When Guide Management modal is open, use the board's currentDate (matches the displayed date).
        // Otherwise fall back to the calendar selected date.
        const guideModalOpen = document.getElementById('guideManagementModal')?.classList.contains('show');
        const formattedDate = (guideModalOpen && typeof currentDate !== 'undefined' && currentDate)
            ? currentDate
            : getCalendarSelectedDate();
        let url = `${searchBookingUrl}?query=${encodeURIComponent(searchVal)}&date=${encodeURIComponent(formattedDate)}`;
        showLoading();
        fetch(url)
            .then(res => res.json())
            .then(data => {
            hideLoading();
            if (data.success && data.bookings && data.bookings.length > 0) {
                
                // --- render bookings (unchanged) ---
               const tbSvgPerson = `<svg style="max-width:15px;height:auto;flex-shrink:0;vertical-align:middle;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87m-3-12a4 4 0 0 1 0 7.75"/></g></svg>`;
               const tbSvgInfant = `<svg style="max-width:15px;height:auto;flex-shrink:0;vertical-align:middle;" xmlns="http://www.w3.org/2000/svg" width="256" height="256" viewBox="0 0 24 24" fill="#717886"><g fill="none" stroke="#717886"><path stroke-linejoin="round" stroke-width="1.5" d="M19.95 10.896a8.001 8.001 0 0 1-15.9 0a1.5 1.5 0 1 1 .159-2.719a8.003 8.003 0 0 1 15.583 0a1.5 1.5 0 1 1 .159 2.719Z"/><path stroke-linejoin="round" stroke-width="1.5" d="M12 15a1 1 0 0 0 1-1h-2a1 1 0 0 0 1 1Z"/><path stroke-linecap="round" stroke-width="1.5" d="M18 22c0-1.792-.786-3.4-2.031-4.5M6 22c0-1.792.786-3.4 2.031-4.5M12 2c1 0 2 .895 2 2s-1 2-2 2a2.63 2.63 0 0 1-1.5-.5"/><path stroke-linecap="round" stroke-width="2" d="M9 10v.01m6-.01v.01"/></g></svg>`;

               const bookingsHtml = data.bookings.map(booking => {
                    const assignedGuests = booking.assigned_tourists || booking.tourists || 0;
                    const checkIns = { "1": 0, "2": 0, "A": 0, ...(booking.checkInsCount || {}) };
                    const checkedIn = (checkIns["1"] || 0) + (checkIns["2"] || 0);
                    const absent = checkIns["A"] || 0;
                    const pending = Math.max(assignedGuests - checkedIn - absent, 0);

                    let checkInHtml = "";
                    if (pending > 0 && checkIns["1"] === 0 && checkIns["2"] === 0 && checkIns["A"] === 0) {
                        checkInHtml = `<span style="background:#fde8e8;color:#e53e3e;border-radius:20px;padding:2px 10px;font-size:12px;">Not Checked In: ${pending}</span>`;
                    } else {
                        const parts = [];
                        if (checkIns["1"] > 0)
                            parts.push(`<span style="background:#fef9c3;color:#92400e;border-radius:20px;padding:2px 10px;font-size:12px;">1st Check In: ${checkIns["1"]}</span>`);
                        if (checkIns["2"] > 0)
                            parts.push(`<span style="background:#fef08a;color:#78350f;border-radius:20px;padding:2px 10px;font-size:12px;">2nd Check In: ${checkIns["2"]}</span>`);
                        if (checkIns["A"] > 0)
                            parts.push(`<span style="background:#f8d7da;color:#842029;border-radius:20px;padding:2px 10px;font-size:12px;">Absent: ${checkIns["A"]}</span>`);
                        if (pending > 0)
                            parts.push(`<span style="background:#fde8e8;color:#e53e3e;border-radius:20px;padding:2px 10px;font-size:12px;">Not Checked In: ${pending}</span>`);
                        checkInHtml = parts.join(" ");
                    }

                    // Format Date + Time
                    let formattedDateTime = "";
                    if (booking.date && booking.time_from) {
                        const dateObj = new Date(`${booking.date}T${booking.time_from}`);
                        const month = (dateObj.getMonth() + 1).toString().padStart(2, "0");
                        const day = dateObj.getDate().toString().padStart(2, "0");
                        const year = dateObj.getFullYear();

                        let hours = dateObj.getHours();
                        const minutes = dateObj.getMinutes().toString().padStart(2, "0");
                        const ampm = hours >= 12 ? "PM" : "AM";
                        hours = hours % 12 || 12;

                        formattedDateTime = `${month}/${day}/${year} @ ${hours}:${minutes} ${ampm}`;
                    }

                    return `
                        <div class="assigned-booking"
                             style="background:#fff;border:1.5px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:8px;"
                             data-booking-id="${booking.id}"
                             data-date="${booking.date}"
                             data-time="${booking.time_from}"
                             data-assigned-guests="${assignedGuests}"
                             draggable="false">
                            <input hidden type="checkbox" class="booking-select-checkbox" value="${booking.id}" checked disabled />
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px;">
                                <span style="font-weight:700;font-size:13px;color:#111827;">${typeof tenantId !== "undefined" ? tenantId + "-" : ""}${booking.id}</span>
                                <div style="font-weight:600;font-size:13px;color:#111827;">${booking.name || ""}</div>
                                <div>${booking.tour_package ? `<span style="background:#dbeafe;color:#2563eb;border-radius:20px;padding:2px 12px;font-size:12px;font-weight:500;">${booking.tour_package}</span>` : ""}</div>
                            </div>
                            <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                                <span style="font-size:12px;color:#374151;">🗓️ ${formattedDateTime}</span>
                                <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;">${tbSvgPerson} ${assignedGuests} guests</span>
                                <span style="display:flex;align-items:center;gap:4px;font-size:12px;color:#374151;">${tbSvgInfant} ${booking.infants} infants</span>
                                ${checkInHtml}
                            </div>
                        </div>
                    `;
                }).join("");

                // --- render guides (unchanged) ---
                let guidesHtml = "";
                if (data.assignedGuides && data.assignedGuides.length > 0) {
                    guidesHtml = `
                        <div class="mt-3 p-2 border-top">
                            <h6 class="fw-bold mb-2">Assigned Guides</h6>
                            <ul class="list-unstyled mb-0" id="assignedGuideList">
                                ${data.assignedGuides.map(g =>
                                    `<li class="d-flex align-items-center gap-2" data-guide-id="${g.id}">
                                        <i class="bi bi-person-check text-success"></i>
                                        ${g.guideName}
                                    </li>`
                                ).join("")}
                            </ul>
                        </div>
                    `;
                }

                // --- inject booking info ---
                document.getElementById("transferBookingInfo").innerHTML = bookingsHtml + guidesHtml;
                slotSelect.innerHTML = `<option value="">-- Select Slot --</option>`; // reset

                if (data.slotTimes && data.slotTimes.slotTimes) {
                    data.slotTimes.slotTimes.forEach(slot => {
                        const option = document.createElement("option");
                        option.value = slot.time_from; // fallback if no ID
                        option.dataset.time = slot.time_from;
                        option.dataset.date = data.slotTimes.date;
                        option.dataset.seats = slot.seats;

                        const formattedTime = formatTimeToAmPm(slot.time_from);

                        option.textContent = `${formattedTime} (${slot.seats} available seats)`;

                        // Auto-select if this is the current slot time
                        if (slot.time_from === currentSlotTime) {
                            option.selected = true;

                            // Call guide loader immediately for current slot
                            loadGuides(slot.time_from, data.slotTimes.date);
                        }

                        slotSelect.appendChild(option);
                    });
                }

                // --- finally show modal ---
                new bootstrap.Modal(document.getElementById("transferBookingModal")).show();
            } else {
                Swal.fire("Not Found", "Booking not found!", "warning");
            }
        });

    });

    // Slot change -> load guides dynamically
    slotSelect.addEventListener("change", function () {
        let selectedOption = this.options[this.selectedIndex];
        let timeFrom = selectedOption.getAttribute("data-time");
        let date = getCalendarSelectedDate();
        loadGuides(timeFrom, date);
    });

    // Confirm transfer
    document.getElementById("confirmTransferBtn").addEventListener("click", function () {
        let slotSelect = document.getElementById("transferSlotSelect");
        let selectedOption = slotSelect.options[slotSelect.selectedIndex];

        let timeFrom = selectedOption?.getAttribute("data-time");
        let date = getCalendarSelectedDate();
        let guideSelect = document.getElementById("transferGuideSelect");
        let guideId = guideSelect.value;

        if (!timeFrom || !date || !guideId) {
            Swal.fire({
                title: "Missing Info",
                text: "Please select slot, date and guide!",
                icon: "error",
                target: '#transferBookingModal'
            });
            return;
        }

       /* ======================================================
           BLOCK TRANSFER OF PAST BOOKINGS (LUXON + TIMEZONE)
           ====================================================== */

        const nowInTZ = DateTime.now().setZone(timezone);
        let pastBookingFound = false;

        document.querySelectorAll(".booking-select-checkbox:checked").forEach(cb => {
            const bookingEl = cb.closest(".assigned-booking");

            const bookingDate = bookingEl.getAttribute("data-date");     // YYYY-MM-DD
            const bookingTime = bookingEl.getAttribute("data-time");     // HH:mm:ss

            if (!bookingDate || !bookingTime) return;

            // Booking datetime in authoritative timezone
            const bookingDateTime = DateTime.fromFormat(
                `${bookingDate} ${bookingTime}`,
                "yyyy-MM-dd HH:mm:ss",
                { zone: timezone }
            );

            if (bookingDateTime < nowInTZ) {
                pastBookingFound = true;
            }
        });

        if (pastBookingFound) {
            Swal.fire({
                title: "Invalid Transfer",
                text: "Can not transfer the expiry booking.",
                icon: "error",
                target: '#transferBookingModal'
            });
            return;
        }

        // Collect selected bookings
        let selectedBookings = [];
        let incomingTourists = 0;
        let duplicateFound = false;

        document.querySelectorAll(".booking-select-checkbox:checked").forEach(cb => {
            let bookingEl = cb.closest(".assigned-booking");

            // Validation: prevent transfer to same slot (same date + time)
            let bookingDate = bookingEl.getAttribute("data-date");
            let bookingTime = bookingEl.getAttribute("data-time");
            if (bookingDate === date && bookingTime === timeFrom) {
                duplicateFound = true;
            }

            selectedBookings.push(cb.value);

            // count tourists for validation
            let guests = parseInt(bookingEl.getAttribute('data-assigned-guests')) || 0;
            incomingTourists += guests;
        });

        if (selectedBookings.length === 0) {
            Swal.fire({
                title: "No Bookings",
                text: "Please select at least one booking to transfer.",
                icon: "warning",
                target: '#transferBookingModal'
            });
            return;
        }

       /* if (duplicateFound) {
            Swal.fire("Invalid Transfer", "Can not transfer the booking in same date and time!", "error");
            return;
        }*/

        // Slot capacity validation
        let slotSeats = parseInt(selectedOption.dataset.seats || 0);
        if (incomingTourists > slotSeats) {
            Swal.fire({
                title: "Over Slot Capacity",
                text: `This slot has only ${slotSeats} available seats, but selected bookings have ${incomingTourists} tourists.`,
                icon: "error",
                target: '#transferBookingModal'
            });
            return;
        }

        // Guide capacity validation
        let guideOption = guideSelect.options[guideSelect.selectedIndex];
        let guideText = guideOption.textContent;
        let match = guideText.match(/Assigned:\s*(\d+)\/(\d+)/);
        let alreadyAssigned = match ? parseInt(match[1]) : 0;
        let guideCapacity = match ? parseInt(match[2]) : 0;

        if ((alreadyAssigned + incomingTourists) > guideCapacity) {
            Swal.fire({
                title: "Over Guide Capacity",
                text: `This guide has capacity ${guideCapacity}, already assigned ${alreadyAssigned}, and incoming ${incomingTourists} will exceed it.`,
                icon: "error",
                target: '#transferBookingModal'
            });
            return;
        }

        // Collect assigned guide IDs
        let assignedGuideIds = [];
        document.querySelectorAll("#assignedGuideList li").forEach(li => {
            let gid = li.getAttribute("data-guide-id");
            if (gid) assignedGuideIds.push(gid);
        });

        let url = `${transferBookingUrl}`;
        showLoading();

        fetch(url, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken
            },
            body: JSON.stringify({
                booking_ids: selectedBookings,
                time_from: timeFrom,
                date: date,
                guide_id: guideId,
                assigned_guide_ids: assignedGuideIds
            })
        })
        .then(async res => {
            const data = await res.json();

            if (!res.ok) {
                // Handles 422, 400 etc.
                throw new Error(data.message || 'Transfer failed');
            }

            return data;
        })
        .then(data => {
            hideLoading();

            if (data.success) {

                if (typeof window.loadBoardData === "function") {
                    window.loadBoardData();
                }

                showToast(data.message || 'Bookings successfully transferred!', 'success');
                if (currentDate) {
                    loadGuideManagement(currentSlotId, currentTimeFrom, currentDate);
                }
                const modalEl = document.getElementById('transferBookingModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

            } else {
                showToast(data.message || 'Transfer failed', 'error');
            }
        })
        .catch(error => {
            hideLoading();
            showToast(error.message || 'Something went wrong', 'error');
        });
    });


});

function formatTimeToAmPm(time24) {
    if (!time24) return "";
    let [hours, minutes] = time24.split(":");
    hours = parseInt(hours, 10);
    const suffix = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12; // convert 0 → 12
    return `${hours}:${minutes} ${suffix}`;
}

// Common function to fetch and update guides
function loadGuides(timeFrom, date) {
    let guideSelect = document.getElementById("transferGuideSelect");
    guideSelect.innerHTML = `<option>Loading...</option>`;
    let url = `${guidesUrl}?&time_from=${encodeURIComponent(timeFrom)}&date=${encodeURIComponent(date)}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            guideSelect.innerHTML = `<option value="">-- Select Guide --</option>`;
            data.forEach(g => {
                guideSelect.innerHTML += `<option value="${g.id}">${g.guideName} (Assigned: ${g.assigned_tourists}/${g.capacity}) : ${g.stationName} </option>`;
            });
        });
}

$(document).off('change', '.booking-check-toggle')
    .on('change', '.booking-check-toggle', function () {

        const $cb = $(this);

        const payload = {
            booking_id: $cb.data('booking-id'),
            station_id: $cb.data('station-id'),
            guide_id: $cb.data('guide-id'),
            checked: $cb.is(':checked') ? 'Yes' : 'No',
            time_from: currentTimeFrom,
            date: currentDate
        };

        // Suppress socket-triggered modal re-render while this POST is in-flight
        // to prevent the checkbox from flickering back to its old state.
        window._boardCheckedInFlight = true;

        $.post(updateBookingCheckedUrl, payload)
            .done(res => {
                hideLoading();
                window._boardCheckedInFlight = false;

                if (!res.success) {
                    showToast(res.message || 'Update failed', 'error');
                    $cb.prop('checked', !$cb.is(':checked')); // rollback
                    return;
                }
            })
            .fail(() => {
                hideLoading();
                window._boardCheckedInFlight = false;
                showToast('Failed to update booking', 'error');
                $cb.prop('checked', !$cb.is(':checked')); // rollback
            });
});

// Click anywhere on booking card → toggle checkbox
$(document).off('click', '.active-bookings-wrap')
    .on('click', '.active-bookings-wrap', function (e) {

    // Ignore clicks on buttons, inputs, svg, labels, actions
    if (
        $(e.target).closest(
            'button, input[type="checkbox"], label, .booking-actions, svg, path'
        ).length
    ) {
        return;
    }

    const $checkbox = $(this).find('.booking-check-toggle').first();
    if (!$checkbox.length) return;

    // Toggle checkbox
    $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
});


