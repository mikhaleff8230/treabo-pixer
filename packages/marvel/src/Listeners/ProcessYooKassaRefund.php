<?php

namespace Marvel\Listeners;

use App\Services\YooKassa\YooKassaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Marvel\Enums\PaymentGatewayType;
use Marvel\Enums\PaymentStatus;
use Marvel\Events\OrderCancelled;

class ProcessYooKassaRefund implements ShouldQueue
{
    public function __construct(
        private readonly YooKassaService $yookassaService
    ) {
    }

    /**
     * Handle the event.
     *
     * @param OrderCancelled $event
     * @return void
     */
    public function handle(OrderCancelled $event)
    {
        $order = $event->order;
        
        // Загружаем необходимые отношения, если они не загружены
        if (!$order->relationLoaded('payment_intent')) {
            $order->load('payment_intent');
        }
        if (!$order->relationLoaded('customer')) {
            $order->load('customer');
        }
        if (!$order->relationLoaded('products')) {
            $order->load('products');
        }

        try {
            // Проверяем, что заказ оплачен и используется Юкасса
            if ($order->payment_status !== PaymentStatus::SUCCESS) {
                Log::info('ProcessYooKassaRefund: Заказ не оплачен, возврат не требуется', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'payment_status' => $order->payment_status
                ]);
                return;
            }

            if ($order->payment_gateway !== PaymentGatewayType::YOOKASSA) {
                Log::info('ProcessYooKassaRefund: Заказ оплачен не через Юкассу', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'payment_gateway' => $order->payment_gateway
                ]);
                return;
            }

            // Получаем payment_id из payment_intent
            $paymentIntent = $order->payment_intent;
            if (!$paymentIntent || empty($paymentIntent)) {
                Log::warning('ProcessYooKassaRefund: Payment intent не найден', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number
                ]);
                return;
            }

            // Если payment_intent - это коллекция, берем первый элемент
            if (is_iterable($paymentIntent) && !is_array($paymentIntent)) {
                $paymentIntent = $paymentIntent->first();
            }

            // Если payment_intent - это массив, берем первый элемент
            if (is_array($paymentIntent)) {
                $paymentIntent = $paymentIntent[0] ?? null;
            }

            if (!$paymentIntent) {
                Log::warning('ProcessYooKassaRefund: Payment intent пустой', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number
                ]);
                return;
            }

            // Получаем payment_id из payment_intent_info
            $paymentIntentInfo = is_object($paymentIntent) 
                ? $paymentIntent->payment_intent_info 
                : (is_array($paymentIntent) ? ($paymentIntent['payment_intent_info'] ?? null) : null);

            if (!$paymentIntentInfo) {
                Log::warning('ProcessYooKassaRefund: payment_intent_info не найден', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number
                ]);
                return;
            }

            $paymentId = is_array($paymentIntentInfo) 
                ? ($paymentIntentInfo['payment_id'] ?? null)
                : (is_object($paymentIntentInfo) ? ($paymentIntentInfo->payment_id ?? null) : null);

            if (!$paymentId) {
                Log::warning('ProcessYooKassaRefund: payment_id не найден в payment_intent_info', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'payment_intent_info' => $paymentIntentInfo
                ]);
                return;
            }

            // Получаем сумму для возврата (используем paid_total, так как это фактически оплаченная сумма)
            $refundAmount = $order->paid_total ?? $order->total;
            
            if ($refundAmount <= 0) {
                Log::info('ProcessYooKassaRefund: Сумма возврата равна нулю', [
                    'order_id' => $order->id,
                    'tracking_number' => $order->tracking_number,
                    'refund_amount' => $refundAmount
                ]);
                return;
            }

            // Формируем данные чека возврата
            $receipt = null;
            if ($order->customer && $order->customer->email) {
                $receipt = [
                    'customer' => [
                        'email' => $order->customer->email
                    ],
                    'items' => []
                ];

                // Добавляем товары в чек возврата
                if ($order->products && $order->products->count() > 0) {
                    foreach ($order->products as $product) {
                        $receipt['items'][] = [
                            'description' => 'Возврат: ' . $product->name,
                            'quantity' => $product->pivot->order_quantity ?? 1,
                            'amount' => [
                                'value' => number_format($product->pivot->unit_price ?? 0, 2, '.', ''),
                                'currency' => 'RUB'
                            ],
                            'vat_code' => '1', // НДС 20%
                            'payment_mode' => 'full_payment',
                            'payment_subject' => 'commodity'
                        ];
                    }
                }
            }

            // Выполняем возврат через Юкассу
            Log::info('ProcessYooKassaRefund: Начинаем возврат платежа', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'payment_id' => $paymentId,
                'refund_amount' => $refundAmount
            ]);

            $refundResult = $this->yookassaService->refundPayment($paymentId, $refundAmount, $receipt);

            Log::info('ProcessYooKassaRefund: Возврат успешно выполнен', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'refund_id' => $refundResult['id'] ?? null,
                'refund_status' => $refundResult['status'] ?? null
            ]);

            // Обновляем статус платежа заказа на REVERSAL (возврат)
            $order->payment_status = PaymentStatus::REVERSAL;
            $order->save();

        } catch (\Exception $e) {
            Log::error('ProcessYooKassaRefund: Ошибка при возврате платежа', [
                'order_id' => $order->id ?? null,
                'tracking_number' => $order->tracking_number ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Не пробрасываем исключение, чтобы не прервать обработку других listeners
        }
    }
}

