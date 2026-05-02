<?php

namespace App\Services\DigitalProducts\Handlers;

use App\Services\Courses\CourseSubscriptionService;
use App\Services\DigitalProducts\Contracts\DigitalProductHandlerInterface;
use Marvel\Database\Models\Product;

class SubscriptionProductHandler implements DigitalProductHandlerInterface
{
    public function handle(Product $product, int $userId): array
    {
        $subscription = app(CourseSubscriptionService::class)->createOrExtendForUser($userId, $product);

        return [
            'type' => 'subscription',
            'payload' => [
                'expires_at' => $subscription->expires_at->toIso8601String(),
                'starts_at' => $subscription->starts_at?->toIso8601String(),
            ],
        ];
    }
}
