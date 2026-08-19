<?php

return [

    'default' => env('BROADCAST_CONNECTION', 'log'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('PUSHER_HOST', 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com'),
                'port' => (int) env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                'curl_options' => [
                    CURLOPT_SSL_VERIFYPEER => false,
                ],
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

    ],

    'channels' => [
        'presence-*' => [
            'driver' => 'pusher',
        ],
        '*' => [
            'driver' => 'pusher',
        ],
    ],

];
