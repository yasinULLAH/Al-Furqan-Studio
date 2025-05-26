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
            header { background-color: #2c3e50; color: white; padding: 15px 0; margin-bottom: 20px; }
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
            .word-meaning { cursor: pointer; position: relative; }
            .word-meaning:hover { background-color: #f0f0f0; }
            .word-tooltip { display: none; position: absolute; background: white; border: 1px solid #ddd; padding: 5px; z-index: 100; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
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
        if (hasRole('ulama') {
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) {
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
                    <input type="range" id="progress" name="progress" min="0" max="100" value="0" oninput="document.getElementById('progressValue').innerText = this.value + '%'">
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) {
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