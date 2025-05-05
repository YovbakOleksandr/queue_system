<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'employee') {
    header("Location: login.php");
    exit();
}

include "db.php";

// Додавання колонки called_by_employee_id, якщо її ще немає
$check_column = $conn->query("SHOW COLUMNS FROM queue LIKE 'called_by_employee_id'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE queue ADD COLUMN called_by_employee_id INT NULL AFTER workstation");
}

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
    // Перевірка, чи це не автоматичний виклик при завантаженні сторінки
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
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

                $stmt = $conn->prepare("UPDATE queue SET is_called = 1, called_at = NOW(), workstation = ?, called_by_employee_id = ? WHERE ticket_number = ?");
                $stmt->bind_param("sis", $workstation, $_SESSION["user_id"], $ticket_number);
                $stmt->execute();
                $stmt->close();

                $_SESSION["success"] = "Викликано клієнта з номером талону: $ticket_number до робочої станції: $workstation";
            } else {
                $_SESSION["error"] = "На сьогодні записів для вибраних послуг більше немає!";
            }
        }
    }
}

// Обробка POST-запиту для оновлення налаштування автоматичного виклику
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_auto_call"])) {
    $_SESSION["auto_call_next"] = isset($_POST["auto_call_next"]) ? true : false;
    $_SESSION["success"] = "Налаштування оновлено!";
    // редірект на index.php щоб залишатись у шапці
    header("Location: index.php");
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

<style>
    .table-responsive {
        overflow-x: auto;
    }
    .queue-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 0.9rem;
        margin: 0;
    }
    .queue-table th, .queue-table td {
        padding: 10px;
        text-align: center;
        border-bottom: 1px solid #e0e0e0;
        vertical-align: middle;
    }
    .queue-table th {
        background-color: #007bff;
        color: #fff;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
    }
    .queue-table tr:hover {
        background-color: #f5f5f5;
    }
    .queue-table td {
        background-color: #fff;
    }
    .queue-table th:nth-child(1), .queue-table td:nth-child(1) { width: 15%; }
    .queue-table th:nth-child(2), .queue-table td:nth-child(2) { width: 30%; }
    .queue-table th:nth-child(3), .queue-table td:nth-child(3) { width: 15%; }
    .queue-table th:nth-child(4), .queue-table td:nth-child(4) { width: 20%; }
    .queue-table th:nth-child(5), .queue-table td:nth-child(5) { width: 20%; }
    .service-name {
        white-space: normal;
        word-break: break-word;
        line-height: 1.4;
        max-width: 250px;
        margin: 0 auto;
    }
    .status-badge {
        display: inline-block;
        padding: 5px 10px;
        font-size: 0.8rem;
        border-radius: 4px;
        font-weight: 500;
    }
    .status-badge.pending {
        background-color: #fff3cd;
        color: #856404;
    }
    .status-badge.called {
        background-color: #d1ecf1;
        color: #0c5460;
    }
    .status-badge.confirmed {
        background-color: #d4edda;
        color: #155724;
    }
    .status-badge.other-employee {
        background-color: #e2e3e5;
        color: #383d41;
    }
    .workstation {
        display: block;
        margin-top: 4px;
        font-size: 0.75rem;
        color: #6c757d;
    }
    .timer {
        display: inline-block;
        padding: 5px 10px;
        font-size: 0.8rem;
        background-color: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        margin-bottom: 6px;
        white-space: nowrap;
    }
    .actions-cell {
        position: relative;
        min-width: 150px;
        max-width: 180px;
        white-space: nowrap;
    }
    .action-buttons {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 6px;
    }
    .btn {
        padding: 5px 10px;
        font-size: 0.8rem;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        transition: background-color 0.2s;
        white-space: nowrap;
    }
    .confirm-btn {
        background-color: #007bff;
        color: #fff;
    }
    .confirm-btn:hover {
        background-color: #0056b3;
    }
    .cancel-btn {
        background-color: #dc3545;
        color: #fff;
    }
    .cancel-btn:hover {
        background-color: #c82333;
    }
    .complete-btn {
        background-color: #28a745;
        color: #fff;
    }
    .complete-btn:hover {
        background-color: #218838;
    }
</style>

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
                    <form id="call-next-form" method="POST" action="">
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
                        <table class="queue-table" id="queue-table">
                            <thead>
                                <tr>
                                    <th>Номер</th>
                                    <th>Послуга</th>
                                    <th>Час</th>
                                    <th>Статус/Місце</th>
                                    <th>Дії</th>
                                </tr>
                            </thead>
                            <tbody id="queue-table-body">
                                <!-- Динамічний вміст з get_queue.php -->
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

            // Додатковий захист: якщо called_at або wait_time некоректні, не запускати таймер
            if (!calledTime || !waitTime || isNaN(calledTime) || isNaN(waitTime) || waitTime < 10) {
                $(this).html('<i class="fas fa-exclamation-triangle mr-1"></i>Таймер не налаштовано');
                return;
            }

            const remaining = waitTime - (currentTime - calledTime);

            if (remaining > 0) {
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                $(this).html(`<i class=\"fas fa-hourglass-half mr-1\"></i>Час очікування: ${minutes}:${seconds.toString().padStart(2, '0')}`);
            } else {
                $(this).html('<i class="fas fa-exclamation-triangle mr-1"></i>Час вийшов!');
                // НЕ викликаємо cancel автоматично!
                // Скасування лише вручну через кнопку
            }
        });
    }

    // Оновлення черги при зміні дати
    $('#selected_date').change(function() {
        const selectedDate = $(this).val();
        window.location.href = `index.php?selected_date=${selectedDate}`;
    });

    // Оновлення черги
    function updateQueue() {
        const selectedDate = $('#selected_date').val();
        $.ajax({
            url: 'get_queue.php',
            data: { selected_date: selectedDate },
            success: function(data) {
                $('#queue-table-body').html(data);
                updateTimers();
            }
        });
    }

    // Виклик наступного клієнта через AJAX, без reload
    $(document).on('submit', '#call-next-form', function(e) {
        e.preventDefault();
        callNextClient();
    });

    function callNextClient() {
        $.ajax({
            url: 'index.php',
            type: 'POST',
            data: { call_next: true },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                updateQueue(); // Оновлюємо чергу після виклику
                $('.alert-success').remove();
            }
        });
    }

    // Обробка кнопок
    $(document).on('click', '.confirm-btn', function() {
        const ticket = $(this).data('ticket');
        $.ajax({
            url: 'update_status.php',
            type: 'POST',
            data: { 
                action: 'confirm', 
                ticket: ticket,
                employee_id: <?php echo $_SESSION["user_id"]; ?>
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                updateQueue();
            }
        });
    });

    $(document).on('click', '.complete-btn', function() {
        const ticket = $(this).data('ticket');
        $.ajax({
            url: 'update_status.php',
            type: 'POST',
            data: { 
                action: 'complete', 
                ticket: ticket,
                employee_id: <?php echo $_SESSION["user_id"]; ?>
            },
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function(response) {
                updateQueue();
                // Перевіряємо, чи увімкнено автоматичний виклик
                if ("<?php echo isset($_SESSION['auto_call_next']) && $_SESSION['auto_call_next'] ? 'true' : 'false'; ?>" === 'true') {
                    callNextClient();
                }
            }
        });
    });

    $(document).on('click', '.cancel-btn', function() {
        const ticket = $(this).data('ticket');
        if (confirm('Ви впевнені, що хочете скасувати цей запис?')) {
            $.ajax({
                url: 'update_status.php',
                type: 'POST',
                data: { 
                    action: 'cancel', 
                    ticket: ticket,
                    employee_id: <?php echo $_SESSION["user_id"]; ?>
                },
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    updateQueue();
                }
            });
        }
    });

    // Запуск оновлення
    setInterval(updateQueue, 5000);
    updateTimers();
    setInterval(updateTimers, 1000);
</script>