@extends('partials.board.index')
@php
    $routePrefix = routePrefix();
    $userType = userType();
    $authId = Auth::id();
@endphp

@section('main-content')

<div class="container-fluid">
    <ul class="nav nav-tabs" id="boardTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="board-tab" data-bs-toggle="tab" href="#board" role="tab">Board</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="station-tab" data-bs-toggle="tab" href="#station" role="tab">Station</a>
        </li>
        @can('board-station-packages')
        <li class="nav-item">
            <a class="nav-link" id="board-station-packages-tab" data-bs-toggle="tab" href="#board-station-packages" role="tab">Station Packages</a>
        </li>
        @endcan
        <li class="nav-item">
            <a class="nav-link" id="guide-list-tab" data-bs-toggle="tab" href="#guide-list" role="tab">Tour Guide</a>
        </li>
    </ul>

    <div class="tab-content pt-3" id="boardTabsContent">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
                {{ session('success') }}
            </div>
        @endif

        <!-- Board Tab -->
        <div class="tab-pane fade show active" id="board" role="tabpanel">
             <!-- Calendar (centered) -->
            <div class="calendar-nav">
              <button id="prevMonth">&lt;</button>
              <div class="calendar-header" id="calendarHeader"></div>
              <button id="nextMonth">&gt;</button>
            </div>

            <div id="calendarContainer"></div>

            <div class="d-flex justify-content-center mb-3 gap-2 mt-3">
                <!-- QR Scan Button -->
                <button type="button" class="btn btn-outline-primary" id="qr-btn">
                    <i class="fas fa-qrcode me-1"></i> Scan QR
                </button>

                <!-- Booking Search -->
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" id="bookingSearchInput" class="form-control" placeholder="Search Booking id">
                    <button class="btn btn-primary" id="bookingSearchBtn">
                        <i class="fas fa-search"></i>
                    </button>
                </div>

                <!-- Time Slot Button -->
                <button type="button" class="btn btn-outline-success timeSlotBtn" id="timeSlotBtn">
                    <i class="fas fa-clock me-1"></i> Time Slot
                </button>
            </div>
            <!-- Slots -->
            <div class="row" id="boardData">
                @forelse($tourPackage as $slot)

                    <div class="col-md-4 mb-3">
                        <div class="card shadow slot-card {{ $slot->time_from == ($currentSlotTime ?? null) ? 'is-current' : '' }}">
                            <input type="hidden" id="currentSlotTime" value="{{ $currentSlotTime ?? '' }}">

                            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2">
                                    <span>{{ $slot->formatted_time }}</span>
                                </div>

                                <span class="fw-bold">
                                    {{ $slot->total_booked }} / {{ $slot->total_capacity }}
                                </span>

                                <div class="d-flex gap-2">
                                    <button type="button"
                                            class="btn btn-info btn-sm bulk-capacity-btn"
                                            data-slot-id="{{ $slot->id }}"
                                            data-time-from="{{ $slot->time_from }}"
                                            data-date="{{ $slot->date }}" 
                                            title="Bulk Update Capacity">
                                        <i class="fas fa-users me-1"></i>
                                    </button>

                                    <button type="button"
                                            class="btn btn-info btn-sm station-btn"
                                            data-slot-id="{{ $slot->id }}"
                                            data-time-from="{{ $slot->time_from }}"
                                            data-date="{{ $slot->date }}"
                                            data-bs-toggle="modal" data-bs-target="#stationModal">
                                        Add Station
                                    </button>

                                    <button type="button"
                                            class="btn btn-warning btn-sm guide-management-btn"
                                            data-slot-id="{{ $slot->id }}"
                                            data-time-from="{{ $slot->time_from }}"
                                            data-date="{{ $slot->date }}">
                                        <i class="fas fa-user-cog me-1"></i> Management
                                    </button>

                                </div>
                            </div>

                            <div class="card-body">
                                <ul class="list-group">
                                   @php
                                        // Count real stations (ignore nulls)
                                        $maxStations = collect($normalizedStations)->filter(fn($s) => $s !== null)->count();

                                        $realRows = [];
                                    @endphp

                                    @foreach($normalizedStations as $station)
                                        @php
                                            $shouldRender = true;

                                            if ($station === null) {
                                                $shouldRender = false;
                                            }

                                            if ($shouldRender && !empty($station->station_date) && !empty($station->station_time_from)) {
                                                if ($station->station_date != $slot->date || $station->station_time_from != $slot->time_from) {
                                                    $shouldRender = false;
                                                }
                                            }

                                            if ($shouldRender) {
                                                $slotData = $station->guides[$slot->time_from] ?? null;
                                                $guideCollection = collect($slotData?->guides ?? []);
                                            }

                                            if (!$shouldRender) continue;

                                            $assigned = $guideCollection->sum("assigned_tourists");
                                            $capacity = $guideCollection->sum("capacity");

                                            $colorClass = $assigned < $capacity
                                                ? "text-danger"
                                                : ($assigned == $capacity ? "text-success" : "text-warning");

                                            $isSpecificStation = !empty($station->station_date) && !empty($station->station_time_from);

                                            // Resolve station name (override > default)
                                            $stationDisplayName = $station->name;

                                            $overrideName = DB::table('board_station_specific_name')
                                                ->where('station_id', $station->id)
                                                ->where('date', $slot->date)
                                                ->where('time_from', $slot->time_from)
                                                ->value('name');

                                            if ($overrideName) {
                                                $stationDisplayName = $overrideName;
                                            }

                                            // Resolve station packages (specific override > station default)
                                            $defaultPackageIds = json_decode($station->tour_packages ?? '[]', true) ?? [];
                                            $overridePackages = DB::table('board_station_specific_packages')
                                                ->where('station_id', $station->id)
                                                ->where('date', $slot->date)
                                                ->where('time_from', $slot->time_from)
                                                ->value('packages');
                                            $activePackageIds = $overridePackages
                                                ? (json_decode($overridePackages, true) ?? $defaultPackageIds)
                                                : $defaultPackageIds;

                                            $editBtn = '';
                                            
                                            $editBtn = '<button
                                                            class="btn btn-sm btn-outline-primary ms-2 edit-station-btn" style="padding:2px 6px; font-size:12px; line-height:1;"
                                                            data-station-id="'.$station->id.'"
                                                            data-station-name="'.e($stationDisplayName).'"
                                                            data-station-specific="'.($isSpecificStation).'"
                                                            data-time-from="'.$slot->time_from.'"
                                                            data-date="'.$slot->date.'"
                                                            data-packages="'.implode(',', $activePackageIds).'"
                                                        >
                                                            <i class="fa fa-edit"></i>
                                                        </button>';
                                        
                                            $capacityEditBtn = '';

                                            if ($capacity > 0 && $guideCollection->count() > 0) {
                                                $capacityEditBtn = '
                                                <button
                                                    class="btn btn-outline-primary edit-guide-capacity-btn"
                                                    style="padding:2px 6px; font-size:12px; line-height:1;"
                                                    title="Edit capacity"
                                                    data-slot-id="'.$slot->id.'"
                                                    data-time-from="'.$slot->time_from.'"
                                                    data-date="'.$slot->date.'"
                                                    data-station-id="'.($station->id ?? '').'"
                                                    data-guides=\''.json_encode(
                                                        $guideCollection->map(fn($g) => [
                                                            'id' => $g->guide_id,
                                                            'name' => $g->guide_name,
                                                            'capacity' => $g->capacity,
                                                        ])->values()
                                                    ).'\'
                                                >
                                                    <i class="fa fa-edit"></i>
                                                </button>
                                                ';
                                            }

                                            $realRows[] = '
                                            <li class="list-group-item d-flex align-items-center" style="min-height:42px;">

                                                <!-- Station -->
                                                <div style="width:80px; flex-shrink:0; display:flex; align-items:center;">
                                                    <span class="fw-bold">'.e($stationDisplayName).'</span>

                                                    '.$editBtn.'
                                                </div>

                                                <!-- Guide names (LEFT, FULL WIDTH) -->
                                                <div style="flex:1; padding:0 12px; font-weight:600; text-align:center; white-space:normal;">
                                                    '.e($guideCollection->pluck("guide_name")->join(", ")).'
                                                </div>

                                                <!-- Capacity + Edit (RIGHT, TIGHT) -->
                                                <div style="width:90px; flex-shrink:0; display:flex; align-items:center; justify-content:flex-end; gap:4px;">
                                                    <span class="'.$colorClass.' fw-bold">
                                                        '.$assigned.' / '.$capacity.'
                                                    </span>
                                                    '.$capacityEditBtn.'
                                                </div>

                                            </li>
                                            ';

                                        @endphp
                                    @endforeach

                                    {{-- Render real rows --}}
                                    @foreach($realRows as $row)
                                        {!! $row !!}
                                    @endforeach

                                    {{-- ADD EMPTY ROWS AFTER RENDERING --}}
                                    @php
                                        $emptyCount = $maxStations - count($realRows);
                                    @endphp

                                    @for($i = 0; $i < $emptyCount; $i++)
                                        <li class="list-group-item d-flex align-items-center" style="min-height:42px;">
                                            <div style="width:80px;">&nbsp;</div>
                                            <div style="flex:1;">&nbsp;</div>
                                            <div style="width:90px;">&nbsp;</div>
                                        </li>
                                    @endfor
                                </ul>
                            </div>

                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            No slots available for this date.
                        </div>
                    </div>
                @endforelse
            </div>

        </div>

        <!-- Station Tab -->
        <div class="tab-pane fade" id="station" role="tabpanel">
            <div class="d-flex justify-content-between mb-3">
                <h4>Stations</h4>
                <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#stationModal">
                    <i class="bi bi-plus-lg"></i> Add Station
                </button>
            </div>
            <div id="successMessage" class="alert alert-success d-none" role="alert"></div>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Station Name</th>
                        <th>Assigned Packages</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="stationTableBody">
                    @forelse($stationList ?? [] as $station)
                        <tr data-id="{{ $station->id }}">
                            <td>{{ $station->name }}</td>
                            <td>
                                @if(!empty($station->packages) && count($station->packages) > 0)
                                    @foreach($station->packages as $package)
                                        <span class="badge bg-info me-1 mb-1">{{ $package }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">No Packages Assigned</span>
                                @endif
                            </td>
                            <td>{{ $station->status }}</td>
                            <td>
                                <button 
                                    class="btn btn-warning btn-sm editStationBtn" 
                                    data-id="{{ $station->id }}" 
                                    data-name="{{ $station->name }}"
                                    data-status="{{ $station->status }}"
                                    data-packages="{{ $station->tour_packages }}"
                                    data-url="{{ route($routePrefix . '.board.update-station', ['id' => $station->id]) }}"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editStationModal">
                                    <i class="bi bi-pencil me-1"></i> Edit
                                </button>

                                <button 
                                    class="btn btn-danger btn-sm deleteBtn" 
                                    data-id="{{ $station->id }}"
                                    data-url="{{ route($routePrefix . '.board.delete-station', ['id' => '__id__']) }}">
                                    <i class="bi bi-trash me-1"></i> Delete
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted">🚉 No stations found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Station Packages Tab -->
        <div class="tab-pane fade" id="board-station-packages" role="tabpanel">
            @include('partials.board.board-station-package',['boardStationPackage' => $boardStationPackage, 'allPackages' => $tourPackages])
        </div>

        <!-- Guide List -->
        <div class="tab-pane fade mb-4" id="guide-list" role="tabpanel">
            <div class="d-flex justify-content-start mb-3">
                <button 
                    type="button"
                    class="btn btn-sm btn-success fw-semibold"
                    id="activateAllGuidesBtn"
                >
                    Activate All
                </button>
            </div>
            <div class="row g-3" id="tourGuideList">

                @foreach ($tourGuides as $guide)
                    <div class="col-6 col-sm-4 col-md-2"> {{-- NOW 6 CARDS PER ROW --}}
                        <div class="card h-100"
                             style="
                                border-radius:12px;
                                transition:0.25s ease;
                                border:0;
                                padding:6px;
                                height:140px; /* compact */
                                background: linear-gradient(135deg, #dbeafe, #bfdbfe);
                                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                             "
                             onmouseover="this.style.transform='translateY(-4px)'; 
                                           this.style.boxShadow='0 6px 18px rgba(0,0,0,0.15)'"
                             onmouseout="this.style.transform='translateY(0)'; 
                                          this.style.boxShadow='0 2px 8px rgba(0,0,0,0.08)'"
                        >
                            <div class="card-body" style="padding:12px;">

                                <h5 class="card-title mb-1" style="font-size:16px; font-weight:700;">
                                    {{ $guide->name }}
                                </h5>

                                <div class="d-flex flex-column align-items-start mt-2">
                                    <select 
                                        name="guide_status_{{ $guide->id }}"
                                        class="form-select form-select-sm text-white fw-semibold status-dropdown"
                                        data-guide-id="{{ $guide->id }}"
                                        style="
                                            min-width:90px;       /* reduced from 120 */
                                            max-width:90px;       /* fixed width */
                                            height:30px;          /* reduced height */
                                            border:none;
                                            border-radius:6px;
                                            font-size:14px;       /* smaller text */
                                            font-weight:600;
                                            padding:2px 4px;      /* reduced spacing */
                                        "
                                    >
                                        <option value="Active"   {{ $guide->guide_status == 'Active' ? 'selected' : '' }}>Active</option>
                                        <option value="Inactive" {{ $guide->guide_status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>
</div>

<!-- Time Slot Transfer Modal -->
<div class="modal fade" id="timeSlotModal" tabindex="-1" aria-labelledby="timeSlotModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="timeSlotModalLabel">Time Slot Bookings</h5>
        <!-- Date Display -->
        <div class="mx-auto text-center">
          <span id="transferDate" class="fw-bold" style="font-size:1.3rem;"></span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <!-- Search Bar -->
        <div class="mt-3">
          <input type="text" id="timeSlotSearch" class="form-control" placeholder="Search by booking Id, name, or package..." />
        </div>
      <div class="modal-body">
        <!-- Accordion Container -->
        <div class="accordion" id="timeSlotAccordion"></div>
      </div>
    </div>
  </div>
</div>

 <!-- Transfer -->
<div class="modal fade" id="transferBookingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" 
       style="max-height: 80vh; overflow-y: auto;">
    <div class="modal-content" style="max-height: 80vh; overflow-y: auto;">
      
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Transfer Booking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
        <!-- Booking Info (Scrollable) -->
        <div id="transferBookingInfo" class="mb-3" style="max-height: 350px; overflow-y: auto;"></div>

        <!-- Slot & Guide Selection -->
        <div class="row">
          <div class="col-md-6">
            <label class="form-label fw-bold">Select Time Slot</label>
            <select id="transferSlotSelect" class="form-select">
              <option value="">-- Select Slot --</option>
            </select>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-bold">Select Guide</label>
            <select id="transferGuideSelect" class="form-select">
              <option value="">-- Select Guide --</option>
              <!-- Guides will load dynamically after slot selection -->
            </select>
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-success" id="confirmTransferBtn">Confirm Transfer</button>
      </div>
    </div>
  </div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Scan QR</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="qr-reader" style="width:100%; height:300px;"></div>
      </div>
    </div>
  </div>
</div>

<!-- Add Station Modal -->
<div class="modal fade" id="stationModal" tabindex="-1" aria-labelledby="stationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="addStationForm" action="{{ route($routePrefix . '.board.store-station') }}" method="POST">
            @csrf
            <input type="hidden" name="station_time_from" id="station_time_from">
            <input type="hidden" name="station_date" id="station_date">

            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stationModalLabel">Add Station</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div id="tourPackageError" class="text-danger mt-1" style="display:none;">
                        Please select at least one tour package.
                    </div>
                    <!-- Station Name -->
                    <div class="d-flex gap-4">
                        <div class="mb-2 w-100">
                            <label class="form-label">Station Name</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>

                        <!-- Status -->
                        <div class="mb-2 w-100">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <!-- Board Station Packages with Nested Tour Packages -->
                    <div class="mb-2" id="stationAdd">
                        <label class="form-label">Board Station Packages</label>
                        <div style="max-height: 350px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                            @foreach ($boardStationPackage as $bsp)
                                @php
                                    // Directly use array or decode only if string
                                    $assignedPackages = is_array($bsp->assign_packages)
                                        ? $bsp->assign_packages
                                        : json_decode($bsp->assign_packages, true);
                                @endphp

                                <div class="form-check mb-2">
                                    <input
                                        type="checkbox"
                                        class="form-check-input parent-checkbox"
                                        id="bsp_{{ $bsp->id }}"
                                        value="{{ $bsp->id }}"
                                    >
                                    <label class="form-check-label fw-bold" for="bsp_{{ $bsp->id }}">
                                        {{ $bsp->name }}
                                    </label>

                                    <div class="ms-4 mt-1">
                                        @foreach ($tourPackages as $package)
                                            @if (in_array($package->id, $assignedPackages))
                                                <div class="form-check">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input child-checkbox bsp-child-{{ $bsp->id }}"
                                                        name="tour_packages[]"
                                                        value="{{ $package->id }}"
                                                        id="package_{{ $bsp->id }}_{{ $package->id }}"
                                                    >
                                                    <label class="form-check-label" for="package_{{ $bsp->id }}_{{ $package->id }}">
                                                        {{ $package->name }}
                                                    </label>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <hr>
                            @endforeach
                            <div class="border rounded p-2" style="max-height: 400px; overflow-y: auto;">
                                <h4>General Packages</h4>     
                                <div class="row">
                                    @foreach ($tourPackages as $pkg)
                                        <div class="col-md-4 mb-1">
                                            <div class="form-check">
                                                <input class="form-check-input pkg-checkbox" type="checkbox" name="general_packages[]" id="pkg{{ $pkg->id }}" value="{{ $pkg->id }}">
                                                <label class="form-check-label" for="pkg{{ $pkg->id }}">{{ $pkg->name }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Station Modal -->
<div class="modal fade" id="editStationModal" tabindex="-1" aria-labelledby="editStationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form id="editStationForm" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editStationModalLabel">Edit Station</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="editStationId">
                    <div id="editTourPackageError" class="text-danger mt-1" style="display: none;">
                        Please select at least one tour package.
                    </div>
                    <!-- Station Name -->
                     <div class="d-flex gap-4">
                        <div class="mb-2 w-100">
                            <label class="form-label">Station Name</label>
                            <input type="text" class="form-control" name="name" id="editStationName" required>
                        </div>

                        <!-- Status -->
                        <div class="mb-2 w-100">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStationStatus" class="form-control" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>    

                    <!-- Board Station Packages with Nested Tour Packages -->
                    <div class="mb-2" id="stationEdit">
                        <label class="form-label">Board Station Packages</label>
                        <div style="max-height: 350px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                            @foreach ($boardStationPackage as $bsp)
                                @php
                                    $assignedPackages = is_array($bsp->assign_packages)
                                        ? $bsp->assign_packages
                                        : json_decode($bsp->assign_packages, true);
                                @endphp

                                <div class="form-check mb-2">
                                    <input
                                        type="checkbox"
                                        class="form-check-input parent-checkbox"
                                        id="edit_bsp_{{ $bsp->id }}"
                                        value="{{ $bsp->id }}"
                                    >
                                    <label class="form-check-label fw-bold" for="edit_bsp_{{ $bsp->id }}">
                                        {{ $bsp->name }}
                                    </label>

                                    <div class="ms-4 mt-1">
                                        @foreach ($tourPackages as $package)
                                            @if (in_array($package->id, $assignedPackages))
                                                <div class="form-check">
                                                    <input
                                                        type="checkbox"
                                                        class="form-check-input child-checkbox bsp-child-{{ $bsp->id }}"
                                                        name="tour_packages[]"
                                                        value="{{ $package->id }}"
                                                        id="edit_package_{{ $bsp->id }}_{{ $package->id }}"
                                                    >
                                                    <label class="form-check-label" for="edit_package_{{ $bsp->id }}_{{ $package->id }}">
                                                        {{ $package->name }}
                                                    </label>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                                <hr>
                            @endforeach
                            <div class="border rounded p-2" style="max-height: 400px; overflow-y: auto;">
                                <h4>General Packages</h4>     
                                <div class="row">
                                    @foreach ($tourPackages as $pkg)
                                        <div class="col-md-4 mb-1">
                                            <div class="form-check">
                                                <input class="form-check-input pkg-checkbox" type="checkbox" name="general_packages[]" id="pkg{{ $pkg->id }}" value="{{ $pkg->id }}">
                                                <label class="form-check-label" for="pkg{{ $pkg->id }}">{{ $pkg->name }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Guide Management Modal -->
<div class="modal fade" id="guideManagementModal" tabindex="-1" aria-labelledby="guideManagementModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
    <div class="modal-content">
        <div class="modal-header bg-primary text-white align-items-center">
          <h5 class="modal-title" id="guideManagementModalLabel">Guide Management</h5>
          
            <!-- Time & Date Display -->
            <div class="mx-auto text-center">
              <span id="guideDate" class="fw-bold" style="font-size:1.3rem;"></span>
              <span id="guideTime" class="ms-2" style="font-size:1.3rem; font-weight:500;"></span>
              <span id="guideCapacityDisplay" style="font-size:1.3rem; padding-left: 100px;" class="ms-3 fw-bold guide-capacity"></span>
            </div>

            <!-- Time Slot Button -->
            <button type="button" class="btn btn-success timeSlotBtn me-2" style="padding:6px 14px; font-size:0.90rem; margin-right:10px;">
                <i class="fas fa-clock me-1"></i> Time Slot
            </button>

            <!-- PDF Download Button -->
            <a id="downloadPdfBtn" class="btn btn-danger" style="padding:6px 14px; font-size:0.85rem; margin-right:10px;">
                Download PDF
            </a>

            <button type="button" class="btn-close custom-close" data-bs-dismiss="modal"></button>

        </div>

      <div class="modal-body">

        <div class="app-container">
          <div class="max-w-container">
            <div class="main-grid">
              <!-- Available Guides Panel -->
              
              <div class="sidebar">
                <div class="card">
                  <h2 class="section-title">
                    <span class="icon">👥</span>
                    Available Guides
                  </h2>
                  <span class="badge bg-primary position-absolute top-0 end-0 m-2" style="cursor:pointer;" id="autoAssignGuideBtn">
                    Auto-Assign
                  </span>
                  
                  <!-- Search Bar for Guides -->
                  <div class="mb-3 mt-2">
                    <div class="input-group input-group-sm">
                      <span class="input-group-text bg-light border-end-0">
                        <i class="fas fa-search text-muted"></i>
                      </span>
                      <input 
                        type="text" 
                        id="guidesSearchInput" 
                        class="form-control border-start-0" 
                        placeholder="Search guides by name..."
                        style="box-shadow: none; border-left: none;"
                      >
                      <button 
                        type="button" 
                        id="clearGuidesSearch" 
                        class="btn btn-outline-secondary btn-sm"
                        style="display: none;"
                        title="Clear search"
                      >
                        <i class="fas fa-times"></i>
                      </button>
                    </div>
                  </div>
                  
                  <div id="guidesList" class="guides-list">
                    <!-- Guides will be populated by JavaScript -->
                  </div>
                  
                  <!-- No results message -->
                  <div id="guidesNoResults" class="text-muted text-center py-3" style="display: none;">
                    <i class="fas fa-search mb-2"></i>
                    <div>No guides found matching your search</div>
                  </div>
                </div>

                 <!-- Pending Bookings -->
                <div class="card position-relative">
                  <h2 class="section-title mt-2">
                    <span class="icon">📦</span>
                    Pending Bookings
                  </h2>

                   <!-- Totals + Auto-Assign -->
                  <div class="position-absolute top-0 end-0 m-2 d-flex align-items-center gap-2">
                    <span class="text-muted small">
                      👥 <strong id="totalPendingTourist">0</strong>
                      &nbsp;|&nbsp;
                      👶 <strong id="totalPendingInfants">0</strong>
                    </span>
                    <span class="badge bg-primary" style="cursor:pointer;" id="autoAssignBtn">
                      Auto-Assign
                    </span>
                  </div>

                  <div id="pendingBookingsList" class="bookings-list mt-2">
                    <!-- Bookings will be populated by JavaScript -->
                  </div>
                </div>

                 <!-- Busy guides -->
                <div class="card position-relative">
                  <h2 class="section-title">
                    <span class="icon">🚫</span>
                    Busy Guides
                  </h2>

                  <div id="notAvailableGuidesList" class="bookings-list mt-2">
                    <!-- Not avaialble guides will be populated by JavaScript -->
                  </div>
                </div>

                 <!-- Absent bookings -->
                <div class="card position-relative">
                  <h2 class="section-title">
                    <span class="icon">🚫</span>
                    Absent Bookings
                  </h2>

                  <div id="absentBookingsList" class="bookings-list mt-2">
                    <!-- Bookings will be populated by JavaScript -->
                  </div>
                </div>
              </div>

              <!-- Stations Grid -->
              <div class="stations-grid">
                <div id="stationsWrapper" class="stations-container">
                  <!-- Stations will be populated by JavaScript -->
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
 
<!-- Assign Guides Modal -->
@include('partials.board.assign-guides-manual-update',['tourGuides' => $tourGuides])

<!-- Specific station edit modal -->

<div class="modal fade" id="editSpecificStationModal" tabindex="-1">
    <div class="modal-dialog">
        <form id="editSpecificStationForm">
            <div class="modal-content">
                <div class="modal-header align-items-center">
                    <div class="w-100 text-center">
                        <div class="fw-semibold" style="font-size:1.05rem;">
                            Edit Station Name
                        </div>
                        <div id="editStationSlotInfo"
                             class="text-success fw-semibold"
                             style="font-size:0.85rem;">
                        </div>
                    </div>

                    <button type="button"
                            class="btn-close position-absolute end-0 me-3"
                            data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="station_id">
                    <input type="hidden" name="date">
                    <input type="hidden" name="time_from">
                    <input type="hidden" name="station_specific">

                    <div class="mb-3">
                        <label class="form-label">Station Name</label>
                        <input type="text" class="form-control" name="station_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">
                            Packages <small class="text-muted fw-normal">(override for this date / time only)</small>
                        </label>
                        <div style="max-height:220px; overflow-y:auto; border:1px solid #dee2e6; border-radius:6px; padding:10px;">
                            @foreach($tourPackages as $pkg)
                            <div class="form-check">
                                <input class="form-check-input station-specific-pkg-cb"
                                       type="checkbox"
                                       name="packages[]"
                                       value="{{ $pkg->id }}"
                                       id="sspkg_{{ $pkg->id }}">
                                <label class="form-check-label" for="sspkg_{{ $pkg->id }}">{{ $pkg->name }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Specific guide capacity edit modal -->
@include('partials.board.edit-guide-capacity-modal')

<!-- Bulk capacity update modal -->
@include('partials.board.bulk-capacity-update-modal')

<!-- Flatpickr CSS/JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="{{ asset('plugins/html5-qrcode/html5-qrcode.js')  }}"></script>
<script src="{{ asset('js/user-common/checkin/qr-cd-checkin.js') }}"></script>
<script src="{{ asset('vendor/qrcodejs/qrcode.min.js') }}"></script>

<script>
    var tourGuides = @json($tourGuides);
    var assignments = @json($assignments);
    let indexUrl = "{{ route($routePrefix . '.board.index') }}";
    let boardGuideManagementUrl = "{{ route($routePrefix . '.board.guide-management') }}";
    let assignGuideAndBookingToStationUrl = "{{ route($routePrefix . '.board.assign-guide-and-booking')}}";
    let csrfToken = $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}';
    let tenantId = "{{ getSubdomainFromHost() }}";
    tenantId = tenantId.toUpperCase();
    let updateStationOrderUrl = "{{ route($routePrefix . '.board.update-station-order') }}";
    let autoAssignUrl = "{{ route($routePrefix . '.board.auto-assign') }}";
    let unAssignBookingUrl = "{{ route($routePrefix . '.board.unassign-booking') }}";
    let unAssignGuideUrl = "{{ route($routePrefix . '.board.unassign-guide') }}";
    let bulkAssignUrl = "{{ route($routePrefix . '.board.bulk-assign') }}";
    let getAssignedGuidesUrl = "{{ route($routePrefix . '.board.get-manual-update', ['bookingId' => ':bookingId']) }}";
    let assignTourGuideUrl = "{{ route($routePrefix.'.board.manual-update', ['bookingId' => ':bookingId']) }}";
    let bulkCheckInUrl = "{{ route($routePrefix.'.board.bulk-checkin') }}";
    let swapBookingUrl = "{{ route($routePrefix.'.board.swap-bookings') }}";
    let searchBookingUrl = "{{ route($routePrefix.'.board.search-booking') }}";
    let guidesUrl = "{{ route($routePrefix.'.board.guides') }}";
    let transferBookingUrl = "{{ route($routePrefix.'.board.transfer-booking') }}";
    let userType = "{{ $userType }}";
    let currentSlotTime = document.getElementById("currentSlotTime")?.value || null;
    let slotSelect = document.getElementById("transferSlotSelect");
    let timeSlotTransfer = "{{ route($routePrefix.'.board.time-slot-transfer') }}";
    let deleteStationPackages = "{{ route($routePrefix . '.board.station-packages.destroy', '') }}/"; 
    let showStationPackages = "{{ route($routePrefix . '.board.station-packages.edit', '') }}/";
    let updateStationPackages = "{{ route($routePrefix . '.board.station-packages.update', '') }}/";
    let storeStationPackages = "{{ route($routePrefix . '.board.station-packages.store') }}";
    let timezone = "{{ $timezone }}";
    let updateGuideStatusUrl = "{{ route($routePrefix . '.board.update-guide-status') }}";
    let specificStationsUpdateNameUrl = "{{ route($routePrefix . '.board.specific-stations-update-name') }}";
    let specificStationsPackagesUrl = "{{ route($routePrefix . '.board.specific-stations-packages') }}";
    let specificStationsUpdatePackagesUrl = "{{ route($routePrefix . '.board.specific-stations-update-packages') }}";
    let adminBoardPdfUrl = "{{ route($routePrefix . '.board.admin-board-pdf') }}";
    let guideStatusActiveAllUrl = "{{ route($routePrefix . '.board.guide-status-active-all') }}";
    let autoAssignGuideUrl = "{{ route($routePrefix . '.board.auto-assign-guide') }}";
    let readyStatusUpdateUrl = "{{ route($routePrefix . '.board.ready-status-update') }}";
    let updateGuideCapacityUrl = "{{ route($routePrefix . '.board.update-guide-capacity') }}";
    let updateBulkCapacityUrl = "{{ route($routePrefix . '.board.update-bulk-capacity') }}";
    let updateBookingCheckedUrl = "{{ route($routePrefix . '.board.update-booking-checked') }}";
    let markGuideAvailableUrl = "{{ route($routePrefix . '.board.mark-guide-available') }}";
    let unlockStationUrl = "{{ route($routePrefix . '.board.unlock-station') }}";
</script>

<script src="{{ asset('js/admin/board/station.js') }}"></script>
<script src="{{ asset('js/admin/board/time-slot-transfer.js') }}"></script>
<script src="{{ asset('js/admin/board/qr.js') }}"></script>
<script src="{{ asset('js/admin/board/station-packages.js') }}"></script>
<script src="{{ asset('js/admin/board/admin-board-pdf.js') }}"></script>
<x-delete-record-js-file />

@push('scripts')
<script>
    SocketManager.register(new BoardSocketModule());
    SocketManager.subscribe('tenant.{{ tenancy()->tenant->id }}');
</script>
@endpush
@endsection
