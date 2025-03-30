<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] != 'admin') {
    echo "<script>window.location.href = 'login.php';</script>";
    exit();
}

include "db.php";

// Перевірка, чи є поточний адміністратор суперадміном
$is_superadmin = false;
$admin_id = $_SESSION["user_id"];
$stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    if ($row['email'] === 'admin@example.com') {
        $is_superadmin = true;
    }
}
$stmt->close();

// Змінна для відстеження необхідності перенаправлення
$redirect_url = '';
$redirect_needed = false;

// Обробка дій перед виводом HTML із збереженням вкладки
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_service"])) {
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $interval_minutes = intval($_POST["interval_minutes"]);
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $wait_time = intval($_POST["wait_time"]);

    $max_wait_time = floor($interval_minutes * 60 * 0.5);
    if ($wait_time < 60) {
        $_SESSION["error"] = "Час очікування не може бути менше 60 секунд!";
    } elseif ($wait_time > $max_wait_time) {
        $_SESSION["error"] = "Час очікування не може перевищувати 50% часу обслуговування!";
    } else {
        $stmt = $conn->prepare("INSERT INTO services (name, description, interval_minutes, start_time, end_time, wait_time) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissi", $name, $description, $interval_minutes, $start_time, $end_time, $wait_time);

        if ($stmt->execute()) {
            $_SESSION["success"] = "Послугу успішно додано!";
        } else {
            $_SESSION["error"] = "Помилка додавання послуги!";
        }
        $stmt->close();
    }
    $redirect_url = "index.php?tab=services";
    $redirect_needed = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_service"])) {
    $id = $_POST["id"];
    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $interval_minutes = intval($_POST["interval_minutes"]);
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $wait_time = intval($_POST["wait_time"]);

    $max_wait_time = floor($interval_minutes * 60 * 0.5);
    if ($wait_time < 60) {
        $_SESSION["error"] = "Час очікування не може бути менше 60 секунд!";
    } elseif ($wait_time > $max_wait_time) {
        $_SESSION["error"] = "Час очікування не може перевищувати 50% часу обслуговування!";
    } else {
        $stmt = $conn->prepare("UPDATE services SET name = ?, description = ?, interval_minutes = ?, start_time = ?, end_time = ?, wait_time = ? WHERE id = ?");
        $stmt->bind_param("ssissii", $name, $description, $interval_minutes, $start_time, $end_time, $wait_time, $id);

        if ($stmt->execute()) {
            $_SESSION["success"] = "Послугу успішно оновлено!";
        } else {
            $_SESSION["error"] = "Помилка оновлення послуги!";
        }
        $stmt->close();
    }
    $redirect_url = "index.php?tab=services";
    $redirect_needed = true;
}

if (isset($_GET["delete_service"])) {
    $service_id = $_GET["delete_service"];
    $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
    $stmt->bind_param("i", $service_id);

    if ($stmt->execute()) {
        $_SESSION["success"] = "Послугу успішно видалено!";
    } else {
        $_SESSION["error"] = "Помилка видалення послуги!";
    }
    $stmt->close();
    $redirect_url = "index.php?tab=services";
    $redirect_needed = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_user"])) {
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $role = trim($_POST["role"]);
    $workstation = isset($_POST["workstation"]) ? trim($_POST["workstation"]) : "";

    $stmt = $conn->prepare("INSERT INTO users (full_name, email, password, role, workstation) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $full_name, $email, $password, $role, $workstation);

    if ($stmt->execute()) {
        $_SESSION["success"] = "Користувача успішно додано!";
    } else {
        $_SESSION["error"] = "Помилка додавання користувача!";
    }
    $stmt->close();
    $redirect_url = "index.php?tab=users";
    $redirect_needed = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["edit_user"])) {
    $id = $_POST["id"];
    $full_name = trim($_POST["full_name"]);
    $email = trim($_POST["email"]);
    $role = trim($_POST["role"]);
    $workstation = isset($_POST["workstation"]) ? trim($_POST["workstation"]) : "";

    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, role = ?, workstation = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $full_name, $email, $role, $workstation, $id);

    if ($stmt->execute()) {
        $_SESSION["success"] = "Користувача успішно оновлено!";
    } else {
        $_SESSION["error"] = "Помилка оновлення користувача!";
    }
    $stmt->close();
    $redirect_url = "index.php?tab=users";
    $redirect_needed = true;
}

if (isset($_GET["delete_user"])) {
    $user_id = $_GET["delete_user"];
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $_SESSION["success"] = "Користувача успішно видалено!";
    } else {
        $_SESSION["error"] = "Помилка видалення користувача!";
    }
    $stmt->close();
    $redirect_url = "index.php?tab=users";
    $redirect_needed = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["assign_service"])) {
    $employee_id = $_POST["employee_id"];
    $service_id = $_POST["service_id"];

    // Перевірка, чи вже призначена ця послуга працівнику
    $stmt = $conn->prepare("SELECT id FROM employee_services WHERE employee_id = ? AND service_id = ?");
    $stmt->bind_param("ii", $employee_id, $service_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $_SESSION["error"] = "Ця послуга вже призначена цьому працівнику!";
    } else {
        $stmt = $conn->prepare("INSERT INTO employee_services (employee_id, service_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $employee_id, $service_id);
        
        if ($stmt->execute()) {
            $_SESSION["success"] = "Послугу успішно призначено працівнику!";
        } else {
            $_SESSION["error"] = "Помилка призначення послуги!";
        }
    }
    $stmt->close();
    $redirect_url = "index.php?tab=assign";
    $redirect_needed = true;
}

if (isset($_GET["delete_assignment"])) {
    $assignment_id = $_GET["delete_assignment"];
    $stmt = $conn->prepare("DELETE FROM employee_services WHERE id = ?");
    $stmt->bind_param("i", $assignment_id);
    
    if ($stmt->execute()) {
        $_SESSION["success"] = "Призначення успішно видалено!";
    } else {
        $_SESSION["error"] = "Помилка видалення призначення!";
    }
    $stmt->close();
    $redirect_url = "index.php?tab=assign";
    $redirect_needed = true;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["assign_services"])) {
    $employee_id = $_POST["employee_id"];
    $service_ids = $_POST["service_ids"] ?? [];

    $stmt = $conn->prepare("DELETE FROM employee_services WHERE employee_id = ?");
    $stmt->bind_param("i", $employee_id);
    $stmt->execute();
    $stmt->close();

    foreach ($service_ids as $service_id) {
        $stmt = $conn->prepare("INSERT INTO employee_services (employee_id, service_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $employee_id, $service_id);
        $stmt->execute();
        $stmt->close();
    }

    $_SESSION["success"] = "Послуги успішно призначено!";
    $redirect_url = "index.php?tab=assign";
    $redirect_needed = true;
}

// Отримання списку послуг
$services = [];
$sql = "SELECT * FROM services";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
}

// Отримання списку користувачів
$users = [];
$sql = "SELECT * FROM users";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

// Отримання списку працівників
$employees = [];
$sql = "SELECT * FROM users WHERE role = 'employee'";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }
}

// Отримання призначених послуг
$employee_services = [];
$sql = "SELECT es.*, u.full_name, s.name as service_name FROM employee_services es 
        JOIN users u ON es.employee_id = u.id 
        JOIN services s ON es.service_id = s.id";
$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $employee_services[] = $row;
    }
}

// Додавання JavaScript для перенаправлення, якщо потрібно
if ($redirect_needed) {
    echo "<script>window.location.href = '{$redirect_url}';</script>";
    exit();
}
?>

<div class="container mt-4">
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link" id="services-tab" data-toggle="tab" href="#services" role="tab">Послуги</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="users-tab" data-toggle="tab" href="#users" role="tab">Користувачі</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="assign-tab" data-toggle="tab" href="#assign" role="tab">Призначення послуг</a>
        </li>
    </ul>
    <div class="tab-content" id="adminTabContent">
        <div class="tab-pane fade" id="services" role="tabpanel">
            <h3>Додати нову послугу</h3>
            <form action="" method="post">
                <div class="form-group">
                    <label for="name">Назва послуги:</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="description">Опис:</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="interval_minutes">Тривалість (хв):</label>
                        <input type="number" name="interval_minutes" class="form-control" min="5" value="30" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="start_time">Час початку:</label>
                        <input type="time" name="start_time" class="form-control" value="09:00" required>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="end_time">Час закінчення:</label>
                        <input type="time" name="end_time" class="form-control" value="18:00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="wait_time">Час очікування (сек):</label>
                    <input type="number" name="wait_time" class="form-control" min="60" value="300" required>
                    <small class="form-text text-muted">Максимальний час очікування клієнта після виклику (не більше 50% від тривалості послуги)</small>
                </div>
                <button type="submit" name="add_service" class="btn btn-primary">Додати послугу</button>
            </form>
            <hr>
            <h3>Список послуг</h3>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Назва послуги</th>
                            <th>Опис</th>
                            <th>Тривалість (хв)</th>
                            <th>Початок</th>
                            <th>Кінець</th>
                            <th>Очікування (сек)</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($services)): ?>
                            <tr><td colspan="7" class="text-center">Послуг не знайдено.</td></tr>
                        <?php else: ?>
                            <?php foreach ($services as $service): ?>
                                <tr>
                                    <td><?php echo $service['name']; ?></td>
                                    <td><?php echo $service['description']; ?></td>
                                    <td><?php echo $service['interval_minutes']; ?></td>
                                    <td><?php echo $service['start_time']; ?></td>
                                    <td><?php echo $service['end_time']; ?></td>
                                    <td><?php echo $service['wait_time']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-service-btn" data-id="<?php echo $service['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete_service=<?php echo $service['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ви впевнені, що хочете видалити цю послугу?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <tr id="edit-service-form-<?php echo $service['id']; ?>" class="edit-form" style="display:none;">
                                    <td colspan="7">
                                        <form action="" method="post">
                                            <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="name">Назва послуги:</label>
                                                    <input type="text" name="name" class="form-control" value="<?php echo $service['name']; ?>" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="description">Опис:</label>
                                                    <textarea name="description" class="form-control" rows="2"><?php echo $service['description']; ?></textarea>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-3">
                                                    <label for="interval_minutes">Тривалість (хв):</label>
                                                    <input type="number" name="interval_minutes" class="form-control" min="5" value="<?php echo $service['interval_minutes']; ?>" required>
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label for="start_time">Час початку:</label>
                                                    <input type="time" name="start_time" class="form-control" value="<?php echo $service['start_time']; ?>" required>
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label for="end_time">Час закінчення:</label>
                                                    <input type="time" name="end_time" class="form-control" value="<?php echo $service['end_time']; ?>" required>
                                                </div>
                                                <div class="form-group col-md-3">
                                                    <label for="wait_time">Час очікування (сек):</label>
                                                    <input type="number" name="wait_time" class="form-control" min="60" value="<?php echo $service['wait_time']; ?>" required>
                                                </div>
                                            </div>
                                            <button type="submit" name="edit_service" class="btn btn-success">Зберегти зміни</button>
                                            <button type="button" class="btn btn-secondary cancel-edit-btn" data-id="<?php echo $service['id']; ?>">Скасувати</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="users" role="tabpanel">
            <h3>Додати нового користувача</h3>
            <form action="" method="post">
                <div class="form-group">
                    <label for="full_name">ПІБ:</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Пароль:</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="role">Роль:</label>
                        <select name="role" class="form-control" required>
                            <option value="user">Користувач</option>
                            <option value="employee">Працівник</option>
                            <?php if ($is_superadmin): ?>
                                <option value="admin">Адміністратор</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="workstation">Робоче місце (для працівника):</label>
                        <input type="text" name="workstation" class="form-control">
                    </div>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">Додати користувача</button>
            </form>
            <hr>
            <h3>Список користувачів</h3>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ПІБ</th>
                            <th>Email</th>
                            <th>Роль</th>
                            <th>Робоче місце</th>
                            <th>Дії</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="6" class="text-center">Користувачів не знайдено.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['full_name']; ?></td>
                                    <td><?php echo $user['email']; ?></td>
                                    <td><?php echo $user['role']; ?></td>
                                    <td><?php echo $user['workstation']; ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info edit-user-btn" data-id="<?php echo $user['id']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <a href="?delete_user=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ви впевнені, що хочете видалити цього користувача?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr id="edit-user-form-<?php echo $user['id']; ?>" class="edit-form" style="display:none;">
                                    <td colspan="6">
                                        <form action="" method="post">
                                            <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="full_name">ПІБ:</label>
                                                    <input type="text" name="full_name" class="form-control" value="<?php echo $user['full_name']; ?>" required>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="email">Email:</label>
                                                    <input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-md-6">
                                                    <label for="role">Роль:</label>
                                                    <select name="role" class="form-control" required>
                                                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>Користувач</option>
                                                        <option value="employee" <?php echo $user['role'] == 'employee' ? 'selected' : ''; ?>>Працівник</option>
                                                        <?php if ($is_superadmin || $user['role'] == 'admin'): ?>
                                                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Адміністратор</option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                                <div class="form-group col-md-6">
                                                    <label for="workstation">Робоче місце (для працівника):</label>
                                                    <input type="text" name="workstation" class="form-control" value="<?php echo $user['workstation']; ?>">
                                                </div>
                                            </div>
                                            <button type="submit" name="edit_user" class="btn btn-success">Зберегти зміни</button>
                                            <button type="button" class="btn btn-secondary cancel-edit-btn" data-id="<?php echo $user['id']; ?>">Скасувати</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane fade" id="assign" role="tabpanel">
            <h3>Призначення послуг працівникам</h3>
            <form action="" method="post">
                <div class="form-group">
                    <label for="employee_id">Оберіть працівника:</label>
                    <select name="employee_id" class="form-control" required>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['id']; ?>"><?php echo $employee['full_name']; ?> (<?php echo $employee['workstation']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Оберіть послуги:</label>
                    <?php foreach ($services as $service): ?>
                        <div class="form-check">
                            <input type="checkbox" name="service_ids[]" value="<?php echo $service['id']; ?>" class="form-check-input">
                            <label class="form-check-label"><?php echo $service['name']; ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="assign_services" class="btn btn-primary">Призначити послуги</button>
            </form>
            
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $(document).ready(function() {
        // Активація вкладки
        var activeTab = '<?php echo isset($_GET["tab"]) ? $_GET["tab"] : "services"; ?>';
        $('#adminTabs a[href="#' + activeTab + '"]').tab('show');
        
        // Обробники для редагування послуг
        $('.edit-service-btn').click(function() {
            var id = $(this).data('id');
            $('#edit-service-form-' + id).show();
        });
        
        // Обробники для редагування користувачів
        $('.edit-user-btn').click(function() {
            var id = $(this).data('id');
            $('#edit-user-form-' + id).show();
        });
        
        // Обробники для скасування редагування
        $('.cancel-edit-btn').click(function() {
            var id = $(this).data('id');
            $('#edit-service-form-' + id).hide();
            $('#edit-user-form-' + id).hide();
        });
    });
</script>