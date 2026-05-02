<?php

namespace App\Services\Tinkoff\Enums;

enum PaymentStatusEnum: string
{
    case NEW = 'NEW';                           // Создан
    case FORM_SHOWED = 'FORM_SHOWED';           // Платежная форма открыта покупателем
    case DEADLINE_EXPIRED = 'DEADLINE_EXPIRED';  // Просрочен
    case CANCELED = 'CANCELED';                  // Отменен
    case PREAUTHORIZING = 'PREAUTHORIZING';      // Проверка платежных данных
    case AUTHORIZING = 'AUTHORIZING';            // Авторизация
    case AUTHORIZED = 'AUTHORIZED';              // Авторизован
    case AUTH_FAIL = 'AUTH_FAIL';               // Не авторизован
    case REJECTED = 'REJECTED';                  // Отклонен
    case CONFIRMING = 'CONFIRMING';              // Подтверждение
    case CONFIRMED = 'CONFIRMED';                // Подтвержден
    case REFUNDING = 'REFUNDING';                // Возврат
    case REFUNDED = 'REFUNDED';                  // Возвращен
    case PARTIAL_REFUNDED = 'PARTIAL_REFUNDED';  // Частично возвращен
    case REVERSED = 'REVERSED';                  // Отменен после авторизации
    case UNKNOWN = 'UNKNOWN';                    // Неизвестный
}