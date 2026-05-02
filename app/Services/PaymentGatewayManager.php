<?php

namespace App\Services;

use App\Services\Tinkoff\TinkoffService;
use App\Services\Tinkoff\TinkoffConfig;
use App\Services\YooKassa\YooKassaService;
use App\Services\YooKassa\YooKassaConfig;
use Marvel\Database\Models\Settings;

class PaymentGatewayManager
{
    public const TINKOFF = 'tinkoff';
    public const YOOKASSA = 'yookassa';
    
    private string $activeGateway;
    private Settings $settings;

    public function __construct()
    {
        $this->settings = Settings::first();
        $this->activeGateway = $this->settings->active_payment_gateway ?? self::YOOKASSA;
    }

    /**
     * Получить активный платежный шлюз
     */
    public function getActiveGateway(): string
    {
        return $this->activeGateway;
    }

    /**
     * Установить активный платежный шлюз
     */
    public function setActiveGateway(string $gateway): void
    {
        if (!in_array($gateway, [self::TINKOFF, self::YOOKASSA])) {
            throw new \InvalidArgumentException("Неподдерживаемый платежный шлюз: {$gateway}");
        }

        $this->activeGateway = $gateway;
        $this->settings->active_payment_gateway = $gateway;
        $this->settings->save();
    }

    /**
     * Получить сервис для активного шлюза
     */
    public function getService(): TinkoffService|YooKassaService
    {
        return match ($this->activeGateway) {
            self::TINKOFF => $this->getTinkoffService(),
            self::YOOKASSA => $this->getYooKassaService(),
            default => throw new \RuntimeException("Активный шлюз не настроен: {$this->activeGateway}")
        };
    }

    /**
     * Получить сервис Т-банка
     */
    public function getTinkoffService(): TinkoffService
    {
        $config = config('services.tinkoff');
        return new TinkoffService(
            new TinkoffConfig(
                terminal: $config['terminal'],
                password: $config['password'],
                isTest: $config['is_test'],
                apiUrl: $config['api_url']
            )
        );
    }

    /**
     * Получить сервис ЮKassa
     */
    public function getYooKassaService(): YooKassaService
    {
        $config = config('services.yookassa');
        return new YooKassaService(
            new YooKassaConfig(
                shopId: $config['shop_id'],
                secretKey: $config['secret_key'],
                isTest: $config['is_test']
            )
        );
    }

    /**
     * Создать платеж через активный шлюз
     */
    public function createPayment(
        string $orderId,
        float $amount,
        string $description,
        string $successUrl,
        string $failUrl,
        array $receipt = null,
        array $additionalData = []
    ): array {
        $service = $this->getService();

        if ($this->activeGateway === self::TINKOFF) {
            return $service->createPayment(
                $orderId,
                $amount,
                $description,
                $successUrl,
                $failUrl,
                $receipt,
                $additionalData
            );
        } else {
            return $service->createPayment(
                $orderId,
                $amount,
                $description,
                $successUrl,
                $failUrl,
                $receipt
            );
        }
    }

    /**
     * Создать платеж для виджета (только ЮKassa)
     */
    public function createPaymentForWidget(
        string $orderId,
        float $amount,
        string $description,
        string $returnUrl,
        array $receipt = null
    ): array {
        if ($this->activeGateway !== self::YOOKASSA) {
            throw new \RuntimeException("Виджет поддерживается только для ЮKassa");
        }

        $service = $this->getYooKassaService();
        return $service->createPaymentForWidget(
            $orderId,
            $amount,
            $description,
            $returnUrl,
            $receipt
        );
    }

    /**
     * Проверить статус платежа
     */
    public function checkPayment(string $paymentId): array
    {
        $service = $this->getService();
        return $service->checkPayment($paymentId);
    }

    /**
     * Отменить платеж
     */
    public function cancelPayment(string $paymentId): array
    {
        $service = $this->getService();
        return $service->cancelPayment($paymentId);
    }

    /**
     * Возврат платежа
     */
    public function refundPayment(string $paymentId, float $amount, array $receipt = null): array
    {
        $service = $this->getService();
        return $service->refundPayment($paymentId, $amount, $receipt);
    }

    /**
     * Получить доступные шлюзы
     */
    public function getAvailableGateways(): array
    {
        return [
            self::TINKOFF => 'Т-банк',
            self::YOOKASSA => 'ЮKassa'
        ];
    }

    /**
     * Проверить, активен ли шлюз
     */
    public function isGatewayActive(string $gateway): bool
    {
        return $this->activeGateway === $gateway;
    }

    /**
     * Получить настройки для фронтенда
     */
    public function getFrontendSettings(): array
    {
        return [
            'active_gateway' => $this->activeGateway,
            'available_gateways' => $this->getAvailableGateways(),
            'tinkoff_enabled' => $this->isGatewayActive(self::TINKOFF),
            'yookassa_enabled' => $this->isGatewayActive(self::YOOKASSA),
        ];
    }
}

