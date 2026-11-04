@extends('layouts.default-2')

@section('main-content')
	@php
		if(strpos(Route::currentRouteName(), 'tour-operator.affiliate.create') !== false)
			$action = 'Add';
		else
			$action = 'Update';
	@endphp

<div class="page-content min-h-[calc(100vh-82px)]">

	<div class="content-title">
		<h2><i class="far fa-handshake title-icon"></i> {{ $action }} Affiliate</h2>
	</div>

	<div class="sub-nav">
		<a href="{{ route(prefixedRouteName('tour-operator.affiliate.create')) }}" {!! $action == 'Add' ? 'class="active"' : '' !!}><i class="fas fa-plus sub-nav-icon"></i> Add New</a>
		<span class="sub-nav-sep">|</span>
		<a href="{{ route(prefixedRouteName('tour-operator.affiliate.index')) }}"><i class="fas fa-bars sub-nav-icon"></i> View List</a>
	</div>

	<div class="main-content">
		<form method="post" action="{{ $formSubmitRoute }}" onsubmit="return validateAndSubmitForm(this)" data-redirect-on-success="{{ route(prefixedRouteName('tour-operator.affiliate.index')) }}" data-on-success-msg=
	@if($action == 'Add')
"Affiliate added"
	@else {{-- update --}}
"Affiliate's details updated"
	@endif >

	@if($action == 'Update')
		@method('PUT')
	@endif

			<div class="row">
	
				<div class="col-12 col-md-6 mb-3">
					<label for="name" class="form-label">{{ session('userType') == 'Admin' ? 'Affiliate ' : '' }}Name <req>*</req></label>
					<input class="form-control" name="name" id="name" maxlength="80" value="{{ $affiliate->name }}" placeholder="Enter name" onblur="validateCharacters('#name')" required>
				</div>
		
				<div class="col-12 col-md-6 mb-3">
					<label for="email" class="form-label">Email Address <req>*</req></label>
					<input type="email" name="email" id="email" class="form-control" value="{{ $affiliate->email }}" placeholder="Enter email address" required>
				</div>
		
				<div class="col-12 col-md-6 mb-3">
					<label for="phone_number" class="form-label">Phone Number</label>
					<input type="tel" name="phone_number" id="phone_number" class="form-control" value="{{ $affiliate->phone_number }}" placeholder="Enter phone number" data-allowed-characters="0-9 space - ( ) +" onblur="validateCharacters('#phone_number')">
				</div>
		
				<div class="col-12 col-md-6 mb-3">
					<label for="password" class="form-label">Password</label> @if($action == 'Add') <req>*</req> @else <small>(Enter only if need to be changed)</small> @endif
					
					<div class="input-group">
						<input type="password" class="form-control" name="password" id="password" placeholder="Enter password" @if($action == 'Add') required @endif> 
						<div class="input-group-text" style="padding:0.15rem">
							<button type="button" class="btn btn-sm toggle-password" data-toggle="#password"><i class="fa fa-fw fa-eye"></i></button>
						</div>
					</div>
				</div>
			</div>
		
			<div class="mb-3">
				<label>
					<input type="checkbox" name="is_subscribed" value="Yes" {{ $affiliate->is_subscribed == 'Yes' ? 'checked' : '' }}>
					<span><b>Is subscribed?</b></span>
				</label>
			</div>

			<div class="mb-3">
				<label for="status" class="form-label">Status</label>
				<select class="form-select" name="status" id="status">
					<option {{ $affiliate->status == 'Active' ? 'selected' : '' }}>Active</option>
					<option {{ $affiliate->status == 'Inactive' ? 'selected' : '' }}>Inactive</option>
				</select>
			</div>

			<div class="mt-4 text-end"><button class="btn btn-main">{{ $action }}</button></div>
		</form>
	</div> {{-- /.main-content --}}
</div> {{-- /.page-content --}}
@endsection

@section('js-custom')
	@if($action == 'Add' && session('userType') == 'Admin')
<x-admin-js />
	@endif

<script src="{{ asset('js/user-common/affiliate-add-edit.js') }}"></script>
@endsection
