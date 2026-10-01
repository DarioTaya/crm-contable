<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
        'url' => env('WHATSAPP_SERVER_URL', 'http://localhost:3001'),
    ],

    'sunat' => [
        'tipo_cambio_url' => env('SUNAT_TIPO_CAMBIO_URL', 'https://www.sunat.gob.pe/a/txt/tipoCambio.txt'),
        'tipo_cambio_mes_url' => env('SUNAT_TIPO_CAMBIO_MES_URL', 'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias/listarTipoCambio'),
        'ruc_url' => env('SUNAT_RUC_URL', 'https://api.apis.net.pe/v2/sunat/ruc'),
        'ruc_token' => env('SUNAT_RUC_TOKEN', env('APIS_NET_PE_TOKEN')),
    ],
];
