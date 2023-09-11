<?php

use Sarahman\SmsService\Client;

return [
    'default' => [
        'provider' => Client::PROVIDER_SSL,
    ],

    'providers' => [
        Client::PROVIDER_BANGLALINK => [
            'url' => 'https://vas.banglalinkgsm.com/sendSMS/sendSMS',
            'userID' => '',
            'passwd' => '',
            'sender' => '',
        ],
        Client::PROVIDER_BD_WEB_HOST_24 => [
            'url' => 'http://sms.bdwebhost24.com/smsapi',
            'senderid' => '',
            'api_key' => '',
            'type' => 'text',
        ],
        Client::PROVIDER_BOOM_CAST => [
            'url' => 'http://api.boom-cast.com/boomcast/WebFramework/boomCastWebService/externalApiSendTextMessage.php',
            'userName' => '',
            'password' => '',
            'MsgType' => 'TEXT',
            'masking' => 'S.H.P.L',
        ],
        Client::PROVIDER_SSL => [
            'url' => 'http://sms.sslwireless.com/pushapi',
            'user' => '',
            'pass' => '',
            'sid' => '',
        ],
    ],
];
