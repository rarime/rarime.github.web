<?php
// Подключение к базе данных
$dsn = 'mysql:host=localhost;dbname=u68754;charset=utf8';
$username = 'u68754';
$password = '5610469';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Данные для нового администратора
$login = 'admin'; // Логин для нового администратора
$password = 'password123'; // Пароль для нового администратора

// Хеширование пароля
$password_hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Вставка нового администратора в таблицу
    $stmt = $pdo->prepare("INSERT INTO admins (login, password_hash) VALUES (:login, :password_hash)");
    $stmt->execute(['login' => $login, 'password_hash' => $password_hash]);
    echo "Новый администратор успешно добавлен!";
} catch (PDOException $e) {
    die('Ошибка при добавлении администратора: ' . $e->getMessage());
}
?>