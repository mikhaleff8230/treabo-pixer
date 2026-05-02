<?php

namespace Marvel\Traits;

use Marvel\Enums\PaymentStatus;
use Marvel\Enums\PaymentGatewayType;
use Marvel\Enums\OrderStatus as OrderStatusEnum;

trait OrderManagementTrait
{
    use OrderStatusManagerWithPaymentTrait;

    /**
     * changeOrderStatus
     *
     * @param  mixed $order
     * @param  mixed $status
     * @return void
     */
    public function changeOrderStatus($order, $status)
    {
        $prev_order_status = $order->order_status;
        $order->order_status = $status;
        $new_order_status = $order->order_status;

        if ($prev_order_status !== $new_order_status) {
            $payment_gateway_type = isset($order->payment_gateway) ? $order->payment_gateway : PaymentGatewayType::CASH_ON_DELIVERY;
            if ( !in_array($payment_gateway_type, [PaymentGatewayType::CASH, PaymentGatewayType::CASH_ON_DELIVERY]) ) {
                if ($order->payment_status === PaymentStatus::SUCCESS)
                    $this->manageVendorBalance($order, $new_order_status, $prev_order_status);
                $this->orderStatusManagementOnPayment($order, $new_order_status, $order->payment_status);
            } else {
                $this->manageVendorBalance($order, $new_order_status, $prev_order_status);
                $this->orderStatusManagementOnCOD($order, $prev_order_status, $new_order_status);
            }
        }
        $order->save();

        // Обрабатываем дочерние заказы при отмене родительского заказа
        if ($order->order_status === OrderStatusEnum::CANCELLED) {
            // Убеждаемся, что children загружены
            if (!$order->relationLoaded('children')) {
                $order->load('children');
            }
            
            // Отменяем все дочерние заказы
            if ($order->children && $order->children->count() > 0) {
                foreach ($order->children as $child_order) {
                    // Пропускаем уже отмененные заказы
                    if ($child_order->order_status === OrderStatusEnum::CANCELLED) {
                        continue;
                    }
                    
                    // Отменяем дочерний заказ (рекурсивно вызываем changeOrderStatus)
                    $child_order->order_status = $status;
                    $this->changeOrderStatus($child_order, $status);
                }
            }
        }
        return $order;
    }
}
