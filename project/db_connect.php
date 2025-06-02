<?php
$host = 'localhost';
$dbname = 'u68754';
$username = 'u68754';
$password = '5610469'; // Ваш пароль

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Создаем администратора по умолчанию (если его нет)
    $adminUsername = 'admin';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$adminUsername]);
    
    if (!$stmt->fetch()) {
        $adminEmail = 'admin@lavanda.ru';
        $adminPassword = 'SecurePass123!'; // Замените на надежный пароль
        $hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);
        
        $pdo->prepare("INSERT INTO users (username, email, password_hash, is_admin) 
                      VALUES (?, ?, ?, 1)")
           ->execute([$adminUsername, $adminEmail, $hashedPassword]);
    }
} catch (PDOException $e) {
    die("Ошибка подключения: " . $e->getMessage());
}
?>