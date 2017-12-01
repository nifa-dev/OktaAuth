<?php

use Cake\Core\Configure;

$config = [

    'Credentials' => [
        'domain' => 'dev-255537.oktapreview.com',
        'clientId' => '0oackwhnw2oyL8BwT0h7',
        'clientSecret' => 'g2-8QxHn667AZf_hznASraC7vW5RMr5JFNA2cbq9'
    ],
    'OktaConfig' => [
        'redirectUrl' => 'https://jaredtesta.net/jt-cake/login',
        'tokenUrl' => 'https://dev-255537.oktapreview.com/oauth2/default/v1/token?'
    ]
];
return $config;