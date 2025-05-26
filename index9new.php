<?php
ini_set('display_errors', 0);
session_start();
$db = new SQLite3('quran4.db');

$db->exec("
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
                    <input type="number" name="surah" min="1" max="114" required>
                </div>
                <div class="form-group">
                    <label>Ayah:</label>
                    <input type="number" name="ayah" min="1" required>
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
                <label>Related Ayahs (format: 2:255,3:1-5,18:10):</label>
                <input type="text" name="ayahs" placeholder="2:255,3:1-5,18:10" required>
                <small>Use comma-separated format. Ranges supported with hyphen.</small>
            </div>
            <div class="form-group">
                <label>Theme Analysis:</label>
                <textarea name="content" placeholder="Describe how these ayahs relate to the theme..." required></textarea>
            </div>
            <button type="submit" class="btn">💾 Create Theme</button>
            <?php if(!role('ulama')): ?>
            <div class="notification warning">Your theme will require approval before being visible to others.</div>
            <?php endif; ?>
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
                    <input type="number" name="surah" min="1" max="114" required>
                </div>
                <div class="form-group">
                    <label>Ayah:</label>
                    <input type="number" name="ayah" min="1" required>
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
function showGame(g) {
    document.querySelectorAll('.game-area').forEach(el => el.classList.add('hidden'));
    document.getElementById(g).classList.remove('hidden');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
}

function showAdmin(s) {
    document.querySelectorAll('#data, #users, #content, #stats').forEach(el => el.classList.add('hidden'));
    document.getElementById(s).classList.remove('hidden');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
}

function showReview(s) {
    document.querySelectorAll('#tafsir-review, #themes-review, #roots-review').forEach(el => el.classList.add('hidden'));
    document.getElementById(s + '-review').classList.remove('hidden');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
}

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
    }
}

function hideBookmarkForm(s, a) {
    const form = document.getElementById(`bookmark-form-${s}-${a}`);
    if (form) {
        form.remove();
    }
}


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

function filterContributions(type) {
    const items = document.querySelectorAll('.contrib-item');
    items.forEach(item => {
        if(type === 'all' || item.dataset.type === type) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

function filterByRole(role) {
    const items = document.querySelectorAll('.contrib-item');
    items.forEach(item => {
        if(role === 'all' || item.dataset.role === role) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
}

let whizScore = 0;
let jumbleScore = 0;
let memoryScore = 0;

function startWhiz() {
    whizScore = 0;
    document.getElementById('whiz-score').textContent = whizScore;
    const pairs = [
        {ar: 'الله', en: 'Allah'},
        {ar: 'محمد', en: 'Muhammad'},
        {ar: 'القرآن', en: 'Quran'},
        {ar: 'الصلاة', en: 'Prayer'},
        {ar: 'الزكاة', en: 'Charity'},
        {ar: 'الصوم', en: 'Fasting'},
        {ar: 'الحج', en: 'Pilgrimage'},
        {ar: 'الإيمان', en: 'Faith'},
        {ar: 'الجنة', en: 'Paradise'},
        {ar: 'النار', en: 'Hell'}
    ];
    
    let selected = pairs.slice(0, 5);
    let shuffled = selected.map(p => p.en).sort(() => Math.random() - 0.5);
    
    let html = '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin:2rem 0">';
    selected.forEach((pair, i) => {
        html += `<div style="text-align:center;padding:1rem;border:2px solid #ddd;border-radius:8px">
            <div style="font-size:1.8rem;margin-bottom:1rem;font-family:serif">${pair.ar}</div>
            <select onchange="checkWhizAnswer(${i}, this.value)" style="width:100%">
                <option value="">Select meaning</option>
                ${shuffled.map((m, j) => `<option value="${j}">${m}</option>`).join('')}
            </select>
        </div>`;
    });
    html += '</div><button onclick="startWhiz()" class="btn">🔄 New Round</button>';
    document.getElementById('whiz-game').innerHTML = html;
    
    window.whizAnswers = selected.map(p => p.en);
    window.whizShuffled = shuffled;
}

function checkWhizAnswer(wordIndex, selectedIndex) {
    if (selectedIndex !== '') {
        const correct = whizShuffled[selectedIndex] === whizAnswers[wordIndex];
        if (correct) {
            whizScore += 10;
            document.getElementById('whiz-score').textContent = whizScore;
            event.target.style.background = '#28a745';
            event.target.style.color = 'white';
        } else {
            event.target.style.background = '#dc3545';
            event.target.style.color = 'white';
        }
        event.target.disabled = true;
    }
}

function startJumble() {
    jumbleScore = 0;
    document.getElementById('jumble-score').textContent = jumbleScore;
    const ayahs = [
        'بسم الله الرحمن الرحيم',
        'الحمد لله رب العالمين',
        'الرحمن الرحيم',
        'مالك يوم الدين',
        'إياك نعبد وإياك نستعين'
    ];
    
    const selected = ayahs[Math.floor(Math.random() * ayahs.length)];
    const words = selected.split(' ');
    let shuffled = [...words].sort(() => Math.random() - 0.5);
    
    let html = '<div style="margin:2rem 0">';
    html += '<div style="margin-bottom:2rem;min-height:4rem;border:2px dashed #ddd;padding:1rem;border-radius:8px;background:#f8f9fa" id="answer-area">Arrange words here in correct order</div>';
    html += '<div style="text-align:center">';
    shuffled.forEach((word, i) => {
        html += `<span class="word" onclick="selectJumbleWord(${i}, '${word}')" id="word-${i}">${word}</span>`;
    });
    html += '</div>';
    html += '<div style="margin-top:2rem">';
    html += '<button onclick="checkJumble()" class="btn">✅ Check Answer</button>';
    html += '<button onclick="resetJumble()" class="btn btn-danger" style="margin-left:1rem">🔄 Reset</button>';
    html += '<button onclick="startJumble()" class="btn" style="margin-left:1rem">➡️ New Ayah</button>';
    html += '</div></div>';
    document.getElementById('jumble-game').innerHTML = html;
    
    window.jumbleSelected = [];
    window.jumbleCorrect = words;
}

function selectJumbleWord(index, word) {
    const wordEl = document.getElementById(`word-${index}`);
    if (wordEl.classList.contains('selected')) return;
    
    wordEl.classList.add('selected');
    jumbleSelected.push(word);
    
    const answerArea = document.getElementById('answer-area');
    answerArea.innerHTML = jumbleSelected.join(' ') || 'Arrange words here in correct order';
}

function checkJumble() {
    const correct = jumbleSelected.join(' ') === jumbleCorrect.join(' ');
    const answerArea = document.getElementById('answer-area');
    
    if (correct) {
        jumbleScore += 20;
        document.getElementById('jumble-score').textContent = jumbleScore;
        answerArea.style.background = '#d4edda';
        answerArea.style.borderColor = '#c3e6cb';
        answerArea.innerHTML = '✅ Correct! ' + jumbleSelected.join(' ');
    } else {
        answerArea.style.background = '#f8d7da';
        answerArea.style.borderColor = '#f5c6cb';
        answerArea.innerHTML = '❌ Incorrect. Correct order: ' + jumbleCorrect.join(' ');
    }
}

function resetJumble() {
    jumbleSelected = [];
    document.querySelectorAll('.word').forEach(w => w.classList.remove('selected'));
    document.getElementById('answer-area').innerHTML = 'Arrange words here in correct order';
    document.getElementById('answer-area').style.background = '#f8f9fa';
    document.getElementById('answer-area').style.borderColor = '#ddd';
}

function startMemory() {
    memoryScore = 0;
    document.getElementById('memory-score').textContent = memoryScore;
    
    const questions = [
        {q: 'Which Surah is known as the "Heart of the Quran"?', a: ['Ya-Sin (36)', 'Al-Fatiha (1)', 'Al-Baqarah (2)', 'Al-Ikhlas (112)'], c: 0},
        {q: 'How many Ayahs are in Surah Al-Fatiha?', a: ['5', '6', '7', '8'], c: 2},
        {q: 'Which is the longest Surah in the Quran?', a: ['Al-Baqarah', 'Aal-E-Imran', 'An-Nisa', 'Al-Maidah'], c: 0},
        {q: 'Which Surah does not begin with Bismillah?', a: ['Al-Fatiha', 'At-Tawbah', 'Al-Ikhlas', 'An-Nas'], c: 1},
        {q: 'How many times is the word "Allah" mentioned in the Quran approximately?', a: ['1000', '1500', '2000', '2700'], c: 3}
    ];
    
    const q = questions[Math.floor(Math.random() * questions.length)];
    
    let html = '<div style="margin:2rem 0;text-align:center">';
    html += `<h3 style="margin-bottom:2rem">${q.q}</h3>`;
    html += '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;max-width:600px;margin:0 auto">';
    q.a.forEach((answer, i) => {
        html += `<button class="btn" style="padding:1rem" onclick="checkMemoryAnswer(${i}, ${q.c})">${answer}</button>`;
    });
    html += '</div>';
    html += '<button onclick="startMemory()" class="btn" style="margin-top:2rem">➡️ Next Question</button>';
    html += '</div>';
    
    document.getElementById('memory-game').innerHTML = html;
}

function checkMemoryAnswer(selected, correct) {
    const buttons = document.querySelectorAll('#memory-game button');
    buttons.forEach((btn, i) => {
        if (i === correct) {
            btn.style.background = '#28a745';
            btn.classList.add('correct');
        } else if (i === selected && i !== correct) {
            btn.style.background = '#dc3545';
            btn.classList.add('incorrect');
        }
        if (i < buttons.length - 1) btn.disabled = true;
    });
    
    if (selected === correct) {
        memoryScore += 10;
        document.getElementById('memory-score').textContent = memoryScore;
    }
}
</script>
</body>
</html>