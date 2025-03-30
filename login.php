<?php
session_start();
include "db.php";

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
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Вхід</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            max-width: 400px;
            margin-top: 100px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .forgot-password {
            text-align: right;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="text-center">Вхід</h2>
        <?php if (isset($_SESSION["error"])) { echo "<div class='alert alert-danger'>" . $_SESSION["error"] . "</div>"; unset($_SESSION["error"]); } ?>
        <?php if (isset($_SESSION["success"])) { echo "<div class='alert alert-success'>" . $_SESSION["success"] . "</div>"; unset($_SESSION["success"]); } ?>
        <form action="" method="post">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" name="email" class="form-control" placeholder="Введіть ваш email" required>
            </div>
            <div class="form-group">
                <label for="password">Пароль:</label>
                <input type="password" name="password" class="form-control" placeholder="Введіть ваш пароль" required>
            </div>
            <div class="forgot-password">
                <a href="forgot_password.php">Забули пароль?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Увійти</button>
        </form>
        <p class="text-center mt-3">Немає акаунту? <a href="register.php">Зареєструватися</a></p>
    </div>
</body>
</html>