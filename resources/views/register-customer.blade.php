@php
    use App\Models\Settings;
    use App\Models\City;

    $settings = Settings::all();
    $cities = City::where('status',1)->orderby('name')->get();
@endphp
@extends('frontend.layouts.default')

@section('content')

<body class="register-page bg-dots">
	@include('frontend.layouts._header')

	<section class="banner-wrap">
		<div class="container">
			<div class="row">
				<div class="col-12 text-center">
					<div class="banner-title">Register <span>As A Member</span></div>
					<div class="banner-sub-title">Please fill out the below form to register.</div>
				</div>
			</div>
		</div>
	</section>

	<section class="search-form">
		<div class="container">
			<div class="row">
				<div class="col-12">
					<div class="form-wrap light-bg" id="get-a-quote">
						<form id="conpageform" method="POST" action="{{route('register')}}">
	@if($errors->any())
							<div class="alert-close">
								<div class="alert alert-danger">
									<button type="button" class="close" data-dismiss="alert" aria-label="Close">
										<span aria-hidden="true"><i class="ki ki-close"></i></span>
									</button>
									@foreach($errors->all() as $error)
									<li>{{ $error }}</li>
									@endforeach
								</div>
							</div>
	@endif
							@csrf
							
	@if(!empty($refer_userid))
							<input type="hidden" name="refer_userid" value="{{$refer_userid}}">
	@endif 
							<div class="registerbox">
								<div class="form-row">
									<div class="form-group col-md-6">
										<div class="field-wrap">
											<div class="form-label">
												<label for="name">Your Name<span>*</span></label>
											</div>
											<span class="error-text" id="error_name"></span>
											<input id="asamember" type="hidden" name="heading-checkbox" value="member">
											<div class="form-element">
												<input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Your Name">
											</div>
										</div>
									</div>

									<div class="form-group col-md-6">
										<div class="field-wrap">
											<div class="form-label">
												<label for="email">Email Address<span>*</span></label>
											</div>
											<span class="error-text" id="error_email"></span>
											<div class="form-element">
												<input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Your Email Address">
											</div>
										</div>
									</div>
								</div>

								<div class="form-row">
									<div class="form-group col-md-6">
										<div class="field-wrap">
										<div class="field-wrap">
											<div class="form-label">
												<label for="password">Password<span>*</span></label>
											</div>
											<span class="error-text" id="error_password"></span>
											<div class="form-element">
												<input type="password" id="password" name="password" placeholder="Type Here ...">
											</div>
										</div>
										</div>
									</div>

									<div class="form-group col-md-6">
										<div class="field-wrap">
											<div class="form-label">
												<label for="password_confirmation">Confirm Password<span>*</span></label>
											</div>
											<span class="error-text" id="error_password_confirmation"></span>
											<span class="error-text" id="newpassword_error_"> </span>
											<div class="form-element">
												<input type="password" id="password_confirmation" name="password_confirmation" placeholder="Type Here ...">
											</div>
										</div>
									</div>
								</div>

								<div class="form-row">
									<div class="form-group button-wrap col-md-12 text-center">
										<div class="field-wrap">
											<button type="submit" class="btn btn-outline-primary btn-submit-bg-black">Register Now <span><img src="./images/btn-arrow.png" alt="arrow"></span></button>
										</div>
									</div>
								</div>
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>
	</section>
</body>
@include('frontend.layouts._footer')

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

<script>
$(document).ready(function(){
	var email_state = false;
	var username_state = false;
	var _token = $('input[name="_token"]').val();

	$('#email').on('blur', function(){
		var email = $('#email').val();
		if (email == '') {
		  	email_state = false;
		  	return;
		}
		$.ajax({
			url: "{{route('get_user_email')}}",
			type: 'POST',
			data: {
				'username_check' : 1,
				'email' : email,
				_token: _token,
			},
			success: function(response){
				if(response == 1){
					$('#error_email').text('Email is already exist');
				}else{
					
					$('#error_email').text('');
				}	
			}
		});
	});

	$("button").click(function(){
		let name = $('#name').val();
		let email = $('#email').val();
		let password = $('#password').val();
		let password_confirmation = $('#password_confirmation').val();
		let count = $('#password_confirmation').val().length;
		// alert(count);
		
		if(name == "" || name == null){
			$('#error_name').text('Name is required');
			return false;
		}
				
		if(email == "" || email == null){
			$('#error_email').text('Email is required');
			return false;
		}

		if(password == "" || password == null){
			$('#error_password').text('Password is required');
			return false;
		}
		if(password !== password_confirmation){
			$('#error_password_confirmation').text('Confirm password is not same');
			return false;
		}else{
			$('#error_password_confirmation').text('');
		}
		if(count < 8){
			$('#newpassword_error_').text('8 min. characters are required');
			return false;
		}else{
			$('#newpassword_error_').text('');
		}
		
		return true;
	});
}); 
</script>
@endsection