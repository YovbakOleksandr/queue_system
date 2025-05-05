<?php
/* Перевірка сесії та прав доступу */
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    exit();
}

include "db.php";

/* Обробка статусів черги */
$action = $_POST["action"];
$ticket = $_POST["ticket"];
$success = false;

/* Оновлення статусу талону */
if ($action == 'confirm') {
    $stmt = $conn->prepare("UPDATE queue SET is_confirmed = 1, called_at = NOW() WHERE ticket_number = ?");
    $stmt->bind_param("s", $ticket);
    $success = $stmt->execute();
    $stmt->close();
} elseif ($action == 'complete') {
    $stmt = $conn->prepare("UPDATE queue SET status = 'completed', called_at = NOW() WHERE ticket_number = ?");
    $stmt->bind_param("s", $ticket);
    $success = $stmt->execute();
    $stmt->close();
    unset($_SESSION["current_ticket"]);
} elseif ($action == 'cancel') {
    $stmt = $conn->prepare("UPDATE queue SET status = 'cancelled', is_called = 0, is_confirmed = 0, called_at = NOW() WHERE ticket_number = ?");
    $stmt->bind_param("s", $ticket);
    $success = $stmt->execute();
    $stmt->close();
}

/* Повернення результату */
echo json_encode([
    "success" => $success,
    "timestamp" => time()
]);
?>