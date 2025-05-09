<?php
/* Перевірка сесії та прав доступу */
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "db.php";

/* Автоматичне скасування старих записів */
function cancelPastAppointments($conn) {
    $today = date("Y-m-d");
    $stmt = $conn->prepare("UPDATE queue SET status = 'cancelled' WHERE appointment_date < ? AND status = 'pending'");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $stmt->close();
}

cancelPastAppointments($conn);

/* Отримання та перевірка даних послуги */
$service_id = isset($_GET["service_id"]) ? intval($_GET["service_id"]) : 0;

$stmt = $conn->prepare("SELECT id, name, description, interval_minutes, start_time, end_time FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($service_id === 0 || $result->num_rows === 0) {
    $_SESSION['error'] = "Послугу не знайдено.";
    header("Location: index.php");
    exit();
}

$service = $result->fetch_assoc();
$stmt->close();

/* Налаштування дати */
$current_date = date("Y-m-d");
$selected_date = $current_date;
$date_for_slots_check = isset($_GET["selected_date"]) ? $_GET["selected_date"] : $current_date;
if (strtotime($date_for_slots_check) < strtotime($current_date)) {
    $date_for_slots_check = $current_date;
}

/* Обробка форми запису */
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"];
    $appointment_date = $_POST["appointment_date"];
    $appointment_time = $_POST["appointment_time"];
    $form_service_id = isset($_POST["service_id"]) ? intval($_POST["service_id"]) : 0;

    /* Валідація даних */
    if($form_service_id !== $service_id) {
        $error_message = "Помилка: Неправильна послуга.";
    } elseif (strtotime($appointment_date) < strtotime(date("Y-m-d"))) {
        $error_message = "Неможливо записатися на минулу дату.";
    } else {
        $appointment_datetime = strtotime("$appointment_date $appointment_time");
        $current_datetime = time();
        $buffer_seconds = 60;

        if ($appointment_date == date("Y-m-d") && $appointment_datetime <= ($current_datetime + $buffer_seconds)) {
            $error_message = "Неможливо записатися на найближчий або минулий час.";
        } else {
            /* Перевірка існуючого запису */
            $stmt = $conn->prepare("SELECT id FROM queue WHERE user_id = ? AND service_id = ? AND status = 'pending' AND appointment_date >= CURDATE()");
            $stmt->bind_param("ii", $user_id, $service_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error_message = "Ви вже маєте активний запис на цю послугу.";
            } else {
                /* Перевірка перетину часу */
                $stmt = $conn->prepare("
                    SELECT q.appointment_time, s.interval_minutes
                    FROM queue q
                    JOIN services s ON q.service_id = s.id
                    WHERE q.user_id = ? AND q.appointment_date = ? AND q.status = 'pending'
                ");
                $stmt->bind_param("is", $user_id, $appointment_date);
                $stmt->execute();
                $appointments_result = $stmt->get_result();

                $new_start_time = strtotime($appointment_time);
                $new_end_time = $new_start_time + ($service['interval_minutes'] * 60);

                $time_collision = false;
                while ($row = $appointments_result->fetch_assoc()) {
                    $existing_start_time = strtotime($row['appointment_time']);
                    $existing_end_time = $existing_start_time + ($row['interval_minutes'] * 60);

                    if ($new_start_time < $existing_end_time && $new_end_time > $existing_start_time) {
                        $time_collision = true;
                        break;
                    }
                }

                if ($time_collision) {
                    $error_message = "Вибраний час перетинається з іншим вашим записом на цю дату.";
                } else {
                    /* Перевірка доступності слота */
                    $stmt_check_slot = $conn->prepare("SELECT id FROM queue WHERE service_id = ? AND appointment_date = ? AND appointment_time = ? AND status = 'pending'");
                    $stmt_check_slot->bind_param("iss", $service_id, $appointment_date, $appointment_time);
                    $stmt_check_slot->execute();
                    $slot_result = $stmt_check_slot->get_result();

                    if ($slot_result->num_rows > 0) {
                        $error_message = "На жаль, цей час щойно зайняли. Будь ласка, оберіть інший.";
                    } else {
                        /* Створення запису */
                        $ticket_number = 'T' . strtoupper(substr(uniqid(), -4)) . rand(10,99);
                        $stmt_insert = $conn->prepare("INSERT INTO queue (user_id, service_id, appointment_date, appointment_time, ticket_number, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        $stmt_insert->bind_param("iisss", $user_id, $service_id, $appointment_date, $appointment_time, $ticket_number);

                        if ($stmt_insert->execute()) {
                            $success_message = "Шановний(а) {$_SESSION['full_name']}, Ви успішно записалися на послугу \"{$service['name']}\". <br>Дата: $appointment_date <br>Час: $appointment_time <br>Номер вашого талону: <strong>$ticket_number</strong>.<br><small class='text-muted'>Рекомендуємо з'явитися за 15 хвилин до призначеного часу.</small>";
                            echo "<script>localStorage.removeItem('selectedDate'); localStorage.removeItem('selectedTime');</script>";
                        } else {
                            error_log("DB Error: " . $stmt_insert->error);
                            $error_message = "Виникла помилка під час запису. Спробуйте ще раз пізніше.";
                        }
                        $stmt_insert->close();
                    }
                    $stmt_check_slot->close();
                }
                $stmt->close();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Запис на послугу: <?= htmlspecialchars($service["name"]) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        /* Основні стилі */
        body {
            padding-top: 20px;
            padding-bottom: 40px;
            background-color: #f4f6f9;
        }
        .container {
            max-width: 700px;
            margin: 0 auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        /* Стилі повідомлень */
        .no-times-available {
            color: #dc3545;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
            padding: 15px;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
        }

        /* Стилі слотів часу */
        .time-slots-container {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f8f9fa;
        }
        .time-slot-button {
            font-size: 0.9rem;
            padding: 8px 5px;
            min-width: 70px;
            margin: 2px !important;
        }
        .time-slot-button.selected {
            background-color: #007bff !important;
            color: white !important;
            border-color: #0056b3 !important;
            font-weight: bold;
        }
        .time-slot-button:disabled {
            background-color: #e9ecef !important;
            color: #adb5bd !important;
            border-color: #ced4da !important;
            cursor: not-allowed;
            opacity: 0.8 !important;
            text-decoration: line-through;
        }
        .time-slot-button:not(:disabled):hover {
            background-color: #d1ecff;
        }
        .card-header h2 {
            margin-bottom: 0;
        }
        .form-group label {
            font-weight: 500;
        }
    </style>
    <script>
        $(document).ready(function() {
            var selectedDate = $('#appointment_date').val();
            var selectedTime = localStorage.getItem('selectedTime') || null;
            var serviceId = <?php echo $service_id; ?>;
            var scrollPosition = 0;

            function updateTimers() {
                $('.timer').each(function() {
                    const calledTime = parseInt($(this).data('called'));
                    const waitTime = parseInt($(this).data('wait'));
                    const currentTime = Math.floor(Date.now() / 1000);
                    const elapsedTime = currentTime - calledTime;

                    if (isNaN(calledTime) || isNaN(waitTime)) return;

                    const remaining = waitTime - elapsedTime;
                    if (remaining > 0) {
                        const minutes = Math.floor(remaining / 60);
                        const seconds = remaining % 60;
                        $(this).html(`<i class="fas fa-hourglass-half mr-1"></i>${minutes}:${seconds.toString().padStart(2, '0')}`);
                        $(this).removeClass('badge-secondary').addClass('badge-danger');
                    } else {
                        $(this).html(`<i class="fas fa-exclamation-triangle mr-1"></i>Час вийшов`);
                        $(this).removeClass('badge-danger').addClass('badge-secondary');
                    }
                });
            }

            function refreshTimeSlots() {
                scrollPosition = window.scrollY;

                $.ajax({
                    url: 'get_busy_times.php',
                    method: 'GET',
                    data: { service_id: serviceId, appointment_date: selectedDate },
                    dataType: 'json',
                    timeout: 10000,
                    success: function(data) {
                        updateTimeSlots(data.time_slots);
                        window.scrollTo(0, scrollPosition);
                    },
                    error: function(xhr, status, error) {
                        console.error('Помилка завантаження часових слотів:', status, error, xhr.responseText);
                        $('.time-slots-container').html('<div class="alert alert-warning">Не вдалося завантажити доступний час. Спробуйте оновити сторінку.</div>');
                    }
                });
            }

            function updateTimeSlots(timeSlots) {
                var container = $('.time-slots-container');
                container.empty();
                container.append('<input type="hidden" name="appointment_time" id="appointment_time" required>');

                var row = $('<div class="d-flex flex-wrap justify-content-center"></div>');
                var hasAvailableSlots = false;
                var isSelectedTimeStillAvailable = false;

                if (!timeSlots || timeSlots.length === 0) {
                    container.append('<div class="alert alert-info text-center">На вибрану дату немає доступних слотів.</div>');
                    $('#submit-booking').prop('disabled', true);
                    $('.no-times-available').remove();
                    return;
                }

                $.each(timeSlots, function(index, slot) {
                    var isDisabled = !slot.available;
                    var today = new Date().toISOString().split('T')[0];
                    if (selectedDate === today) {
                        var now = new Date();
                        var slotTimeParts = slot.time.split(':');
                        var slotDateTime = new Date(selectedDate);
                        slotDateTime.setHours(parseInt(slotTimeParts[0], 10), parseInt(slotTimeParts[1], 10), 0, 0);

                        if (slotDateTime.getTime() <= now.getTime() + 60000) {
                            isDisabled = true;
                        }
                    }

                    var buttonClass = 'btn btn-outline-primary time-slot-button' + (isDisabled ? ' disabled' : '');
                    var buttonAttrs = isDisabled ? 'disabled title="Час недоступний або зайнятий"' : '';
                    var timeSlotBtn = $('<button type="button" class="' + buttonClass + '" ' +
                                      'data-time="' + slot.time + '" ' + buttonAttrs + '>' +
                                      slot.time + '</button>');

                    if (!isDisabled) {
                        timeSlotBtn.on('click', function() {
                            selectTimeSlot(slot.time);
                        });
                        hasAvailableSlots = true;
                        if (selectedTime === slot.time) {
                            isSelectedTimeStillAvailable = true;
                        }
                    }

                    row.append(timeSlotBtn);
                });

                container.append(row);
                $('.no-times-available').remove();

                if (!hasAvailableSlots) {
                    container.append('<div class="no-times-available">На жаль, всі доступні часові слоти на цю дату вже зайняті.</div>');
                    $('#submit-booking').prop('disabled', true);
                    selectedTime = null;
                    localStorage.removeItem('selectedTime');
                    $('#appointment_time').val('');
                } else {
                    if (isSelectedTimeStillAvailable) {
                        $('.time-slot-button[data-time="' + selectedTime + '"]').addClass('selected');
                        $('#appointment_time').val(selectedTime);
                        $('#submit-booking').prop('disabled', false);
                    } else {
                        if(selectedTime) {
                            $('#appointment_time').val('');
                            selectedTime = null;
                            localStorage.removeItem('selectedTime');
                            $('#submit-booking').prop('disabled', true);
                        } else {
                            $('#submit-booking').prop('disabled', true);
                        }
                    }
                }
            }

            window.selectTimeSlot = function(time) {
                selectedTime = time;
                localStorage.setItem('selectedTime', time);
                $('#appointment_time').val(time);
                $('.time-slot-button').removeClass('selected');
                $('.time-slot-button[data-time="' + time + '"]').addClass('selected');
                $('#submit-booking').prop('disabled', false);
            }

            <?php if (isset($success_message)): ?>
                localStorage.removeItem('selectedDate');
                localStorage.removeItem('selectedTime');
            <?php endif; ?>

            refreshTimeSlots();
            setInterval(refreshTimeSlots, 7000);

            $('#appointment_date').change(function() {
                selectedDate = $(this).val();
                var today = new Date().toISOString().split('T')[0];
                if (selectedDate < today) {
                    alert('Неможливо вибрати минулу дату.');
                    $(this).val(today);
                    selectedDate = today;
                }

                localStorage.setItem('selectedDate', selectedDate);
                selectedTime = null;
                localStorage.removeItem('selectedTime');
                $('#appointment_time').val('');
                $('#submit-booking').prop('disabled', true);
                refreshTimeSlots();
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0"><i class="fas fa-clipboard-list mr-2"></i>Запис на послугу: <?= htmlspecialchars($service["name"]) ?></h2>
            </div>
            <div class="card-body">
                <?php if (!empty($service["description"])): ?>
                    <p><i class="fas fa-info-circle mr-1 text-secondary"></i> <?= nl2br(htmlspecialchars($service["description"])) ?></p>
                <?php endif; ?>
                <p><i class="far fa-clock mr-1 text-secondary"></i> <strong>Орієнтовна тривалість:</strong> <?= $service["interval_minutes"] ?> хвилин</p>
                <p><i class="far fa-calendar-alt mr-1 text-secondary"></i> <strong>Години роботи:</strong> з <?= substr($service["start_time"], 0, 5) ?> до <?= substr($service["end_time"], 0, 5) ?></p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle mr-2"></i><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle mr-2"></i><?php echo $success_message; ?></div>
            <a href="index.php" class="btn btn-primary"><i class="fas fa-home mr-1"></i>Повернутися на головну</a>
            <a href="public_queue.php" class="btn btn-info"><i class="fas fa-users mr-1"></i>Переглянути чергу</a>
        <?php else: ?>
            <form id="booking-form" method="post" action="">
                <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">

                <div class="form-group">
                    <label for="appointment_date"><i class="far fa-calendar-check mr-1"></i>Оберіть дату:</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="form-control"
                           value="<?php echo $selected_date; ?>" min="<?php echo $current_date; ?>" required>
                    <small class="form-text text-muted">Календар доступних дат для запису.</small>
                </div>

                <div class="form-group">
                    <label for="appointment_time"><i class="far fa-clock mr-1"></i>Оберіть доступний час:</label>
                    <div class="time-slots-container">
                        <input type="hidden" name="appointment_time" id="appointment_time" required>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <button type="submit" id="submit-booking" class="btn btn-success btn-lg btn-block" disabled>
                        <i class="fas fa-calendar-plus mr-2"></i>Записатися
                    </button>
                </div>
            </form>
            <div class="mt-3 text-center">
                <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-1"></i>Повернутися до списку послуг</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
