@extends('layouts.default-2')
@php
	$routePrefix = session('userType') == 'Affiliate' ? '' : 'tour-operator.';
@endphp
@section('main-content')
<section class="select-edit-booking py-6 bg-[#F9FAFB]">
	<div class="w-full container">
		<div class="w-full bg-white p-6 max-[1350px]:p-4 border border-[#E5E7EB] rounded-lg overflow-y-auto">
			<div class="flex flex-wrap -mx-3 mb-3">
				<div class="w-full md:w-1/2 max-w-full px-3 user-details mb-3">
					<h3 class="section-title"><span>{{ $booking->customer }}</span></h3>
					<a href="tel:{{ $booking->cust_phone }}" class="contactnum text-xs font-medium text-[#2563EB] hover:text-[#000000]">{{ $booking->cust_phone }}</a><br />
					<span class="text-sm text-[#4B5563] mt-1 contactemail">{{ $booking->cust_email }}</span>
				</div>

				<div class="w-full md:w-1/2 max-w-full px-3 booking-details mb-3">
					<h3 class="section-title">Booking ID #{{ $booking->id }}</h3>
					<p class="text-sm text-[#4B5563] mt-1">Booked on {{ formatDateTime($booking->created_at) }}</p>

					<div class="text-sm font-medium text-[#020817] mt-1"><b>
						@if(session('userType') == 'Admin')
							<span>{{ $booking->tour_operator }}</span> &bull;
						@endif
						<span>{{ $booking->tour_package }}</span> &bull;
						<span>{{ formatDateTime($booking->date . ' ' . $booking->time_from) }}</span>
					</b></div>
				</div>
			</div>
			<div class="flex flex-wrap -mx-3">
				<div class="w-full max-w-full px-3">
					<form id="form-email" method="post" action="{{ route(prefixedRouteName($routePrefix.'booking.send-email'), [ 'bookingId' => $booking->id ]) }}" onsubmit="return validateAndSubmitForm(this)" data-on-success-msg="Email has been sent"  data-redirect-on-success="{{ route(prefixedRouteName($routePrefix.'booking.show'), [ 'booking' => $booking->id ]) }}">
						@csrf
						<div class="mb-2"><label class="form-custom-label" for="email_subject">Email Subject <req>*</req></label></div>
						<div class="mb-3"><input name="email_subject" id="email_subject" class="form-control form-custom-input" required></div>
						<div class="mb-2 form-custom-label">Email Content</div>
						<textarea class="form-control form-custom-input" name="email_content" id="email_content"></textarea>
						<div class="mt-3"><button class="btn btn-main btn-primary-small"><i class="fa fa-paper-plane-o mr-2"></i> Send</button></div>
					</form>
				</div>
			</div>
		</div>
	</div>
</section>
@endsection

@section('js-libraries')
<script src="{{ asset('plugins/ckeditor-4.17.1/ckeditor.js') }}"></script>
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/tour-booking-send-email.js') }}"></script>
@endsection
