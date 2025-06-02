<?php
// Database connection
$host = 'localhost';
$dbname = 'u68754';
$username = 'u68754'; // Replace with your MySQL username
$password = '5610469'; // Replace with your MySQL password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $message = $_POST['message'] ?? '';

    // Basic validation
    if (empty($name) || empty($email) || empty($phone)) {
        die("Ошибка: Все обязательные поля должны быть заполнены.");
    }

    // Insert into contacts table
    try {
        $stmt = $pdo->prepare("INSERT INTO contacts (name, email, phone, message) VALUES (:name, :email, :phone, :message)");
        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'message' => $message
        ]);
        // Redirect back to index.html with success message
        header('Location: index.html?success=1');
        exit;
    } catch (PDOException $e) {
        die("Ошибка при сохранении данных: " . $e->getMessage());
    }
} else {
    die("Недопустимый метод запроса.");
}
?>