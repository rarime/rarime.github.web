<?php
session_start();

// Подключение к базе данных
$dsn = 'mysql:host=localhost;dbname=u68754;charset=utf8';
$username = 'u68754';
$password = '5610469';
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка подключения к базе данных']);
    exit;
}

// Создание таблицы users, если не существует
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            username VARCHAR(255) PRIMARY KEY,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL
        )
    ");
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Ошибка создания таблицы']);
    exit;
}

// Проверка авторизации
if (isset($_GET['check_auth'])) {
    header('Content-Type: application/json');
    if (isset($_SESSION['username'])) {
        $stmt = $pdo->prepare("SELECT username, email, full_name FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            echo json_encode([
                'authenticated' => true,
                'user' => $user
            ]);
        } else {
            echo json_encode(['authenticated' => false]);
        }
    } else {
        echo json_encode(['authenticated' => false]);
    }
    exit;
}

// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.html');
    exit;
}

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Получаем JSON-данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Валидация
    if (!$data || !isset($data['username'], $data['password'], $data['email'], $data['full_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Все поля обязательны']);
        exit;
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Некорректный email']);
        exit;
    }
    
    // Проверка, авторизован ли пользователь
    if (isset($_SESSION['username'])) {
        // Обновление данных авторизованного пользователя
        $username = $_SESSION['username'];
        $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if (!$stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Пользователь не найден']);
            exit;
        }
        
        // Обновляем только email и full_name
        $stmt = $pdo->prepare("UPDATE users SET email = ?, full_name = ? WHERE username = ?");
        $stmt->execute([$data['email'], $data['full_name'], $username]);
        
        echo json_encode(['message' => 'Данные успешно обновлены']);
    } else {
        // Регистрация нового пользователя
        $stmt = $pdo->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) {
            http_response_code(400);
            echo json_encode(['error' => 'Логин уже занят']);
            exit;
        }
        
        // Сохраняем нового пользователя
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name) VALUES (?, ?, ?, ?)");
        $stmt->execute([$data['username'], $data['password'], $data['email'], $data['full_name']]);
        
        // Устанавливаем сессию
        $_SESSION['username'] = $data['username'];
        
        // Формируем ответ
        echo json_encode([
            'username' => $data['username'],
            'password' => $data['password'],
            'profile_url' => '/profile/' . $data['username']
        ]);
    }
    exit;
}
?>