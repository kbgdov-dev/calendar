# PHP Backend для календаря

## Обзор

PHP backend предоставляет REST API для:
- 🔐 Авторизации пользователей (`auth.php`)
- 💾 Сохранения событий календаря (`save_events.php`)

## Требования

- PHP 7.0 или выше
- Веб-сервер (Apache, Nginx и т.д.)
- Права на запись в директорию проекта
- Модуль PHP `session`

## Установка и настройка

### 1. Настройка прав доступа

```bash
chmod 755 /path/to/calendar
chmod 644 /path/to/calendar/*.json
chmod 755 /path/to/calendar/users
chmod 755 /path/to/calendar/users/*
chmod 644 /path/to/calendar/users/*/events.json
```

Для Apache:
```bash
chown -R www-data:www-data /path/to/calendar
```

### 2. Настройка веб-сервера

#### Apache

```.htaccess
<Files "auth.php">
    Require all granted
</Files>

<Files "save_events.php">
    Require all granted
</Files>
```

#### Nginx

```nginx
location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
    fastcgi_index index.php;
    include fastcgi_params;
}
```

## API Endpoints

### 1. Авторизация (`auth.php`)

#### POST /auth.php - Вход в систему

**Запрос:**
```json
{
  "username": "anna",
  "password": "password123"
}
```

**Ответ (успех):**
```json
{
  "success": true,
  "message": "Успешный вход",
  "data": {
    "username": "anna",
    "displayName": "Anna"
  }
}
```

**Ответ (ошибка):**
```json
{
  "success": false,
  "message": "Неверный логин или пароль"
}
```

#### GET /auth.php?action=check - Проверка авторизации

**Ответ (авторизован):**
```json
{
  "success": true,
  "data": {
    "username": "anna",
    "displayName": "Anna"
  }
}
```

**Ответ (не авторизован):**
```json
{
  "success": false,
  "message": "Пользователь не авторизован"
}
```

#### GET /auth.php?action=logout - Выход из системы

**Ответ:**
```json
{
  "success": true,
  "message": "Выход выполнен"
}
```

### 2. Сохранение событий (`save_events.php`)

#### POST /save_events.php - Сохранить события

**Важно:** События сохраняются в разные места в зависимости от авторизации:
- **Авторизованные пользователи**: `/users/{username}/events.json`
- **Неавторизованные (инкогнито)**: `events_{year}.json`

**Запрос:**
```json
{
  "year": 2025,
  "events": [
    {
      "title": "Встреча",
      "date": "2025-03-15",
      "time": "14:00",
      "color": "#3498DB"
    },
    {
      "title": "День рождения",
      "date": "2025-05-20",
      "time": "Весь день",
      "color": "#E74C3C"
    }
  ]
}
```

**Параметры:**

| Параметр | Тип | Обязательный | Описание |
|----------|-----|--------------|----------|
| year | number | Да | Год для сохранения (1900-2100) |
| events | array | Да | Массив событий |

**Структура события:**

| Поле | Тип | Обязательное | Описание |
|------|-----|--------------|----------|
| title | string | Да | Название события |
| date | string | Да | Дата в формате YYYY-MM-DD или MM-DD |
| time | string | Да | Время события или "Весь день" |
| color | string | Нет | Цвет в формате #RRGGBB |

**Ответ (успех, авторизован):**
```json
{
  "success": true,
  "message": "События успешно сохранены",
  "data": {
    "year": 2025,
    "count": 2,
    "filename": "users/anna/events.json"
  }
}
```

**Ответ (успех, инкогнито):**
```json
{
  "success": true,
  "message": "События успешно сохранены",
  "data": {
    "year": 2025,
    "count": 2,
    "filename": "events_2025.json"
  }
}
```

**Ответ (ошибка):**
```json
{
  "success": false,
  "message": "Описание ошибки"
}
```

## Архитектура хранения

### Структура файлов

```
calendar/
├── auth.php                    # API авторизации
├── save_events.php             # API сохранения
├── events.json                 # Базовые события (праздники)
├── users.json                  # Список пользователей
├── users/                      # Директория пользователей
│   ├── anna/
│   │   ├── events.json         # Личные события Anna
│   │   └── events.backup.json  # Резервная копия
│   └── konstantin/
│       ├── events.json         # Личные события Konstantin
│       └── events.backup.json  # Резервная копия
├── events_2025.json            # События 2025 (инкогнито)
├── events_2025.backup.json     # Резервная копия
└── error.log                   # Лог ошибок
```

### Логика фильтрации

#### Базовые события (MM-DD)

События с датами в формате `MM-DD` (например, `01-01`) являются **базовыми** (праздники) и:
- Хранятся **только** в `events.json`
- **НЕ сохраняются** в личные файлы пользователей
- **НЕ сохраняются** в файлы инкогнито

#### Пользовательские события (YYYY-MM-DD)

События с датами в формате `YYYY-MM-DD` (например, `2025-05-15`) являются **пользовательскими** и:
- Для авторизованных: сохраняются в `/users/{username}/events.json`
- Для инкогнито: сохраняются в `events_{year}.json`

#### Пример фильтрации

**Входные данные:**
```json
[
  { "date": "01-01", "title": "Новый год" },           // Базовое
  { "date": "2025-05-15", "title": "Встреча" }         // Пользовательское
]
```

**Для авторизованного пользователя Anna:**
```json
// Сохраняется в users/anna/events.json
[
  { "date": "2025-05-15", "title": "Встреча" }         // Только это
]
```

**Для инкогнито:**
```json
// Сохраняется в events_2025.json
[
  { "date": "2025-05-15", "title": "Встреча" }         // Только это
]
```

## Функции безопасности

### 1. Авторизация

- PHP сессии для хранения данных пользователя
- Проверка логина/пароля из `users.json`
- Автоматическое создание директорий пользователей

**Примечание:** В продакшене используйте:
- Хеширование паролей (bcrypt/argon2)
- JWT токены
- Rate limiting

### 2. Валидация данных

**save_events.php:**
- Проверка типов всех полей
- Валидация формата даты (MM-DD или YYYY-MM-DD)
- Валидация формата цвета (#RRGGBB)
- Валидация года (1900-2100)
- Проверка на пустые значения

**auth.php:**
- Проверка обязательных полей
- Защита от SQL-инъекций (не применимо, т.к. используется JSON)

### 3. Защита от XSS

Все текстовые поля экранируются:
```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
```

### 4. Резервное копирование

При каждом сохранении создается резервная копия:
- `events_{year}.backup.json` (для инкогнито)
- `/users/{username}/events.backup.json` (для пользователей)

В случае ошибки записи файл восстанавливается из бэкапа.

### 5. Блокировка файлов

При записи используется `LOCK_EX`:
```php
file_put_contents($filename, $data, LOCK_EX);
```

### 6. Логирование ошибок

Все ошибки записываются в `error.log`:
```php
error_log("[$timestamp] $message\n", 3, $logFile);
```

## Работа с фронтендом

### JavaScript интеграция

#### Проверка авторизации

```javascript
async function checkAuth() {
    const response = await fetch('auth.php?action=check');
    const result = await response.json();

    if (result.success && result.data) {
        currentUser = result.data;
        console.log('Авторизован:', currentUser.displayName);
    } else {
        currentUser = null;
    }
}
```

#### Вход в систему

```javascript
async function login(username, password) {
    const response = await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ username, password })
    });

    const result = await response.json();

    if (result.success) {
        currentUser = result.data;
        showToast(`Добро пожаловать, ${currentUser.displayName}!`, 'success');
    } else {
        showToast(result.message, 'error');
    }
}
```

#### Сохранение событий

```javascript
async function saveEventsToServer(year, events) {
    const response = await fetch('save_events.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ year, events })
    });

    const result = await response.json();

    if (result.success) {
        console.log('Сохранено:', result.data.filename);
        return true;
    } else {
        console.error('Ошибка:', result.message);
        return false;
    }
}
```

## Устранение неполадок

### Ошибка: "Ошибка записи файла на сервер"

**Причина:** Недостаточно прав для записи.

**Решение:**
```bash
chmod 755 /path/to/calendar
chmod 755 /path/to/calendar/users
chmod 755 /path/to/calendar/users/*
```

### Ошибка: "Некорректный формат даты"

**Причина:** Неправильный формат даты.

**Решение:** Используйте `MM-DD` или `YYYY-MM-DD`.

### Ошибка: "Пользователь не авторизован"

**Причина:** Сессия истекла или не создана.

**Решение:**
- Проверьте, что сессии включены в PHP
- Убедитесь, что в `php.ini` `session.auto_start = 1` или вызывается `session_start()`

### Проблема: Базовые события дублируются

**Причина:** Старая версия `save_events.php` без фильтрации.

**Решение:** Обновите код до версии 3.0.0 и запустите скрипт очистки.

### CORS ошибки

**Причина:** Браузер блокирует cross-origin запросы.

**Решение:** В обоих PHP файлах уже установлены заголовки:
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

## Тестирование

### Тест авторизации

```bash
# Вход
curl -X POST http://localhost/calendar/auth.php \
  -H "Content-Type: application/json" \
  -d '{"username":"anna","password":"password123"}' \
  -c cookies.txt

# Проверка
curl -X GET http://localhost/calendar/auth.php?action=check \
  -b cookies.txt

# Выход
curl -X GET http://localhost/calendar/auth.php?action=logout \
  -b cookies.txt
```

### Тест сохранения

```bash
# Сохранение (авторизованный)
curl -X POST http://localhost/calendar/save_events.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{
    "year": 2025,
    "events": [
      {
        "title": "Тест",
        "date": "2025-12-31",
        "time": "23:59",
        "color": "#FF0000"
      }
    ]
  }'

# Сохранение (инкогнито)
curl -X POST http://localhost/calendar/save_events.php \
  -H "Content-Type: application/json" \
  -d '{
    "year": 2025,
    "events": [
      {
        "title": "Тест инкогнито",
        "date": "2025-12-31",
        "time": "23:59",
        "color": "#00FF00"
      }
    ]
  }'
```

## Безопасность для продакшена

### 1. Хеширование паролей

```php
// При создании пользователя
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

// При проверке
if (password_verify($inputPassword, $user['password'])) {
    // Успешный вход
}
```

### 2. JWT токены

```php
// Вместо сессий используйте JWT
$token = generateJWT($user);
header("Authorization: Bearer $token");
```

### 3. Rate limiting

```php
// Ограничение запросов
if (tooManyRequests($userId)) {
    http_response_code(429);
    sendResponse(false, 'Слишком много запросов');
}
```

### 4. HTTPS обязательно

```apache
# .htaccess
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

### 5. CORS ограничения

```php
// Разрешить только определенные домены
$allowed_origins = ['https://yourdomain.com'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
```

## Производительность

- Резервное копирование только при наличии файла
- Блокировка файлов для предотвращения гонок
- JSON форматируется с отступами для читабельности
- Фильтрация базовых событий на уровне PHP

## Миграция с версии 2.x

Если вы обновляетесь с версии 2.x:

1. Обновите `save_events.php` до новой версии
2. Создайте папку `users/` с правильными правами
3. Запустите скрипт `cleanup_user_events.php` (если были личные файлы)
4. Удалите старые файлы `events_{year}.json` если они содержат базовые события

## Лицензия

Этот проект распространяется под лицензией MIT. См. файл LICENSE для подробностей.
