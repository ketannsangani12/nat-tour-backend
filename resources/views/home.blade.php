@extends('layouts.auth')

@section('main-content')
<div class="login-main-wrp w-full">
	<div class="flex flex-wrap -mx-3">
		<div class="w-full lg:w-1/2 max-w-full px-3 order-1 lg:order-2 mb-10 lg:mb-0">
			<div class="login-img-wrp text-center lg:h-[calc(100vh-160px)] lg:min-h-[480px] flex items-center">
				<img class="overflow-hidden rounded-3xl w-full h-full object-cover object-top" src="{{ asset('images/login-page/login-cover.jpg') }}" alt="">
			</div>
		</div>

		<div class="w-full lg:w-1/2 max-w-full px-3 order-2 lg:order-1">
			<div class="login-form-wrp forgot-password-form-wrp h-full bg-white rounded-3xl p-5 flex items-center lg:h-[calc(100vh-160px)] lg:min-h-[480px] overflow-x-auto">
				<div class="login-form-logo mb-6"><img src="{{ asset('images/login-page/to-login-page-logo.png') }}" alt="{{ config('app.name') }}"></div>
				<div class="login-form-title text-2xl font-semibold text-[#282C34] mb-2.5">Welcome to {{ config('app.name') }}</div>
				<div class="login-form-notes text-base font-normal text-[#6B7280] mb-6">Please log in to continue.</div>
				<!-- <div class="login-form-name text-center">
					<div class="login-form-name-inner"><img src="{{ asset('images/login-page/login-icon.png') }}" alt=""> Login</div>
				</div> -->

				<div class="login-form form-wrp">
					@if($errors->any())
						@include('layouts._errors')
					@endif

					<form id="loginform" method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="return validateEmailAddressFormat()">
						@csrf
						
						<div class="field-wrap">
							<div class="form-label">
								<label class="text-sm font-medium text-black mb-2" for="email">Email Address <span class="required-star text-[#D63A1F]">*</span></label>
							</div>
							<div class="form-element">
								<input type="email" name="email" id="email" placeholder="Enter your email address" value="{{ old('email') }}" required>
							</div>
						</div>

						<div class="field-wrap">
							<div class="form-label flex items-center justify-between">
								<label class="text-sm font-medium text-black mb-2" for="password">Password <span class="required-star text-[#D63A1F]">*</span></label>
								<div class="mb-2 forgotten-password text-end">
									<a class="inline-block text-sm font-medium text-[#0060D6] hover:text-black mb-2.5 transition-all duration-300 ease-in-out" href="{{ route($forgotPasswordRoute) }}">Forgot Password?</a>
								</div>
							</div>
							<div class="form-element">
								<input type="password" name="password" id="password" placeholder="Enter your password" required>
							</div>
						</div>

						<div class="mb-4 checkbox-wrap">
							<label for="remember" class="checked-item text-sm font-medium text-[#6B7280] mb-0 flex items-center">
								<input class="w-4 h-4 border-[#E5E7EB] mr-2" name="remember" id="remember" type="checkbox" value="1" @if(old('remember')) checked @endif >
								<span>Remember me</span>
								<span class="checkmark ms-0"></span>
							</label>
						</div>

						<div class="mb-3 button-wrap">
							<div class="field-wrap">
								<button type="submit" class="btn btn-main btn-main-sm conpagesubmit btn-search w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out">Log In</button>
							</div>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection
