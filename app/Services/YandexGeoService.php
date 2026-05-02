<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class YandexGeoService
{
    private $apiKey;
    private $apiUrl;
    
    public function __construct()
    {
        $this->apiKey = config('services.yandex_locator.api_key');
        $this->apiUrl = config('services.yandex_locator.api_url', 'https://locator.api.maps.yandex.ru/v1/locate');
    }

    /**
     * Получить местоположение через Yandex Locator API
     * 
     * @param string $ip IP адрес
     * @param array $wifi Массив WiFi точек (опционально)
     * @param array $cell Массив Cell данных (опционально)
     * @return array|null Данные о местоположении или null в случае ошибки
     */
    public function getLocationByIp(string $ip, array $wifi = [], array $cell = []): ?array
    {
        try {
            // Проверяем наличие API ключа
            if (!$this->apiKey) {
                Log::warning('Yandex Locator: API key not configured');
                return null;
            }

            // Валидация IP
            if (!filter_var($ip, FILTER_VALIDATE_IP)) {
                Log::warning('Yandex Locator: Invalid IP address', ['ip' => $ip]);
                return null;
            }

            // Пропускаем localhost
            if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
                Log::debug('Yandex Locator: Skipping localhost');
                return null;
            }

            // Кэшируем результат на 1 час
            // Ключ кэша зависит от наличия WiFi/Cell данных для точности
            $cacheKey = "yandex_geo_{$ip}_" . md5(json_encode(['wifi' => $wifi, 'cell' => $cell]));
            
            return Cache::remember($cacheKey, 3600, function () use ($ip, $wifi, $cell) {
                return $this->makeRequest($ip, $wifi, $cell);
            });
            
        } catch (\Exception $e) {
            Log::error('Yandex Locator: Error getting location', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $ip
            ]);
            return null;
        }
    }

    /**
     * Выполнить запрос к Yandex Locator API
     * 
     * @param string $ip IP адрес
     * @param array $wifi Массив WiFi точек
     * @param array $cell Массив Cell данных
     * @return array|null Данные о местоположении или null
     */
    private function makeRequest(string $ip, array $wifi = [], array $cell = []): ?array
    {
        try {
            // Правильный формат запроса для Yandex Locator API
            // Можно передавать: ip, wifi, cell для максимальной точности
            $requestData = [];
            
            // IP адрес (обязательно)
            $requestData['ip'] = [
                [
                    'address' => $ip
                ]
            ];
            
            // WiFi точки (если есть) - для максимальной точности
            if (!empty($wifi) && is_array($wifi)) {
                $requestData['wifi'] = [];
                foreach ($wifi as $wifiPoint) {
                    if (isset($wifiPoint['bssid']) && isset($wifiPoint['signal_strength'])) {
                        $requestData['wifi'][] = [
                            'bssid' => $wifiPoint['bssid'],
                            'signal_strength' => (int) $wifiPoint['signal_strength'],
                            'channel' => $wifiPoint['channel'] ?? null,
                            'age' => $wifiPoint['age'] ?? null
                        ];
                    }
                }
            }
            
            // Cell данные (если есть) - для максимальной точности
            if (!empty($cell) && is_array($cell)) {
                $requestData['cell'] = [];
                foreach ($cell as $cellData) {
                    $cellObject = [];
                    
                    // GSM (2G)
                    if (isset($cellData['gsm'])) {
                        $gsm = $cellData['gsm'];
                        $cellObject['gsm'] = [
                            'mcc' => (int) $gsm['mcc'],
                            'mnc' => (int) $gsm['mnc'],
                            'lac' => (int) $gsm['lac'],
                            'cid' => (int) $gsm['cid'],
                            'signal_strength' => (int) $gsm['signal_strength'],
                            'bsic' => $gsm['bsic'] ?? null,
                            'arfcn' => $gsm['arfcn'] ?? null,
                            'age' => $gsm['age'] ?? null,
                            'timing_advance' => $gsm['timing_advance'] ?? null
                        ];
                    }
                    
                    // WCDMA (3G)
                    if (isset($cellData['wcdma'])) {
                        $wcdma = $cellData['wcdma'];
                        $cellObject['wcdma'] = [
                            'mcc' => (int) $wcdma['mcc'],
                            'mnc' => (int) $wcdma['mnc'],
                            'lac' => (int) $wcdma['lac'],
                            'cid' => (int) $wcdma['cid'],
                            'signal_strength' => (int) $wcdma['signal_strength'],
                            'psc' => $wcdma['psc'] ?? null,
                            'uarfcn' => $wcdma['uarfcn'] ?? null,
                            'age' => $wcdma['age'] ?? null
                        ];
                    }
                    
                    // LTE (4G)
                    if (isset($cellData['lte'])) {
                        $lte = $cellData['lte'];
                        $cellObject['lte'] = [
                            'mcc' => (int) $lte['mcc'],
                            'mnc' => (int) $lte['mnc'],
                            'tac' => (int) $lte['tac'],
                            'ci' => (int) $lte['ci'],
                            'signal_strength' => (int) $lte['signal_strength'],
                            'pci' => $lte['pci'] ?? null,
                            'earfcn' => $lte['earfcn'] ?? null,
                            'age' => $lte['age'] ?? null,
                            'timing_advance' => $lte['timing_advance'] ?? null
                        ];
                    }
                    
                    if (!empty($cellObject)) {
                        $requestData['cell'][] = $cellObject;
                    }
                }
            }
            
            $url = "{$this->apiUrl}?apikey={$this->apiKey}";
            
            Log::debug('Yandex Locator: Making request', [
                'url' => $url,
                'ip' => $ip,
                'has_api_key' => !empty($this->apiKey),
                'request_data' => $requestData
            ]);
            
            // Yandex Locator требует JSON с правильным Content-Type
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'SanCan/1.0'
                ])
                ->asJson()
                ->post($url, $requestData);

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->body();
                
                Log::warning('Yandex Locator: API request failed', [
                    'status' => $status,
                    'body' => $body,
                    'ip' => $ip,
                    'url' => $url,
                    'api_key_preview' => substr($this->apiKey, 0, 10) . '...'
                ]);
                
                // Детальная диагностика для 403 ошибки
                if ($status === 403) {
                    Log::error('Yandex Locator: Access denied (403)', [
                        'message' => 'API ключ не имеет доступа к Yandex Locator API',
                        'solution' => 'Проверьте в кабинете разработчика Яндекс: https://developer.tech.yandex.ru/',
                        'steps' => [
                            '1. Зайдите в кабинет разработчика',
                            '2. Выберите ваше приложение',
                            '3. Убедитесь, что включен "Yandex Locator API"',
                            '4. Проверьте, что используете правильный API ключ',
                            '5. Возможно, нужен отдельный ключ для Locator API'
                        ]
                    ]);
                }
                
                return null;
            }

            $data = $response->json();
            
            Log::debug('Yandex Locator: Response received', [
                'has_response' => !empty($data),
                'response_keys' => array_keys($data ?? []),
                'has_location' => isset($data['location']),
                'has_error' => isset($data['error']),
                'full_response' => $data
            ]);
            
            // Проверяем успешность запроса
            // Yandex Locator возвращает: {"location": {"point": {"lat": ..., "lon": ...}, "accuracy": ...}}
            if (isset($data['location']['point'])) {
                $result = $this->parseResponse($data, $ip);
                
                if (!$result) {
                    Log::warning('Yandex Locator: parseResponse returned null', [
                        'ip' => $ip,
                        'response_data' => $data
                    ]);
                }
                
                return $result;
            } elseif (isset($data['error'])) {
                // API вернул ошибку
                Log::warning('Yandex Locator: API returned error', [
                    'error' => $data['error'],
                    'ip' => $ip,
                    'full_response' => $data
                ]);
                return null;
            } else {
                // Неизвестная структура ответа
                Log::warning('Yandex Locator: Invalid response structure', [
                    'data' => $data,
                    'ip' => $ip,
                    'all_keys' => array_keys($data ?? [])
                ]);
                return null;
            }
            
        } catch (\Exception $e) {
            Log::error('Yandex Locator: Request exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'ip' => $ip,
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Парсить ответ от Yandex Locator API
     * 
     * @param array $data Данные ответа
     * @param string $ip IP адрес
     * @return array|null Данные о местоположении
     */
    private function parseResponse(array $data, string $ip): ?array
    {
        try {
            // Yandex Locator возвращает: {"location": {"point": {"lat": ..., "lon": ...}, "accuracy": ...}}
            if (!isset($data['location']['point'])) {
                Log::warning('Yandex Locator: Missing location.point in response');
                return null;
            }
            
            $point = $data['location']['point'];
            $lat = $point['lat'] ?? null;
            $lon = $point['lon'] ?? null;
            $accuracy = $data['location']['accuracy'] ?? null;
            
            if (!$lat || !$lon) {
                Log::warning('Yandex Locator: Missing coordinates in response', ['point' => $point]);
                return null;
            }
            
            // Yandex Locator возвращает только координаты, нужно получить адрес через Geocoder
            // Но для базового ответа вернем координаты
            // Город и страну можно получить через обратный геокодинг, но это отдельный запрос
            
            return [
                'ip' => $ip,
                'country' => 'Unknown', // Будет определено через обратный геокодинг или MaxMind
                'iso_code' => 'Unknown',
                'city' => 'Unknown', // Будет определено через обратный геокодинг
                'state' => null,
                'state_name' => null,
                'postal_code' => null,
                'lat' => (float) $lat,
                'lon' => (float) $lon,
                'timezone' => $this->getTimezone((float) $lat, (float) $lon),
                'continent' => null,
                'currency' => null,
                'source' => 'yandex_locator',
                'accuracy' => $accuracy,
                'needs_reverse_geocode' => true // Флаг, что нужен обратный геокодинг для получения адреса
            ];
            
        } catch (\Exception $e) {
            Log::error('Yandex Locator: Error parsing response', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Получить код страны по названию
     * 
     * @param string $countryName Название страны
     * @return string Код страны
     */
    private function getCountryCode(string $countryName): string
    {
        $countryCodes = [
            'Россия' => 'RU',
            'Беларусь' => 'BY',
            'Казахстан' => 'KZ',
            'Украина' => 'UA',
            'США' => 'US',
            'Великобритания' => 'GB',
            'Германия' => 'DE',
            'Франция' => 'FR',
            'Италия' => 'IT',
            'Испания' => 'ES',
            'Нидерланды' => 'NL',
            'Польша' => 'PL',
        ];

        return $countryCodes[$countryName] ?? 'Unknown';
    }

    /**
     * Определить таймзону по координатам (упрощенная версия)
     * 
     * @param float $lat Широта
     * @param float $lon Долгота
     * @return string Таймзона
     */
    private function getTimezone(float $lat, float $lon): string
    {
        // Для России всегда Europe/Moscow
        if ($lon > 20 && $lon < 180 && $lat > 41 && $lat < 82) {
            return 'Europe/Moscow';
        }
        
        // Для других регионов возвращаем UTC
        return 'UTC';
    }

    /**
     * Получить город по IP
     * 
     * @param string $ip IP адрес
     * @return string|null Название города
     */
    public function getCityByIp(string $ip): ?string
    {
        $location = $this->getLocationByIp($ip);
        return $location['city'] ?? null;
    }

    /**
     * Получить координаты по IP
     * 
     * @param string $ip IP адрес
     * @return array|null Координаты ['lat' => float, 'lon' => float] или null
     */
    public function getCoordinatesByIp(string $ip): ?array
    {
        $location = $this->getLocationByIp($ip);
        
        if (!$location) {
            return null;
        }

        return [
            'lat' => $location['lat'] ?? 0,
            'lon' => $location['lon'] ?? 0
        ];
    }
}

