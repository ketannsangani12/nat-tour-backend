@extends('layouts.auth')

@section('main-content')
<div class="login-main-wrp">
	<div class="row align-items-center">
		<div class="col-12 col-md-6 col-lg-7 order-1 order-md-2">
			<div class="login-img-wrp">
				<img src="{{ asset('images/login-page/login-image.png') }}" alt="">
			</div>
		</div>

		<div class="col-12 col-md-6 col-lg-5 order-2 order-md-1">
			<div class="login-form-wrp forgot-password-form-wrp">
				<div class="login-form-logo text-center"><img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"></div>

				<div class="login-form-name text-center">
					<div class="login-form-name-inner">Change Password</div>
				</div>

				<div class="form-body hide-after-form-submit">
					<div class="login-form-notest mb-3">Please change / reset your password now.</div>

					<div class="login-form form-wrp">
						<form method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="return submitForm(this)" data-call-on-success="resetPwdSubmitReply" class="hide-after-form-submit">
							<input type="hidden" name="token" value="{{ request('token') }}">
							<input type="hidden" name="email" value="{{ request('email') }}">

							<div class="form-row before-reset-pass">
								<div class="mb-3 col-12">
									<div class="field-wrap">
										<div class="form-label">
											<label for="newpassword"><b>New Password <span class="required-star">*</span></b></label>
										</div>
										<div class="form-element">
											<input type="password" id="password" name="password" placeholder="Enter new password" required>
										</div>
									</div>
								</div>
							</div>

							<div class="form-row before-reset-pass">
								<div class="mb-3 col-12">
									<div class="field-wrap">
										<div class="form-label">
											<label for="password_confirmation"><b>Confirm Password <span class="required-star">*</span></b></label>
										</div>
										<div class="form-element">
											<input type="password" id="password_confirmation" name="password_confirmation" placeholder="Confirm your password" required>
										</div>
									</div>
								</div>
							</div>
							
							<div class="form-row before-reset-pass">
								<div class="mb-3 button-wrap col-md-12 text-center">
									<div class="field-wrap">
										<button type="submit" class="btn btn-main btn-main-sm reset-password-submit btn-search">Reset Password</button>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>

				<div class="thank-you-msg show-after-form-submit hidden">
					<div class="thank-you-title"><span>Password changed!</div>
					<div class="thank-you-detail"><a href="{{ route($loginRoute) }}">Click here</a> to go to the login page.</div>
				</div>
			</div>
		</div>
	</div>
</div>
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/auth.js') }}"></script>
@endsection
