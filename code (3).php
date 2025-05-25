<?php

// Database Configuration
$db_file = 'prison.sqlite';

// Initialize Database (if not exists)
if (!file_exists($db_file)) {
    $db = new SQLite3($db_file);
    $db->exec('CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL -- Admin, Prison Guard, Gate Keeper, Jailor
    )');
    $db->exec('CREATE TABLE inmates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        inmate_id TEXT UNIQUE NOT NULL,
        date_of_birth TEXT,
        admission_date TEXT,
        release_date TEXT,
        crime TEXT,
        cell_number TEXT,
        status TEXT DEFAULT "Active" -- Active, Released, Transferred
    )');
    $db->exec('CREATE TABLE staff (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        staff_id TEXT UNIQUE NOT NULL,
        role TEXT,
        shift TEXT
    )');
    $db->exec('CREATE TABLE cells (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        cell_number TEXT UNIQUE NOT NULL,
        capacity INTEGER,
        current_occupancy INTEGER DEFAULT 0
    )');
    $db->exec('CREATE TABLE visits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        inmate_id TEXT NOT NULL,
        visitor_name TEXT NOT NULL,
        visit_date TEXT NOT NULL,
        visit_time TEXT NOT NULL,
        status TEXT DEFAULT "Scheduled" -- Scheduled, Completed, Cancelled
    )');
    $db->exec('CREATE TABLE incidents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        incident_date TEXT NOT NULL,
        incident_time TEXT NOT NULL,
        description TEXT NOT NULL,
        involved_inmates TEXT,
        involved_staff TEXT,
        reported_by TEXT -- Staff ID
    )');
    $db->exec('CREATE TABLE logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        user TEXT,
        action TEXT
    )');

    // Add a default admin user (password: admin)
    $db->exec("INSERT INTO users (username, password, role) VALUES ('admin', '" . password_hash('admin', PASSWORD_DEFAULT) . "', 'Admin')");

    $db->close();
}

// Start session
session_start();

// Database Connection
function get_db() {
    return new SQLite3('prison.sqlite');
}

// Logging function
function log_action($action) {
    if (isset($_SESSION['username'])) {
        $db = get_db();
        $stmt = $db->prepare('INSERT INTO logs (user, action) VALUES (:user, :action)');
        $stmt->bindValue(':user', $_SESSION['username'], SQLITE3_TEXT);
        $stmt->bindValue(':action', $action, SQLITE3_TEXT);
        $stmt->execute();
        $db->close();
    }
}

// Authentication
function authenticate($username, $password) {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = :username');
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if ($result && password_verify($password, $result['password'])) {
        $_SESSION['user_id'] = $result['id'];
        $_SESSION['username'] = $result['username'];
        $_SESSION['role'] = $result['role'];
        log_action("User logged in: " . $username);
        return true;
    }
    return false;
}

// Authorization
function authorize($allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?page=unauthorized');
        exit();
    }
}

// Logout
if (isset($_GET['logout'])) {
    log_action("User logged out: " . $_SESSION['username']);
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = get_db();

    switch ($action) {
        case 'login':
            $username = $_POST['username'];
            $password = $_POST['password'];
            if (authenticate($username, $password)) {
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $login_error = "Invalid username or password.";
            }
            break;

        case 'add_inmate':
            authorize(['Admin', 'Jailor']);
            $name = $_POST['name'];
            $inmate_id = $_POST['inmate_id'];
            $date_of_birth = $_POST['date_of_birth'];
            $admission_date = $_POST['admission_date'];
            $release_date = $_POST['release_date'];
            $crime = $_POST['crime'];
            $cell_number = $_POST['cell_number'];
            $stmt = $db->prepare('INSERT INTO inmates (name, inmate_id, date_of_birth, admission_date, release_date, crime, cell_number) VALUES (:name, :inmate_id, :date_of_birth, :admission_date, :release_date, :crime, :cell_number)');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_TEXT);
            $stmt->bindValue(':date_of_birth', $date_of_birth, SQLITE3_TEXT);
            $stmt->bindValue(':admission_date', $admission_date, SQLITE3_TEXT);
            $stmt->bindValue(':release_date', $release_date, SQLITE3_TEXT);
            $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->execute();

            // Update cell occupancy
            $update_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy + 1 WHERE cell_number = :cell_number');
            $update_cell_stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $update_cell_stmt->execute();
            log_action("Added inmate: " . $inmate_id);
            break;

        case 'update_inmate':
            authorize(['Admin', 'Jailor']);
            $id = $_POST['id'];
            $name = $_POST['name'];
            $inmate_id = $_POST['inmate_id'];
            $date_of_birth = $_POST['date_of_birth'];
            $admission_date = $_POST['admission_date'];
            $release_date = $_POST['release_date'];
            $crime = $_POST['crime'];
            $cell_number = $_POST['cell_number'];
            $status = $_POST['status'];

            // Get old cell number
            $old_cell_stmt = $db->prepare('SELECT cell_number FROM inmates WHERE id = :id');
            $old_cell_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $old_cell_result = $old_cell_stmt->execute()->fetchArray();
            $old_cell_number = $old_cell_result['cell_number'];

            $stmt = $db->prepare('UPDATE inmates SET name = :name, inmate_id = :inmate_id, date_of_birth = :date_of_birth, admission_date = :admission_date, release_date = :release_date, crime = :crime, cell_number = :cell_number, status = :status WHERE id = :id');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_TEXT);
            $stmt->bindValue(':date_of_birth', $date_of_birth, SQLITE3_TEXT);
            $stmt->bindValue(':admission_date', $admission_date, SQLITE3_TEXT);
            $stmt->bindValue(':release_date', $release_date, SQLITE3_TEXT);
            $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();

            // Update cell occupancy if cell changed or status changed to released/transferred
            if ($old_cell_number !== $cell_number) {
                 if ($old_cell_number) { // Only decrease if there was an old cell
                    $update_old_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy - 1 WHERE cell_number = :cell_number');
                    $update_old_cell_stmt->bindValue(':cell_number', $old_cell_number, SQLITE3_TEXT);
                    $update_old_cell_stmt->execute();
                 }
                 if ($cell_number && $status === 'Active') { // Only increase if new cell and status is active
                    $update_new_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy + 1 WHERE cell_number = :cell_number');
                    $update_new_cell_stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
                    $update_new_cell_stmt->execute();
                 }
            } elseif ($status !== 'Active' && $old_cell_number) {
                 $update_old_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy - 1 WHERE cell_number = :cell_number');
                 $update_old_cell_stmt->bindValue(':cell_number', $old_cell_number, SQLITE3_TEXT);
                 $update_old_cell_stmt->execute();
            } elseif ($status === 'Active' && !$old_cell_number && $cell_number) {
                 $update_new_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy + 1 WHERE cell_number = :cell_number');
                 $update_new_cell_stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
                 $update_new_cell_stmt->execute();
            }

            log_action("Updated inmate: " . $inmate_id);
            break;

        case 'delete_inmate':
            authorize(['Admin']);
            $id = $_POST['id'];
            // Get cell number before deleting
            $cell_stmt = $db->prepare('SELECT cell_number FROM inmates WHERE id = :id');
            $cell_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $cell_result = $cell_stmt->execute()->fetchArray();
            $cell_number = $cell_result['cell_number'];

            $stmt = $db->prepare('DELETE FROM inmates WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();

            // Update cell occupancy
            if ($cell_number) {
                $update_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy - 1 WHERE cell_number = :cell_number');
                $update_cell_stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
                $update_cell_stmt->execute();
            }
            log_action("Deleted inmate with ID: " . $id);
            break;

        case 'add_staff':
            authorize(['Admin']);
            $name = $_POST['name'];
            $staff_id = $_POST['staff_id'];
            $role = $_POST['role'];
            $shift = $_POST['shift'];
            $username = $_POST['username'];
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

            $stmt = $db->prepare('INSERT INTO staff (name, staff_id, role, shift) VALUES (:name, :staff_id, :role, :shift)');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':staff_id', $staff_id, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':shift', $shift, SQLITE3_TEXT);
            $stmt->execute();

            $user_stmt = $db->prepare('INSERT INTO users (username, password, role) VALUES (:username, :password, :role)');
            $user_stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $user_stmt->bindValue(':password', $password, SQLITE3_TEXT);
            $user_stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $user_stmt->execute();
            log_action("Added staff member: " . $staff_id);
            break;

        case 'update_staff':
            authorize(['Admin']);
            $id = $_POST['id'];
            $name = $_POST['name'];
            $staff_id = $_POST['staff_id'];
            $role = $_POST['role'];
            $shift = $_POST['shift'];
            $username = $_POST['username'];
            $password = $_POST['password']; // Handle password update separately if needed

            $stmt = $db->prepare('UPDATE staff SET name = :name, staff_id = :staff_id, role = :role, shift = :shift WHERE id = :id');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':staff_id', $staff_id, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':shift', $shift, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();

            $user_stmt = $db->prepare('UPDATE users SET username = :username, role = :role WHERE id = (SELECT id FROM users WHERE username = (SELECT username FROM staff WHERE id = :id))');
            $user_stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $user_stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $user_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $user_stmt->execute();

            if (!empty($password)) {
                 $update_password_stmt = $db->prepare('UPDATE users SET password = :password WHERE id = (SELECT id FROM users WHERE username = :username)');
                 $update_password_stmt->bindValue(':password', password_hash($password, PASSWORD_DEFAULT), SQLITE3_TEXT);
                 $update_password_stmt->bindValue(':username', $username, SQLITE3_TEXT);
                 $update_password_stmt->execute();
            }
            log_action("Updated staff member: " . $staff_id);
            break;

        case 'delete_staff':
            authorize(['Admin']);
            $id = $_POST['id'];
            // Get username before deleting staff
            $username_stmt = $db->prepare('SELECT username FROM users WHERE id = (SELECT id FROM users WHERE username = (SELECT username FROM staff WHERE id = :id))');
            $username_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $username_result = $username_stmt->execute()->fetchArray();
            $username = $username_result['username'];

            $stmt = $db->prepare('DELETE FROM staff WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();

            $user_stmt = $db->prepare('DELETE FROM users WHERE username = :username');
            $user_stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $user_stmt->execute();
            log_action("Deleted staff member with ID: " . $id);
            break;

        case 'add_cell':
            authorize(['Admin']);
            $cell_number = $_POST['cell_number'];
            $capacity = $_POST['capacity'];
            $stmt = $db->prepare('INSERT INTO cells (cell_number, capacity) VALUES (:cell_number, :capacity)');
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->bindValue(':capacity', $capacity, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Added cell: " . $cell_number);
            break;

        case 'update_cell':
            authorize(['Admin']);
            $id = $_POST['id'];
            $cell_number = $_POST['cell_number'];
            $capacity = $_POST['capacity'];
            $stmt = $db->prepare('UPDATE cells SET cell_number = :cell_number, capacity = :capacity WHERE id = :id');
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->bindValue(':capacity', $capacity, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Updated cell with ID: " . $id);
            break;

        case 'delete_cell':
            authorize(['Admin']);
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM cells WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Deleted cell with ID: " . $id);
            break;

        case 'add_visit':
            authorize(['Admin', 'Gate Keeper']);
            $inmate_id = $_POST['inmate_id'];
            $visitor_name = $_POST['visitor_name'];
            $visit_date = $_POST['visit_date'];
            $visit_time = $_POST['visit_time'];
            $stmt = $db->prepare('INSERT INTO visits (inmate_id, visitor_name, visit_date, visit_time) VALUES (:inmate_id, :visitor_name, :visit_date, :visit_time)');
            $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_TEXT);
            $stmt->bindValue(':visitor_name', $visitor_name, SQLITE3_TEXT);
            $stmt->bindValue(':visit_date', $visit_date, SQLITE3_TEXT);
            $stmt->bindValue(':visit_time', $visit_time, SQLITE3_TEXT);
            $stmt->execute();
            log_action("Scheduled visit for inmate: " . $inmate_id);
            break;

        case 'update_visit':
            authorize(['Admin', 'Gate Keeper']);
            $id = $_POST['id'];
            $inmate_id = $_POST['inmate_id'];
            $visitor_name = $_POST['visitor_name'];
            $visit_date = $_POST['visit_date'];
            $visit_time = $_POST['visit_time'];
            $status = $_POST['status'];
            $stmt = $db->prepare('UPDATE visits SET inmate_id = :inmate_id, visitor_name = :visitor_name, visit_date = :visit_date, visit_time = :visit_time, status = :status WHERE id = :id');
            $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_TEXT);
            $stmt->bindValue(':visitor_name', $visitor_name, SQLITE3_TEXT);
            $stmt->bindValue(':visit_date', $visit_date, SQLITE3_TEXT);
            $stmt->bindValue(':visit_time', $visit_time, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Updated visit with ID: " . $id);
            break;

        case 'delete_visit':
            authorize(['Admin', 'Gate Keeper']);
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM visits WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Deleted visit with ID: " . $id);
            break;

        case 'add_incident':
            authorize(['Admin', 'Prison Guard']);
            $incident_date = $_POST['incident_date'];
            $incident_time = $_POST['incident_time'];
            $description = $_POST['description'];
            $involved_inmates = $_POST['involved_inmates'];
            $involved_staff = $_POST['involved_staff'];
            $reported_by = $_SESSION['username']; // Log the reporting staff
            $stmt = $db->prepare('INSERT INTO incidents (incident_date, incident_time, description, involved_inmates, involved_staff, reported_by) VALUES (:incident_date, :incident_time, :description, :involved_inmates, :involved_staff, :reported_by)');
            $stmt->bindValue(':incident_date', $incident_date, SQLITE3_TEXT);
            $stmt->bindValue(':incident_time', $incident_time, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':involved_inmates', $involved_inmates, SQLITE3_TEXT);
            $stmt->bindValue(':involved_staff', $involved_staff, SQLITE3_TEXT);
            $stmt->bindValue(':reported_by', $reported_by, SQLITE3_TEXT);
            $stmt->execute();
            log_action("Reported incident on " . $incident_date . " at " . $incident_time);
            break;

        case 'update_incident':
            authorize(['Admin', 'Prison Guard']);
            $id = $_POST['id'];
            $incident_date = $_POST['incident_date'];
            $incident_time = $_POST['incident_time'];
            $description = $_POST['description'];
            $involved_inmates = $_POST['involved_inmates'];
            $involved_staff = $_POST['involved_staff'];
            $stmt = $db->prepare('UPDATE incidents SET incident_date = :incident_date, incident_time = :incident_time, description = :description, involved_inmates = :involved_inmates, involved_staff = :involved_staff WHERE id = :id');
            $stmt->bindValue(':incident_date', $incident_date, SQLITE3_TEXT);
            $stmt->bindValue(':incident_time', $incident_time, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':involved_inmates', $involved_inmates, SQLITE3_TEXT);
            $stmt->bindValue(':involved_staff', $involved_staff, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Updated incident with ID: " . $id);
            break;

        case 'delete_incident':
            authorize(['Admin']);
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM incidents WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            log_action("Deleted incident with ID: " . $id);
            break;
    }
    $db->close();
    header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . ($_GET['page'] ?? 'dashboard')); // Redirect to current page
    exit();
}

// Determine current page
$page = $_GET['page'] ?? 'login';
if (isset($_SESSION['role']) && $page === 'login') {
    $page = 'dashboard'; // Redirect logged-in users from login page
} elseif (!isset($_SESSION['role']) && $page !== 'login') {
    $page = 'login'; // Redirect unauthenticated users to login page
}

// Fetch Data based on role and page
$db = get_db();
$inmates = [];
$staff = [];
$cells = [];
$visits = [];
$incidents = [];
$logs = [];

if (isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'Admin':
            $inmates = $db->query('SELECT * FROM inmates');
            $staff = $db->query('SELECT s.*, u.username FROM staff s JOIN users u ON s.staff_id = u.username'); // Join to get username
            $cells = $db->query('SELECT * FROM cells');
            $visits = $db->query('SELECT v.*, i.name as inmate_name FROM visits v JOIN inmates i ON v.inmate_id = i.inmate_id');
            $incidents = $db->query('SELECT * FROM incidents');
            $logs = $db->query('SELECT * FROM logs ORDER BY timestamp DESC');
            break;
        case 'Prison Guard':
            $inmates = $db->query('SELECT * FROM inmates WHERE status = "Active"');
            $incidents = $db->query('SELECT * FROM incidents');
            break;
        case 'Gate Keeper':
            $visits = $db->query('SELECT v.*, i.name as inmate_name FROM visits v JOIN inmates i ON v.inmate_id = i.inmate_id');
            break;
        case 'Jailor':
            $inmates = $db->query('SELECT * FROM inmates');
            $cells = $db->query('SELECT * FROM cells');
            break;
    }
}

$db->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prison Management System</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            background: linear-gradient(to right, #4a0e4e, #2a082c);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            flex-grow: 1;
        }
        header {
            background-color: rgba(0, 0, 0, 0.3);
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }
        header h1 {
            margin: 0;
            color: #fff;
            font-size: 1.8em;
        }
        nav a {
            color: #fff;
            text-decoration: none;
            margin-left: 20px;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        nav a:hover {
            color: #ffcc00;
        }
        .user-info {
            color: #ccc;
            font-size: 0.9em;
        }
        h2 {
            color: #ffcc00;
            border-bottom: 2px solid #ffcc00;
            padding-bottom: 5px;
            margin-top: 30px;
            margin-bottom: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
            background-color: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 12px;
            text-align: left;
            color: #eee;
        }
        th {
            background-color: rgba(255, 255, 255, 0.1);
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.03);
        }
        form {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.08);
        }
        form label {
            display: block;
            margin-bottom: 8px;
            color: #ffcc00;
            font-weight: bold;
        }
        form input, form select, form textarea {
            margin-bottom: 15px;
            padding: 10px;
            width: calc(100% - 22px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1em;
        }
        form input::placeholder, form textarea::placeholder {
            color: #ccc;
        }
        form button {
            padding: 10px 20px;
            background-color: #ffcc00;
            color: #333;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        form button:hover {
            background-color: #e6b800;
        }
        .delete-button {
            background-color: #d9534f;
            color: white;
        }
        .delete-button:hover {
            background-color: #c9302c;
        }
        .edit-button {
            background-color: #5bc0de;
            color: white;
            margin-right: 5px;
        }
        .edit-button:hover {
            background-color: #31b0d5;
        }
        .add-button {
             background-color: #5cb85c;
             color: white;
             padding: 10px 20px;
             border: none;
             border-radius: 4px;
             cursor: pointer;
             font-size: 1em;
             margin-bottom: 20px;
             transition: background-color 0.3s ease;
        }
        .add-button:hover {
            background-color: #4cae4c;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background-color: rgba(255, 255, 255, 0.15);
            margin: 5% auto;
            padding: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            position: relative;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 30px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
            cursor: pointer;
        }
        .close:hover, .close:focus {
            color: #fff;
        }
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(to right, #4a0e4e, #2a082c);
        }
        .login-form {
            background-color: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.5);
            text-align: center;
            width: 300px;
        }
        .login-form h2 {
            color: #ffcc00;
            margin-bottom: 20px;
            border-bottom: none;
        }
        .login-form input {
            width: calc(100% - 22px);
            padding: 12px;
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            font-size: 1em;
        }
        .login-form button {
            width: 100%;
            padding: 12px;
            background-color: #ffcc00;
            color: #333;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1.1em;
            transition: background-color 0.3s ease;
        }
        .login-form button:hover {
            background-color: #e6b800;
        }
        .error-message {
            color: #d9534f;
            margin-top: 10px;
        }
        footer {
            text-align: center;
            padding: 15px;
            color: #ccc;
            font-size: 0.9em;
            background-color: rgba(0, 0, 0, 0.3);
            margin-top: auto;
        }
    </style>
</head>
<body>

<?php if ($page === 'login'): ?>
    <div class="login-container">
        <div class="login-form">
            <h2>Prison Management Login</h2>
            <?php if (isset($login_error)): ?>
                <p class="error-message"><?php echo $login_error; ?></p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
<?php elseif ($page === 'unauthorized'): ?>
    <div class="container">
        <h2>Unauthorized Access</h2>
        <p>You do not have permission to view this page.</p>
        <p><a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=dashboard">Go to Dashboard</a></p>
    </div>
<?php else: ?>

    <header>
        <h1>Prison Management System</h1>
        <nav>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=dashboard">Dashboard</a>
            <?php if (in_array($_SESSION['role'], ['Admin', 'Jailor', 'Prison Guard'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=inmates">Inmates</a>
            <?php endif; ?>
            <?php if (in_array($_SESSION['role'], ['Admin'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=staff">Staff</a>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=cells">Cells</a>
            <?php endif; ?>
             <?php if (in_array($_SESSION['role'], ['Admin', 'Gate Keeper'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=visits">Visits</a>
            <?php endif; ?>
             <?php if (in_array($_SESSION['role'], ['Admin', 'Prison Guard'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=incidents">Incidents</a>
            <?php endif; ?>
             <?php if (in_array($_SESSION['role'], ['Admin'])): ?>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>?page=logs">Logs</a>
            <?php endif; ?>
            <a href="<?php echo $_SERVER['PHP_SELF']; ?>?logout=1">Logout</a>
        </nav>
        <div class="user-info">Logged in as: <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</div>
    </header>

    <div class="container">
        <?php if ($page === 'dashboard'): ?>
            <h2>Dashboard</h2>
            <p>Welcome to the Prison Management System, <?php echo $_SESSION['username']; ?>!</p>
            <p>Your role is: <?php echo $_SESSION['role']; ?></p>

            <?php if (in_array($_SESSION['role'], ['Admin', 'Jailor'])): ?>
                <h3>Inmate Summary</h3>
                <?php
                    $db_summary = get_db();
                    $active_inmates = $db_summary->querySingle('SELECT COUNT(*) FROM inmates WHERE status = "Active"');
                    $total_inmates = $db_summary->querySingle('SELECT COUNT(*) FROM inmates');
                    $db_summary->close();
                ?>
                <p>Total Active Inmates: <?php echo $active_inmates; ?></p>
                <p>Total Inmates (All Statuses): <?php echo $total_inmates; ?></p>
            <?php endif; ?>

             <?php if (in_array($_SESSION['role'], ['Admin', 'Jailor'])): ?>
                <h3>Cell Occupancy</h3>
                 <?php
                    $db_cells_summary = get_db();
                    $cell_summary = $db_cells_summary->query('SELECT cell_number, current_occupancy, capacity FROM cells');
                 ?>
                 <table>
                     <thead>
                         <tr>
                             <th>Cell Number</th>
                             <th>Occupancy</th>
                             <th>Capacity</th>
                         </tr>
                     </thead>
                     <tbody>
                         <?php while($cell_row = $cell_summary->fetchArray()): ?>
                             <tr>
                                 <td><?php echo $cell_row['cell_number']; ?></td>
                                 <td><?php echo $cell_row['current_occupancy']; ?></td>
                                 <td><?php echo $cell_row['capacity']; ?></td>
                             </tr>
                         <?php endwhile; ?>
                     </tbody>
                 </table>
                 <?php $db_cells_summary->close(); ?>
            <?php endif; ?>

             <?php if (in_array($_SESSION['role'], ['Admin', 'Gate Keeper'])): ?>
                <h3>Upcoming Visits</h3>
                 <?php
                    $db_visits_summary = get_db();
                    $upcoming_visits = $db_visits_summary->query('SELECT v.*, i.name as inmate_name FROM visits v JOIN inmates i ON v.inmate_id = i.inmate_id WHERE v.visit_date >= DATE("now") AND v.status = "Scheduled" ORDER BY v.visit_date, v.visit_time LIMIT 5');
                 ?>
                 <?php if ($upcoming_visits->fetchArray()): $upcoming_visits->reset(); ?>
                     <table>
                         <thead>
                             <tr>
                                 <th>Inmate Name</th>
                                 <th>Visitor Name</th>
                                 <th>Date</th>
                                 <th>Time</th>
                             </tr>
                         </thead>
                         <tbody>
                             <?php while($visit_row = $upcoming_visits->fetchArray()): ?>
                                 <tr>
                                     <td><?php echo $visit_row['inmate_name']; ?></td>
                                     <td><?php echo $visit_row['visitor_name']; ?></td>
                                     <td><?php echo $visit_row['visit_date']; ?></td>
                                     <td><?php echo $visit_row['visit_time']; ?></td>
                                 </tr>
                             <?php endwhile; ?>
                         </tbody>
                     </table>
                 <?php else: ?>
                     <p>No upcoming scheduled visits.</p>
                 <?php endif; ?>
                 <?php $db_visits_summary->close(); ?>
            <?php endif; ?>

        <?php elseif ($page === 'inmates' && in_array($_SESSION['role'], ['Admin', 'Jailor', 'Prison Guard'])): ?>
            <h2>Inmates</h2>
            <?php if (in_array($_SESSION['role'], ['Admin', 'Jailor'])): ?>
                <button class="add-button" onclick="openModal('addInmateModal')">Add New Inmate</button>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Inmate ID</th>
                        <th>Date of Birth</th>
                        <th>Admission Date</th>
                        <th>Release Date</th>
                        <th>Crime</th>
                        <th>Cell Number</th>
                        <th>Status</th>
                        <?php if (in_array($_SESSION['role'], ['Admin', 'Jailor'])): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $inmates->fetchArray()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['inmate_id']; ?></td>
                            <td><?php echo $row['date_of_birth']; ?></td>
                            <td><?php echo $row['admission_date']; ?></td>
                            <td><?php echo $row['release_date']; ?></td>
                            <td><?php echo $row['crime']; ?></td>
                            <td><?php echo $row['cell_number']; ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <?php if (in_array($_SESSION['role'], ['Admin', 'Jailor'])): ?>
                                <td>
                                    <button class="edit-button" onclick="openEditModal('editInmateModal', <?php echo json_encode($row); ?>)">Edit</button>
                                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="action" value="delete_inmate">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this inmate?')">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add Inmate Modal -->
            <div id="addInmateModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('addInmateModal')">&times;</span>
                    <h2>Add New Inmate</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_inmate">
                        <label for="name">Name:</label><br>
                        <input type="text" id="name" name="name" required><br>
                        <label for="inmate_id">Inmate ID:</label><br>
                        <input type="text" id="inmate_id" name="inmate_id" required><br>
                        <label for="date_of_birth">Date of Birth:</label><br>
                        <input type="date" id="date_of_birth" name="date_of_birth"><br>
                        <label for="admission_date">Admission Date:</label><br>
                        <input type="date" id="admission_date" name="admission_date" required><br>
                        <label for="release_date">Release Date:</label><br>
                        <input type="date" id="release_date" name="release_date"><br>
                        <label for="crime">Crime:</label><br>
                        <input type="text" id="crime" name="crime"><br>
                        <label for="cell_number">Cell Number:</label><br>
                        <select id="cell_number" name="cell_number" required>
                            <?php
                                $db_cells = get_db();
                                $cell_options = $db_cells->query('SELECT cell_number FROM cells WHERE current_occupancy < capacity');
                                while ($cell_row = $cell_options->fetchArray()):
                            ?>
                                <option value="<?php echo $cell_row['cell_number']; ?>"><?php echo $cell_row['cell_number']; ?></option>
                            <?php endwhile; $db_cells->close(); ?>
                        </select><br>
                        <button type="submit">Add Inmate</button>
                    </form>
                </div>
            </div>

            <!-- Edit Inmate Modal -->
            <div id="editInmateModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('editInmateModal')">&times;</span>
                    <h2>Edit Inmate</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_inmate">
                        <input type="hidden" id="edit_inmate_id" name="id">
                        <label for="edit_name">Name:</label><br>
                        <input type="text" id="edit_name" name="name" required><br>
                        <label for="edit_inmate_id_field">Inmate ID:</label><br>
                        <input type="text" id="edit_inmate_id_field" name="inmate_id" required><br>
                        <label for="edit_date_of_birth">Date of Birth:</label><br>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth"><br>
                        <label for="edit_admission_date">Admission Date:</label><br>
                        <input type="date" id="edit_admission_date" name="admission_date" required><br>
                        <label for="edit_release_date">Release Date:</label><br>
                        <input type="date" id="edit_release_date" name="release_date"><br>
                        <label for="edit_crime">Crime:</label><br>
                        <input type="text" id="edit_crime" name="crime"><br>
                        <label for="edit_cell_number">Cell Number:</label><br>
                        <select id="edit_cell_number" name="cell_number" required>
                             <?php
                                $db_cells = get_db();
                                $cell_options = $db_cells->query('SELECT cell_number FROM cells');
                                while ($cell_row = $cell_options->fetchArray()):
                            ?>
                                <option value="<?php echo $cell_row['cell_number']; ?>"><?php echo $cell_row['cell_number']; ?></option>
                            <?php endwhile; $db_cells->close(); ?>
                        </select><br>
                         <label for="edit_status">Status:</label><br>
                         <select id="edit_status" name="status" required>
                             <option value="Active">Active</option>
                             <option value="Released">Released</option>
                             <option value="Transferred">Transferred</option>
                         </select><br>
                        <button type="submit">Update Inmate</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'staff' && in_array($_SESSION['role'], ['Admin'])): ?>
            <h2>Staff</h2>
            <button class="add-button" onclick="openModal('addStaffModal')">Add New Staff Member</button>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Staff ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Shift</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $staff->fetchArray()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['staff_id']; ?></td>
                            <td><?php echo $row['username']; ?></td>
                            <td><?php echo $row['role']; ?></td>
                            <td><?php echo $row['shift']; ?></td>
                            <td>
                                 <button class="edit-button" onclick="openEditModal('editStaffModal', <?php echo json_encode($row); ?>)">Edit</button>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="action" value="delete_staff">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this staff member?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add Staff Modal -->
            <div id="addStaffModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('addStaffModal')">&times;</span>
                    <h2>Add New Staff Member</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_staff">
                        <label for="staff_name">Name:</label><br>
                        <input type="text" id="staff_name" name="name" required><br>
                        <label for="staff_id">Staff ID:</label><br>
                        <input type="text" id="staff_id" name="staff_id" required><br>
                        <label for="staff_username">Username:</label><br>
                        <input type="text" id="staff_username" name="username" required><br>
                        <label for="staff_password">Password:</label><br>
                        <input type="password" id="staff_password" name="password" required><br>
                        <label for="role">Role:</label><br>
                        <select id="role" name="role" required>
                            <option value="Admin">Admin</option>
                            <option value="Prison Guard">Prison Guard</option>
                            <option value="Gate Keeper">Gate Keeper</option>
                            <option value="Jailor">Jailor</option>
                        </select><br>
                        <label for="shift">Shift:</label><br>
                        <input type="text" id="shift" name="shift"><br>
                        <button type="submit">Add Staff Member</button>
                    </form>
                </div>
            </div>

            <!-- Edit Staff Modal -->
            <div id="editStaffModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('editStaffModal')">&times;</span>
                    <h2>Edit Staff Member</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_staff">
                        <input type="hidden" id="edit_staff_id" name="id">
                        <label for="edit_staff_name">Name:</label><br>
                        <input type="text" id="edit_staff_name" name="name" required><br>
                        <label for="edit_staff_id_field">Staff ID:</label><br>
                        <input type="text" id="edit_staff_id_field" name="staff_id" required><br>
                         <label for="edit_staff_username">Username:</label><br>
                        <input type="text" id="edit_staff_username" name="username" required><br>
                        <label for="edit_staff_password">New Password (leave blank to keep current):</label><br>
                        <input type="password" id="edit_staff_password" name="password"><br>
                        <label for="edit_role">Role:</label><br>
                         <select id="edit_role" name="role" required>
                            <option value="Admin">Admin</option>
                            <option value="Prison Guard">Prison Guard</option>
                            <option value="Gate Keeper">Gate Keeper</option>
                            <option value="Jailor">Jailor</option>
                        </select><br>
                        <label for="edit_shift">Shift:</label><br>
                        <input type="text" id="edit_shift" name="shift"><br>
                        <button type="submit">Update Staff Member</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'cells' && in_array($_SESSION['role'], ['Admin', 'Jailor'])): ?>
            <h2>Cells</h2>
             <?php if (in_array($_SESSION['role'], ['Admin'])): ?>
                <button class="add-button" onclick="openModal('addCellModal')">Add New Cell</button>
            <?php endif; ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cell Number</th>
                        <th>Capacity</th>
                        <th>Current Occupancy</th>
                        <?php if (in_array($_SESSION['role'], ['Admin'])): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $cells->fetchArray()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['cell_number']; ?></td>
                            <td><?php echo $row['capacity']; ?></td>
                            <td><?php echo $row['current_occupancy']; ?></td>
                            <?php if (in_array($_SESSION['role'], ['Admin'])): ?>
                                <td>
                                     <button class="edit-button" onclick="openEditModal('editCellModal', <?php echo json_encode($row); ?>)">Edit</button>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="action" value="delete_cell">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this cell?')">Delete</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add Cell Modal -->
            <div id="addCellModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('addCellModal')">&times;</span>
                    <h2>Add New Cell</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_cell">
                        <label for="cell_number">Cell Number:</label><br>
                        <input type="text" id="cell_number" name="cell_number" required><br>
                        <label for="capacity">Capacity:</label><br>
                        <input type="number" id="capacity" name="capacity" required><br>
                        <button type="submit">Add Cell</button>
                    </form>
                </div>
            </div>

            <!-- Edit Cell Modal -->
            <div id="editCellModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('editCellModal')">&times;</span>
                    <h2>Edit Cell</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_cell">
                        <input type="hidden" id="edit_cell_id" name="id">
                        <label for="edit_cell_number_field">Cell Number:</label><br>
                        <input type="text" id="edit_cell_number_field" name="cell_number" required><br>
                        <label for="edit_capacity">Capacity:</label><br>
                        <input type="number" id="edit_capacity" name="capacity" required><br>
                        <button type="submit">Update Cell</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'visits' && in_array($_SESSION['role'], ['Admin', 'Gate Keeper'])): ?>
            <h2>Visits</h2>
            <button class="add-button" onclick="openModal('addVisitModal')">Schedule New Visit</button>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Inmate Name</th>
                        <th>Visitor Name</th>
                        <th>Visit Date</th>
                        <th>Visit Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $visits->fetchArray()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['inmate_name']; ?></td>
                            <td><?php echo $row['visitor_name']; ?></td>
                            <td><?php echo $row['visit_date']; ?></td>
                            <td><?php echo $row['visit_time']; ?></td>
                            <td><?php echo $row['status']; ?></td>
                            <td>
                                 <button class="edit-button" onclick="openEditModal('editVisitModal', <?php echo json_encode($row); ?>)">Edit</button>
                                <?php if ($_SESSION['role'] === 'Admin'): ?>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="action" value="delete_visit">
                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this visit?')">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add Visit Modal -->
            <div id="addVisitModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('addVisitModal')">&times;</span>
                    <h2>Schedule New Visit</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_visit">
                        <label for="visit_inmate_id">Inmate:</label><br>
                        <select id="visit_inmate_id" name="inmate_id" required>
                            <?php
                                $db_inmates = get_db();
                                $inmate_options = $db_inmates->query('SELECT inmate_id, name FROM inmates WHERE status = "Active"');
                                while ($inmate_row = $inmate_options->fetchArray()):
                            ?>
                                <option value="<?php echo $inmate_row['inmate_id']; ?>"><?php echo $inmate_row['name'] . ' (' . $inmate_row['inmate_id'] . ')'; ?></option>
                            <?php endwhile; $db_inmates->close(); ?>
                        </select><br>
                        <label for="visitor_name">Visitor Name:</label><br>
                        <input type="text" id="visitor_name" name="visitor_name" required><br>
                        <label for="visit_date">Visit Date:</label><br>
                        <input type="date" id="visit_date" name="visit_date" required><br>
                        <label for="visit_time">Visit Time:</label><br>
                        <input type="time" id="visit_time" name="visit_time" required><br>
                        <button type="submit">Schedule Visit</button>
                    </form>
                </div>
            </div>

            <!-- Edit Visit Modal -->
            <div id="editVisitModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('editVisitModal')">&times;</span>
                    <h2>Edit Visit</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_visit">
                        <input type="hidden" id="edit_visit_id" name="id">
                         <label for="edit_visit_inmate_id">Inmate:</label><br>
                        <select id="edit_visit_inmate_id" name="inmate_id" required>
                            <?php
                                $db_inmates = get_db();
                                $inmate_options = $db_inmates->query('SELECT inmate_id, name FROM inmates');
                                while ($inmate_row = $inmate_options->fetchArray()):
                            ?>
                                <option value="<?php echo $inmate_row['inmate_id']; ?>"><?php echo $inmate_row['name'] . ' (' . $inmate_row['inmate_id'] . ')'; ?></option>
                            <?php endwhile; $db_inmates->close(); ?>
                        </select><br>
                        <label for="edit_visitor_name">Visitor Name:</label><br>
                        <input type="text" id="edit_visitor_name" name="visitor_name" required><br>
                        <label for="edit_visit_date">Visit Date:</label><br>
                        <input type="date" id="edit_visit_date" name="visit_date" required><br>
                        <label for="edit_visit_time">Visit Time:</label><br>
                        <input type="time" id="edit_visit_time" name="visit_time" required><br>
                         <label for="edit_visit_status">Status:</label><br>
                         <select id="edit_visit_status" name="status" required>
                             <option value="Scheduled">Scheduled</option>
                             <option value="Completed">Completed</option>
                             <option value="Cancelled">Cancelled</option>
                         </select><br>
                        <button type="submit">Update Visit</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'incidents' && in_array($_SESSION['role'], ['Admin', 'Prison Guard'])): ?>
            <h2>Incidents</h2>
            <button class="add-button" onclick="openModal('addIncidentModal')">Report New Incident</button>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Incident Date</th>
                        <th>Incident Time</th>
                        <th>Description</th>
                        <th>Involved Inmates</th>
                        <th>Involved Staff</th>
                        <th>Reported By</th>
                        <?php if (in_array($_SESSION['role'], ['Admin', 'Prison Guard'])): ?>
                            <th>Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $incidents->fetchArray()): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['incident_date']; ?></td>
                            <td><?php echo $row['incident_time']; ?></td>
                            <td><?php echo $row['description']; ?></td>
                            <td><?php echo $row['involved_inmates']; ?></td>
                            <td><?php echo $row['involved_staff']; ?></td>
                            <td><?php echo $row['reported_by']; ?></td>
                            <?php if (in_array($_SESSION['role'], ['Admin', 'Prison Guard'])): ?>
                                <td>
                                     <button class="edit-button" onclick="openEditModal('editIncidentModal', <?php echo json_encode($row); ?>)">Edit</button>
                                    <?php if ($_SESSION['role'] === 'Admin'): ?>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="action" value="delete_incident">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this incident?')">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Add Incident Modal -->
            <div id="addIncidentModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('addIncidentModal')">&times;</span>
                    <h2>Report New Incident</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="add_incident">
                        <label for="incident_date">Incident Date:</label><br>
                        <input type="date" id="incident_date" name="incident_date" required><br>
                        <label for="incident_time">Incident Time:</label><br>
                        <input type="time" id="incident_time" name="incident_time" required><br>
                        <label for="description">Description:</label><br>
                        <textarea id="description" name="description" rows="4" required></textarea><br>
                        <label for="involved_inmates">Involved Inmate IDs (comma-separated):</label><br>
                        <input type="text" id="involved_inmates" name="involved_inmates"><br>
                        <label for="involved_staff">Involved Staff IDs (comma-separated):</label><br>
                        <input type="text" id="involved_staff" name="involved_staff"><br>
                        <button type="submit">Report Incident</button>
                    </form>
                </div>
            </div>

            <!-- Edit Incident Modal -->
            <div id="editIncidentModal" class="modal">
                <div class="modal-content">
                    <span class="close" onclick="closeModal('editIncidentModal')">&times;</span>
                    <h2>Edit Incident</h2>
                    <form method="post">
                        <input type="hidden" name="action" value="update_incident">
                        <input type="hidden" id="edit_incident_id" name="id">
                        <label for="edit_incident_date">Incident Date:</label><br>
                        <input type="date" id="edit_incident_date" name="incident_date" required><br>
                        <label for="edit_incident_time">Incident Time:</label><br>
                        <input type="time" id="edit_incident_time" name="incident_time" required><br>
                        <label for="edit_description">Description:</label><br>
                        <textarea id="edit_description" name="description" rows="4" required></textarea><br>
                        <label for="edit_involved_inmates">Involved Inmate IDs (comma-separated):</label><br>
                        <input type="text" id="edit_involved_inmates" name="involved_inmates"><br>
                        <label for="edit_involved_staff">Involved Staff IDs (comma-separated):</label><br>
                        <input type="text" id="edit_involved_staff" name="involved_staff"><br>
                        <button type="submit">Update Incident</button>
                    </form>
                </div>
            </div>

        <?php elseif ($page === 'logs' && in_array($_SESSION['role'], ['Admin'])): ?>
            <h2>System Logs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $logs->fetchArray()): ?>
                        <tr>
                            <td><?php echo $row['timestamp']; ?></td>
                            <td><?php echo $row['user']; ?></td>
                            <td><?php echo $row['action']; ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>

    </div>

    <footer>
        &copy; <?php echo date('Y'); ?> Prison Management System. All rights reserved.
    </footer>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = "block";
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = "none";
        }

        function openEditModal(modalId, data) {
            const modal = document.getElementById(modalId);
            const form = modal.querySelector('form');

            // Populate form fields based on modal type
            if (modalId === 'editInmateModal') {
                form.querySelector('#edit_inmate_id').value = data.id;
                form.querySelector('#edit_name').value = data.name;
                form.querySelector('#edit_inmate_id_field').value = data.inmate_id;
                form.querySelector('#edit_date_of_birth').value = data.date_of_birth;
                form.querySelector('#edit_admission_date').value = data.admission_date;
                form.querySelector('#edit_release_date').value = data.release_date;
                form.querySelector('#edit_crime').value = data.crime;
                form.querySelector('#edit_cell_number').value = data.cell_number;
                form.querySelector('#edit_status').value = data.status;
            } else if (modalId === 'editStaffModal') {
                form.querySelector('#edit_staff_id').value = data.id;
                form.querySelector('#edit_staff_name').value = data.name;
                form.querySelector('#edit_staff_id_field').value = data.staff_id;
                form.querySelector('#edit_staff_username').value = data.username;
                form.querySelector('#edit_role').value = data.role;
                form.querySelector('#edit_shift').value = data.shift;
            } else if (modalId === 'editCellModal') {
                form.querySelector('#edit_cell_id').value = data.id;
                form.querySelector('#edit_cell_number_field').value = data.cell_number;
                form.querySelector('#edit_capacity').value = data.capacity;
            } else if (modalId === 'editVisitModal') {
                form.querySelector('#edit_visit_id').value = data.id;
                form.querySelector('#edit_visit_inmate_id').value = data.inmate_id;
                form.querySelector('#edit_visitor_name').value = data.visitor_name;
                form.querySelector('#edit_visit_date').value = data.visit_date;
                form.querySelector('#edit_visit_time').value = data.visit_time;
                form.querySelector('#edit_visit_status').value = data.status;
            } else if (modalId === 'editIncidentModal') {
                form.querySelector('#edit_incident_id').value = data.id;
                form.querySelector('#edit_incident_date').value = data.incident_date;
                form.querySelector('#edit_incident_time').value = data.incident_time;
                form.querySelector('#edit_description').value = data.description;
                form.querySelector('#edit_involved_inmates').value = data.involved_inmates;
                form.querySelector('#edit_involved_staff').value = data.involved_staff;
            }

            modal.style.display = "block";
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = "none";
            }
        }
    </script>

<?php endif; ?>

</body>
</html>