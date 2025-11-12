<?php
/**
 * Скрипт для очистки файлов событий с дубликатами
 *
 * ВНИМАНИЕ: Запустите этот скрипт ОДИН РАЗ, затем удалите его!
 * Использование: откройте в браузере http://ваш-сайт/cleanup_duplicates.php
 */

// Для безопасности - установите пароль
$PASSWORD = 'cleanup2025'; // Измените на свой пароль

// Проверка пароля
if (!isset($_GET['password']) || $_GET['password'] !== $PASSWORD) {
    die('Доступ запрещен. Используйте: ?password=ваш_пароль');
}

$removed = [];
$errors = [];

// Получаем список всех файлов events_*.json
$files = glob(__DIR__ . '/events_*.json');

foreach ($files as $file) {
    $filename = basename($file);

    // Проверяем, что это файл года (events_2025.json и т.д.)
    if (preg_match('/^events_\d{4}\.json$/', $filename)) {
        // Пытаемся удалить файл
        if (unlink($file)) {
            $removed[] = $filename;
        } else {
            $errors[] = "Не удалось удалить: $filename";
        }
    }
}

// Также удаляем backup файлы
$backupFiles = glob(__DIR__ . '/events_*.backup.json');
foreach ($backupFiles as $file) {
    $filename = basename($file);
    if (unlink($file)) {
        $removed[] = $filename;
    }
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Очистка дубликатов событий</title>
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
        ul { list-style: none; padding: 0; }
        li { padding: 5px 0; }
        li:before { content: "✓ "; color: green; font-weight: bold; }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧹 Очистка дубликатов событий</h1>

        <?php if (count($removed) > 0): ?>
            <div class="success">
                <h2>✅ Успешно удалено файлов: <?= count($removed) ?></h2>
                <ul>
                    <?php foreach ($removed as $file): ?>
                        <li><?= htmlspecialchars($file) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <div class="info">
                <p>Файлов для удаления не найдено. Все уже чисто!</p>
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
            Файл: cleanup_duplicates.php
        </div>

        <div class="info">
            <h3>📋 Что было сделано:</h3>
            <p>
                Удалены все файлы вида events_YYYY.json, которые содержали
                дубликаты базовых событий. Базовый файл events.json остался нетронутым.
            </p>
            <p>
                Теперь события будут сохраняться правильно без дублирования.
            </p>
        </div>
    </div>
</body>
</html>
