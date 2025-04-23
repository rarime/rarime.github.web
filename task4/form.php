<?php
// Начало PHP-кода для обработки ошибок и значений
$errors = json_decode($_COOKIE['errors'] ?? '[]', true);
$values = json_decode($_COOKIE['values'] ?? '[]', true);

function errorLabel($key, $errors) {
    if (!isset($errors[$key])) {
        return;
    }
    echo '<small class="error">' . htmlspecialchars($errors[$key]) . '</small>';
}

function getValue($key, $values) {
    echo isset($values[$key]) ? htmlspecialchars($values[$key]) : '';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма анкеты</title>
    <style>
        body {
            background-color: #fffafa;
            padding: 20px;
        }
        .form-container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        select,
        textarea {
            background-color: #ccccff !important;
            border-color: #9999cc !important;
        }
        .error {
            color: #ff3333 !important;
            display: block;
            margin-top: 0.25rem;
        }
        .error-summary {
            background: #ffeeee;
            border-left: 4px solid #ff3333;
            padding: 1rem;
            margin-bottom: 1rem;
            max-width: 400px;
            margin: 0 auto 1rem;
            color: #ff3333;
        }
        form {
            margin: auto;
            max-width: 400px;
        }
        .clear {
            float: right;
            color: #6666cc;
        }
        .is-invalid {
            border-color: #ff3333 !important;
        }
        button[type="submit"] {
            background-color: #6666cc !important;
            color: white !important;
        }
        button[type="submit"]:hover {
            background-color: #5555bb !important;
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
    <div class="form-container">
        <?php if (!empty($errors)): ?>
            <div class="error-summary">
                <strong>Обнаружены ошибки:</strong>
                <ul>
                    <?php foreach ($errors as $field => $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="nice-form-group">
                <!-- Поле имени -->
                <label>Ваше имя</label>
                <input type="text"
                       placeholder="Фамилия Имя"
                       name="name"
                       value="<?php getValue('name', $values); ?>"
                       class="<?= isset($errors['name']) ? 'is-invalid' : '' ?>">
                <?php errorLabel('name', $errors); ?>

                <!-- Поле телефона -->
                <label>Телефон</label>
                <input name="phone"
                       value="<?php getValue('phone', $values); ?>"
                       type="tel"
                       placeholder="+7(XXX)XXX-XX-XX"
                       class="icon-left <?= isset($errors['phone']) ? 'is-invalid' : '' ?>">
                <?php errorLabel('phone', $errors); ?>

                <!-- Поле email -->
                <label>Email</label>
                <input name="email"
                       type="email"
                       placeholder="Введите email"
                       value="<?php getValue('email', $values); ?>"
                       class="icon-left <?= isset($errors['email']) ? 'is-invalid' : '' ?>">
                <?php errorLabel('email', $errors); ?>

                <!-- Поле даты рождения -->
                <label>Дата рождения</label>
                <input type="date" 
                       name="birth_date" 
                       placeholder="Дата рождения"
                       value="<?php getValue('birth_date', $values); ?>"
                       class="<?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>">
                <?php errorLabel('birth_date', $errors); ?>

                <!-- Поле навыков -->
                <label>Ваши навыки</label>
                <select name="languages[]" 
                        multiple
                        class="<?= isset($errors['languages']) ? 'is-invalid' : '' ?>">
                    <?php
                    $languages = $db->query('SELECT * FROM languages');
                    foreach ($languages as $lang) {
                        $selected = in_array($lang['id'], $values['languages'] ?? []) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($lang['id']) . '" ' . $selected . '>' 
                             . htmlspecialchars($lang['name']) . '</option>';
                    }
                    ?>
                </select>
                <?php errorLabel('languages', $errors); ?>

                <!-- Поле "О себе" -->
                <label>Расскажите о себе</label>
                <textarea name="bio"
                          placeholder="О себе"
                          aria-label="Professional short bio"
                          class="<?= isset($errors['bio']) ? 'is-invalid' : '' ?>"><?php getValue('bio', $values); ?></textarea>
                <?php errorLabel('bio', $errors); ?>

                <!-- Поле пола -->
                <fieldset>
                    <legend>Ваш пол</legend>
                    <?php $sex = $values['sex'] ?? null; ?>
                    <label>
                        <input type="radio" 
                               name="sex" 
                               value="1" 
                               <?= ($sex == 1) ? 'checked' : '' ?> />
                        Мужской
                    </label>
                    <label>
                        <input type="radio" 
                               name="sex" 
                               value="0" 
                               <?= ($sex == 0 || $sex === null) ? 'checked' : '' ?> />
                        Женский
                    </label>
                </fieldset>
                <?php errorLabel('sex', $errors); ?>

                <!-- Кнопки отправки и очистки -->
                <div style="margin-top: 1rem;">
                    <button type="submit">Отправить</button>
                    <a class="clear" href="?clear=1">Очистить форму</a>
                </div>
            </div>
        </form>
    </div>
</body>
</html>
