<?php
session_start();

header('Content-Type: text/html; charset=UTF-8');

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

// HTTP-авторизация
if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Area"');
    echo '<h1>401: Требуется авторизация</h1>';
    exit;
}

// Проверка логина и пароля администратора
$stmt = $pdo->prepare("SELECT * FROM admins WHERE login = ?");
$stmt->execute([$_SERVER['PHP_AUTH_USER']]);
$admin = $stmt->fetch();

if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403: Доступ запрещен</h1>';
    exit;
}

// Функция для получения всех заявок
function getApplications($pdo) {
    return $pdo->query("
        SELECT 
            a.*, 
            GROUP_CONCAT(l.name) AS languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN programming_languages l ON al.language_id = l.id
        GROUP BY a.id
    ")->fetchAll();
}

// Функция для получения статистики по языкам программирования
function getLanguageStats($pdo) {
    return $pdo->query("
        SELECT 
            l.name, 
            COUNT(*) AS count
        FROM application_languages al
        JOIN programming_languages l ON al.language_id = l.id
        GROUP BY l.name
    ")->fetchAll();
}

// Получение данных
$applications = getApplications($pdo);
$stats = getLanguageStats($pdo);

// Обработка удаления заявки
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_application'])) {
    $applicationId = $_POST['application_id'];
    try {
        $pdo->beginTransaction();
        $pdo->exec("DELETE FROM application_languages WHERE application_id = $applicationId");
        $pdo->exec("DELETE FROM applications WHERE id = $applicationId");
        $pdo->commit();
        header("Location: admin.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        die('Ошибка при удалении заявки: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Админ-панель</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <h1 class="mb-4">Админ-панель</h1>

        <!-- Статистика -->
        <div class="card mb-4">
            <div class="card-header">Статистика языков</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($stats as $row): ?>
                    <li class="list-group-item">
                        <?= htmlspecialchars($row['name']) ?> 
                        <span class="badge bg-primary rounded-pill"><?= $row['count'] ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Список заявок -->
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
                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                            <td><?= htmlspecialchars($app['phone']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td><?= htmlspecialchars($app['birth_date']) ?></td>
                            <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                            <td><?= htmlspecialchars($app['languages'] ?? 'Нет данных') ?></td>
                            <td>
                                <a href="edit_application.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-warning">Редактировать</a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                    <button type="submit" name="delete_application" class="btn btn-sm btn-danger"
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