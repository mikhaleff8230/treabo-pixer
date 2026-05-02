<?php

namespace App\Services\Sberbank;

class SberbankConfig
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
        private readonly string $apiUrl,
        private readonly string $successUrl,
        private readonly string $failUrl,
        private readonly bool $testMode = true
    ) {}

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getApiUrl(): string
    {
        return $this->apiUrl;
    }

    public function getSuccessUrl(): string
    {
        return $this->successUrl;
    }

    public function getFailUrl(): string
    {
        return $this->failUrl;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }
} 