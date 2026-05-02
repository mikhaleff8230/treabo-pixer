<?php

namespace App\Services\Courses;

use Marvel\Database\Models\Product;
use Marvel\Database\Models\ProductSubscription;
use Marvel\Exceptions\MarvelException;

class CourseSubscriptionService
{
    public function subscriptionPeriodDays(Product $product): int
    {
        $d = (int) ($product->duration_days ?? 0);
        if ($d > 0) {
            return $d;
        }

        return (int) ($product->subscription_days ?? 0);
    }

    /**
     * Активная подписка не трогаем; иначе создаём или обновляем период (повторная выдача при наличии grant).
     */
    public function createOrExtendForUser(int $userId, Product $product): ProductSubscription
    {
        $days = $this->subscriptionPeriodDays($product);
        if ($days < 1) {
            throw new MarvelException(NOT_FOUND);
        }

        $active = ProductSubscription::query()
            ->where('user_id', $userId)
            ->where('product_id', $product->id)
            ->active()
            ->orderByDesc('expires_at')
            ->first();

        if ($active) {
            return $active;
        }

        $startsAt = now();
        $expiresAt = $startsAt->copy()->addDays($days);

        $latest = ProductSubscription::query()
            ->where('user_id', $userId)
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->first();

        if ($latest) {
            $latest->update([
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => ProductSubscription::STATUS_ACTIVE,
            ]);

            return $latest->fresh();
        }

        return ProductSubscription::create([
            'user_id' => $userId,
            'product_id' => $product->id,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'status' => ProductSubscription::STATUS_ACTIVE,
        ]);
    }

    public function isActive(ProductSubscription $subscription): bool
    {
        return $subscription->isActive();
    }
}
