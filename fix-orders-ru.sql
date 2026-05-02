-- Исправление языка старых заказов на русский
-- Выполнить: mysql -u marvel_laravel -p marvel_laravel < fix-orders-ru.sql

USE marvel_laravel;

-- Показать текущие языки
SELECT 'Текущие языки в заказах:' as info;
SELECT language, COUNT(*) as count FROM orders GROUP BY language;

-- Исправить все заказы на русский
UPDATE orders SET language = 'ru' WHERE language != 'ru' OR language IS NULL;

-- Показать результат
SELECT 'Результат:' as info;
SELECT language, COUNT(*) as count FROM orders GROUP BY language;

-- Показать последние заказы YooKassa
SELECT 'Последние заказы YooKassa:' as info;
SELECT id, tracking_number, language, payment_gateway, created_at 
FROM orders 
WHERE payment_gateway = 'yookassa' 
ORDER BY created_at DESC 
LIMIT 10;

