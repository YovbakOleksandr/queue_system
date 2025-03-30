<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Функція для автоматичного скасування талонів за попередню добу
function cancelPastAppointments($conn) {
    $yesterday = date("Y-m-d", strtotime("-1 day"));
    $stmt = $conn->prepare("UPDATE queue SET status = 'canceled' WHERE appointment_date < ? AND status = 'pending'");
    $stmt->bind_param("s", $yesterday);
    $stmt->execute();
    $stmt->close();
}

// Викликаємо функцію для скасування старих талонів
cancelPastAppointments($conn);

// Отримуємо ID послуги з URL
$service_id = isset($_GET["service_id"]) ? $_GET["service_id"] : 0;

// Отримуємо інформацію про послугу
$stmt = $conn->prepare("SELECT id, name, description, interval_minutes, start_time, end_time FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php");
    exit();
}

$service = $result->fetch_assoc();
$stmt->close();

// Отримуємо вибрану дату або встановлюємо поточну
$current_date = date("Y-m-d");
$selected_date = isset($_GET["selected_date"]) ? $_GET["selected_date"] : $current_date;

// Обробка форми запису
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION["user_id"];
    $appointment_date = $_POST["appointment_date"];
    $appointment_time = $_POST["appointment_time"];
    $service_id = $_POST["service_id"];

    $appointment_datetime = strtotime("$appointment_date $appointment_time");
    $current_datetime = time();

    // Перевірка на минулий час із буфером (60 секунд)
    if ($appointment_datetime <= $current_datetime + 60) {
        $error_message = "Неможливо записатися на минулий час.";
    } else {
        // 1. Перевірка на існуючий активний запис на цю саму послугу
        $stmt = $conn->prepare("SELECT id FROM queue WHERE user_id = ? AND service_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $user_id, $service_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error_message = "Ви вже маєте активний запис на цю послугу.";
        } else {
            // 2. Перевірка на перетин часу з іншими активними записами
            $stmt = $conn->prepare("
                SELECT q.appointment_time, s.interval_minutes
                FROM queue q
                JOIN services s ON q.service_id = s.id
                WHERE q.user_id = ? AND q.appointment_date = ? AND q.status = 'pending'
            ");
            $stmt->bind_param("is", $user_id, $appointment_date);
            $stmt->execute();
            $result = $stmt->get_result();

            $new_start_time = strtotime($appointment_time);
            $new_end_time = $new_start_time + ($service['interval_minutes'] * 60);

            while ($row = $result->fetch_assoc()) {
                $existing_start_time = strtotime($row['appointment_time']);
                $existing_end_time = $existing_start_time + ($row['interval_minutes'] * 60);

                // Перевірка на перетин часу
                if ($new_start_time < $existing_end_time && $new_end_time > $existing_start_time) {
                    $error_message = "Вибраний час перетинається з іншим вашим записом.";
                    break;
                }
            }

            if (!isset($error_message)) {
                // Перевірка доступності слота
                $stmt = $conn->prepare("SELECT id FROM queue WHERE service_id = ? AND appointment_date = ? AND appointment_time = ? AND status = 'pending'");
                $stmt->bind_param("iss", $service_id, $appointment_date, $appointment_time);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $error_message = "Цей час вже зайнятий іншим користувачем.";
                } else {
                    // Логіка запису
                    $ticket_number = 'T' . rand(1000, 9999);
                    $stmt = $conn->prepare("INSERT INTO queue (user_id, service_id, appointment_date, appointment_time, ticket_number, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("iisss", $user_id, $service_id, $appointment_date, $appointment_time, $ticket_number);

                    if ($stmt->execute()) {
                        $success_message = "Ви успішно записалися на послугу. Ваш час: $appointment_date о $appointment_time. Номер вашого талону: $ticket_number.";
                    } else {
                        $error_message = "Помилка при записі. Спробуйте ще раз.";
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Запис на послугу</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .no-times-available {
            color: #dc3545;
            font-weight: bold;
            margin: 20px 0;
        }
        .time-slots-container {
            margin: 15px 0;
        }
        .time-slot-button {
            width: 100%;
        }
        .time-slot-button.selected {
            background-color: #007bff;
            color: white;
        }
        .time-slot-button:disabled {
            background-color: #dc3545;
            color: white;
            cursor: not-allowed;
            opacity: 0.6;
        }
    </style>
    <script>
        $(document).ready(function() {
            var selectedDate = localStorage.getItem('selectedDate') || $('#appointment_date').val();
            var selectedTime = localStorage.getItem('selectedTime') || null;
            var serviceId = <?php echo $service_id; ?>;
            var scrollPosition = 0;

            // Відновлюємо вибрану дату
            $('#appointment_date').val(selectedDate);

            function refreshTimeSlots() {
                // Зберігаємо поточну позицію прокрутки
                scrollPosition = window.scrollY;

                $.ajax({
                    url: 'get_busy_times.php',
                    data: { service_id: serviceId, appointment_date: selectedDate },
                    dataType: 'json',
                    success: function(data) {
                        updateTimeSlots(data.time_slots);
                        // Відновлюємо позицію прокрутки
                        window.scrollTo(0, scrollPosition);
                    },
                    error: function(xhr, status, error) {
                        console.error('Помилка завантаження часових слотів:', error, xhr.responseText);
                    }
                });
            }

            // Початкове завантаження та періодичне оновлення кожні 5 секунд
            refreshTimeSlots();
            setInterval(refreshTimeSlots, 5000);

            // Зберігаємо вибрану дату в localStorage при зміні
            $('#appointment_date').change(function() {
                selectedDate = $(this).val();
                localStorage.setItem('selectedDate', selectedDate);
                refreshTimeSlots();
            });

            // Функція для оновлення слотів і відновлення вибору
            function updateTimeSlots(timeSlots) {
                var container = $('.time-slots-container');
                container.empty();
                container.append('<input type="hidden" name="appointment_time" id="appointment_time" required>');

                var row = $('<div class="row"></div>');
                var hasAvailableSlots = false;

                $.each(timeSlots, function(index, slot) {
                    var buttonAttrs = slot.available ? 
                        'onclick="selectTimeSlot(\'' + slot.time + '\')"' : 
                        'disabled';
                    var buttonClass = 'btn btn-outline-primary btn-block time-slot-button' + (slot.available ? '' : ' disabled');

                    var timeSlotBtn = $('<div class="col-md-3 col-6 mb-2">' +
                        '<button type="button" class="' + buttonClass + '" ' +
                        'data-time="' + slot.time + '" ' +
                        buttonAttrs + '>' +
                        slot.time +
                        '</button>' +
                        '</div>');
                    row.append(timeSlotBtn);

                    if (slot.available) {
                        hasAvailableSlots = true;
                        // Якщо цей слот був вибраний раніше і він доступний, вибираємо його
                        if (selectedTime === slot.time) {
                            timeSlotBtn.find('button').addClass('selected');
                            $('#appointment_time').val(slot.time);
                            $('#submit-booking').prop('disabled', false);
                        }
                    }
                });

                container.append(row);

                $('.no-times-available').remove();
                if (!hasAvailableSlots) {
                    container.after('<div class="no-times-available">На вибрану дату немає доступних часових слотів.</div>');
                    $('#submit-booking').prop('disabled', true);
                } else if (!selectedTime) {
                    $('#submit-booking').prop('disabled', true);
                }
            }

            // Функція для вибору часу і збереження в localStorage
            window.selectTimeSlot = function(time) {
                selectedTime = time;
                localStorage.setItem('selectedTime', time);
                $('#appointment_time').val(time);
                $('.time-slot-button').removeClass('selected');
                $('.time-slot-button[data-time="' + time + '"]').addClass('selected');
                $('#submit-booking').prop('disabled', false);
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <h1>Запис на послугу</h1>
        <div class="card mb-4">
            <div class="card-header">
                <h2><?php echo $service["name"]; ?></h2>
            </div>
            <div class="card-body">
                <p><?php echo $service["description"]; ?></p>
                <p><strong>Тривалість обслуговування:</strong> <?php echo $service["interval_minutes"]; ?> хвилин</p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
            <a href="index.php" class="btn btn-primary">Повернутися на головну</a>
        <?php else: ?>
            <form method="post" action="">
                <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">

                <div class="form-group">
                    <label for="appointment_date">Оберіть дату:</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="form-control" 
                           value="<?php echo $selected_date; ?>" min="<?php echo $current_date; ?>" required>
                </div>

                <div class="form-group">
                    <label for="appointment_time">Оберіть доступний час:</label>
                    <div class="time-slots-container">
                        <!-- Тут будуть динамічно додані кнопки часових слотів -->
                    </div>
                    <small class="form-text text-muted">Тривалість запису: <?php echo $service['interval_minutes']; ?> хвилин</small>
                </div>

                <div class="form-group">
                    <button type="submit" id="submit-booking" class="btn btn-success btn-block" disabled>Записатися</button>
                </div>
            </form>
            <a href="index.php" class="btn btn-secondary">Повернутися на головну</a>
        <?php endif; ?>
    </div>
</body>
</html>