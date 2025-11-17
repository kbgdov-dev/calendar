<?php
/**
 * index.php - Интерактивный календарь с серверной авторизацией
 * Улучшенная версия с обработкой авторизации на стороне сервера
 */

// Запускаем сессию
session_start();

// Обработка выхода
if (isset($_GET['logout'])) {
    unset($_SESSION['user']);
    session_destroy();
    header('Location: index.php');
    exit();
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        // Загружаем пользователей из файла
        $usersFile = __DIR__ . '/users.json';
        if (file_exists($usersFile)) {
            $usersData = file_get_contents($usersFile);
            $users = json_decode($usersData, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($users)) {
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
                    // Перенаправляем на главную страницу
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Неверный логин или пароль';
                }
            }
        } else {
            $error = 'Ошибка загрузки данных пользователей';
        }
    } else {
        $error = 'Введите логин и пароль';
    }
}

// Проверяем, авторизован ли пользователь
$isAuthenticated = isset($_SESSION['user']);
$currentUser = $isAuthenticated ? $_SESSION['user'] : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Интерактивный календарь</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Стили для страницы авторизации */
        .login-page {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .login-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-container h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            color: #333;
            text-align: center;
        }

        .login-container .subtitle {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .login-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .login-container input[type="text"],
        .login-container input[type="password"] {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .login-container input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-container button {
            padding: 12px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .login-container button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .error-message {
            background: #ffe6e6;
            color: #d32f2f;
            padding: 12px 15px;
            border-radius: 8px;
            border-left: 4px solid #d32f2f;
            font-size: 14px;
        }

        .login-info {
            margin-top: 20px;
            padding: 15px;
            background: #f0f8ff;
            border-radius: 8px;
            font-size: 13px;
            color: #555;
        }

        .login-info strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }

        /* Модификация для авторизованного состояния */
        .auth-container-php {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-bottom: 1px solid #ddd;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .auth-container-php .user-name {
            font-weight: 500;
            color: #333;
            font-size: 0.9rem;
        }

        .auth-container-php .logout-link {
            padding: 6px 12px;
            background: #dc3545;
            color: white;
            border-radius: 4px;
            font-size: 0.85rem;
            text-decoration: none;
            transition: background 0.2s;
        }

        .auth-container-php .logout-link:hover {
            background: #c82333;
        }
    </style>
</head>
<body>

<?php if (!$isAuthenticated): ?>
    <!-- Страница авторизации -->
    <div class="login-page">
        <div class="login-container">
            <h1>🗓️ Календарь</h1>
            <p class="subtitle">Войдите для доступа к календарю</p>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <input type="text" name="username" placeholder="Логин" required autocomplete="username">
                <input type="password" name="password" placeholder="Пароль" required autocomplete="current-password">
                <button type="submit" name="login">Войти</button>
            </form>

            <div class="login-info">
                <strong>Демо-доступ:</strong>
                Логин: <code>anna</code> / Пароль: <code>anna123</code><br>
                Логин: <code>konstantin</code> / Пароль: <code>konstantin123</code>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Главная страница календаря -->
    <div class="main-layout">

        <div class="large-month-container">
            <div id="month-view">
                <header class="month-header">
                    <h2 id="month-name"></h2>
                </header>
                <div class="calendar-grid" id="large-month-grid">
                </div>
            </div>
        </div>

        <div class="year-view-container">
            <header class="calendar-header">
                <button id="prev-year" title="Предыдущий год">&lt;</button>
                <input type="number" id="year-input" value="2025" min="1900" max="2100">
                <button id="next-year" title="Следующий год">&gt;</button>
            </header>

            <div id="year-info" class="year-info">
                <!-- Здесь будет информация о годе -->
            </div>

            <!-- Блок авторизации (PHP версия) -->
            <div class="auth-container-php">
                <span class="user-name">👤 <?php echo htmlspecialchars($currentUser['displayName']); ?></span>
                <a href="index.php?logout=1" class="logout-link">Выйти</a>
            </div>

            <div id="year-view">
            </div>
        </div>

    </div>

    <div id="event-modal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-content">
                <button class="modal-close" id="modal-close-btn">&times;</button>
                <h3 id="modal-date"></h3>
                <div id="modal-events-list">
                </div>
            </div>

            <!-- Панель редактирования (скрыта по умолчанию) -->
            <div id="edit-panel" class="edit-panel">
                <div class="edit-panel-scroll-content">
                    <div class="edit-panel-header">
                        <h4>📝 Редактирование события</h4>
                    </div>

                    <form id="edit-form" class="edit-form">
                        <div class="form-group">
                            <label for="edit-title">📛 Название события:</label>
                            <input type="text" id="edit-title" name="title" required placeholder="Введите название">
                        </div>

                        <div class="form-group">
                            <label for="edit-time">⏰ Время:</label>
                            <div class="time-group">
                                <input type="time" id="edit-time" name="time">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="edit-all-day" name="allDay">
                                    <span>Весь день</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>🎨 Цвет:</label>
                            <div class="color-picker" id="color-picker">
                                <label class="color-option">
                                    <input type="radio" name="color" value="#E74C3C">
                                    <span class="color-circle" style="background-color: #E74C3C;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#3498DB">
                                    <span class="color-circle" style="background-color: #3498DB;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#2ECC71">
                                    <span class="color-circle" style="background-color: #2ECC71;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#F39C12">
                                    <span class="color-circle" style="background-color: #F39C12;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#9B59B6">
                                    <span class="color-circle" style="background-color: #9B59B6;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#1ABC9C">
                                    <span class="color-circle" style="background-color: #1ABC9C;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#E67E22">
                                    <span class="color-circle" style="background-color: #E67E22;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#95A5A6">
                                    <span class="color-circle" style="background-color: #95A5A6;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#34495E">
                                    <span class="color-circle" style="background-color: #34495E;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#16A085">
                                    <span class="color-circle" style="background-color: #16A085;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#27AE60">
                                    <span class="color-circle" style="background-color: #27AE60;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#2980B9">
                                    <span class="color-circle" style="background-color: #2980B9;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#8E44AD">
                                    <span class="color-circle" style="background-color: #8E44AD;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#C0392B">
                                    <span class="color-circle" style="background-color: #C0392B;"></span>
                                </label>
                                <label class="color-option">
                                    <input type="radio" name="color" value="#D35400">
                                    <span class="color-circle" style="background-color: #D35400;"></span>
                                </label>
                            </div>
                        </div>

                        <div class="edit-panel-actions">
                            <button type="submit" class="btn-save">💾 Сохранить</button>
                            <button type="button" class="btn-cancel" id="edit-cancel-btn">❌ Отмена</button>
                            <button type="button" class="btn-delete" id="edit-delete-btn">🗑️ Удалить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast уведомления -->
    <div id="toast-container" class="toast-container"></div>

    <!-- Модалка подтверждения удаления -->
    <div id="delete-confirm-modal" class="confirm-modal-overlay">
        <div class="confirm-modal-content">
            <h3>🗑️ Удалить событие?</h3>
            <p>Вы уверены, что хотите удалить это событие? Это действие нельзя отменить.</p>
            <div class="confirm-modal-actions">
                <button id="confirm-delete-btn" class="btn-confirm-delete">Удалить</button>
                <button id="cancel-delete-btn" class="btn-cancel-delete">Отмена</button>
            </div>
        </div>
    </div>

    <script>
        // Передаем данные пользователя из PHP в JavaScript
        window.currentUser = {
            username: '<?php echo htmlspecialchars($currentUser['username']); ?>',
            displayName: '<?php echo htmlspecialchars($currentUser['displayName']); ?>'
        };
    </script>
    <script src="script.js"></script>
<?php endif; ?>

</body>
</html>
