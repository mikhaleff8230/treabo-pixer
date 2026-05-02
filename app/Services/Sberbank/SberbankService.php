<?php

namespace App\Services\Sberbank;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SberbankService
{
    public function __construct(
        private readonly SberbankConfig $config
    ) {}

    public function createPayment(int $orderId, float $amount, string $description = ''): array
    {
        $response = Http::asForm()->post($this->config->getApiUrl() . '/payment/rest/register.do', [
            'userName' => $this->config->getUsername(),
            'password' => $this->config->getPassword(),
            'orderNumber' => $orderId,
            'amount' => $amount * 100, // Convert to kopeks
            'returnUrl' => $this->config->getSuccessUrl(),
            'failUrl' => $this->config->getFailUrl(),
            'description' => $description,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to create Sberbank payment: ' . $response->body());
        }

        return $response->json();
    }

    public function checkPayment(string $orderId): array
    {
        $response = Http::asForm()->post($this->config->getApiUrl() . '/payment/rest/getOrderStatus.do', [
            'userName' => $this->config->getUsername(),
            'password' => $this->config->getPassword(),
            'orderNumber' => $orderId,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to check Sberbank payment status: ' . $response->body());
        }

        return $response->json();
    }

    public function cancelPayment(string $orderId): array
    {
        $response = Http::asForm()->post($this->config->getApiUrl() . '/payment/rest/reverse.do', [
            'userName' => $this->config->getUsername(),
            'password' => $this->config->getPassword(),
            'orderNumber' => $orderId,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to cancel Sberbank payment: ' . $response->body());
        }

        return $response->json();
    }

    public function refundPayment(string $orderId, float $amount): array
    {
        $response = Http::asForm()->post($this->config->getApiUrl() . '/payment/rest/refund.do', [
            'userName' => $this->config->getUsername(),
            'password' => $this->config->getPassword(),
            'orderNumber' => $orderId,
            'amount' => $amount * 100, // Convert to kopeks
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to refund Sberbank payment: ' . $response->body());
        }

        return $response->json();
    }
} 