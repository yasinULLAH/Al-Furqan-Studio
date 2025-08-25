<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// Database configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_NAME', 'nur_ul_quran_studio_db');
define('DB_PASS', 'root');

// Establish database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to safely execute prepared statements
function db_query($sql, $params = [], $types = '')
{
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("DB Prepare Error: " . $conn->error . " | SQL: " . $sql);
        return false;
    }
    if (!empty($params)) {
        $bind_names = [$types];
        for ($i = 0; $i < count($params); $i++) {
            $bind_name = 'bind' . $i;
            $$bind_name = $params[$i]; 
            $bind_names[] = &$$bind_name;
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_names);
    }
    $stmt->execute();
    return $stmt;
}

// Function to fetch a single row from a statement result
function db_fetch_row($stmt)
{
    $result = $stmt->get_result();
    if ($result === false) {
        return null;
    }
    return $result->fetch_assoc();
}

// Function to fetch all rows from a statement result
function db_fetch_all($stmt)
{
    $result = $stmt->get_result();
    if ($result === false) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

// User session management functions
function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function get_user_role()
{
    return $_SESSION['role'] ?? 'public';
}

function get_user_id()
{
    return $_SESSION['user_id'] ?? 0;
}

function get_user_username()
{
    return $_SESSION['username'] ?? '';
}

function can_edit_translations()
{
    $role = get_user_role();
    return $role === 'admin' || $role === 'registered';
}

function can_approve_translations()
{
    return get_user_role() === 'admin';
}

// --- AJAX Request Handler ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'Invalid action.'];
    $user_id = get_user_id();
    $user_role = get_user_role();

    header('Content-Type: application/json');

    $isAdmin = ($user_role === 'admin');

    if ($isAdmin) {
        switch ($action) {
            case 'get_all_users':
                $stmt = db_query("SELECT id, username, full_name, email, role, created_at FROM users");
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'update_user_role':
                $userId = $_POST['user_id'];
                $newRole = $_POST['new_role'];
                if ($userId == $user_id && $newRole !== 'admin') { 
                    $response = ['success' => false, 'message' => 'You cannot change your own role from admin.'];
                } else {
                    $stmt = db_query("UPDATE users SET role = ? WHERE id = ?", [$newRole, $userId], 'si');
                    $response = $stmt ? ['success' => true, 'message' => 'User role updated.'] : ['success' => false, 'message' => $conn->error];
                }
                break;
            case 'update_user_full_name':
                $userId = $_POST['user_id'];
                $fullName = $_POST['full_name'];
                $stmt = db_query("UPDATE users SET full_name = ? WHERE id = ?", [$fullName, $userId], 'si');
                $response = $stmt ? ['success' => true, 'message' => 'User full name updated.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_user':
                $userId = $_POST['user_id'];
                if ($userId == $user_id) { 
                    $response = ['success' => false, 'message' => 'You cannot delete your own account.'];
                } else {
                    $stmt = db_query("DELETE FROM users WHERE id = ?", [$userId], 'i');
                    $response = $stmt ? ['success' => true, 'message' => 'User deleted.'] : ['success' => false, 'message' => $conn->error];
                }
                break;
            case 'load_quran_ayah':
                $surah = $_POST['surah'];
                $ayah = $_POST['ayah'];
                $select_cols = ['arabic_text AS arabic'];
                foreach ($translation_config as $lang) {
                    $select_cols[] = $lang->key;
                }
                $sql_select = implode(', ', $select_cols);
                $stmt = db_query("SELECT $sql_select FROM quran_ayahs WHERE surah_num = ? AND ayah_num = ?", [$surah, $ayah], 'ii');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_row($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'admin_update_quran_translation':
                $surah = $_POST['surah'];
                $ayah = $_POST['ayah'];
                $langKey = $_POST['lang_key'];
                $translationText = $_POST['translation_text'];

                $validLangKey = false;
                foreach ($translation_config as $config) {
                    if ($config->key === $langKey) {
                        $validLangKey = true;
                        break;
                    }
                }
                if (!$validLangKey) {
                    $response = ['success' => false, 'message' => 'Invalid language key.'];
                    break;
                }

                $stmt = db_query("UPDATE quran_ayahs SET `$langKey` = ? WHERE surah_num = ? AND ayah_num = ?", [$translationText, $surah, $ayah], 'sii');
                $response = $stmt ? ['success' => true, 'message' => 'Translation updated.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_unapproved_word_translations': 
                $sql = "SELECT uwt.id, uwt.word_id, qw.arabic_word, l.lang_key, l.label AS lang_label, uwt.translation, u.username 
                        FROM user_word_translations uwt
                        JOIN quran_words qw ON uwt.word_id = qw.word_id
                        JOIN languages l ON uwt.lang_key = l.lang_key
                        JOIN users u ON uwt.user_id = u.id
                        WHERE uwt.approved_by IS NULL";
                $stmt = db_query($sql);
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'edit_word_translation': 
                $wordId = $_POST['word_id'];
                $langKey = $_POST['lang_key'];
                $translationText = $_POST['translation_text'];
                $adminApprove = filter_var($_POST['admin_approve'], FILTER_VALIDATE_BOOLEAN);

                $validColName = '';
                foreach ($translation_config as $config) {
                    if ($config->key === $langKey) {
                        $validColName = $config->word_col_name;
                        break;
                    }
                }
                if (!$validColName) {
                    $response = ['success' => false, 'message' => 'Invalid language key or column name.'];
                    break;
                }

                if ($adminApprove) {
                    $conn->begin_transaction();
                    try {
                        $stmt_update_main = db_query("UPDATE quran_words SET `$validColName` = ?, approved_by = ? WHERE word_id = ?", [$translationText, $user_id, $wordId], 'sii');
                        if (!$stmt_update_main) {
                            throw new Exception($conn->error);
                        }
                        $stmt_approve_user_submissions = db_query("UPDATE user_word_translations SET approved_by = ? WHERE word_id = ? AND lang_key = ? AND approved_by IS NULL", [$user_id, $wordId, $langKey], 'iis');
                        if (!$stmt_approve_user_submissions) {
                            throw new Exception($conn->error);
                        }
                        $conn->commit();
                        $response = ['success' => true, 'message' => 'Word translation approved and updated.'];
                    } catch (Exception $e) {
                        $conn->rollback();
                        $response = ['success' => false, 'message' => 'Failed to approve/update word translation: ' . $e->getMessage()];
                    }
                } else {
                    $stmt = db_query("INSERT INTO user_word_translations (user_id, word_id, lang_key, translation) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE translation = ?, submitted_at = NOW(), approved_by = NULL", [$user_id, $wordId, $langKey, $translationText, $translationText], 'iisss');
                    $response = $stmt ? ['success' => true, 'message' => 'Word translation submitted for review.'] : ['success' => false, 'message' => $conn->error];
                }
                break;
            case 'delete_word_translation': 
                $wordId = $_POST['word_id'];
                $langKey = $_POST['lang_key'];
                $stmt = db_query("DELETE FROM user_word_translations WHERE word_id = ? AND lang_key = ? AND approved_by IS NULL", [$wordId, $langKey], 'is');
                $response = $stmt ? ['success' => true, 'message' => 'Pending word translation rejected/deleted.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_word_translations_full': 
                $selectCols = ['qw.word_id', 'qw.arabic_word', 'CONCAT(qw.surah, ":", qw.ayah) AS surah_ayah'];
                foreach ($translation_config as $lang) {
                    $selectCols[] = "`" . $lang->word_col_name . "`";
                }
                $selectCols[] = "u.username AS approved_by_username";
                $sql = "SELECT " . implode(', ', $selectCols) . " FROM quran_words qw LEFT JOIN users u ON qw.approved_by = u.id";
                $stmt = db_query($sql);
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_word_translation': 
                $wordId = $_POST['word_id'];
                $selectCols = ['qw.word_id', 'qw.arabic_word', 'qw.surah', 'qw.ayah', 'qw.position'];
                foreach ($translation_config as $lang) {
                    $selectCols[] = "`" . $lang->word_col_name . "`";
                }
                $sql = "SELECT " . implode(', ', $selectCols) . " FROM quran_words qw WHERE word_id = ?";
                $stmt = db_query($sql, [$wordId], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_row($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_languages':
                $stmt = db_query("SELECT `key`, label, lang_code, direction, font_var FROM languages");
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'add_language':
                $langKey = $_POST['lang_key'];
                $label = $_POST['label'];
                $langCode = $_POST['lang_code'];
                $direction = $_POST['direction'];
                $fontVar = $_POST['font_var'];
                $wordColName = "translation_" . $langKey;

                $conn->begin_transaction();
                try {
                    $stmt = db_query("INSERT INTO languages (`key`, label, lang_code, direction, font_var, word_col_name) VALUES (?, ?, ?, ?, ?, ?)",
                        [$langKey, $label, $langCode, $direction, $fontVar, $wordColName], 'ssssss');
                    if (!$stmt) throw new Exception("Failed to insert into languages: " . $conn->error);

                    $alterAyahSql = "ALTER TABLE quran_ayahs ADD COLUMN `$langKey` TEXT NULL";
                    if (!$conn->query($alterAyahSql)) throw new Exception("Failed to alter quran_ayahs: " . $conn->error);

                    $alterWordSql = "ALTER TABLE quran_words ADD COLUMN `$wordColName` TEXT NULL";
                    if (!$conn->query($alterWordSql)) throw new Exception("Failed to alter quran_words: " . $conn->error);

                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Language added successfully and tables updated.'];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Failed to add language: ' . $e->getMessage()];
                }
                break;
            case 'update_language': 
                $originalKey = $_POST['original_key'];
                $label = $_POST['label'];
                $langCode = $_POST['lang_code'];
                $direction = $_POST['direction'];
                $fontVar = $_POST['font_var'];

                $stmt = db_query("UPDATE languages SET label = ?, lang_code = ?, direction = ?, font_var = ? WHERE `key` = ?",
                    [$label, $langCode, $direction, $fontVar, $originalKey], 'sssss');
                $response = $stmt ? ['success' => true, 'message' => 'Language updated successfully.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_language': 
                $langKey = $_POST['lang_key'];
                $wordColName = "translation_" . $langKey;

                $conn->begin_transaction();
                try {
                    $stmt = db_query("DELETE FROM languages WHERE `key` = ?", [$langKey], 's');
                    if (!$stmt || $stmt->affected_rows === 0) throw new Exception("Language not found or failed to delete from languages.");

                    $alterAyahSql = "ALTER TABLE quran_ayahs DROP COLUMN `$langKey`";
                    if (!$conn->query($alterAyahSql)) throw new Exception("Failed to drop column from quran_ayahs: " . $conn->error);

                    $alterWordSql = "ALTER TABLE quran_words DROP COLUMN `$wordColName`";
                    if (!$conn->query($alterWordSql)) throw new Exception("Failed to drop column from quran_words: " . $conn->error);

                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Language deleted successfully and columns removed.'];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Failed to delete language: ' . $e->getMessage()];
                }
                break;
            case 'get_all_tafsir_admin': 
                $sql = "SELECT ut.id, ut.user_id, u.username, ut.surah, ut.ayah, ut.notes, ut.is_public FROM user_tafsir ut JOIN users u ON ut.user_id = u.id ORDER BY ut.surah, ut.ayah";
                $stmt = db_query($sql);
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_themes_admin': 
                $sql = "SELECT ut.id, ut.user_id, u.username, ut.name, ut.description, ut.is_public FROM user_themes ut JOIN users u ON ut.user_id = u.id ORDER BY ut.name";
                $stmt = db_query($sql);
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_roots_admin': 
                $sql = "SELECT ur.id, ur.user_id, u.username, ur.root, ur.description, ur.is_public FROM user_roots ur JOIN users u ON ur.user_id = u.id ORDER BY ur.root";
                $stmt = db_query($sql);
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'toggle_public_status': 
                $itemId = $_POST['item_id'];
                $itemType = $_POST['item_type'];
                $isPublic = filter_var($_POST['is_public'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $table = '';

                switch ($itemType) {
                    case 'tafsir':
                        $table = 'user_tafsir';
                        break;
                    case 'theme':
                        $table = 'user_themes';
                        break;
                    case 'root':
                        $table = 'user_roots';
                        break;
                    default:
                        $response = ['success' => false, 'message' => 'Invalid item type.'];
                        echo json_encode($response);
                        exit();
                }
                $stmt = db_query("UPDATE $table SET is_public = ? WHERE id = ?", [$isPublic, $itemId], 'ii');
                $response = $stmt ? ['success' => true, 'message' => 'Public status updated.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_tafsir': 
                $stmt = db_query("SELECT id FROM user_tafsir");
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_themes': 
                $stmt = db_query("SELECT id FROM user_themes");
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
        }
    }
    
    if ($user_id > 0) {
        switch ($action) {
            case 'get_all_tafsir':
                $stmt = db_query("SELECT id, surah, ayah, notes, is_public FROM user_tafsir WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'save_tafsir':
                $surah = $_POST['surah'];
                $ayah = $_POST['ayah'];
                $notes = $_POST['notes'];
                $stmt = db_query("INSERT INTO user_tafsir (user_id, surah, ayah, notes, updated_at) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE notes = ?, updated_at = NOW()",
                    [$user_id, $surah, $ayah, $notes, $notes], 'iisss');
                $response = $stmt ? ['success' => true, 'message' => 'Tafsir notes saved.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_tafsir': 
                $surah = $_POST['surah'];
                $ayah = $_POST['ayah'];
                // For user, ensure they only delete their own tafsir
                $stmt = db_query("DELETE FROM user_tafsir WHERE user_id = ? AND surah = ? AND ayah = ?", [$user_id, $surah, $ayah], 'iii');
                $response = $stmt ? ['success' => true, 'message' => 'Tafsir note deleted.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_themes':
                $stmt = db_query("SELECT id, name, description, is_public FROM user_themes WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'add_theme':
                $name = $_POST['name'];
                $description = $_POST['description'];
                $stmt = db_query("INSERT INTO user_themes (user_id, name, description, created_at) VALUES (?, ?, ?, NOW())", [$user_id, $name, $description], 'iss');
                $response = $stmt ? ['success' => true, 'message' => 'Theme added.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'update_theme': 
                $themeId = $_POST['theme_id'];
                $name = $_POST['name'];
                $description = $_POST['description'];
                $stmt = db_query("UPDATE user_themes SET name = ?, description = ? WHERE id = ? AND user_id = ?", [$name, $description, $themeId, $user_id], 'ssii');
                $response = $stmt ? ['success' => true, 'message' => 'Theme updated.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_theme':
                $themeId = $_POST['theme_id'];
                $conn->begin_transaction();
                try {
                    db_query("DELETE FROM user_theme_ayahs WHERE theme_id = ? AND user_id = ?", [$themeId, $user_id], 'ii');
                    $stmt = db_query("DELETE FROM user_themes WHERE id = ? AND user_id = ?", [$themeId, $user_id], 'ii');
                    if (!$stmt) throw new Exception($conn->error);
                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Theme and linked ayahs deleted.'];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Failed to delete theme: ' . $e->getMessage()];
                }
                break;
            case 'get_linked_ayahs_for_theme':
                $themeId = $_POST['theme_id'];
                $stmt = db_query("SELECT id, surah, ayah, notes FROM user_theme_ayahs WHERE user_id = ? AND theme_id = ?", [$user_id, $themeId], 'ii');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_theme_ayahs': 
                $stmt = db_query("SELECT id, theme_id, surah, ayah FROM user_theme_ayahs WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'unlink_ayah_from_theme':
                $linkId = $_POST['link_id'];
                $stmt = db_query("DELETE FROM user_theme_ayahs WHERE id = ? AND user_id = ?", [$linkId, $user_id], 'ii');
                $response = $stmt ? ['success' => true, 'message' => 'Ayah unlinked.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_roots':
                $stmt = db_query("SELECT id, root, description, is_public FROM user_roots WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'save_root_notes':
                $root = $_POST['root'];
                $description = $_POST['description'];
                $stmt = db_query("INSERT INTO user_roots (user_id, root, description, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE description = ?",
                    [$user_id, $root, $description, $description], 'isss');
                $response = $stmt ? ['success' => true, 'message' => 'Root notes saved.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_root_notes': 
                $root = $_POST['root'];
                $stmt = db_query("DELETE FROM user_roots WHERE user_id = ? AND root = ?", [$user_id, $root], 'is');
                $response = $stmt ? ['success' => true, 'message' => 'Root notes deleted.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_recitations':
                $stmt = db_query("SELECT id, surah, ayah_start, ayah_end, qari, log_date, notes FROM user_recitation_logs WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'update_recitation_log': 
                $id = $_POST['id'] ?? null;
                $surah = $_POST['surah'];
                $ayah_start = $_POST['ayah_start'] ?: null;
                $ayah_end = $_POST['ayah_end'] ?: null;
                $qari = $_POST['qari'];
                $log_date = $_POST['log_date'];
                $notes = $_POST['notes'];
                if ($id) {
                    $stmt = db_query("UPDATE user_recitation_logs SET surah = ?, ayah_start = ?, ayah_end = ?, qari = ?, log_date = ?, notes = ? WHERE id = ? AND user_id = ?",
                        [$surah, $ayah_start, $ayah_end, $qari, $log_date, $notes, $id, $user_id], 'iiisssii');
                } else {
                    $stmt = db_query("INSERT INTO user_recitation_logs (user_id, surah, ayah_start, ayah_end, qari, log_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)",
                        [$user_id, $surah, $ayah_start, $ayah_end, $qari, $log_date, $notes], 'iiissss');
                }
                $response = $stmt ? ['success' => true, 'message' => 'Recitation log saved.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_recitation_log':
                $logId = $_POST['log_id'];
                $stmt = db_query("DELETE FROM user_recitation_logs WHERE id = ? AND user_id = ?", [$logId, $user_id], 'ii');
                $response = $stmt ? ['success' => true, 'message' => 'Recitation log deleted.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_hifz':
                $stmt = db_query("SELECT surah, ayah, status, last_review_date, next_review_date, review_count, notes FROM user_hifz WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_hifz_for_ayah':
                $surah = $_POST['surah'];
                $ayah = $_POST['ayah'];
                $stmt = db_query("SELECT surah, ayah, status, last_review_date, next_review_date, review_count, notes FROM user_hifz WHERE user_id = ? AND surah = ? AND ayah = ?", [$user_id, $surah, $ayah], 'iii');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_row($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'update_hifz_status':
                $surah = $_POST['surah'];
                $ayah = $_POST['ayah'];
                $status = $_POST['status'];
                $lastReviewDate = $_POST['last_review_date'] ?: null;
                $nextReviewDate = $_POST['next_review_date'] ?: null;
                $reviewCount = $_POST['review_count'];
                $notes = $_POST['notes'];

                $stmt = db_query("INSERT INTO user_hifz (user_id, surah, ayah, status, last_review_date, next_review_date, review_count, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = ?, last_review_date = ?, next_review_date = ?, review_count = ?, notes = ?",
                    [$user_id, $surah, $ayah, $status, $lastReviewDate, $nextReviewDate, $reviewCount, $notes, $status, $lastReviewDate, $nextReviewDate, $reviewCount, $notes], 'iiisssissssis');
                $response = $stmt ? ['success' => true, 'message' => 'Hifz status updated.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_all_goals':
                $stmt = db_query("SELECT id, title, type, target_surah, target_juz, target_theme_id, target_count, target_day, target_date, creation_date, is_complete FROM user_goals WHERE user_id = ?", [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'add_goal':
                $title = $_POST['title'];
                $type = $_POST['type'];
                $targetSurah = $_POST['targetSurah'] ?? null;
                $targetJuz = $_POST['targetJuz'] ?? null;
                $targetTheme = $_POST['targetTheme'] ?? null;
                $targetCount = $_POST['targetCount'] ?? null;
                $targetDay = $_POST['targetDay'] ?? null;
                $targetDate = $_POST['targetDate'] ?: null;
                $creationDate = $_POST['creationDate'];
                $isComplete = filter_var($_POST['isComplete'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

                $stmt = db_query("INSERT INTO user_goals (user_id, title, type, target_surah, target_juz, target_theme_id, target_count, target_day, target_date, creation_date, is_complete) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                    [$user_id, $title, $type, $targetSurah, $targetJuz, $targetTheme, $targetCount, $targetDay, $targetDate, $creationDate, $isComplete], 'isssiiiisssi');
                $response = $stmt ? ['success' => true, 'message' => 'Goal added.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'update_goal_completion': 
                $id = $_POST['id'];
                $isComplete = filter_var($_POST['isComplete'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
                $title = $_POST['title'] ?? null;
                $targetDate = $_POST['targetDate'] ?? null;

                $sql = "UPDATE user_goals SET is_complete = ?";
                $types = 'i';
                $params = [$isComplete];

                if ($title !== null) {
                    $sql .= ", title = ?";
                    $types .= 's';
                    $params[] = $title;
                }
                if ($targetDate !== null) {
                    $sql .= ", target_date = ?";
                    $types .= 's';
                    $params[] = $targetDate;
                }
                $sql .= " WHERE id = ? AND user_id = ?";
                $types .= 'ii';
                $params[] = $id;
                $params[] = $user_id;

                $stmt = db_query($sql, $params, $types);
                $response = $stmt ? ['success' => true, 'message' => 'Goal updated.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'delete_goal':
                $id = $_POST['id'];
                $stmt = db_query("DELETE FROM user_goals WHERE id = ? AND user_id = ?", [$id, $user_id], 'ii');
                $response = $stmt ? ['success' => true, 'message' => 'Goal deleted.'] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_user_submitted_word_translations': 
                $sql = "SELECT uwt.id, uwt.word_id, uwt.lang_key, uwt.translation, uwt.approved_by, l.word_col_name AS translation_column
                        FROM user_word_translations uwt
                        JOIN languages l ON uwt.lang_key = l.lang_key
                        WHERE uwt.user_id = ?";
                $stmt = db_query($sql, [$user_id], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_all($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'get_word_metadata': 
                $wordId = $_POST['word_id'];
                $stmt = db_query("SELECT word_id, arabic_word FROM quran_words WHERE word_id = ?", [$wordId], 'i');
                $response = $stmt ? ['success' => true, 'data' => db_fetch_row($stmt)] : ['success' => false, 'message' => $conn->error];
                break;
            case 'export_user_data':
                $data = [
                    'user_tafsir' => db_fetch_all(db_query("SELECT surah, ayah, notes, created_at, updated_at, is_public FROM user_tafsir WHERE user_id = ?", [$user_id], 'i')),
                    'user_themes' => db_fetch_all(db_query("SELECT id, name, description, created_at, is_public FROM user_themes WHERE user_id = ?", [$user_id], 'i')),
                    'user_theme_ayahs' => db_fetch_all(db_query("SELECT theme_id, surah, ayah, notes FROM user_theme_ayahs WHERE user_id = ?", [$user_id], 'i')),
                    'user_roots' => db_fetch_all(db_query("SELECT root, description, created_at, is_public FROM user_roots WHERE user_id = ?", [$user_id], 'i')),
                    'user_recitation_logs' => db_fetch_all(db_query("SELECT surah, ayah_start, ayah_end, qari, log_date, notes FROM user_recitation_logs WHERE user_id = ?", [$user_id], 'i')),
                    'user_hifz' => db_fetch_all(db_query("SELECT surah, ayah, status, last_review_date, next_review_date, review_count, notes FROM user_hifz WHERE user_id = ?", [$user_id], 'i')),
                    'user_goals' => db_fetch_all(db_query("SELECT title, type, target_surah, target_juz, target_theme_id, target_count, target_day, target_date, creation_date, is_complete FROM user_goals WHERE user_id = ?", [$user_id], 'i')),
                    'user_word_translations' => db_fetch_all(db_query("SELECT word_id, lang_key, translation, approved_by FROM user_word_translations WHERE user_id = ?", [$user_id], 'i'))
                ];
                $response = ['success' => true, 'data' => $data];
                break;
            case 'import_user_data':
                $jsonData = json_decode($_POST['data'], true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = ['success' => false, 'message' => 'Invalid JSON data.'];
                    break;
                }
                $conn->begin_transaction();
                try {
                    db_query("DELETE FROM user_tafsir WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_theme_ayahs WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_themes WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_roots WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_recitation_logs WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_hifz WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_goals WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_word_translations WHERE user_id = ?", [$user_id], 'i');

                    foreach ($jsonData['user_tafsir'] as $row) {
                        db_query("INSERT INTO user_tafsir (user_id, surah, ayah, notes, created_at, updated_at, is_public) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$user_id, $row['surah'], $row['ayah'], $row['notes'], $row['created_at'], $row['updated_at'], $row['is_public']], 'iissssi');
                    }
                    $themeIdMap = []; 
                    foreach ($jsonData['user_themes'] as $row) {
                        $stmt = db_query("INSERT INTO user_themes (user_id, name, description, created_at, is_public) VALUES (?, ?, ?, ?, ?)",
                            [$user_id, $row['name'], $row['description'], $row['created_at'], $row['is_public']], 'isssi');
                        if ($stmt && $stmt->insert_id) {
                            $themeIdMap[$row['id']] = $stmt->insert_id;
                        }
                    }
                    foreach ($jsonData['user_theme_ayahs'] as $row) {
                        $newThemeId = $themeIdMap[$row['theme_id']] ?? null;
                        if ($newThemeId) {
                            db_query("INSERT INTO user_theme_ayahs (user_id, theme_id, surah, ayah, notes) VALUES (?, ?, ?, ?, ?)",
                                [$user_id, $newThemeId, $row['surah'], $row['ayah'], $row['notes']], 'iiiis');
                        }
                    }
                    foreach ($jsonData['user_roots'] as $row) {
                        db_query("INSERT INTO user_roots (user_id, root, description, created_at, is_public) VALUES (?, ?, ?, ?, ?)",
                            [$user_id, $row['root'], $row['description'], $row['created_at'], $row['is_public']], 'isssi');
                    }
                    foreach ($jsonData['user_recitation_logs'] as $row) {
                        db_query("INSERT INTO user_recitation_logs (user_id, surah, ayah_start, ayah_end, qari, log_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)",
                            [$user_id, $row['surah'], $row['ayah_start'], $row['ayah_end'], $row['qari'], $row['log_date'], $row['notes']], 'iiissss');
                    }
                    foreach ($jsonData['user_hifz'] as $row) {
                        db_query("INSERT INTO user_hifz (user_id, surah, ayah, status, last_review_date, next_review_date, review_count, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                            [$user_id, $row['surah'], $row['ayah'], $row['status'], $row['last_review_date'], $row['next_review_date'], $row['review_count'], $row['notes']], 'iiisssis');
                    }
                    foreach ($jsonData['user_goals'] as $row) {
                        db_query("INSERT INTO user_goals (user_id, title, type, target_surah, target_juz, target_theme_id, target_count, target_day, target_date, creation_date, is_complete) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                            [$user_id, $row['title'], $row['type'], $row['target_surah'], $row['target_juz'], $row['target_theme_id'], $row['target_count'], $row['target_day'], $row['target_date'], $row['creation_date'], $row['is_complete']], 'isssiiiisssi');
                    }
                    foreach ($jsonData['user_word_translations'] as $row) {
                        db_query("INSERT INTO user_word_translations (user_id, word_id, lang_key, translation, approved_by, submitted_at) VALUES (?, ?, ?, ?, ?, NOW())",
                            [$user_id, $row['word_id'], $row['lang_key'], $row['translation'], $row['approved_by']], 'iisssi');
                    }

                    $conn->commit();
                    $response = ['success' => true, 'message' => 'Data imported successfully.'];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Failed to import data: ' . $e->getMessage()];
                }
                break;
            case 'clear_personal_data':
                $conn->begin_transaction();
                try {
                    db_query("DELETE FROM user_tafsir WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_theme_ayahs WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_themes WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_roots WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_recitation_logs WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_hifz WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_goals WHERE user_id = ?", [$user_id], 'i');
                    db_query("DELETE FROM user_word_translations WHERE user_id = ?", [$user_id], 'i');
                    $conn->commit();
                    $response = ['success' => true, 'message' => 'All personal data cleared.'];
                } catch (Exception $e) {
                    $conn->rollback();
                    $response = ['success' => false, 'message' => 'Failed to clear data: ' . $e->getMessage()];
                }
                break;
            default:
                $response = ['success' => false, 'message' => 'Unauthorized action.'];
                break;
        }
    }

    echo json_encode($response);
    exit();
}

if (!is_logged_in()) {
    header('Location: index.php'); 
    exit();
}

$user_role = get_user_role();
$user_id = get_user_id();

$surah_names = [
    "Al-Fatihah", "Al-Baqarah", "Al 'Imran", "An-Nisa'", "Al-Ma'idah", "Al-An'am", "Al-A'raf", "Al-Anfal", "At-Tawbah", "Yunus", "Hud", "Yusuf", "Ar-Ra'd", "Ibrahim", "Al-Hijr", "An-Nahl", "Al-Isra'", "Al-Kahf", "Maryam", "Taha", "Al-Anbya'", "Al-Hajj", "Al-Mu'minun", "An-Nur", "Al-Furqan", "Ash-Shu'ara'", "An-Naml", "Al-Qasas", "Al-'Ankabut", "Ar-Rum", "Luqman", "As-Sajdah", "Al-Ahzab", "Saba'", "Fatir", "Ya-Sin", "As-Saffat", "Sad", "Az-Zumar", "Ghafir", "Fussilat", "Ash-Shura", "Az-Zukhruf", "Ad-Dukhan", "Al-Jathiyah", "Al-Ahqaf", "Muhammad", "Al-Fath", "Al-Hujurat", "Qaf", "Adh-Dhariyat", "At-Tur", "An-Najm", "Al-Qamar", "Ar-Rahman", "Al-Waqi'ah", "Al-Hadid", "Al-Mujadilah", "Al-Hashr", "Al-Mumtahanah", "As-Saff", "Al-Jumu'ah", "Al-Munafiqun", "At-Taghabun", "At-Talaq", "At-Tahrim", "Al-Mulk", "Al-Qalam", "Al-Haqqah", "Al-Ma'arij", "Nuh", "Al-Jinn", "Al-Muzzammil", "Al-Muddaththir", "Al-Qiyamah", "Al-Insan", "Al-Mursalat", "An-Naba'", "An-Nazi'at", "'Abasa", "At-Takwir", "Al-Infitar", "Al-Mutaffifin", "Al-Inshiqaq", "Al-Buruj", "At-Tariq", "Al-A'la", "Al-Ghashiyah", "Al-Fajr", "Al-Balad", "Ash-Shams", "Al-Layl", "Ad-Duha", "Ash-Sharh", "At-Tin", "Al-'Alaq", "Al-Qadr", "Al-Bayyinah", "Az-Zalzalah", "Al-'Adiyat", "Al-Qari'ah", "At-Takathur", "Al-'Asr", "Al-Humazah", "Al-Fil", "Quraysh", "Al-Ma'un", "Al-Kawthar", "Al-Kafirun", "An-Nasr", "Al-Masad", "Al-Ikhlas", "Al-Falaq", "An-Nas"
];
$surah_ayah_counts = [
    0, 7, 286, 200, 176, 120, 165, 206, 75, 129, 109, 123, 111, 43, 52, 99, 128, 111, 110, 98, 135, 112, 78, 118, 64, 77, 227, 93, 88, 69, 60, 34, 30, 73, 54, 45, 83, 182, 88, 75, 85, 54, 53, 89, 59, 37, 35, 38, 29, 18, 45, 60, 49, 62, 55, 78, 96, 29, 22, 24, 13, 14, 11, 11, 18, 12, 12, 30, 52, 52, 44, 28, 28, 20, 56, 40, 31, 50, 40, 46, 42, 29, 19, 36, 25, 22, 17, 19, 26, 30, 20, 15, 21, 11, 8, 5, 19, 5, 8, 8, 11, 11, 8, 3, 9, 5, 4, 7, 3, 6, 3, 5, 4, 5, 6
];
$juz_boundaries_data = [
    (object)['juz' => 1, 'name' => "Alif laam meem (آلم)", 'startSurah' => 1, 'startAyah' => 1],
    (object)['juz' => 2, 'name' => "Sayaqulu (سَيَقُولُ)", 'startSurah' => 2, 'startAyah' => 142],
    (object)['juz' => 3, 'name' => "Tilka r Rusulu (تِلْكَ الرُّسُلُ)", 'startSurah' => 2, 'startAyah' => 253],
    (object)['juz' => 4, 'name' => "Lan Tana Loo (لَنْ تَنَالُوا)", 'startSurah' => 3, 'startAyah' => 93],
    (object)['juz' => 5, 'name' => "Wal Mohsanat (وَالْمُحْصَنَاتُ)", 'startSurah' => 4, 'startAyah' => 24],
    (object)['juz' => 6, 'name' => "La Yahubbullah (لَا يُحِبُّ اللَّهُ)", 'startSurah' => 4, 'startAyah' => 148],
    (object)['juz' => 7, 'name' => "Wa Iza Samiu (وَإِذَا سَمِعُوا)", 'startSurah' => 5, 'startAyah' => 82],
    (object)['juz' => 8, 'name' => "Wa Lau Annana (وَلَوْ أَنَّنَا)", 'startSurah' => 6, 'startAyah' => 111],
    (object)['juz' => 9, 'name' => "Qalal Malao (قَالَ الْمَلَأُ)", 'startSurah' => 7, 'startAyah' => 88],
    (object)['juz' => 10, 'name' => "Wa A'lamu (وَاعْلَمُوا)", 'startSurah' => 8, 'startAyah' => 41],
    (object)['juz' => 11, 'name' => "Yatazeroon (يَعْتَذِرُونَ)", 'startSurah' => 9, 'startAyah' => 93],
    (object)['juz' => 12, 'name' => "Wa Mamin Da'abat (وَمَا مِنْ دَابَّةٍ)", 'startSurah' => 11, 'startAyah' => 6],
    (object)['juz' => 13, 'name' => "Wa Ma Ubiroo (وَمَا أُبَرِّئُ)", 'startSurah' => 12, 'startAyah' => 53],
    (object)['juz' => 14, 'name' => "Rubama (رُبَمَا)", 'startSurah' => 15, 'startAyah' => 1],
    (object)['juz' => 15, 'name' => "Subhanallazi (سُبْحَانَ الَّذِي)", 'startSurah' => 17, 'startAyah' => 1],
    (object)['juz' => 16, 'name' => "Qal Alam (قَالَ أَلَمْ)", 'startSurah' => 18, 'startAyah' => 75],
    (object)['juz' => 17, 'name' => "Aqtarabo (اقْتَرَبَ لِلنَّاسِ)", 'startSurah' => 21, 'startAyah' => 1],
    (object)['juz' => 18, 'name' => "Qadd Aflaha (قَدْ أَفْلَحَ)", 'startSurah' => 23, 'startAyah' => 1],
    (object)['juz' => 19, 'name' => "Wa Qalallazina (وَقَالَ الَّذِينَ)", 'startSurah' => 25, 'startAyah' => 21],
    (object)['juz' => 20, 'name' => "A'man Khalaq (أَمَّنْ خَلَقَ)", 'startSurah' => 27, 'startAyah' => 56],
    (object)['juz' => 21, 'name' => "Utlu Ma Oohi (اتْلُ مَا أُوحِيَ)", 'startSurah' => 29, 'startAyah' => 46],
    (object)['juz' => 22, 'name' => "Wa Manyaqnut (وَمَنْ يَقْنُتْ)", 'startSurah' => 33, 'startAyah' => 31],
    (object)['juz' => 23, 'name' => "Wa Mali (وَمَا لِيَ)", 'startSurah' => 36, 'startAyah' => 28],
    (object)['juz' => 24, 'name' => "Faman Azlam (فَمَنْ أَظْلَمُ)", 'startSurah' => 39, 'startAyah' => 32],
    (object)['juz' => 25, 'name' => "Elahe Yuruddo (إِلَيْهِ يُرَدُّ)", 'startSurah' => 41, 'startAyah' => 47],
    (object)['juz' => 26, 'name' => "Ha'a Meem (حم)", 'startSurah' => 46, 'startAyah' => 1],
    (object)['juz' => 27, 'name' => "Qala Fama Khatbukum (قَالَ فَمَا خَطْبُكُمْ)", 'startSurah' => 51, 'startAyah' => 31],
    (object)['juz' => 28, 'name' => "Qadd Sami Allah (قَدْ سَمِعَ اللَّهُ)", 'startSurah' => 58, 'startAyah' => 1],
    (object)['juz' => 29, 'name' => "Tabarakallazi (تَبَارَكَ الَّذِي)", 'startSurah' => 67, 'startAyah' => 1],
    (object)['juz' => 30, 'name' => "Amma Yatasa'aloon (عَمَّ يَتَسَاءَلُونَ)", 'startSurah' => 78, 'startAyah' => 1]
];

$translation_config = [];
$result = $conn->query("SELECT lang_key as `key`, label, lang_code, direction, font_var, word_col_name FROM languages ORDER BY id");
if ($result) {
    while ($row = $result->fetch_object()) {
        $translation_config[] = $row;
    }
}
$all_langs_result = db_query("SELECT lang_key, word_col_name FROM languages");
$valid_quran_lang_keys = [];
$valid_word_col_names = [];
if ($all_langs_result) {
    foreach (db_fetch_all($all_langs_result) as $lang) {
        $valid_quran_lang_keys[] = $lang['lang_key'];
        $valid_word_col_names[] = $lang['word_col_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon.png" type="image/png">
    <title>Nur-Ul-Quran Studio - Management Dashboard</title>
    <meta name="author" content="Yasin Ullah, Pakistan">
    <meta name="description" content="Management Dashboard for Nur-Ul-Quran Studio, offering role-based access for Admin and Registered users.">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/2.0.7/css/dataTables.bootstrap5.min.css">
    <style>
        :root {
            --color-bg-primary: #e8f5e9;
            --color-bg-secondary: #c8e6c9;
            --color-text-primary: #1b5e20;
            --color-text-secondary: #388e3c;
            --color-accent: #4caf50;
            --color-accent-dark: #388e3c;
            --color-border: #a5d6a7;
            --color-shadow: rgba(0, 0, 0, 0.1);
            --color-highlight: #fff9c4;
            --color-error: #ef5350;
            --color-success: #66bb6a;
            --font-arabic: 'Scheherazade New', 'Lateef', 'Amiri', 'Traditional Arabic', calibri;
            --font-urdu: Calibri, 'Jameel Noori Nastaleeq', 'Noto Nastaliq Urdu', 'Pak Nastaleeq', calibri;
            --font-pashto: 'Mirza', 'Noto Nastaliq Urdu', 'Pak Nastaleeq', calibri;
            --font-bangali: 'Noto Sans Bangali', 'Arial', calibri;
            --font-english: 'Roboto', 'Segoe UI', calibri;
            --font-general: 'Roboto', 'Segoe UI', calibri;
            --border-radius: 8px;
            --padding-main: 20px;
            --transition-speed: 0.3s;
        }

        body {
            font-family: var(--font-general);
            line-height: 1.6;
            color: var(--color-text-primary);
            background-color: var(--color-bg-primary);
            transition: background-color var(--transition-speed), color var(--transition-speed);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            color: var(--color-text-secondary);
            margin-bottom: 15px;
        }

        .container-fluid {
            flex-grow: 1;
        }

        .navbar {
            background-color: var(--color-bg-secondary);
            box-shadow: 0 2px 5px var(--color-shadow);
        }

        .navbar-brand,
        .nav-link {
            color: var(--color-text-primary) !important;
        }

        .nav-link.active {
            font-weight: bold;
            color: var(--color-accent-dark) !important;
        }

        .card {
            background-color: var(--color-bg-secondary);
            border: 1px solid var(--color-border);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 5px var(--color-shadow);
            margin-bottom: var(--padding-main);
        }

        .card-header {
            background-color: var(--color-bg-primary);
            color: var(--color-text-secondary);
            font-weight: bold;
            border-bottom: 1px solid var(--color-border);
        }

        .table {
            color: var(--color-text-primary);
        }

        .table th {
            color: var(--color-text-secondary);
        }

        .table-striped>tbody>tr:nth-of-type(odd)>* {
            background-color: var(--color-bg-primary);
        }

        .form-control,
        .form-select {
            background-color: var(--color-bg-primary);
            color: var(--color-text-primary);
            border-color: var(--color-border);
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--color-accent);
            box-shadow: 0 0 0 0.25rem rgba(var(--color-accent-rgb, 76, 175, 80), 0.25);
        }

        :root {
            --color-accent-rgb: 76, 175, 80;
        }

        .btn-primary {
            background-color: var(--color-accent);
            border-color: var(--color-accent-dark);
        }

        .btn-primary:hover {
            background-color: var(--color-accent-dark);
            border-color: var(--color-accent);
        }

        .btn-danger {
            background-color: var(--color-error);
            border-color: var(--color-error);
        }

        .btn-danger:hover {
            background-color: darken(var(--color-error), 10%);
            border-color: darken(var(--color-error), 10%);
        }

        .modal-content {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
        }

        .modal-header {
            background-color: var(--color-bg-primary);
            border-bottom-color: var(--color-border);
        }

        .modal-footer {
            border-top-color: var(--color-border);
        }

        .modal-title {
            color: var(--color-text-secondary);
        }

        .ayah-arabic-text {
            font-family: var(--font-arabic);
            font-size: 1.5rem;
            direction: rtl;
            text-align: right;
        }

        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
        }

        .toast {
            background-color: var(--color-bg-secondary);
            color: var(--color-text-primary);
            border-color: var(--color-border);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .toast-header {
            background-color: var(--color-bg-primary);
            color: var(--color-text-secondary);
            border-bottom-color: var(--color-border);
        }

        .hifz-ayah-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            margin-left: 10px;
        }

        .status-not-started {
            background-color: #e0e0e0;
            color: #424242;
        }

        .status-in-progress {
            background-color: #fff59d;
            color: #fbc02d;
        }

        .status-memorized {
            background-color: #a5d6a7;
            color: #388e3c;
        }

        .progress-bar-bg {
            background-color: #e9ecef;
            border-radius: 0.25rem;
            height: 20px;
            overflow: hidden;
            margin-top: 5px;
        }

        .progress-bar {
            height: 100%;
            background-color: var(--color-accent);
            text-align: center;
            color: white;
            line-height: 20px;
            transition: width 0.6s ease;
        }

        .form-check.form-switch {
            min-height: 1.5rem;
            padding-left: 3.5em;
            cursor: pointer;
        }

        .form-check.form-switch .form-check-input {
            width: 3em;
            height: 1.5em;
            margin-left: -3.5em;
            background-color: var(--color-bg-primary);
            border-color: var(--color-border);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='rgba%280,0,0,0.25%29'/%3e%3c/svg%3e");
        }

        .form-check.form-switch .form-check-input:checked {
            background-color: var(--color-accent);
            border-color: var(--color-accent);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3e%3ccircle r='3' fill='%23fff'/%3e%3c/svg%3e");
        }
    </style>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Scheherazade+New:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Mirza:wght@700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lateef&display=swap">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&display=swap">
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Nur-Ul-Quran Studio</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" data-bs-target="#dashboard-tab" href="#dashboard-tab" id="dashboard-tab-link">Dashboard</a>
                    </li>
                    <?php if ($user_role === 'admin') : ?>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-users-tab" href="#admin-users-tab" id="admin-users-tab-link">User Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-quran-tab" href="#admin-quran-tab" id="admin-quran-tab-link">Quran Content</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-word-translations-tab" href="#admin-word-translations-tab" id="admin-word-translations-tab-link">Word Translations</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-languages-tab" href="#admin-languages-tab" id="admin-languages-tab-link">Language Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-tafsir-tab" href="#admin-tafsir-tab" id="admin-tafsir-tab-link">Tafsir Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-themes-tab" href="#admin-themes-tab" id="admin-themes-tab-link">Theme Management</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#admin-roots-tab" href="#admin-roots-tab" id="admin-roots-tab-link">Root Word Management</a>
                        </li>
                    <?php else : ?>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-tafsir-tab" href="#user-tafsir-tab" id="user-tafsir-tab-link">My Tafsir</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-themes-tab" href="#user-themes-tab" id="user-themes-tab-link">My Themes & Links</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-roots-tab" href="#user-roots-tab" id="user-roots-tab-link">My Root Words</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-recitation-tab" href="#user-recitation-tab" id="user-recitation-tab-link">My Recitation Log</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-hifz-tab" href="#user-hifz-tab" id="user-hifz-tab-link">My Hifz Plan</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-goals-tab" href="#user-goals-tab" id="user-goals-tab-link">My Goals</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-contributions-tab" href="#user-contributions-tab" id="user-contributions-tab-link">My Contributions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" data-bs-target="#user-data-tab" href="#user-data-tab" id="user-data-tab-link">Data Management</a>
                        </li>
                    <?php endif; ?>
                </ul>
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($_SESSION['username']); ?> (<?= htmlspecialchars($_SESSION['role']); ?>)!
                </span>
                <a href="index.php?action=logout" class="btn btn-outline-danger">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container-fluid py-4">
        <div class="tab-content">
            <div class="tab-pane fade show active" id="dashboard-tab" role="tabpanel">
                <?php if ($user_role === 'admin') : ?>
                    <!-- Admin Dashboard View -->
                    <h2 class="mb-4">Admin Dashboard Overview</h2>
                    <div class="row" id="admin-stats-dashboard">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Total Users</h5>
                                    <p class="card-text fs-3" id="stat-total-users">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Total Tafsir Notes</h5>
                                    <p class="card-text fs-3" id="stat-total-tafsir">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Total Themes</h5>
                                    <p class="card-text fs-3" id="stat-total-themes">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">Word Trans. Submitted</h5>
                                    <p class="card-text fs-3" id="stat-total-word-translations">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <!-- Registered User Dashboard View -->
                    <h2 class="mb-4">My Dashboard</h2>
                    <div class="row" id="user-stats-dashboard">
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">My Tafsir Notes</h5>
                                    <p class="card-text fs-3" id="stat-my-tafsir">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">My Memorized Ayahs</h5>
                                    <p class="card-text fs-3" id="stat-my-hifz">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">My Active Goals</h5>
                                    <p class="card-text fs-3" id="stat-my-goals">0</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">My Contributions (Unapproved)</h5>
                                    <p class="card-text fs-3" id="stat-my-contributions">0</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($user_role === 'admin') : ?>
                <!-- Admin Tabs -->
                <div class="tab-pane fade" id="admin-users-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">User Management</div>
                        <div class="card-body">
                            <table id="usersTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Created At</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="admin-quran-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">Quran Content Management</div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="adminQuranSurahSelect" class="form-label">Surah:</label>
                                    <select id="adminQuranSurahSelect" class="form-select"></select>
                                </div>
                                <div class="col-md-6">
                                    <label for="adminQuranAyahSelect" class="form-label">Ayah:</label>
                                    <select id="adminQuranAyahSelect" class="form-select"></select>
                                </div>
                            </div>
                            <div id="adminQuranAyahContent" class="mb-3">
                                <p class="ayah-arabic-text text-center">Select a Surah and Ayah to load content.</p>
                            </div>
                            <div id="adminQuranTranslationsEdit">
                                <!-- Translation textareas will be loaded here dynamically -->
                            </div>
                            <button id="adminSaveQuranTranslationsBtn" class="btn btn-primary mt-3">Save All Translations</button>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="admin-word-translations-tab" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header">Word-by-Word Translation Approval Queue</div>
                        <div class="card-body">
                            <table id="wordTranslationApprovalTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Word ID</th>
                                        <th>Arabic Word</th>
                                        <th>Language</th>
                                        <th>Translation</th>
                                        <th>Submitted By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">Full Word-by-Word Translation Editor</div>
                        <div class="card-body">
                            <p>Search and edit any word translation directly.</p>
                            <table id="allWordTranslationsTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Word ID</th>
                                        <th>Arabic Word</th>
                                        <th>Surah:Ayah</th>
                                        <!-- Dynamic language columns will be inserted here -->
                                        <th>Approved By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="admin-languages-tab" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header">Add New Language</div>
                        <div class="card-body">
                            <form id="addLanguageForm" class="row g-3">
                                <div class="col-md-6">
                                    <label for="newLangKey" class="form-label">Language Key (e.g., 'es', 'fr', no spaces/special chars):</label>
                                    <input type="text" class="form-control" id="newLangKey" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="newLangLabel" class="form-label">Language Label (e.g., 'Spanish', 'French'):</label>
                                    <input type="text" class="form-control" id="newLangLabel" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="newLangCode" class="form-label">ISO 639-1 Code (e.g., 'es', 'fr'):</label>
                                    <input type="text" class="form-control" id="newLangCode" required maxlength="2">
                                </div>
                                <div class="col-md-6">
                                    <label for="newLangDirection" class="form-label">Text Direction:</label>
                                    <select class="form-select" id="newLangDirection" required>
                                        <option value="ltr">LTR (Left-to-Right)</option>
                                        <option value="rtl">RTL (Right-to-Left)</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label for="newLangFontVar" class="form-label">CSS Font Variable (e.g., 'var(--font-custom)', use 'calibri' as fallback):</label>
                                    <input type="text" class="form-control" id="newLangFontVar" value="calibri">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Add Language</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">Existing Languages</div>
                        <div class="card-body">
                            <table id="languagesTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Key</th>
                                        <th>Label</th>
                                        <th>Code</th>
                                        <th>Direction</th>
                                        <th>Font Variable</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="admin-tafsir-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">All User Tafsir Notes Management</div>
                        <div class="card-body">
                            <table id="adminTafsirTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Surah:Ayah</th>
                                        <th>Notes</th>
                                        <th>Public</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="admin-themes-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">All User Themes Management</div>
                        <div class="card-body">
                            <table id="adminThemesTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Theme Name</th>
                                        <th>Description</th>
                                        <th>Public</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="admin-roots-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">All User Root Word Notes Management</div>
                        <div class="card-body">
                            <table id="adminRootsTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Root Word</th>
                                        <th>Notes</th>
                                        <th>Public</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <!-- Registered User Tabs -->
                <div class="tab-pane fade" id="user-tafsir-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">My Personal Tafsir Notes</div>
                        <div class="card-body">
                            <table id="myTafsirTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Surah:Ayah</th>
                                        <th>Arabic Text</th>
                                        <th>My Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-themes-tab" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header">Manage My Themes</div>
                        <div class="card-body">
                            <form id="addThemeForm" class="mb-3 row g-3">
                                <div class="col-md-6">
                                    <label for="newThemeName" class="form-label">New Theme Name:</label>
                                    <input type="text" class="form-control" id="newThemeName" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="newThemeDescription" class="form-label">Description:</label>
                                    <textarea class="form-control" id="newThemeDescription" rows="1"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">Add Theme</button>
                                </div>
                            </form>
                            <table id="myThemesTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Theme Name</th>
                                        <th>Description</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">Ayahs Linked to Theme: <span id="currentThemeLinkedAyahs"></span></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="selectThemeToViewLinks" class="form-label">Select Theme:</label>
                                <select id="selectThemeToViewLinks" class="form-select">
                                    <option value="">-- Select a Theme --</option>
                                </select>
                            </div>
                            <table id="myThemeLinksTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Surah:Ayah</th>
                                        <th>Arabic Text</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-roots-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">My Root Word Notes</div>
                        <div class="card-body">
                            <table id="myRootsTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Root Word</th>
                                        <th>My Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-recitation-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">My Recitation Log</div>
                        <div class="card-body">
                            <table id="myRecitationsTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Surah</th>
                                        <th>Ayah Range</th>
                                        <th>Qari</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-hifz-tab" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header">My Hifz Progress Overview</div>
                        <div class="card-body" id="hifz-overview-cards">
                            <p>Loading Hifz data...</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">My Memorized & In-Progress Ayahs</div>
                        <div class="card-body">
                            <table id="myHifzTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Surah:Ayah</th>
                                        <th>Status</th>
                                        <th>Last Review</th>
                                        <th>Next Review</th>
                                        <th>Review Count</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-goals-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">My Goals</div>
                        <div class="card-body">
                            <div class="add-goal-form mb-4">
                                <h3>Add a New Goal</h3>
                                <form id="goal-form" class="row g-3">
                                    <div class="col-md-6">
                                        <label for="goal-title" class="form-label">Goal Title:</label>
                                        <input type="text" class="form-control" id="goal-title" required placeholder="e.g., Complete First Khatam">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="goal-type" class="form-label">Goal Type:</label>
                                        <select id="goal-type" class="form-select" required>
                                            <option value="">-- Select Type --</option>
                                            <option value="read_surah">Read a Surah</option>
                                            <option value="listen_surah">Listen to a Surah</option>
                                            <option value="read_quran">Read Entire Quran</option>
                                            <option value="listen_quran">Listen to Entire Quran</option>
                                            <option value="read_ayahs_daily">Read Ayahs Daily</option>
                                            <option value="listen_ayahs_daily">Listen to Ayahs Daily</option>
                                            <option value="memorize_surah">Memorize a Surah</option>
                                            <option value="tafsir_juz">Complete Tafsir for a Juz</option>
                                            <option value="link_theme">Link Ayahs to a Theme</option>
                                            <option value="recurring_surah_daily">Recite Surah Daily (Habit)</option>
                                            <option value="recurring_surah_weekly">Recite Surah Weekly (Habit)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6" id="goal-target-wrapper"></div>
                                    <div class="col-md-6" id="goal-count-wrapper"></div>
                                    <div class="col-md-6">
                                        <label for="goal-date" class="form-label">Target Date (for completion goals):</label>
                                        <input type="date" class="form-control" id="goal-date">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-primary">Add Goal</button>
                                    </div>
                                </form>
                            </div>
                            <div class="mb-3">
                                <h4 class="mb-3">My Current Goals</h4>
                                <table id="myActiveGoalsTable" class="table table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Target</th>
                                            <th>Progress</th>
                                            <th>Due Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                            <div>
                                <h4 class="mb-3">Completed Goals</h4>
                                <table id="myCompletedGoalsTable" class="table table-striped table-hover w-100">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Target</th>
                                            <th>Progress</th>
                                            <th>Completed On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-contributions-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">My Word-by-Word Translation Contributions</div>
                        <div class="card-body">
                            <table id="myContributionsTable" class="table table-striped table-hover w-100">
                                <thead>
                                    <tr>
                                        <th>Word ID</th>
                                        <th>Arabic Word</th>
                                        <th>My Translation</th>
                                        <th>Language</th>
                                        <th>Approval Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="user-data-tab" role="tabpanel">
                    <div class="card mb-4">
                        <div class="card-header">Backup My Data</div>
                        <div class="card-body">
                            <p>Export your personal data (Tafsir, Themes, Roots, Logs, Hifz, Goals) as a JSON file.</p>
                            <button id="exportUserDataBtn" class="btn btn-primary">Export Data</button>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-header">Restore My Data</div>
                        <div class="card-body">
                            <p>Import your personal data from a JSON file. This will overwrite existing data. Proceed with caution.</p>
                            <input type="file" id="importUserDataFile" accept="application/json" class="form-control mb-2">
                            <button id="importUserDataBtn" class="btn btn-warning" disabled>Import Data</button>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header text-danger">Clear All My Personal Data</div>
                        <div class="card-body">
                            <p class="text-danger"><strong>Warning:</strong> This will permanently delete ALL your personal Tafsir, Themes, Roots, Logs, Hifz, and Goal data. This action cannot be undone.</p>
                            <button id="clearAllUserDataBtn" class="btn btn-danger">Clear All Data</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Modals -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User Role & Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editUserId">
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username:</label>
                        <input type="text" class="form-control" id="editUsername" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="editUserFullName" class="form-label">Full Name:</label>
                        <input type="text" class="form-control" id="editUserFullName">
                    </div>
                    <div class="mb-3">
                        <label for="editUserRole" class="form-label">Role:</label>
                        <select class="form-select" id="editUserRole">
                            <option value="public">Public</option>
                            <option value="registered">Registered</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveUserChangesBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editTafsirModal" tabindex="-1" aria-labelledby="editTafsirModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTafsirModalLabel">Edit Tafsir Notes for <span id="tafsirModalAyahRef"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editTafsirSurah">
                    <input type="hidden" id="editTafsirAyah">
                    <div class="mb-3">
                        <label class="form-label">Arabic Text:</label>
                        <p class="ayah-arabic-text" id="editTafsirArabicText"></p>
                    </div>
                    <div class="mb-3">
                        <label for="editTafsirNotes" class="form-label">My Notes:</label>
                        <textarea class="form-control" id="editTafsirNotes" rows="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveTafsirNotesBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editThemeModal" tabindex="-1" aria-labelledby="editThemeModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editThemeModalLabel">Edit Theme</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editThemeId">
                    <div class="mb-3">
                        <label for="editThemeName" class="form-label">Theme Name:</label>
                        <input type="text" class="form-control" id="editThemeName">
                    </div>
                    <div class="mb-3">
                        <label for="editThemeDescription" class="form-label">Description:</label>
                        <textarea class="form-control" id="editThemeDescription" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveThemeBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editRootModal" tabindex="-1" aria-labelledby="editRootModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRootModalLabel">Edit Root Word Notes for <span id="rootModalRootWord"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editRootWordInput">
                    <div class="mb-3">
                        <label for="editRootDescription" class="form-label">My Notes:</label>
                        <textarea class="form-control" id="editRootDescription" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveRootNotesBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editRecitationModal" tabindex="-1" aria-labelledby="editRecitationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRecitationModalLabel">Edit Recitation Log</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editRecitationId">
                    <div class="mb-3">
                        <label for="editRecitationSurah" class="form-label">Surah:</label>
                        <select id="editRecitationSurah" class="form-select"></select>
                    </div>
                    <div class="mb-3">
                        <label for="editRecitationAyahStart" class="form-label">Ayah Start:</label>
                        <input type="number" class="form-control" id="editRecitationAyahStart">
                    </div>
                    <div class="mb-3">
                        <label for="editRecitationAyahEnd" class="form-label">Ayah End:</label>
                        <input type="number" class="form-control" id="editRecitationAyahEnd">
                    </div>
                    <div class="mb-3">
                        <label for="editRecitationQari" class="form-label">Qari/Source:</label>
                        <input type="text" class="form-control" id="editRecitationQari">
                    </div>
                    <div class="mb-3">
                        <label for="editRecitationDate" class="form-label">Date:</label>
                        <input type="date" class="form-control" id="editRecitationDate">
                    </div>
                    <div class="mb-3">
                        <label for="editRecitationNotes" class="form-label">Notes:</label>
                        <textarea class="form-control" id="editRecitationNotes" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveRecitationLogBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editHifzModal" tabindex="-1" aria-labelledby="editHifzModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editHifzModalLabel">Edit Hifz Status for <span id="hifzModalAyahRef"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editHifzSurah">
                    <input type="hidden" id="editHifzAyah">
                    <div class="mb-3">
                        <label for="editHifzStatus" class="form-label">Status:</label>
                        <select id="editHifzStatus" class="form-select">
                            <option value="not-started">Not Started</option>
                            <option value="in-progress">In Progress</option>
                            <option value="memorized">Memorized</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editHifzLastReview" class="form-label">Last Review Date:</label>
                        <input type="date" class="form-control" id="editHifzLastReview">
                    </div>
                    <div class="mb-3">
                        <label for="editHifzNextReview" class="form-label">Next Review Date:</label>
                        <input type="date" class="form-control" id="editHifzNextReview">
                    </div>
                    <div class="mb-3">
                        <label for="editHifzReviewCount" class="form-label">Review Count:</label>
                        <input type="number" class="form-control" id="editHifzReviewCount" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="editHifzNotes" class="form-label">Notes:</label>
                        <textarea class="form-control" id="editHifzNotes" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveHifzStatusBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editWordTranslationModal" tabindex="-1" aria-labelledby="editWordTranslationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editWordTranslationModalLabel">Edit Word Translation for <span id="wordTransModalWord"></span> (ID: <span id="wordTransModalId"></span>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="wordTranslationEditForm">
                    <!-- Dynamic translation textareas -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveWordTranslationsBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editLanguageModal" tabindex="-1" aria-labelledby="editLanguageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editLanguageModalLabel">Edit Language</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editLangOriginalKey">
                    <div class="mb-3">
                        <label for="editLangKey" class="form-label">Language Key:</label>
                        <input type="text" class="form-control" id="editLangKey" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="editLangLabel" class="form-label">Label:</label>
                        <input type="text" class="form-control" id="editLangLabel">
                    </div>
                    <div class="mb-3">
                        <label for="editLangCode" class="form-label">ISO Code:</label>
                        <input type="text" class="form-control" id="editLangCode">
                    </div>
                    <div class="mb-3">
                        <label for="editLangDirection" class="form-label">Direction:</label>
                        <select class="form-select" id="editLangDirection">
                            <option value="ltr">LTR</option>
                            <option value="rtl">RTL</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editLangFontVar" class="form-label">Font Variable:</label>
                        <input type="text" class="form-control" id="editLangFontVar">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveLanguageBtn">Save changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="editGoalModal" tabindex="-1" aria-labelledby="editGoalModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editGoalModalLabel">Edit Goal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editGoalId">
                    <div class="mb-3">
                        <label for="editGoalTitle" class="form-label">Goal Title:</label>
                        <input type="text" class="form-control" id="editGoalTitle">
                    </div>
                    <div class="mb-3">
                        <label for="editGoalTargetDate" class="form-label">Target Date:</label>
                        <input type="date" class="form-control" id="editGoalTargetDate">
                    </div>
                    <div class="mb-3 form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="editGoalIsComplete">
                        <label class="form-check-label" for="editGoalIsComplete">Mark as Complete</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveGoalBtn">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
    <div class="toast-container"></div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/2.0.7/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const ajax_url = 'manage.php'; 
        const surahNames = <?= json_encode($surah_names); ?>;
        const surahAyahCounts = <?= json_encode($surah_ayah_counts); ?>;
        const juzBoundariesData = <?= json_encode($juz_boundaries_data); ?>;
        const allLanguagesConfig = <?= json_encode($translation_config); ?>;
        const userRole = '<?= $user_role; ?>';
        const currentUserId = <?= $user_id; ?>;
        let myTafsirTable, myThemesTable, myThemeLinksTable, myRootsTable, myRecitationsTable, myHifzTable, myActiveGoalsTable, myCompletedGoalsTable, myContributionsTable;
        let usersTable, wordTranslationApprovalTable, allWordTranslationsTable, languagesTable, adminTafsirTable, adminThemesTable, adminRootsTable;

        $(document).ready(function() {
            function showToast(message, type = 'success') {
                const toastId = `toast-${Date.now()}`;
                const toastHtml = `
                    <div id="${toastId}" class="toast align-items-center text-bg-${type === 'success' ? 'success' : 'danger'} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="d-flex">
                            <div class="toast-body">${message}</div>
                            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                    </div>
                `;
                $('.toast-container').append(toastHtml);
                const toastEl = new bootstrap.Toast(document.getElementById(toastId), {
                    autohide: true,
                    delay: 5000
                });
                toastEl.show();
            }

            function sendAjaxRequest(action, data) {
                return $.ajax({
                    url: ajax_url,
                    type: 'POST',
                    data: {
                        action: action,
                        ...data
                    },
                    dataType: 'json'
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    let errorMessage = `AJAX Error: ${textStatus}`;
                    if (errorThrown) errorMessage += ` - ${errorThrown}`;
                    if (jqXHR.responseText) errorMessage += `\nServer Response: ${jqXHR.responseText.substring(0, 200)}...`; // Log first 200 chars
                    console.error('Failed AJAX request:', errorMessage, jqXHR);
                    showToast('Error communicating with server or processing data. Check console for details.', 'error');
                });
            }

            $('a[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                e.preventDefault(); 
                const targetId = $(e.target).attr('href');

                if (targetId === '#dashboard-tab') {
                    loadDashboardStats();
                } else if (targetId === '#admin-users-tab') {
                    loadUsers();
                } else if (targetId === '#admin-quran-tab') {
                    populateAdminQuranSelectors();
                } else if (targetId === '#admin-word-translations-tab') {
                    loadWordTranslationApprovalQueue();
                    loadAllWordTranslations();
                } else if (targetId === '#admin-languages-tab') {
                    loadLanguages();
                } else if (targetId === '#admin-tafsir-tab') {
                    loadAdminTafsir();
                } else if (targetId === '#admin-themes-tab') {
                    loadAdminThemes();
                } else if (targetId === '#admin-roots-tab') {
                    loadAdminRoots();
                } else if (targetId === '#user-tafsir-tab') {
                    loadMyTafsir();
                } else if (targetId === '#user-themes-tab') {
                    loadMyThemes();
                    populateThemeLinksSelect();
                    loadMyThemeLinks($('#selectThemeToViewLinks').val());
                } else if (targetId === '#user-roots-tab') {
                    loadMyRoots();
                } else if (targetId === '#user-recitation-tab') {
                    loadMyRecitations();
                } else if (targetId === '#user-hifz-tab') {
                    loadMyHifzOverview();
                    loadMyHifzDetails();
                } else if (targetId === '#user-goals-tab') {
                    renderGoalsUI();
                } else if (targetId === '#user-contributions-tab') {
                    loadMyContributions();
                }
            });
            loadDashboardStats();

            function populateSurahAyahSelectors(surahSelectId, ayahSelectId) {
                const surahSelect = $(`#${surahSelectId}`);
                const ayahSelect = $(`#${ayahSelectId}`);
                surahSelect.empty();
                for (let i = 1; i <= 114; i++) {
                    surahSelect.append(`<option value="${i}">${i}. ${surahNames[i - 1]}</option>`);
                }
                surahSelect.on('change', function() {
                    const surahNum = parseInt($(this).val());
                    ayahSelect.empty();
                    const totalAyahs = surahAyahCounts[surahNum] || 0;
                    for (let i = 1; i <= totalAyahs; i++) {
                        ayahSelect.append(`<option value="${i}">${i}</option>`);
                    }
                });
                $(`#${surahSelectId}`).trigger('change');
            }

            async function loadDashboardStats() {
                if (userRole === 'admin') {
                    try {
                        const [
                            usersResult,
                            tafsirResult,
                            themesResult,
                            wordTranslationsResult
                        ] = await Promise.all([
                            sendAjaxRequest('get_all_users'),
                            sendAjaxRequest('get_all_tafsir'),
                            sendAjaxRequest('get_all_themes'),
                            sendAjaxRequest('get_all_word_translations_full')
                        ]);

                        $('#stat-total-users').text(usersResult.data ? usersResult.data.length : 0);
                        $('#stat-total-tafsir').text(tafsirResult.data ? tafsirResult.data.length : 0);
                        $('#stat-total-themes').text(themesResult.data ? themesResult.data.length : 0);
                        $('#stat-total-word-translations').text(wordTranslationsResult.data ? wordTranslationsResult.data.length : 0);
                    } catch (error) {
                        showToast('Error loading admin dashboard stats.', 'error');
                        console.error('Error loading admin dashboard stats:', error);
                    }
                } else { 
                    try {
                        const [
                            tafsirResult,
                            hifzResult,
                            goalsResult,
                            contributionsResult
                        ] = await Promise.all([
                            sendAjaxRequest('get_all_tafsir'),
                            sendAjaxRequest('get_all_hifz'),
                            sendAjaxRequest('get_all_goals'),
                            sendAjaxRequest('get_user_submitted_word_translations')
                        ]);

                        $('#stat-my-tafsir').text(tafsirResult.data ? tafsirResult.data.length : 0);
                        $('#stat-my-hifz').text(hifzResult.data ? hifzResult.data.filter(h => h.status === 'memorized').length : 0);
                        $('#stat-my-goals').text(goalsResult.data ? goalsResult.data.filter(g => !g.is_complete).length : 0);
                        $('#stat-my-contributions').text(contributionsResult.data ? contributionsResult.data.filter(c => !c.approved_by).length : 0);
                    } catch (error) {
                        showToast('Error loading user dashboard stats.', 'error');
                        console.error('Error loading user dashboard stats:', error);
                    }
                }
            }

            if (userRole === 'admin') {
                function loadUsers() {
                    if (usersTable) {
                        usersTable.destroy();
                    }
                    sendAjaxRequest('get_all_users').done(function(response) {
                        if (response.success) {
                            usersTable = $('#usersTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'id'
                                }, {
                                    data: 'username'
                                }, {
                                    data: 'full_name'
                                }, {
                                    data: 'email'
                                }, {
                                    data: 'role'
                                }, {
                                    data: 'created_at'
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                    <button class="btn btn-sm btn-info edit-user-btn" data-id="${data.id}" data-username="${data.username}" data-full-name="${escapeHtml(data.full_name)}" data-role="${data.role}">Edit</button>
                                    <button class="btn btn-sm btn-danger delete-user-btn" data-id="${data.id}">Delete</button>
                                `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load users: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.edit-user-btn', function() {
                    const id = $(this).data('id');
                    const username = $(this).data('username');
                    const fullName = unescapeHtml($(this).data('full-name'));
                    const role = $(this).data('role');
                    $('#editUserId').val(id);
                    $('#editUsername').val(username);
                    $('#editUserFullName').val(fullName);
                    $('#editUserRole').val(role);
                    const editUserModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                    editUserModal.show();
                });
                $('#saveUserChangesBtn').on('click', async function() {
                    const id = $('#editUserId').val();
                    const newRole = $('#editUserRole').val();
                    const newFullName = $('#editUserFullName').val();

                    let roleUpdateSuccess = true;
                    if (newRole !== $('#editUserRole').data('original-role')) {
                        try {
                            const roleResponse = await sendAjaxRequest('update_user_role', {
                                user_id: id,
                                new_role: newRole
                            });
                            if (!roleResponse.success) {
                                roleUpdateSuccess = false;
                                showToast('Failed to update user role: ' + roleResponse.message, 'error');
                            }
                        } catch (error) {
                            roleUpdateSuccess = false;
                            showToast('Error updating user role.', 'error');
                        }
                    }

                    let fullNameUpdateSuccess = true;
                    try {
                        const fullNameResponse = await sendAjaxRequest('update_user_full_name', {
                            user_id: id,
                            full_name: newFullName
                        });
                        if (!fullNameResponse.success) {
                            fullNameUpdateSuccess = false;
                            showToast('Failed to update user full name: ' + fullNameResponse.message, 'error');
                        }
                    } catch (error) {
                        fullNameUpdateSuccess = false;
                        showToast('Error updating user full name.', 'error');
                    }

                    if (roleUpdateSuccess && fullNameUpdateSuccess) {
                        showToast('User details updated successfully.');
                        loadUsers();
                        bootstrap.Modal.getInstance(document.getElementById('editUserModal')).hide();
                    }
                });
                $(document).on('click', '.delete-user-btn', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to delete this user? This action is irreversible.')) {
                        sendAjaxRequest('delete_user', {
                            user_id: id
                        }).done(function(response) {
                            if (response.success) {
                                showToast('User deleted successfully.');
                                loadUsers();
                            } else {
                                showToast('Failed to delete user: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function populateAdminQuranSelectors() {
                    populateSurahAyahSelectors('adminQuranSurahSelect', 'adminQuranAyahSelect');
                    $('#adminQuranSurahSelect').off('change').on('change', function() {
                        const surahNum = parseInt($(this).val());
                        $('#adminQuranAyahSelect').empty();
                        const totalAyahs = surahAyahCounts[surahNum] || 0;
                        for (let i = 1; i <= totalAyahs; i++) {
                            $('#adminQuranAyahSelect').append(`<option value="${i}">${i}</option>`);
                        }
                        loadAdminQuranAyah();
                    });
                    $('#adminQuranAyahSelect').off('change').on('change', loadAdminQuranAyah);
                    loadAdminQuranAyah();
                }

                async function loadAdminQuranAyah() {
                    const surah = parseInt($('#adminQuranSurahSelect').val());
                    const ayah = parseInt($('#adminQuranAyahSelect').val());
                    if (isNaN(surah) || isNaN(ayah)) {
                        $('#adminQuranAyahContent').html('<p class="ayah-arabic-text text-center">Select a Surah and Ayah to load content.</p>');
                        $('#adminQuranTranslationsEdit').empty();
                        return;
                    }
                    try {
                        const response = await sendAjaxRequest('load_quran_ayah', {
                            surah: surah,
                            ayah: ayah
                        });
                        if (response.success && response.data) {
                            const ayahData = response.data;
                            $('#adminQuranAyahContent').html(`<p class="ayah-arabic-text">${ayahData.arabic}</p>`);
                            $('#adminQuranTranslationsEdit').empty();
                            allLanguagesConfig.forEach(lang => {
                                const textareaHtml = `
                                    <div class="mb-3">
                                        <label for="admin-translation-${lang.key}" class="form-label">${lang.label} Translation:</label>
                                        <textarea class="form-control" id="admin-translation-${lang.key}" rows="3" dir="${lang.direction}" style="font-family: ${lang.font_var};">${ayahData[lang.key] || ''}</textarea>
                                    </div>
                                `;
                                $('#adminQuranTranslationsEdit').append(textareaHtml);
                            });
                        } else {
                            showToast('Failed to load Ayah content: ' + response.message, 'error');
                        }
                    } catch (error) {
                        showToast('Error communicating with server.', 'error');
                        console.error('Error loading Ayah content:', error);
                    }
                }
                $('#adminSaveQuranTranslationsBtn').on('click', async function() {
                    const surah = parseInt($('#adminQuranSurahSelect').val());
                    const ayah = parseInt($('#adminQuranAyahSelect').val());
                    if (isNaN(surah) || isNaN(ayah)) {
                        showToast('Please select a Surah and Ayah.', 'error');
                        return;
                    }
                    let allSuccess = true;
                    for (const lang of allLanguagesConfig) {
                        const translationText = $(`#admin-translation-${lang.key}`).val();
                        try {
                            const response = await sendAjaxRequest('admin_update_quran_translation', {
                                surah: surah,
                                ayah: ayah,
                                lang_key: lang.key,
                                translation_text: translationText
                            });
                            if (!response.success) {
                                allSuccess = false;
                                showToast(`Failed to save ${lang.label} translation: ${response.message}`, 'error');
                            }
                        } catch (error) {
                            allSuccess = false;
                            showToast(`Error saving ${lang.label} translation.`, 'error');
                            console.error(`Error saving ${lang.label} translation:`, error);
                        }
                    }
                    if (allSuccess) {
                        showToast('All Quran translations updated successfully.');
                    }
                });

                function loadWordTranslationApprovalQueue() {
                    if (wordTranslationApprovalTable) {
                        wordTranslationApprovalTable.destroy();
                    }
                    sendAjaxRequest('get_unapproved_word_translations').done(function(response) {
                        if (response.success) {
                            wordTranslationApprovalTable = $('#wordTranslationApprovalTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'word_id'
                                }, {
                                    data: 'arabic_word'
                                }, {
                                    data: 'lang_label'
                                }, {
                                    data: 'translation'
                                }, {
                                    data: 'username'
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-success approve-word-trans-btn" data-word-id="${data.word_id}" data-lang-key="${data.lang_key}" data-translation="${escapeHtml(data.translation)}">Approve</button>
                                            <button class="btn btn-sm btn-danger reject-word-trans-btn" data-word-id="${data.word_id}" data-lang-key="${data.lang_key}">Reject</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load approval queue: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.approve-word-trans-btn', function() {
                    const wordId = $(this).data('word-id');
                    const langKey = $(this).data('lang-key');
                    const translation = unescapeHtml($(this).data('translation'));
                    sendAjaxRequest('edit_word_translation', {
                        word_id: wordId,
                        lang_key: langKey,
                        translation_text: translation,
                        admin_approve: true
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Translation approved successfully.');
                            loadWordTranslationApprovalQueue();
                            loadAllWordTranslations();
                        } else {
                            showToast('Failed to approve translation: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.reject-word-trans-btn', function() {
                    const wordId = $(this).data('word-id');
                    const langKey = $(this).data('lang-key');
                    if (confirm('Are you sure you want to reject/delete this translation?')) {
                        sendAjaxRequest('delete_word_translation', {
                            word_id: wordId,
                            lang_key: langKey
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Translation rejected/deleted successfully.');
                                loadWordTranslationApprovalQueue();
                                loadAllWordTranslations();
                            } else {
                                showToast('Failed to reject translation: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function loadAllWordTranslations() {
                    if (allWordTranslationsTable) {
                        allWordTranslationsTable.destroy();
                    }
                    sendAjaxRequest('get_all_word_translations_full').done(function(response) {
                        if (response.success) {
                            const tableColumns = [{
                                data: 'word_id'
                            }, {
                                data: 'arabic_word'
                            }, {
                                data: 'surah_ayah'
                            }];
                            const langColumns = allLanguagesConfig.map(lang => ({
                                data: lang.word_col_name,
                                title: lang.label
                            }));
                            tableColumns.push(...langColumns);
                            tableColumns.push({
                                data: 'approved_by_username'
                            }, {
                                data: null,
                                render: function(data) {
                                    return `
                                        <button class="btn btn-sm btn-info edit-all-word-trans-btn" data-id="${data.word_id}" data-arabic="${escapeHtml(data.arabic_word)}">Edit</button>
                                    `;
                                }
                            });
                            allWordTranslationsTable = $('#allWordTranslationsTable').DataTable({
                                data: response.data,
                                columns: tableColumns,
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load all word translations: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.edit-all-word-trans-btn', async function() {
                    const wordId = $(this).data('id');
                    const arabicWord = $(this).data('arabic');
                    $('#wordTransModalWord').text(arabicWord);
                    $('#wordTransModalId').text(wordId);
                    $('#wordTranslationEditForm').empty();
                    try {
                        const response = await sendAjaxRequest('get_word_translation', {
                            word_id: wordId
                        });
                        const currentTranslations = response.success ? response.data : {};
                        allLanguagesConfig.forEach(lang => {
                            const textareaHtml = `
                                <div class="mb-3">
                                    <label for="edit-word-trans-${lang.key}" class="form-label">${lang.label}:</label>
                                    <textarea class="form-control" id="edit-word-trans-${lang.key}" rows="2" dir="${lang.direction}" style="font-family: ${lang.font_var};">${currentTranslations[lang.word_col_name] || ''}</textarea>
                                </div>
                            `;
                            $('#wordTranslationEditForm').append(textareaHtml);
                        });
                        $('#saveWordTranslationsBtn').data('word-id', wordId);
                        const editWordTranslationModal = new bootstrap.Modal(document.getElementById('editWordTranslationModal'));
                        editWordTranslationModal.show();
                    } catch (error) {
                        showToast('Error loading word translation for editing.', 'error');
                        console.error('Error loading word translation for editing:', error);
                    }
                });
                $('#saveWordTranslationsBtn').on('click', async function() {
                    const wordId = $(this).data('word-id');
                    let allSuccess = true;
                    for (const lang of allLanguagesConfig) {
                        const translationText = $(`#edit-word-trans-${lang.key}`).val();
                        try {
                            const response = await sendAjaxRequest('edit_word_translation', {
                                word_id: wordId,
                                lang_key: lang.key,
                                translation_text: translationText,
                                admin_approve: true
                            });
                            if (!response.success) {
                                allSuccess = false;
                                showToast(`Failed to save ${lang.label} translation: ${response.message}`, 'error');
                            }
                        } catch (error) {
                            allSuccess = false;
                            showToast(`Error saving ${lang.label} translation.`, 'error');
                            console.error(`Error saving ${lang.label} translation:`, error);
                        }
                    }
                    if (allSuccess) {
                        showToast('Word translations updated successfully.');
                        loadAllWordTranslations();
                        loadWordTranslationApprovalQueue();
                        bootstrap.Modal.getInstance(document.getElementById('editWordTranslationModal')).hide();
                    }
                });

                function loadLanguages() {
                    if (languagesTable) {
                        languagesTable.destroy();
                    }
                    sendAjaxRequest('get_all_languages').done(function(response) {
                        if (response.success) {
                            languagesTable = $('#languagesTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'key'
                                }, {
                                    data: 'label'
                                }, {
                                    data: 'lang_code'
                                }, {
                                    data: 'direction'
                                }, {
                                    data: 'font_var'
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-lang-btn" data-key="${data.key}" data-label="${escapeHtml(data.label)}" data-code="${data.lang_code}" data-dir="${data.direction}" data-font="${escapeHtml(data.font_var)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-lang-btn" data-key="${data.key}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load languages: ' + response.message, 'error');
                        }
                    });
                }
                $('#addLanguageForm').on('submit', function(e) {
                    e.preventDefault();
                    const langKey = $('#newLangKey').val();
                    const label = $('#newLangLabel').val();
                    const langCode = $('#newLangCode').val();
                    const direction = $('#newLangDirection').val();
                    const fontVar = $('#newLangFontVar').val();
                    sendAjaxRequest('add_language', {
                        lang_key: langKey,
                        label: label,
                        lang_code: langCode,
                        direction: direction,
                        font_var: fontVar
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Language added successfully. Database tables updated.');
                            $('#addLanguageForm')[0].reset();
                            loadLanguages();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast('Failed to add language: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.edit-lang-btn', function() {
                    const key = $(this).data('key');
                    const label = unescapeHtml($(this).data('label'));
                    const code = $(this).data('code');
                    const dir = $(this).data('dir');
                    const font = unescapeHtml($(this).data('font'));
                    $('#editLangOriginalKey').val(key);
                    $('#editLangKey').val(key);
                    $('#editLangLabel').val(label);
                    $('#editLangCode').val(code);
                    $('#editLangDirection').val(dir);
                    $('#editLangFontVar').val(font);
                    const editLanguageModal = new bootstrap.Modal(document.getElementById('editLanguageModal'));
                    editLanguageModal.show();
                });
                $('#saveLanguageBtn').on('click', function() {
                    const originalKey = $('#editLangOriginalKey').val();
                    const label = $('#editLangLabel').val();
                    const code = $('#editLangCode').val();
                    const direction = $('#editLangDirection').val();
                    const fontVar = $('#editLangFontVar').val();
                    sendAjaxRequest('update_language', {
                        original_key: originalKey,
                        label: label,
                        lang_code: code,
                        direction: direction,
                        font_var: fontVar
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Language updated successfully.');
                            loadLanguages();
                            bootstrap.Modal.getInstance(document.getElementById('editLanguageModal')).hide();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showToast('Failed to update language: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.delete-lang-btn', function() {
                    const key = $(this).data('key');
                    if (confirm('Are you sure you want to delete this language? This will remove all associated translations and columns in the Quran tables. This action cannot be undone.')) {
                        sendAjaxRequest('delete_language', {
                            lang_key: key
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Language deleted successfully. Associated columns removed.');
                                loadLanguages();
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showToast('Failed to delete language: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function loadAdminTafsir() {
                    if (adminTafsirTable) {
                        adminTafsirTable.destroy();
                    }
                    sendAjaxRequest('get_all_tafsir_admin').done(async function(response) {
                        if (response.success) {
                            const dataWithArabic = await Promise.all(response.data.map(async (item) => {
                                const ayahData = await sendAjaxRequest('load_quran_ayah', {
                                    surah: item.surah,
                                    ayah: item.ayah
                                });
                                item.arabic = ayahData.success ? ayahData.data.arabic : 'N/A';
                                return item;
                            }));
                            adminTafsirTable = $('#adminTafsirTable').DataTable({
                                data: dataWithArabic,
                                columns: [{
                                    data: 'id'
                                }, {
                                    data: 'username'
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `${data.surah}:${data.ayah}`;
                                    }
                                }, {
                                    data: 'notes',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: 'is_public',
                                    render: function(data, type, row) {
                                        const checked = data == 1 ? 'checked' : '';
                                        return `<div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input toggle-public-btn" type="checkbox" role="switch" ${checked} data-id="${row.id}" data-type="tafsir">
                                        </div>`;
                                    }
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-tafsir-btn" data-surah="${data.surah}" data-ayah="${data.ayah}" data-notes="${escapeHtml(data.notes)}" data-arabic="${escapeHtml(data.arabic)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-tafsir-btn" data-id="${data.id}" data-surah="${data.surah}" data-ayah="${data.ayah}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load admin Tafsir notes: ' + response.message, 'error');
                        }
                    });
                }

                function loadAdminThemes() {
                    if (adminThemesTable) {
                        adminThemesTable.destroy();
                    }
                    sendAjaxRequest('get_all_themes_admin').done(function(response) {
                        if (response.success) {
                            adminThemesTable = $('#adminThemesTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'id'
                                }, {
                                    data: 'username'
                                }, {
                                    data: 'name'
                                }, {
                                    data: 'description',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: 'is_public',
                                    render: function(data, type, row) {
                                        const checked = data == 1 ? 'checked' : '';
                                        return `<div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input toggle-public-btn" type="checkbox" role="switch" ${checked} data-id="${row.id}" data-type="theme">
                                        </div>`;
                                    }
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-theme-btn" data-id="${data.id}" data-name="${escapeHtml(data.name)}" data-desc="${escapeHtml(data.description)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-theme-btn" data-id="${data.id}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load admin themes: ' + response.message, 'error');
                        }
                    });
                }

                function loadAdminRoots() {
                    if (adminRootsTable) {
                        adminRootsTable.destroy();
                    }
                    sendAjaxRequest('get_all_roots_admin').done(function(response) {
                        if (response.success) {
                            adminRootsTable = $('#adminRootsTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'id'
                                }, {
                                    data: 'username'
                                }, {
                                    data: 'root',
                                    className: 'ayah-arabic-text'
                                }, {
                                    data: 'description',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: 'is_public',
                                    render: function(data, type, row) {
                                        const checked = data == 1 ? 'checked' : '';
                                        return `<div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input toggle-public-btn" type="checkbox" role="switch" ${checked} data-id="${row.id}" data-type="root">
                                        </div>`;
                                    }
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-root-btn" data-root="${escapeHtml(data.root)}" data-desc="${escapeHtml(data.description)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-root-btn" data-root="${escapeHtml(data.root)}" data-id="${data.id}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load admin root notes: ' + response.message, 'error');
                        }
                    });
                }

                $(document).on('change', '.toggle-public-btn', function() {
                    const itemId = $(this).data('id');
                    const itemType = $(this).data('type');
                    const isPublic = $(this).is(':checked') ? 1 : 0;
                    const $this = $(this); 
                    sendAjaxRequest('toggle_public_status', {
                        item_id: itemId,
                        item_type: itemType,
                        is_public: isPublic
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Public status updated successfully.');
                        } else {
                            showToast('Failed to update public status: ' + response.message, 'error');
                            $this.prop('checked', !isPublic); 
                        }
                    }).fail(function() {
                        showToast('Error communicating with server.', 'error');
                        $this.prop('checked', !isPublic); 
                    });
                });

            } else { 
                function loadMyTafsir() {
                    if (myTafsirTable) {
                        myTafsirTable.destroy();
                    }
                    sendAjaxRequest('get_all_tafsir').done(async function(response) {
                        if (response.success) {
                            const dataWithArabic = await Promise.all(response.data.map(async (item) => {
                                const ayahData = await sendAjaxRequest('load_quran_ayah', {
                                    surah: item.surah,
                                    ayah: item.ayah
                                });
                                item.arabic = ayahData.success ? ayahData.data.arabic : 'N/A';
                                return item;
                            }));
                            myTafsirTable = $('#myTafsirTable').DataTable({
                                data: dataWithArabic,
                                columns: [{
                                    data: null,
                                    render: function(data) {
                                        return `${data.surah}:${data.ayah}`;
                                    }
                                }, {
                                    data: 'arabic',
                                    className: 'ayah-arabic-text'
                                }, {
                                    data: 'notes',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-tafsir-btn" data-surah="${data.surah}" data-ayah="${data.ayah}" data-notes="${escapeHtml(data.notes)}" data-arabic="${escapeHtml(data.arabic)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-tafsir-btn" data-surah="${data.surah}" data-ayah="${data.ayah}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load Tafsir notes: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.edit-tafsir-btn', function() {
                    const surah = $(this).data('surah');
                    const ayah = $(this).data('ayah');
                    const notes = unescapeHtml($(this).data('notes'));
                    const arabic = unescapeHtml($(this).data('arabic'));
                    $('#editTafsirSurah').val(surah);
                    $('#editTafsirAyah').val(ayah);
                    $('#tafsirModalAyahRef').text(`${surah}:${ayah}`);
                    $('#editTafsirArabicText').text(arabic);
                    $('#editTafsirNotes').val(notes);
                    const editTafsirModal = new bootstrap.Modal(document.getElementById('editTafsirModal'));
                    editTafsirModal.show();
                });
                $('#saveTafsirNotesBtn').on('click', function() {
                    const surah = $('#editTafsirSurah').val();
                    const ayah = $('#editTafsirAyah').val();
                    const notes = $('#editTafsirNotes').val();
                    sendAjaxRequest('save_tafsir', {
                        surah: surah,
                        ayah: ayah,
                        notes: notes
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Tafsir notes updated successfully.');
                            loadMyTafsir();
                            bootstrap.Modal.getInstance(document.getElementById('editTafsirModal')).hide();
                        } else {
                            showToast('Failed to update Tafsir notes: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.delete-tafsir-btn', function() {
                    const surah = $(this).data('surah');
                    const ayah = $(this).data('ayah');
                    if (confirm('Are you sure you want to delete this Tafsir note?')) {
                        sendAjaxRequest('delete_tafsir', {
                            surah: surah,
                            ayah: ayah
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Tafsir note deleted successfully.');
                                loadMyTafsir();
                            } else {
                                showToast('Failed to delete Tafsir note: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function loadMyThemes() {
                    if (myThemesTable) {
                        myThemesTable.destroy();
                    }
                    sendAjaxRequest('get_all_themes').done(function(response) {
                        if (response.success) {
                            myThemesTable = $('#myThemesTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'name'
                                }, {
                                    data: 'description',
                                    render: $.fn.dataTable.render.ellipsis(50)
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-theme-btn" data-id="${data.id}" data-name="${escapeHtml(data.name)}" data-desc="${escapeHtml(data.description)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-theme-btn" data-id="${data.id}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load themes: ' + response.message, 'error');
                        }
                    });
                }
                $('#addThemeForm').on('submit', function(e) {
                    e.preventDefault();
                    const name = $('#newThemeName').val();
                    const description = $('#newThemeDescription').val();
                    sendAjaxRequest('add_theme', {
                        name: name,
                        description: description
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Theme added successfully.');
                            $('#addThemeForm')[0].reset();
                            loadMyThemes();
                            populateThemeLinksSelect();
                        } else {
                            showToast('Failed to add theme: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.edit-theme-btn', function() {
                    const id = $(this).data('id');
                    const name = unescapeHtml($(this).data('name'));
                    const desc = unescapeHtml($(this).data('desc'));
                    $('#editThemeId').val(id);
                    $('#editThemeName').val(name);
                    $('#editThemeDescription').val(desc);
                    const editThemeModal = new bootstrap.Modal(document.getElementById('editThemeModal'));
                    editThemeModal.show();
                });
                $('#saveThemeBtn').on('click', function() {
                    const id = $('#editThemeId').val();
                    const name = $('#editThemeName').val();
                    const description = $('#editThemeDescription').val();
                    sendAjaxRequest('update_theme', {
                        theme_id: id,
                        name: name,
                        description: description
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Theme updated successfully.');
                            loadMyThemes();
                            populateThemeLinksSelect();
                            bootstrap.Modal.getInstance(document.getElementById('editThemeModal')).hide();
                        } else {
                            showToast('Failed to update theme: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.delete-theme-btn', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to delete this theme and all its linked ayahs?')) {
                        sendAjaxRequest('delete_theme', {
                            theme_id: id
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Theme deleted successfully.');
                                loadMyThemes();
                                populateThemeLinksSelect();
                                loadMyThemeLinks(); 
                            } else {
                                showToast('Failed to delete theme: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function populateThemeLinksSelect() {
                    const select = $('#selectThemeToViewLinks');
                    select.empty().append('<option value="">-- Select a Theme --</option>');
                    sendAjaxRequest('get_all_themes').done(function(response) {
                        if (response.success) {
                            response.data.forEach(theme => {
                                select.append(`<option value="${theme.id}">${theme.name}</option>`);
                            });
                        }
                    });
                }
                $('#selectThemeToViewLinks').on('change', function() {
                    const themeId = $(this).val();
                    const themeName = $(this).find('option:selected').text();
                    $('#currentThemeLinkedAyahs').text(themeName !== '-- Select a Theme --' ? themeName : '');
                    loadMyThemeLinks(themeId);
                });

                function loadMyThemeLinks(themeId = null) {
                    if (myThemeLinksTable) {
                        myThemeLinksTable.destroy();
                    }
                    if (!themeId) {
                        $('#myThemeLinksTable tbody').empty().append('<tr><td colspan="4" class="text-center">Select a theme to view linked Ayahs.</td></tr>');
                        return;
                    }
                    sendAjaxRequest('get_linked_ayahs_for_theme', {
                        theme_id: themeId
                    }).done(async function(response) {
                        if (response.success) {
                            const dataWithArabic = await Promise.all(response.data.map(async (item) => {
                                const ayahData = await sendAjaxRequest('load_quran_ayah', {
                                    surah: item.surah,
                                    ayah: item.ayah
                                });
                                item.arabic = ayahData.success ? ayahData.data.arabic : 'N/A';
                                return item;
                            }));
                            myThemeLinksTable = $('#myThemeLinksTable').DataTable({
                                data: dataWithArabic,
                                columns: [{
                                    data: null,
                                    render: function(data) {
                                        return `${data.surah}:${data.ayah}`;
                                    }
                                }, {
                                    data: 'arabic',
                                    className: 'ayah-arabic-text'
                                }, {
                                    data: 'notes',
                                    render: $.fn.dataTable.render.ellipsis(50)
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-danger unlink-ayah-btn" data-id="${data.id}">Unlink</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load theme links: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.unlink-ayah-btn', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to unlink this Ayah from the theme?')) {
                        sendAjaxRequest('unlink_ayah_from_theme', {
                            link_id: id
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Ayah unlinked successfully.');
                                loadMyThemeLinks($('#selectThemeToViewLinks').val());
                            } else {
                                showToast('Failed to unlink Ayah: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function loadMyRoots() {
                    if (myRootsTable) {
                        myRootsTable.destroy();
                    }
                    sendAjaxRequest('get_all_roots').done(function(response) {
                        if (response.success) {
                            myRootsTable = $('#myRootsTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'root',
                                    className: 'ayah-arabic-text'
                                }, {
                                    data: 'description',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-root-btn" data-root="${escapeHtml(data.root)}" data-desc="${escapeHtml(data.description)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-root-btn" data-root="${escapeHtml(data.root)}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load root notes: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.edit-root-btn', function() {
                    const root = unescapeHtml($(this).data('root'));
                    const desc = unescapeHtml($(this).data('desc'));
                    $('#rootModalRootWord').text(root);
                    $('#editRootWordInput').val(root);
                    $('#editRootDescription').val(desc);
                    const editRootModal = new bootstrap.Modal(document.getElementById('editRootModal'));
                    editRootModal.show();
                });
                $('#saveRootNotesBtn').on('click', function() {
                    const root = $('#editRootWordInput').val();
                    const description = $('#editRootDescription').val();
                    sendAjaxRequest('save_root_notes', {
                        root: root,
                        description: description
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Root notes updated successfully.');
                            loadMyRoots();
                            bootstrap.Modal.getInstance(document.getElementById('editRootModal')).hide();
                        } else {
                            showToast('Failed to update root notes: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.delete-root-btn', function() {
                    const root = unescapeHtml($(this).data('root'));
                    if (confirm('Are you sure you want to delete notes for this root word?')) {
                        sendAjaxRequest('delete_root_notes', {
                            root: root
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Root notes deleted successfully.');
                                loadMyRoots();
                            } else {
                                showToast('Failed to delete root notes: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function loadMyRecitations() {
                    if (myRecitationsTable) {
                        myRecitationsTable.destroy();
                    }
                    sendAjaxRequest('get_all_recitations').done(function(response) {
                        if (response.success) {
                            myRecitationsTable = $('#myRecitationsTable').DataTable({
                                data: response.data,
                                columns: [{
                                    data: 'log_date'
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `${data.surah}. ${surahNames[data.surah - 1]}`;
                                    }
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return data.ayah_start && data.ayah_end ? `${data.ayah_start}-${data.ayah_end}` : 'Full Surah';
                                    }
                                }, {
                                    data: 'qari'
                                }, {
                                    data: 'notes',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-recitation-btn" data-id="${data.id}" data-surah="${data.surah}" data-start="${data.ayah_start}" data-end="${data.ayah_end}" data-qari="${escapeHtml(data.qari)}" data-date="${data.log_date}" data-notes="${escapeHtml(data.notes)}">Edit</button>
                                            <button class="btn btn-sm btn-danger delete-recitation-btn" data-id="${data.id}">Delete</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'desc']
                                ]
                            });
                        } else {
                            showToast('Failed to load recitation logs: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.edit-recitation-btn', function() {
                    const id = $(this).data('id');
                    const surah = $(this).data('surah');
                    const start = $(this).data('start');
                    const end = $(this).data('end');
                    const qari = unescapeHtml($(this).data('qari'));
                    const date = $(this).data('date');
                    const notes = unescapeHtml($(this).data('notes'));
                    $('#editRecitationId').val(id);
                    $('#editRecitationSurah').val(surah);
                    $('#editRecitationAyahStart').val(start);
                    $('#editRecitationAyahEnd').val(end);
                    $('#editRecitationQari').val(qari);
                    $('#editRecitationDate').val(date);
                    $('#editRecitationNotes').val(notes);
                    populateSurahAyahSelectors('editRecitationSurah', 'editRecitationAyahStart');
                    $('#editRecitationSurah').val(surah);
                    $('#editRecitationSurah').trigger('change');
                    $('#editRecitationAyahStart').val(start);
                    const totalAyahs = surahAyahCounts[surah] || 0;
                    $('#editRecitationAyahEnd').empty().append('<option value="">Full Surah</option>');
                    for (let i = (start || 1); i <= totalAyahs; i++) {
                        $('#editRecitationAyahEnd').append(`<option value="${i}">${i}</option>`);
                    }
                    $('#editRecitationAyahEnd').val(end);
                    const editRecitationModal = new bootstrap.Modal(document.getElementById('editRecitationModal'));
                    editRecitationModal.show();
                });
                $('#saveRecitationLogBtn').on('click', function() {
                    const id = $('#editRecitationId').val() || null;
                    const surah = $('#editRecitationSurah').val();
                    const ayah_start = $('#editRecitationAyahStart').val() || null;
                    const ayah_end = $('#editRecitationAyahEnd').val() || null;
                    const qari = $('#editRecitationQari').val();
                    const log_date = $('#editRecitationDate').val();
                    const notes = $('#editRecitationNotes').val();
                    sendAjaxRequest('update_recitation_log', {
                        id: id,
                        surah: surah,
                        ayah_start: ayah_start,
                        ayah_end: ayah_end,
                        qari: qari,
                        log_date: log_date,
                        notes: notes
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Recitation log updated successfully.');
                            loadMyRecitations();
                            bootstrap.Modal.getInstance(document.getElementById('editRecitationModal')).hide();
                        } else {
                            showToast('Failed to update recitation log: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.delete-recitation-btn', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to delete this recitation log entry?')) {
                        sendAjaxRequest('delete_recitation_log', {
                            log_id: id
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Recitation log deleted successfully.');
                                loadMyRecitations();
                            } else {
                                showToast('Failed to delete recitation log: ' + response.message, 'error');
                            }
                        });
                    }
                });

                async function loadMyHifzOverview() {
                    try {
                        const hifzResult = await sendAjaxRequest('get_all_hifz');
                        if (!hifzResult.success) throw new Error(hifzResult.message);
                        const allHifz = hifzResult.data;
                        const surahProgress = {};
                        for (let i = 1; i <= 114; i++) {
                            surahProgress[i] = {
                                totalAyahs: surahAyahCounts[i],
                                memorized: 0,
                                inProgress: 0
                            };
                        }
                        allHifz.forEach(h => {
                            if (surahProgress[h.surah]) {
                                if (h.status === 'memorized') surahProgress[h.surah].memorized++;
                                if (h.status === 'in-progress') surahProgress[h.surah].inProgress++;
                            }
                        });

                        let overviewHtml = '';
                        const sortedSurahs = Object.keys(surahProgress).filter(s => surahProgress[s].memorized > 0 || surahProgress[s].inProgress > 0).sort((a, b) => parseInt(a) - parseInt(b));
                        if (sortedSurahs.length === 0) {
                            overviewHtml = '<p class="text-center">No Hifz progress recorded yet.</p>';
                        } else {
                            for (const surahNum of sortedSurahs) {
                                const progress = surahProgress[surahNum];
                                const total = progress.totalAyahs;
                                const memorized = progress.memorized;
                                const inProgress = progress.inProgress;
                                const percent = total > 0 ? ((memorized / total) * 100).toFixed(0) : 0;

                                overviewHtml += `
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h5 class="card-title">Surah ${surahNum}. ${surahNames[surahNum - 1]}</h5>
                                            <p class="card-text mb-1">Memorized: ${memorized} / ${total} Ayahs</p>
                                            <p class="card-text mb-1">In Progress: ${inProgress} Ayahs</p>
                                            <div class="progress-bar-bg">
                                                <div class="progress-bar" style="width: ${percent}%;">
                                                    ${percent}%
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            }
                        }
                        $('#hifz-overview-cards').html(`<div class="row">${overviewHtml}</div>`);
                    } catch (error) {
                        showToast('Error loading Hifz overview: ' + error.message, 'error');
                        console.error('Error loading Hifz overview:', error);
                        $('#hifz-overview-cards').html('<p class="text-center text-danger">Failed to load Hifz overview.</p>');
                    }
                }

                function loadMyHifzDetails() {
                    if (myHifzTable) {
                        myHifzTable.destroy();
                    }
                    sendAjaxRequest('get_all_hifz').done(function(response) {
                        if (response.success) {
                            const filteredData = response.data.filter(h => h.status !== 'not-started');
                            myHifzTable = $('#myHifzTable').DataTable({
                                data: filteredData,
                                columns: [{
                                    data: null,
                                    render: function(data) {
                                        return `${data.surah}:${data.ayah}`;
                                    }
                                }, {
                                    data: 'status',
                                    render: function(data) {
                                        return `<span class="hifz-ayah-status status-${data}">${data.replace('-', ' ')}</span>`;
                                    }
                                }, {
                                    data: 'last_review_date'
                                }, {
                                    data: 'next_review_date'
                                }, {
                                    data: 'review_count'
                                }, {
                                    data: 'notes',
                                    render: $.fn.dataTable.render.ellipsis(50)
                                }, {
                                    data: null,
                                    render: function(data) {
                                        return `
                                            <button class="btn btn-sm btn-info edit-hifz-btn" data-surah="${data.surah}" data-ayah="${data.ayah}" data-status="${data.status}" data-last-review="${data.last_review_date}" data-next-review="${data.next_review_date}" data-review-count="${data.review_count}" data-notes="${escapeHtml(data.notes)}">Edit</button>
                                            <button class="btn btn-sm btn-success record-review-btn" data-surah="${data.surah}" data-ayah="${data.ayah}" ${data.status !== 'memorized' ? 'disabled' : ''}>Record Review</button>
                                        `;
                                    }
                                }],
                                order: [
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load Hifz details: ' + response.message, 'error');
                        }
                    });
                }
                $(document).on('click', '.edit-hifz-btn', function() {
                    const surah = $(this).data('surah');
                    const ayah = $(this).data('ayah');
                    const status = $(this).data('status');
                    const lastReview = $(this).data('last-review');
                    const nextReview = $(this).data('next-review');
                    const reviewCount = $(this).data('review-count');
                    const notes = unescapeHtml($(this).data('notes'));
                    $('#editHifzSurah').val(surah);
                    $('#editHifzAyah').val(ayah);
                    $('#hifzModalAyahRef').text(`${surah}:${ayah}`);
                    $('#editHifzStatus').val(status);
                    $('#editHifzLastReview').val(lastReview);
                    $('#editHifzNextReview').val(nextReview);
                    $('#editHifzReviewCount').val(reviewCount);
                    $('#editHifzNotes').val(notes);
                    const editHifzModal = new bootstrap.Modal(document.getElementById('editHifzModal'));
                    editHifzModal.show();
                });
                $('#saveHifzStatusBtn').on('click', function() {
                    const surah = $('#editHifzSurah').val();
                    const ayah = $('#editHifzAyah').val();
                    const status = $('#editHifzStatus').val();
                    const lastReview = $('#editHifzLastReview').val();
                    const nextReview = $('#editHifzNextReview').val();
                    const reviewCount = $('#editHifzReviewCount').val();
                    const notes = $('#editHifzNotes').val();
                    sendAjaxRequest('update_hifz_status', {
                        surah: surah,
                        ayah: ayah,
                        status: status,
                        last_review_date: lastReview,
                        next_review_date: nextReview,
                        review_count: reviewCount,
                        notes: notes
                    }).done(function(response) {
                        if (response.success) {
                            showToast('Hifz status updated successfully.');
                            loadMyHifzOverview();
                            loadMyHifzDetails();
                            bootstrap.Modal.getInstance(document.getElementById('editHifzModal')).hide();
                        } else {
                            showToast('Failed to update Hifz status: ' + response.message, 'error');
                        }
                    });
                });
                $(document).on('click', '.record-review-btn', function() {
                    const surah = $(this).data('surah');
                    const ayah = $(this).data('ayah');
                    if (confirm(`Record a new review for Surah ${surah}:${ayah}?`)) {
                        sendAjaxRequest('get_hifz_for_ayah', {
                            surah: surah,
                            ayah: ayah
                        }).done(function(response) {
                            if (response.success && response.data) {
                                const existing = response.data;
                                existing.last_review_date = new Date().toISOString().split('T')[0];
                                existing.review_count = (existing.review_count || 0) + 1;
                                existing.next_review_date = calculateNextReview(existing.last_review_date, existing.review_count);
                                sendAjaxRequest('update_hifz_status', {
                                    surah: surah,
                                    ayah: ayah,
                                    status: existing.status,
                                    last_review_date: existing.last_review_date,
                                    next_review_date: existing.next_review_date,
                                    review_count: existing.review_count,
                                    notes: existing.notes
                                }).done(function(updateResponse) {
                                    if (updateResponse.success) {
                                        showToast('Review recorded successfully.');
                                        loadMyHifzOverview();
                                        loadMyHifzDetails();
                                    } else {
                                        showToast('Failed to record review: ' + updateResponse.message, 'error');
                                    }
                                });
                            } else {
                                showToast('Hifz data not found for this Ayah.', 'error');
                            }
                        });
                    }
                });

                function calculateNextReview(lastReviewDate, reviewCount) {
                    const date = new Date(lastReviewDate);
                    let daysToAdd = [1, 3, 7, 15, 30, 60, 90][Math.min(reviewCount, 6)] || 120;
                    date.setDate(date.getDate() + daysToAdd);
                    return date.toISOString().split('T')[0];
                }

                function setupGoalsFormListener() {
                    $('#goal-form').off('submit').on('submit', async function(e) {
                        e.preventDefault();
                        const newGoal = {
                            title: $('#goal-title').val(),
                            type: $('#goal-type').val(),
                            targetDate: $('#goal-date').val(),
                            creationDate: new Date().toISOString().split('T')[0],
                            isComplete: false
                        };
                        const type = newGoal.type;
                        if (type === 'read_surah' || type === 'listen_surah' || type === 'memorize_surah' || type === 'recurring_surah_daily') {
                            newGoal.targetSurah = parseInt($('#goal-target-surah').val());
                        } else if (type === 'recurring_surah_weekly') {
                            newGoal.targetSurah = parseInt($('#goal-target-surah').val());
                            newGoal.targetDay = parseInt($('#goal-target-day').val());
                        } else if (type === 'read_ayahs_daily' || type === 'listen_ayahs_daily') {
                            newGoal.targetCount = parseInt($('#goal-count').val());
                        } else if (type === 'tafsir_juz') {
                            newGoal.targetJuz = parseInt($('#goal-target-juz').val());
                        } else if (type === 'link_theme') {
                            newGoal.targetTheme = parseInt($('#goal-target-theme').val());
                            newGoal.targetCount = parseInt($('#goal-count').val());
                        }
                        const result = await sendAjaxRequest('add_goal', newGoal);
                        if (result.success) {
                            showToast('Goal added successfully.');
                            $('#goal-form')[0].reset();
                            $('#goal-target-wrapper').empty();
                            $('#goal-count-wrapper').empty();
                            await renderGoalsUI();
                        } else {
                            showToast('Failed to add goal: ' + result.message, 'error');
                        }
                    });

                    $('#goal-type').off('change').on('change', async function(e) {
                        const targetWrapper = $('#goal-target-wrapper');
                        const countWrapper = $('#goal-count-wrapper');
                        const type = e.target.value;
                        targetWrapper.empty();
                        countWrapper.empty();
                        let surahOptions = surahNames.map((name, i) => `<option value="${i + 1}">${i + 1}. ${name}</option>`).join('');

                        switch (type) {
                            case 'read_surah':
                            case 'listen_surah':
                            case 'memorize_surah':
                            case 'recurring_surah_daily':
                                targetWrapper.html(`<label for="goal-target-surah" class="form-label">Select Surah:</label><select id="goal-target-surah" class="form-select" required>${surahOptions}</select>`);
                                break;
                            case 'recurring_surah_weekly':
                                const dayOptions = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']
                                    .map((day, i) => `<option value="${i}">${day}</option>`).join('');
                                targetWrapper.html(`<label for="goal-target-surah" class="form-label">Select Surah:</label><select id="goal-target-surah" class="form-select" required>${surahOptions}</select>`);
                                countWrapper.html(`<label for="goal-target-day" class="form-label">On which day?</label><select id="goal-target-day" class="form-select" required>${dayOptions}</select>`);
                                break;
                            case 'read_ayahs_daily':
                            case 'listen_ayahs_daily':
                                countWrapper.html(`<label for="goal-count" class="form-label">How many Ayahs per day?</label><input type="number" id="goal-count" class="form-control" min="1" value="10" required>`);
                                break;
                            case 'read_quran':
                            case 'listen_quran':
                                break;
                            case 'tafsir_juz':
                                const juzOptions = juzBoundariesData.map(j => `<option value="${j.juz}">Juz ${j.juz}</option>`).join('');
                                targetWrapper.html(`<label for="goal-target-juz" class="form-label">Select Juz:</label><select id="goal-target-juz" class="form-select" required>${juzOptions}</select>`);
                                break;
                            case 'link_theme':
                                const themesResult = await sendAjaxRequest('get_all_themes');
                                const themes = themesResult.success ? themesResult.data : [];
                                const themeOptions = themes.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                                targetWrapper.html(`<label for="goal-target-theme" class="form-label">Select Theme:</label><select id="goal-target-theme" class="form-select" required>${themeOptions || '<option disabled>No themes created</option>'}</select>`);
                                countWrapper.html(`<label for="goal-count" class="form-label">Link how many Ayahs?</label><input type="number" id="goal-count" class="form-control" min="1" value="10" required>`);
                                break;
                        }
                    });
                }
                setupGoalsFormListener();

                async function renderGoalsUI() {
                    const allGoalsResult = await sendAjaxRequest('get_all_goals');
                    const allGoals = allGoalsResult.success ? allGoalsResult.data : [];

                    const userData = {
                        hifz: (await sendAjaxRequest('get_all_hifz')).data || [],
                        tafsir: (await sendAjaxRequest('get_all_tafsir')).data || [],
                        recitations: (await sendAjaxRequest('get_all_recitations')).data || [],
                        themeAyahs: (await sendAjaxRequest('get_all_theme_ayahs')).data || []
                    };

                    const activeGoals = [];
                    const completedGoals = [];

                    for (const goal of allGoals) {
                        const {
                            progress,
                            progressText
                        } = await calculateGoalProgress(goal, userData);
                        const isNowComplete = progress >= 100 && (goal.type !== 'read_ayahs_daily' && goal.type !== 'listen_ayahs_daily' && goal.type !== 'recurring_surah_daily' && goal.type !== 'recurring_surah_weekly');

                        if (isNowComplete && !goal.is_complete) {
                            goal.is_complete = true;
                            await sendAjaxRequest('update_goal_completion', {
                                id: goal.id,
                                isComplete: true
                            });
                        }

                        goal.progress = progress;
                        goal.progressText = progressText;

                        if (goal.is_complete) {
                            completedGoals.push(goal);
                        } else {
                            activeGoals.push(goal);
                        }
                    }

                    if (myActiveGoalsTable) {
                        myActiveGoalsTable.destroy();
                    }
                    myActiveGoalsTable = $('#myActiveGoalsTable').DataTable({
                        data: activeGoals,
                        columns: [{
                            data: 'title'
                        }, {
                            data: 'type',
                            render: function(data) {
                                return data.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            }
                        }, {
                            data: null,
                            render: function(data) {
                                return getGoalTargetDescription(data);
                            }
                        }, {
                            data: null,
                            render: function(data) {
                                return `<div class="progress-bar-bg" style="width: 100%;"><div class="progress-bar" style="width: ${data.progress.toFixed(0)}%;">${data.progress.toFixed(0)}%</div></div><small>${data.progressText}</small>`;
                            }
                        }, {
                            data: 'target_date'
                        }, {
                            data: null,
                            render: function(data) {
                                return `
                                    <button class="btn btn-sm btn-info edit-goal-btn" data-id="${data.id}" data-title="${escapeHtml(data.title)}" data-target-date="${data.target_date}" data-is-complete="${data.is_complete}">Edit</button>
                                    <button class="btn btn-sm btn-danger delete-goal-btn" data-id="${data.id}">Delete</button>
                                `;
                            }
                        }],
                        order: [
                            [4, 'asc']
                        ]
                    });

                    if (myCompletedGoalsTable) {
                        myCompletedGoalsTable.destroy();
                    }
                    myCompletedGoalsTable = $('#myCompletedGoalsTable').DataTable({
                        data: completedGoals,
                        columns: [{
                            data: 'title'
                        }, {
                            data: 'type',
                            render: function(data) {
                                return data.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                            }
                        }, {
                            data: null,
                            render: function(data) {
                                return getGoalTargetDescription(data);
                            }
                        }, {
                            data: null,
                            render: function(data) {
                                return `<div class="progress-bar-bg" style="width: 100%;"><div class="progress-bar" style="width: ${data.progress.toFixed(0)}%; background-color: var(--color-success);">${data.progress.toFixed(0)}%</div></div><small>${data.progressText}</small>`;
                            }
                        }, {
                            data: 'target_date'
                        }, {
                            data: null,
                            render: function(data) {
                                return `<button class="btn btn-sm btn-danger delete-goal-btn" data-id="${data.id}">Delete</button>`;
                            }
                        }],
                        order: [
                            [4, 'desc']
                        ]
                    });
                }

                function getGoalTargetDescription(goal) {
                    const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    if (goal.target_surah) return `Surah ${goal.target_surah}: ${surahNames[goal.target_surah - 1]} ${goal.target_day !== null && goal.target_day !== undefined ? `(Every ${days[goal.target_day]})` : ''}`;
                    if (goal.target_juz) return `Juz ${goal.target_juz}`;
                    if (goal.target_theme_id) return `Theme ID: ${goal.target_theme_id}`;
                    if (goal.target_count) return `${goal.target_count} Ayahs/Day`;
                    return 'N/A';
                }

                async function calculateGoalProgress(goal, userData) {
                    let progress = 0;
                    let progressText = '0/0';
                    const allReadingLogs = userData.recitations;
                    switch (goal.type) {
                        case 'read_surah':
                        case 'listen_surah': {
                            const total = surahAyahCounts[goal.target_surah];
                            const ayahsReadInSurah = new Set();
                            allReadingLogs.filter(r => r.surah === goal.target_surah).forEach(r => {
                                const start = r.ayah_start || 1;
                                const end = r.ayah_end || (r.ayah_start ? r.ayah_start : surahAyahCounts[r.surah]);
                                for (let i = start; i <= end; i++) {
                                    ayahsReadInSurah.add(i);
                                }
                            });
                            const completed = ayahsReadInSurah.size;
                            progress = total > 0 ? (completed / total) * 100 : 100;
                            progressText = `${completed}/${total} Ayahs`;
                            break;
                        }
                        case 'read_quran':
                        case 'listen_quran': {
                            const total = 6236; 
                            const ayahsRead = new Set();
                            allReadingLogs.forEach(r => {
                                const start = r.ayah_start || 1;
                                const end = r.ayah_end || (r.ayah_start ? r.ayah_start : surahAyahCounts[r.surah]);
                                for (let i = start; i <= end; i++) {
                                    ayahsRead.add(`${r.surah}:${i}`);
                                }
                            });
                            const completed = ayahsRead.size;
                            progress = (completed / total) * 100;
                            progressText = `${completed}/${total} Ayahs`;
                            break;
                        }
                        case 'read_ayahs_daily':
                        case 'listen_ayahs_daily': {
                            const totalDays = 7;
                            const dailyCounts = getDailyReadingCounts(allReadingLogs);
                            let daysMet = 0;
                            for (let i = 0; i < totalDays; i++) {
                                const d = new Date();
                                d.setDate(d.getDate() - i);
                                const dayKey = d.toISOString().split('T')[0];
                                if ((dailyCounts[dayKey] || 0) >= goal.target_count) {
                                    daysMet++;
                                }
                            }
                            progress = (daysMet / totalDays) * 100;
                            progressText = `${daysMet}/${totalDays} Days Met`;
                            break;
                        }
                        case 'recurring_surah_weekly': {
                            const totalWeeks = 4;
                            let weeksMet = 0;
                            const targetDay = goal.target_day; 
                            const recitationLogsForSurah = allReadingLogs.filter(r => r.surah === goal.target_surah);

                            for (let i = 0; i < totalWeeks; i++) {
                                const checkDate = new Date();
                                checkDate.setDate(checkDate.getDate() - (i * 7)); 

                                const dayOffset = (checkDate.getDay() - targetDay + 7) % 7;
                                checkDate.setDate(checkDate.getDate() - dayOffset);
                                const dayKey = checkDate.toISOString().split('T')[0];

                                if (recitationLogsForSurah.some(log => log.log_date === dayKey)) {
                                    weeksMet++;
                                }
                            }
                            progress = (weeksMet / totalWeeks) * 100;
                            progressText = `${weeksMet}/${totalWeeks} Weeks Met`;
                            break;
                        }
                        case 'recurring_surah_daily': {
                            const totalDays = 7;
                            let daysMet = 0;
                            const recitationLogsForSurah = allReadingLogs.filter(r => r.surah === goal.target_surah);
                            for (let i = 0; i < totalDays; i++) {
                                const checkDate = new Date();
                                checkDate.setDate(checkDate.getDate() - i);
                                const dayKey = checkDate.toISOString().split('T')[0];
                                if (recitationLogsForSurah.some(log => log.log_date === dayKey)) {
                                    daysMet++;
                                }
                            }
                            progress = (daysMet / totalDays) * 100;
                            progressText = `${daysMet}/${totalDays} Days Met`;
                            break;
                        }
                        case 'memorize_surah': {
                            const total = surahAyahCounts[goal.target_surah];
                            const completed = userData.hifz.filter(h => h.surah === goal.target_surah && h.status === 'memorized').length;
                            progress = total > 0 ? (completed / total) * 100 : 100;
                            progressText = `${completed}/${total} Ayahs`;
                            break;
                        }
                        case 'tafsir_juz': {
                            const ayahsInJuz = getAyahsForJuz(goal.target_juz);
                            const total = ayahsInJuz.length;
                            const tafsirKeys = new Set(userData.tafsir.map(t => `${t.surah}:${t.ayah}`));
                            const completed = ayahsInJuz.filter(a => tafsirKeys.has(`${a.surah}:${a.ayah}`)).length;
                            progress = total > 0 ? (completed / total) * 100 : 100;
                            progressText = `${completed}/${total} Ayahs`;
                            break;
                        }
                        case 'link_theme': {
                            const total = goal.target_count;
                            const completed = userData.themeAyahs.filter(ta => ta.theme_id === goal.target_theme_id).length;
                            progress = total > 0 ? (completed / total) * 100 : 100;
                            progressText = `${completed}/${total} Ayahs`;
                            break;
                        }
                    }
                    return {
                        progress: Math.min(progress, 100),
                        progressText
                    };
                }

                function getAyahsForJuz(juzNum) {
                    const ayahs = [];
                    const startJuzData = juzBoundariesData.find(j => j.juz === juzNum);
                    if (!startJuzData) return ayahs;

                    const nextJuzData = juzBoundariesData.find(j => j.juz === juzNum + 1);

                    for (let s = startJuzData.startSurah; s <= 114; s++) {
                        const startAyah = (s === startJuzData.startSurah) ? startJuzData.startAyah : 1;
                        let endAyah = surahAyahCounts[s];

                        if (nextJuzData && s === nextJuzData.startSurah) {
                            endAyah = nextJuzData.startAyah - 1; 
                            if (endAyah < startAyah) endAyah = startAyah; 
                        }

                        for (let a = startAyah; a <= endAyah; a++) {
                            if (a > surahAyahCounts[s]) break; 
                            ayahs.push({
                                surah: s,
                                ayah: a
                            });
                        }
                        if (nextJuzData && s >= nextJuzData.startSurah) break; 
                    }
                    return ayahs;
                }


                function getDailyReadingCounts(allReadingLogs) {
                    const dailyCounts = {};
                    allReadingLogs.forEach(r => {
                        const dayKey = r.log_date;
                        if (!dayKey) return;
                        if (!dailyCounts[dayKey]) dailyCounts[dayKey] = 0;
                        const start = r.ayah_start || 1;
                        const end = r.ayah_end || (r.ayah_start ? r.ayah_start : (surahAyahCounts[r.surah] || 0));
                        dailyCounts[dayKey] += Math.max(0, (end - start + 1));
                    });
                    return dailyCounts;
                }
                $(document).on('click', '.edit-goal-btn', function() {
                    const id = $(this).data('id');
                    const title = unescapeHtml($(this).data('title'));
                    const targetDate = $(this).data('target-date');
                    const isComplete = $(this).data('is-complete');

                    $('#editGoalId').val(id);
                    $('#editGoalTitle').val(title);
                    $('#editGoalTargetDate').val(targetDate);
                    $('#editGoalIsComplete').prop('checked', isComplete);

                    const editGoalModal = new bootstrap.Modal(document.getElementById('editGoalModal'));
                    editGoalModal.show();
                });

                $('#saveGoalBtn').on('click', async function() {
                    const id = $('#editGoalId').val();
                    const title = $('#editGoalTitle').val();
                    const targetDate = $('#editGoalTargetDate').val();
                    const isComplete = $('#editGoalIsComplete').prop('checked');

                    const result = await sendAjaxRequest('update_goal_completion', { 
                        id: id,
                        title: title, 
                        targetDate: targetDate, 
                        isComplete: isComplete
                    });

                    if (result.success) {
                        showToast('Goal updated successfully.');
                        await renderGoalsUI();
                        bootstrap.Modal.getInstance(document.getElementById('editGoalModal')).hide();
                    } else {
                        showToast('Failed to update goal: ' + result.message, 'error');
                    }
                });


                $(document).on('click', '.delete-goal-btn', function() {
                    const id = $(this).data('id');
                    if (confirm('Are you sure you want to delete this goal?')) {
                        sendAjaxRequest('delete_goal', {
                            id: id
                        }).done(function(response) {
                            if (response.success) {
                                showToast('Goal deleted successfully.');
                                renderGoalsUI();
                            } else {
                                showToast('Failed to delete goal: ' + response.message, 'error');
                            }
                        });
                    }
                });

                function loadMyContributions() {
                    if (myContributionsTable) {
                        myContributionsTable.destroy();
                    }
                    sendAjaxRequest('get_user_submitted_word_translations').done(async function(response) {
                        if (response.success) {
                            const dataWithArabic = await Promise.all(response.data.map(async (item) => {
                                const metadataResult = await sendAjaxRequest('get_word_metadata', {
                                    word_id: item.word_id
                                });
                                item.arabic_word = metadataResult.success && metadataResult.data ? metadataResult.data.arabic_word : 'N/A';
                                const langConfig = allLanguagesConfig.find(lang => lang.word_col_name === item.translation_column);
                                item.lang_label = langConfig ? langConfig.label : 'Unknown';
                                return item;
                            }));
                            myContributionsTable = $('#myContributionsTable').DataTable({
                                data: dataWithArabic,
                                columns: [{
                                    data: 'word_id'
                                }, {
                                    data: 'arabic_word',
                                    className: 'ayah-arabic-text'
                                }, {
                                    data: 'translation',
                                    render: $.fn.dataTable.render.ellipsis(100)
                                }, {
                                    data: 'lang_label'
                                }, {
                                    data: 'approved_by',
                                    render: function(data) {
                                        return data ? 'Approved' : 'Pending Approval';
                                    }
                                }],
                                order: [
                                    [4, 'asc'],
                                    [0, 'asc']
                                ]
                            });
                        } else {
                            showToast('Failed to load contributions: ' + response.message, 'error');
                        }
                    });
                }

                $('#exportUserDataBtn').on('click', function() {
                    sendAjaxRequest('export_user_data').done(function(response) {
                        if (response.success) {
                            const jsonString = JSON.stringify(response.data, null, 2);
                            const blob = new Blob([jsonString], {
                                type: 'application/json'
                            });
                            const url = URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = `nur-ul-quran-studio-backup-${new Date().toISOString().split('T')[0]}.json`;
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            URL.revokeObjectURL(url);
                            showToast('Data exported successfully.');
                        } else {
                            showToast('Failed to export data: ' + response.message, 'error');
                        }
                    });
                });
                $('#importUserDataFile').on('change', function() {
                    $('#importUserDataBtn').prop('disabled', !this.files.length);
                });
                $('#importUserDataBtn').on('click', function() {
                    const fileInput = $('#importUserDataFile')[0];
                    if (!fileInput.files.length) {
                        showToast('Please select a file to import.', 'error');
                        return;
                    }
                    if (!confirm('Importing will overwrite your existing personal data. Are you sure you want to proceed?')) {
                        return;
                    }
                    const file = fileInput.files[0];
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            const data = e.target.result;
                            sendAjaxRequest('import_user_data', {
                                data: data
                            }).done(function(response) {
                                if (response.success) {
                                    showToast('Data imported successfully. Dashboard will refresh.');
                                    loadDashboardStats();
                                    loadMyTafsir();
                                    loadMyThemes();
                                    populateThemeLinksSelect();
                                    loadMyThemeLinks();
                                    loadMyRoots();
                                    loadMyRecitations();
                                    loadMyHifzOverview();
                                    loadMyHifzDetails();
                                    renderGoalsUI();
                                    loadMyContributions();
                                    $('#importUserDataFile').val('');
                                    $('#importUserDataBtn').prop('disabled', true);
                                } else {
                                    showToast('Failed to import data: ' + response.message, 'error');
                                }
                            });
                        } catch (error) {
                            showToast('Error parsing JSON file: ' + error.message, 'error');
                            console.error('Error parsing JSON file:', error);
                        }
                    };
                    reader.readAsText(file);
                });
                $('#clearAllUserDataBtn').on('click', function() {
                    if (confirm('WARNING: This will permanently delete ALL your personal data (Tafsir, Themes, Roots, Logs, Hifz, Goals, Contributions). This action cannot be undone. Are you absolutely sure?')) {
                        sendAjaxRequest('clear_personal_data').done(function(response) {
                            if (response.success) {
                                showToast('All personal data cleared successfully. Dashboard will refresh.');
                                loadDashboardStats();
                                loadMyTafsir();
                                loadMyThemes();
                                populateThemeLinksSelect();
                                loadMyThemeLinks();
                                loadMyRoots();
                                loadMyRecitations();
                                loadMyHifzOverview();
                                loadMyHifzDetails();
                                renderGoalsUI();
                                loadMyContributions();
                            } else {
                                showToast('Failed to clear data: ' + response.message, 'error');
                            }
                        });
                    }
                });
            }

            function escapeHtml(text) {
                if (text === null || typeof text === 'undefined') return '';
                const map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) {
                    return map[m];
                });
            }

            function unescapeHtml(text) {
                if (text === null || typeof text === 'undefined') return '';
                const parser = new DOMParser();
                const dom = parser.parseFromString(`<!doctype html><body>${text}`, 'text/html');
                return dom.body.textContent;
            }
        });
    </script>
</body>

</html>