<?php

namespace App\Services\YooKassa;

class YooKassaConfig
{
    public function __construct(
        private readonly string $shopId,
        private readonly string $secretKey,
        private readonly bool $isTest = false
    ) {}

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    public function isTest(): bool
    {
        return $this->isTest;
    }
} 