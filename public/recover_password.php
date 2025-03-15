<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';

// Έλεγχος αν η σελίδα ανακατευθύνθηκε με μήνυμα σφάλματος/επιτυχίας
$error = isset($_GET['error']) ? $_GET['error'] : "";
$success = isset($_GET['success']) ? $_GET['success'] : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = __('email_required');
    } else {
        $stmt = $mysqli->prepare("SELECT id, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $verification_token = bin2hex(random_bytes(32));
            $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Λήξη σε 1 ώρα
            
            // Ενημέρωση και του πεδίου verification_token_expiry
            $update_stmt = $mysqli->prepare("UPDATE users SET verification_token = ?, verification_token_expiry = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $verification_token, $token_expiry, $user['id']);
            
            if ($update_stmt->execute()) {
                // Αποστολή email επαναφοράς κωδικού
                if (send_reset_password_email($user['email'], $verification_token, $_SESSION['language'])) {
                    $success = __('reset_email_sent', ['email' => htmlspecialchars($email)]);
                } else {
                    $error = __('email_send_error');
                }
            } else {
                $error = __('reset_error');
            }
            $update_stmt->close();
        } else {
            $error = __('email_not_found');
        }
        $stmt->close();
    }
    
    // Ανακατεύθυνση με μηνύματα επιτυχίας/σφάλματος
    if (!empty($success)) {
        header("Location: " . BASE_URL . "/public/recover_password.php?success=" . urlencode($success));
        exit();
    } elseif (!empty($error)) {
        header("Location: " . BASE_URL . "/public/recover_password.php?error=" . urlencode($error));
        exit();
    }
}

// Ορισμός μεταβλητών για το template
$page_title = __('reset_password_title');
$additional_css = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/recover_password.css">';

// Φόρτωση του header
require_once '../includes/header.php';
?>

<div class="container">
    <div class="recover-container">
        <div class="recover-form">
            <div class="card shadow">
                <div class="card-body text-center">
                    <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest" class="img-fluid mb-4" style="max-width: 120px;">
                    <h2 class="mb-3"><?= __('reset_password_title') ?></h2>
                    <p class="text-muted mb-4"><?= __('reset_password_subtitle') ?></p>
                    
                    <?php 
                    if (function_exists('display_template')) {
                        display_template('form_recover_password', [
                            'error' => $error,
                            'success' => $success,
                            'language' => $_SESSION['language'],
                            'translations' => $translations ?? []
                        ]);
                    } else {
                        // Fallback αν δεν υπάρχει η συνάρτηση templates
                        require_once BASE_PATH . '/templates/form_recover_password.php';
                    }
                    ?>
                    
                    <p class="mt-3"><?= __('login_link') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Φόρτωση του footer
require_once '../includes/footer.php';
?>