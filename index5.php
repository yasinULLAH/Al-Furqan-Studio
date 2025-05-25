<?php
ob_start(); // Start output buffering
ini_set('display_errors', 0); // Disable error display for production
// Quran Study Hub - A Comprehensive Single-File PHP Application
// Author: Yasin Ullah
// Date: 2023-10-27
// Version: 1.0

// SEO Optimization
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quran Study Hub - Read & Reflect</title>
    <meta name="description" content="Quran Study Hub offers an immersive Quranic reading experience, mimicking the Taj company Mushaf with word-level meanings, tafsir, and hifz tracking.">
    <meta name="keywords" content="Quran, Study, Hub, Taj Mushaf, Arabic, Urdu, English, Tafsir, Hifz, Islam, Muslim, Word-level, Interactive Quran">
    <meta name="author" content="Yasin Ullah">
    <link rel="canonical" href="https://yourdomain.com/index5.php">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text x='0' y='14' font-size='16'>📖</text></svg>" type="image/svg+xml">

    <!-- Google Fonts for Arabic (Noto Naskh Arabic) and general UI -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Naskh+Arabic:wght@400;700&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">

    <style>
        /* General Styles */
        :root {
            --primary-color: #4CAF50; /* Green */
            --secondary-color: #FFC107; /* Amber */
            --accent-color: #2196F3; /* Blue */
            --text-color: #333;
            --light-text-color: #666;
            --bg-color: #f8f8f8;
            --mushaf-bg: #fffbf5; /* Creamy background for Mushaf */
            --border-color: #ddd;
            --shadow: 0 2px 5px rgba(0,0,0,0.1);
            --mushaf-border: 1px solid #c0a080; /* Mushaf border color */
            --mushaf-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color: var(--bg-color);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
        }

        header {
            background-color: var(--primary-color);
            color: white;
            padding: 15px 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
        }

        header h1 {
            font-size: 1.8em;
            margin: 0;
        }

        nav ul {
            list-style: none;
            display: flex;
        }

        nav ul li {
            margin-left: 20px;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }

        nav ul li a:hover {
            color: var(--secondary-color);
        }

        .btn {
            background-color: var(--accent-color);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background-color 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background-color: #1976D2;
            transform: translateY(-2px);
        }

        .btn-primary {
            background-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #388E3C;
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            color: var(--text-color);
        }
        .btn-secondary:hover {
            background-color: #FFB300;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="email"],
        .form-group input[type="file"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1em;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            font-size: 0.9em;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border-color: #bee5eb;
        }

        /* Main Content Layout */
        main {
            flex-grow: 1;
            padding: 20px 0;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .card h3 {
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* Quran Viewer Specific Styles */
        .quran-viewer {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .quran-sidebar {
            flex: 0 0 250px;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            max-height: 70vh;
            overflow-y: auto;
            position: sticky;
            top: 90px; /* Adjust based on header height */
        }

        .quran-sidebar h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
        }

        .surah-list {
            list-style: none;
        }

        .surah-list li {
            margin-bottom: 8px;
        }

        .surah-list li a {
            display: block;
            padding: 8px 10px;
            background-color: var(--bg-color);
            border-radius: 5px;
            text-decoration: none;
            color: var(--text-color);
            transition: background-color 0.2s ease, color 0.2s ease;
        }

        .surah-list li a:hover,
        .surah-list li a.active {
            background-color: var(--primary-color);
            color: white;
        }

        .quran-content-area {
            flex-grow: 1;
            background-color: var(--mushaf-bg);
            padding: 30px;
            border-radius: 10px;
            box-shadow: var(--mushaf-shadow);
            border: var(--mushaf-border);
            min-height: 600px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .quran-content-area h2 {
            font-family: 'Noto Naskh Arabic', serif;
            font-size: 2.5em;
            color: #5d4037; /* Dark brown for Bismillah */
            margin-bottom: 20px;
            direction: rtl;
            text-align: center;
        }

        .quran-page-controls {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-top: 20px;
            padding: 0 20px;
        }

        .quran-page-controls button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }

        .quran-page-controls button:hover {
            background-color: #388E3C;
        }

        .ayah-container {
            margin-bottom: 25px;
            padding: 15px 0;
            border-bottom: 1px dotted var(--border-color);
            text-align: right; /* Ensure Arabic text aligns right */
            direction: rtl;
            font-size: 1.8em; /* Base font size for Arabic */
            line-height: 2.2;
            font-family: 'Noto Naskh Arabic', serif;
            color: #2c3e50; /* Dark blue-grey for Arabic text */
            position: relative;
        }

        .ayah-container:last-child {
            border-bottom: none;
        }

        .arabic-word {
            display: inline-block;
            cursor: pointer;
            position: relative;
            margin: 0 5px;
            padding: 2px 0;
            transition: background-color 0.2s ease;
        }

        .arabic-word:hover {
            background-color: rgba(var(--secondary-color), 0.1);
            border-radius: 3px;
        }

        .word-meaning-popup {
            position: absolute;
            background-color: #333;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.8em;
            white-space: nowrap;
            z-index: 10;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s ease;
            bottom: 100%; /* Position above the word */
            left: 50%;
            transform: translateX(-50%) translateY(10px);
            pointer-events: none; /* Allow clicks through popup */
        }

        .arabic-word:hover .word-meaning-popup {
            opacity: 1;
            visibility: visible;
            transform: translateX(-50%) translateY(0);
        }

        .ayah-number {
            font-size: 0.7em;
            vertical-align: super;
            margin-left: 5px;
            color: var(--accent-color);
            font-weight: bold;
            font-family: 'Roboto', sans-serif; /* Keep numbers standard font */
        }

        /* Tafsir and Meaning Contribution */
        .contribution-form {
            background-color: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-top: 20px;
        }

        .contribution-form h3 {
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        /* Admin/Ulama Dashboard */
        .admin-dashboard, .ulama-dashboard {
            margin-top: 20px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
            box-shadow: var(--shadow);
            border-radius: 8px;
            overflow: hidden;
        }

        table th, table td {
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            text-align: left;
        }

        table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
        }

        table tr:nth-child(even) {
            background-color: #f2f2f2;
        }

        table tr:hover {
            background-color: #e9e9e9;
        }

        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 0.8em;
            margin-right: 5px;
        }

        /* Search & Filters */
        .search-section {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .search-section .form-group {
            flex: 1;
            min-width: 180px;
            margin-bottom: 0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            header .container {
                flex-direction: column;
                align-items: flex-start;
            }

            nav ul {
                margin-top: 10px;
                flex-direction: column;
                width: 100%;
            }

            nav ul li {
                margin: 5px 0;
            }

            .quran-viewer {
                flex-direction: column;
            }

            .quran-sidebar {
                width: 100%;
                max-height: none;
                position: static;
            }

            .quran-content-area {
                padding: 20px;
            }

            .ayah-container {
                font-size: 1.5em;
                line-height: 1.8;
            }

            .search-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-section .form-group {
                min-width: unset;
            }
        }

        @media (max-width: 480px) {
            header h1 {
                font-size: 1.5em;
            }

            .btn {
                padding: 8px 12px;
                font-size: 0.8em;
            }

            .ayah-container {
                font-size: 1.3em;
                line-height: 1.6;
            }
        }

        /* Utility classes */
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
        .mb-20 { margin-bottom: 20px; }
        .hidden { display: none !important; }

        /* Custom scrollbar for Mushaf feel */
        .quran-sidebar, .quran-content-area {
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) var(--bg-color);
        }

        .quran-sidebar::-webkit-scrollbar, .quran-content-area::-webkit-scrollbar {
            width: 8px;
        }

        .quran-sidebar::-webkit-scrollbar-track, .quran-content-area::-webkit-scrollbar-track {
            background: var(--bg-color);
            border-radius: 10px;
        }

        .quran-sidebar::-webkit-scrollbar-thumb, .quran-content-area::-webkit-scrollbar-thumb {
            background-color: var(--primary-color);
            border-radius: 10px;
            border: 2px solid var(--bg-color);
        }

        /* Thematic Linking & Hifz Tracking */
        .hifz-progress-bar {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            height: 10px;
            margin-top: 10px;
            overflow: hidden;
        }
        .hifz-progress {
            height: 100%;
            background-color: var(--accent-color);
            width: 0%; /* Dynamic width */
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        .ayah-hifz-status {
            font-size: 0.8em;
            color: var(--light-text-color);
            margin-top: 5px;
            text-align: left;
            direction: ltr; /* Ensure status text is LTR */
        }

        .ayah-hifz-status .hifz-toggle {
            cursor: pointer;
            color: var(--accent-color);
            font-weight: bold;
            margin-left: 10px;
        }
        .ayah-hifz-status .hifz-toggle:hover {
            text-decoration: underline;
        }

        .ayah-highlight-hifz {
            background-color: rgba(var(--accent-color), 0.1);
            border-radius: 5px;
            padding: 5px;
        }
        .ayah-highlight-theme {
            background-color: rgba(var(--secondary-color), 0.1);
            border-radius: 5px;
            padding: 5px;
        }
        .ayah-highlight-notes {
            background-color: rgba(var(--primary-color), 0.1);
            border-radius: 5px;
            padding: 5px;
        }

        .theme-tag {
            display: inline-block;
            background-color: var(--secondary-color);
            color: var(--text-color);
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 0.75em;
            margin-right: 5px;
            margin-bottom: 5px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .theme-tag:hover {
            background-color: #FFB300;
        }
    </style>
</head>
<body>

<?php
ini_set('display_errors', 0);
// PHP Configuration and Database Setup
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Use a more secure user in production
define('DB_PASSWORD', 'root'); // Use a strong password in production
define('DB_NAME', 'quran_study_hub');

// Error Reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session Management
session_start();

// Database Connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to sanitize input
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($data))));
}

// User Roles
const ROLE_ADMIN = 'admin';
const ROLE_ULAMA = 'ulama';
const ROLE_USER = 'user';

// Check if user is logged in and get role
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role'] ?? null;
}

function has_permission($required_roles) {
    if (!is_logged_in()) {
        return false;
    }
    $user_role = get_user_role();
    return in_array($user_role, $required_roles);
}

// Function to redirect
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Database Schema Creation (Run once, or check existence)
function setup_database($conn) {
    $queries = [
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE,
            role ENUM('admin', 'ulama', 'user') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );",
        "CREATE TABLE IF NOT EXISTS word_dictionary (
            id INT AUTO_INCREMENT PRIMARY KEY,
            arabic_word VARCHAR(255) NOT NULL UNIQUE,
            urdu_meaning TEXT,
            english_meaning TEXT,
            root_word VARCHAR(255),
            CHECK (char_length(arabic_word) > 0)
        );",
        "CREATE TABLE IF NOT EXISTS ayah_word_mapping (
            id INT AUTO_INCREMENT PRIMARY KEY,
            surah_number INT NOT NULL,
            ayah_number INT NOT NULL,
            word_position INT NOT NULL,
            word_id INT NOT NULL,
            FOREIGN KEY (word_id) REFERENCES word_dictionary(id),
            UNIQUE(surah_number, ayah_number, word_position)
        );",
        "CREATE TABLE IF NOT EXISTS tafsirs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            surah_number INT NOT NULL,
            ayah_number INT NOT NULL,
            word_id INT, -- NULL if ayah-level tafsir
            tafsir_text TEXT NOT NULL,
            language ENUM('urdu', 'english') NOT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_by INT,
            approved_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (word_id) REFERENCES word_dictionary(id),
            FOREIGN KEY (approved_by) REFERENCES users(id)
        );",
        "CREATE TABLE IF NOT EXISTS hifz_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            surah_number INT NOT NULL,
            ayah_number INT NOT NULL,
            status ENUM('not_started', 'in_progress', 'memorized') DEFAULT 'not_started',
            last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(user_id, surah_number, ayah_number)
        );",
        "CREATE TABLE IF NOT EXISTS notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            surah_number INT NOT NULL,
            ayah_number INT NOT NULL,
            note_text TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );",
        "CREATE TABLE IF NOT EXISTS themes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            theme_name VARCHAR(255) NOT NULL UNIQUE
        );",
        "CREATE TABLE IF NOT EXISTS ayah_themes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            surah_number INT NOT NULL,
            ayah_number INT NOT NULL,
            theme_id INT NOT NULL,
            FOREIGN KEY (theme_id) REFERENCES themes(id),
            UNIQUE(surah_number, ayah_number, theme_id)
        );"
    ];

    foreach ($queries as $query) {
        if (!$conn->query($query)) {
            // Log error, but don't die, as some tables might exist
            error_log("DB Setup Error: " . $conn->error . " Query: " . $query);
        }
    }

    // Add a default admin user if not exists (for initial setup)
    $admin_username = 'admin';
    $admin_email = 'admin@example.com';
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT); // CHANGE THIS IN PRODUCTION!
    $check_admin_sql = "SELECT id FROM users WHERE username = '$admin_username'";
    $result = $conn->query($check_admin_sql);
    if ($result->num_rows == 0) {
        $insert_admin_sql = "INSERT INTO users (username, password, email, role) VALUES ('$admin_username', '$admin_password', '$admin_email', 'admin')";
        if ($conn->query($insert_admin_sql)) {
            error_log("Default admin user created.");
        } else {
            error_log("Failed to create default admin user: " . $conn->error);
        }
    }
}

// Run database setup on every load (safe with IF NOT EXISTS)
setup_database($conn);

// --- Core Application Logic ---

$current_page = $_GET['page'] ?? 'home';
$message = '';
$message_type = '';

// Handle User Authentication (Login/Register/Logout)
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'register') {
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = password_hash(sanitize_input($_POST['password']), PASSWORD_DEFAULT);
        $role = ROLE_USER; // Default role for registration

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $username, $email, $password, $role);
            if ($stmt->execute()) {
                $message = "Registration successful! Please log in.";
                $message_type = "success";
            } else {
                $message = "Registration failed. Username or email might already exist. " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        } else {
            $message = "Database error during registration.";
            $message_type = "error";
        }
    } elseif ($_POST['action'] == 'login') {
        $username = sanitize_input($_POST['username']);
        $password = sanitize_input($_POST['password']);

        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->bind_result($user_id, $hashed_password, $role);
            $stmt->fetch();
            $stmt->close();

            if ($user_id && password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = $role;
                $message = "Login successful!";
                $message_type = "success";
                redirect("index5.php?page=dashboard"); // Redirect to dashboard after login
            } else {
                $message = "Invalid username or password.";
                $message_type = "error";
            }
        } else {
            $message = "Database error during login.";
            $message_type = "error";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    $message = "You have been logged out.";
    $message_type = "info";
    redirect("index5.php?page=home");
}

// --- Admin/Ulama Functions ---

// Data Ingestion (CSV Upload)

// ... (previous PHP code)

// Data Ingestion (CSV Upload)
if (isset($_POST['upload_data']) && has_permission([ROLE_ADMIN])) {
    $file_type = sanitize_input($_POST['file_type']);
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['csv_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

        if ($file_ext != 'csv') {
            $message = "Invalid file type. Please upload a CSV file.";
            $message_type = "error";
        } else {
            $handle = fopen($file_tmp_name, "r");
            if ($handle) {
                $conn->begin_transaction();
                try {
                    // Disable foreign key checks temporarily for truncation
                    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");

                    if ($file_type == 'word_dictionary') {
                        // Truncate table for fresh import, or implement upsert logic
                        $conn->query("TRUNCATE TABLE word_dictionary");
                        $stmt = $conn->prepare("INSERT INTO word_dictionary (arabic_word, urdu_meaning, english_meaning) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE urdu_meaning=VALUES(urdu_meaning), english_meaning=VALUES(english_meaning)");
                        fgetcsv($handle); // Skip header row
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($data) >= 3) {
                                $arabic_word = sanitize_input($data[0]);
                                $urdu_meaning = sanitize_input($data[1]);
                                $english_meaning = sanitize_input($data[2]);
                                $stmt->bind_param("sss", $arabic_word, $urdu_meaning, $english_meaning);
                                $stmt->execute();
                            }
                        }
                        $message = "Word dictionary data imported successfully.";
                    } elseif ($file_type == 'ayah_word_mapping') {
                        // Truncate table for fresh import
                        $conn->query("TRUNCATE TABLE ayah_word_mapping");
                        $stmt = $conn->prepare("INSERT INTO ayah_word_mapping (surah_number, ayah_number, word_position, word_id) VALUES (?, ?, ?, ?)");
                        fgetcsv($handle); // Skip header row
                        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                            if (count($data) >= 4) {
                                $surah_number = (int) $data[0];
                                $ayah_number = (int) $data[1];
                                $word_position = (int) $data[2];
                                $word_id = (int) $data[3];
                                $stmt->bind_param("iiii", $surah_number, $ayah_number, $word_position, $word_id);
                                $stmt->execute();
                            }
                        }
                        $message = "Ayah word mapping data imported successfully.";
                    }
                    $conn->commit();
                    $message_type = "success";
                    if (isset($stmt)) $stmt->close(); // Close statement if it was opened
                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    $message = "Error importing data: " . $e->getMessage();
                    $message_type = "error";
                } finally {
                    // Re-enable foreign key checks
                    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
                }
                fclose($handle);
            } else {
                $message = "Could not open uploaded file.";
                $message_type = "error";
            }
        }
    } else {
        $message = "Error uploading file. Please select a file.";
        $message_type = "error";
    }
}

// ... (rest of the PHP code)

// Tafsir/Meaning Contribution Submission
if (isset($_POST['submit_tafsir']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $surah_number = (int) sanitize_input($_POST['surah_number']);
    $ayah_number = (int) sanitize_input($_POST['ayah_number']);
    $word_id = isset($_POST['word_id']) ? (int) sanitize_input($_POST['word_id']) : null;
    $tafsir_text = sanitize_input($_POST['tafsir_text']);
    $language = sanitize_input($_POST['language']);
    $status = (get_user_role() == ROLE_ADMIN || get_user_role() == ROLE_ULAMA) ? 'pending' : 'pending'; // All contributions start as pending

    $stmt = $conn->prepare("INSERT INTO tafsirs (user_id, surah_number, ayah_number, word_id, tafsir_text, language, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiisss", $user_id, $surah_number, $ayah_number, $word_id, $tafsir_text, $language, $status);
        if ($stmt->execute()) {
            $message = "Your contribution has been submitted for approval.";
            $message_type = "success";
        } else {
            $message = "Failed to submit contribution: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    } else {
        $message = "Database error submitting contribution.";
        $message_type = "error";
    }
}

// Tafsir/Meaning Approval/Rejection (Admin/Ulama)
if (isset($_POST['approve_tafsir']) && has_permission([ROLE_ADMIN, ROLE_ULAMA])) {
    $tafsir_id = (int) sanitize_input($_POST['tafsir_id']);
    $approver_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE tafsirs SET status = 'approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $approver_id, $tafsir_id);
        if ($stmt->execute()) {
            $message = "Tafsir approved successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to approve tafsir: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}
if (isset($_POST['reject_tafsir']) && has_permission([ROLE_ADMIN, ROLE_ULAMA])) {
    $tafsir_id = (int) sanitize_input($_POST['tafsir_id']);
    $approver_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("UPDATE tafsirs SET status = 'rejected', approved_by = ?, approved_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $approver_id, $tafsir_id);
        if ($stmt->execute()) {
            $message = "Tafsir rejected successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to reject tafsir: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// User Management (Admin)
if (isset($_POST['update_user_role']) && has_permission([ROLE_ADMIN])) {
    $user_id = (int) sanitize_input($_POST['user_id']);
    $new_role = sanitize_input($_POST['new_role']);
    if (in_array($new_role, [ROLE_ADMIN, ROLE_ULAMA, ROLE_USER])) {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $new_role, $user_id);
            if ($stmt->execute()) {
                $message = "User role updated successfully.";
                $message_type = "success";
            } else {
                $message = "Failed to update user role: " . $stmt->error;
                $message_type = "error";
            }
            $stmt->close();
        }
    } else {
        $message = "Invalid role specified.";
        $message_type = "error";
    }
}

// Hifz Tracking (User)
if (isset($_POST['update_hifz_status']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $surah_number = (int) sanitize_input($_POST['surah_number']);
    $ayah_number = (int) sanitize_input($_POST['ayah_number']);
    $status = sanitize_input($_POST['hifz_status']);

    $stmt = $conn->prepare("INSERT INTO hifz_tracking (user_id, surah_number, ayah_number, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status), last_updated = CURRENT_TIMESTAMP");
    if ($stmt) {
        $stmt->bind_param("iiis", $user_id, $surah_number, $ayah_number, $status);
        if ($stmt->execute()) {
            $message = "Hifz status updated.";
            $message_type = "success";
        } else {
            $message = "Failed to update hifz status: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Notes (User)
if (isset($_POST['add_note']) && is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $surah_number = (int) sanitize_input($_POST['surah_number']);
    $ayah_number = (int) sanitize_input($_POST['ayah_number']);
    $note_text = sanitize_input($_POST['note_text']);

    $stmt = $conn->prepare("INSERT INTO notes (user_id, surah_number, ayah_number, note_text) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iiis", $user_id, $surah_number, $ayah_number, $note_text);
        if ($stmt->execute()) {
            $message = "Note added successfully.";
            $message_type = "success";
        } else {
            $message = "Failed to add note: " . $stmt->error;
            $message_type = "error";
        }
        $stmt->close();
    }
}

// Backup and Restore (Admin)
if (isset($_GET['action']) && $_GET['action'] == 'backup_db' && has_permission([ROLE_ADMIN])) {
    // This is a basic example. For production, consider using mysqldump or a more robust solution.
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    while($row = $res->fetch_row()) {
        $tables[] = $row[0];
    }

    $sql_dump = "";
    foreach($tables as $table) {
        $result = $conn->query("SELECT * FROM $table");
        $num_fields = $result->field_count;

        $sql_dump .= "DROP TABLE IF EXISTS `$table`;";
        $row2 = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        $sql_dump .= "\n\n" . $row2[1] . ";\n\n";

        for ($i = 0; $i < $num_fields; $i++) {
            while($row = $result->fetch_row()) {
                $sql_dump .= "INSERT INTO `$table` VALUES(";
                for($j=0; $j<$num_fields; $j++) {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = preg_replace("/\n/","\\n",$row[$j]);
                    if (isset($row[$j])) { $sql_dump .= '"'.$row[$j].'"' ; } else { $sql_dump.= 'NULL'; }
                    if ($j < ($num_fields-1)) { $sql_dump.= ','; }
                }
                $sql_dump.= ");\n";
            }
        }
        $sql_dump.="\n\n\n";
    }

    header('Content-Type: application/octet-stream');
    header('Content-Transfer-Encoding: Binary');
    header('Content-disposition: attachment; filename="quran_study_hub_backup_'.date('Y-m-d_H-i-s').'.sql"');
    echo $sql_dump;
    exit();
}

if (isset($_POST['restore_db']) && has_permission([ROLE_ADMIN])) {
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['sql_file']['tmp_name'];
        $file_ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));

        if ($file_ext != 'sql') {
            $message = "Invalid file type. Please upload an SQL file.";
            $message_type = "error";
        } else {
            $sql_content = file_get_contents($file_tmp_name);
            if ($sql_content === false) {
                $message = "Could not read uploaded SQL file.";
                $message_type = "error";
            } else {
                // This is a simplified restore. For large files, parse statements.
                // Using multi_query for simplicity, but be cautious with untrusted SQL.
                if ($conn->multi_query($sql_content)) {
                    do {
                        // Consume all results
                        if ($result = $conn->store_result()) {
                            $result->free();
                        }
                    } while ($conn->more_results() && $conn->next_result());
                    $message = "Database restored successfully.";
                    $message_type = "success";
                } else {
                    $message = "Error restoring database: " . $conn->error;
                    $message_type = "error";
                }
            }
        }
    } else {
        $message = "Error uploading file. Please select an SQL file.";
        $message_type = "error";
    }
}


// --- AJAX Endpoints ---
// This single file handles AJAX requests by checking a specific parameter.
// This avoids needing a separate `ajax.php` file.

if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_word_meaning') {
    header('Content-Type: application/json');
    $word_id = (int) ($_GET['word_id'] ?? 0);
    $meaning_data = ['urdu' => 'Meaning not found.', 'english' => 'Meaning not found.'];

    if ($word_id > 0) {
        $stmt = $conn->prepare("SELECT urdu_meaning, english_meaning FROM word_dictionary WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $word_id);
            $stmt->execute();
            $stmt->bind_result($urdu, $english);
            if ($stmt->fetch()) {
                $meaning_data['urdu'] = $urdu;
                $meaning_data['english'] = $english;
            }
            $stmt->close();
        }
    }
    echo json_encode($meaning_data);
    exit(); // Important: exit after AJAX response
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_ayah_content') {
    header('Content-Type: application/json');
    $surah_number = (int) ($_GET['surah'] ?? 1);
    $ayah_number = (int) ($_GET['ayah'] ?? 1);
    $user_id = $_SESSION['user_id'] ?? 0;

    $ayah_content = [];
    $hifz_status = 'not_started';
    $notes = [];
    $themes = [];
    $tafsirs = []; // To store approved tafsirs

    // Fetch Ayah Words
    $stmt = $conn->prepare("
        SELECT awm.word_position, wd.arabic_word, wd.id AS word_id
        FROM ayah_word_mapping awm
        JOIN word_dictionary wd ON awm.word_id = wd.id
        WHERE awm.surah_number = ? AND awm.ayah_number = ?
        ORDER BY awm.word_position ASC
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $surah_number, $ayah_number);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $ayah_content[] = $row;
        }
        $stmt->close();
    }

    // Fetch Hifz Status
    if ($user_id) {
        $stmt = $conn->prepare("SELECT status FROM hifz_tracking WHERE user_id = ? AND surah_number = ? AND ayah_number = ?");
        if ($stmt) {
            $stmt->bind_param("iii", $user_id, $surah_number, $ayah_number);
            $stmt->execute();
            $stmt->bind_result($status);
            if ($stmt->fetch()) {
                $hifz_status = $status;
            }
            $stmt->close();
        }

        // Fetch Notes
        $stmt = $conn->prepare("SELECT note_text FROM notes WHERE user_id = ? AND surah_number = ? AND ayah_number = ? ORDER BY created_at DESC");
        if ($stmt) {
            $stmt->bind_param("iii", $user_id, $surah_number, $ayah_number);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $notes[] = $row['note_text'];
            }
            $stmt->close();
        }
    }

    // Fetch Themes
    $stmt = $conn->prepare("SELECT t.theme_name FROM ayah_themes at JOIN themes t ON at.theme_id = t.id WHERE at.surah_number = ? AND at.ayah_number = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $surah_number, $ayah_number);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $themes[] = $row['theme_name'];
        }
        $stmt->close();
    }

    // Fetch Approved Tafsirs
    $stmt = $conn->prepare("
        SELECT t.tafsir_text, t.language, u.username, wd.arabic_word AS word_arabic
        FROM tafsirs t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN word_dictionary wd ON t.word_id = wd.id
        WHERE t.surah_number = ? AND t.ayah_number = ? AND t.status = 'approved'
        ORDER BY t.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("ii", $surah_number, $ayah_number);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $tafsirs[] = $row;
        }
        $stmt->close();
    }

    echo json_encode([
        'ayah_words' => $ayah_content,
        'hifz_status' => $hifz_status,
        'notes' => $notes,
        'themes' => $themes,
        'tafsirs' => $tafsirs
    ]);
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'search_quran') {
    header('Content-Type: application/json');
    $search_term = sanitize_input($_GET['term'] ?? '');
    $search_type = sanitize_input($_GET['type'] ?? 'all'); // 'all', 'arabic', 'urdu', 'english', 'tafsir', 'theme'
    $surah_filter = (int) ($_GET['surah_filter'] ?? 0);

    $results = [];

    // Search in word_dictionary (Arabic, Urdu, English)
    if ($search_type == 'all' || $search_type == 'arabic' || $search_type == 'urdu' || $search_type == 'english') {
        $sql = "
            SELECT DISTINCT awm.surah_number, awm.ayah_number
            FROM ayah_word_mapping awm
            JOIN word_dictionary wd ON awm.word_id = wd.id
            WHERE 1=1
        ";
        $params = [];
        $types = "";

        if ($search_term) {
            $sql .= " AND (";
            $conditions = [];
            if ($search_type == 'all' || $search_type == 'arabic') {
                $conditions[] = "wd.arabic_word LIKE ?";
                $params[] = "%" . $search_term . "%";
                $types .= "s";
            }
            if ($search_type == 'all' || $search_type == 'urdu') {
                $conditions[] = "wd.urdu_meaning LIKE ?";
                $params[] = "%" . $search_term . "%";
                $types .= "s";
            }
            if ($search_type == 'all' || $search_type == 'english') {
                $conditions[] = "wd.english_meaning LIKE ?";
                $params[] = "%" . $search_term . "%";
                $types .= "s";
            }
            $sql .= implode(" OR ", $conditions) . ")";
        }

        if ($surah_filter > 0) {
            $sql .= " AND awm.surah_number = ?";
            $params[] = $surah_filter;
            $types .= "i";
        }

        $sql .= " ORDER BY awm.surah_number, awm.ayah_number LIMIT 50"; // Limit results for performance

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = ['type' => 'word', 'surah' => $row['surah_number'], 'ayah' => $row['ayah_number']];
            }
            $stmt->close();
        }
    }

    // Search in Tafsirs
    if ($search_type == 'all' || $search_type == 'tafsir') {
        $sql = "
            SELECT DISTINCT surah_number, ayah_number
            FROM tafsirs
            WHERE status = 'approved'
        ";
        $params = [];
        $types = "";

        if ($search_term) {
            $sql .= " AND tafsir_text LIKE ?";
            $params[] = "%" . $search_term . "%";
            $types .= "s";
        }

        if ($surah_filter > 0) {
            $sql .= " AND surah_number = ?";
            $params[] = $surah_filter;
            $types .= "i";
        }
        $sql .= " ORDER BY surah_number, ayah_number LIMIT 50";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = ['type' => 'tafsir', 'surah' => $row['surah_number'], 'ayah' => $row['ayah_number']];
            }
            $stmt->close();
        }
    }

    // Search in Themes
    if ($search_type == 'all' || $search_type == 'theme') {
        $sql = "
            SELECT DISTINCT at.surah_number, at.ayah_number
            FROM ayah_themes at
            JOIN themes t ON at.theme_id = t.id
            WHERE t.theme_name LIKE ?
        ";
        $params = ["%" . $search_term . "%"];
        $types = "s";

        if ($surah_filter > 0) {
            $sql .= " AND at.surah_number = ?";
            $params[] = $surah_filter;
            $types .= "is"; // Adjusted types for new param
        }
        $sql .= " ORDER BY at.surah_number, at.ayah_number LIMIT 50";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($types) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = ['type' => 'theme', 'surah' => $row['surah_number'], 'ayah' => $row['ayah_number']];
            }
            $stmt->close();
        }
    }

    // Deduplicate and sort results
    $unique_results = [];
    foreach ($results as $res) {
        $key = $res['surah'] . '-' . $res['ayah'];
        if (!isset($unique_results[$key])) {
            $unique_results[$key] = $res;
        }
    }
    usort($unique_results, function($a, $b) {
        if ($a['surah'] == $b['surah']) {
            return $a['ayah'] - $b['ayah'];
        }
        return $a['surah'] - $b['surah'];
    });

    echo json_encode(array_values($unique_results));
    exit();
}


// --- Data for UI (Surah List, etc.) ---
$surahs = [
    1 => "Al-Fatiha (The Opening)",
    2 => "Al-Baqarah (The Cow)",
    3 => "Al-Imran (The Family of Imran)",
    4 => "An-Nisa (The Women)",
    // Sample 4 data for Surahs. In a real app, this would be from a database or a more comprehensive static file.
    // For demonstration, we'll use these and dynamically fetch Ayahs.
    // The full list of 114 surahs would be ideal.
    // 5 => "Al-Ma'idah (The Table Spread)",
    // ... up to 114
];

// Get total number of ayahs for hifz tracking progress calculation
$total_ayahs_res = $conn->query("SELECT COUNT(DISTINCT surah_number, ayah_number) AS total_ayahs FROM ayah_word_mapping");
$total_ayahs = $total_ayahs_res ? $total_ayahs_res->fetch_assoc()['total_ayahs'] : 0;

$memorized_ayahs = 0;
if (is_logged_in()) {
    $user_id = $_SESSION['user_id'];
    $memorized_ayahs_res = $conn->query("SELECT COUNT(*) AS memorized_ayahs FROM hifz_tracking WHERE user_id = $user_id AND status = 'memorized'");
    $memorized_ayahs = $memorized_ayahs_res ? $memorized_ayahs_res->fetch_assoc()['memorized_ayahs'] : 0;
}
$hifz_percentage = $total_ayahs > 0 ? round(($memorized_ayahs / $total_ayahs) * 100, 2) : 0;

?>

    <header>
        <div class="container">
            <h1>Quran Study Hub</h1>
            <nav>
                <ul>
                    <li><a href="?page=home">Home</a></li>
                    <li><a href="?page=quran_viewer">Quran Viewer</a></li>
                    <?php if (is_logged_in()): ?>
                        <li><a href="?page=dashboard">Dashboard</a></li>
                        <?php if (has_permission([ROLE_ADMIN])): ?>
                            <li><a href="?page=admin_panel">Admin Panel</a></li>
                        <?php endif; ?>
                        <?php if (has_permission([ROLE_ULAMA])): ?>
                            <li><a href="?page=ulama_panel">Ulama Panel</a></li>
                        <?php endif; ?>
                        <li><a href="?action=logout">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                    <?php else: ?>
                        <li><a href="?page=login">Login</a></li>
                        <li><a href="?page=register">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php
        // --- Page Routing ---
        switch ($current_page) {
            case 'home':
                ?>
                <section class="text-center">
                    <h2>Welcome to Quran Study Hub</h2>
                    <p>Your comprehensive platform for studying the Holy Quran with word-level meanings, personalized tafsir, and hifz tracking.</p>
                    <p>Experience the feel of reading a Taj company Mushaf, enhanced with interactive features.</p>
                    <div class="mt-20">
                        <a href="?page=quran_viewer" class="btn btn-primary">Start Reading Quran</a>
                        <?php if (!is_logged_in()): ?>
                            <a href="?page=register" class="btn btn-secondary">Join Us Today</a>
                        <?php endif; ?>
                    </div>
                </section>
                <?php
                break;

            case 'register':
                if (is_logged_in()) { redirect("index5.php?page=dashboard"); }
                ?>
                <section class="card">
                    <h2>Register</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Register</button>
                    </form>
                    <p class="mt-20">Already have an account? <a href="?page=login">Login here</a>.</p>
                </section>
                <?php
                break;

            case 'login':
                if (is_logged_in()) { redirect("index5.php?page=dashboard"); }
                ?>
                <section class="card">
                    <h2>Login</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Login</button>
                    </form>
                    <p class="mt-20">Don't have an account? <a href="?page=register">Register here</a>.</p>
                </section>
                <?php
                break;

            case 'dashboard':
                if (!is_logged_in()) { redirect("index5.php?page=login"); }
                ?>
                <section class="dashboard-grid">
                    <div class="card">
                        <h3>Welcome, <?php echo $_SESSION['username']; ?>!</h3>
                        <p>Your Role: <strong><?php echo ucfirst($_SESSION['role']); ?></strong></p>
                        <p>Here you can manage your personalized Quran study experience.</p>
                    </div>
                    <div class="card">
                        <h3>Hifz Progress</h3>
                        <p>Memorized Ayahs: <?php echo $memorized_ayahs; ?> / <?php echo $total_ayahs; ?></p>
                        <div class="hifz-progress-bar">
                            <div class="hifz-progress" style="width: <?php echo $hifz_percentage; ?>%;"></div>
                        </div>
                        <p><?php echo $hifz_percentage; ?>% Completed</p>
                        <p class="mt-10"><small>Track your memorization status directly in the Quran Viewer.</small></p>
                    </div>
                    <div class="card">
                        <h3>Your Contributions</h3>
                        <p>View and manage your submitted tafsirs and notes.</p>
                        <a href="?page=my_contributions" class="btn btn-primary mt-10">View Contributions</a>
                    </div>
                    <?php if (has_permission([ROLE_ADMIN, ROLE_ULAMA])): ?>
                    <div class="card">
                        <h3>Pending Approvals</h3>
                        <p>Review and approve tafsir contributions from other users.</p>
                        <a href="?page=ulama_panel" class="btn btn-primary mt-10">Review Submissions</a>
                    </div>
                    <?php endif; ?>
                    <?php if (has_permission([ROLE_ADMIN])): ?>
                    <div class="card">
                        <h3>Admin Tools</h3>
                        <p>Manage users, import data, and perform system backups.</p>
                        <a href="?page=admin_panel" class="btn btn-primary mt-10">Go to Admin Panel</a>
                    </div>
                    <?php endif; ?>
                </section>
                <?php
                break;
            
            case 'my_contributions':
                if (!is_logged_in()) { redirect("index5.php?page=login"); }
                $user_id = $_SESSION['user_id'];
                ?>
                <section class="card">
                    <h2>My Tafsir Contributions</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Surah</th>
                                    <th>Ayah</th>
                                    <th>Word (if any)</th>
                                    <th>Tafsir Text</th>
                                    <th>Language</th>
                                    <th>Status</th>
                                    <th>Submitted On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("
                                    SELECT t.surah_number, t.ayah_number, wd.arabic_word, t.tafsir_text, t.language, t.status, t.created_at
                                    FROM tafsirs t
                                    LEFT JOIN word_dictionary wd ON t.word_id = wd.id
                                    WHERE t.user_id = ? ORDER BY t.created_at DESC
                                ");
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . $row['surah_number'] . "</td>";
                                        echo "<td>" . $row['ayah_number'] . "</td>";
                                        echo "<td dir='rtl'>" . ($row['arabic_word'] ?? 'N/A') . "</td>";
                                        echo "<td>" . htmlspecialchars(mb_substr($row['tafsir_text'], 0, 100, 'UTF-8')) . (mb_strlen($row['tafsir_text'], 'UTF-8') > 100 ? '...' : '') . "</td>";
                                        echo "<td>" . ucfirst($row['language']) . "</td>";
                                        echo "<td>" . ucfirst($row['status']) . "</td>";
                                        echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='7'>No tafsir contributions found.</td></tr>";
                                }
                                $stmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="card mt-20">
                    <h2>My Notes</h2>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Surah</th>
                                    <th>Ayah</th>
                                    <th>Note Text</th>
                                    <th>Added On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt = $conn->prepare("SELECT surah_number, ayah_number, note_text, created_at FROM notes WHERE user_id = ? ORDER BY created_at DESC");
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                if ($result->num_rows > 0) {
                                    while ($row = $result->fetch_assoc()) {
                                        echo "<tr>";
                                        echo "<td>" . $row['surah_number'] . "</td>";
                                        echo "<td>" . $row['ayah_number'] . "</td>";
                                        echo "<td>" . htmlspecialchars(mb_substr($row['note_text'], 0, 100, 'UTF-8')) . (mb_strlen($row['note_text'], 'UTF-8') > 100 ? '...' : '') . "</td>";
                                        echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
                                        echo "</tr>";
                                    }
                                } else {
                                    echo "<tr><td colspan='4'>No notes found.</td></tr>";
                                }
                                $stmt->close();
                                ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php
                break;

            case 'admin_panel':
                if (!has_permission([ROLE_ADMIN])) { redirect("index5.php?page=dashboard"); }
                ?>
                <section class="admin-dashboard">
                    <h2>Admin Panel</h2>

                    <div class="card mt-20">
                        <h3>Data Ingestion (CSV Upload)</h3>
                        <p>Upload <code>data5.AM</code> for Word Dictionary and <code>data2.AM</code> for Ayah Word Mapping.</p>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-group">
                                <label for="file_type">Select Data Type:</label>
                                <select id="file_type" name="file_type" required>
                                    <option value="">-- Select --</option>
                                    <option value="word_dictionary">Word Dictionary (data5.AM)</option>
                                    <option value="ayah_word_mapping">Ayah Word Mapping (data2.AM)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="csv_file">Upload CSV File:</label>
                                <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
                            </div>
                            <button type="submit" name="upload_data" class="btn btn-primary">Upload Data</button>
                        </form>
                    </div>

                    <div class="card mt-20">
                        <h3>User Management</h3>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $result = $conn->query("SELECT id, username, email, role FROM users ORDER BY created_at DESC");
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                                            echo "<td>" . ucfirst($row['role']) . "</td>";
                                            echo "<td class='action-buttons'>";
                                            echo "<form method='POST' style='display:inline-block;'>";
                                            echo "<input type='hidden' name='user_id' value='" . $row['id'] . "'>";
                                            echo "<select name='new_role' style='padding: 5px; border-radius: 3px;'>";
                                            echo "<option value='admin'" . ($row['role'] == 'admin' ? ' selected' : '') . ">Admin</option>";
                                            echo "<option value='ulama'" . ($row['role'] == 'ulama' ? ' selected' : '') . ">Ulama</option>";
                                            echo "<option value='user'" . ($row['role'] == 'user' ? ' selected' : '') . ">User</option>";
                                            echo "</select>";
                                            echo " <button type='submit' name='update_user_role' class='btn btn-primary'>Update Role</button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5'>No users found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-20">
                        <h3>Database Backup & Restore</h3>
                        <p>Create a backup of your database or restore from a previous SQL backup file.</p>
                        <a href="?action=backup_db" class="btn btn-primary">Backup Database</a>
                        <form method="POST" enctype="multipart/form-data" class="mt-10">
                            <div class="form-group">
                                <label for="sql_file">Restore from SQL File:</label>
                                <input type="file" id="sql_file" name="sql_file" accept=".sql" required>
                            </div>
                            <button type="submit" name="restore_db" class="btn btn-secondary">Restore Database</button>
                        </form>
                    </div>

                </section>
                <?php
                break;

            case 'ulama_panel':
                if (!has_permission([ROLE_ADMIN, ROLE_ULAMA])) { redirect("index5.php?page=dashboard"); }
                ?>
                <section class="ulama-dashboard">
                    <h2>Ulama Panel - Tafsir Approvals</h2>
                    <div class="card mt-20">
                        <h3>Pending Tafsir Contributions</h3>
                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>User</th>
                                        <th>Surah</th>
                                        <th>Ayah</th>
                                        <th>Word (if any)</th>
                                        <th>Tafsir Text</th>
                                        <th>Language</th>
                                        <th>Submitted On</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->prepare("
                                        SELECT t.id, u.username, t.surah_number, t.ayah_number, wd.arabic_word, t.tafsir_text, t.language, t.created_at
                                        FROM tafsirs t
                                        JOIN users u ON t.user_id = u.id
                                        LEFT JOIN word_dictionary wd ON t.word_id = wd.id
                                        WHERE t.status = 'pending' ORDER BY t.created_at ASC
                                    ");
                                    $stmt->execute();
                                    $result = $stmt->get_result();
                                    if ($result->num_rows > 0) {
                                        while ($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $row['id'] . "</td>";
                                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                                            echo "<td>" . $row['surah_number'] . "</td>";
                                            echo "<td>" . $row['ayah_number'] . "</td>";
                                            echo "<td dir='rtl'>" . ($row['arabic_word'] ?? 'N/A') . "</td>";
                                            echo "<td>" . htmlspecialchars(mb_substr($row['tafsir_text'], 0, 100, 'UTF-8')) . (mb_strlen($row['tafsir_text'], 'UTF-8') > 100 ? '...' : '') . "</td>";
                                            echo "<td>" . ucfirst($row['language']) . "</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row['created_at'])) . "</td>";
                                            echo "<td class='action-buttons'>";
                                            echo "<form method='POST' style='display:inline-block;'>";
                                            echo "<input type='hidden' name='tafsir_id' value='" . $row['id'] . "'>";
                                            echo "<button type='submit' name='approve_tafsir' class='btn btn-primary'>Approve</button>";
                                            echo "</form>";
                                            echo "<form method='POST' style='display:inline-block; margin-left: 5px;'>";
                                            echo "<input type='hidden' name='tafsir_id' value='" . $row['id'] . "'>";
                                            echo "<button type='submit' name='reject_tafsir' class='btn btn-secondary'>Reject</button>";
                                            echo "</form>";
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='9'>No pending tafsir contributions.</td></tr>";
                                    }
                                    $stmt->close();
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                <?php
                break;

            case 'quran_viewer':
                $current_surah = (int) ($_GET['surah'] ?? 1);
                $current_ayah = (int) ($_GET['ayah'] ?? 1);
                ?>
                <section class="quran-viewer">
                    <div class="quran-sidebar">
                        <h3>Surahs</h3>
                        <ul class="surah-list">
                            <?php foreach ($surahs as $num => $name): ?>
                                <li><a href="javascript:void(0);" data-surah="<?php echo $num; ?>" class="<?php echo ($num == $current_surah) ? 'active' : ''; ?>"><?php echo $num . ". " . $name; ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="quran-content-area" id="quranContentArea">
                        <div class="search-section">
                            <div class="form-group">
                                <label for="search_term">Search:</label>
                                <input type="text" id="search_term" placeholder="Search words, tafsir, themes...">
                            </div>
                            <div class="form-group">
                                <label for="search_type">Search Type:</label>
                                <select id="search_type">
                                    <option value="all">All</option>
                                    <option value="arabic">Arabic Word</option>
                                    <option value="urdu">Urdu Meaning</option>
                                    <option value="english">English Meaning</option>
                                    <option value="tafsir">Tafsir</option>
                                    <option value="theme">Theme</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="search_surah_filter">Filter by Surah:</label>
                                <select id="search_surah_filter">
                                    <option value="0">All Surahs</option>
                                    <?php foreach ($surahs as $num => $name): ?>
                                        <option value="<?php echo $num; ?>"><?php echo $num . ". " . $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button id="search_button" class="btn btn-primary">Search</button>
                        </div>
                        <div id="search_results" class="card hidden" style="width: 100%; text-align: left;">
                            <h4>Search Results:</h4>
                            <ul id="search_results_list" style="list-style: none; padding: 0;"></ul>
                            <button class="btn btn-secondary" onclick="document.getElementById('search_results').classList.add('hidden')">Close Results</button>
                        </div>

                        <h2 id="bismillah" class="mb-20">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</h2>
                        <div id="ayahs_display">
                            <!-- Ayahs will be loaded here via JavaScript -->
                            <p class="text-center">Loading Quranic text...</p>
                        </div>
                        <div class="quran-page-controls">
                            <button id="prevAyahBtn" class="btn">Previous Ayah</button>
                            <span id="currentAyahInfo">Surah: <?php echo $current_surah; ?>, Ayah: <?php echo $current_ayah; ?></span>
                            <button id="nextAyahBtn" class="btn">Next Ayah</button>
                        </div>
                        <div id="ayah_details_popup" class="card hidden" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1001; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto; text-align: left;">
                            <h3 id="popup_ayah_info"></h3>
                            <p><strong>Hifz Status:</strong> <span id="popup_hifz_status"></span> <span id="hifz_toggle_link" class="hifz-toggle" style="cursor: pointer;">(Change)</span></p>
                            <div id="hifz_status_options" class="hidden mt-10">
                                <select id="hifz_status_select">
                                    <option value="not_started">Not Started</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="memorized">Memorized</option>
                                </select>
                                <button class="btn btn-primary btn-sm" id="save_hifz_status">Save</button>
                            </div>
                            <h4 class="mt-10">Notes:</h4>
                            <div id="popup_notes"></div>
                            <?php if (is_logged_in()): ?>
                            <form id="add_note_form" class="mt-10">
                                <input type="hidden" name="surah_number" id="note_surah_number">
                                <input type="hidden" name="ayah_number" id="note_ayah_number">
                                <div class="form-group">
                                    <textarea name="note_text" placeholder="Add your personal note here..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Add Note</button>
                            </form>
                            <?php endif; ?>

                            <h4 class="mt-10">Themes:</h4>
                            <div id="popup_themes"></div>

                            <h4 class="mt-10">Tafsirs:</h4>
                            <div id="popup_tafsirs"></div>
                            <?php if (is_logged_in()): ?>
                            <form id="add_tafsir_form" class="mt-10">
                                <input type="hidden" name="surah_number" id="tafsir_surah_number">
                                <input type="hidden" name="ayah_number" id="tafsir_ayah_number">
                                <input type="hidden" name="word_id" id="tafsir_word_id">
                                <div class="form-group">
                                    <label for="tafsir_text">Add Tafsir/Meaning:</label>
                                    <textarea id="tafsir_text" name="tafsir_text" placeholder="Contribute your tafsir or meaning here..." required></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="tafsir_language">Language:</label>
                                    <select id="tafsir_language" name="language">
                                        <option value="english">English</option>
                                        <option value="urdu">Urdu</option>
                                    </select>
                                </div>
                                <button type="submit" name="submit_tafsir" class="btn btn-primary btn-sm">Submit Tafsir</button>
                            </form>
                            <?php endif; ?>
                            <button class="btn btn-secondary mt-10" onclick="document.getElementById('ayah_details_popup').classList.add('hidden')">Close</button>
                        </div>
                    </div>
                </section>
                <?php
                break;

            default:
                // Fallback to home page if an invalid page is requested
                redirect("index5.php?page=home");
                break;
        }
        ?>
    </main>

    <footer>
        <div class="container text-center">
            <p>© <?php echo date('Y'); ?> Quran Study Hub. All rights reserved. Developed by Yasin Ullah.</p>
        </div>
    </footer>

    <script>
        // Global variables for Quran Viewer
        let currentSurah = <?php echo $current_surah; ?>;
        let currentAyah = <?php echo $current_ayah; ?>;
        const surahData = <?php echo json_encode($surahs); ?>; // Surah names
        const totalAyahsInSurah = {}; // To store total ayahs per surah
        const loggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;

        // Fetch total ayahs per surah (simplified for sample data)
        // In a real app, this would be from DB or a comprehensive JSON/JS object
        // For now, hardcode for sample surahs for navigation logic
        totalAyahsInSurah[1] = 7; // Al-Fatiha
        totalAyahsInSurah[2] = 286; // Al-Baqarah
        totalAyahsInSurah[3] = 200; // Al-Imran
        totalAyahsInSurah[4] = 176; // An-Nisa


        document.addEventListener('DOMContentLoaded', () => {
            const quranContentArea = document.getElementById('quranContentArea');
            const ayahsDisplay = document.getElementById('ayahs_display');
            const currentAyahInfo = document.getElementById('currentAyahInfo');
            const prevAyahBtn = document.getElementById('prevAyahBtn');
            const nextAyahBtn = document.getElementById('nextAyahBtn');
            const surahLinks = document.querySelectorAll('.surah-list a');
            const bismillah = document.getElementById('bismillah');

            // Ayah Details Popup elements
            const ayahDetailsPopup = document.getElementById('ayah_details_popup');
            const popupAyahInfo = document.getElementById('popup_ayah_info');
            const popupHifzStatus = document.getElementById('popup_hifz_status');
            const hifzToggleLink = document.getElementById('hifz_toggle_link');
            const hifzStatusOptions = document.getElementById('hifz_status_options');
            const hifzStatusSelect = document.getElementById('hifz_status_select');
            const saveHifzStatusBtn = document.getElementById('save_hifz_status');
            const popupNotes = document.getElementById('popup_notes');
            const popupThemes = document.getElementById('popup_themes');
            const popupTafsirs = document.getElementById('popup_tafsirs');
            const addNoteForm = document.getElementById('add_note_form');
            const addTafsirForm = document.getElementById('add_tafsir_form');
            const noteSurahNumberInput = document.getElementById('note_surah_number');
            const noteAyahNumberInput = document.getElementById('note_ayah_number');
            const tafsirSurahNumberInput = document.getElementById('tafsir_surah_number');
            const tafsirAyahNumberInput = document.getElementById('tafsir_ayah_number');
            const tafsirWordIdInput = document.getElementById('tafsir_word_id');

            // Search elements
            const searchButton = document.getElementById('search_button');
            const searchTermInput = document.getElementById('search_term');
            const searchTypeSelect = document.getElementById('search_type');
            const searchSurahFilterSelect = document.getElementById('search_surah_filter');
            const searchResultsDiv = document.getElementById('search_results');
            const searchResultsList = document.getElementById('search_results_list');


            // Function to load Ayah content via AJAX
            async function loadAyah(surah, ayah) {
                if (surah < 1 || surah > 114 || ayah < 1 || ayah > (totalAyahsInSurah[surah] || 300)) { // Fallback for max ayah
                    console.warn("Invalid surah or ayah number:", surah, ayah);
                    return;
                }

                currentSurah = surah;
                currentAyah = ayah;

                // Update URL without reloading page
                history.pushState({ surah: currentSurah, ayah: currentAyah }, '', `?page=quran_viewer&surah=${currentSurah}&ayah=${currentAyah}`);

                // Update active surah in sidebar
                surahLinks.forEach(link => {
                    link.classList.remove('active');
                    if (parseInt(link.dataset.surah) === currentSurah) {
                        link.classList.add('active');
                        link.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });

                currentAyahInfo.textContent = `Surah: ${surahData[currentSurah]}, Ayah: ${currentAyah}`;
                ayahsDisplay.innerHTML = '<p class="text-center">Loading Ayah...</p>';
                bismillah.style.display = (surah === 1 && ayah === 1) || (surah !== 9 && ayah === 1) ? 'block' : 'none';

                try {
                    const response = await fetch(`index5.php?ajax=get_ayah_content&surah=${surah}&ayah=${ayah}`);
                    const data = await response.json();

                    if (data.ayah_words && data.ayah_words.length > 0) {
                        let ayahHtml = `<div class="ayah-container" data-surah="${surah}" data-ayah="${ayah}">`;
                        data.ayah_words.forEach(word => {
                            ayahHtml += `<span class="arabic-word" data-word-id="${word.word_id}" data-surah="${surah}" data-ayah="${ayah}" data-pos="${word.word_position}">
                                ${word.arabic_word}
                                <span class="word-meaning-popup">Loading meaning...</span>
                            </span>`;
                        });
                        ayahHtml += `<span class="ayah-number"> ${ayah}</span></div>`;
                        ayahsDisplay.innerHTML = ayahHtml;

                        // Apply highlights based on user data
                        let ayahContainer = ayahsDisplay.querySelector(`.ayah-container[data-surah="${surah}"][data-ayah="${ayah}"]`);
                        if (ayahContainer) {
                            if (data.hifz_status === 'memorized') {
                                ayahContainer.classList.add('ayah-highlight-hifz');
                            }
                            if (data.notes && data.notes.length > 0) {
                                ayahContainer.classList.add('ayah-highlight-notes');
                            }
                            if (data.themes && data.themes.length > 0) {
                                ayahContainer.classList.add('ayah-highlight-theme');
                            }
                        }

                        // Attach hover listeners for word meanings
                        document.querySelectorAll('.arabic-word').forEach(wordSpan => {
                            wordSpan.addEventListener('mouseenter', async (event) => {
                                const wordId = event.target.dataset.wordId;
                                const popup = event.target.querySelector('.word-meaning-popup');
                                if (popup && wordId) {
                                    try {
                                        const meaningResponse = await fetch(`index5.php?ajax=get_word_meaning&word_id=${wordId}`);
                                        const meaningData = await meaningResponse.json();
                                        popup.innerHTML = `<strong>Urdu:</strong> ${meaningData.urdu}<br><strong>English:</strong> ${meaningData.english}`;
                                    } catch (error) {
                                        console.error('Error fetching word meaning:', error);
                                        popup.textContent = 'Error loading meaning.';
                                    }
                                }
                            });

                            // Attach click listener for Ayah details popup
                            wordSpan.addEventListener('click', (event) => {
                                showAyahDetailsPopup(surah, ayah, event.target.dataset.wordId);
                            });
                        });
                    } else {
                        ayahsDisplay.innerHTML = `<p class="text-center">No content found for Surah ${surah}, Ayah ${ayah}.</p>`;
                    }
                } catch (error) {
                    console.error('Error loading ayah:', error);
                    ayahsDisplay.innerHTML = `<p class="text-center alert alert-error">Failed to load Ayah. Please try again.</p>`;
                }
            }

            // Navigation functions
            function navigateAyah(direction) {
                let nextAyah = currentAyah + direction;
                let nextSurah = currentSurah;

                if (direction === 1) { // Next Ayah
                    if (nextAyah > (totalAyahsInSurah[currentSurah] || 300)) { // Go to next surah
                        nextSurah++;
                        nextAyah = 1;
                    }
                } else { // Previous Ayah
                    if (nextAyah < 1) { // Go to previous surah
                        nextSurah--;
                        // Need to fetch max ayah for previous surah, or use a lookup table
                        // For sample, we'll just go to a default high number, or prevent if surah 1
                        nextAyah = totalAyahsInSurah[nextSurah] || 300; // Fallback
                    }
                }

                if (nextSurah < 1 || nextSurah > 114) { // Prevent going out of bounds
                    alert("No more surahs in this direction.");
                    return;
                }
                loadAyah(nextSurah, nextAyah);
            }

            // Event Listeners for Navigation
            prevAyahBtn.addEventListener('click', () => navigateAyah(-1));
            nextAyahBtn.addEventListener('click', () => navigateAyah(1));

            surahLinks.forEach(link => {
                link.addEventListener('click', (event) => {
                    const surahNum = parseInt(event.target.dataset.surah);
                    loadAyah(surahNum, 1); // Load first ayah of selected surah
                });
            });

            // Ayah Details Popup Logic
            async function showAyahDetailsPopup(surah, ayah, wordId = null) {
                popupAyahInfo.textContent = `Details for Surah ${surahData[surah]}, Ayah ${ayah}`;
                ayahDetailsPopup.classList.remove('hidden');
                ayahDetailsPopup.scrollTop = 0; // Scroll to top on open

                // Populate forms
                noteSurahNumberInput.value = surah;
                noteAyahNumberInput.value = ayah;
                tafsirSurahNumberInput.value = surah;
                tafsirAyahNumberInput.value = ayah;
                tafsirWordIdInput.value = wordId; // Set word_id if word-specific tafsir

                // Fetch and display details
                try {
                    const response = await fetch(`index5.php?ajax=get_ayah_content&surah=${surah}&ayah=${ayah}`);
                    const data = await response.json();

                    // Hifz Status
                    popupHifzStatus.textContent = data.hifz_status.replace('_', ' ');
                    hifzStatusSelect.value = data.hifz_status;
                    hifzStatusOptions.classList.add('hidden'); // Hide options by default

                    // Notes
                    popupNotes.innerHTML = data.notes.length > 0 ?
                        data.notes.map(note => `<p>- ${note}</p>`).join('') :
                        '<p>No personal notes for this ayah.</p>';

                    // Themes
                    popupThemes.innerHTML = data.themes.length > 0 ?
                        data.themes.map(theme => `<span class="theme-tag">${theme}</span>`).join('') :
                        '<p>No themes linked to this ayah.</p>';

                    // Tafsirs
                    if (data.tafsirs && data.tafsirs.length > 0) {
                        popupTafsirs.innerHTML = data.tafsirs.map(t => `
                            <div style="border: 1px solid #eee; padding: 10px; margin-bottom: 10px; border-radius: 5px;">
                                <p><strong>${t.word_arabic ? `Word (${t.word_arabic}): ` : ''}</strong> ${t.tafsir_text}</p>
                                <small><em>By ${t.username} (${t.language})</em></small>
                            </div>
                        `).join('');
                    } else {
                        popupTafsirs.innerHTML = '<p>No approved tafsirs for this ayah.</p>';
                    }

                } catch (error) {
                    console.error('Error fetching ayah details:', error);
                    popupAyahInfo.textContent = 'Error loading details.';
                }
            }

            // Hifz status toggle
            hifzToggleLink.addEventListener('click', () => {
                if (!loggedIn) {
                    alert("Please log in to track your hifz progress.");
                    return;
                }
                hifzStatusOptions.classList.toggle('hidden');
            });

            saveHifzStatusBtn.addEventListener('click', async () => {
                const surah = currentSurah;
                const ayah = currentAyah;
                const status = hifzStatusSelect.value;

                const formData = new FormData();
                formData.append('action', 'update_hifz_status');
                formData.append('update_hifz_status', '1'); // Trigger PHP handler
                formData.append('surah_number', surah);
                formData.append('ayah_number', ayah);
                formData.append('hifz_status', status);

                try {
                    const response = await fetch('index5.php', {
                        method: 'POST',
                        body: formData
                    });
                    const text = await response.text(); // Get raw text to check for messages
                    console.log(text); // Log server response for debugging
                    alert("Hifz status updated!");
                    ayahDetailsPopup.classList.add('hidden'); // Close popup
                    loadAyah(currentSurah, currentAyah); // Reload ayah to show highlight
                } catch (error) {
                    console.error('Error updating hifz status:', error);
                    alert("Failed to update hifz status.");
                }
            });

            // Add Note Form Submission
            if (addNoteForm) {
                addNoteForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (!loggedIn) {
                        alert("Please log in to add notes.");
                        return;
                    }
                    const formData = new FormData(addNoteForm);
                    formData.append('action', 'add_note');
                    formData.append('add_note', '1'); // Trigger PHP handler

                    try {
                        const response = await fetch('index5.php', {
                            method: 'POST',
                            body: formData
                        });
                        const text = await response.text();
                        console.log(text);
                        alert("Note added successfully!");
                        addNoteForm.reset();
                        ayahDetailsPopup.classList.add('hidden');
                        loadAyah(currentSurah, currentAyah);
                    } catch (error) {
                        console.error('Error adding note:', error);
                        alert("Failed to add note.");
                    }
                });
            }

            // Add Tafsir Form Submission
            if (addTafsirForm) {
                addTafsirForm.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (!loggedIn) {
                        alert("Please log in to contribute tafsir.");
                        return;
                    }
                    const formData = new FormData(addTafsirForm);
                    formData.append('action', 'submit_tafsir');
                    formData.append('submit_tafsir', '1'); // Trigger PHP handler

                    try {
                        const response = await fetch('index5.php', {
                            method: 'POST',
                            body: formData
                        });
                        const text = await response.text();
                        console.log(text);
                        alert("Tafsir submitted for approval!");
                        addTafsirForm.reset();
                        ayahDetailsPopup.classList.add('hidden');
                        loadAyah(currentSurah, currentAyah); // Reload to potentially show new approved tafsir
                    } catch (error) {
                        console.error('Error submitting tafsir:', error);
                        alert("Failed to submit tafsir.");
                    }
                });
            }

            // Search Functionality
            searchButton.addEventListener('click', async () => {
                const searchTerm = searchTermInput.value.trim();
                const searchType = searchTypeSelect.value;
                const surahFilter = searchSurahFilterSelect.value;

                if (!searchTerm && surahFilter == 0) {
                    alert("Please enter a search term or select a surah filter.");
                    return;
                }

                searchResultsList.innerHTML = '<p>Searching...</p>';
                searchResultsDiv.classList.remove('hidden');

                try {
                    const response = await fetch(`index5.php?ajax=search_quran&term=${encodeURIComponent(searchTerm)}&type=${searchType}&surah_filter=${surahFilter}`);
                    const results = await response.json();

                    if (results.length > 0) {
                        searchResultsList.innerHTML = '';
                        results.forEach(result => {
                            const li = document.createElement('li');
                            const link = document.createElement('a');
                            link.href = `javascript:void(0);`;
                            link.textContent = `Surah ${result.surah}: Ayah ${result.ayah} (${result.type})`;
                            link.addEventListener('click', () => {
                                loadAyah(result.surah, result.ayah);
                                searchResultsDiv.classList.add('hidden');
                            });
                            li.appendChild(link);
                            searchResultsList.appendChild(li);
                        });
                    } else {
                        searchResultsList.innerHTML = '<p>No results found.</p>';
                    }
                } catch (error) {
                    console.error('Error during search:', error);
                    searchResultsList.innerHTML = '<p class="alert alert-error">Error performing search.</p>';
                }
            });


            // Initial load of the current ayah on page load
            if (current_page === 'quran_viewer') {
                loadAyah(currentSurah, currentAyah);
            }
        });

        // Simple function to display messages from PHP (for non-AJAX actions)
        <?php if ($message): ?>
            alert("<?php echo addslashes($message); ?>");
        <?php endif; ?>

        // Prevent form resubmission on page refresh for POST requests
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>
<?php
// Close database connection
$conn->close();
?>