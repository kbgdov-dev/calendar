<?php
/**
 * API для сохранения событий календаря в JSON файлы
 *
 * Принимает POST запросы с JSON данными и сохраняет события
 * в соответствующий файл events_YEAR.json или в личную папку пользователя
 */

// Запускаем сессию для проверки авторизации
session_start();

// Устанавливаем заголовки для JSON ответа
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Отправляет JSON ответ и завершает выполнение скрипта
 */
function sendResponse($success, $message, $data = null) {
    $response = [
        'success' => $success,
        'message' => $message
    ];

    if ($data !== null) {
        $response['data'] = $data;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Логирование ошибок в файл
 */
function logError($message) {
    $logFile = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message\n";
    error_log($logMessage, 3, $logFile);
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Только POST запросы разрешены');
}

// Получаем тело запроса
$rawInput = file_get_contents('php://input');

if (empty($rawInput)) {
    sendResponse(false, 'Пустое тело запроса');
}

// Декодируем JSON
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(false, 'Ошибка декодирования JSON: ' . json_last_error_msg());
}

// Проверяем наличие обязательных полей
if (!isset($input['year']) || !isset($input['events'])) {
    sendResponse(false, 'Отсутствуют обязательные поля: year и events');
}

$year = $input['year'];
$events = $input['events'];

// Валидация года
if (!is_numeric($year) || $year < 1900 || $year > 2100) {
    sendResponse(false, 'Некорректный год. Допустимый диапазон: 1900-2100');
}

// Валидация событий
if (!is_array($events)) {
    sendResponse(false, 'События должны быть массивом');
}

// Фильтруем события: убираем базовые события (с датами MM-DD) для авторизованных пользователей
$filteredEvents = [];
foreach ($events as $index => $event) {
    // Проверяем обязательные поля
    if (!isset($event['title']) || !isset($event['date']) || !isset($event['time'])) {
        sendResponse(false, "Событие #$index: отсутствуют обязательные поля (title, date, time)");
    }

    // Проверяем типы данных
    if (!is_string($event['title']) || trim($event['title']) === '') {
        sendResponse(false, "Событие #$index: название должно быть непустой строкой");
    }

    if (!is_string($event['date'])) {
        sendResponse(false, "Событие #$index: дата должна быть строкой");
    }

    if (!is_string($event['time'])) {
        sendResponse(false, "Событие #$index: время должно быть строкой");
    }

    // Проверяем формат даты (MM-DD или YYYY-MM-DD)
    $isBaseEvent = preg_match('/^\d{2}-\d{2}$/', $event['date']);
    $isUserEvent = preg_match('/^\d{4}-\d{2}-\d{2}$/', $event['date']);

    if (!$isBaseEvent && !$isUserEvent) {
        sendResponse(false, "Событие #$index: некорректный формат даты. Ожидается MM-DD или YYYY-MM-DD");
    }

    // Проверяем цвет (опционально)
    if (isset($event['color']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $event['color'])) {
        sendResponse(false, "Событие #$index: некорректный формат цвета. Ожидается #RRGGBB");
    }

    // Защита от XSS - экранируем HTML теги
    $event['title'] = htmlspecialchars($event['title'], ENT_QUOTES, 'UTF-8');
    if (isset($event['time'])) {
        $event['time'] = htmlspecialchars($event['time'], ENT_QUOTES, 'UTF-8');
    }

    // Если пользователь авторизован, пропускаем базовые события (MM-DD)
    // Для личных файлов сохраняем только события с полной датой (YYYY-MM-DD)
    if (isset($_SESSION['user']) && $isBaseEvent) {
        continue; // Пропускаем базовое событие
    }

    $filteredEvents[] = $event;
}

// Заменяем массив событий на отфильтрованный
$events = $filteredEvents;

// Определяем путь для сохранения в зависимости от авторизации
if (isset($_SESSION['user']) && isset($_SESSION['user']['username'])) {
    // Пользователь авторизован - сохраняем в его личную папку
    $username = $_SESSION['user']['username'];
    $userDir = __DIR__ . "/users/$username";

    // Проверяем, существует ли папка пользователя
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }

    $filename = $userDir . "/events.json";
} else {
    // Пользователь не авторизован - сохраняем в общий файл года
    $filename = __DIR__ . "/events_$year.json";
}

// Создаем резервную копию, если файл существует
if (file_exists($filename)) {
    if (isset($_SESSION['user']) && isset($_SESSION['user']['username'])) {
        $backupFilename = $userDir . "/events.backup.json";
    } else {
        $backupFilename = __DIR__ . "/events_$year.backup.json";
    }

    if (!copy($filename, $backupFilename)) {
        logError("Не удалось создать резервную копию файла $filename");
        sendResponse(false, 'Ошибка создания резервной копии');
    }
}

// Конвертируем события в JSON
$jsonData = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($jsonData === false) {
    logError("Ошибка кодирования JSON: " . json_last_error_msg());
    sendResponse(false, 'Ошибка кодирования данных в JSON');
}

// Пытаемся записать файл
$bytesWritten = file_put_contents($filename, $jsonData, LOCK_EX);

if ($bytesWritten === false) {
    logError("Не удалось записать файл $filename");

    // Пытаемся восстановить из резервной копии
    if (file_exists($backupFilename)) {
        copy($backupFilename, $filename);
    }

    sendResponse(false, 'Ошибка записи файла на сервер');
}

// Проверяем, что файл действительно записан и читается
$savedData = file_get_contents($filename);
$decodedData = json_decode($savedData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    logError("Файл $filename поврежден после записи");

    // Восстанавливаем из резервной копии
    if (file_exists($backupFilename)) {
        copy($backupFilename, $filename);
    }

    sendResponse(false, 'Ошибка проверки сохраненных данных');
}

// Успешно сохранено
$savedFilename = isset($_SESSION['user']) ? "users/{$_SESSION['user']['username']}/events.json" : "events_$year.json";

sendResponse(true, 'События успешно сохранены', [
    'year' => $year,
    'count' => count($events),
    'filename' => $savedFilename
]);
