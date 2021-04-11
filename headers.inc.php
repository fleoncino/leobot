<?php

function creaHeaders($url){
    $key='INSERIRE API KEY';
    $secret='INSERIRE SECRET';
    $nonce=microtime(true)*10000;
    $signature=hash_hmac("sha512",$nonce.$url,$secret);

    $headers=array(
    "Content-Type: application/json",
    "X-TRT-KEY: ".$key,
    "X-TRT-SIGN: ".$signature,
    "X-TRT-NONCE: ".$nonce
);
return $headers;
}


