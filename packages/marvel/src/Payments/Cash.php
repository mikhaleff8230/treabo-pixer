<?php

namespace Marvel\Payments;

use Marvel\Payments\PaymentInterface;

class Cash implements PaymentInterface
{
    public function execute($request)
    {
        // Никакой оплаты не происходит — просто подтверждение
        return [
            'success' => true,
            'message' => 'Оплата наличными при получении.',
        ];
    }

    public function getIntent(array $data): array { return [
        'success' => true,
        'message' => 'Оплата наличными при получении.',
    ]; }
    public function verify(string $id): mixed { return true; }
    public function handleWebHooks(object $request): void {}
    public function createCustomer(object $request): array { return []; }
    public function attachPaymentMethodToCustomer(string $retrieved_payment_method, object $request): object { return (object)[]; }
    public function detachPaymentMethodToCustomer(string $retrieved_payment_method): object { return (object)[]; }
    public function retrievePaymentIntent(string $payment_intent_id): object { return (object)[]; }
    public function confirmPaymentIntent(string $payment_intent_id, array $data): object { return (object)[]; }
    public function setIntent(array $data): array { return []; }
    public function retrievePaymentMethod(string $method_key): object { return (object)[]; }

    public function charge($request) {
        // логика оплаты
    }

    public function refund($request) {
        // логика возврата
    }
}
