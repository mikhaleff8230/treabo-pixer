<?php

namespace App\Services\DigitalProducts\Handlers;

use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use Marvel\Database\Models\Product;

class PromptProductHandler implements DigitalProductHandlerInterface
{
    public function handle(Product $product, int $userId): array
    {
        return [
            'type' => 'prompt',
            'payload' => [
                'prompt_text' => $product->prompt_text,
            ],
        ];
    }
}
