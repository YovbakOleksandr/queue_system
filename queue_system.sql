-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: MySQL-8.0
-- Час створення: Квт 20 2025 р., 14:28
-- Версія сервера: 8.0.35
-- Версія PHP: 8.1.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База даних: `queue_system`
--

-- --------------------------------------------------------

--
-- Структура таблиці `employee_services`
--

CREATE TABLE `employee_services` (
  `id` int NOT NULL,
  `employee_id` int NOT NULL,
  `service_id` int NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `employee_services`
--

INSERT INTO `employee_services` (`id`, `employee_id`, `service_id`, `is_active`) VALUES
(92, 12, 3, 1),
(93, 12, 2, 1),
(94, 12, 7, 1),
(95, 12, 4, 1),
(96, 12, 5, 1),
(97, 12, 6, 1),
(99, 13, 1, 1),
(100, 13, 6, 1);

-- --------------------------------------------------------

--
-- Структура таблиці `queue`
--

CREATE TABLE `queue` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `service_id` int NOT NULL,
  `ticket_number` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `is_called` tinyint(1) DEFAULT '0',
  `called_at` timestamp NULL DEFAULT NULL,
  `is_confirmed` tinyint(1) DEFAULT '0',
  `workstation` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `queue`
--

INSERT INTO `queue` (`id`, `user_id`, `service_id`, `ticket_number`, `appointment_date`, `appointment_time`, `status`, `is_called`, `called_at`, `is_confirmed`, `workstation`) VALUES
(281, 11, 1, 'T652466', '2025-04-20', '14:40:00', 'completed', 1, '2025-04-20 11:32:47', 1, '2'),
(282, 14, 1, 'TADB843', '2025-04-20', '15:00:00', 'cancelled', 0, NULL, 0, NULL);

-- --------------------------------------------------------

--
-- Структура таблиці `services`
--

CREATE TABLE `services` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `duration` int NOT NULL DEFAULT '10',
  `interval_minutes` int DEFAULT '10',
  `start_time` time DEFAULT '08:00:00',
  `end_time` time DEFAULT '18:00:00',
  `wait_time` int DEFAULT '60'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `created_at`, `duration`, `interval_minutes`, `start_time`, `end_time`, `wait_time`) VALUES
(1, 'Медичний огляд', 'Загальний медичний огляд у лікарні', '2025-02-18 17:57:16', 10, 20, '08:00:00', '22:00:00', 70),
(2, 'Загран паспорт', 'Запис/отримання паспорту', '2025-02-25 18:36:37', 10, 10, '05:00:00', '23:00:00', 60),
(3, 'Водійські права', 'Здача іспиту/отримання водійських прав', '2025-02-27 08:08:43', 10, 10, '13:00:00', '23:00:00', 300),
(4, 'Отримання довідки про несудимість', 'Замовлення та отримання довідки про відсутність судимості', '2025-03-22 20:52:20', 10, 10, '06:00:00', '23:50:00', 60),
(5, 'Оформлення субсидії', 'Подання документів для отримання державної субсидії на оплату комунальних послуг.', '2025-04-20 08:56:27', 30, 20, '10:00:00', '18:00:00', 90),
(6, 'Реєстрація місця проживання', 'Процедура реєстрації або зняття з реєстрації місця проживання фізичної особи.', '2025-04-20 08:56:27', 25, 15, '09:30:00', '17:30:00', 75),
(7, 'Консультація юриста', 'Юридична консультація з різних правових питань.', '2025-04-20 08:56:27', 30, 30, '14:00:00', '19:00:00', 120);

-- --------------------------------------------------------

--
-- Структура таблиці `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `security_question` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `security_answer` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('user','employee','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'user',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `workstation` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп даних таблиці `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `security_question`, `security_answer`, `role`, `created_at`, `workstation`) VALUES
(4, 'Супер Адміністратор', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'admin', '2025-02-18 16:17:11', NULL),
(11, 'Йовбак Олександр Віталійович', 'syowbak@gmail.com', '$2y$10$zrL92vng1idNk16yIJZ/ueNhw3KRtrODAguUHEenm3URqe5NDPN0e', 'Ваша перша домашня тварина?', '$2y$10$B9Y/4Y0cQa5EGlri0JjEluKMzwZRFXsclfQnXotK2KiE8x.O23XdO', 'user', '2025-04-20 09:02:26', NULL),
(12, 'Іванов Іван Іванович', 'example1@gmail.com', '$2y$10$sFo/9HTbjNM3HhXqpfsrhOTma9UKAVR.S.IaO1XrIWTPURaoQAer6', 'Ваша перша домашня тварина?', '$2y$10$.M0Mokpm47qBlymbMDsys.PrlQNQsvY9c0Z2ZhTZwTQMpgrqXeL2K', 'employee', '2025-04-20 09:04:12', '1'),
(13, 'Васильов Василь Васильович', 'example2@gmail.com', '$2y$10$oh6hlbmLPi/yygfKuG/rxezfI7gAl8RYvG4p2gF3fC0jbAWddTDdW', 'Назва міста, де ви народилися?', '$2y$10$4Djlit8upqvYFkEeZ/F5q.GERu1Dmck6HXID8gHjJ9EkatdKMX8Bi', 'employee', '2025-04-20 09:07:45', '2'),
(14, 'Шебела Олександра Михайлівна', 'syowbak2@gmail.com', '$2y$10$ggbx/QJk3oC/IqWIq4rSx.1T.84jImueWomwXr8jUGntA3AfHgz.2', 'Назва міста, де ви народилися?', '$2y$10$14CLbI3SGsedh85e6kjJUOFkFX1wscPCDkFlcaG4Ou5KyiSfl.xiC', 'user', '2025-04-20 09:09:07', NULL);

--
-- Індекси збережених таблиць
--

--
-- Індекси таблиці `employee_services`
--
ALTER TABLE `employee_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Індекси таблиці `queue`
--
ALTER TABLE `queue`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Індекси таблиці `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Індекси таблиці `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для збережених таблиць
--

--
-- AUTO_INCREMENT для таблиці `employee_services`
--
ALTER TABLE `employee_services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=101;

--
-- AUTO_INCREMENT для таблиці `queue`
--
ALTER TABLE `queue`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=283;

--
-- AUTO_INCREMENT для таблиці `services`
--
ALTER TABLE `services`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT для таблиці `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Обмеження зовнішнього ключа збережених таблиць
--

--
-- Обмеження зовнішнього ключа таблиці `employee_services`
--
ALTER TABLE `employee_services`
  ADD CONSTRAINT `employee_services_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `employee_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Обмеження зовнішнього ключа таблиці `queue`
--
ALTER TABLE `queue`
  ADD CONSTRAINT `queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `queue_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
