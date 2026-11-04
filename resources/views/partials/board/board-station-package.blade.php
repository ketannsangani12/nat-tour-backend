
<div class="page-content">
	<div class="content-title">
		<h2><i class="fas fa-box"></i> Station Packages</h2>
	</div>
	<div class="card-body">
        <!-- Add Button -->
        <div class="mb-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#boardStationPackageModal">
                <i class="fa fa-plus"></i> Add Parent Package
            </button>
        </div>

        <!-- Modal (Add/Edit) -->
        <div class="modal fade" id="boardStationPackageModal" tabindex="-1" aria-labelledby="boardStationPackageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form id="boardStationPackageForm">
                        @csrf
                        <input type="hidden" name="id" id="recordId">
                        <div class="modal-header">
                            <h5 class="modal-title" id="boardStationPackageModalLabel">Add Parent Package</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label><strong>Parent Package Name</strong></label>
                                    <input type="text" name="name" id="packageName" class="form-control" placeholder="Enter Parent Package Name" required>
                                </div>

                                <div class="col-md-12 mt-3">
                                    <label><strong>Assign Packages</strong></label>
                                    <div class="mb-2 mt-1">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectAllPackages">Select All</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="deselectAllPackages">Deselect All</button>
                                    </div>
                                    <div class="border rounded p-2" style="max-height: 400px; overflow-y: auto;">
                                        
                                        <div class="row">
                                            @foreach ($allPackages as $pkg)
                                                <div class="col-md-4 mb-1">
                                                    <div class="form-check">
                                                        <input class="form-check-input pkg-checkbox" type="checkbox" name="assign_packages[]" id="pkg{{ $pkg->id }}" value="{{ $pkg->id }}">
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
                            <button type="submit" class="btn btn-success btn-sm">Save</button>
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Table -->
        <table class="table table-bordered mt-3" id="boardStationPackagesTable">
            <thead>
                <tr>
                    <th width="5%">#</th>
                    <th>Parent Package Name</th>
                    <th>Assigned Packages</th>
                    <th width="15%">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($boardStationPackage as $p)
				    @php
				        $assigned = \App\Models\TourPackage::whereIn('id', $p->assign_packages ?? [])->pluck('name')->toArray();
				    @endphp
				    <tr data-id="{{ $p->id }}">
				        <td>{{ $loop->iteration }}</td>
				        <td class="pkg-name">{{ $p->name }}</td>
				        <td class="pkg-assigned">
				            @if(!empty($assigned) && count($assigned) > 0)
				                @foreach($assigned as $pkg)
				                    <span class="badge bg-info me-1 mb-1">{{ $pkg }}</span>
				                @endforeach
				            @else
				                <span class="text-muted">-</span>
				            @endif
				        </td>
				        <td>
				            <button class="btn btn-warning btn-sm editRecord"><i class="fa fa-edit"></i></button>
				            <button class="btn btn-danger btn-sm deleteRecord"><i class="fa fa-trash"></i></button>
				        </td>
				    </tr>
				@endforeach
            </tbody>
        </table>
    </div>
</div>
