<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

$db = new SQLite3('quran_study_hub9.db');

$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE,
    password TEXT,
    email TEXT,
    role TEXT DEFAULT "user",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS word_dictionary (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word_id INTEGER UNIQUE,
    arabic TEXT,
    urdu_meaning TEXT,
    english_meaning TEXT,
    diacritics TEXT
)');

$db->exec('CREATE TABLE IF NOT EXISTS ayah_word_mapping (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    word_position INTEGER,
    FOREIGN KEY(word_id) REFERENCES word_dictionary(word_id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS contributions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    word_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    contribution_type TEXT,
    content TEXT,
    status TEXT DEFAULT "pending",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_by INTEGER,
    FOREIGN KEY(user_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS user_progress (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    status TEXT,
    notes TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS user_highlights (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    word_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    word_position INTEGER,
    highlight_color TEXT,
    personal_note TEXT,
    FOREIGN KEY(user_id) REFERENCES users(id)
)');

if (!isset($_SESSION['user_id'])) {
    $stmt = $db->prepare('SELECT * FROM users WHERE role = "admin"');
    $result = $stmt->execute();
    if (!$result->fetchArray()) {
        $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
        $db->exec("INSERT INTO users (username, password, email, role) VALUES ('admin', '$admin_pass', 'admin@quranstudy.com', 'admin')");
    }
}

// ... (keep existing code above this function)

function auth_check($required_roles = null) {
    // 1. Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        return false; // Not logged in, access denied
    }

    // 2. If no specific role is required, logged-in user is sufficient
    if ($required_roles === null) {
        return true; // Logged in, no role restriction, access granted
    }

    $user_role = $_SESSION['role'];

    // 3. Admin role has universal access if a role IS required
    if ($user_role === 'admin') {
        return true; // User is admin, access granted
    }

    // 4. If required_roles is a single string (and user is not admin)
    if (is_string($required_roles)) {
        return $user_role === $required_roles; // Access granted if user role matches
    }

    // 5. If required_roles is an array (and user is not admin)
    if (is_array($required_roles)) {
        return in_array($user_role, $required_roles, true); // Access granted if user role is in the array
    }

    // Default fallback (e.g., $required_roles is an unexpected type)
    return false; // Access denied
}

// ... (keep existing code below this function, like get_word_meaning, etc.)


function get_word_meaning($word_id) {
    global $db;
    $stmt = $db->prepare('SELECT * FROM word_dictionary WHERE word_id = ?');
    $stmt->bindValue(1, $word_id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    return $result->fetchArray(SQLITE3_ASSOC);
}

function get_surah_ayahs($surah) {
    global $db;
    $stmt = $db->prepare('SELECT DISTINCT ayah FROM ayah_word_mapping WHERE surah = ? ORDER BY ayah');
    $stmt->bindValue(1, $surah, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $ayahs = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row['ayah'];
    }
    return $ayahs;
}

function build_ayah_text($surah, $ayah) {
    global $db;
    $stmt = $db->prepare('SELECT awm.word_id, awm.word_position, wd.arabic, awm.surah, awm.ayah 
                         FROM ayah_word_mapping awm 
                         JOIN word_dictionary wd ON awm.word_id = wd.word_id 
                         WHERE awm.surah = ? AND awm.ayah = ? 
                         ORDER BY awm.word_position');
    $stmt->bindValue(1, $surah, SQLITE3_INTEGER);
    $stmt->bindValue(2, $ayah, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $text = '';
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $highlight_class = '';
        if (isset($_SESSION['user_id'])) {
            $h_stmt = $db->prepare('SELECT highlight_color FROM user_highlights WHERE user_id = ? AND word_id = ? AND surah = ? AND ayah = ?');
            $h_stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
            $h_stmt->bindValue(2, $row['word_id'], SQLITE3_INTEGER);
            $h_stmt->bindValue(3, $surah, SQLITE3_INTEGER);
            $h_stmt->bindValue(4, $ayah, SQLITE3_INTEGER);
            $h_result = $h_stmt->execute();
            $highlight = $h_result->fetchArray(SQLITE3_ASSOC);
            if ($highlight) {
                $highlight_class = 'highlighted';
                $text .= "<span class='quran-word {$highlight_class}' data-word-id='{$row['word_id']}' data-surah='{$row['surah']}' data-ayah='{$row['ayah']}' data-pos='{$row['word_position']}' style='background-color: {$highlight['highlight_color']}'>{$row['arabic']}</span> ";
            } else {
                $text .= "<span class='quran-word' data-word-id='{$row['word_id']}' data-surah='{$row['surah']}' data-ayah='{$row['ayah']}' data-pos='{$row['word_position']}'>{$row['arabic']}</span> ";
            }
        } else {
            $text .= "<span class='quran-word' data-word-id='{$row['word_id']}' data-surah='{$row['surah']}' data-ayah='{$row['ayah']}' data-pos='{$row['word_position']}'>{$row['arabic']}</span> ";
        }
    }
    return trim($text);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'login') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->bindValue(1, $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
    
    if ($action === 'register') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $email = $_POST['email'] ?? '';
        
        if ($username && $password && $email) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO users (username, password, email) VALUES (?, ?, ?)');
            $stmt->bindValue(1, $username, SQLITE3_TEXT);
            $stmt->bindValue(2, $hashed, SQLITE3_TEXT);
            $stmt->bindValue(3, $email, SQLITE3_TEXT);
            if ($stmt->execute()) {
                $success = 'Account created successfully';
            } else {
                $error = 'Username already exists';
            }
        }
    }
    
    if ($action === 'import_data' && auth_check('admin')) {
        if (isset($_FILES['dict_file']) && $_FILES['mapping_file']) {
            $dict_content = file_get_contents($_FILES['dict_file']['tmp_name']);
            $mapping_content = file_get_contents($_FILES['mapping_file']['tmp_name']);
            
            $dict_lines = explode("\n", $dict_content);
            $mapping_lines = explode("\n", $mapping_content);
            
            $db->exec('DELETE FROM word_dictionary');
            $db->exec('DELETE FROM ayah_word_mapping');
            
            foreach ($dict_lines as $line) {
                $parts = str_getcsv($line);
                if (count($parts) >= 4) {
                    $stmt = $db->prepare('INSERT INTO word_dictionary (word_id, arabic, urdu_meaning, english_meaning, diacritics) VALUES (?, ?, ?, ?, ?)');
                    $stmt->bindValue(1, $parts[0], SQLITE3_INTEGER);
                    $stmt->bindValue(2, $parts[1], SQLITE3_TEXT);
                    $stmt->bindValue(3, $parts[2], SQLITE3_TEXT);
                    $stmt->bindValue(4, $parts[3], SQLITE3_TEXT);
                    $stmt->bindValue(5, $parts[4] ?? '', SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
            
            foreach ($mapping_lines as $line) {
                $parts = str_getcsv($line);
                if (count($parts) >= 4) {
                    $stmt = $db->prepare('INSERT INTO ayah_word_mapping (word_id, surah, ayah, word_position) VALUES (?, ?, ?, ?)');
                    $stmt->bindValue(1, $parts[0], SQLITE3_INTEGER);
                    $stmt->bindValue(2, $parts[1], SQLITE3_INTEGER);
                    $stmt->bindValue(3, $parts[2], SQLITE3_INTEGER);
                    $stmt->bindValue(4, $parts[3], SQLITE3_INTEGER);
                    $stmt->execute();
                }
            }
            
            $success = 'Data imported successfully';
        }
    }
    
    if ($action === 'add_contribution' && auth_check()) {
        $word_id = $_POST['word_id'] ?? 0;
        $content = $_POST['content'] ?? '';
        $type = $_POST['type'] ?? '';
        $surah = $_POST['surah'] ?? 0;
        $ayah = $_POST['ayah'] ?? 0;
        
        $stmt = $db->prepare('INSERT INTO contributions (user_id, word_id, surah, ayah, contribution_type, content) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $word_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $surah, SQLITE3_INTEGER);
        $stmt->bindValue(4, $ayah, SQLITE3_INTEGER);
        $stmt->bindValue(5, $type, SQLITE3_TEXT);
        $stmt->bindValue(6, $content, SQLITE3_TEXT);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'highlight_word' && auth_check()) {
        $word_id = $_POST['word_id'] ?? 0;
        $surah = $_POST['surah'] ?? 0;
        $ayah = $_POST['ayah'] ?? 0;
        $position = $_POST['position'] ?? 0;
        $color = $_POST['color'] ?? '#ffff00';
        $note = $_POST['note'] ?? '';
        
        $stmt = $db->prepare('INSERT OR REPLACE INTO user_highlights (user_id, word_id, surah, ayah, word_position, highlight_color, personal_note) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
        $stmt->bindValue(2, $word_id, SQLITE3_INTEGER);
        $stmt->bindValue(3, $surah, SQLITE3_INTEGER);
        $stmt->bindValue(4, $ayah, SQLITE3_INTEGER);
        $stmt->bindValue(5, $position, SQLITE3_INTEGER);
        $stmt->bindValue(6, $color, SQLITE3_TEXT);
        $stmt->bindValue(7, $note, SQLITE3_TEXT);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'get_word_meaning') {
        $word_id = $_GET['word_id'] ?? 0;
        $meaning = get_word_meaning($word_id);
        
        $contributions_stmt = $db->prepare('SELECT c.*, u.username FROM contributions c JOIN users u ON c.user_id = u.id WHERE c.word_id = ? AND c.status = "approved" ORDER BY c.created_at DESC');
        $contributions_stmt->bindValue(1, $word_id, SQLITE3_INTEGER);
        $contributions_result = $contributions_stmt->execute();
        $contributions = [];
        while ($contrib = $contributions_result->fetchArray(SQLITE3_ASSOC)) {
            $contributions[] = $contrib;
        }
        
        echo json_encode([
            'meaning' => $meaning,
            'contributions' => $contributions
        ]);
        exit;
    }
    
    if ($action === 'search') {
        $query = $_GET['query'] ?? '';
        $stmt = $db->prepare('SELECT DISTINCT awm.surah, awm.ayah, wd.arabic FROM word_dictionary wd JOIN ayah_word_mapping awm ON wd.word_id = awm.word_id WHERE wd.arabic LIKE ? OR wd.urdu_meaning LIKE ? OR wd.english_meaning LIKE ? LIMIT 20');
        $search_term = "%$query%";
        $stmt->bindValue(1, $search_term, SQLITE3_TEXT);
        $stmt->bindValue(2, $search_term, SQLITE3_TEXT);
        $stmt->bindValue(3, $search_term, SQLITE3_TEXT);
        $result = $stmt->execute();
        
        $results = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $results[] = $row;
        }
        
        echo json_encode($results);
        exit;
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$current_surah = $_GET['surah'] ?? 1;
$current_ayah = $_GET['ayah'] ?? 1;
$page = $_GET['page'] ?? 'reader';

$surah_names = [
    1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Ali-Imran', 4 => 'An-Nisa', 5 => 'Al-Maidah',
    6 => 'Al-Anam', 7 => 'Al-Araf', 8 => 'Al-Anfal', 9 => 'At-Tawbah', 10 => 'Yunus',
    11 => 'Hud', 12 => 'Yusuf', 13 => 'Ar-Rad', 14 => 'Ibrahim', 15 => 'Al-Hijr',
    16 => 'An-Nahl', 17 => 'Al-Isra', 18 => 'Al-Kahf', 19 => 'Maryam', 20 => 'Ta-Ha',
    21 => 'Al-Anbiya', 22 => 'Al-Hajj', 23 => 'Al-Muminun', 24 => 'An-Nur', 25 => 'Al-Furqan',
    26 => 'Ash-Shuara', 27 => 'An-Naml', 28 => 'Al-Qasas', 29 => 'Al-Ankabut', 30 => 'Ar-Rum',
    31 => 'Luqman', 32 => 'As-Sajda', 33 => 'Al-Ahzab', 34 => 'Saba', 35 => 'Fatir',
    36 => 'Ya-Sin', 37 => 'As-Saffat', 38 => 'Sad', 39 => 'Az-Zumar', 40 => 'Ghafir',
    41 => 'Fussilat', 42 => 'Ash-Shura', 43 => 'Az-Zukhruf', 44 => 'Ad-Dukhan', 45 => 'Al-Jathiya',
    46 => 'Al-Ahqaf', 47 => 'Muhammad', 48 => 'Al-Fath', 49 => 'Al-Hujurat', 50 => 'Qaf',
    51 => 'Adh-Dhariyat', 52 => 'At-Tur', 53 => 'An-Najm', 54 => 'Al-Qamar', 55 => 'Ar-Rahman',
    56 => 'Al-Waqi’a', 57 => 'Al-Hadid', 58 => 'Al-Mujadila', 59 => 'Al-Hashr', 60 => 'Al-Mumtahina',
    61 => 'As-Saff', 62 => 'Al-Jumu’a', 63 => 'Al-Munafiqun', 64 => 'At-Taghabun', 65 => 'At-Talaq',
    66 => 'At-Tahrim', 67 => 'Al-Mulk', 68 => 'Al-Qalam', 69 => 'Al-Haqqah', 70 => 'Al-Maarij',
    71 => 'Nuh', 72 => 'Al-Jinn', 73 => 'Al-Muzzammil', 74 => 'Al-Qiyama', 75 => 'Al-Insan',
    76 => 'Al-Mursalat', 77 => 'An-Naba', 78 => 'An-Nazi’at', 79 => 'Abasa', 80 => 'At-Takwir',
    81 => 'Al-Infitar', 82 => 'Al-Mutaffifin', 83 => 'Al-Inshiqaq', 84 => 'Al-Burooj', 85 => 'At-Tariq',
    86 => 'Al-Alaq', 87 => 'Al-Qadr', 88 => 'Al-Bayyina', 89 => 'Az-Zalzalah', 90 => 'Al-Adiyat',
    91 => 'Al-Qaria', 92 => 'At-Takathur', 93 => 'Al-Asr', 94 => 'Al-Inshirah', 95 => 'At-Tin',
    96 => 'Al-Alaq', 97 => 'Al-Qadr', 98 => 'Al-Bayyina', 99 => 'Az-Zalzalah', 100 => 'Al-Adiyat',
    101 => 'Al-Qaria', 102 => 'At-Takathur', 103 => 'Al-Asr', 104 => 'Al-Humazah', 105 => 'Al-Fil',
    106 => 'Quraish', 107 => 'Al-Ma’un', 108 => 'Al-Kawthar', 109 => 'Al-Kafirun', 110 => 'An-Nasr',
    111 => 'Al-Masad', 112 => 'Al-Ikhlas', 113 => 'Al-Falaq', 114 => 'An-Nas'
];

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study Hub - Author: Yasin Ullah, Pakistani</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Amiri', 'Times New Roman', serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            direction: rtl;
        }
        
        .navbar {
            background: linear-gradient(90deg, #2c3e50, #3498db);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #ecf0f1;
        }
        
        .nav-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .auth-container {
            max-width: 400px;
            margin: 5rem auto;
            padding: 2rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ecf0f1;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }
        
        .btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .reader-container {
            padding: 2rem;
        }
        
        .surah-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .ayah-container {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            line-height: 2.5;
            font-size: 1.8rem;
            text-align: right;
            direction: rtl;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .quran-word {
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 3px;
            transition: all 0.2s;
            position: relative;
        }
        
        .quran-word:hover {
            background: #e3f2fd;
            transform: scale(1.05);
        }
        
        .highlighted {
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
        }
        
        .word-popup {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            max-width: 300px;
            min-width: 200px;
            display: none;
        }
        
        .word-popup h4 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .word-popup p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        
        .word-popup .actions {
            margin-top: 1rem;
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .word-popup .btn {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }
        
        .search-container {
            margin-bottom: 2rem;
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            font-size: 1.1rem;
            padding-left: 3rem;
        }
        
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }
        
        .search-result-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .search-result-item:hover {
            background: #f8f9fa;
        }
        
        .admin-panel {
            padding: 2rem;
        }
        
        .admin-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .admin-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .file-upload {
            border: 2px dashed #3498db;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            margin: 1rem 0;
            transition: background 0.3s;
        }
        
        .file-upload:hover {
            background: #f8f9fa;
        }
        
        .contributions-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .contribution-item {
            background: white;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #3498db;
        }
        
        .contribution-item.pending {
            border-left-color: #f39c12;
        }
        
        .contribution-item.approved {
            border-left-color: #27ae60;
        }
        
        .contribution-item.rejected {
            border-left-color: #e74c3c;
        }
        
        .error {
            background: #e74c3c;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .success {
            background: #27ae60;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 80%;
            max-width: 500px;
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #000;
        }
        
        .color-picker {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
        }
        
        .color-option {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #ddd;
            transition: transform 0.2s;
        }
        
        .color-option:hover {
            transform: scale(1.1);
        }
        
        .color-option.selected {
            border-color: #2c3e50;
            transform: scale(1.1);
        }
        
        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 2rem 0;
        }
        
        .ayah-number {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 1rem;
        }
        
        @media (max-width: 768px) {
            .navbar-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .main-container {
                margin: 1rem;
                padding: 0 1rem;
            }
            
            .ayah-container {
                font-size: 1.4rem;
                padding: 1rem;
            }
            
            .surah-selector {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        .footer {
            text-align: center;
            padding: 2rem;
            background: #2c3e50;
            color: white;
            margin-top: 3rem;
        }
        *, select, textarea, input {
            font-family: calibri !important;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-content">
            <div class="logo">🕌 Quran Study Hub</div>
            <div class="nav-menu">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="?page=reader" class="nav-link">📖 Reader</a>
                    <a href="?page=search" class="nav-link">🔍 Search</a>
                    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'ulama'): ?>
                        <a href="?page=admin" class="nav-link">⚙️ Admin</a>
                    <?php endif; ?>
                    <span class="nav-link">👤 <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)</span>
                    <a href="?logout=1" class="nav-link">🚪 Logout</a>
                <?php else: ?>
                    <a href="?page=login" class="nav-link">🔑 Login</a>
                    <a href="?page=register" class="nav-link">📝 Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <?php if (!isset($_SESSION['user_id']) && ($page === 'login' || $page === 'register')): ?>
        <div class="auth-container">
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($page === 'login'): ?>
                <h2 style="text-align: center; margin-bottom: 2rem; color: #2c3e50;">🔑 Login</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Login</button>
                </form>
                <p style="text-align: center; margin-top: 1rem;">
                    Don't have an account? <a href="?page=register">Register here</a>
                </p>
            <?php else: ?>
                <h2 style="text-align: center; margin-bottom: 2rem; color: #2c3e50;">📝 Register</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label>Username:</label>
                        <input type="text" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>Password:</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" class="btn" style="width: 100%;">Register</button>
                </form>
                <p style="text-align: center; margin-top: 1rem;">
                    Already have an account? <a href="?page=login">Login here</a>
                </p>
            <?php endif; ?>
        </div>
    <?php elseif (!isset($_SESSION['user_id'])): ?>
        <div class="main-container">
            <div style="text-align: center; padding: 3rem;">
                <h1 style="color: #2c3e50; margin-bottom: 1rem;">Welcome to Quran Study Hub</h1>
                <p style="font-size: 1.2rem; color: #7f8c8d; margin-bottom: 2rem;">A comprehensive platform for studying the Holy Quran</p>
                <div>
                    <a href="?page=login" class="btn" style="margin: 0.5rem;">🔑 Login</a>
                    <a href="?page=register" class="btn btn-secondary" style="margin: 0.5rem;">📝 Register</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="main-container">
            <?php if (isset($success)): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($page === 'reader'): ?>
                <div class="reader-container">
                    <div class="search-container">
                        <input type="text" id="searchInput" class="search-input" placeholder="Search in Quran...">
                        <div id="searchResults" class="search-results"></div>
                    </div>
                    
                    <div class="surah-selector">
                        <select id="surahSelect" class="form-group input" onchange="navigateToSurah()">
                            <?php for ($i = 1; $i <= 114; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i == $current_surah ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>. <?php echo $surah_names[$i] ?? "Surah $i"; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        
                        <select id="ayahSelect" class="form-group input" onchange="navigateToAyah()">
                            <?php 
                            $ayahs = get_surah_ayahs($current_surah);
                            foreach ($ayahs as $ayah): ?>
                                <option value="<?php echo $ayah; ?>" <?php echo $ayah == $current_ayah ? 'selected' : ''; ?>>
                                    Ayah <?php echo $ayah; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="navigation">
                        <?php if ($current_ayah > 1 || $current_surah > 1): ?>
                            <a href="?page=reader&surah=<?php echo $current_surah; ?>&ayah=<?php echo $current_ayah - 1; ?>" class="btn btn-secondary">← Previous</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                        
                        <div class="ayah-number"><?php echo $current_ayah; ?></div>
                        
                        <?php 
                        $next_ayah = $current_ayah + 1;
                        $next_surah = $current_surah;
                        $ayahs = get_surah_ayahs($current_surah);
                        if (!in_array($next_ayah, $ayahs)) {
                            $next_ayah = 1;
                            $next_surah++;
                        }
                        if ($next_surah <= 30): ?>
                            <a href="?page=reader&surah=<?php echo $next_surah; ?>&ayah=<?php echo $next_ayah; ?>" class="btn btn-secondary">Next →</a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ayah-container">
                        <h3 style="margin-bottom: 1rem; color: #2c3e50;">
                            <?php echo $surah_names[$current_surah] ?? "Surah $current_surah"; ?> - Ayah <?php echo $current_ayah; ?>
                        </h3>
                        <div id="ayahText">
                            <?php echo build_ayah_text($current_surah, $current_ayah); ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($page === 'search'): ?>
                <div class="reader-container">
                    <h2 style="color: #2c3e50; margin-bottom: 2rem;">🔍 Advanced Search</h2>
                    <div class="search-container">
                        <input type="text" id="advancedSearch" class="search-input" placeholder="Search words, meanings, or tafsir...">
                        <div id="advancedSearchResults" class="search-results"></div>
                    </div>
                </div>
            <?php elseif ($page === 'admin' && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'ulama')): ?>
                <div class="admin-panel">
                    <h2 style="color: #2c3e50; margin-bottom: 2rem;">⚙️ Admin Panel</h2>
                    
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <div class="admin-section">
                            <h3>📊 Data Import</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="import_data">
                                <div class="form-group">
                                    <label>Word Dictionary (data5.AM):</label>
                                    <input type="file" name="dict_file" accept=".csv,.AM" required>
                                </div>
                                <div class="form-group">
                                    <label>Ayah Word Mapping (data2.AM):</label>
                                    <input type="file" name="mapping_file" accept=".csv,.AM" required>
                                </div>
                                <button type="submit" class="btn">Import Data</button>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <div class="admin-section">
                        <h3>📝 Pending Contributions</h3>
                        <div class="contributions-list">
                            <?php
                            $stmt = $db->prepare('SELECT c.*, u.username, wd.arabic FROM contributions c JOIN users u ON c.user_id = u.id LEFT JOIN word_dictionary wd ON c.word_id = wd.word_id WHERE c.status = "pending" ORDER BY c.created_at DESC');
                            $result = $stmt->execute();
                            while ($contrib = $result->fetchArray(SQLITE3_ASSOC)):
                            ?>
                                <div class="contribution-item pending">
                                    <strong><?php echo htmlspecialchars($contrib['username']); ?></strong>
                                    <span style="color: #7f8c8d;">(<?php echo $contrib['contribution_type']; ?>)</span>
                                    <br>
                                    Word: <?php echo htmlspecialchars($contrib['arabic'] ?? 'N/A'); ?> (Surah <?php echo $contrib['surah']; ?>:<?php echo $contrib['ayah']; ?>)
                                    <br>
                                    Content: <?php echo htmlspecialchars($contrib['content']); ?>
                                    <br>
                                    <small style="color: #7f8c8d;"><?php echo $contrib['created_at']; ?></small>
                                    <div style="margin-top: 0.5rem;">
                                        <button class="btn" onclick="approveContribution(<?php echo $contrib['id']; ?>)">✅ Approve</button>
                                        <button class="btn btn-danger" onclick="rejectContribution(<?php echo $contrib['id']; ?>)">❌ Reject</button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div id="wordPopup" class="word-popup"></div>
    
    <div id="highlightModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>🎨 Highlight Word</h3>
            <div class="color-picker">
                <div class="color-option" style="background: #ffff00;" onclick="selectColor('#ffff00')"></div>
                <div class="color-option" style="background: #ff9999;" onclick="selectColor('#ff9999')"></div>
                <div class="color-option" style="background: #99ff99;" onclick="selectColor('#99ff99')"></div>
                <div class="color-option" style="background: #9999ff;" onclick="selectColor('#9999ff')"></div>
                <div class="color-option" style="background: #ffcc99;" onclick="selectColor('#ffcc99')"></div>
            </div>
            <div class="form-group">
                <label>Personal Note:</label>
                <textarea id="personalNote" rows="3"></textarea>
            </div>
            <button class="btn" onclick="saveHighlight()">💾 Save Highlight</button>
        </div>
    </div>
    
    <div id="contributionModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3>📝 Add Contribution</h3>
            <div class="form-group">
                <label>Type:</label>
                <select id="contributionType">
                    <option value="tafsir">Tafsir</option>
                    <option value="meaning">Meaning</option>
                    <option value="note">Note</option>
                </select>
            </div>
            <div class="form-group">
                <label>Content:</label>
                <textarea id="contributionContent" rows="4"></textarea>
            </div>
            <button class="btn" onclick="saveContribution()">📤 Submit</button>
        </div>
    </div>

    <footer class="footer">
        <p>© 2025 Quran Study Hub - Author: Yasin Ullah, Pakistani 🇵🇰</p>
        <p>A comprehensive platform for Quranic study and research</p>
    </footer>

    <script>
        let currentWordData = {};
        let selectedColor = '#ffff00';
        
        function navigateToSurah() {
            const surah = document.getElementById('surahSelect').value;
            window.location.href = `?page=reader&surah=${surah}&ayah=1`;
        }
        
        function navigateToAyah() {
            const surah = document.getElementById('surahSelect').value;
            const ayah = document.getElementById('ayahSelect').value;
            window.location.href = `?page=reader&surah=${surah}&ayah=${ayah}`;
        }
        
        function selectColor(color) {
            selectedColor = color;
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }
        
        function closeModal() {
            document.getElementById('highlightModal').style.display = 'none';
            document.getElementById('contributionModal').style.display = 'none';
        }
        
        function showHighlightModal(wordData) {
            currentWordData = wordData;
            document.getElementById('highlightModal').style.display = 'block';
        }
        
        function showContributionModal(wordData) {
            currentWordData = wordData;
            document.getElementById('contributionModal').style.display = 'block';
        }
        
        function saveHighlight() {
            const note = document.getElementById('personalNote').value;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=highlight_word&word_id=${currentWordData.wordId}&surah=${currentWordData.surah}&ayah=${currentWordData.ayah}&position=${currentWordData.position}&color=${selectedColor}&note=${encodeURIComponent(note)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
            
            closeModal();
        }
        
        function saveContribution() {
            const type = document.getElementById('contributionType').value;
            const content = document.getElementById('contributionContent').value;
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add_contribution&word_id=${currentWordData.wordId}&surah=${currentWordData.surah}&ayah=${currentWordData.ayah}&type=${type}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Contribution submitted for approval!');
                    closeModal();
                }
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const words = document.querySelectorAll('.quran-word');
            const popup = document.getElementById('wordPopup');
            
            words.forEach(word => {
                word.addEventListener('mouseenter', function(e) {
                    const wordId = this.dataset.wordId;
                    const surah = this.dataset.surah;
                    const ayah = this.dataset.ayah;
                    const position = this.dataset.pos;
                    
                    fetch(`?ajax=1&action=get_word_meaning&word_id=${wordId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.meaning) {
                                let html = `<h4>${data.meaning.arabic || 'Word'}</h4>`;
                                html += `<p><strong>Urdu:</strong> ${data.meaning.urdu_meaning || 'N/A'}</p>`;
                                html += `<p><strong>English:</strong> ${data.meaning.english_meaning || 'N/A'}</p>`;
                                
                                if (data.contributions && data.contributions.length > 0) {
                                    html += '<hr><h5>Contributions:</h5>';
                                    data.contributions.forEach(contrib => {
                                        html += `<p><small><strong>${contrib.username}:</strong> ${contrib.content}</small></p>`;
                                    });
                                }
                                
                                html += `<div class="actions">
                                    <button class="btn" onclick="showHighlightModal({wordId: ${wordId}, surah: ${surah}, ayah: ${ayah}, position: ${position}})">🎨</button>
                                    <button class="btn" onclick="showContributionModal({wordId: ${wordId}, surah: ${surah}, ayah: ${ayah}})">📝</button>
                                </div>`;
                                
                                popup.innerHTML = html;
                                popup.style.display = 'block';
                                
                                const rect = this.getBoundingClientRect();
                                popup.style.left = (rect.left + window.scrollX) + 'px';
                                popup.style.top = (rect.bottom + window.scrollY + 5) + 'px';
                            }
                        });
                });
                
                word.addEventListener('mouseleave', function() {
                    setTimeout(() => {
                        if (!popup.matches(':hover')) {
                            popup.style.display = 'none';
                        }
                    }, 100);
                });
            });
            
            popup.addEventListener('mouseleave', function() {
                this.style.display = 'none';
            });
            
            const searchInput = document.getElementById('searchInput');
            const searchResults = document.getElementById('searchResults');
            
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const query = this.value.trim();
                    if (query.length < 2) {
                        searchResults.style.display = 'none';
                        return;
                    }
                    
                    fetch(`?ajax=1&action=search&query=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            let html = '';
                            data.forEach(item => {
                                html += `<div class="search-result-item" onclick="navigateToResult(${item.surah}, ${item.ayah})">
                                    <strong>Surah ${item.surah}, Ayah ${item.ayah}</strong><br>
                                    ${item.arabic}
                                </div>`;
                            });
                            searchResults.innerHTML = html;
                            searchResults.style.display = html ? 'block' : 'none';
                        });
                });
            }
            
            const advancedSearch = document.getElementById('advancedSearch');
            const advancedResults = document.getElementById('advancedSearchResults');
            
            if (advancedSearch) {
                advancedSearch.addEventListener('input', function() {
                    const query = this.value.trim();
                    if (query.length < 2) {
                        advancedResults.style.display = 'none';
                        return;
                    }
                    
                    fetch(`?ajax=1&action=search&query=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            let html = '';
                            data.forEach(item => {
                                html += `<div class="search-result-item" onclick="window.location.href='?page=reader&surah=${item.surah}&ayah=${item.ayah}'">
                                    <strong>Surah ${item.surah}, Ayah ${item.ayah}</strong><br>
                                    ${item.arabic}
                                </div>`;
                            });
                            advancedResults.innerHTML = html;
                            advancedResults.style.display = html ? 'block' : 'none';
                        });
                });
            }
        });
        
        function navigateToResult(surah, ayah) {
            window.location.href = `?page=reader&surah=${surah}&ayah=${ayah}`;
        }
        
        function approveContribution(id) {
            if (confirm('Approve this contribution?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=approve_contribution&id=${id}`
                })
                .then(() => location.reload());
            }
        }
        
        function rejectContribution(id) {
            if (confirm('Reject this contribution?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=reject_contribution&id=${id}`
                })
                .then(() => location.reload());
            }
        }
        
        window.onclick = function(event) {
            const highlightModal = document.getElementById('highlightModal');
            const contributionModal = document.getElementById('contributionModal');
            if (event.target === highlightModal) {
                highlightModal.style.display = 'none';
            }
            if (event.target === contributionModal) {
                contributionModal.style.display = 'none';
            }
        }
    </script>
    <?php
// ... (inside the main PHP script, within the POST request handling block)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // ... (existing action handlers like login, register, import_data) ...

    if ($action === 'approve_contribution' && auth_check(['admin', 'ulama'])) {
        $contribution_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($contribution_id && $contribution_id > 0) {
            $stmt = $db->prepare('UPDATE contributions SET status = "approved", approved_by = ? WHERE id = ?');
            $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(2, $contribution_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Contribution approved.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: Failed to approve contribution.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid contribution ID.']);
        }
        exit;
    }
    
    if ($action === 'reject_contribution' && auth_check(['admin', 'ulama'])) {
        $contribution_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($contribution_id && $contribution_id > 0) {
            // Storing user_id in approved_by for rejection logs who rejected it
            $stmt = $db->prepare('UPDATE contributions SET status = "rejected", approved_by = ? WHERE id = ?');
            $stmt->bindValue(1, $_SESSION['user_id'], SQLITE3_INTEGER);
            $stmt->bindValue(2, $contribution_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Contribution rejected.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error: Failed to reject contribution.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid contribution ID.']);
        }
        exit;
    }
    
    // ... (existing action handlers like add_contribution, highlight_word) ...
}

// ... (rest of your PHP script)
?>

<!-- ... (inside the <script> tag at the bottom of your HTML) ... -->
<script>
    // ... (keep existing JavaScript variables and functions like navigateToSurah, etc.)

    function approveContribution(id) {
        if (confirm('Approve this contribution?')) {
            fetch('', { // Current page URL
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=approve_contribution&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // alert(data.message); // Optional: show success message
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not approve contribution.'));
                }
            })
            .catch(error => {
                console.error('Error approving contribution:', error);
                alert('An error occurred while approving. Please check the console.');
            });
        }
    }
    
    function rejectContribution(id) {
        if (confirm('Reject this contribution?')) {
            fetch('', { // Current page URL
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=reject_contribution&id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // alert(data.message); // Optional: show success message
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Could not reject contribution.'));
                }
            })
            .catch(error => {
                console.error('Error rejecting contribution:', error);
                alert('An error occurred while rejecting. Please check the console.');
            });
        }
    }

    // ... (keep existing JavaScript functions like window.onclick, etc.)
</script>
</body>
</html>
