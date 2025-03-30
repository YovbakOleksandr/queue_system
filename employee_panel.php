<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    header("Location: login.php");
    exit();
}

include "db.php";

// Обробка POST-запиту для оновлення робочої станції
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_workstation"])) {
    $workstation = trim($_POST["workstation"]);
    $stmt = $conn->prepare("UPDATE users SET workstation = ? WHERE id = ?");
    $stmt->bind_param("si", $workstation, $_SESSION["user_id"]);
    $stmt->execute();
    $stmt->close();
    $_SESSION["workstation"] = $workstation;
    $_SESSION["success"] = "Робочу станцію оновлено!";
}

// Обробка POST-запиту для зміни фільтра послуг
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_filter"])) {
    $selected_services = $_POST["services"] ?? [];
    $_SESSION["selected_services"] = $selected_services;
    $_SESSION["success"] = "Фільтр послуг оновлено!";
}

// Обробка POST-запиту для виклику наступного клієнта
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["call_next"])) {
    $current_date = date("Y-m-d");
    $selected_services = $_SESSION["selected_services"] ?? [];
    $workstation = $_SESSION["workstation"] ?? "";

    if (empty($workstation)) {
        $_SESSION["error"] = "Будь ласка, спочатку вкажіть вашу робочу станцію!";
    } elseif (empty($selected_services)) {
        $_SESSION["error"] = "Оберіть хоча б одну послугу для обробки!";
    } else {
        $services_list = implode(",", $selected_services);

        $sql = "SELECT q.*, s.name as service_name 
                FROM queue q 
                JOIN services s ON q.service_id = s.id 
                JOIN employee_services es ON q.service_id = es.service_id 
                WHERE q.status = 'pending' 
                AND q.is_called = 0
                AND es.employee_id = " . $_SESSION["user_id"] . " 
                AND es.is_active = 1 
                AND q.appointment_date = '$current_date' 
                AND q.service_id IN ($services_list) 
                ORDER BY q.appointment_time LIMIT 1";
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ticket_number = $row['ticket_number'];
            $_SESSION["current_ticket"] = $ticket_number;

            $stmt = $conn->prepare("UPDATE queue SET is_called = 1, called_at = NOW(), workstation = ? WHERE ticket_number = ?");
            $stmt->bind_param("ss", $workstation, $ticket_number);
            $stmt->execute();
            $stmt->close();

            $_SESSION["success"] = "Викликано клієнта з номером талону: $ticket_number до робочої станції: $workstation";
        } else {
            $_SESSION["error"] = "На сьогодні записів для вибраних послуг більше немає!";
        }
    }
}

// Обробка POST-запиту для оновлення налаштування автоматичного виклику
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_auto_call"])) {
    $_SESSION["auto_call_next"] = isset($_POST["auto_call_next"]) ? true : false;
    $_SESSION["success"] = "Налаштування оновлено!";
    header("Location: employee_panel.php");
    exit();
}

// Отримання списку послуг, які працівник може обробляти
$employee_id = $_SESSION["user_id"];
$services = [];
$sql = "SELECT es.id, s.id as service_id, s.name, es.is_active 
        FROM employee_services es 
        JOIN services s ON es.service_id = s.id 
        WHERE es.employee_id = $employee_id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Отримання поточної робочої станції працівника
$stmt = $conn->prepare("SELECT workstation FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $_SESSION["workstation"] = $row['workstation'];
}
$stmt->close();

// Отримання поточної черги для вибраних послуг
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
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Панель працівника</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
            padding-bottom: 20px;
        }
        .timer { 
            color: #dc3545; 
            font-weight: bold; 
            display: block;
            margin-top: 5px;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            border-radius: 0.5rem;
        }
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 15px 20px;
        }
        .card-body {
            padding: 20px;
        }
        .btn-call-next {
            font-size: 18px;
            padding: 12px 25px;
        }
        .form-check-label {
            cursor: pointer;
        }
        .queue-table th {
            background-color: #f8f9fa;
        }
        .queue-row {
            transition: background-color 0.2s;
        }
        .queue-row:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (isset($_SESSION["success"])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle mr-2"></i><?= $_SESSION["success"] ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php unset($_SESSION["success"]); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION["error"])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle mr-2"></i><?= $_SESSION["error"] ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
            <?php unset($_SESSION["error"]); ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-4">
                <!-- Налаштування та фільтри -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog mr-2"></i>Налаштування робочої станції</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" class="mb-3">
                            <div class="form-group">
                                <label for="workstation"><i class="fas fa-desktop mr-1"></i>Номер робочої станції:</label>
                                <input type="text" name="workstation" id="workstation" class="form-control" 
                                    value="<?= isset($_SESSION["workstation"]) ? $_SESSION["workstation"] : '' ?>" required>
                                <small class="form-text text-muted">Вкажіть номер кабінету або робочого місця</small>
                            </div>
                            <button type="submit" name="update_workstation" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i>Оновити
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter mr-2"></i>Фільтр послуг</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <div class="form-group">
                                <?php foreach ($services as $service): ?>
                                    <div class="form-check mb-2">
                                        <input type="checkbox" name="services[]" id="service_<?= $service['service_id'] ?>" 
                                            value="<?= $service['service_id'] ?>" class="form-check-input" 
                                            <?= in_array($service['service_id'], $selected_services) ? 'checked' : '' ?>>
                                        <label class="form-check-label d-block" for="service_<?= $service['service_id'] ?>">
                                            <?= $service['name'] ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button type="submit" name="update_filter" class="btn btn-primary">
                                <i class="fas fa-sync-alt mr-1"></i>Оновити фільтр
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-clock mr-2"></i>Автоматичний виклик</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post">
                            <div class="form-check mb-3">
                                <input type="checkbox" name="auto_call_next" id="auto_call_next" class="form-check-input" 
                                    <?php echo isset($_SESSION["auto_call_next"]) && $_SESSION["auto_call_next"] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_call_next">
                                    Автоматично викликати наступного клієнта
                                </label>
                                <small class="form-text text-muted">Після завершення обслуговування</small>
                            </div>
                            <button type="submit" name="update_auto_call" class="btn btn-primary">
                                <i class="fas fa-save mr-1"></i>Зберегти
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Основна робоча область -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-bell mr-2"></i>Виклик клієнта</h5>
                    </div>
                    <div class="card-body text-center py-4">
                        <form method="POST" action="employee_panel.php">
                            <button type="submit" name="call_next" class="btn btn-primary btn-lg btn-call-next">
                                <i class="fas fa-user-plus mr-2"></i>Викликати наступного клієнта
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list-alt mr-2"></i>Поточна черга</h5>
                        <div class="form-group mb-0">
                            <input type="date" name="selected_date" id="selected_date" class="form-control" 
                                value="<?= $selected_date ?>" min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 queue-table" id="queue-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-ticket-alt mr-1"></i>Номер талону</th>
                                        <th><i class="fas fa-briefcase mr-1"></i>Послуга</th>
                                        <th><i class="fas fa-clock mr-1"></i>Час</th>
                                        <th><i class="fas fa-desktop mr-1"></i>Робоче місце</th>
                                        <th><i class="fas fa-tasks mr-1"></i>Дії</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($queue)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-info-circle mr-2"></i>Немає клієнтів у черзі на цю дату
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                    <?php foreach ($queue as $item): ?>
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
                                    <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Оновлення таймера
        function updateTimers() {
            $('.timer').each(function() {
                const calledTime = parseInt($(this).data('called'));
                const waitTime = parseInt($(this).data('wait'));
                const ticket = $(this).data('ticket');
                const currentTime = Math.floor(Date.now() / 1000);
                const remaining = waitTime - (currentTime - calledTime);
                
                if (remaining > 0) {
                    const minutes = Math.floor(remaining / 60);
                    const seconds = remaining % 60;
                    $(this).html(`<i class="fas fa-hourglass-half mr-1"></i>Час очікування: ${minutes}:${seconds.toString().padStart(2, '0')}`);
                } else {
                    $(this).html('<i class="fas fa-exclamation-triangle mr-1"></i>Час вийшов!');
                    if (!$(this).hasClass('cancelled')) {
                        $(this).addClass('cancelled');
                        $.post('update_status.php', { action: 'cancel', ticket: ticket }, updateQueue);
                    }
                }
            });
        }

        // Оновлення черги при зміні дати
        $('#selected_date').change(function() {
            const selectedDate = $(this).val();
            window.location.href = `employee_panel.php?selected_date=${selectedDate}`;
        });

        // Оновлення черги
        function updateQueue() {
            const selectedDate = $('#selected_date').val();
            $.ajax({
                url: 'get_queue.php',
                data: { selected_date: selectedDate },
                success: function(data) {
                    $('#queue-table tbody').html(data);
                    updateTimers();
                }
            });
        }

        // Функція для виклику наступного клієнта
        function callNextClient() {
            $.post('employee_panel.php', { call_next: true }, function(response) {
                updateQueue(); // Оновлюємо чергу після виклику
                // Очищаємо повідомлення про успіх після оновлення
                $('.alert-success').remove();
            });
        }

        // Обробка кнопок
        $(document).on('click', '.confirm-btn', function() {
            const ticket = $(this).data('ticket');
            $.post('update_status.php', { action: 'confirm', ticket: ticket }, updateQueue);
        });

        $(document).on('click', '.complete-btn', function() {
            const ticket = $(this).data('ticket');
            $.post('update_status.php', { action: 'complete', ticket: ticket }, function() {
                updateQueue();
                // Перевіряємо, чи увімкнено автоматичний виклик
                if ("<?php echo isset($_SESSION['auto_call_next']) && $_SESSION['auto_call_next'] ? 'true' : 'false'; ?>" === 'true') {
                    callNextClient();
                }
            });
        });

        $(document).on('click', '.cancel-btn', function() {
            const ticket = $(this).data('ticket');
            if (confirm('Ви впевнені, що хочете скасувати цей запис?')) {
                $.post('update_status.php', { action: 'cancel', ticket: ticket }, updateQueue);
            }
        });

        // Запуск оновлення
        setInterval(updateQueue, 5000);
        updateTimers();
        setInterval(updateTimers, 1000);
    </script>
</body>
</html>