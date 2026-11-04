@extends('layouts.auth')

@section('main-content')
<div class="login-main-wrp w-full">
	<div class="flex flex-wrap -mx-3 items-center">
		<div class="w-full lg:w-1/2 max-w-full px-3 order-1 lg:order-2 mb-10 lg:mb-0">
			<div class="login-img-wrp text-center lg:h-[calc(100vh-160px)] lg:min-h-[480px] flex items-center">
				<img class="overflow-hidden rounded-3xl max-h-full w-full h-full object-cover object-top" src="{{ asset('images/login-page/login-cover.jpg') }}" alt="">
			</div>
		</div>

		<div class="w-full lg:w-1/2 max-w-full px-3 order-2 lg:order-1">
			<div class="login-form-wrp forgot-password-form-wrp h-full bg-white rounded-3xl p-5 flex items-center lg:h-[calc(100vh-160px)] lg:min-h-[480px] overflow-x-auto">
				<div class="max-w-[436px] m-auto w-full">
					<div class="login-form-logo mb-6"><img class="max-w-64" src="{{ asset('images/login-page/to-login-page-logo.png') }}" alt="{{ config('app.name') }}"></div>

					<div class="login-form-name hide-after-form-submit">
						<div class="login-form-name-inner text-2xl font-semibold text-[#282C34] mb-2.5">Forgot Password?</div>
					</div>

					<div class="login-form-notes hide-after-form-submit text-base font-normal text-[#6B7280] mb-6">Please enter your registered email address.</div>

					<div class="login-form form-wrp">
						<form method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="return submitForm(this)" data-call-on-success="forgotPwdSubmitReply" class="hide-after-form-submit">
							@csrf

							<div class="field-wrap mb-4">
								<div class="form-label">
									<label class="block text-sm font-medium text-black mb-2.5" for="email"><b>Email Address <span class="required-star text-[#D63A1F]">*</span></b></label>
								</div>
								<div class="form-element">
									<input class="w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" type="email" name="email" id="email" placeholder="Enter your email address" value="{{ old('email') }}" required>
								</div>
							</div>

							<div class="form-group button-wrap">
								<div class="field-wrap">
									<button type="submit" class="btn btn-main btn-main-sm conpagesubmit btn-search w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out">Submit</button>
								</div>
							</div>
						</form>

						<div class="thank-you-msg show-after-form-submit hidden">
							<div class="thank-you-title text-sm font-normal text-[#6B7280]"><span>Thank you,</span> what’s next?</div>
							<div class="thank-you-detail text-sm font-normal text-[#6B7280]">An email will be sent to your registered email address: <a href="#" id="user-email-addr"></a>. Please access the link described in the email to complete the password reset process.</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/auth.js') }}"></script>
@endsection
