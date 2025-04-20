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
$service_id = isset($_GET["service_id"]) ? intval($_GET["service_id"]) : 0; // Додано intval для безпеки

// Отримуємо інформацію про послугу
$stmt = $conn->prepare("SELECT id, name, description, interval_minutes, start_time, end_time FROM services WHERE id = ?");
$stmt->bind_param("i", $service_id);
$stmt->execute();
$result = $stmt->get_result();

if ($service_id === 0 || $result->num_rows === 0) { // Покращена перевірка
    $_SESSION['error'] = "Послугу не знайдено.";
    header("Location: index.php");
    exit();
}

$service = $result->fetch_assoc();
$stmt->close();

// ---- ВИПРАВЛЕННЯ ПОЧАТОК ----
// Отримуємо поточну дату
$current_date = date("Y-m-d");
// ЗАВЖДИ встановлюємо вибрану дату на поточну для початкового відображення
$selected_date = $current_date;
// Якщо дата передана через GET (наприклад, при оновленні слотів через JS),
// використовуємо її для завантаження слотів, але не для початкового значення поля
$date_for_slots_check = isset($_GET["selected_date"]) ? $_GET["selected_date"] : $current_date;
// Перевірка, чи передана дата не раніше поточної (на випадок маніпуляції GET)
if (strtotime($date_for_slots_check) < strtotime($current_date)) {
    $date_for_slots_check = $current_date;
}
// ---- ВИПРАВЛЕННЯ КІНЕЦЬ ----


// Обробка форми запису
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Перевірка CSRF токена (рекомендовано додати)
    // if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    //     die('Помилка CSRF!');
    // }

    $user_id = $_SESSION["user_id"];
    $appointment_date = $_POST["appointment_date"];
    $appointment_time = $_POST["appointment_time"];
    // Перевіряємо service_id з форми, а не з GET, щоб уникнути підміни
    $form_service_id = isset($_POST["service_id"]) ? intval($_POST["service_id"]) : 0;

    // Додаткова перевірка, чи відповідає послуга у формі тій, що відображалася
    if($form_service_id !== $service_id) {
         $error_message = "Помилка: Неправильна послуга.";
    } // Перевірка, чи обрана дата не в минулому
    elseif (strtotime($appointment_date) < strtotime(date("Y-m-d"))) {
         $error_message = "Неможливо записатися на минулу дату.";
    } else {
        $appointment_datetime = strtotime("$appointment_date $appointment_time");
        $current_datetime = time();
        $buffer_seconds = 60; // буфер 1 хвилина

        // Перевірка на минулий час із буфером
        if ($appointment_date == date("Y-m-d") && $appointment_datetime <= ($current_datetime + $buffer_seconds)) {
            $error_message = "Неможливо записатися на найближчий або минулий час.";
        } else {
            // 1. Перевірка на існуючий активний запис на цю саму послугу
            $stmt = $conn->prepare("SELECT id FROM queue WHERE user_id = ? AND service_id = ? AND status = 'pending' AND appointment_date >= CURDATE()"); // Додано перевірку дати >= сьогодні
            $stmt->bind_param("ii", $user_id, $service_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $error_message = "Ви вже маєте активний запис на цю послугу.";
            } else {
                // 2. Перевірка на перетин часу з іншими активними записами *користувача* на цю дату
                $stmt = $conn->prepare("
                    SELECT q.appointment_time, s.interval_minutes
                    FROM queue q
                    JOIN services s ON q.service_id = s.id
                    WHERE q.user_id = ? AND q.appointment_date = ? AND q.status = 'pending'
                ");
                $stmt->bind_param("is", $user_id, $appointment_date);
                $stmt->execute();
                $appointments_result = $stmt->get_result(); // Змінено ім'я змінної результату

                $new_start_time = strtotime($appointment_time);
                $new_end_time = $new_start_time + ($service['interval_minutes'] * 60); // Використовуємо $service

                $time_collision = false;
                while ($row = $appointments_result->fetch_assoc()) {
                    $existing_start_time = strtotime($row['appointment_time']);
                    $existing_end_time = $existing_start_time + ($row['interval_minutes'] * 60);

                    // Перевірка на перетин часу
                    if ($new_start_time < $existing_end_time && $new_end_time > $existing_start_time) {
                        $time_collision = true;
                        break;
                    }
                }

                if ($time_collision) {
                     $error_message = "Вибраний час перетинається з іншим вашим записом на цю дату.";
                 } else {
                    // 3. Перевірка доступності слота (чи не зайнятий він КИМОСЬ іншим)
                    $stmt_check_slot = $conn->prepare("SELECT id FROM queue WHERE service_id = ? AND appointment_date = ? AND appointment_time = ? AND status = 'pending'");
                    $stmt_check_slot->bind_param("iss", $service_id, $appointment_date, $appointment_time);
                    $stmt_check_slot->execute();
                    $slot_result = $stmt_check_slot->get_result();

                    if ($slot_result->num_rows > 0) {
                        $error_message = "На жаль, цей час щойно зайняли. Будь ласка, оберіть інший.";
                    } else {
                        // Логіка запису
                        $ticket_number = 'T' . strtoupper(substr(uniqid(), -4)) . rand(10,99); // Коротший та випадковіший номер
                        $stmt_insert = $conn->prepare("INSERT INTO queue (user_id, service_id, appointment_date, appointment_time, ticket_number, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                        $stmt_insert->bind_param("iisss", $user_id, $service_id, $appointment_date, $appointment_time, $ticket_number);

                        if ($stmt_insert->execute()) {
                            $success_message = "Ви успішно записалися на послугу \"{$service['name']}\". <br>Дата: $appointment_date <br>Час: $appointment_time <br>Номер вашого талону: <strong>$ticket_number</strong>.";
                             // Очищення localStorage після успішного запису
                            echo "<script>localStorage.removeItem('selectedDate'); localStorage.removeItem('selectedTime');</script>";
                        } else {
                            error_log("DB Error: " . $stmt_insert->error); // Логування помилки
                            $error_message = "Виникла помилка під час запису. Спробуйте ще раз пізніше.";
                        }
                         $stmt_insert->close();
                    }
                    $stmt_check_slot->close();
                }
                $stmt->close(); // Закриття stmt для перевірки перетину часу користувача
            }
        }
    }
}

// Генерація CSRF токена для форми
// $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <title>Запис на послугу: <?= htmlspecialchars($service["name"]) ?></title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <style>
        body {
            padding-top: 20px;
            padding-bottom: 40px; /* Додано відступ знизу */
            background-color: #f4f6f9; /* Світлий фон */
        }
        .container {
            max-width: 700px; /* Трохи ширше */
            margin: 0 auto;
            background-color: #fff; /* Білий фон для контейнера */
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
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
        .time-slots-container {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f8f9fa; /* Світлий фон для слотів */
        }
        .time-slot-button {
            /* width: 100%; */ /* Забрано */
            font-size: 0.9rem; /* Трохи менший шрифт */
            padding: 8px 5px; /* Менші падінги */
            min-width: 70px; /* Мінімальна ширина кнопки */
            margin: 2px !important; /* Додано !important для перевизначення Bootstrap */
        }
        .time-slot-button.selected {
            background-color: #007bff !important; /* Важливо для перевизначення */
            color: white !important;
            border-color: #0056b3 !important;
            font-weight: bold;
        }
        .time-slot-button:disabled {
            background-color: #e9ecef !important; /* Світло-сірий фон */
            color: #adb5bd !important;  /* Сірий текст */
            border-color: #ced4da !important; /* Сіра рамка */
            cursor: not-allowed;
            opacity: 0.8 !important; /* Трохи менш прозорий */
            text-decoration: line-through; /* Закреслений текст */
        }
        .time-slot-button:not(:disabled):hover {
             background-color: #d1ecff; /* Світло-блакитний при наведенні */
        }
        .card-header h2 {
             margin-bottom: 0;
        }
        .form-group label {
            font-weight: 500; /* Грубший шрифт для лейблів */
        }
        #loading-indicator {
            display: none; /* Приховано за замовчуванням */
            text-align: center;
            margin: 15px 0;
            color: #007bff;
        }
    </style>
    <script>
        $(document).ready(function() {
            // ---- ВИПРАВЛЕННЯ ПОЧАТОК ----
            // НЕ використовуємо localStorage для початкового значення дати.
            // Беремо значення, яке вже встановлено PHP в атрибуті value.
            var selectedDate = $('#appointment_date').val();
            // ---- ВИПРАВЛЕННЯ КІНЕЦЬ ----

            // Зберігаємо вибраний час (якщо був)
            var selectedTime = localStorage.getItem('selectedTime') || null;
            var serviceId = <?php echo $service_id; ?>;
            var scrollPosition = 0;
            var refreshInterval; // Змінна для інтервалу

            // ---- ВИПРАВЛЕННЯ ПОЧАТОК ----
            // НЕ потрібно оновлювати поле дати тут, PHP вже встановив value="<?php echo $selected_date; ?>"
            // $('#appointment_date').val(selectedDate); <-- ВИДАЛЕНО
            // ---- ВИПРАВЛЕННЯ КІНЕЦЬ ----

             // Елемент індикатора завантаження
             var loadingIndicator = $('#loading-indicator');

            function refreshTimeSlots() {
                // Зупиняємо попередній інтервал, якщо він існує
                if (refreshInterval) {
                    clearInterval(refreshInterval);
                }

                // Показуємо індикатор завантаження
                loadingIndicator.show();

                // Переконуємося, що використовуємо АКТУАЛЬНЕ значення поля дати
                selectedDate = $('#appointment_date').val();

                // Зберігаємо поточну позицію прокрутки
                scrollPosition = window.scrollY;

                $.ajax({
                    url: 'get_busy_times.php',
                    method: 'GET', // Явно вказуємо метод
                    data: { service_id: serviceId, appointment_date: selectedDate },
                    dataType: 'json',
                    timeout: 10000, // Таймаут 10 секунд
                    success: function(data) {
                        updateTimeSlots(data.time_slots);
                        // Відновлюємо позицію прокрутки
                        window.scrollTo(0, scrollPosition);
                         // Запускаємо інтервал тільки ПІСЛЯ успішного завантаження
                        startRefreshInterval();
                    },
                    error: function(xhr, status, error) {
                        console.error('Помилка завантаження часових слотів:', status, error, xhr.responseText);
                        // Можна показати повідомлення користувачу
                         $('.time-slots-container').html('<div class="alert alert-warning">Не вдалося завантажити доступний час. Спробуйте оновити сторінку.</div>');
                    },
                    complete: function() {
                        // Ховаємо індикатор завантаження після завершення запиту
                        loadingIndicator.hide();
                    }
                });
            }

            // Функція для запуску інтервалу оновлення
             function startRefreshInterval() {
                 if (refreshInterval) {
                    clearInterval(refreshInterval);
                 }
                 refreshInterval = setInterval(refreshTimeSlotsSilent, 7000); // Оновлення кожні 7 секунд без індикатора
             }

              // Тихе оновлення слотів (без індикатора завантаження)
            function refreshTimeSlotsSilent() {
                 var currentSelectedDate = $('#appointment_date').val(); // Використовуємо актуальну дату
                 $.ajax({
                    url: 'get_busy_times.php',
                    method: 'GET',
                    data: { service_id: serviceId, appointment_date: currentSelectedDate },
                    dataType: 'json',
                    timeout: 5000,
                    success: function(data) {
                        updateTimeSlots(data.time_slots);
                    },
                    error: function(xhr, status, error) {
                       console.error('Помилка тихого оновлення слотів:', status, error);
                       // При помилці тихого оновлення можна зупинити інтервал, щоб не спамити помилками
                       stopRefreshInterval();
                    }
                });
            }

            // Функція для зупинки інтервалу
             function stopRefreshInterval() {
                 if (refreshInterval) {
                    clearInterval(refreshInterval);
                    refreshInterval = null;
                 }
             }


            // Початкове завантаження
            refreshTimeSlots();

            // Зберігаємо вибрану дату в localStorage ТІЛЬКИ при зміні користувачем
             $('#appointment_date').change(function() {
                // Зупиняємо інтервал при зміні дати
                 stopRefreshInterval();

                selectedDate = $(this).val();
                // Перевірка, чи дата не в минулому через JS (додатково)
                var today = new Date().toISOString().split('T')[0];
                if (selectedDate < today) {
                    alert('Неможливо вибрати минулу дату.');
                    $(this).val(today); // Повертаємо на сьогодні
                    selectedDate = today;
                }

                localStorage.setItem('selectedDate', selectedDate); // Зберігаємо нову дату
                selectedTime = null; // Скидаємо вибраний час при зміні дати
                localStorage.removeItem('selectedTime');
                $('#appointment_time').val('');  // Очищаємо приховане поле часу
                $('#submit-booking').prop('disabled', true); // Блокуємо кнопку запису
                refreshTimeSlots(); // Оновлюємо слоти для нової дати і перезапускаємо інтервал
            });

            // Функція для оновлення слотів і відновлення вибору
            function updateTimeSlots(timeSlots) {
                var container = $('.time-slots-container');
                container.empty(); // Очищуємо контейнер перед додаванням нових слотів
                // Додаємо приховане поле один раз
                 container.append('<input type="hidden" name="appointment_time" id="appointment_time" required>');

                var row = $('<div class="d-flex flex-wrap justify-content-center"></div>'); // Використовуємо flexbox для кращого розташування
                var hasAvailableSlots = false;
                var isSelectedTimeStillAvailable = false;

                // Перевірка, чи є слоти взагалі
                if (!timeSlots || timeSlots.length === 0) {
                     container.append('<div class="alert alert-info text-center">На вибрану дату немає доступних слотів.</div>');
                     $('#submit-booking').prop('disabled', true);
                     $('.no-times-available').remove(); // Видаляємо старе повідомлення, якщо є
                     return; // Виходимо, якщо слотів немає
                 }


                $.each(timeSlots, function(index, slot) {
                    var isDisabled = !slot.available;
                     // Додаткова перевірка на минулий час для поточної дати
                     var today = new Date().toISOString().split('T')[0];
                     if (selectedDate === today) {
                         var now = new Date();
                         var slotTimeParts = slot.time.split(':');
                         var slotDateTime = new Date(selectedDate);
                         slotDateTime.setHours(parseInt(slotTimeParts[0], 10), parseInt(slotTimeParts[1], 10), 0, 0);

                         // Якщо час слоту вже минув + маленький буфер (наприклад, 1 хвилина), робимо його неактивним
                         if (slotDateTime.getTime() <= now.getTime() + 60000) {
                             isDisabled = true;
                         }
                     }

                    // Формуємо кнопку
                    var buttonClass = 'btn btn-outline-primary time-slot-button' + (isDisabled ? ' disabled' : '');
                    var buttonAttrs = isDisabled ? 'disabled title="Час недоступний або зайнятий"' : '';
                    var timeSlotBtn = $('<button type="button" class="' + buttonClass + '" ' +
                                          'data-time="' + slot.time + '" ' + buttonAttrs + '>' +
                                          slot.time + '</button>');

                    // Додаємо обробник кліку тільки для доступних кнопок
                     if (!isDisabled) {
                         timeSlotBtn.on('click', function() {
                             selectTimeSlot(slot.time);
                         });
                        hasAvailableSlots = true;
                        // Якщо цей слот був вибраний раніше і він *досі доступний*, вибираємо його
                        if (selectedTime === slot.time) {
                            isSelectedTimeStillAvailable = true;
                         }
                    }

                    row.append(timeSlotBtn); // Додаємо кнопку до рядка
                });

                container.append(row);

                 // Видаляємо старе повідомлення про відсутність слотів
                 $('.no-times-available').remove();

                 // Показуємо повідомлення, якщо НЕМАЄ доступних слотів
                if (!hasAvailableSlots) {
                    container.append('<div class="no-times-available">На жаль, всі доступні часові слоти на цю дату вже зайняті.</div>');
                    $('#submit-booking').prop('disabled', true);
                    selectedTime = null; // Скидаємо вибір, бо немає доступних
                    localStorage.removeItem('selectedTime');
                    $('#appointment_time').val('');
                } else {
                     // Якщо раніше вибраний час досі доступний, відновлюємо вибір
                    if (isSelectedTimeStillAvailable) {
                        $('.time-slot-button[data-time="' + selectedTime + '"]').addClass('selected');
                        $('#appointment_time').val(selectedTime);
                        $('#submit-booking').prop('disabled', false); // Розблоковуємо кнопку, бо час вибраний
                    } else {
                         // Якщо раніше вибраний час став недоступним, скидаємо вибір
                        if(selectedTime) {
                            $('#appointment_time').val('');
                             selectedTime = null;
                             localStorage.removeItem('selectedTime');
                             $('#submit-booking').prop('disabled', true); // Блокуємо, бо час не вибраний
                        } else {
                             $('#submit-booking').prop('disabled', true); // Блокуємо, бо час не вибраний
                        }
                    }
                }
            }

            // Функція для вибору часу і збереження в localStorage
            window.selectTimeSlot = function(time) {
                selectedTime = time;
                localStorage.setItem('selectedTime', time);
                $('#appointment_time').val(time); // Встановлюємо значення прихованого поля
                $('.time-slot-button').removeClass('selected'); // Знімаємо виділення з усіх кнопок
                $('.time-slot-button[data-time="' + time + '"]').addClass('selected'); // Виділяємо обрану
                $('#submit-booking').prop('disabled', false); // Розблоковуємо кнопку запису
            }

            // Скидання localStorage при успішному записі (з PHP)
            <?php if (isset($success_message)): ?>
                localStorage.removeItem('selectedDate');
                localStorage.removeItem('selectedTime');
            <?php endif; ?>
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
                <!-- <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"> -->
                <input type="hidden" name="service_id" value="<?php echo $service_id; ?>">

                <div class="form-group">
                    <label for="appointment_date"><i class="far fa-calendar-check mr-1"></i>Оберіть дату:</label>
                    <input type="date" name="appointment_date" id="appointment_date" class="form-control"
                           value="<?php echo $selected_date; ?>" min="<?php echo $current_date; ?>" required>
                     <small class="form-text text-muted">Календар доступних дат для запису.</small>
                </div>

                <div class="form-group">
                    <label for="appointment_time"><i class="far fa-clock mr-1"></i>Оберіть доступний час:</label>
                    <!-- Індикатор завантаження -->
                    <div id="loading-indicator">
                        <i class="fas fa-spinner fa-spin mr-2"></i>Завантаження доступного часу...
                    </div>
                    <div class="time-slots-container">
                        <!-- Тут будуть динамічно додані кнопки часових слотів -->
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
