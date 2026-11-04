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
					<div class="banner-title">Register <span>As An Agency/Massage Parlor</span></div>
					<div class="banner-sub-title">Please fill out the below form to register with SPDIR</div>
				</div>
			</div>
		</div>
	</section>
<section class="search-form">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="form-wrap light-bg" id="get-a-quote">
					<form id="conpageform" method="POST" action="{{route('register')}}" enctype="multipart/form-data">
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
                        <input id="agency" type="hidden" name="heading-role" value="agency">
						<div class="registerbox asaagency">
							<div class="form-row">
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="agency_type">Agency Type<span>*</span></label>
										</div>
										<input id="asamember" type="hidden" name="heading-checkbox" value="agency">
										<div class="form-element">
											<span class="error-text" id="error_agency_type"></span>
											<select id="agency_type" class="custom-select form-control" name="agency_type">
												<option disabled @if(!old('agency_type')) selected @endif >Please select one</option>
												@foreach ($agency_types as $agency_type)
	                                                <option value="{{$agency_type}}" @if(old('agency_type') == $agency_type) selected @endif >{{$agency_type}}</option>
	                                            @endforeach
											</select>
										</div>
									</div>
								</div>
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="name">Agency Name<span>*</span></label>
										</div>
										<span class="error-text" id="error_name"></span>
										<div class="form-element">
											<input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Your agency name">
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
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="province">Province<span>*</span></label>
										</div>
										<span class="error-text" id="error_province"></span>
										<div class="form-element">
											<select class="form-control custom-select" id="province" name="province" onChange="getCities()">
												<option disabled selected="">Please select your province</option>
					                            @foreach ($provinces as $province)
					                            <option value="{{$province->id}}" @if(old('province') == $province->id) selected @endif >{{$province->name}}</option>
					                            @endforeach
					                        </select>
										</div>
									</div>
								</div>
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="city">City<span>*</span></label>
										</div>
										<span class="error-text" id="error_city"></span>
										<div class="form-element">
											<select id="city" class="form-control custom-select" name="city"></select>
										</div>
									</div>
								</div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="email">Email Address<span>*</span></label>
										</div>
										<span class="error-text" id="error_email"></span>
										<div class="form-element">
											<input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="Your email address">
										</div>
									</div>
								</div>
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="website">Website Address<span>*</span></label>
										</div>
										<span class="error-text" id="error_website"></span>
										<div class="form-element">
											<input type="text" id="website" name="website" value="{{ old('website') }}" placeholder="Your Website URL">
										</div>
									</div>
								</div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-12 col-lg-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="phonenumberforcalls">Phone Number (For Calls)<span>*</span></label>
										</div>
										<span class="error-text" id="error_phonenumberforcalls"></span>
										<div class="form-element">
											<input type="tel" id="phonenumberforcalls" name="phonenumberforcalls" value="{{ old('phonenumberforcalls') }}" placeholder="Your phone number (for calls)">
										</div>
									</div>
								</div>
								<div class="form-group col-md-12 col-lg-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="phonenumber">Phone Number (For Text)<span>*</span></label>
										</div>
										<span class="error-text" id="error_phonenumber"></span>
										<div class="form-element">
											<input type="tel" id="phonenumber" name="phonenumber" value="{{ old('phonenumber') }}" placeholder="Your phone number (for text)">
										</div>
									</div>
								</div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="twitter">Twitter<span>*</span></label>
										</div>
										<span class="error-text" id="error_twitter"></span>
										<div class="form-element d-flex align-items-center">
											<div class="form-element-icons-text">@</div>
											<input type="text" id="twitter" name="twitter" value="{{ old('twitter') }}" placeholder="john_30">
										</div>
									</div>
								</div>
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="instagram">Instagram<span>*</span></label>
										</div>
										<span class="error-text" id="error_instagram"></span>
										<div class="form-element d-flex align-items-center">
											<div class="form-element-icons-text">@</div>
											<input type="text" id="instagram" name="instagram" value="{{ old('instagram') }}" placeholder="john_30">
										</div>
									</div>
								</div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="facebook">Facebook<span>*</span></label>
										</div>
										<span class="error-text" id="error_facebook"></span>
										<div class="form-element d-flex align-items-center">
											<div class="form-element-icons-text">https://</div>
											<input type="text" id="facebook" name="facebook" value="{{ old('facebook') }}" placeholder="www.facebook.com/john.doe...">
										</div>
									</div>
								</div>
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="tiktok">TikTok<span>*</span></label>
										</div>
										<span class="error-text" id="error_tiktok"></span>
										<div class="form-element d-flex align-items-center">
											<div class="form-element-icons-text">@</div>
											<input type="text" id="tiktok" name="tiktok" value="{{ old('tiktok') }}" placeholder="john_30">
										</div>
									</div>
								</div>
							</div>
							<div class="form-row">
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="onlyfans">OnlyFans<span>*</span></label>
										</div>
										<span class="error-text" id="error_onlyfans"></span>
										<div class="form-element d-flex align-items-center">
											<div class="form-element-icons-text">@</div>
											<input type="text" id="onlyfans" name="onlyfans" value="{{ old('onlyfans') }}" placeholder="john_30">
										</div>
									</div>
								</div>
								<div class="form-group col-md-6">
									<div class="field-wrap">
										<div class="form-label">
											<label for="snapchat">SnapChat<span>*</span></label>
										</div>
										<span class="error-text" id="error_snapchat"></span>
										<div class="form-element d-flex align-items-center">
											<div class="form-element-icons-text">@</div>
											<input type="text" id="snapchat" name="snapchat" value="{{ old('snapchat') }}" placeholder="john_30">
										</div>
									</div>
								</div>
							</div>

							<div class="form-row">
								<div class="form-group button-wrap col-md-12 text-center">
									<div class="field-wrap">
										<button type="submit" class="btn btn-outline-primary btn-submit-bg-black">Register Now <span><img src="{{asset('media/home/btn-arrow.png')}}" alt="arrow"></span></button>
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

	$('#name').on('blur', function(){
		var name = $('#name').val();
		if (name == '') {
		  	username_state = false;
		  	return;
		}
		$.ajax({
			url: "{{route('get_user_name')}}",
			type: 'POST',
			data: {
				'username_check' : 1,
				'name' : name,
				_token: _token,
			},
			success: function(response){
				if(response == 1){
					$('#error_name').text('Name is already exist');
				}else{
					
					$('#error_name').text('');
				}	
			}
		});
	});
	
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
		let agency_type = $('#agency_type').val();
		let name = $('#name').val();
		let email = $('#email').val();
		let password = $('#password').val();
		let password_confirmation = $('#password_confirmation').val();
		let count = $('#password_confirmation').val().length;
		let province = $('#province').val();
		let city = $('#city').val();
		let phonenumberforcalls = $('#phonenumberforcalls').val();
		let phonenumber = $('#phonenumber').val();
		let website = $('#website').val();
		let online_service = $('#online_service').val();
		let twitter = $('#twitter').val();
		let instagram = $('#instagram').val();
		let facebook = $('#facebook').val();
		let tiktok = $('#tiktok').val();
		let onlyfans = $('#onlyfans').val();
		let snapchat = $('#snapchat').val();
		let nationality = $('#nationality').val();
		let age = $('#age').val();
		let weight = $('#weight').val();
    
		if(agency_type == "" || agency_type == null){
			$('#error_agency_type').text('Agency type is required');
			return false;
		}else{
			$('#error_agency_type').text('').remove();
		}
		if(name == "" || name == null){
			$('#error_name').text('Name is required');
			return false;
		}else{
			$('#error_name').text('').remove();
		}

		if(password == "" || password == null){
			$('#error_password').text('Password is required');
			return false;
		}else{
			$('#error_password').text('').remove();
		}
		if(password !== password_confirmation){
			$('#error_password_confirmation').text('Confirm password is not same');
			return false;
		}else{
			$('#error_password_confirmation').text('').remove();
		}
		if(count < 8){
			$('#newpassword_error_').text('8 min. characters are required');
			return false;
		}else{
			$('#newpassword_error_').text('').remove();
		}
		if(province == null || province == ''){
			$('#error_province').text('Province is required');
			return false;
		}else{
			$('#error_province').text('').remove();
		}
		if(city == null || city == ''){
			$('#error_city').text('City is required');
			return false;
		}else{
			$('#error_city').text('').remove();
		}
		if(email == "" || email == null){
			$('#error_email').text('Email is required');
			return false;
		}

		if(website == null || website == ''){
			$('#error_website').text('Website is required');
			return false;
		}else{
			$('#error_website').text('').remove();
		}
		if(phonenumberforcalls == null || phonenumberforcalls == ''){
			$('#error_phonenumberforcalls').text('Phone number is required');
			return false;
		}else{
			$('#error_phonenumberforcalls').text('').remove();
		}
		if(phonenumber == null || phonenumber == ''){
			$('#error_phonenumber').text('Phone number is required');
			return false;
		}else{
			$('#error_phonenumber').text('').remove();
		}
	
		if(twitter == null || twitter == ""){
			$('#error_twitter').text('Twitter is required');
			return false;
		}else{
			$('#error_twitter').text('').remove();
		}
		if(instagram == null || instagram == ""){
			$('#error_instagram').text('instagram is required');
			return false;
		}else{
			$('#error_instagram').text('').remove();
		}
		if(facebook == null || facebook == ""){
			$('#error_facebook').text('Facebook is required');
			return false;
		}else{
			$('#error_facebook').text('').remove();
		}
		if(tiktok == null || tiktok == ""){
			$('#error_tiktok').text('Tiktok is required');
			return false;
		}else{
			$('#error_tiktok').text('').remove();
		}
		if(onlyfans == null || onlyfans == ""){
			$('#error_onlyfans').text('Onlyfans is required');
			return false;
		}else{
			$('#error_onlyfans').text('').remove();
		}
		if(snapchat == null || snapchat == ""){
			$('#error_snapchat').text('Snapchat is required');
			return false;
		}else{
			$('#error_snapchat').text('').remove();
		}
	
		return true;
	});
});

function getCities() {
	$('#city').html('');

	var str='';
	var val=document.getElementById('province');

	for (i=0;i< val.length;i++) {
		if(val[i].selected){
			str += val[i].value + ',';
		}
	}
	var str=str.slice(0,str.length -1);

	$.ajax({
		headers: {
			'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
		},
		method: "POST",
		url: "{{route('fd_get.city')}}",
		data: 'province='+str,
		success: function(result){
			$("#city").html(result);
		}
	});
}
</script>
@endsection