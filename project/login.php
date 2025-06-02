<?php
session_start();
require_once 'db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    try {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];
            
            header('Location: ' . ($user['is_admin'] ? 'admin.php' : 'profile.php'));
            exit;
        } else {
            $error = "Неверный логин или пароль";
        }
    } catch (PDOException $e) {
        $error = "Ошибка базы данных: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход | Лавандовый Путь</title>
    <link rel="stylesheet" href="login.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="images/logo.png" alt="Логотип" class="logo">
                <h1>Добро пожаловать</h1>
                <p>Войдите в свой аккаунт</p>
            </div>

            <?php if ($error): ?>
            <div class="alert error">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="Введите ваш логин">
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Введите ваш пароль">
                </div>

                <button type="submit" class="login-btn">Войти</button>
            </form>

            <div class="login-footer">
                <p>Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a></p>
                <a href="forgot-password.php" class="forgot-password">Забыли пароль?</a>
            </div>
        </div>
    </div>
</body>
</html>