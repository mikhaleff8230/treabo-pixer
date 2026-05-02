<?php

namespace Marvel\Console\Commands;

use Illuminate\Console\Command;
use Marvel\Database\Models\Order;
use Marvel\Services\ShipmentService;
use Marvel\Database\Models\Shipment;

class TestShipmentCreation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shipments:test {order_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test shipment creation for a specific order';

    /**
     * Execute the console command.
     */
    public function handle(ShipmentService $shipmentService)
    {
        $orderId = $this->argument('order_id');
        
        $order = Order::find($orderId);
        
        if (!$order) {
            $this->error("Order with ID {$orderId} not found");
            return 1;
        }

        $this->info("Testing shipment creation for order: {$order->tracking_number}");
        $this->info("Order ID: {$order->id}");
        $this->info("Shipping address: " . json_encode($order->shipping_address, JSON_PRETTY_PRINT));

        // Проверяем есть ли уже отправление для этого заказа
        $existingShipment = Shipment::where('order_id', $order->id)->first();
        if ($existingShipment) {
            $this->warn("Shipment already exists for this order:");
            $this->info("Shipment ID: {$existingShipment->id}");
            $this->info("Service: {$existingShipment->service}");
            $this->info("Status: {$existingShipment->status}");
            $this->info("External ID: {$existingShipment->external_id}");
            return 0;
        }

        // Парсим адрес доставки
        $shippingAddress = is_string($order->shipping_address) 
            ? json_decode($order->shipping_address, true) 
            : $order->shipping_address;

        if (!$shippingAddress) {
            $this->error("No shipping address found");
            return 1;
        }

        // Проверяем тип доставки
        if (!isset($shippingAddress['delivery_type']) || $shippingAddress['delivery_type'] !== 'pvz') {
            $this->error("Not a PVZ delivery. Delivery type: " . ($shippingAddress['delivery_type'] ?? 'not_set'));
            return 1;
        }

        // Проверяем информацию о ПВЗ
        if (!isset($shippingAddress['pvz_info']) || !is_array($shippingAddress['pvz_info'])) {
            $this->error("No PVZ info found");
            return 1;
        }

        $pvzInfo = $shippingAddress['pvz_info'];
        $this->info("PVZ Info: " . json_encode($pvzInfo, JSON_PRETTY_PRINT));

        // Подготавливаем данные для отправления
        $shipmentData = [
            'service' => $this->mapServiceName($pvzInfo['service'] ?? 'sdek'),
            'recipient_info' => [
                'name' => $shippingAddress['name'] ?? 'Клиент',
                'phone' => $shippingAddress['phone'] ?? '',
                'email' => $shippingAddress['email'] ?? '',
            ],
            'recipient_address' => [
                'pvz_id' => $pvzInfo['pvz_id'],
                'city' => $pvzInfo['city'] ?? '',
                'address' => $pvzInfo['name'] ?? $shippingAddress['address'] ?? '',
                'latitude' => $pvzInfo['latitude'] ?? null,
                'longitude' => $pvzInfo['longitude'] ?? null,
            ],
            'package_info' => [
                'weight' => 1000, // 1кг по умолчанию
                'length' => 30,
                'width' => 20,
                'height' => 10,
            ],
            'declared_value' => $order->total ?? 0,
            'delivery_cost' => $order->delivery_fee ?? 0,
            'cash_on_delivery' => false,
            'cod_amount' => null,
            'notes' => $shippingAddress['comment'] ?? "Заказ #{$order->tracking_number}",
        ];

        $this->info("Shipment data: " . json_encode($shipmentData, JSON_PRETTY_PRINT));

        try {
            $this->info("Creating shipment...");
            $shipment = $shipmentService->createShipment($order, $shipmentData);
            
            $this->info("✅ Shipment created successfully!");
            $this->info("Shipment ID: {$shipment->id}");
            $this->info("Service: {$shipment->service}");
            $this->info("Status: {$shipment->status}");
            $this->info("External ID: {$shipment->external_id}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to create shipment: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
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
