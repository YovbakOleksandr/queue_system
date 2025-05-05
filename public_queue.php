<?php
session_start();
include "db.php";

/* Основні налаштування */
$current_date = date("Y-m-d");
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'appointment_date';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'desc';

/* Отримання даних черги */
$sql = "SELECT q.ticket_number, s.name as service_name, q.appointment_time, q.status, q.is_called, q.is_confirmed, q.workstation 
        FROM queue q 
        JOIN services s ON q.service_id = s.id 
        WHERE q.appointment_date = '$current_date' 
        AND q.status = 'pending' 
        ORDER BY q.appointment_time";
$result = $conn->query($sql);

$queue = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $queue[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>Електронна черга</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        /* Основні стилі */
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .container {
            margin-top: 20px;
        }

        /* Стилі таблиці */
        .queue-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .queue-table th, .queue-table td {
            padding: 12px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .queue-table th {
            background-color: #007bff;
            color: white;
        }
        .queue-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        /* Стилі статусів */
        .status {
            font-weight: bold;
        }
        .status.pending {
            color: orange;
        }
        .status.called {
            color: blue;
        }
        .status.confirmed {
            color: green;
        }
        .workstation {
            font-weight: bold;
            color: #d9534f;
        }
        .back-button {
            margin-bottom: 20px;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let updateInterval;

            /* Функції оновлення черги */
            function updateQueue() {
                $.ajax({
                    url: "get_public_queue.php",
                    success: function(data) {
                        $("#queue-table tbody").html(data);
                    },
                    error: function(xhr, status, error) {
                        console.error("Помилка оновлення черги:", error);
                    }
                });
            }

            function startUpdates() {
                updateQueue();
                updateInterval = setInterval(updateQueue, 2000);
            }

            function stopUpdates() {
                if (updateInterval) {
                    clearInterval(updateInterval);
                }
            }

            /* Керування оновленнями */
            startUpdates();

            $(window).on('beforeunload', function() {
                stopUpdates();
            });

            $(document).on('visibilitychange', function() {
                if (document.hidden) {
                    stopUpdates();
                } else {
                    startUpdates();
                }
            });
        });
    </script>
</head>
<body>
    <!-- Основний контент -->
    <div class="container">
        <a href="index.php" class="btn btn-primary back-button">
            <i class="fas fa-arrow-left mr-2"></i>Повернутись на головну сторінку
        </a>
        <h1 class="text-center">Електронна черга</h1>
        <h3 class="text-center">Сьогоднішня черга</h3>
        
        <!-- Таблиця черги -->
        <table class="queue-table" id="queue-table">
            <thead>
                <tr>
                    <th>Номер талону</th>
                    <th>Послуга</th>
                    <th>Час</th>
                    <th>Статус</th>
                    <th>Робоче місце</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue as $item): ?>
                    <tr>
                        <td><?= $item['ticket_number'] ?></td>
                        <td><?= $item['service_name'] ?></td>
                        <td><?= $item['appointment_time'] ?></td>
                        <td class="status <?= $item['is_confirmed'] ? 'confirmed' : ($item['is_called'] ? 'called' : 'pending') ?>">
                            <?= $item['is_confirmed'] ? 'Обслуговується' : ($item['is_called'] ? 'Викликано' : 'Очікує') ?>
                        </td>
                        <td class="workstation">
                            <?= $item['is_called'] && $item['workstation'] ? $item['workstation'] : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
