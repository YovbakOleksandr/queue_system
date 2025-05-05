<?php
session_start();
include "db.php";

/* Перевірка авторизації */
if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

/* Список секретних запитань */
$security_questions = [
    "Ваша перша домашня тварина?",
    "Дівоче прізвище матері?",
    "Назва міста, де ви народилися?",
    "Ім'я вашого найкращого друга дитинства?",
    "Назва вашої першої школи?",
    "Ваш улюблений фільм?",
    "Марка вашого першого автомобіля?"
];

/* Обробка форми реєстрації */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last_name = trim($_POST["last_name"]);
    $first_name = trim($_POST["first_name"]);
    $patronymic = trim($_POST["patronymic"]);
    $full_name = $last_name . " " . $first_name . " " . $patronymic;
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $security_question = trim($_POST["security_question"]);
    $security_answer = trim($_POST["security_answer"]);

    /* Валідація форми */
    $errors = [];

    /* Перевірка ПІБ */
    if (empty($last_name) || empty($first_name) || empty($patronymic)) {
        $errors[] = "Усі поля ПІБ мають бути заповнені";
    }

    /* Перевірка email */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Некоректний формат email";
    }

    /* Перевірка існуючого email */
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    if ($check_stmt->num_rows > 0) {
        $errors[] = "Користувач з таким email вже існує";
    }
    $check_stmt->close();

    /* Перевірка пароля */
    if (strlen($password) < 8) {
        $errors[] = "Пароль має бути не менше 8 символів";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Пароль має містити хоча б одну велику літеру";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Пароль має містити хоча б одну малу літеру";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Пароль має містити хоча б одну цифру";
    }

    /* Перевірка підтвердження пароля */
    if ($password !== $confirm_password) {
        $errors[] = "Паролі не співпадають";
    }

    /* Перевірка секретного запитання */
    if (empty($security_question) || !in_array($security_question, $security_questions)) {
        $errors[] = "Виберіть коректне секретне запитання";
    }

    if (strlen($security_answer) < 2) {
        $errors[] = "Відповідь на секретне запитання занадто коротка";
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_answer = password_hash(strtolower($security_answer), PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, security_question, security_answer) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $security_question, $hashed_answer);

        if ($stmt->execute()) {
            $_SESSION["success"] = "Реєстрація успішна! Тепер увійдіть.";
            header("Location: login.php");
            exit();
        } else {
            $_SESSION["error"] = "Помилка реєстрації: (" . $stmt->errno . ") " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION["error"] = implode("<br>", $errors);
        header("Location: register.php");
        exit();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Реєстрація</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 0;
        }
        .register-container {
            max-width: 600px;
            width: 100%;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
            padding-left: 15px;
        }
        .password-requirements ul {
            margin-bottom: 0;
            padding-left: 1.2em;
        }
        .input-group-text {
            width: 40px;
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <h2 class="text-center mb-4">Створення облікового запису</h2>
        <?php if (isset($_SESSION["error"])) { echo "<div class='alert alert-danger'>" . $_SESSION["error"] . "</div>"; unset($_SESSION["error"]); } ?>
        <?php if (isset($_SESSION["success"])) { echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION["success"]) . "</div>"; unset($_SESSION["success"]); } ?>

        <form action="register.php" method="post">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="last_name">Прізвище:</label>
                    <input type="text" name="last_name" id="last_name" class="form-control" placeholder="Прізвище" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="first_name">Ім'я:</label>
                    <input type="text" name="first_name" id="first_name" class="form-control" placeholder="Ім'я" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="patronymic">По батькові:</label>
                    <input type="text" name="patronymic" id="patronymic" class="form-control" placeholder="По батькові" required value="<?php echo isset($_POST['patronymic']) ? htmlspecialchars($_POST['patronymic']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    </div>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Введіть ваш email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" autocomplete="username">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Пароль:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Введіть пароль" required autocomplete="new-password">
                </div>
                <div class="password-requirements">
                    Пароль повинен містити:
                    <ul>
                        <li>Мінімум 8 символів</li>
                        <li>Хоча б одну велику літеру</li>
                        <li>Хоча б одну малу літеру</li>
                        <li>Хоча б одну цифру</li>
                    </ul>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Підтвердження пароля:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-check-circle"></i></span>
                    </div>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Підтвердіть пароль" required autocomplete="new-password">
                </div>
            </div>

            <div class="form-group">
                <label for="security_question">Секретне запитання:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-question-circle"></i></span>
                    </div>
                    <select name="security_question" id="security_question" class="form-control" required>
                        <option value="">-- Оберіть запитання --</option>
                        <?php foreach ($security_questions as $question): ?>
                            <option value="<?php echo htmlspecialchars($question); ?>" <?php echo (isset($_POST['security_question']) && $_POST['security_question'] == $question) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($question); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <small class="form-text text-muted">Це запитання буде використано для відновлення пароля.</small>
            </div>

            <div class="form-group">
                <label for="security_answer">Відповідь на секретне запитання:</label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-user-secret"></i></span>
                    </div>
                    <input type="text" name="security_answer" id="security_answer" class="form-control" placeholder="Введіть відповідь" required value="<?php echo isset($_POST['security_answer']) ? htmlspecialchars($_POST['security_answer']) : ''; ?>">
                </div>
                <small class="form-text text-muted">Запам'ятайте цю відповідь, регістр не важливий.</small>
            </div>

            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-user-plus mr-2"></i>Зареєструватися</button>
        </form>

        <p class="text-center mt-3">Вже маєте акаунт? <a href="login.php">Увійти</a></p>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>