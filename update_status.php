<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    exit();
}

include "db.php";

$action = $_POST["action"];
$ticket = $_POST["ticket"];

if ($action == 'confirm') {
    $stmt = $conn->prepare("UPDATE queue SET is_confirmed = 1 WHERE ticket_number = ?");
    $stmt->bind_param("s", $ticket);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["success" => true]);
} elseif ($action == 'complete') {
    $stmt = $conn->prepare("UPDATE queue SET status = 'completed' WHERE ticket_number = ?");
    $stmt->bind_param("s", $ticket);
    $stmt->execute();
    $stmt->close();
    unset($_SESSION["current_ticket"]); // Очищаємо поточний талон
    echo json_encode(["success" => true]);
} elseif ($action == 'cancel') {
    $stmt = $conn->prepare("UPDATE queue SET status = 'cancelled', is_called = 0, is_confirmed = 0 WHERE ticket_number = ?");
    $stmt->bind_param("s", $ticket);
    $stmt->execute();
    $stmt->close();
    echo json_encode(["success" => true]); // Не видаляємо сесію працівника
} else {
    echo json_encode(["success" => false]);
}
?>