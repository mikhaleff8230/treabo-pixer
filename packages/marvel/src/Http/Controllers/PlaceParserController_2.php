<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceImage;
use Marvel\Database\Models\Hashtag;
use Marvel\Database\Models\User;
use Marvel\Http\Controllers\CoreController;
use Marvel\Http\Resources\PlaceResource;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class PlaceParserController extends CoreController
{
    /**
     * Парсит контент по URL используя парсер
     */
    public function parse(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'url' => 'required|url',
                'parser_type' => 'nullable|string|in:livemaster',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $url = $request->input('url');
            $parserType = $request->input('parser_type', 'livemaster');

            Log::info('PlaceParserController::parse - начало парсинга', [
                'url' => $url,
                'parser_type' => $parserType,
            ]);

            // Определяем путь к парсеру
            $parserPath = base_path('../product-parser/parsers/livemaster_playwright_working.py');
            
            if (!file_exists($parserPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Парсер не найден',
                ], 404);
            }

            // Запускаем парсер
            $process = new Process([
                'python3',
                $parserPath,
                $url
            ]);
            
            $process->setTimeout(120); // 2 минуты на парсинг
            $process->run();

            if (!$process->isSuccessful()) {
                Log::error('PlaceParserController::parse - ошибка парсера', [
                    'error' => $process->getErrorOutput(),
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при парсинге',
                    'error' => $process->getErrorOutput(),
                ], 500);
            }

            // Парсим JSON результат
            $output = $process->getOutput();
            $parsedData = json_decode($output, true);

            if (!$parsedData || !isset($parsedData['title'])) {
                Log::error('PlaceParserController::parse - неверный формат данных', [
                    'output' => $output,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Не удалось распарсить данные',
                ], 500);
            }

            // Формируем ответ с данными для предпросмотра
            $result = [
                'title' => $parsedData['title'] ?? '',
                'description' => $parsedData['description'] ?? '',
                'source_url' => $parsedData['source_url'] ?? $url,
                'images' => array_slice($parsedData['images'] ?? [], 0, 3), // Берем первые 3 изображения
            ];

            Log::info('PlaceParserController::parse - парсинг успешен', [
                'title' => $result['title'],
                'images_count' => count($result['images']),
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error('PlaceParserController::parse - исключение', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при парсинге: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Создает плейс из спарсенных данных
     */
    public function createFromParsed(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'source_url' => 'nullable|url',
                'images' => 'nullable|array|max:3',
                'images.*' => 'url',
                'hashtags' => 'nullable|array',
                'hashtags.*' => 'string',
                'user_id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $userId = $data['user_id'];

            Log::info('PlaceParserController::createFromParsed - создание плейса', [
                'user_id' => $userId,
                'title' => $data['title'],
            ]);

            // Создаем плейс
            $place = Place::create([
                'user_id' => $userId,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source_url' => $data['source_url'] ?? null,
            ]);

            // Загружаем изображения
            if (!empty($data['images'])) {
                foreach ($data['images'] as $imageUrl) {
                    try {
                        // Скачиваем изображение через cURL
                        $ch = curl_init($imageUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $imageContent = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($imageContent !== false && $httpCode === 200) {
                            // Генерируем уникальное имя файла
                            $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                            $filename = 'places/' . $place->id . '/' . uniqid() . '.' . $extension;
                            
                            // Сохраняем в S3
                            \Storage::disk('s3')->put($filename, $imageContent);
                            
                            // Создаем запись в базе
                            PlaceImage::create([
                                'place_id' => $place->id,
                                'url' => $filename,
                            ]);
                            
                            Log::info('PlaceParserController::createFromParsed - изображение загружено', [
                                'image_url' => $imageUrl,
                                'filename' => $filename,
                            ]);
                        } else {
                            Log::warning('PlaceParserController::createFromParsed - не удалось скачать изображение', [
                                'image_url' => $imageUrl,
                                'http_code' => $httpCode,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('PlaceParserController::createFromParsed - ошибка загрузки изображения', [
                            'image_url' => $imageUrl,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Сохраняем хештеги
            if (!empty($data['hashtags'])) {
                $hashtagIds = [];
                foreach ($data['hashtags'] as $tag) {
                    $tagName = trim(ltrim($tag, '#'));
                    if (!empty($tagName)) {
                        $hashtag = Hashtag::firstOrCreate(
                            ['name' => $tagName],
                            ['slug' => \Illuminate\Support\Str::slug($tagName)]
                        );
                        $hashtagIds[] = $hashtag->id;
                    }
                }
                $place->hashtags()->sync($hashtagIds);
            }

            Log::info('PlaceParserController::createFromParsed - плейс создан', [
                'place_id' => $place->id,
            ]);

            return new PlaceResource($place->fresh(['images', 'hashtags', 'user.profile']));

        } catch (\Exception $e) {
            Log::error('PlaceParserController::createFromParsed - исключение', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании плейса: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Поиск пользователей по email, телефону или логину
     */
    public function searchUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = $request->input('query');

            $users = User::where('email', 'like', "%{$query}%")
                ->orWhere('phone_number', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%")
                ->select('id', 'name', 'email', 'phone_number')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Exception $e) {
            Log::error('PlaceParserController::searchUsers - исключение', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при поиске пользователей',
            ], 500);
        }
    }

    /**
     * Получает список изображений из папки
     */
    public function listImages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'folder' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $folder = $request->input('folder');
            
            // Проверяем существование папки
            if (!is_dir($folder)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Папка не найдена',
                ], 404);
            }

            // Получаем список файлов изображений
            $images = [];
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            $files = scandir($folder);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }
                
                $filePath = $folder . '/' . $file;
                if (is_file($filePath)) {
                    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($extension, $allowedExtensions)) {
                        // Возвращаем полный путь к файлу
                        $images[] = $filePath;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $images,
            ]);

        } catch (\Exception $e) {
            Log::error('PlaceParserController::listImages - исключение', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при получении списка изображений: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Массовое создание плейсов
     */
    public function createBulk(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'places' => 'required|array|max:100',
                'places.*.title' => 'required|string|max:255',
                'places.*.description' => 'nullable|string',
                'places.*.source_url' => 'nullable|url',
                'places.*.image' => 'nullable|string',
                'places.*.hashtags' => 'nullable|array',
                'places.*.hashtags.*' => 'string',
                'user_id' => 'required|integer|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            $userId = $data['user_id'];
            $places = $data['places'];
            
            $created = 0;
            $errors = [];

            Log::info('PlaceParserController::createBulk - начало массового создания', [
                'user_id' => $userId,
                'places_count' => count($places),
            ]);

            foreach ($places as $index => $placeData) {
                try {
                    // Создаем плейс
                    $place = Place::create([
                        'user_id' => $userId,
                        'title' => $placeData['title'],
                        'description' => $placeData['description'] ?? null,
                        'source_url' => $placeData['source_url'] ?? null,
                    ]);

                    // Загружаем изображение из локального файла
                    if (!empty($placeData['image']) && file_exists($placeData['image'])) {
                        try {
                            $imageContent = file_get_contents($placeData['image']);
                            if ($imageContent) {
                                $extension = pathinfo($placeData['image'], PATHINFO_EXTENSION) ?: 'jpg';
                                $filename = 'places/' . $place->id . '/' . uniqid() . '.' . $extension;
                                
                                // Сохраняем в S3
                                \Storage::disk('s3')->put($filename, $imageContent, [
                                    'visibility' => 'public',
                                    'CacheControl' => 'public, max-age=31536000, immutable',
                                    'ContentType' => mime_content_type($placeData['image']) ?: 'image/jpeg',
                                ]);
                                
                                // Создаем запись в базе
                                PlaceImage::create([
                                    'place_id' => $place->id,
                                    'url' => $filename,
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::warning('PlaceParserController::createBulk - ошибка загрузки изображения', [
                                'place_index' => $index,
                                'image_path' => $placeData['image'],
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }

                    // Сохраняем хештеги
                    if (!empty($placeData['hashtags'])) {
                        $hashtagIds = [];
                        foreach ($placeData['hashtags'] as $tag) {
                            $tagName = trim(ltrim($tag, '#'));
                            if (!empty($tagName)) {
                                $hashtag = Hashtag::firstOrCreate(
                                    ['name' => $tagName],
                                    ['slug' => \Illuminate\Support\Str::slug($tagName)]
                                );
                                $hashtagIds[] = $hashtag->id;
                            }
                        }
                        $place->hashtags()->sync($hashtagIds);
                    }

                    $created++;
                    
                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'title' => $placeData['title'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error('PlaceParserController::createBulk - ошибка создания плейса', [
                        'place_index' => $index,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('PlaceParserController::createBulk - массовое создание завершено', [
                'created' => $created,
                'errors_count' => count($errors),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'created' => $created,
                    'total' => count($places),
                    'errors' => $errors,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('PlaceParserController::createBulk - исключение', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при массовом создании плейсов: ' . $e->getMessage(),
            ], 500);
        }
    }
}

