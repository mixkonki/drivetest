<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ελέγχουμε αν υπάρχει το verification token στη διεύθυνση URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Αναζήτηση χρήστη με το συγκεκριμένο token
    $stmt = $mysqli->prepare("SELECT id FROM users WHERE verification_token = ? AND verified = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id);
        $stmt->fetch();

        // Δημιουργία session token
        $session_token = bin2hex(random_bytes(32));

        // Ενημέρωση του χρήστη ως επιβεβαιωμένου και αποθήκευση του session token
        $update_stmt = $mysqli->prepare("UPDATE users SET verified = 1, session_token = NULL WHERE id = ?");
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();

        // Ανακατεύθυνση στη σελίδα σύνδεσης με μήνυμα επιβεβαίωσης
        header("Location: login.php?success=verified");
        exit();
    } else {
        header("Location: login.php?error=invalid_token");
        exit();
    }
} else {
    // Αν δεν υπάρχει token, επιστρέφουμε στη σελίδα σύνδεσης
    header("Location: login.php");
    exit();
}
?>
