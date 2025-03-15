<?php
// Φόρτωση ρυθμίσεων και σύνδεσης βάσης
require_once '../config/config.php';
require_once '../includes/db_connection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Session timeout (30 λεπτά)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
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
    session_unset();
    session_destroy();
    header("Location: " . BASE_URL . "/public/login.php?error=ip_mismatch");
    exit();
}
// CSRF Protection: Δημιουργία ή έλεγχος token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$error = '';
$success = '';
// Έλεγχος αν έγινε υποβολή της φόρμας
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Έλεγχος CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Μη έγκυρο αίτημα!";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        // Ερώτημα για ανάκτηση χρήστη βάσει email
        $stmt = $mysqli->prepare("SELECT id, fullname, password, role, email_verified FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        // Αν δεν βρεθεί χρήστης, επιστρέφουμε μήνυμα λάθους
        if ($stmt->num_rows === 0) {
            $error = "Λάθος email ή κωδικός!";
        } else {
            // Δέσμευση αποτελεσμάτων
            $stmt->bind_result($id, $fullname, $hashed_password, $role, $verified);
            $stmt->fetch();
            // Έλεγχος αν ο χρήστης έχει επιβεβαιώσει το email του
            if ($verified == 0) {
                $error = "Πρέπει να επιβεβαιώσετε το email σας πρώτα!";
                // Προσθήκη συνδέσμου επαναποστολής email επαλήθευσης
                $resend_link = '<a href="' . BASE_URL . '/public/resend_verification.php?email=' . urlencode($email) . '">Αποστολή ξανά</a>';
                $error .= " " . $resend_link;
            } elseif (password_verify($password, $hashed_password)) {
                // Δημιουργία νέου session token
                $session_token = bin2hex(random_bytes(32));
                // Ενημέρωση του χρήστη με το νέο session token
                $update_stmt = $mysqli->prepare("UPDATE users SET session_token = ? WHERE id = ?");
                $update_stmt->bind_param("si", $session_token, $id);
                $update_stmt->execute();
                // Αποθήκευση δεδομένων συνεδρίας
                $_SESSION['user_id'] = $id;
                $_SESSION['fullname'] = $fullname;
                $_SESSION['role'] = $role;
                $_SESSION['session_token'] = $session_token;
                // Αποθήκευση της ώρας σύνδεσης
                $_SESSION['login_time'] = time();
                // Προαιρετική λειτουργία "Να με θυμάσαι"
                if (isset($_POST['remember_me']) && $_POST['remember_me'] == 1) {
                    $remember_token = bin2hex(random_bytes(32));
                    
                    // Αποθήκευση του token στη βάση
                    $remember_stmt = $mysqli->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                    $remember_stmt->bind_param("si", $remember_token, $id);
                    $remember_stmt->execute();
                    
                    // Αποθήκευση του token σε cookie που λήγει σε 30 ημέρες
                    setcookie('remember_token', $remember_token, time() + (86400 * 30), '/', '', true, true);
                }
                // Ανακατεύθυνση βάσει ρόλου
                switch ($role) {
                    case 'admin':
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                        break;
                    case 'school':
                        header("Location: " . BASE_URL . "/schools/school-dashboard.php");
                        break;
                    case 'student':
                        header("Location: " . BASE_URL . "/students/students-dashboard.php");
                        break;
                    case 'user':
                        header("Location: " . BASE_URL . "/users/dashboard.php");
                        break;
                    default:
                        header("Location: " . BASE_URL . "/public/index.php");
                        break;
                }
                exit();
            } else {
                $error = "Λάθος email ή κωδικός!";
            }
        }
        $stmt->close();
    }
}
// Έλεγχος για επιτυχή επαλήθευση email
if (isset($_GET['success']) && $_GET['success'] === 'verified') {
    $success = "Το email σας επαληθεύτηκε επιτυχώς! Μπορείτε να συνδεθείτε.";
}
// Έλεγχος για μήνυμα επιτυχίας εγγραφής
if (isset($_GET['success']) && $_GET['success'] === 'registered') {
    $success = "Η εγγραφή σας ολοκληρώθηκε επιτυχώς! Μπορείτε να συνδεθείτε.";
}
// Τίτλος σελίδας
$page_title = "Σύνδεση";
$load_auth_js = true;
// Φόρτωση του header
require_once '../includes/header.php';
?>
<div class="auth-container">
    <div class="auth-box">
        <div class="auth-header">
            <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest Logo" class="auth-logo">
            <h1>Σύνδεση</h1>
            <p>Συνδεθείτε για να αποκτήσετε πρόσβαση στην πλατφόρμα DriveTest</p>
        </div>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $success ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= $error ?>
        </div>
        <?php endif; ?>
        
        <form action="<?= BASE_URL ?>/public/login.php" method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-group">
                    <span class="input-icon"><i class="fas fa-envelope"></i></span>
                    <input type="email" id="email" name="email" class="form-control" placeholder="Εισάγετε το email σας" required autofocus value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Κωδικός</label>
                <div class="input-group password-visibility">
                    <span class="input-icon"><i class="fas fa-lock"></i></span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Εισάγετε τον κωδικό σας" required>
                    <span class="password-toggle">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            
            <div class="form-options">
                <div class="remember-me">
                    <input type="checkbox" id="remember_me" name="remember_me" value="1">
                    <label for="remember_me">Να με θυμάσαι</label>
                </div>
                
                <a href="<?= BASE_URL ?>/public/recover_password.php" class="forgot-password">Ξέχασα τον κωδικό μου</a>
            </div>
      
            <button type="submit" class="btn-primary btn-block">
                <i class="fas fa-sign-in-alt"></i> Σύνδεση
            </button>
           
        </form>
        
        <div class="auth-separator">
            <span>ή</span>
        </div>
              
        <div class="auth-footer">
            <p>Δεν έχετε λογαριασμό; 
                <a href="<?= BASE_URL ?>/public/register_user.php">Εγγραφή χρήστη</a> ή 
                <a href="<?= BASE_URL ?>/public/register_school.php">Εγγραφή σχολής</a>
            </p>
            
            <p>
                <a href="<?= BASE_URL ?>/public/guest_test.php" class="guest-link">
                    <i class="fas fa-user-clock"></i> Δοκιμή ως επισκέπτης
                </a>
            </p>
        </div>
    </div>
</div>
<?php
// Φόρτωση του footer
require_once '../includes/footer.php';
?>