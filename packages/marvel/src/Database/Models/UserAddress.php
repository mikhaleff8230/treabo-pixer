<?php

namespace Marvel\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'source',
        'title',
        'pvz_id',
        'service',
        'name',
        'city',
        'region',
        'region_with_type',
        'country',
        'address',
        'full_address',
        'street',
        'street_with_type',
        'house',
        'flat',
        'postal_code',
        'kladr_id',
        'fias_id',
        'latitude',
        'longitude',
        'phone',
        'work_time',
        'note',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Связь с пользователем
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope для получения только ПВЗ
     */
    public function scopePvz($query)
    {
        return $query->where('type', 'pvz');
    }

    /**
     * Scope для получения домашних адресов
     */
    public function scopeHome($query)
    {
        return $query->where('type', 'home');
    }

    /**
     * Scope для получения адресов, выбранных пользователем вручную
     */
    public function scopeUserSelected($query)
    {
        return $query->where('type', 'user_selected')
                     ->where('source', 'user_selected');
    }

    /**
     * Scope для получения автоопределенных адресов
     */
    public function scopeAutoDetected($query)
    {
        return $query->where('type', 'auto_detected')
                     ->where('source', 'auto_detected');
    }

    /**
     * Scope для получения активных адресов
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope для получения адресов конкретного пользователя
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope для получения адресов по умолчанию
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Устанавливает адрес как адрес по умолчанию
     * Снимает флаг default с других адресов того же типа
     */
    public function setAsDefault(): void
    {
        // Снимаем флаг default с других адресов этого пользователя и типа
        static::where('user_id', $this->user_id)
            ->where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Устанавливаем текущий адрес как default
        $this->update(['is_default' => true]);
    }

    /**
     * Получить форматированный адрес для отображения
     */
    public function getFormattedAddressAttribute(): string
    {
        if ($this->type === 'pvz') {
            return $this->name ? "{$this->name}, {$this->address}" : $this->address;
        }
        
        return $this->address;
    }

    /**
     * Получить информацию о службе доставки
     */
    public function getServiceInfoAttribute(): array
    {
        $services = [
            'sdek' => ['name' => 'СДЭК', 'color' => '#4CAF50'],
            'yandex' => ['name' => 'Яндекс.Доставка', 'color' => '#FFD700'],
            '5post' => ['name' => '5Post', 'color' => '#FF5722'],
        ];

        return $services[$this->service] ?? ['name' => 'Другая служба', 'color' => '#2196F3'];
    }

    /**
     * Конвертирует в формат для API ответа
     */
    public function toApiFormat(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'pvz_id' => $this->pvz_id,
            'service' => $this->service,
            'service_info' => $this->service_info,
            'name' => $this->name,
            'city' => $this->city,
            'address' => $this->address,
            'formatted_address' => $this->formatted_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'work_time' => $this->work_time,
            'note' => $this->note,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
