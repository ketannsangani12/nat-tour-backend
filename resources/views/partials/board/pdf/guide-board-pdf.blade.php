<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: sans-serif; }
        .badge { padding: 4px 8px; border-radius: 4px; color:white; font-size:12px; }
        .bg-primary { background:#0d6efd; }
        .bg-secondary { background:#6c757d; }
        .bg-success { background:#198754; }
        .bg-info { background:#0dcaf0; color:black; }
        .bg-danger { background:#dc3545; }

        table { width:100%; border-collapse: collapse; margin-bottom:20px; }
        table th, table td { border:1px solid #ccc; padding:6px; font-size:12px; text-align:center; }
        table thead { background:#0d6efd; color:white; }
        h3 { margin:0 0 10px 0; }
        .section { margin-bottom:20px; border:1px solid #ddd; padding:10px; }
    </style>
</head>

<body>

<h3>Guide Board — {{ $selectedDate }}</h3>
<h4>Guide: {{ $guideName }}</h4>
<p><strong>Total Assigned:</strong> {{ $totalAssigned }} / {{ $totalCapacity }}</p>

@foreach($grouped as $time => $bookings)
    @php
        $formattedTime = \Carbon\Carbon::createFromFormat('H:i:s', $time)->format('g:i A');
        $totalAssignedSlot = $bookings->sum('assigned_tourists');
        $totalCapacitySlot = $bookings->first()->capacity ?? 0;
        $stationName = $bookings->first()->station_name ?? '';
    @endphp

    <div class="section">
        <h4>
            {{ $formattedTime }}
            <span class="badge bg-primary">{{ $bookings->count() }} Booking</span>
            <span class="badge bg-secondary">{{ $totalAssignedSlot }}/{{ $totalCapacitySlot }} Assigned</span>
            <span class="badge bg-info">{{ $stationName }}</span>
        </h4>

        <table>
            <thead>
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
                    @php
                        $checkIn = $row->checkInsCount;
                        $totalCheckIns = $checkIn['1'] + $checkIn['2'] + $checkIn['A'];
                        $notCheckedIn = max(0, $row->assigned_tourists - $totalCheckIns);
                    @endphp
                    <tr>
                        <td>{{ $row->id }}</td>
                        <td>{{ $row->name }} <br> {{ $row->email }}</td>
                        <td>{{ $row->tour_package }}</td>
                        <td>
                            @if($row->assigned_tourists > 0)
                                Tourists: {{ $row->assigned_tourists }}
                                Infants: {{ $row->infants }}
                            @endif
                        </td>

                        <td>
                            @foreach($row->assignedGuides as $g)
                                <span class="badge bg-success">{{ $g->guide_name }} ({{ $g->tourists }}) ({{ $g->infants }})</span><br>
                            @endforeach
                        </td>

                        <td>
                            @if($checkIn['1'] > 0)
                                <span class="badge bg-success">1st: {{ $checkIn['1'] }}</span><br>
                            @endif
                            @if($checkIn['2'] > 0)
                                <span class="badge bg-info">2nd: {{ $checkIn['2'] }}</span><br>
                            @endif
                            @if($checkIn['A'] > 0)
                                <span class="badge bg-danger">Absent: {{ $checkIn['A'] }}</span><br>
                            @endif
                            @if($notCheckedIn > 0)
                                <span class="badge bg-secondary">Not Checked: {{ $notCheckedIn }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

    </div>
@endforeach

</body>
</html>
