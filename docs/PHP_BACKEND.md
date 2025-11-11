# PHP Backend для сохранения событий календаря

## Обзор

PHP backend предоставляет REST API для сохранения событий календаря в JSON файлы. Файл `save_events.php` обрабатывает POST запросы от фронтенда и сохраняет события в соответствующий файл `events_YYYY.json`.

## Требования

- PHP 7.0 или выше
- Веб-сервер (Apache, Nginx и т.д.)
- Права на запись в директорию проекта

## Установка и настройка

### 1. Настройка прав доступа

Убедитесь, что PHP скрипт имеет права на запись в директорию проекта:

```bash
chmod 755 /path/to/calendar
chmod 644 /path/to/calendar/*.json
```

Если используется Apache, убедитесь, что пользователь `www-data` имеет права на запись:

```bash
chown -R www-data:www-data /path/to/calendar
```

### 2. Настройка веб-сервера

#### Apache

Убедитесь, что `.htaccess` разрешает выполнение PHP файлов:

```apache
<Files "save_events.php">
    Require all granted
</Files>
```

#### Nginx

Добавьте location для PHP файлов:

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

## API Endpoint

### POST /save_events.php

Сохраняет события для указанного года.

#### Формат запроса

```json
{
  "year": 2025,
  "events": [
    {
      "title": "Новый год",
      "date": "2025-01-01",
      "time": "Весь день",
      "color": "#F39C12"
    },
    {
      "title": "Встреча",
      "date": "2025-03-15",
      "time": "14:00",
      "color": "#3498DB"
    }
  ]
}
```

#### Параметры запроса

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| year | number | Да | Год для сохранения событий (1900-2100) |
| events | array | Да | Массив событий для сохранения |

#### Структура события

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
| title | string | Да | Название события |
| date | string | Да | Дата в формате MM-DD или YYYY-MM-DD |
| time | string | Да | Время события или "Весь день" |
| color | string | Нет | Цвет события в формате #RRGGBB |

#### Ответ при успехе

```json
{
  "success": true,
  "message": "События успешно сохранены",
  "data": {
    "year": 2025,
    "count": 15,
    "filename": "events_2025.json"
  }
}
```

#### Ответ при ошибке

```json
{
  "success": false,
  "message": "Описание ошибки"
}
```

#### Коды ответов

- `200 OK` - События успешно сохранены
- `200 OK` (с `success: false`) - Ошибка валидации или сохранения
- `405 Method Not Allowed` - Использован неправильный HTTP метод

## Функции безопасности

### 1. Валидация данных

- Проверка типов данных всех полей
- Проверка формата даты (MM-DD или YYYY-MM-DD)
- Проверка формата цвета (#RRGGBB)
- Валидация года (1900-2100)
- Проверка на пустые значения

### 2. Защита от XSS

Все текстовые поля (title, time) экранируются с помощью `htmlspecialchars()` для предотвращения XSS атак.

### 3. Резервное копирование

При каждом сохранении создается резервная копия существующего файла (`events_YYYY.backup.json`). В случае ошибки записи файл восстанавливается из резервной копии.

### 4. Блокировка файлов

При записи используется флаг `LOCK_EX` для предотвращения одновременной записи в файл несколькими процессами.

### 5. Логирование ошибок

Все ошибки записываются в файл `error.log` в директории проекта.

## Работа с фронтендом

### Автоматическое сохранение

Фронтенд автоматически отправляет данные на сервер при:

1. Создании нового события
2. Редактировании существующего события
3. Удалении события

### Пример использования из JavaScript

```javascript
async function saveEventsToServer(year, events) {
    try {
        const response = await fetch('save_events.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                year: year,
                events: events
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        console.log('События сохранены:', result);
        return true;

    } catch (error) {
        console.error('Ошибка сохранения:', error);
        return false;
    }
}
```

## Структура файлов

```
calendar/
├── save_events.php          # PHP backend для сохранения событий
├── events.json              # Базовые события (праздники)
├── events_2025.json         # События для 2025 года
├── events_2025.backup.json  # Резервная копия
├── error.log                # Лог ошибок
├── script.js                # Фронтенд код
└── docs/
    └── PHP_BACKEND.md       # Эта документация
```

## Устранение неполадок

### Ошибка: "Ошибка записи файла на сервер"

**Причина:** PHP не имеет прав на запись в директорию.

**Решение:**
```bash
chmod 755 /path/to/calendar
chown www-data:www-data /path/to/calendar
```

### Ошибка: "Некорректный формат даты"

**Причина:** Дата передана в неправильном формате.

**Решение:** Используйте формат `MM-DD` (например, `01-15`) или `YYYY-MM-DD` (например, `2025-01-15`).

### Ошибка CORS

**Причина:** Браузер блокирует запросы из-за политики CORS.

**Решение:** Убедитесь, что в `save_events.php` установлены правильные заголовки:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

### Файл не сохраняется

**Проверьте:**
1. Права доступа к директории и файлам
2. Наличие места на диске
3. Лог ошибок `error.log`
4. Логи веб-сервера (Apache/Nginx)

```bash
# Просмотр последних ошибок
tail -f /path/to/calendar/error.log

# Логи Apache
tail -f /var/log/apache2/error.log

# Логи Nginx
tail -f /var/log/nginx/error.log
```

## Тестирование

### Тест с помощью cURL

```bash
curl -X POST http://localhost/calendar/save_events.php \
  -H "Content-Type: application/json" \
  -d '{
    "year": 2025,
    "events": [
      {
        "title": "Тестовое событие",
        "date": "2025-12-31",
        "time": "23:59",
        "color": "#FF0000"
      }
    ]
  }'
```

Ожидаемый ответ:
```json
{
  "success": true,
  "message": "События успешно сохранены",
  "data": {
    "year": 2025,
    "count": 1,
    "filename": "events_2025.json"
  }
}
```

## Производительность

- Резервное копирование выполняется только при наличии существующего файла
- Используется блокировка файлов для предотвращения конфликтов
- JSON форматируется с отступами для удобства чтения

## Безопасность в продакшене

Для использования в продакшене рекомендуется:

1. **Добавить аутентификацию:**
```php
// Проверка токена
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    sendResponse(false, 'Требуется аутентификация');
}
```

2. **Ограничить CORS:**
```php
// Разрешить только определенные домены
header('Access-Control-Allow-Origin: https://yourdomain.com');
```

3. **Добавить rate limiting:**
```php
// Ограничение количества запросов
if (tooManyRequests()) {
    http_response_code(429);
    sendResponse(false, 'Слишком много запросов');
}
```

4. **Использовать HTTPS:**
```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## Лицензия

Этот проект распространяется под лицензией MIT. См. файл LICENSE для подробностей.
