<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Location Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default location driver used by the application.
    |
    | Supported: "ipinfo", "maxmind", "freegeoip"
    |
    */

    'driver' => env('LOCATION_DRIVER', 'ipinfo'),

    /*
    |--------------------------------------------------------------------------
    | Location Drivers
    |--------------------------------------------------------------------------
    |
    | Here you can configure the location drivers for your application.
    |
    */

    'drivers' => [

        'ipinfo' => [
            'class' => \Stevebauman\Location\Drivers\IpInfo::class,
            'token' => env('IPINFO_TOKEN'),
        ],

        'maxmind' => [
            'class' => \Stevebauman\Location\Drivers\MaxMind::class,
            'user_id' => env('MAXMIND_USER_ID'),
            'license_key' => env('MAXMIND_LICENSE_KEY'),
        ],

        'freegeoip' => [
            'class' => \Stevebauman\Location\Drivers\FreeGeoIp::class,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Driver
    |--------------------------------------------------------------------------
    |
    | If the primary driver fails, use this one
    |
    */
    'fallback' => [
        'ipapi' => [
            'class' => \Stevebauman\Location\Drivers\Http::class,
            'url' => 'http://ipapi.co',
        ],
    ],

];

