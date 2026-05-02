<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Models\Shipment;
use Marvel\Database\Models\Order;
use Marvel\Services\ShipmentService;
use Illuminate\Http\JsonResponse;
use Exception;

class ShipmentController extends CoreController
{
    private ShipmentService $shipmentService;

    public function __construct(ShipmentService $shipmentService)
    {
        $this->shipmentService = $shipmentService;
    }

    /**
     * Получить все отправления
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $service = $request->query('service');
        $status = $request->query('status');
        $orderId = $request->query('order_id');

        $query = Shipment::with('order')->orderBy('created_at', 'desc');

        if ($service) {
            $query->forService($service);
        }

        if ($status) {
            $query->withStatus($status);
        }

        if ($orderId) {
            $query->where('order_id', $orderId);
        }

        $shipments = $query->paginate($perPage);

        return response()->json([
            'data' => $shipments->items(),
            'meta' => [
                'current_page' => $shipments->currentPage(),
                'per_page' => $shipments->perPage(),
                'total' => $shipments->total(),
                'last_page' => $shipments->lastPage(),
            ]
        ]);
    }

    /**
     * Создать отправление для заказа
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'service' => 'required|in:sdek,yandex,5post',
            'recipient_info' => 'required|array',
            'recipient_info.name' => 'required|string|max:255',
            'recipient_info.phone' => 'required|string|max:20',
            'recipient_info.email' => 'nullable|email',
            'recipient_address' => 'required|array',
            'package_info' => 'required|array',
            'package_info.weight' => 'required|numeric|min:1',
            'package_info.length' => 'nullable|numeric|min:1',
            'package_info.width' => 'nullable|numeric|min:1',
            'package_info.height' => 'nullable|numeric|min:1',
            'declared_value' => 'nullable|numeric|min:0',
            'delivery_cost' => 'nullable|numeric|min:0',
            'cash_on_delivery' => 'boolean',
            'cod_amount' => 'nullable|numeric|min:0',
            'tariff_code' => 'nullable|integer',
            'sender_point' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $order = Order::findOrFail($validated['order_id']);

            // Проверяем, нет ли уже отправления для этого заказа
            $existingShipment = Shipment::where('order_id', $order->id)->first();
            if ($existingShipment) {
                return response()->json([
                    'error' => 'Для этого заказа уже создано отправление',
                    'existing_shipment' => $existingShipment->toApiFormat()
                ], 422);
            }

            $shipment = $this->shipmentService->createShipment($order, $validated);

            \Log::info('Shipment created successfully', [
                'shipment_id' => $shipment->id,
                'order_id' => $order->id,
                'service' => $validated['service']
            ]);

            return response()->json([
                'message' => 'Отправление успешно создано',
                'data' => $shipment->toApiFormat()
            ], 201);

        } catch (Exception $e) {
            \Log::error('Failed to create shipment', [
                'order_id' => $validated['order_id'],
                'service' => $validated['service'],
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Ошибка создания отправления: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить конкретное отправление
     */
    public function show(Request $request, $id): JsonResponse
    {
        $shipment = Shipment::with('order')->find($id);

        if (!$shipment) {
            return response()->json(['error' => 'Отправление не найдено'], 404);
        }

        return response()->json([
            'data' => $shipment->toApiFormat()
        ]);
    }

    /**
     * Обновить отправление
     */
    public function update(Request $request, $id): JsonResponse
    {
        $shipment = Shipment::find($id);

        if (!$shipment) {
            return response()->json(['error' => 'Отправление не найдено'], 404);
        }

        $validated = $request->validate([
            'recipient_info' => 'sometimes|array',
            'package_info' => 'sometimes|array',
            'declared_value' => 'sometimes|numeric|min:0',
            'delivery_cost' => 'sometimes|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $shipment->update($validated);

        \Log::info('Shipment updated', [
            'shipment_id' => $shipment->id,
            'updated_fields' => array_keys($validated)
        ]);

        return response()->json([
            'message' => 'Отправление обновлено',
            'data' => $shipment->fresh()->toApiFormat()
        ]);
    }

    /**
     * Отменить отправление
     */
    public function cancel(Request $request, $id): JsonResponse
    {
        $shipment = Shipment::find($id);

        if (!$shipment) {
            return response()->json(['error' => 'Отправление не найдено'], 404);
        }

        if (!$shipment->canBeCancelled()) {
            return response()->json([
                'error' => 'Отправление нельзя отменить в текущем статусе'
            ], 422);
        }

        $reason = $request->input('reason', 'Отменено пользователем');

        $shipment->updateStatus(
            Shipment::STATUS_CANCELLED,
            $reason,
            [
                'cancelled_by' => auth()->user()->id ?? 'system',
                'reason' => $reason
            ]
        );

        \Log::info('Shipment cancelled', [
            'shipment_id' => $shipment->id,
            'reason' => $reason
        ]);

        return response()->json([
            'message' => 'Отправление отменено',
            'data' => $shipment->fresh()->toApiFormat()
        ]);
    }

    /**
     * Обновить статус отправления вручную
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $shipment = Shipment::find($id);

        if (!$shipment) {
            return response()->json(['error' => 'Отправление не найдено'], 404);
        }

        try {
            $this->shipmentService->updateShipmentStatus($shipment);

            return response()->json([
                'message' => 'Статус отправления обновлен',
                'data' => $shipment->fresh()->toApiFormat()
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Ошибка обновления статуса: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить отправления для конкретного заказа
     */
    public function getByOrder(Request $request, $orderId): JsonResponse
    {
        $shipments = Shipment::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => $shipments->map(fn($shipment) => $shipment->toApiFormat()),
            'total' => $shipments->count()
        ]);
    }

    /**
     * Получить статистику отправлений
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from', now()->subDays(30)->startOfDay());
        $dateTo = $request->query('date_to', now()->endOfDay());

        $stats = [
            'total' => Shipment::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'by_status' => Shipment::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),
            'by_service' => Shipment::whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('service, COUNT(*) as count')
                ->groupBy('service')
                ->get()
                ->pluck('count', 'service'),
            'delivered_today' => Shipment::whereDate('delivered_at', now()->toDateString())->count(),
            'in_transit' => Shipment::active()->count(),
        ];

        return response()->json([
            'data' => $stats,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ]);
    }

    /**
     * Webhook для обновления статусов от служб доставки
     */
    public function webhook(Request $request): JsonResponse
    {
        $service = $request->query('service');
        $signature = $request->header('X-Signature');

        \Log::info('Webhook received', [
            'service' => $service,
            'signature' => $signature,
            'payload' => $request->all()
        ]);

        try {
            switch ($service) {
                case 'sdek':
                    return $this->handleCdekWebhook($request);
                case 'yandex':
                    return $this->handleYandexWebhook($request);
                case '5post':
                    return $this->handle5PostWebhook($request);
                default:
                    return response()->json(['error' => 'Unknown service'], 400);
            }
        } catch (Exception $e) {
            \Log::error('Webhook processing failed', [
                'service' => $service,
                'error' => $e->getMessage()
            ]);

            return response()->json(['error' => 'Processing failed'], 500);
        }
    }

    /**
     * Обработка webhook от СДЭК
     */
    private function handleCdekWebhook(Request $request): JsonResponse
    {
        $data = $request->all();

        if (!isset($data['uuid'])) {
            return response()->json(['error' => 'Missing UUID'], 400);
        }

        $shipment = Shipment::where('external_id', $data['uuid'])->first();

        if (!$shipment) {
            \Log::warning('Shipment not found for webhook', ['uuid' => $data['uuid']]);
            return response()->json(['error' => 'Shipment not found'], 404);
        }

        // Обновляем статус отправления
        $this->shipmentService->updateShipmentStatus($shipment);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Заглушки для webhook других служб
     */
    private function handleYandexWebhook(Request $request): JsonResponse
    {
        // TODO: Реализовать обработку webhook Яндекс.Доставка
        return response()->json(['status' => 'ok']);
    }

    private function handle5PostWebhook(Request $request): JsonResponse
    {
        // TODO: Реализовать обработку webhook 5Post
        return response()->json(['status' => 'ok']);
    }
}
