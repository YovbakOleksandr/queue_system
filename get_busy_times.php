<?php
/* Перевірка сесії та прав доступу */
session_start();
if (!isset($_SESSION["user_id"])) {
    exit('Unauthorized');
}

include "db.php";

/* Отримання параметрів запиту */
$service_id = $_GET["service_id"];
$appointment_date = $_GET["appointment_date"];
$user_id = $_SESSION["user_id"];

/* Отримання зайнятих часів для послуги */
$busy_times = [];
$stmt = $conn->prepare("SELECT appointment_time FROM queue WHERE service_id = ? AND appointment_date = ? AND (status = 'pending' OR is_called = 1)");
$stmt->bind_param("is", $service_id, $appointment_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $busy_time = substr($row['appointment_time'], 0, 5);
    $busy_times[] = $busy_time;
}
$stmt->close();

/* Отримання зайнятих часів користувача */
$user_busy_times = [];
$stmt = $conn->prepare("SELECT appointment_time FROM queue WHERE user_id = ? AND appointment_date = ? AND status = 'pending'");
$stmt->bind_param("is", $user_id, $appointment_date);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_busy_time = substr($row['appointment_time'], 0, 5);
    $user_busy_times[] = $user_busy_time;
}
$stmt->close();

/* Об'єднання всіх зайнятих часів */
$all_busy_times = array_unique(array_merge($busy_times, $user_busy_times));

/* Отримання інформації про послугу */
$stmt = $conn->prepare("SELECT interval_minutes, start_time, end_time FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

/* Генерація часових слотів */
$current_date = date("Y-m-d");
$start_time = substr($service['start_time'], 0, 5);
$end_time = substr($service['end_time'], 0, 5);
$interval_minutes = $service['interval_minutes'];

$time_slots = [];
$start_timestamp = strtotime("$appointment_date $start_time");
$end_timestamp = strtotime("$appointment_date $end_time");
$current_timestamp = $start_timestamp;

while ($current_timestamp < $end_timestamp) {
    $time_slot = date("H:i", $current_timestamp);
    $slot_datetime = $current_timestamp;
    $is_available = true;

    if ($appointment_date == $current_date && $slot_datetime <= time()) {
        $is_available = false;
    } else {
        if (in_array($time_slot, $all_busy_times)) {
            $is_available = false;
        }
    }

    $time_slots[] = [
        'time' => $time_slot,
        'available' => $is_available
    ];

    $current_timestamp += $interval_minutes * 60;
}

/* Повернення результату */
header('Content-Type: application/json');
echo json_encode(['time_slots' => $time_slots]);