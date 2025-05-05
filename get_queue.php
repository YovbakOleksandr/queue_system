<?php
// --- Ініціалізація сесії та перевірка доступу ---
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    exit();
}
// --- Перевірка AJAX-запиту ---
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    exit();
}
include "db.php";

// --- Отримання черги для працівника ---
$employee_id = $_SESSION["user_id"];
$selected_date = isset($_GET["selected_date"]) ? $_GET["selected_date"] : date("Y-m-d");
$selected_services = $_SESSION["selected_services"] ?? [];
$queue = [];
if (!empty($selected_services)) {
    $services_list = implode(",", $selected_services);
    $sql = "SELECT q.*, s.name as service_name, s.wait_time, q.called_by_employee_id 
            FROM queue q 
            JOIN services s ON q.service_id = s.id 
            JOIN employee_services es ON q.service_id = es.service_id 
            WHERE q.status = 'pending' 
            AND q.appointment_date = '$selected_date' 
            AND es.employee_id = $employee_id 
            AND es.is_active = 1 
            AND q.service_id IN ($services_list) 
            ORDER BY q.appointment_time";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $queue[] = $row;
        }
    }
}

// --- Формування HTML для таблиці черги ---
if (empty($queue)) {
    echo '<tr><td colspan="5" class="text-center py-4">Немає клієнтів у черзі на цю дату</td></tr>';
} else {
    foreach ($queue as $item): 
        $status_class = '';
        $status_text = '';
        if ($item['is_confirmed'] == 1) {
            $status_class = 'confirmed';
            $status_text = 'Обслуговується';
        } elseif ($item['is_called'] == 1) {
            if ($item['called_by_employee_id'] == $employee_id) {
                $status_class = 'called';
                $status_text = 'Викликано';
            } else {
                $status_class = 'other-employee';
                $status_text = 'Обслуговується іншим';
            }
        } else {
            $status_class = 'pending';
            $status_text = 'Очікує';
        }
    ?>
        <tr class="queue-row">
            <td><?= $item['ticket_number'] ?></td>
            <td class="service-name"><?= $item['service_name'] ?></td>
            <td><?= $item['appointment_time'] ?></td>
            <td>
                <span class="status-badge <?= $status_class ?>"><?= $status_text ?></span>
                <?php if ($item['workstation']): ?>
                    <span class="workstation"><?= $item['workstation'] ?></span>
                <?php endif; ?>
            </td>
            <td class="actions-cell">
                <?php if ($item['is_called'] == 1 && $item['is_confirmed'] == 0 && $item['called_by_employee_id'] == $employee_id): ?>
                    <div class="timer" data-called="<?= strtotime($item['called_at']) ?>" data-wait="<?= $item['wait_time'] ?>" data-ticket="<?= $item['ticket_number'] ?>"></div>
                    <div class="action-buttons">
                        <button class="btn confirm-btn" data-ticket="<?= $item['ticket_number'] ?>">Підтвердити</button>
                        <button class="btn cancel-btn" data-ticket="<?= $item['ticket_number'] ?>">Скасувати</button>
                    </div>
                <?php elseif ($item['is_confirmed'] == 1 && $item['called_by_employee_id'] == $employee_id): ?>
                    <button class="btn complete-btn" data-ticket="<?= $item['ticket_number'] ?>">Завершити</button>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach;
}
?>

