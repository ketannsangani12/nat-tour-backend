@extends('layouts.default-2')

@section('main-content')
	@php
	$routePrefix = routePrefix();
	if(session('userType') == 'Admin') {
		$uTypeAdmin = true;
	} else {
		$uTypeAdmin = false;
	}
@endphp

<div class="page-content py-6 bg-[#F9FAFB] min-h-[calc(100vh-82px)]">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3">
				<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg mt-6 overflow-y-auto">
					<div class="flex flex-wrap -mx-3 items-center mb-8">
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="content-title text-center md:text-left">
								<h2 class="section-title !mb-0">{{ $page_title }}</h2>
							</div>
						</div>
						<div class="w-full md:w-1/2 max-w-full px-3">
							<div class="sub-nav flex items-center justify-center md:justify-end">
								<a class="btn-primary-small" href="{{ route($routePrefix . '.promotion.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
							</div>
						</div>
					</div>

					<!-- <div class="content-title">
						<h2 class="section-title">{{ $page_title }}</h2>
					</div>

					<div class="sub-nav flex items-center justify-end mb-8">
						<a class="btn-outline-primary-small" href="{{ route($routePrefix . '.promotion.index') }}"><i class="fas fa-bars sub-nav-icon mr-2"></i> View List</a>
					</div> -->

					<div class="main-content">
						<form class="form" method="post" action="{{$formSubmitRoute}}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route($routePrefix . '.promotion.index') }}" data-on-success-msg=
						"Promotion assigned">
							<input type="hidden" name="promotion_id" value="{{$promotionId}}">
							<div class="flex flex-wrap -mx-3 mb-3">
								<div class="w-full sm:w-2/3 md:w-1/3 max-w-full px-3 mb-3">
									<label for="affiliate_id" class="form-label form-custom-label">Affiliate <req>*</req></label>
									<select class="form-control form-custom-input" name="affiliate_id" id="affiliate_id" required>
										<option selected disabled>Please select affiliate</option>
										@foreach($affiliates as $affiliate)
										<option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
										@endforeach
									</select>
								</div>
								<div class="w-full sm:w-1/3 md:w-2/3 max-w-full px-3 mb-3 text-end">
									<button class="btn btn-md btn-primary btn-warning-small">Add</button>
								</div>
							</div>
						</form>
						<div class="w-full overflow-y-auto">
							<table class="table table-bordered table-hover mt-3 mb-0 w-full border-collapse tbl-price">
								<thead>
									<tr>
										<th class="th-custom-css" width="100">Affiliate ID</th>
										<th class="th-custom-css">Affiliate Name</th>
										<th class="th-custom-css" width="100">Action</th>
									</tr>
								</thead>
								<tbody style="vertical-align: middle;">
									@if ($assignedAffiliates->count())
										@foreach ($assignedAffiliates as $assignedAffiliate)
											<tr>
												<td class="td-custom-css" width="100">{{ $assignedAffiliate->affiliate->id }}</td>
												<td class="td-custom-css">{{ $assignedAffiliate->affiliate->name }}</td>
												<td class="td-custom-css" width="100">
													<button data-url="{{ route($routePrefix . '.promotion-unassign-affiliate', [ 'id' => $assignedAffiliate->id ]) }}" data-method="delete" class="btn btn-sm btn-delete btn-danger-small !flex items-center">
														<i class="far fa-trash-alt mr-2"></i>
														Delete
													</button>
												</td>
											</tr>
										@endforeach
									@else
										<tr>
											<td class="td-custom-css" colspan="6" align="center">
												No Affiliate Assigned.
											</td>
										</tr>
									@endif
								</tbody>
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
		<link href="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.css') }}" rel="stylesheet">
	@else
		<link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
	@endif
@endsection

@section('js-libraries')
	@if(App::environment('local'))
		<script src="{{ asset('plugins/jquery-ui-1.12.1/jquery-ui.min.js') }}"></script>
	@else
		<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
	@endif
@endsection

@section('js-custom')
	<script src="{{ asset('js/delete-record.js') }}"></script>
	<script src="{{ asset('js/user-common/tour-promotion-add-edit.js') }}?v=4"></script>
	
@endsection
