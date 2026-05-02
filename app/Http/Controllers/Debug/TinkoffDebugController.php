<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Marvel\src\Payments\Tinkoff;

class TinkoffDebugController extends Controller
{
    public function index()
    {
        if (!app()->environment('production')) {
            abort(404);
        }

        // Проверка наличия необходимых переменных окружения
        $requiredEnvVars = [
            'TINKOFF_TERMINAL_KEY',
            'TINKOFF_PASSWORD',
            'TINKOFF_API_URL'
        ];

        foreach ($requiredEnvVars as $var) {
            if (empty(env($var))) {
                return response()->json([
                    'error' => "Отсутствует переменная окружения {$var}"
                ], 500);
            }
        }

        return view('debug.tinkoff-debug');
    }

    public function createPayment(Request $request)
    {
        try {
            if (!app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот метод доступен только в production'
                ], 403);
            }

            $amount = $request->input('amount', 1.00);
            
            $tinkoff = new Tinkoff();
            
            $paymentData = [
                'order_id' => 'TEST-' . time(),
                'amount' => $amount,
                'success_url' => config('app.url') . '/payment/success',
                'cancel_url' => config('app.url') . '/payment/fail'
            ];

            $payment = $tinkoff->getIntent($paymentData);

            return response()->json([
                'success' => true,
                'payment_id' => $payment['payment_id'] ?? null,
                'payment_url' => $payment['payment_url'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkStatus($paymentId)
    {
        try {
            if (!app()->environment('production')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Этот метод доступен только в production'
                ], 403);
            }

            $tinkoff = new Tinkoff();
            $status = $tinkoff->verify($paymentId);

            return response()->json([
                'success' => true,
                'status' => $status ? 'Подтвержден' : 'Не подтвержден'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
} 