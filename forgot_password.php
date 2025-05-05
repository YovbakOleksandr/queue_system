<?php
session_start();
include "db.php";

/* Ініціалізація змінних */
$show_question_form = false;
$show_answer_form = false;
$show_password_form = false;
$user_email = "";
$security_question = "";
$user_id = 0;

/* Крок 1: Перевірка email */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["email"]) && !isset($_POST["security_answer"]) && !isset($_POST["new_password"])) {
    $email = trim($_POST["email"]);
    
    $stmt = $conn->prepare("SELECT id, full_name, security_question FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (empty($user["security_question"])) {
            $_SESSION["error"] = "Для цього облікового запису не встановлено секретне запитання. Зверніться до адміністратора.";
        } else {
            $show_question_form = true;
            $user_email = $email;
            $security_question = $user["security_question"];
            $user_id = $user["id"];
            
            $_SESSION["reset_email"] = $email;
            $_SESSION["reset_user_id"] = $user_id;
        }
    } else {
        $_SESSION["error"] = "Користувача з такою електронною поштою не знайдено";
    }
    $stmt->close();
}

/* Крок 2: Перевірка відповіді */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["security_answer"]) && !isset($_POST["new_password"])) {
    $security_answer = strtolower(trim($_POST["security_answer"]));
    $email = $_SESSION["reset_email"];
    $user_id = $_SESSION["reset_user_id"];
    
    $stmt = $conn->prepare("SELECT security_answer, security_question FROM users WHERE id = ? AND email = ?");
    $stmt->bind_param("is", $user_id, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        if (password_verify($security_answer, $user["security_answer"])) {
            $show_password_form = true;
        } else {
            $_SESSION["error"] = "Неправильна відповідь на секретне запитання";
            $show_question_form = true;
            $user_email = $email;
            $security_question = $user["security_question"];
        }
    } else {
        $_SESSION["error"] = "Помилка ідентифікації користувача";
    }
    $stmt->close();
}

/* Крок 3: Зміна пароля */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["new_password"])) {
    $new_password = trim($_POST["new_password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $user_id = $_SESSION["reset_user_id"];
    $email = $_SESSION["reset_email"];
    
    /* Валідація пароля */
    $errors = [];
    
    if (strlen($new_password) < 8) {
        $errors[] = "Пароль має бути не менше 8 символів";
    }
    
    if (!preg_match('/[A-Z]/', $new_password)) {
        $errors[] = "Пароль має містити хоча б одну велику літеру";
    }
    
    if (!preg_match('/[a-z]/', $new_password)) {
        $errors[] = "Пароль має містити хоча б одну малу літеру";
    }
    
    if (!preg_match('/[0-9]/', $new_password)) {
        $errors[] = "Пароль має містити хоча б одну цифру";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "Паролі не співпадають";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ? AND email = ?");
        $stmt->bind_param("sis", $hashed_password, $user_id, $email);
        
        if ($stmt->execute()) {
            $_SESSION["success"] = "Ваш пароль успішно оновлено. Тепер ви можете увійти з новим паролем.";
            
            unset($_SESSION["reset_email"]);
            unset($_SESSION["reset_user_id"]);
            
            header("Location: login.php");
            exit();
        } else {
            $_SESSION["error"] = "Помилка оновлення пароля: " . $conn->error;
            $show_password_form = true;
        }
        $stmt->close();
    } else {
        $_SESSION["error"] = implode("<br>", $errors);
        $show_password_form = true;
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Відновлення пароля</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 500px;
            margin-top: 100px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center mb-4">Відновлення пароля</h2>
        
        <?php if (isset($_SESSION["error"])) { echo "<div class='alert alert-danger'>" . $_SESSION["error"] . "</div>"; unset($_SESSION["error"]); } ?>
        <?php if (isset($_SESSION["success"])) { echo "<div class='alert alert-success'>" . $_SESSION["success"] . "</div>"; unset($_SESSION["success"]); } ?>
        
        <?php if (!$show_question_form && !$show_password_form): ?>
            <div class="card">
                <div class="card-header">
                    Крок 1: Введіть вашу електронну пошту
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" name="email" class="form-control" placeholder="Введіть ваш email" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Продовжити</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($show_question_form && !$show_password_form): ?>
            <div class="card">
                <div class="card-header">
                    Крок 2: Дайте відповідь на секретне запитання
                </div>
                <div class="card-body">
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?></p>
                    <p><strong>Запитання:</strong> <?php echo htmlspecialchars($security_question); ?></p>
                    
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="security_answer">Ваша відповідь:</label>
                            <input type="text" name="security_answer" class="form-control" placeholder="Введіть вашу відповідь" required>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block">Перевірити</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($show_password_form): ?>
            <div class="card">
                <div class="card-header">
                    Крок 3: Створіть новий пароль
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="new_password">Новий пароль:</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Введіть новий пароль" required>
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
                            <input type="password" name="confirm_password" class="form-control" placeholder="Підтвердіть новий пароль" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Змінити пароль</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
        
        <p class="text-center mt-3">
            <a href="login.php">Повернутися до входу</a>
        </p>
    </div>
</body>
</html>
