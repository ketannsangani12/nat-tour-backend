@extends('layouts.default-2')

@section('main-content')
<section class="select-edit-booking">

	<div class="row align-items-center">

		<div class="col-md-6 booking-details">
			<div style="font-size:120%"><b>
				@if(session('userType') == 'Admin')
				<span>{{ $booking->tour_operator }}</span> &bull;
				@endif
				<span>{{ $booking->tour_package }}</span> &bull;
				<span>{{ formatDateTime($booking->date . ' ' . $booking->time_from) }}</span>
			</b></div>
		</div>
	</div>

	<hr>

	<form id="form-email" method="post" action="{{ route(('send-emailbyDatetime'), [ 'date' => $booking->date, 'time' => $booking->time_from]) }}" onsubmit="return validateAndSubmitForm(this)" data-on-success-msg="Email has been sent" style="max-width:620px;margin:auto">
		@csrf
		<div class="mb-2"><label for="email_subject">Email Subject <req>*</req></label></div>
		<div class="mb-3"><input name="email_subject" id="email_subject" class="form-control" required></div>
		<div class="mb-2">Email Content</div>
		<textarea class="form-control" name="email_content" id="email_content"></textarea>
		<div class="mt-3"><button class="btn btn-main"><i class="fa fa-paper-plane-o"></i> Send</button></div>
	</form>
</section>
@endsection

@section('js-libraries')
<script src="{{ asset('plugins/ckeditor-4.17.1/ckeditor.js') }}"></script>
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/tour-booking-send-email.js') }}"></script>
@endsection
