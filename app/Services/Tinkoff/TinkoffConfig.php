<?php

namespace App\Services\Tinkoff;

class TinkoffConfig
{
    public function __construct(
        private readonly string $terminal,
        private readonly string $password,
        private readonly bool $isTest = false,
        private readonly string $apiUrl = 'https://securepay.tinkoff.ru/v2'
    ) {}

    public function getTerminal(): string
    {
        return $this->terminal;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function isTest(): bool
    {
        return $this->isTest;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }
}