<?php
/**
 * Скрипт для очистки личных файлов пользователей от базовых событий
 *
 * ВНИМАНИЕ: Запустите этот скрипт ОДИН РАЗ, затем удалите его!
 * Использование: откройте в браузере http://ваш-сайт/cleanup_user_events.php?password=cleanup2025
 */

// Для безопасности - установите пароль
$PASSWORD = 'cleanup2025'; // Измените на свой пароль

// Проверка пароля
if (!isset($_GET['password']) || $_GET['password'] !== $PASSWORD) {
    die('Доступ запрещен. Используйте: ?password=ваш_пароль');
}

$cleaned = [];
$errors = [];
$stats = [
    'total_users' => 0,
    'total_events_before' => 0,
    'total_events_after' => 0,
    'removed_events' => 0
];

// Получаем список всех пользователей
$usersDir = __DIR__ . '/users';

if (!is_dir($usersDir)) {
    die('Папка users не найдена');
}

$userFolders = array_diff(scandir($usersDir), ['.', '..']);

foreach ($userFolders as $username) {
    $userDir = $usersDir . '/' . $username;

    if (!is_dir($userDir)) {
        continue;
    }

    $stats['total_users']++;

    $eventsFile = $userDir . '/events.json';

    if (!file_exists($eventsFile)) {
        continue;
    }

    // Читаем файл событий
    $jsonContent = file_get_contents($eventsFile);
    $events = json_decode($jsonContent, true);

    if (!is_array($events)) {
        $errors[] = "Пользователь $username: некорректный формат JSON";
        continue;
    }

    $eventsBefore = count($events);
    $stats['total_events_before'] += $eventsBefore;

    // Фильтруем события: оставляем только с полной датой (YYYY-MM-DD)
    $filteredEvents = array_filter($events, function($event) {
        // Проверяем формат даты
        if (!isset($event['date'])) {
            return false;
        }

        // Оставляем только события с полной датой (YYYY-MM-DD)
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $event['date']);
    });

    // Переиндексируем массив
    $filteredEvents = array_values($filteredEvents);

    $eventsAfter = count($filteredEvents);
    $removed = $eventsBefore - $eventsAfter;

    $stats['total_events_after'] += $eventsAfter;
    $stats['removed_events'] += $removed;

    if ($removed > 0) {
        // Создаем резервную копию
        $backupFile = $userDir . '/events.backup_before_cleanup.json';
        copy($eventsFile, $backupFile);

        // Сохраняем очищенные события
        $newJson = json_encode($filteredEvents, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($eventsFile, $newJson);

        $cleaned[] = [
            'username' => $username,
            'before' => $eventsBefore,
            'after' => $eventsAfter,
            'removed' => $removed
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Очистка личных файлов от базовых событий</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 { color: #333; }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .stats {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .stat-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Очистка личных файлов от базовых событий</h1>

        <div class="stats">
            <h3>📊 Статистика</h3>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Пользователей обработано</div>
                    <div class="stat-value"><?= $stats['total_users'] ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Событий удалено</div>
                    <div class="stat-value"><?= $stats['removed_events'] ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Событий было</div>
                    <div class="stat-value"><?= $stats['total_events_before'] ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Событий осталось</div>
                    <div class="stat-value"><?= $stats['total_events_after'] ?></div>
                </div>
            </div>
        </div>

        <?php if (count($cleaned) > 0): ?>
            <div class="success">
                <h2>✅ Успешно очищено файлов: <?= count($cleaned) ?></h2>
                <table>
                    <thead>
                        <tr>
                            <th>Пользователь</th>
                            <th>Было событий</th>
                            <th>Осталось</th>
                            <th>Удалено</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cleaned as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['username']) ?></td>
                                <td><?= $item['before'] ?></td>
                                <td><?= $item['after'] ?></td>
                                <td><?= $item['removed'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="info">
                <p>Базовых событий в личных файлах не найдено. Все уже чисто!</p>
            </div>
        <?php endif; ?>

        <?php if (count($errors) > 0): ?>
            <div class="error">
                <h2>❌ Ошибки:</h2>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="warning">
            ⚠️ ВАЖНО: Теперь УДАЛИТЕ этот скрипт с сервера!<br>
            Файл: cleanup_user_events.php
        </div>

        <div class="info">
            <h3>📋 Что было сделано:</h3>
            <p>
                Из личных файлов пользователей удалены все базовые события
                (с датами формата MM-DD). Оставлены только пользовательские события
                с полной датой (YYYY-MM-DD).
            </p>
            <p>
                Резервные копии сохранены в файлы events.backup_before_cleanup.json
                в папке каждого пользователя.
            </p>
            <p>
                Теперь события будут отображаться правильно без дублирования!
            </p>
        </div>
    </div>
</body>
</html>
