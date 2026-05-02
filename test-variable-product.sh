#!/bin/bash

# Скрипт для тестирования создания вариативных товаров
# Использование: ./test-variable-product.sh [API_URL] [TOKEN] [SHOP_ID] [TYPE_ID]

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Параметры по умолчанию
API_URL="${1:-https://api.sancan.ru/api}"
TOKEN="${2:-}"
SHOP_ID="${3:-1}"
TYPE_ID="${4:-1}"

# Проверка SSL (для api.sancan.ru)
SSL_VERIFY=true
if [ "$API_URL" = "https://api.sancan.ru/api" ] || [ "$API_URL" = "https://api.sancan.ru" ]; then
    SSL_VERIFY=true
fi

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Тестирование создания вариативного товара${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Функция для вывода ошибки
error() {
    echo -e "${RED}❌ ОШИБКА: $1${NC}"
}

# Функция для вывода успеха
success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Функция для вывода информации
info() {
    echo -e "${YELLOW}ℹ️  $1${NC}"
}

# Функция для вывода этапа
step() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}ЭТАП $1: $2${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Проверка наличия curl
if ! command -v curl &> /dev/null; then
    error "curl не установлен. Установите curl для продолжения."
    exit 1
fi

# Проверка наличия jq (опционально, для красивого вывода JSON)
HAS_JQ=false
if command -v jq &> /dev/null; then
    HAS_JQ=true
fi

# ЭТАП 1: Проверка доступности API
step "1" "Проверка доступности API"
info "URL: $API_URL"

RESPONSE=$(curl -s -k -o /dev/null -w "%{http_code}" "$API_URL/test/variable-product/check" 2>&1)
if [ "$RESPONSE" = "200" ]; then
    success "API доступен (HTTP $RESPONSE)"
else
    error "API недоступен (HTTP $RESPONSE)"
    info "Проверьте, что сервер запущен и URL правильный"
    exit 1
fi

# ЭТАП 2: Проверка endpoint для проверки данных
step "2" "Проверка endpoint /test/variable-product/check"
CHECK_RESPONSE=$(curl -s -k "$API_URL/test/variable-product/check")
if [ $? -eq 0 ]; then
    success "Endpoint отвечает"
    if [ "$HAS_JQ" = true ]; then
        echo "$CHECK_RESPONSE" | jq '.'
    else
        echo "$CHECK_RESPONSE"
    fi
else
    error "Endpoint не отвечает"
    exit 1
fi

# ЭТАП 3: Проверка авторизации
step "3" "Проверка авторизации"
if [ -z "$TOKEN" ]; then
    error "Токен не указан"
    info "Использование: $0 [API_URL] [TOKEN] [SHOP_ID] [TYPE_ID]"
    info "Или установите переменную окружения: export TOKEN=your_token"
    exit 1
else
    success "Токен указан: ${TOKEN:0:20}..."
fi

# Проверка валидности токена через запрос к защищенному endpoint
AUTH_CHECK=$(curl -s -k -o /dev/null -w "%{http_code}" \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    "$API_URL/test/variable-product" \
    -X POST \
    -d '{}' 2>&1)

if [ "$AUTH_CHECK" = "401" ] || [ "$AUTH_CHECK" = "403" ]; then
    error "Токен невалиден или нет прав доступа (HTTP $AUTH_CHECK)"
    exit 1
elif [ "$AUTH_CHECK" = "422" ] || [ "$AUTH_CHECK" = "500" ]; then
    success "Токен валиден (получен ответ HTTP $AUTH_CHECK - это нормально для пустого запроса)"
else
    info "Статус авторизации: HTTP $AUTH_CHECK"
fi

# ЭТАП 4: Подготовка тестовых данных
step "4" "Подготовка тестовых данных"
info "Shop ID: $SHOP_ID"
info "Type ID: $TYPE_ID"

# Создаем JSON с тестовыми данными
TEST_DATA=$(cat <<EOF
{
  "name": "Тестовый вариативный товар $(date +%s)",
  "product_type": "variable",
  "shop_id": $SHOP_ID,
  "type_id": $TYPE_ID,
  "description": "Тестовое описание",
  "unit": "шт.",
  "status": "draft",
  "variations": [1, 2],
  "variation_options": {
    "upsert": [
      {
        "price": "100.00",
        "quantity": 10,
        "sku": "TEST-$(date +%s)-1",
        "title": "Вариант 1",
        "options": "[{\"name\":\"Цвет\",\"value\":\"Красный\"}]"
      },
      {
        "price": "150.00",
        "quantity": 5,
        "sku": "TEST-$(date +%s)-2",
        "title": "Вариант 2",
        "options": "[{\"name\":\"Цвет\",\"value\":\"Синий\"}]"
      }
    ]
  }
}
EOF
)

success "Тестовые данные подготовлены"
if [ "$HAS_JQ" = true ]; then
    echo "$TEST_DATA" | jq '.'
else
    echo "$TEST_DATA"
fi

# ЭТАП 5: Отправка запроса на создание товара
step "5" "Отправка запроса на создание товара"
info "Отправка POST запроса..."

RESPONSE=$(curl -s -k -w "\n%{http_code}" \
    -X POST \
    -H "Authorization: Bearer $TOKEN" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d "$TEST_DATA" \
    "$API_URL/test/variable-product" 2>&1)

HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

echo ""
info "HTTP Status Code: $HTTP_CODE"

if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "200" ]; then
    success "Товар создан успешно!"
    if [ "$HAS_JQ" = true ]; then
        echo "$BODY" | jq '.'
    else
        echo "$BODY"
    fi
    
    # Извлекаем ID товара если есть
    if [ "$HAS_JQ" = true ]; then
        PRODUCT_ID=$(echo "$BODY" | jq -r '.product.id // empty')
        if [ -n "$PRODUCT_ID" ]; then
            echo ""
            info "ID созданного товара: $PRODUCT_ID"
        fi
    fi
elif [ "$HTTP_CODE" = "422" ]; then
    error "Ошибка валидации (HTTP 422)"
    if [ "$HAS_JQ" = true ]; then
        echo "$BODY" | jq '.'
    else
        echo "$BODY"
    fi
elif [ "$HTTP_CODE" = "500" ]; then
    error "Внутренняя ошибка сервера (HTTP 500)"
    echo "$BODY"
    info "Проверьте логи Laravel: storage/logs/laravel.log"
elif [ "$HTTP_CODE" = "401" ] || [ "$HTTP_CODE" = "403" ]; then
    error "Ошибка авторизации (HTTP $HTTP_CODE)"
    echo "$BODY"
else
    error "Неожиданный статус (HTTP $HTTP_CODE)"
    echo "$BODY"
fi

# ЭТАП 6: Проверка логов Laravel
step "6" "Проверка логов"
info "Проверьте логи Laravel для детальной информации:"
info "tail -f storage/logs/laravel.log | grep 'ProductRepository::storeProduct'"
info "или"
info "tail -f storage/logs/laravel.log | grep 'TEST:'"

echo ""
echo -e "${BLUE}========================================${NC}"
if [ "$HTTP_CODE" = "201" ] || [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✅ Тестирование завершено успешно!${NC}"
    exit 0
else
    echo -e "${RED}❌ Тестирование завершено с ошибками${NC}"
    exit 1
fi

