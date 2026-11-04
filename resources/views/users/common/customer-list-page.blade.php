@extends('layouts.default-2')

@php
	$routePrefix = routePrefix();
	$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
<div class="page-content py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="content-title">
						<h2 class="section-title">Customer List</h2>
					</div>

					<div class="main-content">
						<div class="flex flex-wrap -mx-3">
							<div class="w-full md:w-1/4 max-w-full px-3 mb-3">
								<label for="txt-date_from" class="form-label form-custom-label">Date <req>*</req></label>
								<input name="txt-date_from" id="txt-date_from" class="form-control form-custom-input" value="{{ date('m/d/Y') }}" placeholder="Enter tour date" readonly required>
								<input type="hidden" name="date" id="hid-date">
							</div>

							<div class="w-1/2 md:w-1/6 max-w-full px-3 mb-3">
								<label class="form-label form-custom-label w-full hidden md:block">&nbsp;</label>
								<button type="button" class="btn btn-secondary btn-primary-small" id="btnSearch" name="btnSearch">Filter</button>
							</div>

							<div class="w-1/2 md:w-7/12 max-w-full px-3 mb-3 text-end">
								<label class="form-label form-custom-label w-full hidden md:block">&nbsp;</label>
								<button type="button" class="btn btn-secondary btn-info-small" id="bulkSendEmailBtn" name="bulkSendEmail" onclick="bulkSendEmail()">Bulk Send Email</button>
							</div>
						</div>
						<div class="w-full overflow-y-auto">
							<table class="table table-bordered table-hover w-full border-collapse" id="datatable">
								<thead>
									<tr>
										<th class="th-custom-css"><input type="checkbox" id="select-all"></th>
										<th class="th-custom-css">Name</th>
										<th class="th-custom-css">Phone Number</th>
										<th class="th-custom-css">Email</th>
										<th class="th-custom-css" style="width: 300px;">Action</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div> {{-- /.main-content --}}
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
	<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/datatables-1.11.3/dataTables.bootstrap5.min.js') }}"></script>
	@else
	<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
	<script src="https://cdn.datatables.net/1.11.3/js/dataTables.bootstrap5.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
	@endif
	<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/customer-list.js') }}"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
<script>
	var customerListIndexAhr = "{{ route($routePrefix . '.customer-list.index-ahr') }}";
	var searchUrl = "{{ route($routePrefix . '.customer-list.searchdate', ['fromdate' => '_FROM_DATE_'])}}";
	var sendEmailToCustomerUrl = "{{ route($routePrefix . '.customer-list.send-email-to-customer') }}";
</script>
@endsection
