@extends('layouts.default-2')

@php
	$uTypeAdmin = session('userType') == 'Admin';
	$routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
@endphp

@section('main-content')
	<!-- <div class="row">
		<div class="col-12 col-md-12 col-lg-12">
			<div class="date-select-wrp d-flex">
				<a href="{{ route(prefixedRouteName($routePrefix.'agenda'), [ 'fromDate' => $from, 'toDate' => $to, 'tourPackageId' => $packageId, 'tourCustomerId' => $customerId ]) }}">
					<h4>Agenda</h4>
				</a>
			</div>
		</div>
	</div> -->

	<div class="agenda-section py-6 bg-white border-b border-[#E5E7EB]" style="position: relative;">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-full max-w-full px-3 mb-2">
					<a href="{{ route(prefixedRouteName($routePrefix.'agenda'), [ 'fromDate' => $from, 'toDate' => $to, 'tourPackageId' => $packageId, 'tourCustomerId' => $customerId ]) }}">
						<h1 class="font-normal text-gray-900 text-2xl mb-2">Agenda</h1>
					</a>
				</div>

				<div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2">
					<label for="agendadaterange" class="form-label form-custom-label mb-1">Packages</label>
					<select class="form-select form-custom-input whitespace-nowrap overflow-hidden" id="packages">
						<option value="0" selected>All</option>
						@foreach ($packages as $package)
							<option value="{{ $package->id }}" {{ $packageId == $package->id ? 'selected' : '' }}>{{ $package->name }}</option>
						@endforeach
					</select>
				</div>

				<div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2">
					<label for="agendadaterange" class="form-label form-custom-label mb-1">Affiliates</label>
					<select class="form-select form-custom-input whitespace-nowrap overflow-hidden" id="customers">
						@if (userType() != 'Affiliate')
						<option value="0" selected>All</option>
						@endif

						@foreach ($customers as $customer)
							<option value="{{ $customer->id }}" {{ $customerId == $customer->id ? 'selected' : '' }}>{{ $customer->name }}</option>
						@endforeach
					</select>
				</div>

				<div class="w-full sm:w-full md:w-2/3 lg:w-1/2 xl:w-2/5 max-w-full px-3 mb-2">
					<label for="agendadaterange" class="form-label form-custom-label mb-1">Date Range</label>
					<div id="agendadaterange" class="pull-right rounded form-control nat-daterange form-custom-input whitespace-nowrap overflow-hidden">
						<i class="glyphicon glyphicon-calendar fa fa-calendar mr-2"></i>
						<span></span>
						<b class="caret"></b>
					</div>
				</div>

				<div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 xl:w-1/5 max-w-full px-3 mb-2">
					<label class="form-custom-label mb-1 !block">&nbsp;</label>
					<input type="hidden" id="filter-current-route" value="{{ route(prefixedRouteName($routePrefix.'agenda'), [ 'fromDate' => $from, 'toDate' => $to, 'tourPackageId' => $packageId, 'tourCustomerId' => $customerId ]) }}" />
					<input type="hidden" id="filter-updated-route" value="{{ route(prefixedRouteName($routePrefix.'agenda'), [ 'fromDate' => 'from', 'toDate' => 'to', 'tourPackageId' => 'packageId', 'tourCustomerId' => 'customerId' ]) }}" />
					<button type="submit" class="btn btn-warning-small btn-md" id="filter-submit"><i class="fa fa-search"></i></button>
					<a href="{{ route(prefixedRouteName($routePrefix.'download-affiliate-agenda'), [ 'fromDate' => $from, 'toDate' => $to, 'tourPackageId' => $packageId, 'tourCustomerId' => $customerId ]) }}"><button type="submit" class="btn btn-info-small btn-md" id="filter-download"><i class="fa fa-download"></i></button></a>
					<button type="submit" class="btn btn-danger-small btn-md" id="filter-clear"><i class="fa fa-eraser"></i></button>
				</div>
			</div>
		</div>
	</div>

	<div class="tour-detail-sec pt-6 pb-3 max-[1024px]:pb-3 bg-[#F9FAFB]">
		<div class="w-full container">
			<div class="flex flex-wrap -mx-3">
				<div class="w-1/2 md:w-2/6 lg:w-1/4 max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#ffc107] bg-orange-50 rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Number of Orders:</div>
						<div class="font-bold text-[#264f73] text-xl text-center">{{ $total_orders }}</div>
					</div>
				</div>
				<div class="w-1/2 md:w-2/6 lg:w-1/4 max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#198754] bg-[#19875430] rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Number of Bookings:</div>
						<div class="font-bold text-[#264f73] text-xl text-center">{{ $total_bookings }}</div>
					</div>
				</div>
				<div class="w-1/2 md:w-2/6 lg:w-1/4 max-w-full mb-3 text-center px-3">
					<div class="w-full flex flex-col items-center justify-between h-full p-4 border border-solid border-[#b6effb] bg-[#0dcaf020] rounded-lg text-center">
						<div class="font-medium text-gray-600 text-sm mb-1">Number of Tickets:</div>
						<div class="font-bold text-[#264f73] text-xl text-center">{{ $total_tickets }}</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="bg-[#F9FAFB] pb-6 min-h-[calc(100vh-370px)]">
		<div class="w-full container">
			<div class="tour-detail-table-wrap max-[1350px]:p-4 px-[20px] py-[30px] border rounded-xl bg-white mb-6">
				<table class="table table-bordered table-sortable table-hover table-sm w-full" id="datatable">
					<thead>
						<tr class="align-middle">
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.id' && $request->sort_order === 'asc' ? 'asc' : 'desc' }}  {{ $request->sort_field === 'tbm.id' ? 'selected' : '' }}">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.id', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Booking #
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tp.name' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tp.name' ? 'selected' : '' }}">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tp.name', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Package
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.name' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tbm.name' ? 'selected' : '' }}">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.name', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Name
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.tourists' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tbm.tourists' ? 'selected' : '' }}">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.tourists', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Guest(s)
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tst.date' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tst.date' ? 'selected' : '' }} booking-date">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tst.date', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Booking Date
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.created_at' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tbm.created_at' ? 'selected' : '' }} created-date">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.created_at', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Created Date
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.booking_total' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tbm.booking_total' ? 'selected' : '' }} booking-amount">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.booking_total', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Booking Amount
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.total' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tbm.total' ? 'selected' : '' }} paid-amount">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.total', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Paid Amount
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable {{ $request->sort_field === 'tbm.status' && $request->sort_order === 'asc' ? 'asc' : 'desc' }} {{ $request->sort_field === 'tbm.status' ? 'selected' : '' }} status">
								<a class="anchor-tag absolute top-0 left-0 w-full h-full" href="{{ $request->fullUrlWithQuery(['sort_field' => 'tbm.status', 'sort_order' => $request->sort_order === 'asc' ? 'desc' : 'asc']) }}"></a>
								Status
							</th>
							<th class="th-custom-css relative cursor-pointer custom-table-sortable redeem">
								Redeem
							</th>
						</tr>
					</thead>
					<tbody>
						@if ($data->count())
							@foreach ($data as $item)
							<tr>
								<td class="td-custom-css">
									<div class="flex items-center">
										<a href="{{ route(prefixedRouteName($routePrefix.'booking.show'), [ 'booking' => $item->bookingid ]) }}" target="_blank">{{ $item->bookingid }}</a>
										@if($item->has_active_dispute && $item->dispute_info)
											<span class="dispute-indicator text-xs font-semibold px-2 py-1 rounded-full border ml-2 {{ $item->dispute_info['css_class'] }}"
												  title="{{ $item->dispute_info['status_label'] }} - {{ $item->dispute_info['reason_label'] }} ({{ $item->dispute_info['currency'] }} {{ $item->dispute_info['amount'] }})"
												  data-bs-toggle="tooltip"
												  data-bs-placement="top">
												<i class="fa fa-exclamation-triangle mr-1"></i>
												{{ $item->dispute_info['status_label'] }}
											</span>
										@endif
									</div>
								</td>
								<td class="td-custom-css">{{ $item->package }}</td>
								<td class="td-custom-css">{{ $item->name }}</td>
								<td class="td-custom-css">{{ $item->tourists }}</td>
								<td class="td-custom-css">{{ formatDate($item->bookingdate) }}</td>
								<td class="td-custom-css">{{ formatDate($item->created_at) }}</td>
								<td class="td-custom-css">{{ currencyFormation(false, true) . number_format($item->booking_total, 2) }}</td>
								<td class="td-custom-css">{{ currencyFormation(false, true) . number_format($item->paid_amount, 2) }}</td>
								<td class="td-custom-css">{{ $item->status }}</td>
								<td class="td-custom-css redeem-ticket-section">
									@if ($item->checkin && $item->checkin_type == 1)
										@foreach($item->package_list as $key => $ticket)
											<button class=" {{ $ticket->af_class }}">{{ $ticket->redeemed }}/{{ $ticket->total }} {{ $ticket->name }}</button>
										@endforeach
									@else
										<button class="btn btn-warning-small btn-sm">Check-in Off</button>
									@endif
								</td>
							</tr>
							@endforeach
						@else
							<tr>
								<td colspan="10" class="text-center">No data found.</td>
							</tr>
						@endif
					</tbody>
				</table>

				<div>{{ $data->appends(['sort_field' => $request->sort_field, 'sort_order' => $request->sort_order])->links('vendor.pagination.bootstrap-5') }}</div>
			</div>
		</div>
	</div>
@endsection

@section('css-custom')
<style>
	/* Dispute Indicator Styles */
	.dispute-indicator {
		display: inline-flex;
		align-items: center;
		font-weight: 600;
		font-size: 0.75rem;
		white-space: nowrap;
		transition: all 0.2s ease-in-out;
	}

	.dispute-indicator:hover {
		transform: scale(1.05);
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}

	.dispute-indicator i {
		font-size: 0.7rem;
	}

	/* Dispute Status Colors */
	.dispute-indicator.bg-red-100 {
		background-color: #fee2e2;
		color: #991b1b;
		border-color: #fecaca;
	}

	.dispute-indicator.bg-yellow-100 {
		background-color: #fef3c7;
		color: #92400e;
		border-color: #fde68a;
	}

	.dispute-indicator.bg-blue-100 {
		background-color: #dbeafe;
		color: #1e40af;
		border-color: #bfdbfe;
	}

	.dispute-indicator.bg-green-100 {
		background-color: #dcfce7;
		color: #166534;
		border-color: #bbf7d0;
	}

	.dispute-indicator.bg-gray-100 {
		background-color: #f3f4f6;
		color: #374151;
		border-color: #d1d5db;
	}

	/* Tooltip styling for dispute indicators */
	.dispute-indicator[data-bs-toggle="tooltip"] {
		cursor: help;
	}

	/* Attention-required disputes get a pulsing animation */
	.dispute-indicator.bg-red-100.requires-attention {
		animation: pulse-red 2s infinite;
	}

	@keyframes pulse-red {
		0% {
			box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
		}
		70% {
			box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
		}
		100% {
			box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
		}
	}
</style>
@endsection

@section('css-libraries')
	<link href="{{ asset('plugins/daterange/daterangepicker.2.1.25.css') }}" rel="stylesheet" type="text/css" />
	<!-- <link href="{{ asset('css/agenda.css') }}" rel="stylesheet" type="text/css"> -->
@endsection

@section('js-libraries')
	<script src="{{ asset('plugins/moment-2.29.1.min.js') }}"></script>
	<script src="{{ asset('plugins/daterange/daterangepicker.2.1.25.js')}}"></script>
	<script src="{{ asset('js/user-common/agenda/agenda.js')}}"></script>
@endsection

@section('js-custom')
<script>
	$(document).ready(() => {
		const from = "{{ $from }}";
		const to = "{{ $to }}";
		const packageId = "{{ $packageId }}";
		const customerId = "{{ $customerId }}";

		// Instantiate the Agenda class
		const dateRangePicker = new Agenda(from, to, packageId, customerId);
	});
</script>
@endsection
