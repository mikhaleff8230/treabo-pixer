<?php

namespace Marvel\Services;

use Marvel\Database\Models\Shipment;
use Marvel\Database\Models\Order;
use Marvel\Services\CdekService;
use Illuminate\Support\Facades\Log;
use Exception;

class ShipmentService
{
    private CdekService $cdekService;

    public function __construct(CdekService $cdekService)
    {
        $this->cdekService = $cdekService;
    }

    /**
     * Создает отправление для заказа
     */
    public function createShipment(Order $order, array $shipmentData): Shipment
    {
        $service = $shipmentData['service'] ?? Shipment::SERVICE_SDEK;

        Log::info('Creating shipment', [
            'order_id' => $order->id,
            'service' => $service,
            'shipment_data' => $shipmentData
        ]);

        try {
            switch ($service) {
                case Shipment::SERVICE_SDEK:
                    return $this->createCdekShipment($order, $shipmentData);
                case Shipment::SERVICE_YANDEX:
                    return $this->createYandexShipment($order, $shipmentData);
                case Shipment::SERVICE_5POST:
                    return $this->create5PostShipment($order, $shipmentData);
                default:
                    throw new Exception("Неподдерживаемая служба доставки: {$service}");
            }
        } catch (Exception $e) {
            Log::error('Failed to create shipment', [
                'order_id' => $order->id,
                'service' => $service,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Создает отправление в СДЭК
     */
    private function createCdekShipment(Order $order, array $shipmentData): Shipment
    {
        // Подготовка данных для создания заказа в СДЭК
        $cdekOrderData = $this->prepareCdekOrderData($order, $shipmentData);

        // Создание заказа в СДЭК через API
        $cdekResponse = $this->cdekService->createOrder($cdekOrderData);

        if (!isset($cdekResponse['entity']['uuid'])) {
            throw new Exception('Не удалось получить UUID заказа от СДЭК: ' . json_encode($cdekResponse));
        }

        // Создание записи об отправлении в БД
        $shipment = Shipment::create([
            'order_id' => $order->id,
            'service' => Shipment::SERVICE_SDEK,
            'external_id' => $cdekResponse['entity']['uuid'],
            'status' => Shipment::STATUS_CREATED,
            'sender_address' => $shipmentData['sender_address'] ?? $this->getDefaultSenderAddress(),
            'recipient_address' => $shipmentData['recipient_address'],
            'package_info' => $shipmentData['package_info'],
            'declared_value' => $shipmentData['declared_value'] ?? 0,
            'delivery_cost' => $shipmentData['delivery_cost'] ?? 0,
            'recipient_info' => $shipmentData['recipient_info'],
            'services' => $shipmentData['services'] ?? [],
            'cash_on_delivery' => $shipmentData['cash_on_delivery'] ?? false,
            'cod_amount' => $shipmentData['cod_amount'] ?? null,
            'api_response' => $cdekResponse,
            'notes' => $shipmentData['notes'] ?? null,
        ]);

        Log::info('CDEK shipment created', [
            'shipment_id' => $shipment->id,
            'external_id' => $shipment->external_id,
            'order_id' => $order->id
        ]);

        return $shipment;
    }

    /**
     * Подготовка данных для создания заказа в СДЭК
     */
    private function prepareCdekOrderData(Order $order, array $shipmentData): array
    {
        $recipientInfo = $shipmentData['recipient_info'];
        $recipientAddress = $shipmentData['recipient_address'];
        $packageInfo = $shipmentData['package_info'];

        // Определяем тип доставки и подготавливаем данные
        $isPvzDelivery = isset($recipientAddress['pvz_id']) && !empty($recipientAddress['pvz_id']);
        
        Log::info('Preparing CDEK order data', [
            'order_id' => $order->id,
            'is_pvz_delivery' => $isPvzDelivery,
            'recipient_address' => $recipientAddress,
            'pvz_id' => $recipientAddress['pvz_id'] ?? 'not_set'
        ]);

        $orderData = [
            'order_number' => "ORDER-{$order->id}-" . time(), // Номер заказа в нашей системе
            'tariff_code' => $shipmentData['tariff_code'] ?? config('services.cdek.default_tariff', 136), // Тариф СДЭК (ПВЗ-ПВЗ)
            'comment' => $shipmentData['notes'] ?? "Заказ #{$order->id}",
            
            // Отправитель
            'sender' => $this->prepareSenderData($shipmentData['sender_address'] ?? []),
            
            // Получатель
            'recipient' => [
                'name' => $recipientInfo['name'],
                'phones' => [
                    ['number' => $this->formatPhone($recipientInfo['phone'])]
                ],
            ],
            
            // Локации
            'from_location' => $this->prepareFromLocation($shipmentData),
            'to_location' => $this->prepareToLocation($recipientAddress, $isPvzDelivery),
            
            // Упаковки
            'packages' => [
                [
                    'number' => "PACK-{$order->id}-1",
                    'weight' => $packageInfo['weight'] ?? config('services.cdek.default_weight', 1000), // вес в граммах
                    'length' => $packageInfo['length'] ?? config('services.cdek.default_dimensions.length', 30),   // см
                    'width' => $packageInfo['width'] ?? config('services.cdek.default_dimensions.width', 20),     // см
                    'height' => $packageInfo['height'] ?? config('services.cdek.default_dimensions.height', 10),   // см
                    'comment' => 'Товары интернет-магазина',
                    'items' => $this->prepareOrderItems($order),
                ]
            ],
        ];

        // Добавляем данные для ПВЗ доставки
        if ($isPvzDelivery) {
            $orderData['shipment_point'] = $shipmentData['sender_point'] ?? null; // Код ПВЗ отправителя
            $orderData['delivery_point'] = $recipientAddress['pvz_id']; // Код ПВЗ получателя
            Log::info('Added PVZ delivery data', [
                'order_id' => $order->id,
                'delivery_point' => $recipientAddress['pvz_id']
            ]);
        } else {
            // Если доставка по адресу, а не в ПВЗ
            $orderData['to_location'] = isset($recipientAddress['city_code']) ? [
                'code' => $recipientAddress['city_code'],
                'address' => $recipientAddress['address'] ?? null,
            ] : null;
        }

        return $orderData;
    }

    /**
     * Подготовка данных отправителя для СДЭК
     */
    private function prepareSenderData(array $senderAddress): array
    {
        $config = config('services.cdek');
        
        return [
            'name' => $senderAddress['name'] ?? $config['sender_name'] ?? 'SanCan',
            'phones' => [
                ['number' => $this->formatPhone($senderAddress['phone'] ?? $config['sender_phone'] ?? '+79999999999')]
            ],
        ];
    }

    /**
     * Подготовка локации отправителя
     */
    private function prepareFromLocation(array $shipmentData): array
    {
        $config = config('services.cdek');
        
        return [
            'code' => $config['sender_city_code'] ?? null,
            'address' => $config['sender_address'] ?? 'ул. Примерная, д. 1'
        ];
    }

    /**
     * Подготовка локации получателя
     */
    private function prepareToLocation(array $recipientAddress, bool $isPvzDelivery): array
    {
        if ($isPvzDelivery) {
            // Для ПВЗ доставки используем код ПВЗ и адрес ПВЗ
            return [
                'code' => $recipientAddress['pvz_id'] ?? null,
                'address' => $recipientAddress['address'] ?? $recipientAddress['name'] ?? 'ПВЗ'
            ];
        } else {
            // Для адресной доставки используем город и адрес
            return [
                'code' => $recipientAddress['city_code'] ?? null,
                'address' => $recipientAddress['address'] ?? null
            ];
        }
    }

    /**
     * Подготовка услуг для СДЭК
     */
    private function prepareCdekServices(array $shipmentData): array
    {
        $services = [];

        // Страхование
        if (isset($shipmentData['declared_value']) && $shipmentData['declared_value'] > 0) {
            $services[] = [
                'code' => 'INSURANCE',
                'parameter' => round($shipmentData['declared_value'], 2)
            ];
        }

        // Наложенный платеж
        if ($shipmentData['cash_on_delivery'] ?? false) {
            $services[] = [
                'code' => 'CASH_ON_DELIVERY',
                'parameter' => round($shipmentData['cod_amount'], 2)
            ];
        }

        // SMS уведомления
        $services[] = [
            'code' => 'SMS'
        ];

        return $services;
    }

    /**
     * Подготовка товаров заказа для СДЭК
     */
    private function prepareOrderItems(Order $order): array
    {
        $items = [];
        
        // Получаем товары из заказа
        foreach ($order->products ?? [] as $product) {
            $items[] = [
                'name' => $product['name'] ?? 'Товар',
                'ware_key' => $product['id'] ?? 'ITEM-' . count($items),
                'marking' => null, // Код маркировки (если есть)
                'payment' => [
                    'value' => round($product['unit_price'] ?? 0, 2)
                ],
                'cost' => round($product['unit_price'] ?? 0, 2),
                'weight' => round($product['weight'] ?? 100), // вес в граммах
                'weight_gross' => round($product['weight'] ?? 100),
                'amount' => $product['quantity'] ?? 1,
            ];
        }

        // Если товары не найдены, добавляем один общий товар
        if (empty($items)) {
            $items[] = [
                'name' => "Заказ #{$order->id}",
                'ware_key' => "ORDER-{$order->id}",
                'payment' => ['value' => round($order->total ?? 0, 2)],
                'cost' => round($order->total ?? 0, 2),
                'weight' => 1000, // 1 кг по умолчанию
                'weight_gross' => 1000,
                'amount' => 1,
            ];
        }

        return $items;
    }

    /**
     * Форматирует номер телефона для СДЭК
     */
    private function formatPhone(string $phone): string
    {
        // Убираем все символы кроме цифр
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Добавляем +7 если номер начинается с 8 или 9
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '8') {
            $phone = '7' . substr($phone, 1);
        } elseif (strlen($phone) === 10 && substr($phone, 0, 1) === '9') {
            $phone = '7' . $phone;
        }
        
        return '+' . $phone;
    }

    /**
     * Получает адрес отправителя по умолчанию
     */
    private function getDefaultSenderAddress(): array
    {
        $config = config('services.cdek');
        
        return [
            'name' => $config['sender_name'] ?? 'SanCan',
            'phone' => $config['sender_phone'] ?? '+79999999999',
            'city' => $config['sender_city'] ?? 'Москва',
            'address' => $config['sender_address'] ?? 'ул. Примерная, д. 1',
            'formatted_address' => ($config['sender_city'] ?? 'Москва') . ', ' . ($config['sender_address'] ?? 'ул. Примерная, д. 1'),
        ];
    }

    /**
     * Создает отправление в Яндекс.Доставка (заглушка)
     */
    private function createYandexShipment(Order $order, array $shipmentData): Shipment
    {
        // TODO: Реализовать интеграцию с Яндекс.Доставка
        throw new Exception('Яндекс.Доставка пока не поддерживается');
    }

    /**
     * Создает отправление в 5Post (заглушка)
     */
    private function create5PostShipment(Order $order, array $shipmentData): Shipment
    {
        // TODO: Реализовать интеграцию с 5Post
        throw new Exception('5Post пока не поддерживается');
    }

    /**
     * Обновляет статус отправления
     */
    public function updateShipmentStatus(Shipment $shipment): void
    {
        try {
            switch ($shipment->service) {
                case Shipment::SERVICE_SDEK:
                    $this->updateCdekShipmentStatus($shipment);
                    break;
                case Shipment::SERVICE_YANDEX:
                    $this->updateYandexShipmentStatus($shipment);
                    break;
                case Shipment::SERVICE_5POST:
                    $this->update5PostShipmentStatus($shipment);
                    break;
            }
        } catch (Exception $e) {
            Log::error('Failed to update shipment status', [
                'shipment_id' => $shipment->id,
                'service' => $shipment->service,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Обновляет статус отправления СДЭК
     */
    private function updateCdekShipmentStatus(Shipment $shipment): void
    {
        $orderInfo = $this->cdekService->getOrderInfo($shipment->external_id);
        
        if (isset($orderInfo['entity'])) {
            $entity = $orderInfo['entity'];
            
            // Обновляем трек-номер если он появился
            if (isset($entity['cdek_number']) && !$shipment->tracking_number) {
                $shipment->tracking_number = $entity['cdek_number'];
            }

            // Обновляем статус на основе статуса СДЭК
            if (isset($entity['statuses']) && is_array($entity['statuses'])) {
                $lastStatus = end($entity['statuses']);
                $newStatus = $this->mapCdekStatusToInternal($lastStatus['code']);
                
                if ($newStatus !== $shipment->status) {
                    $shipment->updateStatus(
                        $newStatus,
                        $lastStatus['name'] ?? null,
                        [
                            'external_code' => $lastStatus['code'],
                            'external_name' => $lastStatus['name'] ?? null,
                            'date_time' => $lastStatus['date_time'] ?? null,
                        ]
                    );
                }
            }

            // Сохраняем полный ответ API
            $shipment->api_response = array_merge($shipment->api_response ?? [], [
                'last_update' => now()->toISOString(),
                'order_info' => $orderInfo
            ]);
            
            $shipment->save();
        }
    }

    /**
     * Преобразует статус СДЭК во внутренний статус
     */
    private function mapCdekStatusToInternal(string $cdekStatus): string
    {
        $statusMap = [
            'CREATED' => Shipment::STATUS_CREATED,
            'RECEIVED_AT_SHIPMENT_WAREHOUSE' => Shipment::STATUS_SHIPPED,
            'TAKEN_BY_TRANSPORTER_FROM_SHIPMENT_WAREHOUSE' => Shipment::STATUS_IN_TRANSIT,
            'ARRIVED_AT_DELIVERY_WAREHOUSE' => Shipment::STATUS_IN_TRANSIT,
            'DELIVERED' => Shipment::STATUS_DELIVERED,
            'NOT_DELIVERED' => Shipment::STATUS_IN_TRANSIT,
            'RETURNED' => Shipment::STATUS_RETURNED,
        ];

        return $statusMap[$cdekStatus] ?? Shipment::STATUS_IN_TRANSIT;
    }

    /**
     * Заглушки для других служб
     */
    private function updateYandexShipmentStatus(Shipment $shipment): void
    {
        // TODO: Реализовать обновление статуса Яндекс.Доставка
    }

    private function update5PostShipmentStatus(Shipment $shipment): void
    {
        // TODO: Реализовать обновление статуса 5Post
    }

    /**
     * Получает все активные отправления для обновления статусов
     */
    public function getActiveShipmentsForStatusUpdate(): \Illuminate\Database\Eloquent\Collection
    {
        return Shipment::active()
            ->where('updated_at', '<', now()->subMinutes(30)) // Обновляем каждые 30 минут
            ->limit(100) // Ограничиваем количество для одного запуска
            ->get();
    }

    /**
     * Массовое обновление статусов активных отправлений
     */
    public function updateActiveShipmentsStatus(): int
    {
        $shipments = $this->getActiveShipmentsForStatusUpdate();
        $updated = 0;

        foreach ($shipments as $shipment) {
            try {
                $this->updateShipmentStatus($shipment);
                $updated++;
            } catch (Exception $e) {
                Log::error('Failed to update shipment status in batch', [
                    'shipment_id' => $shipment->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Batch shipment status update completed', [
            'total_processed' => $shipments->count(),
            'successfully_updated' => $updated
        ]);

        return $updated;
    }
}
