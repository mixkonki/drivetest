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

// Έλεγχος αν έγινε υποβολή της φόρμας
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Έλεγχος CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Μη έγκυρο αίτημα!";
    } else {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);

        // Ερώτημα για ανάκτηση χρήστη βάσει email
        $stmt = $mysqli->prepare("SELECT id, fullname, password, role, verified FROM users WHERE email = ?");
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

                // Ανακατεύθυνση βάσει ρόλου
                switch ($role) {
                    case 'admin':
                        header("Location: " . BASE_URL . "/admin/dashboard.php");
                        break;
                    case 'school':
                        header("Location: " . BASE_URL . "/schools/dashboard.php");
                        break;
                    case 'student':
                        header("Location: " . BASE_URL . "/students/dashboard.php");
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

// Φόρτωση του header
require_once('../includes/header.php');
?>

<main class="container" role="main">
    <h1 class="page-title" aria-label="Σύνδεση Χρήστη">Σύνδεση Χρήστη</h1>

    <!-- Εμφάνιση μηνύματος λάθους αν υπάρχει -->
    <?php if (isset($error)) : ?>
        <p class="error-message" role="alert"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form action="login.php" method="post" class="form-container" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="form-group">
            <label for="email" class="sr-only">Email:</label>
            <input type="email" id="email" name="email" required aria-required="true" placeholder="Email">
        </div>

        <div class="form-group">
            <label for="password" class="sr-only">Κωδικός:</label>
            <input type="password" id="password" name="password" required aria-required="true" placeholder="Κωδικός">
        </div>

        <button type="submit" class="login-btn" aria-label="Σύνδεση">Σύνδεση</button>
    </form>
</main>

<?php
// Φόρτωση του footer
require_once('../includes/footer.php');
?>