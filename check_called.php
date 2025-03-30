<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    exit();
}

include "db.php";

$user_id = $_SESSION["user_id"];

// Перевірка, чи клієнта викликали (тільки активні, не скасовані та не завершені)
$sql = "SELECT q.ticket_number, q.workstation, q.called_at, s.wait_time 
        FROM queue q 
        JOIN services s ON q.service_id = s.id 
        WHERE q.user_id = ? 
        AND q.is_called = 1 
        AND q.status = 'pending' 
        AND q.is_confirmed = 0
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "is_called" => true,
        "ticket_number" => $row['ticket_number'],
        "workstation" => $row['workstation'],
        "called_at" => strtotime($row['called_at']),
        "wait_time" => $row['wait_time']
    ]);
} else {
    echo json_encode(["is_called" => false]);
}

$stmt->close();
$conn->close();
?>