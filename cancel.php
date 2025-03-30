<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "db.php";

$ticket_number = $_GET["ticket_number"];

// Перевірка статусу талону
$stmt = $conn->prepare("SELECT status FROM queue WHERE ticket_number = ? AND user_id = ?");
$stmt->bind_param("si", $ticket_number, $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $status = $row["status"];
    
    if ($status == 'pending') {
        // Скасування активного талону
        $stmt = $conn->prepare("UPDATE queue SET status = 'cancelled', is_called = 0, is_confirmed = 0 WHERE ticket_number = ? AND user_id = ?");
        $stmt->bind_param("si", $ticket_number, $_SESSION["user_id"]);
        if ($stmt->execute()) {
            $_SESSION["success"] = "Запис успішно скасовано!";
        } else {
            $_SESSION["error"] = "Помилка скасування запису!";
        }
    } elseif ($status == 'completed' || $status == 'cancelled') {
        // Видалення завершеного або скасованого талону
        $stmt = $conn->prepare("DELETE FROM queue WHERE ticket_number = ? AND user_id = ?");
        $stmt->bind_param("si", $ticket_number, $_SESSION["user_id"]);
        if ($stmt->execute()) {
            $_SESSION["success"] = "Талон успішно видалено!";
        } else {
            $_SESSION["error"] = "Помилка видалення талону!";
        }
    } else {
        $_SESSION["error"] = "Дію не можна виконати для цього талону!";
    }
} else {
    $_SESSION["error"] = "Талон не знайдено або не належить вам!";
}

$stmt->close();
$conn->close();

header("Location: index.php");
exit();
?>