<?php

namespace Marvel\Policies;

use Marvel\Database\Models\Comment;
use Marvel\Database\Models\User;

class CommentPolicy
{
    /**
     * Определяет, может ли пользователь просматривать комментарий
     */
    public function view(User $user, Comment $comment): bool
    {
        // Публичные комментарии может просматривать любой
        // Одобренные комментарии видны всем
        return $comment->status === 'approved';
    }

    /**
     * Определяет, может ли пользователь создавать комментарии
     */
    public function create(User $user): bool
    {
        // Любой авторизованный пользователь может создавать комментарии
        return true;
    }

    /**
     * Определяет, может ли пользователь обновлять комментарий
     */
    public function update(User $user, Comment $comment): bool
    {
        // Пользователь может редактировать только свои комментарии
        // Или если он администратор
        return $user->id === $comment->user_id || $this->isAdmin($user);
    }

    /**
     * Определяет, может ли пользователь удалять комментарий
     */
    public function delete(User $user, Comment $comment): bool
    {
        // Пользователь может удалять только свои комментарии
        // Или если он администратор
        return $user->id === $comment->user_id || $this->isAdmin($user);
    }

    /**
     * Определяет, может ли пользователь изменять статус комментария
     */
    public function changeStatus(User $user, Comment $comment): bool
    {
        // Только администратор может изменять статус
        return $this->isAdmin($user);
    }

    /**
     * Проверяет, является ли пользователь администратором
     */
    protected function isAdmin(User $user): bool
    {
        // Проверка через permissions или role
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo(\Marvel\Enums\Permission::SUPER_ADMIN);
        }

        // Альтернативная проверка через массив permissions
        if (isset($user->permissions) && is_array($user->permissions)) {
            return in_array('super_admin', $user->permissions);
        }

        // Проверка через role
        if (isset($user->role)) {
            return $user->role === 'super_admin';
        }

        return false;
    }
}

