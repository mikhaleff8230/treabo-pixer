<?php

namespace App\Services\Tinkoff\Actions;

class CreatePaymentData
{
    public function __construct(
        private readonly string $orderId,
        private readonly float $amount,
        private readonly string $description,
        private readonly ?string $successUrl = null,
        private readonly ?string $failUrl = null,
        private readonly ?array $receipt = null,
        private readonly array $additionalData = []
    ) {}

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSuccessUrl(): ?string
    {
        return $this->successUrl;
    }

    public function getFailUrl(): ?string
    {
        return $this->failUrl;
    }

    public function getReceipt(): ?array
    {
        return $this->receipt;
    }

    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }
} 