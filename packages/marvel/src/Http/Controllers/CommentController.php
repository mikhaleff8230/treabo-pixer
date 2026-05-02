<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Comment;
use Marvel\Http\Controllers\CoreController;
use Marvel\Http\Requests\Comment\StoreCommentRequest;
use Marvel\Http\Requests\Comment\UpdateCommentRequest;
use Marvel\Http\Resources\CommentResource;
use Marvel\Policies\CommentPolicy;

class CommentController extends CoreController
{
    /**
     * Получить список комментариев
     * Публичный доступ - только approved комментарии
     */
    public function index(Request $request)
    {
        try {
            $commentableType = $request->get('commentable_type');
            $commentableId = $request->get('commentable_id');

            // Валидация обязательных параметров
            if (!$commentableType || !$commentableId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Параметры commentable_type и commentable_id обязательны'
                ], 400);
            }

            // Получаем только одобренные родительские комментарии
            $query = Comment::approved()
                ->parentComments()
                ->forCommentable($commentableType, $commentableId)
                ->with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
                ->orderBy('created_at', 'desc');

            $comments = $query->get();

            Log::info('CommentController::index - комментарии загружены', [
                'commentable_type' => $commentableType,
                'commentable_id' => $commentableId,
                'count' => $comments->count(),
            ]);

            return response()->json([
                'success' => true,
                'data' => CommentResource::collection($comments),
            ]);

        } catch (\Exception $e) {
            Log::error('CommentController::index - ошибка', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при загрузке комментариев'
            ], 500);
        }
    }

    /**
     * Создать новый комментарий
     * Требует авторизации
     */
    public function store(StoreCommentRequest $request)
    {
        try {
            $user = $request->user();

            // Проверяем, существует ли родительский комментарий (если указан)
            if ($request->parent_id) {
                $parentComment = Comment::find($request->parent_id);
                if (!$parentComment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Родительский комментарий не найден'
                    ], 404);
                }

                // Проверяем, что ответ не превышает 1 уровень вложенности
                if ($parentComment->parent_id !== null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Максимальная вложенность комментариев - 1 уровень'
                    ], 400);
                }

                // Наследуем commentable_type и commentable_id от родителя
                $commentableType = $parentComment->commentable_type;
                $commentableId = $parentComment->commentable_id;
            } else {
                $commentableType = $request->commentable_type;
                $commentableId = $request->commentable_id;
            }

            // Создаем комментарий со статусом pending
            $comment = Comment::create([
                'user_id' => $user->id,
                'commentable_type' => $commentableType,
                'commentable_id' => $commentableId,
                'parent_id' => $request->parent_id,
                'body' => $request->body,
                'status' => 'pending',
            ]);

            // Загружаем отношения для ответа
            $comment->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);

            Log::info('CommentController::store - комментарий создан', [
                'comment_id' => $comment->id,
                'user_id' => $user->id,
                'commentable_type' => $commentableType,
                'commentable_id' => $commentableId,
            ]);

            return response()->json([
                'success' => true,
                'data' => new CommentResource($comment),
                'message' => 'Комментарий создан и ожидает модерации'
            ], 201);

        } catch (\Exception $e) {
            Log::error('CommentController::store - ошибка', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при создании комментария'
            ], 500);
        }
    }

    /**
     * Обновить комментарий
     * Требует авторизации и проверки прав через Policy
     */
    public function update(UpdateCommentRequest $request, Comment $comment)
    {
        try {
            $comment->update([
                'body' => $request->body,
            ]);

            // Сбрасываем статус на pending после редактирования
            $comment->status = 'pending';
            $comment->save();

            // Загружаем отношения для ответа
            $comment->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);

            Log::info('CommentController::update - комментарий обновлен', [
                'comment_id' => $comment->id,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'data' => new CommentResource($comment),
                'message' => 'Комментарий обновлен и ожидает модерации'
            ]);

        } catch (\Exception $e) {
            Log::error('CommentController::update - ошибка', [
                'error' => $e->getMessage(),
                'comment_id' => $comment->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении комментария'
            ], 500);
        }
    }

    /**
     * Удалить комментарий (soft delete)
     * Требует авторизации и проверки прав через Policy
     */
    public function destroy(Request $request, Comment $comment)
    {
        try {
            // Проверка прав доступа через Policy
            if (!$request->user()->can('delete', $comment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет прав на удаление этого комментария'
                ], 403);
            }

            $commentId = $comment->id;
            $comment->delete(); // Soft delete

            Log::info('CommentController::destroy - комментарий удален', [
                'comment_id' => $commentId,
                'user_id' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Комментарий удален'
            ]);

        } catch (\Exception $e) {
            Log::error('CommentController::destroy - ошибка', [
                'error' => $e->getMessage(),
                'comment_id' => $comment->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении комментария'
            ], 500);
        }
    }
}

