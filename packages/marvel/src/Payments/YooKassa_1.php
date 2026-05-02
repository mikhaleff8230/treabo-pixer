<?php

namespace Marvel\Payments;

use Exception;
use Marvel\Database\Models\Order;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;
use Marvel\Traits\PaymentTrait;
use App\Services\YooKassa\YooKassaService;
use App\Services\YooKassa\YooKassaConfig;
use Symfony\Component\HttpKernel\Exception\HttpException;

class YooKassa extends Base implements PaymentInterface
{
    use PaymentTrait;

    private YooKassaService $yookassaService;

    public function __construct()
    {
        parent::__construct();
        
        $config = config('services.yookassa');
        $this->yookassaService = new YooKassaService(
            new YooKassaConfig(
                shopId: $config['shop_id'],
                secretKey: $config['secret_key'],
                isTest: $config['is_test']
            )
        );
    }

    public function getIntent(array $data): array
    {
        try {
            $order = Order::findOrFail($data['order_id']);
            
            // Формируем данные чека
            $receipt = [
                'customer' => [
                    'email' => $order->customer->email
                ],
                'items' => []
            ];

            // Добавляем товары в чек
            foreach ($order->products as $product) {
                $receipt['items'][] = [
                    'description' => $product->name,
                    'quantity' => $product->pivot->order_quantity,
                    'amount' => [
                        'value' => number_format($product->pivot->unit_price, 2, '.', ''),
                        'currency' => 'RUB'
                    ],
                    'vat_code' => '1', // НДС 20%
                    'payment_mode' => 'full_payment',
                    'payment_subject' => 'commodity'
                ];
            }

            $response = $this->yookassaService->createPayment(
                orderId: $order->tracking_number,
                amount: $order->total,
                description: "Оплата заказа #{$order->tracking_number}",
                successUrl: $data['success_url'],
                failUrl: $data['cancel_url'],
                receipt: $receipt
            );

            return [
                'client_secret' => null,
                'payment_id' => $response['id'],
                'payment_url' => $response['payment_url'],
                'is_redirect' => true
            ];
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    /**
     * Создание платежа для виджета
     */
    public function getIntentForWidget(array $data): array
    {
        try {
            $order = Order::findOrFail($data['order_id']);
            
            // Формируем данные чека
            $receipt = [
                'customer' => [
                    'email' => $order->customer->email
                ],
                'items' => []
            ];

            // Добавляем товары в чек
            foreach ($order->products as $product) {
                $receipt['items'][] = [
                    'description' => $product->name,
                    'quantity' => $product->pivot->order_quantity,
                    'amount' => [
                        'value' => number_format($product->pivot->unit_price, 2, '.', ''),
                        'currency' => 'RUB'
                    ],
                    'vat_code' => '1', // НДС 20%
                    'payment_mode' => 'full_payment',
                    'payment_subject' => 'commodity'
                ];
            }

            $response = $this->yookassaService->createPaymentForWidget(
                orderId: $order->tracking_number,
                amount: $order->total,
                description: "Оплата заказа #{$order->tracking_number}",
                returnUrl: $data['success_url'],
                receipt: $receipt
            );

            return [
                'confirmation_token' => $response['confirmation_token'],
                'payment_id' => $response['id'],
                'is_widget' => true
            ];
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function verify(string $id): mixed
    {
        try {
            $response = $this->yookassaService->checkPayment($id);
            return $response['status'] === 'succeeded';
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function handleWebHooks($request): void
    {
        try {
            \Log::info('YooKassa handleWebHooks: начало обработки');
            
            $data = json_decode($request->getContent(), true);
            
            \Log::info('YooKassa webhook data:', $data);
            
            if (isset($data['event'])) {
                \Log::info('YooKassa webhook event: ' . $data['event']);
            }
            
            if (isset($data['object'])) {
                \Log::info('YooKassa webhook object:', is_array($data['object']) ? $data['object'] : ['type' => gettype($data['object'])]);
                
                if (isset($data['object']['metadata']['orderId'])) {
                    $orderId = $data['object']['metadata']['orderId'];
                    \Log::info('YooKassa webhook orderId: ' . $orderId);
                    
                    switch ($data['event']) {
                        case 'payment.succeeded':
                            \Log::info('Payment succeeded for order: ' . $orderId);
                            $this->updatePaymentOrderStatus(
                                $request,
                                OrderStatus::COMPLETED,  // Меняем на COMPLETED
                                PaymentStatus::SUCCESS
                            );
                            break;
                        case 'payment.waiting_for_capture':
                            \Log::info('Payment waiting for capture for order: ' . $orderId);
                            $this->updatePaymentOrderStatus(
                                $request,
                                OrderStatus::PENDING,
                                PaymentStatus::PROCESSING
                            );
                            break;
                        case 'payment.canceled':
                            \Log::info('Payment canceled for order: ' . $orderId);
                            $this->updatePaymentOrderStatus(
                                $request,
                                OrderStatus::PENDING,
                                PaymentStatus::FAILED
                            );
                            break;
                        default:
                            \Log::warning('Unknown event type: ' . ($data['event'] ?? 'N/A'));
                    }
                } else {
                    \Log::warning('orderId not found in metadata');
                }
            } else {
                \Log::warning('object not found in webhook data');
            }
            
            \Log::info('YooKassa handleWebHooks: обработка завершена');
        } catch (Exception $e) {
            \Log::error('YooKassa handleWebHooks error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw new HttpException(400, $e->getMessage());
        }
    }
    
    /**
     * Update Payment and Order Status для webhook
     *
     * @param $request
     * @param $orderStatus
     * @param $paymentStatus
     * @return void
     */
    public function updatePaymentOrderStatus($request, $orderStatus, $paymentStatus): void
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['object']['metadata']['orderId'])) {
                \Log::error('YooKassa updatePaymentOrderStatus: orderId not found in metadata');
                return;
            }
            
            $orderId = $data['object']['metadata']['orderId'];
            \Log::info('YooKassa updatePaymentOrderStatus: ищем заказ ' . $orderId);
            
            $order = Order::where('tracking_number', $orderId)->first();
            
            if (!$order) {
                \Log::warning('YooKassa updatePaymentOrderStatus: заказ не найден ' . $orderId);
                return;
            }
            
            \Log::info('YooKassa updatePaymentOrderStatus: обновляем заказ', [
                'order_id' => $order->id,
                'old_order_status' => $order->order_status,
                'old_payment_status' => $order->payment_status,
                'new_order_status' => $orderStatus,
                'new_payment_status' => $paymentStatus
            ]);
            
            $this->webhookSuccessResponse($order, $orderStatus, $paymentStatus);
            
            \Log::info('YooKassa updatePaymentOrderStatus: заказ обновлен успешно');
        } catch (Exception $e) {
            \Log::error('YooKassa updatePaymentOrderStatus error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function createCustomer(object $request): array
    {
        // ЮKassa не поддерживает сохранение данных клиента
        return [];
    }

    public function attachPaymentMethodToCustomer(string $retrieved_payment_method, object $request): object
    {
        // ЮKassa не поддерживает сохранение способов оплаты
        return (object)[];
    }

    public function detachPaymentMethodToCustomer(string $retrieved_payment_method): object
    {
        // ЮKassa не поддерживает удаление способов оплаты
        return (object)[];
    }

    public function retrievePaymentIntent(string $payment_intent_id): object
    {
        try {
            $response = $this->yookassaService->checkPayment($payment_intent_id);
            return (object)$response;
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function confirmPaymentIntent(string $payment_intent_id, array $data): object
    {
        try {
            $response = $this->yookassaService->checkPayment($payment_intent_id);
            return (object)$response;
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function setIntent(array $data): array
    {
        // ЮKassa не поддерживает предварительную авторизацию
        return [];
    }

    public function retrievePaymentMethod(string $method_key): object
    {
        // ЮKassa не поддерживает сохранение способов оплаты
        return (object)[];
    }
} 