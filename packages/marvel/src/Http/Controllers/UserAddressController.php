<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Models\UserAddress;
use Marvel\Http\Requests\UserAddressRequest;
use Illuminate\Http\JsonResponse;

class UserAddressController extends CoreController
{
    /**
     * Получить все адреса пользователя
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $type = $request->query('type'); // 'pvz' или 'home'
        
        $query = UserAddress::forUser($user->id)->active()->with('user');
        
        if ($type) {
            if ($type === 'pvz') {
                $query->pvz();
            } elseif ($type === 'home') {
                $query->home();
            }
        }
        
        $addresses = $query->orderBy('is_default', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();

        return response()->json([
            'data' => $addresses->map(fn($address) => $address->toApiFormat()),
            'total' => $addresses->count()
        ]);
    }

    /**
     * Создать новый адрес
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'type' => 'required|in:pvz,home',
            'title' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'pvz_id' => 'nullable|string|max:255',
            'service' => 'nullable|string|max:50',
            'name' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'work_time' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
        ]);

        $validated['user_id'] = $user->id;

        // Проверяем, нет ли уже такого адреса
        if ($validated['type'] === 'pvz' && isset($validated['pvz_id'])) {
            $existingAddress = UserAddress::forUser($user->id)
                ->where('pvz_id', $validated['pvz_id'])
                ->where('service', $validated['service'])
                ->first();
                
            if ($existingAddress) {
                return response()->json([
                    'error' => 'Этот ПВЗ уже добавлен в ваши адреса',
                    'existing_address' => $existingAddress->toApiFormat()
                ], 422);
            }
        }

        $address = UserAddress::create($validated);

        // Устанавливаем как адрес по умолчанию если указано
        if ($validated['is_default'] ?? false) {
            $address->setAsDefault();
        }

        \Log::info('User address created', [
            'user_id' => $user->id,
            'address_id' => $address->id,
            'type' => $address->type,
            'title' => $address->title
        ]);

        return response()->json([
            'message' => 'Адрес успешно добавлен',
            'data' => $address->fresh()->toApiFormat()
        ], 201);
    }

    /**
     * Получить конкретный адрес
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $address = UserAddress::forUser($user->id)->find($id);

        if (!$address) {
            return response()->json(['error' => 'Адрес не найден'], 404);
        }

        return response()->json([
            'data' => $address->toApiFormat()
        ]);
    }

    /**
     * Обновить адрес
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $address = UserAddress::forUser($user->id)->find($id);

        if (!$address) {
            return response()->json(['error' => 'Адрес не найден'], 404);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'phone' => 'nullable|string|max:20',
            'work_time' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
            'is_default' => 'boolean',
        ]);

        $address->update($validated);

        // Устанавливаем как адрес по умолчанию если указано
        if ($validated['is_default'] ?? false) {
            $address->setAsDefault();
        }

        \Log::info('User address updated', [
            'user_id' => $user->id,
            'address_id' => $address->id,
            'updated_fields' => array_keys($validated)
        ]);

        return response()->json([
            'message' => 'Адрес успешно обновлен',
            'data' => $address->fresh()->toApiFormat()
        ]);
    }

    /**
     * Удалить адрес
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $address = UserAddress::forUser($user->id)->find($id);

        if (!$address) {
            return response()->json(['error' => 'Адрес не найден'], 404);
        }

        $wasDefault = $address->is_default;
        $type = $address->type;

        $address->delete();

        // Если удаленный адрес был по умолчанию, назначаем новый
        if ($wasDefault) {
            $newDefault = UserAddress::forUser($user->id)
                ->where('type', $type)
                ->active()
                ->first();
                
            if ($newDefault) {
                $newDefault->setAsDefault();
            }
        }

        \Log::info('User address deleted', [
            'user_id' => $user->id,
            'address_id' => $id,
            'was_default' => $wasDefault
        ]);

        return response()->json([
            'message' => 'Адрес успешно удален'
        ]);
    }

    /**
     * Установить адрес как адрес по умолчанию
     */
    public function setDefault(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $address = UserAddress::forUser($user->id)->find($id);

        if (!$address) {
            return response()->json(['error' => 'Адрес не найден'], 404);
        }

        $address->setAsDefault();

        \Log::info('User address set as default', [
            'user_id' => $user->id,
            'address_id' => $address->id,
            'type' => $address->type
        ]);

        return response()->json([
            'message' => 'Адрес установлен по умолчанию',
            'data' => $address->fresh()->toApiFormat()
        ]);
    }

    /**
     * Быстрое добавление ПВЗ из карты
     */
    public function addPvzFromMap(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'pvz_id' => 'required|string|max:255',
            'service' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'work_time' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
        ]);

        // Проверяем, нет ли уже такого ПВЗ
        $existingAddress = UserAddress::forUser($user->id)
            ->where('pvz_id', $validated['pvz_id'])
            ->where('service', $validated['service'])
            ->first();
            
        if ($existingAddress) {
            return response()->json([
                'message' => 'Этот ПВЗ уже добавлен в ваши адреса',
                'data' => $existingAddress->toApiFormat()
            ]);
        }

        // Генерируем title если не указан
        if (!isset($validated['title'])) {
            $validated['title'] = "{$validated['service']} - {$validated['name']}";
        }

        $validated['user_id'] = $user->id;
        $validated['type'] = 'pvz';

        $address = UserAddress::create($validated);

        \Log::info('PVZ added from map', [
            'user_id' => $user->id,
            'address_id' => $address->id,
            'pvz_id' => $validated['pvz_id'],
            'service' => $validated['service']
        ]);

        return response()->json([
            'message' => 'ПВЗ успешно добавлен в избранные адреса',
            'data' => $address->toApiFormat()
        ], 201);
    }
}
