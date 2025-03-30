<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    header("Location: login.php");
    exit();
}

include "db.php";

// Отримання списку послуг, які працівник може обробляти
$employee_id = $_SESSION["user_id"];
$services = [];
$sql = "SELECT es.id, s.name, es.is_active 
        FROM employee_services es 
        JOIN services s ON es.service_id = s.id 
        WHERE es.employee_id = $employee_id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Оновлення активних послуг
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_services"])) {
    foreach ($services as $service) {
        $is_active = isset($_POST["service_" . $service['id']]) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE employee_services SET is_active = ? WHERE id = ?");
        $stmt->bind_param("ii", $is_active, $service['id']);
        $stmt->execute();
        $stmt->close();
    }
    $_SESSION["success"] = "Послуги успішно оновлено!";
    header("Location: employee_services.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Мої послуги</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-check {
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Мої послуги</h2>
        <?php if (isset($_SESSION["success"])) { echo "<div class='alert alert-success'>" . $_SESSION["success"] . "</div>"; unset($_SESSION["success"]); } ?>
        <form action="" method="post">
            <?php foreach ($services as $service): ?>
                <div class="form-check">
                    <input type="checkbox" name="service_<?php echo $service['id']; ?>" class="form-check-input" <?php echo $service['is_active'] ? 'checked' : ''; ?>>
                    <label class="form-check-label"><?php echo $service['name']; ?></label>
                </div>
            <?php endforeach; ?>
            <button type="submit" name="update_services" class="btn btn-primary mt-3">Оновити послуги</button>
        </form>
    </div>
</body>
</html>
