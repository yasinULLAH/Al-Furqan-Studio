<?php
// Quran Study Application - Single PHP File Solution
// Nur Al-Quran Studio Offline Clone with SQLite backend

// Database configuration
define('DB_NAME', 'quran_studio.db');
session_start();

// Initialize database if not exists
function initDB() {
    if (!file_exists(DB_NAME)) {
        $db = new SQLite3(DB_NAME);
        
        // Create tables for Quran data
        $db->exec("CREATE TABLE IF NOT EXISTS surahs (
            id INTEGER PRIMARY KEY,
            name_ar TEXT, name_en TEXT, name_ur TEXT, name_bn TEXT,
            revelation_place TEXT, ayah_count INTEGER
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS ayahs (
            id INTEGER PRIMARY KEY,
            surah_id INTEGER, ayah_num INTEGER,
            text_ar TEXT, text_ur TEXT, text_en TEXT, text_bn TEXT,
            FOREIGN KEY(surah_id) REFERENCES surahs(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS word_meanings (
            id INTEGER PRIMARY KEY,
            word_id TEXT, ur_meaning TEXT, en_meaning TEXT
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS word_metadata (
            id INTEGER PRIMARY KEY,
            word_id TEXT, surah INTEGER, ayah INTEGER, word_position INTEGER
        )");
        
        // Create tables for user content
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY,
            username TEXT UNIQUE, password TEXT, email TEXT,
            role TEXT DEFAULT 'public', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS tafsirs (
            id INTEGER PRIMARY KEY,
            ayah_id INTEGER, user_id INTEGER, content TEXT,
            status TEXT DEFAULT 'pending', -- pending/approved/rejected
            is_ulama BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(ayah_id) REFERENCES ayahs(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS themes (
            id INTEGER PRIMARY KEY,
            title TEXT, description TEXT, user_id INTEGER,
            status TEXT DEFAULT 'pending', is_ulama BOOLEAN DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS theme_ayahs (
            id INTEGER PRIMARY KEY,
            theme_id INTEGER, ayah_id INTEGER,
            FOREIGN KEY(theme_id) REFERENCES themes(id),
            FOREIGN KEY(ayah_id) REFERENCES ayahs(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS hifz (
            id INTEGER PRIMARY KEY,
            user_id INTEGER, surah_id INTEGER, ayah_from INTEGER, ayah_to INTEGER,
            progress INTEGER, last_reviewed DATETIME,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(surah_id) REFERENCES surahs(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS recitation_logs (
            id INTEGER PRIMARY KEY,
            user_id INTEGER, surah_id INTEGER, ayah_from INTEGER, ayah_to INTEGER,
            duration INTEGER, date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id),
            FOREIGN KEY(surah_id) REFERENCES surahs(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS word_contributions (
            id INTEGER PRIMARY KEY,
            word_id TEXT, ur_meaning TEXT, en_meaning TEXT, user_id INTEGER,
            status TEXT DEFAULT 'pending', created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS settings (
            name TEXT PRIMARY KEY, value TEXT
        )");
        
        // Create admin user if not exists
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT OR IGNORE INTO users (username, password, email, role) 
                  VALUES ('admin', '$admin_pass', 'admin@quranstudio.com', 'admin')");
        
        $db->close();
        return true;
    }
    return false;
}

// Database connection
function getDB() {
    return new SQLite3(DB_NAME);
}

// Authentication functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function currentUser() {
    if (isLoggedIn()) {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindValue(':id', $_SESSION['user_id'], SQLITE3_INTEGER);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    return null;
}

function hasRole($role) {
    $user = currentUser();
    return $user && ($user['role'] === $role || $user['role'] === 'admin');
}

// Data loading functions
function loadTranslationFile($filePath, $language) {
    $db = getDB();
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Parse the line format: [Arabic Ayah] ترجمہ: [Translation]<br/>س [Surah] آ [Ayah]
        $parts = explode('<br/>', $line);
        if (count($parts) < 2) continue;
        
        $ayahParts = explode(' ترجمہ: ', $parts[0]);
        $arabic = $ayahParts[0];
        $translation = isset($ayahParts[1]) ? $ayahParts[1] : '';
        
        $metaParts = explode(' ', $parts[1]);
        $surah = trim($metaParts[1]);
        $ayah = trim($metaParts[3]);
        
        // Check if ayah exists
        $stmt = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah AND ayah_num = :ayah");
        $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
        $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $ayahData = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($ayahData) {
            // Update existing ayah
            $column = "text_" . strtolower($language);
            $stmt = $db->prepare("UPDATE ayahs SET $column = :translation WHERE id = :id");
            $stmt->bindValue(':translation', $translation, SQLITE3_TEXT);
            $stmt->bindValue(':id', $ayahData['id'], SQLITE3_INTEGER);
            $stmt->execute();
        } else {
            // Insert new ayah (Arabic only, other languages will be added when their files are loaded)
            $stmt = $db->prepare("INSERT INTO ayahs (surah_id, ayah_num, text_ar) VALUES (:surah, :ayah, :arabic)");
            $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
            $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
            $stmt->bindValue(':arabic', $arabic, SQLITE3_TEXT);
            $stmt->execute();
        }
    }
}

function loadWordMeanings($filePath) {
    $db = getDB();
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if (count($parts) < 3) continue;
        
        $word_id = trim($parts[0]);
        $ur_meaning = trim($parts[1]);
        $en_meaning = trim($parts[2]);
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO word_meanings (word_id, ur_meaning, en_meaning) 
                             VALUES (:word_id, :ur_meaning, :en_meaning)");
        $stmt->bindValue(':word_id', $word_id, SQLITE3_TEXT);
        $stmt->bindValue(':ur_meaning', $ur_meaning, SQLITE3_TEXT);
        $stmt->bindValue(':en_meaning', $en_meaning, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function loadWordMetadata($filePath) {
    $db = getDB();
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if (count($parts) < 4) continue;
        
        $word_id = trim($parts[0]);
        $surah = trim($parts[1]);
        $ayah = trim($parts[2]);
        $word_position = trim($parts[3]);
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO word_metadata (word_id, surah, ayah, word_position) 
                             VALUES (:word_id, :surah, :ayah, :word_position)");
        $stmt->bindValue(':word_id', $word_id, SQLITE3_TEXT);
        $stmt->bindValue(':surah', $surah, SQLITE3_INTEGER);
        $stmt->bindValue(':ayah', $ayah, SQLITE3_INTEGER);
        $stmt->bindValue(':word_position', $word_position, SQLITE3_INTEGER);
        $stmt->execute();
    }
}

// Helper functions
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function renderHeader() {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Nur Al-Quran Studio</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
            header { background-color: #2c3e50; color: white; padding: 15px 0; margin-bottom: 20px; min-height: 70px;}
            nav { display: flex; justify-content: space-between; align-items: center; }
            .nav-links a { color: white; text-decoration: none; margin: 0 10px; }
            .auth-links a { color: white; text-decoration: none; margin: 0 10px; }
            .card { background: white; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); padding: 20px; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; }
            th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f2f2f2; }
            .btn { display: inline-block; padding: 8px 15px; background-color: #3498db; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
            .btn-danger { background-color: #e74c3c; }
            .btn-success { background-color: #2ecc71; }
            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; }
            .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
            .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
            .alert-success { background-color: #d4edda; color: #155724; }
            .alert-danger { background-color: #f8d7da; color: #721c24; }
            .flex { display: flex; }
            .flex-col { flex-direction: column; }
            .gap-4 { gap: 1rem; }
            .quran-text { font-size: 24px; text-align: right; direction: rtl; }
            .translation { font-size: 16px; margin-top: 10px; }
            .word-meaning {
                    cursor: pointer;
                    position: relative;
                    background: #a9ef97;
                    border-radius: 19px;
                    padding: 12px;
                }
            .word-meaning:hover { background-color: #f0f0f0; }
            .word-tooltip { display: none; position: absolute; background: white; border: 1px solid #ddd; padding: 5px; z-index: 100;width: max-content; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            nav > h1 {
    position: absolute;
    top: 69px;
    left: 6px;
    color: chocolate;
    background: #2c3e50;
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 13px;
    padding: 10px;
}
    *, select, input{
    font-family: calibri !important;
    }
        </style>
    </head>
    <body>
    <header>
        <div class="container">
            <nav>
                <h1>Nur Al-Quran Studio</h1>
                <div class="nav-links">';
    
    if (isLoggedIn()) {
        $user = currentUser();
        echo '<a href="?page=home">Home</a>';
        echo '<a href="?page=quran">Quran Viewer</a>';
        echo '<a href="?page=tafsir">Tafsir</a>';
        echo '<a href="?page=themes">Thematic Study</a>';
        echo '<a href="?page=hifz">Hifz Hub</a>';
        echo '<a href="?page=recitation">Recitation Log</a>';
        if (hasRole('user') || hasRole('ulama') || hasRole('admin')) {
            echo '<a href="?page=contributions">My Contributions</a>';
        }
        if (hasRole('ulama')) {
            echo '<a href="?page=ulama">Ulama Dashboard</a>';
        }
        if (hasRole('admin')) {
            echo '<a href="?page=admin">Admin Panel</a>';
        }
    } else {
        echo '<a href="?page=home">Home</a>';
        echo '<a href="?page=quran">Quran Viewer</a>';
        echo '<a href="?page=tafsir">Tafsir</a>';
        echo '<a href="?page=themes">Thematic Study</a>';
    }
    
    echo '</div>
                <div class="auth-links">';
    
    if (isLoggedIn()) {
        echo '<span>Welcome, ' . htmlspecialchars($user['username']) . '</span>';
        echo '<a href="?page=profile">Profile</a>';
        echo '<a href="?action=logout">Logout</a>';
    } else {
        echo '<a href="?page=login">Login</a>';
        echo '<a href="?page=register">Register</a>';
    }
    
    echo '</div>
            </nav>
        </div>
    </header>
    <div class="container">';
}

function renderFooter() {
    echo '</div>
    <footer style="background-color: #2c3e50; color: white; padding: 20px 0; margin-top: 20px;">
        <div class="container" style="text-align: center;">
            <p>Nur Al-Quran Studio Offline - &copy; ' . date('Y') . '</p>
        </div>
    </footer>
    <script>
        // Word meaning tooltip functionality
        document.addEventListener("DOMContentLoaded", function() {
            const wordElements = document.querySelectorAll(".word-meaning");
            wordElements.forEach(word => {
                word.addEventListener("mouseenter", function(e) {
                    const tooltip = this.querySelector(".word-tooltip");
                    if (tooltip) {
                        tooltip.style.display = "block";
                        // Position the tooltip
                        tooltip.style.left = (e.clientX - this.getBoundingClientRect().left) + "px";
                        tooltip.style.top = (this.getBoundingClientRect().bottom - this.getBoundingClientRect().top + 5) + "px";
                    }
                });
                
                word.addEventListener("mouseleave", function() {
                    const tooltip = this.querySelector(".word-tooltip");
                    if (tooltip) tooltip.style.display = "none";
                });
            });
        });
    </script>
    </body>
    </html>';
}

function displayMessage($type, $message) {
    echo '<div class="alert alert-' . $type . '">' . $message . '</div>';
}

// Routing and page rendering
function handleRequest() {
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    $action = isset($_GET['action']) ? $_GET['action'] : null;
    
    // Handle actions first
    if ($action === 'logout') {
        session_destroy();
        header("Location: ?page=login");
        exit();
    }
    
    // Initialize database if not exists
    if (!file_exists(DB_NAME) && $page !== 'setup') {
        header("Location: ?page=setup");
        exit();
    }
    
    // Render header
    renderHeader();
    
    // Handle page content
    switch ($page) {
        case 'setup':
            handleSetup();
            break;
        case 'home':
            handleHome();
            break;
        case 'login':
            handleLogin();
            break;
        case 'register':
            handleRegister();
            break;
        case 'quran':
            handleQuranViewer();
            break;
        case 'tafsir':
            handleTafsir();
            break;
        case 'themes':
            handleThemes();
            break;
        case 'hifz':
            handleHifz();
            break;
        case 'recitation':
            handleRecitation();
            break;
        case 'contributions':
            handleContributions();
            break;
        case 'ulama':
            handleUlamaDashboard();
            break;
        case 'admin':
            handleAdminPanel();
            break;
        case 'profile':
            handleProfile();
            break;
        default:
            handleHome();
    }
    
    // Render footer
    renderFooter();
}

// Page handlers
function handleSetup() {
    if (file_exists(DB_NAME)) {
        displayMessage('danger', 'Database already exists. Setup is not required.');
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $init = initDB();
        if ($init) {
            displayMessage('success', 'Database initialized successfully. You can now login as admin with username "admin" and password "admin123".');
            echo '<p><a href="?page=login" class="btn">Proceed to Login</a></p>';
            return;
        } else {
            displayMessage('danger', 'Failed to initialize database.');
        }
    }
    
    echo '<div class="card">
        <h2>Initial Setup</h2>
        <p>Welcome to Nur Al-Quran Studio. Click the button below to initialize the database.</p>
        <form method="post">
            <button type="submit" class="btn">Initialize Database</button>
        </form>
    </div>';
}

function handleHome() {
    echo '<div class="card">
        <h2>Welcome to Nur Al-Quran Studio</h2>
        <p>This application provides comprehensive tools for Quran study including:</p>
        <ul>
            <li>Quran Viewer with multiple translations</li>
            <li>Personal and community Tafsir</li>
            <li>Thematic study tools</li>
            <li>Root word analysis</li>
            <li>Hifz tracking</li>
            <li>Recitation logs</li>
            <li>Advanced search</li>
        </ul>';
    
    if (!isLoggedIn()) {
        echo '<p>Please <a href="?page=login">login</a> or <a href="?page=register">register</a> to access all features.</p>';
    }
    
    echo '</div>';
}

function handleLogin() {
    if (isLoggedIn()) {
        header("Location: ?page=home");
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username']);
        $password = sanitizeInput($_POST['password']);
        
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            
            // Update last login
            $stmt = $db->prepare("UPDATE users SET last_login = datetime('now') WHERE id = :id");
            $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
            $stmt->execute();
            
            header("Location: ?page=home");
            exit();
        } else {
            displayMessage('danger', 'Invalid username or password.');
        }
    }
    
    echo '<div class="card">
        <h2>Login</h2>
        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        <p>Don\'t have an account? <a href="?page=register">Register here</a></p>
    </div>';
}

function handleRegister() {
    if (isLoggedIn()) {
        header("Location: ?page=home");
        exit();
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = sanitizeInput($_POST['username']);
        $password = sanitizeInput($_POST['password']);
        $email = sanitizeInput($_POST['email']);
        
        $db = getDB();
        
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        if ($result->fetchArray()) {
            displayMessage('danger', 'Username already exists.');
            return;
        }
        
        // Insert new user
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, password, email, role) 
                             VALUES (:username, :password, :email, 'public')");
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        
        if ($stmt->execute()) {
            displayMessage('success', 'Registration successful. Please login.');
            header("Location: ?page=login");
            exit();
        } else {
            displayMessage('danger', 'Registration failed. Please try again.');
        }
    }
    
    echo '<div class="card">
        <h2>Register</h2>
        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        <p>Already have an account? <a href="?page=login">Login here</a></p>
    </div>';
}

function handleQuranViewer() {
    $db = getDB();
    
    // Get surah list
    $surahs = [];
    $result = $db->query("SELECT id FROM surahs ORDER BY id");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $surahs[] = $row['id'];
    }
    
    // Get current surah (default to 1)
    $currentSurah = isset($_GET['surah']) ? intval($_GET['surah']) : 1;
    if (!in_array($currentSurah, $surahs)) $currentSurah = 1;
    
    // Get ayahs for current surah
    $ayahs = [];
    $stmt = $db->prepare("SELECT * FROM ayahs WHERE surah_id = :surah ORDER BY ayah_num");
    $stmt->bindValue(':surah', $currentSurah, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row;
    }
    
    // Get word-by-word data for ayahs
    $wordData = [];
    if (!empty($ayahs)) {
        $firstAyah = $ayahs[0]['ayah_num'];
        $lastAyah = end($ayahs)['ayah_num'];
        
        $stmt = $db->prepare("SELECT wm.word_id, wm.ur_meaning, wm.en_meaning, wd.surah, wd.ayah, wd.word_position 
                             FROM word_metadata wd
                             JOIN word_meanings wm ON wd.word_id = wm.word_id
                             WHERE wd.surah = :surah AND wd.ayah BETWEEN :start AND :end
                             ORDER BY wd.ayah, wd.word_position");
        $stmt->bindValue(':surah', $currentSurah, SQLITE3_INTEGER);
        $stmt->bindValue(':start', $firstAyah, SQLITE3_INTEGER);
        $stmt->bindValue(':end', $lastAyah, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            if (!isset($wordData[$row['ayah']])) {
                $wordData[$row['ayah']] = [];
            }
            $wordData[$row['ayah']][] = $row;
        }
    }
    
    echo '<div class="card">
        <h2>Quran Viewer</h2>
        <div class="flex gap-4">
            <div style="flex: 1;">
                <h3>Surahs</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 5px;">';
    
    foreach ($surahs as $surah) {
        $active = $surah == $currentSurah ? 'style="background-color: #3498db; color: white;"' : '';
        echo '<a href="?page=quran&surah=' . $surah . '" class="btn" ' . $active . '>' . $surah . '</a>';
    }
    
    echo '</div>
            </div>
            <div style="flex: 3;">
                <h3>Surah ' . $currentSurah . '</h3>';
    
    foreach ($ayahs as $ayah) {
        echo '<div class="quran-text">' . $ayah['text_ar'] . '</div>';
        echo '<div class="translation">' . $ayah['text_en'] . '</div>';
        
        // Display word-by-word if available
        if (isset($wordData[$ayah['ayah_num']])) {
            echo '<div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 5px;">';
            foreach ($wordData[$ayah['ayah_num']] as $word) {
                echo '<span class="word-meaning">
                    ' . $word['word_id'] . '
                    <div class="word-tooltip">
                        <div><strong>Urdu:</strong> ' . $word['ur_meaning'] . '</div>
                        <div><strong>English:</strong> ' . $word['en_meaning'] . '</div>
                    </div>
                </span>';
            }
            echo '</div>';
        }
        
        // Display tafsir if available
        $stmt = $db->prepare("SELECT t.*, u.username FROM tafsirs t 
                             JOIN users u ON t.user_id = u.id
                             WHERE t.ayah_id = :ayah_id AND (t.status = 'approved' OR (t.user_id = :user_id AND t.status != 'rejected'))
                             ORDER BY t.is_ulama DESC, t.created_at DESC");
        $stmt->bindValue(':ayah_id', $ayah['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':user_id', isLoggedIn() ? $_SESSION['user_id'] : 0, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        $tafsirs = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $tafsirs[] = $row;
        }
        
        if (!empty($tafsirs)) {
            echo '<div style="margin-top: 15px; background: #f9f9f9; padding: 10px; border-radius: 5px;">
                <h4>Tafsir</h4>';
            
            foreach ($tafsirs as $tafsir) {
                echo '<div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #eee;">
                    <p>' . $tafsir['content'] . '</p>
                    <small>By ' . $tafsir['username'] . ' (' . ($tafsir['is_ulama'] ? 'Ulama' : 'User') . ') on ' . $tafsir['created_at'] . '</small>
                </div>';
            }
            
            echo '</div>';
        }
        
        // Add tafsir form for logged in users
        if (isLoggedIn()) {
            echo '<div style="margin-top: 15px;">
                <form method="post" action="?action=add_tafsir">
                    <input type="hidden" name="ayah_id" value="' . $ayah['id'] . '">
                    <div class="form-group">
                        <label for="tafsir_content">Add your Tafsir</label>
                        <textarea id="tafsir_content" name="content" rows="3" required></textarea>
                    </div>
                    <button type="submit" class="btn">Submit</button>
                </form>
            </div>';
        }
        
        echo '<hr style="margin: 20px 0;">';
    }
    
    echo '</div>
        </div>
    </div>';
}

function handleTafsir() {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_tafsir' && isLoggedIn()) {
            $ayah_id = intval($_POST['ayah_id']);
            $content = sanitizeInput($_POST['content']);
            $is_ulama = hasRole('ulama') || hasRole('admin') ? 1 : 0;
            
            $stmt = $db->prepare("INSERT INTO tafsirs (ayah_id, user_id, content, is_ulama, status) 
                                VALUES (:ayah_id, :user_id, :content, :is_ulama, 
                                CASE WHEN :is_ulama = 1 THEN 'approved' ELSE 'pending' END)");
            $stmt->bindValue(':ayah_id', $ayah_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':content', $content, SQLITE3_TEXT);
            $stmt->bindValue(':is_ulama', $is_ulama, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                displayMessage('success', 'Tafsir submitted successfully. ' . 
                    ($is_ulama ? 'It is now publicly visible.' : 'It will be visible after approval by Ulama.'));
            } else {
                displayMessage('danger', 'Failed to submit tafsir.');
            }
        }
    }
    
    // Get all tafsirs (approved or user's own)
    $query = "SELECT t.*, a.surah_id, a.ayah_num, u.username FROM tafsirs t
             JOIN ayahs a ON t.ayah_id = a.id
             JOIN users u ON t.user_id = u.id
             WHERE t.status = 'approved'";
    
    if (isLoggedIn()) {
        $query .= " OR (t.user_id = " . $_SESSION['user_id'] . " AND t.status != 'rejected')";
    }
    
    $query .= " ORDER BY t.created_at DESC";
    
    $result = $db->query($query);
    $tafsirs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tafsirs[] = $row;
    }
    
    echo '<div class="card">
        <h2>Tafsir</h2>
        <p>Explore explanations and interpretations of Quranic verses.</p>';
    
    if (isLoggedIn()) {
        echo '<a href="?page=quran" class="btn">Add New Tafsir (Select Ayah)</a>';
    }
    
    echo '<div style="margin-top: 20px;">';
    
    if (empty($tafsirs)) {
        echo '<p>No tafsirs available yet.</p>';
    } else {
        foreach ($tafsirs as $tafsir) {
            echo '<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                <h3>Surah ' . $tafsir['surah_id'] . ' Ayah ' . $tafsir['ayah_num'] . '</h3>
                <p>' . $tafsir['content'] . '</p>
                <small>By ' . $tafsir['username'] . ' (' . ($tafsir['is_ulama'] ? 'Ulama' : 'User') . ') on ' . $tafsir['created_at'] . '</small>';
            
            // Admin/Ulama actions
            if ((hasRole('ulama') && !$tafsir['is_ulama']) || hasRole('admin')) {
                echo '<div style="margin-top: 10px;">
                    <a href="?action=approve_tafsir&id=' . $tafsir['id'] . '" class="btn btn-success">Approve</a>
                    <a href="?action=reject_tafsir&id=' . $tafsir['id'] . '" class="btn btn-danger">Reject</a>
                </div>';
            }
            
            echo '</div>';
        }
    }
    
    echo '</div>
    </div>';
}

function handleThemes() {
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_theme' && isLoggedIn()) {
            $title = sanitizeInput($_POST['title']);
            $description = sanitizeInput($_POST['description']);
            $is_ulama = hasRole('ulama') || hasRole('admin') ? 1 : 0;
            
            $stmt = $db->prepare("INSERT INTO themes (title, description, user_id, is_ulama, status) 
                                VALUES (:title, :description, :user_id, :is_ulama, 
                                CASE WHEN :is_ulama = 1 THEN 'approved' ELSE 'pending' END)");
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':is_ulama', $is_ulama, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $theme_id = $db->lastInsertRowID();
                
                // Add ayahs to theme
                if (isset($_POST['ayahs']) && is_array($_POST['ayahs'])) {
                    foreach ($_POST['ayahs'] as $ayah_id) {
                        $stmt = $db->prepare("INSERT INTO theme_ayahs (theme_id, ayah_id) VALUES (:theme_id, :ayah_id)");
                        $stmt->bindValue(':theme_id', $theme_id, SQLITE3_INTEGER);
                        $stmt->bindValue(':ayah_id', intval($ayah_id), SQLITE3_INTEGER);
                        $stmt->execute();
                    }
                }
                
                displayMessage('success', 'Theme submitted successfully. ' . 
                    ($is_ulama ? 'It is now publicly visible.' : 'It will be visible after approval by Ulama.'));
            } else {
                displayMessage('danger', 'Failed to submit theme.');
            }
        }
    }
    
    // Get all themes (approved or user's own)
    $query = "SELECT th.*, u.username FROM themes th
             JOIN users u ON th.user_id = u.id
             WHERE th.status = 'approved'";
    
    if (isLoggedIn()) {
        $query .= " OR (th.user_id = " . $_SESSION['user_id'] . " AND th.status != 'rejected')";
    }
    
    $query .= " ORDER BY th.created_at DESC";
    
    $result = $db->query($query);
    $themes = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $themes[] = $row;
    }
    
    echo '<div class="card">
        <h2>Thematic Study</h2>
        <p>Explore Quranic themes and topics across different surahs and ayahs.</p>';
    
    if (isLoggedIn()) {
        echo '<a href="?page=themes&action=new" class="btn">Create New Theme</a>';
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'new') {
        echo '<div class="card" style="margin-top: 20px;">
            <h3>Create New Theme</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_theme">
                <div class="form-group">
                    <label for="title">Theme Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4" required></textarea>
                </div>
                <div class="form-group">
                    <label>Select Ayahs (Go to Quran Viewer to find ayah numbers)</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 5px;">';
        
        // List all ayahs for selection (simplified)
        $result = $db->query("SELECT a.id, a.surah_id, a.ayah_num FROM ayahs a ORDER BY a.surah_id, a.ayah_num");
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            echo '<div><input type="checkbox" name="ayahs[]" value="' . $row['id'] . '"> ' . 
                 'S' . $row['surah_id'] . ':' . $row['ayah_num'] . '</div>';
        }
        
        echo '</div>
                </div>
                <button type="submit" class="btn">Submit Theme</button>
            </form>
        </div>';
    } else {
        echo '<div style="margin-top: 20px;">';
        
        if (empty($themes)) {
            echo '<p>No themes available yet.</p>';
        } else {
            foreach ($themes as $theme) {
                echo '<div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                    <h3>' . $theme['title'] . '</h3>
                    <p>' . $theme['description'] . '</p>
                    <small>By ' . $theme['username'] . ' (' . ($theme['is_ulama'] ? 'Ulama' : 'User') . ') on ' . $theme['created_at'] . '</small>';
                
                // Get ayahs for this theme
                $stmt = $db->prepare("SELECT a.surah_id, a.ayah_num FROM theme_ayahs ta
                                    JOIN ayahs a ON ta.ayah_id = a.id
                                    WHERE ta.theme_id = :theme_id
                                    ORDER BY a.surah_id, a.ayah_num");
                $stmt->bindValue(':theme_id', $theme['id'], SQLITE3_INTEGER);
                $result = $stmt->execute();
                
                $ayahs = [];
                while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                    $ayahs[] = 'S' . $row['surah_id'] . ':' . $row['ayah_num'];
                }
                
                if (!empty($ayahs)) {
                    echo '<div style="margin-top: 10px;">
                        <strong>Related Ayahs:</strong> ' . implode(', ', $ayahs) . '
                    </div>';
                }
                
                // View button
                echo '<div style="margin-top: 10px;">
                    <a href="?page=themes&view=' . $theme['id'] . '" class="btn">View Details</a>
                </div>';
                
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
    
    echo '</div>';
}

function handleHifz() {
    if (!isLoggedIn()) {
        displayMessage('danger', 'Please login to access Hifz Hub.');
        return;
    }
    
    $db = getDB();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'add_hifz') {
            $surah_id = intval($_POST['surah_id']);
            $ayah_from = intval($_POST['ayah_from']);
            $ayah_to = intval($_POST['ayah_to']);
            $progress = intval($_POST['progress']);
            
            $stmt = $db->prepare("INSERT INTO hifz (user_id, surah_id, ayah_from, ayah_to, progress, last_reviewed) 
                                VALUES (:user_id, :surah_id, :ayah_from, :ayah_to, :progress, datetime('now'))");
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':surah_id', $surah_id, SQLITE3_INTEGER);
            $stmt->bindValue(':ayah_from', $ayah_from, SQLITE3_INTEGER);
            $stmt->bindValue(':ayah_to', $ayah_to, SQLITE3_INTEGER);
            $stmt->bindValue(':progress', $progress, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                displayMessage('success', 'Hifz entry added successfully.');
            } else {
                displayMessage('danger', 'Failed to add hifz entry.');
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'update_hifz') {
            $hifz_id = intval($_POST['hifz_id']);
            $progress = intval($_POST['progress']);
            
            $stmt = $db->prepare("UPDATE hifz SET progress = :progress, last_reviewed = datetime('now') 
                                WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':progress', $progress, SQLITE3_INTEGER);
            $stmt->bindValue(':id', $hifz_id, SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                displayMessage('success', 'Hifz progress updated successfully.');
            } else {
                displayMessage('danger', 'Failed to update hifz progress.');
            }
        }
    }
    
    // Get user's hifz progress
    $stmt = $db->prepare("SELECT h.*, s.name_ar, s.name_en FROM hifz h
                         LEFT JOIN surahs s ON h.surah_id = s.id
                         WHERE h.user_id = :user_id
                         ORDER BY h.last_reviewed DESC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $hifz_entries = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $hifz_entries[] = $row;
    }
    
    // Get surah list for dropdown
    $surahs = [];
    $result = $db->query("SELECT id, name_ar, name_en FROM surahs ORDER BY id");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $surahs[$row['id']] = $row['name_ar'] . ' (' . $row['name_en'] . ')';
    }
    
    echo '<div class="card">
        <h2>Hifz Hub</h2>
        <p>Track your Quran memorization progress.</p>';
    
    echo '<div class="flex gap-4">
        <div style="flex: 1;">
            <h3>Add New Hifz</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_hifz">
                <div class="form-group">
                    <label for="surah_id">Surah</label>
                    <select id="surah_id" name="surah_id" required>';
    
    foreach ($surahs as $id => $name) {
        echo '<option value="' . $id . '">' . $id . '. ' . $name . '</option>';
    }
    
    echo '</select>
                </div>
                <div class="form-group">
                    <label for="ayah_from">From Ayah</label>
                    <input type="number" id="ayah_from" name="ayah_from" min="1" required>
                </div>
                <div class="form-group">
                    <label for="ayah_to">To Ayah</label>
                    <input type="number" id="ayah_to" name="ayah_to" min="1" required>
                </div>
                <div class="form-group">
                    <label for="progress">Progress (%)</label>
                    <input type="range" id="progress" name="progress" min="0" max="100" value="0" oninput="document.getElementById(\'progressValue\').innerText = this.value + '%'">
                    <span id="progressValue">0%</span>
                </div>
                <button type="submit" class="btn">Add Hifz</button>
            </form>
        </div>
        <div style="flex: 2;">
            <h3>Your Hifz Progress</h3>';
    
    if (empty($hifz_entries)) {
        echo '<p>No hifz entries yet. Start by adding your first memorization section above.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Surah</th>
                    <th>Ayahs</th>
                    <th>Progress</th>
                    <th>Last Reviewed</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($hifz_entries as $entry) {
            $surah_name = $entry['name_ar'] ? $entry['name_ar'] . ' (' . $entry['name_en'] . ')' : $entry['surah_id'];
            echo '<tr>
                <td>' . $surah_name . '</td>
                <td>' . $entry['ayah_from'] . '-' . $entry['ayah_to'] . '</td>
                <td>
                    <div style="background: #eee; height: 20px; width: 100%; border-radius: 10px;">
                        <div style="background: #2ecc71; width: ' . $entry['progress'] . '%; height: 100%; border-radius: 10px;"></div>
                    </div>
                    ' . $entry['progress'] . '%
                </td>
                <td>' . $entry['last_reviewed'] . '</td>
                <td>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="update_hifz">
                        <input type="hidden" name="hifz_id" value="' . $entry['id'] . '">
                        <input type="range" name="progress" min="0" max="100" value="' . $entry['progress'] . '" 
                               oninput="this.nextElementSibling.value = this.value + '%'" 
                               onchange="this.form.submit()">
                        <output>' . $entry['progress'] . '%</output>
                    </form>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    </div>
    </div>';
}

function handleRecitation() {
    if (!isLoggedIn()) {
        displayMessage('danger', 'Please login to access Recitation Log.');
        return;
    }
    
    $db = getDB();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_recitation') {
            $surah_id = intval($_POST['surah_id']);
            $ayah_from = intval($_POST['ayah_from']);
            $ayah_to = intval($_POST['ayah_to']);
            $duration = intval($_POST['duration']);
            
            $stmt = $db->prepare("INSERT INTO recitation_logs (user_id, surah_id, ayah_from, ayah_to, duration) 
                                VALUES (:user_id, :surah_id, :ayah_from, :ayah_to, :duration)");
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(':surah_id', $surah_id, SQLITE3_INTEGER);
            $stmt->bindValue(':ayah_from', $ayah_from, SQLITE3_INTEGER);
            $stmt->bindValue(':ayah_to', $ayah_to, SQLITE3_INTEGER);
            $stmt->bindValue(':duration', $duration, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                displayMessage('success', 'Recitation logged successfully.');
            } else {
                displayMessage('danger', 'Failed to log recitation.');
            }
        }
    }
    
    // Get user's recitation logs
    $stmt = $db->prepare("SELECT r.*, s.name_ar, s.name_en FROM recitation_logs r
                         LEFT JOIN surahs s ON r.surah_id = s.id
                         WHERE r.user_id = :user_id
                         ORDER BY r.date DESC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $logs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $logs[] = $row;
    }
    
    // Get surah list for dropdown
    $surahs = [];
    $result = $db->query("SELECT id, name_ar, name_en FROM surahs ORDER BY id");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $surahs[$row['id']] = $row['name_ar'] . ' (' . $row['name_en'] . ')';
    }
    
    echo '<div class="card">
        <h2>Recitation Log</h2>
        <p>Track your Quran recitation practice.</p>';
    
    echo '<div class="flex gap-4">
        <div style="flex: 1;">
            <h3>Log New Recitation</h3>
            <form method="post">
                <input type="hidden" name="action" value="add_recitation">
                <div class="form-group">
                    <label for="surah_id">Surah</label>
                    <select id="surah_id" name="surah_id" required>';
    
    foreach ($surahs as $id => $name) {
        echo '<option value="' . $id . '">' . $id . '. ' . $name . '</option>';
    }
    
    echo '</select>
                </div>
                <div class="form-group">
                    <label for="ayah_from">From Ayah</label>
                    <input type="number" id="ayah_from" name="ayah_from" min="1" required>
                </div>
                <div class="form-group">
                    <label for="ayah_to">To Ayah</label>
                    <input type="number" id="ayah_to" name="ayah_to" min="1" required>
                </div>
                <div class="form-group">
                    <label for="duration">Duration (minutes)</label>
                    <input type="number" id="duration" name="duration" min="1" required>
                </div>
                <button type="submit" class="btn">Log Recitation</button>
            </form>
        </div>
        <div style="flex: 2;">
            <h3>Your Recitation History</h3>';
    
    if (empty($logs)) {
        echo '<p>No recitation logs yet. Start by logging your first recitation above.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Surah</th>
                    <th>Ayahs</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($logs as $log) {
            $surah_name = $log['name_ar'] ? $log['name_ar'] . ' (' . $log['name_en'] . ')' : $log['surah_id'];
            echo '<tr>
                <td>' . $log['date'] . '</td>
                <td>' . $surah_name . '</td>
                <td>' . $log['ayah_from'] . '-' . $log['ayah_to'] . '</td>
                <td>' . $log['duration'] . ' minutes</td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    </div>
    </div>';
}

function handleContributions() {
    if (!isLoggedIn()) {
        displayMessage('danger', 'Please login to access Contributions.');
        return;
    }
    
    $db = getDB();
    $user = currentUser();
    
    // Handle contribution actions
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'delete_tafsir' && isset($_GET['id'])) {
            $stmt = $db->prepare("DELETE FROM tafsirs WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Tafsir deleted successfully.');
            } else {
                displayMessage('danger', 'Failed to delete tafsir.');
            }
        } elseif ($_GET['action'] === 'delete_theme' && isset($_GET['id'])) {
            $stmt = $db->prepare("DELETE FROM themes WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            if ($stmt->execute()) {
                // Also delete related ayahs
                $stmt = $db->prepare("DELETE FROM theme_ayahs WHERE theme_id = :theme_id");
                $stmt->bindValue(':theme_id', intval($_GET['id']), SQLITE3_INTEGER);
                $stmt->execute();
                
                displayMessage('success', 'Theme deleted successfully.');
            } else {
                displayMessage('danger', 'Failed to delete theme.');
            }
        } elseif ($_GET['action'] === 'delete_word_contribution' && isset($_GET['id'])) {
            $stmt = $db->prepare("DELETE FROM word_contributions WHERE id = :id AND user_id = :user_id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Word contribution deleted successfully.');
            } else {
                displayMessage('danger', 'Failed to delete word contribution.');
            }
        }
    }
    
    // Get user's contributions
    $tafsirs = [];
    $stmt = $db->prepare("SELECT t.*, a.surah_id, a.ayah_num FROM tafsirs t
                         JOIN ayahs a ON t.ayah_id = a.id
                         WHERE t.user_id = :user_id
                         ORDER BY t.created_at DESC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tafsirs[] = $row;
    }
    
    $themes = [];
    $stmt = $db->prepare("SELECT * FROM themes WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $themes[] = $row;
    }
    
    $word_contributions = [];
    $stmt = $db->prepare("SELECT * FROM word_contributions WHERE user_id = :user_id ORDER BY created_at DESC");
    $stmt->bindValue(':user_id', $_SESSION['user_id'], SQLITE3_INTEGER);
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $word_contributions[] = $row;
    }
    
    echo '<div class="card">
        <h2>My Contributions</h2>
        <p>Manage your contributions to the community.</p>';
    
    echo '<div style="margin-top: 20px;">
        <h3>My Tafsirs</h3>';
    
    if (empty($tafsirs)) {
        echo '<p>No tafsir contributions yet.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Surah:Ayah</th>
                    <th>Content</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($tafsirs as $tafsir) {
            echo '<tr>
                <td>' . $tafsir['surah_id'] . ':' . $tafsir['ayah_num'] . '</td>
                <td>' . substr($tafsir['content'], 0, 50) . (strlen($tafsir['content']) > 50 ? '...' : '') . '</td>
                <td>' . ucfirst($tafsir['status']) . '</td>
                <td>' . $tafsir['created_at'] . '</td>
                <td>
                    <a href="?page=quran&surah=' . $tafsir['surah_id'] . '#ayah-' . $tafsir['ayah_num'] . '" class="btn">View</a>
                    <a href="?page=contributions&action=delete_tafsir&id=' . $tafsir['id'] . '" class="btn btn-danger">Delete</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    
    <div style="margin-top: 20px;">
        <h3>My Themes</h3>';
    
    if (empty($themes)) {
        echo '<p>No theme contributions yet.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($themes as $theme) {
            echo '<tr>
                <td>' . $theme['title'] . '</td>
                <td>' . substr($theme['description'], 0, 50) . (strlen($theme['description']) > 50 ? '...' : '') . '</td>
                <td>' . ucfirst($theme['status']) . '</td>
                <td>' . $theme['created_at'] . '</td>
                <td>
                    <a href="?page=themes&view=' . $theme['id'] . '" class="btn">View</a>
                    <a href="?page=contributions&action=delete_theme&id=' . $theme['id'] . '" class="btn btn-danger">Delete</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    
    <div style="margin-top: 20px;">
        <h3>My Word Contributions</h3>';
    
    if (empty($word_contributions)) {
        echo '<p>No word contributions yet.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Word ID</th>
                    <th>Urdu Meaning</th>
                    <th>English Meaning</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($word_contributions as $contribution) {
            echo '<tr>
                <td>' . $contribution['word_id'] . '</td>
                <td>' . $contribution['ur_meaning'] . '</td>
                <td>' . $contribution['en_meaning'] . '</td>
                <td>' . ucfirst($contribution['status']) . '</td>
                <td>' . $contribution['created_at'] . '</td>
                <td>
                    <a href="?page=contributions&action=delete_word_contribution&id=' . $contribution['id'] . '" class="btn btn-danger">Delete</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    </div>';
}

function handleUlamaDashboard() {
    if (!hasRole('ulama') && !hasRole('admin')) {
        displayMessage('danger', 'You do not have permission to access this page.');
        return;
    }
    
    $db = getDB();
    
    // Handle approval actions
    if (isset($_GET['action'])) {
        if ($_GET['action'] === 'approve_tafsir' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE tafsirs SET status = 'approved' WHERE id = :id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Tafsir approved successfully.');
            } else {
                displayMessage('danger', 'Failed to approve tafsir.');
            }
        } elseif ($_GET['action'] === 'reject_tafsir' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE tafsirs SET status = 'rejected' WHERE id = :id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Tafsir rejected successfully.');
            } else {
                displayMessage('danger', 'Failed to reject tafsir.');
            }
        } elseif ($_GET['action'] === 'approve_theme' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE themes SET status = 'approved' WHERE id = :id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Theme approved successfully.');
            } else {
                displayMessage('danger', 'Failed to approve theme.');
            }
        } elseif ($_GET['action'] === 'reject_theme' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE themes SET status = 'rejected' WHERE id = :id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Theme rejected successfully.');
            } else {
                displayMessage('danger', 'Failed to reject theme.');
            }
        } elseif ($_GET['action'] === 'approve_word' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE word_contributions SET status = 'approved' WHERE id = :id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            if ($stmt->execute()) {
                // Add to word meanings
                $stmt = $db->prepare("SELECT * FROM word_contributions WHERE id = :id");
                $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
                $result = $stmt->execute();
                $word = $result->fetchArray(SQLITE3_ASSOC);
                
                if ($word) {
                    $stmt = $db->prepare("INSERT OR REPLACE INTO word_meanings (word_id, ur_meaning, en_meaning) 
                                         VALUES (:word_id, :ur_meaning, :en_meaning)");
                    $stmt->bindValue(':word_id', $word['word_id'], SQLITE3_TEXT);
                    $stmt->bindValue(':ur_meaning', $word['ur_meaning'], SQLITE3_TEXT);
                    $stmt->bindValue(':en_meaning', $word['en_meaning'], SQLITE3_TEXT);
                    $stmt->execute();
                }
                
                displayMessage('success', 'Word contribution approved and added to dictionary.');
            } else {
                displayMessage('danger', 'Failed to approve word contribution.');
            }
        } elseif ($_GET['action'] === 'reject_word' && isset($_GET['id'])) {
            $stmt = $db->prepare("UPDATE word_contributions SET status = 'rejected' WHERE id = :id");
            $stmt->bindValue(':id', intval($_GET['id']), SQLITE3_INTEGER);
            if ($stmt->execute()) {
                displayMessage('success', 'Word contribution rejected.');
            } else {
                displayMessage('danger', 'Failed to reject word contribution.');
            }
        }
    }
    
    // Get pending contributions
    $pending_tafsirs = [];
    $stmt = $db->prepare("SELECT t.*, a.surah_id, a.ayah_num, u.username FROM tafsirs t
                         JOIN ayahs a ON t.ayah_id = a.id
                         JOIN users u ON t.user_id = u.id
                         WHERE t.status = 'pending' AND t.is_ulama = 0
                         ORDER BY t.created_at DESC");
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pending_tafsirs[] = $row;
    }
    
    $pending_themes = [];
    $stmt = $db->prepare("SELECT t.*, u.username FROM themes t
                         JOIN users u ON t.user_id = u.id
                         WHERE t.status = 'pending' AND t.is_ulama = 0
                         ORDER BY t.created_at DESC");
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pending_themes[] = $row;
    }
    
    $pending_words = [];
    $stmt = $db->prepare("SELECT w.*, u.username FROM word_contributions w
                         JOIN users u ON w.user_id = u.id
                         WHERE w.status = 'pending'
                         ORDER BY w.created_at DESC");
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $pending_words[] = $row;
    }
    
    echo '<div class="card">
        <h2>Ulama Dashboard</h2>
        <p>Review and approve community contributions.</p>';
    
    echo '<div style="margin-top: 20px;">
        <h3>Pending Tafsirs</h3>';
    
    if (empty($pending_tafsirs)) {
        echo '<p>No pending tafsirs to review.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Surah:Ayah</th>
                    <th>Content</th>
                    <th>Contributor</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($pending_tafsirs as $tafsir) {
            echo '<tr>
                <td>' . $tafsir['surah_id'] . ':' . $tafsir['ayah_num'] . '</td>
                <td>' . substr($tafsir['content'], 0, 50) . (strlen($tafsir['content']) > 50 ? '...' : '') . '</td>
                <td>' . $tafsir['username'] . '</td>
                <td>' . $tafsir['created_at'] . '</td>
                <td>
                    <a href="?page=quran&surah=' . $tafsir['surah_id'] . '#ayah-' . $tafsir['ayah_num'] . '" class="btn">View</a>
                    <a href="?page=ulama&action=approve_tafsir&id=' . $tafsir['id'] . '" class="btn btn-success">Approve</a>
                    <a href="?page=ulama&action=reject_tafsir&id=' . $tafsir['id'] . '" class="btn btn-danger">Reject</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    
    <div style="margin-top: 20px;">
        <h3>Pending Themes</h3>';
    
    if (empty($pending_themes)) {
        echo '<p>No pending themes to review.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Contributor</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($pending_themes as $theme) {
            echo '<tr>
                <td>' . $theme['title'] . '</td>
                <td>' . substr($theme['description'], 0, 50) . (strlen($theme['description']) > 50 ? '...' : '') . '</td>
                <td>' . $theme['username'] . '</td>
                <td>' . $theme['created_at'] . '</td>
                <td>
                    <a href="?page=themes&view=' . $theme['id'] . '" class="btn">View</a>
                    <a href="?page=ulama&action=approve_theme&id=' . $theme['id'] . '" class="btn btn-success">Approve</a>
                    <a href="?page=ulama&action=reject_theme&id=' . $theme['id'] . '" class="btn btn-danger">Reject</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    
    <div style="margin-top: 20px;">
        <h3>Pending Word Contributions</h3>';
    
    if (empty($pending_words)) {
        echo '<p>No pending word contributions to review.</p>';
    } else {
        echo '<table>
            <thead>
                <tr>
                    <th>Word ID</th>
                    <th>Urdu Meaning</th>
                    <th>English Meaning</th>
                    <th>Contributor</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
        
        foreach ($pending_words as $word) {
            echo '<tr>
                <td>' . $word['word_id'] . '</td>
                <td>' . $word['ur_meaning'] . '</td>
                <td>' . $word['en_meaning'] . '</td>
                <td>' . $word['username'] . '</td>
                <td>' . $word['created_at'] . '</td>
                <td>
                    <a href="?page=ulama&action=approve_word&id=' . $word['id'] . '" class="btn btn-success">Approve</a>
                    <a href="?page=ulama&action=reject_word&id=' . $word['id'] . '" class="btn btn-danger">Reject</a>
                </td>
            </tr>';
        }
        
        echo '</tbody>
        </table>';
    }
    
    echo '</div>
    </div>';
}

function handleAdminPanel() {
    if (!hasRole('admin')) {
        displayMessage('danger', 'You do not have permission to access this page.');
        return;
    }
    
    $db = getDB();
    
    // Handle admin actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'load_data') {
            $file_type = sanitizeInput($_POST['file_type']);
            $file_path = sanitizeInput($_POST['file_path']);
            
            if (file_exists($file_path)) {
                switch ($file_type) {
                    case 'urdu_translation':
                        loadTranslationFile($file_path, 'ur');
                        break;
                    case 'english_translation':
                        loadTranslationFile($file_path, 'en');
                        break;
                    case 'bengali_translation':
                        loadTranslationFile($file_path, 'bn');
                        break;
                    case 'word_meanings':
                        loadWordMeanings($file_path);
                        break;
                    case 'word_metadata':
                        loadWordMetadata($file_path);
                        break;
                    default:
                        displayMessage('danger', 'Invalid file type selected.');
                        break;
                }
                
                displayMessage('success', 'Data loaded successfully from ' . $file_path);
            } else {
                displayMessage('danger', 'File not found: ' . $file_path);
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'promote_user') {
            $user_id = intval($_POST['user_id']);
            $role = sanitizeInput($_POST['role']);
            
            $stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :id");
            $stmt->bindValue(':role', $role, SQLITE3_TEXT);
            $stmt->bindValue(':id', $user_id, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                displayMessage('success', 'User role updated successfully.');
            } else {
                displayMessage('danger', 'Failed to update user role.');
            }
        }
    }
    
    // Get all users
    $users = [];
    $result = $db->query("SELECT * FROM users ORDER BY role, username");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    
    echo '<div class="card">
        <h2>Admin Panel</h2>';
    
    echo '<div class="flex gap-4">
        <div style="flex: 1;">
            <h3>Load Data Files</h3>
            <form method="post">
                <input type="hidden" name="action" value="load_data">
                <div class="form-group">
                    <label for="file_type">File Type</label>
                    <select id="file_type" name="file_type" required>
                        <option value="urdu_translation">Urdu Translation (data new.AM)</option>
                        <option value="english_translation">English Translation (dataENG.AM)</option>
                        <option value="bengali_translation">Bengali Translation (dataBNG.AM)</option>
                        <option value="word_meanings">Word Meanings (data5 new.AM)</option>
                        <option value="word_metadata">Word Metadata (word2.AM)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="file_path">File Path</label>
                    <input type="text" id="file_path" name="file_path" required placeholder="e.g., /path/to/data new.AM">
                </div>
                <button type="submit" class="btn">Load Data</button>
            </form>
        </div>
        <div style="flex: 2;">
            <h3>User Management</h3>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Last Login</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($users as $user) {
        echo '<tr>
            <td>' . $user['username'] . '</td>
            <td>' . $user['email'] . '</td>
            <td>' . ucfirst($user['role']) . '</td>
            <td>' . ($user['last_login'] ? $user['last_login'] : 'Never') . '</td>
            <td>
                <form method="post" style="display: inline;">
                    <input type="hidden" name="action" value="promote_user">
                    <input type="hidden" name="user_id" value="' . $user['id'] . '">
                    <select name="role" onchange="this.form.submit()">
                        <option value="public"' . ($user['role'] === 'public' ? ' selected' : '') . '>Public</option>
                        <option value="user"' . ($user['role'] === 'user' ? ' selected' : '') . '>User</option>
                        <option value="ulama"' . ($user['role'] === 'ulama' ? ' selected' : '') . '>Ulama</option>
                        <option value="admin"' . ($user['role'] === 'admin' ? ' selected' : '') . '>Admin</option>
                    </select>
                </form>
            </td>
        </tr>';
    }
    
    echo '</tbody>
            </table>
        </div>
    </div>
    </div>';
}

function handleProfile() {
    if (!isLoggedIn()) {
        displayMessage('danger', 'Please login to access your profile.');
        return;
    }
    
    $db = getDB();
    $user = currentUser();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
            $email = sanitizeInput($_POST['email']);
            $current_password = sanitizeInput($_POST['current_password']);
            $new_password = sanitizeInput($_POST['new_password']);
            
            // Verify current password if changing password
            if (!empty($new_password) && !password_verify($current_password, $user['password'])) {
                displayMessage('danger', 'Current password is incorrect.');
                return;
            }
            
            // Update profile
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET email = :email, password = :password WHERE id = :id");
                $stmt->bindValue(':password', $hashed, SQLITE3_TEXT);
            } else {
                $stmt = $db->prepare("UPDATE users SET email = :email WHERE id = :id");
            }
            
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':id', $user['id'], SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                displayMessage('success', 'Profile updated successfully.');
                // Refresh user data
                $user = currentUser();
            } else {
                displayMessage('danger', 'Failed to update profile.');
            }
        }
    }
    
    echo '<div class="card">
        <h2>Profile</h2>
        <form method="post">
            <input type="hidden" name="action" value="update_profile">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" value="' . $user['username'] . '" disabled>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="' . $user['email'] . '" required>
            </div>
            <div class="form-group">
                <label for="current_password">Current Password (only required if changing password)</label>
                <input type="password" id="current_password" name="current_password">
            </div>
            <div class="form-group">
                <label for="new_password">New Password (leave blank to keep current)</label>
                <input type="password" id="new_password" name="new_password">
            </div>
            <button type="submit" class="btn">Update Profile</button>
        </form>
    </div>';
}

// Initialize database if not exists
initDB();

// Handle the request
handleRequest();
?>
<!-- <?php

// --- Complete Surah Data Array ---
// Place this array near the bottom of your single PHP file,
// before the populate function.
$all_surahs_data = [
    [
        'id' => 1,
        'name_ar' => 'الفاتحة',
        'name_en' => 'Al-Fatiha',
        'name_ur' => 'الفاتحة',
        'name_bn' => 'সূরা আল-ফাতিহা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 7
    ],
    [
        'id' => 2,
        'name_ar' => 'البقرة',
        'name_en' => 'Al-Baqara',
        'name_ur' => 'البقرة',
        'name_bn' => 'সূরা আল-বাকারা',
        'revelation_place' => 'Medina',
        'ayah_count' => 286
    ],
    [
        'id' => 3,
        'name_ar' => 'آل عمران',
        'name_en' => 'Al-Imran',
        'name_ur' => 'آل عمران',
        'name_bn' => 'সূরা আলে ইমরান',
        'revelation_place' => 'Medina',
        'ayah_count' => 200
    ],
    [
        'id' => 4,
        'name_ar' => 'النساء',
        'name_en' => 'An-Nisa',
        'name_ur' => 'النساء',
        'name_bn' => 'সূরা আন-নিসা',
        'revelation_place' => 'Medina',
        'ayah_count' => 176
    ],
    [
        'id' => 5,
        'name_ar' => 'المائدة',
        'name_en' => 'Al-Maidah',
        'name_ur' => 'المائدة',
        'name_bn' => 'সূরা আল-মায়িদah',
        'revelation_place' => 'Medina',
        'ayah_count' => 120
    ],
    [
        'id' => 6,
        'name_ar' => 'الأنعام',
        'name_en' => 'Al-An\'am',
        'name_ur' => 'الأنعام',
        'name_bn' => 'সূরা আল-আন\'আম',
        'revelation_place' => 'Mecca',
        'ayah_count' => 165
    ],
    [
        'id' => 7,
        'name_ar' => 'الأعراف',
        'name_en' => 'Al-A\'raf',
        'name_ur' => 'الأعراف',
        'name_bn' => 'সূরা আল-আ\'রাফ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 206
    ],
    [
        'id' => 8,
        'name_ar' => 'الأنفال',
        'name_en' => 'Al-Anfal',
        'name_ur' => 'الأنفال',
        'name_bn' => 'সূরা আল-আনফাল',
        'revelation_place' => 'Medina',
        'ayah_count' => 75
    ],
    [
        'id' => 9,
        'name_ar' => 'التوبة',
        'name_en' => 'At-Tawbah',
        'name_ur' => 'التوبة',
        'name_bn' => 'সূরা আত-তাওবাহ',
        'revelation_place' => 'Medina',
        'ayah_count' => 129
    ],
    [
        'id' => 10,
        'name_ar' => 'يونس',
        'name_en' => 'Yunus',
        'name_ur' => 'یونس',
        'name_bn' => 'সূরা ইউনুস',
        'revelation_place' => 'Mecca',
        'ayah_count' => 109
    ],
    [
        'id' => 11,
        'name_ar' => 'هود',
        'name_en' => 'Hud',
        'name_ur' => 'ھود',
        'name_bn' => 'সূরা হুদ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 123
    ],
    [
        'id' => 12,
        'name_ar' => 'يوسف',
        'name_en' => 'Yusuf',
        'name_ur' => 'یوسف',
        'name_bn' => 'সূরা ইউসুফ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 111
    ],
    [
        'id' => 13,
        'name_ar' => 'الرعد',
        'name_en' => 'Ar-Ra\'d',
        'name_ur' => 'الرعد',
        'name_bn' => 'সূরা আর-রা\'দ',
        'revelation_place' => 'Medina',
        'ayah_count' => 43
    ],
    [
        'id' => 14,
        'name_ar' => 'ابراهيم',
        'name_en' => 'Ibrahim',
        'name_ur' => 'ابراہیم',
        'name_bn' => 'সূরা ইবরাহীম',
        'revelation_place' => 'Mecca',
        'ayah_count' => 52
    ],
    [
        'id' => 15,
        'name_ar' => 'الحجر',
        'name_en' => 'Al-Hijr',
        'name_ur' => 'الحجر',
        'name_bn' => 'সূরা আল-হিজর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 99
    ],
    [
        'id' => 16,
        'name_ar' => 'النحل',
        'name_en' => 'An-Nahl',
        'name_ur' => 'النحل',
        'name_bn' => 'সূরা আন-নাহল',
        'revelation_place' => 'Mecca',
        'ayah_count' => 128
    ],
    [
        'id' => 17,
        'name_ar' => 'الإسراء',
        'name_en' => 'Al-Isra',
        'name_ur' => 'الإسراء',
        'name_bn' => 'সূরা আল-ইসরা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 111
    ],
    [
        'id' => 18,
        'name_ar' => 'الكهف',
        'name_en' => 'Al-Kahf',
        'name_ur' => 'الكهف',
        'name_bn' => 'সূরা আল-কাহফ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 110
    ],
    [
        'id' => 19,
        'name_ar' => 'مريم',
        'name_en' => 'Maryam',
        'name_ur' => 'مریم',
        'name_bn' => 'সূরা মারইয়াম',
        'revelation_place' => 'Mecca',
        'ayah_count' => 98
    ],
    [
        'id' => 20,
        'name_ar' => 'طه',
        'name_en' => 'Ta-Ha',
        'name_ur' => 'طه',
        'name_bn' => 'সূরা ত্বাহা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 135
    ],
    [
        'id' => 21,
        'name_ar' => 'الأنبياء',
        'name_en' => 'Al-Anbiya',
        'name_ur' => 'الأنبیاء',
        'name_bn' => 'সূরা আল-আম্বিয়া',
        'revelation_place' => 'Mecca',
        'ayah_count' => 112
    ],
    [
        'id' => 22,
        'name_ar' => 'الحج',
        'name_en' => 'Al-Hajj',
        'name_ur' => 'الحج',
        'name_bn' => 'সূরা আল-হাজ্জ',
        'revelation_place' => 'Medina', // Note: Some sources say mixed Mecca/Medina, but predominantly Medina
        'ayah_count' => 78
    ],
    [
        'id' => 23,
        'name_ar' => 'المؤمنون',
        'name_en' => 'Al-Mu\'minun',
        'name_ur' => 'المؤمنون',
        'name_bn' => 'সূরা আল-মুমিনুন',
        'revelation_place' => 'Mecca',
        'ayah_count' => 118
    ],
    [
        'id' => 24,
        'name_ar' => 'النور',
        'name_en' => 'An-Nur',
        'name_ur' => 'النور',
        'name_bn' => 'সূরা আন-নূর',
        'revelation_place' => 'Medina',
        'ayah_count' => 64
    ],
    [
        'id' => 25,
        'name_ar' => 'الفرقان',
        'name_en' => 'Al-Furqan',
        'name_ur' => 'الفرقان',
        'name_bn' => 'সূরা আল-ফুরকান',
        'revelation_place' => 'Mecca',
        'ayah_count' => 77
    ],
    [
        'id' => 26,
        'name_ar' => 'الشعراء',
        'name_en' => 'Ash-Shu\'ara',
        'name_ur' => 'الشعراء',
        'name_bn' => 'সূরা আশ-শুআরা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 227
    ],
    [
        'id' => 27,
        'name_ar' => 'النمل',
        'name_en' => 'An-Naml',
        'name_ur' => 'النمل',
        'name_bn' => 'সূরা আন-নামল',
        'revelation_place' => 'Mecca',
        'ayah_count' => 93
    ],
    [
        'id' => 28,
        'name_ar' => 'القصص',
        'name_en' => 'Al-Qasas',
        'name_ur' => 'القصص',
        'name_bn' => 'সূরা আল-কাসাস',
        'revelation_place' => 'Mecca',
        'ayah_count' => 88
    ],
    [
        'id' => 29,
        'name_ar' => 'العنكبوت',
        'name_en' => 'Al-Ankabut',
        'name_ur' => 'العنکبوت',
        'name_bn' => 'সূরা আল-আনকাবূত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 69
    ],
    [
        'id' => 30,
        'name_ar' => 'الروم',
        'name_en' => 'Ar-Rum',
        'name_ur' => 'الروم',
        'name_bn' => 'সূরা আর-রূম',
        'revelation_place' => 'Mecca',
        'ayah_count' => 60
    ],
    [
        'id' => 31,
        'name_ar' => 'لقمان',
        'name_en' => 'Luqman',
        'name_ur' => 'لقمان',
        'name_bn' => 'সূরা লুকমান',
        'revelation_place' => 'Mecca',
        'ayah_count' => 34
    ],
    [
        'id' => 32,
        'name_ar' => 'السجدة',
        'name_en' => 'As-Sajda',
        'name_ur' => 'السجدة',
        'name_bn' => 'সূরা আস-সাজদাহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 30
    ],
    [
        'id' => 33,
        'name_ar' => 'الأحزاب',
        'name_en' => 'Al-Ahzab',
        'name_ur' => 'الأحزاب',
        'name_bn' => 'সূরা আল-আহযাব',
        'revelation_place' => 'Medina',
        'ayah_count' => 73
    ],
    [
        'id' => 34,
        'name_ar' => 'سبأ',
        'name_en' => 'Saba',
        'name_ur' => 'سبأ',
        'name_bn' => 'সূরা সাবা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 54
    ],
    [
        'id' => 35,
        'name_ar' => 'فاطر',
        'name_en' => 'Fatir',
        'name_ur' => 'فاطر',
        'name_bn' => 'সূরা ফাতির',
        'revelation_place' => 'Mecca',
        'ayah_count' => 45
    ],
    [
        'id' => 36,
        'name_ar' => 'يس',
        'name_en' => 'Ya-Sin',
        'name_ur' => 'یس',
        'name_bn' => 'সূরা ইয়াসীন',
        'revelation_place' => 'Mecca',
        'ayah_count' => 83
    ],
    [
        'id' => 37,
        'name_ar' => 'الصافات',
        'name_en' => 'As-Saffat',
        'name_ur' => 'الصافات',
        'name_bn' => 'সূরা আস-সাফফাত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 182
    ],
    [
        'id' => 38,
        'name_ar' => 'ص',
        'name_en' => 'Sad',
        'name_ur' => 'ص',
        'name_bn' => 'সূরা ছোয়اد',
        'revelation_place' => 'Mecca',
        'ayah_count' => 88
    ],
    [
        'id' => 39,
        'name_ar' => 'الزمر',
        'name_en' => 'Az-Zumar',
        'name_ur' => 'الزمر',
        'name_bn' => 'সূরা আয-যুমার',
        'revelation_place' => 'Mecca',
        'ayah_count' => 75
    ],
    [
        'id' => 40,
        'name_ar' => 'غافر',
        'name_en' => 'Ghafir',
        'name_ur' => 'غافر',
        'name_bn' => 'সূরা গাফির',
        'revelation_place' => 'Mecca',
        'ayah_count' => 85
    ],
    [
        'id' => 41,
        'name_ar' => 'فصلت',
        'name_en' => 'Fussilat',
        'name_ur' => 'فصلت',
        'name_bn' => 'সূরা ফুছছিলাত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 54
    ],
    [
        'id' => 42,
        'name_ar' => 'الشورى',
        'name_en' => 'Ash-Shura',
        'name_ur' => 'الشورٰی',
        'name_bn' => 'সূরা আশ-শূরা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 53
    ],
    [
        'id' => 43,
        'name_ar' => 'الزخرف',
        'name_en' => 'Az-Zukhruf',
        'name_ur' => 'الزخرف',
        'name_bn' => 'সূরা আয-যুখরুফ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 89
    ],
    [
        'id' => 44,
        'name_ar' => 'الدخان',
        'name_en' => 'Ad-Dukhan',
        'name_ur' => 'الدخان',
        'name_bn' => 'সূরা আদ-দুخان',
        'revelation_place' => 'Mecca',
        'ayah_count' => 59
    ],
    [
        'id' => 45,
        'name_ar' => 'الجاثية',
        'name_en' => 'Al-Jathiya',
        'name_ur' => 'الجاثیة',
        'name_bn' => 'সূরা আল-জাসিয়াহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 37
    ],
    [
        'id' => 46,
        'name_ar' => 'الأحقاف',
        'name_en' => 'Al-Ahqaf',
        'name_ur' => 'الأحقاف',
        'name_bn' => 'সূরা আল-আহকাফ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 35
    ],
    [
        'id' => 47,
        'name_ar' => 'محمد',
        'name_en' => 'Muhammad',
        'name_ur' => 'محمد',
        'name_bn' => 'সূরা মুহাম্মাদ',
        'revelation_place' => 'Medina',
        'ayah_count' => 38
    ],
    [
        'id' => 48,
        'name_ar' => 'الفتح',
        'name_en' => 'Al-Fath',
        'name_ur' => 'الفتح',
        'name_bn' => 'সূরা আল-ফাতহ',
        'revelation_place' => 'Medina',
        'ayah_count' => 29
    ],
    [
        'id' => 49,
        'name_ar' => 'الحجرات',
        'name_en' => 'Al-Hujurat',
        'name_ur' => 'الحجرات',
        'name_bn' => 'সূরা আল-হুজুরাত',
        'revelation_place' => 'Medina',
        'ayah_count' => 18
    ],
    [
        'id' => 50,
        'name_ar' => 'ق',
        'name_en' => 'Qaf',
        'name_ur' => 'ق',
        'name_bn' => 'সূরা কাফ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 45
    ],
    [
        'id' => 51,
        'name_ar' => 'الذاريات',
        'name_en' => 'Adh-Dhariyat',
        'name_ur' => 'الذاریات',
        'name_bn' => 'সূরা আয-যারিয়াত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 60
    ],
    [
        'id' => 52,
        'name_ar' => 'الطور',
        'name_en' => 'At-Tur',
        'name_ur' => 'الطور',
        'name_bn' => 'সূরা আত-তূর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 49
    ],
    [
        'id' => 53,
        'name_ar' => 'النجم',
        'name_en' => 'An-Najm',
        'name_ur' => 'النجم',
        'name_bn' => 'সূরা আন-নাজম',
        'revelation_place' => 'Mecca',
        'ayah_count' => 62
    ],
    [
        'id' => 54,
        'name_ar' => 'القمر',
        'name_en' => 'Al-Qamar',
        'name_ur' => 'القمر',
        'name_bn' => 'সূরা আল-কামার',
        'revelation_place' => 'Mecca',
        'ayah_count' => 55
    ],
    [
        'id' => 55,
        'name_ar' => 'الرحمن',
        'name_en' => 'Ar-Rahman',
        'name_ur' => 'الرحمن',
        'name_bn' => 'সূরা আর-রাحমান',
        'revelation_place' => 'Medina', // Note: Some sources say Mecca, but predominantly Medina
        'ayah_count' => 78
    ],
    [
        'id' => 56,
        'name_ar' => 'الواقعة',
        'name_en' => 'Al-Waqi\'a',
        'name_ur' => 'الواقعة',
        'name_bn' => 'সূরা আল-ওয়াকিআ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 96
    ],
    [
        'id' => 57,
        'name_ar' => 'الحديد',
        'name_en' => 'Al-Hadid',
        'name_ur' => 'الحدید',
        'name_bn' => 'সূরা আল-হাদীদ',
        'revelation_place' => 'Medina',
        'ayah_count' => 29
    ],
    [
        'id' => 58,
        'name_ar' => 'المجادلة',
        'name_en' => 'Al-Mujadila',
        'name_ur' => 'المجادلة',
        'name_bn' => 'সূরা আল-মুজাদালাহ',
        'revelation_place' => 'Medina',
        'ayah_count' => 22
    ],
    [
        'id' => 59,
        'name_ar' => 'الحشر',
        'name_en' => 'Al-Hashr',
        'name_ur' => 'الحشر',
        'name_bn' => 'সূরা আল-হাশর',
        'revelation_place' => 'Medina',
        'ayah_count' => 24
    ],
    [
        'id' => 60,
        'name_ar' => 'الممتحنة',
        'name_en' => 'Al-Mumtahana',
        'name_ur' => 'الممتحنة',
        'name_bn' => 'সূরা আল-মুমতাহিনاه',
        'revelation_place' => 'Medina',
        'ayah_count' => 13
    ],
    [
        'id' => 61,
        'name_ar' => 'الصف',
        'name_en' => 'As-Saff',
        'name_ur' => 'الصف',
        'name_bn' => 'সূরা আস-সাফ',
        'revelation_place' => 'Medina',
        'ayah_count' => 14
    ],
    [
        'id' => 62,
        'name_ar' => 'الجمعة',
        'name_en' => 'Al-Jumu\'a',
        'name_ur' => 'الجمعة',
        'name_bn' => 'সূরা আল-জুমুআ',
        'revelation_place' => 'Medina',
        'ayah_count' => 11
    ],
    [
        'id' => 63,
        'name_ar' => 'المنافقون',
        'name_en' => 'Al-Munafiqun',
        'name_ur' => 'المنافقون',
        'name_bn' => 'সূরা আল-মুনাফিকুন',
        'revelation_place' => 'Medina',
        'ayah_count' => 11
    ],
    [
        'id' => 64,
        'name_ar' => 'التغابن',
        'name_en' => 'At-Taghabun',
        'name_ur' => 'التغابن',
        'name_bn' => 'সূরা আত-তাগাবুন',
        'revelation_place' => 'Medina',
        'ayah_count' => 18
    ],
    [
        'id' => 65,
        'name_ar' => 'الطلاق',
        'name_en' => 'At-Talaq',
        'name_ur' => 'الطلاق',
        'name_bn' => 'সূরা আত-তালাক',
        'revelation_place' => 'Medina',
        'ayah_count' => 12
    ],
    [
        'id' => 66,
        'name_ar' => 'التحريم',
        'name_en' => 'At-Tahrim',
        'name_ur' => 'التحریم',
        'name_bn' => 'সূরা আত-তাহরীম',
        'revelation_place' => 'Medina',
        'ayah_count' => 12
    ],
    [
        'id' => 67,
        'name_ar' => 'الملك',
        'name_en' => 'Al-Mulk',
        'name_ur' => 'الملك',
        'name_bn' => 'সূরা আল-মুলক',
        'revelation_place' => 'Mecca',
        'ayah_count' => 30
    ],
    [
        'id' => 68,
        'name_ar' => 'القلم',
        'name_en' => 'Al-Qalam',
        'name_ur' => 'القلم',
        'name_bn' => 'সূরা আল-কলম',
        'revelation_place' => 'Mecca',
        'ayah_count' => 52
    ],
    [
        'id' => 69,
        'name_ar' => 'الحاقة',
        'name_en' => 'Al-Haaqqa',
        'name_ur' => 'الحاقة',
        'name_bn' => 'সূরা আল-হাক্কাহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 52
    ],
    [
        'id' => 70,
        'name_ar' => 'المعارج',
        'name_en' => 'Al-Ma\'arij',
        'name_ur' => 'المعارج',
        'name_bn' => 'সূরা আল-মাআরিজ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 44
    ],
    [
        'id' => 71,
        'name_ar' => 'نوح',
        'name_en' => 'Nuh',
        'name_ur' => 'نوح',
        'name_bn' => 'সূরা নূহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 28
    ],
    [
        'id' => 72,
        'name_ar' => 'الجن',
        'name_en' => 'Al-Jinn',
        'name_ur' => 'الجن',
        'name_bn' => 'সূরা আল-জ্বিন',
        'revelation_place' => 'Mecca',
        'ayah_count' => 28
    ],
    [
        'id' => 73,
        'name_ar' => 'المزمل',
        'name_en' => 'Al-Muzzammil',
        'name_ur' => 'المزمل',
        'name_bn' => 'সূরা আল-মুযযাম্মিল',
        'revelation_place' => 'Mecca',
        'ayah_count' => 20
    ],
    [
        'id' => 74,
        'name_ar' => 'المدثر',
        'name_en' => 'Al-Muddathir',
        'name_ur' => 'المدثر',
        'name_bn' => 'সূরা আল-মুদ্দাসসির',
        'revelation_place' => 'Mecca',
        'ayah_count' => 56
    ],
    [
        'id' => 75,
        'name_ar' => 'القيامة',
        'name_en' => 'Al-Qiyama',
        'name_ur' => 'القیامة',
        'name_bn' => 'সূরা আল-ক্বিয়ামাহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 40
    ],
    [
        'id' => 76,
        'name_ar' => 'الانسان',
        'name_en' => 'Al-Insan',
        'name_ur' => 'الانسان',
        'name_bn' => 'সূরা আল-ইনসান',
        'revelation_place' => 'Medina', // Note: Some sources say Mecca, but predominantly Medina
        'ayah_count' => 31
    ],
    [
        'id' => 77,
        'name_ar' => 'المرسلات',
        'name_en' => 'Al-Mursalat',
        'name_ur' => 'المرسلات',
        'name_bn' => 'সূরা আল-মুরসালাত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 50
    ],
    [
        'id' => 78,
        'name_ar' => 'النبأ',
        'name_en' => 'An-Naba',
        'name_ur' => 'النبإ',
        'name_bn' => 'সূরা আন-নাবা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 40
    ],
    [
        'id' => 79,
        'name_ar' => 'النازعات',
        'name_en' => 'An-Nazi\'at',
        'name_ur' => 'النازعات',
        'name_bn' => 'সূরা আন-নাযিআত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 46
    ],
    [
        'id' => 80,
        'name_ar' => 'عبس',
        'name_en' => '\'Abasa',
        'name_ur' => 'عبس',
        'name_bn' => 'সূরা আবাসা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 42
    ],
    [
        'id' => 81,
        'name_ar' => 'التكوير',
        'name_en' => 'At-Takwir',
        'name_ur' => 'التکویر',
        'name_bn' => 'সূরা আত-তাকভীর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 29
    ],
    [
        'id' => 82,
        'name_ar' => 'الإنفطار',
        'name_en' => 'Al-Infitar',
        'name_ur' => 'الإنفطار',
        'name_bn' => 'সূরা আল-ইনফিতার',
        'revelation_place' => 'Mecca',
        'ayah_count' => 19
    ],
    [
        'id' => 83,
        'name_ar' => 'المطففين',
        'name_en' => 'Al-Mutaffifin',
        'name_ur' => 'المطففین',
        'name_bn' => 'সূরা আল-মুতাফ্‌‌ফিفīn',
        'revelation_place' => 'Mecca',
        'ayah_count' => 36
    ],
    [
        'id' => 84,
        'name_ar' => 'الإنشقاق',
        'name_en' => 'Al-Inshiqaq',
        'name_ur' => 'الإنشقاق',
        'name_bn' => 'সূরা আল-ইনশিকাক',
        'revelation_place' => 'Mecca',
        'ayah_count' => 25
    ],
    [
        'id' => 85,
        'name_ar' => 'البروج',
        'name_en' => 'Al-Buruj',
        'name_ur' => 'البروج',
        'name_bn' => 'সূরা আল-বুরূজ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 22
    ],
    [
        'id' => 86,
        'name_ar' => 'الطارق',
        'name_en' => 'At-Tariq',
        'name_ur' => 'الطارق',
        'name_bn' => 'সূরা আত-তারিক',
        'revelation_place' => 'Mecca',
        'ayah_count' => 17
    ],
    [
        'id' => 87,
        'name_ar' => 'الأعلى',
        'name_en' => 'Al-A\'la',
        'name_ur' => 'الأعلى',
        'name_bn' => 'সূরা আল-আ\'লা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 19
    ],
    [
        'id' => 88,
        'name_ar' => 'الغاشية',
        'name_en' => 'Al-Ghashiya',
        'name_ur' => 'الغاشیة',
        'name_bn' => 'সূরা আল-গাশিয়াহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 26
    ],
    [
        'id' => 89,
        'name_ar' => 'الفجر',
        'name_en' => 'Al-Fajr',
        'name_ur' => 'الفجر',
        'name_bn' => 'সূরা আল-ফাজর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 30
    ],
    [
        'id' => 90,
        'name_ar' => 'البلد',
        'name_en' => 'Al-Balad',
        'name_ur' => 'البلد',
        'name_bn' => 'সূরা আল-বালাদ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 20
    ],
    [
        'id' => 91,
        'name_ar' => 'الشمس',
        'name_en' => 'Ash-Shams',
        'name_ur' => 'الشمس',
        'name_bn' => 'সূরা আশ-شams',
        'revelation_place' => 'Mecca',
        'ayah_count' => 15
    ],
    [
        'id' => 92,
        'name_ar' => 'الليل',
        'name_en' => 'Al-Layl',
        'name_ur' => 'اللیل',
        'name_bn' => 'সূরা আল-লায়ল',
        'revelation_place' => 'Mecca',
        'ayah_count' => 21
    ],
    [
        'id' => 93,
        'name_ar' => 'الضحى',
        'name_en' => 'Ad-Duha',
        'name_ur' => 'الضحٰی',
        'name_bn' => 'সূরা আদ-দুহা',
        'revelation_place' => 'Mecca',
        'ayah_count' => 11
    ],
    [
        'id' => 94,
        'name_ar' => 'الشرح', // or Al-Inshirah
        'name_en' => 'Ash-Sharh',
        'name_ur' => 'الشرح',
        'name_bn' => 'সূরা আল-ইনশিরাহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 8
    ],
    [
        'id' => 95,
        'name_ar' => 'التين',
        'name_en' => 'At-Tin',
        'name_ur' => 'التین',
        'name_bn' => 'সূরা আত-তীন',
        'revelation_place' => 'Mecca',
        'ayah_count' => 8
    ],
    [
        'id' => 96,
        'name_ar' => 'العلق',
        'name_en' => 'Al-\'Alaq',
        'name_ur' => 'العلق',
        'name_bn' => 'সূরা আল-আলাক',
        'revelation_place' => 'Mecca',
        'ayah_count' => 19
    ],
    [
        'id' => 97,
        'name_ar' => 'القدر',
        'name_en' => 'Al-Qadr',
        'name_ur' => 'القدر',
        'name_bn' => 'সূরা আল-কদর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 5
    ],
    [
        'id' => 98,
        'name_ar' => 'البينة',
        'name_en' => 'Al-Bayyina',
        'name_ur' => 'البیّنة',
        'name_bn' => 'সূরা আল-বাইয়্যিনاه',
        'revelation_place' => 'Medina',
        'ayah_count' => 8
    ],
    [
        'id' => 99,
        'name_ar' => 'الزلزلة',
        'name_en' => 'Az-Zalzala',
        'name_ur' => 'الزلزلة',
        'name_bn' => 'সূরা আয-যালযালাহ',
        'revelation_place' => 'Medina', // Note: Some sources say Mecca, but predominantly Medina
        'ayah_count' => 8
    ],
    [
        'id' => 100,
        'name_ar' => 'العاديات',
        'name_en' => 'Al-\'Adiyat',
        'name_ur' => 'العادیات',
        'name_bn' => 'সূরা আল-আদিয়াত',
        'revelation_place' => 'Mecca',
        'ayah_count' => 11
    ],
    [
        'id' => 101,
        'name_ar' => 'القارعة',
        'name_en' => 'Al-Qari\'a',
        'name_ur' => 'القارعة',
        'name_bn' => 'সূরা আল-ক্বারিআহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 11
    ],
    [
        'id' => 102,
        'name_ar' => 'التكاثر',
        'name_en' => 'At-Takathur',
        'name_ur' => 'التکاثر',
        'name_bn' => 'সূরা আত-তাকাছুর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 8
    ],
    [
        'id' => 103,
        'name_ar' => 'العصر',
        'name_en' => 'Al-\'Asr',
        'name_ur' => 'العصر',
        'name_bn' => 'সূরা আল-আস্বর',
        'revelation_place' => 'Mecca',
        'ayah_count' => 3
    ],
    [
        'id' => 104,
        'name_ar' => 'الهمزة',
        'name_en' => 'Al-Humaza',
        'name_ur' => 'الهمزة',
        'name_bn' => 'সূরা আল-হুমাযাহ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 9
    ],
    [
        'id' => 105,
        'name_ar' => 'الفيل',
        'name_en' => 'Al-Fil',
        'name_ur' => 'الفیل',
        'name_bn' => 'সূরা আল-ফীল',
        'revelation_place' => 'Mecca',
        'ayah_count' => 5
    ],
    [
        'id' => 106,
        'name_ar' => 'قريش',
        'name_en' => 'Quraysh',
        'name_ur' => 'قریش',
        'name_bn' => 'সূরা কুরাইশ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 4
    ],
    [
        'id' => 107,
        'name_ar' => 'الماعون',
        'name_en' => 'Al-Ma\'un',
        'name_ur' => 'الماعون',
        'name_bn' => 'সূরা আল-মাউন',
        'revelation_place' => 'Mecca',
        'ayah_count' => 7
    ],
    [
        'id' => 108,
        'name_ar' => 'الكوثر',
        'name_en' => 'Al-Kawthar',
        'name_ur' => 'الکوثر',
        'name_bn' => 'সূরা আল-কাউসার',
        'revelation_place' => 'Mecca',
        'ayah_count' => 3
    ],
    [
        'id' => 109,
        'name_ar' => 'الكافرون',
        'name_en' => 'Al-Kafirun',
        'name_ur' => 'الکافرون',
        'name_bn' => 'সূরা আল-কাফিরুন',
        'revelation_place' => 'Mecca',
        'ayah_count' => 6
    ],
    [
        'id' => 110,
        'name_ar' => 'النصر',
        'name_en' => 'An-Nasr',
        'name_ur' => 'النصر',
        'name_bn' => 'সূরা আন-নাসর',
        'revelation_place' => 'Medina',
        'ayah_count' => 3
    ],
    [
        'id' => 111,
        'name_ar' => 'المسد', // or Al-Lahab
        'name_en' => 'Al-Masad',
        'name_ur' => 'المسد',
        'name_bn' => 'সূরা আল-মাসাদ',
        'revelation_place' => 'Mecca',
        'ayah_count' => 5
    ],
    [
        'id' => 112,
        'name_ar' => 'الإخلاص',
        'name_en' => 'Al-Ikhlas',
        'name_ur' => 'الإخلاص',
        'name_bn' => 'সূরা আল-ইখলাস',
        'revelation_place' => 'Mecca',
        'ayah_count' => 4
    ],
    [
        'id' => 113,
        'name_ar' => 'الفلق',
        'name_en' => 'Al-Falaq',
        'name_ur' => 'الفلق',
        'name_bn' => 'সূরা আল-ফালাক',
        'revelation_place' => 'Mecca',
        'ayah_count' => 5
    ],
    [
        'id' => 114,
        'name_ar' => 'الناس',
        'name_en' => 'An-Nas',
        'name_ur' => 'الناس',
        'name_bn' => 'সূরা আন-নাস',
        'revelation_place' => 'Mecca',
        'ayah_count' => 6
    ]
];


/**
 * Populates the 'surahs' table with the complete list of 114 surahs
 * only if the table exists and currently has less than 110 rows.
 * This function assumes DB_NAME is defined and a getDB() function exists
 * elsewhere in the script to provide an SQLite3 connection.
 */
function populateSurahsTableIfIncomplete() {
    global $all_surahs_data; // Access the global surah data array

    // Check if the database file exists before attempting to connect
    if (!file_exists(DB_NAME)) {
        // Database file does not exist, population is not possible yet.
        // This means initDB() hasn't run or failed.
        // It's likely initDB() already contains surah population logic for first creation.
        error_log("populateSurahsTableIfIncomplete: Database file " . DB_NAME . " not found. Skipping population.");
        return;
    }

    $db = null;
    try {
        $db = getDB(); // Use your existing getDB function

        // Check if the surahs table exists
        $tableCheck = $db->querySingle("SELECT name FROM sqlite_master WHERE type='table' AND name='surahs'");
        if (!$tableCheck) {
             error_log("populateSurahsTableIfIncomplete: 'surahs' table does not exist. Skipping population.");
             // The table doesn't exist, can't insert data.
             $db->close();
             return;
        }

        // Check the row count in the surahs table
        $count = $db->querySingle("SELECT COUNT(*) FROM surahs");

        if ($count < 110) {
             error_log("populateSurahsTableIfIncomplete: 'surahs' table has " . $count . " rows (< 110). Attempting to populate...");

             $db->exec('BEGIN TRANSACTION;'); // Start transaction

             // Use INSERT OR IGNORE to add only rows that don't already exist based on 'id'
             $stmt = $db->prepare("INSERT OR IGNORE INTO surahs (id, name_ar, name_en, name_ur, name_bn, revelation_place, ayah_count)
                                 VALUES (:id, :name_ar, :name_en, :name_ur, :name_bn, :revelation_place, :ayah_count)");

             foreach ($all_surahs_data as $surah) {
                 $stmt->bindValue(':id', $surah['id'], SQLITE3_INTEGER);
                 $stmt->bindValue(':name_ar', $surah['name_ar'], SQLITE3_TEXT);
                 $stmt->bindValue(':name_en', $surah['name_en'], SQLITE3_TEXT);
                 $stmt->bindValue(':name_ur', $surah['name_ur'], SQLITE3_TEXT);
                 $stmt->bindValue(':name_bn', $surah['name_bn'], SQLITE3_TEXT);
                 $stmt->bindValue(':revelation_place', $surah['revelation_place'], SQLITE3_TEXT);
                 $stmt->bindValue(':ayah_count', $surah['ayah_count'], SQLITE3_INTEGER);

                 if (!$stmt->execute()) {
                      // Log individual insert errors but continue the loop
                      error_log("populateSurahsTableIfIncomplete: Failed to insert surah " . $surah['id'] . ": " . $db->lastErrorMsg());
                 }
             }

             $db->exec('COMMIT;'); // Commit transaction
             $stmt->finalize();

             // Re-check count after population attempt
             $final_count = $db->querySingle("SELECT COUNT(*) FROM surahs");
             error_log("populateSurahsTableIfIncomplete: Population attempt finished. Final surahs count: " . $final_count);

             // Optional: Display a message if population was attempted
             if ($final_count > $count) {
                 // Assuming you have a displayMessage function elsewhere
                 // displayMessage('success', 'Surah data updated. Total surahs: ' . $final_count);
             } else {
                 // displayMessage('info', 'Surah data check complete. No missing surahs found.');
             }


        } else {
             error_log("populateSurahsTableIfIncomplete: 'surahs' table already has enough data (" . $count . " rows). Skipping population.");
        }

    } catch (Exception $e) {
        error_log("populateSurahsTableIfIncomplete: Database error - " . $e->getMessage());
        // Attempt rollback in case an error occurred after transaction started
        if ($db && $db->inTransaction()) {
             $db->exec('ROLLBACK;');
        }
         // Optional: Display error message
         // displayMessage('danger', 'A database error occurred during surah data population.');
    } finally {
        if ($db) {
            $db->close(); // Always close the connection
        }
    }
}

// --- Call the function ---
// Add this line at the very end of your single PHP file,
// after handleRequest() and any other top-level calls.
populateSurahsTableIfIncomplete();

?> -->