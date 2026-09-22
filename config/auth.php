<?php

return [

    'defaults' => [
        'guard'     => 'aggregator',
        'passwords' => 'aggregators',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        'aggregator' => [
            'driver'   => 'session',
            'provider' => 'aggregators',
        ],
    ],

    'providers' => [
      

        'aggregators' => [
            'driver' => 'eloquent',
            'model'  => App\Domain\Auth\Models\Aggregator::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],

        'aggregators' => [
            'provider'  => 'aggregators',
            'table'     => 'password_reset_tokens',
            'expire'    => 60,
            'throttle'  => 60,
        ],
    ],

    'password_timeout' => 10800,
];