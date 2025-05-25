<?php
/**
 * Nur Al-Quran Studio Offline - Single PHP File Edition
 * Author: Yasin Ullah, Pakistani
 *
 * This single-file application provides a Quranic study environment with
 * multi-user roles, SQLite database, and features like Quran viewing,
 * personal Tafsir, thematic linking, root analysis, and more.
 *
 * IMPORTANT: Requires PHP 7.4+ with PDO SQLite extension enabled.
 * Ensure the directory has write permissions for the SQLite database file.
 *
 * Data files (data.AM, dataENG.AM, dataBNG.AM, data5.AM) must be in the same directory.
 * Clear your browser cache if UI issues persist after updates.
 */

// --- Configuration ---
define('DB_FILE', __DIR__ . '/quran_studio.sqlite');
define('APP_NAME', 'Nur Al-Quran Studio Offline');
define('APP_VERSION', '1.0.0');

// Data file paths relative to this script
define('QURAN_URDU_FILE', 'data.AM');
define('QURAN_ENGLISH_FILE', 'dataENG.AM');
define('QURAN_BANGALI_FILE', 'dataBNG.AM');
define('WORD_TRANSLATION_FILE', 'data5.AM');

// Translation configurations
$translation_configs = [
    'urdu' => ['file' => QURAN_URDU_FILE, 'lang' => 'ur', 'dir' => 'rtl', 'label' => 'Urdu'],
    'english' => ['file' => QURAN_ENGLISH_FILE, 'lang' => 'en', 'dir' => 'ltr', 'label' => 'English'],
    'Bangali' => ['file' => QURAN_BANGALI_FILE, 'lang' => 'bn', 'dir' => 'ltr', 'label' => 'Bangali'],
];

// Surah names and ayah counts (client-side duplicated from JS for PHP-side rendering)
$surah_names = [
    "Al-Fatihah", "Al-Baqarah", "Al 'Imran", "An-Nisa'", "Al-Ma'idah", "Al-An'am", "Al-A'raf", "Al-Anfal", "At-Tawbah", "Yunus",
    "Hud", "Yusuf", "Ar-Ra'd", "Ibrahim", "Al-Hijr", "An-Nahl", "Al-Isra'", "Al-Kahf", "Maryam", "Taha",
    "Al-Anbya'", "Al-Hajj", "Al-Mu'minun", "An-Nur", "Al-Furqan", "Ash-Shu'ara'", "An-Naml", "Al-Qasas", "Al-'Ankabut", "Ar-Rum",
    "Luqman", "As-Sajdah", "Al-Ahzab", "Saba'", "Fatir", "Ya-Sin", "As-Saffat", "Sad", "Az-Zumar", "Ghafir",
    "Fussilat", "Ash-Shura", "Az-Zukhruf", "Ad-Dukhan", "Al-Jathiyah", "Al-Ahqaf", "Muhammad", "Al-Fath", "Al-Hujurat", "Qaf",
    "Adh-Dhariyat", "At-Tur", "An-Najm", "Al-Qamar", "Ar-Rahman", "Al-Waqi'ah", "Al-Hadid", "Al-Mujadilah", "Al-Hashr", "Al-Mumtahanah",
    "As-Saff", "Al-Jumu'ah", "Al-Munafiqun", "At-Taghabun", "At-Talaq", "At-Tahrim", "Al-Mulk", "Al-Qalam", "Al-Haqqah", "Al-Ma'arij",
    "Nuh", "Al-Jinn", "Al-Muzzammil", "Al-Muddaththir", "Al-Qiyamah", "Al-Insan", "Al-Mursalat", "An-Naba'", "An-Nazi'at", "'Abasa",
    "At-Takwir", "Al-Infitar", "Al-Mutaffifin", "Al-Inshiqaq", "Al-Buruj", "At-Tariq", "Al-A'la", "Al-Ghashiyah", "Al-Fajr", "Al-Balad",
    "Ash-Shams", "Al-Layl", "Ad-Duha", "Ash-Sharh", "At-Tin", "Al-'Alaq", "Al-Qadr", "Al-Bayyinah", "Az-Zalzalah", "Al-'Adiyat",
    "Al-Qari'ah", "At-Takathur", "Al-'Asr", "Al-Humazah", "Al-Fil", "Quraysh", "Al-Ma'un", "Al-Kawthar", "Al-Kafirun", "An-Nasr",
    "Al-Masad", "Al-Ikhlas", "Al-Falaq", "An-Nas"
];
$surah_ayah_counts = [
    0, 7, 286, 200, 176, 120, 165, 206, 75, 129, 109, 123, 111, 43, 52, 99, 128, 111, 110, 98, 135, 112, 78, 118, 64, 77, 227, 93, 88, 69,
    60, 34, 30, 73, 54, 45, 83, 182, 88, 75, 85, 54, 53, 89, 59, 37, 35, 38, 29, 18, 45, 60, 49, 62, 55, 78, 96, 29, 22, 24,
    13, 14, 11, 11, 18, 12, 12, 30, 52, 52, 44, 28, 28, 20, 56, 40, 31, 50, 40, 46, 42, 29, 19, 36, 25, 22, 17, 19, 26, 30,
    20, 15, 21, 11, 8, 5, 19, 5, 8, 8, 11, 11, 8, 3, 9, 5, 4, 7, 3, 6, 3, 5, 4, 5, 6
];

// --- Session & Authentication ---
session_start();

// Database connection
try {
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Database Initialization ---
function init_db(PDO $pdo, array $translation_configs) {
    // Check if tables exist, if not, create them
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table';")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('users', $tables)) {
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            email TEXT UNIQUE,
            role TEXT NOT NULL DEFAULT 'public', -- 'public', 'registered', 'ulama', 'admin'
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );");
        // Create an admin user on first setup
        $admin_pass = password_hash('adminpass', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password_hash, email, role) VALUES ('admin', '$admin_pass', 'admin@example.com', 'admin');");
        echo "Admin user 'admin' created with password 'adminpass'. Please change immediately!<br>";

        // Create a ulama user on first setup
        $ulama_pass = password_hash('ulamapass', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password_hash, email, role) VALUES ('ulama', '$ulama_pass', 'ulama@example.com', 'ulama');");
        echo "Ulama user 'ulama' created with password 'ulamapass'.<br>";

        // Create a registered user on first setup
        $reg_pass = password_hash('regpass', PASSWORD_DEFAULT);
        $pdo->exec("INSERT INTO users (username, password_hash, email, role) VALUES ('user', '$reg_pass', 'user@example.com', 'registered');");
        echo "Registered user 'user' created with password 'regpass'.<br>";
    }

    // Dynamic columns for translations in quran_ayahs (less ideal for single file, but based on prompt)
    $quran_columns = "id INTEGER PRIMARY KEY AUTOINCREMENT, surah INTEGER NOT NULL, ayah INTEGER NOT NULL, arabic_text TEXT NOT NULL";
    foreach ($translation_configs as $key => $config) {
        $quran_columns .= ", {$key}_translation TEXT";
    }
    $quran_columns .= ", UNIQUE(surah, ayah)";

    if (!in_array('quran_ayahs', $tables)) {
        $pdo->exec("CREATE TABLE quran_ayahs ($quran_columns);");
    } else {
        // Check for missing translation columns and add them
        $stmt = $pdo->query("PRAGMA table_info(quran_ayahs);");
        $existing_cols = array_column($stmt->fetchAll(), 'name');
        foreach ($translation_configs as $key => $config) {
            $col_name = "{$key}_translation";
            if (!in_array($col_name, $existing_cols)) {
                $pdo->exec("ALTER TABLE quran_ayahs ADD COLUMN $col_name TEXT;");
                echo "Added column '{$col_name}' to quran_ayahs.<br>";
            }
        }
    }

    if (!in_array('word_translations', $tables)) {
        $pdo->exec("CREATE TABLE word_translations (
            quran_text TEXT PRIMARY KEY UNIQUE NOT NULL,
            ur_meaning TEXT,
            en_meaning TEXT
        );");
    }

    if (!in_array('user_tafsir', $tables)) {
        $pdo->exec("CREATE TABLE user_tafsir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            surah INTEGER NOT NULL,
            ayah INTEGER NOT NULL,
            notes TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (user_id, surah, ayah)
        );");
    }

    if (!in_array('themes', $tables)) {
        $pdo->exec("CREATE TABLE themes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL UNIQUE,
            parent_id INTEGER REFERENCES themes(id), -- nullable
            description TEXT,
            created_by INTEGER REFERENCES users(id),
            is_approved INTEGER DEFAULT 0, -- 0: pending, 1: approved, 2: rejected
            approved_by INTEGER REFERENCES users(id),
            approval_date TEXT
        );");
    }

    if (!in_array('theme_ayah_links', $tables)) {
        $pdo->exec("CREATE TABLE theme_ayah_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            theme_id INTEGER NOT NULL REFERENCES themes(id),
            surah INTEGER NOT NULL,
            ayah INTEGER NOT NULL,
            notes TEXT,
            linked_by INTEGER NOT NULL REFERENCES users(id),
            is_approved INTEGER DEFAULT 0, -- 0: pending, 1: approved, 2: rejected
            approved_by INTEGER REFERENCES users(id),
            approval_date TEXT,
            UNIQUE (theme_id, surah, ayah, linked_by)
        );");
    }

    if (!in_array('root_notes', $tables)) {
        $pdo->exec("CREATE TABLE root_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            root_word TEXT NOT NULL,
            description TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            is_approved INTEGER DEFAULT 0,
            approved_by INTEGER REFERENCES users(id),
            approval_date TEXT,
            UNIQUE (user_id, root_word)
        );");
    }

    if (!in_array('recitation_logs', $tables)) {
        $pdo->exec("CREATE TABLE recitation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            surah INTEGER NOT NULL,
            ayah_start INTEGER,
            ayah_end INTEGER,
            qari TEXT,
            recitation_date TEXT NOT NULL,
            notes TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        );");
    }

    if (!in_array('hifz_tracking', $tables)) {
        $pdo->exec("CREATE TABLE hifz_tracking (
            user_id INTEGER NOT NULL REFERENCES users(id),
            surah INTEGER NOT NULL,
            ayah INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'not-started', -- 'not-started', 'in-progress', 'memorized'
            last_review_date TEXT,
            next_review_date TEXT,
            review_count INTEGER DEFAULT 0,
            notes TEXT,
            PRIMARY KEY (user_id, surah, ayah)
        );");
    }

    if (!in_array('app_settings', $tables)) {
        $pdo->exec("CREATE TABLE app_settings (
            setting_key TEXT PRIMARY KEY UNIQUE NOT NULL,
            setting_value TEXT
        );");
    }
}

// --- Data Loading Functions ---

// Function to parse .AM files (Quran text/translation)
function parse_am_file(string $file_path): array {
    $data = [];
    if (!file_exists($file_path)) {
        return [];
    }
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode(' ترجمہ: ', $line);
        if (count($parts) < 2) continue;

        $arabic_part = trim($parts[0]);
        $rest = $parts[1];

        if (preg_match('/<br\/>س (\d{3}) آ (\d{3})$/', $rest, $matches)) {
            $translation_part = trim(substr($rest, 0, strlen($rest) - strlen($matches[0])));
            $surah_num = (int)$matches[1];
            $ayah_num = (int)$matches[2];

            if ($surah_num >= 1 && $surah_num <= 114 && $ayah_num >= 1) {
                $data[] = [
                    'surah' => $surah_num,
                    'ayah' => $ayah_num,
                    'arabic' => $arabic_part,
                    'translation' => $translation_part,
                ];
            }
        }
    }
    return $data;
}

// Function to parse data5.AM (word-by-word)
function parse_word_translation_file(string $file_path): array {
    $data = [];
    if (!file_exists($file_path)) {
        return [];
    }
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($lines)) return [];

    $headers = str_getcsv($lines[0]); // quran_text,ur_meaning,en_meaning
    for ($i = 1; $i < count($lines); $i++) {
        $row = str_getcsv($lines[$i]);
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }
    return $data;
}

// Utility for Arabic word normalization (for fuzzy matching)
function generate_arabic_regex(string $word): string {
    $pattern = trim($word);
    $pattern = preg_replace('/[\x{064B}-\x{0652}\x{0670}]/u', '', $pattern); // Remove Harakat

    // Apply flexible character replacements for regex pattern
    $pattern = str_replace(
        ['ؤ', 'و', 'ك', 'ک', 'آ', 'ا', 'أ', 'إ', 'ى', 'ی', 'ي', 'ہ', 'ھ', 'ة', 'ۃ', 'ه', 'ے', 'مٰ'],
        ['(?:و|ؤ)', '(?:و|ؤ)', '(?:ك|ک)', '(?:ك|ک)', '(?:آ|ا|أ|إ)', '(?:آ|ا|أ|إ)', '(?:آ|ا|أ|إ)', '(?:آ|ا|أ|إ)', '(?:ى|ی|ي)', '(?:ى|ی|ي)', '(?:ى|ی|ي)', '(?:ہ|ھ|ة|ۃ|ه)', '(?:ہ|ھ|ة|ۃ|ه)', '(?:ہ|ھ|ة|ۃ|ه)', '(?:ہ|ھ|ة|ۃ|ه)', '(?:ہ|ھ|ة|ۃ|ه)', '(?:ے|ی)', '(?:مٰ|م)'],
        $pattern
    );

    // Escape any remaining regex special characters
    $pattern = preg_quote($pattern, '/');

    return "/^{$pattern}$/ui"; // 'u' for UTF-8, 'i' for case-insensitive (though less relevant for Arabic)
}

function load_initial_data(PDO $pdo, array $translation_configs) {
    global $surah_ayah_counts; // Needed for count check

    // Check if quran_ayahs is already populated
    $stmt = $pdo->query("SELECT COUNT(*) FROM quran_ayahs;");
    $ayah_count = $stmt->fetchColumn();

    if ($ayah_count < array_sum($surah_ayah_counts)) { // Simple check, total ayahs in Quran is 6236
        echo "<h3>Importing Quran data... This may take a moment.</h3>";
        $pdo->beginTransaction();
        try {
            $quran_insert_stmt = $pdo->prepare("INSERT OR REPLACE INTO quran_ayahs (surah, ayah, arabic_text, urdu_translation, english_translation, Bangali_translation) VALUES (:surah, :ayah, :arabic_text, :urdu_translation, :english_translation, :Bangali_translation);");

            // Prepare for batch updates
            $ayahs_data = []; // surah-ayah => [arabic, urdu, english, Bangali]

            foreach ($translation_configs as $lang_key => $config) {
                $file_data = parse_am_file($config['file']);
                foreach ($file_data as $row) {
                    $key = "{$row['surah']}-{$row['ayah']}";
                    if (!isset($ayahs_data[$key])) {
                        $ayahs_data[$key] = [
                            'surah' => $row['surah'],
                            'ayah' => $row['ayah'],
                            'arabic_text' => $row['arabic'],
                            'urdu_translation' => '',
                            'english_translation' => '',
                            'Bangali_translation' => '',
                        ];
                    }
                    $ayahs_data[$key]["{$lang_key}_translation"] = $row['translation'];
                }
            }

            foreach ($ayahs_data as $data) {
                $quran_insert_stmt->execute([
                    ':surah' => $data['surah'],
                    ':ayah' => $data['ayah'],
                    ':arabic_text' => $data['arabic_text'],
                    ':urdu_translation' => $data['urdu_translation'],
                    ':english_translation' => $data['english_translation'],
                    ':Bangali_translation' => $data['Bangali_translation'],
                ]);
            }
            echo "Quran Ayahs imported successfully.<br>";
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error importing Quran data: " . $e->getMessage() . "<br>";
            die();
        }
    } else {
        echo "Quran Ayahs already populated.<br>";
    }

    // Check if word_translations is populated
    $stmt = $pdo->query("SELECT COUNT(*) FROM word_translations;");
    $word_count = $stmt->fetchColumn();
    if ($word_count === 0) {
        echo "<h3>Importing Word-by-Word data...</h3>";
        $pdo->beginTransaction();
        try {
            $word_data = parse_word_translation_file(WORD_TRANSLATION_FILE);
            $word_insert_stmt = $pdo->prepare("INSERT OR REPLACE INTO word_translations (quran_text, ur_meaning, en_meaning) VALUES (:quran_text, :ur_meaning, :en_meaning);");
            foreach ($word_data as $row) {
                $word_insert_stmt->execute([
                    ':quran_text' => $row['quran_text'],
                    ':ur_meaning' => $row['ur_meaning'],
                    ':en_meaning' => $row['en_meaning'],
                ]);
            }
            echo "Word-by-Word data imported successfully.<br>";
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "Error importing Word-by-Word data: " . $e->getMessage() . "<br>";
            die();
        }
    } else {
        echo "Word-by-Word data already populated.<br>";
    }
}

// --- User Management & Auth Functions ---
function get_current_user(): array {
    return $_SESSION['user'] ?? ['id' => 0, 'username' => 'Guest', 'role' => 'public'];
}

function is_authenticated(): bool {
    return isset($_SESSION['user_id']);
}

function check_role(string $required_role): bool {
    $user_role = get_current_user()['role'];
    $roles_hierarchy = ['public' => 0, 'registered' => 1, 'ulama' => 2, 'admin' => 3];
    return $roles_hierarchy[$user_role] >= $roles_hierarchy[$required_role];
}

function login_user(PDO $pdo, string $username, string $password): ?string {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username;");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user'] = $user; // Store full user data in session
        return null; // Success
    }
    return "Invalid username or password.";
}

function register_user(PDO $pdo, string $username, string $password, string $email): ?string {
    if (empty($username) || empty($password) || empty($email)) {
        return "All fields are required.";
    }
    if (strlen($password) < 6) {
        return "Password must be at least 6 characters.";
    }
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (:username, :password_hash, :email, 'registered');");
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => $password_hash,
            ':email' => $email
        ]);
        return null; // Success
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) { // SQLite constraint violation for UNIQUE
            return "Username or email already exists.";
        }
        return "Registration failed: " . $e->getMessage();
    }
}

function logout_user() {
    session_unset();
    session_destroy();
    header('Location: index.php'); // Redirect to home
    exit();
}

// --- API & Action Handlers (for PHP-side processing) ---
$action = $_GET['action'] ?? null;
$message = null; // For displaying messages to the user

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($action) {
        case 'login':
            $message = login_user($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '');
            if (!$message) header('Location: index.php?page=quran'); // Redirect on success
            break;
        case 'register':
            $message = register_user($pdo, $_POST['username'] ?? '', $_POST['password'] ?? '', $_POST['email'] ?? '');
            if (!$message) $message = "Registration successful! You can now log in.";
            break;
        case 'logout':
            logout_user(); // Handles redirect
            break;
        case 'save_tafsir':
            if (check_role('registered')) {
                $user_id = get_current_user()['id'];
                $surah = $_POST['surah'] ?? 0;
                $ayah = $_POST['ayah'] ?? 0;
                $notes = $_POST['notes'] ?? '';
                if ($surah && $ayah && $notes) {
                    $stmt = $pdo->prepare("INSERT OR REPLACE INTO user_tafsir (user_id, surah, ayah, notes, updated_at) VALUES (:user_id, :surah, :ayah, :notes, CURRENT_TIMESTAMP);");
                    $stmt->execute([':user_id' => $user_id, ':surah' => $surah, ':ayah' => $ayah, ':notes' => $notes]);
                    $message = "Tafsir saved!";
                } else {
                    $message = "Error: Missing Tafsir data.";
                }
            } else {
                $message = "You must be logged in to save Tafsir.";
            }
            break;
        case 'add_theme':
            if (check_role('registered')) {
                $name = trim($_POST['new_theme_name'] ?? '');
                $parent_id = !empty($_POST['parent_theme_select']) ? (int)$_POST['parent_theme_select'] : null;
                if ($name) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO themes (name, parent_id, created_by) VALUES (:name, :parent_id, :created_by);");
                        $stmt->execute([':name' => $name, ':parent_id' => $parent_id, ':created_by' => get_current_user()['id']]);
                        $message = "Theme added!";
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) $message = "Theme name already exists.";
                        else $message = "Error adding theme: " . $e->getMessage();
                    }
                } else { $message = "Theme name cannot be empty."; }
            } else { $message = "You must be logged in to add themes."; }
            break;
        case 'link_ayah_to_theme':
            if (check_role('registered')) {
                $theme_id = (int)($_POST['link_theme_select'] ?? 0);
                $surah = (int)($_POST['current_surah_link'] ?? 0);
                $ayah = (int)($_POST['current_ayah_link'] ?? 0);
                $notes = trim($_POST['theme_link_notes'] ?? '');
                if ($theme_id && $surah && $ayah) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO theme_ayah_links (theme_id, surah, ayah, notes, linked_by) VALUES (:theme_id, :surah, :ayah, :notes, :linked_by);");
                        $stmt->execute([':theme_id' => $theme_id, ':surah' => $surah, ':ayah' => $ayah, ':notes' => $notes, ':linked_by' => get_current_user()['id']]);
                        $message = "Ayah linked to theme!";
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) $message = "Ayah already linked to this theme by you.";
                        else $message = "Error linking Ayah: " . $e->getMessage();
                    }
                } else { $message = "Missing data for linking Ayah to theme."; }
            } else { $message = "You must be logged in to link Ayahs."; }
            break;
        case 'admin_import_quran_data':
            if (check_role('admin')) {
                // This will trigger the initial data loading logic (which checks if populated)
                load_initial_data($pdo, $translation_configs);
                $message = "Initial data import process triggered.";
            } else {
                $message = "Unauthorized access.";
            }
            break;
        // Add other POST handlers for recitation logs, hifz tracking, etc.
    }
}

// --- Data Fetching Functions (Server-side) ---

function get_ayah_data(PDO $pdo, int $surah, int $ayah, array $translation_configs): ?array {
    $columns = "arabic_text";
    foreach ($translation_configs as $key => $config) {
        $columns .= ", {$key}_translation";
    }
    $stmt = $pdo->prepare("SELECT $columns FROM quran_ayahs WHERE surah = :surah AND ayah = :ayah;");
    $stmt->execute([':surah' => $surah, ':ayah' => $ayah]);
    $data = $stmt->fetch();
    return $data ?: null;
}

function get_user_tafsir(PDO $pdo, int $user_id, int $surah, int $ayah): ?string {
    $stmt = $pdo->prepare("SELECT notes FROM user_tafsir WHERE user_id = :user_id AND surah = :surah AND ayah = :ayah;");
    $stmt->execute([':user_id' => $user_id, ':surah' => $surah, ':ayah' => $ayah]);
    return $stmt->fetchColumn() ?: null;
}

function get_all_themes(PDO $pdo, int $user_id = 0): array {
    // For now, return themes created by the current user OR approved public ones (if Ulama/Admin)
    $sql = "SELECT id, name, parent_id, description FROM themes WHERE created_by = :user_id";
    if (check_role('ulama')) { // Ulama and Admin can see all, eventually filter by approved
         $sql = "SELECT id, name, parent_id, description FROM themes ORDER BY name;";
         $stmt = $pdo->query($sql);
    } else {
         $sql .= " ORDER BY name;";
         $stmt = $pdo->prepare($sql);
         $stmt->execute([':user_id' => $user_id]);
    }
    return $stmt->fetchAll();
}

function get_linked_ayahs_for_theme(PDO $pdo, int $theme_id, int $user_id = 0): array {
    $sql = "SELECT surah, ayah, notes FROM theme_ayah_links WHERE theme_id = :theme_id";
    if (!check_role('ulama')) { // Guests/Registered users only see their own (or approved if public)
         $sql .= " AND linked_by = :user_id"; // Simple filtering for now
    }
    $sql .= " ORDER BY surah, ayah;";
    $stmt = $pdo->prepare($sql);
    if (!check_role('ulama')) {
        $stmt->execute([':theme_id' => $theme_id, ':user_id' => $user_id]);
    } else {
        $stmt->execute([':theme_id' => $theme_id]);
    }
    return $stmt->fetchAll();
}

function get_word_translation(PDO $pdo, string $quran_text_raw): ?array {
    $stmt = $pdo->prepare("SELECT ur_meaning, en_meaning FROM word_translations WHERE quran_text = :quran_text;");
    $stmt->execute([':quran_text' => $quran_text_raw]);
    return $stmt->fetch();
}


// --- Main Application Logic ---
init_db($pdo, $translation_configs); // Initialize database on every run (safe to re-run)

// Load current user data into session
if (isset($_SESSION['user_id']) && !isset($_SESSION['user'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id;");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $_SESSION['user'] = $stmt->fetch();
}

$current_user = get_current_user();
$current_page = $_GET['page'] ?? 'quran';

// Current Surah/Ayah for Quran Viewer & Tafsir (from GET or default)
$current_surah = isset($_GET['s']) ? (int)$_GET['s'] : 1;
$current_ayah = isset($_GET['a']) ? (int)$_GET['a'] : 1;
$selected_translation_key = $_GET['tl'] ?? 'urdu'; // tl = translation language

// Fetch current ayah data for display
$current_ayah_data = get_ayah_data($pdo, $current_surah, $current_ayah, $translation_configs);

// Fetch user's tafsir for current ayah
$current_tafsir_notes = null;
if (is_authenticated()) {
    $current_tafsir_notes = get_user_tafsir($pdo, $current_user['id'], $current_surah, $current_ayah);
}

// Fetch all themes for selects
$all_themes = get_all_themes($pdo, $current_user['id']);

// Fetch active theme for displaying linked ayahs in themes section
$active_theme_id_for_display = $_GET['theme_id'] ?? ($all_themes[0]['id'] ?? 0);
$linked_ayahs_for_active_theme = [];
if ($active_theme_id_for_display > 0) {
    $linked_ayahs_for_active_theme = get_linked_ayahs_for_theme($pdo, (int)$active_theme_id_for_display, $current_user['id']);
}
$active_theme_name_for_display = array_values(array_filter($all_themes, fn($t) => $t['id'] == $active_theme_id_for_display));
$active_theme_name_for_display = $active_theme_name_for_display[0]['name'] ?? 'N/A';

// --- HTML Structure ---
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - By Yasin Ullah</title>
    <meta name="author" content="Yasin Ullah, Pakistani">
    <meta name="description" content="An offline-first, client-side Quranic study environment with personal Tafsir, thematic linking, root analysis, Hifz tracking, and advanced search.">

    <style>
        /* General Styles & Reset */
        :root {
            /* Default Theme: Serene Digital Mosque */
            --color-bg-primary: #e8f5e9; /* Light Green */
            --color-bg-secondary: #c8e6c9; /* Lighter Green */
            --color-text-primary: #1b5e20; /* Dark Green */
            --color-text-secondary: #388e3c; /* Medium Green */
            --color-accent: #4caf50; /* Green */
            --color-accent-dark: #388e3c; /* Darker Green */
            --color-border: #a5d6a7; /* Light Green Border */
            --color-shadow: rgba(0, 0, 0, 0.1);
            --color-highlight: #fff9c4; /* Light Yellow */
            --color-error: #ef5350; /* Red */
            --color-success: #66bb6a; /* Green */
            --font-arabic: 'Scheherazade New', 'Lateef', 'Amiri', 'Traditional Arabic', calibri; /* Preferred Arabic fonts */
            --font-urdu: 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Pak Nastaleeq', calibri; /* Preferred Urdu fonts */
            --font-Bangali: 'Noto Sans Bangali', 'Arial', calibri; /* Bangali fonts */
            --font-english: 'Roboto', 'Segoe UI', calibri; /* English font */
            --font-general: 'Roboto', 'Segoe UI', calibri; /* General UI font */
            --border-radius: 8px;
            --padding-main: 20px;
            --transition-speed: 0.3s;
        }

        /* Ancient Illuminated Manuscript Theme */
        body.theme-manuscript {
            --color-bg-primary: #f5f5dc; /* Beige/Parchment */
            --color-bg-secondary: #fff8dc; /* Cornsilk */
            --color-text-primary: #5d4037; /* Dark Brown */
            --color-text-secondary: #795548; /* Brown */
            --color-accent: #ffb300; /* Amber */
            --color-accent-dark: #fb8c00; /* Dark Amber */
            --color-border: #d7ccc8; /* Light Brown */
            --color-shadow: rgba(0, 0, 0, 0.15);
            --color-highlight: #ffe082; /* Light Amber */
            --color-error: #c62828; /* Dark Red */
            --color-success: #388e3c; /* Dark Green */
            --font-arabic: 'Scheherazade New', calibri;
            --font-urdu: 'Jameel Noori Nastaleeq', calibri;
            --font-Bangali: 'Noto Sans Bangali', calibri;
            --font-english: 'Merriweather', calibri;
            --font-general: 'Merriweather', calibri;
        }

        /* Futuristic Holo-Quran Theme */
        body.theme-holo {
            --color-bg-primary: #0d1a2b; /* Dark Blue */
            --color-bg-secondary: #1a2b3c; /* Slightly Lighter Blue */
            --color-text-primary: #e0f7fa; /* Cyan */
            --color-text-secondary: #b2ebf2; /* Lighter Cyan */
            --color-accent: #00bcd4; /* Cyan */
            --color-accent-dark: #00838f; /* Dark Cyan */
            --color-border: #26a69a; /* Teal */
            --color-shadow: rgba(0, 188, 212, 0.2); /* Cyan shadow */
            --color-highlight: #80deea; /* Light Cyan */
            --color-error: #ff5252; /* Red */
            --color-success: #00e676; /* Green */
            --font-arabic: 'Orbitron', calibri; /* Futuristic Arabic (placeholder, needs actual font) */
            --font-urdu: 'Orbitron', calibri; /* Placeholder */
            --font-Bangali: 'Orbitron', calibri;
            --font-english: 'Orbitron', calibri;
            --font-general: 'Orbitron', calibri;
            --border-radius: 4px;
        }


        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-general);
            line-height: 1.6;
            color: var(--color-text-primary);
            background-color: var(--color-bg-primary);
            transition: background-color var(--transition-speed), color var(--transition-speed);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-y: scroll; /* Allow scrolling on body */
        }

        h1, h2, h3, h4, h5, h6 {
            color: var(--color-text-secondary);
            margin-bottom: 15px;
        }

        button, input[type="submit"], input[type="button"] {
            font-family: var(--font-general);
            background-color: var(--color-accent);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: background-color var(--transition-speed), opacity var(--transition-speed);
            font-size: 1rem;
        }

        button:hover, input[type="submit"]:hover, input[type="button"]:hover {
            background-color: var(--color-accent-dark);
            opacity: 0.9;
        }
         button:focus, input[type="submit"]:focus, input[type="button"]:focus {
            outline: 2px solid var(--color-accent-dark);
            outline-offset: 2px;
        }

        input[type="text"], input[type="number"], textarea, select {
            font-family: var(--font-general);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
            width: 100%;
            max-width: 400px; /* Limit width for forms */
        }
         input[type="text"]:focus, input[type="number"]:focus, textarea:focus, select:focus {
            outline: 2px solid var(--color-accent);
            border-color: var(--color-accent);
         }

        textarea {
            min-height: 150px;
            resize: vertical;
            max-width: 100%;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--color-text-secondary);
        }

        a {
            color: var(--color-accent-dark);
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        /* Layout */
        .container {
            display: flex;
            flex-grow: 1; /* Allow container to take up available space */
            padding: var(--padding-main);
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            margin-right: var(--padding-main);
            flex-shrink: 0;
            background-color: var(--color-bg-secondary);
            padding: var(--padding-main);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px var(--color-shadow);
        }

        .main-content {
            flex-grow: 1;
            background-color: var(--color-bg-secondary);
            padding: var(--padding-main);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px var(--color-shadow);
            overflow-y: auto; /* Allow main content area to scroll */
            /* max-height: calc(100vh - (var(--padding-main) * 2) - 60px); */ /* Adjust based on header height */
        }

        header {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
            padding: 15px var(--padding-main);
            box-shadow: 0 2px 5px var(--color-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-shrink: 0; /* Prevent header from shrinking */
        }

        header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--color-text-primary);
        }

        nav ul {
            list-style: none;
            padding: 0;
        }

        nav ul li {
            margin-bottom: 10px;
        }

        nav a {
            display: block;
            padding: 10px;
            background-color: var(--color-bg-primary);
            border-radius: var(--border-radius);
            color: var(--color-text-primary);
            transition: background-color var(--transition-speed), color var(--transition-speed);
        }

        nav a:hover, nav a.active {
            background-color: var(--color-accent);
            color: white;
            text-decoration: none;
        }

        /* Specific Sections */
        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

        /* Quran Section */
        .quran-viewer h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        .ayah {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-primary);
            transition: background-color var(--transition-speed);
        }

        .ayah:hover {
             background-color: var(--color-highlight);
        }

        .ayah-number {
            font-weight: bold;
            color: var(--color-accent-dark);
            margin-bottom: 10px;
            display: block;
            text-align: center;
        }

        .ayah-arabic {
            font-family: var(--font-arabic);
            font-size: 1.8rem;
            text-align: right;
            direction: rtl;
            margin-bottom: 10px;
            line-height: 2.5; /* Increased line height for clarity */
        }

        .ayah-arabic span {
            cursor: pointer;
            padding: 2px 4px;
            border-bottom: 1px dashed transparent;
            transition: background-color 0.2s, border-bottom-color 0.2s;
        }

        .ayah-arabic span:hover {
            background-color: rgba(var(--color-accent-dark-rgb, 56, 142, 60), 0.2); /* Use RGBA for hover */
            border-bottom-color: var(--color-accent-dark);
        }
        /* Add RGB variables for themes */
        :root { --color-accent-dark-rgb: 56, 142, 60; } /* Serene */
        body.theme-manuscript { --color-accent-dark-rgb: 251, 140, 0; } /* Manuscript */
        body.theme-holo { --color-accent-dark-rgb: 0, 131, 143; } /* Holo */


        .ayah-translation {
            /* These will be set dynamically by JS based on selected translation */
            font-size: 1.1rem;
            color: var(--color-text-secondary);
        }

        .word-translation-tooltip {
            position: absolute;
            background-color: var(--color-accent-dark);
            color: white;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            z-index: 100;
            pointer-events: none; /* Don't interfere with clicks */
            opacity: 0;
            transition: opacity 0.2s;
        }

        .word-translation-tooltip.visible {
            opacity: 1;
        }

        /* Tafsir Builder */
        .tafsir-editor {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--color-border);
        }

        .tafsir-editor textarea {
            width: 100%;
            max-width: 100%;
            margin-bottom: 10px;
        }

        /* Thematic Linker */
        .theme-manager, .theme-linker {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-primary);
        }

        .theme-list ul {
            list-style: none;
            padding-left: 20px;
        }
        .theme-list li {
            margin-bottom: 5px;
        }
        .theme-list li span {
             cursor: pointer;
             color: var(--color-text-secondary);
             transition: color var(--transition-speed);
        }
        .theme-list li span:hover {
             color: var(--color-accent-dark);
             text-decoration: underline;
        }
        .theme-list .theme-actions button {
            padding: 3px 8px;
            font-size: 0.8rem;
            margin-left: 5px;
        }

        /* Root Word Analyzer */
        .root-analyzer-form {
            margin-bottom: 20px;
        }
        .root-results ul {
            list-style: none;
            padding: 0;
        }
        .root-results li {
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-primary);
            font-size: 1.5rem;
        }

        /* Recitation Log */
        .recitation-log-form {
             margin-bottom: 20px;
        }
        .recitation-list ul {
            list-style: none;
            padding: 0;
        }
        .recitation-list li {
             margin-bottom: 10px;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-primary);
        }

        /* Memorization Hub */
        .hifz-ayah-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            margin-left: 10px;
        }
        .status-not-started { background-color: #e0e0e0; color: #424242; }
        .status-in-progress { background-color: #fff59d; color: #fbc02d; }
        .status-memorized { background-color: #a5d6a7; color: #388e3c; }

        /* Advanced Search */
        .search-options label {
            display: inline-block;
            margin-right: 15px;
            font-weight: normal;
        }
        .search-results ul {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }
        .search-results li {
             margin-bottom: 10px;
            padding: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-primary);
            font-size: x-large;
        }
        .search-results .result-context {
            font-size: large;
            color: var(--color-text-secondary);
            margin-top: 5px;
        }

        /* Data Management / Settings */
        .settings-section {
            margin-bottom: 20px;
        }

        /* Modals */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: var(--color-bg-secondary);
            padding: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 5px 15px var(--color-shadow);
            max-width: 600px;
            width: 90%;
            position: relative;
        }

        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            color: var(--color-text-secondary);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }

        /* Loading Indicator */
        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            font-size: 1.5rem;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            display: none; /* Hidden by default */
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }
        .flex-group { display: flex; gap: 10px; align-items: center; } /* For buttons/inputs side-by-side */


        /* Accessibility (WCAG 2.1 AAA considerations) */
        [tabindex="0"]:focus, button:focus, input:focus, select:focus, textarea:focus, a:focus {
            outline: 3px solid var(--color-accent-dark); /* Stronger focus indicator */
            outline-offset: 2px;
        }

        /* Ensure sufficient contrast - relies on theme colors being chosen correctly */

        /* Screen reader only class */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }

        /* RTL adjustments */
        [dir="rtl"] .ayah-arabic, [dir="rtl"] .ayah-translation {
            text-align: right;
        }
         [dir="rtl"] .sidebar {
            margin-right: 0;
            margin-left: var(--padding-main);
         }
         [dir="rtl"] .theme-list ul {
            padding-left: 0;
            padding-right: 20px;
         }
         [dir="rtl"] .theme-list .theme-actions button {
            margin-left: 0;
            margin-right: 5px;
         }
        [dir="rtl"] .hifz-ayah-status {
            margin-left: 0;
            margin-right: 10px;
        }
         [dir="rtl"] .search-options label {
            margin-right: 0;
            margin-left: 15px;
         }


        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                padding: 10px;
            }
            .sidebar {
                width: 100%;
                margin-right: 0;
                margin-bottom: 20px;
            }
             [dir="rtl"] .sidebar {
                margin-left: 0;
                margin-bottom: 20px;
             }
            .main-content {
                padding: 15px;
                 max-height: calc(100vh - (10px * 2) - 60px - 20px - 20px); /* Adjust for mobile padding, header, sidebar margin */
            }
            header {
                flex-direction: column;
                align-items: flex-start;
                padding: 10px;
            }
            header h1 {
                margin-bottom: 10px;
            }
            nav ul {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
            }
            nav ul li {
                margin-bottom: 0;
            }
            nav a {
                padding: 8px 12px;
                font-size: 0.9rem;
            }
             input[type="text"], input[type="number"], textarea, select {
                max-width: 100%;
            }
             .flex-group {
                flex-direction: column;
                gap: 10px;
             }
             .flex-group button, .flex-group input {
                width: 100%;
             }
        }

        /* Chronospatial/Bioluminescent Simulation (Basic) */
        body.theme-holo .ayah:hover {
             background: linear-gradient(90deg, rgba(0,188,212,0.1) 0%, rgba(0,188,212,0.05) 100%);
        }
        body.theme-holo .ayah-arabic span:hover {
             background-color: rgba(0, 188, 212, 0.3);
             border-bottom-color: var(--color-highlight);
        }
        body.theme-holo .word-translation-tooltip {
             background-color: var(--color-accent);
             box-shadow: 0 0 10px var(--color-accent);
        }
         body.theme-holo nav a.active {
            background-color: var(--color-accent);
            box-shadow: 0 0 8px var(--color-accent);
         }
    </style>
    <!-- Optional: Include fonts if not available locally -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@404&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Jameel+Noori+Nastaleeq+Regular&display=swap">
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap">
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bangali&display=swap">
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron&display=swap">
     <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap">
</head>
<body dir="ltr">

    <?php if ($message): ?>
    <div style="background-color: <?php echo strpos($message, 'Error') !== false || strpos($message, 'Invalid') !== false ? 'var(--color-error)' : 'var(--color-success)'; ?>; color: white; padding: 10px; text-align: center; font-weight: bold; position: fixed; width: 100%; z-index: 9999;">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <div id="loading-overlay" style="display: flex;">Loading app data... Please wait.</div>

    <header>
        <h1><?php echo APP_NAME; ?></h1>
        <div class="header-controls">
            <span style="margin-right: 15px;">Welcome, <strong><?php echo htmlspecialchars($current_user['username']); ?></strong> (<?php echo ucfirst($current_user['role']); ?>)</span>
            <?php if (is_authenticated()): ?>
                <form action="index.php?action=logout" method="POST" style="display: inline;">
                    <button type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a href="index.php?page=login" style="margin-right: 10px; color: var(--color-accent-dark);">Login</a>
                <a href="index.php?page=register" style="color: var(--color-accent-dark);">Register</a>
            <?php endif; ?>
             <label for="theme-switcher" class="sr-only">Choose Theme</label>
            <select id="theme-switcher" aria-label="Choose Theme">
                <option value="serene">Serene Digital Mosque</option>
                <option value="manuscript">Ancient Illuminated Manuscript</option>
                <option value="holo">Futuristic Holo-Quran</option>
            </select>
        </div>
    </header>

    <div class="container">
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><a href="index.php?page=quran" class="nav-link <?php echo ($current_page == 'quran') ? 'active' : ''; ?>" data-section="quran">Quran Viewer</a></li>
                    <?php if (check_role('registered')): ?>
                        <li><a href="index.php?page=tafsir" class="nav-link <?php echo ($current_page == 'tafsir') ? 'active' : ''; ?>" data-section="tafsir">Personal Tafsir</a></li>
                        <li><a href="index.php?page=themes" class="nav-link <?php echo ($current_page == 'themes') ? 'active' : ''; ?>" data-section="themes">Thematic Linker</a></li>
                        <li><a href="index.php?page=roots" class="nav-link <?php echo ($current_page == 'roots') ? 'active' : ''; ?>" data-section="roots">Root Word Analyzer</a></li>
                        <li><a href="index.php?page=recitation" class="nav-link <?php echo ($current_page == 'recitation') ? 'active' : ''; ?>" data-section="recitation">Recitation Log</a></li>
                        <li><a href="index.php?page=hifz" class="nav-link <?php echo ($current_page == 'hifz') ? 'active' : ''; ?>" data-section="hifz">Memorization Hub</a></li>
                        <li><a href="index.php?page=search" class="nav-link <?php echo ($current_page == 'search') ? 'active' : ''; ?>" data-section="search">Advanced Search</a></li>
                        <li><a href="index.php?page=data" class="nav-link <?php echo ($current_page == 'data') ? 'active' : ''; ?>" data-section="data">Data Management</a></li>
                    <?php endif; ?>
                    <?php if (check_role('ulama')): ?>
                        <li><a href="index.php?page=approval" class="nav-link <?php echo ($current_page == 'approval') ? 'active' : ''; ?>" data-section="approval">Approval Queue</a></li>
                    <?php endif; ?>
                    <?php if (check_role('admin')): ?>
                        <li><a href="index.php?page=admin_panel" class="nav-link <?php echo ($current_page == 'admin_panel') ? 'active' : ''; ?>" data-section="admin_panel">Admin Panel</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>

        <main class="main-content">
            <?php if ($current_page === 'login'): ?>
                <section id="login" class="section active" role="region" aria-labelledby="login-heading">
                    <h2 id="login-heading">Login</h2>
                    <form action="index.php?action=login" method="POST">
                        <label for="login-username">Username:</label>
                        <input type="text" id="login-username" name="username" required>
                        <label for="login-password">Password:</label>
                        <input type="password" id="login-password" name="password" required>
                        <button type="submit">Login</button>
                    </form>
                    <p class="mt-20">Don't have an account? <a href="index.php?page=register">Register here</a></p>
                </section>
            <?php elseif ($current_page === 'register'): ?>
                 <section id="register" class="section active" role="region" aria-labelledby="register-heading">
                    <h2 id="register-heading">Register</h2>
                    <form action="index.php?action=register" method="POST">
                        <label for="register-username">Username:</label>
                        <input type="text" id="register-username" name="username" required>
                        <label for="register-email">Email:</label>
                        <input type="email" id="register-email" name="email" required>
                        <label for="register-password">Password:</label>
                        <input type="password" id="register-password" name="password" required>
                        <button type="submit">Register</button>
                    </form>
                    <p class="mt-20">Already have an account? <a href="index.php?page=login">Login here</a></p>
                </section>
            <?php elseif ($current_page === 'quran'): ?>
                <section id="quran" class="section active" role="region" aria-labelledby="quran-heading">
                    <h2 id="quran-heading">Quran Viewer</h2>
                    <div class="quran-controls flex-group mb-20">
                        <label for="surah-select" class="sr-only">Select Surah</label>
                        <select id="surah-select" aria-label="Select Surah" onchange="window.location.href = 'index.php?page=quran&s=' + this.value + '&a=1&tl=' + document.getElementById('translation-select').value;">
                            <?php for ($s = 1; $s <= 114; $s++): ?>
                                <option value="<?php echo $s; ?>" <?php echo ($current_surah == $s) ? 'selected' : ''; ?>><?php echo $s . '. ' . $surah_names[$s-1]; ?></option>
                            <?php endfor; ?>
                        </select>
                        <label for="ayah-select" class="sr-only">Select Ayah</label>
                        <select id="ayah-select" aria-label="Select Ayah" onchange="window.location.href = 'index.php?page=quran&s=' + document.getElementById('surah-select').value + '&a=' + this.value + '&tl=' + document.getElementById('translation-select').value;">
                            <?php for ($a = 1; $a <= $surah_ayah_counts[$current_surah]; $a++): ?>
                                <option value="<?php echo $a; ?>" <?php echo ($current_ayah == $a) ? 'selected' : ''; ?>><?php echo $a; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                     <div class="quran-controls flex-group mb-20">
                         <label for="translation-select" class="sr-only">Select Translation</label>
                         <select id="translation-select" aria-label="Select Translation" onchange="window.location.href = 'index.php?page=quran&s=<?php echo $current_surah; ?>&a=<?php echo $current_ayah; ?>&tl=' + this.value;">
                             <?php foreach ($translation_configs as $key => $config): ?>
                                <option value="<?php echo $key; ?>" <?php echo ($selected_translation_key == $key) ? 'selected' : ''; ?>><?php echo $config['label']; ?></option>
                             <?php endforeach; ?>
                         </select>
                     </div>

                    <div id="quran-display" class="quran-viewer" lang="ar" dir="rtl">
                        <?php if ($current_ayah_data): ?>
                            <div class="ayah" data-surah="<?php echo $current_surah; ?>" data-ayah="<?php echo $current_ayah; ?>">
                                <div class="ayah-number">Surah <?php echo $current_surah; ?>:<?php echo $current_ayah; ?> (<?php echo $surah_names[$current_surah-1]; ?>)</div>
                                <div class="ayah-arabic" lang="ar" dir="rtl">
                                    <?php
                                    $arabic_words = preg_split('/\s+/u', $current_ayah_data['arabic_text'], -1, PREG_SPLIT_NO_EMPTY);
                                    foreach ($arabic_words as $word) {
                                        echo '<span data-word="' . htmlspecialchars($word) . '" tabindex="0" role="button">' . htmlspecialchars($word) . ' </span>';
                                    }
                                    ?>
                                </div>
                                <div class="ayah-translation" lang="<?php echo $translation_configs[$selected_translation_key]['lang']; ?>" dir="<?php echo $translation_configs[$selected_translation_key]['dir']; ?>" style="font-family: var(--font-<?php echo $selected_translation_key; ?>); text-align: <?php echo $translation_configs[$selected_translation_key]['dir'] == 'rtl' ? 'right' : 'left'; ?>;">
                                    <?php echo htmlspecialchars($current_ayah_data["{$selected_translation_key}_translation"]); ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-center" style="color: var(--color-error);">Ayah <?php echo $current_surah; ?>:<?php echo $current_ayah; ?> not found.</p>
                        <?php endif; ?>
                    </div>
                     <div id="word-translation-area" class="mt-20">
                         <p class="text-center">Click on an Arabic word to see its translation.</p>
                     </div>
                </section>
            <?php elseif ($current_page === 'tafsir'): ?>
                <?php if (check_role('registered')): ?>
                <section id="tafsir" class="section active" role="region" aria-labelledby="tafsir-heading">
                    <h2 id="tafsir-heading">Personal Tafsir Builder</h2>
                    <p>Write your notes and reflections for the current Ayah.</p>
                     <div id="current-ayah-tafsir" class="ayah mb-20">
                        <?php if ($current_ayah_data): ?>
                            <div class="ayah-number">Tafsir for Surah <?php echo $current_surah; ?>:<?php echo $current_ayah; ?> (<?php echo $surah_names[$current_surah-1]; ?>)</div>
                            <div class="ayah-arabic" lang="ar" dir="rtl" style="font-size: 1.5rem; line-height: 2;">
                                <?php echo htmlspecialchars($current_ayah_data['arabic_text']); ?>
                            </div>
                            <div class="ayah-translation" lang="<?php echo $translation_configs[$selected_translation_key]['lang']; ?>" dir="<?php echo $translation_configs[$selected_translation_key]['dir']; ?>" style="font-family: var(--font-<?php echo $selected_translation_key; ?>);">
                                <?php echo htmlspecialchars($current_ayah_data["{$selected_translation_key}_translation"]); ?>
                            </div>
                        <?php else: ?>
                            <p class="text-center">Navigate to an Ayah in the Quran Viewer to add Tafsir.</p>
                        <?php endif; ?>
                     </div>
                    <div class="tafsir-editor">
                        <form action="index.php?action=save_tafsir" method="POST">
                            <input type="hidden" name="surah" value="<?php echo $current_surah; ?>">
                            <input type="hidden" name="ayah" value="<?php echo $current_ayah; ?>">
                            <label for="tafsir-notes">Your Tafsir Notes:</label>
                            <textarea id="tafsir-notes" name="notes" placeholder="Enter your personal notes, interpretations, and reflections here..."><?php echo htmlspecialchars($current_tafsir_notes ?? ''); ?></textarea>
                            <button type="submit" id="save-tafsir-btn">Save Tafsir</button>
                        </form>
                         <p id="tafsir-status" aria-live="polite"></p>
                    </div>
                </section>
                <?php else: ?>
                    <p class="text-center" style="color: var(--color-error);">Please log in as a Registered User to access Personal Tafsir.</p>
                <?php endif; ?>
            <?php elseif ($current_page === 'themes'): ?>
                <?php if (check_role('registered')): ?>
                 <section id="themes" class="section active" role="region" aria-labelledby="themes-heading">
                    <h2 id="themes-heading">Thematic Linker Pro</h2>
                    <p>Create and manage themes, and link Ayahs to them.</p>

                    <div class="theme-manager mb-20">
                        <h3>Manage Themes</h3>
                        <form action="index.php?action=add_theme" method="POST" class="flex-group mb-10">
                            <label for="new-theme-name" class="sr-only">New Theme Name</label>
                            <input type="text" id="new-theme-name" name="new_theme_name" placeholder="New Theme Name">
                            <label for="parent-theme-select" class="sr-only">Parent Theme (Optional)</label>
                            <select id="parent-theme-select" name="parent_theme_select" aria-label="Parent Theme (Optional)">
                                <option value="">-- No Parent --</option>
                                <?php foreach ($all_themes as $theme): ?>
                                    <option value="<?php echo $theme['id']; ?>"><?php echo htmlspecialchars($theme['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" id="add-theme-btn">Add Theme</button>
                        </form>
                        <div class="theme-list">
                            <h4>Existing Themes</h4>
                            <ul id="themes-list">
                                <?php if (empty($all_themes)): ?>
                                    <li>No themes added yet.</li>
                                <?php else: ?>
                                    <?php foreach ($all_themes as $theme): ?>
                                        <li>
                                            <a href="index.php?page=themes&theme_id=<?php echo $theme['id']; ?>#linked-ayahs-list" class="view-theme-ayahs"><?php echo htmlspecialchars($theme['name']); ?></a>
                                            <div class="theme-actions" style="display: inline-block;">
                                                <!-- Delete button would go here -->
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                         <p id="theme-manager-status" aria-live="polite"></p>
                    </div>

                    <div class="theme-linker">
                        <h3>Link Current Ayah (<?php echo $current_surah; ?>:<?php echo $current_ayah; ?>)</h3>
                        <div id="current-ayah-theme-text" class="ayah mb-20">
                            <?php if ($current_ayah_data): ?>
                                <div class="ayah-number">Ayah for Linking: Surah <?php echo $current_surah; ?>:<?php echo $current_ayah; ?></div>
                                <div class="ayah-arabic" lang="ar" dir="rtl" style="font-size: 1.5rem; line-height: 2;">
                                    <?php echo htmlspecialchars($current_ayah_data['arabic_text']); ?>
                                </div>
                            <?php else: ?>
                                 <p class="text-center">Navigate to an Ayah in the Quran Viewer to link themes.</p>
                            <?php endif; ?>
                        </div>
                        <form action="index.php?action=link_ayah_to_theme" method="POST">
                            <input type="hidden" name="current_surah_link" value="<?php echo $current_surah; ?>">
                            <input type="hidden" name="current_ayah_link" value="<?php echo $current_ayah; ?>">
                            <label for="link-theme-select">Select Theme to Link:</label>
                            <select id="link-theme-select" name="link_theme_select" aria-label="Select Theme to Link" onchange="window.location.href='index.php?page=themes&theme_id='+this.value">
                                 <option value="">-- Select Theme --</option>
                                <?php foreach ($all_themes as $theme): ?>
                                    <option value="<?php echo $theme['id']; ?>" <?php echo ($active_theme_id_for_display == $theme['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($theme['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label for="theme-link-notes">Notes for this link (Optional):</label>
                            <textarea id="theme-link-notes" name="theme_link_notes" placeholder="Notes on why this Ayah relates to this theme..."></textarea>
                            <button type="submit" id="link-ayah-to-theme-btn">Link Ayah</button>
                        </form>
                         <p id="theme-linker-status" aria-live="polite"></p>

                        <h4 class="mt-20">Ayahs Linked to Selected Theme: <span id="linked-theme-name"><?php echo htmlspecialchars($active_theme_name_for_display); ?></span></h4>
                        <ul id="linked-ayahs-list">
                            <?php if (empty($linked_ayahs_for_active_theme)): ?>
                                <li>Select a theme above to see linked ayahs, or no ayahs linked yet.</li>
                            <?php else: ?>
                                <?php foreach ($linked_ayahs_for_active_theme as $link): ?>
                                    <li>
                                        <strong>Surah <?php echo $link['surah']; ?>:<?php echo $link['ayah']; ?></strong>
                                        <?php echo !empty($link['notes']) ? ' - <em>' . htmlspecialchars($link['notes']) . '</em>' : ''; ?>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                </section>
                <?php else: ?>
                    <p class="text-center" style="color: var(--color-error);">Please log in as a Registered User to access Thematic Linker.</p>
                <?php endif; ?>
            <?php elseif ($current_page === 'roots'): ?>
                <?php if (check_role('registered')): ?>
                <section id="roots" class="section active" role="region" aria-labelledby="roots-heading">
                    <h2 id="roots-heading">Root Word Analyzer & Concordance</h2>
                    <p>Input an Arabic root word to find occurrences in the Quran (simplified string search).</p>

                    <div class="root-analyzer-form mb-20">
                        <div class="flex-group mb-10">
                            <label for="root-input" class="sr-only">Arabic Root Word</label>
                            <input type="text" id="root-input" placeholder="Enter Arabic Root (e.g., ق-و-ل) or (علم) or (ر۔ب)" lang="ar" dir="rtl">
                            <button id="analyze-root-btn">Analyze Root</button>
                        </div>
                        <label for="root-description">Description/Notes for this Root (Optional):</label>
                        <textarea id="root-description" placeholder="Your notes on this root's meaning..."></textarea>
                        <button id="save-root-notes-btn">Save Root Notes</button>
                         <p id="root-status" aria-live="polite"></p>
                    </div>

                    <div class="root-results">
                        <h3>Occurrences Found for: <span id="analyzed-root-term">N/A</span></h3>
                        <ul id="root-occurrences-list">
                            <li>Enter a root word and click "Analyze Root".</li>
                        </ul>
                    </div>
                </section>
                <?php else: ?>
                    <p class="text-center" style="color: var(--color-error);">Please log in as a Registered User to access Root Word Analyzer.</p>
                <?php endif; ?>
            <?php elseif ($current_page === 'recitation'): ?>
                <?php if (check_role('registered')): ?>
                <section id="recitation" class="section active" role="region" aria-labelledby="recitation-heading">
                    <h2 id="recitation-heading">Comparative Recitation Log</h2>
                    <p>Log your listening sessions to different Qaris.</p>

                    <div class="recitation-log-form mb-20">
                        <h3>Add Log Entry</h3>
                        <div class="flex-group mb-10">
                            <label for="rec-surah-select" class="sr-only">Surah</label>
                            <select id="rec-surah-select" aria-label="Surah">
                                <?php for ($s = 1; $s <= 114; $s++): ?>
                                    <option value="<?php echo $s; ?>"><?php echo $s . '. ' . $surah_names[$s-1]; ?></option>
                                <?php endfor; ?>
                            </select>
                            <label for="rec-ayah-start" class="sr-only">Ayah Start (Optional)</label>
                            <input type="number" id="rec-ayah-start" placeholder="Ayah Start (Optional)" min="1">
                            <label for="rec-ayah-end" class="sr-only">Ayah End (Optional)</label>
                            <input type="number" id="rec-ayah-end" placeholder="Ayah End (Optional)" min="1">
                        </div>
                         <div class="flex-group mb-10">
                            <label for="rec-qari" class="sr-only">Qari/Source</label>
                            <input type="text" id="rec-qari" placeholder="Qari or Source (e.g., Mishary Alafasy, Local Masjid Imam)">
                            <label for="rec-date" class="sr-only">Date</label>
                            <input type="date" id="rec-date" aria-label="Date">
                         </div>
                        <label for="rec-notes">Notes (Tajweed, Style, Impact):</label>
                        <textarea id="rec-notes" placeholder="Notes on Tajweed, style, emotional impact..."></textarea>
                        <button id="save-recitation-btn">Save Log Entry</button>
                         <p id="recitation-status" aria-live="polite"></p>
                    </div>

                    <div class="recitation-list">
                        <h3>Log Entries</h3>
                        <ul id="recitations-list">
                            <li>No entries logged yet. (Functionality pending)</li>
                        </ul>
                    </div>
                </section>
                <?php else: ?>
                    <p class="text-center" style="color: var(--color-error);">Please log in as a Registered User to access Recitation Log.</p>
                <?php endif; ?>
            <?php elseif ($current_page === 'hifz'): ?>
                <?php if (check_role('registered')): ?>
                <section id="hifz" class="section active" role="region" aria-labelledby="hifz-heading">
                    <h2 id="hifz-heading">Memorization Hub</h2>
                    <p>Track your Hifz progress and review schedule.</p>

                    <div class="hifz-controls flex-group mb-20">
                         <label for="hifz-surah-select" class="sr-only">Select Surah for Hifz</label>
                        <select id="hifz-surah-select" aria-label="Select Surah for Hifz">
                            <?php for ($s = 1; $s <= 114; $s++): ?>
                                <option value="<?php echo $s; ?>"><?php echo $s . '. ' . $surah_names[$s-1]; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div id="hifz-ayahs-list">
                        <p class="text-center">Select a Surah to track Hifz progress. (Functionality pending)</p>
                    </div>
                     <p id="hifz-status" aria-live="polite"></p>
                </section>
                <?php else: ?>
                    <p class="text-center" style="color: var(--color-error);">Please log in as a Registered User to access Memorization Hub.</p>
                <?php endif; ?>
            <?php elseif ($current_page === 'search'): ?>
                 <section id="search" class="section active" role="region" aria-labelledby="search-heading">
                    <h2 id="search-heading">Advanced Search</h2>
                    <p>Search across Quran text, translations, and your personal data.</p>

                    <div class="search-form mb-20">
                        <label for="search-input" class="sr-only">Search Term</label>
                        <input type="text" id="search-input" placeholder="Enter search term">
                        <div class="search-options mb-10" role="group" aria-label="Search Scope">
                            <label><input type="checkbox" class="search-scope" value="quran-arabic" checked> Quran Arabic</label>
                            <label><input type="checkbox" class="search-scope" value="quran-translation" checked> Quran Translation</label>
                            <?php if (check_role('registered')): ?>
                                <label><input type="checkbox" class="search-scope" value="tafsir"> Personal Tafsir</label>
                                <label><input type="checkbox" class="search-scope" value="themes"> Theme Notes</label>
                                <label><input type="checkbox" class="search-scope" value="roots"> Root Notes</label>
                                <label><input type="checkbox" class="search-scope" value="recitation"> Recitation Notes</label>
                                <label><input type="checkbox" class="search-scope" value="hifz"> Hifz Notes</label>
                            <?php endif; ?>
                        </div>
                        <button id="perform-search-btn">Search</button>
                         <p id="search-status" aria-live="polite"></p>
                    </div>

                    <div class="search-results">
                        <h3>Search Results</h3>
                        <ul id="search-results-list">
                            <li>Enter a search term and click "Search". (Functionality pending)</li>
                        </ul>
                    </div>
                </section>
            <?php elseif ($current_page === 'data'): ?>
                <?php if (check_role('registered')): ?>
                <section id="data" class="section active" role="region" aria-labelledby="data-heading">
                    <h2 id="data-heading">Data Management</h2>
                    <p>Manage your personal data (Tafsir, Themes, Roots, Logs, Hifz).</p>

                    <div class="settings-section mb-20">
                        <h3>Backup Data</h3>
                        <p>Export your personal data as a JSON file.</p>
                        <button id="export-data-btn">Export Data (Functionality pending)</button>
                         <p id="export-status" aria-live="polite"></p>
                    </div>

                    <div class="settings-section mb-20">
                        <h3>Restore Data</h3>
                        <p>Import your personal data from a JSON file. This will overwrite existing data.</p>
                        <label for="import-file" class="sr-only">Choose JSON file to import</label>
                        <input type="file" id="import-file" accept="application/json">
                        <button id="import-data-btn" disabled>Import Data (Functionality pending)</button>
                         <p id="import-status" aria-live="polite"></p>
                    </div>

                     <div class="settings-section">
                        <h3>Clear All Personal Data</h3>
                        <p class="mb-10" style="color: var(--color-error);">Warning: This will permanently delete ALL your personal Tafsir, Themes, Roots, Logs, and Hifz data.</p>
                         <button id="clear-data-btn" style="background-color: var(--color-error);">Clear All Data (Functionality pending)</button>
                         <p id="clear-status" aria-live="polite"></p>
                     </div>
                </section>
                <?php else: ?>
                    <p class="text-center" style="color: var(--color-error);">Please log in as a Registered User to access Data Management.</p>
                <?php endif; ?>
            <?php elseif ($current_page === 'approval'): ?>
                 <?php if (check_role('ulama')): ?>
                 <section id="approval" class="section active" role="region" aria-labelledby="approval-heading">
                    <h2 id="approval-heading">Approval Queue</h2>
                    <p>Review and approve user contributions.</p>
                    <ul id="approval-list">
                        <li>No pending contributions. (Functionality pending)</li>
                    </ul>
                 </section>
                 <?php else: ?>
                     <p class="text-center" style="color: var(--color-error);">You do not have permission to view the Approval Queue.</p>
                 <?php endif; ?>
            <?php elseif ($current_page === 'admin_panel'): ?>
                <?php if (check_role('admin')): ?>
                 <section id="admin_panel" class="section active" role="region" aria-labelledby="admin-heading">
                    <h2 id="admin-heading">Admin Panel</h2>
                    <p>Manage users and global data.</p>

                    <div class="settings-section mb-20">
                        <h3>Manage Users</h3>
                        <ul id="user-list">
                            <li>User management functionality pending.</li>
                        </ul>
                    </div>

                    <div class="settings-section mb-20">
                        <h3>Import Initial Quran & Translation Data</h3>
                        <p>This will load Arabic, Urdu, English, Bangali Quran text and word-by-word data.</p>
                        <?php
                            $stmt_quran_count = $pdo->query("SELECT COUNT(*) FROM quran_ayahs;");
                            $current_ayah_count_db = $stmt_quran_count->fetchColumn();
                            $total_expected_ayahs = array_sum($surah_ayah_counts);
                            if ($current_ayah_count_db < $total_expected_ayahs) {
                                echo '<p style="color: var(--color-error);">Quran data is not fully loaded. Please import.</p>';
                                echo '<form action="index.php?action=admin_import_quran_data" method="POST">';
                                echo '<button type="submit">Import All Initial Data</button>';
                                echo '</form>';
                            } else {
                                echo '<p style="color: var(--color-success);">Initial Quran and translation data already loaded.</p>';
                            }
                        ?>
                    </div>
                 </section>
                 <?php else: ?>
                     <p class="text-center" style="color: var(--color-error);">You do not have permission to view the Admin Panel.</p>
                 <?php endif; ?>
            <?php else: ?>
                <section id="default-section" class="section active">
                    <p class="text-center">Content for <?php echo htmlspecialchars($current_page); ?> page.</p>
                </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Modals (JS controlled) -->
    <div id="themeAyahsModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="themeAyahsModalTitle">
        <div class="modal-content">
            <span class="close-button" aria-label="Close Theme Ayahs Modal">&times;</span>
            <h3 id="themeAyahsModalTitle">Ayahs Linked to Theme: <span id="modal-theme-name"></span></h3>
            <ul id="modal-linked-ayahs-list">
                <!-- Linked ayahs populated here by JS -->
            </ul>
        </div>
    </div>

    <div id="rootOccurrencesModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="rootOccurrencesModalTitle">
        <div class="modal-content">
            <span class="close-button" aria-label="Close Root Occurrences Modal">&times;</span>
            <h3 id="rootOccurrencesModalTitle">Occurrences for Root: <span id="modal-root-term"></span></h3>
            <ul id="modal-root-occurrences-list">
                <!-- Root occurrences populated here by JS -->
            </ul>
        </div>
    </div>

<script>
    // --- Global JS Variables (from PHP for client-side use) ---
    const APP_BASE_URL = 'index.php'; // Base URL for AJAX calls
    const SURAH_AYAH_COUNTS_JS = <?php echo json_encode($surah_ayah_counts); ?>;
    const SURAH_NAMES_JS = <?php echo json_encode($surah_names); ?>;
    const TRANSLATION_CONFIGS_JS = <?php echo json_encode($translation_configs); ?>;

    // --- Utility Functions ---
    function showLoading(message = 'Loading...') {
        const loadingOverlay = document.getElementById('loading-overlay');
        loadingOverlay.textContent = message;
        loadingOverlay.style.display = 'flex';
        document.body.setAttribute('aria-busy', 'true');
    }

    function hideLoading() {
        document.getElementById('loading-overlay').style.display = 'none';
        document.body.setAttribute('aria-busy', 'false');
    }

    function setStatusMessage(elementId, message, isError = false) {
        const statusElement = document.getElementById(elementId);
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.style.color = isError ? 'var(--color-error)' : 'var(--color-success)';
            statusElement.style.fontWeight = 'bold';
            setTimeout(() => {
                statusElement.textContent = '';
                statusElement.style.color = '';
                statusElement.style.fontWeight = '';
            }, 5000); // Message disappears after 5 seconds
        }
    }

    // Utility for Arabic word normalization (for fuzzy matching) - same as PHP's
    function generateArabicRegex(word) {
        if (!word) return new RegExp('.^'); // Match nothing

        let pattern = word.trim().replace(/[\u064B-\u0652\u0670]/g, ''); // Remove Harakat

        // Apply flexible character replacements for regex pattern
        pattern = pattern
                        .replace(/ؤ/g, '(?:و|ؤ)')
                        .replace(/و/g, '(?:و|ؤ)') // Make waw and hamza on waw interchangeable
                        .replace(/ك/g, '(?:ك|ک)')
                        .replace(/ک/g, '(?:ك|ک)') // Make kaf and keheh interchangeable
                        .replace(/آ/g, '(?:آ|ا|أ|إ)')
                        .replace(/ا/g, '(?:آ|ا|أ|إ)')
                        .replace(/أ/g, '(?:آ|ا|أ|إ)')
                        .replace(/إ/g, '(?:آ|ا|أ|إ)') // Make all alif forms interchangeable
                        .replace(/ى/g, '(?:ى|ی|ي)')
                        .replace(/ی/g, '(?:ى|ی|ي)')
                        .replace(/ي/g, '(?:ى|ی|ي)') // Make alif maqsoorah, yeh forms interchangeable
                        .replace(/ہ/g, '(?:ہ|ھ|ة|ۃ|ه)')
                        .replace(/ھ/g, '(?:ہ|ھ|ة|ۃ|ه)')
                        .replace(/ة/g, '(?:ہ|ھ|ة|ۃ|ه)')
                        .replace(/ۃ/g, '(?:ہ|ھ|ة|ۃ|ه)')
                        .replace(/ه/g, '(?:ہ|ھ|ة|ۃ|ه)') // Make hah, taa marbuta forms interchangeable
                        .replace(/ے/g, '(?:ے|ی)') // Make yeh barree and yeh interchangeable (Urdu specific)
                        .replace(/م/g, '(?:مٰ|م)'); // Make meem and meem with alif interchangeable (Urdu specific)

        // Escape any remaining regex special characters that are not part of our specific variations
        pattern = pattern.replace(/[-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|\`]/g, "\\$&");

        return new RegExp(`^${pattern}$`, 'ui'); // 'u' for UTF-8, 'i' for case-insensitive
    }


    // --- Theme Switcher ---
    const themeSwitcher = document.getElementById('theme-switcher');
    if (themeSwitcher) {
        function applyTheme(themeName) {
            document.body.className = '';
            if (themeName !== 'serene') {
                document.body.classList.add(`theme-${themeName}`);
            }
            // Save theme preference to localStorage for client-side persistence
            localStorage.setItem('appTheme', themeName);
        }

        const savedTheme = localStorage.getItem('appTheme');
        if (savedTheme) {
            themeSwitcher.value = savedTheme;
            applyTheme(savedTheme);
        } else {
            applyTheme(themeSwitcher.value); // Apply default
        }

        themeSwitcher.addEventListener('change', (event) => {
            applyTheme(event.target.value);
        });
    }

    // --- Modals ---
    document.querySelectorAll('.modal .close-button').forEach(button => {
        button.addEventListener('click', (event) => {
            event.target.closest('.modal').style.display = 'none';
        });
    });
    window.addEventListener('click', (event) => {
        document.querySelectorAll('.modal').forEach(modal => {
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });
    });

    // --- Quran Viewer Word Click Logic ---
    const wordTranslationArea = document.getElementById('word-translation-area');
    const quranDisplay = document.getElementById('quran-display');

    if (quranDisplay) {
        // This is a simplified client-side cache for word-by-word data
        // In a real app, you'd load this from an API endpoint once.
        let wordTranslationsCache = []; // Filled via AJAX if needed

        async function loadWordTranslationsCache() {
            if (wordTranslationsCache.length > 0) return; // Already loaded

            showLoading("Loading word translations for lookup...");
            try {
                // AJAX call to PHP backend to get all word translations
                const response = await fetch(APP_BASE_URL + '?api=get_all_word_translations');
                if (!response.ok) throw new Error('Failed to fetch word translations');
                const data = await response.json();
                wordTranslationsCache = data.words || [];
                console.log(`JS cache: Loaded ${wordTranslationsCache.length} word translations.`);
            } catch (error) {
                console.error("Error loading word translations cache:", error);
                setStatusMessage('word-translation-area', 'Error loading word meanings for lookup.', true);
            } finally {
                hideLoading();
            }
        }

        function handleWordClick(event) {
            const clickedWordRaw = event.target.getAttribute('data-word');
            if (!clickedWordRaw) return;

            const ayahElement = event.target.closest('.ayah');
            const surah = ayahElement.getAttribute('data-surah');
            const ayah = ayahElement.getAttribute('data-ayah');

            const translationDiv = ayahElement.querySelector('.ayah-translation');
            const fullTranslation = translationDiv ? translationDiv.textContent : 'Translation not found.';

            const selectedTranslationKey = document.getElementById('translation-select').value;
            const translationInfo = TRANSLATION_CONFIGS_JS[selectedTranslationKey];
            const translationLabel = translationInfo ? translationInfo.label : 'Selected Translation';
            const translationLang = translationInfo ? translationInfo.lang : 'en';
            const translationDir = translationInfo ? translationInfo.dir : 'ltr';
            const translationFont = translationInfo ? `var(--font-${selectedTranslationKey})` : `var(--font-general)`;
            const translationTextAlign = translationInfo.dir === 'rtl' ? 'right' : 'left';

            let wordUrduMeaning = "N/A";
            let wordEnglishMeaning = "N/A";

            if (wordTranslationsCache.length > 0) {
                const regex = generateArabicRegex(clickedWordRaw);
                const foundWordTranslation = wordTranslationsCache.find(wordEntry => {
                    // Normalize the word from the cached entry for comparison against the regex
                    const cleanedEntryWord = wordEntry.quran_text.replace(/[\u064B-\u0652\u0670]/g, '');
                    return regex.test(cleanedEntryWord);
                });

                if (foundWordTranslation) {
                    wordUrduMeaning = foundWordTranslation.ur_meaning || "N/A";
                    wordEnglishMeaning = foundWordTranslation.en_meaning || "N/A";
                }
            } else {
                console.warn("Word translations cache is empty. Please ensure data5.AM loaded correctly.");
            }

            wordTranslationArea.innerHTML = `
                <p><strong>Selected Word:</strong> <span lang="ar" dir="rtl" style="font-family: var(--font-arabic); font-size: 1.2rem;">${clickedWordRaw}</span></p>
                <p><strong>Urdu Meaning:</strong> <span lang="ur" dir="rtl" style="font-family: var(--font-urdu);">${wordUrduMeaning}</span></p>
                <p><strong>English Meaning:</strong> <span lang="en" dir="ltr" style="font-family: var(--font-english);">${wordEnglishMeaning}</span></p>
                <p><strong>Full Ayah Translation (${surah}:${ayah}) - ${translationLabel}:</strong> <span lang="${translationLang}" dir="${translationDir}" style="font-family: ${translationFont}; text-align: ${translationTextAlign};">${fullTranslation}</span></p>
            `;

            document.querySelectorAll('.ayah-arabic span').forEach(span => {
                span.style.backgroundColor = 'transparent';
            });
            event.target.style.backgroundColor = 'var(--color-highlight)';
        }

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.ayah-arabic span').forEach(wordSpan => {
                wordSpan.addEventListener('click', handleWordClick);
                wordSpan.addEventListener('focus', handleWordClick); // For accessibility
            });
             if (wordTranslationsCache.length === 0) { // Only load if not already loaded from PHP
                loadWordTranslationsCache();
            }
        });
    }

    // --- Initial Loading Overlay Control (JS) ---
    // This script runs AFTER PHP has finished rendering and importing data
    // If PHP outputted 'importing' messages, the overlay would be visible.
    // We now hide it once the DOM is ready and JS takes over.
    document.addEventListener('DOMContentLoaded', () => {
        hideLoading(); // Hide the overlay once the page is interactive
    });

</script>
</body>
</html>
<?php
// --- AJAX API Endpoints (PHP-side) ---
// This section handles AJAX requests from the client-side JavaScript.
// It's placed at the end so it's not part of the main HTML output on a normal page load.

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    switch ($_GET['api']) {
        case 'get_all_word_translations':
            try {
                $stmt = $pdo->query("SELECT quran_text, ur_meaning, en_meaning FROM word_translations;");
                $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode(['success' => true, 'words' => $words]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit();
        // Add other API endpoints here for AJAX (e.g., search, dynamic theme updates, hifz status)
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown API endpoint.']);
            exit();
    }
}
?>