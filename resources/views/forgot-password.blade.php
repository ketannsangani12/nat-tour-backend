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
				<div class="login-form-wrp forgot-password-form-wrp h-full bg-white rounded-3xl p-5 flex items-center lg:h-[calc(100vh-160px)] lg:min-h-[590px] overflow-x-auto">
					<div class="max-w-[436px] m-auto w-full">
						<div class="login-form-logo mb-6"><img class="max-w-64" src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}"></div>
						<div class="login-form-name">
							<div class="login-form-name-inner text-2xl font-semibold text-[#282C34] mb-6">{{ $page_title }}</div>
						</div>
						<form method="POST" action="{{ route($formSubmitRoute) }}" onsubmit="if(validateEmailAddressFormat())return submitForm(this);else return false" data-call-on-success="forgotPwdSubmitReply">
							@csrf

							@if(isset($tourOperators))
								<div class="mb-3">
									<label for="tour_operator" class="form-label block text-sm font-medium text-black mb-2.5"><b>Tour Operator</b></label>
									<select id="tour_operator" name="tour_operator" class="form-select w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none">
										@foreach($tourOperators as $to)
											<option value="{{ $to->id }}"{{ old('tour_operator') == $to->id ? ' selected' : '' }}>{{ $to->name }}</option>
										@endforeach
									</select>
								</div>
							@endif

							<p class="text-base font-normal text-[#6B7280] mb-3">Enter your registered email address to get a link by email to reset your account's password.</p>

							@if($errors->any())
								@include('layouts._errors')
							@endif

							<div class="mb-3">
								<label for="email" class="form-label block text-sm font-medium text-black mb-2.5"><b>Email Address <req>*</req></b></label>
								<input type="text" id="email" name="email" class="form-control w-full text-base font-normal text-[#6B7280] border border-[#E5E7EB] rounded-md bg-[#FAFAFA] px-5 py-2.5 outline-none shadow-none" placeholder="Enter email address" required>
							</div>

							<div class="text-center">
								<button type="submit" class="btn btn-main btn-main-sm w-full text-sm text-white font-medium shadow-sm rounded-lg px-10 py-2.5 block bg-[#F97316] border border-[#F97316] hover:border-[#F97316] hover:bg-white hover:text-[#F97316] transition-all duration-300 ease-in-out">Submit</button>
							</div>
						</form>

						<div class="mt-3 text-center">
							<a class="inline-block text-sm font-medium text-[#0060D6] hover:text-black mb-2.5 transition-all duration-300 ease-in-out" href="{{ route($loginRoute) }}">Login</a>
						</div>
					</div>
				</div>
			</div> {{-- /.col --}}

			<div class="w-full max-w-full px-3 show-after-form-submit hidden">
				<p class="text-base font-normal text-[#6B7280]">You should receive an email on the email address <a href="#" id="user-email-addr"></a>. Please open the link provided in the email to reset your account's password.</p>
			</div>
		</div> {{-- /.row --}}
	</div> {{-- /.container --}}
</section>
@endsection

@section('scripts')
<script src="{{ asset('js/user-common/auth.js') }}"></script>
@endsection
