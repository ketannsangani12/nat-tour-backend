@extends('layouts.auth')

@section('main-content')
<div class="tour-operator w-full">
    <div class="login-main-wrp w-full">
        <div class="flex flex-wrap -mx-3 items-center">
            <div class="w-full lg:w-1/2 max-w-full px-3 order-1 lg:order-2 mb-10 lg:mb-0">
                <div class="login-img-wrp text-center lg:h-[calc(100vh-160px)] lg:min-h-[590px] flex items-center">
                    <img class="overflow-hidden rounded-3xl max-h-full w-full h-full object-cover object-top" src="{{ asset('images/login-page/login-cover.jpg') }}" alt="">
                </div>
            </div>
            <div class="w-full lg:w-1/2 max-w-full px-3 order-2 lg:order-1">
                <div class="login-form-wrp h-full bg-white rounded-3xl p-5 flex items-center lg:h-[calc(100vh-160px)] lg:min-h-[480px] overflow-x-auto">
                    <div class="max-w-[436px] m-auto w-full">
                        <div class="login-form-logo mb-6"><img  class="max-w-64" src="{{ asset('images/login-page/to-login-page-logo.png') }}" alt="login-page-logo"></div>

                        <div class="login-form form-wrp">
                            <div class="login-form-title text-2xl font-semibold text-[#282C34] mb-2.5">Login</div>
                            @if (isPermissionGranted() === false)
                            <form id="loginform" method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="return validateEmailAddressFormat()">
                            @endif
                                @csrf

                                @if($errors->any())
                                    @include('layouts._errors')
                                @endif

                                @if (isPermissionGranted())
                                    <div class="field-wrap mb-4">
                                        <div class="form-label">
                                            <label class="block text-sm font-medium text-black mb-2.5" for="domain">Tours Web Address</label>
                                        </div>
                                        <!-- Input field with Icon and Tooltip -->
                                        <div class="form-element domain-wrp flex items-center">
                                            <input class="w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" type="text" name="domain" id="domain" placeholder="Enter your subdomain" value="{{ old('domain') }}" onkeyup="return tenantForm(this)">
                                            <span class="form-element-text ml-3" data-bs-toggle="tooltip" data-bs-placement="top" title=".nativeamericantours.com">
                                                <i class="fas fa-info-circle" style="display: inline-flex; align-items: center; line-height: inherit; font-size: 16px; color: rgb(198,78,0);"></i>
                                            </span>
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
                                <div id="login-normal">
                                    <div class="field-wrap mb-4">
                                        <div class="form-label">
                                            <label class="block text-sm font-medium text-black mb-2.5" for="email">Username <span class="required-star text-[#D63A1F]">*</span></label>
                                        </div>
                                        <div class="form-element email-wrp">
                                            <input class="w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" type="email" name="email" id="email" placeholder="Type your username" value="{{ old('email') }}" required>
                                        </div>
                                    </div>

                                    <div class="field-wrap mb-4">
                                        <div class="form-label flex items-center justify-between">
                                            <label class="block text-sm font-medium text-black mb-2.5" for="password">Password <span class="required-star text-[#D63A1F]">*</span></label>
                                            <div class="mb-2.5 forgotten-password text-end">
                                                <a class="inline-block text-sm font-medium text-[#0060D6] hover:text-black mb-2.5 transition-all duration-300 ease-in-out" href="{{ route($forgotPasswordRoute) }}">Forgot Password?</a>
                                            </div>
                                        </div>
                                        <div class="form-element password-wrp">
                                            <input class="w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" type="password" name="password" id="password" placeholder="Type your password" required>
                                        </div>
                                    </div>

                                    <div class="button-wrap">
                                        <div class="field-wrap">
                                            <button type="submit" class="btn btn-main btn-main-sm conpagesubmit btn-search w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out" id="btn-login">LogIn</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection