
<?php
/**
 * Responsive Quranic Study Web App
 * Author: Yasin Ullah (Pakistani)
 * Description: A single PHP file application for Quranic study with multi-user roles,
 * responsive design, and integrated SQLite database for Quranic text, translations,
 * tafsir, and user-generated content. Includes essential backup/restore functionality.
 *
 * This file handles all routing, database interactions, HTML rendering, CSS, and JavaScript.
 */

session_start(); // Start PHP session for user authentication

// --- Configuration ---
define('DB_PATH', __DIR__ . '/database.sqlite'); // Path to the SQLite database file
define('DATA_DIR', __DIR__ . '/data');           // Directory for initial data files (data.AM, dataENG.AM, etc.)
define('UPLOAD_DIR', __DIR__ . '/uploads');       // Directory for admin uploads (Tafsir sets, DB backups)

// --- Helper Functions ---

/**
 * Establishes and returns a PDO database connection to SQLite.
 * Ensures error mode is set to exceptions for robust error handling.
 * @return PDO
 */
function get_db_connection() {
    try {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Enable foreign key support for SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        return $pdo;
    } catch (PDOException $e) {
        // In a real application, log this error securely and display a user-friendly message.
        // For a single-file app, a direct die is acceptable for critical errors during startup.
        die("Database connection error: " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Creates database tables and loads initial data from specified files on first run.
 * This function is idempotent: it uses INSERT OR IGNORE and CREATE TABLE IF NOT EXISTS
 * to prevent issues on repeated execution if parts already exist.
 * @param PDO $pdo The PDO database connection.
 */
function create_tables_and_load_initial_data($pdo) {
    // 1. Define all table creation SQL statements
    $sql_tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            email TEXT UNIQUE,
            role TEXT NOT NULL DEFAULT 'public', -- 'public', 'registered', 'ulama', 'admin'
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )",
        "CREATE TABLE IF NOT EXISTS quran_ayahs (
            surah_id INTEGER NOT NULL,
            ayah_id INTEGER NOT NULL,
            arabic_text TEXT NOT NULL,
            PRIMARY KEY (surah_id, ayah_id)
        )",
        "CREATE TABLE IF NOT EXISTS full_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            translation_name TEXT NOT NULL,
            language_code TEXT NOT NULL UNIQUE
        )",
        "CREATE TABLE IF NOT EXISTS ayah_translations (
            surah_id INTEGER NOT NULL,
            ayah_id INTEGER NOT NULL,
            translation_id INTEGER NOT NULL,
            text TEXT NOT NULL,
            PRIMARY KEY (surah_id, ayah_id, translation_id),
            FOREIGN KEY (surah_id, ayah_id) REFERENCES quran_ayahs(surah_id, ayah_id) ON DELETE CASCADE,
            FOREIGN KEY (translation_id) REFERENCES full_translations(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS word_by_word_translations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            quran_text TEXT NOT NULL UNIQUE, -- The Arabic word itself
            ur_meaning TEXT,
            en_meaning,
            is_public BOOLEAN DEFAULT 1, -- Can be made public by Admin/Ulama
            approved_by_user_id INTEGER, -- Who approved it
            FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS tafsir_sets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            author TEXT NOT NULL,
            is_public BOOLEAN DEFAULT 1 -- Global visibility for the set
        )",
        "CREATE TABLE IF NOT EXISTS ayah_tafsir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            surah_id INTEGER NOT NULL,
            ayah_id INTEGER NOT NULL,
            tafsir_set_id INTEGER, -- Nullable if it's a general public contribution not part of a set
            user_id INTEGER, -- Who contributed/edited (if not part of a set)
            text TEXT NOT NULL,
            is_public BOOLEAN DEFAULT 0, -- Needs approval if from Registered user, or 1 if directly added by Ulama/Admin
            status TEXT DEFAULT 'pending', -- 'private', 'pending', 'approved', 'rejected'
            FOREIGN KEY (surah_id, ayah_id) REFERENCES quran_ayahs(surah_id, ayah_id) ON DELETE CASCADE,
            FOREIGN KEY (tafsir_set_id) REFERENCES tafsir_sets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )",
        "CREATE TABLE IF NOT EXISTS user_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            surah_id INTEGER NOT NULL,
            ayah_id INTEGER NOT NULL,
            note_text TEXT NOT NULL,
            is_private BOOLEAN DEFAULT 1, -- True for personal notes
            status TEXT DEFAULT 'private', -- 'private', 'pending', 'approved', 'rejected'
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (surah_id, ayah_id) REFERENCES quran_ayahs(surah_id, ayah_id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS user_themes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            css_rules TEXT NOT NULL,
            is_public BOOLEAN DEFAULT 0,
            status TEXT DEFAULT 'private', -- 'private', 'pending', 'approved', 'rejected'
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS root_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            root_word TEXT NOT NULL,
            note_text TEXT NOT NULL,
            is_public BOOLEAN DEFAULT 0,
            status TEXT DEFAULT 'private', -- 'private', 'pending', 'approved', 'rejected'
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS recitation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            surah_id INTEGER NOT NULL,
            ayah_id_start INTEGER NOT NULL,
            ayah_id_end INTEGER NOT NULL,
            log_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS hifz_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            surah_id INTEGER NOT NULL,
            ayah_id_start INTEGER NOT NULL,
            ayah_id_end INTEGER NOT NULL,
            memorized_date DATETIME,
            status TEXT DEFAULT 'not_memorized', -- 'not_memorized', 'memorized', 'revising'
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )",
        "CREATE TABLE IF NOT EXISTS approval_queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            content_type TEXT NOT NULL, -- e.g., 'user_note', 'ayah_tafsir', 'word_by_word_translation', 'user_theme', 'root_note'
            content_id INTEGER NOT NULL,
            submitted_by_user_id INTEGER NOT NULL,
            reviewed_by_user_id INTEGER, -- Nullable until reviewed
            status TEXT DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
            submission_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            review_date DATETIME,
            FOREIGN KEY (submitted_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    ];

    foreach ($sql_tables as $sql) {
        $pdo->exec($sql);
    }

    // 2. Insert default Admin user if not exists
    $admin_username = 'admin';
    // !!! IMPORTANT: CHANGE THIS PASSWORD IN PRODUCTION !!!
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (username, password_hash, role, email) VALUES (?, ?, ?, ?)");
    $stmt->execute([$admin_username, $admin_password, 'admin', 'admin@quranapp.com']);

    // 3. Load full Ayah translations (data.AM, dataENG.AM, dataBNG.AM)
    $translations_to_load = [
        'AM' => ['translation_name' => 'Urdu Translation', 'language_code' => 'ur'],
        'ENG.AM' => ['translation_name' => 'English Translation', 'language_code' => 'en'],
        'BNG.AM' => ['translation_name' => 'Bengali Translation', 'language_code' => 'bn']
    ];

    foreach ($translations_to_load as $file_suffix => $info) {
        $file_path = DATA_DIR . '/data' . $file_suffix;
        if (file_exists($file_path)) {
            $pdo->beginTransaction();
            try {
                // Insert translation type if not exists, then get its ID
                $stmt = $pdo->prepare("INSERT INTO full_translations (translation_name, language_code) VALUES (?, ?) ON CONFLICT(language_code) DO UPDATE SET translation_name=excluded.translation_name");
                $stmt->execute([$info['translation_name'], $info['language_code']]);
                $stmt = $pdo->prepare("SELECT id FROM full_translations WHERE language_code = ?");
                $stmt->execute([$info['language_code']]);
                $translation_id = $stmt->fetchColumn();

                $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    // Example: بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيمِ ترجمہ: شروع اللہ کے نام سے جو بڑا مہربان نہایت رحم والا ہے<br/>س 001 آ 001
                    // Regex: (.*?) for Arabic, (.*?) for translation, (\d+) for surah, (\d+) for ayah
                    if (preg_match('/^(.*?) ترجمہ: (.*?)<br\/>س (\d+) آ (\d+)$/u', $line, $matches)) {
                        $arabic_text = trim($matches[1]);
                        $translation_text = trim($matches[2]);
                        $surah_id = (int)$matches[3];
                        $ayah_id = (int)$matches[4];

                        // Insert Arabic text into quran_ayahs (will ignore if already exists due to PRIMARY KEY)
                        $stmt_arabic = $pdo->prepare("INSERT OR IGNORE INTO quran_ayahs (surah_id, ayah_id, arabic_text) VALUES (?, ?, ?)");
                        $stmt_arabic->execute([$surah_id, $ayah_id, $arabic_text]);

                        // Insert Ayah translation
                        $stmt_translation = $pdo->prepare("INSERT OR IGNORE INTO ayah_translations (surah_id, ayah_id, translation_id, text) VALUES (?, ?, ?, ?)");
                        $stmt_translation->execute([$surah_id, $ayah_id, $translation_id, $translation_text]);
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Error loading full translations from " . $file_path . ": " . $e->getMessage());
            }
        } else {
            error_log("Data file not found: " . $file_path);
        }
    }

    // 4. Load Word-by-Word Translations (data5.AM)
    $wbw_file = DATA_DIR . '/data5.AM';
    if (file_exists($wbw_file)) {
        $pdo->beginTransaction();
        try {
            $handle = fopen($wbw_file, "r");
            if ($handle) {
                fgetcsv($handle); // Skip header row: quran_text,ur_meaning,en_meaning
                while (($data = fgetcsv($handle)) !== FALSE) {
                    if (count($data) >= 3) { // Ensure enough columns
                        $quran_text = trim($data[0]);
                        $ur_meaning = trim($data[1]);
                        $en_meaning = trim($data[2]);
                        $stmt = $pdo->prepare("INSERT OR IGNORE INTO word_by_word_translations (quran_text, ur_meaning, en_meaning) VALUES (?, ?, ?)");
                        $stmt->execute([$quran_text, $ur_meaning, $en_meaning]);
                    }
                }
                fclose($handle);
            }
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Error loading word-by-word translations from " . $wbw_file . ": " . $e->getMessage());
        }
    } else {
        error_log("Word-by-word data file not found: " . $wbw_file);
    }
}

/**
 * Checks if a user is currently logged in.
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Gets the current user's role. Defaults to 'public' if not logged in.
 * @return string
 */
function get_user_role() {
    return $_SESSION['user_role'] ?? 'public';
}

/**
 * Checks if the current user has one of the required roles.
 * @param array $required_roles An array of roles that are allowed (e.g., ['admin', 'ulama']).
 * @return bool
 */
function has_permission($required_roles) {
    $current_role = get_user_role();
    return in_array($current_role, $required_roles);
}

// --- Initialize Database and Directories (on first run or if missing) ---
// Ensure necessary directories exist
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Check if database file exists. If not, create tables and load initial data.
if (!file_exists(DB_PATH)) {
    // Attempt to create database and load data
    $pdo_init = null; // Declare to prevent issues if connection fails before assignment
    try {
        $pdo_init = get_db_connection();
        create_tables_and_load_initial_data($pdo_init);
        // It's crucial to correctly populate DATA_DIR with the provided .AM files before running the app.
        // If the files are not present, initial data loading for Quran text and translations will fail.
        error_log("Database created and initial data loaded successfully.");
    } catch (Exception $e) {
        error_log("FATAL ERROR: Could not initialize database or load initial data: " . $e->getMessage());
        // Clean up partial DB file if creation failed? Maybe just let it fail.
        die("Application setup failed. Please check server logs and ensure data files are in the 'data' directory.");
    } finally {
        $pdo_init = null; // Close connection used for initialization
    }
}

// Establish the main PDO connection for the current request cycle
$pdo = get_db_connection();

// --- Handle AJAX Requests ---
// All AJAX requests are handled by checking the 'action' POST parameter.
if (isset($_POST['action'])) {
    header('Content-Type: application/json'); // Respond with JSON
    $response = ['success' => false, 'message' => 'Invalid action or missing parameters.']; // Default error response

    switch ($_POST['action']) {
        case 'login':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $response = ['success' => true, 'message' => 'Login successful!', 'role' => $user['role']];
            } else {
                $response = ['success' => false, 'message' => 'Invalid username or password.'];
            }
            break;

        case 'register':
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $email = $_POST['email'] ?? '';

            if (empty($username) || empty($password) || empty($email)) {
                $response = ['success' => false, 'message' => 'All fields are required.'];
                break;
            }
            if (strlen($password) < 6) {
                $response = ['success' => false, 'message' => 'Password must be at least 6 characters.'];
                break;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response = ['success' => false, 'message' => 'Invalid email format.'];
                break;
            }

            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, 'registered')");
                $stmt->execute([$username, $password_hash, $email]);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['user_role'] = 'registered';
                $response = ['success' => true, 'message' => 'Registration successful! You are now logged in.', 'role' => 'registered'];
            } catch (PDOException $e) {
                if ($e->getCode() == '23000') { // SQLite unique constraint violation
                    $response = ['success' => false, 'message' => 'Username or email already exists.'];
                } else {
                    $response = ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
                    error_log("Registration PDO error: " . $e->getMessage());
                }
            }
            break;

        case 'logout':
            session_unset();
            session_destroy();
            $response = ['success' => true, 'message' => 'Logged out successfully.'];
            break;

        case 'load_surah_ayah':
            $surah_id = (int)($_POST['surah_id'] ?? 1);
            $ayah_id = (int)($_POST['ayah_id'] ?? 1);
            $translation_id = (int)($_POST['translation_id'] ?? 1);

            // Fetch basic Ayah info, Arabic text, and selected translation
            $stmt = $pdo->prepare("SELECT
                                    qa.arabic_text,
                                    at.text AS translation_text
                                FROM
                                    quran_ayahs qa
                                JOIN
                                    ayah_translations at ON qa.surah_id = at.surah_id AND qa.ayah_id = at.ayah_id
                                WHERE
                                    qa.surah_id = ? AND qa.ayah_id = ? AND at.translation_id = ?");
            $stmt->execute([$surah_id, $ayah_id, $translation_id]);
            $ayah_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$ayah_data) {
                $response = ['success' => false, 'message' => 'Ayah not found or translation missing for Surah ' . $surah_id . ' Ayah ' . $ayah_id . ' with translation ' . $translation_id . '.'];
                break;
            }

            // Get selected translation name
            $stmt = $pdo->prepare("SELECT translation_name FROM full_translations WHERE id = ?");
            $stmt->execute([$translation_id]);
            $selected_translation_name = $stmt->fetchColumn();

            // Get word-by-word meanings
            $arabic_words = preg_split('/[ ]+/u', $ayah_data['arabic_text'], -1, PREG_SPLIT_NO_EMPTY); // Split by one or more spaces
            $word_by_word_data = [];
            if (!empty($arabic_words)) {
                $placeholders = implode(',', array_fill(0, count($arabic_words), '?'));
                $stmt = $pdo->prepare("SELECT quran_text, ur_meaning, en_meaning FROM word_by_word_translations WHERE quran_text IN (" . $placeholders . ")");
                $stmt->execute($arabic_words);
                $wbw_results = $stmt->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC); // Get results keyed by quran_text

                foreach ($arabic_words as $word) {
                    $meaning = $wbw_results[$word] ?? ['ur_meaning' => 'N/A', 'en_meaning' => 'N/A'];
                    $word_by_word_data[] = [
                        'word' => $word,
                        'ur_meaning' => $meaning['ur_meaning'],
                        'en_meaning' => $meaning['en_meaning']
                    ];
                }
            }

            // Get Tafsir (public and personal if applicable)
            $tafsir_data = [];
            // Public tafsir
            $stmt = $pdo->prepare("SELECT at.text, ts.name AS tafsir_name, ts.author
                                FROM ayah_tafsir at
                                LEFT JOIN tafsir_sets ts ON at.tafsir_set_id = ts.id
                                WHERE at.surah_id = ? AND at.ayah_id = ? AND at.is_public = 1 AND at.status = 'approved'");
            $stmt->execute([$surah_id, $ayah_id]);
            $tafsir_data['public'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Personal notes/tafsir (only for logged-in user)
            if (is_logged_in()) {
                $stmt = $pdo->prepare("SELECT note_text, is_private, status FROM user_notes
                                    WHERE user_id = ? AND surah_id = ? AND ayah_id = ?
                                    AND (is_private = 1 OR (is_private = 0 AND status = 'approved'))");
                $stmt->execute([$_SESSION['user_id'], $surah_id, $ayah_id]);
                $tafsir_data['personal'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Get all available translation options for the dropdown
            $stmt = $pdo->query("SELECT id, translation_name FROM full_translations ORDER BY id ASC");
            $translation_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response = [
                'success' => true,
                'surah_id' => $surah_id,
                'ayah_id' => $ayah_id,
                'arabic_text' => $ayah_data['arabic_text'],
                'translation_text' => $ayah_data['translation_text'],
                'selected_translation_name' => $selected_translation_name,
                'word_by_word' => $word_by_word_data,
                'tafsir' => $tafsir_data,
                'translation_options' => $translation_options
            ];
            break;

        case 'save_note':
            if (!is_logged_in()) {
                $response = ['success' => false, 'message' => 'Login required.'];
                break;
            }
            $user_id = $_SESSION['user_id'];
            $surah_id = (int)$_POST['surah_id'];
            $ayah_id = (int)$_POST['ayah_id'];
            $note_text = trim($_POST['note_text'] ?? '');
            $is_private = isset($_POST['is_private']) ? (int)$_POST['is_private'] : 1; // 1 for private, 0 for public

            if (empty($note_text)) {
                $response = ['success' => false, 'message' => 'Note text cannot be empty.'];
                break;
            }

            $status = ($is_private == 1) ? 'private' : 'pending';

            $pdo->beginTransaction();
            try {
                // Check if a note already exists for this ayah/user (specifically private ones, as they are editable)
                $stmt = $pdo->prepare("SELECT id FROM user_notes WHERE user_id = ? AND surah_id = ? AND ayah_id = ? AND is_private = 1");
                $stmt->execute([$user_id, $surah_id, $ayah_id]);
                $existing_note_id = $stmt->fetchColumn();

                if ($existing_note_id) {
                    // Update existing private note
                    $stmt = $pdo->prepare("UPDATE user_notes SET note_text = ?, is_private = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$note_text, $is_private, $status, $existing_note_id]);
                    $response = ['success' => true, 'message' => 'Note updated successfully.'];
                } else {
                    // Insert new note
                    $stmt = $pdo->prepare("INSERT INTO user_notes (user_id, surah_id, ayah_id, note_text, is_private, status) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $surah_id, $ayah_id, $note_text, $is_private, $status]);
                    $new_note_id = $pdo->lastInsertId();
                    $response = ['success' => true, 'message' => 'Note saved successfully.'];

                    // If submitted for public (is_private == 0), add to approval queue
                    if ($is_private == 0 && has_permission(['registered'])) { // Only registered can submit for public approval
                         $stmt = $pdo->prepare("INSERT INTO approval_queue (content_type, content_id, submitted_by_user_id) VALUES ('user_note', ?, ?)");
                         $stmt->execute([$new_note_id, $user_id]);
                         $response['message'] .= " It has been submitted for approval.";
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Failed to save note: ' . $e->getMessage()];
                error_log("Save Note error: " . $e->getMessage());
            }
            break;

        case 'get_note':
            if (!is_logged_in()) {
                $response = ['success' => false, 'message' => 'Login required.'];
                break;
            }
            $user_id = $_SESSION['user_id'];
            $surah_id = (int)$_POST['surah_id'];
            $ayah_id = (int)$_POST['ayah_id'];

            // Fetch a private note for the user for this ayah
            $stmt = $pdo->prepare("SELECT note_text, is_private FROM user_notes WHERE user_id = ? AND surah_id = ? AND ayah_id = ? AND is_private = 1");
            $stmt->execute([$user_id, $surah_id, $ayah_id]);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($note) {
                $response = ['success' => true, 'note_text' => $note['note_text'], 'is_private' => $note['is_private']];
            } else {
                $response = ['success' => true, 'note_text' => '', 'is_private' => 1]; // No private note found, return empty private note setup
            }
            break;

        case 'admin_import_tafsir':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }

            if (!isset($_FILES['tafsir_file']) || $_FILES['tafsir_file']['error'] !== UPLOAD_ERR_OK) {
                $response = ['success' => false, 'message' => 'No file uploaded or upload error.'];
                break;
            }

            $file_info = $_FILES['tafsir_file'];
            $file_ext = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
            $temp_path = $file_info['tmp_name'];

            if ($file_ext === 'csv') {
                $tafsir_set_name = trim($_POST['tafsir_set_name_csv'] ?? 'Imported Tafsir (CSV)');
                $tafsir_author = trim($_POST['tafsir_author_csv'] ?? 'Unknown');

                if (empty($tafsir_set_name) || empty($tafsir_author)) {
                    $response = ['success' => false, 'message' => 'Tafsir set name and author cannot be empty for CSV import.'];
                    break;
                }

                $pdo->beginTransaction();
                try {
                    // Insert or get Tafsir Set ID
                    $stmt = $pdo->prepare("INSERT INTO tafsir_sets (name, author, is_public) VALUES (?, ?, 1) ON CONFLICT(name) DO UPDATE SET author=excluded.author");
                    $stmt->execute([$tafsir_set_name, $tafsir_author]);
                    $stmt = $pdo->prepare("SELECT id FROM tafsir_sets WHERE name = ?");
                    $stmt->execute([$tafsir_set_name]);
                    $tafsir_set_id = $stmt->fetchColumn();

                    if (($handle = fopen($temp_path, "r")) !== FALSE) {
                        $header = fgetcsv($handle); // Skip header: surah,ayah,tafsir_text,tafsir_name,author
                        while (($data = fgetcsv($handle, 0, ",")) !== FALSE) { // Read entire line
                            if (count($data) >= 3) { // Ensure at least surah, ayah, tafsir_text exist
                                $surah = (int)($data[0] ?? 0);
                                $ayah = (int)($data[1] ?? 0);
                                $text = trim($data[2] ?? '');

                                if ($surah > 0 && $ayah > 0 && !empty($text)) {
                                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO ayah_tafsir (surah_id, ayah_id, tafsir_set_id, text, is_public, status) VALUES (?, ?, ?, ?, 1, 'approved')");
                                    $stmt->execute([$surah, $ayah, $tafsir_set_id, $text]);
                                } else {
                                    error_log("Skipping malformed CSV Tafsir row: " . implode(',', $data));
                                }
                            }
                        }
                        fclose($handle);
                    }
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'CSV Tafsir imported successfully.'];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $response = ['success' => false, 'message' => 'CSV Tafsir import failed: ' . $e->getMessage()];
                    error_log("CSV Tafsir import error: " . $e->getMessage());
                }
            } elseif ($file_ext === 'json') {
                $json_content = file_get_contents($temp_path);
                $tafsir_data = json_decode($json_content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = ['success' => false, 'message' => 'Invalid JSON file: ' . json_last_error_msg()];
                    break;
                }
                if (!is_array($tafsir_data)) {
                    $response = ['success' => false, 'message' => 'JSON file must contain an array of Tafsir objects.'];
                    break;
                }

                $tafsir_set_name = trim($_POST['tafsir_set_name_json'] ?? 'Imported Tafsir (JSON)');
                $tafsir_author = trim($_POST['tafsir_author_json'] ?? 'Unknown');

                if (empty($tafsir_set_name) || empty($tafsir_author)) {
                    $response = ['success' => false, 'message' => 'Tafsir set name and author cannot be empty for JSON import.'];
                    break;
                }

                $pdo->beginTransaction();
                try {
                    // Insert or get Tafsir Set ID
                    $stmt = $pdo->prepare("INSERT INTO tafsir_sets (name, author, is_public) VALUES (?, ?, 1) ON CONFLICT(name) DO UPDATE SET author=excluded.author");
                    $stmt->execute([$tafsir_set_name, $tafsir_author]);
                    $stmt = $pdo->prepare("SELECT id FROM tafsir_sets WHERE name = ?");
                    $stmt->execute([$tafsir_set_name]);
                    $tafsir_set_id = $stmt->fetchColumn();

                    foreach ($tafsir_data as $entry) {
                        if (isset($entry['surah'], $entry['ayah'], $entry['text'])) {
                            $surah = (int)$entry['surah'];
                            $ayah = (int)$entry['ayah'];
                            $text = trim($entry['text']);
                            // Optional: Tafsir_name and author from JSON entry could override set defaults if needed,
                            // but for consistent sets, we use the form input.
                            if ($surah > 0 && $ayah > 0 && !empty($text)) {
                                $stmt = $pdo->prepare("INSERT OR REPLACE INTO ayah_tafsir (surah_id, ayah_id, tafsir_set_id, text, is_public, status) VALUES (?, ?, ?, ?, 1, 'approved')");
                                $stmt->execute([$surah, $ayah, $tafsir_set_id, $text]);
                            } else {
                                error_log("Skipping malformed JSON Tafsir entry: " . json_encode($entry));
                            }
                        }
                    }
                    $pdo->commit();
                    $response = ['success' => true, 'message' => 'JSON Tafsir imported successfully.'];
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $response = ['success' => false, 'message' => 'JSON Tafsir import failed: ' . $e->getMessage()];
                    error_log("JSON Tafsir import error: " . $e->getMessage());
                }
            } else {
                $response = ['success' => false, 'message' => 'Unsupported file type. Only CSV and JSON are allowed.'];
            }
            break;

        case 'admin_backup_db':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $backup_filename = 'database_backup_' . date('Ymd_His') . '.sqlite';
            $backup_file_path = UPLOAD_DIR . '/' . $backup_filename;

            try {
                // Ensure the PDO connection is not busy with other transactions during copy
                $pdo = null; // Close current PDO connection

                if (copy(DB_PATH, $backup_file_path)) {
                    $response = ['success' => true, 'message' => 'Database backed up successfully.', 'file_name' => $backup_filename];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to create backup file. Check server permissions for ' . UPLOAD_DIR];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()];
                error_log("DB Backup error: " . $e->getMessage());
            } finally {
                // Re-establish the database connection after backup operation
                $pdo = get_db_connection();
            }
            break;

        case 'admin_restore_db':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                $response = ['success' => false, 'message' => 'No backup file uploaded or upload error.'];
                break;
            }
            $uploaded_file = $_FILES['backup_file']['tmp_name'];
            $uploaded_filename = $_FILES['backup_file']['name'];

            if (pathinfo($uploaded_filename, PATHINFO_EXTENSION) !== 'sqlite') {
                $response = ['success' => false, 'message' => 'Invalid file type. Only .sqlite files are allowed for restore.'];
                break;
            }

            try {
                // Close PDO connection before replacing the file
                $pdo = null;

                if (copy($uploaded_file, DB_PATH)) {
                    // Re-establish connection immediately for subsequent operations in this request
                    $pdo = get_db_connection();
                    $response = ['success' => true, 'message' => 'Database restored successfully. The application will refresh.'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to restore database. Check file permissions.'];
                }
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Restore failed: ' . $e->getMessage()];
                error_log("DB Restore error: " . $e->getMessage());
            } finally {
                // If restore failed, $pdo might be null, ensure it's re-initialized for page render.
                if ($pdo === null) {
                   $pdo = get_db_connection();
                }
            }
            break;

        case 'get_surah_list':
            $stmt = $pdo->query("SELECT DISTINCT surah_id FROM quran_ayahs ORDER BY surah_id ASC");
            $surah_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response = ['success' => true, 'surahs' => $surah_ids];
            break;

        case 'get_ayah_list':
            $surah_id = (int)($_POST['surah_id'] ?? 0);
            if ($surah_id <= 0) {
                $response = ['success' => false, 'message' => 'Invalid Surah ID.'];
                break;
            }
            $stmt = $pdo->prepare("SELECT DISTINCT ayah_id FROM quran_ayahs WHERE surah_id = ? ORDER BY ayah_id ASC");
            $stmt->execute([$surah_id]);
            $ayah_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $response = ['success' => true, 'ayahs' => $ayah_ids];
            break;

        case 'get_pending_approvals':
            if (!has_permission(['ulama', 'admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $stmt = $pdo->prepare("SELECT
                                    aq.id, aq.content_type, aq.content_id, aq.submission_date,
                                    u.username AS submitted_by_username
                                FROM
                                    approval_queue aq
                                JOIN
                                    users u ON aq.submitted_by_user_id = u.id
                                WHERE
                                    aq.status = 'pending' ORDER BY aq.submission_date ASC");
            $stmt->execute();
            $pending_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch details for each item based on content_type
            foreach ($pending_items as &$item) {
                if ($item['content_type'] == 'user_note') {
                    $stmt_detail = $pdo->prepare("SELECT surah_id, ayah_id, note_text FROM user_notes WHERE id = ?");
                    $stmt_detail->execute([$item['content_id']]);
                    $item['details'] = $stmt_detail->fetch(PDO::FETCH_ASSOC);
                }
                // TODO: Add more content types (e.g., 'ayah_tafsir', 'word_by_word_translation', 'user_theme', 'root_note')
                // For 'ayah_tafsir' and 'word_by_word_translation' contributed by 'registered' users, they would appear here
                // Note: The problem statement says Ulama/Admin can *directly* contribute/edit public WBW meanings and Tafsir,
                // implying those won't necessarily go through approval queue if done by Ulama/Admin.
            }
            unset($item); // Break the reference

            $response = ['success' => true, 'pending_items' => $pending_items];
            break;

        case 'approve_content':
        case 'reject_content':
            if (!has_permission(['ulama', 'admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $approval_id = (int)$_POST['approval_id'];
            $new_status = ($_POST['action'] == 'approve_content') ? 'approved' : 'rejected';
            $reviewer_id = $_SESSION['user_id'];

            $pdo->beginTransaction();
            try {
                // Get content details from approval_queue first
                $stmt = $pdo->prepare("SELECT content_type, content_id FROM approval_queue WHERE id = ?");
                $stmt->execute([$approval_id]);
                $item = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$item) {
                    $response = ['success' => false, 'message' => 'Approval item not found.'];
                    $pdo->rollBack();
                    break;
                }

                // Update content table based on content_type
                switch ($item['content_type']) {
                    case 'user_note':
                        // If approved, it becomes public (is_private=0, status='approved'). If rejected, it stays private (is_private=1, status='private').
                        $is_private_value = ($new_status == 'approved') ? 0 : 1;
                        $stmt = $pdo->prepare("UPDATE user_notes SET status = ?, is_private = ? WHERE id = ?");
                        $stmt->execute([$new_status, $is_private_value, $item['content_id']]);
                        break;
                    case 'ayah_tafsir':
                        $is_public_value = ($new_status == 'approved') ? 1 : 0;
                        $stmt = $pdo->prepare("UPDATE ayah_tafsir SET status = ?, is_public = ? WHERE id = ?");
                        $stmt->execute([$new_status, $is_public_value, $item['content_id']]);
                        break;
                    // TODO: Add cases for 'word_by_word_translation', 'user_theme', 'root_note'
                    default:
                        $response = ['success' => false, 'message' => 'Unsupported content type for approval.'];
                        $pdo->rollBack();
                        break 2; // Break outer switch
                }

                // Finally, update approval_queue status
                $stmt = $pdo->prepare("UPDATE approval_queue SET status = ?, reviewed_by_user_id = ?, review_date = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$new_status, $reviewer_id, $approval_id]);

                $response = ['success' => true, 'message' => 'Content ' . $new_status . ' successfully.'];
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Failed to ' . $new_status . ' content: ' . $e->getMessage()];
                error_log("Approval/Rejection error: " . $e->getMessage());
            }
            break;

        case 'edit_public_word':
            if (!has_permission(['ulama', 'admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $quran_text = trim($_POST['quran_text'] ?? '');
            $ur_meaning = trim($_POST['ur_meaning'] ?? '');
            $en_meaning = trim($_POST['en_meaning'] ?? '');
            $approved_by = $_SESSION['user_id'];

            if (empty($quran_text)) {
                $response = ['success' => false, 'message' => 'Arabic word cannot be empty.'];
                break;
            }

            try {
                // INSERT OR REPLACE ensures that if `quran_text` exists, its meanings are updated.
                // This means Ulama/Admin can directly edit the public word meanings.
                $stmt = $pdo->prepare("INSERT OR REPLACE INTO word_by_word_translations (quran_text, ur_meaning, en_meaning, is_public, approved_by_user_id) VALUES (?, ?, ?, 1, ?)");
                $stmt->execute([$quran_text, $ur_meaning, $en_meaning, $approved_by]);
                $response = ['success' => true, 'message' => 'Word-by-word meaning updated/added.'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Failed to save word meaning: ' . $e->getMessage()];
                error_log("Edit Word-by-Word error: " . $e->getMessage());
            }
            break;

        case 'add_ayah_tafsir_public':
            if (!has_permission(['ulama', 'admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $surah_id = (int)$_POST['surah_id'];
            $ayah_id = (int)$_POST['ayah_id'];
            $tafsir_text = trim($_POST['tafsir_text'] ?? '');
            $tafsir_set_name = trim($_POST['tafsir_set_name'] ?? 'General Public Tafsir');
            $tafsir_author = trim($_POST['tafsir_author'] ?? $_SESSION['username']);
            $user_id = $_SESSION['user_id'];

            if (empty($tafsir_text) || $surah_id <= 0 || $ayah_id <= 0) {
                $response = ['success' => false, 'message' => 'Surah, Ayah, and Tafsir text are required.'];
                break;
            }

            $pdo->beginTransaction();
            try {
                // Get or create Tafsir Set
                // Using INSERT OR IGNORE and then SELECT for existing sets, or a direct ON CONFLICT clause
                $stmt = $pdo->prepare("INSERT INTO tafsir_sets (name, author, is_public) VALUES (?, ?, 1) ON CONFLICT(name) DO UPDATE SET author=excluded.author");
                $stmt->execute([$tafsir_set_name, $tafsir_author]);
                $stmt = $pdo->prepare("SELECT id FROM tafsir_sets WHERE name = ?");
                $stmt->execute([$tafsir_set_name]);
                $tafsir_set_id = $stmt->fetchColumn();

                // Insert Ayah Tafsir. For public tafsir added by Ulama/Admin, it's directly approved.
                // Allow multiple Tafsir entries for the same Ayah (from different sets/authors)
                $stmt = $pdo->prepare("INSERT INTO ayah_tafsir (surah_id, ayah_id, tafsir_set_id, user_id, text, is_public, status) VALUES (?, ?, ?, ?, ?, 1, 'approved')");
                $stmt->execute([$surah_id, $ayah_id, $tafsir_set_id, $user_id, $tafsir_text]);
                $response = ['success' => true, 'message' => 'Public Tafsir added successfully.'];
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $response = ['success' => false, 'message' => 'Failed to add public Tafsir: ' . $e->getMessage()];
                error_log("Add Public Tafsir error: " . $e->getMessage());
            }
            break;

        case 'search_quran':
            $search_term = trim($_POST['search_term'] ?? '');
            if (empty($search_term)) {
                $response = ['success' => false, 'message' => 'Search term cannot be empty.'];
                break;
            }

            $results = [];
            $search_param = '%' . $search_term . '%';

            // Search in Arabic text
            $stmt = $pdo->prepare("SELECT surah_id, ayah_id, arabic_text FROM quran_ayahs WHERE arabic_text LIKE ? LIMIT 20");
            $stmt->execute([$search_param]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'type' => 'Arabic Text',
                    'surah_id' => $row['surah_id'],
                    'ayah_id' => $row['ayah_id'],
                    'text' => $row['arabic_text']
                ];
            }

            // Search in English translation (default for general translation search)
            $stmt = $pdo->prepare("SELECT at.surah_id, at.ayah_id, at.text FROM ayah_translations at JOIN full_translations ft ON at.translation_id = ft.id WHERE ft.language_code = 'en' AND at.text LIKE ? LIMIT 20");
            $stmt->execute([$search_param]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'type' => 'English Translation',
                    'surah_id' => $row['surah_id'],
                    'ayah_id' => $row['ayah_id'],
                    'text' => $row['text']
                ];
            }

            // Search in public tafsir
            $stmt = $pdo->prepare("SELECT surah_id, ayah_id, text FROM ayah_tafsir WHERE is_public = 1 AND status = 'approved' AND text LIKE ? LIMIT 20");
            $stmt->execute([$search_param]);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[] = [
                    'type' => 'Public Tafsir',
                    'surah_id' => $row['surah_id'],
                    'ayah_id' => $row['ayah_id'],
                    'text' => $row['text']
                ];
            }

            // Search in user's personal content (notes, root notes if applicable)
            if (is_logged_in()) {
                // Personal Notes
                $stmt = $pdo->prepare("SELECT surah_id, ayah_id, note_text FROM user_notes WHERE user_id = ? AND note_text LIKE ? AND is_private = 1 LIMIT 20");
                $stmt->execute([$_SESSION['user_id'], $search_param]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $results[] = [
                        'type' => 'Personal Note',
                        'surah_id' => $row['surah_id'],
                        'ayah_id' => $row['ayah_id'],
                        'text' => $row['note_text']
                    ];
                }
                // TODO: Add search for user's root notes if implemented
            }

            $response = ['success' => true, 'results' => $results];
            break;

        // --- Admin Only: User Management ---
        case 'admin_get_users':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY id ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response = ['success' => true, 'users' => $users];
            break;

        case 'admin_update_user_role':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $user_id = (int)$_POST['user_id'];
            $new_role = $_POST['role'] ?? 'registered';
            if (!in_array($new_role, ['public', 'registered', 'ulama', 'admin'])) {
                $response = ['success' => false, 'message' => 'Invalid role specified.'];
                break;
            }
            // Prevent changing the role of the currently logged-in admin (self-demotion/lockout prevention)
            if ($user_id == $_SESSION['user_id'] && $new_role !== 'admin') {
                 $response = ['success' => false, 'message' => 'Cannot change your own role from admin.'];
                 break;
            }
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$new_role, $user_id]);
                $response = ['success' => true, 'message' => 'User role updated.'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Failed to update role: ' . $e->getMessage()];
                error_log("User role update error: " . $e->getMessage());
            }
            break;

        case 'admin_delete_user':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $user_id = (int)$_POST['user_id'];
            // Prevent deleting self
            if ($user_id == $_SESSION['user_id']) {
                $response = ['success' => false, 'message' => 'Cannot delete your own admin account.'];
                break;
            }
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $response = ['success' => true, 'message' => 'User deleted successfully.'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()];
                error_log("User deletion error: " . $e->getMessage());
            }
            break;

        case 'admin_reset_password':
            if (!has_permission(['admin'])) {
                $response = ['success' => false, 'message' => 'Access denied.'];
                break;
            }
            $user_id = (int)$_POST['user_id'];
            $new_password = $_POST['new_password'] ?? '';
            if (empty($new_password) || strlen($new_password) < 6) {
                $response = ['success' => false, 'message' => 'New password must be at least 6 characters.'];
                break;
            }
            try {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
                $response = ['success' => true, 'message' => 'User password reset successfully.'];
            } catch (Exception $e) {
                $response = ['success' => false, 'message' => 'Failed to reset password: ' . $e->getMessage()];
                error_log("Password reset error: " . $e->getMessage());
            }
            break;

        default:
            $response = ['success' => false, 'message' => 'Unknown AJAX action.'];
            break;
    }
    echo json_encode($response);
    exit(); // Terminate script after AJAX response
}

// --- Main HTML Structure & Initial Page Render ---
// This part is executed if it's a direct page load, not an AJAX request.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quranic Study App - Yasin Ullah</title>
    <meta name="description" content="A comprehensive responsive Quranic study web app with multiple translations, word-by-word meanings, Tafsir, and personal study tools.">
    <meta name="keywords" content="Quran, study, Islam, Tafsir, word-by-word, translation, Yasin Ullah, PHP, SQLite, Responsive, Web App">
    <meta name="author" content="Yasin Ullah">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📖</text></svg>">
    <!-- Google Fonts for Arabic text (optional, but improves rendering) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400..700&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    <style>
        /* Define CSS Variables for easy theming (light/dark mode support) */
        :root {
            --primary-color: #4CAF50; /* Green */
            --secondary-color: #8BC34A; /* Light Green */
            --accent-color: #FFC107; /* Amber for highlights */
            --text-color: #333;
            --bg-color: #f8f9fa;
            --header-bg: #fff;
            --border-color: #ddd;
            --box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        /* Dark mode preference */
        @media (prefers-color-scheme: dark) {
            :root {
                --primary-color: #4CAF50;
                --secondary-color: #6D9F43;
                --accent-color: #FFEB3B;
                --text-color: #e0e0e0;
                --bg-color: #1a1a1a;
                --header-bg: #2a2a2a;
                --border-color: #444;
                --box-shadow: 0 2px 4px rgba(0,0,0,0.5);
            }
        }
        /* Base Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Full viewport height */
        }
        /* Header Styling */
        header {
            background-color: var(--header-bg);
            color: var(--text-color);
            padding: 15px 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            position: sticky; /* Sticky header */
            top: 0;
            z-index: 1000;
        }
        .app-title {
            margin: 0;
            font-size: 1.8em;
            color: var(--primary-color);
            text-decoration: none;
            flex-shrink: 0; /* Prevent shrinking */
        }
        nav {
            display: flex;
            align-items: center;
            flex-grow: 1; /* Allow nav to take available space */
            justify-content: flex-end;
            gap: 15px; /* Space between nav items */
            flex-wrap: wrap; /* Allow nav items to wrap */
        }
        nav a, .nav-btn, .dropdown-btn {
            color: var(--text-color);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            white-space: nowrap; /* Keep text on one line */
            border: none;
            background: none;
            cursor: pointer;
            font-size: 1em;
            font-family: inherit;
        }
        nav a:hover, .nav-btn:hover, .dropdown-btn:hover {
            background-color: rgba(0,0,0,0.05);
        }
        /* Dropdown Menus */
        .dropdown {
            position: relative;
            display: inline-block;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--header-bg);
            min-width: 160px;
            box-shadow: var(--box-shadow);
            z-index: 1;
            border-radius: 5px;
            overflow: hidden;
            right: 0; /* Align dropdown to the right */
        }
        .dropdown-content a {
            color: var(--text-color);
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            text-align: left;
        }
        .dropdown-content a:hover {
            background-color: var(--primary-color);
            color: #fff;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }

        /* Main Content Area */
        main {
            flex-grow: 1; /* Allow main content to grow and fill space */
            padding: 20px;
            max-width: 1200px;
            margin: 20px auto; /* Center main content */
            background-color: var(--header-bg);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }
        /* Footer Styling */
        footer {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
            color: #777;
            background-color: var(--header-bg);
            box-shadow: 0 -2px 4px rgba(0,0,0,0.05);
        }

        /* Forms & Buttons */
        .form-container {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            background-color: var(--header-bg);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
        }
        .form-container h2 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group input[type="email"],
        .form-group textarea,
        .form-group select {
            width: calc(100% - 22px); /* Account for padding and border */
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-size: 1em;
        }
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px 0;
        }
        .btn {
            background-color: var(--primary-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn:hover {
            background-color: var(--secondary-color);
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        /* Message Styles (success, error, info) */
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Quran Viewer Specific Styles */
        .quran-navigation {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            gap: 10px;
            flex-wrap: wrap;
        }
        .quran-navigation select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        .quran-display {
            background-color: var(--bg-color);
            padding: 20px;
            border-radius: 8px;
            box-shadow: inset 0 0 5px rgba(0,0,0,0.05);
        }
        .quran-ayah {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .quran-ayah:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .arabic-text {
            font-family: 'Scheherazade New', 'Noto Naskh Arabic', 'Traditional Arabic', serif;
            font-size: 2.2em;
            text-align: right;
            margin-bottom: 10px;
            line-height: 1.8;
            direction: rtl; /* Right-to-left for Arabic */
        }
        .arabic-word {
            cursor: help;
            border-bottom: 1px dotted var(--primary-color);
            display: inline-block; /* Allows spacing and hover effect */
            margin: 0 2px;
            position: relative; /* For tooltip */
            padding-bottom: 2px; /* For better dotted line visibility */
        }
        .word-tooltip {
            visibility: hidden;
            background-color: var(--primary-color);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px 10px;
            position: absolute;
            z-index: 1;
            bottom: 125%; /* Position above the text */
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap;
            font-size: 0.9em;
            min-width: 150px; /* Ensure it's wide enough */
        }
        .arabic-word:hover .word-tooltip {
            visibility: visible;
            opacity: 1;
        }
        .translation-text {
            font-size: 1.1em;
            color: #555;
            text-align: left;
            margin-top: 10px;
        }
        .tafsir-section h3, .notes-section h3 {
            color: var(--primary-color);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 5px;
            margin-top: 20px;
            margin-bottom: 15px;
        }
        .tafsir-item, .note-item {
            background-color: var(--header-bg);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin-bottom: 10px;
        }
        .tafsir-item h4 {
            margin-top: 0;
            margin-bottom: 5px;
            color: var(--secondary-color);
        }
        .tafsir-item p, .note-item p {
            margin: 0;
        }
        #personalNoteForm {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        #personalNoteForm textarea {
            width: calc(100% - 22px);
            height: 100px;
            resize: vertical;
        }
        .loading-indicator {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #777;
        }

        /* Admin & Ulama Panels */
        .admin-panel, .ulama-panel {
            padding: 20px;
            background-color: var(--header-bg);
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
        }
        .admin-panel h2, .ulama-panel h2 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 20px;
            text-align: center;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 0.9em;
        }
        .data-table th, .data-table td {
            border: 1px solid var(--border-color);
            padding: 8px;
            text-align: left;
        }
        .data-table th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: bold;
        }
        .data-table tr:nth-child(even) {
            background-color: rgba(0,0,0,0.02);
        }
        .data-table td .btn {
            padding: 5px 8px;
            font-size: 0.8em;
            margin-right: 5px;
        }
        .data-table td .btn:last-child {
            margin-right: 0;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                align-items: flex-start;
            }
            nav {
                margin-top: 15px;
                width: 100%;
                justify-content: center;
            }
            .nav-btn, .dropdown-btn {
                padding: 10px;
                font-size: 0.9em;
            }
            .dropdown-content {
                right: auto;
                left: 0;
                width: 100%;
                min-width: unset; /* Override fixed width */
            }
            main {
                padding: 15px;
                margin: 15px auto;
            }
            .arabic-text {
                font-size: 1.8em;
            }
            .translation-text {
                font-size: 1em;
            }
            .quran-navigation {
                flex-direction: column;
            }
            .quran-navigation select, .quran-navigation .btn, .search-container input, .search-container button {
                width: 100%;
                margin-top: 5px;
            }
            .search-container {
                width: 100%;
            }
            .data-table {
                font-size: 0.8em; /* Smaller font for tables on small screens */
            }
            .data-table th, .data-table td {
                padding: 6px;
            }
        }
        @media (max-width: 480px) {
            .app-title {
                font-size: 1.5em;
            }
            nav {
                flex-wrap: wrap;
                gap: 5px;
            }
            .form-container {
                padding: 15px;
            }
            .arabic-text {
                font-size: 1.5em;
            }
            .word-tooltip {
                font-size: 0.8em;
                min-width: 100px;
            }
        }
    </style>
</head>
<body>
    <header>
        <a href="index.php" class="app-title">Quranic Study App</a>
        <nav>
            <?php if (is_logged_in()): ?>
                <span style="color: var(--primary-color); font-weight: bold;">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars(ucfirst(get_user_role())); ?>)</span>
                <?php if (has_permission(['registered', 'ulama', 'admin'])): ?>
                    <div class="dropdown">
                        <button class="dropdown-btn">My Study</button>
                        <div class="dropdown-content">
                            <a href="#personal-notes" onclick="showSection('personal-notes'); return false;">Personal Notes</a>
                            <a href="#hifz-tracking" onclick="showSection('hifz-tracking'); return false;">Hifz Tracking</a>
                            <a href="#recitation-logs" onclick="showSection('recitation-logs'); return false;">Recitation Logs</a>
                            <a href="#my-themes" onclick="showSection('my-themes'); return false;">My Themes</a>
                            <a href="#my-root-notes" onclick="showSection('my-root-notes'); return false;">My Root Notes</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_permission(['ulama', 'admin'])): ?>
                    <div class="dropdown">
                        <button class="dropdown-btn">Ulama Tools</button>
                        <div class="dropdown-content">
                            <a href="#approval-queue" onclick="showSection('approval-queue'); return false;">Approval Queue</a>
                            <a href="#manage-public-content" onclick="showSection('manage-public-content'); return false;">Manage Public Content</a>
                            <a href="#edit-word-by-word" onclick="showSection('edit-word-by-word'); return false;">Edit Word Meanings</a>
                            <a href="#add-public-tafsir" onclick="showSection('add-public-tafsir'); return false;">Add Public Tafsir</a>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (has_permission(['admin'])): ?>
                    <div class="dropdown">
                        <button class="dropdown-btn">Admin Panel</button>
                        <div class="dropdown-content">
                            <a href="#user-management" onclick="showSection('user-management'); return false;">User Management</a>
                            <a href="#data-management" onclick="showSection('data-management'); return false;">Data Management</a>
                            <a href="#app-settings" onclick="showSection('app-settings'); return false;">App Settings</a>
                        </div>
                    </div>
                <?php endif; ?>
                <a href="#" class="nav-btn" id="logoutBtn">Logout</a>
            <?php else: ?>
                <a href="#login" class="nav-btn" onclick="showSection('login'); return false;">Login</a>
                <a href="#register" class="nav-btn" onclick="showSection('register'); return false;">Register</a>
            <?php endif; ?>
            <a href="#quran-viewer" class="nav-btn" onclick="showSection('quran-viewer'); return false;">Quran Viewer</a>
            <div class="search-container">
                <input type="text" id="globalSearchInput" placeholder="Search Quran, translations, notes..." style="padding: 8px; border-radius: 5px; border: 1px solid var(--border-color); background-color: var(--bg-color); color: var(--text-color);">
                <button class="btn" onclick="performGlobalSearch()">Search</button>
            </div>
        </nav>
    </header>

    <main id="app-main-content">
        <div id="quran-viewer" class="content-section">
            <h2>Quran Viewer</h2>
            <div class="quran-navigation">
                <label for="surahSelect" class="sr-only">Select Surah</label>
                <select id="surahSelect"></select>
                <label for="ayahSelect" class="sr-only">Select Ayah</label>
                <select id="ayahSelect"></select>
                <label for="translationSelect" class="sr-only">Select Translation</label>
                <select id="translationSelect"></select>
                <button class="btn" onclick="loadSelectedAyah()">Go</button>
            </div>
            <div id="quranDisplay" class="quran-display">
                <div class="loading-indicator">Loading Quran...</div>
            </div>
            <div id="personalNoteSection" class="notes-section" style="display: <?php echo is_logged_in() ? 'block' : 'none'; ?>">
                <h3>Personal Notes for Ayah <span id="currentAyahNoteRef"></span></h3>
                <form id="personalNoteForm">
                    <input type="hidden" id="noteSurahId" value="">
                    <input type="hidden" id="noteAyahId" value="">
                    <div class="form-group">
                        <textarea id="noteText" placeholder="Write your private note here..."></textarea>
                    </div>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="noteIsPublic" <?php echo has_permission(['registered', 'ulama', 'admin']) ? '' : 'disabled'; ?>> Submit for Public Approval (Registered only)
                        </label>
                    </div>
                    <button type="submit" class="btn">Save Note</button>
                </form>
            </div>
            <div id="publicTafsirSection" class="tafsir-section">
                <h3>Tafsir & Interpretations</h3>
                <div id="tafsirContent"></div>
            </div>
        </div>

        <div id="login" class="content-section form-container" style="display: none;">
            <h2>Login</h2>
            <div id="loginMessage" class="message" style="display: none;"></div>
            <form id="loginForm">
                <div class="form-group">
                    <label for="loginUsername">Username:</label>
                    <input type="text" id="loginUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="loginPassword">Password:</label>
                    <input type="password" id="loginPassword" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>

        <div id="register" class="content-section form-container" style="display: none;">
            <h2>Register</h2>
            <div id="registerMessage" class="message" style="display: none;"></div>
            <form id="registerForm">
                <div class="form-group">
                    <label for="regUsername">Username:</label>
                    <input type="text" id="regUsername" name="username" required>
                </div>
                <div class="form-group">
                    <label for="regEmail">Email:</label>
                    <input type="email" id="regEmail" name="email" required>
                </div>
                <div class="form-group">
                    <label for="regPassword">Password:</label>
                    <input type="password" id="regPassword" name="password" required>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
        </div>

        <div id="search-results" class="content-section" style="display: none;">
            <h2>Search Results for "<span id="searchTermDisplay"></span>"</h2>
            <div id="searchResultsContent">
                <p class="loading-indicator">No results yet. Type in the search box above.</p>
            </div>
        </div>

        <!-- Registered User Sections (Placeholders) -->
        <?php if (has_permission(['registered', 'ulama', 'admin'])): ?>
            <div id="personal-notes" class="content-section" style="display: none;">
                <h2>My Personal Notes</h2>
                <p>Manage all your private and public notes here. (Functionality to be implemented)</p>
                <div id="allPersonalNotes"></div>
            </div>
            <div id="hifz-tracking" class="content-section" style="display: none;">
                <h2>Hifz Tracking</h2>
                <p>Track your Quran memorization progress. (Functionality to be implemented)</p>
            </div>
            <div id="recitation-logs" class="content-section" style="display: none;">
                <h2>Recitation Logs</h2>
                <p>Log your daily Quran recitations. (Functionality to be implemented)</p>
            </div>
            <div id="my-themes" class="content-section" style="display: none;">
                <h2>My Custom Themes</h2>
                <p>Create and manage your custom themes. (Functionality to be implemented)</p>
            </div>
            <div id="my-root-notes" class="content-section" style="display: none;">
                <h2>My Root Notes</h2>
                <p>Keep notes on Quranic root words and their meanings. (Functionality to be implemented)</p>
            </div>
        <?php endif; ?>

        <!-- Ulama & Admin Sections -->
        <?php if (has_permission(['ulama', 'admin'])): ?>
            <div id="approval-queue" class="content-section ulama-panel" style="display: none;">
                <h2>Approval Queue</h2>
                <p>Review content submitted by registered users for public visibility.</p>
                <div id="approvalQueueContent">
                    <p class="loading-indicator">Loading pending items...</p>
                </div>
            </div>
            <div id="manage-public-content" class="content-section ulama-panel" style="display: none;">
                <h2>Manage Public Content</h2>
                <p>Directly manage public themes and root notes. (Functionality to be implemented)</p>
            </div>
            <div id="edit-word-by-word" class="content-section ulama-panel" style="display: none;">
                <h2>Edit Public Word-by-Word Meanings</h2>
                <div id="editWbWMessage" class="message" style="display: none;"></div>
                <form id="editWbWForm">
                    <div class="form-group">
                        <label for="editWbWQuranText">Arabic Word:</label>
                        <input type="text" id="editWbWQuranText" name="quran_text" required placeholder="e.g., بِسْمِ">
                    </div>
                    <div class="form-group">
                        <label for="editWbWUrMeaning">Urdu Meaning:</label>
                        <input type="text" id="editWbWUrMeaning" name="ur_meaning" placeholder="Urdu meaning">
                    </div>
                    <div class="form-group">
                        <label for="editWbWEnMeaning">English Meaning:</label>
                        <input type="text" id="editWbWEnMeaning" name="en_meaning" placeholder="English meaning">
                    </div>
                    <button type="submit" class="btn">Save/Update Word Meaning</button>
                </form>
            </div>
            <div id="add-public-tafsir" class="content-section ulama-panel" style="display: none;">
                <h2>Add/Edit Public Ayah Tafsir</h2>
                <div id="addPublicTafsirMessage" class="message" style="display: none;"></div>
                <form id="addPublicTafsirForm">
                    <div class="form-group">
                        <label for="publicTafsirSurahId">Surah:</label>
                        <input type="number" id="publicTafsirSurahId" name="surah_id" min="1" max="114" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="publicTafsirAyahId">Ayah:</label>
                        <input type="number" id="publicTafsirAyahId" name="ayah_id" min="1" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="publicTafsirSetName">Tafsir Set Name (e.g., Tafsir Ibn Kathir):</label>
                        <input type="text" id="publicTafsirSetName" name="tafsir_set_name" placeholder="Leave empty for 'General Public Tafsir'">
                    </div>
                    <div class="form-group">
                        <label for="publicTafsirAuthor">Author:</label>
                        <input type="text" id="publicTafsirAuthor" name="tafsir_author" value="<?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="publicTafsirText">Tafsir Text:</label>
                        <textarea id="publicTafsirText" name="tafsir_text" rows="8" required></textarea>
                    </div>
                    <button type="submit" class="btn">Save Public Tafsir</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Admin Specific Sections -->
        <?php if (has_permission(['admin'])): ?>
            <div id="user-management" class="content-section admin-panel" style="display: none;">
                <h2>User Management</h2>
                <p>Manage users, roles, and passwords.</p>
                <div id="userManagementMessage" class="message" style="display: none;"></div>
                <div id="userList">
                    <p class="loading-indicator">Loading users...</p>
                </div>
            </div>

            <div id="data-management" class="content-section admin-panel" style="display: none;">
                <h2>Data Management</h2>
                <p>Import new Tafsir sets, backup, and restore the database.</p>
                <div id="dataManagementMessage" class="message" style="display: none;"></div>

                <h3>Import New Tafsir Set</h3>
                <form id="importTafsirCSVForm" enctype="multipart/form-data">
                    <h4>From CSV (surah,ayah,tafsir_text,tafsir_name,author)</h4>
                    <div class="form-group">
                        <label for="tafsirFileCSV">CSV File:</label>
                        <input type="file" id="tafsirFileCSV" name="tafsir_file" accept=".csv" required>
                    </div>
                    <div class="form-group">
                        <label for="tafsirSetNameCSV">Tafsir Set Name:</label>
                        <input type="text" id="tafsirSetNameCSV" name="tafsir_set_name_csv" placeholder="e.g., Tafsir Al-Jalalaynn" required>
                    </div>
                    <div class="form-group">
                        <label for="tafsirAuthorCSV">Author:</label>
                        <input type="text" id="tafsirAuthorCSV" name="tafsir_author_csv" placeholder="e.g., Jalal ad-Din al-Mahalli" required>
                    </div>
                    <button type="submit" class="btn">Import CSV Tafsir</button>
                </form>
                <hr style="margin: 20px 0; border-color: var(--border-color);">
                <form id="importTafsirJSONForm" enctype="multipart/form-data">
                    <h4>From JSON (array of objects with surah, ayah, text, tafsir_name, author)</h4>
                    <div class="form-group">
                        <label for="tafsirFileJSON">JSON File:</label>
                        <input type="file" id="tafsirFileJSON" name="tafsir_file" accept=".json" required>
                    </div>
                    <div class="form-group">
                        <label for="tafsirSetNameJSON">Tafsir Set Name:</label>
                        <input type="text" id="tafsirSetNameJSON" name="tafsir_set_name_json" placeholder="e.g., Tafsir Mufti Taqi Usmani" required>
                    </div>
                    <div class="form-group">
                        <label for="tafsirAuthorJSON">Author:</label>
                        <input type="text" id="tafsirAuthorJSON" name="tafsir_author_json" placeholder="e.g., Mufti Taqi Usmani" required>
                    </div>
                    <button type="submit" class="btn">Import JSON Tafsir</button>
                </form>

                <h3 style="margin-top: 30px;">Database Backup & Restore</h3>
                <button class="btn" onclick="backupDatabase()">Backup Database</button>
                <p style="font-size: 0.9em; margin-top: 5px;">A timestamped backup file will be created in the 'uploads' directory.</p>
                <hr style="margin: 20px 0; border-color: var(--border-color);">
                <form id="restoreDbForm" enctype="multipart/form-data" style="margin-top: 15px;">
                    <h4>Restore Database from Backup</h4>
                    <div class="form-group">
                        <label for="backupFile">Select Backup File (.sqlite):</label>
                        <input type="file" id="backupFile" name="backup_file" accept=".sqlite" required>
                    </div>
                    <button type="submit" class="btn btn-danger">Restore Database</button>
                    <p style="color: #dc3545; font-size: 0.9em; margin-top: 10px;">Warning: Restoring will overwrite the current database. All existing data will be replaced by the backup content. This action is irreversible.</p>
                </form>
            </div>

            <div id="app-settings" class="content-section admin-panel" style="display: none;">
                <h2>Application Settings</h2>
                <p>Manage global application settings. (Functionality to be implemented)</p>
            </div>
        <?php endif; ?>

    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> Quranic Study App by Yasin Ullah. All rights reserved.</p>
    </footer>

    <script>
        // Global variables for current Surah/Ayah and Translation
        // Initialize from URL parameters or default to Surah 1, Ayah 1
        let currentSurah = parseInt(<?php echo isset($_GET['s']) ? (int)$_GET['s'] : 1; ?>);
        let currentAyah = parseInt(<?php echo isset($_GET['a']) ? (int)$_GET['a'] : 1; ?>);
        let currentTranslationId = 1; // Default to first translation in the database

        // Function to show/hide sections
        function showSection(sectionId) {
            document.querySelectorAll('.content-section').forEach(section => {
                section.style.display = 'none';
            });
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.style.display = 'block';

                // Specific actions for sections
                if (sectionId === 'quran-viewer') {
                    // Load the Ayah if it's the Quran viewer section
                    // Delay slightly to ensure UI is ready
                    setTimeout(() => loadSelectedAyah(), 100);
                } else if (sectionId === 'approval-queue') {
                    loadApprovalQueue();
                } else if (sectionId === 'user-management') {
                    loadUserManagement();
                }
                // Update URL hash for better navigation and deep linking (though not fully crawled by bots for SPA)
                history.pushState(null, '', '#' + sectionId);
            } else {
                console.error('Section not found:', sectionId);
            }
        }

        // Helper for AJAX requests
        // `data` can be a plain object or a FormData object for file uploads
        async function fetchData(action, data = {}, method = 'POST') {
            const isFormData = data instanceof FormData;
            const body = isFormData ? data : new FormData();

            if (!isFormData) { // If not already FormData, append action and other data
                body.append('action', action);
                for (const key in data) {
                    if (data.hasOwnProperty(key)) {
                        body.append(key, data[key]);
                    }
                }
            } else { // If already FormData, ensure 'action' is present
                if (!body.has('action')) {
                   body.append('action', action);
                }
            }

            try {
                const response = await fetch('index.php', {
                    method: method,
                    body: body
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error('Fetch error for action ' + action + ':', error);
                return { success: false, message: 'Network or server error: ' + error.message };
            }
        }

        // Display message helper
        function displayMessage(elementId, message, type) {
            const element = typeof elementId === 'string' ? document.getElementById(elementId) : elementId;
            if (element) {
                element.textContent = message;
                element.className = `message ${type}`;
                element.style.display = 'block';
                // Automatically hide after 5 seconds
                setTimeout(() => {
                    element.style.display = 'none';
                    element.textContent = ''; // Clear message
                }, 5000);
            } else {
                console.error('Message element not found:', elementId);
            }
        }

        // Simple HTML Escaping function for security
        function htmlspecialchars(str) {
            if (typeof str !== 'string') {
                str = String(str);
            }
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // --- Authentication ---
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = e.target.username.value;
            const password = e.target.password.value;
            const response = await fetchData('login', { username, password });
            displayMessage('loginMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                // Full page reload is simplest for updating navigation/permissions dynamically
                window.location.reload();
            }
        });

        document.getElementById('registerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = e.target.username.value;
            const email = e.target.email.value;
            const password = e.target.password.value;
            const response = await fetchData('register', { username, email, password });
            displayMessage('registerMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                window.location.reload();
            }
        });

        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                const response = await fetchData('logout');
                if (response.success) {
                    window.location.reload();
                } else {
                    alert('Logout failed: ' + response.message);
                }
            });
        }

        // --- Quran Viewer ---
        async function populateSurahSelect() {
            const select = document.getElementById('surahSelect');
            const response = await fetchData('get_surah_list');
            if (response.success) {
                select.innerHTML = '';
                response.surahs.forEach(surah => {
                    const option = document.createElement('option');
                    option.value = surah;
                    option.textContent = `Surah ${surah}`;
                    select.appendChild(option);
                });
                // Set initial surah from URL or default
                select.value = currentSurah;
                await populateAyahSelect(currentSurah); // Then populate Ayah select
            } else {
                console.error('Failed to load surah list:', response.message);
                displayMessage('quranDisplay', `Failed to load Surah list: ${response.message}`, 'error');
            }
        }

        async function populateAyahSelect(surahId) {
            const select = document.getElementById('ayahSelect');
            const response = await fetchData('get_ayah_list', { surah_id: surahId });
            if (response.success) {
                select.innerHTML = '';
                response.ayahs.forEach(ayah => {
                    const option = document.createElement('option');
                    option.value = ayah;
                    option.textContent = `Ayah ${ayah}`;
                    select.appendChild(option);
                });
                // Set initial ayah from URL or default, ensuring it's valid for the surah
                if (response.ayahs.includes(currentAyah)) {
                    select.value = currentAyah;
                } else {
                    // If current ayah is out of range for new surah, default to 1
                    select.value = 1;
                    currentAyah = 1;
                }
            } else {
                console.error('Failed to load ayah list:', response.message);
                displayMessage('quranDisplay', `Failed to load Ayah list for Surah ${surahId}: ${response.message}`, 'error');
            }
        }

        async function populateTranslationSelect(translations) {
            const select = document.getElementById('translationSelect');
            select.innerHTML = ''; // Clear existing options
            translations.forEach(translation => {
                const option = document.createElement('option');
                option.value = translation.id;
                option.textContent = translation.translation_name;
                select.appendChild(option);
            });
            // Try to set previously selected translation, otherwise default to first option
            if (select.querySelector(`option[value="${currentTranslationId}"]`)) {
                select.value = currentTranslationId;
            } else if (translations.length > 0) {
                select.value = translations[0].id;
                currentTranslationId = translations[0].id;
            }
        }

        // Event listeners for Surah/Ayah/Translation selection changes
        document.getElementById('surahSelect').addEventListener('change', async (e) => {
            currentSurah = parseInt(e.target.value);
            await populateAyahSelect(currentSurah); // Await for ayah list to update before setting currentAyah
            // If current Ayah is no longer valid for the new Surah, it defaults to 1 in populateAyahSelect
        });

        document.getElementById('ayahSelect').addEventListener('change', (e) => {
            currentAyah = parseInt(e.target.value);
        });

        document.getElementById('translationSelect').addEventListener('change', (e) => {
            currentTranslationId = parseInt(e.target.value);
        });

        async function loadSelectedAyah() {
            const quranDisplay = document.getElementById('quranDisplay');
            quranDisplay.innerHTML = '<div class="loading-indicator">Loading Ayah...</div>'; // Show loading

            const response = await fetchData('load_surah_ayah', {
                surah_id: currentSurah,
                ayah_id: currentAyah,
                translation_id: currentTranslationId
            });

            if (response.success) {
                quranDisplay.innerHTML = ''; // Clear loading indicator
                
                // Update URL for SEO and direct linking
                history.pushState(null, '', `index.php?s=${response.surah_id}&a=${response.ayah_id}`);

                const ayahDiv = document.createElement('div');
                ayahDiv.className = 'quran-ayah';

                const arabicText = document.createElement('div');
                arabicText.className = 'arabic-text';
                // Split Arabic text into words and create interactive spans
                response.arabic_text.split(/\s+/u).forEach(word => { // Use unicode-aware split for spaces
                    if (word.trim() === '') return; // Skip empty strings from multiple spaces

                    const span = document.createElement('span');
                    span.className = 'arabic-word';
                    span.textContent = word;

                    // Find corresponding meaning for the word
                    const wbwMeaning = response.word_by_word.find(item => item.word === word);
                    if (wbwMeaning) {
                        const tooltip = document.createElement('span');
                        tooltip.className = 'word-tooltip';
                        tooltip.textContent = `Urdu: ${htmlspecialchars(wbwMeaning.ur_meaning || 'N/A')} | English: ${htmlspecialchars(wbwMeaning.en_meaning || 'N/A')}`;
                        span.appendChild(tooltip);
                    }
                    arabicText.appendChild(span);
                    arabicText.appendChild(document.createTextNode(' ')); // Maintain space visually
                });
                ayahDiv.appendChild(arabicText);

                const translationText = document.createElement('div');
                translationText.className = 'translation-text';
                translationText.innerHTML = `<strong>${htmlspecialchars(response.selected_translation_name || 'Translation')}:</strong> ${htmlspecialchars(response.translation_text)}`;
                ayahDiv.appendChild(translationText);

                quranDisplay.appendChild(ayahDiv);

                // Update navigation selects based on fetched data
                document.getElementById('surahSelect').value = response.surah_id;
                // Re-populate Ayah select for current Surah to ensure accurate Ayah range
                await populateAyahSelect(response.surah_id);
                document.getElementById('ayahSelect').value = response.ayah_id;
                populateTranslationSelect(response.translation_options); // Ensure translation dropdown is updated

                // Update Tafsir section
                const tafsirContentDiv = document.getElementById('tafsirContent');
                tafsirContentDiv.innerHTML = ''; // Clear previous content

                if (response.tafsir && (response.tafsir.public.length > 0 || response.tafsir.personal.length > 0)) {
                    // Public Tafsir
                    if (response.tafsir.public.length > 0) {
                        response.tafsir.public.forEach(tafsir => {
                            const itemDiv = document.createElement('div');
                            itemDiv.className = 'tafsir-item';
                            itemDiv.innerHTML = `<h4>${htmlspecialchars(tafsir.tafsir_name || 'Public Tafsir')} (by ${htmlspecialchars(tafsir.author || 'Unknown')})</h4><p>${htmlspecialchars(tafsir.text)}</p>`;
                            tafsirContentDiv.appendChild(itemDiv);
                        });
                    }

                    // Personal Tafsir/Notes (if logged in)
                    if (response.tafsir.personal && response.tafsir.personal.length > 0) {
                        response.tafsir.personal.forEach(note => {
                            const itemDiv = document.createElement('div');
                            itemDiv.className = 'note-item';
                            itemDiv.innerHTML = `<h4>Your Note (${note.is_private ? 'Private' : 'Public (pending)'})</h4><p>${htmlspecialchars(note.note_text)}</p>`;
                            tafsirContentDiv.appendChild(itemDiv);
                        });
                    }
                } else {
                    tafsirContentDiv.innerHTML = '<p>No public Tafsir or personal notes found for this Ayah.</p>';
                }


                // Update personal note form
                const personalNoteSection = document.getElementById('personalNoteSection');
                const noteSurahId = document.getElementById('noteSurahId');
                const noteAyahId = document.getElementById('noteAyahId');
                const currentAyahNoteRef = document.getElementById('currentAyahNoteRef');
                const noteText = document.getElementById('noteText');
                const noteIsPublic = document.getElementById('noteIsPublic');

                // Check if user is logged in using PHP variable echoed to JS
                const isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;
                const canSubmitPublic = <?php echo has_permission(["registered", "ulama", "admin"]) ? 'true' : 'false'; ?>;

                if (isLoggedIn) {
                    personalNoteSection.style.display = 'block';
                    noteSurahId.value = response.surah_id;
                    noteAyahId.value = response.ayah_id;
                    currentAyahNoteRef.textContent = `${response.surah_id}:${response.ayah_id}`;

                    // Fetch existing note for the user for this ayah
                    const existingNoteResponse = await fetchData('get_note', {
                        surah_id: response.surah_id,
                        ayah_id: response.ayah_id
                    });
                    if (existingNoteResponse.success) {
                        noteText.value = existingNoteResponse.note_text;
                        noteIsPublic.checked = !existingNoteResponse.is_private; // Check if it's meant to be public
                    } else {
                        noteText.value = '';
                        noteIsPublic.checked = false;
                    }

                    // Enable/disable public submission checkbox based on role
                    if (canSubmitPublic) {
                        noteIsPublic.removeAttribute('disabled');
                    } else {
                        noteIsPublic.setAttribute('disabled', 'true');
                    }

                } else {
                    personalNoteSection.style.display = 'none';
                }

            } else {
                quranDisplay.innerHTML = `<div class="message error">${htmlspecialchars(response.message)}</div>`;
                console.error('Error loading ayah:', response.message);
            }
        }

        // --- Personal Note Saving ---
        document.getElementById('personalNoteForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const surah_id = document.getElementById('noteSurahId').value;
            const ayah_id = document.getElementById('noteAyahId').value;
            const note_text = document.getElementById('noteText').value;
            // `is_private` is 0 for public (pending approval), 1 for truly private
            const is_private = document.getElementById('noteIsPublic').checked ? 0 : 1;

            const response = await fetchData('save_note', { surah_id, ayah_id, note_text, is_private });
            displayMessage(document.getElementById('personalNoteSection'), response.message, response.success ? 'success' : 'error');
            if (response.success) {
                loadSelectedAyah(); // Reload ayah to show updated notes/tafsir
            }
        });

        // --- Admin/Ulama: Approval Queue ---
        async function loadApprovalQueue() {
            const approvalQueueContent = document.getElementById('approvalQueueContent');
            approvalQueueContent.innerHTML = '<p class="loading-indicator">Loading pending items...</p>';

            const response = await fetchData('get_pending_approvals');
            if (response.success) {
                if (response.pending_items.length === 0) {
                    approvalQueueContent.innerHTML = '<p>No pending items in the approval queue.</p>';
                    return;
                }
                let html = '<table class="data-table"><thead><tr><th>ID</th><th>Type</th><th>Submitted By</th><th>Details</th><th>Actions</th></tr></thead><tbody>';
                response.pending_items.forEach(item => {
                    let detailsHtml = 'N/A';
                    if (item.content_type === 'user_note' && item.details) {
                        detailsHtml = `Ayah ${item.details.surah_id}:${item.details.ayah_id}: "${htmlspecialchars(item.details.note_text.substring(0, 100))}${item.details.note_text.length > 100 ? '...' : ''}"`;
                    }
                    // TODO: Extend for other content types as they get approval logic

                    html += `<tr>
                                <td>${item.id}</td>
                                <td>${htmlspecialchars(item.content_type.replace(/_/g, ' '))}</td>
                                <td>${htmlspecialchars(item.submitted_by_username)}</td>
                                <td>${detailsHtml}</td>
                                <td>
                                    <button class="btn" onclick="approveContent(${item.id})">Approve</button>
                                    <button class="btn btn-danger" onclick="rejectContent(${item.id})">Reject</button>
                                </td>
                            </tr>`;
                });
                html += '</tbody></table>';
                approvalQueueContent.innerHTML = html;
            } else {
                displayMessage(approvalQueueContent, response.message, 'error');
            }
        }

        async function approveContent(approvalId) {
            const response = await fetchData('approve_content', { approval_id: approvalId });
            displayMessage(document.getElementById('approvalQueueContent'), response.message, response.success ? 'success' : 'error');
            if (response.success) {
                loadApprovalQueue(); // Reload queue
            }
        }

        async function rejectContent(approvalId) {
            const response = await fetchData('reject_content', { approval_id: approvalId });
            displayMessage(document.getElementById('approvalQueueContent'), response.message, response.success ? 'success' : 'error');
            if (response.success) {
                loadApprovalQueue(); // Reload queue
            }
        }

        // --- Ulama/Admin: Edit Word-by-Word Meanings ---
        document.getElementById('editWbWForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const quran_text = document.getElementById('editWbWQuranText').value;
            const ur_meaning = document.getElementById('editWbWUrMeaning').value;
            const en_meaning = document.getElementById('editWbWEnMeaning').value;
            const response = await fetchData('edit_public_word', { quran_text, ur_meaning, en_meaning });
            displayMessage('editWbWMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                e.target.reset(); // Clear form
            }
        });

        // --- Ulama/Admin: Add Public Tafsir ---
        document.getElementById('addPublicTafsirForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const surah_id = document.getElementById('publicTafsirSurahId').value;
            const ayah_id = document.getElementById('publicTafsirAyahId').value;
            const tafsir_text = document.getElementById('publicTafsirText').value;
            const tafsir_set_name = document.getElementById('publicTafsirSetName').value;
            const tafsir_author = document.getElementById('publicTafsirAuthor').value;

            const response = await fetchData('add_ayah_tafsir_public', {
                surah_id, ayah_id, tafsir_text, tafsir_set_name, tafsir_author
            });

            displayMessage('addPublicTafsirMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                e.target.reset(); // Clear form
                // Optionally reload current ayah if it's the one being viewed
                if (parseInt(surah_id) === currentSurah && parseInt(ayah_id) === currentAyah) {
                    loadSelectedAyah();
                }
            }
        });

        // --- Admin: User Management ---
        async function loadUserManagement() {
            const userListDiv = document.getElementById('userList');
            userListDiv.innerHTML = '<p class="loading-indicator">Loading users...</p>';

            const response = await fetchData('admin_get_users');
            if (response.success) {
                if (response.users.length === 0) {
                    userListDiv.innerHTML = '<p>No users found.</p>';
                    return;
                }
                let html = '<table class="data-table"><thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created At</th><th>Actions</th></tr></thead><tbody>';
                response.users.forEach(user => {
                    const is_self = (user.id == <?php echo $_SESSION['user_id'] ?? 0; ?>); // Check if it's the current user
                    html += `<tr>
                                <td>${user.id}</td>
                                <td>${htmlspecialchars(user.username)}</td>
                                <td>${htmlspecialchars(user.email || 'N/A')}</td>
                                <td>
                                    <select onchange="updateUserRole(${user.id}, this.value)" ${is_self ? 'disabled' : ''}>
                                        <option value="public" ${user.role === 'public' ? 'selected' : ''}>Public</option>
                                        <option value="registered" ${user.role === 'registered' ? 'selected' : ''}>Registered</option>
                                        <option value="ulama" ${user.role === 'ulama' ? 'selected' : ''}>Ulama</option>
                                        <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                                    </select>
                                </td>
                                <td>${user.created_at}</td>
                                <td>
                                    <button class="btn btn-secondary" onclick="resetUserPasswordPrompt(${user.id}, '${htmlspecialchars(user.username)}')" ${is_self ? 'disabled' : ''}>Reset Password</button>
                                    <button class="btn btn-danger" onclick="deleteUser(${user.id}, '${htmlspecialchars(user.username)}')" ${is_self ? 'disabled' : ''}>Delete</button>
                                </td>
                            </tr>`;
                });
                html += '</tbody></table>';
                userListDiv.innerHTML = html;
            } else {
                displayMessage(userListDiv, response.message, 'error');
            }
        }

        async function updateUserRole(userId, newRole) {
            const response = await fetchData('admin_update_user_role', { user_id: userId, role: newRole });
            displayMessage('userManagementMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                loadUserManagement(); // Reload list
            }
        }

        function resetUserPasswordPrompt(userId, username) {
            const newPassword = prompt(`Enter new password for ${username}:`);
            if (newPassword && newPassword.length >= 6) {
                resetUserPassword(userId, newPassword);
            } else if (newPassword !== null) { // If user didn't cancel
                displayMessage('userManagementMessage', 'Password must be at least 6 characters.', 'error');
            }
        }

        async function resetUserPassword(userId, newPassword) {
            const response = await fetchData('admin_reset_password', { user_id: userId, new_password: newPassword });
            displayMessage('userManagementMessage', response.message, response.success ? 'success' : 'error');
        }

        async function deleteUser(userId, username) {
            if (confirm(`Are you sure you want to delete user "${username}"? This action is irreversible.`)) {
                const response = await fetchData('admin_delete_user', { user_id: userId });
                displayMessage('userManagementMessage', response.message, response.success ? 'success' : 'error');
                if (response.success) {
                    loadUserManagement(); // Reload list
                }
            }
        }

        // --- Admin: Data Management ---
        document.getElementById('importTafsirCSVForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetchData('admin_import_tafsir', formData); // Pass action separately for fetchData
            displayMessage('dataManagementMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                e.target.reset(); // Clear form
            }
        });

        document.getElementById('importTafsirJSONForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetchData('admin_import_tafsir', formData); // Pass action separately for fetchData
            displayMessage('dataManagementMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                e.target.reset(); // Clear form
            }
        });

        async function backupDatabase() {
            const response = await fetchData('admin_backup_db');
            displayMessage('dataManagementMessage', response.message, response.success ? 'success' : 'error');
            // If success, provide a way to download the file directly, though not explicitly asked
            // if (response.success && response.file_name) {
            //     const downloadUrl = `<?php echo htmlspecialchars(str_replace($_SERVER['DOCUMENT_ROOT'], '', UPLOAD_DIR)); ?>/${response.file_name}`;
            //     const downloadLink = document.createElement('a');
            //     downloadLink.href = downloadUrl;
            //     downloadLink.download = response.file_name;
            //     downloadLink.textContent = 'Click here to download backup';
            //     document.getElementById('dataManagementMessage').appendChild(downloadLink);
            // }
        }

        document.getElementById('restoreDbForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!confirm('Are you absolutely sure you want to restore the database? This will overwrite ALL current data and is irreversible!')) {
                return;
            }
            const formData = new FormData(e.target);
            const response = await fetchData('admin_restore_db', formData);
            displayMessage('dataManagementMessage', response.message, response.success ? 'success' : 'error');
            if (response.success) {
                setTimeout(() => window.location.reload(), 1000); // Reload page to ensure new DB connection
            }
        });

        // --- Global Search ---
        async function performGlobalSearch() {
            const searchInput = document.getElementById('globalSearchInput');
            const searchTerm = searchInput.value;
            if (!searchTerm.trim()) {
                displayMessage('searchResultsContent', 'Please enter a search term.', 'info');
                return;
            }

            showSection('search-results'); // Switch to search results section
            document.getElementById('searchTermDisplay').textContent = htmlspecialchars(searchTerm);
            const searchResultsContent = document.getElementById('searchResultsContent');
            searchResultsContent.innerHTML = '<p class="loading-indicator">Searching...</p>';

            const response = await fetchData('search_quran', { search_term: searchTerm });

            if (response.success) {
                if (response.results.length === 0) {
                    searchResultsContent.innerHTML = '<p>No results found for your search term.</p>';
                    return;
                }
                let html = '<ul>';
                response.results.forEach(result => {
                    html += `<li><strong>${htmlspecialchars(result.type)} (Surah ${result.surah_id}, Ayah ${result.ayah_id}):</strong> `;
                    // Use onclick to navigate to the specific ayah in the Quran viewer
                    html += `<a href="index.php?s=${result.surah_id}&a=${result.ayah_id}" onclick="event.preventDefault(); currentSurah=${result.surah_id}; currentAyah=${result.ayah_id}; showSection('quran-viewer');">${htmlspecialchars(result.text.substring(0, 200))}${result.text.length > 200 ? '...' : ''}</a></li>`;
                });
                html += '</ul>';
                searchResultsContent.innerHTML = html;
            } else {
                displayMessage(searchResultsContent, response.message, 'error');
            }
        }
        // Listen for Enter key in search input
        document.getElementById('globalSearchInput').addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                performGlobalSearch();
            }
        });


        // --- Initial Page Load Setup ---
        document.addEventListener('DOMContentLoaded', async () => {
            // Populate surah and ayah selects first
            await populateSurahSelect();

            // Determine which section to show on initial load
            const urlHash = window.location.hash.substring(1);
            const initialSection = urlHash || 'quran-viewer'; // Default to quran-viewer if no hash

            // Show the appropriate section
            showSection(initialSection);
        });

    </script>
</body>
</html>
<?php
// Close PDO connection explicitly (optional, PHP script termination handles it, but good practice)
$pdo = null;
?>