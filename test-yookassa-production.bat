@echo off
REM Тест ЮKassa на продакшене api.sancan.ru

echo =======================================
echo Тестирование API ЮKassa на продакшене
echo =======================================
echo.

echo [1] Тестируем GET /api/test-yookassa
curl -X GET https://api.sancan.ru/api/test-yookassa
echo.
echo.

echo [2] Тестируем POST /api/custom-yookassa-order
curl -X POST https://api.sancan.ru/api/custom-yookassa-order ^
  -H "Content-Type: application/json" ^
  -H "Accept: application/json" ^
  -d "{\"name\":\"Test Order\",\"email\":\"test@example.com\",\"phone\":\"+79991234567\",\"amount\":100.00,\"shipping_address\":{\"name\":\"Test User\",\"phone\":\"+79991234567\",\"address\":\"Moscow\",\"delivery_type\":\"courier\"},\"products\":[{\"product_id\":1,\"order_quantity\":1,\"unit_price\":100.00,\"subtotal\":100.00}]}"
echo.
echo.

echo Тестирование завершено
pause

