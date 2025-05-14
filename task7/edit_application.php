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

// Получение и валидация ID заявки
$applicationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($applicationId === false || $applicationId === null) {
    http_response_code(400);
    die("Недействительный ID заявки");
}

// Получение данных заявки с защитой от SQL-инъекций
$stmt = $pdo->prepare("
    SELECT a.*, GROUP_CONCAT(al.language_id) as languages
    FROM applications a
    LEFT JOIN application_languages al ON a.id = al.application_id
    WHERE a.id = ?
    GROUP BY a.id
");
$stmt->execute([$applicationId]);
$app = $stmt->fetch();

if (!$app) {
    http_response_code(404);
    die("Заявка не найдена");
}

// Получение списка языков
$languagesList = $pdo->query("SELECT * FROM programming_languages")->fetchAll();

// Обработка редактирования с защитой от CSRF и SQL-инъекций
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('Недействительный CSRF-токен');
    }

    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $birth_date = filter_input(INPUT_POST, 'birth_date', FILTER_SANITIZE_STRING);
    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $languages = filter_input(INPUT_POST, 'languages', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
    $biography = trim(filter_input(INPUT_POST, 'biography', FILTER_SANITIZE_STRING));
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;

    // Дополнительная валидация
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        die('Недействительный email');
    }
    if (!in_array($gender, ['male', 'female'])) {
        die('Недействительное значение пола');
    }

    try {
        $pdo->beginTransaction();

        // Обновление заявки
        $stmt = $pdo->prepare("
            UPDATE applications 
            SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                gender = ?, biography = ?, contract_accepted = ?
            WHERE id = ?
        ");
        $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, 
                       $biography, $contract_accepted, $applicationId]);

        // Обновление языков
        $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
        $stmt->execute([$applicationId]);

        $stmt = $pdo->prepare("
            INSERT INTO application_languages (application_id, language_id)
            VALUES (?, ?)
        ");
        foreach ($languages as $lang) {
            $lang = filter_var($lang, FILTER_VALIDATE_INT);
            if ($lang !== false) {
                $stmt->execute([$applicationId, $lang]);
            }
        }

        $pdo->commit();
        header("Location: admin.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        die("Ошибка сервера");
    }
}

// Заполнение значений для формы
$values = [
    'full_name' => $app['full_name'],
    'phone' => $app['phone'],
    'email' => $app['email'],
    'birth_date' => $app['birth_date'],
    'gender' => $app['gender'],
    'languages' => $app['languages'] ? explode(',', $app['languages']) : [],
    'biography' => $app['biography'],
    'contract_accepted' => $app['contract_accepted']
];

$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование заявки</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        textarea {
            resize: vertical;
            height: 100px;
        }
        .radio-group, .checkbox-group {
            margin: 10px 0;
        }
        select[multiple] {
            height: 150px;
        }
        button {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #218838;
        }
        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }
        .auth-link {
            text-align: center;
            margin-top: 20px;
        }
        .auth-link a {
            color: #007bff;
            text-decoration: none;
        }
        .auth-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Редактирование заявки</h1>

    <form action="edit_application.php?id=<?= urlencode($applicationId) ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
        
        <div class="form-group">
            <label for="full_name">ФИО:</label>
            <input type="text" id="full_name" name="full_name" 
                   value="<?= htmlspecialchars($values['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?= htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" 
                   value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="birth_date">Дата рождения:</label>
            <input type="date" id="birth_date" name="birth_date" 
                   value="<?= htmlspecialchars($values['birth_date'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" 
                       <?= $values['gender'] === 'male' ? 'checked' : '' ?> required> Мужской</label>
                <label><input type="radio" name="gender" value="female" 
                       <?= $values['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
        </div>

        <div class="form-group">
            <label for="languages">Любимый язык программирования:</label>
            <select id="languages" name="languages[]" multiple required>
                <?php foreach ($languagesList as $lang): ?>
                    <option value="<?= htmlspecialchars($lang['id'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= in_array($lang['id'], $values['languages']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="biography">Биография:</label>
            <textarea id="biography" name="biography" required><?= htmlspecialchars($values['biography'], ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="contract_accepted" 
                           <?= $values['contract_accepted'] ? 'checked' : '' ?> required>
                    С контрактом ознакомлен(а)
                </label>
            </div>
        </div>

        <button type="submit">Сохранить</button>
        <div class="auth-link">
            <a href="admin.php">Отмена</a>
        </div>
    </form>
</body>
</html>