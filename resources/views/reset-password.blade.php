@extends('layouts.default')

@section('content')
<section class="login-form py-14 lg:py-20 bg-[#f7f7f7] min-h-screen">
	<div class="w-full container">
		<div class="flex flex-wrap -mx-3 hide-after-form-submit">
			<div class="w-full lg:w-1/2 max-w-full px-3 order-1 lg:order-2 mb-10 lg:mb-0">
				<div class="login-img-wrp text-center lg:h-[calc(100vh-160px)] lg:min-h-[590px] flex items-center">
					<img class="overflow-hidden rounded-3xl max-h-full w-full h-full object-cover object-top" src="{{ asset('images/login-page/login-cover.jpg') }}" alt="">
				</div>
			</div>
			<div class="w-full lg:w-1/2 max-w-full px-3 order-2 lg:order-1">
				<div class="login-form-wrp h-full bg-white rounded-3xl p-5 flex items-center lg:h-[calc(100vh-160px)] lg:min-h-[590px] overflow-x-auto">
					<div class="max-w-[436px] m-auto w-full">
						<div class="login-form-logo mb-6"><img class="max-w-64" src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"></div>
						<div class="login-form-name">
							<div class="login-form-name-inner text-2xl font-semibold text-[#282C34] mb-2.5">{{ $page_title }}</div>
						</div>
						<div class="login-form-note text-base font-normal text-[#6B7280] mb-6">Enter new password to set.</div>

						<form method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="return submitForm(this)" data-call-on-success="resetPwdSubmitReply">
							@csrf

							<input type="hidden" name="token" value="{{ request('token') }}">

							@if(request('id')) {{-- customer reset password --}}
								<input type="hidden" name="id" value="{{ request('id') }}">
							@else
								<input type="hidden" name="email" value="{{ request('email') }}">
							@endif

							@if($errors->any())
								@include('layouts._errors')
							@endif

							<div class="mb-3">
								<label for="password" class="form-label block text-sm font-medium text-black mb-2.5"><b>New Password <req>*</req></b></label>
								<input type="password" id="password" name="password" class="form-control w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" placeholder="Enter new password" required>
							</div>

							<div class="mb-3">
								<label for="password_confirmation" class="form-label block text-sm font-medium text-black mb-2.5"><b>Confirm New Password <req>*</req></b></label>
								<input type="password" id="password_confirmation" name="password_confirmation" class="form-control w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" placeholder="Re-enter new password" required>
							</div>

							<div class="text-center">
								<button type="submit" class="btn btn-main btn-main-sm w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out">Reset Password</button>
							</div>
						</form>
					</div>
				</div>

				<div class="mt-3 text-center">
					<a class="inline-block text-sm font-medium text-[#0060D6] hover:text-black mb-2.5 transition-all duration-300 ease-in-out" href="{{ route($loginRoute) }}">Login</a>
				</div>
			</div> {{-- /.col --}}
		</div>
		<div class="flex flex-wrap -mx-3">
			<div class="w-full max-w-full px-3 show-after-form-submit hidden">
				<p class="text-base font-normal text-[#6B7280] mb-3">Password changed!</p>
				<p class="text-base font-normal text-[#6B7280]"><a class="inline-block text-sm font-medium text-[#0060D6] hover:text-black mb-2.5 transition-all duration-300 ease-in-out" href="{{ route($loginRoute) }}">Click here</a> to go to the login page.</p>
			</div>
		</div> {{-- /.row --}}
	</div> {{-- /.container --}}
</section>
@endsection

@section('scripts')
<script src="{{ asset('js/user-common/auth.js') }}"></script>
@endsection
