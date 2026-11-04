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

                            // Resolve active package IDs (specific override > station default)
                            $defaultPackageIds = json_decode($station->tour_packages ?? '[]', true) ?? [];
                            $overridePackagesJson = DB::table('board_station_specific_packages')
                                ->where('station_id', $station->id)
                                ->where('date', $slot->date)
                                ->where('time_from', $slot->time_from)
                                ->value('packages');
                            $activePackageIds = $overridePackagesJson
                                ? (json_decode($overridePackagesJson, true) ?? $defaultPackageIds)
                                : $defaultPackageIds;

                            $editBtn = '';

                            $editBtn = '<button
                                            class="btn btn-sm btn-outline-primary ms-2 edit-station-btn"
                                            style="padding:2px 6px; font-size:12px; line-height:1;"
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