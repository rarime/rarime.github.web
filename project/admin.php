<?php
session_start();
require_once 'db_connect.php';

// Проверка прав администратора
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit;
}

// Получение данных
$contacts = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC")->fetchAll();
$users = $pdo->query("SELECT id, username, email, full_name, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Установка заголовка HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | Лавандовый Путь</title>
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-container">
        <header class="admin-header">
            <h1>Админ-панель "Лавандовый Путь"</h1>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </header>

        <nav class="admin-nav">
            <button class="nav-btn active" data-tab="contacts">Заявки клиентов</button>
            <button class="nav-btn" data-tab="users">Пользователи</button>
        </nav>

        <main class="admin-content">
            <section id="contacts" class="tab-content active">
                <h2>Заявки клиентов <span class="badge"><?= count($contacts) ?></span></h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Сообщение</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                            <tr>
                                <td><?= htmlspecialchars($contact['id']) ?></td>
                                <td><?= htmlspecialchars($contact['name']) ?></td>
                                <td><?= htmlspecialchars($contact['email']) ?></td>
                                <td><?= htmlspecialchars($contact['phone']) ?></td>
                                <td><?= htmlspecialchars($contact['message'] ?? '') ?></td>
                                <td><?= htmlspecialchars($contact['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="users" class="tab-content">
                <h2>Пользователи <span class="badge"><?= count($users) ?></span></h2>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Логин</th>
                                <th>Email</th>
                                <th>Имя</th>
                                <th>Дата регистрации</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['full_name'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($user['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </main>

        <footer class="admin-footer">
            <p>&copy; <?= date('Y') ?> Тур Агенство "Лавандовый Путь"</p>
        </footer>
    </div>

    <script src="admin.js"></script>
</body>
</html>