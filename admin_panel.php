<?php
/* Перевірка сесії та прав доступу */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'admin') {
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
}

include_once "db.php";

/* Налаштування перенаправлення */
$redirect_url = 'index.php';
$redirect_tab = '';
$redirect_needed = false;

/* Переклад ролей */
$role_translation = [
    'admin' => 'Адміністратор',
    'employee' => 'Працівник',
    'user' => 'Користувач'
];

/* Обробка POST запитів */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $action = $_POST['action'];

    switch ($action) {
        case 'add_service':
            $name = trim($_POST["name"]);
            $description = trim($_POST["description"]);
            $interval_minutes = intval($_POST["interval_minutes"]);
            $start_time = $_POST["start_time"];
            $end_time = $_POST["end_time"];
            $wait_time = intval($_POST["wait_time"]);
            $max_wait_time = floor($interval_minutes * 60 * 0.5);

            if (mb_strlen($name) > 50) {
                $_SESSION["error"] = "Назва послуги не може перевищувати 50 символів!";
            } elseif ($wait_time < 60) {
                $_SESSION["error"] = "Час очікування не може бути менше 60 секунд!";
            } elseif ($interval_minutes > 0 && $wait_time > $max_wait_time) {
                $_SESSION["error"] = "Час очікування ($wait_time сек) не може перевищувати 50% часу обслуговування ($max_wait_time сек)!";
            } else {
                $stmt = $conn->prepare("INSERT INTO services (name, description, interval_minutes, start_time, end_time, wait_time) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssissi", $name, $description, $interval_minutes, $start_time, $end_time, $wait_time);
                if ($stmt->execute()) $_SESSION["success"] = "Послугу '$name' успішно додано!";
                else $_SESSION["error"] = "Помилка додавання послуги: " . $stmt->error;
                $stmt->close();
            }
            $redirect_tab = 'services';
            break;

        case 'edit_service':
            $id = $_POST["edit_service_id"];
            $name = trim($_POST["edit_name"]);
            $description = trim($_POST["edit_description"]);
            $interval_minutes = intval($_POST["edit_interval_minutes"]);
            $start_time = $_POST["edit_start_time"];
            $end_time = $_POST["edit_end_time"];
            $wait_time = intval($_POST["edit_wait_time"]);
            $max_wait_time = floor($interval_minutes * 60 * 0.5);

            if (mb_strlen($name) > 50) {
                $_SESSION["error"] = "Назва послуги не може перевищувати 50 символів!";
            } elseif ($wait_time < 60) {
                $_SESSION["error"] = "Час очікування не може бути менше 60 секунд!";
            } elseif ($interval_minutes > 0 && $wait_time > $max_wait_time) {
                 $_SESSION["error"] = "Час очікування ($wait_time сек) не може перевищувати 50% часу обслуговування ($max_wait_time сек)!";
            } else {
                $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, interval_minutes = ?, start_time = ?, end_time = ?, wait_time = ? WHERE id = ?");
                $stmt->bind_param("ssissii", $name, $description, $interval_minutes, $start_time, $end_time, $wait_time, $id);
                if ($stmt->execute()) $_SESSION["success"] = "Послугу '$name' успішно оновлено!";
                else $_SESSION["error"] = "Помилка оновлення послуги: " . $stmt->error;
                $stmt->close();
            }
            $redirect_tab = 'services';
            break;

        case 'add_user':
            $full_name = trim($_POST["full_name"]);
            $email = trim($_POST["email"]);
            $password = $_POST["password"];
            $role = trim($_POST["role"]);
            $workstation = ($role === 'employee' && isset($_POST["workstation"])) ? trim($_POST["workstation"]) : null;

            if (empty($password)) $_SESSION["error"] = "Пароль є обов'язковим.";
            else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $_SESSION["error"] = "Некоректний email.";
            else {
                $password_errors = [];
                if (strlen($password) < 8) $password_errors[] = "Пароль > 8 символів.";
                if (!preg_match('/[A-Z]/', $password)) $password_errors[] = "Велика літера.";
                if (!preg_match('/[a-z]/', $password)) $password_errors[] = "Мала літера.";
                if (!preg_match('/[0-9]/', $password)) $password_errors[] = "Цифра.";

                if (!empty($password_errors)) {
                    $_SESSION["error"] = "Пароль: " . implode(", ", $password_errors);
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, workstation) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $full_name, $email, $hashed_password, $role, $workstation);
                    if ($stmt->execute()) {
                        $_SESSION["success"] = "Користувача '$full_name' ({$role_translation[$role]}) успішно додано!";
                    } else {
                        $_SESSION["error"] = ($conn->errno == 1062 || $stmt->errno == 1062) ? "Email '$email' вже існує." : "Помилка додавання: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
            $redirect_tab = 'users';
            break;

        case 'edit_user':
            $id = $_POST["edit_user_id"];
            $full_name = trim($_POST["edit_full_name"]);
            $email = trim($_POST["edit_email"]);
            $role = trim($_POST["edit_role"]);
            $workstation = ($role === 'employee' && isset($_POST["edit_workstation"])) ? trim($_POST["edit_workstation"]) : null;
            $new_password = trim($_POST["edit_new_password"]);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $_SESSION["error"] = "Некоректний email.";
            else if ($id == $_SESSION["user_id"] && $role != 'admin') $_SESSION["error"] = "Ви не можете змінити власну роль.";
            else {
                $sql = ""; $types = ""; $params = []; $can_proceed = true;

                if (!empty($new_password)) {
                     $password_errors = [];
                     if (strlen($new_password) < 8) $password_errors[] = "Мін 8 симв.";
                     if (!empty($password_errors)) {
                        $_SESSION["error"] = "Новий пароль: " . implode(", ", $password_errors);
                        $can_proceed = false;
                     } else {
                        $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, workstation = ?, password = ? WHERE id = ?";
                        $types = "sssssi"; $params = [$full_name, $email, $role, $workstation, $hashed_new_password, $id];
                        $success_message = "Дані та пароль '$full_name' оновлено!";
                     }
                } else {
                    $sql = "UPDATE users SET full_name = ?, email = ?, role = ?, workstation = ? WHERE id = ?";
                    $types = "ssssi"; $params = [$full_name, $email, $role, $workstation, $id];
                    $success_message = "Дані '$full_name' оновлено!";
                }

                if ($can_proceed) {
                    $stmt = $conn->prepare($sql);
                    if (!$stmt) $_SESSION["error"] = "Помилка підготовки: " . $conn->error;
                    else {
                        $stmt->bind_param($types, ...$params);
                        if ($stmt->execute()) {
                            $_SESSION["success"] = $success_message;
                            if ($id == $_SESSION["user_id"]) $_SESSION["full_name"] = $full_name;
                        } else {
                            $_SESSION["error"] = ($conn->errno == 1062 || $stmt->errno == 1062) ? "Email '$email' вже існує." : "Помилка оновлення: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                }
            }
            $redirect_tab = 'users';
            break;

        case 'assign_services':
            $employee_id = $_POST["employee_id"];
            $service_ids = $_POST["service_ids"] ?? [];
            $conn->begin_transaction();
            try {
                $stmt_delete = $conn->prepare("DELETE FROM employee_services WHERE employee_id = ?");
                $stmt_delete->bind_param("i", $employee_id); $stmt_delete->execute(); $stmt_delete->close();
                if (!empty($service_ids)) {
                    $stmt_insert = $conn->prepare("INSERT INTO employee_services (employee_id, service_id, is_active) VALUES (?, ?, 1)");
                    foreach ($service_ids as $service_id) {
                        $stmt_insert->bind_param("ii", $employee_id, $service_id);
                        if(!$stmt_insert->execute()) throw new Exception($stmt_insert->error);
                    }
                    $stmt_insert->close();
                    $_SESSION["success"] = "Послуги призначено!";
                } else {
                    $_SESSION["success"] = "Призначення для працівника скасовано.";
                }
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION["error"] = "Помилка призначення: " . $e->getMessage();
            }
            $redirect_tab = 'assign';
            break;
    }
    $redirect_needed = true;
}

/* Обробка GET запитів */
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    switch ($action) {
        case 'delete_service':
            if ($id > 0) {
                 $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM employee_services WHERE service_id = ?");
                 $stmt_check->bind_param("i", $id); $stmt_check->execute();
                 $count = ($stmt_check->get_result()->fetch_assoc()['count']) ?? 0; $stmt_check->close();
                 if ($count > 0) $_SESSION["error"] = "Неможливо видалити, послуга призначена працівникам.";
                 else {
                    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    if ($stmt->execute()) $_SESSION["success"] = "Послугу видалено!";
                    else $_SESSION["error"] = "Помилка видалення послуги.";
                    $stmt->close();
                 }
            }
            $redirect_tab = 'services';
            break;

        case 'delete_user':
            if ($id > 0) {
                 if ($id == $_SESSION["user_id"]) $_SESSION["error"] = "Неможливо видалити свій обліковий запис.";
                 else {
                    $stmt_check = $conn->prepare("SELECT role FROM users WHERE id = ?");
                    $stmt_check->bind_param("i", $id); $stmt_check->execute();
                    $user_data = $stmt_check->get_result()->fetch_assoc(); $stmt_check->close();
                    if (!$user_data) $_SESSION["error"] = "Користувача не знайдено.";
                    else {
                        $can_delete = true;
                        if ($user_data['role'] == 'employee') {
                            $stmt_assign = $conn->prepare("SELECT COUNT(*) as count FROM employee_services WHERE employee_id = ?");
                            $stmt_assign->bind_param("i", $id); $stmt_assign->execute();
                            $assign_count = ($stmt_assign->get_result()->fetch_assoc()['count']) ?? 0; $stmt_assign->close();
                            if ($assign_count > 0) {
                                $_SESSION["error"] = "Неможливо видалити працівника з активними призначеннями.";
                                $can_delete = false;
                            }
                        }
                        if ($can_delete) {
                             $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                             $stmt->bind_param("i", $id);
                             if ($stmt->execute()) $_SESSION["success"] = "Користувача видалено!";
                             else $_SESSION["error"] = "Помилка видалення користувача.";
                             $stmt->close();
                        }
                    }
                 }
            }
            $redirect_tab = 'users';
            break;

        case 'delete_assignment':
             if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM employee_services WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) $_SESSION["success"] = "Призначення видалено!";
                else $_SESSION["error"] = "Помилка видалення призначення.";
                $stmt->close();
             }
            $redirect_tab = 'assign';
            break;
    }
    $redirect_needed = true;
}

/* Отримання даних для відображення */
$services = $conn->query("SELECT * FROM services ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$users = $conn->query("SELECT * FROM users ORDER BY full_name ASC, email ASC, role ASC")->fetch_all(MYSQLI_ASSOC);
$employees = $conn->query("SELECT id, full_name, workstation FROM users WHERE role = 'employee' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);

$employee_services = [];
$sql_assignments = "SELECT es.id, es.employee_id, es.service_id, u.full_name as employee_name, u.workstation, s.name as service_name
                    FROM employee_services es
                    JOIN users u ON es.employee_id = u.id
                    JOIN services s ON es.service_id = s.id
                    ORDER BY u.full_name, s.name";
$result_assignments = $conn->query($sql_assignments);
if ($result_assignments) {
    while ($row = $result_assignments->fetch_assoc()) {
        $emp_id = $row['employee_id'];
        if (!isset($employee_services[$emp_id])) {
             $employee_services[$emp_id] = [ 'name' => $row['employee_name'], 'workstation' => $row['workstation'], 'assignments' => [] ];
        }
        $employee_services[$emp_id]['assignments'][] = [ 'assignment_id' => $row['id'], 'service_id' => $row['service_id'], 'service_name' => $row['service_name'] ];
    }
} else {
     $_SESSION["error"] = ($_SESSION["error"] ?? "") . "<br>Помилка запиту призначень: " . $conn->error;
}

/* Виконання перенаправлення */
if ($redirect_needed) {
    $redirect_url .= "?tab=" . $redirect_tab . "&t=" . time();
    echo "<script>window.location.replace('{$redirect_url}');</script>";
    exit();
}
?>

<div class="container-fluid mt-4">

    <!-- Повідомлення -->
    <?php if (isset($_SESSION["success"])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_SESSION["success"]) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <?php unset($_SESSION["success"]); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION["error"])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
             <i class="fas fa-exclamation-triangle mr-2"></i><?= $_SESSION["error"] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <?php unset($_SESSION["error"]); ?>
    <?php endif; ?>
     <?php if (isset($_SESSION["warning"])): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
             <i class="fas fa-exclamation-circle mr-2"></i><?= $_SESSION["warning"] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
        <?php unset($_SESSION["warning"]); ?>
    <?php endif; ?>

    <!-- Навігація -->
    <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="services-tab-link" data-toggle="tab" href="#services-tab" role="tab">
                <i class="fas fa-briefcase mr-1"></i> Послуги
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="users-tab-link" data-toggle="tab" href="#users-tab" role="tab">
                <i class="fas fa-users mr-1"></i> Користувачі
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="assign-tab-link" data-toggle="tab" href="#assign-tab" role="tab">
                <i class="fas fa-user-tag mr-1"></i> Призначення
            </a>
        </li>
    </ul>

    <!-- Вміст вкладок -->
    <div class="tab-content" id="adminTabContent">

        <!-- Вкладка Послуги -->
        <div class="tab-pane fade" id="services-tab" role="tabpanel">
            <div class="row">
                <!-- Форма Додавання Послуги -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-plus-circle mr-2 text-primary"></i>Додати послугу</h5>
                        </div>
                        <div class="card-body">
                            <form action="index.php" method="post">
                                <input type="hidden" name="action" value="add_service">
                                <div class="form-group">
                                    <label for="name">Назва:</label>
                                    <input type="text" name="name" id="name" class="form-control" required maxlength="50">
                                </div>
                                <div class="form-group">
                                    <label for="description">Опис:</label>
                                    <textarea name="description" id="description" class="form-control" rows="2"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-6"><label for="interval_minutes">Тривалість (хв):</label><input type="number" name="interval_minutes" id="interval_minutes" class="form-control" min="5" value="30" required></div>
                                    <div class="form-group col-md-6"><label for="wait_time">Очікування (сек):</label><input type="number" name="wait_time" id="wait_time" class="form-control" min="60" value="300" required></div>
                                </div>
                                <div class="form-row">
                                     <div class="form-group col-md-6"><label for="start_time">Початок:</label><input type="time" name="start_time" id="start_time" class="form-control" value="09:00" required></div>
                                     <div class="form-group col-md-6"><label for="end_time">Кінець:</label><input type="time" name="end_time" id="end_time" class="form-control" value="18:00" required></div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus mr-1"></i>Додати</button>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Список Послуг -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                         <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-list-ul mr-2 text-primary"></i>Список послуг</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>Назва / Опис</th>
                                            <th>Трив.</th>
                                            <th>Графік</th>
                                            <th>Очік.</th>
                                            <th>Дії</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($services)): ?>
                                            <tr><td colspan="5" class="text-center py-4">Немає послуг</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($services as $service): ?>
                                                <tr>
                                                    <td><strong><?= htmlspecialchars($service['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($service['description']) ?></small></td>
                                                    <td><?= $service['interval_minutes'] ?> хв</td>
                                                    <td><?= substr($service['start_time'], 0, 5) ?>-<?= substr($service['end_time'], 0, 5) ?></td>
                                                    <td><?= $service['wait_time'] ?> сек</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editServiceModal"
                                                                data-id="<?= $service['id'] ?>" data-name="<?= htmlspecialchars($service['name']) ?>"
                                                                data-description="<?= htmlspecialchars($service['description']) ?>" data-interval="<?= $service['interval_minutes'] ?>"
                                                                data-start="<?= $service['start_time'] ?>" data-end="<?= $service['end_time'] ?>"
                                                                data-wait="<?= $service['wait_time'] ?>" title="Редагувати"> <i class="fas fa-edit"></i> </button>
                                                        <a href="?action=delete_service&id=<?= $service['id'] ?>&tab=services" class="btn btn-sm btn-danger"
                                                           onclick="return confirm('Видалити послугу \'<?= htmlspecialchars(addslashes($service['name'])) ?>\'?')" title="Видалити"> <i class="fas fa-trash"></i> </a>
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

        <!-- Вкладка Користувачі -->
        <div class="tab-pane fade" id="users-tab" role="tabpanel">
             <div class="row">
                <!-- Форма Додавання Користувача -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-user-plus mr-2 text-primary"></i>Додати користувача</h5>
                        </div>
                        <div class="card-body">
                            <form action="index.php" method="post">
                                <input type="hidden" name="action" value="add_user">
                                <div class="form-group"><label for="add_full_name">ПІБ:</label><input type="text" name="full_name" id="add_full_name" class="form-control" required></div>
                                <div class="form-group"><label for="add_email">Email:</label><input type="email" name="email" id="add_email" class="form-control" required autocomplete="username"></div>
                                <div class="form-group"><label for="add_password">Пароль:</label><input type="password" name="password" id="add_password" class="form-control" required autocomplete="new-password"><small class="form-text text-muted">Мін. 8 симв, літери, цифра.</small></div>
                                <div class="form-group"><label for="add_role">Роль:</label><select name="role" id="add_role" class="form-control" required onchange="toggleWorkstationField(this.value, 'workstation_add')"><option value="user"><?= $role_translation['user'] ?></option><option value="employee"><?= $role_translation['employee'] ?></option><option value="admin"><?= $role_translation['admin'] ?></option></select></div>
                                <div class="form-group" id="workstation_add_group" style="display: none;"><label for="workstation_add">Роб. місце:</label><input type="text" name="workstation" id="workstation_add" class="form-control"><small class="form-text text-muted">Для працівника.</small></div>
                                <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-plus mr-1"></i>Додати</button>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Список Користувачів -->
                <div class="col-lg-8">
                     <div class="card shadow-sm">
                         <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-users-cog mr-2 text-primary"></i>Список користувачів</h5>
                        </div>
                         <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr><th>ПІБ</th><th>Email</th><th>Роль</th><th>Роб. місце</th><th>Дії</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                            <tr><td colspan="5" class="text-center py-4">Користувачів нема</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): ?>
                                                <tr class="<?= ($user['id'] == $_SESSION['user_id']) ? 'table-primary font-weight-bold' : '' ?>">
                                                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td><span class="badge badge-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'employee' ? 'warning' : 'secondary') ?>"><?= $role_translation[$user['role']] ?? ucfirst(htmlspecialchars($user['role'])) ?></span></td>
                                                    <td><?= $user['workstation'] ? htmlspecialchars($user['workstation']) : '-' ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info" data-toggle="modal" data-target="#editUserModal"
                                                                data-id="<?= $user['id'] ?>" data-fullname="<?= htmlspecialchars($user['full_name']) ?>"
                                                                data-email="<?= htmlspecialchars($user['email']) ?>" data-role="<?= $user['role'] ?>"
                                                                data-workstation="<?= htmlspecialchars($user['workstation']) ?>" title="Редагувати"> <i class="fas fa-edit"></i> </button>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <a href="?action=delete_user&id=<?= $user['id'] ?>&tab=users" class="btn btn-sm btn-danger"
                                                               onclick="return confirm('Видалити користувача \'<?= htmlspecialchars(addslashes($user['full_name'])) ?>\'?')" title="Видалити"> <i class="fas fa-trash"></i> </a>
                                                        <?php endif; ?>
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

        <!-- Вкладка Призначення -->
        <div class="tab-pane fade" id="assign-tab" role="tabpanel">
            <div class="row">
                 <!-- Форма Призначення -->
                <div class="col-lg-5 mb-4">
                     <div class="card shadow-sm">
                          <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-user-cog mr-2 text-primary"></i>Призначити послуги</h5>
                        </div>
                         <div class="card-body">
                             <?php if (empty($employees)): ?> <div class="alert alert-warning">Додайте працівників.</div>
                             <?php elseif (empty($services)): ?> <div class="alert alert-warning">Додайте послуги.</div>
                             <?php else: ?>
                                <form action="index.php" method="post">
                                    <input type="hidden" name="action" value="assign_services">
                                    <div class="form-group"><label for="employee_id">Працівник:</label><select name="employee_id" id="employee_id" class="form-control" required onchange="loadEmployeeServices(this.value)"><option value="">-- Оберіть --</option><?php foreach ($employees as $e): ?><option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['full_name']) ?> (<?= $e['workstation'] ? htmlspecialchars($e['workstation']) : 'Місце?' ?>)</option><?php endforeach; ?></select></div>
                                    <div class="form-group"><label>Послуги:</label><div id="service_checkboxes" style="max-height: 250px; overflow-y: auto; border: 1px solid #ced4da; padding: 10px; border-radius: .25rem;"><?php foreach ($services as $s): ?><div class="form-check mb-2"><input type="checkbox" name="service_ids[]" value="<?= $s['id'] ?>" class="form-check-input service-checkbox" id="assign_service_<?= $s['id'] ?>" disabled><label class="form-check-label" for="assign_service_<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></label></div><?php endforeach; ?></div></div>
                                    <button type="submit" id="assign_submit_button" class="btn btn-primary btn-block" disabled><i class="fas fa-save mr-1"></i>Зберегти</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                 <!-- Список Призначень -->
                <div class="col-lg-7">
                     <div class="card shadow-sm">
                         <div class="card-header bg-light">
                             <h5 class="mb-0"><i class="fas fa-list-check mr-2 text-primary"></i>Поточні призначення</h5>
                         </div>
                         <div class="card-body p-0">
                            <?php if (empty($employee_services)): ?>
                                <div class="alert alert-light m-3 text-center">Нема призначень.</div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($employee_services as $emp_id => $data): ?>
                                        <div class="list-group-item">
                                            <h6 class="mb-1"><i class="fas fa-user-tie mr-2"></i><?= htmlspecialchars($data['name'] ?? '?') ?><?php if (!empty($data['workstation'])): ?> <small class="text-muted">(<?= htmlspecialchars($data['workstation']) ?>)</small><?php endif; ?></h6>
                                            <div>
                                                 <?php if (!empty($data['assignments'])): foreach ($data['assignments'] as $a): ?>
                                                    <span class="badge badge-pill badge-light mr-1 mb-1 p-2"><?= htmlspecialchars($a['service_name'] ?? '?') ?> <a href="?action=delete_assignment&id=<?= $a['assignment_id'] ?>&tab=assign" class="text-danger ml-1" title="Видалити" onclick="return confirm('Видалити призначення послуги \'<?= htmlspecialchars(addslashes($a['service_name'] ?? '')) ?>\'?')">&times;</a></span>
                                                 <?php endforeach; else: ?><small class="text-muted">Немає</small><?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- /#adminTabContent -->
</div> <!-- /.container-fluid -->

<!-- Модальне вікно: Редагувати послугу -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-labelledby="editServiceModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="index.php" method="post"> <input type="hidden" name="action" value="edit_service">
                <div class="modal-header"><h5 class="modal-title" id="editServiceModalLabel">Редагувати</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <input type="hidden" name="edit_service_id" id="edit_service_id">
                    <div class="form-group"><label for="edit_name">Назва:</label><input type="text" name="edit_name" id="edit_name" class="form-control" required maxlength="50"></div>
                    <div class="form-group"><label for="edit_description">Опис:</label><textarea name="edit_description" id="edit_description" class="form-control" rows="2"></textarea></div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label for="edit_interval_minutes">Трив. (хв):</label><input type="number" name="edit_interval_minutes" id="edit_interval_minutes" class="form-control" min="5" required></div>
                        <div class="form-group col-md-6"><label for="edit_wait_time">Очік. (сек):</label><input type="number" name="edit_wait_time" id="edit_wait_time" class="form-control" min="60" required><small class="form-text text-muted">Макс. час пропуску (≤ 50% трив.)</small></div>
                    </div>
                    <div class="form-row">
                         <div class="form-group col-md-6"><label for="edit_start_time">Початок:</label><input type="time" name="edit_start_time" id="edit_start_time" class="form-control" required></div>
                         <div class="form-group col-md-6"><label for="edit_end_time">Кінець:</label><input type="time" name="edit_end_time" id="edit_end_time" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Скасувати</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Зберегти</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Модальне вікно: Редагувати користувача -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
             <form action="index.php" method="post"> <input type="hidden" name="action" value="edit_user">
                <div class="modal-header"><h5 class="modal-title" id="editUserModalLabel">Редагувати</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <input type="hidden" name="edit_user_id" id="edit_user_id">
                    <div class="form-group"><label for="edit_full_name">ПІБ:</label><input type="text" name="edit_full_name" id="edit_full_name" class="form-control" required></div>
                    <div class="form-group"><label for="edit_email">Email:</label><input type="email" name="edit_email" id="edit_email" class="form-control" required autocomplete="username"></div>
                    <div class="form-group"><label for="edit_role">Роль:</label><select name="edit_role" id="edit_role" class="form-control" required onchange="toggleWorkstationField(this.value, 'edit_workstation')"><option value="user"><?= $role_translation['user'] ?></option><option value="employee"><?= $role_translation['employee'] ?></option><option value="admin"><?= $role_translation['admin'] ?></option></select><div id="role-warning" class="text-danger form-text mt-1" style="display: none;"></div></div>
                    <div class="form-group" id="edit_workstation_group" style="display: none;"><label for="edit_workstation">Роб. місце:</label><input type="text" name="edit_workstation" id="edit_workstation" class="form-control"><small class="form-text text-muted">Для працівника.</small></div>
                    <div class="form-group"><label for="edit_new_password">Новий пароль:</label><input type="password" name="edit_new_password" id="edit_new_password" class="form-control" placeholder="Не змінювати" autocomplete="new-password"><small class="form-text text-muted">Залиште порожнім, щоб не міняти.</small></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">Скасувати</button><button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i>Зберегти</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Скрипти -->
<script>
    $(document).ready(function() {
        // --- Активація вкладки ---
        const urlParams = new URLSearchParams(window.location.search);
        let activeTab = urlParams.get('tab') || 'services'; // За замовчуванням 'services'
        $('#adminTabs a[href="#' + activeTab + '-tab"]').tab('show');

        // --- Заповнення модалки послуги ---
        $('#editServiceModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget); var modal = $(this);
            modal.find('#edit_service_id').val(button.data('id'));
            modal.find('#edit_name').val(button.data('name'));
            modal.find('#edit_description').val(button.data('description'));
            modal.find('#edit_interval_minutes').val(button.data('interval'));
            modal.find('#edit_start_time').val(button.data('start'));
            modal.find('#edit_end_time').val(button.data('end'));
            modal.find('#edit_wait_time').val(button.data('wait'));
            modal.find('.modal-title').text('Редагувати: ' + button.data('name'));
        });

        // --- Заповнення модалки користувача ---
        $('#editUserModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget); var modal = $(this);
            var userId = button.data('id');
            var currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
            var role = button.data('role');

            modal.find('#edit_user_id').val(userId);
            modal.find('#edit_full_name').val(button.data('fullname'));
            modal.find('#edit_email').val(button.data('email'));
            modal.find('#edit_role').val(role).prop('disabled', false); // Скидання блокування
            modal.find('#edit_workstation').val(button.data('workstation'));
            modal.find('#edit_new_password').val('');
            modal.find('.modal-title').text('Редагувати: ' + button.data('fullname'));
            modal.find('#role-warning').hide(); // Сховати попередження

            toggleWorkstationField(role, 'edit_workstation');

            if (userId === currentUserId) {
                modal.find('#edit_role').prop('disabled', true);
                modal.find('#role-warning').text('Ви не можете змінити власну роль.').show();
            }
        });

        // --- Завантаження призначень при виборі працівника ---
        window.loadEmployeeServices = function(employeeId) {
            var checkboxes = $('.service-checkbox');
            var submitButton = $('#assign_submit_button');
            var allAssignments = <?php echo json_encode($employee_services ?? []); ?>; // PHP -> JS
            checkboxes.prop('checked', false).prop('disabled', !employeeId);
            submitButton.prop('disabled', !employeeId);

            if (employeeId && allAssignments[employeeId]) {
                allAssignments[employeeId].assignments.forEach(function(a) {
                    $('#assign_service_' + a.service_id).prop('checked', true);
                });
            }
        };
        loadEmployeeServices($('#employee_id').val()); // Ініціалізація

    }); // end ready

    // --- Показ/приховування поля роб. місця ---
    function toggleWorkstationField(selectedRole, prefix) {
        const group = document.getElementById(prefix + '_group');
        const input = document.getElementById(prefix);
        if (!group || !input) return;
        group.style.display = (selectedRole === 'employee') ? 'block' : 'none';
        if (selectedRole !== 'employee') input.value = '';
    }

    // --- Автозакриття повідомлень ---
    window.setTimeout(() => {
        $(".alert").fadeTo(500, 0).slideUp(500, function(){ $(this).remove(); });
    }, 7000);
</script>