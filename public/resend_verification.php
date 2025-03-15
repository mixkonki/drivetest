<?php
session_start();
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';

// Έλεγχος αν η φόρμα υποβλήθηκε
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Έλεγχος αν το email είναι έγκυρο
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Αναζήτηση χρήστη με βάση το email
        $stmt = $mysqli->prepare("SELECT id, verification_token, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id, $verification_token, $email_verified);
            $stmt->fetch();
            
            // Ελέγχουμε αν έχει ήδη επιβεβαιωθεί
            if ($email_verified == 1) {
                // Ανακατεύθυνση στη σελίδα σύνδεσης με μήνυμα
                header("Location: " . BASE_URL . "/public/login.php?success=already_verified");
                exit();
            }
            
            // Αν δεν υπάρχει token, δημιουργούμε νέο
            if (empty($verification_token)) {
                $verification_token = bin2hex(random_bytes(32));
                
                // Ενημέρωση του token στη βάση
                $update_stmt = $mysqli->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
                $update_stmt->bind_param("si", $verification_token, $user_id);
                $update_stmt->execute();
                $update_stmt->close();
            }
            
            // Προσδιορισμός της γλώσσας
            $language = isset($_SESSION['language']) ? $_SESSION['language'] : 'el';
            
            // Αποστολή του verification email
            $sent = send_verification_email($email, $verification_token, $language);
            
            if ($sent) {
                // Επιτυχής αποστολή
                header("Location: " . BASE_URL . "/public/email_verification_notice.php?email=" . urlencode($email) . "&resend=success");
            } else {
                // Αποτυχία αποστολής
                header("Location: " . BASE_URL . "/public/email_verification_notice.php?email=" . urlencode($email) . "&resend=email_error");
            }
        } else {
            // Δεν βρέθηκε ο χρήστης
            header("Location: " . BASE_URL . "/public/email_verification_notice.php?email=" . urlencode($email) . "&resend=error");
        }
        
        $stmt->close();
    } else {
        // Μη έγκυρο email
        header("Location: " . BASE_URL . "/public/login.php?error=invalid_email");
    }
} else {
    // Άμεση πρόσβαση στη σελίδα χωρίς υποβολή φόρμας
    header("Location: " . BASE_URL . "/public/login.php");
}
exit();
?>