<?php
// Author: Yasin Ullah
// Pakistani

// Configuration
define('DB_PATH', __DIR__ . '/quran_study_hub2.sqlite');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'adminpass'); // CHANGE THIS IN PRODUCTION!
define('DEFAULT_USER_ROLE', 'user');
define('DEFAULT_ADMIN_ROLE', 'admin');

// Database Initialization
function init_db() {
    $db = new SQLite3(DB_PATH);
    $db->exec("PRAGMA foreign_keys = ON;");

    // Create tables if they don't exist
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT '" . DEFAULT_USER_ROLE . "'
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS personal_tafsir (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        surah INTEGER NOT NULL,
        ayah INTEGER NOT NULL,
        tafsir TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (user_id, surah, ayah)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS themes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (user_id, name)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS theme_ayahs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        theme_id INTEGER NOT NULL,
        surah INTEGER NOT NULL,
        ayah INTEGER NOT NULL,
        FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE,
        UNIQUE (theme_id, surah, ayah)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS root_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        root_word TEXT NOT NULL,
        note TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (user_id, root_word)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS recitation_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        surah INTEGER NOT NULL,
        ayah INTEGER NOT NULL,
        log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS hifz_tracking (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        surah INTEGER NOT NULL,
        ayah INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'not_started', -- not_started, learning, memorized, reviewing
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE (user_id, surah, ayah)
    )");

    // New tables for word-level data
    $db->exec("CREATE TABLE IF NOT EXISTS word_dictionary (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        quran_text TEXT UNIQUE NOT NULL, -- Arabic word with diacritics
        ur_meaning TEXT,
        en_meaning TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ayah_word_mapping (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        word_id INTEGER NOT NULL,
        surah INTEGER NOT NULL,
        ayah INTEGER NOT NULL,
        word_position INTEGER NOT NULL, -- 0-indexed position within the ayah
        FOREIGN KEY (word_id) REFERENCES word_dictionary(id) ON DELETE CASCADE,
        UNIQUE (surah, ayah, word_position)
    )");

    // Add default admin user if not exists
    // Add default admin user if not exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindValue(':username', ADMIN_USERNAME, SQLITE3_TEXT);
    $result = $stmt->execute();
    $count = $result->fetchArray()[0];
    // $result->finalize(); // Remove this line

    if ($count == 0) {
        $hashed_password = password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT);
        $stmt->finalize(); // Finalize the SELECT statement before preparing the INSERT statement
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
        $stmt->bindValue(':username', ADMIN_USERNAME, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
        $stmt->bindValue(':role', DEFAULT_ADMIN_ROLE, SQLITE3_TEXT);
        $stmt->execute();
        // $stmt->finalize(); // Keep this line
    }
    $stmt->finalize(); // Finalize the INSERT statement (or the SELECT statement if INSERT wasn't executed)

    $db->close();
}

// Session Management
session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_username() {
    return $_SESSION['username'] ?? null;
}

function get_user_role() {
    return $_SESSION['role'] ?? null;
}

function is_admin() {
    return get_user_role() === DEFAULT_ADMIN_ROLE;
}

function login($username, $password) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function logout() {
    session_unset();
    session_destroy();
}

// Data Handling Functions (CRUD for user data)

function get_personal_tafsir($user_id, $surah, $ayah) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT tafsir FROM personal_tafsir WHERE user_id = :user_id AND surah = :surah AND ayah = :ayah");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $tafsir = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    return $tafsir['tafsir'] ?? '';
}

function save_personal_tafsir($user_id, $surah, $ayah, $tafsir) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT OR REPLACE INTO personal_tafsir (user_id, surah, ayah, tafsir) VALUES (:user_id, :surah, :ayah, :tafsir)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $stmt->bindValue(':tafsir', $tafsir, SQLITE3_TEXT);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function get_themes($user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, name, description FROM themes WHERE user_id = :user_id ORDER BY name");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $themes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $themes[] = $row;
    }
    $result->finalize();
    $db->close();
    return $themes;
}

function create_theme($user_id, $name, $description) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT INTO themes (user_id, name, description) VALUES (:user_id, :name, :description)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':description', $description, SQLITE3_TEXT);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function delete_theme($user_id, $theme_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("DELETE FROM themes WHERE id = :theme_id AND user_id = :user_id");
    $stmt->bindValue(':theme_id', $theme_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function get_theme_ayahs($theme_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT surah, ayah FROM theme_ayahs WHERE theme_id = :theme_id ORDER BY surah, ayah");
    $stmt->bindValue(':theme_id', $theme_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $ayahs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row;
    }
    $result->finalize();
    $db->close();
    return $ayahs;
}

function add_ayah_to_theme($theme_id, $surah, $ayah) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT OR IGNORE INTO theme_ayahs (theme_id, surah, ayah) VALUES (:theme_id, :surah, :ayah)");
    $stmt->bindValue(':theme_id', $theme_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function remove_ayah_from_theme($theme_id, $surah, $ayah) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("DELETE FROM theme_ayahs WHERE theme_id = :theme_id AND surah = :surah AND ayah = :ayah");
    $stmt->bindValue(':theme_id', $theme_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function get_root_notes($user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, root_word, note FROM root_notes WHERE user_id = :user_id ORDER BY root_word");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $notes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $notes[] = $row;
    }
    $result->finalize();
    $db->close();
    return $notes;
}

function save_root_note($user_id, $root_word, $note) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT OR REPLACE INTO root_notes (user_id, root_word, note) VALUES (:user_id, :root_word, :note)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':root_word', $root_word, SQLITE3_TEXT);
    $stmt->bindValue(':note', $note, SQLITE3_TEXT);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function delete_root_note($user_id, $note_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("DELETE FROM root_notes WHERE id = :note_id AND user_id = :user_id");
    $stmt->bindValue(':note_id', $note_id, SQLITE3_INTEGER);
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function log_recitation($user_id, $surah, $ayah) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT INTO recitation_logs (user_id, surah, ayah) VALUES (:user_id, :surah, :ayah)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function get_recitation_history($user_id, $limit = 10) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT surah, ayah, log_time FROM recitation_logs WHERE user_id = :user_id ORDER BY log_time DESC LIMIT :limit");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $history = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $history[] = $row;
    }
    $result->finalize();
    $db->close();
    return $history;
}

function get_hifz_status($user_id, $surah, $ayah) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT status FROM hifz_tracking WHERE user_id = :user_id AND surah = :surah AND ayah = :ayah");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $status = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    return $status['status'] ?? 'not_started';
}

function update_hifz_status($user_id, $surah, $ayah, $status) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT OR REPLACE INTO hifz_tracking (user_id, surah, ayah, status) VALUES (:user_id, :surah, :ayah, :status)");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function get_hifz_summary($user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM hifz_tracking WHERE user_id = :user_id GROUP BY status");
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $summary = [
        'not_started' => 0,
        'learning' => 0,
        'memorized' => 0,
        'reviewing' => 0
    ];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $summary[$row['status']] = $row['count'];
    }
    $result->finalize();
    $db->close();
    return $summary;
}

// Admin Functions

function get_all_users() {
    if (!is_admin()) return [];
    $db = new SQLite3(DB_PATH);
    $result = $db->query("SELECT id, username, role FROM users");
    $users = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    $result->finalize();
    $db->close();
    return $users;
}

function delete_user($user_id) {
    if (!is_admin()) return false;
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("DELETE FROM users WHERE id = :user_id AND role != :admin_role"); // Prevent deleting the default admin via this function
    $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
    $stmt->bindValue(':admin_role', DEFAULT_ADMIN_ROLE, SQLITE3_TEXT);
    $success = $stmt->execute();
    $stmt->finalize();
    $db->close();
    return $success !== false;
}

function import_word_dictionary($filepath) {
    if (!is_admin() || !file_exists($filepath)) return ['success' => false, 'message' => 'File not found or not authorized.'];

    $db = new SQLite3(DB_PATH);
    $db->exec('BEGIN TRANSACTION;');
    $stmt = $db->prepare("INSERT OR IGNORE INTO word_dictionary (quran_text, ur_meaning, en_meaning) VALUES (:quran_text, :ur_meaning, :en_meaning)");

    $handle = fopen($filepath, "r");
    if ($handle === FALSE) {
        $db->exec('ROLLBACK;');
        $db->close();
        return ['success' => false, 'message' => 'Could not open file.'];
    }

    $header = fgetcsv($handle); // Skip header row
    $imported_count = 0;
    $errors = [];

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) >= 3) {
            $quran_text = trim($data[0]);
            $ur_meaning = trim($data[1]);
            $en_meaning = trim($data[2]);

            $stmt->bindValue(':quran_text', $quran_text, SQLITE3_TEXT);
            $stmt->bindValue(':ur_meaning', $ur_meaning, SQLITE3_TEXT);
            $stmt->bindValue(':en_meaning', $en_meaning, SQLITE3_TEXT);

            if ($stmt->execute()) {
                $imported_count++;
            } else {
                $errors[] = "Error importing word: " . htmlspecialchars($quran_text);
            }
        } else {
             $errors[] = "Skipping malformed row: " . implode(',', $data);
        }
    }

    fclose($handle);
    $stmt->finalize();

    if (empty($errors)) {
        $db->exec('COMMIT;');
        $db->close();
        return ['success' => true, 'message' => "Successfully imported $imported_count words."];
    } else {
        $db->exec('ROLLBACK;');
        $db->close();
        return ['success' => false, 'message' => "Import completed with errors. Imported $imported_count words. Errors: " . implode(', ', $errors)];
    }
}

function import_ayah_word_mapping($filepath) {
    if (!is_admin() || !file_exists($filepath)) return ['success' => false, 'message' => 'File not found or not authorized.'];

    $db = new SQLite3(DB_PATH);
    $db->exec('BEGIN TRANSACTION;');

    // Prepare statement to get word_id from quran_text
    $get_word_id_stmt = $db->prepare("SELECT id FROM word_dictionary WHERE quran_text = :quran_text");
    // Prepare statement to insert mapping
    $insert_mapping_stmt = $db->prepare("INSERT OR IGNORE INTO ayah_word_mapping (word_id, surah, ayah, word_position) VALUES (:word_id, :surah, :ayah, :word_position)");

    $handle = fopen($filepath, "r");
    if ($handle === FALSE) {
        $db->exec('ROLLBACK;');
        $db->close();
        return ['success' => false, 'message' => 'Could not open file.'];
    }

    $header = fgetcsv($handle); // Skip header row
    $imported_count = 0;
    $errors = [];

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) >= 4) {
            $quran_text = trim($data[0]);
            $surah = (int)$data[1];
            $ayah = (int)$data[2];
            $word_position = (int)$data[3];

            // Find the word_id
            $get_word_id_stmt->bindValue(':quran_text', $quran_text, SQLITE3_TEXT);
            $result = $get_word_id_stmt->execute();
            $word = $result->fetchArray(SQLITE3_ASSOC);
            $result->finalize();

            if ($word) {
                $word_id = $word['id'];

                // Insert the mapping
                $insert_mapping_stmt->bindValue(':word_id', $word_id, SQLITE3_INTEGER);
                $insert_mapping_stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
                $insert_mapping_stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
                $insert_mapping_stmt->bindValue(':word_position', $word_position, SQLITE3_INTEGER);

                if ($insert_mapping_stmt->execute()) {
                    $imported_count++;
                } else {
                    $errors[] = "Error importing mapping for word '" . htmlspecialchars($quran_text) . "' at $surah:$ayah:$word_position";
                }
            } else {
                $errors[] = "Word '" . htmlspecialchars($quran_text) . "' not found in dictionary for mapping at $surah:$ayah:$word_position";
            }
        } else {
             $errors[] = "Skipping malformed row: " . implode(',', $data);
        }
    }

    fclose($handle);
    $get_word_id_stmt->finalize();
    $insert_mapping_stmt->finalize();

    if (empty($errors)) {
        $db->exec('COMMIT;');
        $db->close();
        return ['success' => true, 'message' => "Successfully imported $imported_count word mappings."];
    } else {
        $db->exec('ROLLBACK;');
        $db->close();
        return ['success' => false, 'message' => "Import completed with errors. Imported $imported_count mappings. Errors: " . implode(', ', $errors)];
    }
}


// Quran Data Retrieval (Dynamic from word-level)

function get_surah_list() {
    // This should ideally come from a static list or a dedicated table if needed,
    // but for simplicity, we'll use a hardcoded list for now.
    // In a real app, you might have a 'surahs' table with name, number, etc.
    $surahs = [
        1 => ['name_arabic' => 'الفاتحة', 'name_english' => 'Al-Fatihah', 'ayahs' => 7],
        2 => ['name_arabic' => 'البقرة', 'name_english' => 'Al-Baqarah', 'ayahs' => 286],
        3 => ['name_arabic' => 'آل عمران', 'name_english' => 'Al-Imran', 'ayahs' => 200],
        4 => ['name_arabic' => 'النساء', 'name_english' => 'An-Nisa', 'ayahs' => 176],
        // Add more surahs as needed, up to 114
    ];
    return $surahs;
}

function get_ayah_words($surah, $ayah) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT wd.quran_text, awm.word_position
                          FROM ayah_word_mapping awm
                          JOIN word_dictionary wd ON awm.word_id = wd.id
                          WHERE awm.surah = :surah AND awm.ayah = :ayah
                          ORDER BY awm.word_position");
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $words = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $words[] = $row;
    }
    $result->finalize();
    $db->close();
    return $words;
}

function get_word_meaning($surah, $ayah, $word_position) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT wd.quran_text, wd.ur_meaning, wd.en_meaning
                          FROM ayah_word_mapping awm
                          JOIN word_dictionary wd ON awm.word_id = wd.id
                          WHERE awm.surah = :surah AND awm.ayah = :ayah AND awm.word_position = :word_position");
    $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
    $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
    $stmt->bindValue(':word_position', $word_position, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $meaning = $result->fetchArray(SQLITE3_ASSOC);
    $result->finalize();
    $db->close();
    return $meaning;
}

function search_quran($query) {
    $db = new SQLite3(DB_PATH);
    // Simple search for now: find ayahs containing any word from the query
    // A more advanced search would involve full-text search or stemming
    $search_terms = explode(' ', trim($query));
    $placeholders = implode(',', array_fill(0, count($search_terms), '?'));

    $sql = "SELECT DISTINCT awm.surah, awm.ayah
            FROM ayah_word_mapping awm
            JOIN word_dictionary wd ON awm.word_id = wd.id
            WHERE wd.quran_text IN ($placeholders)
            ORDER BY awm.surah, awm.ayah";

    $stmt = $db->prepare($sql);
    foreach ($search_terms as $index => $term) {
        $stmt->bindValue($index + 1, $term, SQLITE3_TEXT);
    }

    $result = $stmt->execute();
    $results = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $results[] = $row;
    }
    $result->finalize();
    $db->close();
    return $results;
}


// Backup and Restore Functions

function backup_data() {
    if (!is_logged_in()) return ['success' => false, 'message' => 'Not logged in.'];

    $user_id = get_user_id();
    $db = new SQLite3(DB_PATH);

    $backup_data = [
        'personal_tafsir' => [],
        'themes' => [],
        'theme_ayahs' => [],
        'root_notes' => [],
        'recitation_logs' => [],
        'hifz_tracking' => []
    ];

    // Fetch user-specific data
    $tables = ['personal_tafsir', 'themes', 'theme_ayahs', 'root_notes', 'recitation_logs', 'hifz_tracking'];
    foreach ($tables as $table) {
        // theme_ayahs needs special handling as it links to themes
        if ($table === 'theme_ayahs') {
             $stmt = $db->prepare("SELECT ta.theme_id, ta.surah, ta.ayah FROM theme_ayahs ta JOIN themes t ON ta.theme_id = t.id WHERE t.user_id = :user_id");
             $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        } else {
            $stmt = $db->prepare("SELECT * FROM $table WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
        }

        $result = $stmt->execute();
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $backup_data[$table][] = $row;
        }
        $result->finalize();
        $stmt->finalize();
    }

    $db->close();

    $backup_json = json_encode($backup_data, JSON_PRETTY_PRINT);
    if ($backup_json === false) {
         return ['success' => false, 'message' => 'Error encoding backup data: ' . json_last_error_msg()];
    }

    // Provide the JSON data for download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="quran_study_hub2_backup_' . get_username() . '_' . date('YmdHis') . '.json"');
    echo $backup_json;
    exit; // Stop further script execution after sending the file
}

function restore_data($filepath) {
    if (!is_logged_in() || !file_exists($filepath)) return ['success' => false, 'message' => 'File not found or not logged in.'];

    $user_id = get_user_id();
    $db = new SQLite3(DB_PATH);
    $db->exec('BEGIN TRANSACTION;');

    $backup_json = file_get_contents($filepath);
    $backup_data = json_decode($backup_json, true);

    if ($backup_data === null) {
        $db->exec('ROLLBACK;');
        $db->close();
        return ['success' => false, 'message' => 'Invalid JSON data in backup file.'];
    }

    try {
        // Clear existing user data (optional, but safer for a clean restore)
        // Consider if you want to merge or replace. Replacing is simpler.
        $db->exec("DELETE FROM personal_tafsir WHERE user_id = $user_id");
        $db->exec("DELETE FROM root_notes WHERE user_id = $user_id");
        $db->exec("DELETE FROM recitation_logs WHERE user_id = $user_id");
        $db->exec("DELETE FROM hifz_tracking WHERE user_id = $user_id");
        // Deleting themes will cascade delete theme_ayahs
        $db->exec("DELETE FROM themes WHERE user_id = $user_id");


        // Restore data
        if (isset($backup_data['personal_tafsir'])) {
            $stmt = $db->prepare("INSERT INTO personal_tafsir (user_id, surah, ayah, tafsir) VALUES (:user_id, :surah, :ayah, :tafsir)");
            foreach ($backup_data['personal_tafsir'] as $row) {
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':surah', $row['surah'], SQLITE3_INTEGER);
                $stmt->bindValue(':ayah', $row['ayah'], SQLITE3_INTEGER);
                $stmt->bindValue(':tafsir', $row['tafsir'], SQLITE3_TEXT);
                $stmt->execute();
            }
            $stmt->finalize();
        }

        // Restore themes first to get new theme IDs
        $old_to_new_theme_ids = [];
        if (isset($backup_data['themes'])) {
            $stmt = $db->prepare("INSERT INTO themes (user_id, name, description) VALUES (:user_id, :name, :description)");
            foreach ($backup_data['themes'] as $row) {
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':name', $row['name'], SQLITE3_TEXT);
                $stmt->bindValue(':description', $row['description'], SQLITE3_TEXT);
                $stmt->execute();
                $new_theme_id = $db->lastInsertRowID();
                $old_to_new_theme_ids[$row['id']] = $new_theme_id;
            }
            $stmt->finalize();
        }

        // Restore theme_ayahs using the new theme IDs
        if (isset($backup_data['theme_ayahs'])) {
            $stmt = $db->prepare("INSERT INTO theme_ayahs (theme_id, surah, ayah) VALUES (:theme_id, :surah, :ayah)");
            foreach ($backup_data['theme_ayahs'] as $row) {
                if (isset($old_to_new_theme_ids[$row['theme_id']])) {
                    $new_theme_id = $old_to_new_theme_ids[$row['theme_id']];
                    $stmt->bindValue(':theme_id', $new_theme_id, SQLITE3_INTEGER);
                    $stmt->bindValue(':surah', $row['surah'], SQLITE3_INTEGER);
                    $stmt->bindValue(':ayah', $row['ayah'], SQLITE3_INTEGER);
                    $stmt->execute();
                }
            }
            $stmt->finalize();
        }

        if (isset($backup_data['root_notes'])) {
            $stmt = $db->prepare("INSERT INTO root_notes (user_id, root_word, note) VALUES (:user_id, :root_word, :note)");
            foreach ($backup_data['root_notes'] as $row) {
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':root_word', $row['root_word'], SQLITE3_TEXT);
                $stmt->bindValue(':note', $row['note'], SQLITE3_TEXT);
                $stmt->execute();
            }
            $stmt->finalize();
        }

        if (isset($backup_data['recitation_logs'])) {
            $stmt = $db->prepare("INSERT INTO recitation_logs (user_id, surah, ayah, log_time) VALUES (:user_id, :surah, :ayah, :log_time)");
            foreach ($backup_data['recitation_logs'] as $row) {
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':surah', $row['surah'], SQLITE3_INTEGER);
                $stmt->bindValue(':ayah', $row['ayah'], SQLITE3_INTEGER);
                $stmt->bindValue(':log_time', $row['log_time'], SQLITE3_TEXT); // Assuming log_time is stored as text/datetime string
                $stmt->execute();
            }
            $stmt->finalize();
        }

        if (isset($backup_data['hifz_tracking'])) {
            $stmt = $db->prepare("INSERT INTO hifz_tracking (user_id, surah, ayah, status) VALUES (:user_id, :surah, :ayah, :status)");
            foreach ($backup_data['hifz_tracking'] as $row) {
                $stmt->bindValue(':user_id', $user_id, SQLITE3_INTEGER);
                $stmt->bindValue(':surah', $row['surah'], SQLITE3_INTEGER);
                $stmt->bindValue(':ayah', $row['ayah'], SQLITE3_INTEGER);
                $stmt->bindValue(':status', $row['status'], SQLITE3_TEXT);
                $stmt->execute();
            }
            $stmt->finalize();
        }

        $db->exec('COMMIT;');
        $db->close();
        return ['success' => true, 'message' => 'Data restored successfully.'];

    } catch (Exception $e) {
        $db->exec('ROLLBACK;');
        $db->close();
        return ['success' => false, 'message' => 'Error during restore: ' . $e->getMessage()];
    }
}


// Handle AJAX Requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'Invalid request.'];

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $user_id = get_user_id();

        switch ($action) {
            case 'get_word_meaning':
                if (isset($_POST['surah'], $_POST['ayah'], $_POST['pos'])) {
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    $pos = (int)$_POST['pos'];
                    $meaning = get_word_meaning($surah, $ayah, $pos);
                    if ($meaning) {
                        $response = ['success' => true, 'data' => $meaning];
                    } else {
                        $response = ['success' => false, 'message' => 'Meaning not found.'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Missing parameters for get_word_meaning.'];
                }
                break;

            case 'save_tafsir':
                if (is_logged_in() && isset($_POST['surah'], $_POST['ayah'], $_POST['tafsir'])) {
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    $tafsir = $_POST['tafsir'];
                    if (save_personal_tafsir($user_id, $surah, $ayah, $tafsir)) {
                        $response = ['success' => true, 'message' => 'Tafsir saved.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to save tafsir.'];
                    }
                } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                }
                break;

            case 'get_tafsir':
                 if (is_logged_in() && isset($_POST['surah'], $_POST['ayah'])) {
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    $tafsir = get_personal_tafsir($user_id, $surah, $ayah);
                    $response = ['success' => true, 'tafsir' => $tafsir];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'get_themes':
                 if (is_logged_in()) {
                    $themes = get_themes($user_id);
                    $response = ['success' => true, 'themes' => $themes];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in.'];
                 }
                 break;

            case 'create_theme':
                 if (is_logged_in() && isset($_POST['name'], $_POST['description'])) {
                    $name = trim($_POST['name']);
                    $description = trim($_POST['description']);
                    if (!empty($name)) {
                        if (create_theme($user_id, $name, $description)) {
                            $response = ['success' => true, 'message' => 'Theme created.'];
                        } else {
                            $response = ['success' => false, 'message' => 'Failed to create theme. Theme name might already exist.'];
                        }
                    } else {
                         $response = ['success' => false, 'message' => 'Theme name cannot be empty.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'delete_theme':
                 if (is_logged_in() && isset($_POST['theme_id'])) {
                    $theme_id = (int)$_POST['theme_id'];
                    if (delete_theme($user_id, $theme_id)) {
                        $response = ['success' => true, 'message' => 'Theme deleted.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to delete theme.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'get_theme_ayahs':
                 if (is_logged_in() && isset($_POST['theme_id'])) {
                    $theme_id = (int)$_POST['theme_id'];
                    $ayahs = get_theme_ayahs($theme_id);
                    $response = ['success' => true, 'ayahs' => $ayahs];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'add_ayah_to_theme':
                 if (is_logged_in() && isset($_POST['theme_id'], $_POST['surah'], $_POST['ayah'])) {
                    $theme_id = (int)$_POST['theme_id'];
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    if (add_ayah_to_theme($theme_id, $surah, $ayah)) {
                        $response = ['success' => true, 'message' => 'Ayah added to theme.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to add ayah to theme (might already be added).'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'remove_ayah_from_theme':
                 if (is_logged_in() && isset($_POST['theme_id'], $_POST['surah'], $_POST['ayah'])) {
                    $theme_id = (int)$_POST['theme_id'];
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    if (remove_ayah_from_theme($theme_id, $surah, $ayah)) {
                        $response = ['success' => true, 'message' => 'Ayah removed from theme.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to remove ayah from theme.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'get_root_notes':
                 if (is_logged_in()) {
                    $notes = get_root_notes($user_id);
                    $response = ['success' => true, 'notes' => $notes];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in.'];
                 }
                 break;

            case 'save_root_note':
                 if (is_logged_in() && isset($_POST['root_word'], $_POST['note'])) {
                    $root_word = trim($_POST['root_word']);
                    $note = $_POST['note'];
                     if (!empty($root_word)) {
                        if (save_root_note($user_id, $root_word, $note)) {
                            $response = ['success' => true, 'message' => 'Root note saved.'];
                        } else {
                            $response = ['success' => false, 'message' => 'Failed to save root note.'];
                        }
                    } else {
                         $response = ['success' => false, 'message' => 'Root word cannot be empty.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'delete_root_note':
                 if (is_logged_in() && isset($_POST['note_id'])) {
                    $note_id = (int)$_POST['note_id'];
                    if (delete_root_note($user_id, $note_id)) {
                        $response = ['success' => true, 'message' => 'Root note deleted.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to delete root note.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'log_recitation':
                 if (is_logged_in() && isset($_POST['surah'], $_POST['ayah'])) {
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    if (log_recitation($user_id, $surah, $ayah)) {
                        $response = ['success' => true, 'message' => 'Recitation logged.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to log recitation.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'get_recitation_history':
                 if (is_logged_in()) {
                    $history = get_recitation_history($user_id);
                    $response = ['success' => true, 'history' => $history];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in.'];
                 }
                 break;

            case 'get_hifz_status':
                 if (is_logged_in() && isset($_POST['surah'], $_POST['ayah'])) {
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    $status = get_hifz_status($user_id, $surah, $ayah);
                    $response = ['success' => true, 'status' => $status];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'update_hifz_status':
                 if (is_logged_in() && isset($_POST['surah'], $_POST['ayah'], $_POST['status'])) {
                    $surah = (int)$_POST['surah'];
                    $ayah = (int)$_POST['ayah'];
                    $status = $_POST['status']; // Validate status on server side if needed
                    if (update_hifz_status($user_id, $surah, $ayah, $status)) {
                        $response = ['success' => true, 'message' => 'Hifz status updated.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to update hifz status.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in or missing parameters.'];
                 }
                 break;

            case 'get_hifz_summary':
                 if (is_logged_in()) {
                    $summary = get_hifz_summary($user_id);
                    $response = ['success' => true, 'summary' => $summary];
                 } else {
                    $response = ['success' => false, 'message' => 'Not logged in.'];
                 }
                 break;

            case 'search_quran':
                 if (isset($_POST['query'])) {
                    $query = trim($_POST['query']);
                    if (!empty($query)) {
                        $results = search_quran($query);
                        $response = ['success' => true, 'results' => $results];
                    } else {
                        $response = ['success' => false, 'message' => 'Search query cannot be empty.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Missing search query.'];
                 }
                 break;

            // Admin AJAX actions
            case 'admin_get_users':
                 if (is_admin()) {
                    $users = get_all_users();
                    $response = ['success' => true, 'users' => $users];
                 } else {
                    $response = ['success' => false, 'message' => 'Unauthorized.'];
                 }
                 break;

            case 'admin_delete_user':
                 if (is_admin() && isset($_POST['user_id'])) {
                    $user_id_to_delete = (int)$_POST['user_id'];
                    if ($user_id_to_delete == get_user_id()) {
                         $response = ['success' => false, 'message' => 'Cannot delete your own account.'];
                    } elseif (delete_user($user_id_to_delete)) {
                        $response = ['success' => true, 'message' => 'User deleted.'];
                    } else {
                        $response = ['success' => false, 'message' => 'Failed to delete user.'];
                    }
                 } else {
                    $response = ['success' => false, 'message' => 'Unauthorized or missing parameters.'];
                 }
                 break;

            default:
                $response = ['success' => false, 'message' => 'Unknown AJAX action.'];
                break;
        }
    }

    echo json_encode($response);
    exit; // Stop script execution after AJAX response
}


// Handle Form Submissions (Non-AJAX)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {

    if (isset($_POST['login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        if (login($username, $password)) {
            header('Location: ?page=dashboard');
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    } elseif (isset($_POST['logout'])) {
        logout();
        header('Location: ?page=login');
        exit;
    } elseif (isset($_POST['admin_import_dictionary']) && is_admin()) {
        if (isset($_FILES['dictionary_file']) && $_FILES['dictionary_file']['error'] === UPLOAD_ERR_OK) {
            $filepath = $_FILES['dictionary_file']['tmp_name'];
            $result = import_word_dictionary($filepath);
            $admin_message = $result['message'];
        } else {
            $admin_message = "Error uploading dictionary file.";
        }
    } elseif (isset($_POST['admin_import_mapping']) && is_admin()) {
         if (isset($_FILES['mapping_file']) && $_FILES['mapping_file']['error'] === UPLOAD_ERR_OK) {
            $filepath = $_FILES['mapping_file']['tmp_name'];
            $result = import_ayah_word_mapping($filepath);
            $admin_message = $result['message'];
        } else {
            $admin_message = "Error uploading mapping file.";
        }
    } elseif (isset($_POST['backup_data']) && is_logged_in()) {
        backup_data(); // This function handles the download headers and exits
    } elseif (isset($_POST['restore_data']) && is_logged_in()) {
        if (isset($_FILES['restore_file']) && $_FILES['restore_file']['error'] === UPLOAD_ERR_OK) {
            $filepath = $_FILES['restore_file']['tmp_name'];
            $result = restore_data($filepath);
            $restore_message = $result['message'];
        } else {
            $restore_message = "Error uploading restore file.";
        }
    }
}

// Initialize the database on first run
init_db();

// Routing
$page = $_GET['page'] ?? 'home';

// Define available pages and required login status/role
$pages = [
    'home' => ['requires_login' => false, 'requires_admin' => false],
    'login' => ['requires_login' => false, 'requires_admin' => false],
    'dashboard' => ['requires_login' => true, 'requires_admin' => false],
    'quran' => ['requires_login' => true, 'requires_admin' => false],
    'tafsir' => ['requires_login' => true, 'requires_admin' => false],
    'themes' => ['requires_login' => true, 'requires_admin' => false],
    'roots' => ['requires_login' => true, 'requires_admin' => false],
    'recitation' => ['requires_login' => true, 'requires_admin' => false],
    'hifz' => ['requires_login' => true, 'requires_admin' => false],
    'search' => ['requires_login' => true, 'requires_admin' => false],
    'settings' => ['requires_login' => true, 'requires_admin' => false], // For backup/restore
    'admin' => ['requires_login' => true, 'requires_admin' => true],
];

// Check access
if (!isset($pages[$page])) {
    $page = 'home'; // Default to home if page not found
}

if ($pages[$page]['requires_login'] && !is_logged_in()) {
    $page = 'login'; // Redirect to login if required but not logged in
}

if ($pages[$page]['requires_admin'] && !is_admin()) {
    $page = 'dashboard'; // Redirect to dashboard if admin required but not admin
}

// --- HTML Output ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study Hub - <?php echo ucfirst($page); ?></title>
    <meta name="description" content="An advanced Quran study application with personal tafsir, thematic linking, root notes, hifz tracking, and word-by-word meanings.">
    <meta name="keywords" content="Quran, Islam, Study, Tafsir, Themes, Roots, Hifz, Memorization, Arabic, Word by Word, Yasin Ullah">
    <meta name="author" content="Yasin Ullah">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50; /* Green */
            --secondary-color: #8BC34A; /* Light Green */
            --accent-color: #FFC107; /* Amber */
            --background-color: #f4f7f6;
            --card-background: #ffffff;
            --text-color: #333;
            --border-color: #ddd;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --arabic-font: 'Scheherazade New', serif; /* Or other suitable Arabic fonts */
        }

        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap');

        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--background-color);
            margin: 0;
            padding-top: 60px; /* Space for fixed header */
            direction: ltr; /* Default direction */
        }

        .arabic-text {
            font-family: var(--arabic-font);
            direction: rtl;
            text-align: right;
            line-height: 2.5;
            font-size: 1.8em;
        }

        header {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px var(--shadow-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h1 {
            margin: 0;
            font-size: 1.5em;
        }

        nav ul {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
        }

        nav ul li {
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

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .card {
            background-color: var(--card-background);
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px var(--shadow-color);
        }

        h2 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--secondary-color);
            padding-bottom: 10px;
            margin-top: 0;
        }

        form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        form input[type="text"],
        form input[type="password"],
        form input[type="email"],
        form textarea,
        form select,
        form input[type="file"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-sizing: border-box; /* Include padding in width */
        }

        form button, .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        form button:hover, .btn:hover {
            background-color: var(--secondary-color);
        }

        .error {
            color: #f44336; /* Red */
            margin-bottom: 15px;
        }

        .success {
            color: #4CAF50; /* Green */
            margin-bottom: 15px;
        }

        /* Quran Viewer Styles */
        .quran-ayah {
            margin-bottom: 30px;
            border-bottom: 1px dashed var(--border-color);
            padding-bottom: 20px;
        }

        .ayah-number {
            font-size: 1.2em;
            font-weight: bold;
            color: var(--accent-color);
            margin-left: 10px;
        }

        .word {
            cursor: pointer;
            padding: 0 5px;
            border-bottom: 2px solid transparent;
            transition: border-bottom 0.2s ease, background-color 0.2s ease;
            display: inline-block; /* Allows padding and border */
        }

        .word:hover {
            background-color: rgba(var(--accent-color), 0.2);
            border-bottom-color: var(--accent-color);
        }

        .word-meaning-popup {
            position: absolute;
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 10px;
            box-shadow: 0 2px 10px var(--shadow-color);
            z-index: 100;
            max-width: 300px;
            pointer-events: none; /* Allow clicks to pass through to elements below */
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .word-meaning-popup.visible {
             opacity: 1;
             pointer-events: auto; /* Enable clicks when visible */
        }

        .word-meaning-popup h4 {
            margin-top: 0;
            margin-bottom: 5px;
            color: var(--primary-color);
        }

        .word-meaning-popup p {
            margin: 5px 0;
            font-size: 0.9em;
        }

        /* Context Menu */
        .context-menu {
            position: absolute;
            background-color: var(--card-background);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            box-shadow: 0 2px 10px var(--shadow-color);
            z-index: 200;
            list-style: none;
            margin: 0;
            padding: 5px 0;
            display: none; /* Hidden by default */
        }

        .context-menu li {
            padding: 8px 15px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .context-menu li:hover {
            background-color: var(--background-color);
        }

        /* Thematic Linking Styles */
        .theme-list {
            list-style: none;
            padding: 0;
        }

        .theme-item {
            background-color: var(--background-color);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .theme-item span {
            flex-grow: 1;
            margin-right: 10px;
        }

        .theme-item button {
            background-color: #f44336; /* Red */
            padding: 5px 10px;
        }
         .theme-item button:hover {
            background-color: #d32f2f; /* Darker Red */
        }

        .theme-ayahs-list {
            list-style: none;
            padding: 0;
            margin-top: 10px;
            font-size: 0.9em;
        }

        .theme-ayahs-list li {
            display: inline-block;
            margin-right: 10px;
            background-color: var(--secondary-color);
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
        }

        /* Root Notes Styles */
        .root-note-list {
            list-style: none;
            padding: 0;
        }

        .root-note-item {
            background-color: var(--background-color);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .root-note-item strong {
            color: var(--primary-color);
        }

        /* Hifz Tracking Styles */
        .hifz-status-selector {
            margin-left: 20px;
            padding: 5px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .hifz-summary {
            margin-top: 20px;
            padding: 15px;
            background-color: var(--background-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .hifz-summary p {
            margin: 5px 0;
        }

        /* Admin Styles */
        .user-list {
            list-style: none;
            padding: 0;
        }

        .user-item {
            background-color: var(--background-color);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-item span {
            flex-grow: 1;
            margin-right: 10px;
        }

         .user-item button {
            background-color: #f44336; /* Red */
            padding: 5px 10px;
        }
         .user-item button:hover {
            background-color: #d32f2f; /* Darker Red */
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            nav ul {
                flex-direction: column;
                align-items: center;
            }

            nav ul li {
                margin: 5px 0;
            }

            header {
                flex-direction: column;
                padding: 10px;
            }

            .container {
                padding: 0 10px;
            }
        }
    </style>
</head>
<body>

    <header>
        <h1>Quran Study Hub</h1>
        <nav>
            <ul>
                <?php if (is_logged_in()): ?>
                    <li><a href="?page=dashboard">Dashboard</a></li>
                    <li><a href="?page=quran">Quran</a></li>
                    <li><a href="?page=tafsir">Tafsir</a></li>
                    <li><a href="?page=themes">Themes</a></li>
                    <li><a href="?page=roots">Roots</a></li>
                    <li><a href="?page=recitation">Recitation</a></li>
                    <li><a href="?page=hifz">Hifz</a></li>
                    <li><a href="?page=search">Search</a></li>
                    <li><a href="?page=settings">Settings</a></li>
                    <?php if (is_admin()): ?>
                        <li><a href="?page=admin">Admin</a></li>
                    <?php endif; ?>
                    <li>
                        <form method="post" style="display:inline;">
                            <button type="submit" name="logout" class="btn" style="background: none; color: white; padding: 0; font-size: 1em;">Logout (<?php echo htmlspecialchars(get_username()); ?>)</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="?page=home">Home</a></li>
                    <li><a href="?page=login">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <div class="container">
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
         <?php if (isset($admin_message)): ?>
            <div class="<?php echo strpos($admin_message, 'Error') === false ? 'success' : 'error'; ?>"><?php echo $admin_message; ?></div>
        <?php endif; ?>
         <?php if (isset($restore_message)): ?>
            <div class="<?php echo strpos($restore_message, 'Error') === false ? 'success' : 'error'; ?>"><?php echo $restore_message; ?></div>
        <?php endif; ?>

        <?php
        // --- Page Content ---
        switch ($page) {
            case 'home':
                ?>
                <div class="card">
                    <h2>Welcome to Quran Study Hub</h2>
                    <p>Your personal platform for deeper Quranic study.</p>
                    <?php if (!is_logged_in()): ?>
                        <p><a href="?page=login">Login</a> or <a href="#">Register</a> (Registration not implemented yet)</p>
                    <?php else: ?>
                        <p>Welcome back, <?php echo htmlspecialchars(get_username()); ?>!</p>
                        <p><a href="?page=dashboard">Go to Dashboard</a></p>
                    <?php endif; ?>
                </div>
                <?php
                break;

            case 'login':
                ?>
                <div class="card">
                    <h2>Login</h2>
                    <form method="post">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>

                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>

                        <button type="submit" name="login">Login</button>
                    </form>
                </div>
                <?php
                break;

            case 'dashboard':
                ?>
                <div class="card">
                    <h2>Dashboard</h2>
                    <p>Welcome, <?php echo htmlspecialchars(get_username()); ?>!</p>

                    <h3>Your Study Progress</h3>
                    <?php $hifz_summary = get_hifz_summary(get_user_id()); ?>
                    <div class="hifz-summary">
                        <p><strong>Hifz Summary:</strong></p>
                        <p>Not Started: <?php echo $hifz_summary['not_started'] ?? 0; ?> Ayahs</p>
                        <p>Learning: <?php echo $hifz_summary['learning'] ?? 0; ?> Ayahs</p>
                        <p>Memorized: <?php echo $hifz_summary['memorized'] ?? 0; ?> Ayahs</p>
                        <p>Reviewing: <?php echo $hifz_summary['reviewing'] ?? 0; ?> Ayahs</p>
                    </div>

                     <h3>Recent Recitation</h3>
                     <?php $recitation_history = get_recitation_history(get_user_id(), 5); ?>
                     <?php if (!empty($recitation_history)): ?>
                         <ul>
                             <?php foreach ($recitation_history as $log): ?>
                                 <li>Recited Surah <?php echo $log['surah']; ?> Ayah <?php echo $log['ayah']; ?> on <?php echo date('Y-m-d H:i', strtotime($log['log_time'])); ?></li>
                             <?php endforeach; ?>
                         </ul>
                     <?php else: ?>
                         <p>No recent recitation logs.</p>
                     <?php endif; ?>

                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="?page=quran">Start Reading Quran</a></li>
                        <li><a href="?page=tafsir">View Your Tafsir Notes</a></li>
                        <li><a href="?page=themes">Manage Your Themes</a></li>
                        <li><a href="?page=roots">Explore Root Notes</a></li>
                        <li><a href="?page=hifz">Track Your Hifz</a></li>
                    </ul>
                </div>
                <?php
                break;

            case 'quran':
                $surah_list = get_surah_list();
                $current_surah = isset($_GET['s']) ? (int)$_GET['s'] : 1;
                $current_ayah = isset($_GET['a']) ? (int)$_GET['a'] : 1;

                // Validate surah and ayah
                if (!isset($surah_list[$current_surah])) {
                    $current_surah = 1;
                    $current_ayah = 1;
                }
                if ($current_ayah < 1 || $current_ayah > $surah_list[$current_surah]['ayahs']) {
                     $current_ayah = 1;
                }

                $next_ayah_link = '';
                $prev_ayah_link = '';
                $next_surah_link = '';
                $prev_surah_link = '';

                // Calculate navigation links
                if ($current_ayah < $surah_list[$current_surah]['ayahs']) {
                    $next_ayah_link = "?page=quran&s=$current_surah&a=" . ($current_ayah + 1);
                } else {
                    $next_surah = $current_surah + 1;
                    if (isset($surah_list[$next_surah])) {
                        $next_surah_link = "?page=quran&s=$next_surah&a=1";
                    }
                }

                if ($current_ayah > 1) {
                    $prev_ayah_link = "?page=quran&s=$current_surah&a=" . ($current_ayah - 1);
                } else {
                    $prev_surah = $current_surah - 1;
                    if (isset($surah_list[$prev_surah])) {
                        $prev_ayah_link = "?page=quran&s=$prev_surah&a=" . $surah_list[$prev_surah]['ayahs'];
                    }
                }

                $ayah_words = get_ayah_words($current_surah, $current_ayah);
                $hifz_status = get_hifz_status(get_user_id(), $current_surah, $current_ayah);
                ?>
                <div class="card">
                    <h2>Surah <?php echo $current_surah; ?>: <?php echo htmlspecialchars($surah_list[$current_surah]['name_english']); ?> (<?php echo htmlspecialchars($surah_list[$current_surah]['name_arabic']); ?>) - Ayah <?php echo $current_ayah; ?></h2>

                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <?php if ($prev_ayah_link): ?>
                                <a href="<?php echo $prev_ayah_link; ?>" class="btn">« Previous Ayah</a>
                            <?php endif; ?>
                        </div>
                        <div>
                             <?php if ($next_ayah_link): ?>
                                <a href="<?php echo $next_ayah_link; ?>" class="btn">Next Ayah »</a>
                            <?php elseif ($next_surah_link): ?>
                                <a href="<?php echo $next_surah_link; ?>" class="btn">Next Surah »</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="arabic-text quran-ayah" data-surah="<?php echo $current_surah; ?>" data-ayah="<?php echo $current_ayah; ?>">
                        <?php foreach ($ayah_words as $word): ?>
                            <span class="word" data-surah="<?php echo $current_surah; ?>" data-ayah="<?php echo $current_ayah; ?>" data-pos="<?php echo $word['word_position']; ?>">
                                <?php echo htmlspecialchars($word['quran_text']); ?>
                            </span>
                        <?php endforeach; ?>
                        <span class="ayah-number">(<?php echo $current_ayah; ?>)</span>
                    </div>

                    <div class="ayah-actions">
                        <button class="btn log-recitation-btn" data-surah="<?php echo $current_surah; ?>" data-ayah="<?php echo $current_ayah; ?>">Log Recitation</button>
                         <select class="hifz-status-selector" data-surah="<?php echo $current_surah; ?>" data-ayah="<?php echo $current_ayah; ?>">
                            <option value="not_started" <?php echo $hifz_status === 'not_started' ? 'selected' : ''; ?>>Not Started</option>
                            <option value="learning" <?php echo $hifz_status === 'learning' ? 'selected' : ''; ?>>Learning</option>
                            <option value="memorized" <?php echo $hifz_status === 'memorized' ? 'selected' : ''; ?>>Memorized</option>
                            <option value="reviewing" <?php echo $hifz_status === 'reviewing' ? 'selected' : ''; ?>>Reviewing</option>
                        </select>
                    </div>

                </div>

                <!-- Word Meaning Popup -->
                <div class="word-meaning-popup">
                    <h4>Word Meaning</h4>
                    <p><strong>Arabic:</strong> <span id="popup-arabic"></span></p>
                    <p><strong>Urdu:</strong> <span id="popup-urdu"></span></p>
                    <p><strong>English:</strong> <span id="popup-english"></span></p>
                </div>

                 <!-- Context Menu -->
                <ul class="context-menu">
                    <li data-action="add-to-theme">Add to Theme</li>
                    <li data-action="add-root-note">Add Root Note</li>
                    <li data-action="view-tafsir">View/Add Tafsir</li>
                </ul>

                <?php
                break;

            case 'tafsir':
                 ?>
                <div class="card">
                    <h2>Personal Tafsir</h2>
                    <p>View and manage your personal notes on Ayahs.</p>
                    <!-- This page could list ayahs with tafsir, or provide a search/browse interface -->
                    <p>Browse Tafsir by Surah and Ayah (Not fully implemented yet - use the Quran viewer to add/view tafsir for specific ayahs).</p>
                </div>
                <?php
                break;

            case 'themes':
                 ?>
                <div class="card">
                    <h2>Thematic Linking</h2>
                    <p>Organize Ayahs by themes you create.</p>

                    <h3>Your Themes</h3>
                    <ul class="theme-list" id="theme-list">
                        <?php
                        $themes = get_themes(get_user_id());
                        if (!empty($themes)):
                            foreach ($themes as $theme):
                                ?>
                                <li class="theme-item" data-theme-id="<?php echo $theme['id']; ?>">
                                    <span>
                                        <strong><?php echo htmlspecialchars($theme['name']); ?></strong>
                                        <?php if (!empty($theme['description'])): ?>
                                            <br><small><?php echo htmlspecialchars($theme['description']); ?></small>
                                        <?php endif; ?>
                                        <ul class="theme-ayahs-list">
                                            <?php
                                            $theme_ayahs = get_theme_ayahs($theme['id']);
                                            foreach ($theme_ayahs as $ayah):
                                                ?>
                                                <li><?php echo $ayah['surah']; ?>:<?php echo $ayah['ayah']; ?> <i class="fas fa-times remove-theme-ayah" data-surah="<?php echo $ayah['surah']; ?>" data-ayah="<?php echo $ayah['ayah']; ?>"></i></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </span>
                                    <button class="delete-theme-btn"><i class="fas fa-trash"></i> Delete</button>
                                </li>
                                <?php
                            endforeach;
                        else:
                            ?>
                            <li>No themes created yet.</li>
                        <?php endif; ?>
                    </ul>

                    <h3>Create New Theme</h3>
                    <form id="create-theme-form">
                        <label for="theme_name">Theme Name:</label>
                        <input type="text" id="theme_name" name="name" required>

                        <label for="theme_description">Description:</label>
                        <textarea id="theme_description" name="description"></textarea>

                        <button type="submit">Create Theme</button>
                    </form>
                </div>
                <?php
                break;

            case 'roots':
                 ?>
                <div class="card">
                    <h2>Root Notes</h2>
                    <p>Add and manage notes related to specific Arabic root words.</p>

                    <h3>Your Root Notes</h3>
                    <ul class="root-note-list" id="root-note-list">
                         <?php
                        $notes = get_root_notes(get_user_id());
                        if (!empty($notes)):
                            foreach ($notes as $note):
                                ?>
                                <li class="root-note-item" data-note-id="<?php echo $note['id']; ?>">
                                    <strong><?php echo htmlspecialchars($note['root_word']); ?>:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                    <button class="btn delete-root-note-btn" style="background-color: #f44336; padding: 5px 10px;"><i class="fas fa-trash"></i> Delete</button>
                                </li>
                                <?php
                            endforeach;
                        else:
                            ?>
                            <li>No root notes created yet.</li>
                        <?php endif; ?>
                    </ul>

                    <h3>Add New Root Note</h3>
                    <form id="save-root-note-form">
                        <label for="root_word">Root Word:</label>
                        <input type="text" id="root_word" name="root_word" required>

                        <label for="root_note">Note:</label>
                        <textarea id="root_note" name="note" required></textarea>

                        <button type="submit">Save Root Note</button>
                    </form>
                </div>
                <?php
                break;

            case 'recitation':
                 ?>
                <div class="card">
                    <h2>Recitation Log</h2>
                    <p>Track your Quran recitation progress.</p>

                    <h3>Recent Recitation History</h3>
                     <?php $recitation_history = get_recitation_history(get_user_id(), 20); ?>
                     <?php if (!empty($recitation_history)): ?>
                         <ul>
                             <?php foreach ($recitation_history as $log): ?>
                                 <li>Recited Surah <?php echo $log['surah']; ?> Ayah <?php echo $log['ayah']; ?> on <?php echo date('Y-m-d H:i', strtotime($log['log_time'])); ?></li>
                             <?php endforeach; ?>
                         </ul>
                     <?php else: ?>
                         <p>No recent recitation logs.</p>
                     <?php endif; ?>
                     <p>Recitation is logged automatically when you click the "Log Recitation" button on the Quran page.</p>
                </div>
                <?php
                break;

            case 'hifz':
                 ?>
                <div class="card">
                    <h2>Hifz Tracking</h2>
                    <p>Track your Quran memorization progress Ayah by Ayah.</p>

                    <h3>Your Hifz Summary</h3>
                    <?php $hifz_summary = get_hifz_summary(get_user_id()); ?>
                    <div class="hifz-summary">
                        <p>Not Started: <?php echo $hifz_summary['not_started'] ?? 0; ?> Ayahs</p>
                        <p>Learning: <?php echo $hifz_summary['learning'] ?? 0; ?> Ayahs</p>
                        <p>Memorized: <?php echo $hifz_summary['memorized'] ?? 0; ?> Ayahs</p>
                        <p>Reviewing: <?php echo $hifz_summary['reviewing'] ?? 0; ?> Ayahs</p>
                    </div>

                    <p>Update Hifz status for each Ayah directly on the <a href="?page=quran">Quran page</a>.</p>
                    <!-- Could add a table/list view of hifz status per surah/ayah here -->
                </div>
                <?php
                break;

            case 'search':
                 ?>
                <div class="card">
                    <h2>Advanced Search</h2>
                    <p>Search the Quran text for words or phrases.</p>

                    <form id="quran-search-form">
                        <label for="search_query">Search Query:</label>
                        <input type="text" id="search_query" name="query" required>
                        <button type="submit">Search</button>
                    </form>

                    <div id="search-results" style="margin-top: 20px;">
                        <!-- Search results will be loaded here via AJAX -->
                    </div>
                </div>
                <?php
                break;

            case 'settings':
                 ?>
                <div class="card">
                    <h2>Settings</h2>
                    <p>Manage your account and data.</p>

                    <h3>Backup Your Data</h3>
                    <p>Download a JSON file containing your personal tafsir, themes, root notes, recitation logs, and hifz tracking.</p>
                    <form method="post">
                        <button type="submit" name="backup_data">Download Backup</button>
                    </form>

                    <h3>Restore Your Data</h3>
                    <p>Upload a backup JSON file to restore your personal data. This will replace your current data.</p>
                    <form method="post" enctype="multipart/form-data">
                        <label for="restore_file">Choose Backup File:</label>
                        <input type="file" id="restore_file" name="restore_file" accept=".json" required>
                        <button type="submit" name="restore_data">Restore Data</button>
                    </form>
                </div>
                <?php
                break;

            case 'admin':
                if (is_admin()):
                ?>
                <div class="card">
                    <h2>Admin Panel</h2>
                    <p>Manage users and import core Quran data.</p>

                    <h3>Manage Users</h3>
                    <ul class="user-list" id="user-list">
                        <?php
                        $users = get_all_users();
                        foreach ($users as $user):
                            ?>
                            <li class="user-item" data-user-id="<?php echo $user['id']; ?>">
                                <span><?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['role']); ?>)</span>
                                <?php if ($user['role'] !== DEFAULT_ADMIN_ROLE): ?>
                                    <button class="delete-user-btn"><i class="fas fa-trash"></i> Delete</button>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <h3>Import Quran Data</h3>
                    <p>Import the word dictionary (data6.AM) and ayah word mapping (word2.AM) CSV files.</p>

                    <h4>Import Word Dictionary (data6.AM)</h4>
                    <form method="post" enctype="multipart/form-data">
                        <label for="dictionary_file">Choose data6.AM CSV:</label>
                        <input type="file" id="dictionary_file" name="dictionary_file" accept=".csv" required>
                        <button type="submit" name="admin_import_dictionary">Import Dictionary</button>
                    </form>

                    <h4>Import Ayah Word Mapping (word2.AM)</h4>
                    <form method="post" enctype="multipart/form-data">
                        <label for="mapping_file">Choose word2.AM CSV:</label>
                        <input type="file" id="mapping_file" name="mapping_file" accept=".csv" required>
                        <button type="submit" name="admin_import_mapping">Import Mapping</button>
                    </form>
                </div>
                <?php
                endif;
                break;

            default:
                // Should not happen due to routing logic, but as a fallback
                ?>
                <div class="card">
                    <h2>Page Not Found</h2>
                    <p>The requested page could not be found.</p>
                </div>
                <?php
                break;
        }
        ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // --- AJAX Helper ---
            function ajaxRequest(action, data, onSuccess, onError) {
                const formData = new FormData();
                formData.append('action', action);
                for (const key in data) {
                    formData.append(key, data[key]);
                }

                fetch('', { // Fetch to the same file
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest' // Identify as AJAX
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        onSuccess(data);
                    } else {
                        onError(data.message || 'An unknown error occurred.');
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    onError('Request failed: ' + error.message);
                });
            }

            // --- Word Meaning Popup ---
            const wordMeaningPopup = document.querySelector('.word-meaning-popup');
            const popupArabic = document.getElementById('popup-arabic');
            const popupUrdu = document.getElementById('popup-urdu');
            const popupEnglish = document.getElementById('popup-english');
            let popupTimeout;

            document.querySelectorAll('.word').forEach(wordSpan => {
                wordSpan.addEventListener('mouseover', (e) => {
                    clearTimeout(popupTimeout); // Clear any pending hide
                    const surah = wordSpan.dataset.surah;
                    const ayah = wordSpan.dataset.ayah;
                    const pos = wordSpan.dataset.pos;

                    // Position the popup near the word
                    const rect = wordSpan.getBoundingClientRect();
                    wordMeaningPopup.style.top = `${rect.bottom + window.scrollY + 5}px`;
                    wordMeaningPopup.style.left = `${rect.left + window.scrollX}px`;

                    // Fetch and display meaning
                    ajaxRequest('get_word_meaning', { surah: surah, ayah: ayah, pos: pos },
                        (response) => {
                            popupArabic.textContent = response.data.quran_text;
                            popupUrdu.textContent = response.data.ur_meaning || 'N/A';
                            popupEnglish.textContent = response.data.en_meaning || 'N/A';
                            wordMeaningPopup.classList.add('visible');
                        },
                        (message) => {
                            console.error('Error fetching meaning:', message);
                            // Optionally display an error in the popup
                            popupArabic.textContent = 'Error';
                            popupUrdu.textContent = message;
                            popupEnglish.textContent = '';
                            wordMeaningPopup.classList.add('visible');
                        }
                    );
                });

                wordSpan.addEventListener('mouseout', () => {
                    // Hide the popup after a short delay
                    popupTimeout = setTimeout(() => {
                        wordMeaningPopup.classList.remove('visible');
                    }, 300); // Adjust delay as needed
                });
            });

             // Keep popup visible when hovering over the popup itself
            wordMeaningPopup.addEventListener('mouseover', () => {
                clearTimeout(popupTimeout);
                wordMeaningPopup.classList.add('visible');
            });

            wordMeaningPopup.addEventListener('mouseout', () => {
                 popupTimeout = setTimeout(() => {
                    wordMeaningPopup.classList.remove('visible');
                }, 300);
            });


            // --- Context Menu ---
            const contextMenu = document.querySelector('.context-menu');
            let currentAyahData = null; // To store surah/ayah/pos of the clicked word

            document.querySelectorAll('.word').forEach(wordSpan => {
                wordSpan.addEventListener('contextmenu', (e) => {
                    e.preventDefault(); // Prevent default browser context menu

                    currentAyahData = {
                        surah: wordSpan.dataset.surah,
                        ayah: wordSpan.dataset.ayah,
                        pos: wordSpan.dataset.pos,
                        word: wordSpan.textContent.trim() // Store the Arabic word
                    };

                    // Position the context menu
                    contextMenu.style.top = `${e.clientY + window.scrollY}px`;
                    contextMenu.style.left = `${e.clientX + window.scrollX}px`;
                    contextMenu.style.display = 'block';
                });
            });

            // Hide context menu when clicking elsewhere
            document.addEventListener('click', (e) => {
                if (!contextMenu.contains(e.target)) {
                    contextMenu.style.display = 'none';
                    currentAyahData = null;
                }
            });

            // Handle context menu actions
            contextMenu.querySelectorAll('li').forEach(item => {
                item.addEventListener('click', () => {
                    const action = item.dataset.action;
                    contextMenu.style.display = 'none'; // Hide menu after selection

                    if (!currentAyahData) return; // Should not happen if menu was visible

                    switch (action) {
                        case 'add-to-theme':
                            // Prompt user to select/create theme (simplified for now)
                            // In a real app, you'd show a modal with theme options
                            const themeId = prompt("Enter Theme ID to add this Ayah (Surah " + currentAyahData.surah + " Ayah " + currentAyahData.ayah + "):");
                            if (themeId) {
                                ajaxRequest('add_ayah_to_theme', { theme_id: themeId, surah: currentAyahData.surah, ayah: currentAyahData.ayah },
                                    (response) => { alert(response.message); location.reload(); }, // Reload to see changes
                                    (message) => { alert('Error: ' + message); }
                                );
                            }
                            break;
                        case 'add-root-note':
                             // Prompt user for note (simplified)
                             const rootNote = prompt("Add note for root word '" + currentAyahData.word + "':");
                             if (rootNote !== null) { // Allow empty note to clear
                                ajaxRequest('save_root_note', { root_word: currentAyahData.word, note: rootNote },
                                    (response) => { alert(response.message); location.reload(); }, // Reload to see changes
                                    (message) => { alert('Error: ' + message); }
                                );
                             }
                            break;
                        case 'view-tafsir':
                            // Redirect or show modal for tafsir
                            // For now, redirect to tafsir page (basic)
                            // window.location.href = `?page=tafsir&s=${currentAyahData.surah}&a=${currentAyahData.ayah}`;
                            // Or fetch and show tafsir in a modal/sidebar
                            ajaxRequest('get_tafsir', { surah: currentAyahData.surah, ayah: currentAyahData.ayah },
                                (response) => {
                                    const tafsirText = response.tafsir || 'No personal tafsir yet.';
                                    alert(`Personal Tafsir for ${currentAyahData.surah}:${currentAyahData.ayah}:\n\n${tafsirText}\n\n(You can add/edit this on the Tafsir page or via a dedicated UI element)`);
                                },
                                (message) => { alert('Error fetching tafsir: ' + message); }
                            );
                            break;
                    }
                    currentAyahData = null; // Clear data after action
                });
            });


            // --- Ayah Actions ---
            document.querySelectorAll('.log-recitation-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const surah = button.dataset.surah;
                    const ayah = button.dataset.ayah;
                    ajaxRequest('log_recitation', { surah: surah, ayah: ayah },
                        (response) => { alert(response.message); },
                        (message) => { alert('Error logging recitation: ' + message); }
                    );
                });
            });

            document.querySelectorAll('.hifz-status-selector').forEach(select => {
                select.addEventListener('change', (e) => {
                    const surah = select.dataset.surah;
                    const ayah = select.dataset.ayah;
                    const status = e.target.value;
                    ajaxRequest('update_hifz_status', { surah: surah, ayah: ayah, status: status },
                        (response) => { console.log(response.message); }, // Maybe update a local summary count
                        (message) => { alert('Error updating hifz status: ' + message); }
                    );
                });
            });

            // --- Themes Page ---
            const createThemeForm = document.getElementById('create-theme-form');
            if (createThemeForm) {
                createThemeForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(createThemeForm);
                    const name = formData.get('name');
                    const description = formData.get('description');

                    ajaxRequest('create_theme', { name: name, description: description },
                        (response) => { alert(response.message); location.reload(); }, // Reload to see new theme
                        (message) => { alert('Error creating theme: ' + message); }
                    );
                });
            }

            document.querySelectorAll('.delete-theme-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const themeItem = button.closest('.theme-item');
                    const themeId = themeItem.dataset.themeId;
                    if (confirm('Are you sure you want to delete this theme and all its linked ayahs?')) {
                        ajaxRequest('delete_theme', { theme_id: themeId },
                            (response) => { alert(response.message); themeItem.remove(); },
                            (message) => { alert('Error deleting theme: ' + message); }
                        );
                    }
                });
            });

             document.querySelectorAll('.remove-theme-ayah').forEach(icon => {
                icon.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevent triggering parent theme item click
                    const themeItem = icon.closest('.theme-item');
                    const themeId = themeItem.dataset.themeId;
                    const surah = icon.dataset.surah;
                    const ayah = icon.dataset.ayah;
                     if (confirm(`Remove Ayah ${surah}:${ayah} from this theme?`)) {
                        ajaxRequest('remove_ayah_from_theme', { theme_id: themeId, surah: surah, ayah: ayah },
                            (response) => { alert(response.message); icon.parentElement.remove(); }, // Remove the ayah list item
                            (message) => { alert('Error removing ayah: ' + message); }
                        );
                     }
                });
            });


            // --- Root Notes Page ---
            const saveRootNoteForm = document.getElementById('save-root-note-form');
            if (saveRootNoteForm) {
                saveRootNoteForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(saveRootNoteForm);
                    const root_word = formData.get('root_word');
                    const note = formData.get('note');

                    ajaxRequest('save_root_note', { root_word: root_word, note: note },
                        (response) => { alert(response.message); location.reload(); }, // Reload to see new note
                        (message) => { alert('Error saving root note: ' + message); }
                    );
                });
            }

             document.querySelectorAll('.delete-root-note-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const noteItem = button.closest('.root-note-item');
                    const noteId = noteItem.dataset.noteId;
                    if (confirm('Are you sure you want to delete this root note?')) {
                        ajaxRequest('delete_root_note', { note_id: noteId },
                            (response) => { alert(response.message); noteItem.remove(); },
                            (message) => { alert('Error deleting root note: ' + message); }
                        );
                    }
                });
            });


            // --- Search Page ---
            const quranSearchForm = document.getElementById('quran-search-form');
            const searchResultsDiv = document.getElementById('search-results');
            if (quranSearchForm) {
                quranSearchForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(quranSearchForm);
                    const query = formData.get('query');

                    searchResultsDiv.innerHTML = '<p>Searching...</p>';

                    ajaxRequest('search_quran', { query: query },
                        (response) => {
                            if (response.results.length > 0) {
                                let html = '<h3>Search Results:</h3><ul>';
                                response.results.forEach(result => {
                                    html += `<li><a href="?page=quran&s=${result.surah}&a=${result.ayah}">Surah ${result.surah}: Ayah ${result.ayah}</a></li>`;
                                });
                                html += '</ul>';
                                searchResultsDiv.innerHTML = html;
                            } else {
                                searchResultsDiv.innerHTML = '<p>No results found.</p>';
                            }
                        },
                        (message) => {
                            searchResultsDiv.innerHTML = `<p class="error">Search failed: ${message}</p>`;
                        }
                    );
                });
            }

            // --- Admin Page ---
            document.querySelectorAll('.delete-user-btn').forEach(button => {
                button.addEventListener('click', () => {
                    const userItem = button.closest('.user-item');
                    const userId = userItem.dataset.userId;
                    if (confirm('Are you sure you want to delete this user and ALL their data? This cannot be undone.')) {
                        ajaxRequest('admin_delete_user', { user_id: userId },
                            (response) => { alert(response.message); userItem.remove(); },
                            (message) => { alert('Error deleting user: ' + message); }
                        );
                    }
                });
            });

        });
    </script>

</body>
</html>