<?php
namespace App\Utils;


class RequestUtils {


    public static function asyncCurlRequest($url, $headers, $data){

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'nat-app');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers ); 
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, false);
        curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 100); 
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);

        ob_start();
        curl_exec($ch);
        curl_close($ch);
        ob_get_clean();
    }



    public static function asyncSocketRequest($url, $params=[], $headers=[]) {

        $post_string = "";

        foreach ($params as $key => &$val) {
            if (is_array($val)) $val = implode(',', $val);
            $post_params[] = $key.'='.urlencode($val);
            $post_string = implode('&', $post_params);
        }

        $parts = parse_url($url);

        $fp = fsockopen($parts['host'],
            isset($parts['port'])?$parts['port']:80,
            $errno, $errstr, 30);
    
        $out = "POST ".$parts['path']." HTTP/1.1\r\n";
        $out.= "Host: ".$parts['host']."\r\n";
        $out.= "Content-Type: application/json\r\n";
        $out.= "User-Agent: nat-applicaiton\r\n";
        $out.= "Accept: */*\r\n";
        $out.= "Accept-Encoding: gzip, deflate, br\r\n";
        $out.= "Content-Length: ".strlen($post_string)."\r\n";
        foreach($headers as $header){
            $out.= $header."\r\n";
        }

        $out.= "Connection: Close\r\n\r\n";
        if (isset($post_string)) $out.= $post_string;
    
        print($out);

        fwrite($fp, $out);
        fclose($fp);
        
    }


}