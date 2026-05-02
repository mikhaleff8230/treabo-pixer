<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceFollow;
use Marvel\Database\Models\User;
use Marvel\Http\Controllers\CoreController;

class PlaceFollowController extends CoreController
{
    /**
     * Подписаться на плейс
     */
    public function follow(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $userId = $request->user()->id;

        // Нельзя подписаться на свой плейс
        if ($place->user_id === $userId) {
            return response()->json([
                'message' => 'Нельзя подписаться на свой плейс',
                'following' => false
            ], 400);
        }

        // Проверяем, не подписан ли уже пользователь
        $existingFollow = PlaceFollow::where('place_id', $placeId)
            ->where('user_id', $userId)
            ->first();

        if ($existingFollow) {
            return response()->json([
                'message' => 'Вы уже подписаны на этот плейс',
                'following' => true
            ], 400);
        }

        // Создаем подписку
        PlaceFollow::create([
            'place_id' => $placeId,
            'user_id' => $userId
        ]);

        return response()->json([
            'message' => 'Подписка оформлена',
            'following' => true,
            'followers_count' => $place->followers()->count()
        ]);
    }

    /**
     * Отписаться от плейса
     */
    public function unfollow(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $userId = $request->user()->id;

        // Находим и удаляем подписку
        $follow = PlaceFollow::where('place_id', $placeId)
            ->where('user_id', $userId)
            ->first();

        if (!$follow) {
            return response()->json([
                'message' => 'Вы не подписаны на этот плейс',
                'following' => false
            ], 400);
        }

        $follow->delete();

        return response()->json([
            'message' => 'Подписка отменена',
            'following' => false,
            'followers_count' => $place->followers()->count()
        ]);
    }

    /**
     * Переключить подписку (подписаться/отписаться)
     */
    public function toggle(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $userId = $request->user()->id;

        // Нельзя подписаться на свой плейс
        if ($place->user_id === $userId) {
            return response()->json([
                'message' => 'Нельзя подписаться на свой плейс',
                'following' => false
            ], 400);
        }

        // Проверяем, есть ли уже подписка
        $existingFollow = PlaceFollow::where('place_id', $placeId)
            ->where('user_id', $userId)
            ->first();

        if ($existingFollow) {
            // Отписываемся
            $existingFollow->delete();
            $following = false;
            $message = 'Подписка отменена';
        } else {
            // Подписываемся
            PlaceFollow::create([
                'place_id' => $placeId,
                'user_id' => $userId
            ]);
            $following = true;
            $message = 'Подписка оформлена';
        }

        return response()->json([
            'message' => $message,
            'following' => $following,
            'followers_count' => $place->followers()->count()
        ]);
    }

    /**
     * Проверить, подписан ли пользователь на плейс
     */
    public function check(Request $request, $placeId)
    {
        $userId = $request->user()->id;
        
        $following = PlaceFollow::where('place_id', $placeId)
            ->where('user_id', $userId)
            ->exists();

        return response()->json([
            'following' => $following
        ]);
    }

    /**
     * Получить список подписчиков плейса
     */
    public function followers(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        
        $followers = $place->followers()
            ->with('user:id,name,avatar')
            ->paginate(20);

        return response()->json([
            'data' => $followers->items(),
            'meta' => [
                'current_page' => $followers->currentPage(),
                'last_page' => $followers->lastPage(),
                'per_page' => $followers->perPage(),
                'total' => $followers->total()
            ]
        ]);
    }

    /**
     * Получить список плейсов, на которые подписан пользователь
     */
    public function following(Request $request)
    {
        $userId = $request->user()->id;
        
        $following = PlaceFollow::where('user_id', $userId)
            ->with(['place.images', 'place.user:id,name,avatar'])
            ->paginate(20);

        return response()->json([
            'data' => $following->items(),
            'meta' => [
                'current_page' => $following->currentPage(),
                'last_page' => $following->lastPage(),
                'per_page' => $following->perPage(),
                'total' => $following->total()
            ]
        ]);
    }
} 