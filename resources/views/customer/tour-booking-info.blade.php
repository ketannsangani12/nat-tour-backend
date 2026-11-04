@extends('layouts.defaulmailLink')

@section('content')
<div class="card">
	<div class="card-body">
		@if($errors->any())
		@include('layouts._errors')
		@endif

		@if(session('message'))
		@include('layouts._message')
		@endif

		<div class="text-end">
			<button type="button" class="btn btn-primary" onclick="print()"><i class="fa fa-print"></i> Print</button>
			@if (isset($cancelBooking) && $bookingM->status == 'Cancelled')
				<span class="cancelled">Cancelled</span>
			@endif
		</div>

		<div class="print-area">
			@include('users.common.tour-booking-info-print-2')
		</div>
		@if(isset($cancelPreventNotification))
		<input type="hidden" id="cancel-prevent-notification" value="{{ $cancelPreventNotification }}">
		@endif
		@if (isset($cancelBooking) && isset($cancellationPolicyApply) && !empty($cancellationPolicyApply))

		<input type="hidden" id="refund-type" value="{{ $cancellationPolicyApply['refund_basis'] ?? ''  }}">
		<input type="hidden" id="refund-percent" value="{{ $cancellationPolicyApply['refund_percent'] ?? ''  }}">
		<input type="hidden" id="cancel-booking-url" value="{{ route('customer.cancel-booking', [ 'bookingId' => $bookingId ]) }}">
		@endif
		<input type="hidden" id="is-cancelled" value="{{ $bookingM->status  }}">
		<div class="separator"></div>

		{{-- need to check login status as this page can be opened without login, and message/request to cancel should not be sent without login --}}
		@if(session('userType') == 'Customer' && $bookingM->status == 'Booked')
		<div class="separator"></div>
		<h3>Request Cancellation / Refund</h3>

		<form method="post" action="{{ route('customer.tour-booking-cancel', [ 'id' => $bookingM->id ]) }}" onsubmit="return submitForm(this)" data-on-success-msg="Message for requesting cancellation / refund is sent to the tour operator">
			@csrf

			<div class="mb-3">
				<label for="message" class="form-label">Message for Tour Operator</label>
				<textarea name="message" id="message" class="form-control" required></textarea>
			</div>

			<div><button class="btn btn-warning">Submit</button></div>
		</form>
		@endif
	</div>{{-- /.card-body --}}
</div>{{-- /.card --}}
@endsection

@section('styles')
<link href="{{ asset('css/cust-booking-info.css') }}" rel="stylesheet">
@endsection

@section('scripts')
@if (isset($cancelBooking))
<script src="{{ asset('js/customer/cust-booking-info.js') }}"></script>
@endif
@endsection
