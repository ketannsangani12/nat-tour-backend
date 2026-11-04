 @php
    $routePrefix = routePrefix();
@endphp
 <div class="d-flex justify-content-between align-items-center mb-4 px-2 mt-2">
    <h5 class="mb-0">🗓️
        {{ $selectedDate }}
    </h5>
    <span class="badge bg-info fs-6">
        {{ $guideName }}
    </span>

    <div class="d-flex flex-column align-items-start">
        <select name="guide_status" id="guide_status" data-guide-id="{{ $guide->id }}"
                class="form-select form-select-sm status-dropdown text-white fw-semibold" 
                style="min-width: 100px;">
            <option value="Active" {{ old('guide_status', $guide->guide_status ?? '') == 'Active' ? 'selected' : '' }}>Active</option>
            <option value="Inactive" {{ old('guide_status', $guide->guide_status ?? '') == 'Inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>

    <span class="badge bg-info fs-6">
        Total Assigned: {{ $totalAssigned }} / {{ $totalCapacity }}
    </span>
    <a href="{{ route($routePrefix . '.board.guide-board-pdf', ['date' => request('date')]) }}"
       class="btn btn-danger btn-sm">
       Download PDF
    </a>

</div>
@if($grouped->isEmpty())
    <div class="alert alert-warning text-center m-4">No bookings found for this date.</div>
@else
    <div class="accordion" id="bookingAccordion">

        @foreach($grouped as $time => $bookings)
            @php
                $formattedTime = \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
                $collapseId = 'collapse-' . str_replace(':', '-', $time);

                // Sum total assigned tourists and capacity
                $totalAssigned = $bookings->sum('assigned_tourists');
                $totalCapacity = $bookings->first()->capacity ?? 0;;
                $stationName = $bookings->first()->station_name ?? '';;
                $tenantId = strtoupper(getSubdomainFromHost());
                $hasValidBookings = isset($bookings[0]) && !is_null($bookings[0]->id);
            @endphp

            <div class="accordion-item mb-3 shadow-sm">
                <h2 class="accordion-header" id="heading-{{ $loop->index }}">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}" aria-expanded="true"
                            aria-controls="{{ $collapseId }}">
                        <strong>{{ $formattedTime }}</strong>

                        @if($hasValidBookings)
                            <span class="badge bg-primary ms-2">
                                {{ $bookings->count() }} Booking{{ $bookings->count() > 1 ? 's' : '' }}
                            </span>
                        @else
                            <span class="badge bg-primary ms-2">
                                 0 Booking
                            </span>
                        @endif

                        <span class="badge bg-secondary ms-2">
                            {{ $totalAssigned }}/{{ $totalCapacity }} Assigned
                        </span>
                        <span class="badge bg-secondary ms-2">
                            {{ $stationName }}
                        </span>
                    </button>
                </h2>

                <div id="{{ $collapseId }}" class="accordion-collapse collapse show" aria-labelledby="heading-{{ $loop->index }}">
                    <div class="accordion-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover w-100 text-center align-middle mb-0">
                                <thead class="bg-primary text-white">
                                    <tr>
                                        <th>Booking ID</th>
                                        <th>Customer</th>
                                        <th>Tour Package</th>
                                        <th>Assigned Tourists</th>
                                        <th>Guide</th>
                                        <th>Check-in Status</th>
                                        
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bookings as $row)
                                        <tr>
                                            <td>
                                                <!-- <a href="#" class="text-decoration-underline text-primary fw-bold">
                                                    {{ $row->id }}
                                                </a> -->
                                                {{ $row->id ? $tenantId : ''}}-{{ $row->id }}
                                            </td>
                                            <td>{{ $row->name }} {{ $row->email }}</td>
                                            <td>{{ $row->tour_package }}</td>
                                            <td>
                                                @if($row->assigned_tourists > 0)
                                                <span class="badge bg-success">
                                                   Tourists: {{ $row->assigned_tourists }}
                                                </span>
                                                <span class="badge bg-success">
                                                    Infants: {{ $row->infants }}
                                                </span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($row->assignedGuides) && count($row->assignedGuides) > 0)
                                                    @foreach($row->assignedGuides as $guide)
                                                        <span class="badge bg-success">
                                                            {{ $guide->guide_name }} ({{ $guide->tourists }}) ({{ $guide->infants }})
                                                        </span>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $checkIn = $row->checkInsCount ?? ['1' => 0, '2' => 0, 'A' => 0];
                                                    $totalCheckIns = $checkIn['1'] + $checkIn['2'] + $checkIn['A'];
                                                    $notCheckedIn = max(0, $row->assigned_tourists - $totalCheckIns);
                                                @endphp

                                                @if($checkIn['1'] > 0)
                                                    <span class="badge bg-success me-1">1st Check-In: {{ $checkIn['1'] }}</span>
                                                @endif

                                                @if($checkIn['2'] > 0)
                                                    <span class="badge bg-info text-dark me-1">2nd Check-In: {{ $checkIn['2'] }}</span>
                                                @endif

                                                @if($checkIn['A'] > 0)
                                                    <span class="badge bg-danger me-1">Absent: {{ $checkIn['A'] }}</span>
                                                @endif

                                                @if($notCheckedIn > 0)
                                                    <span class="badge bg-secondary">Not Checked In: {{ $notCheckedIn }}</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        @endforeach
    </div>
@endif
