<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TestYooKassaController extends Controller
{
    public function test()
    {
        return response()->json([
            'message' => 'Test YooKassa controller works!',
            'timestamp' => now(),
            'status' => 'success'
        ]);
    }
}





