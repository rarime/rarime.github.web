<?php
// form.php

$values = isset($values) && is_array($values) ? $values : [];
$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'biography', 'contract_accepted'];
foreach ($fields as $field) {
    $cookieValue = filter_input(INPUT_COOKIE, $field . '_value', FILTER_SANITIZE_STRING);
    $values[$field] = $cookieValue ?? ($values[$field] ?? '');
}

if (!empty($values['languages']) && is_string($values['languages'])) {
    $values['languages'] = explode(',', filter_var($values['languages'], FILTER_SANITIZE_STRING));
} elseif (!is_array($values['languages'])) {
    $values['languages'] = [];
}

$errors = [];
foreach ($fields as $field) {
    if (isset($_COOKIE[$field . '_error'])) {
        $errors[$field] = filter_var($_COOKIE[$field . '_error'], FILTER_SANITIZE_STRING);
        setcookie($field . '_error', '', time() - 3600, "/", "", true, true);
    }
}

$success_message = '';
if (isset($_COOKIE['save'])) {
    $success_message = 'Данные успешно сохранены!';
    setcookie('save', '', time() - 3600, "/", "", true, true);
}

$new_login = isset($_SESSION['new_login']) ? $_SESSION['new_login'] : '';
$new_password = isset($_SESSION['new_password']) ? $_SESSION['new_password'] : '';
if ($new_login && $new_password) {
    $success_message .= "<br>Ваш логин: " . htmlspecialchars($new_login, ENT_QUOTES, 'UTF-8') . 
                       "<br>Ваш пароль: " . htmlspecialchars($new_password, ENT_QUOTES, 'UTF-8') . 
                       "<br>Сохраните эти данные, они больше не будут отображаться!";
    unset($_SESSION['new_login']);
    unset($_SESSION['new_password']);
}

$csrf_token = generateCsrfToken();
$isAuthorized = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма заявки</title>
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
    <h1>Форма заявки</h1>

    <?php if (!empty($success_message)): ?>
        <p class="success"><?= htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form action="index.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="full_name">ФИО:</label>
            <input type="text" id="full_name" name="full_name" 
                   value="<?= htmlspecialchars($values['full_name'], ENT_QUOTES, 'UTF-8') ?>" required>
            <?php if (isset($errors['full_name'])): ?>
                <p class="error"><?= htmlspecialchars($errors['full_name'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" 
                   value="<?= htmlspecialchars($values['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
            <?php if (isset($errors['phone'])): ?>
                <p class="error"><?= htmlspecialchars($errors['phone'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" 
                   value="<?= htmlspecialchars($values['email'], ENT_QUOTES, 'UTF-8') ?>" required>
            <?php if (isset($errors['email'])): ?>
                <p class="error"><?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="birth_date">Дата рождения:</label>
            <input type="date" id="birth_date" name="birth_date" 
                   value="<?= htmlspecialchars($values['birth_date'], ENT_QUOTES, 'UTF-8') ?>" required>
            <?php if (isset($errors['birth_date'])): ?>
                <p class="error"><?= htmlspecialchars($errors['birth_date'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" 
                       <?= $values['gender'] === 'male' ? 'checked' : '' ?> required> Мужской</label>
                <label><input type="radio" name="gender" value="female" 
                       <?= $values['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <p class="error"><?= htmlspecialchars($errors['gender'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="languages">Любимый язык программирования:</label>
            <select id="languages" name="languages[]" multiple required>
                <?php
                $programming_languages = [
                    1 => 'Pascal', 2 => 'C', 3 => 'C++', 4 => 'JavaScript', 5 => 'PHP', 6 => 'Python',
                    7 => 'Java', 8 => 'Haskell', 9 => 'Clojure', 10 => 'Prolog', 11 => 'Scala', 12 => 'Go'
                ];
                foreach ($programming_languages as $id => $lang) {
                    $selected = in_array((string)$id, $values['languages']) ? 'selected' : '';
                    echo "<option value=\"" . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . "\" $selected>" . 
                         htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . "</option>";
                }
                ?>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <p class="error"><?= htmlspecialchars($errors['languages'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="biography">Биография:</label>
            <textarea id="biography" name="biography" required><?= htmlspecialchars($values['biography'], ENT_QUOTES, 'UTF-8') ?></textarea>
            <?php if (isset($errors['biography'])): ?>
                <p class="error"><?= htmlspecialchars($errors['biography'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="contract_accepted" 
                           <?= $values['contract_accepted'] == 1 ? 'checked' : '' ?> required>
                    С контрактом ознакомлен(а)
                </label>
            </div>
            <?php if (isset($errors['contract_accepted'])): ?>
                <p class="error"><?= htmlspecialchars($errors['contract_accepted'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
        </div>

        <button type="submit"><?= $isAuthorized ? 'Сохранить изменения' : 'Сохранить' ?></button>
    </form>

    <?php if (!$isAuthorized): ?>
        <div class="auth-link">
            <a href="login.php">Войти для редактирования данных</a>
        </div>
    <?php else: ?>
        <div class="auth-link">
            <a href="logout.php">Выйти</a>
        </div>
    <?php endif; ?>
</body>
</html>