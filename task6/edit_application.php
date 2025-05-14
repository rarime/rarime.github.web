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

// Получение ID заявки
$applicationId = $_GET['id'] ?? null;

if (!$applicationId) {
    die("Заявка не найдена");
}

// Получение данных заявки
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
    die("Заявка не найдена");
}

// Обработка редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $birth_date = $_POST['birth_date'];
    $gender = $_POST['gender'];
    $languages = $_POST['languages'] ?? [];
    $biography = trim($_POST['biography']);
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;

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
        $pdo->exec("DELETE FROM application_languages WHERE application_id = $applicationId");

        $stmt = $pdo->prepare("
            INSERT INTO application_languages (application_id, language_id)
            VALUES (?, ?)
        ");
        foreach ($languages as $lang) {
            $stmt->execute([$applicationId, $lang]);
        }

        $pdo->commit();
        header("Location: admin.php");
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Ошибка: " . $e->getMessage());
    }
}

// Получение списка языков
$languagesList = $pdo->query("SELECT * FROM programming_languages")->fetchAll();

// Заполнение значений для формы
$values = [
    'full_name' => $app['full_name'],
    'phone' => $app['phone'],
    'email' => $app['email'],
    'birth_date' => $app['birth_date'],
    'gender' => $app['gender'],
    'languages' => explode(',', $app['languages']),
    'biography' => $app['biography'],
    'contract_accepted' => $app['contract_accepted']
];
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

        .success {
            color: green;
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
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

    <form action="edit_application.php?id=<?= $applicationId ?>" method="POST">
        <div class="form-group">
            <label for="full_name">ФИО:</label>
            <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($values['full_name']) ?>" required>
        </div>

        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($values['phone']) ?>" required>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($values['email']) ?>" required>
        </div>

        <div class="form-group">
            <label for="birth_date">Дата рождения:</label>
            <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($values['birth_date']) ?>" required>
        </div>

        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= $values['gender'] === 'male' ? 'checked' : '' ?> required> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $values['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
        </div>

        <div class="form-group">
            <label for="languages">Любимый язык программирования:</label>
            <select id="languages" name="languages[]" multiple required>
                <?php foreach ($languagesList as $lang): ?>
                    <option value="<?= $lang['id'] ?>"
                        <?= in_array($lang['id'], $values['languages']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($lang['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="biography">Биография:</label>
            <textarea id="biography" name="biography" required><?= htmlspecialchars($values['biography']) ?></textarea>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="contract_accepted" <?= $values['contract_accepted'] ? 'checked' : '' ?> required>
                    С контрактом ознакомлен(а)
                </label>
            </div>
        </div>

        <button type="submit">Сохранить</button>
        <a href="admin.php" class="btn btn-secondary">Отмена</a>
    </form>
</body>
</html>