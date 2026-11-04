@extends('layouts.default-2')

@php
$routePrefix = routePrefix();
$uTypeAdmin = session('userType') == 'Admin';
$userRole = userRole();
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">@if($uTypeAdmin) Tour Operator @endif Staff Users</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="flex items-center justify-center md:justify-end">
							<div class="relative inline-block text-left" id="groupFilter">
								<!-- Trigger button -->
								<button
									type="button"
									id="groupFilterButton"
									class="inline-flex mr-2 items-center px-4 py-1.5 border border-[#2563EB] text-[#2563EB] bg-[#EFF6FF] rounded-full text-sm font-medium focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#2563EB]">
									<span>Role: <span id="groupFilterLabel" class="font-semibold ml-1">{{ $selectedRole ? $selectedRole->display_name : 'All' }}</span></span>
									<svg class="ml-2 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
										<path fill-rule="evenodd"
											d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z"
											clip-rule="evenodd" />
									</svg>
								</button>

								<!-- Dropdown menu -->
								<div
									id="groupFilterMenu"
									class="absolute mt-2 w-56 rounded-lg bg-white shadow-lg border border-gray-100 py-1 text-sm text-gray-800 hidden z-10">
									<button type="button"
										data-value=""
										class="block w-full text-left px-4 py-2 hover:bg-gray-100">
										All
									</button>
									@foreach($roles as $role)
									<button type="button"
										data-value="{{ $role->id }}"
										class="block w-full text-left px-4 py-2 hover:bg-gray-100">
										{{ $role->display_name }}
									</button>
									@endforeach
								</div>
							</div>
							<input type="hidden" id="role_filter_value" value="{{ $selectedRole ? $selectedRole->id : '' }}">

							@if($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
							<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . ('.staff.create')) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
							@endif
						</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">@if($uTypeAdmin) Tour Operator @endif Staff Users</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						@if($userRole == 'super-admin' || $userRole == 'admin' || $userRole == 'manager')
						<a class="mr-2 btn-warning-small" href="{{ route($routePrefix . ('.staff.create')) }}"><i class="fas fa-plus sub-nav-icon mr-2"></i> Add New</a>
						@endif
						<a class="btn-outline-primary-small active" href="{{ route($routePrefix . ('.staff.index')) }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						@if($errors->any())
						@include('layouts._errors')
						@endif

						@if(session('message'))
						@include('layouts._message')
						@endif

						<div class="row" style="margin-bottom:20px;">

						</div>
						<div class="w-full overflow-y-auto">
							<table class="table table-bordered table-hover w-full border-collapse" id="datatable">
								<thead>
									<tr>
										<th class="th-custom-css border-t-0 font-medium">First Name</th>
										<th class="th-custom-css border-t-0 font-medium">Last Name</th>
										<th class="th-custom-css border-t-0 font-medium">Email Address</th>
										<th class="th-custom-css border-t-0 font-medium">Phone Number</th>
										<th class="th-custom-css border-t-0 font-medium">Role</th>
										<th class="th-custom-css border-t-0 font-medium">Status</th>
										<th class="th-custom-css border-t-0 font-medium" style="width: 180px !important;">Action</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div> {{-- /.main-content --}}
					</div>
				</div>
			</div>
		</div>
	</div>
</div> {{-- /.page-content --}}
@endsection

@section('css-libraries')
@if(App::environment('local'))
<link href="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.css') }}" rel="stylesheet">
@else
<link href="https://cdn.datatables.net/1.11.3/css/dataTables.bootstrap5.min.css" rel="stylesheet">
@endif
@endsection

@section('js-libraries')
@if(App::environment('local'))
<script src="{{ asset('plugins/datatables-1.11.3/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
@else
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
@endif
@endsection

@section('js-custom')
<script>
	const savedFilter = "{{ $selectedRole ? $selectedRole->id :'' }}"; // Get saved filter from server-side
	$(document).ready(function() {
		// ✅ On page load, apply saved filter to hidden input + label
		if (savedFilter !== "") {
			$('#role_filter_value').val(savedFilter);

		} else {
			$('#groupFilterLabel').text('All');
		}
		dtbl = $('#datatable').DataTable({
			processing: true,
			serverSide: false,
			responsive: true,
			ajax: {
				method: 'POST',
				url: "{{ route($routePrefix . ('.staff.index-ahr')) }}",
				data: function(d) {
					d.role_filter = $('#role_filter_value').val(); // send filter to backend
					d._token = '{{ csrf_token() }}';
				}
			},
			order: [
				[0, 'asc'],
				[1, 'asc']
			],
			columns: [{
					data: 'first_name',
					name: 'tos.first_name',
					className: 'td-custom-css fw-bold'
				},
				{
					data: 'last_name',
					name: 'tos.last_name',
					className: 'td-custom-css fw-bold'
				},
				{
					data: 'email',
					name: 'tos.email',
					className: 'td-custom-css'
				},
				{
					data: 'phone_number',
					name: 'tos.phone_number',
					className: 'td-custom-css'
				},
				{
					data: 'name',
					name: 'r.name',
					className: 'td-custom-css'
				},
				{
					data: 'status',
					name: 'tos.status',
					className: 'td-custom-css text-center'
				},
				{
					data: 'action',
					className: 'td-custom-css text-center whitespace-nowrap',
					orderable: false,
					searchable: false
				},
			]
		});

		// Dropdown logic
		const wrapper = document.getElementById('groupFilter');
		const btn = document.getElementById('groupFilterButton');
		const menu = document.getElementById('groupFilterMenu');
		const label = document.getElementById('groupFilterLabel');

		// Toggle menu
		btn.addEventListener('click', function() {
			menu.classList.toggle('hidden');
		});

		// Handle dropdown option click
		menu.querySelectorAll('button').forEach(item => {
			item.addEventListener('click', function() {

				const text = this.textContent.trim();
				const value = this.dataset.value || '';

				// Update label
				label.textContent = text;

				// Store filter in hidden input
				$('#role_filter_value').val(value);

				// 🔥 Reload DataTable via Ajax
				dtbl.ajax.reload();

				// Close dropdown
				menu.classList.add('hidden');
			});
		});

		// Close when clicking outside
		document.addEventListener('click', function(e) {
			if (!wrapper.contains(e.target)) {
				menu.classList.add('hidden');
			}
		});
	});
</script>

<x-delete-record-js-file />
@endsection