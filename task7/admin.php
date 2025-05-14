<?php
session_start();

// Защита от XSS через заголовки
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Подключение к базе данных
$dsn = 'mysql:host=localhost;dbname=u68754;charset=utf8';
$username = 'u68754';
$password = '5610469';

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Ошибка сервера');
}

// HTTP-авторизация
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Area"');
    die('Требуется авторизация');
}

// Проверка администратора с защитой от SQL-инъекций
$stmt = $pdo->prepare("SELECT * FROM admins WHERE login = ?");
$stmt->execute([$_SERVER['PHP_AUTH_USER']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Доступ запрещен');
}

// Функции с защитой от SQL-инъекций
function getApplications($pdo) {
    $stmt = $pdo->query("
        SELECT 
            a.*, 
            GROUP_CONCAT(l.name) AS languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN programming_languages l ON al.language_id = l.id
        GROUP BY a.id
    ");
    return $stmt->fetchAll();
}

function getLanguageStats($pdo) {
    $stmt = $pdo->query("
        SELECT 
            l.name, 
            COUNT(*) AS count
        FROM application_languages al
        JOIN programming_languages l ON al.language_id = l.id
        GROUP BY l.name
    ");
    return $stmt->fetchAll();
}

// Защита от CSRF
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Обработка данных
$applications = getApplications($pdo);
$stats = getLanguageStats($pdo);

// Обработка удаления с защитой от CSRF и SQL-инъекций
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('Недействительный CSRF-токен');
    }

    $applicationId = filter_input(INPUT_POST, 'application_id', FILTER_VALIDATE_INT);
    if ($applicationId === false || $applicationId === null) {
        http_response_code(400);
        die('Недействительный ID заявки');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$applicationId]);
        $stmt = $pdo->prepare("DELETE FROM applications WHERE id = ?");
        $stmt->execute([$applicationId]);
        $pdo->commit();
        header("Location: admin.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        die('Ошибка сервера');
    }
}

$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
          rel="stylesheet" 
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" 
          crossorigin="anonymous">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="mb-4">Админ-панель</h1>

        <div class="card mb-4">
            <div class="card-header">Статистика языков</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($stats as $row): ?>
                    <li class="list-group-item">
                        <?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>
                        <span class="badge bg-primary rounded-pill">
                            <?= htmlspecialchars($row['count'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="card">
            <div class="card-header">Заявки пользователей</div>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ФИО</th>
                        <th>Телефон</th>
                        <th>Email</th>
                        <th>Дата рождения</th>
                        <th>Пол</th>
                        <th>Языки</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= htmlspecialchars($app['full_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($app['phone'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($app['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($app['birth_date'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($app['gender'] === 'male' ? 'Мужской' : 'Женский', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($app['languages'] ?? 'Нет данных', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <a href="edit_application.php?id=<?= urlencode($app['id']) ?>" 
                                   class="btn btn-sm btn-warning">Редактировать</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="application_id" 
                                           value="<?= htmlspecialchars($app['id'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="csrf_token" 
                                           value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" name="delete_application" 
                                            class="btn btn-sm btn-danger"
                                            onclick="return confirm('Удалить заявку?')">Удалить</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>