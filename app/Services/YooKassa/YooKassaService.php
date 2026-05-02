<?php

namespace App\Services\YooKassa;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use YooKassa\Client;

class YooKassaService
{
    private Client $client;
    private const API_URL = 'https://api.yookassa.ru/v3';

    public function __construct(
        private readonly YooKassaConfig $config
    ) {
        $this->client = new Client();
        $this->client->setAuth($config->getShopId(), $config->getSecretKey());
    }

    /**
     * Создание платежа с редиректом
     */
    public function createPayment(
        string $orderId,
        float $amount,
        string $description,
        string $successUrl,
        string $failUrl,
        array $receipt = null
    ): array {
        try {
            $paymentData = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'RUB'
                ],
                'confirmation' => [
                    'type' => 'redirect',
                    'return_url' => $successUrl
                ],
                'capture' => true,
                'description' => $description,
                'metadata' => [
                    'orderId' => $orderId
                ]
            ];

            // Добавляем данные чека, если они предоставлены
            if ($receipt !== null) {
                $paymentData['receipt'] = $receipt;
            }

            $payment = $this->client->createPayment($paymentData, uniqid('', true));

            return [
                'id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'paid' => $payment->getPaid(),
                'payment_url' => $payment->getConfirmation()->getConfirmationUrl()
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Ошибка при создании платежа: ' . $e->getMessage());
        }
    }

    /**
     * Создание платежа для виджета (embedded)
     */
    public function createPaymentForWidget(
        string $orderId,
        float $amount,
        string $description,
        string $returnUrl,
        array $receipt = null
    ): array {
        try {
            $paymentData = [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'RUB'
                ],
                'confirmation' => [
                    'type' => 'embedded'
                ],
                'capture' => true,
                'description' => $description,
                'metadata' => [
                    'orderId' => $orderId
                ]
            ];

            // Добавляем данные чека, если они предоставлены
            if ($receipt !== null) {
                $paymentData['receipt'] = $receipt;
            }

            $payment = $this->client->createPayment($paymentData, uniqid('', true));

            return [
                'id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'paid' => $payment->getPaid(),
                'confirmation_token' => $payment->getConfirmation()->getConfirmationToken()
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Ошибка при создании платежа для виджета: ' . $e->getMessage());
        }
    }

    /**
     * Проверка статуса платежа
     */
    public function checkPayment(string $paymentId): array
    {
        try {
            $payment = $this->client->getPaymentInfo($paymentId);
            
            $result = [
                'id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'paid' => $payment->getPaid(),
                'amount' => $payment->getAmount()->getValue(),
                'currency' => $payment->getAmount()->getCurrency(),
            ];
            
            // Проверяем наличие receipt (метод может отсутствовать в некоторых версиях SDK)
            if (method_exists($payment, 'getReceipt')) {
                $receipt = $payment->getReceipt();
                $result['receipt'] = $receipt ? (method_exists($receipt, 'toArray') ? $receipt->toArray() : null) : null;
            } else {
                $result['receipt'] = null;
            }
            
            return $result;
        } catch (\Exception $e) {
            throw new RuntimeException('Ошибка при проверке статуса платежа: ' . $e->getMessage());
        }
    }

    /**
     * Отмена платежа
     */
    public function cancelPayment(string $paymentId): array
    {
        try {
            $payment = $this->client->cancelPayment($paymentId);
            
            return [
                'id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'paid' => $payment->getPaid()
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Ошибка при отмене платежа: ' . $e->getMessage());
        }
    }

    /**
     * Возврат платежа
     */
    public function refundPayment(string $paymentId, float $amount, array $receipt = null): array
    {
        try {
            $refundData = [
                'payment_id' => $paymentId,
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency' => 'RUB'
                ]
            ];

            // Добавляем данные чека возврата, если они предоставлены
            if ($receipt !== null) {
                $refundData['receipt'] = $receipt;
            }

            $refund = $this->client->createRefund($refundData, uniqid('', true));
            
            return [
                'id' => $refund->getId(),
                'status' => $refund->getStatus(),
                'amount' => $refund->getAmount()->getValue(),
                'receipt' => $refund->getReceipt() ? $refund->getReceipt()->toArray() : null
            ];
        } catch (\Exception $e) {
            throw new RuntimeException('Ошибка при возврате платежа: ' . $e->getMessage());
        }
    }
} 