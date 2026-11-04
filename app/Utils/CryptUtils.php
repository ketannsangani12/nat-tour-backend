<?php
namespace App\Utils;

const __PASSPHRASE__ = '1c1e7b616cd7ec55442180265d4a36ad99024c12b0fbb48a07dc9c6610e7a461';

class CryptUtils {

    /**
     * Encrypt Json with 256 sha and base64 encode
     * Use to send objects between services 
     * Example useage: urlencode(encrypt(['booking_id' => '12345', 'tenant_id' => 'kens']))
     */
    public static function encrypt(array $data): string
    {
        $data_json_64 = base64_encode(json_encode($data));
        $secret_key = hex2bin(__PASSPHRASE__);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = '';
        $encrypted_64 = openssl_encrypt($data_json_64, 'aes-256-gcm', $secret_key, 0, $iv, $tag);
        $json = new \stdClass();
        $json->iv = base64_encode($iv);
        $json->data = $encrypted_64;
        $json->tag = base64_encode($tag);
        return base64_encode(json_encode($json));
    }

    /**
     * Decrypt Json with 256 sha and base64 encode
     * Use to send objects between services 
     */
    public static function decrypt(string $data): array
    {
        $secret_key = hex2bin(__PASSPHRASE__);
        $json = json_decode(base64_decode($data), true);
        $iv = base64_decode($json['iv']);
        $tag = base64_decode($json['tag']);
        $encrypted_data = base64_decode($json['data']);
        $decrypted_data = openssl_decrypt($encrypted_data, 'aes-256-gcm', $secret_key, OPENSSL_RAW_DATA, $iv, $tag);
        return json_decode(base64_decode($decrypted_data),True);
    }

}