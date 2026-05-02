<?php

namespace App\Services\Tinkoff\Entities;

use App\Services\Tinkoff\Enums\PaymentStatusEnum;

class PaymentEntity
{
    public function __construct(
        private readonly string $paymentId,
        private readonly string $orderId,
        private readonly int $amount,
        private readonly PaymentStatusEnum $status,
        private readonly ?string $paymentUrl = null,
        private readonly array $raw = []
    ) {}

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount / 100;
    }

    public function getStatus(): PaymentStatusEnum
    {
        return $this->status;
    }

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function getRawData(): array
    {
        return $this->raw;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            paymentId: $data['PaymentId'],
            orderId: $data['OrderId'],
            amount: $data['Amount'],
            status: PaymentStatusEnum::from($data['Status']),
            paymentUrl: $data['PaymentURL'] ?? null,
            raw: $data
        );
    }
}