<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceLike;
use Marvel\Http\Controllers\CoreController;

class PlaceLikeController extends CoreController
{
    /**
     * Поставить лайк плейсу
     */
    public function like(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $userId = $request->user()->id;

        // Проверяем, не лайкал ли уже пользователь этот плейс
        $existingLike = PlaceLike::where('place_id', $placeId)
            ->where('user_id', $userId)
            ->first();

        if ($existingLike) {
            return response()->json([
                'message' => 'Вы уже лайкнули этот плейс',
                'liked' => true
            ], 400);
        }

        // Создаем новый лайк
        PlaceLike::create([
            'place_id' => $placeId,
            'user_id' => $userId
        ]);

        return response()->json([
            'message' => 'Плейс лайкнут',
            'liked' => true,
            'likes_count' => $place->likes()->count()
        ]);
    }

    /**
     * Убрать лайк с плейса
     */
    public function unlike(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $userId = $request->user()->id;

        // Находим и удаляем лайк
        $like = PlaceLike::where('place_id', $placeId)
            ->where('user_id', $userId)
            ->first();

        if (!$like) {
            return response()->json([
                'message' => 'Вы не лайкали этот плейс',
                'liked' => false
            ], 400);
        }

        $like->delete();

        return response()->json([
            'message' => 'Лайк убран',
            'liked' => false,
            'likes_count' => $place->likes()->count()
        ]);
    }

    /**
     * Переключить лайк (поставить/убрать)
     */
    public function toggle(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $user = $request->user();
        $ipAddress = $request->ip();
        
        // Получаем или создаем anonymous_id из cookie для анонимных пользователей
        $anonymousId = $request->cookie('anonymous_id');
        if (!$anonymousId && !$user) {
            $anonymousId = 'anon_' . uniqid() . '_' . time();
        }

        // Определяем идентификатор пользователя
        $userId = $user ? $user->id : null;
        
        // Проверяем, есть ли уже лайк
        $query = PlaceLike::where('place_id', $placeId);
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where(function($q) use ($ipAddress, $anonymousId) {
                $q->where('ip_address', $ipAddress)
                  ->orWhere('anonymous_id', $anonymousId);
            });
        }
        $existingLike = $query->first();

        if ($existingLike) {
            // Убираем лайк
            $existingLike->delete();
            $liked = false;
            $message = 'Лайк убран';
        } else {
            // Ставим лайк
            PlaceLike::create([
                'place_id' => $placeId,
                'user_id' => $userId,
                'ip_address' => $userId ? null : $ipAddress,
                'anonymous_id' => $userId ? null : $anonymousId
            ]);
            $liked = true;
            $message = 'Плейс лайкнут';
        }

        $response = response()->json([
            'message' => $message,
            'liked' => $liked,
            'likes_count' => $place->likes()->count()
        ]);
        
        // Устанавливаем cookie для анонимных пользователей
        if (!$user && $anonymousId) {
            $response->cookie('anonymous_id', $anonymousId, 60 * 24 * 365); // 1 год
        }
        
        return $response;
    }

    /**
     * Проверить, лайкнул ли пользователь плейс
     */
    public function check(Request $request, $placeId)
    {
        $user = $request->user();
        $ipAddress = $request->ip();
        $anonymousId = $request->cookie('anonymous_id');
        
        // Определяем идентификатор пользователя
        $userId = $user ? $user->id : null;
        
        // Проверяем, есть ли лайк
        $query = PlaceLike::where('place_id', $placeId);
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where(function($q) use ($ipAddress, $anonymousId) {
                $q->where('ip_address', $ipAddress)
                  ->orWhere('anonymous_id', $anonymousId);
            });
        }
        
        $liked = $query->exists();

        return response()->json([
            'liked' => $liked
        ]);
    }

    /**
     * Получить список пользователей, лайкнувших плейс
     */
    public function likers(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        
        $likers = $place->likes()
            ->with('user:id,name,avatar')
            ->paginate(20);

        return response()->json([
            'data' => $likers->items(),
            'meta' => [
                'current_page' => $likers->currentPage(),
                'last_page' => $likers->lastPage(),
                'per_page' => $likers->perPage(),
                'total' => $likers->total()
            ]
        ]);
    }

    /**
     * Получить список плейсов, лайкнутых текущим пользователем
     */
    public function myLikes(Request $request)
    {
        $userId = $request->user()->id;
        
        $likes = PlaceLike::where('user_id', $userId)
            ->with(['place' => function($query) {
                $query->with(['images', 'videos', 'user', 'hashtags']);
            }])
            ->paginate(20);

        return response()->json([
            'data' => $likes->items(),
            'meta' => [
                'current_page' => $likes->currentPage(),
                'last_page' => $likes->lastPage(),
                'per_page' => $likes->perPage(),
                'total' => $likes->total()
            ]
        ]);
    }
} 