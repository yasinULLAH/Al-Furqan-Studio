<?php

// Database Configuration
$db_file = 'prison.sqlite';

// Initialize Database (if not exists)
if (!file_exists($db_file)) {
    $db = new SQLite3($db_file);
    $db->exec('CREATE TABLE inmates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        inmate_id TEXT UNIQUE NOT NULL,
        date_of_birth TEXT,
        admission_date TEXT,
        release_date TEXT,
        crime TEXT,
        cell_number TEXT
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
        FOREIGN KEY (inmate_id) REFERENCES inmates(inmate_id)
    )');
    $db->exec('CREATE TABLE incidents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        incident_date TEXT NOT NULL,
        incident_time TEXT NOT NULL,
        description TEXT NOT NULL,
        involved_inmates TEXT,
        involved_staff TEXT
    )');
    $db->close();
}

// Database Connection
function get_db() {
    return new SQLite3('prison.sqlite');
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $db = get_db();

    switch ($action) {
        case 'add_inmate':
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
            break;

        case 'update_inmate':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $inmate_id = $_POST['inmate_id'];
            $date_of_birth = $_POST['date_of_birth'];
            $admission_date = $_POST['admission_date'];
            $release_date = $_POST['release_date'];
            $crime = $_POST['crime'];
            $cell_number = $_POST['cell_number'];

            // Get old cell number
            $old_cell_stmt = $db->prepare('SELECT cell_number FROM inmates WHERE id = :id');
            $old_cell_stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $old_cell_result = $old_cell_stmt->execute()->fetchArray();
            $old_cell_number = $old_cell_result['cell_number'];

            $stmt = $db->prepare('UPDATE inmates SET name = :name, inmate_id = :inmate_id, date_of_birth = :date_of_birth, admission_date = :admission_date, release_date = :release_date, crime = :crime, cell_number = :cell_number WHERE id = :id');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_TEXT);
            $stmt->bindValue(':date_of_birth', $date_of_birth, SQLITE3_TEXT);
            $stmt->bindValue(':admission_date', $admission_date, SQLITE3_TEXT);
            $stmt->bindValue(':release_date', $release_date, SQLITE3_TEXT);
            $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();

            // Update cell occupancy if cell changed
            if ($old_cell_number !== $cell_number) {
                $update_old_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy - 1 WHERE cell_number = :cell_number');
                $update_old_cell_stmt->bindValue(':cell_number', $old_cell_number, SQLITE3_TEXT);
                $update_old_cell_stmt->execute();

                $update_new_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy + 1 WHERE cell_number = :cell_number');
                $update_new_cell_stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
                $update_new_cell_stmt->execute();
            }
            break;

        case 'delete_inmate':
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
            $update_cell_stmt = $db->prepare('UPDATE cells SET current_occupancy = current_occupancy - 1 WHERE cell_number = :cell_number');
            $update_cell_stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $update_cell_stmt->execute();
            break;

        case 'add_staff':
            $name = $_POST['name'];
            $staff_id = $_POST['staff_id'];
            $role = $_POST['role'];
            $shift = $_POST['shift'];
            $stmt = $db->prepare('INSERT INTO staff (name, staff_id, role, shift) VALUES (:name, :staff_id, :role, :shift)');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':staff_id', $staff_id, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':shift', $shift, SQLITE3_TEXT);
            $stmt->execute();
            break;

        case 'update_staff':
            $id = $_POST['id'];
            $name = $_POST['name'];
            $staff_id = $_POST['staff_id'];
            $role = $_POST['role'];
            $shift = $_POST['shift'];
            $stmt = $db->prepare('UPDATE staff SET name = :name, staff_id = :staff_id, role = :role, shift = :shift WHERE id = :id');
            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':staff_id', $staff_id, SQLITE3_TEXT);
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':shift', $shift, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'delete_staff':
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM staff WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'add_cell':
            $cell_number = $_POST['cell_number'];
            $capacity = $_POST['capacity'];
            $stmt = $db->prepare('INSERT INTO cells (cell_number, capacity) VALUES (:cell_number, :capacity)');
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->bindValue(':capacity', $capacity, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'update_cell':
            $id = $_POST['id'];
            $cell_number = $_POST['cell_number'];
            $capacity = $_POST['capacity'];
            $stmt = $db->prepare('UPDATE cells SET cell_number = :cell_number, capacity = :capacity WHERE id = :id');
            $stmt->bindValue(':cell_number', $cell_number, SQLITE3_TEXT);
            $stmt->bindValue(':capacity', $capacity, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'delete_cell':
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM cells WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'add_visit':
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
            break;

        case 'update_visit':
            $id = $_POST['id'];
            $inmate_id = $_POST['inmate_id'];
            $visitor_name = $_POST['visitor_name'];
            $visit_date = $_POST['visit_date'];
            $visit_time = $_POST['visit_time'];
            $stmt = $db->prepare('UPDATE visits SET inmate_id = :inmate_id, visitor_name = :visitor_name, visit_date = :visit_date, visit_time = :visit_time WHERE id = :id');
            $stmt->bindValue(':inmate_id', $inmate_id, SQLITE3_TEXT);
            $stmt->bindValue(':visitor_name', $visitor_name, SQLITE3_TEXT);
            $stmt->bindValue(':visit_date', $visit_date, SQLITE3_TEXT);
            $stmt->bindValue(':visit_time', $visit_time, SQLITE3_TEXT);
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'delete_visit':
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM visits WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;

        case 'add_incident':
            $incident_date = $_POST['incident_date'];
            $incident_time = $_POST['incident_time'];
            $description = $_POST['description'];
            $involved_inmates = $_POST['involved_inmates'];
            $involved_staff = $_POST['involved_staff'];
            $stmt = $db->prepare('INSERT INTO incidents (incident_date, incident_time, description, involved_inmates, involved_staff) VALUES (:incident_date, :incident_time, :description, :involved_inmates, :involved_staff)');
            $stmt->bindValue(':incident_date', $incident_date, SQLITE3_TEXT);
            $stmt->bindValue(':incident_time', $incident_time, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':involved_inmates', $involved_inmates, SQLITE3_TEXT);
            $stmt->bindValue(':involved_staff', $involved_staff, SQLITE3_TEXT);
            $stmt->execute();
            break;

        case 'update_incident':
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
            break;

        case 'delete_incident':
            $id = $_POST['id'];
            $stmt = $db->prepare('DELETE FROM incidents WHERE id = :id');
            $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
            $stmt->execute();
            break;
    }
    $db->close();
    header('Location: ' . $_SERVER['PHP_SELF']); // Redirect to prevent form resubmission
    exit();
}

// Fetch Data
$db = get_db();

$inmates = $db->query('SELECT * FROM inmates');
$staff = $db->query('SELECT * FROM staff');
$cells = $db->query('SELECT * FROM cells');
$visits = $db->query('SELECT v.*, i.name as inmate_name FROM visits v JOIN inmates i ON v.inmate_id = i.inmate_id');
$incidents = $db->query('SELECT * FROM incidents');

$db->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prison Management System</title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        form { margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 5px; }
        form input, form select, form textarea { margin-bottom: 10px; padding: 8px; width: calc(100% - 18px); }
        form button { padding: 10px 15px; background-color: #5cb85c; color: white; border: none; border-radius: 4px; cursor: pointer; }
        form button:hover { background-color: #4cae4c; }
        .delete-button { background-color: #d9534f; }
        .delete-button:hover { background-color: #c9302c; }
        .edit-button { background-color: #f0ad4e; margin-right: 5px;}
        .edit-button:hover { background-color: #ec971f; }
        .modal { display: none; position: fixed; z-index: 1; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 15% auto; padding: 20px; border: 1px solid #888; width: 80%; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; }
        .close:hover, .close:focus { color: black; text-decoration: none; cursor: pointer; }
    </style>
</head>
<body>

    <h1>Prison Management System</h1>

    <!-- Inmates Section -->
    <h2>Inmates</h2>
    <button onclick="openModal('addInmateModal')">Add New Inmate</button>
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
                <th>Actions</th>
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
                    <td>
                        <button class="edit-button" onclick="openEditModal('editInmateModal', <?php echo json_encode($row); ?>)">Edit</button>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete_inmate">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this inmate?')">Delete</button>
                        </form>
                    </td>
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
                <button type="submit">Update Inmate</button>
            </form>
        </div>
    </div>

    <!-- Staff Section -->
    <h2>Staff</h2>
    <button onclick="openModal('addStaffModal')">Add New Staff Member</button>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Staff ID</th>
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
                <label for="role">Role:</label><br>
                <input type="text" id="role" name="role"><br>
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
                <label for="edit_role">Role:</label><br>
                <input type="text" id="edit_role" name="role"><br>
                <label for="edit_shift">Shift:</label><br>
                <input type="text" id="edit_shift" name="shift"><br>
                <button type="submit">Update Staff Member</button>
            </form>
        </div>
    </div>

    <!-- Cells Section -->
    <h2>Cells</h2>
    <button onclick="openModal('addCellModal')">Add New Cell</button>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Cell Number</th>
                <th>Capacity</th>
                <th>Current Occupancy</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $cells->fetchArray()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['cell_number']; ?></td>
                    <td><?php echo $row['capacity']; ?></td>
                    <td><?php echo $row['current_occupancy']; ?></td>
                    <td>
                         <button class="edit-button" onclick="openEditModal('editCellModal', <?php echo json_encode($row); ?>)">Edit</button>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete_cell">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this cell?')">Delete</button>
                        </form>
                    </td>
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

    <!-- Visits Section -->
    <h2>Visits</h2>
    <button onclick="openModal('addVisitModal')">Schedule New Visit</button>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Inmate Name</th>
                <th>Visitor Name</th>
                <th>Visit Date</th>
                <th>Visit Time</th>
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
                    <td>
                         <button class="edit-button" onclick="openEditModal('editVisitModal', <?php echo json_encode($row); ?>)">Edit</button>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete_visit">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this visit?')">Delete</button>
                        </form>
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
                        $inmate_options = $db_inmates->query('SELECT inmate_id, name FROM inmates');
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
                <button type="submit">Update Visit</button>
            </form>
        </div>
    </div>

    <!-- Incidents Section -->
    <h2>Incidents</h2>
    <button onclick="openModal('addIncidentModal')">Report New Incident</button>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Incident Date</th>
                <th>Incident Time</th>
                <th>Description</th>
                <th>Involved Inmates</th>
                <th>Involved Staff</th>
                <th>Actions</th>
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
                    <td>
                         <button class="edit-button" onclick="openEditModal('editIncidentModal', <?php echo json_encode($row); ?>)">Edit</button>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="action" value="delete_incident">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <button type="submit" class="delete-button" onclick="return confirm('Are you sure you want to delete this incident?')">Delete</button>
                        </form>
                    </td>
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
            } else if (modalId === 'editStaffModal') {
                form.querySelector('#edit_staff_id').value = data.id;
                form.querySelector('#edit_staff_name').value = data.name;
                form.querySelector('#edit_staff_id_field').value = data.staff_id;
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

</body>
</html>