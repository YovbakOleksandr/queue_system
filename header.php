<?php
// Перевірка, чи вже запущена сесія
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Перевірка, чи підключено файл з базою даних
if (!function_exists('mysqli_init') || !is_file('db.php')) {
    include_once "db.php";
}

// Отримання поточної дати та часу
date_default_timezone_set('Europe/Kyiv');
$current_date = date("d.m.Y");
$current_time = date("H:i:s");

// Визначення поточної сторінки для активації відповідного пункту меню
$current_page = basename($_SERVER['PHP_SELF']);

// Встановлення заголовка сторінки (можна перевизначити перед підключенням header.php)
if (!isset($page_title)) {
    $page_title = "Система управління чергою";
}
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            color: white;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            transition: all 0.3s ease;
        }
        .header:hover {
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }
        .header h2 {
            font-size: 1.8rem;
            margin-bottom: 5px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .header p {
            font-size: 1rem;
            margin: 0;
            opacity: 0.9;
        }
        .header .btn {
            margin-left: 15px;
            padding: 8px 20px;
            font-weight: 500;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        .header .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .header .btn-info:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        .header .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .header .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .header .btn-success {
            background-color: #28a745;
            border-color: #28a745;
        }
        .header .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header-title {
            text-align: left;
        }
        .success-msg {
            background-color: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        .error-msg {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            .header-actions {
                margin-top: 15px;
                width: 100%;
                justify-content: space-between;
            }
            .header .btn {
                margin-left: 0;
                width: 48%;
            }
        }
    </style>
    <!-- Додаткові стилі можна додавати тут -->
    <?php if (isset($additional_styles)) echo $additional_styles; ?>
</head>
<body>
    <div class="container mt-4">
        <!-- Шапка -->
        <div class="header">
            <div class="header-title">
                <?php if (isset($_SESSION["user_id"])): ?>
                    <h2>Ласкаво просимо, <?= htmlspecialchars($_SESSION["full_name"]) ?>!</h2>
                    <p>Ви увійшли як <strong><?= htmlspecialchars($_SESSION["role"]) ?></strong></p>
                <?php else: ?>
                    <h2>Система управління чергою</h2>
                <?php endif; ?>
                <p><strong>Дата:</strong> <?= $current_date ?> | <strong>Час:</strong> <span id="current-time"><?= $current_time ?></span></p>
            </div>
            <div class="header-actions">
                <?php if (isset($_SESSION["user_id"])): ?>
                    <a href="public_queue.php" class="btn btn-success"><i class="fas fa-users mr-2"></i>Публічна черга</a>
                    <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt mr-2"></i>Вийти</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-info"><i class="fas fa-sign-in-alt mr-2"></i>Увійти</a>
                    <a href="register.php" class="btn btn-info"><i class="fas fa-user-plus mr-2"></i>Зареєструватися</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Повідомлення -->
        <?php if (isset($_SESSION["success"])): ?>
            <div class="success-msg"><?= $_SESSION["success"] ?></div>
            <?php unset($_SESSION["success"]); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION["error"])): ?>
            <div class="error-msg"><?= $_SESSION["error"] ?></div>
            <?php unset($_SESSION["error"]); ?>
        <?php endif; ?>

        <!-- Основний вміст сторінки йде після включення header.php -->
