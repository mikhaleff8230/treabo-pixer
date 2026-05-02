<?php
/**
 * Created by PhpStorm.
 * User: m.shakin@digitalwand.ru
 * Date: 21.12.17
 * Time: 13:11
 */

return [
    'terminal' => env('TINKOFF_TERMINAL'),
    'password' => env('TINKOFF_PASSWORD'),
    'test' => env('TINKOFF_TEST', false),
    'api_url' => env('TINKOFF_API_URL', 'https://securepay.tinkoff.ru/v2'),
];