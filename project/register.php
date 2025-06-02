<?php
session_start();
require_once 'db_connect.php';

// Убедимся, что таблица существует с правильной структурой
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            full_name VARCHAR(100),
            is_admin TINYINT(1) DEFAULT 0,ы
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Если столбец 'name' существует, переименуем его в 'username'
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'name'");
    if ($stmt->fetch()) {
        $pdo->exec("ALTER TABLE users CHANGE name username VARCHAR(50) NOT NULL");
    }
} catch (PDOException $e) {
    die("Ошибка при работе с таблицей: " . $e->getMessage());
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $full_name = trim($_POST['full_name'] ?? '');

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Все обязательные поля должны быть заполнены!";
    } else {
        try {
            // Используем username вместо name
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                $error = "Пользователь с таким логином или email уже существует!";
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $email, $hashed_password, $full_name]);
                
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                header('Location: profile.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = "Ошибка базы данных: " . $e->getMessage();
            
            // Для отладки можно добавить:
            error_log("SQL Error: " . $e->getMessage());
            error_log("SQL Query: " . $stmt->queryString);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация | Лавандовый Путь</title>
    <link rel="stylesheet" href="auth-style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-header">
            <h1>Тур Агенство "Лавандовый Путь"</h1>
            <h2>Создание аккаунта</h2>
        </div>

        <?php if (!empty($error)): ?>
            <div class="auth-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="username">Имя пользователя*</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Пароль*</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="full_name">Полное имя</label>
                <input type="text" id="full_name" name="full_name">
            </div>

            <button type="submit" class="auth-button">Зарегистрироваться</button>
        </form>

        <div class="auth-footer">
            <p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
        </div>
    </div>
</body>
</html>