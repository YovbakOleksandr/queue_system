<?php
// --- Ініціалізація сесії та підключення до БД ---
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
include "db.php";
$role = $_SESSION["role"];
date_default_timezone_set('Europe/Kyiv');
$current_date = date("d.m.Y");
$current_time = date("H:i:s");
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'services';

// --- Обробка POST-запитів для працівника ---
if ($role === 'employee' && $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["update_workstation"])) {
        $workstation = trim($_POST["workstation"]);
        $stmt = $conn->prepare("UPDATE users SET workstation = ? WHERE id = ?");
        $stmt->bind_param("si", $workstation, $_SESSION["user_id"]);
        $stmt->execute();
        $stmt->close();
        $_SESSION["workstation"] = $workstation;
        $_SESSION["success"] = "Робочу станцію оновлено!";
        header("Location: index.php?tab=settings");
        exit();
    }
    if (isset($_POST["update_filter"])) {
        $selected_services = $_POST["services"] ?? [];
        $_SESSION["selected_services"] = $selected_services;
        $_SESSION["success"] = "Фільтр послуг оновлено!";
        header("Location: index.php?tab=settings");
        exit();
    }
    if (isset($_POST["update_auto_call"])) {
        $_SESSION["auto_call_next"] = isset($_POST["auto_call_next"]) ? true : false;
        $_SESSION["success"] = "Налаштування оновлено!";
        header("Location: index.php?tab=settings");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Головна</title>
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
        .header-actions {
            display: flex;
            align-items: center;
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
</head>
<body>
    <div class="container mt-4">
        <!-- Шапка -->
        <div class="header">
            <div>
                <h2>Ласкаво просимо, <?= htmlspecialchars($_SESSION["full_name"]) ?>!</h2>
                <p>Ви увійшли як <strong><?= htmlspecialchars($_SESSION["role"]) ?></strong></p>
                <p><strong>Дата:</strong> <?= $current_date ?> | <strong>Час:</strong> <span id="current-time"><?= $current_time ?></span></p>
            </div>
            <div class="header-actions">
                <a href="public_queue.php" class="btn btn-info"><i class="fas fa-eye mr-2"></i>Переглянути публічну чергу</a>
                <a href="logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt mr-2"></i>Вийти</a>
            </div>
        </div>
        <!-- Повідомлення -->
        <?php if (isset($_SESSION["success"])) { echo "<p class='success-msg'>" . $_SESSION["success"] . "</p>"; unset($_SESSION["success"]); } ?>
        <?php if (isset($_SESSION["error"])) { echo "<p class='error-msg'>" . $_SESSION["error"] . "</p>"; unset($_SESSION["error"]); } ?>
        <!-- Основний вміст -->
        <?php if ($role === 'admin'): ?>
            <h3>Панель адміністратора</h3>
            <?php include 'admin_panel.php'; ?>
        <?php elseif ($role === 'employee'): ?>
            <h3>Панель працівника</h3>
            <?php include 'employee_panel.php'; ?>
        <?php else: ?>
            <?php include 'user_panel.php'; ?>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Оновлення часу в шапці
        function updateClock() {
            const now = new Date();
            document.getElementById("current-time").textContent = now.toLocaleTimeString();
        }
        setInterval(updateClock, 1000);
        // Активація вкладки
        $(document).ready(function() {
            var activeTab = '<?php echo $active_tab; ?>';
            if ('<?php echo $role; ?>' === 'admin') {
                $('#adminTabs a[href="#' + activeTab + '-tab"]').tab('show');
            } else if ('<?php echo $role; ?>' === 'employee') {
                $('#employeeTabs a[href="#' + activeTab + '-tab"]').tab('show');
            }
        });
    </script>
</body>
</html>