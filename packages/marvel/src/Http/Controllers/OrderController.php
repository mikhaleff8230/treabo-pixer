<?php

namespace Marvel\Http\Controllers;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Marvel\Traits\WalletsTrait;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Marvel\Enums\Permission;
use Marvel\Enums\OrderStatus;
use Marvel\Enums\PaymentStatus;
use Marvel\Exports\OrderExport;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Purchase;
use Marvel\Database\Models\OrderedFile;
use Maatwebsite\Excel\Facades\Excel;
use Marvel\Database\Models\Settings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Marvel\Database\Repositories\OrderRepository;
use Marvel\Exceptions\MarvelException;
use Marvel\Database\Models\DownloadToken;
use Marvel\Http\Requests\OrderCreateRequest;
use Marvel\Http\Requests\OrderUpdateRequest;
use niklasravnsborg\LaravelPdf\Facades\Pdf as PDF;
use Marvel\Enums\PaymentGatewayType;
use Marvel\Traits\OrderManagementTrait;
use Marvel\Traits\PaymentStatusManagerWithOrderTrait;
use Marvel\Traits\PaymentTrait;
use Marvel\Traits\TranslationTrait;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Marvel\Database\Models\Payment;
use App\Services\YooKassa\YooKassaConfig;
use App\Services\YooKassa\YooKassaService;

class OrderController extends CoreController
{
    use WalletsTrait,
        OrderManagementTrait,
        TranslationTrait,
        PaymentStatusManagerWithOrderTrait,
        PaymentTrait;

    public OrderRepository $repository;
    public Settings $settings;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
        $this->settings = Settings::first();
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Order[]
     */
    public function index(Request $request)
    {
        $limit = $request->limit ? $request->limit : 10;
        return $this->fetchOrders($request)->paginate($limit)->withQueryString();
    }

    /**
     * Clean digital order creation endpoint.
     * POST /orders/create
     */
    public function createDigitalOrder(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        $validated = $request->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
        ]);

        $product = Product::with('digital_file')->findOrFail($validated['productId']);

        if (!(bool) $product->is_digital || !$product->digital_file) {
            return response()->json([
                'message' => 'Доступно только для цифровых товаров с привязанным файлом.',
            ], 422);
        }

        $amount = (float) ($product->sale_price ?? 0) > 0
            ? (float) $product->sale_price
            : (float) ($product->price ?? 0);

        $trackingNumber = $this->repository->generateTrackingNumber();

        $order = DB::transaction(function () use ($user, $product, $amount, $trackingNumber) {
            $order = Order::create([
                'tracking_number' => $trackingNumber,
                'customer_id' => $user->id,
                'customer_contact' => $user->profile?->contact ?? $user->email ?? ('user-' . $user->id),
                'customer_name' => $user->name,
                'amount' => $amount,
                'paid_total' => 0,
                'total' => $amount,
                // Digital-flow mapping: CREATED
                'order_status' => OrderStatus::PENDING,
                'payment_status' => PaymentStatus::PENDING,
                'shipping_address' => null,
                'billing_address' => null,
                'delivery_fee' => 0,
                'sales_tax' => 0,
                'discount' => 0,
                'language' => 'ru',
            ]);

            $order->products()->attach($product->id, [
                'order_quantity' => 1,
                'unit_price' => $amount,
                'subtotal' => $amount,
                'variation_option_id' => null,
            ]);

            return $order->load(['products.digital_file']);
        });

        return response()->json([
            'orderId' => $order->id,
            'trackingNumber' => $order->tracking_number,
            'status' => 'CREATED',
            'order' => $order,
        ]);
    }

    /**
     * Digital payment success endpoint.
     * POST /payments/success
     */
    public function markDigitalPaymentSuccess(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        $validated = $request->validate([
            'orderId' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::with(['products.digital_file'])
            ->where('id', $validated['orderId'])
            ->where('customer_id', $user->id)
            ->firstOrFail();

        DB::transaction(function () use ($order, $user) {
            $order->update([
                // Digital-flow mapping: PAID
                'order_status' => OrderStatus::COMPLETED,
                'payment_status' => PaymentStatus::SUCCESS,
                'paid_total' => $order->total ?? $order->amount ?? 0,
            ]);
            $this->syncOrderPurchasesAndFiles($order, $user->id);
        });

        return response()->json([
            'orderId' => $order->id,
            'status' => 'PAID',
        ]);
    }

    /**
     * Fallback-confirm for YooKassa payments when webhook is delayed/missed.
     * POST /payments/yookassa/confirm
     */
    public function confirmYooKassaPayment(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        $validated = $request->validate([
            'tracking_number' => ['required', 'string'],
            'payment_id' => ['nullable', 'string'],
        ]);

        $order = Order::with(['products.digital_file', 'products.variation_options'])
            ->where('tracking_number', $validated['tracking_number'])
            ->where('customer_id', $user->id)
            ->firstOrFail();

        // Idempotent early exit
        if ($order->payment_status === PaymentStatus::SUCCESS) {
            return response()->json([
                'tracking_number' => $order->tracking_number,
                'status' => 'ALREADY_PAID',
            ]);
        }

        $paymentId = $validated['payment_id'] ?? null;
        if (!$paymentId) {
            $paymentIntent = \Marvel\Database\Models\PaymentIntent::where('order_id', $order->id)
                ->where('payment_gateway', PaymentGatewayType::YOOKASSA)
                ->latest('id')
                ->first();

            $paymentIntentInfo = $paymentIntent?->payment_intent_info;
            if (is_string($paymentIntentInfo)) {
                $paymentIntentInfo = json_decode($paymentIntentInfo, true) ?? [];
            }
            $paymentId = $paymentIntentInfo['payment_id'] ?? null;
        }

        if (!$paymentId) {
            return response()->json([
                'message' => 'Не удалось определить payment_id для проверки оплаты.',
            ], 422);
        }

        $shopId = config('services.yookassa.shop_id');
        $secretKey = config('services.yookassa.secret_key');
        $isTest = config('services.yookassa.is_test', false);

        if (empty($shopId) || empty($secretKey)) {
            return response()->json([
                'message' => 'YooKassa не настроен на сервере.',
            ], 500);
        }

        $service = new YooKassaService(new YooKassaConfig($shopId, $secretKey, $isTest));
        $paymentInfo = $service->checkPayment($paymentId);

        $isPaid = (($paymentInfo['status'] ?? null) === 'succeeded') && (($paymentInfo['paid'] ?? false) === true);
        if (!$isPaid) {
            return response()->json([
                'tracking_number' => $order->tracking_number,
                'status' => 'NOT_PAID',
                'payment_status' => $paymentInfo['status'] ?? null,
            ], 409);
        }

        DB::transaction(function () use ($order, $user) {
            $order->update([
                'order_status' => OrderStatus::COMPLETED,
                'payment_status' => PaymentStatus::SUCCESS,
                'paid_total' => $order->total ?? $order->amount ?? 0,
            ]);

            $this->syncOrderPurchasesAndFiles($order->fresh(['products.digital_file', 'products.variation_options']), $user->id);
        });

        return response()->json([
            'tracking_number' => $order->tracking_number,
            'status' => 'PAID',
        ]);
    }

    private function syncOrderPurchasesAndFiles(Order $order, int $userId): void
    {
        foreach ($order->products as $product) {
            Purchase::firstOrCreate([
                'user_id' => $userId,
                'product_id' => $product->id,
                'order_id' => $order->id,
            ]);

            $digitalFileId = $this->resolveDigitalFileIdForOrderedProduct($product);
            if (!$digitalFileId) {
                continue;
            }

            OrderedFile::firstOrCreate([
                'tracking_number' => $order->tracking_number,
                'customer_id' => $userId,
                'digital_file_id' => $digitalFileId,
            ], [
                'purchase_key' => Str::random(16),
            ]);
        }
    }

    private function resolveDigitalFileIdForOrderedProduct($product): ?int
    {
        $variationOptionId = $product->pivot->variation_option_id ?? null;
        if ($variationOptionId) {
            $variation = Variation::with('digital_file')->find($variationOptionId);
            if ($variation && $variation->is_digital && $variation->digital_file) {
                return (int) $variation->digital_file->id;
            }
        }

        if ((bool) $product->is_digital && $product->digital_file) {
            return (int) $product->digital_file->id;
        }

        return null;
    }

    /**
     * fetchOrders
     *
     * @param mixed $request
     * @return object
     */
    public function fetchOrders(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }

        // Загружаем products с withTrashed для удаленных товаров
        $withRelations = [
            'children',
            'products' => function($q) {
                $q->withTrashed()->with(['variation_options', 'digital_file']);
            }
        ];

        // Нормализуем shop_id (может быть строкой или числом)
        $shopId = null;
        if (isset($request->shop_id) && $request->shop_id !== 'undefined' && $request->shop_id !== null && $request->shop_id !== '') {
            $shopId = is_numeric($request->shop_id) ? (int)$request->shop_id : $request->shop_id;
        }
        
        \Log::info('OrderController::fetchOrders - Начало обработки', [
            'user_id' => $user->id,
            'shop_id_raw' => $request->shop_id ?? 'не указан',
            'shop_id_normalized' => $shopId ?? 'не указан',
            'shop_id_type' => gettype($request->shop_id),
            'is_super_admin' => $user->hasPermissionTo(Permission::SUPER_ADMIN),
            'all_request_params' => $request->all()
        ]);

        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && !$shopId) {
            \Log::info('OrderController::fetchOrders - Запрос для супер-админа (все заказы)');
            return $this->repository->with($withRelations)->where('id', '!=', null)->where('parent_id', '=', null);
        } else if ($shopId) {
            // Проверяем права доступа
            $hasPermission = false;
            $permissionError = null;
            try {
                $hasPermission = $this->repository->hasPermission($user, $shopId);
            } catch (\Exception $e) {
                $permissionError = $e->getMessage();
                \Log::warning('OrderController::fetchOrders - Ошибка проверки прав доступа', [
                    'user_id' => $user->id,
                    'shop_id' => $shopId,
                    'error' => $permissionError
                ]);
            }
            
            if ($hasPermission) {
                // Для продавцов показываем дочерние заказы (child orders) для данного shop_id
                // Дочерние заказы имеют parent_id != null и относятся к конкретному магазину
                $query = $this->repository->with($withRelations)
                    ->where('shop_id', '=', $shopId)
                    ->whereNotNull('parent_id');
                
                // Применяем сортировку (новые сверху по умолчанию)
                $orderBy = $request->orderBy ?? 'created_at';
                $sortedBy = strtoupper($request->sortedBy ?? 'desc');
                $query->orderBy($orderBy, $sortedBy === 'ASC' ? 'asc' : 'desc');
                
                // Логируем количество найденных заказов (без выполнения запроса)
                $ordersCountQuery = clone $query;
                $ordersCount = $ordersCountQuery->count();
                
                \Log::info('OrderController::fetchOrders - Запрос заказов для продавца', [
                    'user_id' => $user->id,
                    'shop_id' => $shopId,
                    'shop_id_original' => $request->shop_id,
                    'orderBy' => $orderBy,
                    'sortedBy' => $sortedBy,
                    'orders_count' => $ordersCount,
                    'has_permission' => true,
                    'query_sql' => $query->toSql(),
                    'query_bindings' => $query->getBindings()
                ]);
                
                return $query;
            } else {
                // Если shop_id указан, но нет прав доступа - возвращаем пустой результат
                \Log::warning('OrderController::fetchOrders - Нет прав доступа к магазину', [
                    'user_id' => $user->id,
                    'shop_id' => $shopId,
                    'permission_error' => $permissionError
                ]);
                
                // Возвращаем пустой запрос с правильной структурой
                $query = $this->repository->with($withRelations)
                    ->where('id', '=', 0); // Заведомо несуществующий ID
                
                return $query;
            }
        } else {
            // Логируем, почему запрос попал в этот блок
            $hasPermissionResult = false;
            $permissionCheckError = null;
            if (isset($request->shop_id) && $request->shop_id !== 'undefined') {
                try {
                    $hasPermissionResult = $this->repository->hasPermission($user, $request->shop_id);
                } catch (\Exception $e) {
                    $permissionCheckError = $e->getMessage();
                }
            }
            
            \Log::warning('OrderController::fetchOrders - Запрос попал в блок для клиентов', [
                'user_id' => $user->id,
                'shop_id' => $request->shop_id ?? 'не указан',
                'shop_id_type' => gettype($request->shop_id),
                'has_permission_result' => $hasPermissionResult,
                'permission_check_error' => $permissionCheckError,
                'is_super_admin' => $user->hasPermissionTo(Permission::SUPER_ADMIN),
                'user_permissions' => $user->getAllPermissions()->pluck('name')->toArray(),
            ]);
            
            // Для обычных пользователей - заказы клиента, сортировка по дате создания (новые сверху)
            $orderBy = $request->orderBy ?? 'created_at';
            $sortedBy = strtoupper($request->sortedBy ?? 'desc');
            
            $query = $this->repository->with($withRelations)
                ->where('customer_id', '=', $user->id)
                ->where('parent_id', '=', null);
            
            // Применяем сортировку
            $query->orderBy('created_at', $sortedBy === 'ASC' ? 'asc' : 'desc');
            
            \Log::info('OrderController::fetchOrders - Запрос для обычного пользователя', [
                'user_id' => $user->id,
                'orderBy' => $orderBy,
                'sortedBy' => $sortedBy,
                'query_sql' => $query->toSql(),
                'query_bindings' => $query->getBindings(),
            ]);
            
            return $query;
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OrderCreateRequest $request
     * @return LengthAwarePaginator|\Illuminate\Support\Collection|mixed
     * @throws MarvelException
     */
    public function store(OrderCreateRequest $request)
    {
        try {
            return DB::transaction(fn () => $this->repository->storeOrder($request, $this->settings));
        } catch (MarvelException $th) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $th->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param $params
     * @return JsonResponse
     * @throws MarvelException
     */
    public function show(Request $request, $params)
    {
        $request["tracking_number"] = $params;
        try {
            return $this->fetchSingleOrder($request);
        } catch (MarvelException $e) {
            throw new MarvelException($e->getMessage());
        }
    }

    /**
     * fetchSingleOrder
     *
     * @param mixed $request
     * @return void
     * @throws MarvelException
     */
    public function fetchSingleOrder(Request $request)
    {
        $user = $request->user() ?? null;
        $language = $request->language ?? DEFAULT_LANGUAGE;
        $orderParam = $request->tracking_number ?? $request->id;
        try {
            $order = $this->repository->where('language', $language)->with([
                'products.digital_file',
                'children.shop',
                'wallet_point',
            ])->where('id', $orderParam)->orWhere('tracking_number', $orderParam)->firstOrFail();
        } catch (ModelNotFoundException $e) {
            throw new ModelNotFoundException(NOT_FOUND);
        }

        // Create Intent
        if (!in_array($order->payment_gateway, [
            PaymentGatewayType::CASH, PaymentGatewayType::CASH_ON_DELIVERY, PaymentGatewayType::FULL_WALLET_PAYMENT
        ])) {
            // $order['payment_intent'] = $this->processPaymentIntent($request, $this->settings);
            $order['payment_intent'] = $this->attachPaymentIntent($orderParam);
        }

        if (!$order->customer_id) {
            return $order;
        }
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN)) {
            return $order;
        } elseif (isset($order->shop_id)) {
            if ($user && ($this->repository->hasPermission($user, $order->shop_id) || $user->id == $order->customer_id)) {
                return $order;
            }
        } elseif ($user && $user->id == $order->customer_id) {
            return $order;
        } else {
            throw new AuthorizationException(NOT_AUTHORIZED);
        }
    }

    /**
     * findByTrackingNumber
     *
     * @param mixed $request
     * @param mixed $tracking_number
     * @return void
     */
    public function findByTrackingNumber(Request $request, $tracking_number)
    {
        $user = $request->user() ?? null;
        try {
            $order = $this->repository->with(['products.digital_file', 'products.variation_options', 'children.shop', 'wallet_point', 'payment_intent'])
                ->findOneByFieldOrFail('tracking_number', $tracking_number);

            if ($order->customer_id === null) {
                return $order;
            }
            if ($user && ($user->id === $order->customer_id || $user->can('super_admin'))) {
                return $order;
            } else {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param OrderUpdateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(OrderUpdateRequest $request, $id)
    {
        try {
            $request["id"] = $id;
            return $this->updateOrder($request);
        } catch (MarvelException $e) {
            throw new MarvelException(COULD_NOT_UPDATE_THE_RESOURCE, $e->getMessage());
        }
    }

    public function updateOrder(OrderUpdateRequest $request)
    {
        return $this->repository->updateOrder($request);
    }

    /**
     * Cancel order by customer
     *
     * @param Request $request
     * @param string $tracking_number
     * @return JsonResponse
     * @throws MarvelException
     */
    public function cancelOrder(Request $request, $tracking_number)
    {
        \Log::info('OrderController::cancelOrder - Начало отмены заказа', [
            'tracking_number' => $tracking_number,
            'user_id' => $request->user()?->id,
        ]);

        try {
            $user = $request->user();
            
            if (!$user) {
                \Log::warning('OrderController::cancelOrder - Пользователь не авторизован', [
                    'tracking_number' => $tracking_number,
                ]);
                return response()->json([
                    'message' => 'Вы не авторизованы. Пожалуйста, войдите в систему.',
                    'error' => 'NOT_AUTHORIZED'
                ], 401);
            }

            \Log::info('OrderController::cancelOrder - Поиск заказа', [
                'tracking_number' => $tracking_number,
                'user_id' => $user->id,
            ]);

            try {
                // Загружаем заказ со всеми необходимыми отношениями, включая дочерние заказы
                $order = $this->repository->with([
                    'payment_intent', 
                    'customer', 
                    'products',
                    'children' // Загружаем дочерние заказы для правильной обработки
                ])->findOneByFieldOrFail('tracking_number', $tracking_number);
            } catch (ModelNotFoundException $e) {
                \Log::warning('OrderController::cancelOrder - Заказ не найден', [
                    'tracking_number' => $tracking_number,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'message' => 'Заказ не найден.',
                    'error' => 'ORDER_NOT_FOUND'
                ], 404);
            }

            // Проверяем, что заказ принадлежит пользователю
            // Если это дочерний заказ, проверяем родительский
            $orderToCheck = $order;
            if ($order->parent_id) {
                // Это дочерний заказ - проверяем родительский
                $parentOrder = Order::find($order->parent_id);
                if ($parentOrder) {
                    $orderToCheck = $parentOrder;
                }
            }
            
            if ($orderToCheck->customer_id !== $user->id) {
                \Log::warning('OrderController::cancelOrder - Попытка отменить чужой заказ', [
                    'tracking_number' => $tracking_number,
                    'user_id' => $user->id,
                    'order_customer_id' => $order->customer_id,
                    'parent_customer_id' => $order->parent_id ? ($orderToCheck->customer_id ?? null) : null,
                ]);
                return response()->json([
                    'message' => 'У вас нет прав для отмены этого заказа.',
                    'error' => 'FORBIDDEN'
                ], 403);
            }

            // Проверяем, что заказ можно отменить (до статуса OUT_FOR_DELIVERY)
            $nonCancellableStatuses = [
                \Marvel\Enums\OrderStatus::OUT_FOR_DELIVERY,
                \Marvel\Enums\OrderStatus::COMPLETED,
                \Marvel\Enums\OrderStatus::CANCELLED,
            ];

            if (in_array($order->order_status, $nonCancellableStatuses)) {
                \Log::info('OrderController::cancelOrder - Заказ нельзя отменить из-за статуса', [
                    'tracking_number' => $tracking_number,
                    'user_id' => $user->id,
                    'order_status' => $order->order_status,
                ]);
                return response()->json([
                    'message' => 'Заказ нельзя отменить. Заказ уже в доставке, выполнен или уже отменен.',
                    'error' => 'ORDER_CANNOT_BE_CANCELLED',
                    'order_status' => $order->order_status,
                ], 400);
            }

            \Log::info('OrderController::cancelOrder - Отмена заказа', [
                'tracking_number' => $tracking_number,
                'user_id' => $user->id,
                'current_status' => $order->order_status,
            ]);

            // Отменяем заказ
            try {
                $this->changeOrderStatus($order, \Marvel\Enums\OrderStatus::CANCELLED);
                
                \Log::info('OrderController::cancelOrder - Заказ успешно отменен', [
                    'tracking_number' => $tracking_number,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'message' => 'Заказ успешно отменен',
                    'order' => $order->fresh()
                ]);
            } catch (\Exception $e) {
                \Log::error('OrderController::cancelOrder - Ошибка при изменении статуса заказа', [
                    'tracking_number' => $tracking_number,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return response()->json([
                    'message' => 'Произошла ошибка при отмене заказа. Попробуйте позже.',
                    'error' => 'STATUS_CHANGE_ERROR'
                ], 500);
            }
        } catch (MarvelException $e) {
            \Log::error('OrderController::cancelOrder - MarvelException', [
                'tracking_number' => $tracking_number,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'MARVEL_EXCEPTION'
            ], 400);
        } catch (\Exception $e) {
            \Log::error('OrderController::cancelOrder - Неожиданная ошибка', [
                'tracking_number' => $tracking_number,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Произошла неожиданная ошибка. Попробуйте позже.',
                'error' => 'INTERNAL_ERROR'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Export order dynamic url
     *
     * @param Request $request
     * @param int $shop_id
     * @return string
     */
    public function exportOrderUrl(Request $request, $shop_id = null)
    {
        try {
            $user = $request->user();

            if ($user && !$this->repository->hasPermission($user, $request->shop_id)) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }

            $dataArray = [
                'user_id' => $user->id,
                'token' => Str::random(16),
                'payload' => $request->shop_id
            ];
            $newToken = DownloadToken::create($dataArray);

            return route('export_order.token', ['token' => $newToken->token]);
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $e->getMessage());
        }
    }

    /**
     * Export order to excel sheet
     *
     * @param string $token
     * @return void
     */
    public function exportOrder($token)
    {
        $shop_id = 0;
        try {
            $downloadToken = DownloadToken::where('token', $token)->first();

            $shop_id = $downloadToken->payload;
            $downloadToken->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(TOKEN_NOT_FOUND);
        }

        try {
            return Excel::download(new OrderExport($this->repository, $shop_id), 'orders.xlsx');
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Export order dynamic url
     *
     * @param Request $request
     * @param int $shop_id
     * @return string
     */
    public function downloadInvoiceUrl(Request $request)
    {

        try {
            $user = $request->user();
            if ($user && !$this->repository->hasPermission($user, $request->shop_id)) {
                throw new AuthorizationException(NOT_AUTHORIZED);
            }
            if (empty($request->order_id)) {
                throw new NotFoundHttpException(NOT_FOUND);
            }
            $language = $request->language ?? DEFAULT_LANGUAGE;
            $isRTL = $request->is_rtl ?? false;

            $translatedText = $this->formatInvoiceTranslateText($request->translated_text);

            $payload = [
                'user_id' => $user->id,
                'order_id' => intval($request->order_id),
                'language' => $language,
                'translated_text' => $translatedText,
                'is_rtl' => $isRTL
            ];

            $data = [
                'user_id' => $user->id,
                'token' => Str::random(16),
                'payload' => serialize($payload)
            ];

            $newToken = DownloadToken::create($data);

            return route('download_invoice.token', ['token' => $newToken->token]);
        } catch (MarvelException $e) {
            throw new MarvelException($e->getMessage());
        }
    }

    /**
     * Export order to excel sheet
     *
     * @param string $token
     * @return void
     */
    public function downloadInvoice($token)
    {
        $payloads = [];
        try {
            $downloadToken = DownloadToken::where('token', $token)->firstOrFail();
            $payloads = unserialize($downloadToken->payload);
            $downloadToken->delete();
        } catch (MarvelException $e) {
            throw new MarvelException(TOKEN_NOT_FOUND);
        }

        try {
            $settings = Settings::getData($payloads['language']);
            $order = $this->repository->with(['products.digital_file', 'products.variation_options', 'children.shop', 'wallet_point', 'parent_order'])->where('id', $payloads['order_id'])->orWhere('tracking_number', $payloads['order_id'])->firstOrFail();

            $invoiceData = [
                'order' => $order,
                'settings' => $settings,
                'translated_text' => $payloads['translated_text'],
                'is_rtl' => $payloads['is_rtl'],
                'language' => $payloads['language'],
            ];
            $pdf = PDF::loadView('pdf.order-invoice', $invoiceData);
            $filename = 'invoice-order-' . $payloads['order_id'] . '.pdf';

            return $pdf->download($filename);
        } catch (MarvelException $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * submitPayment
     *
     * @param mixed $request
     * @return void
     * @throws Exception
     */
    public function submitPayment(Request $request): void
    {
        $tracking_number = $request->tracking_number ?? null;
        if ($request->has('payment_gateway')) {
            $request['payment_gateway'] = strtoupper($request->payment_gateway);
        }
        try {
            $order = $this->repository->with(['products.digital_file', 'products.variation_options', 'children.shop', 'wallet_point', 'payment_intent'])
                ->findOneByFieldOrFail('tracking_number', $tracking_number);

            switch ($order->payment_gateway) {
                case PaymentGatewayType::STRIPE:
                    $this->stripe($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::TINKOFF:
                    $this->tinkoff($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::YOOKASSA:
                    $this->yookassa($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::INTELLECT_MONEY:
                    $this->intellectmoney($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::PAYPAL:
                    $this->paypal($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::MOLLIE:
                    $this->mollie($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::RAZORPAY:
                    $this->razorpay($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::SSLCOMMERZ:
                    $this->sslcommerz($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::PAYSTACK:
                    $this->paystack($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::PAYMONGO:
                    $this->paymongo($order, $request, $this->settings);
                case PaymentGatewayType::XENDIT:
                    $this->xendit($order, $request, $this->settings);
                case PaymentGatewayType::IYZICO:
                    $this->iyzico($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::COINBASE:
                    $this->coinbase($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::BITPAY:
                    $this->bitpay($order, $request, $this->settings);
                case PaymentGatewayType::BKASH:
                    $this->bkash($order, $request, $this->settings);
                    break;
                case PaymentGatewayType::FLUTTERWAVE:
                    $this->flutterwave($order, $request, $this->settings);
                    break;
            }
        } catch (MarvelException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG, $e->getMessage());
        }
    }

    /**
     * Process Tinkoff payment
     */
    protected function tinkoff($order, $request, $settings)
    {
        try {
            $payment = Payment::getIntent([
                'order_id' => $order->id,
                'amount' => $order->total,
                'success_url' => $settings->options['paymentGateway']['tinkoff']['success_url'],
                'cancel_url' => $settings->options['paymentGateway']['tinkoff']['cancel_url'],
            ]);

            return $payment;
        } catch (\Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * Process YooKassa payment
     */
    protected function yookassa($order, $request, $settings)
    {
        try {
            $payment = Payment::getIntent([
                'order_id' => $order->id,
                'amount' => $order->total,
                'success_url' => $settings->options['paymentGateway']['yookassa']['success_url'] ?? '',
                'cancel_url' => $settings->options['paymentGateway']['yookassa']['cancel_url'] ?? '',
            ]);
            return $payment;
        } catch (\Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }
}
