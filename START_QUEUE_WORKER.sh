#!/bin/bash

# Скрипт для запуска Queue Worker для импорта товаров

echo "🚀 Запуск Queue Worker для импорта товаров..."
echo ""

# Переходим в директорию API
cd "$(dirname "$0")/pixer-api" || exit

# Проверяем запущен ли уже worker
if pgrep -f "queue:work" > /dev/null; then
    echo "⚠️  Queue Worker уже запущен!"
    echo "Процессы:"
    ps aux | grep queue:work | grep -v grep
    echo ""
    read -p "Хотите перезапустить? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "🔄 Останавливаем старые workers..."
        pkill -f "queue:work"
        sleep 2
    else
        exit 0
    fi
fi

# Очищаем застрявшие задачи (опционально)
echo "🧹 Очищаем застрявшие задачи..."
php artisan queue:clear

# Запускаем worker
echo "✅ Запускаем Queue Worker..."
echo ""
echo "Параметры:"
echo "  - tries: 1 (без retry)"
echo "  - timeout: 600 секунд (10 минут на задачу - для изображений)"
echo "  - memory: 2048M"
echo ""

# Запускаем в фоне с неограниченным количеством задач
nohup php artisan queue:work --queue=default --tries=1 --timeout=600 --memory=2048 --sleep=1 --max-jobs=0 --max-time=0 --verbose > storage/logs/queue-worker.log 2>&1 &

WORKER_PID=$!

echo "✅ Queue Worker запущен! PID: $WORKER_PID"
echo ""
echo "📊 Логи:"
echo "  tail -f storage/logs/queue-worker.log"
echo "  tail -f storage/logs/laravel.log"
echo ""
echo "🛑 Остановить worker:"
echo "  kill $WORKER_PID"
echo "  или"
echo "  pkill -f 'queue:work'"
echo ""

# Показываем последние 10 строк лога
sleep 1
echo "📝 Последние логи:"
tail -n 10 storage/logs/queue-worker.log

