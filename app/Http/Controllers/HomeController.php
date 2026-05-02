<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stevebauman\Location\Facades\Location;
use Illuminate\Support\Facades\Cookie;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $city = $request->cookie('user_city');
        if (!$city) {
            $ip = $request->ip();
            if ($ip === '127.0.0.1' || $ip === '::1') {
                // для локальной разработки можно подставить тестовый IP из РФ
                $ip = '95.24.18.3';
            }
            $position = Location::get($ip);
            $city = $position && $position->cityName ? $position->cityName : null;
        }
        return view('welcome', ['detectedCity' => $city]);
    }

    public function setCity(Request $request)
    {
        $city = $request->input('city');
        $minutes = 60 * 24 * 30; // 30 дней
        Cookie::queue('user_city', $city, $minutes);
        return response()->json(['status' => 'ok', 'city' => $city]);
    }
}

