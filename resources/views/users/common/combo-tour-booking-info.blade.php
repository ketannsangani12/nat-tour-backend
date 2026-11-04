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
				<button type="button" class="btn btn-primary" onclick="print()"><i class="fa fa-print"></i> Print
				</button>
			</div>

			<div class="print-area">
					@include('users.common.tour-booking-info-print-combo')
			</div>



			<div class="separator"></div>

		</div>{{-- /.card --}}
		@endsection

		@section('styles')
			<link href="{{ asset('css/cust-booking-info.css') }}" rel="stylesheet">
		@endsection

