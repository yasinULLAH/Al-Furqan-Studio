<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('root', 'http://localhost/Al-Furqan-Studio-new/index.php');
define('DB_NAME', 'nur_ul_quran_studio_db');
define('DB_PASS', 'root');
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
function db_query($sql, $params = [], $types = '')
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DB Prepare Error: " . $conn->error . " | SQL: " . $sql);
        return false;
    }
    if (!empty($params)) {
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i];
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    return $stmt;
}
function db_fetch_row($stmt)
{
    $result = $stmt->get_result();
    if ($result === false) {
        return null;
    }
    return $result->fetch_assoc();
}
function db_fetch_all($stmt)
{
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}
/* function setup_database()
{
    global $conn;
    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role ENUM('public', 'registered', 'admin') DEFAULT 'public',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS quran_ayahs (
            surah INT NOT NULL,
            ayah INT NOT NULL,
            arabic TEXT NOT NULL,
            urdu TEXT,
            english TEXT,
            Bangali TEXT,
            pashto TEXT,
            PRIMARY KEY (surah, ayah)
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS tafsir (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            surah INT NOT NULL,
            ayah INT NOT NULL,
            notes TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, surah, ayah)
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS themes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            parent_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS theme_ayahs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            theme_id INT NOT NULL,
            surah INT NOT NULL,
            ayah INT NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE,
            UNIQUE (user_id, theme_id, surah, ayah)
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS root_words (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            root VARCHAR(50) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE (user_id, root)
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS recitations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            surah INT NOT NULL,
            ayah_start INT,
            ayah_end INT,
            qari VARCHAR(255),
            log_date DATE NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS hifz (
            user_id INT NOT NULL,
            surah INT NOT NULL,
            ayah INT NOT NULL,
            status ENUM('not-started', 'in-progress', 'memorized') DEFAULT 'not-started',
            last_review_date DATE,
            next_review_date DATE,
            review_count INT DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, surah, ayah),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS settings (
            user_id INT NOT NULL,
            setting_name VARCHAR(100) NOT NULL,
            setting_value TEXT NOT NULL,
            PRIMARY KEY (user_id, setting_name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS word_translations (
            word_id INT PRIMARY KEY,
            ur_meaning TEXT,
            en_meaning TEXT,
            pashto_text TEXT,
            bn_meaning TEXT,
            approved_by INT,
            approved_at TIMESTAMP NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS word_metadata (
            word_id INT PRIMARY KEY,
            surah INT NOT NULL,
            ayah INT NOT NULL,
            word_position INT NOT NULL,
            arabic_word TEXT NOT NULL,
            UNIQUE KEY (surah, ayah, word_position)
        )
    ");
    $conn->query("
        CREATE TABLE IF NOT EXISTS goals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            type VARCHAR(100) NOT NULL,
            target_date DATE,
            creation_date DATE NOT NULL,
            is_complete BOOLEAN DEFAULT FALSE,
            target_surah INT,
            target_juz INT,
            target_theme INT,
            target_count INT,
            target_day INT, -- For recurring weekly goals (0=Sunday, 6=Saturday)
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");

    $stmt_check_admin = db_query("SELECT id FROM users WHERE username = ?", ['admin'], 's');

    $admin_exists = db_fetch_row($stmt_check_admin);
    if ($stmt_check_admin) {
        $stmt_check_admin->close();
    }


    if (!$admin_exists) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt_insert_admin = db_query(
            "INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'admin')",
            ['admin', $password, 'admin@example.com'],
            'sss'
        );
        if ($stmt_insert_admin) {
            $stmt_insert_admin->close();
        }
    }
}
$manifest_config = [
    (object)['key' => 'urdu', 'label' => 'Urdu Translation', 'file_type' => 'quran_translation', 'url' => 'data new.AM', 'version' => '1.0', 'lang_code' => 'ur', 'direction' => 'rtl', 'font_var' => 'var(--font-urdu)'],
    (object)['key' => 'english', 'label' => 'English Translation', 'file_type' => 'quran_translation', 'url' => 'dataENG.AM', 'version' => '1.0', 'lang_code' => 'en', 'direction' => 'ltr', 'font_var' => '--font-english'],
    (object)['key' => 'Bangali', 'label' => 'Bangali Translation', 'file_type' => 'quran_translation', 'url' => 'dataBNG.AM', 'version' => '1.0', 'lang_code' => 'bn', 'direction' => 'ltr', 'font_var' => '--font-Bangali'],
    (object)['key' => 'pashto', 'label' => 'Pashto Translation', 'file_type' => 'quran_translation', 'url' => 'dataPS.AM', 'version' => '1.0', 'lang_code' => 'ps', 'direction' => 'rtl', 'font_var' => '--font-pashto'],
    (object)['key' => 'word_translation', 'label' => 'Word-by-Word Translations', 'file_type' => 'word_translation', 'url' => 'data5 new.AM', 'version' => '1.0', 'headers' => ['word_id', 'ur_meaning', 'en_meaning', 'pashto_text', 'bn_meaning'], 'auto_approve_user_id' => 1],
    (object)['key' => 'word_metadata', 'label' => 'Word Metadata', 'file_type' => 'word_metadata', 'url' => 'word2.AM', 'version' => '1.0', 'headers' => ['word_id', 'surah', 'ayah', 'word_position', 'arabic_word']]
];
setup_database();
function import_data_from_file($file_config)
{
    global $conn;
    $file_path = $file_config->url;
    if (!file_exists($file_path)) {
        error_log("Data file not found: " . $file_path);
        return false;
    }

    $handle = fopen($file_path, "r");
    if ($handle === false) {
        error_log("Failed to open file for reading: " . $file_path);
        return false;
    }

    $table_name = '';
    $headers = [];
    $is_quran_translation = false;

    if ($file_config->file_type === 'quran_translation') {
        $table_name = 'quran_ayahs';
        $is_quran_translation = true;
    } elseif ($file_config->file_type === 'word_translation') {
        $table_name = 'word_translations';
        $headers = $file_config->headers;
        $conn->query("TRUNCATE TABLE $table_name");
    } elseif ($file_config->file_type === 'word_metadata') {
        $table_name = 'word_metadata';
        $headers = $file_config->headers;
        $conn->query("TRUNCATE TABLE $table_name");
    } else {
        error_log("Unknown file type for import: " . $file_config->file_type);
        fclose($handle);
        return false;
    }

    $stmt_insert_arabic = null;
    $stmt_insert = null;
    $stmt_update_trans = null;

    if ($is_quran_translation) {
        $stmt_insert_arabic = $conn->prepare("INSERT IGNORE INTO quran_ayahs (surah, ayah, arabic) VALUES (?, ?, ?)");
        $stmt_update_trans = $conn->prepare("UPDATE quran_ayahs SET {$file_config->key} = ? WHERE surah = ? AND ayah = ?");
        if (!$stmt_insert_arabic || !$stmt_update_trans) {
            error_log("Failed to prepare Quran translation statements: " . $conn->error);
            fclose($handle);
            return false;
        }
    } else {
        $placeholders = implode(', ', array_fill(0, count($headers), '?'));
        $header_cols = implode(', ', $headers);
        $sql = "";
        if ($table_name === 'word_translations') {
            $headers_with_approval = $headers;
            $placeholders_with_approval = $placeholders;
            if (isset($file_config->auto_approve_user_id)) {
                $headers_with_approval[] = 'approved_by';
                $placeholders_with_approval .= ', ?';
                $headers_with_approval[] = 'approved_at';
                $placeholders_with_approval .= ', NOW()';
            }
            $sql = "INSERT INTO $table_name (" . implode(', ', $headers_with_approval) . ") VALUES ($placeholders_with_approval)";
        } else {
            $sql = "INSERT INTO $table_name ($header_cols) VALUES ($placeholders)";
        }
        $stmt_insert = $conn->prepare($sql);
        if (!$stmt_insert) {
            error_log("Failed to prepare statement for $table_name: " . $conn->error . " | SQL: " . $sql);
            fclose($handle);
            return false;
        }
    }

    $conn->begin_transaction();
    $row_count = 0;

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if (empty($line)) continue;

        if ($is_quran_translation) {
            $parts = explode(' ترجمہ: ', $line, 2);
            if (count($parts) < 2) continue;
            $arabic_full = $parts[0];
            $trans_meta = $parts[1];
            if (preg_match('/<br\/>\s*(?:s|س)\s*\.?\s*(\d{1,3})\s*(?:a|آ)\s*\.?\s*(\d{1,3})\s*$/i', $trans_meta, $matches)) {
                $surah = (int)$matches[1];
                $ayah = (int)$matches[2];
                $translation_text = substr($trans_meta, 0, strrpos($trans_meta, $matches[0])) ?? $trans_meta;
                $stmt_insert_arabic->bind_param("iis", $surah, $ayah, $arabic_full);
                $stmt_insert_arabic->execute();
                $stmt_update_trans->bind_param("sii", $translation_text, $surah, $ayah);
                $stmt_update_trans->execute();
                $row_count++;
            }
        } else {
            $values = str_getcsv($line);
            if ($file_config->file_type === 'word_translation' && isset($values[4])) {

                $remapped_values = [
                    $values[0],
                    $values[2],
                    $values[3],
                    $values[4],
                    ''
                ];
                $values = $remapped_values;
            }
            if (count($values) !== count($headers)) {
                error_log("Skipping malformed line in {$file_path}: " . $line);
                continue;
            }
            $bind_params = [];
            $bind_types = '';
            for ($i = 0; $i < count($headers); $i++) {
                $header = $headers[$i];
                $value = trim($values[$i]);
                if (in_array($header, ['word_id', 'surah', 'ayah', 'word_position'])) {
                    $bind_params[] = (int)$value;
                    $bind_types .= 'i';
                } else {
                    $bind_params[] = $value;
                    $bind_types .= 's';
                }
            }
            if ($table_name === 'word_translations' && isset($file_config->auto_approve_user_id)) {
                $bind_params[] = $file_config->auto_approve_user_id;
                $bind_types .= 'i';
            }


            $bind_names = [$bind_types];
            for ($i = 0; $i < count($bind_params); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $bind_params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array([$stmt_insert, 'bind_param'], $bind_names);

            $stmt_insert->execute();
            $row_count++;
        }
    }

    fclose($handle);

    if ($stmt_insert_arabic) $stmt_insert_arabic->close();
    if ($stmt_update_trans) $stmt_update_trans->close();
    if ($stmt_insert) $stmt_insert->close();
    $conn->commit();
    return true;
}
function initial_populate_from_files()
{
    global $conn, $manifest_config;
    $stmt_quran_count = $conn->query("SELECT COUNT(*) FROM quran_ayahs");
    $quran_empty = $stmt_quran_count->fetch_row()[0] == 0;
    $stmt_word_trans_count = $conn->query("SELECT COUNT(*) FROM word_translations");
    $word_trans_empty = $stmt_word_trans_count->fetch_row()[0] == 0;
    $stmt_word_meta_count = $conn->query("SELECT COUNT(*) FROM word_metadata");
    $word_meta_empty = $stmt_word_meta_count->fetch_row()[0] == 0;
    foreach ($manifest_config as $config) {
        $should_import = false;
        if ($config->file_type === 'quran_translation' && $quran_empty) {
            $should_import = true;
        } elseif ($config->file_type === 'word_translation' && $word_trans_empty) {
            $should_import = true;
        } elseif ($config->file_type === 'word_metadata' && $word_meta_empty) {
            $should_import = true;
        }
        if ($should_import) {
            error_log("Initiating import for: " . $config->label . " from " . $config->url);
            if (import_data_from_file($config)) {
                error_log("Successfully imported: " . $config->label);
            } else {
                error_log("Failed to import: " . $config->label);
            }
        }
    }
}
initial_populate_from_files(); */

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}
function get_user_role()
{
    return $_SESSION['role'] ?? 'public';
}
function get_user_id()
{
    return $_SESSION['user_id'] ?? 0;
}
function can_edit_translations()
{
    $role = get_user_role();
    return $role === 'admin' || $role === 'registered';
}
function can_approve_translations()
{
    return get_user_role() === 'admin';
}
function handle_login()
{
    global $conn;
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $stmt = db_query("SELECT id, username, password, role FROM users WHERE username = ?", [$username], 's');
        if ($stmt && $user = db_fetch_row($stmt)) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $message = 'Invalid password.';
            }
        } else {
            $message = 'User not found.';
        }
    }
    return $message;
}
function handle_register()
{
    global $conn;
    $message = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        if ($password !== $confirm_password) {
            $message = 'Passwords do not match.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = db_query("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'registered')", [$username, $hashed_password, $email], 'sss');
            if ($stmt) {
                $message = 'Registration successful! You can now log in.';
            } else {
                if ($conn->errno === 1062) {
                    $message = 'Username or Email already exists.';
                } else {
                    $message = 'Registration failed. Please try again.';
                }
            }
        }
    }
    return $message;
}
function handle_logout()
{
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_unset();
        session_destroy();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}
$login_message = handle_login();
$register_message = handle_register();
handle_logout();
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $user_id = get_user_id();
    $user_role = get_user_role();
    $all_langs_result = db_query("SELECT lang_key, word_col_name FROM languages");
    $valid_quran_lang_keys = [];
    $valid_word_col_names = [];
    if ($all_langs_result) {
        foreach (db_fetch_all($all_langs_result) as $lang) {
            $valid_quran_lang_keys[] = $lang['lang_key'];
            $valid_word_col_names[] = $lang['word_col_name'];
        }
    }
    switch ($action) {
        case 'toggle_public_status':
            if (get_user_role() !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
                break;
            }
            $content_type = $_POST['content_type'] ?? '';
            $content_id = $_POST['content_id'] ?? 0;
            $is_public = isset($_POST['is_public']) ? (int)$_POST['is_public'] : 0;

            $valid_tables = ['tafsir' => 'id', 'themes' => 'id', 'root_words' => 'id'];
            if (array_key_exists($content_type, $valid_tables) && $content_id > 0) {
                $id_column = $valid_tables[$content_type];
                $stmt = db_query("UPDATE `$content_type` SET is_public = ? WHERE $id_column = ?", [$is_public, $content_id], 'ii');
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'Public status updated.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database update failed.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            }
            break;
        case 'get_public_content':
            $public_data = [
                'tafsir' => [],
                'themes' => [],
                'root_words' => []
            ];

            // Fetch Public Tafsir
            $stmt_tafsir = db_query("SELECT t.*, u.full_name, u.username FROM tafsir t JOIN users u ON t.user_id = u.id WHERE t.is_public = 1 ORDER BY t.created_at DESC LIMIT 50");
            if ($stmt_tafsir) $public_data['tafsir'] = db_fetch_all($stmt_tafsir);

            // Fetch Public Themes
            $stmt_themes = db_query("SELECT th.*, u.full_name, u.username FROM themes th JOIN users u ON th.user_id = u.id WHERE th.is_public = 1 ORDER BY th.created_at DESC LIMIT 50");
            if ($stmt_themes) $public_data['themes'] = db_fetch_all($stmt_themes);

            // Fetch Public Root Words
            $stmt_roots = db_query("SELECT r.*, u.full_name, u.username FROM root_words r JOIN users u ON r.user_id = u.id WHERE r.is_public = 1 ORDER BY r.created_at DESC LIMIT 50");
            if ($stmt_roots) $public_data['root_words'] = db_fetch_all($stmt_roots);

            echo json_encode(['success' => true, 'data' => $public_data]);
            break;
        case 'load_quran_ayah':
            $surah = $_POST['surah'] ?? 0;
            $ayah = $_POST['ayah'] ?? 0;
            if ($surah && $ayah) {
                $stmt = db_query("SELECT * FROM quran_ayahs WHERE surah = ? AND ayah = ?", [$surah, $ayah], 'ii');
                if ($stmt && $data = db_fetch_row($stmt)) {
                    echo json_encode(['success' => true, 'data' => $data]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ayah not found.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            }
            break;
        case 'get_all_word_metadata_for_surah':
            $surah = $_POST['surah'] ?? 0;
            if ($surah > 0 && $surah <= 114) {
                $stmt = db_query("SELECT word_id, ayah, word_position, arabic_word FROM word_metadata WHERE surah = ? ORDER BY ayah, word_position", [$surah], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch word metadata.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid Surah for metadata.']);
            }
            break;
        case 'get_all_quran_ayahs_for_surah':
            $surah = $_POST['surah'] ?? 0;
            if ($surah > 0 && $surah <= 114) {
                $stmt = db_query("SELECT * FROM quran_ayahs WHERE surah = ? ORDER BY ayah ASC", [$surah], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch Ayahs.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid Surah provided.']);
            }
            break;
        case 'save_tafsir':
            if (is_logged_in()) {
                $surah = $_POST['surah'] ?? 0;
                $ayah = $_POST['ayah'] ?? 0;
                $notes = $_POST['notes'] ?? '';
                if ($surah && $ayah && $notes) {
                    $stmt = db_query("INSERT INTO tafsir (user_id, surah, ayah, notes) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE notes = ?", [$user_id, $surah, $ayah, $notes, $notes], 'iiiss');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Tafsir saved.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save Tafsir.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_tafsir':
            $surah = $_POST['surah'] ?? 0;
            $ayah = $_POST['ayah'] ?? 0;
            if ($surah && $ayah) {
                $stmt = db_query("SELECT notes FROM tafsir WHERE user_id = ? AND surah = ? AND ayah = ?", [$user_id, $surah, $ayah], 'iii');
                if ($stmt && $data = db_fetch_row($stmt)) {
                    echo json_encode(['success' => true, 'notes' => $data['notes']]);
                } else {
                    echo json_encode(['success' => true, 'notes' => '']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            }
            break;
        case 'get_all_tafsir':
            if (is_logged_in()) {
                $stmt = db_query("SELECT surah, ayah, notes FROM tafsir WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch tafsir.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'add_theme':
            if (is_logged_in()) {
                $name = $_POST['name'] ?? '';
                $parentId = $_POST['parentId'] ?? null;
                $description = $_POST['description'] ?? '';
                $params = [$user_id, $name, $description];
                $types = 'iss';
                if ($parentId) {
                    $sql = "INSERT INTO themes (user_id, name, description, parent_id) VALUES (?, ?, ?, ?)";
                    $params[] = $parentId;
                    $types .= 'i';
                } else {
                    $sql = "INSERT INTO themes (user_id, name, description) VALUES (?, ?, ?)";
                }
                $stmt = db_query($sql, $params, $types);
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'Theme added.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add theme.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_all_themes':
            if (is_logged_in()) {
                $stmt = db_query("SELECT id, name, description, parent_id FROM themes WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch themes.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'delete_theme':
            if (is_logged_in()) {
                $theme_id = $_POST['theme_id'] ?? 0;
                if ($theme_id) {
                    db_query("DELETE FROM theme_ayahs WHERE user_id = ? AND theme_id = ?", [$user_id, $theme_id], 'ii');
                    $stmt = db_query("DELETE FROM themes WHERE user_id = ? AND id = ?", [$user_id, $theme_id], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Theme deleted.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete theme.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'link_ayah_to_theme':
            if (is_logged_in()) {
                $theme_id = $_POST['theme_id'] ?? 0;
                $surah = $_POST['surah'] ?? 0;
                $ayah = $_POST['ayah'] ?? 0;
                $notes = $_POST['notes'] ?? '';
                if ($theme_id && $surah && $ayah) {
                    $stmt = db_query("INSERT INTO theme_ayahs (user_id, theme_id, surah, ayah, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE notes = ?", [$user_id, $theme_id, $surah, $ayah, $notes, $notes], 'iiiss');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Ayah linked to theme.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to link Ayah.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_linked_ayahs_for_theme':
            if (is_logged_in()) {
                $theme_id = $_POST['theme_id'] ?? 0;
                if ($theme_id) {
                    $stmt = db_query("SELECT surah, ayah, notes FROM theme_ayahs WHERE user_id = ? AND theme_id = ?", [$user_id, $theme_id], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to fetch linked Ayahs.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'unlink_ayah_from_theme':
            if (is_logged_in()) {
                $link_id = $_POST['link_id'] ?? 0;
                if ($link_id) {
                    $stmt = db_query("DELETE FROM theme_ayahs WHERE user_id = ? AND id = ?", [$user_id, $link_id], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Ayah unlinked.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to unlink Ayah.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'analyze_root':
            if (is_logged_in()) {
                $root_term = $_POST['root_term'] ?? '';
                if ($root_term) {
                    $stmt = db_query("SELECT description FROM root_words WHERE user_id = ? AND root = ?", [$user_id, $root_term], 'is');
                    if ($stmt && $data = db_fetch_row($stmt)) {
                        echo json_encode(['success' => true, 'description' => $data['description']]);
                    } else {
                        echo json_encode(['success' => true, 'description' => '']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid root term.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'save_root_notes':
            if (is_logged_in()) {
                $root = $_POST['root'] ?? '';
                $description = $_POST['description'] ?? '';
                if ($root) {
                    $stmt = db_query("INSERT INTO root_words (user_id, root, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description = ?", [$user_id, $root, $description, $description], 'isss');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Root notes saved.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save root notes.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid root.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_all_roots':
            if (is_logged_in()) {
                $stmt = db_query("SELECT root, description FROM root_words WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch root words.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'save_recitation_log':
            if (is_logged_in()) {
                $surah = $_POST['surah'] ?? 0;
                $ayah_start = $_POST['ayah_start'] ?? null;
                $ayah_end = $_POST['ayah_end'] ?? null;
                $qari = $_POST['qari'] ?? '';
                $log_date = $_POST['log_date'] ?? '';
                $notes = $_POST['notes'] ?? '';
                if ($surah && $log_date) {
                    $stmt = db_query("INSERT INTO recitations (user_id, surah, ayah_start, ayah_end, qari, log_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)", [$user_id, $surah, $ayah_start, $ayah_end, $qari, $log_date, $notes], 'iiisss');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Recitation log saved.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save log.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_all_recitations':
            if (is_logged_in()) {
                $stmt = db_query("SELECT id, surah, ayah_start, ayah_end, qari, log_date, notes FROM recitations WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch recitations.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'delete_recitation_log':
            if (is_logged_in()) {
                $log_id = $_POST['log_id'] ?? 0;
                if ($log_id) {
                    $stmt = db_query("DELETE FROM recitations WHERE user_id = ? AND id = ?", [$user_id, $log_id], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Log entry deleted.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete log.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'update_hifz_status':
            if (is_logged_in()) {
                $surah = $_POST['surah'] ?? 0;
                $ayah = $_POST['ayah'] ?? 0;
                $status = $_POST['status'] ?? 'not-started';
                $last_review_date = $_POST['last_review_date'] ?? null;
                $next_review_date = $_POST['next_review_date'] ?? null;
                $review_count = $_POST['review_count'] ?? 0;
                $notes = $_POST['notes'] ?? '';
                if ($surah && $ayah && $status) {
                    $sql = "INSERT INTO hifz (user_id, surah, ayah, status, last_review_date, next_review_date, review_count, notes)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE status = ?, last_review_date = ?, next_review_date = ?, review_count = ?, notes = ?";
                    $params = [
                        $user_id,
                        $surah,
                        $ayah,
                        $status,
                        $last_review_date,
                        $next_review_date,
                        $review_count,
                        $notes,
                        $status,
                        $last_review_date,
                        $next_review_date,
                        $review_count,
                        $notes
                    ];
                    $types = 'iiississsis';
                    $stmt = db_query($sql, $params, $types);
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Hifz status updated.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update hifz status.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_hifz_for_surah':
            if (is_logged_in()) {
                $surah = $_POST['surah'] ?? 0;
                if ($surah) {
                    $stmt = db_query("SELECT ayah, status, last_review_date, next_review_date, review_count, notes FROM hifz WHERE user_id = ? AND surah = ?", [$user_id, $surah], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to fetch hifz data.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_all_hifz':
            if (is_logged_in()) {
                $stmt = db_query("SELECT surah, ayah, status, last_review_date, next_review_date, review_count, notes FROM hifz WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch hifz data.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'search_data':
            if (is_logged_in()) {
                $search_term = '%' . ($_POST['search_term'] ?? '') . '%';
                $scopes = $_POST['scopes'] ?? [];
                $results = [];
                if (in_array('quran-arabic', $scopes)) {
                    $stmt = db_query("SELECT surah, ayah, arabic FROM quran_ayahs WHERE arabic LIKE ?", [$search_term], 's');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $results[] = ['type' => 'Quran', 'ref' => "Surah {$row['surah']}:{$row['ayah']}", 'surah' => $row['surah'], 'ayah' => $row['ayah'], 'context' => $row['arabic'], 'source' => 'Arabic'];
                        }
                    }
                }
                if (in_array('quran-translation', $scopes)) {
                    $translations = ['urdu', 'english', 'Bangali', 'pashto'];
                    foreach ($translations as $lang) {
                        $stmt = db_query("SELECT surah, ayah, $lang FROM quran_ayahs WHERE $lang LIKE ?", [$search_term], 's');
                        if (in_array('quran-translation', $scopes)) {
                            // This now dynamically fetches all available language keys for Quran translations
                            foreach ($valid_quran_lang_keys as $lang_key) {
                                $stmt = db_query("SELECT surah, ayah, `$lang_key` FROM quran_ayahs WHERE `$lang_key` LIKE ?", [$search_term], 's');
                                if ($stmt) {
                                    foreach (db_fetch_all($stmt) as $row) {
                                        if (!empty($row[$lang_key])) {
                                            $results[] = ['type' => 'Quran', 'ref' => "Surah {$row['surah']}:{$row['ayah']}", 'surah' => $row['surah'], 'ayah' => $row['ayah'], 'context' => $row[$lang_key], 'source' => ucfirst($lang_key) . ' Translation'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if (in_array('tafsir', $scopes)) {
                    $stmt = db_query("SELECT surah, ayah, notes FROM tafsir WHERE user_id = ? AND notes LIKE ?", [$user_id, $search_term], 'is');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $results[] = ['type' => 'Tafsir', 'ref' => "Surah {$row['surah']}:{$row['ayah']}", 'surah' => $row['surah'], 'ayah' => $row['ayah'], 'context' => $row['notes'], 'source' => 'Personal Tafsir'];
                        }
                    }
                }
                if (in_array('themes', $scopes)) {
                    $stmt = db_query("SELECT id, name, description FROM themes WHERE user_id = ? AND (name LIKE ? OR description LIKE ?)", [$user_id, $search_term, $search_term], 'iss');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $results[] = ['type' => 'Theme', 'ref' => "Theme: {$row['name']}", 'context' => $row['description'], 'source' => 'Theme Description'];
                        }
                    }
                    $stmt = db_query("SELECT ta.surah, ta.ayah, ta.notes, t.name AS theme_name FROM theme_ayahs ta JOIN themes t ON ta.theme_id = t.id WHERE ta.user_id = ? AND ta.notes LIKE ?", [$user_id, $search_term], 'is');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $results[] = ['type' => 'Theme Link', 'ref' => "Surah {$row['surah']}:{$row['ayah']} (Theme: {$row['theme_name']})", 'surah' => $row['surah'], 'ayah' => $row['ayah'], 'context' => $row['notes'], 'source' => 'Theme Link Notes'];
                        }
                    }
                }
                if (in_array('roots', $scopes)) {
                    $stmt = db_query("SELECT root, description FROM root_words WHERE user_id = ? AND (root LIKE ? OR description LIKE ?)", [$user_id, $search_term, $search_term], 'iss');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $results[] = ['type' => 'Root', 'ref' => "Root: {$row['root']}", 'context' => $row['description'], 'source' => 'Root Notes'];
                        }
                    }
                }
                if (in_array('recitation', $scopes)) {
                    $stmt = db_query("SELECT surah, ayah_start, ayah_end, notes FROM recitations WHERE user_id = ? AND notes LIKE ?", [$user_id, $search_term], 'is');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $range = ($row['ayah_start'] && $row['ayah_end']) ? "{$row['ayah_start']}-{$row['ayah_end']}" : ($row['ayah_start'] ? "{$row['ayah_start']}" : 'Full Surah');
                            $results[] = ['type' => 'Recitation Log', 'ref' => "Surah {$row['surah']} ({$range})", 'context' => $row['notes'], 'source' => 'Recitation Notes'];
                        }
                    }
                }
                if (in_array('hifz', $scopes)) {
                    $stmt = db_query("SELECT surah, ayah, notes FROM hifz WHERE user_id = ? AND notes LIKE ?", [$user_id, $search_term], 'is');
                    if ($stmt) {
                        foreach (db_fetch_all($stmt) as $row) {
                            $results[] = ['type' => 'Hifz', 'ref' => "Surah {$row['surah']}:{$row['ayah']}", 'surah' => $row['surah'], 'ayah' => $row['ayah'], 'context' => $row['notes'], 'source' => 'Hifz Notes'];
                        }
                    }
                }
                echo json_encode(['success' => true, 'data' => $results]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_setting':
            $setting_name = $_POST['name'] ?? '';
            if (is_logged_in() && $setting_name) {
                $stmt = db_query("SELECT setting_value FROM settings WHERE user_id = ? AND setting_name = ?", [$user_id, $setting_name], 'is');
                if ($stmt && $data = db_fetch_row($stmt)) {
                    echo json_encode(['success' => true, 'value' => $data['setting_value']]);
                } else {
                    echo json_encode(['success' => true, 'value' => null]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated or invalid parameters.']);
            }
            break;
        case 'put_setting':
            $setting_name = $_POST['name'] ?? '';
            $setting_value = $_POST['value'] ?? '';
            if (is_logged_in() && $setting_name) {
                $stmt = db_query("INSERT INTO settings (user_id, setting_name, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$user_id, $setting_name, $setting_value, $setting_value], 'isss');
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'Setting saved.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save setting.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated or invalid parameters.']);
            }
            break;
        case 'get_word_translation':
            $word_id = $_POST['word_id'] ?? 0;
            if ($word_id) {
                $stmt = db_query("SELECT * FROM word_translations WHERE word_id = ?", [$word_id], 'i');
                if ($stmt && $data = db_fetch_row($stmt)) {
                    echo json_encode(['success' => true, 'data' => $data]);
                } else {
                    echo json_encode(['success' => true, 'data' => null]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid word_id.']);
            }
            break;
        case 'get_word_metadata_for_ayah':
            $surah = $_POST['surah'] ?? 0;
            $ayah = $_POST['ayah'] ?? 0;
            if ($surah && $ayah) {
                $stmt = db_query("SELECT word_id, word_position, arabic_word FROM word_metadata WHERE surah = ? AND ayah = ? ORDER BY word_position", [$surah, $ayah], 'ii');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch word metadata.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            }
            break;
        case 'get_all_word_metadata':
            $stmt = db_query("SELECT word_id, surah, ayah, word_position, arabic_word FROM word_metadata");
            if ($stmt) {
                echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch all word metadata.']);
            }
            break;
        case 'get_all_word_translations':
            $stmt = db_query("SELECT word_id, ur_meaning, en_meaning, pashto_text, bn_meaning FROM word_translations");
            if ($stmt) {
                echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch all word translations.']);
            }
            break;
        case 'update_translation':
            if (can_edit_translations()) {
                $word_id = $_POST['word_id'] ?? 0;
                $lang = $_POST['lang'] ?? '';
                $text = $_POST['text'] ?? '';
                if ($word_id && $lang && in_array($lang, $valid_word_col_names)) {

                    $sql = "INSERT INTO word_translations (word_id, $lang) VALUES (?, ?) ON DUPLICATE KEY UPDATE $lang = ?";


                    $stmt = db_query($sql, [$word_id, $text, $text], 'iss');

                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Translation updated. Admin approval might be required.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update translation.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters or unauthorized language.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authorized to edit translations.']);
            }
            break;
        case 'admin_approve_translation':
            if (can_approve_translations()) {
                $word_id = $_POST['word_id'] ?? 0;
                $lang = $_POST['lang'] ?? '';
                if ($word_id && $lang && in_array($lang, ['ur_meaning', 'en_meaning', 'pashto_text', 'bn_meaning'])) {
                    $stmt = db_query("UPDATE word_translations SET approved_by = ?, approved_at = NOW() WHERE word_id = ?", [$user_id, $word_id], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Translation approved.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to approve translation.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authorized to approve translations.']);
            }
            break;
        case 'add_goal':
            if (is_logged_in()) {
                $title = $_POST['title'] ?? '';
                $type = $_POST['type'] ?? '';
                $target_date = $_POST['targetDate'] ?? null;
                $creation_date = $_POST['creationDate'] ?? date('Y-m-d');
                $target_surah = $_POST['targetSurah'] ?? null;
                $target_juz = $_POST['targetJuz'] ?? null;
                $target_theme = $_POST['targetTheme'] ?? null;
                $target_count = $_POST['targetCount'] ?? null;
                $target_day = $_POST['targetDay'] ?? null;
                $sql = "INSERT INTO goals (user_id, title, type, target_date, creation_date, target_surah, target_juz, target_theme, target_count, target_day)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $params = [$user_id, $title, $type, $target_date, $creation_date, $target_surah, $target_juz, $target_theme, $target_count, $target_day];
                $types = 'issssiiiii';
                $stmt = db_query($sql, $params, $types);
                if ($stmt) {
                    echo json_encode(['success' => true, 'message' => 'Goal added.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to add goal.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_all_goals':
            if (is_logged_in()) {
                $stmt = db_query("SELECT * FROM goals WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch goals.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'delete_goal':
            if (is_logged_in()) {
                $goal_id = $_POST['id'] ?? 0;
                if ($goal_id) {
                    $stmt = db_query("DELETE FROM goals WHERE user_id = ? AND id = ?", [$user_id, $goal_id], 'ii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Goal deleted.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete goal.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'update_goal_completion':
            if (is_logged_in()) {
                $goal_id = $_POST['id'] ?? 0;
                $is_complete = $_POST['isComplete'] ?? false;
                if ($goal_id) {
                    $stmt = db_query("UPDATE goals SET is_complete = ? WHERE user_id = ? AND id = ?", [$is_complete, $user_id, $goal_id], 'iii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Goal completion status updated.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update goal completion.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'export_user_data':
            if (is_logged_in()) {
                $data = [];
                $stores = ['tafsir', 'themes', 'theme_ayahs', 'root_words', 'recitations', 'hifz', 'goals', 'settings'];
                foreach ($stores as $store_name) {
                    $stmt = db_query("SELECT * FROM $store_name WHERE user_id = ?", [$user_id], 'i');
                    if ($stmt) {
                        $data[$store_name] = db_fetch_all($stmt);
                    } else {
                        $data[$store_name] = [];
                    }
                }
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'import_user_data':
            if (is_logged_in()) {
                $import_data = json_decode($_POST['data'], true);
                if (!is_array($import_data)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid import data format.']);
                    exit();
                }
                $conn->begin_transaction();
                try {
                    $stores_to_clear = ['tafsir', 'themes', 'theme_ayahs', 'root_words', 'recitations', 'hifz', 'goals', 'settings'];
                    foreach ($stores_to_clear as $store_name) {
                        db_query("DELETE FROM $store_name WHERE user_id = ?", [$user_id], 'i');
                    }
                    $import_order = [
                        'themes',
                        'root_words',
                        'tafsir',
                        'recitations',
                        'hifz',
                        'goals',
                        'settings',
                        'theme_ayahs'
                    ];
                    foreach ($import_order as $store_name) {
                        if (isset($import_data[$store_name]) && is_array($import_data[$store_name])) {
                            foreach ($import_data[$store_name] as $row) {
                                $columns = [];
                                $values = [];
                                $types = '';
                                foreach ($row as $col => $val) {
                                    if ($col !== 'id' && $col !== 'user_id' && $col !== 'created_at' && $col !== 'updated_at') {
                                        $columns[] = $col;
                                        $values[] = $val;
                                        if (is_int($val)) $types .= 'i';
                                        elseif (is_float($val)) $types .= 'd';
                                        elseif (is_bool($val)) $types .= 'i';
                                        else $types .= 's';
                                    }
                                }
                                $columns[] = 'user_id';
                                $values[] = $user_id;
                                $types .= 'i';
                                if (!in_array('created_at', $columns)) {
                                    $columns[] = 'created_at';
                                    $values[] = $row['created_at'] ?? date('Y-m-d H:i:s');
                                    $types .= 's';
                                }
                                if (in_array($store_name, ['tafsir', 'hifz']) && !in_array('updated_at', $columns)) {
                                    $columns[] = 'updated_at';
                                    $values[] = $row['updated_at'] ?? date('Y-m-d H:i:s');
                                    $types .= 's';
                                }
                                $sql = "INSERT INTO $store_name (" . implode(', ', $columns) . ") VALUES (" . implode(', ', array_fill(0, count($columns), '?')) . ")";
                                $stmt = db_query($sql, $values, $types);
                                if (!$stmt) {
                                    throw new Exception("Failed to insert into $store_name: " . $conn->error);
                                }
                            }
                        }
                    }
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'Data imported successfully.']);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Import failed: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'clear_personal_data':
            if (is_logged_in()) {
                $conn->begin_transaction();
                try {
                    $stores = ['tafsir', 'themes', 'theme_ayahs', 'root_words', 'recitations', 'hifz', 'goals', 'settings'];
                    foreach ($stores as $store_name) {
                        db_query("DELETE FROM $store_name WHERE user_id = ?", [$user_id], 'i');
                    }
                    $conn->commit();
                    echo json_encode(['success' => true, 'message' => 'All personal data cleared.']);
                } catch (Exception $e) {
                    $conn->rollback();
                    echo json_encode(['success' => false, 'message' => 'Failed to clear data: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'get_all_themes_for_dropdown':
            if (is_logged_in()) {
                $stmt = db_query("SELECT id, name FROM themes WHERE user_id = ?", [$user_id], 'i');
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch themes.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            }
            break;
        case 'edit_word_translation':
            if (can_edit_translations()) {
                $word_id = $_POST['word_id'] ?? 0;
                $lang_key = $_POST['lang_key'] ?? '';
                $translation_text = $_POST['translation_text'] ?? '';
                $stmt_get_col = db_query("SELECT word_col_name FROM languages WHERE lang_key = ?", [$lang_key], 's');
                $db_col_row = db_fetch_row($stmt_get_col);
                $db_col = $db_col_row ? $db_col_row['word_col_name'] : '';
                if ($word_id && $db_col) {

                    if ($user_role === 'admin') {

                        $sql = "INSERT INTO word_translations (word_id, $db_col, approved_by, approved_at) 
                                VALUES (?, ?, ?, NOW()) 
                                ON DUPLICATE KEY UPDATE $db_col = ?, approved_by = ?, approved_at = NOW()";
                        $stmt = db_query($sql, [$word_id, $translation_text, $user_id, $translation_text, $user_id], 'isisi');
                        if ($stmt) {
                            echo json_encode(['success' => true, 'message' => 'Translation updated and auto-approved.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to approve translation.']);
                        }
                    } else {

                        $sql = "INSERT INTO word_translations (word_id, $db_col) VALUES (?, ?) ON DUPLICATE KEY UPDATE $db_col = ?";
                        $stmt = db_query($sql, [$word_id, $translation_text, $translation_text], 'iss');
                        if ($stmt) {
                            echo json_encode(['success' => true, 'message' => 'Translation submitted for review.']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Failed to submit translation.']);
                        }
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
            break;
        case 'admin_update_quran_translation':
            if ($user_role === 'admin') {
                $surah = $_POST['surah'] ?? 0;
                $ayah = $_POST['ayah'] ?? 0;
                $lang_key = $_POST['lang_key'] ?? '';
                $translation_text = $_POST['translation_text'] ?? '';
                if ($surah && $ayah && $lang_key && in_array($lang_key, $valid_quran_lang_keys)) {
                    $sql = "UPDATE quran_ayahs SET $lang_key = ? WHERE surah = ? AND ayah = ?";
                    $stmt = db_query($sql, [$translation_text, $surah, $ayah], 'sii');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'Quran translation updated by admin.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update Quran translation.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
            break;
        case 'get_all_users':
            if ($user_role === 'admin') {
                $stmt = db_query("SELECT id, username, email, role, created_at FROM users");
                if ($stmt) {
                    echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to fetch users.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
            break;
        case 'update_user_role':
            if ($user_role === 'admin') {
                $user_id_to_update = $_POST['user_id'] ?? 0;
                $new_role = $_POST['new_role'] ?? '';
                if ($user_id_to_update && in_array($new_role, ['public', 'registered', 'admin'])) {
                    $stmt = db_query("UPDATE users SET role = ? WHERE id = ?", [$new_role, $user_id_to_update], 'si');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'User role updated.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update user role.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
            break;
        case 'delete_user':
            if ($user_role === 'admin') {
                $user_id_to_delete = $_POST['user_id'] ?? 0;
                if ($user_id_to_delete) {
                    $stmt = db_query("DELETE FROM users WHERE id = ?", [$user_id_to_delete], 'i');
                    if ($stmt) {
                        echo json_encode(['success' => true, 'message' => 'User deleted.']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to delete user.']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
            break;
        case 'add_language':
            if ($user_role === 'admin') {
                $lang_key = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['lang_key'] ?? '');
                $label = $_POST['label'] ?? '';
                $lang_code = $_POST['lang_code'] ?? '';
                $direction = in_array($_POST['direction'], ['ltr', 'rtl']) ? $_POST['direction'] : 'ltr';
                $font_var = $_POST['font_var'] ?? '';
                $word_col_name = $lang_key . '_meaning';
                if ($lang_key && $label && $lang_code) {
                    $conn->begin_transaction();
                    try {
                        db_query(
                            "INSERT INTO languages (lang_key, label, lang_code, direction, font_var, word_col_name) VALUES (?, ?, ?, ?, ?, ?)",
                            [$lang_key, $label, $lang_code, $direction, $font_var, $word_col_name],
                            'ssssss'
                        );
                        $conn->query("ALTER TABLE quran_ayahs ADD COLUMN `$lang_key` TEXT NULL");
                        if ($conn->error) throw new Exception("Failed on quran_ayahs: " . $conn->error);
                        $conn->query("ALTER TABLE word_translations ADD COLUMN `$word_col_name` TEXT NULL");
                        if ($conn->error) throw new Exception("Failed on word_translations: " . $conn->error);
                        $conn->commit();
                        echo json_encode(['success' => true, 'message' => 'Language added successfully.']);
                    } catch (Exception $e) {
                        $conn->rollback();
                        echo json_encode(['success' => false, 'message' => 'Failed to add language: ' . $e->getMessage()]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            }
            break;
        case 'get_all_languages':
            $stmt = db_query("SELECT lang_key AS `key`, label, lang_code, direction, font_var FROM languages ORDER BY id");
            if ($stmt) {
                echo json_encode(['success' => true, 'data' => db_fetch_all($stmt)]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch languages.']);
            }
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown AJAX action.']);
            break;
    }
    exit();
}
$surah_names = [
    "Al-Fatihah",
    "Al-Baqarah",
    "Al 'Imran",
    "An-Nisa'",
    "Al-Ma'idah",
    "Al-An'am",
    "Al-A'raf",
    "Al-Anfal",
    "At-Tawbah",
    "Yunus",
    "Hud",
    "Yusuf",
    "Ar-Ra'd",
    "Ibrahim",
    "Al-Hijr",
    "An-Nahl",
    "Al-Isra'",
    "Al-Kahf",
    "Maryam",
    "Taha",
    "Al-Anbya'",
    "Al-Hajj",
    "Al-Mu'minun",
    "An-Nur",
    "Al-Furqan",
    "Ash-Shu'ara'",
    "An-Naml",
    "Al-Qasas",
    "Al-'Ankabut",
    "Ar-Rum",
    "Luqman",
    "As-Sajdah",
    "Al-Ahzab",
    "Saba'",
    "Fatir",
    "Ya-Sin",
    "As-Saffat",
    "Sad",
    "Az-Zumar",
    "Ghafir",
    "Fussilat",
    "Ash-Shura",
    "Az-Zukhruf",
    "Ad-Dukhan",
    "Al-Jathiyah",
    "Al-Ahqaf",
    "Muhammad",
    "Al-Fath",
    "Al-Hujurat",
    "Qaf",
    "Adh-Dhariyat",
    "At-Tur",
    "An-Najm",
    "Al-Qamar",
    "Ar-Rahman",
    "Al-Waqi'ah",
    "Al-Hadid",
    "Al-Mujadilah",
    "Al-Hashr",
    "Al-Mumtahanah",
    "As-Saff",
    "Al-Jumu'ah",
    "Al-Munafiqun",
    "At-Taghabun",
    "At-Talaq",
    "At-Tahrim",
    "Al-Mulk",
    "Al-Qalam",
    "Al-Haqqah",
    "Al-Ma'arij",
    "Nuh",
    "Al-Jinn",
    "Al-Muzzammil",
    "Al-Muddaththir",
    "Al-Qiyamah",
    "Al-Insan",
    "Al-Mursalat",
    "An-Naba'",
    "An-Nazi'at",
    "'Abasa",
    "At-Takwir",
    "Al-Infitar",
    "Al-Mutaffifin",
    "Al-Inshiqaq",
    "Al-Buruj",
    "At-Tariq",
    "Al-A'la",
    "Al-Ghashiyah",
    "Al-Fajr",
    "Al-Balad",
    "Ash-Shams",
    "Al-Layl",
    "Ad-Duha",
    "Ash-Sharh",
    "At-Tin",
    "Al-'Alaq",
    "Al-Qadr",
    "Al-Bayyinah",
    "Az-Zalzalah",
    "Al-'Adiyat",
    "Al-Qari'ah",
    "At-Takathur",
    "Al-'Asr",
    "Al-Humazah",
    "Al-Fil",
    "Quraysh",
    "Al-Ma'un",
    "Al-Kawthar",
    "Al-Kafirun",
    "An-Nasr",
    "Al-Masad",
    "Al-Ikhlas",
    "Al-Falaq",
    "An-Nas"
];
$surah_ayah_counts = [
    0,
    7,
    286,
    200,
    176,
    120,
    165,
    206,
    75,
    129,
    109,
    123,
    111,
    43,
    52,
    99,
    128,
    111,
    110,
    98,
    135,
    112,
    78,
    118,
    64,
    77,
    227,
    93,
    88,
    69,
    60,
    34,
    30,
    73,
    54,
    45,
    83,
    182,
    88,
    75,
    85,
    54,
    53,
    89,
    59,
    37,
    35,
    38,
    29,
    18,
    45,
    60,
    49,
    62,
    55,
    78,
    96,
    29,
    22,
    24,
    13,
    14,
    11,
    11,
    18,
    12,
    12,
    30,
    52,
    52,
    44,
    28,
    28,
    20,
    56,
    40,
    31,
    50,
    40,
    46,
    42,
    29,
    19,
    36,
    25,
    22,
    17,
    19,
    26,
    30,
    20,
    15,
    21,
    11,
    8,
    5,
    19,
    5,
    8,
    8,
    11,
    11,
    8,
    3,
    9,
    5,
    4,
    7,
    3,
    6,
    3,
    5,
    4,
    5,
    6
];
$juz_boundaries_data = [
    (object)['juz' => 1, 'name' => "Alif laam meem (آلم)", 'startSurah' => 1, 'startAyah' => 1],
    (object)['juz' => 2, 'name' => "Sayaqulu (سَيَقُولُ)", 'startSurah' => 2, 'startAyah' => 142],
    (object)['juz' => 3, 'name' => "Tilka r Rusulu (تِلْكَ الرُّسُلُ)", 'startSurah' => 2, 'startAyah' => 253],
    (object)['juz' => 4, 'name' => "Lan Tana Loo (لَنْ تَنَالُوا)", 'startSurah' => 3, 'startAyah' => 93],
    (object)['juz' => 5, 'name' => "Wal Mohsanat (وَالْمُحْصَنَاتُ)", 'startSurah' => 4, 'startAyah' => 24],
    (object)['juz' => 6, 'name' => "La Yahubbullah (لَا يُحِبُّ اللَّهُ)", 'startSurah' => 4, 'startAyah' => 148],
    (object)['juz' => 7, 'name' => "Wa Iza Samiu (وَإِذَا سَمِعُوا)", 'startSurah' => 5, 'startAyah' => 82],
    (object)['juz' => 8, 'name' => "Wa Lau Annana (وَلَوْ أَنَّنَا)", 'startSurah' => 6, 'startAyah' => 111],
    (object)['juz' => 9, 'name' => "Qalal Malao (قَالَ الْمَلَأُ)", 'startSurah' => 7, 'startAyah' => 88],
    (object)['juz' => 10, 'name' => "Wa A'lamu (وَاعْلَمُوا)", 'startSurah' => 8, 'startAyah' => 41],
    (object)['juz' => 11, 'name' => "Yatazeroon (يَعْتَذِرُونَ)", 'startSurah' => 9, 'startAyah' => 93],
    (object)['juz' => 12, 'name' => "Wa Mamin Da'abat (وَمَا مِنْ دَابَّةٍ)", 'startSurah' => 11, 'startAyah' => 6],
    (object)['juz' => 13, 'name' => "Wa Ma Ubiroo (وَمَا أُبَرِّئُ)", 'startSurah' => 12, 'startAyah' => 53],
    (object)['juz' => 14, 'name' => "Rubama (رُبَمَا)", 'startSurah' => 15, 'startAyah' => 1],
    (object)['juz' => 15, 'name' => "Subhanallazi (سُبْحَانَ الَّذِي)", 'startSurah' => 17, 'startAyah' => 1],
    (object)['juz' => 16, 'name' => "Qal Alam (قَالَ أَلَمْ)", 'startSurah' => 18, 'startAyah' => 75],
    (object)['juz' => 17, 'name' => "Aqtarabo (اقْتَرَبَ لِلنَّاسِ)", 'startSurah' => 21, 'startAyah' => 1],
    (object)['juz' => 18, 'name' => "Qadd Aflaha (قَدْ أَفْلَحَ)", 'startSurah' => 23, 'startAyah' => 1],
    (object)['juz' => 19, 'name' => "Wa Qalallazina (وَقَالَ الَّذِينَ)", 'startSurah' => 25, 'startAyah' => 21],
    (object)['juz' => 20, 'name' => "A'man Khalaq (أَمَّنْ خَلَقَ)", 'startSurah' => 27, 'startAyah' => 56],
    (object)['juz' => 21, 'name' => "Utlu Ma Oohi (اتْلُ مَا أُوحِيَ)", 'startSurah' => 29, 'startAyah' => 46],
    (object)['juz' => 22, 'name' => "Wa Manyaqnut (وَمَنْ يَقْنُتْ)", 'startSurah' => 33, 'startAyah' => 31],
    (object)['juz' => 23, 'name' => "Wa Mali (وَمَا لِيَ)", 'startSurah' => 36, 'startAyah' => 28],
    (object)['juz' => 24, 'name' => "Faman Azlam (فَمَنْ أَظْلَمُ)", 'startSurah' => 39, 'startAyah' => 32],
    (object)['juz' => 25, 'name' => "Elahe Yuruddo (إِلَيْهِ يُرَدُّ)", 'startSurah' => 41, 'startAyah' => 47],
    (object)['juz' => 26, 'name' => "Ha'a Meem (حم)", 'startSurah' => 46, 'startAyah' => 1],
    (object)['juz' => 27, 'name' => "Qala Fama Khatbukum (قَالَ فَمَا خَطْبُكُمْ)", 'startSurah' => 51, 'startAyah' => 31],
    (object)['juz' => 28, 'name' => "Qadd Sami Allah (قَدْ سَمِعَ اللَّهُ)", 'startSurah' => 58, 'startAyah' => 1],
    (object)['juz' => 29, 'name' => "Tabarakallazi (تَبَارَكَ الَّذِي)", 'startSurah' => 67, 'startAyah' => 1],
    (object)['juz' => 30, 'name' => "Amma Yatasa'aloon (عَمَّ يَتَسَاءَلُونَ)", 'startSurah' => 78, 'startAyah' => 1]
];
$static_quranic_themes = [
    (object)['id' => 'static_01', 'name' => "Ayat al-Kursi (Verse of the Throne)", 'exampleSurah' => 2, 'exampleAyah' => 255, 'description' => "Greatest verse, protection, affirmation of Allah's sovereignty."],
    (object)['id' => 'static_02', 'name' => "Surah Al-Fatihah (The Opening)", 'exampleSurah' => 1, 'exampleAyah' => 1, 'description' => "Essence of the Quran, recited in every prayer."],
    (object)['id' => 'static_03', 'name' => "Surah Yasin (Heart of the Quran)", 'exampleSurah' => 36, 'exampleAyah' => 1, 'description' => "Recited for blessings, ease, and for the deceased."],
    (object)['id' => 'static_04', 'name' => "Surah Al-Mulk (The Dominion - Protector from Grave)", 'exampleSurah' => 67, 'exampleAyah' => 1, 'description' => "Recited for protection from torment of the grave."],
    (object)['id' => 'static_05', 'name' => "Surah Al-Waqi'ah (The Inevitable - Sustenance)", 'exampleSurah' => 56, 'exampleAyah' => 1, 'description' => "Recited for protection against poverty and for sustenance."],
    (object)['id' => 'static_06', 'name' => "Surah Ar-Rahman (The Most Merciful - Bride of Quran)", 'exampleSurah' => 55, 'exampleAyah' => 1, 'description' => "Highlights Allah's bounties and mercy."],
    (object)['id' => 'static_07', 'name' => "Surah Al-Kahf (The Cave - Protection from Dajjal)", 'exampleSurah' => 18, 'exampleAyah' => 1, 'description' => "Recited on Fridays, protection from Dajjal, stories of guidance."],
    (object)['id' => 'static_08', 'name' => "Last two Ayahs of Surah Al-Baqarah", 'exampleSurah' => 2, 'exampleAyah' => 285, 'description' => "Sufficient for protection and blessings if recited at night."],
    (object)['id' => 'static_09', 'name' => "Surah Al-Ikhlas (Sincerity - Equals 1/3rd Quran)", 'exampleSurah' => 112, 'exampleAyah' => 1, 'description' => "Pure monotheism, immense reward."],
    (object)['id' => 'static_10', 'name' => "Surah Al-Falaq (The Daybreak - Protection)", 'exampleSurah' => 113, 'exampleAyah' => 1, 'description' => "Seeking refuge from evil."],
    (object)['id' => 'static_11', 'name' => "Surah An-Nas (Mankind - Protection)", 'exampleSurah' => 114, 'exampleAyah' => 1, 'description' => "Seeking refuge from whispers of Shaytan."],
    (object)['id' => 'static_12', 'name' => "Ayah for seeking Forgiveness (Syed-ul-Istighfar concept)", 'exampleSurah' => 3, 'exampleAyah' => 135, 'description' => "Verses encouraging repentance and seeking forgiveness."],
    (object)['id' => 'static_13', 'name' => "Dua of Yunus (AS) / Ayat-e-Karima", 'exampleSurah' => 21, 'exampleAyah' => 87, 'description' => "For relief from distress: La ilaha illa anta subhanaka inni kuntu minaz-zalimin."],
    (object)['id' => 'static_14', 'name' => "Verse of Light (Ayat an-Nur)", 'exampleSurah' => 24, 'exampleAyah' => 35, 'description' => "Metaphorical description of Allah's light and guidance."],
    (object)['id' => 'static_15', 'name' => "Four Quls (Al-Kafirun, Al-Ikhlas, Al-Falaq, An-Nas)", 'exampleSurah' => 109, 'exampleAyah' => 1, 'description' => "Collective term for these four protective Surahs."],
    (object)['id' => 'static_16', 'name' => "Guidance in Decision Making (Istikhara concept)", 'exampleSurah' => 2, 'exampleAyah' => 216, 'description' => "Trusting Allah's knowledge in what is good/bad for us."],
    (object)['id' => 'static_17', 'name' => "Marriage and Family", 'exampleSurah' => 30, 'exampleAyah' => 21, 'description' => "Signs of Allah in creating spouses for tranquility and affection."],
    (object)['id' => 'static_18', 'name' => "Dealing with Grief and Loss", 'exampleSurah' => 2, 'exampleAyah' => 156, 'description' => "Inna lillahi wa inna ilayhi raji'un - Turning to Allah in times of calamity."],
    (object)['id' => 'static_19', 'name' => "Financial Dealings & Charity", 'exampleSurah' => 2, 'exampleAyah' => 261, 'description' => "Parable of those who spend in Allah's way."],
    (object)['id' => 'static_20', 'name' => "Good Conduct and Speech", 'exampleSurah' => 17, 'exampleAyah' => 53, 'description' => "Speak that which is best."],
    (object)['id' => 'static_21', 'name' => "Patience in Adversity", 'exampleSurah' => 39, 'exampleAyah' => 10, 'description' => "The patient will be given their reward without account."],
    (object)['id' => 'static_22', 'name' => "Seeking Knowledge", 'exampleSurah' => 20, 'exampleAyah' => 114, 'description' => "Rabbi zidni ilma - My Lord, increase me in knowledge."],
    (object)['id' => 'static_23', 'name' => "Respect for Parents", 'exampleSurah' => 17, 'exampleAyah' => 23, 'description' => "Decree to not worship except Him, and to parents, good treatment."],
    (object)['id' => 'static_24', 'name' => "Maintaining Ties of Kinship", 'exampleSurah' => 4, 'exampleAyah' => 1, 'description' => "Fear Allah through whom you demand your mutual rights, and reverence the wombs."],
    (object)['id' => 'static_25', 'name' => "Trust in Allah (Tawakkul)", 'exampleSurah' => 65, 'exampleAyah' => 3, 'description' => "And whoever relies upon Allah - then He is sufficient for him."],
    (object)['id' => 'static_26', 'name' => "Prohibition of Backbiting & Slander", 'exampleSurah' => 49, 'exampleAyah' => 12, 'description' => "Avoid suspicion, do not spy or backbite each other."],
    (object)['id' => 'static_27', 'name' => "Importance of Justice", 'exampleSurah' => 5, 'exampleAyah' => 8, 'description' => "Be just; that is nearer to righteousness."],
    (object)['id' => 'static_28', 'name' => "Humility", 'exampleSurah' => 25, 'exampleAyah' => 63, 'description' => "Servants of the Most Merciful are those who walk upon the earth easily..."],
    (object)['id' => 'static_29', 'name' => "Gratitude for Blessings", 'exampleSurah' => 16, 'exampleAyah' => 114, 'description' => "So eat of what Allah has provided you lawful and good and be grateful for the favor of Allah."],
    (object)['id' => 'static_30', 'name' => "Remembrance of Allah (Dhikr)", 'exampleSurah' => 13, 'exampleAyah' => 28, 'description' => "Unquestionably, by the remembrance of Allah hearts are assured."],
    (object)['id' => 'static_31', 'name' => "Overcoming Anxiety & Stress", 'exampleSurah' => 94, 'exampleAyah' => 5, 'description' => "For indeed, with hardship [will be] ease."],
    (object)['id' => 'static_32', 'name' => "The Power of Dua (Supplication)", 'exampleSurah' => 40, 'exampleAyah' => 60, 'description' => "And your Lord says, 'Call upon Me; I will respond to you.'"],
    (object)['id' => 'static_33', 'name' => "Warning Against Arrogance", 'exampleSurah' => 31, 'exampleAyah' => 18, 'description' => "And do not turn your cheek [in contempt] toward people..."],
    (object)['id' => 'static_34', 'name' => "Importance of Consultation (Shura)", 'exampleSurah' => 42, 'exampleAyah' => 38, 'description' => "...and whose affair is [determined by] consultation among themselves..."],
    (object)['id' => 'static_35', 'name' => "Forgiving Others", 'exampleSurah' => 64, 'exampleAyah' => 14, 'description' => "...but if you pardon and overlook and forgive - then indeed, Allah is Forgiving and Merciful."],
    (object)['id' => 'static_36', 'name' => "Unity of the Ummah", 'exampleSurah' => 3, 'exampleAyah' => 103, 'description' => "And hold firmly to the rope of Allah all together and do not become divided."],
    (object)['id' => 'static_37', 'name' => "The Brevity of Worldly Life", 'exampleSurah' => 57, 'exampleAyah' => 20, 'description' => "Know that the life of this world is but amusement and diversion..."],
    (object)['id' => 'static_38', 'name' => "Reward for Good Deeds", 'exampleSurah' => 99, 'exampleAyah' => 7, 'description' => "So whoever does an atom's weight of good will see it."],
    (object)['id' => 'static_39', 'name' => "Call to Reflection (Tadabbur)", 'exampleSurah' => 47, 'exampleAyah' => 24, 'description' => "Then do they not reflect upon the Qur'an, or are there locks upon [their] hearts?"],
    (object)['id' => 'static_40', 'name' => "Friday Prayer (Jumu'ah)", 'exampleSurah' => 62, 'exampleAyah' => 9, 'description' => "O you who have believed, when the adhan is called for the prayer on the day of Jumu'ah..."]
];
$translation_config = [];
$result = $conn->query("SELECT lang_key AS `key`, label, lang_code, direction, font_var, word_col_name FROM languages ORDER BY id");
if ($result) {
    while ($row = $result->fetch_object()) {
        $translation_config[] = $row;
    }
}
/* $stmt_check_quran = $conn->query("SELECT COUNT(*) FROM quran_ayahs");
if ($stmt_check_quran && $stmt_check_quran->fetch_row()[0] == 0) {
    $conn->query("INSERT INTO quran_ayahs (surah, ayah, arabic, urdu, english, Bangali, pashto) VALUES
        (1, 1, 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ', 'شروع اللہ کے نام سے جو بڑا مہربان نہایت رحم والا ہے', 'In the name of Allah, the Entirely Merciful, the Especially Merciful.', 'আল্লাহর নামে, যিনি পরম করুণাময়, অতি দয়ালু।', 'د الله په نامه، چې ډېر مهربان، ډېر بخښونکی دی.'),
        (1, 2, 'الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ', 'سب تعریف اللہ کے لئے ہے جو تمام جہانوں کا رب ہے', 'All praise is due to Allah, Lord of the worlds,', 'সকল প্রশংসা আল্লাহর জন্য, যিনি বিশ্বজগতের পালনকর্তা।', 'ټول حمد الله ته، چې د نړيو پالونکی دی.'),
        (1, 3, 'الرَّحْمَٰنِ الرَّحِيمِ', 'بڑا مہربان نہایت رحم والا', 'The Entirely Merciful, the Especially Merciful,', 'যিনি পরম করুণাময়, অতি দয়ালু।', 'ډېر مهربان، ډېر بخښونکی.'),
        (1, 4, 'مَالِكِ يَوْمِ الدِّينِ', 'روز جزا کا مالک', 'Sovereign of the Day of Recompense.', 'কর্মফল দিবসের মালিক।', 'د قیامت ورځې مالک.'),
        (1, 5, 'إِيَّاكَ نَعْبُدُ وَإِيَّاكَ نَسْتَعِينُ', 'ہم تیری ہی عبادت کرتے ہیں اور تجھی سے مدد مانگتے ہیں', 'It is You we worship and You we ask for help.', 'আমরা একমাত্র তোমারই ইবাদত করি এবং একমাত্র তোমারই সাহায্য চাই।', 'یوازې ستا عبادت کوو او یوازې له تا مرسته غواړو.'),
        (2, 1, 'الم', 'الف، لام، میم', 'Alif, Lām, Meem.', 'আলিফ, লাম, মীম।', 'الف، لام، میم.'),
        (2, 2, 'ذَٰلِكَ الْكِتَابُ لَا رَيْبَ ۛ فِيهِ ۛ هُدًى لِّلْمُتَّقِينَ', 'یہ وہ کتاب ہے جس میں کوئی شک نہیں، پرہیزگاروں کے لیے ہدایت ہے', 'This is the Book about which there is no doubt, a guidance for those who fear Allah.', 'এটি এমন একটি কিতাব, যাতে কোনো সন্দেহ নেই, মুত্তাকীদের জন্য পথপ্রদর্শক।', 'دا هغه کتاب دی چې په هغې کې شک نشته، د پرهیزګارانو لپاره لارښوونه ده.'),
        (2, 255, 'اللَّهُ لَا إِلَٰهَ إِلَّا هُوَ الْحَيُّ الْقَيُّومُ ۚ لَا تَأْخُذُهُ سِنَةٌ وَلَا نَوْمٌ ۚ لَّهُ مَا فِي السَّمَاوَاتِ وَمَا فِي الْأَرْضِ ۗ مَن ذَا الَّذِي يَشْفَعُ عِندَهُ إِلَّا بِإِذْنِهِ ۚ يَعْلَمُ مَا بَيْنَ أَيْدِيهِمْ وَمَا خَلْفَهُمْ ۖ وَلَا يُحِيطُونَ بِشَيْءٍ مِّنْ عِلْمِهِ إِلَّا بِمَا شَاءَ ۚ وَسِعَ كُرْسِيُّهُ السَّمَاوَاتِ وَالْأَرْضَ ۖ وَلَا يَئُودُهُ حِفْظُهُمَا ۚ وَهُوَ الْعَلِيُّ الْعَظِيمُ', 'اللہ وہ ہے جس کے سوا کوئی معبود نہیں، زندہ ہے، سب کا سنبھالنے والا ہے، اسے نہ اونگھ آتی ہے نہ نیند، اسی کا ہے جو کچھ آسمانوں میں ہے اور جو کچھ زمین میں ہے۔ کون ہے جو اس کے حضور شفاعت کر سکے مگر اس کی اجازت سے؟ وہ جانتا ہے جو ان کے سامنے ہے اور جو ان کے پیچھے ہے، اور وہ اس کے علم میں سے کسی چیز کو احاطہ نہیں کر سکتے مگر جتنا وہ چاہے، اس کی کرسی آسمانوں اور زمین کو گھیرے ہوئے ہے، اور اسے ان دونوں کی حفاظت تھکاتی نہیں، اور وہ بلند وبالا عظمت والا ہے۔', 'Allah - there is no deity except Him, the Ever-Living, the Sustainer of [all] existence. Neither slumber nor sleep overtakes Him. To Him belongs whatever is in the heavens and whatever is on the earth. Who is it that can intercede with Him except by His permission? He knows what is [presently] before them and what will be after them, and they encompass not a thing of His knowledge except for what He wills. His Kursi extends over the heavens and the earth, and their preservation tires Him not. And He is the Most High, the Most Great.', 'আল্লাহ্, তিনি ছাড়া কোনো উপাস্য নেই। তিনি চিরঞ্জীব, সর্বসত্তার ধারক। তাঁকে তন্দ্রা বা নিদ্রা স্পর্শ করে না। আসমান ও যমীনে যা কিছু আছে, সবই তাঁর। কে আছে এমন, যে তাঁর অনুমতি ছাড়া তাঁর কাছে সুপারিশ করবে? তিনি তাদের সম্মুখের ও পশ্চাতের সবকিছু জানেন। তাঁর জ্ঞান থেকে তারা কোনো কিছুকে পরিবেষ্টন করতে পারে না, তবে তিনি যতটুকু চান। তাঁর কুরসি আসমান ও যমীনকে পরিব্যাপ্ত করে আছে। আর সেগুলোর রক্ষণাবেক্ষণ তাঁকে ক্লান্ত করে না। তিনি সুউচ্চ, সুমহান।', 'الله – بل ستايل نشته، ژوندی دی، د ټولو هستیو ساتونکی دی. نه ورته غوړ وینا راځي او نه خوب. د هغه دی هغه څه چې په اسمانونو او ځمکه کې دي. څوک دی چې د هغه په حضور کې شفاعت وکړي مګر د هغه په اجازه؟ هغه پوهیږي چې د دوی په مخ کې څه دي او د دوی تر شا به څه وي، او دوی د هغه له علم څخه هیڅ شی نه شي شاملولی مګر هغه څه چې هغه وغواړي. د هغه کرسی په اسمانونو او ځمکه باندې پراخ دی، او د هغوی ساتنه هغه نه ستړي کوي. او هغه ډېر لوړ، ډېر لوی دی.')
    ");
    $conn->query("INSERT INTO word_metadata (word_id, surah, ayah, word_position, arabic_word) VALUES
        (1001, 1, 1, 0, 'بِسْمِ'), (1002, 1, 1, 1, 'اللَّهِ'), (1003, 1, 1, 2, 'الرَّحْمَٰنِ'), (1004, 1, 1, 3, 'الرَّحِيمِ'),
        (1005, 1, 2, 0, 'الْحَمْدُ'), (1006, 1, 2, 1, 'لِلَّهِ'), (1007, 1, 2, 2, 'رَبِّ'), (1008, 1, 2, 3, 'الْعَالَمِينَ')
    ");
    $conn->query("INSERT INTO word_translations (word_id, ur_meaning, en_meaning, pashto_text, bn_meaning, approved_by, approved_at) VALUES
        (1001, 'نام سے', 'In the name of', 'په نوم', 'নামে', 1, NOW()),
        (1002, 'اللہ کی', 'of Allah', 'د الله', 'আল্লাহর', 1, NOW()),
        (1003, 'رحمٰن', 'the Entirely Merciful', 'ډېر مهربان', 'পরম করুণাময়', 1, NOW()),
        (1004, 'رحیم', 'the Especially Merciful', 'ډېر بخښونکی', 'অতি দয়ালু', 1, NOW()),
        (1005, 'حمد', 'All praise', 'ټول حمد', 'সকল প্রশংসা', 1, NOW()),
        (1006, 'اللہ کے لیے', 'for Allah', 'د الله ته', 'আল্লাহর জন্য', 1, NOW()),
        (1007, 'رب', 'Lord', 'پالونکی', 'পালনকর্তা', 1, NOW()),
        (1008, 'جہانوں کا', 'of the worlds', 'د نړيو', 'বিশ্বজগতের', 1, NOW())
    ");
} */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="manifest.json">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>Nur-Ul-Quran Offline - By Yasin Ullah</title>
    <meta name="author" content="Yasin Ullah, Pakistan">
    <meta name="description"
        content="An offline-first, client-side Quranic study environment with personal Tafsir, thematic linking, root analysis, Hifz tracking, and advanced search.">
    <script type="text/javascript"
        src="https://cdn.jsdelivr.net/npm/vis-network@latest/dist/vis-network.min.js"></script>
    <script type="text/javascript"
        src="https://cdnjs.cloudflare.com/ajax/libs/diff_match_patch/20121119/diff_match_patch.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --color-bg-primary: #e8f5e9;
            --color-bg-secondary: #c8e6c9;
            --color-text-primary: #1b5e20;
            --color-text-secondary: #388e3c;
            --color-accent: #4caf50;
            --color-accent-dark: #388e3c;
            --color-border: #a5d6a7;
            --color-shadow: rgba(0, 0, 0, 0.1);
            --color-highlight: #fff9c4;
            --color-error: #ef5350;
            --color-success: #66bb6a;
            --font-arabic: 'Scheherazade New', 'Lateef', 'Amiri', 'Traditional Arabic', calibri;
            --font-urdu: Calibri, 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Pak Nastaleeq', calibri;
            --font-pashto: 'Mirza', 'Noto Nastaliq Urdu', 'Pak Nastaleeq', calibri;
            --font-Bangali: 'Noto Sans Bangali', 'Arial', calibri;
            --font-english: 'Roboto', 'Segoe UI', calibri;
            --font-general: 'Roboto', 'Segoe UI', calibri;
            --border-radius: 8px;
            --padding-main: 20px;
            --transition-speed: 0.3s;
        }

        body.theme-manuscript {
            --color-bg-primary: #f5f5dc;
            --color-bg-secondary: #fff8dc;
            --color-text-primary: #5d4037;
            --color-text-secondary: #795548;
            --color-accent: #ffb300;
            --color-accent-dark: #fb8c00;
            --color-border: #d7ccc8;
            --color-shadow: rgba(0, 0, 0, 0.15);
            --color-highlight: #ffe082;
            --color-error: #c62828;
            --color-success: #388e3c;
            --font-arabic: 'Scheherazade New', calibri;
            --font-urdu: Calibri, 'Jameel Noori Nastaleeq', calibri;
            --font-Bangali: 'Noto Sans Bangali', calibri;
            --font-english: 'Merriweather', calibri;
            --font-general: 'Merriweather', calibri;
        }

        body.theme-holo {
            --color-bg-primary: #0d1a2b;
            --color-bg-secondary: #1a2b3c;
            --color-text-primary: #e0f7fa;
            --color-text-secondary: #b2ebf2;
            --color-accent: #00bcd4;
            --color-accent-dark: #00838f;
            --color-border: #26a69a;
            --color-shadow: rgba(0, 188, 212, 0.2);
            --color-highlight: #80deea;
            --color-error: #ff5252;
            --color-success: #00e676;
            --font-arabic: 'Orbitron', calibri;
            --font-urdu: Calibri, 'Orbitron', calibri;
            --font-Bangali: 'Orbitron', calibri;
            --font-english: 'Orbitron', calibri;
            --font-general: 'Orbitron', calibri;
            --border-radius: 4px;
        }

        *,
        *::before,
        *::after {
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
            overflow-y: scroll;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: var(--color-text-secondary);
            margin-bottom: 15px;
        }

        button,
        input[type="submit"],
        input[type="button"] {
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

        button:hover,
        input[type="submit"]:hover,
        input[type="button"]:hover {
            background-color: var(--color-accent-dark);
            opacity: 0.9;
        }

        button:focus,
        input[type="submit"]:focus,
        input[type="button"]:focus {
            outline: 2px solid var(--color-accent-dark);
            outline-offset: 2px;
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select,
        input[type="password"],
        input[type="email"] {
            font-family: var(--font-general);
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
            width: 100%;
            max-width: 400px;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus,
        input[type="password"]:focus,
        input[type="email"]:focus {
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

        .container {
            display: flex;
            flex-grow: 1;
            padding: 6px 0px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .sidebar {
            width: 250px;
            margin-right: var(--padding-main);
            flex-shrink: 0;
            background-color: var(--color-bg-secondary);
            padding: 7px;
            border-radius: var(--border-radius);
            box-shadow: 0 8px 14px rgb(0 0 0);
            margin-top: -61px;
        }

        .main-content {
            flex-grow: 1;
            background-color: var(--color-bg-secondary);
            padding: var(--padding-main);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px var(--color-shadow);
            overflow-y: auto;
        }

        header {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
            padding: 5px var(--padding-main);
            box-shadow: 0 2px 5px var(--color-shadow);
            display: flex;
            justify-content: end;
            align-items: center;
            flex-shrink: 0;
        }

        header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: var(--color-text-primary);
            margin-right: 16px;
            position: absolute;
            left: 75px;
            top: 0px;
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
            padding: 7px;
            background-color: var(--color-bg-primary);
            border-radius: var(--border-radius);
            color: var(--color-text-primary);
            transition: background-color var(--transition-speed), color var(--transition-speed);
            text-align: center;
            box-shadow: 0px 5px 7px;
        }

        nav a:hover,
        nav a.active {
            background-color: var(--color-accent);
            color: white;
            text-decoration: none;
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
        }

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
            line-height: 2.5;
        }

        .ayah-arabic span {
            cursor: pointer;
            padding: 2px 4px;
            border-bottom: 1px dashed transparent;
            transition: background-color 0.2s, border-bottom-color 0.2s;
        }

        .ayah-arabic span:hover {
            background-color: rgba(var(--color-accent-dark-rgb, 56, 142, 60), 0.2);
            border-bottom-color: var(--color-accent-dark);
        }

        :root {
            --color-accent-dark-rgb: 56, 142, 60;
        }

        body.theme-manuscript {
            --color-accent-dark-rgb: 251, 140, 0;
        }

        body.theme-holo {
            --color-accent-dark-rgb: 0, 131, 143;
        }

        .ayah-translation {
            font-size: 1.3rem;
            color: #3b0aff;
        }

        .tafsir-editor {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--color-border);
        }

        .tafsir-editor textarea {
            width: 100%;
            max-width: 100%;
            margin-bottom: 10px;
            font-size: medium;
        }

        .theme-manager,
        .theme-linker {
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

        .hifz-ayah-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            margin-left: 10px;
        }

        .status-not-started {
            background-color: #e0e0e0;
            color: #424242;
        }

        .status-in-progress {
            background-color: #fff59d;
            color: #fbc02d;
        }

        .status-memorized {
            background-color: #a5d6a7;
            color: #388e3c;
        }

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
        }

        .search-results .result-context {
            font-size: large;
            color: var(--color-text-secondary);
            margin-top: 5px;
        }

        .settings-section {
            margin-bottom: 20px;
        }

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

        #loading-overlay {
            display: flex;
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
            display: none;
        }

        .text-center {
            text-align: center;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-20 {
            margin-bottom: -3px;
        }

        .flex-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        [tabindex="0"]:focus,
        button:focus,
        input:focus,
        select:focus,
        textarea:focus,
        a:focus {
            outline: 3px solid var(--color-accent-dark);
            outline-offset: 2px;
        }

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

        [dir="rtl"] .ayah-arabic,
        [dir="rtl"] .ayah-translation {
            border-radius: 1px;
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

            input[type="text"],
            input[type="number"],
            textarea,
            select {
                max-width: 100%;
            }

            .flex-group {
                flex-direction: column;
                gap: 10px;
            }

            .flex-group button,
            .flex-group input {
                width: 100%;
            }
        }

        body.theme-holo .ayah:hover {
            background: linear-gradient(90deg, rgba(0, 188, 212, 0.1) 0%, rgba(0, 188, 212, 0.05) 100%);
        }

        body.theme-holo .ayah-arabic span:hover {
            background-color: rgba(0, 188, 212, 0.3);
            border-bottom-color: var(--color-highlight);
        }

        body.theme-holo nav a.active {
            background-color: var(--color-accent);
            box-shadow: 0 0 8px var(--color-accent);
        }

        #loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.85);
            color: white;
            justify-content: center;
            align-items: center;
            z-index: 2000;
            text-align: center;
            padding: 20px;
        }

        .loading-content {
            background-color: var(--color-bg-secondary, #1a2b3c);
            padding: 30px 40px;
            border-radius: var(--border-radius, 8px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
        }

        body.theme-serene .loading-content {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
        }

        body.theme-manuscript .loading-content {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
        }

        body.theme-holo .loading-content {
            background-color: #1a2b3c;
            color: #e0f7fa;
        }

        #loading-message-primary {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        #loading-message-secondary {
            font-size: 1rem;
            margin-bottom: 20px;
            min-height: 1.6em;
        }

        #loading-progress-bar-container {
            width: 100%;
            background-color: var(--color-border, #a5d6a7);
            border-radius: var(--border-radius, 8px);
            overflow: hidden;
            height: 20px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        body.theme-holo #loading-progress-bar-container {
            background-color: #26a69a;
        }

        #loading-progress-bar {
            height: 100%;
            background-color: var(--color-accent, #4caf50);
            width: 0%;
            transition: width 0.3s ease-out;
            border-radius: var(--border-radius, 8px) 0 0 var(--border-radius, 8px);
        }

        body.theme-holo #loading-progress-bar {
            background-color: var(--color-accent);
        }

        #loading-percentage {
            font-size: 1.1rem;
            font-weight: bold;
        }

        #loading-first-time-notice {
            font-size: 0.85em;
            margin-top: 15px;
            opacity: 0.8;
        }

        .view-switcher label {
            display: inline-block;
            margin-bottom: 0;
        }

        .view-switcher div>label {
            margin-left: 3px;
        }

        .root-view-content {
            display: none;
        }

        .root-view-content.active-view {
            display: block;
        }

        #root-network-graph-container {
            margin-top: 20px;
        }

        .view-switcher label {
            display: inline-block;
            margin-bottom: 0;
        }

        .view-switcher div>label {
            margin-left: 3px;
        }

        .root-view-content {
            display: none;
        }

        .root-view-content.active-view {
            display: block;
        }

        #root-network-graph-container {
            margin-top: 20px;
        }

        .custom-popup#root-node-popup {
            position: absolute;
            background-color: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            padding: 15px;
            box-shadow: 0 8px 36px black;
            display: none;
            border-radius: var(--border-radius);
            word-wrap: break-word;
            font-size: 1.1rem !important;
        }

        .custom-popup#root-node-popup h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--color-accent-dark);
            font-family: var(--font-arabic);
            font-size: 1.5rem;
        }

        .custom-popup#root-node-popup p {
            margin: 0;
            line-height: 2;
            font-size: 1.3rem;
        }

        #root-graph-placeholder {
            text-align: center;
            margin: 20px 0;
        }

        body.graph-fullscreen-active {
            overflow: hidden;
        }

        #root-network-graph-container.fullscreen-graph {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 1500;
            background-color: var(--color-bg-primary);
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: stretch;
        }

        #root-network-graph-container.fullscreen-graph #root-network-graph {
            flex-grow: 1;
            border: none;
            width: 100%;
            height: 100%;
        }

        .graph-fullscreen-close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 1501;
            padding: 8px 15px;
            font-size: 1.2rem;
            background-color: var(--color-error);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
        }

        #root-network-graph-container.fullscreen-graph #root-graph-pagination-controls {
            position: absolute;
            bottom: 10px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(var(--color-bg-secondary-rgb, 200, 230, 201), 0.9);
            padding: 5px 10px;
            border-radius: var(--border-radius);
            z-index: 1501;
        }

        :root {
            --color-bg-secondary-rgb: 200, 230, 201;
        }

        body.theme-manuscript {
            --color-bg-secondary-rgb: 255, 248, 220;
        }

        body.theme-holo {
            --color-bg-secondary-rgb: 26, 43, 60;
        }

        div#root-node-popup {
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            font-family: var(--font-arabic) !important;
            direction: rtl !important;
            text-align: right !important;
            z-index: 214748364799999999999999999999999999999999999999999999999999999999999999 !important;
        }

        button#launchFullScreenReaderBtnEnhanced {
            position: absolute;
            right: 23px;
            top: 6px;
        }

        button#quitGameButton {
            position: fixed;
            bottom: 1px;
            left: 3px;
        }

        #fsReaderSettingsPanel>div:nth-child(2)>label:nth-child(7) {
            display: none;
        }

        span.item-surah-ayah.theme-modal-ayah-link {
            cursor: pointer;
        }

        #hamburger-btn {
            display: none;
            position: relative;
            width: 30px;
            height: 22px;
            background: none;
            border: none;
            cursor: pointer;
            z-index: 1010;
            margin-right: 15px;
        }

        #hamburger-btn span {
            display: block;
            position: absolute;
            height: 3px;
            width: 100%;
            background: var(--color-text-primary);
            border-radius: 2px;
            left: 0;
        }

        #hamburger-btn span:nth-child(1) {
            top: 0px;
        }

        #hamburger-btn span:nth-child(2) {
            top: 9px;
        }

        #hamburger-btn span:nth-child(3) {
            top: 18px;
        }

        #sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        @media (max-width: 768px) {
            #hamburger-btn {
                display: block !important;
            }

            .sidebar {
                display: none !important;
            }

            body.sidebar-open .sidebar {
                display: block !important;
                position: fixed;
                left: 0;
                top: 0;
                width: 280px;
                height: 100%;
                z-index: 1000;
                margin: 0;
                overflow-y: auto;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
                background-color: var(--color-bg-secondary);
            }

            body.sidebar-open #sidebar-overlay {
                display: block !important;
            }
        }

        button#fsReaderBookmarkBtn {
            margin-right: 3px;
        }

        .tajweed.ham_wasl,
        .tajweed.slnt,
        .tajweed.laam_shamsiyah {
            color: #AAAAAA;
        }

        .tajweed.madda_normal {
            color: #537FFF;
        }

        .tajweed.madda_permissible {
            color: #4050FF;
        }

        .tajweed.madda_necessary {
            color: #0000FF;
        }

        .tajweed.qalqalah {
            color: #DD0000;
        }

        .tajweed.madda_obligatory {
            color: #FF0000;
        }

        .tajweed.ikhafa_shafawi {
            color: #D500B4;
        }

        .tajweed.ikhafa {
            color: #9400A8;
        }

        .tajweed.idgham_shafawi {
            color: #58B800;
        }

        .tajweed.idgham_wo_ghunnah {
            color: #169200;
        }

        .tajweed.idgham_ghunnah {
            color: #169200;
        }

        .tajweed.iqlab {
            color: #26BFFD;
        }

        .tajweed.ghunnah {
            color: #FF7D7D;
        }

        span.end {
            display: none;
        }

        @font-face {
            font-family: "KFGQPC Uthman Taha Naskh";
            src: url("https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.eot");
            src: url("https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.eot?#iefix")format("embedded-opentype"),
                url("https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.woff2")format("woff2"),
                url("https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.woff")format("woff"),
                url("https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.ttf")format("truetype"),
                url("https://db.onlinewebfonts.com/t/f0034555c887b9dbfb67c6fd228d8f33.svg#KFGQPC Uthman Taha Naskh")format("svg");
        }

        .word-by-word-options {
            margin-top: 20px;
        }

        #wordByWordLanguageOptions {
            padding-left: 25px;
            margin-top: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .word-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 1.2em;
        }

        .word-table th,
        .word-table td {
            border: 1px solid var(--color-border);
            padding: 8px 12px;
            text-align: center;
            vertical-align: middle;
        }

        .word-table th {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-secondary);
            font-weight: bold;
        }

        .word-table .arabic-word-cell {
            font-family: var(--font-arabic);
            font-size: 1.5em;
            direction: rtl;
        }

        button.generate-image-btn {
            padding: 5px;
            margin-top: 18px;
        }

        .theme-view-content {
            display: none;
        }

        .theme-view-content.active-view {
            display: block !important;
        }

        .graph-controls-container {
            flex-wrap: wrap;
        }

        .ayah {
            position: relative !important;
            overflow: visible !important;
        }

        .ayah-actions-toggle {
            display: none;
            position: absolute;
            top: 2px;
            left: 45px;
            font-size: 1.2rem;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid var(--color-border);
            background-color: var(--color-bg-primary);
            color: var(--color-text-secondary);
            cursor: pointer;
            z-index: 11;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
            transition: background-color 0.2s;
        }

        .ayah-quick-actions {
            position: absolute;
            top: 8px;
            left: 2px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            background-color: rgba(var(--color-bg-secondary-rgb), 0.8);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            padding: 6px;
            border-radius: var(--border-radius);
            z-index: 10;
            transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out;
            transform: translateX(-120%);
            opacity: 0;
            pointer-events: none;
        }

        .ayah-quick-actions.visible {
            transform: translateX(0) !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        @media (hover: hover) and (min-width: 769px) {
            .ayah:hover .ayah-quick-actions {
                transform: translateX(0);
                opacity: 1;
                pointer-events: auto;
            }
        }

        @media (max-width: 768px) {
            .ayah-actions-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .ayah:hover .ayah-quick-actions {
                transform: translateX(-120%);
                opacity: 0;
                pointer-events: none;
            }
        }

        .action-icon {
            font-size: 1.2rem;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--color-border);
            border-radius: 50%;
            background-color: var(--color-bg-primary);
            color: var(--color-text-secondary);
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s, transform 0.2s;
        }

        .action-icon:hover {
            background-color: var(--color-accent);
            color: white;
            transform: scale(1.1);
        }

        .theme-synthesis-btn {
            padding: 3px 8px !important;
            font-size: 0.8rem !important;
            margin-left: 8px !important;
            background-color: var(--color-accent-dark) !important;
        }

        .synthesis-entry {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--color-border);
        }

        .synthesis-entry:last-child {
            border-bottom: none;
        }

        .synthesis-ayah-ref {
            font-weight: bold;
            font-size: 1.2em;
            color: var(--color-text-secondary);
            margin-bottom: 10px;
        }

        .synthesis-ayah-text {
            font-family: var(--font-arabic);
            font-size: 1.8em;
            line-height: 2;
            text-align: right;
            direction: rtl;
            background-color: var(--color-bg-primary);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 15px;
        }

        .synthesis-tafsir-heading {
            font-weight: bold;
            color: var(--color-text-secondary);
            margin-top: 20px;
            margin-bottom: 5px;
        }

        .synthesis-tafsir-notes {
            font-family: var(--font-general);
            font-size: 1.1em;
            line-height: 1.7;
            white-space: pre-wrap;
            padding-left: 15px;
            border-left: 3px solid var(--color-accent);
        }

        @media print {
            body>*:not(#synthesisModalContent) {
                display: none;
            }

            #synthesisModalContent,
            #synthesisModalContent * {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                box-shadow: none !important;
                border: none !important;
                background-color: white !important;
                color: black !important;
            }

            .synthesis-ayah-text {
                font-size: 22pt !important;
            }

            .synthesis-tafsir-notes {
                font-size: 12pt !important;
            }
        }

        #commandPaletteResults {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 350px;
            overflow-y: auto;
            width: 370px;
        }

        .cp-result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            cursor: pointer;
            border-radius: var(--border-radius);
            transition: background-color 0.15s, color 0.15s;
            font-size: 1rem;
            background: #007800;
            color: white;
            margin-top: 1px;
            border-top: 1px solid #03ff0d;
        }

        .cp-result-item.active {
            background-color: var(--color-accent);
            color: white;
        }

        /* .cp-result-item:hover {
    background-color: var(--color-accent);
    color: white;
} */
        .cp-result-item .category {
            font-size: 0.8em;
            opacity: 0.7;
        }

        .cp-result-item.active .category {
            opacity: 1;
        }

        #login-modal .modal-content,
        #register-modal .modal-content,
        #admin-modal .modal-content {
            max-width: 450px;
        }

        #login-modal,
        #register-modal,
        #admin-modal {
            z-index: 100000;
        }

        #admin-modal .modal-content {
            max-width: 900px;
        }

        .user-list {
            list-style: none;
            padding: 0;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
        }

        .user-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px dotted var(--color-border);
        }

        .user-list li:last-child {
            border-bottom: none;
        }

        .user-list .user-info {
            flex-grow: 1;
            text-align: left;
        }

        .user-list .user-actions {
            display: flex;
            gap: 5px;
        }

        textarea#admin-translation-text {
            font-size: large;
        }

        button {
            margin-bottom: 8px;
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Mirza:wght@700&display=swap');
    </style>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Orbitron&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lateef&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&display=swap">
</head>

<body dir="ltr">
    <div id="sidebar-overlay"></div>
    <div id="loading-overlay">
        <div class="loading-content">
            <div id="loading-message-primary">Loading Quran data...</div>
            <div id="loading-message-secondary">Initializing...</div>
            <div id="loading-progress-bar-container">
                <div id="loading-progress-bar" style="width: 0%;"></div>
            </div>
            <div id="loading-percentage">0%</div>
            <div id="loading-first-time-notice" style="font-size: 0.85em; margin-top: 15px;">
                (Loads slow first time or after update. After that, it opens quickly.)
                <br />
                <span dir="rtl">
                    (پہلی بار یا اپ ڈیٹ کے بعد ایپ تھوڑا وقت لے گا، اگلی بار فوراً کھلے گا۔)
                </span>
            </div>
        </div>
    </div>
    <header>
        <button id="hamburger-btn" aria-label="Open menu"><span></span><span></span><span></span></button>
        <h1>Nur-Ul-Quran Offline</h1>
        <div class="header-controls">
            <?php if (is_logged_in()): ?>
                <span style="margin-right: 15px;">Welcome, <?= htmlspecialchars($_SESSION['username']); ?>! (<?= htmlspecialchars($_SESSION['role']); ?>)</span>
                <?php if (get_user_role() === 'admin'): ?>
                    <button id="admin-panel-btn" style="margin-right: 15px;">Admin</button>
                <?php endif; ?>
                <a href="?action=logout" class="button">Logout</a>
            <?php else: ?>
                <button id="login-btn" style="margin-right: 10px;">Login</button>
                <button id="register-btn">Register</button>
            <?php endif; ?>
            <label for="theme-switcher" class="sr-only">Choose Theme</label>
            <select id="theme-switcher" aria-label="Choose Theme" style="margin-left: 15px;">
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
                    <li><a href="#community" class="nav-link" data-section="community">Community Content</a></li>
                    <li><a href="#quran" class="nav-link active" data-section="quran">Quran Viewer</a></li>
                    <?php if (is_logged_in()): ?>
                        <li><a href="#tafsir" class="nav-link" data-section="tafsir">Personal Tafsir</a></li>
                        <li><a href="#themes" class="nav-link" data-section="themes">Thematic Linker</a></li>
                        <li><a href="#roots" class="nav-link" data-section="roots">Root Word Analyzer</a></li>
                        <li><a href="#recitation" class="nav-link" data-section="recitation">Recitation Log</a></li>
                        <li><a href="#hifz" class="nav-link" data-section="hifz">Memorization Hub</a></li>
                        <li><a href="#goals" class="nav-link" data-section="goals">My Goals</a></li>
                        <li><a href="#reporting" class="nav-link" data-section="reporting">Reporting</a></li>
                        <li><a href="#search" class="nav-link" data-section="search">Advanced Search</a></li>
                        <li><a href="#data" class="nav-link" data-section="data">Data Management</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </aside>
        <main class="main-content">
            <section id="quran" class="section active" role="region" aria-labelledby="quran-heading">
                <h2 id="quran-heading">Quran Viewer</h2>
                <div class="quran-controls flex-group mb-20">
                    <label for="surah-select" class="sr-only">Select Surah</label>
                    <select id="surah-select" aria-label="Select Surah"></select>
                    <label for="ayah-select" class="sr-only">Select Ayah</label>
                    <select id="ayah-select" aria-label="Select Ayah"></select>
                </div>
                <div class="quran-controls flex-group mb-20">
                    <label>View Mode:</label>
                    <label><input type="radio" name="quran-view-mode" value="single" checked> Single Ayah</label>
                    <label><input type="radio" name="quran-view-mode" value="continuous"> Continuous</label>
                </div>
                <div class="quran-controls flex-group mb-20">
                    <label for="translation-select">Select Translation:</label>
                    <select id="translation-select" aria-label="Select Translation">
                        <option value="urdu">Urdu (Included)</option>
                        <option value="english">English (Included)</option>
                        <option value="Bangali">Bangali (Included)</option>
                        <option value="pashto">Pashto (Included)</option>
                    </select>
                </div>
                <div id="quran-display" class="quran-viewer" lang="ar" dir="rtl">
                    <p class="text-center">Select a Surah and Ayah to start.</p>
                </div>
                <div id="quran-continuous-display" class="quran-viewer" style="display:none;" lang="ar" dir="rtl"></div>
                <div id="word-translation-area" class="mt-20">
                    <p class="text-center">Click on an Arabic word to see its translation.</p>
                </div>
                <?php if (get_user_role() === 'admin' || get_user_role() === 'registered'): ?>
                    <div class="quran-controls flex-group mb-20">
                        <select id="admin-translation-lang" aria-label="Select language to edit">
                            <option value="urdu">Urdu</option>
                            <option value="english">English</option>
                            <option value="Bangali">Bangali</option>
                            <option value="pashto">Pashto</option>
                        </select>
                    </div>
                    <div>
                        <label for="admin-translation-lang">Edit Translation:</label>
                        <textarea id="admin-translation-text" placeholder="Enter translation..."></textarea>
                        <button id="admin-save-translation-btn">Save Translation</button>
                    </div>
                <?php endif; ?>
            </section>
            </section>
            <section id="community" class="section" role="region" aria-labelledby="community-heading">
                <h2 id="community-heading">Featured Community Content</h2>
                <p>Explore Tafsir, themes, and notes shared by other users, approved by our admins.</p>

                <div class="report-section">
                    <h4>Public Tafsir Notes</h4>
                    <ul id="public-tafsir-list" class="report-list">
                        <li>Loading...</li>
                    </ul>
                </div>

                <div class="report-section">
                    <h4>Public Themes</h4>
                    <ul id="public-themes-list" class="report-list">
                        <li>Loading...</li>
                    </ul>
                </div>

                <div class="report-section">
                    <h4>Public Root Word Notes</h4>
                    <ul id="public-roots-list" class="report-list">
                        <li>Loading...</li>
                    </ul>
                </div>
            </section>
            <?php if (is_logged_in()): ?>
                <section id="tafsir" class="section" role="region" aria-labelledby="tafsir-heading">
                    <h2 id="tafsir-heading">Personal Tafsir Builder</h2>
                    <p>Write your notes and reflections for the current Ayah.</p>
                    <div id="current-ayah-tafsir" class="ayah mb-20">
                        <p class="text-center">Navigate to an Ayah in the Quran Viewer to add Tafsir.</p>
                    </div>
                    <div class="tafsir-editor">
                        <label for="tafsir-notes">Your Tafsir Notes:</label>
                        <textarea id="tafsir-notes"
                            placeholder="Enter your personal notes, interpretations, and reflections here..."></textarea>
                        <button id="save-tafsir-btn">Save Tafsir</button>
                        <p id="tafsir-status" aria-live="polite"></p>
                    </div>
                </section>
                <section id="themes" class="section" role="region" aria-labelledby="themes-heading">
                    <h2 id="themes-heading">Thematic Linker Pro</h2>
                    <div class="view-switcher mb-10 flex-group" style="justify-content: flex-start;">
                        <label style="margin-bottom:0;">View Mode: </label>
                        <div>
                            <input type="radio" id="theme-view-form" name="theme-view-mode" value="form" checked>
                            <label for="theme-view-form" style="margin-right: 10px; font-weight:normal;">Forms &
                                Lists</label>
                        </div>
                        <div>
                            <input type="radio" id="theme-view-graph" name="theme-view-mode" value="graph">
                            <label for="theme-view-graph" style="font-weight:normal;">Graph View</label>
                        </div>
                    </div>
                    <div id="theme-form-view" class="theme-view-content active-view">
                        <p>Create and manage themes, and link Ayahs to them.</p>
                        <div class="theme-manager mb-20">
                            <h3>Manage Themes</h3>
                            <div class="flex-group mb-10">
                                <label for="new-theme-name" class="sr-only">New Theme Name</label>
                                <input type="text" id="new-theme-name" placeholder="New Theme Name">
                                <label for="parent-theme-select" class="sr-only">Parent Theme (Optional)</label>
                                <select id="parent-theme-select" aria-label="Parent Theme (Optional)">
                                    <option value="">-- No Parent --</option>
                                </select>
                                <button id="add-theme-btn">Add Theme</button>
                            </div>
                            <div class="theme-list">
                                <h4>Existing Themes</h4>
                                <ul id="themes-list">
                                    <li>No themes added yet.</li>
                                </ul>
                            </div>
                            <p id="theme-manager-status" aria-live="polite"></p>
                        </div>
                        <div class="theme-linker">
                            <h3>Link Current Ayah (<span id="current-ayah-theme-ref">N/A</span>)</h3>
                            <div id="current-ayah-theme-text" class="ayah mb-20">
                                <p class="text-center">Navigate to an Ayah in the Quran Viewer to link themes.</p>
                            </div>
                            <label for="link-theme-select">Select Theme to Link:</label>
                            <select id="link-theme-select" aria-label="Select Theme to Link">
                                <option value="">-- Select Theme --</option>
                            </select>
                            <label for="theme-link-notes">Notes for this link (Optional):</label>
                            <textarea id="theme-link-notes"
                                placeholder="Notes on why this Ayah relates to this theme..."></textarea>
                            <button id="link-ayah-to-theme-btn">Link Ayah</button>
                            <p id="theme-linker-status" aria-live="polite"></p>
                            <h4 class="mt-20">Ayahs Linked to Selected Theme: <span id="linked-theme-name">N/A</span></h4>
                            <ul id="linked-ayahs-list">
                                <li>Select a theme above to see linked ayahs.</li>
                            </ul>
                        </div>
                    </div>
                    <div id="theme-graph-view" class="theme-view-content" style="display: none;">
                        <div class="graph-controls-container mb-10 flex-group"
                            style="justify-content: space-between; align-items: center; background-color: var(--color-bg-secondary); padding: 10px; border-radius: var(--border-radius);">
                            <div>
                                <label for="themeGraphFilterSelect" style="margin-bottom: 5px;">Filter by Theme:</label>
                                <select id="themeGraphFilterSelect">
                                    <option value="all">Show All Themes</option>
                                </select>
                            </div>
                            <div>
                                <label for="themeGraphLayoutSelect" style="margin-bottom: 5px;">Graph Layout:</label>
                                <select id="themeGraphLayoutSelect">
                                    <option value="barnesHut">Force-Directed (Default)</option>
                                    <option value="hierarchical-UD">Hierarchical (Top-Down)</option>
                                    <option value="hierarchical-LR">Hierarchical (Left-Right)</option>
                                    <option value="improvedLayout">Improved Layout</option>
                                </select>
                            </div>
                            <button id="regenerateThemeGraphBtn" style="align-self: flex-end; display: none;">Apply</button>
                        </div>
                        <div id="theme-network-graph"
                            style="width: 100%; height: 600px; border: 1px solid var(--color-border);">
                        </div>
                    </div>
                </section>
                <section id="roots" class="section" role="region" aria-labelledby="roots-heading">
                    <h2 id="roots-heading">Root Word Analyzer & Concordance</h2>
                    <p>Input an Arabic root word to find occurrences in the Quran.</p>
                    <div class="root-analyzer-form mb-20">
                        <div class="flex-group mb-10">
                            <label for="root-input" class="sr-only">Arabic Root Word</label>
                            <input type="text" id="root-input"
                                placeholder="Enter Arabic Root (e.g., ق-و-ل) or (ع ل م) or (ر۔ب)" lang="ar" dir="rtl">
                            <button id="analyze-root-btn">Analyze Root</button>
                        </div>
                        <label for="root-description">Description/Notes for this Root (Optional):</label>
                        <textarea id="root-description" placeholder="Your notes on this root's meaning..."></textarea>
                        <button id="save-root-notes-btn">Save Root Notes</button>
                        <p id="root-status" aria-live="polite"></p>
                    </div>
                    <div class="view-switcher mb-10 flex-group" style="justify-content: flex-start;">
                        <label style="margin-bottom:0;">View Mode: </label>
                        <div>
                            <input type="radio" id="root-view-list" name="root-view-mode" value="list" checked>
                            <label for="root-view-list" style="margin-right: 10px; font-weight:normal;">List</label>
                        </div>
                        <div>
                            <input type="radio" id="root-view-tree" name="root-view-mode" value="tree">
                            <label for="root-view-tree" style="font-weight:normal;">Tree Graph</label>
                        </div>
                    </div>
                    <div class="root-results">
                        <h3>Occurrences Found for: <span id="analyzed-root-term">N/A</span></h3>
                        <ul id="root-occurrences-list" class="root-view-content active-view">
                            <li>Enter a root word and click "Analyze Root".</li>
                        </ul>
                        <div id="root-network-graph-container" class="root-view-content" style="display: none;">
                            <p id="root-graph-placeholder" class="text-center" style="display:none; margin: 20px 0;">Graph
                                will appear here after analysis.</p>
                            <div id="root-network-graph"
                                style="width: 100%; height: 500px; border: 1px solid var(--color-border);"></div>
                            <div id="root-graph-pagination-controls" class="flex-group mt-10"
                                style="justify-content: center; display: none;">
                                <button id="prev-root-graph-page-btn" disabled>« Previous</button>
                                <span id="root-graph-page-info" style="margin: 0 15px; font-weight: normal;">Page 1 of
                                    1</span>
                                <button id="next-root-graph-page-btn" disabled>Next »</button>
                            </div>
                        </div>
                    </div>
                    <div id="root-node-popup" class="custom-popup"
                        style="font-family: var(--font-arabic); direction: rtl; text-align: right;">
                    </div>
                </section>
                <section id="recitation" class="section" role="region" aria-labelledby="recitation-heading">
                    <h2 id="recitation-heading">Comparative Recitation Log</h2>
                    <p>Log your listening sessions to different Qaris.</p>
                    <div class="recitation-log-form mb-20">
                        <h3>Add Log Entry</h3>
                        <div class="flex-group mb-10">
                            <label for="rec-surah-select" class="sr-only">Surah</label>
                            <select id="rec-surah-select" aria-label="Surah"></select>
                            <label for="rec-ayah-start" class="sr-only">Ayah Start</label>
                            <input type="number" id="rec-ayah-start" required placeholder="Ayah Start" min="1">
                            <label for="rec-ayah-end" class="sr-only">Ayah End</label>
                            <input type="number" id="rec-ayah-end" required placeholder="Ayah End" min="1">
                        </div>
                        <div class="flex-group mb-10">
                            <label for="rec-qari" class="sr-only">Qari/Source</label>
                            <input type="text" id="rec-qari"
                                placeholder="Qari or Source (e.g., Mishary Alafasy, Local Masjid Imam)">
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
                            <li>No entries logged yet.</li>
                        </ul>
                    </div>
                </section>
                <section id="hifz" class="section" role="region" aria-labelledby="hifz-heading">
                    <h2 id="hifz-heading">Memorization Hub</h2>
                    <p>Track your Hifz progress and review schedule.</p>
                    <div class="hifz-controls flex-group mb-20">
                        <label for="hifz-surah-select" class="sr-only">Select Surah for Hifz</label>
                        <select id="hifz-surah-select" aria-label="Select Surah for Hifz"></select>
                    </div>
                    <div id="hifz-ayahs-list">
                        <p class="text-center">Select a Surah to track Hifz progress.</p>
                    </div>
                    <p id="hifz-status" aria-live="polite"></p>
                </section>
                <section id="search" class="section" role="region" aria-labelledby="search-heading">
                    <h2 id="search-heading">Advanced Search</h2>
                    <p>Search across Quran text, translations, and your personal data.</p>
                    <div class="search-form mb-20">
                        <label for="search-input" class="sr-only">Search Term</label>
                        <input type="text" id="search-input" placeholder="Enter search term">
                        <div class="search-options mb-10" role="group" aria-label="Search Scope">
                            <label><input type="checkbox" class="search-scope" value="quran-arabic" checked> Quran
                                Arabic</label>
                            <label><input type="checkbox" class="search-scope" value="quran-translation" checked> Quran
                                Translation</label>
                            <label><input type="checkbox" class="search-scope" value="tafsir"> Personal Tafsir</label>
                            <label><input type="checkbox" class="search-scope" value="themes"> Theme Notes</label>
                            <label><input type="checkbox" class="search-scope" value="roots"> Root Notes</label>
                            <label><input type="checkbox" class="search-scope" value="recitation"> Recitation Notes</label>
                            <label><input type="checkbox" class="search-scope" value="hifz"> Hifz Notes</label>
                        </div>
                        <button id="perform-search-btn">Search</button>
                        <p id="search-status" aria-live="polite"></p>
                    </div>
                    <div class="search-results">
                        <h3>Search Results</h3>
                        <ul id="search-results-list">
                            <li>Enter a search term and click "Search".</li>
                        </ul>
                    </div>
                </section>
                <section id="data" class="section" role="region" aria-labelledby="data-heading">
                    <h2 id="data-heading">Data Management</h2>
                    <p>Manage your personal data (Tafsir, Themes, Roots, Logs, Hifz).</p>
                    <div class="settings-section mb-20">
                        <h3>Backup Data</h3>
                        <p>Export your personal data as a JSON file.</p>
                        <button id="export-data-btn">Export Data</button>
                        <p id="export-status" aria-live="polite"></p>
                    </div>
                    <div class="settings-section mb-20">
                        <h3>Restore Data</h3>
                        <p>Import your personal data from a JSON file. This will overwrite existing data.</p>
                        <label for="import-file" class="sr-only">Choose JSON file to import</label>
                        <input type="file" id="import-file" accept="application/json">
                        <button id="import-data-btn" disabled>Import Data</button>
                        <p id="import-status" aria-live="polite"></p>
                    </div>
                    <div class="settings-section">
                        <h3>Clear All Personal Data</h3>
                        <p class="mb-10" style="color: var(--color-error);">Warning: This will permanently delete ALL your
                            personal Tafsir, Themes, Roots, Logs, and Hifz data.</p>
                        <button id="clear-data-btn" style="background-color: var(--color-error);">Clear All Data</button>
                        <p id="clear-status" aria-live="polite"></p>
                    </div>
                    <div class="settings-section mb-20">
                        <h3>Export Personal Tafsir</h3>
                        <p>Generate a file of all your Tafsir notes for backup or printing.</p>
                        <div class="flex-group" style="justify-content: flex-start; flex-wrap: wrap;">
                            <button id="export-tafsir-to-docx-btn">Export to .docx</button>
                            <button id="export-tafsir-to-md-btn">Export to Markdown</button>
                            <button id="export-tafsir-to-pdf-btn">Export to PDF</button>
                        </div>
                        <p id="export-tafsir-docx-status" aria-live="polite"></p>
                        <p id="export-tafsir-md-status" aria-live="polite"></p>
                        <p id="export-tafsir-pdf-status" aria-live="polite"></p>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <div id="login-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Login</h2>
            <?php if (!empty($login_message)): ?>
                <p class="form-message" style="color: red;"><?= $login_message; ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                <label for="login-username">Username:</label>
                <input type="text" id="login-username" name="username" required>
                <label for="login-password">Password:</label>
                <input type="password" id="login-password" name="password" required>
                <button type="submit">Login</button>
            </form>
            <p style="margin-top: 15px;">Don't have an account? <a href="#" id="open-register-from-login">Register here</a>.</p>
        </div>
    </div>
    <div id="register-modal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <h2>Register</h2>
            <?php if (!empty($register_message)): ?>
                <p class="form-message" style="color: <?= (strpos($register_message, 'successful') !== false) ? 'green' : 'red'; ?>;"><?= $register_message; ?></p>
            <?php endif; ?>
            <form method="POST" action="">
                <input type="hidden" name="action" value="register">
                <label for="register-username">Username:</label>
                <input type="text" id="register-username" name="username" required>
                <label for="register-email">Email:</label>
                <input type="email" id="register-email" name="email" required>
                <label for="register-password">Password:</label>
                <input type="password" id="register-password" name="password" required>
                <label for="register-confirm-password">Confirm Password:</label>
                <input type="password" id="register-confirm-password" name="confirm_password" required>
                <button type="submit">Register</button>
            </form>
            <p style="margin-top: 15px;">Already have an account? <a href="#" id="open-login-from-register">Login here</a>.</p>
        </div>
    </div>
    <?php if (get_user_role() === 'admin'): ?>
        <div id="admin-modal" class="modal">
            <div class="modal-content">
                <span class="close-button">&times;</span>
                <h2>Admin Panel</h2>
                <div class="admin-tabs">
                    <button class="admin-tab active" data-tab="users">User Management</button>
                    <button class="admin-tab" data-tab="translations">Translation Review</button>
                </div>
                <div id="admin-tab-users" class="admin-tab-content active">
                    <h3>Manage Users</h3>
                    <ul id="user-list" class="user-list">
                        <li>Loading users...</li>
                    </ul>
                </div>
                <div id="admin-tab-translations" class="admin-tab-content">
                    <h3>Review Word Translations</h3>
                    <div class="flex-group mb-10">
                        <label for="review-word-id">Word ID:</label>
                        <input type="number" id="review-word-id" placeholder="Enter Word ID">
                        <button id="load-word-for-review">Load Word</button>
                    </div>
                    <div id="review-word-details" style="display:none; border-top: 1px solid var(--color-border); padding-top: 15px;">
                        <h4 style="margin-bottom:10px;">Arabic Word: <span id="review-arabic-word"></span></h4>
                        <div id="translation-review-languages">
                        </div>
                        <p id="review-status" aria-live="polite"></p>
                    </div>
                    <h3>Review Line-by-Line Translations</h3>
                    <div class="flex-group mb-10">
                        <label for="review-ayah-surah">Surah:</label>
                        <select id="review-ayah-surah"></select>
                        <label for="review-ayah-ayah">Ayah:</label>
                        <select id="review-ayah-ayah"></select>
                        <button id="load-ayah-for-line-review">Load Ayah</button>
                    </div>
                    <div id="review-ayah-line-details" style="display:none; border-top: 1px solid var(--color-border); padding-top: 15px;">
                        <h4 style="margin-bottom:10px;">Arabic: <span id="review-ayah-arabic"></span></h4>
                        <div id="line-translation-review-languages">
                        </div>
                        <p id="line-review-status" aria-live="polite"></p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div id="themeSynthesisModal" class="modal">
        <div class="modal-content" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
            <span class="close-button" onclick="this.parentElement.parentElement.style.display='none'">×</span>
            <h3 id="synthesisModalTitle" style="flex-shrink: 0;">Synthesis for Theme: ...</h3>
            <div id="synthesisModalContent" style="overflow-y: auto; flex-grow: 1; padding-right: 15px;">
            </div>
            <div id="synthesisModalActions"
                style="flex-shrink: 0; padding-top: 15px; border-top: 1px solid var(--color-border); text-align: right;">
                <button id="synthesisPrintBtn">Print / Save as PDF</button>
            </div>
        </div>
    </div>
    <div id="tafsirQuickModal" class="modal quick-action-modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h4>Tafsir/Notes for <span class="modal-ayah-ref"></span></h4>
            <div class="ayah-context"></div>
            <div class="form-group">
                <label for="quickTafsirText">Your Notes:</label>
                <textarea id="quickTafsirText" rows="8" style="width: 100%;"></textarea>
            </div>
            <button id="saveQuickTafsirBtn">Save Notes</button>
            <p id="quickTafsirStatus" aria-live="polite" style="margin-top:10px;"></p>
        </div>
    </div>
    <div id="themeQuickModal" class="modal quick-action-modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h4>Theme Links for <span class="modal-ayah-ref"></span></h4>
            <div class="ayah-context"></div>
            <div class="form-group">
                <label>Link to Theme(s):</label>
                <select id="quickThemeSelect" multiple style="width: 100%; height: 150px;"></select>
            </div>
            <button id="saveQuickThemeBtn">Save Links</button>
        </div>
    </div>
    <div id="recitationQuickModal" class="modal quick-action-modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h4>Log Recitation for <span class="modal-ayah-ref"></span></h4>
            <div class="ayah-context"></div>
            <div class="form-group">
                <label for="quickRecitationQari">Qari/Source:</label>
                <input type="text" id="quickRecitationQari" style="width: 100%;">
            </div>
            <div class="form-group">
                <label for="quickRecitationNotes">Notes (Tajweed, Style, etc.):</label>
                <textarea id="quickRecitationNotes" rows="4" style="width: 100%;"></textarea>
            </div>
            <button id="saveQuickRecitationBtn">Log this Recitation</button>
        </div>
    </div>
    <div id="hifzQuickModal" class="modal quick-action-modal">
        <div class="modal-content">
            <span class="close-button">×</span>
            <h4>Memorization Status for <span class="modal-ayah-ref"></span></h4>
            <div class="ayah-context"></div>
            <p style="text-align:center;">Current Status: <strong id="currentHifzStatus"></strong></p>
            <div id="quickHifzStatusButtons" class="form-group">
                <button data-status="not-started">Not Started</button>
                <button data-status="in-progress">In Progress</button>
                <button data-status="memorized">Memorized</button>
            </div>
            <p id="quickHifzStatus" aria-live="polite" style="text-align:center;"></p>
        </div>
    </div>
    <div id="ayahImageModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="ayahImageModalTitle">
        <div class="modal-content">
            <span class="close-button" onclick="this.parentElement.parentElement.style.display='none'">×</span>
            <h3 id="ayahImageModalTitle">Generate Ayah Image</h3>
            <p><strong>Step 1:</strong> Select which full-ayah translations to include:</p>
            <div id="imageTranslationOptions">
            </div>
            <div class="word-by-word-options">
                <p><strong>Step 2:</strong> Add a word-by-word translation table (optional):</p>
                <label>
                    <input type="checkbox" id="includeWordByWordTable"> Include Word-by-Word Table
                </label>
                <div id="wordByWordLanguageOptions" style="display: none;">
                    <p style="margin-bottom: 5px; font-size: 0.9em;">Select languages for the table:</p>
                </div>
            </div>
            <button id="createImageBtn" style="margin-top: 25px;">Create & Download Image</button>
        </div>
    </div>
    <div id="themeAyahsModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="themeAyahsModalTitle">
        <div class="modal-content">
            <span class="close-button" aria-label="Close Theme Ayahs Modal">&times;</span>
            <h3 id="themeAyahsModalTitle">Ayahs Linked to Theme: <span id="modal-theme-name"></span></h3>
            <ul id="modal-linked-ayahs-list">
            </ul>
        </div>
    </div>
    <div id="rootOccurrencesModal" class="modal" role="dialog" aria-modal="true"
        aria-labelledby="rootOccurrencesModalTitle">
        <div class="modal-content">
            <span class="close-button" aria-label="Close Root Occurrences Modal">&times;</span>
            <h3 id="rootOccurrencesModalTitle">Occurrences for Root: <span id="modal-root-term"></span></h3>
            <ul id="modal-root-occurrences-list">
            </ul>
        </div>
    </div>
    <div id="commandPalette" class="modal" role="dialog" aria-modal="true" aria-labelledby="commandPaletteLabel">
        <div class="command-palette-content">
            <label for="commandPaletteInput" id="commandPaletteLabel" class="sr-only">Type a command or
                search...</label>
            <input type="text" id="commandPaletteInput" placeholder="Go to Surah, Export Data, Start Game...">
            <ul id="commandPaletteResults"></ul>
        </div>
    </div>
    <script>
        const DB_NAME_PLACEHOLDER = 'NurAlQuranStudioDBz';
        const DB_VERSION_PLACEHOLDER = 8;
        const STORE_QURAN_PLACEHOLDER = 'quran';
        const STORE_TAFSIR_PLACEHOLDER = 'tafsir';
        const STORE_THEMES_PLACEHOLDER = 'themes';
        const STORE_THEME_AYAHS_PLACEHOLDER = 'theme_ayahs';
        const STORE_ROOTS_PLACEHOLDER = 'roots';
        const STORE_ROOT_AYAHS_PLACEHOLDER = 'root_ayahs';
        const STORE_RECITATIONS_PLACEHOLDER = 'recitations';
        const STORE_HIFZ_PLACEHOLDER = 'hifz';
        const STORE_SETTINGS_PLACEHOLDER = 'settings';
        const STORE_WORD_TRANSLATIONS_PLACEHOLDER = 'word_translations';
        const STORE_WORD_METADATA_PLACEHOLDER = 'word_metadata';
        const ajax_url = '<?php echo $_SERVER['PHP_SELF']; ?>';
        let rootNetwork = null;
        let rootNodePopupEl = null;
        let activeRootNodeIdForPopup = null;
        let allRootOccurrencesCache = [];
        let currentRootGraphPage = 1;
        const rootGraphItemsPerPage = 20;
        let totalProgressUnits = 0;
        let completedProgressUnits = 0;
        let currentSurah = 1;
        let currentAyah = 1;
        let totalAyahsInSurah = 7;
        let quranDataLoaded = false;
        const surahNames = <?php echo json_encode($surah_names); ?>;
        const surahAyahCounts = <?php echo json_encode($surah_ayah_counts); ?>;
        const juzBoundariesData = <?php echo json_encode($juz_boundaries_data); ?>;
        const staticQuranicThemes = <?php echo json_encode($static_quranic_themes); ?>;
        const allLanguagesConfig = <?php echo json_encode($translation_config); ?>;
        const isUserLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;
        const userRole = '<?php echo get_user_role(); ?>';
        const currentUserId = <?php echo get_user_id(); ?>;
        const juzStartSurahs = [0, 1, 2, 2, 3, 4, 4, 5, 6, 7, 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 20, 21, 22, 23, 25, 26, 27, 29, 33, 36, 39, 41, 46, 51, 58, 67, 78];
        const juzStartAyahs = [0, 1, 142, 253, 93, 24, 148, 82, 111, 41, 88, 93, 6, 53, 1, 1, 75, 1, 1, 56, 47, 31, 28, 36, 46, 60, 31, 1, 31, 28, 22, 47, 1, 31, 1, 1, 1];

        function getJuzFromSurahAyah(surah, ayah) {
            if (isNaN(surah) || isNaN(ayah) || surah < 1 || ayah < 1) return 1;
            for (let i = juzBoundariesData.length - 1; i >= 0; i--) {
                const boundary = juzBoundariesData[i];
                if (surah > boundary.startSurah || (surah === boundary.startSurah && ayah >= boundary.startAyah)) {
                    return boundary.juz;
                }
            }
            return 1;
        }
        async function sendAjaxRequest(action, data) {
            const formData = new FormData();
            formData.append('action', action);
            for (const key in data) {
                formData.append(key, data[key]);
            }
            try {
                const response = await fetch(ajax_url, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();
                if (result.success === false) {
                    console.error("AJAX error for action:", action, result.message);
                }
                return result;
            } catch (error) {
                console.error("Network or parsing error for action:", action, error);
                return {
                    success: false,
                    message: error.message
                };
            }
        }
        async function getData(storeName, key) {
            let action;
            let params = {};
            switch (storeName) {
                case 'quran_ayahs':
                    action = 'load_quran_ayah';
                    params.surah = key[0];
                    params.ayah = key[1];
                    break;
                case 'tafsir':
                    action = 'get_tafsir';
                    params.surah = key[0];
                    params.ayah = key[1];
                    break;
                case 'themes':
                    action = 'get_theme';
                    params.theme_id = key;
                    break;
                case 'theme_ayahs':
                    action = 'get_theme_ayah_link';
                    params.link_id = key;
                    break;
                case 'root_words':
                    action = 'analyze_root';
                    params.root_term = key;
                    break;
                case 'recitations':
                    action = 'get_recitation_log';
                    params.log_id = key;
                    break;
                case 'hifz':
                    action = 'get_hifz_for_ayah';
                    params.surah = key[0];
                    params.ayah = key[1];
                    break;
                case 'settings':
                    action = 'get_setting';
                    params.name = key;
                    break;
                case 'word_translations':
                    action = 'get_word_translation';
                    params.word_id = key;
                    break;
                case 'word_metadata':
                    action = 'get_word_metadata';
                    params.word_id = key;
                    break;
                case 'goals':
                    action = 'get_goal';
                    params.id = key;
                    break;
                default:
                    console.warn(`getData: Unrecognized store name: ${storeName}`);
                    return null;
            }
            const result = await sendAjaxRequest(action, params);
            return result.success ? result.data || result.notes || result.value : null;
        }
        async function getAllData(storeName, indexName = null, query = null) {
            let action;
            let params = {};
            switch (storeName) {
                case 'quran_ayahs':
                    action = 'get_all_quran_ayahs';
                    break;
                case 'tafsir':
                    action = 'get_all_tafsir';
                    break;
                case 'themes':
                    action = 'get_all_themes';
                    break;
                case 'theme_ayahs':
                    action = 'get_all_theme_ayahs';
                    break;
                case 'root_words':
                    action = 'get_all_roots';
                    break;
                case 'recitations':
                    action = 'get_all_recitations';
                    break;
                case 'hifz':
                    action = 'get_all_hifz';
                    break;
                case 'settings':
                    action = 'get_all_settings';
                    break;
                case 'word_translations':
                    action = 'get_all_word_translations';
                    break;
                case 'word_metadata':
                    action = 'get_all_word_metadata';
                    break;
                case 'goals':
                    action = 'get_all_goals';
                    break;
                default:
                    console.warn(`getAllData: Unrecognized store name: ${storeName}`);
                    return [];
            }
            const result = await sendAjaxRequest(action, params);
            return result.success ? result.data : [];
        }
        async function addData(storeName, data) {
            let action;
            switch (storeName) {
                case 'tafsir':
                    action = 'save_tafsir';
                    break;
                case 'themes':
                    action = 'add_theme';
                    break;
                case 'theme_ayahs':
                    action = 'link_ayah_to_theme';
                    break;
                case 'root_words':
                    action = 'save_root_notes';
                    break;
                case 'recitations':
                    action = 'save_recitation_log';
                    break;
                case 'hifz':
                    action = 'update_hifz_status';
                    break;
                case 'goals':
                    action = 'add_goal';
                    break;
                default:
                    console.warn(`addData: Unrecognized store name: ${storeName}`);
                    return {
                        success: false, message: 'Unrecognized store.'
                    };
            }
            return await sendAjaxRequest(action, data);
        }
        async function putData(storeName, data) {
            let action;
            switch (storeName) {
                case 'tafsir':
                    action = 'save_tafsir';
                    break;
                case 'themes':
                    action = 'update_theme';
                    break;
                case 'theme_ayahs':
                    action = 'link_ayah_to_theme';
                    break;
                case 'root_words':
                    action = 'save_root_notes';
                    break;
                case 'recitations':
                    action = 'update_recitation_log';
                    break;
                case 'hifz':
                    action = 'update_hifz_status';
                    break;
                case 'settings':
                    action = 'put_setting';
                    break;
                case 'word_translations':
                    action = 'edit_word_translation';
                    break;
                case 'quran_ayahs':
                    action = 'admin_update_quran_translation';
                    break;
                case 'goals':
                    action = 'update_goal_completion';
                    break;
                default:
                    console.warn(`putData: Unrecognized store name: ${storeName}`);
                    return {
                        success: false, message: 'Unrecognized store.'
                    };
            }
            return await sendAjaxRequest(action, data);
        }
        async function deleteData(storeName, key) {
            let action;
            let params = {};
            switch (storeName) {
                case 'themes':
                    action = 'delete_theme';
                    params.theme_id = key;
                    break;
                case 'theme_ayahs':
                    action = 'unlink_ayah_from_theme';
                    params.link_id = key;
                    break;
                case 'recitations':
                    action = 'delete_recitation_log';
                    params.log_id = key;
                    break;
                case 'goals':
                    action = 'delete_goal';
                    params.id = key;
                    break;
                default:
                    console.warn(`deleteData: Unrecognized store name: ${storeName}`);
                    return {
                        success: false, message: 'Unrecognized store.'
                    };
            }
            return await sendAjaxRequest(action, params);
        }
        async function clearStore(storeName) {
            console.warn(`clearStore(${storeName}) called. In MySQL, this would typically be a DELETE FROM for the current user's data.`);
            return {
                success: true,
                message: 'Clear store logic handled internally by import/clear user data.'
            };
        }
        async function getObjectStore(storeName, mode) {
            return {
                index: (indexName) => ({
                    getAll: (range) => {
                        console.warn(`IndexedDB index.getAll(${indexName}, ${JSON.stringify(range)}) called. Needs specific AJAX implementation.`);
                        return Promise.resolve([]);
                    }
                }),
                get: (key) => getData(storeName, key),
                add: (data) => addData(storeName, data),
                put: (data) => putData(storeName, data),
                delete: (key) => deleteData(storeName, key),
                clear: () => clearStore(storeName)
            };
        }
        async function populateTranslationSelect(localManifest) {
            const select = document.getElementById('translation-select');
            const quranTranslations = allLanguagesConfig;
            const previouslySelected = select.value || 'urdu';
            select.innerHTML = '';
            quranTranslations.forEach(config => {
                const option = document.createElement('option');
                option.value = config.key;
                option.textContent = config.label;
                option.dataset.isLoaded = true;
                option.dataset.config = JSON.stringify(config);
                select.appendChild(option);
            });
            if (select.querySelector(`option[value="${previouslySelected}"]`)) {
                select.value = previouslySelected;
            } else {
                select.value = 'urdu';
            }
        }
        async function fetchRemoteManifest() {
            const mockManifest = {};
            allLanguagesConfig.forEach(config => {
                mockManifest[config.key] = {
                    version: '1.0'
                };
            });
            return mockManifest;
        }

        function getTranslationConfig(key) {
            const config = allLanguagesConfig.find(item => item.key === key);
            if (!config) return {
                key: 'english',
                lang_code: 'en',
                direction: 'ltr',
                label: 'English',
                font_var: 'var(--font-english)'
            };
            return {
                key: config.key,
                lang: config.lang_code,
                dir: config.direction,
                label: config.label,
                font: config.font_var
            };
        }
        async function processDataFile(config, progressCallback) {
            console.warn(`processDataFile called for ${config.key}. In this PHP/MySQL setup, translations are usually managed directly in the database.`);
            progressCallback(1);
            return {
                success: true
            };
        }
        let isUpdateCheckInProgress = false;
        async function loadQuranData() {
            if (isUpdateCheckInProgress) {
                console.log("Update check already running, skipping duplicate call.");
                return;
            }
            isUpdateCheckInProgress = true;
            showLoading("Initializing...", "Checking for app updates...", 0);
            totalProgressUnits = 100;
            completedProgressUnits = 5;
            updateLoadingProgress(0, "Fetching manifest...");
            try {
                console.log("--- STARTING DEBUG ---");

                if (typeof allLanguagesConfig === 'undefined') {
                    throw new Error("Critical Failure: `allLanguagesConfig` is not defined. The PHP script is likely broken before this point.");
                }
                window.appManifest = allLanguagesConfig;

                updateLoadingProgress(0, "Checking local data versions...");
                const localManifest = await getLocalManifest();

                await populateTranslationSelect(localManifest);
                await populateSurahAyahSelects();

                await loadAyah(currentSurah, currentAyah);

            } catch (e) {
                console.error(">>> CRITICAL ERROR IN loadQuranData <<<", e);
                alert(`A specific error occurred. Check the console (F12) for the full error message starting with '>>> CRITICAL ERROR'.\n\nError Message: ${e.message}`);
            } finally {
                hideLoading();
                isUpdateCheckInProgress = false;
            }
        }
        async function getLocalManifest() {
            const versions = {};
            allLanguagesConfig.forEach(config => {
                versions[config.key] = {
                    version: '1.0'
                };
            });
            return versions;
        }

        function isNewDay() {
            return false;
        }

        function showPashtoNoticeOnce() {
            if (!localStorage.getItem('pashtoNoticeShown')) {
                const urduMessage = "پشتو ترجمہ خودکار ترجمہ ہے اور قرآنی معیارات کے مطابق مکمل تصدیق شدہ نہیں ہے۔";
                const pashtoMessage = "پښتو ژباړه اتوماتیکه ژباړه ده او د قرآني معیارونو سره مکمل سم نه ده تایید شوې.";
                alert(`${urduMessage}\n\n${pashtoMessage}`);
                localStorage.setItem('pashtoNoticeShown', 'true');
            }
        }

        function populateSurahAyahSelects() {
            const surahSelect = document.getElementById('surah-select');
            const ayahSelect = document.getElementById('ayah-select');
            const recSurahSelect = document.getElementById('rec-surah-select');
            const hifzSurahSelect = document.getElementById('hifz-surah-select');
            const adminAyahSurahSelect = document.getElementById('review-ayah-surah');
            if (surahSelect.options.length === 0) {
                for (let i = 1; i <= 114; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = `${i}. ${surahNames[i - 1]}`;
                    surahSelect.appendChild(option.cloneNode(true));
                    if (recSurahSelect) recSurahSelect.appendChild(option.cloneNode(true));
                    if (hifzSurahSelect) hifzSurahSelect.appendChild(option.cloneNode(true));
                    if (adminAyahSurahSelect) adminAyahSurahSelect.appendChild(option.cloneNode(true));
                }
            }
            surahSelect.value = currentSurah;
            if (recSurahSelect) recSurahSelect.value = currentSurah;
            if (hifzSurahSelect) hifzSurahSelect.value = currentSurah;
            if (adminAyahSurahSelect) adminAyahSurahSelect.value = currentSurah;
            updateAyahSelect(currentSurah);
            ayahSelect.value = currentAyah;
            if (adminAyahSurahSelect) {
                updateAdminAyahSelect(currentSurah);
                const adminAyahAyahSelect = document.getElementById('review-ayah-ayah');
                if (adminAyahAyahSelect) adminAyahAyahSelect.value = currentAyah;
            }
        }

        function updateAyahSelect(surahNum) {
            const ayahSelect = document.getElementById('ayah-select');
            const adminAyahAyahSelect = document.getElementById('review-ayah-ayah');
            ayahSelect.innerHTML = '';
            if (adminAyahAyahSelect) adminAyahAyahSelect.innerHTML = '';
            totalAyahsInSurah = surahAyahCounts[surahNum];
            for (let i = 1; i <= totalAyahsInSurah; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                ayahSelect.appendChild(option.cloneNode(true));
                if (adminAyahAyahSelect) adminAyahAyahSelect.appendChild(option.cloneNode(true));
            }
            if (currentAyah > totalAyahsInSurah) {
                currentAyah = 1;
            }
            ayahSelect.value = currentAyah;
            if (adminAyahAyahSelect) adminAyahAyahSelect.value = currentAyah;
        }

        function updateAdminAyahSelect(surahNum) {
            const adminAyahAyahSelect = document.getElementById('review-ayah-ayah');
            if (!adminAyahAyahSelect) return;
            adminAyahAyahSelect.innerHTML = '';
            const totalAyahs = surahAyahCounts[surahNum];
            for (let i = 1; i <= totalAyahs; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                adminAyahAyahSelect.appendChild(option);
            }
            if (currentAyah > totalAyahs) {
                adminAyahAyahSelect.value = 1;
            } else {
                adminAyahAyahSelect.value = currentAyah;
            }
        }
        async function loadAyah(surah, ayah) {
            const selectedTranslationKey = document.getElementById('translation-select').value;
            if (selectedTranslationKey === 'pashto') {
                showPashtoNoticeOnce();
            }
            showLoading(`Loading Ayah ${surah}:${ayah}...`);
            try {
                const result = await sendAjaxRequest('load_quran_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                if (result.success && result.data) {
                    const quran = result.data;
                    currentSurah = surah;
                    currentAyah = ayah;
                    updateAyahSelect(surah);
                    document.getElementById('surah-select').value = surah;
                    document.getElementById('ayah-select').value = ayah;
                    const adminSurahSelect = document.getElementById('review-ayah-surah');
                    const adminAyahSelect = document.getElementById('review-ayah-ayah');
                    if (adminSurahSelect) adminSurahSelect.value = surah;
                    if (adminAyahSelect) adminAyahSelect.value = ayah;
                    if (currentQuranView === 'single') {
                        await renderSingleAyahView(surah, ayah, quran);
                    } else {
                        await renderContinuousView(surah, ayah, quran);
                    }
                    if (isUserLoggedIn) {
                        await updateTafsirAndThemeViews();
                    }
                } else {
                    document.getElementById('quran-display').innerHTML = `<p class="text-center" style="color: var(--color-error);">Ayah ${surah}:${ayah} not found in data.</p>`;
                }
            } catch (error) {
                console.error("Error loading ayah:", error);
                document.getElementById('quran-display').innerHTML = `<p class="text-center" style="color: var(--color-error);">Error loading Ayah: ${error.message}</p>`;
            } finally {
                hideLoading();
            }
        }
        async function renderSingleAyahView(surah, ayah, quranData = null) {
            const displayArea = document.getElementById('quran-display');
            showLoading(`Loading Ayah ${surah}:${ayah}...`);
            try {
                let quran = quranData;
                if (!quran) {
                    const result = await sendAjaxRequest('load_quran_ayah', {
                        surah: surah,
                        ayah: ayah
                    });
                    if (result.success) {
                        quran = result.data;
                    } else {
                        throw new Error(result.message);
                    }
                }
                if (!quran) {
                    return;
                }
                const words = quran.arabic.split(/\s+/).filter(w => w.trim());
                const metadataResult = await sendAjaxRequest('get_word_metadata_for_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                const wordMetadata = metadataResult.success ? metadataResult.data : [];
                const metadataMap = new Map(wordMetadata.map(m => [m.word_position, m.word_id]));
                let wordsHTML = words.map((word, index) => {
                    const wordId = metadataMap.get(index) || 'null';
                    return `<span class="arabic-word" data-word-text="${word.trim()}" data-word-id="${wordId}" tabindex="0" role="button">${word}</span>`;
                }).join(' ');
                currentSurah = surah;
                currentAyah = ayah;
                document.getElementById('surah-select').value = surah;
                updateAyahSelect(surah);
                document.getElementById('ayah-select').value = ayah;
                const translationInfo = getTranslationConfig(document.getElementById('translation-select').value);
                displayArea.innerHTML = `
                    <div class="ayah" data-surah="${surah}" data-ayah="${ayah}">
                        <div class="ayah-number">Surah ${surah}:${ayah} (${surahNames[surah - 1]})</div>
                        <div class="ayah-arabic">${wordsHTML}</div>
                        <div class="ayah-translation" lang="${translationInfo.lang}" dir="${translationInfo.dir}" style="font-family:${translationInfo.font}">${quran[translationInfo.key] || "Translation not available."}</div>
                    </div>`;
                addWordClickListeners();
                const adminTranslationSection = document.getElementById('admin-translation-text');
                if (adminTranslationSection) {
                    const currentLang = document.getElementById('admin-translation-lang').value;
                    adminTranslationSection.value = quran[currentLang] || '';
                }
            } catch (e) {
                console.error(e);
                displayArea.innerHTML = `<p class="text-center" style="color: var(--color-error);">Error rendering Ayah: ${e.message}</p>`;
            } finally {
                hideLoading();
            }
        }
        async function getWordTranslationById(wordId) {
            if (isNaN(wordId)) return {
                ur: "N/A",
                en: "N/A",
                ps: "N/A",
                bn: "N/A"
            };
            try {
                const result = await sendAjaxRequest('get_word_translation', {
                    word_id: wordId
                });
                if (result.success && result.data) {
                    const trans = result.data;
                    return {
                        ur: trans.ur_meaning || "N/A",
                        en: trans.en_meaning || "N/A",
                        ps: trans.pashto_text || "N/A",
                        bn: trans.bn_meaning || "N/A"
                    };
                } else {
                    return {
                        ur: "N/A",
                        en: "N/A",
                        ps: "N/A",
                        bn: "N/A"
                    };
                }
            } catch (e) {
                console.error("Error fetching translation for word_id:", wordId, e);
                return {
                    ur: "Error",
                    en: "Error",
                    ps: "Error",
                    bn: "Error"
                };
            }
        }
        async function handleWordClick(event) {
            const wordSpan = event.target;
            const wordId = parseInt(wordSpan.dataset.wordId, 10);
            document.querySelectorAll('.ayah-arabic span, #quran-continuous-display .arabic-word').forEach(span => span.style.backgroundColor = 'transparent');
            wordSpan.style.backgroundColor = 'var(--color-highlight)';
            const translationArea = document.getElementById('word-translation-area');

            if (isNaN(wordId)) {
                translationArea.innerHTML = `<p>Translation not available for this word.</p>`;
                return;
            }

            try {
                const result = await sendAjaxRequest('get_word_translation', {
                    word_id: wordId
                });
                const translations = result.success && result.data ? result.data : {};

                const fullAyahTranslation = wordSpan.closest('.ayah').querySelector('.ayah-translation').textContent;
                const fullTranslationInfo = getTranslationConfig(document.getElementById('translation-select').value);

                let html = `<p><strong>Selected Word:</strong> <span lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${wordSpan.dataset.wordText}</span></p>`;

                // Dynamically loop through all available languages
                allLanguagesConfig.forEach(config => {
                    const meaning = translations[config.word_col_name] || "N/A";
                    html += `<p><strong>${config.label}:</strong> <span lang="${config.lang_code}" dir="${config.direction}" style="font-family: var(${config.font_var});">${meaning}</span></p>`;
                });

                html += `<p><strong>Full Ayah Translation:</strong> <span lang="${fullTranslationInfo.lang}" dir="${fullTranslationInfo.dir}" style="font-family: ${fullTranslationInfo.font};">${fullAyahTranslation}</span></p>`;

                if (isUserLoggedIn && (userRole === 'admin' || userRole === 'registered')) {
                    html += `<button class="edit-word-translation-btn" data-word-id="${wordId}" data-word-text="${wordSpan.dataset.wordText}" style="margin-top: 10px; padding: 5px 10px; font-size: 0.9em;">Edit Word Translation</button>`;
                }

                translationArea.innerHTML = html;

                if (isUserLoggedIn && (userRole === 'admin' || userRole === 'registered')) {
                    document.querySelector('.edit-word-translation-btn')?.addEventListener('click', handleEditWordTranslation);
                }

            } catch (error) {
                console.error("Error handling word click:", error);
                translationArea.innerHTML = `<p style="color: var(--color-error);">Error fetching word details.</p>`;
            }
        }
        async function handleEditWordTranslation(event) {
            const wordId = event.target.dataset.wordId;
            const wordText = event.target.dataset.wordText;
            const translations = await getWordTranslationById(wordId);
            let langOptions = allLanguagesConfig.map(config => {
                const meaning = translations[config.lang_code];
                const currentMeaning = (meaning && meaning !== 'N/A') ? meaning : '';
                return `
                    <div style="margin-bottom: 10px;">
                        <label for="edit-trans-${config.key}">${config.label} Meaning:</label>
                        <textarea id="edit-trans-${config.key}" data-lang-key="${config.key}" dir="${config.direction}" style="font-family: var(${config.font_var}); width: 100%; min-height: 50px;">${currentMeaning}</textarea>
                    </div>
                `;
            }).join('');
            const modalContent = `
                <h4>Edit Translation for: <span lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${wordText}</span> (Word ID: ${wordId})</h4>
                ${langOptions}
                <button id="save-edited-word-translation">Save Changes</button>
                <p id="edit-word-trans-status" aria-live="polite" style="margin-top: 10px;"></p>
            `;
            const editModal = document.getElementById('tafsirQuickModal');
            editModal.querySelector('.modal-ayah-ref').textContent = '';
            editModal.querySelector('.ayah-context').innerHTML = modalContent;
            editModal.querySelector('#quickTafsirText').style.display = 'none';
            editModal.querySelector('#saveQuickTafsirBtn').style.display = 'none';
            editModal.style.display = 'flex';
            document.getElementById('save-edited-word-translation')?.addEventListener('click', async () => {
                const statusEl = document.getElementById('edit-word-trans-status');
                statusEl.textContent = 'Saving...';
                statusEl.style.color = 'var(--color-text-secondary)';
                let allSuccess = true;
                for (const config of allLanguagesConfig) {
                    const textarea = document.getElementById(`edit-trans-${config.key}`);
                    const newText = textarea.value;
                    const result = await sendAjaxRequest('edit_word_translation', {
                        word_id: wordId,
                        lang_key: config.key,
                        translation_text: newText
                    });
                    if (!result.success) {
                        allSuccess = false;
                        console.error(`Failed to save ${config.key} for word ${wordId}: ${result.message}`);
                    }
                }
                if (allSuccess) {
                    statusEl.textContent = 'Changes saved! Admin approval may be needed.';
                    statusEl.style.color = 'var(--color-success)';
                    setTimeout(() => editModal.style.display = 'none', 2000);
                } else {
                    statusEl.textContent = 'Some changes failed to save.';
                    statusEl.style.color = 'var(--color-error)';
                }
            });
        }
        async function renderContinuousView(surah, targetAyah, quranData = null) {
            const displayArea = document.getElementById('quran-continuous-display');
            showLoading(`Loading Surah ${surah}...`);
            displayArea.innerHTML = '';
            try {
                let allAyahsInSurah = [];
                if (quranData && quranData.surah === surah) {}
                const allAyahsResult = await sendAjaxRequest('get_all_quran_ayahs_for_surah', {
                    surah: surah
                });
                if (allAyahsResult.success) {
                    allAyahsInSurah = allAyahsResult.data;
                } else {
                    throw new Error(allAyahsResult.message);
                }
                const allMetadataResult = await sendAjaxRequest('get_all_word_metadata_for_surah', {
                    surah: surah
                });
                const allMetadataForSurah = allMetadataResult.success ? allMetadataResult.data : [];
                const translationInfo = getTranslationConfig(document.getElementById('translation-select').value);
                allAyahsInSurah.forEach(quran => {
                    const words = quran.arabic.split(/\s+/).filter(w => w.trim());
                    const metadataMap = new Map(allMetadataForSurah.filter(m => m.ayah === quran.ayah).map(m => [m.word_position, m.word_id]));
                    let wordsHTML = words.map((word, index) => {
                        const wordId = metadataMap.get(index) || 'null';
                        return `<span class="arabic-word" data-word-text="${word.trim()}" data-word-id="${wordId}">${word}</span>`;
                    }).join(' ');
                    const ayahDiv = document.createElement('div');
                    ayahDiv.id = `s${quran.surah}a${quran.ayah}`;
                    ayahDiv.className = 'ayah';
                    ayahDiv.innerHTML = `
                        <div class="ayah-number">Surah ${quran.surah}:${quran.ayah}</div>
                        <div class="ayah-arabic">${wordsHTML}</div>
                        <div class="ayah-translation" lang="${translationInfo.lang}" dir="${translationInfo.dir}" style="font-family:${translationInfo.font}">${quran[translationInfo.key] || "Translation not available."}</div>
                    `;
                    displayArea.appendChild(ayahDiv);
                });
                addWordTooltipListeners();
                const targetElement = document.getElementById(`s${surah}a${targetAyah}`);
                if (targetElement) targetElement.scrollIntoView({
                    behavior: 'auto',
                    block: 'center'
                });
            } catch (e) {
                console.error(e);
                displayArea.innerHTML = `<p class="text-center" style="color: var(--color-error);">Error rendering continuous view: ${e.message}</p>`;
            } finally {
                hideLoading();
            }
        }
        async function getAllDataInRange(storeName, range, indexName = null) {
            if (storeName === 'word_metadata' && indexName === 'location_idx') {
                const [minSurah, minAyah, minPos] = range.lower;
                const [maxSurah, maxAyah, maxPos] = range.upper;
                const result = await sendAjaxRequest('get_word_metadata_in_range', {
                    min_surah: minSurah,
                    min_ayah: minAyah,
                    min_pos: minPos,
                    max_surah: maxSurah,
                    max_ayah: maxAyah,
                    max_pos: maxPos
                });
                return result.success ? result.data : [];
            }
            if (storeName === 'quran_ayahs') {
                const [minSurah, minAyah] = range.lower;
                const [maxSurah, maxAyah] = range.upper;
                const result = await sendAjaxRequest('get_ayahs_in_range', {
                    min_surah: minSurah,
                    min_ayah: minAyah,
                    max_surah: maxSurah,
                    max_ayah: maxAyah
                });
                return result.success ? result.data : [];
            }
            return await getAllData(storeName);
        }
        window.TRANSLATION_CONFIG = allLanguagesConfig;
        const originalPopulateThemeSelects = populateThemeSelects;
        async function populateThemeSelects() {
            if (!isUserLoggedIn) {
                console.log("User not logged in, skipping populateThemeSelects.");
                return;
            }
            const themeSelectElements = [
                document.getElementById('parent-theme-select'),
                document.getElementById('link-theme-select')
            ];
            try {
                const result = await sendAjaxRequest('get_all_themes_for_dropdown');
                if (result.success) {
                    const themes = result.data;
                    themeSelectElements.forEach(select => {
                        if (!select) return;
                        const preservedValue = select.value;
                        select.innerHTML = '';
                        const defaultOption = document.createElement('option');
                        defaultOption.value = "";
                        defaultOption.textContent = select.id === 'parent-theme-select' ? "-- No Parent --" : "-- Select Theme --";
                        select.appendChild(defaultOption);
                        themes.forEach(theme => {
                            const option = document.createElement('option');
                            option.value = theme.id;
                            option.textContent = theme.name;
                            select.appendChild(option);
                        });
                        if (select.querySelector(`option[value="${preservedValue}"]`)) {
                            select.value = preservedValue;
                        } else {
                            select.value = "";
                        }
                    });
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error("Error populating theme selects:", error);
                if (typeof setStatusMessage === "function" && document.getElementById('theme-manager-status')) {
                    setStatusMessage('theme-manager-status', 'Failed to load themes for dropdowns.', true);
                }
            }
        };
        const originalDisplayThemesList = displayThemesList;
        async function displayThemesList() {
            if (!isUserLoggedIn) {
                const themesListEl = document.getElementById('themes-list');
                if (themesListEl) themesListEl.innerHTML = '<li>Login to manage themes.</li>';
                return;
            }
            if (isLoadingThemesListGlobalFlag) {
                return;
            }
            isLoadingThemesListGlobalFlag = true;
            const themesListElement = document.getElementById('themes-list');
            if (!themesListElement) {
                console.error('[displayThemesList] Element with ID "themes-list" not found.');
                isLoadingThemesListGlobalFlag = false;
                return;
            }
            themesListElement.innerHTML = '';
            try {
                const result = await sendAjaxRequest('get_all_themes');
                if (result.success) {
                    const themes = result.data;
                    if (!themesListElement.parentNode) {
                        isLoadingThemesListGlobalFlag = false;
                        return;
                    }
                    if (themes.length === 0) {
                        themesListElement.innerHTML = '<li>No themes added yet.</li>';
                    } else {
                        themes.forEach(theme => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <span data-theme-id="${theme.id}" class="view-theme-ayahs" tabindex="0" role="button" aria-label="View ayahs for theme ${theme.name}">${theme.name}</span>
                                <div class="theme-actions" style="display: inline-block;">
                                    <button data-theme-id="${theme.id}" class="delete-theme-btn" aria-label="Delete theme ${theme.name}">Delete</button>
                                </div>
                            `;
                            themesListElement.appendChild(li);
                        });
                        themesListElement.querySelectorAll('.view-theme-ayahs').forEach(span => {
                            const newSpan = span.cloneNode(true);
                            span.parentNode.replaceChild(newSpan, span);
                            newSpan?.addEventListener('click', handleViewThemeAyahs);
                            newSpan?.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter' || e.key === ' ') handleViewThemeAyahs(e);
                            });
                        });
                        themesListElement.querySelectorAll('.delete-theme-btn').forEach(button => {
                            const newButton = button.cloneNode(true);
                            button.parentNode.replaceChild(newButton, button);
                            newButton?.addEventListener('click', handleDeleteTheme);
                        });
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error("[displayThemesList] Error:", error);
                if (themesListElement) {
                    themesListElement.innerHTML = `<li>Error loading themes: ${error.message}</li>`;
                }
            } finally {
                isLoadingThemesListGlobalFlag = false;
            }
        };
        const originalAddTheme = addTheme;
        async function addTheme() {
            if (!isUserLoggedIn) {
                setStatusMessage('theme-manager-status', 'Login to add themes.', true);
                return;
            }
            const nameInput = document.getElementById('new-theme-name');
            const parentSelect = document.getElementById('parent-theme-select');
            const name = nameInput.value.trim();
            const parentId = parentSelect.value ? parseInt(parentSelect.value, 10) : null;
            if (!name) {
                setStatusMessage('theme-manager-status', 'Theme name cannot be empty.', true);
                return;
            }
            showLoading("Adding theme...");
            try {
                const result = await sendAjaxRequest('add_theme', {
                    name: name,
                    parentId: parentId,
                    description: ''
                });
                if (result.success) {
                    setStatusMessage('theme-manager-status', `Theme "${name}" added.`, false);
                    nameInput.value = '';
                    parentSelect.value = '';
                    await populateThemeSelects();
                    await displayThemesList();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('theme-manager-status', 'Failed to add theme.', true);
            } finally {
                hideLoading();
            }
        };
        const originalHandleDeleteTheme = handleDeleteTheme;
        async function handleDeleteTheme(event) {
            if (!isUserLoggedIn) {
                alert('Login to delete themes.');
                return;
            }
            const themeId = parseInt(event.target.getAttribute('data-theme-id'), 10);
            if (isNaN(themeId) || !confirm("Delete this theme and all its linked ayahs?")) return;
            showLoading("Deleting theme...");
            try {
                const result = await sendAjaxRequest('delete_theme', {
                    theme_id: themeId
                });
                if (result.success) {
                    setStatusMessage('theme-manager-status', 'Theme and linked ayahs deleted.', false);
                    populateThemeSelects();
                    displayThemesList();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('theme-manager-status', 'Failed to delete theme.', true);
            } finally {
                hideLoading();
            }
        };
        const originalLinkAyahToTheme = linkAyahToTheme;
        async function linkAyahToTheme() {
            if (!isUserLoggedIn) {
                setStatusMessage('theme-linker-status', 'Login to link ayahs to themes.', true);
                return;
            }
            const themeSelect = document.getElementById('link-theme-select');
            const notesInput = document.getElementById('theme-link-notes');
            const themeId = themeSelect.value ? parseInt(themeSelect.value, 10) : null;
            const notes = notesInput.value.trim();
            if (!themeId) {
                setStatusMessage('theme-linker-status', 'Please select a theme.', true);
                return;
            }
            if (currentSurah === 0 || currentAyah === 0) {
                setStatusMessage('theme-linker-status', 'Navigate to an Ayah first.', true);
                return;
            }
            showLoading(`Linking Ayah ${currentSurah}:${currentAyah}...`);
            try {
                const checkResult = await sendAjaxRequest('get_linked_ayahs_for_theme', {
                    theme_id: themeId
                });
                if (checkResult.success && checkResult.data.some(link => link.surah === currentSurah && link.ayah === currentAyah)) {
                    setStatusMessage('theme-linker-status', 'Ayah already linked to this theme.', true);
                } else {
                    const linkResult = await sendAjaxRequest('link_ayah_to_theme', {
                        theme_id: themeId,
                        surah: currentSurah,
                        ayah: currentAyah,
                        notes: notes
                    });
                    if (linkResult.success) {
                        setStatusMessage('theme-linker-status', `Ayah ${currentSurah}:${currentAyah} linked.`, false);
                        notesInput.value = '';
                        await displayLinkedAyahsForCurrentTheme();
                    } else {
                        throw new Error(linkResult.message);
                    }
                }
            } catch (error) {
                setStatusMessage('theme-linker-status', 'Failed to link Ayah.', true);
            } finally {
                hideLoading();
            }
        };
        const originalHandleDeleteThemeLink = handleDeleteThemeLink;
        async function handleDeleteThemeLink(event) {
            if (!isUserLoggedIn) {
                alert('Login to unlink ayahs.');
                return;
            }
            const linkId = parseInt(event.target.getAttribute('data-link-id'), 10);
            if (isNaN(linkId) || !confirm("Unlink this Ayah?")) return;
            showLoading("Unlinking Ayah...");
            try {
                const result = await sendAjaxRequest('unlink_ayah_from_theme', {
                    link_id: linkId
                });
                if (result.success) {
                    setStatusMessage('theme-linker-status', 'Ayah unlinked.', false);
                    const modalContent = event.target.closest('.modal-content');
                    const currentModalThemeId = parseInt(modalContent.getAttribute('data-current-theme-id'), 10);
                    if (!isNaN(currentModalThemeId)) {
                        await displayLinkedAyahsForThemeInModal(currentModalThemeId);
                    } else {
                        document.getElementById('themeAyahsModal').style.display = 'none';
                        displayThemesList();
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('theme-linker-status', 'Failed to unlink Ayah.', true);
            } finally {
                hideLoading();
            }
        };
        const originalDisplayLinkedAyahsForCurrentTheme = displayLinkedAyahsForCurrentTheme;
        async function displayLinkedAyahsForCurrentTheme() {
            if (!isUserLoggedIn) {
                const nameEl = document.getElementById('linked-theme-name');
                const listEl = document.getElementById('linked-ayahs-list');
                if (nameEl) nameEl.textContent = 'N/A';
                if (listEl) listEl.innerHTML = '<li>Login to see linked ayahs.</li>';
                return;
            }
            const themeSelect = document.getElementById('link-theme-select');
            const themeId = themeSelect.value ? parseInt(themeSelect.value, 10) : null;
            const listEl = document.getElementById('linked-ayahs-list');
            const nameEl = document.getElementById('linked-theme-name');
            listEl.innerHTML = '';
            if (!themeId) {
                nameEl.textContent = 'N/A';
                listEl.innerHTML = '<li>Select a theme to see linked ayahs.</li>';
                return;
            }
            showLoading("Loading linked ayahs...");
            try {
                const themeResult = await sendAjaxRequest('get_theme', {
                    theme_id: themeId
                });
                nameEl.textContent = themeResult.success && themeResult.data ? themeResult.data.name : 'Unknown';
                const linkedAyahsResult = await sendAjaxRequest('get_linked_ayahs_for_theme', {
                    theme_id: themeId
                });
                if (linkedAyahsResult.success) {
                    const linkedAyahs = linkedAyahsResult.data;
                    if (linkedAyahs.length === 0) {
                        listEl.innerHTML = '<li>No ayahs linked.</li>';
                    } else {
                        linkedAyahs.sort((a, b) => (a.surah !== b.surah) ? a.surah - b.surah : a.ayah - b.ayah);
                        linkedAyahs.forEach(link => {
                            const li = document.createElement('li');
                            li.innerHTML = `<strong>S ${link.surah}:${link.ayah}</strong> ${link.notes ? `- <em>${link.notes}</em>` : ''}`;
                            listEl.appendChild(li);
                        });
                    }
                } else {
                    throw new Error(linkedAyahsResult.message);
                }
            } catch (error) {
                nameEl.textContent = 'Error';
                listEl.innerHTML = `<li>Error: ${error.message}</li>`;
            } finally {
                hideLoading();
            }
        };
        const originalDisplayLinkedAyahsForThemeInModal = displayLinkedAyahsForThemeInModal;
        async function displayLinkedAyahsForThemeInModal(themeId) {
            if (!isUserLoggedIn) {
                const listEl = document.getElementById('modal-linked-ayahs-list');
                if (listEl) listEl.innerHTML = '<li>Login to view linked ayahs.</li>';
                return;
            }
            const listEl = document.getElementById('modal-linked-ayahs-list');
            listEl.innerHTML = '';
            try {
                const result = await sendAjaxRequest('get_linked_ayahs_for_theme', {
                    theme_id: themeId
                });
                if (result.success) {
                    const linkedAyahs = result.data;
                    if (linkedAyahs.length === 0) {
                        listEl.innerHTML = '<li>No ayahs linked.</li>';
                    } else {
                        linkedAyahs.sort((a, b) => (a.surah !== b.surah) ? a.surah - b.surah : a.ayah - b.ayah);
                        linkedAyahs.forEach(link => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                 <strong>
                                     <span class="item-surah-ayah theme-modal-ayah-link" 
                                         data-surah="${link.surah}" 
                                         data-ayah="${link.ayah}" 
                                         tabindex="0" 
                                         role="button"
                                         title="Go to Surah ${link.surah}, Ayah ${link.ayah} in Quran Viewer">
                                         Surah ${link.surah}:${link.ayah}
                                     </span>
                                 </strong>
                                 ${link.notes ? ` - <em>${link.notes.substring(0, 100)}${link.notes.length > 100 ? '...' : ''}</em>` : ''}
                                 <button data-link-id="${link.id}" class="delete-theme-link-btn" style="margin-left: 10px;" aria-label="Unlink Ayah ${link.surah}:${link.ayah} from this theme">Unlink</button>
                             `;
                            listEl.appendChild(li);
                        });
                        listEl.querySelectorAll('.delete-theme-link-btn').forEach(button => {
                            const newButton = button.cloneNode(true);
                            button.parentNode.replaceChild(newButton, button);
                            newButton?.addEventListener('click', handleDeleteThemeLink);
                        });
                        listEl.querySelectorAll('.theme-modal-ayah-link').forEach(span => {
                            const newSpan = span.cloneNode(true);
                            span.parentNode.replaceChild(newSpan, span);
                            newSpan?.addEventListener('click', handleGoToAyahFromThemeModal);
                            newSpan?.addEventListener('keydown', (ev) => {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    handleGoToAyahFromThemeModal(ev);
                                }
                            });
                        });
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                listEl.innerHTML = `<li>Error: ${error.message}</li>`;
            }
        };
        const originalAnalyzeRoot = analyzeRoot;
        async function analyzeRoot() {
            if (!isUserLoggedIn) {
                setStatusMessage('root-status', 'Login to analyze roots.', true);
                return;
            }
            const rootInput = document.getElementById('root-input');
            const rootTerm = rootInput.value.trim();
            const analyzedRootTermElement = document.getElementById('analyzed-root-term');
            const currentViewMode = document.querySelector('input[name="root-view-mode"]:checked').value;
            const occurrencesListElement = document.getElementById('root-occurrences-list');
            const graphContainer = document.getElementById('root-network-graph');
            const graphPlaceholder = document.getElementById('root-graph-placeholder');
            const paginationControls = document.getElementById('root-graph-pagination-controls');
            analyzedRootTermElement.textContent = rootTerm || 'N/A';
            allRootOccurrencesCache = [];
            currentRootGraphPage = 1;
            occurrencesListElement.innerHTML = '';
            graphContainer.innerHTML = '';
            if (rootNetwork) {
                rootNetwork.destroy();
                rootNetwork = null;
            }
            graphPlaceholder.style.display = 'none';
            paginationControls.style.display = 'none';
            if (!rootTerm) {
                setStatusMessage('root-status', 'Please enter an Arabic root word.', true);
                if (currentViewMode === 'list') occurrencesListElement.innerHTML = '<li>Please enter an Arabic root word.</li>';
                else {
                    graphPlaceholder.textContent = 'Please enter an Arabic root word.';
                    graphPlaceholder.style.display = 'block';
                }
                return;
            }
            if (rootTerm.length < 2) {
                setStatusMessage('root-status', 'Root term should be at least 2 characters.', true);
                if (currentViewMode === 'list') occurrencesListElement.innerHTML = '<li>Root term too short.</li>';
                else {
                    graphPlaceholder.textContent = 'Root term too short.';
                    graphPlaceholder.style.display = 'block';
                }
                return;
            }
            showLoading(`Analyzing root "${rootTerm}"...`);
            try {
                const allAyahsResult = await sendAjaxRequest('get_all_quran_ayahs');
                if (!allAyahsResult.success) throw new Error(allAyahsResult.message);
                const allAyahs = allAyahsResult.data;
                const foundOccurrences = [];
                allAyahs.forEach(ayahData => {
                    const words = ayahData.arabic.split(/\s+/);
                    words.forEach(word => {
                        let wordanClean = word.replace(/[ًٌٍََُِِّْٰٓۡٔؒ]/g, "");
                        let wordanNormalized = wordanClean
                            .replace(/ؤ|و/g, "(و|ؤ)")
                            .replace(/ك|ک/g, "(ك|ک)")
                            .replace(/آ|ا|أ|إ/g, "(آ|ا|أ|إ)")
                            .replace(/ى|ی|ي/g, "(ى|ی|ي)")
                            .replace(/ہ|ھ|ة|ۃ|ه/g, "(ہ|ھ|ة|ۃ|ه)")
                            .replace(/ے/g, "(ے|ی)")
                            .replace(/م/g, "(مٰ|م)");
                        let rootTermPatternPart = rootTerm.replace(/[ًٌٍََُِِّْٰٓۡٔؒ]/g, "")
                            .replace(/ؤ|و/g, "(و|ؤ)")
                            .replace(/ك|ک/g, "(ك|ک)")
                            .replace(/آ|ا|أ|إ/g, "(آ|ا|أ|إ)")
                            .replace(/ى|ی|ي/g, "(ى|ی|ي)")
                            .replace(/ہ|ھ|ة|ۃ|ه/g, "(ہ|ھ|ة|ۃ|ه)")
                            .replace(/ے/g, "(ے|ی)")
                            .replace(/م/g, "(مٰ|م)");
                        let flexibleRootPatternStr = rootTermPatternPart
                            .replace(/ /g, ".{0,1}")
                            .replace(/-/g, ".{0,1}")
                            .replace(/۔/g, ".{0,1}");
                        let flexibleRegex = new RegExp(flexibleRootPatternStr);
                        let strictRootPatternStr = rootTermPatternPart.replace(/[ \-۔]/g, "");
                        if (flexibleRegex.test(wordanClean) || wordanClean.includes(strictRootPatternStr)) {
                            foundOccurrences.push({
                                surah: ayahData.surah,
                                ayah: ayahData.ayah,
                                word: word,
                                context: ayahData.arabic
                            });
                        }
                    });
                });
                allRootOccurrencesCache = [...foundOccurrences];
                if (allRootOccurrencesCache.length === 0) {
                    setStatusMessage('root-status', `No occurrences found for "${rootTerm}".`, false);
                    if (currentViewMode === 'list') {
                        occurrencesListElement.innerHTML = '<li>No occurrences found.</li>';
                    } else {
                        graphPlaceholder.textContent = 'No occurrences found to display in graph.';
                        graphPlaceholder.style.display = 'block';
                    }
                } else {
                    setStatusMessage('root-status', `Found ${allRootOccurrencesCache.length} occurrences for "${rootTerm}".`, false);
                    if (currentViewMode === 'list') {
                        occurrencesListElement.innerHTML = '';
                        allRootOccurrencesCache.forEach(occ => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                 <strong>Surah ${occ.surah}:${occ.ayah}</strong> - Word: <span lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${occ.word}</span>
                                 <div class="result-context" lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${occ.context}</div>
                             `;
                            occurrencesListElement.appendChild(li);
                        });
                    } else if (currentViewMode === 'tree') {
                        occurrencesListElement.innerHTML = '<li>Graph view active. Results may be paginated below.</li>';
                        graphPlaceholder.style.display = 'none';
                        updateRootGraphView();
                        paginationControls.style.display = (allRootOccurrencesCache.length > rootGraphItemsPerPage) ? 'flex' : 'none';
                    }
                }
                const rootNotesResult = await sendAjaxRequest('analyze_root', {
                    root_term: rootTerm
                });
                if (rootNotesResult.success && rootNotesResult.description) {
                    document.getElementById('root-description').value = rootNotesResult.description;
                } else {
                    document.getElementById('root-description').value = '';
                }
            } catch (error) {
                console.error("Error analyzing root:", error);
                setStatusMessage('root-status', 'Failed to analyze root.', true);
                if (currentViewMode === 'list') occurrencesListElement.innerHTML = `<li>Error analyzing root: ${error.message}</li>`;
                else {
                    graphPlaceholder.textContent = `Error analyzing root: ${error.message}`;
                    graphPlaceholder.style.display = 'block';
                }
            } finally {
                hideLoading();
            }
        };
        const originalSaveRootNotes = saveRootNotes;
        async function saveRootNotes() {
            if (!isUserLoggedIn) {
                setStatusMessage('root-status', 'Login to save root notes.', true);
                return;
            }
            const rootInput = document.getElementById('root-input');
            const descriptionInput = document.getElementById('root-description');
            const rootTerm = rootInput.value.trim();
            const description = descriptionInput.value.trim();
            if (!rootTerm) {
                setStatusMessage('root-status', 'Enter root word to save notes.', true);
                return;
            }
            showLoading("Saving root notes...");
            try {
                const result = await sendAjaxRequest('save_root_notes', {
                    root: rootTerm,
                    description: description
                });
                if (result.success) {
                    setStatusMessage('root-status', `Notes updated for "${rootTerm}".`, false);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('root-status', 'Failed to save root notes.', true);
            } finally {
                hideLoading();
            }
        };
        const originalLoadRecitationLogs = loadRecitationLogs;
        async function loadRecitationLogs() {
            if (!isUserLoggedIn) {
                const listEl = document.getElementById('recitations-list');
                if (listEl) listEl.innerHTML = '<li>Login to view recitation logs.</li>';
                return;
            }
            if (isLoadingRecitationLogsGlobalFlag) {
                return;
            }
            isLoadingRecitationLogsGlobalFlag = true;
            const listEl = document.getElementById('recitations-list');
            if (!listEl) {
                console.error('[loadRecitationLogs] Element with ID "recitations-list" not found.');
                isLoadingRecitationLogsGlobalFlag = false;
                return;
            }
            listEl.innerHTML = '';
            showLoading("Loading recitation logs...");
            try {
                const result = await sendAjaxRequest('get_all_recitations');
                if (result.success) {
                    const logs = result.data;
                    if (!listEl.parentNode) {
                        isLoadingRecitationLogsGlobalFlag = false;
                        hideLoading();
                        return;
                    }
                    if (logs.length === 0) {
                        listEl.innerHTML = '<li>No entries logged yet.</li>';
                    } else {
                        logs.sort((a, b) => new Date(b.log_date) - new Date(a.log_date));
                        logs.forEach(log => {
                            const li = document.createElement('li');
                            const range = log.ayah_start && log.ayah_end ? `Ayahs ${log.ayah_start}-${log.ayah_end}` :
                                log.ayah_start ? `Ayah ${log.ayah_start}` : 'Full Surah';
                            li.innerHTML = `
                                <strong>S ${log.surah} (${surahNames[log.surah - 1]})</strong> - ${range} <br>
                                Qari: ${log.qari || 'N/A'} | Date: ${log.log_date || 'N/A'}
                                ${log.notes ? `<br>Notes: <em>${log.notes}</em>` : ''}
                                <div style="margin-top: 5px;">
                                    <button data-log-id="${log.id}" class="delete-recitation-btn">Delete</button>
                                </div>
                            `;
                            listEl.appendChild(li);
                        });
                        listEl.querySelectorAll('.delete-recitation-btn').forEach(button => {
                            const newButton = button.cloneNode(true);
                            button.parentNode.replaceChild(newButton, button);
                            newButton?.addEventListener('click', handleDeleteRecitationLog);
                        });
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error("[loadRecitationLogs] Error:", error);
                if (listEl) {
                    listEl.innerHTML = `<li>Error loading recitation logs: ${error.message}</li>`;
                }
            } finally {
                hideLoading();
                isLoadingRecitationLogsGlobalFlag = false;
            }
        };
        const originalSaveRecitationLog = saveRecitationLog;
        async function saveRecitationLog() {
            if (!isUserLoggedIn) {
                setStatusMessage('recitation-status', 'Login to save recitation logs.', true);
                return;
            }
            const surah = parseInt(document.getElementById('rec-surah-select').value, 10);
            const ayahStart = document.getElementById('rec-ayah-start').value ? parseInt(document.getElementById('rec-ayah-start').value, 10) : null;
            const ayahEnd = document.getElementById('rec-ayah-end').value ? parseInt(document.getElementById('rec-ayah-end').value, 10) : null;
            const qari = document.getElementById('rec-qari').value.trim();
            const date = document.getElementById('rec-date').value;
            const notes = document.getElementById('rec-notes').value.trim();
            if (isNaN(surah)) {
                setStatusMessage('recitation-status', 'Select Surah.', true);
                return;
            }
            if (!date) {
                setStatusMessage('recitation-status', 'Select date.', true);
                return;
            }
            if (ayahStart && (isNaN(ayahStart) || ayahStart < 1 || ayahStart > surahAyahCounts[surah])) {
                setStatusMessage('recitation-status', `Invalid Ayah Start.`, true);
                return;
            }
            if (ayahEnd && (isNaN(ayahEnd) || ayahEnd < 1 || ayahEnd > surahAyahCounts[surah])) {
                setStatusMessage('recitation-status', `Invalid Ayah End.`, true);
                return;
            }
            if (ayahStart && ayahEnd && ayahStart > ayahEnd) {
                setStatusMessage('recitation-status', 'Start Ayah > End Ayah.', true);
                return;
            }
            showLoading("Saving recitation log...");
            try {
                const result = await sendAjaxRequest('save_recitation_log', {
                    surah,
                    ayah_start: ayahStart,
                    ayah_end: ayahEnd,
                    qari,
                    log_date: date,
                    notes
                });
                if (result.success) {
                    setStatusMessage('recitation-status', 'Log entry saved.', false);
                    ['rec-ayah-start', 'rec-ayah-end', 'rec-qari', 'rec-date', 'rec-notes'].forEach(id => document.getElementById(id).value = '');
                    loadRecitationLogs();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('recitation-status', 'Failed to save log.', true);
            } finally {
                hideLoading();
            }
        };
        const originalHandleDeleteRecitationLog = handleDeleteRecitationLog;
        async function handleDeleteRecitationLog(event) {
            if (!isUserLoggedIn) {
                alert('Login to delete recitation logs.');
                return;
            }
            const logId = parseInt(event.target.getAttribute('data-log-id'), 10);
            if (isNaN(logId) || !confirm("Delete this log entry?")) return;
            showLoading("Deleting log entry...");
            try {
                const result = await sendAjaxRequest('delete_recitation_log', {
                    log_id: logId
                });
                if (result.success) {
                    setStatusMessage('recitation-status', 'Log entry deleted.', false);
                    loadRecitationLogs();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('recitation-status', 'Failed to delete log.', true);
            } finally {
                hideLoading();
            }
        };
        const originalLoadHifzForSurah = loadHifzForSurah;
        async function loadHifzForSurah(surah) {
            if (!isUserLoggedIn) {
                const listEl = document.getElementById('hifz-ayahs-list');
                if (listEl) listEl.innerHTML = '<p class="text-center">Login to track Hifz progress.</p>';
                return;
            }
            if (isLoadingHifzForSurahGlobalFlag) {
                return;
            }
            isLoadingHifzForSurahGlobalFlag = true;
            const listEl = document.getElementById('hifz-ayahs-list');
            if (!listEl) {
                console.error('[loadHifzForSurah] Element with ID "hifz-ayahs-list" not found.');
                isLoadingHifzForSurahGlobalFlag = false;
                return;
            }
            listEl.innerHTML = '';
            if (isNaN(surah) || surah < 1 || surah > 114) {
                listEl.innerHTML = '<p class="text-center">Select a valid Surah.</p>';
                isLoadingHifzForSurahGlobalFlag = false;
                return;
            }
            showLoading(`Loading Hifz for Surah ${surah} (${surahNames[surah - 1] || ''})...`);
            try {
                const totalAyahs = surahAyahCounts[surah];
                const result = await sendAjaxRequest('get_hifz_for_surah', {
                    surah: surah
                });
                if (!result.success) {
                    throw new Error(result.message);
                }
                const hifzEntries = result.data;
                if (!listEl.parentNode) {
                    console.warn(`[loadHifzForSurah] listEl no longer in DOM after fetching data for Surah ${surah}.`);
                    isLoadingHifzForSurahGlobalFlag = false;
                    hideLoading();
                    return;
                }
                const hifzMap = new Map(hifzEntries.map(e => [e.ayah, e]));
                if (totalAyahs === 0) {
                    listEl.innerHTML = `<p class="text-center">No Ayahs listed for Surah ${surah}.</p>`;
                } else {
                    for (let i = 1; i <= totalAyahs; i++) {
                        const ayahData = hifzMap.get(i) || {
                            surah,
                            ayah: i,
                            status: 'not-started',
                            last_review_date: null,
                            next_review_date: null,
                            review_count: 0,
                            notes: ''
                        };
                        const li = document.createElement('div');
                        li.classList.add('ayah');
                        li.setAttribute('data-ayah-ref', `${surah}:${i}`);
                        let statusText = ayahData.status.replace('-', ' ');
                        let reviewInfo = '';
                        if (ayahData.status === 'memorized' && ayahData.next_review_date) {
                            reviewInfo = ` | Next Review: ${ayahData.next_review_date}`;
                        }
                        li.innerHTML = `
                            <div class="ayah-number">S ${surah}:${i}</div>
                            <div class="hifz-ayah-details" style="margin-bottom: 8px;">
                                Status: <span class="hifz-ayah-status status-${ayahData.status}">${statusText}</span>${reviewInfo}
                            </div>
                            <div class="hifz-ayah-controls flex-group" style="justify-content: flex-start; gap: 5px;">
                                <button data-surah="${surah}" data-ayah="${i}" data-status="not-started" class="set-hifz-status-btn" aria-label="Set Surah ${surah} Ayah ${i} to Not Started" ${ayahData.status === 'not-started' ? 'disabled' : ''}>Not Started</button>
                                <button data-surah="${surah}" data-ayah="${i}" data-status="in-progress" class="set-hifz-status-btn" aria-label="Set Surah ${surah} Ayah ${i} to In Progress" ${ayahData.status === 'in-progress' ? 'disabled' : ''}>In Progress</button>
                                <button data-surah="${surah}" data-ayah="${i}" data-status="memorized" class="set-hifz-status-btn" aria-label="Set Surah ${surah} Ayah ${i} to Memorized" ${ayahData.status === 'memorized' ? 'disabled' : ''}>Memorized</button>
                                ${ayahData.status === 'memorized' ? `<button data-surah="${surah}" data-ayah="${i}" class="record-review-btn" aria-label="Record review for Surah ${surah} Ayah ${i}">Record Review</button>` : ''}
                                <button data-surah="${surah}" data-ayah="${i}" class="view-hifz-notes-btn" aria-label="View or edit notes for Surah ${surah} Ayah ${i}">Notes</button>
                            </div>
                        `;
                        listEl.appendChild(li);
                    }
                    listEl.querySelectorAll('.set-hifz-status-btn').forEach(button => {
                        const newButton = button.cloneNode(true);
                        button.parentNode.replaceChild(newButton, button);
                        newButton?.addEventListener('click', handleSetHifzStatus);
                    });
                    listEl.querySelectorAll('.record-review-btn').forEach(button => {
                        const newButton = button.cloneNode(true);
                        button.parentNode.replaceChild(newButton, button);
                        newButton?.addEventListener('click', handleRecordReview);
                    });
                    listEl.querySelectorAll('.view-hifz-notes-btn').forEach(button => {
                        const newButton = button.cloneNode(true);
                        button.parentNode.replaceChild(newButton, button);
                        newButton?.addEventListener('click', handleViewHifzNotes);
                    });
                }
            } catch (error) {
                console.error(`[loadHifzForSurah] Error for Surah ${surah}:`, error);
                if (listEl) {
                    listEl.innerHTML = `<li>Error loading Hifz data for Surah ${surah}: ${error.message}</li>`;
                }
            } finally {
                hideLoading();
                isLoadingHifzForSurahGlobalFlag = false;
            }
        };
        const originalHandleSetHifzStatus = handleSetHifzStatus;
        async function handleSetHifzStatus(event) {
            if (!isUserLoggedIn) {
                alert('Login to update Hifz status.');
                return;
            }
            const surah = parseInt(event.target.getAttribute('data-surah'), 10);
            const ayah = parseInt(event.target.getAttribute('data-ayah'), 10);
            const status = event.target.getAttribute('data-status');
            showLoading(`Setting status for ${surah}:${ayah}...`);
            try {
                const existingResult = await sendAjaxRequest('get_hifz_for_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                let existing = existingResult.success && existingResult.data ? existingResult.data : {
                    surah,
                    ayah,
                    status: 'not-started',
                    last_review_date: null,
                    next_review_date: null,
                    review_count: 0,
                    notes: ''
                };
                existing.status = status;
                if (status !== 'memorized') {
                    existing.last_review_date = null;
                    existing.next_review_date = null;
                    existing.review_count = 0;
                } else if (!existing.last_review_date) {
                    existing.last_review_date = new Date().toISOString().split('T')[0];
                    existing.review_count = 0;
                    existing.next_review_date = calculateNextReview(existing.last_review_date, existing.review_count);
                }
                const updateResult = await sendAjaxRequest('update_hifz_status', {
                    surah: surah,
                    ayah: ayah,
                    status: existing.status,
                    last_review_date: existing.last_review_date,
                    next_review_date: existing.next_review_date,
                    review_count: existing.review_count,
                    notes: existing.notes
                });
                if (updateResult.success) {
                    setStatusMessage('hifz-status', `Status updated for ${surah}:${ayah}.`, false);
                    loadHifzForSurah(surah);
                } else {
                    throw new Error(updateResult.message);
                }
            } catch (error) {
                setStatusMessage('hifz-status', 'Failed to update status.', true);
            } finally {
                hideLoading();
            }
        };
        const originalHandleRecordReview = handleRecordReview;
        async function handleRecordReview(event) {
            if (!isUserLoggedIn) {
                alert('Login to record reviews.');
                return;
            }
            const surah = parseInt(event.target.getAttribute('data-surah'), 10);
            const ayah = parseInt(event.target.getAttribute('data-ayah'), 10);
            showLoading(`Recording review for ${surah}:${ayah}...`);
            try {
                const existingResult = await sendAjaxRequest('get_hifz_for_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                let existing = existingResult.success && existingResult.data ? existingResult.data : null;
                if (!existing || existing.status !== 'memorized') {
                    setStatusMessage('hifz-status', 'Ayah not memorized.', true);
                    hideLoading();
                    return;
                }
                existing.last_review_date = new Date().toISOString().split('T')[0];
                existing.review_count = (existing.review_count || 0) + 1;
                existing.next_review_date = calculateNextReview(existing.last_review_date, existing.review_count);
                const updateResult = await sendAjaxRequest('update_hifz_status', {
                    surah: surah,
                    ayah: ayah,
                    status: existing.status,
                    last_review_date: existing.last_review_date,
                    next_review_date: existing.next_review_date,
                    review_count: existing.review_count,
                    notes: existing.notes
                });
                if (updateResult.success) {
                    setStatusMessage('hifz-status', `Review recorded. Next: ${existing.next_review_date}`, false);
                    loadHifzForSurah(surah);
                } else {
                    throw new Error(updateResult.message);
                }
            } catch (error) {
                setStatusMessage('hifz-status', 'Failed to record review.', true);
            } finally {
                hideLoading();
            }
        };
        const originalHandleViewHifzNotes = handleViewHifzNotes;
        async function handleViewHifzNotes(event) {
            if (!isUserLoggedIn) {
                alert('Login to view Hifz notes.');
                return;
            }
            const surah = parseInt(event.target.getAttribute('data-surah'), 10);
            const ayah = parseInt(event.target.getAttribute('data-ayah'), 10);
            showLoading(`Loading notes for ${surah}:${ayah}...`);
            try {
                const existingResult = await sendAjaxRequest('get_hifz_for_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                let existing = existingResult.success && existingResult.data ? existingResult.data : {
                    surah,
                    ayah,
                    status: 'not-started',
                    notes: ''
                };
                const notes = prompt(`Notes for Surah ${surah}:${ayah}:\n${existing.notes}\nEdit notes:`, existing.notes || '');
                if (notes !== null && notes !== existing.notes) {
                    existing.notes = notes;
                    const updateResult = await sendAjaxRequest('update_hifz_status', {
                        surah: surah,
                        ayah: ayah,
                        status: existing.status,
                        last_review_date: existing.last_review_date,
                        next_review_date: existing.next_review_date,
                        review_count: existing.review_count,
                        notes: existing.notes
                    });
                    if (updateResult.success) {
                        setStatusMessage('hifz-status', `Notes updated.`, false);
                        loadHifzForSurah(surah);
                    } else {
                        throw new Error(updateResult.message);
                    }
                } else {
                    setStatusMessage('hifz-status', 'Notes unchanged.', false);
                }
            } catch (error) {
                setStatusMessage('hifz-status', 'Failed to load/save notes.', true);
            } finally {
                hideLoading();
            }
        };
        const originalPerformSearch = performSearch;
        async function performSearch() {
            if (!isUserLoggedIn) {
                setStatusMessage('search-status', 'Login to perform search.', true);
                return;
            }
            const searchTerm = document.getElementById('search-input').value.trim();
            const searchScopes = Array.from(document.querySelectorAll('.search-scope:checked')).map(cb => cb.value);
            const searchResultsList = document.getElementById('search-results-list');
            searchResultsList.innerHTML = '';
            if (!searchTerm) {
                setStatusMessage('search-status', 'Please enter a search term.', true);
                return;
            }
            if (searchScopes.length === 0) {
                setStatusMessage('search-status', 'Please select at least one search scope.', true);
                return;
            }
            showLoading(`Searching for "${searchTerm}"...`);
            try {
                const result = await sendAjaxRequest('search_data', {
                    search_term: searchTerm,
                    scopes: searchScopes
                });
                if (!result.success) throw new Error(result.message);
                const results = result.data;
                if (results.length === 0) {
                    searchResultsList.innerHTML = '<li>No results found.</li>';
                    setStatusMessage('search-status', `No results found for "${searchTerm}".`, false);
                } else {
                    setStatusMessage('search-status', `Found ${results.length} results for "${searchTerm}".`, false);
                    results.sort((a, b) => {
                        if (a.surah && b.surah && a.surah !== b.surah) return a.surah - b.surah;
                        if (a.ayah && b.ayah) return a.ayah - b.ayah;
                        return 0;
                    });
                    results.forEach(result => {
                        const li = document.createElement('li');
                        li.innerHTML = `
                             <strong>${result.type}: ${result.ref}</strong> (${result.source})
                             <div class="result-context">${highlightMatch(result.context, searchTerm)}</div>
                             ${(result.type === 'Quran' || result.type === 'Tafsir' || result.type === 'Hifz') ?
                                `<button data-surah="${result.surah}" data-ayah="${result.ayah}" class="go-to-ayah-btn" style="margin-top: 5px; padding: 3px 8px; font-size: 0.8rem;">Go to Ayah</button>` : ''}
                         `;
                        searchResultsList.appendChild(li);
                    });
                    searchResultsList.querySelectorAll('.go-to-ayah-btn').forEach(button => {
                        button?.addEventListener('click', handleGoToAyahFromSearch);
                    });
                }
            } catch (error) {
                console.error("Error during search:", error);
                setStatusMessage('search-status', 'Failed to perform search.', true);
                searchResultsList.innerHTML = `<li>Error during search: ${error.message}</li>`;
            } finally {
                hideLoading();
            }
        };
        const originalExportData = exportData;
        async function exportData() {
            if (!isUserLoggedIn) {
                setStatusMessage('export-status', 'Login to export data.', true);
                return;
            }
            showLoading("Exporting data...");
            try {
                const result = await sendAjaxRequest('export_user_data');
                if (!result.success) throw new Error(result.message);
                const jsonString = JSON.stringify(result.data, null, 2);
                const blob = new Blob([jsonString], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `nur-al-quran-studio-backup-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
                setStatusMessage('export-status', 'Data exported.', false);
            } catch (error) {
                setStatusMessage('export-status', 'Export failed.', true);
            } finally {
                hideLoading();
            }
        };
        const originalImportData = importData;
        async function importData(file) {
            if (!isUserLoggedIn || !file || !confirm("Importing will overwrite existing data. Continue?")) return;
            showLoading("Importing data...");
            try {
                const reader = new FileReader();
                reader.onload = async (event) => {
                    try {
                        const data = event.target.result;
                        const result = await sendAjaxRequest('import_user_data', {
                            data: data
                        });
                        if (result.success) {
                            setStatusMessage('import-status', 'Data imported.', false);
                            await loadAyah(currentSurah, currentAyah);
                            if (isUserLoggedIn) {
                                displayThemesList();
                                loadRecitationLogs();
                                if (document.getElementById('hifz-surah-select')) {
                                    const hifzSurahSelect = document.getElementById('hifz-surah-select');
                                    if (hifzSurahSelect.value) loadHifzForSurah(parseInt(hifzSurahSelect.value, 10));
                                }
                                if (typeof renderGoalsUI === 'function') renderGoalsUI();
                                if (typeof loadAndDisplayReportData_Enhanced === 'function') loadAndDisplayReportData_Enhanced();
                            }
                        } else {
                            throw new Error(result.message);
                        }
                    } catch (parseError) {
                        setStatusMessage('import-status', 'Invalid import file format or import failed: ' + parseError.message, true);
                        hideLoading();
                    } finally {
                        hideLoading();
                    }
                };
                reader.onerror = () => {
                    setStatusMessage('import-status', 'Failed to read file.', true);
                    hideLoading();
                };
                reader.readAsText(file);
            } catch (error) {
                setStatusMessage('import-status', 'Import initiation failed.', true);
                hideLoading();
            }
        };
        const originalClearAllPersonalData = clearAllPersonalData;
        async function clearAllPersonalData() {
            if (!isUserLoggedIn || !confirm("DELETE ALL personal data? This cannot be undone.")) return;
            showLoading("Clearing all personal data...");
            try {
                const result = await sendAjaxRequest('clear_personal_data');
                if (result.success) {
                    setStatusMessage('clear-status', 'All personal data cleared.', false);
                    if (document.getElementById('tafsir-notes')) document.getElementById('tafsir-notes').value = '';
                    if (document.getElementById('themes-list')) document.getElementById('themes-list').innerHTML = '<li>No themes added yet.</li>';
                    if (document.getElementById('root-occurrences-list')) document.getElementById('root-occurrences-list').innerHTML = '<li>Enter a root word and click "Analyze Root".</li>';
                    if (document.getElementById('recitations-list')) document.getElementById('recitations-list').innerHTML = '<li>No entries logged yet.</li>';
                    if (document.getElementById('hifz-ayahs-list')) document.getElementById('hifz-ayahs-list').innerHTML = '<p class="text-center">Select a Surah to track Hifz progress.</p>';
                    if (isUserLoggedIn) {
                        populateThemeSelects();
                        if (typeof renderGoalsUI === 'function') renderGoalsUI();
                        if (typeof loadAndDisplayReportData_Enhanced === 'function') loadAndDisplayReportData_Enhanced();
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('clear-status', 'Data clear failed.', true);
            } finally {
                hideLoading();
            }
        };
        const originalLoadThemePreference = loadThemePreference;
        async function loadThemePreference() {
            if (!isUserLoggedIn) {
                const savedTheme = localStorage.getItem('theme_preference');
                const theme = savedTheme || 'serene';
                document.getElementById('theme-switcher').value = theme;
                applyTheme(theme);
                return;
            }
            try {
                const result = await sendAjaxRequest('get_setting', {
                    name: 'theme'
                });
                const theme = result.success && result.value ? result.value : 'serene';
                document.getElementById('theme-switcher').value = theme;
                applyTheme(theme);
            } catch (error) {
                console.error("Failed to load theme preference:", error);
                document.getElementById('theme-switcher').value = 'serene';
                applyTheme('serene');
            }
        };
        const originalApplyTheme = applyTheme;

        function applyTheme(themeName) {
            document.body.className = '';
            if (themeName !== 'serene') document.body.classList.add(`theme-${themeName}`);
            if (isUserLoggedIn) {
                sendAjaxRequest('put_setting', {
                    name: 'theme',
                    value: themeName
                }).catch(console.error);
            } else {
                localStorage.setItem('theme_preference', themeName);
            }
        };
        async function loadAdminUsers() {
            const userListEl = document.getElementById('user-list');
            if (!userListEl) return;
            userListEl.innerHTML = '<li>Loading users...</li>';
            try {
                const result = await sendAjaxRequest('get_all_users');
                if (result.success) {
                    const users = result.data;
                    if (users.length === 0) {
                        userListEl.innerHTML = '<li>No users found.</li>';
                    } else {
                        userListEl.innerHTML = '';
                        users.forEach(user => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <div class="user-info">
                                    <strong>${user.username}</strong> (${user.role})<br>
                                    <small>${user.email}</small>
                                </div>
                                <div class="user-actions">
                                    <select data-user-id="${user.id}" class="user-role-select" aria-label="Change role for ${user.username}">
                                        <option value="public" ${user.role === 'public' ? 'selected' : ''}>Public</option>
                                        <option value="registered" ${user.role === 'registered' ? 'selected' : ''}>Registered</option>
                                        <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
                                    </select>
                                    <button data-user-id="${user.id}" class="delete-user-btn" style="background-color: var(--color-error);">Delete</button>
                                </div>
                            `;
                            userListEl.appendChild(li);
                        });
                        userListEl.querySelectorAll('.user-role-select').forEach(select => {
                            select?.addEventListener('change', handleUserRoleChange);
                        });
                        userListEl.querySelectorAll('.delete-user-btn').forEach(button => {
                            button?.addEventListener('click', handleDeleteUser);
                        });
                    }
                } else {
                    userListEl.innerHTML = `<li>Error: ${result.message}</li>`;
                }
            } catch (error) {
                userListEl.innerHTML = `<li>Error loading users: ${error.message}</li>`;
            }
        }
        async function handleUserRoleChange(event) {
            const userId = event.target.dataset.userId;
            const newRole = event.target.value;
            if (confirm(`Are you sure you want to change the role for user ID ${userId} to ${newRole}?`)) {
                try {
                    const result = await sendAjaxRequest('update_user_role', {
                        user_id: userId,
                        new_role: newRole
                    });
                    if (result.success) {
                        alert(result.message);
                        loadAdminUsers();
                    } else {
                        alert(`Failed to update role: ${result.message}`);
                    }
                } catch (error) {
                    alert(`An error occurred: ${error.message}`);
                }
            } else {
                const originalRole = event.target.options[event.target.selectedIndex].textContent.toLowerCase().trim();
                event.target.value = originalRole;
            }
        }
        async function handleDeleteUser(event) {
            const userId = event.target.dataset.userId;
            if (confirm(`Are you sure you want to delete user ID ${userId}? This action is irreversible.`)) {
                try {
                    const result = await sendAjaxRequest('delete_user', {
                        user_id: userId
                    });
                    if (result.success) {
                        alert(result.message);
                        loadAdminUsers();
                    } else {
                        alert(`Failed to delete user: ${result.message}`);
                    }
                } catch (error) {
                    alert(`An error occurred: ${error.message}`);
                }
            }
        }
        async function loadWordForReview() {
            const wordIdInput = document.getElementById('review-word-id');
            const wordId = parseInt(wordIdInput.value, 10);
            const reviewDetailsDiv = document.getElementById('review-word-details');
            const arabicWordSpan = document.getElementById('review-arabic-word');
            const translationReviewLanguagesDiv = document.getElementById('translation-review-languages');
            const reviewStatus = document.getElementById('review-status');
            reviewDetailsDiv.style.display = 'none';
            arabicWordSpan.textContent = '';
            translationReviewLanguagesDiv.innerHTML = '';
            reviewStatus.textContent = '';
            if (isNaN(wordId) || wordId <= 0) {
                reviewStatus.textContent = 'Please enter a valid Word ID.';
                reviewStatus.style.color = 'var(--color-error)';
                return;
            }
            try {
                const metadataResult = await sendAjaxRequest('get_word_metadata', {
                    word_id: wordId
                });
                if (!metadataResult.success || !metadataResult.data) {
                    reviewStatus.textContent = 'Word metadata not found for this ID.';
                    reviewStatus.style.color = 'var(--color-error)';
                    return;
                }
                const arabicWord = metadataResult.data.arabic_word;
                arabicWordSpan.textContent = arabicWord;
                const translationsResult = await sendAjaxRequest('get_word_translation', {
                    word_id: wordId
                });
                const currentTranslations = translationsResult.success && translationsResult.data ? translationsResult.data : {};
                allLanguagesConfig.forEach(config => {
                    const langKey = config.key;
                    const dbColName = `${langKey}_meaning`;
                    const translationText = currentTranslations[dbColName] || '';
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <label for="review-trans-${langKey}">${config.label} Translation:</label>
                        <textarea id="review-trans-${langKey}" data-lang-key="${langKey}" dir="${config.direction}" style="font-family: ${config.font}; width: 100%; min-height: 50px;">${translationText}</textarea>
                        <button class="admin-approve-trans-btn" data-word-id="${wordId}" data-lang-key="${langKey}">Approve & Save ${config.label}</button>
                    `;
                    translationReviewLanguagesDiv.appendChild(div);
                });
                reviewDetailsDiv.style.display = 'block';
                document.querySelectorAll('.admin-approve-trans-btn').forEach(button => {
                    button?.addEventListener('click', handleAdminApproveTranslation);
                });
            } catch (error) {
                reviewStatus.textContent = `Error loading word for review: ${error.message}`;
                reviewStatus.style.color = 'var(--color-error)';
            }
        }
        async function handleAdminApproveTranslation(event) {
            const wordId = event.target.dataset.wordId;
            const langKey = event.target.dataset.langKey;
            const textarea = document.getElementById(`review-trans-${langKey}`);
            const newTranslationText = textarea.value;
            const reviewStatus = document.getElementById('review-status');
            reviewStatus.textContent = 'Saving...';
            reviewStatus.style.color = 'var(--color-text-secondary)';
            try {
                const saveResult = await sendAjaxRequest('edit_word_translation', {
                    word_id: wordId,
                    lang_key: langKey,
                    translation_text: newTranslationText
                });
                if (saveResult.success) {
                    reviewStatus.textContent = `${langKey} translation saved and approved!`;
                    reviewStatus.style.color = 'var(--color-success)';
                } else {
                    throw new Error(saveResult.message);
                }
            } catch (error) {
                reviewStatus.textContent = `Failed to save/approve translation: ${error.message}`;
                reviewStatus.style.color = 'var(--color-error)';
            }
        }
        async function loadAyahForLineReview() {
            const surahSelect = document.getElementById('review-ayah-surah');
            const ayahSelect = document.getElementById('review-ayah-ayah');
            const surah = parseInt(surahSelect.value, 10);
            const ayah = parseInt(ayahSelect.value, 10);
            const lineReviewDetailsDiv = document.getElementById('review-ayah-line-details');
            const ayahArabicSpan = document.getElementById('review-ayah-arabic');
            const lineTranslationLanguagesDiv = document.getElementById('line-translation-review-languages');
            const lineReviewStatus = document.getElementById('line-review-status');
            lineReviewDetailsDiv.style.display = 'none';
            ayahArabicSpan.textContent = '';
            lineTranslationLanguagesDiv.innerHTML = '';
            lineReviewStatus.textContent = '';
            if (isNaN(surah) || isNaN(ayah) || surah <= 0 || ayah <= 0) {
                lineReviewStatus.textContent = 'Please select a valid Surah and Ayah.';
                lineReviewStatus.style.color = 'var(--color-error)';
                return;
            }
            try {
                const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                if (!ayahDataResult.success || !ayahDataResult.data) {
                    lineReviewStatus.textContent = 'Ayah data not found.';
                    lineReviewStatus.style.color = 'var(--color-error)';
                    return;
                }
                const ayahData = ayahDataResult.data;
                ayahArabicSpan.textContent = ayahData.arabic;
                allLanguagesConfig.forEach(config => {
                    const langKey = config.key;
                    const translationText = ayahData[langKey] || '';
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <label for="line-review-trans-${langKey}">${config.label} Translation:</label>
                        <textarea id="line-review-trans-${langKey}" data-lang-key="${langKey}" dir="${config.direction}" style="font-family: ${config.font}; width: 100%; min-height: 100px;">${translationText}</textarea>
                        <button class="admin-save-line-trans-btn" data-surah="${surah}" data-ayah="${ayah}" data-lang-key="${langKey}">Save ${config.label} (Line)</button>
                    `;
                    lineTranslationLanguagesDiv.appendChild(div);
                });
                lineReviewDetailsDiv.style.display = 'block';
                document.querySelectorAll('.admin-save-line-trans-btn').forEach(button => {
                    button?.addEventListener('click', handleAdminSaveLineTranslation);
                });
            } catch (error) {
                lineReviewStatus.textContent = `Error loading ayah for line review: ${error.message}`;
                lineReviewStatus.style.color = 'var(--color-error)';
            }
        }
        async function handleAdminSaveLineTranslation(event) {
            const surah = event.target.dataset.surah;
            const ayah = event.target.dataset.ayah;
            const langKey = event.target.dataset.langKey;
            const textarea = document.getElementById(`line-review-trans-${langKey}`);
            const newTranslationText = textarea.value;
            const lineReviewStatus = document.getElementById('line-review-status');
            lineReviewStatus.textContent = 'Saving...';
            lineReviewStatus.style.color = 'var(--color-text-secondary)';
            try {
                const result = await sendAjaxRequest('admin_update_quran_translation', {
                    surah: surah,
                    ayah: ayah,
                    lang_key: langKey,
                    translation_text: newTranslationText
                });
                if (result.success) {
                    lineReviewStatus.textContent = `${langKey} line translation updated!`;
                    lineReviewStatus.style.color = 'var(--color-success)';
                    if (currentSurah == surah && currentAyah == ayah) {
                        loadAyah(currentSurah, currentAyah);
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                lineReviewStatus.textContent = `Failed to save line translation: ${error.message}`;
                lineReviewStatus.style.color = 'var(--color-error)';
            }
        }

        function setupAuthEventListeners() {
            const loginBtn = document.getElementById('login-btn');
            const registerBtn = document.getElementById('register-btn');
            const loginModal = document.getElementById('login-modal');
            const registerModal = document.getElementById('register-modal');
            const openRegisterFromLogin = document.getElementById('open-register-from-login');
            const openLoginFromRegister = document.getElementById('open-login-from-register');
            if (loginBtn) {
                loginBtn?.addEventListener('click', () => {
                    loginModal.style.display = 'flex';
                    if (registerModal) registerModal.style.display = 'none';
                    document.getElementById('login-username').focus();
                });
            }
            if (registerBtn) {
                registerBtn?.addEventListener('click', () => {
                    registerModal.style.display = 'flex';
                    if (loginModal) loginModal.style.display = 'none';
                    document.getElementById('register-username').focus();
                });
            }
            if (openRegisterFromLogin) {
                openRegisterFromLogin?.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (loginModal) loginModal.style.display = 'none';
                    if (registerModal) registerModal.style.display = 'flex';
                    document.getElementById('register-username').focus();
                });
            }
            if (openLoginFromRegister) {
                openLoginFromRegister?.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (registerModal) registerModal.style.display = 'none';
                    if (loginModal) loginModal.style.display = 'flex';
                    document.getElementById('login-username').focus();
                });
            }
            const adminPanelBtn = document.getElementById('admin-panel-btn');
            const adminModal = document.getElementById('admin-modal');
            if (adminPanelBtn && adminModal) {
                adminPanelBtn?.addEventListener('click', () => {
                    adminModal.style.display = 'flex';
                    loadAdminUsers();
                });
                document.querySelectorAll('.admin-tab').forEach(tab => {
                    tab?.addEventListener('click', (e) => {
                        document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
                        e.target.classList.add('active');
                        document.querySelectorAll('.admin-tab-content').forEach(c => c.style.display = 'none');
                        const targetTabId = `admin-tab-${e.target.dataset.tab}`;
                        document.getElementById(targetTabId).style.display = 'block';
                        if (e.target.dataset.tab === 'users') {
                            loadAdminUsers();
                        } else if (e.target.dataset.tab === 'translations') {
                            const adminAyahSurahSelect = document.getElementById('review-ayah-surah');
                            if (adminAyahSurahSelect && adminAyahSurahSelect.options.length === 0) {
                                populateSurahAyahSelects();
                            }
                            document.getElementById('review-word-details').style.display = 'none';
                            document.getElementById('review-ayah-line-details').style.display = 'none';
                        }
                    });
                });
                const loadWordBtn = document.getElementById('load-word-for-review');
                if (loadWordBtn) loadWordBtn?.addEventListener('click', loadWordForReview);
                const loadAyahLineBtn = document.getElementById('load-ayah-for-line-review');
                if (loadAyahLineBtn) loadAyahLineBtn?.addEventListener('click', loadAyahForLineReview);
                const adminAyahSurahSelect = document.getElementById('review-ayah-surah');
                if (adminAyahSurahSelect) {
                    adminAyahSurahSelect?.addEventListener('change', (event) => {
                        updateAdminAyahSelect(parseInt(event.target.value, 10));
                    });
                }
            }
        }

        function patchSidebarForAuth() {
            const sidebar = document.querySelector('.sidebar nav ul');
            if (!sidebar) return;
            sidebar.querySelectorAll('.nav-link[data-auth]').forEach(link => {
                link.closest('li').remove();
            });
            if (isUserLoggedIn) {
                const authenticatedLinks = [{
                        section: 'tafsir',
                        text: 'Personal Tafsir'
                    },
                    {
                        section: 'themes',
                        text: 'Thematic Linker'
                    },
                    {
                        section: 'roots',
                        text: 'Root Word Analyzer'
                    },
                    {
                        section: 'recitation',
                        text: 'Recitation Log'
                    },
                    {
                        section: 'hifz',
                        text: 'Memorization Hub'
                    },
                    {
                        section: 'goals',
                        text: 'My Goals'
                    },
                    {
                        section: 'reporting',
                        text: 'Reporting'
                    },
                    {
                        section: 'search',
                        text: 'Advanced Search'
                    },
                    {
                        section: 'data',
                        text: 'Data Management'
                    },
                ];
                authenticatedLinks.forEach(linkInfo => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = `#${linkInfo.section}`;
                    a.className = `nav-link`;
                    a.dataset.section = linkInfo.section;
                    a.dataset.auth = 'true';
                    a.textContent = linkInfo.text;
                    li.appendChild(a);
                    sidebar.appendChild(li);
                    a?.addEventListener('click', (event) => {
                        event.preventDefault();
                        const sectionId = event.currentTarget.dataset.section;
                        if (sectionId && typeof showSection === 'function') {
                            showSection(sectionId);
                        }
                    });
                });
            } else {
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                const quranLink = document.querySelector('.nav-link[data-section="quran"]');
                if (quranLink) quranLink.classList.add('active');
                document.querySelectorAll('.section:not(#quran)').forEach(section => {
                    section.innerHTML = '<p class="text-center">Please login to access this feature.</p>';
                });
            }
        }

        function setupAdminTranslationUI() {
            const adminTranslationGroup = document.querySelector('.quran-controls.flex-group.mb-20:has(#admin-translation-lang)');
            if (!adminTranslationGroup) return;
            if (userRole === 'admin' || userRole === 'registered') {
                adminTranslationGroup.style.display = 'flex';
                const langSelect = document.getElementById('admin-translation-lang');
                const textarea = document.getElementById('admin-translation-text');
                const saveBtn = document.getElementById('admin-save-translation-btn');
                langSelect?.addEventListener('change', async () => {
                    const selectedLang = langSelect.value;
                    const currentAyahData = await sendAjaxRequest('load_quran_ayah', {
                        surah: currentSurah,
                        ayah: currentAyah
                    });
                    if (currentAyahData.success && currentAyahData.data) {
                        textarea.value = currentAyahData.data[selectedLang] || '';
                    } else {
                        textarea.value = '';
                    }
                });
                saveBtn?.addEventListener('click', async () => {
                    const selectedLang = langSelect.value;
                    const newTranslation = textarea.value;
                    try {
                        const result = await sendAjaxRequest('admin_update_quran_translation', {
                            surah: currentSurah,
                            ayah: currentAyah,
                            lang_key: selectedLang,
                            translation_text: newTranslation
                        });
                        if (result.success) {
                            alert(`Translation for ${selectedLang} saved!`);
                            loadAyah(currentSurah, currentAyah);
                        } else {
                            alert(`Failed to save translation: ${result.message}`);
                        }
                    } catch (error) {
                        alert(`An error occurred: ${error.message}`);
                    }
                });
                langSelect.value = document.getElementById('translation-select').value || 'urdu';
                langSelect.dispatchEvent(new Event('change'));
            } else {
                adminTranslationGroup.style.display = 'none';
            }
        }
        let eventListenersInitialized = false;

        function setupEventListeners() {
            if (eventListenersInitialized) {
                return;
            }
            document.querySelector('.sidebar nav')?.addEventListener('click', (e) => {
                if (e.target.matches('a.nav-link[data-section]')) {
                    e.preventDefault();
                    showSection(e.target.dataset.section);
                }
            });
            const hamburgerBtn = document.getElementById('hamburger-btn');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            const body = document.body;
            if (hamburgerBtn && sidebar && overlay && body) {
                const toggleSidebar = () => {
                    const isOpening = !body.classList.contains('sidebar-open');
                    body.classList.toggle('sidebar-open');
                    hamburgerBtn.setAttribute('aria-expanded', isOpening);
                };
                hamburgerBtn?.addEventListener('click', toggleSidebar);
                overlay?.addEventListener('click', toggleSidebar);
                sidebar?.addEventListener('click', (e) => {
                    if (e.target.matches('a.nav-link') && body.classList.contains('sidebar-open')) {
                        toggleSidebar();
                    }
                });
            }
            document.getElementById('surah-select')?.addEventListener('change', (event) => {
                currentSurah = parseInt(event.target.value, 10);
                updateAyahSelect(currentSurah);
                loadAyah(currentSurah, currentAyah);
            });
            document.getElementById('ayah-select')?.addEventListener('change', (event) => {
                currentAyah = parseInt(event.target.value, 10);
                loadAyah(currentSurah, currentAyah);
            });
            document.getElementById('translation-select')?.addEventListener('change', async (event) => {
                const selectedKey = event.target.value;
                if (selectedKey === 'pashto') {
                    showPashtoNoticeOnce();
                }
                const selectedOption = event.target.selectedOptions[0];
                await loadAyah(currentSurah, currentAyah);
            });
            if (isUserLoggedIn) {
                document.getElementById('save-tafsir-btn')?.addEventListener('click', saveTafsir);
                document.getElementById('add-theme-btn')?.addEventListener('click', addTheme);
                document.getElementById('link-ayah-to-theme-btn')?.addEventListener('click', linkAyahToTheme);
                document.getElementById('link-theme-select')?.addEventListener('change', displayLinkedAyahsForCurrentTheme);
                document.getElementById('analyze-root-btn')?.addEventListener('click', analyzeRoot);
                document.getElementById('save-root-notes-btn')?.addEventListener('click', saveRootNotes);
                document.getElementById('save-recitation-btn')?.addEventListener('click', saveRecitationLog);
                document.getElementById('rec-surah-select')?.addEventListener('change', (event) => {
                    const surah = parseInt(event.target.value, 10);
                    const totalAyahs = surahAyahCounts[surah];
                    document.getElementById('rec-ayah-start').max = totalAyahs;
                    document.getElementById('rec-ayah-end').max = totalAyahs;
                });
                document.getElementById('hifz-surah-select')?.addEventListener('change', (event) => {
                    loadHifzForSurah(parseInt(event.target.value, 10));
                });
                document.getElementById('perform-search-btn')?.addEventListener('click', performSearch);
                document.getElementById('export-data-btn')?.addEventListener('click', exportData);
                document.getElementById('import-file')?.addEventListener('change', (event) => {
                    document.getElementById('import-data-btn').disabled = !event.target.files[0];
                });
                document.getElementById('import-data-btn')?.addEventListener('click', () => {
                    const fileInput = document.getElementById('import-file');
                    if (fileInput.files.length > 0) importData(fileInput.files[0]);
                    else setStatusMessage('import-status', 'Select file to import.', true);
                });
                document.getElementById('clear-data-btn')?.addEventListener('click', clearAllPersonalData);
                setupTafsirExportButtons();
            }
            document.getElementById('theme-switcher')?.addEventListener('change', (event) => applyTheme(event.target.value));
            document.querySelectorAll('.modal .close-button').forEach(button => {
                button?.addEventListener('click', (event) => event.target.closest('.modal').style.display = 'none');
            });
            window?.addEventListener('click', (event) => {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (event.target === modal) modal.style.display = 'none';
                });
            });
            window?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    document.querySelectorAll('.modal').forEach(modal => modal.style.display = 'none');
                }
            });
            setupTafsirExportButtons();

            if (userRole === 'admin') {
                setupAdminTranslationUI();
            }
            document.querySelectorAll('input[name="root-view-mode"]').forEach(radio => {
                radio?.addEventListener('change', function() {
                    const newViewMode = this.value;
                    const rootContents = document.querySelectorAll('.root-view-content');
                    rootContents.forEach(el => {
                        el.classList.remove('active-view');
                        el.style.display = 'none';
                    });
                    document.getElementById('root-graph-placeholder').style.display = 'none';
                    const paginationControls = document.getElementById('root-graph-pagination-controls');
                    const occurrencesListElement = document.getElementById('root-occurrences-list');
                    const graphContainerWrapper = document.getElementById('root-network-graph-container');
                    const graphContainer = document.getElementById('root-network-graph');
                    const analyzedRootTerm = document.getElementById('analyzed-root-term').textContent;
                    if (newViewMode === 'list') {
                        occurrencesListElement.classList.add('active-view');
                        occurrencesListElement.style.display = 'block';
                        paginationControls.style.display = 'none';
                        if (analyzedRootTerm !== 'N/A' && allRootOccurrencesCache.length > 0) {
                            occurrencesListElement.innerHTML = '';
                            allRootOccurrencesCache.forEach(occ => {
                                const li = document.createElement('li');
                                li.innerHTML = `
                                    <strong>Surah ${occ.surah}:${occ.ayah}</strong> - Word: <span lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${occ.word}</span>
                                    <div class="result-context" lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${occ.context}</div>
                                `;
                                occurrencesListElement.appendChild(li);
                            });
                        } else if (analyzedRootTerm === 'N/A') {
                            occurrencesListElement.innerHTML = '<li>Enter a root word and click "Analyze Root".</li>';
                        } else {
                            occurrencesListElement.innerHTML = '<li>No occurrences found or error in analysis.</li>';
                        }
                    } else if (newViewMode === 'tree') {
                        graphContainerWrapper.classList.add('active-view');
                        graphContainerWrapper.style.display = 'block';
                        occurrencesListElement.innerHTML = '<li>Graph view active. Results may be paginated below if applicable.</li>';
                        if (analyzedRootTerm !== 'N/A' && allRootOccurrencesCache.length > 0) {
                            updateRootGraphView();
                            paginationControls.style.display = (allRootOccurrencesCache.length > rootGraphItemsPerPage) ? 'flex' : 'none';
                        } else if (analyzedRootTerm !== 'N/A' && allRootOccurrencesCache.length === 0 && graphContainer.children.length === 0) {
                            analyzeRoot();
                        } else if (analyzedRootTerm === 'N/A') {
                            graphContainer.innerHTML = '';
                            document.getElementById('root-graph-placeholder').style.display = 'block';
                            document.getElementById('root-graph-placeholder').textContent = 'Enter a root and click "Analyze Root" to see the graph.';
                            paginationControls.style.display = 'none';
                        }
                    }
                });
            });

            function goToPrevRootGraphPage() {
                if (currentRootGraphPage > 1) {
                    currentRootGraphPage--;
                    updateRootGraphView();
                }
            }

            function goToNextRootGraphPage() {
                const totalPages = Math.ceil(allRootOccurrencesCache.length / rootGraphItemsPerPage);
                if (currentRootGraphPage < totalPages) {
                    currentRootGraphPage++;
                    updateRootGraphView();
                }
            }
            document.getElementById('prev-root-graph-page-btn')?.addEventListener('click', goToPrevRootGraphPage);
            document.getElementById('next-root-graph-page-btn')?.addEventListener('click', goToNextRootGraphPage);
            let graphCloseButton = null;
            document.querySelectorAll('input[name="root-view-mode"]').forEach(radio => {
                radio?.addEventListener('change', function() {
                    const newViewMode = this.value;
                    const rootSection = document.getElementById('roots');
                    const graphContainerWrapper = document.getElementById('root-network-graph-container');
                    const occurrencesListElement = document.getElementById('root-occurrences-list');
                    const mainContent = document.querySelector('.main-content');
                    const header = document.querySelector('header');
                    const sidebar = document.querySelector('.sidebar');
                    if (graphCloseButton && graphCloseButton.parentNode) {
                        graphCloseButton.parentNode.removeChild(graphCloseButton);
                        graphCloseButton = null;
                    }
                    if (rootNodePopupEl) rootNodePopupEl.style.display = 'none';
                    activeRootNodeIdForPopup = null;
                    if (newViewMode === 'tree') {
                        document.body.classList.add('graph-fullscreen-active');
                        graphContainerWrapper.classList.add('fullscreen-graph');
                        if (header) header.style.display = 'none';
                        if (sidebar) sidebar.style.display = 'none';
                        mainContent.style.overflowY = 'hidden';
                        occurrencesListElement.classList.remove('active-view');
                        occurrencesListElement.style.display = 'none';
                        graphContainerWrapper.classList.add('active-view');
                        graphContainerWrapper.style.display = 'flex';
                        graphCloseButton = document.createElement('button');
                        graphCloseButton.textContent = 'Close Graph (Esc)';
                        graphCloseButton.classList.add('graph-fullscreen-close-btn');
                        graphCloseButton.onclick = () => {
                            document.getElementById('root-view-list').click();
                        };
                        graphContainerWrapper.appendChild(graphCloseButton);
                        if (allRootOccurrencesCache.length > 0 || document.getElementById('analyzed-root-term').textContent !== 'N/A') {
                            updateRootGraphView();
                        } else {
                            document.getElementById('root-network-graph').innerHTML = '';
                            document.getElementById('root-graph-placeholder').textContent = 'Analyze a root to see the graph.';
                            document.getElementById('root-graph-placeholder').style.display = 'block';
                        }
                        const paginationControls = document.getElementById('root-graph-pagination-controls');
                        if (paginationControls) {
                            paginationControls.style.display = (allRootOccurrencesCache.length > rootGraphItemsPerPage) ? 'flex' : 'none';
                        }
                    } else {
                        document.body.classList.remove('graph-fullscreen-active');
                        graphContainerWrapper.classList.remove('fullscreen-graph');
                        if (header) header.style.display = '';
                        if (sidebar) sidebar.style.display = '';
                        mainContent.style.overflowY = 'auto';
                        graphContainerWrapper.classList.remove('active-view');
                        graphContainerWrapper.style.display = 'none';
                        occurrencesListElement.classList.add('active-view');
                        occurrencesListElement.style.display = 'block';
                        document.getElementById('root-graph-pagination-controls').style.display = 'none';
                        const analyzedRootTermText = document.getElementById('analyzed-root-term').textContent;
                        if (analyzedRootTermText !== 'N/A' && allRootOccurrencesCache.length > 0) {
                            occurrencesListElement.innerHTML = '';
                            allRootOccurrencesCache.forEach(occ => {
                                const li = document.createElement('li');
                                li.innerHTML = `<strong>Surah ${occ.surah}:${occ.ayah}</strong> - Word: <span lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${occ.word}</span><div class="result-context" lang="ar" dir="rtl" style="font-family: var(--font-arabic);">${occ.context}</div>`;
                                occurrencesListElement.appendChild(li);
                            });
                        } else if (analyzedRootTermText === 'N/A' || analyzedRootTermText.trim() === '') {
                            occurrencesListElement.innerHTML = '<li>Enter a root word and click "Analyze Root".</li>';
                        } else {
                            occurrencesListElement.innerHTML = '<li>No occurrences found or error in analysis.</li>';
                        }
                    }
                });
            });
            window?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && document.body.classList.contains('graph-fullscreen-active')) {
                    if (graphCloseButton) {
                        graphCloseButton.click();
                    }
                }
            });
            window?.addEventListener('beforeunload', handlePageUnload);
            setupQuranViewSwitcher();
            eventListenersInitialized = true;
        }
        document?.addEventListener('DOMContentLoaded', async () => {
            setupAuthEventListeners();
            const loginMessageEl = document.querySelector('#login-modal .form-message');
            const registerMessageEl = document.querySelector('#register-modal .form-message');
            if (loginMessageEl && loginMessageEl.textContent.trim() !== '') {
                document.getElementById('login-modal').style.display = 'flex';
            } else if (registerMessageEl && registerMessageEl.textContent.trim() !== '') {
                document.getElementById('register-modal').style.display = 'flex';
            }
            if (!isUserLoggedIn) {
                await loadQuranData();
                loadTranslationPreference();
                setupEventListeners();
                initializeReportingModule_Enhanced();
            } else {
                try {
                    await loadQuranData();
                    loadTranslationPreference();
                    setupEventListeners();
                    displayThemesList();
                    setupTafsirExportButtons();
                    setupGameModal();
                    initializeReportingModule_Enhanced();
                    initializeGoalsModule();
                    if (window.rootNodePopupEl) {
                        window.rootNodePopupEl = document.getElementById('root-node-popup');
                    } else {
                        window.rootNodePopupEl = document.createElement('div');
                        window.rootNodePopupEl.id = 'root-node-popup';
                        document.body.appendChild(window.rootNodePopupEl);
                    }
                } catch (error) {
                    console.error("App initialization failed for logged-in user:", error);
                    hideLoading();
                    alert("Failed to initialize: " + error.message + "\nPlease clear website data and refresh.");
                }
            }
        });
        let currentQuranView = 'single';
        let tooltipTimeout;

        function setupQuranViewSwitcher() {
            document.querySelectorAll('input[name="quran-view-mode"]').forEach(radio => {
                radio?.addEventListener('change', function() {
                    currentQuranView = this.value;
                    document.getElementById('quran-display').style.display = (currentQuranView === 'single') ? 'block' : 'none';
                    document.getElementById('quran-continuous-display').style.display = (currentQuranView === 'continuous') ? 'block' : 'none';
                    document.getElementById('word-translation-area').style.display = (currentQuranView === 'single') ? 'block' : 'none';
                    loadAyah(currentSurah, currentAyah);
                });
            });
        }
        async function updateTafsirAndThemeViews() {
            if (!isUserLoggedIn || !currentSurah || !currentAyah) return;
            const tafsirAyahDisplay = document.getElementById('current-ayah-tafsir');
            const themeAyahDisplay = document.getElementById('current-ayah-theme-text');
            const tafsirNotes = document.getElementById('tafsir-notes');
            const themeAyahRef = document.getElementById('current-ayah-theme-ref');
            try {
                const quranResult = await sendAjaxRequest('load_quran_ayah', {
                    surah: currentSurah,
                    ayah: currentAyah
                });
                if (!quranResult.success || !quranResult.data) throw new Error("Quran data not found.");
                const quran = quranResult.data;
                const tafsirResult = await sendAjaxRequest('get_tafsir', {
                    surah: currentSurah,
                    ayah: currentAyah
                });
                const tafsir = tafsirResult.success ? tafsirResult.notes : '';
                if (quran) {
                    const ayahElement = document.createElement('div');
                    ayahElement.classList.add('ayah');
                    ayahElement.innerHTML = `
                        <div class="ayah-number">Surah ${currentSurah}:${currentAyah} (${surahNames[currentSurah - 1]})</div>
                        <div class="ayah-arabic">${quran.arabic}</div>
                    `;
                    tafsirAyahDisplay.innerHTML = '';
                    tafsirAyahDisplay.appendChild(ayahElement.cloneNode(true));
                    tafsirNotes.value = tafsir;
                    themeAyahDisplay.innerHTML = '';
                    themeAyahDisplay.appendChild(ayahElement.cloneNode(true));
                    themeAyahRef.textContent = `${currentSurah}:${currentAyah}`;
                    await displayLinkedAyahsForCurrentTheme();
                }
            } catch (error) {
                console.error("Error updating secondary views:", error);
            }
        }

        function addWordClickListeners() {
            document.querySelectorAll('.ayah-arabic span').forEach(wordSpan => {
                wordSpan.removeEventListener('click', handleWordClick);
                wordSpan?.addEventListener('click', handleWordClick);
                wordSpan.removeEventListener('focus', handleWordFocus);
                wordSpan?.addEventListener('focus', handleWordFocus);
                wordSpan.removeEventListener('blur', handleWordBlur);
                wordSpan?.addEventListener('blur', handleWordBlur);
            });
        }

        function handleWordFocus(event) {
            handleWordClick(event);
        }

        function addWordTooltipListeners() {
            document.querySelectorAll('#quran-continuous-display .arabic-word').forEach(wordSpan => {
                wordSpan.addEventListener('click', (event) => {
                    // Make the translation area visible for continuous view clicks
                    document.getElementById('word-translation-area').style.display = 'block';
                    handleWordClick(event); // Reuse the existing click handler logic
                });
            });
        }

        function handleWordBlur(event) {
            event.target.style.backgroundColor = 'transparent';
        }
        async function loadPublicContent() {
            const tafsirList = document.getElementById('public-tafsir-list');
            const themesList = document.getElementById('public-themes-list');
            const rootsList = document.getElementById('public-roots-list');

            tafsirList.innerHTML = '<li>Loading...</li>';
            themesList.innerHTML = '<li>Loading...</li>';
            rootsList.innerHTML = '<li>Loading...</li>';

            const result = await sendAjaxRequest('get_public_content');

            if (result.success) {
                const {
                    tafsir,
                    themes,
                    root_words
                } = result.data;

                tafsirList.innerHTML = tafsir.length > 0 ?
                    tafsir.map(t => `<li><span class="item-surah-ayah" data-surah="${t.surah}" data-ayah="${t.ayah}">S ${t.surah}:${t.ayah}</span> by <strong>${t.full_name || t.username}</strong><span class="item-notes">${t.notes.substring(0, 150)}...</span></li>`).join('') :
                    '<li>No public Tafsir notes available yet.</li>';

                themesList.innerHTML = themes.length > 0 ?
                    themes.map(t => `<li><span class="item-ref">${t.name}</span> by <strong>${t.full_name || t.username}</strong><span class="item-notes">${t.description || ''}</span></li>`).join('') :
                    '<li>No public themes available yet.</li>';

                rootsList.innerHTML = roots.length > 0 ?
                    roots.map(r => `<li><span class="item-ref" lang="ar" dir="rtl">${r.root}</span> by <strong>${r.full_name || r.username}</strong><span class="item-notes">${r.description || ''}</span></li>`).join('') :
                    '<li>No public root word notes available yet.</li>';

                // Make Surah:Ayah links clickable
                tafsirList.querySelectorAll('.item-surah-ayah').forEach(el => el.addEventListener('click', e => {
                    const s = parseInt(e.currentTarget.dataset.surah);
                    const a = parseInt(e.currentTarget.dataset.ayah);
                    loadAyah(s, a);
                    showSection('quran');
                }));

            } else {
                const errorMsg = '<li>Failed to load public content.</li>';
                tafsirList.innerHTML = errorMsg;
                themesList.innerHTML = errorMsg;
                rootsList.innerHTML = errorMsg;
            }
        }

        function showSection(sectionId) {
            document.querySelectorAll('.section').forEach(section => {
                section.classList.remove('active');
                section.setAttribute('aria-hidden', 'true');
            });
            const activeSection = document.getElementById(sectionId);
            if (activeSection) {
                activeSection.classList.add('active');
                activeSection.setAttribute('aria-hidden', 'false');
                activeSection.focus();
            }
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                link.setAttribute('aria-current', 'false');
            });
            const activeLink = document.querySelector(`.nav-link[data-section="${sectionId}"]`);
            if (activeLink) {
                activeLink.classList.add('active');
                activeLink.setAttribute('aria-current', 'page');
            }
            if (isUserLoggedIn) {
                if (sectionId === 'community') {
                    loadPublicContent();
                } else if (isUserLoggedIn) { // Note the change from 'if' to 'else if'
                    if (sectionId === 'tafsir' || sectionId === 'themes') {
                        updateTafsirAndThemeViews();
                    } else if (sectionId === 'themes') {
                        populateThemeSelects();
                        displayLinkedAyahsForCurrentTheme();
                    } else if (sectionId === 'recitation') {
                        loadRecitationLogs();
                    } else if (sectionId === 'hifz') {
                        const hifzSurahSelect = document.getElementById('hifz-surah-select');
                        if (hifzSurahSelect && hifzSurahSelect.value) {
                            loadHifzForSurah(parseInt(hifzSurahSelect.value, 10));
                        }
                    } else if (sectionId === 'goals') {
                        if (typeof renderGoalsUI === 'function') renderGoalsUI();
                    } else if (sectionId === 'reporting') {
                        if (typeof loadAndDisplayReportData_Enhanced === 'function') loadAndDisplayReportData_Enhanced();
                    }
                }
            }
        }

        function showLoading(primaryMessage, secondaryMessage = "Initializing...", initialPercentage = 0) {
            const loadingOverlay = document.getElementById('loading-overlay');
            const primaryMsgEl = document.getElementById('loading-message-primary');
            const secondaryMsgEl = document.getElementById('loading-message-secondary');
            const progressBarEl = document.getElementById('loading-progress-bar');
            const percentageEl = document.getElementById('loading-percentage');
            const firstTimeNoticeEl = document.getElementById('loading-first-time-notice');
            if (primaryMsgEl) primaryMsgEl.textContent = primaryMessage;
            if (secondaryMsgEl) secondaryMsgEl.textContent = secondaryMessage;
            if (progressBarEl) progressBarEl.style.width = `${initialPercentage}%`;
            if (percentageEl) percentageEl.textContent = `${initialPercentage}%`;
            const currentTheme = document.body.className.includes('theme-manuscript') ? 'manuscript' :
                document.body.className.includes('theme-holo') ? 'holo' : 'serene';
            const loadingContent = loadingOverlay.querySelector('.loading-content');
            if (loadingContent) {
                if (currentTheme === 'holo') {
                    loadingContent.style.backgroundColor = 'var(--color-bg-secondary)';
                    loadingContent.style.color = 'var(--color-text-primary)';
                    if (document.getElementById('loading-progress-bar-container')) document.getElementById('loading-progress-bar-container').style.backgroundColor = '#26a69a';
                    if (progressBarEl) progressBarEl.style.backgroundColor = 'var(--color-accent)';
                } else if (currentTheme === 'manuscript') {
                    loadingContent.style.backgroundColor = 'var(--color-bg-secondary)';
                    loadingContent.style.color = 'var(--color-text-primary)';
                    if (document.getElementById('loading-progress-bar-container')) document.getElementById('loading-progress-bar-container').style.backgroundColor = 'var(--color-border)';
                    if (progressBarEl) progressBarEl.style.backgroundColor = 'var(--color-accent)';
                } else {
                    loadingContent.style.backgroundColor = 'var(--color-bg-secondary)';
                    loadingContent.style.color = 'var(--color-text-primary)';
                    if (document.getElementById('loading-progress-bar-container')) document.getElementById('loading-progress-bar-container').style.backgroundColor = 'var(--color-border)';
                    if (progressBarEl) progressBarEl.style.backgroundColor = 'var(--color-accent)';
                }
            }
            loadingOverlay.style.display = 'flex';
            document.body.setAttribute('aria-busy', 'true');
        }

        function updateLoadingProgress(unitsIncrement, secondaryMessage) {
            if (totalProgressUnits === 0) return;
            completedProgressUnits += unitsIncrement;
            const percentage = Math.min(100, Math.round((completedProgressUnits / totalProgressUnits) * 100));
            const secondaryMsgEl = document.getElementById('loading-message-secondary');
            const progressBarEl = document.getElementById('loading-progress-bar');
            const percentageEl = document.getElementById('loading-percentage');
            if (secondaryMsgEl) secondaryMsgEl.textContent = secondaryMessage;
            if (progressBarEl) progressBarEl.style.width = `${percentage}%`;
            if (percentageEl) percentageEl.textContent = `${percentage}%`;
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
            document.body.setAttribute('aria-busy', 'false');
            completedProgressUnits = 0;
            totalProgressUnits = 0;
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
                }, 7000);
            }
        }
        async function saveTafsir() {
            if (!isUserLoggedIn) {
                setStatusMessage('tafsir-status', 'Login to save Tafsir.', true);
                return;
            }
            const notes = document.getElementById('tafsir-notes').value.trim();
            if (!notes) {
                setStatusMessage('tafsir-status', 'Tafsir notes cannot be empty.', true);
                return;
            }
            if (currentSurah === 0 || currentAyah === 0) {
                setStatusMessage('tafsir-status', 'Navigate to an Ayah first.', true);
                return;
            }
            showLoading(`Saving Tafsir for ${currentSurah}:${currentAyah}...`);
            try {
                const result = await sendAjaxRequest('save_tafsir', {
                    surah: currentSurah,
                    ayah: currentAyah,
                    notes: notes
                });
                if (result.success) {
                    setStatusMessage('tafsir-status', `Tafsir saved for ${currentSurah}:${currentAyah}.`, false);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                setStatusMessage('tafsir-status', 'Failed to save Tafsir.', true);
            } finally {
                hideLoading();
            }
        }
        async function handleIndexThemeClick(event) {
            event.preventDefault();
            const clickedAnchorElement = event.currentTarget;
            if (!clickedAnchorElement || !clickedAnchorElement.dataset) {
                console.error("handleIndexThemeClick: clickedAnchorElement or its dataset is invalid.", clickedAnchorElement);
                return;
            }
            const themeId = clickedAnchorElement.dataset.themeId;
            const isStatic = clickedAnchorElement.dataset.isStatic === 'true';
            const exampleSurahStr = clickedAnchorElement.dataset.exampleSurah;
            const exampleAyahStr = clickedAnchorElement.dataset.exampleAyah;
            if (!themeId) {
                console.error("Theme ID missing from clicked element's dataset.");
                return;
            }
            const indexPanel = document.getElementById('fsReaderIndexPanel');
            if (indexPanel) indexPanel.style.display = 'none';
            stopAndClearAudio();
            let targetSurah, targetAyah;
            if (isStatic) {
                targetSurah = exampleSurahStr ? parseInt(exampleSurahStr) : NaN;
                targetAyah = exampleAyahStr ? parseInt(exampleAyahStr) : NaN;
                const staticTheme = staticQuranicThemes.find(st => st.id === themeId);
                if (staticTheme) {
                    const surahTitleEl = document.getElementById('fsReaderSurahTitle');
                    const pageInfoEl = document.getElementById('fsReaderPageInfo');
                    if (surahTitleEl) surahTitleEl.textContent = `Theme: ${staticTheme.name}`;
                    if (pageInfoEl) {
                        if (isNaN(targetSurah) || isNaN(targetAyah)) {
                            pageInfoEl.textContent = "(Example Ayah not set or invalid)";
                        } else {
                            pageInfoEl.textContent = `(Example: S${targetSurah}:A${targetAyah})`;
                        }
                    }
                }
            } else {
                try {
                    const dbThemeId = parseInt(themeId);
                    if (isNaN(dbThemeId)) {
                        console.error("Invalid DB Theme ID:", themeId);
                        return;
                    }
                    const result = await sendAjaxRequest('get_linked_ayahs_for_theme', {
                        theme_id: dbThemeId
                    });
                    const allThemeAyahLinks = result.success ? result.data : [];
                    const linksForThisTheme = allThemeAyahLinks
                        .filter(link => link.theme_id === dbThemeId)
                        .sort((a, b) => (a.surah !== b.surah) ? a.surah - b.surah : a.ayah - b.ayah);
                    if (linksForThisTheme.length > 0) {
                        const firstLink = linksForThisTheme[0];
                        targetSurah = firstLink.surah;
                        targetAyah = firstLink.ayah;
                    } else {
                        alert("No Ayahs are currently linked to this user-defined theme.");
                        const themeDataResult = await sendAjaxRequest('get_theme', {
                            theme_id: dbThemeId
                        });
                        const themeData = themeDataResult.success ? themeDataResult.data : null;
                        const surahTitleEl = document.getElementById('fsReaderSurahTitle');
                        const pageInfoEl = document.getElementById('fsReaderPageInfo');
                        if (themeData && surahTitleEl) surahTitleEl.textContent = `Theme: ${themeData.name}`;
                        if (pageInfoEl) pageInfoEl.textContent = "(No linked Ayahs)";
                        return;
                    }
                } catch (error) {
                    console.error("Error fetching links for DB theme:", error);
                    alert("Could not retrieve linked Ayahs for this theme.");
                    return;
                }
            }
            if (!isNaN(targetSurah) && !isNaN(targetAyah) && targetSurah >= 1 && targetSurah <= 114 && targetAyah >= 1) {
                const maxAyahs = (surahAyahCounts[targetSurah]) ? surahAyahCounts[targetSurah] : 0;
                if (maxAyahs > 0 && targetAyah <= maxAyahs) {
                    fullScreenReaderCurrentSurah = targetSurah;
                    fullScreenReaderCurrentAyah = targetAyah;
                    if (fullScreenReaderViewMode === 'paged') {
                        fullScreenReaderCurrentPage = surahToPageEnhanced(targetSurah, targetAyah);
                        await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
                        setTimeout(() => highlightAndScrollToAyahInPage(targetSurah, targetAyah), 300);
                    } else {
                        continuousScrollNextSurahToLoad = targetSurah;
                        continuousScrollNextAyahToLoad = targetAyah;
                        await initializeContinuousScroll();
                    }
                } else {
                    console.warn(`Invalid Ayah number ${targetAyah} for Surah ${targetSurah}. Max is ${maxAyahs}. Cannot navigate.`);
                    if (isStatic) alert(`The example Ayah (S${targetSurah}:A${targetAyah}) for this static theme is invalid.`);
                }
            } else if (isStatic) {} else {
                console.warn(`Navigation aborted for theme "${themeId}" due to invalid targetSurah/Ayah.`);
            }
        }
        let isLoadingThemesListGlobalFlag = false;
        async function handleGoToAyahFromThemeModal(event) {
            const surah = parseInt(event.currentTarget.getAttribute('data-surah'), 10);
            const ayah = parseInt(event.currentTarget.getAttribute('data-ayah'), 10);
            if (!isNaN(surah) && !isNaN(ayah)) {
                const modal = document.getElementById('themeAyahsModal');
                if (modal) {
                    modal.style.display = 'none';
                }
                if (typeof loadAyah === 'function' && typeof window.showSection === 'function') {
                    await loadAyah(surah, ayah);
                    window.showSection('quran');
                } else {
                    console.error("loadAyah or showSection function not found.");
                    alert("Error navigating to Ayah. Required functions are missing.");
                }
            } else {
                console.warn("Invalid Surah/Ayah data on clicked element:", event.currentTarget.dataset);
            }
        }
        async function handleViewThemeAyahs(event) {
            if (!isUserLoggedIn) {
                alert('Login to view theme details.');
                return;
            }
            const themeId = parseInt(event.target.closest('[data-theme-id]').getAttribute('data-theme-id'), 10);
            if (isNaN(themeId)) {
                console.warn("handleViewThemeAyahs: Could not determine themeId from event target.", event.target);
                return;
            }
            showLoading("Loading linked ayahs...");
            try {
                const themeResult = await sendAjaxRequest('get_theme', {
                    theme_id: themeId
                });
                const theme = themeResult.success ? themeResult.data : null;
                if (!theme) {
                    setStatusMessage('theme-manager-status', 'Theme not found.', true);
                    hideLoading();
                    return;
                }
                document.getElementById('modal-theme-name').textContent = theme.name;
                const listEl = document.getElementById('modal-linked-ayahs-list');
                listEl.innerHTML = '';
                const linkedAyahsResult = await sendAjaxRequest('get_linked_ayahs_for_theme', {
                    theme_id: themeId
                });
                if (linkedAyahsResult.success) {
                    const linkedAyahs = linkedAyahsResult.data;
                    if (linkedAyahs.length === 0) {
                        listEl.innerHTML = '<li>No ayahs linked yet.</li>';
                    } else {
                        linkedAyahs.sort((a, b) => (a.surah !== b.surah) ? a.surah - b.surah : a.ayah - b.ayah);
                        linkedAyahs.forEach(link => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <strong>
                                    <span class="item-surah-ayah theme-modal-ayah-link" 
                                        data-surah="${link.surah}" 
                                        data-ayah="${link.ayah}" 
                                        tabindex="0" 
                                        role="button"
                                        title="Go to Surah ${link.surah}, Ayah ${link.ayah} in Quran Viewer">
                                        Surah ${link.surah}:${link.ayah}
                                    </span>
                                </strong>
                                ${link.notes ? ` - <em>${link.notes.substring(0, 100)}${link.notes.length > 100 ? '...' : ''}</em>` : ''}
                                <button data-link-id="${link.id}" class="delete-theme-link-btn" style="margin-left: 10px;" aria-label="Unlink Ayah ${link.surah}:${link.ayah} from this theme">Unlink</button>
                            `;
                            listEl.appendChild(li);
                        });
                        listEl.querySelectorAll('.delete-theme-link-btn').forEach(button => {
                            const newButton = button.cloneNode(true);
                            button.parentNode.replaceChild(newButton, button);
                            newButton?.addEventListener('click', handleDeleteThemeLink);
                        });
                        listEl.querySelectorAll('.theme-modal-ayah-link').forEach(span => {
                            const newSpan = span.cloneNode(true);
                            span.parentNode.replaceChild(newSpan, span);
                            newSpan?.addEventListener('click', handleGoToAyahFromThemeModal);
                            newSpan?.addEventListener('keydown', (ev) => {
                                if (ev.key === 'Enter' || ev.key === ' ') {
                                    handleGoToAyahFromThemeModal(ev);
                                }
                            });
                        });
                    }
                } else {
                    throw new Error(linkedAyahsResult.message);
                }
            } catch (error) {
                setStatusMessage('theme-manager-status', 'Failed to load theme details.', true);
            } finally {
                hideLoading();
                document.getElementById('themeAyahsModal').style.display = 'flex';
                document.getElementById('themeAyahsModal').querySelector('.modal-content').setAttribute('data-current-theme-id', themeId);
                document.getElementById('themeAyahsModalTitle').focus();
            }
        }
        async function displayThemesList() {
            if (!isUserLoggedIn) {
                const themesListEl = document.getElementById('themes-list');
                if (themesListEl) themesListEl.innerHTML = '<li>Login to manage themes.</li>';
                return;
            }
            if (isLoadingThemesListGlobalFlag) {
                return;
            }
            isLoadingThemesListGlobalFlag = true;
            const themesListElement = document.getElementById('themes-list');
            if (!themesListElement) {
                console.error('[displayThemesList] Element with ID "themes-list" not found.');
                isLoadingThemesListGlobalFlag = false;
                return;
            }
            themesListElement.innerHTML = '';
            try {
                const result = await sendAjaxRequest('get_all_themes');
                if (result.success) {
                    const themes = result.data;
                    if (!themesListElement.parentNode) {
                        isLoadingThemesListGlobalFlag = false;
                        return;
                    }
                    if (themes.length === 0) {
                        themesListElement.innerHTML = '<li>No themes added yet.</li>';
                    } else {
                        themes.forEach(theme => {
                            const li = document.createElement('li');
                            li.innerHTML = `
                                <span data-theme-id="${theme.id}" class="view-theme-ayahs" tabindex="0" role="button" aria-label="View ayahs for theme ${theme.name}">${theme.name}</span>
                                <div class="theme-actions" style="display: inline-block;">
                                    <button data-theme-id="${theme.id}" class="delete-theme-btn" aria-label="Delete theme ${theme.name}">Delete</button>
                                </div>
                            `;
                            themesListElement.appendChild(li);
                        });
                        themesListElement.querySelectorAll('.view-theme-ayahs').forEach(span => {
                            const newSpan = span.cloneNode(true);
                            span.parentNode.replaceChild(newSpan, span);
                            newSpan?.addEventListener('click', handleViewThemeAyahs);
                            newSpan?.addEventListener('keydown', (e) => {
                                if (e.key === 'Enter' || e.key === ' ') handleViewThemeAyahs(e);
                            });
                        });
                        themesListElement.querySelectorAll('.delete-theme-btn').forEach(button => {
                            const newButton = button.cloneNode(true);
                            button.parentNode.replaceChild(newButton, button);
                            newButton?.addEventListener('click', handleDeleteTheme);
                        });
                    }
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error("[displayThemesList] Error:", error);
                if (themesListElement) {
                    themesListElement.innerHTML = `<li>Error loading themes: ${error.message}</li>`;
                }
            } finally {
                isLoadingThemesListGlobalFlag = false;
            }
        }

        function getCssVar(varName) {
            return getComputedStyle(document.documentElement).getPropertyValue(varName).trim();
        }

        function calculateNextReview(lastReviewDate, reviewCount) {
            const date = new Date(lastReviewDate);
            let daysToAdd = [1, 3, 7, 15, 30, 60, 90][Math.min(reviewCount, 6)] || 120;
            date.setDate(date.getDate() + daysToAdd);
            return date.toISOString().split('T')[0];
        }

        function highlightMatch(text, searchTerm) {
            if (!text || !searchTerm) return text;
            const regex = new RegExp(`(${searchTerm.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return text.replace(regex, '<mark>$1</mark>');
        }
        async function handleGoToAyahFromSearch(event) {
            const surah = parseInt(event.target.getAttribute('data-surah'), 10);
            const ayah = parseInt(event.target.getAttribute('data-ayah'), 10);
            if (!isNaN(surah) && !isNaN(ayah)) {
                await loadAyah(surah, ayah);
                showSection('quran');
            }
        }
        async function exportTafsirToDocx() {
            alert('Export to DOCX feature is not yet implemented.');
        }

        async function exportTafsirToMd() {
            alert('Export to Markdown feature is not yet implemented.');
        }

        async function exportTafsirToPdf() {
            if (typeof html2pdf === 'undefined') {
                alert('PDF generation library is missing. Cannot export.');
                return;
            }
            showLoading("Generating PDF...");
            try {
                const tafsirResult = await sendAjaxRequest('get_all_tafsir');
                const allTafsir = tafsirResult.success ? tafsirResult.data : [];

                if (allTafsir.length === 0) {
                    alert("You have no Tafsir notes to export.");
                    return;
                }

                allTafsir.sort((a, b) => (a.surah - b.surah) || (a.ayah - b.ayah));

                let contentHTML = `<h1>My Personal Tafsir</h1>`;
                for (const note of allTafsir) {
                    const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                        surah: note.surah,
                        ayah: note.ayah
                    });
                    const arabicText = ayahDataResult.success ? ayahDataResult.data.arabic : '';
                    contentHTML += `<div style="page-break-inside: avoid; margin-bottom: 2em; border-bottom: 1px solid #eee; padding-bottom: 1em;">
                        <h3>Surah ${surahNames[note.surah - 1]} (${note.surah}:${note.ayah})</h3>
                        <p style="font-family: var(--font-arabic); font-size: 1.5em; text-align: right; direction: rtl;">${arabicText}</p>
                        <p style="white-space: pre-wrap;">${note.notes.replace(/\n/g, '<br>')}</p>
                     </div>`;
                }

                const element = document.createElement('div');
                element.innerHTML = contentHTML;

                html2pdf().from(element).set({
                    margin: 1,
                    filename: 'my_tafsir_export.pdf',
                    jsPDF: {
                        unit: 'in',
                        format: 'letter',
                        orientation: 'portrait'
                    }
                }).save();

            } catch (error) {
                alert("An error occurred while generating the PDF.");
            } finally {
                hideLoading();
            }
        }

        function setupTafsirExportButtons() {
            const exportDocxBtn = document.getElementById('export-tafsir-to-docx-btn');
            if (exportDocxBtn) {
                exportDocxBtn?.addEventListener('click', exportTafsirToDocx);
            }
            const exportMdBtn = document.getElementById('export-tafsir-to-md-btn');
            if (exportMdBtn) {
                exportMdBtn?.addEventListener('click', exportTafsirToMd);
            }
            const exportPdfBtn = document.getElementById('export-tafsir-to-pdf-btn');
            if (exportPdfBtn) {
                exportPdfBtn?.addEventListener('click', exportTafsirToPdf);
            }
        }

        function exitBrowserFullscreen() {
            if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement) {
                if (document.exitFullscreen) {
                    document.exitFullscreen().catch(err => console.error("Error exiting fullscreen:", err.message, err.name));
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
                return true;
            }
            return false;
        }

        function requestBrowserFullscreenForDocument() {
            const docEl = document.documentElement;
            if (docEl.requestFullscreen) {
                docEl.requestFullscreen().catch(err => console.warn("FS Doc: Request failed:", err.message, err.name));
            } else if (docEl.webkitRequestFullscreen) {
                docEl.webkitRequestFullscreen();
            } else if (docEl.mozRequestFullScreen) {
                docEl.mozRequestFullScreen();
            } else if (docEl.msRequestFullscreen) {
                docEl.msRequestFullscreen();
            } else {
                console.warn("FS Doc: Browser Fullscreen API not supported for documentElement.");
            }
        }

        function loadTranslationPreference() {
            const selectElement = document.getElementById('translation-select');
            const storageKey = 'selectedTranslation';
            if (!selectElement) {
                console.error("Translation select element not found.");
                return;
            }
            selectElement?.addEventListener('change', () => {
                localStorage.setItem(storageKey, selectElement.value);
            });
            const savedValue = localStorage.getItem(storageKey);
            if (savedValue) {
                if (selectElement.querySelector(`option[value="${savedValue}"]`)) {
                    selectElement.value = savedValue;
                }
            }
        }

        function setupGameModal() {
            const gameModalHTML = `
                <div id="quranGameModal" class="modal game-modal" role="dialog" aria-modal="true" aria-labelledby="gameModalTitle" style="display: none;">
                    <div class="modal-content game-modal-content">
                        <span class="close-button game-close-button" aria-label="Close Game">×</span>
                        <h2 id="gameModalTitle">Quranic Games</h2>
                        <div class="game-selection-area"> 
                            <p>Choose a game to play:</p>
                            <button id="startGameWordWhiz" class="game-select-btn">Lughat-ul-Nur</button>
                            <button id="startGameAyahJumble" class="game-select-btn">Nazm-ul-Ayah</button>
                        </div>
                        <div id="gamePlayArea" class="game-play-area" style="display: none;">
                        </div>
                            <div id="gameScoreArea" class="game-score-area" style="display: none;">
                                <p>Score: <span id="gameCurrentScore">0</span></p>
                                <p>High Score: <span id="gameHighScore">0</span> (for this session)</p>
                            </div>
                            <div class="game-controls">
                                <button id="quitGameButton" style="display:none; background-color: var(--color-error); margin-top:15px;">Quit Current Game</button>
                            </div>
                        </div>
                    </div>
                `;
            document.body.insertAdjacentHTML('beforeend', gameModalHTML);
            const gameModalCSS = `
                .game-modal .modal-content {
                    max-width: 90%;
                    width: 881px; 
                    max-height: 98vh;
                    overflow-y: auto;
                    background-color: var(--color-bg-primary); 
                    color: var(--color-text-primary);
                }
                .game-modal-content h2 {
                    text-align: center;
                    color: var(--color-text-secondary);
                    margin-bottom: 0px;
                }
                .game-selection-area {
                    text-align: center;
                    margin-bottom: -3px;
                }
                .game-select-btn {
                    margin: 10px;
                    padding: 12px 20px;
                    font-size: 1.1rem;
                }
                .game-play-area {
                    padding: 8px;
                    border: 1px solid var(--color-border);
                    border-radius: var(--border-radius);
                    background-color: var(--color-bg-secondary);
                    min-height: fit-content;
                    text-align: center;
                }
                .game-question-arabic {
                    font-family: var(--font-arabic);
                    font-size: 2rem;
                    margin-bottom: 13px;
                    direction: rtl;
                }
                .game-options-list {
                    list-style: none;
                    padding: 0;
                    margin: 0 auto;
                    max-width: 400px; 
                }
                .game-options-list li button {
                    display: block;
                    width: 100%;
                    margin-bottom: 10px;
                    padding: 12px;
                    background-color: var(--color-accent);
                    border: 1px solid var(--color-accent-dark);
                    color: white;
                    font-family: var(--font-general); 
                }
                .game-options-list li button:hover {
                    background-color: var(--color-accent-dark);
                }
                .game-options-list li button.correct {
                    background-color: #1050c5 !important;
                }
                .game-options-list li button.incorrect {
                    background-color: var(--color-error) !important;
                }
                .game-feedback {
                    margin-top: 15px;
                    font-weight: bold;
                }
                .game-score-area {
                    text-align: center;
                    margin-top: 20px;
                    padding-top: 10px;
                    border-top: 1px solid var(--color-border);
                }
                .game-controls {
                    text-align: center;
                    margin-top: 20px;
                }
                .jumble-word-container {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: center;
                    gap: 10px;
                    margin-bottom: 20px;
                    min-height: 50px; 
                    padding: 10px;
                    border: 1px dashed var(--color-border);
                    border-radius: var(--border-radius);
                }
                .jumble-word {
                    font-family: var(--font-arabic);
                    font-size: 1.8rem;
                    padding: 8px 12px;
                    background-color: var(--color-bg-primary);
                    border: 1px solid var(--color-border);
                    border-radius: var(--border-radius);
                    cursor: grab;
                    user-select: none; 
                    direction: rtl;
                }
                .jumble-word.dragging {
                    opacity: 0.5;
                    background-color: var(--color-highlight);
                }
                .jumble-target-area {
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: flex-start; 
                    gap: 5px; 
                    padding: 10px;
                    border: 2px solid var(--color-accent);
                    border-radius: var(--border-radius);
                    min-height: 70px;
                    background-color: var(--color-bg-secondary);
                    direction: rtl; 
                }
                .jumble-target-area .jumble-word {
                    cursor: default; 
                }
                #jumbleSubmitAnswer { margin-top: 15px; }
                .game-modal.fullscreen {
                    padding: 0;
                }
                .game-modal.fullscreen .modal-content {
                    width: 100%;
                    max-width: 100%;
                    height: 100vh;
                    max-height: 100vh;
                    border-radius: 0;
                    display: flex;
                    flex-direction: column;
                }
                .game-modal.fullscreen .game-play-area {
                    flex-grow: 1;
                    overflow-y: auto;
                }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.type = "text/css";
            styleSheet.innerText = gameModalCSS;
            document.head.appendChild(styleSheet);
            const gameModal = document.getElementById('quranGameModal');
            const closeButton = gameModal.querySelector('.game-close-button');
            const startGameWordWhizBtn = document.getElementById('startGameWordWhiz');
            const startGameAyahJumbleBtn = document.getElementById('startGameAyahJumble');
            const quitGameButton = document.getElementById('quitGameButton');

            function performModalCloseActions(switchToQuranViewer = true) {
                if (gameModal.style.display !== 'none') {
                    const wasGameModalTheFullscreenElement = (
                        document.fullscreenElement === gameModal ||
                        document.webkitFullscreenElement === gameModal ||
                        document.mozFullScreenElement === gameModal ||
                        document.msFullscreenElement === gameModal
                    );
                    gameModal.style.display = 'none';
                    gameModal.classList.remove('fullscreen');
                    resetGameUI();
                    activeGame = null;
                    if (typeof recitationGame_State !== 'undefined' && recitationGame_State.gameActive) {
                        if (typeof stopReferenceAudio_Engine === 'function') stopReferenceAudio_Engine();
                        if (recitationGame_State.isRecording && typeof stopUserRecording_Recitation_Engine === 'function') stopUserRecording_Recitation_Engine();
                        if (recitationGame_State.speechRecognition && typeof recitationGame_State.speechRecognition.abort === 'function') recitationGame_State.speechRecognition.abort();
                        recitationGame_State.gameActive = false;
                        recitationGame_State.isRecording = false;
                    }
                    if (typeof ayahTypingGameActive !== 'undefined' && ayahTypingGameActive) {
                        if (typeof ayahTypingTimerInterval !== 'undefined' && ayahTypingTimerInterval) {
                            clearInterval(ayahTypingTimerInterval);
                            ayahTypingTimerInterval = null;
                        }
                        ayahTypingGameActive = false;
                    }
                    if (switchToQuranViewer && typeof showSection === 'function') {
                        showSection('quran');
                        const quranViewerSection = document.getElementById('quran');
                        if (quranViewerSection) {
                            quranViewerSection.setAttribute('tabindex', '-1');
                            quranViewerSection.focus();
                        }
                    }
                    if (wasGameModalTheFullscreenElement) {
                        if (exitBrowserFullscreen()) {
                            setTimeout(() => {
                                requestBrowserFullscreenForDocument();
                            }, 150);
                        } else {
                            console.log("exitBrowserFullscreen reported not in FS, but wasGameModalTheFullscreenElement was true. This is odd. Focusing Quran viewer.");
                            const quranViewerSection = document.getElementById('quran');
                            if (quranViewerSection) quranViewerSection.focus();
                        }
                    }
                }
            }

            function restoreModalInteractivity() {
                if (gameModal) {
                    gameModal.style.pointerEvents = '';
                }
            }

            function resetGameUI() {
                restoreModalInteractivity();
                setTimeout(() => {
                    const gamePlayArea = document.getElementById('gamePlayArea');
                    const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
                    const gameScoreArea = document.getElementById('gameScoreArea');
                    const quitGameButton = document.getElementById('quitGameButton');
                    const gameModalTitle = document.getElementById('gameModalTitle');
                    if (gameSelectionArea) {
                        gameSelectionArea.style.display = 'block';
                    } else {
                        console.error("[MainGameModal] resetGameUI: gameSelectionArea (.game-selection-area) not found.");
                    }
                    if (gamePlayArea) {
                        gamePlayArea.style.display = 'none';
                        gamePlayArea.innerHTML = '';
                    } else {
                        console.error("[MainGameModal] resetGameUI: gamePlayArea not found");
                    }
                    if (gameScoreArea) {
                        gameScoreArea.style.display = 'none';
                    } else {
                        console.error("[MainGameModal] resetGameUI: gameScoreArea not found");
                    }
                    if (quitGameButton) {
                        quitGameButton.style.display = 'none';
                    } else {
                        console.error("[MainGameModal] resetGameUI: quitGameButton not found");
                    }
                    if (gameModalTitle) {
                        gameModalTitle.textContent = "Quranic Games";
                    } else {
                        console.error("[MainGameModal] resetGameUI: gameModalTitle not found");
                    }
                }, 400);
            }
            closeButton?.addEventListener('click', () => {
                performModalCloseActions(true);
            });
            window?.addEventListener('click', (event) => {
                if (event.target === gameModal) {
                    performModalCloseActions(true);
                }
            });
            window?.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && gameModal.style.display === 'flex') {
                    performModalCloseActions(true);
                }
            });
            startGameWordWhizBtn?.addEventListener('click', () => startWordWhizGame());
            startGameAyahJumbleBtn?.addEventListener('click', () => startAyahJumbleGame());
            quitGameButton?.addEventListener('click', () => {
                activeGame = null;
                resetGameUI();
            });
            const sidebarNav = document.querySelector('.sidebar nav ul');
            if (sidebarNav) {
                const gameLi = document.createElement('li');
                const gameLink = document.createElement('a');
                gameLink.href = "#games";
                gameLink.textContent = "Quranic Games";
                gameLink.id = "openGamesModalBtn";
                gameLi.appendChild(gameLink);
                sidebarNav.appendChild(gameLi);
                gameLink?.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.querySelectorAll('.nav-link.active').forEach(l => l.classList.remove('active'));
                    gameLink.classList.add('active');
                    restoreModalInteractivity();
                    gameModal.style.display = 'flex';
                    gameModal.classList.add('fullscreen');
                    resetGameUI();
                });
            }
            addFlashcardSuiteButtonToModal();
            addAyahTypingGameButtonToModal();
            addEnhancedFullScreenReaderLaunchButton();
        }
        let activeGame = null;
        let gameScore = 0;
        let gameHighScore = 0;
        let wordWhizQuestions = [];
        let currentWordWhizQuestionIndex = 0;
        let ayahJumbleQuestion = null;

        function resetGameUI() {
            setTimeout(() => {
                const gamePlayArea = document.getElementById('gamePlayArea');
                const gameSelectionArea = document.getElementById('game-selection-area');
                const gameScoreArea = document.getElementById('gameScoreArea');
                const quitGameButton = document.getElementById('quitGameButton');
                const gameModalTitle = document.getElementById('gameModalTitle');
                if (!gameSelectionArea) {
                    if (!gamePlayArea) {}
                    const modalContent = document.querySelector('#quranGameModal .modal-content');
                    if (modalContent && !gameSelectionArea) {}
                } else {
                    gameSelectionArea.style.display = 'block';
                }
                if (gamePlayArea) {
                    gamePlayArea.style.display = 'none';
                    gamePlayArea.innerHTML = '';
                } else {}
                if (gameScoreArea) {
                    gameScoreArea.style.display = 'none';
                } else {
                    console.error("[MainGameModal] resetGameUI: gameScoreArea not found");
                }
                if (quitGameButton) {
                    quitGameButton.style.display = 'none';
                } else {
                    console.error("[MainGameModal] resetGameUI: quitGameButton not found");
                }
                if (gameModalTitle) {
                    gameModalTitle.textContent = "Quranic Games";
                } else {
                    console.error("[MainGameModal] resetGameUI: gameModalTitle not found");
                }
            }, 400);
        }

        function showGamePlayUI(gameTitle) {
            document.body.classList.add('game-mode-active');
            const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
            const gamePlayArea = document.getElementById('gamePlayArea');
            const gameScoreArea = document.getElementById('gameScoreArea');
            const quitGameButton = document.getElementById('quitGameButton');
            const gameModalTitle = document.getElementById('gameModalTitle');
            if (gameSelectionArea) {
                gameSelectionArea.style.display = 'none';
            }
            if (gamePlayArea) {
                gamePlayArea.style.display = 'block';
            }
            if (gameScoreArea) {
                gameScoreArea.style.display = 'block';
                updateScoreDisplay();
            }
            if (quitGameButton) {
                quitGameButton.style.display = 'block';
            }
            if (gameModalTitle) {
                gameModalTitle.textContent = gameTitle;
            }
        }

        function updateScoreDisplay() {
            document.getElementById('gameCurrentScore').textContent = gameScore;
            document.getElementById('gameHighScore').textContent = gameHighScore;
        }

        function getDynamicLanguageData(entry, langKey) {
            const config = getTranslationConfig(langKey);
            if (!entry || !config) {
                return {
                    meaningText: null,
                    meaningFont: 'var(--font-general)',
                    meaningLangDir: 'ltr'
                };
            }
            const propertyMap = {
                'urdu': 'ur_meaning',
                'english': 'en_meaning',
                'pashto': 'pashto_text',
                'Bangali': 'bn_meaning'
            };
            const meaningText = entry[propertyMap[langKey]] || null;
            if (meaningText && meaningText.trim() === "") {
                return {
                    meaningText: null,
                    meaningFont: config.font,
                    meaningLangDir: config.dir
                };
            }
            return {
                meaningText,
                meaningFont: config.font,
                meaningLangDir: config.dir
            };
        }
        async function startWordWhizGame() {
            activeGame = 'wordWhiz';
            gameScore = 0;
            updateScoreDisplay();
            showGamePlayUI("Word Whiz Challenge");
            document.getElementById('gamePlayArea').innerHTML = '<p>Loading questions...</p>';
            try {
                const allWordMetadataResult = await sendAjaxRequest('get_all_word_metadata');
                const allWordTranslationsResult = await sendAjaxRequest('get_all_word_translations');
                if (!allWordMetadataResult.success || !allWordTranslationsResult.success ||
                    allWordMetadataResult.data.length < 10 || allWordTranslationsResult.data.length < 10) {
                    document.getElementById('gamePlayArea').innerHTML = '<p>Not enough word data to start the game. Please ensure Quran and word data are loaded.</p>';
                    return;
                }
                const allWordMetadata = allWordMetadataResult.data;
                const allWordTranslations = allWordTranslationsResult.data;
                const translationMap = new Map();
                allWordTranslations.forEach(wt => translationMap.set(wt.word_id, wt));
                wordWhizQuestions = [];
                const selectedWordIds = new Set();
                const selectedLangKey = document.getElementById('translation-select').value;
                for (let i = 0; i < 10; i++) {
                    let attempt = 0;
                    let randomMetaEntry, quranAyah, wordText, translationEntry;
                    while (attempt < 50) {
                        randomMetaEntry = allWordMetadata[Math.floor(Math.random() * allWordMetadata.length)];
                        if (selectedWordIds.has(randomMetaEntry.word_id)) {
                            attempt++;
                            continue;
                        }
                        translationEntry = translationMap.get(randomMetaEntry.word_id);
                        if (!translationEntry || (!translationEntry.en_meaning && !translationEntry.ur_meaning && !translationEntry.bn_meaning && !translationEntry.pashto_text)) {
                            attempt++;
                            continue;
                        }
                        const quranAyahResult = await sendAjaxRequest('load_quran_ayah', {
                            surah: randomMetaEntry.surah,
                            ayah: randomMetaEntry.ayah
                        });
                        quranAyah = quranAyahResult.success ? quranAyahResult.data : null;
                        if (!quranAyah || !quranAyah.arabic) {
                            attempt++;
                            continue;
                        }
                        const wordsInAyah = quranAyah.arabic.split(/\s+/);
                        if (randomMetaEntry.word_position < wordsInAyah.length) {
                            wordText = wordsInAyah[randomMetaEntry.word_position];
                            if (wordText && wordText.trim() !== "") break;
                        }
                        attempt++;
                    }
                    if (!wordText) continue;
                    selectedWordIds.add(randomMetaEntry.word_id);
                    const {
                        meaningText: correctAnswerText,
                        meaningFont: fontPreference,
                        meaningLangDir: langDir
                    } = getDynamicLanguageData(translationEntry, selectedLangKey);
                    if (!correctAnswerText || correctAnswerText.trim() === "") continue;
                    const options = [correctAnswerText];
                    let distractorCount = 0;
                    while (distractorCount < 3 && options.length < allWordTranslations.length) {
                        const randomDistractorTrans = allWordTranslations[Math.floor(Math.random() * allWordTranslations.length)];
                        const {
                            meaningText: distractorText
                        } = getDynamicLanguageData(randomDistractorTrans, selectedLangKey);
                        if (distractorText && distractorText.trim() !== "" && !options.includes(distractorText) && distractorText !== correctAnswerText) {
                            options.push(distractorText);
                            distractorCount++;
                        }
                    }
                    shuffleArray(options);
                    wordWhizQuestions.push({
                        word_id: randomMetaEntry.word_id,
                        arabicWord: wordText,
                        options: options,
                        correctAnswer: correctAnswerText,
                        fontPreference: fontPreference,
                        translationLangDir: langDir
                    });
                }
                if (wordWhizQuestions.length === 0) {
                    document.getElementById('gamePlayArea').innerHTML = '<p>Could not generate questions. Try again or check data.</p>';
                    return;
                }
                currentWordWhizQuestionIndex = 0;
                displayWordWhizQuestion();
            } catch (error) {
                console.error("Error starting WordWhiz game:", error);
                document.getElementById('gamePlayArea').innerHTML = `<p>Error loading game: ${error.message}</p>`;
            }
        }

        function displayWordWhizQuestion() {
            if (currentWordWhizQuestionIndex >= wordWhizQuestions.length) {
                endWordWhizGame();
                return;
            }
            const q = wordWhizQuestions[currentWordWhizQuestionIndex];
            let optionsHTML = '<ul class="game-options-list">';
            q.options.forEach(option => {
                optionsHTML += `<li><button data-answer="${option}" style="font-family: ${q.fontPreference}; direction: ${q.translationLangDir}; text-align: ${q.translationLangDir === 'rtl' ? 'right' : 'left'};">${option}</button></li>`;
            });
            optionsHTML += '</ul>';
            const gamePlayArea = document.getElementById('gamePlayArea');
            gamePlayArea.innerHTML = `
                <p>What is the meaning of this word(in Quran)?</p>
                <div class="game-question-arabic">${q.arabicWord}</div>
                ${optionsHTML}
                <div class="game-feedback" id="wordWhizFeedback"></div>
                <button id="nextWordWhizQuestion" style="display:none; margin-top: 10px;">Next Question</button>
            `;
            gamePlayArea.querySelectorAll('.game-options-list button').forEach(button => {
                button?.addEventListener('click', handleWordWhizAnswer);
            });
            document.getElementById('nextWordWhizQuestion')?.addEventListener('click', () => {
                currentWordWhizQuestionIndex++;
                displayWordWhizQuestion();
            });
        }

        function handleWordWhizAnswer(event) {
            const selectedButton = event.target;
            const selectedAnswer = selectedButton.dataset.answer;
            const q = wordWhizQuestions[currentWordWhizQuestionIndex];
            const feedbackEl = document.getElementById('wordWhizFeedback');
            const nextButton = document.getElementById('nextWordWhizQuestion');
            document.querySelectorAll('.game-options-list button').forEach(btn => {
                btn.disabled = true;
                if (btn.dataset.answer === q.correctAnswer) {
                    btn.classList.add('correct');
                }
            });
            if (selectedAnswer === q.correctAnswer) {
                feedbackEl.textContent = "Correct!";
                feedbackEl.style.color = 'var(--color-success)';
                gameScore += 10;
                if (gameScore > gameHighScore) gameHighScore = gameScore;
                updateScoreDisplay();
            } else {
                feedbackEl.textContent = `Incorrect. The correct answer is: ${q.correctAnswer}`;
                feedbackEl.style.color = 'var(--color-error)';
                selectedButton.classList.add('incorrect');
            }
            nextButton.style.display = 'inline-block';
        }

        function endWordWhizGame() {
            document.getElementById('gamePlayArea').innerHTML = `
                <h3>Game Over!</h3>
                <p>Your final score: ${gameScore}</p>
                <button id="playWordWhizAgain">Play Again</button>
            `;
            document.getElementById('playWordWhizAgain')?.addEventListener('click', startWordWhizGame);
            activeGame = null;
        }
        let jumbledWords = [];
        let correctOrderWords = [];
        let draggedItem = null;
        async function startAyahJumbleGame() {
            activeGame = 'ayahJumble';
            gameScore = 0;
            updateScoreDisplay();
            showGamePlayUI("Ayah Jumble Challenge");
            document.getElementById('gamePlayArea').innerHTML = '<p>Loading Ayah...</p>';
            try {
                let randomAyahData, words;
                let attempts = 0;
                const MAX_ATTEMPTS = 20;
                const MIN_WORDS = 4;
                const MAX_WORDS = 10;
                while (attempts < MAX_ATTEMPTS) {
                    const randomSurah = Math.floor(Math.random() * 114) + 1;
                    const randomAyahNum = Math.floor(Math.random() * surahAyahCounts[randomSurah]) + 1;
                    const ayahResult = await sendAjaxRequest('load_quran_ayah', {
                        surah: randomSurah,
                        ayah: randomAyahNum
                    });
                    randomAyahData = ayahResult.success ? ayahResult.data : null;
                    if (randomAyahData && randomAyahData.arabic) {
                        words = randomAyahData.arabic.trim().split(/\s+/).filter(w => w.length > 0);
                        if (words.length >= MIN_WORDS && words.length <= MAX_WORDS) {
                            break;
                        }
                    }
                    attempts++;
                }
                if (!words || words.length < MIN_WORDS) {
                    document.getElementById('gamePlayArea').innerHTML = '<p>Could not find a suitable Ayah for the game. Please try again.</p>';
                    return;
                }
                correctOrderWords = [...words];
                jumbledWords = shuffleArray([...words]);
                ayahJumbleQuestion = {
                    originalAyah: randomAyahData.arabic,
                    words: words,
                    surah: randomAyahData.surah,
                    ayah: randomAyahData.ayah
                };
                displayAyahJumbleQuestion();
            } catch (error) {
                console.error("Error starting AyahJumble game:", error);
                document.getElementById('gamePlayArea').innerHTML = `<p>Error loading game: ${error.message}</p>`;
            }
        }

        function displayAyahJumbleQuestion() {
            const gamePlayArea = document.getElementById('gamePlayArea');
            let jumbledWordsHTML = '';
            jumbledWords.forEach((word, index) => {
                jumbledWordsHTML += `<div class="jumble-word" draggable="true" data-index="${index}" data-word="${word}">${word}</div>`;
            });
            gamePlayArea.innerHTML = `
                <p>Arrange the words to form the correct Ayah (Drag or Click):</p>
                <div id="jumbleSourceContainer" class="jumble-word-container">
                    ${jumbledWordsHTML}
                </div>
                <p style="margin-top: 20px;">Your arrangement:</p>
                <div id="jumbleTargetContainer" class="jumble-target-area">
                </div>
                <button id="jumbleSubmitAnswer">Check Answer</button>
                <button id="jumbleResetArrangement" style="margin-left:10px;">Reset</button>
                <div class="game-feedback" id="ayahJumbleFeedback"></div>
                <button id="nextAyahJumbleQuestion" style="display:none; margin-top: 10px;">Next Ayah</button>
            `;
            addJumbleDragDropListeners();
            const jumbleArea = gamePlayArea;
            jumbleArea.removeEventListener('click', handleJumbleWordClick);
            jumbleArea?.addEventListener('click', handleJumbleWordClick);
            document.getElementById('jumbleSubmitAnswer')?.addEventListener('click', handleAyahJumbleSubmit);
            document.getElementById('jumbleResetArrangement')?.addEventListener('click', resetJumbleArrangement);
            document.getElementById('nextAyahJumbleQuestion')?.addEventListener('click', () => startAyahJumbleGame());
        }

        function addJumbleDragDropListeners() {
            const sourceContainer = document.getElementById('jumbleSourceContainer');
            const targetContainer = document.getElementById('jumbleTargetContainer');
            const attachListenersToWords = (containerSelector) => {
                document.querySelectorAll(`${containerSelector} .jumble-word`).forEach(draggable => {
                    draggable.removeEventListener('dragstart', dragStartHandler);
                    draggable?.addEventListener('dragstart', dragStartHandler);
                    draggable.removeEventListener('dragend', dragEndHandler);
                    draggable?.addEventListener('dragend', dragEndHandler);
                });
            };
            const dragStartHandler = (e) => {
                draggedItem = e.target;
                setTimeout(() => draggedItem.classList.add('dragging'), 0);
            };
            const dragEndHandler = () => {
                if (draggedItem) {
                    draggedItem.classList.remove('dragging');
                }
                draggedItem = null;
            };
            attachListenersToWords('#jumbleSourceContainer');
            attachListenersToWords('#jumbleTargetContainer');
            [sourceContainer, targetContainer].forEach(container => {
                container.removeEventListener('dragover', dragOverHandler);
                container?.addEventListener('dragover', dragOverHandler);
                container.removeEventListener('drop', dropHandler);
                container?.addEventListener('drop', dropHandler);
            });
        }
        const dragOverHandler = (e) => {
            e.preventDefault();
        };
        const dropHandler = (e) => {
            e.preventDefault();
            const targetDropContainer = e.target.closest('.jumble-word-container, .jumble-target-area');
            if (draggedItem && targetDropContainer) {
                const afterElement = getDragAfterElement(targetDropContainer, e.clientY, e.clientX);
                if (afterElement == null) {
                    targetDropContainer.appendChild(draggedItem);
                } else {
                    targetDropContainer.insertBefore(draggedItem, afterElement);
                }
                const feedbackEl = document.getElementById('ayahJumbleFeedback');
                const submitButton = document.getElementById('jumbleSubmitAnswer');
                if (feedbackEl) feedbackEl.textContent = '';
                if (submitButton) submitButton.disabled = false;
            }
        }

        function handleJumbleWordClick(event) {
            const clickedWord = event.target.closest('.jumble-word');
            if (!clickedWord) return;
            const sourceContainer = document.getElementById('jumbleSourceContainer');
            const targetContainer = document.getElementById('jumbleTargetContainer');
            const feedbackEl = document.getElementById('ayahJumbleFeedback');
            const submitButton = document.getElementById('jumbleSubmitAnswer');
            if (clickedWord.parentNode === sourceContainer) {
                targetContainer.appendChild(clickedWord);
            } else if (clickedWord.parentNode === targetContainer) {
                sourceContainer.appendChild(clickedWord);
                const wordsInSource = [...sourceContainer.children];
                wordsInSource.sort((a, b) => parseInt(a.dataset.index) - parseInt(b.dataset.index));
                wordsInSource.forEach(word => sourceContainer.appendChild(word));
            }
            if (feedbackEl) feedbackEl.textContent = '';
            if (submitButton) submitButton.disabled = false;
        }

        function getDragAfterElement(container, y, x) {
            const draggableElements = [...container.querySelectorAll('.jumble-word:not(.dragging)')];
            const containerDir = getComputedStyle(container).direction;
            for (const child of draggableElements) {
                const box = child.getBoundingClientRect();
                const childMidY = box.top + box.height / 2;
                const childMidX = box.left + box.width / 2;
                if (Math.abs(y - childMidY) < box.height) {
                    if (containerDir === 'rtl') {
                        if (x > childMidX) {
                            return child;
                        }
                    } else {
                        if (x < childMidX) {
                            return child;
                        }
                    }
                } else if (y < childMidY) {
                    return child;
                }
            }
            return null;
        }

        function resetJumbleArrangement() {
            const targetContainer = document.getElementById('jumbleTargetContainer');
            const sourceContainer = document.getElementById('jumbleSourceContainer');
            [...targetContainer.children].forEach(child => sourceContainer.appendChild(child));
            const wordsInSource = [...sourceContainer.children];
            wordsInSource.sort((a, b) => parseInt(a.dataset.index) - parseInt(b.dataset.index));
            wordsInSource.forEach(word => sourceContainer.appendChild(word));
            if (document.getElementById('ayahJumbleFeedback')) {
                document.getElementById('ayahJumbleFeedback').textContent = '';
            }
            const submitButton = document.getElementById('jumbleSubmitAnswer');
            if (submitButton) {
                submitButton.disabled = false;
            }
            const nextButton = document.getElementById('nextAyahJumbleQuestion');
            if (nextButton) {
                nextButton.style.display = 'none';
            }
        }

        function handleAyahJumbleSubmit() {
            const targetContainer = document.getElementById('jumbleTargetContainer');
            const userAnswerWords = [...targetContainer.children].map(el => el.textContent.trim());
            const feedbackEl = document.getElementById('ayahJumbleFeedback');
            const nextButton = document.getElementById('nextAyahJumbleQuestion');
            const submitButton = document.getElementById('jumbleSubmitAnswer');
            if (userAnswerWords.join(' ') === correctOrderWords.join(' ')) {
                feedbackEl.textContent = "Correct! Masha'Allah!";
                feedbackEl.style.color = 'var(--color-success)';
                gameScore += 20;
                if (gameScore > gameHighScore) gameHighScore = gameScore;
                updateScoreDisplay();
                nextButton.style.display = 'inline-block';
                submitButton.disabled = true;
                [...targetContainer.children].forEach(child => child.style.backgroundColor = 'var(--color-success)');
            } else {
                feedbackEl.textContent = `Not quite. Try again or reset. The correct Ayah is: ${ayahJumbleQuestion.originalAyah}`;
                feedbackEl.style.color = 'var(--color-error)';
                nextButton.style.display = 'none';
                [...targetContainer.children].forEach(child => child.style.backgroundColor = 'var(--color-error)');
                setTimeout(() => {
                    [...targetContainer.children].forEach(child => child.style.backgroundColor = '');
                }, 2000);
            }
        }

        function shuffleArray(array) {
            for (let i = array.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [array[i], array[j]] = [array[j], array[i]];
            }
            return array;
        }
        let flashcardQuestions = [];
        let currentFlashcardIndex = 0;
        let flashcardShowAnswer = false;
        let memoryMatchCardsArray = [];
        let memoryFirstCardFlipped = null;
        let memorySecondCardFlipped = null;
        let memoryLockBoardActive = false;
        let memoryPairsFoundCount = 0;
        let memoryAttemptCount = 0;
        let memoryWordPairsForGame = [];

        function injectFlashcardGameCSS_Suite() {
            const cssId = "flashcardGameStylesSuite";
            if (document.getElementById(cssId)) return;
            const styles = `
                .flashcard-game-area { display: flex; flex-direction: column; align-items: center; width: 100%; padding: 10px; }
                .flashcard-container { perspective: 1000px; width: 90%; max-width: 320px; min-height: 180px; height: auto; aspect-ratio: 3 / 2; margin: 15px auto; cursor: pointer; }
                .flashcard { width: 100%; height: 100%; position: relative; transform-style: preserve-3d; transition: transform 0.6s; border: 1px solid var(--color-border); border-radius: var(--border-radius); }
                .flashcard.is-flipped { transform: rotateY(180deg); }
                .flashcard-face { position: absolute; width: 100%; height: 100%; backface-visibility: hidden; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 15px; text-align: center; border-radius: var(--border-radius); background-color: var(--color-bg-secondary); color: var(--color-text-primary); overflow-wrap: break-word; word-break: break-word; }
                .flashcard-front { font-family: var(--font-arabic); font-size: clamp(1.8rem, 6vw, 2.8rem); direction: rtl; }
                .flashcard-back { transform: rotateY(180deg); font-size: clamp(1rem, 4vw, 1.4rem); }
                .flashcard-controls { margin-top: 15px; text-align: center; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
                .flashcard-controls button { min-width: 100px; }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }

        function injectMemoryMatchGameCSS_Suite() {
            const cssId = "memoryMatchGameStylesSuite";
            if (document.getElementById(cssId)) return;
            const styles = `
                .memory-match-info { text-align: center; margin-bottom: 10px; font-size: 1.1rem; }
                .memory-match-attempts { font-weight: bold; color: var(--color-text-secondary); }
                .memory-match-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 10px; padding: 10px; max-width: 500px; margin: 15px auto; }
                .memory-card { aspect-ratio: 1 / 1; background-color: var(--color-accent); border: 1px solid var(--color-accent-dark); border-radius: var(--border-radius); display: flex; justify-content: center; align-items: center; cursor: pointer; font-size: 1rem; color: white; user-select: none; transform-style: preserve-3d; transition: transform 0.3s, background-color 0.3s; }
                .memory-card .card-content { display: none; text-align: center; overflow-wrap: break-word; word-break: break-word; padding: 5px; }
                .memory-card.arabic-text .card-content { font-family: var(--font-arabic); direction: rtl; font-size: clamp(1.1rem, 3.5vw, 1.6rem); }
                .memory-card.meaning-text .card-content { font-size: clamp(0.7rem, 2.5vw, 1rem); }
                .memory-card.is-flipped .card-content { display: block; }
                .memory-card.is-flipped { background-color: var(--color-bg-secondary); color: var(--color-text-primary); }
                .memory-card.is-matched { background-color: var(--color-success) !important; color: white !important; cursor: default; opacity: 0.8; }
                .memory-card.is-matched .card-content { display: block; }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }
        async function startFlashcardGame_Suite() {
            activeGame = 'flashcards_suite';
            showGamePlayUI("Flashcard Frenzy");
            injectFlashcardGameCSS_Suite();
            const gamePlayArea = document.getElementById('gamePlayArea');
            if (!gamePlayArea) {
                console.error("Flashcard: gamePlayArea not found!");
                return;
            }
            gamePlayArea.innerHTML = '<p style="text-align:center; padding:20px;">Loading flashcards...</p>';
            try {
                const allWordMetadataResult = await sendAjaxRequest('get_all_word_metadata');
                const allWordTranslationsResult = await sendAjaxRequest('get_all_word_translations');
                if (!allWordMetadataResult.success || !allWordTranslationsResult.success ||
                    allWordMetadataResult.data.length < 3 || allWordTranslationsResult.data.length < 3) {
                    gamePlayArea.innerHTML = '<p style="text-align:center; padding:20px; color:var(--color-error);">Not enough word data for flashcards.</p>';
                    return;
                }
                const allWordMetadata = allWordMetadataResult.data;
                const allWordTranslations = allWordTranslationsResult.data;
                const translationMap = new Map(allWordTranslations.map(wt => [wt.word_id, wt]));
                flashcardQuestions = [];
                const selectedWordIds = new Set();
                const maxCards = 10;
                const selectedLangKey = document.getElementById('translation-select').value;
                for (let i = 0; i < maxCards * 2 && flashcardQuestions.length < maxCards; i++) {
                    let attempt = 0;
                    let randomMetaEntry, quranAyah, wordText, translationEntry;
                    while (attempt < 20) {
                        randomMetaEntry = allWordMetadata[Math.floor(Math.random() * allWordMetadata.length)];
                        if (selectedWordIds.has(randomMetaEntry.word_id)) {
                            attempt++;
                            continue;
                        }
                        translationEntry = translationMap.get(randomMetaEntry.word_id);
                        if (!translationEntry || (!translationEntry.en_meaning && !translationEntry.ur_meaning && !translationEntry.bn_meaning && !translationEntry.pashto_text)) {
                            attempt++;
                            continue;
                        }
                        const quranAyahResult = await sendAjaxRequest('load_quran_ayah', {
                            surah: randomMetaEntry.surah,
                            ayah: randomMetaEntry.ayah
                        });
                        quranAyah = quranAyahResult.success ? quranAyahResult.data : null;
                        if (!quranAyah || !quranAyah.arabic) {
                            attempt++;
                            continue;
                        }
                        const wordsInAyah = quranAyah.arabic.split(/\s+/);
                        if (randomMetaEntry.word_position < wordsInAyah.length) {
                            wordText = wordsInAyah[randomMetaEntry.word_position];
                            if (wordText && wordText.trim() !== "") break;
                        }
                        attempt++;
                    }
                    if (!wordText) continue;
                    selectedWordIds.add(randomMetaEntry.word_id);
                    const {
                        meaningText,
                        meaningLangDir,
                        meaningFont
                    } = getDynamicLanguageData(translationEntry, selectedLangKey);
                    if (!meaningText || meaningText.trim() === "" || meaningText.trim().toLowerCase() === "n/a") {
                        selectedWordIds.delete(randomMetaEntry.word_id);
                        continue;
                    }
                    flashcardQuestions.push({
                        arabicWord: wordText,
                        meaning: meaningText,
                        meaningLangDir,
                        meaningFont
                    });
                }
                if (flashcardQuestions.length === 0) {
                    gamePlayArea.innerHTML = '<p style="text-align:center; padding:20px; color:var(--color-error);">Could not generate any flashcards.</p>';
                    return;
                }
                currentFlashcardIndex = 0;
                displayFlashcard_Suite();
            } catch (error) {
                console.error("Error starting Flashcard game (Suite):", error);
                gamePlayArea.innerHTML = `<p style="text-align:center; padding:20px; color:var(--color-error);">Error loading game: ${error.message}.</p>`;
            }
        }

        function displayFlashcard_Suite() {
            if (currentFlashcardIndex >= flashcardQuestions.length) {
                endFlashcardGame_Suite();
                return;
            }
            flashcardShowAnswer = false;
            const cardData = flashcardQuestions[currentFlashcardIndex];
            const gamePlayArea = document.getElementById('gamePlayArea');
            gamePlayArea.innerHTML = '';
            const gameAreaWrapper = document.createElement('div');
            gameAreaWrapper.className = 'flashcard-game-area';
            const container = document.createElement('div');
            container.className = 'flashcard-container';
            const card = document.createElement('div');
            card.className = 'flashcard';
            const frontFace = document.createElement('div');
            frontFace.className = 'flashcard-face flashcard-front';
            frontFace.textContent = cardData.arabicWord;
            const backFace = document.createElement('div');
            backFace.className = 'flashcard-face flashcard-back';
            backFace.textContent = cardData.meaning;
            backFace.style.fontFamily = cardData.meaningFont;
            backFace.style.direction = cardData.meaningLangDir;
            backFace.style.textAlign = cardData.meaningLangDir === 'rtl' ? 'right' : 'left';
            card.append(frontFace, backFace);
            container.appendChild(card);
            gameAreaWrapper.appendChild(container);
            const controlsDiv = document.createElement('div');
            controlsDiv.className = 'flashcard-controls';
            const flipButton = document.createElement('button');
            flipButton.textContent = 'Flip Card';
            const gotItButton = document.createElement('button');
            gotItButton.textContent = 'Got it!';
            gotItButton.style.display = 'none';
            const notYetButton = document.createElement('button');
            notYetButton.textContent = 'Not Yet';
            notYetButton.style.display = 'none';
            controlsDiv.append(flipButton, gotItButton, notYetButton);
            gameAreaWrapper.appendChild(controlsDiv);
            gamePlayArea.appendChild(gameAreaWrapper);
            container?.addEventListener('click', () => toggleFlashcardFlip_Suite(card, gotItButton, notYetButton, flipButton));
            flipButton?.addEventListener('click', () => toggleFlashcardFlip_Suite(card, gotItButton, notYetButton, flipButton));
            gotItButton?.addEventListener('click', () => handleFlashcardResponse_Suite(true));
            notYetButton?.addEventListener('click', () => handleFlashcardResponse_Suite(false));
        }

        function toggleFlashcardFlip_Suite(cardEl, gotItBtn, notYetBtn, flipBtn) {
            cardEl.classList.toggle('is-flipped');
            flashcardShowAnswer = cardEl.classList.contains('is-flipped');
            gotItBtn.style.display = flashcardShowAnswer ? 'inline-block' : 'none';
            notYetBtn.style.display = flashcardShowAnswer ? 'inline-block' : 'none';
            flipBtn.textContent = flashcardShowAnswer ? 'Show Question' : 'Flip Card';
        }

        function handleFlashcardResponse_Suite(knewIt) {
            if (knewIt) {} else {
                const currentCardData = flashcardQuestions[currentFlashcardIndex];
                flashcardQuestions.push(currentCardData);
            }
            currentFlashcardIndex++;
            displayFlashcard_Suite();
        }

        function endFlashcardGame_Suite() {
            const gamePlayArea = document.getElementById('gamePlayArea');
            if (!gamePlayArea) return;
            const originalFlashcardSet = flashcardQuestions.slice(0, currentFlashcardIndex);
            const uniqueWordsForMemory = [];
            const seenArabicWords = new Set();
            for (const card of originalFlashcardSet) {
                if (!seenArabicWords.has(card.arabicWord) && uniqueWordsForMemory.length < 6) {
                    uniqueWordsForMemory.push(card);
                    seenArabicWords.add(card.arabicWord);
                }
                if (uniqueWordsForMemory.length >= 6) break;
            }
            const canPlayMemory = uniqueWordsForMemory.length >= 2;
            let memoryBtnHTML = canPlayMemory ? `<button id="startMemoryMatchGameBtn_Suite">Test Your Memory!</button>` : `<p style="font-size:0.9em; margin-top:10px;">(Not enough unique cards seen for a memory game.)</p>`;
            gamePlayArea.innerHTML = `
                <div style="text-align:center; padding: 20px;">
                    <h3>Flashcard Session Over!</h3>
                    <p style="font-size:0.9em;">(Cards marked "Not Yet" are re-added to the end for more practice in the next flashcard round.)</p>
                    <div style="margin-top: 20px; display:flex; flex-direction:column; align-items:center; gap:10px;">
                        <button id="playFlashcardsAgain_Suite">Practice Flashcards Again</button>
                        ${memoryBtnHTML}
                    </div>
                </div>
            `;
            const playAgainFCBtn = document.getElementById('playFlashcardsAgain_Suite');
            if (playAgainFCBtn) playAgainFCBtn?.addEventListener('click', startFlashcardGame_Suite);
            if (canPlayMemory) {
                const startMemoryBtn = document.getElementById('startMemoryMatchGameBtn_Suite');
                if (startMemoryBtn) startMemoryBtn?.addEventListener('click', () => startMemoryMatchGame_Suite(uniqueWordsForMemory));
            }
            activeGame = null;
        }
        async function startMemoryMatchGame_Suite(wordDataForGame) {
            activeGame = 'memoryMatch_suite';
            showGamePlayUI("Memory Match Challenge");
            injectMemoryMatchGameCSS_Suite();
            const gamePlayArea = document.getElementById('gamePlayArea');
            if (!gamePlayArea) {
                console.error("Memory Match: gamePlayArea not found!");
                return;
            }
            gamePlayArea.innerHTML = '<p style="text-align:center; padding:20px;">Setting up the memory board...</p>';
            memoryWordPairsForGame = [...wordDataForGame];
            memoryMatchCardsArray = [];
            memoryPairsFoundCount = 0;
            memoryAttemptCount = 0;
            memoryLockBoardActive = false;
            memoryFirstCardFlipped = null;
            memorySecondCardFlipped = null;
            memoryWordPairsForGame.forEach((pair, index) => {
                memoryMatchCardsArray.push({
                    id: `arabic-${index}`,
                    type: 'arabic',
                    content: pair.arabicWord,
                    pairId: index,
                    isFlipped: false,
                    isMatched: false
                });
                memoryMatchCardsArray.push({
                    id: `meaning-${index}`,
                    type: 'meaning',
                    content: pair.meaning,
                    pairId: index,
                    font: pair.meaningFont,
                    dir: pair.meaningLangDir,
                    isFlipped: false,
                    isMatched: false
                });
            });
            shuffleArray(memoryMatchCardsArray);
            renderMemoryBoard_Suite();
            updateMemoryGameInfo_Suite();
        }

        function renderMemoryBoard_Suite() {
            const gamePlayArea = document.getElementById('gamePlayArea');
            gamePlayArea.innerHTML = '';
            const infoDiv = document.createElement('div');
            infoDiv.className = 'memory-match-info';
            infoDiv.innerHTML = `Pairs Found: <span id="memoryPairsFoundEl_Suite">0</span>/${memoryWordPairsForGame.length} | Attempts: <span id="memoryAttemptsEl_Suite" class="memory-match-attempts">0</span>`;
            gamePlayArea.appendChild(infoDiv);
            const grid = document.createElement('div');
            grid.className = 'memory-match-grid';
            const numCards = memoryMatchCardsArray.length;
            let columns = (numCards <= 6) ? 3 : (numCards <= 8) ? 4 : (numCards <= 12) ? 4 : 4;
            grid.style.gridTemplateColumns = `repeat(${columns}, 1fr)`;
            memoryMatchCardsArray.forEach(cardData => {
                const cardElement = document.createElement('div');
                cardElement.className = 'memory-card';
                cardElement.dataset.id = cardData.id;
                const contentSpan = document.createElement('span');
                contentSpan.className = 'card-content';
                contentSpan.textContent = cardData.content;
                if (cardData.type === 'arabic') cardElement.classList.add('arabic-text');
                else {
                    cardElement.classList.add('meaning-text');
                    contentSpan.style.fontFamily = cardData.font;
                    contentSpan.style.direction = cardData.dir;
                    contentSpan.style.textAlign = cardData.dir === 'rtl' ? 'right' : 'left';
                }
                cardElement.appendChild(contentSpan);
                if (cardData.isFlipped) cardElement.classList.add('is-flipped');
                if (cardData.isMatched) cardElement.classList.add('is-matched', 'is-flipped');
                cardElement?.addEventListener('click', () => handleMemoryCardClick_Suite(cardElement, cardData));
                grid.appendChild(cardElement);
            });
            gamePlayArea.appendChild(grid);
        }

        function handleMemoryCardClick_Suite(cardEl, cardData) {
            if (memoryLockBoardActive || cardData.isFlipped || cardData.isMatched) return;
            cardData.isFlipped = true;
            cardEl.classList.add('is-flipped');
            if (!memoryFirstCardFlipped) {
                memoryFirstCardFlipped = cardData;
                return;
            }
            memorySecondCardFlipped = cardData;
            memoryLockBoardActive = true;
            memoryAttemptCount++;
            updateMemoryGameInfo_Suite();
            checkForMemoryMatch_Suite();
        }

        function checkForMemoryMatch_Suite() {
            const isMatch = memoryFirstCardFlipped.pairId === memorySecondCardFlipped.pairId;
            if (isMatch) {
                memoryFirstCardFlipped.isMatched = true;
                memorySecondCardFlipped.isMatched = true;
                memoryPairsFoundCount++;
                updateMemoryGameInfo_Suite();
                document.querySelector(`.memory-card[data-id="${memoryFirstCardFlipped.id}"]`)?.classList.add('is-matched');
                document.querySelector(`.memory-card[data-id="${memorySecondCardFlipped.id}"]`)?.classList.add('is-matched');
                resetMemoryTurn_Suite();
                if (memoryPairsFoundCount === memoryWordPairsForGame.length) {
                    setTimeout(endMemoryMatchGame_Suite, 600);
                }
            } else {
                setTimeout(() => {
                    if (memoryFirstCardFlipped) memoryFirstCardFlipped.isFlipped = false;
                    if (memorySecondCardFlipped) memorySecondCardFlipped.isFlipped = false;
                    document.querySelector(`.memory-card[data-id="${memoryFirstCardFlipped?.id}"]`)?.classList.remove('is-flipped');
                    document.querySelector(`.memory-card[data-id="${memorySecondCardFlipped?.id}"]`)?.classList.remove('is-flipped');
                    resetMemoryTurn_Suite();
                }, 1200);
            }
        }

        function resetMemoryTurn_Suite() {
            memoryFirstCardFlipped = null;
            memorySecondCardFlipped = null;
            memoryLockBoardActive = false;
        }

        function updateMemoryGameInfo_Suite() {
            const pairsEl = document.getElementById('memoryPairsFoundEl_Suite');
            const attemptsEl = document.getElementById('memoryAttemptsEl_Suite');
            if (pairsEl) pairsEl.textContent = memoryPairsFoundCount;
            if (attemptsEl) attemptsEl.textContent = memoryAttemptCount;
        }

        function endMemoryMatchGame_Suite() {
            const gamePlayArea = document.getElementById('gamePlayArea');
            if (!gamePlayArea) return;
            gamePlayArea.innerHTML = `
                <div style="text-align:center; padding: 20px;">
                    <h3>Memory Game Cleared!</h3>
                    <p>You found all ${memoryPairsFoundCount} pairs in ${memoryAttemptCount} attempts!</p>
                    <div style="margin-top:15px; display:flex; justify-content:center; gap:10px;">
                        <button id="playMemoryMatchAgainBtn_Suite">Play Memory Again</button>
                        <button id="backToGameSelectionBtn_Suite">Game Selection</button>
                    </div>
                </div>
            `;
            const playAgainMMBtn = document.getElementById('playMemoryMatchAgainBtn_Suite');
            if (playAgainMMBtn) playAgainMMBtn?.addEventListener('click', () => startMemoryMatchGame_Suite(memoryWordPairsForGame));
            const backBtn = document.getElementById('backToGameSelectionBtn_Suite');
            if (backBtn) backBtn?.addEventListener('click', () => {
                activeGame = null;
                resetGameUI();
            });
            activeGame = null;
        }

        function addFlashcardSuiteButtonToModal() {
            const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
            const buttonId = 'startGameFlashcardSuiteBtn';
            if (gameSelectionArea && !document.getElementById(buttonId)) {
                const suiteButton = document.createElement('button');
                suiteButton.id = buttonId;
                suiteButton.className = 'game-select-btn';
                suiteButton.textContent = 'Flashcard & Memory';
                suiteButton?.addEventListener('click', startFlashcardGame_Suite);
                const existingButtons = gameSelectionArea.querySelectorAll('.game-select-btn');
                if (existingButtons.length > 0) {
                    existingButtons[existingButtons.length - 1].insertAdjacentElement('afterend', suiteButton);
                } else {
                    gameSelectionArea.appendChild(suiteButton);
                }
            }
        }

        function updateVisualReadingProgress(currentS, currentA) {
            if (!visualReadingSession.active) return;
            visualReadingSession.endSurah = parseInt(currentS);
            visualReadingSession.endAyah = parseInt(currentA);
        }
        async function logVisualReadingSession() {
            if (!visualReadingSession.active || !visualReadingSession.startSurah || !visualReadingSession.endSurah || !isUserLoggedIn) {
                visualReadingSession = {
                    active: false,
                    startSurah: null,
                    startAyah: null,
                    endSurah: null,
                    endAyah: null
                };
                return;
            }
            const {
                startSurah,
                startAyah,
                endSurah,
                endAyah
            } = visualReadingSession;
            if (startSurah === endSurah && startAyah === endAyah) {
                visualReadingSession = {
                    active: false,
                    startSurah: null,
                    startAyah: null,
                    endSurah: null,
                    endAyah: null
                };
                return;
            }
            const logPromises = [];
            const qariName = "Visual/Self Reading";
            for (let s = startSurah; s <= endSurah; s++) {
                const currentStartAyah = (s === startSurah) ? startAyah : 1;
                const currentEndAyah = (s === endSurah) ? endAyah : surahAyahCounts[s];
                const logEntry = {
                    surah: s,
                    ayah_start: currentStartAyah,
                    ayah_end: currentEndAyah,
                    qari: qariName,
                    log_date: new Date().toISOString().split('T')[0],
                    notes: `Auto-logged reading session for Surah ${s}.`
                };
                logPromises.push(sendAjaxRequest('save_recitation_log', logEntry));
            }
            try {
                await Promise.all(logPromises);
                console.log(`Successfully logged reading for ${logPromises.length} separate Surah(s).`);
            } catch (error) {
                console.error("Failed to auto-log one or more visual reading sessions:", error);
            }
            visualReadingSession = {
                active: false,
                startSurah: null,
                startAyah: null,
                endSurah: null,
                endAyah: null
            };
        }
        let isFullScreenReaderActive = false;
        let isDailyReadingSessionActive = false;
        let visualReadingSession = {
            active: false,
            startSurah: null,
            startAyah: null,
            endSurah: null,
            endAyah: null
        };
        let fullScreenReaderCurrentPage = 1;
        let fullScreenReaderCurrentSurah = 1;
        let fullScreenReaderCurrentAyah = 1;
        let fullScreenReaderViewMode = 'paged';
        let fullScreenReaderAudioPlayer = null;
        let fullScreenReaderAudioQueue = [];
        let fullScreenReaderIsPlayingAudio = false;
        let fullScreenReaderContinuousAudioMode = false;

        function getQuranAudioUrl(languageCode) {
            let edition, bitrate;
            if (languageCode === 'en') {
                edition = 'en.walk';
                bitrate = 192;
            } else if (languageCode === 'ur') {
                edition = 'ur.khan';
                bitrate = 64;
            } else {
                throw new Error("Invalid languageCode: must be 'en' or 'ur'");
            }
            return function(ayahNumber) {
                if (
                    typeof ayahNumber !== 'number' ||
                    !Number.isInteger(ayahNumber) ||
                    ayahNumber <= 0
                ) {
                    throw new Error('Ayah number must be a positive integer');
                }
                return `https://cdn.islamic.network/quran/audio/${bitrate}/${edition}/${ayahNumber}.mp3`;
            };
        }
        let fullScreenReaderSettings = {
            arabicFont: 'Scheherazade New',
            fontSize: '2.5rem',
            linesPerPage: 15,
            showTransliteration: false,
            autoScrollAudio: true,
            highlightColor: 'rgba(255, 255, 150, 0.4)',
            continuousAudio: false,
            audioSource: 'quran',
            showTajweedColors: false,
        };
        const TAJ_COMPANY_PAGES = 604;
        let tajMushafPageData = [];
        let continuousScrollNextSurahToLoad = 1;
        let continuousScrollNextAyahToLoad = 1;
        const CONTINUOUS_SCROLL_LOAD_COUNT = 60;
        let isLoadingMoreAyahs = false;
        let continuousScrollSurahContainer = null;

        function injectEnhancedFullScreenReaderCSS() {
            const cssId = "enhancedFullScreenReaderStyles";
            if (document.getElementById(cssId)) return;
            const styles = `
                #fullScreenReaderOverlay {
                    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
                    background-color: var(--color-bg-primary, #f0f0f0); 
                    z-index: 10000; display: flex; flex-direction: column; overflow: hidden;
                    font-family: var(--font-arabic);
                }
                #fsReaderHeader, #fsReaderFooter {
                    padding: 8px 12px; background-color: var(--color-bg-secondary, #e0e0e0);
                    border-bottom: 1px solid var(--color-border, #ccc); flex-shrink: 0;
                    display: flex; justify-content: space-between; align-items: center;
                }
                #fsReaderFooter { border-top: 1px solid var(--color-border, #ccc); border-bottom: none; }
                #fsReaderHeader .title-page-container { text-align: center; flex-grow: 1; }
                #fsReaderHeader .title { font-size: 1.1rem; font-weight: bold; color: var(--color-text-primary); }
                #fsReaderHeader .page-info { font-size: 0.9rem; color: var(--color-text-secondary); }
                #fsReaderContent {
                    flex-grow: 1; overflow-y: auto; overflow-x: hidden; padding: 5px;
                    display: flex; flex-direction: column; align-items: center; 
                }
                .fsReaderPage {
                    background-color: #fff; border: 1px solid #ddd; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    padding: 15px 20px; margin-bottom: 15px; width: 96%;  direction: rtl;
                }
                #continuousScrollSurahContainer { width: 96%;  margin: 0 auto; }
                .continuousSurahBlock {
                    padding: 10px 15px; margin-bottom: 20px; direction: rtl;
                    border-bottom: 2px solid var(--color-accent, #4caf50); 
                }
                .continuousSurahBlock h2.surahNameHeader { 
                    text-align: center; font-size: 1.8em; 
                    color: var(--color-accent-dark, #388e3c); margin-bottom: 0.8em;
                    padding: 5px; border-bottom: 1px solid var(--color-border);
                }
                .bismillahText {
                    text-align: center; font-size: 1.5em;  margin: 1em 0;
                    font-family: 'KFGQPC Uthman Taha Naskh', var(--font-arabic); 
                }
                .fsReaderAyah {
                    display: inline; margin-right: 0.1em; line-height: 2.3; 
                    transition: background-color 0.2s; cursor: pointer; 
                }
                .fsReaderAyahNumber {
                    font-size: 0.65em; color: var(--color-accent-dark, #388e3c);
                    padding: 0px 0.25em; margin: 0 0.15em;
                    border: 1px solid var(--color-accent, #4caf50); border-radius: 50%;
                    display: inline-block; line-height: 1.2; vertical-align: middle;
                }
                .fsReaderTransliteration {
                    display: block; font-size: 0.7em; color: #666;
                    font-family: var(--font-english); direction: ltr; text-align: right;
                    margin-top: -1em; margin-bottom: 0.6em; padding-right: 2em; 
                }
                .highlighted-ayah { border-radius: 3px; } 
                #fsReaderFooter button, #fsReaderHeader button { font-size: 1.4rem; padding: 6px 8px; }
                #fsReaderScrubSlider { flex-grow:1; margin:0 10px; accent-color: var(--color-accent); }
                .fsReaderSlidingPanel {
                    position: absolute; top: 33px; 
                    width: 258px; max-width: 90%; background-color: var(--color-bg-primary);
                    border: 1px solid var(--color-border); box-shadow: 0 3px 10px rgba(0,0,0,0.15);
                    padding: 15px; z-index: 10001; display: none;
                    color: var(--color-text-primary); overflow-y:auto;
                }
                #fsReaderSettingsPanel { right: 0; border-radius: 0 0 0 var(--border-radius); }
                #fsReaderIndexPanel { left: 0; border-radius: 0 0 var(--border-radius) 0; }
                .fsReaderSlidingPanel h4 { margin-top: 0; margin-bottom:12px; color: var(--color-text-secondary); border-bottom: 1px solid var(--color-border); padding-bottom: 8px;}
                .fsReaderSlidingPanel label { display: block; margin-top: 8px; margin-bottom: 2px; font-size:0.95em; font-weight:normal; color: var(--color-text-secondary); }
                .fsReaderSlidingPanel select, .fsReaderSlidingPanel input[type=range], .fsReaderSlidingPanel input[type=checkbox], .fsReaderSlidingPanel input[type=color] { 
                    width: 100%; margin-bottom:12px; 
                }
                .fsReaderSlidingPanel input[type=range] { padding: 0; }
                .fsReaderSlidingPanel .panel-section { margin-bottom:15px; }
                .fsReaderSlidingPanel .panel-section h5 { margin-bottom:5px; font-size:1em; color: var(--color-text-primary); }
                #fsReaderIndexPanel .index-tabs { display:flex; margin-bottom:10px; border-bottom: 1px solid var(--color-border); }
                #fsReaderIndexPanel .index-tab { padding: 8px 12px; cursor:pointer; border-radius: 4px 4px 0 0; }
                #fsReaderIndexPanel .index-tab.active-tab { background-color: var(--color-bg-secondary); border: 1px solid var(--color-border); border-bottom:1px solid var(--color-bg-secondary); }
                .index-list { list-style: none; padding-left: 0; max-height: calc(100vh - 200px); overflow-y: auto;}
                .index-list li a { display: block; padding: 7px 5px; color: var(--color-text-primary); font-size:0.95em; border-bottom: 1px dotted var(--color-border); }
                .index-list li a:hover { background-color: var(--color-bg-secondary); text-decoration:none; }
                .index-list-item-num { color: var(--color-accent-dark); font-weight:bold; margin-right:8px; display:inline-block; width:25px; text-align:right;}
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }
        async function launchFullScreenQuranReaderEnhanced() {
            if (isFullScreenReaderActive) return;
            isDailyReadingSessionActive = false;
            isFullScreenReaderActive = true;
            document.body.style.overflow = 'hidden';
            injectEnhancedFullScreenReaderCSS();
            const overlay = document.createElement('div');
            overlay.id = 'fullScreenReaderOverlay';
            overlay.innerHTML = `
                <div id="fsReaderHeader">
                    <button id="fsReaderIndexToggleBtn" title="Index">☰</button>
                    <div class="title-page-container">
                        <div id="fsReaderSurahTitle" class="title">Loading...</div>
                        <div id="fsReaderPageInfo" class="page-info">Page ...</div>
                    </div>
                    <button id="fsReaderBookmarkBtn" title="Go to your daily reading spot (Ctrl+Click to set current spot)">📑</button>
                    <button id="fsReaderSettingsToggleBtn" title="Settings">⚙️</button>
                </div>
                <div id="fsReaderContent" style="font-size: ${fullScreenReaderSettings.fontSize};">
                    <p>Loading Quran content...</p>
                </div>
                <div id="fsReaderFooter">
                    <button id="fsReaderPrevBtn" title="Previous">◀</button>
                    <input type="range" id="fsReaderScrubSlider" min="1" max="${TAJ_COMPANY_PAGES}" value="1" title="Navigate">
                    <button id="fsReaderNextBtn" title="Next">▶</button>
                    <button id="fsReaderPlayPauseBtn" title="Play/Pause">🔊</button>
                    <button id="fsReaderCloseBtnEnhanced" title="Close Reader">✕</button>
                </div>
                <div id="fsReaderSettingsPanel" class="fsReaderSlidingPanel">
                    <h4>Reader Settings</h4>
                    <div class="panel-section">
                        <h5>Appearance</h5>
                        <label for="fsArabicFontSelect">Arabic Font:</label>
                        <select id="fsArabicFontSelect">
                            <option value="Scheherazade New" data-google-font="Scheherazade+New:wght@400;700">Scheherazade New</option>
                            <option value="KFGQPC Uthman Taha Naskh">KFGQPC Uthman Naskh</option>
                            <option value="Amiri" data-google-font="Amiri:wght@400;700">Amiri</option>
                            <option value="Lateef" data-google-font="Lateef">Lateef</option>
                            <option value="Noto Naskh Arabic" data-google-font="Noto+Naskh+Arabic:wght@400;700">Noto Naskh Arabic</option>
                            <option value="var(--font-arabic)">App Default Arabic</option>
                        </select>
                        <label for="fsFontSizeSlider">Base Font Size (<span id="fsFontSizeValue">${fullScreenReaderSettings.fontSize}</span>):</label>
                        <input type="range" id="fsFontSizeSlider" min="1.3" max="11.0" step="0.1" value="${parseFloat(fullScreenReaderSettings.fontSize)}">
                        <div id="fsLinesPerPageSettingDiv">
                            <label for="fsLinesPerPageSlider">Lines Per Page (Paged View) (<span id="fsLinesPerPageValue">${fullScreenReaderSettings.linesPerPage}</span>):</label>
                            <input type="range" id="fsLinesPerPageSlider" min="8" max="35" step="1" value="${fullScreenReaderSettings.linesPerPage}">
                        </div>
                        <label><input type="checkbox" id="fsShowTransliterationCheck"> Show Transliteration</label>
                        <label><input type="checkbox" id="fsShowTajweedCheck"> Show Tajweed Colors</label>
                    </div>
                    <div class="panel-section">
                        <h5>Audio</h5>
                        <label for="fsAudioSourceSelect">Audio Source:</label>
                        <select id="fsAudioSourceSelect">
                            <option value="quran">Quran Recitation (Alafasy)</option>
                            <option value="en">English Translation (Ibrahim Walk)</option>
                            <option value="ur">Urdu Translation (Fateh Jalandhri)</option>
                        </select>
                        <label><input type="checkbox" id="fsContinuousAudioCheck"> Continuous Audio Playback</label>
                        <label><input type="checkbox" id="fsAutoScrollAudioCheck"> Auto-scroll with Audio</label>
                        <label for="fsHighlightColorPicker">Highlight Color:</label>
                        <input type="color" id="fsHighlightColorPicker" value="${fullScreenReaderSettings.highlightColor.startsWith('rgba') ? '#ffff96' : fullScreenReaderSettings.highlightColor}">
                    </div>
                    <div class="panel-section">
                        <h5>Navigation</h5>
                        <label for="fsViewModeSelect">View Mode:</label>
                        <select id="fsViewModeSelect">
                            <option value="paged">Paged (Mushaf Style)</option>
                            <option value="continuous-scroll">Continuous Scroll</option>
                        </select>
                    </div>
                </div>
                <div id="fsReaderIndexPanel" class="fsReaderSlidingPanel">
                    <h4>Index</h4>
                    <div class="index-tabs">
                        <span class="index-tab active-tab" data-tab="surahs">Surahs</span>
                        <span class="index-tab" data-tab="juz">Juz (Parahs)</span>
                        <span class="index-tab" data-tab="themes">Themes</span>
                    </div>
                    <div id="fsIndexContentSurahs" class="index-content-panel">
                        <ul class="index-list" id="fsIndexSurahList"></ul>
                    </div>
                    <div id="fsIndexContentJuz" class="index-content-panel" style="display:none;">
                        <ul class="index-list" id="fsIndexJuzList"></ul>
                    </div>
                    <div id="fsIndexContentThemes" class="index-content-panel" style="display:none;">
                        <input type="text" id="fsThematicIndexSearchInput" placeholder="Search themes..." style="width:100%; margin-bottom:10px;">
                        <ul class="index-list" id="fsIndexThemeList">
                            <li><a href="#" data-s="2" data-a="255">Ayat al-Kursi</a></li>
                        </ul>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
            loadLastReadPosition();
            loadFullScreenReaderSettings();
            await loadTajMushafPageDataIfNeeded();
            visualReadingSession = {
                active: true,
                startSurah: fullScreenReaderCurrentSurah,
                startAyah: fullScreenReaderCurrentAyah,
                endSurah: fullScreenReaderCurrentSurah,
                endAyah: fullScreenReaderCurrentAyah
            };
            setupEnhancedFullScreenReaderEventListeners();
            populateIndexLists();
            if (fullScreenReaderViewMode === 'paged') {
                document.getElementById('fsLinesPerPageSettingDiv').style.display = 'block';
                await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
            } else {
                document.getElementById('fsLinesPerPageSettingDiv').style.display = 'none';
                await initializeContinuousScroll();
            }
            updateReaderHeaderInfo();
            updateScrubSliderRangeAndValue();
        }

        function closeFullScreenQuranReaderEnhanced() {
            const overlay = document.getElementById('fullScreenReaderOverlay');
            if (overlay) {
                document.body.removeChild(overlay);
            }
            document.body.style.overflow = 'auto';
            isFullScreenReaderActive = false;
            if (isDailyReadingSessionActive) {
                saveLastReadPosition();
                logVisualReadingSession();
            }
            if (isUserLoggedIn) {
                logAutoRecitationSession();
            }
            saveLastReadPosition();
            saveFullScreenReaderSettings();
        }
        async function loadTajMushafPageDataIfNeeded() {
            tajMushafPageData = [];
            let currentS = 1,
                currentA = 1;
            let currentJuz = 1;
            for (let page = 1; page <= TAJ_COMPANY_PAGES; page++) {
                const pageStartS = currentS;
                const pageStartA = currentA;
                let lineCountApproximation = 0;
                let pageEndS = currentS;
                let pageEndA = currentA;
                if (juzBoundariesData[currentJuz - 1] && pageStartS >= juzBoundariesData[currentJuz - 1].startSurah) {
                    currentJuz = getJuzFromSurahAyah(pageStartS, pageStartA);
                }
                while (lineCountApproximation < fullScreenReaderSettings.linesPerPage && currentS <= 114) {
                    lineCountApproximation++;
                    pageEndS = currentS;
                    pageEndA = currentA;
                    currentA++;
                    if (currentA > surahAyahCounts[currentS]) {
                        currentS++;
                        currentA = 1;
                        if (currentS > 114) break;
                    }
                }
                tajMushafPageData.push({
                    page: page,
                    startSurah: pageStartS,
                    startAyah: pageStartA,
                    endSurah: pageEndS,
                    endAyah: pageEndA,
                    juz: currentJuz
                });
                if (currentS > 114) break;
            }
            console.log(`Taj Mushaf Page Data Loaded/Recalculated: ${tajMushafPageData.length} pages based on ${fullScreenReaderSettings.linesPerPage} lines/page.`);
        }
        async function renderQuranPageEnhanced(pageNumber) {
            const contentDiv = document.getElementById('fsReaderContent');
            if (!contentDiv || !tajMushafPageData.length) {
                if (contentDiv) contentDiv.innerHTML = "<p style='color:red;text-align:center;'>Error rendering page.</p>";
                return;
            }
            contentDiv.innerHTML = '';
            contentDiv.style.alignItems = 'center';
            contentDiv.style.fontSize = fullScreenReaderSettings.fontSize;
            const pageData = tajMushafPageData.find(p => p.page === pageNumber);
            if (!pageData) {
                contentDiv.innerHTML = `<div class="fsReaderPage" style="text-align:center; padding: 50px;">Page ${pageNumber} data not found.</div>`;
                return;
            }
            fullScreenReaderCurrentPage = pageNumber;
            fullScreenReaderCurrentSurah = pageData.startSurah;
            fullScreenReaderCurrentAyah = pageData.startAyah;
            const pageDiv = document.createElement('div');
            pageDiv.className = 'fsReaderPage';
            let currentS_loop_var = pageData.startSurah;
            let currentA_loop_var = pageData.startAyah;
            while (true) {
                if (currentS_loop_var > pageData.endSurah || (currentS_loop_var === pageData.endSurah && currentA_loop_var > pageData.endAyah)) {
                    break;
                }
                if (currentS_loop_var > 114) break;
                const ayahHTML = await getAyahHTML(currentS_loop_var, currentA_loop_var);
                if (ayahHTML) {
                    if (currentA_loop_var === 1 && currentS_loop_var !== 1 && currentS_loop_var !== 9) {
                        const bismDiv = document.createElement('div');
                        bismDiv.className = 'bismillahText';
                        bismDiv.style.fontFamily = fullScreenReaderSettings.arabicFont;
                        bismDiv.textContent = "بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ";
                        pageDiv.appendChild(bismDiv);
                    }
                    const ayahSpan = document.createElement('span');
                    ayahSpan.className = 'fsReaderAyah';
                    ayahSpan.dataset.surah = currentS_loop_var;
                    ayahSpan.dataset.ayah = currentA_loop_var;
                    ayahSpan.style.fontFamily = fullScreenReaderSettings.arabicFont;
                    ayahSpan.innerHTML = ayahHTML;
                    const s_for_click = currentS_loop_var;
                    const a_for_click = currentA_loop_var;
                    ayahSpan?.addEventListener('click', () => {
                        handleAyahSpanClick(s_for_click, a_for_click);
                    });
                    pageDiv.appendChild(ayahSpan);
                    const ayahNumSpan = document.createElement('span');
                    ayahNumSpan.className = 'fsReaderAyahNumber';
                    ayahNumSpan.textContent = arabicNumber(currentA_loop_var);
                    pageDiv.appendChild(ayahNumSpan);
                    if (fullScreenReaderSettings.showTransliteration) {
                        const translitText = await getAyahTransliteration(currentS_loop_var, currentA_loop_var);
                        if (translitText) {
                            const translitSpan = document.createElement('span');
                            translitSpan.className = 'fsReaderTransliteration';
                            translitSpan.textContent = translitText;
                            pageDiv.appendChild(translitSpan);
                        }
                    }
                }
                currentA_loop_var++;
                if (currentA_loop_var > surahAyahCounts[currentS_loop_var]) {
                    currentS_loop_var++;
                    currentA_loop_var = 1;
                }
            }
            contentDiv.appendChild(pageDiv);
            contentDiv.scrollTop = 0;
            updateReaderHeaderInfo();
            updateScrubSliderRangeAndValue();
        }
        async function initializeContinuousScroll() {
            const contentDiv = document.getElementById('fsReaderContent');
            if (!contentDiv) {
                console.error("initializeContinuousScroll: fsReaderContent not found.");
                return;
            }
            contentDiv.innerHTML = '';
            contentDiv.style.alignItems = 'stretch';
            contentDiv.style.fontSize = fullScreenReaderSettings.fontSize;
            continuousScrollSurahContainer = document.createElement('div');
            continuousScrollSurahContainer.id = 'continuousScrollSurahContainer';
            contentDiv.appendChild(continuousScrollSurahContainer);
            continuousScrollNextSurahToLoad = parseInt(fullScreenReaderCurrentSurah);
            continuousScrollNextAyahToLoad = parseInt(fullScreenReaderCurrentAyah);
            if (isNaN(continuousScrollNextSurahToLoad) || continuousScrollNextSurahToLoad < 1 || continuousScrollNextSurahToLoad > 114) {
                continuousScrollNextSurahToLoad = 1;
            }
            const maxAyahsForInitS = (continuousScrollNextSurahToLoad >= 1 && continuousScrollNextSurahToLoad <= 114 && surahAyahCounts[continuousScrollNextSurahToLoad]) ? surahAyahCounts[continuousScrollNextSurahToLoad] : 0;
            if (isNaN(continuousScrollNextAyahToLoad) || continuousScrollNextAyahToLoad < 1 || (maxAyahsForInitS > 0 && continuousScrollNextAyahToLoad > maxAyahsForInitS)) {
                continuousScrollNextAyahToLoad = 1;
            }
            isLoadingMoreAyahs = false;
            await loadMoreAyahsForContinuousScroll();
            const initialTargetS = continuousScrollNextSurahToLoad;
            const initialTargetA = continuousScrollNextAyahToLoad;
            if (initialTargetS > 1 || initialTargetA > 1) {
                setTimeout(() => {
                    const targetAyahEl = continuousScrollSurahContainer.querySelector(
                        `.fsReaderAyah[data-surah="${initialTargetS}"][data-ayah="${initialTargetA}"]`
                    );
                    if (targetAyahEl) {
                        targetAyahEl.scrollIntoView({
                            behavior: "auto",
                            block: "start"
                        });
                        highlightAyahEnhanced(initialTargetS, initialTargetA);
                    }
                    updateReaderHeaderInfo();
                    updateScrubSliderRangeAndValue();
                }, 200);
            } else {
                updateReaderHeaderInfo();
                updateScrubSliderRangeAndValue();
            }
        }
        async function loadMoreAyahsForContinuousScroll() {
            if (!continuousScrollSurahContainer || !document.body.contains(continuousScrollSurahContainer)) {
                isLoadingMoreAyahs = false;
                return;
            }
            if (isLoadingMoreAyahs || continuousScrollNextSurahToLoad > 114) {
                if (continuousScrollNextSurahToLoad > 114) isLoadingMoreAyahs = false;
                return;
            }
            isLoadingMoreAyahs = true;
            let ayahsLoadedInBatch = 0;
            let currentRenderingSurah = -1;
            let surahBlockDiv = null;
            while (ayahsLoadedInBatch < CONTINUOUS_SCROLL_LOAD_COUNT && continuousScrollNextSurahToLoad <= 114) {
                if (!continuousScrollSurahContainer || !document.body.contains(continuousScrollSurahContainer)) {
                    isLoadingMoreAyahs = false;
                    return;
                }
                const s_loop = parseInt(continuousScrollNextSurahToLoad);
                const a_loop = parseInt(continuousScrollNextAyahToLoad);
                if (isNaN(s_loop) || isNaN(a_loop) || s_loop < 1 || s_loop > 114 || a_loop < 1) {
                    isLoadingMoreAyahs = false;
                    break;
                }
                if (s_loop !== currentRenderingSurah) {
                    currentRenderingSurah = s_loop;
                    surahBlockDiv = continuousScrollSurahContainer.querySelector(`.continuousSurahBlock[data-surah-num="${s_loop}"]`);
                    if (!surahBlockDiv) {
                        surahBlockDiv = document.createElement('div');
                        surahBlockDiv.className = 'continuousSurahBlock';
                        surahBlockDiv.dataset.surahNum = s_loop;
                        const surahNameHeader = document.createElement('h2');
                        surahNameHeader.className = 'surahNameHeader';
                        surahNameHeader.style.fontFamily = fullScreenReaderSettings.arabicFont;
                        surahNameHeader.textContent = `${s_loop}. Surah ${surahNames[s_loop - 1] || `Surah ${s_loop}`}`;
                        surahBlockDiv.appendChild(surahNameHeader);
                        if (s_loop !== 1 && s_loop !== 9) {
                            const bismDiv = document.createElement('div');
                            bismDiv.className = 'bismillahText';
                            bismDiv.style.fontFamily = fullScreenReaderSettings.arabicFont;
                            bismDiv.textContent = "بِسْمِ ٱللَّهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ";
                            surahBlockDiv.appendChild(bismDiv);
                        }
                        continuousScrollSurahContainer.appendChild(surahBlockDiv);
                    }
                }
                if (!surahBlockDiv || !document.body.contains(surahBlockDiv)) {
                    isLoadingMoreAyahs = false;
                    return;
                }
                const ayahHTML = await getAyahHTML(s_loop, a_loop);
                const ayahSpan = document.createElement('span');
                ayahSpan.className = 'fsReaderAyah';
                ayahSpan.dataset.surah = s_loop;
                ayahSpan.dataset.ayah = a_loop;
                ayahSpan.style.fontFamily = fullScreenReaderSettings.arabicFont;
                ayahSpan.innerHTML = ayahHTML;
                const s_for_click = s_loop;
                const a_for_click = a_loop;
                ayahSpan?.addEventListener('click', () => {
                    handleAyahSpanClick(s_for_click, a_for_click);
                });
                surahBlockDiv.appendChild(ayahSpan);
                const ayahNumSpan = document.createElement('span');
                ayahNumSpan.className = 'fsReaderAyahNumber';
                ayahNumSpan.textContent = arabicNumber(a_loop);
                surahBlockDiv.appendChild(ayahNumSpan);
                ayahsLoadedInBatch++;
                if (fullScreenReaderSettings.showTransliteration) {
                    const translitText = await getAyahTransliteration(s_loop, a_loop);
                    if (translitText) {
                        const translitSpan = document.createElement('span');
                        translitSpan.className = 'fsReaderTransliteration';
                        translitSpan.textContent = translitText;
                        surahBlockDiv.appendChild(translitSpan);
                    }
                }
                continuousScrollNextAyahToLoad++;
                const maxAyahsThisSurah = (s_loop >= 1 && s_loop <= 114 && surahAyahCounts[s_loop]) ? surahAyahCounts[s_loop] : 0;
                if (maxAyahsThisSurah > 0 && continuousScrollNextAyahToLoad > maxAyahsThisSurah) {
                    if (s_loop < 114) {
                        continuousScrollNextSurahToLoad = s_loop + 1;
                        continuousScrollNextAyahToLoad = 1;
                    } else {
                        continuousScrollNextSurahToLoad = 115;
                        break;
                    }
                } else if (maxAyahsThisSurah === 0 && s_loop <= 114) {
                    isLoadingMoreAyahs = false;
                    break;
                }
            }
            isLoadingMoreAyahs = false;
        }

        function populateIndexLists() {
            const surahListEl = document.getElementById('fsIndexSurahList');
            const juzListEl = document.getElementById('fsIndexJuzList');
            if (!surahListEl || !juzListEl) return;
            surahListEl.innerHTML = '';
            juzListEl.innerHTML = '';
            for (let i = 1; i <= 114; i++) {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.dataset.surah = i;
                a.innerHTML = `<span class="index-list-item-num">${arabicNumber(i)}</span> ${surahNames[i - 1] || `Surah ${i}`}`;
                a?.addEventListener('click', handleIndexSurahClick);
                li.appendChild(a);
                surahListEl.appendChild(li);
            }
            juzBoundariesData.forEach(juzInfo => {
                const li = document.createElement('li');
                const a = document.createElement('a');
                a.href = '#';
                a.dataset.juz = juzInfo.juz;
                a.dataset.startSurah = juzInfo.startSurah;
                a.dataset.startAyah = juzInfo.startAyah;
                a.innerHTML = `<span class="index-list-item-num">${arabicNumber(juzInfo.juz)}</span> ${juzInfo.name}`;
                a?.addEventListener('click', handleIndexJuzClick);
                li.appendChild(a);
                juzListEl.appendChild(li);
            });
            populateThemesIndexList();
        }
        async function handleIndexSurahClick(event) {
            event.preventDefault();
            const surahNum = parseInt(event.currentTarget.dataset.surah) || 1;
            const indexPanel = document.getElementById('fsReaderIndexPanel');
            if (indexPanel) indexPanel.style.display = 'none';
            const contentDiv = document.getElementById('fsReaderContent');
            if (!contentDiv) {
                console.error("handleIndexSurahClick: fsReaderContent not found.");
                return;
            }
            stopAndClearAudio();
            fullScreenReaderCurrentSurah = surahNum;
            fullScreenReaderCurrentAyah = 1;
            if (fullScreenReaderViewMode === 'paged') {
                const page = surahToPageEnhanced(surahNum, 1);
                fullScreenReaderCurrentPage = page;
                await renderQuranPageEnhanced(page);
            } else {
                continuousScrollNextSurahToLoad = surahNum;
                continuousScrollNextAyahToLoad = 1;
                await initializeContinuousScroll();
            }
        }
        async function handleIndexJuzClick(event) {
            event.preventDefault();
            const startS = parseInt(event.currentTarget.dataset.startSurah);
            const startA = parseInt(event.currentTarget.dataset.startAyah);
            const indexPanel = document.getElementById('fsReaderIndexPanel');
            if (indexPanel) indexPanel.style.display = 'none';
            const contentDiv = document.getElementById('fsReaderContent');
            if (!contentDiv) {
                console.error("handleIndexJuzClick: fsReaderContent not found.");
                return;
            }
            stopAndClearAudio();
            fullScreenReaderCurrentSurah = startS;
            fullScreenReaderCurrentAyah = startA;
            if (fullScreenReaderViewMode === 'paged') {
                const page = surahToPageEnhanced(startS, startA);
                fullScreenReaderCurrentPage = page;
                await renderQuranPageEnhanced(page);
                setTimeout(() => highlightAndScrollToAyahInPage(startS, startA), 250);
            } else {
                if (continuousScrollSurahContainer) {
                    continuousScrollSurahContainer.innerHTML = '';
                }
                continuousScrollNextSurahToLoad = startS;
                continuousScrollNextAyahToLoad = startA;
                await initializeContinuousScroll();
            }
            updateReaderHeaderInfo();
            updateScrubSliderRangeAndValue();
        }

        function highlightAndScrollToAyahInPage(surah, ayah) {
            const targetAyahEl = document.querySelector(`#fsReaderContent .fsReaderAyah[data-surah="${surah}"][data-ayah="${ayah}"]`);
            if (targetAyahEl) {
                targetAyahEl.scrollIntoView({
                    behavior: "smooth",
                    block: "center"
                });
                highlightAyahEnhanced(surah, ayah);
            }
        }

        function updateReaderHeaderInfo() {
            const surahTitleEl = document.getElementById('fsReaderSurahTitle');
            const pageInfoEl = document.getElementById('fsReaderPageInfo');
            if (!surahTitleEl || !pageInfoEl) return;
            let displayS = parseInt(fullScreenReaderCurrentSurah);
            let displayA = parseInt(fullScreenReaderCurrentAyah);
            let displayPage = parseInt(fullScreenReaderCurrentPage);
            if (isNaN(displayS) || displayS < 1 || displayS > 114) displayS = 1;
            const maxAyahsForCurrentDisplayS = (displayS >= 1 && displayS <= 114 && surahAyahCounts[displayS]) ? surahAyahCounts[displayS] : 0;
            if (isNaN(displayA) || displayA < 1 || (maxAyahsForCurrentDisplayS > 0 && displayA > maxAyahsForCurrentDisplayS)) displayA = 1;
            if (isNaN(displayPage) || displayPage < 1) displayPage = 1;
            if (fullScreenReaderViewMode === 'continuous-scroll' && continuousScrollSurahContainer) {
                const firstVisibleAyah = findFirstVisibleAyah();
                if (firstVisibleAyah) {
                    let sFromDOM = parseInt(firstVisibleAyah.dataset.surah);
                    let aFromDOM = parseInt(firstVisibleAyah.dataset.ayah);
                    if (!isNaN(sFromDOM) && sFromDOM >= 1 && sFromDOM <= 114) {
                        displayS = sFromDOM;
                        fullScreenReaderCurrentSurah = sFromDOM;
                    }
                    const maxAyahsForDomS = (sFromDOM >= 1 && sFromDOM <= 114 && surahAyahCounts[sFromDOM]) ? surahAyahCounts[sFromDOM] : 0;
                    if (!isNaN(aFromDOM) && aFromDOM >= 1 && (maxAyahsForDomS > 0 && aFromDOM <= maxAyahsForDomS)) {
                        displayA = aFromDOM;
                        fullScreenReaderCurrentAyah = aFromDOM;
                    }
                }
            } else if (fullScreenReaderViewMode === 'paged') {
                const pageData = tajMushafPageData.find(p => p.page === displayPage);
                if (pageData) {
                    displayS = pageData.startSurah;
                    displayA = pageData.startAyah;
                } else {
                    displayS = 1;
                    displayA = 1;
                    displayPage = 1;
                }
            }
            let displayJuz = getJuzFromSurahAyah(displayS, displayA);
            if (isNaN(displayJuz) || displayJuz < 1 || displayJuz > 30) displayJuz = 1;
            const surahNameText = (displayS >= 1 && displayS <= 114 && surahNames[displayS - 1]) ?
                surahNames[displayS - 1] :
                `Surah ${displayS}`;
            surahTitleEl.textContent = `Surah ${displayS}: ${surahNameText}`;
            let pageText = `Juz ${displayJuz}`;
            if (fullScreenReaderViewMode === 'paged') {
                pageText = `Page ${displayPage}/${TAJ_COMPANY_PAGES} (Juz ${displayJuz})`;
            } else {
                pageText = `(S:${displayS} A:${displayA}) Juz ${displayJuz}`;
            }
            pageInfoEl.textContent = pageText;
            updateVisualReadingProgress(displayS, displayA);
        }

        function handlePageUnload() {
            if (isFullScreenReaderActive) {
                if (isDailyReadingSessionActive) {
                    saveLastReadPosition();
                    logVisualReadingSession();
                }
                if (isUserLoggedIn) {
                    logAutoRecitationSession();
                }
            }
        }

        function findFirstVisibleAyah() {
            const contentDiv = document.getElementById('fsReaderContent');
            if (!contentDiv) return null;
            const ayahs = contentDiv.querySelectorAll('.fsReaderAyah');
            for (let ayahEl of ayahs) {
                const rect = ayahEl.getBoundingClientRect();
                const contentRect = contentDiv.getBoundingClientRect();
                if (rect.top >= contentRect.top && rect.top <= contentRect.bottom - 50) {
                    return ayahEl;
                }
            }
            return ayahs.length > 0 ? ayahs[0] : null;
        }

        function updateScrubSliderRangeAndValue() {
            const slider = document.getElementById('fsReaderScrubSlider');
            if (!slider) return;
            if (fullScreenReaderViewMode === 'paged') {
                slider.min = 1;
                slider.max = TAJ_COMPANY_PAGES;
                slider.value = fullScreenReaderCurrentPage;
            } else {
                slider.min = 1;
                slider.max = 114;
                slider.value = fullScreenReaderCurrentSurah;
            }
        }
        async function goToNextEnhanced() {
            stopAndClearAudio();
            if (fullScreenReaderViewMode === 'paged') {
                if (fullScreenReaderCurrentPage < TAJ_COMPANY_PAGES) {
                    fullScreenReaderCurrentPage++;
                    await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
                }
            } else {
                const contentDiv = document.getElementById('fsReaderContent');
                contentDiv.scrollTop += contentDiv.clientHeight * 0.8;
                if (contentDiv.scrollTop + contentDiv.clientHeight >= contentDiv.scrollHeight - 200) {
                    await loadMoreAyahsForContinuousScroll();
                }
                setTimeout(updateReaderHeaderInfo, 300);
            }
        }
        async function goToPrevEnhanced() {
            stopAndClearAudio();
            if (fullScreenReaderViewMode === 'paged') {
                if (fullScreenReaderCurrentPage > 1) {
                    fullScreenReaderCurrentPage--;
                    await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
                }
            } else {
                const contentDiv = document.getElementById('fsReaderContent');
                contentDiv.scrollTop -= contentDiv.clientHeight * 0.8;
                setTimeout(updateReaderHeaderInfo, 300);
            }
        }

        function surahToPageEnhanced(surah, ayah = 1) {
            if (!tajMushafPageData || tajMushafPageData.length === 0) return 1;
            for (const pageInfo of tajMushafPageData) {
                if (surah === pageInfo.startSurah && surah === pageInfo.endSurah) {
                    if (ayah >= pageInfo.startAyah && ayah <= pageInfo.endAyah) return pageInfo.page;
                } else if (surah === pageInfo.startSurah && ayah >= pageInfo.startAyah) {
                    return pageInfo.page;
                } else if (surah === pageInfo.endSurah && ayah <= pageInfo.endAyah) {
                    return pageInfo.page;
                } else if (surah > pageInfo.startSurah && surah < pageInfo.endSurah) {
                    return pageInfo.page;
                }
            }
            const firstPageOfSurah = tajMushafPageData.find(p => p.startSurah === surah || p.endSurah === surah);
            return firstPageOfSurah ? firstPageOfSurah.page : 1;
        }
        async function applyFullScreenReaderSettingsChanges() {
            const contentDiv = document.getElementById('fsReaderContent');
            if (contentDiv) {
                contentDiv.style.fontSize = fullScreenReaderSettings.fontSize;
            }
            if (fullScreenReaderViewMode === 'paged') {
                tajMushafPageData = [];
                await loadTajMushafPageDataIfNeeded();
                await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
            } else {
                continuousScrollSurahContainer.innerHTML = '';
                continuousScrollNextSurahToLoad = fullScreenReaderCurrentSurah;
                continuousScrollNextAyahToLoad = fullScreenReaderCurrentAyah;
                await initializeContinuousScroll();
            }
            saveFullScreenReaderSettings();
        }

        function saveFullScreenReaderSettings() {
            if (!isUserLoggedIn) {
                localStorage.setItem('fsReaderSettingsEnhanced', JSON.stringify(fullScreenReaderSettings));
                return;
            }
            sendAjaxRequest('put_setting', {
                name: 'fsReaderSettingsEnhanced',
                value: JSON.stringify(fullScreenReaderSettings)
            }).catch(console.error);
        }
        async function loadFullScreenReaderSettings() {
            let settings = null;
            if (isUserLoggedIn) {
                const result = await sendAjaxRequest('get_setting', {
                    name: 'fsReaderSettingsEnhanced'
                });
                if (result.success && result.value) {
                    try {
                        settings = JSON.parse(result.value);
                    } catch (e) {
                        console.error("Error parsing DB settings", e);
                    }
                }
            } else {
                const saved = localStorage.getItem('fsReaderSettingsEnhanced');
                if (saved) {
                    try {
                        settings = JSON.parse(saved);
                    } catch (e) {
                        console.error("Error parsing local settings", e);
                    }
                }
            }
            if (settings) {
                Object.assign(fullScreenReaderSettings, settings);
            }
            const audioSourceSelect = document.getElementById('fsAudioSourceSelect');
            if (audioSourceSelect) audioSourceSelect.value = fullScreenReaderSettings.audioSource;
            const fontSelect = document.getElementById('fsArabicFontSelect');
            if (fontSelect) fontSelect.value = fullScreenReaderSettings.arabicFont;
            const sizeSlider = document.getElementById('fsFontSizeSlider');
            const sizeValueEl = document.getElementById('fsFontSizeValue');
            if (sizeSlider && sizeValueEl) {
                sizeSlider.value = parseFloat(fullScreenReaderSettings.fontSize);
                sizeValueEl.textContent = fullScreenReaderSettings.fontSize;
            }
            const linesSlider = document.getElementById('fsLinesPerPageSlider');
            const linesValueEl = document.getElementById('fsLinesPerPageValue');
            if (linesSlider && linesValueEl) {
                linesSlider.value = fullScreenReaderSettings.linesPerPage;
                linesValueEl.textContent = fullScreenReaderSettings.linesPerPage;
            }
            const translitCheck = document.getElementById('fsShowTransliterationCheck');
            if (translitCheck) translitCheck.checked = fullScreenReaderSettings.showTransliteration;
            const tajweedCheck = document.getElementById('fsShowTajweedCheck');
            if (tajweedCheck) tajweedCheck.checked = fullScreenReaderSettings.showTajweedColors;
            const contAudioCheck = document.getElementById('fsContinuousAudioCheck');
            if (contAudioCheck) contAudioCheck.checked = fullScreenReaderSettings.continuousAudio;
            const autoScrollCheck = document.getElementById('fsAutoScrollAudioCheck');
            if (autoScrollCheck) autoScrollCheck.checked = fullScreenReaderSettings.autoScrollAudio;
            const highlightPicker = document.getElementById('fsHighlightColorPicker');
            if (highlightPicker) {
                highlightPicker.value = fullScreenReaderSettings.highlightColor.startsWith('rgba') ?
                    rgbToHex(fullScreenReaderSettings.highlightColor) :
                    fullScreenReaderSettings.highlightColor;
            }
            const viewModeSelect = document.getElementById('fsViewModeSelect');
            if (viewModeSelect) viewModeSelect.value = fullScreenReaderViewMode;
            loadAndApplyDynamicFont(fullScreenReaderSettings.arabicFont);
        }

        function rgbToHex(rgba) {
            if (rgba.startsWith('#')) return rgba;
            const parts = rgba.substring(rgba.indexOf('(') + 1, rgba.lastIndexOf(')')).split(/,\s*/);
            if (parts.length < 3) return '#ffff96';
            const r = parseInt(parts[0]).toString(16).padStart(2, '0');
            const g = parseInt(parts[1]).toString(16).padStart(2, '0');
            const b = parseInt(parts[2]).toString(16).padStart(2, '0');
            return `#${r}${g}${b}`;
        }
        async function loadLastReadPosition() {
            let posData = null;
            if (isUserLoggedIn) {
                const result = await sendAjaxRequest('get_setting', {
                    name: 'fsReaderLastPosEnhanced'
                });
                if (result.success && result.value) {
                    try {
                        posData = JSON.parse(result.value);
                    } catch (e) {
                        console.error("Error parsing DB last position", e);
                    }
                }
            } else {
                const saved = localStorage.getItem('fsReaderLastPosEnhanced');
                if (saved) {
                    try {
                        posData = JSON.parse(saved);
                    } catch (e) {
                        console.error("Error parsing local last position", e);
                    }
                }
            }
            let page = 1,
                surah = 1,
                ayah = 1,
                viewMode = 'paged';
            if (posData) {
                page = parseInt(posData.page);
                surah = parseInt(posData.surah);
                ayah = parseInt(posData.ayah);
                viewMode = posData.viewMode === 'continuous-scroll' ? 'continuous-scroll' : 'paged';
                if (isNaN(page) || page < 1 || page > TAJ_COMPANY_PAGES) page = 1;
                if (isNaN(surah) || surah < 1 || surah > 114) {
                    surah = 1;
                }
                const maxAyahsForSurah = (surah >= 1 && surah <= 114 && surahAyahCounts[surah]) ? surahAyahCounts[surah] : 0;
                if (isNaN(ayah) || ayah < 1 || (maxAyahsForSurah > 0 && ayah > maxAyahsForSurah)) {
                    ayah = 1;
                }
                if (surah === 1 && ayah > 7) ayah = 1;
            }
            fullScreenReaderCurrentPage = page;
            fullScreenReaderCurrentSurah = surah;
            fullScreenReaderCurrentAyah = ayah;
            fullScreenReaderViewMode = viewMode;
            const viewModeSelect = document.getElementById('fsViewModeSelect');
            if (viewModeSelect) {
                viewModeSelect.value = fullScreenReaderViewMode;
            }
        }

        function saveLastReadPosition() {
            const pos = {
                page: fullScreenReaderCurrentPage,
                surah: fullScreenReaderCurrentSurah,
                ayah: fullScreenReaderCurrentAyah,
                viewMode: fullScreenReaderViewMode
            };
            if (isUserLoggedIn) {
                sendAjaxRequest('put_setting', {
                    name: 'fsReaderLastPosEnhanced',
                    value: JSON.stringify(pos)
                }).catch(console.error);
            } else {
                localStorage.setItem('fsReaderLastPosEnhanced', JSON.stringify(pos));
            }
        }
        async function playAudioForAyahEnhanced(surah, ayah) {
            const sNum = parseInt(surah);
            const aNum = parseInt(ayah);
            if (isNaN(sNum) || isNaN(aNum) || sNum < 1 || sNum > 114 || aNum < 1 ||
                (surahAyahCounts[sNum] && aNum > surahAyahCounts[sNum])) {
                if (fullScreenReaderIsPlayingAudio) stopAndClearAudio();
                return;
            }
            if (isUserLoggedIn && !fullScreenReaderSettings.continuousAudio) {
                await logAutoRecitationSession();
            }
            stopAndClearAudio();
            if (isUserLoggedIn) {
                if (!autoLogSession.active) {
                    autoLogSession.active = true;
                    autoLogSession.startSurah = sNum;
                    autoLogSession.startAyah = aNum;
                } else {
                    autoLogSession.endSurah = sNum;
                    autoLogSession.endAyah = aNum;
                }
            }
            let audioSrc;
            const audioSourceMode = fullScreenReaderSettings.audioSource || 'quran';
            if (audioSourceMode === 'en' || audioSourceMode === 'ur') {
                let absoluteAyahNum = 0;
                for (let i = 1; i < sNum; i++) {
                    absoluteAyahNum += surahAyahCounts[i];
                }
                absoluteAyahNum += aNum;
                audioSrc = getQuranAudioUrl(audioSourceMode)(absoluteAyahNum);
            } else {
                const surahPadded = String(sNum).padStart(3, '0');
                const ayahPadded = String(aNum).padStart(3, '0');
                audioSrc = `https://everyayah.com/data/Alafasy_128kbps/${surahPadded}${ayahPadded}.mp3`;
            }
            fullScreenReaderAudioPlayer = new Audio(audioSrc);
            fullScreenReaderAudioPlayer.currentSrcAyahS = sNum;
            fullScreenReaderAudioPlayer.currentSrcAyahA = aNum;
            try {
                await fullScreenReaderAudioPlayer.play();
                fullScreenReaderIsPlayingAudio = true;
                const playPauseBtn = document.getElementById('fsReaderPlayPauseBtn');
                if (playPauseBtn) playPauseBtn.textContent = '❚❚';
                highlightAyahEnhanced(sNum, aNum);
                fullScreenReaderAudioPlayer.onended = async () => {
                    fullScreenReaderIsPlayingAudio = false;
                    const playPauseBtn = document.getElementById('fsReaderPlayPauseBtn');
                    if (playPauseBtn) playPauseBtn.textContent = '🔊';
                    if (fullScreenReaderSettings.continuousAudio) {
                        let nextS = sNum;
                        let nextA = aNum + 1;
                        if (surahAyahCounts[nextS] && nextA > surahAyahCounts[nextS]) {
                            if (nextS < 114) {
                                nextS++;
                                nextA = 1;
                            } else {
                                removeHighlightEnhanced();
                                if (isUserLoggedIn) await logAutoRecitationSession();
                                return;
                            }
                        } else if (!surahAyahCounts[nextS]) {
                            removeHighlightEnhanced();
                            if (isUserLoggedIn) await logAutoRecitationSession();
                            return;
                        }
                        fullScreenReaderCurrentSurah = nextS;
                        fullScreenReaderCurrentAyah = nextA;
                        updateReaderHeaderInfo();
                        playAudioForAyahEnhanced(nextS, nextA);
                    } else {
                        removeHighlightEnhanced();
                        if (isUserLoggedIn) await logAutoRecitationSession();
                    }
                };
                fullScreenReaderAudioPlayer.onerror = (e) => {
                    console.error("Audio playback error", e);
                    if (isUserLoggedIn) logAutoRecitationSession();
                };
            } catch (err) {
                console.error("Could not play audio:", err);
                if (isUserLoggedIn) logAutoRecitationSession();
            }
        }

        function toggleAudioPlaybackEnhanced() {
            const playPauseBtn = document.getElementById('fsReaderPlayPauseBtn');
            if (!fullScreenReaderAudioPlayer || fullScreenReaderAudioPlayer.src === '' || fullScreenReaderAudioPlayer.src === window.location.href || fullScreenReaderAudioPlayer.ended) {
                let targetS = parseInt(fullScreenReaderCurrentSurah);
                let targetA = parseInt(fullScreenReaderCurrentAyah);
                if (isNaN(targetS) || targetS < 1 || targetS > 114) targetS = 1;
                const maxAyahsForGlobalS = (targetS >= 1 && targetS <= 114 && surahAyahCounts[targetS]) ? surahAyahCounts[targetS] : 0;
                if (isNaN(targetA) || targetA < 1 || (maxAyahsForGlobalS > 0 && targetA > maxAyahsForGlobalS)) targetA = 1;
                if (fullScreenReaderViewMode === 'continuous-scroll') {
                    const firstVisible = findFirstVisibleAyah();
                    if (firstVisible && firstVisible.dataset.surah && firstVisible.dataset.ayah) {
                        let sFromDOM = parseInt(firstVisible.dataset.surah);
                        let aFromDOM = parseInt(firstVisible.dataset.ayah);
                        if (!isNaN(sFromDOM) && sFromDOM >= 1 && sFromDOM <= 114) {
                            const maxAyahsForDomS = (sFromDOM >= 1 && sFromDOM <= 114 && surahAyahCounts[sFromDOM]) ? surahAyahCounts[sFromDOM] : 0;
                            if (!isNaN(aFromDOM) && aFromDOM >= 1 && (maxAyahsForDomS > 0 && aFromDOM <= maxAyahsForDomS)) {
                                targetS = sFromDOM;
                                targetA = aFromDOM;
                            } else {}
                        } else {}
                    } else {}
                } else {}
                fullScreenReaderCurrentSurah = targetS;
                fullScreenReaderCurrentAyah = targetA;
                updateReaderHeaderInfo();
                playAudioForAyahEnhanced(targetS, targetA);
            } else if (fullScreenReaderIsPlayingAudio) {
                fullScreenReaderAudioPlayer.pause();
                fullScreenReaderIsPlayingAudio = false;
                if (playPauseBtn) playPauseBtn.textContent = '🔊';
            } else {
                fullScreenReaderAudioPlayer.play().then(() => {
                    fullScreenReaderIsPlayingAudio = true;
                    if (playPauseBtn) playPauseBtn.textContent = '❚❚';
                    highlightAyahEnhanced(parseInt(fullScreenReaderAudioPlayer.currentSrcAyahS) || fullScreenReaderCurrentSurah,
                        parseInt(fullScreenReaderAudioPlayer.currentSrcAyahA) || fullScreenReaderCurrentAyah);
                }).catch(err => {
                    console.error("Error resuming audio:", err);
                    if (playPauseBtn) playPauseBtn.textContent = '🔊';
                });
            }
        }

        function stopAndClearAudio() {
            if (isUserLoggedIn) {
                logAutoRecitationSession();
            }
            if (fullScreenReaderAudioPlayer) {
                fullScreenReaderAudioPlayer.pause();
                fullScreenReaderAudioPlayer.onended = null;
                fullScreenReaderAudioPlayer.onerror = null;
                fullScreenReaderAudioPlayer.src = '';
                fullScreenReaderAudioPlayer.load();
                fullScreenReaderAudioPlayer = null;
            }
            fullScreenReaderIsPlayingAudio = false;
            removeHighlightEnhanced();
            const playPauseBtn = document.getElementById('fsReaderPlayPauseBtn');
            if (playPauseBtn) playPauseBtn.textContent = '🔊';
        }

        function highlightAyahEnhanced(surah, ayah) {
            removeHighlightEnhanced();
            const contentDiv = document.getElementById('fsReaderContent');
            const ayahEl = contentDiv.querySelector(`.fsReaderAyah[data-surah="${surah}"][data-ayah="${ayah}"]`);
            if (ayahEl) {
                ayahEl.style.backgroundColor = fullScreenReaderSettings.highlightColor;
                ayahEl.classList.add('highlighted-ayah');
                if (fullScreenReaderSettings.autoScrollAudio) {
                    const rect = ayahEl.getBoundingClientRect();
                    const contentRect = contentDiv.getBoundingClientRect();
                    const isVisible = rect.top >= contentRect.top && rect.bottom <= contentRect.bottom;
                    if (!isVisible) {
                        ayahEl.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                            inline: 'nearest'
                        });
                    }
                }
            }
        }

        function removeHighlightEnhanced() {
            const highlighted = document.querySelector('#fsReaderContent .highlighted-ayah');
            if (highlighted) {
                highlighted.style.backgroundColor = '';
                highlighted.classList.remove('highlighted-ayah');
            }
        }

        function arabicNumber(num) {
            const arabicNumerals = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
            return String(num).split('').map(digit => arabicNumerals[parseInt(digit)]).join('');
        }
        async function getAyahTransliteration(surah, ayah) {
            return null;
        }
        let isReaderChromeVisible = true;

        function applyFontChangeToView() {
            const newFont = fullScreenReaderSettings.arabicFont;
            document.querySelectorAll(
                '#fsReaderContent .fsReaderAyah, #fsReaderContent .bismillahText, #fsReaderContent .surahNameHeader'
            ).forEach(el => {
                el.style.fontFamily = newFont;
            });
        }

        function setupEnhancedFullScreenReaderEventListeners() {
            const closeBtn = document.getElementById('fsReaderCloseBtnEnhanced');
            const nextBtn = document.getElementById('fsReaderNextBtn');
            const prevBtn = document.getElementById('fsReaderPrevBtn');
            const playPauseBtn = document.getElementById('fsReaderPlayPauseBtn');
            const settingsToggleBtn = document.getElementById('fsReaderSettingsToggleBtn');
            const indexToggleBtn = document.getElementById('fsReaderIndexToggleBtn');
            const settingsPanel = document.getElementById('fsReaderSettingsPanel');
            const indexPanel = document.getElementById('fsReaderIndexPanel');
            const contentDiv = document.getElementById('fsReaderContent');
            const headerDiv = document.getElementById('fsReaderHeader');
            const footerDiv = document.getElementById('fsReaderFooter');
            const audioSourceSelect = document.getElementById('fsAudioSourceSelect');
            if (audioSourceSelect) {
                audioSourceSelect?.addEventListener('change', (e) => {
                    fullScreenReaderSettings.audioSource = e.target.value;
                    stopAndClearAudio();
                    saveFullScreenReaderSettings();
                });
            }
            const bookmarkBtn = document.getElementById('fsReaderBookmarkBtn');
            if (bookmarkBtn) {
                bookmarkBtn?.addEventListener('click', async (event) => {
                    if (event.ctrlKey || event.metaKey) {
                        if (isUserLoggedIn) {
                            saveDailyBookmarkPosition();
                            event.target.style.transition = 'transform 0.2s';
                            event.target.style.transform = 'scale(1.4)';
                            setTimeout(() => {
                                event.target.style.transform = 'scale(1)';
                            }, 250);
                        } else {
                            alert('Login to save your daily reading spot.');
                        }
                    } else {
                        if (isUserLoggedIn) {
                            isDailyReadingSessionActive = true;
                            visualReadingSession = {
                                active: true,
                                startSurah: fullScreenReaderCurrentSurah,
                                startAyah: fullScreenReaderCurrentAyah,
                                endSurah: fullScreenReaderCurrentSurah,
                                endAyah: fullScreenReaderCurrentAyah
                            };
                            event.target.style.backgroundColor = 'var(--color-success)';
                            setTimeout(() => {
                                event.target.style.backgroundColor = '';
                            }, 1000);
                            await loadAndGoToDailyBookmark();
                        } else {
                            alert('Login to use the daily reading bookmark feature.');
                        }
                    }
                });
            }
            const scrubSlider = document.getElementById('fsReaderScrubSlider');
            if (closeBtn) closeBtn?.addEventListener('click', closeFullScreenQuranReaderEnhanced);
            if (nextBtn) nextBtn?.addEventListener('click', goToNextEnhanced);
            if (prevBtn) prevBtn?.addEventListener('click', goToPrevEnhanced);
            if (playPauseBtn) playPauseBtn?.addEventListener('click', toggleAudioPlaybackEnhanced);
            if (settingsToggleBtn && settingsPanel && indexPanel) {
                settingsToggleBtn?.addEventListener('click', () => {
                    const isSettingsVisible = settingsPanel.style.display === 'block';
                    settingsPanel.style.display = isSettingsVisible ? 'none' : 'block';
                    indexPanel.style.display = 'none';
                });
            }
            if (indexToggleBtn && indexPanel && settingsPanel) {
                indexToggleBtn?.addEventListener('click', () => {
                    const isIndexVisible = indexPanel.style.display === 'block';
                    indexPanel.style.display = isIndexVisible ? 'none' : 'block';
                    settingsPanel.style.display = 'none';
                });
            }
            if (contentDiv && headerDiv && footerDiv && settingsPanel && indexPanel) {
                contentDiv?.addEventListener('click', (e) => {
                    if (e.target === contentDiv && settingsPanel.style.display === 'none' && indexPanel.style.display === 'none') {
                        toggleReaderChromeVisibility();
                    } else if (settingsPanel.style.display === 'block' && !settingsPanel.contains(e.target) && e.target !== settingsToggleBtn) {
                        settingsPanel.style.display = 'none';
                    } else if (indexPanel.style.display === 'block' && !indexPanel.contains(e.target) && e.target !== indexToggleBtn) {
                        indexPanel.style.display = 'none';
                    }
                });
            }
            const arabicFontSelect = document.getElementById('fsArabicFontSelect');
            const fontSizeSlider = document.getElementById('fsFontSizeSlider');
            const fontSizeValueEl = document.getElementById('fsFontSizeValue');
            const linesPerPageSlider = document.getElementById('fsLinesPerPageSlider');
            const linesPerPageValueEl = document.getElementById('fsLinesPerPageValue');
            const showTransliterationCheck = document.getElementById('fsShowTransliterationCheck');
            const continuousAudioCheck = document.getElementById('fsContinuousAudioCheck');
            const autoScrollAudioCheck = document.getElementById('fsAutoScrollAudioCheck');
            const highlightColorPicker = document.getElementById('fsHighlightColorPicker');
            const viewModeSelect = document.getElementById('fsViewModeSelect');
            const showTajweedCheck = document.getElementById('fsShowTajweedCheck');
            if (showTajweedCheck) {
                showTajweedCheck.checked = fullScreenReaderSettings.showTajweedColors;
                showTajweedCheck?.addEventListener('change', (e) => {
                    fullScreenReaderSettings.showTajweedColors = e.target.checked;
                    applyFullScreenReaderSettingsChanges();
                });
            }
            if (arabicFontSelect) {
                arabicFontSelect?.addEventListener('change', (e) => {
                    loadAndApplyDynamicFont(e.target.value);
                    applyFontChangeToView();
                    saveFullScreenReaderSettings();
                });
            }
            if (fontSizeSlider && fontSizeValueEl) {
                fontSizeSlider?.addEventListener('input', (e) => {
                    fullScreenReaderSettings.fontSize = `${e.target.value}rem`;
                    fontSizeValueEl.textContent = fullScreenReaderSettings.fontSize;
                    const contentDiv = document.getElementById('fsReaderContent');
                    if (contentDiv) contentDiv.style.fontSize = fullScreenReaderSettings.fontSize;
                });
                fontSizeSlider?.addEventListener('change', applyFullScreenReaderSettingsChanges);
            }
            if (linesPerPageSlider && linesPerPageValueEl) {
                linesPerPageSlider?.addEventListener('input', (e) => {
                    fullScreenReaderSettings.linesPerPage = parseInt(e.target.value, 10);
                    linesPerPageValueEl.textContent = fullScreenReaderSettings.linesPerPage;
                });
                linesPerPageSlider?.addEventListener('change', applyFullScreenReaderSettingsChanges);
            }
            if (showTransliterationCheck) showTransliterationCheck?.addEventListener('change', (e) => {
                fullScreenReaderSettings.showTransliteration = e.target.checked;
                applyFullScreenReaderSettingsChanges();
            });
            if (continuousAudioCheck) continuousAudioCheck?.addEventListener('change', (e) => {
                fullScreenReaderSettings.continuousAudio = e.target.checked;
                saveFullScreenReaderSettings();
            });
            if (autoScrollAudioCheck) autoScrollAudioCheck?.addEventListener('change', (e) => {
                fullScreenReaderSettings.autoScrollAudio = e.target.checked;
                saveFullScreenReaderSettings();
            });
            if (highlightColorPicker) highlightColorPicker?.addEventListener('change', (e) => {
                fullScreenReaderSettings.highlightColor = e.target.value;
                saveFullScreenReaderSettings();
            });
            if (viewModeSelect) {
                viewModeSelect?.addEventListener('change', async (e) => {
                    fullScreenReaderViewMode = e.target.value;
                    saveFullScreenReaderSettings();
                    document.getElementById('fsLinesPerPageSettingDiv').style.display = (fullScreenReaderViewMode === 'paged') ? 'block' : 'none';
                    stopAndClearAudio();
                    if (fullScreenReaderViewMode === 'paged') {
                        fullScreenReaderCurrentPage = surahToPageEnhanced(fullScreenReaderCurrentSurah, fullScreenReaderCurrentAyah);
                        await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
                    } else {
                        continuousScrollNextSurahToLoad = fullScreenReaderCurrentSurah;
                        continuousScrollNextAyahToLoad = 1;
                        await initializeContinuousScroll();
                    }
                    updateReaderHeaderInfo();
                    updateScrubSliderRangeAndValue();
                });
            }
            document.querySelectorAll('#fsReaderIndexPanel .index-tab').forEach(tab => {
                tab?.addEventListener('click', (e) => {
                    document.querySelectorAll('#fsReaderIndexPanel .index-tab').forEach(t => t.classList.remove('active-tab'));
                    e.target.classList.add('active-tab');
                    document.querySelectorAll('#fsReaderIndexPanel .index-content-panel').forEach(p => p.style.display = 'none');
                    document.getElementById(`fsIndexContent${e.target.dataset.tab.charAt(0).toUpperCase() + e.target.dataset.tab.slice(1)}`).style.display = 'block';
                });
            });
            if (scrubSlider) {
                scrubSlider?.addEventListener('input', () => {
                    if (fullScreenReaderViewMode === 'paged') {
                        const page = parseInt(scrubSlider.value, 10);
                        const pageData = tajMushafPageData.find(p => p.page === page);
                        if (pageData) {
                            document.getElementById('fsReaderSurahTitle').textContent = `Surah ${pageData.startSurah}: ${surahNames[pageData.startSurah - 1]}`;
                            document.getElementById('fsReaderPageInfo').textContent = `Page ${page}/${TAJ_COMPANY_PAGES} (Juz ${pageData.juz || getJuzFromSurahAyah(pageData.startSurah, pageData.startAyah)})`;
                        }
                    } else {
                        const surah = parseInt(scrubSlider.value, 10);
                        document.getElementById('fsReaderSurahTitle').textContent = `Surah ${surah}: ${surahNames[surah - 1]}`;
                        document.getElementById('fsReaderPageInfo').textContent = `Juz ${getJuzFromSurahAyah(surah, 1)}`;
                    }
                });
                scrubSlider?.addEventListener('change', async () => {
                    stopAndClearAudio();
                    if (fullScreenReaderViewMode === 'paged') {
                        fullScreenReaderCurrentPage = parseInt(scrubSlider.value, 10);
                        await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
                    } else {
                        fullScreenReaderCurrentSurah = parseInt(scrubSlider.value, 10);
                        fullScreenReaderCurrentAyah = 1;
                        continuousScrollNextSurahToLoad = fullScreenReaderCurrentSurah;
                        continuousScrollNextAyahToLoad = 1;
                        await initializeContinuousScroll();
                    }
                    updateReaderHeaderInfo();
                });
            }
            if (contentDiv) {
                contentDiv?.addEventListener('scroll', async () => {
                    if (fullScreenReaderViewMode === 'continuous-scroll' && !isLoadingMoreAyahs) {
                        const scrollThreshold = contentDiv.scrollHeight - contentDiv.clientHeight - 700;
                        if (contentDiv.scrollTop >= scrollThreshold) {
                            await loadMoreAyahsForContinuousScroll();
                        }
                        updateReaderHeaderInfo();
                    }
                });
            }
            setupKeyboardAndTapNavigation();
        }

        function loadAndApplyDynamicFont(fontValue) {
            fullScreenReaderSettings.arabicFont = fontValue;
        }
        let autoLogSession = {
            active: false,
            startSurah: null,
            startAyah: null,
            endSurah: null,
            endAyah: null
        };

        function saveDailyBookmarkPosition() {
            const bookmark = {
                surah: fullScreenReaderCurrentSurah,
                ayah: fullScreenReaderCurrentAyah,
                page: fullScreenReaderCurrentPage,
                viewMode: fullScreenReaderViewMode
            };
            if (isUserLoggedIn) {
                sendAjaxRequest('put_setting', {
                    name: 'fsReaderDailyBookmark',
                    value: JSON.stringify(bookmark)
                }).catch(console.error);
            } else {
                localStorage.setItem('fsReaderDailyBookmark', JSON.stringify(bookmark));
            }
            const btn = document.getElementById('fsReaderBookmarkBtn');
            if (btn) {
                btn.style.transition = 'transform 0.2s ease-out';
                btn.style.transform = 'scale(1.4)';
                setTimeout(() => {
                    btn.style.transform = 'scale(1)';
                }, 250);
            }
        }
        async function getAyahHTML(surah, ayah) {
            if (fullScreenReaderSettings.showTajweedColors) {
                try {
                    const response = await fetch(`tajweed_data/${surah}/${ayah}.html`);
                    if (!response.ok) {
                        throw new Error(`Local file not found: ${response.statusText}`);
                    }
                    const originalHtml = await response.text();
                    const htmlWithoutVerseNumber = originalHtml.replace(/\s*﴿[٠-٩]+﴾\s*/g, '');
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = htmlWithoutVerseNumber;
                    tempDiv.querySelectorAll('tajweed').forEach(el => {
                        const span = document.createElement('span');
                        span.className = `tajweed ${el.getAttribute('class')}`;
                        span.innerHTML = el.innerHTML;
                        el.parentNode.replaceChild(span, el);
                    });
                    return tempDiv.innerHTML;
                } catch (error) {
                    console.warn(`Local Tajweed fetch failed for ${surah}:${ayah}. Falling back.`, error);
                }
            }
            const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                surah: surah,
                ayah: ayah
            });
            const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
            return ayahData ? ayahData.arabic.trim() : `[Ayah ${surah}:${ayah} not found]`;
        }
        async function loadAndGoToDailyBookmark() {
            let bookmark = null;
            if (isUserLoggedIn) {
                const result = await sendAjaxRequest('get_setting', {
                    name: 'fsReaderDailyBookmark'
                });
                if (result.success && result.value) {
                    try {
                        bookmark = JSON.parse(result.value);
                    } catch (e) {
                        console.error("Error parsing DB bookmark", e);
                    }
                }
            } else {
                const saved = localStorage.getItem('fsReaderDailyBookmark');
                if (saved) {
                    try {
                        bookmark = JSON.parse(saved);
                    } catch (e) {
                        console.error("Error parsing local bookmark", e);
                    }
                }
            }
            if (!bookmark) {
                console.warn("Daily bookmark not set. Use Ctrl+Click on the bookmark icon to set it.");
                const btn = document.getElementById('fsReaderBookmarkBtn');
                if (btn) {
                    btn.style.transition = 'transform 0.1s ease-in-out';
                    btn.style.transform = 'translateX(-3px)';
                    setTimeout(() => {
                        btn.style.transform = 'translateX(3px)';
                    }, 100);
                    setTimeout(() => {
                        btn.style.transform = 'translateX(0px)';
                    }, 200);
                }
                return;
            }
            if (isUserLoggedIn) await logAutoRecitationSession();
            stopAndClearAudio();
            fullScreenReaderCurrentSurah = bookmark.surah;
            fullScreenReaderCurrentAyah = bookmark.ayah;
            fullScreenReaderCurrentPage = bookmark.page;
            fullScreenReaderViewMode = bookmark.viewMode;
            const viewModeSelect = document.getElementById('fsViewModeSelect');
            if (viewModeSelect) viewModeSelect.value = fullScreenReaderViewMode;
            if (fullScreenReaderViewMode === 'paged') {
                document.getElementById('fsLinesPerPageSettingDiv').style.display = 'block';
                await renderQuranPageEnhanced(fullScreenReaderCurrentPage);
            } else {
                document.getElementById('fsLinesPerPageSettingDiv').style.display = 'none';
                await initializeContinuousScroll();
            }
            updateReaderHeaderInfo();
            updateScrubSliderRangeAndValue();
        }
        async function logAutoRecitationSession() {
            if (!isUserLoggedIn || !autoLogSession.active) return;
            const {
                startSurah,
                startAyah,
                endSurah,
                endAyah
            } = autoLogSession;
            const finalEndAyah = endAyah || startAyah;
            const finalEndSurah = endSurah || startSurah;
            const qariMap = {
                'quran': 'Mishary Alafasy (FS Reader)',
                'en': 'Ibrahim Walk (FS Reader)',
                'ur': 'Fateh Jalandhri (FS Reader)'
            };
            const qariName = qariMap[fullScreenReaderSettings.audioSource] || 'FS Reader';
            const logEntry = {
                surah: startSurah,
                ayah_start: startAyah,
                ayah_end: finalEndAyah,
                qari: qariName,
                log_date: new Date().toISOString().split('T')[0],
                notes: `Auto-logged from full-screen reader. Listened to S${startSurah}:${startAyah} through S${finalEndSurah}:${finalEndAyah}.`
            };
            try {
                const result = await sendAjaxRequest('save_recitation_log', logEntry);
                if (result.success) {
                    console.log("Auto-logged recitation session:", logEntry);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error("Failed to auto-log recitation session:", error);
            }
            autoLogSession = {
                active: false,
                startSurah: null,
                startAyah: null,
                endSurah: null,
                endAyah: null
            };
        }

        function toggleReaderChromeVisibility(forceShow) {
            const headerDiv = document.getElementById('fsReaderHeader');
            const footerDiv = document.getElementById('fsReaderFooter');
            const settingsPanel = document.getElementById('fsReaderSettingsPanel');
            const indexPanel = document.getElementById('fsReaderIndexPanel');
            if (forceShow !== undefined) {
                isReaderChromeVisible = !forceShow;
            }
            isReaderChromeVisible = !isReaderChromeVisible;
            if (headerDiv) headerDiv.style.display = isReaderChromeVisible ? 'flex' : 'none';
            if (footerDiv) footerDiv.style.display = isReaderChromeVisible ? 'flex' : 'none';
            if (!isReaderChromeVisible) {
                if (settingsPanel) settingsPanel.style.display = 'none';
                if (indexPanel) indexPanel.style.display = 'none';
            }
        }

        function addEnhancedFullScreenReaderLaunchButton() {
            const quranViewerSection = document.getElementById('quran');
            if (!quranViewerSection) {
                console.error("Quran viewer section not found for launch button.");
                return;
            }
            const buttonId = 'launchFullScreenReaderBtnEnhanced';
            if (document.getElementById(buttonId)) return;
            const launchButton = document.createElement('button');
            launchButton.id = buttonId;
            launchButton.innerHTML = '📖 <span class="sr-only">Open Full Screen Reader</span>';
            launchButton.title = 'Open Immersive Quran Reader';
            launchButton.style.padding = '5px 10px';
            launchButton.style.fontSize = '1.2rem';
            launchButton.style.marginLeft = '10px';
            launchButton.style.verticalAlign = 'middle';
            launchButton?.addEventListener('click', () => {
                fullScreenReaderCurrentSurah = window.currentSurah || 1;
                fullScreenReaderCurrentAyah = window.currentAyah || 1;
                if (fullScreenReaderViewMode === 'paged') {
                    fullScreenReaderCurrentPage = surahToPageEnhanced(fullScreenReaderCurrentSurah, fullScreenReaderCurrentAyah);
                }
                launchFullScreenQuranReaderEnhanced();
                setTimeout(() => {
                    document.getElementById('fsReaderSettingsToggleBtn').click();
                    setTimeout(() => {
                        document.getElementById('fsReaderSettingsToggleBtn').click();
                    }, 200);
                    document.querySelector("#fsReaderSettingsPanel").style.right = "66px"
                    document.querySelector("#fsReaderSettingsPanel").style.top = "4px"
                    document.querySelector("#fsReaderSettingsPanel").style.maxHeight = "100%"
                }, 200);
            });
            const quranControls = quranViewerSection.querySelector('.quran-controls:first-of-type .flex-group');
            if (quranControls) {
                quranControls.appendChild(launchButton);
            } else {
                const header = quranViewerSection.querySelector('h2');
                if (header) header.insertAdjacentElement('afterend', launchButton);
                else quranViewerSection.insertBefore(launchButton, quranViewerSection.firstChild);
            }
        }

        function handleAyahSpanClick(clickedSurah, clickedAyah) {
            fullScreenReaderCurrentSurah = parseInt(clickedSurah);
            fullScreenReaderCurrentAyah = parseInt(clickedAyah);
            updateReaderHeaderInfo();
            playAudioForAyahEnhanced(fullScreenReaderCurrentSurah, fullScreenReaderCurrentAyah);
        }

        function setupKeyboardAndTapNavigation() {
            const readerOverlay = document.getElementById('fullScreenReaderOverlay');
            const contentArea = document.getElementById('fsReaderContent');
            if (!readerOverlay) {
                console.warn("Keyboard/Tap Nav: fullScreenReaderOverlay not found.");
                return;
            }
            const handleKeyDown = (event) => {
                if (!isFullScreenReaderActive ||
                    (document.activeElement && ['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName))) {
                    return;
                }
                const settingsPanel = document.getElementById('fsReaderSettingsPanel');
                const indexPanel = document.getElementById('fsReaderIndexPanel');
                if ((settingsPanel && settingsPanel.style.display === 'block') ||
                    (indexPanel && indexPanel.style.display === 'block')) {
                    if (event.key === 'Escape') {
                        if (settingsPanel && settingsPanel.style.display === 'block') settingsPanel.style.display = 'none';
                        if (indexPanel && indexPanel.style.display === 'block') indexPanel.style.display = 'none';
                        event.preventDefault();
                    }
                    return;
                }
                switch (event.key) {
                    case 'ArrowLeft':
                        if (typeof goToPrevEnhanced === "function") {
                            goToPrevEnhanced();
                            event.preventDefault();
                        }
                        break;
                    case 'ArrowRight':
                        if (typeof goToNextEnhanced === "function") {
                            goToNextEnhanced();
                            event.preventDefault();
                        }
                        break;
                    case 'Escape':
                        if (typeof closeFullScreenQuranReaderEnhanced === "function") {
                            closeFullScreenQuranReaderEnhanced();
                            event.preventDefault();
                        }
                        break;
                }
            };
            document?.addEventListener('keydown', handleKeyDown);
            if (contentArea) {
                let touchStartY = 0;
                let touchEndY = 0;
                const tapThreshold = 50;
                const screenPortionThreshold = 0.33;
                const handleTouchStart = (event) => {
                    if (event.touches.length === 1) {
                        touchStartY = event.touches[0].clientY;
                    }
                };
                const handleTouchEnd = (event) => {
                    if (event.changedTouches.length === 1) {
                        touchEndY = event.changedTouches[0].clientY;
                        const verticalDiff = touchEndY - touchStartY;
                        const screenHeight = window.innerHeight;
                        if (Math.abs(verticalDiff) > tapThreshold * 1.5) {
                            return;
                        }
                        const tapYPosition = touchStartY;
                        if (tapYPosition < screenHeight * screenPortionThreshold) {
                            if (typeof goToPrevEnhanced === "function") {
                                goToPrevEnhanced();
                            }
                        } else if (tapYPosition > screenHeight * (1 - screenPortionThreshold)) {
                            if (typeof goToNextEnhanced === "function") {
                                goToNextEnhanced();
                            }
                        } else {}
                    }
                };
                const handleContentClickForTap = (event) => {
                    if (event.target.closest('.fsReaderAyah, button, select, input, a')) {
                        return;
                    }
                    const settingsPanel = document.getElementById('fsReaderSettingsPanel');
                    const indexPanel = document.getElementById('fsReaderIndexPanel');
                    if ((settingsPanel && settingsPanel.style.display === 'block') ||
                        (indexPanel && indexPanel.style.display === 'block')) {
                        return;
                    }
                    const contentRect = contentArea.getBoundingClientRect();
                    const clickYRelativeToContent = event.clientY - contentRect.top;
                    const contentHeight = contentArea.clientHeight;
                    if (clickYRelativeToContent < contentHeight * screenPortionThreshold) {
                        if (typeof goToPrevEnhanced === "function") {
                            goToPrevEnhanced();
                        }
                    } else if (clickYRelativeToContent > contentHeight * (1 - screenPortionThreshold)) {
                        if (typeof goToNextEnhanced === "function") {
                            goToNextEnhanced();
                        }
                    }
                };
                contentArea?.addEventListener('touchstart', handleTouchStart, {
                    passive: true
                });
                contentArea?.addEventListener('touchend', handleTouchEnd, {
                    passive: true
                });
                contentArea?.addEventListener('click', handleContentClickForTap);
                readerOverlay.readerKeyDownHandler = handleKeyDown;
                contentArea.readerTouchStartHandler = handleTouchStart;
                contentArea.readerTouchEndHandler = handleTouchEnd;
                contentArea.readerContentClickHandler = handleContentClickForTap;
            }
        }
        let ayahTypingTargetText = "";
        let ayahTypingTargetCleanText = "";
        let ayahTypingTargetSpans = [];
        let ayahTypingCurrentIndex = 0;
        let ayahTypingErrors = 0;
        let ayahTypingCorrectStrokes = 0;
        let ayahTypingTotalStrokesAttemptedByPlayer = 0;
        let ayahTypingStartTime = 0;
        let ayahTypingTimerInterval = null;
        let ayahTypingGameActive = false;
        let ayahTypingDiacriticMode = 'ignore';
        let ayahTypingSessionHighScoreWPM = 0;
        let ayahTypingSessionHighScoreAcc = 0;
        let ayahTypingCurrentAyahData = null;

        function normalizeArabicCharForTyping(char) {
            if (!char) return "";
            let nfdNormalizedChar = char.normalize('NFD');
            let marksRemovedChar = nfdNormalizedChar.replace(/\p{M}/gu, '');
            let tatweelRemovedChar = marksRemovedChar.replace(/\u0640/g, '');
            let letterNormalizedChar = tatweelRemovedChar;
            letterNormalizedChar = letterNormalizedChar.replace(/[آأإٱ]/g, 'ا');
            letterNormalizedChar = letterNormalizedChar.replace(/[ؤو]/g, 'و');
            letterNormalizedChar = letterNormalizedChar.replace(/[ىیي]/g, 'ي');
            letterNormalizedChar = letterNormalizedChar.replace(/[ہھةۃه]/g, 'ه');
            letterNormalizedChar = letterNormalizedChar.replace(/[كک]/g, 'ك');
            letterNormalizedChar = letterNormalizedChar.replace(/[لٰل]/g, 'ل');
            letterNormalizedChar = letterNormalizedChar.replace(/[ڤ]/g, 'ف');
            letterNormalizedChar = letterNormalizedChar.replace(/[مٰم]/g, 'م');
            return letterNormalizedChar;
        }

        function injectAyahTypingGameCSS_Engine() {
            const cssId = "ayahTypingGameStylesEngine";
            if (document.getElementById(cssId)) return;
            const styles = `
                .ayah-typing-game-area { display: flex; flex-direction: column; align-items: center; width: 100%; padding: 10px; height: 100%; }
                .typing-options-bar { display: flex; justify-content: space-around; align-items: center; width: 100%; max-width: 600px; margin-bottom: 15px; padding: 8px; background-color: var(--color-bg-secondary); border-radius: var(--border-radius); }
                .typing-options-bar label { font-size: 0.9em; display:flex; align-items:center; gap: 5px;}
                .typing-options-bar select, .typing-options-bar button { font-size: 0.9em; padding: 5px 8px; }
                #ayahDisplayContainer_Engine {
                    font-family: var(--font-arabic);
                    font-size: 2.2rem; 
                    line-height: 2.5;
                    direction: rtl;
                    text-align: right;
                    padding: 15px;
                    margin-bottom: 15px;
                    border: 1px solid var(--color-border);
                    border-radius: var(--border-radius);
                    background-color: var(--color-bg-primary);
                    width: 100%;
                    max-width: 90%; 
                    min-height: 100px; 
                    overflow-wrap: break-word;
                }
                #ayahDisplayContainer_Engine span {
                    transition: background-color 0.1s, color 0.1s;
                    padding: 0; 
                    letter-spacing: normal; 
                }
                #ayahDisplayContainer_Engine .char-correct { background-color: #d4edda; color: #155724; } 
                #ayahDisplayContainer_Engine .char-incorrect { background-color: #f8d7da; color: #721c24; } 
                #ayahDisplayContainer_Engine .char-current { background-color: var(--color-highlight); box-shadow: 0 0 0 2px var(--color-accent); }
                #ayahDisplayContainer_Engine .char-pending { color: var(--color-text-secondary); opacity: 0.7; }
                #typingInputArea_Engine {
                    font-family: var(--font-arabic);
                    font-size: 2rem; 
                    direction: rtl;
                    text-align: right;
                    width: 100%;
                    max-width: 90%; 
                    padding: 10px;
                    margin-bottom: 15px;
                    border: 1px solid var(--color-accent);
                    border-radius: var(--border-radius);
                    min-height: 60px;
                    resize: none; 
                }
                #typingInputArea_Engine:focus { outline: 2px solid var(--color-accent-dark); }
                .typing-stats-container { display: flex; justify-content: space-around; width: 100%; max-width: 600px; margin-bottom: 15px; font-size: 1.1rem; }
                .typing-stats-container div { background-color: var(--color-bg-secondary); padding: 8px 12px; border-radius: var(--border-radius); text-align: center; }
                .typing-stats-container strong { color: var(--color-accent-dark); display:block; font-size: 1.3em; }
                .typing-controls-area { margin-top: 15px; }
                .typing-controls-area button { margin: 0 5px; }
                .typing-results-area { text-align: center; padding: 20px; }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }
        async function startAyahTyping_Engine() {
            activeGame = 'ayahTyping_engine';
            injectAyahTypingGameCSS_Engine();
            const mainGameModal = document.getElementById('quranGameModal');
            const gamePlayArea = document.getElementById('gamePlayArea');
            if (!gamePlayArea) {
                console.error("Ayah Typing: gamePlayArea element not found in the modal!");
                return;
            }
            if (!mainGameModal) {
                console.error("Ayah Typing: Main game modal not found!");
                return;
            }
            mainGameModal.style.display = 'none';
            gamePlayArea.innerHTML = `
                <div class="ayah-typing-game-area">
                    <div class="typing-options-bar">
                        <label for="diacriticModeSelect_Engine">Diacritics:
                            <select id="diacriticModeSelect_Engine">
                                <option value="ignore">Ignore (Type Base Letters)</option>
                                <option value="strict">Strict (Match Exactly)</option>
                            </select>
                        </label>
                        <button id="playAyahAudioBtn_Engine" title="Play Ayah Recitation" style="display:none;">🔊</button>
                    </div>
                    <div id="ayahDisplayContainer_Engine">Press "Start" to load an Ayah.</div>
                    <textarea id="typingInputArea_Engine" rows="2" placeholder="ابدأ الكتابة هنا..." disabled></textarea>
                    <div class="typing-stats-container">
                        <div>Timer: <strong id="typingTimer_Engine">0s</strong></div>
                        <div>WPM: <strong id="typingWPM_Engine">0</strong></div>
                        <div>Accuracy: <strong id="typingAccuracy_Engine">0%</strong></div>
                    </div>
                    <div class="typing-controls-area">
                        <button id="startTypingGameBtn_Engine">Start</button>
                        <button id="resetTypingGameBtn_Engine" style="display:none;">Next Ayah</button>
                    </div>
                    <div id="typingResultsArea_Engine" class="typing-results-area" style="display:none;"></div>
                </div>
            `;
            const ayahDisplay = document.getElementById('ayahDisplayContainer_Engine');
            if (isFullScreenReaderActive && fullScreenReaderSettings && fullScreenReaderSettings.fontSize) {
                ayahDisplay.style.fontSize = fullScreenReaderSettings.fontSize;
            }
            document.getElementById('startTypingGameBtn_Engine')?.addEventListener('click', loadNextAyahForTyping_Engine);
            document.getElementById('resetTypingGameBtn_Engine')?.addEventListener('click', loadNextAyahForTyping_Engine);
            document.getElementById('diacriticModeSelect_Engine')?.addEventListener('change', (e) => {
                ayahTypingDiacriticMode = e.target.value;
            });
            document.getElementById('playAyahAudioBtn_Engine')?.addEventListener('click', () => {
                if (ayahTypingCurrentAyahData && typeof playAudioForAyahEnhanced === 'function') {
                    playAudioForAyahEnhanced(ayahTypingCurrentAyahData.surah, ayahTypingCurrentAyahData.ayah);
                } else {
                    console.warn("Cannot play audio: Ayah data missing or audio player function not available.");
                }
            });
            document.getElementById('typingInputArea_Engine')?.addEventListener('input', handleTypingInput_Engine);
            ayahTypingSessionHighScoreWPM = parseInt(localStorage.getItem('ayahTypingHighScoreWPM') || '0');
            ayahTypingSessionHighScoreAcc = parseInt(localStorage.getItem('ayahTypingHighScoreAcc') || '0');
            requestAyahTypingFullscreen(gamePlayArea);
        }
        async function loadNextAyahForTyping_Engine() {
            resetTypingGameState_Engine();
            const ayahDisplay = document.getElementById('ayahDisplayContainer_Engine');
            const typingInput = document.getElementById('typingInputArea_Engine');
            ayahDisplay.innerHTML = '<p>Loading new Ayah...</p>';
            typingInput.disabled = true;
            typingInput.value = '';
            document.getElementById('startTypingGameBtn_Engine').style.display = 'none';
            document.getElementById('resetTypingGameBtn_Engine').style.display = 'inline-block';
            document.getElementById('resetTypingGameBtn_Engine').textContent = 'Loading...';
            document.getElementById('resetTypingGameBtn_Engine').disabled = true;
            document.getElementById('playAyahAudioBtn_Engine').style.display = 'none';
            document.getElementById('typingResultsArea_Engine').style.display = 'none';
            try {
                let randomAyahObj;
                let attempts = 0;
                const MIN_CHARS = 15;
                const MAX_CHARS = 180;
                do {
                    const randomSurah = Math.floor(Math.random() * 114) + 1;
                    const randomAyahNum = Math.floor(Math.random() * (surahAyahCounts[randomSurah] || 1)) + 1;
                    console.log(`[loadNextAyahForTyping_Engine] Attempt ${attempts + 1}: Trying S${randomSurah}:A${randomAyahNum}`);
                    const ayahResult = await sendAjaxRequest('load_quran_ayah', {
                        surah: randomSurah,
                        ayah: randomAyahNum
                    });
                    randomAyahObj = ayahResult.success ? ayahResult.data : null;
                    if (randomAyahObj && randomAyahObj.arabic) {
                        console.log(`[loadNextAyahForTyping_Engine] Fetched Ayah (raw): '${randomAyahObj.arabic}' (Length: ${randomAyahObj.arabic.length})`);
                    } else {
                        console.log(`[loadNextAyahForTyping_Engine] Ayah S${randomSurah}:A${randomAyahNum} not found or no Arabic text.`);
                    }
                    attempts++;
                } while ((!randomAyahObj || !randomAyahObj.arabic || randomAyahObj.arabic.trim().length < MIN_CHARS || randomAyahObj.arabic.trim().length > MAX_CHARS) && attempts < 50);
                if (!randomAyahObj || !randomAyahObj.arabic) {
                    const errorMsg = "[loadNextAyahForTyping_Engine] Could not load a suitable Ayah after 50 attempts.";
                    console.error(errorMsg);
                    ayahDisplay.innerHTML = `<p style="color:red;">${errorMsg}</p>`;
                    document.getElementById('startTypingGameBtn_Engine').style.display = 'inline-block';
                    document.getElementById('resetTypingGameBtn_Engine').style.display = 'none';
                    return;
                }
                ayahTypingCurrentAyahData = {
                    surah: randomAyahObj.surah,
                    ayah: randomAyahObj.ayah
                };
                ayahTypingTargetText = randomAyahObj.arabic.trim();
                console.log(`[loadNextAyahForTyping_Engine] FINAL ayahTypingTargetText for rendering: '${ayahTypingTargetText}' (Length: ${ayahTypingTargetText.length})`);
                ayahTypingTargetCleanText = normalizeArabicCharForTyping(ayahTypingTargetText);
                console.log(`[loadNextAyahForTyping_Engine] Normalized (Clean) Target Text: '${ayahTypingTargetCleanText}'`);
                renderAyahForTyping_Engine(ayahTypingTargetText);
                typingInput.disabled = false;
                typingInput.placeholder = "ابدأ الكتابة هنا عند الجاهزية";
                setTimeout(() => typingInput.focus(), 100);
                document.getElementById('resetTypingGameBtn_Engine').textContent = 'Next Ayah';
                document.getElementById('resetTypingGameBtn_Engine').disabled = false;
                document.getElementById('playAyahAudioBtn_Engine').style.display = 'inline-block';
            } catch (error) {
                console.error("[loadNextAyahForTyping_Engine] Error loading Ayah for typing:", error);
                ayahDisplay.innerHTML = `<p style="color:red;">Error: ${error.message}</p>`;
                document.getElementById('startTypingGameBtn_Engine').style.display = 'inline-block';
                document.getElementById('resetTypingGameBtn_Engine').style.display = 'none';
            }
        }

        function renderAyahForTyping_Engine(ayahText) {
            const ayahDisplay = document.getElementById('ayahDisplayContainer_Engine');
            ayahDisplay.innerHTML = '';
            ayahTypingTargetSpans = [];
            for (let i = 0; i < ayahText.length; i++) {
                const charSpan = document.createElement('span');
                charSpan.textContent = ayahText[i];
                charSpan.className = 'char-pending';
                ayahDisplay.appendChild(charSpan);
                ayahTypingTargetSpans.push(charSpan);
            }
            if (ayahTypingTargetSpans.length > 0) {
                ayahTypingTargetSpans[0].classList.add('char-current');
            }
        }

        function resetTypingGameState_Engine() {
            if (ayahTypingTimerInterval) clearInterval(ayahTypingTimerInterval);
            ayahTypingGameActive = false;
            ayahTypingCurrentIndex = 0;
            ayahTypingErrors = 0;
            ayahTypingCorrectStrokes = 0;
            ayahTypingTotalStrokesAttemptedByPlayer = 0;
            ayahTypingStartTime = 0;
            ayahTypingTimerInterval = null;
            ayahTypingCurrentAyahData = null;
            document.getElementById('typingTimer_Engine').textContent = "0s";
            document.getElementById('typingWPM_Engine').textContent = "0";
            document.getElementById('typingAccuracy_Engine').textContent = "0%";
            const resultsArea = document.getElementById('typingResultsArea_Engine');
            if (resultsArea) resultsArea.style.display = 'none';
            const typingInput = document.getElementById('typingInputArea_Engine');
            if (typingInput) {
                typingInput.value = '';
                typingInput.disabled = true;
            }
        }

        function handleTypingInput_Engine() {
            const typingInput = document.getElementById('typingInputArea_Engine');
            if (!typingInput) {
                return;
            }
            const userInput = typingInput.value;
            if (!ayahTypingGameActive && ayahTypingTargetSpans.length > 0 && userInput.length > 0) {
                ayahTypingGameActive = true;
                ayahTypingStartTime = Date.now();
                if (ayahTypingTimerInterval) clearInterval(ayahTypingTimerInterval);
                ayahTypingTimerInterval = setInterval(updateTypingTimer_Engine, 1000);
            }
            if (ayahTypingTargetSpans.length === 0) return;
            let currentEffectiveInputIndex = 0;
            let MismatchEncountered = false;
            for (let targetIdx = 0; targetIdx < ayahTypingTargetSpans.length; targetIdx++) {
                const span = ayahTypingTargetSpans[targetIdx];
                const targetCharOriginal = ayahTypingTargetText[targetIdx];
                span.className = '';
                if (MismatchEncountered) {
                    span.classList.add('char-pending');
                    continue;
                }
                const normalizedTargetChar = normalizeArabicCharForTyping(targetCharOriginal);
                if (ayahTypingDiacriticMode === 'ignore' && normalizedTargetChar === '') {
                    span.classList.add('char-correct');
                    if (targetIdx === userInput.length && currentEffectiveInputIndex === userInput.length) {}
                    continue;
                }
                const userTypedCharOriginal = userInput[currentEffectiveInputIndex];
                if (currentEffectiveInputIndex < userInput.length) {
                    const normalizedUserChar = normalizeArabicCharForTyping(userTypedCharOriginal);
                    if (targetIdx === currentEffectiveInputIndex && !MismatchEncountered) {
                        console.log(`[Compare Char TargetIdx #${targetIdx} / InputIdx #${currentEffectiveInputIndex}] TargetOrig: '${targetCharOriginal}', UserTypedOrig: '${userTypedCharOriginal}'`);
                        if (ayahTypingDiacriticMode === 'ignore') {
                            console.log(`    NormTarget: '${normalizedTargetChar}', NormUser: '${normalizedUserChar}'`);
                        }
                    }
                    if (normalizedUserChar === normalizedTargetChar) {
                        span.classList.add('char-correct');
                    } else {
                        if (ayahTypingDiacriticMode === 'ignore' && normalizedTargetChar.trim() === '' && normalizedUserChar.trim() === '') {
                            span.classList.add('char-correct');
                        } else {
                            span.classList.add('char-incorrect');
                            MismatchEncountered = true;
                            if (targetIdx === currentEffectiveInputIndex) {
                                console.log(`    MISMATCH. UserNorm: '${normalizedUserChar}' vs TargetNorm: '${normalizedTargetChar}'`);
                            }
                        }
                    }
                    currentEffectiveInputIndex++;
                } else {
                    if (!MismatchEncountered) {
                        span.classList.add('char-current');
                        MismatchEncountered = true;
                    } else {
                        span.classList.add('char-pending');
                    }
                }
            }
            ayahTypingCurrentIndex = currentEffectiveInputIndex;
            if (ayahTypingGameActive) {
                updateLiveTypingStats_Engine(userInput);
            }
            let baseTargetLength = 0;
            for (let char of ayahTypingTargetText) {
                if (normalizeArabicCharForTyping(char) !== '') baseTargetLength++;
            }
            if (currentEffectiveInputIndex >= baseTargetLength && !MismatchEncountered && ayahTypingGameActive) {
                endTypingRound_Engine(userInput);
            }
        }

        function updateLiveTypingStats_Engine(currentUserInput) {
            if (!ayahTypingGameActive) {
                document.getElementById('typingWPM_Engine').textContent = "0";
                document.getElementById('typingAccuracy_Engine').textContent = "0%";
                return;
            }
            let liveCorrectChars = 0;
            let liveErrors = 0;
            const typedLength = currentUserInput.length;
            const comparisonLength = Math.min(typedLength, ayahTypingTargetText.length);
            for (let i = 0; i < comparisonLength; i++) {
                const targetOriginal = ayahTypingTargetText[i];
                const userTypedOriginal = currentUserInput[i];
                if (ayahTypingDiacriticMode === 'ignore') {
                    if (normalizeArabicCharForTyping(userTypedOriginal) === normalizeArabicCharForTyping(targetOriginal)) {
                        liveCorrectChars++;
                    } else {
                        if (targetOriginal.trim() === '' && userTypedOriginal && userTypedOriginal.trim() === '') {
                            liveCorrectChars++;
                        } else {
                            liveErrors++;
                        }
                    }
                } else {
                    if (userTypedOriginal === targetOriginal) {
                        liveCorrectChars++;
                    } else {
                        liveErrors++;
                    }
                }
            }
            if (typedLength > ayahTypingTargetText.length) {
                liveErrors += (typedLength - ayahTypingTargetText.length);
            }
            ayahTypingCorrectStrokes = liveCorrectChars;
            ayahTypingErrors = liveErrors;
            ayahTypingTotalStrokesAttemptedByPlayer = typedLength;
            const currentTime = Date.now();
            const timeElapsedSeconds = (currentTime - ayahTypingStartTime) / 1000;
            let wpm = 0;
            if (timeElapsedSeconds > 0.5) {
                const wordsTypedCorrectly = liveCorrectChars / 5;
                const minutesElapsed = timeElapsedSeconds / 60;
                wpm = minutesElapsed > 0 ? Math.round(wordsTypedCorrectly / minutesElapsed) : 0;
            }
            let accuracy = 0;
            if (typedLength > 0) {
                accuracy = Math.round((liveCorrectChars / typedLength) * 100);
            }
            if (accuracy < 0) accuracy = 0;
            document.getElementById('typingWPM_Engine').textContent = wpm;
            document.getElementById('typingAccuracy_Engine').textContent = `${accuracy}%`;
        }

        function endTypingRound_Engine(finalUserInput) {
            if (!ayahTypingGameActive) return;
            ayahTypingGameActive = false;
            if (ayahTypingTimerInterval) clearInterval(ayahTypingTimerInterval);
            document.getElementById('typingInputArea_Engine').disabled = true;
            const cleanUserInput = finalUserInput.split('').map(normalizeForTypingComparison).join('');
            const typedLength = cleanUserInput.length;
            let correctChars = 0;
            for (let i = 0; i < ayahTypingTargetCleanText.length; i++) {
                if (i < typedLength && cleanUserInput[i] === ayahTypingTargetCleanText[i]) {
                    correctChars++;
                }
            }
            const errors = typedLength - correctChars;
            const finalAccuracy = typedLength > 0 ? Math.round((correctChars / typedLength) * 100) : 0;
            const timeElapsedSeconds = (Date.now() - ayahTypingStartTime) / 1000;
            const finalWPM = timeElapsedSeconds > 0 ? Math.round((correctChars / 5) / (timeElapsedSeconds / 60)) : 0;
            if (finalWPM > ayahTypingSessionHighScoreWPM) {
                ayahTypingSessionHighScoreWPM = finalWPM;
                localStorage.setItem('ayahTypingHighScoreWPM', finalWPM);
            }
            if (finalAccuracy > ayahTypingSessionHighScoreAcc) {
                ayahTypingSessionHighScoreAcc = finalAccuracy;
                localStorage.setItem('ayahTypingHighScoreAcc', finalAccuracy);
            }
            const resultsArea = document.getElementById('typingResultsArea_Engine');
            resultsArea.innerHTML = `
                <h4>Ayah Complete!</h4>
                <p>Your WPM: ${finalWPM}</p>
                <p>Accuracy: ${finalAccuracy}%</p>
                <p>Correct Characters: ${correctChars} / ${typedLength} (typed)</p>
                <p>Errors: ${errors}</p>
                <p>Time: ${timeElapsedSeconds.toFixed(1)}s</p>
                <p><em>Session High: ${ayahTypingSessionHighScoreWPM} WPM, ${ayahTypingSessionHighScoreAcc}% Acc</em></p>
            `;
            resultsArea.style.display = 'block';
            document.getElementById('resetTypingGameBtn_Engine').focus();
        }

        function normalizeForTypingComparison(char) {
            if (!char) return "";
            let nfdNormalizedChar = char.normalize('NFD');
            let marksRemovedChar = nfdNormalizedChar.replace(/\p{M}/gu, '');
            let tatweelRemovedChar = marksRemovedChar.replace(/\u0640/g, '');
            let letterNormalizedChar = tatweelRemovedChar;
            letterNormalizedChar = letterNormalizedChar.replace(/[إأآٱ]/g, 'ا');
            letterNormalizedChar = letterNormalizedChar.replace(/[ى]/g, 'ي');
            letterNormalizedChar = letterNormalizedChar.replace(/[ؤ]/g, 'و');
            letterNormalizedChar = letterNormalizedChar.replace(/[ئ]/g, 'ي');
            letterNormalizedChar = letterNormalizedChar.replace(/[ة]/g, 'ه');
            letterNormalizedChar = letterNormalizedChar.replace(/[كک]/g, 'ك');
            return letterNormalizedChar.trim();
        }

        function updateTypingTimer_Engine() {
            if (!ayahTypingGameActive) return;
            const currentTime = Date.now();
            const timeElapsed = Math.round((currentTime - ayahTypingStartTime) / 1000);
            document.getElementById('typingTimer_Engine').textContent = `${timeElapsed}s`;
        }

        function addAyahTypingGameButtonToModal() {
            const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
            const buttonId = 'startGameAyahTypingEngineBtn';
            if (gameSelectionArea && !document.getElementById(buttonId)) {
                const typingGameButton = document.createElement('button');
                typingGameButton.id = buttonId;
                typingGameButton.className = 'game-select-btn';
                typingGameButton.textContent = 'Ayah Typing Challenge';
                typingGameButton?.addEventListener('click', () => {
                    const gamePlayArea = document.getElementById('gamePlayArea');
                    const gameSelectionArea = document.getElementById('game-selection-area');
                    if (gameSelectionArea) gameSelectionArea.style.display = 'none';
                    if (gamePlayArea) gamePlayArea.style.display = 'flex';
                    setTimeout(() => {
                        startAyahTyping_Engine();
                    }, 50);
                });
                const existingButtons = gameSelectionArea.querySelectorAll('.game-select-btn');
                if (existingButtons.length > 0) {
                    existingButtons[existingButtons.length - 1].insertAdjacentElement('afterend', typingGameButton);
                } else {
                    const pElement = gameSelectionArea.querySelector('p');
                    if (pElement) {
                        pElement.insertAdjacentElement('afterend', typingGameButton);
                    } else {
                        gameSelectionArea.appendChild(typingGameButton);
                    }
                }
            }
        }
        let ayahTypingGameOriginalParent = null;
        let ayahTypingGameCloseButtonFS = null;

        function requestAyahTypingFullscreen(gamePlayAreaElement) {
            if (!gamePlayAreaElement) {
                console.error("requestAyahTypingFullscreen: gamePlayAreaElement is null.");
                return;
            }
            const fullscreenContainer = document.createElement('div');
            fullscreenContainer.id = 'ayahTypingFullscreenContainer';
            fullscreenContainer.style.position = 'fixed';
            fullscreenContainer.style.top = '0';
            fullscreenContainer.style.left = '0';
            fullscreenContainer.style.width = '100vw';
            fullscreenContainer.style.height = '100vh';
            fullscreenContainer.style.backgroundColor = 'var(--color-bg-primary, #e8f5e9)';
            fullscreenContainer.style.zIndex = '20000';
            fullscreenContainer.style.display = 'flex';
            fullscreenContainer.style.flexDirection = 'column';
            fullscreenContainer.style.justifyContent = 'center';
            fullscreenContainer.style.alignItems = 'center';
            fullscreenContainer.style.padding = '20px';
            fullscreenContainer.style.boxSizing = 'border-box';
            ayahTypingGameOriginalParent = gamePlayAreaElement.parentNode;
            fullscreenContainer.appendChild(gamePlayAreaElement);
            gamePlayAreaElement.style.width = '100%';
            gamePlayAreaElement.style.height = '100%';
            gamePlayAreaElement.style.maxWidth = '1200px';
            gamePlayAreaElement.style.maxHeight = '95vh';
            gamePlayAreaElement.style.overflow = 'auto';
            gamePlayAreaElement.style.backgroundColor = 'var(--color-bg-secondary, #c8e6c9)';
            gamePlayAreaElement.style.borderRadius = 'var(--border-radius, 8px)';
            gamePlayAreaElement.style.boxShadow = '0 0 20px rgba(0,0,0,0.2)';
            ayahTypingGameCloseButtonFS = document.createElement('button');
            ayahTypingGameCloseButtonFS.id = 'ayahTypingCloseFullscreenBtn';
            ayahTypingGameCloseButtonFS.textContent = '✕';
            ayahTypingGameCloseButtonFS.style.position = 'absolute';
            ayahTypingGameCloseButtonFS.style.top = '20px';
            ayahTypingGameCloseButtonFS.style.right = '20px';
            ayahTypingGameCloseButtonFS.style.fontSize = '1.8rem';
            ayahTypingGameCloseButtonFS.style.padding = '5px 12px';
            ayahTypingGameCloseButtonFS.style.backgroundColor = 'var(--color-error, #ef5350)';
            ayahTypingGameCloseButtonFS.style.color = 'white';
            ayahTypingGameCloseButtonFS.style.border = 'none';
            ayahTypingGameCloseButtonFS.style.borderRadius = '50%';
            ayahTypingGameCloseButtonFS.style.cursor = 'pointer';
            ayahTypingGameCloseButtonFS.style.zIndex = '20001';
            ayahTypingGameCloseButtonFS.title = "Exit Fullscreen Typing (Esc)";
            ayahTypingGameCloseButtonFS.onclick = exitAyahTypingFullscreen;
            fullscreenContainer.appendChild(ayahTypingGameCloseButtonFS);
            document.body.appendChild(fullscreenContainer);
            document.body.style.overflow = 'hidden';
            if (fullscreenContainer.requestFullscreen) {
                fullscreenContainer.requestFullscreen().catch(err => {
                    console.warn(`Error attempting to enable full-screen mode: ${err.message} (${err.name})`);
                });
            } else if (fullscreenContainer.webkitRequestFullscreen) {
                fullscreenContainer.webkitRequestFullscreen();
            } else if (fullscreenContainer.msRequestFullscreen) {
                fullscreenContainer.msRequestFullscreen();
            }
            document?.addEventListener('fullscreenchange', handleBrowserFullscreenChange);
            document?.addEventListener('webkitfullscreenchange', handleBrowserFullscreenChange);
            document?.addEventListener('mozfullscreenchange', handleBrowserFullscreenChange);
            document?.addEventListener('MSFullscreenChange', handleBrowserFullscreenChange);
        }

        function exitAyahTypingFullscreen() {
            const gamePlayAreaElement = document.getElementById('gamePlayArea');
            const fullscreenContainer = document.getElementById('ayahTypingFullscreenContainer');
            if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement) {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
            cleanupAyahTypingCustomFullscreenOverlay(gamePlayAreaElement, fullscreenContainer);
        }

        function cleanupAyahTypingCustomFullscreenOverlay(gamePlayAreaElement, fullscreenContainer) {
            if (fullscreenContainer && fullscreenContainer.parentNode) {
                if (gamePlayAreaElement && ayahTypingGameOriginalParent) {
                    gamePlayAreaElement.style.width = '';
                    gamePlayAreaElement.style.height = '';
                    gamePlayAreaElement.style.maxWidth = '';
                    gamePlayAreaElement.style.maxHeight = '';
                    gamePlayAreaElement.style.overflow = '';
                    gamePlayAreaElement.style.backgroundColor = '';
                    gamePlayAreaElement.style.borderRadius = '';
                    gamePlayAreaElement.style.boxShadow = '';
                    ayahTypingGameOriginalParent.appendChild(gamePlayAreaElement);
                }
                fullscreenContainer.parentNode.removeChild(fullscreenContainer);
            }
            if (ayahTypingGameCloseButtonFS && ayahTypingGameCloseButtonFS.parentNode) {
                ayahTypingGameCloseButtonFS.parentNode.removeChild(ayahTypingGameCloseButtonFS);
                ayahTypingGameCloseButtonFS = null;
            }
            document.body.style.overflow = '';
            document.removeEventListener('fullscreenchange', handleBrowserFullscreenChange);
            document.removeEventListener('webkitfullscreenchange', handleBrowserFullscreenChange);
            document.removeEventListener('mozfullscreenchange', handleBrowserFullscreenChange);
            document.removeEventListener('MSFullscreenChange', handleBrowserFullscreenChange);
            activeGame = null;
            resetGameUI();
            const mainGameModal = document.getElementById('quranGameModal');
            if (mainGameModal && mainGameModal.style.display === 'none') {
                mainGameModal.style.display = 'flex';
            }
        }

        function handleBrowserFullscreenChange() {
            const fullscreenContainer = document.getElementById('ayahTypingFullscreenContainer');
            if (!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement)) {
                if (fullscreenContainer) {
                    console.log("Browser fullscreen exited, cleaning up custom overlay.");
                    cleanupAyahTypingCustomFullscreenOverlay(
                        document.getElementById('gamePlayArea'),
                        fullscreenContainer
                    );
                }
            }
        }

        function enterSimpleImmersiveView() {
            const gameModalTitleEl = document.getElementById('gameModalTitle');
            if (gameModalTitleEl) {
                originalTitleDisplay_SimpleImmersive = gameModalTitleEl.style.display;
                gameModalTitleEl.style.display = 'none';
            } else {
                console.warn("[SimpleImmersive] gameModalTitleEl not found for hiding.");
            }
            isSimpleImmersiveActive = true;
        }

        function exitSimpleImmersiveView() {
            if (!isSimpleImmersiveActive) return;
            const gameModalTitleEl = document.getElementById('gameModalTitle');
            if (gameModalTitleEl) {
                gameModalTitleEl.style.display = originalTitleDisplay_SimpleImmersive;
            }
            isSimpleImmersiveActive = false;
        }
        let recitationGame_UI = {
            surahSelect: null,
            ayahStartSelect: null,
            ayahEndSelect: null,
            loadAyahButton: null,
            ayahDisplayArea: null,
            referencePlayButton: null,
            recordButton: null,
            analysisFeedbackArea: null,
            recordingStatusText: null,
            currentScoreDisplay: null,
            bestScoreDisplay: null
        };
        let recitationGame_State = {
            currentSurah: 1,
            currentAyahStart: 1,
            currentAyahEnd: 1,
            targetAyahText: "",
            targetAyahWords: [],
            referenceAudio: null,
            referenceAudioDuration: 0,
            referenceHighlightInterval: null,
            userAudioRecorder: null,
            userAudioChunks: [],
            isRecording: false,
            speechRecognition: null,
            currentReferenceHighlightIndex: 0,
            gameActive: false,
            ayahKey: ""
        };
        const STORE_RECITATION_GAME_PROGRESS = 'recitationGameProgress_v1';

        function injectRecitationPracticeGameCSS_Engine() {
            const cssId = "recitationPracticeGameStylesEngine";
            if (document.getElementById(cssId)) return;
            const styles = `
                .recitation-practice-game-area { display: flex; flex-direction: column; align-items: center; width: 100%; padding: 10px; height:100%; box-sizing: border-box; }
                .recitation-controls-bar { display: flex; flex-wrap: wrap; justify-content: space-around; align-items: center; width: 100%; max-width: 700px; margin-bottom: 15px; padding: 10px; background-color: var(--color-bg-secondary); border-radius: var(--border-radius); gap: 10px; }
                .recitation-controls-bar label { font-size: 0.9em; margin-right: 5px;}
                .recitation-controls-bar select, .recitation-controls-bar button { font-size: 0.9em; padding: 6px 10px; }
                #recitationAyahDisplay_Engine {
                    font-family: var(--font-arabic); 
                    font-size: 2.4rem; 
                    line-height: 2.8; 
                    direction: rtl;
                    text-align: right;
                    padding: 20px;
                    margin-bottom: 20px;
                    border: 1px solid var(--color-border);
                    border-radius: var(--border-radius);
                    background-color: var(--color-bg-primary);
                    width: 100%;
                    max-width: 95%;
                    min-height: 150px;
                    overflow-y: auto; 
                    cursor: default; 
                }
                #recitationAyahDisplay_Engine .highlight-word {
                    background-color: var(--color-highlight, yellow); 
                    border-radius: 3px;
                    padding: 0 0.1em; 
                    transition: background-color 0.15s ease-in-out;
                }
                #recitationAyahDisplay_Engine .diff-correct { color: var(--color-success, green); font-weight: bold; }
                #recitationAyahDisplay_Engine .diff-incorrect { color: var(--color-error, red); text-decoration: line-through; }
                #recitationAyahDisplay_Engine .diff-missing { color: var(--color-text-secondary, gray); font-style: italic; }
                .recitation-action-buttons { display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
                .recitation-action-buttons button { padding: 10px 18px; font-size: 1.1rem; }
                #recordUserAyah_Engine.is-recording { background-color: var(--color-error); }
                #recordingStatus_Engine { font-size: 1em; color: var(--color-text-secondary); min-height: 1.5em; margin-bottom: 10px; text-align: center;}
                #analysisFeedbackArea_Engine { width: 100%; max-width: 95%; padding: 15px; border: 1px dashed var(--color-border); border-radius: var(--border-radius); background-color: var(--color-bg-secondary); min-height: 80px; text-align: center; margin-top:15px; }
                #analysisFeedbackArea_Engine h4 { margin-top: 0; color: var(--color-text-primary); }
                #analysisFeedbackArea_Engine p { margin-bottom: 8px; }
                .recitation-game-scores { text-align: center; margin-top: 15px; font-size: 1rem; }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }
        async function startRecitationPracticeGame_Engine() {
            activeGame = 'recitationPractice_engine';
            injectRecitationPracticeGameCSS_Engine();
            recitationGame_State.gameActive = true;
            const gamePlayArea = document.getElementById('gamePlayArea');
            if (!gamePlayArea) {
                const mainModal = document.getElementById('quranGameModal');
                if (mainModal) mainModal.innerHTML = "<p style='color:red; text-align:center; padding:20px;'>Error: Game area component missing. Cannot start recitation practice.</p>";
                return;
            }
            showGamePlayUI("Recitation Practice");
            const gameHTML = `
                <div class="recitation-practice-game-area">
                    <div class="recitation-controls-bar">
                        <div>
                            <label for="recitationSurahSelect_Engine">Surah:</label>
                            <select id="recitationSurahSelect_Engine"></select>
                        </div>
                        <div>
                            <label for="recitationAyahStartSelect_Engine">From Ayah:</label>
                            <select id="recitationAyahStartSelect_Engine"></select>
                        </div>
                        <div>
                            <label for="recitationAyahEndSelect_Engine">To Ayah (Optional):</label>
                            <select id="recitationAyahEndSelect_Engine"><option value="">Single Ayah</option></select>
                        </div>
                        <button id="loadRecitationAyah_Engine">Load Ayah(s)</button>
                    </div>
                    <div id="recitationAyahDisplay_Engine">Select Surah and Ayah, then click "Load Ayah(s)".</div>
                    <div class="recitation-action-buttons">
                        <button id="playReferenceAyah_Engine" disabled>▶️ Play Reference</button>
                        <button id="recordUserAyah_Engine" disabled>🎤 Record My Recitation</button>
                    </div>
                    <div id="recordingStatus_Engine"></div>
                    <div id="analysisFeedbackArea_Engine" style="display:none;">
                        <h4>Analysis Feedback</h4>
                        <p id="feedbackTextAccuracy_Engine">Accuracy: -</p>
                        <p id="feedbackTiming_Engine">Timing: -</p>
                        <p id="feedbackRecognizedText_Engine" style="font-family: var(--font-arabic); direction:rtl;"></p>
                    </div>
                    <div class="recitation-game-scores">
                        Current Ayah Accuracy: <strong id="currentRecitationScore_Engine">-</strong>%<br>
                        Best Accuracy for this Ayah: <strong id="bestRecitationScore_Engine">-</strong>%
                    </div>
                </div>
            `;
            gamePlayArea.innerHTML = gameHTML;
            if (!document.getElementById('feedbackTextAccuracy_Engine')) {} else {}
            if (!document.getElementById('feedbackRecognizedText_Engine')) {}
            if (!document.getElementById('feedbackTiming_Engine')) {}
            recitationGame_UI.surahSelect = document.getElementById('recitationSurahSelect_Engine');
            recitationGame_UI.ayahStartSelect = document.getElementById('recitationAyahStartSelect_Engine');
            recitationGame_UI.ayahEndSelect = document.getElementById('recitationAyahEndSelect_Engine');
            recitationGame_UI.loadAyahButton = document.getElementById('loadRecitationAyah_Engine');
            recitationGame_UI.ayahDisplayArea = document.getElementById('recitationAyahDisplay_Engine');
            recitationGame_UI.referencePlayButton = document.getElementById('playReferenceAyah_Engine');
            recitationGame_UI.recordButton = document.getElementById('recordUserAyah_Engine');
            recitationGame_UI.analysisFeedbackArea = document.getElementById('analysisFeedbackArea_Engine');
            recitationGame_UI.recordingStatusText = document.getElementById('recordingStatus_Engine');
            recitationGame_UI.currentScoreDisplay = document.getElementById('currentRecitationScore_Engine');
            recitationGame_UI.bestScoreDisplay = document.getElementById('bestRecitationScore_Engine');
            if (recitationGame_UI.surahSelect) {
                for (let i = 1; i <= 114; i++) {
                    const option = document.createElement('option');
                    option.value = i;
                    option.textContent = `${i}. ${surahNames[i - 1]}`;
                    recitationGame_UI.surahSelect.appendChild(option);
                }
            } else {}
            if (recitationGame_UI.surahSelect) recitationGame_UI.surahSelect?.addEventListener('change', updateRecitationAyahSelectors_Engine);
            if (recitationGame_UI.ayahStartSelect) recitationGame_UI.ayahStartSelect?.addEventListener('change', () => {
                const startAyah = parseInt(recitationGame_UI.ayahStartSelect.value);
                const endAyahSelect = recitationGame_UI.ayahEndSelect;
                if (endAyahSelect) {
                    const currentEndValue = parseInt(endAyahSelect.value);
                    if (currentEndValue && currentEndValue < startAyah) {
                        endAyahSelect.value = "";
                    }
                    populateRecitationAyahEndSelect_Engine(parseInt(recitationGame_UI.surahSelect.value), startAyah);
                }
            });
            if (recitationGame_UI.loadAyahButton) recitationGame_UI.loadAyahButton?.addEventListener('click', loadAyahForRecitationPractice_Engine);
            if (recitationGame_UI.referencePlayButton) recitationGame_UI.referencePlayButton?.addEventListener('click', playReferenceAudioWithHighlighting_Engine);
            if (recitationGame_UI.recordButton) recitationGame_UI.recordButton?.addEventListener('click', toggleUserRecording_Recitation_Engine);
            updateRecitationAyahSelectors_Engine();
            setupSpeechRecognition_Engine();
        }

        function updateRecitationAyahSelectors_Engine() {
            const surahNum = parseInt(recitationGame_UI.surahSelect.value);
            const totalAyahs = surahAyahCounts[surahNum] || 0;
            recitationGame_UI.ayahStartSelect.innerHTML = '';
            for (let i = 1; i <= totalAyahs; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                recitationGame_UI.ayahStartSelect.appendChild(option);
            }
            populateRecitationAyahEndSelect_Engine(surahNum, 1);
        }

        function populateRecitationAyahEndSelect_Engine(surahNum, startAyahNum) {
            const totalAyahs = surahAyahCounts[surahNum] || 0;
            const endSelect = recitationGame_UI.ayahEndSelect;
            const currentEndValue = endSelect.value;
            endSelect.innerHTML = '<option value="">Single Ayah Mode</option>';
            for (let i = startAyahNum; i <= totalAyahs; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                endSelect.appendChild(option);
            }
            if (currentEndValue && parseInt(currentEndValue) >= startAyahNum && parseInt(currentEndValue) <= totalAyahs) {
                endSelect.value = currentEndValue;
            } else {
                endSelect.value = "";
            }
        }
        async function loadAyahForRecitationPractice_Engine() {
            recitationGame_State.currentSurah = parseInt(recitationGame_UI.surahSelect.value);
            recitationGame_State.currentAyahStart = parseInt(recitationGame_UI.ayahStartSelect.value);
            const endAyahValue = recitationGame_UI.ayahEndSelect.value;
            recitationGame_State.currentAyahEnd = endAyahValue ? parseInt(endAyahValue) : recitationGame_State.currentAyahStart;
            if (recitationGame_State.currentAyahEnd < recitationGame_State.currentAyahStart) {
                alert("End Ayah cannot be before Start Ayah.");
                recitationGame_State.currentAyahEnd = recitationGame_State.currentAyahStart;
                recitationGame_UI.ayahEndSelect.value = "";
            }
            recitationGame_State.targetAyahText = "";
            recitationGame_State.targetAyahWords = [];
            recitationGame_UI.ayahDisplayArea.innerHTML = "Loading...";
            recitationGame_UI.analysisFeedbackArea.style.display = 'none';
            recitationGame_UI.currentScoreDisplay.textContent = '-';
            recitationGame_State.ayahKey = `s${recitationGame_State.currentSurah}a${recitationGame_State.currentAyahStart}`;
            if (recitationGame_State.currentAyahStart !== recitationGame_State.currentAyahEnd) {
                recitationGame_State.ayahKey += `-a${recitationGame_State.currentAyahEnd}`;
            }
            for (let i = recitationGame_State.currentAyahStart; i <= recitationGame_State.currentAyahEnd; i++) {
                const ayahResult = await sendAjaxRequest('load_quran_ayah', {
                    surah: recitationGame_State.currentSurah,
                    ayah: i
                });
                const ayahData = ayahResult.success ? ayahResult.data : null;
                if (ayahData && ayahData.arabic) {
                    const ayahTextClean = ayahData.arabic.trim();
                    recitationGame_State.targetAyahText += ayahTextClean + (i < recitationGame_State.currentAyahEnd ? " " : "");
                    recitationGame_State.targetAyahWords.push(...ayahTextClean.split(/\s+/).filter(w => w.length > 0));
                }
            }
            if (recitationGame_State.targetAyahText) {
                renderLoadedAyahForRecitation_Engine();
                recitationGame_UI.referencePlayButton.disabled = false;
                recitationGame_UI.recordButton.disabled = false;
            } else {
                recitationGame_UI.ayahDisplayArea.innerHTML = "Error loading Ayah text.";
                recitationGame_UI.referencePlayButton.disabled = true;
                recitationGame_UI.recordButton.disabled = true;
            }
            loadBestScore_Recitation_Engine();
        }

        function renderLoadedAyahForRecitation_Engine() {
            recitationGame_UI.ayahDisplayArea.innerHTML = '';
            recitationGame_State.targetAyahWords.forEach(word => {
                const span = document.createElement('span');
                span.textContent = word + " ";
                recitationGame_UI.ayahDisplayArea.appendChild(span);
            });
        }
        async function populateThemesIndexList() {
            const themeListEl = document.getElementById('fsIndexThemeList');
            if (!themeListEl) return;
            themeListEl.innerHTML = '';

            // Add static themes
            if (typeof staticQuranicThemes !== 'undefined' && Array.isArray(staticQuranicThemes)) {
                staticQuranicThemes.forEach(theme => {
                    const li = document.createElement('li');
                    const a = document.createElement('a');
                    a.href = '#';
                    a.dataset.themeId = theme.id;
                    a.dataset.isStatic = 'true';
                    a.dataset.exampleSurah = theme.exampleSurah;
                    a.dataset.exampleAyah = theme.exampleAyah;
                    a.textContent = theme.name;
                    a?.addEventListener('click', handleIndexThemeClick);
                    li.appendChild(a);
                    themeListEl.appendChild(li);
                });
            }

            // Add user-defined themes if logged in
            if (isUserLoggedIn) {
                try {
                    const result = await sendAjaxRequest('get_all_themes');
                    const userThemes = result.success ? result.data : [];
                    if (userThemes.length > 0) {
                        const divider = document.createElement('li');
                        divider.innerHTML = `<hr style="margin: 5px 0; border-color: var(--color-border);">`;
                        themeListEl.appendChild(divider);
                    }
                    userThemes.forEach(theme => {
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.href = '#';
                        a.dataset.themeId = theme.id;
                        a.dataset.isStatic = 'false';
                        a.textContent = `[My Theme] ${theme.name}`;
                        a?.addEventListener('click', handleIndexThemeClick);
                        li.appendChild(a);
                        themeListEl.appendChild(li);
                    });
                } catch (error) {
                    console.error("Failed to load user themes for index:", error);
                }
            }
        }
        async function playReferenceAudioWithHighlighting_Engine() {
            if (recitationGame_State.isRecording) {
                alert("Please stop recording first.");
                return;
            }
            stopReferenceAudio_Engine();
            recitationGame_UI.referencePlayButton.textContent = "🔄 Loading...";
            recitationGame_UI.referencePlayButton.disabled = true;
            recitationGame_UI.recordButton.disabled = true;
            let combinedAudioBufferSources = [];
            let totalDuration = 0;
            try {
                for (let i = recitationGame_State.currentAyahStart; i <= recitationGame_State.currentAyahEnd; i++) {
                    const surahPadded = String(recitationGame_State.currentSurah).padStart(3, '0');
                    const ayahPadded = String(i).padStart(3, '0');
                    const audioSrcUrl = `https://everyayah.com/data/Alafasy_128kbps/${surahPadded}${ayahPadded}.mp3`;
                    const response = await fetch(audioSrcUrl);
                    const arrayBuffer = await response.arrayBuffer();
                    const tempAudioCtx = new(window.AudioContext || window.webkitAudioContext)();
                    const decodedBuffer = await tempAudioCtx.decodeAudioData(arrayBuffer);
                    combinedAudioBufferSources.push({
                        src: audioSrcUrl,
                        duration: decodedBuffer.duration
                    });
                    totalDuration += decodedBuffer.duration;
                    await tempAudioCtx.close();
                }
            } catch (error) {
                console.error("Error fetching reference audio segment durations:", error);
                recitationGame_UI.ayahDisplayArea.innerHTML = "Error loading reference audio data.";
                recitationGame_UI.referencePlayButton.textContent = "▶️ Play Reference";
                recitationGame_UI.referencePlayButton.disabled = false;
                recitationGame_UI.recordButton.disabled = false;
                return;
            }
            recitationGame_State.referenceAudioDuration = totalDuration;
            recitationGame_State.currentReferenceHighlightIndex = 0;
            recitationGame_State.referenceAudio = new Audio();
            let currentSegmentIndex = 0;
            const playNextSegment = () => {
                if (currentSegmentIndex >= combinedAudioBufferSources.length) {
                    stopReferenceAudio_Engine();
                    return;
                }
                recitationGame_State.referenceAudio.src = combinedAudioBufferSources[currentSegmentIndex].src;
                recitationGame_State.referenceAudio.play().catch(e => {
                    console.error("Error playing segment:", e);
                    stopReferenceAudio_Engine();
                });
                currentSegmentIndex++;
            };
            recitationGame_State.referenceAudio.onended = playNextSegment;
            recitationGame_State.referenceAudio.onerror = () => {
                console.error("Error with reference audio playback.");
                stopReferenceAudio_Engine();
            };
            playNextSegment();
            const wordsToHighlight = recitationGame_UI.ayahDisplayArea.querySelectorAll('span');
            if (wordsToHighlight.length > 0 && totalDuration > 0) {
                const timePerWord = totalDuration / wordsToHighlight.length;
                let highlightIdx = 0;
                wordsToHighlight.forEach(s => s.classList.remove('highlight-word'));
                recitationGame_State.referenceHighlightInterval = setInterval(() => {
                    if (highlightIdx < wordsToHighlight.length) {
                        if (highlightIdx > 0) wordsToHighlight[highlightIdx - 1].classList.remove('highlight-word');
                        wordsToHighlight[highlightIdx].classList.add('highlight-word');
                        highlightIdx++;
                    } else {
                        clearInterval(recitationGame_State.referenceHighlightInterval);
                        if (wordsToHighlight.length > 0) wordsToHighlight[wordsToHighlight.length - 1].classList.remove('highlight-word');
                    }
                }, timePerWord * 1000);
            }
            recitationGame_UI.referencePlayButton.textContent = "⏹️ Stop Reference";
            recitationGame_UI.referencePlayButton.disabled = false;
        }

        function stopReferenceAudio_Engine() {
            if (recitationGame_State.referenceAudio) {
                recitationGame_State.referenceAudio.pause();
                recitationGame_State.referenceAudio.src = "";
                recitationGame_State.referenceAudio.onended = null;
                recitationGame_State.referenceAudio.onerror = null;
                recitationGame_State.referenceAudio = null;
            }
            if (recitationGame_State.referenceHighlightInterval) {
                clearInterval(recitationGame_State.referenceHighlightInterval);
                recitationGame_State.referenceHighlightInterval = null;
            }
            recitationGame_UI.ayahDisplayArea.querySelectorAll('span.highlight-word').forEach(s => s.classList.remove('highlight-word'));
            recitationGame_UI.referencePlayButton.textContent = "▶️ Play Reference";
            if (recitationGame_State.targetAyahText) {
                recitationGame_UI.referencePlayButton.disabled = false;
                recitationGame_UI.recordButton.disabled = false;
            }
        }
        async function toggleUserRecording_Recitation_Engine() {
            if (recitationGame_State.isRecording) {
                stopUserRecording_Recitation_Engine();
            } else {
                if (recitationGame_State.referenceAudio && !recitationGame_State.referenceAudio.paused) {
                    stopReferenceAudio_Engine();
                }
                await startUserRecording_Recitation_Engine();
            }
        }

        function setupSpeechRecognition_Engine() {
            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                alert("Desktop speech recognition is not supported in this browser. Please try Google Chrome or Microsoft Edge.");
                if (recitationGame_UI.recordButton) recitationGame_UI.recordButton.disabled = true;
                return;
            }
            recitationGame_State.speechRecognition = new SpeechRecognition();
            const recognition = recitationGame_State.speechRecognition;
            recognition.lang = 'ar-SA';
            recognition.continuous = true;
            recognition.interimResults = true;
            recognition.onstart = () => {
                recitationGame_State.userTranscript = '';
                if (recitationGame_UI.recordingStatusText) recitationGame_UI.recordingStatusText.textContent = "Listening...";
            };
            recognition.onresult = (event) => {
                let final_transcript = '';
                for (let i = 0; i < event.results.length; ++i) {
                    final_transcript += event.results[i][0].transcript;
                }
                recitationGame_State.userTranscript = final_transcript.trim();
            };
            recognition.onerror = (event) => {
                console.error("Speech Recognition Error:", event.error);
                if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
                    alert("Microphone access was denied. Please allow microphone access in your browser settings to use this feature.");
                } else {
                    alert(`An error occurred during speech recognition: ${event.error}`);
                }
                if (recitationGame_State.isRecording) stopUserRecording_Recitation_Engine();
            };
            recognition.onend = () => {
                recitationGame_State.speechResultReceived = true;
                tryFinalizeAnalysis_Engine();
            };
        }
        async function startUserRecording_Recitation_Engine() {
            recitationGame_State.mediaRecordingStopped = false;
            recitationGame_State.speechResultReceived = false;
            recitationGame_State.analysisTriggeredThisRound = false;
            recitationGame_State.userTranscript = "";
            recitationGame_State.isRecording = true;
            recitationGame_UI.recordButton.textContent = "🛑 Stop Recording";
            recitationGame_UI.recordButton.classList.add('is-recording');
            recitationGame_UI.referencePlayButton.disabled = true;
            recitationGame_UI.analysisFeedbackArea.style.display = 'none';
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: true
                });
                recitationGame_State.userAudioRecorder = new MediaRecorder(stream, {
                    mimeType: 'audio/webm'
                });
                recitationGame_State.userAudioRecorder.onstop = () => {
                    recitationGame_State.mediaRecordingStopped = true;
                    stream.getTracks().forEach(track => track.stop());
                    tryFinalizeAnalysis_Engine();
                };
                recitationGame_State.userAudioRecorder.start();
                if (recitationGame_State.speechRecognition) {
                    recitationGame_State.speechRecognition.start();
                }
            } catch (err) {
                console.error("Error starting native recording:", err);
                alert("Could not start recording. Please grant microphone permission and try again.");
                recitationGame_State.isRecording = false;
                recitationGame_UI.recordButton.textContent = "🎤 Record My Recitation";
                recitationGame_UI.recordButton.classList.remove('is-recording');
                recitationGame_UI.referencePlayButton.disabled = false;
            }
        }

        function stopUserRecording_Recitation_Engine() {
            if (!recitationGame_State.isRecording) return;
            recitationGame_State.isRecording = false;
            recitationGame_UI.recordButton.textContent = "⏳ Processing...";
            recitationGame_UI.recordButton.disabled = true;
            if (recitationGame_State.userAudioRecorder && recitationGame_State.userAudioRecorder.state === "recording") {
                recitationGame_State.userAudioRecorder.stop();
            }
            if (recitationGame_State.speechRecognition) {
                setTimeout(() => {
                    recitationGame_State.speechRecognition.stop();
                }, 300);
            }
        }

        function tryFinalizeAnalysis_Engine() {
            if (recitationGame_State.analysisTriggeredThisRound) return;
            if (recitationGame_State.mediaRecordingStopped && recitationGame_State.speechResultReceived) {
                recitationGame_State.analysisTriggeredThisRound = true;
                const audioBlob = new Blob(recitationGame_State.userAudioChunks, {
                    type: 'audio/webm'
                });
                analyzeUserRecitation_Engine(audioBlob);
                recitationGame_UI.recordButton.disabled = false;
                recitationGame_UI.recordButton.textContent = "🎤 Record My Recitation";
                recitationGame_UI.recordButton.classList.remove('is-recording');
                recitationGame_UI.referencePlayButton.disabled = false;
            }
        }
        async function analyzeUserRecitation_Engine(audioBlob) {
            if (!recitationGame_State.gameActive) {
                return;
            }
            if (recitationGame_UI.recordingStatusText) {
                recitationGame_UI.recordingStatusText.textContent = "Analyzing...";
            }
            const feedbackArea = recitationGame_UI.analysisFeedbackArea;
            if (feedbackArea) {
                feedbackArea.style.display = 'block';
                feedbackArea.innerHTML = `
                    <h4>Analysis Feedback</h4>
                    <p id="feedbackTextAccuracy_Engine">Accuracy: Calculating...</p>
                    <div style="text-align:right; direction:rtl; margin-top:10px; padding-top:10px; border-top: 1px dotted var(--color-border);">
                        <strong>الآية المستهدفة (Target):</strong>
                        <p style="font-family: var(--font-arabic); font-size: 1.2em;">${recitationGame_State.targetAyahText}</p>
                    </div>
                    <div style="text-align:right; direction:rtl; margin-top:10px; padding-top:10px; border-top: 1px dotted var(--color-border);">
                        <strong>ما تم التعرف عليه (Recognized):</strong>
                        <p id="feedbackRecognizedText_Engine" style="font-family: var(--font-arabic); font-size: 1.2em; color: var(--color-accent-dark);">(...)</p>
                    </div>
                    <div style="text-align:right; direction:rtl; margin-top:10px; padding-top:10px; border-top: 1px dotted var(--color-border);">
                        <strong>المقارنة البصرية (Visual Diff):</strong>
                        <p id="feedbackDiff_Engine" style="font-family: var(--font-arabic); font-size: 1.2em;"></p>
                    </div>
                `;
            }
            const recognizedText = recitationGame_State.userTranscript || "";
            const targetTextNorm = normalizeArabicForComparison_Engine(recitationGame_State.targetAyahText);
            const recognizedTextNorm = normalizeArabicForComparison_Engine(recognizedText);
            const {
                accuracy,
                diffHTML
            } = calculateTextAccuracyAndDiff_Engine(targetTextNorm, recognizedTextNorm);
            console.log(`[RecitationGame] Calculated Accuracy: ${accuracy}%, Diff HTML generated.`);
            const feedbackAccuracyEl = document.getElementById('feedbackTextAccuracy_Engine');
            const feedbackRecognizedEl = document.getElementById('feedbackRecognizedText_Engine');
            const feedbackDiffEl = document.getElementById('feedbackDiff_Engine');
            if (feedbackAccuracyEl) feedbackAccuracyEl.textContent = `Text Accuracy: ${accuracy.toFixed(1)}%`;
            if (feedbackRecognizedEl) feedbackRecognizedEl.textContent = recognizedText || "(No speech recognized)";
            if (feedbackDiffEl) feedbackDiffEl.innerHTML = diffHTML;
            if (recitationGame_UI.currentScoreDisplay) {
                recitationGame_UI.currentScoreDisplay.textContent = `${accuracy.toFixed(0)}`;
            }
            if (recitationGame_UI.recordingStatusText) {
                recitationGame_UI.recordingStatusText.textContent = "Analysis complete.";
            }
            saveScore_Recitation_Engine(accuracy);
        }

        function normalizeArabicForComparison_Engine(text) {
            if (!text) return "";
            let str = text;
            str = str.normalize('NFD').replace(/[\u064B-\u065F\u0670\u08D4-\u08E1\u08E3-\u08FF]/g, '');
            str = str.replace(/\u0640/g, '');
            str = str.replace(/[إأآٱ]/g, 'ا');
            str = str.replace(/[ؤ]/g, 'و');
            str = str.replace(/[ئ]/g, 'ي');
            str = str.replace(/[ى]/g, 'ي');
            str = str.replace(/[ة]/g, 'ه');
            str = str.replace(/[كک]/g, 'ك');
            str = str.replace(/[.,!?:;"'()\[\]{}0-9٠-٩]/g, '');
            text = text.trim().replace(/\s+/g, ' ');
            return str;
        }

        function levenshteinDistance_Engine(s1, s2) {
            s1 = s1.toLowerCase();
            s2 = s2.toLowerCase();
            const costs = [];
            for (let i = 0; i <= s1.length; i++) {
                let lastValue = i;
                for (let j = 0; j <= s2.length; j++) {
                    if (i === 0) costs[j] = j;
                    else if (j > 0) {
                        let newValue = costs[j - 1];
                        if (s1[i - 1] !== s2[j - 1]) newValue = Math.min(Math.min(newValue, lastValue), costs[j]) + 1;
                        costs[j - 1] = lastValue;
                        lastValue = newValue;
                    }
                }
                if (i > 0) costs[s2.length] = lastValue;
            }
            return costs[s2.length];
        }

        function calculateTextAccuracyAndDiff_Engine(targetNormalized, recognizedNormalized) {
            if (!targetNormalized) return {
                accuracy: 0,
                diffHTML: "<p>(Target text was empty)</p>"
            };
            const dmp = new diff_match_patch();
            const diff = dmp.diff_main(recognizedNormalized, targetNormalized);
            dmp.diff_cleanupSemantic(diff);
            let diffHTML = "";
            let correctChars = 0;
            diff.forEach(part => {
                const operation = part[0];
                const text = part[1];
                switch (operation) {
                    case 0:
                        diffHTML += `<span class="diff-correct">${text}</span>`;
                        correctChars += text.length;
                        break;
                    case 1:
                        diffHTML += `<span class="diff-missing" title="Missing: ${text}">${text}</span>`;
                        break;
                    case -1:
                        diffHTML += `<span class="diff-incorrect" title="Incorrect/Extra: ${text}">${text}</span>`;
                        break;
                }
            });
            const targetLength = targetNormalized.length;
            const accuracy = targetLength > 0 ? (correctChars / targetLength) * 100 : 0;
            return {
                accuracy: Math.max(0, Math.min(100, accuracy)),
                diffHTML: diffHTML || "(No recognized text to compare)"
            };
        }
        async function saveScore_Recitation_Engine(accuracy) {
            if (!isUserLoggedIn || !recitationGame_State.ayahKey) {
                console.warn("saveScore_Recitation_Engine: User not logged in or Ayah key is not set. Cannot save score.");
                return;
            }
            try {
                const key = `recitationPractice_${recitationGame_State.ayahKey}_bestAccuracy`;
                const existingBestAccuracyText = localStorage.getItem(key);
                const existingBestAccuracy = existingBestAccuracyText ? parseFloat(existingBestAccuracyText) : 0;
                const newBestAccuracy = Math.max(existingBestAccuracy, accuracy);
                localStorage.setItem(key, newBestAccuracy.toString());
                localStorage.setItem(`recitationPractice_${recitationGame_State.ayahKey}_lastAttemptDate`, new Date().toISOString());
                localStorage.setItem(`recitationPractice_${recitationGame_State.ayahKey}_lastScore`, accuracy.toString());
                if (recitationGame_UI.bestScoreDisplay) {
                    recitationGame_UI.bestScoreDisplay.textContent = `${newBestAccuracy.toFixed(0)}`;
                } else {
                    console.warn("saveScore_Recitation_Engine: bestScoreDisplay UI element not found.");
                }
            } catch (error) {
                console.error("Error saving recitation game score to localStorage:", error);
            }
        }
        async function loadBestScore_Recitation_Engine() {
            if (recitationGame_UI.bestScoreDisplay) {
                recitationGame_UI.bestScoreDisplay.textContent = '-';
            } else {
                console.warn("loadBestScore_Recitation_Engine: bestScoreDisplay UI element not found, cannot set default.");
            }
            if (!recitationGame_State.ayahKey) {
                console.warn("loadBestScore_Recitation_Engine: Ayah key is not set. Cannot load score.");
                return;
            }
            try {
                const key = `recitationPractice_${recitationGame_State.ayahKey}_bestAccuracy`;
                const bestAccuracyText = localStorage.getItem(key);
                const bestAccuracy = bestAccuracyText ? parseFloat(bestAccuracyText) : 0;
                if (recitationGame_UI.bestScoreDisplay) {
                    recitationGame_UI.bestScoreDisplay.textContent = `${bestAccuracy.toFixed(0)}`;
                }
            } catch (error) {
                console.error("Error loading best recitation game score from localStorage:", error);
            }
        }

        function addRecitationPracticeGameButtonToModal() {
            const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
            const buttonId = 'startRecitationPracticeGameBtn';
            if (gameSelectionArea && !document.getElementById(buttonId)) {
                const recitationGameButton = document.createElement('button');
                recitationGameButton.id = buttonId;
                recitationGameButton.className = 'game-select-btn';
                recitationGameButton.textContent = 'Recitation Practice';
                recitationGameButton?.addEventListener('click', startRecitationPracticeGame_Engine);
                const existingButtons = gameSelectionArea.querySelectorAll('.game-select-btn');
                if (existingButtons.length > 0) {
                    existingButtons[existingButtons.length - 1].insertAdjacentElement('afterend', recitationGameButton);
                } else {
                    const pElement = gameSelectionArea.querySelector('p');
                    if (pElement) pElement.insertAdjacentElement('afterend', recitationGameButton);
                    else gameSelectionArea.appendChild(recitationGameButton);
                }
            }
        }
        if (document.getElementById('quranGameModal')) {
            addRecitationPracticeGameButtonToModal();
        } else {
            const observer = new MutationObserver((mutationsList, obs) => {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList') {
                        const modal = document.getElementById('quranGameModal');
                        if (modal) {
                            addRecitationPracticeGameButtonToModal();
                            obs.disconnect();
                            return;
                        }
                    }
                }
            });
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
        const mainGameModalForRecitationCleanup = document.getElementById('quranGameModal');
        if (mainGameModalForRecitationCleanup) {
            const gameModalCloseBtn = mainGameModalForRecitationCleanup.querySelector('.game-close-button');
            if (gameModalCloseBtn) {
                gameModalCloseBtn?.addEventListener('click', () => {
                    if (recitationGame_State.gameActive) {
                        stopReferenceAudio_Engine();
                        if (recitationGame_State.isRecording && recitationGame_State.userAudioRecorder) {
                            recitationGame_State.userAudioRecorder.stop();
                        }
                        if (recitationGame_State.speechRecognition) recitationGame_State.speechRecognition.abort();
                        recitationGame_State.gameActive = false;
                        recitationGame_State.isRecording = false;
                    }
                });
            }
        }
        (function() {
            const GAME_MODAL_LAUNCH_BUTTON_ID = 'openGamesModalBtn';
            const TARGET_ELEMENT_FOR_FULLSCREEN_ID = 'quranGameModal';
            const INITIALIZATION_DELAY_MS = 600;

            function requestFullscreenForElement(elementId) {
                const targetElement = document.getElementById(elementId);
                if (!targetElement) {
                    console.error(`Fullscreen Targeter: Element with ID "${elementId}" not found. Cannot go fullscreen.`);
                    return;
                }
                if (targetElement.style.display === 'none' || getComputedStyle(targetElement).display === 'none') {}
                if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement) {
                    return;
                }
                if (targetElement.requestFullscreen) {
                    targetElement.requestFullscreen().catch(err => console.warn("Fullscreen Targeter: Fullscreen request failed:", err.message, err.name));
                } else if (targetElement.webkitRequestFullscreen) {
                    targetElement.webkitRequestFullscreen().catch(err => console.warn("Fullscreen Targeter: Fullscreen request failed (webkit):", err.message, err.name));
                } else if (targetElement.mozRequestFullScreen) {
                    targetElement.mozRequestFullScreen();
                } else if (targetElement.msRequestFullscreen) {
                    targetElement.msRequestFullscreen();
                } else {}
            }

            function setupLaunchButtonListenerForFullscreen() {
                const launchButton = document.getElementById(GAME_MODAL_LAUNCH_BUTTON_ID);
                if (launchButton) {
                    launchButton?.addEventListener('click', (event) => {
                        setTimeout(() => {
                            requestFullscreenForElement(TARGET_ELEMENT_FOR_FULLSCREEN_ID);
                        }, 200);
                    });
                } else {}
            }
            window?.addEventListener('load', () => {
                setTimeout(() => {
                    setupLaunchButtonListenerForFullscreen();
                }, INITIALIZATION_DELAY_MS);
            });

            function logFullscreenExit(event) {
                if (!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement)) {}
            }
            document?.addEventListener('fullscreenchange', logFullscreenExit);
            document?.addEventListener('webkitfullscreenchange', logFullscreenExit);
            document?.addEventListener('mozfullscreenchange', logFullscreenExit);
            document?.addEventListener('MSFullscreenChange', logFullscreenExit);
        })();
        (function() {
            const READER_LAUNCH_BUTTON_ID = 'launchFullScreenReaderBtnEnhanced';
            const READER_OVERLAY_ID = 'fullScreenReaderOverlay';
            const INITIALIZATION_DELAY_MS = 500;
            const FULLSCREEN_REQUEST_DELAY_MS = 200;

            function requestFullscreenForReaderOverlay(elementId) {
                const targetElement = document.getElementById(elementId);
                if (!targetElement) {
                    console.error(`Reader FS Overlay: Element with ID "${elementId}" not found. Cannot go fullscreen.`);
                    return;
                }
                if (targetElement.style.display === 'none' || getComputedStyle(targetElement).display === 'none') {
                    console.warn(`Reader FS Overlay: Target overlay "${elementId}" is not visible. Fullscreen might not work as expected. Ensure launchFullScreenQuranReaderEnhanced() makes it visible.`);
                }
                if (document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement) {
                    console.log("Reader FS Overlay: Already in browser fullscreen mode with some element. Will not re-request for overlay.");
                    return;
                }
                if (targetElement.requestFullscreen) {
                    targetElement.requestFullscreen().catch(err => console.warn("Reader FS Overlay: Fullscreen request failed:", err.message, err.name));
                } else if (targetElement.webkitRequestFullscreen) {
                    targetElement.webkitRequestFullscreen().catch(err => console.warn("Reader FS Overlay: Fullscreen request failed (webkit):", err.message, err.name));
                } else if (targetElement.mozRequestFullScreen) {
                    targetElement.mozRequestFullScreen();
                } else if (targetElement.msRequestFullscreen) {
                    targetElement.msRequestFullscreen();
                } else {
                    console.warn(`Reader FS Overlay: Browser Fullscreen API not supported for the element "${elementId}".`);
                }
            }

            function setupReaderFullscreenLaunchListener() {
                const launchButton = document.getElementById(READER_LAUNCH_BUTTON_ID);
                if (launchButton) {
                    launchButton?.addEventListener('click', () => {
                        setTimeout(() => {
                            requestFullscreenForReaderOverlay(READER_OVERLAY_ID);
                        }, FULLSCREEN_REQUEST_DELAY_MS);
                    });
                } else {}
            }
            window?.addEventListener('load', () => {
                setTimeout(() => {
                    setupReaderFullscreenLaunchListener();
                }, INITIALIZATION_DELAY_MS);
            });

            function logReaderFullscreenExit(event) {
                const readerOverlayElement = document.getElementById(READER_OVERLAY_ID);
                if (!(document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement)) {}
            }
            document?.addEventListener('fullscreenchange', logReaderFullscreenExit);
            document?.addEventListener('webkitfullscreenchange', logReaderFullscreenExit);
            document?.addEventListener('mozfullscreenchange', logReaderFullscreenExit);
            document?.addEventListener('MSFullscreenChange', logReaderFullscreenExit);
        })();
        let currentReportingUserData = null;

        function injectReportingModuleStyles_Enhanced() {
            const cssId = "reportingModuleStylesEnhanced";
            if (document.getElementById(cssId)) return;
            const styles = `
                .reporting-dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; padding: 15px; background-color: var(--color-bg-secondary); border-radius: var(--border-radius); }
                .dashboard-card { background-color: var(--color-bg-primary); padding: 15px; border-radius: var(--border-radius); box-shadow: 0 1px 3px var(--color-shadow); text-align: center; }
                .dashboard-card h4 { margin-top: 0; margin-bottom: 8px; color: var(--color-text-secondary); font-size: 1.1em; }
                .dashboard-card .stat-value { font-size: 1.8em; font-weight: bold; color: var(--color-accent-dark); display: block; }
                .reporting-filters-container { margin-bottom: 20px; padding: 15px; background-color: var(--color-bg-secondary); border-radius: var(--border-radius); }
                .reporting-filters-container h3 { margin-top:0; margin-bottom: 10px; }
                .reporting-filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; align-items: end; }
                .reporting-filters-grid label { font-size: 0.9em; margin-bottom: 3px; }
                .reporting-filters-grid select, .reporting-filters-grid input[type="date"], .reporting-filters-grid input[type="text"] { width: 100%; max-width: none; }
                .reporting-quick-date-filters { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; margin-bottom: 10px; }
                .reporting-quick-date-filters button { padding: 6px 10px; font-size: 0.85em; background-color: var(--color-accent-dark); flex-grow:1; min-width: 80px;}
                .reporting-quick-date-filters button:hover { background-color: var(--color-accent); }
                .reporting-content-area { margin-top: 20px; }
                .report-section { margin-bottom: 25px; padding: 15px; background-color: var(--color-bg-primary); border: 1px solid var(--color-border); border-radius: var(--border-radius); }
                .report-section h4 { margin-top: 0; margin-bottom: 10px; color: var(--color-text-secondary); border-bottom: 1px solid var(--color-border); padding-bottom: 5px; }
                .report-list { list-style: none; padding: 0; }
                .report-list li { padding: 8px 0; border-bottom: 1px dotted var(--color-border); font-size: 0.95em; }
                .report-list li:last-child { border-bottom: none; }
                .report-list .item-ref { font-weight: bold; color: var(--color-accent-dark); }
                .report-list .item-date { font-size: 0.9em; color: var(--color-text-secondary); }
                .report-list .item-notes { display: block; font-style: italic; color: var(--color-text-secondary); margin-top: 3px; padding-left: 10px; font-size: 0.9em; }
                .report-list .item-surah-ayah { cursor: pointer; text-decoration: underline; }
                .report-list .item-surah-ayah:hover { color: var(--color-accent); }
                .simple-bar-chart-container { padding:10px; border: 1px solid var(--color-border); border-radius: var(--border-radius); background-color: var(--color-bg-secondary); }
                .bar-chart-title { text-align:center; font-weight:bold; margin-bottom:10px; color:var(--color-text-secondary); }
                .bar-chart { display: flex; align-items: flex-end; height: 200px; border-bottom: 1px solid var(--color-text-secondary); padding-bottom: 5px; gap: 2px; }
                .bar-chart .bar { flex-grow: 1; background-color: var(--color-accent); text-align: center; position: relative; min-width: 20px; border-radius: 3px 3px 0 0; }
                .bar-chart .bar:hover { background-color: var(--color-accent-dark); }
                .bar-chart .bar .bar-label { position: absolute; bottom: -20px; left: 50%; transform: translateX(-50%); font-size: 0.7em; color: var(--color-text-primary); white-space: nowrap; }
                .bar-chart .bar .bar-value { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 0.75em; font-weight: bold; color: var(--color-text-primary); }
                #noReportDataMessage { text-align: center; padding: 20px; font-style: italic; }
                body.body-reporting-fullscreen { overflow: hidden !important; } 
                body.body-reporting-fullscreen > header,
                body.body-reporting-fullscreen > .container > .sidebar,
                body.body-reporting-fullscreen > .container > .main-content > .section:not(#reporting) {
                    display: none !important;
                }
                body.body-reporting-fullscreen > .container {
                    padding: 0 !important;
                    margin: 0 !important;
                    max-width: 100% !important;
                    height: 100vh;
                    display: flex; 
                    flex-direction: column;
                }
                body.body-reporting-fullscreen > .container > .main-content {
                    padding: 0 !important; margin: 0 !important; height: 100%;
                    flex-grow: 1; 
                    display: flex; 
                    flex-direction: column;
                }
                #reporting.reporting-fullscreen-active {
                    width: 100%;
                    height: 100%;
                    overflow-y: auto;
                    background-color: var(--color-bg-primary);
                    padding: var(--padding-main); 
                    box-sizing: border-box;
                    display: flex; 
                    flex-direction: column;
                    flex-grow: 1;
                }
                #exitReportFullscreenBtn {
                    position: absolute; 
                    top: 10px; 
                    right: 10px; 
                    z-index: 1001; 
                    padding: 8px 12px;
                    background-color: var(--color-error);
                    color: white;
                    border-radius: var(--border-radius);
                    border:none;
                    cursor:pointer;
                }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }

        function createReportingSectionStructure_Enhanced() {
            const mainContent = document.querySelector('.main-content');
            if (!mainContent || document.getElementById('reporting')) return;
            const reportingSection = document.createElement('section');
            reportingSection.id = 'reporting';
            reportingSection.className = 'section';
            reportingSection.setAttribute('role', 'region');
            reportingSection.setAttribute('aria-labelledby', 'reporting-heading');
            let surahOptions = surahNames.map((name, i) => `<option value="${i + 1}">${i + 1}. ${name}</option>`).join('');
            reportingSection.innerHTML = `
                <h2 id="reporting-heading">My Contributions & Progress</h2>
                <div id="reportingDashboard" class="reporting-dashboard">
                    <p>Loading dashboard...</p>
                </div>
                <div class="reporting-filters-container">
                    <h3>Filter Reports</h3>
                    <div class="reporting-quick-date-filters">
                        <button data-period="today">Today</button>
                        <button data-period="this_week">This Week</button>
                        <button data-period="this_month">This Month</button>
                        <button data-period="this_year">This Year</button>
                        <button data-period="all_time">All Time</button>
                    </div>
                    <div class="reporting-filters-grid">
                        <div>
                            <label for="reportTypeFilter">Report Type:</label>
                            <select id="reportTypeFilter">
                                <option value="all">All My Contributions</option>
                                <option value="tafsir">Personal Tafsir</option>
                                <option value="hifz">Memorization (Hifz)</option>
                                <option value="themes">Thematic Links</option>
                                <option value="roots">Root Word Notes</option>
                                <option value="recitations">Recitation Logs</option>
                            </select>
                        </div>
                        <div>
                            <label for="reportSurahFilter">Surah:</label>
                            <select id="reportSurahFilter">
                                <option value="">All Surahs</option>
                                ${surahOptions}
                            </select>
                        </div>
                        <div>
                            <label for="reportDateFromFilter">Date From:</label>
                            <input type="date" id="reportDateFromFilter">
                        </div>
                        <div>
                            <label for="reportDateToFilter">Date To:</label>
                            <input type="date" id="reportDateToFilter">
                        </div>
                        <div>
                            <label for="reportKeywordSearch">Keyword Search (Notes):</label>
                            <input type="text" id="reportKeywordSearch" placeholder="Search in notes...">
                        </div>
                        <div>
                            <button id="applyReportFiltersBtn" style="width:100%;">Apply Filters</button>
                        </div>
                    </div>
                </div>
                <div id="reportingContent" class="reporting-content-area">
                    <p>Select filters and apply to view reports.</p>
                </div>
            `;
            mainContent.appendChild(reportingSection);
            const surahFilterSelect = document.getElementById('reportSurahFilter');
        }

        function enterReportingFullscreen() {
            document.body.classList.add('body-reporting-fullscreen');
            const reportingSection = document.getElementById('reporting');
            if (reportingSection) {
                reportingSection.classList.add('reporting-fullscreen-active');
                addExitReportFullscreenButton_Enhanced();
                reportingSection.scrollTop = 0;
            }
        }

        function exitReportingFullscreen() {
            document.body.classList.remove('body-reporting-fullscreen');
            const reportingSection = document.getElementById('reporting');
            if (reportingSection) {
                reportingSection.classList.remove('reporting-fullscreen-active');
            }
            removeExitReportFullscreenButton_Enhanced();
        }

        function addExitReportFullscreenButton_Enhanced() {
            removeExitReportFullscreenButton_Enhanced();
            const reportingSection = document.getElementById('reporting');
            if (!reportingSection) return;
            const btn = document.createElement('button');
            btn.id = 'exitReportFullscreenBtn';
            btn.textContent = 'Exit Fullscreen Report';
            btn.onclick = () => {
                exitReportingFullscreen();
                if (typeof window.showSection_Patched === 'function') window.showSection_Patched('quran');
                else if (typeof window.showSection === 'function') window.showSection('quran');
            };
            reportingSection.insertBefore(btn, reportingSection.firstChild);
        }

        function removeExitReportFullscreenButton_Enhanced() {
            const btn = document.getElementById('exitReportFullscreenBtn');
            if (btn && btn.parentNode) {
                btn.parentNode.removeChild(btn);
            }
        }

        function getISODateString(date) {
            return date.getFullYear() + '-' +
                ('0' + (date.getMonth() + 1)).slice(-2) + '-' +
                ('0' + date.getDate()).slice(-2);
        }

        function getTodayDateRange_Enhanced() {
            const today = new Date();
            return {
                start: getISODateString(today),
                end: getISODateString(today)
            };
        }

        function getThisWeekDateRange_Enhanced() {
            const today = new Date();
            const dayOfWeek = today.getDay();
            const startDate = new Date(today);
            startDate.setDate(today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1));
            const endDate = new Date(startDate);
            endDate.setDate(startDate.getDate() + 6);
            return {
                start: getISODateString(startDate),
                end: getISODateString(endDate)
            };
        }

        function getThisMonthDateRange_Enhanced() {
            const today = new Date();
            const y = today.getFullYear();
            const m = today.getMonth();
            const startDate = new Date(y, m, 1);
            const endDate = new Date(y, m + 1, 0);
            return {
                start: getISODateString(startDate),
                end: getISODateString(endDate)
            };
        }

        function getThisYearDateRange_Enhanced() {
            const today = new Date();
            const y = today.getFullYear();
            const startDate = new Date(y, 0, 1);
            const endDate = new Date(y, 11, 31);
            return {
                start: getISODateString(startDate),
                end: getISODateString(endDate)
            };
        }

        function setupReportingFiltersEventListeners_Enhanced(userData) {
            const applyBtn = document.getElementById('applyReportFiltersBtn');
            const dateFromInput = document.getElementById('reportDateFromFilter');
            const dateToInput = document.getElementById('reportDateToFilter');
            if (applyBtn) {
                applyBtn.onclick = () => {
                    const filters = {
                        reportType: document.getElementById('reportTypeFilter').value,
                        surah: document.getElementById('reportSurahFilter').value,
                        dateFrom: dateFromInput.value,
                        dateTo: dateToInput.value,
                        keyword: document.getElementById('reportKeywordSearch').value,
                    };
                    renderReportDetails(userData, filters);
                };
            }
            document.querySelectorAll('.reporting-quick-date-filters button').forEach(button => {
                button?.addEventListener('click', () => {
                    const period = button.dataset.period;
                    let range;
                    switch (period) {
                        case 'today':
                            range = getTodayDateRange_Enhanced();
                            break;
                        case 'this_week':
                            range = getThisWeekDateRange_Enhanced();
                            break;
                        case 'this_month':
                            range = getThisMonthDateRange_Enhanced();
                            break;
                        case 'this_year':
                            range = getThisYearDateRange_Enhanced();
                            break;
                        case 'all_time':
                            dateFromInput.value = '';
                            dateToInput.value = '';
                            if (applyBtn) applyBtn.click();
                            return;
                    }
                    if (range) {
                        dateFromInput.value = range.start;
                        dateToInput.value = range.end;
                    }
                    if (applyBtn) applyBtn.click();
                });
            });
        }
        async function loadAllUserDataForReports() {
            if (!isUserLoggedIn) {
                console.error("User not logged in, cannot load report data.");
                currentReportingUserData = null;
                return null;
            }
            try {
                const [
                    tafsirResult, themesResult, themeAyahsResult, rootsResult,
                    recitationsResult, hifzResult, quranAyahsResult, goalsResult
                ] = await Promise.all([
                    sendAjaxRequest('get_all_tafsir'),
                    sendAjaxRequest('get_all_themes'),
                    sendAjaxRequest('get_all_theme_ayahs'),
                    sendAjaxRequest('get_all_roots'),
                    sendAjaxRequest('get_all_recitations'),
                    sendAjaxRequest('get_all_hifz'),
                    sendAjaxRequest('get_all_quran_ayahs'),
                    sendAjaxRequest('get_all_goals')
                ]);
                const quranMap = new Map();
                if (quranAyahsResult.success && quranAyahsResult.data) {
                    quranAyahsResult.data.forEach(item => quranMap.set(`${item.surah}-${item.ayah}`, item.arabic));
                }
                currentReportingUserData = {
                    tafsir: tafsirResult.success ? tafsirResult.data : [],
                    themes: themesResult.success ? themesResult.data : [],
                    themeAyahs: themeAyahsResult.success ? themeAyahsResult.data : [],
                    roots: rootsResult.success ? rootsResult.data : [],
                    recitations: recitationsResult.success ? recitationsResult.data : [],
                    hifz: hifzResult.success ? hifzResult.data : [],
                    goals: goalsResult.success ? goalsResult.data : [],
                    quranMap: quranMap
                };
                return currentReportingUserData;
            } catch (error) {
                console.error("Error fetching all user data for reports:", error);
                currentReportingUserData = null;
                return null;
            }
        }

        function renderReportingDashboard(userData) {
            const dashboardArea = document.getElementById('reportingDashboard');
            if (!dashboardArea || !userData) {
                if (dashboardArea) dashboardArea.innerHTML = "<p>Error loading dashboard data.</p>";
                return;
            }
            const {
                tafsir,
                themes,
                roots,
                recitations,
                hifz
            } = userData;
            const totalTafsir = tafsir ? tafsir.length : 0;
            const totalThemes = themes ? themes.length : 0;
            const totalRootNotes = roots ? roots.filter(r => r.description && r.description.trim() !== '').length : 0;
            const totalRecitations = recitations ? recitations.length : 0;
            const totalHifzAyahs = hifz ? hifz.filter(h => h.status === 'memorized').length : 0;
            const totalHifzInProgress = hifz ? hifz.filter(h => h.status === 'in-progress').length : 0;
            dashboardArea.innerHTML = `
                <div class="dashboard-card"><h4>Personal Tafsir</h4><span class="stat-value">${totalTafsir}</span><span>Ayahs with Notes</span></div>
                <div class="dashboard-card"><h4>Memorized Ayahs</h4><span class="stat-value">${totalHifzAyahs}</span><span>Status: Memorized</span></div>
                <div class="dashboard-card"><h4>Themes Created</h4><span class="stat-value">${totalThemes}</span><span>Custom Themes</span></div>
                <div class="dashboard-card"><h4>Recitation Logs</h4><span class="stat-value">${totalRecitations}</span><span>Listening Sessions</span></div>
                <div class="dashboard-card"><h4>Root Word Notes</h4><span class="stat-value">${totalRootNotes}</span><span>Roots with Descriptions</span></div>
                <div class="dashboard-card"><h4>Hifz In Progress</h4><span class="stat-value">${totalHifzInProgress}</span><span>Ayahs In Progress</span></div>`;
        }

        function renderReportDetails(userData, filters) {
            const contentArea = document.getElementById('reportingContent');
            if (!contentArea || !userData) {
                if (contentArea) contentArea.innerHTML = "<p>Error loading report details.</p>";
                return;
            }
            contentArea.innerHTML = '';
            const reportType = filters.reportType || 'all';
            const filterSurah = filters.surah ? parseInt(filters.surah) : null;
            const dateFrom = filters.dateFrom ? new Date(filters.dateFrom) : null;
            const dateTo = filters.dateTo ? new Date(filters.dateTo) : null;
            if (dateTo) dateTo.setHours(23, 59, 59, 999);
            const keyword = filters.keyword ? filters.keyword.toLowerCase() : null;
            let dataFound = false;
            const createSection = (title, items) => {
                if (items.length === 0) return '';
                dataFound = true;
                let listHTML = '<ul class="report-list">' + items.join('') + '</ul>';
                return `<div class="report-section"><h4>${title}</h4>${listHTML}</div>`;
            };
            const makeAyahClickable = (s, a, txt) => `<span class="item-surah-ayah" data-surah="${s}" data-ayah="${a}" title="Go to S${s}:A${a}">${txt}</span>`;
            if (reportType === 'all' || reportType === 'tafsir') {
                const items = (userData.tafsir || []).filter(t =>
                    (!filterSurah || t.surah === filterSurah) &&
                    (!keyword || (t.notes && t.notes.toLowerCase().includes(keyword)))
                ).map(t => `<li>${makeAyahClickable(t.surah, t.ayah, `S ${t.surah}:${t.ayah}`)}<span class="item-notes">${(t.notes || '').substring(0, 150)}...</span></li>`);
                contentArea.innerHTML += createSection('Personal Tafsir Notes', items);
            }
            if (reportType === 'all' || reportType === 'hifz') {
                const items = (userData.hifz || []).filter(h =>
                    (!filterSurah || h.surah === filterSurah) &&
                    (!keyword || (h.notes && h.notes.toLowerCase().includes(keyword)))
                ).map(h => {
                    let d = `Status: <span class="hifz-ayah-status status-${h.status}">${h.status.replace('-', ' ')}</span>`;
                    if (h.next_review_date) d += ` | Next Review: ${h.next_review_date}`;
                    if (h.notes) d += `<span class="item-notes">${h.notes.substring(0, 100)}...</span>`;
                    return `<li>${makeAyahClickable(h.surah, h.ayah, `S ${h.surah}:${h.ayah}`)} - ${d}</li>`;
                });
                contentArea.innerHTML += createSection('Memorization (Hifz) Progress', items);
                if (items.length > 0 && (reportType === 'all' || reportType === 'hifz')) {
                    const chartData = Object.entries((userData.hifz || []).filter(h => h.status === 'memorized').reduce((acc, h) => {
                            acc[h.surah] = (acc[h.surah] || 0) + 1;
                            return acc;
                        }, {}))
                        .map(([s, c]) => ({
                            label: `S${s}`,
                            count: c,
                            fullLabel: `${surahNames[parseInt(s) - 1] || 'S' + s}: ${c} Ayahs`
                        }))
                        .sort((a, b) => parseInt(a.label.substring(1)) - parseInt(b.label.substring(1)));
                    if (chartData.length > 0) {
                        const id = 'hifzProgressChart';
                        contentArea.innerHTML += `<div class="report-section"><h4>Hifz Progress (Memorized)</h4><div id="${id}"></div></div>`;
                        setTimeout(() => createSimpleBarChart(chartData, id, "Ayahs Memorized per Surah", 'count', 'label', 'var(--color-success)'), 0);
                        dataFound = true;
                    }
                }
            }
            if (reportType === 'all' || reportType === 'themes') {
                const themeNotes = (userData.themes || []).filter(th => !keyword || (th.name.toLowerCase().includes(keyword) || (th.description && th.description.toLowerCase().includes(keyword))))
                    .map(th => `<li>Theme: <span class="item-ref">${th.name}</span><span class="item-notes">${th.description ? th.description.substring(0, 150) + '...' : 'No description.'}</span></li>`);
                contentArea.innerHTML += createSection('Theme Notes & Descriptions', themeNotes);
                const themeLinks = (userData.themeAyahs || []).filter(ta => {
                    const theme = userData.themes.find(t => t.id === ta.theme_id);
                    return (!filterSurah || ta.surah === filterSurah) && (!keyword || (ta.notes && ta.notes.toLowerCase().includes(keyword)) || (theme && theme.name.toLowerCase().includes(keyword)));
                }).map(ta => {
                    const theme = userData.themes.find(t => t.id === ta.theme_id);
                    return `<li>${makeAyahClickable(ta.surah, ta.ayah, `S ${ta.surah}:${ta.ayah}`)} linked to <span class="item-ref">${theme ? theme.name : 'Unknown'}</span>${ta.notes ? `<span class="item-notes">Note: ${ta.notes.substring(0, 100)}...</span>` : ''}</li>`;
                });
                contentArea.innerHTML += createSection('Ayahs Linked to Themes', themeLinks);
            }
            if (reportType === 'all' || reportType === 'roots') {
                const items = (userData.roots || []).filter(r => !keyword || (r.root.toLowerCase().includes(keyword) || (r.description && r.description.toLowerCase().includes(keyword))))
                    .map(r => `<li>Root: <span class="item-ref" lang="ar" dir="rtl">${r.root}</span><span class="item-notes">${r.description ? r.description.substring(0, 150) + '...' : 'No notes.'}</span></li>`);
                contentArea.innerHTML += createSection('Root Word Notes', items);
            }
            if (reportType === 'all' || reportType === 'recitations') {
                const filteredRecitationItems = (userData.recitations || [])
                    .filter(r => {
                        const recDate = r.log_date ? new Date(r.log_date) : null;
                        if (!recDate) return false;
                        if (filterSurah && r.surah !== filterSurah) return false;
                        if (dateFrom && recDate < dateFrom) return false;
                        if (dateTo && recDate > dateTo) return false;
                        if (keyword && ((!r.qari || !r.qari.toLowerCase().includes(keyword)) && (!r.notes || !r.notes.toLowerCase().includes(keyword)))) return false;
                        return true;
                    });
                const recitationListItems = filteredRecitationItems
                    .sort((a, b) => new Date(b.log_date) - new Date(a.log_date))
                    .map(r => {
                        let ayahCount = 0;
                        if (r.ayah_start && r.ayah_end) {
                            ayahCount = (r.ayah_end - r.ayah_start) + 1;
                        } else if (r.ayah_start) {
                            ayahCount = 1;
                        } else {
                            ayahCount = surahAyahCounts[r.surah] || 0;
                        }
                        const range = r.ayah_start && r.ayah_end ? `Ayahs ${r.ayah_start}-${r.ayah_end}` : r.ayah_start ? `Ayah ${r.ayah_start}` : 'Full Surah';
                        return `<li>Surah ${r.surah} (${surahNames[r.surah - 1]}) - ${range} (${ayahCount} Ayah${ayahCount !== 1 ? 's' : ''})
                                <span class="item-date"> - ${r.qari || 'N/A'} on ${r.log_date || 'N/A'}</span>
                                ${r.notes ? `<span class="item-notes">${r.notes.substring(0, 150)}...</span>` : ''}</li>`;
                    });
                contentArea.innerHTML += createSection('Recitation Logs', recitationListItems);
                if (filteredRecitationItems.length > 0) {
                    const ayahsRecitedByMonth = {};
                    const ayahsRecitedByDay = {};
                    filteredRecitationItems.forEach(r => {
                        if (r.log_date) {
                            let countOfAyahsInLog = 0;
                            if (r.ayah_start && r.ayah_end) {
                                countOfAyahsInLog = (parseInt(r.ayah_end) - parseInt(r.ayah_start)) + 1;
                            } else if (r.ayah_start) {
                                countOfAyahsInLog = 1;
                            } else {
                                countOfAyahsInLog = surahAyahCounts[r.surah] || 0;
                            }
                            if (countOfAyahsInLog <= 0) return;
                            const monthYear = new Date(r.log_date).toLocaleDateString(navigator.language || 'en-US', {
                                year: 'numeric',
                                month: 'short'
                            });
                            ayahsRecitedByMonth[monthYear] = (ayahsRecitedByMonth[monthYear] || 0) + countOfAyahsInLog;
                            const dayKey = getISODateString(new Date(r.log_date));
                            ayahsRecitedByDay[dayKey] = (ayahsRecitedByDay[dayKey] || 0) + countOfAyahsInLog;
                        }
                    });
                    const monthlyChartData = Object.entries(ayahsRecitedByMonth)
                        .map(([month, count]) => ({
                            label: month,
                            count: count,
                            fullLabel: `${month}: ${count} Ayahs Recited`
                        }))
                        .sort((a, b) => new Date(a.label) - new Date(b.label));
                    if (monthlyChartData.length > 0) {
                        const monthlyChartContainerId = 'recitationLogsMonthlyChart';
                        let sectionEl = contentArea.querySelector('#recitationLogsMonthlyChartSection');
                        if (!sectionEl) {
                            sectionEl = document.createElement('div');
                            sectionEl.className = 'report-section';
                            sectionEl.id = 'recitationLogsMonthlyChartSection';
                            contentArea.appendChild(sectionEl);
                        }
                        sectionEl.innerHTML = `<h4>Ayahs Recited (Monthly)</h4><div id="${monthlyChartContainerId}"></div>`;
                        setTimeout(() => createSimpleBarChart(monthlyChartData, monthlyChartContainerId, null, 'count', 'label', 'var(--color-accent)'), 0);
                        dataFound = true;
                    }
                    const dailyChartData = Object.entries(ayahsRecitedByDay)
                        .map(([day, count]) => ({
                            label: new Date(day).toLocaleDateString(navigator.language || 'en-US', {
                                month: 'short',
                                day: 'numeric'
                            }),
                            date: day,
                            count: count,
                            fullLabel: `${new Date(day).toLocaleDateString(navigator.language || 'en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })}: ${count} Ayahs Recited`
                        }))
                        .sort((a, b) => new Date(a.date) - new Date(b.date));
                    if (dailyChartData.length > 0) {
                        let showDailyChart = true;
                        if (dateFrom && dateTo) {
                            const diffTime = Math.abs(dateTo - dateFrom);
                            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                            if (diffDays > 62) {
                                showDailyChart = false;
                            }
                        } else if (!dateFrom && !dateTo && dailyChartData.length > 62) {
                            showDailyChart = false;
                        }
                        if (showDailyChart) {
                            const dailyChartContainerId = 'recitationLogsDailyChart';
                            let sectionElDaily = contentArea.querySelector('#recitationLogsDailyChartSection');
                            if (!sectionElDaily) {
                                sectionElDaily = document.createElement('div');
                                sectionElDaily.className = 'report-section';
                                sectionElDaily.id = 'recitationLogsDailyChartSection';
                                contentArea.appendChild(sectionElDaily);
                            }
                            sectionElDaily.innerHTML = `<h4>Ayahs Recited (Daily - Filtered Period)</h4><div id="${dailyChartContainerId}"></div>`;
                            setTimeout(() => createSimpleBarChart(dailyChartData, dailyChartContainerId, null, 'count', 'label', 'var(--color-accent-dark)'), 0);
                            dataFound = true;
                        }
                    }
                }
            }
            contentArea.querySelectorAll('.item-surah-ayah').forEach(el => el?.addEventListener('click', e => {
                const s = parseInt(e.currentTarget.dataset.surah),
                    a = parseInt(e.currentTarget.dataset.ayah);
                if (s && a && typeof loadAyah === 'function' && typeof window.showSection === 'function') {
                    loadAyah(s, a);
                    window.showSection('quran');
                }
            }));
            if (!dataFound) contentArea.innerHTML = '<p id="noReportDataMessage">No data matches criteria.</p>';
        }

        function createSimpleBarChart(data, containerId, title, valueKey = 'count', labelKey = 'label', barColor = 'var(--color-accent)') {
            const container = document.getElementById(containerId);
            if (!container) {
                console.error(`Chart container #${containerId} not found.`);
                return;
            }
            container.innerHTML = '';
            if (!data || data.length === 0) {
                container.innerHTML = "<p>No data to display in chart.</p>";
                return;
            }
            if (title) {
                const titleEl = document.createElement('div');
                titleEl.className = 'bar-chart-title';
                titleEl.textContent = title;
                container.appendChild(titleEl);
            }
            const chartEl = document.createElement('div');
            chartEl.className = 'bar-chart';
            if (data.length > 15) {
                chartEl.style.overflowX = 'auto';
                chartEl.style.minWidth = `${data.length * 30}px`;
            }
            const maxValue = Math.max(...data.map(item => item[valueKey]), 0);
            if (maxValue === 0 && data.every(item => item[valueKey] === 0)) {
                container.innerHTML += "<p>All values are zero for this period.</p>";
                return;
            }
            data.forEach(item => {
                const barWrapper = document.createElement('div');
                barWrapper.className = 'bar';
                const numericValue = Number(item[valueKey]) || 0;
                const percentageHeight = maxValue > 0 ? (numericValue / maxValue) * 100 : 0;
                barWrapper.style.height = `${Math.max(percentageHeight, 5)}%`;
                barWrapper.style.backgroundColor = barColor;
                barWrapper.title = item.fullLabel || `${item[labelKey]}: ${numericValue}`;
                const valueSpan = document.createElement('span');
                valueSpan.className = 'bar-value';
                valueSpan.textContent = numericValue;
                barWrapper.appendChild(valueSpan);
                const labelSpan = document.createElement('span');
                labelSpan.className = 'bar-label';
                labelSpan.textContent = item[labelKey];
                barWrapper.appendChild(labelSpan);
                chartEl.appendChild(barWrapper);
            });
            container.appendChild(chartEl);
        }
        let reportingModuleInitialized = false;

        function initializeReportingModule_Enhanced() {
            if (!isUserLoggedIn) {
                createReportingSectionStructure_Enhanced();
                injectReportingModuleStyles_Enhanced();
                return;
            }
            if (reportingModuleInitialized) return;
            createReportingSectionStructure_Enhanced();
            injectReportingModuleStyles_Enhanced();
            const sidebarNav = document.querySelector('.sidebar nav ul');
            const existingReportLink = document.querySelector('a[data-section="reporting"]');
            if (sidebarNav && !existingReportLink) {
                const reportLi = document.createElement('li');
                const reportLink = document.createElement('a');
                reportLink.href = "#reporting";
                reportLink.className = "nav-link";
                reportLink.dataset.section = "reporting";
                reportLink.textContent = "Reporting";
                reportLi.appendChild(reportLink);
                const dataManagementLink = sidebarNav.querySelector('a[data-section="data"]');
                if (dataManagementLink && dataManagementLink.parentElement) {
                    sidebarNav.insertBefore(reportLi, dataManagementLink.parentElement);
                } else {
                    sidebarNav.appendChild(reportLi);
                }
                reportLink?.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (typeof window.showSection === 'function') {
                        window.showSection('reporting');
                    } else {
                        console.error("Global window.showSection function not found for reporting link.");
                    }
                });
            }
            reportingModuleInitialized = true;
        }
        async function loadAndDisplayReportData_Enhanced() {
            if (!isUserLoggedIn) {
                document.getElementById('reportingContent').innerHTML = '<p class="text-center">Please login to access reports.</p>';
                document.getElementById('reportingDashboard').innerHTML = '<p class="text-center">Please login to access dashboard.</p>';
                document.querySelector('.reporting-filters-container').style.display = 'none';
                return;
            } else {
                document.querySelector('.reporting-filters-container').style.display = 'block';
            }
            const reportingContent = document.getElementById('reportingContent');
            const dashboardArea = document.getElementById('reportingDashboard');
            if (dashboardArea) dashboardArea.innerHTML = '<p>Loading dashboard...</p>';
            if (reportingContent) reportingContent.innerHTML = '<p>Loading report data...</p>';
            try {
                await loadAllUserDataForReports();
                if (!currentReportingUserData) {
                    if (dashboardArea) dashboardArea.innerHTML = "<p>Failed to load data for dashboard.</p>";
                    if (reportingContent) reportingContent.innerHTML = "<p>Failed to load report data. Check console for errors.</p>";
                    return;
                }
                renderReportingDashboard(currentReportingUserData);
                renderReportDetails(currentReportingUserData, {
                    reportType: 'all'
                });
                setupReportingFiltersEventListeners_Enhanced(currentReportingUserData);
            } catch (error) {
                console.error("Error loading/displaying report data:", error);
                if (dashboardArea) dashboardArea.innerHTML = "<p style='color:red;'>Error loading dashboard. See console.</p>";
                if (reportingContent) reportingContent.innerHTML = "<p style='color:red;'>Error loading reports. See console.</p>";
            }
        }
        if (!window.originalAppDOMContentLoaded && typeof window.originalDOMContentLoadedHandler === 'function') {
            window.originalAppDOMContentLoaded = window.originalDOMContentLoadedHandler;
        } else if (!window.originalAppDOMContentLoaded) {}
        (function() {
            let originalDOMContentLoadedHandler = null;
            let originalShowSectionHandler = null;
            if (typeof window.originalDOMContentLoadedHandler === 'function') {
                originalDOMContentLoadedHandler = window.originalDOMContentLoadedHandler;
            }
            if (typeof window.showSection === 'function') {
                originalShowSectionHandler = window.showSection;
            }
            const patchedDOMLoadHandler = async () => {
                if (typeof originalDOMContentLoadedHandler === 'function' && originalDOMContentLoadedHandler !== patchedDOMLoadHandler) {
                    await originalDOMContentLoadedHandler();
                } else {
                    if (typeof loadThemePreference === 'function') await loadThemePreference();
                    if (typeof setupEventListeners === 'function') setupEventListeners();
                    else console.error("setupEventListeners not found");
                    if (typeof loadQuranData === 'function') await loadQuranData();
                    if (isUserLoggedIn) {
                        if (typeof displayThemesList === 'function') displayThemesList();
                        if (typeof setupTafsirExportButtons === 'function') setupTafsirExportButtons();
                        if (typeof initializeGoalsModule === 'function') initializeGoalsModule();
                        if (typeof initializeReportingModule_Enhanced === 'function') initializeReportingModule_Enhanced();
                        if (typeof setupGameModal === 'function') console.error("setupGameModal not found");
                        if (window.rootNodePopupEl) {
                            window.rootNodePopupEl = document.getElementById('root-node-popup');
                        } else {
                            window.rootNodePopupEl = document.createElement('div');
                            window.rootNodePopupEl.id = 'root-node-popup';
                            document.body.appendChild(window.rootNodePopupEl);
                        }
                    }
                }
            };
            if (typeof window.originalDOMContentLoadedHandler === 'function') {
                document.removeEventListener('DOMContentLoaded', window.originalDOMContentLoadedHandler);
            }
            window.originalDOMContentLoadedHandler = patchedDOMLoadHandler;
            document?.addEventListener('DOMContentLoaded', window.originalDOMContentLoadedHandler);

            function patchedShowSection(sectionId) {
                if (typeof originalShowSectionHandler === 'function' && originalShowSectionHandler !== patchedShowSection) {
                    originalShowSectionHandler.apply(this, arguments);
                } else {
                    if (typeof document !== 'undefined') {
                        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
                        const activeS = document.getElementById(sectionId);
                        if (activeS) activeS.classList.add('active');
                        document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                        const activeL = document.querySelector(`.nav-link[data-section="${sectionId}"]`);
                        if (activeL) activeL.classList.add('active');
                    }
                }
                const activeSectionElement = typeof document !== 'undefined' ? document.getElementById(sectionId) : null;
                if (sectionId === 'reporting') {
                    if (isUserLoggedIn && activeSectionElement && activeSectionElement.classList.contains('active')) {
                        enterReportingFullscreen();
                        if (!currentReportingUserData) {
                            loadAndDisplayReportData_Enhanced();
                        } else {
                            renderReportingDashboard(currentReportingUserData);
                            renderReportDetails(currentReportingUserData, {
                                reportType: 'all'
                            });
                            setupReportingFiltersEventListeners_Enhanced(currentReportingUserData);
                        }
                    } else if (!isUserLoggedIn && activeSectionElement) {
                        activeSectionElement.innerHTML = '<p class="text-center">Please login to access reports.</p>';
                    }
                } else {
                    if (typeof document !== 'undefined' && document.body.classList.contains('body-reporting-fullscreen')) {
                        exitReportingFullscreen();
                    }
                }
                if (isUserLoggedIn) {
                    if (typeof document !== 'undefined') {
                        if (sectionId === 'themes' && typeof populateThemeSelects === 'function' && typeof displayLinkedAyahsForCurrentTheme === 'function') {
                            populateThemeSelects();
                            displayLinkedAyahsForCurrentTheme();
                        } else if (sectionId === 'recitation' && typeof loadRecitationLogs === 'function') {
                            loadRecitationLogs();
                        } else if (sectionId === 'hifz' && typeof loadHifzForSurah === 'function') {
                            const hifzSurahSelect = document.getElementById('hifz-surah-select');
                            if (hifzSurahSelect && hifzSurahSelect.value) loadHifzForSurah(parseInt(hifzSurahSelect.value, 10));
                        } else if (sectionId === 'roots' && typeof window.rootNetwork !== 'undefined' && window.rootNetwork && typeof window.rootNetwork.fit === 'function') {
                            setTimeout(() => window.rootNetwork.fit(), 100);
                        } else if (sectionId === 'goals' && typeof renderGoalsUI === 'function') {
                            renderGoalsUI();
                        }
                    }
                } else if (sectionId !== 'quran' && activeSectionElement && !activeSectionElement.classList.contains('reporting-fullscreen-active')) {
                    activeSectionElement.innerHTML = '<p class="text-center">Please login to access this feature.</p>';
                }
            };
            window.showSection = patchedShowSection;
        })();

        function detectDirection(text) {
            const rtlPattern = /[\u0591-\u07FF\uFB1D-\uFDFD\uFE70-\uFEFC]/;
            return rtlPattern.test(text) ? 'rtl' : 'ltr';
        }
        const textarea = document.getElementById('tafsir-notes');
        if (textarea) {
            ['input', 'change', 'keyup', 'paste'].forEach(eventType => {
                textarea?.addEventListener(eventType, () => {
                    const dir = detectDirection(textarea.value);
                    textarea.setAttribute('dir', dir);
                });
            });
            const originalDescriptor = Object.getOwnPropertyDescriptor(HTMLTextAreaElement.prototype, 'value');
            Object.defineProperty(textarea, 'value', {
                get() {
                    return originalDescriptor.get.call(this);
                },
                set(val) {
                    originalDescriptor.set.call(this, val);
                    const dir = detectDirection(val);
                    textarea.setAttribute('dir', dir);
                }
            });
            const initialDir = detectDirection(textarea.value);
            textarea.setAttribute('dir', initialDir);
        }
        let jigsawState = {
            difficulty: 'easy',
            piecesCorrect: 0,
            totalPieces: 0,
            currentAyahRef: '',
            draggedPiece: null,
        };

        function injectVerseJigsawCSS_Engine() {
            const cssId = "verseJigsawGameStylesEngine";
            if (document.getElementById(cssId)) return;
            const styles = `
                .jigsaw-game-wrapper { display: flex; flex-direction: column; align-items: center; width: 100%; height: 100%; padding: 5px; box-sizing: border-box; }
                .jigsaw-controls { display: flex; justify-content: center; align-items: center; gap: 15px; margin-bottom: 10px; width: 100%; flex-wrap: wrap; }
                .jigsaw-controls label { font-size: 0.9em; }
                .jigsaw-main-area { display: flex; flex-direction: column; align-items: center; gap: 15px; width: 100%; }
                #jigsawBoard_Engine {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 5px;
                    padding: 10px;
                    width: 95%;
                    min-height: 100px;
                    border: 2px solid var(--color-accent-dark);
                    border-radius: var(--border-radius);
                    background-color: var(--color-bg-primary);
                    direction: rtl;
                }
                .jigsaw-slot {
                    flex-grow: 1;
                    min-width: 80px;
                    height: 60px;
                    border: 1px dashed var(--color-border);
                    background-color: var(--color-bg-secondary);
                    transition: background-color 0.2s;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    font-family: var(--font-arabic);
                    font-size: 1.8rem;
                }
                .jigsaw-slot.over { background-color: var(--color-highlight); }
                .jigsaw-slot.filled { border-style: solid; background-color: var(--color-success); color: white; }
                #jigsawPieceTray_Engine {
                    display: flex; flex-wrap: wrap; gap: 8px;
                    width: 95%;
                    padding: 10px;
                    border: 1px solid var(--color-border);
                    border-radius: var(--border-radius);
                    background-color: var(--color-bg-secondary);
                    justify-content: center;
                    min-height: 70px;
                }
                .jigsaw-piece {
                    padding: 8px 12px;
                    border: 1px solid var(--color-accent);
                    border-radius: var(--border-radius);
                    background-color: var(--color-bg-primary);
                    cursor: grab;
                    transition: opacity 0.2s;
                    font-family: var(--font-arabic);
                    font-size: 1.8rem;
                    direction: rtl;
                    user-select: none;
                }
                .jigsaw-piece.dragging { opacity: 0.4; cursor: grabbing; }
                #jigsawCompletionMessage_Engine {
                    text-align: center; margin-top: 15px; font-size: 1.2rem; color: var(--color-success); font-weight: bold;
                }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }

        function startVerseJigsawGame_Engine() {
            activeGame = 'verseJigsaw_engine';
            showGamePlayUI("Verse Jigsaw Puzzle");
            injectVerseJigsawCSS_Engine();
            const gamePlayArea = document.getElementById('gamePlayArea');
            gamePlayArea.innerHTML = `
                <div class="jigsaw-game-wrapper">
                    <div class="jigsaw-controls">
                        <label for="jigsawDifficultySelect">Ayah Length:</label>
                        <select id="jigsawDifficultySelect">
                            <option value="easy">Short (4-6 Words)</option>
                            <option value="medium">Medium (7-10 Words)</option>
                            <option value="hard">Long (11-15 Words)</option>
                        </select>
                        <button id="newJigsawPuzzleBtn">New Puzzle</button>
                    </div>
                    <p id="jigsawAyahRef" style="text-align:center; font-style:italic; color:var(--color-text-secondary);"></p>
                    <div class="jigsaw-main-area">
                        <div id="jigsawPieceTray_Engine"><p>Loading Puzzle...</p></div>
                        <div id="jigsawBoard_Engine"></div>
                    </div>
                    <div id="jigsawCompletionMessage_Engine"></div>
                </div>
            `;
            document.getElementById('jigsawDifficultySelect')?.addEventListener('change', (e) => {
                jigsawState.difficulty = e.target.value;
                generateNewJigsawPuzzle_Engine();
            });
            document.getElementById('newJigsawPuzzleBtn')?.addEventListener('click', generateNewJigsawPuzzle_Engine);
            generateNewJigsawPuzzle_Engine();
        }
        async function generateNewJigsawPuzzle_Engine() {
            const tray = document.getElementById('jigsawPieceTray_Engine');
            const board = document.getElementById('jigsawBoard_Engine');
            const msg = document.getElementById('jigsawCompletionMessage_Engine');
            const ref = document.getElementById('jigsawAyahRef');
            tray.innerHTML = '<p>Searching for a suitable Ayah...</p>';
            board.innerHTML = '';
            msg.textContent = '';
            ref.textContent = '';
            const difficultyMap = {
                easy: {
                    min: 4,
                    max: 6
                },
                medium: {
                    min: 7,
                    max: 10
                },
                hard: {
                    min: 11,
                    max: 15
                }
            };
            const {
                min,
                max
            } = difficultyMap[jigsawState.difficulty];
            let attempts = 0;
            let words = [];
            let ayahData;
            try {
                while (attempts < 50) {
                    const randomSurah = Math.floor(Math.random() * 114) + 1;
                    const randomAyahNum = Math.floor(Math.random() * surahAyahCounts[randomSurah]) + 1;
                    const fetchedResult = await sendAjaxRequest('load_quran_ayah', {
                        surah: randomSurah,
                        ayah: randomAyahNum
                    });
                    const fetchedData = fetchedResult.success ? fetchedResult.data : null;
                    if (fetchedData && fetchedData.arabic) {
                        const fetchedWords = fetchedData.arabic.trim().split(/\s+/).filter(w => w);
                        if (fetchedWords.length >= min && fetchedWords.length <= max) {
                            words = fetchedWords;
                            ayahData = fetchedData;
                            break;
                        }
                    }
                    attempts++;
                }
            } catch (err) {
                board.innerHTML = `<p style='color:red'>Error accessing database.</p>`;
                return;
            }
            if (words.length === 0) {
                tray.innerHTML = `<p style='color:red'>Could not find a suitable Ayah. Please try another difficulty.</p>`;
                return;
            }
            jigsawState.totalPieces = words.length;
            jigsawState.piecesCorrect = 0;
            jigsawState.currentAyahRef = `Surah ${ayahData.surah}:${ayahData.ayah}`;
            ref.textContent = `Assemble the verse from: ${jigsawState.currentAyahRef}`;
            tray.innerHTML = '';
            let pieces = [];
            for (let i = 0; i < words.length; i++) {
                const slot = document.createElement('div');
                slot.classList.add('jigsaw-slot');
                slot.dataset.slotIndex = i;
                board.appendChild(slot);
                const piece = document.createElement('div');
                piece.id = `piece-${i}`;
                piece.dataset.wordIndex = i;
                piece.classList.add('jigsaw-piece');
                piece.draggable = true;
                piece.textContent = words[i];
                pieces.push(piece);
            }
            shuffleArray(pieces).forEach(p => tray.appendChild(p));
            addJigsawDragDropListeners_Engine();
        }

        function addJigsawDragDropListeners_Engine() {
            document.querySelectorAll('.jigsaw-piece').forEach(piece => {
                piece?.addEventListener('dragstart', e => {
                    jigsawState.draggedPiece = e.target;
                    setTimeout(() => e.target.classList.add('dragging'), 0);
                });
                piece?.addEventListener('dragend', e => e.target.classList.remove('dragging'));
            });
            document.querySelectorAll('.jigsaw-slot').forEach(slot => {
                slot?.addEventListener('dragover', e => {
                    e.preventDefault();
                    if (!slot.hasChildNodes()) slot.classList.add('over');
                });
                slot?.addEventListener('dragleave', () => slot.classList.remove('over'));
                slot?.addEventListener('drop', e => {
                    e.preventDefault();
                    slot.classList.remove('over');
                    if (jigsawState.draggedPiece && !slot.hasChildNodes() && slot.dataset.slotIndex === jigsawState.draggedPiece.dataset.wordIndex) {
                        slot.textContent = '';
                        slot.appendChild(jigsawState.draggedPiece);
                        jigsawState.draggedPiece.draggable = false;
                        jigsawState.draggedPiece.style.cursor = 'default';
                        slot.classList.add('filled');
                        jigsawState.draggedPiece = null;
                        jigsawState.piecesCorrect++;
                        if (jigsawState.piecesCorrect === jigsawState.totalPieces) {
                            document.getElementById('jigsawCompletionMessage_Engine').textContent = "Masha'Allah! Verse Assembled!";
                        }
                    }
                });
            });
        }

        function addVerseJigsawGameButtonToModal() {
            const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
            const buttonId = 'startVerseJigsawBtn';
            if (gameSelectionArea && !document.getElementById(buttonId)) {
                const jigsawButton = document.createElement('button');
                jigsawButton.id = buttonId;
                jigsawButton.className = 'game-select-btn';
                jigsawButton.textContent = 'Takmil al-Ayah';
                jigsawButton?.addEventListener('click', startVerseJigsawGame_Engine);
                const existingButtons = gameSelectionArea.querySelectorAll('.game-select-btn');
                if (existingButtons.length > 0) {
                    existingButtons[existingButtons.length - 1].insertAdjacentElement('afterend', jigsawButton);
                } else {
                    gameSelectionArea.appendChild(jigsawButton);
                }
            }
        }
        if (typeof setupGameModal.isPatchedForJigsaw === 'undefined') {
            const originalSetupGameModal = setupGameModal;

            window.setupGameModal = function() {
                originalSetupGameModal.apply(this, arguments);
                addVerseJigsawGameButtonToModal();
            };
            window.setupGameModal.isPatchedForJigsaw = true;
        }
        (function() {
            'use strict';
            let ayahmatState = {
                currentQuestion: null,
                totalQuestions: 5,
                currentQuestionIndex: 0,
                score: 0,
            };

            function injectAyahMatchCSS_Engine() {
                const cssId = "ayahMatchGameStylesEngine";
                if (document.getElementById(cssId)) return;
                const styles = `
                    .ayah-match-wrapper { display: flex; flex-direction: column; align-items: center; width: 100%; padding: 10px; text-align: center; }
                    .ayah-match-question-arabic {
                        font-size: 2rem; line-height: 2.5; margin: 20px 0; padding: 20px;
                        background-color: var(--color-bg-primary); border-radius: var(--border-radius);
                        color: var(--color-text-primary); border: 1px solid var(--color-border);
                        font-family: var(--font-arabic); direction: rtl;
                    }
                    .ayah-match-options-container { display: flex; flex-direction: column; gap: 10px; width: 100%; max-width: 600px; margin: 0 auto; }
                    .ayah-match-option-btn {
                        padding: 15px; font-size: 1.2rem; text-align: left; line-height: 1.6;
                    }
                    .ayah-match-option-btn.correct { background-color: var(--color-success) !important; color: white !important; }
                    .ayah-match-option-btn.incorrect { background-color: var(--color-error) !important; color: white !important; }
                    .ayah-match-feedback { margin-top: 15px; font-weight: bold; min-height: 1.5em; }
                    .ayah-match-controls button { margin-top: 10px; }
                `;
                const styleSheet = document.createElement("style");
                styleSheet.id = cssId;
                styleSheet.type = "text/css";
                styleSheet.innerText = styles;
                document.head.appendChild(styleSheet);
            }
            async function startAyahMatchGame_Engine() {
                activeGame = 'ayahMatch_engine';
                showGamePlayUI("Ayah-Translation Match");
                injectAyahMatchCSS_Engine();
                ayahmatState.currentQuestionIndex = 0;
                ayahmatState.score = 0;
                gameScore = 0;
                updateScoreDisplay();
                const gamePlayArea = document.getElementById('gamePlayArea');
                gamePlayArea.innerHTML = `<div class="ayah-match-wrapper"><p>Loading a new question...</p></div>`;
                await displayNextAyahMatchQuestion();
            }
            async function displayNextAyahMatchQuestion() {
                const gamePlayArea = document.getElementById('gamePlayArea');
                if (ayahmatState.currentQuestionIndex >= ayahmatState.totalQuestions) {
                    endAyahMatchGame_Engine();
                    return;
                }
                gamePlayArea.innerHTML = `<div class="ayah-match-wrapper"><p>Searching for a suitable Ayah...</p></div>`;
                const question = await fetchAyahMatchQuestion_Engine();
                if (!question) {
                    gamePlayArea.innerHTML = `<div class="ayah-match-wrapper"><p style="color:red;">Could not generate a question. Please try again.</p><div class="ayah-match-controls"><button id="ayahMatchNextBtn">Try Again</button></div></div>`;
                    document.getElementById('ayahMatchNextBtn')?.addEventListener('click', displayNextAyahMatchQuestion);
                    return;
                }
                ayahmatState.currentQuestion = question;
                let optionsHTML = '';
                question.options.forEach(opt => {
                    optionsHTML += `<button class="ayah-match-option-btn" data-answer="${opt.translation}" style="font-family:${opt.font}; direction:${opt.dir}; text-align:${opt.textAlign};">${opt.translation}</button>`;
                });
                gamePlayArea.innerHTML = `
                    <div class="ayah-match-wrapper">
                        <p>Which is the correct translation for the following Ayah?</p>
                        <div class="ayah-match-question-arabic">${question.arabicQuestion}</div>
                        <div class="ayah-match-options-container">${optionsHTML}</div>
                        <div class="ayah-match-feedback"></div>
                        <div class="ayah-match-controls"><button id="ayahMatchNextBtn" style="display:none;">Next Question</button></div>
                    </div>`;
                document.querySelectorAll('.ayah-match-option-btn').forEach(btn => btn?.addEventListener('click', handleAyahMatchAnswer_Engine));
                document.getElementById('ayahMatchNextBtn')?.addEventListener('click', displayNextAyahMatchQuestion);
            }
            async function fetchAyahMatchQuestion_Engine() {
                let attempts = 0;
                console.log("[AyahMatch v9] Starting robust search for question set...");
                while (attempts < 100) {
                    attempts++;
                    try {
                        let candidateAyahs = [];
                        let seenKeys = new Set();
                        while (candidateAyahs.length < 3) {
                            const rSurah = Math.floor(Math.random() * 114) + 1;
                            const rAyah = Math.floor(Math.random() * (surahAyahCounts[rSurah] || 1)) + 1;
                            const key = `${rSurah}:${rAyah}`;
                            if (seenKeys.has(key)) continue;
                            const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                                surah: rSurah,
                                ayah: rAyah
                            });
                            const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                            seenKeys.add(key);
                            if (!ayahData || !ayahData.arabic || ayahData.arabic.trim() === "") continue;
                            const hasTranslation = allLanguagesConfig.some(conf => ayahData[conf.key] && ayahData[conf.key].trim() !== "");
                            if (!hasTranslation) continue;
                            candidateAyahs.push(ayahData);
                        }
                        const correctAnswerRecord = candidateAyahs[0];
                        const distractor1Record = candidateAyahs[1];
                        const distractor2Record = candidateAyahs[2];
                        const getDisplayTranslation = (record) => {
                            const selectedLangKey = document.getElementById('translation-select').value;
                            const fallbackOrder = allLanguagesConfig.map(c => c.key);
                            const prioritizedOrder = [selectedLangKey, ...fallbackOrder.filter(k => k !== selectedLangKey)];
                            for (const lang of prioritizedOrder) {
                                if (record[lang] && record[lang].trim() !== "") {
                                    return {
                                        translation: record[lang],
                                        config: getTranslationConfig(lang)
                                    };
                                }
                            }
                            return null;
                        };
                        const correctAnswerOption = getDisplayTranslation(correctAnswerRecord);
                        const distractor1Option = getDisplayTranslation(distractor1Record);
                        const distractor2Option = getDisplayTranslation(distractor2Record);
                        if (!correctAnswerOption || !distractor1Option || !distractor2Option) {
                            console.error("[AyahMatch] Failed to get display translations even after validation. Retrying.");
                            continue;
                        }
                        const options = [{
                                translation: correctAnswerOption.translation,
                                font: correctAnswerOption.config.font,
                                dir: correctAnswerOption.config.dir,
                                textAlign: correctAnswerOption.config.dir === 'rtl' ? 'right' : 'left'
                            },
                            {
                                translation: distractor1Option.translation,
                                font: distractor1Option.config.font,
                                dir: distractor1Option.config.dir,
                                textAlign: distractor1Option.config.dir === 'rtl' ? 'right' : 'left'
                            },
                            {
                                translation: distractor2Option.translation,
                                font: distractor2Option.config.font,
                                dir: distractor2Option.config.dir,
                                textAlign: distractor2Option.config.dir === 'rtl' ? 'right' : 'left'
                            },
                        ];
                        console.log(`[AyahMatch v9] SUCCESS! Generated a valid question from S${correctAnswerRecord.surah}:A${correctAnswerRecord.ayah}.`);
                        return {
                            arabicQuestion: correctAnswerRecord.arabic,
                            options: shuffleArray(options),
                            correctAnswer: correctAnswerOption.translation,
                        };
                    } catch (error) {
                        console.warn(`[AyahMatch] Error during attempt ${attempts}:`, error);
                    }
                }
                console.error("[AyahMatch v9] CRITICAL FAILURE: Failed to generate a question after all attempts.");
                return null;
            }

            function handleAyahMatchAnswer_Engine(event) {
                const selectedButton = event.target;
                const selectedAnswer = selectedButton.dataset.answer;
                const correctAnswer = ayahmatState.currentQuestion.correctAnswer;
                const feedbackEl = document.querySelector('.ayah-match-feedback');
                document.querySelectorAll('.ayah-match-option-btn').forEach(btn => {
                    btn.disabled = true;
                    if (btn.dataset.answer === correctAnswer) {
                        btn.classList.add('correct');
                    }
                });
                if (selectedAnswer === correctAnswer) {
                    feedbackEl.textContent = "Correct! Masha'Allah!";
                    feedbackEl.style.color = 'var(--color-success)';
                    ayahmatState.score += 10;
                    gameScore = ayahmatState.score;
                    if (gameScore > gameHighScore) gameHighScore = gameScore;
                    updateScoreDisplay();
                } else {
                    selectedButton.classList.add('incorrect');
                    feedbackEl.textContent = "Not quite. The correct translation is highlighted in green.";
                    feedbackEl.style.color = 'var(--color-error)';
                }
                ayahmatState.currentQuestionIndex++;
                document.getElementById('ayahMatchNextBtn').style.display = 'inline-block';
            }

            function endAyahMatchGame_Engine() {
                const gamePlayArea = document.getElementById('gamePlayArea');
                gamePlayArea.innerHTML = `
                    <div class="ayah-match-wrapper">
                        <h3>Game Over!</h3>
                        <p>Your final score: ${ayahmatState.score} / ${ayahmatState.totalQuestions * 10}</p>
                        <div class="ayah-match-controls"><button id="playAyahMatchAgainBtn">Play Again</button></div>
                    </div>`;
                document.getElementById('playAyahMatchAgainBtn')?.addEventListener('click', startAyahMatchGame_Engine);
                activeGame = null;
            }

            function addAyahMatchGameButtonToModal() {
                const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
                const oldButton = document.getElementById('startContextualCluesBtn');
                if (oldButton) oldButton.remove();
                const buttonId = 'startAyahMatchBtn';
                if (gameSelectionArea && !document.getElementById(buttonId)) {
                    const matchButton = document.createElement('button');
                    matchButton.id = buttonId;
                    matchButton.className = 'game-select-btn';
                    matchButton.textContent = 'Ayah-Translation Match';
                    matchButton?.addEventListener('click', startAyahMatchGame_Engine);
                    gameSelectionArea.appendChild(matchButton);
                }
            }
            if (typeof setupGameModal !== 'undefined' && typeof setupGameModal.isPatchedForAyahMatch_v9 === 'undefined') {
                const originalSetupGameModal = setupGameModal;

                window.setupGameModal = function() {
                    originalSetupGameModal.apply(this, arguments);
                    addAyahMatchGameButtonToModal();
                };
                window.setupGameModal.isPatchedForAyahMatch_v9 = true;
            }
        })();

        function initializeGoalsModule() {
            if (!isUserLoggedIn) {
                createGoalsSectionHTML();
                injectGoalsCSS();
                return;
            }
            if (window.goalsModuleInitialized) return;
            injectGoalsCSS();
            addGoalsNavLink();
            createGoalsSectionHTML();
            setupGoalsFormListener();
            patchShowSectionForGoals();
            window.goalsModuleInitialized = true;
        }

        function injectGoalsCSS() {
            const cssId = "studyGoalsStyles";
            if (document.getElementById(cssId)) document.getElementById(cssId).remove();
            const styles = `
                .goals-container { padding: 10px; }
                .add-goal-form { background-color: var(--color-bg-secondary); padding: 20px; border-radius: var(--border-radius); margin-bottom: 25px; box-shadow: 0 2px 5px var(--color-shadow); }
                .add-goal-form h3 { margin-top: 0; }
                .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; align-items: end; }
                .form-grid label { display: block; margin-bottom: 5px; font-weight: bold; color: var(--color-text-secondary); }
                .form-grid input, .form-grid select { width: 100%; max-width: 100%; }
                .goals-tabs { display: flex; border-bottom: 2px solid var(--color-border); margin-bottom: 20px; }
                .goal-tab { padding: 10px 20px; cursor: pointer; font-size: 1.1em; color: var(--color-text-secondary); }
                .goal-tab.active { color: var(--color-text-primary); border-bottom: 3px solid var(--color-accent); font-weight: bold; }
                .goals-list-panel { display: none; }
                .goals-list-panel.active { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; }
                .goal-card { background-color: var(--color-bg-primary); border: 1px solid var(--color-border); border-left: 5px solid var(--color-accent); border-radius: var(--border-radius); padding: 20px; display: flex; flex-direction: column; }
                .goal-card.completed { border-left-color: var(--color-success); opacity: 0.8; }
                .goal-card h4 { margin-top: 0; color: var(--color-accent-dark); }
                .goal-card .goal-meta { font-size: 0.9em; color: var(--color-text-secondary); margin-bottom: 15px; }
                .progress-container { margin-bottom: 10px; }
                .progress-label { display: flex; justify-content: space-between; font-size: 0.9em; margin-bottom: 5px; }
                .progress-bar-bg { background-color: var(--color-bg-secondary); border-radius: 5px; overflow: hidden; height: 22px; }
                .progress-bar { background-color: var(--color-accent); height: 100%; width: 0%; transition: width 0.5s ease-in-out; text-align: center; color: white; font-weight: bold; font-size: 0.8em; line-height: 22px; }
                .goal-actions { margin-top: 20px; text-align: right; display: flex; gap: 10px; justify-content: flex-end; }
                .goal-actions button { font-size: 0.85em; padding: 6px 12px; }
                .delete-goal-btn { background-color: var(--color-error); }
                .view-details-btn { background-color: var(--color-accent-dark); }
                .goal-details-modal .modal-content { max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; }
                .goal-details-modal h3 { border-bottom: 1px solid var(--color-border); padding-bottom: 10px; margin-bottom: 15px; }
                .goal-details-content { overflow-y: auto; flex-grow: 1; }
                .detail-list { list-style-type: none; padding: 0; }
                .detail-item { padding: 10px; border-bottom: 1px dotted var(--color-border); }
                .detail-item a { color: var(--color-accent-dark); cursor: pointer; font-weight: bold; }
                .detail-item-meta { font-size: 0.85em; color: var(--color-text-secondary); margin-left: 10px;}
                .detail-item-notes { font-style: italic; display: block; margin-top: 5px; padding-left: 15px; font-size: 0.9em; }
                .detail-item-status { font-size: 0.8em; padding: 3px 8px; border-radius: var(--border-radius); float: right; }
                .status-complete { background-color: var(--color-success); color: white; }
                .status-incomplete { background-color: #e0e0e0; color: #424242; }
            `;
            const styleSheet = document.createElement("style");
            styleSheet.id = cssId;
            styleSheet.type = "text/css";
            styleSheet.innerText = styles;
            document.head.appendChild(styleSheet);
        }

        function addGoalsNavLink() {
            const sidebarNav = document.querySelector('.sidebar nav ul');
            if (!sidebarNav || document.querySelector('a[data-section="goals"]')) return;
            const goalsLi = document.createElement('li');
            const goalsLink = document.createElement('a');
            goalsLink.href = "#goals";
            goalsLink.className = "nav-link";
            goalsLink.dataset.section = "goals";
            goalsLink.textContent = "My Goals";
            goalsLi.appendChild(goalsLink);
            goalsLink?.addEventListener('click', (e) => {
                e.preventDefault();
                window.showSection('goals');
            });
            const reportingLink = sidebarNav.querySelector('a[data-section="reporting"]');
            if (reportingLink) {
                sidebarNav.insertBefore(goalsLi, reportingLink.parentElement);
            } else {
                const dataManagementLink = sidebarNav.querySelector('a[data-section="data"]');
                if (dataManagementLink) {
                    sidebarNav.insertBefore(goalsLi, dataManagementLink.parentElement);
                } else {
                    sidebarNav.appendChild(goalsLi);
                }
            }
        }

        function createGoalsSectionHTML() {
            const mainContent = document.querySelector('.main-content');
            if (!mainContent || document.getElementById('goals')) return;
            const goalsSection = document.createElement('section');
            goalsSection.id = 'goals';
            goalsSection.className = 'section';
            goalsSection.setAttribute('role', 'region');
            goalsSection.setAttribute('aria-labelledby', 'goals-heading');
            let surahOptions = surahNames.map((name, i) => `<option value="${i + 1}">${i + 1}. ${name}</option>`).join('');
            goalsSection.innerHTML = `
                <div class="goals-container">
                    <h2 id="goals-heading">My Quran Goals</h2>
                    <div class="add-goal-form">
                        <h3>Add a New Goal</h3>
                        <form id="goal-form">
                            <div class="form-grid">
                                <div>
                                    <label for="goal-title">Goal Title:</label>
                                    <input type="text" id="goal-title" required placeholder="e.g., Complete First Khatam">
                                </div>
                                <div>
                                    <label for="goal-type">Goal Type:</label>
                                    <select id="goal-type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="read_surah">Read a Surah</option>
                                        <option value="listen_surah">Listen to a Surah</option>
                                        <option value="read_quran">Read Entire Quran</option>
                                        <option value="listen_quran">Listen to Entire Quran</option>
                                        <option value="read_ayahs_daily">Read Ayahs Daily</option>
                                        <option value="listen_ayahs_daily">Listen to Ayahs Daily</option>
                                        <option value="memorize_surah">Memorize a Surah</option>
                                        <option value="tafsir_juz">Complete Tafsir for a Juz</option>
                                        <option value="link_theme">Link Ayahs to a Theme</option>
                                        <option value="recurring_surah_daily">Recite Surah Daily (Habit)</option>
                                        <option value="recurring_surah_weekly">Recite Surah Weekly (Habit)</option>
                                    </select>
                                </div>
                                <div id="goal-target-wrapper"></div>
                                <div id="goal-count-wrapper"></div>
                                <div>
                                    <label for="goal-date">Target Date (for completion goals):</label>
                                    <input type="date" id="goal-date">
                                </div>
                                <div>
                                    <button type="submit">Add Goal</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="goals-tabs">
                        <span class="goal-tab active" data-tab="active">Active Goals</span>
                        <span class="goal-tab" data-tab="completed">Completed Goals</span>
                    </div>
                    <div id="goals-list-active" class="goals-list-panel active"></div>
                    <div id="goals-list-completed" class="goals-list-panel"></div>
                </div>
                <div id="goalDetailsModal" class="modal goal-details-modal"><div class="modal-content"><span class="close-button" onclick="this.parentElement.parentElement.style.display='none'">×</span><h3 id="modalGoalTitle"></h3><div id="modalGoalContent" class="goal-details-content"></div></div></div>
            `;
            mainContent.appendChild(goalsSection);
            document.getElementById('goal-type')?.addEventListener('change', async (e) => {
                const targetWrapper = document.getElementById('goal-target-wrapper');
                const countWrapper = document.getElementById('goal-count-wrapper');
                const type = e.target.value;
                targetWrapper.innerHTML = '';
                countWrapper.innerHTML = '';
                switch (type) {
                    case 'read_surah':
                    case 'listen_surah':
                    case 'memorize_surah':
                    case 'recurring_surah_daily':
                        targetWrapper.innerHTML = `<label for="goal-target-surah">Select Surah:</label><select id="goal-target-surah" required>${surahOptions}</select>`;
                        break;
                    case 'recurring_surah_weekly':
                        const dayOptions = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                            .map((day, i) => `<option value="${i}">${day}</option>`).join('');
                        targetWrapper.innerHTML = `<label for="goal-target-surah">Select Surah:</label><select id="goal-target-surah" required>${surahOptions}</select>`;
                        countWrapper.innerHTML = `<label for="goal-target-day">On which day?</label><select id="goal-target-day" required>${dayOptions}</select>`;
                        break;
                    case 'read_ayahs_daily':
                    case 'listen_ayahs_daily':
                        countWrapper.innerHTML = `<label for="goal-count">How many Ayahs per day?</label><input type="number" id="goal-count" min="1" value="10" required>`;
                        break;
                    case 'read_quran':
                    case 'listen_quran':
                        break;
                    case 'tafsir_juz':
                        const juzOptions = juzBoundariesData.map(j => `<option value="${j.juz}">Juz ${j.juz}</option>`).join('');
                        targetWrapper.innerHTML = `<label for="goal-target-juz">Select Juz:</label><select id="goal-target-juz" required>${juzOptions}</select>`;
                        break;
                    case 'link_theme':
                        const themesResult = await sendAjaxRequest('get_all_themes');
                        const themes = themesResult.success ? themesResult.data : [];
                        const themeOptions = themes.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                        targetWrapper.innerHTML = `<label for="goal-target-theme">Select Theme:</label><select id="goal-target-theme" required>${themeOptions || '<option disabled>No themes created</option>'}</select>`;
                        countWrapper.innerHTML = `<label for="goal-count">Link how many Ayahs?</label><input type="number" id="goal-count" min="1" value="10" required>`;
                        break;
                }
            });
            document.querySelectorAll('.goal-tab').forEach(tab => tab?.addEventListener('click', (e) => {
                document.querySelectorAll('.goal-tab, .goals-list-panel').forEach(el => el.classList.remove('active'));
                e.target.classList.add('active');
                document.getElementById(`goals-list-${e.target.dataset.tab}`).classList.add('active');
            }));
        }

        function setupGoalsFormListener() {
            const form = document.getElementById('goal-form');
            if (form && !form.dataset.listenerAttached) {
                form?.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const newGoal = {
                        title: document.getElementById('goal-title').value,
                        type: document.getElementById('goal-type').value,
                        targetDate: document.getElementById('goal-date').value,
                        creationDate: new Date().toISOString().split('T')[0],
                        isComplete: false
                    };
                    const type = newGoal.type;
                    if (type === 'read_surah' || type === 'listen_surah' || type === 'memorize_surah' || type === 'recurring_surah_daily') {
                        newGoal.targetSurah = parseInt(document.getElementById('goal-target-surah').value);
                    } else if (type === 'recurring_surah_weekly') {
                        newGoal.targetSurah = parseInt(document.getElementById('goal-target-surah').value);
                        newGoal.targetDay = parseInt(document.getElementById('goal-target-day').value);
                    } else if (type === 'read_ayahs_daily' || type === 'listen_ayahs_daily') {
                        newGoal.targetCount = parseInt(document.getElementById('goal-count').value);
                    } else if (type === 'tafsir_juz') {
                        newGoal.targetJuz = parseInt(document.getElementById('goal-target-juz').value);
                    } else if (type === 'link_theme') {
                        newGoal.targetTheme = parseInt(document.getElementById('goal-target-theme').value);
                        newGoal.targetCount = parseInt(document.getElementById('goal-count').value);
                    }
                    const result = await sendAjaxRequest('add_goal', newGoal);
                    if (result.success) {
                        form.reset();
                        document.getElementById('goal-target-wrapper').innerHTML = '';
                        document.getElementById('goal-count-wrapper').innerHTML = '';
                        await renderGoalsUI();
                    } else {
                        alert('Failed to add goal: ' + result.message);
                    }
                });
                form.dataset.listenerAttached = 'true';
            }
        }
        async function renderGoalsUI() {
            if (!isUserLoggedIn) {
                const activeList = document.getElementById('goals-list-active');
                const completedList = document.getElementById('goals-list-completed');
                if (activeList) activeList.innerHTML = '<p class="text-center">Please login to manage goals.</p>';
                if (completedList) completedList.innerHTML = '<p class="text-center">Please login to manage goals.</p>';
                return;
            }
            const activeList = document.getElementById('goals-list-active');
            const completedList = document.getElementById('goals-list-completed');
            if (!activeList || !completedList) return;
            activeList.innerHTML = '<p>Loading active goals...</p>';
            completedList.innerHTML = '<p>Loading completed goals...</p>';
            const allGoalsResult = await sendAjaxRequest('get_all_goals');
            const allGoals = allGoalsResult.success ? allGoalsResult.data : [];
            const userData = {
                hifz: (await sendAjaxRequest('get_all_hifz')).data || [],
                tafsir: (await sendAjaxRequest('get_all_tafsir')).data || [],
                recitations: (await sendAjaxRequest('get_all_recitations')).data || [],
                themeAyahs: (await sendAjaxRequest('get_all_theme_ayahs')).data || []
            };
            let activeHTML = '';
            let completedHTML = '';
            for (const goal of allGoals) {
                const {
                    progress,
                    progressText
                } = await calculateGoalProgress(goal, userData);
                const isNowComplete = progress >= 100 && (goal.type !== 'read_ayahs_daily' && goal.type !== 'listen_ayahs_daily' && goal.type !== 'recurring_surah_daily' && goal.type !== 'recurring_surah_weekly');
                if (isNowComplete && !goal.is_complete) {
                    goal.is_complete = true;
                    await sendAjaxRequest('update_goal_completion', {
                        id: goal.id,
                        isComplete: true
                    });
                }
                const goalCardHTML = createGoalCardHTML(goal, progress, progressText);
                if (goal.is_complete) {
                    completedHTML += goalCardHTML;
                } else {
                    activeHTML += goalCardHTML;
                }
            }
            activeList.innerHTML = activeHTML || '<p>No active goals. Add one above!</p>';
            completedList.innerHTML = completedHTML || '<p>No goals completed yet. Keep going!</p>';
            document.querySelectorAll('.delete-goal-btn').forEach(btn => btn?.addEventListener('click', handleDeleteGoal));
            document.querySelectorAll('.view-details-btn').forEach(btn => btn?.addEventListener('click', handleViewGoalDetails));
        }
        async function calculateGoalProgress(goal, userData) {
            let progress = 0;
            let progressText = '0/0';
            const allReadingLogs = userData.recitations;
            switch (goal.type) {
                case 'read_surah':
                case 'listen_surah': {
                    const total = surahAyahCounts[goal.target_surah];
                    const ayahsReadInSurah = new Set();
                    allReadingLogs.filter(r => r.surah === goal.target_surah).forEach(r => {
                        const start = r.ayah_start || 1;
                        const end = r.ayah_end || (r.ayah_start ? r.ayah_start : surahAyahCounts[r.surah]);
                        for (let i = start; i <= end; i++) {
                            ayahsReadInSurah.add(i);
                        }
                    });
                    const completed = ayahsReadInSurah.size;
                    progress = total > 0 ? (completed / total) * 100 : 100;
                    progressText = `${completed}/${total} Ayahs`;
                    break;
                }
                case 'read_quran':
                case 'listen_quran': {
                    const total = 6236;
                    const ayahsRead = new Set();
                    allReadingLogs.forEach(r => {
                        const start = r.ayah_start || 1;
                        const end = r.ayah_end || (r.ayah_start ? r.ayah_start : surahAyahCounts[r.surah]);
                        for (let i = start; i <= end; i++) {
                            ayahsRead.add(`${r.surah}:${i}`);
                        }
                    });
                    const completed = ayahsRead.size;
                    progress = (completed / total) * 100;
                    progressText = `${completed}/${total} Ayahs`;
                    break;
                }
                case 'read_ayahs_daily':
                case 'listen_ayahs_daily': {
                    const total = 7;
                    const dailyCounts = getDailyReadingCounts(allReadingLogs);
                    let completed = 0;
                    for (let i = 0; i < 7; i++) {
                        const d = new Date();
                        d.setDate(d.getDate() - i);
                        const dayKey = d.toISOString().split('T')[0];
                        if ((dailyCounts[dayKey] || 0) >= goal.target_count) {
                            completed++;
                        }
                    }
                    progress = (completed / total) * 100;
                    progressText = `${completed}/${total} Days Met`;
                    break;
                }
                case 'recurring_surah_weekly': {
                    const total = 4;
                    let completed = 0;
                    const recitationLogsForSurah = allReadingLogs.filter(r => r.surah === goal.target_surah);
                    for (let i = 0; i < total; i++) {
                        const checkDate = new Date();
                        checkDate.setDate(checkDate.getDate() - (i * 7));
                        const dayOfWeek = checkDate.getDay();
                        checkDate.setDate(checkDate.getDate() - (dayOfWeek - goal.target_day));
                        const dayKey = checkDate.toISOString().split('T')[0];
                        if (recitationLogsForSurah.some(log => log.log_date === dayKey)) {
                            completed++;
                        }
                    }
                    progress = (completed / total) * 100;
                    progressText = `${completed}/${total} Weeks Met`;
                    break;
                }
                case 'recurring_surah_daily': {
                    const total = 7;
                    let completed = 0;
                    const recitationLogsForSurah = allReadingLogs.filter(r => r.surah === goal.target_surah);
                    for (let i = 0; i < total; i++) {
                        const checkDate = new Date();
                        checkDate.setDate(checkDate.getDate() - i);
                        const dayKey = checkDate.toISOString().split('T')[0];
                        if (recitationLogsForSurah.some(log => log.log_date === dayKey)) {
                            completed++;
                        }
                    }
                    progress = (completed / total) * 100;
                    progressText = `${completed}/${total} Days Met`;
                    break;
                }
                case 'memorize_surah': {
                    const total = surahAyahCounts[goal.target_surah];
                    const completed = userData.hifz.filter(h => h.surah === goal.target_surah && h.status === 'memorized').length;
                    progress = total > 0 ? (completed / total) * 100 : 100;
                    progressText = `${completed}/${total} Ayahs`;
                    break;
                }
                case 'tafsir_juz': {
                    const ayahsInJuz = getAyahsForJuz(goal.target_juz);
                    const total = ayahsInJuz.length;
                    const tafsirKeys = new Set(userData.tafsir.map(t => `${t.surah}:${t.ayah}`));
                    const completed = ayahsInJuz.filter(a => tafsirKeys.has(`${a.surah}:${a.ayah}`)).length;
                    progress = total > 0 ? (completed / total) * 100 : 100;
                    progressText = `${completed}/${total} Ayahs`;
                    break;
                }
                case 'link_theme': {
                    const total = goal.target_count;
                    const completed = userData.themeAyahs.filter(ta => ta.theme_id === goal.target_theme).length;
                    progress = total > 0 ? (completed / total) * 100 : 100;
                    progressText = `${completed}/${total} Ayahs`;
                    break;
                }
            }
            return {
                progress: Math.min(progress, 100),
                progressText
            };
        }

        function getDailyReadingCounts(allReadingLogs) {
            const dailyCounts = {};
            allReadingLogs.forEach(r => {
                const dayKey = r.log_date;
                if (!dayKey) return;
                if (!dailyCounts[dayKey]) dailyCounts[dayKey] = 0;
                const start = r.ayah_start || 1;
                const end = r.ayah_end || (r.ayah_start ? r.ayah_start : (surahAyahCounts[r.surah] || 0));
                dailyCounts[dayKey] += Math.max(0, (end - start + 1));
            });
            return dailyCounts;
        }

        function createGoalCardHTML(goal, progress, progressText) {
            const progressPercent = progress.toFixed(0);
            const isHabitGoal = goal.type.includes('daily') || goal.type.includes('weekly');
            const deadlineText = !isHabitGoal && goal.target_date ? `Target: ${new Date(goal.target_date).toLocaleDateString()}` : 'Habit Goal';
            const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            let goalDescription = '';
            if (goal.type === 'read_surah') goalDescription = `Read Surah ${goal.target_surah}: ${surahNames[goal.target_surah - 1]}`;
            else if (goal.type === 'listen_surah') goalDescription = `Listen to Surah ${goal.target_surah}: ${surahNames[goal.target_surah - 1]}`;
            else if (goal.type === 'read_quran') goalDescription = `Complete Full Reading of Quran`;
            else if (goal.type === 'listen_quran') goalDescription = `Complete Full Listening of Quran`;
            else if (goal.type === 'read_ayahs_daily') goalDescription = `Read ${goal.target_count} Ayahs Daily`;
            else if (goal.type === 'listen_ayahs_daily') goalDescription = `Listen to ${goal.target_count} Ayahs Daily`;
            else if (goal.type === 'memorize_surah') goalDescription = `Memorize Surah ${goal.target_surah}: ${surahNames[goal.target_surah - 1]}`;
            else if (goal.type === 'tafsir_juz') goalDescription = `Tafsir for Juz ${goal.target_juz}`;
            else if (goal.type === 'link_theme') {
                goalDescription = `Link Ayahs to a theme (ID: ${goal.target_theme})`;
            } else if (goal.type === 'recurring_surah_weekly') goalDescription = `Recite Surah ${goal.target_surah} (${surahNames[goal.target_surah - 1]}) every ${days[goal.target_day]}`;
            else if (goal.type === 'recurring_surah_daily') goalDescription = `Recite Surah ${goal.target_surah} (${surahNames[goal.target_surah - 1]}) every day`;
            return `
                <div class="goal-card ${goal.is_complete ? 'completed' : ''}">
                    <div>
                        <h4>${goal.title}</h4>
                        <div class="goal-meta">${goalDescription} | ${deadlineText}</div>
                        <div class="progress-container">
                            <div class="progress-label"><span>Progress</span><span>${progressText}</span></div>
                            <div class="progress-bar-bg"><div class="progress-bar" style="width: ${progressPercent}%;">${progressPercent}%</div></div>
                        </div>
                    </div>
                    <div class="goal-actions">
                        <button class="view-details-btn" data-goal-id="${goal.id}">View Details</button>
                        <button class="delete-goal-btn" data-goal-id="${goal.id}">Delete</button>
                    </div>
                </div>`;
        }
        async function handleViewGoalDetails(e) {
            const goalId = parseInt(e.target.dataset.goalId);
            const goalResult = await sendAjaxRequest('get_goal', {
                id: goalId
            });
            const goal = goalResult.success ? goalResult.data : null;
            if (!goal) return;
            const modal = document.getElementById('goalDetailsModal');
            document.getElementById('modalGoalTitle').textContent = `Details for: ${goal.title}`;
            const contentEl = document.getElementById('modalGoalContent');
            contentEl.innerHTML = '<p>Loading details...</p>';
            modal.style.display = 'flex';
            let detailHTML = '<ul class="detail-list">';
            const allReadingLogsResult = await sendAjaxRequest('get_all_recitations');
            const allReadingLogs = allReadingLogsResult.success ? allReadingLogsResult.data : [];
            switch (goal.type) {
                case 'read_surah':
                case 'listen_surah':
                case 'read_quran':
                case 'listen_quran': {
                    const isFullQuran = goal.type.includes('quran');
                    const ayahsRead = new Set();
                    allReadingLogs.forEach(r => {
                        const start = r.ayah_start || 1;
                        const end = r.ayah_end || (r.ayah_start ? r.ayah_start : (surahAyahCounts[r.surah] || 0));
                        for (let i = start; i <= end; i++) {
                            if (isFullQuran || r.surah === goal.target_surah) {
                                ayahsRead.add(`${r.surah}:${i}`);
                            }
                        }
                    });
                    let ayahsToRead = [];
                    if (isFullQuran) {
                        detailHTML += `<li><p>Total unique Ayahs read/listened to: ${ayahsRead.size} / 6236</p></li>`;
                    } else {
                        for (let i = 1; i <= surahAyahCounts[goal.target_surah]; i++) ayahsToRead.push(`${goal.target_surah}:${i}`);
                        const remaining = ayahsToRead.filter(key => !ayahsRead.has(key));
                        if (remaining.length > 0) {
                            detailHTML += remaining.slice(0, 100).map(key => {
                                const [s, a] = key.split(':');
                                return `<li class="detail-item"><a onclick="jumpToTafsir(${s}, ${a})">Remaining: Surah ${s}:${a}</a></li>`;
                            }).join('');
                            if (remaining.length > 100) detailHTML += `<li>And ${remaining.length - 100} more...</li>`;
                        } else {
                            detailHTML += '<li><p>All Ayahs have been read/listened to. Congratulations!</p></li>';
                        }
                    }
                    break;
                }
                case 'read_ayahs_daily':
                case 'listen_ayahs_daily': {
                    const dailyCounts = getDailyReadingCounts(allReadingLogs);
                    for (let i = 0; i < 30; i++) {
                        const d = new Date();
                        d.setDate(d.getDate() - i);
                        const dayKey = d.toISOString().split('T')[0];
                        const count = dailyCounts[dayKey] || 0;
                        const isComplete = count >= goal.target_count;
                        detailHTML += `<li class="detail-item"><span>${d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })}</span><span class="detail-item-status status-${isComplete ? 'complete' : 'incomplete'}">${count} / ${goal.target_count} Ayahs</span></li>`;
                    }
                    break;
                }
                case 'recurring_surah_daily':
                case 'recurring_surah_weekly': {
                    const isWeekly = goal.type.includes('weekly');
                    const logsForSurah = allReadingLogs.filter(r => r.surah === goal.target_surah);
                    const daysToCheck = isWeekly ? 4 * 7 : 30;
                    for (let i = 0; i < daysToCheck; i++) {
                        const checkDate = new Date();
                        checkDate.setDate(checkDate.getDate() - i);
                        if (isWeekly && checkDate.getDay() !== goal.target_day) continue;
                        const dayKey = checkDate.toISOString().split('T')[0];
                        const wasMet = logsForSurah.some(log => log.log_date === dayKey);
                        detailHTML += `<li class="detail-item"><span>${checkDate.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' })}</span><span class="detail-item-status status-${wasMet ? 'complete' : 'incomplete'}">${wasMet ? 'Done' : 'Pending'}</span></li>`;
                    }
                    break;
                }
                case 'tafsir_juz': {
                    const ayahsInJuz = getAyahsForJuz(goal.target_juz);
                    const total = ayahsInJuz.length;
                    const tafsirResult = await sendAjaxRequest('get_all_tafsir');
                    const tafsirData = tafsirResult.success ? tafsirResult.data : [];
                    const tafsirKeys = new Set(tafsirData.map(t => `${t.surah}:${t.ayah}`));
                    const completed = ayahsInJuz.filter(a => tafsirKeys.has(`${a.surah}:${a.ayah}`)).length;
                    detailHTML += `<li><p>Tafsir completed for ${completed} / ${total} Ayahs in Juz ${goal.target_juz}.</p></li>`;
                    break;
                }
                case 'link_theme': {
                    const themeResult = await sendAjaxRequest('get_theme', {
                        theme_id: goal.target_theme
                    });
                    const themeName = themeResult.success && themeResult.data ? themeResult.data.name : `Theme ID ${goal.target_theme}`;
                    const linkedAyahsResult = await sendAjaxRequest('get_linked_ayahs_for_theme', {
                        theme_id: goal.target_theme
                    });
                    const linkedAyahs = linkedAyahsResult.success ? linkedAyahsResult.data : [];
                    const total = goal.target_count;
                    const completed = linkedAyahs.length;
                    detailHTML += `<li><p>Ayahs linked to "${themeName}": ${completed} / ${total}</p></li>`;
                    break;
                }
                default:
                    detailHTML += '<li><p>Detailed progress view is not available for this goal type yet.</p></li>';
            }
            detailHTML += '</ul>';
            contentEl.innerHTML = detailHTML;
        }
        async function handleDeleteGoal(e) {
            const goalId = parseInt(e.target.dataset.goalId);
            if (confirm("Are you sure you want to delete this goal?")) {
                const result = await sendAjaxRequest('delete_goal', {
                    id: goalId
                });
                if (result.success) {
                    await renderGoalsUI();
                } else {
                    alert('Failed to delete goal: ' + result.message);
                }
            }
        }
        async function jumpToTafsir(surah, ayah) {
            const modal = document.getElementById('goalDetailsModal');
            if (modal) modal.style.display = 'none';
            await loadAyah(surah, ayah);
            showSection('tafsir');
        }

        function getAyahsForJuz(juzNum) {
            const ayahs = [];
            const startJuzData = juzBoundariesData[juzNum - 1];
            const endJuzData = juzBoundariesData[juzNum] || {
                startSurah: 115,
                startAyah: 1
            };
            for (let s = startJuzData.startSurah; s < endJuzData.startSurah; s++) {
                const startAyah = (s === startJuzData.startSurah) ? startJuzData.startAyah : 1;
                for (let a = startAyah; a <= surahAyahCounts[s]; a++) {
                    ayahs.push({
                        surah: s,
                        ayah: a
                    });
                }
            }
            if (endJuzData.startSurah <= 114) {
                const startAyahLastSurah = (endJuzData.startSurah === startJuzData.startSurah) ? startJuzData.startAyah : 1;
                for (let a = startAyahLastSurah; a < endJuzData.startAyah; a++) {
                    ayahs.push({
                        surah: endJuzData.startSurah,
                        ayah: a
                    });
                }
            }
            return ayahs;
        }

        function patchShowSectionForGoals() {
            if (window.showSection.isPatchedForGoals) return;
            const originalShowSection = window.showSection;

            function showSection(sectionId) {
                originalShowSection.apply(this, arguments);
                if (sectionId === 'goals') {
                    if (isUserLoggedIn) {
                        renderGoalsUI();
                    } else {
                        document.getElementById('goals-list-active').innerHTML = '<p class="text-center">Please login to manage goals.</p>';
                        document.getElementById('goals-list-completed').innerHTML = '<p class="text-center">Please login to manage goals.</p>';
                        document.querySelector('.add-goal-form').innerHTML = '<p class="text-center">Please login to add goals.</p>';
                    }
                }
            };
            window.showSection.isPatchedForGoals = true;
        }
        document?.addEventListener('DOMContentLoaded', initializeGoalsModule);
    </script>
    <script>
        (function() {
            'use strict';
            const JSZIP_CDN_URL = 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js';
            let jszipLoaded = false;
            let dataFilesManifestCache = null;

            function loadJSZip() {
                return new Promise((resolve, reject) => {
                    if (typeof JSZip !== 'undefined') {
                        jszipLoaded = true;
                        resolve(window.JSZip);
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = JSZIP_CDN_URL;
                    script.onload = () => {
                        jszipLoaded = true;
                        resolve(window.JSZip);
                    };
                    script.onerror = () => reject(new Error('Failed to load JSZip library.'));
                    document.head.appendChild(script);
                });
            }
            async function fetchAndParseManifest() {
                return [];
            }

            function triggerDownload(blob, filename) {
                const downloadUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = downloadUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(downloadUrl);
            }
            async function handleZippedAppDownload(button) {
                const originalButtonContent = 'Zip 📦';
                button.disabled = true;
                button.innerHTML = 'Zipping... ⏳';
                try {
                    if (!jszipLoaded) {
                        await loadJSZip();
                    }
                    if (typeof JSZip === 'undefined') {
                        throw new Error("JSZip library failed to load correctly.");
                    }
                    const zip = new JSZip();
                    try {
                        const faviconResponse = await fetch('favicon.png');
                        if (faviconResponse.ok) {
                            const faviconBlob = await faviconResponse.blob();
                            zip.file('favicon.png', faviconBlob);
                        }
                    } catch (e) {
                        console.warn("Could not add favicon.png to zip:", e);
                    }
                    const zipBlob = await zip.generateAsync({
                        type: 'blob'
                    });
                    triggerDownload(zipBlob, 'NurAlQuran_OfflineApp.zip');
                    button.innerHTML = originalButtonContent;
                } catch (error) {
                    console.error('Error during zipped app download:', error);
                    alert(`Failed to prepare zipped app for download: ${error.message}. Check console for details.`);
                    button.innerHTML = 'Error! Retry Zip 📦';
                } finally {
                    button.disabled = false;
                }
            }
            async function handleUnzippedAppDownload(button) {
                const originalButtonContent = 'Files 📁';
                button.disabled = true;
                button.innerHTML = 'Preparing... ⏳';
                try {
                    triggerDownload(phpBlob, 'index.php');
                    await new Promise(resolve => setTimeout(resolve, 500));
                    try {
                        button.innerHTML = `Downloading favicon.png...`;
                        const faviconResponse = await fetch('favicon.png');
                        if (faviconResponse.ok) {
                            const faviconBlob = await faviconResponse.blob();
                            triggerDownload(faviconBlob, 'favicon.png');
                            await new Promise(resolve => setTimeout(resolve, 300));
                        }
                    } catch (e) {
                        console.warn("Could not download favicon.png:", e);
                    }
                    button.innerHTML = originalButtonContent;
                } catch (error) {
                    console.error('Error during unzipped app download:', error);
                    alert(`Failed to prepare unzipped files for download: ${error.message}. Check console for details.`);
                    button.innerHTML = 'Error! Retry Files 📁';
                } finally {
                    button.disabled = false;
                }
            }

            function createAndInsertDownloadButtons() {
                const quranHeading = document.getElementById('quran-heading');
                if (!quranHeading) {
                    console.warn('Target h2#quran-heading for download buttons not found yet. Retrying...');
                    setTimeout(createAndInsertDownloadButtons, 500);
                    return;
                }
                if (document.getElementById('downloadAppZippedBtn')) return;
                if (getComputedStyle(quranHeading).display !== 'flex') {
                    quranHeading.style.display = 'flex';
                    quranHeading.style.justifyContent = 'space-between';
                    quranHeading.style.alignItems = 'center';
                    quranHeading.style.width = '100%';
                }
                let textSpanContainer = quranHeading.querySelector('span#quranHeadingText');
                if (!textSpanContainer) {
                    textSpanContainer = document.createElement('span');
                    textSpanContainer.id = 'quranHeadingText';
                    while (quranHeading.firstChild && quranHeading.firstChild.id !== 'quranHeadingDownloadButtons') {
                        textSpanContainer.appendChild(quranHeading.firstChild);
                    }
                    quranHeading.insertBefore(textSpanContainer, quranHeading.firstChild);
                }
                const buttonsContainer = document.createElement('div');
                buttonsContainer.id = 'quranHeadingDownloadButtons';
                buttonsContainer.style.display = 'flex';
                buttonsContainer.style.gap = '8px';
                buttonsContainer.style.marginLeft = 'auto';
                buttonsContainer.style.flexShrink = '0';
                const commonButtonStyle = `
                padding: 5px 8px; 
                font-size: 0.85rem;  
                color: white;
                border: none;
                border-radius: var(--border-radius, 4px);
                cursor: pointer;
                vertical-align: middle;
                line-height: normal;
                white-space: nowrap; 
            `;
                const hoverEffect = (btn, originalColor, hoverColor) => {
                    btn?.addEventListener('mouseover', () => btn.style.backgroundColor = hoverColor);
                    btn?.addEventListener('mouseout', () => btn.style.backgroundColor = originalColor);
                };
                const downloadZippedButton = document.createElement('button');
                downloadZippedButton.id = 'downloadAppZippedBtn';
                downloadZippedButton.innerHTML = 'Zip 📦';
                downloadZippedButton.title = 'Download App & Data (Zipped)';
                downloadZippedButton.style.cssText = commonButtonStyle;
                downloadZippedButton.style.backgroundColor = 'var(--color-success, #28a745)';
                hoverEffect(downloadZippedButton, 'var(--color-success, #28a745)', 'var(--color-accent-dark, #218838)');
                downloadZippedButton?.addEventListener('click', () => handleZippedAppDownload(downloadZippedButton));
                const downloadUnzippedButton = document.createElement('button');
                downloadUnzippedButton.id = 'downloadAppUnzippedBtn';
                downloadUnzippedButton.innerHTML = 'Files 📁';
                downloadUnzippedButton.title = 'Download App & Data (Individual Files)';
                downloadUnzippedButton.style.cssText = commonButtonStyle;
                downloadUnzippedButton.style.backgroundColor = 'var(--color-accent, #007bff)';
                hoverEffect(downloadUnzippedButton, 'var(--color-accent, #007bff)', 'var(--color-accent-dark, #0056b3)');
                downloadUnzippedButton?.addEventListener('click', () => handleUnzippedAppDownload(downloadUnzippedButton));
                buttonsContainer.appendChild(downloadZippedButton);
                buttonsContainer.appendChild(downloadUnzippedButton);
                quranHeading.appendChild(buttonsContainer);
            }
            if (document.readyState === 'loading') {
                document?.addEventListener('DOMContentLoaded', createAndInsertDownloadButtons);
            } else {
                createAndInsertDownloadButtons();
            }
        })();
    </script>
    <script>
        (function() {
            'usese strict';
            const sectionShortcuts = {
                'Digit1': 'quran',
                'Digit2': 'tafsir',
                'Digit3': 'themes',
                'Digit4': 'roots',
                'Digit5': 'recitation',
                'Digit6': 'hifz',
                'Digit7': 'search',
                'Digit8': 'data',
                'Digit9': 'goals'
            };

            function goToNextAyah() {
                if (typeof currentSurah === 'undefined' || typeof currentAyah === 'undefined' || typeof surahAyahCounts === 'undefined' || typeof loadAyah !== 'function') {
                    return;
                }
                let nextS = currentSurah;
                let nextA = currentAyah + 1;
                if (nextA > surahAyahCounts[nextS]) {
                    if (nextS < 114) {
                        nextS++;
                        nextA = 1;
                    } else {
                        return;
                    }
                }
                loadAyah(nextS, nextA);
            }

            function goToPrevAyah() {
                if (typeof currentSurah === 'undefined' || typeof currentAyah === 'undefined' || typeof surahAyahCounts === 'undefined' || typeof loadAyah !== 'function') {
                    return;
                }
                let prevS = currentSurah;
                let prevA = currentAyah - 1;
                if (prevA < 1) {
                    if (prevS > 1) {
                        prevS--;
                        prevA = surahAyahCounts[prevS];
                    } else {
                        return;
                    }
                }
                loadAyah(prevS, prevA);
            }

            function handleGlobalKeyDown(event) {
                if (['Control', 'Shift', 'Alt', 'Meta'].includes(event.key)) {
                    return;
                }
                const activeEl = document.activeElement;
                const isTyping = activeEl && ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName.toUpperCase());
                const isModalOpen = !!document.querySelector('.modal[style*="display: flex"]');
                const isReaderOpen = !!document.getElementById('fullScreenReaderOverlay');
                if (isModalOpen || isReaderOpen) {
                    return;
                }
                if (isTyping && !(event.ctrlKey && event.key.toLowerCase() === 's' && activeEl.id === 'tafsir-notes')) {
                    return;
                }
                if (event.ctrlKey && !event.shiftKey && !event.altKey && event.key.toLowerCase() === 's') {
                    event.preventDefault();
                    const activeSectionId = document.querySelector('.section.active')?.id;
                    if (activeSectionId === 'tafsir') {
                        document.getElementById('save-tafsir-btn')?.click();
                    } else if (activeSectionId === 'roots') {
                        document.getElementById('save-root-notes-btn')?.click();
                    } else if (activeSectionId === 'recitation') {
                        document.getElementById('save-recitation-btn')?.click();
                    }
                } else if (event.ctrlKey && event.shiftKey && sectionShortcuts[event.code]) {
                    event.preventDefault();
                    const sectionId = sectionShortcuts[event.code];
                    if (typeof showSection === 'function') {
                        showSection(sectionId);
                    } else {}
                } else if (event.ctrlKey && !isTyping && (event.key === 'ArrowRight' || event.key === 'ArrowLeft')) {
                    event.preventDefault();
                    if (event.key === 'ArrowRight') {
                        goToNextAyah();
                    } else {
                        goToPrevAyah();
                    }
                } else {}
            }
            document?.addEventListener('DOMContentLoaded', () => {
                document?.addEventListener('keydown', handleGlobalKeyDown);
            });
        })();
    </script>
    <script>
        (function() {
            'use strict';
            const HTML2CANVAS_CDN = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            let html2canvasLoaded = false;

            function loadHtml2Canvas() {
                return new Promise((resolve, reject) => {
                    if (typeof html2canvas !== 'undefined') {
                        html2canvasLoaded = true;
                        resolve();
                        return;
                    }
                    const script = document.createElement('script');
                    script.src = HTML2CANVAS_CDN;
                    script.onload = () => {
                        html2canvasLoaded = true;
                        resolve();
                    };
                    script.onerror = () => reject(new Error("Failed to load image generation library."));
                    document.head.appendChild(script);
                });
            }
            async function createImageContainerForCanvas(ayahData, selectedTranslations, wordByWordOptions) {
                const imageContainer = document.createElement('div');
                const themeBgPrimary = getComputedStyle(document.body).getPropertyValue('--color-bg-primary').trim();
                const themeBgSecondary = getComputedStyle(document.body).getPropertyValue('--color-bg-secondary').trim();
                const themeBorder = getComputedStyle(document.body).getPropertyValue('--color-border').trim();
                const themeTextPrimary = getComputedStyle(document.body).getPropertyValue('--color-text-primary').trim();
                const themeTextSecondary = getComputedStyle(document.body).getPropertyValue('--color-text-secondary').trim();
                imageContainer.style.cssText = `
                width: 800px;
                padding: 40px;
                border: 1px solid ${themeBorder};
                background: linear-gradient(135deg, ${themeBgPrimary} 0%, ${themeBgSecondary} 100%);
                font-family: var(--font-general);
                color: ${themeTextPrimary};
                display: flex;
                flex-direction: column;
                gap: 20px;
            `;
                if (ayahData.surah !== 1 && ayahData.surah !== 9 && ayahData.ayah === 1) {
                    const bismillah = document.createElement('div');
                    bismillah.textContent = 'بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ';
                    bismillah.style.cssText = "font-family: var(--font-arabic); font-size: 2em; text-align: center; margin-bottom: 20px;";
                    imageContainer.appendChild(bismillah);
                }
                const ref = document.createElement('h2');
                ref.textContent = `Surah ${surahNames[ayahData.surah - 1]} (${ayahData.surah}:${ayahData.ayah})`;
                ref.style.cssText = `text-align: center; color: ${themeTextSecondary}; margin-bottom: 10px; font-weight: normal;`;
                imageContainer.appendChild(ref);
                const arabicText = document.createElement('div');
                arabicText.textContent = ayahData.arabic;
                arabicText.style.cssText = "font-family: var(--font-arabic); font-size: 3.2em; text-align: center; direction: rtl; line-height: 1.8; margin-bottom: 20px;";
                imageContainer.appendChild(arabicText);
                selectedTranslations.forEach(trans => {
                    const divider = document.createElement('hr');
                    divider.style.cssText = "border: none; border-top: 1px solid var(--color-border); opacity: 0.5;";
                    imageContainer.appendChild(divider);
                    const transContainer = document.createElement('div');
                    transContainer.innerHTML = `
                    <h4 style="color:${themeTextSecondary}; margin-bottom:5px; font-size:1em;">${trans.config.label}</h4>
                    <p style="font-family:var(--font-${trans.config.key}); font-size:1.4em; direction:${trans.config.dir}; text-align:left; line-height:1.6; margin:0;">${trans.text}</p>
                `;
                    imageContainer.appendChild(transContainer);
                });
                if (wordByWordOptions.include && wordByWordOptions.languages.length > 0) {
                    const table = await createWordByWordTable(ayahData, wordByWordOptions.languages);
                    if (table) {
                        const divider = document.createElement('hr');
                        divider.style.cssText = "border: none; border-top: 1px solid var(--color-border); margin-top: 20px;";
                        imageContainer.appendChild(divider);
                        const tableHeader = document.createElement('h4');
                        tableHeader.textContent = "Word-by-Word Breakdown";
                        tableHeader.style.cssText = `color:${themeTextSecondary}; margin: 20px 0 0 0; text-align: center;`;
                        imageContainer.appendChild(tableHeader);
                        imageContainer.appendChild(table);
                    }
                }
                const watermark = document.createElement('div');
                watermark.textContent = 'Generated by Nur-Ul-Quran App';
                watermark.style.cssText = `margin-top: 30px; text-align: center; font-size: 12px; color: ${themeTextSecondary}; opacity: 0.6;`;
                imageContainer.appendChild(watermark);
                imageContainer.style.position = 'absolute';
                imageContainer.style.left = '-9999px';
                document.body.appendChild(imageContainer);
                return imageContainer;
            }
            async function createWordByWordTable(ayahData, languageKeys) {
                const propertyMap = {
                    'urdu': 'ur_meaning',
                    'english': 'en_meaning',
                    'pashto': 'pashto_text',
                    'Bangali': 'bn_meaning'
                };
                const metadataResult = await sendAjaxRequest('get_word_metadata_for_ayah', {
                    surah: ayahData.surah,
                    ayah: ayahData.ayah
                });
                const metadata = metadataResult.success ? metadataResult.data : [];
                if (!metadata || metadata.length === 0) return null;
                const wordIds = metadata.map(m => m.word_id);
                const translationPromises = wordIds.map(id => sendAjaxRequest('get_word_translation', {
                    word_id: id
                }));
                const translationResults = await Promise.all(translationPromises);
                const translations = translationResults.map(res => res.success ? res.data : {});
                const arabicWords = ayahData.arabic.split(/\s+/).filter(w => w);
                const table = document.createElement('table');
                table.className = 'word-table';
                const thead = table.createTHead();
                const headerRow = thead.insertRow();
                headerRow.innerHTML = '<th>Word (Arabic)</th>';
                languageKeys.forEach(key => {
                    const config = getTranslationConfig(key);
                    headerRow.innerHTML += `<th>${config.label}</th>`;
                });
                const tbody = table.createTBody();
                for (let i = 0; i < arabicWords.length; i++) {
                    const row = tbody.insertRow();
                    const arabicCell = row.insertCell();
                    arabicCell.className = 'arabic-word-cell';
                    arabicCell.textContent = arabicWords[i];
                    const wordTrans = translations[i] || {};
                    languageKeys.forEach(key => {
                        const transCell = row.insertCell();
                        const config = getTranslationConfig(key);
                        const propertyName = propertyMap[key];
                        transCell.textContent = wordTrans[propertyName] || 'N/A';
                        transCell.style.fontFamily = `var(--font-${config.key})`;
                        transCell.style.direction = config.dir;
                    });
                }
                return table;
            }
            async function generateFinalImage() {
                const modal = document.getElementById('ayahImageModal');
                const createBtn = document.getElementById('createImageBtn');
                createBtn.disabled = true;
                createBtn.textContent = 'Creating...';
                try {
                    const surah = parseInt(modal.dataset.surah, 10);
                    const ayah = parseInt(modal.dataset.ayah, 10);
                    const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                        surah: surah,
                        ayah: ayah
                    });
                    const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                    if (!ayahData) throw new Error(`Could not retrieve data for Ayah ${surah}:${ayah}.`);
                    const selectedTranslations = Array.from(document.querySelectorAll('#imageTranslationOptions input:checked'))
                        .map(cb => ({
                            langKey: cb.value,
                            text: ayahData[cb.value],
                            config: getTranslationConfig(cb.value)
                        }));
                    const wordByWordOptions = {
                        include: document.getElementById('includeWordByWordTable').checked,
                        languages: Array.from(document.querySelectorAll('#wordByWordLanguageOptions input:checked')).map(cb => cb.value)
                    };
                    const imageSourceContainer = await createImageContainerForCanvas(ayahData, selectedTranslations, wordByWordOptions);
                    const canvas = await html2canvas(imageSourceContainer, {
                        useCORS: true,
                        backgroundColor: null,
                        scale: 2
                    });
                    const link = document.createElement('a');
                    link.download = `Ayah_${surah}_${ayah}.png`;
                    link.href = canvas.toDataURL("image/png");
                    link.click();
                    document.body.removeChild(imageSourceContainer);
                    modal.style.display = 'none';
                } catch (error) {
                    console.error("Error generating final image:", error);
                    alert("Sorry, an error occurred. Please check the console for details.");
                } finally {
                    createBtn.disabled = false;
                    createBtn.textContent = 'Create & Download Image';
                }
            }
            async function openImageSettingsModal(event) {
                const button = event.currentTarget;
                button.disabled = true;
                try {
                    if (!html2canvasLoaded) await loadHtml2Canvas();
                    const surah = parseInt(button.dataset.surah, 10);
                    const ayah = parseInt(button.dataset.ayah, 10);
                    const modal = document.getElementById('ayahImageModal');
                    modal.dataset.surah = surah;
                    modal.dataset.ayah = ayah;
                    const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                        surah: surah,
                        ayah: ayah
                    });
                    const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                    if (!ayahData) throw new Error(`Could not find data for Ayah ${surah}:${ayah}.`);
                    const availableTranslations = allLanguagesConfig.filter(f => ayahData[f.key]);
                    const currentlySelectedInUI = document.getElementById('translation-select').value;
                    const fullTransOptions = document.getElementById('imageTranslationOptions');
                    const wbywLangOptions = document.getElementById('wordByWordLanguageOptions');
                    fullTransOptions.innerHTML = '';
                    wbywLangOptions.innerHTML = '';
                    availableTranslations.forEach(config => {
                        fullTransOptions.innerHTML += `<label><input type="checkbox" value="${config.key}" ${config.key === currentlySelectedInUI ? 'checked' : ''}> ${config.label}</label>`;
                        wbywLangOptions.innerHTML += `<label><input type="checkbox" class="wbyw-lang-option" value="${config.key}" ${config.key === currentlySelectedInUI ? 'checked' : ''}> ${config.label}</label>`;
                    });
                    const includeWbyWCheckbox = document.getElementById('includeWordByWordTable');
                    includeWbyWCheckbox.checked = false;
                    wbywLangOptions.style.display = 'none';
                    includeWbyWCheckbox.onchange = () => {
                        wbywLangOptions.style.display = includeWbyWCheckbox.checked ? 'flex' : 'none';
                    };
                    const createBtn = document.getElementById('createImageBtn');
                    const newCreateBtn = createBtn.cloneNode(true);
                    createBtn.parentNode.replaceChild(newCreateBtn, createBtn);
                    newCreateBtn?.addEventListener('click', generateFinalImage);
                    modal.style.display = 'flex';
                } catch (error) {
                    console.error("Error opening image settings modal:", error);
                    alert(error.message);
                } finally {
                    button.disabled = false;
                }
            }

            function addShareButtonToAyah(surah, ayah) {
                const ayahDiv = document.querySelector(`.ayah[data-surah="${surah}"][data-ayah="${ayah}"]`);
                if (!ayahDiv || ayahDiv.querySelector('.generate-image-btn')) return;
                const actionsDiv = document.createElement('div');
                actionsDiv.className = 'ayah-actions';
                const button = document.createElement('button');
                button.className = 'generate-image-btn';
                button.innerHTML = '🖼️';
                button.dataset.surah = surah;
                button.dataset.ayah = ayah;
                button?.addEventListener('click', openImageSettingsModal);
                actionsDiv.appendChild(button);
                ayahDiv.appendChild(actionsDiv);
            }

            function patchAndApplyToInitialView() {
                if (window.renderSingleAyahView && !window.renderSingleAyahView.isPatchedForSharing) {
                    const originalFunction = window.renderSingleAyahView;
                    async function renderSingleAyahView(...args) {
                        await originalFunction.apply(this, args);
                        const [surah, ayah] = args;
                        addShareButtonToAyah(surah, ayah);
                    };
                    window.renderSingleAyahView.isPatchedForSharing = true;
                    console.log("renderSingleAyahView patched for image generation (v3.1).");
                }
                const initialAyahDiv = document.querySelector('#quran-display .ayah[data-surah]');
                if (initialAyahDiv) {
                    const surah = parseInt(initialAyahDiv.dataset.surah, 10);
                    const ayah = parseInt(initialAyahDiv.dataset.ayah, 10);
                    if (!isNaN(surah) && !isNaN(ayah)) {
                        addShareButtonToAyah(surah, ayah);
                    }
                }
            }
            document?.addEventListener('DOMContentLoaded', patchAndApplyToInitialView);
        })();
    </script>
    <script>
        (function() {
            'use strict';

            function parseHash() {
                try {
                    const hash = window.location.hash.substring(1);
                    if (!hash) return null;
                    const parts = hash.split(':');
                    if (parts.length !== 2) return null;
                    const surah = parseInt(parts[0], 10);
                    const ayah = parseInt(parts[1], 10);
                    if (isNaN(surah) || isNaN(ayah)) return null;
                    if (surah < 1 || surah > 114) return null;
                    if (ayah < 1 || ayah > surahAyahCounts[surah]) return null;
                    console.log(`[HashNav] Parsed valid location from hash: S${surah}:A${ayah}`);
                    return {
                        surah,
                        ayah
                    };
                } catch (e) {
                    console.error("[HashNav] Error parsing hash:", e);
                    return null;
                }
            }

            function patchLoadAyahForHashUpdate() {
                if (window.loadAyah && !window.loadAyah.isPatchedForHashNav) {
                    const originalFunction = window.loadAyah;
                    async function loadAyah(...args) {
                        await originalFunction.apply(this, args);
                        const [surah, ayah] = args;
                        const newHash = `${surah}:${ayah}`;
                        if (window.location.hash !== `#${newHash}`) {
                            history.replaceState(null, '', `#${newHash}`);
                        }
                    };
                    window.loadAyah.isPatchedForHashNav = true;
                    console.log("[HashNav] `loadAyah` has been patched to update the URL hash.");
                }
            }

            function handleInitialLoadFromHash() {
                const initialLocation = parseHash();
                if (initialLocation) {
                    if (typeof window.currentSurah !== 'undefined' && typeof window.currentAyah !== 'undefined') {
                        window.currentSurah = initialLocation.surah;
                        window.currentAyah = initialLocation.ayah;
                        console.log(`[HashNav] Overriding initial location to S${window.currentSurah}:A${window.currentAyah}`);
                    }
                }
            }
            handleInitialLoadFromHash();
            document?.addEventListener('DOMContentLoaded', () => {
                setTimeout(patchLoadAyahForHashUpdate, 100);
                window?.addEventListener('hashchange', () => {
                    const newLocation = parseHash();
                    if (newLocation && (newLocation.surah !== window.currentSurah || newLocation.ayah !== window.currentAyah)) {
                        console.log('[HashNav] Hash changed by user, loading new location.');
                        window.loadAyah(newLocation.surah, newLocation.ayah);
                    }
                });
            });
        })();
    </script>
    <script>
        (function() {
            'use strict';
            let themeNetworkInstance = null;
            async function generateThemeGraph() {
                const graphContainer = document.getElementById('theme-network-graph');
                const filterSelect = document.getElementById('themeGraphFilterSelect');
                const layoutSelect = document.getElementById('themeGraphLayoutSelect');
                if (!graphContainer || !filterSelect || !layoutSelect) return;
                graphContainer.innerHTML = '<p style="text-align:center; padding: 20px;">Loading graph data...</p>';
                if (themeNetworkInstance) {
                    themeNetworkInstance.destroy();
                    themeNetworkInstance = null;
                }
                try {
                    const themesResult = await sendAjaxRequest('get_all_themes');
                    const themeAyahsResult = await sendAjaxRequest('get_all_theme_ayahs');
                    const themes = themesResult.success ? themesResult.data : [];
                    const themeAyahs = themeAyahsResult.success ? themeAyahsResult.data : [];
                    const selectedFilterValue = filterSelect.value;
                    filterSelect.innerHTML = '<option value="all">Show All Themes</option>';
                    themes.forEach(theme => {
                        const option = document.createElement('option');
                        option.value = theme.id;
                        option.textContent = theme.name;
                        filterSelect.appendChild(option);
                    });
                    filterSelect.value = selectedFilterValue;
                    if (!themes || themes.length === 0) {
                        graphContainer.innerHTML = '<p style="text-align:center; padding: 20px;">No themes created yet.</p>';
                        return;
                    }
                    const nodes = new vis.DataSet();
                    const edges = new vis.DataSet();
                    const selectedThemeId = filterSelect.value === 'all' ? null : parseInt(filterSelect.value, 10);
                    const themesToDisplay = selectedThemeId ? themes.filter(t => t.id === selectedThemeId) : themes;
                    const linksToDisplay = selectedThemeId ? themeAyahs.filter(l => l.theme_id === selectedThemeId) : themeAyahs;
                    if (themesToDisplay.length === 0 && selectedThemeId) {
                        graphContainer.innerHTML = '<p style="text-align:center; padding: 20px;">Selected theme has no linked ayahs.</p>';
                        return;
                    }
                    const bodyStyles = getComputedStyle(document.body);
                    const themeNodeBg = bodyStyles.getPropertyValue('--color-accent-dark').trim();
                    const themeNodeBorder = bodyStyles.getPropertyValue('--color-accent').trim();
                    const ayahNodeBorder = bodyStyles.getPropertyValue('--color-border').trim();
                    const ayahNodeFontColor = bodyStyles.getPropertyValue('--color-text-primary').trim();
                    const ayahNodeHighlightBg = bodyStyles.getPropertyValue('--color-highlight').trim();
                    const generalFont = bodyStyles.getPropertyValue('--font-general').trim() || 'arial';
                    const arabicFont = bodyStyles.getPropertyValue('--font-arabic').trim() || 'tahoma';
                    const ayahNodeSolidBg = bodyStyles.getPropertyValue('--color-bg-primary').trim();
                    themesToDisplay.forEach(theme => {
                        nodes.add({
                            id: `theme_${theme.id}`,
                            label: theme.name,
                            shape: 'box',
                            color: {
                                background: themeNodeBg,
                                border: themeNodeBorder,
                                highlight: {
                                    background: themeNodeBorder,
                                    border: themeNodeBg
                                }
                            },
                            font: {
                                color: 'white',
                                size: 18,
                                face: generalFont
                            },
                            mass: 3,
                            type: 'theme'
                        });
                    });
                    if (linksToDisplay && linksToDisplay.length > 0) {
                        for (const link of linksToDisplay) {
                            const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                                surah: link.surah,
                                ayah: link.ayah
                            });
                            const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                            const arabicText = ayahData ? ayahData.arabic.substring(0, 40) + '...' : `[Ayah ${link.surah}:${link.ayah}]`;
                            const nodeId = `ayah_${link.surah}_${link.ayah}_link${link.id}`;
                            nodes.add({
                                id: nodeId,
                                label: `S${link.surah}:${link.ayah}\n${arabicText}`,
                                shape: 'ellipse',
                                color: {
                                    background: ayahNodeSolidBg,
                                    border: ayahNodeBorder,
                                    highlight: {
                                        background: ayahNodeHighlightBg,
                                        border: themeNodeBorder
                                    }
                                },
                                font: {
                                    color: ayahNodeFontColor,
                                    multi: true,
                                    face: arabicFont
                                },
                                type: 'ayah',
                                surah: link.surah,
                                ayah: link.ayah
                            });
                            edges.add({
                                from: `theme_${link.theme_id}`,
                                to: nodeId,
                                arrows: 'to'
                            });
                        }
                    }
                    const data = {
                        nodes: nodes,
                        edges: edges
                    };
                    const selectedLayout = layoutSelect.value;
                    let options = {
                        interaction: {
                            hover: true,
                            navigationButtons: true,
                            keyboard: true
                        },
                        nodes: {
                            borderWidth: 2,
                            shadow: true
                        },
                        edges: {
                            width: 1.5,
                            color: {
                                inherit: 'from'
                            }
                        },
                        physics: {
                            enabled: true
                        }
                    };
                    if (selectedLayout === 'barnesHut') {
                        options.physics.solver = 'barnesHut';
                        options.physics.barnesHut = {
                            gravitationalConstant: -20000,
                            centralGravity: 0.1,
                            springLength: 250
                        };
                    } else if (selectedLayout.startsWith('hierarchical')) {
                        options.physics.enabled = false;
                        options.layout = {
                            hierarchical: {
                                enabled: true,
                                direction: selectedLayout.split('-')[1].toUpperCase(),
                                sortMethod: 'hubsize',
                                nodeSpacing: 150,
                                treeSpacing: 250
                            }
                        };
                    } else if (selectedLayout === 'improvedLayout') {
                        options.physics.enabled = false;
                        options.layout = {
                            improvedLayout: true
                        };
                    }
                    await document.fonts.ready;
                    console.log("[ThemeGraph] Fonts ready, rendering network with concrete color values.");
                    themeNetworkInstance = new vis.Network(graphContainer, data, options);
                    themeNetworkInstance.on("click", function(params) {
                        if (params.nodes.length > 0) {
                            const nodeId = params.nodes[0];
                            const clickedNode = nodes.get(nodeId);
                            if (clickedNode && clickedNode.type === 'ayah') {
                                if (typeof loadAyah === 'function' && typeof showSection === 'function') {
                                    loadAyah(clickedNode.surah, clickedNode.ayah);
                                    showSection('quran');
                                }
                            }
                        }
                    });
                } catch (error) {
                    console.error("Error generating theme graph:", error);
                    graphContainer.innerHTML = `<p style="text-align:center; color:red; padding:20px;">Failed to generate graph: ${error.message}</p>`;
                }
            }

            function setupThemeGraphControls() {
                const filterSelect = document.getElementById('themeGraphFilterSelect');
                const layoutSelect = document.getElementById('themeGraphLayoutSelect');
                const applyBtn = document.getElementById('regenerateThemeGraphBtn');
                if (!filterSelect || !layoutSelect || !applyBtn) {
                    console.error("Could not find all theme graph control elements.");
                    return;
                }
                filterSelect?.addEventListener('change', generateThemeGraph);
                layoutSelect?.addEventListener('change', generateThemeGraph);
                applyBtn?.addEventListener('click', generateThemeGraph);
                populateThemeFilterDropdown();
            }
            async function populateThemeFilterDropdown() {
                const filterSelect = document.getElementById('themeGraphFilterSelect');
                if (!filterSelect) return;
                try {
                    const result = await sendAjaxRequest('get_all_themes');
                    const themes = result.success ? result.data : [];
                    const firstOption = filterSelect.options[0];
                    filterSelect.innerHTML = '';
                    filterSelect.appendChild(firstOption);
                    themes.forEach(theme => {
                        const option = document.createElement('option');
                        option.value = theme.id;
                        option.textContent = theme.name;
                        filterSelect.appendChild(option);
                    });
                } catch (e) {
                    console.error("Could not populate theme filter:", e);
                }
            }

            function setupThemeViewSwitcher() {
                document.querySelectorAll('input[name="theme-view-mode"]').forEach(radio => {
                    radio?.addEventListener('change', function() {
                        const formView = document.getElementById('theme-form-view');
                        const graphView = document.getElementById('theme-graph-view');
                        if (this.value === 'graph') {
                            formView.classList.remove('active-view');
                            graphView.classList.add('active-view');
                            setupThemeGraphControls();
                            generateThemeGraph();
                        } else {
                            graphView.classList.remove('active-view');
                            formView.classList.add('active-view');
                            if (themeNetworkInstance) {
                                themeNetworkInstance.destroy();
                                themeNetworkInstance = null;
                            }
                        }
                    });
                });
            }
            document?.addEventListener('DOMContentLoaded', setupThemeViewSwitcher);
        })();
    </script>
    <script>
        (function() {
            'use strict';
            let observer = null;

            function getQuickActionsHTML() {
                return `
            <button class="ayah-actions-toggle">⚙️</button>
            <div class="ayah-quick-actions">
                <button class="action-icon" data-action="tafsir" title="Add/Edit Tafsir">📝</button>
                <button class="action-icon" data-action="theme" title="Link to Theme">🔗</button>
                <button class="action-icon" data-action="recitation" title="Log Recitation">🎧</button>
                <button class="action-icon" data-action="hifz" title="Set Hifz Status">❤️</button>
            </div>
        `;
            }

            function injectActionsIntoAyahDiv(ayahDiv) {
                if (!ayahDiv || !(ayahDiv instanceof Element) || ayahDiv.querySelector('.ayah-quick-actions')) {
                    return;
                }
                let surah, ayah;
                surah = ayahDiv.dataset.surah;
                ayah = ayahDiv.dataset.ayah;
                if (!surah || !ayah) {
                    if (ayahDiv.id) {
                        const match = ayahDiv.id.match(/s(\d+)a(\d+)/);
                        if (match && match.length === 3) {
                            surah = match[1];
                            ayah = match[2];
                            ayahDiv.dataset.surah = surah;
                            ayahDiv.dataset.ayah = ayah;
                        }
                    }
                }
                if (surah && ayah && isUserLoggedIn) {
                    ayahDiv.insertAdjacentHTML('beforeend', getQuickActionsHTML());
                } else if (surah && ayah && !isUserLoggedIn) {} else {
                    console.warn("[QuickActions] Could not determine Surah/Ayah for an .ayah div, skipping.", ayahDiv);
                }
            }
            async function openTafsirModal(surah, ayah) {
                if (!isUserLoggedIn) {
                    alert('Login to add Tafsir notes.');
                    return;
                }
                const modal = document.getElementById('tafsirQuickModal');
                modal.dataset.surah = surah;
                modal.dataset.ayah = ayah;
                const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                const tafsirDataResult = await sendAjaxRequest('get_tafsir', {
                    surah: surah,
                    ayah: ayah
                });
                const tafsirNotes = tafsirDataResult.success ? tafsirDataResult.notes : '';
                modal.querySelector('.modal-ayah-ref').textContent = `${surah}:${ayah}`;
                modal.querySelector('.ayah-context').textContent = ayahData?.arabic || 'Ayah text not found.';
                modal.querySelector('#quickTafsirText').value = tafsirNotes;
                modal.style.display = 'flex';
                modal.querySelector('#quickTafsirText').focus();
                document.getElementById('saveQuickTafsirBtn').onclick = async () => {
                    const notes = document.getElementById('quickTafsirText').value;
                    const result = await sendAjaxRequest('save_tafsir', {
                        surah,
                        ayah,
                        notes
                    });
                    const statusEl = document.getElementById('quickTafsirStatus');
                    if (result.success) {
                        statusEl.textContent = "Saved!";
                        setTimeout(() => {
                            statusEl.textContent = "";
                            modal.style.display = 'none';
                        }, 1200);
                    } else {
                        statusEl.textContent = `Failed: ${result.message}`;
                        statusEl.style.color = 'var(--color-error)';
                    }
                };
            }
            async function openThemeModal(surah, ayah) {
                if (!isUserLoggedIn) {
                    alert('Login to link to themes.');
                    return;
                }
                const modal = document.getElementById('themeQuickModal');
                modal.dataset.surah = surah;
                modal.dataset.ayah = ayah;
                const [themesResult, linkedThemesForAyahResult, ayahDataResult] = await Promise.all([
                    sendAjaxRequest('get_all_themes'),
                    sendAjaxRequest('get_linked_ayahs_for_ayah', {
                        surah: surah,
                        ayah: ayah
                    }),
                    sendAjaxRequest('load_quran_ayah', {
                        surah: surah,
                        ayah: ayah
                    })
                ]);
                const themes = themesResult.success ? themesResult.data : [];
                const linkedThemesForAyah = linkedThemesForAyahResult.success ? linkedThemesForAyahResult.data : [];
                const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                modal.querySelector('.modal-ayah-ref').textContent = `${surah}:${ayah}`;
                modal.querySelector('.ayah-context').textContent = ayahData?.arabic || 'Ayah text not found.';
                const select = document.getElementById('quickThemeSelect');
                select.innerHTML = '';
                const linkedThemeIds = new Set(linkedThemesForAyah.map(l => l.theme_id));
                themes.forEach(theme => {
                    const option = document.createElement('option');
                    option.value = theme.id;
                    option.textContent = theme.name;
                    if (linkedThemeIds.has(theme.id)) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
                modal.style.display = 'flex';
                document.getElementById('saveQuickThemeBtn').onclick = async () => {
                    const selectedThemeIds = new Set(Array.from(select.selectedOptions).map(opt => parseInt(opt.value)));
                    let allSuccess = true;
                    for (const link of linkedThemesForAyah) {
                        if (!selectedThemeIds.has(link.theme_id)) {
                            const result = await sendAjaxRequest('unlink_ayah_from_theme', {
                                link_id: link.id
                            });
                            if (!result.success) allSuccess = false;
                        }
                    }
                    for (const themeId of selectedThemeIds) {
                        if (!linkedThemeIds.has(themeId)) {
                            const result = await sendAjaxRequest('link_ayah_to_theme', {
                                theme_id: themeId,
                                surah,
                                ayah,
                                notes: ''
                            });
                            if (!result.success) allSuccess = false;
                        }
                    }
                    if (allSuccess) {
                        modal.style.display = 'none';
                    } else {
                        alert('Some theme links failed to save/update. Check console.');
                    }
                };
            }
            async function openRecitationModal(surah, ayah) {
                if (!isUserLoggedIn) {
                    alert('Login to log recitations.');
                    return;
                }
                const modal = document.getElementById('recitationQuickModal');
                modal.dataset.surah = surah;
                modal.dataset.ayah = ayah;
                const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                    surah: surah,
                    ayah: ayah
                });
                const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                modal.querySelector('.modal-ayah-ref').textContent = `${surah}:${ayah}`;
                modal.querySelector('.ayah-context').textContent = ayahData?.arabic || 'Ayah text not found.';
                modal.querySelector('#quickRecitationQari').value = '';
                modal.querySelector('#quickRecitationNotes').value = '';
                modal.style.display = 'flex';
                document.getElementById('saveQuickRecitationBtn').onclick = async () => {
                    const log = {
                        surah,
                        ayah_start: ayah,
                        ayah_end: ayah,
                        qari: document.getElementById('quickRecitationQari').value.trim(),
                        log_date: new Date().toISOString().split('T')[0],
                        notes: document.getElementById('quickRecitationNotes').value.trim()
                    };
                    const result = await sendAjaxRequest('save_recitation_log', log);
                    if (result.success) {
                        modal.style.display = 'none';
                    } else {
                        alert('Failed to save recitation log: ' + result.message);
                    }
                };
            }
            async function openHifzModal(surah, ayah) {
                if (!isUserLoggedIn) {
                    alert('Login to manage Hifz status.');
                    return;
                }
                const modal = document.getElementById('hifzQuickModal');
                modal.dataset.surah = surah;
                modal.dataset.ayah = ayah;
                const [hifzDataResult, ayahDataResult] = await Promise.all([
                    sendAjaxRequest('get_hifz_for_ayah', {
                        surah: surah,
                        ayah: ayah
                    }),
                    sendAjaxRequest('load_quran_ayah', {
                        surah: surah,
                        ayah: ayah
                    })
                ]);
                const hifzData = hifzDataResult.success ? hifzDataResult.data : null;
                const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                modal.querySelector('.modal-ayah-ref').textContent = `${surah}:${ayah}`;
                modal.querySelector('.ayah-context').textContent = ayahData?.arabic || 'Ayah text not found.';
                const currentStatus = hifzData?.status || 'not-started';
                modal.querySelector('#currentHifzStatus').textContent = currentStatus.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
                modal.querySelectorAll('#quickHifzStatusButtons button').forEach(btn => {
                    btn.disabled = (btn.dataset.status === currentStatus);
                    btn.onclick = async (e) => {
                        const newStatus = e.target.dataset.status;
                        const existing = hifzData || {
                            surah,
                            ayah,
                            status: 'not-started',
                            notes: '',
                            last_review_date: null,
                            next_review_date: null,
                            review_count: 0
                        };
                        existing.status = newStatus;
                        if (newStatus === 'memorized' && !existing.last_review_date) {
                            existing.last_review_date = new Date().toISOString().split('T')[0];
                            existing.review_count = 0;
                            existing.next_review_date = calculateNextReview(existing.last_review_date, existing.review_count);
                        } else if (newStatus !== 'memorized') {
                            existing.last_review_date = null;
                            existing.next_review_date = null;
                            existing.review_count = 0;
                        }
                        const result = await sendAjaxRequest('update_hifz_status', {
                            surah,
                            ayah,
                            status: existing.status,
                            notes: existing.notes,
                            last_review_date: existing.last_review_date,
                            next_review_date: existing.next_review_date,
                            review_count: existing.review_count
                        });
                        if (result.success) {
                            modal.querySelector('#quickHifzStatus').textContent = "Status Updated!";
                            setTimeout(() => {
                                modal.querySelector('#quickHifzStatus').textContent = "";
                                modal.style.display = 'none';
                            }, 1200);
                        } else {
                            alert('Failed to update Hifz status: ' + result.message);
                        }
                    };
                });
                modal.style.display = 'flex';
            }

            function setupQuickActionsListener() {
                document.body?.addEventListener('click', (e) => {
                    const icon = e.target.closest('.action-icon');
                    if (icon) {
                        const ayahDiv = icon.closest('.ayah');
                        if (!ayahDiv) return;
                        const surah = parseInt(ayahDiv.dataset.surah, 10);
                        const ayah = parseInt(ayahDiv.dataset.ayah, 10);
                        const action = icon.dataset.action;
                        if (!surah || !ayah || !action || !isUserLoggedIn) return;
                        switch (action) {
                            case 'tafsir':
                                openTafsirModal(surah, ayah);
                                break;
                            case 'theme':
                                openThemeModal(surah, ayah);
                                break;
                            case 'recitation':
                                openRecitationModal(surah, ayah);
                                break;
                            case 'hifz':
                                openHifzModal(surah, ayah);
                                break;
                        }
                        return;
                    }
                    const toggle = e.target.closest('.ayah-actions-toggle');
                    if (toggle) {
                        const actionTray = toggle.nextElementSibling;
                        if (actionTray && actionTray.classList.contains('ayah-quick-actions')) {
                            document.querySelectorAll('.ayah-quick-actions.visible').forEach(tray => {
                                if (tray !== actionTray) tray.classList.remove('visible');
                            });
                            actionTray.classList.toggle('visible');
                        }
                        return;
                    }
                    if (!e.target.closest('.ayah-quick-actions')) {
                        document.querySelectorAll('.ayah-quick-actions.visible').forEach(tray => {
                            tray.classList.remove('visible');
                        });
                    }
                });
                document.querySelectorAll('.quick-action-modal .close-button').forEach(btn => {
                    btn?.addEventListener('click', () => {
                        btn.closest('.modal').style.display = 'none';
                    });
                });
            }

            function processExistingAyahs(container) {
                if (!container) return;
                const ayahsToProcess = container.querySelectorAll('.ayah:not(:has(.ayah-quick-actions))');
                if (ayahsToProcess.length > 0) {
                    console.log(`[QuickActions DEBUG] Found ${ayahsToProcess.length} new Ayah divs to process in #${container.id}.`);
                    ayahsToProcess.forEach(injectActionsIntoAyahDiv);
                }
            }

            function initializeQuickActionsModule() {
                if (observer) return;
                const singleViewContainer = document.getElementById('quran-display');
                const continuousViewContainer = document.getElementById('quran-continuous-display');
                if (!singleViewContainer || !continuousViewContainer) {
                    console.error("Quick Actions FATAL: Could not find Quran display containers.");
                    return;
                }
                const mutationCallback = (mutationsList) => {
                    for (const mutation of mutationsList) {
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            mutation.addedNodes.forEach(node => {
                                if (node.nodeType === Node.ELEMENT_NODE) {
                                    if (node.classList.contains('ayah')) {
                                        injectActionsIntoAyahDiv(node);
                                    }
                                    node.querySelectorAll('.ayah').forEach(injectActionsIntoAyahDiv);
                                }
                            });
                        }
                    }
                };
                observer = new MutationObserver(mutationCallback);
                const config = {
                    childList: true,
                    subtree: true
                };
                observer.observe(singleViewContainer, config);
                observer.observe(continuousViewContainer, config);
                console.log("[QuickActions] MutationObserver is now watching both views.");
                document.querySelectorAll('input[name="quran-view-mode"]').forEach(radio => {
                    radio?.addEventListener('change', function() {
                        console.log(`[QuickActions DEBUG] View mode changed to: ${this.value}. Triggering manual check.`);
                        setTimeout(() => {
                            const targetContainer = this.value === 'single' ? singleViewContainer : continuousViewContainer;
                            processExistingAyahs(targetContainer);
                        }, 50);
                    });
                });
                const defaultViewContainer = document.querySelector('input[name="quran-view-mode"]:checked').value === 'single' ? singleViewContainer : continuousViewContainer;
                processExistingAyahs(defaultViewContainer);
            }
            document?.addEventListener('DOMContentLoaded', () => {
                setupQuickActionsListener();
                initializeQuickActionsModule();
            });
        })();
    </script>
    <script>
        (function() {
            'use strict';
            let isSynthesisPatched = false;
            async function handleThemeSynthesis(event) {
                if (!isUserLoggedIn) {
                    alert('Login to synthesize themes.');
                    return;
                }
                const themeId = parseInt(event.target.dataset.themeId, 10);
                if (isNaN(themeId)) return;
                showLoading("Synthesizing Theme...");
                try {
                    const [themeResult, allThemeLinksResult, allTafsirResult] = await Promise.all([
                        sendAjaxRequest('get_theme', {
                            theme_id: themeId
                        }),
                        sendAjaxRequest('get_all_theme_ayahs'),
                        sendAjaxRequest('get_all_tafsir')
                    ]);
                    const theme = themeResult.success ? themeResult.data : null;
                    const allThemeLinks = allThemeLinksResult.success ? allThemeLinksResult.data : [];
                    const allTafsir = allTafsirResult.success ? allTafsirResult.data : [];
                    const relevantLinks = allThemeLinks
                        .filter(link => link.theme_id === themeId)
                        .sort((a, b) => (a.surah !== b.surah) ? a.surah - b.surah : a.ayah - b.ayah);
                    if (relevantLinks.length === 0) {
                        alert(`The theme "${theme.name}" has no linked Ayahs.`);
                        hideLoading();
                        return;
                    }
                    const tafsirMap = new Map(allTafsir.map(t => [`${t.surah}:${t.ayah}`, t.notes]));
                    let synthesisHTML = '';
                    for (const link of relevantLinks) {
                        const ayahDataResult = await sendAjaxRequest('load_quran_ayah', {
                            surah: link.surah,
                            ayah: link.ayah
                        });
                        const ayahData = ayahDataResult.success ? ayahDataResult.data : null;
                        const tafsirNotes = tafsirMap.get(`${link.surah}:${link.ayah}`) || "<i>No Tafsir notes found for this Ayah.</i>";
                        synthesisHTML += `
                        <div class="synthesis-entry">
                            <div class="synthesis-ayah-ref">Surah ${link.surah}: Ayah ${link.ayah}</div>
                            <div class="synthesis-ayah-text">${ayahData?.arabic || 'Arabic text not found.'}</div>
                            <div class="synthesis-tafsir-heading">My Tafsir/Notes:</div>
                            <div class="synthesis-tafsir-notes">${tafsirNotes}</div>
                        </div>
                    `;
                    }
                    const modal = document.getElementById('themeSynthesisModal');
                    document.getElementById('synthesisModalTitle').textContent = `Synthesis for Theme: "${theme.name}"`;
                    document.getElementById('synthesisModalContent').innerHTML = synthesisHTML;
                    modal.style.display = 'flex';
                } catch (error) {
                    console.error("Error during theme synthesis:", error);
                    alert("Failed to generate synthesis document. Check console for details.");
                } finally {
                    hideLoading();
                }
            }

            function addSynthesisButtons() {
                const themeListItems = document.querySelectorAll('#themes-list > li');
                themeListItems.forEach(li => {
                    const themeSpan = li.querySelector('.view-theme-ayahs');
                    if (themeSpan && !li.querySelector('.theme-synthesis-btn')) {
                        const themeId = themeSpan.dataset.themeId;
                        const actionsDiv = li.querySelector('.theme-actions');
                        if (themeId && actionsDiv) {
                            const synthButton = document.createElement('button');
                            synthButton.className = 'theme-synthesis-btn';
                            synthButton.textContent = 'Synthesize';
                            synthButton.title = 'Generate a document from this theme\'s Ayahs and your Tafsir';
                            synthButton.dataset.themeId = themeId;
                            synthButton?.addEventListener('click', handleThemeSynthesis);
                            actionsDiv.appendChild(synthButton);
                        }
                    }
                });
            }

            function patchDisplayThemesList() {
                if (window.displayThemesList && !window.displayThemesList.isPatchedForSynthesis) {
                    const originalFunction = window.displayThemesList;
                    async function displayThemesList(...args) {
                        await originalFunction.apply(this, args);
                        addSynthesisButtons();
                    };
                    window.displayThemesList.isPatchedForSynthesis = true;
                    console.log("displayThemesList has been patched for Theme Synthesis.");
                }
            }
            document?.addEventListener('DOMContentLoaded', () => {
                const printBtn = document.getElementById('synthesisPrintBtn');
                if (printBtn) {
                    printBtn?.addEventListener('click', () => {
                        window.print();
                    });
                }
                setTimeout(() => {
                    patchDisplayThemesList();
                    addSynthesisButtons();
                }, 200);
            });
        })();
    </script>
    <script>
        (function() {
            'use strict';
            const EXAM_SYSTEM_URL = 'exam.html';
            /**
             * Dynamically injects the CSS required for the fullscreen iframe.
             */
            function injectIframeStyles() {
                if (document.getElementById('exam-iframe-styles')) return;
                const styles = `
                #iframe-master-container {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    z-index: 2147483647; background-color: #fff;
                    display: flex; flex-direction: column;
                }
                #examSystemIframe { flex-grow: 1; border: none; }
                #closeExamIframeBtn {
                    position: absolute; top: 15px; right: 15px; z-index: 1;
                    background-color: #dc3545; color: white; font-size: 24px;
                    width: 40px; height: 40px; border-radius: 50%;
                    border: 2px solid white; cursor: pointer; display: flex;
                    justify-content: center; align-items: center; line-height: 1;
                }
            `;
                const styleSheet = document.createElement("style");
                styleSheet.id = 'exam-iframe-styles';
                styleSheet.innerText = styles;
                document.head.appendChild(styleSheet);
            }
            /**
             * A utility to request fullscreen on an element, handling browser differences.
             */
            function requestFullscreenForElement(element) {
                if (element.requestFullscreen) {
                    element.requestFullscreen().catch(err => console.warn(`Fullscreen request failed: ${err.message}`));
                } else if (element.webkitRequestFullscreen) {
                    element.webkitRequestFullscreen();
                } else if (element.mozRequestFullScreen) {
                    element.mozRequestFullScreen();
                } else if (element.msRequestFullscreen) {
                    element.msRequestFullscreen();
                }
            }
            /**
             * A utility to exit fullscreen mode.
             */
            function exitFullscreen() {
                if (document.fullscreenElement) {
                    document.exitFullscreen().catch(err => console.warn(`Exit fullscreen failed: ${err.message}`));
                } else if (document.webkitFullscreenElement) {
                    document.webkitExitFullscreen();
                } else if (document.mozFullScreenElement) {
                    document.mozCancelFullScreen();
                } else if (document.msFullscreenElement) {
                    document.msExitFullscreen();
                }
            }
            /**
             * Creates and displays the exam system in its own fullscreen context.
             * @param {Event} event - The click event from the launch button.
             */
            function launchExamSystemInIframe(event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                if (document.getElementById('iframe-master-container')) return;
                const gameModal = document.getElementById('quranGameModal');
                if (gameModal) gameModal.style.display = 'none';
                const masterContainer = document.createElement('div');
                masterContainer.id = 'iframe-master-container';
                const iframe = document.createElement('iframe');
                iframe.id = 'examSystemIframe';
                iframe.src = EXAM_SYSTEM_URL;
                const closeButton = document.createElement('button');
                closeButton.id = 'closeExamIframeBtn';
                closeButton.innerHTML = '×';
                closeButton.title = 'Close Exam System';
                closeButton.onclick = () => {
                    exitFullscreen();
                    masterContainer.remove();
                    if (gameModal) gameModal.style.display = 'flex';
                };
                masterContainer.appendChild(iframe);
                masterContainer.appendChild(closeButton);
                document.body.appendChild(masterContainer);
                requestFullscreenForElement(masterContainer);
            }
            /**
             * Creates and adds the "Launch Exam System" button.
             * Uses addEventListener for better event control.
             */
            function addLaunchButton() {
                const gameSelectionArea = document.querySelector('#quranGameModal .game-selection-area');
                if (!gameSelectionArea) return false;
                if (document.getElementById('launchExamSystemBtn')) return true;
                const launchButton = document.createElement('button');
                launchButton.id = 'launchExamSystemBtn';
                launchButton.className = 'game-select-btn';
                launchButton.style.backgroundColor = 'var(--color-danger, #dc3545)';
                launchButton.textContent = 'Launch Exam System';
                launchButton?.addEventListener('click', launchExamSystemInIframe);
                gameSelectionArea.appendChild(launchButton);
                return true;
            }

            function initializeIntegration() {
                injectIframeStyles();
                let attempts = 0;
                const maxAttempts = 50;
                const interval = setInterval(() => {
                    attempts++;
                    if (addLaunchButton()) {
                        clearInterval(interval);
                    } else if (attempts > maxAttempts) {
                        clearInterval(interval);
                        console.error('Exam System Launcher: Failed to find ".game-selection-area" after multiple attempts.');
                    }
                }, 500);
            }
            if (document.readyState === 'loading') {
                document?.addEventListener('DOMContentLoaded', initializeIntegration);
            } else {
                initializeIntegration();
            }
        })();
    </script>
    <script>
        (function() {
            'use strict';
            const paletteEl = document.getElementById('commandPalette');
            const inputEl = document.getElementById('commandPaletteInput');
            const resultsEl = document.getElementById('commandPaletteResults');
            let allCommands = [];
            let activeIndex = -1;
            const openGameModalAndStart = (gameFunction) => {
                const gameModal = document.getElementById('quranGameModal');
                if (gameModal) gameModal.style.display = 'flex';
                if (typeof gameFunction === 'function') {
                    gameFunction();
                }
                closeCommandPalette();
            };
            const generateActionCommands = () => {
                const commands = [{
                        name: 'Export All Personal Data',
                        category: 'Data',
                        execute: () => document.getElementById('export-data-btn')?.click()
                    },
                    {
                        name: 'Import Personal Data...',
                        category: 'Data',
                        execute: () => {
                            showSection('data');
                            document.getElementById('import-file')?.click();
                        }
                    },
                    {
                        name: 'Export Tafsir as DOCX',
                        category: 'Data',
                        execute: () => document.getElementById('export-tafsir-to-docx-btn')?.click()
                    },
                    {
                        name: 'Export Tafsir as PDF',
                        category: 'Data',
                        execute: () => document.getElementById('export-tafsir-to-pdf-btn')?.click()
                    },
                    {
                        name: 'Save Current Tafsir Notes',
                        category: 'Action',
                        execute: () => document.getElementById('save-tafsir-btn')?.click()
                    },
                    {
                        name: 'Launch Full Screen Reader',
                        category: 'Action',
                        execute: () => document.getElementById('launchFullScreenReaderBtnEnhanced')?.click()
                    },
                    {
                        name: 'Analyze Root Word...',
                        category: 'Action',
                        execute: () => {
                            const root = prompt("Enter Arabic root to analyze (e.g., ع ل م)");
                            if (root) {
                                showSection('roots');
                                document.getElementById('root-input').value = root;
                                document.getElementById('analyze-root-btn').click();
                            }
                        }
                    },
                    {
                        name: 'Clear All Personal Data (Irreversible!)',
                        category: 'Data',
                        execute: () => document.getElementById('clear-data-btn')?.click()
                    },
                ];
                return commands.filter(cmd => isUserLoggedIn || !['Data', 'Action'].includes(cmd.category));
            };
            const generateNavigationCommands = () => {
                const commands = [{
                        name: 'Quran Viewer',
                        category: 'Navigation',
                        execute: () => showSection('quran')
                    },
                    {
                        name: 'Personal Tafsir',
                        category: 'Navigation',
                        execute: () => showSection('tafsir')
                    },
                    {
                        name: 'Thematic Linker',
                        category: 'Navigation',
                        execute: () => showSection('themes')
                    },
                    {
                        name: 'Root Word Analyzer',
                        category: 'Navigation',
                        execute: () => showSection('roots')
                    },
                    {
                        name: 'Recitation Log',
                        category: 'Navigation',
                        execute: () => showSection('recitation')
                    },
                    {
                        name: 'Memorization Hub',
                        category: 'Navigation',
                        execute: () => showSection('hifz')
                    },
                    {
                        name: 'Advanced Search',
                        category: 'Navigation',
                        execute: () => showSection('search')
                    },
                    {
                        name: 'Data Management',
                        category: 'Navigation',
                        execute: () => showSection('data')
                    },
                    {
                        name: 'My Goals',
                        category: 'Navigation',
                        execute: () => showSection('goals')
                    },
                    {
                        name: 'Reporting',
                        category: 'Navigation',
                        execute: () => showSection('reporting')
                    },
                ];
                return commands.filter(cmd => isUserLoggedIn || cmd.section === 'quran');
            };
            const generateGameCommands = () => [{
                    name: 'Game: Word Meaning (Lughat-ul-Nur)',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startWordWhizGame)
                },
                {
                    name: 'Game: Ayah Jumble (Nazm-ul-Ayah)',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startAyahJumbleGame)
                },
                {
                    name: 'Game: Ayah Translation Match',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startAyahMatchGame_Engine)
                },
                {
                    name: 'Game: Ayah Typing Challenge',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startAyahTyping_Engine)
                },
                {
                    name: 'Game: Recitation Practice',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startRecitationPracticeGame_Engine)
                },
                {
                    name: 'Game: Flashcard & Memory',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startFlashcardGame_Suite)
                },
                {
                    name: 'Game: Verse Jigsaw (Takmil al-Ayah)',
                    category: 'Game',
                    execute: () => openGameModalAndStart(window.startVerseJigsawGame_Engine)
                },
                {
                    name: 'Launch Exam System',
                    category: 'Game',
                    execute: () => document.getElementById('launchExamSystemBtn')?.click()
                },
            ];
            const generateSettingsCommands = () => [{
                    name: 'Switch to Serene Theme',
                    category: 'Settings',
                    execute: () => {
                        document.getElementById('theme-switcher').value = 'serene';
                        applyTheme('serene');
                    }
                },
                {
                    name: 'Switch to Manuscript Theme',
                    category: 'Settings',
                    execute: () => {
                        document.getElementById('theme-switcher').value = 'manuscript';
                        applyTheme('manuscript');
                    }
                },
                {
                    name: 'Switch to Holo-Quran Theme',
                    category: 'Settings',
                    execute: () => {
                        document.getElementById('theme-switcher').value = 'holo';
                        applyTheme('holo');
                    }
                },
                {
                    name: 'Switch to Urdu Translation',
                    category: 'Settings',
                    execute: () => {
                        document.getElementById('translation-select').value = 'urdu';
                        document.getElementById('translation-select').dispatchEvent(new Event('change'));
                    }
                },
                {
                    name: 'Switch to English Translation',
                    category: 'Settings',
                    execute: () => {
                        document.getElementById('translation-select').value = 'english';
                        document.getElementById('translation-select').dispatchEvent(new Event('change'));
                    }
                },
            ];

            function getCommands() {
                const surahCommands = surahNames.map((name, index) => ({
                    name: `Go to Surah ${index + 1}: ${name}`,
                    category: 'Surah',
                    execute: () => {
                        if (typeof loadAyah === 'function') {
                            loadAyah(index + 1, 1);
                            showSection('quran');
                        }
                    }
                }));
                const juzCommands = juzBoundariesData.map((juz) => ({
                    name: `Go to Juz ${juz.juz}: ${juz.name}`,
                    category: 'Juz',
                    execute: () => {
                        if (typeof loadAyah === 'function') {
                            loadAyah(juz.startSurah, juz.startAyah);
                            showSection('quran');
                        }
                    }
                }));
                const ayahCommand = {
                    name: 'Go to specific Ayah...',
                    category: 'Action',
                    execute: () => {
                        const ref = prompt('Enter Surah:Ayah (e.g., 2:255)');
                        if (ref) {
                            const parts = ref.split(':').map(p => parseInt(p.trim()));
                            if (parts.length === 2 && !isNaN(parts[0]) && !isNaN(parts[1])) {
                                loadAyah(parts[0], parts[1]);
                                showSection('quran');
                            } else {
                                alert('Invalid format. Please use Surah:Ayah, for example, 2:255');
                            }
                        }
                    }
                };
                return [
                    ...generateNavigationCommands(),
                    ayahCommand,
                    ...generateActionCommands(),
                    ...generateSettingsCommands(),
                    ...generateGameCommands(),
                    ...surahCommands,
                    ...juzCommands,
                ];
            }

            function renderResults(filteredCommands) {
                resultsEl.innerHTML = '';
                filteredCommands.slice(0, 7).forEach((cmd) => {
                    const li = document.createElement('li');
                    li.classList.add('cp-result-item');
                    li.dataset.commandName = cmd.name;
                    li.innerHTML = `
                    <span>${cmd.name}</span>
                    <span class="category">${cmd.category}</span>
                `;
                    li?.addEventListener('click', () => executeCommand(cmd));
                    resultsEl.appendChild(li);
                });
                activeIndex = -1;
                if (filteredCommands.length > 0) {
                    activeIndex = 0;
                    resultsEl.children[0]?.classList.add('active');
                }
            }

            function filterAndRender() {
                const query = inputEl.value.toLowerCase().trim();
                if (!query) {
                    renderResults(allCommands.filter(c => ['Navigation', 'Action', 'Game'].includes(c.category)));
                    return;
                }
                const filtered = allCommands.filter(cmd => cmd.name.toLowerCase().includes(query));
                renderResults(filtered);
            }

            function executeCommand(command) {
                if (command && typeof command.execute === 'function') {
                    command.execute();
                    closeCommandPalette();
                }
            }

            function openCommandPalette() {
                allCommands = getCommands();
                paletteEl.style.display = 'flex';
                inputEl.value = '';
                filterAndRender();
                inputEl.focus();
            }

            function closeCommandPalette() {
                paletteEl.style.display = 'none';
            }

            function updateSelection(direction) {
                const items = resultsEl.children;
                if (items.length === 0) return;
                items[activeIndex]?.classList.remove('active');
                if (direction === 'down') {
                    activeIndex = (activeIndex + 1) % items.length;
                } else {
                    activeIndex = (activeIndex - 1 + items.length) % items.length;
                }
                items[activeIndex]?.classList.add('active');
                items[activeIndex]?.scrollIntoView({
                    block: 'nearest'
                });
            }
            document?.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    openCommandPalette();
                }
            });
            paletteEl?.addEventListener('click', (e) => {
                if (e.target === paletteEl) closeCommandPalette();
            });
            inputEl?.addEventListener('input', filterAndRender);
            inputEl?.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeCommandPalette();
                else if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    updateSelection('down');
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    updateSelection('up');
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    const activeItem = resultsEl.querySelector('.active');
                    if (activeItem) {
                        const commandName = activeItem.dataset.commandName;
                        const commandToExecute = allCommands.find(c => c.name === commandName);
                        executeCommand(commandToExecute);
                    }
                }
            });
        })();

        document.querySelectorAll('input, textarea, select').forEach(el => el.setAttribute('dir', 'auto'));
    </script>