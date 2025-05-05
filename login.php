<?php
session_start();
include "db.php";

/* Перевірка авторизації */
if (isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit();
}

/* Обробка форми входу */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("SELECT id, full_name, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $full_name, $hashed_password, $role);
        $stmt->fetch();
        if (password_verify($password, $hashed_password)) {
            $_SESSION["user_id"] = $id;
            $_SESSION["full_name"] = $full_name;
            $_SESSION["role"] = $role;
            header("Location: index.php");
            exit();
        } else {
            $_SESSION["error"] = "Невірний пароль!";
        }
    } else {
        $_SESSION["error"] = "Користувача не знайдено!";
    }
    $stmt->close();
    $conn->close();
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Вхід</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.25rem;
        }
        .input-group-text {
             width: 40px;
             justify-content: center;
        }
        .forgot-password {
            text-align: right;
            font-size: 0.9em;
            margin-bottom: 1.25rem;
        }
        .btn-block {
            padding: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4">Вхід до системи</h2>
        <?php if (isset($_SESSION["error"])) { echo "<div class='alert alert-danger'>" . htmlspecialchars($_SESSION["error"]) . "</div>"; unset($_SESSION["error"]); } ?>
        <?php if (isset($_SESSION["success"])) { echo "<div class='alert alert-success'>" . htmlspecialchars($_SESSION["success"]) . "</div>"; unset($_SESSION["success"]); } ?>
        <form action="login.php" method="post">
            <div class="form-group">
                <label for="email">Email:</label>
                <div class="input-group">
                     <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                    </div>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Введіть ваш email" required autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                 <div class="input-group">
                     <div class="input-group-prepend">
                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    </div>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Введіть ваш пароль" required autocomplete="current-password">
                 </div>
            </div>
            <div class="forgot-password">
                <a href="forgot_password.php">Забули пароль?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt mr-2"></i>Увійти</button>
        </form>
        <p class="text-center mt-3">Немає акаунту? <a href="register.php">Зареєструватися</a></p>
    </div>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>