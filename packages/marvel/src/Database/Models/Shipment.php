<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'service',
        'external_id',
        'tracking_number',
        'barcode',
        'status',
        'external_status',
        'status_description',
        'sender_address',
        'recipient_address',
        'package_info',
        'declared_value',
        'delivery_cost',
        'recipient_info',
        'services',
        'cash_on_delivery',
        'cod_amount',
        'shipped_at',
        'estimated_delivery',
        'delivered_at',
        'api_response',
        'tracking_events',
        'notes',
    ];

    protected $casts = [
        'sender_address' => 'array',
        'recipient_address' => 'array',
        'package_info' => 'array',
        'recipient_info' => 'array',
        'services' => 'array',
        'api_response' => 'array',
        'tracking_events' => 'array',
        'cash_on_delivery' => 'boolean',
        'declared_value' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'cod_amount' => 'decimal:2',
        'shipped_at' => 'datetime',
        'estimated_delivery' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    // Константы статусов
    const STATUS_CREATED = 'created';
    const STATUS_SHIPPED = 'shipped';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RETURNED = 'returned';
    const STATUS_LOST = 'lost';

    // Константы служб доставки
    const SERVICE_SDEK = 'sdek';
    const SERVICE_YANDEX = 'yandex';
    const SERVICE_5POST = '5post';

    /**
     * Связь с заказом
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope для получения отправлений конкретной службы
     */
    public function scopeForService($query, string $service)
    {
        return $query->where('service', $service);
    }

    /**
     * Scope для получения отправлений по статусу
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope для получения активных отправлений
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED, self::STATUS_RETURNED, self::STATUS_LOST]);
    }

    /**
     * Scope для получения завершенных отправлений
     */
    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED, self::STATUS_RETURNED, self::STATUS_LOST]);
    }

    /**
     * Проверяет, можно ли отменить отправление
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_CREATED, self::STATUS_SHIPPED]);
    }

    /**
     * Проверяет, доставлено ли отправление
     */
    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    /**
     * Проверяет, в пути ли отправление
     */
    public function isInTransit(): bool
    {
        return in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_IN_TRANSIT]);
    }

    /**
     * Получает человекочитаемое название статуса
     */
    public function getStatusLabelAttribute(): string
    {
        $statuses = [
            self::STATUS_CREATED => 'Создано',
            self::STATUS_SHIPPED => 'Отправлено',
            self::STATUS_IN_TRANSIT => 'В пути',
            self::STATUS_DELIVERED => 'Доставлено',
            self::STATUS_CANCELLED => 'Отменено',
            self::STATUS_RETURNED => 'Возвращено',
            self::STATUS_LOST => 'Утеряно',
        ];

        return $statuses[$this->status] ?? 'Неизвестный статус';
    }

    /**
     * Получает информацию о службе доставки
     */
    public function getServiceInfoAttribute(): array
    {
        $services = [
            self::SERVICE_SDEK => [
                'name' => 'СДЭК',
                'color' => '#4CAF50',
                'tracking_url' => 'https://www.cdek.ru/track.html?order_id=',
            ],
            self::SERVICE_YANDEX => [
                'name' => 'Яндекс.Доставка',
                'color' => '#FFD700',
                'tracking_url' => 'https://taxi.yandex.ru/delivery/track/',
            ],
            self::SERVICE_5POST => [
                'name' => '5Post',
                'color' => '#FF5722',
                'tracking_url' => 'https://5post.ru/tracking/',
            ],
        ];

        return $services[$this->service] ?? [
            'name' => 'Неизвестная служба',
            'color' => '#2196F3',
            'tracking_url' => '',
        ];
    }

    /**
     * Получает URL для отслеживания
     */
    public function getTrackingUrlAttribute(): string
    {
        $serviceInfo = $this->service_info;
        if (!$this->tracking_number || !$serviceInfo['tracking_url']) {
            return '';
        }

        return $serviceInfo['tracking_url'] . $this->tracking_number;
    }

    /**
     * Обновляет статус отправления
     */
    public function updateStatus(string $status, string $description = null, array $eventData = null): void
    {
        $this->status = $status;
        
        if ($description) {
            $this->status_description = $description;
        }

        // Устанавливаем специальные даты для определенных статусов
        switch ($status) {
            case self::STATUS_SHIPPED:
                if (!$this->shipped_at) {
                    $this->shipped_at = now();
                }
                break;
            case self::STATUS_DELIVERED:
                if (!$this->delivered_at) {
                    $this->delivered_at = now();
                }
                break;
        }

        // Добавляем событие в историю отслеживания
        if ($eventData) {
            $events = $this->tracking_events ?? [];
            $events[] = array_merge($eventData, [
                'timestamp' => now()->toISOString(),
                'status' => $status,
                'description' => $description,
            ]);
            $this->tracking_events = $events;
        }

        $this->save();
    }

    /**
     * Добавляет событие отслеживания
     */
    public function addTrackingEvent(array $eventData): void
    {
        $events = $this->tracking_events ?? [];
        $events[] = array_merge($eventData, [
            'timestamp' => now()->toISOString(),
        ]);
        $this->tracking_events = $events;
        $this->save();
    }

    /**
     * Конвертирует в формат для API ответа
     */
    public function toApiFormat(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'service' => $this->service,
            'service_info' => $this->service_info,
            'external_id' => $this->external_id,
            'tracking_number' => $this->tracking_number,
            'barcode' => $this->barcode,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_description' => $this->status_description,
            'tracking_url' => $this->tracking_url,
            'recipient_address' => $this->recipient_address,
            'package_info' => $this->package_info,
            'declared_value' => $this->declared_value,
            'delivery_cost' => $this->delivery_cost,
            'recipient_info' => $this->recipient_info,
            'cash_on_delivery' => $this->cash_on_delivery,
            'cod_amount' => $this->cod_amount,
            'shipped_at' => $this->shipped_at?->toISOString(),
            'estimated_delivery' => $this->estimated_delivery?->toISOString(),
            'delivered_at' => $this->delivered_at?->toISOString(),
            'tracking_events' => $this->tracking_events,
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
