<?php

namespace Marvel\Payments;

use Exception;
use Marvel\Database\Models\Order;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;
use Marvel\Payments\PaymentInterface;
use Marvel\Traits\PaymentTrait;
use App\Services\Tinkoff\TinkoffService;
use App\Services\Tinkoff\TinkoffConfig;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Tinkoff extends Base implements PaymentInterface
{
    use PaymentTrait;

    private TinkoffService $tinkoffService;

    public function __construct()
    {
        parent::__construct();
        
        $config = config('services.tinkoff');
        $this->tinkoffService = new TinkoffService(
            new TinkoffConfig(
                terminal: $config['terminal'],
                password: $config['password'],
                isTest: $config['is_test'],
                apiUrl: $config['api_url']
            )
        );
    }

    public function getIntent(array $data): array
    {
        try {
            $order = Order::findOrFail($data['order_id']);
            $response = $this->tinkoffService->createPayment(
                orderId: $order->tracking_number,
                amount: $order->total,
                description: "Оплата заказа #{$order->tracking_number}",
                successUrl: $data['success_url'],
                failUrl: $data['cancel_url']
            );

            return [
                'client_secret' => null, // Тинькофф не использует client_secret
                'payment_id' => $response['PaymentId'],
                'payment_url' => $response['PaymentURL'],
                'is_redirect' => true
            ];
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function verify(string $id): mixed
    {
        try {
            $response = $this->tinkoffService->checkPayment($id);
            return $response['Status'] === 'CONFIRMED';
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function handleWebHooks(object $request): void
    {
        try {
            $data = $request->all();
            if (isset($data['OrderId'])) {
                $order = Order::where('tracking_number', $data['OrderId'])->first();
                if ($order && $data['Status'] === 'CONFIRMED') {
                    $this->updatePaymentOrderStatus(
                        $request,
                        OrderStatus::PROCESSING,
                        PaymentStatus::SUCCESS
                    );
                }
            }
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function createCustomer(object $request): array
    {
        // Тинькофф не поддерживает сохранение данных клиента
        return [];
    }

    public function attachPaymentMethodToCustomer(string $retrieved_payment_method, object $request): object
    {
        // Тинькофф не поддерживает сохранение способов оплаты
        return (object)[];
    }

    public function detachPaymentMethodToCustomer(string $retrieved_payment_method): object
    {
        // Тинькофф не поддерживает удаление способов оплаты
        return (object)[];
    }

    public function retrievePaymentIntent(string $payment_intent_id): object
    {
        try {
            $response = $this->tinkoffService->checkPayment($payment_intent_id);
            return (object)$response;
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function confirmPaymentIntent(string $payment_intent_id, array $data): object
    {
        try {
            $response = $this->tinkoffService->confirmPayment($payment_intent_id);
            return (object)$response;
        } catch (Exception $e) {
            throw new HttpException(400, $e->getMessage());
        }
    }

    public function setIntent(array $data): array
    {
        // Тинькофф не поддерживает предварительную авторизацию
        return [];
    }

    public function retrievePaymentMethod(string $method_key): object
    {
        // Тинькофф не поддерживает сохранение способов оплаты
        return (object)[];
    }
} 