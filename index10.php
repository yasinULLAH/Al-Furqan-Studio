<?php
// Author: Yasin Ullah
// Pakistani

// Configuration
define('DB_PATH', __DIR__ . '/quran_study.sqlite');
define('APP_NAME', 'Nur Al-Quran Studio Offline');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // CHANGE THIS IN PRODUCTION!

// Initialize database
function init_db() {
    $db = new SQLite3(DB_PATH);
    $db->exec("PRAGMA foreign_keys = ON;");

    // Create tables if they don't exist
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'Public' CHECK (role IN ('Public', 'User', 'Ulama', 'Admin'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS surahs (
        id INTEGER PRIMARY KEY,
        name_arabic TEXT,
        name_english TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ayahs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        surah_id INTEGER,
        ayah_number INTEGER,
        arabic_text TEXT,
        FOREIGN KEY (surah_id) REFERENCES surahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS translations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ayah_id INTEGER,
        language TEXT,
        text TEXT,
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS word_translations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        word_id INTEGER UNIQUE,
        ur_meaning TEXT,
        en_meaning TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS word_metadata (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        word_id INTEGER UNIQUE,
        surah_id INTEGER,
        ayah_id INTEGER,
        word_position INTEGER
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS personal_tafsir (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        surah_id INTEGER,
        ayah_id INTEGER,
        tafsir_text TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS themes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        theme_name TEXT,
        description TEXT,
        is_public INTEGER DEFAULT 0, -- 0: private, 1: community, 2: ulama/admin
        status TEXT DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS theme_ayahs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        theme_id INTEGER,
        surah_id INTEGER,
        ayah_id INTEGER,
        FOREIGN KEY (theme_id) REFERENCES themes(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS hifz_tracking (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        surah_id INTEGER,
        ayah_id INTEGER,
        status TEXT CHECK (status IN ('Not Started', 'Memorizing', 'Memorized')),
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS recitation_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        surah_id INTEGER,
        ayah_from INTEGER,
        ayah_to INTEGER,
        recitation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS contributions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        type TEXT CHECK (type IN ('WordMeaning', 'AyahMeaning', 'Tafsir', 'Theme')),
        related_id INTEGER, -- ID of the related item (word_id, ayah_id, theme_id)
        content TEXT,
        status TEXT DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");

    // Add admin user if not exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindValue(':username', ADMIN_USERNAME);
    $result = $stmt->execute();
    $row = $result->fetchArray();
    if ($row[0] == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'Admin')");
        $stmt->bindValue(':username', ADMIN_USERNAME);
        $stmt->bindValue(':password', password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT));
        $stmt->execute();
    }

    $db->close();
}

// Load initial data
function load_initial_data() {
    $db = new SQLite3(DB_PATH);
    $db->exec("PRAGMA foreign_keys = ON;");
    $db->exec("BEGIN TRANSACTION;");

    // Check if surahs are already loaded
    $stmt = $db->prepare("SELECT COUNT(*) FROM surahs");
    $result = $stmt->execute();
    $row = $result->fetchArray();
    if ($row[0] == 0) {
        // Load Surah names (basic, can be expanded)
        $surahs = [
            1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Al-Imran', 4 => 'An-Nisa', 5 => 'Al-Ma\'idah',
            6 => 'Al-An\'am', 7 => 'Al-A\'raf', 8 => 'Al-Anfal', 9 => 'At-Tawbah', 10 => 'Yunus',
            11 => 'Hud', 12 => 'Yusuf', 13 => 'Ar-Ra\'d', 14 => 'Ibrahim', 15 => 'Al-Hijr',
            16 => 'An-Nahl', 17 => 'Al-Isra', 18 => 'Al-Kahf', 19 => 'Maryam', 20 => 'Taha',
            21 => 'Al-Anbiya', 22 => 'Al-Hajj', 23 => 'Al-Mu\'minun', 24 => 'An-Nur', 25 => 'Al-Furqan',
            26 => 'Ash-Shu\'ara', 27 => 'An-Naml', 28 => 'Al-Qasas', 29 => 'Al-Ankabut', 30 => 'Ar-Rum',
            31 => 'Luqman', 32 => 'As-Sajdah', 33 => 'Al-Ahzab', 34 => 'Saba', 35 => 'Fatir',
            36 => 'Ya-Sin', 37 => 'As-Saffat', 38 => 'Sad', 39 => 'Az-Zumar', 40 => 'Ghafir',
            41 => 'Fussilat', 42 => 'Ash-Shuraa', 43 => 'Az-Zukhruf', 44 => 'Ad-Dukhan', 45 => 'Al-Jathiyah',
            46 => 'Al-Ahqaf', 47 => 'Muhammad', 48 => 'Al-Fath', 49 => 'Al-Hujurat', 50 => 'Qaf',
            51 => 'Adh-Dhariyat', 52 => 'At-Tur', 53 => 'An-Najm', 54 => 'Al-Qamar', 55 => 'Ar-Rahman',
            56 => 'Al-Waqi\'ah', 57 => 'Al-Hadid', 58 => 'Al-Mujadila', 59 => 'Al-Hashr', 60 => 'Al-Mumtahanah',
            61 => 'As-Saff', 62 => 'Al-Jumu\'ah', 63 => 'Al-Munafiqun', 64 => 'At-Taghabun', 65 => 'At-Talaq',
            66 => 'At-Tahrim', 67 => 'Al-Mulk', 68 => 'Al-Qalam', 69 => 'Al-Haqqah', 70 => 'Al-Ma\'arij',
            71 => 'Nuh', 72 => 'Al-Jinn', 73 => 'Al-Muzzammil', 74 => 'Al-Muddaththir', 75 => 'Al-Qiyamah',
            76 => 'Al-Insan', 77 => 'Al-Mursalat', 78 => 'An-Naba', 79 => 'An-Nazi\'at', 80 => '\'Abasa',
            81 => 'At-Takwir', 82 => 'Al-Infitar', 83 => 'Al-Mutaffifin', 84 => 'Al-Inshiqaq', 85 => 'Al-Buruj',
            86 => 'At-Tariq', 87 => 'Al-A\'la', 88 => 'Al-Ghashiyah', 89 => 'Al-Fajr', 90 => 'Al-Balad',
            91 => 'Ash-Shams', 92 => 'Al-Layl', 93 => 'Ad-Duhaa', 94 => 'Ash-Sharh', 95 => 'At-Tin',
            96 => 'Al-\'Alaq', 97 => 'Al-Qadr', 98 => 'Al-Bayyinah', 99 => 'Az-Zalzalah', 100 => 'Al-\'Adiyat',
            101 => 'Al-Qari\'ah', 102 => 'At-Takathur', 103 => 'Al-\'Asr', 104 => 'Al-Humazah', 105 => 'Al-Fil',
            106 => 'Quraysh', 107 => 'Al-Ma\'un', 108 => 'Al-Kawthar', 109 => 'Al-Kafirun', 110 => 'An-Nasr',
            111 => 'Al-Masad', 112 => 'Al-Ikhlas', 113 => 'Al-Falaq', 114 => 'An-Nas'
        ];
        $stmt = $db->prepare("INSERT INTO surahs (id, name_english) VALUES (:id, :name_english)");
        foreach ($surahs as $id => $name) {
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':name_english', $name);
            $stmt->execute();
        }
    }

    // Load Ayahs and Translations
    $files = ['data new.AM' => 'Urdu', 'dataENG.AM' => 'English', 'dataBNG.AM' => 'Bengali'];
    foreach ($files as $file => $language) {
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Example line: بِسۡمِ ٱللَّهِ ٱلرَّحۡمَـٰنِ ٱلرَّحِيمِ ترجمہ: اللہ کے نام سے شروع جو بڑا مہربان نہایت رحم والا ہے<br/>س 001 آ 001
                if (preg_match('/^(.*?) ترجمہ: (.*?)<br\/>س (\d{3}) آ (\d{3})$/', $line, $matches)) {
                    $arabic_text = trim($matches[1]);
                    $translation_text = trim($matches[2]);
                    $surah_id = (int)$matches[3];
                    $ayah_number = (int)$matches[4];

                    // Find or insert Ayah
                    $stmt_ayah = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");
                    $stmt_ayah->bindValue(':surah_id', $surah_id);
                    $stmt_ayah->bindValue(':ayah_number', $ayah_number);
                    $ayah_result = $stmt_ayah->execute();
                    $ayah_row = $ayah_result->fetchArray();

                    $ayah_id = null;
                    if ($ayah_row) {
                        $ayah_id = $ayah_row['id'];
                    } else {
                        $stmt_insert_ayah = $db->prepare("INSERT INTO ayahs (surah_id, ayah_number, arabic_text) VALUES (:surah_id, :ayah_number, :arabic_text)");
                        $stmt_insert_ayah->bindValue(':surah_id', $surah_id);
                        $stmt_insert_ayah->bindValue(':ayah_number', $ayah_number);
                        $stmt_insert_ayah->bindValue(':arabic_text', $arabic_text);
                        $stmt_insert_ayah->execute();
                        $ayah_id = $db->lastInsertRowID();
                    }

                    // Insert Translation (only if it doesn't exist for this ayah and language)
                    $stmt_check_translation = $db->prepare("SELECT COUNT(*) FROM translations WHERE ayah_id = :ayah_id AND language = :language");
                    $stmt_check_translation->bindValue(':ayah_id', $ayah_id);
                    $stmt_check_translation->bindValue(':language', $language);
                    $translation_count_result = $stmt_check_translation->execute();
                    $translation_count_row = $translation_count_result->fetchArray();

                    if ($translation_count_row[0] == 0) {
                        $stmt_insert_translation = $db->prepare("INSERT INTO translations (ayah_id, language, text) VALUES (:ayah_id, :language, :text)");
                        $stmt_insert_translation->bindValue(':ayah_id', $ayah_id);
                        $stmt_insert_translation->bindValue(':language', $language);
                        $stmt_insert_translation->bindValue(':text', $translation_text);
                        $stmt_insert_translation->execute();
                    }
                }
            }
        } else {
            error_log("Data file not found: " . $file);
        }
    }

    // Load Word Translations (data5 new.AM)
    if (file_exists('data5 new.AM')) {
        $lines = file('data5 new.AM', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stmt_insert_word_translation = $db->prepare("INSERT OR IGNORE INTO word_translations (word_id, ur_meaning, en_meaning) VALUES (:word_id, :ur_meaning, :en_meaning)");
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) == 3) {
                $word_id = (int)$data[0];
                $ur_meaning = trim($data[1]);
                $en_meaning = trim($data[2]);
                $stmt_insert_word_translation->bindValue(':word_id', $word_id);
                $stmt_insert_word_translation->bindValue(':ur_meaning', $ur_meaning);
                $stmt_insert_word_translation->bindValue(':en_meaning', $en_meaning);
                $stmt_insert_word_translation->execute();
            }
        }
    } else {
        error_log("Word translation file not found: data5 new.AM");
    }

    // Load Word Metadata (word2.AM)
    if (file_exists('word2.AM')) {
        $lines = file('word2.AM', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stmt_insert_word_metadata = $db->prepare("INSERT OR IGNORE INTO word_metadata (word_id, surah_id, ayah_id, word_position) VALUES (:word_id, :surah_id, :ayah_id, :word_position)");
        $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) == 4) {
                $word_id = (int)$data[0];
                $surah_id = (int)$data[1];
                $ayah_number = (int)$data[2];
                $word_position = (int)$data[3];

                $stmt_get_ayah_id->bindValue(':surah_id', $surah_id);
                $stmt_get_ayah_id->bindValue(':ayah_number', $ayah_number);
                $ayah_result = $stmt_get_ayah_id->execute();
                $ayah_row = $ayah_result->fetchArray();
                if ($ayah_row) {
                    $ayah_id = $ayah_row['id'];
                    $stmt_insert_word_metadata->bindValue(':word_id', $word_id);
                    $stmt_insert_word_metadata->bindValue(':surah_id', $surah_id);
                    $stmt_insert_word_metadata->bindValue(':ayah_id', $ayah_id);
                    $stmt_insert_word_metadata->bindValue(':word_position', $word_position);
                    $stmt_insert_word_metadata->execute();
                } else {
                    error_log("Could not find ayah for word metadata: S{$surah_id}:A{$ayah_number}");
                }
            }
        }
    } else {
        error_log("Word metadata file not found: word2.AM");
    }


    $db->exec("COMMIT;");
    $db->close();
}

// Authentication and Authorization
session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? 'Public';
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function authenticate($username, $password) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function register_user($username, $password) {
    $db = new SQLite3(DB_PATH);
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt_check->bindValue(':username', $username);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    if ($row[0] > 0) {
        $db->close();
        return "Username already exists.";
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt_insert = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'User')");
    $stmt_insert->bindValue(':username', $username);
    $stmt_insert->bindValue(':password', $hashed_password);
    $success = $stmt_insert->execute();
    $db->close();

    return $success ? true : "Registration failed.";
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function has_permission($required_role) {
    $user_role = get_user_role();
    $roles = ['Public' => 0, 'User' => 1, 'Ulama' => 2, 'Admin' => 3];
    return isset($roles[$user_role]) && isset($roles[$required_role]) && $roles[$user_role] >= $roles[$required_role];
}

// Database Operations
function get_surahs() {
    $db = new SQLite3(DB_PATH);
    $results = $db->query("SELECT id, name_english FROM surahs ORDER BY id");
    $surahs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $surahs[] = $row;
    }
    $db->close();
    return $surahs;
}

function get_ayahs_by_surah($surah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, surah_id, ayah_number, arabic_text FROM ayahs WHERE surah_id = :surah_id ORDER BY ayah_number");
    $stmt->bindValue(':surah_id', $surah_id);
    $results = $stmt->execute();
    $ayahs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row;
    }
    $db->close();
    return $ayahs;
}

function get_translations_by_ayah($ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT language, text FROM translations WHERE ayah_id = :ayah_id");
    $stmt->bindValue(':ayah_id', $ayah_id);
    $results = $stmt->execute();
    $translations = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $translations[$row['language']] = $row['text'];
    }
    $db->close();
    return $translations;
}

function get_personal_tafsir($user_id, $surah_id, $ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT tafsir_text FROM personal_tafsir WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row ? $row['tafsir_text'] : '';
}

function save_personal_tafsir($user_id, $surah_id, $ayah_id, $tafsir_text) {
    $db = new SQLite3(DB_PATH);
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM personal_tafsir WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt_check->bindValue(':user_id', $user_id);
    $stmt_check->bindValue(':surah_id', $surah_id);
    $stmt_check->bindValue(':ayah_id', $ayah_id);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    if ($row[0] > 0) {
        $stmt = $db->prepare("UPDATE personal_tafsir SET tafsir_text = :tafsir_text WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    } else {
        $stmt = $db->prepare("INSERT INTO personal_tafsir (user_id, surah_id, ayah_id, tafsir_text) VALUES (:user_id, :surah_id, :ayah_id, :tafsir_text)");
    }
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $stmt->bindValue(':tafsir_text', $tafsir_text);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function get_themes($filter = 'all', $user_id = null) {
    $db = new SQLite3(DB_PATH);
    $sql = "SELECT t.id, t.theme_name, t.description, t.is_public, u.username FROM themes t JOIN users u ON t.user_id = u.id";
    $params = [];

    if ($filter === 'community') {
        $sql .= " WHERE t.is_public = 1 AND t.status = 'Approved'";
    } elseif ($filter === 'ulama') {
         $sql .= " WHERE t.is_public = 2"; // Ulama/Admin contributions are public=2
    } elseif ($filter === 'personal' && $user_id !== null) {
        $sql .= " WHERE t.user_id = :user_id";
        $params[':user_id'] = $user_id;
    } elseif ($filter === 'pending' && has_permission('Ulama')) {
        $sql .= " WHERE t.status = 'Pending'";
    } else {
        // 'all' or public view for non-logged in
        $sql .= " WHERE (t.is_public IN (1, 2) AND t.status = 'Approved')";
        if (is_logged_in()) {
             $sql .= " OR t.user_id = :current_user_id";
             $params[':current_user_id'] = get_user_id();
        }
    }
    $sql .= " ORDER BY t.theme_name";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }

    $results = $stmt->execute();
    $themes = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $themes[] = $row;
    }
    $db->close();
    return $themes;
}

function get_theme_ayahs($theme_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT ta.surah_id, ta.ayah_id, a.ayah_number, s.name_english FROM theme_ayahs ta JOIN ayahs a ON ta.ayah_id = a.id JOIN surahs s ON ta.surah_id = s.id WHERE ta.theme_id = :theme_id ORDER BY ta.surah_id, a.ayah_number");
    $stmt->bindValue(':theme_id', $theme_id);
    $results = $stmt->execute();
    $ayahs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row;
    }
    $db->close();
    return $ayahs;
}

function save_theme($user_id, $theme_name, $description, $ayahs, $is_public = 0, $status = 'Pending') {
    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");

    // Insert or update theme
    $stmt_check = $db->prepare("SELECT id FROM themes WHERE user_id = :user_id AND theme_name = :theme_name");
    $stmt_check->bindValue(':user_id', $user_id);
    $stmt_check->bindValue(':theme_name', $theme_name);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    $theme_id = null;
    if ($row) {
        $theme_id = $row['id'];
        $stmt_update = $db->prepare("UPDATE themes SET description = :description, is_public = :is_public, status = :status WHERE id = :theme_id");
        $stmt_update->bindValue(':description', $description);
        $stmt_update->bindValue(':is_public', $is_public);
        $stmt_update->bindValue(':status', $status);
        $stmt_update->bindValue(':theme_id', $theme_id);
        $stmt_update->execute();

        // Delete existing ayahs for this theme
        $stmt_delete_ayahs = $db->prepare("DELETE FROM theme_ayahs WHERE theme_id = :theme_id");
        $stmt_delete_ayahs->bindValue(':theme_id', $theme_id);
        $stmt_delete_ayahs->execute();

    } else {
        $stmt_insert = $db->prepare("INSERT INTO themes (user_id, theme_name, description, is_public, status) VALUES (:user_id, :theme_name, :description, :is_public, :status)");
        $stmt_insert->bindValue(':user_id', $user_id);
        $stmt_insert->bindValue(':theme_name', $theme_name);
        $stmt_insert->bindValue(':description', $description);
        $stmt_insert->bindValue(':is_public', $is_public);
        $stmt_insert->bindValue(':status', $status);
        $stmt_insert->execute();
        $theme_id = $db->lastInsertRowID();
    }

    // Insert ayahs for the theme
    if ($theme_id && !empty($ayahs)) {
        $stmt_insert_ayah = $db->prepare("INSERT INTO theme_ayahs (theme_id, surah_id, ayah_id) VALUES (:theme_id, :surah_id, :ayah_id)");
        $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");

        // Normalize ayahs input (handle newline or comma separated)
        $ayah_list = [];
        foreach ($ayahs as $ayah_input) {
            $ayah_input = trim($ayah_input);
            if (strpos($ayah_input, ',') !== false) {
                $ayah_list = array_merge($ayah_list, array_map('trim', explode(',', $ayah_input)));
            } elseif (!empty($ayah_input)) {
                $ayah_list[] = $ayah_input;
            }
        }
        $ayah_list = array_unique($ayah_list); // Remove duplicates

        foreach ($ayah_list as $ayah_str) {
            // Assuming ayahs are passed as {surah_id}-{ayah_number}
            if (preg_match('/^(\d+)-(\d+)$/', $ayah_str, $matches)) {
                $surah_id_val = (int)$matches[1];
                $ayah_number_val = (int)$matches[2];

                $stmt_get_ayah_id->bindValue(':surah_id', $surah_id_val);
                $stmt_get_ayah_id->bindValue(':ayah_number', $ayah_number_val);
                $ayah_result = $stmt_get_ayah_id->execute();
                $ayah_row = $ayah_result->fetchArray();
                if ($ayah_row) {
                     $stmt_insert_ayah->bindValue(':theme_id', $theme_id);
                     $stmt_insert_ayah->bindValue(':surah_id', $surah_id_val);
                     $stmt_insert_ayah->bindValue(':ayah_id', $ayah_row['id']);
                     $stmt_insert_ayah->execute();
                } else {
                    error_log("Could not find ayah for theme: S{$surah_id_val}:A{$ayah_number_val}");
                }
            } else {
                 error_log("Invalid ayah format in theme input: {$ayah_str}");
            }
        }
    }

    $success = $db->exec("COMMIT;");
    $db->close();
    return $success ? true : $db->lastErrorMsg();
}


function get_hifz_status($user_id, $surah_id, $ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT status FROM hifz_tracking WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row ? $row['status'] : 'Not Started';
}

function update_hifz_status($user_id, $surah_id, $ayah_id, $status) {
    $db = new SQLite3(DB_PATH);
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM hifz_tracking WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt_check->bindValue(':user_id', $user_id);
    $stmt_check->bindValue(':surah_id', $surah_id);
    $stmt_check->bindValue(':ayah_id', $ayah_id);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    if ($row[0] > 0) {
        $stmt = $db->prepare("UPDATE hifz_tracking SET status = :status WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    } else {
        $stmt = $db->prepare("INSERT INTO hifz_tracking (user_id, surah_id, ayah_id, status) VALUES (:user_id, :surah_id, :ayah_id, :status)");
    }
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $stmt->bindValue(':status', $status);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function get_recitation_log($user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT rl.id, rl.surah_id, rl.ayah_from, rl.ayah_to, rl.recitation_date, rl.notes, s.name_english FROM recitation_log rl JOIN surahs s ON rl.surah_id = s.id WHERE user_id = :user_id ORDER BY recitation_date DESC");
    $stmt->bindValue(':user_id', $user_id);
    $results = $stmt->execute();
    $logs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $logs[] = $row;
    }
    $db->close();
    return $logs;
}

function add_recitation_log($user_id, $surah_id, $ayah_from, $ayah_to, $notes) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT INTO recitation_log (user_id, surah_id, ayah_from, ayah_to, notes) VALUES (:user_id, :surah_id, :ayah_from, :ayah_to, :notes)");
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_from', $ayah_from);
    $stmt->bindValue(':ayah_to', $ayah_to);
    $stmt->bindValue(':notes', $notes);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function delete_recitation_log($log_id, $user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("DELETE FROM recitation_log WHERE id = :log_id AND user_id = :user_id");
    $stmt->bindValue(':log_id', $log_id);
    $stmt->bindValue(':user_id', $user_id);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function search_quran($query, $language = 'Arabic') {
    $db = new SQLite3(DB_PATH);
    $sql = "SELECT a.id AS ayah_id, a.surah_id, a.ayah_number, a.arabic_text, s.name_english, t.text AS translation_text
            FROM ayahs a
            JOIN surahs s ON a.surah_id = s.id
            LEFT JOIN translations t ON a.id = t.ayah_id AND t.language = :language
            WHERE a.arabic_text LIKE :query_arabic";

    $params = [
        ':language' => $language,
        ':query_arabic' => '%' . $query . '%'
    ];

    if ($language !== 'Arabic') {
         $sql .= " OR t.text LIKE :query_translation";
         $params[':query_translation'] = '%' . $query . '%';
    }

    $stmt = $db->prepare($sql);
    foreach($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }

    $results = $stmt->execute();
    $search_results = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $search_results[] = $row;
    }
    $db->close();
    return $search_results;
}

function get_word_translations($word_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT ur_meaning, en_meaning FROM word_translations WHERE word_id = :word_id");
    $stmt->bindValue(':word_id', $word_id);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row;
}

function get_ayah_by_surah_ayah($surah_id, $ayah_number) {
     $db = new SQLite3(DB_PATH);
     $stmt = $db->prepare("SELECT id, arabic_text FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");
     $stmt->bindValue(':surah_id', $surah_id);
     $stmt->bindValue(':ayah_number', $ayah_number);
     $result = $stmt->execute();
     $row = $result->fetchArray(SQLITE3_ASSOC);
     $db->close();
     return $row;
}

function get_all_users() {
    $db = new SQLite3(DB_PATH);
    $results = $db->query("SELECT id, username, role FROM users ORDER BY username");
    $users = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    $db->close();
    return $users;
}

function update_user_role($user_id, $role) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :user_id");
    $stmt->bindValue(':role', $role);
    $stmt->bindValue(':user_id', $user_id);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function get_pending_contributions() {
    $db = new SQLite3(DB_PATH);
    $sql = "SELECT c.id, c.user_id, u.username, c.type, c.related_id, c.content, c.created_at
            FROM contributions c
            JOIN users u ON c.user_id = u.id
            WHERE c.status = 'Pending'";
    $results = $db->query($sql);
    $contributions = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $contributions[] = $row;
    }
    $db->close();
    return $contributions;
}

function approve_contribution($contribution_id) {
    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");

    $stmt_get = $db->prepare("SELECT user_id, type, related_id, content FROM contributions WHERE id = :id AND status = 'Pending'");
    $stmt_get->bindValue(':id', $contribution_id);
    $result_get = $stmt_get->execute();
    $contribution = $result_get->fetchArray(SQLITE3_ASSOC);

    $success = false;
    if ($contribution) {
        $user_id = $contribution['user_id'];
        $type = $contribution['type'];
        $related_id = $contribution['related_id'];
        $content = $contribution['content'];

        // Apply the contribution based on type
        switch ($type) {
            case 'Tafsir':
                // Assuming related_id is ayah_id for Tafsir contributions
                // This will add a community Tafsir entry. Need a separate table for community Tafsir.
                // For now, let's assume related_id is ayah_id and content is the tafsir text.
                // A proper implementation would involve a 'community_tafsir' table.
                // For this single-file example, we'll just mark the contribution as approved.
                // A more robust system would integrate this into a viewable community content.
                 error_log("Contribution type 'Tafsir' approval logic needs refinement.");
                break;
            case 'Theme':
                // Assuming related_id is theme_id for Theme contributions
                // Mark the theme as public (community) and approved
                $stmt_update_theme = $db->prepare("UPDATE themes SET is_public = 1, status = 'Approved' WHERE id = :theme_id AND user_id = :user_id");
                $stmt_update_theme->bindValue(':theme_id', $related_id);
                $stmt_update_theme->bindValue(':user_id', $user_id);
                $stmt_update_theme->execute();
                break;
            // Add cases for WordMeaning, AyahMeaning if they were implemented as contributions
            default:
                error_log("Unknown contribution type: " . $type);
                $db->exec("ROLLBACK;");
                $db->close();
                return false;
        }

        // Mark contribution as Approved
        $stmt_update_contribution = $db->prepare("UPDATE contributions SET status = 'Approved' WHERE id = :id");
        $stmt_update_contribution->bindValue(':id', $contribution_id);
        $success = $stmt_update_contribution->execute();
    }

    if ($success) {
        $db->exec("COMMIT;");
    } else {
        $db->exec("ROLLBACK;");
    }
    $db->close();
    return $success;
}

function reject_contribution($contribution_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("UPDATE contributions SET status = 'Rejected' WHERE id = :id");
    $stmt->bindValue(':id', $contribution_id);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

// Data Export/Import
function export_user_data($user_id) {
    $db = new SQLite3(DB_PATH);
    $data = [];

    // Personal Tafsir
    $stmt_tafsir = $db->prepare("SELECT surah_id, ayah_id, tafsir_text FROM personal_tafsir WHERE user_id = :user_id");
    $stmt_tafsir->bindValue(':user_id', $user_id);
    $results_tafsir = $stmt_tafsir->execute();
    $data['personal_tafsir'] = [];
    while ($row = $results_tafsir->fetchArray(SQLITE3_ASSOC)) {
        $data['personal_tafsir'][] = $row;
    }

    // Themes (Personal)
    $stmt_themes = $db->prepare("SELECT id, theme_name, description FROM themes WHERE user_id = :user_id");
    $stmt_themes->bindValue(':user_id', $user_id);
    $results_themes = $stmt_themes->execute();
    $data['personal_themes'] = [];
    while ($theme_row = $results_themes->fetchArray(SQLITE3_ASSOC)) {
        $theme_id = $theme_row['id'];
        $theme_data = $theme_row;
        $theme_data['ayahs'] = [];

        $stmt_theme_ayahs = $db->prepare("SELECT surah_id, ayah_id FROM theme_ayahs WHERE theme_id = :theme_id");
        $stmt_theme_ayahs->bindValue(':theme_id', $theme_id);
        $results_theme_ayahs = $stmt_theme_ayahs->execute();
        while ($ayah_row = $results_theme_ayahs->fetchArray(SQLITE3_ASSOC)) {
             // Need to get ayah_number for export to make it easier to read/import
             $stmt_get_ayah_number = $db->prepare("SELECT ayah_number FROM ayahs WHERE id = :ayah_id");
             $stmt_get_ayah_number->bindValue(':ayah_id', $ayah_row['ayah_id']);
             $ayah_num_row = $stmt_get_ayah_number->execute()->fetchArray();
             if ($ayah_num_row) {
                 $data['personal_themes'][$theme_id]['ayahs'][] = [
                     'surah_id' => $ayah_row['surah_id'],
                     'ayah_number' => $ayah_num_row['ayah_number'] // Export ayah_number instead of ayah_id
                 ];
             }
        }
         // Re-index the personal_themes array after adding ayahs
         $data['personal_themes'][] = $theme_data;
         unset($data['personal_themes'][$theme_id]); // Remove the temporary key
    }
     // Re-index the final personal_themes array
    $data['personal_themes'] = array_values($data['personal_themes']);


    // Hifz Tracking
    $stmt_hifz = $db->prepare("SELECT surah_id, ayah_id, status FROM hifz_tracking WHERE user_id = :user_id");
    $stmt_hifz->bindValue(':user_id', $user_id);
    $results_hifz = $stmt_hifz->execute();
    $data['hifz_tracking'] = [];
    while ($row = $results_hifz->fetchArray(SQLITE3_ASSOC)) {
        $data['hifz_tracking'][] = $row;
    }

    // Recitation Log
    $stmt_recitation = $db->prepare("SELECT surah_id, ayah_from, ayah_to, recitation_date, notes FROM recitation_log WHERE user_id = :user_id");
    $stmt_recitation->bindValue(':user_id', $user_id);
    $results_recitation = $stmt_recitation->execute();
    $data['recitation_log'] = [];
    while ($row = $results_recitation->fetchArray(SQLITE3_ASSOC)) {
        $data['recitation_log'][] = $row;
    }

    $db->close();
    return json_encode($data, JSON_PRETTY_PRINT);
}

function import_user_data($user_id, $json_data) {
    $data = json_decode($json_data, true);
    if ($data === null) {
        return "Invalid JSON data.";
    }

    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");
    $errors = [];

    // Import Personal Tafsir
    if (isset($data['personal_tafsir']) && is_array($data['personal_tafsir'])) {
        $stmt_tafsir = $db->prepare("INSERT OR REPLACE INTO personal_tafsir (user_id, surah_id, ayah_id, tafsir_text) VALUES (:user_id, :surah_id, :ayah_id, :tafsir_text)");
         $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number"); // Need ayah_id from surah/ayah_number

        foreach ($data['personal_tafsir'] as $item) {
            if (isset($item['surah_id'], $item['ayah_id'], $item['tafsir_text'])) {
                 // Need to get the actual ayah_id from the database based on surah_id and ayah_id (which is ayah_number in export)
                 $stmt_get_ayah_id->bindValue(':surah_id', $item['surah_id']);
                 $stmt_get_ayah_id->bindValue(':ayah_number', $item['ayah_id']); // Assuming ayah_id in export is ayah_number
                 $ayah_row = $stmt_get_ayah_id->execute()->fetchArray();
                 if ($ayah_row) {
                    $stmt_tafsir->bindValue(':user_id', $user_id);
                    $stmt_tafsir->bindValue(':surah_id', $item['surah_id']);
                    $stmt_tafsir->bindValue(':ayah_id', $ayah_row['id']); // Use the actual ayah_id
                    $stmt_tafsir->bindValue(':tafsir_text', $item['tafsir_text']);
                    if (!$stmt_tafsir->execute()) {
                        $errors[] = "Error importing Tafsir for S{$item['surah_id']}:A{$item['ayah_id']}: " . $db->lastErrorMsg();
                    }
                 } else {
                     $errors[] = "Could not find Ayah S{$item['surah_id']}:A{$item['ayah_id']} for Tafsir import.";
                 }
            } else {
                $errors[] = "Invalid personal_tafsir item format.";
            }
        }
    }

    // Import Themes (Personal)
    if (isset($data['personal_themes']) && is_array($data['personal_themes'])) {
        $stmt_theme = $db->prepare("INSERT INTO themes (user_id, theme_name, description, is_public, status) VALUES (:user_id, :theme_name, :description, 0, 'Approved')"); // Import as personal, approved
        $stmt_theme_ayah = $db->prepare("INSERT INTO theme_ayahs (theme_id, surah_id, ayah_id) VALUES (:theme_id, :surah_id, :ayah_id)");
         $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number"); // Need ayah_id from surah/ayah_number

        foreach ($data['personal_themes'] as $theme) {
            if (isset($theme['theme_name'], $theme['description'], $theme['ayahs']) && is_array($theme['ayahs'])) {
                 // Check if theme name already exists for this user
                 $stmt_check_theme = $db->prepare("SELECT id FROM themes WHERE user_id = :user_id AND theme_name = :theme_name");
                 $stmt_check_theme->bindValue(':user_id', $user_id);
                 $stmt_check_theme->bindValue(':theme_name', $theme['theme_name']);
                 $existing_theme = $stmt_check_theme->execute()->fetchArray();

                 if ($existing_theme) {
                     $errors[] = "Skipping import of theme '{$theme['theme_name']}' as it already exists for this user.";
                     continue;
                 }


                $stmt_theme->bindValue(':user_id', $user_id);
                $stmt_theme->bindValue(':theme_name', $theme['theme_name']);
                $stmt_theme->bindValue(':description', $theme['description']);
                if ($stmt_theme->execute()) {
                    $theme_id = $db->lastInsertRowID();
                    foreach ($theme['ayahs'] as $ayah) {
                        if (isset($ayah['surah_id'], $ayah['ayah_number'])) { // Expecting ayah_number from export
                             // Need to get the actual ayah_id from the database based on surah_id and ayah_number
                             $stmt_get_ayah_id->bindValue(':surah_id', $ayah['surah_id']);
                             $stmt_get_ayah_id->bindValue(':ayah_number', $ayah['ayah_number']);
                             $ayah_row = $stmt_get_ayah_id->execute()->fetchArray();
                             if ($ayah_row) {
                                $stmt_theme_ayah->bindValue(':theme_id', $theme_id);
                                $stmt_theme_ayah->bindValue(':surah_id', $ayah['surah_id']);
                                $stmt_theme_ayah->bindValue(':ayah_id', $ayah_row['id']); // Use the actual ayah_id
                                if (!$stmt_theme_ayah->execute()) {
                                    $errors[] = "Error importing Ayah for theme '{$theme['theme_name']}': S{$ayah['surah_id']}:A{$ayah['ayah_number']}: " . $db->lastErrorMsg();
                                }
                             } else {
                                 $errors[] = "Could not find Ayah S{$ayah['surah_id']}:A{$ayah['ayah_number']} for theme '{$theme['theme_name']}' during import.";
                             }
                        } else {
                             $errors[] = "Invalid theme ayah item format for theme '{$theme['theme_name']}'.";
                        }
                    }
                } else {
                    $errors[] = "Error importing theme '{$theme['theme_name']}': " . $db->lastErrorMsg();
                }
            } else {
                $errors[] = "Invalid personal_themes item format.";
            }
        }
    }

    // Import Hifz Tracking
    if (isset($data['hifz_tracking']) && is_array($data['hifz_tracking'])) {
        $stmt_hifz = $db->prepare("INSERT OR REPLACE INTO hifz_tracking (user_id, surah_id, ayah_id, status) VALUES (:user_id, :surah_id, :ayah_id, :status)");
         $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number"); // Need ayah_id from surah/ayah_number

        foreach ($data['hifz_tracking'] as $item) {
            if (isset($item['surah_id'], $item['ayah_id'], $item['status'])) { // Expecting ayah_id in export (which is ayah_number)
                 // Need to get the actual ayah_id from the database based on surah_id and ayah_id (which is ayah_number in export)
                 $stmt_get_ayah_id->bindValue(':surah_id', $item['surah_id']);
                 $stmt_get_ayah_id->bindValue(':ayah_number', $item['ayah_id']); // Assuming ayah_id in export is ayah_number
                 $ayah_row = $stmt_get_ayah_id->execute()->fetchArray();
                 if ($ayah_row) {
                    $stmt_hifz->bindValue(':user_id', $user_id);
                    $stmt_hifz->bindValue(':surah_id', $item['surah_id']);
                    $stmt_hifz->bindValue(':ayah_id', $ayah_row['id']); // Use the actual ayah_id
                    $stmt_hifz->bindValue(':status', $item['status']);
                    if (!$stmt_hifz->execute()) {
                        $errors[] = "Error importing Hifz for S{$item['surah_id']}:A{$item['ayah_id']}: " . $db->lastErrorMsg();
                    }
                 } else {
                     $errors[] = "Could not find Ayah S{$item['surah_id']}:A{$item['ayah_id']} for Hifz import.";
                 }
            } else {
                $errors[] = "Invalid hifz_tracking item format.";
            }
        }
    }

    // Import Recitation Log
    if (isset($data['recitation_log']) && is_array($data['recitation_log'])) {
        $stmt = $db->prepare("INSERT INTO recitation_log (user_id, surah_id, ayah_from, ayah_to, recitation_date, notes) VALUES (:user_id, :surah_id, :ayah_from, :ayah_to, :recitation_date, :notes)");
        foreach ($data['recitation_log'] as $item) {
            if (isset($item['surah_id'], $item['ayah_from'], $item['ayah_to'], $item['recitation_date'], $item['notes'])) {
                $stmt->bindValue(':user_id', $user_id);
                $stmt->bindValue(':surah_id', $item['surah_id']);
                $stmt->bindValue(':ayah_from', $item['ayah_from']);
                $stmt->bindValue(':ayah_to', $item['ayah_to']);
                $stmt->bindValue(':recitation_date', $item['recitation_date']);
                $stmt->bindValue(':notes', $item['notes']);
                if (!$stmt->execute()) {
                    $errors[] = "Error importing Recitation Log for S{$item['surah_id']}:A{$item['ayah_from']}-A{$item['ayah_to']}: " . $db->lastErrorMsg();
                }
            } else {
                $errors[] = "Invalid recitation_log item format.";
            }
        }
    }


    if (empty($errors)) {
        $db->exec("COMMIT;");
        $db->close();
        return true;
    } else {
        $db->exec("ROLLBACK;");
        $db->close();
        return "Import completed with errors: " . implode(", ", $errors);
    }
}


// Helper functions for rendering HTML
function render_header($title = APP_NAME) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <meta name="description" content="Offline Quran Study Application with Tafsir, Themes, Hifz, and more.">
        <meta name="keywords" content="Quran, Tafsir, Hifz, Islam, Study, Offline, PHP, SQLite">
        <meta name="author" content="Yasin Ullah">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
        <style>
            body { font-family: 'Arial', sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f4f4f4; color: #333; }
            .container { width: 90%; margin: 20px auto; background: #fff; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
            header { background: #007bff; color: #fff; padding: 10px 0; text-align: center; margin-bottom: 20px; }
            header h1 { margin: 0; }
            nav { background: #e9ecef; padding: 10px 0; text-align: center; margin-bottom: 20px; }
            nav a { margin: 0 15px; text-decoration: none; color: #007bff; font-weight: bold; }
            nav a:hover { text-decoration: underline; }
            .content { padding: 20px; }
            .quran-ayah { border-bottom: 1px solid #eee; padding: 15px 0; margin-bottom: 15px; }
            .quran-ayah:last-child { border-bottom: none; }
            .arabic-text { font-size: 24px; text-align: right; margin-bottom: 10px; direction: rtl; font-family: 'Scheherazade New', serif; }
            .translation-text { font-size: 16px; color: #555; margin-bottom: 10px; }
            .ayah-meta { font-size: 14px; color: #888; text-align: left; }
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            .form-group input[type="text"], .form-group input[type="password"], .form-group textarea, .form-group select { width: 100%; padding: 8px; border: 1px solid #ccc; box-sizing: border-box; }
            .btn { background: #007bff; color: #fff; padding: 10px 15px; border: none; cursor: pointer; font-size: 16px; }
            .btn:hover { background: #0056b3; }
            .error { color: red; margin-bottom: 10px; }
            .success { color: green; margin-bottom: 10px; }
            .login-register { text-align: center; margin-top: 20px; }
            .login-register a { margin: 0 10px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            table, th, td { border: 1px solid #ddd; }
            th, td { padding: 10px; text-align: left; }
            th { background-color: #f2f2f2; }
            .admin-panel { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px; }
            .contribution-item { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; background-color: #f9f9f9; }
            .contribution-item h4 { margin-top: 0; }
             .word-meaning { font-size: 14px; color: #0056b3; cursor: pointer; text-decoration: underline; }
             .word-meaning:hover { color: #003f7f; }
             .meaning-tooltip {
                 position: absolute;
                 background-color: #ffffcc;
                 border: 1px solid #ccc;
                 padding: 5px;
                 z-index: 100;
                 display: none;
                 font-size: 12px;
             }
             .game-area { text-align: center; margin-top: 20px; }
             .game-area button { margin: 5px; }
             #ayah-jumble-container { margin-top: 20px; }
             .jumbled-word { display: inline-block; margin: 5px; padding: 8px; border: 1px solid #ccc; cursor: pointer; background-color: #eee; }
             .jumbled-word.selected { background-color: #a0a0a0; color: #fff; }
             #ayah-jumble-output { border: 1px solid #ccc; min-height: 50px; padding: 10px; margin-top: 10px; background-color: #fff; direction: rtl; }
             #ayah-jumble-output .placed-word { display: inline-block; margin: 5px; padding: 8px; border: 1px solid #b0b0b0; background-color: #d0d0d0; }
             .theme-ayah-list { list-style: none; padding: 0; }
             .theme-ayah-list li { margin-bottom: 5px; }
             .theme-ayah-list li a { text-decoration: none; color: #007bff; }
             .theme-ayah-list li a:hover { text-decoration: underline; }
             *, select, textarea, input {
                 font-family: calibri !important;
             }
        </style>
         <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    </head>
    <body>
        <header>
            <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
        </header>
        <nav>
            <a href="?view=quran">Quran Viewer</a>
            <?php if (is_logged_in()): ?>
                <a href="?view=tafsir">Personal Tafsir</a>
                <a href="?view=themes">Themes</a>
                <a href="?view=hifz">Hifz Hub</a>
                <a href="?view=recitation">Recitation Log</a>
                 <a href="?view=games">Games</a>
                 <a href="?view=profile">Profile</a>
                <?php if (has_permission('Ulama')): ?>
                     <a href="?view=contributions">Contributions</a>
                <?php endif; ?>
                 <?php if (has_permission('Admin')): ?>
                     <a href="?view=admin">Admin Panel</a>
                 <?php endif; ?>
                <a href="?action=logout">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            <?php else: ?>
                <a href="?view=themes&filter=community">Community Themes</a>
                 <a href="?view=themes&filter=ulama">Ulama Themes</a>
                 <a href="?view=games">Games</a>
                <a href="?view=login">Login</a>
                <a href="?view=register">Register</a>
            <?php endif; ?>
             <a href="?view=search">Search</a>
        </nav>
        <div class="container">
            <div class="content">
    <?php
}

function render_footer() {
    ?>
            </div>
        </div>
        <footer>
            <p style="text-align: center; margin-top: 20px; color: #555;">© <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Developed by Yasin Ullah.</p>
        </footer>
        <div id="meaning-tooltip" class="meaning-tooltip"></div>
        <script>
            // Basic JavaScript for interactivity (tooltips, games)
            document.addEventListener('DOMContentLoaded', () => {
                const tooltip = document.getElementById('meaning-tooltip');

                document.querySelectorAll('.word-meaning').forEach(wordSpan => {
                    wordSpan.addEventListener('mouseover', (event) => {
                        const urMeaning = wordSpan.getAttribute('data-ur');
                        const enMeaning = wordSpan.getAttribute('data-en');
                        let content = '';
                        if (urMeaning) content += 'Urdu: ' + urMeaning + '<br>';
                        if (enMeaning) content += 'English: ' + enMeaning;

                        if (content) {
                            tooltip.innerHTML = content;
                            tooltip.style.display = 'block';
                            tooltip.style.left = (event.pageX + 10) + 'px';
                            tooltip.style.top = (event.pageY + 10) + 'px';
                        }
                    });

                    wordSpan.addEventListener('mouseout', () => {
                        tooltip.style.display = 'none';
                    });
                });

                // Ayah Jumble Game Logic (Basic)
                const jumbleContainer = document.getElementById('ayah-jumble-container');
                const jumbleOutput = document.getElementById('ayah-jumble-output');
                const jumbleCheckBtn = document.getElementById('ayah-jumble-check');
                const jumbleResetBtn = document.getElementById('ayah-jumble-reset');
                const jumbleMessage = document.getElementById('ayah-jumble-message');

                let selectedWord = null;
                let correctOrder = [];

                if (jumbleContainer && jumbleOutput) {
                     // Get correct order from data attribute
                     const correctOrderAttr = jumbleContainer.getAttribute('data-correct-order');
                     if (correctOrderAttr) {
                         try {
                             correctOrder = JSON.parse(correctOrderAttr);
                         } catch (e) {
                             console.error("Failed to parse correct order:", e);
                             correctOrder = []; // Fallback
                         }
                     }


                    jumbleContainer.addEventListener('click', (event) => {
                        const target = event.target;
                        if (target.classList.contains('jumbled-word')) {
                            if (selectedWord) {
                                selectedWord.classList.remove('selected');
                            }
                            selectedWord = target;
                            selectedWord.classList.add('selected');
                        }
                    });

                    jumbleOutput.addEventListener('click', (event) => {
                         const target = event.target;
                        if (target.classList.contains('placed-word')) {
                             // Move placed word back to jumble container
                             const wordText = target.textContent;
                             const wordElement = document.createElement('span');
                             wordElement.classList.add('jumbled-word');
                             wordElement.textContent = wordText;
                             jumbleContainer.appendChild(wordElement);
                             target.remove();
                        } else if (selectedWord) {
                             // Place selected word in output
                             const placedWord = document.createElement('span');
                             placedWord.classList.add('placed-word');
                             placedWord.textContent = selectedWord.textContent;
                             jumbleOutput.appendChild(placedWord);
                             selectedWord.remove();
                             selectedWord = null; // Deselect
                        }
                    });

                    if(jumbleCheckBtn) {
                        jumbleCheckBtn.addEventListener('click', () => {
                            const placedWords = Array.from(jumbleOutput.querySelectorAll('.placed-word')).map(el => el.textContent.trim());
                            const isCorrect = placedWords.every((word, index) => word === correctOrder[index]) && placedWords.length === correctOrder.length;

                            if (jumbleMessage) {
                                if (isCorrect) {
                                    jumbleMessage.textContent = "Correct! Well done!";
                                    jumbleMessage.style.color = 'green';
                                } else {
                                    jumbleMessage.textContent = "Incorrect. Try again.";
                                    jumbleMessage.style.color = 'red';
                                }
                            }
                        });
                    }

                    if(jumbleResetBtn) {
                        jumbleResetBtn.addEventListener('click', () => {
                            jumbleOutput.innerHTML = ''; // Clear output
                            jumbleContainer.innerHTML = ''; // Clear jumble area
                            if (jumbleMessage) jumbleMessage.textContent = '';

                            // Re-populate jumble area from correct order (shuffle)
                            let shuffledWords = [...correctOrder];
                            for (let i = shuffledWords.length - 1; i > 0; i--) {
                                const j = Math.floor(Math.random() * (i + 1));
                                [shuffledWords[i], shuffledWords[j]] = [shuffledWords[j], shuffledWords[i]]; // Swap
                            }

                            shuffledWords.forEach(word => {
                                const wordElement = document.createElement('span');
                                wordElement.classList.add('jumbled-word');
                                wordElement.textContent = word;
                                jumbleContainer.appendChild(wordElement);
                            });
                             selectedWord = null; // Reset selection
                        });
                    }
                }
            });
        </script>
    </body>
    </html>
    <?php
}

// Routing and Request Handling
$view = $_GET['view'] ?? 'quran';
$action = $_GET['action'] ?? null;
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                if (authenticate($_POST['username'], $_POST['password'])) {
                    header('Location: ?view=quran');
                    exit;
                } else {
                    $error = "Invalid username or password.";
                    $view = 'login'; // Stay on login page
                }
                break;
            case 'register':
                $result = register_user($_POST['username'], $_POST['password']);
                if ($result === true) {
                    $message = "Registration successful. You can now login.";
                    $view = 'login'; // Redirect to login page
                } else {
                    $error = $result;
                    $view = 'register'; // Stay on register page
                }
                break;
             case 'save_tafsir':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_id = (int)$_POST['ayah_id'];
                     $tafsir_text = $_POST['tafsir_text'];
                     if (save_personal_tafsir($user_id, $surah_id, $ayah_id, $tafsir_text)) {
                         $message = "Personal Tafsir saved successfully.";
                     } else {
                         $error = "Failed to save Personal Tafsir.";
                     }
                     // Redirect back to the ayah view or tafsir page
                     header("Location: ?view=quran&surah={$surah_id}#ayah-{$ayah_id}");
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                 break;
            case 'save_theme':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $theme_name = trim($_POST['theme_name']);
                     $description = trim($_POST['description']);
                     $ayahs = isset($_POST['theme_ayahs']) ? explode("\n", str_replace("\r", "", $_POST['theme_ayahs'])) : []; // Split by newline
                     // Also handle comma separated if needed, but newline is simpler for textarea
                     $is_public = isset($_POST['is_public']) && $_POST['is_public'] == '1' ? 1 : 0; // User can suggest community (1) or keep private (0)
                     // Ulama/Admin can set to public (2) directly
                     if (has_permission('Ulama') && isset($_POST['is_public']) && $_POST['is_public'] == '2') {
                         $is_public = 2;
                     }
                     $status = ($is_public == 1 && !has_permission('Ulama')) ? 'Pending' : 'Approved'; // User suggestions are pending unless Ulama/Admin

                     if (empty($theme_name)) {
                         $error = "Theme name cannot be empty.";
                         $view = 'themes'; // Stay on themes page
                     } else {
                         $result = save_theme($user_id, $theme_name, $description, $ayahs, $is_public, $status);
                         if ($result === true) {
                             $message = "Theme '{$theme_name}' saved successfully." . ($status === 'Pending' ? " Awaiting review." : "");
                             header('Location: ?view=themes');
                             exit;
                         } else {
                             $error = "Failed to save theme: " . $result;
                             $view = 'themes'; // Stay on themes page
                         }
                     }
                 } else {
                     $error = "Permission denied.";
                 }
                 break;
            case 'update_hifz':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_id = (int)$_POST['ayah_id'];
                     $status = $_POST['hifz_status'];
                     if (update_hifz_status($user_id, $surah_id, $ayah_id, $status)) {
                         $message = "Hifz status updated.";
                     } else {
                         $error = "Failed to update Hifz status.";
                     }
                     // Redirect back to the ayah view or hifz page
                      header("Location: ?view=quran&surah={$surah_id}#ayah-{$ayah_id}");
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'add_recitation_log':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_from = (int)$_POST['ayah_from'];
                     $ayah_to = (int)$_POST['ayah_to'];
                     $notes = $_POST['notes'];
                      if ($ayah_from > $ayah_to) {
                          $error = "Start Ayah cannot be greater than End Ayah.";
                          $view = 'recitation'; // Stay on recitation page
                      } else {
                         if (add_recitation_log($user_id, $surah_id, $ayah_from, $ayah_to, $notes)) {
                             $message = "Recitation log added successfully.";
                             header('Location: ?view=recitation');
                             exit;
                         } else {
                             $error = "Failed to add recitation log.";
                             $view = 'recitation'; // Stay on recitation page
                         }
                      }
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'delete_recitation_log':
                 if (is_logged_in() && has_permission('User')) {
                     $log_id = (int)$_POST['log_id'];
                     $user_id = get_user_id();
                     if (delete_recitation_log($log_id, $user_id)) {
                         $message = "Recitation log deleted.";
                     } else {
                         $error = "Failed to delete recitation log.";
                     }
                     header('Location: ?view=recitation');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'update_user_role':
                 if (has_permission('Admin')) {
                     $user_id = (int)$_POST['user_id'];
                     $role = $_POST['role'];
                     if (update_user_role($user_id, $role)) {
                         $message = "User role updated.";
                     } else {
                         $error = "Failed to update user role.";
                     }
                     header('Location: ?view=admin');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'approve_contribution':
                 if (has_permission('Ulama')) {
                     $contribution_id = (int)$_POST['contribution_id'];
                     if (approve_contribution($contribution_id)) {
                         $message = "Contribution approved.";
                     } else {
                         $error = "Failed to approve contribution.";
                     }
                     header('Location: ?view=contributions');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'reject_contribution':
                 if (has_permission('Ulama')) {
                     $contribution_id = (int)$_POST['contribution_id'];
                     if (reject_contribution($contribution_id)) {
                         $message = "Contribution rejected.";
                     } else {
                         $error = "Failed to reject contribution.";
                     }
                     header('Location: ?view=contributions');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'load_initial_data':
                 if (has_permission('Admin')) {
                     load_initial_data();
                     $message = "Initial data loading process initiated. Check logs for errors.";
                     header('Location: ?view=admin');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
             case 'export_data':
                if (is_logged_in() && has_permission('User')) {
                    $user_id = get_user_id();
                    $data = export_user_data($user_id);
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="quran_study_export_' . $_SESSION['username'] . '_' . date('YmdHis') . '.json"');
                    echo $data;
                    exit;
                } else {
                    $error = "Permission denied.";
                }
                break;
             case 'import_data':
                 if (is_logged_in() && has_permission('User')) {
                     if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                         $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
                         $user_id = get_user_id();
                         $result = import_user_data($user_id, $file_content);
                         if ($result === true) {
                             $message = "Data imported successfully.";
                         } else {
                             $error = "Data import failed: " . $result;
                         }
                     } else {
                         $error = "Error uploading file.";
                     }
                     $view = 'profile'; // Stay on profile page
                 } else {
                     $error = "Permission denied.";
                 }
                 break;

        }
    }
} elseif ($action === 'logout') {
    logout();
} elseif ($action === 'export_data') {
    // Handle GET request for export (e.g., link click)
    if (is_logged_in() && has_permission('User')) {
        $user_id = get_user_id();
        $data = export_user_data($user_id);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="quran_study_export_' . $_SESSION['username'] . '_' . date('YmdHis') . '.json"');
        echo $data;
        exit;
    } else {
        $error = "Permission denied.";
        $view = 'login'; // Redirect to login if not allowed
    }
}


// Ensure database is initialized on first run
if (!file_exists(DB_PATH)) {
    init_db();
    // Optionally redirect to an admin setup page or show a message
    // For this single file, we'll just show a message on the admin page
    if ($view !== 'admin') {
         $view = 'admin'; // Force admin view to load data
         $message = "Database initialized. Please load initial data.";
    }
}


// Render Views
render_header();

if ($message) {
    echo "<div class='success'>{$message}</div>";
}
if ($error) {
    echo "<div class='error'>{$error}</div>";
}

switch ($view) {
    case 'quran':
        $surahs = get_surahs();
        $selected_surah_id = isset($_GET['surah']) ? (int)$_GET['surah'] : 1;
        // Ensure selected_surah_id is valid
        $valid_surah_ids = array_column($surahs, 'id');
        if (!in_array($selected_surah_id, $valid_surah_ids)) {
            $selected_surah_id = 1; // Default to Surah 1 if invalid
        }

        $ayahs = get_ayahs_by_surah($selected_surah_id);
        $current_user_id = get_user_id();

        echo "<h2>Quran Viewer</h2>";
        echo "<div class='form-group'>";
        echo "<label for='surah-select'>Select Surah:</label>";
        echo "<select id='surah-select' onchange='window.location.href=\"?view=quran&surah=\" + this.value'>";
        $selected_surah_name = 'Unknown Surah';
        foreach ($surahs as $surah) {
            $selected = ($surah['id'] == $selected_surah_id) ? 'selected' : '';
            echo "<option value='{$surah['id']}' {$selected}>{$surah['id']}. {$surah['name_english']}</option>";
            if ($surah['id'] == $selected_surah_id) {
                 $selected_surah_name = $surah['name_english'];
            }
        }
        echo "</select>";
        echo "</div>";

        echo "<h3>Surah {$selected_surah_id}: " . htmlspecialchars($selected_surah_name) . "</h3>";

        foreach ($ayahs as $ayah) {
            $translations = get_translations_by_ayah($ayah['id']);
            $personal_tafsir = is_logged_in() ? get_personal_tafsir($current_user_id, $ayah['surah_id'], $ayah['id']) : '';
            $hifz_status = is_logged_in() ? get_hifz_status($current_user_id, $ayah['surah_id'], $ayah['id']) : 'Not Started';

            echo "<div class='quran-ayah' id='ayah-{$ayah['id']}'>";
            echo "<div class='ayah-meta'>{$ayah['surah_id']}:{$ayah['ayah_number']}</div>";
            echo "<div class='arabic-text'>" . htmlspecialchars($ayah['arabic_text']) . "</div>";

            // Display translations
            foreach ($translations as $lang => $text) {
                echo "<div class='translation-text'><strong>{$lang}:</strong> " . htmlspecialchars($text) . "</div>";
            }

            // Display Personal Tafsir (if logged in)
            if (is_logged_in() && has_permission('User')) {
                echo "<h4>Personal Tafsir</h4>";
                echo "<form method='POST'>";
                echo "<input type='hidden' name='action' value='save_tafsir'>";
                echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                echo "<textarea name='tafsir_text' rows='4' cols='50'>" . htmlspecialchars($personal_tafsir) . "</textarea><br>";
                echo "<button type='submit' class='btn'>Save Tafsir</button>";
                echo "</form>";

                // Hifz Tracking
                 echo "<h4>Hifz Status</h4>";
                 echo "<form method='POST'>";
                 echo "<input type='hidden' name='action' value='update_hifz'>";
                 echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                 echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                 echo "<select name='hifz_status'>";
                 $statuses = ['Not Started', 'Memorizing', 'Memorized'];
                 foreach($statuses as $status) {
                     $selected = ($hifz_status === $status) ? 'selected' : '';
                     echo "<option value='{$status}' {$selected}>{$status}</option>";
                 }
                 echo "</select>";
                 echo "<button type='submit' class='btn'>Update Hifz</button>";
                 echo "</form>";
            }

            echo "</div>"; // .quran-ayah
        }
        break;

    case 'tafsir':
        if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to view Personal Tafsir.</div>";
            break;
        }
        $user_id = get_user_id();
        // Fetch all personal tafsir entries for the user
        $db = new SQLite3(DB_PATH);
        $stmt = $db->prepare("SELECT pt.surah_id, pt.ayah_id, pt.tafsir_text, a.ayah_number, s.name_english FROM personal_tafsir pt JOIN ayahs a ON pt.ayah_id = a.id JOIN surahs s ON pt.surah_id = s.id WHERE pt.user_id = :user_id ORDER BY pt.surah_id, a.ayah_number");
        $stmt->bindValue(':user_id', $user_id);
        $results = $stmt->execute();
        $personal_tafsirs = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $personal_tafsirs[] = $row;
        }
        $db->close();

        echo "<h2>Personal Tafsir</h2>";
        if (empty($personal_tafsirs)) {
            echo "<p>You have not added any personal tafsir yet. Go to the Quran Viewer to add notes to ayahs.</p>";
        } else {
            foreach ($personal_tafsirs as $tafsir) {
                echo "<div class='quran-ayah'>";
                echo "<div class='ayah-meta'><a href='?view=quran&surah={$tafsir['surah_id']}#ayah-{$tafsir['ayah_id']}'>{$tafsir['surah_id']}:{$tafsir['ayah_number']} - {$tafsir['name_english']}</a></div>";
                echo "<div class='translation-text'>" . nl2br(htmlspecialchars($tafsir['tafsir_text'])) . "</div>";
                echo "</div>";
            }
        }
        break;

    case 'themes':
        $filter = $_GET['filter'] ?? 'all'; // all, community, ulama, personal, pending
        $user_id = get_user_id();
        $can_create_themes = is_logged_in() && has_permission('User');
        $can_view_pending = has_permission('Ulama');

        echo "<h2>Thematic Linker</h2>";

        echo "<p>View: ";
        echo "<a href='?view=themes&filter=all'>All Public</a> | ";
        echo "<a href='?view=themes&filter=community'>Community</a> | ";
        echo "<a href='?view=themes&filter=ulama'>Ulama/Admin</a>";
        if ($can_create_themes) {
             echo " | <a href='?view=themes&filter=personal'>My Themes</a>";
        }
        if ($can_view_pending) {
             echo " | <a href='?view=themes&filter=pending'>Pending Contributions</a>";
        }
        echo "</p>";


        if ($can_create_themes && ($filter === 'personal' || !isset($_GET['filter']))) {
             echo "<h3>Create/Edit My Theme</h3>";
             echo "<form method='POST'>";
             echo "<input type='hidden' name='action' value='save_theme'>";
             echo "<div class='form-group'>";
             echo "<label for='theme_name'>Theme Name:</label>";
             echo "<input type='text' id='theme_name' name='theme_name' required>";
             echo "</div>";
             echo "<div class='form-group'>";
             echo "<label for='description'>Description:</label>";
             echo "<textarea id='description' name='description' rows='3'></textarea>";
             echo "</div>";
             echo "<div class='form-group'>";
             echo "<label>Ayahs (Surah-Ayah, one per line or comma separated):</label>";
             echo "<textarea name='theme_ayahs' rows='5' placeholder='e.g., 1-1
1-2
2-255 or 1-1, 1-2, 2-255'></textarea>";
             echo "</div>";
             echo "<div class='form-group'>";
             echo "<label for='is_public'>Visibility:</label>";
             echo "<select id='is_public' name='is_public'>";
             echo "<option value='0'>Private (Only I can see)</option>";
             echo "<option value='1'>Suggest for Community (Requires Review)</option>";
             // Admins/Ulama can create public themes directly (is_public=2)
             if (has_permission('Ulama')) {
                  echo "<option value='2'>Public (Ulama/Admin Contribution)</option>";
             }
             echo "</select>";
             echo "</div>";
             echo "<button type='submit' class='btn'>Save Theme</button>";
             echo "</form>";
             echo "<hr>";
        }

        $themes = get_themes($filter, $user_id);

        echo "<h3>";
        if ($filter === 'community') echo "Community Themes";
        elseif ($filter === 'ulama') echo "Ulama/Admin Themes";
        elseif ($filter === 'personal') echo "My Themes";
        elseif ($filter === 'pending') echo "Pending Theme Contributions";
        else echo "All Public Themes";
        echo "</h3>";


        if (empty($themes)) {
            echo "<p>No themes found for this filter.</p>";
        } else {
            foreach ($themes as $theme) {
                echo "<div>";
                echo "<h4>" . htmlspecialchars($theme['theme_name']) . " by " . htmlspecialchars($theme['username']) . "</h4>";
                echo "<p>" . nl2br(htmlspecialchars($theme['description'])) . "</p>";
                if ($filter === 'pending') {
                     echo "<p><strong>Status:</strong> {$theme['status']}</p>";
                }
                $theme_ayahs = get_theme_ayahs($theme['id']);
                if (!empty($theme_ayahs)) {
                    echo "<p><strong>Ayahs:</strong></p>";
                    echo "<ul class='theme-ayah-list'>";
                    foreach($theme_ayahs as $ayah) {
                         echo "<li><a href='?view=quran&surah={$ayah['surah_id']}#ayah-{$ayah['ayah_id']}'>{$ayah['surah_id']}:{$ayah['ayah_number']} - {$ayah['name_english']}</a></li>";
                    }
                    echo "</ul>";
                }
                 // Add edit/delete for personal themes
                 if ($filter === 'personal' && $theme['user_id'] == $user_id) {
                     // Edit functionality could pre-fill the form above
                     // Delete functionality would require a separate action
                 }
                echo "</div><hr>";
            }
        }
        break;

    case 'hifz':
         if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to use the Hifz Hub.</div>";
            break;
        }
        $user_id = get_user_id();
        $surahs = get_surahs();

        echo "<h2>Hifz Hub</h2>";
        echo "<p>Track your Quran memorization progress.</p>";

        // Display Hifz status per Surah/Ayah (summary or detailed)
        // For simplicity, let's show a list of Surahs and allow drilling down
        echo "<h3>Surah Progress</h3>";
        echo "<ul>";
        foreach($surahs as $surah) {
             echo "<li><a href='?view=hifz&surah={$surah['id']}'>Surah {$surah['id']}: {$surah['name_english']}</a></li>";
        }
        echo "</ul>";

        if (isset($_GET['surah'])) {
             $selected_surah_id = (int)$_GET['surah'];
             $selected_surah = null;
             foreach($surahs as $s) {
                 if ($s['id'] == $selected_surah_id) {
                     $selected_surah = $s;
                     break;
                 }
             }

             if ($selected_surah) {
                 echo "<h3>Hifz Status for Surah {$selected_surah_id}: {$selected_surah['name_english']}</h3>";
                 $ayahs = get_ayahs_by_surah($selected_surah_id);
                 echo "<table>";
                 echo "<thead><tr><th>Ayah</th><th>Status</th><th>Action</th></tr></thead>";
                 echo "<tbody>";
                 foreach($ayahs as $ayah) {
                     $hifz_status = get_hifz_status($user_id, $ayah['surah_id'], $ayah['id']);
                     echo "<tr>";
                     echo "<td><a href='?view=quran&surah={$ayah['surah_id']}#ayah-{$ayah['id']}'>{$ayah['surah_id']}:{$ayah['ayah_number']}</a></td>";
                     echo "<td>{$hifz_status}</td>";
                     echo "<td>";
                     echo "<form method='POST' style='display:inline-block;'>";
                     echo "<input type='hidden' name='action' value='update_hifz'>";
                     echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                     echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                     echo "<select name='hifz_status' onchange='this.form.submit()'>";
                     $statuses = ['Not Started', 'Memorizing', 'Memorized'];
                     foreach($statuses as $status) {
                         $selected = ($hifz_status === $status) ? 'selected' : '';
                         echo "<option value='{$status}' {$selected}>{$status}</option>";
                     }
                     echo "</select>";
                     echo "</form>";
                     echo "</td>";
                     echo "</tr>";
                 }
                 echo "</tbody>";
                 echo "</table>";
             } else {
                 echo "<p>Invalid Surah selected.</p>";
             }
        }


        break;

    case 'recitation':
         if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to use the Recitation Log.</div>";
            break;
        }
        $user_id = get_user_id();
        $surahs = get_surahs();
        $logs = get_recitation_log($user_id);

        echo "<h2>Recitation Log</h2>";

        echo "<h3>Add New Entry</h3>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='add_recitation_log'>";
        echo "<div class='form-group'>";
        echo "<label for='surah_id'>Surah:</label>";
        echo "<select id='surah_id' name='surah_id' required>";
        foreach ($surahs as $surah) {
            echo "<option value='{$surah['id']}'>{$surah['id']}. {$surah['name_english']}</option>";
        }
        echo "</select>";
        echo "</div>";
         echo "<div class='form-group'>";
         echo "<label for='ayah_from'>From Ayah:</label>";
         echo "<input type='number' id='ayah_from' name='ayah_from' min='1' required>";
         echo "</div>";
         echo "<div class='form-group'>";
         echo "<label for='ayah_to'>To Ayah:</label>";
         echo "<input type='number' id='ayah_to' name='ayah_to' min='1' required>";
         echo "</div>";
        echo "<div class='form-group'>";
        echo "<label for='notes'>Notes:</label>";
        echo "<textarea id='notes' name='notes' rows='3'></textarea>";
        echo "</div>";
        echo "<button type='submit' class='btn'>Add Log</button>";
        echo "</form>";

        echo "<h3>My Recitation History</h3>";
        if (empty($logs)) {
            echo "<p>No recitation logs added yet.</p>";
        } else {
            echo "<table>";
            echo "<thead><tr><th>Date</th><th>Surah</th><th>Ayahs</th><th>Notes</th><th>Action</th></tr></thead>";
            echo "<tbody>";
            foreach ($logs as $log) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($log['recitation_date']) . "</td>";
                echo "<td>{$log['surah_id']}. " . htmlspecialchars($log['name_english']) . "</td>";
                echo "<td>{$log['ayah_from']} - {$log['ayah_to']}</td>";
                echo "<td>" . nl2br(htmlspecialchars($log['notes'])) . "</td>";
                echo "<td>";
                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this log entry?\");'>";
                echo "<input type='hidden' name='action' value='delete_recitation_log'>";
                echo "<input type='hidden' name='log_id' value='{$log['id']}'>";
                echo "<button type='submit' class='btn' style='background-color: #dc3545;'>Delete</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        }
        break;

    case 'search':
        echo "<h2>Advanced Search</h2>";
        echo "<form method='GET'>";
        echo "<input type='hidden' name='view' value='search'>";
        echo "<div class='form-group'>";
        echo "<label for='query'>Search Query:</label>";
        echo "<input type='text' id='query' name='query' value='" . htmlspecialchars($_GET['query'] ?? '') . "' required>";
        echo "</div>";
        echo "<div class='form-group'>";
        echo "<label for='language'>Search In:</label>";
        echo "<select id='language' name='language'>";
        $languages = ['Arabic', 'Urdu', 'English', 'Bengali']; // Available languages
        $selected_lang = $_GET['language'] ?? 'Arabic';
        foreach($languages as $lang) {
            $selected = ($selected_lang === $lang) ? 'selected' : '';
            echo "<option value='{$lang}' {$selected}>{$lang}</option>";
        }
        echo "</select>";
        echo "</div>";
        echo "<button type='submit' class='btn'>Search</button>";
        echo "</form>";

        if (isset($_GET['query']) && $_GET['query'] !== '') {
            $query = $_GET['query'];
            $language = $_GET['language'] ?? 'Arabic';
            $search_results = search_quran($query, $language);

            echo "<h3>Search Results for '" . htmlspecialchars($query) . "' in " . htmlspecialchars($language) . "</h3>";

            if (empty($search_results)) {
                echo "<p>No results found.</p>";
            } else {
                foreach ($search_results as $result) {
                    echo "<div class='quran-ayah'>";
                    echo "<div class='ayah-meta'><a href='?view=quran&surah={$result['surah_id']}#ayah-{$result['ayah_id']}'>{$result['surah_id']}:{$result['ayah_number']} - {$result['name_english']}</a></div>";
                    echo "<div class='arabic-text'>" . htmlspecialchars($result['arabic_text']) . "</div>";
                    if ($result['translation_text']) {
                         echo "<div class='translation-text'><strong>{$language}:</strong> " . htmlspecialchars($result['translation_text']) . "</div>";
                    }
                    echo "</div>";
                }
            }
        }
        break;

    case 'games':
         echo "<h2>Quran Games</h2>";
         echo "<p>Test your knowledge with these games.</p>";

         // Word Whiz (Basic - display a random word and ask for meaning)
         echo "<h3>Word Whiz</h3>";
         // Fetch a random word with translation (requires more complex query/data structure)
         // For simplicity, let's just mention the game concept.
         echo "<p>Match Arabic words with their meanings. (Coming Soon)</p>";

         // Ayah Jumble (Basic - jumble words of an ayah)
         echo "<h3>Ayah Jumble</h3>";
          // Select a random ayah for the game
         $db = new SQLite3(DB_PATH);
         $result = $db->query("SELECT a.id, a.arabic_text, a.surah_id, a.ayah_number, s.name_english FROM ayahs a JOIN surahs s ON a.surah_id = s.id ORDER BY RANDOM() LIMIT 1");
         $random_ayah = $result->fetchArray(SQLITE3_ASSOC);
         $db->close();

         if ($random_ayah) {
             echo "<p>Unscramble the words to form the correct ayah:</p>";
             echo "<p>Surah {$random_ayah['surah_id']}: Ayah {$random_ayah['ayah_number']} ({$random_ayah['name_english']})</p>";

             $words = explode(' ', trim($random_ayah['arabic_text']));
             $correct_order = json_encode($words); // Store correct order as JSON
             shuffle($words); // Jumble the words

             echo "<div class='game-area'>";
             echo "<div id='ayah-jumble-container' data-correct-order='" . htmlspecialchars($correct_order, ENT_QUOTES, 'UTF-8') . "'>";
             foreach($words as $word) {
                 echo "<span class='jumbled-word'>" . htmlspecialchars($word) . "</span>";
             }
             echo "</div>"; // jumble-container
             echo "<p>Click words above to place them below:</p>";
             echo "<div id='ayah-jumble-output'></div>";
             echo "<button id='ayah-jumble-check' class='btn'>Check</button>";
             echo "<button id='ayah-jumble-reset' class='btn'>Reset</button>";
             echo "<div id='ayah-jumble-message'></div>";
             echo "</div>"; // game-area
         } else {
             echo "<p>Could not load an ayah for the game.</p>";
         }


        break;

    case 'profile':
         if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to view your profile.</div>";
            break;
        }
        $user_id = get_user_id();
        $username = $_SESSION['username'];
        $role = $_SESSION['role'];

        echo "<h2>User Profile</h2>";
        echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($role) . "</p>";

        echo "<h3>Data Management</h3>";
        echo "<p>Export your personal data (Tafsir, Themes, Hifz, Recitation Log).</p>";
        echo "<a href='?action=export_data' class='btn'>Export My Data</a>";

        echo "<h4>Import Data</h4>";
        echo "<p>Import previously exported personal data.</p>";
        echo "<form method='POST' enctype='multipart/form-data'>";
        echo "<input type='hidden' name='action' value='import_data'>";
        echo "<div class='form-group'>";
        echo "<label for='import_file'>Select JSON file:</label>";
        echo "<input type='file' id='import_file' name='import_file' accept='.json' required>";
        echo "</div>";
        echo "<button type='submit' class='btn'>Import Data</button>";
        echo "</form>";

        break;

    case 'contributions':
         if (!has_permission('Ulama')) {
             echo "<div class='error'>You do not have permission to view contributions.</div>";
             break;
         }
         $pending_contributions = get_pending_contributions();

         echo "<h2>Pending Contributions</h2>";
         if (empty($pending_contributions)) {
             echo "<p>No pending contributions at this time.</p>";
         } else {
             foreach($pending_contributions as $contribution) {
                 echo "<div class='contribution-item'>";
                 echo "<h4>{$contribution['type']} Contribution by " . htmlspecialchars($contribution['username']) . "</h4>";
                 echo "<p>Submitted on: {$contribution['created_at']}</p>";
                 echo "<p>Related ID: {$contribution['related_id']}</p>"; // Could link to the related item
                 echo "<p>Content:</p>";
                 echo "<pre>" . htmlspecialchars($contribution['content']) . "</pre>";

                 echo "<form method='POST' style='display:inline-block;'>";
                 echo "<input type='hidden' name='action' value='approve_contribution'>";
                 echo "<input type='hidden' name='contribution_id' value='{$contribution['id']}'>";
                 echo "<button type='submit' class='btn' style='background-color: #28a745;'>Approve</button>";
                 echo "</form>";

                 echo "<form method='POST' style='display:inline-block; margin-left: 10px;'>";
                 echo "<input type='hidden' name='action' value='reject_contribution'>";
                 echo "<input type='hidden' name='contribution_id' value='{$contribution['id']}'>";
                 echo "<button type='submit' class='btn' style='background-color: #dc3545;'>Reject</button>";
                 echo "</form>";
                 echo "</div>";
             }
         }
         break;

    case 'admin':
        if (!has_permission('Admin')) {
            echo "<div class='error'>You do not have permission to access the Admin Panel.</div>";
            break;
        }

        echo "<h2>Admin Panel</h2>";

        echo "<h3>User Management</h3>";
        $users = get_all_users();
        if (empty($users)) {
            echo "<p>No users found.</p>";
        } else {
            echo "<table>";
            echo "<thead><tr><th>Username</th><th>Role</th><th>Action</th></tr></thead>";
            echo "<tbody>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>";
                if ($user['role'] !== 'Admin' || $user['id'] !== get_user_id()) { // Cannot change own admin role
                    echo "<form method='POST' style='display:inline-block;'>";
                    echo "<input type='hidden' name='action' value='update_user_role'>";
                    echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                    echo "<select name='role' onchange='this.form.submit()'>";
                    $roles = ['Public', 'User', 'Ulama', 'Admin'];
                    foreach ($roles as $role) {
                        $selected = ($user['role'] === $role) ? 'selected' : '';
                        echo "<option value='{$role}' {$selected}>{$role}</option>";
                    }
                    echo "</select>";
                    echo "</form>";
                } else {
                    echo "Admin";
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        }

        echo "<h3>Data Loading</h3>";
         if (!file_exists(DB_PATH) || filesize(DB_PATH) < 1000) { // Simple check if database is likely empty
             echo "<p>Database appears empty. Load initial data from .AM files.</p>";
             echo "<form method='POST'>";
             echo "<input type='hidden' name='action' value='load_initial_data'>";
             echo "<button type='submit' class='btn'>Load Initial Data</button>";
             echo "</form>";
         } else {
             echo "<p>Initial data appears to be loaded.</p>";
              // Option to re-load or load new language files (more complex, requires file upload handling)
              echo "<p>Loading new language data requires file upload functionality (not fully implemented in this single file example).</p>";
         }

         echo "<h3>Manage Contributions</h3>";
         echo "<p><a href='?view=contributions'>Review Pending Contributions</a></p>";
         // Admin could also manage approved/rejected contributions here
         break;

    case 'login':
        if (is_logged_in()) {
            echo "<p>You are already logged in as " . htmlspecialchars($_SESSION['username']) . ". <a href='?action=logout'>Logout</a></p>";
        } else {
            echo "<h2>Login</h2>";
            echo "<form method='POST'>";
            echo "<input type='hidden' name='action' value='login'>";
            echo "<div class='form-group'>";
            echo "<label for='username'>Username:</label>";
            echo "<input type='text' id='username' name='username' required>";
            echo "</div>";
            echo "<div class='form-group'>";
            echo "<label for='password'>Password:</label>";
            echo "<input type='password' id='password' name='password' required>";
            echo "</div>";
            echo "<button type='submit' class='btn'>Login</button>";
            echo "</form>";
            echo "<div class='login-register'><p>Don't have an account? <a href='?view=register'>Register here</a></p></div>";
        }
        break;

    case 'register':
        if (is_logged_in()) {
             echo "<p>You are already logged in as " . htmlspecialchars($_SESSION['username']) . ".</p>";
        } else {
            echo "<h2>Register</h2>";
            echo "<form method='POST'>";
            echo "<input type='hidden' name='action' value='register'>";
            echo "<div class='form-group'>";
            echo "<label for='username'>Username:</label>";
            echo "<input type='text' id='username' name='username' required>";
            echo "</div>";
            echo "<div class='form-group'>";
            echo "<label for='password'>Password:</label>";
            echo "<input type='password' id='password' name='password' required>";
            echo "</div>";
            echo "<button type='submit' class='btn'>Register</button>";
            echo "</form>";
            echo "<div class='login-register'><p>Already have an account? <a href='?view=login'>Login here</a></p></div>";
        }
        break;

    default:
        echo "<p>Welcome to " . htmlspecialchars(APP_NAME) . ".</p>";
        echo "<p>Select a feature from the navigation menu.</p>";
        break;
}

render_footer();

//load_initial_data(); //load first time data
?>