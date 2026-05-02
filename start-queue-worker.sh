#!/bin/bash
# Скрипт для ручного запуска воркера очереди

echo "Запуск воркера очереди..."
echo "Для остановки нажмите Ctrl+C"
echo ""

php artisan queue:work --queue=imports,default --sleep=3 --tries=3 --max-time=3600 --verbose
