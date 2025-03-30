<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

include "db.php";

// Отримання доступних послуг
$services = [];
$sql = "SELECT id, name, description FROM services";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель користувача</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title text-primary"><i class="fas fa-clipboard-list mr-2"></i>Доступні послуги</h3>
                        <div class="row mt-4">
                            <?php if (!empty($services)): ?>
                                <?php foreach ($services as $service): ?>
                                    <div class="col-md-4 mb-4">
                                        <div class="card h-100 border-0 shadow-sm service-card">
                                            <div class="card-body">
                                                <h5 class="card-title text-dark"><?= htmlspecialchars($service['name']) ?></h5>
                                                <p class="card-text text-secondary"><?= htmlspecialchars($service['description']) ?></p>
                                                <a href="book.php?service_id=<?= $service['id'] ?>" class="btn btn-primary btn-block">
                                                    <i class="fas fa-calendar-check mr-2"></i>Записатися
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i>Наразі доступних послуг немає.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title text-primary"><i class="fas fa-ticket-alt mr-2"></i>Ваші записи</h3>
                        <div id="user-queue" class="mt-4">
                            <?php include 'get_user_queue.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Блок для таймера -->
        <div id="timer-container" class="mt-4">
            <div id="notification" class="notification"></div>
        </div>
    </div>

    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            z-index: 1000;
            display: none;
            transition: opacity 0.5s ease-in-out;
        }
        .notification.show {
            display: block;
            opacity: 1;
        }
        .notification.hide {
            opacity: 0;
        }
        .service-card {
            transition: transform 0.2s ease-in-out;
            border-radius: 10px;
        }
        .service-card:hover {
            transform: translateY(-5px);
        }
        .service-list {
            list-style-type: none;
            padding: 0;
        }
        .service-list li {
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .service-list li:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .timer {
            color: #dc3545;
            font-weight: bold;
            display: inline-block;
            margin-left: 10px;
        }
    </style>

    <script>
    // Глобальні змінні для часу виклику та часу очікування
    let calledTime = null;
    let waitTime = null;
    let calledTicket = null;

    function updateTimers() {
        // Оновлюємо тільки таймер у повідомленні про виклик
        if (calledTime && waitTime && calledTicket) {
            const currentTime = Math.floor(Date.now() / 1000);
            const remaining = waitTime - (currentTime - calledTime);
            
            if (remaining > 0) {
                const minutes = Math.floor(remaining / 60);
                const seconds = remaining % 60;
                $('#notification .timer').text(`Час очікування: ${minutes}:${seconds.toString().padStart(2, '0')}`);
            } else {
                $('#notification .timer').text('Час вийшов!');
                localStorage.removeItem('calledTime');
                localStorage.removeItem('waitTime');
                localStorage.removeItem('calledTicket');
                calledTime = null;
                waitTime = null;
                calledTicket = null;
                $('#notification').removeClass('show').addClass('hide');
            }
        }
    }

    function checkCalled() {
        $.ajax({
            url: 'check_called.php',
            dataType: 'json',
            success: function(data) {
                if (data.is_called) {
                    calledTime = data.called_at;
                    waitTime = data.wait_time;
                    calledTicket = data.ticket_number;
                    localStorage.setItem('calledTime', calledTime);
                    localStorage.setItem('waitTime', waitTime);
                    localStorage.setItem('calledTicket', calledTicket);
                    localStorage.setItem('workstation', data.workstation);
                    
                    const message = `
                        <i class="fas fa-bell mr-2"></i>Вас викликано! 
                        <br>Номер талону: <strong>${data.ticket_number}</strong>
                        <br>Робоче місце: <strong>${data.workstation}</strong>
                        <br><span class="timer"></span>
                    `;
                    $('#notification').html(message).removeClass('hide').addClass('show');
                } else {
                    calledTime = null;
                    waitTime = null;
                    calledTicket = null;
                    localStorage.removeItem('calledTime');
                    localStorage.removeItem('waitTime');
                    localStorage.removeItem('calledTicket');
                    localStorage.removeItem('workstation');
                    $('#notification').removeClass('show').addClass('hide');
                }
                updateTimers();
            },
            error: function(xhr, status, error) {
                console.error('Помилка перевірки виклику:', error);
            }
        });
    }

    $(document).ready(function() {
        const storedTime = localStorage.getItem('calledTime');
        const storedWait = localStorage.getItem('waitTime');
        const storedTicket = localStorage.getItem('calledTicket');
        
        if (storedTime && storedWait && storedTicket) {
            calledTime = parseInt(storedTime);
            waitTime = parseInt(storedWait);
            calledTicket = storedTicket;
            
            // Перевіряємо, чи не минув час очікування 
            const currentTime = Math.floor(Date.now() / 1000);
            const remaining = waitTime - (currentTime - calledTime);
            
            if (remaining > 0) {
                const message = `
                    <i class="fas fa-bell mr-2"></i>Вас викликано! 
                    <br>Номер талону: <strong>${calledTicket}</strong>
                    <br>Робоче місце: <strong>${localStorage.getItem('workstation') || 'Не вказано'}</strong>
                    <br><span class="timer"></span>
                `;
                $('#notification').html(message).removeClass('hide').addClass('show');
            } else {
                // Якщо час вийшов, видаляємо інформацію про виклик
                localStorage.removeItem('calledTime');
                localStorage.removeItem('waitTime');
                localStorage.removeItem('calledTicket');
                localStorage.removeItem('workstation');
                calledTime = null;
                waitTime = null;
                calledTicket = null;
            }
        }
        
        // Запускаємо оновлення таймерів
        setInterval(updateTimers, 1000);
        setInterval(checkCalled, 5000);
    });
    
    setInterval(function() {
        $.get('get_user_queue.php', function(data) {
            $('#user-queue').html(data);
        });
    }, 5000);
    </script>
</body>
</html>