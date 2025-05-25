<?php
// Author: Yasin Ullah
// Pakistani

// Database Configuration
define('DB_PATH', __DIR__ . '/quran_study5.sqlite');

// Ensure the database file exists and is writable
if (!file_exists(DB_PATH)) {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Initial schema creation
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT DEFAULT 'user' -- 'user', 'admin'
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS personal_tafsir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            surah INTEGER,
            ayah INTEGER,
            tafsir TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS thematic_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            surah_from INTEGER,
            ayah_from INTEGER,
            surah_to INTEGER,
            ayah_to INTEGER,
            theme TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS root_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            root_word TEXT, -- Assuming root word is stored here
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS recitation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            surah INTEGER,
            ayah_start INTEGER,
            ayah_end INTEGER,
            recited_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS hifz_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            surah INTEGER,
            ayah_start INTEGER,
            ayah_end INTEGER,
            status TEXT DEFAULT 'learning', -- 'learning', 'memorized', 'revision'
            last_revised DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS word_dictionary (
            word_id INTEGER PRIMARY KEY,
            quran_text TEXT NOT NULL,
            ur_meaning TEXT,
            en_meaning TEXT
        )");
        $db->exec("CREATE TABLE IF NOT EXISTS ayah_word_mapping (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            surah INTEGER NOT NULL,
            ayah INTEGER NOT NULL,
            word_position INTEGER NOT NULL,
            word_id INTEGER NOT NULL,
            FOREIGN KEY (word_id) REFERENCES word_dictionary(word_id)
        )");

        // Add sample admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$admin_password', 'admin')");

        // Add sample user
        $user_password = password_hash('user123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, role) VALUES ('user', '$user_password', 'user')");

        // Add sample data
        $db->exec("INSERT INTO personal_tafsir (user_id, surah, ayah, tafsir) VALUES (2, 1, 1, 'This is my personal reflection on Surah Al-Fatihah, Ayah 1.')");
        $db->exec("INSERT INTO thematic_links (user_id, surah_from, ayah_from, surah_to, ayah_to, theme) VALUES (2, 2, 1, 3, 1, 'Concept of Tawheed')");
        $db->exec("INSERT INTO root_notes (user_id, root_word, note) VALUES (2, 'علم', 'Notes on the root meaning of knowledge.')");
        $db->exec("INSERT INTO recitation_logs (user_id, surah, ayah_start, ayah_end) VALUES (2, 1, 1, 7)");
        $db->exec("INSERT INTO hifz_tracking (user_id, surah, ayah_start, ayah_end, status) VALUES (2, 1, 1, 7, 'memorized')");

    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Start session
session_start();

// Function to get database connection
function get_db() {
    try {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Authentication Functions
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['user_role'] ?? 'guest';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ?page=login');
        exit();
    }
}

function require_admin() {
    require_login();
    if (get_user_role() !== 'admin') {
        header('Location: ?page=dashboard'); // Or an access denied page
        exit();
    }
}

// Handle Form Submissions (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $db = get_db();
            if ($db) {
                $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $username;
                    $_SESSION['user_role'] = $user['role'];
                    header('Location: ?page=dashboard');
                    exit();
                } else {
                    $_SESSION['error'] = 'Invalid username or password.';
                    header('Location: ?page=login');
                    exit();
                }
            } else {
                 $_SESSION['error'] = 'Database error during login.';
                 header('Location: ?page=login');
                 exit();
            }
            break;

        case 'logout':
            session_unset();
            session_destroy();
            header('Location: ?page=login');
            exit();
            break;

        case 'add_tafsir':
            require_login();
            $surah = $_POST['surah'] ?? null;
            $ayah = $_POST['ayah'] ?? null;
            $tafsir = $_POST['tafsir'] ?? '';
            $user_id = $_SESSION['user_id'];

            if ($surah !== null && $ayah !== null) {
                $db = get_db();
                if ($db) {
                    // Check if tafsir already exists for this ayah and user
                    $stmt_check = $db->prepare("SELECT id FROM personal_tafsir WHERE user_id = :user_id AND surah = :surah AND ayah = :ayah");
                    $stmt_check->bindParam(':user_id', $user_id);
                    $stmt_check->bindParam(':surah', $surah);
                    $stmt_check->bindParam(':ayah', $ayah);
                    $stmt_check->execute();
                    $existing_tafsir = $stmt_check->fetch(PDO::FETCH_ASSOC);

                    if ($existing_tafsir) {
                        // Update existing tafsir
                        $stmt_update = $db->prepare("UPDATE personal_tafsir SET tafsir = :tafsir, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt_update->bindParam(':tafsir', $tafsir);
                        $stmt_update->bindParam(':id', $existing_tafsir['id']);
                        $stmt_update->execute();
                    } else {
                        // Insert new tafsir
                        $stmt_insert = $db->prepare("INSERT INTO personal_tafsir (user_id, surah, ayah, tafsir) VALUES (:user_id, :surah, :ayah, :tafsir)");
                        $stmt_insert->bindParam(':user_id', $user_id);
                        $stmt_insert->bindParam(':surah', $surah);
                        $stmt_insert->bindParam(':ayah', $ayah);
                        $stmt_insert->bindParam(':tafsir', $tafsir);
                        $stmt_insert->execute();
                    }
                    $_SESSION['success'] = 'Tafsir saved successfully.';
                } else {
                    $_SESSION['error'] = 'Database error saving tafsir.';
                }
            } else {
                $_SESSION['error'] = 'Invalid Surah or Ayah provided.';
            }
            // Redirect back to the ayah view
            header('Location: ?page=quran&surah=' . $surah . '&ayah=' . $ayah);
            exit();
            break;

        case 'add_thematic_link':
            require_login();
            $surah_from = $_POST['surah_from'] ?? null;
            $ayah_from = $_POST['ayah_from'] ?? null;
            $surah_to = $_POST['surah_to'] ?? null;
            $ayah_to = $_POST['ayah_to'] ?? null;
            $theme = $_POST['theme'] ?? '';
            $user_id = $_SESSION['user_id'];

            if ($surah_from !== null && $ayah_from !== null && $surah_to !== null && $ayah_to !== null) {
                $db = get_db();
                if ($db) {
                    $stmt = $db->prepare("INSERT INTO thematic_links (user_id, surah_from, ayah_from, surah_to, ayah_to, theme) VALUES (:user_id, :surah_from, :ayah_from, :surah_to, :ayah_to, :theme)");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':surah_from', $surah_from);
                    $stmt->bindParam(':ayah_from', $ayah_from);
                    $stmt->bindParam(':surah_to', $surah_to);
                    $stmt->bindParam(':ayah_to', $ayah_to);
                    $stmt->bindParam(':theme', $theme);
                    $stmt->execute();
                    $_SESSION['success'] = 'Thematic link added successfully.';
                } else {
                     $_SESSION['error'] = 'Database error adding thematic link.';
                }
            } else {
                 $_SESSION['error'] = 'Invalid link details provided.';
            }
             // Redirect back to the ayah view
            header('Location: ?page=quran&surah=' . $surah_from . '&ayah=' . $ayah_from);
            exit();
            break;

        case 'add_root_note':
            require_login();
            $root_word = $_POST['root_word'] ?? '';
            $note = $_POST['note'] ?? '';
            $user_id = $_SESSION['user_id'];

            if (!empty($root_word)) {
                 $db = get_db();
                if ($db) {
                    $stmt = $db->prepare("INSERT INTO root_notes (user_id, root_word, note) VALUES (:user_id, :root_word, :note)");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':root_word', $root_word);
                    $stmt->bindParam(':note', $note);
                    $stmt->execute();
                    $_SESSION['success'] = 'Root note added successfully.';
                } else {
                    $_SESSION['error'] = 'Database error adding root note.';
                }
            } else {
                 $_SESSION['error'] = 'Root word cannot be empty.';
            }
             // Redirect back to the root notes page or dashboard
            header('Location: ?page=root_notes');
            exit();
            break;

        case 'add_recitation_log':
            require_login();
            $surah = $_POST['surah'] ?? null;
            $ayah_start = $_POST['ayah_start'] ?? null;
            $ayah_end = $_POST['ayah_end'] ?? null;
            $notes = $_POST['notes'] ?? '';
            $user_id = $_SESSION['user_id'];

            if ($surah !== null && $ayah_start !== null && $ayah_end !== null) {
                $db = get_db();
                if ($db) {
                    $stmt = $db->prepare("INSERT INTO recitation_logs (user_id, surah, ayah_start, ayah_end, notes) VALUES (:user_id, :surah, :ayah_start, :ayah_end, :notes)");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':surah', $surah);
                    $stmt->bindParam(':ayah_start', $ayah_start);
                    $stmt->bindParam(':ayah_end', $ayah_end);
                    $stmt->bindParam(':notes', $notes);
                    $stmt->execute();
                    $_SESSION['success'] = 'Recitation log added successfully.';
                } else {
                     $_SESSION['error'] = 'Database error adding recitation log.';
                }
            } else {
                 $_SESSION['error'] = 'Invalid recitation details provided.';
            }
             // Redirect back to the recitation logs page or dashboard
            header('Location: ?page=recitation_logs');
            exit();
            break;

        case 'add_hifz_tracking':
            require_login();
            $surah = $_POST['surah'] ?? null;
            $ayah_start = $_POST['ayah_start'] ?? null;
            $ayah_end = $_POST['ayah_end'] ?? null;
            $status = $_POST['status'] ?? 'learning';
            $user_id = $_SESSION['user_id'];

             if ($surah !== null && $ayah_start !== null && $ayah_end !== null) {
                $db = get_db();
                if ($db) {
                     // Check if entry already exists for this range and user
                    $stmt_check = $db->prepare("SELECT id FROM hifz_tracking WHERE user_id = :user_id AND surah = :surah AND ayah_start = :ayah_start AND ayah_end = :ayah_end");
                    $stmt_check->bindParam(':user_id', $user_id);
                    $stmt_check->bindParam(':surah', $surah);
                    $stmt_check->bindParam(':ayah_start', $ayah_start);
                    $stmt_check->bindParam(':ayah_end', $ayah_end);
                    $stmt_check->execute();
                    $existing_entry = $stmt_check->fetch(PDO::FETCH_ASSOC);

                    if ($existing_entry) {
                         // Update existing entry
                        $stmt_update = $db->prepare("UPDATE hifz_tracking SET status = :status, last_revised = CURRENT_TIMESTAMP WHERE id = :id");
                        $stmt_update->bindParam(':status', $status);
                        $stmt_update->bindParam(':id', $existing_entry['id']);
                        $stmt_update->execute();
                    } else {
                         // Insert new entry
                        $stmt_insert = $db->prepare("INSERT INTO hifz_tracking (user_id, surah, ayah_start, ayah_end, status, last_revised) VALUES (:user_id, :surah, :ayah_start, :ayah_end, :status, CURRENT_TIMESTAMP)");
                        $stmt_insert->bindParam(':user_id', $user_id);
                        $stmt_insert->bindParam(':surah', $surah);
                        $stmt_insert->bindParam(':ayah_start', $ayah_start);
                        $stmt_insert->bindParam(':ayah_end', $ayah_end);
                        $stmt_insert->bindParam(':status', $status);
                        $stmt_insert->execute();
                    }
                    $_SESSION['success'] = 'Hifz tracking updated successfully.';
                } else {
                     $_SESSION['error'] = 'Database error updating hifz tracking.';
                }
            } else {
                 $_SESSION['error'] = 'Invalid hifz tracking details provided.';
            }
             // Redirect back to the hifz tracking page or dashboard
            header('Location: ?page=hifz_tracking');
            exit();
            break;

        case 'import_data5':
            require_admin();
            if (isset($_FILES['data5_file']) && $_FILES['data5_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['data5_file']['tmp_name'];
                $db = get_db();
                if ($db) {
                    $db->beginTransaction();
                    try {
                        // Clear existing data
                        $db->exec("DELETE FROM word_dictionary");
                        $db->exec("DELETE FROM sqlite_sequence WHERE name='word_dictionary'"); // Reset auto-increment

                        if (($handle = fopen($file, "r")) !== FALSE) {
                            // Skip header row if exists
                            // fgetcsv($handle);
                            while (($data = fgetcsv($handle)) !== FALSE) {
                                if (count($data) >= 4) {
                                    $word_id = (int)$data[0];
                                    $quran_text = $data[1];
                                    $ur_meaning = $data[2];
                                    $en_meaning = $data[3];

                                    $stmt = $db->prepare("INSERT INTO word_dictionary (word_id, quran_text, ur_meaning, en_meaning) VALUES (:word_id, :quran_text, :ur_meaning, :en_meaning)");
                                    $stmt->bindParam(':word_id', $word_id);
                                    $stmt->bindParam(':quran_text', $quran_text);
                                    $stmt->bindParam(':ur_meaning', $ur_meaning);
                                    $stmt->bindParam(':en_meaning', $en_meaning);
                                    $stmt->execute();
                                }
                            }
                            fclose($handle);
                        }
                        $db->commit();
                        $_SESSION['success'] = 'data5.AM imported successfully.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $_SESSION['error'] = 'Error importing data5.AM: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = 'Database error during data5.AM import.';
                }
            } else {
                $_SESSION['error'] = 'Error uploading data5.AM file.';
            }
            header('Location: ?page=admin_data');
            exit();
            break;

        case 'import_data2':
            require_admin();
            if (isset($_FILES['data2_file']) && $_FILES['data2_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['data2_file']['tmp_name'];
                $db = get_db();
                if ($db) {
                    $db->beginTransaction();
                    try {
                         // Clear existing data
                        $db->exec("DELETE FROM ayah_word_mapping");
                        $db->exec("DELETE FROM sqlite_sequence WHERE name='ayah_word_mapping'"); // Reset auto-increment

                        if (($handle = fopen($file, "r")) !== FALSE) {
                            // Skip header row if exists
                            // fgetcsv($handle);
                            while (($data = fgetcsv($handle)) !== FALSE) {
                                if (count($data) >= 4) {
                                    $word_id = (int)$data[0];
                                    $surah = (int)$data[1];
                                    $ayah = (int)$data[2];
                                    $word_position = (int)$data[3];

                                    $stmt = $db->prepare("INSERT INTO ayah_word_mapping (word_id, surah, ayah, word_position) VALUES (:word_id, :surah, :ayah, :word_position)");
                                    $stmt->bindParam(':word_id', $word_id);
                                    $stmt->bindParam(':surah', $surah);
                                    $stmt->bindParam(':ayah', $ayah);
                                    $stmt->bindParam(':word_position', $word_position);
                                    $stmt->execute();
                                }
                            }
                            fclose($handle);
                        }
                        $db->commit();
                        $_SESSION['success'] = 'data2.AM imported successfully.';
                    } catch (Exception $e) {
                        $db->rollBack();
                        $_SESSION['error'] = 'Error importing data2.AM: ' . $e->getMessage();
                    }
                } else {
                    $_SESSION['error'] = 'Database error during data2.AM import.';
                }
            } else {
                $_SESSION['error'] = 'Error uploading data2.AM file.';
            }
            header('Location: ?page=admin_data');
            exit();
            break;

        case 'backup_db':
            require_admin();
            $backup_file = 'quran_study_backup_' . date('YmdHis') . '.sqlite';
            if (copy(DB_PATH, __DIR__ . '/' . $backup_file)) {
                $_SESSION['success'] = 'Database backed up successfully to ' . $backup_file;
                 // Offer download
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($backup_file) . '"');
                readfile(__DIR__ . '/' . $backup_file);
                unlink(__DIR__ . '/' . $backup_file); // Delete temporary file
                exit;
            } else {
                $_SESSION['error'] = 'Failed to create database backup.';
            }
            header('Location: ?page=admin_data');
            exit();
            break;

        case 'restore_db':
            require_admin();
            if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['restore_file']['tmp_name'];
                 // Simple check if it's a sqlite file (basic)
                if (mime_content_type($file) === 'application/x-sqlite3') {
                    // Close existing database connection
                    $db = null;
                    // Replace the current database file
                    if (rename($file, DB_PATH)) {
                        $_SESSION['success'] = 'Database restored successfully.';
                    } else {
                        $_SESSION['error'] = 'Failed to replace database file.';
                    }
                } else {
                     $_SESSION['error'] = 'Invalid file type. Please upload a valid SQLite database file.';
                }
            } else {
                $_SESSION['error'] = 'Error uploading restore file.';
            }
            header('Location: ?page=admin_data');
            exit();
            break;

        // Add other POST actions here (e.g., user management by admin)
    }
}

// Handle GET requests for rendering pages
$page = $_GET['page'] ?? 'home';

// Surah and Ayah data (basic structure, could be expanded)
$surahs = [
    1 => ['name_arabic' => 'الفاتحة', 'name_english' => 'Al-Fatiha', 'ayahs' => 7],
    2 => ['name_arabic' => 'البقرة', 'name_english' => 'Al-Baqarah', 'ayahs' => 286],
    3 => ['name_arabic' => 'آل عمران', 'name_english' => 'Al-Imran', 'ayahs' => 200],
    4 => ['name_arabic' => 'النساء', 'name_english' => 'An-Nisa', 'ayahs' => 176],
    5 => ['name_arabic' => 'المائدة', 'name_english' => 'Al-Ma\'idah', 'ayahs' => 120],
    6 => ['name_arabic' => 'الأنعام', 'name_english' => 'Al-An\'am', 'ayahs' => 165],
    7 => ['name_arabic' => 'الأعراف', 'name_english' => 'Al-A\'raf', 'ayahs' => 206],
    8 => ['name_arabic' => 'الأنفال', 'name_english' => 'Al-Anfal', 'ayahs' => 75],
    9 => ['name_arabic' => 'التوبة', 'name_english' => 'At-Tawbah', 'ayahs' => 129],
    10 => ['name_arabic' => 'يونس', 'name_english' => 'Yunus', 'ayahs' => 109],
    11 => ['name_arabic' => 'هود', 'name_english' => 'Hud', 'ayahs' => 123],
    12 => ['name_arabic' => 'يوسف', 'name_english' => 'Yusuf', 'ayahs' => 111],
    13 => ['name_arabic' => 'الرعد', 'name_english' => 'Ar-Ra\'d', 'ayahs' => 43],
    14 => ['name_arabic' => 'ابراهيم', 'name_english' => 'Ibrahim', 'ayahs' => 52],
    15 => ['name_arabic' => 'الحجر', 'name_english' => 'Al-Hijr', 'ayahs' => 99],
    16 => ['name_arabic' => 'النحل', 'name_english' => 'An-Nahl', 'ayahs' => 128],
    17 => ['name_arabic' => 'الإسراء', 'name_english' => 'Al-Isra', 'ayahs' => 111],
    18 => ['name_arabic' => 'الكهف', 'name_english' => 'Al-Kahf', 'ayahs' => 110],
    19 => ['name_arabic' => 'مريم', 'name_english' => 'Maryam', 'ayahs' => 98],
    20 => ['name_arabic' => 'طه', 'name_english' => 'Taha', 'ayahs' => 135],
    21 => ['name_arabic' => 'الأنبياء', 'name_english' => 'Al-Anbiya', 'ayahs' => 112],
    22 => ['name_arabic' => 'الحج', 'name_english' => 'Al-Hajj', 'ayahs' => 78],
    23 => ['name_arabic' => 'المؤمنون', 'name_english' => 'Al-Mu\'minun', 'ayahs' => 118],
    24 => ['name_arabic' => 'النور', 'name_english' => 'An-Nur', 'ayahs' => 64],
    25 => ['name_arabic' => 'الفرقان', 'name_english' => 'Al-Furqan', 'ayahs' => 77],
    26 => ['name_arabic' => 'الشعراء', 'name_english' => 'Ash-Shu\'ara', 'ayahs' => 227],
    27 => ['name_arabic' => 'النمل', 'name_english' => 'An-Naml', 'ayahs' => 93],
    28 => ['name_arabic' => 'القصص', 'name_english' => 'Al-Qasas', 'ayahs' => 88],
    29 => ['name_arabic' => 'العنكبوت', 'name_english' => 'Al-Ankabut', 'ayahs' => 69],
    30 => ['name_arabic' => 'الروم', 'name_english' => 'Ar-Rum', 'ayahs' => 60],
    31 => ['name_arabic' => 'لقمان', 'name_english' => 'Luqman', 'ayahs' => 34],
    32 => ['name_arabic' => 'السجدة', 'name_english' => 'As-Sajdah', 'ayahs' => 30],
    33 => ['name_arabic' => 'الأحزاب', 'name_english' => 'Al-Ahzab', 'ayahs' => 73],
    34 => ['name_arabic' => 'سبأ', 'name_english' => 'Saba', 'ayahs' => 54],
    35 => ['name_arabic' => 'فاطر', 'name_english' => 'Fatir', 'ayahs' => 45],
    36 => ['name_arabic' => 'يس', 'name_english' => 'Ya-Sin', 'ayahs' => 83],
    37 => ['name_arabic' => 'الصافات', 'name_english' => 'As-Saffat', 'ayahs' => 182],
    38 => ['name_arabic' => 'ص', 'name_english' => 'Sad', 'ayahs' => 88],
    39 => ['name_arabic' => 'الزمر', 'name_english' => 'Az-Zumar', 'ayahs' => 75],
    40 => ['name_arabic' => 'غافر', 'name_english' => 'Ghafir', 'ayahs' => 85],
    41 => ['name_arabic' => 'فصلت', 'name_english' => 'Fussilat', 'ayahs' => 54],
    42 => ['name_arabic' => 'الشورى', 'name_english' => 'Ash-Shuraa', 'ayahs' => 53],
    43 => ['name_arabic' => 'الزخرف', 'name_english' => 'Az-Zukhruf', 'ayahs' => 89],
    44 => ['name_arabic' => 'الدخان', 'name_english' => 'Ad-Dukhan', 'ayahs' => 59],
    45 => ['name_arabic' => 'الجاثية', 'name_english' => 'Al-Jathiyah', 'ayahs' => 37],
    46 => ['name_arabic' => 'الأحقاف', 'name_english' => 'Al-Ahqaf', 'ayahs' => 35],
    47 => ['name_arabic' => 'محمد', 'name_english' => 'Muhammad', 'ayahs' => 38],
    48 => ['name_arabic' => 'الفتح', 'name_english' => 'Al-Fath', 'ayahs' => 29],
    49 => ['name_arabic' => 'الحجرات', 'name_english' => 'Al-Hujurat', 'ayahs' => 18],
    50 => ['name_arabic' => 'ق', 'name_english' => 'Qaf', 'ayahs' => 45],
    51 => ['name_arabic' => 'الذاريات', 'name_english' => 'Adh-Dhariyat', 'ayahs' => 60],
    52 => ['name_arabic' => 'الطور', 'name_english' => 'At-Tur', 'ayahs' => 49],
    53 => ['name_arabic' => 'النجم', 'name_english' => 'An-Najm', 'ayahs' => 62],
    54 => ['name_arabic' => 'القمر', 'name_english' => 'Al-Qamar', 'ayahs' => 55],
    55 => ['name_arabic' => 'الرحمن', 'name_english' => 'Ar-Rahman', 'ayahs' => 78],
    56 => ['name_arabic' => 'الواقعة', 'name_english' => 'Al-Waqi\'ah', 'ayahs' => 96],
    57 => ['name_arabic' => 'الحديد', 'name_english' => 'Al-Hadid', 'ayahs' => 29],
    58 => ['name_arabic' => 'المجادلة', 'name_english' => 'Al-Mujadila', 'ayahs' => 22],
    59 => ['name_arabic' => 'الحشر', 'name_english' => 'Al-Hashr', 'ayahs' => 24],
    60 => ['name_arabic' => 'الممتحنة', 'name_english' => 'Al-Mumtahanah', 'ayahs' => 13],
    61 => ['name_arabic' => 'الصف', 'name_english' => 'As-Saff', 'ayahs' => 14],
    62 => ['name_arabic' => 'الجمعة', 'name_english' => 'Al-Jumu\'ah', 'ayahs' => 11],
    63 => ['name_arabic' => 'المنافقون', 'name_english' => 'Al-Munafiqun', 'ayahs' => 11],
    64 => ['name_arabic' => 'التغابن', 'name_english' => 'At-Taghabun', 'ayahs' => 18],
    65 => ['name_arabic' => 'الطلاق', 'name_english' => 'At-Talaq', 'ayahs' => 12],
    66 => ['name_arabic' => 'التحريم', 'name_english' => 'At-Tahrim', 'ayahs' => 12],
    67 => ['name_arabic' => 'الملك', 'name_english' => 'Al-Mulk', 'ayahs' => 30],
    68 => ['name_arabic' => 'القلم', 'name_english' => 'Al-Qalam', 'ayahs' => 52],
    69 => ['name_arabic' => 'الحاقة', 'name_english' => 'Al-Haqqah', 'ayahs' => 52],
    70 => ['name_arabic' => 'المعارج', 'name_english' => 'Al-Ma\'arij', 'ayahs' => 44],
    71 => ['name_arabic' => 'نوح', 'name_english' => 'Nuh', 'ayahs' => 28],
    72 => ['name_arabic' => 'الجن', 'name_english' => 'Al-Jinn', 'ayahs' => 28],
    73 => ['name_arabic' => 'المزمل', 'name_english' => 'Al-Muzzammil', 'ayahs' => 20],
    74 => ['name_arabic' => 'المدثر', 'name_english' => 'Al-Muddaththir', 'ayahs' => 56],
    75 => ['name_arabic' => 'القيامة', 'name_english' => 'Al-Qiyamah', 'ayahs' => 40],
    76 => ['name_arabic' => 'الإنسان', 'name_english' => 'Al-Insan', 'ayahs' => 31],
    77 => ['name_arabic' => 'المرسلات', 'name_english' => 'Al-Mursalat', 'ayahs' => 50],
    78 => ['name_arabic' => 'النبأ', 'name_english' => 'An-Naba', 'ayahs' => 40],
    79 => ['name_arabic' => 'النازعات', 'name_english' => 'An-Nazi\'at', 'ayahs' => 46],
    80 => ['name_arabic' => 'عبس', 'name_english' => '\'Abasa', 'ayahs' => 42],
    81 => ['name_arabic' => 'التكوير', 'name_english' => 'At-Takwir', 'ayahs' => 29],
    82 => ['name_arabic' => 'الإنفطار', 'name_english' => 'Al-Infitar', 'ayahs' => 19],
    83 => ['name_arabic' => 'المطففين', 'name_english' => 'Al-Mutaffifin', 'ayahs' => 36],
    84 => ['name_arabic' => 'الإنشقاق', 'name_english' => 'Al-Inshiqaq', 'ayahs' => 25],
    85 => ['name_arabic' => 'البروج', 'name_english' => 'Al-Buruj', 'ayahs' => 22],
    86 => ['name_arabic' => 'الطارق', 'name_english' => 'At-Tariq', 'ayahs' => 17],
    87 => ['name_arabic' => 'الأعلى', 'name_english' => 'Al-A\'la', 'ayahs' => 19],
    88 => ['name_arabic' => 'الغاشية', 'name_english' => 'Al-Ghashiyah', 'ayahs' => 26],
    89 => ['name_arabic' => 'الفجر', 'name_english' => 'Al-Fajr', 'ayahs' => 30],
    90 => ['name_arabic' => 'البلد', 'name_english' => 'Al-Balad', 'ayahs' => 20],
    91 => ['name_arabic' => 'الشمس', 'name_english' => 'Ash-Shams', 'ayahs' => 15],
    92 => ['name_arabic' => 'الليل', 'name_english' => 'Al-Layl', 'ayahs' => 21],
    93 => ['name_arabic' => 'الضحى', 'name_english' => 'Ad-Duhaa', 'ayahs' => 11],
    94 => ['name_arabic' => 'الشرح', 'name_english' => 'Ash-Sharh', 'ayahs' => 8],
    95 => ['name_arabic' => 'التين', 'name_english' => 'At-Tin', 'ayahs' => 8],
    96 => ['name_arabic' => 'العلق', 'name_english' => 'Al-\'Alaq', 'ayahs' => 19],
    97 => ['name_arabic' => 'القدر', 'name_english' => 'Al-Qadr', 'ayahs' => 5],
    98 => ['name_arabic' => 'البينة', 'name_english' => 'Al-Bayyinah', 'ayahs' => 8],
    99 => ['name_arabic' => 'الزلزلة', 'name_english' => 'Az-Zalzalah', 'ayahs' => 8],
    100 => ['name_arabic' => 'العاديات', 'name_english' => 'Al-\'Adiyat', 'ayahs' => 11],
    101 => ['name_arabic' => 'القارعة', 'name_english' => 'Al-Qari\'ah', 'ayahs' => 11],
    102 => ['name_arabic' => 'التكاثر', 'name_english' => 'At-Takathur', 'ayahs' => 8],
    103 => ['name_arabic' => 'العصر', 'name_english' => 'Al-\'Asr', 'ayahs' => 3],
    104 => ['name_arabic' => 'الهمزة', 'name_english' => 'Al-Humazah', 'ayahs' => 9],
    105 => ['name_arabic' => 'الفيل', 'name_english' => 'Al-Fil', 'ayahs' => 5],
    106 => ['name_arabic' => 'قريش', 'name_english' => 'Quraysh', 'ayahs' => 4],
    107 => ['name_arabic' => 'الماعون', 'name_english' => 'Al-Ma\'un', 'ayahs' => 7],
    108 => ['name_arabic' => 'الكوثر', 'name_english' => 'Al-Kawthar', 'ayahs' => 3],
    109 => ['name_arabic' => 'الكافرون', 'name_english' => 'Al-Kafirun', 'ayahs' => 6],
    110 => ['name_arabic' => 'النصر', 'name_english' => 'An-Nasr', 'ayahs' => 3],
    111 => ['name_arabic' => 'المسد', 'name_english' => 'Al-Masad', 'ayahs' => 5],
    112 => ['name_arabic' => 'الإخلاص', 'name_english' => 'Al-Ikhlas', 'ayahs' => 4],
    113 => ['name_arabic' => 'الفلق', 'name_english' => 'Al-Falaq', 'ayahs' => 5],
    114 => ['name_arabic' => 'الناس', 'name_english' => 'An-Nas', 'ayahs' => 6],
];

// Function to get Surah name by number
function get_surah_name($surah_number, $lang = 'english') {
    global $surahs;
    if (isset($surahs[$surah_number])) {
        return $lang === 'arabic' ? $surahs[$surah_number]['name_arabic'] : $surahs[$surah_number]['name_english'];
    }
    return "Unknown Surah";
}

// Function to get total ayahs in a surah
function get_surah_ayah_count($surah_number) {
    global $surahs;
    return $surahs[$surah_number]['ayahs'] ?? 0;
}

// Function to get word details by word_id
function get_word_details($word_id) {
    $db = get_db();
    if ($db) {
        $stmt = $db->prepare("SELECT quran_text, ur_meaning, en_meaning FROM word_dictionary WHERE word_id = :word_id");
        $stmt->bindParam(':word_id', $word_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return null;
}


// HTML Structure and Page Rendering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study Hub | Yasin Ullah</title>
    <meta name="description" content="Comprehensive Quran Study Hub with personal tafsir, thematic linking, root notes, recitation logs, hifz tracking, and advanced search. Powered by word-level data.">
    <meta name="keywords" content="Quran, Islam, Study, Tafsir, Thematic Links, Root Words, Recitation, Hifz, Memorization, Arabic, Urdu, English, Yasin Ullah">
    <meta name="author" content="Yasin Ullah">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --secondary-color: #8BC34A; /* Light Green */
            --accent-color: #FFC107; /* Amber */
            --background-color: #f4f7f6;
            --card-background: #ffffff;
            --text-color: #333;
            --heading-color: #1a1a1a;
            --border-color: #ddd;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px var(--shadow-color);
        }

        header h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
        }

        nav li {
            margin-left: 20px;
        }

        nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        nav a:hover {
            color: var(--accent-color);
        }
        
        main {
            flex: 1;
            padding: 2rem;
            width: 90%;
            margin: 20px auto;
            background-color: var(--card-background);
            box-shadow: 0 0 10px var(--shadow-color);
            border-radius: 8px;
        }

        .container {
             max-width: 1200px;
             margin: 0 auto;
             padding: 0 1rem;
        }

        h2 {
            color: var(--heading-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .flash-message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            opacity: 1;
            transition: opacity 0.5s ease-in-out;
        }

        .flash-message.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .flash-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .flash-message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        .flash-message.hide {
            opacity: 0;
            height: 0;
            padding: 0 1rem;
            margin-bottom: 0;
            overflow: hidden;
        }


        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        form input[type="text"],
        form input[type="password"],
        form input[type="number"],
        form textarea,
        form select,
        form input[type="file"] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }

         form button {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s ease;
        }

        form button:hover {
            background-color: var(--secondary-color);
        }

        .quran-viewer {
            font-family: 'Traditional Arabic', 'Scheherazade', 'Lateef', serif;
            font-size: 1.8rem;
            line-height: 2.5;
            text-align: right;
            direction: rtl;
        }

        .ayah {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: #f9f9f9;
            position: relative;
        }

        .ayah-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-left: 10px;
             display: inline-block;
             vertical-align: top;
        }

        .word {
            cursor: pointer;
            position: relative;
            display: inline-block;
            margin: 0 3px;
            padding: 2px 0;
            transition: background-color 0.2s ease;
        }

        .word:hover {
            background-color: var(--accent-color);
            border-radius: 3px;
        }

        .word-meaning {
            position: absolute;
            bottom: 100%; /* Position above the word */
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--heading-color);
            color: white;
            padding: 10px;
            border-radius: 5px;
            white-space: nowrap;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            pointer-events: none; /* Allow clicking through the tooltip */
            min-width: 150px;
            text-align: center;
        }

        .word:hover .word-meaning {
            opacity: 1;
            visibility: visible;
            bottom: calc(100% + 5px); /* Adjust position slightly above */
        }

        .word-meaning::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 5px;
            border-style: solid;
            border-color: var(--heading-color) transparent transparent transparent;
        }

        .word-meaning strong {
            display: block;
            margin-bottom: 5px;
        }

        .word-meaning p {
            margin: 0;
            font-size: 0.9rem;
        }

        .tafsir-section {
            margin-top: 20px;
            border-top: 1px dashed var(--border-color);
            padding-top: 15px;
        }

        .tafsir-section h3 {
            margin-top: 0;
            color: var(--primary-color);
        }

        .tafsir-section textarea {
            width: calc(100% - 22px);
            min-height: 100px;
        }

        .thematic-links-section {
             margin-top: 20px;
             border-top: 1px dashed var(--border-color);
             padding-top: 15px;
        }

        .thematic-links-section h3 {
             margin-top: 0;
             color: var(--primary-color);
        }

        .thematic-links-list {
             list-style: none;
             padding: 0;
        }

        .thematic-links-list li {
             background-color: #e9e9e9;
             padding: 8px 12px;
             margin-bottom: 5px;
             border-radius: 3px;
             font-size: 0.9rem;
        }

        .thematic-links-list a {
             color: var(--heading-color);
             text-decoration: none;
             font-weight: bold;
        }

        .thematic-links-list a:hover {
             text-decoration: underline;
        }

        .root-notes-section {
             margin-top: 20px;
             border-top: 1px dashed var(--border-color);
             padding-top: 15px;
        }

        .root-notes-section h3 {
             margin-top: 0;
             color: var(--primary-color);
        }

         .root-notes-list {
             list-style: none;
             padding: 0;
        }

        .root-notes-list li {
             background-color: #e9e9e9;
             padding: 8px 12px;
             margin-bottom: 5px;
             border-radius: 3px;
             font-size: 0.9rem;
        }

        .recitation-logs-section, .hifz-tracking-section {
             margin-top: 20px;
             border-top: 1px dashed var(--border-color);
             padding-top: 15px;
        }

         .recitation-logs-section h3, .hifz-tracking-section h3 {
             margin-top: 0;
             color: var(--primary-color);
        }

         .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th, .data-table td {
            border: 1px solid var(--border-color);
            padding: 10px;
            text-align: left;
        }

        .data-table th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: bold;
        }

        .data-table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        .search-results-list {
            list-style: none;
            padding: 0;
        }

        .search-results-list li {
            background-color: #e9e9e9;
            padding: 10px 15px;
            margin-bottom: 8px;
            border-radius: 4px;
        }

        .search-results-list a {
            color: var(--heading-color);
            text-decoration: none;
            font-weight: bold;
        }

        .search-results-list a:hover {
            text-decoration: underline;
        }


        footer {
            background-color: var(--heading-color);
            color: white;
            text-align: center;
            padding: 1rem;
            margin-top: 20px;
        }

        footer p {
            margin: 0;
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                text-align: center;
            }

            nav ul {
                margin-top: 10px;
                flex-direction: column;
                align-items: center;
            }

            nav li {
                margin: 5px 0;
            }

            main {
                padding: 1rem;
            }

            form input[type="text"],
            form input[type="password"],
            form input[type="number"],
            form textarea,
            form select,
            form input[type="file"] {
                width: calc(100% - 20px);
            }

             .word-meaning {
                white-space: normal;
                min-width: 100px;
                left: 0;
                transform: translateX(0);
             }

             .word:hover .word-meaning {
                 left: 0;
                 transform: translateX(0);
             }

             .word-meaning::after {
                 left: 10%; /* Adjust arrow position */
                 transform: translateX(-10%);
             }
        }
*, select, textarea, input {
            font-family: calibri !important;
        }
    </style>
</head>
<body>

    <header>
        <div class="site-title">
            <h1>Quran Study Hub</h1>
        </div>
        <nav>
            <ul>
                <li><a href="?page=home">Home</a></li>
                <?php if (is_logged_in()): ?>
                    <li><a href="?page=dashboard">Dashboard</a></li>
                    <li><a href="?page=quran">Read Quran</a></li>
                    <li><a href="?page=search">Search</a></li>
                    <li><a href="?page=root_notes">Root Notes</a></li>
                    <li><a href="?page=recitation_logs">Recitation Logs</a></li>
                    <li><a href="?page=hifz_tracking">Hifz Tracking</a></li>
                    <?php if (get_user_role() === 'admin'): ?>
                        <li><a href="?page=admin_data">Admin Data</a></li>
                    <?php endif; ?>
                    <li>
                        <form action="" method="post" style="display:inline;">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" style="background: none; border: none; color: white; cursor: pointer; font-weight: bold; padding: 0; margin: 0; font-size: 1rem; transition: color 0.3s ease;">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="?page=login">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="container">
            <?php
            // Display flash messages
            if (isset($_SESSION['success'])): ?>
                <div class="flash-message success">
                    <?php echo htmlspecialchars($_SESSION['success']); ?>
                </div>
                <?php unset($_SESSION['success']);
            endif;

            if (isset($_SESSION['error'])): ?>
                <div class="flash-message error">
                    <?php echo htmlspecialchars($_SESSION['error']); ?>
                </div>
                <?php unset($_SESSION['error']);
            endif;

            if (isset($_SESSION['info'])): ?>
                <div class="flash-message info">
                    <?php echo htmlspecialchars($_SESSION['info']); ?>
                </div>
                <?php unset($_SESSION['info']);
            endif;

            // Route to pages
            switch ($page) {
                case 'home':
                    ?>
                    <h2>Welcome to Quran Study Hub</h2>
                    <p>Your personal companion for studying the Holy Quran. Explore verses, add your personal tafsir, link themes, track your memorization, and more.</p>
                    <?php if (!is_logged_in()): ?>
                        <p><a href="?page=login">Login</a> or <a href="#">Register (Coming Soon)</a> to start your study journey.</p>
                    <?php else: ?>
                         <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! Go to your <a href="?page=dashboard">Dashboard</a> or <a href="?page=quran">Read Quran</a>.</p>
                    <?php endif; ?>
                    <?php
                    break;

                case 'login':
                    if (is_logged_in()) {
                        header('Location: ?page=dashboard');
                        exit();
                    }
                    ?>
                    <h2>Login</h2>
                    <form action="" method="post">
                        <input type="hidden" name="action" value="login">
                        <div>
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div>
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <div>
                            <button type="submit">Login</button>
                        </div>
                    </form>
                    <?php
                    break;

                case 'dashboard':
                    require_login();
                    ?>
                    <h2>Dashboard</h2>
                    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars(ucfirst(get_user_role())); ?>)!</p>

                    <h3>Your Activity Summary</h3>
                    <?php
                    $db = get_db();
                    if ($db) {
                        $user_id = $_SESSION['user_id'];

                        // Count Personal Tafsir entries
                        $stmt_tafsir = $db->prepare("SELECT COUNT(*) FROM personal_tafsir WHERE user_id = :user_id");
                        $stmt_tafsir->bindParam(':user_id', $user_id);
                        $stmt_tafsir->execute();
                        $tafsir_count = $stmt_tafsir->fetchColumn();

                        // Count Thematic Links
                        $stmt_links = $db->prepare("SELECT COUNT(*) FROM thematic_links WHERE user_id = :user_id");
                        $stmt_links->bindParam(':user_id', $user_id);
                        $stmt_links->execute();
                        $links_count = $stmt_links->fetchColumn();

                        // Count Root Notes
                        $stmt_notes = $db->prepare("SELECT COUNT(*) FROM root_notes WHERE user_id = :user_id");
                        $stmt_notes->bindParam(':user_id', $user_id);
                        $stmt_notes->execute();
                        $notes_count = $stmt_notes->fetchColumn();

                         // Count Recitation Logs
                        $stmt_recitations = $db->prepare("SELECT COUNT(*) FROM recitation_logs WHERE user_id = :user_id");
                        $stmt_recitations->bindParam(':user_id', $user_id);
                        $stmt_recitations->execute();
                        $recitations_count = $stmt_recitations->fetchColumn();

                         // Count Hifz Tracking entries
                        $stmt_hifz = $db->prepare("SELECT COUNT(*) FROM hifz_tracking WHERE user_id = :user_id");
                        $stmt_hifz->bindParam(':user_id', $user_id);
                        $stmt_hifz->execute();
                        $hifz_count = $stmt_hifz->fetchColumn();

                        ?>
                        <ul>
                            <li>Personal Tafsir Entries: <?php echo $tafsir_count; ?></li>
                            <li>Thematic Links Added: <?php echo $links_count; ?></li>
                            <li>Root Notes Created: <?php echo $notes_count; ?></li>
                            <li>Recitation Logs Recorded: <?php echo $recitations_count; ?></li>
                            <li>Hifz Tracking Entries: <?php echo $hifz_count; ?></li>
                        </ul>
                        <?php
                    } else {
                        echo "<p>Could not load activity summary due to a database error.</p>";
                    }
                    ?>

                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="?page=quran">Read Quran</a></li>
                        <li><a href="?page=search">Search the Quran</a></li>
                        <li><a href="?page=root_notes">View Your Root Notes</a></li>
                        <li><a href="?page=recitation_logs">View Your Recitation Logs</a></li>
                        <li><a href="?page=hifz_tracking">Manage Your Hifz Tracking</a></li>
                    </ul>

                    <?php if (get_user_role() === 'admin'): ?>
                        <h3>Admin Actions</h3>
                        <ul>
                            <li><a href="?page=admin_data">Manage Data & Backup/Restore</a></li>
                            <li><a href="#">Manage Users (Coming Soon)</a></li>
                        </ul>
                    <?php endif; ?>

                    <?php
                    break;

                case 'quran':
                    require_login();
                    $current_surah = $_GET['surah'] ?? 1;
                    $current_ayah = $_GET['ayah'] ?? 1;

                    // Validate surah and ayah
                    if (!isset($surahs[$current_surah])) {
                        $current_surah = 1;
                        $current_ayah = 1;
                    }
                    $total_ayahs_in_surah = get_surah_ayah_count($current_surah);
                    if ($current_ayah < 1 || $current_ayah > $total_ayahs_in_surah) {
                        $current_ayah = 1;
                    }

                    $surah_name_arabic = get_surah_name($current_surah, 'arabic');
                    $surah_name_english = get_surah_name($current_surah, 'english');

                    ?>
                    <h2>Surah <?php echo htmlspecialchars($current_surah); ?> - <?php echo htmlspecialchars($surah_name_english); ?> (<?php echo htmlspecialchars($surah_name_arabic); ?>)</h2>

                    <div class="navigation" style="margin-bottom: 20px;">
                        <label for="surah_select">Go to Surah:</label>
                        <select id="surah_select" onchange="window.location.href = '?page=quran&surah=' + this.value">
                            <?php foreach ($surahs as $num => $data): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($num == $current_surah) ? 'selected' : ''; ?>>
                                    <?php echo $num; ?>. <?php echo htmlspecialchars($data['name_english']); ?> (<?php echo htmlspecialchars($data['name_arabic']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>

                         <label for="ayah_select">Go to Ayah:</label>
                        <select id="ayah_select" onchange="window.location.href = '?page=quran&surah=<?php echo $current_surah; ?>&ayah=' + this.value">
                             <?php for ($i = 1; $i <= $total_ayahs_in_surah; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $current_ayah) ? 'selected' : ''; ?>>
                                    Ayah <?php echo $i; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="quran-viewer">
                        <?php
                        $db = get_db();
                        if ($db) {
                            // Fetch words for the current ayah
                            $stmt = $db->prepare("SELECT awm.word_position, awm.word_id, wd.quran_text, wd.ur_meaning, wd.en_meaning
                                                 FROM ayah_word_mapping awm
                                                 JOIN word_dictionary wd ON awm.word_id = wd.word_id
                                                 WHERE awm.surah = :surah AND awm.ayah = :ayah
                                                 ORDER BY awm.word_position");
                            $stmt->bindParam(':surah', $current_surah);
                            $stmt->bindParam(':ayah', $current_ayah);
                            $stmt->execute();
                            $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

                            echo '<div class="ayah" data-surah="' . $current_surah . '" data-ayah="' . $current_ayah . '">';
                             // Bismillah check (simple for now)
                            if ($current_surah !== 1 && $current_surah !== 9 && $current_ayah === 1) {
                                 echo '<span class="bismillah">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</span> ';
                            }
                            foreach ($words as $word) {
                                // Pre-populate tooltip data
                                $meaning_html = '<strong>' . htmlspecialchars($word['quran_text']) . '</strong>'
                                              . '<p>Urdu: ' . htmlspecialchars($word['ur_meaning'] ?: 'N/A') . '</p>'
                                              . '<p>English: ' . htmlspecialchars($word['en_meaning'] ?: 'N/A') . '</p>';

                                echo '<span class="word" data-word-id="' . htmlspecialchars($word['word_id']) . '" data-surah="' . htmlspecialchars($current_surah) . '" data-ayah="' . htmlspecialchars($current_ayah) . '" data-pos="' . htmlspecialchars($word['word_position']) . '">'
                                     . htmlspecialchars($word['quran_text'])
                                     . '<span class="word-meaning">' . $meaning_html . '</span>'
                                     . '</span> ';
                            }
                            echo '<span class="ayah-number"> (' . $current_ayah . ') </span>';
                            echo '</div>';

                        } else {
                            echo "<p>Could not load Ayah due to a database error.</p>";
                        }
                        ?>
                    </div>

                    <div class="tafsir-section">
                        <h3>Personal Tafsir for Ayah <?php echo $current_ayah; ?></h3>
                        <?php
                        $personal_tafsir = '';
                        if (is_logged_in()) {
                            $db = get_db();
                            if ($db) {
                                $user_id = $_SESSION['user_id'];
                                $stmt = $db->prepare("SELECT tafsir FROM personal_tafsir WHERE user_id = :user_id AND surah = :surah AND ayah = :ayah");
                                $stmt->bindParam(':user_id', $user_id);
                                $stmt->bindParam(':surah', $current_surah);
                                $stmt->bindParam(':ayah', $current_ayah);
                                $stmt->execute();
                                $tafsir_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                if ($tafsir_data) {
                                    $personal_tafsir = $tafsir_data['tafsir'];
                                }
                            }
                        }
                        ?>
                        <form action="" method="post">
                            <input type="hidden" name="action" value="add_tafsir">
                            <input type="hidden" name="surah" value="<?php echo $current_surah; ?>">
                            <input type="hidden" name="ayah" value="<?php echo $current_ayah; ?>">
                            <textarea name="tafsir" id="personal_tafsir_text" placeholder="Enter your personal tafsir here..."><?php echo htmlspecialchars($personal_tafsir); ?></textarea>
                            <button type="submit">Save Tafsir</button>
                        </form>
                         <div id="current_personal_tafsir" style="margin-top: 15px; padding: 10px; border: 1px solid #eee; background-color: #fcfcfc; min-height: 50px;">
                             <?php echo $personal_tafsir ? nl2br(htmlspecialchars($personal_tafsir)) : 'No personal tafsir added yet.'; ?>
                         </div>
                    </div>

                     <div class="thematic-links-section">
                         <h3>Thematic Links from Ayah <?php echo $current_ayah; ?></h3>
                         <div id="thematic_links_list">
                             <?php
                             $thematic_links = [];
                             if (is_logged_in()) {
                                 $db = get_db();
                                 if ($db) {
                                     $user_id = $_SESSION['user_id'];
                                     $stmt = $db->prepare("SELECT * FROM thematic_links WHERE user_id = :user_id AND surah_from = :surah AND ayah_from = :ayah ORDER BY surah_to, ayah_to");
                                     $stmt->bindParam(':user_id', $user_id);
                                     $stmt->bindParam(':surah', $current_surah);
                                     $stmt->bindParam(':ayah', $current_ayah);
                                     $stmt->execute();
                                     $thematic_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                 }
                             }

                             if (!empty($thematic_links)): ?>
                                 <ul class="thematic-links-list">
                                     <?php foreach ($thematic_links as $link): ?>
                                         <li>
                                             <a href="?page=quran&surah=<?php echo $link['surah_to']; ?>&ayah=<?php echo $link['ayah_to']; ?>">
                                                 Surah <?php echo $link['surah_to']; ?>, Ayah <?php echo $link['ayah_to']; ?>
                                             </a>: <?php echo htmlspecialchars($link['theme']); ?>
                                         </li>
                                     <?php endforeach; ?>
                                 </ul>
                             <?php else: ?>
                                 <p>No thematic links added from this ayah yet.</p>
                             <?php endif; ?>
                         </div>
                         <form action="" method="post" style="margin-top: 15px;">
                             <input type="hidden" name="action" value="add_thematic_link">
                             <input type="hidden" name="surah_from" value="<?php echo $current_surah; ?>">
                             <input type="hidden" name="ayah_from" value="<?php echo $current_ayah; ?>">
                             <h4>Add New Thematic Link</h4>
                             <div>
                                 <label for="link_surah_to">Link to Surah:</label>
                                 <select id="link_surah_to" name="surah_to" required>
                                     <?php foreach ($surahs as $num => $data): ?>
                                         <option value="<?php echo $num; ?>">
                                             <?php echo $num; ?>. <?php echo htmlspecialchars($data['name_english']); ?>
                                         </option>
                                     <?php endforeach; ?>
                                 </select>
                             </div>
                             <div>
                                 <label for="link_ayah_to">Link to Ayah:</label>
                                 <input type="number" id="link_ayah_to" name="ayah_to" min="1" value="1" required>
                             </div>
                             <div>
                                 <label for="link_theme">Theme/Connection:</label>
                                 <input type="text" id="link_theme" name="theme" placeholder="e.g., Patience, Tawheed" required>
                             </div>
                             <button type="submit">Add Link</button>
                         </form>
                     </div>

                    <?php
                    break;

                case 'search':
                    require_login();
                    ?>
                    <h2>Search the Quran</h2>
                    <form id="search_form" action="" method="get">
                        <input type="hidden" name="page" value="search">
                        <div>
                            <label for="search_query">Search for Arabic words, Urdu or English meanings:</label>
                            <input type="text" id="search_query" name="query" placeholder="Enter search term..." value="<?php echo htmlspecialchars($_GET['query'] ?? ''); ?>">
                        </div>
                        <div>
                            <button type="submit">Search</button>
                        </div>
                    </form>

                    <div id="search_results" style="margin-top: 20px;">
                        <h3>Search Results</h3>
                        <div id="search_results_content">
                            <?php
                            if (isset($_GET['query']) && !empty($_GET['query'])) {
                                $query = $_GET['query'];
                                $db = get_db();
                                $results = [];
                                if ($db) {
                                    // Basic search for now, could be improved with FTS
                                    $stmt = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah
                                                         FROM ayah_word_mapping awm
                                                         JOIN word_dictionary wd ON awm.word_id = wd.word_id
                                                         WHERE wd.quran_text LIKE :query OR wd.ur_meaning LIKE :query OR wd.en_meaning LIKE :query
                                                         ORDER BY awm.surah, awm.ayah
                                                         LIMIT 50"); // Limit results
                                    $search_query_param = '%' . $query . '%';
                                    $stmt->bindParam(':query', $search_query_param);
                                    $stmt->execute();
                                    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                }

                                if (!empty($results)): ?>
                                    <ul class="search-results-list">
                                        <?php foreach ($results as $result): ?>
                                            <li><a href="?page=quran&surah=<?php echo $result['surah']; ?>&ayah=<?php echo $result['ayah']; ?>">Surah <?php echo $result['surah']; ?>, Ayah <?php echo $result['ayah']; ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p>No results found for "<?php echo htmlspecialchars($query); ?>".</p>
                                <?php endif;
                            } else {
                                echo '<p>Enter a query and click search to see results.</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <?php
                    break;

                case 'root_notes':
                    require_login();
                     $db = get_db();
                    $user_id = $_SESSION['user_id'];
                    $notes = [];
                    if ($db) {
                        $stmt = $db->prepare("SELECT * FROM root_notes WHERE user_id = :user_id ORDER BY root_word");
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ?>
                    <h2>My Root Notes</h2>
                     <form action="" method="post" style="margin-bottom: 20px;">
                         <input type="hidden" name="action" value="add_root_note">
                         <h4>Add New Root Note</h4>
                         <div>
                             <label for="root_word">Root Word (Arabic):</label>
                             <input type="text" id="root_word" name="root_word" required>
                         </div>
                         <div>
                             <label for="note">Note:</label>
                             <textarea name="note" id="note" placeholder="Enter your note about this root..."></textarea>
                         </div>
                         <button type="submit">Save Note</button>
                     </form>

                    <h3>Existing Notes</h3>
                    <?php if (!empty($notes)): ?>
                        <ul class="root-notes-list">
                            <?php foreach ($notes as $note): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($note['root_word']); ?>:</strong>
                                    <?php echo nl2br(htmlspecialchars($note['note'])); ?>
                                    <br><small>Created: <?php echo $note['created_at']; ?> | Updated: <?php echo $note['updated_at']; ?></small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>You haven't added any root notes yet.</p>
                    <?php endif; ?>
                    <?php
                    break;

                case 'recitation_logs':
                    require_login();
                    $db = get_db();
                    $user_id = $_SESSION['user_id'];
                    $logs = [];
                     if ($db) {
                        $stmt = $db->prepare("SELECT * FROM recitation_logs WHERE user_id = :user_id ORDER BY recited_at DESC");
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ?>
                    <h2>My Recitation Logs</h2>
                     <form action="" method="post" style="margin-bottom: 20px;">
                         <input type="hidden" name="action" value="add_recitation_log">
                         <h4>Add New Recitation Log</h4>
                         <div>
                             <label for="rec_surah">Surah:</label>
                             <select id="rec_surah" name="surah" required>
                                  <?php foreach ($surahs as $num => $data): ?>
                                     <option value="<?php echo $num; ?>">
                                         <?php echo $num; ?>. <?php echo htmlspecialchars($data['name_english']); ?>
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                         <div>
                             <label for="rec_ayah_start">Ayah Start:</label>
                             <input type="number" id="rec_ayah_start" name="ayah_start" min="1" value="1" required>
                         </div>
                         <div>
                             <label for="rec_ayah_end">Ayah End:</label>
                             <input type="number" id="rec_ayah_end" name="ayah_end" min="1" value="1" required>
                         </div>
                         <div>
                             <label for="rec_notes">Notes:</label>
                             <textarea name="notes" id="rec_notes" placeholder="Any notes about this recitation..."></textarea>
                         </div>
                         <button type="submit">Log Recitation</button>
                     </form>

                    <h3>Recent Logs</h3>
                    <?php if (!empty($logs)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Surah</th>
                                    <th>Ayahs</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['recited_at']; ?></td>
                                        <td><?php echo htmlspecialchars(get_surah_name($log['surah'])); ?> (<?php echo $log['surah']; ?>)</td>
                                        <td><?php echo $log['ayah_start']; ?> - <?php echo $log['ayah_end']; ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($log['notes'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>You haven't logged any recitations yet.</p>
                    <?php endif; ?>
                    <?php
                    break;

                case 'hifz_tracking':
                    require_login();
                    $db = get_db();
                    $user_id = $_SESSION['user_id'];
                    $hifz_entries = [];
                     if ($db) {
                        $stmt = $db->prepare("SELECT * FROM hifz_tracking WHERE user_id = :user_id ORDER BY surah, ayah_start");
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->execute();
                        $hifz_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                    ?>
                    <h2>My Hifz Tracking</h2>
                     <form action="" method="post" style="margin-bottom: 20px;">
                         <input type="hidden" name="action" value="add_hifz_tracking">
                         <h4>Add/Update Hifz Entry</h4>
                         <div>
                             <label for="hifz_surah">Surah:</label>
                             <select id="hifz_surah" name="surah" required>
                                  <?php foreach ($surahs as $num => $data): ?>
                                     <option value="<?php echo $num; ?>">
                                         <?php echo $num; ?>. <?php echo htmlspecialchars($data['name_english']); ?>
                                     </option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                         <div>
                             <label for="hifz_ayah_start">Ayah Start:</label>
                             <input type="number" id="hifz_ayah_start" name="ayah_start" min="1" value="1" required>
                         </div>
                         <div>
                             <label for="hifz_ayah_end">Ayah End:</label>
                             <input type="number" id="hifz_ayah_end" name="ayah_end" min="1" value="1" required>
                         </div>
                         <div>
                             <label for="hifz_status">Status:</label>
                             <select id="hifz_status" name="status" required>
                                 <option value="learning">Learning</option>
                                 <option value="memorized">Memorized</option>
                                 <option value="revision">Revision</option>
                             </select>
                         </div>
                         <button type="submit">Save Hifz Entry</button>
                     </form>

                    <h3>My Hifz Progress</h3>
                     <?php if (!empty($hifz_entries)): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Surah</th>
                                    <th>Ayahs</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($hifz_entries as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(get_surah_name($entry['surah'])); ?> (<?php echo $entry['surah']; ?>)</td>
                                        <td><?php echo $entry['ayah_start']; ?> - <?php echo $entry['ayah_end']; ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($entry['status'])); ?></td>
                                        <td><?php echo $entry['last_revised']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>You haven't added any hifz tracking entries yet.</p>
                    <?php endif; ?>
                    <?php
                    break;

                case 'admin_data':
                    require_admin();
                    ?>
                    <h2>Admin Data Management</h2>

                    <h3>Import Data Files</h3>
                    <p>Import <code>data5.AM</code> (Word Dictionary) and <code>data2.AM</code> (Ayah Word Mapping).</p>

                    <form action="" method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
                        <input type="hidden" name="action" value="import_data5">
                        <div>
                            <label for="data5_file">Upload data5.AM (Word Dictionary):</label>
                            <input type="file" id="data5_file" name="data5_file" accept=".csv" required>
                        </div>
                        <button type="submit">Import data5.AM</button>
                    </form>

                    <form action="" method="post" enctype="multipart/form-data" style="margin-bottom: 20px;">
                        <input type="hidden" name="action" value="import_data2">
                        <div>
                            <label for="data2_file">Upload data2.AM (Ayah Word Mapping):</label>
                            <input type="file" id="data2_file" name="data2_file" accept=".csv" required>
                        </div>
                        <button type="submit">Import data2.AM</button>
                    </form>

                    <h3>Database Backup & Restore</h3>
                    <form action="" method="post" style="display: inline-block; margin-right: 10px;">
                        <input type="hidden" name="action" value="backup_db">
                        <button type="submit"><i class="fas fa-download"></i> Backup Database</button>
                    </form>

                    <form action="" method="post" enctype="multipart/form-data" style="display: inline-block;">
                        <input type="hidden" name="action" value="restore_db">
                        <div>
                            <label for="restore_file" style="display: inline-block;">Restore Database:</label>
                            <input type="file" id="restore_file" name="restore_file" accept=".sqlite" required>
                        </div>
                        <button type="submit"><i class="fas fa-upload"></i> Restore Database</button>
                    </form>

                    <h3>User Management (Coming Soon)</h3>
                    <p>Future feature to manage users, roles, etc.</p>

                    <?php
                    break;

                default:
                    // Fallback to home page
                    header('Location: ?page=home');
                    exit();
            }
            ?>
        </div>
    </main>

    <footer>
        <p>© <?php echo date('Y'); ?> Quran Study Hub. Developed by Yasin Ullah.</p>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Flash message auto-hide
            const flashMessages = document.querySelectorAll('.flash-message');
            flashMessages.forEach(msg => {
                setTimeout(() => {
                    msg.classList.add('hide');
                    // Remove from DOM after transition
                    msg.addEventListener('transitionend', () => msg.remove());
                }, 5000); // Hide after 5 seconds
            });

            // Helper function for nl2br in JavaScript
             function nl2br(str) {
                 if (typeof str !== 'string') {
                     return str;
                 }
                 return str.replace(/\n/g, '<br>');
             }

             // Helper function for htmlspecialchars in JavaScript (basic)
             function htmlspecialchars(str) {
                 if (typeof str !== 'string') {
                     return str;
                 }
                 return str.replace(/&/g, '&')
                           .replace(/</g, '<')
                           .replace(/>/g, '>')
                           .replace(/"/g, '"')
                           .replace(/'/g, '\'');
             }
        });
    </script>

</body>
</html>