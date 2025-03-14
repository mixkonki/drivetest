<?php
require_once '../config/config.php';
require_once '../includes/db_connection.php';
require_once '../includes/mailer.php';

// Έλεγχος αν τα βοηθητικά αρχεία είναι διαθέσιμα
$use_language_helper = function_exists('__') && function_exists('load_language');
$use_template_helper = function_exists('display_template');

// Φόρτωση γλώσσας με παραδοσιακό τρόπο αν χρειάζεται
if (!$use_language_helper) {
    $language = isset($_GET['lang']) && in_array($_GET['lang'], ['el', 'al', 'ru', 'en', 'de']) ? $_GET['lang'] : 'el';
    $translationsPath = dirname(__DIR__) . '/languages/';
    $translations = require $translationsPath . "{$language}.php";
} else {
    $language = $_SESSION['language'];
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password !== $confirm_password) {
        $error = $use_language_helper ? __('password_mismatch') : 
                 (isset($translations['password_mismatch']) ? $translations['password_mismatch'] : "Τα συνθηματικά δεν ταιριάζουν!");
    } elseif (!preg_match('/^(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,16}$/', $password)) {
        $error = $use_language_helper ? __('password_requirements') : 
                 (isset($translations['password_requirements']) ? $translations['password_requirements'] : "Το συνθηματικό δεν πληροί τα κριτήρια ασφαλείας!");
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $verification_token = bin2hex(random_bytes(32));

        $check_stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $recover_link = "<a href='recover_password.php?lang=$language'>Ανάκτηση συνθηματικού</a>";
            $error = $use_language_helper ? __('email_exists') . " " . $recover_link : 
                     (isset($translations['email_exists']) ? $translations['email_exists'] : "Το email είναι ήδη εγγεγραμμένο! $recover_link");
        } else {
            $stmt = $mysqli->prepare("INSERT INTO users (fullname, email, password, role, verification_token, email_verified) VALUES (?, ?, ?, 'user', ?, 0)");
            if ($stmt) {
                $stmt->bind_param("ssss", $fullname, $email, $hashed_password, $verification_token);
                if ($stmt->execute()) {
                    send_verification_email($email, $verification_token, $language);
                    $success = $use_language_helper ? __('verification_sent', ['email' => htmlspecialchars($email)]) : 
                               (isset($translations['verification_sent']) ? $translations['verification_sent'] : "Ένα email επαλήθευσης έχει σταλεί στο " . htmlspecialchars($email) . ".");
                } else {
                    $error = $use_language_helper ? __('registration_error') : 
                             (isset($translations['registration_error']) ? $translations['registration_error'] : "Σφάλμα κατά την εγγραφή!");
                }
                $stmt->close();
            } else {
                $error = $use_language_helper ? __('query_error') : 
                         (isset($translations['query_error']) ? $translations['query_error'] : "Σφάλμα προετοιμασίας ερωτήματος!");
            }
        }
        $check_stmt->close();
    }
}

// Μετάφραση τίτλου σελίδας
$page_title = $use_language_helper ? __('register_title') : 
              (isset($translations['register_title']) ? $translations['register_title'] : 'Εγγραφή Χρήστη - DriveTest');
?>
<!DOCTYPE html>
<html lang="<?= $language ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <link rel="icon" type="image/ico" href="<?= BASE_URL ?>/assets/images/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/register.css">
</head>
<body>
    <div class="container">
        <div class="columns-wrapper" style="display: flex; gap: 50px;">
            <div class="form-column">
                <?php 
                if ($use_template_helper) {
                    // Χρήση του νέου συστήματος templates
                    display_template('form_register_user', [
                        'error' => $error,
                        'success' => $success,
                        'language' => $language
                    ]);
                } else {
                    // Χρήση του παραδοσιακού τρόπου
                    require_once '../templates/form_register_user.php';
                }
                ?>
            </div>
            <div class="info-box">
                <p><?= $use_language_helper ? __('info_box_text') : 
                      (isset($translations['info_box_text']) ? $translations['info_box_text'] : 'Με την εγγραφή σας σήμερα, θα έχετε πρόσβαση σε όλα τα προϊόντα DriveTest. Δεν απαιτείται πιστωτική κάρτα!') ?></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/register.js"></script>
</body>
</html>