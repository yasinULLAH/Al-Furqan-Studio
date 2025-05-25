<?php
/*
Quran Study Hub - Single File Application
Author: Yasin Ullah (Pakistani)
Description: An advanced Quran Study Hub with dynamic word-level Quran rendering,
             multi-user roles, personal tafsir, thematic linking, root notes,
             recitation logs, hifz tracking, advanced search, user data management,
             admin tools, and backup/restore functionality.
Version: 1.0.0
*/

// --- Configuration and Setup ---
session_start();
define('DB_FILE', __DIR__ . '/quran_study_hub.sqlite');
define('APP_NAME', 'Quran Study Hub');
define('APP_AUTHOR', 'Yasin Ullah');

// Error reporting (uncomment for development)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// --- Database Handler (PDO SQLite) ---
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initSchema();
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getPdo() {
        return $this->pdo;
    }

    // Inside the Database class, initSchema method:
private function initSchema() {
    $this->pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user', 
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS surahs (
            surah_number INTEGER PRIMARY KEY,
            name_arabic TEXT NOT NULL,
            name_english TEXT NOT NULL,
            revelation_type TEXT, 
            ayah_count INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS word_dictionary (
            id INTEGER PRIMARY KEY, -- This ID comes from data5.AM's first column
            quran_text TEXT NOT NULL, -- <<< UNIQUE constraint removed here
            ur_meaning TEXT,
            en_meaning TEXT
        );
        -- Index on quran_text is still useful for searching, even if not unique
        CREATE INDEX IF NOT EXISTS idx_word_dictionary_quran_text ON word_dictionary(quran_text);

        CREATE TABLE IF NOT EXISTS ayah_word_mapping (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word_dict_id INTEGER NOT NULL,
            surah_number INTEGER NOT NULL,
            ayah_number INTEGER NOT NULL,
            word_position INTEGER NOT NULL, 
            FOREIGN KEY (word_dict_id) REFERENCES word_dictionary(id),
            FOREIGN KEY (surah_number) REFERENCES surahs(surah_number),
            UNIQUE (surah_number, ayah_number, word_position)
        );
        CREATE INDEX IF NOT EXISTS idx_ayah_word_mapping_word_dict_id ON ayah_word_mapping(word_dict_id);
        CREATE INDEX IF NOT EXISTS idx_ayah_word_mapping_surah_ayah ON ayah_word_mapping(surah_number, ayah_number);

        -- ... (rest of your tables: personal_tafsir, themes, etc.)
        CREATE TABLE IF NOT EXISTS personal_tafsir (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            surah_number INTEGER NOT NULL,
            ayah_number INTEGER NOT NULL,
            tafsir_text TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (surah_number) REFERENCES surahs(surah_number)
        );
        CREATE INDEX IF NOT EXISTS idx_personal_tafsir_user_surah_ayah ON personal_tafsir(user_id, surah_number, ayah_number);

        CREATE TABLE IF NOT EXISTS themes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            theme_name TEXT NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS theme_ayah_links (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            theme_id INTEGER NOT NULL,
            surah_number INTEGER NOT NULL,
            ayah_number INTEGER NOT NULL,
            user_id INTEGER NOT NULL, 
            FOREIGN KEY (theme_id) REFERENCES themes(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (surah_number) REFERENCES surahs(surah_number),
            UNIQUE(theme_id, surah_number, ayah_number, user_id)
        );

        CREATE TABLE IF NOT EXISTS root_notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            root_word TEXT NOT NULL, 
            notes TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS recitation_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            surah_number INTEGER NOT NULL,
            from_ayah INTEGER NOT NULL,
            to_ayah INTEGER NOT NULL,
            date_recited DATE NOT NULL,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (surah_number) REFERENCES surahs(surah_number)
        );

        CREATE TABLE IF NOT EXISTS hifz_tracking (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            surah_number INTEGER NOT NULL,
            ayah_number INTEGER NOT NULL,
            status TEXT NOT NULL, 
            last_revised_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (surah_number) REFERENCES surahs(surah_number),
            UNIQUE(user_id, surah_number, ayah_number)
        );
        CREATE INDEX IF NOT EXISTS idx_hifz_tracking_user_surah_ayah ON hifz_tracking(user_id, surah_number, ayah_number);
    ");

    // Pre-populate Surahs if table is empty
    $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM surahs");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        $this->populateSurahs();
    }
}

     private function populateSurahs() {
        // Ensure this list is complete and accurate for all 114 Surahs
        $full_surahs_data = [
            1 => ['name_arabic' => 'ٱلْفَاتِحَة', 'name_english' => 'Al-Fatiha', 'ayahs' => 7, 'type' => 'Meccan'],
            2 => ['name_arabic' => 'ٱلْبَقَرَة', 'name_english' => 'Al-Baqarah', 'ayahs' => 286, 'type' => 'Medinan'],
            3 => ['name_arabic' => 'آلِ عِمْرَان', 'name_english' => 'Aal-i-Imran', 'ayahs' => 200, 'type' => 'Medinan'],
            4 => ['name_arabic' => 'ٱلنِّسَاء', 'name_english' => 'An-Nisa', 'ayahs' => 176, 'type' => 'Medinan'],
            5 => ['name_arabic' => 'ٱلْمَائِدَة', 'name_english' => 'Al-Ma\'idah', 'ayahs' => 120, 'type' => 'Medinan'],
            6 => ['name_arabic' => 'ٱلْأَنْعَام', 'name_english' => 'Al-An\'am', 'ayahs' => 165, 'type' => 'Meccan'],
            7 => ['name_arabic' => 'ٱلْأَعْرَاف', 'name_english' => 'Al-A\'raf', 'ayahs' => 206, 'type' => 'Meccan'],
            8 => ['name_arabic' => 'ٱلْأَنْفَال', 'name_english' => 'Al-Anfal', 'ayahs' => 75, 'type' => 'Medinan'],
            9 => ['name_arabic' => 'ٱلتَّوْبَة', 'name_english' => 'At-Tawbah', 'ayahs' => 129, 'type' => 'Medinan'],
            10 => ['name_arabic' => 'يُونُس', 'name_english' => 'Yunus', 'ayahs' => 109, 'type' => 'Meccan'],
            11 => ['name_arabic' => 'هُود', 'name_english' => 'Hud', 'ayahs' => 123, 'type' => 'Meccan'],
            12 => ['name_arabic' => 'يُوسُف', 'name_english' => 'Yusuf', 'ayahs' => 111, 'type' => 'Meccan'],
            13 => ['name_arabic' => 'ٱلرَّعْد', 'name_english' => 'Ar-Ra\'d', 'ayahs' => 43, 'type' => 'Medinan'], // Some say Meccan
            14 => ['name_arabic' => 'إِبْرَاهِيم', 'name_english' => 'Ibrahim', 'ayahs' => 52, 'type' => 'Meccan'],
            15 => ['name_arabic' => 'ٱلْحِجْر', 'name_english' => 'Al-Hijr', 'ayahs' => 99, 'type' => 'Meccan'],
            16 => ['name_arabic' => 'ٱلنَّحْل', 'name_english' => 'An-Nahl', 'ayahs' => 128, 'type' => 'Meccan'],
            17 => ['name_arabic' => 'ٱلْإِسْرَاء', 'name_english' => 'Al-Isra', 'ayahs' => 111, 'type' => 'Meccan'],
            18 => ['name_arabic' => 'ٱلْكَهْف', 'name_english' => 'Al-Kahf', 'ayahs' => 110, 'type' => 'Meccan'],
            19 => ['name_arabic' => 'مَرْيَم', 'name_english' => 'Maryam', 'ayahs' => 98, 'type' => 'Meccan'],
            20 => ['name_arabic' => 'طه', 'name_english' => 'Taha', 'ayahs' => 135, 'type' => 'Meccan'],
            21 => ['name_arabic' => 'ٱلْأَنْبِيَاء', 'name_english' => 'Al-Anbiya', 'ayahs' => 112, 'type' => 'Meccan'],
            22 => ['name_arabic' => 'ٱلْحَجّ', 'name_english' => 'Al-Hajj', 'ayahs' => 78, 'type' => 'Medinan'], // Mixed
            23 => ['name_arabic' => 'ٱلْمُؤْمِنُون', 'name_english' => 'Al-Mu\'minun', 'ayahs' => 118, 'type' => 'Meccan'],
            24 => ['name_arabic' => 'ٱلنُّور', 'name_english' => 'An-Nur', 'ayahs' => 64, 'type' => 'Medinan'],
            25 => ['name_arabic' => 'ٱلْفُرْقَان', 'name_english' => 'Al-Furqan', 'ayahs' => 77, 'type' => 'Meccan'],
            26 => ['name_arabic' => 'ٱلشُّعَرَاء', 'name_english' => 'Ash-Shu\'ara', 'ayahs' => 227, 'type' => 'Meccan'],
            27 => ['name_arabic' => 'ٱلنَّمْل', 'name_english' => 'An-Naml', 'ayahs' => 93, 'type' => 'Meccan'],
            28 => ['name_arabic' => 'ٱلْقَصَص', 'name_english' => 'Al-Qasas', 'ayahs' => 88, 'type' => 'Meccan'],
            29 => ['name_arabic' => 'ٱلْعَنْكَبُوت', 'name_english' => 'Al-Ankabut', 'ayahs' => 69, 'type' => 'Meccan'],
            30 => ['name_arabic' => 'ٱلرُّوم', 'name_english' => 'Ar-Rum', 'ayahs' => 60, 'type' => 'Meccan'],
            31 => ['name_arabic' => 'لُقْمَان', 'name_english' => 'Luqman', 'ayahs' => 34, 'type' => 'Meccan'],
            32 => ['name_arabic' => 'ٱلسَّجْدَة', 'name_english' => 'As-Sajdah', 'ayahs' => 30, 'type' => 'Meccan'],
            33 => ['name_arabic' => 'ٱلْأَحْزَاب', 'name_english' => 'Al-Ahzab', 'ayahs' => 73, 'type' => 'Medinan'],
            34 => ['name_arabic' => 'سَبَأ', 'name_english' => 'Saba', 'ayahs' => 54, 'type' => 'Meccan'],
            35 => ['name_arabic' => 'فَاطِر', 'name_english' => 'Fatir', 'ayahs' => 45, 'type' => 'Meccan'],
            36 => ['name_arabic' => 'يس', 'name_english' => 'Ya-Sin', 'ayahs' => 83, 'type' => 'Meccan'],
            37 => ['name_arabic' => 'ٱلصَّافَّات', 'name_english' => 'As-Saffat', 'ayahs' => 182, 'type' => 'Meccan'],
            38 => ['name_arabic' => 'ص', 'name_english' => 'Sad', 'ayahs' => 88, 'type' => 'Meccan'],
            39 => ['name_arabic' => 'ٱلزُّمَر', 'name_english' => 'Az-Zumar', 'ayahs' => 75, 'type' => 'Meccan'],
            40 => ['name_arabic' => 'غَافِر', 'name_english' => 'Ghafir', 'ayahs' => 85, 'type' => 'Meccan'],
            41 => ['name_arabic' => 'فُصِّلَت', 'name_english' => 'Fussilat', 'ayahs' => 54, 'type' => 'Meccan'],
            42 => ['name_arabic' => 'ٱلشُّورَىٰ', 'name_english' => 'Ash-Shuraa', 'ayahs' => 53, 'type' => 'Meccan'],
            43 => ['name_arabic' => 'ٱلزُّخْرُف', 'name_english' => 'Az-Zukhruf', 'ayahs' => 89, 'type' => 'Meccan'],
            44 => ['name_arabic' => 'ٱلدُّخَان', 'name_english' => 'Ad-Dukhan', 'ayahs' => 59, 'type' => 'Meccan'],
            45 => ['name_arabic' => 'ٱلْجَاثِيَة', 'name_english' => 'Al-Jathiyah', 'ayahs' => 37, 'type' => 'Meccan'],
            46 => ['name_arabic' => 'ٱلْأَحْقَاف', 'name_english' => 'Al-Ahqaf', 'ayahs' => 35, 'type' => 'Meccan'],
            47 => ['name_arabic' => 'مُحَمَّد', 'name_english' => 'Muhammad', 'ayahs' => 38, 'type' => 'Medinan'],
            48 => ['name_arabic' => 'ٱلْفَتْح', 'name_english' => 'Al-Fath', 'ayahs' => 29, 'type' => 'Medinan'],
            49 => ['name_arabic' => 'ٱلْحُجُرَات', 'name_english' => 'Al-Hujurat', 'ayahs' => 18, 'type' => 'Medinan'],
            50 => ['name_arabic' => 'ق', 'name_english' => 'Qaf', 'ayahs' => 45, 'type' => 'Meccan'],
            51 => ['name_arabic' => 'ٱلذَّارِيَات', 'name_english' => 'Adh-Dhariyat', 'ayahs' => 60, 'type' => 'Meccan'],
            52 => ['name_arabic' => 'ٱلطُّور', 'name_english' => 'At-Tur', 'ayahs' => 49, 'type' => 'Meccan'],
            53 => ['name_arabic' => 'ٱلنَّجْم', 'name_english' => 'An-Najm', 'ayahs' => 62, 'type' => 'Meccan'],
            54 => ['name_arabic' => 'ٱلْقَمَر', 'name_english' => 'Al-Qamar', 'ayahs' => 55, 'type' => 'Meccan'],
            55 => ['name_arabic' => 'ٱلرَّحْمَٰن', 'name_english' => 'Ar-Rahman', 'ayahs' => 78, 'type' => 'Medinan'], // Some say Meccan
            56 => ['name_arabic' => 'ٱلْوَاقِعَة', 'name_english' => 'Al-Waqi\'ah', 'ayahs' => 96, 'type' => 'Meccan'],
            57 => ['name_arabic' => 'ٱلْحَدِيد', 'name_english' => 'Al-Hadid', 'ayahs' => 29, 'type' => 'Medinan'],
            58 => ['name_arabic' => 'ٱلْمُجَادِلَة', 'name_english' => 'Al-Mujadila', 'ayahs' => 22, 'type' => 'Medinan'],
            59 => ['name_arabic' => 'ٱلْحَشْر', 'name_english' => 'Al-Hashr', 'ayahs' => 24, 'type' => 'Medinan'],
            60 => ['name_arabic' => 'ٱلْمُمْتَحَنَة', 'name_english' => 'Al-Mumtahanah', 'ayahs' => 13, 'type' => 'Medinan'],
            61 => ['name_arabic' => 'ٱلصَّفّ', 'name_english' => 'As-Saf', 'ayahs' => 14, 'type' => 'Medinan'],
            62 => ['name_arabic' => 'ٱلْجُمُعَة', 'name_english' => 'Al-Jumu\'ah', 'ayahs' => 11, 'type' => 'Medinan'],
            63 => ['name_arabic' => 'ٱلْمُنَافِقُون', 'name_english' => 'Al-Munafiqun', 'ayahs' => 11, 'type' => 'Medinan'],
            64 => ['name_arabic' => 'ٱلتَّغَابُن', 'name_english' => 'At-Taghabun', 'ayahs' => 18, 'type' => 'Medinan'],
            65 => ['name_arabic' => 'ٱلطَّلَاق', 'name_english' => 'At-Talaq', 'ayahs' => 12, 'type' => 'Medinan'],
            66 => ['name_arabic' => 'ٱلتَّحْرِيم', 'name_english' => 'At-Tahrim', 'ayahs' => 12, 'type' => 'Medinan'],
            67 => ['name_arabic' => 'ٱلْمُلْك', 'name_english' => 'Al-Mulk', 'ayahs' => 30, 'type' => 'Meccan'],
            68 => ['name_arabic' => 'ٱلْقَلَم', 'name_english' => 'Al-Qalam', 'ayahs' => 52, 'type' => 'Meccan'],
            69 => ['name_arabic' => 'ٱلْحَاقَّة', 'name_english' => 'Al-Haqqah', 'ayahs' => 52, 'type' => 'Meccan'],
            70 => ['name_arabic' => 'ٱلْمَعَارِج', 'name_english' => 'Al-Ma\'arij', 'ayahs' => 44, 'type' => 'Meccan'],
            71 => ['name_arabic' => 'نُوح', 'name_english' => 'Nuh', 'ayahs' => 28, 'type' => 'Meccan'],
            72 => ['name_arabic' => 'ٱلْجِنّ', 'name_english' => 'Al-Jinn', 'ayahs' => 28, 'type' => 'Meccan'],
            73 => ['name_arabic' => 'ٱلْمُزَّمِّل', 'name_english' => 'Al-Muzzammil', 'ayahs' => 20, 'type' => 'Meccan'],
            74 => ['name_arabic' => 'ٱلْمُدَّثِّر', 'name_english' => 'Al-Muddaththir', 'ayahs' => 56, 'type' => 'Meccan'],
            75 => ['name_arabic' => 'ٱلْقِيَامَة', 'name_english' => 'Al-Qiyamah', 'ayahs' => 40, 'type' => 'Meccan'],
            76 => ['name_arabic' => 'ٱلْإِنْسَان', 'name_english' => 'Al-Insan', 'ayahs' => 31, 'type' => 'Medinan'],
            77 => ['name_arabic' => 'ٱلْمُرْسَلَات', 'name_english' => 'Al-Mursalat', 'ayahs' => 50, 'type' => 'Meccan'],
            78 => ['name_arabic' => 'ٱلنَّبَإ', 'name_english' => 'An-Naba', 'ayahs' => 40, 'type' => 'Meccan'],
            79 => ['name_arabic' => 'ٱلنَّازِعَات', 'name_english' => 'An-Nazi\'at', 'ayahs' => 46, 'type' => 'Meccan'],
            80 => ['name_arabic' => 'عَبَسَ', 'name_english' => 'Abasa', 'ayahs' => 42, 'type' => 'Meccan'],
            81 => ['name_arabic' => 'ٱلتَّكْوِير', 'name_english' => 'At-Takwir', 'ayahs' => 29, 'type' => 'Meccan'],
            82 => ['name_arabic' => 'ٱلْإِنْفِطَار', 'name_english' => 'Al-Infitar', 'ayahs' => 19, 'type' => 'Meccan'],
            83 => ['name_arabic' => 'ٱلْمُطَفِّفِين', 'name_english' => 'Al-Mutaffifin', 'ayahs' => 36, 'type' => 'Meccan'],
            84 => ['name_arabic' => 'ٱلْإِنْشِقَاق', 'name_english' => 'Al-Inshiqaq', 'ayahs' => 25, 'type' => 'Meccan'],
            85 => ['name_arabic' => 'ٱلْبُرُوج', 'name_english' => 'Al-Buruj', 'ayahs' => 22, 'type' => 'Meccan'],
            86 => ['name_arabic' => 'ٱلطَّارِق', 'name_english' => 'At-Tariq', 'ayahs' => 17, 'type' => 'Meccan'],
            87 => ['name_arabic' => 'ٱلْأَعْلَىٰ', 'name_english' => 'Al-Ala', 'ayahs' => 19, 'type' => 'Meccan'],
            88 => ['name_arabic' => 'ٱلْغَاشِيَة', 'name_english' => 'Al-Ghashiyah', 'ayahs' => 26, 'type' => 'Meccan'],
            89 => ['name_arabic' => 'ٱلْفَجْر', 'name_english' => 'Al-Fajr', 'ayahs' => 30, 'type' => 'Meccan'],
            90 => ['name_arabic' => 'ٱلْبَلَد', 'name_english' => 'Al-Balad', 'ayahs' => 20, 'type' => 'Meccan'],
            91 => ['name_arabic' => 'ٱلشَّمْس', 'name_english' => 'Ash-Shams', 'ayahs' => 15, 'type' => 'Meccan'],
            92 => ['name_arabic' => 'ٱللَّيْل', 'name_english' => 'Al-Layl', 'ayahs' => 21, 'type' => 'Meccan'],
            93 => ['name_arabic' => 'ٱلضُّحَىٰ', 'name_english' => 'Ad-Duhaa', 'ayahs' => 11, 'type' => 'Meccan'],
            94 => ['name_arabic' => 'ٱلشَّرْح', 'name_english' => 'Ash-Sharh', 'ayahs' => 8, 'type' => 'Meccan'],
            95 => ['name_arabic' => 'ٱلتِّين', 'name_english' => 'At-Tin', 'ayahs' => 8, 'type' => 'Meccan'],
            96 => ['name_arabic' => 'ٱلْعَلَق', 'name_english' => 'Al-Alaq', 'ayahs' => 19, 'type' => 'Meccan'],
            97 => ['name_arabic' => 'ٱلْقَدْر', 'name_english' => 'Al-Qadr', 'ayahs' => 5, 'type' => 'Meccan'],
            98 => ['name_arabic' => 'ٱلْبَيِّنَة', 'name_english' => 'Al-Bayyinah', 'ayahs' => 8, 'type' => 'Medinan'],
            99 => ['name_arabic' => 'ٱلزَّلْزَلَة', 'name_english' => 'Az-Zalzalah', 'ayahs' => 8, 'type' => 'Medinan'], // Some say Meccan
            100 => ['name_arabic' => 'ٱلْعَادِيَات', 'name_english' => 'Al-Adiyat', 'ayahs' => 11, 'type' => 'Meccan'],
            101 => ['name_arabic' => 'ٱلْقَارِعَة', 'name_english' => 'Al-Qari\'ah', 'ayahs' => 11, 'type' => 'Meccan'],
            102 => ['name_arabic' => 'ٱلتَّكَاثُر', 'name_english' => 'At-Takathur', 'ayahs' => 8, 'type'
            => 'Meccan'],
            103 => ['name_arabic' => 'ٱلْعَصْر', 'name_english' => 'Al-Asr', 'ayahs' => 3, 'type' => 'Meccan'],
            104 => ['name_arabic' => 'ٱلْهُمَزَة', 'name_english' => 'Al-Humazah', 'ayahs' => 9, 'type' => 'Meccan'],
            105 => ['name_arabic' => 'ٱلْفِيل', 'name_english' => 'Al-Fil', 'ayahs' => 5, 'type' => 'Meccan'],
            106 => ['name_arabic' => 'قُرَيْش', 'name_english' => 'Quraysh', 'ayahs' => 4, 'type' => 'Meccan'],
            107 => ['name_arabic' => 'ٱلْمَاعُون', 'name_english' => 'Al-Ma\'un', 'ayahs' => 7, 'type' => 'Meccan'],
            108 => ['name_arabic' => 'ٱلْكَوْثَر', 'name_english' => 'Al-Kawthar', 'ayahs' => 3, 'type' => 'Meccan'],
            109 => ['name_arabic' => 'ٱلْكَافِرُون', 'name_english' => 'Al-Kafirun', 'ayahs' => 6, 'type' => 'Meccan'],
            110 => ['name_arabic' => 'ٱلنَّصْر', 'name_english' => 'An-Nasr', 'ayahs' => 3, 'type' => 'Medinan'],
            111 => ['name_arabic' => 'ٱلْمَسَد', 'name_english' => 'Al-Masad', 'ayahs' => 5, 'type' => 'Meccan'],
            112 => ['name_arabic' => 'ٱلْإِخْلَاص', 'name_english' => 'Al-Ikhlas', 'ayahs' => 4, 'type' => 'Meccan'],
            113 => ['name_arabic' => 'ٱلْفَلَق', 'name_english' => 'Al-Falaq', 'ayahs' => 5, 'type' => 'Meccan'],
            114 => ['name_arabic' => 'ٱلنَّاس', 'name_english' => 'An-Nas', 'ayahs' => 6, 'type' => 'Meccan'],
        ];

        // Use INSERT OR IGNORE to prevent errors if this method is somehow called again
        // when data already exists. Ideally, it's only called when the table is empty.
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO surahs (surah_number, name_arabic, name_english, ayah_count, revelation_type) VALUES (?, ?, ?, ?, ?)");
        foreach ($full_surahs_data as $number => $data) {
             $stmt->execute([$number, $data['name_arabic'], $data['name_english'], $data['ayahs'], $data['type']]);
        }
    }

    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }

    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
}
$db = Database::getInstance(); // Global database object

// --- Helper Functions ---
function html_escape($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && $_SESSION['user_role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You need to be logged in to access this page.'];
        redirect('?page=login');
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'You do not have permission to access this page.'];
        redirect('?page=home');
    }
}

function display_message() {
    if (isset($_SESSION['message'])) {
        $type = $_SESSION['message']['type'] === 'error' ? 'msg-error' : 'msg-success';
        echo '<div class="message ' . $type . '">' . html_escape($_SESSION['message']['text']) . '</div>';
        unset($_SESSION['message']);
    }
}

function get_surah_name($surah_number, $lang = 'english') {
    global $db;
    $field = $lang === 'arabic' ? 'name_arabic' : 'name_english';
    $surah = $db->fetch("SELECT $field FROM surahs WHERE surah_number = ?", [$surah_number]);
    return $surah ? $surah[$field] : "Surah $surah_number";
}

function get_all_surahs() {
    global $db;
    return $db->fetchAll("SELECT surah_number, name_arabic, name_english, ayah_count FROM surahs ORDER BY surah_number ASC");
}

// --- Authentication Functions ---
function register_user($username, $email, $password) {
    global $db;
    if (empty($username) || empty($email) || empty($password)) {
        return "All fields are required.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format.";
    }
    if (strlen($password) < 6) {
        return "Password must be at least 6 characters long.";
    }

    $existing_user = $db->fetch("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
    if ($existing_user) {
        return "Username or email already exists.";
    }

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $db->query("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)", [$username, $email, $password_hash]);
        return true;
    } catch (PDOException $e) {
        return "Registration failed: " . $e->getMessage();
    }
}

function login_user($username_or_email, $password) {
    global $db;
    $user = $db->fetch("SELECT id, username, password_hash, role FROM users WHERE username = ? OR email = ?", [$username_or_email, $username_or_email]);

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        return true;
    }
    return "Invalid username/email or password.";
}

function logout_user() {
    session_destroy();
    redirect('?page=login');
}

// --- Quran Data Functions ---
function get_words_for_ayah_dynamic($surah_number, $ayah_number) {
    global $db;
    $sql = "SELECT awm.word_position, wd.quran_text
            FROM ayah_word_mapping awm
            JOIN word_dictionary wd ON awm.word_dict_id = wd.id
            WHERE awm.surah_number = ? AND awm.ayah_number = ?
            ORDER BY awm.word_position ASC";
    return $db->fetchAll($sql, [$surah_number, $ayah_number]);
}

function get_ayah_text_dynamic($surah_number, $ayah_number) {
    $words_data = get_words_for_ayah_dynamic($surah_number, $ayah_number);
    $ayah_html = '';
    foreach ($words_data as $word_data) {
        $ayah_html .= '<span class="arabic-word" data-surah="' . html_escape($surah_number) . '" data-ayah="' . html_escape($ayah_number) . '" data-pos="' . html_escape($word_data['word_position']) . '">' . html_escape($word_data['quran_text']) . '</span> ';
    }
    return trim($ayah_html);
}

function get_word_details($surah_number, $ayah_number, $word_position) {
    global $db;
    $sql = "SELECT wd.quran_text, wd.ur_meaning, wd.en_meaning
            FROM ayah_word_mapping awm
            JOIN word_dictionary wd ON awm.word_dict_id = wd.id
            WHERE awm.surah_number = ? AND awm.ayah_number = ? AND awm.word_position = ?";
    return $db->fetch($sql, [$surah_number, $ayah_number, $word_position]);
}

// --- Hifz Tracking Functions ---
function get_hifz_status_for_ayah($user_id, $surah_number, $ayah_number) {
    global $db;
    return $db->fetch("SELECT status FROM hifz_tracking WHERE user_id = ? AND surah_number = ? AND ayah_number = ?", [$user_id, $surah_number, $ayah_number]);
}

// --- Admin Functions ---
function import_word_dictionary_csv($filePath) {
    global $db;
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return "File not found or not readable: " . html_escape($filePath);
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return "Failed to open file: " . html_escape($filePath);
    }

    $pdo = $db->getPdo();
    $pdo->beginTransaction();
    
    // INSERT OR REPLACE uses the PRIMARY KEY (id) for conflict resolution.
    // If a row with the same 'id' exists, it's replaced.
    // Since UNIQUE constraint is removed from quran_text, it won't cause errors here.
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO word_dictionary (id, quran_text, ur_meaning, en_meaning) VALUES (?, ?, ?, ?)");
    
    $imported_count = 0;
    $skipped_count = 0;
    $line_number = 0;

    // 1. Skip header row
    $header = fgetcsv($handle);
    $line_number++;
    if ($header === false) {
        fclose($handle);
        // No transaction to rollback if it's just reading header, but good practice if any action was taken
        if ($pdo->inTransaction()) $pdo->rollBack();
        return "Failed to read header row from word dictionary file, or file is empty.";
    }
    // Optional: you could validate $header content here if needed.

    while (($data = fgetcsv($handle)) !== FALSE) {
        $line_number++;
        // Ensure we have at least 2 columns (id, quran_text)
        if (count($data) < 2) {
            // error_log("Word Dictionary Import: Skipping line $line_number: insufficient columns. Expected at least 2, got " . count($data));
            $skipped_count++;
            continue;
        }

        $id_raw = trim($data[0]);
        // Explicitly remove UTF-8 BOM if present at the beginning of the first data field's value
        // This can happen if the file has a BOM and fgetcsv includes it in the first field's data.
        if (strpos($id_raw, "\xEF\xBB\xBF") === 0) {
            $id_raw = substr($id_raw, 3);
        }
        
        $quran_text = trim($data[1]);
        // Handle optional meaning fields gracefully
        $ur_meaning = (isset($data[2]) && trim($data[2]) !== '') ? trim($data[2]) : null;
        $en_meaning = (isset($data[3]) && trim($data[3]) !== '') ? trim($data[3]) : null;

        if (!is_numeric($id_raw)) {
            // error_log("Word Dictionary Import: Skipping line $line_number: ID '$id_raw' is not numeric.");
            $skipped_count++;
            continue;
        }
        $id = (int)$id_raw;

        // quran_text should not be empty, id should be positive (optional check, DB might allow 0 if not auto-increment)
        if (empty($quran_text) || $id <= 0) { 
            // error_log("Word Dictionary Import: Skipping line $line_number: quran_text is empty or ID is not positive (ID: $id, Text: '$quran_text').");
            $skipped_count++;
            continue;
        }

        try {
            $stmt->execute([$id, $quran_text, $ur_meaning, $en_meaning]);
            $imported_count++;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            fclose($handle);
            // error_log("Word Dictionary Import Error at line $line_number (ID: $id, Text: '$quran_text'): " . $e->getMessage());
            return "Error importing word dictionary at line $line_number (ID: $id, Text: '" . html_escape($quran_text) . "'): " . html_escape($e->getMessage()) . ". Process halted. $imported_count words imported, $skipped_count rows skipped before this error.";
        }
    }
    
    fclose($handle);
    
    if ($pdo->inTransaction()) {
        if (!$pdo->commit()) {
            // error_log("Word Dictionary Import: Failed to commit transaction.");
            return "Failed to commit transaction for word dictionary. $imported_count words were prepared. $skipped_count rows skipped. Check database integrity and logs.";
        }
    }

    $message = "Successfully processed word dictionary file. Imported $imported_count words.";
    if ($skipped_count > 0) {
        $message .= " Skipped $skipped_count rows due to formatting issues (e.g., non-numeric/invalid ID, missing quran_text, or insufficient columns).";
    }
    return $message;
}

function import_ayah_word_mapping_csv($filePath) {
    global $db;
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return "File not found or not readable.";
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return "Failed to open file.";
    }

    $pdo = $db->getPdo();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("INSERT OR IGNORE INTO ayah_word_mapping (word_dict_id, surah_number, ayah_number, word_position) VALUES (?, ?, ?, ?)");
    
    $count = 0;
    // Skip header if exists
    // fgetcsv($handle);

    while (($data = fgetcsv($handle)) !== FALSE) {
        if (count($data) == 4) { // word_id, surah, ayah, word_position
            $word_dict_id = trim($data[0]);
            $surah_number = trim($data[1]);
            $ayah_number = trim($data[2]);
            $word_position = trim($data[3]);

            if (empty($word_dict_id) || empty($surah_number) || empty($ayah_number) || $word_position === '') continue;

            try {
                // Check if word_dict_id exists in word_dictionary
                $word_exists = $db->fetch("SELECT id FROM word_dictionary WHERE id = ?", [$word_dict_id]);
                if (!$word_exists) {
                     // Log or handle missing word_dict_id
                     // error_log("Skipping mapping for non-existent word_dict_id: $word_dict_id in data2.AM");
                     continue; 
                }

                $stmt->execute([$word_dict_id, $surah_number, $ayah_number, $word_position]);
                if ($stmt->rowCount() > 0) $count++;

            } catch (PDOException $e) {
                // If UNIQUE constraint fails, it means it's already there, so we can ignore it with INSERT OR IGNORE.
                // For other errors, rollback.
                if ($pdo->inTransaction() && strpos($e->getMessage(), 'UNIQUE constraint failed') === false) {
                     $pdo->rollBack();
                     fclose($handle);
                     return "Error importing ayah word mapping (word ID: $word_dict_id, S:$surah_number A:$ayah_number P:$word_position): " . $e->getMessage();
                }
            }
        }
    }
    fclose($handle);
    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    return "Successfully processed ayah word mappings. $count new mappings added.";
}


function perform_backup() {
    require_admin();
    $db_file_path = DB_FILE;
    if (file_exists($db_file_path)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="quran_study_hub_backup_' . date('YmdHis') . '.sqlite"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($db_file_path));
        readfile($db_file_path);
        exit;
    } else {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Database file not found.'];
        redirect('?page=admin_dashboard');
    }
}

function perform_restore($file_path) {
    require_admin();
    // Basic validation: ensure it's an SQLite file (very basic check)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);

    // Allowed mime types for SQLite files (can vary)
    $allowed_mime_types = ['application/x-sqlite3', 'application/vnd.sqlite3', 'application/octet-stream'];

    if (!in_array($mime_type, $allowed_mime_types)) {
         unlink($file_path); // Delete uploaded invalid file
         return "Invalid file type. Please upload a valid SQLite database file. Detected: " . $mime_type;
    }

    // Further validation: try to open it with PDO
    try {
        $test_pdo = new PDO('sqlite:' . $file_path);
        // You could run a simple query to check if it's a valid DB for this app, e.g., check for 'users' table
        $stmt = $test_pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
        if (!$stmt->fetch()) {
            throw new Exception("Uploaded database does not seem to be a valid Quran Study Hub database (missing users table).");
        }
        unset($test_pdo); // Close connection
    } catch (Exception $e) {
        unlink($file_path); // Delete uploaded invalid file
        return "Invalid database file structure: " . $e->getMessage();
    }
    
    // Close current DB connection if any (might not be strictly necessary for file replacement)
    global $db;
    unset($db); // This will make Database::getInstance() re-initialize on next use.
    
    if (copy($file_path, DB_FILE)) {
        unlink($file_path); // Delete temporary uploaded file
        // Re-initialize DB connection
        $db = Database::getInstance(); 
        return true;
    } else {
        unlink($file_path); // Delete temporary uploaded file
        return "Failed to restore database. Check file permissions.";
    }
}

// --- AJAX Action Handler ---
if (isset($_GET['ajax_action'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax_action'];

    if ($action === 'get_word_details') {
        $surah = filter_input(INPUT_GET, 'surah', FILTER_VALIDATE_INT);
        $ayah = filter_input(INPUT_GET, 'ayah', FILTER_VALIDATE_INT);
        $pos = filter_input(INPUT_GET, 'pos', FILTER_VALIDATE_INT);

        if ($surah && $ayah && $pos !== false) {
            $word_details = get_word_details($surah, $ayah, $pos);
            $hifz_status = null;
            if (is_logged_in()) {
                $hifz_data = get_hifz_status_for_ayah($_SESSION['user_id'], $surah, $ayah);
                if($hifz_data) $hifz_status = $hifz_data['status'];
            }

            if ($word_details) {
                echo json_encode(['success' => true, 'word' => $word_details, 'hifz_status' => $hifz_status]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Word not found.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
        }
        exit;
    }
    // Add more AJAX actions here
    echo json_encode(['success' => false, 'message' => 'Unknown AJAX action.']);
    exit;
}

// --- POST Action Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $result = register_user($_POST['username'], $_POST['email'], $_POST['password']);
        if ($result === true) {
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Registration successful! Please log in.'];
            redirect('?page=login');
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => $result];
        }
    } elseif ($action === 'login') {
        $result = login_user($_POST['username_or_email'], $_POST['password']);
        if ($result === true) {
            redirect('?page=home');
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => $result];
        }
    } elseif ($action === 'admin_import_data5') {
        require_admin();
        if (isset($_FILES['data5_file']) && $_FILES['data5_file']['error'] == UPLOAD_ERR_OK) {
            $result = import_word_dictionary_csv($_FILES['data5_file']['tmp_name']);
            $_SESSION['message'] = ['type' => is_string($result) && strpos($result, "Error") === 0 ? 'error' : 'success', 'text' => $result];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'File upload error or no file selected for Word Dictionary.'];
        }
        redirect('?page=admin_data_import');
    } elseif ($action === 'admin_import_data2') {
        require_admin();
        if (isset($_FILES['data2_file']) && $_FILES['data2_file']['error'] == UPLOAD_ERR_OK) {
            $result = import_ayah_word_mapping_csv($_FILES['data2_file']['tmp_name']);
            $_SESSION['message'] = ['type' => is_string($result) && strpos($result, "Error") === 0 ? 'error' : 'success', 'text' => $result];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'File upload error or no file selected for Ayah Word Mapping.'];
        }
        redirect('?page=admin_data_import');
    } elseif ($action === 'admin_restore_backup') {
        require_admin();
        if (isset($_FILES['backup_file']) && $_FILES['backup_file']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = sys_get_temp_dir(); // Or a dedicated writable directory
            $tmp_name = $_FILES['backup_file']['tmp_name'];
            $new_file_path = $upload_dir . '/' . basename($_FILES['backup_file']['name']); // Use a safe name
            
            if (move_uploaded_file($tmp_name, $new_file_path)) {
                $result = perform_restore($new_file_path); // $new_file_path is used and then deleted by perform_restore
                if ($result === true) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'Database restored successfully.'];
                } else {
                    $_SESSION['message'] = ['type' => 'error', 'text' => $result];
                }
            } else {
                 $_SESSION['message'] = ['type' => 'error', 'text' => 'Failed to move uploaded file.'];
            }
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Backup file upload error or no file selected. Error code: ' . ($_FILES['backup_file']['error'] ?? 'N/A')];
        }
        redirect('?page=admin_backup_restore');
    } elseif ($action === 'add_tafsir') {
        require_login();
        $surah = filter_input(INPUT_POST, 'surah_number', FILTER_VALIDATE_INT);
        $ayah = filter_input(INPUT_POST, 'ayah_number', FILTER_VALIDATE_INT);
        $tafsir_text = trim($_POST['tafsir_text']);
        if ($surah && $ayah && !empty($tafsir_text)) {
            $db->query("INSERT INTO personal_tafsir (user_id, surah_number, ayah_number, tafsir_text) VALUES (?, ?, ?, ?)",
                [$_SESSION['user_id'], $surah, $ayah, $tafsir_text]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Tafsir added.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid input for Tafsir.'];
        }
        redirect('?page=quran_viewer&surah=' . $surah . '#ayah-' . $surah . '-' . $ayah);
    } elseif ($action === 'update_hifz_status') {
        require_login();
        $surah = filter_input(INPUT_POST, 'surah_number', FILTER_VALIDATE_INT);
        $ayah = filter_input(INPUT_POST, 'ayah_number', FILTER_VALIDATE_INT);
        $status = $_POST['status']; // Validate status values
        $valid_statuses = ['not_started', 'memorizing', 'memorized', 'revising'];
        if ($surah && $ayah && in_array($status, $valid_statuses)) {
             $db->query("INSERT OR REPLACE INTO hifz_tracking (user_id, surah_number, ayah_number, status, last_revised_at) VALUES (?, ?, ?, ?, ?)",
                [$_SESSION['user_id'], $surah, $ayah, $status, ($status === 'revising' || $status === 'memorized' ? date('Y-m-d H:i:s') : null)]);
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Hifz status updated for S'.$surah.':A'.$ayah.'.'];
        } else {
            $_SESSION['message'] = ['type' => 'error', 'text' => 'Invalid input for Hifz status.'];
        }
        redirect($_POST['redirect_url'] ?? '?page=quran_viewer&surah='.$surah.'#ayah-'.$surah.'-'.$ayah);
    }
    // Add other POST actions here

    // Fallback redirect if action not handled or no specific redirect set
    $current_page = $_GET['page'] ?? 'home';
    redirect('?page=' . $current_page);
}

// --- GET Action Handler (non-AJAX) ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'logout') {
        logout_user();
    } elseif ($action === 'admin_backup_db') {
        perform_backup(); // This exits
    }
    // Add other GET actions here
}

// --- Page Router/Controller ---
$page = $_GET['page'] ?? (is_logged_in() ? 'home' : 'login');

?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo html_escape(ucfirst(str_replace('_', ' ', $page))) . ' - ' . APP_NAME; ?></title>
    <meta name="description" content="Advanced Quran Study Hub by Yasin Ullah. Word-by-word analysis, tafsir, hifz tracking, and more.">
    <meta name="keywords" content="Quran, Islam, Study, Tafsir, Hifz, Recitation, Word by Word Quran, Yasin Ullah, Pakistani">
    <meta name="author" content="<?php echo APP_AUTHOR; ?>">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&family=Inter:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2c3e50; /* Dark Blue-Gray */
            --secondary-color: #3498db; /* Bright Blue */
            --accent-color: #1abc9c; /* Teal */
            --background-color: #f4f7f6; /* Light Gray */
            --text-color: #333;
            --quran-font: 'Noto Naskh Arabic', 'Traditional Arabic', serif;
            --ui-font: 'Inter', sans-serif;
            --card-bg: #ffffff;
            --border-color: #e0e0e0;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
            --word-hover-bg: #e0f7fa;
            --word-hover-text: #00796b;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: var(--ui-font);
            margin: 0;
            background-color: var(--background-color);
            color: var(--text-color);
            line-height: 1.6;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        #app-container { display: flex; flex-direction: column; flex-grow: 1; }
        header {
            background-color: var(--primary-color);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        header h1 { margin: 0; font-size: 1.8rem; }
        header nav a { color: white; text-decoration: none; margin-left: 1.5rem; font-weight: 500; transition: color 0.2s; }
        header nav a:hover, header nav a.active { color: var(--accent-color); }
        main { padding: 2rem; flex-grow: 1; }
        footer {
            background-color: var(--primary-color);
            color: white;
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 1rem; }
        .card {
            background-color: var(--card-bg);
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05), 0 1px 2px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group textarea { min-height: 100px; }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            transition: background-color 0.2s, transform 0.1s;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary { background-color: var(--secondary-color); color: white; }
        .btn-primary:hover { background-color: #2980b9; }
        .btn-accent { background-color: var(--accent-color); color: white; }
        .btn-accent:hover { background-color: #16a085; }
        .btn-danger { background-color: var(--error-color); color: white; }
        .btn-danger:hover { background-color: #c0392b; }
        
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; border: 1px solid transparent; }
        .msg-success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .msg-error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

        /* Quran Viewer Styles */
        .quran-viewer { }
        .surah-header { margin-bottom: 1.5rem; text-align: center; }
        .surah-header h2 { font-family: var(--quran-font); font-size: 2.5rem; color: var(--primary-color); }
        .surah-header p { font-size: 1.1rem; color: #555; }
        .ayah-container {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: var(--card-bg);
            border-radius: 6px;
            border-left: 5px solid var(--accent-color);
            transition: box-shadow 0.3s;
        }
        .ayah-container:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .ayah-number {
            font-weight: bold;
            color: var(--secondary-color);
            margin-right: 0.5em; /* LTR context */
            font-size: 1.1em;
        }
        .quran-text {
            font-family: var(--quran-font);
            direction: rtl;
            text-align: right;
            font-size: 2em; /* Increased size */
            line-height: 2.2; /* Increased line height */
            color: #222;
        }
        .arabic-word {
            cursor: pointer;
            padding: 0 3px;
            transition: background-color 0.3s, color 0.3s;
            border-radius: 3px;
        }
        .arabic-word:hover, .arabic-word.highlighted {
            background-color: var(--word-hover-bg);
            color: var(--word-hover-text);
        }
        .hifz-memorized { background-color: #e6ffed; border-left-color: #4caf50; }
        .hifz-memorizing { background-color: #fff9e6; border-left-color: #ffc107; }
        .hifz-revising { background-color: #e3f2fd; border-left-color: #2196f3; }

        /* Word Detail Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal.active { display: flex; }
        .modal-content {
            background-color: var(--card-bg);
            margin: auto;
            padding: 25px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            position: relative;
        }
        .modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute;
            top: 10px;
            right: 20px;
        }
        .modal-close:hover, .modal-close:focus { color: black; text-decoration: none; cursor: pointer; }
        .modal-word-arabic { font-family: var(--quran-font); font-size: 2rem; text-align: right; margin-bottom: 1rem; color: var(--primary-color); }
        .modal-body p { margin: 0.5rem 0; }
        .modal-body strong { color: var(--secondary-color); }

        /* Ayah actions */
        .ayah-actions { margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border-color); }
        .ayah-actions details summary { cursor: pointer; font-weight: 500; color: var(--primary-color); }
        .ayah-actions details summary:hover { color: var(--secondary-color); }
        .ayah-actions form { margin-top: 0.5rem; }

        /* Responsive */
        @media (max-width: 768px) {
            header { flex-direction: column; padding: 1rem; }
            header h1 { margin-bottom: 0.5rem; }
            header nav { display: flex; flex-wrap: wrap; justify-content: center;}
            header nav a { margin: 0.5rem; }
            main { padding: 1rem; }
            .quran-text { font-size: 1.6em; line-height: 2; }
            .modal-content { width: 95%; }
        }
        .surah-selector { margin-bottom: 2rem; }
        .surah-selector label { margin-right: 1rem; }
        .surah-selector select, .surah-selector input { padding: 0.5rem; border-radius: 4px; border: 1px solid var(--border-color); }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
        th, td { padding: 0.75rem; text-align: left; border: 1px solid var(--border-color); }
        th { background-color: #f0f0f0; }
        .admin-menu a { display: block; margin-bottom: 0.5rem; padding: 0.5rem; background-color: #eee; text-decoration: none; color: var(--primary-color); border-radius: 4px;}
        .admin-menu a:hover { background-color: #ddd; }
    </style>
</head>
<body>
    <div id="app-container">
        <header>
            <h1><a href="?page=home" style="color:white; text-decoration:none;"><?php echo APP_NAME; ?></a></h1>
            <nav>
                <?php if (is_logged_in()): ?>
                    <a href="?page=home" class="<?php echo $page === 'home' ? 'active' : ''; ?>">Home</a>
                    <a href="?page=quran_viewer" class="<?php echo $page === 'quran_viewer' ? 'active' : ''; ?>">Quran Viewer</a>
                    <a href="?page=themes" class="<?php echo $page === 'themes' ? 'active' : ''; ?>">Themes</a>
                    <a href="?page=hifz_tracker" class="<?php echo $page === 'hifz_tracker' ? 'active' : ''; ?>">Hifz Tracker</a>
                    <a href="?page=recitation_log" class="<?php echo $page === 'recitation_log' ? 'active' : ''; ?>">Recitation Log</a>
                    <a href="?page=search" class="<?php echo $page === 'search' ? 'active' : ''; ?>">Search</a>
                    <a href="?page=profile" class="<?php echo $page === 'profile' ? 'active' : ''; ?>">Profile</a>
                    <?php if (is_admin()): ?>
                        <a href="?page=admin_dashboard" class="<?php echo strpos($page, 'admin_') === 0 ? 'active' : ''; ?>">Admin</a>
                    <?php endif; ?>
                    <a href="?action=logout">Logout (<?php echo html_escape($_SESSION['username']); ?>)</a>
                <?php else: ?>
                    <a href="?page=login" class="<?php echo $page === 'login' ? 'active' : ''; ?>">Login</a>
                    <a href="?page=register" class="<?php echo $page === 'register' ? 'active' : ''; ?>">Register</a>
                <?php endif; ?>
            </nav>
        </header>

        <main class="container">
            <?php display_message(); ?>

            <?php if ($page === 'home'): ?>
                <h2>Welcome to <?php echo APP_NAME; ?></h2>
                <?php if (is_logged_in()): ?>
                    <p>Assalamu Alaikum, <?php echo html_escape($_SESSION['username']); ?>! Select an option from the navigation menu to get started.</p>
                    <div class="card">
                        <h3>Quick Links</h3>
                        <p><a href="?page=quran_viewer" class="btn btn-primary">Open Quran Viewer</a></p>
                        <p><a href="?page=hifz_tracker" class="btn btn-accent">My Hifz Progress</a></p>
                    </div>
                <?php else: ?>
                    <p>Please log in or register to access the full features of the Quran Study Hub.</p>
                <?php endif; ?>

            <?php elseif ($page === 'login'): ?>
                <div class="card" style="max-width: 400px; margin: 2rem auto;">
                    <h2>Login</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="username_or_email">Username or Email</label>
                            <input type="text" id="username_or_email" name="username_or_email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                    <p style="margin-top: 1rem;">Don't have an account? <a href="?page=register">Register here</a>.</p>
                </div>

            <?php elseif ($page === 'register'): ?>
                 <div class="card" style="max-width: 400px; margin: 2rem auto;">
                    <h2>Register</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
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
                        <button type="submit" class="btn btn-primary">Register</button>
                    </form>
                    <p style="margin-top: 1rem;">Already have an account? <a href="?page=login">Login here</a>.</p>
                </div>

            <?php elseif ($page === 'quran_viewer'): ?>
                <?php require_login(); ?>
                <div class="quran-viewer">
                    <?php
                    $all_surahs = get_all_surahs();
                    $current_surah_num = filter_input(INPUT_GET, 'surah', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1, 'max_range' => 114]]);
                    $surah_info = $db->fetch("SELECT * FROM surahs WHERE surah_number = ?", [$current_surah_num]);
                    ?>
                    <div class="card surah-selector">
                        <form method="GET">
                            <input type="hidden" name="page" value="quran_viewer">
                            <label for="surah_select">Select Surah:</label>
                            <select id="surah_select" name="surah" onchange="this.form.submit()">
                                <?php foreach ($all_surahs as $s): ?>
                                    <option value="<?php echo $s['surah_number']; ?>" <?php if ($s['surah_number'] == $current_surah_num) echo 'selected'; ?>>
                                        <?php echo $s['surah_number']; ?>. <?php echo html_escape($s['name_english']); ?> (<?php echo html_escape($s['name_arabic']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <label for="ayah_jump" style="margin-left:1em;">Go to Ayah:</label>
                            <input type="number" id="ayah_jump_num" name="jumpto_ayah" min="1" max="<?php echo $surah_info['ayah_count'];?>" style="width: 70px;">
                            <button type="button" class="btn btn-primary" onclick="jumpToAyah()">Go</button>

                        </form>
                    </div>

                    <?php if ($surah_info): ?>
                        <div class="surah-header">
                            <h2><?php echo html_escape($surah_info['name_arabic']); ?></h2>
                            <p><?php echo html_escape($surah_info['name_english']); ?> - Ayahs: <?php echo $surah_info['ayah_count']; ?> - <?php echo html_escape($surah_info['revelation_type']); ?></p>
                        </div>

                        <?php if ($current_surah_num == 1 && $surah_info['revelation_type'] == 'Meccan'): // Bismillah for all surahs except At-Tawbah (9), but Fatiha has it in its text
                            /* No explicit Bismillah here as it's part of Fatiha words. For others: */
                        elseif ($current_surah_num != 9): ?>
                            <div class="ayah-container bismillah">
                                <p class="quran-text" style="text-align:center; font-size:1.5em;">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</p>
                            </div>
                        <?php endif; ?>

                        <?php
                        for ($ayah_num = 1; $ayah_num <= $surah_info['ayah_count']; $ayah_num++):
                            $ayah_text_html = get_ayah_text_dynamic($current_surah_num, $ayah_num);
                            $hifz_status_data = get_hifz_status_for_ayah($_SESSION['user_id'], $current_surah_num, $ayah_num);
                            $hifz_class = $hifz_status_data ? 'hifz-' . html_escape($hifz_status_data['status']) : '';
                            $user_tafsir = $db->fetch("SELECT tafsir_text FROM personal_tafsir WHERE user_id = ? AND surah_number = ? AND ayah_number = ?", [$_SESSION['user_id'], $current_surah_num, $ayah_num]);
                        ?>
                            <div id="ayah-<?php echo $current_surah_num . '-' . $ayah_num; ?>" class="ayah-container <?php echo $hifz_class; ?>">
                                <span class="ayah-number"><?php echo $ayah_num; ?></span>
                                <span class="quran-text"><?php echo $ayah_text_html; ?></span>
                                
                                <div class="ayah-actions">
                                     <details>
                                        <summary><i class="fas fa-cog"></i> Ayah Actions & Info</summary>
                                        <div style="padding-top:10px;">
                                        <!-- Hifz Tracking Form -->
                                        <form method="POST" style="display:inline-block; margin-right:10px;">
                                            <input type="hidden" name="action" value="update_hifz_status">
                                            <input type="hidden" name="surah_number" value="<?php echo $current_surah_num; ?>">
                                            <input type="hidden" name="ayah_number" value="<?php echo $ayah_num; ?>">
                                            <input type="hidden" name="redirect_url" value="?page=quran_viewer&surah=<?php echo $current_surah_num; ?>#ayah-<?php echo $current_surah_num . '-' . $ayah_num; ?>">
                                            <select name="status" onchange="this.form.submit()">
                                                <option value="not_started" <?php echo ($hifz_status_data && $hifz_status_data['status'] == 'not_started') ? 'selected' : ''; ?>>Not Started</option>
                                                <option value="memorizing" <?php echo ($hifz_status_data && $hifz_status_data['status'] == 'memorizing') ? 'selected' : ''; ?>>Memorizing</option>
                                                <option value="memorized" <?php echo ($hifz_status_data && $hifz_status_data['status'] == 'memorized') ? 'selected' : ''; ?>>Memorized</option>
                                                <option value="revising" <?php echo ($hifz_status_data && $hifz_status_data['status'] == 'revising') ? 'selected' : ''; ?>>Revising</option>
                                            </select>
                                        </form>

                                        <!-- Personal Tafsir -->
                                        <button class="btn btn-sm btn-accent" onclick="toggleTafsirForm('<?php echo $current_surah_num; ?>', '<?php echo $ayah_num; ?>')"><i class="fas fa-pen"></i> Tafsir</button>
                                        
                                        <div id="tafsir-form-<?php echo $current_surah_num; ?>-<?php echo $ayah_num; ?>" style="display:none; margin-top:10px;">
                                            <h5>Personal Tafsir for <?php echo get_surah_name($current_surah_num); ?> <?php echo $current_surah_num; ?>:<?php echo $ayah_num; ?></h5>
                                            <?php if ($user_tafsir): ?>
                                                <p><strong>Your current Tafsir:</strong> <?php echo nl2br(html_escape($user_tafsir['tafsir_text'])); ?></p>
                                            <?php endif; ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="add_tafsir">
                                                <input type="hidden" name="surah_number" value="<?php echo $current_surah_num; ?>">
                                                <input type="hidden" name="ayah_number" value="<?php echo $ayah_num; ?>">
                                                <div class="form-group">
                                                    <textarea name="tafsir_text" placeholder="Write your notes/tafsir here..."><?php echo $user_tafsir ? html_escape($user_tafsir['tafsir_text']) : ''; ?></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary"><?php echo $user_tafsir ? 'Update' : 'Add'; ?> Tafsir</button>
                                            </form>
                                        </div>
                                        <!-- TODO: Add links for Thematic Linking, Root Notes -->
                                        </div>
                                    </details>
                                </div>
                            </div>
                        <?php endfor; ?>
                    <?php else: ?>
                        <p class="msg-error">Surah not found.</p>
                    <?php endif; ?>
                </div>

            <?php elseif ($page === 'profile'): require_login(); ?>
                <h2>My Profile</h2>
                <p>Username: <?php echo html_escape($_SESSION['username']); ?></p>
                <p>Email: <?php echo html_escape($db->fetch("SELECT email FROM users WHERE id = ?", [$_SESSION['user_id']])['email']); ?></p>
                <!-- Add forms for updating profile, changing password -->

            <?php elseif ($page === 'admin_dashboard'): require_admin(); ?>
                <h2>Admin Dashboard</h2>
                <div class="admin-menu">
                    <p><a href="?page=admin_user_management" class="btn">User Management</a></p>
                    <p><a href="?page=admin_data_import" class="btn">Import Quran Data</a></p>
                    <p><a href="?page=admin_backup_restore" class="btn">Backup & Restore</a></p>
                    <!-- Add other admin links -->
                </div>
            
            <?php elseif ($page === 'admin_data_import'): require_admin(); ?>
                <h2>Import Quran Data</h2>
                <div class="card">
                    <h3>Import Word Dictionary (data5.AM)</h3>
                    <p>CSV format: <code>word_id,quran_text,ur_meaning,en_meaning</code> (ID must be unique integer)</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="admin_import_data5">
                        <div class="form-group">
                            <label for="data5_file">Upload data5.AM (CSV)</label>
                            <input type="file" id="data5_file" name="data5_file" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Import Word Dictionary</button>
                    </form>
                </div>
                <div class="card">
                    <h3>Import Ayah Word Mapping (data2.AM)</h3>
                     <p>CSV format: <code>word_id,surah_number,ayah_number,word_position</code> (word_id must match an ID from Word Dictionary)</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="admin_import_data2">
                        <div class="form-group">
                            <label for="data2_file">Upload data2.AM (CSV)</label>
                            <input type="file" id="data2_file" name="data2_file" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Import Ayah Word Mapping</button>
                    </form>
                </div>

            <?php elseif ($page === 'admin_backup_restore'): require_admin(); ?>
                <h2>Backup & Restore Database</h2>
                <div class="card">
                    <h3>Backup</h3>
                    <p>Download a backup of the current database.</p>
                    <a href="?action=admin_backup_db" class="btn btn-accent">Download Backup</a>
                </div>
                <div class="card">
                    <h3>Restore</h3>
                    <p><strong>Warning:</strong> Restoring will overwrite the current database. Make sure you have a backup.</p>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="admin_restore_backup">
                        <div class="form-group">
                            <label for="backup_file">Upload Backup File (.sqlite)</label>
                            <input type="file" id="backup_file" name="backup_file" accept=".sqlite,.db" required>
                        </div>
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to restore? This will overwrite current data.');">Restore Database</button>
                    </form>
                </div>
            
            <?php elseif ($page === 'admin_user_management'): require_admin(); ?>
                <h2>User Management</h2>
                <div class="card table-container">
                    <table>
                        <thead>
                            <tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th><th>Created At</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                        <?php 
                        $users = $db->fetchAll("SELECT id, username, email, role, created_at FROM users ORDER BY id ASC");
                        foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo html_escape($user['username']); ?></td>
                                <td><?php echo html_escape($user['email']); ?></td>
                                <td><?php echo html_escape($user['role']); ?></td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td>
                                    <!-- Add actions like Edit Role, Delete User (with caution) -->
                                    <?php if ($user['id'] != $_SESSION['user_id']): // Prevent admin from altering their own role this way ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="admin_change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <select name="new_role" onchange="this.form.submit()">
                                            <option value="user" <?php if($user['role'] == 'user') echo 'selected'; ?>>User</option>
                                            <option value="admin" <?php if($user['role'] == 'admin') echo 'selected'; ?>>Admin</option>
                                        </select>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page === 'themes' || $page === 'hifz_tracker' || $page === 'recitation_log' || $page === 'search'): require_login(); ?>
                <h2><?php echo html_escape(ucfirst(str_replace('_', ' ', $page))); ?></h2>
                <p>This feature (<?php echo html_escape(ucfirst(str_replace('_', ' ', $page))); ?>) is under development. Check back soon!</p>
                <?php if ($page === 'hifz_tracker'): ?>
                    <p><a href="?page=quran_viewer" class="btn btn-primary">Go to Quran Viewer to update Hifz status per Ayah</a></p>
                    <!-- Add overview of hifz progress here -->
                <?php endif; ?>

            <?php else: ?>
                <h2>Page Not Found</h2>
                <p>The page you are looking for does not exist.</p>
            <?php endif; ?>

        </main>

        <footer>
            <p>© <?php echo date('Y'); ?> <?php echo APP_NAME; ?> by <?php echo APP_AUTHOR; ?> (Pakistani). All rights reserved.</p>
        </footer>
    </div>

    <!-- Word Detail Modal -->
    <div id="word-detail-modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal('word-detail-modal')">×</span>
            <h3 id="modal-word-arabic" class="modal-word-arabic"></h3>
            <div class="modal-body">
                <p><strong>English:</strong> <span id="modal-word-en"></span></p>
                <p><strong>Urdu:</strong> <span id="modal-word-ur" style="direction:rtl; text-align:right; font-family: var(--quran-font);"></span></p>
                <hr>
                <p><strong>Ayah Hifz Status:</strong> <span id="modal-ayah-hifz-status"></span></p>
                <!-- Future actions: Add note for word, Link to root, etc. -->
                <div class="ayah-actions">
                    <p>
                        <button class="btn btn-sm btn-accent" id="modal-add-tafsir-btn">Add/Edit Tafsir for Ayah</button>
                        <!-- Add Hifz update from modal? Maybe too complex for modal. -->
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // --- JavaScript ---
        let activeWordElement = null;

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            if (activeWordElement) {
                activeWordElement.classList.remove('highlighted');
                activeWordElement = null;
            }
        }

        function jumpToAyah() {
            const surahSelect = document.getElementById('surah_select');
            const surahNum = surahSelect.value;
            const ayahJumpInput = document.getElementById('ayah_jump_num');
            const ayahNum = ayahJumpInput.value;
            if (ayahNum) {
                window.location.href = '?page=quran_viewer&surah=' + surahNum + '#ayah-' + surahNum + '-' + ayahNum;
                // For a smoother scroll if already on page, but full reload is simpler for now
                // const targetAyah = document.getElementById('ayah-' + surahNum + '-' + ayahNum);
                // if(targetAyah) targetAyah.scrollIntoView({ behavior: 'smooth' });
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Highlight ayah if hash is present
            if (window.location.hash) {
                const targetId = window.location.hash.substring(1);
                const targetElement = document.getElementById(targetId);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    targetElement.style.boxShadow = '0 0 15px rgba(var(--accent-color-rgb), 0.5)'; // Temporary highlight
                    setTimeout(() => { targetElement.style.boxShadow = ''; }, 3000);
                }
            }
        });

        // Event delegation for arabic words
        document.addEventListener('click', async function(event) {
            if (event.target.classList.contains('arabic-word')) {
                if (activeWordElement) {
                    activeWordElement.classList.remove('highlighted');
                }
                activeWordElement = event.target;
                activeWordElement.classList.add('highlighted');

                const surah = event.target.dataset.surah;
                const ayah = event.target.dataset.ayah;
                const pos = event.target.dataset.pos;

                try {
                    const response = await fetch(`?ajax_action=get_word_details&surah=${surah}&ayah=${ayah}&pos=${pos}`);
                    if (!response.ok) throw new Error('Network response was not ok: ' + response.statusText);
                    const data = await response.json();

                    if (data.success) {
                        document.getElementById('modal-word-arabic').textContent = data.word.quran_text;
                        document.getElementById('modal-word-en').textContent = data.word.en_meaning || 'N/A';
                        document.getElementById('modal-word-ur').textContent = data.word.ur_meaning || 'N/A';
                        document.getElementById('modal-ayah-hifz-status').textContent = data.hifz_status || 'Not Tracked';
                        
                        const tafsirBtn = document.getElementById('modal-add-tafsir-btn');
                        tafsirBtn.onclick = () => {
                            closeModal('word-detail-modal');
                            // Scroll to ayah and open tafsir form
                            const ayahElement = document.getElementById(`ayah-${surah}-${ayah}`);
                            if (ayahElement) ayahElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            toggleTafsirForm(surah, ayah, true);
                        };

                        document.getElementById('word-detail-modal').classList.add('active');
                    } else {
                        alert('Error: ' + (data.message || 'Could not fetch word details.'));
                        if (activeWordElement) activeWordElement.classList.remove('highlighted');
                    }
                } catch (error) {
                    console.error('Error fetching word details:', error);
                    alert('Failed to fetch word details. Check console for errors.');
                    if (activeWordElement) activeWordElement.classList.remove('highlighted');
                }
            } else if (event.target.classList.contains('modal-close') || event.target.id === 'word-detail-modal') {
                 // Check if click is on modal background itself, not its content
                 if (event.target.id === 'word-detail-modal' || event.target.classList.contains('modal-close')) {
                    closeModal('word-detail-modal');
                 }
            }
        });

        function toggleTafsirForm(surah, ayah, forceOpen = false) {
            const formDiv = document.getElementById(`tafsir-form-${surah}-${ayah}`);
            if (formDiv) {
                if (forceOpen) {
                    formDiv.style.display = 'block';
                } else {
                    formDiv.style.display = formDiv.style.display === 'none' ? 'block' : 'none';
                }
                if (formDiv.style.display === 'block') {
                    formDiv.querySelector('textarea').focus();
                }
            }
        }

        // Close modal with ESC key
        document.addEventListener('keydown', function (event) {
            if (event.key === "Escape") {
                const activeModal = document.querySelector('.modal.active');
                if (activeModal) {
                    closeModal(activeModal.id);
                }
            }
        });

    </script>
</body>
</html>
<?php
// End of file
?>