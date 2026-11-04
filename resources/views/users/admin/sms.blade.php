@extends('layouts.default-2', ['wrapclass' => 'container-fluid'])


@section('main-content')
<div class="flex flex-wrap">
    <div class="w-full p-4">
        <div class="min-h-5 mb-4">
            <div id="sms-errors-display" style="display: none;" class="border border-[#FF0000] text-sm text-[#FF0000] py-1 px-4 bg-[#FFE9E9]">  </div>
            <div id="sms-message-display" style="display: none;" class="border border-[#2EA135] text-sm text-[#2EA135] py-1 px-4 bg-[#E9FFEB]">  </div>
        </div>

        <ul class="nav nav-tabs p-1 bg-[#F3F4F6] rounded-lg flex items-center overflow-x-auto" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link text-sm font-medium text-[#64748B] py-1.5 px-6 rounded-lg active" id="home-tab" data-bs-toggle="tab" data-bs-target="#home" type="button" role="tab" aria-controls="home" aria-selected="true">Booking Qr Code</button>
            </li>

            <li class="nav-item" role="presentation">
              <button class="nav-link text-sm font-medium text-[#64748B] py-1.5 px-6 rounded-lg" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab" aria-controls="profile" aria-selected="false">Pain Text Message</button>
            </li>
        </ul>

        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade py-4 show active" id="home" role="tabpanel" aria-labelledby="home-tab">
                <h4 class="text-lg font-semibold text-[#111827] mb-2">Send QR Code Link</h4>
                <form id="send-sms-link" method="POST" >
                    @if( isset($form_values) && ($form_values["booking_id"]))
                        <label for="bookingId" class="form-label inline-block text-sm font-semibold text-[#111827] mb-2">{{$form_values["cust_name"]}}</label>
                        <label for="bookingId" class="form-label inline-block text-sm font-semibold text-[#111827] mb-2">{{$form_values["cust_phone"]}}</label>
                        <input type="text" class="form-control mb-4 form-custom-input" disabled="true" value="{{$prefix_id}}{{$form_values["booking_id"]}}" name="bookingId" id="bookingId" placeholder="Booking Id" />
                        <input type="hidden" value="{{$form_values["booking_id"]}}" name="sms_booking_id" id="sms_booking_id" />
                    @else
                        <label for="bookingId" class="form-label inline-block text-lg font-semibold text-[#111827] mb-2">Booking Id</label>
                        <input type="text" class="form-control mb-4 form-custom-input" value="" name="sms_booking_id" id="sms_booking_id" placeholder="Booking Id" />
                    @endif
                    <input type="hidden" name="csrf_token" id="csrf_token" value="{{ csrf_token() }}" />
                    <input type="submit" class="btn btn-primary inline-block mx-auto btn-primary-small" value="Send QR Code" />
                </form>
            </div>

            <div class="tab-pane fade py-4" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                <h4 class="text-lg font-semibold text-[#111827] mb-2">Send SMS Message</h4>
                <form id="send-sms-message" method="POST" >
                    <label for="message" class="form-label inline-block text-sm font-semibold text-[#111827] mb-2">Message</label>
                    @if( isset($form_values) && ($form_values["booking_id"]))
                        <textarea type="text" class="form-control form-custom-input min-h-16 mb-4" cols="5" rows="3" style="text-align: left !important;" name="message" id="message" placeholder="Message">Regarding Booking: {{$prefix_id}}{{$form_values["booking_id"]}}</textarea>
                    @else
                        <input type="text" class="form-control form-custom-input mb-4" value="" name="message" id="message" placeholder="Message" /> 
                    @endif
                    
                    <label for="phone_number" class="form-label text-sm font-semibold text-[#111827] mb-2">Phone Number</label>
                    @if( isset($form_values) && ($form_values["cust_phone"]))
                        <input type="text" class="form-control form-custom-input mb-4" value="{{$form_values["cust_phone"]}}" name="phone_number" id="phone_number" placeholder="Phone Number">
                    @else
                        <input type="text" class="form-control form-custom-input mb-4" value="" name="phone_number" id="phone_number" placeholder="Phone Number">
                    @endif
                    <input type="hidden" name="csrf_token" id="csrf_token" value="{{ csrf_token() }}" />
                    <input type="submit" class="btn btn-primary inline-block mx-auto btn-primary-small" value="Send Text" />
                </form>
            </div>
        </div>

        <div style="inline-block text-lg font-semibold text-[#111827] mb-2"> <strong>Note:</strong> </div>
        <div class="text-[#055160] bg-[#cff4fc] border border-[#b6effb] px-4 py-4 rounded text-sm">
            <small>Country Code is required to send sms messages, <br />
            International phone numbers may take longer to receive text. <br />
            Some countries block text from the United States. <br />
            </small>
        </div>
    </div>
</div>
@endsection

@section('js-libraries')
@endsection

@section('css-custom')
@endsection

@section('js-custom')
<script>

const smsFormValues = @json($form_values);
const smsIsAdminVal = "{{session('userType') == 'Admin' ? '/admin' : ''}}";
var userType = "{{ session('userType') }}";

/**
 * check phone number stndard format
*/
function validatePhoneNumber(val, callback=(msg)=>{ $("#sms-errors-display").show().html(msg); }) {
    const num = val.toString().trim();
    const mob=/^[+]*[(]{0,1}[0-9]{1,3}[)]{0,1}[-\s\./0-9]*$/g;
    if (mob.test(num) == false) {
        callback("Please Enter Valid Phone Number.");
        return false;
    }

     if (num.length > 15) {
        callback("Only 15 characters allowed for Phone Number field.");
        return false;
    }

    if(!num.includes("+")) {
        callback("Phone Number Missing Country Code");
        return false;
    }

    return true;
}

/**
 * clean up the phone number and remove speical chars 
 * example: +1(530) 957-5821 -> +15309575821
*/
const cleanPhoneNumber = (num) => {
    return num.toString().replace(/[^0-9\+]/g, '')
}

/**
 * disable all select fields
*/
const disableSubBtn = (disabled=true, ele='input[type=submit]') => {
    $(ele).each((_idx, e) => { 
        $(e).prop("disabled", disabled); 
    } );
}

/**
 * reset the message divs 
*/
const resetMessageview = () => {
    $("#sms-errors-display").hide().html("");
    $("#sms-message-display").hide().html("");
}

/**
 * on page load add listeners to the page.
*/
$(document).ready(function(_evt){

    // send simple text message
    $("#send-sms-message").submit(function(e){
        e.preventDefault();
        resetMessageview();

        if( $("#message").val().trim() === "" ){
            $("#sms-errors-display").show().html(" Message field can not be blank");
            return false
        }
    
        let data = {};
        $(e.target).serializeArray().forEach((v)=>data[v.name] = v.value)
        data.phone_number = cleanPhoneNumber(data.phone_number);
        if( !validatePhoneNumber(data.phone_number) ){
            return false;
        }

        disableSubBtn(true);
        var urlSendSms = "";
        if(userType == 'Affiliate'){
            urlSendSms = `${smsIsAdminVal}/affiliate/checkin/sms-send`;
        }else{
            urlSendSms = `${smsIsAdminVal}/tour-operator/checkin/sms-send`;
        }

        $.ajax({
            type: 'POST',
            url: urlSendSms,
            data: data,
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: (res) => {  
                $("#sms-message-display").show().html(" Message Sent Successfully ");
                console.log(res);
            },
            error: (err) => { 
                $("#sms-errors-display").show().html(err);
                console.log(err);
            },
        }).always( _evt => disableSubBtn(false) );
    });
    

    //send booking link and qr code
    $("#send-sms-link").submit(function(e){
        e.preventDefault();
        resetMessageview();
        const bookingId = $("#sms_booking_id").val();

        if( bookingId.toString().trim() === ""){
            $("#sms-errors-display").show().html("Booking id field can not be blank");
        }

        if( smsFormValues !== null ) {
            const phone_number = cleanPhoneNumber(smsFormValues.cust_phone);
            if( !validatePhoneNumber(phone_number) ){
                return false;
            }
        }

        disableSubBtn(true);
        var urlSendSmsLink = "";
        if(userType == 'Affiliate'){
            urlSendSmsLink = `${smsIsAdminVal}/affiliate/checkin/sms-send-link`;
        }else{
            urlSendSmsLink = `${smsIsAdminVal}/tour-operator/checkin/sms-send-link`;
        }
        
        $.ajax({
            type: 'POST',
            url: urlSendSmsLink,
            data: {"bookingId": bookingId},
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: (res) => {
                $("#sms-message-display").show().html(" Message Sent Successfully ");
                console.log(res); 
            },
            error: (err) => {
                $("#sms-errors-display").show().html(err);
                console.log(err);
            },
        }).always( _evt => disableSubBtn(false) );
    
    });
    

});
</script>
@endsection