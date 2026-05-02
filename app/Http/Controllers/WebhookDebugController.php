<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\BalanceDeposit;

class WebhookDebugController extends Controller
{
    /**
     * Универсальный webhook endpoint для диагностики
     * POST /api/webhooks/debug
     */
    public function debug(Request $request)
    {
        Log::info('=== WEBHOOK DEBUG: Получен запрос ===');
        Log::info('Method: ' . $request->method());
        Log::info('URL: ' . $request->fullUrl());
        Log::info('Headers:', $request->headers->all());
        Log::info('Raw Content: ' . $request->getContent());
        Log::info('Content Type: ' . $request->header('Content-Type'));
        Log::info('IP: ' . $request->ip());
        Log::info('All Input:', $request->all());
        
        // Пробуем распарсить JSON
        $jsonData = json_decode($request->getContent(), true);
        if ($jsonData) {
            Log::info('Parsed JSON:', $jsonData);
            
            // Извлекаем payment_id
            $paymentId = null;
            if (isset($jsonData['object'])) {
                if (is_array($jsonData['object'])) {
                    $paymentId = $jsonData['object']['id'] ?? null;
                } elseif (is_object($jsonData['object'])) {
                    $paymentId = $jsonData['object']->id ?? null;
                }
            }
            
            Log::info('Extracted payment_id: ' . ($paymentId ?? 'NULL'));
            
            // Ищем в базе
            if ($paymentId) {
                $deposit = BalanceDeposit::where('payment_id', $paymentId)->first();
                if ($deposit) {
                    Log::info('✓ Найдено пополнение баланса в БД:', [
                        'deposit_id' => $deposit->id,
                        'status' => $deposit->status,
                        'amount' => $deposit->amount,
                        'seller_id' => $deposit->seller_id
                    ]);
                } else {
                    Log::warning('✗ Пополнение баланса НЕ найдено в БД для payment_id: ' . $paymentId);
                    
                    // Показываем все pending
                    $allPending = BalanceDeposit::where('status', 'pending')->whereNotNull('payment_id')->get();
                    Log::info('Все pending пополнения:', [
                        'count' => $allPending->count(),
                        'payment_ids' => $allPending->pluck('payment_id')->toArray()
                    ]);
                }
            }
        }
        
        return response()->json([
            'status' => 'ok',
            'message' => 'Webhook received and logged',
            'timestamp' => now()->toDateTimeString()
        ]);
    }
}

