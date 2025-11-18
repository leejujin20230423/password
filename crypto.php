<?php

class PasswordCrypto {

    private $key = "your_secret_key_32bytes_long!!!!";   // 꼭 32바이트
    private $method = "AES-256-CBC";

    public function encrypt($data) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->method));
        $encrypted = openssl_encrypt($data, $this->method, $this->key, 0, $iv);
        return base64_encode($encrypted . "::" . base64_encode($iv));
    }

    public function decrypt($data) {
        list($encrypted_data, $iv) = explode("::", base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, $this->method, $this->key, 0, base64_decode($iv));
    }
}
