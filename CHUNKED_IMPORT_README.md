# Chunked Import System - Импорт товаров по частям

## Обзор

Система chunked импорта позволяет безопасно импортировать большие файлы (до 10,000+ SKU) без блокировки сервера. Товары обрабатываются небольшими частями (чанками) в фоновом режиме.

## Возможности

- ✅ **Безопасный импорт** - до 10,000+ товаров без падения сервера
- ✅ **Фоновая обработка** - не блокирует интерфейс
- ✅ **Отслеживание прогресса** - реальное время выполнения
- ✅ **Автоматическое разбиение** - оптимальный размер чанков
- ✅ **Обработка ошибок** - продолжение при сбоях отдельных чанков
- ✅ **Масштабируемость** - поддержка множественных импортов

## Установка и настройка

### 1. Настройка очереди

```bash
# Запустите скрипт настройки
chmod +x queue-setup.sh
./queue-setup.sh
```

### 2. Запуск воркера очереди

```bash
# Ручной запуск (для тестирования)
./start-queue-worker.sh

# Или через Supervisor (рекомендуется)
sudo supervisorctl start laravel-queue-worker:*

# Или через systemd
sudo systemctl start laravel-queue-worker
```

## API Endpoints

### Запуск импорта

```http
POST /api/xml-import/import
Content-Type: multipart/form-data

{
    "xml_file": "file.csv",
    "queue": true,
    "chunked": true,           // Принудительно использовать chunked импорт
    "chunk_size": 100,         // Размер чанка (опционально)
    "options": {
        "shop_id": 1,
        "category_id": 5,
        "update_existing": true
    },
    "field_mapping": {
        "name": "product_name",
        "sku": "article",
        "price": "cost"
    }
}
```

**Ответ:**
```json
{
    "success": true,
    "message": "Chunked import started",
    "data": {
        "token": "uuid-token",
        "total_products": 5000,
        "chunk_size": 100,
        "total_chunks": 50,
        "import_type": "chunked"
    }
}
```

### Отслеживание прогресса

```http
GET /api/xml-import/progress?token=uuid-token
```

**Ответ:**
```json
{
    "success": true,
    "progress": {
        "status": "processing",
        "total_chunks": 50,
        "chunks_completed": 25,
        "progress_percent": 50.0,
        "started_at": "2024-01-15T10:30:00Z",
        "updated_at": "2024-01-15T10:35:00Z"
    },
    "stats": {
        "total": 2500,
        "imported": 2000,
        "updated": 500,
        "errors": 0,
        "chunks_completed": 25,
        "status": "processing"
    }
}
```

### Детальная статистика

```http
GET /api/xml-import/import-stats?token=uuid-token
```

### Список активных импортов

```http
GET /api/xml-import/active-imports
```

### Очистка данных импорта

```http
POST /api/xml-import/cleanup
{
    "token": "uuid-token"
}
```

## Статусы импорта

- `queued` - Импорт поставлен в очередь
- `processing` - Импорт выполняется
- `completed` - Импорт завершен успешно
- `failed` - Импорт завершен с ошибками
- `error` - Критическая ошибка

## Размеры чанков

Система автоматически определяет оптимальный размер чанка:

| Количество товаров | Размер чанка |
|-------------------|--------------|
| ≤ 100            | 10           |
| 101-1000         | 50           |
| 1001-5000        | 100          |
| > 5000           | 200          |

Можно задать свой размер чанка через параметр `chunk_size`.

## Мониторинг и отладка

### Логи

```bash
# Логи воркера очереди
tail -f storage/logs/queue-worker.log

# Логи Laravel
tail -f storage/logs/laravel.log

# Логи конкретного импорта
ls storage/app/xml-import-progress/
ls storage/app/xml-import-stats/
ls storage/app/xml-import-chunks/
```

### Команды для управления очередью

```bash
# Показать неудачные задачи
php artisan queue:failed

# Повторить неудачные задачи
php artisan queue:retry all

# Очистить неудачные задачи
php artisan queue:flush

# Статус очереди
php artisan queue:work --once --verbose
```

## Производительность

### Рекомендуемые настройки сервера

- **CPU**: 2+ ядра
- **RAM**: 4+ GB
- **PHP memory_limit**: 512M+
- **PHP max_execution_time**: 300s
- **MySQL**: InnoDB с оптимизированными настройками

### Настройки PHP

```ini
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M
```

### Настройки MySQL

```sql
-- Для больших импортов
SET innodb_buffer_pool_size = 1G;
SET innodb_log_file_size = 256M;
SET innodb_flush_log_at_trx_commit = 2;
```

## Troubleshooting

### Проблема: Воркер не запускается

```bash
# Проверьте настройки очереди
php artisan config:show queue

# Очистите кеш
php artisan config:clear
php artisan cache:clear

# Проверьте права доступа
chmod -R 775 storage/
```

### Проблема: Импорт зависает

```bash
# Проверьте статус воркера
ps aux | grep queue:work

# Перезапустите воркер
sudo supervisorctl restart laravel-queue-worker:*
```

### Проблема: Недостаточно памяти

```bash
# Уменьшите размер чанка
# В запросе: "chunk_size": 50

# Или увеличьте memory_limit в php.ini
```

## Примеры использования

### Импорт CSV файла

```javascript
// 1. Запуск импорта
const formData = new FormData();
formData.append('xml_file', csvFile);
formData.append('queue', 'true');
formData.append('chunked', 'true');
formData.append('chunk_size', '100');

const response = await fetch('/api/xml-import/import', {
    method: 'POST',
    body: formData
});

const result = await response.json();
const token = result.data.token;

// 2. Отслеживание прогресса
const checkProgress = async () => {
    const progressResponse = await fetch(`/api/xml-import/progress?token=${token}`);
    const progress = await progressResponse.json();
    
    console.log(`Прогресс: ${progress.progress.progress_percent}%`);
    
    if (progress.progress.status === 'completed') {
        console.log('Импорт завершен!');
        return;
    }
    
    // Проверяем каждые 5 секунд
    setTimeout(checkProgress, 5000);
};

checkProgress();
```

### Импорт с настройками

```javascript
const importData = {
    xml_file: file,
    queue: true,
    chunked: true,
    options: {
        shop_id: 1,
        category_id: 5,
        update_existing: true,
        download_images: true
    },
    field_mapping: {
        name: 'product_name',
        sku: 'article',
        price: 'cost',
        description: 'description',
        image: 'image_url'
    }
};
```

## Безопасность

- Все импорты выполняются в изолированных процессах
- Временные файлы автоматически очищаются
- Логирование всех операций
- Ограничение размера файлов (50MB по умолчанию)
- Валидация всех входных данных

## Масштабирование

Для высоконагруженных систем:

1. **Несколько воркеров**: Запустите несколько процессов `queue:work`
2. **Отдельный сервер**: Вынесите обработку очереди на отдельный сервер
3. **Redis кластер**: Используйте Redis для очереди
4. **Горизонтальное масштабирование**: Несколько серверов с общей базой данных


