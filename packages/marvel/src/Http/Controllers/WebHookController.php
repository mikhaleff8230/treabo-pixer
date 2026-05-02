<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Facades\Payment;
use Marvel\Payments\Flutterwave;

class WebHookController extends CoreController
{

    public function stripe(Request $request)
    {
        return Payment::handleWebHooks($request);
    }

    public function paypal(Request $request)
    {
        return Payment::handleWebHooks($request);
    }

    public function tinkoff(Request $request)
    {
        try {
            $tinkoff = new \Marvel\Payment\Tinkoff();
            $tinkoff->handleWebHooks($request);
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            \Log::error('Tinkoff Webhook error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        }
    }

public function handleTinkoffWebhook(Request $request)
{
    // Подпись и данные от Tinkoff
    // Изменяй статус заказа
}





    public function intellectmoney(Request $request)
    {
        return Payment::handleWebHooks($request);
    }

    public function razorpay(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function mollie(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function sslcommerz(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function paystack(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function paymongo(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function xendit(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function iyzico(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function bitpay(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function coinbase(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function bkash(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function flutterwave(Request $request)
    {
        return Payment::handleWebHooks($request);
    }
    public function callback(Request $request)
    { 
        return Flutterwave::callback($request);
    }
    public function yookassa(Request $request)
    {
        \Log::info('=== YooKassa Webhook Received ===');
        \Log::info('Method: ' . $request->method());
        \Log::info('URL: ' . $request->fullUrl());
        \Log::info('IP: ' . $request->ip());
        \Log::info('Headers: ', $request->headers->all());
        \Log::info('Raw Body: ' . $request->getContent());
        \Log::info('Content Type: ' . $request->header('Content-Type'));
        \Log::info('All Input: ', $request->all());
        
        // Пробуем распарсить JSON
        $rawContent = $request->getContent();
        $jsonData = json_decode($rawContent, true);
        
        if ($jsonData) {
            \Log::info('Parsed JSON Data:', $jsonData);
            
            // Извлекаем payment_id для диагностики
            $paymentId = null;
            if (isset($jsonData['object'])) {
                if (is_array($jsonData['object'])) {
                    $paymentId = $jsonData['object']['id'] ?? null;
                } elseif (is_object($jsonData['object'])) {
                    $paymentId = $jsonData['object']->id ?? null;
                }
            }
            
            \Log::info('Extracted payment_id from webhook: ' . ($paymentId ?? 'NULL'));
            
            // Проверяем, есть ли такой payment_id в базе
            if ($paymentId) {
                $deposit = \App\Models\BalanceDeposit::where('payment_id', $paymentId)->first();
                if ($deposit) {
                    \Log::info('✓ Найдено пополнение баланса в БД:', [
                        'deposit_id' => $deposit->id,
                        'status' => $deposit->status,
                        'amount' => $deposit->amount,
                        'seller_id' => $deposit->seller_id
                    ]);
                } else {
                    \Log::warning('✗ Пополнение баланса НЕ найдено в БД для payment_id: ' . $paymentId);
                    
                    // Показываем все pending для диагностики
                    $allPending = \App\Models\BalanceDeposit::where('status', 'pending')
                        ->whereNotNull('payment_id')
                        ->get();
                    \Log::info('Все pending пополнения:', [
                        'count' => $allPending->count(),
                        'payment_ids' => $allPending->pluck('payment_id')->toArray()
                    ]);
                }
            }
        } else {
            \Log::warning('Не удалось распарсить JSON из webhook');
        }
        
        try {
            $yookassa = new \Marvel\Payments\YooKassa();
            $yookassa->handleWebHooks($request);
            
            \Log::info('YooKassa Webhook processed successfully');
            
            return response()->json([
                'status' => 'ok',
                'message' => 'Webhook processed'
            ]);
        } catch (\Throwable $e) {
            \Log::error('YooKassa Webhook error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function customWebhookExample(Request $request)
    {
        // Пример собственной логики обработки webhook
        return response()->json([
            'status' => 'success',
            'message' => 'Custom webhook received',
            'data' => $request->all(),
        ]);
    }
}
