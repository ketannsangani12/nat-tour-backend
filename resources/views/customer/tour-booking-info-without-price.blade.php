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
			@include('users.common.tour-booking-info-print-without-price')
		</div>
		
	</div>{{-- /.card-body --}}
</div>{{-- /.card --}}
@endsection

@section('styles')
<link href="{{ asset('css/cust-booking-info.css') }}" rel="stylesheet">
@endsection


