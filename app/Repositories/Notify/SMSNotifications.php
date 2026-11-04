<?php

namespace App\Repositories\Notify;

use Aws\Sns\SnsClient;
use Aws\Exception\AwsException;
use App\Repositories\Repository;

use App\Constants\Constants;
use App\Utils\RequestUtils;
use App\Utils\StrUtils;
use App\Utils\CryptUtils;

use Exception;

class SMSNotifications extends Repository
{
    private $config;

    /**
     * init sms auth config.
     */
    function __construct()
    {
        $this->config = config('services');
    }

    /**
     * sendSMSAsync offloads the process of sending a single request to a lambda 
     * assumes that the country code prefix is appened to the phone number
     * 
     * @param string $message text message up to 140 bytes
     * @param string $phone phone number with + courntry code +1 222 333-4444
     */
    public function sendSMSAsync($message, $phone)
    {
        try {
            ///notify/sns/index
            $url = Constants::SMS_API_URL . "/notify/sms/send";
            $dataStr = json_encode(["message" => $message, "number" => $phone]);
            $headers = $this->getHeaders($dataStr);
            //RequestUtils::asyncSocketRequest($url, [ "message"=> $message, "number"=> $phone  ], ['x-api-key: '.Constants::SMS_API_KEY]);
            RequestUtils::asyncCurlRequest($url, $headers, $dataStr);
            return true;
        } catch (Exception $err) {
            print($err);
            return false;
        }
    }

    /**
     * sendSMSBulkAsync offloads the process to a lambda 
     * assumes that the country code prefix is appened to the phone number
     * 
     * @param string $message text message up to 140 bytes
     * @param array $phone_numbers array phone numbers with + courntry code 
     * 
     * @return array [sent count, total count, failed count]
     */
    public function sendSMSBulkAsync($message, $phone_numbers): array
    {

        //bulk api allows for 100 sms text at once 
        $process_numbers = array_chunk($phone_numbers, 99);

        //track sent/failed
        $sent_count = 0;
        $total_count = count($phone_numbers);

        //for each grp of 99 numbers snd bulk text message
        foreach ($process_numbers as $number_grp) {
            $url = Constants::SMS_API_URL . "/notify/sms/bulk/send";
            $dataStr = json_encode(["message" => $message, "numbers" => implode("|", $number_grp)]);
            $headers = $this->getHeaders($dataStr);
            RequestUtils::asyncCurlRequest($url, $headers, $dataStr);
            $sent_count += count($number_grp);
        }

        //return res
        return [
            "sent" => $sent_count,
            "total" => $total_count,
            "fail" => ($total_count - $sent_count)
        ];
    }

    /**
     * Send sms for booking
     *
     * @param int|string $bookingId
     * @param array $props [ tenant_id, cust_phone, cust_name, package_name  ]
     * @return array
     */
    public function sendSMSLink($bookingId,  $props): array
    {
        $error = null;
        $smsMessage = "";

        try {
            $encoded_data    = CryptUtils::encrypt([strval($bookingId), $props["tenant_id"]]);
            $qrcode_url      = Constants::SMS_API_URL;

            $smsMessage      = "Hello " . $props["cust_name"] . ", Tour Confirmation For ";
            $smsMessage     .= $props["package_name"] . " Booking ID: " . $bookingId . " \n";
            $smsMessage     .= $qrcode_url . "/qr/" . $props["tenant_id"] . "/" . $encoded_data;

            if (!isset($props["cust_phone"]) || $props["cust_phone"] == "") {
                $error = "phone number is missing";
            }

            if ($error == null) {
                $this->sendSMSAsync($smsMessage, $props["cust_phone"]);
            }
        } catch (Exception $err) {
            $error = $err->getMessage();
        }

        return [
            "message" => $smsMessage,
            "props" => $props,
            "error" => $error
        ];
    }

    /**
     * sendSMS Send a message to single phone number, Note Uses Local CPU resources
     * assumes that the country code prefix is appened to the phone number
     * 
     * @param string $message text message up to 140 bytes
     * @param string $phone phone number with + courntry code
     * 
     * @return array
     */
    public function sendSMS($message, $phone): array
    {
        try {
            $SnSclient = new SnsClient($this->getAWSCreds());
            $phone = StrUtils::parseIntPhoneNumber($phone);
            $result = $SnSclient->publish($this->getAWSContext($message, $phone));
            return [
                "result" => $result,
                "error" => null
            ];
        } catch (AwsException $e) {
            return [
                "result" => null,
                "error" => $e->getMessage()
            ];
        }
    }

    /**
     * Send Bulk Aws Sms Messages Note Uses Local CPU resources
     * 
     * @param string $message 
     * @param array $phone_numbers list of phone numbers to send to
     * @param array 
     */
    public function sendSMSBulk($message, $phone_numbers): array
    {
        $SnSclient = new SnsClient($this->getAWSCreds());
        $results = [];

        try {
            foreach ($phone_numbers as $phone) {
                $phone = StrUtils::parseIntPhoneNumber($phone);
                $res = $SnSclient->publish($this->getAWSContext($message, $phone));
                $results[] = $res["MessageId"];
            }

            return [
                "result" => $results,
                "error" => null
            ];
        } catch (AwsException $e) {
            return [
                "result" => null,
                "error" => $e->getMessage()
            ];
        }
    }

    /**
     * Return standard api headers
     * 
     * @param string|null $content
     * 
     * @return array
     */
    public function getHeaders($content = null): array
    {
        $headers = [
            'x-api-key: ' . Constants::SMS_API_KEY,
            'Content-Type: application/json',
            'User-Agent: nat-applicaiton',
            'Accept: */*',
            'Accept-Encoding: gzip, deflate, br'
        ];

        if ($content) {
            $headers[] = 'Content-Length: ' . strlen($content);
        }

        return $headers;
    }

    /**
     * Get Aws login info from conf file
     * 
     * @return array
     */
    public function getAWSCreds()
    {
        $profile = $this->config['ses'];
        $conf = [
            'credentials' => array(
                'key' => $profile['key'],
                'secret' => $profile['secret'],
            ),
            'region' => $profile['region'],
            'version' => 'latest'
        ];

        return $conf;
    }

    /**
     * Set the AWS Sms Context Args
     * 
     * @param string $message
     * @param string $phone
     * 
     * @return array
     */
    public function getAWSContext($message, $phone)
    {
        return [
            "MessageAttributes" => [
                'AWS.SNS.SMS.SenderID' => [
                    'DataType' => 'String',
                    'StringValue' => 'NAT'
                ],
                'AWS.SNS.SMS.SMSType' => [
                    'DataType' => 'String',
                    'StringValue' => 'Transactional'
                ]
            ],
            "Message" => $message,
            "PhoneNumber" => $phone
        ];
    }
}
