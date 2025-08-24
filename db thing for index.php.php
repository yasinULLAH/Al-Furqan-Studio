<?php
// Set high execution time and memory limit for this script only
ini_set('max_execution_time', 300); // 5 minutes
ini_set('memory_limit', '256M');

// --- Basic Setup ---
header('Content-Type: text/plain; charset=utf-8');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "Starting Database Setup Script...\n";
echo "------------------------------------\n";

// --- IMPORTANT: Database Credentials ---
// These are the credentials you confirmed are correct.
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'nur_ul_quran_studio_db');

// --- Data File Configuration ---
$manifest_config = [
    (object)['key' => 'urdu', 'label' => 'Urdu Translation', 'file_type' => 'quran_translation', 'url' => 'data new.AM'],
    (object)['key' => 'english', 'label' => 'English Translation', 'file_type' => 'quran_translation', 'url' => 'dataENG.AM'],
    (object)['key' => 'Bangali', 'label' => 'Bangali Translation', 'file_type' => 'quran_translation', 'url' => 'dataBNG.AM'],
    (object)['key' => 'pashto', 'label' => 'Pashto Translation', 'file_type' => 'quran_translation', 'url' => 'dataPS.AM'],
    (object)['key' => 'word_translation', 'label' => 'Word-by-Word Translations', 'file_type' => 'word_translation', 'url' => 'data5 new.AM', 'headers' => ['word_id', 'ur_meaning', 'en_meaning', 'pashto_text', 'bn_meaning'], 'auto_approve_user_id' => 1],
    (object)['key' => 'word_metadata', 'label' => 'Word Metadata', 'file_type' => 'word_metadata', 'url' => 'word2.AM', 'headers' => ['word_id', 'surah', 'ayah', 'word_position', 'arabic_word']]
];

// --- Database Connection ---
echo "Attempting to connect to database 'DB_NAME'...\n";
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("FATAL ERROR: Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset("utf8mb4");
echo "Database connection successful.\n\n";

// --- Function to Drop All Tables ---
function drop_all_tables($conn)
{
    echo "Dropping existing tables (if they exist)...\n";
    $tables = [
        'theme_ayahs', 'tafsir', 'themes', 'root_words', 'recitations', 'hifz',
        'settings', 'word_translations', 'word_metadata', 'goals', 'users'
    ];
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    foreach ($tables as $table) {
        if ($conn->query("DROP TABLE IF EXISTS `$table`")) {
            echo " - Table `$table` dropped.\n";
        } else {
            echo " - Warning: Could not drop table `$table`. " . $conn->error . "\n";
        }
    }
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Finished dropping tables.\n\n";
}

// --- Function to Create All Tables ---
function create_all_tables($conn)
{
    echo "Creating new table structures...\n";
    $queries = [
        "CREATE TABLE users ( id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE NOT NULL, password VARCHAR(255) NOT NULL, email VARCHAR(100) UNIQUE NOT NULL, role ENUM('public', 'registered', 'admin') DEFAULT 'public', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP )",
        "CREATE TABLE quran_ayahs ( surah INT NOT NULL, ayah INT NOT NULL, arabic TEXT NOT NULL, urdu TEXT, english TEXT, Bangali TEXT, pashto TEXT, PRIMARY KEY (surah, ayah) )",
        "CREATE TABLE tafsir ( id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, surah INT NOT NULL, ayah INT NOT NULL, notes TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE (user_id, surah, ayah) )",
        "CREATE TABLE themes ( id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT, parent_id INT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE )",
        "CREATE TABLE theme_ayahs ( id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, theme_id INT NOT NULL, surah INT NOT NULL, ayah INT NOT NULL, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE, UNIQUE (user_id, theme_id, surah, ayah) )",
        "CREATE TABLE root_words ( id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, root VARCHAR(50) NOT NULL, description TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, UNIQUE (user_id, root) )",
        "CREATE TABLE recitations ( id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, surah INT NOT NULL, ayah_start INT, ayah_end INT, qari VARCHAR(255), log_date DATE NOT NULL, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE )",
        "CREATE TABLE hifz ( user_id INT NOT NULL, surah INT NOT NULL, ayah INT NOT NULL, status ENUM('not-started', 'in-progress', 'memorized') DEFAULT 'not-started', last_review_date DATE, next_review_date DATE, review_count INT DEFAULT 0, notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (user_id, surah, ayah), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE )",
        "CREATE TABLE settings ( user_id INT NOT NULL, setting_name VARCHAR(100) NOT NULL, setting_value TEXT NOT NULL, PRIMARY KEY (user_id, setting_name), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE )",
        "CREATE TABLE word_translations ( word_id INT PRIMARY KEY, ur_meaning TEXT, en_meaning TEXT, pashto_text TEXT, bn_meaning TEXT, approved_by INT, approved_at TIMESTAMP NULL, FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL )",
        "CREATE TABLE word_metadata ( word_id INT PRIMARY KEY, surah INT NOT NULL, ayah INT NOT NULL, word_position INT NOT NULL, arabic_word TEXT NOT NULL, UNIQUE KEY (surah, ayah, word_position) )",
        "CREATE TABLE goals ( id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(100) NOT NULL, target_date DATE, creation_date DATE NOT NULL, is_complete BOOLEAN DEFAULT FALSE, target_surah INT, target_juz INT, target_theme INT, target_count INT, target_day INT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE )"
    ];

    foreach ($queries as $sql) {
        $tableName = explode(' ', $sql)[2];
        if ($conn->query($sql)) {
            echo " - Table `$tableName` created successfully.\n";
        } else {
            die(" - FATAL ERROR creating table `$tableName`: " . $conn->error . "\n");
        }
    }
    echo "All tables created.\n\n";
}

// --- Function to Create Admin User ---
function create_admin_user($conn) {
    echo "Checking for admin user...\n";
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES ('admin', ?, 'admin@example.com', 'admin')");
    $stmt->bind_param('s', $password);
    if ($stmt->execute()) {
        echo " - Admin user created successfully. (Username: admin, Password: admin123)\n\n";
    } else {
        echo " - Admin user already exists or an error occurred. " . $stmt->error . "\n\n";
    }
    $stmt->close();
}

// --- Memory-Efficient Data Import Function ---
function import_data_from_file($file_config, $conn) {
    // (This is the corrected, memory-safe version of the function)
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
    } elseif ($file_config->file_type === 'word_metadata') {
        $table_name = 'word_metadata';
        $headers = $file_config->headers;
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
    $line_num = 0;

    while (($line = fgets($handle)) !== false) {
        $line_num++;
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
            if ($file_config->file_type === 'word_metadata' && count($values) === 4) {
                // Add a placeholder for the missing 'arabic_word' column
                $values[] = ''; 
            }
            if (count($values) !== count($headers)) {
                echo " - WARNING: Malformed line #{$line_num} in {$file_path}. Skipping.\n";
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
            
            if(!$stmt_insert->execute()) {
                 echo " - ERROR executing insert on line #{$line_num} for {$table_name}: " . $stmt_insert->error . "\n";
            } else {
                $row_count++;
            }
        }
        if ($row_count > 0 && $row_count % 5000 === 0) {
            echo " - ...processed {$row_count} rows...\n";
        }
    }

    fclose($handle);
    $conn->commit();

    if ($stmt_insert_arabic) $stmt_insert_arabic->close();
    if ($stmt_update_trans) $stmt_update_trans->close();
    if ($stmt_insert) $stmt_insert->close();
    
    return $row_count;
}

// --- Main Execution ---
drop_all_tables($conn);
create_all_tables($conn);
create_admin_user($conn);

echo "Starting data import process. This may take a few minutes...\n\n";

foreach($manifest_config as $config) {
    echo "Importing '{$config->label}' from '{$config->url}'...\n";
    $rows_imported = import_data_from_file($config, $conn);
    if ($rows_imported === false) {
        echo " - IMPORT FAILED for {$config->label}. Check file paths and permissions.\n\n";
    } else {
        echo " - Successfully imported {$rows_imported} rows.\n\n";
    }
}

echo "------------------------------------\n";
echo "DATABASE SETUP COMPLETE!\n";
echo "You should now remove the setup_db.php file from your server.\n";

?>