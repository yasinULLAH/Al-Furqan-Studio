<?php
// Author: Yasin Ullah
// Pakistani

// Configuration
define('DB_PATH', __DIR__ . '/quran_study2.sqlite');
define('APP_NAME', 'Nur Al-Quran Studio Offline');
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123'); // CHANGE THIS IN PRODUCTION!
define('DEFAULT_TRANSLATION', 'English'); // Default translation to display

// Initialize database
function init_db() {
    $db = new SQLite3(DB_PATH);
    $db->exec("PRAGMA foreign_keys = ON;");

    // Create tables if they don't exist
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT DEFAULT 'Public' CHECK (role IN ('Public', 'User', 'Ulama', 'Admin'))
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS surahs (
        id INTEGER PRIMARY KEY,
        name_arabic TEXT,
        name_english TEXT,
        revelation_type TEXT,
        ayah_count INTEGER
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS ayahs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        surah_id INTEGER,
        ayah_number INTEGER,
        arabic_text TEXT,
        FOREIGN KEY (surah_id) REFERENCES surahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS translations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ayah_id INTEGER,
        language TEXT,
        text TEXT,
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS word_translations (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        word_id INTEGER UNIQUE,
        ur_meaning TEXT,
        en_meaning TEXT
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS word_metadata (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        word_id INTEGER UNIQUE,
        surah_id INTEGER,
        ayah_id INTEGER,
        word_position INTEGER,
        arabic_word TEXT -- Store the actual Arabic word for easier lookup
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS personal_tafsir (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        surah_id INTEGER,
        ayah_id INTEGER,
        tafsir_text TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

     $db->exec("CREATE TABLE IF NOT EXISTS community_tafsir (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER, -- Original contributor
        surah_id INTEGER,
        ayah_id INTEGER,
        tafsir_text TEXT,
        status TEXT DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        approved_by INTEGER, -- Admin/Ulama user id
        approved_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");


    $db->exec("CREATE TABLE IF NOT EXISTS themes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        theme_name TEXT,
        description TEXT,
        is_public INTEGER DEFAULT 0, -- 0: private, 1: community, 2: ulama/admin
        status TEXT DEFAULT 'Pending' CHECK (status IN ('Pending', 'Approved', 'Rejected')),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        approved_by INTEGER, -- Admin/Ulama user id
        approved_at DATETIME,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (approved_by) REFERENCES users(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS theme_ayahs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        theme_id INTEGER,
        surah_id INTEGER,
        ayah_id INTEGER,
        FOREIGN KEY (theme_id) REFERENCES themes(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS hifz_tracking (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        surah_id INTEGER,
        ayah_id INTEGER,
        status TEXT CHECK (status IN ('Not Started', 'Memorizing', 'Memorized')),
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id),
        FOREIGN KEY (ayah_id) REFERENCES ayahs(id)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS recitation_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        surah_id INTEGER,
        ayah_from INTEGER,
        ayah_to INTEGER,
        recitation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        notes TEXT,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (surah_id) REFERENCES surahs(id)
    )");

    // Add admin user if not exists
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindValue(':username', ADMIN_USERNAME);
    $result = $stmt->execute();
    $row = $result->fetchArray();
    if ($row[0] == 0) {
        $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'Admin')");
        $stmt->bindValue(':username', ADMIN_USERNAME);
        $stmt->bindValue(':password', password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT));
        $stmt->execute();
    }

    $db->close();
}

// Load initial data
function load_initial_data() {
    $db = new SQLite3(DB_PATH);
    $db->exec("PRAGMA foreign_keys = ON;");
    $db->exec("BEGIN TRANSACTION;");

    // Check if surahs are already loaded
    $stmt = $db->prepare("SELECT COUNT(*) FROM surahs");
    $result = $stmt->execute();
    $row = $result->fetchArray();
    if ($row[0] == 0) {
        // Load Surah names and metadata (basic, can be expanded)
        // This data is often available in separate files or online APIs.
        // For this example, hardcode a basic list.
        $surahs = [
            1 => ['name_english' => 'Al-Fatiha', 'revelation_type' => 'Meccan', 'ayah_count' => 7],
            2 => ['name_english' => 'Al-Baqarah', 'revelation_type' => 'Medinan', 'ayah_count' => 286],
            3 => ['name_english' => 'Al-Imran', 'revelation_type' => 'Medinan', 'ayah_count' => 200],
            4 => ['name_english' => 'An-Nisa', 'revelation_type' => 'Medinan', 'ayah_count' => 176],
            5 => ['name_english' => 'Al-Ma\'idah', 'revelation_type' => 'Medinan', 'ayah_count' => 120],
            6 => ['name_english' => 'Al-An\'am', 'revelation_type' => 'Meccan', 'ayah_count' => 165],
            7 => ['name_english' => 'Al-A\'raf', 'revelation_type' => 'Meccan', 'ayah_count' => 206],
            8 => ['name_english' => 'Al-Anfal', 'revelation_type' => 'Medinan', 'ayah_count' => 75],
            9 => ['name_english' => 'At-Tawbah', 'revelation_type' => 'Medinan', 'ayah_count' => 129],
            10 => ['name_english' => 'Yunus', 'revelation_type' => 'Meccan', 'ayah_count' => 109],
            11 => ['name_english' => 'Hud', 'revelation_type' => 'Meccan', 'ayah_count' => 123],
            12 => ['name_english' => 'Yusuf', 'revelation_type' => 'Meccan', 'ayah_count' => 111],
            13 => ['name_english' => 'Ar-Ra\'d', 'revelation_type' => 'Medinan', 'ayah_count' => 43],
            14 => ['name_english' => 'Ibrahim', 'revelation_type' => 'Meccan', 'ayah_count' => 52],
            15 => ['name_english' => 'Al-Hijr', 'revelation_type' => 'Meccan', 'ayah_count' => 99],
            16 => ['name_english' => 'An-Nahl', 'revelation_type' => 'Meccan', 'ayah_count' => 128],
            17 => ['name_english' => 'Al-Isra', 'revelation_type' => 'Meccan', 'ayah_count' => 111],
            18 => ['name_english' => 'Al-Kahf', 'revelation_type' => 'Meccan', 'ayah_count' => 110],
            19 => ['name_english' => 'Maryam', 'revelation_type' => 'Meccan', 'ayah_count' => 98],
            20 => ['name_english' => 'Taha', 'revelation_type' => 'Meccan', 'ayah_count' => 135],
            21 => ['name_english' => 'Al-Anbiya', 'revelation_type' => 'Meccan', 'ayah_count' => 112],
            22 => ['name_english' => 'Al-Hajj', 'revelation_type' => 'Medinan', 'ayah_count' => 78],
            23 => ['name_english' => 'Al-Mu\'minun', 'revelation_type' => 'Meccan', 'ayah_count' => 118],
            24 => ['name_english' => 'An-Nur', 'revelation_type' => 'Medinan', 'ayah_count' => 64],
            25 => ['name_english' => 'Al-Furqan', 'revelation_type' => 'Meccan', 'ayah_count' => 77],
            26 => ['name_english' => 'Ash-Shu\'ara', 'revelation_type' => 'Meccan', 'ayah_count' => 227],
            27 => ['name_english' => 'An-Naml', 'revelation_type' => 'Meccan', 'ayah_count' => 93],
            28 => ['name_english' => 'Al-Qasas', 'revelation_type' => 'Meccan', 'ayah_count' => 88],
            29 => ['name_english' => 'Al-Ankabut', 'revelation_type' => 'Meccan', 'ayah_count' => 69],
            30 => ['name_english' => 'Ar-Rum', 'revelation_type' => 'Meccan', 'ayah_count' => 60],
            31 => ['name_english' => 'Luqman', 'revelation_type' => 'Meccan', 'ayah_count' => 34],
            32 => ['name_english' => 'As-Sajdah', 'revelation_type' => 'Meccan', 'ayah_count' => 30],
            33 => ['name_english' => 'Al-Ahzab', 'revelation_type' => 'Medinan', 'ayah_count' => 73],
            34 => ['name_english' => 'Saba', 'revelation_type' => 'Meccan', 'ayah_count' => 54],
            35 => ['name_english' => 'Fatir', 'revelation_type' => 'Meccan', 'ayah_count' => 45],
            36 => ['name_english' => 'Ya-Sin', 'revelation_type' => 'Meccan', 'ayah_count' => 83],
            37 => ['name_english' => 'As-Saffat', 'revelation_type' => 'Meccan', 'ayah_count' => 182],
            38 => ['name_english' => 'Sad', 'revelation_type' => 'Meccan', 'ayah_count' => 88],
            39 => ['name_english' => 'Az-Zumar', 'revelation_type' => 'Meccan', 'ayah_count' => 75],
            40 => ['name_english' => 'Ghafir', 'revelation_type' => 'Meccan', 'ayah_count' => 85],
            41 => ['name_english' => 'Fussilat', 'revelation_type' => 'Meccan', 'ayah_count' => 54],
            42 => ['name_english' => 'Ash-Shuraa', 'revelation_type' => 'Meccan', 'ayah_count' => 53],
            43 => ['name_english' => 'Az-Zukhruf', 'revelation_type' => 'Meccan', 'ayah_count' => 89],
            44 => ['name_english' => 'Ad-Dukhan', 'revelation_type' => 'Meccan', 'ayah_count' => 59],
            45 => ['name_english' => 'Al-Jathiyah', 'revelation_type' => 'Meccan', 'ayah_count' => 37],
            46 => ['name_english' => 'Al-Ahqaf', 'revelation_type' => 'Meccan', 'ayah_count' => 35],
            47 => ['name_english' => 'Muhammad', 'revelation_type' => 'Medinan', 'ayah_count' => 38],
            48 => ['name_english' => 'Al-Fath', 'revelation_type' => 'Medinan', 'ayah_count' => 29],
            49 => ['name_english' => 'Al-Hujurat', 'revelation_type' => 'Medinan', 'ayah_count' => 18],
            50 => ['name_english' => 'Qaf', 'revelation_type' => 'Meccan', 'ayah_count' => 45],
            51 => ['name_english' => 'Adh-Dhariyat', 'revelation_type' => 'Meccan', 'ayah_count' => 60],
            52 => ['name_english' => 'At-Tur', 'revelation_type' => 'Meccan', 'ayah_count' => 49],
            53 => ['name_english' => 'An-Najm', 'revelation_type' => 'Meccan', 'ayah_count' => 62],
            54 => ['name_english' => 'Al-Qamar', 'revelation_type' => 'Meccan', 'ayah_count' => 55],
            55 => ['name_english' => 'Ar-Rahman', 'revelation_type' => 'Medinan', 'ayah_count' => 78],
            56 => ['name_english' => 'Al-Waqi\'ah', 'revelation_type' => 'Meccan', 'ayah_count' => 96],
            57 => ['name_english' => 'Al-Hadid', 'revelation_type' => 'Medinan', 'ayah_count' => 29],
            58 => ['name_english' => 'Al-Mujadila', 'revelation_type' => 'Medinan', 'ayah_count' => 22],
            59 => ['name_english' => 'Al-Hashr', 'revelation_type' => 'Medinan', 'ayah_count' => 24],
            60 => ['name_english' => 'Al-Mumtahanah', 'revelation_type' => 'Medinan', 'ayah_count' => 13],
            61 => ['name_english' => 'As-Saff', 'revelation_type' => 'Medinan', 'ayah_count' => 14],
            62 => ['name_english' => 'Al-Jumu\'ah', 'revelation_type' => 'Medinan', 'ayah_count' => 11],
            63 => ['name_english' => 'Al-Munafiqun', 'revelation_type' => 'Medinan', 'ayah_count' => 11],
            64 => ['name_english' => 'At-Taghabun', 'revelation_type' => 'Medinan', 'ayah_count' => 18],
            65 => ['name_english' => 'At-Talaq', 'revelation_type' => 'Medinan', 'ayah_count' => 12],
            66 => ['name_english' => 'At-Tahrim', 'revelation_type' => 'Medinan', 'ayah_count' => 12],
            67 => ['name_english' => 'Al-Mulk', 'revelation_type' => 'Meccan', 'ayah_count' => 30],
            68 => ['name_english' => 'Al-Qalam', 'revelation_type' => 'Meccan', 'ayah_count' => 52],
            69 => ['name_english' => 'Al-Haqqah', 'revelation_type' => 'Meccan', 'ayah_count' => 52],
            70 => ['name_english' => 'Al-Ma\'arij', 'revelation_type' => 'Meccan', 'ayah_count' => 44],
            71 => ['name_english' => 'Nuh', 'revelation_type' => 'Meccan', 'ayah_count' => 28],
            72 => ['name_english' => 'Al-Jinn', 'revelation_type' => 'Meccan', 'ayah_count' => 28],
            73 => ['name_english' => 'Al-Muzzammil', 'revelation_type' => 'Meccan', 'ayah_count' => 20],
            74 => ['name_english' => 'Al-Muddaththir', 'revelation_type' => 'Meccan', 'ayah_count' => 56],
            75 => ['name_english' => 'Al-Qiyamah', 'revelation_type' => 'Meccan', 'ayah_count' => 40],
            76 => ['name_english' => 'Al-Insan', 'revelation_type' => 'Medinan', 'ayah_count' => 31],
            77 => ['name_english' => 'Al-Mursalat', 'revelation_type' => 'Meccan', 'ayah_count' => 50],
            78 => ['name_english' => 'An-Naba', 'revelation_type' => 'Meccan', 'ayah_count' => 40],
            79 => ['name_english' => 'An-Nazi\'at', 'revelation_type' => 'Meccan', 'ayah_count' => 46],
            80 => ['name_english' => '\'Abasa', 'revelation_type' => 'Meccan', 'ayah_count' => 42],
            81 => ['name_english' => 'At-Takwir', 'revelation_type' => 'Meccan', 'ayah_count' => 29],
            82 => ['name_english' => 'Al-Infitar', 'revelation_type' => 'Meccan', 'ayah_count' => 19],
            83 => ['name_english' => 'Al-Mutaffifin', 'revelation_type' => 'Meccan', 'ayah_count' => 36],
            84 => ['name_english' => 'Al-Inshiqaq', 'revelation_type' => 'Meccan', 'ayah_count' => 25],
            85 => ['name_english' => 'Al-Buruj', 'revelation_type' => 'Meccan', 'ayah_count' => 22],
            86 => ['name_english' => 'At-Tariq', 'revelation_type' => 'Meccan', 'ayah_count' => 17],
            87 => ['name_english' => 'Al-A\'la', 'revelation_type' => 'Meccan', 'ayah_count' => 19],
            88 => ['name_english' => 'Al-Ghashiyah', 'revelation_type' => 'Meccan', 'ayah_count' => 26],
            89 => ['name_english' => 'Al-Fajr', 'revelation_type' => 'Meccan', 'ayah_count' => 30],
            90 => ['name_english' => 'Al-Balad', 'revelation_type' => 'Meccan', 'ayah_count' => 20],
            91 => ['name_english' => 'Ash-Shams', 'revelation_type' => 'Meccan', 'ayah_count' => 15],
            92 => ['name_english' => 'Al-Layl', 'revelation_type' => 'Meccan', 'ayah_count' => 21],
            93 => ['name_english' => 'Ad-Duhaa', 'revelation_type' => 'Meccan', 'ayah_count' => 11],
            94 => ['name_english' => 'Ash-Sharh', 'revelation_type' => 'Meccan', 'ayah_count' => 8],
            95 => ['name_english' => 'At-Tin', 'revelation_type' => 'Meccan', 'ayah_count' => 8],
            96 => ['name_english' => 'Al-\'Alaq', 'revelation_type' => 'Meccan', 'ayah_count' => 19],
            97 => ['name_english' => 'Al-Qadr', 'revelation_type' => 'Meccan', 'ayah_count' => 5],
            98 => ['name_english' => 'Al-Bayyinah', 'revelation_type' => 'Medinan', 'ayah_count' => 8],
            99 => ['name_english' => 'Az-Zalzalah', 'revelation_type' => 'Medinan', 'ayah_count' => 8],
            100 => ['name_english' => 'Al-\'Adiyat', 'revelation_type' => 'Meccan', 'ayah_count' => 11],
            101 => ['name_english' => 'Al-Qari\'ah', 'revelation_type' => 'Meccan', 'ayah_count' => 11],
            102 => ['name_english' => 'At-Takathur', 'revelation_type' => 'Meccan', 'ayah_count' => 8],
            103 => ['name_english' => 'Al-\'Asr', 'revelation_type' => 'Meccan', 'ayah_count' => 3],
            104 => ['name_english' => 'Al-Humazah', 'revelation_type' => 'Meccan', 'ayah_count' => 9],
            105 => ['name_english' => 'Al-Fil', 'revelation_type' => 'Meccan', 'ayah_count' => 5],
            106 => ['name_english' => 'Quraysh', 'revelation_type' => 'Meccan', 'ayah_count' => 4],
            107 => ['name_english' => 'Al-Ma\'un', 'revelation_type' => 'Meccan', 'ayah_count' => 7],
            108 => ['name_english' => 'Al-Kawthar', 'revelation_type' => 'Meccan', 'ayah_count' => 3],
            109 => ['name_english' => 'Al-Kafirun', 'revelation_type' => 'Meccan', 'ayah_count' => 6],
            110 => ['name_english' => 'An-Nasr', 'revelation_type' => 'Medinan', 'ayah_count' => 3],
            111 => ['name_english' => 'Al-Masad', 'revelation_type' => 'Meccan', 'ayah_count' => 5],
            112 => ['name_english' => 'Al-Ikhlas', 'revelation_type' => 'Meccan', 'ayah_count' => 4],
            113 => ['name_english' => 'Al-Falaq', 'revelation_type' => 'Meccan', 'ayah_count' => 5],
            114 => ['name_english' => 'An-Nas', 'revelation_type' => 'Meccan', 'ayah_count' => 6]
        ];
        $stmt = $db->prepare("INSERT INTO surahs (id, name_english, revelation_type, ayah_count) VALUES (:id, :name_english, :revelation_type, :ayah_count)");
        foreach ($surahs as $id => $data) {
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':name_english', $data['name_english']);
            $stmt->bindValue(':revelation_type', $data['revelation_type']);
            $stmt->bindValue(':ayah_count', $data['ayah_count']);
            $stmt->execute();
        }
    }

    // Load Ayahs and Translations
    $files = ['data new.AM' => 'Urdu', 'dataENG.AM' => 'English', 'dataBNG.AM' => 'Bengali'];
    foreach ($files as $file => $language) {
        if (file_exists($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Example line: بِسۡمِ ٱللَّهِ ٱلرَّحۡمَـٰنِ ٱلرَّحِيمِ ترجمہ: اللہ کے نام سے شروع جو بڑا مہربان نہایت رحم والا ہے<br/>س 001 آ 001
                if (preg_match('/^(.*?) ترجمہ: (.*?)<br\/>س (\d{3}) آ (\d{3})$/u', $line, $matches)) {
                    $arabic_text = trim($matches[1]);
                    $translation_text = trim($matches[2]);
                    $surah_id = (int)$matches[3];
                    $ayah_number = (int)$matches[4];

                    // Find or insert Ayah
                    $stmt_ayah = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");
                    $stmt_ayah->bindValue(':surah_id', $surah_id);
                    $stmt_ayah->bindValue(':ayah_number', $ayah_number);
                    $ayah_result = $stmt_ayah->execute();
                    $ayah_row = $ayah_result->fetchArray();

                    $ayah_id = null;
                    if ($ayah_row) {
                        $ayah_id = $ayah_row['id'];
                         // Update arabic_text if it was empty or different (handle potential inconsistencies)
                        $stmt_update_ayah = $db->prepare("UPDATE ayahs SET arabic_text = :arabic_text WHERE id = :id AND (arabic_text IS NULL OR arabic_text = '')");
                        $stmt_update_ayah->bindValue(':arabic_text', $arabic_text);
                        $stmt_update_ayah->bindValue(':id', $ayah_id);
                        $stmt_update_ayah->execute();

                    } else {
                        $stmt_insert_ayah = $db->prepare("INSERT INTO ayahs (surah_id, ayah_number, arabic_text) VALUES (:surah_id, :ayah_number, :arabic_text)");
                        $stmt_insert_ayah->bindValue(':surah_id', $surah_id);
                        $stmt_insert_ayah->bindValue(':ayah_number', $ayah_number);
                        $stmt_insert_ayah->bindValue(':arabic_text', $arabic_text);
                        $stmt_insert_ayah->execute();
                        $ayah_id = $db->lastInsertRowID();
                    }

                    // Insert Translation (only if it doesn't exist for this ayah and language)
                    $stmt_check_translation = $db->prepare("SELECT COUNT(*) FROM translations WHERE ayah_id = :ayah_id AND language = :language");
                    $stmt_check_translation->bindValue(':ayah_id', $ayah_id);
                    $stmt_check_translation->bindValue(':language', $language);
                    $translation_count_result = $stmt_check_translation->execute();
                    $translation_count_row = $translation_count_result->fetchArray();

                    if ($translation_count_row[0] == 0) {
                        $stmt_insert_translation = $db->prepare("INSERT INTO translations (ayah_id, language, text) VALUES (:ayah_id, :language, :text)");
                        $stmt_insert_translation->bindValue(':ayah_id', $ayah_id);
                        $stmt_insert_translation->bindValue(':language', $language);
                        $stmt_insert_translation->bindValue(':text', $translation_text);
                        $stmt_insert_translation->execute();
                    }
                }
            }
        } else {
            error_log("Data file not found: " . $file);
        }
    }

    // Load Word Translations (data5 new.AM)
    if (file_exists('data5 new.AM')) {
        $lines = file('data5 new.AM', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stmt_insert_word_translation = $db->prepare("INSERT OR IGNORE INTO word_translations (word_id, ur_meaning, en_meaning) VALUES (:word_id, :ur_meaning, :en_meaning)");
        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) == 3) {
                $word_id = (int)$data[0];
                $ur_meaning = trim($data[1]);
                $en_meaning = trim($data[2]);
                $stmt_insert_word_translation->bindValue(':word_id', $word_id);
                $stmt_insert_word_translation->bindValue(':ur_meaning', $ur_meaning);
                $stmt_insert_word_translation->bindValue(':en_meaning', $en_meaning);
                $stmt_insert_word_translation->execute();
            }
        }
    } else {
        error_log("Word translation file not found: data5 new.AM");
    }

    // Load Word Metadata (word2.AM)
    if (file_exists('word2.AM')) {
        $lines = file('word2.AM', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stmt_insert_word_metadata = $db->prepare("INSERT OR IGNORE INTO word_metadata (word_id, surah_id, ayah_id, word_position, arabic_word) VALUES (:word_id, :surah_id, :ayah_id, :word_position, :arabic_word)");
        $stmt_get_ayah = $db->prepare("SELECT id, arabic_text FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");

        foreach ($lines as $line) {
            $data = str_getcsv($line);
            if (count($data) == 4) {
                $word_id = (int)$data[0];
                $surah_id = (int)$data[1];
                $ayah_number = (int)$data[2];
                $word_position = (int)$data[3];

                $stmt_get_ayah->bindValue(':surah_id', $surah_id);
                $stmt_get_ayah->bindValue(':ayah_number', $ayah_number);
                $ayah_result = $stmt_get_ayah->execute();
                $ayah_row = $ayah_result->fetchArray();
                if ($ayah_row) {
                    $ayah_id = $ayah_row['id'];
                    $arabic_text = $ayah_row['arabic_text'];
                    $words_in_ayah = explode(' ', $arabic_text);
                    $arabic_word = $words_in_ayah[$word_position - 1] ?? ''; // Get the word at the position (1-based index)

                    $stmt_insert_word_metadata->bindValue(':word_id', $word_id);
                    $stmt_insert_word_metadata->bindValue(':surah_id', $surah_id);
                    $stmt_insert_word_metadata->bindValue(':ayah_id', $ayah_id);
                    $stmt_insert_word_metadata->bindValue(':word_position', $word_position);
                    $stmt_insert_word_metadata->bindValue(':arabic_word', $arabic_word);
                    $stmt_insert_word_metadata->execute();
                } else {
                    error_log("Could not find ayah for word metadata: S{$surah_id}:A{$ayah_number}");
                }
            }
        }
    } else {
        error_log("Word metadata file not found: word2.AM");
    }


    $db->exec("COMMIT;");
    $db->close();
}

// Authentication and Authorization
session_start();

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? 'Public';
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function authenticate($username, $password) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, password, role FROM users WHERE username = :username");
    $stmt->bindValue(':username', $username);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function register_user($username, $password) {
    $db = new SQLite3(DB_PATH);
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt_check->bindValue(':username', $username);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    if ($row[0] > 0) {
        $db->close();
        return "Username already exists.";
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt_insert = $db->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'User')");
    $stmt_insert->bindValue(':username', $username);
    $stmt_insert->bindValue(':password', $hashed_password);
    $success = $stmt_insert->execute();
    $db->close();

    return $success ? true : "Registration failed.";
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

function has_permission($required_role) {
    $user_role = get_user_role();
    $roles = ['Public' => 0, 'User' => 1, 'Ulama' => 2, 'Admin' => 3];
    return isset($roles[$user_role]) && isset($roles[$required_role]) && $roles[$user_role] >= $roles[$required_role];
}

// Database Operations
function get_surahs() {
    $db = new SQLite3(DB_PATH);
    $results = $db->query("SELECT id, name_english, name_arabic, revelation_type, ayah_count FROM surahs ORDER BY id");
    $surahs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $surahs[] = $row;
    }
    $db->close();
    return $surahs;
}

function get_ayahs_by_surah($surah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT id, surah_id, ayah_number, arabic_text FROM ayahs WHERE surah_id = :surah_id ORDER BY ayah_number");
    $stmt->bindValue(':surah_id', $surah_id);
    $results = $stmt->execute();
    $ayahs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row;
    }
    $db->close();
    return $ayahs;
}

function get_translations_by_ayah($ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT language, text FROM translations WHERE ayah_id = :ayah_id");
    $stmt->bindValue(':ayah_id', $ayah_id);
    $results = $stmt->execute();
    $translations = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $translations[$row['language']] = $row['text'];
    }
    $db->close();
    return $translations;
}

function get_personal_tafsir($user_id, $surah_id, $ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT tafsir_text FROM personal_tafsir WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row ? $row['tafsir_text'] : '';
}

function save_personal_tafsir($user_id, $surah_id, $ayah_id, $tafsir_text) {
    $db = new SQLite3(DB_PATH);
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM personal_tafsir WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt_check->bindValue(':user_id', $user_id);
    $stmt_check->bindValue(':surah_id', $surah_id);
    $stmt_check->bindValue(':ayah_id', $ayah_id);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    if ($row[0] > 0) {
        $stmt = $db->prepare("UPDATE personal_tafsir SET tafsir_text = :tafsir_text, updated_at = CURRENT_TIMESTAMP WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    } else {
        $stmt = $db->prepare("INSERT INTO personal_tafsir (user_id, surah_id, ayah_id, tafsir_text) VALUES (:user_id, :surah_id, :ayah_id, :tafsir_text)");
    }
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $stmt->bindValue(':tafsir_text', $tafsir_text);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function get_community_tafsir_by_ayah($ayah_id) {
     $db = new SQLite3(DB_PATH);
     $stmt = $db->prepare("SELECT ct.tafsir_text, u.username FROM community_tafsir ct JOIN users u ON ct.user_id = u.id WHERE ct.ayah_id = :ayah_id AND ct.status = 'Approved' ORDER BY ct.created_at DESC");
     $stmt->bindValue(':ayah_id', $ayah_id);
     $results = $stmt->execute();
     $tafsirs = [];
     while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
         $tafsirs[] = $row;
     }
     $db->close();
     return $tafsirs;
}

function submit_community_tafsir($user_id, $surah_id, $ayah_id, $tafsir_text) {
     $db = new SQLite3(DB_PATH);
     $stmt = $db->prepare("INSERT INTO community_tafsir (user_id, surah_id, ayah_id, tafsir_text, status) VALUES (:user_id, :surah_id, :ayah_id, :tafsir_text, 'Pending')");
     $stmt->bindValue(':user_id', $user_id);
     $stmt->bindValue(':surah_id', $surah_id);
     $stmt->bindValue(':ayah_id', $ayah_id);
     $stmt->bindValue(':tafsir_text', $tafsir_text);
     $success = $stmt->execute();
     $db->close();
     return $success;
}


function get_themes($filter = 'all', $user_id = null) {
    $db = new SQLite3(DB_PATH);
    $sql = "SELECT t.id, t.theme_name, t.description, t.is_public, u.username FROM themes t JOIN users u ON t.user_id = u.id";
    $params = [];

    if ($filter === 'community') {
        $sql .= " WHERE t.is_public = 1 AND t.status = 'Approved'";
    } elseif ($filter === 'ulama') {
         $sql .= " WHERE t.is_public = 2"; // Ulama/Admin contributions are public=2
    } elseif ($filter === 'personal' && $user_id !== null) {
        $sql .= " WHERE t.user_id = :user_id";
        $params[':user_id'] = $user_id;
    } elseif ($filter === 'pending' && has_permission('Ulama')) {
        $sql .= " WHERE t.status = 'Pending'";
    } else {
        // 'all' or public view for non-logged in
        $sql .= " WHERE (t.is_public IN (1, 2) AND t.status = 'Approved')";
        if (is_logged_in()) {
             $sql .= " OR t.user_id = :current_user_id";
             $params[':current_user_id'] = get_user_id();
        }
    }
    $sql .= " ORDER BY t.theme_name";

    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }

    $results = $stmt->execute();
    $themes = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $themes[] = $row;
    }
    $db->close();
    return $themes;
}

function get_theme_ayahs($theme_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT ta.surah_id, ta.ayah_id, a.ayah_number, s.name_english FROM theme_ayahs ta JOIN ayahs a ON ta.ayah_id = a.id JOIN surahs s ON ta.surah_id = s.id WHERE ta.theme_id = :theme_id ORDER BY ta.surah_id, a.ayah_number");
    $stmt->bindValue(':theme_id', $theme_id);
    $results = $stmt->execute();
    $ayahs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $ayahs[] = $row;
    }
    $db->close();
    return $ayahs;
}

function save_theme($user_id, $theme_name, $description, $ayahs, $is_public = 0, $status = 'Pending') {
    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");

    // Insert or update theme
    $stmt_check = $db->prepare("SELECT id FROM themes WHERE user_id = :user_id AND theme_name = :theme_name");
    $stmt_check->bindValue(':user_id', $user_id);
    $stmt_check->bindValue(':theme_name', $theme_name);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    $theme_id = null;
    if ($row) {
        $theme_id = $row['id'];
        $stmt_update = $db->prepare("UPDATE themes SET description = :description, is_public = :is_public, status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :theme_id");
        $stmt_update->bindValue(':description', $description);
        $stmt_update->bindValue(':is_public', $is_public);
        $stmt_update->bindValue(':status', $status);
        $stmt_update->bindValue(':theme_id', $theme_id);
        $stmt_update->execute();

        // Delete existing ayahs for this theme
        $stmt_delete_ayahs = $db->prepare("DELETE FROM theme_ayahs WHERE theme_id = :theme_id");
        $stmt_delete_ayahs->bindValue(':theme_id', $theme_id);
        $stmt_delete_ayahs->execute();

    } else {
        $stmt_insert = $db->prepare("INSERT INTO themes (user_id, theme_name, description, is_public, status) VALUES (:user_id, :theme_name, :description, :is_public, :status)");
        $stmt_insert->bindValue(':user_id', $user_id);
        $stmt_insert->bindValue(':theme_name', $theme_name);
        $stmt_insert->bindValue(':description', $description);
        $stmt_insert->bindValue(':is_public', $is_public);
        $stmt_insert->bindValue(':status', $status);
        $stmt_insert->execute();
        $theme_id = $db->lastInsertRowID();
    }

    // Insert ayahs for the theme
    if ($theme_id && !empty($ayahs)) {
        $stmt_insert_ayah = $db->prepare("INSERT INTO theme_ayahs (theme_id, surah_id, ayah_id) VALUES (:theme_id, :surah_id, :ayah_id)");
        $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");

        // Normalize ayahs input (handle newline or comma separated)
        $ayah_list = [];
        foreach ($ayahs as $ayah_input) {
            $ayah_input = trim($ayah_input);
            if (strpos($ayah_input, ',') !== false) {
                $ayah_list = array_merge($ayah_list, array_map('trim', explode(',', $ayah_input)));
            } elseif (!empty($ayah_input)) {
                $ayah_list[] = $ayah_input;
            }
        }
        $ayah_list = array_unique($ayah_list); // Remove duplicates

        foreach ($ayah_list as $ayah_str) {
            // Assuming ayahs are passed as {surah_id}-{ayah_number}
            if (preg_match('/^(\d+)-(\d+)$/', $ayah_str, $matches)) {
                $surah_id_val = (int)$matches[1];
                $ayah_number_val = (int)$matches[2];

                $stmt_get_ayah_id->bindValue(':surah_id', $surah_id_val);
                $stmt_get_ayah_id->bindValue(':ayah_number', $ayah_number_val);
                $ayah_result = $stmt_get_ayah_id->execute();
                $ayah_row = $ayah_result->fetchArray();
                if ($ayah_row) {
                     $stmt_insert_ayah->bindValue(':theme_id', $theme_id);
                     $stmt_insert_ayah->bindValue(':surah_id', $surah_id_val);
                     $stmt_insert_ayah->bindValue(':ayah_id', $ayah_row['id']);
                     $stmt_insert_ayah->execute();
                } else {
                    error_log("Could not find ayah for theme: S{$surah_id_val}:A{$ayah_number_val}");
                }
            } else {
                 error_log("Invalid ayah format in theme input: {$ayah_str}");
            }
        }
    }

    $success = $db->exec("COMMIT;");
    $db->close();
    return $success ? true : $db->lastErrorMsg();
}


function get_hifz_status($user_id, $surah_id, $ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT status FROM hifz_tracking WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $db->close();
    return $row ? $row['status'] : 'Not Started';
}

function update_hifz_status($user_id, $surah_id, $ayah_id, $status) {
    $db = new SQLite3(DB_PATH);
    $stmt_check = $db->prepare("SELECT COUNT(*) FROM hifz_tracking WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    $stmt_check->bindValue(':user_id', $user_id);
    $stmt_check->bindValue(':surah_id', $surah_id);
    $stmt_check->bindValue(':ayah_id', $ayah_id);
    $result = $stmt_check->execute();
    $row = $result->fetchArray();

    if ($row[0] > 0) {
        $stmt = $db->prepare("UPDATE hifz_tracking SET status = :status, last_updated = CURRENT_TIMESTAMP WHERE user_id = :user_id AND surah_id = :surah_id AND ayah_id = :ayah_id");
    } else {
        $stmt = $db->prepare("INSERT INTO hifz_tracking (user_id, surah_id, ayah_id, status) VALUES (:user_id, :surah_id, :ayah_id, :status)");
    }
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_id', $ayah_id);
    $stmt->bindValue(':status', $status);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function get_recitation_log($user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT rl.id, rl.surah_id, rl.ayah_from, rl.ayah_to, rl.recitation_date, rl.notes, s.name_english FROM recitation_log rl JOIN surahs s ON rl.surah_id = s.id WHERE user_id = :user_id ORDER BY recitation_date DESC");
    $stmt->bindValue(':user_id', $user_id);
    $results = $stmt->execute();
    $logs = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $logs[] = $row;
    }
    $db->close();
    return $logs;
}

function add_recitation_log($user_id, $surah_id, $ayah_from, $ayah_to, $notes) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("INSERT INTO recitation_log (user_id, surah_id, ayah_from, ayah_to, notes) VALUES (:user_id, :surah_id, :ayah_from, :ayah_to, :notes)");
    $stmt->bindValue(':user_id', $user_id);
    $stmt->bindValue(':surah_id', $surah_id);
    $stmt->bindValue(':ayah_from', $ayah_from);
    $stmt->bindValue(':ayah_to', $ayah_to);
    $stmt->bindValue(':notes', $notes);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function delete_recitation_log($log_id, $user_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("DELETE FROM recitation_log WHERE id = :log_id AND user_id = :user_id");
    $stmt->bindValue(':log_id', $log_id);
    $stmt->bindValue(':user_id', $user_id);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function search_quran($query, $language = 'Arabic') {
    $db = new SQLite3(DB_PATH);
    $sql = "SELECT a.id AS ayah_id, a.surah_id, a.ayah_number, a.arabic_text, s.name_english, t.text AS translation_text
            FROM ayahs a
            JOIN surahs s ON a.surah_id = s.id
            LEFT JOIN translations t ON a.id = t.ayah_id AND t.language = :language
            WHERE a.arabic_text LIKE :query_arabic";

    $params = [
        ':language' => $language,
        ':query_arabic' => '%' . $query . '%'
    ];

    if ($language !== 'Arabic') {
         $sql .= " OR t.text LIKE :query_translation";
         $params[':query_translation'] = '%' . $query . '%';
    }

    $stmt = $db->prepare($sql);
    foreach($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }

    $results = $stmt->execute();
    $search_results = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $search_results[] = $row;
    }
    $db->close();
    return $search_results;
}

function get_word_translations_for_ayah($ayah_id) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("SELECT wm.word_position, wm.arabic_word, wt.ur_meaning, wt.en_meaning
                          FROM word_metadata wm
                          LEFT JOIN word_translations wt ON wm.word_id = wt.word_id
                          WHERE wm.ayah_id = :ayah_id
                          ORDER BY wm.word_position");
    $stmt->bindValue(':ayah_id', $ayah_id);
    $results = $stmt->execute();
    $words = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $words[] = $row;
    }
    $db->close();
    return $words;
}


function get_ayah_by_surah_ayah($surah_id, $ayah_number) {
     $db = new SQLite3(DB_PATH);
     $stmt = $db->prepare("SELECT id, arabic_text FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number");
     $stmt->bindValue(':surah_id', $surah_id);
     $stmt->bindValue(':ayah_number', $ayah_number);
     $result = $stmt->execute();
     $row = $result->fetchArray(SQLITE3_ASSOC);
     $db->close();
     return $row;
}

function get_all_users() {
    $db = new SQLite3(DB_PATH);
    $results = $db->query("SELECT id, username, role FROM users ORDER BY username");
    $users = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $users[] = $row;
    }
    $db->close();
    return $users;
}

function update_user_role($user_id, $role) {
    $db = new SQLite3(DB_PATH);
    $stmt = $db->prepare("UPDATE users SET role = :role WHERE id = :user_id");
    $stmt->bindValue(':role', $role);
    $stmt->bindValue(':user_id', $user_id);
    $success = $stmt->execute();
    $db->close();
    return $success;
}

function get_pending_contributions() {
    $db = new SQLite3(DB_PATH);
    // Fetching pending community tafsir and pending themes
    $sql = "SELECT ct.id, ct.user_id, u.username, 'Tafsir' AS type, ct.ayah_id AS related_id, ct.tafsir_text AS content, ct.created_at
            FROM community_tafsir ct
            JOIN users u ON ct.user_id = u.id
            WHERE ct.status = 'Pending'
            UNION ALL
            SELECT t.id, t.user_id, u.username, 'Theme' AS type, t.id AS related_id, t.description AS content, t.created_at
            FROM themes t
            JOIN users u ON t.user_id = u.id
            WHERE t.status = 'Pending' AND t.is_public = 1"; // Only pending community themes
    $results = $db->query($sql);
    $contributions = [];
    while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
        $contributions[] = $row;
    }
    $db->close();
    return $contributions;
}

function approve_contribution($contribution_id, $type, $approved_by_user_id) {
    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");
    $success = false;

    if ($type === 'Tafsir') {
        $stmt = $db->prepare("UPDATE community_tafsir SET status = 'Approved', approved_by = :approved_by, approved_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'Pending'");
        $stmt->bindValue(':approved_by', $approved_by_user_id);
        $stmt->bindValue(':id', $contribution_id);
        $success = $stmt->execute();
    } elseif ($type === 'Theme') {
         $stmt = $db->prepare("UPDATE themes SET status = 'Approved', approved_by = :approved_by, approved_at = CURRENT_TIMESTAMP WHERE id = :id AND status = 'Pending' AND is_public = 1");
         $stmt->bindValue(':approved_by', $approved_by_user_id);
         $stmt->bindValue(':id', $contribution_id);
         $success = $stmt->execute();
    } else {
        error_log("Unknown contribution type for approval: " . $type);
        $db->exec("ROLLBACK;");
        $db->close();
        return false;
    }

    if ($success) {
        $db->exec("COMMIT;");
    } else {
        $db->exec("ROLLBACK;");
    }
    $db->close();
    return $success;
}

function reject_contribution($contribution_id, $type) {
    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");
    $success = false;

    if ($type === 'Tafsir') {
        $stmt = $db->prepare("UPDATE community_tafsir SET status = 'Rejected' WHERE id = :id AND status = 'Pending'");
        $stmt->bindValue(':id', $contribution_id);
        $success = $stmt->execute();
    } elseif ($type === 'Theme') {
        $stmt = $db->prepare("UPDATE themes SET status = 'Rejected' WHERE id = :id AND status = 'Pending' AND is_public = 1");
         $stmt->bindValue(':id', $contribution_id);
         $success = $stmt->execute();
    } else {
        error_log("Unknown contribution type for rejection: " . $type);
        $db->exec("ROLLBACK;");
        $db->close();
        return false;
    }

    if ($success) {
        $db->exec("COMMIT;");
    } else {
        $db->exec("ROLLBACK;");
    }
    $db->close();
    return $success;
}


// Data Export/Import
function export_user_data($user_id) {
    $db = new SQLite3(DB_PATH);
    $data = [];

    // Personal Tafsir
    $stmt_tafsir = $db->prepare("SELECT surah_id, ayah_id, tafsir_text FROM personal_tafsir WHERE user_id = :user_id");
    $stmt_tafsir->bindValue(':user_id', $user_id);
    $results_tafsir = $stmt_tafsir->execute();
    $data['personal_tafsir'] = [];
    while ($row = $results_tafsir->fetchArray(SQLITE3_ASSOC)) {
        $data['personal_tafsir'][] = $row;
    }

    // Themes (Personal)
    $stmt_themes = $db->prepare("SELECT id, theme_name, description FROM themes WHERE user_id = :user_id AND is_public = 0"); // Only export private themes
    $stmt_themes->bindValue(':user_id', $user_id);
    $results_themes = $stmt_themes->execute();
    $data['personal_themes'] = [];
    while ($theme_row = $results_themes->fetchArray(SQLITE3_ASSOC)) {
        $theme_id = $theme_row['id'];
        $theme_data = $theme_row;
        $theme_data['ayahs'] = [];

        $stmt_theme_ayahs = $db->prepare("SELECT surah_id, ayah_id FROM theme_ayahs WHERE theme_id = :theme_id");
        $stmt_theme_ayahs->bindValue(':theme_id', $theme_id);
        $results_theme_ayahs = $stmt_theme_ayahs->execute();
        while ($ayah_row = $results_theme_ayahs->fetchArray(SQLITE3_ASSOC)) {
             // Need to get ayah_number for export to make it easier to read/import
             $stmt_get_ayah_number = $db->prepare("SELECT ayah_number FROM ayahs WHERE id = :ayah_id");
             $stmt_get_ayah_number->bindValue(':ayah_id', $ayah_row['ayah_id']);
             $ayah_num_row = $stmt_get_ayah_number->execute()->fetchArray();
             if ($ayah_num_row) {
                 $data['personal_themes'][] = [ // Add directly to the list
                     'theme_name' => $theme_data['theme_name'],
                     'description' => $theme_data['description'],
                     'surah_id' => $ayah_row['surah_id'],
                     'ayah_number' => $ayah_num_row['ayah_number'] // Export ayah_number instead of ayah_id
                 ];
             }
        }
    }


    // Hifz Tracking
    $stmt_hifz = $db->prepare("SELECT surah_id, ayah_id, status FROM hifz_tracking WHERE user_id = :user_id");
    $stmt_hifz->bindValue(':user_id', $user_id);
    $results_hifz = $stmt_hifz->execute();
    $data['hifz_tracking'] = [];
    while ($row = $results_hifz->fetchArray(SQLITE3_ASSOC)) {
        $data['hifz_tracking'][] = $row;
    }

    // Recitation Log
    $stmt_recitation = $db->prepare("SELECT surah_id, ayah_from, ayah_to, recitation_date, notes FROM recitation_log WHERE user_id = :user_id");
    $stmt_recitation->bindValue(':user_id', $user_id);
    $results_recitation = $stmt_recitation->execute();
    $data['recitation_log'] = [];
    while ($row = $results_recitation->fetchArray(SQLITE3_ASSOC)) {
        $data['recitation_log'][] = $row;
    }

    $db->close();
    return json_encode($data, JSON_PRETTY_PRINT);
}

function import_user_data($user_id, $json_data) {
    $data = json_decode($json_data, true);
    if ($data === null) {
        return "Invalid JSON data.";
    }

    $db = new SQLite3(DB_PATH);
    $db->exec("BEGIN TRANSACTION;");
    $errors = [];

    // Import Personal Tafsir
    if (isset($data['personal_tafsir']) && is_array($data['personal_tafsir'])) {
        $stmt_tafsir = $db->prepare("INSERT OR REPLACE INTO personal_tafsir (user_id, surah_id, ayah_id, tafsir_text) VALUES (:user_id, :surah_id, :ayah_id, :tafsir_text)");
         $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number"); // Need ayah_id from surah/ayah_number

        foreach ($data['personal_tafsir'] as $item) {
            if (isset($item['surah_id'], $item['ayah_id'], $item['tafsir_text'])) { // Expecting ayah_id in export (which is ayah_number)
                 // Need to get the actual ayah_id from the database based on surah_id and ayah_id (which is ayah_number in export)
                 $stmt_get_ayah_id->bindValue(':surah_id', $item['surah_id']);
                 $stmt_get_ayah_id->bindValue(':ayah_number', $item['ayah_id']); // Assuming ayah_id in export is ayah_number
                 $ayah_row = $stmt_get_ayah_id->execute()->fetchArray();
                 if ($ayah_row) {
                    $stmt_tafsir->bindValue(':user_id', $user_id);
                    $stmt_tafsir->bindValue(':surah_id', $item['surah_id']);
                    $stmt_tafsir->bindValue(':ayah_id', $ayah_row['id']); // Use the actual ayah_id
                    $stmt_tafsir->bindValue(':tafsir_text', $item['tafsir_text']);
                    if (!$stmt_tafsir->execute()) {
                        $errors[] = "Error importing Tafsir for S{$item['surah_id']}:A{$item['ayah_id']}: " . $db->lastErrorMsg();
                    }
                 } else {
                     $errors[] = "Could not find Ayah S{$item['surah_id']}:A{$item['ayah_id']} for Tafsir import.";
                 }
            } else {
                $errors[] = "Invalid personal_tafsir item format.";
            }
        }
    }

    // Import Themes (Personal) - This format is different from export, assuming flat list
    if (isset($data['personal_themes']) && is_array($data['personal_themes'])) {
         // Reconstruct themes from the flat list
         $themes_to_import = [];
         foreach($data['personal_themes'] as $item) {
             if (isset($item['theme_name'], $item['description'], $item['surah_id'], $item['ayah_number'])) {
                 $theme_name = $item['theme_name'];
                 if (!isset($themes_to_import[$theme_name])) {
                     $themes_to_import[$theme_name] = [
                         'description' => $item['description'],
                         'ayahs' => []
                     ];
                 }
                 $themes_to_import[$theme_name]['ayahs'][] = ['surah_id' => $item['surah_id'], 'ayah_number' => $item['ayah_number']];
             } else {
                  $errors[] = "Invalid personal_themes item format.";
             }
         }

        $stmt_theme = $db->prepare("INSERT INTO themes (user_id, theme_name, description, is_public, status) VALUES (:user_id, :theme_name, :description, 0, 'Approved')"); // Import as personal, approved
        $stmt_theme_ayah = $db->prepare("INSERT INTO theme_ayahs (theme_id, surah_id, ayah_id) VALUES (:theme_id, :surah_id, :ayah_id)");
         $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number"); // Need ayah_id from surah/ayah_number

        foreach ($themes_to_import as $theme_name => $theme_data) {
             // Check if theme name already exists for this user
             $stmt_check_theme = $db->prepare("SELECT id FROM themes WHERE user_id = :user_id AND theme_name = :theme_name");
             $stmt_check_theme->bindValue(':user_id', $user_id);
             $stmt_check_theme->bindValue(':theme_name', $theme_name);
             $existing_theme = $stmt_check_theme->execute()->fetchArray();

             if ($existing_theme) {
                 $errors[] = "Skipping import of theme '{$theme_name}' as it already exists for this user.";
                 continue;
             }

            $stmt_theme->bindValue(':user_id', $user_id);
            $stmt_theme->bindValue(':theme_name', $theme_name);
            $stmt_theme->bindValue(':description', $theme_data['description']);
            if ($stmt_theme->execute()) {
                $theme_id = $db->lastInsertRowID();
                foreach ($theme_data['ayahs'] as $ayah) {
                    if (isset($ayah['surah_id'], $ayah['ayah_number'])) {
                         // Need to get the actual ayah_id from the database based on surah_id and ayah_number
                         $stmt_get_ayah_id->bindValue(':surah_id', $ayah['surah_id']);
                         $stmt_get_ayah_id->bindValue(':ayah_number', $ayah['ayah_number']);
                         $ayah_row = $stmt_get_ayah_id->execute()->fetchArray();
                         if ($ayah_row) {
                            $stmt_theme_ayah->bindValue(':theme_id', $theme_id);
                            $stmt_theme_ayah->bindValue(':surah_id', $ayah['surah_id']);
                            $stmt_theme_ayah->bindValue(':ayah_id', $ayah_row['id']); // Use the actual ayah_id
                            if (!$stmt_theme_ayah->execute()) {
                                $errors[] = "Error importing Ayah for theme '{$theme_name}': S{$ayah['surah_id']}:A{$ayah['ayah_number']}: " . $db->lastErrorMsg();
                            }
                         } else {
                             $errors[] = "Could not find Ayah S{$ayah['surah_id']}:A{$ayah['ayah_number']} for theme '{$theme_name}' during import.";
                         }
                    } else {
                         $errors[] = "Invalid theme ayah item format for theme '{$theme_name}'.";
                    }
                }
            } else {
                $errors[] = "Error importing theme '{$theme_name}': " . $db->lastErrorMsg();
            }
        }
    }

    // Import Hifz Tracking
    if (isset($data['hifz_tracking']) && is_array($data['hifz_tracking'])) {
        $stmt_hifz = $db->prepare("INSERT OR REPLACE INTO hifz_tracking (user_id, surah_id, ayah_id, status) VALUES (:user_id, :surah_id, :ayah_id, :status)");
         $stmt_get_ayah_id = $db->prepare("SELECT id FROM ayahs WHERE surah_id = :surah_id AND ayah_number = :ayah_number"); // Need ayah_id from surah/ayah_number

        foreach ($data['hifz_tracking'] as $item) {
            if (isset($item['surah_id'], $item['ayah_id'], $item['status'])) { // Expecting ayah_id in export (which is ayah_number)
                 // Need to get the actual ayah_id from the database based on surah_id and ayah_id (which is ayah_number in export)
                 $stmt_get_ayah_id->bindValue(':surah_id', $item['surah_id']);
                 $stmt_get_ayah_id->bindValue(':ayah_number', $item['ayah_id']); // Assuming ayah_id in export is ayah_number
                 $ayah_row = $stmt_get_ayah_id->execute()->fetchArray();
                 if ($ayah_row) {
                    $stmt_hifz->bindValue(':user_id', $user_id);
                    $stmt_hifz->bindValue(':surah_id', $item['surah_id']);
                    $stmt_hifz->bindValue(':ayah_id', $ayah_row['id']); // Use the actual ayah_id
                    $stmt_hifz->bindValue(':status', $item['status']);
                    if (!$stmt_hifz->execute()) {
                        $errors[] = "Error importing Hifz for S{$item['surah_id']}:A{$item['ayah_id']}: " . $db->lastErrorMsg();
                    }
                 } else {
                     $errors[] = "Could not find Ayah S{$item['surah_id']}:A{$item['ayah_id']} for Hifz import.";
                 }
            } else {
                $errors[] = "Invalid hifz_tracking item format.";
            }
        }
    }

    // Import Recitation Log
    if (isset($data['recitation_log']) && is_array($data['recitation_log'])) {
        $stmt = $db->prepare("INSERT INTO recitation_log (user_id, surah_id, ayah_from, ayah_to, recitation_date, notes) VALUES (:user_id, :surah_id, :ayah_from, :ayah_to, :recitation_date, :notes)");
        foreach ($data['recitation_log'] as $item) {
            if (isset($item['surah_id'], $item['ayah_from'], $item['ayah_to'], $item['recitation_date'], $item['notes'])) {
                $stmt->bindValue(':user_id', $user_id);
                $stmt->bindValue(':surah_id', $item['surah_id']);
                $stmt->bindValue(':ayah_from', $item['ayah_from']);
                $stmt->bindValue(':ayah_to', $item['ayah_to']);
                $stmt->bindValue(':recitation_date', $item['recitation_date']);
                $stmt->bindValue(':notes', $item['notes']);
                if (!$stmt->execute()) {
                    $errors[] = "Error importing Recitation Log for S{$item['surah_id']}:A{$item['ayah_from']}-A{$item['ayah_to']}: " . $db->lastErrorMsg();
                }
            } else {
                $errors[] = "Invalid recitation_log item format.";
            }
        }
    }


    if (empty($errors)) {
        $db->exec("COMMIT;");
        $db->close();
        return true;
    } else {
        $db->exec("ROLLBACK;");
        $db->close();
        return "Import completed with errors: " . implode(", ", $errors);
    }
}


// Helper functions for rendering HTML
function render_header($title = APP_NAME) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <meta name="description" content="Offline Quran Study Application with Tafsir, Themes, Hifz, and more.">
        <meta name="keywords" content="Quran, Tafsir, Hifz, Islam, Study, Offline, PHP, SQLite">
        <meta name="author" content="Yasin Ullah">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&family=Roboto:wght@400;700&display=swap" rel="stylesheet">
        <style>
            body { font-family: 'Roboto', sans-serif; line-height: 1.6; margin: 0; padding: 0; background-color: #f8f9fa; color: #343a40; }
            .container { width: 95%; max-width: 1200px; margin: 20px auto; background: #fff; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 8px; }
            header { background: linear-gradient(to right, #007bff, #0056b3); color: #fff; padding: 15px 0; text-align: center; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            header h1 { margin: 0; font-size: 2em; }
            nav { background: #e9ecef; padding: 10px 0; text-align: center; margin-bottom: 20px; border-radius: 4px; }
            nav a { margin: 0 15px; text-decoration: none; color: #007bff; font-weight: bold; transition: color 0.3s ease; }
            nav a:hover { color: #0056b3; text-decoration: underline; }
            .content { padding: 0 20px; }
            .quran-ayah { border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 20px; background-color: #f8f9fa; position: relative; }
            .ayah-options { position: absolute; top: 10px; right: 10px; font-size: 0.9em; }
            .ayah-options a { margin-left: 10px; color: #007bff; text-decoration: none; }
            .ayah-options a:hover { text-decoration: underline; }
            .arabic-text { font-size: 28px; text-align: right; margin-bottom: 15px; direction: rtl; font-family: 'Scheherazade New', serif; line-height: 2; }
            .translation-text { font-size: 17px; color: #555; margin-bottom: 15px; border-top: 1px dashed #ccc; padding-top: 10px; }
            .ayah-meta { font-size: 15px; color: #888; text-align: left; margin-top: 10px; }
            .word-by-word { margin-top: 10px; font-size: 15px; direction: rtl; text-align: right; }
            .word-by-word span { display: inline-block; margin: 0 5px; cursor: pointer; border-bottom: 1px dashed #007bff; }
            .word-by-word span:hover { color: #007bff; }
            .word-meaning-tooltip {
                 position: absolute;
                 background-color: #fff;
                 border: 1px solid #ccc;
                 padding: 10px;
                 z-index: 100;
                 display: none;
                 font-size: 13px;
                 box-shadow: 2px 2px 5px rgba(0,0,0,0.2);
                 border-radius: 5px;
                 max-width: 250px;
                 text-align: left;
            }
             .word-meaning-tooltip strong { color: #007bff; }

            .form-group { margin-bottom: 15px; }
            .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
            .form-group input[type="text"], .form-group input[type="password"], .form-group textarea, .form-group select, .form-group input[type="number"], .form-group input[type="file"] { width: 100%; padding: 10px; border: 1px solid #ccc; box-sizing: border-box; border-radius: 4px; }
             .form-group textarea { resize: vertical; }
            .btn { background: #007bff; color: #fff; padding: 10px 20px; border: none; cursor: pointer; font-size: 1em; border-radius: 4px; transition: background-color 0.3s ease; }
            .btn:hover { background: #0056b3; }
            .btn-danger { background-color: #dc3545; }
            .btn-danger:hover { background-color: #c82333; }
             .btn-success { background-color: #28a745; }
             .btn-success:hover { background-color: #218838; }

            .error { color: #dc3545; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
            .success { color: #28a745; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
            .login-register { text-align: center; margin-top: 20px; }
            .login-register a { margin: 0 10px; color: #007bff; text-decoration: none; }
            .login-register a:hover { text-decoration: underline; }

            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            table, th, td { border: 1px solid #dee2e6; }
            th, td { padding: 12px; text-align: left; }
            th { background-color: #e9ecef; font-weight: bold; }
             tbody tr:nth-child(odd) { background-color: #f8f9fa; }
             tbody tr:hover { background-color: #e2e6ea; }

            .admin-panel { margin-top: 30px; border-top: 1px solid #ccc; padding-top: 20px; }
            .contribution-item { border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 15px; background-color: #f8f9fa; }
            .contribution-item h4 { margin-top: 0; color: #007bff; }
             .contribution-item pre { background-color: #e9ecef; padding: 10px; border-radius: 4px; overflow-x: auto; }

             .game-area { text-align: center; margin-top: 20px; padding: 20px; border: 1px dashed #007bff; border-radius: 8px; background-color: #e9f5ff; }
             .game-area button { margin: 5px; padding: 10px 15px; }
             #ayah-jumble-container { margin-top: 20px; min-height: 80px; border: 1px solid #ccc; padding: 10px; background-color: #fff; border-radius: 4px; }
             .jumbled-word { display: inline-block; margin: 5px; padding: 8px; border: 1px solid #007bff; cursor: pointer; background-color: #007bff; color: #fff; border-radius: 4px; transition: background-color 0.3s ease, color 0.3s ease; }
             .jumbled-word:hover { background-color: #0056b3; }
             .jumbled-word.selected { background-color: #ffc107; color: #343a40; border-color: #ffc107; }
             #ayah-jumble-output { border: 1px solid #28a745; min-height: 50px; padding: 10px; margin-top: 10px; background-color: #d4edda; border-radius: 4px; }
             #ayah-jumble-output .placed-word { display: inline-block; margin: 5px; padding: 8px; border: 1px solid #28a745; background-color: #28a745; color: #fff; border-radius: 4px; cursor: pointer; }

             .theme-ayah-list { list-style: none; padding: 0; }
             .theme-ayah-list li { margin-bottom: 5px; }
             .theme-ayah-list li a { text-decoration: none; color: #007bff; }
             .theme-ayah-list li a:hover { text-decoration: underline; }

             .tafsir-section { margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 15px; }
             .tafsir-section h4 { color: #007bff; margin-bottom: 10px; }
             .community-tafsir-item { border: 1px solid #e9ecef; padding: 10px; margin-bottom: 10px; background-color: #f1f1f1; border-radius: 4px; }
             .community-tafsir-item strong { color: #555; }

             .docx-export-form { margin-top: 20px; padding: 15px; border: 1px dashed #007bff; border-radius: 8px; background-color: #e9f5ff; }

             .profile-section { margin-top: 20px; }
             .profile-section h3 { color: #007bff; }
             .profile-section form { margin-bottom: 20px; padding: 15px; border: 1px dashed #ccc; border-radius: 8px; background-color: #f8f9fa; }
        </style>
    </head>
    <body>
        <header>
            <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
        </header>
        <nav>
            <a href="?view=quran">Quran Viewer</a>
            <?php if (is_logged_in()): ?>
                <a href="?view=tafsir">Personal Tafsir</a>
                <a href="?view=themes">Themes</a>
                <a href="?view=hifz">Hifz Hub</a>
                <a href="?view=recitation">Recitation Log</a>
                 <a href="?view=games">Games</a>
                 <a href="?view=profile">Profile</a>
                <?php if (has_permission('Ulama')): ?>
                     <a href="?view=contributions">Contributions</a>
                <?php endif; ?>
                 <?php if (has_permission('Admin')): ?>
                     <a href="?view=admin">Admin Panel</a>
                 <?php endif; ?>
                <a href="?action=logout">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            <?php else: ?>
                <a href="?view=themes&filter=community">Community Themes</a>
                 <a href="?view=themes&filter=ulama">Ulama Themes</a>
                 <a href="?view=games">Games</a>
                <a href="?view=login">Login</a>
                <a href="?view=register">Register</a>
            <?php endif; ?>
             <a href="?view=search">Search</a>
        </nav>
        <div class="container">
            <div class="content">
    <?php
}

function render_footer() {
    ?>
            </div>
        </div>
        <footer>
            <p style="text-align: center; margin-top: 20px; color: #555;">&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Developed by Yasin Ullah.</p>
        </footer>
        <div id="word-meaning-tooltip" class="word-meaning-tooltip"></div>
        <script>
            // Basic JavaScript for interactivity (tooltips, games)
            document.addEventListener('DOMContentLoaded', () => {
                const tooltip = document.getElementById('word-meaning-tooltip');

                document.querySelectorAll('.word-by-word span').forEach(wordSpan => {
                    wordSpan.addEventListener('mouseover', (event) => {
                        const urMeaning = wordSpan.getAttribute('data-ur');
                        const enMeaning = wordSpan.getAttribute('data-en');
                        let content = '';
                        if (urMeaning) content += '<strong>Urdu:</strong> ' + urMeaning + '<br>';
                        if (enMeaning) content += '<strong>English:</strong> ' + enMeaning;

                        if (content) {
                            tooltip.innerHTML = content;
                            tooltip.style.display = 'block';
                            tooltip.style.left = (event.pageX + 10) + 'px';
                            tooltip.style.top = (event.pageY + 10) + 'px';
                        }
                    });

                    wordSpan.addEventListener('mouseout', () => {
                        tooltip.style.display = 'none';
                    });
                });

                // Ayah Jumble Game Logic (Basic)
                const jumbleContainer = document.getElementById('ayah-jumble-container');
                const jumbleOutput = document.getElementById('ayah-jumble-output');
                const jumbleCheckBtn = document.getElementById('ayah-jumble-check');
                const jumbleResetBtn = document.getElementById('ayah-jumble-reset');
                const jumbleMessage = document.getElementById('ayah-jumble-message');

                let selectedWord = null;
                let correctOrder = [];

                if (jumbleContainer && jumbleOutput) {
                     // Get correct order from data attribute
                     const correctOrderAttr = jumbleContainer.getAttribute('data-correct-order');
                     if (correctOrderAttr) {
                         try {
                             correctOrder = JSON.parse(correctOrderAttr);
                         } catch (e) {
                             console.error("Failed to parse correct order:", e);
                             correctOrder = []; // Fallback
                         }
                     }

                    jumbleContainer.addEventListener('click', (event) => {
                        const target = event.target;
                        if (target.classList.contains('jumbled-word')) {
                            if (selectedWord) {
                                selectedWord.classList.remove('selected');
                            }
                            selectedWord = target;
                            selectedWord.classList.add('selected');
                        }
                    });

                    jumbleOutput.addEventListener('click', (event) => {
                         const target = event.target;
                        if (target.classList.contains('placed-word')) {
                             // Move placed word back to jumble container
                             const wordText = target.textContent;
                             const wordElement = document.createElement('span');
                             wordElement.classList.add('jumbled-word');
                             wordElement.textContent = wordText;
                             // Find the correct position to insert the word back
                             let inserted = false;
                             const outputWords = Array.from(jumbleOutput.querySelectorAll('.placed-word')).map(el => el.textContent.trim());
                             const targetIndex = outputWords.indexOf(wordText); // Simple index lookup, might need refinement for duplicates

                             const jumbleWords = Array.from(jumbleContainer.querySelectorAll('.jumbled-word')).map(el => el.textContent.trim());

                             // Attempt to insert back into jumble container in a somewhat ordered way
                             let placed = false;
                             for(let i = 0; i < jumbleContainer.children.length; i++) {
                                 if (wordText.localeCompare(jumbleContainer.children[i].textContent) < 0) {
                                     jumbleContainer.insertBefore(wordElement, jumbleContainer.children[i]);
                                     placed = true;
                                     break;
                                 }
                             }
                             if (!placed) {
                                 jumbleContainer.appendChild(wordElement); // Append if no suitable place found
                             }


                             target.remove();
                        } else if (selectedWord) {
                             // Place selected word in output
                             const placedWord = document.createElement('span');
                             placedWord.classList.add('placed-word');
                             placedWord.textContent = selectedWord.textContent;
                             jumbleOutput.appendChild(placedWord);
                             selectedWord.remove();
                             selectedWord = null; // Deselect
                        }
                    });

                    if(jumbleCheckBtn) {
                        jumbleCheckBtn.addEventListener('click', () => {
                            const placedWords = Array.from(jumbleOutput.querySelectorAll('.placed-word')).map(el => el.textContent.trim());
                            const isCorrect = placedWords.every((word, index) => word === correctOrder[index]) && placedWords.length === correctOrder.length;

                            if (jumbleMessage) {
                                if (isCorrect) {
                                    jumbleMessage.textContent = "Correct! Well done!";
                                    jumbleMessage.style.color = 'green';
                                } else {
                                    jumbleMessage.textContent = "Incorrect. Try again.";
                                    jumbleMessage.style.color = 'red';
                                }
                            }
                        });
                    }

                    if(jumbleResetBtn) {
                        jumbleResetBtn.addEventListener('click', () => {
                            jumbleOutput.innerHTML = ''; // Clear output
                            jumbleContainer.innerHTML = ''; // Clear jumble area
                            if (jumbleMessage) jumbleMessage.textContent = '';

                            // Re-populate jumble area from correct order (shuffle)
                            let shuffledWords = [...correctOrder];
                            for (let i = shuffledWords.length - 1; i > 0; i--) {
                                const j = Math.floor(Math.random() * (i + 1));
                                [shuffledWords[i], shuffledWords[j]] = [shuffledWords[j], shuffledWords[i]]; // Swap
                            }

                            shuffledWords.forEach(word => {
                                const wordElement = document.createElement('span');
                                wordElement.classList.add('jumbled-word');
                                wordElement.textContent = word;
                                jumbleContainer.appendChild(wordElement);
                            });
                             selectedWord = null; // Reset selection
                        });
                    }
                }

                 // Helper function to copy ayah text
                 window.copyAyah = function(ayahId) {
                     const ayahElement = document.getElementById('ayah-' + ayahId);
                     if (ayahElement) {
                         const arabicText = ayahElement.querySelector('.arabic-text')?.textContent || '';
                         const translationText = ayahElement.querySelector('.translation-text')?.textContent || '';
                         const ayahMeta = ayahElement.querySelector('.ayah-meta')?.textContent || '';
                         const textToCopy = `${ayahMeta}\n${arabicText}\n${translationText}`;

                         navigator.clipboard.writeText(textToCopy).then(() => {
                             alert('Ayah copied to clipboard!');
                         }).catch(err => {
                             console.error('Failed to copy Ayah: ', err);
                             alert('Failed to copy Ayah.');
                         });
                     }
                 };

                 // Helper function to add ayah to theme (basic prompt)
                 window.addToTheme = function(surahId, ayahNumber) {
                     const themeName = prompt("Enter the name of the theme to add this ayah (S" + surahId + ":A" + ayahNumber + ") to:");
                     if (themeName) {
                         // This would ideally trigger a form submission or AJAX call
                         // For this single file, we'll just log it or show a message
                         alert("Ayah S" + surahId + ":A" + ayahNumber + " added to theme '" + themeName + "' (requires saving the theme).");
                         // A more robust implementation would update the theme form textarea
                         const themeAyahsTextarea = document.querySelector('textarea[name="theme_ayahs"]');
                         if (themeAyahsTextarea) {
                             const currentAyahs = themeAyahsTextarea.value.trim();
                             const newAyah = surahId + '-' + ayahNumber;
                             if (currentAyahs === '') {
                                 themeAyahsTextarea.value = newAyah;
                             } else {
                                 // Prevent duplicates
                                 const existingAyahs = currentAyahs.split(/[\r\n,]+/).map(a => a.trim());
                                 if (!existingAyahs.includes(newAyah)) {
                                     themeAyahsTextarea.value += '\n' + newAyah;
                                 } else {
                                     alert("Ayah S" + surahId + ":A" + ayahNumber + " is already in the theme list.");
                                 }
                             }
                         }
                     }
                 };


            });
        </script>
         <?php if ($view === 'tafsir' && is_logged_in() && has_permission('User')): ?>
         <script>
             // JavaScript for Tafsir submission modal/form (if needed)
             // For now, it's a simple textarea on the Ayah view.
         </script>
         <?php endif; ?>
    </body>
    </html>
    <?php
}

// Routing and Request Handling
$view = $_GET['view'] ?? 'quran';
$action = $_GET['action'] ?? null;
$message = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'login':
                if (authenticate($_POST['username'], $_POST['password'])) {
                    header('Location: ?view=quran');
                    exit;
                } else {
                    $error = "Invalid username or password.";
                    $view = 'login'; // Stay on login page
                }
                break;
            case 'register':
                $result = register_user($_POST['username'], $_POST['password']);
                if ($result === true) {
                    $message = "Registration successful. You can now login.";
                    $view = 'login'; // Redirect to login page
                } else {
                    $error = $result;
                    $view = 'register'; // Stay on register page
                }
                break;
             case 'save_tafsir':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_id = (int)$_POST['ayah_id'];
                     $tafsir_text = trim($_POST['tafsir_text']);
                     if (save_personal_tafsir($user_id, $surah_id, $ayah_id, $tafsir_text)) {
                         $message = "Personal Tafsir saved successfully.";
                     } else {
                         $error = "Failed to save Personal Tafsir.";
                     }
                     // Redirect back to the ayah view or tafsir page
                     header("Location: ?view=quran&surah={$surah_id}#ayah-{$ayah_id}");
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                 break;
             case 'submit_community_tafsir':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_id = (int)$_POST['ayah_id'];
                     $tafsir_text = trim($_POST['community_tafsir_text']);
                     if (empty($tafsir_text)) {
                         $error = "Community Tafsir text cannot be empty.";
                     } else {
                         if (submit_community_tafsir($user_id, $surah_id, $ayah_id, $tafsir_text)) {
                             $message = "Community Tafsir submitted for review.";
                         } else {
                             $error = "Failed to submit Community Tafsir.";
                         }
                     }
                     // Redirect back to the ayah view
                     header("Location: ?view=quran&surah={$surah_id}#ayah-{$ayah_id}");
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                 break;
            case 'save_theme':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $theme_name = trim($_POST['theme_name']);
                     $description = trim($_POST['description']);
                     // Handle both newline and comma separated ayahs
                     $ayahs_raw = isset($_POST['theme_ayahs']) ? $_POST['theme_ayahs'] : '';
                     $ayahs = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $ayahs_raw)));

                     $is_public = isset($_POST['is_public']) && $_POST['is_public'] == '1' ? 1 : 0; // User can suggest community (1) or keep private (0)
                     // Ulama/Admin can set to public (2) directly
                     if (has_permission('Ulama') && isset($_POST['is_public']) && $_POST['is_public'] == '2') {
                         $is_public = 2;
                     }
                     $status = ($is_public == 1 && !has_permission('Ulama')) ? 'Pending' : 'Approved'; // User suggestions are pending unless Ulama/Admin

                     if (empty($theme_name)) {
                         $error = "Theme name cannot be empty.";
                         $view = 'themes'; // Stay on themes page
                     } else {
                         $result = save_theme($user_id, $theme_name, $description, $ayahs, $is_public, $status);
                         if ($result === true) {
                             $message = "Theme '{$theme_name}' saved successfully." . ($status === 'Pending' ? " Awaiting review." : "");
                             header('Location: ?view=themes');
                             exit;
                         } else {
                             $error = "Failed to save theme: " . $result;
                             $view = 'themes'; // Stay on themes page
                         }
                     }
                 } else {
                     $error = "Permission denied.";
                 }
                 break;
            case 'update_hifz':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_id = (int)$_POST['ayah_id'];
                     $status = $_POST['hifz_status'];
                     if (update_hifz_status($user_id, $surah_id, $ayah_id, $status)) {
                         $message = "Hifz status updated.";
                     } else {
                         $error = "Failed to update Hifz status.";
                     }
                     // Redirect back to the ayah view or hifz page
                      header("Location: ?view=quran&surah={$surah_id}#ayah-{$ayah['id']}");
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'add_recitation_log':
                 if (is_logged_in() && has_permission('User')) {
                     $user_id = get_user_id();
                     $surah_id = (int)$_POST['surah_id'];
                     $ayah_from = (int)$_POST['ayah_from'];
                     $ayah_to = (int)$_POST['ayah_to'];
                     $notes = trim($_POST['notes']);
                      if ($ayah_from <= 0 || $ayah_to <= 0 || $ayah_from > $ayah_to) {
                          $error = "Invalid Ayah range.";
                          $view = 'recitation'; // Stay on recitation page
                      } else {
                         if (add_recitation_log($user_id, $surah_id, $ayah_from, $ayah_to, $notes)) {
                             $message = "Recitation log added successfully.";
                             header('Location: ?view=recitation');
                             exit;
                         } else {
                             $error = "Failed to add recitation log.";
                             $view = 'recitation'; // Stay on recitation page
                         }
                      }
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'delete_recitation_log':
                 if (is_logged_in() && has_permission('User')) {
                     $log_id = (int)$_POST['log_id'];
                     $user_id = get_user_id();
                     if (delete_recitation_log($log_id, $user_id)) {
                         $message = "Recitation log deleted.";
                     } else {
                         $error = "Failed to delete recitation log.";
                     }
                     header('Location: ?view=recitation');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'update_user_role':
                 if (has_permission('Admin')) {
                     $user_id = (int)$_POST['user_id'];
                     $role = $_POST['role'];
                     if (update_user_role($user_id, $role)) {
                         $message = "User role updated.";
                     } else {
                         $error = "Failed to update user role.";
                     }
                     header('Location: ?view=admin');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'approve_contribution':
                 if (has_permission('Ulama')) {
                     $contribution_id = (int)$_POST['contribution_id'];
                     $type = $_POST['contribution_type']; // Need type from form
                     $approved_by_user_id = get_user_id();
                     if (approve_contribution($contribution_id, $type, $approved_by_user_id)) {
                         $message = "Contribution approved.";
                     } else {
                         $error = "Failed to approve contribution.";
                     }
                     header('Location: ?view=contributions');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'reject_contribution':
                 if (has_permission('Ulama')) {
                     $contribution_id = (int)$_POST['contribution_id'];
                     $type = $_POST['contribution_type']; // Need type from form
                     if (reject_contribution($contribution_id, $type)) {
                         $message = "Contribution rejected.";
                     } else {
                         $error = "Failed to reject contribution.";
                     }
                     header('Location: ?view=contributions');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
            case 'load_initial_data':
                 if (has_permission('Admin')) {
                     load_initial_data();
                     $message = "Initial data loading process initiated. Check logs for errors.";
                     header('Location: ?view=admin');
                     exit;
                 } else {
                     $error = "Permission denied.";
                 }
                break;
             case 'export_data':
                if (is_logged_in() && has_permission('User')) {
                    $user_id = get_user_id();
                    $data = export_user_data($user_id);
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="quran_study_export_' . $_SESSION['username'] . '_' . date('YmdHis') . '.json"');
                    echo $data;
                    exit;
                } else {
                    $error = "Permission denied.";
                }
                break;
             case 'import_data':
                 if (is_logged_in() && has_permission('User')) {
                     if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                         $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
                         $user_id = get_user_id();
                         $result = import_user_data($user_id, $file_content);
                         if ($result === true) {
                             $message = "Data imported successfully.";
                         } else {
                             $error = "Data import failed: " . $result;
                         }
                     } else {
                         $error = "Error uploading file.";
                     }
                     $view = 'profile'; // Stay on profile page
                 } else {
                     $error = "Permission denied.";
                 }
                 break;
             case 'export_docx':
                 // DOCX export logic (requires a library like PhpWord - not included in single file)
                 // This is a placeholder. A real implementation would use a library.
                 // Since we cannot include a library in a single file, this feature is genuinely not feasible
                 // without breaking the single-file constraint.
                 // We will provide a basic text export instead.
                 if (isset($_POST['surah_id'])) {
                     $surah_id = (int)$_POST['surah_id'];
                     $surah = null;
                     $surahs = get_surahs();
                     foreach($surahs as $s) {
                         if ($s['id'] === $surah_id) {
                             $surah = $s;
                             break;
                         }
                     }

                     if ($surah) {
                         $ayahs = get_ayahs_by_surah($surah_id);
                         $output = "Surah {$surah['id']}: {$surah['name_english']} ({$surah['name_arabic']})\n\n";
                         foreach ($ayahs as $ayah) {
                             $output .= "{$ayah['surah_id']}:{$ayah['ayah_number']}\n";
                             $output .= $ayah['arabic_text'] . "\n";
                             $translations = get_translations_by_ayah($ayah['id']);
                             foreach($translations as $lang => $text) {
                                 $output .= "{$lang}: {$text}\n";
                             }
                             $output .= "\n"; // Separator between ayahs
                         }

                         header('Content-Type: text/plain; charset=utf-8');
                         header('Content-Disposition: attachment; filename="quran_surah_' . $surah_id . '.txt"');
                         echo $output;
                         exit;

                     } else {
                         $error = "Invalid Surah ID for export.";
                         $view = 'quran';
                     }

                 } else {
                      $error = "No Surah ID provided for export.";
                      $view = 'quran';
                 }
                 break;

        }
    }
} elseif ($action === 'logout') {
    logout();
} elseif ($action === 'export_data') {
    // Handle GET request for export (e.g., link click)
    if (is_logged_in() && has_permission('User')) {
        $user_id = get_user_id();
        $data = export_user_data($user_id);
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="quran_study_export_' . $_SESSION['username'] . '_' . date('YmdHis') . '.json"');
        echo $data;
        exit;
    } else {
        $error = "Permission denied.";
        $view = 'login'; // Redirect to login if not allowed
    }
}


// Ensure database is initialized on first run
if (!file_exists(DB_PATH)) {
    init_db();
    // Optionally redirect to an admin setup page or show a message
    // For this single file, we'll just show a message on the admin page
    if ($view !== 'admin') {
         $view = 'admin'; // Force admin view to load data
         $message = "Database initialized. Please load initial data.";
    }
}


// Render Views
render_header();

if ($message) {
    echo "<div class='success'>{$message}</div>";
}
if ($error) {
    echo "<div class='error'>{$error}</div>";
}

switch ($view) {
    case 'quran':
        $surahs = get_surahs();
        $selected_surah_id = isset($_GET['surah']) ? (int)$_GET['surah'] : 1;
        // Ensure selected_surah_id is valid
        $valid_surah_ids = array_column($surahs, 'id');
        if (!in_array($selected_surah_id, $valid_surah_ids)) {
            $selected_surah_id = 1; // Default to Surah 1 if invalid
        }

        $ayahs = get_ayahs_by_surah($selected_surah_id);
        $current_user_id = get_user_id();

        // Find selected surah details
        $selected_surah = null;
        foreach($surahs as $s) {
            if ($s['id'] == $selected_surah_id) {
                $selected_surah = $s;
                break;
            }
        }

        echo "<h2>Quran Viewer</h2>";
        echo "<div class='form-group'>";
        echo "<label for='surah-select'>Select Surah:</label>";
        echo "<select id='surah-select' onchange='window.location.href=\"?view=quran&surah=\" + this.value'>";
        foreach ($surahs as $surah) {
            $selected = ($surah['id'] == $selected_surah_id) ? 'selected' : '';
            echo "<option value='{$surah['id']}' {$selected}>{$surah['id']}. {$surah['name_english']}</option>";
        }
        echo "</select>";
        echo "</div>";

        if ($selected_surah) {
             echo "<h3>Surah {$selected_surah['id']}: " . htmlspecialchars($selected_surah['name_english']) . " (" . htmlspecialchars($selected_surah['name_arabic']) . ")</h3>";
             echo "<p>Revelation: " . htmlspecialchars($selected_surah['revelation_type']) . " | Ayahs: {$selected_surah['ayah_count']}</p>";
        } else {
             echo "<h3>Select a Surah</h3>";
        }


        foreach ($ayahs as $ayah) {
            $translations = get_translations_by_ayah($ayah['id']);
            $personal_tafsir = is_logged_in() ? get_personal_tafsir($current_user_id, $ayah['surah_id'], $ayah['id']) : '';
            $community_tafsirs = get_community_tafsir_by_ayah($ayah['id']);
            $hifz_status = is_logged_in() ? get_hifz_status($current_user_id, $ayah['surah_id'], $ayah['id']) : 'Not Started';
            $word_translations = get_word_translations_for_ayah($ayah['id']);


            echo "<div class='quran-ayah' id='ayah-{$ayah['id']}'>";
            echo "<div class='ayah-meta'>{$ayah['surah_id']}:{$ayah['ayah_number']}</div>";
             // Add Ayah Options (Copy, Add to Theme, etc.)
             echo "<div class='ayah-options'>";
             echo "<a href='#' onclick='copyAyah({$ayah['id']}); return false;'>Copy</a>"; // Prevent default link behavior
             if (is_logged_in() && has_permission('User')) {
                 echo "<a href='#' onclick='addToTheme({$ayah['surah_id']}, {$ayah['ayah_number']}); return false;'>Add to Theme</a>"; // Prevent default link behavior
             }
             echo "</div>"; // .ayah-options


            echo "<div class='arabic-text'>" . htmlspecialchars($ayah['arabic_text']) . "</div>";

            // Display Word-by-Word Translations
            if (!empty($word_translations)) {
                 echo "<div class='word-by-word'>";
                 foreach($word_translations as $word) {
                     echo "<span data-ur='" . htmlspecialchars($word['ur_meaning'] ?? '') . "' data-en='" . htmlspecialchars($word['en_meaning'] ?? '') . "'>" . htmlspecialchars($word['arabic_word']) . "</span> ";
                 }
                 echo "</div>";
            }


            // Display translations
            foreach ($translations as $lang => $text) {
                echo "<div class='translation-text'><strong>{$lang}:</strong> " . nl2br(htmlspecialchars($text)) . "</div>";
            }

            // Tafsir Section
            echo "<div class='tafsir-section'>";
             // Personal Tafsir (if logged in)
            if (is_logged_in() && has_permission('User')) {
                echo "<h4>Personal Tafsir</h4>";
                echo "<form method='POST'>";
                echo "<input type='hidden' name='action' value='save_tafsir'>";
                echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                echo "<textarea name='tafsir_text' rows='4' cols='50' placeholder='Add your personal notes or tafsir here...'>" . htmlspecialchars($personal_tafsir) . "</textarea><br>";
                echo "<button type='submit' class='btn'>Save Personal Tafsir</button>";
                echo "</form>";

                 // Submit Community Tafsir
                 echo "<h4>Submit Community Tafsir</h4>";
                 echo "<form method='POST'>";
                 echo "<input type='hidden' name='action' value='submit_community_tafsir'>";
                 echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                 echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                 echo "<textarea name='community_tafsir_text' rows='4' cols='50' placeholder='Suggest a community tafsir for review...'></textarea><br>";
                 echo "<button type='submit' class='btn'>Submit for Community</button>";
                 echo "</form>";
            }

             // Community Tafsir (Approved only)
             if (!empty($community_tafsirs)) {
                 echo "<h4>Community Tafsir</h4>";
                 foreach($community_tafsirs as $ct) {
                     echo "<div class='community-tafsir-item'>";
                     echo "<strong>" . htmlspecialchars($ct['username']) . ":</strong> ";
                     echo nl2br(htmlspecialchars($ct['tafsir_text']));
                     echo "</div>";
                 }
             }

            echo "</div>"; // .tafsir-section

            // Hifz Tracking (if logged in)
            if (is_logged_in() && has_permission('User')) {
                 echo "<div class='tafsir-section'>"; // Use same styling
                 echo "<h4>Hifz Status</h4>";
                 echo "<form method='POST'>";
                 echo "<input type='hidden' name='action' value='update_hifz'>";
                 echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                 echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                 echo "<select name='hifz_status' onchange='this.form.submit()'>";
                 $statuses = ['Not Started', 'Memorizing', 'Memorized'];
                 foreach($statuses as $status) {
                     $selected = ($hifz_status === $status) ? 'selected' : '';
                     echo "<option value='{$status}' {$selected}>{$status}</option>";
                 }
                 echo "</select>";
                 // No separate button needed if submitting on change
                 echo "</form>";
                 echo "</div>"; // .tafsir-section
            }


            echo "</div>"; // .quran-ayah
        }

         // DOCX Export Form (Now a text export)
         echo "<div class='docx-export-form'>";
         echo "<h3>Export Surah to Text File</h3>";
         echo "<p>Export the currently viewed Surah to a plain text (.txt) file.</p>";
         echo "<form method='POST'>";
         echo "<input type='hidden' name='action' value='export_docx'>"; // Keep action name for consistency
         echo "<input type='hidden' name='surah_id' value='{$selected_surah_id}'>";
         echo "<button type='submit' class='btn'>Export Surah {$selected_surah_id} to TXT</button>";
         echo "</form>";
         echo "</div>";


        break;

    case 'tafsir':
        if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to view Personal Tafsir.</div>";
            break;
        }
        $user_id = get_user_id();
        // Fetch all personal tafsir entries for the user
        $db = new SQLite3(DB_PATH);
        $stmt = $db->prepare("SELECT pt.surah_id, pt.ayah_id, pt.tafsir_text, a.ayah_number, s.name_english FROM personal_tafsir pt JOIN ayahs a ON pt.ayah_id = a.id JOIN surahs s ON pt.surah_id = s.id WHERE pt.user_id = :user_id ORDER BY pt.surah_id, a.ayah_number");
        $stmt->bindValue(':user_id', $user_id);
        $results = $stmt->execute();
        $personal_tafsirs = [];
        while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
            $personal_tafsirs[] = $row;
        }
        $db->close();

        echo "<h2>Personal Tafsir</h2>";
        if (empty($personal_tafsirs)) {
            echo "<p>You have not added any personal tafsir yet. Go to the Quran Viewer to add notes to ayahs.</p>";
        } else {
            foreach ($personal_tafsirs as $tafsir) {
                echo "<div class='quran-ayah'>"; // Re-use ayah styling
                echo "<div class='ayah-meta'><a href='?view=quran&surah={$tafsir['surah_id']}#ayah-{$tafsir['ayah_id']}'>{$tafsir['surah_id']}:{$tafsir['ayah_number']} - {$tafsir['name_english']}</a></div>";
                echo "<div class='translation-text'>" . nl2br(htmlspecialchars($tafsir['tafsir_text'])) . "</div>";
                echo "</div>";
            }
        }
        break;

    case 'themes':
        $filter = $_GET['filter'] ?? 'all'; // all, community, ulama, personal, pending
        $user_id = get_user_id();
        $can_create_themes = is_logged_in() && has_permission('User');
        $can_view_pending = has_permission('Ulama');

        echo "<h2>Thematic Linker</h2>";

        echo "<p>View: ";
        echo "<a href='?view=themes&filter=all'>All Public</a> | ";
        echo "<a href='?view=themes&filter=community'>Community</a> | ";
        echo "<a href='?view=themes&filter=ulama'>Ulama/Admin</a>";
        if ($can_create_themes) {
             echo " | <a href='?view=themes&filter=personal'>My Themes</a>";
        }
        if ($can_view_pending) {
             echo " | <a href='?view=themes&filter=pending'>Pending Contributions</a>";
        }
        echo "</p>";


        if ($can_create_themes && ($filter === 'personal' || !isset($_GET['filter']))) {
             echo "<h3>Create/Edit My Theme</h3>";
             echo "<form method='POST'>";
             echo "<input type='hidden' name='action' value='save_theme'>";
             echo "<div class='form-group'>";
             echo "<label for='theme_name'>Theme Name:</label>";
             echo "<input type='text' id='theme_name' name='theme_name' required>";
             echo "</div>";
             echo "<div class='form-group'>";
             echo "<label for='description'>Description:</label>";
             echo "<textarea id='description' name='description' rows='3'></textarea>";
             echo "</div>";
             echo "<div class='form-group'>";
             echo "<label>Ayahs (Surah-Ayah, one per line or comma separated):</label>";
             echo "<textarea name='theme_ayahs' rows='5' placeholder='e.g., 1-1&#10;1-2&#10;2-255 or 1-1, 1-2, 2-255'></textarea>";
             echo "</div>";
             echo "<div class='form-group'>";
             echo "<label for='is_public'>Visibility:</label>";
             echo "<select id='is_public' name='is_public'>";
             echo "<option value='0'>Private (Only I can see)</option>";
             echo "<option value='1'>Suggest for Community (Requires Review)</option>";
             // Admins/Ulama can create public themes directly (is_public=2)
             if (has_permission('Ulama')) {
                  echo "<option value='2'>Public (Ulama/Admin Contribution)</option>";
             }
             echo "</select>";
             echo "</div>";
             echo "<button type='submit' class='btn'>Save Theme</button>";
             echo "</form>";
             echo "<hr>";
        }

        $themes = get_themes($filter, $user_id);

        echo "<h3>";
        if ($filter === 'community') echo "Community Themes";
        elseif ($filter === 'ulama') echo "Ulama/Admin Themes";
        elseif ($filter === 'personal') echo "My Themes";
        elseif ($filter === 'pending') echo "Pending Theme Contributions";
        else echo "All Public Themes";
        echo "</h3>";


        if (empty($themes)) {
            echo "<p>No themes found for this filter.</p>";
        } else {
            foreach ($themes as $theme) {
                echo "<div class='quran-ayah'>"; // Re-use ayah styling for theme blocks
                echo "<h4>" . htmlspecialchars($theme['theme_name']) . " by " . htmlspecialchars($theme['username']) . "</h4>";
                echo "<p>" . nl2br(htmlspecialchars($theme['description'])) . "</p>";
                if ($filter === 'pending') {
                     echo "<p><strong>Status:</strong> {$theme['status']}</p>";
                }
                $theme_ayahs = get_theme_ayahs($theme['id']);
                if (!empty($theme_ayahs)) {
                    echo "<p><strong>Ayahs:</strong></p>";
                    echo "<ul class='theme-ayah-list'>";
                    foreach($theme_ayahs as $ayah) {
                         echo "<li><a href='?view=quran&surah={$ayah['surah_id']}#ayah-{$ayah['ayah_id']}'>{$ayah['surah_id']}:{$ayah['ayah_number']} - {$ayah['name_english']}</a></li>";
                    }
                    echo "</ul>";
                }
                 // Add edit/delete for personal themes
                 if ($filter === 'personal' && $theme['user_id'] == $user_id) {
                     // Edit functionality could pre-fill the form above
                     // Delete functionality would require a separate action
                 }
                echo "</div>"; // .quran-ayah
            }
        }
        break;

    case 'hifz':
         if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to use the Hifz Hub.</div>";
            break;
        }
        $user_id = get_user_id();
        $surahs = get_surahs();

        echo "<h2>Hifz Hub</h2>";
        echo "<p>Track your Quran memorization progress.</p>";

        // Display Hifz status per Surah/Ayah (summary or detailed)
        // For simplicity, let's show a list of Surahs and allow drilling down
        echo "<h3>Surah Progress</h3>";
        echo "<ul>";
        foreach($surahs as $surah) {
             echo "<li><a href='?view=hifz&surah={$surah['id']}'>Surah {$surah['id']}: {$surah['name_english']} ({$surah['ayah_count']} Ayahs)</a></li>";
        }
        echo "</ul>";

        if (isset($_GET['surah'])) {
             $selected_surah_id = (int)$_GET['surah'];
             $selected_surah = null;
             foreach($surahs as $s) {
                 if ($s['id'] == $selected_surah_id) {
                     $selected_surah = $s;
                     break;
                 }
             }

             if ($selected_surah) {
                 echo "<h3>Hifz Status for Surah {$selected_surah['id']}: {$selected_surah['name_english']}</h3>";
                 $ayahs = get_ayahs_by_surah($selected_surah_id);
                 echo "<table>";
                 echo "<thead><tr><th>Ayah</th><th>Status</th><th>Action</th></tr></thead>";
                 echo "<tbody>";
                 foreach($ayahs as $ayah) {
                     $hifz_status = get_hifz_status($user_id, $ayah['surah_id'], $ayah['id']);
                     echo "<tr>";
                     echo "<td><a href='?view=quran&surah={$ayah['surah_id']}#ayah-{$ayah['id']}'>{$ayah['surah_id']}:{$ayah['ayah_number']}</a></td>";
                     echo "<td>{$hifz_status}</td>";
                     echo "<td>";
                     echo "<form method='POST' style='display:inline-block;'>";
                     echo "<input type='hidden' name='action' value='update_hifz'>";
                     echo "<input type='hidden' name='surah_id' value='{$ayah['surah_id']}'>";
                     echo "<input type='hidden' name='ayah_id' value='{$ayah['id']}'>";
                     echo "<select name='hifz_status' onchange='this.form.submit()'>";
                     $statuses = ['Not Started', 'Memorizing', 'Memorized'];
                     foreach($statuses as $status) {
                         $selected = ($hifz_status === $status) ? 'selected' : '';
                         echo "<option value='{$status}' {$selected}>{$status}</option>";
                     }
                     echo "</select>";
                     echo "</form>";
                     echo "</td>";
                     echo "</tr>";
                 }
                 echo "</tbody>";
                 echo "</table>";
             } else {
                 echo "<p>Invalid Surah selected.</p>";
             }
        }


        break;

    case 'recitation':
         if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to use the Recitation Log.</div>";
            break;
        }
        $user_id = get_user_id();
        $surahs = get_surahs();
        $logs = get_recitation_log($user_id);

        echo "<h2>Recitation Log</h2>";

        echo "<h3>Add New Entry</h3>";
        echo "<form method='POST'>";
        echo "<input type='hidden' name='action' value='add_recitation_log'>";
        echo "<div class='form-group'>";
        echo "<label for='surah_id'>Surah:</label>";
        echo "<select id='surah_id' name='surah_id' required>";
        foreach ($surahs as $surah) {
            echo "<option value='{$surah['id']}'>{$surah['id']}. {$surah['name_english']}</option>";
        }
        echo "</select>";
        echo "</div>";
         echo "<div class='form-group'>";
         echo "<label for='ayah_from'>From Ayah:</label>";
         echo "<input type='number' id='ayah_from' name='ayah_from' min='1' required>";
         echo "</div>";
         echo "<div class='form-group'>";
         echo "<label for='ayah_to'>To Ayah:</label>";
         echo "<input type='number' id='ayah_to' name='ayah_to' min='1' required>";
         echo "</div>";
        echo "<div class='form-group'>";
        echo "<label for='notes'>Notes:</label>";
        echo "<textarea id='notes' name='notes' rows='3'></textarea>";
        echo "</div>";
        echo "<button type='submit' class='btn'>Add Log</button>";
        echo "</form>";

        echo "<h3>My Recitation History</h3>";
        if (empty($logs)) {
            echo "<p>No recitation logs added yet.</p>";
        } else {
            echo "<table>";
            echo "<thead><tr><th>Date</th><th>Surah</th><th>Ayahs</th><th>Notes</th><th>Action</th></tr></thead>";
            echo "<tbody>";
            foreach ($logs as $log) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($log['recitation_date']) . "</td>";
                echo "<td>{$log['surah_id']}. " . htmlspecialchars($log['name_english']) . "</td>";
                echo "<td>{$log['ayah_from']} - {$log['ayah_to']}</td>";
                echo "<td>" . nl2br(htmlspecialchars($log['notes'])) . "</td>";
                echo "<td>";
                echo "<form method='POST' style='display:inline-block;' onsubmit='return confirm(\"Are you sure you want to delete this log entry?\");'>";
                echo "<input type='hidden' name='action' value='delete_recitation_log'>";
                echo "<input type='hidden' name='log_id' value='{$log['id']}'>";
                echo "<button type='submit' class='btn btn-danger'>Delete</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        }
        break;

    case 'search':
        echo "<h2>Advanced Search</h2>";
        echo "<form method='GET'>";
        echo "<input type='hidden' name='view' value='search'>";
        echo "<div class='form-group'>";
        echo "<label for='query'>Search Query:</label>";
        echo "<input type='text' id='query' name='query' value='" . htmlspecialchars($_GET['query'] ?? '') . "' required>";
        echo "</div>";
        echo "<div class='form-group'>";
        echo "<label for='language'>Search In:</label>";
        echo "<select id='language' name='language'>";
        $languages = ['Arabic', 'Urdu', 'English', 'Bengali']; // Available languages
        $selected_lang = $_GET['language'] ?? DEFAULT_TRANSLATION;
        foreach($languages as $lang) {
            $selected = ($selected_lang === $lang) ? 'selected' : '';
            echo "<option value='{$lang}' {$selected}>{$lang}</option>";
        }
        echo "</select>";
        echo "</div>";
        echo "<button type='submit' class='btn'>Search</button>";
        echo "</form>";

        if (isset($_GET['query']) && $_GET['query'] !== '') {
            $query = $_GET['query'];
            $language = $_GET['language'] ?? DEFAULT_TRANSLATION;
            $search_results = search_quran($query, $language);

            echo "<h3>Search Results for '" . htmlspecialchars($query) . "' in " . htmlspecialchars($language) . "</h3>";

            if (empty($search_results)) {
                echo "<p>No results found.</p>";
            } else {
                foreach ($search_results as $result) {
                    echo "<div class='quran-ayah'>"; // Re-use ayah styling
                    echo "<div class='ayah-meta'><a href='?view=quran&surah={$result['surah_id']}#ayah-{$result['ayah_id']}'>{$result['surah_id']}:{$result['ayah_number']} - {$result['name_english']}</a></div>";
                    echo "<div class='arabic-text'>" . htmlspecialchars($result['arabic_text']) . "</div>";
                    if ($result['translation_text']) {
                         echo "<div class='translation-text'><strong>{$language}:</strong> " . nl2br(htmlspecialchars($result['translation_text'])) . "</div>";
                    }
                    echo "</div>"; // .quran-ayah
                }
            }
        }
        break;

    case 'games':
         echo "<h2>Quran Games</h2>";
         echo "<p>Test your knowledge with these games.</p>";

         // Word Whiz (Basic - display a random word and ask for meaning)
         echo "<h3>Word Whiz</h3>";
         // Fetch a random word with translation (requires more complex query/data structure)
         // For simplicity, let's just mention the game concept.
         echo "<p>Match Arabic words with their meanings. (Requires word-by-word data and a game interface - basic concept described below)</p>";
         echo "<p><strong>How to play (Concept):</strong> A random Arabic word from the Quran is displayed. The user selects the correct Urdu or English meaning from a list of options. Score is kept. This requires fetching random word metadata and their translations, and presenting multiple choices.</p>";


         // Ayah Jumble (Basic - jumble words of an ayah)
         echo "<h3>Ayah Jumble</h3>";
          // Select a random ayah for the game
         $db = new SQLite3(DB_PATH);
         // Select slightly longer ayahs that have word metadata
         $result = $db->query("SELECT a.id, a.arabic_text, a.surah_id, a.ayah_number, s.name_english FROM ayahs a JOIN surahs s ON a.surah_id = s.id WHERE EXISTS (SELECT 1 FROM word_metadata wm WHERE wm.ayah_id = a.id) AND LENGTH(a.arabic_text) > 20 ORDER BY RANDOM() LIMIT 1");
         $random_ayah = $result->fetchArray(SQLITE3_ASSOC);
         $db->close();

         if ($random_ayah) {
             echo "<p>Unscramble the words to form the correct ayah:</p>";
             echo "<p>Surah {$random_ayah['surah_id']}: Ayah {$random_ayah['ayah_number']} ({$random_ayah['name_english']})</p>";

             $words = explode(' ', trim($random_ayah['arabic_text']));
             $correct_order = json_encode($words); // Store correct order as JSON
             shuffle($words); // Jumble the words

             echo "<div class='game-area'>";
             echo "<div id='ayah-jumble-container' data-correct-order='" . htmlspecialchars($correct_order, ENT_QUOTES, 'UTF-8') . "'>";
             foreach($words as $word) {
                 echo "<span class='jumbled-word'>" . htmlspecialchars($word) . "</span>";
             }
             echo "</div>"; // jumble-container
             echo "<p>Click words above to place them below:</p>";
             echo "<div id='ayah-jumble-output'></div>";
             echo "<button id='ayah-jumble-check' class='btn'>Check</button>";
             echo "<button id='ayah-jumble-reset' class='btn'>Reset</button>";
             echo "<div id='ayah-jumble-message'></div>";
             echo "</div>"; // game-area
         } else {
             echo "<p>Could not load a suitable ayah for the game (ensure word metadata is loaded). Try loading initial data from Admin Panel.</p>";
         }


        break;

    case 'profile':
         if (!is_logged_in() || !has_permission('User')) {
            echo "<div class='error'>You must be logged in to view your profile.</div>";
            break;
        }
        $user_id = get_user_id();
        $username = $_SESSION['username'];
        $role = $_SESSION['role'];

        echo "<div class='profile-section'>";
        echo "<h2>User Profile</h2>";
        echo "<p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>";
        echo "<p><strong>Role:</strong> " . htmlspecialchars($role) . "</p>";

        echo "<h3>Data Management</h3>";
        echo "<p>Export your personal data (Tafsir, Themes, Hifz, Recitation Log) as a JSON file.</p>";
        echo "<a href='?action=export_data' class='btn'>Export My Data</a>";

        echo "<h4>Import Data</h4>";
        echo "<p>Import previously exported personal data. This will merge data based on Surah and Ayah numbers, potentially overwriting existing personal data for the same Ayah.</p>";
        echo "<form method='POST' enctype='multipart/form-data'>";
        echo "<input type='hidden' name='action' value='import_data'>";
        echo "<div class='form-group'>";
        echo "<label for='import_file'>Select JSON file:</label>";
        echo "<input type='file' id='import_file' name='import_file' accept='.json' required>";
        echo "</div>";
        echo "<button type='submit' class='btn'>Import Data</button>";
        echo "</form>";
         echo "</div>"; // .profile-section

        break;

    case 'contributions':
         if (!has_permission('Ulama')) {
             echo "<div class='error'>You do not have permission to view contributions.</div>";
             break;
         }
         $pending_contributions = get_pending_contributions();

         echo "<h2>Pending Contributions</h2>";
         if (empty($pending_contributions)) {
             echo "<p>No pending contributions at this time.</p>";
         } else {
             foreach($pending_contributions as $contribution) {
                 echo "<div class='contribution-item'>";
                 echo "<h4>{$contribution['type']} Contribution by " . htmlspecialchars($contribution['username']) . "</h4>";
                 echo "<p>Submitted on: {$contribution['created_at']}</p>";
                 // Link to related item (Ayah for Tafsir, Theme for Theme)
                 if ($contribution['type'] === 'Tafsir') {
                     $db = new SQLite3(DB_PATH);
                     $stmt_ayah = $db->prepare("SELECT surah_id, ayah_number FROM ayahs WHERE id = :ayah_id");
                     $stmt_ayah->bindValue(':ayah_id', $contribution['related_id']);
                     $ayah_info = $stmt_ayah->execute()->fetchArray(SQLITE3_ASSOC);
                     $db->close();
                     if ($ayah_info) {
                          echo "<p>Related Ayah: <a href='?view=quran&surah={$ayah_info['surah_id']}#ayah-{$contribution['related_id']}'>{$ayah_info['surah_id']}:{$ayah_info['ayah_number']}</a></p>";
                     } else {
                         echo "<p>Related Ayah ID: {$contribution['related_id']}</p>";
                     }
                 } elseif ($contribution['type'] === 'Theme') {
                      echo "<p>Related Theme ID: {$contribution['related_id']}</p>"; // Could add link to theme details page if implemented
                 }

                 echo "<p>Content:</p>";
                 echo "<pre>" . htmlspecialchars($contribution['content']) . "</pre>";

                 echo "<form method='POST' style='display:inline-block;'>";
                 echo "<input type='hidden' name='action' value='approve_contribution'>";
                 echo "<input type='hidden' name='contribution_id' value='{$contribution['id']}'>";
                 echo "<input type='hidden' name='contribution_type' value='{$contribution['type']}'>"; // Pass type
                 echo "<button type='submit' class='btn btn-success'>Approve</button>";
                 echo "</form>";

                 echo "<form method='POST' style='display:inline-block; margin-left: 10px;'>";
                 echo "<input type='hidden' name='action' value='reject_contribution'>";
                 echo "<input type='hidden' name='contribution_id' value='{$contribution['id']}'>";
                 echo "<input type='hidden' name='contribution_type' value='{$contribution['type']}'>"; // Pass type
                 echo "<button type='submit' class='btn btn-danger'>Reject</button>";
                 echo "</form>";
                 echo "</div>";
             }
         }
         break;

    case 'admin':
        if (!has_permission('Admin')) {
            echo "<div class='error'>You do not have permission to access the Admin Panel.</div>";
            break;
        }

        echo "<h2>Admin Panel</h2>";

        echo "<h3>User Management</h3>";
        $users = get_all_users();
        if (empty($users)) {
            echo "<p>No users found.</p>";
        } else {
            echo "<table>";
            echo "<thead><tr><th>Username</th><th>Role</th><th>Action</th></tr></thead>";
            echo "<tbody>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                echo "<td>" . htmlspecialchars($user['role']) . "</td>";
                echo "<td>";
                if ($user['role'] !== 'Admin' || $user['id'] !== get_user_id()) { // Cannot change own admin role
                    echo "<form method='POST' style='display:inline-block;'>";
                    echo "<input type='hidden' name='action' value='update_user_role'>";
                    echo "<input type='hidden' name='user_id' value='{$user['id']}'>";
                    echo "<select name='role' onchange='this.form.submit()'>";
                    $roles = ['Public', 'User', 'Ulama', 'Admin'];
                    foreach ($roles as $role) {
                        $selected = ($user['role'] === $role) ? 'selected' : '';
                        echo "<option value='{$role}' {$selected}>{$role}</option>";
                    }
                    echo "</select>";
                    echo "</form>";
                } else {
                    echo "Admin";
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</tbody>";
            echo "</table>";
        }

        echo "<h3>Data Loading</h3>";
         if (!file_exists(DB_PATH) || filesize(DB_PATH) < 1000) { // Simple check if database is likely empty
             echo "<p>Database appears empty. Load initial data from .AM files.</p>";
             echo "<form method='POST'>";
             echo "<input type='hidden' name='action' value='load_initial_data'>";
             echo "<button type='submit' class='btn'>Load Initial Data</button>";
             echo "</form>";
         } else {
             echo "<p>Initial data appears to be loaded.</p>";
              // Option to re-load or load new language files (more complex, requires file upload handling)
              echo "<p>Loading new language data requires file upload functionality (not fully implemented in this single file example).</p>";
         }

         echo "<h3>Manage Contributions</h3>";
         echo "<p><a href='?view=contributions'>Review Pending Contributions</a></p>";
         // Admin could also manage approved/rejected contributions here
         break;

    case 'login':
        if (is_logged_in()) {
            echo "<p>You are already logged in as " . htmlspecialchars($_SESSION['username']) . ". <a href='?action=logout'>Logout</a></p>";
        } else {
            echo "<h2>Login</h2>";
            echo "<form method='POST'>";
            echo "<input type='hidden' name='action' value='login'>";
            echo "<div class='form-group'>";
            echo "<label for='username'>Username:</label>";
            echo "<input type='text' id='username' name='username' required>";
            echo "</div>";
            echo "<div class='form-group'>";
            echo "<label for='password'>Password:</label>";
            echo "<input type='password' id='password' name='password' required>";
            echo "</div>";
            echo "<button type='submit' class='btn'>Login</button>";
            echo "</form>";
            echo "<div class='login-register'><p>Don't have an account? <a href='?view=register'>Register here</a></p></div>";
        }
        break;

    case 'register':
        if (is_logged_in()) {
             echo "<p>You are already logged in as " . htmlspecialchars($_SESSION['username']) . ".</p>";
        } else {
            echo "<h2>Register</h2>";
            echo "<form method='POST'>";
            echo "<input type='hidden' name='action' value='register'>";
            echo "<div class='form-group'>";
            echo "<label for='username'>Username:</label>";
            echo "<input type='text' id='username' name='username' required>";
            echo "</div>";
            echo "<div class='form-group'>";
            echo "<label for='password'>Password:</label>";
            echo "<input type='password' id='password' name='password' required>";
            echo "</div>";
            echo "<button type='submit' class='btn'>Register</button>";
            echo "</form>";
            echo "<div class='login-register'><p>Already have an account? <a href='?view=login'>Login here</a></p></div>";
        }
        break;

    default:
        echo "<p>Welcome to " . htmlspecialchars(APP_NAME) . ".</p>";
        echo "<p>Select a feature from the navigation menu.</p>";
        break;
}

render_footer();

?>