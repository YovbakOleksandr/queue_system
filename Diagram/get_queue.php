<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    exit();
}

include "db.php";

$employee_id = $_SESSION["user_id"];
$selected_date = isset($_GET["selected_date"]) ? $_GET["selected_date"] : date("Y-m-d");
$selected_services = $_SESSION["selected_services"] ?? [];
$queue = [];

if (!empty($selected_services)) {
    $services_list = implode(",", $selected_services);
    $sql = "SELECT q.*, s.name as service_name, s.wait_time 
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

if (empty($queue)) {
    echo '<tr><td colspan="5" class="text-center py-4"><i class="fas fa-info-circle mr-2"></i>Немає клієнтів у черзі на цю дату</td></tr>';
} else {
    foreach ($queue as $item): ?>
        <tr class="queue-row">
            <td><?= $item['ticket_number'] ?></td>
            <td><?= $item['service_name'] ?></td>
            <td><?= $item['appointment_time'] ?></td>
            <td>
                <?php if ($item['workstation']): ?>
                    <span class="badge badge-info">Місце <?= $item['workstation'] ?></span>
                <?php else: ?>
                    <span class="badge badge-secondary">Не призначено</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($item['is_called'] == 1 && $item['is_confirmed'] == 0): ?>
                    <span class="timer" data-called="<?= strtotime($item['called_at']) ?>" data-wait="<?= $item['wait_time'] ?>" data-ticket="<?= $item['ticket_number'] ?>"></span>
                    <button class="btn btn-primary btn-sm confirm-btn" data-ticket="<?= $item['ticket_number'] ?>">
                        <i class="fas fa-user-check mr-1"></i>Підтвердити
                    </button>
                <?php elseif ($item['is_confirmed'] == 1): ?>
                    <button class="btn btn-success btn-sm complete-btn" data-ticket="<?= $item['ticket_number'] ?>">
                        <i class="fas fa-check-circle mr-1"></i>Завершити
                    </button>
                <?php endif; ?>
                <button class="btn btn-outline-danger btn-sm cancel-btn" data-ticket="<?= $item['ticket_number'] ?>">
                    <i class="fas fa-times-circle mr-1"></i>Скасувати
                </button>
            </td>
        </tr>
    <?php endforeach;
}
?>