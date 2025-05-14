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

// Время жизни куки для успешных данных – 1 год
$one_year = time() + (365 * 24 * 60 * 60);

// CSRF-функции
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Функция для генерации случайного логина
function generateLogin($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $emailParts = explode('@', $email);
    return $emailParts[0] . rand(100, 999);
}

// Функция для генерации случайного пароля
function generatePassword($length = 12) {
    return bin2hex(random_bytes($length / 2));
}

// Проверка авторизации
$isAuthorized = isset($_SESSION['user_id']);
$application_id = $isAuthorized ? filter_var($_SESSION['application_id'], FILTER_VALIDATE_INT) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('Недействительный CSRF-токен');
    }

    $errors = [];
    $values = [];

    $full_name = trim(filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_STRING));
    $values['full_name'] = $full_name;
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $full_name) || iconv_strlen($full_name, 'UTF-8') > 150) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы, не более 150 символов.';
    }

    $phone = trim(filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING));
    $values['phone'] = $phone;
    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        $errors['phone'] = 'Телефон должен содержать от 10 до 15 цифр, возможно с префиксом "+".';
    }

    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
    $values['email'] = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат e-mail.';
    }

    $birth_date = filter_input(INPUT_POST, 'birth_date', FILTER_SANITIZE_STRING);
    $values['birth_date'] = $birth_date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors['birth_date'] = 'Дата рождения должна быть в формате ГГГГ-ММ-ДД.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$date || $date->format('Y-m-d') !== $birth_date || $date > new DateTime()) {
            $errors['birth_date'] = 'Некорректная дата рождения или дата в будущем.';
        }
    }

    $gender = filter_input(INPUT_POST, 'gender', FILTER_SANITIZE_STRING);
    $values['gender'] = $gender;
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Некорректное значение пола.';
    }

    $languages = filter_input(INPUT_POST, 'languages', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
    $values['languages'] = $languages;
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        $valid_languages = range(1, 12);
        foreach ($languages as $lang) {
            $lang = filter_var($lang, FILTER_VALIDATE_INT);
            if ($lang === false || !in_array($lang, $valid_languages)) {
                $errors['languages'] = 'Некорректный выбор языка программирования.';
                break;
            }
        }
    }

    $biography = trim(filter_input(INPUT_POST, 'biography', FILTER_SANITIZE_STRING));
    $values['biography'] = $biography;
    if (empty($biography)) {
        $errors['biography'] = 'Поле биографии не может быть пустым.';
    }

    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    $values['contract_accepted'] = $contract_accepted;
    if (!$contract_accepted) {
        $errors['contract_accepted'] = 'Необходимо согласиться с контрактом.';
    }

    if (!empty($errors)) {
        foreach ($errors as $field => $error_message) {
            setcookie($field . '_error', $error_message, 0, "/", "", true, true);
        }
        foreach ($values as $field => $value) {
            $cookieValue = is_array($value) ? implode(',', $value) : $value;
            setcookie($field . '_value', $cookieValue, 0, "/", "", true, true);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        if ($isAuthorized && $application_id) {
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = ?, phone = ?, email = ?, birth_date = ?, 
                    gender = ?, biography = ?, contract_accepted = ?
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, 
                           $biography, $contract_accepted, $application_id]);

            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$application_id]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$full_name, $phone, $email, $birth_date, $gender, 
                           $biography, $contract_accepted]);

            $application_id = $pdo->lastInsertId();

            $login = generateLogin($email);
            $plain_password = generatePassword();
            $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users (application_id, login, password_hash)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$application_id, $login, $password_hash]);

            $_SESSION['new_login'] = $login;
            $_SESSION['new_password'] = $plain_password;
        }

        $stmt = $pdo->prepare("
            INSERT INTO application_languages (application_id, language_id)
            VALUES (?, ?)
        ");
        foreach ($languages as $lang) {
            $stmt->execute([$application_id, (int)$lang]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(500);
        die('Ошибка сервера');
    }

    foreach ($values as $field => $value) {
        $cookieValue = is_array($value) ? implode(',', $value) : $value;
        setcookie($field . '_value', $cookieValue, $one_year, "/", "", true, true);
    }

    foreach ($errors as $field => $_) {
        setcookie($field . '_error', '', time() - 3600, "/", "", true, true);
    }
    setcookie('save', '1', $one_year, "/", "", true, true);

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

if ($isAuthorized && $application_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT full_name, phone, email, birth_date, gender, biography, contract_accepted
            FROM applications
            WHERE id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();

        if ($application) {
            $values = $application;
            $values['contract_accepted'] = (int)$values['contract_accepted'];

            $stmt = $pdo->prepare("
                SELECT language_id
                FROM application_languages
                WHERE application_id = ?
            ");
            $stmt->execute([$application_id]);
            $values['languages'] = array_column($stmt->fetchAll(), 'language_id');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        die('Ошибка сервера');
    }
}

// Загружаем форму
include __DIR__ . '/form.php';