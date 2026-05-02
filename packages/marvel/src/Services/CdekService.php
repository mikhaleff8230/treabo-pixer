<?php

namespace Marvel\Services;

use CdekSDK2\Client;
use CdekSDK2\BaseTypes\Order;
use CdekSDK2\BaseTypes\Package;
use CdekSDK2\BaseTypes\Contact;
use CdekSDK2\BaseTypes\Location;
use CdekSDK2\BaseTypes\Item;
use CdekSDK2\BaseTypes\Money;
use CdekSDK2\BaseTypes\Phone;
use CdekSDK2\Dto\PickupPointList;
use CdekSDK2\Dto\OrderInfo;
use CdekSDK2\Dto\CityList;
use CdekSDK2\Dto\TariffList;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Http\Adapter\Guzzle7\Client as GuzzleAdapter;

class CdekService
{
    private ?Client $client = null;

    private array $config = [];

    /** @var bool Интеграция с ТК (вкл. при PHYSICAL_SHIPPING_ENABLED=true) */
    private bool $physicalShippingEnabled = false;

    public function __construct()
    {
        $this->physicalShippingEnabled = (bool) config('services.marketplace.physical_shipping_enabled', false);
        if (!$this->physicalShippingEnabled) {
            Log::info('CdekService: интеграция отключена (services.marketplace.physical_shipping_enabled=false)');
            return;
        }

        $this->config = config('services.cdek') ?? [];
        
        // Проверяем наличие конфигурации CDEK
        if (empty($this->config) || empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            \Log::warning('CdekService: CDEK не настроен в .env файле', [
                'config_exists' => !empty($this->config),
                'client_id_exists' => !empty($this->config['client_id'] ?? null),
                'client_secret_exists' => !empty($this->config['client_secret'] ?? null),
                'env_client_id' => env('CDEK_CLIENT_ID') ? 'SET' : 'NOT SET',
                'env_client_secret' => env('CDEK_CLIENT_SECRET') ? 'SET' : 'NOT SET'
            ]);
            return; // Не инициализируем клиент если нет конфигурации
        }
        
        // Создаем HTTP клиент для СДЭК SDK
        $httpClient = new GuzzleAdapter();
        $this->client = new Client($httpClient);
        
        // Устанавливаем аутентификацию
        $this->client->setAccount($this->config['client_id']);
        $this->client->setSecure($this->config['client_secret']);
        
        // Устанавливаем тестовый режим если нужно
        if ($this->config['test_mode'] ?? false) {
            $this->client->setTestMode(true);
        }
    }

    /**
     * Получение списка ПВЗ по городу
     */
    public function getPvzList(string $cityCode): array
    {
        if (!$this->physicalShippingEnabled || $this->client === null) {
            return [];
        }
        
        try {
            // Получаем список ПВЗ через SDK
            $response = $this->client->offices()->getFiltered([
                'city_code' => $cityCode,
                'type' => 'PVZ', // Только пункты выдачи заказов
                'country_code' => 'RU'
            ]);
            
            if (!$response->isOk()) {
                $errors = $response->getErrors();
                throw new Exception('CDEK API error: ' . json_encode($errors));
            }

            // Форматируем ответ
            $pvzResponse = $this->client->formatResponseList($response, PickupPointList::class);
            $pvzList = [];
            
            foreach ($pvzResponse->items as $point) {
                $pvzList[] = [
                    'id' => $point->code,
                    'name' => $point->name,
                    'address' => $point->address_full ?? $point->address_comment,
                    'latitude' => $point->location->latitude ?? 0,
                    'longitude' => $point->location->longitude ?? 0,
                    'service' => 'sdek',
                    'work_time' => $point->work_time ?? '',
                    'phones' => $point->phone ?? [],
                    'email' => $point->email ?? '',
                    'note' => $point->note ?? '',
                    'is_handout' => $point->is_handout ?? true,
                    'is_reception' => $point->is_reception ?? true,
                    'is_dressing_room' => $point->is_dressing_room ?? false,
                    'have_cashless' => $point->have_cashless ?? true,
                    'have_cash' => $point->have_cash ?? true,
                    'allowed_cod' => $point->allowed_cod ?? true,
                ];
            }

            return $pvzList;
            
        } catch (Exception $e) {
            Log::error('CDEK Service Error: Failed to get PVZ list', [
                'cityCode' => $cityCode,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Поиск города по названию
     */
    public function findCityByName(string $cityName): ?array
    {
        if (!$this->physicalShippingEnabled || $this->client === null) {
            return null;
        }
        
        try {
            $cacheKey = 'cdek_city_' . md5($cityName);
            
            return Cache::remember($cacheKey, 3600, function () use ($cityName) {
                // Получаем список городов через SDK
                $response = $this->client->cities()->getFiltered([
                    'city' => $cityName,
                    'country_code' => 'RU',
                    'size' => 1000
                ]);
                
                if (!$response->isOk()) {
                    return null;
                }

                // Форматируем ответ
                $citiesResponse = $this->client->formatResponseList($response, CityList::class);
                
                if (empty($citiesResponse->items)) {
                    return null;
                }

                // Находим основной город (с наибольшим населением)
                $mainCity = null;
                foreach ($citiesResponse->items as $city) {
                    if (!$mainCity || ($city->population ?? 0) > ($mainCity->population ?? 0)) {
                        $mainCity = $city;
                    }
                }

                return [
                    'code' => $mainCity->code,
                    'name' => $mainCity->city,
                    'population' => $mainCity->population ?? 0,
                    'region' => $mainCity->region ?? '',
                    'country' => $mainCity->country ?? 'RU',
                ];
            });
            
        } catch (Exception $e) {
            Log::error('CDEK Service Error: Failed to find city', [
                'cityName' => $cityName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Расчет стоимости доставки
     */
    public function calculateDelivery(array $params): array
    {
        if (!$this->physicalShippingEnabled || $this->client === null) {
            return [
                'delivery_sum' => 0,
                'period_min' => 0,
                'period_max' => 0,
                'currency' => 'RUB',
                'calendar_min' => null,
                'calendar_max' => null,
            ];
        }
        
        try {
            // Создаем объекты для расчета
            $packages = [];
            foreach ($params['packages'] as $packageData) {
                $package = Package::create([
                    'weight' => $packageData['weight'],
                    'length' => $packageData['length'],
                    'width' => $packageData['width'],
                    'height' => $packageData['height'],
                ]);
                $packages[] = $package;
            }

            // Выполняем расчет через SDK
            $response = $this->client->calculator()->calculate([
                'tariff_code' => $params['tariff_code'] ?? 136,
                'from_location' => ['code' => $params['from_city_code']],
                'to_location' => ['code' => $params['to_city_code']],
                'packages' => $packages
            ]);
            
            if (!$response->isOk()) {
                $errors = $response->getErrors();
                throw new Exception('CDEK API error: ' . json_encode($errors));
            }

            $tariffData = $this->client->formatResponse($response, TariffList::class);

            return [
                'delivery_sum' => $tariffData->delivery_sum ?? 0,
                'period_min' => $tariffData->period_min ?? 0,
                'period_max' => $tariffData->period_max ?? 0,
                'currency' => $tariffData->currency ?? 'RUB',
                'calendar_min' => $tariffData->calendar_min ?? null,
                'calendar_max' => $tariffData->calendar_max ?? null,
            ];
            
        } catch (Exception $e) {
            Log::error('CDEK Service Error: Failed to calculate delivery', [
                'params' => $params,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Создание заказа в СДЭК
     */
    public function createOrder(array $orderData): array
    {
        if (!$this->physicalShippingEnabled || $this->client === null) {
            throw new Exception('Интеграция с ТК отключена в конфигурации маркетплейса');
        }
        
        try {
            Log::info('CDEK Service: Creating order', ['orderData' => $orderData]);
            
            // Создаем контакты
            $senderPhones = [];
            if (isset($orderData['sender']['phones']) && is_array($orderData['sender']['phones'])) {
                foreach ($orderData['sender']['phones'] as $phoneData) {
                    $senderPhones[] = Phone::create([
                        'number' => $phoneData['number'] ?? $phoneData
                    ]);
                }
            }
            
            $recipientPhones = [];
            if (isset($orderData['recipient']['phones']) && is_array($orderData['recipient']['phones'])) {
                foreach ($orderData['recipient']['phones'] as $phoneData) {
                    $recipientPhones[] = Phone::create([
                        'number' => $phoneData['number'] ?? $phoneData
                    ]);
                }
            }
            
            $sender = Contact::create([
                'company' => $orderData['sender']['company'] ?? '',
                'name' => $orderData['sender']['name'],
                'email' => $orderData['sender']['email'] ?? '',
                'phones' => $senderPhones
            ]);
            
            $recipient = Contact::create([
                'name' => $orderData['recipient']['name'],
                'email' => $orderData['recipient']['email'] ?? '',
                'phones' => $recipientPhones
            ]);
            
            // Создаем локации
            $fromLocation = Location::create([
                'code' => $orderData['from_location']['code'] ?? null,
                'address' => $orderData['from_location']['address'] ?? null
            ]);
            
            $toLocation = Location::create([
                'code' => $orderData['to_location']['code'] ?? null,
                'address' => $orderData['to_location']['address'] ?? null
            ]);
            
            // Создаем упаковки с товарами
            $packages = [];
            foreach ($orderData['packages'] as $packageData) {
                $items = [];
                foreach ($packageData['items'] as $itemData) {
                    $items[] = Item::create([
                        'name' => $itemData['name'],
                        'ware_key' => $itemData['ware_key'],
                        'payment' => Money::create([
                            'value' => $itemData['payment'],
                            'vat_sum' => $itemData['vat_sum'] ?? 0,
                            'vat_rate' => $itemData['vat_rate'] ?? 0
                        ]),
                        'cost' => $itemData['cost'],
                        'weight' => $itemData['weight'],
                        'amount' => $itemData['amount']
                    ]);
                }
                
                $packages[] = Package::create([
                    'number' => $packageData['number'],
                    'weight' => $packageData['weight'],
                    'length' => $packageData['length'],
                    'width' => $packageData['width'],
                    'height' => $packageData['height'],
                    'comment' => $packageData['comment'] ?? '',
                    'items' => $items
                ]);
            }
            
            // Создаем заказ
            $order = Order::create([
                'number' => $orderData['order_number'],
                'tariff_code' => $orderData['tariff_code'],
                'comment' => $orderData['comment'] ?? '',
                'sender' => $sender,
                'recipient' => $recipient,
                'from_location' => $fromLocation,
                'to_location' => $toLocation,
                'packages' => $packages
            ]);
            
            Log::info('CDEK Service: Sending order to CDEK API', ['order' => $order]);
            
            // Отправляем заказ в СДЭК
            $response = $this->client->orders()->add($order);
            
            if (!$response->isOk()) {
                $errors = $response->getErrors();
                Log::error('CDEK API error', ['errors' => $errors, 'response' => $response]);
                throw new Exception('CDEK API error: ' . json_encode($errors));
            }

            $createdOrder = $this->client->formatResponse($response, Order::class);
            
            Log::info('CDEK Service: Order created successfully', [
                'uuid' => $createdOrder->entity->uuid,
                'cdek_number' => $createdOrder->entity->cdek_number ?? null
            ]);
            
            return [
                'entity' => [
                    'uuid' => $createdOrder->entity->uuid,
                    'cdek_number' => $createdOrder->entity->cdek_number ?? null
                ],
                'status' => 'created',
                'created_at' => now(),
            ];
            
        } catch (Exception $e) {
            Log::error('CDEK Service Error: Failed to create order', [
                'orderData' => $orderData,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Получение информации о заказе
     */
    public function getOrderInfo(string $orderUuid): array
    {
        if (!$this->physicalShippingEnabled || $this->client === null) {
            throw new Exception('Интеграция с ТК отключена в конфигурации маркетплейса');
        }
        
        try {
            // Получаем информацию о заказе через SDK
            $response = $this->client->orders()->get($orderUuid);
            
            if (!$response->isOk()) {
                $errors = $response->getErrors();
                throw new Exception('CDEK API error: ' . json_encode($errors));
            }

            $orderInfo = $this->client->formatResponse($response, OrderInfo::class);
            
            return [
                'uuid' => $orderInfo->entity->uuid,
                'cdek_number' => $orderInfo->entity->cdek_number ?? null,
                'status' => $orderInfo->entity->statuses[0]->code ?? 'unknown',
                'status_name' => $orderInfo->entity->statuses[0]->name ?? 'Неизвестно',
                'delivery_sum' => $orderInfo->entity->delivery_sum ?? 0,
                'is_delivery_paid' => $orderInfo->entity->is_delivery_paid ?? false,
                'tracking_number' => $orderInfo->entity->cdek_number ?? null,
            ];
            
        } catch (Exception $e) {
            Log::error('CDEK Service Error: Failed to get order info', [
                'orderUuid' => $orderUuid,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Получение доступных тарифов
     */
    public function getAvailableTariffs(): array
    {
        if (!$this->physicalShippingEnabled) {
            return [];
        }

        return [
            136 => ['name' => 'Посылка склад-склад', 'mode' => 'door-to-door'],
            137 => ['name' => 'Посылка склад-дверь', 'mode' => 'door-to-door'],
            138 => ['name' => 'Посылка дверь-склад', 'mode' => 'door-to-door'],
            139 => ['name' => 'Посылка дверь-дверь', 'mode' => 'door-to-door'],
            233 => ['name' => 'Экономичная посылка склад-дверь', 'mode' => 'economy'],
            234 => ['name' => 'Экономичная посылка склад-склад', 'mode' => 'economy'],
        ];
    }

    /**
     * Конфигурация для webhook
     */
    public function getWebhookConfig(): array
    {
        return [
            'url' => config('app.url') . '/api/webhooks/cdek',
            'events' => [
                'ORDER_STATUS_CHANGED',
                'DELIVERY_STATUS_CHANGED'
            ]
        ];
    }
}
