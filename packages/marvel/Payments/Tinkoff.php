<?php

namespace Marvel\Payments;

use Illuminate\Support\Facades\Http;

class Tinkoff implements PaymentInterface
{
    public function execute($data)
    {
        // Пример вызова API Тинькофф с тестовыми данными
        $response = Http::post('https://securepay.tinkoff.ru/v2/Init', [
            'TerminalKey' => config('services.tinkoff.terminal_key'),
            'Password' => config('services.tinkoff.password'),
            'Amount' => $data['amount'] * 100, // копейки
            'OrderId' => $data['order_id'],
            'Description' => 'Оплата заказа #' . $data['order_id'],
            'SuccessURL' => $data['success_url'],
            'FailURL' => $data['fail_url'],
        ]);

        if ($response->ok() && isset($response['PaymentURL'])) {
            return [
                'success' => true,
                'url' => $response['PaymentURL'],
            ];
        }

        return [
            'success' => false,
            'message' => $response->json('Message') ?? 'Ошибка инициализации платежа',
        ];
    }
}
