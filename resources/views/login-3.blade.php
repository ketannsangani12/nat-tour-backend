@extends('layouts.auth')
@section('main-content')

<div class="tour-operator py-14 lg:py-20 bg-[#f7f7f7] min-h-screen">
    <div class="w-full container">
        <div class="flex flex-wrap -mx-3">
            <div class="w-full max-w-full px-3">
				<div class="login-main-wrp w-full">
					<div class="flex flex-wrap -mx-3 items-center">
						<div class="w-full lg:w-1/2 max-w-full px-3 order-1 lg:order-2 mb-10 lg:mb-0">
							<div class="login-img-wrp text-center lg:h-[calc(100vh-160px)] lg:min-h-[590px] flex items-center">
								<img class="overflow-hidden rounded-3xl max-h-full w-full h-full object-cover object-top" src="{{ asset('images/login-page/login-cover.jpg') }}" alt="">
							</div>
						</div>
					    <div class="w-full lg:w-1/2 max-w-full px-3 order-2 lg:order-1">
					        <div class="login-form-wrp h-full bg-white rounded-3xl p-5 flex items-center lg:h-[calc(100vh-160px)] lg:min-h-[590px] overflow-x-auto">
								<div class="max-w-[436px] m-auto w-full">
									<div class="login-form-logo mb-6"><img class="max-w-64" src="{{ asset('images/affiliate-login-logo.png') }}" alt="login-page-logo"></div>
									<div class="login-form form-wrp">
										<div class="login-form-title text-2xl font-semibold text-[#282C34] mb-2.5">Login</div>

										@if($errors->any())
											@include('layouts._errors')
										@endif

										@if (isPermissionGranted() === false)
										<form id="loginform" method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="return validateEmailAddressFormat()">
										@endif

										@csrf

										@if (isPermissionGranted())
											<div class="mb-3">
												<div class="field-wrap">
													<div class="form-label block text-sm font-medium text-black mb-2.5">
														<label for="domain"><b>Tours Web Address</b></label>
													</div>
													<!-- Input field -->
													<div class="input-group">
														<input type="text" class="form-control form-control w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" name="domain" id="domain" placeholder="Enter your subdomain" value="{{ old('domain') }}" onkeyup="return tenantForm(this)">
														<span class="input-group-text">{{ config('constants.environment.'.config('app.env')) }}</span>
													</div>
												</div>
											</div>

											<input type="hidden" id="urlToCheckTenantExist" value="{{ route('check-tenant-exist', ':id') }}">

											<div id="div-tenant-continue" style="display: none;">
												<div class="mb-3 button-wrap">
													<div class="field-wrap">
														<button type="button" id="btn-tenant-continue" class="btn btn-main btn-main-sm conpagesubmit btn-search w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out" onclick="return tenantContinue()">Continue</button>
													</div>
												</div>
											</div>
											@endif

											@if (isPermissionGranted() === false)
												<div class="mb-3 col-12">
													<div class="field-wrap">
														<div class="form-label block text-sm font-medium text-black mb-2.5">
															<label for="domain">Email Address<span class="required-star">*</span></label>
														</div>
														<!-- Input field with Icon and Tooltip -->
														<div class="form-element email-wrp d-flex align-items-center">
															<input class="form-control w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" type="email" name="email" id="email" placeholder="Enter your email address" value="{{ old('email') }}" required>
														</div>
													</div>
												</div>
												<div class="mb-3 col-12">
													<div class="field-wrap">
														<div class="form-label text-sm font-medium text-black mb-2.5 flex items-center justify-between">
															<label for="domain">Password<span class="required-star">*</span></label>
															<div class="forgotten-password text-end">
																<a class="inline-block text-sm font-medium text-[#0060D6] hover:text-black mb-2.5 transition-all duration-300 ease-in-out" href="{{ route($forgotPasswordRoute) }}">Forgot Password?</a>
															</div>
														</div>
														<!-- Input field with Icon and Tooltip -->
														<div class="form-element password-wrp d-flex align-items-center">
															<input class="form-control w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" type="password" name="password" id="password" placeholder="Enter your Password" required>
														</div>
													</div>
												</div>

												<div id="div-tenant-continue">
													<div class="mb-3 button-wrap">
														<div class="field-wrap">
															<button type="submit" id="btn-tenant-continue" class="btn btn-main btn-main-sm conpagesubmit btn-search w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out">Log In</button>
														</div>
													</div>
												</div>
											@endif
										</form>
									</div>
								</div>
					        </div>
					    </div>
					</div>
				</div>
			</div>
        </div>
    </div>
</div>

@endsection
