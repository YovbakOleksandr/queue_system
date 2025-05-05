<?php
/* Перевірка сесії та прав доступу */
session_start();
if (!isset($_SESSION["user_id"])) {
    exit();
}

include "db.php";

/* Отримання записів користувача */
$user_queue = [];
$sql = "SELECT q.*, s.name as service_name FROM queue q JOIN services s ON q.service_id = s.id WHERE q.user_id = " . $_SESSION["user_id"];
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $user_queue[] = $row;
    }
}

/* Сортування талонів за пріоритетом */
usort($user_queue, function($a, $b) {
    // Визначення пріоритету для кожного запису
    $getPriority = function($item) {
        if ($item['is_called'] == 1 && $item['is_confirmed'] == 0) {
            return 1; // Найвищий пріоритет - викликані
        } elseif ($item['status'] == 'pending') {
            return 2; // Середній пріоритет - очікують
        } else {
            return 3; // Найнижчий пріоритет - завершені або скасовані
        }
    };
    
    $priorityA = $getPriority($a);
    $priorityB = $getPriority($b);
    
    // Якщо пріоритети різні, сортуємо за пріоритетом
    if ($priorityA !== $priorityB) {
        return $priorityA - $priorityB;
    }
    
    // Якщо пріоритети однакові, сортуємо за датою та часом
    $timeA = strtotime($a['appointment_date'] . ' ' . $a['appointment_time']);
    $timeB = strtotime($b['appointment_date'] . ' ' . $b['appointment_time']);
    
    return $timeB - $timeA;
});

/* Відображення записів */
if (!empty($user_queue)) {
    echo '<ul class="service-list">';
    foreach ($user_queue as $item) {
        /* Визначення статусу запису */
        $status_class = '';
        $status_icon = '';
        $status_text = '';
        
        if ($item['status'] == 'completed') {
            $status_class = 'text-success';
            $status_icon = 'check-circle';
            $status_text = 'Завершено';
        } elseif ($item['status'] == 'cancelled') {
            $status_class = 'text-danger';
            $status_icon = 'times-circle';
            $status_text = 'Скасовано';
        } elseif ($item['is_called'] == 1 && $item['is_confirmed'] == 1) {
            $status_class = 'text-primary';
            $status_icon = 'user-clock';
            $status_text = 'Обслуговується';
        } elseif ($item['is_called'] == 1) {
            $status_class = 'text-info';
            $status_icon = 'bell';
            $status_text = 'Викликано';
        } else {
            $status_class = 'text-warning';
            $status_icon = 'clock';
            $status_text = 'Очікує';
        }
        
        /* Формування HTML для запису */
        echo '<li>';
        echo '<div class="row align-items-center">';
        
        // Інформація про запис
        echo '<div class="col-md-9">';
        echo '<h5 class="mb-1"><i class="fas fa-ticket-alt mr-2"></i>Номер талону: <strong>' . $item['ticket_number'] . '</strong></h5>';
        echo '<div class="row mt-3">';
        echo '<div class="col-md-6">';
        echo '<p><i class="fas fa-briefcase mr-2"></i>Послуга: <strong>' . $item['service_name'] . '</strong></p>';
        echo '<p><i class="fas fa-calendar-day mr-2"></i>Дата: <strong>' . $item['appointment_date'] . '</strong></p>';
        echo '</div>';
        echo '<div class="col-md-6">';
        echo '<p><i class="fas fa-clock mr-2"></i>Час: <strong>' . $item['appointment_time'] . '</strong></p>';
        
        if ($item['is_called'] == 1 && !empty($item['workstation'])) {
            echo '<p><i class="fas fa-map-marker-alt mr-2"></i>Робоче місце: <strong>' . $item['workstation'] . '</strong></p>';
        }
        
        echo '</div>';
        echo '</div>';
        
        echo '<p class="mt-2"><i class="fas fa-info-circle mr-2"></i>Статус: ';
        echo '<span class="' . $status_class . '"><i class="fas fa-' . $status_icon . ' mr-1"></i>' . $status_text . '</span>';
        echo '</p>';
        echo '</div>';
        
        // Кнопки дій
        echo '<div class="col-md-3 text-right">';
        if ($item['status'] == 'pending') {
            echo '<a href="cancel.php?ticket_number=' . $item['ticket_number'] . '" class="btn btn-outline-danger"><i class="fas fa-times mr-1"></i>Скасувати</a>';
        } elseif ($item['status'] == 'completed' || $item['status'] == 'cancelled') {
            echo '<a href="cancel.php?ticket_number=' . $item['ticket_number'] . '" class="btn btn-outline-danger"><i class="fas fa-trash mr-1"></i>Видалити</a>';
        }
        echo '</div>';
        
        echo '</div>';
        echo '</li>';
    }
    echo '</ul>';
} else {
    echo '<div class="alert alert-info"><i class="fas fa-info-circle mr-2"></i>У вас немає активних записів.</div>';
}
?>