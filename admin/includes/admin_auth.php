<?php
// Έλεγχος αν το session έχει ήδη ξεκινήσει, αν όχι το ξεκινάμε
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Φόρτωση ρυθμίσεων (αν δεν έχει ήδη φορτωθεί)
if (!defined('BASE_URL')) {
    $config = require_once '../../config/config.php';
    define('BASE_URL', $config['base_url']);
    // Η σταθερά DEBUG ορίζεται στο config.php, οπότε δεν χρειάζεται εδώ
}

// Λογιστική για logs (αν DEBUG είναι ενεργοποιημένο)
if (DEBUG) {
    $logFile = '../../admin/test/debug_log.txt'; // Διαδρομή προς το υπάρχον log file
    $logMessage = "[" . date("Y-m-d H:i:s") . "] [INFO] Έλεγχος πρόσβασης admin από IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND); // @ για να αποφύγουμε σφάλματα αν δεν υπάρχει δικαίωμα
}

// Session timeout (30 λεπτά)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    if (DEBUG) {
        @file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] [WARNING] Session timeout για χρήστη ID: " . ($_SESSION['user_id'] ?? 'Ανώνυμος') . "\n", FILE_APPEND);
    }
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/public/login.php?error=timeout");
    exit();
}
$_SESSION['last_activity'] = time();

// Έλεγχος IP (προαιρετικό, για επιπλέον ασφάλεια)
if (!isset($_SESSION['ip'])) {
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
} elseif ($_SESSION['ip'] !== $_SERVER['REMOTE_ADDR']) {
    if (DEBUG) {
        @file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] [ERROR] IP mismatch για χρήστη ID: " . ($_SESSION['user_id'] ?? 'Ανώνυμος') . "\n", FILE_APPEND);
    }
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/public/login.php?error=ip_mismatch");
    exit();
}

// Έλεγχος αν ο χρήστης είναι συνδεδεμένος και είναι admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    if (DEBUG) {
        @file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] [WARNING] Μη εξουσιοδοτημένη πρόσβαση από IP: " . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);
    }
    header("Location: " . BASE_URL . "/public/login.php?error=unauthorized");
    exit();
}

// Ενημέρωση τελευταίας δραστηριότητας για το session
$_SESSION['last_activity'] = time();
?>