<?php
session_start();

$db = new SQLite3('quran_hub.db');

$db->exec('CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    email TEXT,
    role TEXT DEFAULT "user",
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)');

$db->exec('CREATE TABLE IF NOT EXISTS word_dictionary (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quran_text TEXT NOT NULL,
    ur_meaning TEXT,
    en_meaning TEXT
)');

$db->exec('CREATE TABLE IF NOT EXISTS ayah_word_mapping (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    word_position INTEGER,
    FOREIGN KEY (word_id) REFERENCES word_dictionary(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS personal_tafsir (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    tafsir_text TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS thematic_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    theme_name TEXT,
    surah INTEGER,
    ayah INTEGER,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS root_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    word_id INTEGER,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (word_id) REFERENCES word_dictionary(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS recitation_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    surah INTEGER,
    ayah_start INTEGER,
    ayah_end INTEGER,
    duration_minutes INTEGER,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)');

$db->exec('CREATE TABLE IF NOT EXISTS hifz_tracking (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    surah INTEGER,
    ayah INTEGER,
    mastery_level INTEGER DEFAULT 1,
    last_reviewed DATETIME,
    review_count INTEGER DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id)
)');

if (!$db->querySingle("SELECT COUNT(*) FROM users WHERE role='admin'")) {
    $pa1 = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$pa1', 'admin')");
}

$f1 = function($f2) {
    if (!$f2) return null;
    $f3 = fopen($f2['tmp_name'], 'r');
    $f4 = [];
    while (($f5 = fgetcsv($f3)) !== FALSE) {
        $f4[] = $f5;
    }
    fclose($f3);
    return $f4;
};

$f6 = function($f7) {
    global $db;
    $f8 = $db->prepare("INSERT INTO word_dictionary (quran_text, ur_meaning, en_meaning) VALUES (?, ?, ?)");
    foreach ($f7 as $f9 => $f10) {
        if ($f9 == 0) continue;
        if (count($f10) >= 3) {
            $f8->bindValue(1, $f10[0]);
            $f8->bindValue(2, $f10[1]);
            $f8->bindValue(3, $f10[2]);
            $f8->execute();
        }
    }
};

$f11 = function($f12) {
    global $db;
    $f13 = $db->prepare("INSERT INTO ayah_word_mapping (word_id, surah, ayah, word_position) VALUES (?, ?, ?, ?)");
    foreach ($f12 as $f14 => $f15) {
        if ($f14 == 0) continue;
        if (count($f15) >= 4) {
            $f13->bindValue(1, $f15[0]);
            $f13->bindValue(2, $f15[1]);
            $f13->bindValue(3, $f15[2]);
            $f13->bindValue(4, $f15[3]);
            $f13->execute();
        }
    }
};

$f16 = function($s1, $a1) {
    global $db;
    $st1 = "SELECT awm.*, wd.quran_text, wd.en_meaning, wd.ur_meaning FROM ayah_word_mapping awm 
            JOIN word_dictionary wd ON awm.word_id = wd.id 
            WHERE awm.surah = ? AND awm.ayah = ? 
            ORDER BY awm.word_position";
    $st2 = $db->prepare($st1);
    $st2->bindValue(1, $s1);
    $st2->bindValue(2, $a1);
    $res1 = $st2->execute();
    
    $words1 = [];
    while ($row1 = $res1->fetchArray(SQLITE3_ASSOC)) {
        $words1[] = $row1;
    }
    return $words1;
};

$currentSurah = isset($_GET['s']) ? (int)$_GET['s'] : 1;
$currentAyah = isset($_GET['a']) ? (int)$_GET['a'] : 1;
$currentSection = isset($_GET['section']) ? $_GET['section'] : 'reader';

$f60 = [
    1 => 'Al-Fatiha', 2 => 'Al-Baqarah', 3 => 'Ali Imran', 4 => 'An-Nisa', 5 => 'Al-Maidah',
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

$f61 = [
    1 => 7, 2 => 286, 3 => 200, 4 => 176, 5 => 120, 6 => 165, 7 => 206, 8 => 75, 9 => 129, 10 => 109,
    11 => 123, 12 => 111, 13 => 43, 14 => 52, 15 => 99, 16 => 128, 17 => 111, 18 => 110, 19 => 98, 20 => 135,
    21 => 112, 22 => 78, 23 => 118, 24 => 64, 25 => 77, 26 => 227, 27 => 93, 28 => 88, 29 => 69, 30 => 60,
    31 => 34, 32 => 30, 33 => 73, 34 => 54, 35 => 45, 36 => 83, 37 => 182, 38 => 88, 39 => 75, 40 => 85,
    41 => 54, 42 => 53, 43 => 89, 44 => 59, 45 => 37, 46 => 35, 47 => 38, 48 => 29, 49 => 18, 50 => 45,
    51 => 60, 52 => 49, 53 => 62, 54 => 55, 55 => 78, 56 => 96, 57 => 29, 58 => 22, 59 => 24, 60 => 13,
    61 => 14, 62 => 11, 63 => 11, 64 => 18, 65 => 12, 66 => 12, 67 => 30, 68 => 52, 69 => 52, 70 => 44,
    71 => 28, 72 => 28, 73 => 20, 74 => 56, 75 => 40, 76 => 31, 77 => 50, 78 => 40, 79 => 46, 80 => 42,
    81 => 29, 82 => 19, 83 => 36, 84 => 25, 85 => 22, 86 => 17, 87 => 19, 88 => 26, 89 => 30, 90 => 20,
    91 => 15, 92 => 21, 93 => 11, 94 => 8, 95 => 8, 96 => 19, 97 => 5, 98 => 8, 99 => 8, 100 => 11,
    101 => 11, 102 => 8, 103 => 3, 104 => 9, 105 => 5, 106 => 4, 107 => 7, 108 => 3, 109 => 6, 110 => 3,
    111 => 5, 112 => 4, 113 => 5, 114 => 6
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $user1 = $_POST['username'];
                $pass1 = $_POST['password'];
                $stmt1 = $db->prepare("SELECT * FROM users WHERE username = ?");
                $stmt1->bindValue(1, $user1);
                $result1 = $stmt1->execute();
                $userData = $result1->fetchArray(SQLITE3_ASSOC);
                
                if ($userData && password_verify($pass1, $userData['password'])) {
                    $_SESSION['user_id'] = $userData['id'];
                    $_SESSION['username'] = $userData['username'];
                    $_SESSION['role'] = $userData['role'];
                }
                header('Location: ?section=reader');
                exit;
                
            case 'register':
                $user1 = $_POST['username'];
                $pass1 = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $email1 = $_POST['email'];
                $stmt2 = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                $stmt2->bindValue(1, $user1);
                $stmt2->bindValue(2, $pass1);
                $stmt2->bindValue(3, $email1);
                $stmt2->execute();
                header('Location: ?section=reader');
                exit;
                
            case 'logout':
                session_destroy();
                header('Location: ?');
                exit;
                
            case 'save_tafsir':
                if (isset($_SESSION['user_id'])) {
                    $stmt3 = $db->prepare("INSERT OR REPLACE INTO personal_tafsir (user_id, surah, ayah, tafsir_text) VALUES (?, ?, ?, ?)");
                    $stmt3->bindValue(1, $_SESSION['user_id']);
                    $stmt3->bindValue(2, $_POST['surah']);
                    $stmt3->bindValue(3, $_POST['ayah']);
                    $stmt3->bindValue(4, $_POST['tafsir']);
                    $stmt3->execute();
                }
                header("Location: ?section=reader&s={$_POST['surah']}&a={$_POST['ayah']}");
                exit;
                
            case 'save_theme':
                if (isset($_SESSION['user_id'])) {
                    $stmt4 = $db->prepare("INSERT INTO thematic_links (user_id, theme_name, surah, ayah, notes) VALUES (?, ?, ?, ?, ?)");
                    $stmt4->bindValue(1, $_SESSION['user_id']);
                    $stmt4->bindValue(2, $_POST['theme']);
                    $stmt4->bindValue(3, $_POST['surah']);
                    $stmt4->bindValue(4, $_POST['ayah']);
                    $stmt4->bindValue(5, $_POST['notes']);
                    $stmt4->execute();
                }
                header("Location: ?section=reader&s={$_POST['surah']}&a={$_POST['ayah']}");
                exit;
                
            case 'update_hifz':
                if (isset($_SESSION['user_id'])) {
                    $stmt5 = $db->prepare("INSERT OR REPLACE INTO hifz_tracking (user_id, surah, ayah, mastery_level, last_reviewed, review_count) 
                                          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, COALESCE((SELECT review_count FROM hifz_tracking WHERE user_id=? AND surah=? AND ayah=?), 0) + 1)");
                    $stmt5->bindValue(1, $_SESSION['user_id']);
                    $stmt5->bindValue(2, $_POST['surah']);
                    $stmt5->bindValue(3, $_POST['ayah']);
                    $stmt5->bindValue(4, $_POST['level']);
                    $stmt5->bindValue(5, $_SESSION['user_id']);
                    $stmt5->bindValue(6, $_POST['surah']);
                    $stmt5->bindValue(7, $_POST['ayah']);
                    $stmt5->execute();
                }
                header("Location: ?section=reader&s={$_POST['surah']}&a={$_POST['ayah']}");
                exit;
                
            case 'upload_dictionary':
                if (isset($_FILES['dict_file']) && $_SESSION['role'] === 'admin') {
                    $data1 = $f1($_FILES['dict_file']);
                    if ($data1) {
                        $db->exec("DELETE FROM word_dictionary");
                        $f6($data1);
                    }
                }
                header('Location: ?section=admin');
                exit;
                
            case 'upload_mapping':
                if (isset($_FILES['map_file']) && $_SESSION['role'] === 'admin') {
                    $data2 = $f1($_FILES['map_file']);
                    if ($data2) {
                        $db->exec("DELETE FROM ayah_word_mapping");
                        $f11($data2);
                    }
                }
                header('Location: ?section=admin');
                exit;
                
            case 'search':
                $query1 = $_POST['query'];
                $type1 = $_POST['type'];
                
                if ($type1 === 'arabic') {
                    $stmt6 = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah FROM ayah_word_mapping awm 
                                          JOIN word_dictionary wd ON awm.word_id = wd.id 
                                          WHERE wd.quran_text LIKE ? LIMIT 50");
                    $stmt6->bindValue(1, "%$query1%");
                } else {
                    $stmt6 = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah FROM ayah_word_mapping awm 
                                          JOIN word_dictionary wd ON awm.word_id = wd.id 
                                          WHERE wd.en_meaning LIKE ? OR wd.ur_meaning LIKE ? LIMIT 50");
                    $stmt6->bindValue(1, "%$query1%");
                    $stmt6->bindValue(2, "%$query1%");
                }
                
                header("Location: ?section=search&q=" . urlencode($query1) . "&type=$type1");
                exit;
        }
    }
}

$currentWords = $f16($currentSurah, $currentAyah);

$userTafsir = null;
$userThemes = [];
$userHifz = null;

if (isset($_SESSION['user_id'])) {
    $stmt7 = $db->prepare("SELECT * FROM personal_tafsir WHERE user_id = ? AND surah = ? AND ayah = ?");
    $stmt7->bindValue(1, $_SESSION['user_id']);
    $stmt7->bindValue(2, $currentSurah);
    $stmt7->bindValue(3, $currentAyah);
    $result2 = $stmt7->execute();
    $userTafsir = $result2->fetchArray(SQLITE3_ASSOC);
    
    $stmt8 = $db->prepare("SELECT * FROM thematic_links WHERE user_id = ? AND surah = ? AND ayah = ?");
    $stmt8->bindValue(1, $_SESSION['user_id']);
    $stmt8->bindValue(2, $currentSurah);
    $stmt8->bindValue(3, $currentAyah);
    $result3 = $stmt8->execute();
    while ($theme1 = $result3->fetchArray(SQLITE3_ASSOC)) {
        $userThemes[] = $theme1;
    }
    
    $stmt9 = $db->prepare("SELECT * FROM hifz_tracking WHERE user_id = ? AND surah = ? AND ayah = ?");
    $stmt9->bindValue(1, $_SESSION['user_id']);
    $stmt9->bindValue(2, $currentSurah);
    $stmt9->bindValue(3, $currentAyah);
    $result4 = $stmt9->execute();
    $userHifz = $result4->fetchArray(SQLITE3_ASSOC);
}

$searchResults = [];
if ($currentSection === 'search' && isset($_GET['q'])) {
    $query1 = $_GET['q'];
    $type1 = $_GET['type'] ?? 'arabic';
    
    if ($type1 === 'arabic') {
        $stmt10 = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah FROM ayah_word_mapping awm 
                               JOIN word_dictionary wd ON awm.word_id = wd.id 
                               WHERE wd.quran_text LIKE ? LIMIT 50");
        $stmt10->bindValue(1, "%$query1%");
    } else {
        $stmt10 = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah FROM ayah_word_mapping awm 
                               JOIN word_dictionary wd ON awm.word_id = wd.id 
                               WHERE wd.en_meaning LIKE ? OR wd.ur_meaning LIKE ? LIMIT 50");
        $stmt10->bindValue(1, "%$query1%");
        $stmt10->bindValue(2, "%$query1%");
    }
    
    $result5 = $stmt10->execute();
    while ($row2 = $result5->fetchArray(SQLITE3_ASSOC)) {
        $searchResults[] = $row2;
    }
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study Hub - Advanced Islamic Study Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #cbd5e1 100%);
            min-height: 100vh;
        }
        
        .arabic { font-family: 'Amiri', serif; }
        .gradient-bg { background: var(--primary-gradient); }
        .glass { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); }
        
        .word-span {
            display: inline-block;
            margin: 0 4px;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }
        
        .word-span:hover {
            background: var(--primary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .tooltip {
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            margin-bottom: 8px;
        }
        
        .word-span:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
        
        .tooltip::after {
            content: '';
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid transparent;
            border-top-color: rgba(0,0,0,0.9);
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .btn-secondary {
            background: white;
            color: #374151;
            border: 2px solid #e5e7eb;
        }
        
        .btn-secondary:hover {
            border-color: #6366f1;
            color: #6366f1;
        }
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 24px;
            border: 1px solid #f1f5f9;
        }
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
        }
        
        .nav-link {
            padding: 12px 16px;
            border-radius: 8px;
            text-decoration: none;
            color: #374151;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-link:hover, .nav-link.active {
            background: #e0e7ff;
            color: #6366f1;
        }
        
        .mastery-1 { background: linear-gradient(45deg, #fee2e2, #fecaca); }
        .mastery-2 { background: linear-gradient(45deg, #ddd6fe, #e0e7ff); }
        .mastery-3 { background: linear-gradient(45deg, #fef3c7, #fed7aa); }
        .mastery-4 { background: linear-gradient(45deg, #d1fae5, #bbf7d0); }
        .mastery-5 { background: linear-gradient(45deg, #cffafe, #a7f3d0); }
        
        .animate-fade-in { animation: fadeIn 0.6s ease-out; }
        .animate-slide-up { animation: slideUp 0.4s ease-out; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .grid {
            display: grid;
            gap: 24px;
        }
        
        .grid-cols-1 { grid-template-columns: 1fr; }
        .grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
        .grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
        
        @media (max-width: 768px) {
            .grid-cols-2, .grid-cols-3 { grid-template-columns: 1fr; }
            .container { padding: 0 16px; }
        }
        
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .space-y-4 > * + * { margin-top: 16px; }
        .space-y-6 > * + * { margin-top: 24px; }
        .space-x-4 > * + * { margin-left: 16px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mt-4 { margin-top: 16px; }
        .mt-6 { margin-top: 24px; }
        .text-center { text-align: center; }
        .text-2xl { font-size: 24px; }
        .text-3xl { font-size: 30px; }
        .text-4xl { font-size: 36px; }
        .text-lg { font-size: 18px; }
        .text-sm { font-size: 14px; }
        .font-bold { font-weight: 700; }
        .font-semibold { font-weight: 600; }
        .text-gray-600 { color: #4b5563; }
        .text-gray-800 { color: #1f2937; }
        .text-blue-600 { color: #2563eb; }
        .text-green-600 { color: #16a34a; }
        .text-red-600 { color: #dc2626; }
        .text-white { color: white; }
        .hidden { display: none; }
        .block { display: block; }
        .w-full { width: 100%; }
        .h-20 { height: 80px; }
        .rounded-lg { border-radius: 8px; }
        .rounded-xl { border-radius: 12px; }
        .shadow-lg { box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .shadow-xl { box-shadow: 0 20px 25px rgba(0,0,0,0.1); }
        .p-4 { padding: 16px; }
        .p-6 { padding: 24px; }
        .p-8 { padding: 32px; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .py-3 { padding-top: 12px; padding-bottom: 12px; }
        .px-4 { padding-left: 16px; padding-right: 16px; }
        .px-6 { padding-left: 24px; padding-right: 24px; }
    </style>
</head>
<body class="animate-fade-in">

<?php if (!isset($_SESSION['user_id'])): ?>
<div class="flex items-center justify-center min-h-screen">
    <div class="glass-card w-full max-w-md">
        <div class="text-center mb-6">
            <div class="w-20 h-20 gradient-bg rounded-xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-quran text-3xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Quran Study Hub</h1>
            <p class="text-gray-600">Advanced Islamic Study Platform</p>
        </div>

        <?php if (isset($_GET['register'])): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="register">
            <input type="text" name="username" placeholder="Username" class="form-input" required>
            <input type="email" name="email" placeholder="Email" class="form-input" required>
            <input type="password" name="password" placeholder="Password" class="form-input" required>
            <button type="submit" class="btn btn-primary w-full">
                <i class="fas fa-user-plus"></i>Register
            </button>
            <p class="text-center text-sm">
                <a href="?" class="text-blue-600 hover:underline">Already have an account? Sign in</a>
            </p>
        </form>
        <?php else: ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <input type="text" name="username" placeholder="Username" class="form-input" required>
            <input type="password" name="password" placeholder="Password" class="form-input" required>
            <button type="submit" class="btn btn-primary w-full">
                <i class="fas fa-sign-in-alt"></i>Sign In
            </button>
            <p class="text-center text-sm">
                <a href="?register=1" class="text-blue-600 hover:underline">Need an account? Register here</a>
            </p>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>

<nav class="glass shadow-lg sticky top-0 z-50">
    <div class="container">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <div class="flex items-center">
                    <i class="fas fa-quran text-2xl text-blue-600 mr-3"></i>
                    <span class="text-xl font-bold text-gray-800">Quran Study Hub</span>
                </div>
                <div class="hidden md:flex space-x-2">
                    <a href="?section=reader" class="nav-link <?= $currentSection === 'reader' ? 'active' : '' ?>">
                        <i class="fas fa-book-open"></i>Reader
                    </a>
                    <a href="?section=search" class="nav-link <?= $currentSection === 'search' ? 'active' : '' ?>">
                        <i class="fas fa-search"></i>Search
                    </a>
                    <a href="?section=study" class="nav-link <?= $currentSection === 'study' ? 'active' : '' ?>">
                        <i class="fas fa-graduation-cap"></i>Study
                    </a>
                    <a href="?section=hifz" class="nav-link <?= $currentSection === 'hifz' ? 'active' : '' ?>">
                        <i class="fas fa-memory"></i>Hifz
                    </a>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="?section=admin" class="nav-link <?= $currentSection === 'admin' ? 'active' : '' ?>" style="color: #dc2626;">
                        <i class="fas fa-cog"></i>Admin
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600 hidden sm:block">
                    Welcome, <?= htmlspecialchars($_SESSION['username']) ?>
                </span>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="text-red-600 hover:text-red-700">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container py-6">

<?php if ($currentSection === 'reader'): ?>
<div class="space-y-6">
    <div class="glass-card">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div class="flex items-center space-x-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Surah</label>
                    <form method="GET" onchange="this.submit()">
                        <input type="hidden" name="section" value="reader">
                        <input type="hidden" name="a" value="1">
                        <select name="s" class="form-select">
                            <?php foreach ($f60 as $num => $name): ?>
                            <option value="<?= $num ?>" <?= $currentSurah == $num ? 'selected' : '' ?>>
                                <?= $num ?>. <?= $name ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Ayah</label>
                    <form method="GET" onchange="this.submit()">
                        <input type="hidden" name="section" value="reader">
                        <input type="hidden" name="s" value="<?= $currentSurah ?>">
                        <select name="a" class="form-select">
                            <?php for ($i = 1; $i <= $f61[$currentSurah]; $i++): ?>
                            <option value="<?= $i ?>" <?= $currentAyah == $i ? 'selected' : '' ?>>
                                <?= $i ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </form>
                </div>
            </div>
            <div class="flex space-x-2">
                <?php 
                $prevS = $currentSurah;
                $prevA = $currentAyah - 1;
                if ($prevA < 1 && $currentSurah > 1) {
                    $prevS = $currentSurah - 1;
                    $prevA = $f61[$prevS];
                }
                
                $nextS = $currentSurah;
                $nextA = $currentAyah + 1;
                if ($nextA > $f61[$currentSurah] && $currentSurah < 114) {
                    $nextS = $currentSurah + 1;
                    $nextA = 1;
                }
                ?>
                <?php if ($prevA >= 1 || $prevS != $currentSurah): ?>
                <a href="?section=reader&s=<?= $prevS ?>&a=<?= $prevA ?>" class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
                <?php if ($nextA <= $f61[$currentSurah] || $nextS != $currentSurah): ?>
                <a href="?section=reader&s=<?= $nextS ?>&a=<?= $nextA ?>" class="btn btn-secondary">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="glass-card">
                <div class="text-center mb-6">
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">
                        <?= $f60[$currentSurah] ?>
                    </h2>
                    <p class="text-lg text-gray-600">Ayah <?= $currentAyah ?></p>
                </div>
                
                <div class="p-8 bg-gradient-to-r from-blue-50 to-purple-50 rounded-xl mb-6">
                    <div class="arabic text-4xl leading-loose text-center text-gray-800" style="line-height: 2.5;">
                        <?php foreach ($currentWords as $word): ?>
                        <span class="word-span">
                            <?= htmlspecialchars($word['quran_text']) ?>
                            <div class="tooltip">
                                <div class="arabic text-lg mb-2"><?= htmlspecialchars($word['quran_text']) ?></div>
                                <div class="text-blue-300 text-sm mb-1">
                                    <?= htmlspecialchars($word['en_meaning'] ?: 'No English meaning') ?>
                                </div>
                                <div class="text-green-300 text-sm">
                                    <?= htmlspecialchars($word['ur_meaning'] ?: 'No Urdu meaning') ?>
                                </div>
                            </div>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="flex justify-center space-x-4">
                    <button class="btn btn-primary" onclick="alert('Audio feature coming soon')">
                        <i class="fas fa-play"></i>Play Audio
                    </button>
                    <button class="btn btn-secondary" onclick="toggleMeanings()">
                        <i class="fas fa-language"></i>Toggle Meanings
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="glass-card">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-sticky-note mr-2 text-yellow-500"></i>Personal Tafsir
                </h3>
                <form method="POST">
                    <input type="hidden" name="action" value="save_tafsir">
                    <input type="hidden" name="surah" value="<?= $currentSurah ?>">
                    <input type="hidden" name="ayah" value="<?= $currentAyah ?>">
                    <textarea name="tafsir" rows="4" class="form-textarea mb-4" placeholder="Write your personal reflections..."><?= htmlspecialchars($userTafsir['tafsir_text'] ?? '') ?></textarea>
                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-save"></i>Save Tafsir
                    </button>
                </form>
            </div>

            <div class="glass-card">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-tags mr-2 text-purple-500"></i>Thematic Links
                </h3>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="action" value="save_theme">
                    <input type="hidden" name="surah" value="<?= $currentSurah ?>">
                    <input type="hidden" name="ayah" value="<?= $currentAyah ?>">
                    <input type="text" name="theme" placeholder="Theme name" class="form-input mb-3" required>
                    <textarea name="notes" rows="3" class="form-textarea mb-3" placeholder="Theme notes..."></textarea>
                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-plus"></i>Add Theme
                    </button>
                </form>
                
                <div class="space-y-2">
                    <?php foreach ($userThemes as $theme): ?>
                    <div class="p-3 bg-purple-100 rounded-lg">
                        <div class="font-semibold text-purple-800"><?= htmlspecialchars($theme['theme_name']) ?></div>
                        <div class="text-sm text-purple-600"><?= htmlspecialchars($theme['notes']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="glass-card">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">
                    <i class="fas fa-brain mr-2 text-green-500"></i>Hifz Status
                </h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_hifz">
                    <input type="hidden" name="surah" value="<?= $currentSurah ?>">
                    <input type="hidden" name="ayah" value="<?= $currentAyah ?>">
                    <div class="mb-4">
                        <label class="block text-sm font-semibold text-gray-600 mb-2">Mastery Level</label>
                        <select name="level" class="form-select">
                            <option value="1" <?= ($userHifz['mastery_level'] ?? 1) == 1 ? 'selected' : '' ?>>1 - Learning</option>
                            <option value="2" <?= ($userHifz['mastery_level'] ?? 1) == 2 ? 'selected' : '' ?>>2 - Practicing</option>
                            <option value="3" <?= ($userHifz['mastery_level'] ?? 1) == 3 ? 'selected' : '' ?>>3 - Memorized</option>
                            <option value="4" <?= ($userHifz['mastery_level'] ?? 1) == 4 ? 'selected' : '' ?>>4 - Reviewing</option>
                            <option value="5" <?= ($userHifz['mastery_level'] ?? 1) == 5 ? 'selected' : '' ?>>5 - Mastered</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">
                        <i class="fas fa-save"></i>Update Hifz
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($currentSection === 'search'): ?>
<div class="glass-card">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">
        <i class="fas fa-search mr-3 text-blue-600"></i>Advanced Search
    </h2>
    
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <input type="hidden" name="action" value="search">
        <div class="md:col-span-2">
            <input type="text" name="query" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" class="form-input" placeholder="Search in Quran..." required>
        </div>
        <div class="flex space-x-2">
            <select name="type" class="form-select">
                <option value="arabic" <?= ($_GET['type'] ?? 'arabic') === 'arabic' ? 'selected' : '' ?>>Arabic Text</option>
                <option value="translation" <?= ($_GET['type'] ?? 'arabic') === 'translation' ? 'selected' : '' ?>>Translation</option>
            </select>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i>Search
            </button>
        </div>
    </form>
    
    <div class="space-y-4">
        <?php if (empty($searchResults) && isset($_GET['q'])): ?>
        <div class="text-center text-gray-500 py-8">No results found for "<?= htmlspecialchars($_GET['q']) ?>"</div>
        <?php endif; ?>
        
        <?php foreach ($searchResults as $result): ?>
        <div class="card hover:shadow-lg transition-shadow duration-300">
            <a href="?section=reader&s=<?= $result['surah'] ?>&a=<?= $result['ayah'] ?>" class="block">
                <div class="flex justify-between items-center">
                    <span class="font-semibold text-gray-800">
                        <?= $f60[$result['surah']] ?> <?= $result['surah'] ?>:<?= $result['ayah'] ?>
                    </span>
                    <i class="fas fa-arrow-right text-blue-600"></i>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php elseif ($currentSection === 'study'): ?>
<div class="grid grid-cols-1 lg:grid-cols-2">
    <div class="glass-card">
        <h3 class="text-xl font-bold text-gray-800 mb-6">
            <i class="fas fa-clock mr-3 text-blue-600"></i>Recitation Log
        </h3>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="log_recitation">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Surah</label>
                    <select name="surah" class="form-select">
                        <?php foreach ($f60 as $num => $name): ?>
                        <option value="<?= $num ?>"><?= $num ?>. <?= $name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">Duration (min)</label>
                    <input type="number" name="duration" class="form-input" placeholder="Minutes" required>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">From Ayah</label>
                    <input type="number" name="ayah_start" min="1" value="1" class="form-input" required>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-2">To Ayah</label>
                    <input type="number" name="ayah_end" min="1" value="1" class="form-input" required>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-600 mb-2">Notes</label>
                <textarea name="notes" rows="3" class="form-textarea" placeholder="Any notes or reflections..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-full">
                <i class="fas fa-plus"></i>Log Recitation
            </button>
        </form>
    </div>

    <div class="glass-card">
        <h3 class="text-xl font-bold text-gray-800 mb-6">
            <i class="fas fa-chart-line mr-3 text-green-600"></i>Study Statistics
        </h3>
        <div class="space-y-6">
            <?php
            $totalRecitations = $db->querySingle("SELECT COUNT(*) FROM recitation_logs WHERE user_id = " . $_SESSION['user_id']);
            $totalAyahs = $db->querySingle("SELECT COUNT(*) FROM hifz_tracking WHERE user_id = " . $_SESSION['user_id'] . " AND mastery_level >= 3");
            $totalNotes = $db->querySingle("SELECT COUNT(*) FROM personal_tafsir WHERE user_id = " . $_SESSION['user_id']);
            ?>
            <div class="text-center p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
                <div class="text-2xl font-bold text-blue-600"><?= $totalRecitations ?></div>
                <div class="text-sm text-gray-600">Total Recitations</div>
            </div>
            <div class="text-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                <div class="text-2xl font-bold text-green-600"><?= $totalAyahs ?></div>
                <div class="text-sm text-gray-600">Ayahs Memorized</div>
            </div>
            <div class="text-center p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
                <div class="text-2xl font-bold text-purple-600"><?= $totalNotes ?></div>
                <div class="text-sm text-gray-600">Personal Notes</div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($currentSection === 'hifz'): ?>
<div class="glass-card">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">
        <i class="fas fa-memory mr-3 text-green-600"></i>Hifz Tracking Dashboard
    </h2>
    
    <?php
    $hifzStats = $db->prepare("SELECT 
        COUNT(*) as total_memorized,
        COUNT(CASE WHEN mastery_level >= 3 THEN 1 END) as memorized_ayahs,
        COUNT(CASE WHEN last_reviewed < date('now', '-7 days') THEN 1 END) as reviews_due
        FROM hifz_tracking WHERE user_id = ?");
    $hifzStats->bindValue(1, $_SESSION['user_id']);
    $statsResult = $hifzStats->execute();
    $stats = $statsResult->fetchArray(SQLITE3_ASSOC);
    ?>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="text-center p-6 bg-gradient-to-r from-emerald-50 to-green-50 rounded-xl">
            <div class="text-3xl font-bold text-emerald-600"><?= $stats['memorized_ayahs'] ?></div>
            <div class="text-sm text-gray-600">Ayahs Memorized</div>
        </div>
        <div class="text-center p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
            <div class="text-3xl font-bold text-blue-600"><?= $stats['reviews_due'] ?></div>
            <div class="text-sm text-gray-600">Reviews Due</div>
        </div>
        <div class="text-center p-6 bg-gradient-to-r from-yellow-50 to-orange-50 rounded-xl">
            <div class="text-3xl font-bold text-yellow-600"><?= round(($stats['memorized_ayahs']/6236)*100, 1) ?>%</div>
            <div class="text-sm text-gray-600">Overall Progress</div>
        </div>
        <div class="text-center p-6 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
            <div class="text-3xl font-bold text-purple-600">0</div>
            <div class="text-sm text-gray-600">Day Streak</div>
        </div>
    </div>

    <?php
    $hifzProgress = $db->prepare("SELECT ht.*, 0 as surah_name FROM hifz_tracking ht WHERE user_id = ? ORDER BY surah, ayah LIMIT 50");
    $hifzProgress->bindValue(1, $_SESSION['user_id']);
    $progressResult = $hifzProgress->execute();
    ?>
    
    <div class="space-y-4">
        <?php while ($progress = $progressResult->fetchArray(SQLITE3_ASSOC)): ?>
        <div class="p-4 mastery-<?= $progress['mastery_level'] ?> rounded-lg">
            <div class="flex justify-between items-center">
                <span class="font-semibold">
                    <?= $f60[$progress['surah']] ?> <?= $progress['surah'] ?>:<?= $progress['ayah'] ?>
                </span>
                <span class="text-sm opacity-75">Level <?= $progress['mastery_level'] ?></span>
            </div>
            <div class="text-sm opacity-75 mt-1">
                Reviewed <?= $progress['review_count'] ?> times
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<?php elseif ($currentSection === 'admin' && $_SESSION['role'] === 'admin'): ?>
<div class="glass-card">
    <h2 class="text-2xl font-bold text-red-600 mb-6">
        <i class="fas fa-cog mr-3"></i>Admin Panel
    </h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="p-6 bg-red-50 rounded-xl">
            <h3 class="text-lg font-semibold text-red-800 mb-4">
                <i class="fas fa-book mr-2"></i>Word Dictionary Upload
            </h3>
            <p class="text-sm text-red-600 mb-4">
                Upload CSV with: quran_text, ur_meaning, en_meaning
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_dictionary">
                <input type="file" name="dict_file" accept=".csv" class="form-input mb-4" required>
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-upload"></i>Upload Dictionary
                </button>
            </form>
        </div>
        
        <div class="p-6 bg-blue-50 rounded-xl">
            <h3 class="text-lg font-semibold text-blue-800 mb-4">
                <i class="fas fa-map mr-2"></i>Word Mapping Upload
            </h3>
            <p class="text-sm text-blue-600 mb-4">
                Upload CSV with: word_id, surah, ayah, word_position
            </p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_mapping">
                <input type="file" name="map_file" accept=".csv" class="form-input mb-4" required>
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-upload"></i>Upload Mapping
                </button>
            </form>
        </div>
    </div>
    
    <div class="mt-8 p-6 bg-gray-50 rounded-xl">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-database mr-2"></i>Database Status
        </h3>
        <div class="grid grid-cols-2 gap-4">
            <?php
            $dictCount = $db->querySingle("SELECT COUNT(*) FROM word_dictionary");
            $mappingCount = $db->querySingle("SELECT COUNT(*) FROM ayah_word_mapping");
            $userCount = $db->querySingle("SELECT COUNT(*) FROM users");
            ?>
            <div class="text-center">
                <div class="text-2xl font-bold text-blue-600"><?= $dictCount ?></div>
                <div class="text-sm text-gray-600">Dictionary Words</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-green-600"><?= $mappingCount ?></div>
                <div class="text-sm text-gray-600">Word Mappings</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-purple-600"><?= $userCount ?></div>
                <div class="text-sm text-gray-600">Total Users</div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold text-orange-600">
                    <?= $db->querySingle("SELECT COUNT(DISTINCT surah) FROM ayah_word_mapping") ?>
                </div>
                <div class="text-sm text-gray-600">Surahs Available</div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

</main>

<script>
function toggleMeanings() {
    const tooltips = document.querySelectorAll('.tooltip');
    tooltips.forEach(tooltip => {
        if (tooltip.style.opacity === '1') {
            tooltip.style.opacity = '0';
            tooltip.style.visibility = 'hidden';
        } else {
            tooltip.style.opacity = '1';
            tooltip.style.visibility = 'visible';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const wordSpans = document.querySelectorAll('.word-span');
    
    wordSpans.forEach(span => {
        span.addEventListener('click', function() {
            const tooltip = this.querySelector('.tooltip');
            if (tooltip) {
                tooltip.style.opacity = tooltip.style.opacity === '1' ? '0' : '1';
                tooltip.style.visibility = tooltip.style.visibility === 'visible' ? 'hidden' : 'visible';
            }
        });
    });

    const forms = document.querySelectorAll('form[method="GET"]');
    forms.forEach(form => {
        form.addEventListener('change', function() {
            this.submit();
        });
    });

    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(message => {
        setTimeout(() => {
            message.style.opacity = '0';
            setTimeout(() => message.remove(), 300);
        }, 3000);
    });

    if (window.location.hash) {
        const target = document.querySelector(window.location.hash);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth' });
        }
    }

    const mobileMenuToggle = document.getElementById('mobile-menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuToggle && mobileMenu) {
        mobileMenuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }

    const saveButtons = document.querySelectorAll('button[type="submit"]');
    saveButtons.forEach(button => {
        button.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
            this.disabled = true;
        });
    });

    const searchForm = document.querySelector('form[action*="search"]');
    if (searchForm) {
        const searchInput = searchForm.querySelector('input[name="query"]');
        if (searchInput) {
            searchInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchForm.submit();
                }
            });
        }
    }

    const tooltipElements = document.querySelectorAll('[data-tooltip]');
    tooltipElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'absolute z-50 px-3 py-2 text-sm text-white bg-black rounded shadow-lg';
            tooltip.textContent = this.getAttribute('data-tooltip');
            tooltip.style.top = (this.offsetTop - 40) + 'px';
            tooltip.style.left = this.offsetLeft + 'px';
            document.body.appendChild(tooltip);
            this.tooltipElement = tooltip;
        });

        element.addEventListener('mouseleave', function() {
            if (this.tooltipElement) {
                this.tooltipElement.remove();
                this.tooltipElement = null;
            }
        });
    });

    window.addEventListener('beforeunload', function(e) {
        const unsavedForms = document.querySelectorAll('form[data-changed="true"]');
        if (unsavedForms.length > 0) {
            e.preventDefault();
            e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
        }
    });

    const formInputs = document.querySelectorAll('input, textarea, select');
    formInputs.forEach(input => {
        input.addEventListener('change', function() {
            const form = this.closest('form');
            if (form) {
                form.setAttribute('data-changed', 'true');
            }
        });
    });

    const submitForms = document.querySelectorAll('form');
    submitForms.forEach(form => {
        form.addEventListener('submit', function() {
            this.removeAttribute('data-changed');
        });
    });

    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.getAttribute('data-width');
        if (width) {
            setTimeout(() => {
                bar.style.width = width + '%';
            }, 100);
        }
    });

    const animatedCounters = document.querySelectorAll('.animate-counter');
    animatedCounters.forEach(counter => {
        const target = parseInt(counter.textContent);
        let current = 0;
        const increment = target / 100;
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            counter.textContent = Math.floor(current);
        }, 20);
    });
});

function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white animate-slide-up ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('Copied to clipboard!');
    }).catch(() => {
        showNotification('Failed to copy', 'error');
    });
}

function shareAyah(surah, ayah) {
    const url = `${window.location.origin}?section=reader&s=${surah}&a=${ayah}`;
    if (navigator.share) {
        navigator.share({
            title: `Quran ${surah}:${ayah}`,
            url: url
        });
    } else {
        copyToClipboard(url);
    }
}

function printAyah() {
    window.print();
}

function exportData() {
    showNotification('Export feature coming soon');
}

function importData() {
    showNotification('Import feature coming soon');
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

function changeTheme(theme) {
    document.body.className = `theme-${theme}`;
    localStorage.setItem('theme', theme);
}

function loadUserPreferences() {
    const theme = localStorage.getItem('theme');
    if (theme) {
        changeTheme(theme);
    }
    
    const fontSize = localStorage.getItem('fontSize');
    if (fontSize) {
        document.documentElement.style.fontSize = fontSize + 'px';
    }
}

function saveFontSize(size) {
    document.documentElement.style.fontSize = size + 'px';
    localStorage.setItem('fontSize', size);
}

loadUserPreferences();
</script>

<?php endif; ?>

</body>
</html>

<!-- Author: Yasin Ullah, Pakistani Developer -->
