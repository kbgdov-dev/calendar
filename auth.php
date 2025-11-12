<?php
/**
 * API для авторизации пользователей
 */

// Устанавливаем заголовки для JSON ответа
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Запускаем сессию
session_start();

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
 * Загружает список пользователей из users.json
 */
function loadUsers() {
    $usersFile = __DIR__ . '/users.json';

    if (!file_exists($usersFile)) {
        return [];
    }

    $usersData = file_get_contents($usersFile);
    $users = json_decode($usersData, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }

    return $users;
}

/**
 * Обработчик GET запросов - проверка авторизации
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'check') {
        // Проверяем, авторизован ли пользователь
        if (isset($_SESSION['user'])) {
            sendResponse(true, 'Пользователь авторизован', [
                'username' => $_SESSION['user']['username'],
                'displayName' => $_SESSION['user']['displayName']
            ]);
        } else {
            sendResponse(false, 'Пользователь не авторизован');
        }
    } elseif ($action === 'logout') {
        // Выход из системы
        unset($_SESSION['user']);
        session_destroy();
        sendResponse(true, 'Вы вышли из системы');
    } else {
        sendResponse(false, 'Неизвестное действие');
    }
}

/**
 * Обработчик POST запросов - вход в систему
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');

    if (empty($rawInput)) {
        sendResponse(false, 'Пустое тело запроса');
    }

    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        sendResponse(false, 'Ошибка декодирования JSON: ' . json_last_error_msg());
    }

    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($username) || empty($password)) {
        sendResponse(false, 'Не указаны логин или пароль');
    }

    // Загружаем пользователей
    $users = loadUsers();

    // Ищем пользователя
    $foundUser = null;
    foreach ($users as $user) {
        if ($user['username'] === $username && $user['password'] === $password) {
            $foundUser = $user;
            break;
        }
    }

    if ($foundUser) {
        // Сохраняем данные пользователя в сессии
        $_SESSION['user'] = [
            'username' => $foundUser['username'],
            'displayName' => $foundUser['displayName']
        ];

        sendResponse(true, 'Авторизация успешна', [
            'username' => $foundUser['username'],
            'displayName' => $foundUser['displayName']
        ]);
    } else {
        sendResponse(false, 'Неверный логин или пароль');
    }
}

sendResponse(false, 'Недопустимый метод запроса');
