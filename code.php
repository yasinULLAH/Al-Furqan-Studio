<?php

// Database Configuration
$dbFile = 'jail_management.db';

// Initialize SQLite Database (if it doesn't exist)
try {
    $db = new SQLite3($dbFile);
    $db->exec("
        CREATE TABLE IF NOT EXISTS inmates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            date_of_birth DATE,
            gender TEXT,
            address TEXT,
            crime TEXT,
            sentence_start DATE,
            sentence_end DATE,
            cell_block TEXT,
            cell_number INTEGER,
            medical_notes TEXT,
            status TEXT DEFAULT 'Active' -- e.g., Active, Released, Transferred
        );

        CREATE TABLE IF NOT EXISTS staff (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'Guard' -- e.g., Warden, Guard, Medical
        );

        CREATE TABLE IF NOT EXISTS visitors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            inmate_id INTEGER,
            visit_date DATE,
            visit_time TIME,
            relationship TEXT,
            notes TEXT,
            FOREIGN KEY (inmate_id) REFERENCES inmates(id)
        );

        CREATE TABLE IF NOT EXISTS incidents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            description TEXT,
            inmate_id INTEGER,
            staff_id INTEGER,
            resolution TEXT,
            FOREIGN KEY (inmate_id) REFERENCES inmates(id),
            FOREIGN KEY (staff_id) REFERENCES staff(id)
        );
    ");
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

session_start();

// Authentication functions
function authenticate($username, $password) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM staff WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);

    if ($user && password_verify($password, $user['password'])) { // Using password_hash for security
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function logout() {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']); // Redirect to login
    exit();
}

// Function to get all Inmate Details
function getAllInmates() {
    global $db;
    $result = $db->query("SELECT * FROM inmates ORDER BY name ASC");
    $inmates = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $inmates[] = $row;
    }
    return $inmates;
}
// Function to get all Inmate Details by Id
function getInmateById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM inmates WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $inmate = $result->fetchArray(SQLITE3_ASSOC);
    return $inmate;
}
// Get all staff members
function getAllStaff() {
    global $db;
    $result = $db->query("SELECT * FROM staff ORDER BY name ASC");
    $staff = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $staff[] = $row;
    }
    return $staff;
}
// Get a specific staff member by ID
function getStaffById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM staff WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $staff = $result->fetchArray(SQLITE3_ASSOC);
    return $staff;
}
//Get all visitor details
function getAllVisitors() {
    global $db;
    $result = $db->query("SELECT * FROM visitors ORDER BY visit_date DESC");
    $visitors = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $visitors[] = $row;
    }
    return $visitors;
}
//Get a specific visitor by ID
function getVisitorById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM visitors WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $visitor = $result->fetchArray(SQLITE3_ASSOC);
    return $visitor;
}
// Get all incident reports
function getAllIncidents() {
    global $db;
    $result = $db->query("SELECT incidents.*, inmates.name AS inmate_name, staff.name AS staff_name FROM incidents LEFT JOIN inmates ON incidents.inmate_id = inmates.id LEFT JOIN staff ON incidents.staff_id = staff.id ORDER BY date_time DESC");
    $incidents = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $incidents[] = $row;
    }
    return $incidents;
}
//Get a specific Incident by ID
function getIncidentById($id) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM incidents WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $incident = $result->fetchArray(SQLITE3_ASSOC);
    return $incident;
}


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = $_POST['username'];
        $password = $_POST['password'];
        if (authenticate($username, $password)) {
            header("Location: " . $_SERVER['PHP_SELF']); // Redirect to refresh
            exit();
        } else {
            $loginError = "Invalid username or password.";
        }
    } elseif (isset($_POST['logout'])) {
        logout();
    } elseif (isset($_POST['add_inmate'])) {
        // Sanitize input (important!)
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $date_of_birth = filter_var($_POST['date_of_birth'], FILTER_SANITIZE_STRING);
        $gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
        $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
        $crime = filter_var($_POST['crime'], FILTER_SANITIZE_STRING);
        $sentence_start = filter_var($_POST['sentence_start'], FILTER_SANITIZE_STRING);
        $sentence_end = filter_var($_POST['sentence_end'], FILTER_SANITIZE_STRING);
        $cell_block = filter_var($_POST['cell_block'], FILTER_SANITIZE_STRING);
        $cell_number = filter_var($_POST['cell_number'], FILTER_SANITIZE_NUMBER_INT);
        $medical_notes = filter_var($_POST['medical_notes'], FILTER_SANITIZE_STRING);

        $stmt = $db->prepare("
            INSERT INTO inmates (name, date_of_birth, gender, address, crime, sentence_start, sentence_end, cell_block, cell_number, medical_notes)
            VALUES (:name, :date_of_birth, :gender, :address, :crime, :sentence_start, :sentence_end, :cell_block, :cell_number, :medical_notes)
        ");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':date_of_birth', $date_of_birth, SQLITE3_TEXT);
        $stmt->bindValue(':gender', $gender, SQLITE3_TEXT);
        $stmt->bindValue(':address', $address, SQLITE3_TEXT);
        $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
        $stmt->bindValue(':sentence_start', $sentence_start, SQLITE3_TEXT);
        $stmt->bindValue(':sentence_end', $sentence_end, SQLITE3_TEXT);
        $stmt->bindValue(':cell_block', $cell_block, SQLITE3_TEXT);
        $stmt->bindValue(':cell_number', $cell_number, SQLITE3_INTEGER);
        $stmt->bindValue(':medical_notes', $medical_notes, SQLITE3_TEXT);

        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();

    } elseif (isset($_POST['update_inmate'])) {
          // Sanitize input
          $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
          $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
          $date_of_birth = filter_var($_POST['date_of_birth'], FILTER_SANITIZE_STRING);
          $gender = filter_var($_POST['gender'], FILTER_SANITIZE_STRING);
          $address = filter_var($_POST['address'], FILTER_SANITIZE_STRING);
          $crime = filter_var($_POST['crime'], FILTER_SANITIZE_STRING);
          $sentence_start = filter_var($_POST['sentence_start'], FILTER_SANITIZE_STRING);
          $sentence_end = filter_var($_POST['sentence_end'], FILTER_SANITIZE_STRING);
          $cell_block = filter_var($_POST['cell_block'], FILTER_SANITIZE_STRING);
          $cell_number = filter_var($_POST['cell_number'], FILTER_SANITIZE_NUMBER_INT);
          $medical_notes = filter_var($_POST['medical_notes'], FILTER_SANITIZE_STRING);
          $status = filter_var($_POST['status'], FILTER_SANITIZE_STRING);
    
          $stmt = $db->prepare("
              UPDATE inmates SET
                  name = :name,
                  date_of_birth = :date_of_birth,
                  gender = :gender,
                  address = :address,
                  crime = :crime,
                  sentence_start = :sentence_start,
                  sentence_end = :sentence_end,
                  cell_block = :cell_block,
                  cell_number = :cell_number,
                  medical_notes = :medical_notes,
                  status = :status
              WHERE id = :id
          ");
          $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
          $stmt->bindValue(':name', $name, SQLITE3_TEXT);
          $stmt->bindValue(':date_of_birth', $date_of_birth, SQLITE3_TEXT);
          $stmt->bindValue(':gender', $gender, SQLITE3_TEXT);
          $stmt->bindValue(':address', $address, SQLITE3_TEXT);
          $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
          $stmt->bindValue(':sentence_start', $sentence_start, SQLITE3_TEXT);
          $stmt->bindValue(':sentence_end', $sentence_end, SQLITE3_TEXT);
          $stmt->bindValue(':cell_block', $cell_block, SQLITE3_TEXT);
          $stmt->bindValue(':cell_number', $cell_number, SQLITE3_INTEGER);
          $stmt->bindValue(':medical_notes', $medical_notes, SQLITE3_TEXT);
          $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    
          $stmt->execute();
          header("Location: " . $_SERVER['PHP_SELF']); // Refresh
          exit();
    } elseif (isset($_POST['delete_inmate'])) {
          $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
          $stmt = $db->prepare("DELETE FROM inmates WHERE id = :id");
          $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
          $stmt->execute();
          header("Location: " . $_SERVER['PHP_SELF']); // Refresh
          exit();
    } elseif (isset($_POST['add_staff'])) {
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $password = $_POST['password']; // NEVER store plain text passwords!
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT); // Hash the password

        $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);

        $stmt = $db->prepare("
            INSERT INTO staff (name, username, password, role)
            VALUES (:name, :username, :password, :role)
        ");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT); // Store the HASHED password
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    }  elseif (isset($_POST['update_staff'])) {
        // Sanitize input
        $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $role = filter_var($_POST['role'], FILTER_SANITIZE_STRING);
    
        // Check if a new password was provided
        if (!empty($_POST['password'])) {
            $password = $_POST['password'];
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE staff SET
                    name = :name,
                    username = :username,
                    password = :password,
                    role = :role
                WHERE id = :id
            ");
            $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        } else {
            $stmt = $db->prepare("
                UPDATE staff SET
                    name = :name,
                    username = :username,
                    role = :role
                WHERE id = :id
            ");
        }
    
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
    
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['delete_staff'])) {
        $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
        $stmt = $db->prepare("DELETE FROM staff WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['add_visitor'])) {
        // Sanitize input
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $inmate_id = filter_var($_POST['inmate_id'], FILTER_SANITIZE_NUMBER_INT);
        $visit_date = filter_var($_POST['visit_date'], FILTER_SANITIZE_STRING);
        $visit_time = filter_var($_POST['visit_time'], FILTER_SANITIZE_STRING);
        $relationship = filter_var($_POST['relationship'], FILTER_SANITIZE_STRING);
        $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);

        $stmt = $db->prepare("
            INSERT INTO visitors (name, inmate_id, visit_date, visit_time, relationship, notes)
            VALUES (:name, :inmate_id, :visit_date, :visit_time, :relationship, :notes)
        ");
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_INTEGER);
        $stmt->bindValue(':visit_date', $visit_date, SQLITE3_TEXT);
        $stmt->bindValue(':visit_time', $visit_time, SQLITE3_TEXT);
        $stmt->bindValue(':relationship', $relationship, SQLITE3_TEXT);
        $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);

        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['update_visitor'])) {
        // Sanitize input
        $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $inmate_id = filter_var($_POST['inmate_id'], FILTER_SANITIZE_NUMBER_INT);
        $visit_date = filter_var($_POST['visit_date'], FILTER_SANITIZE_STRING);
        $visit_time = filter_var($_POST['visit_time'], FILTER_SANITIZE_STRING);
        $relationship = filter_var($_POST['relationship'], FILTER_SANITIZE_STRING);
        $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);

        $stmt = $db->prepare("
            UPDATE visitors SET
                name = :name,
                inmate_id = :inmate_id,
                visit_date = :visit_date,
                visit_time = :visit_time,
                relationship = :relationship,
                notes = :notes
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':name', $name, SQLITE3_TEXT);
        $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_INTEGER);
        $stmt->bindValue(':visit_date', $visit_date, SQLITE3_TEXT);
        $stmt->bindValue(':visit_time', $visit_time, SQLITE3_TEXT);
        $stmt->bindValue(':relationship', $relationship, SQLITE3_TEXT);
        $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);

        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['delete_visitor'])) {
        $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
        $stmt = $db->prepare("DELETE FROM visitors WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['add_incident'])) {
        // Sanitize input
        $date_time = filter_var($_POST['date_time'], FILTER_SANITIZE_STRING);
        $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
        $inmate_id = filter_var($_POST['inmate_id'], FILTER_SANITIZE_NUMBER_INT);
        $staff_id = filter_var($_POST['staff_id'], FILTER_SANITIZE_NUMBER_INT);
        $resolution = filter_var($_POST['resolution'], FILTER_SANITIZE_STRING);

        $stmt = $db->prepare("
            INSERT INTO incidents (date_time, description, inmate_id, staff_id, resolution)
            VALUES (:date_time, :description, :inmate_id, :staff_id, :resolution)
        ");
        $stmt->bindValue(':date_time', $date_time, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_INTEGER);
        $stmt->bindValue(':staff_id', $staff_id, SQLITE3_INTEGER);
        $stmt->bindValue(':resolution', $resolution, SQLITE3_TEXT);

        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['update_incident'])) {
        // Sanitize input
        $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
        $date_time = filter_var($_POST['date_time'], FILTER_SANITIZE_STRING);
        $description = filter_var($_POST['description'], FILTER_SANITIZE_STRING);
        $inmate_id = filter_var($_POST['inmate_id'], FILTER_SANITIZE_NUMBER_INT);
        $staff_id = filter_var($_POST['staff_id'], FILTER_SANITIZE_NUMBER_INT);
        $resolution = filter_var($_POST['resolution'], FILTER_SANITIZE_STRING);

        $stmt = $db->prepare("
            UPDATE incidents SET
                date_time = :date_time,
                description = :description,
                inmate_id = :inmate_id,
                staff_id = :staff_id,
                resolution = :resolution
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':date_time', $date_time, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_INTEGER);
        $stmt->bindValue(':staff_id', $staff_id, SQLITE3_INTEGER);
        $stmt->bindValue(':resolution', $resolution, SQLITE3_TEXT);

        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    } elseif (isset($_POST['delete_incident'])) {
        $id = filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
        $stmt = $db->prepare("DELETE FROM incidents WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        header("Location: " . $_SERVER['PHP_SELF']); // Refresh
        exit();
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Jail Prison Management System</title>
    <style>
        body { font-family: sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; }
        input[type="text"], input[type="date"], input[type="time"], input[type="number"], select, textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 10px;
            border: 1px solid #ccc;
            box-sizing: border-box; /* Important for consistent width */
        }
        input[type="submit"], button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
        }
        input[type="submit"]:hover, button:hover {
            background-color: #3e8e41;
        }
        .error {
            color: red;
        }
        .success {
            color: green;
        }
        .hidden {
            display: none;
        }

        /* Styles for Inmate Details */
        .inmate-details {
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 20px;
        }

        .inmate-details h2 {
            margin-top: 0;
        }
    </style>
</head>
<body>

<h1>Jail Prison Management System</h1>

<?php if (isLoggedIn()): ?>
    <p>Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($_SESSION['role']); ?>)
        <form method="post" style="display: inline;">
            <input type="submit" name="logout" value="Logout">
        </form>
    </p>

    <h2>Dashboard</h2>

    <?php if ($_SESSION['role'] === 'Warden' || $_SESSION['role'] === 'Guard'): ?>
        <h3>Inmate Management</h3>

        <!-- Add Inmate Form -->
        <h4>Add New Inmate</h4>
        <form method="post">
            <label for="name">Name:</label>
            <input type="text" name="name" id="name" required>

            <label for="date_of_birth">Date of Birth:</label>
            <input type="date" name="date_of_birth" id="date_of_birth">

            <label for="gender">Gender:</label>
            <select name="gender" id="gender">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
            </select>

            <label for="address">Address:</label>
            <input type="text" name="address" id="address">

            <label for="crime">Crime:</label>
            <input type="text" name="crime" id="crime" required>

            <label for="sentence_start">Sentence Start:</label>
            <input type="date" name="sentence_start" id="sentence_start">

            <label for="sentence_end">Sentence End:</label>
            <input type="date" name="sentence_end" id="sentence_end">

            <label for="cell_block">Cell Block:</label>
            <input type="text" name="cell_block" id="cell_block">

            <label for="cell_number">Cell Number:</label>
            <input type="number" name="cell_number" id="cell_number">

            <label for="medical_notes">Medical Notes:</label>
            <textarea name="medical_notes" id="medical_notes"></textarea>

            <input type="submit" name="add_inmate" value="Add Inmate">
        </form>

        <!-- List Inmates -->
        <h4>Current Inmates</h4>
        <?php
        $inmates = getAllInmates();
        if (count($inmates) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Cell Block</th>
                        <th>Cell Number</th>
                        <th>Crime</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inmates as $inmate): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inmate['id']); ?></td>
                            <td><?php echo htmlspecialchars($inmate['name']); ?></td>
                            <td><?php echo htmlspecialchars($inmate['cell_block']); ?></td>
                            <td><?php echo htmlspecialchars($inmate['cell_number']); ?></td>
                            <td><?php echo htmlspecialchars($inmate['crime']); ?></td>
                            <td><?php echo htmlspecialchars($inmate['status']); ?></td>
                            <td>
                                <button onclick="showEditInmateForm(<?php echo htmlspecialchars($inmate['id']); ?>)">Edit</button>
                                <button onclick="showDeleteInmateForm(<?php echo htmlspecialchars($inmate['id']); ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No inmates found.</p>
        <?php endif; ?>
        <!-- Edit Inmate Form (Hidden by default) -->
        <div id="editInmateForm" class="hidden">
            <h4>Edit Inmate</h4>
            <form method="post">
                <input type="hidden" name="id" id="edit_inmate_id">
                <label for="edit_name">Name:</label>
                <input type="text" name="name" id="edit_name" required>

                <label for="edit_date_of_birth">Date of Birth:</label>
                <input type="date" name="date_of_birth" id="edit_date_of_birth">

                <label for="edit_gender">Gender:</label>
                <select name="gender" id="edit_gender">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>

                <label for="edit_address">Address:</label>
                <input type="text" name="address" id="edit_address">

                <label for="edit_crime">Crime:</label>
                <input type="text" name="crime" id="edit_crime" required>

                <label for="edit_sentence_start">Sentence Start:</label>
                <input type="date" name="sentence_start" id="edit_sentence_start">

                <label for="edit_sentence_end">Sentence End:</label>
                <input type="date" name="sentence_end" id="edit_sentence_end">

                <label for="edit_cell_block">Cell Block:</label>
                <input type="text" name="cell_block" id="edit_cell_block">

                <label for="edit_cell_number">Cell Number:</label>
                <input type="number" name="cell_number" id="edit_cell_number">

                <label for="edit_medical_notes">Medical Notes:</label>
                <textarea name="medical_notes" id="edit_medical_notes"></textarea>
                 <label for="edit_status">Status:</label>
                <select name="status" id="edit_status">
                    <option value="Active">Active</option>
                    <option value="Released">Released</option>
                    <option value="Transferred">Transferred</option>
                </select>
                <input type="submit" name="update_inmate" value="Update Inmate">
                <button type="button" onclick="hideEditInmateForm()">Cancel</button>
            </form>
        </div>
        <!-- Delete Inmate Form (Hidden by default) -->
        <div id="deleteInmateForm" class="hidden">
            <h4>Delete Inmate</h4>
            <p>Are you sure you want to delete this inmate?</p>
            <form method="post">
                <input type="hidden" name="id" id="delete_inmate_id">
                <input type="submit" name="delete_inmate" value="Delete Inmate">
                <button type="button" onclick="hideDeleteInmateForm()">Cancel</button>
            </form>
        </div>

        <h3>Staff Management</h3>

        <!-- Add Staff Form -->
        <h4>Add New Staff Member</h4>
        <form method="post">
            <label for="name">Name:</label>
            <input type="text" name="name" id="name" required>

            <label for="username">Username:</label>
            <input type="text" name="username" id="username" required>

            <label for="password">Password:</label>
            <input type="text" name="password" id="password" required>

            <label for="role">Role:</label>
            <select name="role" id="role">
                <option value="Guard">Guard</option>
                <option value="Warden">Warden</option>
                <option value="Medical">Medical</option>
            </select>

            <input type="submit" name="add_staff" value="Add Staff Member">
        </form>

        <!-- List Staff -->
        <h4>Current Staff</h4>
        <?php
        $staff = getAllStaff();
        if (count($staff) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff as $staff_member): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($staff_member['id']); ?></td>
                            <td><?php echo htmlspecialchars($staff_member['name']); ?></td>
                            <td><?php echo htmlspecialchars($staff_member['username']); ?></td>
                            <td><?php echo htmlspecialchars($staff_member['role']); ?></td>
                            <td>
                                <button onclick="showEditStaffForm(<?php echo htmlspecialchars($staff_member['id']); ?>)">Edit</button>
                                <button onclick="showDeleteStaffForm(<?php echo htmlspecialchars($staff_member['id']); ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No staff members found.</p>
        <?php endif; ?>
          <!-- Edit Staff Form (Hidden by default) -->
          <div id="editStaffForm" class="hidden">
            <h4>Edit Staff Member</h4>
            <form method="post">
                <input type="hidden" name="id" id="edit_staff_id">
                <label for="edit_name">Name:</label>
                <input type="text" name="name" id="edit_staff_name" required>
    
                <label for="edit_username">Username:</label>
                <input type="text" name="username" id="edit_staff_username" required>
    
                <label for="edit_password">New Password (leave blank to keep current):</label>
                <input type="password" name="password" id="edit_staff_password">
    
                <label for="edit_role">Role:</label>
                <select name="role" id="edit_staff_role">
                    <option value="Guard">Guard</option>
                    <option value="Warden">Warden</option>
                    <option value="Medical">Medical</option>
                </select>
    
                <input type="submit" name="update_staff" value="Update Staff Member">
                <button type="button" onclick="hideEditStaff