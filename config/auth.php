<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
            'hash' => false,
        ],

        'pembeli' => [
            'driver' => 'sanctum',
            'provider' => 'pembelis',
        ],

        'penitip' => [
            'driver' => 'sanctum',
            'provider' => 'penitips',
        ],

        'organisasi' => [
            'driver' => 'sanctum',
            'provider' => 'organisasis',
        ],

        'pegawai' => [
            'driver' => 'sanctum',
            'provider' => 'pegawais',
        ],
        
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        'pembelis' => [
            'driver' => 'eloquent',
            'model' => App\Models\Pembeli::class,
        ],

        'penitips' => [
            'driver' => 'eloquent',
            'model' => App\Models\Penitip::class,
        ],

        'organisasis' => [
            'driver' => 'eloquent',
            'model' => App\Models\Organisasi::class,
        ],

        'pegawais' => [
            'driver' => 'eloquent',
            'model' => App\Models\Pegawai::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        
        'pembelis' => [
            'provider' => 'pembelis',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        
        'penitips' => [
            'provider' => 'penitips',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        
        'organisasis' => [
            'provider' => 'organisasis',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
        
        'pegawais' => [
            'provider' => 'pegawais',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => 10800,
];