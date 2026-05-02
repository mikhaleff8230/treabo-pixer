<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Marvel\Services\CdekService;

class PvzController extends CoreController
{
    protected CdekService $cdekService;

    private function isPhysicalShippingEnabled(): bool
    {
        return (bool) config('services.marketplace.physical_shipping_enabled', false);
    }

    public function __construct(CdekService $cdekService)
    {
        $this->cdekService = $cdekService;
    }

    public function getPvz(Request $request)
    {
        if (!$this->isPhysicalShippingEnabled()) {
            return response()->json(['error' => 'Physical shipping is disabled'], 503);
        }

        // Подробное логирование входящего запроса
        \Log::info('PVZ DEBUG: Request received', [
            'service' => $request->query('service'),
            'city' => $request->query('city'),
            'all_params' => $request->all(),
            'headers' => $request->headers->all(),
            'url' => $request->fullUrl()
        ]);

        $service = $request->query('service');
        $city = urldecode($request->query('city')); // Декодируем URL-кодированную строку
        
        if (!$service || !$city) {
            \Log::error('PVZ ERROR: missing service or city', [
                'service' => $service,
                'city' => $city,
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'service and city required'], 400);
        }

        \Log::info('PVZ DEBUG: Parameters validated', [
            'service' => $service,
            'city' => $city
        ]);

        if ($service === 'sdek') {
            return $this->getSdekPvz($city);
        } elseif ($service === 'yandex') {
            return $this->getYandexPvz($city);
        } else {
            return response()->json(['error' => 'unknown service'], 400);
        }
    }

    private function getSdekPvz($city)
    {
        \Log::info('PVZ DEBUG: Starting CDEK request via CdekService', ['city' => $city]);
        
        try {
            // Пробуем найти город через CdekService
            $cityData = $this->cdekService->findCityByName($city);
            
            if (!$cityData) {
                // Если не найден, используем hardcoded коды для основных городов
                $cityCode = $this->getHardcodedCityCode($city);
                
                if (!$cityCode) {
                    \Log::error('PVZ ERROR: City not found in CDEK', ['city' => $city]);
                    return response()->json(['error' => 'City not found in CDEK. Try: Москва, Санкт-Петербург, Новосибирск'], 404);
                }
            } else {
                $cityCode = $cityData['code'];
                \Log::info('PVZ DEBUG: City found via CdekService', [
                    'city' => $city,
                    'cityCode' => $cityCode,
                    'cityData' => $cityData
                ]);
            }

            // Получаем ПВЗ через CdekService
            \Log::info('PVZ DEBUG: Requesting CDEK PVZ via service', [
                'cityCode' => $cityCode
            ]);
            
            $pvzList = $this->cdekService->getPvzList($cityCode);
            
            \Log::info('PVZ DEBUG: CdekService response', [
                'city' => $city,
                'cityCode' => $cityCode,
                'pvz_count' => count($pvzList)
            ]);

            // CdekService уже возвращает отформатированный массив
            return response()->json($pvzList);

        } catch (\Exception $e) {
            \Log::error('PVZ ERROR: Exception in getSdekPvz', [
                'city' => $city,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'CDEK error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Получить hardcoded код города для основных городов России
     */
    private function getHardcodedCityCode($city): ?int
    {
        $cityLower = mb_strtolower($city);
        
        $cityMap = [
            'москва' => 44,
            'moscow' => 44,
            'санкт-петербург' => 137,
            'спб' => 137,
            'petersburg' => 137,
            'новосибирск' => 270,
            'novosibirsk' => 270,
            'екатеринбург' => 250,
            'нижний новгород' => 312,
            'казань' => 352,
            'челябинск' => 748,
            'омск' => 555,
            'самара' => 935,
            'ростов-на-дону' => 438,
            'уфа' => 477,
        ];
        
        return $cityMap[$cityLower] ?? null;
    }

    /**
     * Расчет стоимости доставки СДЭК
     */
    public function calculateDelivery(Request $request)
    {
        if (!$this->isPhysicalShippingEnabled()) {
            return response()->json(['error' => 'Physical shipping is disabled'], 503);
        }

        try {
            $validated = $request->validate([
                'from_city_code' => 'required|integer',
                'to_city_code' => 'required|integer',
                'tariff_code' => 'integer',
                'packages' => 'required|array',
                'packages.*.weight' => 'required|numeric',
                'packages.*.length' => 'required|numeric',
                'packages.*.width' => 'required|numeric',
                'packages.*.height' => 'required|numeric',
            ]);

            $result = $this->cdekService->calculateDelivery($validated);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            \Log::error('PVZ ERROR: Failed to calculate delivery', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'Failed to calculate delivery: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Создание заказа в СДЭК
     */
    public function createOrder(Request $request)
    {
        if (!$this->isPhysicalShippingEnabled()) {
            return response()->json(['error' => 'Physical shipping is disabled'], 503);
        }

        try {
            $validated = $request->validate([
                'order_number' => 'required|string',
                'tariff_code' => 'required|integer',
                'comment' => 'string',
                'sender' => 'required|array',
                'recipient' => 'required|array',
                'from_location' => 'required|array',
                'to_location' => 'required|array',
                'packages' => 'required|array',
            ]);

            $result = $this->cdekService->createOrder($validated);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            \Log::error('PVZ ERROR: Failed to create order', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);
            return response()->json(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Получение информации о заказе СДЭК
     */
    public function getOrderInfo(Request $request, $orderUuid)
    {
        if (!$this->isPhysicalShippingEnabled()) {
            return response()->json(['error' => 'Physical shipping is disabled'], 503);
        }

        try {
            $result = $this->cdekService->getOrderInfo($orderUuid);
            
            return response()->json($result);
            
        } catch (\Exception $e) {
            \Log::error('PVZ ERROR: Failed to get order info', [
                'orderUuid' => $orderUuid,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to get order info: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Получение доступных тарифов СДЭК
     */
    public function getAvailableTariffs()
    {
        if (!$this->isPhysicalShippingEnabled()) {
            return response()->json(['error' => 'Physical shipping is disabled'], 503);
        }

        try {
            $tariffs = $this->cdekService->getAvailableTariffs();
            
            return response()->json($tariffs);
            
        } catch (\Exception $e) {
            \Log::error('PVZ ERROR: Failed to get tariffs', [
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Failed to get tariffs: ' . $e->getMessage()], 500);
        }
    }

    private function getYandexPvz($city)
    {
        try {
            $apiKey = config('services.yandex_delivery.api_key');
            
            if (!$apiKey) {
                \Log::error('PVZ ERROR: Yandex API key not configured');
                return response()->json(['error' => 'Yandex API key not configured'], 500);
            }

            // Запрашиваем ПВЗ Яндекс.Доставки
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get('https://b2b.taxi.yandex.net/api/delivery/v1/deliverypoints', [
                'city' => $city,
                'type' => 'pickup'
            ]);

            // Логируем ответ Яндекса для диагностики
            \Log::error('YANDEX PVZ RESPONSE', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if (!$response->successful()) {
                \Log::error('PVZ ERROR: Yandex API error', [
                    'city' => $city,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return response()->json([
                    'error' => 'Yandex API error',
                    'status' => $response->status(),
                    'body' => $response->body()
                ], 500);
            }

            $pvzList = $response->json();
            
            // Форматируем ответ для фронта
            $formattedPvz = [];
            if (isset($pvzList['delivery_points'])) {
                foreach ($pvzList['delivery_points'] as $pvz) {
                    $formattedPvz[] = [
                        'id' => $pvz['id'] ?? '',
                        'name' => $pvz['name'] ?? '',
                        'address' => $pvz['address'] ?? '',
                        'latitude' => $pvz['location']['lat'] ?? 0,
                        'longitude' => $pvz['location']['lon'] ?? 0,
                        'phone' => $pvz['phone'] ?? '',
                        'work_time' => $pvz['schedule'] ?? '',
                        'service' => 'yandex'
                    ];
                }
            }

            return response()->json($formattedPvz);

        } catch (\Exception $e) {
            \Log::error('PVZ ERROR: Exception in getYandexPvz', [
                'city' => $city,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Yandex error: ' . $e->getMessage()], 500);
        }
    }
} 