<?php

namespace App\Services\Tinkoff\Actions;

use App\Services\Tinkoff\Entities\PaymentEntity;
use App\Services\Tinkoff\Exceptions\TinkoffException;
use App\Services\Tinkoff\TinkoffService;

class CreatePaymentAction
{
    public function __construct(
        private readonly TinkoffService $service
    ) {}

    public static function make(TinkoffService $service): self
    {
        return new self($service);
    }

    public function run(CreatePaymentData $data): PaymentEntity
    {
        try {
            $response = $this->service->createPayment(
                orderId: $data->getOrderId(),
                amount: $data->getAmount(),
                description: $data->getDescription(),
                successUrl: $data->getSuccessUrl(),
                failUrl: $data->getFailUrl(),
                receipt: $data->getReceipt(),
                additionalData: $data->getAdditionalData()
            );

            if (!isset($response['Success']) || !$response['Success']) {
                throw new TinkoffException(
                    message: $response['Message'] ?? 'Неизвестная ошибка при создании платежа',
                    errorData: $response
                );
            }

            return PaymentEntity::fromArray($response);
        } catch (\Exception $e) {
            if ($e instanceof TinkoffException) {
                throw $e;
            }

            throw new TinkoffException(
                message: 'Ошибка при создании платежа: ' . $e->getMessage(),
                errorData: ['exception' => $e]
            );
        }
    }
} 