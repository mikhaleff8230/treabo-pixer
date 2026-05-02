<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\OrderedFile;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Purchase;
use Marvel\Database\Models\User;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;

class DigitalAccessGrantService
{
    /**
     * @return array{granted: bool, via?: string, reason?: string}
     */
    public function grant(int $userId, int $productId, ?string $trackingNumber): array
    {
        if (Purchase::where('user_id', $userId)->where('product_id', $productId)->exists()) {
            return ['granted' => true, 'via' => 'purchase', 'reason' => null];
        }

        if (!$trackingNumber) {
            return ['granted' => false, 'reason' => 'no_tracking_and_no_purchase'];
        }

        $order = $this->findOrderForDigitalAccess($userId, $trackingNumber);

        if ($order && $this->orderContainsProduct($order, $productId)) {
            $this->ensurePurchaseAndOrderedFile($order, $userId, $productId);
            return ['granted' => true, 'via' => 'order', 'reason' => null];
        }

        if ($this->hasOrderedFileEntitlement($userId, $productId, $trackingNumber)) {
            $orderForPurchase = $order
                ?? Order::where('tracking_number', $trackingNumber)
                    ->where('customer_id', $userId)
                    ->first();

            if ($orderForPurchase) {
                $this->ensurePurchaseAndOrderedFile($orderForPurchase, $userId, $productId);
            } else {
                Log::warning('digital_access.ordered_file_without_order_row', [
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'tracking_number' => $trackingNumber,
                ]);
            }

            return ['granted' => true, 'via' => 'ordered_file', 'reason' => null];
        }

        return ['granted' => false, 'reason' => 'no_proof_of_purchase'];
    }

    /**
     * Заказ по tracking_number: владелец или «гость» с совпадением email и оплаченным заказом
     * (CustomYooKassa и др. могут оставить customer_id = null при чекауте без сессии).
     */
    private function findOrderForDigitalAccess(int $userId, string $trackingNumber): ?Order
    {
        $order = Order::where('tracking_number', $trackingNumber)
            ->whereNotIn('order_status', [OrderStatus::CANCELLED])
            ->with(['products.digital_file', 'children.products.digital_file', 'parent_order.products.digital_file'])
            ->first();

        if (!$order) {
            return null;
        }

        $this->healPaymentStatusIfOrderCompleted($order);
        $order = $order->fresh([
            'products.digital_file',
            'children.products.digital_file',
            'parent_order.products.digital_file',
        ]);
        if (!$order) {
            return null;
        }

        if ($order->customer_id !== null && (int) $order->customer_id !== (int) $userId) {
            return null;
        }

        if ($order->customer_id === null) {
            $bound = $this->attachGuestOrderToBuyerIfEligible($order, $userId)
                || $this->attachGuestOrderWhenDigitallyFulfilled($order, $userId);
            if (!$bound) {
                return null;
            }

            return $order->fresh([
                'products.digital_file',
                'children.products.digital_file',
                'parent_order.products.digital_file',
            ]);
        }

        return $order;
    }

    /**
     * Заказ считается оплаченным/закрытым для выдачи цифровки при типичных рассинхронах
     * (order-completed + payment-pending, или paid_total >= total).
     */
    private function orderIsDigitallyFulfilled(Order $order): bool
    {
        if ($order->order_status === OrderStatus::COMPLETED) {
            return true;
        }
        if ($order->payment_status === PaymentStatus::SUCCESS) {
            return true;
        }

        $total = (float) ($order->total ?? $order->amount ?? 0);
        $paid = (float) ($order->paid_total ?? 0);
        if ($total > 0 && $paid + 0.0001 >= $total) {
            return true;
        }

        return false;
    }

    /**
     * Привязка гостевого заказа к залогиненному пользователю без проверки email,
     * только если заказ уже фактически закрыт/оплачен (иначе риск перехвата по tracking).
     */
    private function attachGuestOrderWhenDigitallyFulfilled(Order $order, int $userId): bool
    {
        if (!User::query()->whereKey($userId)->exists()) {
            return false;
        }
        if (!$this->orderIsDigitallyFulfilled($order)) {
            return false;
        }

        $this->assignCustomerToOrderTree($order, $userId);

        Log::warning('digital_access.guest_order_bound_fulfilled_trust', [
            'user_id' => $userId,
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
        ]);

        return true;
    }

    private function assignCustomerToOrderTree(Order $order, int $userId): void
    {
        $rootId = $order->parent_id ? (int) $order->parent_id : (int) $order->id;

        DB::transaction(function () use ($rootId, $userId) {
            Order::query()->whereKey($rootId)->update(['customer_id' => $userId]);
            Order::query()->where('parent_id', $rootId)->update(['customer_id' => $userId]);
        });
    }

    /**
     * Однократно проставляет customer_id корню заказа и дочерним, если email в заказе = email пользователя и оплата прошла.
     */
    private function attachGuestOrderToBuyerIfEligible(Order $order, int $userId): bool
    {
        $user = User::query()->find($userId);
        if (!$user || $user->email === null || trim((string) $user->email) === '') {
            return false;
        }

        $orderEmail = $this->extractOrderBuyerEmail($order);
        if ($orderEmail === null || strcasecmp(trim($orderEmail), trim((string) $user->email)) !== 0) {
            Log::info('digital_access.guest_order_email_mismatch', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'user_id' => $userId,
            ]);

            return false;
        }

        if (!$this->orderIsDigitallyFulfilled($order)) {
            Log::info('digital_access.guest_order_not_paid', [
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
            ]);

            return false;
        }

        $this->assignCustomerToOrderTree($order, $userId);

        Log::info('digital_access.guest_order_bound_to_user', [
            'root_order_id' => $order->parent_id ? (int) $order->parent_id : (int) $order->id,
            'user_id' => $userId,
            'tracking_number' => $order->tracking_number,
        ]);

        return true;
    }

    private function extractOrderBuyerEmail(Order $order): ?string
    {
        $fromShipping = $this->extractEmailFromShippingAddress($order->shipping_address);
        if ($fromShipping !== null) {
            return $fromShipping;
        }

        return $this->extractEmailFromShippingAddress($order->billing_address ?? null);
    }

    private function extractEmailFromShippingAddress(mixed $shippingAddress): ?string
    {
        $addr = $shippingAddress;
        if (is_string($addr)) {
            $decoded = json_decode($addr, true);
            $addr = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($addr)) {
            return null;
        }
        $email = $addr['email'] ?? null;
        if (!is_string($email) || trim($email) === '') {
            return null;
        }

        return $email;
    }

    public function ensurePurchaseAndOrderedFile(Order $order, int $userId, int $productId): void
    {
        $this->healPaymentStatusIfOrderCompleted($order);

        Purchase::firstOrCreate([
            'user_id' => $userId,
            'product_id' => $productId,
            'order_id' => $order->id,
        ]);

        $product = Product::with('digital_file')->find($productId);
        if ($product?->digital_file?->id) {
            OrderedFile::firstOrCreate([
                'tracking_number' => $order->tracking_number,
                'customer_id' => $userId,
                'digital_file_id' => $product->digital_file->id,
            ], [
                'purchase_key' => Str::random(16),
            ]);
        }
    }

    /**
     * Чинит типичный баг: order_status = completed, payment_status застыл в pending.
     */
    private function healPaymentStatusIfOrderCompleted(Order $order): void
    {
        if ($order->order_status !== OrderStatus::COMPLETED) {
            return;
        }
        if ($order->payment_status === PaymentStatus::SUCCESS) {
            return;
        }

        $rootId = $order->parent_id ? (int) $order->parent_id : (int) $order->id;

        Order::query()->whereKey($rootId)->update(['payment_status' => PaymentStatus::SUCCESS]);
        Order::query()->where('parent_id', $rootId)->update(['payment_status' => PaymentStatus::SUCCESS]);

        Log::info('digital_access.healed_payment_status_for_completed_order', [
            'root_order_id' => $rootId,
        ]);
    }

    public function orderContainsProduct(Order $order, int $productId): bool
    {
        $requestedProduct = Product::with('digital_file')->find($productId);
        $requestedDigitalFileId = $requestedProduct?->digital_file?->id;

        $containsDirect = $order->products->contains(function ($p) use ($productId, $requestedDigitalFileId) {
            if ((int) $p->id === (int) $productId) {
                return true;
            }
            if ($requestedDigitalFileId && (int) ($p->digital_file?->id ?? 0) === (int) $requestedDigitalFileId) {
                return true;
            }
            return false;
        });
        if ($containsDirect) {
            return true;
        }

        foreach ($order->children as $child) {
            $containsInChild = $child->products->contains(function ($p) use ($productId, $requestedDigitalFileId) {
                if ((int) $p->id === (int) $productId) {
                    return true;
                }
                if ($requestedDigitalFileId && (int) ($p->digital_file?->id ?? 0) === (int) $requestedDigitalFileId) {
                    return true;
                }
                return false;
            });
            if ($containsInChild) {
                return true;
            }
        }

        if ($order->parent_order) {
            $containsInParent = $order->parent_order->products->contains(function ($p) use ($productId, $requestedDigitalFileId) {
                if ((int) $p->id === (int) $productId) {
                    return true;
                }
                if ($requestedDigitalFileId && (int) ($p->digital_file?->id ?? 0) === (int) $requestedDigitalFileId) {
                    return true;
                }
                return false;
            });
            if ($containsInParent) {
                return true;
            }
        }

        return false;
    }

    public function hasOrderedFileEntitlement(int $userId, int $productId, string $trackingNumber): bool
    {
        $product = Product::with('digital_file')->find($productId);
        $digitalFileId = $product?->digital_file?->id;

        return OrderedFile::where('tracking_number', $trackingNumber)
            ->where('customer_id', $userId)
            ->where(function ($q) use ($digitalFileId, $productId) {
                if ($digitalFileId) {
                    $q->where('digital_file_id', $digitalFileId);
                }
                $q->orWhere('digital_file_id', $productId);
            })
            ->exists();
    }
}
