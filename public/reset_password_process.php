<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';

$error = "";
$success = "";

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $stmt = $mysqli->prepare("SELECT id, email, verification_token_expiry FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $expiry = $user['verification_token_expiry'];
        if (strtotime($expiry) < time()) {
            $error = "Ο σύνδεσμος επαναφοράς έχει λήξει. Παρακαλώ ζητήστε νέο.";
        } else {
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $password = trim($_POST['password']);
                $confirm_password = trim($_POST['confirm_password']);

                if ($password !== $confirm_password) {
                    $error = "Τα συνθηματικά δεν ταιριάζουν!";
                } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/', $password)) {
                    $error = "Το συνθηματικό δεν πληροί τα κριτήρια ασφαλείας!";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $update_stmt = $mysqli->prepare("UPDATE users SET password = ?, verification_token = NULL, verification_token_expiry = NULL WHERE id = ?");
                    $update_stmt->bind_param("si", $hashed_password, $user['id']);
                    if ($update_stmt->execute()) {
                        $success = "Ο κωδικός σας επαναφέρθηκε με επιτυχία! <a href='login.php'>Σύνδεση</a>";
                    } else {
                        $error = "Σφάλμα κατά την επαναφορά κωδικού.";
                    }
                    $update_stmt->close();
                }
            }
        }
    } else {
        $error = "Μη έγκυρος σύνδεσμος επαναφοράς.";
    }
    $stmt->close();
} else {
    header("Location: recover_password.php");
    exit();
}
?>

<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/reset_password.css">

<main class="reset-container">
    <div class="reset-form">
        <div class="logo">
            <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest">
        </div>
        <h1>Επαναφορά Κωδικού</h1>
        <p>Δημιουργήστε έναν νέο κωδικό για τον λογαριασμό σας.</p>
        <form action="reset_password_process.php?token=<?= htmlspecialchars($_GET['token']) ?>" method="post">
            <?php if ($error): ?>
                <p class="error-message"><?= $error ?></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success-message"><?= $success ?></p>
            <?php endif; ?>

            <div class="password-visibility">
                <input type="password" name="password" placeholder="Νέο Συνθηματικό" required id="password">
                <span class="password-toggle" onclick="togglePassword('password')">
                    <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password">
                </span>
            </div>

            <div class="password-visibility">
                <input type="password" name="confirm_password" placeholder="Επιβεβαίωση Συνθηματικού" required id="confirm_password">
                <span class="password-toggle" onclick="togglePassword('confirm_password')">
                    <img src="<?= BASE_URL ?>/assets/images/eye.png" alt="Show/Hide Password">
                </span>
            </div>

            <p class="text-pass">Το συνθηματικό πρέπει να περιέχει:</p>
            <ul class="password-hint">
                <li id="hint-length">❌ 8-16 χαρακτήρες</li>
                <li id="hint-uppercase">❌ 1 κεφαλαίο γράμμα</li>
                <li id="hint-number">❌ 1 αριθμός</li>
                <li id="hint-special">❌ 1 ειδικός χαρακτήρας</li>
            </ul>

            <button type="submit" class="btn-primary">Αλλαγή Κωδικού</button>
        </form>
    </div>
</main>

<script src="<?= BASE_URL ?>/assets/js/reset_password.js"></script>