<!DOCTYPE html>
<html>
<head>
<style>
    body { font-family: sans-serif; font-size: 11px; }
    .station-card { border:1px solid #999; padding:10px; border-radius:6px; margin-bottom:20px;}
    .station-header { font-weight:bold; font-size:14px; margin-bottom:6px; }
    .pkg { font-size:11px; color:#555; }

    .badge {
        font-size: 10px;
        padding: 3px 6px;
        border-radius: 4px;
        color: #fff;
    }
    .bg-primary { background:#0d6efd; }
    .bg-success { background:#198754; }
    .bg-warning { background:#ffc107; color:black }
    .bg-danger { background:#dc3545; }
    .bg-secondary { background:#6c757d; }

    table { width:100%; border-collapse:collapse; margin-top:8px;}
    th, td { border:1px solid #ccc; padding:5px; font-size:10px; text-align:center;}
</style>
</head>
<body>

<h2>
    Guide Management — {{ $date }}
    @if($time) ({{ \Carbon\Carbon::parse($time)->format('g:i A') }}) @endif
</h2>

@foreach($stations as $station)
<div class="station-card">

    {{-- Station Name --}}
    <div class="station-header">
        {{ $station->name }}
    </div>

    {{-- Packages --}}
    @if(!empty($station->packages))
        <div class="pkg">
            <strong>Packages:</strong>
            {{ collect($station->packages)->pluck('name')->join(', ') }}
        </div>
    @endif

    {{-- Assigned Guide --}}
    @php
        $guide = ($station->guides && count($station->guides) > 0)
            ? $station->guides[0]
            : null;

        $totalAssigned = collect($station->bookings ?? [])->sum('assignedTourists');
        $capacity = $guide->capacity ?? 0;
    @endphp

    <p style="margin:6px 0;">
        <strong>Assigned Guide:</strong>
        @if($guide)
            <span class="badge bg-primary">
                {{ $guide->guideName }} ({{ $totalAssigned }}/{{ $capacity }})
            </span>
        @else
            <span class="badge bg-secondary">No guide assigned</span>
        @endif
    </p>

    {{-- Bookings --}}
    <table>
        <thead>
            <tr>
                <th style="width:80px;">Booking ID</th>
                <th>Customer</th>
                <th>Tour Package</th>
                <th style="width:80px;">Assigned</th>
                <th style="width:140px;">Check-In Status</th>
            </tr>
        </thead>
        <tbody>

        @forelse($station->bookings as $b)

            @php
                $assigned = (int) ($b->assignedTourists ?? 0);
                $infants = (int) ($b->infants ?? 0);

                $ciSource = $b->checkInsCount ?? [];
                $ciArray  = is_array($ciSource) ? $ciSource : (array) $ciSource;

                $ci1 = (int) ($ciArray['1'] ?? 0);
                $ci2 = (int) ($ciArray['2'] ?? 0);
                $ciA = (int) ($ciArray['A'] ?? 0);

                // Normalize total check-ins so they NEVER exceed assigned
                $totalCI = $ci1 + $ci2 + $ciA;
                $overflow = $totalCI - $assigned;

                if ($overflow > 0) {
                    // Reduce Absent first
                    $reduce = min($ciA, $overflow);
                    $ciA -= $reduce;
                    $overflow -= $reduce;

                    // Then reduce 2nd check-in
                    if ($overflow > 0) {
                        $reduce = min($ci2, $overflow);
                        $ci2 -= $reduce;
                        $overflow -= $reduce;
                    }

                    // Then reduce 1st check-in
                    if ($overflow > 0) {
                        $ci1 = max(0, $ci1 - $overflow);
                    }
                }

                $pending = max(0, $assigned - ($ci1 + $ci2 + $ciA));
            @endphp

            <tr>
                <td>{{ $b->id }}</td>
                <td>{{ $b->name }}</td>
                <td>{{ $b->tour_package }}</td>
                <td>
                   Tourists: {{ $assigned }}
                   Infants: {{ $infants }}
                </td>
                <td>
                    @if($ci1 > 0)
                        <span class="badge bg-warning">1st: {{ $ci1 }}</span>
                    @endif
                    @if($ci2 > 0)
                        <span class="badge bg-success">2nd: {{ $ci2 }}</span>
                    @endif
                    @if($ciA > 0)
                        <span class="badge bg-danger">Absent: {{ $ciA }}</span>
                    @endif
                    @if($pending > 0)
                        <span class="badge bg-secondary">Pending: {{ $pending }}</span>
                    @endif
                </td>
            </tr>

        @empty
            <tr>
                <td colspan="5">No assigned bookings</td>
            </tr>
        @endforelse

        </tbody>
    </table>

</div>
@endforeach

{{-- Unassigned Bookings --}}
@if(count($unassignedBookings) > 0)
<h3>Pending Bookings (Not Assigned)</h3>
<table>
    <thead>
        <tr><th>ID</th><th>Customer</th><th>Tour Package</th><th>Guests</th></tr>
    </thead>
    <tbody>
        @foreach($unassignedBookings as $p)
        <tr>
            <td>{{ $p->id }}</td>
            <td>{{ $p->name }}</td>
            <td>{{ $p->tour_package }}</td>
            <td>Tourists: {{ $p->pending_tourists }} Infants: {{ $p->pending_infants }} </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Absent Bookings --}}
@if(count($absentBookings) > 0)
<h3>Fully Absent Bookings</h3>
<table>
    <thead>
        <tr><th>ID</th><th>Customer</th><th>Tour Package</th><th>Guests</th></tr>
    </thead>
    <tbody>
        @foreach($absentBookings as $a)
        <tr>
            <td>{{ $a->id }}</td>
            <td>{{ $a->name }}</td>
            <td>{{ $a->tour_package }}</td>
            <td>{{ $a->total_tourists }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Guide Summary --}}
@if(count($guides) > 0)
<h3 style="margin-top:15px;">Guide Summary</h3>

<table>
    <thead>
        <tr>
            <th>Guide</th>
            <th style="width:90px;">Capacity</th>
            <th style="width:90px;">Assigned</th>
            <th style="width:90px;">Remaining</th>
        </tr>
    </thead>
    <tbody>
        @foreach($guides as $g)
        @php
            $remaining = max(0, $g->capacity - $g->tourists_assigned);
        @endphp
        <tr>
            <td>{{ $g->guideName }}</td>
            <td>{{ $g->capacity }}</td>
            <td>{{ $g->tourists_assigned }}</td>
            <td>{{ $remaining }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

</body>
</html>
