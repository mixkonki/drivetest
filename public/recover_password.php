<?php
session_start();
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';

// Φόρτωση γλώσσης
$language = isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'al', 'ru', 'en', 'de']) ? $_GET['lang'] : 'el';
$translationsPath = dirname(__DIR__) . '/languages/';
$translations = require $translationsPath . "{$language}.php";

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Παρακαλώ εισάγετε το email σας.";
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
                if (send_reset_password_email($user['email'], $verification_token, $language)) {
                    $success = isset($translations['reset_email_sent']) ? $translations['reset_email_sent'] : "Ένα email επαναφοράς κωδικού έχει σταλεί στο " . htmlspecialchars($email) . ".";
                } else {
                    $error = "Σφάλμα κατά την αποστολή email. Παρακαλώ προσπαθήστε ξανά.";
                }
            } else {
                $error = isset($translations['reset_error']) ? $translations['reset_error'] : "Σφάλμα κατά την επαναφορά. Προσπαθήστε ξανά!";
            }
            $update_stmt->close();
        } else {
            $error = isset($translations['email_not_found']) ? $translations['email_not_found'] : "Το email δεν βρέθηκε. Ελέγξτε το και προσπαθήστε ξανά.";
        }
        $stmt->close();
    }
}
// Φόρτωση του header
require_once '../includes/header.php';
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($translations['reset_password_title']) ? $translations['reset_password_title'] : 'Επαναφορά Κωδικού - DriveTest' ?></title>
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/recover_password.css">
</head>
<body>
    <main class="container">
        <div class="recover-container">
            <div class="recover-form">
                <div class="card shadow">
                    <div class="card-body text-center">
                        <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest" class="img-fluid" style="max-width: 120px;">
                        <h2 class="mb-3"><?= isset($translations['reset_password_title']) ? $translations['reset_password_title'] : 'Επαναφορά Κωδικού' ?></h2>
                        <p class="text-muted mb-4"><?= isset($translations['reset_password_subtitle']) ? $translations['reset_password_subtitle'] : 'Εισαγάγετε το email σας για να λάβετε σύνδεσμο επαναφοράς.' ?></p>
                        <form action="recover_password.php?lang=<?= $language ?>" method="post" class="needs-validation" novalidate>
                            <?php if ($error): ?>
                                <div class="alert alert-danger" role="alert"><?= $error ?></div>
                            <?php endif; ?>
                            <?php if ($success): ?>
                                <div class="alert alert-success" role="alert"><?= $success ?></div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="email" class="form-label"><?= isset($translations['email']) ? $translations['email'] : 'Email' ?></label>
                                <input type="email" name="email" id="email" class="form-control" placeholder="<?= isset($translations['email_placeholder']) ? $translations['email_placeholder'] : 'Email' ?>" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100"><?= isset($translations['reset_button']) ? $translations['reset_button'] : 'Αποστολή Συνδέσμου' ?></button>
                        </form>
                        <p class="mt-3"><?= isset($translations['login_link']) ? $translations['login_link'] : 'Θυμηθήκατε τον κωδικό σας; <a href="' . BASE_URL . '/public/login.php?lang=' . $language . '">Συνδεθείτε</a>' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
</body>
</html>
