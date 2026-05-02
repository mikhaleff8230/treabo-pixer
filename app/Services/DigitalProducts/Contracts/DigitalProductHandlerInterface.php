<?php

namespace App\Services\DigitalProducts\Contracts;

use Marvel\Database\Models\Product;

interface DigitalProductHandlerInterface
{
    /**
     * @return array{type: string, payload: array<string, mixed>}
     */
    public function handle(Product $product, int $userId): array;
}
