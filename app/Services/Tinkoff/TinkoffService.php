<?php

namespace App\Services\Tinkoff;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class TinkoffService
{
    public function __construct(
        private readonly TinkoffConfig $config
    ) {}

    /**
     * Инициализация платежа
     */
    public function createPayment(
        string $orderId,
        float $amount,
        string $description,
        string $successUrl = null,
        string $failUrl = null,
        array $receipt = null,
        array $additionalData = []
    ): array {
        $data = array_filter([
            'TerminalKey' => $this->config->getTerminal(),
            'Amount' => (int)($amount * 100), // конвертируем в копейки
            'OrderId' => $orderId,
            'Description' => $description,
            'SuccessURL' => $successUrl,
            'FailURL' => $failUrl,
            'Receipt' => $receipt,
            'DATA' => $additionalData
        ]);

        $data['Token'] = $this->generateToken($data);

        $response = Http::post($this->config->getApiUrl() . '/Init', $data);

        if (!$response->successful()) {
            throw new RuntimeException('Ошибка при создании платежа: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Проверка статуса платежа
     */
    public function checkPayment(string $paymentId): array
    {
        $data = [
            'TerminalKey' => $this->config->getTerminal(),
            'PaymentId' => $paymentId
        ];

        $data['Token'] = $this->generateToken($data);

        $response = Http::post($this->config->getApiUrl() . '/GetState', $data);

        if (!$response->successful()) {
            throw new RuntimeException('Ошибка при проверке статуса платежа: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Подтверждение платежа
     */
    public function confirmPayment(string $paymentId, float $amount = null): array
    {
        $data = [
            'TerminalKey' => $this->config->getTerminal(),
            'PaymentId' => $paymentId
        ];

        if ($amount !== null) {
            $data['Amount'] = (int)($amount * 100);
        }

        $data['Token'] = $this->generateToken($data);

        $response = Http::post($this->config->getApiUrl() . '/Confirm', $data);

        if (!$response->successful()) {
            throw new RuntimeException('Ошибка при подтверждении платежа: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Отмена платежа
     */
    public function cancelPayment(string $paymentId): array
    {
        $data = [
            'TerminalKey' => $this->config->getTerminal(),
            'PaymentId' => $paymentId
        ];

        $data['Token'] = $this->generateToken($data);

        $response = Http::post($this->config->getApiUrl() . '/Cancel', $data);

        if (!$response->successful()) {
            throw new RuntimeException('Ошибка при отмене платежа: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Возврат платежа
     */
    public function refundPayment(string $paymentId, float $amount): array
    {
        $data = [
            'TerminalKey' => $this->config->getTerminal(),
            'PaymentId' => $paymentId,
            'Amount' => (int)($amount * 100)
        ];

        $data['Token'] = $this->generateToken($data);

        $response = Http::post($this->config->getApiUrl() . '/Refund', $data);

        if (!$response->successful()) {
            throw new RuntimeException('Ошибка при возврате платежа: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Генерация токена для запроса
     */
    private function generateToken(array $data): string
    {
        // Сортируем значения по алфавиту
        $values = array_filter($data, function ($key) {
            return !in_array($key, ['Token', 'Receipt', 'DATA']);
        }, ARRAY_FILTER_USE_KEY);
        
        ksort($values);
        
        // Формируем строку значений
        $values = implode('', array_values($values));
        
        // Добавляем пароль в конец
        $values .= $this->config->getPassword();
        
        // Возвращаем SHA-256 хеш
        return hash('sha256', $values);
    }
}