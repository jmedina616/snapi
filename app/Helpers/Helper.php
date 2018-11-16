<?php

if (!function_exists('smhEncrypt')) {

    function smhEncrypt($input) {
        $smsk = env('SMSK');
//        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
//        $securekey = mhash(MHASH_MD5, $smsk);
//        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
//        $encrypt_word = base64_encode($iv . mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $securekey, $input, MCRYPT_MODE_CBC, $iv));
//
//        if (strpos($encrypt_word, '+') !== false) {
//            return smhEncrypt($input);
//        } else {
//            return $encrypt_word;
//        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($input, 'aes-256-cbc', $smsk, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }

}

if (!function_exists('smhDecrypt')) {

    function smhDecrypt($input) {
        $smsk = env('SMSK');
//        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
//        $securekey = mhash(MHASH_MD5, $smsk);
//        $input = base64_decode($input);
//        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
//        $iv = substr($input, 0, $iv_size);
//        $cipher = substr($input, $iv_size);
//        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $securekey, $cipher, MCRYPT_MODE_CBC, $iv));

        list($encrypted_data, $iv) = array_pad(explode('::', base64_decode($input), 2), 2, null);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $smsk, 0, $iv);
    }

}

