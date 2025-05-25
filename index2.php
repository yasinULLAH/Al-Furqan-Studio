<?php
session_start();

$db = new SQLite3('quran_hub2.db');

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
    $h1 = password_hash('admin123', PASSWORD_DEFAULT);
    $db->exec("INSERT INTO users (username, password, role) VALUES ('admin', '$h1', 'admin')");
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

$f16 = function($f17, $f18, $f19 = null) {
    global $db;
    $f20 = "SELECT awm.*, wd.quran_text FROM ayah_word_mapping awm 
            JOIN word_dictionary wd ON awm.word_id = wd.id 
            WHERE awm.surah = ? AND awm.ayah = ? 
            ORDER BY awm.word_position";
    $f21 = $db->prepare($f20);
    $f21->bindValue(1, $f17);
    $f21->bindValue(2, $f18);
    $f22 = $f21->execute();
    
    $f23 = [];
    while ($f24 = $f22->fetchArray(SQLITE3_ASSOC)) {
        $f23[] = $f24;
    }
    return $f23;
};

$f25 = function($f26) {
    global $db;
    $f27 = $db->prepare("SELECT * FROM word_dictionary WHERE id = ?");
    $f27->bindValue(1, $f26);
    $f28 = $f27->execute();
    return $f28->fetchArray(SQLITE3_ASSOC);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                $f29 = $_POST['username'];
                $f30 = $_POST['password'];
                $f31 = $db->prepare("SELECT * FROM users WHERE username = ?");
                $f31->bindValue(1, $f29);
                $f32 = $f31->execute();
                $f33 = $f32->fetchArray(SQLITE3_ASSOC);
                
                if ($f33 && password_verify($f30, $f33['password'])) {
                    $_SESSION['user_id'] = $f33['id'];
                    $_SESSION['username'] = $f33['username'];
                    $_SESSION['role'] = $f33['role'];
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false]);
                }
                exit;
                
            case 'register':
                $f29 = $_POST['username'];
                $f30 = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $f34 = $_POST['email'];
                $f35 = $db->prepare("INSERT INTO users (username, password, email) VALUES (?, ?, ?)");
                $f35->bindValue(1, $f29);
                $f35->bindValue(2, $f30);
                $f35->bindValue(3, $f34);
                
                if ($f35->execute()) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false]);
                }
                exit;
                
            case 'upload_dictionary':
                if (isset($_FILES['dict_file']) && $_SESSION['role'] === 'admin') {
                    $f36 = $f1($_FILES['dict_file']);
                    if ($f36) {
                        $db->exec("DELETE FROM word_dictionary");
                        $f6($f36);
                        echo json_encode(['success' => true]);
                    }
                }
                exit;
                
            case 'upload_mapping':
                if (isset($_FILES['map_file']) && $_SESSION['role'] === 'admin') {
                    $f37 = $f1($_FILES['map_file']);
                    if ($f37) {
                        $db->exec("DELETE FROM ayah_word_mapping");
                        $f11($f37);
                        echo json_encode(['success' => true]);
                    }
                }
                exit;
                
            case 'get_word_meaning':
                $f38 = $_POST['word_id'];
                $f39 = $f25($f38);
                echo json_encode($f39);
                exit;
                
            case 'save_tafsir':
                if (isset($_SESSION['user_id'])) {
                    $f40 = $db->prepare("INSERT OR REPLACE INTO personal_tafsir (user_id, surah, ayah, tafsir_text) VALUES (?, ?, ?, ?)");
                    $f40->bindValue(1, $_SESSION['user_id']);
                    $f40->bindValue(2, $_POST['surah']);
                    $f40->bindValue(3, $_POST['ayah']);
                    $f40->bindValue(4, $_POST['tafsir']);
                    echo json_encode(['success' => $f40->execute()]);
                }
                exit;
                
            case 'save_theme':
                if (isset($_SESSION['user_id'])) {
                    $f41 = $db->prepare("INSERT INTO thematic_links (user_id, theme_name, surah, ayah, notes) VALUES (?, ?, ?, ?, ?)");
                    $f41->bindValue(1, $_SESSION['user_id']);
                    $f41->bindValue(2, $_POST['theme']);
                    $f41->bindValue(3, $_POST['surah']);
                    $f41->bindValue(4, $_POST['ayah']);
                    $f41->bindValue(5, $_POST['notes']);
                    echo json_encode(['success' => $f41->execute()]);
                }
                exit;
                
            case 'save_root_note':
                if (isset($_SESSION['user_id'])) {
                    $f42 = $db->prepare("INSERT OR REPLACE INTO root_notes (user_id, word_id, notes) VALUES (?, ?, ?)");
                    $f42->bindValue(1, $_SESSION['user_id']);
                    $f42->bindValue(2, $_POST['word_id']);
                    $f42->bindValue(3, $_POST['notes']);
                    echo json_encode(['success' => $f42->execute()]);
                }
                exit;
                
            case 'log_recitation':
                if (isset($_SESSION['user_id'])) {
                    $f43 = $db->prepare("INSERT INTO recitation_logs (user_id, surah, ayah_start, ayah_end, duration_minutes, notes) VALUES (?, ?, ?, ?, ?, ?)");
                    $f43->bindValue(1, $_SESSION['user_id']);
                    $f43->bindValue(2, $_POST['surah']);
                    $f43->bindValue(3, $_POST['ayah_start']);
                    $f43->bindValue(4, $_POST['ayah_end']);
                    $f43->bindValue(5, $_POST['duration']);
                    $f43->bindValue(6, $_POST['notes']);
                    echo json_encode(['success' => $f43->execute()]);
                }
                exit;
                
            case 'update_hifz':
                if (isset($_SESSION['user_id'])) {
                    $f44 = $db->prepare("INSERT OR REPLACE INTO hifz_tracking (user_id, surah, ayah, mastery_level, last_reviewed, review_count) 
                                       VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, COALESCE((SELECT review_count FROM hifz_tracking WHERE user_id=? AND surah=? AND ayah=?), 0) + 1)");
                    $f44->bindValue(1, $_SESSION['user_id']);
                    $f44->bindValue(2, $_POST['surah']);
                    $f44->bindValue(3, $_POST['ayah']);
                    $f44->bindValue(4, $_POST['level']);
                    $f44->bindValue(5, $_SESSION['user_id']);
                    $f44->bindValue(6, $_POST['surah']);
                    $f44->bindValue(7, $_POST['ayah']);
                    echo json_encode(['success' => $f44->execute()]);
                }
                exit;
                
            case 'search':
                $f45 = $_POST['query'];
                $f46 = $_POST['type'];
                $f47 = [];
                
                if ($f46 === 'arabic') {
                    $f48 = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah FROM ayah_word_mapping awm 
                                        JOIN word_dictionary wd ON awm.word_id = wd.id 
                                        WHERE wd.quran_text LIKE ? LIMIT 50");
                    $f48->bindValue(1, "%$f45%");
                } else {
                    $f48 = $db->prepare("SELECT DISTINCT awm.surah, awm.ayah FROM ayah_word_mapping awm 
                                        JOIN word_dictionary wd ON awm.word_id = wd.id 
                                        WHERE wd.en_meaning LIKE ? OR wd.ur_meaning LIKE ? LIMIT 50");
                    $f48->bindValue(1, "%$f45%");
                    $f48->bindValue(2, "%$f45%");
                }
                
                $f49 = $f48->execute();
                while ($f50 = $f49->fetchArray(SQLITE3_ASSOC)) {
                    $f47[] = $f50;
                }
                echo json_encode($f47);
                exit;
                
            case 'logout':
                session_destroy();
                echo json_encode(['success' => true]);
                exit;
        }
    }
}

if (isset($_GET['api'])) {
    switch ($_GET['api']) {
        case 'ayah_words':
            $f17 = $_GET['surah'];
            $f18 = $_GET['ayah'];
            $f51 = $f16($f17, $f18);
            echo json_encode($f51);
            exit;
            
        case 'user_data':
            if (isset($_SESSION['user_id'])) {
                $f52 = [];
                
                $f53 = $db->prepare("SELECT * FROM personal_tafsir WHERE user_id = ? AND surah = ? AND ayah = ?");
                $f53->bindValue(1, $_SESSION['user_id']);
                $f53->bindValue(2, $_GET['surah']);
                $f53->bindValue(3, $_GET['ayah']);
                $f54 = $f53->execute();
                $f52['tafsir'] = $f54->fetchArray(SQLITE3_ASSOC);
                
                $f55 = $db->prepare("SELECT * FROM thematic_links WHERE user_id = ? AND surah = ? AND ayah = ?");
                $f55->bindValue(1, $_SESSION['user_id']);
                $f55->bindValue(2, $_GET['surah']);
                $f55->bindValue(3, $_GET['ayah']);
                $f56 = $f55->execute();
                $f52['themes'] = [];
                while ($f57 = $f56->fetchArray(SQLITE3_ASSOC)) {
                    $f52['themes'][] = $f57;
                }
                
                $f58 = $db->prepare("SELECT * FROM hifz_tracking WHERE user_id = ? AND surah = ? AND ayah = ?");
                $f58->bindValue(1, $_SESSION['user_id']);
                $f58->bindValue(2, $_GET['surah']);
                $f58->bindValue(3, $_GET['ayah']);
                $f59 = $f58->execute();
                $f52['hifz'] = $f59->fetchArray(SQLITE3_ASSOC);
                
                echo json_encode($f52);
            }
            exit;
    }
}

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
?>

<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study Hub - Advanced Islamic Study Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'pulse-slow': 'pulse 3s infinite',
                    },
                    fontFamily: {
                        'arabic': ['Amiri', 'Arabic Typesetting', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap');
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .word-hover { transition: all 0.2s cubic-bezier(0.4, 0.0, 0.2, 1); }
        .word-hover:hover { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; transform: scale(1.05); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .glass-effect { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .mastery-1 { background: linear-gradient(45deg, #ff9a9e, #fecfef); }
        .mastery-2 { background: linear-gradient(45deg, #a18cd1, #fbc2eb); }
        .mastery-3 { background: linear-gradient(45deg, #fad0c4, #ffd1ff); }
        .mastery-4 { background: linear-gradient(45deg, #a8edea, #fed6e3); }
        .mastery-5 { background: linear-gradient(45deg, #d299c2, #fef9d7); }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-indigo-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-indigo-900">

<?php if (!isset($_SESSION['user_id'])): ?>
<div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-20 w-20 flex items-center justify-center rounded-full gradient-bg shadow-2xl">
                <i class="fas fa-quran text-3xl text-white"></i>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">
                Quran Study Hub
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-300">
                Advanced Islamic Study Platform
            </p>
        </div>
        
        <div class="glass-effect rounded-2xl shadow-2xl p-8" id="authContainer">
            <div id="loginForm" class="space-y-6">
                <div>
                    <label class="sr-only">Username</label>
                    <input id="loginUsername" type="text" required class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Username">
                </div>
                <div>
                    <label class="sr-only">Password</label>
                    <input id="loginPassword" type="password" required class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="Password">
                </div>
                <div>
                    <button onclick="f62()" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white gradient-bg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-300">
                        <i class="fas fa-sign-in-alt mr-2"></i>
                        Sign In
                    </button>
                </div>
                <div class="text-center">
                    <button onclick="f63()" class="text-indigo-600 hover:text-indigo-500 text-sm">
                        Need an account? Register here
                    </button>
                </div>
            </div>
            
            <div id="registerForm" class="space-y-6 hidden">
                <div>
                    <input id="regUsername" type="text" required class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Username">
                </div>
                <div>
                    <input id="regEmail" type="email" required class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Email">
                </div>
                <div>
                    <input id="regPassword" type="password" required class="appearance-none rounded-lg relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Password">
                </div>
                <div>
                    <button onclick="f64()" class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white gradient-bg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-300">
                        <i class="fas fa-user-plus mr-2"></i>
                        Register
                    </button>
                </div>
                <div class="text-center">
                    <button onclick="f65()" class="text-indigo-600 hover:text-indigo-500 text-sm">
                        Already have an account? Sign in
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function f62() {
    const f66 = document.getElementById('loginUsername').value;
    const f67 = document.getElementById('loginPassword').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=login&username=${f66}&password=${f67}`
    })
    .then(f68 => f68.json())
    .then(f69 => {
        if (f69.success) {
            location.reload();
        } else {
            alert('Invalid credentials');
        }
    });
}

function f64() {
    const f66 = document.getElementById('regUsername').value;
    const f70 = document.getElementById('regEmail').value;
    const f67 = document.getElementById('regPassword').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=register&username=${f66}&email=${f70}&password=${f67}`
    })
    .then(f68 => f68.json())
    .then(f69 => {
        if (f69.success) {
            alert('Registration successful! Please login.');
            f65();
        } else {
            alert('Registration failed');
        }
    });
}

function f63() {
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.remove('hidden');
}

function f65() {
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('loginForm').classList.remove('hidden');
}
</script>

<?php else: ?>

<div class="min-h-screen">
    <nav class="glass-effect shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-quran text-2xl text-indigo-600 mr-2"></i>
                        <span class="text-xl font-bold text-gray-900 dark:text-white">Quran Study Hub</span>
                    </div>
                    <div class="hidden md:flex space-x-1">
                        <button onclick="f71('reader')" class="nav-btn px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:bg-indigo-100 dark:hover:bg-indigo-900">
                            <i class="fas fa-book-open mr-1"></i>Reader
                        </button>
                        <button onclick="f71('search')" class="nav-btn px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:bg-indigo-100 dark:hover:bg-indigo-900">
                            <i class="fas fa-search mr-1"></i>Search
                        </button>
                        <button onclick="f71('study')" class="nav-btn px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:bg-indigo-100 dark:hover:bg-indigo-900">
                            <i class="fas fa-graduation-cap mr-1"></i>Study
                        </button>
                        <button onclick="f71('hifz')" class="nav-btn px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:bg-indigo-100 dark:hover:bg-indigo-900">
                            <i class="fas fa-memory mr-1"></i>Hifz
                        </button>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                        <button onclick="f71('admin')" class="nav-btn px-3 py-2 rounded-lg text-sm font-medium transition-all duration-200 hover:bg-red-100 dark:hover:bg-red-900 text-red-600">
                            <i class="fas fa-cog mr-1"></i>Admin
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-sm text-gray-600 dark:text-gray-300 hidden sm:block">
                        Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <button onclick="f72()" class="text-red-600 hover:text-red-700 transition-colors duration-200">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div id="readerSection" class="section-content">
            <div class="glass-effect rounded-2xl shadow-2xl p-6 mb-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
                    <div class="flex items-center space-x-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Surah</label>
                            <select id="surahSelect" onchange="f73()" class="form-select rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <?php foreach ($f60 as $f74 => $f75): ?>
                                <option value="<?php echo $f74; ?>"><?php echo $f74 . '. ' . $f75; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Ayah</label>
                            <select id="ayahSelect" onchange="f76()" class="form-select rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="1">1</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button onclick="f77()" class="btn-primary px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <button onclick="f78()" class="btn-primary px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div class="glass-effect rounded-2xl shadow-2xl p-8">
                        <div class="text-center mb-6">
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                                <span id="currentSurahName">Al-Fatiha</span>
                            </h2>
                            <p class="text-lg text-gray-600 dark:text-gray-300">
                                Ayah <span id="currentAyahNum">1</span>
                            </p>
                        </div>
                        
                        <div id="ayahDisplay" class="text-center p-8 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-gray-800 dark:to-gray-700 rounded-xl mb-6">
                            <div id="arabicText" class="font-arabic text-3xl md:text-4xl leading-loose text-gray-800 dark:text-gray-200 mb-4" style="line-height: 2.5;">
                            </div>
                            <div id="meaningDisplay" class="text-lg text-gray-600 dark:text-gray-400 italic mt-4 hidden">
                            </div>
                        </div>
                        
                        <div class="flex justify-center space-x-4 mb-6">
                            <button onclick="f79()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                <i class="fas fa-language mr-2"></i>Show Meanings
                            </button>
                            <button onclick="f80()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                                <i class="fas fa-play mr-2"></i>Audio
                            </button>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="glass-effect rounded-2xl shadow-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-sticky-note mr-2 text-yellow-500"></i>Personal Tafsir
                        </h3>
                        <textarea id="tafsirText" rows="4" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-3" placeholder="Write your personal reflections..."></textarea>
                        <button onclick="f81()" class="w-full px-4 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors duration-200">
                            Save Tafsir
                        </button>
                    </div>

                    <div class="glass-effect rounded-2xl shadow-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-tags mr-2 text-purple-500"></i>Thematic Links
                        </h3>
                        <input id="themeInput" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-3" placeholder="Theme name">
                        <textarea id="themeNotes" rows="3" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-3" placeholder="Theme notes..."></textarea>
                        <button onclick="f82()" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors duration-200">
                            Add Theme
                        </button>
                        <div id="themesList" class="mt-4 space-y-2"></div>
                    </div>

                    <div class="glass-effect rounded-2xl shadow-xl p-6">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                            <i class="fas fa-brain mr-2 text-green-500"></i>Hifz Status
                        </h3>
                        <div id="hifzStatus" class="mb-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Mastery Level:</span>
                                <select id="hifzLevel" class="form-select text-sm rounded">
                                    <option value="1">1 - Learning</option>
                                    <option value="2">2 - Practicing</option>
                                    <option value="3">3 - Memorized</option>
                                    <option value="4">4 - Reviewing</option>
                                    <option value="5">5 - Mastered</option>
                                </select>
                            </div>
                        </div>
                        <button onclick="f83()" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200">
                            Update Hifz
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="searchSection" class="section-content hidden">
            <div class="glass-effect rounded-2xl shadow-2xl p-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    <i class="fas fa-search mr-3 text-indigo-600"></i>Advanced Search
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="md:col-span-2">
                        <input id="searchQuery" type="text" class="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Search in Quran...">
                    </div>
                    <div class="flex space-x-2">
                        <select id="searchType" class="form-select rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="arabic">Arabic Text</option>
                            <option value="translation">Translation</option>
                        </select>
                        <button onclick="f84()" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors duration-200">
                            Search
                        </button>
                    </div>
                </div>
                
                <div id="searchResults" class="space-y-4"></div>
            </div>
        </div>

        <div id="studySection" class="section-content hidden">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="glass-effect rounded-2xl shadow-2xl p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                        <i class="fas fa-clock mr-3 text-blue-600"></i>Recitation Log
                    </h3>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Surah</label>
                                <select id="reciteSurah" class="form-select w-full rounded-lg border-gray-300 shadow-sm">
                                    <?php foreach ($f60 as $f74 => $f75): ?>
                                    <option value="<?php echo $f74; ?>"><?php echo $f74 . '. ' . $f75; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Duration (min)</label>
                                <input id="reciteDuration" type="number" class="form-input w-full rounded-lg border-gray-300 shadow-sm" placeholder="Minutes">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">From Ayah</label>
                                <input id="reciteStart" type="number" min="1" value="1" class="form-input w-full rounded-lg border-gray-300 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">To Ayah</label>
                                <input id="reciteEnd" type="number" min="1" value="1" class="form-input w-full rounded-lg border-gray-300 shadow-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Notes</label>
                            <textarea id="reciteNotes" rows="3" class="form-textarea w-full rounded-lg border-gray-300 shadow-sm" placeholder="Any notes or reflections..."></textarea>
                        </div>
                        <button onclick="f85()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            Log Recitation
                        </button>
                    </div>
                </div>

                <div class="glass-effect rounded-2xl shadow-2xl p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">
                        <i class="fas fa-chart-line mr-3 text-green-600"></i>Study Statistics
                    </h3>
                    <div class="space-y-6" id="studyStats">
                        <div class="text-center p-4 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-xl">
                            <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">0</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Total Recitations</div>
                        </div>
                        <div class="text-center p-4 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900 dark:to-emerald-900 rounded-xl">
                            <div class="text-2xl font-bold text-green-600 dark:text-green-400">0</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Ayahs Memorized</div>
                        </div>
                        <div class="text-center p-4 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-xl">
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">0</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Personal Notes</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="hifzSection" class="section-content hidden">
            <div class="glass-effect rounded-2xl shadow-2xl p-6">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">
                    <i class="fas fa-memory mr-3 text-green-600"></i>Hifz Tracking Dashboard
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="text-center p-6 bg-gradient-to-r from-emerald-50 to-green-50 dark:from-emerald-900 dark:to-green-900 rounded-xl">
                        <div class="text-3xl font-bold text-emerald-600 dark:text-emerald-400" id="totalMemorized">0</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Ayahs Memorized</div>
                    </div>
                    <div class="text-center p-6 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900 dark:to-indigo-900 rounded-xl">
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400" id="reviewsDue">0</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Reviews Due</div>
                    </div>
                    <div class="text-center p-6 bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900 dark:to-orange-900 rounded-xl">
                        <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400" id="progressPercent">0%</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Overall Progress</div>
                    </div>
                    <div class="text-center p-6 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900 dark:to-pink-900 rounded-xl">
                        <div class="text-3xl font-bold text-purple-600 dark:text-purple-400" id="currentStreak">0</div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Day Streak</div>
                    </div>
                </div>

                <div id="hifzProgress" class="space-y-4"></div>
            </div>
        </div>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <div id="adminSection" class="section-content hidden">
            <div class="glass-effect rounded-2xl shadow-2xl p-6">
                <h2 class="text-2xl font-bold text-red-600 mb-6">
                    <i class="fas fa-cog mr-3"></i>Admin Panel
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-6 bg-red-50 dark:bg-red-900 rounded-xl">
                        <h3 class="text-lg font-semibold text-red-800 dark:text-red-200 mb-4">
                            <i class="fas fa-book mr-2"></i>Word Dictionary Upload
                        </h3>
                        <p class="text-sm text-red-600 dark:text-red-300 mb-4">
                            Upload CSV with: quran_text, ur_meaning, en_meaning
                        </p>
                        <input type="file" id="dictFile" accept=".csv" class="mb-4 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-red-50 file:text-red-700 hover:file:bg-red-100">
                        <button onclick="f86()" class="w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200">
                            Upload Dictionary
                        </button>
                    </div>
                    
                    <div class="p-6 bg-blue-50 dark:bg-blue-900 rounded-xl">
                        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200 mb-4">
                            <i class="fas fa-map mr-2"></i>Word Mapping Upload
                        </h3>
                        <p class="text-sm text-blue-600 dark:text-blue-300 mb-4">
                            Upload CSV with: word_id, surah, ayah, word_position
                        </p>
                        <input type="file" id="mapFile" accept=".csv" class="mb-4 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <button onclick="f87()" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            Upload Mapping
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <div id="wordTooltip" class="fixed z-50 p-4 bg-gray-900 text-white rounded-lg shadow-2xl max-w-sm hidden transform transition-all duration-200">
        <div class="font-arabic text-lg mb-2" id="tooltipArabic"></div>
        <div class="text-sm text-blue-300 mb-1" id="tooltipEnglish"></div>
        <div class="text-sm text-green-300" id="tooltipUrdu"></div>
    </div>
</div>

<script>
let f88 = 1;
let f89 = 1;
let f90 = <?php echo json_encode($f60); ?>;
let f91 = <?php echo json_encode($f61); ?>;

function f71(f92) {
    document.querySelectorAll('.section-content').forEach(f93 => f93.classList.add('hidden'));
    document.getElementById(f92 + 'Section').classList.remove('hidden');
    
    document.querySelectorAll('.nav-btn').forEach(f94 => f94.classList.remove('bg-indigo-100', 'dark:bg-indigo-900'));
    event.target.classList.add('bg-indigo-100', 'dark:bg-indigo-900');
}

function f72() {
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=logout'
    }).then(() => location.reload());
}

function f73() {
    f88 = parseInt(document.getElementById('surahSelect').value);
    f89 = 1;
    f95();
    f76();
}

function f95() {
    const f96 = document.getElementById('ayahSelect');
    f96.innerHTML = '';
    for (let f97 = 1; f97 <= f91[f88]; f97++) {
        f96.innerHTML += `<option value="${f97}">${f97}</option>`;
    }
    f96.value = f89;
}

function f76() {
    f89 = parseInt(document.getElementById('ayahSelect').value);
    f98();
    f99();
}

function f98() {
    document.getElementById('currentSurahName').textContent = f90[f88];
    document.getElementById('currentAyahNum').textContent = f89;
    
    fetch(`?api=ayah_words&surah=${f88}&ayah=${f89}`)
        .then(f100 => f100.json())
        .then(f101 => {
            const f102 = document.getElementById('arabicText');
            f102.innerHTML = '';
            
            f101.forEach(f103 => {
                const f104 = document.createElement('span');
                f104.textContent = f103.quran_text + ' ';
                f104.className = 'word-hover cursor-pointer mx-1 px-2 py-1';
                f104.setAttribute('data-surah', f103.surah);
                f104.setAttribute('data-ayah', f103.ayah);
                f104.setAttribute('data-pos', f103.word_position);
                f104.setAttribute('data-word-id', f103.word_id);
                
                f104.addEventListener('mouseenter', f105);
                f104.addEventListener('mouseleave', f106);
                f104.addEventListener('mousemove', f107);
                
                f102.appendChild(f104);
            });
        });
}

function f105(f108) {
    const f109 = f108.target.getAttribute('data-word-id');
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_word_meaning&word_id=${f109}`
    })
    .then(f100 => f100.json())
    .then(f110 => {
        if (f110) {
            document.getElementById('tooltipArabic').textContent = f110.quran_text;
            document.getElementById('tooltipEnglish').textContent = f110.en_meaning || 'No English meaning';
            document.getElementById('tooltipUrdu').textContent = f110.ur_meaning || 'No Urdu meaning';
            document.getElementById('wordTooltip').classList.remove('hidden');
        }
    });
}

function f106() {
    document.getElementById('wordTooltip').classList.add('hidden');
}

function f107(f108) {
    const f111 = document.getElementById('wordTooltip');
    f111.style.left = (f108.pageX + 10) + 'px';
    f111.style.top = (f108.pageY - 10) + 'px';
}

function f99() {
    fetch(`?api=user_data&surah=${f88}&ayah=${f89}`)
        .then(f100 => f100.json())
        .then(f112 => {
            document.getElementById('tafsirText').value = f112.tafsir ? f112.tafsir.tafsir_text : '';
            
            const f113 = document.getElementById('themesList');
            f113.innerHTML = '';
            if (f112.themes) {
                f112.themes.forEach(f114 => {
                    f113.innerHTML += `<div class="p-2 bg-purple-100 dark:bg-purple-800 rounded text-sm">
                        <strong>${f114.theme_name}</strong><br>
                        <span class="text-gray-600 dark:text-gray-300">${f114.notes}</span>
                    </div>`;
                });
            }
            
            if (f112.hifz) {
                document.getElementById('hifzLevel').value = f112.hifz.mastery_level;
            }
        });
}

function f77() {
    if (f89 > 1) {
        f89--;
    } else if (f88 > 1) {
        f88--;
        f89 = f91[f88];
    }
    f115();
}

function f78() {
    if (f89 < f91[f88]) {
        f89++;
    } else if (f88 < 114) {
        f88++;
        f89 = 1;
    }
    f115();
}

function f115() {
    document.getElementById('surahSelect').value = f88;
    f95();
    f76();
}

function f79() {
    const f116 = document.getElementById('meaningDisplay');
    f116.classList.toggle('hidden');
}

function f80() {
    alert('Audio feature will be implemented with audio API integration');
}

function f81() {
    const f117 = document.getElementById('tafsirText').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_tafsir&surah=${f88}&ayah=${f89}&tafsir=${encodeURIComponent(f117)}`
    })
    .then(f100 => f100.json())
    .then(f118 => {
        if (f118.success) {
            f119('Tafsir saved successfully!', 'success');
        }
    });
}

function f82() {
    const f120 = document.getElementById('themeInput').value;
    const f121 = document.getElementById('themeNotes').value;
    
    if (!f120) return;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=save_theme&surah=${f88}&ayah=${f89}&theme=${encodeURIComponent(f120)}&notes=${encodeURIComponent(f121)}`
    })
    .then(f100 => f100.json())
    .then(f118 => {
        if (f118.success) {
            f119('Theme saved successfully!', 'success');
            document.getElementById('themeInput').value = '';
            document.getElementById('themeNotes').value = '';
            f99();
        }
    });
}

function f83() {
    const f122 = document.getElementById('hifzLevel').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update_hifz&surah=${f88}&ayah=${f89}&level=${f122}`
    })
    .then(f100 => f100.json())
    .then(f118 => {
        if (f118.success) {
            f119('Hifz status updated!', 'success');
            f123();
        }
    });
}

function f84() {
    const f124 = document.getElementById('searchQuery').value;
    const f125 = document.getElementById('searchType').value;
    
    if (!f124) return;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=search&query=${encodeURIComponent(f124)}&type=${f125}`
    })
    .then(f100 => f100.json())
    .then(f126 => {
        const f127 = document.getElementById('searchResults');
        f127.innerHTML = '';
        
        if (f126.length === 0) {
            f127.innerHTML = '<div class="text-center text-gray-500 py-8">No results found</div>';
            return;
        }
        
        f126.forEach(f128 => {
            f127.innerHTML += `<div class="p-4 bg-white dark:bg-gray-800 rounded-lg shadow cursor-pointer hover:bg-indigo-50 dark:hover:bg-indigo-900 transition-colors duration-200" 
                onclick="f129(${f128.surah}, ${f128.ayah})">
                <div class="flex justify-between items-center">
                    <span class="font-medium">${f90[f128.surah]} ${f128.surah}:${f128.ayah}</span>
                    <i class="fas fa-arrow-right text-indigo-600"></i>
                </div>
            </div>`;
        });
    });
}

function f129(f130, f131) {
    f88 = f130;
    f89 = f131;
    f71('reader');
    f115();
}

function f85() {
    const f132 = document.getElementById('reciteSurah').value;
    const f133 = document.getElementById('reciteStart').value;
    const f134 = document.getElementById('reciteEnd').value;
    const f135 = document.getElementById('reciteDuration').value;
    const f136 = document.getElementById('reciteNotes').value;
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=log_recitation&surah=${f132}&ayah_start=${f133}&ayah_end=${f134}&duration=${f135}&notes=${encodeURIComponent(f136)}`
    })
    .then(f100 => f100.json())
    .then(f118 => {
        if (f118.success) {
            f119('Recitation logged successfully!', 'success');
            document.getElementById('reciteDuration').value = '';
            document.getElementById('reciteNotes').value = '';
            f137();
        }
    });
}

function f123() {
    fetch(`?api=hifz_progress`)
        .then(f100 => f100.json())
        .then(f138 => {
            const f139 = document.getElementById('hifzProgress');
            f139.innerHTML = '';
            
            f138.forEach(f140 => {
                const f141 = `mastery-${f140.mastery_level}`;
                f139.innerHTML += `<div class="p-4 ${f141} rounded-lg shadow">
                    <div class="flex justify-between items-center">
                        <span class="font-medium">${f90[f140.surah]} ${f140.surah}:${f140.ayah}</span>
                        <span class="text-sm opacity-75">Level ${f140.mastery_level}</span>
                    </div>
                    <div class="text-sm opacity-75 mt-1">
                        Reviewed ${f140.review_count} times
                    </div>
                </div>`;
            });
        });
}

function f137() {
    fetch(`?api=study_stats`)
        .then(f100 => f100.json())
        .then(f142 => {
            if (f142.recitations) document.querySelector('#studyStats div:nth-child(1) .text-2xl').textContent = f142.recitations;
            if (f142.memorized) document.querySelector('#studyStats div:nth-child(2) .text-2xl').textContent = f142.memorized;
            if (f142.notes) document.querySelector('#studyStats div:nth-child(3) .text-2xl').textContent = f142.notes;
        });
}

function f86() {
    const f143 = document.getElementById('dictFile').files[0];
    if (!f143) return;
    
    const f144 = new FormData();
    f144.append('action', 'upload_dictionary');
    f144.append('dict_file', f143);
    
    fetch('', {
        method: 'POST',
        body: f144
    })
    .then(f100 => f100.json())
    .then(f118 => {
        if (f118.success) {
            f119('Dictionary uploaded successfully!', 'success');
        } else {
            f119('Upload failed!', 'error');
        }
    });
}

function f87() {
    const f145 = document.getElementById('mapFile').files[0];
    if (!f145) return;
    
    const f146 = new FormData();
    f146.append('action', 'upload_mapping');
    f146.append('map_file', f145);
    
    fetch('', {
        method: 'POST',
        body: f146
    })
    .then(f100 => f100.json())
    .then(f118 => {
        if (f118.success) {
            f119('Mapping uploaded successfully!', 'success');
        } else {
            f119('Upload failed!', 'error');
        }
    });
}

function f119(f147, f148) {
    const f149 = document.createElement('div');
    f149.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg text-white ${f148 === 'success' ? 'bg-green-500' : 'bg-red-500'} animate-slide-up`;
    f149.textContent = f147;
    document.body.appendChild(f149);
    
    setTimeout(() => {
        f149.remove();
    }, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    f98();
    f99();
    f137();
    f123();
});

window.addEventListener('load', function() {
    document.body.classList.add('animate-fade-in');
});
</script>

<?php endif; ?>

</body>
</html>

<!-- Author: Yasin Ullah, Pakistani Developer -->
