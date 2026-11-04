@extends('layouts.default-2')

@section('main-content')

@php
$routePrefix = routePrefix();
@endphp

<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
                    <div class="content-title">
                        <h2 class="section-title">Tenant Users</h2>
                    </div>

                    @if (isCentralDomain())
                    <div class="sub-nav flex items-center justify-end mb-8">
                        <a class="mr-2 btn-warning-small" href="{{ route("$routePrefix.release.index") }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
                        <a class="btn-outline-primary-small" href="{{ route("$routePrefix.release.view", $release->id) }}"><i class="fas fa-eye sub-nav-icon mr-2"></i> View Release</a>
                    </div>
                    @endif

                    <ul class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-y-auto" id="tenantTabs" role="tablist">
                        @foreach ($users as $index => $user)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link tabs-custom-link {{ $index === 0 ? 'active' : '' }}"
                                id="tenant-tab-{{ $index }}"
                                data-bs-toggle="tab"
                                data-bs-target="#tenant-{{ $index }}"
                                type="button" role="tab"
                                aria-controls="tenant-{{ $index }}"
                                aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                {{ $user['tenant']->name }}
                            </button>
                        </li>
                        @endforeach
                    </ul>

                    <div class="tab-content" id="tenantTabsContent">
                        @foreach ($users as $index => $user)
                        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="tenant-{{ $index }}" role="tabpanel">
                            <div class="p-5 border border-[#E5E7EB] rounded-b-md">
                                <div class="accordion" id="accordion-{{ $index }}">
                                    <div class="flex justify-between items-center mb-3 notify-panel" data-tenant-id="{{ $user['tenant']->id }}">
                                        <div class="fs-6">
                                            Total Users Selected:
                                            <span class="badge bg-secondary total-selected-count" data-tenant-id="{{ $user['tenant']->id }}">0</span>
                                        </div>
                                        <button type="button" class="btn btn-primary send-notification-btn btn-info-small" data-tenant-id="{{ $user['tenant']->id }}">
                                            Send Notification
                                        </button>
                                    </div>

                                    {{-- Admins Accordion --}}
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header" id="heading-admin-{{ $index }}">
                                            <button class="accordion-button accordion-button-wrap" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapse-admin-{{ $index }}" aria-expanded="true"
                                                aria-controls="collapse-admin-{{ $index }}">
                                                <strong>Admins ({{ $user['admins']->count() }})</strong>
                                                <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </button>
                                        </h2>
                                        <div id="collapse-admin-{{ $index }}" class="accordion-collapse collapse show"
                                            aria-labelledby="heading-admin-{{ $index }}" data-bs-parent="#accordion-{{ $index }}">
                                            <div class="accordion-body p-4 border border-[#E5E7EB] rounded-lg">
                                                <div class="table-responsive w-full overflow-x-auto">
                                                    <table class="table table-striped table-hover table-bordered align-middle mb-0 w-full border-collapse">
                                                        <thead class="table-primary">
                                                            <tr>
                                                                <th class="th-custom-css text-center align-middle" style="width:40px;">
                                                                    <input type="checkbox" class="h-4 w-4 form-check-input select-all" data-group="admin-{{ $index }}">
                                                                </th>
                                                                <th class="th-custom-css">ID</th>
                                                                <th class="th-custom-css">Name</th>
                                                                <th class="th-custom-css">Email</th>
                                                                <th class="th-custom-css">Status</th>
                                                                <th class="th-custom-css">Created At</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if ($user['admins']->count() > 0)
                                                            @foreach ($user['admins'] as $admin)
                                                            <tr>
                                                                <td class="td-custom-css text-center align-middle">
                                                                    <input type="checkbox" class="h-4 w-4 form-check-input child-checkbox admin-{{ $index }}" data-user-type="admin" data-group="admin-{{ $index }}" data-tenant-id="{{ $user['tenant']->id }}" value="{{ $admin->id }}">
                                                                </td>
                                                                <td class="td-custom-css">{{ $admin->id }}</td>
                                                                <td class="td-custom-css">{{ $admin->name }}</td>
                                                                <td class="td-custom-css">{{ $admin->email }}</td>
                                                                <td class="td-custom-css">{{ $admin->status }}</td>
                                                                <td class="td-custom-css">{{ $admin->created_at }}</td>
                                                            </tr>
                                                            @endforeach
                                                            @else
                                                            <tr>
                                                                <td colspan="6" class="td-custom-css text-center">No Admins Found</td>
                                                            </tr>
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Staff Users Accordion --}}
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header" id="heading-staff-{{ $index }}">
                                            <button class="accordion-button accordion-button-wrap collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapse-staff-{{ $index }}" aria-expanded="true"
                                                aria-controls="collapse-staff-{{ $index }}">
                                                <strong>Staff Users ({{ $user['staffUsers']->count() }})</strong>
                                                <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </button>
                                        </h2>
                                        <div id="collapse-staff-{{ $index }}" class="accordion-collapse collapse show"
                                            aria-labelledby="heading-staff-{{ $index }}" data-bs-parent="#accordion-{{ $index }}">
                                            <div class="accordion-body p-4 border border-[#E5E7EB] rounded-lg">
                                                <div class="table-responsive w-full overflow-x-auto">
                                                    <table class="table table-striped table-hover table-bordered align-middle mb-0 w-full border-collapse">
                                                        <thead class="table-primary">
                                                            <tr>
                                                                <th class="th-custom-css text-center align-middle" style="width:40px;">
                                                                    <input type="checkbox" class="h-4 w-4 form-check-input select-all" data-group="staff-{{ $index }}">
                                                                </th>
                                                                <th class="th-custom-css">ID</th>
                                                                <th class="th-custom-css">Full Name</th>
                                                                <th class="th-custom-css">Email</th>
                                                                <th class="th-custom-css">Phone</th>
                                                                <th class="th-custom-css">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if ($user['staffUsers']->count() > 0)
                                                            @foreach ($user['staffUsers'] as $staff)
                                                            <tr>
                                                                <td class="td-custom-css text-center align-middle">
                                                                    <input type="checkbox" class="h-4 w-4 form-check-input child-checkbox staff-{{ $index }}" data-user-type="staff" data-group="staff-{{ $index }}" data-tenant-id="{{ $user['tenant']->id }}" value="{{ $staff->id }}">
                                                                </td>
                                                                <td class="td-custom-css">{{ $staff->id }}</td>
                                                                <td class="td-custom-css">{{ $staff->first_name }} {{ $staff->last_name }}</td>
                                                                <td class="td-custom-css">{{ $staff->email }}</td>
                                                                <td class="td-custom-css">{{ $staff->phone_number }}</td>
                                                                <td class="td-custom-css">{{ $staff->status }}</td>
                                                            </tr>
                                                            @endforeach
                                                            @else
                                                            <tr>
                                                                <td colspan="6" class="td-custom-css text-center">No Staff Users Found</td>
                                                            </tr>
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Affiliates Accordion --}}
                                    <div class="accordion-item mb-3">
                                        <h2 class="accordion-header" id="heading-affiliate-{{ $index }}">
                                            <button class="accordion-button accordion-button-wrap collapsed" type="button" data-bs-toggle="collapse"
                                                data-bs-target="#collapse-affiliate-{{ $index }}" aria-expanded="true"
                                                aria-controls="collapse-affiliate-{{ $index }}">
                                                <strong>Affiliates ({{ $user['affiliates']->count() }})</strong>
                                                <svg width="9" height="5" viewBox="0 0 9 5" fill="none" xmlns="http://www.w3.org/2000/svg" class="inline-block align-text-top ml-auto"><path d="M0.861572 0.732422L4.40455 4.2754L7.94752 0.732422" stroke="#2C2421" stroke-width="1.18083" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                            </button>
                                        </h2>
                                        <div id="collapse-affiliate-{{ $index }}" class="accordion-collapse collapse show"
                                            aria-labelledby="heading-affiliate-{{ $index }}" data-bs-parent="#accordion-{{ $index }}">
                                            <div class="accordion-body p-4 border border-[#E5E7EB] rounded-lg">
                                                <div class="table-responsive w-full overflow-x-auto">
                                                    <table class="table table-striped table-hover table-bordered align-middle mb-0 w-full border-collapse">
                                                        <thead class="table-primary">
                                                            <tr>
                                                                <th class="th-custom-css text-center align-middle" style="width:40px;">
                                                                    <input type="checkbox" class="h-4 w-4 form-check-input select-all" data-group="affiliate-{{ $index }}">
                                                                </th>
                                                                <th class="th-custom-css">ID</th>
                                                                <th class="th-custom-css">Name</th>
                                                                <th class="th-custom-css">Email</th>
                                                                <th class="th-custom-css">Phone</th>
                                                                <th class="th-custom-css">Status</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @if ($user['affiliates']->count() > 0)
                                                            @foreach ($user['affiliates'] as $affiliate)
                                                            <tr>
                                                                <td class="td-custom-css text-center align-middle">
                                                                    <input type="checkbox" class="h-4 w-4 form-check-input child-checkbox affiliate-{{ $index }}" data-user-type="affiliate" data-group="affiliate-{{ $index }}" data-tenant-id="{{ $user['tenant']->id }}" value="{{ $affiliate->id }}">
                                                                </td>
                                                                <td class="td-custom-css">{{ $affiliate->id }}</td>
                                                                <td class="td-custom-css">{{ $affiliate->name }}</td>
                                                                <td class="td-custom-css">{{ $affiliate->email }}</td>
                                                                <td class="td-custom-css">{{ $affiliate->phone_number }}</td>
                                                                <td class="td-custom-css">{{ $affiliate->status }}</td>
                                                            </tr>
                                                            @endforeach
                                                            @else
                                                            <tr>
                                                                <td colspan="6" class="td-custom-css text-center">No Affiliates Found</td>
                                                            </tr>
                                                            @endif
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('js-custom')
<script>
    var sendNotificationUrl = "{{ route(routePrefix() . '.release.send-notifications', $release->id) }}";
</script>
@endsection

@section('js-libraries')
<script src="{{ asset('js/admin/release-users.js') }}"></script>
@endsection