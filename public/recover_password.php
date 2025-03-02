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

    $stmt = $mysqli->prepare("SELECT id, email, created_at FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $verification_token = bin2hex(random_bytes(32));
        $token_created_at = date('Y-m-d H:i:s'); // Χρησιμοποιούμε το created_at για να ελέγξουμε τη λήξη

        $update_stmt = $mysqli->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
        $update_stmt->bind_param("si", $verification_token, $user['id']);
        
        if ($update_stmt->execute()) {
            send_reset_password_email($user['email'], $verification_token);
            $success = isset($translations['reset_email_sent']) ? $translations['reset_email_sent'] : "Ένα email επαναφοράς κωδικού έχει σταλεί στο " . htmlspecialchars($email) . ".";
        } else {
            $error = isset($translations['reset_error']) ? $translations['reset_error'] : "Σφάλμα κατά την επαναφορά. Προσπαθήστε ξανά!";
        }
        $update_stmt->close();
    } else {
        $error = isset($translations['email_not_found']) ? $translations['email_not_found'] : "Το email δεν βρέθηκε. Ελέγξτε το και προσπαθήστε ξανά.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($translations['reset_password_title']) ? $translations['reset_password_title'] : 'Επαναφορά Κωδικού - DriveTest' ?></title>
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">
    <style>
        .container {
            min-height: calc(100vh - 200px); /* Λαμβάνει υπόψη header/footer */
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .card {
            width: 100%;
            max-width: 500px;
            border: none;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-body {
            padding: 2rem;
        }
        .text-center.mb-4 img {
            max-width: 150px;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .form-label {
            font-weight: 500;
        }
        .btn-primary {
            background: #d9534f;
            border: none;
        }
        .btn-primary:hover {
            background: #c9302c;
        }
    </style>
</head>
<body>
    <?php require_once '../includes/header.php'; ?>

    <main class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <img src="<?= BASE_URL ?>/assets/images/drivetest.png" alt="DriveTest" class="img-fluid" style="max-width: 120px;">
                        </div>
                        <h2 class="text-center mb-3"><?= isset($translations['reset_password_title']) ? $translations['reset_password_title'] : 'Επαναφορά Κωδικού' ?></h2>
                        <p class="text-center text-muted mb-4"><?= isset($translations['reset_password_subtitle']) ? $translations['reset_password_subtitle'] : 'Εισαγάγετε το email σας για να λάβετε σύνδεσμο επαναφοράς.' ?></p>
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
                        <p class="text-center mt-3"><?= isset($translations['login_link']) ? $translations['login_link'] : 'Θυμηθήκατε τον κωδικό σας? <a href="' . BASE_URL . '/public/login.php?lang=' . $language . '">Συνδεθείτε</a>' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>