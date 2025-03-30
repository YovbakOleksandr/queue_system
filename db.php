<?php
$host = "127.127.126.26"; // IP з конфігурації
$port = 3306; // Вказаний порт
$user = "root"; // Користувач
$password = ""; // Пароль, якщо є, вкажіть його тут
$database = "queue_system"; // Замініть на назву вашої БД

// Підключення до MySQL
$conn = new mysqli($host, $user, $password, $database, $port);

// Перевірка підключення
if ($conn->connect_error) {
    die("Помилка підключення: " . $conn->connect_error);
}
?>
