@php
    $routePrefix = routePrefix();
@endphp

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