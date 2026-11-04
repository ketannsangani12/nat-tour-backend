@php
    $routePrefix = routePrefix();
    $userType = userType();
    $authId = Auth::id();
@endphp
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