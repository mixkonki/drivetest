<?php
/**
 * DriveTest - Ανακατεύθυνση add_question.php
 * 
 * Αυτό το αρχείο πλέον ανακατευθύνει στη σελίδα manage_questions.php
 * όπου η προσθήκη ερωτήσεων χειρίζεται μέσω JavaScript.
 */

require_once '../../config/config.php';
require_once '../includes/admin_auth.php';

// Καταγραφή αν το debugging είναι ενεργοποιημένο
if (defined('DEBUG') && DEBUG) {
    $logFile = BASE_PATH . '/admin/test/debug_log.txt';
    $logMessage = "[" . date("Y-m-d H:i:s") . "] Ανακατεύθυνση από add_question.php στο manage_questions.php\n";
    @file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// Ανακατευθύνουμε στο manage_questions.php με παράμετρο action=add
// που θα χρησιμοποιηθεί για να ανοίξει αυτόματα τη φόρμα προσθήκης
header('Location: ' . BASE_URL . '/admin/test/manage_questions.php?action=add');
exit();
?>