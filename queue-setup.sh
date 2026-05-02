#!/bin/bash

# Скрипт для настройки очереди импорта товаров
# Запускать от имени пользователя, под которым работает Laravel

echo "=== НАСТРОЙКА ОЧЕРЕДИ ДЛЯ ИМПОРТА ТОВАРОВ ==="
echo ""

# Проверяем, что мы в правильной директории
if [ ! -f "artisan" ]; then
    echo "❌ Ошибка: Запустите скрипт из корневой директории Laravel проекта"
    exit 1
fi

echo "1. Настройка переменных окружения..."

# Создаем .env если его нет
if [ ! -f ".env" ]; then
    echo "❌ Файл .env не найден. Создайте его на основе .env.example"
    exit 1
fi

# Проверяем настройки очереди
QUEUE_CONNECTION=$(grep "QUEUE_CONNECTION" .env | cut -d '=' -f2)
if [ -z "$QUEUE_CONNECTION" ]; then
    echo "QUEUE_CONNECTION=database" >> .env
    echo "✅ Добавлен QUEUE_CONNECTION=database"
else
    echo "ℹ️  QUEUE_CONNECTION уже настроен: $QUEUE_CONNECTION"
fi

# Проверяем настройки Redis (если используется)
if [ "$QUEUE_CONNECTION" = "redis" ]; then
    REDIS_HOST=$(grep "REDIS_HOST" .env | cut -d '=' -f2)
    if [ -z "$REDIS_HOST" ]; then
        echo "REDIS_HOST=127.0.0.1" >> .env
        echo "REDIS_PASSWORD=null" >> .env
        echo "REDIS_PORT=6379" >> .env
        echo "✅ Добавлены настройки Redis"
    fi
fi

echo ""
echo "2. Создание таблиц для очереди..."

# Запускаем миграции
php artisan queue:table
php artisan queue:failed-table
php artisan migrate

echo "✅ Таблицы очереди созданы"

echo ""
echo "3. Создание директорий для импорта..."

# Создаем необходимые директории
mkdir -p storage/app/xml-import-progress
mkdir -p storage/app/xml-import-stats
mkdir -p storage/app/xml-import-chunks
mkdir -p storage/logs

# Устанавливаем права доступа
chmod -R 775 storage/app/xml-import-*
chmod -R 775 storage/logs

echo "✅ Директории созданы"

echo ""
echo "4. Настройка Supervisor (рекомендуется)..."

# Создаем конфигурацию Supervisor
SUPERVISOR_CONFIG="/etc/supervisor/conf.d/laravel-queue-worker.conf"
if [ -w "/etc/supervisor/conf.d/" ]; then
    cat > $SUPERVISOR_CONFIG << EOF
[program:laravel-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=php $(pwd)/artisan queue:work --queue=imports,default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=$(pwd)/storage/logs/queue-worker.log
stopwaitsecs=3600
EOF
    echo "✅ Конфигурация Supervisor создана: $SUPERVISOR_CONFIG"
    echo "ℹ️  Перезапустите Supervisor: sudo supervisorctl reread && sudo supervisorctl update"
else
    echo "⚠️  Не удалось создать конфигурацию Supervisor (нет прав доступа)"
    echo "ℹ️  Создайте файл $SUPERVISOR_CONFIG вручную"
fi

echo ""
echo "5. Создание systemd сервиса (альтернатива Supervisor)..."

SERVICE_FILE="/etc/systemd/system/laravel-queue-worker.service"
if [ -w "/etc/systemd/system/" ]; then
    cat > $SERVICE_FILE << EOF
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php $(pwd)/artisan queue:work --queue=imports,default --sleep=3 --tries=3 --max-time=3600
WorkingDirectory=$(pwd)

[Install]
WantedBy=multi-user.target
EOF
    echo "✅ Systemd сервис создан: $SERVICE_FILE"
    echo "ℹ️  Запустите сервис: sudo systemctl enable laravel-queue-worker && sudo systemctl start laravel-queue-worker"
else
    echo "⚠️  Не удалось создать systemd сервис (нет прав доступа)"
    echo "ℹ️  Создайте файл $SERVICE_FILE вручную"
fi

echo ""
echo "6. Создание скрипта для ручного запуска воркера..."

cat > start-queue-worker.sh << 'EOF'
#!/bin/bash
# Скрипт для ручного запуска воркера очереди

echo "Запуск воркера очереди..."
echo "Для остановки нажмите Ctrl+C"
echo ""

php artisan queue:work --queue=imports,default --sleep=3 --tries=3 --max-time=3600 --verbose
EOF

chmod +x start-queue-worker.sh
echo "✅ Скрипт start-queue-worker.sh создан"

echo ""
echo "7. Тестирование очереди..."

# Очищаем кеш конфигурации
php artisan config:clear
php artisan cache:clear

# Тестируем подключение к очереди
php artisan queue:work --once --timeout=10 > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "✅ Очередь работает корректно"
else
    echo "⚠️  Возможны проблемы с очередью. Проверьте настройки."
fi

echo ""
echo "=== НАСТРОЙКА ЗАВЕРШЕНА ==="
echo ""
echo "Для запуска импорта товаров:"
echo "1. Запустите воркер очереди: ./start-queue-worker.sh"
echo "2. Или используйте Supervisor/systemd (рекомендуется)"
echo ""
echo "Для мониторинга очереди:"
echo "- php artisan queue:work --once (выполнить одну задачу)"
echo "- php artisan queue:failed (показать неудачные задачи)"
echo "- php artisan queue:retry all (повторить неудачные задачи)"
echo ""
echo "Логи воркера: storage/logs/queue-worker.log"
echo "Логи импорта: storage/logs/laravel.log"


