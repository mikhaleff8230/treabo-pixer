<?php

namespace Marvel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AuthPhoneController extends CoreController
{
    /**
     * Отправить звонок для авторизации
     */
    public function sendCall(Request $request): JsonResponse
    {
        // TODO: Реализовать отправку звонка для авторизации
        return response()->json([
            'success' => false,
            'message' => 'Метод не реализован'
        ], 501);
    }

    /**
     * Проверить статус авторизации
     */
    public function status(Request $request, string $uuid): JsonResponse
    {
        // TODO: Реализовать проверку статуса
        return response()->json([
            'success' => false,
            'message' => 'Метод не реализован'
        ], 501);
    }

    /**
     * Верифицировать телефон
     */
    public function verify(Request $request): JsonResponse
    {
        // TODO: Реализовать верификацию телефона
        return response()->json([
            'success' => false,
            'message' => 'Метод не реализован'
        ], 501);
    }
}

