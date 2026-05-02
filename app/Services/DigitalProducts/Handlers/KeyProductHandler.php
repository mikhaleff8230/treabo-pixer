<?php

namespace App\Services\DigitalProducts\Handlers;

use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductKey;

class KeyProductHandler implements DigitalProductHandlerInterface
{
    public function handle(Product $product, int $userId): array
    {
        $key = ProductKey::where('product_id', $product->id)->whereNull('used_by')->first();
        if (!$key) {
            $key = ProductKey::where('product_id', $product->id)
                ->where('used_by', $userId)
                ->firstOrFail();
        } else {
            $key->update([
                'used_by' => $userId,
                'used_at' => now(),
            ]);
        }

        return [
            'type' => 'key',
            'payload' => [
                'license_key' => $key->key,
            ],
        ];
    }
}
