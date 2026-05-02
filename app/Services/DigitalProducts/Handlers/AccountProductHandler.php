<?php

namespace App\Services\DigitalProducts\Handlers;

use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use Marvel\Database\Models\Product;

class AccountProductHandler implements DigitalProductHandlerInterface
{
    public function handle(Product $product, int $userId): array
    {
        return [
            'type' => 'account',
            'payload' => [
                'account_data' => $product->account_data,
            ],
        ];
    }
}
