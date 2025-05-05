<?php
/* Перевірка сесії та підключення до БД */
session_start();
include "db.php";

/* Отримання поточної дати */
$current_date = date("Y-m-d");

/* Отримання списку активних записів */
$sql = "SELECT q.ticket_number, s.name as service_name, q.appointment_time, q.status, q.is_called, q.is_confirmed, q.workstation 
        FROM queue q 
        JOIN services s ON q.service_id = s.id 
        WHERE q.appointment_date = '$current_date' 
        AND q.status = 'pending' 
        ORDER BY q.appointment_time";
$result = $conn->query($sql);

/* Формування HTML для записів */
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>{$row['ticket_number']}</td>
                <td>{$row['service_name']}</td>
                <td>{$row['appointment_time']}</td>
                <td class='status " . ($row['is_confirmed'] ? 'confirmed' : ($row['is_called'] ? 'called' : 'pending')) . "'>
                    " . ($row['is_confirmed'] ? 'Обслуговується' : ($row['is_called'] ? 'Викликано' : 'Очікує')) . "
                </td>
                <td class='workstation'>
                    " . ($row['is_called'] && $row['workstation'] ? $row['workstation'] : '-') . "
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5'>На сьогодні записів немає.</td></tr>";
}
?>
