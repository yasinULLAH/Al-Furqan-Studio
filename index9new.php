<?php
ini_set('display_errors', 0);
session_start();
$db = new SQLite3('quran4.db');

$db->exec("
CREATE TABLE IF NOT EXISTS quran_word_text (
    word_id INTEGER PRIMARY KEY,
    quran_text TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    role TEXT DEFAULT 'user',
    approved INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS ayahs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    surah INTEGER NOT NULL,
    ayah INTEGER NOT NULL,
    arabic TEXT NOT NULL,
    translation TEXT NOT NULL,
    language TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS words (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word_id INTEGER NOT NULL,
    ur_meaning TEXT,
    en_meaning TEXT
);

CREATE TABLE IF NOT EXISTS word_meta (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word_id INTEGER NOT NULL,
    surah INTEGER NOT NULL,
    ayah INTEGER NOT NULL,
    position INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS tafsir (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    surah INTEGER NOT NULL,
    ayah INTEGER NOT NULL,
    content TEXT NOT NULL,
    approved INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS themes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    ayahs TEXT NOT NULL,
    content TEXT NOT NULL,
    approved INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS recitation_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    surah INTEGER NOT NULL,
    ayah INTEGER NOT NULL,
    date DATE NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS hifz_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    surah INTEGER NOT NULL,
    ayah INTEGER NOT NULL,
    memorized INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    type TEXT NOT NULL,
    reference TEXT NOT NULL,
    content TEXT NOT NULL,
    approved INTEGER DEFAULT 0,
    reviewed_by INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS bookmarks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    surah INTEGER NOT NULL,
    ayah INTEGER NOT NULL,
    note TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);

CREATE TABLE IF NOT EXISTS root_analysis (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    root_word TEXT NOT NULL,
    analysis TEXT NOT NULL,
    approved INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id)
);
");

$admin = $db->querySingle("SELECT COUNT(*) FROM users WHERE role = 'admin'");
if (!$admin) {
    $pw = password_hash('admin', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password, role, approved) VALUES ('admin', '$pw', 'admin', 1)");
}

function auth() {
    global $db;
    if (!isset($_SESSION['user_id'])) return false;
    $user = $db->querySingle("SELECT * FROM users WHERE id = {$_SESSION['user_id']}", true);
    return $user && $user['approved'] ? $user : false;
}

function role($r) {
    $u = auth();
    if (!$u) return false;
    $roles = ['public' => 0, 'user' => 1, 'ulama' => 2, 'admin' => 3];
    return $roles[$u['role']] >= $roles[$r];
}

$a = isset($_POST['action']) ? $_POST['action'] : '';

if ($a == 'login') {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $user = $db->querySingle("SELECT * FROM users WHERE username = '$u'", true);
    if ($user && password_verify($p, $user['password']) && $user['approved']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
    }
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: '. $_SERVER['PHP_SELF']);

    exit;
}

if ($a == 'register') {
    $u = $_POST['username'];
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password) VALUES ('$u', '$p')");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'logout') {
    session_destroy();
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'load_data' && role('admin')) {
    $f = $_FILES['data_file']['tmp_name'];
    $lang = $_POST['language'];
    if ($f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^(.*?) ترجمہ: (.*?)<br\/>س (\d{3}) آ (\d{3})$/', $line, $m)) {
                $ar = $db->escapeString(trim($m[1]));
                $tr = $db->escapeString(trim($m[2]));
                $s = intval($m[3]);
                $ay = intval($m[4]);
                $db->exec("INSERT OR REPLACE INTO ayahs (surah, ayah, arabic, translation, language) VALUES ($s, $ay, '$ar', '$tr', '$lang')");
            }
        }
    }
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'load_words' && role('admin')) {
    $f = $_FILES['word_file']['tmp_name'];
    if ($f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 3) {
                $wid = intval($parts[0]);
                $ur = $db->escapeString(trim($parts[1]));
                $en = $db->escapeString(trim($parts[2]));
                $db->exec("INSERT OR REPLACE INTO words (word_id, ur_meaning, en_meaning) VALUES ($wid, '$ur', '$en')");
            }
        }
    }
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}
if ($a == 'load_quran_words' && role('admin')) {
    $f = $_FILES['quran_words_file']['tmp_name'];
    if ($f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
             echo "Error: Could not read the uploaded file.";
             // Redirect with error
             // header('Location: ' . $_SERVER['PHP_SELF'] . '?page=admin&tab=data&error=' . urlencode('Could not read uploaded file.')); exit;
             exit;
        }
        if (empty($lines)) {
             echo "Error: Uploaded file is empty.";
              // Redirect with error
              // header('Location: ' . $_SERVER['PHP_SELF'] . '?page=admin&tab=data&error=' . urlencode('Uploaded file is empty.')); exit;
             exit;
        }

        $header_line = array_shift($lines); // Read and remove the header line

        // --- FIX: Explicitly remove UTF-8 BOM if present ---
        // BOM for UTF-8 is EF BB BF
        if (substr($header_line, 0, 3) === "\xef\xbb\xbf") {
            $header_line = substr($header_line, 3);
        }
        // --- End FIX ---

        // We are not strictly validating the header columns by name anymore,
        // just removing the first line assuming it's the header.
        // You could add a check here if the *content* of the header line
        // matches expected values if needed, but the request is to ignore it.

        // --- FIX: Set busy timeout to handle database locks ---
        // This tells SQLite to wait up to 5000 milliseconds (5 seconds) if the database is busy
        $db->busyTimeout(5000);
        // --- End FIX ---


        $db->exec('BEGIN TRANSACTION'); // Start transaction for performance

        $stmt = $db->prepare("INSERT OR REPLACE INTO quran_word_text (word_id, quran_text) VALUES (:word_id, :quran_text)");
        if ($stmt === false) {
             $db->exec('ROLLBACK'); // Rollback transaction
             echo "Error preparing statement: " . $db->lastErrorMsg();
             // Redirect with error
             // header('Location: ' . $_SERVER['PHP_SELF'] . '?page=admin&tab=data&error=' . urlencode('Database error preparing statement.')); exit;
             exit;
        }

        $processed_count = 0;
        $skipped_lines = [];
        $line_num = 1; // Start line number after header

        foreach ($lines as $line) {
            $line_num++;
            $trimmed_line = trim($line);

            // Skip empty lines (already filtered by FILE_SKIP_EMPTY_LINES, but safety check)
            if ($trimmed_line === '') {
                 continue;
            }

            // --- FIX: Use explode and handle potential extra commas ---
            $parts = explode(',', $trimmed_line, 2); // Split only on the first comma

            // Check if we got at least 2 parts (word_id and the rest as quran_text)
            if (count($parts) >= 2) {
                $wid = intval(trim($parts[0])); // Trim word_id part just in case of spaces
                $text = trim($parts[1]);       // Everything after the first comma

                if ($wid > 0 && $text !== '') {
                    $stmt->bindValue(':word_id', $wid, SQLITE3_INTEGER);
                    $stmt->bindValue(':quran_text', $text, SQLITE3_TEXT);
                    $execute_result = $stmt->execute(); // execute() returns bool for INSERT/UPDATE/DELETE

                    if ($execute_result === false) {
                         // Log the specific error and line, but continue processing if possible
                         error_log("Database error executing INSERT for word_id $wid (Line $line_num): " . $db->lastErrorMsg() . " on line: " . htmlspecialchars($line));
                         $skipped_lines[] = "Line $line_num (DB Error): " . htmlspecialchars($line);
                    } else {
                         // Success - execute() returns bool for INSERT
                          $processed_count++;
                    }
                    // No need for clearBindings()
                } else {
                     // Log or report lines with invalid data format (word_id <= 0 or empty text)
                     error_log("Skipping line with invalid data (Line $line_num): Invalid word_id ($wid) or empty text. Line: " . htmlspecialchars($line));
                     $skipped_lines[] = "Line $line_num (Invalid Data): " . htmlspecialchars($line);
                }
            } else {
                 // Log or report lines that didn't contain a comma or had only a word_id without text
                 error_log("Skipping malformed CSV line (Line $line_num): Expected 'word_id,quran_text'. Line: " . htmlspecialchars($line));
                 $skipped_lines[] = "Line $line_num (Malformed): " . htmlspecialchars($line);
            }
             // --- End FIX ---
        } // end foreach

        $stmt->close();

        $db->exec('COMMIT TRANSACTION'); // Commit transaction

         // Optional: Provide feedback on skipped lines. This would require storing messages
         // and redirecting with a parameter or displaying them on the page *before* redirecting.
         // if (!empty($skipped_lines)) {
         //     // Store $skipped_lines in $_SESSION or pass via GET params (carefully due to length)
         //     $_SESSION['import_skipped_lines'] = $skipped_lines;
         // }
         // $_SESSION['import_processed_count'] = $processed_count;


    } else {
         // Handle case where file upload failed (e.g., file size, permissions)
         echo "Error: No file uploaded or file upload failed. Check PHP file upload limits and file permissions.";
         // Redirect with error
         // header('Location: ' . $_SERVER['PHP_SELF'] . '?page=admin&tab=data&error=' . urlencode('File upload failed.')); exit;
         exit;
    }

    // Redirect back to the admin page, data tab
    // Important: Do not have any echo/print statements before header() *except* for errors that exit.
    $redirect_url = $_SERVER['PHP_SELF'];
    // Preserve relevant GET parameters for the admin page
    $params = ['page=admin', 'tab=data']; // Default redirect target

    if(!empty($_GET)) {
         $allowed_get_params_to_preserve = ['page', 'tab']; // Only preserve 'page' and 'tab' from GET
         foreach($_GET as $k => $v) {
             if(in_array($k, $allowed_get_params_to_preserve)) {
                 // Overwrite defaults if present in GET
                 $params[$k] = urlencode($k) . '=' . urlencode($v);
             }
         }
    }
     // Ensure page=admin and tab=data are the final state
    $params['page'] = 'page=admin';
    $params['tab'] = 'tab=data';


    $query_string = implode('&', array_unique($params));

    header('Location: ' . $redirect_url . ($query_string ? '?' . $query_string : ''));
    exit;
}
if ($a == 'load_word_meta' && role('admin')) {
    $f = $_FILES['meta_file']['tmp_name'];
    if ($f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 4) {
                $wid = intval($parts[0]);
                $s = intval($parts[1]);
                $ay = intval($parts[2]);
                $pos = intval($parts[3]);
                $db->exec("INSERT OR REPLACE INTO word_meta (word_id, surah, ayah, position) VALUES ($wid, $s, $ay, $pos)");
            }
        }
    }
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'add_tafsir' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $ay = intval($_POST['ayah']);
    $c = $db->escapeString($_POST['content']);
    $app = role('ulama') ? 1 : 0;
    $db->exec("INSERT INTO tafsir (user_id, surah, ayah, content, approved) VALUES ($uid, $s, $ay, '$c', $app)");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'add_theme' && role('user')) {
    $uid = $_SESSION['user_id'];
    $t = $db->escapeString($_POST['title']);
    $ayahs = $db->escapeString($_POST['ayahs']);
    $c = $db->escapeString($_POST['content']);
    $app = role('ulama') ? 1 : 0;
    $db->exec("INSERT INTO themes (user_id, title, ayahs, content, approved) VALUES ($uid, '$t', '$ayahs', '$c', $app)");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'log_recitation' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $ay = intval($_POST['ayah']);
    $d = date('Y-m-d');
    $db->exec("INSERT INTO recitation_log (user_id, surah, ayah, date) VALUES ($uid, $s, $ay, '$d')");
    header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . ($_GET['page'] ?? 'viewer') . '&surah=' . $s . '&lang=' . ($_GET['lang'] ?? 'ur'));
    exit;
}

if ($a == 'update_hifz' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $ay = intval($_POST['ayah']);
    $m = intval($_POST['memorized']);
    $db->exec("INSERT OR REPLACE INTO hifz_progress (user_id, surah, ayah, memorized) VALUES ($uid, $s, $ay, $m)");
    header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . ($_GET['page'] ?? 'viewer') . '&surah=' . $s . '&lang=' . ($_GET['lang'] ?? 'ur'));
    exit;
}

if ($a == 'add_bookmark' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $ay = intval($_POST['ayah']);
    $note = $db->escapeString($_POST['note']);
    $db->exec("INSERT INTO bookmarks (user_id, surah, ayah, note) VALUES ($uid, $s, $ay, '$note')");
    header('Location: ' . $_SERVER['PHP_SELF'] . '?page=' . ($_GET['page'] ?? 'viewer') . '&surah=' . $s . '&lang=' . ($_GET['lang'] ?? 'ur'));
    exit;
}


if ($a == 'del_bookmark' && role('user')) {
    $bid = intval($_POST['bookmark_id']);
    $uid = $_SESSION['user_id'];
    $db->exec("DELETE FROM bookmarks WHERE id = $bid AND user_id = $uid");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'add_root_analysis' && role('user')) {
    $uid = $_SESSION['user_id'];
    $root = $db->escapeString($_POST['root_word']);
    $analysis = $db->escapeString($_POST['analysis']);
    $app = role('ulama') ? 1 : 0;
    $db->exec("INSERT INTO root_analysis (user_id, root_word, analysis, approved) VALUES ($uid, '$root', '$analysis', $app)");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'approve_user' && role('admin')) {
    $uid = intval($_POST['user_id']);
    $db->exec("UPDATE users SET approved = 1 WHERE id = $uid");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'promote_user' && role('admin')) {
    $uid = intval($_POST['user_id']);
    $r = $_POST['new_role'];
    $db->exec("UPDATE users SET role = '$r' WHERE id = $uid");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'approve_content' && role('ulama')) {
    $cid = intval($_POST['content_id']);
    $table = $_POST['table'];
    $db->exec("UPDATE $table SET approved = 1 WHERE id = $cid");
    $redirect_url = $_SERVER['PHP_SELF'];
$params = [];
foreach(['page','surah','lang','from_ayah','to_ayah','user_id','content_id','table','new_role','search_lang','query'] as $p) {
    if(isset($_GET[$p])) $params[] = $p.'='.$_GET[$p];
    if(isset($_POST[$p])) $params[] = $p.'='.$_POST[$p];
}
if($params) $redirect_url .= '?' . implode('&',$params);
header('Location: ' . $redirect_url);

    exit;
}

if ($a == 'search') {
    $q = $_POST['query'];
    $lang = $_POST['search_lang'] ?: 'ur';
    
    // Get all ayahs for the language
    $all_ayahs = $db->query("SELECT * FROM ayahs WHERE language = '$lang'");
    $matching_ayahs = [];
    
    // Check if query contains Arabic characters
    $has_arabic = preg_match('/[\x{0600}-\x{06FF}]/u', $q);
    
    if ($lang == 'ur' || $has_arabic) {
        // Normalize search query
        $normalized_query = preg_replace('/[ًٌٍََُِِّْٰٓۡٔؒ]/u', '', $q);
        $normalized_query = preg_replace('/[ؤو]/u', '[وؤ]', $normalized_query);
        $normalized_query = preg_replace('/[كک]/u', '[كک]', $normalized_query);
        $normalized_query = preg_replace('/[آاأإ]/u', '[آاأإ]', $normalized_query);
        $normalized_query = preg_replace('/[ىیي]/u', '[ىیي]', $normalized_query);
        $normalized_query = preg_replace('/[ہھةۃه]/u', '[ہھةۃه]', $normalized_query);
        $normalized_query = preg_replace('/ے/u', '[ےی]', $normalized_query);
        $normalized_query = preg_replace('/م/u', '[مٰم]', $normalized_query);
        $normalized_query = preg_replace('/\s+/u', '.*', $normalized_query);
        
        // Search through all ayahs
        while ($ayah = $all_ayahs->fetchArray(SQLITE3_ASSOC)) {
            // Normalize ayah text for comparison
            $normalized_arabic = preg_replace('/[ًٌٍََُِِّْٰٓۡٔؒ]/u', '', $ayah['arabic']);
            $normalized_translation = $ayah['translation'];
            
            // Check if normalized query matches normalized Arabic or translation
            if (preg_match('/' . $normalized_query . '/u', $normalized_arabic) || 
                stripos($normalized_translation, $q) !== false) {
                $matching_ayahs[] = $ayah;
                if (count($matching_ayahs) >= 50) break; // Limit results
            }
        }
    } else {
        // Regular text search
        while ($ayah = $all_ayahs->fetchArray(SQLITE3_ASSOC)) {
            if (stripos($ayah['arabic'], $q) !== false || 
                stripos($ayah['translation'], $q) !== false) {
                $matching_ayahs[] = $ayah;
                if (count($matching_ayahs) >= 50) break; // Limit results
            }
        }
    }
    
    // Convert results to format expected by display code
    $results = (object)['results' => $matching_ayahs, 'index' => 0];
}


if ($a == 'export_personal' && role('user')) {
    $uid = $_SESSION['user_id'];
    $data = [];
    
    $tafsirs = $db->query("SELECT * FROM tafsir WHERE user_id = $uid");
    while ($t = $tafsirs->fetchArray(SQLITE3_ASSOC)) {
        $data['tafsir'][] = $t;
    }
    
    $themes = $db->query("SELECT * FROM themes WHERE user_id = $uid");
    while ($th = $themes->fetchArray(SQLITE3_ASSOC)) {
        $data['themes'][] = $th;
    }
    
    $hifz = $db->query("SELECT * FROM hifz_progress WHERE user_id = $uid AND memorized = 1");
    while ($h = $hifz->fetchArray(SQLITE3_ASSOC)) {
        $data['hifz'][] = $h;
    }
    
    $bookmarks = $db->query("SELECT * FROM bookmarks WHERE user_id = $uid");
    while ($b = $bookmarks->fetchArray(SQLITE3_ASSOC)) {
        $data['bookmarks'][] = $b;
    }
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="personal_quran_data.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

$u = auth();
$page = isset($_GET['page']) ? $_GET['page'] : 'viewer';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quran Study App</title>
<style>
*{box-sizing:border-box}
body{font-family:system-ui;margin:0;background:#f5f5f5}
.nav{background:#2c5aa0;color:white;padding:1rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap}
.nav a{color:white;text-decoration:none;margin:0.5rem;padding:0.5rem;border-radius:4px}
.nav a:hover{background:#1e3d6f}
.nav-links{display:flex;flex-wrap:wrap}
.nav-user{display:flex;align-items:center;gap:1rem}
.container{max-width:1200px;margin:0 auto;padding:1rem}
.card{background:white;border-radius:8px;padding:1.5rem;margin:1rem 0;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
.btn{background:#2c5aa0;color:white;border:none;padding:0.5rem 1rem;border-radius:4px;cursor:pointer;margin:0.2rem}
.btn:hover{background:#1e3d6f}
.btn-sm{padding:0.3rem 0.6rem;font-size:0.9rem}
.btn-danger{background:#dc3545}
.btn-danger:hover{background:#c82333}
.form-group{margin:1rem 0}
label{display:block;margin-bottom:0.5rem;font-weight:bold}
input,select,textarea{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:4px;box-sizing:border-box}
textarea{height:100px;resize:vertical}
.ayah{border-left:4px solid #2c5aa0;padding:1rem;margin:1rem 0;background:white;border-radius:4px}
.arabic{font-size:1.5rem;direction:rtl;text-align:right;margin-bottom:1rem;line-height:2;font-family:'Times New Roman',serif}
.translation{font-size:1.1rem;color:#333;line-height:1.6}
.meta{font-size:0.9rem;color:#666;margin-top:0.5rem}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem}
.grid-2{grid-template-columns:repeat(auto-fit,minmax(200px,1fr))}
.user-panel{background:#e8f4f8;border-radius:4px;padding:1rem;margin-bottom:1rem}
.tabs{display:flex;border-bottom:2px solid #ddd;margin-bottom:2rem;overflow-x:auto}
.tab{padding:1rem 2rem;cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap}
.tab.active{border-bottom-color:#2c5aa0;background:#f0f8ff}
.hidden{display:none}
.game-area{text-align:center;padding:2rem}
.word{display:inline-block;margin:0.2rem;padding:0.5rem;background:#e8f4f8;border-radius:4px;cursor:pointer}
.word.selected{background:#2c5aa0;color:white}
.word.correct{background:#28a745;color:white}
.word.incorrect{background:#dc3545;color:white}
.score{font-size:1.2rem;font-weight:bold;margin:1rem 0}
.stats{display:flex;justify-content:space-around;text-align:center;margin:1rem 0}
.stat{padding:1rem;background:#f8f9fa;border-radius:4px}
.bookmark-item{background:#fff3cd;border:1px solid #ffeaa7;border-radius:4px;padding:1rem;margin:0.5rem 0}
.contribution{border-left:4px solid #f39c12;padding:1rem;margin:0.5rem 0;background:#fef9e7;border-radius:4px}
.approved{border-left-color:#27ae60;background:#eafaf1}
.pending{border-left-color:#e74c3c;background:#fdedec}
.filter-bar{background:#f8f9fa;padding:1rem;border-radius:4px;margin-bottom:1rem}
.root-word{background:#e8f4f8;padding:0.5rem;border-radius:4px;display:inline-block;margin:0.2rem;font-weight:bold}
.word-analysis{background:#f8f9fa;padding:1rem;border-radius:4px;margin:0.5rem 0}
.progress-bar{background:#e9ecef;height:20px;border-radius:10px;overflow:hidden}
.progress-fill{background:#28a745;height:100%;transition:width 0.3s ease}
.notification{padding:1rem;margin:1rem 0;border-radius:4px;background:#d4edda;border:1px solid #c3e6cb;color:#155724}
.warning{background:#fff3cd;border-color:#ffeaa7;color:#856404}
.error{background:#f8d7da;border-color:#f5c6cb;color:#721c24}
@media (max-width: 768px) {
    .nav{flex-direction:column;gap:1rem}
    .nav-links{justify-content:center}
    .container{padding:0.5rem}
    .grid{grid-template-columns:1fr}
    .tabs{flex-direction:column}
    .tab{text-align:center}
}

div.word-analysis > div:nth-child(3) {
    direction: rtl;
}

.btn-success{background:#28a745;color:white}
.btn-success:hover{background:#218838}

</style>
</head>
<body>

<div class="nav">
    <div class="nav-links">
        <a href="?page=viewer">📖 Viewer</a>
        <?php if($u): ?>
        <a href="?page=personal">👤 Personal</a>
        <a href="?page=tafsir">📝 Tafsir</a>
        <a href="?page=themes">🏷️ Themes</a>
        <a href="?page=recitation">🎵 Recitation</a>
        <a href="?page=hifz">🧠 Hifz Hub</a>
        <a href="?page=bookmarks">🔖 Bookmarks</a>
        <a href="?page=roots">🌳 Root Analysis</a>
        <?php endif; ?>
        <a href="?page=search">🔍 Search</a>
        <a href="?page=games">🎮 Games</a>
        <a href="?page=contributions">🤝 Community</a>
        <?php if(role('ulama')): ?>
        <a href="?page=review">👨‍🏫 Review</a>
        <?php endif; ?>
        <?php if(role('admin')): ?>
        <a href="?page=admin">⚙️ Admin</a>
        <?php endif; ?>
    </div>
    <div class="nav-user">
        <?php if($u): ?>
            <span>Welcome, <?= htmlspecialchars($u['username']) ?> (<?= ucfirst($u['role']) ?>)</span>
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn btn-sm">Logout</button>
            </form>
        <?php else: ?>
            <a href="?page=login">Login</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <?php if(!$u && $page != 'login'): ?>
    <div class="card">
        <h2>🕌 Welcome to Quran Study App</h2>
        <div class="stats">
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM ayahs") ?></h3>
                <p>Total Ayahs</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM tafsir WHERE approved = 1") ?></h3>
                <p>Approved Tafsir</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM themes WHERE approved = 1") ?></h3>
                <p>Public Themes</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM users WHERE approved = 1") ?></h3>
                <p>Active Users</p>
            </div>
        </div>
        <p>Access the Quran text, translations, and scholarly content. <a href="?page=login">Login</a> for personal features like Tafsir creation, Hifz tracking, and more.</p>
    </div>
    <?php endif; ?>

    <?php if($page == 'login'): ?>
    <div class="grid">
        <div class="card">
            <h2>🔐 Login</h2>
            <form method="post">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">Login</button>
            </form>
        </div>
        <div class="card">
            <h2>📝 Register</h2>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required>
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn">Register</button>
            </form>
            <div class="notification warning">
                <strong>Note:</strong> Registration requires admin approval. You'll be notified once approved.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($page == 'viewer'): ?>
    <div class="card">
        <h2>📖 Quran Viewer</h2>
        <form method="get" class="filter-bar">
            <input type="hidden" name="page" value="viewer">
            <div class="grid grid-2">
                <div class="form-group">
                    <label>Surah:</label>
                    <select name="surah">
                        <option value="">Select Surah</option>
                        <?php 
                        $surahs = [
                            1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Aal-E-Imran', 4 => 'An-Nisa', 5 => 'Al-Maidah',
                            6 => 'Al-Anam', 7 => 'Al-Araf', 8 => 'Al-Anfal', 9 => 'At-Tawbah', 10 => 'Yunus',
                            11 => 'Hud', 12 => 'Yusuf', 13 => 'Ar-Rad', 14 => 'Ibrahim', 15 => 'Al-Hijr',
                            16 => 'An-Nahl', 17 => 'Al-Isra', 18 => 'Al-Kahf', 19 => 'Maryam', 20 => 'Ta-Ha'
                        ];
                        for($i=1;$i<=114;$i++): 
                            $name = isset($surahs[$i]) ? $surahs[$i] : "Surah $i";
                        ?>
                        <option value="<?=$i?>" <?= $_GET['surah']==$i?'selected':'' ?>><?=$i?>. <?=$name?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Language:</label>
                    <select name="lang">
                        <option value="ur" <?= $_GET['lang']=='ur'?'selected':'' ?>>🇵🇰 Urdu</option>
                        <option value="en" <?= $_GET['lang']=='en'?'selected':'' ?>>🇺🇸 English</option>
                        <option value="bn" <?= $_GET['lang']=='bn'?'selected':'' ?>>🇧🇩 Bengali</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>From Ayah:</label>
                    <input type="number" name="from_ayah" value="<?= $_GET['from_ayah'] ?>" min="1">
                </div>
                <div class="form-group">
                    <label>To Ayah:</label>
                    <input type="number" name="to_ayah" value="<?= $_GET['to_ayah'] ?>" min="1">
                </div>
            </div>
            <button type="submit" class="btn">🔄 Load Ayahs</button>
        </form>
    </div>

    <?php if($_GET['surah']): ?>
    <?php 
    $s = intval($_GET['surah']);
    $lang = $_GET['lang'] ?: 'ur';
    $from = $_GET['from_ayah'] ? intval($_GET['from_ayah']) : 1;
    $to = $_GET['to_ayah'] ? intval($_GET['to_ayah']) : 999;
    
    $ayahs = $db->query("SELECT * FROM ayahs WHERE surah = $s AND language = '$lang' AND ayah BETWEEN $from AND $to ORDER BY ayah");
    $total = 0;
    ?>
    <div class="card">
        <h3>📜 Surah <?= $s ?> - <?= isset($surahs[$s]) ? $surahs[$s] : "Surah $s" ?></h3>
        <?php while($ayah = $ayahs->fetchArray(SQLITE3_ASSOC)): $total++; ?>
        <div class="ayah" id="ayah-<?= $ayah['surah'] ?>-<?= $ayah['ayah'] ?>">
            <div class="arabic"><?= htmlspecialchars($ayah['arabic']) ?></div>
            <div class="translation"><?= htmlspecialchars($ayah['translation']) ?></div>
            <div class="meta">
                <span>📍 Surah <?= $ayah['surah'] ?>, Ayah <?= $ayah['ayah'] ?></span>
                
<?php if($u): ?>
<div style="margin-top:1rem">
    <?php 
    // Check current status
    $recited_today = $db->querySingle("SELECT COUNT(*) FROM recitation_log WHERE user_id = {$u['id']} AND surah = {$ayah['surah']} AND ayah = {$ayah['ayah']} AND date = CURRENT_DATE");
    $is_bookmarked = $db->querySingle("SELECT COUNT(*) FROM bookmarks WHERE user_id = {$u['id']} AND surah = {$ayah['surah']} AND ayah = {$ayah['ayah']}");
    $is_memorized = $db->querySingle("SELECT COUNT(*) FROM hifz_progress WHERE user_id = {$u['id']} AND surah = {$ayah['surah']} AND ayah = {$ayah['ayah']} AND memorized = 1");
    ?>
    
    <form style="display:inline-block;margin-right:0.5rem" method="post" action="">
        <input type="hidden" name="action" value="log_recitation">
        <input type="hidden" name="surah" value="<?= $ayah['surah'] ?>">
        <input type="hidden" name="ayah" value="<?= $ayah['ayah'] ?>">
        <button type="submit" class="btn btn-sm <?= $recited_today ? 'btn-success' : '' ?>" <?= $recited_today ? 'disabled' : '' ?>>
            <?= $recited_today ? '✅ Recited Today' : '🎵 Log Recitation' ?>
        </button>
    </form>
    
    <?php if(!$is_bookmarked): ?>
    <button onclick="showBookmarkForm(<?= $ayah['surah'] ?>, <?= $ayah['ayah'] ?>)" class="btn btn-sm" style="margin-right:0.5rem">🔖 Bookmark</button>
    <?php else: ?>
    <button class="btn btn-sm btn-success" style="margin-right:0.5rem" disabled>✅ Bookmarked</button>
    <?php endif; ?>
    
    <form style="display:inline-block" method="post" action="">
        <input type="hidden" name="action" value="update_hifz">
        <input type="hidden" name="surah" value="<?= $ayah['surah'] ?>">
        <input type="hidden" name="ayah" value="<?= $ayah['ayah'] ?>">
        <input type="hidden" name="memorized" value="<?= $is_memorized ? 0 : 1 ?>">
        <button type="submit" class="btn btn-sm <?= $is_memorized ? 'btn-success' : '' ?>">
            <?= $is_memorized ? '✅ Memorized' : '🧠 Mark Memorized' ?>
        </button>
    </form>
</div>
<?php endif; ?>

            </div>
            
            <?php 
            $tf = $db->query("SELECT t.*, u.username, u.role FROM tafsir t JOIN users u ON t.user_id = u.id WHERE t.surah = {$ayah['surah']} AND t.ayah = {$ayah['ayah']} AND t.approved = 1 ORDER BY u.role DESC, t.created_at DESC");
            while($t = $tf->fetchArray(SQLITE3_ASSOC)):
            ?>
            <div class="contribution approved">
                <strong>📝 Tafsir by <?= htmlspecialchars($t['username']) ?> (<?= ucfirst($t['role']) ?>):</strong><br>
                <?= nl2br(htmlspecialchars($t['content'])) ?>
            </div>
            <?php endwhile; ?>
            
<?php 
$wm = $db->query("SELECT wm.*, w.ur_meaning, w.en_meaning FROM word_meta wm LEFT JOIN words w ON wm.word_id = w.word_id WHERE wm.surah = {$ayah['surah']} AND wm.ayah = {$ayah['ayah']} ORDER BY wm.position");
$word_meanings = [];
while($w = $wm->fetchArray(SQLITE3_ASSOC)):
    $word_meanings[] = $w;
endwhile;

if(count($word_meanings) > 0):
?>
<div class="word-analysis">
    <strong>🔤 Word-by-Word Analysis:</strong><br>
    
    <?php if(array_filter($word_meanings, function($w) { return $w['ur_meaning']; })): ?>
    <div style="margin:0.5rem 0">
        <strong>🇵🇰 Urdu:</strong><br>
        <?php foreach($word_meanings as $wm): ?>
            <?php if($wm['ur_meaning']): ?>
            <span class="word" title="Position: <?= $wm['position'] ?>, Word ID: <?= $wm['word_id'] ?>">
                <?= htmlspecialchars($wm['ur_meaning']) ?>
            </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if(array_filter($word_meanings, function($w) { return $w['en_meaning']; })): ?>
    <div style="margin:0.5rem 0">
        <strong>🇺🇸 English:</strong><br>
        <?php foreach($word_meanings as $wm): ?>
            <?php if($wm['en_meaning']): ?>
            <span class="word" title="Position: <?= $wm['position'] ?>, Word ID: <?= $wm['word_id'] ?>">
                <?= htmlspecialchars($wm['en_meaning']) ?>
            </span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
        </div>
        <?php endwhile; ?>
        
        <?php if($total == 0): ?>
        <div class="notification warning">
            No ayahs found for the selected criteria. Please check if data has been loaded for this language.
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if($page == 'personal' && $u): ?>
    <div class="card">
        <h2>👤 Personal Dashboard</h2>
        <div class="stats">
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM tafsir WHERE user_id = {$u['id']}") ?></h3>
                <p>My Tafsir</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM themes WHERE user_id = {$u['id']}") ?></h3>
                <p>My Themes</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(DISTINCT date) FROM recitation_log WHERE user_id = {$u['id']}") ?></h3>
                <p>Recitation Days</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM hifz_progress WHERE user_id = {$u['id']} AND memorized = 1") ?></h3>
                <p>Memorized Ayahs</p>
            </div>
        </div>
        
        <div style="margin-top:2rem">
            <form method="post" style="display:inline">
                <input type="hidden" name="action" value="export_personal">
                <button type="submit" class="btn">📥 Export Personal Data</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3>📊 Recent Activity</h3>
        <?php 
        $recent = $db->query("SELECT 'recitation' as type, surah, ayah, date as created_at FROM recitation_log WHERE user_id = {$u['id']} 
                             UNION ALL 
                             SELECT 'tafsir' as type, surah, ayah, created_at FROM tafsir WHERE user_id = {$u['id']}
                             ORDER BY created_at DESC LIMIT 10");
        while($r = $recent->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="padding:0.5rem;margin:0.5rem 0;background:#f8f9fa;border-radius:4px;border-left:4px solid #2c5aa0">
            <strong><?= ucfirst($r['type']) ?>:</strong> Surah <?= $r['surah'] ?>, Ayah <?= $r['ayah'] ?>
            <span style="float:right;color:#666"><?= date('M d, Y', strtotime($r['created_at'])) ?></span>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'tafsir' && $u): ?>
   <div class="card">
    <h2>📝 Personal Tafsir</h2>
    <form method="post">
        <input type="hidden" name="action" value="add_tafsir">
        <div class="grid">
            <div class="form-group">
                <label>Surah:</label>
                <select name="surah" required>
                    <option value="">Select Surah</option>
                    <?php 
                    $surahs = [
                        1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Aal-E-Imran', 4 => 'An-Nisa', 5 => 'Al-Maidah',
                        6 => 'Al-Anam', 7 => 'Al-Araf', 8 => 'Al-Anfal', 9 => 'At-Tawbah', 10 => 'Yunus'
                        // Add more surah names as needed
                    ];
                    for($i=1;$i<=114;$i++): 
                        $name = isset($surahs[$i]) ? $surahs[$i] : "Surah $i";
                    ?>
                    <option value="<?=$i?>"><?=$i?>. <?=$name?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ayah:</label>
                <select name="ayah" id="ayah-select" required>
                    <option value="">Select Surah first</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label>Tafsir Content:</label>
            <textarea name="content" placeholder="Write your understanding and interpretation of this ayah..." required></textarea>
        </div>
        <button type="submit" class="btn">💾 Save Tafsir</button>
        <?php if(!role('ulama')): ?>
        <div class="notification warning">Your tafsir will require approval before being visible to others.</div>
        <?php endif; ?>
    </form>
</div>


    <div class="card">
        <h3>📋 My Tafsir Collection</h3>
        <?php 
        $tf = $db->query("SELECT * FROM tafsir WHERE user_id = {$u['id']} ORDER BY created_at DESC");
        $count = 0;
        while($t = $tf->fetchArray(SQLITE3_ASSOC)): $count++;
        ?>
        <div class="contribution <?= $t['approved'] ? 'approved' : 'pending' ?>">
            <strong>📍 Surah <?= $t['surah'] ?>, Ayah <?= $t['ayah'] ?></strong>
            <?php if(!$t['approved']): ?><span style="color:orange"> (⏳ Pending Approval)</span><?php endif; ?>
            <div style="margin-top:0.5rem"><?= nl2br(htmlspecialchars($t['content'])) ?></div>
            <div style="margin-top:0.5rem;font-size:0.9rem;color:#666">
                Created: <?= date('M d, Y H:i', strtotime($t['created_at'])) ?>
            </div>
        </div>
        <?php endwhile; ?>
        
        <?php if($count == 0): ?>
        <div class="notification">You haven't created any tafsir yet. Start by adding your first interpretation above!</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'themes' && $u): ?>
    <div class="card">
    <h2>🏷️ Thematic Linker</h2>
    <form method="post">
        <input type="hidden" name="action" value="add_theme">
        <div class="form-group">
            <label>Theme Title:</label>
            <input type="text" name="title" placeholder="e.g., Patience in the Quran" required>
        </div>
        <div class="form-group">
            <label>Add Related Ayahs:</label>
            <div class="grid">
                <select id="theme-surah">
                    <option value="">Select Surah</option>
                    <?php for($i=1;$i<=114;$i++): 
                        $name = isset($surahs[$i]) ? $surahs[$i] : "Surah $i";
                    ?>
                    <option value="<?=$i?>"><?=$i?>. <?=$name?></option>
                    <?php endfor; ?>
                </select>
                <select id="theme-ayah">
                    <option value="">Select Ayah</option>
                </select>
                <button type="button" onclick="addAyahToTheme()" class="btn">➕ Add Ayah</button>
            </div>
            <div id="selected-ayahs" style="margin-top:1rem"></div>
            <input type="hidden" name="ayahs" id="ayahs-input" required>
        </div>
        <div class="form-group">
            <label>Theme Analysis:</label>
            <textarea name="content" placeholder="Describe how these ayahs relate to the theme..." required></textarea>
        </div>
        <button type="submit" class="btn">💾 Create Theme</button>
    </form>
</div>


    <div class="card">
        <h3>🌟 Public Themes</h3>
        <div class="filter-bar">
            <label>Filter by contributor type:</label>
            <select onchange="filterThemes(this.value)">
                <option value="all">All Contributors</option>
                <option value="admin">Admin</option>
                <option value="ulama">Ulama</option>
                <option value="user">Community</option>
            </select>
        </div>
        
        <?php 
        $th = $db->query("SELECT t.*, u.username, u.role FROM themes t JOIN users u ON t.user_id = u.id WHERE t.approved = 1 ORDER BY u.role DESC, t.created_at DESC");
        while($theme = $th->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div class="contribution approved theme-item" data-role="<?= $theme['role'] ?>">
            <h4>🏷️ <?= htmlspecialchars($theme['title']) ?></h4>
            <p><strong>👤 By:</strong> <?= htmlspecialchars($theme['username']) ?> (<?= ucfirst($theme['role']) ?>)</p>
            <p><strong>📍 Related Ayahs:</strong> <?= htmlspecialchars($theme['ayahs']) ?></p>
            <div style="margin-top:1rem"><?= nl2br(htmlspecialchars($theme['content'])) ?></div>
            <div style="margin-top:0.5rem;font-size:0.9rem;color:#666">
                Created: <?= date('M d, Y', strtotime($theme['created_at'])) ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <div class="card">
        <h3>📝 My Themes</h3>
        <?php 
        $my_th = $db->query("SELECT * FROM themes WHERE user_id = {$u['id']} ORDER BY created_at DESC");
        $my_count = 0;
        while($mt = $my_th->fetchArray(SQLITE3_ASSOC)): $my_count++;
        ?>
        <div class="contribution <?= $mt['approved'] ? 'approved' : 'pending' ?>">
            <h4><?= htmlspecialchars($mt['title']) ?></h4>
            <?php if(!$mt['approved']): ?><span style="color:orange"> (⏳ Pending Approval)</span><?php endif; ?>
            <p><strong>📍 Ayahs:</strong> <?= htmlspecialchars($mt['ayahs']) ?></p>
            <div><?= nl2br(htmlspecialchars($mt['content'])) ?></div>
        </div>
        <?php endwhile; ?>
        
        <?php if($my_count == 0): ?>
        <div class="notification">You haven't created any themes yet. Create your first thematic analysis above!</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'bookmarks' && $u): ?>
    <div class="card">
        <h2>🔖 My Bookmarks</h2>
        <?php 
        $bm = $db->query("SELECT b.*, a.arabic, a.translation FROM bookmarks b LEFT JOIN ayahs a ON b.surah = a.surah AND b.ayah = a.ayah WHERE b.user_id = {$u['id']} AND a.language = 'ur' ORDER BY b.created_at DESC");
        $bm_count = 0;
        while($bookmark = $bm->fetchArray(SQLITE3_ASSOC)): $bm_count++;
        ?>
        <div class="bookmark-item">
            <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div style="flex:1">
                    <strong>📍 Surah <?= $bookmark['surah'] ?>, Ayah <?= $bookmark['ayah'] ?></strong>
                    <?php if($bookmark['arabic']): ?>
                    <div class="arabic" style="font-size:1.2rem;margin:0.5rem 0"><?= htmlspecialchars($bookmark['arabic']) ?></div>
                    <div class="translation" style="font-size:1rem;margin:0.5rem 0"><?= htmlspecialchars($bookmark['translation']) ?></div>
                    <?php endif; ?>
                    <?php if($bookmark['note']): ?>
                    <div style="background:#f8f9fa;padding:0.5rem;border-radius:4px;margin-top:0.5rem">
                        <strong>📝 Note:</strong> <?= nl2br(htmlspecialchars($bookmark['note'])) ?>
                    </div>
                    <?php endif; ?>
                    <div style="font-size:0.9rem;color:#666;margin-top:0.5rem">
                        Bookmarked: <?= date('M d, Y H:i', strtotime($bookmark['created_at'])) ?>
                    </div>
                </div>
                <div>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="del_bookmark">
                        <input type="hidden" name="bookmark_id" value="<?= $bookmark['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this bookmark?')">🗑️ Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
        
        <?php if($bm_count == 0): ?>
        <div class="notification">No bookmarks yet. Visit the Quran viewer and bookmark ayahs you want to remember!</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<?php if($page == 'hifz' && $u): ?>
<div class="card">
    <h2>🧠 Hifz Hub - Memorization Tracker</h2>
    <form method="post">
        <input type="hidden" name="action" value="update_hifz">
        <div class="grid">
            <div class="form-group">
                <label>Surah:</label>
                <select name="surah" required>
                    <option value="">Select Surah</option>
                    <?php 
                    $surahs = [
                        1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Aal-E-Imran', 4 => 'An-Nisa', 5 => 'Al-Maidah',
                        6 => 'Al-Anam', 7 => 'Al-Araf', 8 => 'Al-Anfal', 9 => 'At-Tawbah', 10 => 'Yunus',
                        11 => 'Hud', 12 => 'Yusuf', 13 => 'Ar-Rad', 14 => 'Ibrahim', 15 => 'Al-Hijr',
                        16 => 'An-Nahl', 17 => 'Al-Isra', 18 => 'Al-Kahf', 19 => 'Maryam', 20 => 'Ta-Ha',
                        21 => 'Al-Anbiya', 22 => 'Al-Hajj', 23 => 'Al-Muminun', 24 => 'An-Nur', 25 => 'Al-Furqan',
                        26 => 'Ash-Shuara', 27 => 'An-Naml', 28 => 'Al-Qasas', 29 => 'Al-Ankabut', 30 => 'Ar-Rum',
                        31 => 'Luqman', 32 => 'As-Sajdah', 33 => 'Al-Ahzab', 34 => 'Saba', 35 => 'Fatir',
                        36 => 'Ya-Sin', 37 => 'As-Saffat', 38 => 'Sad', 39 => 'Az-Zumar', 40 => 'Ghafir',
                        41 => 'Fussilat', 42 => 'Ash-Shura', 43 => 'Az-Zukhruf', 44 => 'Ad-Dukhan', 45 => 'Al-Jathiyah',
                        46 => 'Al-Ahqaf', 47 => 'Muhammad', 48 => 'Al-Fath', 49 => 'Al-Hujurat', 50 => 'Qaf',
                        51 => 'Adh-Dhariyat', 52 => 'At-Tur', 53 => 'An-Najm', 54 => 'Al-Qamar', 55 => 'Ar-Rahman',
                        56 => 'Al-Waqiah', 57 => 'Al-Hadid', 58 => 'Al-Mujadila', 59 => 'Al-Hashr', 60 => 'Al-Mumtahanah',
                        61 => 'As-Saff', 62 => 'Al-Jumuah', 63 => 'Al-Munafiqun', 64 => 'At-Taghabun', 65 => 'At-Talaq',
                        66 => 'At-Tahrim', 67 => 'Al-Mulk', 68 => 'Al-Qalam', 69 => 'Al-Haqqah', 70 => 'Al-Maarij',
                        71 => 'Nuh', 72 => 'Al-Jinn', 73 => 'Al-Muzzammil', 74 => 'Al-Muddaththir', 75 => 'Al-Qiyamah',
                        76 => 'Al-Insan', 77 => 'Al-Mursalat', 78 => 'An-Naba', 79 => 'An-Naziat', 80 => 'Abasa',
                        81 => 'At-Takwir', 82 => 'Al-Infitar', 83 => 'Al-Mutaffifin', 84 => 'Al-Inshiqaq', 85 => 'Al-Buruj',
                        86 => 'At-Tariq', 87 => 'Al-Ala', 88 => 'Al-Ghashiyah', 89 => 'Al-Fajr', 90 => 'Al-Balad',
                        91 => 'Ash-Shams', 92 => 'Al-Layl', 93 => 'Ad-Duha', 94 => 'Ash-Sharh', 95 => 'At-Tin',
                        96 => 'Al-Alaq', 97 => 'Al-Qadr', 98 => 'Al-Bayyinah', 99 => 'Az-Zalzalah', 100 => 'Al-Adiyat',
                        101 => 'Al-Qariah', 102 => 'At-Takathur', 103 => 'Al-Asr', 104 => 'Al-Humazah', 105 => 'Al-Fil',
                        106 => 'Quraysh', 107 => 'Al-Maun', 108 => 'Al-Kawthar', 109 => 'Al-Kafirun', 110 => 'An-Nasr',
                        111 => 'Al-Masad', 112 => 'Al-Ikhlas', 113 => 'Al-Falaq', 114 => 'An-Nas'
                    ];
                    for($i=1;$i<=114;$i++): 
                        $name = isset($surahs[$i]) ? $surahs[$i] : "Surah $i";
                    ?>
                    <option value="<?=$i?>"><?=$i?>. <?=$name?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Ayah:</label>
                <select name="ayah" required>
                    <option value="">Select Surah first</option>
                </select>
            </div>
            <div class="form-group">
                <label>Memorization Status:</label>
                <select name="memorized">
                    <option value="0">❌ Not Memorized</option>
                    <option value="1">✅ Memorized</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn">💾 Update Progress</button>
    </form>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const s = document.querySelectorAll('select[name="surah"]');
            s.forEach(function(surahSelect) {
                surahSelect.addEventListener('change', function() {
                    const ayahSelect = this.closest('form').querySelector('select[name="ayah"]');
                    const surahValue = this.value;

                    if (ayahSelect && surahValue) {
                        const ayahCounts = {1:7,2:286,3:200,4:176,5:120,6:165,7:206,8:75,9:129,10:109,11:123,12:111,13:43,14:52,15:99,16:128,17:111,18:110,19:98,20:135,21:112,22:78,23:118,24:64,25:77,26:227,27:93,28:88,29:69,30:60,31:34,32:30,33:73,34:54,35:45,36:83,37:182,38:88,39:75,40:85,41:54,42:53,43:89,44:59,45:37,46:35,47:38,48:29,49:18,50:45,51:60,52:49,53:62,54:55,55:78,56:96,57:29,58:22,59:24,60:13,61:14,62:11,63:11,64:18,65:12,66:12,67:30,68:52,69:52,70:44,71:28,72:28,73:20,74:56,75:40,76:31,77:50,78:40,79:46,80:42,81:29,82:19,83:36,84:25,85:22,86:17,87:19,88:26,89:30,90:20,91:15,92:21,93:11,94:8,95:8,96:19,97:5,98:8,99:8,100:11,101:11,102:8,103:3,104:9,105:5,106:4,107:7,108:3,109:6,110:3,111:5,112:4,113:5,114:6};

                        const maxAyahs = ayahCounts[parseInt(surahValue)] || 300;

                        ayahSelect.innerHTML = '<option value="">Select Ayah</option>';
                        for (let i = 1; i <= maxAyahs; i++) {
                            ayahSelect.innerHTML += `<option value="${i}">Ayah ${i}</option>`;
                        }
                    }
                });
            });
        });
    </script>
</div>


    <div class="card">
        <h3>📊 Memorization Progress</h3>
        <?php 
        $total_memorized = $db->querySingle("SELECT COUNT(*) FROM hifz_progress WHERE user_id = {$u['id']} AND memorized = 1");
        $total_ayahs = 6236; // Approximate total ayahs in Quran
        $progress_percentage = $total_memorized > 0 ? ($total_memorized / $total_ayahs) * 100 : 0;
        ?>
        
        <div class="stats">
            <div class="stat">
                <h3><?= $total_memorized ?></h3>
                <p>Memorized Ayahs</p>
            </div>
            <div class="stat">
                <h3><?= number_format($progress_percentage, 1) ?>%</h3>
                <p>Overall Progress</p>
            </div>
        </div>
        
        <div style="margin:2rem 0">
            <label>Overall Progress:</label>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progress_percentage ?>%"></div>
            </div>
        </div>

        <h4>📋 Memorized Ayahs by Surah:</h4>
        <?php 
        $surah_progress = $db->query("SELECT surah, COUNT(*) as count, GROUP_CONCAT(ayah ORDER BY ayah) as ayahs FROM hifz_progress WHERE user_id = {$u['id']} AND memorized = 1 GROUP BY surah ORDER BY surah");
        while($sp = $surah_progress->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="margin:1rem 0;padding:1rem;background:#e8f4f8;border-radius:4px">
            <strong>📜 Surah <?= $sp['surah'] ?>:</strong> <?= $sp['count'] ?> ayahs
            <div style="margin-top:0.5rem">
                <?php 
                $ayahs = explode(',', $sp['ayahs']);
                foreach($ayahs as $ayah): 
                ?>
                <span class="word"><?= trim($ayah) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endwhile; ?>
        
        <?php if($total_memorized == 0): ?>
        <div class="notification">Start tracking your memorization progress above! Mark ayahs as memorized as you learn them.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'recitation' && $u): ?>
    <div class="card">
        <h2>🎵 Recitation Log</h2>
        <?php 
        $recent_recitations = $db->query("SELECT r.*, ROW_NUMBER() OVER (ORDER BY r.date DESC, r.surah, r.ayah) as row_num FROM recitation_log r WHERE r.user_id = {$u['id']} ORDER BY r.date DESC, r.surah, r.ayah LIMIT 50");
        $daily_stats = $db->query("SELECT date, COUNT(*) as count FROM recitation_log WHERE user_id = {$u['id']} GROUP BY date ORDER BY date DESC LIMIT 30");
        ?>
        
        <div class="stats">
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM recitation_log WHERE user_id = {$u['id']}") ?></h3>
                <p>Total Recitations</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(DISTINCT date) FROM recitation_log WHERE user_id = {$u['id']}") ?></h3>
                <p>Active Days</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM recitation_log WHERE user_id = {$u['id']} AND date = CURRENT_DATE") ?></h3>
                <p>Today's Recitations</p>
            </div>
        </div>

        <h3>📅 Daily Recitation Log</h3>
        <?php while($ds = $daily_stats->fetchArray(SQLITE3_ASSOC)): ?>
        <div style="display:flex;justify-content:space-between;padding:0.5rem;margin:0.5rem 0;background:#f8f9fa;border-radius:4px">
            <span><?= date('D, M d, Y', strtotime($ds['date'])) ?></span>
            <span><strong><?= $ds['count'] ?> recitations</strong></span>
        </div>
        <?php endwhile; ?>

        <h3>📋 Recent Recitations</h3>
        <?php while($r = $recent_recitations->fetchArray(SQLITE3_ASSOC)): ?>
        <div style="padding:0.5rem;margin:0.5rem 0;background:#e8f4f8;border-radius:4px;border-left:4px solid #2c5aa0">
            <strong>📍 Surah <?= $r['surah'] ?>, Ayah <?= $r['ayah'] ?></strong>
            <span style="float:right;color:#666"><?= date('M d, Y', strtotime($r['date'])) ?></span>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'roots' && $u): ?>
    <div class="card">
        <h2>🌳 Root Word Analyzer</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_root_analysis">
            <div class="form-group">
                <label>Arabic Root Word:</label>
                <input type="text" name="root_word" placeholder="e.g., ص ل ح" required>
            </div>
            <div class="form-group">
                <label>Root Analysis:</label>
                <textarea name="analysis" placeholder="Analyze the meanings and derivatives of this root..." required></textarea>
            </div>
            <button type="submit" class="btn">💾 Save Analysis</button>
            <?php if(!role('ulama')): ?>
            <div class="notification warning">Your root analysis will require approval before being visible to others.</div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>📚 Public Root Analyses</h3>
        <?php 
        $roots = $db->query("SELECT r.*, u.username, u.role FROM root_analysis r JOIN users u ON r.user_id = u.id WHERE r.approved = 1 ORDER BY u.role DESC, r.created_at DESC");
        while($root = $roots->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div class="contribution approved">
            <div class="root-word"><?= htmlspecialchars($root['root_word']) ?></div>
            <strong>👤 Analysis by <?= htmlspecialchars($root['username']) ?> (<?= ucfirst($root['role']) ?>)</strong>
            <div style="margin-top:1rem"><?= nl2br(htmlspecialchars($root['analysis'])) ?></div>
            <div style="margin-top:0.5rem;font-size:0.9rem;color:#666">
                Created: <?= date('M d, Y', strtotime($root['created_at'])) ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <div class="card">
        <h3>📝 My Root Analyses</h3>
        <?php 
        $my_roots = $db->query("SELECT * FROM root_analysis WHERE user_id = {$u['id']} ORDER BY created_at DESC");
        $root_count = 0;
        while($mr = $my_roots->fetchArray(SQLITE3_ASSOC)): $root_count++;
        ?>
        <div class="contribution <?= $mr['approved'] ? 'approved' : 'pending' ?>">
            <div class="root-word"><?= htmlspecialchars($mr['root_word']) ?></div>
            <?php if(!$mr['approved']): ?><span style="color:orange"> (⏳ Pending Approval)</span><?php endif; ?>
            <div style="margin-top:1rem"><?= nl2br(htmlspecialchars($mr['analysis'])) ?></div>
        </div>
        <?php endwhile; ?>
        
        <?php if($root_count == 0): ?>
        <div class="notification">You haven't analyzed any root words yet. Start by adding your first analysis above!</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'search'): ?>
    <div class="card">
        <h2>🔍 Advanced Quran Search</h2>
        <form method="post">
            <input type="hidden" name="action" value="search">
            <div class="grid">
                <div class="form-group">
                    <label>Search Query:</label>
                    <input type="text" name="query" value="<?= htmlspecialchars($_POST['query'] ?? '') ?>" placeholder="Search in Arabic text or translation..." required>
                </div>
                <div class="form-group">
                    <label>Language:</label>
                    <select name="search_lang">
                        <option value="ur" <?= ($_POST['search_lang'] ?? '') == 'ur' ? 'selected' : '' ?>>🇵🇰 Urdu</option>
                        <option value="en" <?= ($_POST['search_lang'] ?? '') == 'en' ? 'selected' : '' ?>>🇺🇸 English</option>
                        <option value="bn" <?= ($_POST['search_lang'] ?? '') == 'bn' ? 'selected' : '' ?>>🇧🇩 Bengali</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">🔍 Search Quran</button>
        </form>
    </div>

<?php if(isset($results)): ?>
<div class="card">
    <h3>🎯 Search Results for "<?= htmlspecialchars($_POST['query']) ?>" (<?= count($matching_ayahs) ?> found)</h3>
    <?php foreach($matching_ayahs as $r): ?>
    <div class="ayah">
        <div class="arabic"><?= htmlspecialchars($r['arabic']) ?></div>
        <div class="translation"><?= htmlspecialchars($r['translation']) ?></div>
        <div class="meta">
            <span>📍 Surah <?= $r['surah'] ?>, Ayah <?= $r['ayah'] ?></span>
            <a href="?page=viewer&surah=<?= $r['surah'] ?>&from_ayah=<?= $r['ayah'] ?>&to_ayah=<?= $r['ayah'] ?>" class="btn btn-sm" style="margin-left:1rem">👁️ View Context</a>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if(count($matching_ayahs) == 0): ?>
    <div class="notification warning">No results found for your search query. Try different keywords or check spelling.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

    <?php endif; ?>

    <?php if($page == 'games'): ?>
    <?php
    // Fetch data for games dynamically from the database

    // Word Whiz: Get random word entries with meanings and their actual Arabic text
    // Join word_meta (wm), words (w), and quran_word_text (q)
    $word_entries_query = $db->query("
        SELECT wm.word_id, w.ur_meaning, w.en_meaning, q.quran_text
        FROM word_meta wm
        JOIN words w ON wm.word_id = w.word_id
        JOIN quran_word_text q ON wm.word_id = q.word_id -- Join with the new table
        WHERE (w.ur_meaning IS NOT NULL AND w.ur_meaning != '') OR (w.en_meaning IS NOT NULL AND w.en_meaning != '')
        AND q.quran_text IS NOT NULL AND q.quran_text != '' -- Ensure Arabic text exists
        GROUP BY wm.word_id -- Group by word_id to get unique word entries across the Quran
        ORDER BY RANDOM() LIMIT 300 -- Fetch a pool of up to 300 unique words
    ");

    $word_list_for_games = [];
    while ($row = $word_entries_query->fetchArray(SQLITE3_ASSOC)) {
        $word_list_for_games[] = $row;
    }

    // Ayah Jumble & Memory: Fetch a separate pool of random full ayahs for these games
     $ayahs_query = $db->query("SELECT surah, ayah, arabic FROM ayahs WHERE language = 'ur' AND arabic IS NOT NULL AND arabic != '' AND LENGTH(arabic) BETWEEN 30 AND 250 ORDER BY RANDOM() LIMIT 100"); // Fetch a pool of 100 ayahs of medium length
    $quran_ayahs_for_jumble_memory = [];
    while ($a = $ayahs_query->fetchArray(SQLITE3_ASSOC)) {
        $quran_ayahs_for_jumble_memory[] = $a;
    }


    // Fetch Surah names (for Memory game display) - This list is hardcoded as it's static
    $surahNames = [
        1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Aal-E-Imran', 4 => 'An-Nisa', 5 => 'Al-Maidah',
        6 => 'Al-Anam', 7 => 'Al-Araf', 8 => 'Al-Anfal', 9 => 'At-Tawbah', 10 => 'Yunus',
        11 => 'Hud', 12 => 'Yusuf', 13 => 'Ar-Rad', 14 => 'Ibrahim', 15 => 'Al-Hijr',
        16 => 'An-Nahl', 17 => 'Al-Isra', 18 => 'Al-Kahf', 19 => 'Maryam', 20 => 'Ta-Ha',
        21 => 'Al-Anbiya', 22 => 'Al-Hajj', 23 => 'Al-Muminun', 24 => 'An-Nur', 25 => 'Al-Furqan',
        26 => 'Ash-Shuara', 27 => 'An-Naml', 28 => 'Al-Qasas', 29 => 'Al-Ankabut', 30 => 'Ar-Rum',
        31 => 'Luqman', 32 => 'As-Sajdah', 33 => 'Al-Ahzab', 34 => 'Saba', 35 => 'Fatir',
        36 => 'Ya-Sin', 37 => 'As-Saffat', 38 => 'Sad', 39 => 'Az-Zumar', 40 => 'Ghafir',
        41 => 'Fussilat', 42 => 'Ash-Shura', 43 => 'Az-Zukhruf', 44 => 'Ad-Dukhan', 45 => 'Al-Jathiyah',
        46 => 'Al-Ahqaf', 47 => 'Muhammad', 48 => 'Al-Fath', 49 => 'Al-Hujurat', 50 => 'Qaf',
        51 => 'Adh-Dhariyat', 52 => 'At-Tur', 53 => 'An-Najm', 54 => 'Al-Qamar', 55 => 'Ar-Rahman',
        56 => 'Al-Waqiah', 57 => 'Al-Hadid', 58 => 'Al-Mujadila', 59 => 'Al-Hashr', 60 => 'Al-Mumtahanah',
        61 => 'As-Saff', 62 => 'Al-Jumuah', 63 => 'Al-Munafiqun', 64 => 'At-Taghabun', 65 => 'At-Talaq',
        66 => 'At-Tahrim', 67 => 'Al-Mulk', 68 => 'Al-Qalam', 69 => 'Al-Haqqah', 70 => 'Al-Maarij',
        71 => 'Nuh', 72 => 'Al-Jinn', 73 => 'Al-Muzzammil', 74 => 'Al-Muddaththir', 75 => 'Al-Qiyamah',
        76 => 'Al-Insan', 77 => 'Al-Mursalat', 78 => 'An-Naba', 79 => 'An-Naziat', 80 => 'Abasa',
        81 => 'At-Takwir', 82 => 'Al-Infitar', 83 => 'Al-Mutaffifin', 84 => 'Al-Inshiqaq', 85 => 'Al-Buruj',
        86 => 'At-Tariq', 87 => 'Al-Ala', 88 => 'Al-Ghashiyah', 89 => 'Al-Fajr', 90 => 'Al-Balad',
        91 => 'Ash-Shams', 92 => 'Al-Layl', 93 => 'Ad-Duha', 94 => 'Ash-Sharh', 95 => 'At-Tin',
        96 => 'Al-Alaq', 97 => 'Al-Qadr', 98 => 'Al-Bayyinah', 99 => 'Az-Zalzalah', 100 => 'Al-Adiyat',
        101 => 'Al-Qariah', 102 => 'At-Takathur', 103 => 'Al-Asr', 104 => 'Al-Humazah', 105 => 'Al-Fil',
        106 => 'Quraysh', 107 => 'Al-Maun', 108 => 'Al-Kawthar', 109 => 'Al-Kafirun', 110 => 'An-Nasr',
        111 => 'Al-Masad', 112 => 'Al-Ikhlas', 113 => 'Al-Falaq', 114 => 'An-Nas'
    ];
    ?>
    <!-- Embed fetched data in hidden divs as JSON strings -->
    <div id="quran-word-data" data-json='<?= htmlspecialchars(json_encode($word_list_for_games), ENT_QUOTES, 'UTF-8') ?>'></div>
    <div id="quran-ayahs-data" data-json='<?= htmlspecialchars(json_encode($quran_ayahs_for_jumble_memory), ENT_QUOTES, 'UTF-8') ?>'></div>
    <div id="surah-names-data" data-json='<?= htmlspecialchars(json_encode($surahNames), ENT_QUOTES, 'UTF-8') ?>'></div>


    <!-- Rest of the HTML for the games page starts here -->
    <div class="tabs">
        <div class="tab active" onclick="showGame('whiz')">🧩 Word Whiz</div>
        <div class="tab" onclick="showGame('jumble')">🔀 Ayah Jumble</div>
        <div class="tab" onclick="showGame('memory')">🧠 Memory Challenge</div>
    </div>

    <div id="whiz" class="game-area">
        <h2>🧩 Word Whiz - Arabic-English Matching</h2>
        <p>Match Arabic words with their English meanings!</p>
        <div id="whiz-game">
            <button onclick="startWhiz()" class="btn">🎮 Start Game</button>
        </div>
        <div class="score">Score: <span id="whiz-score">0</span></div>
        <div id="whiz-result"></div>
    </div>

    <div id="jumble" class="game-area hidden">
        <h2>🔀 Ayah Jumble - Word Arrangement</h2>
        <p>Arrange the Arabic words in the correct order!</p>
        <div id="jumble-game">
            <button onclick="startJumble()" class="btn">🎮 Start Game</button>
        </div>
        <div class="score">Score: <span id="jumble-score">0</span></div>
        <div id="jumble-result"></div>
    </div>

    <div id="memory" class="game-area hidden">
        <h2>🧠 Memory Challenge - Surah & Ayah</h2>
        <p>Test your knowledge of Surah and Ayah numbers!</p>
        <div id="memory-game">
            <button onclick="startMemory()" class="btn">🎮 Start Challenge</button>
        </div>
        <div class="score">Score: <span id="memory-score">0</span></div>
        <div id="memory-result"></div>
    </div>
    <?php endif; ?>
    
    <?php if($page == 'contributions'): ?>
    <div class="card">
        <h2>🤝 Community Contributions</h2>
        <div class="filter-bar">
            <label>Filter by type:</label>
            <select onchange="filterContributions(this.value)">
                <option value="all">All Types</option>
                <option value="tafsir">Tafsir</option>
                <option value="themes">Themes</option>
                <option value="roots">Root Analysis</option>
            </select>
            <label style="margin-left:2rem">Filter by contributor:</label>
            <select onchange="filterByRole(this.value)">
                <option value="all">All Contributors</option>
                <option value="admin">Admin</option>
                <option value="ulama">Ulama</option>
                <option value="user">Community</option>
            </select>
        </div>
    
        <h3>📝 Community Tafsir</h3>
        <div id="tafsir-contributions">
        <?php 
        $ct = $db->query("SELECT t.*, u.username, u.role FROM tafsir t JOIN users u ON t.user_id = u.id WHERE t.approved = 1 ORDER BY u.role DESC, t.created_at DESC LIMIT 20");
        while($ct_item = $ct->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div class="contribution approved contrib-item" data-type="tafsir" data-role="<?= $ct_item['role'] ?>">
            <strong>📍 Surah <?= $ct_item['surah'] ?>, Ayah <?= $ct_item['ayah'] ?></strong>
            <span style="color:#666"> - by <?= htmlspecialchars($ct_item['username']) ?> (<?= ucfirst($ct_item['role']) ?>)</span>
            <div style="margin-top:1rem"><?= nl2br(htmlspecialchars($ct_item['content'])) ?></div>
            <div style="margin-top:0.5rem;font-size:0.9rem;color:#666">
                <?= date('M d, Y', strtotime($ct_item['created_at'])) ?>
            </div>
        </div>
        <?php endwhile; ?>
        </div>
    
        <h3>🏷️ Community Themes</h3>
        <div id="theme-contributions">
        <?php 
        $cth = $db->query("SELECT t.*, u.username, u.role FROM themes t JOIN users u ON t.user_id = u.id WHERE t.approved = 1 ORDER BY u.role DESC, t.created_at DESC LIMIT 10");
        while($cth_item = $cth->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div class="contribution approved contrib-item" data-type="themes" data-role="<?= $cth_item['role'] ?>">
            <h4><?= htmlspecialchars($cth_item['title']) ?></h4>
            <span style="color:#666">by <?= htmlspecialchars($cth_item['username']) ?> (<?= ucfirst($cth_item['role']) ?>)</span>
            <p><strong>📍 Ayahs:</strong> <?= htmlspecialchars($cth_item['ayahs']) ?></p>
            <div style="margin-top:1rem"><?= nl2br(htmlspecialchars($cth_item['content'])) ?></div>
        </div>
        <?php endwhile; ?>
        </div>
    
        <h3>🌳 Root Word Analyses</h3>
        <div id="root-contributions">
        <?php 
        $cr = $db->query("SELECT r.*, u.username, u.role FROM root_analysis r JOIN users u ON r.user_id = u.id WHERE r.approved = 1 ORDER BY u.role DESC, r.created_at DESC LIMIT 10");
        while($cr_item = $cr->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div class="contribution approved contrib-item" data-type="roots" data-role="<?= $cr_item['role'] ?>">
            <div class="root-word"><?= htmlspecialchars($cr_item['root_word']) ?></div>
            <span style="color:#666">by <?= htmlspecialchars($cr_item['username']) ?> (<?= ucfirst($cr_item['role']) ?>)</span>
            <div style="margin-top:1rem"><?= nl2br(htmlspecialchars($cr_item['analysis'])) ?></div>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if($page == 'review' && role('ulama')): ?>
    <div class="tabs">
        <div class="tab active" onclick="showReview('tafsir')">📝 Tafsir</div>
        <div class="tab" onclick="showReview('themes')">🏷️ Themes</div>
        <div class="tab" onclick="showReview('roots')">🌳 Roots</div>
    </div>
    
    <div id="tafsir-review">
        <div class="card">
            <h2>📝 Pending Tafsir Review</h2>
            <?php 
            $pt = $db->query("SELECT t.*, u.username FROM tafsir t JOIN users u ON t.user_id = u.id WHERE t.approved = 0 ORDER BY t.created_at ASC");
            $pt_count = 0;
            while($pt_item = $pt->fetchArray(SQLITE3_ASSOC)): $pt_count++;
            ?>
            <div class="contribution pending">
                <strong>📍 Surah <?= $pt_item['surah'] ?>, Ayah <?= $pt_item['ayah'] ?></strong>
                <span style="color:#666"> - by <?= htmlspecialchars($pt_item['username']) ?></span>
                <div style="margin:1rem 0"><?= nl2br(htmlspecialchars($pt_item['content'])) ?></div>
                <div style="margin-top:1rem">
                    <form style="display:inline" method="post">
                        <input type="hidden" name="action" value="approve_content">
                        <input type="hidden" name="content_id" value="<?= $pt_item['id'] ?>">
                        <input type="hidden" name="table" value="tafsir">
                        <button type="submit" class="btn">✅ Approve</button>
                    </form>
                    <button class="btn btn-danger" onclick="rejectContent('tafsir', <?= $pt_item['id'] ?>)">❌ Reject</button>
                </div>
            </div>
            <?php endwhile; ?>
            
            <?php if($pt_count == 0): ?>
            <div class="notification">No pending tafsir for review.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="themes-review" class="hidden">
        <div class="card">
            <h2>🏷️ Pending Themes Review</h2>
            <?php 
            $pth = $db->query("SELECT t.*, u.username FROM themes t JOIN users u ON t.user_id = u.id WHERE t.approved = 0 ORDER BY t.created_at ASC");
            $pth_count = 0;
            while($pth_item = $pth->fetchArray(SQLITE3_ASSOC)): $pth_count++;
            ?>
            <div class="contribution pending">
                <h4><?= htmlspecialchars($pth_item['title']) ?></h4>
                <span style="color:#666">by <?= htmlspecialchars($pth_item['username']) ?></span>
                <p><strong>📍 Ayahs:</strong> <?= htmlspecialchars($pth_item['ayahs']) ?></p>
                <div style="margin:1rem 0"><?= nl2br(htmlspecialchars($pth_item['content'])) ?></div>
                <div style="margin-top:1rem">
                    <form style="display:inline" method="post">
                        <input type="hidden" name="action" value="approve_content">
                        <input type="hidden" name="content_id" value="<?= $pth_item['id'] ?>">
                        <input type="hidden" name="table" value="themes">
                        <button type="submit" class="btn">✅ Approve</button>
                    </form>
                    <button class="btn btn-danger" onclick="rejectContent('themes', <?= $pth_item['id'] ?>)">❌ Reject</button>
                </div>
            </div>
            <?php endwhile; ?>
            
            <?php if($pth_count == 0): ?>
            <div class="notification">No pending themes for review.</div>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="roots-review" class="hidden">
        <div class="card">
            <h2>🌳 Pending Root Analyses Review</h2>
            <?php 
            $pr = $db->query("SELECT r.*, u.username FROM root_analysis r JOIN users u ON r.user_id = u.id WHERE r.approved = 0 ORDER BY r.created_at ASC");
            $pr_count = 0;
            while($pr_item = $pr->fetchArray(SQLITE3_ASSOC)): $pr_count++;
            ?>
            <div class="contribution pending">
                <div class="root-word"><?= htmlspecialchars($pr_item['root_word']) ?></div>
                <span style="color:#666">by <?= htmlspecialchars($pr_item['username']) ?></span>
                <div style="margin:1rem 0"><?= nl2br(htmlspecialchars($pr_item['analysis'])) ?></div>
                <div style="margin-top:1rem">
                    <form style="display:inline" method="post">
                        <input type="hidden" name="action" value="approve_content">
                        <input type="hidden" name="content_id" value="<?= $pr_item['id'] ?>">
                        <input type="hidden" name="table" value="root_analysis">
                        <button type="submit" class="btn">✅ Approve</button>
                    </form>
                    <button class="btn btn-danger" onclick="rejectContent('roots', <?= $pr_item['id'] ?>)">❌ Reject</button>
                </div>
            </div>
            <?php endwhile; ?>
            
            <?php if($pr_count == 0): ?>
            <div class="notification">No pending root analyses for review.</div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if($page == 'admin' && role('admin')): ?>
    <div class="tabs">
        <div class="tab active" onclick="showAdmin('data')">📊 Data Management</div>
        <div class="tab" onclick="showAdmin('users')">👥 User Management</div>
        <div class="tab" onclick="showAdmin('content')">📝 Content Management</div>
        <div class="tab" onclick="showAdmin('stats')">📈 Statistics</div>
    </div>
    
    <div id="data" class="card">
        <h2>📊 Quran Data Management</h2>
        <div class="card" style="margin:0">
                <h3>📖 Load Quran Arabic Words</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="load_quran_words">
                    <div class="form-group">
                        <label>Quran Words File (CSV):</label>
                        <input type="file" name="quran_words_file" accept=".csv" required>
                        <small>Format: word\_id,quran\_text</small>
                    </div>
                    <button type="submit" class="btn">📥 Load Quran Words</button>
                </form>
            </div>
        <div class="grid">
            <div class="card" style="margin:0">
                <h3>📖 Load Ayah Data</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="load_data">
                    <div class="form-group">
                        <label>Ayah Data File (.AM):</label>
                        <input type="file" name="data_file" accept=".AM" required>
                        <small>Expected format: [Arabic] ترجمہ: [Translation]&lt;br/&gt;س [Surah] آ [Ayah]</small>
                    </div>
                    <div class="form-group">
                        <label>Language:</label>
                        <select name="language" required>
                            <option value="ur">🇵🇰 Urdu</option>
                            <option value="en">🇺🇸 English</option>
                            <option value="bn">🇧🇩 Bengali</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">📥 Load Ayah Data</button>
                </form>
            </div>
    
            <div class="card" style="margin:0">
                <h3>🔤 Load Word Meanings</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="load_words">
                    <div class="form-group">
                        <label>Word Meanings File (CSV):</label>
                        <input type="file" name="word_file" accept=".AM,.csv" required>
                        <small>Format: word_id,ur_meaning,en_meaning</small>
                    </div>
                    <button type="submit" class="btn">📥 Load Word Data</button>
                </form>
            </div>
    
            <div class="card" style="margin:0">
                <h3>📍 Load Word Metadata</h3>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="load_word_meta">
                    <div class="form-group">
                        <label>Word Metadata File (CSV):</label>
                        <input type="file" name="meta_file" accept=".AM,.csv" required>
                        <small>Format: word_id,surah,ayah,position</small>
                    </div>
                    <button type="submit" class="btn">📥 Load Word Meta</button>
                </form>
            </div>
        </div>
    
        <div class="stats" style="margin-top:2rem">
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM ayahs") ?></h3>
                <p>Total Ayahs Loaded</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM words") ?></h3>
                <p>Word Meanings</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM word_meta") ?></h3>
                <p>Word Positions</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(DISTINCT language) FROM ayahs") ?></h3>
                <p>Languages</p>
            </div>
        </div>
    </div>
    
    <div id="users" class="card hidden">
        <h2>👥 User Management</h2>
        
        <h3>⏳ Pending Registrations</h3>
        <?php 
        $pu = $db->query("SELECT * FROM users WHERE approved = 0 ORDER BY id ASC");
        $pending_count = 0;
        while($pending_user = $pu->fetchArray(SQLITE3_ASSOC)): $pending_count++;
        ?>
        <div class="contribution pending">
            <strong>👤 <?= htmlspecialchars($pending_user['username']) ?></strong>
            <span style="color:#666"> - Registration pending since user ID <?= $pending_user['id'] ?></span>
            <div style="margin-top:1rem">
                <form style="display:inline" method="post">
                    <input type="hidden" name="action" value="approve_user">
                    <input type="hidden" name="user_id" value="<?= $pending_user['id'] ?>">
                    <button type="submit" class="btn">✅ Approve</button>
                </form>
                <button class="btn btn-danger" onclick="deleteUser(<?= $pending_user['id'] ?>)">❌ Reject</button>
            </div>
        </div>
        <?php endwhile; ?>
        
        <?php if($pending_count == 0): ?>
        <div class="notification">No pending registrations.</div>
        <?php endif; ?>
    
        <h3>👥 All Active Users</h3>
        <?php 
        $au = $db->query("SELECT * FROM users WHERE approved = 1 ORDER BY role DESC, username ASC");
        ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;margin-top:1rem">
                <tr style="background:#f8f9fa">
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Username</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Current Role</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Actions</th>
                </tr>
                <?php while($active_user = $au->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td style="padding:1rem;border:1px solid #ddd"><?= htmlspecialchars($active_user['username']) ?></td>
                    <td style="padding:1rem;border:1px solid #ddd">
                        <span style="background:<?= $active_user['role']=='admin'?'#dc3545':($active_user['role']=='ulama'?'#ffc107':'#28a745') ?>;color:white;padding:0.3rem 0.6rem;border-radius:4px;font-size:0.9rem">
                            <?= ucfirst($active_user['role']) ?>
                        </span>
                    </td>
                    <td style="padding:1rem;border:1px solid #ddd">
                        <?php if($active_user['username'] != 'admin'): ?>
                        <form style="display:inline" method="post">
                            <input type="hidden" name="action" value="promote_user">
                            <input type="hidden" name="user_id" value="<?= $active_user['id'] ?>">
                            <select name="new_role" style="width:auto;margin-right:0.5rem">
                                <option value="user" <?= $active_user['role']=='user'?'selected':'' ?>>User</option>
                                <option value="ulama" <?= $active_user['role']=='ulama'?'selected':'' ?>>Ulama</option>
                                <option value="admin" <?= $active_user['role']=='admin'?'selected':'' ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn btn-sm">🔄 Update</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#666">System Admin</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
    
    <div id="content" class="card hidden">
        <h2>📝 Content Management</h2>
        
        <div class="stats">
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM tafsir WHERE approved = 0") ?></h3>
                <p>Pending Tafsir</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM themes WHERE approved = 0") ?></h3>
                <p>Pending Themes</p>
            </div>
            <div class="stat">
                <h3><?= $db->querySingle("SELECT COUNT(*) FROM root_analysis WHERE approved = 0") ?></h3>
                <p>Pending Root Analyses</p>
            </div>
        </div>
    
        <p><a href="?page=review" class="btn">👨‍🏫 Go to Review Panel</a></p>
    
        <h3>📊 Content Overview</h3>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;margin-top:1rem">
                <tr style="background:#f8f9fa">
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Content Type</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Total</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Approved</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Pending</th>
                </tr>
                <tr>
                    <td style="padding:1rem;border:1px solid #ddd">📝 Tafsir</td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM tafsir") ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM tafsir WHERE approved = 1") ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM tafsir WHERE approved = 0") ?></td>
                </tr>
                <tr>
                    <td style="padding:1rem;border:1px solid #ddd">🏷️ Themes</td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM themes") ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM themes WHERE approved = 1") ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM themes WHERE approved = 0") ?></td>
                </tr>
                <tr>
                    <td style="padding:1rem;border:1px solid #ddd">🌳 Root Analysis</td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM root_analysis") ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM root_analysis WHERE approved = 1") ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $db->querySingle("SELECT COUNT(*) FROM root_analysis WHERE approved = 0") ?></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div id="stats" class="card hidden">
        <h2>📈 Platform Statistics</h2>
        
        <div class="grid">
            <div class="stats">
                <div class="stat">
                    <h3><?= $db->querySingle("SELECT COUNT(*) FROM users WHERE approved = 1") ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat">
                    <h3><?= $db->querySingle("SELECT COUNT(*) FROM recitation_log") ?></h3>
                    <p>Total Recitations</p>
                </div>
                <div class="stat">
                    <h3><?= $db->querySingle("SELECT COUNT(*) FROM bookmarks") ?></h3>
                    <p>Total Bookmarks</p>
                </div>
                <div class="stat">
                    <h3><?= $db->querySingle("SELECT COUNT(*) FROM hifz_progress WHERE memorized = 1") ?></h3>
                    <p>Memorized Ayahs</p>
                </div>
            </div>
        </div>
    
        <h3>📊 Most Active Users</h3>
        <?php 
        $active_users = $db->query("SELECT u.username, u.role, 
                                   (SELECT COUNT(*) FROM tafsir WHERE user_id = u.id) as tafsir_count,
                                   (SELECT COUNT(*) FROM recitation_log WHERE user_id = u.id) as recitation_count,
                                   (SELECT COUNT(*) FROM hifz_progress WHERE user_id = u.id AND memorized = 1) as hifz_count
                                   FROM users u WHERE u.approved = 1 ORDER BY (tafsir_count + recitation_count + hifz_count) DESC LIMIT 10");
        ?>
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;margin-top:1rem">
                <tr style="background:#f8f9fa">
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">User</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Role</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Tafsir</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Recitations</th>
                    <th style="padding:1rem;text-align:left;border:1px solid #ddd">Hifz</th>
                </tr>
                <?php while($au = $active_users->fetchArray(SQLITE3_ASSOC)): ?>
                <tr>
                    <td style="padding:1rem;border:1px solid #ddd"><?= htmlspecialchars($au['username']) ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= ucfirst($au['role']) ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $au['tafsir_count'] ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $au['recitation_count'] ?></td>
                    <td style="padding:1rem;border:1px solid #ddd"><?= $au['hifz_count'] ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
    <?php endif; ?>
    </div>

<script>
// Global variables for scores and theme ayahs
let whizScore = 0;
let jumbleScore = 0;
let memoryScore = 0;
let selectedAyahs = []; // For themes page

// Global variables to hold loaded data from hidden divs
let quranWordDataWithArabic = [];
let quranAyahsForJumbleMemory = [];
let surahNames = {};

// --- Function to load data from hidden divs ---
function loadGameData() {
    const wordDataEl = document.getElementById('quran-word-data');
    const ayahsDataEl = document.getElementById('quran-ayahs-data');
    const surahNamesEl = document.getElementById('surah-names-data');

    if (wordDataEl && wordDataEl.dataset.json) {
        try {
            quranWordDataWithArabic = JSON.parse(wordDataEl.dataset.json);
            console.log('Parsed word entries with Arabic text for Word Whiz:', quranWordDataWithArabic.length);
        } catch (e) {
            console.error('Error parsing quran-word-data:', e);
            document.getElementById('whiz-game').innerHTML = '<div class="notification error">Error loading word data for games.</div>';
        }
    } else {
         console.warn('No quran-word-data element or data found.');
         document.getElementById('whiz-game').innerHTML = '<div class="notification warning">No word data found for games. Please load data from Admin > Data Management.</div>';
    }

     if (ayahsDataEl && ayahsDataEl.dataset.json) {
        try {
            quranAyahsForJumbleMemory = JSON.parse(ayahsDataEl.dataset.json);
             console.log('Parsed ayahs for Jumble/Memory:', quranAyahsForJumbleMemory.length);
        } catch (e) {
            console.error('Error parsing quran-ayahs-data:', e);
             document.getElementById('jumble-game').innerHTML = '<div class="notification error">Error loading ayah data for Jumble/Memory.</div>';
             document.getElementById('memory-game').innerHTML = '<div class="notification error">Error loading ayah data for Jumble/Memory.</div>';
        }
    } else {
         console.warn('No quran-ayahs-data element or data found.');
         document.getElementById('jumble-game').innerHTML = '<div class="notification warning">No ayah data found for Jumble/Memory. Please load data from Admin > Data Management.</div>';
         document.getElementById('memory-game').innerHTML = '<div class="notification warning">No ayah data found for Jumble/Memory. Please load data from Admin > Data Management.</div>';
    }

     if (surahNamesEl && surahNamesEl.dataset.json) {
        try {
             surahNames = JSON.parse(surahNamesEl.dataset.json);
             console.log('Parsed surah names.');
        } catch (e) {
            console.error('Error parsing surah-names-data:', e);
             // This error is less critical, just log it
        }
    } else {
         console.warn('No surah-names-data element or data found.');
    }
}


// --- Helper function (Still needed for Jumble/Memory if you revert to parsing full text) ---
// If you are using quran_word_text for Jumble/Memory words directly, this might not be needed.
// Based on the previous plan, Jumble/Memory still use the full ayah text split. So keep this if needed.
/*
function getArabicWordByPosition(fullText, position) {
    if (!fullText || position < 1) return null;
    // Split by one or more spaces, filter empty strings
    const words = fullText.split(/\s+/).filter(word => word.trim() !== '');
    // position is 1-based, array index is 0-based
    if (position > 0 && position <= words.length) {
        return words[position - 1];
    }
    return null; // Position out of bounds
}
*/


// --- General Tab/Section Display Functions ---
function showGame(g) {
    document.querySelectorAll('.game-area').forEach(el => el.classList.add('hidden'));
    document.getElementById(g).classList.remove('hidden');
    document.querySelectorAll('.tabs .tab').forEach(el => el.classList.remove('active'));
    // Find the correct tab based on game id and activate it
    let gameIdMap = {'whiz': 'Word Whiz', 'jumble': 'Ayah Jumble', 'memory': 'Memory Challenge'};
    document.querySelectorAll('.tabs .tab').forEach(el => {
        if (el.textContent.includes(gameIdMap[g])) {
            el.classList.add('active');
        }
    });
    // If starting a game section, try to load data
     loadGameData();
}

function showAdmin(s) {
    document.querySelectorAll('#data, #users, #content, #stats').forEach(el => el.classList.add('hidden'));
    document.getElementById(s).classList.remove('hidden');
    document.querySelectorAll('.tabs .tab').forEach(el => el.classList.remove('active'));
     let adminIdMap = {'data': 'Data Management', 'users': 'User Management', 'content': 'Content Management', 'stats': 'Statistics'};
      document.querySelectorAll('.tabs .tab').forEach(el => {
        if (el.textContent.includes(adminIdMap[s])) {
            el.classList.add('active');
        }
    });
}

function showReview(s) {
    document.querySelectorAll('#tafsir-review, #themes-review, #roots-review').forEach(el => el.classList.add('hidden'));
    document.getElementById(s + '-review').classList.remove('hidden');
    document.querySelectorAll('.tabs .tab').forEach(el => el.classList.remove('active'));
     let reviewIdMap = {'tafsir': 'Tafsir', 'themes': 'Themes', 'roots': 'Roots'};
     document.querySelectorAll('.tabs .tab').forEach(el => {
        if (el.textContent.includes(reviewIdMap[s])) {
            el.classList.add('active');
        }
    });
}

// --- Viewer Page Functions ---
function showBookmarkForm(s, a) {
    // Check if form already exists
    let form = document.getElementById(`bookmark-form-${s}-${a}`);
    if (form) {
        form.classList.remove('hidden');
        return;
    }

    // Create form on the fly
    const ayahDiv = document.getElementById(`ayah-${s}-${a}`);
    if (ayahDiv) {
        const formHtml = `
            <div id="bookmark-form-${s}-${a}" style="margin-top:1rem;background:#f8f9fa;padding:1rem;border-radius:4px">
                <form method="post" action="">
                    <input type="hidden" name="action" value="add_bookmark">
                    <input type="hidden" name="surah" value="${s}">
                    <input type="hidden" name="ayah" value="${a}">
                    <div class="form-group">
                        <label>Bookmark Note:</label>
                        <textarea name="note" placeholder="Optional note about this ayah..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-sm">💾 Save Bookmark</button>
                    <button type="button" onclick="hideBookmarkForm(${s}, ${a})" class="btn btn-sm btn-danger">❌ Cancel</button>
                </form>
            </div>
        `;
        ayahDiv.insertAdjacentHTML('beforeend', formHtml);
        ayahDiv.querySelector('textarea[name="note"]').focus(); // Focus the textarea
    }
}

function hideBookmarkForm(s, a) {
    const form = document.getElementById(`bookmark-form-${s}-${a}`);
    if (form) {
        form.remove();
    }
}

// --- Themes Page Functions ---
function filterThemes(role) {
    const items = document.querySelectorAll('.theme-item');
    items.forEach(item => {
        if(role === 'all' || item.dataset.role === role) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

// --- Community Page Functions ---
function filterContributions(type) {
    const items = document.querySelectorAll('.contrib-item');
    items.forEach(item => {
        // Hide all first, then show based on filter
        item.style.display = 'none';
    });
     document.querySelectorAll(`.contrib-item[data-type="${type}"]`).forEach(item => {
          item.style.display = 'block';
     });
     if (type === 'all') {
         document.querySelectorAll('.contrib-item').forEach(item => item.style.display = 'block');
     }
}

function filterByRole(role) {
    const items = document.querySelectorAll('.contrib-item');
    items.forEach(item => {
        // Hide all first, then show based on filter
        item.style.display = 'none';
    });
     document.querySelectorAll(`.contrib-item[data-role="${role}"]`).forEach(item => {
          item.style.display = 'block';
     });
     if (role === 'all') {
         document.querySelectorAll('.contrib-item').forEach(item => item.style.display = 'block');
     }
}


// --- Games Page Functions ---

// --- Word Whiz Game ---
// --- Word Whiz Game ---
function startWhiz() {
    whizScore = 0; // Reset score per round for Word Whiz
    document.getElementById('whiz-score').textContent = whizScore;
    document.getElementById('whiz-result').innerHTML = ''; // Clear previous result message

    if (!quranWordDataWithArabic || quranWordDataWithArabic.length < 5) {
        document.getElementById('whiz-game').innerHTML = '<div class="notification warning">Not enough word data with Arabic text loaded for this game. Please load data from Admin > Data Management.</div><button onclick="startWhiz()" class="btn">🔄 New Round</button>'; // Re-enable button just in case
        console.error("Not enough data for Word Whiz:", quranWordDataWithArabic ? quranWordDataWithArabic.length : 0);
        return;
    }

    // Select 5 random unique word entries from the pool that have AT LEAST ONE meaning
    let selectedWordEntries = [];
    let availableIndices = Array.from(Array(quranWordDataWithArabic.length).keys());

    // Filter available indices to only include entries with AT LEAST one meaning and Arabic text
    availableIndices = availableIndices.filter(index => {
        const entry = quranWordDataWithArabic[index];
        return entry.quran_text && (entry.ur_meaning || entry.en_meaning);
    });

     if (availableIndices.length < 5) {
         document.getElementById('whiz-game').innerHTML = '<div class="notification warning">Could not find 5 unique word entries with Arabic text and at least one meaning. Please load more data.</div><button onclick="startWhiz()" class="btn">🔄 New Round</button>'; // Re-enable button
          console.warn("Failed to find enough suitable word entries:", availableIndices.length);
         return;
     }


    // Shuffle available indices and take the first 5
    availableIndices.sort(() => Math.random() - 0.5);

    for(let i = 0; i < 5; i++) {
        const dataIndex = availableIndices[i]; // Take from the shuffled array
        selectedWordEntries.push(quranWordDataWithArabic[dataIndex]);
    }

    let matchPairs = [];
    let allMeanings = []; // This will hold the meanings to be displayed in the dropdown

    selectedWordEntries.forEach(entry => {
        let targetMeaning = null;
        let chosenLang = null;

        // --- FIX: Randomly choose English or Urdu meaning if both exist ---
        if (entry.en_meaning && entry.ur_meaning) {
            if (Math.random() < 0.5) { // 50% chance
                targetMeaning = entry.en_meaning;
                chosenLang = 'en';
            } else {
                targetMeaning = entry.ur_meaning;
                chosenLang = 'ur';
            }
        } else if (entry.en_meaning) {
            targetMeaning = entry.en_meaning;
            chosenLang = 'en';
        } else if (entry.ur_meaning) {
            targetMeaning = entry.ur_meaning;
            chosenLang = 'ur';
        }
        // --- End FIX ---


        if (targetMeaning) {
             matchPairs.push({
                 arabic: entry.quran_text,
                 meaning: targetMeaning,
                 wordId: entry.word_id, // Keep word_id for reference if needed
                 lang: chosenLang // Store which language was chosen for this pair
             });
             allMeanings.push(targetMeaning);
        }
    });

    // Ensure we actually got 5 valid pairs (might be fewer if some entries lacked meanings after filter)
     if (matchPairs.length < 5) {
          console.warn(`Created only ${matchPairs.length} valid pairs from selected entries, trying again.`);
          startWhiz(); // Try again if not enough valid pairs were created
          return;
     }


    let shuffledMeanings = [...allMeanings].sort(() => Math.random() - 0.5);

    let html = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin:2rem 0">';
    matchPairs.forEach((pair, index) => { // Add index back to identify the match pair div
        // Escape quotes and double quotes for HTML attributes
        const escapedMeaning = pair.meaning.replace(/"/g, '"').replace(/'/g, '\'');

        html += `<div class="whiz-match-pair" data-pair-index="${index}" style="text-align:center;padding:1rem;border:2px solid #ddd;border-radius:8px">
            <div class="whiz-arabic-word" style="font-size:1.8rem;margin-bottom:1rem;font-family:serif;direction:rtl;text-align:right;"
                 title="Word ID: ${pair.wordId}">
                ${pair.arabic}
            </div>
            <select onchange="checkWhizAnswer(this)" data-correct-meaning="${escapedMeaning}" style="width:100%">
                <option value="">Select meaning</option>
                ${shuffledMeanings.map((m) => {
                     // Escape meanings for option values as well
                    const escapedOptionMeaning = m.replace(/"/g, '"').replace(/'/g, '\'');
                    return `<option value="${escapedOptionMeaning}">${m}</option>`;
                }).join('')}
            </select>
        </div>`;
    });
    html += '</div><button onclick="startWhiz()" class="btn">🔄 New Round</button>';
    document.getElementById('whiz-game').innerHTML = html;
}

function checkWhizAnswer(selectElement) {
    const selectedMeaning = selectElement.value;
    const correctMeaning = selectElement.dataset.correctMeaning; // This is the escaped meaning

    if (selectedMeaning !== '') {
        // Need to unescape selectedMeaning from the <option> value before comparing
        const unescapedSelectedMeaning = selectedMeaning.replace(/"/g, '"').replace(/'/g, "'");
         const unescapedCorrectMeaning = correctMeaning.replace(/"/g, '"').replace(/'/g, "'");

        const correct = unescapedSelectedMeaning === unescapedCorrectMeaning;

        if (correct) {
            selectElement.style.background = '#d4edda'; // Light green
            selectElement.style.borderColor = '#c3e6cb';
            selectElement.style.color = '#155724';
        } else {
            selectElement.style.background = '#f8d7da'; // Light red
            selectElement.style.borderColor = '#f5c6cb';
            selectElement.style.color = '#721c24';
             // --- FIX: Correctly target the Arabic word div for logging ---
             const parentDiv = selectElement.closest('.whiz-match-pair'); // Find the parent container
             const arabicWordDiv = parentDiv ? parentDiv.querySelector('.whiz-arabic-word') : null; // Find the Arabic word div inside it

             if (arabicWordDiv) {
                 console.log(`Incorrect match for "${arabicWordDiv.textContent.trim()}". Correct meaning is "${unescapedCorrectMeaning}"`);
             } else {
                  console.log(`Incorrect match. Correct meaning is "${unescapedCorrectMeaning}". Could not find Arabic word element.`);
             }
             // --- End FIX ---
        }
        selectElement.disabled = true; // Disable the select element after selection

        // Check if all select elements are answered (disabled)
        const allSelects = document.querySelectorAll('#whiz-game select');
        let allAnswered = true;
        allSelects.forEach(s => {
            if (!s.disabled) {
                 allAnswered = false;
            }
        });

        // If all are answered, check if all are correct and update score
        if (allAnswered) {
            let allCorrect = true;
            allSelects.forEach(s => {
                 // Compare unescaped values here too
                 const s_selected = s.value.replace(/"/g, '"').replace(/'/g, "'");
                 const s_correct = s.dataset.correctMeaning.replace(/"/g, '"').replace(/'/g, "'");
                 if (s_selected !== s_correct) {
                     allCorrect = false;
                 }
            });

            if (allCorrect) {
                 whizScore += 50; // Grant points for completing the round correctly
                 document.getElementById('whiz-score').textContent = whizScore;
                 document.getElementById('whiz-result').innerHTML = '<div class="notification btn-success">Round Complete! All matches are correct!</div>';
            } else {
                 document.getElementById('whiz-result').innerHTML = '<div class="notification btn-danger">Round Complete! Some matches were incorrect. Try again!</div>';
            }
        }
    }
}

// --- Ayah Jumble Game ---
let jumbleCorrect = [];
let jumbleSelected = [];

function startJumble() {
    // jumbleScore = 0; // Don't reset score here, score per round is added on check
    document.getElementById('jumble-score').textContent = jumbleScore;
    document.getElementById('jumble-result').innerHTML = ''; // Clear previous result message

    if (!quranAyahsForJumbleMemory || quranAyahsForJumbleMemory.length === 0) {
        document.getElementById('jumble-game').innerHTML = '<div class="notification warning">No ayah data loaded to start the game. Please load data from Admin > Data Management.</div><button onclick="startJumble()" class="btn">➡️ New Ayah</button>'; // Re-enable button
         console.error("No data for Ayah Jumble");
        return;
    }

    // Select one random ayah object
    const selectedAyah = quranAyahsForJumbleMemory[Math.floor(Math.random() * quranAyahsForJumbleMemory.length)];
    const ayahArabicText = selectedAyah.arabic;

    // Simple split by space for words, filtering out empty strings and punctuation if necessary (basic filter)
    // Using a regex that preserves some common attached particles like و, ف, ب, ل, ك etc. might be better,
    // but a simple space split aligns with the word_meta table's likely structure. Stick to space split.
    const words = ayahArabicText.split(/\s+/).filter(word => word.trim() !== '');
    if (words.length < 3 || words.length > 15) { // Ensure ayah has enough words, but not too many
         console.warn(`Selected ayah (S${selectedAyah.surah}:A${selectedAyah.ayah}) has ${words.length} words, picking another.`);
         startJumble(); // Try again
         return;
    }

    let shuffledWords = [...words].sort(() => Math.random() - 0.5);
     // Ensure shuffled is different from correct (simple check)
     let attempts = 0;
     while (shuffledWords.join(' ') === words.join(' ') && words.length > 1 && attempts < 10) {
          shuffledWords.sort(() => Math.random() - 0.5); // Re-shuffle
          attempts++;
     }
     if (shuffledWords.join(' ') === words.join(' ') && words.length > 1) {
        shuffledWords.reverse(); // Last resort simple shuffle
     }


    // Store the correct order globally for checking
    jumbleCorrect = words;
    jumbleSelected = []; // Reset selected words for the new round

    let html = '<div style="margin:2rem 0">';
     html += `<div style="font-size:1.2rem;margin-bottom:1rem;text-align:center;color:#555">Surah ${selectedAyah.surah}, Ayah ${selectedAyah.ayah}</div>`; // Show reference
    html += '<div style="margin-bottom:2rem;min-height:4rem;border:2px dashed #ddd;padding:1rem;border-radius:8px;background:#f8f9fa;direction:rtl;text-align:right;user-select: none;" id="answer-area">Arrange words here in correct order</div>';
    html += '<div style="text-align:center;direction:rtl;user-select: none;">'; // Arabic text direction for words, prevent accidental text selection
    shuffledWords.forEach((word, i) => {
        // Use a data attribute to store the original text, escape single quotes for onclick
        const escapedWord = word.replace(/'/g, "\\'").replace(/"/g, '"');
        html += `<span class="word" onclick="selectJumbleWord(this)" data-word-text="${escapedWord}" data-original-order="${i}">${word}</span>`;
    });
    html += '</div>';
    html += '<div style="margin-top:2rem;text-align:center">';
    html += '<button onclick="checkJumble()" class="btn">✅ Check Answer</button>';
    html += '<button onclick="resetJumble()" class="btn btn-danger" style="margin-left:1rem">🔄 Reset</button>';
    html += '<button onclick="startJumble()" class="btn" style="margin-left:1rem">➡️ New Ayah</button>';
    html += '</div></div>';
    document.getElementById('jumble-game').innerHTML = html;
}

function selectJumbleWord(wordElement) {
    // Check if the element has already been selected or is disabled
    if (wordElement.classList.contains('selected') || wordElement.classList.contains('disabled')) return;

    wordElement.classList.add('selected');
     const wordText = wordElement.dataset.wordText || wordElement.textContent.trim(); // Use data attribute or text
    jumbleSelected.push(wordText.replace(/\\'/g, "'").replace(/"/g, '"')); // Unescape for internal array

    const answerArea = document.getElementById('answer-area');
     answerArea.innerHTML = jumbleSelected.map(w => `<span style="display:inline-block; margin: 0 0.2rem;">${w}</span>`).join('') || 'Arrange words here in correct order';
}

function checkJumble() {
     // Compare the joined strings
    const correct = jumbleSelected.join(' ') === jumbleCorrect.join(' ');
    const answerArea = document.getElementById('answer-area');
    const wordSpans = document.querySelectorAll('#jumble-game .word');


    if (correct) {
        jumbleScore += 50; // Grant points for correct arrangement
        document.getElementById('jumble-score').textContent = jumbleScore;
        answerArea.style.background = '#d4edda'; // Light green
        answerArea.style.borderColor = '#c3e6cb';
         answerArea.style.color = '#155724';
         answerArea.innerHTML = '✅ Correct! ' + jumbleCorrect.join(' '); // Show the correct order

         // Mark all selected words as correct
         wordSpans.forEach(w => {
             if (w.classList.contains('selected')) {
                 w.classList.remove('selected');
                 w.classList.add('correct');
             }
              w.onclick = null; // Disable clicks
         });

         document.getElementById('jumble-result').innerHTML = '<div class="notification btn-success">Correct Arrangement!</div>';


    } else {
        answerArea.style.background = '#f8d7da'; // Light red
        answerArea.style.borderColor = '#f5c6cb';
        answerArea.style.color = '#721c24';
        answerArea.innerHTML = '❌ Incorrect. Correct order: ' + jumbleCorrect.join(' '); // Show the correct order

         // Mark selected words as incorrect and disable them
         wordSpans.forEach(w => {
             if (w.classList.contains('selected')) {
                 w.classList.remove('selected');
                 w.classList.add('incorrect');
                 w.onclick = null; // Disable clicks
             }
         });
          document.getElementById('jumble-result').innerHTML = '<div class="notification btn-danger">Incorrect Arrangement. Reset or try a New Ayah!</div>';
    }

     // Disable check button until next round
     document.querySelector('#jumble-game .btn').disabled = true;
}

function resetJumble() {
    jumbleSelected = [];
     document.getElementById('jumble-result').innerHTML = ''; // Clear result message
    document.querySelectorAll('#jumble-game .word').forEach(w => {
        w.classList.remove('selected', 'correct', 'incorrect', 'disabled');
        // Re-enable click handlers
        // Need to re-fetch the original word text from data attribute
        // Ensure click handler is re-added correctly
        w.onclick = function() { selectJumbleWord(this); };
    });
    document.getElementById('answer-area').innerHTML = 'Arrange words here in correct order';
    document.getElementById('answer-area').style.background = '#f8f9fa';
    document.getElementById('answer-area').style.borderColor = '#ddd';
     document.getElementById('answer-area').style.color = ''; // Reset color

     // Re-enable check button
     document.querySelector('#jumble-game .btn').disabled = false;
}


// --- Memory Challenge Game ---
// This game is about identifying the Surah and Ayah number for a random Arabic ayah
function startMemory() {
    // memoryScore = 0; // Don't reset score here, score per round is added on check
    document.getElementById('memory-score').textContent = memoryScore;
    document.getElementById('memory-result').innerHTML = ''; // Clear previous result message


     if (!quranAyahsForJumbleMemory || quranAyahsForJumbleMemory.length < 4) { // Need at least 4 unique ayahs for choices
        document.getElementById('memory-game').innerHTML = '<div class="notification warning">Not enough ayah data loaded for this game. Please load data from Admin > Data Management.</div><button onclick="startMemory()" class="btn">➡️ Next Question</button>'; // Re-enable button
         console.error("No data for Memory Challenge");
        return;
    }

    // Select one random ayah to be the question
     const questionAyahIndex = Math.floor(Math.random() * quranAyahsForJumbleMemory.length);
    const questionAyah = quranAyahsForJumbleMemory[questionAyahIndex];

    // Generate answer options (including the correct one and 3 incorrect ones)
    let answerOptions = [{
        text: `Surah ${questionAyah.surah}, Ayah ${questionAyah.ayah}`,
        isCorrect: true,
        ayahRef: `${questionAyah.surah}:${questionAyah.ayah}`
    }];

    // Select 3 random unique incorrect ayahs for options
    let incorrectAyahsIndices = [];
    let availableIndices = Array.from(Array(quranAyahsForJumbleMemory.length).keys()).filter(i => i !== questionAyahIndex); // Exclude the question ayah index

     // Shuffle available indices and take the first 3 (or fewer if less than 3 available)
     availableIndices.sort(() => Math.random() - 0.5);

    for(let i = 0; i < 3 && availableIndices.length > 0; i++) {
         const dataIndex = availableIndices[i]; // Take from the shuffled array
         const incorrectAyah = quranAyahsForJumbleMemory[dataIndex];
         answerOptions.push({
             text: `Surah ${incorrectAyah.surah}, Ayah ${incorrectAyah.ayah}`,
             isCorrect: false,
             ayahRef: `${incorrectAyah.surah}:${incorrectAyah.ayah}`
         });
    }

    // Shuffle the answer options
    answerOptions.sort(() => Math.random() - 0.5);

    let html = '<div style="margin:2rem 0;text-align:center">';
    html += `<h3 style="margin-bottom:1rem">Identify the Surah and Ayah number for this verse:</h3>`;
    html += `<div class="arabic" style="font-size:1.8rem;margin-bottom:2rem;direction:rtl;text-align:right;max-width:800px;margin:0 auto 2rem auto;">${questionAyah.arabic}</div>`;
    html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;max-width:600px;margin:0 auto">';

    answerOptions.forEach((option) => {
        // Store isCorrect state in a data attribute on the button
        html += `<button class="btn" style="padding:1rem;" data-is-correct="${option.isCorrect}">${option.text}</button>`;
    });

    html += '</div>';
     html += '<button onclick="startMemory()" class="btn" style="margin-top:2rem">➡️ Next Question</button>'; // Next button always visible
    html += '</div>';

    document.getElementById('memory-game').innerHTML = html;

    // Add click listeners to the answer buttons AFTER they are added to the DOM
    document.querySelectorAll('#memory-game .grid button').forEach(button => {
        button.addEventListener('click', function() {
            checkMemoryAnswer(this);
        });
    });
}

function checkMemoryAnswer(clickedButton) {
    const isCorrect = clickedButton.dataset.isCorrect === 'true'; // Read boolean from data attribute
    const buttons = document.querySelectorAll('#memory-game .grid button'); // Select only the answer buttons

    // Disable all answer buttons
    buttons.forEach(btn => {
       btn.disabled = true;
    });

    if (isCorrect) {
        memoryScore += 15; // Grant points for correct answer
        document.getElementById('memory-score').textContent = memoryScore;
        clickedButton.style.background = '#28a745'; // Green
        clickedButton.style.color = 'white';
        clickedButton.classList.add('correct');
        document.getElementById('memory-result').innerHTML = '<div class="notification btn-success">Correct!</div>';
    } else {
        clickedButton.style.background = '#dc3545'; // Red
        clickedButton.style.color = 'white';
        clickedButton.classList.add('incorrect');
        document.getElementById('memory-result').innerHTML = '<div class="notification btn-danger">Incorrect.</div>';

        // Highlight the correct answer
        buttons.forEach(btn => {
             if (btn.dataset.isCorrect === 'true') {
                 btn.style.background = '#d4edda'; // Light green for the correct one
                 btn.style.borderColor = '#c3e6cb';
                 btn.style.color = '#155724';
             }
        });
    }
     // The "Next Question" button is separate and not affected by disabling answer buttons
}


// --- Shared Functions (used by multiple pages) ---

// Keep the Surah/Ayah dropdown logic for Tafsir/Hifz/Viewer pages
document.addEventListener('DOMContentLoaded', function() {
    // Load game data when the page loads (in case the 'games' page is the landing page)
    loadGameData(); // Call the data loading function here


    const s = document.querySelectorAll('select[name="surah"]');
    s.forEach(function(surahSelect) {
        surahSelect.addEventListener('change', function() {
            const ayahSelect = this.closest('form').querySelector('select[name="ayah"]');
            const surahValue = parseInt(this.value); // Parse to integer

            if (ayahSelect && !isNaN(surahValue) && surahValue > 0 && surahValue <= 114) {
                // Use the getMaxAyahs function to get the count
                const maxAyahs = getMaxAyahs(surahValue);

                ayahSelect.innerHTML = '<option value="">Select Ayah</option>';
                for (let i = 1; i <= maxAyahs; i++) {
                    ayahSelect.innerHTML += `<option value="${i}">Ayah ${i}</option>`;
                }
                 // If there was a specific ayah selected in $_GET/$_POST, try to select it
                 // This requires passing GET/POST values into JS somehow, which is not done globally yet.
                 // For now, just populating the options based on surah is sufficient.
                 // You could add data attributes to the select on page load to store initial GET/POST values.
            } else if (ayahSelect) {
                 ayahSelect.innerHTML = '<option value="">Select Surah first</option>';
            }
        });
         // Trigger change on load if a surah is already selected (e.g., on Tafsir page reload)
         // Added check to ensure it's a valid number before triggering
         if (surahSelect.value && !isNaN(parseInt(surahSelect.value))) {
              surahSelect.dispatchEvent(new Event('change'));
         }
    });

     // Initial setup for filters on Community page if it's the active page
     // Note: Need to check if these elements exist before adding listeners or calling functions
     const communityFilterType = document.querySelector('#contributions .filter-bar select:nth-of-type(1)');
     const communityFilterRole = document.querySelector('#contributions .filter-bar select:nth-of-type(2)');

     if(communityFilterType) {
        communityFilterType.addEventListener('change', function() {
            filterContributions(this.value);
        });
         // Trigger initial filter if needed, or just let the initial load handle it
         // filterContributions(communityFilterType.value); // Or 'all' - uncomment if needed
     }
      if(communityFilterRole) {
        communityFilterRole.addEventListener('change', function() {
            filterByRole(this.value);
        });
         // filterByRole(communityFilterRole.value); // Or 'all' - uncomment if needed
     }

     // Ensure initial tab is shown on Games, Admin, Review pages if applicable
      const gamesTabs = document.querySelectorAll('.game-area');
      if(gamesTabs.length > 0) {
           // Find the active tab element first
           // Assuming the tab links within the page's tab bar are used for initial state
           const pageTabs = document.querySelectorAll('.tabs .tab');
           const activePageTabEl = document.querySelector('.tabs .tab.active');

           if (activePageTabEl) {
               // Get the target div id from the onclick attribute (e.g., showGame('whiz') -> 'whiz')
               const onclickAttr = activePageTabEl.getAttribute('onclick');
               const match = onclickAttr ? onclickAttr.match(/showGame\('([^']+)'\)/) : null;
               if (match && match[1]) {
                   showGame(match[1]); // Call showGame to activate the tab and load data
               } else {
                    showGame('whiz'); // Default if onclick is unexpected
               }
           } else {
               showGame('whiz'); // Default if no active tab
           }
      }

      const adminSections = document.querySelectorAll('#data, #users, #content, #stats');
       if(adminSections.length > 0) {
            const pageTabs = document.querySelectorAll('.tabs .tab');
            const activePageTabEl = document.querySelector('.tabs .tab.active');
            if (activePageTabEl) {
                 const onclickAttr = activePageTabEl.getAttribute('onclick');
                 const match = onclickAttr ? onclickAttr.match(/showAdmin\('([^']+)'\)/) : null;
                 if (match && match[1]) {
                    showAdmin(match[1]);
                } else {
                    showAdmin('data'); // Default
                }
            } else {
                showAdmin('data'); // Default
            }
       }

       const reviewSections = document.querySelectorAll('#tafsir-review, #themes-review, #roots-review');
        if(reviewSections.length > 0) {
            const pageTabs = document.querySelectorAll('.tabs .tab');
            const activePageTabEl = document.querySelector('.tabs .tab.active');
            if (activePageTabEl) {
                 const onclickAttr = activePageTabEl.getAttribute('onclick');
                 const match = onclickAttr ? onclickAttr.match(/showReview\('([^']+)'\)/) : null;
                 if (match && match[1]) {
                    showReview(match[1]);
                } else {
                    showReview('tafsir'); // Default
                }
            } else {
                showReview('tafsir'); // Default
            }
       }
});


// Keep the getMaxAyahs function as is (used by surah/ayah dropdowns)
function getMaxAyahs(surah) {
    const ayahCounts = {1:7,2:286,3:200,4:176,5:120,6:165,7:206,8:75,9:129,10:109,11:123,12:111,13:43,14:52,15:99,16:128,17:111,18:110,19:98,20:135,21:112,22:78,23:118,24:64,25:77,26:227,27:93,28:88,29:69,30:60,31:34,32:30,33:73,34:54,35:45,36:83,37:182,38:88,39:75,40:85,41:54,42:53,43:89,44:59,45:37,46:35,47:38,48:29,49:18,50:45,51:60,52:49,53:62,54:55,55:78,56:96,57:29,58:22,59:24,60:13,61:14,62:11,63:11,64:18,65:12,66:12,67:30,68:52,69:52,70:44,71:28,72:28,73:20,74:56,75:40,76:31,77:50,78:40,79:46,80:42,81:29,82:19,83:36,84:25,85:22,86:17,87:19,88:26,89:30,90:20,91:15,92:21,93:11,94:8,95:8,96:19,97:5,98:8,99:8,100:11,101:11,102:8,103:3,104:9,105:5,106:4,107:7,108:3,109:6,110:3,111:5,112:4,113:5,114:6};
    return ayahCounts[surah] || 300; // Fallback
}

// Keep the theme ayah selection logic as is
// selectedAyahs is declared at the top of the script block

function addAyahToTheme() {
    const surah = document.getElementById('theme-surah').value;
    const ayah = document.getElementById('theme-ayah').value;

    if (surah && ayah) {
        const ayahRef = `${surah}:${ayah}`;
        if (!selectedAyahs.includes(ayahRef)) {
            selectedAyahs.push(ayahRef);
            updateSelectedAyahs();
        }
        document.getElementById('theme-surah').value = '';
        document.getElementById('theme-ayah').innerHTML = '<option value="">Select Ayah</option>';
    }
}

function updateSelectedAyahs() {
    const container = document.getElementById('selected-ayahs');
    // Use a data attribute or a class to distinguish these word-like spans
    container.innerHTML = selectedAyahs.map(ayah =>
        `<span class="word" onclick="removeAyah('${ayah}')">${ayah} ❌</span>`
    ).join('');
    document.getElementById('ayahs-input').value = selectedAyahs.join(',');
}

function removeAyah(ayah) {
    selectedAyahs = selectedAyahs.filter(a => a !== ayah);
    updateSelectedAyahs();
}

// Add placeholder functions for admin/review actions (needs PHP backend implementation)
function deleteUser(userId) {
     if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
         // Send a POST request to the server
         const form = document.createElement('form');
         form.method = 'post';
         form.action = ''; // Submit to the current page

         const actionInput = document.createElement('input');
         actionInput.type = 'hidden';
         actionInput.name = 'action';
         actionInput.value = 'delete_user'; // You need to add this action in your PHP
         form.appendChild(actionInput);

         const idInput = document.createElement('input');
         idInput.type = 'hidden';
         idInput.name = 'user_id';
         idInput.value = userId;
         form.appendChild(idInput);

         document.body.appendChild(form); // Append form to body to submit
         form.submit(); // Submit the form
     }
}

function rejectContent(table, contentId) {
     if (confirm(`Are you sure you want to reject this content (ID ${contentId})? This will permanently remove it.`)) {
         // Send a POST request to the server
         const form = document.createElement('form');
         form.method = 'post';
         form.action = ''; // Submit to the current page

         const actionInput = document.createElement('input');
         actionInput.type = 'hidden';
         actionInput.name = 'action';
         actionInput.value = 'reject_content'; // You need to add this action in your PHP
         form.appendChild(actionInput);

          const tableInput = document.createElement('input');
         tableInput.type = 'hidden';
         tableInput.name = 'table';
         tableInput.value = table;
         form.appendChild(tableInput);

         const idInput = document.createElement('input');
         idInput.type = 'hidden';
         idInput.name = 'content_id';
         idInput.value = contentId;
         form.appendChild(idInput);

         document.body.appendChild(form); // Append form to body to submit
         form.submit(); // Submit the form
     }
}

</script>
</body>
</html>

<script>
const fontUrl = "https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap";

const link = document.createElement("link");
link.rel = "stylesheet";
link.href = fontUrl;
document.head.appendChild(link);

const style = document.createElement("style");
style.innerHTML = `
  * {
    font-family: Calibri, 'Noto Nastaliq Urdu', "Jameel Noori Nastaleeq" !important;
  }
  input, textarea, select, button {
    font-family: Calibri, 'Noto Nastaliq Urdu', "Jameel Noori Nastaleeq" !important;
  }
`;
document.head.appendChild(style);
</script>