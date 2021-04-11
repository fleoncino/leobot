<?php

function InviaMessaggioTelegram($messaggio){

$apiToken = "INSERIRE API TOKEN";

$data = [
    'chat_id' => 'INSERIRE CHAT ID',
    'text' =>  $messaggio
];

$response = file_get_contents("https://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data) );

}
