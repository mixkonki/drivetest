<?php
// Αρχείο: config/init.php - Αρχικοποιεί την εφαρμογή και τις βασικές λειτουργίες
session_start();

// Ρυθμίσεις βάσης δεδομένων
$host = 'localhost';
$db = 'drivetest';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// Σύνδεση με τη βάση δεδομένων
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Σφάλμα σύνδεσης: ' . $e->getMessage());
}

// Ορισμός βασικών σταθερών
define('BASE_URL', 'http://localhost/drivetest');
define('BASE_PATH', '/drivetest');
define('APP_NAME', 'DriveTest');
define('APP_VERSION', '1.0.0');

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Ανακατεύθυνση σε συγκεκριμένη διεύθυνση
function redirect($location) {
    header("Location: $location");
    exit;
}

// Ορισμός flash message για εμφάνιση στον χρήστη
function setFlashMessage($type, $message) {
    $_SESSION['flash_messages'][$type] = $message;
}

// Ανάκτηση και αφαίρεση των flash messages
function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

// Έλεγχος αν ο χρήστης έχει συγκεκριμένο ρόλο
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Έλεγχος αν ο χρήστης έχει ενεργή συνδρομή στη συγκεκριμένη κατηγορία
function hasActiveSubscription($categoryId) {
    global $pdo;
    if (!isLoggedIn()) return false;
    
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM subscriptions 
        WHERE user_id = ? AND status = 'active' 
          AND JSON_CONTAINS(categories, ?) 
          AND expiry_date >= CURDATE()
    ");
    $stmt->execute([$userId, json_encode($categoryId)]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

// Καταγραφή σφάλματος
function logError($message, $file = null, $line = null) {
    $logFile = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if ($file) {
        $logMessage .= " in $file";
        if ($line) {
            $logMessage .= " on line $line";
        }
    }
    
    error_log($logMessage . PHP_EOL, 3, $logFile);
}
?>