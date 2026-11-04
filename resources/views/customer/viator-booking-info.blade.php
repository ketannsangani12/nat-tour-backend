@extends('layouts.defaulmailLink')

@section('content')
<div class="card">
	<div class="card-body">
		@if(session('message'))
		@include('layouts._message')
		@endif

		<div class="text-end">
			@if (isset($cancelBooking) && $bookingM->status == 'Cancelled')
				<span class="cancelled">Cancelled</span>
			@endif
		</div>

		<div class="print-area">
			@include('users.common.tour-booking-info-print-2')
		</div>

		<div class="separator"></div>

	</div>{{-- /.card-body --}}
</div>{{-- /.card --}}
@endsection

@section('styles')
<link href="{{ asset('css/cust-booking-info.css') }}" rel="stylesheet">
@endsection