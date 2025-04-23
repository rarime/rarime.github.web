<?php
session_start();

// Подключение к базе данных
$user = 'u68754';
$pass = '5610469';

try {
    $db = new PDO('mysql:host=localhost;dbname=u68754', $user, $pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Очистка Cookies
function clearCookie($name) {
    setcookie($name, '', time() - 3600, '/');
    unset($_COOKIE[$name]);
}

// Обработка очистки формы
if ($_SERVER['REQUEST_METHOD'] == 'GET' && !empty($_GET['clear'])) {
    clearCookie('errors');
    clearCookie('values');
    clearCookie('submission_id');
    header("Location: " . strtok($_SERVER['REQUEST_URI'], '?'));
    exit();
}

// Загрузка предыдущих данных
function loadSubmission($db, $id) {
    $stmt = $db->prepare("SELECT * FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT language_id FROM submission_languages WHERE submission_id = ?");
    $stmt->execute([$id]);
    $data['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return $data;
}

// Валидация данных
function validateData($data, $db) {
    $errors = [];
    $rules = [
        'name' => [
            'required' => 'Имя обязательно',
            'regex' => ['/^[А-Яа-яЁё\s-]+$/u', 'Только русские буквы, пробелы и дефисы'],
            'max_length' => [50, 'Не более 50 символов']
        ],
        'phone' => [
            'required' => 'Телефон обязателен',
            'regex' => ['/^\+7\(\d{3}\)\d{3}-\d{2}-\d{2}$/', 'Формат: +7(XXX)XXX-XX-XX']
        ],
        'email' => [
            'required' => 'Email обязателен',
            'filter' => [FILTER_VALIDATE_EMAIL, 'Некорректный email']
        ],
        'birth_date' => [
            'required' => 'Дата рождения обязательна',
            'date_not_future' => 'Дата не может быть в будущем'
        ],
        'bio' => [
            'required' => 'Расскажите о себе',
            'max_length' => [200, 'Не более 200 символов']
        ],
        'sex' => [
            'required' => 'Укажите пол',
            'in' => [[0, 1], 'Некорректное значение']
        ],
        'languages' => [
            'required' => 'Выберите хотя бы один язык',
            'array_min' => [1, 'Выберите хотя бы один язык'],
            'exists' => ['languages', 'id', 'Выбран несуществующий язык']
        ]
    ];

    foreach ($rules as $field => $validators) {
        $value = $data[$field] ?? null;
        
        foreach ($validators as $type => $params) {
            switch ($type) {
                case 'required':
                    if (empty($value)) {
                        $errors[$field] = $params;
                        break 2;
                    }
                    break;
                    
                case 'regex':
                    if (!preg_match($params[0], $value)) {
                        $errors[$field] = $params[1];
                        break 2;
                    }
                    break;
                    
                case 'max_length':
                    if (strlen($value) > $params[0]) {
                        $errors[$field] = $params[1];
                        break 2;
                    }
                    break;
                    
                case 'filter':
                    if (!filter_var($value, $params[0])) {
                        $errors[$field] = $params[1];
                        break 2;
                    }
                    break;
                    
                case 'date_not_future':
                    if (strtotime($value) > time()) {
                        $errors[$field] = $params;
                        break 2;
                    }
                    break;
                    
                case 'in':
                    if (!in_array($value, $params[0])) {
                        $errors[$field] = $params[1];
                        break 2;
                    }
                    break;
                    
                case 'array_min':
                    if (count($value) < $params[0]) {
                        $errors[$field] = $params[1];
                        break 2;
                    }
                    break;
                    
                case 'exists':
                    $stmt = $db->prepare("SELECT COUNT(*) FROM {$params[0]} WHERE {$params[1]} = ?");
                    foreach ($value as $item) {
                        $stmt->execute([$item]);
                        if ($stmt->fetchColumn() == 0) {
                            $errors[$field] = $params[2];
                            break 3;
                        }
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

// Обработка POST-запроса
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'name' => $_POST['name'] ?? null,
        'phone' => $_POST['phone'] ?? null,
        'email' => $_POST['email'] ?? null,
        'birth_date' => $_POST['birth_date'] ?? null,
        'bio' => $_POST['bio'] ?? null,
        'sex' => isset($_POST['sex']) ? (int)$_POST['sex'] : null,
        'languages' => $_POST['languages'] ?? []
    ];
    
    $errors = validateData($data, $db);
    
    if (!empty($errors)) {
        // Сохраняем ошибки и данные в Cookies
        setcookie('errors', json_encode($errors), 0, '/');
        setcookie('values', json_encode($data), 0, '/');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
    
    // Сохранение в БД
    try {
        $db->beginTransaction();
        
        // Сохраняем основную информацию
        $stmt = $db->prepare("INSERT INTO submissions (name, phone, email, birth_date, bio, sex) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['name'],
            $data['phone'],
            $data['email'],
            $data['birth_date'],
            $data['bio'],
            $data['sex']
        ]);
        
        $submissionId = $db->lastInsertId();
        
        // Сохраняем языки
        $stmt = $db->prepare("INSERT INTO submission_languages (submission_id, language_id) VALUES (?, ?)");
        foreach ($data['languages'] as $langId) {
            $stmt->execute([$submissionId, $langId]);
        }
        
        $db->commit();
        
        // Сохраняем ID отправки на 1 год
        setcookie('submission_id', $submissionId, time() + 60*60*24*365, '/');
        clearCookie('errors');
        clearCookie('values');
        
        header("Location: ?save=1");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $errors['db'] = "Ошибка сохранения: " . $e->getMessage();
        setcookie('errors', json_encode($errors), 0, '/');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Загрузка данных для формы
$values = [];
if (!empty($_COOKIE['values'])) {
    $values = json_decode($_COOKIE['values'], true);
} elseif (!empty($_COOKIE['submission_id'])) {
    $values = loadSubmission($db, $_COOKIE['submission_id']);
}

// Отображение формы
if (!empty($_GET['save'])) {
    echo 'Спасибо, результаты сохранены.<br/><a href="' . strtok($_SERVER['REQUEST_URI'], '?') . '">Назад к форме</a>';
    exit();
}

// Удаляем ошибки после их отображения
$errors = [];
if (!empty($_COOKIE['errors'])) {
    $errors = json_decode($_COOKIE['errors'], true);
    clearCookie('errors');
}

include('form.php');
