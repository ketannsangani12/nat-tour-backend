@extends('layouts.default-2')
@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-83px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">Edit Network</h2>
                                @if (session('success'))
                                    <div id="success-alert" class="alert alert-success alert-dismissible fade show my-3" role="alert">
                                        {{ session('success') }}
                                    </div>
                                @endif
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="mr-2 btn-warning-small" href="{{ route(routePrefix() . '.networks.create') }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
                                <a class="btn-outline-primary-small" href="{{ route(routePrefix() . '.networks.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

                    <div class="main-content">
                        {{-- Edit Network Form --}}
                        <form action="{{ route(routePrefix() . '.networks.update', $network->id) }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <div class="flex flex-wrap -mx-3">
                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="name" class="form-label form-custom-label">Network Name</label>
                                    <input type="text" name="name" class="form-control form-custom-input" value="{{ old('name', $network->name) }}" required>
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="frontend_url" class="form-label form-custom-label">Frontend URL</label>
                                    <input type="url" name="frontend_url" class="form-control form-custom-input" value="{{ old('frontend_url', $network->frontend_url) }}" required>
                                    @error('frontend_url')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <div class="w-full md:w-1/3 max-w-full px-3 mb-3">
                                    <label for="status" class="form-label form-custom-label">Status</label>
                                    <select name="status" class="form-select form-custom-input">
                                        <option value="1" {{ $network->status == 1 ? 'selected' : '' }}>Active</option>
                                        <option value="0" {{ $network->status == 0 ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="flex justify-between items-center">
                                {{-- Left side buttons --}}
                                <div class="flex items-center">
                                    <button type="submit" class="btn btn-primary btn-primary-small mr-2">Update Network</button>
                                    <a href="{{ route(routePrefix() . '.networks.index') }}" class="btn btn-secondary btn-light-small">Cancel</a>
                                </div>

                                {{-- Right side button --}}
                                @if (count($networkTenants) >= 2)
                                <a href="{{ route(routePrefix() . '.networks.assign-user', $network->id) }}" class="btn btn-primary btn-primary-small">
                                    Assign User
                                </a>
                                @endif
                            </div>


                        </form>

                        {{-- Assigned Tenants Table --}}
                        <h4 class="section-title mt-4 mb-3">Assigned Tenants</h4>
                        <div class="w-full overflow-y-auto">
                            <table class="table table-bordered table-hover w-full border-collapse">
                                <thead>
                                    <tr>
                                        <th class="th-custom-css">Tenant ID</th>
                                        <th class="th-custom-css">Status</th>
                                        <th class="th-custom-css" style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($networkTenants as $tenant)
                                    <tr>
                                        <td class="td-custom-css">{{ $tenant->tenant_id }}</td>
                                        <td class="td-custom-css">{{ $tenant->status ? 'Active' : 'Inactive' }}</td>
                                        <td class="td-custom-css flex items-center">
                                                <button class="btn btn-primary btn-sm btn-primary-small mr-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#configureModal"
                                                data-tenant-id="{{ $tenant->tenant_id }}"
                                                data-network-id="{{ $tenant->network_id }}"
                                            >
                                                Configure
                                            </button>

                                            <form action="{{ route(routePrefix() . '.networks.removeTenant', $tenant->id) }}" method="POST" onsubmit="return confirm('Remove this tenant?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-danger btn-sm btn-danger-small">Remove</button>
                                            </form>
                                        </td>

                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="3" class="td-custom-css text-center">No tenants assigned yet.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Assign New Tenant --}}
                        <h5 class="section-title mt-4 mb-3">Assign New Tenant</h5>

                        <form action="{{ route(routePrefix() . '.networks.assignTenant', $network->id) }}" method="POST" class="sm:flex items-center">
                            @csrf
                            <select name="tenant_id" class="form-select form-custom-input" required>
                                <option value="">Select Tenant</option>
                                @foreach($allTenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->id }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary btn-primary-small max-[639px]:mt-2 sm:ml-2">Assign</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Configuration Modal -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden" id="configureModal" tabindex="-1" aria-labelledby="configureModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen max-w-[400px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
      <form id="configurationForm" method="POST" action="{{ route(routePrefix() . '.networks.save-configuration') }}">
        @csrf
        <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
          <h3 class="text-lg font-semibold text-[#111827]">Configure Tenant</h3>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
        </div>


        <div class="modal-body p-4">
          <input type="hidden" name="tenant_id" id="modalTenantId">
          <input type="hidden" name="network_id" id="modalNetworkId">

            <!-- Allow Calendar Switch -->
            <div class="flex flex-wrap -mx-3 mb-2 mt-2">
              <div class="w-full max-w-full px-3">
                <div class="form-check form-switch flex items-center">
                    <label class="form-check-label mb-0 form-custom-label" for="calendarSwitch">
                    Allow Calendar Switch Without Login
                  </label>
                  <input class="form-check-input form-custom-input ml-2" type="checkbox" name="calendar_switch" id="calendarSwitch">
                  
                </div>
              </div>
            </div>

          <!-- Package Assignments (loaded via AJAX) -->
          <div>
            <h3 class="text-base font-semibold text-[#111827] mb-3">Package Assignment</h3>
            <div class="flex items-center mb-2">
              <button type="button" class="btn btn-sm btn-outline-primary btn-primary-small mr-2" id="selectAllPackages">Select All</button>
              <button type="button" class="btn btn-sm btn-outline-secondary btn-outline-primary-small" id="deselectAllPackages">Deselect All</button>
            </div>
            <div id="packageCheckboxes" class="flex flex-wrap -mx-3 mt-3" style="max-height: 300px; overflow-y: auto;">
              <!-- Checkboxes will be injected here -->
            </div>
          </div>
        </div>

        <div class="modal-footer px-4 pb-4">
          <button type="submit" class="btn btn-success btn-success-small">Save Configuration</button>
          <button type="button" class="btn btn-secondary btn-light-small" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
    const getTenantTourPackage = "{{ route(routePrefix() . '.networks.get-tenant-tour-package') }}";
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/admin/configure.js?v=44') }}"></script>

@endsection

