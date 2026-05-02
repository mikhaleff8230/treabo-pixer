<?php

namespace Marvel\Payments;

use Exception;
use Marvel\Database\Models\Order;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;
use Marvel\Enums\PaymentGatewayType;
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
                'items' => []
            ];
            
            // Добавляем данные клиента, если есть
            $customer = [];
            if ($order->customer && !empty($order->customer->email)) {
                $customer['email'] = $order->customer->email;
            }
            
            // Добавляем customer только если есть email
            if (!empty($customer)) {
                $receipt['customer'] = $customer;
            }

            // Добавляем товары в чек
            foreach ($order->products as $product) {
                $receipt['items'][] = [
                    'description' => $product->name,
                    'quantity' => $product->pivot->order_quantity,
                    'amount' => [
                        'value' => number_format($product->pivot->unit_price, 2, '.', ''),
                        'currency' => 'RUB'
                    ],
                    'vat_code' => 1, // НДС 20%
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

            // Сохраняем payment_id в payment_intent_info, если PaymentIntent уже существует
            $paymentIntent = \Marvel\Database\Models\PaymentIntent::where('order_id', $order->id)
                ->where('payment_gateway', PaymentGatewayType::YOOKASSA)
                ->first();
            
            if ($paymentIntent && $response['id']) {
                $paymentIntentInfo = $paymentIntent->payment_intent_info ?? [];
                if (is_string($paymentIntentInfo)) {
                    $paymentIntentInfo = json_decode($paymentIntentInfo, true) ?? [];
                }
                
                $paymentIntentInfo['payment_id'] = $response['id'];
                $paymentIntent->payment_intent_info = $paymentIntentInfo;
                $paymentIntent->save();
                
                \Log::info('YooKassa getIntent: payment_id сохранен в payment_intent_info', [
                    'payment_intent_id' => $paymentIntent->id,
                    'payment_id' => $response['id']
                ]);
            }

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
                'items' => []
            ];
            
            // Добавляем данные клиента, если есть
            $customer = [];
            if ($order->customer && !empty($order->customer->email)) {
                $customer['email'] = $order->customer->email;
            }
            
            // Добавляем customer только если есть email
            if (!empty($customer)) {
                $receipt['customer'] = $customer;
            }

            // Добавляем товары в чек
            foreach ($order->products as $product) {
                $receipt['items'][] = [
                    'description' => $product->name,
                    'quantity' => $product->pivot->order_quantity,
                    'amount' => [
                        'value' => number_format($product->pivot->unit_price, 2, '.', ''),
                        'currency' => 'RUB'
                    ],
                    'vat_code' => 1, // НДС 20%
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

            // Сохраняем payment_id в payment_intent_info, если PaymentIntent уже существует
            $paymentIntent = \Marvel\Database\Models\PaymentIntent::where('order_id', $order->id)
                ->where('payment_gateway', PaymentGatewayType::YOOKASSA)
                ->first();
            
            if ($paymentIntent && $response['id']) {
                $paymentIntentInfo = $paymentIntent->payment_intent_info ?? [];
                if (is_string($paymentIntentInfo)) {
                    $paymentIntentInfo = json_decode($paymentIntentInfo, true) ?? [];
                }
                
                $paymentIntentInfo['payment_id'] = $response['id'];
                $paymentIntent->payment_intent_info = $paymentIntentInfo;
                $paymentIntent->save();
                
                \Log::info('YooKassa getIntentForWidget: payment_id сохранен в payment_intent_info', [
                    'payment_intent_id' => $paymentIntent->id,
                    'payment_id' => $response['id']
                ]);
            }

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
            
            // ВАЖНО: YooKassa не использует подпись в заголовках, но можно проверить IP-адреса
            // YooKassa отправляет webhook с IP-адресов, указанных в документации
            // Для дополнительной безопасности можно проверить, что запрос пришел с доверенных IP
            
            $data = json_decode($request->getContent(), true);
            
            \Log::info('YooKassa webhook data:', $data);
            \Log::info('YooKassa webhook IP:', ['ip' => $request->ip(), 'forwarded_for' => $request->header('X-Forwarded-For')]);
            
            if (isset($data['event'])) {
                \Log::info('YooKassa webhook event: ' . $data['event']);
            }
            
            if (isset($data['object'])) {
                \Log::info('YooKassa webhook object:', is_array($data['object']) ? $data['object'] : ['type' => gettype($data['object'])]);
                
                // Извлекаем payment_id и event из webhook данных
                $paymentId = null;
                $event = $data['event'] ?? null;
                
                if (is_array($data['object'])) {
                    $paymentId = $data['object']['id'] ?? null;
                } elseif (is_object($data['object'])) {
                    // Если object - это объект, пытаемся получить id через методы
                    if (method_exists($data['object'], 'getId')) {
                        $paymentId = $data['object']->getId();
                    } elseif (isset($data['object']->id)) {
                        $paymentId = $data['object']->id;
                    }
                }
                
                \Log::info('YooKassa: Извлеченные данные', [
                    'payment_id' => $paymentId,
                    'event' => $event,
                    'object_type' => gettype($data['object'])
                ]);
                
                // Извлекаем paid из объекта
                $isPaid = false;
                if (is_array($data['object'])) {
                    $isPaid = ($data['object']['paid'] ?? false) === true;
                } elseif (is_object($data['object'])) {
                    $isPaid = ($data['object']->paid ?? false) === true;
                }
                
                // ПЕРВЫМ ДЕЛОМ проверяем пополнение баланса по payment_id
                // Делаем ТОЧНО то же самое, что команда ProcessPendingBalanceDeposits
                if ($paymentId && $event === 'payment.succeeded') {
                    \Log::info('YooKassa: Проверяем пополнение баланса для payment_id: ' . $paymentId, [
                        'payment_id' => $paymentId,
                        'event' => $event,
                        'is_paid_from_webhook' => $isPaid
                    ]);
                    
                    // Ищем запись в БД
                    $balanceDeposit = \App\Models\BalanceDeposit::where('payment_id', $paymentId)->first();
                    
                    if ($balanceDeposit) {
                        \Log::info('YooKassa: ✓ Найдено пополнение баланса в БД', [
                            'deposit_id' => $balanceDeposit->id,
                            'seller_id' => $balanceDeposit->seller_id,
                            'amount' => $balanceDeposit->amount,
                            'status' => $balanceDeposit->status,
                            'payment_id' => $paymentId
                        ]);
                        
                        // ВАЖНО: Проверяем статус через API YooKassa (как делает команда)
                        // Не полагаемся только на данные из webhook
                        try {
                            $shopId = config('services.yookassa.shop_id');
                            $secretKey = config('services.yookassa.secret_key');
                            $isTest = config('services.yookassa.is_test', false);
                            
                            if (empty($shopId) || empty($secretKey)) {
                                \Log::error('YooKassa: YooKassa не настроен в конфиге');
                                throw new \Exception('YooKassa не настроен');
                            }
                            
                            $config = new \App\Services\YooKassa\YooKassaConfig($shopId, $secretKey, $isTest);
                            $service = new \App\Services\YooKassa\YooKassaService($config);
                            
                            \Log::info('YooKassa: Проверяем статус платежа через API для payment_id: ' . $paymentId);
                            $paymentInfo = $service->checkPayment($paymentId);
                            
                            \Log::info('YooKassa: Статус платежа из API', [
                                'status' => $paymentInfo['status'] ?? null,
                                'paid' => $paymentInfo['paid'] ?? null,
                                'amount' => $paymentInfo['amount'] ?? null
                            ]);
                            
                            // Проверяем статус ТОЧНО как в команде ProcessPendingBalanceDeposits
                            if (($paymentInfo['status'] ?? null) === 'succeeded' && ($paymentInfo['paid'] ?? false) === true) {
                                if ($balanceDeposit->status === 'pending') {
                                    // Платёж успешен - пополняем баланс (ТОЧНО как в команде)
                                    $balance = \App\Models\SellerBalance::getOrCreate($balanceDeposit->seller_id);
                                    $oldBalance = $balance->balance;
                                    $balance->deposit($balanceDeposit->amount, "Пополнение баланса через YooKassa webhook");
                                    
                                    $balanceDeposit->update([
                                        'status' => 'succeeded',
                                        'paid_at' => now()
                                    ]);

                                    \Log::info('YooKassa: ✓✓✓ БАЛАНС ПОПОЛНЕН УСПЕШНО (через API проверку) ✓✓✓', [
                                        'deposit_id' => $balanceDeposit->id,
                                        'seller_id' => $balanceDeposit->seller_id,
                                        'amount' => $balanceDeposit->amount,
                                        'old_balance' => $oldBalance,
                                        'new_balance' => $balance->balance,
                                        'payment_id' => $paymentId,
                                        'payment_status_from_api' => $paymentInfo['status'],
                                        'payment_paid_from_api' => $paymentInfo['paid']
                                    ]);
                                    
                                    // Не обрабатываем как заказ, если это пополнение баланса
                                    return;
                                } else {
                                    \Log::info('YooKassa: Пополнение баланса уже обработано', [
                                        'deposit_id' => $balanceDeposit->id,
                                        'status' => $balanceDeposit->status
                                    ]);
                                    return;
                                }
                            } else {
                                \Log::warning('YooKassa: Платеж еще не завершен или не оплачен по данным API', [
                                    'payment_id' => $paymentId,
                                    'status_from_api' => $paymentInfo['status'] ?? null,
                                    'paid_from_api' => $paymentInfo['paid'] ?? null,
                                    'deposit_status' => $balanceDeposit->status
                                ]);
                            }
                        } catch (\Exception $e) {
                            \Log::error('YooKassa: Ошибка при проверке статуса платежа через API', [
                                'payment_id' => $paymentId,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                            // Продолжаем обработку, возможно это не пополнение баланса
                        }
                    } else {
                        // Показываем все pending для диагностики
                        $allPending = \App\Models\BalanceDeposit::where('status', 'pending')
                            ->whereNotNull('payment_id')
                            ->get();
                        \Log::warning('YooKassa: ✗ Пополнение баланса НЕ найдено для payment_id: ' . $paymentId, [
                            'payment_id' => $paymentId,
                            'searched_in' => $allPending->count() . ' pending записей',
                            'all_payment_ids' => $allPending->pluck('payment_id')->toArray()
                        ]);
                    }
                }
                
                // Проверяем invoice по payment_id
                if ($paymentId && $event === 'payment.succeeded') {
                    // ВАЖНО: Проверяем статус через API YooKassa (как для пополнения баланса)
                    try {
                        $shopId = config('services.yookassa.shop_id');
                        $secretKey = config('services.yookassa.secret_key');
                        $isTest = config('services.yookassa.is_test', false);
                        
                        if (!empty($shopId) && !empty($secretKey)) {
                            $config = new \App\Services\YooKassa\YooKassaConfig($shopId, $secretKey, $isTest);
                            $service = new \App\Services\YooKassa\YooKassaService($config);
                            
                            \Log::info('YooKassa: Проверяем статус платежа через API для invoice', [
                                'payment_id' => $paymentId
                            ]);
                            
                            $paymentInfo = $service->checkPayment($paymentId);
                            
                            \Log::info('YooKassa: Статус платежа из API для invoice', [
                                'status' => $paymentInfo['status'] ?? null,
                                'paid' => $paymentInfo['paid'] ?? null
                            ]);
                            
                            // Проверяем статус ТОЧНО как для пополнения баланса
                            if (($paymentInfo['status'] ?? null) === 'succeeded' && ($paymentInfo['paid'] ?? false) === true) {
                                $invoice = \App\Models\Invoice::where('payment_id', $paymentId)->first();
                                if ($invoice) {
                                    \Log::info('YooKassa: Найден invoice для payment_id: ' . $paymentId, [
                                        'invoice_id' => $invoice->id,
                                        'status' => $invoice->status
                                    ]);
                                    
                                    if ($invoice->status === 'pending') {
                                        $invoice->update([
                                            'status' => 'paid',
                                            'paid_at' => now()
                                        ]);

                                        // Если это счет для подписки, активируем подписку
                                        $subscription = \App\Models\PlanSubscription::where('invoice_id', $invoice->id)->first();
                                        if ($subscription) {
                                            $subscription->update(['status' => 'active']);
                                            
                                            // Обновляем тариф пользователя
                                            $seller = $invoice->seller;
                                            if ($seller) {
                                                $seller->plan_id = $subscription->plan_id;
                                                $seller->save();
                                                
                                                \Log::info('YooKassa: Тариф пользователя обновлен', [
                                                    'seller_id' => $seller->id,
                                                    'plan_id' => $subscription->plan_id,
                                                    'subscription_id' => $subscription->id
                                                ]);
                                            }
                                        }

                                        \Log::info('YooKassa: Invoice оплачен и обработан', [
                                            'invoice_id' => $invoice->id,
                                            'seller_id' => $invoice->seller_id,
                                            'subscription_id' => $subscription->id ?? null
                                        ]);
                                        
                                        return;
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('YooKassa: Ошибка при проверке статуса платежа для invoice', [
                            'payment_id' => $paymentId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                
                // Обрабатываем как обычный заказ (если есть orderId в metadata)
                if (isset($data['object']['metadata']['orderId'])) {
                    $orderId = $data['object']['metadata']['orderId'];
                    \Log::info('YooKassa webhook orderId: ' . $orderId);
                    
                    switch ($event) {
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
                            \Log::warning('Unknown event type: ' . ($event ?? 'N/A'));
                    }
                } else {
                    \Log::warning('orderId not found in metadata and payment not processed as balance/invoice');
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
            
            // Загружаем заказ с дочерними заказами
            $order = Order::with('children')->where('tracking_number', $orderId)->first();
            
            if (!$order) {
                \Log::warning('YooKassa updatePaymentOrderStatus: заказ не найден ' . $orderId);
                return;
            }
            
            \Log::info('YooKassa updatePaymentOrderStatus: заказ найден', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'children_count' => $order->children ? $order->children->count() : 0
            ]);
            
            // Получаем payment_id из webhook
            $paymentId = null;
            if (is_array($data['object'])) {
                $paymentId = $data['object']['id'] ?? null;
            } elseif (is_object($data['object'])) {
                if (method_exists($data['object'], 'getId')) {
                    $paymentId = $data['object']->getId();
                } elseif (isset($data['object']->id)) {
                    $paymentId = $data['object']->id;
                }
            }
            
            \Log::info('YooKassa updatePaymentOrderStatus: обновляем заказ', [
                'order_id' => $order->id,
                'old_order_status' => $order->order_status,
                'old_payment_status' => $order->payment_status,
                'new_order_status' => $orderStatus,
                'new_payment_status' => $paymentStatus,
                'payment_id' => $paymentId
            ]);
            
            // Обновляем payment_intent_info с payment_id
            if ($paymentId) {
                $paymentIntent = \Marvel\Database\Models\PaymentIntent::where('order_id', $order->id)
                    ->where('payment_gateway', PaymentGatewayType::YOOKASSA)
                    ->first();
                
                if ($paymentIntent) {
                    $paymentIntentInfo = $paymentIntent->payment_intent_info ?? [];
                    if (is_string($paymentIntentInfo)) {
                        $paymentIntentInfo = json_decode($paymentIntentInfo, true) ?? [];
                    }
                    
                    $paymentIntentInfo['payment_id'] = $paymentId;
                    $paymentIntent->payment_intent_info = $paymentIntentInfo;
                    $paymentIntent->save();
                    
                    \Log::info('YooKassa updatePaymentOrderStatus: payment_id сохранен в payment_intent_info', [
                        'payment_intent_id' => $paymentIntent->id,
                        'payment_id' => $paymentId
                    ]);
                } else {
                    \Log::warning('YooKassa updatePaymentOrderStatus: PaymentIntent не найден для заказа', [
                        'order_id' => $order->id,
                        'payment_gateway' => PaymentGatewayType::YOOKASSA
                    ]);
                }
            }
            
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