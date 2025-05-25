<?php
// Database Configuration
$dbFile = 'jail_management.db';
$db = new SQLite3($dbFile);

// Initialize Database (if not exists)
function initDatabase($db)
{
    $query = "
        CREATE TABLE IF NOT EXISTS inmates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            age INTEGER,
            crime TEXT,
            sentence_start DATE,
            sentence_end DATE,
            cell_id INTEGER,
            notes TEXT
        );

        CREATE TABLE IF NOT EXISTS cells (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            capacity INTEGER NOT NULL,
            occupied INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS guards (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            shift TEXT
        );

        INSERT INTO cells (capacity) SELECT 4 WHERE NOT EXISTS (SELECT 1 FROM cells);
        INSERT INTO cells (capacity) SELECT 4 WHERE NOT EXISTS (SELECT 1 FROM cells WHERE id = 2);
        INSERT INTO cells (capacity) SELECT 4 WHERE NOT EXISTS (SELECT 1 FROM cells WHERE id = 3);
    ";
    $db->exec($query);
}

initDatabase($db);

// --- Helper Functions ---
function displayMessage($message, $type = 'success')
{
    echo "<div class='alert alert-$type'>$message</div>";
}

function sanitizeInput($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// --- Inmate Management ---
function addInmate($db, $name, $age, $crime, $sentence_start, $sentence_end, $cell_id, $notes)
{
    $name = sanitizeInput($name);
    $age = intval($age);
    $crime = sanitizeInput($crime);
    $notes = sanitizeInput($notes);

    $stmt = $db->prepare("INSERT INTO inmates (name, age, crime, sentence_start, sentence_end, cell_id, notes) VALUES (:name, :age, :crime, :sentence_start, :sentence_end, :cell_id, :notes)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':age', $age, SQLITE3_INTEGER);
    $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
    $stmt->bindValue(':sentence_start', $sentence_start, SQLITE3_TEXT);
    $stmt->bindValue(':sentence_end', $sentence_end, SQLITE3_TEXT);
    $stmt->bindValue(':cell_id', $cell_id, SQLITE3_INTEGER);
    $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);

    $result = $stmt->execute();

    if ($result) {
        updateCellOccupancy($db, $cell_id);
        displayMessage("Inmate added successfully!", 'success');
    } else {
        displayMessage("Error adding inmate: " . $db->lastErrorMsg(), 'danger');
    }
}

function getInmates($db)
{
    $result = $db->query("SELECT * FROM inmates");
    $inmates = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $inmates[] = $row;
    }
    return $inmates;
}

function getInmateById($db, $id)
{
    $stmt = $db->prepare("SELECT * FROM inmates WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function updateInmate($db, $id, $name, $age, $crime, $sentence_start, $sentence_end, $cell_id, $notes)
{
    $name = sanitizeInput($name);
    $age = intval($age);
    $crime = sanitizeInput($crime);
    $notes = sanitizeInput($notes);

    $stmt = $db->prepare("UPDATE inmates SET name = :name, age = :age, crime = :crime, sentence_start = :sentence_start, sentence_end = :sentence_end, cell_id = :cell_id, notes = :notes WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':age', $age, SQLITE3_INTEGER);
    $stmt->bindValue(':crime', $crime, SQLITE3_TEXT);
    $stmt->bindValue(':sentence_start', $sentence_start, SQLITE3_TEXT);
    $stmt->bindValue(':sentence_end', $sentence_end, SQLITE3_TEXT);
    $stmt->bindValue(':cell_id', $cell_id, SQLITE3_INTEGER);
    $stmt->bindValue(':notes', $notes, SQLITE3_TEXT);

    $result = $stmt->execute();
    if ($result) {
        displayMessage("Inmate updated successfully!", 'success');
    } else {
        displayMessage("Error updating inmate: " . $db->lastErrorMsg(), 'danger');
    }
}

function deleteInmate($db, $id)
{
    $inmate = getInmateById($db, $id);
    $cell_id = $inmate['cell_id'];
    $stmt = $db->prepare("DELETE FROM inmates WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result) {
        updateCellOccupancy($db, $cell_id);
        displayMessage("Inmate deleted successfully!", 'success');
    } else {
        displayMessage("Error deleting inmate: " . $db->lastErrorMsg(), 'danger');
    }
}


// --- Cell Management ---
function getCells($db)
{
    $result = $db->query("SELECT * FROM cells");
    $cells = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $cells[] = $row;
    }
    return $cells;
}

function getCellById($db, $id)
{
    $stmt = $db->prepare("SELECT * FROM cells WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function addCell($db, $capacity)
{
    $capacity = intval($capacity);
    $stmt = $db->prepare("INSERT INTO cells (capacity) VALUES (:capacity)");
    $stmt->bindValue(':capacity', $capacity, SQLITE3_INTEGER);
    $result = $stmt->execute();

    if ($result) {
        displayMessage("Cell added successfully!", 'success');
    } else {
        displayMessage("Error adding cell: " . $db->lastErrorMsg(), 'danger');
    }
}

function updateCell($db, $id, $capacity)
{
    $capacity = intval($capacity);
    $stmt = $db->prepare("UPDATE cells SET capacity = :capacity WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':capacity', $capacity, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result) {
        displayMessage("Cell updated successfully!", 'success');
    } else {
        displayMessage("Error updating cell: " . $db->lastErrorMsg(), 'danger');
    }
}

function deleteCell($db, $id)
{
    // Check if the cell is occupied.  If so, prevent delete.
    $cell = getCellById($db, $id);
    if ($cell['occupied'] > 0) {
        displayMessage("Cannot delete cell. Cell is occupied.", 'danger');
        return;
    }
    $stmt = $db->prepare("DELETE FROM cells WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result) {
        displayMessage("Cell deleted successfully!", 'success');
    } else {
        displayMessage("Error deleting cell: " . $db->lastErrorMsg(), 'danger');
    }
}

function updateCellOccupancy($db, $cell_id)
{
    $stmt = $db->prepare("SELECT COUNT(*) FROM inmates WHERE cell_id = :cell_id");
    $stmt->bindValue(':cell_id', $cell_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $occupied = $result->fetchArray()[0];

    $stmt = $db->prepare("UPDATE cells SET occupied = :occupied WHERE id = :cell_id");
    $stmt->bindValue(':occupied', $occupied, SQLITE3_INTEGER);
    $stmt->bindValue(':cell_id', $cell_id, SQLITE3_INTEGER);
    $stmt->execute();
}



// --- Guard Management ---
function addGuard($db, $name, $shift)
{
    $name = sanitizeInput($name);
    $shift = sanitizeInput($shift);

    $stmt = $db->prepare("INSERT INTO guards (name, shift) VALUES (:name, :shift)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':shift', $shift, SQLITE3_TEXT);

    $result = $stmt->execute();
    if ($result) {
        displayMessage("Guard added successfully!", 'success');
    } else {
        displayMessage("Error adding guard: " . $db->lastErrorMsg(), 'danger');
    }
}

function getGuards($db)
{
    $result = $db->query("SELECT * FROM guards");
    $guards = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $guards[] = $row;
    }
    return $guards;
}

function getGuardById($db, $id)
{
    $stmt = $db->prepare("SELECT * FROM guards WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function updateGuard($db, $id, $name, $shift)
{
    $name = sanitizeInput($name);
    $shift = sanitizeInput($shift);

    $stmt = $db->prepare("UPDATE guards SET name = :name, shift = :shift WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':shift', $shift, SQLITE3_TEXT);
    $result = $stmt->execute();
    if ($result) {
        displayMessage("Guard updated successfully!", 'success');
    } else {
        displayMessage("Error updating guard: " . $db->lastErrorMsg(), 'danger');
    }
}

function deleteGuard($db, $id)
{
    $stmt = $db->prepare("DELETE FROM guards WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    if ($result) {
        displayMessage("Guard deleted successfully!", 'success');
    } else {
        displayMessage("Error deleting guard: " . $db->lastErrorMsg(), 'danger');
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jail Management System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
        }

        .nav-tabs .nav-link.active {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>

<body>
    <h1>Jail Management System</h1>

    <ul class="nav nav-tabs" id="myTab" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" id="inmates-tab" data-toggle="tab" href="#inmates" role="tab" aria-controls="inmates" aria-selected="true">Inmates</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="cells-tab" data-toggle="tab" href="#cells" role="tab" aria-controls="cells" aria-selected="false">Cells</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" id="guards-tab" data-toggle="tab" href="#guards" role="tab" aria-controls="guards" aria-selected="false">Guards</a>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <div class="tab-pane fade show active" id="inmates" role="tabpanel" aria-labelledby="inmates-tab">
            <h2>Inmate Management</h2>

            <!-- Add Inmate Form -->
            <h3>Add Inmate</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_inmate">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="age">Age:</label>
                    <input type="number" class="form-control" id="age" name="age">
                </div>
                <div class="form-group">
                    <label for="crime">Crime:</label>
                    <input type="text" class="form-control" id="crime" name="crime">
                </div>
                <div class="form-group">
                    <label for="sentence_start">Sentence Start Date:</label>
                    <input type="date" class="form-control" id="sentence_start" name="sentence_start">
                </div>
                <div class="form-group">
                    <label for="sentence_end">Sentence End Date:</label>
                    <input type="date" class="form-control" id="sentence_end" name="sentence_end">
                </div>
                <div class="form-group">
                    <label for="cell_id">Cell ID:</label>
                    <select class="form-control" id="cell_id" name="cell_id">
                        <?php
                        $cells = getCells($db);
                        foreach ($cells as $cell) {
                            echo "<option value='" . $cell['id'] . "'>" . $cell['id'] . " (Capacity: " . $cell['capacity'] . ", Occupied: " . $cell['occupied'] . ")</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea class="form-control" id="notes" name="notes"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add Inmate</button>
            </form>

            <!-- List Inmates -->
            <h3>Inmates</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Crime</th>
                        <th>Sentence Start</th>
                        <th>Sentence End</th>
                        <th>Cell ID</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $inmates = getInmates($db);
                    foreach ($inmates as $inmate) {
                        echo "<tr>";
                        echo "<td>" . $inmate['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($inmate['name']) . "</td>";
                        echo "<td>" . $inmate['age'] . "</td>";
                        echo "<td>" . htmlspecialchars($inmate['crime']) . "</td>";
                        echo "<td>" . $inmate['sentence_start'] . "</td>";
                        echo "<td>" . $inmate['sentence_end'] . "</td>";
                        echo "<td>" . $inmate['cell_id'] . "</td>";
                        echo "<td>" . htmlspecialchars($inmate['notes']) . "</td>";
                        echo "<td>
                                <a href='?action=edit_inmate&id=" . $inmate['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                <a href='?action=delete_inmate&id=" . $inmate['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Edit Inmate Form -->
            <?php
            if (isset($_GET['action']) && $_GET['action'] == 'edit_inmate' && isset($_GET['id'])) {
                $inmate_id = intval($_GET['id']);
                $inmate = getInmateById($db, $inmate_id);
                if ($inmate) {
            ?>
                    <h3>Edit Inmate</h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_inmate">
                        <input type="hidden" name="id" value="<?php echo $inmate['id']; ?>">
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($inmate['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="age">Age:</label>
                            <input type="number" class="form-control" id="age" name="age" value="<?php echo $inmate['age']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="crime">Crime:</label>
                            <input type="text" class="form-control" id="crime" name="crime" value="<?php echo htmlspecialchars($inmate['crime']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="sentence_start">Sentence Start Date:</label>
                            <input type="date" class="form-control" id="sentence_start" name="sentence_start" value="<?php echo $inmate['sentence_start']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="sentence_end">Sentence End Date:</label>
                            <input type="date" class="form-control" id="sentence_end" name="sentence_end" value="<?php echo $inmate['sentence_end']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="cell_id">Cell ID:</label>
                            <select class="form-control" id="cell_id" name="cell_id">
                                <?php
                                $cells = getCells($db);
                                foreach ($cells as $cell) {
                                    $selected = ($cell['id'] == $inmate['cell_id']) ? 'selected' : '';
                                    echo "<option value='" . $cell['id'] . "' " . $selected . ">" . $cell['id'] . " (Capacity: " . $cell['capacity'] . ", Occupied: " . $cell['occupied'] . ")</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes:</label>
                            <textarea class="form-control" id="notes" name="notes"><?php echo htmlspecialchars($inmate['notes']); ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Inmate</button>
                    </form>
            <?php
                } else {
                    displayMessage("Inmate not found.", 'danger');
                }
            }
            ?>
        </div>

        <div class="tab-pane fade" id="cells" role="tabpanel" aria-labelledby="cells-tab">
            <h2>Cell Management</h2>

            <!-- Add Cell Form -->
            <h3>Add Cell</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_cell">
                <div class="form-group">
                    <label for="capacity">Capacity:</label>
                    <input type="number" class="form-control" id="capacity" name="capacity" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Cell</button>
            </form>

            <!-- List Cells -->
            <h3>Cells</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Capacity</th>
                        <th>Occupied</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cells = getCells($db);
                    foreach ($cells as $cell) {
                        echo "<tr>";
                        echo "<td>" . $cell['id'] . "</td>";
                        echo "<td>" . $cell['capacity'] . "</td>";
                        echo "<td>" . $cell['occupied'] . "</td>";
                        echo "<td>
                                <a href='?action=edit_cell&id=" . $cell['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                <a href='?action=delete_cell&id=" . $cell['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Edit Cell Form -->
            <?php
            if (isset($_GET['action']) && $_GET['action'] == 'edit_cell' && isset($_GET['id'])) {
                $cell_id = intval($_GET['id']);
                $cell = getCellById($db, $cell_id);
                if ($cell) {
            ?>
                    <h3>Edit Cell</h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_cell">
                        <input type="hidden" name="id" value="<?php echo $cell['id']; ?>">
                        <div class="form-group">
                            <label for="capacity">Capacity:</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" value="<?php echo $cell['capacity']; ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Update Cell</button>
                    </form>
            <?php
                } else {
                    displayMessage("Cell not found.", 'danger');
                }
            }
            ?>
        </div>

        <div class="tab-pane fade" id="guards" role="tabpanel" aria-labelledby="guards-tab">
            <h2>Guard Management</h2>

            <!-- Add Guard Form -->
            <h3>Add Guard</h3>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_guard">
                <div class="form-group">
                    <label for="name">Name:</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <div class="form-group">
                    <label for="shift">Shift:</label>
                    <input type="text" class="form-control" id="shift" name="shift">
                </div>
                <button type="submit" class="btn btn-primary">Add Guard</button>
            </form>

            <!-- List Guards -->
            <h3>Guards</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Shift</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $guards = getGuards($db);
                    foreach ($guards as $guard) {
                        echo "<tr>";
                        echo "<td>" . $guard['id'] . "</td>";
                        echo "<td>" . htmlspecialchars($guard['name']) . "</td>";
                        echo "<td>" . htmlspecialchars($guard['shift']) . "</td>";
                        echo "<td>
                                <a href='?action=edit_guard&id=" . $guard['id'] . "' class='btn btn-sm btn-warning'>Edit</a>
                                <a href='?action=delete_guard&id=" . $guard['id'] . "' class='btn btn-sm btn-danger' onclick='return confirm(\"Are you sure?\")'>Delete</a>
                              </td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>

            <!-- Edit Guard Form -->
            <?php
            if (isset($_GET['action']) && $_GET['action'] == 'edit_guard' && isset($_GET['id'])) {
                $guard_id = intval($_GET['id']);
                $guard = getGuardById($db, $guard_id);
                if ($guard) {
            ?>
                    <h3>Edit Guard</h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_guard">
                        <input type="hidden" name="id" value="<?php echo $guard['id']; ?>">
                        <div class="form-group">
                            <label for="name">Name:</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($guard['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="shift">Shift:</label>
                            <input type="text" class="form-control" id="shift" name="shift" value="<?php echo htmlspecialchars($guard['shift']); ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Update Guard</button>
                    </form>
            <?php
                } else {
                    displayMessage("Guard not found.", 'danger');
                }
            }
            ?>
        </div>
    </div>

    <?php
    // Handle Form Submissions
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add_inmate':
                addInmate(
                    $db,
                    $_POST['name'],
                    $_POST['age'],
                    $_POST['crime'],
                    $_POST['sentence_start'],
                    $_POST['sentence_end'],
                    $_POST['cell_id'],
                    $_POST['notes']
                );
                break;
            case 'update_inmate':
                updateInmate(
                    $db,
                    $_POST['id'],
                    $_POST['name'],
                    $_POST['age'],
                    $_POST['crime'],
                    $_POST['sentence_start'],
                    $_POST['sentence_end'],
                    $_POST['cell_id'],
                    $_POST['notes']
                );
                break;
            case 'add_cell':
                addCell($db, $_POST['capacity']);
                break;
            case 'update_cell':
                updateCell($db, $_POST['id'], $_POST['capacity']);
                break;
            case 'add_guard':
                addGuard($db, $_POST['name'], $_POST['shift']);
                break;
            case 'update_guard':
                updateGuard($db, $_POST['id'], $_POST['name'], $_POST['shift']);
                break;
            default:
                break;
        }
    }


    // Handle GET Requests for Deletion
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        if ($action == 'delete_inmate' && isset($_GET['id'])) {
            deleteInmate($db, $_GET['id']);
        } elseif ($action == 'delete_cell' && isset($_GET['id'])) {
            deleteCell($db, $_GET['id']);
        } elseif ($action == 'delete_guard' && isset($_GET['id'])) {
            deleteGuard($db, $_GET['id']);
        }
    }
    ?>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>

</html>