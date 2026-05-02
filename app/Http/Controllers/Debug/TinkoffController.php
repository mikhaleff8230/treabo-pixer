<?php

namespace App\Http\Controllers\Debug;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Marvel\src\Payments\Tinkoff;
use Illuminate\Support\Facades\Log;

class TinkoffController extends Controller
{
    public function index()
    {
        if (!app()->environment('production')) {
            abort(404);
        }
        
        return view('debug.tinkoff');
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

            Log::channel('tinkoff')->info('Создан тестовый платеж', [
                'payment_id' => $payment['payment_id'] ?? null,
                'amount' => $amount
            ]);

            return response()->json([
                'success' => true,
                'payment_id' => $payment['payment_id'] ?? null,
                'payment_url' => $payment['payment_url'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::channel('tinkoff')->error('Ошибка при создании платежа', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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

            Log::channel('tinkoff')->info('Проверка статуса платежа', [
                'payment_id' => $paymentId,
                'status' => $status
            ]);

            return response()->json([
                'success' => true,
                'status' => $status ? 'Подтвержден' : 'Не подтвержден'
            ]);
        } catch (\Exception $e) {
            Log::channel('tinkoff')->error('Ошибка при проверке статуса', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
} 