<?php
// Author: Yasin Ullah, Pakistani
session_start();
$db = new PDO('sqlite:quran_app.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$surahNames = ['Al-Fatihah','Al-Baqarah','Aal-E-Imran','An-Nisa','Al-Maidah','Al-Anam','Al-Araf','Al-Anfal','At-Tawbah','Yunus','Hud','Yusuf','Ar-Rad','Ibrahim','Al-Hijr','An-Nahl','Al-Isra','Al-Kahf','Maryam','Taha','Al-Anbiya','Al-Hajj','Al-Muminun','An-Nur','Al-Furqan','Ash-Shuara','An-Naml','Al-Qasas','Al-Ankabut','Ar-Rum','Luqman','As-Sajdah','Al-Ahzab','Saba','Fatir','Ya-Sin','As-Saffat','Sad','Az-Zumar','Ghafir','Fussilat','Ash-Shura','Az-Zukhruf','Ad-Dukhan','Al-Jathiyah','Al-Ahqaf','Muhammad','Al-Fath','Al-Hujurat','Qaf','Adh-Dhariyat','At-Tur','An-Najm','Al-Qamar','Ar-Rahman','Al-Waqiah','Al-Hadid','Al-Mujadila','Al-Hashr','Al-Mumtahanah','As-Saff','Al-Jumuah','Al-Munafiqun','At-Taghabun','At-Talaq','At-Tahrim','Al-Mulk','Al-Qalam','Al-Haqqah','Al-Maarij','Nuh','Al-Jinn','Al-Muzzammil','Al-Muddaththir','Al-Qiyamah','Al-Insan','Al-Mursalat','An-Naba','An-Naziat','Abasa','At-Takwir','Al-Infitar','Al-Mutaffifin','Al-Inshiqaq','Al-Buruj','At-Tariq','Al-Ala','Al-Ghashiyah','Al-Fajr','Al-Balad','Ash-Shams','Al-Lail','Ad-Dhuha','Ash-Sharh','At-Tin','Al-Alaq','Al-Qadr','Al-Bayyinah','Az-Zalzalah','Al-Adiyat','Al-Qariah','At-Takathur','Al-Asr','Al-Humazah','Al-Fil','Quraysh','Al-Maun','Al-Kawthar','Al-Kafirun','An-Nasr','Al-Masad','Al-Ikhlas','Al-Falaq','An-Nas'];
$surahAyahCounts = [7,286,200,176,120,165,206,75,129,109,123,111,43,52,99,128,111,110,98,135,112,78,118,64,77,227,93,88,69,60,34,30,73,34,46,82,47,75,36,83,53,62,44,57,46,35,65,77,50,45,29,39,53,55,78,96,62,11,13,60,11,18,12,22,17,20,30,11,18,50,40,22,28,88,18,16,83,50,40,34,21,36,23,31,34,21,17,26,30,21,22,17,19,6,30,37,6,12,8,8,19,29,25,22,11,8,9,10,6,19,18,15,8,24,21,22,15,9,20,23,13,29];
$juzBoundariesData = [['surah'=>1,'ayah'=>1],['surah'=>2,'ayah'=>142],['surah'=>2,'ayah'=>253],['surah'=>3,'ayah'=>93],['surah'=>4,'ayah'=>24],['surah'=>4,'ayah'=>148],['surah'=>5,'ayah'=>82],['surah'=>6,'ayah'=>111],['surah'=>7,'ayah'=>88],['surah'=>8,'ayah'=>41],['surah'=>9,'ayah'=>93],['surah'=>11,'ayah'=>6],['surah'=>12,'ayah'=>53],['surah'=>15,'ayah'=>1],['surah'=>17,'ayah'=>1],['surah'=>18,'ayah'=>75],['surah'=>21,'ayah'=>1],['surah'=>23,'ayah'=>1],['surah'=>25,'ayah'=>21],['surah'=>27,'ayah'=>56],['surah'=>29,'ayah'=>46],['surah'=>33,'ayah'=>31],['surah'=>36,'ayah'=>28],['surah'=>39,'ayah'=>32],['surah'=>41,'ayah'=>47],['surah'=>46,'ayah'=>1],['surah'=>51,'ayah'=>31],['surah'=>58,'ayah'=>1],['surah'=>67,'ayah'=>1],['surah'=>78,'ayah'=>1]];

function initDb1() {
    global $db;
    $db->exec("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT NOT NULL CHECK(role IN ('admin', 'editor', 'contributor', 'reader')))");
    $db->exec("CREATE TABLE IF NOT EXISTS quran_text (id INTEGER PRIMARY KEY, surah INT, ayah INT, arabic_text TEXT, english_translation TEXT, urdu_translation TEXT, pashto_translation TEXT, bangali_translation TEXT, UNIQUE(surah, ayah))");
    $db->exec("CREATE TABLE IF NOT EXISTS quran_words (id INTEGER PRIMARY KEY, surah INT, ayah INT, word_position INT, arabic_word TEXT, english_meaning TEXT, urdu_meaning TEXT, pashto_meaning TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS suggestions (id INTEGER PRIMARY KEY, user_id INT, type TEXT CHECK(type IN ('ayah_translation', 'word_meaning')), target_id INT, language TEXT, suggested_text TEXT, status TEXT DEFAULT 'pending')");
    $db->exec("CREATE TABLE IF NOT EXISTS tafsir (id INTEGER PRIMARY KEY, user_id INT, surah INT, ayah INT, notes TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS themes (id INTEGER PRIMARY KEY, user_id INT, name TEXT, description TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS theme_links (id INTEGER PRIMARY KEY, user_id INT, theme_id INT, surah INT, ayah INT, notes TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS hifz (id INTEGER PRIMARY KEY, user_id INT, surah INT, ayah INT, status TEXT, next_review_date TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS recitation_logs (id INTEGER PRIMARY KEY, user_id INT, surah INT, ayah_start INT, ayah_end INT, qari TEXT, date TEXT, notes TEXT)");
    $db->exec("CREATE TABLE IF NOT EXISTS goals (id INTEGER PRIMARY KEY, user_id INT, title TEXT, type TEXT, target TEXT, target_count INT, is_complete INT)");
    
    $stmt = $db->query("SELECT COUNT(*) FROM quran_text");
    if ($stmt->fetchColumn() == 0) {
        seedData1();
    }
}

function seedData1() {
    global $db;
    $quranData = [
        [1,1,"بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ","In the name of Allah, the Entirely Merciful, the Especially Merciful.","اللہ کے نام سے جو نہایت رحم کرنے والا رحیم ہے","د الله په نوم چې ډېر مهربان، رحیم دی","পরম করুণাময় ও অসীম দয়ালু আল্লাহর নামে"],
        [1,2,"الْحَمْدُ لِلَّهِ رَبِّ الْعَالَمِينَ","[All] praise is [due] to Allah, Lord of the worlds","تمام تعریف اللہ کے لیے ہے جو تمام جہانوں کا رب ہے","ټول ستاینې د الله لپاره دي چې د ټولو عالمونو رب دی","সমস্ত প্রশংসা আল্লাহর, যিনি সকল জগতের প্রতিপালক"],
        [1,3,"الرَّحْمَٰنِ الرَّحِيمِ","The Entirely Merciful, the Especially Merciful","نہایت رحم کرنے والا مہربان","ډېر مهربان، رحیم","পরম করুণাময়, অতি দয়ালু"],
        [1,4,"مَالِكِ يَوْمِ الدِّينِ","Sovereign of the Day of Recompense","بدلہ کے دن کا مالک","د قیامت د ورځې مالک","বিচার দিনের মালিক"]
    ];
    
    $wordsData = [
        [1,1,1,"بِسْمِ","In the name","نام سے","په نوم"],
        [1,1,2,"اللَّهِ","Allah","اللہ","الله"],
        [1,1,3,"الرَّحْمَٰنِ","The Entirely Merciful","رحمٰن","رحمن"],
        [1,1,4,"الرَّحِيمِ","The Especially Merciful","رحیم","رحیم"]
    ];
    
    foreach ($quranData as $row) {
        $stmt = $db->prepare("INSERT INTO quran_text (surah, ayah, arabic_text, english_translation, urdu_translation, pashto_translation, bangali_translation) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute($row);
    }
    
    foreach ($wordsData as $row) {
        $stmt = $db->prepare("INSERT INTO quran_words (surah, ayah, word_position, arabic_word, english_meaning, urdu_meaning, pashto_meaning) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute($row);
    }
}

function checkAuth1() {
    return isset($_SESSION['user_id']);
}

function checkRole1($role) {
    return isset($_SESSION['role']) && 
           (($_SESSION['role'] === 'admin') || 
            ($_SESSION['role'] === 'editor' && in_array($role, ['editor','contributor','reader'])) ||
            ($_SESSION['role'] === 'contributor' && in_array($role, ['contributor','reader'])) ||
            ($_SESSION['role'] === 'reader' && $role === 'reader'));
}

initDb1();

if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'login':
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$_POST['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($_POST['password'], $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
            }
            break;
            
        case 'register':
            try {
                $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'reader')");
                $stmt->execute([$_POST['username'], $hash]);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Username already exists']);
            }
            break;
            
        case 'logout':
            session_destroy();
            echo json_encode(['success' => true]);
            break;
            
        case 'get_ayah_data':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM quran_text WHERE surah = ? AND ayah = ?");
            $stmt->execute([$_POST['surah'], $_POST['ayah']]);
            $ayah = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare("SELECT * FROM quran_words WHERE surah = ? AND ayah = ? ORDER BY word_position");
            $stmt->execute([$_POST['surah'], $_POST['ayah']]);
            $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['ayah' => $ayah, 'words' => $words]);
            break;
            
        case 'save_tafsir':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("INSERT OR REPLACE INTO tafsir (user_id, surah, ayah, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $_POST['surah'], $_POST['ayah'], $_POST['notes']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'get_tafsir':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("SELECT notes FROM tafsir WHERE user_id = ? AND surah = ? AND ayah = ?");
            $stmt->execute([$_SESSION['user_id'], $_POST['surah'], $_POST['ayah']]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['notes' => $result ? $result['notes'] : '']);
            break;
            
        case 'add_theme':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO themes (user_id, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $_POST['name'], $_POST['description']]);
            echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
            break;
            
        case 'get_themes':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM themes WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'link_theme':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO theme_links (user_id, theme_id, surah, ayah, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $_POST['theme_id'], $_POST['surah'], $_POST['ayah'], $_POST['notes']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'search_root':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $root = $_POST['root'];
            $stmt = $db->prepare("SELECT * FROM quran_words WHERE arabic_word LIKE ?");
            $stmt->execute(['%'.$root.'%']);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'update_hifz':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("INSERT OR REPLACE INTO hifz (user_id, surah, ayah, status, next_review_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $_POST['surah'], $_POST['ayah'], $_POST['status'], $_POST['date']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'get_hifz_data':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM hifz WHERE user_id = ? AND surah = ?");
            $stmt->execute([$_SESSION['user_id'], $_POST['surah']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'add_goal':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO goals (user_id, title, type, target, target_count, is_complete) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$_SESSION['user_id'], $_POST['title'], $_POST['type'], $_POST['target'], $_POST['target_count']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'get_goals':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stmt = $db->prepare("SELECT * FROM goals WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'search_quran':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $query = $_POST['query'];
            $results = [];
            
            if (isset($_POST['search_arabic'])) {
                $stmt = $db->prepare("SELECT surah, ayah, arabic_text FROM quran_text WHERE arabic_text LIKE ?");
                $stmt->execute(['%'.$query.'%']);
                $results['arabic'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if (isset($_POST['search_english'])) {
                $stmt = $db->prepare("SELECT surah, ayah, english_translation FROM quran_text WHERE english_translation LIKE ?");
                $stmt->execute(['%'.$query.'%']);
                $results['english'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            echo json_encode($results);
            break;
            
        case 'get_stats':
            if (!checkAuth1()) {
                echo json_encode(['error' => 'Not authenticated']);
                break;
            }
            
            $stats = [];
            $stmt = $db->prepare("SELECT COUNT(*) FROM hifz WHERE user_id = ? AND status = 'memorized'");
            $stmt->execute([$_SESSION['user_id']]);
            $stats['memorized'] = $stmt->fetchColumn();
            
            $stmt = $db->prepare("SELECT COUNT(*) FROM tafsir WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $stats['tafsir'] = $stmt->fetchColumn();
            
            echo json_encode($stats);
            break;
            
        case 'submit_suggestion':
            if (!checkAuth1() || !checkRole1('contributor')) {
                echo json_encode(['error' => 'Not authorized']);
                break;
            }
            
            $stmt = $db->prepare("INSERT INTO suggestions (user_id, type, target_id, language, suggested_text) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $_POST['type'], $_POST['target_id'], $_POST['language'], $_POST['text']]);
            echo json_encode(['success' => true]);
            break;
            
        case 'get_suggestions':
            if (!checkAuth1() || !checkRole1('editor')) {
                echo json_encode(['error' => 'Not authorized']);
                break;
            }
            
            $stmt = $db->query("SELECT * FROM suggestions WHERE status = 'pending'");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;
            
        case 'approve_suggestion':
            if (!checkAuth1() || !checkRole1('editor')) {
                echo json_encode(['error' => 'Not authorized']);
                break;
            }
            
            $stmt = $db->prepare("UPDATE suggestions SET status = 'approved' WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

if (!checkAuth1()) {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study App - Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); width: 100%; max-width: 400px; }
        .login-container h2 { text-align: center; margin-bottom: 30px; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; color: #555; }
        .form-group input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .btn { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin-bottom: 10px; }
        .btn:hover { background: #5a6fd8; }
        .toggle-form { text-align: center; margin-top: 20px; color: #666; cursor: pointer; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 id="form-title">Login</h2>
        <form id="auth-form">
            <div class="form-group">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn" id="submit-btn">Login</button>
        </form>
        <div class="toggle-form" id="toggle-form">Don't have an account? Register</div>
    </div>

    <script>
        let isLogin = true;
        const form = document.getElementById('auth-form');
        const title = document.getElementById('form-title');
        const submitBtn = document.getElementById('submit-btn');
        const toggleForm = document.getElementById('toggle-form');

        toggleForm.addEventListener('click', () => {
            isLogin = !isLogin;
            if (isLogin) {
                title.textContent = 'Login';
                submitBtn.textContent = 'Login';
                toggleForm.textContent = "Don't have an account? Register";
            } else {
                title.textContent = 'Register';
                submitBtn.textContent = 'Register';
                toggleForm.textContent = 'Already have an account? Login';
            }
        });

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', isLogin ? 'login' : 'register');
            formData.append('username', document.getElementById('username').value);
            formData.append('password', document.getElementById('password').value);

            try {
                const response = await fetch('', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    if (isLogin) {
                        location.reload();
                    } else {
                        alert('Registration successful! Please login.');
                        toggleForm.click();
                    }
                } else {
                    alert(result.message || 'An error occurred');
                }
            } catch (error) {
                alert('Network error occurred');
            }
        });
    </script>
</body>
</html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study App</title>
    <script src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --background: #f8f9fa;
            --text: #2c3e50;
            --sidebar: #34495e;
        }

        .theme-serene {
            --primary: #27ae60;
            --secondary: #2ecc71;
            --accent: #f39c12;
            --background: #f8fff9;
            --text: #2c3e50;
            --sidebar: #34a853;
        }

        .theme-manuscript {
            --primary: #8b4513;
            --secondary: #daa520;
            --accent: #cd853f;
            --background: #fdf6e3;
            --text: #5d4037;
            --sidebar: #6f4e37;
        }

        .theme-holo {
            --primary: #1a1a1a;
            --secondary: #00ffff;
            --accent: #ff00ff;
            --background: #000;
            --text: #00ffff;
            --sidebar: #333;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: var(--background); color: var(--text); transition: all 0.3s ease; }

        .app-container { display: flex; height: 100vh; }
        .sidebar { width: 250px; background: var(--sidebar); color: white; padding: 20px 0; overflow-y: auto; }
        .sidebar h3 { padding: 0 20px; margin-bottom: 20px; font-size: 18px; }
        .sidebar ul { list-style: none; }
        .sidebar li { margin-bottom: 5px; }
        .sidebar a { display: block; padding: 12px 20px; color: white; text-decoration: none; transition: background 0.2s; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); }

        .main-content { flex: 1; padding: 20px; overflow-y: auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { color: var(--primary); font-size: 28px; }
        .theme-selector { display: flex; gap: 10px; }
        .theme-btn { width: 30px; height: 30px; border-radius: 50%; border: 2px solid #fff; cursor: pointer; }
        .theme-serene { background: #27ae60; }
        .theme-manuscript { background: #daa520; }
        .theme-holo { background: #00ffff; }

        .module { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; display: none; }
        .module.active { display: block; }
        .module h2 { color: var(--primary); margin-bottom: 20px; font-size: 24px; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        .form-group textarea { min-height: 100px; resize: vertical; }

        .btn { background: var(--secondary); color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 14px; margin: 5px; transition: background 0.2s; }
        .btn:hover { opacity: 0.9; }
        .btn-primary { background: var(--primary); }
        .btn-accent { background: var(--accent); }

        .ayah-display { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid var(--secondary); }
        .arabic-text { font-size: 24px; line-height: 1.8; text-align: right; margin-bottom: 15px; font-family: 'Amiri', serif; }
        .translation { font-size: 16px; line-height: 1.6; margin-bottom: 10px; }
        .word-by-word { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .word { background: var(--background); padding: 8px 12px; border-radius: 5px; border: 1px solid #ddd; cursor: pointer; text-align: center; min-width: 80px; }
        .word:hover { background: var(--secondary); color: white; }
        .word .arabic { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
        .word .meaning { font-size: 12px; color: #666; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: var(--primary); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-card h3 { font-size: 32px; margin-bottom: 10px; }
        .stat-card p { opacity: 0.9; }

        .search-results { max-height: 400px; overflow-y: auto; }
        .result-item { background: #f8f9fa; padding: 15px; margin-bottom: 10px; border-radius: 5px; border-left: 3px solid var(--secondary); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 1000; }
        .modal-content { background: white; margin: 5% auto; padding: 30px; width: 90%; max-width: 800px; border-radius: 10px; max-height: 80vh; overflow-y: auto; }
        .modal.active { display: block; }
        .close-modal { float: right; font-size: 28px; cursor: pointer; color: #aaa; }
        .close-modal:hover { color: black; }

        .fullscreen-reader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--background); z-index: 2000; display: none; padding: 20px; }
        .fullscreen-reader.active { display: block; }
        .reader-controls { background: var(--primary); color: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
        .reader-text { font-size: 28px; line-height: 2; text-align: right; max-width: 800px; margin: 0 auto; }
        .paged-view { height: calc(100vh - 120px); overflow: hidden; display: flex; flex-direction: column; justify-content: center; }
        .continuous-view { height: calc(100vh - 120px); overflow-y: auto; }

        .game-selection { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .game-card { background: var(--secondary); color: white; padding: 30px; border-radius: 10px; text-align: center; cursor: pointer; transition: transform 0.2s; }
        .game-card:hover { transform: translateY(-5px); }

        .quiz-container { text-align: center; }
        .quiz-question { font-size: 24px; margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; }
        .quiz-options { display: flex; flex-direction: column; gap: 10px; margin-bottom: 20px; }
        .quiz-option { padding: 15px; background: #e9ecef; border-radius: 5px; cursor: pointer; transition: background 0.2s; }
        .quiz-option:hover { background: var(--secondary); color: white; }

        .drag-drop-container { min-height: 200px; border: 2px dashed #ddd; border-radius: 10px; padding: 20px; margin: 20px 0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: center; }
        .draggable-word { background: var(--secondary); color: white; padding: 10px 15px; border-radius: 5px; cursor: grab; user-select: none; }
        .draggable-word:active { cursor: grabbing; }

        @media (max-width: 768px) {
            .app-container { flex-direction: column; }
            .sidebar { width: 100%; height: auto; }
            .main-content { padding: 15px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="theme-serene">
    <div class="app-container">
        <nav class="sidebar">
            <h3>Quran Study App</h3>
            <ul>
                <li><a href="#" onclick="showModule('quran-viewer')" class="active">Quran Viewer</a></li>
                <li><a href="#" onclick="showModule('tafsir')">Personal Tafsir</a></li>
                <li><a href="#" onclick="showModule('themes')">Thematic Linker</a></li>
                <li><a href="#" onclick="showModule('root-analyzer')">Root Word Analyzer</a></li>
                <li><a href="#" onclick="showModule('hifz-hub')">Hifz Hub</a></li>
                <li><a href="#" onclick="showModule('recitation')">Recitation Log</a></li>
                <li><a href="#" onclick="showModule('goals')">Study Goals</a></li>
                <li><a href="#" onclick="showModule('search')">Advanced Search</a></li>
                <li><a href="#" onclick="showModule('reports')">Reporting</a></li>
                <?php if (checkRole1('admin')): ?>
                <li><a href="#" onclick="showModule('admin')">Admin Panel</a></li>
                <?php endif; ?>
                <li><a href="#" onclick="openFullscreenReader()">Immersive Reader</a></li>
                <li><a href="#" onclick="openGamesModal()">Quranic Games</a></li>
                <li><a href="#" onclick="logout()">Logout</a></li>
            </ul>
        </nav>

        <main class="main-content">
            <header class="header">
                <h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
                <div class="theme-selector">
                    <div class="theme-btn theme-serene" onclick="changeTheme('serene')"></div>
                    <div class="theme-btn theme-manuscript" onclick="changeTheme('manuscript')"></div>
                    <div class="theme-btn theme-holo" onclick="changeTheme('holo')"></div>
                </div>
            </header>

            <!-- Quran Viewer Module -->
            <section id="quran-viewer" class="module active">
                <h2>Quran Viewer</h2>
                <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label for="surah-select">Surah:</label>
                        <select id="surah-select" onchange="loadAyah()">
                            <?php foreach ($surahNames as $i => $name): ?>
                            <option value="<?php echo $i+1; ?>"><?php echo ($i+1) . '. ' . $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ayah-select">Ayah:</label>
                        <select id="ayah-select" onchange="loadAyah()">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="translation-select">Translation:</label>
                        <select id="translation-select" onchange="loadAyah()">
                            <option value="english">English</option>
                            <option value="urdu">Urdu</option>
                            <option value="pashto">Pashto</option>
                            <option value="bangali">Bengali</option>
                        </select>
                    </div>
                </div>
                <div id="ayah-display"></div>
            </section>

            <!-- Personal Tafsir Module -->
            <section id="tafsir" class="module">
                <h2>Personal Tafsir</h2>
                <div class="form-group">
                    <label for="tafsir-notes">Your Notes:</label>
                    <textarea id="tafsir-notes" placeholder="Write your tafsir notes here..."></textarea>
                </div>
                <button class="btn btn-primary" onclick="saveTafsir()">Save Notes</button>
            </section>

            <!-- Thematic Linker Module -->
            <section id="themes" class="module">
                <h2>Thematic Linker</h2>
                <div class="form-group">
                    <label for="theme-name">New Theme Name:</label>
                    <input type="text" id="theme-name" placeholder="Enter theme name">
                </div>
                <div class="form-group">
                    <label for="theme-desc">Description:</label>
                    <textarea id="theme-desc" placeholder="Enter theme description"></textarea>
                </div>
                <button class="btn btn-primary" onclick="addTheme()">Create Theme</button>
                
                <h3>Link Current Ayah to Theme</h3>
                <select id="theme-select"></select>
                <textarea id="link-notes" placeholder="Notes for this link"></textarea>
                <button class="btn btn-secondary" onclick="linkTheme()">Link Ayah</button>
            </section>

            <!-- Root Word Analyzer Module -->
            <section id="root-analyzer" class="module">
                <h2>Root Word Analyzer</h2>
                <div class="form-group">
                    <label for="root-input">Arabic Root:</label>
                    <input type="text" id="root-input" placeholder="Enter Arabic root (e.g., كتب)">
                </div>
                <button class="btn btn-primary" onclick="searchRoot()">Search</button>
                <div id="root-results"></div>
                <div id="network-graph" style="height: 400px; margin-top: 20px;"></div>
            </section>

            <!-- Hifz Hub Module -->
            <section id="hifz-hub" class="module">
                <h2>Hifz Hub</h2>
                <div class="form-group">
                    <label for="hifz-surah">Select Surah:</label>
                    <select id="hifz-surah" onchange="loadHifzData()">
                        <?php foreach ($surahNames as $i => $name): ?>
                        <option value="<?php echo $i+1; ?>"><?php echo ($i+1) . '. ' . $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div id="hifz-list"></div>
            </section>

            <!-- Study Goals Module -->
            <section id="goals" class="module">
                <h2>Study Goals</h2>
                <div class="form-group">
                    <label for="goal-title">Goal Title:</label>
                    <input type="text" id="goal-title" placeholder="Enter goal title">
                </div>
                <div class="form-group">
                    <label for="goal-type">Goal Type:</label>
                    <select id="goal-type">
                        <option value="memorization">Memorization</option>
                        <option value="reading">Reading</option>
                        <option value="study">Study</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="goal-target">Target Count:</label>
                    <input type="number" id="goal-target" placeholder="Enter target count">
                </div>
                <button class="btn btn-primary" onclick="addGoal()">Add Goal</button>
                <div id="goals-list"></div>
            </section>

            <!-- Advanced Search Module -->
            <section id="search" class="module">
                <h2>Advanced Search</h2>
                <div class="form-group">
                    <input type="text" id="search-query" placeholder="Enter search query">
                </div>
                <div class="form-group">
                    <label><input type="checkbox" id="search-arabic"> Arabic Text</label>
                    <label><input type="checkbox" id="search-english" checked> English Translation</label>
                    <label><input type="checkbox" id="search-urdu"> Urdu Translation</label>
                    <label><input type="checkbox" id="search-tafsir"> Your Tafsir Notes</label>
                </div>
                <button class="btn btn-primary" onclick="searchQuran()">Search</button>
                <div id="search-results" class="search-results"></div>
            </section>

            <!-- Reporting Module -->
            <section id="reports" class="module">
                <h2>Reporting Dashboard</h2>
                <div class="stats-grid" id="stats-grid"></div>
                <button class="btn btn-primary" onclick="loadStats()">Refresh Stats</button>
            </section>

            <?php if (checkRole1('admin')): ?>
            <!-- Admin Panel Module -->
            <section id="admin" class="module">
                <h2>Admin Panel</h2>
                <div id="users-list"></div>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <!-- Fullscreen Reader -->
    <div id="fullscreen-reader" class="fullscreen-reader">
        <div class="reader-controls">
            <button class="btn" onclick="closeFullscreenReader()">Close</button>
            <label>Font Size: <input type="range" id="font-size" min="16" max="48" value="28" onchange="updateReaderSettings()"></label>
            <label>Lines Per Page: <input type="number" id="lines-per-page" min="5" max="20" value="10" onchange="updateReaderSettings()"></label>
            <label><input type="checkbox" id="continuous-mode" onchange="updateReaderSettings()"> Continuous Mode</label>
            <label><input type="checkbox" id="show-tajweed" onchange="updateReaderSettings()"> Show Tajweed</label>
        </div>
        <div id="reader-content" class="reader-text paged-view"></div>
    </div>

    <!-- Games Modal -->
    <div id="games-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeGamesModal()">&times;</span>
            <h2>Quranic Games</h2>
            <div id="games-menu" class="game-selection">
                <div class="game-card" onclick="startGame('ayah-match')">
                    <h3>Ayah-Translation Match</h3>
                    <p>Match Arabic ayahs with their translations</p>
                </div>
                <div class="game-card" onclick="startGame('ayah-jumble')">
                    <h3>Ayah Jumble</h3>
                    <p>Arrange scrambled words of an ayah</p>
                </div>
                <div class="game-card" onclick="startGame('word-whiz')">
                    <h3>Word Whiz</h3>
                    <p>Match Arabic words with meanings</p>
                </div>
            </div>
            <div id="game-content" style="display: none;"></div>
        </div>
    </div>

    <script>
        const surahNames = <?php echo json_encode($surahNames); ?>;
        const surahAyahCounts = <?php echo json_encode($surahAyahCounts); ?>;
        const userRole = '<?php echo $_SESSION['role']; ?>';
        let currentSurah = 1;
        let currentAyah = 1;
        let currentAyahData = null;

        async function apiCall(action, data = {}) {
            const formData = new FormData();
            formData.append('action', action);
            Object.keys(data).forEach(key => formData.append(key, data[key]));
            
            const response = await fetch('', { method: 'POST', body: formData });
            return await response.json();
        }

        function showModule(moduleId) {
            document.querySelectorAll('.module').forEach(m => m.classList.remove('active'));
            document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
            document.getElementById(moduleId).classList.add('active');
            event.target.classList.add('active');
            
            if (moduleId === 'tafsir') loadTafsir();
            if (moduleId === 'themes') loadThemes();
            if (moduleId === 'goals') loadGoals();
            if (moduleId === 'hifz-hub') loadHifzData();
            if (moduleId === 'reports') loadStats();
        }

        function changeTheme(theme) {
            document.body.className = `theme-${theme}`;
        }

        async function loadAyah() {
            currentSurah = parseInt(document.getElementById('surah-select').value);
            currentAyah = parseInt(document.getElementById('ayah-select').value);
            
            const result = await apiCall('get_ayah_data', { surah: currentSurah, ayah: currentAyah });
            currentAyahData = result;
            
            updateAyahSelect();
            displayAyah();
            loadTafsir();
        }

        function updateAyahSelect() {
            const ayahSelect = document.getElementById('ayah-select');
            const ayahCount = surahAyahCounts[currentSurah - 1];
            ayahSelect.innerHTML = '';
            for (let i = 1; i <= ayahCount; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                if (i === currentAyah) option.selected = true;
                ayahSelect.appendChild(option);
            }
        }

        function displayAyah() {
            if (!currentAyahData || !currentAyahData.ayah) return;
            
            const display = document.getElementById('ayah-display');
            const translation = document.getElementById('translation-select').value;
            const ayah = currentAyahData.ayah;
            
            let translationText = '';
            switch(translation) {
                case 'english': translationText = ayah.english_translation; break;
                case 'urdu': translationText = ayah.urdu_translation; break;
                case 'pashto': translationText = ayah.pashto_translation; break;
                case 'bangali': translationText = ayah.bangali_translation; break;
            }
            
            let wordsHtml = '';
            if (currentAyahData.words) {
                wordsHtml = '<div class="word-by-word">';
                currentAyahData.words.forEach(word => {
                    wordsHtml += `<div class="word" onclick="showWordMeaning('${word.arabic_word}', '${word.english_meaning}')">
                        <div class="arabic">${word.arabic_word}</div>
                        <div class="meaning">${word.english_meaning}</div>
                    </div>`;
                });
                wordsHtml += '</div>';
            }
            
            display.innerHTML = `
                <div class="ayah-display">
                    <div class="arabic-text">${ayah.arabic_text}</div>
                    <div class="translation">${translationText}</div>
                    ${wordsHtml}
                    ${userRole === 'contributor' || userRole === 'editor' ? '<button class="btn" onclick="suggestEdit()">Suggest Edit</button>' : ''}
                    ${userRole === 'editor' || userRole === 'admin' ? '<button class="btn" onclick="directEdit()">Edit</button>' : ''}
                </div>
            `;
        }

        async function saveTafsir() {
            const notes = document.getElementById('tafsir-notes').value;
            const result = await apiCall('save_tafsir', { 
                surah: currentSurah, 
                ayah: currentAyah, 
                notes: notes 
            });
            if (result.success) {
                alert('Tafsir saved successfully!');
            }
        }

        async function loadTafsir() {
            const result = await apiCall('get_tafsir', { 
                surah: currentSurah, 
                ayah: currentAyah 
            });
            document.getElementById('tafsir-notes').value = result.notes || '';
        }

        async function addTheme() {
            const name = document.getElementById('theme-name').value;
            const description = document.getElementById('theme-desc').value;
            
            if (!name) {
                alert('Please enter a theme name');
                return;
            }
            
            const result = await apiCall('add_theme', { name: name, description: description });
            if (result.success) {
                alert('Theme created successfully!');
                document.getElementById('theme-name').value = '';
                document.getElementById('theme-desc').value = '';
                loadThemes();
            }
        }

        async function loadThemes() {
            const result = await apiCall('get_themes');
            const select = document.getElementById('theme-select');
            select.innerHTML = '<option value="">Select a theme</option>';
            
            result.forEach(theme => {
                const option = document.createElement('option');
                option.value = theme.id;
                option.textContent = theme.name;
                select.appendChild(option);
            });
        }

        async function linkTheme() {
            const themeId = document.getElementById('theme-select').value;
            const notes = document.getElementById('link-notes').value;
            
            if (!themeId) {
                alert('Please select a theme');
                return;
            }
            
            const result = await apiCall('link_theme', {
                theme_id: themeId,
                surah: currentSurah,
                ayah: currentAyah,
                notes: notes
            });
            
            if (result.success) {
                alert('Ayah linked to theme successfully!');
                document.getElementById('link-notes').value = '';
            }
        }

        async function searchRoot() {
            const root = document.getElementById('root-input').value;
            if (!root) return;
            
            const result = await apiCall('search_root', { root: root });
            
            const resultsDiv = document.getElementById('root-results');
            resultsDiv.innerHTML = '<h3>Search Results:</h3>';
            
            result.forEach(word => {
                resultsDiv.innerHTML += `
                    <div class="result-item">
                        <strong>${word.arabic_word}</strong> - ${word.english_meaning}
                        <br><small>Surah ${word.surah}, Ayah ${word.ayah}</small>
                    </div>
                `;
            });
            
            // Create network graph
            if (result.length > 0) {
                const nodes = result.map(word => ({
                    id: word.id,
                    label: word.arabic_word,
                    title: word.english_meaning
                }));
                
                const edges = [];
                const data = { nodes: nodes, edges: edges };
                const options = {
                    nodes: {
                        shape: 'circle',
                        size: 20,
                        font: { size: 16 }
                    }
                };
                
                const container = document.getElementById('network-graph');
                new vis.Network(container, data, options);
            }
        }

        async function loadHifzData() {
            const surah = parseInt(document.getElementById('hifz-surah').value);
            const result = await apiCall('get_hifz_data', { surah: surah });
            
            const container = document.getElementById('hifz-list');
            container.innerHTML = '<h3>Memorization Progress:</h3>';
            
            const ayahCount = surahAyahCounts[surah - 1];
            for (let i = 1; i <= ayahCount; i++) {
                const hifzData = result.find(h => h.ayah === i);
                const status = hifzData ? hifzData.status : 'not_started';
                
                container.innerHTML += `
                    <div style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 5px;">
                        <span style="width: 100px;">Ayah ${i}:</span>
                        <select onchange="updateHifzStatus(${surah}, ${i}, this.value)">
                            <option value="not_started" ${status === 'not_started' ? 'selected' : ''}>Not Started</option>
                            <option value="in_progress" ${status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                            <option value="memorized" ${status === 'memorized' ? 'selected' : ''}>Memorized</option>
                        </select>
                    </div>
                `;
            }
        }

        async function updateHifzStatus(surah, ayah, status) {
            const result = await apiCall('update_hifz', {
                surah: surah,
                ayah: ayah,
                status: status,
                date: new Date().toISOString().split('T')[0]
            });
            
            if (!result.success) {
                alert('Error updating status');
            }
        }

        async function addGoal() {
            const title = document.getElementById('goal-title').value;
            const type = document.getElementById('goal-type').value;
            const target = document.getElementById('goal-target').value;
            
            if (!title || !target) {
                alert('Please fill all fields');
                return;
            }
            
            const result = await apiCall('add_goal', {
                title: title,
                type: type,
                target: type,
                target_count: parseInt(target)
            });
            
            if (result.success) {
                alert('Goal added successfully!');
                document.getElementById('goal-title').value = '';
                document.getElementById('goal-target').value = '';
                loadGoals();
            }
        }

        async function loadGoals() {
            const result = await apiCall('get_goals');
            const container = document.getElementById('goals-list');
            container.innerHTML = '<h3>Your Goals:</h3>';
            
            result.forEach(goal => {
                const progress = Math.min(100, (goal.current_count || 0) / goal.target_count * 100);
                container.innerHTML += `
                    <div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <h4>${goal.title}</h4>
                        <p>Type: ${goal.type} | Target: ${goal.target_count}</p>
                        <div style="background: #ddd; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="background: var(--secondary); height: 100%; width: ${progress}%; transition: width 0.3s;"></div>
                        </div>
                        <small>${progress.toFixed(1)}% complete</small>
                    </div>
                `;
            });
        }

        async function searchQuran() {
            const query = document.getElementById('search-query').value;
            if (!query) return;
            
            const searchData = { query: query };
            if (document.getElementById('search-arabic').checked) searchData.search_arabic = true;
            if (document.getElementById('search-english').checked) searchData.search_english = true;
            if (document.getElementById('search-urdu').checked) searchData.search_urdu = true;
            
            const result = await apiCall('search_quran', searchData);
            
            const container = document.getElementById('search-results');
            container.innerHTML = '<h3>Search Results:</h3>';
            
            ['arabic', 'english', 'urdu'].forEach(lang => {
                if (result[lang]) {
                    result[lang].forEach(item => {
                        container.innerHTML += `
                            <div class="result-item">
                                <strong>Surah ${item.surah}, Ayah ${item.ayah}</strong>
                                <p>${item[lang + (lang === 'arabic' ? '_text' : '_translation')]}</p>
                            </div>
                        `;
                    });
                }
            });
        }

        async function loadStats() {
            const result = await apiCall('get_stats');
            
            const container = document.getElementById('stats-grid');
            container.innerHTML = `
                <div class="stat-card">
                    <h3>${result.memorized || 0}</h3>
                    <p>Ayahs Memorized</p>
                </div>
                <div class="stat-card">
                    <h3>${result.tafsir || 0}</h3>
                    <p>Tafsir Notes Written</p>
                </div>
                <div class="stat-card">
                    <h3>0</h3>
                    <p>Study Sessions</p>
                </div>
                <div class="stat-card">
                    <h3>0</h3>
                    <p>Goals Completed</p>
                </div>
            `;
        }

        function openFullscreenReader() {
            document.getElementById('fullscreen-reader').classList.add('active');
            loadReaderContent();
        }

        function closeFullscreenReader() {
            document.getElementById('fullscreen-reader').classList.remove('active');
        }

        function updateReaderSettings() {
            const fontSize = document.getElementById('font-size').value;
			const linesPerPage = document.getElementById('lines-per-page').value;
            const continuous = document.getElementById('continuous-mode').checked;
            const showTajweed = document.getElementById('show-tajweed').checked;
            
            const content = document.getElementById('reader-content');
            content.style.fontSize = fontSize + 'px';
            content.style.lineHeight = continuous ? '1.8' : (2.5 / linesPerPage);
            
            if (continuous) {
                content.className = 'reader-text continuous-view';
            } else {
                content.className = 'reader-text paged-view';
            }
            
            loadReaderContent();
        }

        async function loadReaderContent() {
            const content = document.getElementById('reader-content');
            if (currentAyahData && currentAyahData.ayah) {
                content.innerHTML = `
                    <div style="text-align: center; margin-bottom: 30px;">
                        <h2>سورة ${surahNames[currentSurah - 1]}</h2>
                    </div>
                    <div style="text-align: right; line-height: 2.5;">
                        ${currentAyahData.ayah.arabic_text}
                    </div>
                `;
            }
        }

        function openGamesModal() {
            document.getElementById('games-modal').classList.add('active');
            document.getElementById('games-menu').style.display = 'grid';
            document.getElementById('game-content').style.display = 'none';
        }

        function closeGamesModal() {
            document.getElementById('games-modal').classList.remove('active');
        }

        function startGame(gameType) {
            document.getElementById('games-menu').style.display = 'none';
            document.getElementById('game-content').style.display = 'block';
            
            switch(gameType) {
                case 'ayah-match':
                    startAyahMatchGame();
                    break;
                case 'ayah-jumble':
                    startAyahJumbleGame();
                    break;
                case 'word-whiz':
                    startWordWhizGame();
                    break;
            }
        }

        function startAyahMatchGame() {
            const gameContent = document.getElementById('game-content');
            const ayahData = currentAyahData && currentAyahData.ayah ? currentAyahData.ayah : {
                arabic_text: "بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ",
                english_translation: "In the name of Allah, the Entirely Merciful, the Especially Merciful.",
                urdu_translation: "اللہ کے نام سے جو نہایت رحم کرنے والا رحیم ہے",
                pashto_translation: "د الله په نوم چې ډېر مهربان، رحیم دی"
            };
            
            const correctAnswer = ayahData.english_translation;
            const options = [
                correctAnswer,
                "Say: He is Allah, the One!",
                "Guide us to the straight path"
            ].sort(() => Math.random() - 0.5);
            
            gameContent.innerHTML = `
                <div class="quiz-container">
                    <h3>Match the Arabic Ayah with its English Translation</h3>
                    <div class="quiz-question">${ayahData.arabic_text}</div>
                    <div class="quiz-options">
                        ${options.map(option => `
                            <div class="quiz-option" onclick="checkAnswer('${option}', '${correctAnswer}', this)">${option}</div>
                        `).join('')}
                    </div>
                    <div id="game-result"></div>
                    <button class="btn" onclick="openGamesModal()" style="margin-top: 20px;">Back to Games</button>
                </div>
            `;
        }

        function startAyahJumbleGame() {
            const gameContent = document.getElementById('game-content');
            const words = currentAyahData && currentAyahData.words ? 
                currentAyahData.words.map(w => w.arabic_word) : 
                ["بِسْمِ", "اللَّهِ", "الرَّحْمَٰنِ", "الرَّحِيمِ"];
            
            const scrambledWords = [...words].sort(() => Math.random() - 0.5);
            
            gameContent.innerHTML = `
                <div class="quiz-container">
                    <h3>Arrange the Words in Correct Order</h3>
                    <p>Drag and drop the words to form the complete ayah</p>
                    <div class="drag-drop-container" id="word-bank">
                        ${scrambledWords.map(word => `
                            <div class="draggable-word" draggable="true" ondragstart="drag(event)">${word}</div>
                        `).join('')}
                    </div>
                    <div class="drag-drop-container" id="answer-area" ondrop="drop(event)" ondragover="allowDrop(event)">
                        <p>Drop words here</p>
                    </div>
                    <button class="btn" onclick="checkJumbleAnswer()" style="margin-top: 20px;">Check Answer</button>
                    <button class="btn" onclick="openGamesModal()" style="margin-top: 20px;">Back to Games</button>
                    <div id="jumble-result"></div>
                </div>
            `;
        }

        function startWordWhizGame() {
            const gameContent = document.getElementById('game-content');
            const wordData = currentAyahData && currentAyahData.words && currentAyahData.words[0] ? 
                currentAyahData.words[0] : {
                    arabic_word: "بِسْمِ",
                    english_meaning: "In the name"
                };
            
            const correctAnswer = wordData.english_meaning;
            const options = [
                correctAnswer,
                "Most Merciful",
                "Allah"
            ].sort(() => Math.random() - 0.5);
            
            gameContent.innerHTML = `
                <div class="quiz-container">
                    <h3>What does this Arabic word mean?</h3>
                    <div class="quiz-question">${wordData.arabic_word}</div>
                    <div class="quiz-options">
                        ${options.map(option => `
                            <div class="quiz-option" onclick="checkAnswer('${option}', '${correctAnswer}', this)">${option}</div>
                        `).join('')}
                    </div>
                    <div id="game-result"></div>
                    <button class="btn" onclick="openGamesModal()" style="margin-top: 20px;">Back to Games</button>
                </div>
            `;
        }

        function checkAnswer(selected, correct, element) {
            const resultDiv = document.getElementById('game-result');
            const options = document.querySelectorAll('.quiz-option');
            
            options.forEach(option => {
                option.style.pointerEvents = 'none';
                if (option.textContent === correct) {
                    option.style.background = '#27ae60';
                    option.style.color = 'white';
                } else if (option === element && selected !== correct) {
                    option.style.background = '#e74c3c';
                    option.style.color = 'white';
                }
            });
            
            if (selected === correct) {
                resultDiv.innerHTML = '<p style="color: #27ae60; font-size: 18px; margin-top: 20px;">Correct! Well done!</p>';
            } else {
                resultDiv.innerHTML = '<p style="color: #e74c3c; font-size: 18px; margin-top: 20px;">Incorrect. The correct answer is highlighted.</p>';
            }
        }

        function drag(ev) {
            ev.dataTransfer.setData("text", ev.target.textContent);
            ev.dataTransfer.setData("element", ev.target.outerHTML);
        }

        function allowDrop(ev) {
            ev.preventDefault();
        }

        function drop(ev) {
            ev.preventDefault();
            const text = ev.dataTransfer.getData("text");
            const elementHTML = ev.dataTransfer.getData("element");
            
            if (ev.target.id === 'answer-area' || ev.target.parentNode.id === 'answer-area') {
                const container = ev.target.id === 'answer-area' ? ev.target : ev.target.parentNode;
                
                if (container.children.length === 1 && container.children[0].tagName === 'P') {
                    container.innerHTML = '';
                }
                
                const newElement = document.createElement('div');
                newElement.innerHTML = elementHTML;
                newElement.firstChild.ondragstart = drag;
                container.appendChild(newElement.firstChild);
                
                const originalElements = document.querySelectorAll('#word-bank .draggable-word');
                originalElements.forEach(el => {
                    if (el.textContent === text) {
                        el.remove();
                    }
                });
            }
        }

        function checkJumbleAnswer() {
            const answerArea = document.getElementById('answer-area');
            const userAnswer = Array.from(answerArea.querySelectorAll('.draggable-word')).map(el => el.textContent);
            const correctAnswer = currentAyahData && currentAyahData.words ? 
                currentAyahData.words.map(w => w.arabic_word) : 
                ["بِسْمِ", "اللَّهِ", "الرَّحْمَٰنِ", "الرَّحِيمِ"];
            
            const resultDiv = document.getElementById('jumble-result');
            
            if (JSON.stringify(userAnswer) === JSON.stringify(correctAnswer)) {
                resultDiv.innerHTML = '<p style="color: #27ae60; font-size: 18px; margin-top: 20px;">Correct! Perfect arrangement!</p>';
            } else {
                resultDiv.innerHTML = `<p style="color: #e74c3c; font-size: 18px; margin-top: 20px;">Incorrect. Correct order: ${correctAnswer.join(' ')}</p>`;
            }
        }

        function showWordMeaning(word, meaning) {
            alert(`${word}\n\nMeaning: ${meaning}`);
        }

        function suggestEdit() {
            if (userRole !== 'contributor' && userRole !== 'editor') return;
            
            const newText = prompt('Suggest an edit:');
            if (newText) {
                apiCall('submit_suggestion', {
                    type: 'ayah_translation',
                    target_id: currentAyahData.ayah.id,
                    language: document.getElementById('translation-select').value,
                    text: newText
                }).then(result => {
                    if (result.success) {
                        alert('Suggestion submitted successfully!');
                    }
                });
            }
        }

        function directEdit() {
            if (userRole !== 'editor' && userRole !== 'admin') return;
            
            const newText = prompt('Enter new translation:');
            if (newText) {
                // This would need additional backend implementation
                alert('Direct edit functionality would be implemented here');
            }
        }

        async function logout() {
            const result = await apiCall('logout');
            if (result.success) {
                location.reload();
            }
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            loadAyah();
            updateAyahSelect();
        });

        // Update ayah select when surah changes
        document.getElementById('surah-select').addEventListener('change', function() {
            currentSurah = parseInt(this.value);
            currentAyah = 1;
            updateAyahSelect();
            loadAyah();
        });

        // Auto-load data when page loads
        loadAyah();
        loadThemes();
        loadGoals();
        loadStats();
    </script>
</body>
</html>
