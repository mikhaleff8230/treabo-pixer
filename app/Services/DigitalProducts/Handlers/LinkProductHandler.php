<?php

namespace App\Services\DigitalProducts\Handlers;

use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use Marvel\Database\Models\Product;

class LinkProductHandler implements DigitalProductHandlerInterface
{
    public function handle(Product $product, int $userId): array
    {
        return [
            'type' => 'link',
            'payload' => [
                'external_url' => $product->external_url,
            ],
        ];
    }
}
