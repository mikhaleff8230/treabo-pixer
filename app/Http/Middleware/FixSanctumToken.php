<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware для исправления формата токена Sanctum
 * Исправляет формат {hash}|{id}|{token} на {id}|{token}
 */
class FixSanctumToken
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            // Проверяем формат токена
            $parts = explode('|', $token);
            
            // Если формат {hash}|{id}|{token}, исправляем на {id}|{token}
            if (count($parts) === 3) {
                $correctToken = $parts[1] . '|' . $parts[2];
                
                // Заменяем токен в заголовке
                $request->headers->set('Authorization', 'Bearer ' . $correctToken);
            }
        }
        
        return $next($request);
    }
}

