@extends('layouts.default-2')
@section('main-content')

<div class="page-content py-6 bg-[#F9FAFB]">
    <div class="w-full container">
        <div class="flex flex-wrap -mx-3">
		    <div class="w-full max-w-full px-3">
                <div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center">
						<div class="w-full max-w-full px-3">
                            @if (session('success'))
                                <div id="success-alert" class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                </div>
                            @endif

                            <div class="card shadow-lg border border-[#E5E7EB] rounded-lg overflow-hidden">
                                <div class="card-header px-4 py-3 bg-[#F9FAFB] border-b border-[#E5E7EB] form-custom-label w-full !flex items-center justify-between">
                                    <h3 class="mb-0">Switch Company</h3>
                                    <a href="{{ $backUrl }}" class="btn btn-light btn-sm btn-light-small">
                                        <i class="fas fa-arrow-left me-1"></i> Back
                                    </a>
                                </div>

                                <div class="card-body p-4">
                                    <form method="POST" id="assign-users-form" action="{{ route(routePrefix() . '.networks.assign-tenants-user') }}">
                                        @csrf
                                        <input type="hidden" name="network_id" value="{{$networkId}}">
                                        <input type="hidden" name="group_id" id="group_id" value="">
                                        <input type="hidden" name="switch_company_id" id="switch_company_id" value="">

                                        <!-- Search input -->
                                        <div class="mb-3">
                                            <input type="search" id="tenantTableSearch" class="form-control form-custom-input" placeholder="Search tenant ID...">
                                        </div>

                                        <!-- Rendered table -->
                                        <div id="tenant-sections"></div>

                                        <div class="mt-4">
                                            <button type="submit" class="btn btn-success btn-success-small">Assign Users</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if (count($assignments) > 0)
<div class="page-content py-6 bg-[#F9FAFB]">
    <div class="w-full container">
        <div class="flex flex-wrap -mx-3">
		    <div class="w-full max-w-full px-3">
                <div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="flex flex-wrap -mx-3 items-center">
						<div class="w-full max-w-full px-3">
                            <div class="container mt-4" id="assigned-users-list">
                                <h2>Assigned Users</h2>
                                <div class="accordion mt-2" id="assignedUsersAccordion">
                                    @foreach($assignments as $groupId => $groupUsers)
                                        <div class="accordion-item mb-3">
                                            <h2 class="accordion-header" id="heading-{{ $groupId }}">
                                                <button class="accordion-button accordion-button-wrap" type="button" data-bs-toggle="collapse"
                                                    data-bs-target="#collapse-{{ $groupId }}" aria-expanded="true" aria-controls="collapse-{{ $groupId }}">
                                                    <strong>Group ID: {{ $groupId }}</strong>
                                                    <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                                </button>
                                            </h2>

                                            <div id="collapse-{{ $groupId }}"
                                                class="accordion-collapse collapse show"
                                                aria-labelledby="heading-{{ $groupId }}">

                                                <div class="accordion-body p-4 border border-[#E5E7EB] rounded-lg">
                                                    <div class="flex justify-end mb-2">
                                                        <button 
                                                            class="btn btn-sm btn-primary-small me-2 edit-group-btn" 
                                                            data-group-id="{{ $groupId }}" 
                                                            data-group-users='@json($groupUsers)'
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#editGroupModal"
                                                        >
                                                            Edit
                                                        </button>

                                                        <form 
                                                            action="{{ route(routePrefix() . '.networks.delete-assign-tenants-user') }}" 
                                                            method="POST" 
                                                            class="d-inline delete-group-form" 
                                                            data-group-id="{{ $groupId }}"
                                                        >
                                                            @csrf
                                                            <input type="hidden" name="group_id" class="delete-group-id" value="">
                                                            <button type="submit" class="btn btn-sm btn-danger-small">Delete</button>
                                                        </form>
                                                    </div>

                                                    <div class="table-responsive">
                                                        <table class="table table-bordered mb-0 table-fixed text-nowrap" style="table-layout: fixed; width: 100%;">
                                                            <thead class="thead-light">
                                                                <tr>
                                                                    <th class="th-custom-css" style="width: 30%;">Tenant ID</th>
                                                                    <th class="th-custom-css" style="width: 30%;">User Type</th>
                                                                    <th class="th-custom-css" style="width: 40%;">User Name</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @forelse($groupUsers as $user)
                                                                    <tr>
                                                                        <td class="text-truncate td-custom-css" title="{{ $user['tenant'] }}">{{ $user['tenant'] }}</td>
                                                                        <td class="text-truncate td-custom-css" title="{{ $user['user_type'] }}">{{ $user['user_type'] }}</td>
                                                                        <td class="text-truncate td-custom-css" title="{{ $user['user_name'] }}">{{ $user['user_name'] }}</td>
                                                                    </tr>
                                                                @empty
                                                                    <tr>
                                                                        <td colspan="3" class="text-center td-custom-css">No users found</td>
                                                                    </tr>
                                                                @endforelse
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                <div class="mt-3">
                                    {{ $assignments->links('pagination::bootstrap-5') }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- User‑picker modal -->
<div class="modal fade fixed top-0 left-0 z-[1060] w-full h-full overflow-x-hidden overflow-y-auto outline-0 bg-black/75 hidden show" id="userSelectModal" tabindex="-1" aria-hidden="true">
  <!-- NOTE modal-xl for extra width -->
  <div class="modal-dialog modal-fullscreen max-w-[1100px] mx-auto my-[20px] min-h-[calc(100%-40px)] w-[calc(100%-40px)] flex items-center">
    <div class="modal-content rounded-xl bg-white w-full overflow-hidden">
      <div class="modal-header px-5 py-4 bg-[#F9FAFB] flex items-center justify-between">
        <h5 class="modal-title text-lg font-semibold text-[#111827]">
          Select <span id="modalRoleLabel"></span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 4L4 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path><path d="M4 4L12 12" stroke="#020817" stroke-width="1.33333" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>
      </div>
      <!-- User list -->
      <div class="modal-body p-4">
              <!-- Search bar -->
        <div class="border-b py-2 mb-2">
            <input type="search"
                class="form-control form-custom-input"
                id="userSearchInput"
                placeholder="Search by name or email…" />
        </div>
        <ul class="list-group list-group-flush overflow-y-auto" id="modalUserList"></ul>
      </div>
    </div>
  </div>
</div>

<script>
    const tenantData = @json($tenantWithUser ?? []);
    const urlcheckUserExists = "{{ route(routePrefix() . '.networks.check-user-exists') }}"
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('js/admin/tenants-assign-user.js') }}"></script>

@endsection
