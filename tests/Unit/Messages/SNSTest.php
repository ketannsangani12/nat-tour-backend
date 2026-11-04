<?php
namespace Tests\Unit\Messages;

use Tests\TestCase;
use App\Repositories\Notify\SMSNotifications;
use App\Utils\StrUtils;
// use App\Utils\CryptUtils;
// use App\Constants\Constants;
// use App\Models\Setting;



/**
 * Test SMS Notification Messages.
 *  Note: this is Scott Deans Phone Number +15309575821
 *  you need to add a phone number AWS_SMS_PHONE_NUMBER var in .env for testing
 * 
 */
class SNSTest extends TestCase{

    public $repo;

    public function initRepo(): void {
        $this->repo = new SMSNotifications();
    }

    public function testCanParsePhoneNumber(){
        $phone =  StrUtils::parseIntPhoneNumber("+1 (530) 957-5821");
        $this->assertEquals("+15309575821", $phone);
     }

    public function testCanGetSmsApiRequestHeaders(){
        $this->initRepo();
        $headers = $this->repo->getHeaders("content");
        $this->assertTrue(count($headers) > 4);
    }

    public function testCanSendSingleAsyncSms() {
        $this->initRepo();
        $phoneNumber = env('AWS_SMS_PHONE_NUMBER', '+15309575821');
        $res = $this->repo->sendSMSAsync("Send single socket asynchronous sms message", $phoneNumber); 
        $this->assertTrue($res);
    }

    // public function testCanSendSmsBulkTxt(){
    //     $this->initRepo();
    //     $number = env('AWS_SMS_PHONE_NUMBER', '+15309575821');
    //     $phoneNumbers = [$number, $number, $number];
    //     $res = $this->repo->sendSMSBulkAsync("Send bulk asynchronous sms message", $phoneNumbers); 
    //     $this->assertTrue($res['fail'] == 0);
    // }

    // public function testCanSendSynchronousSms() {
    //     $this->initRepo();
    //     $phoneNumber = env('AWS_SMS_PHONE_NUMBER', '+15309575821');
    //     $res = $this->repo->sendSMS("Send single synchronous sms message", $phoneNumber); 
    //     $this->assertTrue($res["error"] == null);
    // }

    // public function testCanSendBulkSynchronousSms(){
    //     $this->initRepo();
    //     $number = env('AWS_SMS_PHONE_NUMBER', '+15309575821');
    //     $phoneNumbers = [$number, $number, $number];
    //     $res = $this->repo->sendSMSBulk("Send bulk synchronous sms message", $phoneNumbers);
    //     $this->assertTrue(count($res["result"]) > 2);
    // }


    // public function testcanSendSMSLinkAsync( ) : void {

    //     $res = false;

    //     $number = env('AWS_SMS_PHONE_NUMBER', '+15309575821');
    //     $bookingId = 12345;  
    //     $data = ["tenant_id" => "dixies", 
    //             "cust_phone"=>$number, 
    //             "packageName"=> "Main Tour",
    //             "cust_name" => "Test User" ];
    //     $info = (object)$data;

	// 	// if $sms enabled send sms txt message 
	// 	if( $info->cust_phone ){
	// 		$encoded_data = CryptUtils::encrypt([ strval($bookingId), $info->tenant_id ]);
	// 		$qrcode_url = Constants::SMS_API_URL;
    //         $prefix = "";
	// 		$settings = Setting::where('key', 'BOOKING_ID_PREFIX')->first();
    //         if(isset($settings)){
    //             $prefix = $settings->value;
    //         }
	// 		$smsMessage = "Hello ". $info->cust_name.", Tour Confirmation For ";
	// 		$smsMessage .= $info->packageName." Booking ID: ". $prefix."".$bookingId;
	// 		$smsMessage .= " Details: ". $qrcode_url."/qr/".$info->tenant_id."/".$encoded_data;
	// 		$smsNotify = new SMSNotifications();
	// 		$res = $smsNotify->sendSMSAsync($smsMessage, $info->cust_phone);
	// 	}

    //     $this->assertTrue($res);
	// }

}