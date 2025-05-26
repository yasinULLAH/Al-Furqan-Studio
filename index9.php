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

if ($_POST['action'] == 'login') {
    $u = $_POST['username'];
    $p = $_POST['password'];
    $user = $db->querySingle("SELECT * FROM users WHERE username = '$u'", true);
    if ($user && password_verify($p, $user['password']) && $user['approved']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'register') {
    $u = $_POST['username'];
    $p = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password) VALUES ('$u', '$p')");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'logout') {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'load_data' && role('admin')) {
    $f = $_FILES['data_file']['tmp_name'];
    $lang = $_POST['language'];
    if ($f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (preg_match('/^(.*?) ترجمہ: (.*?)<br\/>س (\d{3}) آ (\d{3})$/', $line, $m)) {
                $arabic = trim($m[1]);
                $trans = trim($m[2]);
                $surah = intval($m[3]);
                $ayah = intval($m[4]);
                $db->exec("INSERT OR REPLACE INTO ayahs (surah, ayah, arabic, translation, language) VALUES ($surah, $ayah, '$arabic', '$trans', '$lang')");
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'load_words' && role('admin')) {
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
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'load_word_meta' && role('admin')) {
    $f = $_FILES['meta_file']['tmp_name'];
    if ($f) {
        $lines = file($f, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            $parts = explode(',', $line);
            if (count($parts) >= 4) {
                $wid = intval($parts[0]);
                $s = intval($parts[1]);
                $a = intval($parts[2]);
                $pos = intval($parts[3]);
                $db->exec("INSERT OR REPLACE INTO word_meta (word_id, surah, ayah, position) VALUES ($wid, $s, $a, $pos)");
            }
        }
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'add_tafsir' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $a = intval($_POST['ayah']);
    $c = $db->escapeString($_POST['content']);
    $app = role('ulama') ? 1 : 0;
    $db->exec("INSERT INTO tafsir (user_id, surah, ayah, content, approved) VALUES ($uid, $s, $a, '$c', $app)");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'add_theme' && role('user')) {
    $uid = $_SESSION['user_id'];
    $t = $db->escapeString($_POST['title']);
    $ayahs = $db->escapeString($_POST['ayahs']);
    $c = $db->escapeString($_POST['content']);
    $app = role('ulama') ? 1 : 0;
    $db->exec("INSERT INTO themes (user_id, title, ayahs, content, approved) VALUES ($uid, '$t', '$ayahs', '$c', $app)");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'log_recitation' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $a = intval($_POST['ayah']);
    $d = date('Y-m-d');
    $db->exec("INSERT INTO recitation_log (user_id, surah, ayah, date) VALUES ($uid, $s, $a, '$d')");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'update_hifz' && role('user')) {
    $uid = $_SESSION['user_id'];
    $s = intval($_POST['surah']);
    $a = intval($_POST['ayah']);
    $m = intval($_POST['memorized']);
    $db->exec("INSERT OR REPLACE INTO hifz_progress (user_id, surah, ayah, memorized) VALUES ($uid, $s, $a, $m)");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'approve_user' && role('admin')) {
    $uid = intval($_POST['user_id']);
    $db->exec("UPDATE users SET approved = 1 WHERE id = $uid");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'promote_user' && role('admin')) {
    $uid = intval($_POST['user_id']);
    $r = $_POST['new_role'];
    $db->exec("UPDATE users SET role = '$r' WHERE id = $uid");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'approve_content' && role('ulama')) {
    $cid = intval($_POST['content_id']);
    $table = $_POST['table'];
    $uid = $_SESSION['user_id'];
    $db->exec("UPDATE $table SET approved = 1 WHERE id = $cid");
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($_POST['action'] == 'search') {
    $q = $db->escapeString($_POST['query']);
    $lang = $_POST['search_lang'] ?: 'ur';
    $results = $db->query("SELECT * FROM ayahs WHERE (arabic LIKE '%$q%' OR translation LIKE '%$q%') AND language = '$lang' LIMIT 50");
}

$u = auth();
$page = $_GET['page'] ?: 'viewer';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Quran Study App</title>
<style>
body{font-family:system-ui;margin:0;background:#f5f5f5}
.nav{background:#2c5aa0;color:white;padding:1rem;display:flex;justify-content:space-between;align-items:center}
.nav a{color:white;text-decoration:none;margin:0 1rem}
.nav a:hover{text-decoration:underline}
.container{max-width:1200px;margin:0 auto;padding:2rem}
.card{background:white;border-radius:8px;padding:1.5rem;margin:1rem 0;box-shadow:0 2px 4px rgba(0,0,0,0.1)}
.btn{background:#2c5aa0;color:white;border:none;padding:0.5rem 1rem;border-radius:4px;cursor:pointer}
.btn:hover{background:#1e3d6f}
.form-group{margin:1rem 0}
label{display:block;margin-bottom:0.5rem;font-weight:bold}
input,select,textarea{width:100%;padding:0.5rem;border:1px solid #ddd;border-radius:4px;box-sizing:border-box}
textarea{height:100px}
.ayah{border-left:4px solid #2c5aa0;padding:1rem;margin:1rem 0;background:white;border-radius:4px}
.arabic{font-size:1.5rem;direction:rtl;text-align:right;margin-bottom:1rem;line-height:2}
.translation{font-size:1.1rem;color:#333}
.meta{font-size:0.9rem;color:#666;margin-top:0.5rem}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem}
.user-panel{background:#e8f4f8;border-radius:4px;padding:1rem;margin-bottom:1rem}
.tabs{display:flex;border-bottom:2px solid #ddd;margin-bottom:2rem}
.tab{padding:1rem 2rem;cursor:pointer;border-bottom:2px solid transparent}
.tab.active{border-bottom-color:#2c5aa0;background:#f0f8ff}
.hidden{display:none}
.game-area{text-align:center;padding:2rem}
.word{display:inline-block;margin:0.2rem;padding:0.5rem;background:#e8f4f8;border-radius:4px;cursor:pointer}
.word.selected{background:#2c5aa0;color:white}
.score{font-size:1.2rem;font-weight:bold;margin:1rem 0}
</style>
</head>
<body>

<div class="nav">
    <div>
        <a href="?page=viewer">Quran Viewer</a>
        <?php if($u): ?>
        <a href="?page=tafsir">Tafsir</a>
        <a href="?page=themes">Themes</a>
        <a href="?page=recitation">Recitation</a>
        <a href="?page=hifz">Hifz Hub</a>
        <?php endif; ?>
        <a href="?page=search">Search</a>
        <a href="?page=games">Games</a>
        <?php if(role('admin')): ?>
        <a href="?page=admin">Admin</a>
        <?php endif; ?>
    </div>
    <div>
        <?php if($u): ?>
            Welcome, <?= htmlspecialchars($u['username']) ?> (<?= $u['role'] ?>)
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="btn">Logout</button>
            </form>
        <?php else: ?>
            <a href="?page=login">Login</a>
        <?php endif; ?>
    </div>
</div>

<div class="container">
    <?php if(!$u && $page != 'login'): ?>
    <div class="card">
        <h3>Welcome to Quran Study App</h3>
        <p>Access Quran text, translations, and scholarly content. <a href="?page=login">Login</a> for personal features.</p>
    </div>
    <?php endif; ?>

    <?php if($page == 'login'): ?>
    <div class="card">
        <h2>Login</h2>
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
        <h2>Register</h2>
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
        <p><small>Registration requires admin approval.</small></p>
    </div>
    <?php endif; ?>

    <?php if($page == 'viewer'): ?>
    <div class="card">
        <h2>Quran Viewer</h2>
        <form method="get">
            <input type="hidden" name="page" value="viewer">
            <div class="grid">
                <div class="form-group">
                    <label>Surah:</label>
                    <select name="surah">
                        <?php for($i=1;$i<=114;$i++): ?>
                        <option value="<?=$i?>" <?= $_GET['surah']==$i?'selected':'' ?>><?=$i?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Language:</label>
                    <select name="lang">
                        <option value="ur" <?= $_GET['lang']=='ur'?'selected':'' ?>>Urdu</option>
                        <option value="en" <?= $_GET['lang']=='en'?'selected':'' ?>>English</option>
                        <option value="bn" <?= $_GET['lang']=='bn'?'selected':'' ?>>Bengali</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">Load Surah</button>
        </form>
    </div>

    <?php if($_GET['surah']): ?>
    <?php 
    $s = intval($_GET['surah']);
    $lang = $_GET['lang'] ?: 'ur';
    $ayahs = $db->query("SELECT * FROM ayahs WHERE surah = $s AND language = '$lang' ORDER BY ayah");
    ?>
    <div class="card">
        <h3>Surah <?= $s ?></h3>
        <?php while($ayah = $ayahs->fetchArray(SQLITE3_ASSOC)): ?>
        <div class="ayah">
            <div class="arabic"><?= htmlspecialchars($ayah['arabic']) ?></div>
            <div class="translation"><?= htmlspecialchars($ayah['translation']) ?></div>
            <div class="meta">Surah <?= $ayah['surah'] ?>, Ayah <?= $ayah['ayah'] ?></div>
            
            <?php if($u): ?>
            <div style="margin-top:1rem">
                <form style="display:inline" method="post">
                    <input type="hidden" name="action" value="log_recitation">
                    <input type="hidden" name="surah" value="<?= $ayah['surah'] ?>">
                    <input type="hidden" name="ayah" value="<?= $ayah['ayah'] ?>">
                    <button type="submit" class="btn">Log Recitation</button>
                </form>
            </div>
            <?php endif; ?>
            
            <?php 
            $tafsirs = $db->query("SELECT t.*, u.username FROM tafsir t JOIN users u ON t.user_id = u.id WHERE t.surah = {$ayah['surah']} AND t.ayah = {$ayah['ayah']} AND t.approved = 1");
            while($t = $tafsirs->fetchArray(SQLITE3_ASSOC)):
            ?>
            <div style="background:#f9f9f9;padding:1rem;margin:0.5rem 0;border-radius:4px">
                <strong>Tafsir by <?= htmlspecialchars($t['username']) ?>:</strong><br>
                <?= nl2br(htmlspecialchars($t['content'])) ?>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if($page == 'tafsir' && $u): ?>
    <div class="card">
        <h2>Personal Tafsir</h2>
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
                <textarea name="content" required></textarea>
            </div>
            <button type="submit" class="btn">Add Tafsir</button>
        </form>
    </div>

    <div class="card">
        <h3>My Tafsir</h3>
        <?php 
        $tafsirs = $db->query("SELECT * FROM tafsir WHERE user_id = {$u['id']} ORDER BY created_at DESC");
        while($t = $tafsirs->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="border:1px solid #ddd;padding:1rem;margin:0.5rem 0;border-radius:4px">
            <strong>Surah <?= $t['surah'] ?>, Ayah <?= $t['ayah'] ?></strong>
            <?php if(!$t['approved']): ?><span style="color:orange">(Pending Approval)</span><?php endif; ?>
            <p><?= nl2br(htmlspecialchars($t['content'])) ?></p>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'themes' && $u): ?>
    <div class="card">
        <h2>Thematic Linker</h2>
        <form method="post">
            <input type="hidden" name="action" value="add_theme">
            <div class="form-group">
                <label>Theme Title:</label>
                <input type="text" name="title" required>
            </div>
            <div class="form-group">
                <label>Related Ayahs (format: 2:255,3:1-5):</label>
                <input type="text" name="ayahs" required>
            </div>
            <div class="form-group">
                <label>Theme Content:</label>
                <textarea name="content" required></textarea>
            </div>
            <button type="submit" class="btn">Add Theme</button>
        </form>
    </div>

    <div class="card">
        <h3>Public Themes</h3>
        <?php 
        $themes = $db->query("SELECT t.*, u.username FROM themes t JOIN users u ON t.user_id = u.id WHERE t.approved = 1 ORDER BY t.created_at DESC");
        while($th = $themes->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="border:1px solid #ddd;padding:1rem;margin:0.5rem 0;border-radius:4px">
            <h4><?= htmlspecialchars($th['title']) ?></h4>
            <p><strong>By:</strong> <?= htmlspecialchars($th['username']) ?></p>
            <p><strong>Ayahs:</strong> <?= htmlspecialchars($th['ayahs']) ?></p>
            <p><?= nl2br(htmlspecialchars($th['content'])) ?></p>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>

    <?php if($page == 'hifz' && $u): ?>
    <div class="card">
        <h2>Hifz Hub</h2>
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
                    <label>Status:</label>
                    <select name="memorized">
                        <option value="0">Not Memorized</option>
                        <option value="1">Memorized</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">Update Progress</button>
        </form>
    </div>

    <div class="card">
        <h3>Memorization Progress</h3>
        <?php 
        $progress = $db->query("SELECT * FROM hifz_progress WHERE user_id = {$u['id']} AND memorized = 1 ORDER BY surah, ayah");
        $total = 0;
        while($p = $progress->fetchArray(SQLITE3_ASSOC)):
            $total++;
        ?>
        <div style="display:inline-block;margin:0.2rem;padding:0.5rem;background:#e8f4f8;border-radius:4px">
            <?= $p['surah'] ?>:<?= $p['ayah'] ?>
        </div>
        <?php endwhile; ?>
        <p><strong>Total Memorized: <?= $total ?> ayahs</strong></p>
    </div>
    <?php endif; ?>

    <?php if($page == 'search'): ?>
    <div class="card">
        <h2>Advanced Search</h2>
        <form method="post">
            <input type="hidden" name="action" value="search">
            <div class="grid">
                <div class="form-group">
                    <label>Search Query:</label>
                    <input type="text" name="query" value="<?= htmlspecialchars($_POST['query']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Language:</label>
                    <select name="search_lang">
                        <option value="ur">Urdu</option>
                        <option value="en">English</option>
                        <option value="bn">Bengali</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn">Search</button>
        </form>
    </div>

    <?php if(isset($results)): ?>
    <div class="card">
        <h3>Search Results</h3>
        <?php while($r = $results->fetchArray(SQLITE3_ASSOC)): ?>
        <div class="ayah">
            <div class="arabic"><?= htmlspecialchars($r['arabic']) ?></div>
            <div class="translation"><?= htmlspecialchars($r['translation']) ?></div>
            <div class="meta">Surah <?= $r['surah'] ?>, Ayah <?= $r['ayah'] ?></div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if($page == 'games'): ?>
    <div class="tabs">
        <div class="tab active" onclick="showGame('whiz')">Word Whiz</div>
        <div class="tab" onclick="showGame('jumble')">Ayah Jumble</div>
    </div>

    <div id="whiz" class="game-area">
        <h2>Word Whiz</h2>
        <p>Match Arabic words with their meanings!</p>
        <div id="whiz-game">
            <button onclick="startWhiz()" class="btn">Start Game</button>
        </div>
        <div class="score">Score: <span id="whiz-score">0</span></div>
    </div>

    <div id="jumble" class="game-area hidden">
        <h2>Ayah Jumble</h2>
        <p>Arrange the words in correct order!</p>
        <div id="jumble-game">
            <button onclick="startJumble()" class="btn">Start Game</button>
        </div>
        <div class="score">Score: <span id="jumble-score">0</span></div>
    </div>
    <?php endif; ?>

    <?php if($page == 'admin' && role('admin')): ?>
    <div class="tabs">
        <div class="tab active" onclick="showAdmin('data')">Load Data</div>
        <div class="tab" onclick="showAdmin('users')">Manage Users</div>
        <div class="tab" onclick="showAdmin('content')">Manage Content</div>
    </div>

    <div id="data" class="card">
        <h2>Load Quran Data</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="load_data">
            <div class="form-group">
                <label>Ayah Data File (.AM):</label>
                <input type="file" name="data_file" accept=".AM" required>
            </div>
            <div class="form-group">
                <label>Language:</label>
                <select name="language" required>
                    <option value="ur">Urdu</option>
                    <option value="en">English</option>
                    <option value="bn">Bengali</option>
                </select>
            </div>
            <button type="submit" class="btn">Load Ayah Data</button>
        </form>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="load_words">
            <div class="form-group">
                <label>Word Meanings File (CSV):</label>
                <input type="file" name="word_file" accept=".AM,.csv" required>
            </div>
            <button type="submit" class="btn">Load Word Data</button>
        </form>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="load_word_meta">
            <div class="form-group">
                <label>Word Metadata File (CSV):</label>
                <input type="file" name="meta_file" accept=".AM,.csv" required>
            </div>
            <button type="submit" class="btn">Load Word Meta</button>
        </form>
    </div>

    <div id="users" class="card hidden">
        <h2>User Management</h2>
        <h3>Pending Users</h3>
        <?php 
        $pending = $db->query("SELECT * FROM users WHERE approved = 0");
        while($pu = $pending->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="border:1px solid #ddd;padding:1rem;margin:0.5rem 0;border-radius:4px">
            <strong><?= htmlspecialchars($pu['username']) ?></strong>
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="approve_user">
                <input type="hidden" name="user_id" value="<?= $pu['id'] ?>">
                <button type="submit" class="btn">Approve</button>
            </form>
        </div>
        <?php endwhile; ?>

        <h3>All Users</h3>
        <?php 
        $users = $db->query("SELECT * FROM users WHERE approved = 1");
        while($user = $users->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="border:1px solid #ddd;padding:1rem;margin:0.5rem 0;border-radius:4px">
            <strong><?= htmlspecialchars($user['username']) ?></strong> - <?= $user['role'] ?>
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="promote_user">
                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                <select name="new_role">
                    <option value="user" <?= $user['role']=='user'?'selected':'' ?>>User</option>
                    <option value="ulama" <?= $user['role']=='ulama'?'selected':'' ?>>Ulama</option>
                    <option value="admin" <?= $user['role']=='admin'?'selected':'' ?>>Admin</option>
                </select>
                <button type="submit" class="btn">Update Role</button>
            </form>
        </div>
        <?php endwhile; ?>
    </div>

    <div id="content" class="card hidden">
        <h2>Content Management</h2>
        <h3>Pending Tafsir</h3>
        <?php 
        $pending_t = $db->query("SELECT t.*, u.username FROM tafsir t JOIN users u ON t.user_id = u.id WHERE t.approved = 0");
        while($pt = $pending_t->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="border:1px solid #ddd;padding:1rem;margin:0.5rem 0;border-radius:4px">
            <strong>Surah <?= $pt['surah'] ?>, Ayah <?= $pt['ayah'] ?></strong> by <?= htmlspecialchars($pt['username']) ?>
            <p><?= nl2br(htmlspecialchars($pt['content'])) ?></p>
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="approve_content">
                <input type="hidden" name="content_id" value="<?= $pt['id'] ?>">
                <input type="hidden" name="table" value="tafsir">
                <button type="submit" class="btn">Approve</button>
            </form>
        </div>
        <?php endwhile; ?>

        <h3>Pending Themes</h3>
        <?php 
        $pending_th = $db->query("SELECT t.*, u.username FROM themes t JOIN users u ON t.user_id = u.id WHERE t.approved = 0");
        while($pth = $pending_th->fetchArray(SQLITE3_ASSOC)):
        ?>
        <div style="border:1px solid #ddd;padding:1rem;margin:0.5rem 0;border-radius:4px">
            <strong><?= htmlspecialchars($pth['title']) ?></strong> by <?= htmlspecialchars($pth['username']) ?>
            <p><strong>Ayahs:</strong> <?= htmlspecialchars($pth['ayahs']) ?></p>
            <p><?= nl2br(htmlspecialchars($pth['content'])) ?></p>
            <form style="display:inline" method="post">
                <input type="hidden" name="action" value="approve_content">
                <input type="hidden" name="content_id" value="<?= $pth['id'] ?>">
                <input type="hidden" name="table" value="themes">
                <button type="submit" class="btn">Approve</button>
            </form>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function showGame(game) {
    document.querySelectorAll('.game-area').forEach(el => el.classList.add('hidden'));
    document.getElementById(game).classList.remove('hidden');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
}

function showAdmin(section) {
    document.querySelectorAll('#data, #users, #content').forEach(el => el.classList.add('hidden'));
    document.getElementById(section).classList.remove('hidden');
    document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');
}

let whizScore = 0;
let jumbleScore = 0;

function startWhiz() {
    whizScore = 0;
    document.getElementById('whiz-score').textContent = whizScore;
    const words = ['الله', 'محمد', 'القرآن', 'الصلاة', 'الزكاة'];
    const meanings = ['Allah', 'Muhammad', 'Quran', 'Prayer', 'Charity'];
    let shuffled = meanings.sort(() => Math.random() - 0.5);
    
    let html = '<div style="margin:2rem 0">';
    words.forEach((word, i) => {
        html += `<div style="display:inline-block;margin:1rem;padding:1rem;border:2px solid #ddd;border-radius:8px;text-align:center;vertical-align:top">
            <div style="font-size:1.5rem;margin-bottom:1rem">${word}</div>
            <select onchange="checkAnswer(${i}, this.value)">
                <option value="">Select meaning</option>
                ${shuffled.map((m, j) => `<option value="${j}">${m}</option>`).join('')}
            </select>
        </div>`;
    });
    html += '</div>';
    document.getElementById('whiz-game').innerHTML = html;
}

function checkAnswer(wordIndex, selectedIndex) {
    if (selectedIndex == wordIndex) {
        whizScore += 10;
        document.getElementById('whiz-score').textContent = whizScore;
        event.target.style.background = '#4caf50';
        event.target.style.color = 'white';
    } else if (selectedIndex !== '') {
        event.target.style.background = '#f44336';
        event.target.style.color = 'white';
    }
}

function startJumble() {
    jumbleScore = 0;
    document.getElementById('jumble-score').textContent = jumbleScore;
    const ayah = 'بسم الله الرحمن الرحيم';
    const words = ayah.split(' ');
    let shuffled = [...words].sort(() => Math.random() - 0.5);
    let selected = [];
    
    let html = '<div style="margin:2rem 0">';
    html += '<div style="margin-bottom:2rem;min-height:3rem;border:2px dashed #ddd;padding:1rem" id="answer-area">Drop words here in order</div>';
    html += '<div>';
    shuffled.forEach((word, i) => {
        html += `<span class="word" onclick="selectWord(${i}, '${word}')">${word}</span>`;
    });
    html += '</div>';
    html += '<button onclick="checkJumble()" class="btn" style="margin-top:1rem">Check Answer</button>';
    html += '</div>';
    document.getElementById('jumble-game').innerHTML = html;
    
    window.selectedWords = [];
    window.correctOrder = words;
}

function selectWord(index, word) {
    const wordEl = event.target;
    if (wordEl.classList.contains('selected')) return;
    
    wordEl.classList.add('selected');
    selectedWords.push(word);
    
    const answerArea = document.getElementById('answer-area');
    answerArea.innerHTML = selectedWords.join(' ') || 'Drop words here in order';
}

function checkJumble() {
    if (selectedWords.join(' ') === correctOrder.join(' ')) {
        jumbleScore += 20;
        document.getElementById('jumble-score').textContent = jumbleScore;
        document.getElementById('answer-area').style.background = '#4caf50';
        document.getElementById('answer-area').style.color = 'white';
        document.getElementById('answer-area').innerHTML = 'Correct! ' + selectedWords.join(' ');
    } else {
        document.getElementById('answer-area').style.background = '#f44336';
        document.getElementById('answer-area').style.color = 'white';
        document.getElementById('answer-area').innerHTML = 'Incorrect. Try again!';
    }
}
</script>

</body>
</html>
