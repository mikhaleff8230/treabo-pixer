<?php

namespace Marvel\Listeners;

use Marvel\Events\OrderCreated;
use Marvel\Services\ShipmentService;
use Marvel\Database\Models\Shipment;
use Illuminate\Support\Facades\Log;
use Exception;

class CreateShipmentForOrder
{
    private ShipmentService $shipmentService;

    /**
     * Create the event listener.
     */
    public function __construct(ShipmentService $shipmentService)
    {
        $this->shipmentService = $shipmentService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCreated $event): void
    {
        if (!config('services.marketplace.physical_shipping_enabled', false)) {
            Log::info('CreateShipmentForOrder: пропуск — физическая доставка отключена (config)');
            return;
        }

        $order = $event->order;

        Log::info('OrderCreated event received', [
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
            'shipping_address' => $order->shipping_address
        ]);

        // Проверяем есть ли информация о ПВЗ в адресе доставки
        if (!$this->shouldCreateShipment($order)) {
            Log::info('Skipping shipment creation - not a PVZ delivery', [
                'order_id' => $order->id,
                'shipping_address' => $order->shipping_address
            ]);
            return;
        }

        try {
            // Подготавливаем данные для создания отправления
            $shipmentData = $this->prepareShipmentData($order);
            
            // Создаем отправление
            $shipment = $this->shipmentService->createShipment($order, $shipmentData);

            Log::info('Shipment created automatically for order', [
                'order_id' => $order->id,
                'shipment_id' => $shipment->id,
                'service' => $shipment->service
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create shipment for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Не бросаем исключение, чтобы не прерывать создание заказа
            // Отправление можно будет создать вручную позже
        }
    }

    /**
     * Проверяет, нужно ли создавать отправление для заказа
     */
    private function shouldCreateShipment($order): bool
    {
        // Проверяем есть ли адрес доставки
        if (!$order->shipping_address) {
            Log::info('No shipping address found', ['order_id' => $order->id]);
            return false;
        }

        $shippingAddress = is_string($order->shipping_address) 
            ? json_decode($order->shipping_address, true) 
            : $order->shipping_address;

        Log::info('Parsed shipping address', [
            'order_id' => $order->id,
            'shipping_address' => $shippingAddress
        ]);

        // Проверяем тип доставки
        if (!isset($shippingAddress['delivery_type']) || $shippingAddress['delivery_type'] !== 'pvz') {
            Log::info('Not a PVZ delivery', [
                'order_id' => $order->id,
                'delivery_type' => $shippingAddress['delivery_type'] ?? 'not_set'
            ]);
            return false;
        }

        // Проверяем есть ли информация о ПВЗ
        if (!isset($shippingAddress['pvz_info']) || !is_array($shippingAddress['pvz_info'])) {
            Log::info('No PVZ info found', [
                'order_id' => $order->id,
                'pvz_info' => $shippingAddress['pvz_info'] ?? 'not_set'
            ]);
            return false;
        }

        $pvzInfo = $shippingAddress['pvz_info'];
        
        // Проверяем обязательные поля ПВЗ
        $hasRequiredFields = isset($pvzInfo['pvz_id']) && 
                            isset($pvzInfo['service']) && 
                            isset($pvzInfo['name']) &&
                            in_array($pvzInfo['service'], ['sdek', 'yandex', '5post']);

        Log::info('PVZ info validation', [
            'order_id' => $order->id,
            'pvz_info' => $pvzInfo,
            'has_required_fields' => $hasRequiredFields
        ]);

        return $hasRequiredFields;
    }

    /**
     * Подготавливает данные для создания отправления
     */
    private function prepareShipmentData($order): array
    {
        $shippingAddress = is_string($order->shipping_address) 
            ? json_decode($order->shipping_address, true) 
            : $order->shipping_address;

        $pvzInfo = $shippingAddress['pvz_info'];

        // Подготавливаем данные получателя
        $recipientInfo = [
            'name' => $shippingAddress['name'] ?? 'Клиент',
            'phone' => $shippingAddress['phone'] ?? '',
            'email' => $shippingAddress['email'] ?? '',
        ];

        // Подготавливаем адрес получателя (ПВЗ)
        $recipientAddress = [
            'pvz_id' => $pvzInfo['pvz_id'],
            'city' => $pvzInfo['city'] ?? '',
            'address' => $pvzInfo['name'] ?? $shippingAddress['address'] ?? '',
            'latitude' => $pvzInfo['latitude'] ?? null,
            'longitude' => $pvzInfo['longitude'] ?? null,
        ];

        // Подготавливаем информацию об упаковке
        $packageInfo = $this->calculatePackageInfo($order);

        // Определяем службу доставки
        $service = $this->mapServiceName($pvzInfo['service']);

        return [
            'service' => $service,
            'recipient_info' => $recipientInfo,
            'recipient_address' => $recipientAddress,
            'package_info' => $packageInfo,
            'declared_value' => $order->total ?? 0,
            'delivery_cost' => $order->delivery_fee ?? 0,
            'cash_on_delivery' => $this->shouldUseCOD($order),
            'cod_amount' => $this->shouldUseCOD($order) ? ($order->total ?? 0) : null,
            'notes' => $shippingAddress['comment'] ?? "Заказ #{$order->tracking_number}",
        ];
    }

    /**
     * Рассчитывает параметры упаковки на основе товаров в заказе
     */
    private function calculatePackageInfo($order): array
    {
        $totalWeight = 0;
        $totalVolume = 0;
        $itemCount = 0;

        // Получаем товары из заказа
        if ($order->products && is_array($order->products)) {
            foreach ($order->products as $product) {
                $quantity = $product['order_quantity'] ?? $product['quantity'] ?? 1;
                $itemCount += $quantity;
                
                // Вес товара (если указан)
                if (isset($product['weight'])) {
                    $totalWeight += $product['weight'] * $quantity;
                }
                
                // Объем товара (если указаны размеры)
                if (isset($product['length'], $product['width'], $product['height'])) {
                    $volume = $product['length'] * $product['width'] * $product['height'];
                    $totalVolume += $volume * $quantity;
                }
            }
        }

        // Если вес не указан, используем примерный вес
        if ($totalWeight <= 0) {
            $totalWeight = max(100 * $itemCount, 500); // минимум 100г на товар, но не менее 500г
        }

        // Рассчитываем размеры упаковки
        $dimensions = $this->calculatePackageDimensions($totalVolume, $itemCount);

        return [
            'weight' => min($totalWeight, 30000), // максимум 30кг
            'length' => $dimensions['length'],
            'width' => $dimensions['width'], 
            'height' => $dimensions['height'],
        ];
    }

    /**
     * Рассчитывает размеры упаковки
     */
    private function calculatePackageDimensions(float $volume, int $itemCount): array
    {
        $config = config('services.cdek.default_dimensions', [
            'length' => 30,
            'width' => 20,
            'height' => 10,
        ]);

        // Если есть объем товаров, пытаемся рассчитать размеры
        if ($volume > 0) {
            // Предполагаем примерное соотношение сторон 3:2:1
            $baseVolume = pow($volume, 1/3);
            $length = min($baseVolume * 1.5, 120); // максимум 120см
            $width = min($baseVolume * 1.2, 80);   // максимум 80см  
            $height = min($baseVolume * 0.8, 70);  // максимум 70см
            
            return [
                'length' => max($length, $config['length']),
                'width' => max($width, $config['width']),
                'height' => max($height, $config['height']),
            ];
        }

        // Увеличиваем размеры в зависимости от количества товаров
        $sizeMultiplier = min(sqrt($itemCount), 3); // максимум в 3 раза больше

        return [
            'length' => min($config['length'] * $sizeMultiplier, 120),
            'width' => min($config['width'] * $sizeMultiplier, 80),
            'height' => min($config['height'] * $sizeMultiplier, 70),
        ];
    }

    /**
     * Определяет нужно ли использовать наложенный платеж
     */
    private function shouldUseCOD($order): bool
    {
        // Используем наложенный платеж если заказ еще не оплачен
        return $order->payment_status === 'pending' || 
               $order->payment_status === 'processing' ||
               $order->payment_gateway === 'cash_on_delivery';
    }

    /**
     * Преобразует название службы из фронтенда в константу модели
     */
    private function mapServiceName(string $frontendService): string
    {
        $map = [
            'sdek' => Shipment::SERVICE_SDEK,
            'yandex' => Shipment::SERVICE_YANDEX,
            '5post' => Shipment::SERVICE_5POST,
        ];

        return $map[$frontendService] ?? Shipment::SERVICE_SDEK;
    }
}