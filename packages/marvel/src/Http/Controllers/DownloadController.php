<?php

namespace Marvel\Http\Controllers;

use App\Services\DigitalAccessGrantService;
use App\Services\DigitalProductAccessService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\DownloadToken;
use Marvel\Database\Models\OrderedFile;
use Marvel\Database\Models\Purchase;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Variation;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\User;
use Marvel\Database\Repositories\DownloadRepository;
use Marvel\Exceptions\MarvelException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Marvel\Enums\OrderStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DownloadController extends CoreController
{
    public $repository;

    public function __construct(
        DownloadRepository $repository,
        protected DigitalAccessGrantService $digitalAccessGrant,
        protected DigitalProductAccessService $digitalProductAccess
    ) {
        $this->repository = $repository;
    }

    /**
     * fetchDownloadableFiles
     *
     * @param mixed $request
     * @return void
     * @throws MarvelException
     */
    public function fetchDownloadableFiles(Request $request)
    {
        $limit = isset($request->limit) ? $request->limit : 15;
        $paginator = $this->fetchFiles($request)->paginate($limit);
        
        // Загружаем fileable для всех файлов после пагинации
        // Используем loadMorph для загрузки связей
        $collection = $paginator->getCollection();
        
        // Загружаем fileable с withTrashed для удаленных товаров
        // Сначала получаем все digital_files
        $digitalFiles = $collection->pluck('file')->filter();
        $fileableIds = [];
        $fileableTypes = [];
        
        foreach ($digitalFiles as $file) {
            if ($file && $file->fileable_id && $file->fileable_type) {
                $fileableIds[$file->fileable_type][] = $file->fileable_id;
                $fileableTypes[$file->fileable_type] = $file->fileable_type;
            }
        }
        
        // Загружаем fileable с withTrashed
        $fileables = [];
        foreach ($fileableTypes as $type) {
            $ids = $fileableIds[$type] ?? [];
            if (empty($ids)) continue;
            
            if ($type === Product::class) {
                $fileables[$type] = Product::withTrashed()->whereIn('id', $ids)->with(['shop'])->get()->keyBy('id');
            } elseif ($type === Variation::class) {
                $fileables[$type] = Variation::whereIn('id', $ids)->with(['product' => function($q) {
                    $q->withTrashed()->with(['shop']);
                }])->get()->keyBy('id');
            }
        }
        
        // Присваиваем загруженные fileable обратно
        foreach ($collection as $orderedFile) {
            if ($orderedFile->file && $orderedFile->file->fileable_id && $orderedFile->file->fileable_type) {
                $type = $orderedFile->file->fileable_type;
                $id = $orderedFile->file->fileable_id;
                if (isset($fileables[$type][$id])) {
                    $orderedFile->file->setRelation('fileable', $fileables[$type][$id]);
                }
            }
        }
        
        return $paginator->withQueryString();
    }

    /**
     * fetchFiles
     *
     * @param mixed $request
     * @return mixed
     * @throws MarvelException
     */
    public function fetchFiles(Request $request)
    {
        try {
            $user = $request->user();
            if ($user) {
                // Используем подзапрос для сортировки по дате заказа
                $orderBy = $request->orderBy ?? 'created_at';
                $sortedBy = strtoupper($request->sortedBy ?? 'desc');
                
                $query = OrderedFile::where('ordered_files.customer_id', $user->id)
                    ->with([
                        'order' => function($q) {
                            // Загружаем заказ без фильтрации по статусу
                            $q->with(['products' => function($q) {
                                $q->withTrashed()->with(['variation_options']); // Загружаем даже удаленные товары
                            }]);
                        },
                        'file.fileable'
                    ]);
                
                // Всегда используем leftJoin для сортировки по дате заказа
                // Это гарантирует, что заказы будут отсортированы правильно
                // Используем COALESCE для обработки случаев, когда заказ еще не создан
                $query->leftJoin('orders', 'ordered_files.tracking_number', '=', 'orders.tracking_number')
                      ->select('ordered_files.*', 
                               \DB::raw('COALESCE(orders.created_at, ordered_files.created_at) as order_created_at'));
                
                // Сортируем по дате заказа (новые сверху)
                // Используем COALESCE для сортировки, чтобы новые заказы всегда были сверху
                $query->orderBy(\DB::raw('COALESCE(orders.created_at, ordered_files.created_at)'), $sortedBy === 'ASC' ? 'asc' : 'desc')
                      ->orderBy('ordered_files.created_at', $sortedBy === 'ASC' ? 'asc' : 'desc'); // Дополнительная сортировка на случай, если дата заказа одинаковая
                
                return $query;
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $th) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    /**
     * generateDownloadableUrl
     *
     * @param mixed $request
     * @return void
     * @throws MarvelException
     */
    public function generateDownloadableUrl(Request $request)
    {
        try {
            $user = $request->user();
            $orderedFiles = OrderedFile::where('digital_file_id', $request->digital_file_id)->where('customer_id', $user->id)->get();
            if (count($orderedFiles)) {
                $dataArray = [
                    'user_id' => $user->id,
                    'token' => Str::random(16),
                    'digital_file_id' => $request->digital_file_id
                ];
                $newToken = DownloadToken::create($dataArray);
                return route('download_url.token', ['token' => $newToken->token]);
            }
            throw new AuthorizationException(NOT_AUTHORIZED);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    /**
     * generateFreeDigitalDownloadableUrl -> Pixer-laravel
     *
     * @param mixed $request
     * @return void
     * @throws MarvelException
     */
    public function generateFreeDigitalDownloadableUrl(Request $request)
    {
        $product_id = $request->product_id;
        try {
            $product = Product::with('digital_file')->findOrFail($product_id);
        } catch (\Throwable $th) {
            throw new MarvelException(NOT_FOUND);
        }
        if ($product->price == 0 ||  (isset($product->sale_price) && $product->sale_price == 0)) {
            if ($product->digital_file->id) {
                $dataArray = [
                    'token' => Str::random(16),
                    'digital_file_id' => $product->digital_file->id
                ];
                $newToken = DownloadToken::create($dataArray);
                return route('download_url.token', ['token' => $newToken->token]);
            } else {
                throw new MarvelException(NOT_FOUND);
            }
        } else {
            throw new MarvelException(NOT_A_FREE_PRODUCT);
        }
    }

    /**
     * downloadFile
     *
     * @param mixed $token
     * @return void
     * @throws MarvelException
     */
    public function downloadFile($token)
    {
        try {
            $downloadToken = DownloadToken::with('file')->where('token', $token)->where('downloaded', 0)->first();
            if ($downloadToken) {
                $downloadToken->downloaded = 1;
                $downloadToken->save();
            } else {
                return ['message' => TOKEN_NOT_FOUND];
            }
        } catch (Exception $e) {
            throw new MarvelException(TOKEN_NOT_FOUND);
        }
        try {
            $mediaItem = Media::where('model_id', $downloadToken->file->attachment_id)->firstOrFail();
        } catch (Exception $e) {
            return ['message' => NOT_FOUND];
        }
        return response()->streamDownload(function () use ($downloadToken) {
            $url = (string) ($downloadToken->file->url ?? '');
            $isHttp = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');

            if ($isHttp) {
                $contents = @file_get_contents($url);
                if ($contents !== false) {
                    echo $contents;
                    return;
                }
            }

            // Fallback: try to stream directly from S3 by object key.
            $key = $url;
            if ($isHttp) {
                $parsed = parse_url($url);
                $key = ltrim($parsed['path'] ?? '', '/');
            }

            if ($key && Storage::disk('s3')->exists($key)) {
                $stream = Storage::disk('s3')->readStream($key);
                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            }
        }, $mediaItem->file_name);
    }

    /**
     * Protected download endpoint for purchased digital products.
     * GET /products/{id}/download
     */
    public function downloadPurchasedProduct(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        $trackingNumber = trim((string) $request->query('tracking_number', '')) ?: null;
        $grant = $this->digitalAccessGrant->grant($user->id, $id, $trackingNumber);
        if (!$grant['granted']) {
            Log::warning('digital_download.denied', [
                'user_id' => $user->id,
                'product_id' => $id,
                'tracking_number' => $trackingNumber,
                'reason' => $grant['reason'] ?? 'unknown',
            ]);
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        $product = Product::with('digital_file')->findOrFail($id);
        if (!(bool) $product->is_digital || !$product->digital_file) {
            throw new MarvelException(NOT_FOUND);
        }

        $newToken = DownloadToken::create([
            'user_id' => $user->id,
            'token' => Str::random(16),
            'digital_file_id' => $product->digital_file->id,
        ]);

        return response()->json([
            'download_url' => route('download_url.token', ['token' => $newToken->token]),
        ]);
    }

    /**
     * Unified access endpoint for digital products.
     * GET /products/{id}/access
     *
     * Доступ: Purchase → Order (по tracking_number; временно без проверки оплаты, кроме отмены) → ordered_files.
     * ?debug=1 — JSON с полем _debug (purchase / order / ordered_files, сверка tracking).
     */
    public function accessPurchasedProduct(Request $request, int $id)
    {
        $trackingNumber = trim((string) $request->query('tracking_number', '')) ?: null;
        $emailHint = trim((string) $request->get('access_email', '')) ?: trim((string) $request->get('email', '')) ?: null;

        // Sanctum: явно guard api-token; без middleware auth:sanctum токен всё равно разбирается.
        $sanctumUser = $request->user('sanctum');
        $user = $sanctumUser;

        // Фолбэк: нет сессии/токена, но есть номер заказа и email покупателя (как в заказе)
        if (!$user && $trackingNumber !== null && $emailHint !== null && $emailHint !== '') {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($emailHint)])
                ->first();
        }

        if (!$user) {
            Log::warning('digital_access.no_authenticated_user', [
                'product_id' => $id,
                'has_tracking' => $trackingNumber !== null,
                'has_email_hint' => $emailHint !== null && $emailHint !== '',
            ]);
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        $debug = $request->boolean('debug');

        Log::info('digital_access.request', [
            'user_id' => $user->id,
            'product_id' => $id,
            'tracking_number' => $trackingNumber,
            'resolved_via_email_hint' => $sanctumUser === null && $user !== null,
            'debug' => $debug,
        ]);

        $product = Product::with('digital_file')->findOrFail($id);
        $debugSnapshot = $this->buildDigitalAccessDebug($user->id, $id, $trackingNumber, $product);

        $grant = $this->digitalAccessGrant->grant($user->id, $id, $trackingNumber);

        if ($debug) {
            if (!$grant['granted']) {
                Log::warning('digital_access.denied_debug', [
                    'user_id' => $user->id,
                    'product_id' => $id,
                    'tracking_number' => $trackingNumber,
                    'reason' => $grant['reason'] ?? 'unknown',
                ]);
                return response()->json([
                    'granted' => false,
                    'error' => 'NO_ACCESS',
                    '_debug' => array_merge($debugSnapshot, ['grant_reason' => $grant['reason'] ?? null]),
                ], 403);
            }
            $access = $this->digitalProductAccess->renderAccess($user, $product);
            $data = $access->getData(true);
            $data['_debug'] = array_merge($debugSnapshot, [
                'grant_via' => $grant['via'],
            ]);
            Log::info('digital_access.granted', [
                'user_id' => $user->id,
                'product_id' => $id,
                'tracking_number' => $trackingNumber,
                'via' => $grant['via'],
                'debug' => true,
            ]);
            return response()->json($data);
        }

        if (!$grant['granted']) {
            Log::warning('digital_access.denied', [
                'user_id' => $user->id,
                'product_id' => $id,
                'tracking_number' => $trackingNumber,
                'reason' => $grant['reason'] ?? 'unknown',
            ]);
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        Log::info('digital_access.granted', [
            'user_id' => $user->id,
            'product_id' => $id,
            'tracking_number' => $trackingNumber,
            'via' => $grant['via'],
        ]);

        return $this->digitalProductAccess->renderAccess($user, $product);
    }

    /**
     * Диагностика для ?debug=1: purchase, order, ordered_files, сверка tracking с БД.
     */
    private function buildDigitalAccessDebug(int $userId, int $productId, ?string $trackingNumber, Product $product): array
    {
        $purchase = Purchase::where('user_id', $userId)
            ->where('product_id', $productId)
            ->first();

        $order = null;
        $orderAnyCustomer = null;
        if ($trackingNumber) {
            $order = Order::where('tracking_number', $trackingNumber)
                ->where('customer_id', $userId)
                ->whereNotIn('order_status', [OrderStatus::CANCELLED])
                ->with(['products.digital_file', 'children.products.digital_file', 'parent_order.products.digital_file'])
                ->first();
            $orderAnyCustomer = Order::where('tracking_number', $trackingNumber)->first();
        }

        $digitalFileId = $product->digital_file?->id;
        $orderedMatching = collect();
        if ($trackingNumber) {
            $orderedMatching = OrderedFile::where('tracking_number', $trackingNumber)
                ->where('customer_id', $userId)
                ->where(function ($q) use ($digitalFileId, $productId) {
                    if ($digitalFileId) {
                        $q->where('digital_file_id', $digitalFileId);
                    }
                    $q->orWhere('digital_file_id', $productId);
                })
                ->get(['id', 'tracking_number', 'digital_file_id', 'customer_id']);
        }

        return [
            'tracking_number_from_request' => $trackingNumber,
            'product_id' => $productId,
            'product_digital_file_id' => $digitalFileId,
            'purchase' => $purchase ? [
                'id' => $purchase->id,
                'user_id' => $purchase->user_id,
                'product_id' => $purchase->product_id,
                'order_id' => $purchase->order_id,
            ] : null,
            'order_for_user' => $order ? [
                'id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'customer_id' => $order->customer_id,
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'tracking_matches_request' => $trackingNumber !== null && $order->tracking_number === $trackingNumber,
                'contains_product' => $this->digitalAccessGrant->orderContainsProduct($order, $productId),
            ] : null,
            'order_by_tracking_any_customer' => $orderAnyCustomer ? [
                'exists' => true,
                'id' => $orderAnyCustomer->id,
                'tracking_number' => $orderAnyCustomer->tracking_number,
                'customer_id' => $orderAnyCustomer->customer_id,
                'request_user_owns_order' => (int) $orderAnyCustomer->customer_id === (int) $userId,
            ] : ['exists' => false],
            'ordered_files_matching_legacy' => $orderedMatching->values()->all(),
        ];
    }

    // TODO : PB laravel dev er code, but need to be checked in Pixer which controller function is applicable
    // public function downloadFile($token)
    // {
    //     try {
    //         try {
    //             $downloadToken = DownloadToken::with('file')->where('token', $token)->first();
    //             if ($downloadToken) {
    //                 $downloadToken->delete();
    //             } else {
    //                 return ['message' => TOKEN_NOT_FOUND];
    //             }
    //         } catch (Exception $e) {
    //             throw new HttpException(404, TOKEN_NOT_FOUND);
    //         }
    //         try {
    //             $mediaItem = Media::findOrFail($downloadToken->file->attachment_id);
    //         } catch (Exception $e) {
    //             return ['message' => NOT_FOUND];
    //         }
    //         return $mediaItem;
    //     } catch (MarvelException $e) {
    //         throw new MarvelException(NOT_FOUND);
    //     }
    // }
}
